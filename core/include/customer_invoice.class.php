<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class customer_invoice extends AJAX_library {

	public $total;
	public $current_hash;
    public $proposal_hash;
    public $invoice_hash;
    public $customer_hash;

	public $invoice_info = array();
	public $current_invoice = array();
	public $current_payment = array();

	public $invoice_status = array(
    	'0' => 	'Transmission Failed',
  	    '1' =>	'Transmission Pending',
  	    '2' =>	'Transmission In Progress',
        '3' =>	'Transmission Successful',
	    '4' =>   ''
	);
	public $invoice_icons = array(
    	'0' =>		"<img src=\"images/po_failed.gif\" title=\"Invoice Transmission Failed\" />",
		'1' =>		"<img src=\"images/po_pending.gif\" title=\"Invoice Transmission Pending\" />",
		'2' =>		"<img src=\"images/po_sending.gif\" title=\"Invoice Transmission In Progress\" />",
		'3' =>		"<img src=\"images/po_sent.gif\" title=\"Invoice Transmission Sucessful\" />",
		'4' =>		''
	);

	/**
	 * Class constructor
	 *
	 * @param  array    $param  Associative array providing class starting variables. Accepted variables are:
	 *                  user_hash  =>  calling user's id hash
	 *                  proposal_hash  =>  instantiating proposal hash
	 *                  invoice_hash   =>  instantiating invoice hash
	 * @return
	 */
	function customer_invoice() {
		global $db;

		$this->form = new form;
		$this->validate = new Validator;
		$this->db =& $db;

		foreach ( func_get_arg(0) as $_key => $_val ) {

			if ( property_exists( __CLASS__, $_key ) )
    			$this->$_key = $_val;
		}

		if ( func_num_args() == 1 && ! is_array( func_get_arg(0) ) )
            $this->current_hash = func_get_arg(0);

		if ( ! $this->current_hash )
			$this->current_hash = $_SESSION['id_hash'];

		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);

		$this->get_total();
	}

	function set_values() {

        foreach ( func_get_arg(0) as $_key => $_val ) {

            if ( property_exists( __CLASS__, $_key ) ) {

            	if ( $_key )
                	$this->$_key = $_val;
                else
                    unset($this->$_key);
            }
        }
	}

	function fetch_customer_invoices($start, $end, $order_by=NULL, $order_dir=NULL) {

		$this->invoice_info = array();

		if ( $this->active_search ) {

			$result = $this->db->query("SELECT t1.query, t1.total
									    FROM search t1
									    WHERE t1.search_hash = '{$this->active_search}'");
			if ( $row = $this->db->fetch_assoc($result) ) {

    			$this->total = $row['total'];
    			$sql = unserialize( base64_decode($row['query']) );
			}
		}

		if ( $this->total ) {

			$result = $this->db->query("SELECT customer_invoice.*,
									    CASE
										WHEN customer_invoice.invoice_to = 'C'
											THEN customers.customer_name
											ELSE NULL
										END AS customer_name,
										CASE
										WHEN customer_invoice.invoice_to = 'V'
											THEN vendors.vendor_name
											ELSE NULL
										END AS vendor_name
										FROM customer_invoice
										LEFT JOIN customers ON customers.customer_hash = customer_invoice.customer_hash
										LEFT JOIN vendors ON vendors.vendor_hash = customer_invoice.customer_hash
										WHERE " .
										( $this->customer_hash ?
											"customer_invoice.customer_hash = '{$this->customer_hash}' AND " : NULL
										) .
										( $this->proposal_hash ?
											"customer_invoice.proposal_hash = '{$this->proposal_hash}' AND " : NULL
										) .
										( $sql ?
											$sql : NULL
										) . "customer_invoice.deleted = 0 " .
										( $order_by ?
											"ORDER BY $order_by " .
											( $order_dir ?
												$order_dir : " ASC "
											) : "ORDER BY customer_invoice.invoice_no ASC"
										) . "
										LIMIT $start, $end");
			while ( $row = $this->db->fetch_assoc($result) )
				array_push($this->invoice_info, $row);
		}

	}

	function fetch_open_invoices($order_by=NULL, $order_dir=NULL) {

		$this->invoice_info = array();
		$_total = 0;

		$result = $this->db->query("SELECT
                                		t1.*,
                                        IF( ISNULL(t1.currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(t1.currency) ) AS symbol
									FROM customer_invoice t1
									WHERE " .
									( $this->customer_hash ?
										"t1.customer_hash = '{$this->customer_hash}' AND " : NULL
									) .
									( $this->proposal_hash ?
										"t1.proposal_hash = '{$this->proposal_hash}' AND " : NULL
									) . "
									t1.balance != 0 AND t1.type = 'I' AND t1.deleted = 0 " .
									( $order_by ?
										"ORDER BY $order_by " .
    									( $order_dir ?
											$order_dir : "ASC"
										) : "ORDER BY t1.invoice_no ASC"
									) );
		while ( $row = $this->db->fetch_assoc($result) ) {

			$_total++;
			array_push($this->invoice_info, $row);
		}

		return $_total;
	}

	function fetch_customer_deposits($proposal_hash) {

		$result = $this->db->query("SELECT
	                            		t1.*,
	                            		t2.*
									FROM customer_invoice t1
									LEFT JOIN customer_payment t2 ON t2.invoice_hash = t1.invoice_hash AND t2.deleted = 0
									WHERE
    									t1.customer_hash = '{$this->customer_hash}'
    									AND " .
    									( $proposal_hash != '*' ?
    										"t1.proposal_hash = '$proposal_hash' AND" : NULL
    									) . " t1.type = 'D' AND t1.deleted = 0
									ORDER BY t1.proposal_hash DESC");
		while ( $row = $this->db->fetch_assoc($result) )
			$deposit[] = $row;

		return $deposit;
	}

	function fetch_invoice_record($invoice_hash, $edit=false) {

		$this->current_invoice = array();
		unset($this->invoice_hash, $this->proposal_hash);

		$result = $this->db->query("SELECT customer_invoice.* , line_items.item_hash, COUNT(line_items.item_hash) AS total_items ,
									CASE
									WHEN customer_invoice.invoice_to = 'C'
										THEN customers.customer_name
										ELSE NULL
									END AS customer_name ,
									CASE
									WHEN customer_invoice.invoice_to = 'V'
										THEN vendors.vendor_name
										ELSE NULL
									END AS vendor_name , customers.payment_terms , customers.currency AS customer_currency
									FROM `customer_invoice`
									LEFT JOIN line_items ON line_items.invoice_hash = customer_invoice.invoice_hash
									LEFT JOIN customers ON customers.customer_hash = customer_invoice.customer_hash
									LEFT JOIN vendors ON vendors.vendor_hash = customer_invoice.customer_hash
									WHERE customer_invoice.invoice_hash = '$invoice_hash'
									GROUP BY line_items.invoice_hash");
		if ( $row = $this->db->fetch_assoc($result) ) {

			$this->current_invoice = $row;
			$this->invoice_hash = $invoice_hash;
			$this->proposal_hash = $this->current_invoice['proposal_hash'];

			if ( $this->current_invoice['vendor_name'] && ! $this->current_invoice['customer_name'] )
				$this->current_invoice['customer_name'] = $this->current_invoice['vendor_name'];

			if ( $edit )
				$this->lock = $this->content['row_lock'] = $this->p->lock("customer_invoice", $this->invoice_hash, $this->popup_id);

			if ( $this->current_invoice['bill_to_hash'] ) {

				$result = $this->db->query("SELECT t1.location_name AS location_name
											FROM locations t1
											WHERE t1.entry_hash = '{$this->current_invoice['customer_hash']}' AND t1.entry_type = 'c' AND t1.location_hash = '{$this->current_invoice['bill_to_hash']}'");
				$this->current_invoice['bill_to_name'] = $this->db->result($result, 0, 'location_name');
			}

			$this->current_invoice['line_items'] = array();
			if ( $this->current_invoice['type'] == 'I' && $this->current_invoice['total_items'] ) {

				$result = $this->db->query("SELECT t1.item_hash
											FROM line_items t1
											WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t1.invoice_hash = '{$this->invoice_hash}'
											ORDER BY t1.invoice_line_no ASC");
				while ( $row = $this->db->fetch_assoc($result) )
					$this->current_invoice['line_items'][] = $row['item_hash'];
			}

			$this->current_invoice['payments'] = array();
			$result = $this->db->query("SELECT t1.*, t2.account_name
										FROM customer_payment t1
										LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
										WHERE t1.invoice_hash = '{$this->invoice_hash}' AND t1.deleted = 0");
			while ( $row = $this->db->fetch_assoc($result) )
				$this->current_invoice['payments'][] = $row;


			return true;
		}

		return false;
	}

	function fetch_payment_record($payment_hash) {

		$this->current_payment = array();

		$result = $this->db->query("SELECT
    		                            t1.*,
    		                            t2.account_name
						  			FROM customer_payment t1
									LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
						  			WHERE t1.payment_hash = '$payment_hash'");

		if ( $row = $this->db->fetch_assoc($result) ) {

			$this->current_payment = $row;
			$this->payment_hash = $payment_hash;

			return true;
		}

		return false;
	}

	function doit_invoice() {

		$invoice_hash = $_POST['invoice_hash'];
		$trans_type = $_POST['trans_type'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$punch = $_POST['punch'];

		$accounting = new accounting($this->current_hash);

		if ( $_POST['integrate'] ) { # Set flush to 2 to return a string from trigger error if call originated from integration, otherwise 1

			$flush = 2;
			$integrate = true;
			$this->customer_hash = $_POST['customer_hash'];
		} else
			$flush = 1;

		if ( $trans_type == 'D' ) {

			$delete = $_POST['delete'];
			$check_no = $_POST['check_no'];
			$receipt_date = $_POST['receipt_date'];
			$amount = preg_replace('/[^-.0-9]/', "", $_POST['amount']);
			$comments = $_POST['comments'];
			$payment_hash = $_POST['payment_hash'];
			$receive_from_unapplied = $_POST['receive_from_unapplied'];
			$payment_account_hash = $_POST['payment_account_hash'];
			$submit_btn = ($delete ? 'rcv_dep_rm' : 'rcv_dep');

			$currency = $_POST['currency'];

            if ( $invoice_hash ) {

                if ( ! $this->fetch_invoice_record($invoice_hash) )
                	return $this->__trigger_error("System error encountered when attempting to lookup deposit record. Please reload window and try again. <!-- Tried to fetch deposit [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

                if ( $payment_hash ) {

                    if ( ! $this->fetch_payment_record($payment_hash) )
                    	return $this->__trigger_error("System error encountered when attempting to lookup payment record. Please reload window and try again. <!-- Tried to fetch payment [ $payment_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

                    if ( $this->current_payment['applied_from'] ) { # Make sure that the applied from invoice is valid

                        $r = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
                                               FROM customer_invoice t1
                                               WHERE t1.invoice_hash = '{$this->current_payment['applied_from']}' AND customer_invoice.deleted = 0");
                        $applied_from_valid = $this->db->result($r, 0, 'Total');
                    }
                }

            } elseif ( ! $invoice_hash && $receive_from_unapplied ) {

                $r = $this->db->query("SELECT t1.account_hash AS payment_account_hash
                                       FROM customer_payment t1
                                       WHERE t1.invoice_hash = '$receive_from_unapplied'");
                $payment_account_hash = $this->db->result($r, 0, 'payment_account_hash');
            }

			# Error 410 means that the applied from account hash is missing
			# Error 400 means that the account has from the current payment is missing
			if ( ( ! $this->payment_hash && ! $payment_account_hash ) || ( $this->payment_hash && ! $payment_account_hash && ( ! $this->current_payment['applied_from'] || ( $this->current_payment['applied_from'] && ! $applied_from_valid ) ) ) ) {

                if ( $_POST['delete'] )
                    return $this->__trigger_error("This receipt is missing the account that it was received into.<br />We appologize, however this action requires you to contact DealerChoice support." . ( ! $this->current_payment['applied_from'] || ( $this->current_payment['applied_from'] && ! $applied_from_valid ) ? " #410" : "#400" ), E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                else
                    return $this->__trigger_error("You have not indicated in which account you want to receive this payment.<br />Make sure you have defined at least 1 checking account. You can do this by checking the 'Will you write checks from this account' box from within the chart of accounts 'General' tab.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
			}

			# Deleting a customer deposit
			if ( $delete ) {

				if ( bccomp($this->current_invoice['balance'], $this->current_invoice['amount'], 2) )
					return $this->__trigger_error("This deposit has been applied in form of payment to one or more receivables and cannot be deleted.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

				if ( ! $this->db->start_transaction() )
					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				$posting_date = $this->current_payment['receipt_date'];

				if ( ! $this->db->query("UPDATE customer_invoice t1
        								 SET
	        								 t1.timestamp = UNIX_TIMESTAMP(),
	        								 t1.last_change = '{$this->current_hash}',
	        								 t1.deleted = 1,
	        								 t1.deleted_date = CURDATE()
        								 WHERE t1.invoice_hash = '{$this->invoice_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                }

				if ( ! $this->db->query("UPDATE customer_payment t1
        								 SET
        								 t1.timestamp = UNIX_TIMESTAMP(),
        								 t1.last_change = '{$this->current_hash}',
        								 t1.deleted = 1,
        								 t1.deleted_date = CURDATE()
        								 WHERE t1.payment_hash = '{$this->payment_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                }

                $accounting->start_transaction( array(
                    'customer_hash'     =>  $this->current_invoice['customer_hash'],
                    'ar_invoice_hash'   =>  $invoice_hash,
                    'proposal_hash'     =>  $this->current_invoice['proposal_hash'],
                    'currency'          =>  $this->current_invoice['proposal_hash'],
                    'exchange_rate'     =>  $this->current_invoice['exchange_rate']
                ) );

                $audit_id = $accounting->audit_id();

				if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_CUST_DEPOSIT_ACCT, bcmul($this->current_invoice['amount'], -1, 2), 'AD', 'Customer deposit deleted.') )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				if ( $this->current_payment['applied_from'] ) { # If the deposit being deleted was received from an unapplied deposit, then return the funds there

					$tmp_obj = new customer_invoice($this->current_hash);
					if ( ! $tmp_obj->fetch_invoice_record($this->current_payment['applied_from']) )
						return $this->__trigger_error("System error encountered when attempting to lookup deposit record. Please reload window and try again. <!-- Tried fetching invoice [ {$this->current_payment['applied_from']} ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					$new_balance = bcadd($tmp_obj->current_invoice['balance'], $this->current_invoice['balance'], 2);

					if ( ! $this->db->query("UPDATE customer_invoice t1
        									 SET
        									 t1.timestamp = UNIX_TIMESTAMP(),
        									 t1.last_change = '{$this->current_hash}',
        									 t1.balance = '$new_balance'
        									 WHERE t1.invoice_hash = '{$tmp_obj->invoice_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                    }

					if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_CUST_DEPOSIT_ACCT, $this->current_invoice['amount'], 'AD', 'Customer deposit reapplied to unapplied deposits.') )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				} else {

					if ( ! $accounting->exec_trans($audit_id, $posting_date, $this->current_payment['account_hash'], bcmul($this->current_invoice['amount'], -1, 2), 'AD', 'Customer deposit deleted.') )
    					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
				}

				if ( ! $accounting->end_transaction() )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$this->db->end_transaction();

				$feedback = "Your deposit has been deleted";

			} else {

				$submit_btn = 'rcv_dep';

				$sql_1 = $sql_2 = array();

				if ( $receive_from_unapplied )
                    unset($check_no);

				# Editing an existing customer deposit
				if ( ( ( $check_no && ! $receive_from_unapplied ) || ( $receive_from_unapplied && ! $check_no ) ) && $amount && $receipt_date ) {

					if ( $invoice_hash ) {

						if ( bccomp($this->current_invoice['balance'], $this->current_invoice['amount'], 2) )
							return $this->__trigger_error("This deposit cannot be adjusted because part of it has been applied to a customer receipt.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

                        if ( $this->current_invoice['currency'] && $this->current_invoice['exchange_rate'] )
                            $amount = currency_exchange($amount, $this->current_invoice['exchange_rate'], 1);

						if ( $this->current_payment['applied_from'] ) {

							$tmp_obj = new customer_invoice($this->current_hash);
							if ( ! $tmp_obj->fetch_invoice_record($this->current_payment['applied_from']) )
								return $this->__trigger_error("System error encountered when attempting to lookup invoice record. Please reload window and try again. <!-- Tried fetching invoice [ {$this->current_payment['applied_from']} ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

							if ( bccomp($amount, bcadd($this->current_invoice['amount'], $tmp_obj->current_invoice['balance'], 2), 2) == 1 )
								return $this->__trigger_error("The deposit amount you entered exceeds the amount of the original unapplied deposit from which this deposit was created. Please confirm you are adjusting the correct deposit record.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);
						}

						$posting_date = $this->current_payment['receipt_date'];

						if ( $receipt_date != $this->current_invoice['invoice_date'] ) { # If we're changing the date

							if ( $accounting->period_ck($receipt_date) )
    							return $this->__trigger_error($accounting->closed_err, E_USER_NOTICE, __FILE__, __LIN__, 1, false, $submit_btn);

    						# TODO: Reverse original transaction for GAAP compliance
                            ( $receipt_date != $this->current_invoice['invoice_date'] ? array_push($sql_1, "t1.invoice_date = '$receipt_date'") : NULL );
                            ( $receipt_date != $this->current_invoice['invoice_date'] ? array_push($sql_2, "t1.receipt_date = '$receipt_date'") : NULL );
						}

                        if ( bccomp(0, $amount, 2) == 1 )
                            return $this->__trigger_error("The receipt amount entered is invalid. Please confirm that the amount entered is a non negative number.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

                        if ( ! $this->db->start_transaction() )
                            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

                        if ( bccomp($amount, $this->current_invoice['amount'], 2) ) {

							$adj = bcsub($amount, $this->current_invoice['amount'], 2);
							$balance = bcadd($this->current_invoice['balance'], $adj, 2);

							$accounting->start_transaction( array(
								'customer_hash'     =>  $this->current_invoice['customer_hash'],
								'ar_invoice_hash'   =>  $this->invoice_hash,
								'ar_payment_hash'   =>  $payment_hash,
								'proposal_hash'     =>  $this->current_invoice['proposal_hash'],
                                'currency'          =>  $this->current_invoice['currency'],
                                'exchange_rate'		=>  $this->current_invoice['exchange_rate'],
                            ) );

							$audit_id = $accounting->audit_id();

							if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_CUST_DEPOSIT_ACCT, $adj, 'AD', 'Adjustment to previously received customer deposit.') )
							    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

							if ( $tmp_obj ) { # If deposit was received from unapplied deposit make adjustments to unapplied instead of cash

								$accounting->setTransIndex( array(
    								'proposal_hash',
    								'ar_payment_hash'
								), 1);

								$accounting->setTransIndex( array(
    								'ar_invoice_hash'   =>  $this->current_payment['applied_from']
								) );

								if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_CUST_DEPOSIT_ACCT, bcmul($adj, -1, 2), 'AD', 'Adjustment to original unapplied deposit') )
								    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

								$tmp_balance = bcadd($tmp_obj->current_invoice['balance'], bcmul($adj, -1, 2), 2);
								if ( ! $this->db->query("UPDATE customer_invoice t1
        												 SET
	        												 t1.timestamp = UNIX_TIMESTAMP(),
	        												 t1.last_change = '{$this->current_hash}',
	        												 t1.balance = '$tmp_balance'
        												 WHERE t1.invoice_hash = '{$this->current_payment['applied_from']}'")
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                                }

							} else {

								if ( ! $accounting->exec_trans($audit_id, $posting_date, $this->current_invoice['payments'][0]['account_hash'], $adj, 'AD', 'Adjustment to previously received customer deposit.') )
    								return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
							}

	                        if ( ! $accounting->end_transaction() )
                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

	                        array_push($sql_1, "t1.amount = '$amount', t1.balance = '$balance'");
	                        array_push($sql_2, "t1.receipt_amount = '$amount'");
                        }

                        ( $check_no != $this->current_payment['check_no'] ? array_push($sql_2, "t1.check_no = '$check_no'") : NULL );
                        ( strlen( base64_encode($comments) ) != strlen( base64_encode( stripslashes($this->current_payment['comments']) ) ) ? array_push($sql_2, "t1.comments = '" . addslashes($comments) . "'") : NULL );

                        if ( $sql_1 ) {

							if ( ! $this->db->query("UPDATE customer_invoice t1
        											 SET
	        											 t1.timestamp = UNIX_TIMESTAMP(),
	        											 t1.last_change = '{$this->current_hash}',
	        											 " . implode(", ", $sql_1) . "
        											 WHERE t1.invoice_hash = '{$this->invoice_hash}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                            }
                        }

						if ( $sql_2 ) {

							if ( ! $this->db->query("UPDATE customer_payment t1
        											 SET
            											 t1.timestamp = UNIX_TIMESTAMP(),
            											 t1.last_change = '{$this->current_hash}',
            											 " . implode(", ", $sql_2) . "
        											 WHERE t1.payment_hash = '{$this->payment_hash}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                            }
						}

						$this->db->end_transaction();

    					if ( $sql_1 || $sql_2 )
    						$feedback = "Your customer deposit has been updated.";
    					else
                            $feedback = "No changes have been made.";

					} else {

						$accounting = new accounting($this->current_hash);

						if ( $accounting->period_ck($receipt_date) )
							return ( $integrate ? $accounting->closed_err : $this->__trigger_error($accounting->closed_err, E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn));

						$audit_id = $accounting->audit_id();

						if ( ! $this->db->start_transaction() )
							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						if ( $receive_from_unapplied ) {

							unset($payment_account_hash);
							$tmp_obj = new customer_invoice($this->current_hash);
							if ( ! $tmp_obj->fetch_invoice_record($receive_from_unapplied) )
								return $this->__trigger_error("System error encountered when attempting to lookup deposit record. Please reload window and try again. <!-- Tried fetching invoice [ $receive_from_unapplied ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

							if ( $tmp_obj->current_invoice['currency'] && $tmp_obj->current_invoice['exchange_rate'] ) {

								$sys = new system_config();

								$payment_currency = $tmp_obj->current_invoice['currency'];
								$payment_rate = $tmp_obj->current_invoice['exchange_rate'];

								$amount = currency_exchange($amount, $payment_rate, 1);
							}

							if ( bccomp($amount, $tmp_obj->current_invoice['balance'], 2) == 1 )
								return $this->__trigger_error("The amount indicated exceeds the balance remaining on the deposit. Please make sure your amount is less than or equal to \$" . number_format( ( $payment_currency ? currency_exchange($tmp_obj->current_invoice['balance'], $payment_rate) : $tmp_obj->current_invoice['balance'] ), 2) . ".", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

							$accounting->start_transaction( array(
								'proposal_hash'      =>  $this->proposal_hash,
								'ar_invoice_hash'    =>  $receive_from_unapplied,
								'customer_hash'      =>  $tmp_obj->current_invoice['customer_hash'],
								'currency'           =>  $payment_currency,
								'exchange_rate'      =>  $payment_rate
							) );

							$balance = _round(bcsub($tmp_obj->current_invoice['balance'], $amount, 4), 2);
							$check_no = $tmp_obj->current_invoice['payments'][0]['check_no'];
							if ( ! $this->db->query("UPDATE customer_invoice t1
        											 SET
	        											 t1.timestamp = UNIX_TIMESTAMP(),
	        											 t1.last_change = '{$this->current_hash}',
	        											 t1.balance = '$balance'
        											 WHERE t1.invoice_hash = '$receive_from_unapplied'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                            }

							if ( ! $accounting->exec_trans($audit_id, $receipt_date, DEFAULT_CUST_DEPOSIT_ACCT, bcmul($amount, -1, 2), 'CR', 'Receiving a customer deposit from unapplied') )
								return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						} elseif ( $currency ) {

							$sys = new system_config();

                            if ( ! $sys->fetch_currency($currency) )
                                return $this->__trigger_error("System error encountered when attempting to lookup currency record [ $currency ]. Please check with your system administrator and make sure currency tables have been properly configured. <!-- Tried fetching currency [ $currency ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

                            $payment_rate = $sys->current_currency['rate'];
                            $payment_currency = $sys->currency_id;
						}

						if ( $payment_currency ) {

                            if ( ! defined('MULTI_CURRENCY') || ! $sys->home_currency )
                                return $this->__trigger_error("The default home currency has not been defined in your system configuration! This invoice was created with multiple currencies enabled and cannot be edited without multiple currencies enabled.<br /><br />Please check with your system administrator.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

                            if ( ! valid_account($this->db, $sys->home_currency['account_hash']) )
                                return $this->__trigger_error("You have not defined a valid chart of account to be used for capturing gain/loss on currency exchange.", E_USER_NOTICE, __FILE__, __LINE__, $flush, $submit_btn);

                            if ( ! $receive_from_unapplied )
                                $amount = currency_exchange($amount, $payment_rate, 1);
						}

						$invoice_hash = rand_hash('customer_invoice', 'invoice_hash');
						$payment_hash = rand_hash('customer_payment', 'payment_hash');

                        $accounting->setTransIndex( array(
                            'proposal_hash'       =>  $this->proposal_hash,
                            'ar_invoice_hash'     =>  $invoice_hash,
                            'ar_payment_hash'     =>  $payment_hash,
                            'customer_hash'       =>  $this->customer_hash,
                            'currency'            =>  $payment_currency,
                            'exchange_rate'       =>  $payment_rate
                        ) );

						if ( ! $accounting->exec_trans($audit_id, $receipt_date, DEFAULT_CUST_DEPOSIT_ACCT, $amount, 'CR', 'Receiving a new customer deposit') )
						    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						if ( ! $receive_from_unapplied ) {

							if ( ! $accounting->exec_trans($audit_id, $receipt_date, $payment_account_hash, $amount, 'CR', 'Receiving a new customer deposit') )
							    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
						}

						if ( ! $this->db->query("INSERT INTO customer_invoice
                                                 (
	                                                 timestamp,
	                                                 last_change,
	                                                 invoice_hash,
	                                                 type,
	                                                 proposal_hash,
	                                                 customer_hash,
	                                                 invoice_date,
	                                                 amount,
	                                                 balance " .
	                                                 ( $payment_currency ?
	                                                     ",
	                                                     currency,
	                                                     exchange_rate,
	                                                     currency_account" : NULL
	                                                 ) . "
                                                 )
        										 VALUES
        										 (
	        										 UNIX_TIMESTAMP(),
	        										 '{$this->current_hash}',
	        										 '$invoice_hash',
	        										 'D',
	        										 '{$this->proposal_hash}',
	        										 '{$this->customer_hash}',
	        										 '$receipt_date',
	        										 '$amount',
	        										 '$amount' " .
	        										 ( $payment_currency ?
	            										 ",
	            										 '$payment_currency',
	            										 '$payment_rate',
	            										 '{$sys->home_currency['account_hash']}'" : NULL
	            									 ) . "
            									 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                        }

						if ( ! $this->db->query("INSERT INTO customer_payment
        										 (
	        										 timestamp,
	        										 last_change,
	        										 payment_hash,
	        										 invoice_hash,
	        										 receipt_amount,
	        										 account_hash,
	        										 applied_from,
	        										 receipt_date,
	        										 check_no,
	        										 comments " .
	                        						 ( $payment_currency ?
	                            						 ",
	                            						 currency,
	                            						 exchange_rate" : NULL
	                        						 ) . "
                        						 )
        										 VALUES
        										 (
	        										 UNIX_TIMESTAMP(),
	        										 '{$this->current_hash}',
	        										 '$payment_hash',
	        										 '$invoice_hash',
	        										 '$amount', " .
	        										 ( $payment_account_hash ?
    	        										 "'$payment_account_hash'" : "NULL"
	        										 ) . ", " .
	        										 ( $receive_from_unapplied ?
    	        										 "'$receive_from_unapplied'" : "NULL"
    	        								     ) . ",
	        										 '$receipt_date',
	        										 '$check_no',
	        										 '" . addslashes($comments) . "' " .
	        										 ( $payment_currency ?
	            										 ",
	            										 '$payment_currency',
	            										 '$payment_rate'" : NULL
	            									 ) . "
            									 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                        }

						if ( ! $accounting->end_transaction() )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						$this->db->end_transaction();

						$feedback = "Your customer deposit has been recorded";
					}

				} else {

					if ( ! $check_no ) $this->set_error("err1{$this->popup_id}");
					if ( ! $receipt_date ) $this->set_error("err4{$this->popup_id}");
					if ( ! $amount ) $this->set_error("err3{$this->popup_id}");
					return $this->__trigger_error("You left some required fields blank! Please check the indicated fields and try again.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);
				}
			}

		} elseif ( $trans_type == 'I' ) {

			$submit_btn = 'invoice_btn';
			if ( $this->proposals->current_proposal['order_type'] == 'D' )
				$direct_bill = true;

			$new_item['type'] = 'I';
			$total_amt = $_POST['total_amt'];
            $invoice_created = array();

			for ( $z = 0; $z < count($total_amt); $z++ ) {

				$new_item = array();
				$new_item['transmit_type'] = $_POST['transmit_type'][$z];

				switch ( $new_item['transmit_type'] ) {

					case 'F':

					$new_item['transmit_to'] = $_POST['transmit_to_fax'][$z];
					if ( ! $this->validate->is_phone( $new_item['transmit_to'] ) ) {

						$this->set_error("err4{$z}{$this->popup_id}");
						return $this->__trigger_error("The fax number you entered is invalid. Please enter a valid fax number (555-555-5555) to continue.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
					}

					break;

					case 'E':

					$new_item['transmit_to'] = $_POST['transmit_to_email'][$z];
					if ( ! $this->validate->is_email($new_item['transmit_to']) ) {

						$this->set_error("err4{$z}{$this->popup_id}");
						return $this->__trigger_error("The email address you entered is invalid. Please enter a valid email address (name@domain.com) to continue.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
					}

					break;

					case 'M':

					$new_item['transmit_to'] = $_POST['transmit_to_mail'][$z];

					break;
				}

				$new_item['remit_to_addr'] = htmlspecialchars( $_POST['remit_to_address'][$z][ $_POST['remit_to'][$z] ], ENT_QUOTES);
				$new_item['bill_to_hash'] = $_POST['bill_to_hash'];
				$new_item['proposal_hash'] = $this->proposals->proposal_hash;
				$invoice_to = $_POST['invoice_to'][$z];

				if ( $invoice_to == 'V' ) {

					if ( $invoice_to = $_POST["invoice_to_vendor_hash_{$z}"] )
                        $new_item['customer_hash'] = $invoice_to;

					if ( ( $_POST["invoice_to_vendor_{$z}"] && ! $_POST["invoice_to_vendor_hash_{$z}"] ) || ! $_POST["invoice_to_vendor_{$z}"] ) {

						$this->set_error("err6{$z}{$this->popup_id}");
						return $this->__trigger_error( ( ! $_POST["invoice_to_vendor_{$z}"] ? "You did not select the vendor to invoice! Please select a valid vendor." : "We can't seem to find the vendor you selected below. Please select a valid vendor." ), E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
					}

					$new_item['invoice_to'] = 'V';

				} else
				    $new_item['customer_hash'] = $invoice_to;

				unset($new_item['currency'], $new_item['exchange_rate'], $new_item['currency_account']);

                if ( defined('MULTI_CURRENCY') && $_POST["currency_{$z}"] ) {

                    $sys = new system_config();
                    if ( ! $sys->home_currency )
                        return $this->__trigger_error("The default home currency has not been defined in your system configuration! This invoice was created with multiple currencies enabled and cannot be edited without multiple currencies enabled.<br /><br />Please check with your system administrator.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

                    if ( ! $sys->fetch_currency($_POST["currency_{$z}"]) )
                        return $this->__trigger_error("Unable to find a currency exchange rule/rate for the payment currency [ {$_POST["currency_{$z}"]} ]. Please check with your system administrator to make sure currency exchange settings have been properly configured.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

                    $new_item['currency'] = $sys->currency_id;
	                $new_item['exchange_rate'] = $sys->current_currency['rate'];
	                $new_item['currency_account'] = $sys->home_currency['account_hash'];

                    if ( ! $sys->home_currency['account_hash'] )
                        return $this->__trigger_error("System error encountered when attempting to lookup currency exchange gain/loss account. Please check with your system administrator to make sure currency exchange settings have been properly configured.", E_USER_ERROR, __FILE__, __LINE__, $flush, $submit_btn);
                }

				$new_item['invoice_date'] = $_POST["invoice_date_{$z}"];
				$print_logo = $_POST["print_logo_{$z}"];
				$use_logo = $_POST["use_logo_{$z}"];

				if ( ! $this->db->start_transaction() )
					return $this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				$accounting = new accounting($this->current_hash);

				if ( $accounting->period_ck( $new_item["invoice_date_{$z}"] ) )
					return $this->__trigger_error($accounting->closed_err, E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

				$invoice_date = $new_item['invoice_date'];
				if ( $direct_bill ) {

					if ( ! $new_item['invoice_to'] )
    					$new_item['invoice_to'] = 'C';
    				if ( ! $new_item['customer_hash'] )
    					$new_item['customer_hash'] = $invoice_to[$z];

					$total_sell = $total_ar_amt = 0;
					$direct_invoice_type = $_POST['direct_invoice_type'][$z];
					while ( list($direct_invoice_vendor, $invoice_type) = each($direct_invoice_type) ) {

						$direct_print_pref[ $direct_invoice_vendor ] = $invoice_type;
						if ( $invoice_type == 'S' || $invoice_type == 'O' )
							$total_sell = bcadd($total_sell, $total_amt[$z][$direct_invoice_vendor]['sell'], 2);
						else {

							$is_direct_punch = true;
							$total_sell = bcadd($total_sell, $total_amt[$z][$direct_invoice_vendor]['commission'], 2);
						}
						if ( $invoice_type == 'S' || $invoice_type == 'C' )
							$new_item['invoice_to'] = 'V';
					}
					if ( ( ! $punch && $new_item['invoice_to'] == 'V' ) || ( $punch && $is_direct_punch ) )
						$new_item['direct_bill'] = 1;

					$new_item['amount'] = $total_ar_amt = $total_sell;

				} else {

					$new_item['amount'] = $total_ar_amt = $total_amt[$z];
					$new_item['customer_hash'] = $invoice_to;
				}

				$item_list = $_POST['item'][$z];
				$lines = new line_items($this->proposals->proposal_hash, $punch);

				if ( ! $direct_bill ) {

                    list( $total_tax, $tax_hash, $tax_local, $indiv_tax) = $lines->calc_tax($item_list);
                    if ( $total_tax ) {

                        $sys = new system_config($this->current_hash);
                        while ( list($tax_acct_hash) = each($tax_hash) ) {

                            if ( ! $sys->fetch_tax_rule($tax_acct_hash) )
                                return $this->__trigger_error("System error encountered when attempting to lookup valid tax rule for line item sale tax. Please check with your system administrator and make sure all tax rules have been properly configured.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

                            if ( $sys->current_tax['incomplete'] )
                                return $this->__trigger_error("The liability account for tax rule '{$sys->current_tax['state_name']}" . ( $sys->current_tax['local'] ? ", {$sys->current_tax['local']}" : NULL ) . "' has not been completely configured. Please contact your system administrator or finance department before continuing.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                        }
                    }

				} else
					$total_tax = 0;

				if ( ! defined('SMALL_ORDER_FEE_INCOME_ACCT') || ! defined('SMALL_ORDER_FEE_COGS_ACCT') || ! defined('FREIGHT_INCOME_ACCT') || ! defined('FREIGHT_COGS_ACCT') || ! defined('FUEL_SURCHARGE_INCOME_ACCT') || ! defined('FUEL_SURCHARGE_COGS_ACCT') || ! defined('CBD_INCOME_ACCT') || ! defined('CBD_COGS_ACCT') )
					return $this->__trigger_error("One or more of the misc vendor fees has not been mapped to a valid chart of account. Please contact your system administrator or finance department before continuing.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				if ( $total_tax ) {

					$new_item['tax'] = $total_tax;
					$new_item['amount'] = bcadd($new_item['amount'], $total_tax, 4);
					$total_ar_amt = bcadd($total_ar_amt, $total_tax, 4);
				}

				$new_item['deposit_applied'] = $deposit_applied = preg_replace('/[^.0-9]/', "", $_POST['deposit_used'][$z]);
				if ( $new_item['deposit_applied'] )
					$new_item['balance'] = $new_item['amount'] - $new_item['deposit_applied'];
				else
					$new_item['balance'] = $new_item['amount'];

				$customer_balance = $new_item['balance'];
				if ( bccomp($new_item['amount'], 0, 2) == 1 && ! $new_item['transmit_to'] ) {

					$this->set_error("err4{$z}{$this->popup_id}");
					return $this->__trigger_error("You left some required fields blank! Please check the indicated fields and try again.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);
				}

				$new_item['install_hash'] = $this->proposals->current_proposal['install_addr_hash'];
				$new_item['install_city'] = $this->proposals->current_proposal['install_addr_city'];
				$new_item['install_state'] = $this->proposals->current_proposal['install_addr_state'];
				$new_item['install_zip'] = $this->proposals->current_proposal['install_addr_zip'];
				$new_item['install_country'] = $this->proposals->current_proposal['install_addr_country'];
				
				$amount = $new_item['amount'];
				
				if($amount == 0){
					$new_item['paid_in_full'] = 1;
				}

				$total_deposit = 0;
				$deposits = $this->fetch_customer_deposits($this->proposals->proposal_hash);
				for ( $i = 0; $i < count($deposits); $i++ )
					$total_deposit = bcadd($total_deposit, $deposits[$i]['balance'], 2);

				if ( bccomp($new_item['deposit_applied'], $total_deposit, 2) == 1 || ( bccomp($new_item['deposit_applied'], $new_item['amount'], 2) == 1 && bccomp(0, $new_item['amount'], 2) == 1 ) ) {

					$this->set_error("err1{$z}{$this->popup_id}");
					$this->set_error("err5{$z}{$this->popup_id}");
					return $this->__trigger_error( ( bccomp($new_item['deposit_applied'], $new_item['amount'], 2) == 1 ? "Your invoice will result in a negative balance. Invoices cannot have a negative balance." : "Your deposit amount is greater than exists under this proposal! Make sure your applied deposit does not exceed \$" . number_format($total_deposit, 2) . "."), E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);
				}

				$invoice_no = fetch_sys_var('NEXT_INVOICE_NO');
				$invoice_seed = fetch_sys_var('INVOICE_SEED');
				if ( ! $invoice_no ) {

					$r = $this->db->query("SELECT t1.invoice_no
									       FROM customer_invoice t1
										   ORDER BY t1.invoice_no DESC
										   LIMIT 1");
					if ( $invoice_no = $this->db->result($r, 0, 'invoice_no'))
						$invoice_no++;
					else
						$invoice_no = 1000;
				}
				

				while ( $this->invoice_exists($invoice_seed, $invoice_no) > 0 )
					$invoice_no++;
				
				

				$invoice_hash = rand_hash('customer_invoice', 'invoice_hash');

                $accounting->start_transaction( array(
	                'proposal_hash'     =>  $this->proposals->proposal_hash,
	                'customer_hash'     =>  $new_item['customer_hash'],
	                'ar_invoice_hash'   =>  $invoice_hash,
                    'currency'          =>  $new_item['currency'],
                    'exchange_rate'     =>  $new_item['exchange_rate']
                ) );

                $audit_id = $accounting->audit_id();

				$item_value_array = $new_item;
				if($punch && $new_item['invoice_to'] == 'C'){
					$new_item['customer_hash'] = $this->customer_hash;
				}
				$new_item = $this->db->prepare_query($new_item, "customer_invoice", 'INSERT');
				
				$users = new system_config();
				//For each of the users, fetch their record
				$users->fetch_user_record($this->current_hash);

				//($users->current_user['unique_id'] ? $users->current_user['unique_id'] : NULL ) .
				if(defined('ACTIVATE_SALES_REP_ID') && ACTIVATE_SALES_REP_ID == 1){
					$sales_rep_unique_id = $this->proposals->current_proposal['sales_rep_unique_id'];
				}
				$_invoice_no = ( defined('INVOICE_SEED') ? INVOICE_SEED : NULL ).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL) . $invoice_no ;
				
				//here
				if ( ! $this->db->query("INSERT INTO customer_invoice
			                         (
				                         timestamp,
				                         last_change,
				                         invoice_hash,
				                         invoice_no,
				                         punch,
				                         " . implode(", ", array_keys($new_item) ) . "
				)
						VALUES
						(
						UNIX_TIMESTAMP(),
						'{$this->current_hash}',
						'$invoice_hash',
						'$_invoice_no',
						'$punch',
						" . implode(", ", array_values($new_item) ) . "
						)")
						) {
				
						return $this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
				}
                
                if($amount == 0){
                	$date = date("Y-m-d");
                	if ( ! $this->db->query("INSERT INTO customer_payment
        										 (
	        										 timestamp,
	        										 last_change,
	        										 payment_hash,
	        										 invoice_hash,
	        										 receipt_amount,
	        										 account_hash,
	        										 applied_from,
	        										 receipt_date,
	        										 check_no,
	        										 comments " .
						                			( $payment_currency ?
						                					",
							                            						 currency,
							                            						 exchange_rate" : NULL
						                			) . "
								                	)
								                			VALUES
								                			(
								                			UNIX_TIMESTAMP(),
								                			'{$this->current_hash}',
								                			'$payment_hash',
								                			'$invoice_hash',
								                			'$amount', " .
								                			( $payment_account_hash ?
								                			"'$payment_account_hash'" : "NULL"
								                			) . ", " .
								                			( $receive_from_unapplied ?
								                			"'$receive_from_unapplied'" : "NULL"
								                	) . ",
								                	'$date',
								                	'$check_no',
								                	'" . addslashes($comments) . "' " .
								                	( $payment_currency ?
								                			",
								                			'$payment_currency',
								                			'$payment_rate'" : NULL
								                	) . "
								                	)")
                	) {
                
                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                }
                }
                
                

				array_push($invoice_created, $_invoice_no);

				if ( float_length($total_ar_amt) > 4 ) # Trac #1258
                    $total_ar_amt = _round($total_ar_amt, 4);

				if ( ! $accounting->exec_trans($audit_id, $invoice_date, DEFAULT_AR_ACCT, $total_ar_amt, 'AR', "Customer Invoice: $_invoice_no") )
                    return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

                # Sales Tax
				if ( $total_tax ) {

					$tax_hash_update = array();
					$sys = new system_config($this->current_hash);

					reset($tax_hash);
					while ( list($tax_acct_hash, $tax_amt) = each($tax_hash) ) {

						if ( ! $sys->fetch_tax_rule($tax_acct_hash) )
							return $this->__trigger_error("System error encountered when attempting to lookup sales tax rule. Please check with your system administrator and make sure all sales tax rules have been properly configured.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						$tax_hash_update[ $tax_acct_hash ] = $sys->current_tax['account_hash'];
						if ( ! $accounting->exec_trans($audit_id, $invoice_date, $sys->current_tax['account_hash'], $tax_amt, 'AR', $sys->current_tax['state_name'] . ( $sys->current_tax['local'] ? ", " . ( $lines->tax_country == 'CA' ? strtoupper($sys->current_tax['local']) : $sys->current_tax['local'] ) : NULL ) . " (" . ( $sys->current_tax['rate'] * 100 ) . "%) Tax") )
		                    return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
					}
				}

				$vendors = new vendors($this->current_hash);
                $dep_hash_hidden = $_POST['deposit_hash'];
                $total_deposit_applied = $deposit_applied;

				if ( $dep_hash_hidden && bccomp($deposit_applied, 0, 2) == 1 ) {

                    $tmp_obj = new customer_invoice($this->current_hash);
					for ( $i = 0; $i < count($dep_hash_hidden); $i++ ) {

						if ( ! $tmp_obj->fetch_invoice_record( $dep_hash_hidden[$i] ) )
							return $this->__trigger_error("System error encountered when attempting to lookup deposit invoice. Please reload window and try again.<!-- Tried to fetch invoice [{$dep_hash_hidden[$i]}]-->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						$balance = $tmp_obj->current_invoice['balance'] - $deposit_applied;
						if ( bccomp($balance, 0, 2) == -1 ) {

							$balance = 0;
							$deposit_applied = $deposit_applied - $tmp_obj->current_invoice['balance'];
						} else
							$deposit_applied = $deposit_applied - $tmp_obj->current_invoice['balance'];

						if ( ! $this->db->query("UPDATE customer_invoice t1
												 SET
													 t1.timestamp = UNIX_TIMESTAMP(),
													 t1.last_change = '{$this->current_hash}',
													 t1.balance = '$balance'
												 WHERE t1.invoice_hash = '{$tmp_obj->invoice_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                        }

						if ( bccomp($deposit_applied, 0, 2) == -1 )
							break 1;
					}
					if ( ! $accounting->exec_trans($audit_id, $invoice_date, DEFAULT_AR_ACCT, bcmul($total_deposit_applied, -1, 2), 'AR', 'Customer deposit applied') )
                        return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
					if ( ! $accounting->exec_trans($audit_id, $invoice_date, DEFAULT_CUST_DEPOSIT_ACCT, bcmul($total_deposit_applied, -1, 2), 'AR', 'Customer deposit applied') )
					    return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
				}

				$line_no = 0;
				for ( $i = 0; $i < count($item_list); $i++ ) {

					if ( ! $lines->fetch_line_item_record($item_list[$i]) )
						return $this->__trigger_error("System error encountered during invoice line item lookup. Unable to fetch line item for invoice tag. Please reload window and try again. <!--Item hash: {$item_list[$i]}-->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					if ( $lines->current_line['status'] == 2 )
                        return $this->__trigger_error("One or more of the items to be invoiced are already invoiced and cannot be processed under the new invoice. Please confirm that you have selected the correct line items to be invoiced and try again.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					# If this is a direct bill, mark the line to indicate how much was actually invoiced
					$invoice_line_type = '';
					if ( $direct_bill ) {

						if ( $direct_invoice_type[ $lines->current_line['vendor_hash'] ] == 'C' )
							$invoice_line_type = 'C';
						else
							$invoice_line_type = 'S';
					}
					if ( $total_tax && bccomp($total_tax, 0, 2) == 1 ) {

						$r = $this->db->query("SELECT t1.obj_id, t1.tax_hash
						                       FROM tax_collected t1
						                       WHERE t1.proposal_hash = '{$this->proposals->proposal_hash}' AND t1.item_hash = '{$item_list[$i]}'");
						while ( $row = $this->db->fetch_assoc($r) ) {

	                        if ( ! $this->db->query("UPDATE tax_collected t1
        	                                         SET
            	                                         t1.invoice_hash = '$invoice_hash',
            	                                         t1.account_hash = '{$tax_hash_update[ $row['tax_hash'] ]}'
        	                                         WHERE t1.obj_id = '{$row['obj_id']}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                            }
						}

					} else {

                        if ( ! $this->db->query("DELETE FROM tax_collected
                                                 WHERE proposal_hash = '{$this->proposals->proposal_hash}' AND item_hash = '{$item_list[$i]}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                        }
					}

					if ( $lines->current_line['item_code'] && ! $lines->current_line['product_hash'] ) {

						switch ( $lines->current_line['item_code'] && ! $lines->current_line['product_hash'] ) {

							case 'sof':

							$income_acct = SMALL_ORDER_FEE_INCOME_ACCT;
							$exp_acct = SMALL_ORDER_FEE_COGS_ACCT;

							break;

							case 'frf':

							$income_acct = FREIGHT_INCOME_ACCT;
							$exp_acct = FREIGHT_COGS_ACCT;

							break;

							case 'fsf':

							$income_acct = FUEL_SURCHARGE_INCOME_ACCT;
							$exp_acct = FUEL_SURCHARGE_COGS_ACCT;

							break;

							case 'cbd':

							$income_acct = CBD_INCOME_ACCT;
							$exp_acct = CBD_COGS_ACCT;

							break;
						}

					} else {

						if ( ! $vendors->fetch_product_record($lines->current_line['product_hash']) )
							return $this->__trigger_error("Unable to fetch vendor product record for line item product lookup. Please check that vendor product used in invoice items have been correctly configured. <!--Tried to fetch product {$lines->current_line['product_hash']}-->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						$income_acct = $vendors->current_product['product_income_account'];
						$exp_acct = $vendors->current_product['product_expense_account'];
					}

					if ( $lines->current_line['work_order_cost_ext'] )
						$ext_cost = $lines->current_line['work_order_cost_ext'];
					else
						$ext_cost = $lines->current_line['ext_cost'];

					$accounting->setTransIndex( array(
    					'item_hash'    =>  $lines->item_hash
					) );

					if ( ! $accounting->exec_trans($audit_id, $invoice_date, $income_acct, _round($lines->current_line['ext_sell'], 2), 'AR', $lines->current_line['item_product'].($lines->current_line['item_no'] ? "&nbsp;(".$lines->current_line['item_no'].")" : NULL), $lines->current_line['item_hash']) )
					    return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					if ( ! $direct_bill || ( $direct_bill && strtoupper( $direct_invoice_type[ $lines->current_line['vendor_hash'] ] ) != 'C' ) ) {

						if ( ! $accounting->exec_trans($audit_id, $invoice_date, $exp_acct, _round($ext_cost, 2), 'AR', $lines->current_line['item_product'].($lines->current_line['item_no'] ? "&nbsp;(".$lines->current_line['item_no'].")" : NULL), $lines->current_line['item_hash']) )
	                        return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                        if ( ! $accounting->exec_trans($audit_id, $invoice_date, DEFAULT_WIP_ACCT, bcmul( _round($ext_cost, 2), -1, 2), 'AR', $lines->current_line['item_product'] . ( $lines->current_line['item_no'] ? " ({$lines->current_line['item_no']})" : NULL ), $lines->current_line['item_hash']) )
                            return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					} elseif ( $direct_bill && strtoupper( $direct_invoice_type[$lines->current_line['vendor_hash']] ) == 'C' ) {

						if ( ! $accounting->exec_trans($audit_id, $invoice_date, $exp_acct, _round($ext_cost, 2), 'AR', $lines->current_line['item_product'] . ( $lines->current_line['item_no'] ? " ({$lines->current_line['item_no']})" : NULL ), $lines->current_line['item_hash']) )
							return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
					}

                    $line_no++;
                    if ( ! $this->db->query("UPDATE line_items t1
                                             SET
	                                             t1.timestamp = UNIX_TIMESTAMP(),
	                                             t1.last_change = '{$this->current_hash}',
	                                             t1.invoice_line_no = '$line_no',
	                                             t1.invoice_hash = '$invoice_hash',
	                                             t1.income_account_hash = '$income_acct',
	                                             t1.expense_account_hash = '$exp_acct',
	                                             t1.wip_account_hash = '" . DEFAULT_WIP_ACCT . "',
	                                             t1.direct_bill_amt = '$invoice_line_type'
                                             WHERE t1.item_hash = '{$item_list[$i]}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
                    }
				}

				if ( ! $accounting->end_transaction() )
    				return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

                $this->db->end_transaction();

				if ( ( defined('OVERHEAD_RATE') && bccomp(OVERHEAD_RATE, 0, 4) && defined('OVERHEAD_TYPE') ) || $overhead_hash = global_classes::calculate_overhead($this->proposal_hash) ) {

	                $r = $this->db->query("SELECT t1.overhead_rate
	                                       FROM proposals t1
	                                       WHERE t1.proposal_hash = '{$this->proposal_hash}'");
	                if ( $this->db->result($r, 0, 'overhead_rate') == 0 ) {

	                	if ( $overhead_hash ) {

                            $s = new system_config($this->current_hash);
                            if ( $s->fetch_overhead_record($overhead_hash) ) {

                                $overhead_rate = $s->current_overhead['rate'];
                                $overhead_type = $s->current_overhead['apply_to'];
                            }
	                	} else {

                            $overhead_rate = OVERHEAD_RATE;
                            $overhead_type = OVERHEAD_TYPE;
	                	}

	                	if ( $overhead_rate && $overhead_type ) {

		                    if ( ! $this->db->query("UPDATE proposals t1
				                                     SET
					                                     t1.overhead_rate = '$overhead_rate',
					                                     t1.overhead_type = '$overhead_type'
				                                     WHERE t1.proposal_hash = '{$this->proposal_hash}'")
                            ) {

                            	$this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__);
                            }
	                	}
	                }
				}

				if ( $item_value_array['transmit_type'] == 'E' || $item_value_array['transmit_type'] == 'F' ) {

					if ( $item_value_array['transmit_type'] == 'E' ) {

						$args['subject'] = "Invoice $_invoice_no";
						$args['email_body'] = "";
					}

					$args['type'] = $item_value_array['transmit_type'];
					$args['invoice_hash'] = $invoice_hash;
					$args['proposal_hash'] = $this->proposals->proposal_hash;
					$args['recipient_list'] = $args['fax'] = $item_value_array['transmit_to'];

					$args['print_logo'] = $print_logo;
					$args['use_logo'] = $use_logo;

					$args['date'] = date("Y-m-d");
					$args['attached'] = "customer_invoice|$invoice_hash|Customer_Invoice_{$_invoice_no}.pdf";

					# Print prefs
					$args['use_prefs'] = $_POST["use_prefs{$z}"];
					$args['use_template'] = $_POST["use_template{$z}"];

					$send = new mail;
					if ( ! $send->doit_send($args) ) {

						$this->__trigger_error("Error sending outgoing customer invoice: {$send->content['form_return']['feedback']}", E_USER_ERROR, __FILE__, __LINE__, 1);
						$queue_err[] = "$_invoice_no|{$send->content['form_return']['feedback']}";
					}

				} elseif ( $item_value_array['transmit_type'] == 'M' )
					$invoice_hash_created[] = $invoice_hash;

				update_sys_data('NEXT_INVOICE_NO', ++$invoice_no );
				$this->jscript_action("purgeinvoice('{$invoices[$i]}');");
			}

			if ( $print_pref = fetch_user_data("invoice_print") )
				$print_pref = __unserialize( stripslashes($print_pref) );
			
			//return $this->__trigger_error($print_pref, E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			# Save vendor specific direct bill preferences for commission or sell
			if ( $print_pref['direct'] && is_array($direct_print_pref) ) {

				foreach ( $direct_print_pref as $key => $val )
					$print_pref['direct'][$key] = $val;

			} else
				$print_pref['direct'] = $direct_print_pref;

			if ( $print_logo ) {

				$print_pref['print_logo'] = $print_logo;
				$print_pref['use_logo'] = $use_logo;
			}

			$print_pref['transmit_type'] = $_POST['transmit_type'][0];
			$print_pref['remit_to'] = $_POST['remit_to'][0];
			store_user_data("invoice_print", addslashes( serialize($print_pref) ) );

			//Mark the proposal fully invoiced
			$all_items = array();
			$item_list = $_POST['item'];
			for ( $i = 0; $i < count($item_list); $i++ )
				$all_items = array_merge($all_items, $item_list[$i]);

			$all_items = array_values($all_items);
			if ( count($invoice_created) == 1 )
				$feedback = "Customer invoice {$invoice_created[0]} has been created.";
			else
				$feedback = count($invoice_created) . " invoice" . ( count($invoice_created) > 1 ? "s have" : " has" ) . " been created and " . ( count($invoice_created) > 1 ? "they are" : "it is" ) . " shown below.";

			if ( ! $this->db->query("UPDATE proposals t1
        							 SET " .
        							 ( $lines->total == count($all_items) ?
            							 "t1.invoiced = 1,
            							 t1.proposal_status = 'FI',
            							 t1.status_comment = 'Fully invoiced as of " . date(DATE_FORMAT) . "'"
            							 :
            							 "t1.proposal_status = 'PI',
            							 t1.status_comment = 'Partially invoiced as of " . date(DATE_FORMAT) . "'"
                    				 ) . ",
                						 t1.status = 2
        							 WHERE t1.proposal_hash = '{$this->proposals->proposal_hash}'")
            ) {

            	$this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__);
            }
		}

		if ( $invoice_hash_created && ! $integrate )
			$jscript_action = "agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'print_invoice', 'proposal_hash={$this->proposals->proposal_hash}', 'invoice_hash=" . implode("|", $invoice_hash_created) . "', 'use_template={$_POST['use_template0']}', 'use_prefs={$_POST['use_prefs0']}', 'popup_id=print_po', 'exec=1');window.setTimeout('agent.call(\'customer_invoice\', \'sf_loadcontent\', \'cf_loadcontent\', \'proposal_invoices\', \'proposal_hash={$this->proposals->proposal_hash}\', \'otf=1\');', 500);window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'uncheck_lines\', \'proposal_hash={$this->proposals->proposal_hash}\', \'from=invoice\', \'action=new\');', 700);";

		if ( $integrate )
			return $feedback;
		else {

			$this->content['action'] = 'close';
			$this->content['page_feedback'] = $feedback;
			$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'proposal_invoices', 'proposal_hash={$this->proposal_hash}', 'otf=1', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'limit=$limit');")."window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->proposal_hash}\', \'otf=1\');', 1500)";
		}
		
		$this->db->query("UPDATE `commissions_paid`
		SET `paid_in_full` = 0
		WHERE `proposal_hash` = '{$this->proposals->proposal_hash}'");

		return;
	}

	function fetch_invoiced_tax($item_hash, $invoice_hash=NULL) {
		$total_tax = 0;
		$result = $this->db->query("SELECT tax_collected.tax_hash , FORMAT(SUM(((line_items.sell * line_items.qty) * tax_collected.rate)), 2) AS total_tax
									FROM `tax_collected`
									LEFT JOIN `line_items` ON line_items.item_hash = tax_collected.item_hash
									WHERE ".($invoice_hash ?
									   "tax_collected.invoice_hash = '$invoice_hash'" : "tax_collected.proposal_hash = '".$this->proposal_hash."' AND tax_collected.invoice_hash != ''")."
									GROUP BY tax_collected.tax_hash");
        while ($row = $this->db->fetch_assoc($result)) {
        	$tax = preg_replace('/[^-.0-9]/', "", $row['total_tax']);
			$total_tax += $tax;
			$tax_table[$row['tax_hash']] = $tax;
		}

		if ($total_tax)
			return array($total_tax, $tax_table);
	}

	function doit() {

		$action = $_POST['action'];
		$method = $_POST['method'];
		$jscript_action = $_POST['jscript_action'];

		if ( $proposal_hash = $_POST['proposal_hash'] ) {

			$this->proposals = new proposals($this->current_hash);
			if ( ! $this->proposals->fetch_master_record($proposal_hash) )
    			return $this->__trigger_error("System error encountered when attempting to lookup proposal record. Please reload window and try again. <!-- Tried fetching proposal [ $proposal_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			if ( ! $this->proposal_hash ) {

				$this->set_values( array(
    				"proposal_hash" =>  $this->proposals->proposal_hash
				) );
			}

			if ( ! $this->customer_hash ) {

				$this->set_values( array(
    				"customer_hash" =>  $this->proposals->current_proposal['customer_hash']
				) );
			}
		}

		$invoice_hash = $_POST['invoice_hash'];
		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];
		$this->popup_id = $this->content['popup_controls']['popup_id'] = $_POST['popup_id'];

		if ( $action )
			return $this->$action();

		if ( $method == 'edit_line' ) {

            $item_descr = $_POST['item_descr'];
            $item_no = $_POST['item_no'];
            $item_tag1 = $_POST['item_tag1'];
            $item_tag2 = $_POST['item_tag2'];
            $item_tag3 = $_POST['item_tag3'];
            $product_hash = $_POST['item_product_hash'];

            if ( $item_hash && ! $product_hash )
                return $this->__trigger_error("Please select a valid product or service for your line item.", E_USER_ERROR, __FILE__, __LINE__, 1);

            $list = preg_replace('/[^-.0-9]/', "", $_POST['list']);
            $cost = preg_replace('/[^-.0-9]/', "", $_POST['cost']);
            $discount1 = preg_replace('/[^.0-9]/', "", $_POST['discount1']);
            $discount2 = preg_replace('/[^.0-9]/', "", $_POST['discount2']);
            $discount3 = preg_replace('/[^.0-9]/', "", $_POST['discount3']);
            $discount4 = preg_replace('/[^.0-9]/', "", $_POST['discount4']);
            $discount5 = preg_replace('/[^.0-9]/', "", $_POST['discount5']);

			$accounting = new accounting($this->current_hash);
			if ( $item_hash = $_POST['item_hash'] ) {

				$lines = new line_items($this->proposal_hash);
				if ( ! $lines->fetch_line_item_record($item_hash) )
					return $this->__trigger_error("System error encountered when attempting to lookup line item record for edit. Please reload window and try again. <!-- Tried fetching item [ $item_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

				$vendors = new vendors($this->current_hash);
				if ( ! $vendors->fetch_product_record($lines->current_line['product_hash']) )
					return $this->__trigger_error("System error encountered when attempting to fetch item product record. Please reload window and try again. <!-- Tried to fetch product [ {$lines->current_line['product_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

				if ( ! $this->fetch_invoice_record($invoice_hash) )
					return $this->__trigger_error("System error when attempting to fetch invoice record. Please reload window and try again. <!-- Tried fetching invoice [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

				if ( ! $this->db->start_transaction() )
					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

				$accounting->start_transaction( array(
					'ar_invoice_hash'   =>  $this->invoice_hash,
					'customer_hash'     =>  $this->current_invoice['customer_hash'],
					'proposal_hash'     =>  $this->current_invoice['proposal_hash']
				) );

				if ( $this->current_invoice['payments'] )
					return $this->__trigger_error("You cannot make changes to this invoice because a receipt has already been received.", E_USER_NOTICE, __FILE__, __LINE__, 1);

				if ( $this->current_invoice['direct_bill'] )
					$direct_bill = true;

                ( bccomp($cost, $lines->current_line['cost'], 2) ? $sql_up[] = "`cost` = '$cost'" : NULL );
                ( bccomp($list, $lines->current_line['list'], 2) ? $sql_up[] = "`list` = '$list'" : NULL );

                ( bccomp($discount1, $lines->current_line['discount1'], 4) ? $sql_up[] = "`discount1` = '$discount1'" : NULL );
                ( bccomp($discount2, $lines->current_line['discount2'], 4) ? $sql_up[] = "`discount2` = '$discount2'" : NULL );
                ( bccomp($discount3, $lines->current_line['discount3'], 4) ? $sql_up[] = "`discount3` = '$discount3'" : NULL );
                ( bccomp($discount4, $lines->current_line['discount4'], 4) ? $sql_up[] = "`discount4` = '$discount4'" : NULL );
                ( bccomp($discount5, $lines->current_line['discount5'], 4) ? $sql_up[] = "`discount5` = '$discount5'" : NULL );

                ( $item_no != $lines->current_line['item_no'] ? $sql_up[] = "`item_no` = '$item_no'" : NULL );
                ( strlen( base64_encode($item_descr) ) != strlen( base64_encode( stripslashes($lines->current_line['item_descr']) ) ) ? $sql_up[] = "`item_descr` = '" . stripslashes($item_descr) . "'" : NULL );
                ( $item_tag1 != stripslashes($lines->current_line['item_tag1']) ? $sql_up[] = "`item_tag1` = '" . addslashes($item_tag1) . "'" : NULL );
                ( $item_tag2 != stripslashes($lines->current_line['item_tag2']) ? $sql_up[] = "`item_tag2` = '" . addslashes($item_tag2) . "'" : NULL );
                ( $item_tag3 != stripslashes($lines->current_line['item_tag3']) ? $sql_up[] = "`item_tag3` = '" . addslashes($item_tag3) . "'" : NULL );

                ( $product_hash != $lines->current_line['product_hash'] ? $sql_up[] = "`product_hash` = '$product_hash'" : NULL );

				if ( bccomp($cost, $lines->current_line['cost'], 2) ) {

					$update = true;
					$invoice_total = $this->current_invoice['amount'];
					$invoice_balance = $this->current_invoice['balance'];

                    if ( bccomp($cost, $lines->current_line['cost'], 2) ) {

                    	$cost_change = true;
                        $cost_adj = bcsub(_round(bcmul($cost, $lines->current_line['qty'], 4), 2), $lines->current_line['ext_cost'], 2);
                    }

					$accounting->setTransIndex( array(
    					'item_hash'    =>  $lines->item_hash
					) );

	                if ( $sql_up )
	                    $sql = "UPDATE `line_items`
	                            SET `last_change` = '".$this->current_hash."' , `timestamp` = ".time()." , ".implode(" , ", $sql_up)."
	                            WHERE `proposal_hash` = '{$this->proposals->proposal_hash}' AND `item_hash` = '$item_hash'";

                    if ($cost_change) {
                    	$posting_date = $this->current_invoice['invoice_date'];

                        //Update the expense account first
                      	if ($accounting->exec_trans($audit_id, $posting_date, $vendors->current_product['product_expense_account'], $cost_adj, 'AD', 'Line Item Adjustment : '.$lines->current_line['item_product'].($lines->current_line['item_no'] ? "&nbsp;(".$lines->current_line['item_no'].")" : NULL), $lines->current_line['item_hash']) === false)
                            return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, 1);
                        if ($direct_bill && $lines->current_line['direct_bill_amt'] == 'C' && !$sell_change) {
                            if ($accounting->exec_trans($audit_id, $posting_date, DEFAULT_AR_ACCT, bcmul($cost_adj, -1, 2), 'AD', 'Direct Invoice Adjustment (line item adjustment) : '.$this->current_invoice['invoice_no'], $lines->current_line['item_hash']) === false)
                                return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, 1);
                        }
                        //Update the WIP account next
                        if (!$direct_bill || ($direct_bill && $lines->current_line['direct_bill_amt'] == 'S')) {
	                        if ($accounting->exec_trans($audit_id, $posting_date, DEFAULT_WIP_ACCT, bcmul($cost_adj, -1, 2), 'AD', 'Line Item Adjustment : '.$lines->current_line['item_product'].($lines->current_line['item_no'] ? "&nbsp;(".$lines->current_line['item_no'].")" : NULL), $lines->current_line['item_hash']) === false)
	                            return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, 1);
                        }
                        //Update the po
                        if ($lines->current_line['po_hash']) {
                            $r = $this->db->query("SELECT SUM(line_items.qty * line_items.cost) AS ext_po_cost
                                                   FROM line_items
                                                   WHERE line_items.proposal_hash = '".$lines->current_line['proposal_hash']."' AND line_items.po_hash = '".$lines->current_line['po_hash']."'");
                            if ($this->db->db_error)
                                return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            else
                                $po_order_amount = bcadd(_round(bcsub($this->db->result($r, 0, 'ext_po_cost'), $lines->current_line['ext_cost'], 4), 2), _round(bcmul($cost, $lines->current_line['qty'], 4), 2), 2);

                            $this->db->query("UPDATE `purchase_order`
                                              SET `order_amount` = '$po_order_amount'
                                              WHERE `po_hash` = '".$lines->current_line['po_hash']."'");
                            if ($this->db->db_error)
                                return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_DATABASE_ERROR, __FILE__, __LINE__, 1);

                        	/*
                            $po = new purchase_order($lines->current_line['proposal_hash']);
                            if ($po->fetch_po_record($lines->current_line['proposal_hash'], $lines->current_line['po_hash']) === false)
                            	return $this->__trigger_error("Unable to fetch PO record in order to update cost. Tried to fetch PO [".$lines->current_line['po_hash']."].", E_USER_ERROR, __FILE__, __LINE__, 1);

                            $this->db->query("UPDATE `purchase_order`
                                              SET order_amount = '".($po->current_po['order_amount'] + $cost_adj)."'
                                              WHERE purchase_order.po_hash = '".$lines->current_line['po_hash']."'");
							if ($this->db->db_error)
								return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, 1);
                            */
                        }
                        if ($direct_bill && $lines->current_line['direct_bill_amt'] == 'C') {
                            $invoice_total = _round(bcadd($invoice_total, bcmul($cost_adj, -1, 2), 4), 2);
                            $invoice_balance = _round(bcadd($invoice_balance, bcmul($cost_adj, -1, 2), 4), 2);
                            $direct_sell_change = true;
                        }
                    }
					$accounting->setTransIndex( array(
    					'item_hash'
					), 1);
				}

				if ($sql) {
					$update = true;
					$this->db->query($sql);
					if ($this->db->db_error)
						return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, 1);

					if ($direct_sell_change) {
						$this->db->query("UPDATE `customer_invoice`
										  SET ".($new_balance == 0 ? "`paid_in_full` = 1" : "`paid_in_full` = 0")." , `amount` = '$invoice_total' , `balance` = '$invoice_balance' ".(is_numeric($deposit_applied) ? ", `deposit_applied` = '$deposit_applied' " : NULL).(is_numeric($invoice_tax) ? ", `tax` = '".$invoice_tax."'" : NULL)."
										  WHERE `invoice_hash` = '$invoice_hash'");
						if ($this->db->db_error)
							return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, 1);
					}
				}

				if ( ! $accounting->end_transaction() )
    				return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

				$this->db->end_transaction();

				$feedback = 'Invoiced item has been updated.';
			}
			/*
			else {
				//Adding one or more lines to the current invoice
				$line_items = $_POST['invoice_item'.$this->popup_id];
				for ($i = 0; $i < count($line_items); $i++) {
					if ($line_items[$i])
						$line_item[] = $line_items[$i];
				}

				if (!count($line_item)) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = 'You selected no line items! Please select at least one line to continue.';
					return;
				}
				$lines = new line_items($this->proposal_hash);
				$this->fetch_invoice_record($invoice_hash);

				$this->db->start_transaction();

				if ($this->current_invoice['direct_bill'])
					$direct_bill = true;

				$vendors = new vendors($this->current_hash);

				$result = $this->db->query("SELECT `invoice_line_no`
											FROM `line_items`
											WHERE `invoice_hash` = '".$this->invoice_hash."'
											ORDER BY `invoice_line_no` DESC
											LIMIT 1");
				$last_line = $this->db->result($result);

				$accounting->setTransIndex( array(
                    'ar_invoice_hash'   =   $this->invoice_hash,
    				'customer_hash'     =   $this->current_invoice['customer_hash'],
    				'proposal_hash'     =>  $this->current_invoice['proposal_hash']
                ) );

				for ($i = 0; $i < count($line_item); $i++) {
					$lines->fetch_line_item_record($line_item[$i]);
					if ($lines->current_line['work_order_cost_ext'])
						$ext_cost = $lines->current_line['work_order_cost_ext'];
					else
						$ext_cost = $lines->current_line['ext_cost'];

					if ($direct_bill) {
						$result = $this->db->query("SELECT `direct_bill_amt`
													FROM `line_items`
													WHERE `invoice_hash` = '".$this->invoice_hash."' AND `vendor_hash` = '".$lines->current_line['vendor_hash']."'");
						$direct_bill_amt = $this->db->result($result);

						if ($direct_bill_amt == 'S' || $direct_bill_amt == 'O' || !$direct_bill_amt)
							$new_cost += $lines->current_line['ext_sell'];
						else
							$new_cost += ($lines->current_line['ext_sell'] - $ext_cost);
					} else
						$new_cost += $lines->current_line['ext_sell'];

					$tax_up[] = "UPDATE `tax_collected`
								 SET `invoice_hash` = '$invoice_hash'
								 WHERE `proposal_hash` = '".$this->proposal_hash."' AND `item_hash` = '".$line_item[$i]."'";

					$last_line++;
					$this->db->query("UPDATE `line_items`
									  SET `last_change` = '".$this->current_hash."' , `timestamp` = ".time()." , `invoice_line_no` = '$last_line' , `invoice_hash` = '$invoice_hash' ".($direct_bill ? ", `direct_bill_amt` = '$direct_bill_amt'" : NULL)."
									  WHERE `item_hash` = '".$line_item[$i]."'");
					$vendors->fetch_product_record($lines->current_line['product_hash']);

					$accounting->setTransIndex( array(
                        'item_hash' =>  $lines->item_hash
                    ) );

					if ($accounting->exec_trans($audit_id, date("Y-m-d"), $vendors->current_product['product_income_account'], $lines->current_line['ext_sell'], 'AD', 'Line item added to invoice : '.$lines->current_line['item_product'], $lines->current_line['item_hash']) === false) {
					    $this->db->end_transaction(1);
		                $this->content['error'] = 1;
		                $this->content['form_return']['feedback'] = "The default Accounts Receivable chart of account is not defined! Please make sure that you have properly defined your default accounts under Accounting->General Journal->Business Cycle Settings.";
		                return;
					}
					if (!$direct_bill || ($direct_bill && ($direct_bill_amt == 'S' || $direct_bill_amt == 'O' || !$direct_bill_amt))) {
						if ($accounting->exec_trans($audit_id, date("Y-m-d"), $vendors->current_product['product_expense_account'], $ext_cost, 'AD', 'Line item added to invoice : '.$lines->current_line['item_product'], $lines->current_line['item_hash']) === false)
						    $transaction_error = 1;
						if ($accounting->exec_trans($audit_id, date("Y-m-d"), DEFAULT_WIP_ACCT, ($ext_cost * -1), 'AD', 'Line item added to invoice : '.$lines->current_line['item_product'], $lines->current_line['item_hash']) === false)
						    $transaction_error = 1;
					} elseif ($direct_bill && $direct_bill_amt == 'C')
						if ($accounting->exec_trans($audit_id, date("Y-m-d"), $vendors->current_product['product_expense_account'], $ext_cost, 'AD', 'Line item added to invoice : '.$lines->current_line['item_product'], $lines->current_line['item_hash']) === false)
						    $transaction_error = 1;

				}

				if ($transaction_error) {
                    $this->db->end_transaction(1);
                    $this->content['error'] = 1;
                    $this->content['form_return']['feedback'] = "One or more of the products within your line items has not been properly associated with a valid income/expense account! Please make sure that you have properly defined your products and services and that they are correctly associated with valid income/expense accounts.";
                    return;
				}

				$accounting->setTransIndex( array(
                    'item_hash'
                ), 1);

				//Sales Tax:
				if (!$direct_bill) {
					list($total_tax, $tax_hash) = $lines->calc_tax($line_item);
					if ($total_tax) {
						$sys = new system_config($this->current_hash);
						reset($tax_hash);
						while (list($tax_acct_hash, $tax_amt) = each($tax_hash)) {
							$sys->fetch_tax_rule($tax_acct_hash);
	                        if ($accounting->exec_trans($audit_id, date("Y-m-d"), $sys->current_tax['account_hash'], $tax_amt, 'AR', $sys->current_tax['state_name'].($sys->current_tax['local'] ? ", ".$sys->current_tax['local'] : NULL)." (".($sys->current_tax['rate'] * 100)."%) Tax") === false) {
	                            $this->db->end_transaction(1);
	                            $this->content['error'] = 1;
	                            $this->content['form_return']['feedback'] = "The liability chart of account for one of your tax rules has not been defined. Please check your tax tables and make sure each tax rule has been properly associated with a liability chart of account. Tax tables can be found in System->System Config->Company Settings.";
	                            return;
	                        }

						}
						$new_cost += $total_tax;
						$invoice_tax = $this->current_invoice['tax'] + $total_tax;
					}
				}
				if ($tax_up) {
					for ($i = 0; $i < count($tax_up);  $i++)
						$this->db->query($tax_up[$i]);
				}

				$cost_adj = $new_cost;
				$new_cost = $this->current_invoice['amount'] + $new_cost;
				$balance = $this->current_invoice['balance'] + $cost_adj;
				$sql = true;
				$feedback = "Item".(count($line_item) > 1 ? "s have" : " has")." been added to this customer invoice.";
				if ($sql) {
					$update = true;
					$adj = _round($new_cost - $this->current_invoice['amount']);
					if ($new_cost) {
						$this->db->query("UPDATE `customer_invoice`
										  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `amount` = '$new_cost' , `balance` = '$balance' ".($invoice_tax ? ", `tax` = '".$invoice_tax."'" : NULL)."
										  WHERE `invoice_hash` = '$invoice_hash'");
						if ($accounting->exec_trans($audit_id, date("Y-m-d"), DEFAULT_AR_ACCT, $adj, 'AD', 'Invoice Adjustment (additional lines added) : '.$this->current_invoice['invoice_no']) === false) {
			                $this->db->end_transaction(1);
			                $this->content['error'] = 1;
			                $this->content['form_return']['feedback'] = "The default Accounts Receivable chart of account is not defined! Please make sure that you have properly defined your default accounts under Accounting->General Journal->Business Cycle Settings.";
			                return;
						}
					}
				}
			}
			*/

            $this->content['jscript'][] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'uncheck_lines', 'proposal_hash=".$proposal_hash."', 'from=invoice', 'action=new');";

			$this->content['action'] = 'close';//$next_action;
			$this->content['page_feedback'] = ($update ? $feedback : 'No changes have been made.');
			$this->content['jscript_action'] = "agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'proposal_hash={$this->proposals->proposal_hash}', 'invoice_hash=".$invoice_hash."', 'popup_id=line_item');window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->proposals->proposal_hash}\', \'otf=1\');', 1500)";

		}

		 elseif ( $method == 'move_deposit' ) {

			$submit_btn = 'move_deposit_btn';
			$move_to = $_POST['move_to'];

			if ( $invoice_hash = $_POST['invoice_hash'] ) {

				if ( ! $this->fetch_invoice_record($invoice_hash) )
					return $this->__trigger_error("System error encountered when attempting to lookup deposit record. Please reload window and try again. <!-- Tried to fetch deposit [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			}

            $posting_date = $this->current_invoice['invoice_date'];

			$current_proposal = $_POST['proposal_hash'];
			$proposals = new proposals($this->current_hash);
			if ( ! $proposals->fetch_master_record($current_proposal) )
				return $this->__trigger_error("System error encountered when attempting to fetch proposal record. Please reload window and try again. <!-- Tried to fetch proposal [ $current_proposal ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			$result = $this->db->query("SELECT
                                			t1.customer_hash,
                                			t1.proposal_hash
										FROM proposals t1
										WHERE t1.proposal_no = '$move_to'");
			$proposal_hash = $this->db->result($result, 0, 'proposal_hash');
			$customer_hash = $this->db->result($result, 0, 'customer_hash');
			if ( $proposal_hash == $current_proposal ) {

				return $this->__trigger_error("The proposal you entered is the same as the current proposal. No changes have been made.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			$accounting = new accounting($this->current_hash);

			if ( ! $move_to || ! $proposal_hash ) {

				$this->set_error("err_move{$this->popup_id}");
				return $this->__trigger_error( ( ! $move_to ? "Please enter a proposal number to move the deposit." : "The proposal number you entered does not exist. Please enter a valid proposal number." ), E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
			} else {

				if ( ! $this->db->start_transaction() )
					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$accounting->start_transaction( array(
					'ar_invoice_hash'   =>  $this->invoice_hash,
					'customer_hash'     =>  $this->current_invoice['customer_hash'],
					'proposal_hash'     =>  $this->current_invoice['proposal_hash']
				) );

				$audit_id = $accounting->audit_id();

				if ( ! $this->db->query("UPDATE customer_invoice t1
        								 SET " .
	                        				 ( $customer_hash != $this->current_proposal['customer_hash'] ?
	                            				 "t1.customer_hash = '$customer_hash', " : NULL
	                        				 ) . "
	                        				 t1.proposal_hash = '$proposal_hash'
        								 WHERE t1.invoice_hash = '$invoice_hash'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

				if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_CUST_DEPOSIT_ACCT, bcmul($this->current_invoice['balance'], -1, 2), 'AD', "Reassign deposit to proposal $move_to") )
				    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	            if ( $customer_hash != $this->current_proposal['customer_hash'] ) {

	                $accounting->setTransIndex( array(
    	                'customer_hash'    =>  $customer_hash
	                ) );
	            }

	            $accounting->setTransIndex( array(
    	            'proposal_hash'    =>  $proposal_hash
	            ) );

	            if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_CUST_DEPOSIT_ACCT, $this->current_invoice['balance'], 'AD', "Reassign deposit to proposal $move_to") )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( ! $accounting->end_transaction() )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$this->db->end_transaction();

                $this->content['action'] = 'close';
                $this->content['page_feedback'] = "The deposit has been moved to proposal $move_to.";
		        $this->content['jscript_action'] = "agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'proposal_invoices', 'otf=1', 'proposal_hash=$current_proposal');window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash=$current_proposal\', \'otf=1\');', 1500)";
			}
		}

		return;
	}
	/**
	 * Display print options for printing customer invoice.  Updated to add more print options for trac #473
	 *
	 */
	function print_invoice() {
		$this->content['popup_controls']['popup_id'] = $this->popup_id = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id']);
		$proposal_hash = ($_POST['proposal_hash'] ? $_POST['proposal_hash'] : $this->ajax_vars['proposal_hash']);
		$invoice_hash = ($_POST['invoice_hash'] ? $_POST['invoice_hash'] : $this->ajax_vars['invoice_hash']);
		$exec = ($_POST['exec'] ? $_POST['exec'] : $this->ajax_vars['exec']);
		$this->content['popup_controls']['popup_title'] = "Print Invoices";

		if ($_POST['doit_print'] || $exec) {
			$template_hash = ($_POST['use_template'] ? $_POST['use_template'] : $this->ajax_vars['use_template']);
			$print_pref_hash = ($_POST['use_prefs'] ? $_POST['use_prefs'] : $this->ajax_vars['use_prefs']);
			if ($print_pref = fetch_user_data("invoice_print"))
				$print_pref = unserialize(stripslashes($print_pref));
			if (!$exec) {
				$this->content['action'] = 'continue';
				$proposal_hash = $_POST['proposal_hash'];
				$print_pref['gen_print_fields'] = $gen_print_fields = $_POST['gen_print_fields'];
				$print_pref['item_print_fields'] = $item_print_fields = $_POST['item_print_fields'];
				$print_pref['footer_msg'] = $footer_msg = htmlspecialchars($_POST['footer_msg'], ENT_QUOTES);
				$print_pref['print_logo'] = $print_logo = $_POST['print_logo'];
				$print_pref['use_logo'] = $use_logo = $_POST['use_logo'];
				$print_pref['invoice_detail'] = $invoice_detail = $_POST['invoice_detail'];
				$print_pref['title'] = $title = htmlspecialchars($_POST['title'], ENT_QUOTES);
				$print_pref['default_template'] = $template_hash;
				$print_pref['default_prefs'] = $print_pref_hash;
				$print_prefs_str = addslashes(serialize($print_pref));

				$template = new templates();
				$template->doit_save_prefs("invoice_print", $print_prefs_str);

				store_user_data("invoice_print", $print_prefs_str);

				if ($template->error) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = $template->error_msg;
					$this->content['form_return']['err']['saved_prefs_name'] = 1;
					$this->content['submit_btn'] = "invoice_btn";
					return;
				}

			} else {
				// Save default template and prefs only.
				$print_pref['default_template'] = $template_hash;
				$print_pref['default_prefs'] = $print_pref_hash;
				store_user_data("invoice_print", addslashes(serialize($print_pref)));
			}
			// Pass fp=1 in the GET array indicating that this invoice was printed from print options rather than invoice generation.  Added for Trac #1307
			$tbl .= "
			<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;text-align:center;\">
				<div id=\"loading".$this->popup_id."\" style=\"width:100%;padding-top:50px;padding-bottom:45px;\">
					<h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch".$this->popup_id."\">Loading Document...</h3>
					<div id=\"link_img_holder".$this->popup_id."\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
				</div>
				<input id=\"url\" type=\"hidden\" value=\"print.php?t=".base64_encode('customer_invoice')."&h=".$invoice_hash."&h2=".$proposal_hash."&c=".urlencode($_POST['customer_contact'])."&m=$template_hash&p=$print_pref_hash&fp=1\"/>
			    <input id=\"popup\" type=\"hidden\" value=\"$this->popup_id\">
            </div>";

			$this->content['html']['main_table'.$this->popup_id] = $tbl;
            $this->content['jscript'][] = "setTimeout(function(){window.open(document.getElementById('url').value,'_blank');popup_windows[document.getElementById('popup').value].hide();},500);";

		} else {
			$logo = explode("|", fetch_sys_var('company_logo'));
			for ($i = 0; $i < count($logo); $i++) {
				$logo_in[] = $logo[$i];
				$logo_out[] = basename($logo[$i]);
			}

			$template = new templates();

			if ($print_pref = fetch_user_data("invoice_print"))
				$print_pref = unserialize(($print_pref));

			// Initially show the customer print preferences if the user has no defaults, or last time they chose custom
            if (($print_pref['default_prefs'] == "custom" && $this->p->ck("customer_invoice", 'P')) || !$print_pref['default_prefs'])
            	$show_custom_prefs = true;

			// Check if new print preferences are set up for this user, and if they aren't give them the default set
			if (!$print_pref['gen_print_fields'] && !$print_pref['item_print_fields'] && !$print_pref['customer_contact']) {
				$print_pref['item_print_fields'] = array("line_no", "item_no", "item_descr", "item_qty", "sell", "ext_sell", "product");
				$print_pref['gen_print_fields'] = array("customer_contact", "customer_po", "comments", "install_addr", "remit_to");
				$print_pref['print_logo'] = 1;
			}
			$print_ops = array("line_no"		=>	"Line Numbers",
							   "item_no"		=>	"Item Number",
							   "item_descr"		=>	"Item Description",
							   "item_qty" 		=> 	"Item Quantity",
							   "sell"           =>  "Item Sell",
                               "ext_sell"       =>  "Extended Sell",
			                   "list"           =>  "Item List",
			                   "ext_list"       =>  "Extended List",
			                   "discounting"    =>  "Item Discounting",
							   "list_discount"	=>  "List Discount",
							   "product"		=>	"Item Product",
							   "item_finish"	=>	"Item Finishes & Options",
							   "hide_vendor"	=>	"Hide Vendor Name",
							   "zero_sell"		=>	"Zero Sell Items",
								);
			$gen_print_ops = array("customer_contact"	=>	"Customer Contact",
								   "customer_po"		=>	"Customer PO",
			                       "group_name"         =>  "Item Groups",
			                       "group_total"        =>  "Group Totals",
			                       "group_break"        =>  "Page Break After Groups",
								   "proposal_descr"		=>	"Proposal Description",
								   "comments"			=>	"Proposal Comments",
								   "install_addr"		=>	"Installation Location",
								   "remit_to"			=>	"Remittance Address",
									"sub_total"			=>	"Display Sub Totals");
			if ($this->current_invoice['invoice_to'] != 'V') {
				$customer_contact = stripslashes(proposals::fetch_customer_contact_name($proposal_hash));
			}
			$tbl .= $this->form->form_tag().
			$this->form->hidden(array("proposal_hash" => $proposal_hash, "invoice_hash" => $invoice_hash, "popup_id" => $this->popup_id))."
			<div id=\"main_table".$this->popup_id."\" style=\"margin-top:0;padding-top:0\">
				<div class=\"panel\" style=\"margin-top:0;height:100%;\">
					<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
						<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
							<p id=\"feedback_message".$this->popup_id."\"></p>
					</div>
					<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
						<tr>
							<td style=\"background-color:#ffffff;padding-left:20px;padding-bottom:5px;\">
								<div style=\"float:right;margin-top:10px;margin-right:25px;\">
									".$this->form->button("value=Print Invoice", "id=invoice_btn", "onClick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=print_invoice', 'doit_print=1');")."
								</div>
								<div style=\"margin-bottom:15px;margin-top:5px;font-weight:bold;\">Invoice Print Options</div>
								<div style=\"margin-left:15px;margin-bottom:15px;margin-top:15px;\">
									<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">".
										$template->template_select("invoice_print", 7, NULL, NULL, $this->popup_id, $proposal_hash, $invoice_hash)."
										<tr id=\"print_pref1\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;padding-top:10px;\">Company Logo:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">
												".$this->form->select("use_logo", $logo_out, $print_pref['use_logo'], $logo_in, "blank=1")."
												<div style=\"padding-top:5px;\">
													".$this->form->checkbox("name=print_logo", "value=1", ($print_pref['print_logo'] ? "checked" : NULL))."&nbsp;Print Logo?
												</div>
											</td>
										</tr>
										<tr id=\"print_pref2\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">
												General Print Fields:
												<div style=\"padding-top:5px;font-style:italic;\"><small>hold cntrl key for multiple</small></div>
											</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->select("gen_print_fields[]", array_values($gen_print_ops), $print_pref['gen_print_fields'], array_keys($gen_print_ops), "blank=1", "multiple", "style=width:200px;height:75px")."
											</td>
										</tr>
										<tr id=\"print_pref3\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">
												Line Item Print Fields:
												<div style=\"padding-top:5px;font-style:italic;\"><small>hold cntrl key for multiple</small></div>
											</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->select("item_print_fields[]", array_values($print_ops), $print_pref['item_print_fields'], array_keys($print_ops), "blank=1", "multiple", "style=width:200px;height:100px")."
											</td>
										</tr>
                                        <tr id=\"print_pref4\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
                                            <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Invoice Details:</td>
                                            <td style=\"background-color:#ffffff;\">
                                                ".$this->form->select("invoice_detail", array("Print Line Item Details", "Print Summarized by Group"), $print_pref['invoice_detail'], array(0, 1), "blank=1")."
                                            </td>
                                        </tr>
										<tr id=\"print_pref5\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" id=\"err_title\">Document Title:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=title",
															"value=".($print_pref['title'] ? $print_pref['title'] : "Invoice"),
															"maxlength=36", "style=width:200px;")."
											</td>
										</tr>
										<tr id=\"print_pref6\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Customer Contact:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=customer_contact",
															"value=".$customer_contact,
															"maxlength=32", "style=width:200px;")."
											</td>
										</tr>
										<tr id=\"print_pref7\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Invoice Footer Message:</td>
											<td style=\"background-color:#ffffff;\">".$this->form->text_area("name=footer_msg", "value=".fetch_sys_var("invoice_footer_message"), "cols=45", "rows=4")."</td>
										</tr>".
										$template->save_print_prefs(($show_custom_prefs ? 1 : NULL))."
									</table>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>".$this->form->close_form();

		}
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function receive_from_unapplied() {
		$customer_hash = ($this->ajax_vars['customer_hash'] ? $this->ajax_vars['customer_hash'] : $_POST['customer_hash']);
		$type = ($this->ajax_vars['type'] ? $this->ajax_vars['type'] : $_POST['type']);
		$invoice_hash = ($this->ajax_vars['invoice_hash'] ? $this->ajax_vars['invoice_hash'] : $_POST['invoice_hash']);
		$proposal_hash = ($this->ajax_vars['proposal_hash'] ? $this->ajax_vars['proposal_hash'] : $_POST['proposal_hash']);
		$this->popup_id = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id']);

		if ($_POST['unapplied']) {
			if ($_POST['receive_from_unapplied']) {
				$receive_from_unapplied = $_POST['receive_from_unapplied'];
				if ($receive_from_unapplied == 'reset') {
                    $this->content['jscript'] = "if(\$('toggle_receipt_new".$this->popup_id."')){\$('toggle_receipt_new".$this->popup_id."').show();}if(\$('toggle_credits".$this->popup_id."')){\$('toggle_credits".$this->popup_id."').show();}if(\$('toggle_receipt_unapplied".$this->popup_id."')){\$('toggle_receipt_unapplied".$this->popup_id."').hide();}if(\$('currency')){\$('currency').enable();}if(\$('payment_account_hash')){\$('payment_account_hash').enable()}";
					$this->content['html']['toggle_receipt_unapplied'.$this->popup_id] = '';
					$this->content['value']['amount'] = '';
					$this->content['action'] = 'continue';
				} else {
					if ($type == 'apply_credits') {
						$credits = new customer_credits($this->current_hash);
						if ($credits->fetch_credit_record($receive_from_unapplied))
                            $obj = $credits->current_credit;
						else {
                            $this->content['action'] = 'continue';
                            $this->content['jscript'] = "alert('Unable to fetch credit record to apply to current invoice.');";
                            return;
						}
					} else {
    					if ($this->fetch_invoice_record($receive_from_unapplied)) {
                            $obj =& $this->current_invoice;
                            if ($obj['currency'] && $obj['exchange_rate'])
                                $obj['balance'] = currency_exchange($obj['balance'], $obj['exchange_rate']);
    					} else {
                            $this->content['action'] = 'continue';
                            $this->content['jscript'] = "alert('Unable to fetch deposit record to apply to current invoice.');";
                            return;
                        }
					}
                    $this->content['jscript'] = "if(\$('toggle_receipt_new".$this->popup_id."')){\$('toggle_receipt_new".$this->popup_id."').hide();}if(\$('toggle_credits".$this->popup_id."')){\$('toggle_credits".$this->popup_id."').hide();}if(\$('toggle_receipt_unapplied".$this->popup_id."')){\$('toggle_receipt_unapplied".$this->popup_id."').show();}if(\$('currency')){".($obj['currency'] && $obj['exchange_rate'] ? "\$('currency').value='".$obj['currency']."';" : NULL)."\$('currency').disable()}if(\$('payment_account_hash')){\$('payment_account_hash').disable()}";
					$this->content['html']['toggle_receipt_unapplied'.$this->popup_id] = ($type == 'apply_credits' ?
					   'From customer credit applied on '.date(DATE_FORMAT, strtotime($obj['credit_date'])) : "From ".($type == 'rcv_pmt' ? "deposit received" : "unapplied receipt")." on ".date(DATE_FORMAT, strtotime($obj['invoice_date']))).$this->form->hidden(array('receive_from_unapplied' => $receive_from_unapplied, 'type' => $type))."&nbsp;&nbsp;[<small><a href=\"javascript:void(0);\" onClick=\"submit_form(\$('popup_id').form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=receive_from_unapplied', 'unapplied=1', 'receive_from_unapplied=reset');\" class=\"link_standard\">cancel</a></small>]";
					$this->content['value']['amount'] = number_format($obj['balance'], 2);
					$this->content['action'] = 'continue';
				}
			} else {
				$this->content['action'] = 'continue';
				$this->content['jscript'] = "alert('Please select which of the listed unapplied payments you wish to apply against this invoice.');";
				return;
			}
		} else {

			if ( $type == 'rcv_pmt' ) {

				$this->set_values( array(
    				'customer_hash' =>  $customer_hash
				) );

				$unapplied = $this->fetch_customer_deposits($proposal_hash);
				$title = "Select From Available Deposits:";
				$holder = 'receive_from_holder';

			} elseif ( $type == 'apply_credits' ) {

				$this->set_values( array(
    				'customer_hash' =>  $customer_hash
				) );

				$unapplied = $this->fetch_customer_credits();
				$title = "Select From Available Credits:";
				$holder = 'apply_credit_holder';

			} else {

				$customers = new customers($this->current_hash);
				$unapplied = $customers->fetch_unapplied_deposits($customer_hash);
				$title = "Select From Unapplied Receipts:";
				$holder = 'receive_from_holder';
			}

			for ($i = 0; $i < count($unapplied); $i++) {
				if ($unapplied[$i]['currency'] && $unapplied[$i]['exchange_rate'])
				    $unapplied[$i]['balance'] = currency_exchange($unapplied[$i]['balance'], $unapplied[$i]['exchange_rate']);

				$loc_tbl .= "
				<div class=\"function_menu_item\">
					".$this->form->radio("name=receive_from_unapplied",
				                         "id=receive_from_unapplied_".($type == 'apply_credits' ? $unapplied[$i]['credit_hash'] : $unapplied[$i]['invoice_hash']),
                        				 "value=".($type == 'apply_credits' ? $unapplied[$i]['credit_hash'] : $unapplied[$i]['invoice_hash'])).
					"&nbsp;
					<a href=\"javascript:void(0);\" onClick=\"\$('receive_from_unapplied_".($type == 'apply_credits' ? $unapplied[$i]['credit_hash'] : $unapplied[$i]['invoice_hash'])."').checked=1;\" style=\"text-decoration:none;color:#000000\">
    					".date(DATE_FORMAT, strtotime($type == 'apply_credits' ? $unapplied[$i]['credit_date'] : $unapplied[$i]['invoice_date']))." - $".number_format($unapplied[$i]['balance'], 2).($unapplied[$i]['currency'] && $unapplied[$i]['exchange_rate'] ?
    					    "<span style=\"margin-left:5px;\">".$unapplied[$i]['currency']."</span>" : NULL)."
        			</a>
				</div>";
			}

			$tbl = "
			<div id=\"".$holder."_changer".$this->popup_id."\" class=\"function_menu\" style=\"width:275px;display:block;\">
				<div style=\"float:right;padding:3px\">
					<a href=\"javascript:void(0);\" onClick=\"toggle_display('".$holder."_changer".$this->popup_id."', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
				</div>
				<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">
					$title
				</div>
				<table cellpadding=\"0\" cellspacing=\"1\" style=\"width:100%;\">
					<tr>
						<td style=\"width:40%;background-color:#ffffff;\">
							<div style=\"color:#000000;".(count($unapplied) > 4 ? "height:86px;" : NULL)."margin-right:1px;overflow:auto;\" >
								".$loc_tbl."
							</div>
						</td>
					</tr>
					<tr>
						<td colspan=\"2\" style=\"text-align:right;padding:5px;background-color:#bbc7ce;\">
							".$this->form->button("value=Go",
                    							  "onMouseUp=\$('toggle_display(\'".$holder."_changer".$this->popup_id."\').hide();', 500)",
                    							  "onclick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=receive_from_unapplied', 'unapplied=1', 'type=".$type."');")."
						</td>
					</tr>
				</table>
			</div>";
			$this->content['html'][$holder.$this->popup_id] =  $tbl;
		}
	}

	function edit_invoice() {

		if ( $this->ajax_vars['popup_id'] )
			$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];

		if ( ! $this->fetch_invoice_record($this->ajax_vars['invoice_hash'], 1) )
            return $this->__trigger_error("System error encountered during invoice lookup. Unable to fetch invoice record for database edit/update. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 1);

		$this->proposals = new proposals($this->current_hash);
		if ( ! $this->proposals->fetch_master_record($this->proposal_hash) )
    		return $this->__trigger_error("System error encountered during proposal lookup. Unable to fetch proposal record for database edit/update. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 1);

		if ( $payment_hash = $this->ajax_vars['payment_hash'] ) {

			if ( ! $this->fetch_payment_record($payment_hash) )
                return $this->__trigger_error("System error encountered during payment lookup. Unable to fetch payment record for database update. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 1);
		}

		$this->content['popup_controls']['popup_title'] = "Customer Invoice Summary";

		if ( ! $this->payment_hash ) {

			$this->set_values( array(
    			'customer_hash'  =>  $this->current_invoice['customer_hash']
    		) );

			if ( $unapplied = $this->fetch_customer_deposits($this->proposal_hash) ) {

				for ( $i = 0; $i < count($unapplied); $i++ ) {

					if ( bccomp($unapplied[$i]['balance'], 0, 2) == 1 ) {

						$rcv_from_un = true;
						break;
					}
				}
			}

			$r = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
			                       FROM customer_credit t1
			                       WHERE t1.customer_hash = '{$this->current_invoice['customer_hash']}' AND t1.balance > 0 AND t1.deleted = 0");
			$customer_credits = $this->db->result($r, 0, 'Total');
		}

        $lines = new line_items($this->proposal_hash, $this->current_invoice['punch']);
        $lines->set_invoice_tax_flags($this->invoice_hash);

        for ( $i = 0; $i < $this->current_invoice['total_items']; $i++ ) {

            if ( ! $lines->fetch_line_item_record($this->current_invoice['line_items'][$i]) )
            	return $this->__trigger_error("System error encountered when attempting to lookup one or more invoice line items. Can't fetch line item for database edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 1);

            //TODO remove when we have modern browser support.
            $lines->current_line['item_descr'] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $lines->current_line['item_descr']);	
            	
            $item_table .= "
            <tr style=\"background-color:#efefef;\">
                <td colspan=\"3\" style=\"font-weight:bold;\" class=\"smallfont\">" .
	                ( strlen($lines->current_line['item_descr']) > 65 ?
	                    "<span title=\"" . htmlentities($lines->current_line['item_descr'], ENT_QUOTES) . "\">" . stripslashes( substr($lines->current_line['item_descr'], 0, 60) ) . "...</span>" : stripslashes($lines->current_line['item_descr'])
	                ) . "
                    &nbsp;&nbsp;" .
	                ( $this->lock && $this->p->ck(get_class($this), 'E', 'line_items') && ! $this->current_invoice['deleted'] ? "
                        <span style=\"font-weight:normal;\">
                            [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_item', 'proposal_hash={$this->proposal_hash}', 'item_hash={$this->current_invoice['line_items'][$i]}', 'invoice_hash={$this->invoice_hash}', 'parent_popup_id={$this->popup_id}', 'popup_id=line_item_detail', 'from_edit=invoice', 'row_lock={$this->lock}');\" class=\"link_standard\">edit</a></small>]
                        </span>" : NULL
                    ) . "
                </td>
            </tr>
            <tr style=\"background-color:#efefef;\">
                <td colspan=\"3\" style=\"padding-bottom:0;padding-top:0;\" class=\"smallfont\">
                    Line: {$lines->current_line['po_line_no']}
                    <span style=\"font-style:italic;padding-left:5px;\">" . stripslashes($lines->current_line['item_product']) . "</span>
                </td>
            </tr>
            <tr style=\"background-color:#efefef;\">
                <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\">
                    <div>Qty: {$lines->current_line['qty']}</div>
                    <div style=\"padding-top:5px;\">
                        Item Cost: " .
                        ( bccomp($lines->current_line['cost'], 0, 2) == -1 ?
                            "(\$" . number_format( bcmul($lines->current_line['cost'], -1, 2), 2) . ")" : "\$" . number_format($lines->current_line['cost'], 2)
                        ) . "
                    </div>
                    <div style=\"padding-top:5px;\">
                        Ext Cost: " .
                        ( bccomp($lines->current_line['ext_cost'], 0, 2) == -1 ?
                            "(\$" . number_format( bcmul($lines->current_line['ext_cost'], -1, 2), 2) . ")" : "\$" . number_format($lines->current_line['ext_cost'], 2)
                        ) . "
                    </div>
                </td>
                <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\">
                    <div>Item No: {$lines->current_line['item_no']}</div>
                    <div style=\"padding-top:5px;\">
                        Item Sell: " .
                        ( bccomp($lines->current_line['sell'], 0, 2) == -1 ?
                            "(\$" . number_format( bcmul($lines->current_line['sell'], -1, 2), 2) . ")" : "\$" . number_format($lines->current_line['sell'], 2)
                        ) . "
                    </div>
                    <div style=\"padding-top:5px;\">
                        Ext Sell: " .
                        ( bccomp($lines->current_line['ext_sell'], 0, 2) == -1 ?
                            "(\$" . number_format( bcmul($lines->current_line['ext_sell'], -1, 2), 2) . ")" : "\$" . number_format($lines->current_line['ext_sell'], 2)
                        ) . "
                    </div>
                </td>
                <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\">
                    <div>Ack No: {$lines->current_line['ack_no']}</div>
                    <div style=\"padding-top:5px;\">
                        Ship Date: " .
                        ( $lines->current_line['ship_date'] && $lines->current_line['ship_date'] != '0000-00-00' ?
                            date(DATE_FORMAT, strtotime($lines->current_line['ship_date'])) : NULL
                        ) . "
                    </div>
                    <div style=\"padding-top:5px;\">
                        Receive Date: " .
                        ( $lines->current_line['receive_date'] && $lines->current_line['receive_date'] != '0000-00-00' ?
                            date(DATE_FORMAT, strtotime($lines->current_line['receive_date'])) : NULL
                        ) . "
                    </div>
                </td>
            </tr>";
        }

        if ( list($finance_charges, $total_finance) = $this->fetch_finance_charges($this->invoice_hash) ) {

            for ( $i = 0; $i < count($finance_charges); $i++ ) {

	            $item_table .= "
	            <tr style=\"background-color:#efefef;\">
	                <td colspan=\"3\" style=\"font-weight:bold;\" class=\"smallfont\">" .
	    	            ( $this->p->ck(get_class($this), 'E') ? "
	                        <span style=\"margin-right:3px;\"><a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to remove this finance charge from your customer invoice?')){submit_form(\$('invoice_hash').form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_finance_charge', 'charge_hash={$finance_charges[$i]['charge_hash']}', 'rm=1');}\"><img src=\"images/void_check.gif\" border=\"0\" title=\"Remove these finance charges\" /></a></span>" : NULL
	    	            ) . "
    	                Finance Charges
    	            </td>
	            </tr>
	            <tr style=\"background-color:#efefef;\">
	                <td colspan=\"3\" style=\"padding-bottom:0;padding-top:0;font-style:italic;\" class=\"smallfont\">
	                    " . stripslashes($finance_charges[$i]['comment']) . "
	                </td>
	            </tr>
	            <tr style=\"background-color:#efefef;\">
	                <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\">
	                    <div>Amount: \$" . number_format($finance_charges[$i]['amount'], 2) . "</div>
	                </td>
	                <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\">
	                    <div>Date: " . date(DATE_FORMAT, strtotime($finance_charges[$i]['date'])) . "</div>

	                </td>
	                <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\">&nbsp;</td>
	            </tr>";
            }

        }

        if ( $this->invoice_hash && bccomp($this->current_invoice['amount'], $this->current_invoice['balance'], 2) )
            list($payment_applied, $credit_applied) = $this->total_payments($this->invoice_hash);

        if ( $this->current_invoice['currency'] )
            $sys = new system_config();

        $checking_account_hash = $checking_account_name = array();
		$result = $this->db->query("SELECT t1.account_hash, t1.account_no, t1.account_name
									FROM accounts t1
									WHERE t1.account_type = 'CA' AND t1.checking = 1
									ORDER BY t1.account_no, t1.account_name ASC");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$checking_account_hash[] = $row['account_hash'];
			$checking_account_name[] = ( trim($row['account_no']) ? trim($row['account_no']) . " : " : NULL ) . stripslashes($row['account_name']);
		}

		if ( defined('DEFAULT_CASH_ACCT') ) {

			if ( ! $checking_account_hash || ! in_array(DEFAULT_CASH_ACCT, $checking_account_hash) ) {

				$accounts = new accounting;
				if ( $accounts->fetch_account_record(DEFAULT_CASH_ACCT) ) {

    				$checking_account_hash[] = DEFAULT_CASH_ACCT;
    				$checking_account_name[] = ( trim($accounts->current_account['account_no']) ? trim($accounts->current_account['account_no']) . " : " : NULL ) . stripslashes($accounts->current_account['account_name']);
				}
			}
		}

		if ( $lines->tax_country == 'CA' ) {

			list($total_tax, $tax_rules, $tax_local, $indiv_tax) = $lines->fetch_invoice_tax($this->invoice_hash);
			if ( $indiv_tax ) {

				$k = 0;
                while ( list($tax_hash, $local_name) = each($tax_local) ) {

                    $k++;
                    $tax_tbl .= "
                    <div " . ( $k < count($tax_local) ? "style=\"margin-bottom:5px;\"" : NULL ) . ">
                        \$" . number_format( _round($tax_rules[$tax_hash], 2), 2) . "
                    </div>";

                    $tax_msg .= "
                    <div style=\"" . ( $k < count($tax_local) ? "margin-bottom:5px;" : NULL ) . "font-size:92%;\">
                        Tax : " . strtoupper( stripslashes($local_name) ) . "
                    </div>";
                }
			}
		}

		$tbl =
		$this->form->form_tag().
		$this->form->hidden( array(
    		"invoice_hash"    => $this->invoice_hash,
    		"payment_hash"    => $this->payment_hash,
    		"proposal_hash"   => $this->proposal_hash,
    		"popup_id"        => $this->popup_id
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"padding:0;\">
						 <div style=\"background-color:#ffffff;height:100%;\">
							<div style=\"margin:15px 35px;\">
								<h3 style=\"margin-bottom:5px;color:#00477f;\">
									Customer Invoice : {$this->current_invoice['invoice_no']}
								</h3>
								<div style=\"padding:5px 0 5px 5px;\">" .
    								( $this->p->lock_stat(get_class($this), $this->popup_id) ?
    									"<div style=\"float:right;padding-right:15px;\">" . $this->p->lock_stat(get_class($this), $this->popup_id) . "</div>" : NULL
    								) .
									"<a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'print_invoice', 'proposal_hash={$this->proposal_hash}', 'invoice_hash={$this->invoice_hash}', 'popup_id=print_po');\" class=\"link_standard\"><img src=\"images/print.gif\" title=\"Print this customer invoice.\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;&nbsp;" .
									( $this->current_invoice['status'] != 1 && $this->current_invoice['status'] != 2 ?
    									"<a href=\"javascript:void(0);\" onClick=\"if(confirm('This invoice was already sent to " . stripslashes($this->proposals->current_proposal['customer']) . ". Are you sure you want to re-send?')){}\" class=\"link_standard\"><img src=\"images/resend.gif\" title=\"Re-send this invoice to " . htmlentities($this->proposals->current_proposal['customer'], ENT_QUOTES) . "\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;&nbsp;" : NULL
									) .
									( ! $this->current_invoice['reconciled'] && ! $this->current_invoice['deleted'] && $this->p->ck(get_class($this), 'D') && $this->lock && (! count($this->current_invoice['payments']) || !$this->current_invoice['payments'][0]['payment_hash']) ?
    									"<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to kill and delete this invoice? All items will be reverted to un-invoiced and the invoice will be deleted. This action CAN NOT be undone!')){submit_form(\$('invoice_hash').form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_delete_invoice');}\" class=\"link_standard\"><img src=\"images/kill.gif\" title=\"Kill & Delete this invoice.\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;&nbsp;" : NULL
									) .
									"<a href=\"javascript:void(0);\" onClick=\"agent.call('mail', 'sf_loadcontent', 'show_popup_window', 'agent', 'proposal_hash={$this->proposal_hash}', 'cc=customer_invoice', 'invoice_hash={$this->invoice_hash}', 'popup_id=line_item2');\" class=\"link_standard\"><img src=\"images/send.gif\" title=\"Open the mail &amp; fax terminal\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;&nbsp;" .
									( $this->p->ck(get_class($this), 'E') ?
    									"<a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'finance_charges', 'invoice_hash={$this->invoice_hash}', 'popup_id=finance_charge_win', 'invoice_popup={$this->popup_id}');\" class=\"link_standard\"><img src=\"images/finance_charges.gif\" title=\"Add finance charges to this invoice\" border=\"0\" style=\"margin-bottom:2px;\" /></a>&nbsp;&nbsp;&nbsp;&nbsp;" : NULL
									) .
									( $this->p->ck('customer_credits', 'A') ?
    									( ! $this->current_invoice['direct_bill'] && strtolower($this->current_invoice['invoice_to']) != 'v' ?
                                            "<a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits', 'sf_loadcontent', 'show_popup_window', 'invoice_credits', 'invoice_hash={$this->invoice_hash}', 'popup_id=credits_win', 'invoice_popup={$this->popup_id}');\" class=\"link_standard\">" : NULL
    									) .
        								"<img src=\"images/invoice_credits.gif\" title=\"" . ( $this->current_invoice['direct_bill'] || strtolower($this->current_invoice['invoice_to']) == 'v' ? "Credits may not be issued against direct bill or vendor facing invoices." : "Issue credit against this invoice" ) . "\" border=\"0\" style=\"margin-bottom:2px;" . ( $this->current_invoice['direct_bill'] || strtolower($this->current_invoice['invoice_to']) == 'v' ? "opacity:0.4;filter:alpha(opacity=40);" : NULL ) . "\" />" .
    									( ! $this->current_invoice['direct_bill'] && strtolower($this->current_invoice['invoice_to']) != 'v' ?
        									"</a>" : NULL
    									) . "&nbsp;&nbsp;&nbsp;&nbsp;" : NULL
                                    ) . "
								</div>
								<ul id=\"cinvoicemaintab{$this->popup_id}\" class=\"shadetabs\">
									<li><a href=\"javascript:void(0);\" id=\"cntrl_cinvoicetcontent1{$this->popup_id}\" rel=\"cinvoicetcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\" >Invoice Details</a></li>" .
									( ! $this->current_invoice['deleted'] ?
    									"<li ><a href=\"javascript:void(0);\" rel=\"cinvoicetcontent2{$this->popup_id}\" id=\"cntrl_cinvoicetcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\" >Receive Payment</a></li>" : NULL
									) .
									( $this->current_invoice['payments'] ?
    									"<li ><a href=\"javascript:void(0);\" rel=\"cinvoicetcontent3{$this->popup_id}\" id=\"cntrl_cinvoicetcontent3{$this->popup_id}\" onClick=\"expandcontent(this);\" >Receipt Log</a></li>" : NULL
									) . "
								</ul>
								<div id=\"cinvoicetcontent1{$this->popup_id}\" class=\"tabcontent\">
									<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:90%;\">
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;vertical-align:top;width:125px;\">Invoice To:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
                                            ( $this->current_invoice['invoice_to'] == 'C' ?
												stripslashes($this->current_invoice['customer_name']) .
												( $this->current_invoice['bill_to_hash'] ?
													"<div style=\"padding-top:5px;font-style:italic;\">" . stripslashes($this->current_invoice['bill_to_name']) . "</div>" : NULL
												) : stripslashes($this->current_invoice['vendor_name'])
    										) . "
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;width:125px;\">Sales Rep:</td>
											<td style=\"background-color:#ffffff;\">" . stripslashes($this->proposals->current_proposal['sales_rep']) . "</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;width:125px;\">Invoice Date:</td>
											<td style=\"background-color:#ffffff;\">" . date(DATE_FORMAT, strtotime($this->current_invoice['invoice_date'])) . "</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;width:125px;\">Due Date:</td>
											<td style=\"background-color:#ffffff;\">" .
    										( ( is_numeric($this->current_invoice['payment_terms']) && $this->current_invoice['payment_terms'] === 0 ) || ( ! is_numeric($this->current_invoice['payment_terms']) && DEFAULT_CUST_PAY_TERMS === 0 ) ?
        										" Upon Receipt" : date(DATE_FORMAT, strtotime("{$this->current_invoice['invoice_date']} +" . ( is_numeric($this->current_invoice['payment_terms']) ? $this->current_invoice['payment_terms'] : DEFAULT_CUST_PAY_TERMS ) . " days"))
    										) . "
        									</td>
										</tr>" .
                                        ( $this->current_invoice['currency'] ?
    										"<tr>
	                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;width:125px;\">Currency: </td>
	                                            <td style=\"background-color:#ffffff;\">
	                                                {$this->current_invoice['currency']}
	                                                <span style=\"padding-left:5px;font-style:italic;\">Amounts shown in {$sys->home_currency['code']}</span>
	                                            </td>
											</tr>" .
											( $this->current_invoice['currency'] != $sys->home_currency['code'] ?
		                                        "<tr>
		                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;width:125px;\">Rate: </td>
		                                            <td style=\"background-color:#ffffff;\">" . rtrim( trim($this->current_invoice['exchange_rate'], '0'), '.') . "%</td>
		                                        </tr>" : NULL
											) : NULL
										) . "
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;vertical-align:top;padding-top:8px;width:125px;\">
												Invoice Amount:
											</td>
											<td style=\"background-color:#ffffff;\">
												<table cellspacing=\"0\" cellpadding=\"5\">
													<tr>
														<td style=\"text-align:right;\">\$" . number_format( $this->current_invoice['amount'] - $this->current_invoice['tax'] - $this->current_invoice['finance_charges'], 2) . "</td>
														<td style=\"padding-left:5px;\">" .
                										( ! $this->current_invoice['direct_bill'] ?
                    										"Total Sell" : NULL
                										) . "
                										</td>
													</tr>" .
                                                    ( bccomp($this->current_invoice['tax'], 0, 2) == 1 ?
														"<tr>
															<td style=\"text-align:right\">" .
                                                            ( $lines->tax_country == 'CA' && $tax_tbl ?
	                                                            $tax_tbl : "\$" . number_format($this->current_invoice['tax'], 2)
	                                                        ) . "
	                                                        </td>
															<td style=\"padding-left:5px;\">" .
		                                                    ( $lines->tax_country == 'CA' && $tax_tbl ?
	                                                            $tax_msg : "<div style=\"font-size:92%;\">Sales Tax</div>"
	                                                        ) . "
	                                                        </td>
														</tr>" : NULL
	                                                ) .
	                                                ( $lines->tax_country == 'CA' && $tax_tbl ?
	                                                    "<tr>
	                                                        <td style=\"text-align:right;border-top:1px solid black;\">\$" . number_format($total_tax, 2) . "</td>
	                                                        <td style=\"padding-left:5px;font-size:92%;border-top:1px solid black;\">Total Tax</td>
	                                                    </tr>" : NULL
	                                                ) .
	                                                ( bccomp($this->current_invoice['finance_charges'], 0, 2) ?
	                                                    "<tr>
	                                                        <td style=\"text-align:right;\">\$" . number_format($this->current_invoice['finance_charges'], 2) . "</td>
	                                                        <td style=\"padding-left:5px;font-size:92%;vertical-align:bottom;\">Finance Charges</td>
	                                                    </tr>" : NULL
	                                                ) .
	                                                ( bccomp($this->current_invoice['deposit_applied'], 0, 2) ?
														"<tr>
															<td style=\"text-align:right;border-top:1px solid black;\">\$" . number_format($this->current_invoice['amount'], 2) . "</td>
															<td style=\"padding-left:5px;font-style:italic;border-top:1px solid black;\">Subtotal</td>
														</tr>" : NULL
	                                                ) . "
													<tr>" .
	                                                ( bccomp($this->current_invoice['deposit_applied'], 0, 2) == 1 ?
														"<td style=\"text-align:right\">(\$" . number_format($this->current_invoice['deposit_applied'], 2) . ")</td>
    														<td style=\"padding-left:5px;font-style:italic;\">Applied Deposit</td>
    													</tr>" : NULL
	                                                ) .
	                                                ( bccomp($this->current_invoice['deposit_applied'], 0, 2) || bccomp($this->current_invoice['tax'], 0, 2) || bccomp($this->current_invoice['finance_charges'], 0, 2) ?
														"<tr>
															<td style=\"text-align:right;border-top:1px solid black;\">\$" . number_format($this->current_invoice['amount'] - $this->current_invoice['deposit_applied'], 2) . "</td>
															<td style=\"padding-left:5px;border-top:1px solid black;\">Total Invoiced</td>
														</tr>" : NULL
	                                                ) .
	                                                ( bccomp($credit_applied, 0, 2) == 1 ?
														"<tr>
	                                                        <td style=\"text-align:right;\">(\$" . number_format($credit_applied, 2) . ")</td>
	                                                        <td style=\"padding-left:5px;font-style:italic;\">Credit Applied</td>
														</tr>" : NULL
	                                                ) . "
												</table>
											</td>
										</tr>
                                        <tr>
                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;width:125px;\">Open Balance:</td>
                                            <td style=\"background-color:#ffffff;\">" .
                                            ( bccomp($this->current_invoice['balance'], 0, 2) == -1 ?
                                                "(\$" . number_format( bcmul($this->current_invoice['balance'], -1, 2), 2) . ")" : "\$" . number_format($this->current_invoice['balance'], 2)
                                            ) . "
                                            </td>
                                        </tr>
										<tr>
											<td style=\"text-align:right;vertical-align:top;background-color:#efefef;font-weight:bold;width:125px;\">Invoice Date:</td>
											<td style=\"vertical-align:top;background-color:#ffffff;\">" . date(DATE_FORMAT, strtotime($this->current_invoice['invoice_date'])) . "</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;vertical-align:top;width:125px;\">Sent By:</td>
											<td style=\"background-color:#ffffff;vertical-align:bottom;\">" .
    										( $this->current_invoice['transmit_type'] == 'E' ?
        										'Email' :
        										( $this->current_invoice['transmit_type'] == 'F' ?
            										'Fax' : 'Standard Mail'
        										)
        									) .
        									( $this->current_invoice['transmit_type'] != 'M' && ( ! $this->current_invoice['status'] || $this->current_invoice['status'] == 3 ) ?
													" on " . date(TIMESTAMP_FORMAT, $this->current_invoice['transmit_timestamp']) : NULL
        									) .
        									( $this->current_invoice['transmit_type'] != 'M' ?
												"<div style=\"padding-top:5px;\">
													{$this->invoice_icons[ $this->current_invoice['status'] ]}&nbsp;
													<i><small>({$this->invoice_status[ $this->current_invoice['status'] ]})</small></i>
												</div>" : NULL
											) . "
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;background-color:#efefef;vertical-align:top;font-weight:bold;width:125px;\" nowrap>Sent To:</td>
											<td style=\"background-color:#ffffff;\">" . stripslashes( nl2br($this->current_invoice['transmit_to']) ) . "</td>
										</tr>" .
										( $this->current_invoice['transmit_confirm'] ?
											"<tr>
												<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;\">Confirmation No:</td>
												<td style=\"background-color:#ffffff;vertical-align:bottom;\">{$this->current_invoice['transmit_confirm']}</td>
											</tr>" : NULL
										) . "
 										<tr>
											<td style=\"text-align:right;background-color:#efefef;vertical-align:top;font-weight:bold;width:125px;\" nowrap>Remit To:</td>
											<td style=\"background-color:#ffffff;\">" . stripslashes( nl2br($this->current_invoice['remit_to_addr']) ) . "</td>
										</tr>
                                    </table>
	                                <div style=\"padding-top:10px;font-weight:bold;\" >
	                                    Item Summary:
	                                    <div style=\"margin-top:5px;\" id=\"summary{$this->popup_id}\"></div>
	                                </div>
	                                <div style=\"padding-top:0;height:200px;overflow-y:auto;overflow-x:hidden;\">
	                                    <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:97%;\" >
	                                        $item_table
	                                    </table>
	                                </div>
								</div>
								<div id=\"cinvoicetcontent2{$this->popup_id}\" class=\"tabcontent\">
									<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:90%;\">" .
										( ( $this->payment_hash && $this->current_payment['currency'] && $this->current_payment['exchange_rate'] ) || ( ! $this->payment_hash && defined('MULTI_CURRENCY') && $this->current_invoice['currency'] ) ?
	                                        "<tr>
	                                            <td style=\"background-color:#efefef;text-align:right;width:125px;padding-top:10px;font-weight:bold;\">Currency:</td>
	                                            <td style=\"background-color:#ffffff;padding-top:10px;\" >" .
	        									( $this->current_payment ?
	                                                $this->current_payment['currency']
	                                                :
	                                                $this->form->select(
	                                                    "currency",
	                                                    array($this->current_invoice['currency'], $sys->home_currency['code']),
	                                                    $this->current_invoice['currency'],
	                                                    array($this->current_invoice['currency'], $sys->home_currency['code']),
	                                                    "blank=1"
	                                                )
	                                            ) . "
	                                            </td>
	                                        </tr>" .
	                                        ( $this->current_payment && $this->current_payment['currency'] ?
		                                        "<tr>
		                                            <td style=\"background-color:#efefef;text-align:right;width:125px;padding-top:10px;font-weight:bold;\">Rate:</td>
		                                            <td style=\"background-color:#ffffff;padding-top:10px;\" >" . rtrim( trim($this->current_payment['exchange_rate'], '0'), '.') . "%</td>
		                                        </tr>" : NULL
	                                        ) : NULL
	                                    ) . "
    									<tr>
											<td style=\"background-color:#efefef;text-align:right;width:125px;padding-top:10px;font-weight:bold;\" id=\"err1{$this->popup_id}\">Check No:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\" >
												<div id=\"toggle_receipt_new{$this->popup_id}\" style=\"display:block;\">" .
													( $rcv_from_un ?
														"<div style=\"float:right;padding-right:15px;\"><span id=\"receive_from_holder{$this->popup_id}\"></span>[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'receive_from_unapplied', 'type=rcv_pmt', 'proposal_hash={$this->proposal_hash}', 'popup_id={$this->popup_id}', 'invoice_hash={$this->invoice_hash}', 'customer_hash={$this->current_invoice['customer_hash']}');\" class=\"link_standard\">Receive From Proposal Deposits</a></small>]</div>" : NULL
													) .
													$this->form->text_box(
    													"name=check_no",
                        								"value={$this->current_payment['check_no']}",
                        								"size=15",
                                                        ( $this->current_payment['applied_from'] ? "readonly" : NULL )
                                                    ) . "
												</div>
												<div id=\"toggle_receipt_unapplied{$this->popup_id}\" style=\"display:none;\"></div>
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\" id=\"err2{$this->popup_id}\">Receipt Date:</td>
											<td style=\"background-color:#ffffff;\">
                                                <div id=\"toggle_credits{$this->popup_id}\" style=\"float:right;padding-right:15px;\" style=\"display:block;\">" .
                                                ( $customer_credits ? "
                                                    <span id=\"apply_credit_holder{$this->popup_id}\"></span>[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'receive_from_unapplied', 'type=apply_credits', 'proposal_hash={$this->proposal_hash}', 'popup_id={$this->popup_id}', 'invoice_hash={$this->invoice_hash}', 'customer_hash={$this->current_invoice['customer_hash']}');\" class=\"link_standard\">Apply Customer Credits</a></small>]" : NULL
                                                ) . "
                                                </div>
                                                <div id=\"receipt_date_holder{$this->popup_id}\"></div>
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\" id=\"err3{$this->popup_id}\">Amount:</td>
											<td style=\"background-color:#ffffff;\">" .
    											$this->form->text_box(
        											"name=amount",
                            						"value=" . number_format( ( $this->current_payment['currency'] && $this->current_payment['exchange_rate'] ? currency_exchange($this->current_payment['receipt_amount'], $this->current_payment['exchange_rate']) : $this->current_payment['receipt_amount'] ), 2),
                            						"size=10",
                            						"style=text-align:right;",
                            						"onFocus=this.select();",
                            						"onBlur=this.value=formatCurrency(this.value);")."
    										</td>
										</tr>" .
                                        ( ! $this->payment_hash || ( $this->payment_hash && ! $this->current_payment['applied_from'] ) ?
											"<tr>
												<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\" id=\"err3{$this->popup_id}\">Account:</td>
												<td style=\"background-color:#ffffff;\">" .
												( $this->payment_hash ?
													$this->current_payment['account_name'] .
													$this->form->hidden( array(
    													'payment_account_hash' => $this->current_payment['account_hash']
													) )  :
													( $checking_account_hash ?
														"&nbsp;&nbsp;" .
														$this->form->select(
    														"payment_account_hash",
															$checking_account_name,
															( defined('DEFAULT_CASH_ACCT') ? DEFAULT_CASH_ACCT : '' ),
															$checking_account_hash,
															"blank=1"
														) : "<img src=\"images/alert.gif\" />&nbsp;&nbsp;You have no checking accounts defined!"
													)
												) . "
												</td>
											</tr>" : NULL
										) . "
										<tr>
											<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;padding-top:10px\">Comments:</td>
											<td style=\"background-color:#ffffff;\">" .
        										$this->form->text_area(
            										"name=comments",
            										"value=" . stripslashes($this->current_payment['comments']),
            										"rows=4",
            										"cols=40"
        										) . "
        									</td>
										</tr>
									</table>" .
                                    ( $this->current_payment['applied_from'] ?
										"<div style=\"margin-top:10px;font-style:italic;\">
	                                        This receipt was applied from a " .
                                            ( $this->current_payment['applied_type'] == 'C' ?
	                                            "customer credit" : "proposal deposit"
                                            ) . ". Any adjustments will be applied to that transaction.
										</div>" : NULL
                                    ) . "
									<div style=\"padding-top:10px;\">" .
                                    ( ! $this->current_invoice['reconciled'] && ! $this->current_invoice['deleted'] && $this->lock ?
										$this->form->button(
    										"value=Save Payment",
                                            "id=rcv_btn",
                                            "onClick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_rcv_pmt');this.disabled=1"
										) .
										"&nbsp;&nbsp;" .
										( $this->payment_hash ?
											$this->form->button(
	    										"value=Delete",
	    										"id=rcv_btn_rm",
	    										"onClick=if(confirm('Are you sure you want to delete this receipt? This action CANNOT be undone!')){submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_rcv_pmt', 'delete=true');this.disabled=1}"
											) : NULL
    									) : NULL
    								) . "
									</div>
								</div>";

                            $this->content["jscript"][] = "setTimeout('DateInput(\'receipt_date\', true, \'YYYY-MM-DD\', \'" . ( $this->current_payment['receipt_date'] && $this->current_payment['receipt_date']!= '0000-00-00' ? $this->current_payment['receipt_date'] : date("Y-m-d") ) . "\', 1, \'receipt_date_holder{$this->popup_id}\')', 25);";

							if ( $this->current_invoice['payments'] ) {

								$colspan = ( $this->current_invoice['currency'] ? 5 : 4 );

								$tbl .= "
								<div id=\"cinvoicetcontent3{$this->popup_id}\" class=\"tabcontent\">
									<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:600px;margin-top:0;\" class=\"smallfont\">
										<tr>
											<td style=\"padding:0;\">
												 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\" >
													<tr>
														<td style=\"font-weight:bold;vertical-align:middle;background-color:#efefef;\" colspan=\"$colspan\" class=\"smallfont\">
															There are " . count($this->current_invoice['payments']) . " receipt" . ( count($this->current_invoice['payments']) > 1 ? "s" : NULL ) . " against this invoice.
															<div style=\"margin-left:25px;padding-top:5px;font-weight:normal;font-style:italic;\">" .
															( bccomp($this->current_invoice['balance'], 0, 2) != 1 ?
																"This invoice has been paid in full" : "This invoice has an outstanding balance of \$" . number_format($this->current_invoice['balance'], 2)
															) . "
															</div>
														</td>
													</tr>
													<tr class=\"thead\" style=\"font-weight:bold;\">
														<td >Check No</td>
														<td >Date</td>
														<td >Account</td>" .
														( $this->current_invoice['currency'] ?
    														"<td style=\"text-align:right;\">Currency Adj</td>" : NULL
														) . "
														<td class=\"num_field\">Rcpt Amount</td>
													</tr>";

												for ( $i = 0; $i < count($this->current_invoice['payments']); $i++ ) {

													$onClick = "agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'proposal_hash={$this->proposal_hash}', 'invoice_hash={$this->invoice_hash}', 'payment_hash={$this->current_invoice['payments'][$i]['payment_hash']}', 'popup_id=line_item', 'tab_to=tcontent2');";
													if ( $this->current_invoice['payments'][$i]['applied_from'] && $this->current_invoice['payments'][$i]['applied_type'] == 'C' )
                                                        $onClick = "agent.call('customer_credits', 'sf_loadcontent', 'show_popup_window', 'invoice_credits', 'proposal_hash={$this->proposal_hash}', 'invoice_hash={$this->invoice_hash}', 'credit_hash={$this->current_invoice['payments'][$i]['applied_from']}', 'payment_hash={$this->current_invoice['payments'][$i]['payment_hash']}', 'popup_id=credit_win', 'invoice_popup={$this->popup_id}');";

													unset($reconciled_acct);
													if ( $this->current_invoice['payments'][$i]['reconciled'] ) {

                                                        $r = $this->db->query("SELECT t1.account_name
                                                                               FROM accounts t1
                                                                               WHERE t1.account_hash = '{$this->current_invoice['payments'][$i]['applied_from']}'");
                                                        $reconciled_acct = $this->db->result($r, 0, 'account_name');
													}

													$tbl .= "
													<tr " . ( ! $this->current_invoice['reconciled'] && ! $this->current_invoice['deleted'] && ! $this->current_invoice['payments'][$i]['deleted'] ? "title=\"Edit this receipt\" onClick=\"$onClick\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
														<td class=\"smallfont\" style=\"border-bottom:1px solid #cccccc;\">" .
    													( $this->current_invoice['payments'][$i]['reconciled'] ?
															"<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to undo this auto reconciliation payment?')){agent.call('customer_invoice', 'sf_loadcontent', 'refresh_form', 'remove_reconcile', 'invoice_hash={$this->invoice_hash}', 'payment_hash={$this->current_invoice['payments'][$i]['payment_hash']}', 'popup_id=line_item')}\"><img src=\"images/void_check.gif\" title=\"Undo auto reconciliation function\" border=\"0\" /></a>&nbsp;[AUTO RECONCILED]" : $this->current_invoice['payments'][$i]['check_no']
    													) . "
														</td>
														<td class=\"smallfont\" style=\"border-bottom:1px solid #cccccc;\">" . date(DATE_FORMAT, strtotime($this->current_invoice['payments'][$i]['receipt_date'])) . "</td>
														<td class=\"smallfont\" style=\"border-bottom:1px solid #cccccc;\">" .
    													( $this->current_invoice['payments'][$i]['applied_from'] ?
															( $this->current_invoice['payments'][$i]['reconciled'] ?
															    $reconciled_acct : "<i>Applied from Customer " .
															    ( $this->current_invoice['payments'][$i]['applied_type'] == 'C' ?
    															    "Credit" : "Deposit"
															    ) . "</i>"
															) : $this->current_invoice['payments'][$i]['account_name']
														) . "
														</td>" .
														( $this->current_invoice['currency'] ?
															"<td class=\"smallfont\" style=\"border-bottom:1px solid #cccccc;text-align:right;" . ( $this->current_invoice['payments'][$i]['currency_adjustment'] ? "padding-right:5px;" : NULL ) . "\">" .
	    														( bccomp($this->current_invoice['payments'][$i]['currency_adjustment'], 0, 2) ?
    	                                                            ( bccomp($this->current_invoice['payments'][$i]['currency_adjustment'], 0, 2) == -1 ?
    	                                                                "(\$" . number_format( bcmul($this->current_invoice['payments'][$i]['currency_adjustment'], -1, 2), 2) . ")" : "\$" . number_format($this->current_invoice['payments'][$i]['currency_adjustment'], 2)
    	                                                            ) : "&nbsp;"
    	                                                        ) . "
	                                                        </td>" : NULL
	                                                    ) . "
														<td class=\"smallfont\" style=\"text-align:right;border-bottom:1px solid #cccccc;\">\$" . number_format($this->current_invoice['payments'][$i]['receipt_amount'], 2) . "</td>
													</tr>";

													$total_rcvd = $total_rcvd + $this->current_invoice['payments'][$i]['receipt_amount'] ;
												}

												$tbl .= "
                                                    <tr>
                                                        <td colspan=\"$colspan\" style=\"background-color:#efefef;text-align:right;\">
                                                            <div style=\"margin-top:15px;\">
	                                                            <table>
	                                                                <tr>
	                                                                    <td style=\"text-align:right;font-weight:bold;padding-bottom:3px;\" >Total Invoiced:</td>
	                                                                    <td style=\"font-weight:bold;text-align:left;padding-bottom:3px;\" >\$" . number_format( $this->current_invoice['amount'] - $this->current_invoice['deposit_applied'], 2) . "</td>
	                                                                </tr>
	                                                                <tr>
	                                                                    <td style=\"text-align:right;font-weight:bold;padding-bottom:3px;\" >Total Received:</td>
	                                                                    <td style=\"font-weight:bold;text-align:left;padding-bottom:3px;\" >\$" . number_format($total_rcvd, 2) . "</td>
	                                                                </tr>
	                                                                <tr>
	                                                                    <td style=\"text-align:right;font-weight:bold;\" >Balance Due:</td>
	                                                                    <td style=\"font-weight:bold;text-align:left;\">" .
	                                                                    ( bccomp($this->current_invoice['balance'], 0, 2) == -1 ?
	                                                                        "(\$" . number_format( bcmul($this->current_invoice['balance'], -1, 2), 2) . ")" : "\$" . number_format($this->current_invoice['balance'], 2)
	                                                                    ) . "
	                                                                    </td>
	                                                                </tr>
	                                                            </table>
	                                                        </div>
                                                        </td>
                                                    <tr>
												</table>
											</td>
										</tr>
									</table>
								</div>";
							}

							$tbl .= "
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>" .
		$this->form->close_form();

		if ( $this->invoice_hash )
			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'cinvoicemaintab{$this->popup_id}\')', 50);";

		if ( $this->ajax_vars['tab_to'] )
			$this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_cinvoice{$this->ajax_vars['tab_to']}{$this->popup_id}\'))', 100)";
		else
            $this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_cinvoicetcontent1{$this->popup_id}\'))', 100)";

		$this->content['popup_controls']['onclose'] = "agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'proposal_invoices', 'otf=1', 'proposal_hash={$this->proposals->proposal_hash}');";
		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function remove_reconcile() {

		$invoice_hash = $this->ajax_vars['invoice_hash'];
		$payment_hash = $this->ajax_vars['payment_hash'];
		$popup_id = $this->ajax_vars['popup_id'];

		if ( $this->fetch_invoice_record($invoice_hash) ) {

			if ( $this->current_invoice['deleted'] )
				return $this->__trigger_error("Unable to continue. This invoice has been flagged as deleted and cannot be edited.", E_USER_NOTICE, __FILE__, __LINE__, 1, 2);

			if ( $this->fetch_payment_record($payment_hash) ) {

				if ( $this->current_payment['deleted'] )
					return $this->__trigger_error("Unable to continue. This payment has been flagged as deleted and cannot be edited.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				$accounting = new accounting($this->current_hash);

                $posting_date = $this->current_payment['receipt_date'];

				if ( ! $this->db->start_transaction() )
					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);

				$accounting->start_transaction( array(
					'ar_invoice_hash'   =>  $this->invoice_hash,
					'customer_hash'     =>  $this->current_invoice['customer_hash'],
					'proposal_hash'     =>  $this->current_invoice['proposal_hash'],
					'ar_payment_hash'   =>  $payment_hash
				) );

				$audit_id = $accounting->audit_id();

                if ( ! $accounting->fetch_account_record($this->current_payment['applied_from']) )
                    return $this->__trigger_error("System error encountered when attempting to fetch reconciliation account. Please reload window and try again. <!-- Tried to fetch account [ {$this->current_payment['applied_from']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                $reconcile_amt = bcmul($this->current_payment['receipt_amount'], -1, 2);
                if ( $accounting->current_account['account_action'] == 'CR' )
                    $reconcile_amt = bcmul($reconcile_amt, -1, 2);

				if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_AR_ACCT, $this->current_payment['receipt_amount'], 'AD', "Auto Reconciliation Undo - Invoice {$this->current_invoice['invoice_no']}.") )
				    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
				if ( ! $accounting->exec_trans($audit_id, $posting_date, $this->current_payment['applied_from'], $reconcile_amt, 'AD', "Auto Reconciliation Undo - Invoice {$this->current_invoice['invoice_no']}.") )
				    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				$balance = bcadd($this->current_invoice['balance'], $this->current_payment['receipt_amount'], 2);
				$this->db->query("UPDATE `customer_invoice`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `reconciled` = 0 , `balance` = '$balance'
								  WHERE `invoice_hash` = '{$this->invoice_hash}'");
				if ($this->db->db_error)
					return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, 1);

				$this->db->query("UPDATE `customer_payment`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `deleted` = 1 , `deleted_date` = '".date("Y-m-d")."'
								  WHERE `payment_hash` = '".$this->current_payment['payment_hash']."'");
				if ($this->db->db_error)
					return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, 1);

				if ( ! $accounting->end_transaction() )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				$this->db->end_transaction();

				$this->content['page_feedback'] = "Reconciliation payment has been removed.";

			} else
                return $this->__trigger_error("System error encountered when attempting to lookup payment record. Please reload window and try again. <!-- Tried fetching payment [ $payment_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		} else
			return $this->__trigger_error("System error encountered when attempting to lookup invoice record for edit. Please reload window and try again. <!-- Tried fetching invoice [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		$this->content['action'] = 'continue';
		$this->content['jscript'] = "agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash=$invoice_hash', 'popup_id=$popup_id');";

		return;
	}

	function edit_item() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['invoice_hash'])
			return;

		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$proposals = new proposals($this->current_hash);

		$invoice_hash = $this->ajax_vars['invoice_hash'];
		$this->fetch_invoice_record($invoice_hash);

		$lines = new line_items($proposal_hash, $this->current_invoice['punch']);
		if ($item_hash = $this->ajax_vars['item_hash']) {
			$lines->fetch_line_item_record($item_hash);
			$this->content['popup_controls']['popup_title'] = "Edit an Invoiced Line Item";
		} else
			$this->content['popup_controls']['popup_title'] = "Add a Line Item To This Invoice";

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("invoice_hash" => $invoice_hash,
								  "proposal_hash" => $proposal_hash,
								  "popup_id" => $this->popup_id,
								  "item_hash" => $item_hash,
		                          "item_placeholder"  =>  1))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>".($item_hash ? "
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\" >
				<tr>
					<td style=\"background-color:#ffffff;padding-left:20px;\">
						<table style=\"width:100%\" >
							<tr>
								<td style=\"vertical-align:top;padding-bottom:5px;width:30%\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" >Vendor: </div>
									".$this->form->text_box("name=", "value=".$lines->current_line['item_vendor'], "readonly").
									$this->form->hidden(array("vendor_hash" => $lines->current_line['vendor_hash']))."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;width:30%\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" >Item Number: </div>
									".$this->form->text_box("name=item_no", "value=".$lines->current_line['item_no'], ($item_hash ? "readonly" : NULL))."
								</td>
								<td rowspan=\"4\" style=\"vertical-align:top;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\">Item Description: </div>
									".$this->form->text_area("name=item_descr",
															 "rows=11",
															 "cols=35",
															 "value=".$lines->current_line['item_descr'],
															 ($lines->current_line['import_data'] || $lines->current_line['item_code'] ? "readonly" : ''),
															 ($lines->current_line['import_data'] ? "title=This item was created from an import, editing has been disabled." : NULL)
															 )."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\">Ship To: </div>
									".$this->form->text_box("name=", "value=".$lines->current_line['item_ship_to'], "readonly").
									$this->form->hidden(array("ship_to_hash" => $lines->current_line['ship_to_hash']))."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\">Product / Service: </div>".($item_hash ? "
									<div id=\"vendor_product_holder\">
										".$this->form->text_box("name=", "value=".$lines->current_line['item_product'], "readonly").
										$this->form->hidden(array("vendor_hash" => $this->current_invoice['ship_to_hash'])) :
										$proposals->innerHTML_customer($this->current_invoice['vendor_hash'], 'item_po', 'vendor_product_holder', NULL))."
									</div>
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;font\">
									<div style=\"padding-bottom:5px;font-weight:bold;\">Quantity: </div>
									 ".$this->form->text_box("name=qty",
															 "value=".(!$item_hash ? 1 : strrev(substr(strstr(strrev($lines->current_line['qty']), '.'), 1)).(str_replace('0', '', substr(strrchr($lines->current_line['qty'], '.'), 1)) ?
																str_replace('0', '', strrchr($lines->current_line['qty'], '.')) : NULL)),
															 "size=10",
															 "maxlength=13",
															 "style=text-align:right;",
															 "readonly",
															 "title=This line has already been purchased, quantity cannot be changed."
															 )."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">".($item_hash ? "
									<div style=\"padding-bottom:5px;font-weight:bold;\">Item List:</div>
									".$this->form->text_box("name=list",
															 "value=".($lines->current_line['list'] ? number_format($lines->current_line['list'], 2) : NULL),
															 "maxlength=13",
															 "size=10",
															 "style=text-align:right;",
															 "readonly",
															 "title=This line has already been purchased, list cannot be changed."
															 ) : NULL)."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;font\">
									<div style=\"padding-bottom:5px;font-weight:bold;\">Item Cost:</div>
									".$this->form->text_box("name=cost",
															 "value=".($lines->current_line['cost'] ? number_format($lines->current_line['cost'], 2) : NULL),
															 "maxlength=13",
															 "size=10",
															 "style=text-align:right;",
															 "readonly",
															 "title=This line has already been purchased, cost cannot be changed."
															 )."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\">Item Sell:</div>
									".$this->form->text_box("name=sell",
															 "value=".($lines->current_line['sell'] ? number_format($lines->current_line['sell'], 2) : NULL),
															 "maxlength=13",
															 "size=10",
															 "style=text-align:right;",
															 "onBlur=this.value=formatCurrency(this.value)"
															 )."
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>" : NULL);
			if (!$item_hash){
				$proposals->fetch_master_record($proposal_hash);
				$lines = new line_items($proposals->proposal_hash, $this->current_invoice['punch']);

				$r = $this->db->query("SELECT COUNT(*) AS Total
									   FROM `line_items`
									   WHERE line_items.proposal_hash = '$proposal_hash' AND line_items.po_hash != '' AND line_items.invoice_hash = ''");
				if ($this->db->result($r, 0, 'Total'))
					$lines->fetch_line_items(0, $lines->total, 1);

				//if it's a direct bill only allow certain line items to be added
				if ($this->current_invoice['direct_bill']) {
					$direct_bill = true;
					for ($i = 0; $i < count($this->current_invoice['line_items']); $i++) {
						$r = $this->db->query("SELECT `direct_bill_amt` , `vendor_hash`
											   FROM `line_items`
											   WHERE `item_hash` = '".$this->current_invoice['line_items'][$i]."'");
						$direct_bill_vendor[] = $this->db->result($r, 0, 'vendor_hash');
					}
					if (is_array($direct_bill_vendor))
						$direct_bill_vendor = array_unique($direct_bill_vendor);
				}

				$tbl .= "
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\" >
					<tr>
						<td style=\"background-color:#ffffff;padding:20px;\">
							 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" class=\"table_list\" >
								<tr class=\"thead\" style=\"font-weight:bold;\">
									<td style=\"width:10px;\">".$this->form->checkbox("name=check_all", "value=1", "onClick=checkall(document.getElementsByName('invoice_item{$this->popup_id}[]'), this.checked);")."</td>
									<td style=\"width:200px;vertical-align:bottom;\">Item No.</td>
									<td style=\"width:300px;vertical-align:bottom;\">Item Descr.</td>
									<td style=\"width:90px;text-align:right;vertical-align:bottom;\">".($this->current_invoice['direct_bill'] ? "Ext Sell" : "Item Sell")."</td>
									<td style=\"width:100px;text-align:right;vertical-align:bottom;padding-right:5px;\">".($this->current_invoice['direct_bill'] ? "Ext Comm" : "Ext Sell")."</td>
								</tr>";
                                for ($i = 0; $i < $lines->total; $i++) {
                                    if (($lines->line_info[$i]['status'] == 1 && !$lines->line_info[$i]['invoice_hash']) && ((!$direct_bill && !$lines->line_info[$i]['direct_bill_vendor']) || ($direct_bill && in_array($lines->line_info[$i]['vendor_hash'], $direct_bill_vendor))))
                                        $line_info[] =& $lines->line_info[$i];

                                }
								for ($i = 0; $i < count($line_info); $i++) {
									$line_exists = true;
									if ($line_info[$i]['group_hash'] && $line_info[$i]['group_hash'] != $line_info[$i-1]['group_hash']) {
										$tbl .= "
										<tr style=\"background-color:#d6d6d6;\">
											<td colspan=\"5\" style=\"font-weight:bold;padding-top:15px;\">".nl2br($line_info[$i]['group_descr']).":</td>
										</tr>
										<tr>
											<td colspan=\"5\" style=\"background-color:#cccccc;\"></td>
										</tr>";
									}

									if ($line_info[$i]['group_hash'] && $line_info[$i]['active']) {
										$grp_sell += $line_info[$i]['sell'];
										$grp_ext_sell += ($line_info[$i]['sell'] * $line_info[$i]['qty']);
									}

									$tbl .= "
									<tr style=\"background-color:#efefef;\">
										<td></td>
										<td colspan=\"4\" style=\"padding:5px 0 0 10px;font-style:italic;font-weight:bold;\">
											".$line_info[$i]['vendor_name'].($line_info[$i]['product_name'] ? "
													: ".$line_info[$i]['product_name'] : NULL)."
										</td>
									</tr>
									<tr style=\"background-color:#efefef;\">
										<td style=\"vertical-align:bottom;cursor:auto;\" >
											".$this->form->checkbox("name=invoice_item{$this->popup_id}[]",
																	"value=".$line_info[$i]['item_hash'],
																	(!$line_info[$i]['vendor_hash'] || !$line_info[$i]['product_hash'] ? "disabled title=This item has no assigned ".(!$line_info[$i]['vendor_hash'] ? "vendor" : "product")." and cannot be added to this invoice!" : NULL))."
										</td>
										<td style=\"vertical-align:bottom;\">
											<div >".$line_info[$i]['item_no']."</div>
										</td>
										<td style=\"vertical-align:bottom;\" ".(strlen($line_info[$i]['item_descr']) > 70 ? "title=\"".$line_info[$i]['item_descr']."\"" : NULL)." $onClick>".(strlen($line_info[$i]['item_descr']) > 70 ?
											wordwrap(substr($line_info[$i]['item_descr'], 0, 65), 35, "<br />")."..." : (strlen($line_info[$i]['item_descr']) > 35 ?
												wordwrap($line_info[$i]['item_descr'], 35, "<br />") : nl2br($line_info[$i]['item_descr'])))."
										</td>
										<td style=\"vertical-align:bottom;\" class=\"num_field\">$".number_format(($this->current_invoice['direct_bill'] ? $line_info[$i]['ext_sell'] : $line_info[$i]['sell']), 2)."</td>
										<td style=\"vertical-align:bottom;padding-right:5px;\" class=\"num_field\">$".number_format(($this->current_invoice['direct_bill'] ? ($line_info[$i]['ext_sell'] - $line_info[$i]['ext_cost']) : $line_info[$i]['ext_sell']), 2)."</td>
									</tr>
									<tr>
										<td style=\"background-color:#cccccc;\" colspan=\"5\"></td>
									</tr>";

									if ($line_info[$i]['group_hash'] && (!$line_info[$i+1]['group_hash'] || $line_info[$i+1]['group_hash'] != $line_info[$i]['group_hash'])) {
										$tbl .= "
										<tr style=\"background-color:#d6d6d6\">
											<td></td>
											<td colspan=\"4\" style=\"font-weight:bold;padding-top:15px;text-align:right;\">End of Group: ".$line_info[$i]['group_descr']."</td>
										</tr>";

										unset($grp_sell, $grp_ext_sell);
									}
								}
								if (!$line_exists) {
									$tbl .= "
									<tr style=\"background-color:#ffffff\">
										<td></td>
										<td colspan=\"4\" style=\"padding-top:15px;\">".($this->ajax_vars['punch'] ?
											"No punchlist items have been ordered. You must cut POs for punch items before invoicing." : "
											All line items available for invoicing have already been invoiced! Make sure any line items you
											may be trying to invoice have been ordered first!")."
										</td>
									</tr>";
								}
							$tbl .= "
							</table>
						</td>
					</tr>
				</table>";
			}
			$tbl .= "
			<div style=\"padding-top:10px;padding-left:25px;\">
				".$this->form->button("value=Save & Update", "onClick=submit_form(\$('item_placeholder').form, 'customer_invoice', 'exec_post', 'refresh_form', 'method=edit_line');")."
			</div>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function new_deposit() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['proposal_hash'] && $this->ajax_vars['invoice_hash'])
			$unapplied = true;
		else {
			if (!$this->proposals && !$this->proposals->proposal_hash) {
				$proposal_hash = $this->ajax_vars['proposal_hash'];
				if (!$proposal_hash)
					return $this->__trigger_error("Unable to determine valid proposal hash for new deposit. Please try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 1);

				$this->proposals = new proposals($this->current_hash);
				$this->proposals->fetch_master_record($proposal_hash);
			}
		}
		if ($invoice_hash = $this->ajax_vars['invoice_hash'])
			$this->fetch_invoice_record($invoice_hash);
		else {
			$customers = new customers($this->current_hash);
			if ($unapplied = $customers->fetch_unapplied_deposits($this->proposals->current_proposal['customer_hash']))
				$rcv_from_un = true;

            $customer_hash = $this->proposals->current_proposal['customer_hash'];
		}
		$this->content['popup_controls']['popup_id'] = $this->popup_id;
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($this->invoice_hash ? "View & Edit Customer Deposit" : "Receive Customer Deposit");

		//Get the checking accounts
		$result = $this->db->query("SELECT `account_hash` , `account_no` , `account_name`
									FROM `accounts`
									WHERE `account_type` = 'CA' AND `checking` = 1
									ORDER BY `account_no` , `account_name` ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			$checking_account_hash[] = $row['account_hash'];
			$checking_account_name[] = ($row['account_no'] ? $row['account_no']." : " : NULL).$row['account_name'];
		}
		if (defined('DEFAULT_CASH_ACCT')) {
			if (!$checking_account_hash || !in_array(DEFAULT_CASH_ACCT, $checking_account_hash)) {
				$checking_account_hash[] = DEFAULT_CASH_ACCT;
				$checking_account_name[] = ($accounts->current_account['account_no'] ? $accounts->current_account['account_no']." : " : NULL).$accounts->current_account['account_name'];
			}
		}

		if (defined('MULTI_CURRENCY') && $default_currency = $this->fetch_default_currency($customer_hash)) {
		    $sys = new system_config();

		    if ($sys->home_currency['code'] != $default_currency) {
		        $sys->fetch_currency($default_currency);
		        $customer_currency = $sys->currency_id;
		    }
		}

		$tbl = $this->form->form_tag().
		$this->form->hidden(array("proposal_hash" => $this->proposals->proposal_hash,
		                          "invoice_hash" => $this->invoice_hash,
		                          "payment_hash" => $this->current_invoice['payments'][0]['payment_hash'],
		                          "popup_id" => $this->popup_id,
		                          "trans_type" => 'D'))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"padding:0;\">
						 <div style=\"background-color:#ffffff;height:100%;\">
							<div style=\"margin:15px 35px;\">
								<h3 style=\"margin-bottom:5px;color:#00477f;\">".($this->invoice_hash ?
									($unapplied ?
										"View &amp; Edit Unapplied Deposit" : "View &amp; Edit Proposal Deposit: {$this->proposals->current_proposal['proposal_no']}").(!$unapplied && $this->current_invoice['balance'] == $this->current_invoice['amount'] ? "
										<div style=\"margin-top:5px;margin-left:25px;font-weight:normal;font-size:55%;\">
											<div id=\"move_cntrl{$this->popup_id}\">
												<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"toggle_display('move_deposit{$this->popup_id}', 'block');toggle_display('move_cntrl{$this->popup_id}', 'none');\">Move deposit to another proposal</a>
											</div>
											<div id=\"move_deposit{$this->popup_id}\" style=\"display:none;\">
												<span id=\"err_move{$this->popup_id}\">Move To Proposal No:</span> ".$this->form->text_box("name=move_to", "value=".(defined('PROPOSAL_SEED') ? PROPOSAL_SEED : NULL), "size=10")."
												&nbsp;".
												$this->form->button("value=Go", "id=move_deposit_btn", "onClick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'method=move_deposit');this.disabled=1;")."
												&nbsp;".
												$this->form->button("value=Cancel", "onClick=toggle_display('move_deposit{$this->popup_id}', 'none');toggle_display('move_cntrl{$this->popup_id}', 'block');")."
											</div>
										</div>" : NULL) : "Receive a Deposit For Proposal ".$this->proposals->current_proposal['proposal_no'])."
								</h3>
								<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:90%;\">
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;padding-top:10px;\">Customer:</td>
										<td style=\"background-color:#ffffff;padding-top:10px;\">".($this->current_invoice['customer_name'] ? $this->current_invoice['customer_name'] : $this->proposals->current_proposal['customer'])."</td>
									</tr>" .
									( ( defined('MULTI_CURRENCY') && $customer_currency ) || ( $this->invoice_hash && $this->current_invoice['payments'][0]['currency'] ) ?
	                                    "<tr>
	                                        <td style=\"background-color:#efefef;text-align:right;width:125px;padding-top:10px;font-weight:bold;\">Currency:</td>
	                                        <td style=\"background-color:#ffffff;padding-top:10px;\" >" .
											( $this->invoice_hash ?
	                                            $this->current_invoice['payments'][0]['currency'] : $this->form->select("currency",
					                                                                                                     array($customer_currency, $sys->home_currency['code']),
					                                                                                                     $customer_currency,
					                                                                                                     array($customer_currency, $sys->home_currency['code']),
					                                                                                                     "blank=1")
	                                        ) . "
	                                        </td>
		                                </tr>" .
	                                    ( $this->invoice_hash && $this->current_invoice['payments'][0]['currency'] != $sys->home_currency['code'] ?
			                                "<tr>
			                                    <td style=\"background-color:#efefef;text-align:right;width:125px;padding-top:10px;font-weight:bold;\">Rate:</td>
			                                    <td style=\"background-color:#ffffff;padding-top:10px;\" >" . rtrim( trim($this->current_invoice['payments'][0]['exchange_rate'], '0'), '.') . "%</td>
			                                </tr>" : NULL
	                                    ) : NULL
	                                )."
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\" id=\"err1{$this->popup_id}\">Check No:</td>
										<td style=\"background-color:#ffffff;\">
											<div id=\"toggle_receipt_new{$this->popup_id}\" style=\"display:block;\">" .
											( $rcv_from_un ?
												"<div style=\"float:right;padding-right:15px;\"><span id=\"receive_from_holder{$this->popup_id}\"></span>[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'receive_from_unapplied', 'popup_id={$this->popup_id}', 'type=D', 'customer_hash={$this->proposals->current_proposal['customer_hash']}');\" class=\"link_standard\">Receive From Unapplied Receipts</a></small>]</div>" : NULL
											) .
											$this->form->text_box(
    											"name=check_no",
    											"value={$this->current_invoice['payments'][0]['check_no']}",
    											"size=10"
											) . "
											</div>
											<div id=\"toggle_receipt_unapplied{$this->popup_id}\" style=\"display:none;\"></div>
										</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\" id=\"err4{$this->popup_id}\">Receipt Date:</td>
										<td style=\"background-color:#ffffff;\" id=\"receipt_date_holder{$this->popup_id}\">".($this->invoice_hash ?
                                            date(DATE_FORMAT, strtotime($this->current_invoice['payments'][0]['receipt_date'])).$this->form->hidden(array('receipt_date' => $this->current_invoice['payments'][0]['receipt_date'])) : NULL)."
										</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\" id=\"err3{$this->popup_id}\">Amount:</td>
										<td style=\"background-color:#ffffff;\">
    										".$this->form->text_box("name=amount",
                                                                    "value=".number_format(($this->current_invoice['currency'] && $this->current_invoice['exchange_rate'] ? currency_exchange($this->current_invoice['amount'], $this->current_invoice['exchange_rate']) : $this->current_invoice['amount']), 2),
                            										"size=10",
                            										"style=text-align:right;",
                            										"onFocus=select();",
                            										"onBlur=this.value=formatCurrency(this.value);")."
                            			</td>
									</tr>".(!$this->current_invoice['payments'][0]['applied_from'] ? "
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\" id=\"err3{$this->popup_id}\">Account:</td>
										<td style=\"background-color:#ffffff;\">".($this->invoice_hash && $this->current_invoice['payments'][0]['account_name'] ?
											$this->current_invoice['payments'][0]['account_name'].$this->form->hidden(array('payment_account_hash' => $this->current_invoice['payments'][0]['account_hash'])) : (!$this->invoice_hash && $checking_account_hash ? "
												&nbsp;&nbsp;".
												$this->form->select("payment_account_hash",
																	$checking_account_name,
																	(defined('DEFAULT_CASH_ACCT') ? DEFAULT_CASH_ACCT : ''),
																	$checking_account_hash,
																	"blank=1") : "<img src=\"images/alert.gif\" />&nbsp;&nbsp;".($this->invoice_hash ?
																	   "This receipt does not indicate which account it was received into." : "You have no checking accounts defined.")))."
										</td>
									</tr>" : NULL)."
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;padding-top:10px\">Comments:</td>
										<td style=\"background-color:#ffffff;\">".$this->form->text_area("name=comments", "value=".$this->current_invoice['payments'][0]['comments'], "rows=4", "cols=40")."</td>
									</tr>
								</table>
								<div style=\"padding-top:20px;\">".((!$this->invoice_hash || bccomp($this->current_invoice['amount'], $this->current_invoice['balance'], 2) == 0) && !$this->current_invoice['deleted'] ?
									$this->form->button("value=Save",
                    									"id=rcv_dep",
                    									"onClick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_invoice');this.disabled=1").($this->invoice_hash ? "
										&nbsp;".
										$this->form->button("value=Delete",
										                    "id=rcv_dep_rm",
										                    "onClick=if(confirm('Are you sure you want to delete this deposit? This action CANNOT be undone!')){submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_invoice', 'delete=true');this.disabled=1}").($this->current_invoice['payments'][0]['applied_from'] ?
                                            "<span style=\"font-style:italic;margin-left:10px;\">This deposit was applied from a previously received unapplied receipt.</span>" : NULL) : NULL) : "<i>This deposit has already been applied and cannot be changed.</i>")."
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		if (!$this->invoice_hash)
            $jscript[] = "setTimeout('DateInput(\'receipt_date\', true, \'YYYY-MM-DD\', \'".($this->current_invoice['payments'][0]['receipt_date'] ? $this->current_invoice['payments'][0]['receipt_date'] : date("Y-m-d"))."\', 1, \'receipt_date_holder{$this->popup_id}\')', 25);";

		$this->content['jscript'] = $jscript;
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function doit_change_tax() {
		$punch = $_POST['punch'];
		$install_hash = $_POST['install_hash'];
		$tax_rules = $_POST['tax_rules'];
		$parent_popup_id = $_POST['parent_popup_id'];
		$deposit_used = $_POST['deposit_used'];
		$total_amt = $_POST['total_amt'];
		$z = $_POST['z'];
		$item = $_POST['item'];
		$notax = "false";
		if (!is_numeric($z))
			$z = 0;

		if (!$install_hash)
			return $this->__trigger_error("You have no installation address assigned to this proposal, therefore tax rules cannot be determined.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		$this->db->query("DELETE FROM `tax_collected`
						  WHERE `proposal_hash` = '{$this->proposal_hash}' AND `invoice_hash` = '' AND `punch` = ".($punch ? 1 : 0)."");

		if ($tax_rules = $_POST['tax_rules']) {
			//Get the product list
			$result = $this->db->query("SELECT vendor_products.product_hash
										FROM `line_items`
										LEFT JOIN vendor_products on vendor_products.product_hash = line_items.product_hash
										WHERE line_items.proposal_hash = '{$this->proposal_hash}' AND line_items.invoice_hash = '' AND line_items.punch = ".($punch ? 1 : 0)." AND vendor_products.product_taxable = 1
										GROUP BY vendor_products.product_hash");
			while ($row = $this->db->fetch_assoc($result))
				$product[] = $row['product_hash'];

			$sys = new system_config($this->current_hash);
			for($i = 0; $i < count($tax_rules); $i++) {
				if ($tax_rules[$i]) {
					$sys->fetch_tax_rule($tax_rules[$i]);
					if ($sys->current_tax['incomplete'])
						return $this->__trigger_error("The tax rule for ".$sys->current_tax['state_name'].($sys->current_tax['local'] ? ", ".$sys->current_tax['local'] : NULL)." has not been associated with a liability account. Please have your system administrator re configure the sales tax rule.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

					//For each of the products on our proposal, see if that local collects tax
					for ($j = 0; $j < count($product); $j++) {
						if (@in_array($product[$j], $sys->current_tax['product'])) {
							//Get all the lines under that product that haven't been invoiced yet
							$result = $this->db->query("SELECT `item_hash`
														FROM `line_items`
														WHERE `proposal_hash` = '{$this->proposal_hash}' AND `product_hash` = '".$product[$j]."' AND `invoice_hash` = '' AND `punch` = ".($punch ? 1 : 0)."");
							while ($row = $this->db->fetch_assoc($result))
								$this->db->query("INSERT INTO `tax_collected`
											 	  (`timestamp` , `proposal_hash` , `item_hash` , `tax_hash` , `rate` , `punch`)
											 	  VALUES(".time()." , '{$this->proposal_hash}' , '".$row['item_hash']."' , '".$sys->tax_hash."' , '".$sys->current_tax['rate']."' , ".($punch ? 1 : 0).")");
						}
					}
					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM `location_tax`
												WHERE `location_hash` = '$install_hash' AND `tax_hash` = '".$tax_rules[$i]."'");
					if (!$this->db->result($result))
						$this->db->query("INSERT INTO `location_tax`
										  (`location_hash` , `tax_hash`)
										  VALUES('$install_hash' , '".$tax_rules[$i]."')");
				}else{
					$this->db->query("DELETE FROM `tax_collected` WHERE `proposal_hash` = '{$this->proposal_hash}'");
					$this->db->query("DELETE FROM `location_tax` WHERE `location_hash` = '$install_hash'");
					$notax = "true";
				}
			}
		}
		$lines = new line_items($this->proposal_hash, $punch);
		if($notax == "true"){
			$total_tax = 0;
		}else{
			list($total_tax) = $lines->calc_tax($item[$z]);
		}

		$this->content['jscript'][] = "toggle_display('tax_tr_".$z.$parent_popup_id."', '".(bccomp($total_tax, 0, 2) == 1 ? 'block' : 'none')."');";
		$this->content['html']['total_tax_'.$z.$parent_popup_id] = "$".number_format($total_tax, 2).$this->form->hidden(array('total_tax['.$z.']' => $total_tax));
		$this->content['html']['invoice_subtotal_'.$z.$parent_popup_id] = "$".number_format(bcadd($total_tax, $total_amt[$z], 2), 2);

		$this->content['html']['total_amount_'.$z.$parent_popup_id] = "$".number_format(bcsub(bcadd($total_tax, $total_amt[$z], 2), preg_replace('/[^-.0-9]/', "", $deposit_used[$z]), 2), 2);
		$this->content['action'] = "close";
		$this->content['page_feedback'] = "Tax settings have been saved.";
		return;
	}

	function change_tax() {
		$punch = ($_POST['punch'] ? 1 : 0);
		$proposal_hash = $_POST['proposal_hash'];
		$install_state = $_POST['install_state'];
		$install_country = $_POST['install_country'];
		$total_amt = $_POST['total_amt'];
		$install_hash = $_POST['install_hash'];
		$item = $_POST['item'];
		$parent_popup_id = $_POST['parent_popup_id'];
		$z = $_POST['z'];
		$deposit_used = $_POST['deposit_used'];
		$lines = new line_items($proposal_hash);
		$proposals = new proposals($this->current_hash);

		$this->content['popup_controls']['popup_id'] = $this->popup_id;
		$this->content['popup_controls']['popup_title'] = "Change Tax Rules";

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("proposal_hash" => $proposal_hash, "parent_popup_id" => $parent_popup_id, "z" => $z, "popup_id" => $this->popup_id, "punch" => $punch))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"padding:15px 25px;;background-color:#ffffff;\">
						<div style=\"font-weight:bold;margin-bottom:10px;\">
							Change Proposal Tax
						</div>";
						for ($i = 0; $i < count($item); $i++) {
							for ($j = 0; $j < count($item[$i]); $j++)
								$tbl .= $this->form->hidden(array('item['.$i.']['.$j.']' => $item[$i][$j]));
						}
						for ($i = 0; $i < count($total_amt); $i++)
							$tbl .= $this->form->hidden(array('total_amt[]' => $total_amt[$i]));
						for ($i = 0; $i < count($deposit_used); $i++)
							$tbl .= $this->form->hidden(array('deposit_used[]' => $deposit_used[$i]));
						//Get the product list
						$product = array();
						$result = $this->db->query("SELECT vendor_products.product_hash
													FROM `line_items`
													LEFT JOIN vendor_products on vendor_products.product_hash = line_items.product_hash
													WHERE line_items.proposal_hash = '".$proposal_hash."' AND line_items.invoice_hash = '' AND line_items.punch = ".($punch ? 1 : 0)." AND line_items.active = 1 AND vendor_products.product_taxable = 1
													GROUP BY vendor_products.product_hash");
						while ($row = $this->db->fetch_assoc($result))
							$product[] = $row['product_hash'];

						$sys = new system_config($this->current_hash);
						$sys->fetch_tax_tables($install_country, $install_state, $product);

						$tax_hash[] = "";
						$tax_name[] = "Don't apply any tax rules";
						while (list($state, $info) = each($sys->tax_tables)) {
							$tax_hash[] = $info['state']['tax_hash'];
							$tax_name[] = $info['state']['state_name']." (".($info['state']['rate'] * 100)."%)";
							if ($info['local']) {
								for ($i = 0; $i < count($info['local']); $i++) {
									$tax_hash[] = $info['local'][$i]['tax_hash'];
									$tax_name[] = "&nbsp;&nbsp;&nbsp;&nbsp;--&nbsp;".$info['local'][$i]['local']." (".($info['local'][$i]['rate'] * 100)."%)";
								}
							}
						}
						if ($tax_hash)
							$tax_tbl = $this->form->hidden(array('install_hash' => $install_hash)).$this->form->select("tax_rules[]", $tax_name, $proposals->fetch_location_tax($install_hash), $tax_hash, "blank=1", "multiple", "size=".(count($tax_hash) > 5 ? "6" : count($tax_hash)), "style=width:350px;", "onClick=if(this.options[0].selected){for(var i=1;i<this.options.length;i++){this.options[i].selected=0}}");

						if ($tax_tbl)
							$tbl .= "
							<div style=\"margin-bottom:15px;\">
								There are tax rules established within the state of ".$sys->tax_tables[$install_state]['state']['state_name'].".
								<br />Please determine which tax rules should be applied to this proposal. Use the CNTRL key to select multiple.
								<div style=\"margin-bottom:5px;margin-top:5px;\">".$tax_tbl."</div>
							</div>".
							$this->form->button("value=Save Tax", "onClick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_change_tax');");
						else
							$tbl .= "
							<div style=\"margin-bottom:15px;\">
								There are tax rules that apply to any of the products or services found within your proposal.
							</div>";

					$tbl .= "
					</td>
				</tr>
			</table>
		</div>".$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}

	/* 2009-05-01 - Function removed in favor of client side javascript function
	// function deposit_total() {
	//	$z = $_POST['z'];
	//	$total_amt = $_POST['total_amt'][$z];
	//	$total_tax = $_POST['total_tax'][$z];
	//	$deposit_used = $_POST['deposit_used'][$z];
	//
	//	$this->content['html']['total_amount_'.$z.$this->popup_id] = "$".number_format(($total_amt + $total_tax) - $deposit_used, 2);
	//}
    */

	function new_invoice($local=NULL) {
		$punch = ($_POST['punch'] ? $_POST['punch'] : $this->ajax_vars['punch']);
		if ($_POST['step'] == 1) {
			for ($i = 0; $i < count($_POST['invoice_item']); $i++) {
				if ($_POST['invoice_item'][$i])
					$invoice_item[] = $_POST['invoice_item'][$i];
			}
			if (!count($invoice_item)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "You selected no line items! Please select at least one line item to invoice and try again.";
				unset($_POST['step']);
			}
			$proposal_hash = $_POST['proposal_hash'];
			$this->popup_id = $_POST['popup_id'];
			$limit = $_POST['limit'];
		} else {
			if ($this->ajax_vars['popup_id'])
				$this->popup_id = $this->ajax_vars['popup_id'];

			$proposal_hash = $this->ajax_vars['proposal_hash'];
			$limit = $this->ajax_vars['limit'];
		}

		if (!$this->proposals && !$this->proposals->proposal_hash) {
			if (!$proposal_hash)
				return;

			$this->proposals = new proposals($this->current_hash);
			$this->proposals->fetch_master_record($proposal_hash);
		}
		$lines = new line_items($this->proposals->proposal_hash, $punch);
		if ($_POST['step'] != 1) {
			if ($punch == 1) {
				$pm = new pm_module($this->proposals->proposal_hash);
				$pm->fetch_punch_items();

				for ($i = 0; $i < $pm->total_punch; $i++) {
					if ($pm->punch_items[$i]['status'] == 1) {
						$good = true;
						$invoice_item[] = $pm->punch_items[$i]['item_hash'];
						$invoice_item_group[] = $pm->punch_items[$i]['group_hash'];
					}
				}
				$total = count($invoice_item);
			} else {
				$lines->fetch_line_items(0, $lines->total);
				$total = $lines->total;
				$a = true;
				for ($i = 0; $i < $total; $i++) {
                    if ($lines->line_info[$i]['invoice_hash'] || !$lines->line_info[$i]['status'])
                        unset($lines->line_info[$i], $a);
				}
                if (!$a)
                    $lines->line_info = array_values($lines->line_info);

				$r = $this->db->query("SELECT COUNT(*) AS Total
									   FROM `line_items`
									   WHERE `proposal_hash` = '{$this->proposals->proposal_hash}' AND `po_hash` != '' AND `punch` = 0");
				$good = $this->db->result($r, 0, 'Total');
			}
		}
		$this->content['popup_controls']['popup_id'] = $this->popup_id;
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($invoice_hash ? "View / Edit Customer Invoice" : "Create a New Customer Invoice");
		$step = ($this->ajax_vars['step'] ? $this->ajax_vars['step'] : $_POST['step']);

		$tbl = $this->form->form_tag().
		$this->form->hidden(array("punch" => $punch, "proposal_hash" => $this->proposals->proposal_hash, "invoice_hash" => $this->invoice_hash, "popup_id" => $this->popup_id, "step" => $step, 'trans_type' => 'I'))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<div id=\"po_content{$this->popup_id}\">";
			if (!$step && !$invoice_hash) {
				$tbl .= "
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
					<tr>
						<td style=\"padding:0;\">
							 <div style=\"background-color:#ffffff;height:100%;\">
								<div style=\"margin:15px 35px;\">
									<h3 style=\"margin-bottom:5px;color:#00477f;\">Select Line Items</h3>
									<div style=\"margin-left:25px;margin-top:8px;margin-bottom:10px;\">
										".$this->form->button("value=Next -->", "onClick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=new_invoice', 'step=1');")."
									</div>
									<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
										<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
											<p id=\"feedback_message\"></p>
									</div>
									 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" class=\"table_list\" style=\"font-size:9pt;\">
										<tr class=\"thead\" style=\"font-weight:bold;\">
											<td style=\"width:10px;\">
											    ".$this->form->checkbox("name=check_all",
											                            "value=1",
											                            "onClick=checkall(document.getElementsByName('invoice_item[]'), this.checked);")."
											</td>
											<td style=\"width:200px;vertical-align:bottom;\">Item No.</td>
											<td style=\"width:300px;vertical-align:bottom;\">Item Descr.</td>
											<td style=\"width:90px;text-align:right;vertical-align:bottom;\">Item Sell</td>
											<td style=\"width:100px;text-align:right;vertical-align:bottom;padding-right:5px;\">Ext Sell</td>
										</tr>";

										if ($this->proposals->current_proposal['install_addr_hash']) {
											$group_colors = array("group_row1", "group_row2");
											$group_header = array("#D6D6D6", "#C1DAF0");
											for ($i = 0; $i < $total; $i++) {
												if ($punch == 1) {
													$lines->fetch_line_item_record($invoice_item[$i]);
													$lines->line_info[$i] =& $lines->current_line;
													$lines->line_info[$i]['vendor_name'] = $lines->current_line['item_vendor'];
												}

												if ($lines->line_info[$i]['status'] != 1 || $lines->line_info[$i]['invoice_hash'])
													unset($lines->line_info[$i]['group_hash']);
												if ($lines->line_info[$i]['status'] == 1 && !$lines->line_info[$i]['invoice_hash']) {
													$line_exists = true;
													if ($lines->line_info[$i]['group_hash'] && $lines->line_info[$i]['group_hash'] != $lines->line_info[$i-1]['group_hash']) {
														$num_groups++;
														$tbl .= "
														<tr style=\"background-color:".$group_header[($num_groups % 2)]."\">
															<td class=\"smallfont\" colspan=\"5\" style=\"font-weight:bold;padding-top:15px;padding-left:45px;\">
															    ".$this->form->checkbox("onClick=checkall($$('input[igroup=".$lines->line_info[$i]['group_hash']."]'), this.checked)")."&nbsp;
															    Group: ".nl2br($lines->line_info[$i]['group_descr'])."
															</td>
														</tr>
														<tr>
															<td colspan=\"5\" style=\"background-color:#cccccc;\"></td>
														</tr>";
													}

													if ($lines->line_info[$i]['group_hash']) {
														$grp_sell += $lines->line_info[$i]['sell'];
														$grp_ext_sell += ($lines->line_info[$i]['sell'] * $lines->line_info[$i]['qty']);
													}

													$tbl .= "
													<tr ".($lines->line_info[$i]['group_hash'] ? "class=\"".$group_colors[($num_groups % 2)]."\"" : NULL).">
														<td>".(!$lines->line_info[$i]['ack_no'] ? "
															<div style=\"padding-bottom:5px;\"><img src=\"images/alert.gif\" title=\"This line has not been acknowledged!\" /></div>" : NULL)."
														</td>
														<td class=\"smallfont\" colspan=\"4\" style=\"padding:5px 0 0 10px;\">".(!$punch ? "
															Line: ".$lines->line_info[$i]['line_no'] : NULL)."
															<span style=\"padding-left:5px;font-style:italic;\">
																".($lines->line_info[$i]['vendor_name'] ?
                                                                    $lines->line_info[$i]['vendor_name'] : "<img src=\"images/alert.gif\" title=\"This line has no assigned vendor!\" />").
                                                                ($lines->line_info[$i]['product_name'] ? "
																	: ".$lines->line_info[$i]['product_name'] : NULL)."
															</span>
														</td>
													</tr>
													<tr ".($lines->line_info[$i]['group_hash'] ? "class=\"".$group_colors[($num_groups % 2)]."\"" : NULL).">
														<td class=\"smallfont\" style=\"vertical-align:bottom;cursor:auto;\" >".
															$this->form->checkbox("name=invoice_item[]",
															                      "value=".$lines->line_info[$i]['item_hash'],
															                      ($lines->line_info[$i]['group_hash'] ? "igroup=".$lines->line_info[$i]['group_hash'] : NULL),
                                                                                  (!$lines->line_info[$i]['vendor_hash'] || !$lines->line_info[$i]['product_hash'] ? "disabled title=This item has no assigned ".(!$lines->line_info[$i]['vendor_hash'] ? "vendor" : "product")." and cannot be invoiced. Please correct any errors before generating invoices." : NULL))."
														</td>
														<td class=\"smallfont\" style=\"vertical-align:bottom;\">
															<div >".$lines->line_info[$i]['item_no']."</div>
														</td>
														<td class=\"smallfont\" style=\"vertical-align:bottom;\" ".(strlen($lines->line_info[$i]['item_descr']) > 70 ? "title=\"".$lines->line_info[$i]['item_descr']."\"" : NULL)." $onClick>".(strlen($lines->current_line['item_descr']) > 70 ?
															wordwrap(substr($lines->line_info[$i]['item_descr'], 0, 65), 35, "<br />", true)."..." : (strlen($lines->line_info[$i]['item_descr']) > 35 ?
																wordwrap($lines->line_info[$i]['item_descr'], 35, "<br />", true) : nl2br($lines->line_info[$i]['item_descr'])))."
														</td>
														<td class=\"smallfont\" style=\"vertical-align:bottom;text-align:right;\">$".number_format($lines->line_info[$i]['sell'], 2)."</td>
														<td class=\"smallfont\" style=\"vertical-align:bottom;padding-right:5px;text-align:right;\">$".number_format($lines->line_info[$i]['ext_sell'], 2)."</td>
													</tr>
													<tr>
														<td style=\"background-color:#cccccc;\" colspan=\"5\"></td>
													</tr>";

													if ($lines->line_info[$i]['group_hash'] && (!$lines->line_info[$i+1]['group_hash'] || $lines->line_info[$i+1]['group_hash'] != $lines->line_info[$i]['group_hash'])) {
														$tbl .= "
														<tr style=\"background-color:".$group_header[($num_groups % 2)]."\">
															<td></td>
															<td class=\"smallfont\" colspan=\"4\" style=\"font-weight:bold;padding-top:15px;text-align:right;padding-right:10px;\">Group ".$lines->current_line['group_descr']." Total: $".number_format($grp_ext_sell, 2)."</td>
														</tr>";

														unset($grp_sell, $grp_ext_sell);
													}
												}
											}
											if (!$line_exists)
												$tbl .= "
												<tr style=\"background-color:#ffffff\">
													<td></td>
													<td class=\"smallfont\" colspan=\"4\" style=\"padding-top:15px;\">".($punch ?
														"No punchlist items have been ordered and therefore cannot be invoiced." : ($good ? "
																All line items available for order have already been invoiced! Make sure any line items you
																may be trying to invoiced have already been purchased." : "None of your  line items have been ordered! You
																must order lines by creating purchase orders before you create invoices."))."
													</td>
												</tr>";
										} else
											$tbl .= "
											<tr style=\"background-color:#ffffff\">
												<td></td>
												<td class=\"smallfont\" colspan=\"4\" style=\"padding-top:15px;\">
													<div style=\"font-weight:bold;margin-bottom:5px;\">
														<img src=\"images/alert.gif\" />
														&nbsp;
														Your proposal has no installation location!
														<div style=\"margin-top:5px;font-weight:normal\">
															Before creating your invoice, please identify the installation address for this proposal.
															The installation address is important as it is used to properly calculate sales tax. If your proposal has no installation,
															you may use the shipping address as the installation address.
														</div>
													</div>
												</td>
											</tr>";


							$tbl .= "
									</table>
								</div>
							</div>
						</td>
					</tr>
				</table>";
			} else {
				$this->lines = new line_items($this->proposals->proposal_hash, $punch);
				$deposits = $this->fetch_customer_deposits($this->proposals->proposal_hash);

				if ($this->proposals->current_proposal['order_type'] == 'D') {
					$direct = $this->lines->fetch_direct_bill_info($invoice_item);
					unset($invoice_item);
					$k = 0;

					while (list($vendor_hash, $invoice_info) = each($direct)) {
						$invoice_item[$k] = array();
						for ($i = 0; $i < count($invoice_info['items']); $i++) {
							$invoice_item[$k][] = $invoice_info['items'][$i];
							$this->lines->fetch_line_item_record($invoice_info['items'][$i]);
							$item_vendors[$k][$this->lines->current_line['vendor_hash']][] = array("vendor"		=>	$this->lines->current_line['item_vendor'],
																								   "item_hash"	=>	$invoice_info['items'][$i],
																								   "ext_sell"	=>	$this->lines->current_line['ext_sell'],
																								   "commission"	=>	$this->lines->current_line['ext_sell'] - ($this->lines->current_line['work_order_cost_ext'] ? $this->lines->current_line['work_order_cost_ext'] : $this->lines->current_line['ext_cost']));
						}
						//list($tax_due) = $this->lines->calc_tax($invoice_item[$k]);
						$direct_bill_info[$k] = $invoice_info;
						//$total_tax[$k] = $tax_due;

						$k++;
					}

					$vendors = new vendors($this->current_hash);
				} else {
					list($tax_due, $tax_rules, $tax_local, $indiv_tax) = $this->lines->calc_tax($invoice_item);

					$total_tax = array($tax_due);
					$invoice_item = array($invoice_item);
					if ($this->lines->tax_country == 'CA' && $indiv_tax) {
						$k = 0;
						$count = count($tax_local);
                        while (list($tax_hash, $local_name) = each($tax_local)) {
                            $k++;
                            $tax_tbl .= "
                            <div ".($k < $count ? "style=\"margin-bottom:5px;\"" : NULL).">
                                $".number_format($tax_rules[$tax_hash], 2)."
                            </div>";
                            $tax_msg .= "
                            <div style=\"".($k < $count ? "margin-bottom:5px;" : NULL)."font-size:92%;\">
                                Tax : ".strtoupper($local_name)."
                            </div>";
                        }
					}
				}
				$customers = new customers($this->current_hash);
				global $states, $stateNames;

				//See if any of the lines are eligible for tax
				$result = $this->db->query("SELECT vendor_products.product_hash
											FROM `line_items`
											LEFT JOIN vendor_products on vendor_products.product_hash = line_items.product_hash
											WHERE line_items.proposal_hash = '{$this->proposal_hash}'
											AND line_items.invoice_hash = '' AND line_items.active = 1
											AND line_items.punch = 0 AND line_items.status = 1
											AND vendor_products.product_taxable = 1
											GROUP BY vendor_products.product_hash");
				while ($row = $this->db->fetch_assoc($result))
					$product[] = $row['product_hash'];

				//Make sure that none of the tax COA's have been removed before cutting this invoice
				if ($product) {
					$r = $this->db->query("SELECT COUNT(*) AS Total
					                       FROM `tax_collected`
                                           LEFT JOIN tax_table ON tax_table.tax_hash = tax_collected.tax_hash
					                       LEFT JOIN accounts ON accounts.account_hash = tax_table.account_hash
									       WHERE tax_collected.proposal_hash = '{$this->proposal_hash}' AND ISNULL(accounts.account_hash)");
				    if ($this->db->result($r) > 0)
				        $tax_acct_flag = true;
				}

				$jscript[] = "
				purgeinvoice = function() {
					var invoice = arguments[0];
					if (\$('invoice_' + invoice))
						\$('invoice_' + invoice).remove();

					return;
				}";

				$inner_tbl .= "
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
					<tr>
						<td style=\"padding:0;\">
							 <div style=\"background-color:#ffffff;height:100%;\">
								<div style=\"margin:15px 35px;\">
									<h3 style=\"margin-bottom:5px;color:#00477f;\">
										Review Your Invoice".(count($direct) > 1 ? "s" : NULL)."
									</h3>
									<div style=\"margin-bottom:10px;\">
										<div style=\"float:right;\">".($tax_acct_flag ? "
										    <div style=\"margin-top:10px;margin-bottom:10px;float:right;width:350px;\">
                                                <img src=\"images/alert.gif\" title=\"Tax account missing!\" />&nbsp;
                                                One or more sales tax chart of accounts is not established or missing. Please contact your system administrator.
                                             </div>" :
											$this->form->button("value=Create Invoice",
											                      "id=invoice_btn",
				                                                  ($tax_acct_flag ? "disabled title=One or more sales tax chart of accounts is not established. Please contact your system administrator." : NULL),
											                      "onClick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_invoice');this.disabled=1"))."
										</div>
										<small style=\"margin-left:20px;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'new_invoice', 'proposal_hash={$this->proposals->proposal_hash}', 'popup_id=line_item', 'punch=$punch');\"><-- Back</a></small>
									</div>
									<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
										<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
											<p id=\"feedback_message\"></p>
									</div>
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
										<tr>
											<td style=\"vertical-align:middle;\" class=\"smallfont\">
												Your invoice preview is shown below. ".(count($direct) >= 1 ? "
												<div style=\"font-weight:bold;margin:10px 0px\">
													One or more vendors are listed as direct bill vendors, therefore the invoice preview below has been separated according to direct billing vendor.
													If any of your line items were listed as normal open markent business, a standard customer invoice has also been created.
													<div style=\"font-weight:normal;margin:8px 0px\">
    													<img src=\"images/alert.gif\" />&nbsp;
    													You may change the direct bill setup for you vendor(s) by <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"if(\$('item_obj_id[]')){var item_id=new Array();for(var i=0;i<document.getElementsByName('item_obj_id[]').length;i++){item_id.push(document.getElementsByName('item_obj_id[]')[i].value)}}agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'direct_bill_layout', 'invoice_proposal_hash={$this->proposals->proposal_hash}', 'parent_popup_id={$this->popup_id}', 'popup_id=direct_bill_win', 'item='+item_id.join('|'));\">clicking here</a>.
    												</div>
												</div>" : NULL)."
												Please review and make sure there are no errors. When you are
												ready to continue click 'Create Invoice'.
												<div style=\"margin-top:15px;width:100%;\">";

											$customers->fetch_master_record($this->proposals->current_proposal['customer_hash']);
											$customer_name = $this->proposals->current_proposal['customer'];
											if (defined('MULTI_CURRENCY') && $customers->current_customer['currency']) {
                                                $sys = new system_config();

                                                if ($sys->home_currency['code'] != $customers->current_customer['currency']) {
                                                	$sys->fetch_currency($customers->current_customer['currency']);
                                                    $customer_currency = $sys->current_currency;
                                                }
											}
											for ($z = 0; $z < count($invoice_item); $z++) {
												if ($print_pref = fetch_user_data("invoice_print"))
													$print_pref = __unserialize(stripslashes($print_pref));

												$total_amt = 0;
												$total_cost = 0;
												$item_detail_list = '';
												$total_dep = $total_amt = 0;
												unset($invoice_to_out, $invoice_to_in);
												$count = count($invoice_item[$z]) - 1;
												for ($i = 0; $i < count($invoice_item[$z]); $i++) {
													$this->lines->fetch_line_item_record($invoice_item[$z][$i]);
													if (!$direct) {
														$total_amt += _round($this->lines->current_line['ext_sell'], 2);
														if ($product && in_array($this->lines->current_line['product_hash'], $product) && !$tax_eligible)
															$tax_eligible = true;

													}
													$item_detail_list .= "
													<tr title=\"".($this->lines->current_line['item_no'] ? "Item No: ".htmlspecialchars($this->lines->current_line['item_no'])."\n" : NULL)."Descr: ".htmlspecialchars($this->lines->current_line['item_descr'])."\">
														<td style=\"font-size:94%;".($i < $count ? "border-bottom:1px solid #cccccc;" : NULL)."vertical-align:bottom;\">".$this->form->hidden(array("item[".$z."][]" => $this->lines->item_hash, 'item_obj_id[]' => $this->lines->current_line['obj_id'])).$this->lines->current_line['qty']."</td>
														<td style=\"font-size:94%;".($i < $count ? "border-bottom:1px solid #cccccc;" : NULL)."\">".(strlen($this->lines->current_line['item_descr']) > 20 ?
                                                            substr($this->lines->current_line['item_descr'], 0, 17)."..." : $this->lines->current_line['item_descr'])."
                                                        </td>
														<td style=\"font-size:94%;text-align:right;".($i < $count ? "border-bottom:1px solid #cccccc;vertical-align:bottom;" : NULL)."\">
															".($this->lines->current_line['ext_sell'] < 0 ?
	                                                            "($".number_format($this->lines->current_line['ext_sell'] * -1, 2).")" : "$".number_format($this->lines->current_line['ext_sell'], 2))."

	                                                    </td>".($direct ? "
														<td style=\"font-size:94%;text-align:right;".($i < $count ? "border-bottom:1px solid #cccccc;vertical-align:bottom;" : NULL)."\">
    														".($this->lines->current_line['ext_sell'] - $this->lines->current_line['ext_cost'] < 0 ?
                                                                "($".number_format(($this->lines->current_line['ext_sell'] - $this->lines->current_line['ext_cost']) * -1, 2).")" : "$".number_format($this->lines->current_line['ext_sell'] - $this->lines->current_line['ext_cost'], 2))."
        												</td>" : NULL)."
													</tr>";
												}
												if ($direct) {
													$total_amt_select = '';
													while (list($direct_item_vendor, $item_array) = each($item_vendors[$z])) {
														$vendor_total_sell = $vendor_commission = 0;
														for ($i = 0; $i < count($item_array); $i++) {
															$vendor_total_sell += $item_array[$i]['ext_sell'];
															$vendor_commission += $item_array[$i]['commission'];
														}

														$total_amt_select .= "
														<div style=\"margin-top:5px;text-align:left;\">".($item_vendors[$z] > 1 ? "
															<small>".$item_array[0]['vendor'].":</small>" : NULL)."
															<div style=\"margin-top:5px;\">" .
    															$this->form->select("direct_invoice_type[".$z."][".$direct_item_vendor."]",
															                          array(
    															                          "Total Sell: \$" . number_format($vendor_total_sell, 2),
    															                          "Commission: " . ( $vendor_commission < 0 ? "(\$" . number_format($vendor_commission * -1, 2) . ")" : "\$" . number_format($vendor_commission, 2))
															                          ),
															                          ( $print_pref['direct'][$direct_item_vendor] ? $print_pref['direct'][$direct_item_vendor] : 'C' ),
															                          array(
    															                          'S',
    															                          'C'
															                          ),
															                          "blank=1")."
                                                            </div>
														</div>";
														$total_amt_select .=
														$this->form->hidden(
														array(
														  'total_amt['.$z.']['.$direct_item_vendor.'][sell]'          => $vendor_total_sell,
														  'total_amt['.$z.']['.$direct_item_vendor.'][commission]'    => $vendor_commission
														));
													}

													if ( $direct_bill_info[$z]['type'] == 'O' ) {

														$invoice_to = stripslashes($this->proposals->current_proposal['customer']) .
														$this->form->hidden( array(
    														"invoice_to[$z]" => $this->proposals->current_proposal['customer_hash']
														) );

														$customers->fetch_master_record($this->proposals->current_proposal['customer_hash']);

														$mail_to_addr = stripslashes($customers->current_customer['customer_name']) . "\n" .
														( $customers->current_customer['street'] ?
    														stripslashes($customers->current_customer['street']) . "\n" : NULL
    													) .
    													stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";

    													if ( $customers->current_contact['contact_email'] )
    														$email_addr = $customers->current_contact['contact_email'];

    												    if ( $customers->current_customer['fax'] || $customers->current_customer['contact_fax'] )
    														$fax_addr = ( $customers->current_customer['fax'] ? $customers->current_customer['fax'] : $customers->current_contact['contact_fax'] );

													} else {

														$invoice_to = stripslashes($direct_bill_info[ $z ]['vendor']) .
														$this->form->hidden( array(
    														"invoice_to[$z]" => $direct_bill_info[$z]['vendor_hash']
														) );

														$vendors->fetch_master_record($direct_bill_info[$z]['vendor_hash']);

														$mail_to_addr = stripslashes($vendors->current_vendor['vendor_name']) . "\n" .
														( $vendors->current_vendor['street'] ?
    														stripslashes($vendors->current_vendor['street']) . "\n" : NULL
    													) .
    													stripslashes($vendors->current_vendor['city']) . ", {$vendors->current_vendor['state']} {$vendors->current_vendor['zip']}";

    													if ( $vendors->current_vendor['order_email'] )
    														$email_addr = $vendors->current_vendor['order_email'];

                                                        if ( $vendors->current_vendor['fax'] || $vendors->current_vendor['order_fax'] )
    														$fax_addr = ( $vendors->current_vendor['fax'] ? $vendors->current_vendor['fax'] : $vendors->current_vendor['order_fax'] );
													}

												} else {

													if ( $this->proposals->current_proposal['customer_contact'] )
														$customers->fetch_contact_record($this->proposals->current_proposal['customer_contact']);

													if ( $this->proposals->current_proposal['bill_to_hash'] ) {

														$customers->fetch_location_record($this->proposals->current_proposal['bill_to_hash']);
														$bill_to_detail = stripslashes($customers->current_location['location_name']) .
														$this->form->hidden( array(
    														'bill_to_hash' => $this->proposals->current_proposal['bill_to_hash']
														) );

														$mail_to_addr = stripslashes($customers->current_location['location_name']) . "\n" .
														( $customers->current_location['location_street'] ?
    														stripslashes($customers->current_location['location_street']) . "\n" : NULL
    													) .
    													stripslashes($customers->current_location['location_city']) . ", {$customers->current_location['location_state']} {$customers->current_location['location_zip']}";

													} else {

														$mail_to_addr = stripslashes($customers->current_customer['customer_name']) . "\n" .
														( $customers->current_customer['street'] ?
    														stripslashes($customers->current_customer['street']) . "\n" : NULL
    													) .
    													stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";
													}

													if ( $customers->current_contact['contact_email'] )
    													$email_addr = $customers->current_contact['contact_email'];

    											    if ( $customers->current_customer['fax'] || $customers->current_contact['contact_fax'] )
    													$fax_addr = ( $customers->current_customer['fax'] ? $customers->current_customer['fax'] : $customers->current_contact['contact_fax'] );
												}

												if ( $punch ) {

													if ((!$direct && $total_amt == 0 && !is_null($total_amt)) || ($direct && $vendor_total_sell == 0))
														$invoice_to = stripslashes($customer_name);
													elseif ((!$direct && $total_amt > 0 && !is_null($total_amt)) || ($direct && $vendor_total_sell > 0)) {
														$invoice_to_out[] = stripslashes($customer_name);
														$invoice_to_in[] = $this->proposals->current_proposal['customer_hash'];
														if ($this->proposals->current_proposal['bill_to_hash']) {
															$customers->fetch_location_record($this->proposals->current_proposal['bill_to_hash']);
															$invoice_to_out[] = "&nbsp;&nbsp;--&nbsp;".$customers->current_location['location_name'];
															$invoice_to_in[] = $this->proposals->current_proposal['bill_to_hash'];
														}
														$invoice_to_out[] = "A Vendor";
														$invoice_to_in[] = "V";
														$invoice_to = $this->form->select("invoice_to[".$z."]",
														                                  $invoice_to_out,
														                                  '',
														                                  $invoice_to_in,
														                                  "blank=1",
														                                  "id=invoice_to_".$z,
														                                  "onChange=if(this.options[this.selectedIndex].value=='V'){toggle_display('invoice_to_vendor_holder_".$z.$this->popup_id."', 'block');if(\$F('invoice_to_vendor_hash_".$z."')){agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'innerHTML_update_billing_info', 'type=V', 'vendor_hash='+\$F('invoice_to_vendor_hash_".$z."'))}}else{toggle_display('invoice_to_vendor_holder_".$z.$this->popup_id."', 'none');agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'innerHTML_update_billing_info', 'type=C', 'customer_hash=".$this->proposals->current_proposal['customer_hash']."')}");
													}
												} elseif (!$direct)
													$invoice_to = stripslashes($customer_name).
													$this->form->hidden(array('invoice_to['.$z.']' => $this->proposals->current_proposal['customer_hash']));

												$logo = explode("|", fetch_sys_var('company_logo'));
												for ($i = 0; $i < count($logo); $i++) {
													$logo_in[] = $logo[$i];
													$logo_out[] = basename($logo[$i]);
												}

												// Check if new print preferences are set up for this user, and if they aren't give them the default set
												if (!$print_pref['gen_print_fields'] && !$print_pref['item_print_fields'] && !$print_pref['customer_contact']) {
													$print_pref['item_print_fields'] = array("line_no", "item_no", "item_descr", "qty", "sell", "ext_sell", "product");
													$print_pref['gen_print_fields'] = array("customer_contact", "customer_po", "comments", "install_addr", "remit_to");
													// Store these default preferences
													store_user_data("invoice_print", addslashes(serialize($print_pref)));
												}
												if ($remit_to = fetch_sys_var('REMIT_TO_ADDRESSES')) {
													$remit_to = __unserialize(stripslashes($remit_to));
													$remit_to_dd = create_drop_down_arrays($remit_to, "name");
												} else
													unset($remit_to, $remit_to_dd);

												// Find the selected value for both transmit type and remit to based off of user preferences
												$transmit_type = ($this->current_invoice['transmit_type'] ? $this->current_invoice['transmit_type'] : ($print_pref['transmit_type'] ? $print_pref['transmit_type'] : 'E'));
												$selected_val = ($print_pref['remit_to'] ? $print_pref['remit_to'] : 0);

												// If last chosen remit to no longer exists, default to the first one in the list
												if ($selected_val + 1 > count($remit_to))
													$selected_val = 0;
												unset($remit_to_tbl, $remit_to_toggle);

												for ( $i = 0; $i < count($remit_to); $i++ ) {

													$remit_to_addr[$i] = stripslashes($remit_to[$i]['name']) . "\n" .
													( $remit_to[$i]['street'] ?
    													stripslashes($remit_to[$i]['street']) . "\n" : NULL
    												) .
    												stripslashes($remit_to[$i]['city']) . ", {$remit_to[$i]['state']} {$remit_to[$i]['zip']}" .
    												( $remit_to[$i]['notes'] ?
        												"\n" . stripslashes($remit_to[$i]['notes']) : NULL
    												);

													$remit_to_tbl .= "
													<div id=\"remit_to_address_holder{$i}_{$z}\" style=\"" . ( $i == $selected_val ? "display:block" : "display:none" ) . ";\">" .
    													$this->form->text_area(
        													"name=remit_to_address[$z][$i]",
        													"id=remit_to_address_{$z}",
        													"rows=4",
        													"cols=25",
        													"value={$remit_to_addr[$i]}"
    													) . "
    												</div>";

													$remit_to_toggle .= "toggle_display('remit_to_address_holder{$i}_{$z}', 'none');";
												}

												$this->template = new templates();

												if (defined('INVOICE_BACKDATE') && INVOICE_BACKDATE == 1)
                                                    $jscript[] = "setTimeout('DateInput(\'invoice_date_".$z."\', true, \'YYYY-MM-DD\', \'".($this->current_invoice['invoice_date'] ? $this->current_invoice['invoice_date'] : date("Y-m-d"))."\', 1, \'invoice_date_holder".$z.$this->popup_id."\')', 19);";

                                                $jscript[] = "
                                                deposit_total = function(z) {
                                                    var total_amt = parseFloat( \$F('total_amt[' + z + ']').toString().replace(/[^-.0-9]/g, '') );
                                                    var total_tax = \$F('total_tax[' + z + ']').toString().replace(/[^-.0-9]/g, '');
                                                    if ( ! total_tax )
                                                        total_tax = 0;

                                                    total_tax = parseFloat(total_tax);
                                                    var deposit_used = \$F('deposit_used[' + z + ']').toString().replace(/[^-.0-9]/g, '');
                                                    if (!deposit_used)
                                                        deposit_used = 0;

                                                    deposit_used = parseFloat(deposit_used);

                                                    if (deposit_used > (total_amt + total_tax)) {
                                                        \$('deposit_used[' + z + ']').value = '\$' + formatCurrency(total_amt + total_tax);
                                                        deposit_used = (total_amt + total_tax);
                                                    }

                                                    var invoice_net = (total_amt + total_tax) - deposit_used;
                                                    \$('total_amount_' + z + '{$this->popup_id}').innerHTML = '\$' + (!invoice_net || invoice_net == 0 ? '0.00' : formatCurrency(invoice_net));
    											}";

												$inner_tbl .= "
													<div style=\"margin-top:15px;\" id=\"invoice_".$z."\">
														<table>
                                                            <tr>
                                                                <td style=\"font-weight:bold;\" class=\"smallfont\">
                                                                    Invoice Preview : ".(!$punch ?
                                                                        ($invoice_to ?
                                                                            stripslashes($invoice_to) : stripslashes($this->proposals->current_proposal['customer_name'])) : NULL)."
                                                                </td>
                                                                <td class=\"smallfont\">
                                                                    Items To Be Invoiced (".count($invoice_item[$z])."):
                                                                </td>
                                                            </tr>
															<tr>
																<td class=\"smallfont\" style=\"width:40%;vertical-align:top;\">
																	<div style=\"margin-left:20px;padding-top:5px\">
																		<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#cccccc;\">
																			<tr>
																				<td style=\"text-align:right;background-color:#efefef;vertical-align:top;\" class=\"smallfont\" id=\"err6".$z.$this->popup_id."\" nowrap>Invoice To:</td>
																				<td class=\"smallfont\" style=\"background-color:#ffffff;\">
																					".$invoice_to.($bill_to_detail && !$punch ?
																						"<div style=\"padding-top:5px;font-style:italic;\">".$bill_to_detail."</div>" : NULL).($punch ?
																						"<div style=\"padding-top:5px;display:none;\" id=\"invoice_to_vendor_holder_".$z.$this->popup_id."\">
																							<div style=\"padding-bottom:5px;\">Vendor:</div>
																							".$this->form->text_box("name=invoice_to_vendor_".$z,
																													"value=",
																													"autocomplete=off",
																													"size=22",
																													"TABINDEX=1",
																													"onFocus=position_element($('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(\$F('invoice_to_vendor_hash_".$z."')==''){clear_values('invoice_to_vendor_".$z."');}key_call('proposals', 'invoice_to_vendor_".$z."', 'invoice_to_vendor_hash_".$z."', 1);",
																													"onBlur=vtobj.reset_keyResults();key_clear();",
																													"onKeyDown=clear_values('invoice_to_vendor_hash_".$z."');").
																							$this->form->hidden(array("invoice_to_vendor_hash_".$z => ''))."
																						</div>" : NULL)."
																				</td>
																			</tr>".(defined('MULTI_CURRENCY') && $customer_currency && $customer_currency['code'] != $sys->home_currency['code'] ? "
																			<tr>
                                                                                <td style=\"text-align:right;background-color:#efefef;vertical-align:top;\" class=\"smallfont\">Currency:</td>
                                                                                <td class=\"smallfont\" style=\"background-color:#ffffff;\">
                                                                                    ".$this->form->select("currency_".$z,
																							               array($customer_currency['code'], $sys->home_currency['code']),
																							               $customer_currency['code'],
																							               array($customer_currency['code'], $sys->home_currency['code']),
																							               "blank=1")."
                                                                                    <span style=\"padding-left:5px;font-style:italic;\">(Shown in ".$sys->home_currency['code'].")</span>
                                                                                </td>
																			</tr>" : NULL)."
																			<tr>
                                                                                <td style=\"background-color:#ffffff;\" id=\"err5".$z.$this->popup_id."\" colspan=\"2\">".($tax_eligible && $this->proposals->current_proposal['install_addr_hash'] && $this->proposals->current_proposal['install_addr_state'] && !$this->proposals->current_proposal['gsa_customer'] && !$this->proposals->current_proposal['tax_exempt_customer'] && $this->proposals->current_proposal['install_addr_country'] == 'US' && !$this->proposals->current_proposal['install_addr_tax_lock'] || $punch == 1? "
																					<div style=\"float:right;margin-top:margin-right:5px;font-size:92%;\">
																						[<a href=\"javascript:void(0);\" onClick=\"submit_form(\$('popup_id').form, 'customer_invoice', 'exec_post', 'show_popup_window', 'action=change_tax', 'popup_id=tax_win', 'z=$z', 'parent_popup_id={$this->popup_id}', 'proposal_hash={$this->proposals->proposal_hash}', 'install_state=".$this->proposals->current_proposal['install_addr_state']."', 'install_country=".$this->proposals->current_proposal['install_addr_country']."', 'install_hash=".$this->proposals->current_proposal['install_addr_hash']."', '".($punch ? "punch=1" : NULL)."');\" class=\"link_standard\">update tax</a>]
																					</div>" : NULL).
																					($direct ?
	    																				NULL : $this->form->hidden(array("total_amt[".$z."]"    =>  _round($total_amt, 2),
	    																				                                 "punch"                =>  $punch)));
																					if ($z > 0)
																						unset($deposits);

																					if (count($deposits) && (!$direct || ($direct && $direct_bill_info[$z]['type'] == 'O'))) {
																						for ($i = 0; $i < count($deposits); $i++) {
																							$total_dep = _round(bcadd($total_dep, $deposits[$i]['balance'], 4), 2);
																							$dep_hash_hidden .= $this->form->hidden(array('deposit_hash[]' => $deposits[$i]['invoice_hash']));
																						}

																						if (bccomp($total_dep, bcadd($total_amt, $total_tax[$z], 2), 2) == 1)
																							$total_dep = ($total_amt + $total_tax[$z]);

																						$total_amt = (float_length($total_amt) > 2 ? _round($total_amt, 2) : $total_amt);
																						$total_tax[$z] = (float_length($total_tax[$z]) > 2 ? _round($total_tax[$z], 2) : $total_tax[$z]);
																						$total_dep = (float_length($total_dep) > 2 ? _round($total_dep, 2) : $total_dep);

																						$new_amt = bcsub(bcadd($total_amt, $total_tax[$z], 2), $total_dep, 2);
																						if ($total_dep > 0)
																							$deposit_tbl .= $dep_hash_hidden."
																							<tr>
																								<td style=\"padding:5px 0;text-align:right;\"  id=\"err1".$z.$this->popup_id."\" nowrap>
																									(".$this->form->text_box("name=deposit_used[".$z."]",
                        																									 "value=".number_format($total_dep, 2),
                        																									 "size=11",
                        																									 "style=text-align:right;",
																							                                 "onFocus=this.select();",
                        																									 "onBlur=this.value='\$' + formatCurrency(this.value)",
                        																									 "onKeyUp=deposit_total(".$z.");").")
																								</td>
																								<td style=\"font-style:italic;\">&nbsp;&nbsp;<small>(deposits)</small></td>
																							</tr>
																							<tr>
																								<td style=\"border-top:1px solid #000000;text-align:right;\" id=\"total_amount_".$z.$this->popup_id."\">
																									$".number_format($new_amt, 2)."
																								</td>
																								<td></td>
																							</tr>";
																						else
																							unset($deposit_tbl);
																					} else {
																						$total_dep = 0;
																						$total_amt = (float_length($total_amt) > 2 ? _round($total_amt, 2) : $total_amt);
																						$total_tax[$z] = (float_length($total_tax[$z]) > 2 ? _round($total_tax[$z], 2) : $total_tax[$z]);
																					}
																					
																					$inner_tbl .= "
																					<div style=\"margin-left:15px;\">
																						<table cellpadding=\"5\" cellspacing=\"0\">
																							<tr>
																								<td style=\"text-align:right;\" >".
            																					( $direct ?
																									($direct_bill_info[$z]['type'] == 'D' ?
																										$total_amt_select : "$".number_format($direct_bill_info[$z]['total_sell'], 2).
																										                    $this->form->hidden(array('total_amt['.$z.']['.$direct_bill_info[$z]['vendor_hash'].'][sell]' => $direct_bill_info[$z]['total_sell'],
																										                                              'direct_invoice_type['.$z.']['.$direct_bill_info[$z]['vendor_hash'].']' => 'O'))) : "$".number_format($total_amt, 2))."
																								</td>
																								<td style=\"font-size:92%;\" nowrap>" . ( ! $direct || ( $direct && $direct_bill_info[$z]['type'] != 'D' ) ?
																									"Total Sell" : "&nbsp;" ) . "
																								</td>
																							</tr>".(!$direct ?  "
																							<tr style=\"display:".(bccomp($total_tax[$z], 0) == 1 ? 'block' : 'none')."\" id=\"tax_tr_".$z.$this->popup_id."\">
																								<td style=\"text-align:right;\" id=\"total_tax_".$z.$this->popup_id."\">".($this->lines->tax_country == 'CA' && $tax_tbl ?
	    																							$tax_tbl : "$".number_format($total_tax[$z], 2)).
	                                                                                                $this->form->hidden(array('total_tax['.$z.']' => number_format($total_tax[$z], 2)))."
	                                                                                            </td>
																								<td style=\"padding-left:5px\">".($this->lines->tax_country == 'CA' && $tax_tbl ?
	                                                                                                $tax_msg : "<div style=\"font-size:92%;\">Tax</div>")."
	                                                                                            </td>
																							</tr>".($this->lines->tax_country == 'CA' && $tax_tbl ? "
	                                                                                        <tr>
	                                                                                            <td style=\"text-align:right;border-top:1px solid #8f8f8f;\">\$".number_format($total_tax[$z], 2)."</td>
	                                                                                            <td style=\"padding-left:5px;font-size:92%;border-top:1px solid #8f8f8f;\">Total Tax</td>
	                                                                                        </tr>" : NULL).($total_dep > 0 && $total_tax[$z] > 0 ? "
																							<tr>
																								<td style=\"border-top:1px solid #000000;padding:5px 0;text-align:right;\" id=\"invoice_subtotal_".$z.$this->popup_id."\">$".number_format(bcadd($total_amt, $total_tax[$z], 2), 2)."</td>
																								<td style=\"font-style:italic;\">&nbsp;&nbsp;<small>(subtotal)</small></td>
																							</tr>" : (!$total_dep ? "
																							<tr>
																								<td style=\"border-top:1px solid #8f8f8f;text-align:right;\" id=\"total_amount_".$z.$this->popup_id."\">$".number_format(bcadd($total_amt, $total_tax[$z], 2), 2)."</td>
																								<td style=\"border-top:1px solid #8f8f8f;\">Invoice Total</td>
																							</tr>" : NULL)) : NULL) . $deposit_tbl;

																				$inner_tbl .= "
																						</table>
																					</div>
																				</td>
																			</tr>
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;vertical-align:top;background-color:#efefef;\" class=\"smallfont\" id=\"err2".$z.$this->popup_id."\" nowrap>Invoice Date:</td>
																				<td class=\"smallfont\" style=\"vertical-align:top;background-color:#ffffff;\">".(defined('INVOICE_BACKDATE') && INVOICE_BACKDATE == 1 ? "
																					<div id=\"invoice_date_holder".$z.$this->popup_id."\"></div>" : date(DATE_FORMAT).$this->form->hidden(array('invoice_date_'.$z => date("Y-m-d"))))."
																				</td>
																			</tr>" .
																			( ! $direct || ( $direct && $total_amt ) || ( $direct && bccomp($direct_bill_info[$z]['total_sell'], 0, 2) ) || ( $direct && bccomp($direct_bill_info[$z]['total_com'], 0, 2) ) ? "
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;\" nowrap  id=\"err3".$z.$this->popup_id."\">Submit Via:</td>
																				<td class=\"smallfont\" style=\"background-color:#ffffff;\">".$this->form->select("transmit_type[".$z."]", array("Email", "Fax", "Mail"), $transmit_type, array('E', 'F', 'M'), "blank=1", "onChange=switch(this.options[selectedIndex].value){case 'F': toggle_display('transmit_to_fax_holder".$z."', 'block');toggle_display('transmit_to_email_holder".$z."', 'none');toggle_display('transmit_to_mail_holder".$z."', 'none');break;case 'E': toggle_display('transmit_to_fax_holder".$z."', 'none');toggle_display('transmit_to_email_holder".$z."', 'block');toggle_display('transmit_to_mail_holder".$z."', 'none');break;case 'M': toggle_display('transmit_to_fax_holder".$z."', 'none');toggle_display('transmit_to_email_holder".$z."', 'none');toggle_display('transmit_to_mail_holder".$z."', 'block');break;};")."</td>
																			</tr>
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" nowrap  id=\"err4".$z.$this->popup_id."\">Submit To:</td>
																				<td class=\"smallfont\" style=\"background-color:#ffffff;\">
																					<div id=\"transmit_to_fax_holder".$z."\" style=\"display:".($transmit_type ==  'F' ? "block" : "none").";\">".$this->form->text_box("name=transmit_to_fax[".$z."]", "id=transmit_to_fax_".$z, "value=".$fax_addr)."</div>
																					<div id=\"transmit_to_email_holder".$z."\" style=\"display:".($transmit_type == 'E' ? "block" : "none").";\">".$this->form->text_box("name=transmit_to_email[".$z."]", "id=transmit_to_email_".$z, "value=".$email_addr)."</div>
																					<div id=\"transmit_to_mail_holder".$z."\" style=\"display:".($transmit_type ==  'M' ? "block" : "none").";\">".$this->form->text_area("name=transmit_to_mail[".$z."]", "id=transmit_to_mail_".$z, "rows=5", "cols=25", "value=".$mail_to_addr)."</div>
																				</td>
																			</tr>
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" nowrap  id=\"err5{$this->popup_id}\">Remit To: </td>
																				<td class=\"smallfont\" style=\"background-color:#ffffff;\">
																				    <div id=\"remit_to_holder_{$this->popup_id}_".$z."\">".($remit_to_tbl ?
																						$this->form->select("remit_to[".$z."]",
                    																						$remit_to_dd[0],
                    																						($selected_val != 0 ? $selected_val : NULL),
                    																						$remit_to_dd[1],
                    																						"blank=1",
                    																						"style=width:175px;",
                    																						"onChange=if(\$('remit_to_address_holder'+this.options[this.selectedIndex].value+'_$z')){".$remit_to_toggle."toggle_display('remit_to_address_holder'+this.options[this.selectedIndex].value+'_$z', 'block');}") : "<div style=\"font-style:italic;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_remit_addresses', 'popup_id=new_remit_win', 'from=customer_invoice', 'selected_remit=".$print_pref['remit_to']."', 'z_total=".count($invoice_item)."', 'parent_popup_id={$this->popup_id}')\" title=\"Add Remittance Addresses\">No remittance addresses</a></div>")."
																				    </div>
																				    <div style=\"margin-top:5px;width:95%;display:none;\" id=\"remit_address_holder_{$this->popup_id}_".$z."\">".($remit_to_tbl ?
																				        $remit_to_tbl : NULL)."
																				    </div>
    																			</td>
																			</tr>
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Company<br />Logo:</td>
																				<td class=\"smallfont\" style=\"background-color:#ffffff;\">
																					".$this->form->select("use_logo_".$z,
																			                              $logo_out,
																			                              $print_pref['use_logo'],
																			                              $logo_in,
																			                              "blank=1",
																			                              "style=width:175px")."
																					<div style=\"padding-top:5px;\">
																						".$this->form->checkbox("name=print_logo_".$z, "value=1", ($print_pref['print_logo'] ? "checked" : NULL))."&nbsp;Print Logo?
																					</div>
																				</td>
																			</tr>" : NULL).
																			$this->template->template_select("invoice_print", 0, $z)."
																		</table>
																	</div>
																</td>
																<td class=\"smallfont\" style=\"width:60%;vertical-align:top;\">
																	<div style=\"margin-left:10px;margin-top:5px;padding-top:0;height:250px;overflow-y:auto;overflow-x:hidden;\">
																		<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
																			<tr class=\"thead\" style=\"font-weight:bold;font-size:95%;\">
																				<td>Qty</td>
																				<td>Item Descr</td>
																				<td class=\"num_field\">Ext Sell</td>".($direct ? "
																				<td class=\"num_field\">Commission</td>" : NULL)."
																			</tr>
																			".$item_detail_list."
																		</table>
																	</div>
																</td>
															</tr>
														</table>
													</div>";
												}
												$inner_tbl .= "
												</div>
											</td>
										</tr>
									</table>
								</div>
							</div>
						</td>
					</tr>
				</table>";
			}

		$tbl .= "
			</div>
		</div>".
		$this->form->close_form();

		if ($local)
			return array($inner_tbl, $jscript);
		else {
			if ($step) {
				$this->content['action'] = 'continue';
				$this->content['jscript'] = $jscript;
				$this->content['html']['po_content'.$this->popup_id] = $inner_tbl;
			} else
				$this->content['popup_controls']["cmdTable"] = $tbl;
		}
		return;
	}

	function innerHTML_update_billing_info() {
		$type = $this->ajax_vars['type'];
		if (!$type || $type == 'V') {
			$vendor_hash = $this->ajax_vars['vendor_hash'];
			$vendors = new vendors($this->current_hash);
			if ($valid = $vendors->fetch_master_record($vendor_hash)) {

				$this->content['value']['transmit_to_email_0'] = $vendors->current_vendor['order_email'];
				$this->content['value']['transmit_to_fax_0'] = $vendors->current_vendor['fax'];

				$this->content['value']['transmit_to_mail_0'] =
				stripslashes($vendors->current_vendor['vendor_name']) . "\n" .
				( $vendors->current_vendor['street'] ?
					stripslashes($vendors->current_vendor['street']) . "\n" : NULL
				) .
				stripslashes($vendors->current_vendor['city']) . ", {$vendors->current_vendor['state']} {$vendors->current_vendor['zip']}";

				return;
			}

		} elseif ($type == 'C') {

			$customer_hash = $this->ajax_vars['customer_hash'];

			$customers = new customers($this->current_hash);
			$customers->fetch_master_record($customer_hash);

			$this->content['value']['transmit_to_mail_0'] =
			stripslashes($customers->current_customer['customer_name']) . "\n" .
			( $customers->current_customer['street'] ?
    			stripslashes($customers->current_customer['street']) . "\n" : NULL
    		) .
    		stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";

    		if ( $customers->current_contact['contact_email'] )
    			$this->content['value']['transmit_to_email_0'] = $customers->current_contact['contact_email'];

    		if ( $customers->current_contact['fax'] || $customers->current_contact['contact_fax'] )
    			$this->content['value']['transmit_to_fax_0'] = ( $customers->current_customer['fax'] ? $customers->current_customer['fax'] : $customers->current_contact['contact_fax'] );
		}
	}

	/**
	 * Create a pro forma invoice
	 *
	 */
	function new_proforma() {
		$this->popup_id = $this->content['popup_controls']['popup_id'] = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id']);
		$this->content['popup_controls']['popup_title'] = "Print Pro Forma Invoice";
		$proposal_hash = $this->ajax_vars['proposal_hash'];

		if ($_POST['doit_print']) {
			$this->content['action'] = 'continue';

			$proposal_hash = $_POST['proposal_hash'];
			$print_pref['gen_print_fields'] = $gen_print_fields = $_POST['gen_print_fields'];
			$print_pref['item_print_fields'] = $item_print_fields = $_POST['item_print_fields'];
			$print_pref['footer_msg'] = $footer_msg = htmlspecialchars($_POST['footer_msg'], ENT_QUOTES);
			$print_pref['print_logo'] = $print_logo = $_POST['print_logo'];
			$print_pref['use_logo'] = $use_logo = $_POST['use_logo'];
			$print_pref['title'] = $title = htmlspecialchars($_POST['title'], ENT_QUOTES);
			$print_pref['percent'] = $percent = $_POST['percent'];
			$print_pref['remit_to'] = htmlspecialchars($_POST['remit_to'], ENT_QUOTES);
			$print_pref['remit_to_addr'] = htmlspecialchars($_POST['remit_to_address'][$_POST['remit_to']], ENT_QUOTES);
			$print_pref['invoice_no'] = $invoice_no = htmlspecialchars($_POST['invoice_no'], ENT_QUOTES);
			$print_pref['invoice_descr'] = htmlspecialchars($_POST['invoice_descr'], ENT_QUOTES);
			$print_pref['invoice_msg'] = $invoice_msg = htmlspecialchars($_POST['invoice_msg'], ENT_QUOTES);
			$print_pref['save_doc'] = $save_doc = $_POST['save_doc'];
			$print_pref['deposit_amount'] = $deposit_amount = str_replace(",", "", $_POST['deposit_amount']);
			$_SESSION['proforma_items'] = $proforma_items = $_POST['proforma_items'];
			$template_hash = $print_pref['default_template'] = $_POST['use_template'];
			$print_pref_hash = $print_pref['default_prefs'] = $_POST['use_prefs'];
			$print_prefs_str = addslashes(serialize($print_pref));
			$template = new templates();

			if (!$deposit_amount && (!is_numeric($percent) || ($percent > 100) || ($percent <= 0))) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'][] = "Invalid deposit percentage! Please enter a number between 1 and 100.";
				$this->content['form_return']['err']['err_percent'] = 1;
			}
			if (!$percent && (!is_numeric(str_replace(",", "", $deposit_amount)) || ($deposit_amount <= 0))) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'][] = "Invalid deposit amount! Please enter a dollar amount.";
				$this->content['form_return']['err']['err_deposit_amount'] = 1;
			}
			if (!is_array($item_print_fields) && is_array($proforma_items)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'][] = "No item print options selected! Please select at least one line item print field in order to print line items.";
				$this->content['form_return']['err']['err_item_print_fields'] = 1;
			}
			if ($percent && $deposit_amount) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'][] = "Invalid selection! Please enter only a deposit percentage or a deposit amount.";
				$this->content['form_return']['err']['err_deposit_amount'] = 1;
				$this->content['form_return']['err']['err_percent'] = 1;
			}
			if (!$invoice_no || !$title) {
				$this->content['error'] = 1;
				if (!$invoice_no)
					$this->content['form_return']['err']['err_invoice_no'] = 1;
				if (!$title)
					$this->content['form_return']['err']['err_title'] = 1;
				if (!$percent && !$deposit_amount)
					$this->content['form_return']['err']['err_percent'] = 1;
				$this->content['form_return']['feedback'][] = "You left some required fields blank! Please check the indicated fields below and try again.";
			}
			if (!$invoice_msg && !$proforma_items) {
				$this->content['error'] = 1;
				$this->content['form_return']['err']['err_proforma_items'] = 1;
				$this->content['form_return']['err']['err_invoice_msg'] = 1;
				$this->content['form_return']['feedback'][] = "You did not select any line items to invoice! Please check at least one item or choose to display an invoice message.";
			}
			if ($this->content['error']) {
				$this->content['submit_btn'] = "proforma_btn";
				return;
			}
			$template->doit_save_prefs("proforma_invoice", $print_prefs_str);
			if ($template->error) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'][] = $template->error_msg;
				$this->content['form_return']['err']['saved_prefs_name'] = 1;
				$this->content['submit_btn'] = "proforma_btn";
				return;
			}

			store_user_data("proforma_invoice", addslashes(serialize($print_pref)));

			$tbl .= "
			<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;text-align:left;\">
				<div id=\"loading{$this->popup_id}\" style=\"width:100%;padding-top:50px;padding-bottom:45px;text-align:center;\">
					<h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch{$this->popup_id}\">Loading Document...</h3>
					<div id=\"link_img_holder{$this->popup_id}\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
				</div>
                <input id=\"url\" type=\"hidden\" value=\"print.php?t=".base64_encode('proforma_invoice')."&h=".$proposal_hash."&footer_msg=".base64_encode($footer_msg)."&m=$template_hash&p=$print_pref_hash\"/>
                <input id=\"popup\" type=\"hidden\" value=\"$this->popup_id\">
            </div>";
            $this->content['html']['main_table'.$this->popup_id] = $tbl;
            $jscript[] = "setTimeout(function(){window.open(document.getElementById('url').value,'_blank');popup_windows[document.getElementById('popup').value].hide();},500);";

			if ($save_doc) {
				$this->content['page_feedback'] = 'Your pro forma invoice has been saved in the file vault.';
                $jscript[] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'doc_vault', 'otf=1', 'proposal_hash=$proposal_hash');";
			}
		} else {
			$result = $this->db->query("SELECT proposals.proposal_no, proposals.proposal_descr
										FROM proposals
										WHERE proposal_hash = '$proposal_hash'");
			$row = $this->db->fetch_assoc($result);
			$invoice_no = "PF-".$row['proposal_no'];
			$invoice_descr = $row['proposal_descr'];

			$logo = explode("|", fetch_sys_var('company_logo'));
			for ($i = 0; $i < count($logo); $i++) {
				$base_name = basename($logo[$i]);
				if (!ereg("_gray", $base_name)) {
					$logo_in[] = $logo[$i];
					$logo_out[] = $base_name;
				}
			}

			$template = new templates();

			$print_ops = array("line_no"		=>	"Line Numbers",
							   "vendor"			=>	"Vendor Name",
							   "product"		=>	"Product Name",
							   "item_no"		=>	"Item Number",
							   "item_descr"		=>	"Item Description",
							   "qty" 			=> 	"Item Quantity",
							   "list"			=>	"Item List Pricing",
							   "ext_list"		=>	"Extended List Pricing",
							   "sell"			=>	"Item Sell",
							   "ext_sell"		=>	"Extended Sell",
							   "item_tag"		=>	"Item Tagging",
							   "item_finish"	=>	"Item Finishes & Options",
							   "zero_sell"		=>	"Zero Sell Items",
							   "discounting"	=>	"Discounting",
							   "gp_margin"		=>	"GP Margin",
							   "list_discount"	=>	"List Discount",
							   "cr"				=>	"Item Special");
			if ($_SESSION['is_mngr']) {
				$print_ops['cost'] = "Item Cost";
				$print_ops['ext_cost'] = "Extended Cost";
			}
			$gen_print_ops = array("logo_page_only"		=>	"Print Logo on First Page Only",
								   "invoice_descr"		=>	"Invoice Description",
								   "item_totals"		=>	"Invoice Totals",
								   "total_due"			=>	"Total Due",
								   "group_totals"		=>	"Group Totals",
								   "grp_break"			=>	"Page Break After Groups",
								   "grp_sum"			=>	"Group Summary",
								   "tax"				=>	"Tax Amount Due",
								   "customer_po"		=>	"Customer PO",
								   "customer"			=>	"Billing Address",
								   "ship_to"			=>	"Shipping Location",
								   "install_addr"		=>	"Installation Location",
								   "panel_detail"		=>	"Panel Attribute Details");
			// Get print prefs from proforma if exist, otherwise use proposal print prefs
			if ($print_pref = fetch_user_data("proforma_invoice"))
				$print_pref = __unserialize(stripslashes($print_pref));
			elseif ($print_pref = fetch_user_data("proposal_print")) {
				$print_pref = __unserialize(stripslashes($print_pref));
				$print_pref['gen_print_fields'][] = "total_due";
				$print_pref['gen_print_fields'][] = "customer";
				$print_pref['save_doc'] = 1;
			}
			if ($remit_to = fetch_sys_var('REMIT_TO_ADDRESSES')) {
				$remit_to = __unserialize(stripslashes($remit_to));
				$remit_to_dd = create_drop_down_arrays($remit_to, "name");
			} else
				unset($remit_to, $remit_to_dd);

			// Get remit to address info
			$selected_val = ($print_pref['remit_to'] ? $print_pref['remit_to'] : 0);
			// If last chosen remit to no longer exists, default to the first one in the list
			if ($selected_val + 1 > count($remit_to))
				$selected_val = 0;

			for ( $i = 0; $i < count($remit_to); $i++ ) {

				$remit_to_addr[$i] =
				stripslashes($remit_to[$i]['name']) . "\n" .
				( $remit_to[$i]['street'] ?
    				stripslashes($remit_to[$i]['street']) . "\n" : NULL
    			) .
    			stripslashes($remit_to[$i]['city']) . ", {$remit_to[$i]['state']} {$remit_to[$i]['zip']}" .
    			( $remit_to[$i]['notes'] ?
        			"\n" . stripslashes($remit_to[$i]['notes']) : NULL
    			);

				$remit_to_tbl .= "
				<div id=\"remit_to_address_holder{$i}\" style=\"" . ( $i == $selected_val ? "display:block" : "display:none" ) . ";\">" .
    				$this->form->text_area(
        				"name=remit_to_address[$i]",
        				"id=remit_to_address",
        				"rows=6",
        				"cols=25",
        				"value={$remit_to_addr[$i]}",
        				"style=width:200px;"
        			) . "
        		</div>";

				$remit_to_toggle .= "toggle_display('remit_to_address_holder{$i}', 'none');";
			}

			$lines = new line_items($proposal_hash);
			$lines = $lines->fetch_line_items_short(1, "line_items.line_no");
			unset($line_items_tbl);
			if (count($lines) > 0) {
				for ($i = 0; $i < count($lines); $i++) {
					$line_items_tbl .= "
					<div style=\"margin-bottom:5px;\">".
						$this->form->checkbox("name=proforma_items[]", "value=".$lines[$i]['item_hash'])."
						&nbsp;".$lines[$i]['item_vendor']." : ".$lines[$i]['item_product']."
						<div style=\"margin-left:25px;margin-top:5px;\" title=\"".htmlspecialchars($lines[$i]['item_no'])."\">".(strlen($lines[$i]['item_descr']) > 30 ?
							substr($lines[$i]['item_descr'], 0, 30)."..." : $lines[$i]['item_descr']) ."
						</div>
					</div>";
				}
			} else {
				$line_items_tbl .= "
					<div style=\"margin-bottom:5px;margin-left:10px;font-style:italic;\">
						No active line items
					</div>";
			}
			// Javascript function to find status of first check box
			$jscript[] = "get_check_status = function(){
				var a = document.getElementsByName('proforma_items[]');
				return(a[0].checked);
			}";

			// Initially show the customer print preferences if the user has no defaults, or last time they chose custom
            if (($print_pref['default_prefs'] == "custom" && $this->p->ck("customer_invoice", 'P')) || !$print_pref['default_prefs'] || $template->show_custom_prefs("proforma_invoice"))
            	$show_custom_prefs = true;

			$tbl .= $this->form->form_tag().
			$this->form->hidden(array("proposal_hash" => $proposal_hash, "popup_id" => $this->popup_id))."
			<div id=\"main_table{$this->popup_id}\" style=\"margin-top:0;padding-top:0\">
				<div class=\"panel\" style=\"margin-top:0;height:100%;\">
					<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
						<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
							<p id=\"feedback_message{$this->popup_id}\"></p>
					</div>
					<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
						<tr>
							<td style=\"background-color:#ffffff;padding-left:20px;padding-bottom:5px;\">
								<div style=\"float:right;margin-top:10px;margin-right:25px;\">
									".$this->form->button("value=Print Invoice", "id=proforma_btn", "onClick=submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=new_proforma', 'doit_print=1');")."
								</div>
								<div style=\"margin-bottom:15px;margin-top:5px;font-weight:bold;\">Pro Forma Invoice Print Options</div>
								<div style=\"margin-left:15px;margin-bottom:15px;margin-top:15px;\">
									<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">".
										$template->template_select("proforma_invoice", ($remit_to_tbl ? 9 : 8), NULL, NULL, $this->popup_id, $proposal_hash)."
										<tr id=\"print_pref1\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;padding-top:10px;\">Company Logo:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">
												".$this->form->select("use_logo", $logo_out, $print_pref['use_logo'], $logo_in, "blank=1", "style=width:200px;")."
												<div style=\"padding-top:5px;\">
													".$this->form->checkbox("name=print_logo", "value=1", ($print_pref['print_logo'] ? "checked" : NULL))."&nbsp;Print Logo?
												</div>
											</td>
										</tr>
										<tr id=\"print_pref2\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">
												General Print Fields:
												<div style=\"padding-top:5px;font-style:italic;\"><small>hold cntrl key for multiple</small></div>
											</td>
											<td style=\"background-color:#ffffff;width:300;\">
												".$this->form->select("gen_print_fields[]", array_values($gen_print_ops), $print_pref['gen_print_fields'], array_keys($gen_print_ops), "blank=1", "multiple", "style=width:300px;height:100px")."
											</td>
										</tr>
										<tr id=\"print_pref3\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" id=\"err_item_print_fields\">
												Line Item Print Fields:
												<div style=\"padding-top:5px;font-style:italic;\"><small>hold cntrl key for multiple</small></div>
											</td>
											<td style=\"background-color:#ffffff;width:300;\">
												".$this->form->select("item_print_fields[]", array_values($print_ops), $print_pref['item_print_fields'], array_keys($print_ops), "blank=1", "multiple", "style=width:300px;height:100px")."
											</td>
										</tr>
										<tr>
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" id=\"err_proforma_items\">
												Line Items:
												<div style=\"margin-top:5px;\">
													[<small><a href=\"javascript:void(0);\" onClick=\"checkall(document.getElementsByName('proforma_items[]'));\" class=\"link_standard\">toggle all</a></small>]
												</div>
											</td>
											<td style=\"background-color:#ffffff;\">
												<div style=\"padding-top:5px;height:".(count($lines) > 4 ? "160" : (count($lines) == 0 ? 20 : 40 * count($lines)))."px;width:300;overflow:auto;background-color:#efefef;border:1px outset #cccccc\" id=\"line_item_table\">
													$line_items_tbl
												</div>
											</td>
										</tr>
										<tr>
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" id=\"err_invoice_msg\">Invoice Message:</td>
											<td style=\"background-color:#ffffff;\">".$this->form->text_area("name=invoice_msg", "value=".($print_pref['invoice_msg'] ? $print_pref['invoice_msg'] : ""), "cols=45", "rows=4", "style=width:300px;")."</td>
										</tr>
										<tr>
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">
    											<div id=\"err_percent\">$$ Percentage:</div>
    											<div style=\"margin-top:8px;margin-bottom:8px;\">OR</div>
    											<div style=\"margin-top:8px;\" id=\"err_deposit_amount\">$$ Amount</div>
    										</td>
											<td style=\"background-color:#ffffff;text-align:left;vertical-align:top;\">
												".$this->form->text_box("name=percent",
															            "value=".($print_pref['percent'] ? $print_pref['percent'] : ($print_pref['deposit_amount'] ? NULL : 100)),
															            "maxlength=6",
											                            "onFocus=this.select();",
															            "style=width:50px;text-align:right;",
											                            "onBlur=if(this.value){this.value=formatNumber(this.value);\$('deposit_amount').value=''}")."&nbsp;%
                                                <span style=\"padding-left:5px;font-style:italic;\">
                                                    <small>of proposal or selected line items</small>
                                                </span>
                                                <div style=\"margin-top:25px;\">
	                                                ".$this->form->text_box("name=deposit_amount",
	                                                                        "value=".($print_pref['deposit_amount'] ? $print_pref['deposit_amount'] : NULL),
	                                                                        "maxlength=15",
											                                "onFocus=this.select();",
	                                                                        "style=width:80px;text-align:right;",
	                                                                        "onBlur=if(this.value){this.value=formatCurrency(this.value);\$('percent').value=''}")."
                                                </div>
											</td>
										</tr>
										<tr id=\"print_pref4\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" id=\"err_title\">Document Title:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=title",
															"value=".($print_pref['title'] ? $print_pref['title'] : "Pro Forma Invoice"),
															"maxlength=36", "style=width:200px;")."
											</td>
										</tr>
										<tr>
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" id=\"err_invoice_no\">Proforma Invoice No:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=invoice_no",
															"value=".$invoice_no,
															"maxlength=32", "style=width:200px;")."
											</td>
										</tr>
										<tr id=\"print_pref5\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Invoice Description:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=invoice_descr",
															"value=".$invoice_descr,
															"maxlength=32", "style=width:200px;")."
											</td>
										</tr>
										<tr id=\"print_pref6\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td style=\"text-align:right;background-color:#efefef;\" nowrap  id=\"err5{$this->popup_id}\">".(!$remit_to_tbl ? "<img src=\"images/alert.gif\" title=\"No remittance addresses found in system configuration! Please update your settings.\" />&nbsp;" : NULL)."Remit To:</td>
											<td style=\"background-color:#ffffff;\">".($remit_to_tbl ? $this->form->select("remit_to", $remit_to_dd[0], ($selected_val != 0 ? $selected_val : NULL), $remit_to_dd[1], "blank=1", "onChange=if(\$('remit_to_address_holder'+this.options[this.selectedIndex].value)){".$remit_to_toggle."toggle_display('remit_to_address_holder'+this.options[this.selectedIndex].value, 'block');}")
													: "<div style=\"font-style:italic;\"><small>No remittance addresses found</small></div>")."</td>
										</tr>".($remit_to_tbl ? "
										<tr id=\"print_pref9\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td style=\"text-align:right;background-color:#efefef;vertical-align:top;\" nowrap  id=\"err6{$this->popup_id}\">Address:</td>
											<td style=\"background-color:#ffffff;\">$remit_to_tbl</td>
										</tr>" : NULL)."
										<tr id=\"print_pref7\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Footer Message:</td>
											<td style=\"background-color:#ffffff;\">".$this->form->text_area("name=footer_msg", "value=".($print_pref['footer_msg'] ? $print_pref['footer_msg'] : fetch_sys_var("invoice_footer_message")), "cols=45", "rows=4", "style=width:300;")."</td>
										</tr>
										<tr id=\"print_pref8\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td style=\"background-color:#efefef;\"></td>
											<td style=\"background-color:#ffffff;\">".$this->form->checkbox("name=save_doc", "value=1", ($print_pref['save_doc'] ? "checked" : NULL))."&nbsp;Save to File Vault?</td>
										</tr>".
										$template->save_print_prefs(($show_custom_prefs ? 1 : NULL))."
									</table>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>".$this->form->close_form();

		}

		$this->content['jscript'] = $jscript;
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function invoice_exists($invoice_seed, $invoice_no) {
		$r = $this->db->query("SELECT COUNT(*) AS Total
							   FROM customer_invoice
							   WHERE customer_invoice.invoice_no = '$invoice_no' OR customer_invoice.invoice_no LIKE '".$invoice_seed."-".$invoice_no."'");
		return $this->db->result($r, 0, 'Total');
	}

	function finance_charges() {
        $invoice_hash = $this->ajax_vars['invoice_hash'];
        $charge_hash = $this->ajax_vars['charge_hash'];
        $invoice_popup = $this->ajax_vars['invoice_popup'];

        if ($this->fetch_invoice_record($invoice_hash) == false)
            return $this->__trigger_error("Unable to fetch invoice record for edit.", E_USER_ERROR, __FILE__, __LINE__, 1, 1);

        list($finance_charges, $total_finance) = $this->fetch_finance_charges($this->invoice_hash);

        if ($charge_hash) {
            if ($this->fetch_finance_charge($charge_hash) == false)
                return $this->__trigger_error("Unable to fetch finance charge for edit", E_USER_ERROR, __FILE__, __LINE__, 1, 1);
        }

        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Add Finance Charges";
        $this->content['popup_controls']['popup_width'] = "425px;";
        $this->content['popup_controls']['popup_height'] = "575px;";
        $this->content['popup_controls']['popup_resize'] = 0;

        $due_date = ((is_numeric($this->current_invoice['payment_terms']) && $this->current_invoice['payment_terms'] == 0) || (!is_numeric($this->current_invoice['payment_terms']) && DEFAULT_CUST_PAY_TERMS == 0) ?
            -1 : date(DATE_FORMAT, strtotime($this->current_invoice['invoice_date']." +".(is_numeric($this->current_invoice['payment_terms']) ?
                $this->current_invoice['payment_terms'] : DEFAULT_CUST_PAY_TERMS)." days")));
        if ($due_date != -1)
            $past_due = floor(((strtotime(date("Y-m-d")) - strtotime($due_date)) / 86400));

        $this->content['jscript'][] = "
        var amount_finance = '".($this->current_invoice['amount'] < 0 ?
            "($".number_format($this->current_invoice['amount'] * -1, 2).")" : "$".number_format($this->current_invoice['amount'], 2))."';
        var balance_finance = '".($this->current_invoice['balance'] < 0 ?
            "($".number_format($this->current_invoice['balance'] * -1, 2).")" : "$".number_format($this->current_invoice['balance'], 2))."';
        var amount = '".($this->current_invoice['amount'] - $this->current_invoice['finance_charges'] < 0 ?
            "($".number_format(($this->current_invoice['amount'] - $this->current_invoice['finance_charges']) * -1, 2).")" : "$".number_format($this->current_invoice['amount'] - $this->current_invoice['finance_charges'], 2))."';
        var balance = '".($this->current_invoice['balance'] - $this->current_invoice['finance_charges'] < 0 ?
            "($".number_format(($this->current_invoice['balance'] - $this->current_invoice['finance_charges']) * -1, 2).")" : "$".number_format($this->current_invoice['balance'] - $this->current_invoice['finance_charges'], 2))."';
        var int_amount_finance = ".$this->current_invoice['amount'].";
        var int_balance_finance = ".$this->current_invoice['balance'].";
        var int_amount = ".($this->current_invoice['amount'] - $this->current_invoice['finance_charges']).";
        var int_balance = ".($this->current_invoice['balance'] - $this->current_invoice['finance_charges']).";
        calculate_amount = function() {
            var rate = arguments[0];
            var type = \$F('apply_to');
            var charge = 0;

            if (type == 1) {
                if (!\$('assess_overdue') || \$('assess_overdue').checked == 0)
                    var charge_amount = int_balance;
                else if (\$('assess_overdue').checked == 1)
                    var charge_amount = int_balance_finance;
            } else {
                if (!\$('assess_overdue') || \$('assess_overdue').checked == 0)
                    var charge_amount = int_amount;
                else if (\$('assess_overdue').checked == 1)
                    var charge_amount = int_amount_finance;
            }

            rate = parseFloat(rate);
            rate *= .01
            charge_amount = parseFloat( charge_amount.toString().replace(/[^-.0-9]/g, '') );

            charge = rate * charge_amount;
            \$('charge').value = formatCurrency(charge.toFixed(2));
        }
        recalc_totals = function() {
            var c = arguments[0];

            if (c == 1) {
                a = amount_finance;
                b = balance_finance;
            } else {
                a = amount;
                b = balance;
            }
            \$('amount_holder').innerHTML = '$'+formatCurrency(a);
            \$('balance_holder').innerHTML = '$'+formatCurrency(b);
        }";

        $template = new templates();
        $print_op = $template->template_select("invoice_print", 0, NULL, 1);

        $tbl =
        $this->form->form_tag().
        $this->form->hidden(array('popup_id'        =>  $this->popup_id,
                                  'charge_hash'     =>  $this->charge_hash,
                                  'invoice_hash'    =>  $this->invoice_hash,
                                  'invoice_popup'  =>  $invoice_popup))."
        <div class=\"panel\" id=\"main_table{$this->popup_id}\">
            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                 <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message{$this->popup_id}\"></p>
            </div>
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:390px;height:325px;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;\">
                        <h5 style=\"margin-bottom:5px;color:#00477f;\">
                            Customer Invoice : ".$this->current_invoice['invoice_no']."
                        </h5>
                        <div style=\"margin-top:15px;margin-left:15px;\">
                            Invoice Date: ".date(DATE_FORMAT, strtotime($this->current_invoice['invoice_date']))."
                            <div style=\"margin-bottom:4px;\"></div>
                            Due Date: ".($due_date == -1 ?
                                "Upon Receipt" : $due_date)."
                            <div style=\"margin-bottom:4px;\"></div>".($due_date != -1 ? "
                            Days Past Due: ".($past_due < 0 ?
                                "0" : $past_due)." Days
                            <div style=\"margin-bottom:4px;\"></div>" : NULL).
                            $this->form->hidden(array('invoice_balance' =>  $balance,
                                                      'invoice_amount'  =>  $amount,
                                                      'total_finance'  =>   $total_finance))."
                            Amount: <span id=\"amount_holder\">".($this->current_invoice['amount']  < 0 ?
                                "($".number_format(($this->current_invoice['amount']) * -1, 2).")" : "$".number_format($this->current_invoice['amount'], 2))."</span>
                            <div style=\"margin-bottom:4px;\"></div>
                            Balance: <span id=\"balance_holder\">".($this->current_invoice['balance']  < 0 ?
                                "($".number_format(($this->current_invoice['balance']) * -1, 2).")" : "$".number_format($this->current_invoice['balance'], 2))."</span>

                            <div style=\"margin-top:15px;\">
                                <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8f8f8f;\">
                                    <tr>
                                        <td style=\"text-align:right;width:40%;background-color:#efefef;\" nowrap>Add charge to: *</td>
                                        <td style=\"background-color:#ffffff;\">".$this->form->select("apply_to",
                                                                   array("Remaining Balance", "Invoice Total"),
                                                                   1,
                                                                   array(1, 2),
                                                                   "blank=1",
                                                                   "onChange=calculate_amount(\$F('rate'))")."
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\" nowrap>Posting Date: *</td>
                                        <td style=\"background-color:#ffffff;\"><div id=\"posting_date_holder{$this->popup_id}\"></div></td>
                                    </tr>".($total_finance ? "
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\">Assess on overdue finance charges? </td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->checkbox("name=assess_overdue",
                                                                    "value=1",
                                                                    "onClick=recalc_totals(this.checked);calculate_amount(\$F('rate'))",
                                                                    "checked")."&nbsp;%
                                        </td>
                                    </tr>" : NULL)."
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\">Interest Rate: </td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->text_box("name=rate",
                                                                    "value=",
                                                                    "onKeyUp=calculate_amount(this.value)",
                                                                    "size=4",
                                                                    "style=text-align:right;")."&nbsp;%
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\" id=\"err1{$this->popup_id}\" nowrap>Finance Charge: *</td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->text_box("name=charge",
                                                                    "value=",
                                                                    "onChange=if(this.value){\$('rate').value='';this.value=formatCurrency(this.value);}",
                                                                    "size=8",
                                                                    "style=text-align:right;")."
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;vertical-align:top;\" id=\"err2{$this->popup_id}\" nowrap>Comment: *</td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->text_area("name=comment",
                                                                     "value=",
                                                                     "rows=4",
                                                                     "cols=30")."
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\">Resend Invoice: </td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->checkbox("name=resend",
                                                                    "value=1",
                                                                    "onClick=toggle_display('print_op_holder1', this.checked ? 'block' : 'none');toggle_display('print_op_holder2', this.checked ? 'block' : 'none');")."
                                        </td>
                                    </tr>
                                    <tr id=\"print_op_holder1\" style=\"display:none;\">
                                        <td style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Print Template: </td>
                                        <td style=\"background-color:#ffffff;\">".$print_op[0]."</td>
                                    </tr>
                                    <tr id=\"print_op_holder2\" style=\"display:none;\">
                                        <td style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Print Options: </td>
                                        <td style=\"background-color:#ffffff;\">".$print_op[1]."</td>
                                    </tr>
                                </table>
                                <div style=\"margin-top:15px;margin-left:5px;\">
                                    ".$this->form->button("value=Save & Apply",
                                                          "id=finance_btn",
                                                          "onClick=if(confirm('Are you sure you want to apply finance charges to this invoice?')){submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=doit_finance_charge');this.disabled=1;}")."
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>".
        $this->form->close_form();

        if (!$this->charge_hash)
            $this->content['jscript'][] = "setTimeout('DateInput(\'posting_date\', true, \'YYYY-MM-DD\', \'".date("Y-m-d")."\', 1, \'posting_date_holder{$this->popup_id}\')', 19);";

        $this->content['popup_controls']["cmdTable"] = $tbl.$inner.$foot;
	}

	function fetch_finance_charges($invoice_hash) {
        if (!$invoice_hash)
            return false;

        $total_charges = 0;
		$r = $this->db->query("SELECT finance_charges.* , customer_invoice.invoice_no ,
		                       accounts.account_no , accounts.account_name
		                       FROM finance_charges
		                       LEFT JOIN customer_invoice ON customer_invoice.invoice_hash = finance_charges.invoice_hash
		                       LEFT JOIN accounts ON accounts.account_hash = finance_charges.account_hash
		                       WHERE finance_charges.invoice_hash = '$invoice_hash' AND finance_charges.deleted = 0
		                       ORDER BY finance_charges.date ASC");
		while ($row = $this->db->fetch_assoc($r)) {
            $charges[] = $row;
            $total_charges += $row['amount'];
		}

        return array($charges, $total_charges);
	}

	function fetch_finance_charge($charge_hash) {
		if (!$charge_hash)
            return false;

	    $r = $this->db->query("SELECT finance_charges.* , customer_invoice.invoice_no ,
                               accounts.account_no , accounts.account_name
                               FROM finance_charges
                               LEFT JOIN customer_invoice ON customer_invoice.invoice_hash = finance_charges.invoice_hash
                               LEFT JOIN accounts ON accounts.account_hash = finance_charges.account_hash
                               WHERE finance_charges.charge_hash = '$charge_hash' AND finance_charges.deleted = 0");
        if ($row = $this->db->fetch_assoc($r)) {
            $this->current_finance_charge = $row;
            $this->charge_hash = $charge_hash;

            return true;
        }

        return false;
	}

	function doit_finance_charge() {

		$submit_btn = 'finance_btn';
		$charge_hash = $_POST['charge_hash'];
		$invoice_hash = $_POST['invoice_hash'];
		$apply_to = $_POST['apply_to'];
		$date = $_POST['posting_date'];
		$rate = preg_replace('/[^.0-9]/', "", $_POST['rate']);
		$charge = preg_replace('/[^-.0-9]/', "", $_POST['charge']);
		$comment = trim( $_POST['comment'] );
		$resend = $_POST['resend'];
		$invoice_popup = $_POST['invoice_popup'];
		$rm = $_POST['rm'];

		if ( $rm || ( $charge && bccomp($charge, 0, 2) == 1 && $comment ) ) {

			$accounting = new accounting($this->current_hash);

            if ( ! $this->fetch_invoice_record($invoice_hash) )
                return $this->__trigger_error("System error encountered during invoice lookup. Unable to fetch invoice for database edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            $accounting->start_transaction( array(
	            'customer_hash'     =>  $this->current_invoice['customer_hash'],
	            'ar_invoice_hash'   =>  $this->invoice_hash,
	            'proposal_hash'     =>  $this->current_invoice['proposal_hash']
            ) );

            $audit_id = $accounting->audit_id();

	        if ( $rm ) {

	        	if ( ! $this->fetch_finance_charge($charge_hash) )
                    return $this->__trigger_error("System error encountered when attempting to fetch finance charge for delete. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	        	if ( ! $this->db->query("UPDATE finance_charges t1
			        	                 SET t1.deleted = 1, t1.deleted_date = CURDATE()
			        	                 WHERE charge_hash = '{$this->charge_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( ! $accounting->exec_trans($audit_id, $this->current_finance_charge['date'], DEFAULT_AR_ACCT, bcmul($this->current_finance_charge['amount'], -1, 2), 'AD', "Finance charges reversal - Invoice {$this->current_invoice['invoice_no']}") )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                if ( ! $accounting->exec_trans($audit_id, $this->current_finance_charge['date'], $this->current_finance_charge['account_hash'], bcmul($this->current_finance_charge['amount'], -1, 2), 'AD', "Finance charges reversal - Invoice {$this->current_invoice['invoice_no']}") )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                $new_balance = bcsub($this->current_invoice['balance'], $this->current_finance_charge['amount'], 2);
                $new_amount = bcsub($this->current_invoice['amount'], $this->current_finance_charge['amount'], 2);
                $finance_charges = bcsub($this->current_invoice['finance_charges'], $this->current_finance_charge['amount'], 2);

                if ( ! $this->db->query("UPDATE customer_invoice t1
                                         SET
                                             t1.balance = '$new_balance',
                                             t1.amount = '$new_amount',
                                             t1.finance_charges = '$finance_charges'
                                         WHERE t1.invoice_hash = '{$this->invoice_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( ! $accounting->end_transaction() )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                $this->db->end_transaction();

                $this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->proposal_hash}\', \'otf=1\');', 2500);window.setTimeout('agent.call(\'customer_invoice\', \'sf_loadcontent\', \'cf_loadcontent\', \'proposal_invoices\', \'proposal_hash={$this->proposal_hash}\', \'otf=1\');', 1500);";
	            $this->content['action'] = 'close';
	            $this->content['page_feedback'] = 'Finance charge has been deleted';

	            return;

	        } else {

	        	if ( strtotime($date) < strtotime($this->current_invoice['invoice_date']) ) {

	        		$this->set_error("posting_date_holder{$this->popup_id}");
	        		return $this->__trigger_error("Finance charge posting date must not fall before invoice date. Please check your input and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
	        	}

                $charge_hash = rand_hash('finance_charges', 'charge_hash');

                if ( ! $accounting->exec_trans($audit_id, $date, DEFAULT_AR_ACCT, $charge, 'FC', "Finance charges applied - Invoice {$this->current_invoice['invoice_no']}") )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! defined('FINANCE_CHARGE_ACCOUNT') || ! $accounting->fetch_account_record(FINANCE_CHARGE_ACCOUNT) )
                    return $this->__trigger_error("Unable to lookup valid chart of account for finance charges. Please check that the default finance charge account has been properly configured: System Config -> Company & System Settings -> Company Settings.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! $accounting->exec_trans($audit_id, $date, $accounting->account_hash, $charge, 'FC', ( strlen($comment) > 45 ? substr($comment, 0, 43) . "..." : $comment ) ) )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! $this->db->query("INSERT INTO finance_charges
                                         VALUES
                                         (
                                             NULL,
                                             UNIX_TIMESTAMP(),
                                             '{$this->current_hash}',
                                             '$charge_hash',
                                             '{$this->invoice_hash}',
                                             0,
                                             NULL,
                                             '$date',
                                             '$charge',
                                             '" . FINANCE_CHARGE_ACCOUNT . "',
                                             '" . addslashes($comment) . "'
                                         )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                $new_balance = bcadd($this->current_invoice['balance'], $charge, 2);
                $new_amount = bcadd($this->current_invoice['amount'], $charge, 2);
                $finance_charges = bcadd($this->current_invoice['finance_charges'], $charge, 2);

                if ( ! $this->db->query("UPDATE customer_invoice t1
                                         SET
                                             t1.balance = '$new_balance',
                                             t1.amount = '$new_amount',
                                             t1.finance_charges = '$finance_charges'
                                         WHERE t1.invoice_hash = '{$this->invoice_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( ! $accounting->end_transaction() )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                $this->db->end_transaction();

                if ( $resend ) {

	                if ( $this->current_invoice['transmit_type'] == 'E' || $this->current_invoice['transmit_type'] == 'F' ) {

	                    if ( $this->current_invoice['transmit_type'] == 'E' ) {

	                        $args['subject'] = "Invoice {$this->current_invoice['invoice_no']}";
	                        $args['email_body'] = "";
	                    }

	                    $args['type'] = $this->current_invoice['transmit_type'];
	                    $args['invoice_hash'] = $this->invoice_hash;
	                    $args['proposal_hash'] = $this->proposal_hash;
	                    $args['recipient_list'] = $args['fax'] = $this->current_invoice['transmit_to'];

	                    $args['date'] = date("Y-m-d");
	                    $args['attached'] = "customer_invoice|{$this->invoice_hash}|Customer_Invoice_{$this->current_invoice['invoice_no']}.pdf";

	                    # Get print pref and template hash for this invoice
	                    $args['use_prefs'] = $_POST["use_prefs"];
	                    $args['use_template'] = $_POST["use_template"];

	                    $send = new mail;
	                    if ( ! $send->doit_send($args) )
	                        $queue_err[] = "{$this->current_invoice['invoice_no']}|{$send->content['form_return']['feedback']}";

	                } elseif ( $this->current_invoice['transmit_type'] == 'M' )
	                    $invoice_hash_created[] = $this->invoice_hash;
                }

                $this->content['action'] = 'close';
                $this->content['jscript'][] = "agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash={$this->invoice_hash}', 'popup_id=$invoice_popup');";
                $this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->proposal_hash}\', \'otf=1\');', 2500);window.setTimeout('agent.call(\'customer_invoice\', \'sf_loadcontent\', \'cf_loadcontent\', \'proposal_invoices\', \'proposal_hash={$this->proposal_hash}\', \'otf=1\');', 1500);";
                $this->content['page_feedback'] = 'Finance charges have been applied to this invoice.';

                if ( $invoice_hash_created )
                    $this->content['jscript'][] = "agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'print_invoice', 'proposal_hash={$this->proposal_hash}', 'invoice_hash=" . implode("|", $invoice_hash_created) . "', 'use_template={$_POST['use_template']}', 'use_prefs={$_POST['use_prefs']}', 'popup_id=print_invoice', 'exec=1');";

                return;
	        }

		} else {

			if ( ! $rm ) {

	            if ( ! $charge || bccomp($charge, 0, 2) == -1 ) $this->set_error("err1{$this->popup_id}");
	            if ( ! $comment ) $this->set_error("err2{$this->popup_id}");

	            return $this->__trigger_error( ( bccomp($charge, 0, 2) == -1 ? "Negative amounts for finance charges are not permitted!" : "Please make sure you have completed the require fields indicated below!" ), E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			} else
                return $this->__trigger_error("Unable to fetch charge id for edit. <!-- Tried fetching charge [ $charge_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
		}

	}

	function fetch_customer_credits() {
        $result = $this->db->query("SELECT customer_credit.credit_hash , customer_credit.credit_date ,
                                    customer_credit.balance
                                    FROM `customer_credit`
                                    WHERE customer_credit.customer_hash = '".$this->customer_hash."'
                                    AND customer_credit.balance > 0 AND customer_credit.deleted = 0
                                    ORDER BY customer_credit.credit_date DESC");
        while ($row = $this->db->fetch_assoc($result))
            $credit[] = $row;

        return $credit;
	}

	function total_payments($invoice_hash) {

		$total_payments = 0;
		$total_credits = 0;

		$r = $this->db->query("SELECT t1.receipt_amount , t1.applied_from , t1.applied_type , t1.currency , t1.exchange_rate , t1.currency_adjustment
		                       FROM customer_payment t1
		                       WHERE t1.invoice_hash = '$invoice_hash' AND t1.deleted = 0");
		while ($row = $this->db->fetch_assoc($r)) {
            if ($row['applied_from'] && $row['applied_type'] == 'C')
                $total_credits += $row['receipt_amount'];
            else
                $total_payments += $row['receipt_amount'];
		}

		return array($total_payments, $total_credits);
	}

	function direct_bill_layout() {
        $proposal_hash = ($_POST['invoice_proposal_hash'] ? $_POST['invoice_proposal_hash'] : $this->ajax_vars['invoice_proposal_hash']);
        $parent_popup_id = ($_POST['parent_popup_id'] ? $_POST['parent_popup_id'] : $this->ajax_vars['parent_popup_id']);

        $post = $_POST['post'];

        if ($post == 1) {
        	$item = $_POST['item'];
        	$vendor_map = $_POST['vendor_map'];
        	$direct_bill = $_POST['direct_bill'];

        	$sum = array();
            for ($i = 0; $i < count($item); $i++) {
            	$sum[$vendor_map[$item[$i]]][] = $item[$i];
                $invoice_item[] = "'invoice_item[]=".$item[$i]."'";
            }

            if (is_array($sum)) {
                while (list($vendor_group, $direct_vendor) = each($direct_bill)) {
                    for ($i = 0; $i < count($sum[$vendor_group]); $i++)
                        $this->db->query("UPDATE `line_items`
                                          SET `direct_bill_vendor` = '".($direct_vendor == 'OPEN' ? "" : $direct_vendor)."'
                                          WHERE `item_hash` = '".$sum[$vendor_group][$i]."'");
                }
            }

        	$this->content['jscript_action'] = "submit_form(\$('proposal_hash').form, 'customer_invoice', 'exec_post', 'refresh_form', 'action=new_invoice', 'step=1', 'trans_type=I', ".implode(", ", $invoice_item).");";
        	$this->content['action'] = 'close';
        	return;
        } else {
        	$item = explode("|", $this->ajax_vars['item']);
        	$vendor = array();
            for ($i = 0; $i < count($item); $i++) {
            	$r = $this->db->query("SELECT t1.vendor_hash , t1.item_hash , t2.vendor_name , t1.direct_bill_vendor ,
            	                       SUM(t1.qty * t1.sell) AS ext_sell , SUM(t1.qty * t1.cost) AS ext_cost
            	                       FROM line_items t1
            	                       LEFT JOIN vendors t2 ON t2.vendor_hash = t1.vendor_hash
            	                       WHERE t1.obj_id = '".$item[$i]."'
            	                       GROUP BY t1.item_hash");
            	$row = $this->db->fetch_assoc($r);
            	$vendor[$row['vendor_hash']]['name'] = stripslashes($row['vendor_name']);
            	$vendor[$row['vendor_hash']]['total_sell'] += $row['ext_sell'];
            	$vendor[$row['vendor_hash']]['total_cost'] += $row['ext_cost'];
                $direct_bill_vendor_select[$row['vendor_hash']]['name'] = stripslashes($row['vendor_name']);
                $direct_bill_vendor_select[$row['vendor_hash']]['default'] = $row['direct_bill_vendor'];
                $hidden .= $this->form->hidden(array('item[]'                            => $row['item_hash'],
                                                     'vendor_map['.$row['item_hash'].']' => $row['vendor_hash']));
            }

            if (is_array($direct_bill_vendor_select)) {
                while (list($id, $info) = each($direct_bill_vendor_select)) {
                    $direct_vendor_hash[] = $id;
                    $direct_vendor_name[] = "Direct bill to ".$info['name'];
                }
                $direct_vendor_hash[] = "OPEN";
                $direct_vendor_name[] = "Treat as a normal (open) market business";
            }

            if (is_array($vendor)) {
            	$direct_bill_tbl = '';
            	while (list($id, $info) = each($vendor)) {
            		$direct_bill_tbl .= "
            		<tr>
                        <td style=\"padding:8px;vertical-align:top;width:45%\">
                            <div style=\"font-weight:bold;padding-bottom:10px;\">
                                ".$info['name']."
                            </div>
                            <div style=\"padding-bottom:5px;\">
                                Total Sell: ".math::format_num($info['total_sell'])."
                            </div>
                            <div style=\"padding-bottom:5px;\">
                                Total Cost: ".math::format_num($info['total_cost'])."
                            </div>
                        </td>
                        <td style=\"vertical-align:top;width:55%\">
                            <div style=\"padding-top:15px;\">
	                            ".$this->form->select("direct_bill[".$id."]",
	                                                   $direct_vendor_name,
	                                                   ($direct_bill_vendor_select[$id]['default'] ? $direct_bill_vendor_select[$id]['default'] : "OPEN"),
	                                                   $direct_vendor_hash,
	                                                   'blank=1',
	                                                   "direct_action=1")."
                            </div>
                        </td>
            		</tr>";
            	}
            }
            $this->popup_id = $this->ajax_vars['popup_id'];
	        $this->content['popup_controls']['popup_id'] = $this->popup_id = 'direct_bill_win';
	        $this->content['popup_controls']['popup_title'] = "Change Direct Bill Designation";

	        $this->content['jscript'][] = "
	        validate_form = function() {
    	        var fobj = arguments[0];
    	        var opts = $$('select[direct_action=1]');

                var unique = new Array();
                var el = '';

                for (var i = 0; i < opts.length; i++) {
                    el = opts[i];
                    unique[unique.length] = \$F(el);
                }
                var unique = unique.uniq();

                if (unique.length == 1) {
                    var first_el = unique.first();
                    if (first_el == 'OPEN') {
                        if (confirm('You have indicated that all vendors to be invoiced should be treated as normal open market business despite the proposal being labeled as a \'DIRECT BILL\' under the project info tab. Are you sure you want to continue? If you have incorrectly assigned the direct bill vendor setup and choose to continue, you may be incorrectly processing invoice transactions, resulting in incorrect reporting data.')) {
                            submit_form(fobj, 'customer_invoice', 'exec_post', 'refresh_form', 'action=direct_bill_layout', 'post=1');
                            return true;
                        } else
                            return false;
                    }
                }
                submit_form(fobj, 'customer_invoice', 'exec_post', 'refresh_form', 'action=direct_bill_layout', 'post=1');
                return true;
	        }
	        ";
	        $tbl = $this->form->form_tag().
	        $this->form->hidden(array("invoice_proposal_hash"      => $proposal_hash,
	                                  "direct_bill_layout" => 1,
	                                  "parent_popup_id"    => $parent_popup_id,
	                                  "popup_id"           => $this->popup_id)).
            $hidden."
	        <div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
	            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
	                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
	                    <p id=\"feedback_message{$this->popup_id}\"></p>
	            </div>
	            <table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
	                <tr>
	                    <td style=\"padding:15px 25px;;background-color:#ffffff;\">
	                        <table >
                            ".$direct_bill_tbl."
                            </table>
                            <div style=\"margin-top:20px;margin-bottom:10px;margin-left:15px;\">
                                ".$this->form->button("value=Save Settings",
                                                      "onClick=validate_form(this.form);")."
                            </div>
                        </td>
                    </tr>
	            </table>
	        </div>".
	        $this->form->close_form();

            $this->content['popup_controls']["cmdTable"] = $tbl;
        }
	}

	function fetch_default_currency($customer_hash) {
        if (!$customer_hash)
            return;

        $r = $this->db->query("SELECT t1.currency , t1.obj_id
                               FROM customers t1
                               WHERE t1.customer_hash = '".$customer_hash."'");
        if ($row = $this->db->fetch_assoc($r))
            $currency = $row['currency'];
        else {
	        $r = $this->db->query("SELECT t1.currency
	                               FROM vendors t1
	                               WHERE t1.vendor_hash = '".$customer_hash."'");
        	$currency = $this->db->result($r, 0, 'currency');
        }

        return $currency;
	}

    function proposal_invoices($local=NULL) {

        $order = $this->ajax_vars['order'];
        $order_dir = $this->ajax_vars['order_dir'];

        if ( $proposal_hash = $this->ajax_vars['proposal_hash'] ) {

        	$proposals = new proposals($this->current_hash);
        	if ( ! $proposals->fetch_master_record($proposal_hash) )
            	return $this->__trigger_error("System error encountered when trying to lookup proposal for invoice display. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

            $this->get_total( array(
                'proposal_hash' =>  $proposal_hash
            ) );
        }

        $order_by_ops = array(
            "invoice_no"    =>  "customer_invoice.invoice_no",
            "invoice_date"  =>  "customer_invoice.invoice_date"
        );

        if ( $this->total ) {

	        $this->fetch_customer_invoices(
	            0,
	            $this->total,
	            $order_by_ops[ ( $order ? $order : $order_by_ops['invoice_no'] ) ],
	            $order_dir
	        );
        }

        $tbl .= "
        <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
            <tr>
                <td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"8\">" .
	                ( $this->total ? "
	                    Showing 1 - {$this->total} of {$this->total} proposal receivables" : NULL
	                ) . "
                    <div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
	                ( $this->p->ck(get_class($this), 'A') ? "
                        <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'new_invoice', 'proposal_hash={$this->proposal_hash}', 'popup_id=line_item');\"><img src=\"images/new_customer_invoice.gif\" title=\"Create New Invoice\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;" : "&nbsp;&nbsp;&nbsp;"
                    ) .
                    ( $this->p->ck(get_class($this), 'A') ? "
                        <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'new_proforma', 'proposal_hash={$this->proposal_hash}', 'popup_id=line_item');\"><img src=\"images/proforma.gif\" title=\"Create Pro forma Invoice\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;" : "&nbsp;&nbsp;&nbsp;"
                    ) .
                    ( $this->p->ck(get_class($this), 'RD') ? "
                        <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'new_deposit', 'proposal_hash={$this->proposal_hash}', 'popup_id=line_item');\"><img src=\"images/new_deposit.gif\" title=\"Receive customer deposit\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;" : "&nbsp;&nbsp;&nbsp;"
                    ) . "
                    </div>
                </td>
            </tr>
            <tr class=\"thead\" style=\"font-weight:bold;\">
                <td style=\"width:22px;\"></td>
                <td style=\"width:100px;vertical-align:bottom;\">
                    <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'proposal_invoices', 'otf=1', 'proposal_hash={$this->proposal_hash}', 'p=$p', 'order=invoice_no', 'order_dir=" . ( $order == "invoice_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "', 'limit=$limit');\" style=\"color:#ffffff;text-decoration:underline;\">Invoice No.</a>" .
                    ( $order == 'invoice_no' ?
                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                    ) . "
                </td>
                <td style=\"width:120px;vertical-align:bottom;\" nowrap>
                    <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'proposal_invoices', 'otf=1', 'proposal_hash={$this->proposal_hash}', 'p=$p', 'order=invoice_date', 'order_dir=" . ( $order == "invoice_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "', 'limit=$limit');\" style=\"color:#ffffff;text-decoration:underline;\">Date</a>" .
                    ( $order == 'invoice_date' ?
                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                    ) . "
                </td>
                <td style=\"vertical-align:bottom;\">Sent By</td>
                <td style=\"vertical-align:bottom;\" class=\"num_field\">Amount</td>
                <td style=\"vertical-align:bottom;\" class=\"num_field\">Balance</td>
            </tr>";

            for ( $i = 0; $i < $this->total; $i++ ) {

            	if ( $i < $this->total - 1 )
	            	$border = "border-bottom:1px solid #cccccc;";

                $tbl .= "
                <tr " . ( $this->p->ck(get_class($this), 'V') ? "onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', '" . ( $this->invoice_info[$i]['type'] == 'D' ? "new_deposit" : "edit_invoice" ) . "', 'proposal_hash={$this->proposal_hash}', 'invoice_hash={$this->invoice_info[$i]['invoice_hash']}', 'popup_id=line_item');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
                    <td style=\"width:22px;vertical-align:bottom;{$border}\">" .
                    ( $this->invoice_info[$i]['type'] == 'I' && $this->invoice_info[$i]['transmit_type'] != 'M' ?
                        $this->invoice_icons[ $this->invoice_info[$i]['status'] ] : "&nbsp;"
                    ) . "&nbsp;
                    </td>
                    <td style=\"vertical-align:bottom;text-align:left;{$border}font-style:italic;\" class=\"smallfont\">" .
                    ( $this->invoice_info[$i]['type'] == 'D' ?
                        "Customer Deposit" :
                        ( $this->invoice_info[$i]['invoice_to'] == 'C' ?
                            stripslashes($this->invoice_info[$i]['customer_name']) :
                            ( $this->invoice_info[$i]['direct_bill'] ?
                                "Direct: " : NULL
                            ) . stripslashes($this->invoice_info[$i]['vendor_name'])
                        ) . "
                        <div style=\"padding-top:5px;font-style:normal;\">{$this->invoice_info[$i]['invoice_no']}</div>"
                    ) . "
                    </td>
                    <td style=\"vertical-align:bottom;{$border}\" class=\"smallfont\">" . date(DATE_FORMAT, strtotime($this->invoice_info[$i]['invoice_date']) ) . "</td>
                    <td style=\"vertical-align:bottom;{$border}\" class=\"smallfont\" >" .
                    ( $this->invoice_info[$i]['type'] != 'D' ?
	                    ( $this->invoice_info[$i]['transmit_type'] == 'F' ?
	                        "Fax" :
	                        ( $this->invoice_info[$i]['transmit_type'] == 'E' ?
	                            "Email" : "Mail"
	                        )
	                    ) : "&nbsp;"
	                ) . "
                    </td>
                    <td style=\"vertical-align:bottom;text-align:right;{$border}\" class=\"smallfont\">" .
	                ( bccomp($this->invoice_info[$i]['amount'], 0, 2) == -1 ?
                        "(\$" . number_format( bcmul($this->invoice_info[$i]['amount'], -1, 2), 2) . ")" : "\$" . number_format($this->invoice_info[$i]['amount'], 2)
	                ) . "
                    </td>
                    <td style=\"vertical-align:bottom;text-align:right;{$border}\" class=\"smallfont\">" .
	                ( bccomp($this->invoice_info[$i]['balance'], 0, 2) == -1 ?
                        "(\$" . number_format( bcmul($this->invoice_info[$i]['balance'], -1, 2), 2) . ")" : "\$" . number_format($this->invoice_info[$i]['balance'], 2)
	                ) . "
                    </td>
                </tr>";

	            unset($border);
            }

            if ( ! $this->total )
                $tbl .= "
                <tr >
                    <td class=\"smallfont\" colspan=\"6\">You have no customer invoices to display under this proposal. </td>
                </tr>";

            $tbl .= "
        </table>";

        if ( $local )
            return $tbl;

        $this->content['html']['tcontent6_1'] = $tbl;

        return;
    }

    /**
     * Query the customer_invoice table and count total invoices according to parameters
     *
     * @param  array    $param  Associative array with index list identical to __construct
     */

    function get_total() {

        if ( $param = func_get_arg(0) ) {

            $this->proposal_hash = $param['proposal_hash'];
            $this->invoice_hash = $param['invoice_hash'];
            $this->customer_hash = $param['customer_hash'];
        }

        $result = $this->db->query("SELECT COUNT(*) AS Total
                                    FROM customer_invoice
                                    WHERE " .
                                    ( $this->customer_hash ? "
                                        customer_hash = '{$this->customer_hash}' AND " : NULL
                                    ) .
                                    ( $this->proposal_hash ? "
                                        proposal_hash = '{$this->proposal_hash}' AND " : NULL
                                    ) . "deleted = 0");

        $this->total = $this->db->result($result, 0, 'Total');
    }

    /**
     * Delete customer invoice/deposit
     *
     * @param
     * @param
     * @throws
     * @return
     */

	function doit_delete_invoice() {

		$invoice_hash = $_POST['invoice_hash'];
		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];

		if ( ! $this->fetch_invoice_record($invoice_hash) )
			return $this->__trigger_error("System error when attempting to fetch invoice record for invoice delete. Please reload window and try again. <!-- Tried fetching invoice [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

		if ( $this->current_invoice['direct_bill'] )
			$direct_bill = true;

		$posting_date = $this->current_invoice['invoice_date'];

		$accounting = new accounting($this->current_hash);
		if ( ! $this->db->start_transaction() )
			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

		$accounting->start_transaction( array(
			'proposal_hash'      =>  $this->current_invoice['proposal_hash'],
			'customer_hash'      =>  $this->current_invoice['customer_hash'],
			'ar_invoice_hash'    =>  $this->invoice_hash
		) );

		$audit_id = $accounting->audit_id();

		$lines = new line_items($this->proposals->proposal_hash);
		$vendors = new vendors($this->current_hash);

		list($total_tax, $tax_hash, $tax_local, $indiv_tax) = $lines->fetch_invoice_tax($this->invoice_hash);

		for ( $i = 0; $i < $this->current_invoice['total_items']; $i++ ) {

			if ( ! $lines->fetch_line_item_record($this->current_invoice['line_items'][$i]) )
				return $this->__trigger_error("System error when attempting to fetch line item record for invoice delete. Please reload window and try again. <!-- Tried fetching item [ {$this->current_invoice['line_items'][$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			$invoice_item[] = $lines->item_hash;

			if ( ! $this->db->query("UPDATE line_items t1
									 SET
										 t1.timestamp = UNIX_TIMESTAMP(),
										 t1.last_change = '{$this->current_hash}',
										 t1.invoice_line_no = 0,
										 t1.invoice_hash = '',
										 t1.income_account_hash = NULL,
										 t1.expense_account_hash = NULL,
										 t1.wip_account_hash = NULL
									 WHERE t1.item_hash = '{$lines->item_hash}'")
			) {

				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
			}

			$accounting->setTransIndex( array(
				'item_hash' =>  $lines->item_hash
			) );

			if ( $lines->current_line['work_order_cost_ext'] )
				$ext_cost = $lines->current_line['work_order_cost_ext'];
			else
				$ext_cost = $lines->current_line['ext_cost'];

			$income_acct = $lines->current_line['income_account_hash'];
			$exp_acct = $lines->current_line['expense_account_hash'];
			$wip_acct = $lines->current_line['wip_account_hash'];

			if ( ! $direct_bill || ( $direct_bill && strtoupper( $lines->current_line['direct_bill_amt'] ) != 'C' ) ) {

				if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], $income_acct, bcmul($lines->current_line['ext_sell'], -1, 2), 'AD', "Invoiced line income reversal: {$lines->current_line['item_product']}") )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
				if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], $exp_acct, bcmul($ext_cost, -1, 2), 'AD', "Invoiced line COGS reversal: {$lines->current_line['item_product']}") )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
				if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], $wip_acct, $ext_cost, 'AD', "Invoiced line WIP reversal: {$lines->current_line['item_product']}") )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

			} elseif ( $direct_bill && $lines->current_line['direct_bill_amt'] == 'C' ) {

				if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], $income_acct, bcmul($lines->current_line['ext_sell'], -1, 2), 'AD', "Invoiced line income reversal: {$lines->current_line['item_product']}") )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
				if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], $exp_acct, bcmul($ext_cost, -1, 2), 'AD', "Invoiced line COGS reversal: {$lines->current_line['item_product']}") )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
			}
		}

		if ( ! $this->db->query("UPDATE customer_invoice t1
								 SET
								 t1.timestamp = UNIX_TIMESTAMP(),
								 t1.last_change = '{$this->current_hash}',
								 t1.deleted = 1,
								 t1.deleted_date = CURDATE()
								 WHERE t1.invoice_hash = '{$this->invoice_hash}'")
		) {

			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
		}

		# Sales tax reversal
		if ( bccomp($this->current_invoice['tax'], 0, 4) == 1 ) {

			$sys = new system_config($this->current_hash);
			if ( bccomp($total_tax, 0, 4) == 1 && ! bccomp($this->current_invoice['tax'], _round($total_tax, 2), 2) ) {

				reset($tax_hash);
				while ( list($tax_acct_hash, $tax_amt) = each($tax_hash) ) {

					unset($tax_account_hash, $tax_state, $tax_local, $tax_rate);
					$r = $this->db->query("SELECT
											   t1.account_hash,
											   t1.rate,
											   t2.state,
											   t2.local
										   FROM tax_collected t1
										   LEFT JOIN tax_table t2 ON t2.tax_hash = t1.tax_hash
										   WHERE t1.invoice_hash = '{$this->invoice_hash}' AND t1.tax_hash = '$tax_acct_hash'");
					if ( $row = $this->db->fetch_assoc($r) ) {

					   $tax_account_hash = $row['account_hash'];
					   $tax_state = $row['state'];
					   $tax_local = $row['local'];
					   $tax_rate = $row['rate'];

					} else
						return $this->__trigger_error("System error when attempting to invoice sales tax for invoice delete. Please reload window and try again. <!-- Tried fetching tax hash [ $tax_acct_hash ] for invoice [ {$this->invoice_hash} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

					if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], $tax_account_hash, bcmul($tax_amt, -1, 4), 'AD', $tax_state . ( $tax_local ? ", $tax_local" : NULL ) . " (" . bcmul($tax_rate, 100, 4) . "%) Tax") )
						return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
				}
			} else
				return $this->__trigger_error("System error when attempting to lookup invoice sales for invoice delete. Please reload window and try again. Current invoice tax =" . $this->current_invoice['tax'] . " Calc tax = " . $total_tax , E_USER_ERROR, __FILE__, __LINE__, 1);
		}

		# Reverse tax collected table
		if ( ! $this->db->query("UPDATE tax_collected t1
								 SET
									 t1.invoice_hash = '',
									 t1.account_hash = NULL
								 WHERE t1.invoice_hash = '{$this->invoice_hash}'")
		) {

			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
		}

		if ( bccomp($total_tax, 0, 4) == 1 ) { # Trac #1614 - Maintain decimal precision when deleting invoices

			$this->current_invoice['amount'] = bcsub($this->current_invoice['amount'], $this->current_invoice['tax'], 2);
			$this->current_invoice['amount'] = bcadd($this->current_invoice['amount'], $total_tax, 4);
		}

		if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], DEFAULT_AR_ACCT, bcmul($this->current_invoice['amount'], -1, 4), 'AD', "Invoice reversal : {$this->current_invoice['invoice_no']}") )
			return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

		# Redispurse the applied deposits - Modified for Trac #779
		$deposit_amt = $this->current_invoice['deposit_applied'];
		if ( $deposit_amt && bccomp($deposit_amt, 0, 2) ) {

			$total_deposit = $deposit_amt;
			$deposits = $this->fetch_customer_deposits($this->proposals->proposal_hash);
			if ( ! $deposits )
				return $this->__trigger_error("System error when attempting to lookup proposal deposits. Please reload window and try again. <!-- Tried fetching proposal deposits [ {$this->proposals->proposal_hash} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			for ( $i = 0; $i < count($deposits); $i++ ) {

				# If we have already applied this deposit somewhere and we still have deposit money left to redistribute
				if ( bccomp($deposit_amt, 0, 2) == 1 && bccomp($deposits[$i]['balance'], $deposits[$i]['amount'], 2) == -1 ) {

					$balance = bcadd($deposits[$i]['balance'], $deposit_amt, 2);
					if ( bccomp($balance, $deposits[$i]['amount'], 2) == 1 ) {

						$balance = $deposits[$i]['amount'];
						$deposit_amt = $deposit_amt - $balance;
					} else
						$deposit_amt = $deposit_amt - $balance;

					if ( ! $this->db->query("UPDATE customer_invoice t1
											 SET
												 t1.timestamp = UNIX_TIMESTAMP(),
												 t1.last_change = '{$this->current_hash}',
												 t1.balance = '$balance'
											 WHERE t1.invoice_hash = '{$deposits[$i]['invoice_hash']}'")
					) {

						return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
					}
				}
			}

			if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], DEFAULT_AR_ACCT, $total_deposit, 'AD', "Invoice deposit A/R reversal: {$this->current_invoice['invoice_no']}") )
				return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
			if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], DEFAULT_CUST_DEPOSIT_ACCT, $total_deposit, 'AD', "Invoice deposit [ Customer Deposits ] reversal : {$this->current_invoice['invoice_no']}") )
				return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
		}

		# Reverse finance charges if they've been applied
		if ( bccomp($this->current_invoice['finance_charges'], 0) == 1 ) {

			if ( list($finance_charges, $total_finance) = $this->fetch_finance_charges($this->invoice_hash) ) {

				for ( $i = 0; $i < count($finance_charges); $i++ ) {

					if ( ! $this->fetch_finance_charge($finance_charges[$i]['charge_hash']) )
						return $this->__trigger_error("System error when attempting to fetch finance charge record. Please reload window and try again. <!-- Tried fetching xyz [ {$finance_charges[$i]['charge_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

					if ( ! $this->db->query("UPDATE finance_charges t1
											 SET
											 	 t1.timestamp() = UNIX_TIMESTAMP(),
											 	 t1.last_change = '{$this->current_hash}',
												 t1.deleted = 1,
												 t1.deleted_date = CURDATE()
											 WHERE t1.charge_hash = '{$this->charge_hash}'")
					) {

						return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
					}

					if ( ! $accounting->exec_trans($audit_id, $this->current_invoice['invoice_date'], $this->current_finance_charge['account_hash'], bcmul($this->current_finance_charge['amount'], -1, 2), 'AD', 'Finance charges reversal') )
						return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
				}
			}
		}

		if ( ! $accounting->end_transaction() )
			return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

		$this->db->end_transaction();

		$r = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
							   FROM line_items t1
							   WHERE t1.proposal_hash = '{$this->proposals->proposal_hash}' AND t1.invoice_hash != ''");
		if ( ! $this->db->result($result, 0, 'Total') ) {

			if ( ! $this->db->query("UPDATE proposals t1
								     SET
									     t1.status = 1,
									     t1.proposal_status = 'B',
									     t1.overhead_rate = '0',
									     t1.overhead_type = NULL
								     WHERE t1.proposal_hash = '{$this->proposals->proposal_hash}'")
			) {

				$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__	);
			}
		}
		
		$this->db->query("UPDATE `commissions_paid`
				SET `paid_in_full` = 0
				WHERE `proposal_hash` = '{$this->proposals->proposal_hash}'");

		$this->content['action'] = 'close';
		$this->content['page_feedback'] = 'Your invoice had been deleted and all items have been reverted to un-invoiced.';
		$this->content['jscript_action'] = "agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'proposal_invoices', 'otf=1', 'proposal_hash={$this->proposals->proposal_hash}', 'new_invoice=rm');window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->proposals->proposal_hash}\', \'otf=1\');', 1500);";

		return;
	}

	function doit_rcv_pmt() {

		$invoice_hash = $_POST['invoice_hash'];
		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];

		if ( $_POST['integrate'] ) { # Set flush to 2 to return a string from trigger error if call originated from integration, otherwise 1

			$flush = 2;
			$integrate = true;
		} else
			$flush = 1;

		if ( $_POST['delete'] )
			$submit_btn = 'rcv_btn_rm';
		else
			$submit_btn = 'rcv_btn';

		if ( $payment_hash = $_POST['payment_hash'] ) {

			if ( ! $this->fetch_payment_record($payment_hash) )
				return $this->__trigger_error("System error encountered when attempting to fetch payment record for edit. Please reload window and try again. <!-- Tried to fetch payment [ $payment_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

			if ( $this->current_payment['applied_from'] )
				$current_payment_applied_from = $this->current_payment['applied_from'];
		}

		if ( ! $this->fetch_invoice_record($invoice_hash) )
			return $this->__trigger_error("System error encountered when attempting to lookup customer invoice for edit. Please reload window and try again. <!-- Tried to fetch invoice [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

		$payment_account_hash = $_POST['payment_account_hash'];
		$check_no = $_POST['check_no'];
		$receipt_date = $_POST['receipt_date'];
		$receipt_amount = preg_replace('/[^-.0-9]/', "", $_POST['amount']);
		$comments = $_POST['comments'];
		$receive_from_unapplied = $_POST['receive_from_unapplied'];
		$type = $_POST['type'];

		if ( ! $receive_from_unapplied && ! $payment_account_hash && ! $current_payment_applied_from ) {

			if ( $_POST['delete'] )
				$feedback = "This receipt is missing the account that it was received into. We're sorry, but this action requires you to contact DealerChoice support.";
			else
				$feedback = "You have not indicated in which account you want to receive this payment.<br />Make sure you have defined at least 1 checking account. You can do this by checking the 'Will you write checks from this account' box from within the chart of accounts 'General' tab.";

			return $this->__trigger_error($feedback, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
		}

		// Receive from a previously received unapplied deposit
		if ( ! $receive_from_unapplied && ! $_POST['delete'] && ( bccomp(0, $receipt_amount, 2) == 1 || ! bccomp(0, $receipt_amount, 2) || ! $check_no ) ) {

			if ( ! $check_no ) {

				$this->set_error_msg("Please make sure you have entered a check no.");
				$this->set_error("err1{$this->popup_id}");
			}
			if ( bccomp(0, $receipt_amount, 2) == 1 || ! bccomp(0, $receipt_amount, 2) ) {

				$this->set_error_msg("Please check that the payment amount provided is non-negative and greater than zero.");
				$this->set_error("err3{$this->popup_id}");
			}

			return $this->__trigger_error(NULL, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
		}

		$payment_currency = ($this->payment_hash ? $this->current_payment['currency'] : $_POST['currency']);
		if ( $this->current_invoice['currency'] || $payment_currency || ( $this->current_invoice['currency'] && $receive_from_unapplied ) ) {

			$sys = new system_config($this->current_hash);
			if ( ! $sys->home_currency )
				return $this->__trigger_error("The default home currency has not been defined in your system configuration! This invoice was created with multiple currencies enabled and cannot be edited without multiple currencies enabled. Please check with your system administrator.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

			if ( $this->current_invoice['currency'] ) {

				$invoice_currency = $this->current_invoice['currency'];
				$invoice_rate = $this->current_invoice['exchange_rate'];
				$currency_account_hash = $this->current_invoice['currency_account'];
			}

			if ( $payment_currency || ( ! $payment_currency && $this->current_invoice['currency'] && $receive_from_unapplied ) ) {

				if ( ! $payment_currency && $this->current_invoice['currency'] && $receive_from_unapplied ) {

					if ( $type == 'apply_credits' ) {

						$payment_currency = $sys->home_currency['code'];
						$payment_rate = $sys->home_currency['rate'];
					} else {

						$tmp_invoice_obj = new customer_invoice($this->current_hash);
						if ( ! $tmp_invoice_obj->fetch_invoice_record($receive_from_unapplied) )
							return $this->__trigger_error("System error encountered when attempting to lookup unapplied deposit. Please reload window and try again. <!-- Tried to fetch invoice [ $receive_from_unapplied ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						$payment_currency = $tmp_invoice_obj->current_invoice['currency'];
						$payment_rate = $tmp_invoice_obj->current_invoice['exchange_rate'];
					}

				} else {

					if ( $this->payment_hash )
						$payment_rate = $this->current_payment['exchange_rate'];
					else {

						if ( ! $sys->fetch_currency($payment_currency) )
							return $this->__trigger_error("Unable to find a currency exchange rule/rate for the payment currency. Please check your currency exchange tables and ensure you have set up an exchange rule for the payment currency!", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

						$payment_rate = $sys->current_currency['rate'];
					}
				}
			}
		}

		$accounting = new accounting($this->current_hash);

		if ( $payment_hash ) {

			$payment_currency = $this->current_payment['currency'];
			$payment_rate = $this->current_payment['rate'];

			$adjustment_date = $this->current_payment['receipt_date']; # Set the adjustment date to abide to GAAP compliance

			if ( $accounting->period_ck( $adjustment_date ) )
				return $this->__trigger_error($accounting->closed_err, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

			if ($this->db->start_transaction() === false)
				return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

			if ( $_POST['delete'] ) {

				$accounting->start_transaction( array(
					'ar_invoice_hash'  =>  $this->invoice_hash,
					'proposal_hash'    =>  $this->current_invoice['proposal_hash'],
					'customer_hash'    =>  $this->current_invoice['customer_hash'],
					'ar_payment_hash'  =>  $payment_hash,
					'currency'         =>  $payment_currency,
					'exchange_rate'    =>  $payment_rate
				) );

				$audit_id = $accounting->audit_id();
				$submit_btn = 'rcv_btn_rm';

				$this->db->query("UPDATE `customer_payment`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `deleted` = 1 , `deleted_date` = '".date("Y-m-d")."'
								  WHERE `payment_hash` = '".$this->payment_hash."'");
				if ($this->db->db_error)
					return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				$balance = bcadd($this->current_invoice['balance'], $this->current_payment['receipt_amount'], 2);

				if ($this->current_payment['currency'] && $this->current_payment['exchange_rate'] && bccomp($this->current_payment['currency_adjustment'], 0, 2) != 0)
					$balance = bcadd($balance, $this->current_payment['currency_adjustment'], 2);

				$this->db->query("UPDATE `customer_invoice`
								  SET `paid_in_full` = 0 , `balance` = '$balance'
								  WHERE `invoice_hash` = '".$this->invoice_hash."'");
				if ($this->db->db_error)
					return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				if ( $this->current_payment['applied_from'] ) { # If this payment was received from an unapplied deposit

					if ( $this->current_payment['applied_type'] == 'C' ) {

						$credits = new customer_credits($this->current_hash);
						if ( ! $credits->fetch_credit_record($this->current_payment['applied_from']) )
							return $this->__trigger_error("Unable to fetch credit record for edit. Please try again.", E_USER_ERROR, __FILE__, __LINE__, $flush);

						$accounting->setTransIndex( array(
							'ar_credit_hash'    =>  $credits->credit_hash
						) );

						$refund_amt = bcadd($credits->current_credit['balance'], $this->current_payment['receipt_amount'], 2);
						$this->db->query("UPDATE `customer_credit`
										  SET `balance` = '$refund_amt'
										  WHERE `credit_hash` = '".$credits->credit_hash."'");
						if ($this->db->db_error)
							return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						if ($accounting->exec_trans($audit_id, $adjustment_date, DEFAULT_AR_ACCT, ($this->current_payment['receipt_amount'] * -1), 'AD', 'Delete customer payment - refund to customer credit.') === false)
							return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);


						// Delete customer credit if it was created from an invoice. Added for Trac #1119
						if ($credits->current_credit['invoice_hash']) {
							unset($_POST, $args);
							$args['submit_btn'] = $submit_btn;
							$args['credit_hash'] = $credits->credit_hash;
							$args['method'] = 'rm';

							if ($credits->doit_customer_credit($args) == false)
								return $this->__trigger_error($customer_credits->accounting->err['errno'].' - '.$customer_credits->accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

							$feedback = "Your receipt has been deleted along with the applied customer credit.";
						}
					} else {
						$tmp = new customer_invoice($this->current_hash);
						if ($tmp->fetch_invoice_record($this->current_payment['applied_from']) === false)
							return $this->__trigger_error("Unable to fetch deposit record for edit. Please try again.", E_USER_ERROR, __FILE__, __LINE__, $flush);

						$refund_amt = bcadd($tmp->current_invoice['balance'], $this->current_payment['receipt_amount'], 2);
						$this->db->query("UPDATE `customer_invoice`
										  SET `balance` = '$refund_amt'
										  WHERE `invoice_hash` = '".$this->current_payment['applied_from']."'");
						if ($this->db->db_error)
							return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						if ($accounting->exec_trans($audit_id, $adjustment_date, DEFAULT_CUST_DEPOSIT_ACCT, $this->current_payment['receipt_amount'], 'AD', 'Delete customer payment - return to unapplied') === false)
							return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
					}
				} else {
					if ($accounting->exec_trans($audit_id, $adjustment_date, $this->current_payment['account_hash'], bcmul($this->current_payment['receipt_amount'], -1, 2), 'AD', "Delete customer payment") === false)
						return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);



					if ($this->current_payment['currency'] && $this->current_payment['exchange_rate'] && bccomp($this->current_payment['currency_adjustment'], 0, 2) != 0) {
						if ($accounting->exec_trans($audit_id, $adjustment_date, DEFAULT_AR_ACCT, $this->current_payment['currency_adjustment'], 'AD', 'Gain/Loss on currency exchange') === false)
							return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
						if ($accounting->exec_trans($audit_id, $adjustment_date, $this->current_invoice['currency_account'], ($accounting->account_action($this->current_invoice['currency_account']) == 'CR' ? $this->current_payment['currency_adjustment'] : bcmul($this->current_payment['currency_adjustment'], -1)), 'AD', 'Gain/Loss on currency exchange') === false)
							return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
					}
				}
				if ($accounting->exec_trans($audit_id, $adjustment_date, DEFAULT_AR_ACCT, $this->current_payment['receipt_amount'], 'AD', "Delete customer payment") === false)
					return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				$feedback = ($feedback ? $feedback : "Your receipt has been deleted".($this->current_payment['applied_from'] ? " and refunded to the ".($this->current_payment['applied_type'] == 'D' ? "customer's unapplied deposits" : "customer's credit file") : NULL).".");

			} else {

				$submit_btn = 'rcv_btn';

				$currency_adj = 0;
				if ( $this->current_payment['currency'] && $this->current_payment['exchange_rate'] ) {

					$orig_receipt_amount = $receipt_amount;
					$receipt_amount = currency_exchange($receipt_amount, $this->current_payment['exchange_rate'], 1, 6); // Receipt amount per payment rate

					if ( bccomp( _round($receipt_amount, 2), $this->current_payment['receipt_amount'], 2) ) {

						$adjusted_receipt_amount = currency_exchange($orig_receipt_amount, $invoice_rate, 1, 6); // Receipt amount per invoice rate
						$currency_adj = _round( bcsub($adjusted_receipt_amount, $receipt_amount, 6), 2);
						if ( bccomp($currency_adj, $this->current_payment['currency_adjustment'], 2) )
							$adj_currency_adj = bcsub($this->current_payment['currency_adjustment'], $currency_adj, 2);
					}
				}

				bccomp($this->current_payment['receipt_amount'], $receipt_amount, 2) ? $sql[] = " receipt_amount = '$receipt_amount' " : NULL;
				if ( $this->current_payment['receipt_date'] != $receipt_date ) {

					if ( $accounting->period_ck( $receipt_date ) ) //Make sure the period is open
						return $this->__trigger_error($accounting->closed_err, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					$sql[] = " receipt_date = '$receipt_date' ";

					# GAAP compliance, reverse orig trans if date has changed
					if ( ! $this->current_payment['applied_from'] ) {

						if ( $accounting->period_ck( $this->current_payment['receipt_date'] ) )
							return $this->__trigger_error("Unable to proceed, period has been closed. In order to modify date of receipt, the period in which the original receipt was posted must not be closed. Please re-open this period or contact a system administrator.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						$accounting->start_transaction( array(
							'ar_invoice_hash'  =>  $this->invoice_hash,
							'proposal_hash'    =>  $this->current_invoice['proposal_hash'],
							'customer_hash'    =>  $this->current_invoice['customer_hash'],
							'ar_payment_hash'  =>  $payment_hash,
							'currency'         =>  $payment_currency,
							'exchange_rate'    =>  $payment_rate
						) );

						$audit_id = $accounting->audit_id();

						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['receipt_date'], $this->current_payment['account_hash'], bcmul($this->current_payment['receipt_amount'], -1, 2), 'AD', "Customer payment reversal - Receipt date changed by user") )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['receipt_date'], DEFAULT_AR_ACCT, $this->current_payment['receipt_amount'], 'AD', "Customer payment reversal - Receipt date changed by user") )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						if ( ! $accounting->end_transaction() )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						$date_reverse = true;

					}

					$adjustment_date = $receipt_date;
				}

				$accounting->start_transaction( array(
					'ar_invoice_hash'  =>  $this->invoice_hash,
					'proposal_hash'    =>  $this->current_invoice['proposal_hash'],
					'customer_hash'    =>  $this->current_invoice['customer_hash'],
					'currency'         =>  $payment_currency,
					'exchange_rate'    =>  $payment_rate
				) );

				$audit_id = $accounting->audit_id();

				( $this->current_payment['check_no'] != $check_no ? $sql[] = "`check_no` = '$check_no'" : NULL);
				( strlen( base64_encode( stripslashes($this->current_payment['comments'] ) ) ) != strlen( base64_encode($comments) ) ? $sql[] = "`comments` = '" . addslashes($comments) . "'" : NULL);

				if ( $sql ) {

					if ( bccomp($this->current_payment['receipt_amount'], $receipt_amount, 2) || $date_reverse ) {

						if ( $date_reverse )
							$adj = $receipt_amount;
						else
							$adj = bcsub( _round($receipt_amount, 2), $this->current_payment['receipt_amount'], 2);

						$balance = bcsub($this->current_invoice['balance'], bcsub( _round($receipt_amount, 2), $this->current_payment['receipt_amount'], 2), 2);
						if ( $this->current_payment['currency'] && $this->current_payment['exchange_rate'] && bccomp($adj_currency_adj, 0, 2) )
							$balance = bcadd($balance, $adj_currency_adj, 2);

						$accounting->setTransIndex( array(
							'ar_payment_hash'    =>  $this->payment_hash
						) );

						if ( $this->current_payment['applied_from'] ) {

							if ( $this->current_payment['applied_type'] == 'C' ) {

								$credits = new customer_credits($this->current_hash);
								if ( ! $credits->fetch_credit_record($this->current_payment['applied_from']) )
									return $this->__trigger_error("System error encountered when attempting to fetch credit record for edit. Please reload window and try again. <!-- Tried fetching [ {$this->current_payment['applied_from']} ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

								if ( bccomp($adj, $credits->current_credit['balance'], 2) == 1 )
									return $this->__trigger_error("The receipt amount you entered exceeds the available balance remaining on the customer credit that the current receipt was applied from.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

								$accounting->setTransIndex( array(
									'ar_credit_hash'    =>  $credits->credit_hash
								) );
								$new_balance = bcadd($credits->current_credit['balance'], bcmul($adj, -1, 2), 2);

								$this->db->query("UPDATE `customer_credit`
												  SET `proposal_hash` = '".$this->current_invoice['proposal_hash']."', `invoice_hash` = '".$this->current_invoice['invoice_hash']."', `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `balance` = '$new_balance'
												  WHERE `credit_hash` = '".$credits->credit_hash."'");
								if ($this->db->db_error)
									return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

								if ($accounting->exec_trans($audit_id, $adjustment_date, DEFAULT_AR_ACCT, $adj, 'AD', 'Customer receipt adjustment - reverted to customer credit') === false)
									return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
							} else {

								$tmp_obj = new customer_invoice($this->current_hash);
								if ( ! $tmp_obj->fetch_invoice_record($this->current_payment['applied_from']) )
									return $this->__trigger_error("System error encountered when attempting to fetch deposit record for edit. Please reload window and try again. <!-- Tried fetching invoice [ {$this->current_payment['applied_from']} ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

								if ( bccomp($adj, $tmp_obj->current_invoice['balance'], 2) == 1 )
									return $this->__trigger_error("The receipt amount you entered exceeds the available balance remaining on the deposit that this receipt was applied from.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

								$new_balance = bcadd($tmp_obj->current_invoice['balance'], bcmul($adj, -1, 2), 2);

								$this->db->query("UPDATE `customer_invoice`
												  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `balance` = '$new_balance'
												  WHERE `invoice_hash` = '".$this->current_payment['applied_from']."'");
								if ($this->db->db_error)
									return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

								if ($accounting->exec_trans($audit_id, $adjustment_date, DEFAULT_CUST_DEPOSIT_ACCT, ($adj * -1), 'AD', 'Customer receipt adjustment - difference refunded to unapplied') === false)
									return $this->__trigger_error($accounting->err['errno'].' - '.$accounting->err['err'], E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
							}

						} else {

							if ( ! $accounting->exec_trans($audit_id, $adjustment_date, $this->current_payment['account_hash'], $adj, 'AD', 'Customer receipt adjustment') )
								return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

							if ($this->current_payment['currency'] && $this->current_payment['exchange_rate'] && bccomp($adj_currency_adj, 0, 2) ) {
								if ( ! $accounting->exec_trans($audit_id, $adjustment_date, $this->current_invoice['currency_account'], ($accounting->account_action($this->current_invoice['currency_account']) == 'CR' ? $adj_currency_adj : bcmul($adj_currency_adj, -1)), 'AD', 'Gain/Loss on currency exchange') )
									return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
								if ( ! $accounting->exec_trans($audit_id, $adjustment_date, DEFAULT_AR_ACCT, $adj_currency_adj, 'AD', 'Gain/Loss on currency exchange') )
									return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

								$sql[] = "`currency_adjustment` = '$currency_adj'";
							}
						}

						$this->db->query("UPDATE `customer_invoice`
										  SET ".($balance > 0 ? "`paid_in_full` = 0 , " : NULL)." `balance` = '$balance'
										  WHERE `invoice_hash` = '".$this->invoice_hash."'");
						if ($this->db->db_error)
							return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

						if ( ! $accounting->exec_trans($audit_id, $adjustment_date, DEFAULT_AR_ACCT, ($adj * -1), 'AD', 'Customer receipt adjustment') )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					}

					$this->db->query("UPDATE `customer_payment`
									  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , ".implode(" , ", $sql)."
									  WHERE `payment_hash` = '".$this->payment_hash."'");
					if ($this->db->db_error)
						return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					$feedback = "Your receipt has been updated.";
				} else
					$feedback = "No changes have been made.";
			}

			if ( ! $accounting->end_transaction() )
				return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			$this->db->end_transaction();

		} else { # Receiving a new invoice payment

			$submit_btn = 'rcv_btn';
			if ( $accounting->period_ck($receipt_date) )
				return $this->__trigger_error($accounting->closed_err, E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			if ( ! $this->db->start_transaction() )
				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			$accounting->start_transaction( array(
				'ar_invoice_hash'  =>  $this->invoice_hash,
				'proposal_hash'    =>  $this->current_invoice['proposal_hash'],
				'customer_hash'    =>  $this->current_invoice['customer_hash']
			) );

			$audit_id = $accounting->audit_id();

			if ( $receive_from_unapplied ) {

				unset($payment_account_hash);
				if ( ! $type )
					return $this->__trigger_error("Unable to identify record type (i.e. deposit/credit) to be applied to the current payment. Please reload window and try again. <!-- Tried fetching [ $receive_from_unapplied ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

				if ( $type == 'apply_credits' ) {

					$credits = new customer_credits($this->current_hash);
					if ( ! $credits->fetch_credit_record($receive_from_unapplied) )
						return $this->__trigger_error("System error encountered when attempting to lookup credit record. Please reload window and try again. <!-- Tried fetching [ $receive_from_unapplied ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					$accounting->setTransIndex( array(
						'ar_credit_hash'    =>  $credits->credit_hash
					) );

					$check_no = $credits->current_credit['credit_no'];
					if ( bccomp($receipt_amount, 0, 2) != 1 || bccomp($receipt_amount, $credits->current_credit['balance'], 2) == 1 )
						return $this->__trigger_error( ( bccomp($receipt_amount, 0, 2) != 1 ? "Please indicate the receipt amount to apply to this invoice. Receipt amount must be greater than zero." : "The receipt amount you indicated exceeds the amount available (\$" . number_format($credits->current_credit['balance'], 2) . ") on this customer credit." ), E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

					$remaining_amt = bcsub($credits->current_credit['balance'], $receipt_amount, 2);
					if ( ! $accounting->exec_trans($audit_id, $receipt_date, DEFAULT_AR_ACCT, $receipt_amount, 'AD', 'Applying customer credit to invoice.') )
						return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					if ( ! $accounting->exec_trans($audit_id, $receipt_date, DEFAULT_AR_ACCT, ($receipt_amount * -1), 'AD', 'Applying customer credit to invoice.') )
						return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

					$this->db->query("UPDATE `customer_credit`
									  SET `proposal_hash` = '".$this->current_invoice['proposal_hash']."', `invoice_hash` = '".$this->current_invoice['invoice_hash']."', `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `balance` = '$remaining_amt'
									  WHERE `credit_hash` = '".$credits->credit_hash."'");
					if ($this->db->db_error)
						return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
						
					$this->db->query("UPDATE `customer_credit_expenses`
									  SET `item_hash` = '".$this->current_invoice['item_hash']."'
									  WHERE `credit_hash` = '".$credits->credit_hash."'");
					if ($this->db->db_error)
						return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					$applied_type = 'C';

				} else {

					$tmp_invoice_obj = new customer_invoice($this->current_hash);
					if ( ! $tmp_invoice_obj->fetch_invoice_record($receive_from_unapplied) )
						return $this->__trigger_error("System error encountered when attempting to lookup deposit record for payment application. Please reload window and try again. <!-- Tried fetching invoice [ $receive_from_unapplied ] -->", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					$check_no = $tmp_invoice_obj->current_invoice['payments'][0]['check_no'];

					if ( bccomp($receipt_amount, 0, 2) != 1 || bccomp($receipt_amount, $tmp_invoice_obj->current_invoice['balance'], 2) == 1 )
						return $this->__trigger_error( ( bccomp($receipt_amount, 0, 2) ? "Please indicate the receipt amount to apply to this invoice." : "The receipt amount you indicated exceeds the amount available (\$" . number_format($tmp_invoice_obj->current_invoice['balance'], 2) . ") on this proposal deposit." ), E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

					$remaining_amt = bcsub($tmp_invoice_obj->current_invoice['balance'], $receipt_amount, 2);
					if ( ! $accounting->exec_trans($audit_id, $receipt_date, DEFAULT_CUST_DEPOSIT_ACCT, ($receipt_amount * -1), 'AD', 'Receive payment from unapplied') )
						return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					if ( ! $accounting->exec_trans($audit_id, $receipt_date, DEFAULT_AR_ACCT, ($receipt_amount * -1), 'AD', 'Receive payment from unapplied') )
						return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

					$this->db->query("UPDATE `customer_invoice`
									  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `balance` = '$remaining_amt'
									  WHERE `invoice_hash` = '".$tmp_invoice_obj->invoice_hash."'");
					if ($this->db->db_error)
						return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

					$applied_type = 'D';
				}

			} else {

				$currency_adj = 0;
				if ( $invoice_currency && ( $payment_currency != $sys->home_currency['code'] ) ) {

					$orig_receipt_amount = $receipt_amount;

					$receipt_amount = currency_exchange($receipt_amount, $sys->current_currency['rate'], 1, 6); # Receipt amount per current rate
					$adjusted_receipt_amount = currency_exchange($orig_receipt_amount, $invoice_rate, 1, 6); # Receipt amount per invoice rate

					$currency_adj = _round( bcsub($adjusted_receipt_amount, $receipt_amount, 6), 2);
					$receipt_amount = _round($receipt_amount, 2);
				}
			}
			
		
			$payment_hash = rand_hash('customer_payment', 'payment_hash');
			
			$accounting->setTransIndex( array(
				'ar_payment_hash'   =>  $payment_hash
			) );

			if ( ! $this->db->query("INSERT INTO customer_payment
									 (
		    							 timestamp,
		    							 last_change,
		    							 payment_hash,
		    							 applied_from,
		    							 applied_type,
		    							 invoice_hash,
		    							 receipt_amount,
		    							 currency_adjustment,
		    							 account_hash,
		    							 receipt_date,
		    							 check_no,
		    							 comments,
		    							 currency,
		    							 exchange_rate
		    						 )
									 VALUES
									 (
										 UNIX_TIMESTAMP(),
										 '{$this->current_hash}',
										 '$payment_hash', " .
										 ( $receive_from_unapplied ?
			    							 "'$receive_from_unapplied'" : "NULL"
										 ) . ", " .
										 ( $applied_type ?
			    							 "'$applied_type'" : "NULL"
										 ) . ",
										 '$invoice_hash',
										 '$receipt_amount',
										 '$currency_adj',
										 '$payment_account_hash',
										 '$receipt_date',
										 '$check_no',
										 '$comments', " .
										 ( $invoice_currency && ( $payment_currency != $sys->home_currency['code'] ) ?
			    							 "'$payment_currency'" : "NULL"
			    						 ) . ", " .
			    						 ( $invoice_currency && ( $payment_currency != $sys->home_currency['code'] ) ?
			        						 "'$payment_rate'" : "0"
			    						 ) . "
		    						  )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
            }
            
             if ( ! $this->db->query("INSERT INTO customer_credit_applied
                                         VALUES
                                         (
                                             NULL,
                                             '$payment_hash',
                                             '$credits->credit_hash',
                                             '$receipt_amount',
                                             0,
                                             0
                                         )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }
            

			$balance = bcsub($this->current_invoice['balance'], bcadd($receipt_amount, $currency_adj, 2), 2);
			$this->db->query("UPDATE `customer_invoice`
							  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `balance` = '$balance' ".($balance <= 0 ? ", `paid_in_full` = 1" : NULL)."
							  WHERE `invoice_hash` = '".$this->invoice_hash."'");
			if ($this->db->db_error)
				return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);

			if ( ! $receive_from_unapplied ) {

				if ( ! $accounting->exec_trans($audit_id, $receipt_date, $payment_account_hash, $receipt_amount, 'CR', 'Customer receipt') )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				if ( ! $accounting->exec_trans($audit_id, $receipt_date, DEFAULT_AR_ACCT, ($receipt_amount * -1), 'CR', 'Customer receipt') )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			if ( $invoice_currency && bccomp($currency_adj, 0, 2) ) {

				$curr_adj_cr = $currency_adj;
				if ( $accounting->account_action($currency_account_hash) == 'CR' )
					$curr_adj_cr = bcmul($curr_adj_cr, -1, 2);

				if ( ! $accounting->exec_trans($audit_id, $receipt_date, DEFAULT_AR_ACCT, bcmul($currency_adj, -1), 'CR', 'Gain/Loss on currency exchange') )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				if ( ! $accounting->exec_trans($audit_id, $receipt_date, $currency_account_hash, $curr_adj_cr, 'CR', 'Gain/Loss on currency exchange') )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			if ( ! $accounting->end_transaction() )
				return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			$this->db->end_transaction();

			if ( bccomp($balance, 0, 2) != 1 ) {

				$invoice_hash = $this->invoice_hash;
				$lines = new line_items($this->proposal_hash);
				$line_items = $lines->fetch_line_items_short();

				for ( $i = 0; $i < count($line_items); $i++ ) {

					if ( $line_items[$i]['status'] && $line_items[$i]['invoice_hash'] != $invoice_hash ) {

						if ( $line_items[$i]['invoice_hash'] ) {

							$this->fetch_invoice_record($line_items[$i]['invoice_hash']);
							if ( ! $this->current_invoice['paid_in_full'] ) {

								$not_final = $line_items[$i]['invoice_hash'];
								break;
							}

						} else
							break;

					}
				}

				if ( ! $not_final ) {

					$this->db->query("UPDATE `proposals`
									  SET `invoiced` = 1
									  WHERE `proposal_hash` = '{$this->proposal_hash}'");
				}
			}

			$feedback = "Your receipt has been recorded.";
		}

		if ( $integrate )
			return $feedback;
		else {

			$this->content['action'] = 'close';
			$this->content['page_feedback'] = $feedback;
			$this->content['jscript_action'] = "agent.call('customer_invoice', 'sf_loadcontent', 'cf_loadcontent', 'proposal_invoices', 'otf=1', 'proposal_hash={$this->proposals->proposal_hash}');window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->proposals->proposal_hash}\', \'otf=1\');', 1500)";
		}
	}












}
?>