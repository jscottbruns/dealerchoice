<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
//subs.class.php
class customers extends AJAX_library {

	public $total;
	public $total_contacts;
	public $total_locations;

	public $customer_hash;
	public $contact_hash;
	public $location_hash;

	public $customer_info = array();
	public $customer_contacts = array();
	public $customer_locations = array();

	public $current_customer;
	public $current_contact;
	public $current_location;

	public $ajax_vars;

	private $form;
	private $check;

    public $trans_error = array('default_ar'           =>  "Your default Accounts Receivable account has not been defined.",
                                'default_ap'           =>  "Your default Accounts Payable chart of account has not been defined.",
                                'misc_acct'            =>  "One of the chart of accounts found within the current transaction has not been properly defined. Please ensure that any accounts used within the current transaction have been properly defined.",
                                'default_cust_dep'     =>  "Your default Customer Deposits chart of account has not been properly defined.");

	function customers($passedHash=NULL) {
		global $db;

		$this->form = new form;
		$this->db =& $db;

		if ($passedHash)
			$this->current_hash = $passedHash;
		else
			$this->current_hash = $_SESSION['id_hash'];

		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);
		if ($_POST['active_search'])
            $result = $this->db->query("SELECT `total` AS Total
                                        FROM `search`
                                        WHERE `search_hash` = '".$_POST['active_search']."'");
		else
			$result = $this->db->query("SELECT COUNT(*) AS Total
									    FROM `customers`
									    WHERE customers.deleted = 0");

		$this->total = $this->db->result($result, 0, 'Total');
        $this->cust = new custom_fields(get_class($this));
	}

	function __destruct() {
		$this->content = '';
	}

	function fetch_customers($start, $order_by=NULL, $order_dir=NULL) {
		$end = MAIN_PAGNATION_NUM;

		if ($this->active_search) {
			$result = $this->db->query("SELECT `query` , `total` , `search_str`
								  		FROM `search`
								  		WHERE `search_hash` = '".$this->active_search."'");
			$row = $this->db->fetch_assoc($result);
			$this->total = $row['total'];
			$sql = base64_decode($row['query']);
			$this->search_vars = $this->p->load_search_vars($row['search_str']);
		}

		$result = $this->db->query("SELECT customers.customer_hash , customers.customer_name , customers.city ,
		                           customers.state , customers.account_no
							  		FROM `customers` ".($this->search_vars['discount_id'] ? "
		                            LEFT JOIN discounting ON discounting.customer_hash = customers.customer_hash AND discounting.deleted = 0" : NULL)."
		                            LEFT JOIN locations ON locations.entry_hash = customers.customer_hash AND locations.entry_type = 'c'
							  		LEFT JOIN proposals ON customers.customer_hash = proposals.customer_hash
		                            WHERE ".($sql ? $sql." AND " : NULL)."customers.deleted = 0
									GROUP BY customers.customer_hash".($order_by ? "
							  		ORDER BY $order_by ".($order_dir ? $order_dir : "ASC") : NULL)."
							  		LIMIT $start , $end");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->customer_info, array_merge($row, $this->calculate_totals($row['customer_hash'])));


	}

	function fetch_master_record($customer_hash, $edit=false) {
		if (!$customer_hash)
			return;

		$result = $this->db->query("SELECT customers.* ,
									(SUM(CASE
										WHEN customer_invoice.type = 'I' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN customer_invoice.type = 'D' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END)
									) AS balance_total ,
									(SUM(CASE
										WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) < ".ACCOUNT_AGING_SCHED1." AND customer_invoice.type = 'I' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) < ".ACCOUNT_AGING_SCHED1." AND customer_invoice.type = 'D' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END)
									) AS balance_current ,
									(SUM(CASE
										WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND customer_invoice.type = 'I' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND customer_invoice.type = 'D' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END)
									) AS balance_sched1 ,
									(SUM(CASE
										WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND customer_invoice.type = 'I' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND customer_invoice.type = 'D' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END)
									) AS balance_sched2 ,
									(SUM(CASE
										WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) >= ".ACCOUNT_AGING_SCHED3." AND customer_invoice.type = 'I' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) >= ".ACCOUNT_AGING_SCHED3." AND customer_invoice.type = 'D' AND customer_invoice.balance != 0
											THEN customer_invoice.balance
											ELSE 0
										END)
									) AS balance_sched3
								   FROM `customers`
								   LEFT JOIN `customer_invoice` ON customer_invoice.customer_hash = customers.customer_hash AND customer_invoice.deleted = 0
								   WHERE customers.customer_hash = '$customer_hash'
								   GROUP BY customer_invoice.customer_hash");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_customer = $row;
			$this->customer_hash = $this->current_customer['customer_hash'];

			$result = $this->db->query("SELECT COUNT(*) AS Total
									    FROM `customer_contacts`
									    WHERE `customer_hash` = '".$this->current_customer['customer_hash']."' AND `deleted` = 0");
			$this->total_contacts = $this->db->result($result);

			$result = $this->db->query("SELECT COUNT(*) AS Total
								  		FROM `locations`
								  		WHERE `entry_hash` = '".$this->current_customer['customer_hash']."' && `entry_type` = 'c' AND `deleted` = 0");
			$this->total_locations = $this->db->result($result);

			$this->discounting = new discounting($this->customer_hash, 'C');
			if ($edit)
				$this->lock = $this->content['row_lock'] = $this->p->lock("customers", $this->customer_hash, $this->popup_id);

			return true;
		}

		return false;
	}

	function fetch_contacts() {

        if ( $param = func_get_arg(0) ) {

        	$start = $param['start_from'];
			$end = $param['end'];
			$order_by = $param['order_by'];
			$order_dir = $param['order_dir'];
			$propose_to_location = $param['propose_to_location'];
        }

		if ( strlen($order_by) == 32 && ! $propose_to_location ) {

            $propose_to_location = $order_by;
            unset($order_by, $order_dir);
		}

        if ( ! $end )
			$end = SUB_PAGNATION_NUM;

        $this->customer_contacts = array();
        $_total = 0;

		$result = $this->db->query("SELECT COUNT(*) AS Total
							        FROM customer_contacts
							        WHERE customer_hash = '{$this->customer_hash}' AND deleted = 0");
		$this->total_contacts = $this->db->result($result, 0, 'Total');

		if ( ! $this->total_contacts )
    		return 0;

        $result = $this->db->query("SELECT " .
                                    ( $propose_to_location ?
                                        "t1_1.ownership, " : NULL
                                    ) . "
                                    t1.*
                                    FROM customer_contacts t1 " .
                                    ( $propose_to_location ?
                                        "LEFT JOIN location_contacts t2 ON t2.contact_hash = t1.contact_hash
                                        LEFT JOIN
                                        (
                                            SELECT
                                                t1.contact_hash,
                                                COUNT( t1.location_hash ) AS ownership
                                            FROM location_contacts t1
                                            GROUP BY t1.contact_hash
                                        ) AS t1_1 ON t1_1.contact_hash = t1.contact_hash" : NULL
                                    ) . "
                                    WHERE " .
                                    ( $propose_to_location ?
                                        "
                                        (
	                                        (
	                                            t1.customer_hash = '{$this->customer_hash}' AND t2.location_hash = '$propose_to_location'
	                                        )
	                                        OR
	                                        (
	                                            t1.customer_hash = '{$this->customer_hash}'
	                                            AND
	                                            (
	                                                ISNULL( t1.ownership ) OR t1.ownership = 0
	                                            )
	                                        )
                                        )"
                                        :
                                        "t1.customer_hash = '{$this->customer_hash}'"
                                    ) . "
                                    AND t1.deleted = 0
                                    GROUP BY t1.obj_id " .
                                    ( $order_by ?
                                        "ORDER BY $order_by " .
                                        ( $order_dir ?
                                            $order_dir : "DESC"
                                        ) : NULL
                                    ) . "
                                    ORDER BY contact_name asc
                                    LIMIT $start, $end");
        while ( $row = $this->db->fetch_assoc($result) ) {

        	$_total++;
            array_push($this->customer_contacts, $row);
        }

        return $_total;
	}

	function fetch_contact_record($contact_hash) {

		$result = $this->db->query("SELECT customer_contacts.*
							  		FROM customer_contacts
							  		WHERE customer_contacts.contact_hash = '$contact_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_contact = $row;
			$this->contact_hash = $contact_hash;

			return true;
		}

		return false;
	}


	function fetch_locations($start, $order_by=NULL, $order_dir=NULL) {

		$end = SUB_PAGNATION_NUM;
		$this->customer_locations = array();
		$_total = 0;

		$result = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
							  		FROM locations t1
							  		WHERE t1.entry_hash = '{$this->customer_hash}' && t1.entry_type = 'c' AND t1.deleted = 0");
		$this->total_locations = $this->db->result($result, 0, 'Total');

		$result = $this->db->query("SELECT t1.*
							  		FROM locations t1
							  		WHERE t1.entry_hash = '{$this->customer_hash}' && t1.entry_type = 'c' AND t1.deleted = 0 " .
							  		( $order_by ?
    							  		"ORDER BY $order_by " .
    							  		( $order_dir ?
        							  		$order_dir : "DESC"
            							) : NULL
        							) . "
							  		LIMIT $start, $end");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$_total++;
			array_push($this->customer_locations, $row);
		}

		return $_total;
	}
	
	function fetch_all_locations($start, $order_by=NULL, $order_dir=NULL) {
	
		$end = 1000;
		$this->customer_locations = array();
		$_total = 0;
	
		$result = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
				FROM locations t1
				WHERE t1.entry_hash = '{$this->customer_hash}' && t1.entry_type = 'c' AND t1.deleted = 0");
				$this->total_locations = $this->db->result($result, 0, 'Total');
	
				$result = $this->db->query("SELECT t1.*
						FROM locations t1
						WHERE t1.entry_hash = '{$this->customer_hash}' && t1.entry_type = 'c' AND t1.deleted = 0 " .
						( $order_by ?
						"ORDER BY $order_by " .
						( $order_dir ?
								$order_dir : "DESC"
						) : NULL
		) . "
		LIMIT $start, $end");
		while ( $row = $this->db->fetch_assoc($result) ) {
	
			$_total++;
			array_push($this->customer_locations, $row);
	}
	
	return $_total;
		}

	function fetch_location_record($location_hash) {

		$result = $this->db->query("SELECT locations.*
							  		FROM `locations`
							  		WHERE locations.location_hash = '$location_hash' && `entry_type` = 'c'");
		if ($row = $this->db->fetch_assoc($result)) {

			$this->current_location = $row;
			$this->location_hash = $location_hash;

			return true;
		}

		return false;
	}

	function doit_customer_location() {

		if ( func_num_args() > 0 ) {

    		$customer_hash = func_get_arg(0);
            $new_customer = func_get_arg(1);
		}

		$p = $_POST['p'];
		$order = $_POST['order_dir'];
		$order_dir = $_POST['order_dir'];
		$integrate = $_POST['integrate'];

		if ( $integrate && ! is_object($this->check) )
			$this->check = new Validator;

        if ( ! $new_customer && ! $this->current_customer )
			$this->fetch_master_record($customer_hash);

        if ( $location_hash = $_POST['location_hash'] ) {

            if ( ! $this->fetch_location_record($location_hash) )
                return $this->__trigger_error("A system error was encountered when trying to lookup customer location for database update. Please re-load customer window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

            $edit_location = true;
            $action_delete = $_POST['delete'];
        }

		$location['location_name'] = addslashes( trim($_POST['location_name']) );
        $location['location_account_no'] = $_POST['location_account_no'];
		$location['location_street'] = addslashes( trim($_POST['location_street']) );
		$location['location_city'] = addslashes( trim($_POST['location_city']) );
		$location['location_state'] = $_POST['location_state'];
		$location['location_zip'] = $_POST['location_zip'];
		$location['location_country'] = $_POST['location_country'];
        $location_tax = $_POST['location_tax'];
		$location['location_tax_lock'] = $_POST['location_tax_lock'];

		if ( ! $location['location_name'] || ! $location['location_city'] || ! $location['location_state'] || ! $location['location_country'] ) {

			if ( ! $location['location_name'] ) $this->set_error("err4_1{$this->popup_id}");
			if ( ! $location['location_city'] ) $this->set_error("err4_3{$this->popup_id}");
			if ( ! $location['location_state'] ) $this->set_error("err4_4{$this->popup_id}");
			if ( ! $location['location_country'] ) $this->set_error("err4_5a{$this->popup_id}");

			if ( $new_customer ) {

                $this->__trigger_error("Please check that you have completed all required customer location fields. Missing or invalid fields are indicated in red.", E_USER_NOTICE, __FILE__, __LINE__);
        		return false;
			}

            return $this->__trigger_error("Please check that you have completed all required fields, indicated below.", E_USER_NOTICE, __FILE__, __LINE__, 1);
		}

		if ( ! $this->check->is_zip($location['location_zip']) ) {

			$err_msg = $this->check->ERROR;
			if ( $integrate )
            	return $err_msg;
            else {

            	$err = 1;
				$this->set_error_msg($err_msg);
				$this->set_error('err4_5');
            }
		}
        if ( $custom = $_POST['custom'] ) {

            while ( list($cust_key, $cust_val) = each($custom) ) {

                $this->cust->fetch_custom_field($cust_key);
                if ( $this->cust->current_field['required'] && ! trim($cust_val) ) {

					if ( $integrate )
						$missing_fields[] = $cust_val;
					else {
	                	$this->set_error("err_custom{$this->cust->current_field['obj_id']}");
	                    $err = 1;
					}

                    $e = 1;
                }

                $location[ $this->cust->current_field['col_name'] ] = trim($cust_val);
            }

            if ( $e ) {

                if ( $integrate )
            		return "Missing required fields: " . implode(", ", $missing_fields);
            	else
            		$this->set_error_msg("Please confirm that you have completed the required customer contact fields indicated below.");
            }
        }

        if ( $err ) {

            if ( $new_customer )
                return false;
            else
                return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
        }

        if ( ! $new_customer ) {

	        if ( ! $this->db->start_transaction() )
	            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
        }

		if ( $edit_location ) {

			if ( $action_delete ) {

				if ( ! $this->db->query("UPDATE locations t1
                        				 SET
                            				 t1.deleted = 1,
                            				 t1.deleted_date = CURDATE()
                                         WHERE t1.location_hash = '{$this->location_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				$this->content['page_feedback'] = "Your location has been deleted.";

			} else {

                $location = $this->db->prepare_query($location, "locations", 'UPDATE');
                if ( ! $this->db->query("UPDATE locations
		                                 SET
		                                     timestamp = UNIX_TIMESTAMP(),
		                                     last_change = '{$this->current_hash}' ,
		                                     " . implode(", ", $location) . "
		                                 WHERE location_hash = '{$this->location_hash}'")
                ) {

                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				$this->db->query("DELETE FROM location_tax
				                  WHERE location_hash = 'customers|{$this->customer_hash}|{$this->location_hash}'");

				for ( $i = 0; $i < count($location_tax); $i++ ) {

				    if ( ! $this->db->query("INSERT INTO location_tax
        				                     VALUES
        				                     (
	        				                     NULL,
	        				                     'customers|{$this->customer_hash}|{$this->location_hash}',
	        				                     '{$location_tax[$i]}'
        				                     )")
				    ) {

                        return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
				    }
				}

                $this->content['page_feedback'] = "Your location has been updated.";
			}

			unset($this->current_location);

		} else {

			$location_hash = rand_hash('locations', 'location_hash');

            $location = $this->db->prepare_query($location, "locations", 'INSERT');
            if ( ! $this->db->query("INSERT INTO locations
		                              (
		                                  timestamp,
		                                  last_change,
		                                  entry_hash,
		                                  entry_type,
		                                  location_hash,
			                              " . implode(", ", array_keys($location) ) . "
		                              )
		                              VALUES (
		                                  UNIX_TIMESTAMP(),
		                                  '{$this->current_hash}',
		                                  '$customer_hash',
		                                  'c',
		                                  '$location_hash',
		                                  " . implode(", ", array_values($location) ) . "
		                              )")
            ) {

                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            for ( $i = 0; $i < count($location_tax); $i++ ) {

                if ( ! $this->db->query("INSERT INTO location_tax
                                         VALUES
                                         (
	                                         NULL,
	                                         'customers|{$this->customer_hash}|$location_hash',
	                                         '{$location_tax[$i]}'
                                         )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
            }
		}

        if ( $new_customer )
            return true;

		$this->db->end_transaction();

		unset($this->ajax_vars);
		if ( $customer_hash ) {

			$this->fetch_master_record($customer_hash);
			$this->ajax_vars['customer_hash'] = $customer_hash;
		}

		$this->ajax_vars['p'] = $p;
		$this->ajax_vars['order'] = $order;
		$this->ajax_vars['order_dir'] = $order_dir;

		if ( $integrate )
			return "Success";
		else {

			$this->content['action'] = 'continue';
			$this->content['html']["tcontent4{$this->popup_id}"] = $this->location_info_form();

			return;
		}

	}

	function doit_customer_contact() {

		if ( func_num_args() > 0 ) {

			$customer_hash = func_get_arg(0);
			$new_customer = func_get_arg(1);
		}

		$p = $_POST['p'];
		$order = $_POST['order_dir'];
		$order_dir = $_POST['order_dir'];
		$integrate = $_POST['integrate'];

		if ( $integrate && ! is_object($this->check) )
			$this->check = new Validator;

        if ( ! $new_customer && ! $this->current_customer )
    		$this->fetch_master_record($customer_hash);

		if ( $contact_hash = $_POST['contact_hash'] ) {

			if ( ! $this->fetch_contact_record($contact_hash) )
                return $this->__trigger_error("A system error was encountered when trying to lookup customer contact for database update. Please re-load customer window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			$edit_contact = true;
			$action_delete = $_POST['delete'];
		}

		$contact['contact_name'] = addslashes( trim($_POST['contact_name']) );
		$contact['contact_title'] = addslashes( trim($_POST['contact_title']) );
		$contact['contact_phone1'] = $_POST['contact_phone'];
		$contact['contact_phone2'] = $_POST['contact_phone2'];
		$contact['contact_mobile'] = $_POST['contact_mobile'];
		$contact['contact_fax'] = $_POST['contact_fax'];
		$contact['contact_email'] = $_POST['contact_email'];
		$contact['contact_user_name'] = $_POST['contact_user_name'];
		$contact['contact_password'] = $_POST['contact_password'];
		$location_list = $_POST['location_list'];

	    if ( ! $contact['contact_name'] ) {

            if ( ! $contact['contact_name'] ) $this->set_error("err3_1{$this->popup_id}");

            if ( $new_customer ) {

                $this->__trigger_error("Please check that you have completed all required customer contact fields. Missing or invalid fields are indicated in red.", E_USER_NOTICE, __FILE__, __LINE__);
                return false;
            }

            return $this->__trigger_error("Please check that you have completed all required fields, indicated below.", E_USER_NOTICE, __FILE__, __LINE__, 1);
        }

		if ( $contact['contact_email'] && ! $this->check->is_email($contact['contact_email']) ) {

			$err_msg = $this->check->ERROR;
			if ( $integrate )
				return $err_msg;
			else {

				$err = 1;
				$this->set_error_msg($err_msg);
				$this->set_error('err3_7');
			}
		}

		if ( $contact['contact_fax'] && ! $this->check->is_phone($contact['contact_fax']) ) {

			$err_msg = $this->check->ERROR;
			if ( $integrate )
				return $err_msg;
			else {

				$err = 1;
				$this->set_error_msg($this->check->ERROR);
				$this->set_error('err3_6');
			}
		}

        if ( $custom = $_POST['custom'] ) {

            while ( list($cust_key, $cust_val) = each($custom) ) {

                $this->cust->fetch_custom_field($cust_key);
                if ( $this->cust->current_field['required'] && ! trim($cust_val) ) {

                    if ( $integrate )
                 		$missing_fields[] = $cust_val;
                    else {

	                	$this->set_error("err_custom{$this->cust->current_field['obj_id']}");
	                    $err = 1;
                    }

                    $e = 1;
                }

                $contact[ $this->cust->current_field['col_name'] ] = trim($cust_val);
            }

            if ( $e ) {

            	if ( $integrate )
            		return "Missing required fields: " . implode(", ", $missing_fields);
            	else
            		$this->set_error_msg("Please confirm that you have completed the required customer contact fields indicated below.");
            }
        }

        if ( $err ) {

            if ( $new_customer )
                return false;
            else
                return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
        }

        if ( ! $new_customer ) {

	        if ( ! $this->db->start_transaction() )
	            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
        }

		if ( $edit_contact ) {

			if ( $action_delete ) {

				if ( ! $this->db->query("UPDATE customer_contacts t1
							             SET
    							             t1.deleted = 1,
    							             t1.deleted_date = CURDATE()
                				         WHERE t1.contact_hash = '{$this->contact_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
                if ( ! $this->db->query("DELETE FROM location_contacts
                                         WHERE location_contacts.contact_hash = '{$this->contact_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

                if ( $integrate )
                	return "Contact deleted";
                else
					$this->content['page_feedback'] = "Contact has been removed";

			} else {

                $contact = $this->db->prepare_query($contact, "customer_contacts", 'UPDATE');
                if ( ! $this->db->query("UPDATE customer_contacts
		                                  SET
		                                      timestamp = UNIX_TIMESTAMP(),
		                                      last_change = '{$this->current_hash}',
		                                      " . implode(", ", $contact) . "
		                                  WHERE contact_hash = '{$this->contact_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				if ( ! $location_list ) {

                    if ( ! $this->db->query("DELETE FROM location_contacts
                                             WHERE location_contacts.contact_hash = '{$this->contact_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

				} else {

                	if ( ! ( $current_loc = $this->fetch_location_contacts($this->contact_hash) ) )
                        $to_add = $location_list;
                    else {

                        $to_add = array_diff($location_list, $current_loc);
                        $to_rm = array_diff($current_loc, $location_list);
                    }

                    if ( $to_add ) {

                    	reset($to_add);
                        foreach ( $to_add as $key => $hash ) {

                            if ( ! $this->db->query("INSERT INTO location_contacts
                                                     VALUES
                                                     (
	                                                     NULL,
	                                                     '{$this->contact_hash}',
	                                                     '{$to_add[$key]}'
                                                     )")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
                        }
                    }

                    if (count($to_rm)) {

                    	reset($to_rm);
                        foreach ( $to_rm as $key => $hash ) {

                            if ( ! $this->db->query("DELETE FROM location_contacts
                                                     WHERE location_hash = '{$to_rm[$key]}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
                        }
                    }
                }

                $this->content['page_feedback'] = "Your contact has been updated.";
			}

			unset($this->current_contact);

		} else {

			$contact_hash = rand_hash('customer_contacts', 'contact_hash');

            $contact = $this->db->prepare_query($contact, "customer_contacts", 'INSERT');
            if ( ! $this->db->query("INSERT INTO customer_contacts
		                              (
		                                  timestamp,
		                                  last_change,
		                                  customer_hash,
		                                  contact_hash,
		                                  " . implode(", ", array_keys($contact) ) . "
		                              )
		                              VALUES
		                              (
		                                  UNIX_TIMESTAMP(),
		                                  '{$this->current_hash}',
		                                  '$customer_hash',
		                                  '$contact_hash',
		                                  " . implode(", ", array_values($contact) ) . "
		                              )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			if ( $location_list ) {

                for ( $i = 0; $i < count($location_list); $i++ ) {

                    if ( ! $this->db->query("INSERT INTO location_contacts
                                             VALUES
                                             (
	                                             NULL,
	                                             '$contact_hash',
	                                             '{$location_list[$i]}'
                                             )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
                }
			}
		}

		if ( $new_customer )
            return true;

        $this->db->end_transaction();

		unset($this->ajax_vars);
		if ($customer_hash) {

			$this->fetch_master_record($customer_hash);
			$this->ajax_vars['customer_hash'] = $customer_hash;
		}
		$this->ajax_vars['p'] = $p;
		$this->ajax_vars['order'] = $order;
		$this->ajax_vars['order_dir'] = $order_dir;

		if ( $integrate )
			return "Success";
		else {
			$this->content['action'] = 'continue';
			$this->content['html']["tcontent3{$this->popup_id}"] = $this->contact_info_form();

			return;
		}

	}

	function doit_customer_discount() {

		$p = $_POST['p'];
		$order = $_POST['order_dir'];
		$order_dir = $_POST['order_dir'];

		$discount_next_action = $_POST['discount_next_action'];

		if ( $discount_hash = $_POST['discount_hash'] ) {

			if ( ! $this->discounting->fetch_discount_record($discount_hash) )
    			return $this->__trigger_error("System error encountered when attempting to lookup discount record for edit. Please reload window and try again. <!-- Tried fetching discount [ $discount_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			$edit_discount = true;
			$action_delete = $_POST['delete'];
		}

        if ( ! $this->db->start_transaction() )
            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

		if ( $action_delete ) {

			if ( ! $this->db->query("UPDATE discounting t1
                                     LEFT JOIN discount_details t2 ON t2.discount_hash = t1.discount_hash
                                     SET
                                         t1.timestamp = UNIX_TIMESTAMP(),
                                         t1.last_change = '{$this->current_hash}',
                                         t1.deleted = 1,
                                         t1.deleted_date = CURDATE(),
                                         t2.deleted = 1,
                                         t2.deleted_date = CURDATE()
        							 WHERE t1.discount_hash = '{$this->discounting->discount_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			$this->content['page_feedback'] = "Discount has been deleted";

		} else {

			if ( $_POST['discount_vendor'] && $_POST['discount_descr'] && $_POST['discount_id'] && $_POST['discount_effective_date'] && $_POST['discount_expiration_date'] ) {

				if ( ! $_POST['discount_vendor_hash'] ) {

                    $e = 1;
					$this->set_error("err6_2{$this->popup_id}");
					return $this->set_error_msg("We can't seem to find the vendor you entered below. If you are trying to create a new vendor, please use the plus icon below.");
				}

				if ( ! checkdate( substr($_POST['discount_effective_date'], 5, 2), substr($_POST['discount_effective_date'], 8), substr($_POST['discount_effective_date'], 0, 4) ) || ! checkdate( substr($_POST['discount_expiration_date'], 5, 2), substr($_POST['discount_expiration_date'], 8), substr($_POST['discount_expiration_date'], 0, 4) ) ) {

                    $e = 1;
					if ( ! checkdate( substr($_POST['discount_effective_date'], 5, 2), substr($_POST['discount_effective_date'], 8), substr($_POST['discount_effective_date'], 0, 4) ) ) $this->set_error("err6_5{$this->popup_id}");
					if ( ! checkdate( substr($_POST['discount_expiration_date'], 5, 2), substr($_POST['discount_expiration_date'], 8), substr($_POST['discount_expiration_date'], 0, 4) ) ) $this->set_error("err6_6{$this->popup_id}");

					return $this->set_error_msg("The dates you entered, indicated below are invalid. Please check that you have entered valid dates and try again.");
				}

				if ( strtotime($_POST['discount_expiration_date']) <= strtotime($_POST['discount_effective_date']) ) {

					$e = 1;
					$this->set_error("err6_5{$this->popup_id}");
					$this->set_error("err6_6{$this->popup_id}");

					return $this->set_error_msg("Please check your dates below. If looks like your expiration date falls before your effective date.");
				}

                if ( $e )
                    return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__ , __LINE__, 1);

				$discount['discount_type'] = 'C';
				$discount['vendor_hash'] = $_POST['discount_vendor_hash'];
				if ( ! $this->discounting->discount_hash ) {

					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM discounting t1
												WHERE t1.vendor_hash = '{$discount['vendor_hash']}' AND t1.customer_hash = '{$this->customer_hash}' AND t1.discount_id = '{$_POST['discount_id']}' AND t1.deleted = 0");
					if ( $this->db->result($result) ) {

						$this->set_error("err6_2{$this->popup_id}");
                        return $this->__trigger_error("A vendor discount already exists under this customer for the selected vendor with the discount ID you entered. If you are trying to edit the vendor's discount, select that discount it and it the record.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}
				}

				$discount['discount_descr'] = $_POST['discount_descr'];
				$discount['discount_id'] = $_POST['discount_id'];
				$discount['discount_gsa'] = $_POST['discount_gsa'];
				$discount['discount_effective_date'] = $_POST['discount_effective_date'];
				$discount['discount_expiration_date'] = $_POST['discount_expiration_date'];

				if ( ! $this->discounting->discount_hash ) {

					$discount_hash = rand_hash('discounting', 'discount_hash');

					if ( ! $this->db->query("INSERT INTO discounting
        									 VALUES
        									 (
	        									 NULL,
	        									 UNIX_TIMESTAMP(),
	        									 '{$this->current_hash}',
	        									 '$discount_hash',
	        									 0,
	        									 NULL,
	        									 '{$discount['vendor_hash']}',
	        									 '{$discount['discount_type']}',
	        									 '{$discount['discount_id']}',
	        									 '{$discount['discount_descr']}',
	        									 '{$discount['discount_gsa']}',
	        									 0,
	        									 '{$this->customer_hash}',
	        									 '{$discount['discount_effective_date']}',
	        									 '{$discount['discount_expiration_date']}'
        									 )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

				} else {

					if ( ! $this->db->query("UPDATE discounting t1
        									 SET
	        									 t1.timestamp = UNIX_TIMESTAMP(),
	        									 t1.last_change = '{$this->current_hash}',
	        									 t1.discount_id = '{$discount['discount_id']}',
	        									 t1.discount_descr = '{$discount['discount_descr']}',
	        									 t1.discount_gsa = '{$discount['discount_gsa']}',
	        									 t1.discount_effective_date = '{$discount['discount_effective_date']}',
	        									 t1.discount_expiration_date = '{$discount['discount_expiration_date']}'
        									 WHERE t1.discount_hash = '{$this->discounting->discount_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
				}

			} else {

				if ( ! $_POST['discount_vendor'] ) $this->set_error("err6_2{$this->popup_id}");
				if ( ! $_POST['discount_descr'] ) $this->set_error("err6_7{$this->popup_id}");
				if ( ! $_POST['discount_id'] ) $this->set_error("err6_4{$this->popup_id}");
				if ( ! $_POST['discount_effective_date'] ) $this->set_error("err6_5{$this->popup_id}");
				if ( ! $_POST['discount_expiration_date'] ) $this->set_error("err6_6{$this->popup_id}");

				return $this->__trigger_error("You left some required fields blank when creating your discount. Please check the indicated fields and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
			}
		}

		$this->db->end_transaction();

		unset($this->ajax_vars);
		$this->fetch_master_record($this->customer_hash);

		$this->ajax_vars['p'] = $p;
		$this->ajax_vars['order'] = $order;
		$this->ajax_vars['order_dir'] = $order_dir;

		$this->ajax_vars['customer_hash'] = $this->customer_hash;
		$this->ajax_vars['discount_hash'] = $discount_hash;
		$this->ajax_vars['action'] = ( $discount_next_action ? $discount_next_action : 'showtable' );
		$this->content['action'] = 'continue';

		$this->content['html']["tcontent5{$this->popup_id}"] = $this->discount_info_form();
	}

	function doit_customer_discount_item() {

		$p = $_POST['p'];
		$order = $_POST['order_dir'];
		$order_dir = $_POST['order_dir'];

        $disc_type = $_POST['disc_type'];

        if ( $discount_hash = $_POST['discount_hash'] ) {

            if ( ! $this->discounting->fetch_discount_record($discount_hash) )
                return $this->__trigger_error("We can't seem to find the discount record that you're trying to update. It's possible that it may have been removed by another user.",E_USER_ERROR,__FILE__,__LINE__,1);

            $action_delete = $_POST['delete'];
        }

        if ( $item_hash = $_POST['item_hash'] ) {

            if ( ! $this->discounting->fetch_item_record($item_hash) )
                return $this->__trigger_error("System error encountered when attempting to lookup discount item record for edit. Please reload window and try again. <!-- Tried fetching discount item [ $item_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

            $action_delete = $_POST['delete'];
        }

        if ( ! $this->db->start_transaction() )
            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

		if ( $action_delete ) {

			if ( ! $this->db->query("UPDATE discount_details t1
        			                 SET
            			                 t1.deleted = 1,
            			                 t1.deleted_date = CURDATE()
        							 WHERE t1.discount_hash = '{$this->discounting->discount_hash}' AND t1.item_hash = '{$this->discounting->item_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            $this->content['feedback'] = "Discount item has been deleted";

		} else {

			if ( ( ! $this->discounting->item_hash && count($_POST['discount_product_hash']) > 0) || ( ! $this->discounting->item_hash && count($_POST['discount_product_hash_2']) > 0 ) || $this->discounting->item_hash ) {

                $disc_type = $_POST['disc_type'];
                $discount_type = $_POST['discount_type'];
                $discount_descr = addslashes($_POST['discount_descr']);

                if ( $disc_type == 1 )
                    $product_hash = $_POST['discount_product_hash'];
                elseif ( $disc_type == 2 )
                    $product_hash = $_POST['discount_product_hash_2'];

                if ( ! is_array($product_hash) )
                    $product_hash = array($product_hash);

                $discount_frf_quoted = $_POST['discount_frf_quoted'];
				$discount_frf_amount = preg_replace('/[^.0-9]/', "", $_POST['discount_frf_amount']);
				if ( bccomp($discount_frf_amount, 0, 2) == 1 ) {

					if ( $_POST['discount_frf_amount_type'] && $_POST['discount_frf_amount_add'] && $_POST['discount_frf_amount_add_type'] && $_POST['discount_frf_effective_date'] ) {

						$discount_frf_amount_type = $_POST['discount_frf_amount_type'];
						$discount_frf_amount_add = preg_replace('/[^.0-9]/', "", $_POST['discount_frf_amount_add']);
						$discount_frf_amount_add_type = $_POST['discount_frf_amount_add_type'];
						if ( $discount_frf_amount_add_type == "P" ) {

							if ( bccomp($discount_frf_amount_add, 100, 2) >= 0 ) {

								$this->set_error("err6_41{$this->popup_id}");
								return $this->__trigger_error("Please check that you have entered your add on percent correctly. Percentages should be entered as greater than 1. For example, a percent of 69.75% should be entered as 69.75, not .6975.", E_USER_NOTICE, __FILE__, __LINE__, 1);
							}

							$discount_frf_amount_add = bcmul($discount_frf_amount_add, .01, 4);
							$discount_frf_amount_add_type_of = $_POST['discount_frf_amount_add_type_of'];
						}

						if ( $discount_frf_amount_add_type == 'P' && !$discount_frf_amount_add_type_of ) {

							$this->set_error("err6_42{$this->popup_id}");
							return $this->__trigger_error("In order to create a small order fee you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

						$discount_frf_effective_date = $_POST['discount_frf_effective_date'];
						$discount_frf_expire_date = $_POST['discount_frf_expire_date'];
						$discount_frf_account = $_POST['discount_frf_account'];
						if ( ! checkdate( substr($discount_frf_effective_date, 5, 2), substr($discount_frf_effective_date, 8, 2), substr($discount_frf_effective_date, 0, 4) ) || ( $discount_frf_expire_date && strtotime($discount_frf_expire_date) <= strtotime($discount_frf_effective_date) ) || ( $discount_frf_expire_date && ! checkdate( substr($discount_frf_expire_date, 5, 2), substr($discount_frf_expire_date, 8, 2), substr($discount_frf_expire_date, 0, 4) ) ) ) {

							if ( ! checkdate( substr($discount_frf_effective_date, 5, 2), substr($discount_frf_effective_date, 8, 2), substr($discount_frf_effective_date, 0, 4) ) ) $this->set_error("err6_44{$this->popup_id}");
							if ( $discount_frf_expire_date && ! checkdate( substr($discount_frf_expire_date, 5, 2), substr($discount_frf_expire_date, 8, 2), substr($discount_frf_expire_date, 0, 4) ) ) $this->set_error("err6_45{$this->popup_id}");
							if ( $discount_frf_expire_date && strtotime($discount_frf_expire_date) <= strtotime($discount_frf_effective_date) ) $this->set_error("err6_45{$this->popup_id}");
							return $this->__trigger_error("There looks to be something wrong with your dates below. Please make sure that you entered valid dates and that your expiration date is not before your effective date.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

						$discount_frf = "product_frf_amount=$discount_frf_amount|product_frf_amount_type=$discount_frf_amount_type|product_frf_amount_add=$discount_frf_amount_add|product_frf_amount_add_type=$discount_frf_amount_add_type|product_frf_amount_add_type_of=$discount_frf_amount_add_type_of|product_frf_effective_date=$discount_frf_effective_date|product_frf_expire_date=$discount_frf_expire_date|product_frf_account=$discount_frf_account";

					} else {

						if ( ! $_POST['discount_frf_amount_type'] ) $this->set_error("err6_42{$this->popup_id}");
						if ( ! $_POST['discount_frf_amount_add'] ) $this->set_error("err6_41{$this->popup_id}");
						if ( ! $_POST['discount_frf_amount_add_type'] ) $this->set_error("err6_41{$this->popup_id}");
						if ( ! $_POST['discount_frf_effective_date'] ) $this->set_error("err6_44{$this->popup_id}");
						if ( ! $_POST['discount_frf_account'] ) $this->set_error("err6_43{$this->popup_id}");

						return $this->__trigger_error("In order to create add ons for freight terms you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

				} else
					$discount_frf = '';

                if ( $disc_type == 2 ) {

                    $_POST['discount_item_no'] = trim($_POST['discount_item_no']);
                    if ( ! $this->discounting->item_hash  ) {

                        $item_discount = explode("\n", $_POST['discount_item_no']);
                        foreach ( $item_discount as $key => $val ) {

                        	$item_discount[$key] = trim($val);
                            if ( ! trim($val) || trim($val) == 'Enter each item number on a new line...' )
                                unset($item_discount[$key]);
                        }

                    } else
                        $item_discount = trim($_POST['discount_item_no']);

                    if ( ( ! $this->discounting->item_hash && ! count($item_discount) ) || ( $this->discounting->item_hash && ! $item_discount ) ) {

                        if ( ! $this->discounting->item_hash )
                            $this->set_error('disc_type_selector');

                        $this->set_error('item_no_label');
                        return $this->__trigger_error( ( $this->discounting->item_hash ? "The discount you are updating has been created as an item level discount. In order to continue, please make sure that your discount includes an item number.<br /><br />If your revised discount no longer includes this particular item number, you may delete this discount item to prevent it from being used." : "You indicated that you are creating a discount to be applied on specific items within the selected product line. Unfortunately, you didn't include any item numbers to be used within the discount.<br /><br />Please make sure you include at least one item number or change the type of discount that you are creating." ), E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }

                } else
                    $item_discount = array();

                if ( $disc_type == 2 && ( ! $this->discounting->item_hash || ( $this->discounting->item_hash && $item_discount != $this->discounting->current_item['item_no'] ) ) ) { # Check for duplications

                    if ( $this->discounting->item_hash ) {

                        $dup_item = array($item_discount);
                        $product_hash = array($this->discounting->current_item['product_hash']);
                    } else
                        $dup_item = $item_discount;

                    for ( $i = 0; $i < count($product_hash); $i++ ) {

                        for ( $j = 0; $j < count($dup_item); $j++ ) {

                            $r = $this->db->query("SELECT COUNT(*) AS Total
                                                   FROM discount_details t1
                                                   WHERE
                                                       t1.discount_hash = '{$this->discounting->discount_hash}'
                                                       AND
                                                       t1.product_hash = '{$product_hash[$i]}'
                                                       AND
                                                       t1.item_no = '{$dup_item[$j]}'
                                                       AND
                                                       t1.deleted = 0");
                            if ( $this->db->result($r, 0, 'Total') )
                                $dup[] = $dup_item[$j];
                        }
                    }

                    if ( $dup ) {

                        $this->set_error('item_no_label');
                        return $this->__trigger_error("We found duplicate item number discounts in the discount you are " . ( $this->discounting->item_hash ? "editing" : "saving" ) . ". Please check the following item numbers for duplications: " . implode(", ", $dup), E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }
                }

                if ( $disc_type == 3 ) {

					$_POST['discount_code'] = trim($_POST['discount_code']);
                    if ( ! $this->discounting->item_hash ) {

                        $discount_code = explode("\n", $_POST['discount_code']);
                        foreach ( $discount_code as $key => $val ) {

                        	$discount_code[$key] = trim($val);
                            if ( ! trim($val) || trim($val) == 'Enter each discount code on a new line...' )
                                unset($discount_code[$key]);
                        }

                    } else
                        $discount_code = trim($_POST['discount_code']);

                    if ( ( ! $this->discounting->item_hash && ! count($discount_code) ) || ( $this->discounting->item_hash && ! $discount_code ) ) {

                        if ( ! $this->discounting->item_hash )
                            $this->set_error('disc_type_selector');

                        $this->set_error("discount_code_label{$this->popup_id}");
                        return $this->__trigger_error( ( $this->discounting->item_hash ? "The discount you are updating has been created based on item discount code. In order to continue, please make sure that you've enter a valid discount code.<br /><br />If your revised discount no longer includes this particular item number, you may delete this discount item to prevent it from being used." : "You indicated that you are creating a discount to be based on discount code. You failed to include any discount codes to be used within the discount.<br /><br />Please make sure you include at least one discount code or change the type of discount that you are creating." ), E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }
                }

				if ( $disc_type == 3 && ( ! $this->discounting->item_hash || ( $this->discounting->item_hash && $discount_code != $this->discounting->current_item['discount_code'] ) ) ) { # Check for duplications

                    if ( $this->discounting->item_hash ) {

                        $dup_item = array($discount_code);
                    } else
                	    $dup_item = $discount_code;

                    for ( $i = 0; $i < count($dup_item); $i++ ) {

                        $r = $this->db->query("SELECT COUNT(*) AS Total
                                               FROM discount_details t1
                                               WHERE t1.discount_hash = '{$this->discounting->discount_hash}' AND t1.discount_code = '{$dup_item[$i]}' AND t1.deleted = 0");
                        if ( $this->db->result($r, 0, 'Total') )
                            $dup[] = $dup_item[$j];
                    }

                    if ( $dup ) {

                        $this->set_error("discount_code_label{$this->popup_id}");
                        return $this->__trigger_error("We found duplicate discount codes in the discount you are " . ( $this->discounting->item_hash ? "editing" : "saving" ) . ". Please check the following discount codes for duplications: " . implode(", ", $dup), E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }
                }

				if ( $discount_type == 'F' ) { # Non-tiered discounting

					$buy_discount1 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount1']);
					if ( $buy_discount1 )
						$buy_discount1 = bcmul($buy_discount1, .01, 4);

					$buy_discount2 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount2']);
					if ( $buy_discount2 )
						$buy_discount2 = bcmul($buy_discount2, .01, 4);

					$buy_discount3 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount3']);
					if ( $buy_discount3 )
						$buy_discount3 = bcmul($buy_discount3, .01, 4);

					$buy_discount4 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount4']);
					if ( $buy_discount4 )
						$buy_discount4 = bcmul($buy_discount4, .01, 4);

					$buy_discount5 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount5']);
					if ( $buy_discount5 )
						$buy_discount5 = bcmul($buy_discount5, .01, 4);

					$gp_margin = preg_replace('/[^.0-9]/', "", $_POST['gp_margin']);
					if ( $gp_margin )
						$gp_margin = bcmul($gp_margin, .01, 4);

					$sell_discount = preg_replace('/[^.0-9]/', "", $_POST['sell_discount']);
					if ( $sell_discount )
						$sell_discount = bcmul($sell_discount, .01, 4);

					if ( bccomp($sell_discount, 0, 4) )
						unset($gp_margin);

					$install_sell = preg_replace('/[^.0-9]/', "", $_POST['install_sell']);
					if ( $install_sell )
						$install_sell = bcmul($install_sell, .01, 4);

					$sell_disc_amt = bcsub(100, bcmul(100, $sell_discount, 4), 4);
					if ( is_numeric($buy_discount1) ) {

						$total_disc_amt = bcsub(100, bcmul(100, $buy_discount1, 4), 4);
						if ( is_numeric($buy_discount2) ) {

							$total_disc_amt = bcsub($total_disc_amt, bcmul($total_disc_amt, $buy_discount2, 4), 4);
							if ( is_numeric($buy_discount3) ) {

								$total_disc_amt = bcsub($total_disc_amt, bcmul($total_disc_amt, $buy_discount3, 4), 4);
								if ( is_numeric($buy_discount4) ) {

									$total_disc_amt = bcsub($total_disc_amt, bcmul($total_disc_amt, $buy_discount4, 4), 4);
									if ( is_numeric($buy_discount5) )
										$total_disc_amt = bcsub($total_disc_amt, bcmul($total_disc_amt, $buy_discount5, 4), 4);

								}
							}
						}
					}

					if ( $sell_discount && bccomp($total_disc_amt, $sell_disc_amt, 4) >= 0 ) {

                        $this->set_error("err6_10a{$this->popup_id}");
                        return $this->__trigger_error("Your list discount cannot be greater than your buy discount. Please check the indicated fields and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

					unset($sell_disc_amt, $total_disc_amt);

					if ( ! $this->discounting->item_hash ) { # Create new product discount

						if ( $disc_type == 3 )
							$disc_loop =& $discount_code;
						else
    						$disc_loop =& $item_discount;

                        if ( $disc_loop && ! is_array($disc_loop) )
                            $disc_loop = array($disc_loop);
                        elseif ( ! is_array($disc_loop) || ! count($disc_loop) )
                            $disc_loop = array(NULL);

                        if ( $product_hash && ! is_array($product_hash) )
                            $product_hash = array($product_hash);
                        elseif ( ! is_array($product_hash) || ! $product_hash )
                            $product_hash = array(NULL);


                        for ( $i = 0; $i < count($product_hash); $i++ ) {

                            reset($disc_loop);
                            foreach ( $disc_loop as $loop_disc_key => $loop_disc_val ) {

                                $item_hash = rand_hash('discount_details', 'item_hash');

                                if ( ! $this->db->query("INSERT INTO discount_details
                                                         (
                                                             discount_hash,
                                                             item_hash,
                                                             product_hash, " .
                                                             ( $disc_type == 2 || $disc_type == 3 ?
                                                                 ( $disc_type == 2 ?
                                                                     "item_no, " : "discount_code, "
                                                                 ) : NULL
                                                             ) .
                                                             ( $disc_type == 3 ?
                                                                 "discount_descr," : NULL
                                                             ) . "
                                                             discount_frf,
                                                             discount_frf_quoted,
                                                             discount_type,
                                                             buy_discount1,
                                                             buy_discount2,
                                                             buy_discount3,
                                                             buy_discount4,
                                                             buy_discount5,
                                                             sell_discount,
                                                             gp_margin,
                                                             install_sell
                                                         )
                                                         VALUES
                                                         (
                                                             '$discount_hash',
                                                             '$item_hash',
                                                             '{$product_hash[$i]}', " .
                                                             ( $disc_type == 2 || $disc_type == 3 ?
                                                                 ( $loop_disc_val ?
                                                                     "'$loop_disc_val', " : "NULL, "
                                                                 ) : NULL
                                                             ) .
                                                             ( $disc_type == 3 ?
	                                                             ( $discount_descr ?
    	                                                             "'$discount_descr', " : "NULL, "
	                                                             ) : NULL
                                                             ) . "
                                                             '$discount_frf',
                                                             '$discount_frf_quoted',
                                                             '$discount_type',
                                                             '$buy_discount1',
                                                             '$buy_discount2',
                                                             '$buy_discount3',
                                                             '$buy_discount4',
                                                             '$buy_discount5',
                                                             '$sell_discount',
                                                             '$gp_margin',
                                                             '$install_sell'
                                                         )")
                                ) {

                                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                }
                            }
                        }

					} else {

						$sql = array();

                        ( $this->discounting->current_item['item_no'] != $item_discount ? array_push($sql, "t1.item_no = " . ( $item_discount ? "'$item_discount'" : "NULL" ) ) : NULL);
						( bccomp($this->discounting->current_item['discount_type'], $discount_type, 4) ? array_push($sql, "t1.discount_type = '$discount_type'") : NULL);
                        ( $this->discounting->current_item['discount_code'] != $discount_code ? array_push($sql, "t1.discount_code = " . ( $discount_code ? "'$discount_code'" : "NULL" ) ) : NULL );
						( bccomp($this->discounting->current_item['buy_discount1'], $buy_discount1, 4) ? array_push($sql, "t1.buy_discount1 = '$buy_discount1'") : NULL);
						( bccomp($this->discounting->current_item['buy_discount2'], $buy_discount2, 4) ? array_push($sql, "t1.buy_discount2 = '$buy_discount2'") : NULL);
						( bccomp($this->discounting->current_item['buy_discount3'], $buy_discount3, 4) ? array_push($sql, "t1.buy_discount3 = '$buy_discount3'") : NULL);
						( bccomp($this->discounting->current_item['buy_discount4'], $buy_discount4, 4) ? array_push($sql, "t1.buy_discount4 = '$buy_discount4'") : NULL);
						( bccomp($this->discounting->current_item['buy_discount5'], $buy_discount5, 4) ? array_push($sql, "t1.buy_discount5 = '$buy_discount5'") : NULL);
						( bccomp($this->discounting->current_item['sell_discount'], $sell_discount, 4) ? array_push($sql, "t1.sell_discount = '$sell_discount'") : NULL);
						( bccomp($this->discounting->current_item['gp_margin'], $gp_margin, 4) ? array_push($sql, "t1.gp_margin = '$gp_margin'") : NULL);
						( bccomp($this->discounting->current_item['install_sell'], $install_sell, 4) ? array_push($sql, "t1.install_sell = '$install_sell'") : NULL);
						( $this->discounting->current_item['discount_frf'] != $discount_frf ? array_push($sql, "t1.discount_frf = '$discount_frf'") : NULL);
						( $this->discounting->current_item['discount_frf_quoted'] != $discount_frf_quoted ? array_push($sql, "t1.discount_frf_quoted = '$discount_frf_quoted'" ) : NULL );

						if ( $sql ) {

							if ( ! $this->db->query("UPDATE discount_details t1
        											 SET " . implode(",\n", $sql) . "
        											 WHERE t1.discount_hash = '{$this->discounting->discount_hash}' && t1.item_hash = '{$this->discounting->item_hash}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
						}
					}

				} elseif ( $discount_type == 'T' ) { # Non-tiered discounting

					# Tier 1
					$tier1_from = $_POST['tier1_from'];
					$tier1_to = preg_replace('/[^-.0-9]/', "", $_POST['tier1_to']);
					$tier1_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier1_buy_discount']);
					if ( bccomp($tier1_buy_discount, 0, 4) )
    					$tier1_buy_discount = bcmul($tier1_buy_discount, .01, 4);

					$tier1_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier1_sell_discount']);
                    if ( bccomp($tier1_sell_discount, 0, 4) )
                        $tier1_sell_discount = bcmul($tier1_sell_discount, .01, 4);

					$tier1_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier1_install_sell']);
                    if ( bccomp($tier1_install_sell, 0, 4) )
                        $tier1_install_sell = bcmul($tier1_install_sell, .01, 4);

					# Tier 2
					$tier2_from = preg_replace('/[^-.0-9]/', "", $_POST['tier2_from']);
					$tier2_to = preg_replace('/[^-.0-9]/', "", $_POST['tier2_to']);
					$tier2_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier2_buy_discount']);
                    if ( bccomp($tier2_buy_discount, 0, 4) )
                        $tier2_buy_discount = bcmul($tier2_buy_discount, .01, 4);

					$tier2_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier2_sell_discount']);
                    if ( bccomp($tier2_sell_discount, 0, 4) )
                        $tier2_sell_discount = bcmul($tier2_sell_discount, .01, 4);

					$tier2_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier2_install_sell']);
                    if ( bccomp($tier2_install_sell, 0, 4) )
                        $tier2_install_sell = bcmul($tier2_install_sell, .01, 4);

					# Tier 3
					$tier3_from = preg_replace('/[^-.0-9]/', "", $_POST['tier3_from']);
					$tier3_to = preg_replace('/[^-.0-9]/', "", $_POST['tier3_to']);
					$tier3_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier3_buy_discount']);
                    if ( bccomp($tier3_buy_discount, 0, 4) )
                        $tier3_buy_discount = bcmul($tier3_buy_discount, .01, 4);

					$tier3_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier3_sell_discount']);
                    if ( bccomp($tier3_sell_discount, 0, 4) )
                        $tier3_sell_discount = bcmul($tier3_sell_discount, .01, 4);

					$tier3_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier3_install_sell']);
                    if ( bccomp($tier3_install_sell, 0, 4) )
                        $tier3_install_sell = bcmul($tier3_install_sell, .01, 4);

					# Tier 4
					$tier4_from = preg_replace('/[^-.0-9]/', "", $_POST['tier4_from']);
					$tier4_to = preg_replace('/[^-.0-9]/', "", $_POST['tier4_to']);
					$tier4_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier4_buy_discount']);
                    if ( bccomp($tier4_buy_discount, 0, 4) )
                        $tier4_buy_discount = bcmul($tier4_buy_discount, .01, 4);

					$tier4_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier4_sell_discount']);
                    if ( bccomp($tier4_sell_discount, 0, 4) )
                        $tier4_sell_discount = bcmul($tier4_sell_discount, .01, 4);

					$tier4_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier4_install_sell']);
                    if ( bccomp($tier4_install_sell, 0, 4) )
                        $tier4_install_sell = bcmul($tier4_install_sell, .01, 4);

					# Tier 5
					$tier5_from = preg_replace('/[^.0-9]/', "", $_POST['tier5_from']);
					$tier5_to = preg_replace('/[^.0-9]/', "", $_POST['tier5_to']);
					$tier5_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier5_buy_discount']);
                    if ( bccomp($tier5_buy_discount, 0, 4) )
                        $tier5_buy_discount = bcmul($tier5_buy_discount, .01, 4);

					$tier5_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier5_sell_discount']);
                    if ( bccomp($tier5_sell_discount, 0, 4) )
                        $tier5_sell_discount = bcmul($tier5_sell_discount, .01, 4);

					$tier5_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier5_install_sell']);
                    if ( bccomp($tier5_install_sell, 0, 4) )
                        $tier5_install_sell = bcmul($tier5_install_sell, .01, 4);

					# Tier 6
					$tier6_from = preg_replace('/[^.0-9]/', "", $_POST['tier6_from']);
					$tier6_to = preg_replace('/[^.0-9]/', "", $_POST['tier6_to']);
					$tier6_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier6_buy_discount']);
                    if ( bccomp($tier6_buy_discount, 0, 4) )
                        $tier6_buy_discount = bcmul($tier6_buy_discount, .01, 4);

					$tier6_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier6_sell_discount']);
                    if ( bccomp($tier6_sell_discount, 0, 4) )
                        $tier6_sell_discount = bcmul($tier6_sell_discount, .01, 4);

					$tier6_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier6_install_sell']);
                    if ( bccomp($tier6_install_sell, 0, 4) )
                        $tier6_install_sell = bcmul($tier6_install_sell, .01, 4);

					if ( ! $tier1_to || ! $tier1_buy_discount || ! $tier2_buy_discount || ( $tier3_buy_discount && ! $tier2_to ) || ( $tier3_to && ! $tier3_buy_discount ) || ( $tier4_buy_discount && ! $tier3_to ) || ( $tier3_to && ! $tier4_buy_discount ) || ( $tier4_to && ! $tier5_buy_discount ) || ( $tier5_to && ! $tier6_buy_discount ) || ( $tier6_buy_discount && ! $tier5_to ) || ( $tier5_buy_discount && ! $tier4_to ) ) {

						if ( ! $tier1_to ) $this->set_error("err6_13{$this->popup_id}");
						if ( ! $tier1_buy_discount ) $this->set_error("err6_14{$this->popup_id}");
						if ( ! $tier2_buy_discount ) $this->set_error("err6_18{$this->popup_id}");
						if ( $tier3_buy_discount && ! $tier2_to ) $this->set_error("err6_17{$this->popup_id}");
						if ( $tier4_buy_discount && ! $tier3_to ) $this->set_error("err6_21{$this->popup_id}");
						if ( $tier3_to && ! $tier3_buy_discount ) $this->set_error("err6_22{$this->popup_id}");
						if ( $tier3_to && ! $tier4_buy_discount ) $this->set_error("err6_26{$this->popup_id}");
						if ( $tier4_to && ! $tier4_buy_discount ) $this->set_error("err6_52{$this->popup_id}");
						if ( $tier4_to && ! $tier5_buy_discount ) $this->set_error("err6_52{$this->popup_id}");
						if ( $tier5_to && ! $tier5_buy_discount ) $this->set_error("err6_52{$this->popup_id}");
						if ( $tier5_to && ! $tier6_buy_discount ) $this->set_error("err6_56{$this->popup_id}");
						if ( $tier5_buy_discount && ! $tier4_to ) $this->set_error("err6_25{$this->popup_id}");
						if ( $tier6_buy_discount && ! $tier5_to ) $this->set_error("err6_51{$this->popup_id}");

						return $this->__trigger_error("In order to create a tiered discount, please complete the indicated fields.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

                    if ( ( $tier6_buy_discount && $tier6_to && ( bccomp($tier6_to, $tier5_to, 2) != 1 ) ) || ( $tier5_buy_discount && $tier5_to && ( bccomp($tier5_to, $tier4_to, 2) != 1 ) ) || ( $tier4_buy_discount && $tier4_to && ( bccomp($tier4_to, $tier3_to, 2) != 1 ) ) || ( $tier3_buy_discount && $tier3_to && bccomp($tier3_to, $tier2_to, 2) != 1 ) ) {

                        if ( $tier4_buy_discount && $tier4_to && bccomp($tier4_to, $tier3_to, 2) != 0 ) {

                            $this->set_error("err6_21{$this->popup_id}");
                            $this->set_error("err6_25{$this->popup_id}");
                        }

                        if ( $tier3_buy_discount && $tier3_to && bccomp($tier3_to, $tier2_to, 2) != 1 ) {

                            $this->set_error("err6_21{$this->popup_id}");
                            $this->set_error("err6_17{$this->popup_id}");
                        }

                        if ( $tier5_buy_discount && $tier5_to && bccomp($tier5_to, $tier4_to, 2) != 1 ) {

                            $this->set_error("err6_51{$this->popup_id}");
                            $this->set_error("err6_25{$this->popup_id}");
                        }

                        if ( $tier6_buy_discount && $tier6_to && bccomp($tier6_to, $tier5_to, 2) != 1 ) {

                            $this->set_error("err6_51{$this->popup_id}");
                            $this->set_error("err6_55{$this->popup_id}");
                        }

                        return $this->__trigger_error("Your tiered dollar amounts are invalid. Dollar amounts must increase through out your tiers.", E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }

					if ( ! $this->check->is_percent($tier1_buy_discount) || ! $this->check->is_percent($tier2_buy_discount) || ( $tier3_buy_discount && ! $this->check->is_percent($tier3_buy_discount) ) || ( $tier4_buy_discount && ! $this->check->is_percent($tier4_buy_discount) ) || ( $tier1_sell_discount && ! $this->check->is_percent($tier1_sell_discount) ) || ( $tier1_install_sell && ! $this->check->is_percent($tier1_install_sell) ) || ( $tier2_sell_discount && ! $this->check->is_percent($tier2_sell_discount) ) || ( $tier2_install_sell && ! $this->check->is_percent($tier2_install_sell) ) || ( $tier3_sell_discount && ! $this->check->is_percent($tier3_sell_discount) ) || ( $tier4_sell_discount && ! $this->check->is_percent($tier4_sell_discount) ) || ( $tier5_buy_discount && ! $this->check->is_percent($tier5_buy_discount) ) || ( $tier6_buy_discount && ! $this->check->is_percent($tier6_buy_discount) ) || ( $tier5_sell_discount && ! $this->check->is_percent($tier5_sell_discount) ) || ( $tier6_sell_discount && ! $this->check->is_percent($tier6_sell_discount) ) ) {

						if ( ! $this->check->is_percent($tier1_buy_discount) ) $this->set_error("err6_14{$this->popup_id}");
						if ( ! $this->check->is_percent($tier2_buy_discount) ) $this->set_error("err6_18{$this->popup_id}");
						if ( $tier3_buy_discount && ! $this->check->is_percent($tier3_buy_discount) ) $this->set_error("err6_22{$this->popup_id}");
						if ( $tier4_buy_discount && ! $this->check->is_percent($tier4_buy_discount) ) $this->set_error("err6_26{$this->popup_id}");

						if ( $tier1_sell_discount && ! $this->check->is_percent($tier1_sell_discount) ) $this->set_error("err6_15{$this->popup_id}");
						if ( $tier1_install_sell && ! $this->check->is_percent($tier1_install_sell) ) $this->set_error("err6_28{$this->popup_id}");

						if ( $tier2_sell_discount && ! $this->check->is_percent($tier2_sell_discount) ) $this->set_error("err6_19{$this->popup_id}");
						if ( $tier2_install_sell && ! $this->check->is_percent($tier2_install_sell) ) $this->set_error("err6_29{$this->popup_id}");

						if ( $tier3_sell_discount && ! $this->check->is_percent($tier3_sell_discount) ) $this->set_error("err6_23{$this->popup_id}");
						if ( $tier3_install_sell && ! $this->check->is_percent($tier3_install_sell) ) $this->set_error("err6_30{$this->popup_id}");

						if ( $tier4_sell_discount && ! $this->check->is_percent($tier4_sell_discount) ) $this->set_error("err6_27{$this->popup_id}");
						if ( $tier4_install_sell && ! $this->check->is_percent($tier4_install_sell) ) $this->set_error("err6_31{$this->popup_id}");

						return $this->__trigger_error("The percentage you entered for the indicated field is invalid. Percentages should be entered as numbers, not decimals. For example, to enter 65.35%, enter it as 65.35, not .6535.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

					$tier1_from = .01;
					$tier2_from = bcadd($tier1_to, .01, 2);
					$tier3_from = bcadd($tier2_to, .01, 2);
					$tier4_from = bcadd($tier3_to, .01, 2);
					$tier5_from = bcadd($tier4_to, .01, 2);
					$tier6_from = bcadd($tier5_to, .01, 2);

					if ( ! $tier6_buy_discount )
						unset($tier6_from, $tier6_to, $tier6_buy_discount, $tier6_sell_discount);
					else
						unset($tier6_to);

					if ( ! $tier5_buy_discount )
						unset($tier6_from, $tier6_to, $tier6_buy_discount, $tier6_sell_discount, $tier4_to, $tier5_from, $tier5_sell_discount);
					elseif ( ! $tier4_buy_discount )
						unset($tier3_to);

					if ( ! $tier4_sell_discount )
						unset($tier4_sell_discount);
					if ( ! $tier3_sell_discount )
						unset($tier3_sell_discount);
					if ( ! $tier2_sell_discount )
						unset($tier2_sell_discount);
					if ( ! $tier1_sell_discount )
						unset($tier1_sell_discount);

					if ( ! $this->discounting->item_hash ) { # Create new product discount

						if ( $disc_type == 3 )
    						$disc_loop =& $discount_code;
    					else
        					$disc_loop =& $item_discount;

						if ( $disc_loop && ! is_array($disc_loop) )
    						$disc_loop = array($disc_loop);
                        elseif ( ! is_array($disc_loop) || ! count($disc_loop) )
                            $disc_loop = array(NULL);

                        if ( $product_hash && ! is_array($product_hash) )
                            $product_hash = array($product_hash);
                        elseif ( ! $product_hash || ! is_array($product_hash) )
                            $product_hash = array(NULL);

						for ( $i = 0; $i < count($product_hash); $i++ ) {

							reset($disc_loop);
                            foreach ( $disc_loop as $loop_disc_key => $loop_disc_val ) {

								$item_hash = rand_hash('discount_details', 'item_hash');

								if ( ! $this->db->query("INSERT INTO discount_details
    													 (
	    													 discount_hash,
	    													 item_hash,
	    													 product_hash, " .
                            								 ( $disc_type == 2 || $disc_type == 3 ?
                                								 ( $disc_type == 2 ?
                                    								 "item_no, " : "discount_code, "
                                								 ) : NULL
                                							 ) .
                                							 ( $disc_type == 3 ?
                                    							 "discount_descr, " : NULL
                                							 ) . "
	    													 discount_frf,
	    													 discount_frf_quoted,
	    													 discount_type,
	    													 tier1_from,
	    													 tier1_to,
	    													 tier1_buy_discount,
	    													 tier1_sell_discount,
	    													 tier1_install_sell,
	    													 tier2_from,
	    													 tier2_to,
	    													 tier2_buy_discount,
	    													 tier2_sell_discount,
	    													 tier2_install_sell,
	    													 tier3_from,
	    													 tier3_to,
	    													 tier3_buy_discount,
	    													 tier3_sell_discount,
	    													 tier3_install_sell,
	    													 tier4_from,
	    													 tier4_to,
	    													 tier4_buy_discount,
	    													 tier4_sell_discount,
	    													 tier4_install_sell,
	    													 tier5_from,
	    													 tier5_to,
	    													 tier5_buy_discount,
	    													 tier5_sell_discount,
	    													 tier6_from,
	    													 tier6_to,
	    													 tier6_buy_discount,
	    													 tier6_sell_discount
    													 )
    													 VALUES
    													 (
	    													 '$discount_hash',
	    													 '$item_hash', " .
	    													 ( $product_hash[$i] ?
    	    													 "'{$product_hash[$i]}'" : "NULL"
	    													 ) . ", " .
	    													 ( $disc_type == 2 || $disc_type == 3 ?
		    													 ( $loop_disc_val ?
    		    													 "'$loop_disc_val', " : "NULL, "
		    													 ) : NULL
	    													 ) . "
	    													 '$discount_frf',
	    													 '$discount_frf_quoted',
	    													 '$discount_type',
	    													 '$tier1_from',
	    													 '$tier1_to',
	    													 '$tier1_buy_discount',
	    													 '$tier1_sell_discount',
	    													 '$tier1_install_sell',
	    													 '$tier2_from',
	    													 '$tier2_to',
	    													 '$tier2_buy_discount',
	    													 '$tier2_sell_discount',
	    													 '$tier2_install_sell',
	    													 '$tier3_from',
	    													 '$tier3_to',
	    													 '$tier3_buy_discount',
	    													 '$tier3_sell_discount',
	    													 '$tier3_install_sell',
	    													 '$tier4_from',
	    													 '$tier4_to',
	    													 '$tier4_buy_discount',
	    													 '$tier4_sell_discount',
	    													 '$tier4_install_sell',
	    													 '$tier5_from',
	    													 '$tier5_to',
	    													 '$tier5_buy_discount',
	    													 '$tier5_sell_discount',
	    													 '$tier6_from',
	    													 '$tier6_to',
	    													 '$tier6_buy_discount',
	    													 '$tier6_sell_discount'
    													 )")
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                }
                            }
						}

					} else { # Update an existing product discount

    					$sql = array();

                        ( $this->discounting->current_item['item_no'] != $item_discount ? array_push($sql, "t1.item_no = '$item_discount'" ) : NULL );
						( $this->discounting->current_item['discount_type'] != $discount_type ? array_push($sql, "t1.discount_type = '$discount_type'" ) : NULL );
						( bccomp($this->discounting->current_item['tier1_from'], $tier1_from, 2) ? array_push($sql, "t1.tier1_from = '$tier1_from'" ) : NULL );
						( bccomp($this->discounting->current_item['tier1_to'], $tier1_to, 2) ? array_push($sql, "t1.tier1_to = '$tier1_to'" ) : NULL );
						( bccomp($this->discounting->current_item['tier1_buy_discount'], $tier1_buy_discount, 4) ? array_push($sql, "t1.tier1_buy_discount = '$tier1_buy_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier1_sell_discount'], $tier1_sell_discount, 4) ? array_push($sql, "t1.tier1_sell_discount = '$tier1_sell_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier1_install_sell'], $tier1_install_sell, 4) ? array_push($sql, "t1.tier1_install_sell = '$tier1_install_sell'" ) : NULL );
						( bccomp($this->discounting->current_item['tier2_from'], $tier2_from, 2) ? array_push($sql, "t1.tier2_from = '$tier2_from'" ) : NULL );
						( bccomp($this->discounting->current_item['tier2_to'], $tier2_to, 2) ? array_push($sql, "t1.tier2_to = '$tier2_to'" ) : NULL );
						( bccomp($this->discounting->current_item['tier2_buy_discount'], $tier2_buy_discount, 4) ? array_push($sql, "t1.tier2_buy_discount = '$tier2_buy_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier2_sell_discount'], $tier2_sell_discount, 4) ? array_push($sql, "t1.tier2_sell_discount = '$tier2_sell_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier2_install_sell'], $tier2_install_sell, 4) ? array_push($sql, "t1.tier2_install_sell = '$tier2_install_sell'" ) : NULL );
						( bccomp($this->discounting->current_item['tier3_from'], $tier3_from, 2) ? array_push($sql, "t1.tier3_from = '$tier3_from'" ) : NULL );
						( bccomp($this->discounting->current_item['tier3_to'], $tier3_to, 2) ? array_push($sql, "t1.tier3_to = '$tier3_to'" ) : NULL );
						( bccomp($this->discounting->current_item['tier3_buy_discount'], $tier3_buy_discount, 4) ? array_push($sql, "t1.tier3_buy_discount = '$tier3_buy_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier3_sell_discount'], $tier3_sell_discount, 4) ? array_push($sql, "t1.tier3_sell_discount = '$tier3_sell_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier3_install_sell'], $tier3_install_sell, 4) ? array_push($sql, "t1.tier3_install_sell = '$tier3_install_sell'" ) : NULL );
						( bccomp($this->discounting->current_item['tier4_from'], $tier4_from, 2) ? array_push($sql, "t1.tier4_from = '$tier4_from'" ) : NULL );
						( bccomp($this->discounting->current_item['tier4_to'], $tier4_to, 2) ? array_push($sql, "t1.tier4_to = '$tier4_to'" ) : NULL );
						( bccomp($this->discounting->current_item['tier4_buy_discount'], $tier4_buy_discount, 4) ? array_push($sql, "t1.tier4_buy_discount = '$tier4_buy_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier4_sell_discount'], $tier4_sell_discount, 4) ? array_push($sql, "t1.tier4_sell_discount = '$tier4_sell_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier4_install_sell'], $tier4_install_sell, 4) ? array_push($sql, "t1.tier4_install_sell = '$tier4_install_sell'" ) : NULL );
						( bccomp($this->discounting->current_item['tier5_from'], $tier5_from, 2) ? array_push($sql, "t1.tier5_from = '$tier5_from'" ) : NULL );
						( bccomp($this->discounting->current_item['tier5_to'], $tier5_to, 2) ? array_push($sql, "t1.tier5_to = '$tier5_to'" ) : NULL );
						( bccomp($this->discounting->current_item['tier5_buy_discount'], $tier5_buy_discount, 4) ? array_push($sql, "t1.tier5_buy_discount = '$tier5_buy_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier5_sell_discount'], $tier5_sell_discount, 4) ? array_push($sql, "t1.tier5_sell_discount = '$tier5_sell_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier6_from'], $tier6_from, 2) ? array_push($sql, "t1.tier6_from = '$tier6_from'" ) : NULL );
						( bccomp($this->discounting->current_item['tier6_to'], $tier6_to, 2) ? array_push($sql, "t1.tier6_to = '$tier6_to'" ) : NULL );
						( bccomp($this->discounting->current_item['tier6_buy_discount'], $tier6_buy_discount, 4) ? array_push($sql, "t1.tier6_buy_discount = '$tier6_buy_discount'" ) : NULL );
						( bccomp($this->discounting->current_item['tier6_sell_discount'], $tier6_sell_discount, 4) ? array_push($sql, "t1.tier6_sell_discount = '$tier6_sell_discount'" ) : NULL );
						( $this->discounting->current_item['discount_frf'] != $discount_frf ? array_push($sql, "t1.discount_frf = '$discount_frf'" ) : NULL );
						( $this->discounting->current_item['discount_frf_quoted'] != $discount_frf_quoted ? array_push($sql, "t1.discount_frf_quoted = '$discount_frf_quoted'" ) : NULL );

						if ( $sql ) {

							if ( ! $this->db->query("UPDATE discount_details t1
        											 SET " . implode(", ", $sql) . "
        											 WHERE t1.discount_hash = '{$this->discounting->discount_hash}' && t1.item_hash = '{$this->discounting->item_hash}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
						}
					}
				}

			} else {

				$this->set_error("err6_7{$this->popup_id}");
                return $this->__trigger_error("You left some required fields blank when creating your product discount. Please check the indicated fields and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
			}
		}

		$this->db->end_transaction();

		unset($this->ajax_vars, $this->discounting);
		$this->fetch_master_record($this->customer_hash);
		$this->ajax_vars['customer_hash'] = $this->customer_hash;
		$this->ajax_vars['discount_hash'] = $discount_hash;

		$this->ajax_vars['p'] = $p;
		$this->ajax_vars['order'] = $order;
		$this->ajax_vars['order_dir'] = $order_dir;

		$this->ajax_vars['action'] = ( $discount_next_action ? $discount_next_action : 'showtable' );
		$this->content['action'] = 'continue';
		$this->content['html']["tcontent5{$this->popup_id}"] = $this->discount_info_form();
	}

	function doit_receive_payment() {

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

		$customer = $_POST['paying_customer'];
		$customer_hash = $_POST['customer_hash'];
		$final = $_POST['final'];
		$is_vendor = $_POST['is_vendor'];

        $submit_btn = 'rcv_btn';

		if ( $customer && ! $customer_hash ) {

			$this->set_error("err1{$this->popup_id}");
			return $this->__trigger_error("The customer below is invalid. Please make sure you select a valid customer.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
		}

		if ( $final ) {

			if ( $is_vendor ) {

				$vendors = new vendors;
				if ( ! $vendors->fetch_master_record($customer_hash) )
		    		return $this->__trigger_error("System error encountered when attempting to lookup direct bill vendor record for cash receipt. Please reload window and try again. <!-- Tried fetching vendor [ $customer_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			} else {

				if ( ! $this->fetch_master_record($customer_hash) )
		    		return $this->__trigger_error("System error encountered when attempting to lookup customer record for cash receipt. Please reload window and try again. <!-- Tried fetching customer [ $customer_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			$unapplied = $_POST['unapplied'];
			$payment_date = $_POST['payment_date'];
			$amount_received = $remaining_amt = preg_replace('/[^-.0-9]/', "", $_POST['amount_received']);
			$payment_method = $_POST['payment_method'];
			$comments = $_POST['comments'];
			$invoice_hash = $_POST['invoice_hash'];
			$pay_amount = $_POST['pay_amount'];
			$overpayment_action = $_POST['overpayment_action'];
			$overpayment_invoice = $_POST['overpayment_invoice'];
			$overpayment_product = $_POST['overpayment_product'];
			$payment_account_hash = $_POST['payment_account_hash'];
			$currency = $_POST['currency'];

			$submit_btn = 'rcv_btn';

			$accounting = new accounting($this->current_hash);

            if ( $currency ) {

                if ( defined('MULTI_CURRENCY') ) {

                    $sys = new system_config();
                    if ( ! $sys->home_currency )
                        return $this->__trigger_error("The default home currency has not been defined in your system configuration! This invoice was created with multiple currencies enabled and cannot be edited without multiple currencies enabled.<br /><br />Please check with your system administrator.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

                    if ( ! valid_account($this->db, $sys->home_currency['account_hash']) )
                        return $this->__trigger_error("You have not defined a valid chart of account to be used for capturing gain/loss on currency exchange.", E_USER_NOTICE, __FILE__, __LINE__, 1, $submit_btn);

                    if ( ! $sys->fetch_currency($currency) )
                        return $this->__trigger_error("The currency you have entered has not been setup under the system configuration. Please check with your system administrator. <!-- Tried fetching currency [ $currency ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                    $payment_currency = $sys->currency_id;
                    $payment_rate = $sys->current_currency['rate'];

                    $amount_received = currency_exchange($amount_received, $payment_rate, 1);

                } else
                    return $this->__trigger_error("In order to receive this payment in the currency you specified, please check that the system has currency exchange enabled. Check with your system administrator." , E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
            }

            $invoices = array();
			for ( $i = 0; $i < count($invoice_hash); $i++ ) {

				if ( $invoice_hash[$i] )
					$invoices[] = $invoice_hash[$i];
			}

			if ( ! $payment_account_hash )
				return $this->__trigger_error("You have not indicated in which account you want to receive this payment.<br />Make sure you have defined at least 1 checking account. You can do this by checking the 'Will you write checks from this account' box from within the chart of accounts 'General' tab.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

			$amount_to_pay = 0;
			if ( $payment_currency )
                $uncoverted_amount = array();

			$invoice = new customer_invoice($this->current_hash);

			for ( $i = 0; $i < count($invoices); $i++ ) {

				if ( ! $invoice->fetch_invoice_record($invoices[$i]) )
    				return $this->__trigger_error("System error when attempting to fetch invoice record. Please reload window and try again. <!-- Tried fetching invoice [ $invoices[$i] ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$pay_amount[ $invoices[$i] ] = preg_replace('/[^-.0-9]/', "", $pay_amount[ $invoices[$i] ]);
				if ( $payment_currency ) {

					$uncoverted_amount[ $invoices[$i] ] = $pay_amount[ $invoices[$i] ];
                    $pay_amount[ $invoices[$i] ] = currency_exchange($pay_amount[ $invoices[$i] ], $payment_rate, 1);
				}

				if ( bccomp($pay_amount[ $invoices[$i] ], 0, 2) == 1 ) {

					if ( bccomp($pay_amount[ $invoices[$i] ], $invoice->current_invoice['balance'], 2) == 1 || bccomp(0, $pay_amount[ $invoices[$i] ], 2) == 1 ) {

						$this->set_error("err_{$invoices[$i]}");
						$invoice_pay_error = 1;
					}

					$remaining_amt = bcsub($remaining_amt, $pay_amount[ $invoices[$i] ], 2);
					$amount_to_pay = bcadd($amount_to_pay, $pay_amount[ $invoices[$i] ], 2);
				}
			}

			if ( $invoice_pay_error )
                return $this->__trigger_error("The amount you entered for the invoice(s) indicated below is invalid. Please check and make sure the applied payment does not exceed the invoice balance and has not been entered as a negative amount.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

			if ( ( bccomp($amount_received, 0, 2) != 1 || bccomp($amount_to_pay, 0, 2) != 1 ) && ! $unapplied )
				return $this->__trigger_error("Please make sure you have entered a valid payment amount and that you've selected which invoices and/or unapplied deposits to receive the payment against.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
            elseif ( $unapplied && bccomp($amount_received, 0, 2) != 1 )
				return $this->__trigger_error("You have not entered a receipt amount! Please enter a receipt amount to continue.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

            if ( bccomp($amount_to_pay, $amount_received, 2) && ! $overpayment_action && ! $unapplied )
                return $this->__trigger_error("The total payment distribution below " . ( bccomp($amount_to_pay, $amount_received, 2) == 1 ? "exceeds" : "is less than" ) . " the total receipt amount. Please make sure you have accounted for any over or under payments.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

			if ( ( ! $unapplied && bccomp($remaining_amt, 0, 2) == 1 && ! $overpayment_action ) || ( ! $unapplied && bccomp($remaining_amt, 0, 2) == 1 && $overpayment_action == 2 && ( ! $overpayment_product || ! $overpayment_invoice ) ) ) {

				$this->jscript_action("toggle_display('overpayment_action_holder{$this->popup_id}', 'block');");
				if ( bccomp($remaining_amt, 0, 2) == 1 && $overpayment_action == 2 && ( ! $overpayment_product || ! $overpayment_invoice ) )
					return $this->__trigger_error("In order to absort the overpayment as profit you must assign a product/service as well as invoice to be used to absorb the additional money. The product/service will be added as an additional line item on the selected invoice.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				else
					return $this->__trigger_error("Your customer has overpaid by \$" . number_format($remaining_amt, 2) . " and you've not indicated what to do with the remaining amount. Please select the action to be taken against the overpayment amount.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			if ( ( $payment_method == 'CK' || $payment_method == 'CC' ) && ! $comments )
				return $this->__trigger_error("If your customer is paying by check or credit card please enter a check or transaction number before continuing.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

			list($year, $month, $day) = explode("-", $payment_date);

			if ( ! checkdate($month, $day, $year) || $accounting->period_ck($payment_date) ) {

				if ( $accounting->period_ck($payment_date) ) $this->set_error_msg($accounting->closed_err);
				if ( ! checkdate($month, $day, $year) ) $this->set_error_msg("The receipt date you entered is not valid. Please enter a valid date.");
				return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			if ( ! $this->db->start_transaction() )
				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			if ( $unapplied ) { # Unapplied option appearing only when no open invoices exist

				$invoice_hash = rand_hash('customer_invoice', 'invoice_hash');
				$payment_hash = rand_hash('customer_payment', 'payment_hash');

				$accounting->start_transaction( array(
					'ar_invoice_hash'   =>  $invoice_hash,
					'ar_payment_hash'   =>  $payment_hash,
				    'currency'          =>  $payment_currency,
				    'exchange_rate'     =>  $payment_rate
				) );

				if ( $is_vendor ) {

					$accounting->setTransIndex( array(
						'vendor_hash'	=>	$customer_hash
					) );
				} else {

					$accounting->setTransIndex( array(
						'customer_hash'	=>	$customer_hash
					) );
				}

				$accounting->setTransIndex( array(
    				'proposal_hash'
    			), 1);

                $audit_id = $accounting->audit_id();

				if ( ! $accounting->exec_trans($audit_id, $payment_date, DEFAULT_CUST_DEPOSIT_ACCT, $remaining_amt, 'CR', 'Unapplied deposit receipt') )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				if ( ! $accounting->exec_trans($audit_id, $payment_date, $payment_account_hash, $remaining_amt, 'CR', 'Unapplied deposit receipt') )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( ! $this->db->query("INSERT INTO customer_invoice
        								 (
	        								 timestamp,
	        								 last_change,
	        								 invoice_hash,
	        								 invoice_to,
	        								 type,
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
	        								 '" . ( $is_vendor ? 'V' : 'C' ) . "',
	        								 'D',
	        								 '$customer_hash',
	        								 '$payment_date',
	        								 '$remaining_amt',
	        								 '$remaining_amt' " .
	        								 ( $payment_currency ?
	            								 ",
	            								 '$payment_currency',
	            								 '{$payment_rate}',
	            								 '{$sys->home_currency['account_hash']}'" : NULL
	            							 ) . "
            							 )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

				if ( ! $this->db->query("INSERT INTO customer_payment
        								 (
	        								 timestamp,
	        								 last_change,
	        								 payment_hash,
	        								 invoice_hash,
	        								 receipt_amount,
	        								 account_hash,
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
	        								 '$remaining_amt',
	        								 '$payment_account_hash',
	        								 '$payment_date',
	        								 '" . addslashes($comments) . "',
	        								 '" . addslashes($comments) . "' " .
	        								 ( $payment_currency ?
	            								 ",
	            								 '$payment_currency',
	            								 '$payment_rate'" : NULL
	            							 ) . "
            							 )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

				if ( ! $accounting->end_transaction() )
					return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$this->db->end_transaction();

				$f = "Your receipt has been recorded as unapplied against the customer";

			} else {

				for ( $i = 0; $i < count($invoices); $i++ ) {

                    if ( ! $invoice->fetch_invoice_record($invoices[$i]) )
                        return $this->__trigger_error("System error encountered when attempting to fetch invoice record. Please reload window and try again. <!-- Tried to fetch invoice [ {$invoices[$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                    if ( bccomp($pay_amount[ $invoices[$i] ], 0, 2) == 1 ) {

                        $payment_hash = rand_hash('customer_payment', 'payment_hash');

                        $accounting->start_transaction( array(
                            'ar_invoice_hash'    =>  $invoice->invoice_hash,
                            'ar_payment_hash'    =>  $payment_hash,
                            'proposal_hash'      =>  $invoice->current_invoice['proposal_hash'],
                            'currency'           =>  $payment_currency,
                            'exchange_rate'      =>  $payment_rate
                        ) );

                        if ( $is_vendor ) {

                            $accounting->setTransIndex( array(
                                'vendor_hash'   =>  $customer_hash
                            ) );
                        } else {

                            $accounting->setTransIndex( array(
                                'customer_hash' =>  $customer_hash
                            ) );
                        }

                        $audit_id = $accounting->audit_id();
                        $currency_adj = 0;

                        if ( $payment_currency ) {

                            if ( $invoice->current_invoice['currency'] && $invoice->current_invoice['exchange_rate'] ) {

                                $adjusted_receipt_amount = currency_exchange($uncoverted_amount[ $invoices[$i] ], $invoice->current_invoice['exchange_rate'], 1, 6); # Receipt amount per invoice rate
                                $currency_adj = _round( bcsub($adjusted_receipt_amount, $pay_amount[ $invoices[$i] ], 6), 2);
                            }
                        }

                        if ( ! $this->db->query("INSERT INTO customer_payment
                                                 (
                                                     timestamp,
                                                     last_change,
                                                     payment_hash,
                                                     invoice_hash,
                                                     receipt_amount,
                                                     currency_adjustment,
                                                     account_hash,
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
                                                     '{$invoice->invoice_hash}',
                                                     '{$pay_amount[ $invoice->invoice_hash ]}',
                                                     '$currency_adj',
                                                     '$payment_account_hash',
                                                     '$payment_date',
                                                     '" . addslashes($comments) . "',
                                                     '" . addslashes($comments) . "' " .
                                                     ( $payment_currency ?
                                                         ",
                                                         '$payment_currency',
                                                         '$payment_rate'" : NULL
                                                     ) . "
                                                 )")
                        ) {

                            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

                        $balance = bcsub($invoice->current_invoice['balance'], bcadd($pay_amount[ $invoice->invoice_hash ], $currency_adj, 2), 2);
                        if ( ! $this->db->query("UPDATE customer_invoice t1
                                                 SET
                                                     t1.timestamp = UNIX_TIMESTAMP(),
                                                     t1.last_change = '{$this->current_hash}',
                                                     t1.balance = '$balance' " .
                                                     ( bccomp($balance, 0, 2) != 1 ?
                                                         ",
                                                         t1.paid_in_full = 1" : NULL
                                                     ) . "
                                                 WHERE t1.invoice_hash = '{$invoice->invoice_hash}'")
                        ) {

                            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

                        if ( ! $accounting->exec_trans($audit_id, $payment_date, $payment_account_hash, $pay_amount[ $invoice->invoice_hash ], 'CR', 'Payment received') )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        if ( ! $accounting->exec_trans($audit_id, $payment_date, DEFAULT_AR_ACCT, bcmul($pay_amount[ $invoice->invoice_hash ], -1, 2), 'CR', 'Payment received') )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                        if ( $invoice->current_invoice['currency'] && bccomp($currency_adj, 0, 2) ) {

                            $currency_adj_exec = $currency_adj;
                            if ( $accounting->account_action($invoice->current_invoice['currency_account']) == 'DR' )
                                $currency_adj_exec = bcmul($currency_adj_exec, -1, 2);

                            if ( ! $accounting->exec_trans($audit_id, $payment_date, $invoice->current_invoice['currency_account'], bcmul($currency_adj_exec, -1, 2), 'CR', 'Gain/Loss on currency exchange') )
                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                            if ( ! $accounting->exec_trans($audit_id, $payment_date, DEFAULT_AR_ACCT, bcmul($currency_adj, -1, 2), 'CR', 'Gain/Loss on currency exchange') )
                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

                        if ( bccomp($balance, 0, 2) != 1 ) {

                            $invoice_hash = $invoice->invoice_hash;
                            $lines = new line_items($invoice->proposal_hash);
                            $line_items = $lines->fetch_line_items_short();
                            for ( $j = 0; $j < count($line_items); $j++ ) {

                                if ( $line_items[$j]['status'] && $line_items[$j]['invoice_hash'] != $invoice_hash ) {

                                    if ( $line_items[$j]['invoice_hash'] ) {

                                        $invoice->fetch_invoice_record($line_items[$j]['invoice_hash']);
                                        if ( ! $this->current_invoice['paid_in_full'] ) {

                                            $not_final = $line_items[$j]['invoice_hash'];
                                            break;
                                        }

                                    } else
                                        break;

                                }
                            }
                            if ( ! $not_final )
                                $proposal_invoiced[] = $invoice->proposal_hash;
                        }

                        if ( ! $accounting->end_transaction() )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }
				}

				if ( bccomp($remaining_amt, 0, 2) == 1 ) { # Auto-Prompt options appearing when overpayment balance exists

					if ( $overpayment_action == 1 ) { # Save it as unapplied to be used later

						$invoice_hash = rand_hash('customer_invoice', 'invoice_hash');
                        $payment_hash = rand_hash('customer_payment', 'payment_hash');

						$accounting->start_transaction( array(
							'ar_invoice_hash'    =>  $invoice_hash,
							'ar_payment_hash'    =>  $payment_hash,
                            'currency'           =>  $payment_currency,
						    'exchange_rate'      =>  $payment_rate
                        ) );

                        if ( $is_vendor ) {

                        	$accounting->setTransIndex( array(
	                        	'vendor_hash'	=>	$customer_hash
                        	) );
                        } else {

                        	$accounting->setTransIndex( array(
	                        	'customer_hash'	=>	$customer_hash
                        	) );
                        }

                        $accounting->setTransIndex( array(
	                        'proposal_hash'
                        ), 1);

                        $audit_id = $accounting->audit_id();

						if ( ! $accounting->exec_trans($audit_id, $payment_date, DEFAULT_CUST_DEPOSIT_ACCT, $remaining_amt, 'CR', 'Customer overpayment (saved as unapplied)') )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
						if ( ! $accounting->exec_trans($audit_id, $payment_date, $payment_account_hash, $remaining_amt, 'CR', 'Customer overpayment (saved as unapplied)') )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						if ( ! $this->db->query("INSERT INTO customer_invoice
        										 (
	        										 timestamp,
	        										 last_change,
	        										 invoice_hash,
	        										 invoice_to,
	        										 type,
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
	        										 '" . ( $is_vendor ? 'V' : 'C' ) . "',
	        										 'D',
	        										 '$customer_hash',
	        										 '$payment_date',
	        										 '$remaining_amt',
	        										 '$remaining_amt' " .
	        										 ( $payment_currency ?
	            										 ",
	            										 '$payment_currency',
	            										 '$payment_rate',
	            										 '{$sys->home_currency['account_hash']}'" : NULL
	            									 ) . "
            									 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						if ( ! $this->db->query("INSERT INTO customer_payment
        										 (
	        										 timestamp,
	        										 last_change,
	        										 payment_hash,
	        										 invoice_hash,
	        										 receipt_amount,
	        										 account_hash,
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
	        										 '$remaining_amt',
	        										 '$payment_account_hash',
	        										 '$payment_date',
	        										 '" . addslashes($comments) . "',
	        										 '" . addslashes($comments) . "' " .
	        										 ( $payment_currency ?
	            										 ",
	            										 '$payment_currency',
	            										 '$payment_rate'" : NULL
	            									 ) . "
            									 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

                        if ( ! $accounting->end_transaction() )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

					} elseif ( $overpayment_action == 3 ) {

						$invoice = new customer_invoice($this->current_hash);
						if ( ! $invoice->fetch_invoice_record($invoice_hash) )
							return $this->__trigger_error("System error when attempting to fetch invoice record. Please reload window and try again. <!-- Tried fetching invoice [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						$bill_hash = rand_hash('vendor_payables', 'invoice_hash');

						$accounting->start_transaction( array(
							'vendor_hash'     =>  $invoice->current_invoice['customer_hash'],
							'ap_invoice_hash' =>  $bill_hash,
							'proposal_hash'   =>  $invoice->current_invoice['proposal_hash'],
							'currency'        =>  $payment_rate,
							'exchange_rate'   =>  $payment_currency
                        ) );

                        $audit_id = $accounting->audit_id();

						if ( ! $accounting->exec_trans($audit_id, $payment_date, DEFAULT_CUST_DEPOSIT_ACCT, $remaining_amt, 'CR', 'Customer overpayment (refund)') )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
						if ( ! $accounting->exec_trans($audit_id, $payment_date, $payment_account_hash, $remaining_amt, 'CR', 'Customer overpayment (refund)') )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						if ( ! defined('DEFAULT_CUST_DEPOSIT_ACCT') || ! $accounting->fetch_account_record(DEFAULT_CUST_DEPOSIT_ACCT) )
							return $this->__trigger_error("Unable to fetch default customer deposit account. " . ( ! defined('DEFAULT_CUST_DEPOSIT_ACCT') ? "Your default customer deposit account is not defined properly. Please check your accounting system settings." : "System error encountered when attempting to lookup default customer deposit account. <!-- Tried to fetch account [" . DEFAULT_CUST_DEPOSIT_ACCT . "] -->" ), E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						$account_name = $accounting->current_account['account_name'];
						$account_hash = $accounting->account_hash;
						$memo = "Customer refund for overpayment.";

						$expense_hash = rand_hash('vendor_payable_expenses', 'expense_hash');

						if ( ! $this->db->query("INSERT INTO vendor_payable_expenses
        										 VALUES
        										 (
	        										 NULL,
	        										 UNIX_TIMESTAMP(),
	            									 '$expense_hash',
	            									 '$bill_hash',
	            									 '$account_hash',
	            									 NULL,
	            									 '$remaining_amt',
	            									 '" . addslashes($memo) . "'
            									 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						if ( ! $accounting->exec_trans($audit_id, $payment_date, $account_hash, bcmul($remaining_amt, -1, 2), 'AP', $memo) )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						if ( ! $this->db->query("INSERT INTO vendor_payables
										         (
											         timestamp,
											         last_change,
											         invoice_hash,
											         type,
											         complete,
											         pay_flag,
											         po_hash,
											         vendor_hash,
											         invoice_no,
											         invoice_date,
											         receipt_date,
											         due_date,
											         amount,
											         balance,
											         notes " .
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
        										 '$bill_hash',
        										 'R',
        										 1,
        										 1,
        										 '$invoice_hash',
        										 '{$invoice->current_invoice['customer_hash']}',
        										 '{$invoice->current_invoice['invoice_no']}',
        										 '$payment_date',
        										 '$payment_date',
        										 DATE_FORMAT( DATE_ADD( '$payment_date', INTERVAL " . ( defined('CUSTOMER_REFUND_PAY_DAYS') ? CUSTOMER_REFUND_PAY_DAYS : 15 ) . " DAY), '%Y-%m-%d'),
        										 '$remaining_amt',
        										 '$remaining_amt',
        										 'Refund for overpayment on invoice {$invoice->current_invoice['invoice_no']}' " .
        										 ( $payment_currency ?
            										 ",
            										 '$payment_currency',
            										 '$payment_rate',
            										 '{$sys->home_currency['account_hash']}'" : NULL
            									 ) . "
            									 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						if ( ! $accounting->exec_trans($audit_id, $payment_date, DEFAULT_AP_ACCT, $remaining_amt, 'AP', "Customer refund for invoice {$invoice->current_invoice['invoice_no']}") )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                        if ( ! $accounting->end_transaction() )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

					}
				}

                for ( $i = 0; $i < count($proposal_invoiced); $i++ ) {

					if ( ! $this->db->query("UPDATE proposals t1
        					                 SET t1.invoiced = 1
        					                 WHERE t1.proposal_hash = '{$proposal_invoiced[$i]}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }
                }

                $this->db->end_transaction();
			}

			$feedback = ( $f ? $f : "Your receipt has been recorded and has been applied to the selected invoice(s)." );
			$this->content['action'] = 'close';
			$this->content['page_feedback'] = $feedback;

			return;

		} else {

			$this->db->define('convert_currency', 1);

			if ( $is_vendor ) {

				$this->current_customer = array();

				$vendors = new vendors;
				if ( ! $vendors->fetch_master_record($customer_hash) )
					return $this->__trigger_error("Unable to lookup vendor record for cash receipt. Please check customer/vendor selection and try again. <!-- Tried fetching vendor [ $customer_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

				$this->current_customer =& $vendors->current_vendor;

				# TODO: Add currency exchange to select query
				$r = $this->db->query("SELECT SUM( t1.balance ) AS balance
									   FROM customer_invoice t1
									   WHERE t1.customer_hash = '{$vendors->vendor_hash}' AND t1.invoice_to = 'V' AND t1.deleted = 0");
				$this->current_customer['balance_total'] = $this->db->result($r, 0, 'balance');

				$this->content['jscript'][] = "\$('is_vendor').value = '1';";

			} else {

				if ( ! $this->fetch_master_record($customer_hash) )
    				return $this->__trigger_error("Unable to lookup customer record for cash receipt. Please check customer selection and try again. <!-- Tried fetching customer [ $customer_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

    			$this->content['jscript'][] = "\$('is_vendor').value = '';";
			}

			$this->content['jscript'][] = "\$('balance_parent_{$this->popup_id}').show();";

			if ( defined('MULTI_CURRENCY') ) {

                $sys = new system_config();
                if ( $this->current_customer['currency'] && $this->current_customer['currency'] != $sys->home_currency['code'] ) {

                	$this->content['jscript'][] = "\$('currency_holder_{$this->popup_id}').show();";
                	$this->content['html']["currency_select_{$this->popup_id}"] = $this->form->select(
                    	"currency",
						array($this->current_customer['currency'], $sys->home_currency['code']),
						$this->current_customer['currency'],
						array($this->current_customer['currency'], $sys->home_currency['code']),
						"blank=1",
						"style=width:75px;"
					);
                } else {

                    $this->content['jscript'][] = "\$('currency_holder_{$this->popup_id}').hide();";
                    $this->content['html']["currency_select_{$this->popup_id}"] = '';
                }
			}

			$this->content['html']["balance_holder{$this->popup_id}"] = "\$" . number_format($this->current_customer['balance_total'], 2);
			$this->content['html']["inner_tbl_holder{$this->popup_id}"] = $this->receive_payment($customer_hash);
			$this->content['action'] = 'continue';

			return;
		}
	}

	function doit_search() {
		$name = trim($_POST['customer']);
		$customer_no = preg_replace('/[^0-9]/', "", $_POST['customer_no']);
		$account_no = trim($_POST['account_no']);
		$state = $_POST['state'];
		$country = $_POST['country'];
		$tax_id = trim($_POST['tax_id']);
		$credit_limit = preg_replace('/[^-.0-9]/', "", $_POST['credit_limit']);
		$currency = $_POST['currency'];
		$discount_id = trim($_POST['discount_id']);
		$gsa = $_POST['gsa'];
		$po_req = $_POST['po_req'];
		$sales_hash = $_POST['sales_hash'];

		if ($name){
			$name = addslashes($name);
			$sql_p[] = "( customers.customer_name LIKE '{$name}%' OR locations.location_name LIKE '{$name}%' )";
		}
		
		if ($customer_no)
			$sql_p[] = "customers.customer_no LIKE '%".$customer_no."%'";
        if ($account_no)
            $sql_p[] = "(customers.account_no LIKE '%".$account_no."%' OR locations.location_account_no LIKE '%".$account_no."%')";
        if ($state)
            $sql_p[] = "customers.state = '".$state."'";
        if ($country)
            $sql_p[] = "customers.country = '".$country."'";
        if ($tax_id)
            $sql_p[] = "customers.tax_exempt_id = '".$tax_id."'";
        if ($credit_limit != 0)
            $sql_p[] = "customers.credit_limit BETWEEN '".($credit_limit - 250)."' AND '".($credit_limit + 250)."'";
        if ($currency)
            $sql_p[] = "customers.currency = '".$currency."'";
        if ($discount_id)
            $sql_p[] = "discounting.discount_id = '".$discount_id."'";
        if ($gsa)
            $sql_p[] = "customers.gsa = '1'";
        if ($po_req)
            $sql_p[] = "customers.po_required = '1'";
		if ($sales_hash)
			$sql_p[] = "(proposals.sales_hash = '$sales_hash' OR proposals.sales_hash_2 = '$sales_hash')";

		if ($sql_p)
			$sql = implode(" AND ", $sql_p);

        $str = "name=$name|customer_no=$customer_no|account_no=$account_no|state=$state|country=$country|tax_id=$tax_id|credit_limit=$credit_limit|currency=$currency|discount_id=$discount_id|gsa=$gsa|po_req=$po_req|sales_hash=$sales_hash";

		$r = $this->db->query("SELECT customers.obj_id
							   FROM `customers`".($discount_id ? "
		                       LEFT JOIN discounting ON discounting.customer_hash = customers.customer_hash AND discounting.deleted = 0" : NULL)."
		                       LEFT JOIN locations ON locations.entry_hash = customers.customer_hash AND locations.deleted = 0
							   LEFT JOIN proposals ON customers.customer_hash = proposals.customer_hash
		                       WHERE ".($sql ? $sql." AND " : NULL)."customers.deleted = 0
							   GROUP BY customers.customer_hash");
		$total = $this->db->num_rows($r);

		$search_hash = md5(global_classes::get_rand_id(32, "global_classes"));
		while (global_classes::key_exists('search', 'search_hash', $search_hash))
			$search_hash = md5(global_classes::get_rand_id(32, "global_classes"));

		$this->db->query("INSERT INTO `search`
						  (`timestamp` , `search_hash` , `query` , `total` , `search_str`)
						  VALUES (".time()." , '$search_hash' , '".base64_encode($sql)."' , '$total' , '$str')");

		$this->active_search = $search_hash;

		$this->content['action'] = 'close';
		$this->content['jscript_action'] = "agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'active_search=".$this->active_search."');";
		return;
	}


	function doit() {
		global $err, $errStr, $us_states, $ca_states;

		$this->check = new Validator;
		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$next = $_POST['next_action'];
		$action = $_POST['action'];
		$customer_hash = $_POST['customer_hash'];

		$integrate = $_POST['integrate'];

		$jscript_action = $_POST['jscript_action'];
		$action = $_POST['action'];
		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		if ( $customer_hash && ! preg_match("/doit_receive_payment|amount_tracker/", $action) ) {

			if ( ! $this->fetch_master_record($customer_hash) )
                return $this->__trigger_error("Error encountered when trying to lookup customer record. Unable to continue with customer update, please close window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
		}

		$active_search = $_POST['active_search'];
		if ($active_search)
			$this->active_search = $active_search;

		if ($action)
			return $this->$action();

		if ($btn == 'AddContactLine')
			$this->doit_customer_contact($customer_hash);

		if ($btn == 'AddLocationLine')
			$this->doit_customer_location($customer_hash);

		if ($btn == 'AddDiscountLine')
			return $this->doit_customer_discount();

		if ($btn == 'AddDiscountItem')
			return $this->doit_customer_discount_item();

		if ($btn == "Add Customer" || $btn == "Update Customer") {

			if ( trim($_POST['customer_name']) && trim($_POST['city']) && trim($_POST['state']) && trim($_POST['country']) ) {

				# General Tab
				$customer['customer_name'] = addslashes( trim($_POST['customer_name']) );
				$customer['active'] = $_POST['active'];
				$customer['street'] = addslashes($_POST['street']);
				$customer['city'] = addslashes($_POST['city']);
				$customer['state'] = $_POST['state'];
				$customer['country'] = $_POST['country'];
				$customer['zip'] = trim($_POST['zip']);
				$customer['phone'] = $_POST['phone'];
				$customer['fax'] = $_POST['fax'];
				$customer['account_no'] = preg_replace('/[^-.0-9a-zA-Z]/', "", $_POST['account_no']);
				$customer['customer_no'] = preg_replace('/[^0-9]/', "", $_POST['customer_no']);

				//Payment Tab
				$customer['duns_no'] = preg_replace('/[^-0-9]/', "", $_POST['duns_no']);
				$customer['deposit_percent'] = preg_replace('/[^.0-9]/', "", $_POST['deposit_percent']);
				$customer['gsa'] = $_POST['gsa'];
				$customer['po_required'] = $_POST['po_required'];
				$customer['currency'] = $_POST['currency'];
				$customer['upon_receipt'] = $_POST['upon_receipt'];
				$customer['payment_terms'] = ( $upon_receipt ? 0 : $_POST['payment_terms'] );
				$customer['credit_limit'] = ( $_POST['credit_limit'] ? preg_replace('/[^.0-9]/', "", $_POST['credit_limit']) : NULL );
				$customer['credit_hold'] = $_POST['credit_hold'];
				if ( ! $customer_hash && defined('CUSTOMER_CREDIT_HOLD') && CUSTOMER_CREDIT_HOLD == 1 )
                    $customer['credit_hold'] = 1;

                $customer['tax_lock'] = $_POST['tax_lock'];
				$customer['tax_exempt_id'] = trim($_POST['tax_exempt_id']);
				if ( $customer['country'] == 'US' ) {

				    $customer_tax = $_POST['customer_tax'];
				    $customer['pst_exempt'] = 0;
				    $customer['gst_exempt'] = 0;
				    $customer['hst_exempt'] = 0;
				    $customer['qst_exempt'] = 0;
				} elseif ( $customer['country'] == 'CA' ) {

                    $customer['pst_exempt'] = $_POST['pst_exempt'];
                    $customer['gst_exempt'] = $_POST['gst_exempt'];
                    $customer['hst_exempt'] = $_POST['hst_exempt'];
                    $customer['qst_exempt'] = $_POST['qst_exempt'];
				}

				if ( $customer['invoice_reminder'] = $_POST['invoice_reminder'] ) {

					$customer['reminder_days'] = $_POST['reminder_days'];
					if ( strspn($customer['reminder_days'], "0123456789") != strlen($customer['reminder_days']) || ! $customer['reminder_days'] ) {

						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "Please enter a valid number of days to send late invoice reminders.";
						$this->content['form_return']['err']["err2_6a{$this->popup_id}"] = 1;
					}
				}

				$customer['finance_charges'] = $_POST['finance_charges'];
				if ($customer['finance_charges']) {

					$customer['finance_percent'] = preg_replace('/[^.0-9]/', "", $_POST['finance_percent']);
					$customer['finance_days'] = preg_replace('/[^0-9]/', "", $_POST['finance_days']);
					$customer['finance_minimum'] = preg_replace('/[^.0-9]/', "", $_POST['minimum_charge']);
					$customer['finance_resend'] = $_POST['finance_resend'];
					$customer['assess_finance_charges'] = $_POST['assess_finance_charges'];
					if ( $customer['finance_percent'] && ( ! $customer['finance_days'] || bccomp($customer['finance_percent'], 100, 2) == 1 || bccomp($customer['finance_percent'], 1, 2) == -1 ) ) {

						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "Please check that you have entered a valid finance charge percentage and late days to apply the charge.";
						$this->content['form_return']['err']["err2_7{$this->popup_id}"] = 1;
					}
					$customer['finance_percent'] = bcmul($customer['finance_percent'], .01, 4);
				}

				if ( $customer['deposit_percent'] && ( bccomp($customer['deposit_percent'], 1, 2) == -1 || bccomp($customer['deposit_percent'], 100, 2) == 1 ) ) {

					$err_msg = "Please check that you have entered a valid percentage for the required deposit percentage";
					if ( $integrate )
						return $err_msg;
					else {

						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = $err_msg;
						$this->content['form_return']['err']["err2_2{$this->popup_id}"] = 1;
					}
				}
                $customer['deposit_percent'] = bcmul($customer['deposit_percent'], .01, 4);

				if ( $customer['payment_terms'] && ! $this->check->is_allnumbers($customer['payment_terms']) ) {

					$err_msg = $this->check->ERROR;
					if ( $integrate )
						return "Invalid customer payment terms ".$err_msg;
					else {

						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = $err_msg;
						$this->content['form_return']['err']["err2_6{$this->popup_id}"] = 1;
					}
				}

                if ( $custom = $_POST['custom'] ) {

                    while ( list($cust_key, $cust_val) = each($custom) ) {

                        $this->cust->fetch_custom_field($cust_key);
                        if ( $this->cust->current_field['required'] && ! trim($cust_val) ) {

                        	if ( $integrate )
            					$missing_fields[] = $val;
            				else {

	                        	$this->content['form_return']['err']["err_custom{$this->cust->current_field['obj_id']}"] = 1;
	                            $this->content['error'] = 1;
            				}

                            $e = 1;
                        }

                        $customer[ $this->cust->current_field['col_name'] ] = trim($cust_val);
                    }
                    if ( $e ) {

                    	if ( $integrate )
            				return "Missing required fields: " . implode(", ", $missing_fields);
            			else
                    		$this->content['form_return']['feedback'][] = "Please make sure you complete the indicated fields below.";
                    }
                }

				if ( $this->content['error'] )
					return;

				if ( ! $customer ) {

					$this->content['feedback'] = "No changes have been made.";
					return;
				}

		        if ( ! $this->db->start_transaction() )
		            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

				if ( $btn == "Add Customer" ) {

					$customer_hash = rand_hash('customer_vendor_hash_index', 'hash');

					if ( ! $this->db->query("INSERT INTO customer_vendor_hash_index
        									  VALUES
        									  (
            									  '$customer_hash'
        									  )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

                    $customer = $this->db->prepare_query($customer, "customers", 'INSERT');
                    if ( ! $this->db->query("INSERT INTO customers
		                                      (
		                                          timestamp,
		                                          last_change,
		                                          customer_hash,
		                                          " . implode(", ", array_keys($customer) ) . "
		                                      )
		                                      VALUES
		                                      (
		                                          UNIX_TIMESTAMP() ,
		                                          '{$this->current_hash}',
		                                          '$customer_hash',
		                                          " . implode(", ", array_values($customer) ) . "
		                                      )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					if ( $customer_tax ) {

	                    for ( $i = 0; $i < count($customer_tax); $i++ ) {

	                        if ( ! $this->db->query("INSERT INTO location_tax
        	                                         VALUES
        	                                         (
	        	                                         NULL,
	        	                                         'customers|$customer_hash',
	        	                                         '{$customer_tax[$i]}'
        	                                         )")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
	                    }
					}

					if ( trim($_POST['contact_name']) ) {

						if ( ! $this->doit_customer_contact($customer_hash, 1) ) {

                            return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
						}
					}

					if ( trim($_POST['location_name']) ) {

						if ( ! $this->doit_customer_location($customer_hash, 1) ) {

	                        return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
						}
					}

					$this->db->end_transaction();

					if ( $integrate )
						return "Success";
					else {

						$this->content['page_feedback'] = "Customer has been added";
						$this->content['action'] = 'close';
						$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search={$this->active_search}');");
					}

					return;

				} elseif ( $btn == "Update Customer" ) {

					if ( ! $this->fetch_master_record($customer_hash) )
                        return $this->__trigger_error("Error encountered when trying to lookup customer. Unable to update customer, please try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

					( $customer['customer_name'] != $this->current_customer['customer_name'] ? $sql[] = "customer_name = '" . addslashes($customer['customer_name']) . "'" : NULL );
					( $customer['customer_no'] != $this->current_customer['customer_no'] ? $sql[] = "customer_no = '{$customer['customer_no']}'" : NULL );
					( $customer['active'] != $this->current_customer['active'] ? $sql[] = "active = '{$customer['active']}'" : NULL );
					( $customer['street'] != $this->current_customer['street'] ? $sql[] = "street = '{$customer['street']}'" : NULL );
					( $customer['city'] != $this->current_customer['city'] ? $sql[] = "city = '{$customer['city']}'" : NULL );
					( $customer['state'] != $this->current_customer['state'] ? $sql[] = "state = '{$customer['state']}'" : NULL );
					( $customer['zip'] != $this->current_customer['zip'] ? $sql[] = "zip = '{$customer['zip']}'" : NULL );
					( $customer['country'] != $this->current_customer['country'] ? $sql[] = "country = '{$customer['country']}'" : NULL );
					( $customer['phone'] != $this->current_customer['phone'] ? $sql[] = "phone = '{$customer['phone']}'" : NULL );
					( $customer['fax'] != $this->current_customer['fax'] ? $sql[] = "fax = '{$customer['fax']}'" : NULL );
					( $customer['duns_no'] != $this->current_customer['duns_no'] ? $sql[] = "duns_no = '{$customer['duns_no']}'" : NULL );
					( $customer['account_no'] != $this->current_customer['account_no'] ? $sql[] = "account_no = '{$customer['account_no']}'" : NULL );
					( $customer['deposit_percent'] != $this->current_customer['deposit_percent'] ? $sql[] = "deposit_percent = '{$customer['deposit_percent']}'" : NULL );
                    ( $customer['tax_exempt_id'] != $this->current_customer['tax_exempt_id'] ? $sql[] = "tax_exempt_id = '{$customer['tax_exempt_id']}'" : NULL );
                    ( $customer['pst_exempt'] != $this->current_customer['pst_exempt'] ? $sql[] = "pst_exempt = '{$customer['pst_exempt']}'" : NULL );
                    ( $customer['gst_exempt'] != $this->current_customer['gst_exempt'] ? $sql[] = "gst_exempt = '{$customer['gst_exempt']}'" : NULL );
                    ( $customer['hst_exempt'] != $this->current_customer['hst_exempt'] ? $sql[] = "hst_exempt = '{$customer['hst_exempt']}'" : NULL );
                    ( $customer['pst_exempt'] != $this->current_customer['pst_exempt'] ? $sql[] = "pst_exempt = '{$customer['pst_exempt']}'" : NULL );
                    ( $customer['tax_lock'] != $this->current_customer['tax_lock'] ? $sql[] = "tax_lock = '{$customer['tax_lock']}'" : NULL );
					( $customer['gsa'] != $this->current_customer['gsa'] ? $sql[] = "gsa = '{$customer['gsa']}'" : NULL );
                    ( $customer['po_required'] != $this->current_customer['po_required'] ? $sql[] = "po_required = '{$customer['po_required']}'" : NULL );
                    ( $customer['currency'] != $this->current_customer['currency'] ? $sql[] = "currency = '{$customer['currency']}'" : NULL );
					( $customer['payment_terms'] != $this->current_customer['payment_terms'] || $upon_receipt ? $sql[] = "payment_terms = " . ( ! is_numeric($customer['payment_terms']) && ! $customer['upon_receipt'] ? "NULL" : "'{$customer['payment_terms']}'" ) : NULL );
					( $customer['credit_limit'] != $this->current_customer['credit_limit'] ? $sql[] = "credit_limit = '{$customer['credit_limit']}'" : NULL );
					( $customer['credit_hold'] != $this->current_customer['credit_hold'] ? $sql[] = "credit_hold = '{$customer['credit_hold']}'" : NULL );
					( $customer['invoice_reminder'] != $this->current_customer['invoice_reminder'] ? $sql[] = "invoice_reminder = '{$customer['invoice_reminder']}'" : NULL );
					( $customer['reminder_days'] != $this->current_customer['reminder_days'] ? $sql[] = "reminder_days = '{$customer['reminder_days']}'" : NULL );
					( $customer['finance_charges'] != $this->current_customer['finance_charges'] ? $sql[] = "finance_charges = '{$customer['finance_charges']}'" : NULL );
					( $customer['finance_percent'] != $this->current_customer['finance_percent'] ? $sql[] = "finance_percent = '{$customer['finance_percent']}'" : NULL );
					( $customer['finance_days'] != $this->current_customer['finance_days'] ? $sql[] = "finance_days = '{$customer['finance_days']}'" : NULL );
					( $customer['finance_resend'] != $this->current_customer['finance_resend'] ? $sql[] = "finance_resend = '{$customer['finance_resend']}'" : NULL );
                    ( $customer['finance_minimum'] != $this->current_customer['finance_minimum'] ? $sql[] = "finance_minimum = '{$customer['finance_minimum']}'" : NULL );
                    ( $customer['assess_finance_charges'] != $this->current_customer['assess_finance_charges'] ? $sql[] = "assess_finance_charges = '{$customer['assess_finance_charges']}'" : NULL );

                    $customer = $this->db->prepare_query($customer, "customers", 'UPDATE');
                    if ( ! $this->db->query("UPDATE customers
		                                      SET
		                                          timestamp = UNIX_TIMESTAMP(),
		                                          last_change = '{$this->current_hash}',
		                                          " . implode(", ", $customer) . "
		                                      WHERE customer_hash = '{$this->customer_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
                    
                    if ( ! $this->db->query("DELETE FROM location_tax
                    		WHERE location_hash = 'customers|{$this->customer_hash}'")
                    ) {
                    
                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

                    if ( $customer_tax ) {

                        for ( $i = 0; $i < count($customer_tax); $i++ ) {

                            if ( ! $this->db->query("INSERT INTO location_tax
                                                     VALUES
                                                     (
                                                         NULL,
	                                                     'customers|{$this->customer_hash}',
	                                                     '{$customer_tax[$i]}'
                                                     )")
                            ) {

                                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
                        }
                    }

                    $this->db->end_transaction();

					$this->content['page_feedback'] = "Customer has been updated";

					$this->content['action'] = 'close';
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search={$this->active_search}');" );
				}

				return;

			} else {

				if ( ! $_POST['customer_name'] ) $this->set_error("err1_1{$this->popup_id}");
				if ( ! $_POST['city'] ) $this->set_error("err1_4{$this->popup_id}");
				if ( ! $_POST['state'] ) $this->set_error("err1_5{$this->popup_id}");
				if ( ! $_POST['country'] ) $this->set_error("err1_6a{$this->popup_id}");

				if ( $integrate )
					return "You left some required fields blank";
    			else
					return $this->__trigger_error( ( $this->content['form_return']['feedback'] ? "{$this->content['form_return']['feedback']} " : NULL ) . "You left some required fields blank! Please check the indicated fields below and try again.<br />", E_USER_NOTICE, __FILE__, __LINE__, 1);

			}

		} elseif ( $btn == "Delete Customer" ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

			if ( ! $this->db->query("UPDATE customers t1
					                  LEFT JOIN customer_contacts t2 ON t2.customer_hash = t1.customer_hash
					                  LEFT JOIN locations t5 ON t5.entry_hash = t1.customer_hash AND t5.entry_type = 'c'
					                  LEFT JOIN discounting t3 ON t3.customer_hash = t1.customer_hash
					                  LEFT JOIN discount_details t4 ON t4.discount_hash = t3.discount_hash
					                  SET
		    			                  t1.deleted = 1, t1.deleted_date = CURDATE(),
		    			                  t2.deleted = 1, t2.deleted_date = CURDATE(),
					                      t3.deleted = 1, t3.deleted_date = CURDATE(),
					                      t4.deleted = 1, t4.deleted_date = CURDATE(),
					                      t5.deleted = 1, t5.deleted_date = CURDATE()
					                  WHERE t1.customer_hash = '{$this->customer_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            $this->db->end_transaction();

			$this->content['page_feedback'] = "Customer has been removed";

			$this->content['action'] = 'close';
			$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search={$this->active_search}')" );

		}
	}

	function doit_customer_note() {
		$customer_hash = $_POST['customer_hash'];
		$note_hash = $_POST['note_hash'];
		$note = strip_tags($_POST['note']);

		if ($valid = $this->fetch_note_record($note_hash)) {
			if ($_POST['delete']) {
				$this->db->query("DELETE FROM `customer_vendor_notes`
								  WHERE `note_hash` = '$note_hash'");
				$this->content['page_feedback'] = "Note has been removed.";
			} elseif ($note) {
				$this->db->query("UPDATE `customer_vendor_notes`
								  SET `timestamp` = ".time()." , `user_hash` = '".$this->current_hash."' , `comment` = '$note'
								  WHERE `note_hash` = '$note_hash'");
				$this->content['page_feedback'] = "Note has been saved.";
			} else
				$this->content['page_feedback'] = "No changes have been made.";
		} else {
			$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));
			while (global_classes::key_exists('customer_vendor_notes', 'note_hash', $note_hash))
				$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));

			$this->db->query("INSERT INTO `customer_vendor_notes`
							  (`timestamp` , `note_hash` , `type` , `entry_hash` , `user_hash` , `comment`)
							  VALUES (".time()." , '$note_hash' , 'C' , '$customer_hash' , '".$this->current_hash."' , '$note')");
			$this->content['page_feedback'] = "Note has been saved.";
		}
		$this->content['action'] = 'close';

		$notes = $this->fetch_customer_notes($customer_hash, 'C');
		$this->content['html']['note_holder'] = '';
		for ($i = 0; $i < count($notes); $i++)
			$this->content['html']['note_holder'] .= "
			<div style=\"padding-left:5px;padding-bottom:5px;\">
				<strong>".stripslashes($notes[$i]['full_name'])." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $notes[$i]['timestamp']).")</strong> ".($notes[$i]['user_hash'] == $this->current_hash && $this->p->ck(get_class($this), 'E', 'general') ? "
					[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'customer_hash=".$customer_hash."', 'popup_id=note_win', 'note_hash=".$notes[$i]['note_hash']."')\">edit</a></small>]" : NULL)." : ".stripslashes($notes[$i]['comment'])."
			</div>";

		return;
	}

	function edit_note() {
		$customer_hash = $this->ajax_vars['customer_hash'];
		if ($note_hash = $this->ajax_vars['note_hash'])
			$valid = $this->fetch_note_record($note_hash);

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Customer Notes" : "Create A New Customer Note");

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("note_hash" => $this->note_hash,
								  "customer_hash" => $customer_hash,
								  "popup_id" => $this->popup_id))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:15px 20px;\">
						<table >
							<tr>
								<td style=\"vertical-align:top;padding-left:20px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2{$this->popup_id}\">Note: </div>
									".$this->form->text_area("name=note", "rows=7", "cols=75", "value=".$this->current_note['comment'])."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;\">
									<div style=\"text-align:left;padding:15px;\">
										".$this->form->button("value=Save Note", "onClick=submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=doit_customer_note');")."
										&nbsp;&nbsp;".($valid ?
										$this->form->button("value=Delete Note", "onClick=submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=doit_customer_note', 'delete=1');") : NULL)."
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function fetch_customer_notes($customer_hash) {
		if (!$customer_hash)
			return;

		$result = $this->db->query("SELECT customer_vendor_notes.* , users.full_name
									FROM `customer_vendor_notes`
									LEFT JOIN users ON users.id_hash = customer_vendor_notes.user_hash
									WHERE `entry_hash` = '$customer_hash' AND `type` = 'C'
									ORDER BY `timestamp` DESC");
		while ($row = $this->db->fetch_assoc($result))
			$notes[] = $row;


		return $notes;
	}

	function fetch_note_record($note_hash) {
		$result = $this->db->query("SELECT customer_vendor_notes.* , users.full_name
									FROM `customer_vendor_notes`
									LEFT JOIN users ON users.id_hash = customer_vendor_notes.user_hash
									WHERE `note_hash` = '$note_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_note = $row;
			$this->note_hash = $note_hash;
			$this->customer_hash = $row['customer_hash'];

			return true;
		}

		return false;
	}

	function edit_customer() {
		global $stateNames, $states, $country_codes, $country_names;

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Create A New Customer";

		if ( $this->ajax_vars['customer_hash'] ) {

			if ( ! $this->fetch_master_record($this->ajax_vars['customer_hash'], 1) ) {

				return $this->__trigger_error("System error encountered during vendor lookup. Unable to fetch vendor from database. If your browser session has remained open for an extended period of time, this issue may be resolved by logging out and restarting your browser. Error: D1305 Key: {$this->ajax_vars['customer_hash']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
			}

            $this->content['popup_controls']['popup_title'] = "Edit Customer : " . stripslashes($this->current_customer['customer_name']);
		}

		if ( $this->ajax_vars['onclose_action'] )
			$this->content['popup_controls']['onclose'] = $this->ajax_vars['onclose_action'];

		if ( $this->customer_hash ) {

			$customer_notes = "
			<div style=\"padding-top:5px;height:45px;width:90%;overflow:auto;border:1px outset #cccccc;background-color:#efefef;\" id=\"note_holder\">";

			$notes = $this->fetch_customer_notes($this->customer_hash, 'p');

			for ( $i = 0; $i < count($notes); $i++ )
				$customer_notes .= "
				<div style=\"padding-left:5px;padding-bottom:5px;\">
					<strong>" . stripslashes($notes[$i]['full_name']) . " (" . date(DATE_FORMAT . " " . TIMESTAMP_FORMAT, $notes[$i]['timestamp']) . ")</strong> " .
    				( $notes[$i]['user_hash'] == $this->current_hash && $this->p->ck(get_class($this), 'E', 'general') ? "
						[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'customer_hash={$this->customer_hash}', 'popup_id=note_win', 'note_hash={$notes[$i]['note_hash']}')\">edit</a></small>]" : NULL
					) . " : " . stripslashes($notes[$i]['comment']) . "
				</div>";

			$customer_notes .= "
			</div>";

		} else {

			$customer_notes = "
			<div style=\"padding-top:5px;\">" .
    			$this->form->text_area(
        			"name=customer_notes",
					"value=",
					( ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL ),
					"cols=75",
					"rows=4"
				) . "
			</div>";
		}

        if ( defined('MULTI_CURRENCY') ) {

            $sys = new system_config();
            $sys->fetch_currencies();

            $currency_out = $currency_in = array();
            for ( $i = 0; $i < count($sys->currency); $i++ ) {

                $currency_out[] = $sys->currency[$i]['name'];
                $currency_in[] = $sys->currency[$i]['code'];
            }
        }

        unset($this->cust->class_fields);
        if ( $this->cust->fetch_class_fields('general') ) {

            $fields =& $this->cust->class_fields['general'];
            for ( $i = 0; $i < count($fields); $i++ ) {

                $general_custom_fields .= "
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err_custom{$fields[$i]['obj_id']}\">
                        {$fields[$i]['field_name']}: " .
                        ( $fields[$i]['required'] ?
                            "*" : NULL
                        ) . "
                    </td>
                    <td style=\"background-color:#ffffff;\">" .
                        $this->cust->build_input(
                            $fields[$i],
                            $this->current_customer[ $fields[$i]['col_name'] ]
                        ) . "
                    </td>
                </tr>";
            }
        }

        unset($this->cust->class_fields);
        if ( $this->cust->fetch_class_fields('payment') ) {

            $fields =& $this->cust->class_fields['payment'];
            for ( $i = 0; $i < count($fields); $i++ ) {

                $payment_custom_fields .= "
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err_custom{$fields[$i]['obj_id']}\">
                        {$fields[$i]['field_name']}: " .
                        ( $fields[$i]['required'] ?
                            "*" : NULL
                        ) . "
                    </td>
                    <td style=\"background-color:#ffffff;\">" .
                        $this->cust->build_input(
                            $fields[$i],
                            $this->current_customer[ $fields[$i]['col_name'] ]
                        ) . "
                    </td>
                </tr>";
            }
        }

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
    		"customer_hash"   =>  $this->ajax_vars['customer_hash'],
			"popup_id"        =>  $this->popup_id,
			"jscript_action"  =>  $this->ajax_vars['jscript_action'],
			"p"               =>  $this->ajax_vars['p'],
			"order"           =>  $this->ajax_vars['order'],
			"order_dir"       =>  $this->ajax_vars['order_dir'],
			"active_search"   =>  $this->ajax_vars['active_search']
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<div style=\"padding:15px 0 0 15px;font-weight:bold;\"></div>
			<ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">" .
    		( ! $this->customer_hash || ( $this->customer_hash && $this->p->ck(get_class($this), 'V', 'general') ) ?
				"<li class=\"selected\" ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent1{$this->popup_id}\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\">General Info</a></li>" : NULL
    		) .
    		( $this->p->ck(get_class($this), 'V', 'payments') ?
				"<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent2{$this->popup_id}\" rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\">Payment Info</a></li>" : NULL
    		) .
    		( $this->p->ck(get_class($this), 'V', 'contacts') ?
				"<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent3{$this->popup_id}\" rel=\"tcontent3{$this->popup_id}\" onClick=\"expandcontent(this);\">Contact Info</a></li>" : NULL
    		) .
    		( $this->p->ck(get_class($this), 'V', 'locations') ? "
				<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent4{$this->popup_id}\" rel=\"tcontent4{$this->popup_id}\" onClick=\"expandcontent(this);\">Location Info</a></li>" : NULL
			) .
			( $this->current_customer['customer_hash'] ?
    			( $this->p->ck(get_class($this), 'V', 'discounting') ?
    				"<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent5{$this->popup_id}\" rel=\"tcontent5{$this->popup_id}\" onClick=\"expandcontent(this);\">Discounting</a></li>" : NULL
    			) .
    			( $this->p->ck(get_class($this), 'V', 'stats') ?
    				"<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent6{$this->popup_id}\" rel=\"tcontent6{$this->popup_id}\" onClick=\"expandcontent(this);\">Customer Stats</a></li>" : NULL
    			) : NULL
    		) . "
			</ul>" .
    		( $this->p->ck(get_class($this), 'V', 'general') ?
				"<div id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\">
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"table_body\">
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;width:35%;\" id=\"err1_1{$this->popup_id}\">Customer Name: *</td>
							<td style=\"background-color:#ffffff;padding-top:10px;\">" .
								( $this->current_customer ?
									$this->form->text_box(
		    							"name=customer_name",
		    							"value=" . stripslashes($this->current_customer['customer_name']),
		    							"size=35",
		    							"maxlength=255",
		    							( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL)
		    						)
		    						:
									$this->form->text_box(
		                                "name=customer_name",
		                                "value=".stripslashes($this->current_customer['customer_name']),
		                                "autocomplete=off",
		                                "size=35",
		                                "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('customers', 'customer_name', 'customer_name', 1);}",
		                                "onKeyUp=if(ka==false && this.value){key_call('customers', 'customer_name', 'customer_name', 1);}",
		                                "onBlur=key_clear();"
		                            )
		                        ) . "
								<div style=\"padding-top:5px;\">" .
									$this->form->checkbox(
	    								"name=active",
	    								"value=1",
	    								( $this->current_customer['active'] || ! $this->current_customer ? "checked" : NULL ),
	    								( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "disabled" : NULL )
	    							) . "&nbsp;Active
								</div>
							</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_3{$this->popup_id}\">Street: </td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->text_area(
	        						"name=street",
	        						"value=" . stripslashes($this->current_customer['street']),
	        						"rows=3",
	    						    "cols=35",
	        						( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL)
	        					) . "
	        				</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_4{$this->popup_id}\">City: *</td>
							<td style=\"background-color:#ffffff;\">" .
    							$this->form->text_box(
        							"name=city",
        							"value=" . stripslashes($this->current_customer['city']),
        							"size=15",
        							"maxlength=255",
        							( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
        						) . "
        					</td>
						</tr>
	                    <tr>
	                        <td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_5{$this->popup_id}\">State: *</td>
	                        <td style=\"background-color:#ffffff;\">" .
	                        ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ?
	                            ( in_array($this->current_customer['state'], $states) ?
	                                $stateNames[ array_search($this->current_customer['state'], $states) ] : $this->current_customer['state']
	                            ) .
	                            $this->form->hidden( array(
		                            'state'	=>	$this->current_customer['state']
	                            ) ) :
	                            "<input
	                                type=\"hidden\"
	                                name=\"stateSelect_default\"
	                                id=\"stateSelect_default\"
	                                value=\"{$this->current_customer['state']}\" >
                                <div><select id=\"stateSelect\" name=\"state\" onChange=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'load_tax_selection', 'customer_hash={$this->customer_hash}', 'popup_id={$this->popup_id}', 'state='+this.options[this.selectedIndex].value, 'country='+\$F('country'));\"></select></div>"
	                        ) . "
	                        </td>
	                    </tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_6{$this->popup_id}\">Zip: </td>
							<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=zip", "value=".$this->current_customer['zip'], "size=7", "maxlength=10", ($this->customer_hash && !$this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL))."</td>
						</tr>
	                    <tr>
	                        <td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_6a{$this->popup_id}\">Country: *</td>
	                        <td style=\"background-color:#ffffff;\">" .
	                        ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ?
	                            $country_names[ array_search(
	                                ( $this->current_customer['country'] ?
	                                    $this->current_customer['country']
	                                    :
	                                    ( defined('MY_COMPANY_COUNTRY') ?
	                                        MY_COMPANY_COUNTRY : NULL
	                                    )
	                                ), $country_codes) ] .
	                            $this->form->hidden( array(
	                                'country' =>
	                                ( $this->current_customer['country'] ?
	                                    $this->current_customer['country'] :
	                                        ( defined('MY_COMPANY_COUNTRY') ?
	                                            MY_COMPANY_COUNTRY : NULL
	                                        )
	                                    )
	                            ) )
	                            :
	                            "<input
	                                type=\"hidden\"
	                                name=\"countrySelect_default\"
	                                id=\"countrySelect_default\"
	                                value=\"" .
	                                ( $this->current_customer['country'] ?
	                                    $this->current_customer['country'] :
	                                    ( defined('MY_COMPANY_COUNTRY') ?
	                                        MY_COMPANY_COUNTRY : 'US'
	                                    )
	                                ) . "\">
	                            <div><select id=\"countrySelect\" name=\"country\" onchange=\"updateState(this.id);if(this.value=='US'){if(\$F('tax_exempt_id')==''){\$('primary_tax_rules').setStyle({'display':'block'});}agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'load_tax_selection', 'customer_hash={$this->customer_hash}', 'popup_id={$this->popup_id}', 'state='+\$F('state'), 'country='+this.options[this.selectedIndex].value)}else{\$('primary_tax_rules').setStyle({'display':'none'});}if(this.value == 'CA' && \$F('tax_exempt_id')){toggle_display('canada_tax_exemption{$this->popup_id}', 'block');}else{toggle_display('canada_tax_exemption{$this->popup_id}', 'none');}\"></select></div>"
	                        ) . "
	                        </td>
	                    </tr>
						<tr>
							<td style=\"background-color:#ffffff;text-align:right;\" id=\"err1_7{$this->popup_id}\">Phone: </td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->text_box(
	        						"name=phone",
	        						"value={$this->current_customer['phone']}",
	        						"size=18",
	        						"maxlength=32",
	        						( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
	        					) . "
	        				</td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_8{$this->popup_id}\">Fax:</td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->text_box(
	        						"name=fax",
	        						"value={$this->current_customer['fax']}",
	        						"size=18",
	        						"maxlength=16",
	        						( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
	        					) . "
	        				</td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_8a{$this->popup_id}\">Customer No:</td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->text_box(
	        						"name=customer_no",
	        						"value={$this->current_customer['customer_no']}",
	        						"size=7",
	        						"maxlength=11",
	        						( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
	        					) . "
	        				</td>
						</tr>
						$general_custom_fields
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\">
								Customer Notes: " .
								( $this->p->ck(get_class($this), 'E', 'general') ?
									"<div style=\"padding-top:5px;\">
										[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'customer_hash={$this->customer_hash}', 'popup_id=note_win')\">add a note</a></small>]
									</div>" : NULL
	    						) . "
							</td>
							<td style=\"background-color:#ffffff;\">$customer_notes</td>
						</tr>
					</table>
					<div style=\"text-align:left;padding:15px;\" >" .
					( ! $this->customer_hash || ( $this->lock && $this->customer_hash && $this->p->ck(get_class($this), 'E') ) ?
						$this->form->button(
	    					"value=" . ( $this->customer_hash ? "Update Customer" : "Add Customer" ),
	    					"onClick=submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=" . ( $this->customer_hash ? "Update Customer" : "Add Customer" ) . "')"
						) . "&nbsp;" : NULL
					) .
					( $this->lock && $this->customer_hash && $this->p->ck(get_class($this), 'D') ?
						$this->form->button(
	    					"value=Delete Customer",
	    					"onClick=if(confirm('Are you sure you want to delete this customer? This action CANNOT be undone!')) {submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=Delete Customer');}"
						) : NULL
					) . $this->p->lock_stat(get_class($this), $this->popup_id) . "
					</div>
				</div>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'payments') ? "
				<div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:20px;width:35%;\" id=\"err2_1{$this->popup_id}\">DUNS Number:</td>
							<td style=\"background-color:#ffffff;padding-top:20px;\">" .
	    						$this->form->text_box(
	        						"name=duns_no",
	        						"value={$this->current_customer['duns_no']}",
	        						"size=15",
	        						"maxlength=11",
	        						( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL )
	        					) . "
	        					<span style=\"padding-left:10px;font-size:8pt;font-style:italic;\">xx-xxx-xxxx</span>
	        				</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_1a{$this->popup_id}\">Customer Account Number:</td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->text_box(
	        						"name=account_no",
	        						"value={$this->current_customer['account_no']}",
	        						"size=20",
	        						"maxlength=64",
	        						( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL )
	        					) . "
	        				</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_2{$this->popup_id}\">Required Deposit Percentage:</td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->text_box(
	        						"name=deposit_percent",
	        						"value=" .
	        						( ! $this->customer_hash && defined('DEFAULT_CUST_DEPOSIT') ?
	            						bcmul( (float)DEFAULT_CUST_DEPOSIT, 100, 6) :
	            						( $this->current_customer['deposit_percent'] ?
	                						bcmul( (float)$this->current_customer['deposit_percent'], 100, 6) : NULL
	                					)
	                				),
	                				"size=4",
	                				"maxlength=5",
	                				"style=text-align:right;",
	                				( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL )
	                			) . " %
	                		</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_5{$this->popup_id}\">GSA Account:</td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->checkbox(
	        						"name=gsa",
	        						"value=1",
	        						( $this->current_customer['gsa'] ? "checked" : NULL ),
	        						( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL )
	        					) . "
	        				</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_4{$this->popup_id}\">Customer PO Required:</td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->checkbox(
	        						"name=po_required",
	        						"value=1",
	        						( ( ! $this->customer_hash && defined('DEFAULT_CUST_PO_REQUIRED') ) || $this->current_customer['po_required'] ? "checked" : NULL ),
	        						( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL )
	        					) . "
	        				</td>
						</tr>" .
	                    ( defined('MULTI_CURRENCY') ?
		                    "<tr>
		                        <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\">Default Currency:</td>
		                        <td style=\"background-color:#ffffff;\">" .
		                            $this->form->select(
		                                "currency",
							            $currency_out,
							            ( $this->current_customer['currency'] ? $this->current_customer['currency'] : $sys->home_currency['code'] ),
							            $currency_in,
							            "blank=1"
		                            ) . "
		                        </td>
		                    </tr>" : NULL
	                    ) . "
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;vertical-align:top;background-color:#ffffff;\" id=\"err2_6{$this->popup_id}\">Customer Payment Terms:</td>
							<td style=\"background-color:#ffffff;\">" .
	    						$this->form->text_box(
	        						"name=payment_terms",
	                            	"value=" . ( ( ! $this->customer_hash && defined('DEFAULT_CUST_PAY_TERMS') ) || is_numeric($this->current_customer['payment_terms']) ?
	                                    ( is_numeric($this->current_customer['payment_terms']) ?
	                                        $this->current_customer['payment_terms'] : DEFAULT_CUST_PAY_TERMS
	                                    ) : NULL
	                                ),
	        					    "size=3",
	        					    "maxlength=2",
	        					    "style=text-align:right;",
	        					    "onFocus=this.select();",
	        					    ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ?
	                					"readonly" : NULL
	        					    ),
	        					    ( is_numeric($this->current_customer['payment_terms']) && ! $this->current_customer['payment_terms'] ? "disabled" : NULL )
	        					) . " days
								<div style=\"margin-top:5px;\">" .
									$this->form->checkbox(
	    								"name=upon_receipt",
	                        			"value=1",
							            ( (int)$this->current_customer['payment_terms'] === 0 ? "checked" : NULL ),
	                                    ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL ),
	                                    "onClick=if(this.checked){\$('payment_terms').disabled=1;}else{\$('payment_terms').disabled=0}"
	                                ) . "&nbsp;Upon Receipt
								</div>
							</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2_6a{$this->popup_id}\">Customer Credit Limit:</td>
							<td style=\"background-color:#ffffff;\">" .
								$this->form->text_box(
	    							"name=credit_limit",
	    							"value={$this->current_customer['credit_limit']}",
	    							"size=10",
	    							"onBlur=if(this.value){this.value=formatCurrency(this.value);}",
	    							( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL )
	    						) . "
								<div style=\"margin-top:5px;\">" .
									$this->form->checkbox(
	    								"name=credit_hold",
	    								"value=1",
	    								( $this->current_customer['credit_hold'] ? "checked" : NULL ),
	    								( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL )
	    							) . "&nbsp;Manual Credit Hold
								</div>
							</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2_6a{$this->popup_id}\">Late Invoice Reminder:</td>
							<td style=\"background-color:#ffffff;\">" .
								$this->form->checkbox(
	    							"name=invoice_reminder",
	    							"value=1",
	    							( $this->current_customer['invoice_reminder'] ? "checked" : NULL ),
	    							"onClick=if(this.checked){toggle_display('late_reminder_settings{$this->popup_id}', 'block');}else{toggle_display('late_reminder_settings{$this->popup_id}', 'none');}",
	    							( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL )
	    						) . "
								<div id=\"late_reminder_settings{$this->popup_id}\" style=\"padding-top:5px;display:" . ( $this->current_customer['invoice_reminder'] ? "block" : "none" ) . "\">
									How many days prior to an invoice<br />becoming late should a reminder be sent?
									<div style=\"padding-top:5px\">" .
	        							$this->form->text_box(
	            							"name=reminder_days",
	            							"value=" . ( $this->current_customer['reminder_days'] ? $this->current_customer['reminder_days'] : 5 ),
	            							"size=3",
	            							"style=text-align:right;"
	            						) . "
	            					</div>
								</div>
							</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2_7{$this->popup_id}\">Apply Finance Charges:</td>
							<td style=\"background-color:#ffffff;\">" .
								$this->form->checkbox(
	    							"name=finance_charges",
	    							"value=1",
	    							( ( ! $this->customer_hash && defined('DEFAULT_FINANCE_PERCENT') && defined('DEFAULT_FINANCE_DAYS') ) || $this->current_customer['finance_charges'] ? "checked" : NULL ),
	    							"onClick=if(this.checked){toggle_display('finance_holder{$this->popup_id}', 'block');}else{toggle_display('finance_holder{$this->popup_id}', 'none');}",
	    							( $this->customer_hash && !$this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL )
	    						) . "
								<div style=\"margin-left:10px;padding-top:10px;display:" . ( ( ! $this->customer_hash && defined('DEFAULT_FINANCE_PERCENT') && defined('DEFAULT_FINANCE_DAYS') ) || $this->current_customer['finance_charges'] ? "block" : "none" ) . "\" id=\"finance_holder{$this->popup_id}\">
									<table >
									    <tr>
									        <td style=\"text-align:right;\">Interest Rate: </td>
									        <td>" .
	                                            $this->form->text_box(
	                                                "name=finance_percent",
	                                                "value=" .
	                                                ( ( ! $this->customer_hash && defined('DEFAULT_FINANCE_PERCENT') ) || $this->current_customer['finance_percent'] ?
	                                                    ( $this->current_customer['finance_percent'] ?
	                                                        bcmul((float)$this->current_customer['finance_percent'], 100, 6) : bcmul((float)DEFAULT_FINANCE_PERCENT, 100, 6)
	                                                    ) : NULL
	                                                ),
	                                                "size=3",
	                                                "maxlength=5",
	                                                "style=text-align:right;",
	                                                ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL )
	                                            ) . "&nbsp;%
									        </td>
									        <td style=\"text-align:right;\">Grace Period: </td>
	                                        <td>" .
	                                            $this->form->text_box(
	                                                "name=finance_days",
	                                                "value=" .
	                                                ( ( ! $this->customer_hash && defined('DEFAULT_FINANCE_DAYS') ) || $this->current_customer['finance_days'] ?
	                                                    ( $this->current_customer['finance_days'] ?
	                                                        $this->current_customer['finance_days'] : DEFAULT_FINANCE_DAYS
	                                                    ) : NULL
	                                                ),
	                                                "size=2",
	                                                "maxlength=2",
	                                                "style=text-align:right;",
	                                                ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL )
	                                            ) . "&nbsp;Days
	                                        </td>
									    </tr>
	                                    <tr>
	                                        <td style=\"text-align:right;\">Minimum Finance Charge: </td>
	                                        <td>" .
	                                            $this->form->text_box(
	                                                "name=minimum_charge",
	                                                "value=" .
	                                                ( ( ! $this->customer_hash && defined('DEFAULT_FINANCE_MINIMUM') ) || $this->current_customer['finance_minimum'] ?
	                                                    ( $this->current_customer['finance_minimum'] ?
	                                                        $this->current_customer['finance_minimum'] : DEFAULT_FINANCE_MINIMUM
	                                                    ) : NULL
	                                                ),
	                                                "size=6",
	                                                "onBlur=if(this.value){this.value=formatCurrency(this.value)}",
	                                                "style=text-align:right;",
	                                                ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL )
	                                            ) . "
	                                        </td>
	                                        <td style=\"text-align:right;\">Resend invoice? </td>
	                                        <td>" .
	                                            $this->form->checkbox(
	                                                "name=finance_resend",
	                                                "value=1",
	                                                ( $this->current_customer['finance_resend'] ? "checked" : NULL ),
	                                                ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL )
	                                            ) . "
	                                        </td>
	                                    </tr>
	                                    <tr>
	                                        <td style=\"text-align:right;\" colspan=\"3\">Assess finance charges on overdue finance charges: </td>
	                                        <td>" .
	                                            $this->form->checkbox(
	                                                "name=assess_finance_charges",
	                                                "value=1",
	                                                ( $this->current_customer['assess_finance_charges'] ? "checked" : NULL ),
	                                                ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "disabled" : NULL )
	                                            ) . "
	                                        </td>
	                                    </tr>
									</table>
								</div>
							</td>
						</tr>
	                    <tr>
	                        <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2_3{$this->popup_id}\">
	                            Tax Exemption Number:
	                            <div style=\"margin-top:5px;text-align:right;font-style:italic;\">Separate by comma or line break</div>
	                        </td>
	                        <td style=\"background-color:#ffffff;\">" .
	                            $this->form->text_area(
	                                "name=tax_exempt_id",
	                                "value={$this->current_customer['tax_exempt_id']}",
	                                "rows=2",
	                                "cols=35",
	                                "onKeyUp=
	                                if ( this.value ) {
	                                	if( this.value.length == 1 ) {
	                                		\$('primary_tax_rules').hide(); 
	                                		if ( \$F('country') == 'CA' ) { 
	                                			toggle_display('canada_tax_exemption{$this->popup_id}', 'block'); 
											} 
										} 
									} else { 
										\$('primary_tax_rules').show();
										if ( \$F('country') == 'CA' ) { 
	                                			toggle_display('canada_tax_exemption{$this->popup_id}', 'none'); 
										} 
									}",
	                                "onBlur=
	                                if ( this.value ) {
	                                	if( this.value.length == 1 ) {
	                                		\$('primary_tax_rules').hide(); 
	                                		if ( \$F('country') == 'CA' ) { 
	                                			toggle_display('canada_tax_exemption{$this->popup_id}', 'block'); 
											} 
										} 
									} else { 
										\$('primary_tax_rules').show();
										if ( \$F('country') == 'CA' ) { 
	                                			toggle_display('canada_tax_exemption{$this->popup_id}', 'none'); 
										} 
									}",
						            ( $this->customer_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL )
						        ) . "
	                            <div id=\"canada_tax_exemption{$this->popup_id}\" style=\"margin-top:5px;display:" . ( $this->current_customer['country'] == 'CA' && trim($this->current_customer['tax_exempt_id']) ? "block" : "none" ) . "\">
	                                <table>
	                                    <tr>
	                                        <td>
	                                            <div style=\"margin-bottom:3px;\">" .
		                                            $this->form->checkbox(
		                                                "name=gst_exempt",
		                                                "value=1",
		                                                ( $this->current_customer['gst_exempt'] ? "checked" : NULL )
		                                            ) . "&nbsp;GST Exempt
	                                            </div>
	                                        </td>
	                                        <td>
	                                            <div style=\"margin-left:15px;margin-bottom:3px;\">" .
		                                            $this->form->checkbox(
	    	                                            "name=pst_exempt",
	    	                                            "value=1",
	    	                                            ( $this->current_customer['pst_exempt'] ? "checked" : NULL )
	    	                                        ) . "&nbsp;PST Exempt
	                                            </div>
	                                        </td>
	                                    </tr>
	                                    <tr>
	                                        <td>
		                                        <div style=\"margin-bottom:3px;\">" .
		                                            $this->form->checkbox(
		                                                "name=hst_exempt",
		                                                "value=1",
		                                                ( $this->current_customer['hst_exempt'] ? "checked" : NULL )
		                                            ) . "&nbsp;HST Exempt
		                                        </div>
	                                        </td>
	                                        <td>
	                                            <div style=\"margin-left:15px;margin-bottom:3px;\">" .
		                                            $this->form->checkbox(
	    	                                            "name=qst_exempt",
	    	                                            "value=1",
	    	                                            ( $this->current_customer['qst_exempt'] ? "checked" : NULL )
	    	                                        ) . "&nbsp;QST Exempt
	                                            </div>
	                                        </td>
	                                    </tr>
	                                </table>
	                            </div>
	                        </td>
	                    </tr>
	                    <tr id=\"primary_tax_rules\" ". ( $this->current_customer['tax_exempt_id'] || $this->current_customer['country'] != 'US' ? "style=\"display:none\"" : "" ) . ">
	                        <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\">
	                            Customer Sales Tax:
	                            <div style=\"margin-top:10px;font-style:italic;\">
	                                Lock Tax Rules: " .
	                                $this->form->checkbox(
	                                    "name=tax_lock",
	                                    "value=1",
	                                    "title=By locking tax rules, users will not have the ability to change tax rules during proposal finalization.",
	                                    ( $this->current_customer['tax_lock'] ? "checked" : NULL )
	                                ) . "
	                            </div>
	                        </td>
	                        <td style=\"background-color:#ffffff;\" id=\"tax_selection_table{$this->popup_id}\">" .
	                            $this->load_tax_selection(
	                                1,
	                                array(
	                                    'customer_hash'  =>  $this->customer_hash,
						                'country'        =>  $this->current_customer['country'],
						                'state'          =>  $this->current_customer['state']
	                                )
	                            ) . "
	                        </td>
	                    </tr>
	                    $payment_custom_fields
					</table>
					<div style=\"text-align:left;padding:15px;\" >" .
					( ! $this->customer_hash || ( $this->lock && $this->customer_hash && $this->p->ck(get_class($this), 'E') ) ?
						$this->form->button(
	    					"value=" . ( $this->customer_hash ? "Update Customer" : "Add Customer" ),
	    					"onClick=submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=" . ( $this->customer_hash ? "Update Customer" : "Add Customer" ) . "')"
						) . "&nbsp;" : NULL
					) .
					( $this->lock && $this->customer_hash && $this->p->ck(get_class($this), 'D') ?
						$this->form->button(
	    					"value=Delete Customer",
	    					"onClick=if(confirm('Are you sure you want to delete this customer? This action CANNOT be undone!')) {submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=Delete Customer');}"
						) : NULL
					) . $this->p->lock_stat(get_class($this), $this->popup_id) . "
					</div>
				</div>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'contacts') ?
    			"<div id=\"tcontent3{$this->popup_id}\" class=\"tabcontent\">{$this->contact_info_form()}</div>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'locations') ?
    			"<div id=\"tcontent4{$this->popup_id}\" class=\"tabcontent\">{$this->location_info_form()}</div>" : NULL
			) .
			( $this->current_customer['customer_hash'] ?
    			( $this->p->ck(get_class($this), 'V', 'discounting') ?
        			"<div id=\"tcontent5{$this->popup_id}\" class=\"tabcontent\">{$this->discount_info_form()}</div>" : NULL
        		) .
        		( $this->p->ck(get_class($this), 'V', 'stats') ?
        			"<div id=\"tcontent6{$this->popup_id}\" class=\"tabcontent\">{$this->customer_stats_form()}</div>" : NULL
        		) : NULL
        	) . "
		</div>" .
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')', 10);";
		$this->content['jscript'][] = "setTimeout(function(){initCountry('countrySelect location_countrySelect', 'stateSelect location_stateSelect');}, 500);";

		if ( $this->ajax_vars['tab_to'] )
			$this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_{$this->ajax_vars['tab_to']}{$this->popup_id}\'))', 100)";

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function customer_stats_form() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		$invoice = new customer_invoice($this->current_hash);
		$invoice->set_values( array(
    		"customer_hash"   =>  $this->customer_hash
		) );

		$invoice->fetch_open_invoices();
		$deposits = $invoice->fetch_customer_deposits('*');

		for ($i = 0; $i < count($deposits); $i++)
			$total_deposits = $deposits[$i]['balance'];

		$tbl = "
		<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\">
								Customer Statistics for ".stripslashes($this->current_customer['customer_name'])." as of ".date(DATE_FORMAT." ".TIMESTAMP_FORMAT)."
								<div style=\"margin:10px;width:600px;padding:0;\">
									<fieldset>
										<legend>Open Invoices (".count($invoice->invoice_info).")</legend>
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
											<tr>
												<td style=\"padding:10px 25px;\">";
												if (count($invoice->invoice_info)) {
													$tbl .= "
													<div ".(count($invoice->invoice_info) > 3 ? "style=\"height:100px;overflow:auto;\"" : NULL).">
														<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:96%;\" >
															<tr>
																<td style=\"background-color:#efefef;\" class=\"smallfont\">Invoice No.</td>
																<td style=\"background-color:#efefef;\" class=\"smallfont\">Invoice Date</td>
																<td style=\"background-color:#efefef;\" class=\"smallfont\">Invoice Amount</td>
																<td style=\"background-color:#efefef;\" class=\"smallfont\">Amount Due</td>
															</tr>
															<tr>
																<td colspan=\"4\" style=\"background-color:#cccccc;\"</td>
															</tr>";
														for ($i = 0; $i < count($invoice->invoice_info); $i++) {
															$tbl .= "
															<tr onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'proposal_hash=".$invoice->invoice_info[$i]['proposal_hash']."', 'invoice_hash=".$invoice->invoice_info[$i]['invoice_hash']."', 'popup_id=line_item');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" >
																<td class=\"smallfont\">".$invoice->invoice_info[$i]['invoice_no']."</td>
																<td class=\"smallfont\">".date(DATE_FORMAT, strtotime($invoice->invoice_info[$i]['invoice_date']))."</td>
																<td class=\"smallfont\">$".number_format($invoice->invoice_info[$i]['amount'], 2)."</td>
																<td class=\"smallfont\">$".number_format($invoice->invoice_info[$i]['balance'], 2)."</td>
															</tr>".($i < count($invoice->invoice_info) - 1 ? "
															<tr>
																<td colspan=\"4\" style=\"background-color:#cccccc;\"</td>
															</tr>" : NULL);
														}
													$tbl .= "
														</table>
													</div>";
												} else
													$tbl .= "There are no open invoices.";

												$tbl .= "
												</td>
											</tr>
										</table>
									</fieldset>
									<fieldset style=\"margin-top:10px\">
										<legend>Accounts Receivables</legend>
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
											<tr>
												<td style=\"padding:10px 0 0 25px\" colspan=\"2\" class=\"smallfont\">
													Total Outstanding: $".number_format($this->current_customer['balance_total'], 2)."
												</td>
											</tr>".($this->current_customer['balance_total'] != 0 ? "
											<tr>
												<td style=\"padding-left:45px;\" >
													<table style=\"width:100%;\">
														<tr>
															<td style=\"width:50%;\" class=\"smallfont\">
																<div >
																	Total Current: ".($this->current_customer['balance_current'] < 0 ?
																		"<span style=\"color:#ff0000;\">($".number_format($this->current_customer['balance_current'] * -1, 2).")</span>" : "$".number_format($this->current_customer['balance_current'], 2))."
																</div>
																<div style=\"padding-top:5px;\">
																	Total Over ".ACCOUNT_AGING_SCHED1.": ".($this->current_customer['balance_sched1'] < 0 ?
																		"<span style=\"color:#ff0000;\">($".number_format($this->current_customer['balance_sched1'] * -1, 2).")</span>" : "$".number_format($this->current_customer['balance_sched1'], 2))."
																</div>
															</td>
															<td style=\"width:50%;\" class=\"smallfont\">
																<div style=\"padding-top:5px;\">
																	Total Over ".ACCOUNT_AGING_SCHED2.": ".($this->current_customer['balance_sched2'] < 0 ?
																		"<span style=\"color:#ff0000;\">($".number_format($this->current_customer['balance_sched2'] * -1, 2).")</span>" : "$".number_format($this->current_customer['balance_sched2'], 2))."
																</div>
																<div style=\"padding-top:5px;\">
																	Total Over ".ACCOUNT_AGING_SCHED3.": ".($this->current_customer['balance_sched3'] < 0 ?
																		"<span style=\"color:#ff0000;\">($".number_format($this->current_customer['balance_sched3'] * -1, 2).")</span>" : "$".number_format($this->current_customer['balance_sched3'], 2))."
																</div>
															</td>
														</tr>
													</table>
												</td>
											</tr>" : NULL).($total_deposits > 0 ? "
											<tr>
												<td style=\"padding:0 0 10px 25px\" colspan=\"2\" class=\"smallfont\">
													Total Deposits <small>(included in aging above)</small>: $".number_format($total_deposits, 2)."
												</td>
											</tr>" : NULL)."
										</table>
									</fieldset>";
									$result = $this->db->query("SELECT AVG(DATEDIFF(customer_payment.receipt_date , customer_invoice.invoice_date)) AS avg_pay_days
																FROM `customer_invoice`
																LEFT JOIN customer_payment ON customer_payment.invoice_hash = customer_invoice.invoice_hash AND customer_payment.deleted = 0
																WHERE customer_invoice.customer_hash = '{$this->customer_hash}' AND customer_invoice.type = 'I' AND customer_invoice.deleted = 0 AND !ISNULL(customer_payment.receipt_date)");
									$avg_pay_days = $this->db->result($result);
																		$current_qtr = get_quarter();
									list($qtr_start, $qtr_end) = get_quarter_dates($current_qtr);

									$result = $this->db->query("SELECT
																SUM(CASE
																	WHEN customer_invoice.type = 'I'
																		THEN customer_invoice.amount
																		ELSE 0
																	END) AS overall_invoiced ,
																SUM(CASE
																	WHEN customer_invoice.invoice_date BETWEEN '".date("Y-m-01")."' AND CURDATE() AND customer_invoice.type = 'I'
																		THEN customer_invoice.amount
																		ELSE 0
																	END) AS invoiced_mtd ,
																SUM(CASE
																	WHEN customer_invoice.invoice_date BETWEEN '".$qtr_start."' AND CURDATE() AND customer_invoice.type = 'I'
																		THEN customer_invoice.amount
																		ELSE 0
																	END) AS invoiced_qtd ,
																SUM(CASE
																	WHEN customer_invoice.invoice_date BETWEEN '".date("Y-01-01")."' AND CURDATE() AND customer_invoice.type = 'I'
																		THEN customer_invoice.amount
																		ELSE 0
																	END) AS invoiced_ytd
															   FROM `customers`
															   LEFT JOIN `customer_invoice` ON customer_invoice.customer_hash = customers.customer_hash AND customer_invoice.deleted = 0
															   WHERE customers.customer_hash = '{$this->customer_hash}'");

									$row = $this->db->fetch_assoc($result);

									$result = $this->db->query("SELECT AVG(FORMAT((line_items.sell - line_items.cost) / line_items.sell * 100 , 2)) AS avg_margin
																FROM `line_items`
																LEFT JOIN customer_invoice ON customer_invoice.invoice_hash = line_items.invoice_hash AND customer_invoice.deleted = 0
																WHERE customer_invoice.customer_hash = '{$this->customer_hash}' AND !ISNULL(line_items.invoice_hash) AND line_items.invoice_hash != ''");
									$avg_margin = $this->db->result($result);

									$tbl .= "
									<fieldset style=\"margin-top:10px\">
										<legend>Total Invoiced Sales</legend>
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\" >
											<tr>
												<td style=\"padding:10px 25px;width:50%;\" class=\"smallfont\">
													Average Days To Pay: ".round($avg_pay_days, 2)." day".($avg_pay_days > 1 ? "s" : NULL)."
												</td>
												<td style=\"padding:10px 25px;width:50%;\" class=\"smallfont\">
													Average GP Margin: ".round($avg_margin, 2)."%
												</td>
											</tr>
											<tr>
												<td style=\"padding-left:25px;\" colspan=\"2\" class=\"smallfont\">
													Total Invoiced Sales:
													<div style=\"margin-left:45px;\">
														<table style=\"width:100%;\" >
															<tr>
																<td style=\"width:50%;\">
																	<div>MTD: $".number_format($row['invoiced_mtd'], 2)."</div>
																	<div style=\"padding-top:5px;\">QTD: $".number_format($row['invoiced_qtd'], 2)."</div>
																</td>
																<td style=\"width:50%;\">
																	<div>YTD: $".number_format($row['invoiced_ytd'], 2)."</div>
																	<div style=\"padding-top:5px;\">Overall: $".number_format($row['overall_invoiced'], 2)."</div>
																</td>
															</tr>
														</table>
													</div>
												</td>
											</tr>
										</table>
									</fieldset>
								</div>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>";

		if ($this->ajax_vars['otf'])
			$this->content['html']['tcontent6'.$this->popup_id] = $tbl;
		else
			return $tbl;
	}


	function discount_info_form() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		if ( $this->ajax_vars['popup_id'] )
			$this->popup_id = $this->ajax_vars['popup_id'];

		# If we're already editing a customer
		if ( $this->ajax_vars['customer_hash'] && ! $this->customer_hash ) {

			if ( ! $this->fetch_master_record($this->ajax_vars['customer_hash']) )
    			return $this->__trigger_error("System error encountered when attempting to lookup customer record for discount edit. Please reload window and try again. <!-- Tried fetching customer [ {$this->ajax_vars['customer_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		}

		$action = $this->ajax_vars['action'];

		if ( $this->ajax_vars['discount_hash'] ) {

			if ( ! $this->discounting->fetch_discount_record($this->ajax_vars['discount_hash']) )
    			return $this->__trigger_error("System error encountered when attempting to lookup discount record. Please reload window and try again. <!-- Tried fetching discount [ {$this->ajax_vars['discount_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			if ( $this->ajax_vars['item_hash'] ) {

				if ( ! $this->discounting->fetch_item_record($this->ajax_vars['item_hash']) )
    				return $this->__trigger_error("System error encountered when attempting to lookup discount item record. Please reload window and try again. <!-- Tried fetching discount item [ {$this->ajax_vars['item_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			}
		}

		$discount_items_exists = $products_out = $products_in = $products_out1 = $products_in1 = array();

		if ( $this->discounting->discount_hash ) {

	        if ( ! $this->discounting->item_hash ) {

	            $r = $this->db->query("SELECT t1.product_hash
	                                   FROM discount_details t1
	                                   WHERE t1.discount_hash = '{$this->discounting->discount_hash}' AND ISNULL(t1.item_no) AND t1.deleted = 0");
	            while ( $row = $this->db->fetch_assoc($r) )
	                array_push($discount_items_exists, $row['product_hash']);
	        }

	        $vendors = new vendors($this->current_hash);
	        if ( ! $vendors->fetch_master_record($this->discounting->current_discount['vendor_hash']) )
	            return $this->__trigger_error("System error encountered when attempting to lookup vendor record for specified discount. Please reload window and try again. <!-- Tried fetching vendor [ {$this->discounting->current_discount['vendor_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

	        $vendors->fetch_products(0, $vendors->total_products);

	        for ( $i = 0; $i < $vendors->total_products; $i++ ) {

	            if ( ! in_array($vendors->vendor_products[$i]['product_hash'], $discount_items_exists) ) {

	                $products_out[] = $vendors->vendor_products[$i]['product_name'];
	                $products_in[] = $vendors->vendor_products[$i]['product_hash'];
	            }

	            $products_out1[] = $vendors->vendor_products[$i]['product_name'];
	            $products_in1[] = $vendors->vendor_products[$i]['product_hash'];
	        }
		}

        $products_out1[] = "** APPLY ON ALL PRODUCTS **";
        $products_in1[] = "*";

		# Create a new discount record
		if ( $action == 'addnew') {

			if ( ! $this->discounting->discount_hash )
    			$this->content['focus'] = "discount_vendor";

			$tbl = "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">" .
				( $this->discounting->discount_hash ?
	                "<tr>
	                    <td style=\"background-color:#e6e6e6;text-align:left;vertical-align:bottom;\" colspan=\"2\" class=\"smallfont\">
	                        <div style=\"float:right;padding-right:15px;\">
	                            <a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'action=showtable', 'customer_hash={$this->customer_hash}', 'discount_hash={$this->discounting->discount_hash}');\" ><img src=\"images/table.gif\" border=\"0\" style=\"margin-bottom:0\" /></a>
	                            <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'action=showtable', 'customer_hash={$this->customer_hash}', 'discount_hash={$this->discounting->discount_hash}');\" >view discount table</a>
	                        </div>
	                    </td>
	                </tr>" : NULL
	            ) . "
    			<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:10px;width:35%;vertical-align:top;\" id=\"err6_2{$this->popup_id}\">Vendor: *</td>
					<td style=\"background-color:#ffffff;padding-top:10px;vertical-align:top;\">" .
						$this->form->hidden( array(
    						"discount_type" => 'C'
						) ) .
                        ( $this->discounting->discount_hash ?
                            stripslashes($this->discounting->current_discount['discount_vendor']) .
                            $this->form->hidden( array(
                                'discount_vendor_hash'   =>  $this->discounting->current_discount['discount_vendor_hash'],
                                'discount_vendor'        =>  $this->discounting->current_discount['discount_vendor']
                            ) )
                            :
                            $this->form->hidden( array(
                                'discount_next_action' => 'showtable'
                            ) ) .
                            $this->form->text_box(
                                "name=discount_vendor",
                                "value={$this->discounting->current_discount['discount_vendor']}",
                                "autocomplete=off",
                                "size=30",
                                "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'discount_vendor', 'discount_vendor_hash', 1);}",
                                "onKeyUp=if(ka==false && this.value){key_call('proposals', 'discount_vendor', 'discount_vendor_hash', 1);}",
                                "onBlur=key_clear();",
                                "onKeyDown=clear_values('discount_vendor_hash');"
                            ) .
                            $this->form->hidden( array(
                                "discount_vendor_hash" => ( $this->discounting->current_discount['discount_vendor_hash'] ? $this->discounting->current_discount['discount_vendor_hash'] : '' )
                            ) ) .
                            ( $this->p->ck('vendors', 'A') ?
                                "<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors', 'sf_loadcontent', 'show_popup_window', 'edit_vendor', 'parent_win_open', 'parent_win={$this->popup_id}', 'popup_id=sub_win', 'jscript_action=javascript:void(0);');\"><img src=\"images/plus.gif\" border=\"0\" title=\"Create a new vendor\"></a>" : NULL
                            )
                        ) . "
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err6_4{$this->popup_id}\">Discount ID: *</td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_box(
        					"name=discount_id",
        					"value={$this->discounting->current_discount['discount_id']}",
        					"size=10",
        					"maxlength=24"
    					) . "
    				</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err6_7{$this->popup_id}\">Description: *</td>
					<td style=\"background-color:#ffffff;\">" .
						$this->form->text_box(
    						"name=discount_descr",
    						"value=" . ( ! $this->discounting->discount_hash ? stripslashes($this->current_customer['customer_name']) : stripslashes($this->discounting->current_discount['discount_descr']) ),
    						"size=25",
    						"maxlength=64"
						) . "
						<div style=\"padding-top:5px;\">" .
							$this->form->checkbox(
    							"name=discount_gsa",
    							"value=1",
    							( ( ! $this->discounting->discount_hash && $this->current_customer['gsa'] ) || $this->discounting->current_discount['discount_gsa'] ? "checked" : NULL )
    						) . "
							GSA?
						</div>
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err6_5{$this->popup_id}\">Effective Date: *</td>
					<td style=\"background-color:#ffffff;\" id=\"discount_effective_date{$this->popup_id}\">";

					$this->content["jscript"][] = "
					setTimeout('DateInput(\'discount_effective_date\', true, \'YYYY-MM-DD\', \'" . ( $this->discounting->current_discount['discount_effective_date'] ? $this->discounting->current_discount['discount_effective_date'] : date("Y-m-d") ) . "\', 1, \'discount_effective_date{$this->popup_id}\')', 30);";

					$tbl .= "
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err6_6{$this->popup_id}\">Expiration Date: *</td>
					<td style=\"background-color:#ffffff;\" id=\"discount_expiration_date{$this->popup_id}\">";

					$this->content["jscript"][] = "
					setTimeout('DateInput(\'discount_expiration_date\', true, \'YYYY-MM-DD\', \'" . ( $this->discounting->current_discount['discount_expiration_date'] ? $this->discounting->current_discount['discount_expiration_date'] : date("Y-m-d", time() + 15552000) ) . "\', 1, \'discount_expiration_date{$this->popup_id}\')', 30);";

					$tbl .= "
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
						<div style=\"float:right;\">" .
							( $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this), 'E', 'discounting') ?
    							$this->form->hidden( array(
        							'discount_hash' => $this->discounting->current_discount['discount_hash']
    							) ) . "Update Discount"
    							:
    							( ! $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this), 'A', 'discounting') ?
        							"Add Discount" : NULL
    							)
    						) . "
							&nbsp;&nbsp;" .
    						( ( $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this), 'E', 'discounting') ) || ( ! $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this), 'A', 'discounting') ) ?
        						$this->form->button(
            						"value=+",
            						"onClick=submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=AddDiscountLine');"
        						) : NULL
        					) .
							( $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this), 'D', 'discounting') ?
    							"&nbsp;&nbsp;Delete Discount&nbsp;&nbsp;" .
    							$this->form->button(
        							"value=X",
        							"onClick=if(confirm('Are you sure you want to delete this discount? Any proposal that has used this discount will be updated, and the discount info will be removed. This action CANNOT be undone.')){submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=AddDiscountLine', 'delete=true');}"
    							) : NULL
    						) . "
						</div>" .
    					( $this->discounting->total ?
    						"<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->customer_hash}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>" : NULL
    					) . "
					</td>
				</tr>
			</table>";

		} elseif ( $this->customer_hash && $this->discounting->discount_hash && $action == 'showtable' ) { # Show the table for individual PRODUCT discounts for a specific discount record

			$total = $this->discounting->total_discounts;

			$num_pages = ceil($this->discounting->total_discounts / SUB_PAGNATION_NUM);
			$p = ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p;

			$start_from = SUB_PAGNATION_NUM * ( $p - 1 );
			$end = $start_from + SUB_PAGNATION_NUM;
			if ( $end > $this->discounting->total_discounts )
				$end = $this->discounting->total_discounts;

			$order_by_ops = array(
    			"product_name"	 =>	 "vendor_products.product_name",
				"catalog_code"	 =>	 "vendor_products.catalog_code",
			    "item_no"        =>  "discount_details.item_no"
			);

			$this->discounting->fetch_discount_items($start_from, SUB_PAGNATION_NUM, $order_by_ops[$order], $order_dir);

			$tbl = "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" colspan=\"2\" style=\"text-align:left;background-color:#efefef;color:#00477f;padding-top:10px;vertical-align:top;\">
						<div style=\"font-weight:normal;font-size:8pt\">
							<img src=\"images/folder.gif\" />
							&nbsp;
							<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'customer_hash={$this->customer_hash}');\" style=\"color:#00477f;text-decoration:none;\">Customer Discounting</a> >
							<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'customer_hash={$this->customer_hash}', 'action=addnew', 'discount_hash={$this->discounting->discount_hash}');\" style=\"color:#00477f;text-decoration:none;\">" . stripslashes($this->discounting->current_discount['discount_vendor']) . "</a>
							</a>
						</div>
						<div style=\"padding-top:5px;\">
							<img src=\"images/tree_down.gif\" />
							&nbsp;
							<span style=\"margin-bottom:5px;margin-top:10px;font-weight:bold;\">Item & Product Discounts</span>
						</div>
					</td>
				</tr>
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:100%;\">
							<tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"5\">" .
        							( $this->discounting->total_discounts ?
    									"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" . paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'customers', 'discount_info_form', $order, $order_dir, "'otf=1', 'customer_hash={$this->customer_hash}', 'popup_id={$this->popup_id}', 'discount_hash={$this->discounting->discount_hash}', 'action=showtable'") . "</div>
    									Showing " . ( $start_from + 1 ) . " - " . ( $start_from + SUB_PAGNATION_NUM > $this->discounting->total_discounts ? $this->discounting->total_discounts : $start_from + SUB_PAGNATION_NUM ) . " of {$this->discounting->total_discounts} Product Discounts for " . ( $this->discounting->current_discount['discount_type'] == 'C' ? stripslashes($this->discounting->current_discount['discount_customer']) : stripslashes($this->discounting->current_discount['discount_descr']) ) . "." : NULL
        							) . "
									<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">" .
            							( $this->p->ck(get_class($this), 'A', 'discounting') ?
    										"[<small><a href=\"javascript:void(0);\" onClick=\"" .
                							( in_array('*', $discount_items_exists) ?
    											"alert('" .
                    							( in_array('*', $discount_items_exists) ?
                        							"You have already created a global discount for all products under this vendor. If you have a specific product discount to add, in addition to the global product discount, you must first add the new product under the Products tab." :
                        							( $this->total_products > 0 ?
                            							'You have already created discounts for each of this vendors products. To create a new product discount, you must first add the new product under the Products tab.' : 'There are no products listed for this vendor. Please start by adding products under the Products tab.'
                        							)
                        						) . "');" : "agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'action=newprod_discount', 'discount_hash={$this->discounting->discount_hash}', 'customer_hash={$this->customer_hash}');"
                        					) . "\" class=\"link_standard\">New Discount</a></small>]" : NULL
                        				) . "
									</div>
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td style=\"width:25%;\">
									<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->customer_hash}', 'discount_hash={$this->discounting->discount_hash}', 'action=showtable', 'p=$p', 'order=product_name', 'order_dir=".($order == "product_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Product</a>" .
									( $order == 'product_name' ?
										"&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
									) . "
								</td>
                                <td style=\"width:15%;\">
                                    <a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->customer_hash}', 'discount_hash={$this->discounting->discount_hash}', 'action=showtable', 'p=$p', 'order=item_no', 'order_dir=".($order == "item_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Item/Code</a>" .
                                    ( $order == 'item_no' ?
                                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                    ) . "
                                </td>
								<td style=\"padding-left:15px;width:40%;\">Buy Disc</td>
								<td style=\"width:10%;text-align:right;\">List Disc</td>
								<td style=\"width:10%;text-align:right;\">Margin</td>
							</tr>";

							for ( $i = 0; $i < ($end - $start_from); $i++ ) {

								if ( $i < ( ( $end - $start_from ) - 1 ) )
    								$style = "border-bottom:1px solid #cccccc;";

								$tbl .= "
								<tr " . ( $this->p->ck(get_class($this), 'V', 'discounting') ? "onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'action=newprod_discount', 'customer_hash={$this->customer_hash}', 'discount_hash={$this->discounting->discount_hash}', 'item_hash={$this->discounting->discount_items[$i]['item_hash']}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">" .
    								( $this->discounting->discount_items[$i]['product_hash'] == '*' ?
    									"<td class=\"smallfont\" colspan=\"2\" " . ( $style ? "style=\"$style\"" : NULL ) . ">** All Products **</td>"
        								:
        								( $this->discounting->discount_items[$i]['discount_code'] ?
            								"<td class=\"smallfont\" style=\"$style\">[ DISCOUNT CODE ]</td>"
            								:
            								"<td class=\"smallfont\" " . ( $style ? "style=\"$style\"" : NULL ) . ">" .
            								( strlen($this->discounting->discount_items[$i]['product_name']) > 23 ?
                								stripslashes( substr($this->discounting->discount_items[$i]['product_name'], 0, 20) ) . '...' : stripslashes($this->discounting->discount_items[$i]['product_name'])
                							) . "
                							</td>"
            							)
        							) . "
								    <td class=\"smallfont\" style=\"$style\">" .
								    ( $this->discounting->discount_items[$i]['discount_code'] ?
								    	$this->discounting->discount_items[$i]['discount_code'] : $this->discounting->discount_items[$i]['item_no']
								    ) . "&nbsp;
								    </td>" .
								    ( $this->discounting->discount_items[$i]['discount_type'] == "T" ?
    									"<td class=\"smallfont\" style=\"padding-left:15px;$style\" colspan=\"3\">[Tiered Discount]</td>"
    								    :
    									"<td class=\"smallfont\" style=\"padding-left:15px;$style\">" .
    										( bccomp($this->discounting->discount_items[$i]['buy_discount1'], 0, 4) == 1 ?
                								round( bcmul($this->discounting->discount_items[$i]['buy_discount1'], 100, 4), 4) . "%&nbsp;" : "&nbsp;"
                    						) .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount2'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount2'], 100, 4), 4) . "%&nbsp;" : "&nbsp;"
                                            ) .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount3'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount3'], 100, 4), 4) . "%&nbsp;" : "&nbsp;"
                                            ) .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount4'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount4'], 100, 4), 4) . "%&nbsp;" : "&nbsp;"
                                            ) .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount5'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount5'], 100, 4), 4) . "%&nbsp;" : "&nbsp;"
                                            ) . "
										</td>
										<td class=\"smallfont\" style=\"width:15%;text-align:right;$style\">" .
                                            ( bccomp($this->discounting->discount_items[$i]['sell_discount'], 0, 4) == 1 ?
    											round( bcmul($this->discounting->discount_items[$i]['sell_discount'], 100, 4), 4) . "%" : "&nbsp;"
    										) . "
										</td>
										<td class=\"smallfont\" style=\"width:15%;text-align:right;$style\">" .
    										( bccomp($this->discounting->discount_items[$i]['gp_margin'], 0, 4) == 1 ?
    											round( bcmul($this->discounting->discount_items[$i]['gp_margin'], 100, 4), 2) . "%" : "&nbsp;"
    										) . "
										</td>"
    								) . "
								</tr>";

    							unset($style);
							}

							if ( ! $this->discounting->total_discounts )
								$tbl .= "
								<tr >
									<td colspan=\"5\" class=\"smallfont\">
                                        Discount has no associated product/item level discounts
										<div style=\"padding-top:5px;margin-left:25px;\">
											[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'action=newprod_discount', 'discount_hash={$this->discounting->discount_hash}', 'customer_hash={$this->customer_hash}');\" class=\"link_standard\">create new</a></small>]
										</div>
									</td>
								</tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";

		} elseif ( $this->customer_hash && $action == 'newprod_discount' ) { # Create a new PRODUCT discount for a specific discount record under a vendor

            if ( ! $this->discounting->item_hash ) { # Repeat last user action

            	$new_action = 1;

                $r = $this->db->query("SELECT
	                                       t1.item_no,
	                                       t1.discount_code
                                       FROM discount_details t1
                                       LEFT JOIN discounting t2 ON t2.discount_hash = t1.discount_hash
                                       WHERE t2.customer_hash = '{$this->discounting->current_discount['customer_hash']}'
                                       ORDER BY t1.obj_id DESC
                                       LIMIT 1");
                if ( $this->db->result($r, 0, 'item_no') )
                    $new_action = 2;
                elseif ( $this->db->result($r, 0, 'discount_code') )
                    $new_action = 3;
            }

			$tbl = "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" colspan=\"2\" style=\"text-align:left;background-color:#efefef;color:#00477f;padding-top:10px;vertical-align:top;\">
						<div style=\"font-weight:normal;font-size:8pt\">
							<img src=\"images/folder.gif\" />
							&nbsp;
							<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'customer_hash={$this->customer_hash}');\" style=\"color:#00477f;text-decoration:none;\">Customer Discounting</a> >
							<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'customer_hash={$this->customer_hash}', 'action=addnew', 'discount_hash={$this->discounting->discount_hash}');\" style=\"color:#00477f;text-decoration:none;\">" . stripslashes($this->discounting->current_discount['discount_vendor']) . "</a> >
							<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'customer_hash={$this->customer_hash}', 'action=showtable', 'discount_hash={$this->discounting->discount_hash}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" style=\"color:#00477f;text-decoration:none;\">Customer Discount Table</a>
						</div>
						<div style=\"padding-top:5px;\">
							<img src=\"images/tree_down.gif\" />
							&nbsp;
							<span style=\"margin-bottom:5px;margin-top:10px;font-weight:bold;\">" .
                            ( $this->discounting->item_hash ?
                                "Edit" : "Create New"
                            ) . " Item/Product Discount" .
                            ( $this->discounting->item_hash ?
                                " : " .
                                ( $this->discounting->current_item['product_hash'] == '*' ?
                                    "** All Products **" : stripslashes($this->discounting->current_item['product_name'])
                                ) : NULL
                            ) . "
							</span>
						</div>
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"background-color:#ffffff;padding-top:10px;padding-left:45px;vertical-align:top;\" >" .
                        ( ! $this->discounting->item_hash ?
	                        "<div id=\"disc_type_selector\">What type of discount are you creating?</div>
                            <div style=\"margin-top:8px;margin-bottom:10px;\">
                                <table cellpadding=\"2\" cellspacing=\"1\" style=\"background-color:#cccccc;\">
                                    <tr>
                                        <td id=\"disc_type_op_1\" style=\"background-color:#efefef;\">" .
                                            $this->form->radio(
                                                "name=disc_type",
                                                "id=disc_type_radio1",
                                                "value=1",
                                                ( ! $products_out && in_array('*', $discount_items_exists) ? "disabled title=There are no remaining products available for discounting. You may add additional products under Vendors->Products." : NULL ),
                                                "onClick=if(this.checked){toggle_display('discount_1_content{$this->popup_id}', 'block');toggle_display('discount_2_content{$this->popup_id}', 'none');toggle_display('discount_3_content{$this->popup_id}', 'none');}",
                                                ( $new_action != 2 && ( ! $discount_items_exists || ! in_array('*', $discount_items_exists) ) ? "checked" : NULL ),
                                                "style=border:0;padding:3px;"
                                            ) . "
                                        </td>
                                        <td id=\"disc_td_op_1\" style=\"background-color:#efefef;\">" .
                                            ( ! $products_out && in_array('*', $discount_items_exists) ?
                                                "<span style=\"color:8f8f8f;\" title=\"There are no remaining products available for discounting. You may add additional products under Vendors->Products.\">&nbsp;&nbsp;A discount to be applied to an entire product line</span>"
                                                :
                                                "<a href=\"javascript:void(0);\" onClick=\"if(\$('disc_type_radio1')){\$('disc_type_radio1').checked=1;toggle_display('discount_1_content{$this->popup_id}', 'block');toggle_display('discount_2_content{$this->popup_id}', 'none');toggle_display('discount_3_content{$this->popup_id}', 'none');}\" style=\"text-decoration:none;color:#000000;\" onMouseOver=\"\$('disc_type_op_1').setStyle({backgroundColor:'#c1d2ee'});\$('disc_td_op_1').setStyle({backgroundColor:'#c1d2ee'});\" onMouseOut=\"\$('disc_type_op_1').setStyle({backgroundColor:'#efefef'});\$('disc_td_op_1').setStyle({backgroundColor:'#efefef'});\">&nbsp;&nbsp;A discount to be applied to an entire product line</a>"
                                            ) . "
                                        </td>
                                    </tr>
                                    <tr>
                                        <td id=\"disc_type_op_2\" style=\"background-color:#efefef;\">" .
                                            $this->form->radio(
                                                "name=disc_type",
                                                "id=disc_type_radio2",
                                                "value=2",
                                                ( $new_action == 2 || ( ! $products_out && in_array('*', $discount_items_exists) ) ? "checked" : NULL ),
                                                "onClick=if(this.checked){toggle_display('discount_2_content{$this->popup_id}', 'block');toggle_display('discount_1_content{$this->popup_id}', 'none');toggle_display('discount_3_content{$this->popup_id}', 'none');}",
                                                "style=border:0;padding:3px;"
                                            ) . "
                                        </td>
                                        <td id=\"disc_td_op_2\" style=\"background-color:#efefef;\">
                                            <a href=\"javascript:void(0);\" onClick=\"if(\$('disc_type_radio2')){\$('disc_type_radio2').checked=1;toggle_display('discount_2_content{$this->popup_id}', 'block');toggle_display('discount_1_content{$this->popup_id}', 'none');toggle_display('discount_3_content{$this->popup_id}', 'none');}\" style=\"text-decoration:none;color:#000000;\" onMouseOver=\"\$('disc_type_op_2').setStyle({backgroundColor:'#c1d2ee'});\$('disc_td_op_2').setStyle({backgroundColor:'#c1d2ee'});\" onMouseOut=\"\$('disc_type_op_2').setStyle({backgroundColor:'#efefef'});\$('disc_td_op_2').setStyle({backgroundColor:'#efefef'});\">&nbsp;&nbsp;A discount to be applied only on specific items within a product line</a>
                                        </td>
                                    </tr>
                                <tr>
                                    <td id=\"disc_type_op_3\" style=\"background-color:#efefef;\">" .
                                        $this->form->radio("name=disc_type",
                                                             "id=disc_type_radio3",
                                                             "value=3",
                                                             ( $new_action == 3 ? "checked" : NULL ),
                                                             "onClick=if(this.checked){toggle_display('discount_3_content{$this->popup_id}','block');toggle_display('discount_1_content{$this->popup_id}','none');toggle_display('discount_2_content{$this->popup_id}','none');}",
                                                             "style=border:0;padding:3px;")."
                                    </td>
                                    <td id=\"disc_td_op_3\" style=\"background-color:#efefef;\">
                                        <a href=\"javascript:void(0);\" onClick=\"if(\$('disc_type_radio3')){\$('disc_type_radio3').checked=1;toggle_display('discount_3_content{$this->popup_id}','block');toggle_display('discount_1_content{$this->popup_id}','none');toggle_display('discount_2_content{$this->popup_id}','none');}\" style=\"text-decoration:none;color:#000000;\" onMouseOver=\"\$('disc_type_op_3').setStyle({backgroundColor:'#c1d2ee'});\$('disc_td_op_3').setStyle({backgroundColor:'#c1d2ee'});\" onMouseOut=\"\$('disc_type_op_3').setStyle({backgroundColor:'#efefef'});\$('disc_td_op_3').setStyle({backgroundColor:'#efefef'});\">&nbsp;&nbsp;A discount to be applied based on item discount code</a>
                                    </td>
                                </tr>
                                </table>
                            </div>"
                            :
	                        $this->form->hidden( array(
	                            'disc_type' =>
    	                        ( $this->discounting->current_item['item_no'] ?
        	                        2 :
        	                        ( $this->discounting->current_item['discount_code'] ?
            	                        3 : 1
            	                    )
            	                )
	                        ) )
                        ) . "
    					<div " . ( ! $this->discounting->item_hash  ? "style=\"margin-top:10px;\"" : NULL ) . " id=\"err6_7{$this->popup_id}\">
                            <div id=\"discount_1_content{$this->popup_id}\" style=\"display:" . ( ( ! $this->discounting->item_hash && $new_action != 2 && ( ! $discount_items_exists || ! in_array('*', $discount_items_exists) ) ) || ( $this->discounting->item_hash && ! $this->discounting->current_item['item_no'] && ! $this->discounting->current_item['discount_code'] ) ? "block" : "none" ) . ";padding-top:10px;\">
                                <div style=\"padding-top:5px;\" id=\"product_listing_label{$this->popup_id}\">" .
                                ( $this->discounting->item_hash ?
                                    "Viewing discount for: " : "Which product(s) should be used for this discount?"
                                ) . "
                                </div>
                                <div style=\"padding-top:5px;padding-bottom:10px;\" id=\"product_listing{$this->popup_id}\">" .
                                    ( $this->discounting->item_hash ?
	                                    "<strong>" .
	                                    ( $this->discounting->current_item['product_hash'] == '*' ?
	                                        "** All Products **" : stripslashes($this->discounting->current_item['product_name'])
	                                    ) . "
	                                    </strong>"
	                                    :
	                                    $this->form->select(
	                                        "discount_product_hash[]",
	                                        ( $products_out ?
	                                            ( ! in_array('*', $discount_items_exists) ?
	                                                array_merge($products_out, array("** APPLY ON ALL PRODUCTS **") ) : $products_out
	                                            )
	                                            :
	                                            ( ! $discount_items_exists || ! in_array('*', $discount_items_exists) ?
	                                                array("** APPLY ON ALL PRODUCTS **") : NULL
	                                            )
	                                        ),
	                                        $this->discounting->current_item['product_hash'],
	                                        ( $products_in ?
	                                            ( ! in_array('*', $discount_items_exists) ?
	                                                array_merge($products_in, array("*") ) : $products_in
	                                            )
	                                            :
	                                            ( ! $discount_items_exists || ! in_array('*', $discount_items_exists) ?
	                                                array("*") : NULL
	                                            )
	                                        ),
	                                        "multiple",
	                                        "size=5",
	                                        "style=width:225px;",
	                                        "blank=1",
	                                        "onClick=if(this.options[this.options.length-1].selected){for(var i=this.options.length-2;i>=0;i--){if(this.options[i].selected){this.options[i].selected=0;}}}"
	                                    )
                                    ) . "
                                </div>
                            </div>
                            <div id=\"discount_2_content{$this->popup_id}\" style=\"display:" . ( ( ! $this->discounting->item_hash && $new_action == 2 ) || ( ! $this->discounting->item_hash && ( ! $products_out && in_array('*', $discount_items_exists) ) ) || ( $this->discounting->item_hash && $this->discounting->current_item['item_no'] ) ? "block" : "none" ) . ";padding-top:10px;\">
                                <div style=\"padding-top:5px;\" id=\"item_discount_label{$this->popup_id}\">" .
                                ( $this->discounting->item_hash ?
                                    "Item discount for product line:" : "Apply a discount to specific items within the product line:"
                                ) . "
                                </div>
                                <div style=\"padding-top:5px;margin-bottom:10px;\" id=\"item_discount{$this->popup_id}\">" .
                                    ( $this->discounting->item_hash ?
                                        "<strong>" .
                                        ( $this->discounting->current_item['product_hash'] == '*' ?
                                            "** All Products **" : stripslashes($this->discounting->current_item['product_name'])
                                        ) . "</strong>"
                                        :
                                        $this->form->select(
                                            "discount_product_hash_2[]",
                                            $products_out1,
                                            $this->discounting->current_item['product_hash'],
                                            $products_in1,
                                            "blank=1"
                                        )
                                    ) . "
                                </div>
                                <div id=\"item_no_label\">" .
                                    ( $this->discounting->item_hash ?
                                        "Item Number:" : "Which item numbers should be included in this discount?"
                                    ) . "
                                </div>
                                <div style=\"padding-bottom:10px;padding-top:5px;\" id=\"item_listing{$this->popup_id}\">" .
                                    ( $this->discounting->item_hash ?
                                        $this->form->text_box(
                                            "name=discount_item_no",
                                            "value={$this->discounting->current_item['item_no']}",
                                            "maxlength=64"
                                        )
                                        :
                                        $this->form->text_area(
                                            "name=discount_item_no",
                                            "value=Enter each item number on a new line...",
                                            "onFocus=if(this.value=='Enter each item number on a new line...'){this.value='';}",
                                            "rows=4",
                                            "cols=55"
                                        )
                                    ) . "
                                </div>
                            </div>
							<div id=\"discount_3_content{$this->popup_id}\" style=\"display:" . ( ( ! $this->discounting->item_hash && $new_action == 3 ) || ( $this->discounting->item_hash && $this->discounting->current_item['discount_code'] ) ? "block" : "none" ) . ";padding-bottom:10px;padding-top:5px;\">
                                <div style=\"padding-top:5px;\" id=\"discount_code_label{$this->popup_id}\">" .
                                ( $this->discounting->item_hash ?
                                    "Discount Code: *" : "Which discount codes should be included in this discount? *"
                                ) . "
                                </div>
                                <div id=\"discount_code{$this->popup_id}\">" .
    								( $this->discounting->item_hash ?
										$this->form->text_box(
	    									"name=discount_code",
											"value={$this->discounting->current_item['discount_code']}",
											"maxlength=12"
										)
										:
										$this->form->text_area(
	                                        "name=discount_code",
	                                        "value=Enter each discount code on a new line...",
	                                        "onFocus=if(this.value=='Enter each discount code on a new line...'){this.value='';}",
	                                        "rows=4",
	                                        "cols=55"
										)
									) . "
								</div>
								<div style=\"margin-top:5px;\" id=\"discount_code_descr{$this->popup_id}\">
									Description:
								</div>
								<div >" .
									$this->form->text_box(
    									"name=discount_descr",
										"value=".$this->discounting->current_item['discount_descr'],
										"maxlength=64",
										"style=width:225px;"
									) . "
								</div>
							</div>
                        </div>
						<div " . ( ! $this->discounting->item_hash  ? "style=\"margin-top:5px;\"" : NULL ) . " id=\"err6_8\">" .
                            ( $this->discounting->item_hash ?
    							"Discounting Method:" : "Which discounting method should be used? "
                            ) . "
						</div>
						<div style=\"padding-top:5px;\">" .
                            $this->form->select(
                                'discount_type',
                                array("Non-Tiered or Multi-Level Discount", "Tiered Discount by List Price"),
                                $this->discounting->current_item['discount_type'],
                                array('F', 'T'),
                                "onChange=toggle_display('tiered_table{$this->popup_id}', this.options[this.selectedIndex].value=='T'?'block':'none');toggle_display('fixed_table{$this->popup_id}', this.options[this.selectedIndex].value=='T'?'none':'block');", "blank=1"
                            ) . "
						</div>
						<div style=\"display:" . ( $this->discounting->current_item['discount_type'] == 'T' ? "block" : "none" ) . ";margin-top:10px;padding:10px 0;\" id=\"tiered_table{$this->popup_id}\">
							<table style=\"width:95%;\" cellpadding=\"5\">
								<tr>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
										<table >
											<tr>
												<td colspan=\"2\" style=\"font-weight:bold;\">Tier 1:</td>
											</tr>
											<tr>
												<td style=\"text-align:right;\" id=\"err6_12{$this->popup_id}\">From: </td>
												<td style=\"vertical-align:bottom;padding-left:5px;\">
													\$ 0.00
												</td>
											</tr>
											<tr>
												<td style=\"text-align:right;\" id=\"err6_13{$this->popup_id}\">To: </td>
												<td style=\"vertical-align:bottom;padding-left:5px;\">
													\$ " .
													$this->form->text_box(
                                                        'name=tier1_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier1_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier1_to'], 2) : NULL ),
                                                        'size=12',
                                                        'maxlength=12',
                                                        'style=text-align:right;',
    													'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier2_from').innerHTML='$ '+formatCurrency(\$F('tier1_to'), .01);"
													) . "
												</td>
											</tr>
											<tr>
												<td style=\"text-align:right;\" id=\"err6_14{$this->popup_id}\">Buy Discount: </td>
												<td style=\"vertical-align:bottom;padding-left:17px;\">" .
													$this->form->text_box(
    													'name=tier1_buy_discount',
    													'value=' . ( bccomp($this->discounting->current_item['tier1_buy_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier1_buy_discount'], 100, 4): NULL ),
    													'size=6',
    													'maxlength=6',
    													'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
    												) . "&nbsp;%
												</td>
											</tr>
											<tr>
												<td style=\"text-align:right;\" id=\"err6_15{$this->popup_id}\">% Off List: </td>
												<td style=\"vertical-align:bottom;padding-left:17px;\">" .
													$this->form->text_box(
    													'name=tier1_sell_discount',
    													'value=' . ( bccomp($this->discounting->current_item['tier1_sell_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier1_sell_discount'], 100, 4) : NULL ),
    													'size=6',
    													'maxlength=6',
    													'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
													) . "&nbsp;%
												</td>
											</tr>
										</table>
									</td>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
										<table >
											<tr>
												<td colspan=\"2\" style=\"font-weight:bold;\">Tier 2:</td>
											</tr>
											<tr>
												<td style=\"text-align:right;\" id=\"err6_16{$this->popup_id}\">From: </td>
												<td style=\"vertical-align:bottom;padding-left:5px;\">
													<div id=\"tier2_from\">" .
													( bccomp($this->discounting->current_item['tier2_from'], 0, 2) == 1 ?
														"\$ " . number_format($this->discounting->current_item['tier2_from'], 2) : NULL
													) . "
													</div>
												</td>
											</tr>
											<tr>
												<td style=\"text-align:right;\" id=\"err6_17{$this->popup_id}\">To: </td>
												<td style=\"vertical-align:bottom;padding-left:5px;\">
													\$ " .
													$this->form->text_box(
    													'name=tier2_to',
    													'value=' . ( bccomp($this->discounting->current_item['tier2_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier2_to'], 2) : NULL ),
    													'size=12',
    													'maxlength=12',
    													'style=text-align:right;',
                                                        'onFocus=this.select();',
    													"onBlur=this.value=formatCurrency(this.value);\$('tier3_from').innerHTML='$ '+formatCurrency(\$F('tier2_to'), .01);"
													) . "
												</td>
											</tr>
											<tr>
												<td style=\"text-align:right;\" id=\"err6_18{$this->popup_id}\">Buy Discount: </td>
												<td style=\"vertical-align:bottom;padding-left:17px;\">" .
													$this->form->text_box(
    													'name=tier2_buy_discount',
    													'value=' . ( bccomp($this->discounting->current_item['tier2_buy_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier2_buy_discount'], 100, 4) : NULL ),
    													'size=6',
    													'maxlength=6',
    													'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
													) . "&nbsp;%
												</td>
											</tr>
											<tr>
												<td style=\"text-align:right;\" id=\"err6_19{$this->popup_id}\">List Discount: </td>
												<td style=\"vertical-align:bottom;padding-left:17px;\">" .
													$this->form->text_box(
    													'name=tier2_sell_discount',
    													'value=' . ( bccomp($this->discounting->current_item['tier2_sell_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier2_sell_discount'], 100, 4) : NULL ),
    													'size=6',
    													'maxlength=6',
    													'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
													) . "&nbsp;%
												</td>
											</tr>
										</table>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 3:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_20{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier3_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier3_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier3_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_21{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier3_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier3_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier3_to'], 2) : NULL ),
                                                        'size=12',
                                                        'maxlength=12',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier4_from').innerHTML='$ '+formatCurrency(\$F('tier3_to'), .01);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_22{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier3_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier3_buy_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier3_buy_discount'], 100, 4) : NULL ),
                                                        'size=6',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_23{$this->popup_id}\">Discount Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier3_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier3_sell_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier3_sell_discount'], 100, 4) : NULL ),
                                                        'size=6',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 4:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_24{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier4_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier4_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier4_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_25{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier4_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier4_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier4_to'], 2) : NULL ),
                                                        'size=12',
                                                        'maxlength=12',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier5_from').innerHTML='$ '+formatCurrency(\$F('tier4_to'), .01);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_26{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier4_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier4_buy_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier4_buy_discount'], 100, 4) : NULL ),
                                                        'size=6',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_27{$this->popup_id}\">Discount Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier4_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier4_sell_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier4_sell_discount'], 100, 4) : NULL ),
                                                        'size=6',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 5:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_50{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier5_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier5_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier5_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_51{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier5_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier5_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier5_to'], 2) : NULL ),
                                                        'size=12',
                                                        'maxlength=12',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier6_from').innerHTML='$ '+formatCurrency(\$F('tier5_to'), .01);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_52{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier5_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier5_buy_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier5_buy_discount'], 100, 4) : NULL ),
                                                        'size=6',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_53{$this->popup_id}\">Discount Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier5_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier5_sell_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier5_sell_discount'], 100, 4) : NULL ),
                                                        'size=6',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 6:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_54{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier6_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier6_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier6_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_55{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier6_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier6_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier6_to'], 2) : NULL ),
                                                        'size=12',
                                                        'maxlength=12',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_56{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier6_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier6_buy_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier6_buy_discount'], 100, 4) : NULL ),
                                                        'size=6',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_57{$this->popup_id}\">Discount Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier6_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier6_sell_discount'], 0, 4) == 1 ? bcmul($this->discounting->current_item['tier6_sell_discount'], 100, 4) : NULL ),
                                                        'size=6',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
														"onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
								</tr>
							</table>
						</div>
						<div id=\"fixed_table{$this->popup_id}\" style=\"display:" . ( $this->discounting->current_item['discount_type'] == 'F' || ! $this->discounting->item_hash ? "block" : "none" ) . ";margin-top:10px;margin-left:10px;padding:10px;\">
							<table >
								<tr>
									<td style=\"text-align:right;width:150px;\" id=\"err6_9{$this->popup_id}\">Buy Discount: </td>
									<td >" .
										$this->form->text_box(
    										'name=buy_discount1',
    										'value=' . ( bccomp($this->discounting->current_item['buy_discount1'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount1'], 100, 4), 4) : NULL ),
    										'size=5',
    										'maxlength=6',
    										'style=text-align:right;',
											"onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
										) . "&nbsp;%&nbsp;" .
                                        $this->form->text_box(
                                            'name=buy_discount2',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount2'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount2'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
	                                        "onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;" .
                                        $this->form->text_box(
                                            'name=buy_discount3',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount3'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount3'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
	                                        "onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;" .
                                        $this->form->text_box(
                                            'name=buy_discount4',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount4'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount4'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
											"onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;" .
                                        $this->form->text_box(
                                            'name=buy_discount5',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount5'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount5'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
	                                        "onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:150px;\" id=\"err6_10{$this->popup_id}\">GP Margin: </td>
									<td>" .
										$this->form->hidden( array(
    										'tmp_gp_margin'       =>  '',
    										'tmp_list_discount'   =>  ''
										) ) .
										$this->form->text_box(
    										"name=gp_margin",
											"value=" . ( bccomp($this->discounting->current_item['gp_margin'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['gp_margin'], 100, 4), 4) : NULL ),
											"maxlength=6",
											"size=4",
											"style=text-align:right;",
											"onFocus=\$('tmp_gp_margin').value=this.value;this.select();",
											"onKeyDown=if(\$F('tmp_gp_margin')!=this.value){clear_values('sell_discount')}",
											"onChange=validate_decimal(this);"
										) . "&nbsp;%
										&nbsp;<strong>OR</strong>&nbsp;&nbsp;" .
										$this->form->text_box(
    										"name=sell_discount",
											"value=" . ( bccomp($this->discounting->current_item['sell_discount'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['sell_discount'], 100, 4), 4) : NULL ),
											"size=4",
											"maxlength=6",
											"style=text-align:right;",
											"onFocus=\$('tmp_list_discount').value=this.value;this.select();",
											"onKeyDown=if(\$F('tmp_list_discount')!=this.value){clear_values('gp_margin')}",
											"onChange=validate_decimal(this);"
										) . "&nbsp;%
                                        <span id=\"err6_10a{$this->popup_id}\">Discount Off List</span>
									</td>
								</tr>
							</table>
						</div>
						<div style=\"margin-top:10px;padding:10px 0;\">
							If this discount has custom freight terms, enter them here:
							<div style=\"padding-top:5px;margin-bottom:10px;\">
								<span id=\"err6_40{$this->popup_id}\">If under $</span>&nbsp;" .
								$this->form->text_box(
    								"name=discount_frf_amount",
									"value=" . ( bccomp($this->discounting->current_item['discount_frf_amount'], 0, 2) == 1 ? number_format($this->discounting->current_item['discount_frf_amount'], 2) : NULL ),
									"size=9",
									"style=text-align:right;",
									"onBlur=this.value=formatCurrency(this.value);",
    								"onFocus=this.select();"
                                ) . "
								&nbsp;" .
                                $this->form->select(
                                    "discount_frf_amount_type",
                                    array("List", "Net"),
                                    $this->discounting->current_item['discount_frf_amount_type'],
                                    array("L", "N")
                                ) . "
								&nbsp;<span id=\"err6_41{$this->popup_id}\">then add </span>" .
								$this->form->text_box(
    								"name=discount_frf_amount_add",
									"value=" .
    								( $this->discounting->current_item['discount_frf_amount_add_type'] == 'P' ?
        								round( bcmul($this->discounting->current_item['discount_frf_amount_add'], 100, 4), 4) :
        								( bccomp($this->discounting->current_item['discount_frf_amount_add'], 0, 2) == 1 ?
            								number_format($this->discounting->current_item['discount_frf_amount_add'], 2) : NULL
            							)
            						),
									"size=6",
									"style=text-align:right;",
            						"onFocus=this.select();"
								) . "
								&nbsp;" .
								$this->form->select(
    								"discount_frf_amount_add_type",
									array("dollars", "%"),
									$this->discounting->current_item['discount_frf_amount_add_type'],
									array("D", "P"),
									"onChange=if(this.options[this.selectedIndex].value=='P'){\$('discount_freight_amount_add_type_of_holder{$this->popup_id}').style.visibility='visible';}else{\$('discount_freight_amount_add_type_of_holder{$this->popup_id}').style.visibility='hidden';}"
								) . "
								<span id=\"discount_freight_amount_add_type_of_holder{$this->popup_id}\" style=\"visibility:" . ( $this->discounting->current_item['discount_frf_amount_add_type'] == 'P' ? "visible" : "hidden" ) . ";\">
									<span id=\"err6_42{$this->popup_id}\">of</span> " .
									$this->form->select(
    									"discount_frf_amount_add_type_of",
										array("List", "Net"),
										$this->discounting->current_item['discount_frf_amount_add_type_of'],
										array("L", "N")
									) . "
								</span>
								<table>
									<tr>
										<td style=\"padding-left:10px;padding-top:5px;\" id=\"discount_frf_effective_date_holder{$this->popup_id}\"></td>
										<td style=\"padding-top:5px;\" id=\"discount_frf_expire_date_holder{$this->popup_id}\"></td>
									</tr>
								</table>";

								$this->content["jscript"][] = "
								setTimeout('DateInput(\'discount_frf_effective_date\', " . ( $this->discounting->current_item['discount_frf_effective_date'] ? 'true' : 'false').", \'YYYY-MM-DD\', \'".($this->discounting->current_item['discount_frf_effective_date'] ? $this->discounting->current_item['discount_frf_effective_date'] : NULL ) . "\', 1, \'discount_frf_effective_date_holder{$this->popup_id}\', \'<span id=\"err6_44{$this->popup_id}\">Effective:</span>&nbsp;\')', 22);
								setTimeout('DateInput(\'discount_frf_expire_date\', " . ( $this->discounting->current_item['discount_frf_expire_date'] ? 'true' : 'false').", \'YYYY-MM-DD\', \'".($this->discounting->current_item['discount_frf_expire_date'] ? $this->discounting->current_item['discount_frf_expire_date'] : NULL ) . "\', 1, \'discount_frf_expire_date_holder{$this->popup_id}\', \'<span id=\"err6_45{$this->popup_id}\">Thru:</span>&nbsp;\')', 23);";

							$tbl .= "
							</div>
                            Are the freight terms on this discount quoted?
                            <div style=\"padding-top:5px;margin-left:15px;\">" .
                                $this->form->checkbox(
                                    "name=discount_frf_quoted",
                                    "value=1",
                                    ( $this->discounting->current_item['discount_frf_quoted'] ? "checked" : NULL )
                                ) . "
                                Quoted?
                            </div>
						</div>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
						<div style=\"float:right;\">" .
							$this->form->hidden( array(
    							'discount_hash' => $this->discounting->discount_hash
							) ) .
							( $this->discounting->item_hash && $this->p->ck(get_class($this), 'E', 'discounting') ?
    							$this->form->hidden( array(
        							'item_hash' => $this->discounting->item_hash
    							) ) . "Update Discount" :
    							( ! $this->discounting->item_hash && $this->p->ck(get_class($this), 'A', 'discounting') ?
        							"Add Discount" : NULL
    							) ) . "
							&nbsp;&nbsp;" .
							( ( $this->discounting->item_hash && $this->p->ck(get_class($this), 'E', 'discounting') ) || ( ! $this->discounting->item_hash && $this->p->ck(get_class($this), 'A', 'discounting') ) ?
    							$this->form->button(
        							"value=+",
        							"onClick=submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=AddDiscountItem');"
    							) : NULL
    						) .
							( $this->discounting->item_hash && $this->p->ck(get_class($this), 'D', 'discounting') ?
    							"&nbsp;&nbsp;Delete Discount&nbsp;&nbsp;" .
    							$this->form->button(
        							"value=X",
        							"onClick=if (confirm('Are you sure you want to delete this product discount? This action CANNOT be undone!')){submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=AddDiscountItem', 'delete=true');}"
    							) : NULL
    						) . "
						</div>
						<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'customer_hash={$this->customer_hash}', 'action=showtable', 'discount_hash={$this->discounting->discount_hash}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>
					</td>
				</tr>
			</table>";

			$this->content['jscript'][] = "
			validate_decimal = function() {

				var el = arguments[0];
				var elval = parseFloat( \$F(el) ).toFixed(4);

				if ( elval > 100 )
					\$(el).value = '100';
				if ( elval < 0 )
					\$(el).value = '';

			}
			";

		} elseif ( $this->customer_hash ) { # Show all the discount records listed under a specific vendor

			$p = $this->ajax_vars['p'];
			$order = $this->ajax_vars['order'];
			$order_dir = $this->ajax_vars['order_dir'];

			$total = $this->discounting->total;
			$num_pages = ceil($this->discounting->total / SUB_PAGNATION_NUM);

			$p = ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p;

			$start_from = SUB_PAGNATION_NUM * ($p - 1);
			$end = $start_from + SUB_PAGNATION_NUM;
			if ( $end > $this->discounting->total )
				$end = $this->discounting->total;

			$order_by_ops = array(
    			"discount_vendor"				=>	"vendors.vendor_name",
				"discount_id"					=>	"discounting.discount_id",
				"discount_expiration_date"		=>	"discounting.discount_expiration_date"
			);

			$this->discounting->fetch_discounts($start_from, $order_by_ops[ ( $order ? $order : 'discount_descr' ) ], $order_dir);

			$tbl = "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\">
									<div style=\"float:right;font-weight:normal;padding-right:10px;\">" . paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'customers', 'discount_info_form', $order, $order_dir, "'otf=1', 'customer_hash={$this->customer_hash}', 'popup_id={$this->popup_id}'") . "</div>
									Showing " . ( $start_from + 1 ) . " - " .
                        			( $start_from + SUB_PAGNATION_NUM > $this->discounting->total ?
                            			$this->discounting->total : $start_from + SUB_PAGNATION_NUM
                            		) . " of {$this->discounting->total} Discount Records for " . stripslashes($this->current_customer['customer_name']) . "." .
									( $this->p->ck(get_class($this), 'A', 'discounting') ?
										"<div style=\"padding-left:15px;font-weight:normal;padding-top:5px;\">[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'action=addnew', 'customer_hash={$this->customer_hash}');\" class=\"link_standard\">New Discount</a></small>]</div>" : NULL
									) . "
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->customer_hash}', 'p=$p', 'order=discount_vendor', 'order_dir=".($order == "discount_vendor" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Vendor</a>" .
									( $order == 'discount_vendor' ?
										"&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
                                    ) . "
								</td>
								<td >Discount Descr</td>
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->customer_hash}', 'p=$p', 'order=discount_id', 'order_dir=".($order == "discount_id" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Discount ID</a>" .
									( $order == 'discount_id' ?
										"&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
									 ) . "
								</td>
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->customer_hash}', 'p=$p', 'order=discount_expiration_date', 'order_dir=".($order == "discount_expiration_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Expiration Date</a>" .
									( $order == 'discount_expiration_date' ?
										"&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
									 ) . "
								</td>
							</tr>";

							for ( $i = 0; $i < ($end - $start_from); $i++ ) {

								if ( $i < ( ( $end - $start_from ) - 1 ) )
    								$style = "border-bottom:1px solid #cccccc;";

								$tbl .= "
								<tr " . ( $this->p->ck(get_class($this), 'V', 'discounting') ? "onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'action=addnew', 'customer_hash={$this->customer_hash}', 'discount_hash={$this->discounting->discounts[$i]['discount_hash']}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
									<td class=\"smallfont\" " . ( $style ? "style=\"$style\"" : NULL ) . ">" . stripslashes($this->discounting->discounts[$i]['discount_vendor']) . "</td>
									<td class=\"smallfont\" nowrap " . ( $style ? "style=\"$style\"" : NULL ) . ">" .
									( strlen($this->discounting->discounts[$i]['discount_descr']) > 25 ?
										substr($this->discounting->discounts[$i]['discount_descr'], 0, 25) . "..." : $this->discounting->discounts[$i]['discount_descr']
									) . "
									</td>
									<td class=\"smallfont\" " . ( $style ? "style=\"$style\"" : NULL ) . ">{$this->discounting->discounts[$i]['discount_id']}</td>
									<td class=\"smallfont\" style=\"$style" .
										( strtotime($this->discounting->discounts[$i]['discount_expiration_date']) < strtotime( date("Y-m-d") ) ?
	    									"color:#ff0000;" :
	    									( strtotime($this->discounting->discounts[$i]['discount_expiration_date']) - 2592000 <= strtotime( date("Y-m-d") ) ?
												"font-weight:bold;color:#FFD65C" : NULL
	    									)
	    								) .
    								"\">" . date(DATE_FORMAT, strtotime($this->discounting->discounts[$i]['discount_expiration_date'])) . "
    								</td>
								</tr>";

                                unset($style);
							}

                            if ( ! $this->discounting->total )
                                $tbl .= "
                                <tr>
                                    <td class=\"smallfont\" colspan=\"3\">No discounts have been created under this customer.</td>
                                </tr>";


				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";
		}

		if ( $this->ajax_vars['otf'] )
			$this->content['html']["tcontent5{$this->popup_id}"] = $tbl;
        else
    		return $tbl;
	}

	function location_info_form() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if ( $this->ajax_vars['customer_hash'] && ! $this->customer_hash ) {

			if ( ! $this->fetch_master_record($this->ajax_vars['customer_hash']) )
				unset($this->ajax_vars['customer_hash']);
		}

		$this->content['jscript'][] = "setTimeout(function(){initCountry('location_countrySelect', 'location_stateSelect');}, 500);";
        $action = $this->ajax_vars['location_action'];

		if ( $this->ajax_vars['location_hash'] ) {

			if ( ! $this->fetch_location_record($this->ajax_vars['location_hash'] ) ) {

				unset($this->ajax_vars['location_hash']);
				return $this->__trigger_error("A system error was encountered when attempting to lookup customer location. Please reload customer window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
			}
		}

		if ( ! $this->customer_hash || ( $this->customer_hash && $action == 'addnew' ) || ( $this->customer_hash && $this->ajax_vars['parent_win_open'] == 'add_from_proposal' ) ) {

			if ( $action == 'addnew' )
    			$this->content['focus'] = 'location_name';

            $this->cust->fetch_class_fields('location');

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" style=\"padding-top:10px;text-align:right;background-color:#ffffff;width:35%;vertical-align:top;\" id=\"err4_1{$this->popup_id}\">Location Name: *</td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_box(
        					"name=location_name",
                            "value=" . stripslashes($this->current_location['location_name']),
                            "size=35",
                            "maxlength=128",
                            ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL )
                        ) . "
    				</td>
				</tr>
                <tr>
                    <td class=\"smallfont\" style=\"padding-top:10px;text-align:right;background-color:#ffffff;width:35%;\">Account No:</td>
                    <td style=\"background-color:#ffffff;\">" .
                        $this->form->text_box(
                            "name=location_account_no",
                            "value={$this->current_location['location_account_no']}",
                            "size=15",
                            "maxlength=32",
                            ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL)
                        ) . "
                    </td>
                </tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err4_2{$this->popup_id}\">Street: </td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_area(
        					"name=location_street",
        					"value=" . stripslashes( $this->current_location['location_street'] ),
        					"rows=3",
        					"cols=35",
        					( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL )
        				) . "
        			</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err4_3{$this->popup_id}\">City: *</td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_box(
        					"name=location_city",
        					"value=" . stripslashes( $this->current_location['location_city'] ),
        					"size=30",
        					"maxlength=128",
        					( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL)
        				) . "
        			</td>
				</tr>
                <tr>
                    <td style=\"text-align:right;background-color:#ffffff;\" id=\"err4_4{$this->popup_id}\">State: *</td>
                    <td style=\"background-color:#ffffff;\">
                        <input type=\"hidden\"
                            name=\"location_stateSelect_default\"
                            id=\"location_stateSelect_default\"
                            value=\"{$this->current_location['location_state']}\" >
                        <div><select id=\"location_stateSelect\" name=\"location_state\" " . ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "disabled" : NULL) . " onChange=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'load_tax_selection', 'customer_hash={$this->customer_hash}', 'popup_id={$this->popup_id}', 'location_hash={$this->location_hash}', 'location=1', 'state=' + this.options[this.selectedIndex].value, 'country=' + \$F('location_country'));\"></select></div>
                    </td>
                </tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err4_5{$this->popup_id}\">Zip: </td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_box(
        					"name=location_zip",
        					"value={$this->current_location['location_zip']}",
        					"size=7",
        					"maxlength=10",
        					( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL)
        				) . "
        			</td>
				</tr>
                <tr>
                    <td style=\"text-align:right;background-color:#ffffff;\" id=\"err4_5a{$this->popup_id}\">Country: *</td>
                    <td style=\"background-color:#ffffff;\">
                        <input type=\"hidden\"
                            name=\"location_countrySelect_default\"
                            id=\"location_countrySelect_default\"
                            value=\"" .
                            ( $this->current_location['location_country'] ?
                                $this->current_location['location_country'] :
                                ( defined('MY_COMPANY_COUNTRY') ?
                                    MY_COMPANY_COUNTRY : 'US'
                                )
                            ) . "\">
                        <div><select id=\"location_countrySelect\" name=\"location_country\" " . ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "disabled" : NULL ) . " onchange=\"updateState(this.id);if(this.value=='US'){\$('location_tax_rules').setStyle({'display':'block'});agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'load_tax_selection', 'customer_hash={$this->customer_hash}', 'popup_id={$this->popup_id}', 'location_hash={$this->location_hash}', 'location=1', 'state=' + \$F('location_state'), 'country=' + this.options[this.selectedIndex].value);}else{\$('location_tax_rules').setStyle({'display':'none'});\$('locationtax_selection_table{$this->popup_id}').innerHTML=''}\"></select></div>
                    </td>
                </tr>";

                if ( $this->cust->class_fields['location'] ) {

                    $fields =& $this->cust->class_fields['location'];
                    for ( $i = 0; $i < count($fields); $i++ ) {

                        $tbl .= "
                        <tr>
                            <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err_custom{$fields[$i]['obj_id']}\">{$fields[$i]['field_name']}: " . ( $fields[$i]['required'] ? "*" : NULL ) . "</td>
                            <td style=\"background-color:#ffffff;\">" .
                                $this->cust->build_input(
                                    $fields[$i],
                                    $this->current_location[ $fields[$i]['col_name'] ]
                                ) . "
                            </td>
                        </tr>";
                    }
                }

                $tbl .= "
                <tr id=\"location_tax_rules\" style=\"display:" . ( $this->current_customer['tax_exempt_id'] || $this->current_location['location_country'] != 'US' ? "none" : "block" ) . "\">
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\">
                        Location Sales Tax:
                        <div style=\"margin-top:10px;font-style:italic;\">
                            Lock Tax Rules: " .
                            $this->form->checkbox(
                                "name=location_tax_lock",
                                "value=1",
                                "title=By locking tax rules, users will not have the ability to change tax rules during proposal finalization.",
                                ( $this->current_location['location_tax_lock'] ? "checked" : NULL)
                            ) . "
                        </div>
                    </td>
                    <td style=\"background-color:#ffffff;\" id=\"locationtax_selection_table{$this->popup_id}\">" .
                        $this->load_tax_selection(
                            1,
                            array(
                                'customer_hash'  =>  $this->customer_hash,
                                'location_hash'  =>  $this->location_hash,
                                'location'       =>  1,
                                'country'        =>  $this->current_location['location_country'],
                                'state'          =>  $this->current_location['location_state']
                            )
                        ) . "
                    </td>
                </tr>" .
                ( $this->customer_hash ? "
					<tr>
						<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
							<div style=\"float:right;\">" .
								( $this->location_hash && $this->p->ck(get_class($this), 'E', 'locations') ?
	    							$this->form->hidden( array(
	        							'location_hash' => $this->location_hash
	    							) ) .
	    							"Update Location" :
	    							( $this->p->ck(get_class($this), 'A', 'locations') ?
	        							"Add Location" : NULL
	        						)
	        					) . "
								&nbsp;&nbsp;" .
	        					( ( $this->location_hash && $this->p->ck(get_class($this), 'E', 'locations') ) || ( ! $this->location_hash && $this->p->ck(get_class($this), 'A', 'locations') ) ?
	            					$this->form->button(
	                					"value=+",
	                					"onClick=submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=AddLocationLine');"
	                				) : NULL
	                			) .
	                			"&nbsp;&nbsp;" .
								( $this->location_hash && $this->p->ck(get_class($this), 'D', 'locations') ?
	    							"Delete Location" .
    								"&nbsp;&nbsp;" .
	    							$this->form->button(
	        							"value=X",
	        							"onClick=if( confirm('Are you sure you want to delete this location? This action CANNOT be undone!')){submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=AddLocationLine', 'delete=true');}",
	        							( $this->current_location['rm_lock'] ? "disabled title=This location was found elsewhere and cannot be deleted." : NULL )
	        						) : NULL
	        					) . "
							</div>" .
	        				( $this->current_customer['customer_hash'] && $this->total_locations && $this->p->ck(get_class($this), 'A', 'locations') ? "
	    						<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'location_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->current_customer['customer_hash']}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>" : NULL
	    					) . "
						</td>
					</tr>" : NULL
                )."
			</table>";

		} elseif ( $this->customer_hash ) {

			$total = $this->total_locations;
			$num_pages = ceil($this->total_locations / SUB_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ( $p - 1 );
			$end = $start_from + SUB_PAGNATION_NUM;
			if ( $end > $this->total_locations )
				$end = $this->total_locations;

			$order_by_ops = array(
    			"location_name"		    =>	"t1.location_name",
			    "location_account_no"   =>  "t1.location_account_no",
				"location_address"	    =>  "t1.location_state, t1.location_city"
			);

			$_total = $this->fetch_locations(
    			$start_from,
    			$order_by_ops[ ( $order ? $order : "location_name" ) ],
    			( $order_dir ? $order_dir : "ASC" )
    		);

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"3\">" .
	                    			( $this->total_locations ?
										"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" .
		                        			paginate_jscript(
		                            			$num_pages,
		                            			$p,
		                            			'sf_loadcontent',
		                            			'cf_loadcontent',
		                            			'customers',
		                            			'location_info_form',
		                            			$order,
		                            			$order_dir,
		                            			"'otf=1', 'customer_hash={$this->current_customer['customer_hash']}', 'popup_id={$this->popup_id}'"
		                            		) . "
	                            		</div>
										Showing " . ( $start_from + 1 ) . " - " .
		                        		( $start_from + SUB_PAGNATION_NUM > $this->total_locations ?
		                            		$this->total_locations : $start_from + SUB_PAGNATION_NUM
		                            	) . " of {$this->total_locations} Locations for " . stripslashes($this->current_customer['customer_name']) : NULL
		                            ) . "
									<div style=\"padding-left:15px;font-weight:normal;margin-top:5px;\">
	    								[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'location_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'location_action=addnew', 'customer_hash={$this->current_customer['customer_hash']}');\" class=\"link_standard\">Add New Location</a></small>]
	    							</div>
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'location_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->current_customer['customer_hash']}', 'p=$p', 'order=location_name', 'order_dir=".($order == "location_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Location Name</a>" .
									( $order == 'location_name' ?
										"&nbsp;<img src=\"images/" . ($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png") . "\">" : NULL
									) . "
								</td>
                                <td >
                                    <a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'location_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->current_customer['customer_hash']}', 'p=$p', 'order=location_account_no', 'order_dir=".($order == "location_account_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Account No.</a>" .
                                    ( $order == 'location_account_no' ?
                                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                    ) . "
                                </td>
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'location_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->current_customer['customer_hash']}', 'p=$p', 'order=location_address', 'order_dir=".($order == "location_address" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Location Address</a>" .
									( $order == 'location_address' ?
										"&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
									) . "
								</td>
							</tr>";

							$border = "style=\"border-bottom:1px solid #cccccc;\"";
							for ( $i = 0; $i < $_total; $i++ ) {

								if ( $i >= $_total - 1 )
    								unset($border);

								$tbl .= "
								<tr " . ( $this->p->ck(get_class($this), 'V', 'locations') ? "onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'location_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'location_action=addnew', 'customer_hash={$this->current_customer['customer_hash']}', 'location_hash={$this->customer_locations[$i]['location_hash']}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
									<td $border>" .
    								( strlen($this->customer_locations[$i]['location_name']) > 34 ?
        								stripslashes( substr($this->customer_locations[$i]['location_name'], 0, 34) ) . "..." : stripslashes( $this->customer_locations[$i]['location_name'] )
        							) . "
        							</td>
                                    <td $border>" .
                                    ( $this->customer_locations[$i]['location_account_no'] ?
                                        stripslashes($this->customer_locations[$i]['location_account_no']) : "&nbsp;"
                                    ) . "
                                    </td>
									<td $border>" . stripslashes($this->customer_locations[$i]['location_city']) . ", {$this->customer_locations[$i]['location_state']}</td>
								</tr>";
							}

							if ( ! $this->total_locations )
                                $tbl .= "
                                <tr>
                                    <td colspan=\"3\" class=\"smallfont\">You have no locations listed under this customer.</td>
                                </tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";
		}

		if ( $this->ajax_vars['otf'] )
			$this->content['html']["tcontent4{$this->popup_id}"] = $tbl;
		else
			return $tbl;
	}

	function contact_info_form() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

        if ( $this->ajax_vars['popup_id'] )
            $this->popup_id = $this->ajax_vars['popup_id'];

        if ( $this->ajax_vars['customer_hash'] && ! $this->customer_hash ) {

            if ( ! $this->fetch_master_record($this->ajax_vars['customer_hash']) ) {

                return $this->__trigger_error("A system error was encountered when trying to lookup customer record for contact update. Please re-load customer window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
            }
        }

	    $action = $this->ajax_vars['action'];

        if ( $this->ajax_vars['contact_hash'] ) {

            if ( ! $this->fetch_contact_record($this->ajax_vars['contact_hash'], 1) ) {

                return $this->__trigger_error("A system error was encountered when trying to lookup vendor contact for database update. Please re-load vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
            }
        }

		list($location_hash, $location_name) = $this->fetch_location_list($this->customer_hash);

		if ( ! $this->customer_hash || $action == 'addnew' || $this->ajax_vars['parent_win_open'] == 'add_from_proposal' ) {

            $this->cust->fetch_class_fields('contact');

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;width:35%;\" id=\"err3_1{$this->popup_id}\">Contact Name: *</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_name", "value=".$this->current_contact['contact_name'], "size=35", "maxlength=255", ($valid && !$this->p->ck(get_class($this), 'E', 'contacts') ? "readonly" : NULL))."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_2{$this->popup_id}\">Title:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_title", "value=".$this->current_contact['contact_title'], "size=20", "maxlength=128", ($valid && !$this->p->ck(get_class($this), 'E', 'contacts') ? "readonly" : NULL))."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_3{$this->popup_id}\">Phone:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_phone", "value=".$this->current_contact['contact_phone1'], "size=18", "maxlength=32", ($valid && !$this->p->ck(get_class($this), 'E', 'contacts') ? "readonly" : NULL))."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_4{$this->popup_id}\">Phone 2:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_phone2", "value=".$this->current_contact['contact_phone2'], "size=18", "maxlength=32", ($valid && !$this->p->ck(get_class($this), 'E', 'contacts') ? "readonly" : NULL))."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_5{$this->popup_id}\">Mobile:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_mobile", "value=".$this->current_contact['contact_mobile'], "size=18", "maxlength=32", ($valid && !$this->p->ck(get_class($this), 'E', 'contacts') ? "readonly" : NULL))."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_6{$this->popup_id}\">Fax:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_fax", "value=".$this->current_contact['contact_fax'], "size=18", "maxlength=32", ($valid && !$this->p->ck(get_class($this), 'E', 'contacts') ? "readonly" : NULL))."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_7{$this->popup_id}\">Email:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_email", "value=".$this->current_contact['contact_email'], "size=25", "maxlength=128", ($valid && !$this->p->ck(get_class($this), 'E', 'contacts') ? "readonly" : NULL))."</td>
				</tr>".($location_hash ? "
				<tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\">Is this contact location specific?</td>
                    <td style=\"background-color:#ffffff;\">
                        ".$this->form->select("location_list[]",
			                                   $location_name,
			                                   $this->fetch_location_contacts($this->current_contact['contact_hash']),
			                                   $location_hash,
			                                   "multiple",
			                                   "blank=1",
			                                   "rows=4",
			                                   "cols=45")."
                    </td>
				</tr>" : NULL);

                if ($this->cust->class_fields['contact']) {
                    $fields =& $this->cust->class_fields['contact'];
                    for ($i = 0; $i < count($fields); $i++)
                        $tbl .= "
                        <tr>
                            <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err_custom".$fields[$i]['obj_id']."\">".$fields[$i]['field_name'].": ".($fields[$i]['required'] ? "*" : NULL)."</td>
                            <td style=\"background-color:#ffffff;\">".$this->cust->build_input($fields[$i],
                                                                                               $this->current_contact[$fields[$i]['col_name']])."
                            </td>
                        </tr>";

                }

				if ( $this->customer_hash ) {

				    $tbl .= "
					<tr>
						<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
							<div style=\"float:right;\">" .
								( ( $this->current_contact['contact_hash'] && $this->p->ck(get_class($this), 'E', 'contacts') ) ?
	    							$this->form->hidden( array(
	        							'contact_hash' => $this->current_contact['contact_hash']
	    							) ) . "Update Contact" :
	    							( ! $this->current_contact['contact_hash'] && $this->p->ck(get_class($this), 'A', 'contacts') ?
	        							"Add Contact" : NULL
	    							)
	    						) . "
								&nbsp;&nbsp;" .
	    						( ( $this->current_contact['contact_hash'] && $this->p->ck(get_class($this), 'E', 'contacts') ) || ( ! $this->current_contact['contact_hash'] && $this->p->ck(get_class($this), 'A', 'contacts') ) ?
    	    						$this->form->button(
        	    						"value=+",
        	    						"onClick=if (\$F('contact_name')) {submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=AddContactLine');}else{alert('To add a contact for this customer you must include at least a name.')}"
    	    						) : NULL
    	    					) .
    							( $this->p->ck(get_class($this), 'D', 'contacts') && $this->current_contact['contact_hash'] ?
        							"&nbsp;&nbsp;Delete Contact&nbsp;&nbsp;" .
        							$this->form->button(
            							"value=X",
            							"onClick=if (confirm('Are you sure you want to delete this contact? This action CANNOT be undone!')){submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'actionBtn=AddContactLine', 'delete=true', 'popup_id={$this->popup_id}');}"
        							) : NULL
        						) . "
							</div>
    						<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'contact_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'customer_hash={$this->customer_hash}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>
						</td>
					</tr>";
				}

            $tbl .= "
			</table>";

		} elseif ( $this->customer_hash ) {

			$total = $this->total_contacts;
			$num_pages = ceil($this->total_contacts / SUB_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ($p - 1);
			$end = $start_from + SUB_PAGNATION_NUM;
			if ( $end > $this->total_contacts )
				$end = $this->total_contacts;

			$order_by_ops = array(
    			"contact_name"	=>	"t1.contact_name"
			);

			$_total = $this->fetch_contacts( array(
    			'start_from'	=>	$start_from,
    			'order_by'		=>	$order_by_ops[ $order ],
    			'order_dir'		=>	$order_dir
    		) );

			$tbl = "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">" .
	                                ( $this->total_contacts ?
										"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" .
	                                    paginate_jscript(
	                                        $num_pages,
	                                        $p,
	                                        'sf_loadcontent',
	                                        'cf_loadcontent',
	                                        'customers',
	                                        'contact_info_form',
	                                        $order,
	                                        $order_dir,
	                                        "'otf=1', 'customer_hash={$this->current_customer['customer_hash']}', 'popup_id={$this->popup_id}'"
	                                    ) . "
	                                    </div>
										Showing " . ( $start_from + 1 ) . " - " .
	                                    ( $start_from + SUB_PAGNATION_NUM > $this->total_contacts ?
	                                        $this->total_contacts : $start_from + SUB_PAGNATION_NUM
	                                    ) . " of {$this->total_contacts} Contacts for " . stripslashes($this->current_customer['customer_name']) : NULL
	                                ) .
									( $this->p->ck(get_class($this), 'A', 'contacts') ?
										"<div style=\"padding-left:15px;font-weight:normal;margin-top:5px;\">
    										[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'contact_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'action=addnew', 'customer_hash={$this->customer_hash}');\" class=\"link_standard\">Add New Contact</a></small>]
        								</div>" : NULL
        							) . "
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'contact_info_form', 'popup_id={$this->popup_id}', 'otf=1', 'customer_hash={$this->current_customer['customer_hash']}', 'p=$p', 'order=contact_name', 'order_dir=".($order == "contact_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Name</a>" .
									( $order == 'contact_name' ?
										"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
									) . "
								</td>
								<td >Title</td>
								<td >Phone</td>
								<td >Email</td>
							</tr>";

							$border = "style=\"border-bottom:1px solid #cccccc;\"";
							for ( $i = 0; $i < $_total; $i++ ) {

								if ( $i >= $_total - 1)
    								unset($border);

								$tbl .= "
								<tr " . ( $this->p->ck(get_class($this), 'V', 'contacts') ? "onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'contact_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'action=addnew', 'customer_hash={$this->customer_hash}', 'contact_hash={$this->customer_contacts[$i]['contact_hash']}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
									<td $border>" . stripslashes($this->customer_contacts[$i]['contact_name']) . "</td>
									<td $border>" .
									( $this->customer_contacts[$i]['contact_title'] ?
    									stripslashes($this->customer_contacts[$i]['contact_title']) : "&nbsp;"
									) . "
									</td>
									<td $border>" .
									( $this->customer_contacts[$i]['contact_phone1'] ?
    									$this->customer_contacts[$i]['contact_phone1'] : "&nbsp;"
    								) . "
    								</td>
									<td $border>" .
									( $this->customer_contacts[$i]['contact_email'] ?
    									$this->customer_contacts[$i]['contact_email'] : "&nbsp;"
									) . "
									</td>
								</tr>";
							}

                            if ( ! $this->total_contacts )
                                $tbl .= "
                                <tr>
                                    <td colspan=\"4\" class=\"smallfont\">You have no contacts listed under this customer.</td>
                                </tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";
		}

		if ( $this->ajax_vars['otf'] )
			$this->content['html']["tcontent3{$this->popup_id}"] = $tbl;
		else
			return $tbl;
	}

	function amount_tracker() {

		$invoice_hash = $_POST['invoice_hash'];
		$pay_amount = $_POST['pay_amount'];
		$total_due = $_POST['total_due'];
		$from_check = $_POST['from_check'];
		$amount_received = $remaining_amt = preg_replace('/[^-.0-9]/', "", $_POST['amount_received']);
		if ( bccomp($amount_received, 0, 2) == -1 )
			$amount_received = $remaining_amt = 0;

		for ( $i = 0; $i < count($invoice_hash); $i++ ) {

			if ( $invoice_hash[$i] )
				$invoices[] = $invoice_hash[$i];
		}

		$amount_to_pay = 0;
		$invoice = new customer_invoice($this->current_hash);

		for ( $i = 0; $i < count($invoices); $i++ ) {

			$pay_amount[ $invoices[$i] ] = preg_replace('/[^-.0-9]/', "", $pay_amount[ $invoices[$i] ]);

			if ( $invoice->fetch_invoice_record($invoices[$i]) ) {

				if ( ( $from_check && bccomp($pay_amount[ $invoice->invoice_hash ], $invoice->current_invoice['balance'], 2) == 1 ) || ! $pay_amount[ $invoice->invoice_hash ] ) {

					$pay_amount[ $invoice->invoice_hash ] = $invoice->current_invoice['balance'];
					$this->content['value']["pay_amount_{$invoice->invoice_hash}"] = number_format($invoice->current_invoice['balance'], 2);
				}
			}

			if ( bccomp($pay_amount[ $invoices[$i] ], $remaining_amt, 2) == 1 ) {

				$pay_amount[ $invoices[$i] ] = $remaining_amt;
				$this->content['value']["pay_amount_{$invoices[$i]}"] = number_format($pay_amount[ $invoices[$i] ], 2);
			}

			$remaining_amt = bcsub($remaining_amt, $pay_amount[ $invoices[$i] ], 2);
			$amount_to_pay = bcadd($amount_to_pay, $pay_amount[ $invoices[$i] ], 2);

			if ( $invoice->invoice_hash ) {

				$overpay_invoice_hash[] = $invoice->invoice_hash;
				$overpay_invoice_no[] = $invoice->current_invoice['invoice_no'];
			}
		}

		if ( count($invoices) && bccomp($remaining_amt, 0, 2) == 1 ) {

			$this->content['html']["overpayment_amount{$this->popup_id}"] = "\$" . number_format($remaining_amt, 2);
			$this->content['jscript'][] = "toggle_display('overpayment_action_holder{$this->popup_id}', 'block');";

			if ( count($invoices) > 1 )
				$this->content['html']["overpayment_invoice_tag{$this->popup_id}"] = "Which invoice should absorb the increase? <div style=\"margin-left:15px;padding-top:5px;\">".$this->form->select("overpayment_invoice", $overpay_invoice_no, NULL, $overpay_invoice_hash)."</div>";
			else
				$this->content['html']["overpayment_invoice_tag{$this->popup_id}"] = $this->form->hidden(array("overpayment_invoice" => $invoices[0]));
		} else
			$this->content['jscript'][] = "toggle_display('overpayment_action_holder{$this->popup_id}', 'none');";

		$this->content['html']["total_to_pay_holder{$this->popup_id}"] = "\$" . number_format($amount_to_pay, 2);
		$this->content['action'] = 'continue';

		return;
	}

	function fetch_unapplied_deposits($customer_hash) {
		$result = $this->db->query("SELECT t1.invoice_hash , t1.invoice_date , t1.amount , t1.balance , t1.currency , t1.exchange_rate
									FROM customer_invoice t1
									WHERE t1.customer_hash = '$customer_hash' AND t1.type = 'D' AND t1.proposal_hash = '' AND t1.balance > 0 AND t1.deleted = 0");
		while ($row = $this->db->fetch_assoc($result))
			$payment[] = $row;

		return $payment;
	}

	function receive_payment($customer_hash=NULL) {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		$this->popup_id = $this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );
		$this->content['popup_controls']['popup_title'] = "Receive Customer Payment";

		$system_config = new system_config;
		list($prod_hash, $prod_name) = $system_config->company_products();

		$checking_account_hash = $checking_account_name = array();

		$result = $this->db->query("SELECT
										t1.account_hash,
										t1.account_no,
										t1.account_name
									FROM accounts t1
									WHERE t1.account_type = 'CA' AND t1.checking = 1
									ORDER BY t1.account_no, t1.account_name ASC");
		while ( $row = $this->db->fetch_assoc($result) ) {

			array_push($checking_account_hash, $row['account_hash']);
			array_push($checking_account_name, ( $row['account_no'] ? "{$row['account_no']} " : NULL ) . $row['account_name']);
		}

		if ( defined('DEFAULT_CASH_ACCT') ) {

			if ( ! $checking_account_hash || ! in_array(DEFAULT_CASH_ACCT, $checking_account_hash) ) {

				$accounts = new accounting;
				if ( ! $accounts->fetch_account_record(DEFAULT_CASH_ACCT) )
					return $this->__trigger_error("Unable to lookup default checking account. Please confirm that a default checking account has been defined. <!-- Tried fetching account [ " . DEFAULT_CASH_ACCT . " ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

				array_push($checking_account_hash, DEFAULT_CASH_ACCT);
				array_push($checking_account_name, ( $accounts->current_account['account_no'] ? "{$accounts->current_account['account_no']} " : NULL ) . $accounts->current_account['account_name']);
			}
		}

		$tbl =
		$this->form->form_tag().
		$this->form->hidden( array(
			"popup_id" 	=> $this->popup_id,
			"p" 		=> $p,
			"order" 	=> $order,
			"order_dir" => $order_dir,
			"is_vendor"	=> ''
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\" >
				<tr>
					<td style=\"padding:0;\">
						 <div style=\"background-color:#ffffff;height:100%;\">
							<div style=\"margin:15px 25px;\">
								<h3 style=\"margin-bottom:5px;color:#00477f;\">Receive Customer Payments</h3>
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
									<tr>
										<td class=\"smallfont\" colspan=\"2\" style=\"padding-left:25px;vertical-align:top;\" id=\"err1{$this->popup_id}\">
											Customer:
											<div style=\"padding-top:5px;\">
												".$this->form->text_box("name=paying_customer",
																		"value=",
																		"autocomplete=off",
																		"size=30",
																		"onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'paying_customer', 'customer_hash', 1);}",
                                                                        "onKeyUp=if(ka==false && this.value){key_call('proposals', 'paying_customer', 'customer_hash', 1);}",
                                                                		"onBlur=key_clear();",
																		"onKeyDown=clear_values('customer_hash');").
												$this->form->hidden(array("customer_hash" => ''))."
											</div>
										</td>
										<td class=\"smallfont\" style=\"vertical-align:top;\">
											Date Received:
											<div style=\"padding-top:5px;\" id=\"payment_date_holder{$this->popup_id}\"></div>";
											$jscript[] = "setTimeout('DateInput(\'payment_date\', \'true\', \'YYYY-MM-DD\', \'".date("Y-m-d")."\', 1, \'payment_date_holder{$this->popup_id}\')', 45)";
										$tbl .= "
										</td>
										<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:top;padding-right:25px;text-align:right;\">
                                            <div id=\"balance_parent_{$this->popup_id}\" style=\"display:none;\">
												Outstanding Balance:
												<div style=\"padding-top:5px;\" id=\"balance_holder{$this->popup_id}\"></div>
											</div>
										</td>
									</tr>
									<tr>
										<td class=\"smallfont\" style=\"padding-left:25px;vertical-align:top;\">
											Receipt Amount:
											<div style=\"padding-top:5px;\">" .
												$this->form->text_box(
													"name=amount_received",
													"value=",
													"style=text-align:right;",
													"size=12",
													"onFocus=select();",
													"onBlur=this.value=formatCurrency(this.value);submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=amount_tracker', 'from_check=1');"
												) . "
											</div>
										</td>
										<td class=\"smallfont\" style=\"padding-left:10px;vertical-align:top;\">
                                            <div id=\"currency_holder_{$this->popup_id}\" style=\"display:none;\">
                                                Currency:
                                                <div style=\"padding-top:5px;\" id=\"currency_select_{$this->popup_id}\"></div>
                                            </div>
										</td>
										<td class=\"smallfont\" style=\"vertical-align:top;\">
											Payment Method:
											<div style=\"padding-top:5px;\">
												".$this->form->select("payment_method", array("Check", "Cash", "Credit Card"), '', array('CK', 'CA', 'CC'), "blank=1")."
											</div>
										</td>
										<td class=\"smallfont\" style=\"vertical-align:top;\">
											Check / Reference No:
											<div style=\"padding-top:5px;\">
												".$this->form->text_box("name=comments", "value=", "size=25")."
											</div>
										</td>
									</tr>
									<tr>
										<td id=\"inner_tbl_holder{$this->popup_id}\" colspan=\"4\" style=\"padding-top:15px;\">";

									if ( $customer_hash ) {

										$invoice = new customer_invoice($this->current_hash);
										$invoice->set_values( array(
    										"customer_hash"   =>  $customer_hash
										) );

										$_total = $invoice->fetch_open_invoices();

										$inner_tbl = "
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:100%;\" >
											<tr style=\"background-color:#cccccc;\">
												<td class=\"thead\" style=\"font-weight:normal;width:10px;\">" .
    												$this->form->checkbox(
        												"name=check_all",
                            							"value=1",
                            							"onClick=checkall(document.getElementsByName('invoice_hash[]'), this.checked);submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=amount_tracker', 'from_check=1');"
    												) . "
    											</td>
												<td class=\"thead\" style=\"font-weight:normal;\">Invoice No.</td>
												<td class=\"thead\" style=\"font-weight:normal;\">Invoice Date</td>
												<td class=\"thead\" style=\"font-weight:normal;text-align:right;\" >Original Amount</td>
												<td class=\"thead\" style=\"font-weight:normal;text-align:right;\" >Amount Due</td>
												<td class=\"thead\" style=\"font-weight:normal;text-align:right;\" >Payment</td>
											</tr>
											<tr>
												<td colspan=\"6\" style=\"background-color:#cccccc;\"></td>
											</tr>";

										$total_due = $total_orig = 0;
										$border = "border-bottom:1px solid #cccccc;";

										for ( $i = 0; $i < $_total; $i++ ) {

                                            $total_due = bcadd($total_due, $invoice->invoice_info[$i]['balance'], 2);
                                            $total_orig = bcadd($total_orig, $invoice->invoice_info[$i]['amount'], 2);

                                            if ( $i >= $_total - 1 )
	                                            unset($border);

											$inner_tbl .= "
											<tr >
												<td style=\"cursor:auto;$border\">" .
													$this->form->checkbox(
    													"name=invoice_hash[]",
														"value={$invoice->invoice_info[$i]['invoice_hash']}",
														"onClick=if(this.checked==false){\$('pay_amount_{$invoice->invoice_info[$i]['invoice_hash']}').value='';}submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=amount_tracker', 'from_check=1');"
													) . "
												</td>
												<td class=\"smallfont\" style=\"vertical-align:bottom;text-align:left;$border\" id=\"err_{$invoice->invoice_info[$i]['invoice_hash']}\">{$invoice->invoice_info[$i]['invoice_no']}</td>
												<td class=\"smallfont\" style=\"vertical-align:bottom;text-align:left;$border\">" . date(DATE_FORMAT, strtotime($invoice->invoice_info[$i]['invoice_date'])) . "</td>
												<td class=\"smallfont\" style=\"vertical-align:bottom;text-align:right;$border\">\$" . number_format($invoice->invoice_info[$i]['amount'], 2) . "</td>
												<td class=\"smallfont\" style=\"vertical-align:bottom;text-align:right;$border\">\$" . number_format($invoice->invoice_info[$i]['balance'], 2) . "</td>
												<td class=\"smallfont\" style=\"vertical-align:bottom;text-align:right;$border\">" .
													$this->form->text_box(
    													"name=pay_amount[{$invoice->invoice_info[$i]['invoice_hash']}]",
														"id=pay_amount_{$invoice->invoice_info[$i]['invoice_hash']}",
														"value=" . ( bccomp($amount_to_pay, 0, 2) == 1 ? number_format($amount_to_pay, 2) : '' ),
														"size=10",
														"style=text-align:right;",
														"onBlur=if(this.value<0){this.value=0;}submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=amount_tracker', 'from_check=1');if(this.value){this.value=formatCurrency(this.value);}",
														"onFocus=select();"
													) . "
												</td>
											</tr>";

											$overpay_invoice_hash[] = $invoice->invoice_info[$i]['invoice_hash'];
											$overpay_invoice_no[] = $invoice->invoice_info[$i]['invoice_no'];
										}

										/*
										if ( count($invoice->invoice_info) ) {

											$inner_tbl .= "
											<tr >
												<td style=\"cursor:auto;\">" .
													$this->form->checkbox(
    													"name=invoice_hash[]",
														"value=unapplied",
														"onClick=if(this.checked==false){\$('pay_amount_unapplied').value='';}submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=amount_tracker', 'from_check=1');"
													) . "
												</td>
												<td class=\"smallfont\" colspan=\"4\"><i>Receive as an unapplied deposit</i></td>
												<td style=\"vertical-align:bottom;text-align:right;\" class=\"smallfont\">" .
													$this->form->text_box(
    													"name=pay_amount[unapplied]",
														"id=pay_amount_unapplied",
														"value=" . ( bccomp($amount_to_pay, 0, 2) == 1 ? number_format($amount_to_pay, 2) : '' ),
														"size=10",
														"style=text-align:right;",
														"onBlur=if(this.value<0){this.value=0;}submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=amount_tracker');if(this.value){this.value=formatCurrency(this.value);}",
														"onFocus=select();"
													) . "
												</td>
											</tr>
											<tr style=\"background-color:#efefef;\">
												<td class=\"smallfont\" style=\"border-top:1px solid #cccccc;\" colspan=\"2\">&nbsp;</td>
												<td class=\"smallfont\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;\">Totals:</td>
												<td class=\"smallfont\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;text-align:right;\">\$" . number_format($total_orig, 2) . "</td>
												<td class=\"smallfont\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;text-align:right;\" >
													\$" . number_format($total_due, 2) .
													$this->form->hidden( array(
    													"total_due" => $total_due
													) ) . "
												</td>
												<td class=\"smallfont\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;text-align:right;\" id=\"total_to_pay_holder{$this->popup_id}\">\$" . number_format($total_paid, 2) . "</td>
											</tr>";

										} else {
										*/
										if ( ! $invoice->invoice_info ) {

											$inner_tbl .= "
											<tr >
												<td colspan=\"6\" class=\"smallfont\">
													We found no open invoices for this customer. If you are recording a deposit please use the
													'Receive Deposit' tool under the receivables tab within your proposal.
													<br /><br />" .
													$this->form->checkbox(
    													"name=unapplied",
    													"value=1"
													) . "&nbsp;To record this payment as an unapplied deposit, check the box and click Save below.
												</td>
											</tr>";
										}

										$inner_tbl .= "
										</table>";
									}

									$tbl .= "
										</td>
									</tr>
								</table>
								<table style=\"width:100%;\" >
									<tr>
										<td style=\"vertical-align:top:\">
											<div style=\"padding-top:5px;display:none;\" id=\"overpayment_action_holder{$this->popup_id}\">
												<strong>
													Overpayment of <span id=\"overpayment_amount{$this->popup_id}\"></span>.
													<br />What should we do with the remaining amount?
												</strong>
												<div style=\"padding:10px 5px 5px 5px;\">".$this->form->radio("name=overpayment_action", "value=1", "onClick=if(this.checked){toggle_display('overpay_prod{$this->popup_id}', 'none');}")."&nbsp;Save it as unapplied to be used later.</div>
												<div style=\"padding:5px;\">".$this->form->radio("name=overpayment_action", "value=3", "onClick=if(this.checked){toggle_display('overpay_prod{$this->popup_id}', 'none');}")."&nbsp;Refund the overpayment to the customer.</div>
												<!--
												<div style=\"padding:5px;\">
													".$this->form->radio("name=overpayment_action", "value=2", "onClick=if(this.checked){toggle_display('overpay_prod{$this->popup_id}', 'block');}")."&nbsp;Adjust the invoice to absorb the overpayment as profit.
													<div style=\"margin-left:30px;padding-top:10px;display:none;\" id=\"overpay_prod{$this->popup_id}\">
														Which product should absorb the increase?
														<div style=\"margin-left:15px;padding-top:5px;\">
															".$this->form->select("overpayment_product", $prod_name, NULL, $prod_hash)."
														</div>
														<div style=\"padding-top:5px;\">
															<span id=\"overpayment_invoice_tag{$this->popup_id}\"></span>
														</div>
													<div>
												</div>
												-->
											</div>
										</td>
										<td style=\"vertical-align:top;padding-top:20px;text-align:right;\">
											<div style=\"float:right;padding-right:25px;\">
												".$this->form->button("value=Save & Close", "id=rcv_btn", "onClick=submit_form(this.form, 'customers', 'exec_post', 'refresh_form', 'action=doit_receive_payment', 'final=1');this.disabled=1")."
													&nbsp;&nbsp;
													<div style=\"padding-top:10px;\">".($checking_account_hash ? "
														Account: ".
														$this->form->select("payment_account_hash",
																			$checking_account_name,
																			(defined('DEFAULT_CASH_ACCT') ? DEFAULT_CASH_ACCT : ''),
																			$checking_account_hash,
																			"blank=1",
																			"style=width:175px;") : "<img src=\"images/alert.gif\" />&nbsp;&nbsp;You have no checking accounts defined!")."
													</div>
											</div>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		if ($customer_hash)
			return $inner_tbl;

		$this->content['focus'] = "paying_customer";
		$this->content["jscript"] = $jscript;
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function showall() {

		$this->unlock("_");
		$this->load_class_navigation();

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

		$this->popup_id = $this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : 'popup_win_list' );

		$this->customers($this->current_hash);

		$total_1 = $this->total;
		$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ( $end > $this->total )
			$end = $this->total;

		$order_by_ops = array(
            "cna"			=>	"customers.customer_name",
		    "ano"           =>  "customers.account_no",
			"clo"			=>	"customers.state , customers.city",
			"cbcur"			=>  "balance_current",
			"cbsched1"		=>  "balance_sched1",
			"cbsched2"		=>  "balance_sched2",
			"cbsched3"		=>  "balance_sched3",
			"balance_total"	=>	"balance_total"
		);

		$tbl = $this->fetch_customers(
    		$start_from,
    		$order_by_ops[ ( isset($order) && $order_by_ops[$order] ? $order : "cna" ) ],
    		$order_dir
    	);

		if ( $this->total != $total_1 ) {

			$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = MAIN_PAGNATION_NUM * ($p - 1);

			$end = $start_from + MAIN_PAGNATION_NUM;
			if ( $end > $this->total )
				$end = $this->total;

		}

		$tbl .=
		( $this->active_search ?
			"<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
			<div style=\"padding-top:8px;padding-left:25px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall');\">Show All</a>]</div>" : NULL
		) . "
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"padding:15;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"8\">" .
	                    		( $this->total ?
									"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" .
	                        		paginate_jscript(
	                            		$num_pages,
	                            		$p,
	                            		'sf_loadcontent',
	                            		'cf_loadcontent',
	                            		'customers',
	                            		'showall',
	                            		$order,
	                            		$order_dir,
	                            		"'active_search={$this->active_search}', 'popup_id={$this->popup_id}'"
	                            	) . "
	                            	</div>
									Showing " . ( $start_from + 1 ) . " - " .
	                            	( $start_from + MAIN_PAGNATION_NUM > $this->total ?
	                                	$this->total : $start_from + MAIN_PAGNATION_NUM
	                                ) . " of {$this->total} Customers." : NULL
                                ) . "
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
	                                ( $this->p->ck(get_class($this), 'A') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_customer', 'popup_id=main_popup', 'active_search={$this->active_search}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/new.gif\" title=\"Create a new customer\" border=\"0\" /></a>&nbsp;" : NULL
	                                ) .
                                    ( $this->p->ck(get_class($this), 'L') ?
    									"<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'search_customers', 'popup_id=main_popup', 'active_search={$this->active_search}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/search.gif\" title=\"Search customers\" border=\"0\" /></a>&nbsp;" : NULL
                                    ) .
                                    ( $this->p->ck(get_class($this), 'A', 'rcv_pmt') ?
    									"<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'receive_payment', 'popup_id=main_popup', 'active_search={$this->active_search}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/new_deposit.gif\" title=\"Receive customer payments\" border=\"0\" /></a>&nbsp;" : NULL
                                    ) . "&nbsp;
									<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('reports', 'sf_loadcontent', 'show_popup_window', 'print_it_xls', 'type=customers', 'popup_id=xls_customers', 'title=Customer List', 'active_search={$this->active_search}');\"><img src=\"images/excel.gif\" title=\"Export customer list into a spreadsheet.\" border=\"0\" /></a>
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=cna', 'order_dir=".($order == "cna" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search={$this->active_search}'" : NULL).", 'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Customer Name</a>" .
								( $order == 'cna' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
                            <td >
                                <a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=ano', 'order_dir=".($order == "ano" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search={$this->active_search}'" : NULL).", 'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Account No.</a>" .
                                ( $order == 'ano' ?
                                    "&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
                            </td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=clo', 'order_dir=".($order == "clo" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search={$this->active_search}'" : NULL).", 'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Location</a>" .
								( $order == 'clo' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"text-align:right;\">" .
								( $order == 'cbcur' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=cbcur', 'order_dir=".($order == "cbcur" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search={$this->active_search}'" : NULL).", 'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Current</a>
							</td>
							<td style=\"text-align:right;\" nowrap>" .
								( $order == 'cbsched1' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=cbsched1', 'order_dir=".($order == "cbsched1" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search={$this->active_search}'" : NULL).", 'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Past " . ACCOUNT_AGING_SCHED1 . "</a>
							</td>
							<td style=\"text-align:right;\" nowrap>" .
								( $order == 'cbsched2' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=cbsched2', 'order_dir=".($order == "cbsched2" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search={$this->active_search}'" : NULL).", 'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Past " . ACCOUNT_AGING_SCHED2 . "</a>
							</td>
							<td style=\"text-align:right;\" nowrap>" .
								( $order == 'cbsched3' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=cbsched3', 'order_dir=".($order == "cbsched3" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search={$this->active_search}'" : NULL).", 'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Past " . ACCOUNT_AGING_SCHED3 . "</a>
							</td>
							<td style=\"text-align:right;padding-right:10px;\">" .
								( $order == 'balance_total' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=balance_total', 'order_dir=".($order == "balance_total" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search={$this->active_search}'" : NULL).", 'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Total</a>
							</td>
						</tr>";

						$border = "border-bottom:1px solid #AAC8C8";
						for ( $i = 0; $i < ( $end - $start_from ); $i++ ) {

							$b++;
							if ( $this->p->ck(get_class($this), 'V') )
								$onClick = " onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_customer', 'customer_hash={$this->customer_info[$i]['customer_hash']}', 'popup_id={$this->popup_id}', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search={$this->active_search}');\"";

                            if ( $b >= ( $end - $start_from ) )
                                unset($border);

							$tbl .= "
							<tr " . ( $this->p->ck(get_class($this), 'V') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . " >
								<td $onClick " . ( $border ? "style=\"$border\"" : NULL ) . ">" . stripslashes($this->customer_info[$i]['customer_name']) . "&nbsp;</td>
								<td $onClick " . ( $border ? "style=\"$border\"" : NULL ) . ">" .
								( $this->customer_info[$i]['account_no'] ?
								    $this->customer_info[$i]['account_no'] : "&nbsp;"
								) . "
								</td>
								<td $onClick " . ( $border ? "style=\"$border\"" : NULL ) . ">" .
    								( $this->customer_info[$i]['city'] ?
        								stripslashes($this->customer_info[$i]['city']) . ", " : NULL
        							) . "{$this->customer_info[$i]['state']}&nbsp;
        						</td>
								<td $onClick style=\"text-align:right;$border;" . ( $this->customer_info[$i]['balance_current'] < 0 ? "color:red\"" : NULL)."\">" .
								( bccomp($this->customer_info[$i]['balance_current'], 0, 2) ?
									( bccomp($this->customer_info[$i]['balance_current'], 0, 2) == -1 ?
										"(\$" . number_format( bcmul($this->customer_info[$i]['balance_current'], -1, 2), 2) . ")" : "\$" . number_format($this->customer_info[$i]['balance_current'], 2)
									) : "&nbsp;"
								) . "
								</td>
								<td $onClick style=\"text-align:right;$border;" . ( $this->customer_info[$i]['balance_sched1'] < 0 ? "color:red;" : NULL)."\">" .
								( bccomp($this->customer_info[$i]['balance_sched1'], 0, 2) ?
	                                ( bccomp($this->customer_info[$i]['balance_sched1'], 0, 2) == -1 ?
	                                    "(\$" . number_format( bcmul($this->customer_info[$i]['balance_sched1'], -1, 2), 2) . ")" : "\$" . number_format($this->customer_info[$i]['balance_sched1'], 2)
	                                ) : "&nbsp;"
	                            ) . "
								<td $onClick style=\"text-align:right;$border;" . ( $this->customer_info[$i]['balance_sched2'] < 0 ? "color:red;" : NULL)."\">" .
								( bccomp($this->customer_info[$i]['balance_sched2'], 0, 2) ?
	                                ( bccomp($this->customer_info[$i]['balance_sched2'], 0, 2) == -1 ?
	                                    "(\$" . number_format( bcmul($this->customer_info[$i]['balance_sched2'], -1, 2), 2) . ")" : "\$" . number_format($this->customer_info[$i]['balance_sched2'], 2)
	                                ) : "&nbsp;"
	                            ) . "
								<td $onClick style=\"text-align:right;$border;" . ( $this->customer_info[$i]['balance_sched3'] < 0 ? "color:red;" : NULL)."\">" .
								( bccomp($this->customer_info[$i]['balance_sched3'], 0, 2) ?
	                                ( bccomp($this->customer_info[$i]['balance_sched3'], 0, 2) == -1 ?
	                                    "(\$" . number_format( bcmul($this->customer_info[$i]['balance_sched3'], -1, 2), 2) . ")" : "\$" . number_format($this->customer_info[$i]['balance_sched3'], 2)
	                                ) : "&nbsp;"
	                            ) . "
								<td $onClick style=\"text-align:right;font-weight:bold;$border;" . ( $this->customer_info[$i]['balance_total'] < 0 ? "color:red;" : NULL)."\">" .
								( bccomp($this->customer_info[$i]['balance_total'], 0, 2) ?
	                                ( bccomp($this->customer_info[$i]['balance_total'], 0, 2) == -1 ?
	                                    "(\$" . number_format( bcmul($this->customer_info[$i]['balance_total'], -1, 2), 2) . ")" : "\$" . number_format($this->customer_info[$i]['balance_total'], 2)
	                                ) : "&nbsp;"
	                            ) . "
							</tr>";
						}

						if ( ! $this->total )
							$tbl .= "
							<tr >
								<td colspan=\"8\">" .
    							( $this->active_search ?
									"<div style=\"padding-top:10px;font-weight:bold;\">Search returned empty result set</div>"
        							:
        							"You have no customers to display. " .
        							( $this->p->ck(get_class($this), 'A') ?
    									"<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_customer', 'popup_id=main_popup');\">Create a new customer now.</a>" : NULL
        							)
        						) . "
								</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		$this->content['html']['place_holder'] = $tbl;
	}

	function search_customers() {
		global $stateNames, $states, $country_codes, $country_names;

		$date = date("U") - 3600;
		$result = $this->db->query("DELETE FROM `search`
							  		WHERE `timestamp` <= '$date'");

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Search Customers";
		$this->content['focus'] = 'customer';

	    if ($this->ajax_vars['active_search']) {
            $result = $this->db->query("SELECT `search_str`
                                        FROM `search`
                                        WHERE `search_hash` = '".$this->ajax_vars['active_search']."'");
            $row = $this->db->fetch_assoc($result);
            $search_vars = permissions::load_search_vars($row['search_str']);
        }

	    if (defined('MULTI_CURRENCY')) {
            $sys = new system_config();
            $sys->fetch_currencies();

            for ($i = 0; $i < count($sys->currency); $i++) {
                $currency_out[] = $sys->currency[$i]['name'];
                $currency_in[] = $sys->currency[$i]['code'];
            }
        }

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array('popup_id' => $this->content['popup_controls']['popup_id'], 'test' => 1))."
		<div class=\"panel\" id=\"main_table\">
			<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
				<p id=\"feedback_message\"></p>
			</div>
			<div style=\"padding:15px 0 0 15px;font-weight:bold;\"></div>
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"padding:20px 45px;background-color:#ffffff;\">
                        <div style=\"font-weight:bold;padding-bottom:10px;\">
                            Choose your customer & location search criteria below.
                        </div>
                        <table>
                            <tr>
                                <td style=\"padding-left:15px;\">
                                    <fieldset>
                                        <legend>Customer Name</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
												".$this->form->text_box("name=customer",
																		"value=",
																		"autocomplete=off",
																		"size=30",
																		"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('customer_vendor', 'customer', 'customer_hash', 1);}",
                                                                        "onKeyUp=if(ka==false && this.value){key_call('customer_vendor', 'customer', 'customer_hash', 1);}",
																		"onBlur=key_clear();"
																		).
            									$this->form->hidden(array("customer_hash" => $last_input['customer_hash']))."
                                        </div>
                                    </fieldset>
                                </td>
                                <td style=\"padding-left:15px;\" ".(URL_HOST == 'gt.dealer-choice.com' ? "colspan=\"2\"" : NULL).">
                                    <fieldset>
                                        <legend>Account Number</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->text_box("name=account_no",
                                                                    "value=".$search_vars['account_no'],
                                                                    "size=".(URL_HOST == 'gt.dealer-choice.com' ? 25 : 15),
		                                                            "onFocus=this.select()",
                                                                    "maxlength=64")."
                                        </div>
                                    </fieldset>
                                </td>".(URL_HOST != 'gt.dealer-choice.com' ? "
                                <td style=\"padding-left:15px;vertical-align:top;\">
                                    <fieldset>
                                        <legend>Customer Number</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->text_box("name=customer_no",
                                                                    "value=".$search_vars['customer_no'],
                                                                    "size=15",
                                                                    "onFocus=this.select()",
                                                                    "maxlength=64")."
                                        </div>
                                    </fieldset>
                                </td>" : NULL)."
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;\" colspan=\"3\">
                                    <table>
                                        <tr>
                                            <td >
			                                    <fieldset>
			                                        <legend>Located In</legend>
			                                        <div style=\"padding:5px;padding-bottom:5px\">
			                                            ".$this->form->select("state",
			                                                                  $stateNames,
			                                                                  $search_vars['state'],
			                                                                  $states)."
			                                        </div>
			                                    </fieldset>
                                            </td>
                                            <td >
			                                    <fieldset>
			                                        <legend>Country</legend>
			                                        <div style=\"padding:5px;padding-bottom:5px\">
			                                            ".$this->form->select("country",
			                                                                  $country_names,
			                                                                  $search_vars['country'],
			                                                                  $country_codes)."
			                                        </div>
			                                    </fieldset>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;text-align:left\" colspan=\"3\">
                                    <table>
                                        <tr>
                                            <td >
			                                    <fieldset>
			                                        <legend>Tax ID</legend>
			                                        <div style=\"padding:5px;padding-bottom:5px\">
    			                                        ".$this->form->text_box("name=tax_id",
                            			                                        "value=".$search_vars['tax_id'],
                            			                                        "size=15",
                                                                                "onFocus=this.select()",
                            			                                        "maxlength=12")."
    			                                    </div>
			                                    </fieldset>
                                            </td>
                                            <td >
			                                    <fieldset>
			                                        <legend>Credit Limit</legend>
			                                        <div style=\"padding:5px;padding-bottom:5px\">
			                                            ".$this->form->text_box("name=credit_limit",
                        			                                            "value=".$search_vars['credit_limit'],
                        			                                            "size=15",
                                                                                "onFocus=this.select()",
                        			                                            "style=text-align:right;",
                        			                                            "onBlur=if(this.value){this.value=formatCurrency(this.value);}")."
			                                        </div>
			                                    </fieldset>
                                            </td>".(defined('MULTI_CURRENCY') ? "
			                                <td style=\"padding-left:15px;vertical-align:top;\">
			                                    <fieldset>
			                                        <legend>Customer Currency</legend>
			                                        <div style=\"padding:5px;padding-bottom:5px\">
			                                            ".$this->form->select("currency",
			                                                                  $currency_out,
			                                                                  $search_vars['currency'],
			                                                                  $currency_in)."
			                                        </div>
			                                    </fieldset>
			                                </td>" : NULL)."
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;\" colspan=\"3\">
                                    <table>
                                        <tr>
                                            <td >
                                                <fieldset>
                                                    <legend>Discount ID</legend>
                                                    <div style=\"padding:5px;padding-bottom:5px\">
                                                        ".$this->form->text_box("name=discount_id",
                                                                                "value=".$search_vars['discount_id'],
                                                                                "size=15",
                                                                                "onFocus=this.select()",
                                                                                "maxlength=24")."
                                                    </div>
                                                </fieldset>
                                            </td>
                                            <td style=\"vertical-align:middle\">
                                                <div style=\"padding:5px 5px 5px 15px;\">
                                                    ".$this->form->checkbox("name=gsa",
                                                                            "value=1",
			                                                                ($search_vars['gsa'] ? "checked" : NULL))."
                                                    &nbsp;GSA?
                                                </div>
                                            </td>
                                            <td style=\"vertical-align:middle\">
                                                <div style=\"padding:5px 5px 5px 15px;\">
                                                    ".$this->form->checkbox("name=po_req",
                                                                            "value=1",
			                                                                ($search_vars['po_req'] ? "checked" : NULL))."
                                                    &nbsp;PO Required?
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                            	<td style=\"padding-left:15px;\">
                            		<fieldset>
                            			<legend>Sales Rep</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=sales_rep",
																		"value=",
																		"autocomplete=off",
																		"size=30",
																		"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'sales_rep', 'sales_hash', 1);}",
		                                                                "onKeyUp=if(ka==false && this.value){key_call('proposals', 'sales_rep', 'sales_hash', 1);}",
																		"onKeyDown=if(event.keyCode!=9){clear_values('sales_hash');}").
											$this->form->hidden(array("sales_hash" => $last_input['sales_hash']))."
										</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;\" colspan=\"2\">
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
			<div style=\"text-align:right;padding:15px;\">
				".$this->form->button("value=Search", "id=primary", "onClick=submit_form(document.getElementById('test').form, 'customers', 'exec_post', 'refresh_form', 'action=doit_search');")."
			</div>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}

	function load_tax_selection($local=NULL, $args) {
		if ($local) {
            $customer_hash = $args['customer_hash'];
            $location_hash = $args['location_hash'];
            $location = $args['location'];
            $state = $args['state'];
            $country = $args['country'];
		} else {
	        $customer_hash = $this->ajax_vars['customer_hash'];
	        $location_hash = $this->ajax_vars['location_hash'];
	        $location = $this->ajax_vars['location'];
	        $popup_id = $this->ajax_vars['popup_id'];
	        $state = $this->ajax_vars['state'];
	        $country = $this->ajax_vars['country'];
		}
		if (!$state) {
			if ($local)
                return "<i>Select your state first...</i>";
            else
                $this->content['html'][($location ? "location" : NULL)."tax_selection_table".$popup_id] = "<i>Select your state first...</i>";

			return;
		}

		if ( $country == 'US' ) {
	        $sys = new system_config($this->current_hash);
	        $sys->fetch_tax_tables($country, $state);

	        if ($sys->total_tax_rules) {
	            $r = $this->db->query("SELECT tax_hash
	                                   FROM location_tax
	                                   WHERE location_hash = 'customers|".$customer_hash.($location && $location_hash ? "|".$location_hash : NULL)."'");
	            while ($row = $this->db->fetch_assoc($r))
	                $tax_location[] = $row['tax_hash'];

	            $tax_table .= "
	            <div style=\"margin-top:5px;border:1px solid #8f8f8f;width:90%;".($sys->total_tax_rules > 3 ? "height:100px;overflow:auto;" : NULL)."\">
	                <table style=\"width:94%;\" cellpadding=\"4\" cellspacing=\"1\">";

	                while (list($state, $info) = each($sys->tax_tables)) {
	                    $tax_table .= "
	                    <tr style=\"background-color:#efefef;\">
	                        <td style=\"border-bottom:1px solid #8f8f8f;border-right:1px solid #8f8f8f;font-size:9pt;\">
	                            ".$this->form->checkbox("name=".($location ? "location" : "customer")."_tax[]",
	                                                    "value=".$info['state']['tax_hash'],
	                                                    (@in_array($info['state']['tax_hash'], $tax_location) ? "checked" : NULL)).
	                            "&nbsp;&nbsp;".
	                            $info['state']['state_name']."
	                        </td>
	                    </tr>";
	                    if ($info['local']) {
	                        for ($i = 0; $i < count($info['local']); $i++)
	                            $tax_table .= "
	                            <tr style=\"background-color:#efefef;\">
	                                <td style=\"padding-left:35px;border-right:1px solid #8f8f8f;font-size:8pt;border-bottom:1px solid #8f8f8f;\">
	                                    ".$this->form->checkbox("name=".($location ? "location" : "customer")."_tax[]",
	                                                            "id=tax_".$info['state']['state_name'],
	                                                            "value=".$info['local'][$i]['tax_hash'],
	                                                            (@in_array($info['local'][$i]['tax_hash'], $tax_location) ? "checked" : NULL)).
	                                    "&nbsp;&nbsp;".
	                                    $info['local'][$i]['local']."
	                                </td>
	                            </tr>";
	                    }
	                }
	            $tax_table .= "
	                </table>
	            </div>";
	        }
		}

        if ($local)
            return $tax_table;
        else {
        	if ($tax_table) {
	            $this->content['html'][($location ? "location" : NULL)."tax_selection_table".$popup_id] = $tax_table;
	            $this->content['jscript'][] = "\$('".($location ? "location" : "primary")."_tax_rules').setStyle({'display':'block'});\$('".($location ? "location" : NULL)."tax_selection_table".$popup_id."').setStyle({'display':'block'});";
        	} else
                $this->content['jscript'][] = "\$('".($location ? "location" : "primary")."_tax_rules').setStyle({'display':'none'});\$('".($location ? "location" : NULL)."tax_selection_table".$popup_id."').setStyle({'display':'none'});";

        }
	}


	//Fetch locations linked to a sp
	function fetch_location_contacts($contact_hash=NULL) {
        if (!$contact_hash)
            return;

		$r = $this->db->query("SELECT `location_hash`
		                       FROM `location_contacts`
		                       WHERE `contact_hash` = '$contact_hash'");
        while ($row = $this->db->fetch_assoc($r))
            $hash[] = $row['location_hash'];


        return ($hash ? $hash : false);
	}

	function fetch_location_list($customer_hash) {

        $r = $this->db->query("SELECT `location_hash` , `location_name`
                               FROM locations
                               WHERE entry_hash = '$customer_hash' AND entry_type = 'c' AND deleted = 0");
        while ($row = $this->db->fetch_assoc($r)) {
            $hash[] = $row['location_hash'];
            $name[] = $row['location_name'];
        }

        return array($hash, $name);
	}

	function calculate_totals($customer_hash) {

        $result = $this->db->query("SELECT
                                    (SUM(CASE
                                        WHEN customer_invoice.type = 'I' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END) -
                                    SUM(CASE
                                        WHEN customer_invoice.type = 'D' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END)
                                    ) AS balance_total ,
                                    (SUM(CASE
                                        WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) < ".ACCOUNT_AGING_SCHED1." AND customer_invoice.type = 'I' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END) -
                                    SUM(CASE
                                        WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) < ".ACCOUNT_AGING_SCHED1." AND customer_invoice.type = 'D' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END)
                                    ) AS balance_current ,
                                    (SUM(CASE
                                        WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND customer_invoice.type = 'I' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END) -
                                    SUM(CASE
                                        WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND customer_invoice.type = 'D' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END)
                                    ) AS balance_sched1 ,
                                    (SUM(CASE
                                        WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND customer_invoice.type = 'I' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END) -
                                    SUM(CASE
                                        WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND customer_invoice.type = 'D' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END)
                                    ) AS balance_sched2 ,
                                    (SUM(CASE
                                        WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) >= ".ACCOUNT_AGING_SCHED3." AND customer_invoice.type = 'I' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END) -
                                    SUM(CASE
                                        WHEN DATEDIFF(CURDATE(), customer_invoice.invoice_date) >= ".ACCOUNT_AGING_SCHED3." AND customer_invoice.type = 'D' AND customer_invoice.balance != 0
                                            THEN customer_invoice.balance
                                            ELSE 0
                                        END)
                                    ) AS balance_sched3
                                    FROM `customer_invoice`
                                    WHERE customer_invoice.customer_hash = '$customer_hash' AND customer_invoice.deleted = 0");
        $row = $this->db->fetch_assoc($result);
        if (is_array($row))
            return $row;

        return array();
	}
}


























?>