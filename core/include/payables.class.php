<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class payables extends AJAX_library {

	public $total;
	public $total_queue;
	public $invoice_info_refund;
	public $total_commissions_paid;
	public $total_memo_costs;

	public $current_hash;
	public $vendor_hash;
	public $queue_hash;
	public $invoice_hash;
	public $expense_hash;
	public $payment_hash;
	public $proposal_hash;
	public $commission_hash;
	public $memo_hash;

	public $invoice_info = array();
	public $current_invoice = array();
	public $current_expense_line = array();
	public $current_queue = array();
	public $pay_queue = array();
	public $payment_info = array();
	public $memo_cost_items = array();
	public $commissions_paid = array();
	public $current_payment = array();
	public $current_commission = array();
	public $current_memo = array();

	public $trans_error = array(
    	'default_ap'           =>  "Your default Accounts Payable chart of account has not been defined.",
	    'misc_acct'            =>  "One of the chart of accounts found within the current transaction has not been properly defined. Please ensure that any accounts used within the current transaction have been properly defined.",
	    'default_vend_dep'     =>  "Your default Vendor Deposits chart of account has not been properly defined.",
	    'default_vend_disc'    =>  "Your default Vendor Discounts Taken chart of account has not been properly defined.",
	    'default_wip'          =>  "Your default Work In Progress chart of account has not been properly defined."
	);

	function payables($passedHash=NULL) {
		global $db;

		$this->form = new form;
		$this->db =& $db;

		$this->current_hash = $_SESSION['id_hash'];
		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);

		for ($i = 0; $i < count($_POST['aa_sfunc_args']); $i++) {
			if ($i == 0 && $_POST['aa_sfunc_args'][$i] == 'paybills')
				$this->pay_screen = true;
			if (ereg("force_show", $_POST['aa_sfunc_args'][$i]))
				$this->force_show = true;
		}

		if ($this->page_pref = $this->p->page_pref(get_class($this))) {
			if ($this->page_pref['show'] != '*' && !$force_show && !$pay_screen) {
				switch ($this->page_pref['show']) {
					case 1:
					$sql_p[] = "vendor_payables.balance = 0";
					break;

					case 2:
					$sql_p[] = "vendor_payables.pay_flag = 1";
					break;

					case 3:
					$sql_p[] = "vendor_payables.balance > 0";
					break;
				}
			}
			if ($this->pay_screen)
				$sql_p[] = "vendor_payables.balance > 0 AND vendor_payables.hold = 0 AND vendor_payables.type != 'C'";

			// Don't take page preferences into account if force show flag is set for paying bills.  Added for Trac #1348
			if ($this->force_show)
				$sql_p[] = "vendor_payables.pay_flag = 1 AND vendor_payables.type != 'C'";
			else {
				if ($this->page_pref['sort_from_date'])
					$sql_p[] = "vendor_payables.due_date >= '".$this->page_pref['sort_from_date']."'";
				if ($this->page_pref['sort_to_date'] && strtotime($this->page_pref['sort_to_date']) > strtotime($this->page_pref['sort_from_date']))
					$sql_p[] = "vendor_payables.due_date <= '".$this->page_pref['sort_to_date']."'";

				if ($this->page_pref['type'] != '*' && !$this->pay_screen && !$this->force_show)
					$sql_p[] = "vendor_payables.type = '".$this->page_pref['type']."'";

				if ($this->page_pref['created_by'] && !$this->pay_screen && !$this->force_show)
					$sql_p[] = "vendor_payables.created_by ".(count($this->page_pref['created_by']) == 1 ? "= '".$this->page_pref['created_by'][0]."'" : "IN (".implode(" , ", $this->page_pref['created_by']).")");
			}
			if (!$this->active_search)
				$this->page_pref['custom'] = 1;
		}

		if ($sql_p && !$_POST['active_search']) {

			$sql = implode(" AND ", $sql_p);
			$this->page_pref['str'] = $sql;

			$result = $this->db->query("SELECT COUNT(*)
										FROM vendor_payables
										LEFT JOIN purchase_order ON purchase_order.po_hash = vendor_payables.po_hash AND purchase_order.deleted = 0
										LEFT JOIN vendor_payment ON vendor_payment.invoice_hash = vendor_payables.invoice_hash
										WHERE " .
                            			( $sql ?
                                			"$sql AND " : NULL
                            			) . "vendor_payables.deleted = 0
                            			GROUP BY vendor_payables.obj_id");
			$this->total = $this->db->num_rows($result);

		} else {
			if ($_POST['active_search']) {

				$result = $this->db->query("SELECT total
											FROM `search`
											WHERE `search_hash` = '".$_POST['active_search']."'");
				$this->total = $this->db->result($result);
			} else {
				$result = $this->db->query("SELECT COUNT(*) AS Total
											FROM vendor_payables
											WHERE vendor_payables.deleted = 0");
				$this->total = $this->db->result($result);
			}
		}

		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `vendor_pay_queue`");
		$this->total_queue = $this->db->result($result);
		return;
	}


	function fetch_proposal_payables($proposal_hash, $order=NULL, $order_dir=NULL) {
		if (!$proposal_hash)
			return;

		$this->invoice_info = array();
		$result = $this->db->query("SELECT vendor_payables.* , vendors.vendor_name , purchase_order.po_no ,
									purchase_order.order_amount
									FROM `vendor_payables`
									LEFT JOIN `purchase_order` ON purchase_order.po_hash = vendor_payables.po_hash AND purchase_order.deleted = 0
									LEFT JOIN `proposals` ON proposals.proposal_hash = purchase_order.proposal_hash
									LEFT JOIN `vendors` ON vendors.vendor_hash = vendor_payables.vendor_hash
									WHERE purchase_order.proposal_hash = '$proposal_hash' AND vendor_payables.deleted = 0");
		while($row = $this->db->fetch_assoc($result))
			array_push($this->invoice_info, $row);

		$this->total = count($this->invoice_info);
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

	function fetch_open_payables($order_by=NULL, $order_dir=NULL) {
		$this->invoice_info = array();
		$result = $this->db->query("SELECT vendor_payables.*
									FROM `vendor_payables`
									WHERE ".($this->vendor_hash ?
										"vendor_payables.vendor_hash = '".$this->vendor_hash."' AND " : NULL)."
										vendor_payables.balance != 0 AND vendor_payables.type = 'I' AND vendor_payables.deleted = 0 ".($order_by ? "
										ORDER BY ".$order_by.($order_dir ?
											" ".$order_dir : " ASC") : "ORDER BY vendor_payables.due_date ASC"));
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->invoice_info, $row);
	}

	function fetch_payables($start, $end, $order_by=NULL, $order_dir=NULL) {

		$this->invoice_info = array();
		$_total = $this->invoice_info_refund = 0;

		if ( $this->active_search ) {

			$result = $this->db->query("SELECT
											t1.query,
											t1.total,
											t1.search_str
										FROM search t1
								  		WHERE t1.search_hash = '{$this->active_search}'");
			if ( $row = $this->db->fetch_assoc($result) ) {

				$this->total = $row['total'];
				$sql = base64_decode($row['query']);

				unset($this->page_pref['custom']);
				$this->detail_search = $row['detail_search'];
				$this->search_vars = $this->p->load_search_vars($row['search_str']);
			}
		} elseif ( $this->page_pref['str'] )
			$sql = $this->page_pref['str'];

		$r = $this->db->query("SELECT COUNT(*)
							   FROM vendor_payables
							   LEFT JOIN purchase_order ON purchase_order.po_hash = vendor_payables.po_hash AND purchase_order.deleted = 0
							   LEFT JOIN vendor_payment ON vendor_payment.invoice_hash = vendor_payables.invoice_hash
							   WHERE " .
                               ( $sql ?
                                   "$sql AND " : NULL
                               ) . "
                               vendor_payables.deleted = 0
                               GROUP BY vendor_payables.obj_id");
		$this->total = $this->db->num_rows($r);

		if ( $order_by || $sql ) {

			$pattern = array(
				'/\bvendor_payables\.(.*)\b/U',
				'/\bvendors\.(.*)\b/U',
				'/\bpurchase_order\.(.*)\b/U',
				'/\bproposals\.(.*)\b/U',
				'/\bvendor_payment\.(.*)\b/U',
			);
	        $replace = array(
		        't1.$1',
		        't5.$1',
		        't2.$1',
	        	't3.$1',
	        	't4.$1'
	        );

	        if ( $order_by )
		        $order_by = preg_replace($pattern, $replace, $order_by);

		    if ( $sql )
			    $sql = preg_replace($pattern, $replace, $sql);
		}

		$result = $this->db->query("SELECT
										t1.*,
										t1.due_date AS orig_due_date,
										t5.vendor_name,
										t5.payment_discount,
										t2.po_no,
										t2.order_amount,
										t3.proposal_hash,
										CASE
										WHEN t5.discount_days != 0 AND t1.type = 'I'
											THEN DATE_ADD(t1.due_date, INTERVAL - t5.discount_days DAY)
											ELSE t1.due_date
										END AS due_date,
										IF( ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.amount, ROUND( currency_exchange(t1.amount, t1.exchange_rate, 0), 2) ) AS amount,
										IF( ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.balance, ROUND( currency_exchange(t1.balance, t1.exchange_rate, 0), 2) ) AS balance,
										IF( ISNULL(t1.currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(t1.currency) ) AS symbol
									FROM vendor_payables t1
									LEFT JOIN purchase_order t2 ON t2.po_hash = t1.po_hash AND t2.deleted = 0
									LEFT JOIN proposals t3 ON t3.proposal_hash = t2.proposal_hash
									LEFT JOIN vendor_payment t4 ON t4.invoice_hash = t1.invoice_hash
									LEFT JOIN vendors t5 ON t5.vendor_hash = t1.vendor_hash
									WHERE " .
                            		( $sql ?
										"$sql AND " : NULL
                            		) . "t1.deleted = 0
                            		GROUP BY t1.obj_id " .
                            		( $order_by ?
    							  		"ORDER BY $order_by " .
                                		( $order_dir ?
                                    		$order_dir : "ASC"
                                    	) : NULL
                                    ) . "
							  		LIMIT $start, $end");
		while( $row = $this->db->fetch_assoc($result) ) {

			$_total++;
			if ( $row['type'] == 'R' )
				$this->invoice_info_refund++;

			array_push($this->invoice_info, $row);
		}

		return $_total;
	}

	function fetch_pay_queue($account_hash, $preview=NULL, $from_start=NULL) {

		$this->pay_queue = array();

		if ( $result = $this->db->query("SELECT
		                            		t1.*,
		                            		t1.invoice_no as check_invoice_no,
											t1.amount as pay_amount,
											t1.currency AS pay_queue_currency,
											t1.exchange_rate AS pay_queue_exchange_rate,
											IF( ISNULL(t1.currency) OR t1.currency = @FUNCTIONAL_CURRENCY, NULL, ROUND( currency_exchange(t1.amount, t1.exchange_rate, 0), 2) ) AS converted_amount,
											IF( ISNULL(t1.currency) OR t1.currency = @FUNCTIONAL_CURRENCY, currency_symbol(NULL), currency_symbol(t1.currency) ) AS symbol,
											t1.account_hash as payment_account,
											t2.*,
											t4.vendor_name,
											t3.posting_date AS posted_check_date
									    FROM vendor_pay_queue t1
										LEFT JOIN vendor_payables t2 ON t2.invoice_hash = t1.invoice_hash
										LEFT JOIN vendor_payment t3 ON t3.payment_hash = t1.payment_hash
										LEFT JOIN vendors t4 ON t4.vendor_hash = t2.vendor_hash
										WHERE t1.account_hash = '$account_hash' " .
										( $preview ?
	    									" AND t1.ready = 1" : NULL
										) . "
										ORDER BY " .
										( $from_start ?
	    									"t2.due_date" : "t1.check_no ASC"
										) )
        ) {

	        while ( $row = $this->db->fetch_assoc($result) ) {

	            if ( $row['type'] == 'R' ) {

	                $customers = new customers($this->current_hash);
	                $customers->fetch_master_record($row['vendor_hash']);
	                $row['vendor_name'] = $customers->current_customer['customer_name'];
	            }

	            array_push($this->pay_queue, $row);
	        }

	        return true;
        }

        return false;
	}

	function fetch_queue_record($queue_hash) {
		$result = $this->db->query("SELECT t1.* , t1.currency AS pay_queue_currency , t1.exchange_rate AS pay_queue_exchange_rate ,
		                            IF(ISNULL(t1.currency) OR t1.currency = @FUNCTIONAL_CURRENCY, NULL, ROUND(currency_exchange(t1.amount, t1.exchange_rate, 0), 2)) AS converted_amount ,
		                            IF(ISNULL(t1.currency) OR t1.currency = @FUNCTIONAL_CURRENCY, currency_symbol(NULL), currency_symbol(t1.currency)) AS symbol ,
		                            t2.vendor_hash , t2.currency , t2.exchange_rate
									FROM vendor_pay_queue t1
									LEFT JOIN vendor_payables t2 ON t2.invoice_hash = t1.invoice_hash
									WHERE t1.queue_hash = '$queue_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_queue = $row;
			$this->queue_hash = $queue_hash;
			return true;
		}

		return false;
	}

	function fetch_invoice_record($invoice_hash, $edit=false) {

		$result = $this->db->query("SELECT
	                            		t1.*,
	                            		IF( ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.amount, ROUND( currency_exchange(t1.amount, t1.exchange_rate, 0), 2) ) AS amount,
			                            IF( ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.balance, ROUND( currency_exchange(t1.balance, t1.exchange_rate, 0), 2) ) AS balance,
	                                    IF( ISNULL(t1.currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(t1.currency) ) AS symbol,
	                                    IF( ISNULL(t1.currency) OR ISNULL(@convert_currency), SUM(t2.amount), ROUND( currency_exchange(SUM(t2.amount), t1.exchange_rate, 0), 2)) AS total_expense_amount,
	                            		t3.po_no,
	                            		t3.proposal_hash AS invoice_proposal,
	                            		t4.vendor_name,
	                            		t4.currency AS vendor_currency,
	                            		COUNT(t2.expense_hash) AS total_expenses
									FROM vendor_payables t1
									LEFT JOIN vendor_payable_expenses t2 ON t2.invoice_hash = t1.invoice_hash
									LEFT JOIN purchase_order t3 ON t3.po_hash = t1.po_hash AND t3.deleted = 0
									LEFT JOIN vendors t4 ON t4.vendor_hash = t1.vendor_hash
									WHERE t1.invoice_hash = '$invoice_hash' AND t1.deleted = 0
									GROUP BY t1.invoice_hash");
		if ( $row = $this->db->fetch_assoc($result) ) {

			$this->current_invoice = $row;
			$this->invoice_hash = $invoice_hash;

			if ( $edit )
				$this->lock = $this->content['row_lock'] = $this->p->lock("vendor_payables", $this->invoice_hash, $this->popup_id);

			if ( $this->current_invoice['type'] == 'R' ) {

				$customer_invoice = new customer_invoice($this->current_hash);
				$customer_invoice->fetch_invoice_record($this->current_invoice['po_hash']);
				$this->current_invoice['customer_invoice'] = $customer_invoice->current_invoice;
				$this->current_invoice['vendor_name'] = stripslashes($this->current_invoice['customer_invoice']['customer_name']);
			}

            $this->current_invoice['expense_items'] = $this->current_invoice['payments'] = array();
            $this->current_invoice['voided_payments'] = 0;

			if ( bccomp($this->current_invoice['total_expenses'], 0, 2) == 1 )
				$this->current_invoice['expense_items'] = $this->fetch_invoice_expenses($this->invoice_hash);

			$result = $this->db->query("SELECT DISTINCT
	                            			t1.*,
	                            			t2.check_date,
	                            			t2.cleared,
	                            			t2.void,
	                                        IF( ISNULL(t2.currency) OR ISNULL(@convert_currency), t1.payment_amount, ROUND( currency_exchange(t1.payment_amount, t2.exchange_rate, 0), 2) ) AS payment_amount,
	                                        IF( ISNULL(t2.currency) OR ISNULL(@convert_currency), t1.deposit_used, ROUND( currency_exchange(t1.deposit_used, t2.exchange_rate, 0), 2) ) AS deposit_used,
	                                        IF( ISNULL(t2.currency) OR ISNULL(@convert_currency), t1.discount_taken, ROUND( currency_exchange(t1.discount_taken, t2.exchange_rate, 0), 2) ) AS discount_taken,
	                                        IF( ISNULL(t2.currency) OR ISNULL(@convert_currency), t1.credit_used, ROUND( currency_exchange(t1.credit_used, t2.exchange_rate, 0), 2) ) AS credit_used,
	                                        IF( ISNULL(t2.currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(t2.currency) ) AS symbol
										FROM vendor_payment t1
										LEFT JOIN check_register t2 ON t2.check_no = t1.check_no AND t2.account_hash = t1.account_hash
										WHERE t1.invoice_hash = '$invoice_hash'");
			while ( $row2 = $this->db->fetch_assoc($result) ) {

				array_push($this->current_invoice['payments'], $row2);
				if ( $row2['void'] )
                    $this->current_invoice['voided_payments']++;
			}

			if ( $this->current_invoice['item_map'] ) {

                $result = $this->db->query("SELECT t1.item_hash
                                            FROM line_items t1
                                            WHERE t1.ap_invoice_hash = '{$this->invoice_hash}'");
                while ( $row = $this->db->fetch_assoc($result) )
                    $this->current_invoice['ap_invoice_item'][] = $row['item_hash'];

                $result = $this->db->query("SELECT t1.item_hash
                                            FROM work_order_items t1
                                            WHERE t1.ap_invoice_hash = '{$this->invoice_hash}'");
                while ( $row = $this->db->fetch_assoc($result) )
                    $this->current_invoice['ap_invoice_item'][] = $row['item_hash'];
			}

			return true;
		}

		return false;
	}

	function fetch_invoice_expenses($invoice_hash) {
		$result = $this->db->query("SELECT t1.* , t2.account_name , t2.account_no , t3.proposal_no ,  t3.proposal_hash as proposal_hash,
		                            IF(ISNULL(t4.currency) OR ISNULL(@convert_currency), t1.amount, ROUND(currency_exchange(t1.amount, t4.exchange_rate, 0))) AS amount
									FROM vendor_payable_expenses t1
									LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
									LEFT JOIN vendor_payables t4 ON t4.invoice_hash = t1.invoice_hash
		                            LEFT JOIN purchase_order t5 ON t5.po_hash = t4.po_hash
                                    LEFT JOIN proposals t3 ON t3.proposal_hash = t5.proposal_hash
									WHERE t1.invoice_hash = '$invoice_hash'
									ORDER BY t1.obj_id ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			if ($row['account_no'])
				$row['account_name'] = $row['account_no']." : ".stripslashes($row['account_name']);

			$expense_items[] = $row;
		}
		return $expense_items;
	}

	function fetch_expense_line($expense_hash, $invoice_hash) {

		$result = $this->db->query("SELECT t1.* , t2.account_name , t3.proposal_no ,
		                            IF(ISNULL(t4.currency), NULL, ROUND(currency_exchange(t1.amount, t4.exchange_rate, 0))) AS amount_converted
									FROM vendor_payable_expenses t1
									LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
									LEFT JOIN proposals t3 ON t3.proposal_hash = t1.proposal_hash
                            		LEFT JOIN vendor_payables t4 ON t4.invoice_hash = t1.invoice_hash
									WHERE t1.invoice_hash = '$invoice_hash' AND t1.expense_hash = '$expense_hash'");
		if ($row = $this->db->fetch_assoc($result)) {

			$this->current_expense_line = $row;
			$this->expense_hash = $expense_hash;

			return true;
		}

		return false;
	}

	function fetch_vendor_payments($invoice_hash=NULL) {
		$this->payment_info = array();

		$result = $this->db->query("SELECT vendor_payment.* , check_register.check_date , check_register.check_no , vendor_payables.invoice_no
									FROM `vendor_payment`
									LEFT JOIN vendor_payables ON vendor_payables.invoice_hash = vendor_payment.invoice_hash
									LEFT JOIN check_register ON check_register.check_no = vendor_payment.check_no AND check_register.account_hash = vendor_payment.account_hash
									WHERE vendor_payment.invoice_hash = '$invoice_hash'");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->payment_info, $row);
	}

	function fetch_payment_record($payment_hash) {
		if (!$payment_hash)
			return;

		$result = $this->db->query("SELECT t1.* ,
                                    IF(ISNULL(t3.currency) OR ISNULL(@convert_currency), t1.payment_amount, ROUND(currency_exchange(t1.payment_amount, t3.exchange_rate, 0), 2)) AS payment_amount ,
                                    IF(ISNULL(t3.currency) OR ISNULL(@convert_currency), t1.deposit_used, ROUND(currency_exchange(t1.deposit_used, t3.exchange_rate, 0), 2)) AS deposit_used ,
                                    IF(ISNULL(t3.currency) OR ISNULL(@convert_currency), t1.discount_taken, ROUND(currency_exchange(t1.discount_taken, t3.exchange_rate, 0), 2)) AS discount_taken ,
                                    IF(ISNULL(t3.currency) OR ISNULL(@convert_currency), t1.credit_used, ROUND(currency_exchange(t1.credit_used, t3.exchange_rate, 0), 2)) AS credit_used ,
                                    IF(ISNULL(t3.currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(t3.currency)) AS symbol ,
		                            t3.check_date , t3.check_no , t2.invoice_no , t2.vendor_hash , t2.invoice_date
									FROM `vendor_payment` t1
									LEFT JOIN vendor_payables t2 ON t2.invoice_hash = t1.invoice_hash
									LEFT JOIN check_register t3 ON t3.check_no = t1.check_no AND t3.account_hash = t1.account_hash
									WHERE t1.payment_hash = '$payment_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_payment = $row;
			$this->payment_hash = $payment_hash;
			return true;
		}

		return false;
	}

	function amount_tracker() {
		$payment_account_hash = $_POST['payment_account_hash'];
		$invoice_hash = $_POST['invoice_hash'];

		$amount_due = $_POST['amount_due'];
        $discounts_taken = $_POST['discounts_taken'];
        $vendor_deposits = $_POST['vendor_deposits'];
        $vendor_credits = $_POST['vendor_credits'];
        $vendor_hash = $_POST['vendor_hash'];
		$pay_amount = $_POST['pay_amount'];
		$from = $_POST['from'];
		$from_invoice = $_POST['from_invoice'];

		if (!$payment_account_hash) {
			$this->content['error'] = 1;
			$this->content['form_return']['err']['err1'] = 1;
			$this->content['form_return']['feedback'] = "The account you entered is invalid. Please select a valid payment account.";
			return;
		}
		$accounts = new accounting($this->current_hash);
		$accounts->fetch_account_record($payment_account_hash);
		$balance = $accounts->current_account['balance'];

		$amount_to_pay = 0;
		for ($i = 0; $i < count($invoice_hash); $i++) {
			$posted_pay_amt = preg_replace('/[^-.0-9]/', "", $pay_amount[$invoice_hash[$i]]);
			$amount_to_pay += $posted_pay_amt;
			$from_field = $from[$invoice_hash[$i]];

			$total_amt_due += $amount_due[$invoice_hash[$i]];
			$vendor_disc = preg_replace('/[^-.0-9]/', "", $discounts_taken[$invoice_hash[$i]]);
			if ($vendor_disc > $amount_due[$invoice_hash[$i]]) {
				$vendor_disc = $amount_due[$invoice_hash[$i]];
				$this->content['value']['discounts_taken_'.$invoice_hash[$i]] = number_format($vendor_disc, 2);
			}
			$total_disc += $vendor_disc;
			$deposits = preg_replace('/[^-.0-9]/', "", $vendor_deposits[$invoice_hash[$i]]);

			$total_dep += $deposits;
			$credits = 0;
			$credits = preg_replace('/[^-.0-9]/', "", $vendor_credits[$invoice_hash[$i]]);
			if ($credits) {
				$total_cre += $credits;
			    $credit_tracker[$vendor_hash[$invoice_hash[$i]]] += $credits;
			}
			$total_to_pay = ($amount_due[$invoice_hash[$i]] - $vendor_disc - $deposits - $credits);
			// Adjust posted pay amount only if it is greater than the maximum amount allowed.  Modified for Trac #1411 and #1453
			if (bccomp($total_to_pay, $posted_pay_amt) == -1 || ($from_field != 'pay_amount' && $from_invoice && $from_invoice == $invoice_hash[$i] && bccomp($total_to_pay, $posted_pay_amt) != 0) || !is_numeric($posted_pay_amt)) {
				$amount_to_pay = bcsub($amount_to_pay, $posted_pay_amt);
				$posted_pay_amt = $total_to_pay;
				$amount_to_pay = bcadd($amount_to_pay, $posted_pay_amt);
				$this->content['value']['pay_amount_'.$invoice_hash[$i]] = number_format($posted_pay_amt, 2);
			}
		}

		// Find the unselected invoices and depopulate their dollar amount for #1453
		if (is_array($invoice_hash))
			$unselected = array_diff(array_keys($vendor_hash), $invoice_hash);
		else
			$unselected = array_keys($vendor_hash);

		// Uncheck the check all box at the top if any have been unchecked, and check it if all are checked
		if (count($unselected) > 0)
			$this->content['jscript'][] = "\$('check_all').checked = 0";
		else
			$this->content['jscript'][] = "\$('check_all').checked = 1";

		foreach ($unselected as $key => $val) {
			if (preg_replace('/[^-.0-9]/', "", $pay_amount[$val]))
                $this->content['value']['pay_amount_'.$val] = '';
		}
		$this->content['html']['total_amount_due_holder'] = "$".number_format($total_amt_due, 2);
		$this->content['html']['total_discounts_holder'] = ($total_disc > 0 ? "($".number_format($total_disc, 2).")" : "&nbsp;");
		$this->content['html']['total_deposits_holder'] = ($total_dep > 0 ? "($".number_format($total_dep, 2).")" : "&nbsp;");
		$this->content['html']['total_credits_holder'] = ($total_cre > 0 ? "($".number_format($total_cre, 2).")" : "&nbsp;");

		$this->content['html']['payment_account_balance'] = "Ending Balance: ".($balance - $amount_to_pay < 0 ? "<span style=\"color:#ff0000;\">($".number_format(($balance - $amount_to_pay) * -1, 2).")</span>" : "$".number_format($balance - $amount_to_pay, 2));
		$this->content['html']['total_to_pay_holder'] = "$".number_format($amount_to_pay, 2);
		$this->content['action'] = 'continue';
		$this->content['submit_btn'] = 'pay_btn';
		return;
	}

	function vendor_due_date() {
		$vendor_hash = ($_POST['vendor_hash'] ? $_POST['vendor_hash'] : $_POST['po_vendor_hash']);
		$invoice_date = $_POST['invoice_date'];
		$error_node = $_POST['error_node'];
		$change = $_POST['change'];
		$po_no = $_POST['po_no'];

		if (!$change && $invoice_date && $vendor_hash) {
			$vendors = new vendors($this->current_hash);
			$vendors->fetch_master_record($vendor_hash);

			if ($vendors->current_vendor['payment_terms']) {
				$due_date = date("Y-m-j", strtotime($invoice_date." +".$vendors->current_vendor['payment_terms']." days"));
				list($year, $month, $day) = explode("-", $due_date);
				$this->content['jscript'][] = "\$('due_date_Month_ID').value='".($month-1)."';\$('due_date_Day_ID').value='".($day)."';\$('due_date_Year_ID').value='".$year."';\$('due_date').value='".$due_date."';";
			}
			$r = $this->db->query("SELECT customer_vendor_notes.timestamp , customer_vendor_notes.comment , users.full_name
								   FROM `customer_vendor_notes`
								   LEFT JOIN users ON users.id_hash = customer_vendor_notes.user_hash
								   WHERE customer_vendor_notes.entry_hash = '".$vendors->vendor_hash."' AND customer_vendor_notes.type = 'V'");
			while ($row = $this->db->fetch_assoc($r))
				$note[] = stripslashes($row['full_name'])." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $row['timestamp']).") : ".stripslashes($row['comment']);

			if ($note)
				$this->content['html']['vendor_note_holder'] = "<a href=\"javascript:void(0);\" title=\"".@implode("\n", $note)."\"><img src=\"images/line_note.gif\" border=\"0\" /></a>";
			else
				$this->content['html']['vendor_note_holder'] = '';

	        if (defined('MULTI_CURRENCY') && $vendors->current_vendor['currency'] && $vendors->current_vendor['currency'] != FUNCTIONAL_CURRENCY) {
	            $sys = new system_config();

	            $vendor_currency = $sys->home_currency;
	            if ($sys->home_currency['code'] != $vendors->current_vendor['currency']) {
	                $sys->fetch_currency($vendors->current_vendor['currency']);
	                $vendor_currency = $sys->current_currency;
	            }

	            $this->content['html']['currency_select'] = $this->form->select("currency",
	                                                                            array($vendor_currency['code'], $sys->home_currency['code']),
	                                                                            $vendor_currency['code'],
	                                                                            array($vendor_currency['code'], $sys->home_currency['code']),
	                                                                            "blank=1");
                $this->content['jscript'][] = "toggle_display('currency_holder', 'block');";
	        } else
                $this->content['jscript'][] = "toggle_display('currency_holder', 'none');";

		}
		if (!$_POST['account_name_'.$error_node[0]] || $change == 'type') {
			$type = $_POST['type'];

			$result = $this->db->query("SELECT t1.invoice_hash
										FROM vendor_payables t1
										WHERE t1.vendor_hash = '$vendor_hash'".($type ? " AND t1.type = '$type'" : NULL)."
										ORDER BY t1.invoice_date DESC , t1.obj_id DESC
										LIMIT 1");
			if ($previous_invoice = $this->db->result($result)) {
				//Get the last invoice and assign the expense dist
				$r = $this->db->query("SELECT t2.account_hash , t2.account_name , t2.account_no
									   FROM vendor_payable_expenses t1
									   LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
									   WHERE t1.invoice_hash = '$previous_invoice'
									   GROUP BY t2.account_hash
									   ORDER BY t1.obj_id ASC");
				while ($row = $this->db->fetch_assoc($r)) {
					if ($row['account_no'])
						$row['account_name'] = $row['account_no']." : ".addslashes($row['account_name']);

					$exp[] = $row;
				}

				for ($i = 0; $i < count($exp); $i++) {
					if ($i < 3) {
						$this->content['value']['account_name_'.$error_node[$i]] = $exp[$i]['account_name'];
						$this->content['value']['account_hash_'.$error_node[$i]] = $exp[$i]['account_hash'];
					} else {
						$rand_err = rand(500, 5000000);
						$expense_dist_account .= "
						<div style=\"margin-bottom:5px;\">".
							$this->form->text_box("name=account_name_".$rand_err,
													"value=".$exp[$i]['account_name'],
													"autocomplete=off",
													"size=25",
													"onBlur=key_clear();",
													"onFocus=if(this.value){select();}else{submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=innerHTML_vendor_po', 'qtype=balance', 'focus=".$rand_err."');}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', 1);}",
                                                    "onKeyUp=if(ka==false && this.value){key_call('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', 1);}",
                        							"onKeyDown=clear_values('account_hash_".$rand_err."');").
							$this->form->hidden(array("account_hash_".$rand_err => $exp[$i]['account_hash'], "error_node[]" => $rand_err))."
							<a href=\"javascript:void(0);\"
							   onClick=\"position_element(\$('keyResults'), findPos(\$('account_name_".$rand_err."'), 'top')+20, findPos(\$('account_name_".$rand_err."'), 'left')+1);key_list('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>
						</div>";
						$expense_dist_amount .= "
						<div style=\"margin-bottom:5px;\">".$this->form->text_box("name=amount_".$rand_err, "value=".($i == 0 && $total != 0 && $invoice_amount - $total != 0 ? number_format($invoice_amount - $total, 2) : NULL), "size=10", "style=text-align:right", "onFocus=if(this.value){select();}else{submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=innerHTML_vendor_po', 'qtype=balance', 'focus=".$rand_err."');}", "onBlur=this.value=formatCurrency(this.value)")."</div>";
						$expense_dist_memo .= "
						<div style=\"margin-bottom:5px;\">".$this->form->text_box("name=memo_".$rand_err, "value=", "size=23")."</div>";
						$expense_dist_proposal .= "
						<div style=\"margin-bottom:5px;\">
							".$this->form->text_box("name=proposal_no_".$rand_err,
													"value=",
													"autocomplete=off",
													"size=10",
													"onBlur=key_clear();",
													"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'proposal_no_".$rand_err."', 'proposal_hash_".$rand_err."', 1);}",
						                            "onKeyUp=if(ka==false && this.value){key_call('payables', 'proposal_no_".$rand_err."', 'proposal_hash_".$rand_err."', 1);}",
													"onKeyDown=clear_values('proposal_hash_".$rand_err."');").
							$this->form->hidden(array("proposal_hash_".$rand_err => ''))."
						</div>";
					}
				}
				if ($expense_dist_account) {
					$this->content['html_append']['expense_dist_account'] = $expense_dist_account;
					$this->content['html_append']['expense_dist_amount'] = $expense_dist_amount;
					$this->content['html_append']['expense_dist_memo'] = $expense_dist_memo;
					$this->content['html_append']['expense_dist_proposal'] = $expense_dist_proposal;
				}
			} else {
				if ((($type == 'I' && $po_no) || $type == 'D' ) && defined('DEFAULT_WIP_ACCT')) {
					$accounts = new accounting($this->current_hash);
					if ($accounts->fetch_account_record(DEFAULT_WIP_ACCT)) {
						$this->content['value']['account_name_'.$error_node[0]] = ($accounts->current_account['account_no'] ? $accounts->current_account['account_no']." : " : NULL).$accounts->current_account['account_name'];
						$this->content['value']['account_hash_'.$error_node[0]] = $accounts->current_account['account_hash'];
					}
				} elseif ($type == 'C' && defined('DEFAULT_VENDOR_CREDITS_ACCT')) {
					$accounts = new accounting($this->current_hash);
					if ($accounts->fetch_account_record(DEFAULT_VENDOR_CREDITS_ACCT)) {
						$this->content['value']['account_name_'.$error_node[0]] = ($accounts->current_account['account_no'] ? $accounts->current_account['account_no']." : " : NULL).$accounts->current_account['account_name'];
						$this->content['value']['account_hash_'.$error_node[0]] = $accounts->current_account['account_hash'];
					}
				} elseif ($type == 'R' && defined('DEFAULT_CUST_REFUND_ACCT')) {
					$accounts = new accounting($this->current_hash);
					if ($accounts->fetch_account_record(DEFAULT_CUST_REFUND_ACCT)) {
						$this->content['value']['account_name_'.$error_node[0]] = ($accounts->current_account['account_no'] ? $accounts->current_account['account_no']." : " : NULL).$accounts->current_account['account_name'];
						$this->content['value']['account_hash_'.$error_node[0]] = $accounts->current_account['account_hash'];
					}
				}
			}
		}
		$this->content['action'] = "continue";
	}

	function check_deposits($po_hash=NULL) {
		if (!$po_hash)
			$po_hash = ($this->ajax_vars['po_hash'] ? $this->ajax_vars['po_hash'] : $_POST['po_hash']);
		else
			$local = true;

		if (!$local)
			$this->content['action'] = 'continue';

		if ($po_hash) {
			$result = $this->db->query("SELECT (
											SUM(vendor_payables.amount - vendor_payables.balance) -
												(
													SELECT (CASE
														   WHEN !ISNULL(vendor_payment.deposit_used)
														       THEN SUM(vendor_payment.deposit_used)
														       ELSE 0
														   END)
													FROM vendor_payables
													LEFT JOIN vendor_payment ON vendor_payment.invoice_hash = vendor_payables.invoice_hash
													LEFT JOIN check_register ON check_register.check_no = vendor_payment.check_no AND check_register.account_hash = vendor_payment.account_hash
													WHERE vendor_payables.po_hash = '$po_hash' AND vendor_payables.type = 'I' AND vendor_payables.deleted = 0 AND check_register.void != 1
												) -
												(
                                                    SELECT (CASE
                                                           WHEN !ISNULL(vendor_pay_queue.deposit_used)
                                                               THEN SUM(vendor_pay_queue.deposit_used)
                                                               ELSE 0
                                                           END)
                                                    FROM vendor_payables
                                                    LEFT JOIN vendor_pay_queue ON vendor_pay_queue.invoice_hash = vendor_payables.invoice_hash
                                                    WHERE vendor_payables.po_hash = '$po_hash' AND vendor_payables.type = 'I' AND vendor_payables.deleted = 0
												)
										) AS total_dep ,
										SUM(vendor_payables.amount - vendor_payables.balance) AS initial_dep
										FROM `vendor_payables`
										WHERE `po_hash` = '$po_hash' AND `type` = 'D' AND vendor_payables.deleted = 0");
			if ($row = $this->db->fetch_assoc($result)) {
				if ($row['initial_dep']) {
					$total_dep = $row['total_dep'];
					$initial_dep = $row['initial_dep'];
					if ($initial_dep > 0 && $total_dep > 0)
						$deposit_txt = $this->content['html']['deposit_amt_holder'] = "<small>A deposit of ".($initial_dep > $total_dep ?
							"$".number_format($initial_dep, 2)." with a remaining balance of $".number_format($total_dep, 2)."<br />" : "$".number_format($initial_dep, 2))." has been paid towards this PO ".($initial_dep > $total_dep ? NULL : "<br />")."and will automatically be deducted from the total amount due at the time of payment.</small>";

					if (!$local && $initial_dep > 0 && $total_dep > 0)
						$this->content['jscript'][] = "toggle_display('deposit_amt_holder', 'block');";


				}
				return ($local ? $deposit_txt : true);
			}
		}
		if (!$local || $initial_dep <= 0)
			$this->content['jscript'] = "toggle_display('deposit_amt_holder', 'none');";
	}

	function doit_flag_invoice() {
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$flag = $_POST['flag'];
		$active_search = $_POST['active_search'];
		$jscript_action = $_POST['jscript_action'];
		$proposal_hash = $_POST['proposal_hash'];
		$return = $_POST['return'];

		for ($i = 0; $i < count($flag); $i++) {
			if ($flag[$i])
				$flag_item[] = $flag[$i];
		}

		if (count($flag_item)) {
			for ($i = 0; $i < count($flag_item); $i++) {
				$this->fetch_invoice_record($flag_item[$i]);
				if ($this->current_invoice['complete'] && !$this->p->is_locked("vendor_payables", $flag_item[$i])) {
					$u = 1;
					$this->db->query("UPDATE `vendor_payables`
									  SET `pay_flag` = 1
									  WHERE `invoice_hash` = '".$flag_item[$i]."'");
				}
			}
			$feedback = ($u ? count($flag_item)." invoice".(count($flag_item) > 1 ? "s have" : " has")." been flagged for payment." : "We were unable to flag your invoice(s) because they have either not been entered completely or are currently in view by someone else.");
		} else
			$feedback = "You selected no invoices. Nothing has been flagged.";

		$this->content['action'] = 'continue';
		if ($return) {
			list($class, $method) = explode("|", $return);
			$this->content['jscript_action'] = "agent.call('$class', 'sf_loadcontent', 'cf_loadcontent', '$method', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search=$active_search');";
		} else
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'proposal_hash=".$proposal_hash."', 'otf=1', 'p=$p', 'order=$order', 'order_dir=$order_dir');window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash=".$proposal_hash."\', \'otf=1\');', 1500)");

		$this->content['page_feedback'] = $feedback;
		return;
	}

	function doit() {

		$action = $_POST['action'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$btn = $_POST['btn'];
		$jscript_action = $_POST['jscript_action'];
		$from_class = $_POST['from_class'];
		$from_section = $_POST['from_section'];
		$this->popup_id = $this->content['popup_controls']['popup_id'] = $_POST['popup_id'];
		$active_search = $_POST['active_search'];
		if ( $action )
			return $this->$action();

		$invoice_hash = $_POST['invoice_hash'];
		$proposal_hash = $parent_proposal_hash = $_POST['proposal_hash'];

		if ( $btn == 'save' ) {

			$vendor_name = $_POST['po_vendor'];
			$vendor_hash = $_POST['po_vendor_hash'];
			if ( $proposal_hash && ! $vendor_name )
				$vendor_name = 'na';

			$po_hash = $_POST['po_hash'];
			$po_no = $_POST['po_no'];
			$invoice_amount = preg_replace('/[^-.0-9]/', "", $_POST['invoice_amount']);
			$invoice_no = $_POST['invoice_no'];
			$invoice_date = $_POST['invoice_date'];
			$receipt_date = $_POST['receipt_date'];
			$due_date = $_POST['due_date'];
			$type = $_POST['type'];
			$hold = $_POST['hold'];
			$from = $_POST['from'];
			$notes = $_POST['notes'];
			$exclude_credit = $_POST['exclude_credit'];
			$p = $_POST['p'];
			$order = $_POST['order'];
			$order_dir = $_POST['order_dir'];
			$submit_btn = 'invoice_btn';
			$save_option = $_POST['save_option'];
			if ( $po_hash )
    			$item_map = $_POST['item_map'];

			$ap_invoice_item = $_POST['ap_invoice_item'];
			$item_w = $_POST['item_w'];
			$currency = $_POST['currency'];
			
            if ( $invoice_hash ) {

                if ( ! $this->fetch_invoice_record($invoice_hash) )
                	return $this->__trigger_error("System error when attempting to fetch invoice record. Please reload window and try again. <!-- Tried fetching invoice [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                $vendor_name = $this->current_invoice['vendor_name'];
                if ( $this->current_invoice['deleted'] )
                	return $this->__trigger_error("Unable to proceed. This payable has been flagged deleted and cannot be modified.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

                if ($this->current_invoice['currency'] && $this->current_invoice['exchange_rate'])
                    $currency_flag = true;
            } else
                $currency_flag = false;

			if ( $type == 'R' ) {

				if ( ! $po_no || ! $po_hash ) {

					$this->set_error("err2{$this->popup_id}");
					return $this->__trigger_error("In order to create a new customer refund it must be linked to the original customer invoice. Please make sure you select the valid customer invoice you are refunding.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				$customer_invoice = new customer_invoice($this->current_hash);
				if ( ! $customer_invoice->fetch_invoice_record($po_hash) )
					return $this->__trigger_error("System error encountered when attempting to lookup receivable record. Please reload window and try again. <!-- Tried fetching receivable [ $po_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$invoice_no = $customer_invoice->current_invoice['invoice_no'];
				$receipt_date = date("Y-m-d");

			} elseif ( $type == 'D' ) {

				if ( ! $invoice_no && ! $invoice_hash )
					$invoice_no = $po_no;

				if ( ( $parent_proposal_hash && ! $po_hash ) || ( ! $parent_proposal_hash && ( ! $po_hash || ! $po_no ) ) ) {

					$this->set_error("err2{$this->popup_id}");
					return $this->__trigger_error("In order to create a new vendor deposit it must be linked to a valid purchase order. Please make sure that the purchase order has been cut prior to creating a vendor deposit.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

				} elseif ( ! $this->invoice_hash || ( $this->invoice_hash && $po_hash != $this->current_invoice['po_hash'] ) ) {

					$result = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
												FROM vendor_payables t1
												WHERE t1.po_hash = '$po_hash' AND t1.type = 'D' AND t1.deleted = 0");
					if ( $this->db->result($result, 0, 'Total') ) {

						$this->set_error("err2{$this->popup_id}");
						return $this->__trigger_error("A deposit already exists against this purchase order. Only one deposit is allowed per purchase order.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
					}
				}

				$r = $this->db->query("SELECT t1.po_no
									   FROM purchase_order t1
									   WHERE t1.po_hash = '$po_hash'");
				$invoice_no = $this->db->result($r, 0, 'po_no');
				if ( ! $invoice_no )
					return $this->__trigger_error("System error encountered when attempting to lookup purchase order record. Please reload window and try again. <!-- Tried fetching PO [ $po_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			} elseif ( ! $type && ! $invoice_hash )
				$type = 'I';

            if ( ! $_POST['invoice_dup_confirm'] && ( $type == 'I' || $type == 'C' ) && ( ! $invoice_hash || ( $invoice_hash && $this->current_invoice['invoice_no'] != trim($invoice_no) ) ) ) { # Check to make sure a payable has not previously been received under this vendor

                $r = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
                                       FROM vendor_payables t1
                                       WHERE t1.vendor_hash = '$vendor_hash' AND t1.invoice_no = '" . trim($invoice_no) . "' AND t1.deleted = 0");
                if ( $this->db->result($r, 0, 'Total') ) {

                	return $this->__trigger_error(
                    	"We found an invoice numbered [ $invoice_no ] already exists under the selected vendor. Are you sure you want to create an additional vendor payable with the same invoice number?
	                    <br /><br />" .
	                    $this->form->button(
	                        "value=Proceed Anyway",
	                        "onClick=\$('$submit_btn').disabled=1;submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'btn=save', 'invoice_dup_confirm=1'" . ( $_POST['po_overpay_ok'] ? ", 'po_overpay_ok={$_POST['po_overpay_ok']}'" : NULL ) . ");"
	                    ),
	                    E_USER_NOTICE,
	                    __FILE__,
	                    __LINE__,
	                    1,
	                    false,
	                    $submit_btn
	                );
                }
            }

			$accounting = new accounting($this->current_hash);

			if ( ! $invoice_hash && ! $this->invoice_hash )
				$invoice_hash = rand_hash('vendor_payables', 'invoice_hash');

			if ( $vendor_name && $invoice_amount && $invoice_no && $invoice_date && $receipt_date && $due_date ) {

				if ( ! $this->invoice_hash && ( ( $po_no && ! $po_hash ) || ( $vendor_name && ! $vendor_hash ) ) ) {

					if ( $vendor_name && ! $vendor_hash ) $this->set_error("err1{$this->popup_id}");
					if ($po_no && !$po_hash) $this->set_error("err2{$this->popup_id}");
					return $this->__trigger_error("We can't find the items you entered, indicated below. Please make sure you are entering a valid " . ( $type == 'R' ? "customer and/or Invoice number." : "vendor and/or PO number." ), E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				if ( $type == 'R' )
					$receipt_date = $invoice_date;

                if ( ! $this->invoice_hash && ( $currency || $po_hash ) ) {

                    $sys = new system_config();

                    if ( $type != 'R' && $po_hash ) {

                        $r = $this->db->query("SELECT
                                                   t1.currency,
                                                   t1.exchange_rate,
                                                   t1.currency_account
                                               FROM purchase_order t1
                                               WHERE t1.po_hash = '$po_hash'");
                        if ( $row = $this->db->fetch_assoc($r) ) {

                        	if ( $row['currency'] ) {

		                        $invoice_currency = $row['currency'];
		                        $currency_rate = $row['exchange_rate'];
		                        $currency_account = $row['currency_account'];
                        	}

                        } else
                            return $this->__trigger_error("Can't fetch purchase order. Please check that you've entered the correct po number.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

                    } elseif ( defined('MULTI_CURRENCY') && $currency ) {

	                    if ( ! $sys->fetch_currency($currency) )
	                        return $this->__trigger_error("System error encountered when attempting to lookup currency record. Please reload window and try again. <!-- Tried fetching currency [ $currency ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                    if ( ! $sys->home_currency['account_hash'] )
	                        return $this->__trigger_error("You have not defined a valid chart of account to be used for capturing gain/loss on currency exchange.", E_USER_NOTICE, __FILE__, __LINE__, 1, $submit_btn);

	                    if ( $sys->current_currency['code'] != $sys->home_currency['code'] ) {

	                        $invoice_currency = $sys->current_currency['code'];
	                        $currency_rate = $sys->current_currency['rate'];
	                        $currency_account = $sys->home_currency['account_hash'];
	                    } else
	                        unset($currency);
                    }

                } elseif ( $this->invoice_hash && $this->current_invoice['currency'] ) {

                    $invoice_currency = $this->current_invoice['currency'];
                    $currency_rate = $this->current_invoice['exchange_rate'];
                    $currency_account = $this->current_invoice['currency_account'];
                }

				$error_node = $_POST['error_node'];

				if ( ! $this->db->start_transaction() )
					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$audit_id = $accounting->audit_id();
				$total_expense_acct = $total = 0;
				$account_exec = array();

				for ( $i = 0; $i < count($error_node); $i++ ) {

                    $amount = preg_replace('/[^-.0-9]/', "", $_POST["amount_{$error_node[$i]}"]);
                    $total = bcadd($total, $amount, 2);
                    if ( $invoice_currency )
                        $amount = currency_exchange($amount, $currency_rate, 1);

					if ( $item_map && $i == 0 && $type != 'R') {

                        $item_map_wip_acct = $_POST["account_hash_{$error_node[$i]}"];
                        $item_map_wip_amt = $amount;
                        if ( ! $amount )
                            return $this->__trigger_error("In order to use A/P item mapping, your first expense distribution account must be used for your WIP account.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

                        if ( $item_map_wip_acct != fetch_sys_var('DEFAULT_WIP_ACCT') ) {
                        	$this->set_error("account_name_{$error_node[$i]}");
                            return $this->__trigger_error("In order to use A/P item mapping, the first expense account must be assigned to the default WIP account", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
                        }
					}

					$account_name = $_POST["account_name_{$error_node[$i]}"];
					$account_hash = $_POST["account_hash_{$error_node[$i]}"];
					$proposal_no = $_POST["proposal_no_{$error_node[$i]}"];
					$proposal_hash = $_POST["proposal_hash_{$error_node[$i]}"];
					$expense_id = $_POST["expense_id_{$error_node[$i]}"];
                    $memo = $_POST["memo_{$error_node[$i]}"];

					if ( bccomp($amount, 0, 2) && ! $account_name ) {

						$this->set_error("account_name_{$error_node[$i]}");
						if ( ! $account_name_err )
							$this->__trigger_error("You failed to include an account for one or more of your expense lines below. Please make sure you have selected a valid account for any of the expense lines you has an amount.", E_USER_NOTICE, __FILE__, __LINE__);

						$account_name_err = true;
					} elseif ( $amount && ! $account_hash ) {

						$this->set_error("account_name_{$error_node[$i]}");
						if ( ! $account_hash_err )
							$this->__trigger_error("One or more of the accounts below cannot be found. Please check to make sure you selected a valid account.", E_USER_NOTICE, __FILE__, __LINE__);

						$account_hash_err = true;
					}

					if ( $amount && $proposal_no && ! $proposal_hash ) {

						$this->set_error("proposal_no_{$error_node[$i]}");
						if ( ! $proposal_hash_err )
							$this->__trigger_error("One or more of the proposals below cannot be found. Please check to make sure you selected a valid proposal to cost your expense against.", E_USER_NOTICE, __FILE__, __LINE__);

						$proposal_hash_err = true;
					} elseif ( $amount && $proposal_hash && ! $proposal_no )
						unset($proposal_hash);

					if ( $amount && $proposal_hash && ! $memo ) {

						$this->set_error("memo_{$error_node[$i]}");
						if ( ! $proposal_memo_err )
							$this->__trigger_error("Please include a memo or comment for expenses that are to be costed against a job. This will better help job costs to be tracked in later reports.", E_USER_NOTICE, __FILE__, __LINE__);

						$proposal_memo_err = true;
					}

					if ( $amount && ! $this->content['error'] ) {

						$total_expense_acct++;
						unset($sql);
						if ( $expense_id ) {

							if ( ! $this->fetch_expense_line($expense_id, $invoice_hash) )
    							return $this->__trigger_error("System error when attempting to fetch expense distribution record. Please reload window and try again. <!-- Tried fetching expense distribution [ $expense_id $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

							( $account_hash != $this->current_expense_line['account_hash'] ? $sql[] = "account_hash = '$account_hash'" : NULL );
							( $proposal_hash != $this->current_expense_line['proposal_hash'] ? $sql[] = "proposal_hash = " . ( $proposal_hash ? "'$proposal_hash'" : "NULL" ) : NULL );
							( bccomp($amount, $this->current_expense_line['amount'], 2) ? $sql[] = "amount = '$amount'" : NULL );
							( strlen( base64_encode($memo) ) != strlen( base64_encode( stripslashes($this->current_expense_line['memo']) ) ) ? $sql[] = "memo = '" . addslashes($memo) . "'" : NULL );
							if ( $sql ) {

								if ( ! $this->db->query("UPDATE vendor_payable_expenses
            											 SET " . implode(", ", $sql) . "
            											 WHERE expense_hash = '$expense_id'")
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                                }

								if ( $account_hash != $this->current_expense_line['account_hash'] || bccomp($amount, $this->current_expense_line['amount'], 2) ) {

									if ( bccomp($amount, $this->current_expense_line['amount'], 2) ) {

										$adj = bcsub($amount, $this->current_expense_line['amount'], 2);

										array_push( $account_exec, array(
    										"audit_id" 			=>  $audit_id,
											"account_hash"		=>	$this->current_expense_line['account_hash'],
											"amount"			=>	$adj,
										    "item_map"          =>  ( $item_map && $total_expense_acct > 1 ? 1 : NULL ),
											"type"				=>  'AD'
										) );

									} elseif ( $account_hash != $this->current_expense_line['account_hash'] ) { # If we have updated and bill and replaced an exp dist with a new account

										array_push( $account_exec, array(
    										"audit_id" 			=> 	$audit_id,
											"account_hash"		=>	$account_hash,
											"amount"			=>	$amount,
                                            "item_map"          =>  ( $item_map && $total_expense_acct > 1 ? 1 : NULL ),
											"type"				=>  "AD"
										) );
										array_push( $account_exec, array(
    										"audit_id" 			=> 	$audit_id,
											"account_hash"		=>	$this->current_expense_line['account_hash'],
											"amount"			=>	bcmul($this->current_expense_line['amount'], -1, 2),
                                            "item_map"          =>  ( $item_map && $total_expense_acct > 1 ? 1 : NULL ),
											"type"				=>  'AD'
										) );
									}
								}
							}

							$expense_log[] = $expense_id;

						} else {

							$expense_hash = rand_hash('vendor_payable_expenses', 'expense_hash');

							if ( ! $this->db->query("INSERT INTO vendor_payable_expenses
            										 VALUES
            										 (
                                                         NULL,
                                                         UNIX_TIMESTAMP(),
                										 '$expense_hash',
                										 '$invoice_hash',
                										 '$account_hash', " .
                										 ( $proposal_hash ?
                    										 "'$proposal_hash'" : "NULL"
                										 ) . ",
                										 '$amount',
                										 '" . addslashes($memo) . "'
                									 )")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                            }

							array_push( $account_exec, array(
    							"audit_id" 			=> 	$audit_id,
								"account_hash"		=>	$account_hash,
								"amount"			=>	$amount,
                                "item_map"          =>  ( $item_map && $total_expense_acct > 1 ? 1 : NULL ),
								"type"				=>  'AP'
							) );

							$expense_log[] = $expense_hash;
						}

					} elseif ( ( ! $account_name || ! $amount ) && ! $this->content['error'] && $expense_id ) {

						if ( ! $this->fetch_expense_line($expense_id, $invoice_hash) )
							return $this->__trigger_error("System error when attempting to fetch expense distribution record. Please reload window and try again. <!-- Tried fetching expense distribution [ $expense_id $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						if ( ! $this->db->query("DELETE FROM vendor_payable_expenses
            									 WHERE expense_hash = '{$this->expense_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						array_push( $account_exec, array(
    						"audit_id" 			=> 	$audit_id,
							"account_hash"		=>	$this->current_expense_line['account_hash'],
							"amount"			=>	bcmul($this->current_expense_line['amount'], -1, 2),
                            "item_map"          =>  ( ( $item_map && $total_expense_acct > 1) || ( $item_map && $item_map_wip_acct ) ? 1 : NULL ),
							"type"				=> 'AD'
						) );
					}
				}

				if ( $this->content['error'] )
					return $this->__trigger_error("Errors were found while trying to process your invoice.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

				$po_overpay_ok = $_POST['po_overpay_ok'];
				if ( $type == 'I' && $po_hash && ! $po_overpay_ok ) { # Lets see if we have received more than the original PO

					$result = $this->db->query("SELECT IF( ISNULL(t1.currency), t1.order_amount, ROUND( currency_exchange(t1.order_amount, t1.exchange_rate, 0), 2) ) AS order_amount
												FROM purchase_order t1
												WHERE t1.po_hash = '$po_hash'");
					$order_amount = $this->db->result($result, 0, 'order_amount');

					$result = $this->db->query("SELECT SUM( IF( ISNULL(t1.currency), t1.amount, ROUND( currency_exchange(t1.amount, t1.exchange_rate, 0), 2) ) ) AS Total
											    FROM vendor_payables t1
												WHERE " .
                            					( $this->invoice_hash ?
                                					"t1.invoice_hash != '{$this->invoice_hash}' AND " : NULL
                            					) . "t1.po_hash = '$po_hash' AND t1.type = 'I' AND t1.deleted = 0");
					$total_invoice_amt = $this->db->result($result, 0, 'Total');
					$total_invoice_amt = bcadd($total_invoice_amt, $invoice_amount, 2);

					if ( bccomp($total_invoice_amt, $order_amount) )
						return $this->__trigger_error("Total payables entered against this purchase order (\$" . number_format($total_invoice_amt, 2) . ") differ from that of the<br />original PO amount (\$" . number_format($order_amount, 2) . ")." .
						( $this->p->ck(get_class($this), 'E', 'po_diff') ?
							"<br /><br />" .
							$this->form->button(
    							"value=Proceed Anyway",
    							"onClick=\$('invoice_btn').disabled=1;submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'btn=save', 'po_overpay_ok=1'" . ( $_POST['invoice_dup_confirm'] ? ", 'invoice_dup_confirm={$_POST['invoice_dup_confirm']}'" : NULL ) . ");"
							) : "<br /><br />Your permissions prohibit you from continuing further."
						), E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

				}

                if ( bccomp($total, $invoice_amount, 2) ) {

                    if ( $type == 'I' )
                        $f = "Your invoice amount of \$" . number_format($invoice_amount, 2) . " does not equal the total expenses of \$" . number_format($total, 2) . " below. Please ensure that the total expenses equals the total invoice amount.";
                    elseif ( $type == 'D' )
                        $f = "Your deposit amount of \$" . number_format($invoice_amount, 2) . " does not equal the total expenses of \$" . number_format($total, 2) . " below. Please ensure that the total expenses equals the total deposit amount.";
                    elseif ( $type == 'C' )
                        $f = "Your credit amount of \$" . number_format($invoice_amount, 2) . " does not equal the total expenses of \$" . number_format($total, 2) . " below. Please ensure that the total expenses equals the total credit amount.";
                    elseif ( $type == 'R' )
                        $f = "Your refund amount of \$" . number_format($invoice_amount, 2) . " does not equal the total expenses of \$" . number_format($total, 2) . " below. Please ensure that the total expenses equals the total refund amount.";

                    $this->set_error("err3{$this->popup_id}");
                    $this->set_error("err3a{$this->popup_id}");
                    return $this->__trigger_error($f, E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( $invoice_currency )
                    $invoice_amount = currency_exchange($invoice_amount, $currency_rate, 1);

				if ( ( $this->invoice_hash && $this->current_invoice['receipt_date'] != $receipt_date ) || ( ! $this->invoice_hash && $accounting->period_ck($receipt_date) ) ) {

					if ( $accounting->period_ck($receipt_date) )
						return $this->__trigger_error($accounting->closed_err, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				if ( $type == 'I' && $po_hash ) { # See if it has been received in full

					$result = $this->db->query("SELECT
                                					SUM( t2.amount ) AS total_payable,
                                					t1.order_amount
												FROM purchase_order t1
												LEFT JOIN vendor_payables t2 ON t2.po_hash = t1.po_hash AND t2.deleted = 0
												WHERE t1.po_hash = '$po_hash'
												GROUP BY t1.po_hash");
					$row = $this->db->fetch_assoc($result);
					if ( bccomp($row['total_payable'], 0, 2) == 1 && bccomp($row['order_amount'], 0, 2) == 1 ) {

						if ( ! bccomp($row['total_payable'], $row['order_amount'], 2) )
							$received_in_full = 1;
						else
							$received_in_full = 0;
					} else
						$received_in_full = 0;
				} else
					$received_in_full = 1;

                if ( $item_map && ! $po_hash )
                    unset($item_map);

                $sql2 = array();
				if ( $this->invoice_hash ) {

					//array_push( $sql2, "t1.po_hash = '$po_hash'");
					array_push( $sql2, "t1.vendor_hash = '$vendor_hash'");
					array_push( $sql2, "t1.invoice_no = '$invoice_no'");
					array_push( $sql2, "t1.invoice_date = '$invoice_date'");
					array_push( $sql2, "t1.receipt_date = '$receipt_date'");
					array_push( $sql2, "t1.due_date = '$due_date'");
					array_push( $sql2, "t1.notes = '" . addslashes($notes) . "'");
					array_push( $sql2, "t1.exclude_credit = '$exclude_credit'");
					array_push( $sql2, "t1.hold = '$hold'");
                    array_push( $sql2, "t1.item_map = '$item_map'");

                    $accounting->start_transaction( array(
	                    'ap_invoice_hash'   =>  $this->invoice_hash,
	                    'vendor_hash'       =>  $this->current_invoice['vendor_hash'],
                        'proposal_hash'     =>  ( $this->current_invoice['invoice_proposal'] ? $this->current_invoice['invoice_proposal'] : NULL ),
                        'currency'          =>  $invoice_currency,
                        'exchange_rate'     =>  $currency_rate
                    ) );

					if ( bccomp($invoice_amount, $this->current_invoice['amount'], 2) ) {

						array_push( $sql2, "t1.amount = '$invoice_amount'");

						if ( $this->current_invoice['type'] == 'D' ) {

							$debit_balance_adj = bcsub($this->current_invoice['amount'], $this->current_invoice['debit_balance'], 2);
							array_push( $sql2, "t1.debit_balance = '" . bcsub($invoice_amount, $debit_balance_adj, 2) . "'");
						}

						$balance_adj = bcsub($this->current_invoice['amount'], $this->current_invoice['balance'], 2);
						array_push( $sql2, "t1.balance = '" . bcsub($invoice_amount, $balance_adj, 2) . "'");
						if ( bccomp( bcsub($invoice_amount, $balance_adj, 2), 0, 2) == -1 )
                            return $this->__trigger_error("The invoice amount you entered will result in a negative balance. Please check you input and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

						$adj = bcsub($invoice_amount, $this->current_invoice['amount'], 2);
						if ( $this->current_invoice['type'] == 'C' )
                            $adj = bcmul($adj, -1, 2);

						$journal_date = $this->current_invoice['invoice_date'];
						if ( ! $accounting->exec_trans($audit_id, $journal_date, DEFAULT_AP_ACCT, $adj, 'AD', "Invoice adjustment - Invoice No. $invoice_no") )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					}

					$journal_date = $this->current_invoice['invoice_date'];

					if ( ! $this->db->query("UPDATE vendor_payables t1
        									 SET
            									 t1.timestamp = UNIX_TIMESTAMP(),
            									 t1.last_change = '{$this->current_hash}',
            									 t1.complete = 1 " .
            									 ( is_array($sql2) ?
                									 ", " . implode(", ", $sql2) : NULL
            									 ) . "
        									 WHERE t1.invoice_hash = '{$this->invoice_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

				} else {

					if ( $type == 'D' )
						$debit_balance = $invoice_amount;

					if ( ! $this->db->query("INSERT INTO vendor_payables
        									 VALUES
        									 (
            									 NULL,
            									 UNIX_TIMESTAMP(),
            									 '{$this->current_hash}',
            									 '{$this->current_hash}',
            									 '$invoice_hash',
            									 0,
            									 NULL,
            									 '$type',
            									 1,
            									 '$hold',
            									 '$item_map',
            									 0,
            									 '$received_in_full',
            									 0,
            									 " . ( $po_hash ? "'$po_hash'" : "NULL" ) . ",
            									 '$vendor_hash',
            									 '$invoice_no',
            									 '$invoice_date',
            									 '$receipt_date',
            									 '$due_date',
            									 '$invoice_amount',
            									 '$invoice_amount'," .
            									 ( $debit_balance ? "'$debit_balance'" : "0" ) . ",
            									 '" . addslashes($notes) . "'," .
            									 ( $invoice_currency ? "'$invoice_currency'" : "NULL" ) . "," .
            									 ( $invoice_currency ? "'$currency_rate'" : "0" ) . "," .
            									 ( $invoice_currency ? "'$currency_account'" : "NULL" ) . ",
            									 '$exclude_credit'
                                             )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }
                    
					if ( $po_hash ) {

						$r = $this->db->query("SELECT t1.proposal_hash
											   FROM purchase_order t1
											   WHERE t1.po_hash = '$po_hash'");
						$invoice_proposal_hash = $this->db->result($r, 0, 'proposal_hash');
					}

					$accounting->start_transaction( array(
						'proposal_hash'    =>  $invoice_proposal_hash,
						'ap_invoice_hash'  =>  $invoice_hash,
						'vendor_hash'      =>  $vendor_hash,
                        'currency'         =>  $invoice_currency,
                        'exchange_rate'    =>  $currency_rate
					) );

					$journal_date = $invoice_date;

					if ( $type == 'C' ) {

						if ( ! $accounting->exec_trans($audit_id, $journal_date, DEFAULT_AP_ACCT, bcmul($invoice_amount, -1, 2), 'AP', "Vendor credit - $invoice_no") )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					} else {

						if ( ! $accounting->exec_trans($audit_id, $journal_date, DEFAULT_AP_ACCT, $invoice_amount, 'AP', ( $type == 'D' ? "Deposit for PO $invoice_no" : ( $type == 'I' ? "Invoice No. $invoice_no" : "Customer refund for Invoice No: $invoice_no" ) ) ) )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					}
				}

				if ( $item_map == 1 && $po_hash ) {

					$total_item_cost =  $total_wip_rm = $total_wip_add_cost = 0;
					if ( $ap_invoice_item ) {

						if ( ! $this->invoice_hash )
	                        $to_add = $ap_invoice_item;
	                    else {

	                    	if ( ! $this->current_invoice['ap_invoice_item'] && ! is_array($this->current_invoice['ap_invoice_item']) )
	                            $this->current_invoice['ap_invoice_item'] = array();

	                        $to_add = array_diff($ap_invoice_item, $this->current_invoice['ap_invoice_item']);
	                        $to_rm = array_diff($this->current_invoice['ap_invoice_item'], $ap_invoice_item);
	                        for ( $i = 0; $i < count($this->current_invoice['ap_invoice_item']); $i++ ) {

                                if ( ! in_array($this->current_invoice['ap_invoice_item'][$i], $to_rm) )
                                    $total_item_cost = bcadd($total_item_cost, $this->fetch_item_cost($this->current_invoice['ap_invoice_item'][$i]), 2);
	                        }
	                    }

						if ( $to_add ) {

							foreach ( $to_add as $n => $hash ) {

								if ( isset($item_w[ $hash ]) ) {

	                                if ( ! $this->db->query("UPDATE work_order_items t1
        	                                                 SET t1.ap_invoice_hash = '$invoice_hash'
        	                                                 WHERE t1.item_hash = '$hash'")
                                    ) {

                                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                                    }

	                                $r = $this->db->query("SELECT t1.item_descr
	                                                       FROM work_order_items t1
	                                                       WHERE t1.item_hash = '$hash'");

								} else {

		                            if ( ! $this->db->query("UPDATE line_items t1
        		                                             SET t1.ap_invoice_hash = '$invoice_hash'
        		                                             WHERE t1.item_hash = '$hash'")
                                    ) {

                                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                                    }

		                            $r = $this->db->query("SELECT t1.item_descr
		                                                   FROM line_items t1
		                                                   WHERE t1.item_hash = '$hash'");
								}

		                        $item_cost = $this->fetch_item_cost($hash);
		                        if ( $this->invoice_hash && $item_map && ! $this->current_invoice['item_map'] )
                                    $total_wip_add_cost = bcadd($total_wip_add_cost, $item_cost, 2);

                                $total_item_cost = bcadd($total_item_cost, $item_cost, 2);
		                        array_push( $account_exec, array(
    		                        "audit_id"          =>  $audit_id,
		                            "account_hash"      =>  $item_map_wip_acct,
		                            "amount"            =>  $item_cost,
		                            "type"              =>  ( $this->invoice_hash ? 'AD' : 'AP' ),
		                            "item_map"          =>  1,
		                            "item_memo"         =>  ( strlen( $this->db->result($r, 0, 'item_descr') ) > 64 ? substr( $this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') ),
		                            "cond"              =>  1,
		                            "acct_ops"          =>  array(
    		                            'item_hash'   =>  $hash,
		                                'memo'        =>  ( strlen( $this->db->result($r, 0, 'item_descr') ) > 64 ? substr( $this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') )
    		                        )
		                        ) );
							}

							if ( $this->invoice_hash && bccomp($total_wip_add_cost, 0, 2) && bccomp($this->current_invoice['expense_items'][0]['amount'], 0, 2) )
			                    array_push( $account_exec, array(
    			                    "audit_id"          =>  $audit_id,
			                        "account_hash"      =>  $this->current_invoice['expense_items'][0]['account_hash'],
			                        "amount"            =>  bcmul($this->current_invoice['expense_items'][0]['amount'], -1, 2),
			                        "type"              =>  'AD',
			                        "item_map"          =>  1
			                    ) );

						}

	                    if ( $to_rm ) {

	                        foreach ( $to_rm as $n => $hash ) {

	                            if ( isset($item_w[$hash]) ) {

	                                if ( ! $this->db->query("UPDATE work_order_items t1
        	                                                 SET t1.ap_invoice_hash = NULL
        	                                                 WHERE item_hash = '$hash'")
                                    ) {

                                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                                    }

                                    $r = $this->db->query("SELECT t1.item_descr
                                                           FROM work_order_items t1
                                                           WHERE t1.item_hash = '$hash'");
	                            } else {

	                                if ( ! $this->db->query("UPDATE line_items t1
        	                                                 SET t1.ap_invoice_hash = NULL
        	                                                 WHERE t1.item_hash = '$hash'")
                                    ) {

                                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                                    }

                                    $r = $this->db->query("SELECT t1.item_descr
                                                           FROM line_items t1
                                                           WHERE t1.item_hash = '$hash'");
	                            }

                                $item_cost = $this->fetch_item_cost($hash);

                                array_push( $account_exec, array(
                                    "audit_id"          =>  $audit_id,
                                    "account_hash"      =>  $item_map_wip_acct,
                                    "amount"            =>  bcmul($item_cost, -1, 2),
                                    "type"              =>  ( $this->invoice_hash ? 'AD' : 'AP' ),
                                    "item_map"          =>  1,
                                    "cond"              =>  3,
                                    "acct_ops"          =>  array(
                                        'item_hash'   =>  $hash,
                                        'memo'        =>  'AP item reversal : ' . ( strlen( $this->db->result($r, 0, 'item_descr') ) > 64 ? substr($this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') )
                                    )
                                ) );
	                        }
	                    }
					}

                    if ( $this->invoice_hash && $this->current_invoice['expense_items'][0]['account_hash'] != $item_map_wip_acct ) { # We've changed the exp dist acct so reverse everything

                    	$item_cost = array();
                        for ( $i = 0; $i < count($this->current_invoice['ap_invoice_item']); $i++ ) {

                        	if ( ! is_array($to_rm) || ( is_array($to_rm) && ! in_array($this->current_invoice['ap_invoice_item'][$i], $to_rm) ) ) {

		                        $item_cost[ $this->current_invoice['ap_invoice_item'][$i] ] = $this->fetch_item_cost($this->current_invoice['ap_invoice_item'][$i]);

                                $r = $this->db->query("SELECT t1.item_descr
                                                       FROM work_order_items t1
                                                       WHERE t1.item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'");
		                        if ( ! $this->db->num_rows($r) ) {

                                    $r = $this->db->query("SELECT t1.item_descr
                                                           FROM line_items t1
                                                           WHERE t1.item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'");
		                        }

                                $total_item_cost = bcadd($total_item_cost, $item_cost, 2);
		                        array_push( $account_exec, array(
    		                        "audit_id"          =>  $audit_id,
		                            "account_hash"      =>  $this->current_invoice['expense_items'][0]['account_hash'],
		                            "amount"            =>  bcmul($item_cost[$this->current_invoice['ap_invoice_item'][$i]], -1, 2),
		                            "type"              =>  'AD',
		                            "item_map"          =>  1,
		                            "item_memo"         =>  ( strlen($this->db->result($r, 0, 'item_descr') ) > 64 ? substr($this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') ),
                                    "cond"              =>  4,
		                            "acct_ops"          =>  array(
    		                            'item_hash'   =>  $this->current_invoice['ap_invoice_item'][$i],
		                                'memo'        =>  'AP item reversal : ' . ( strlen($this->db->result($r, 0, 'item_descr') ) > 64 ? substr($this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') )
    		                        )
                                ) );
                        	}
                        }

                        for ( $i = 0; $i < count($this->current_invoice['ap_invoice_item']); $i++ ) {

                            $r = $this->db->query("SELECT t1.item_descr
                                                   FROM work_order_items t1
                                                   WHERE t1.item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'");
                            if ( ! $this->db->num_rows($r) ) {

                                $r = $this->db->query("SELECT t1.item_descr
                                                       FROM line_items t1
                                                       WHERE t1.item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'");
                            }

                            $total_item_cost = bcadd($total_item_cost, $item_cost, 2);
                            array_push( $account_exec, array(
                                "audit_id"          =>  $audit_id,
                                "account_hash"      =>  $item_map_wip_acct,
                                "amount"            =>  $item_cost[ $this->current_invoice['ap_invoice_item'][$i] ],
                                "type"              =>  'AD',
                                "item_map"          =>  1,
                                "cond"              =>  5,
                                "item_memo"         =>  ( strlen($this->db->result($r, 0, 'item_descr') ) > 64 ? substr( $this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') ),
                                "acct_ops"          =>  array(
                                    'item_hash'   =>  $this->current_invoice['ap_invoice_item'][$i],
                                    'memo'        =>  ( strlen( $this->db->result($r, 0, 'item_descr') ) > 64 ? substr($this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') )
                                )
                            ) );
                        }
                    }

                    if ( $invoice_currency && $currency_rate ) {

                        $total_item_cost = currency_exchange($total_item_cost, $currency_rate);
                        $invoice_amount = currency_exchange($invoice_amount, $currency_rate);
                        $item_map_wip_amt = currency_exchange($item_map_wip_amt, $currency_rate);
                    }

                    if ( bccomp($total_item_cost, $item_map_wip_amt, 2) )
                    	return $this->__trigger_error("The total cost of the mapped line items (\$" . number_format($total_item_cost, 2) . ") does not equal the total WIP expense amount (\$" . number_format($item_map_wip_amt, 2) . ") entered in the first expense line. Please make the necessary adjustments to any line items with cost discrepancies.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

				} elseif ( ! $item_map && $this->current_invoice['ap_invoice_item'] ) {

                    if ( ! $this->db->query("UPDATE line_items t1
                                             SET t1.ap_invoice_hash = NULL
                                             WHERE t1.ap_invoice_hash = '{$this->invoice_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

                    if ( ! $this->db->query("UPDATE work_order_items t1
                                             SET t1.ap_invoice_hash = NULL
                                             WHERE t1.ap_invoice_hash = '{$this->invoice_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

                    $total_wip_cost = 0;
                    for ( $i = 0; $i < count($this->current_invoice['ap_invoice_item']); $i++ ) {

	                    $r = $this->db->query("SELECT t1.item_descr
	                                           FROM work_order_items t1
	                                           WHERE t1.item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'");
	                    if ( ! $this->db->num_rows($r) ) {

	                        $r = $this->db->query("SELECT t1.item_descr
	                                               FROM line_items t1
	                                               WHERE t1.item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'");
	                    }

                        $item_cost = $this->fetch_item_cost($this->current_invoice['ap_invoice_item'][$i]);

                        $total_wip_cost = bcadd($total_wip_cost, $item_cost, 2);
                        array_push( $account_exec, array(
                            "audit_id"          =>  $audit_id,
                            "account_hash"      =>  $this->current_invoice['expense_items'][0]['account_hash'],
                            "amount"            =>  bcmul($item_cost, -1, 2),
                            "type"              =>  ( $this->invoice_hash ? 'AD' : 'AP' ),
                            "item_map"          =>  1,
                            "item_memo"         =>  ( strlen($this->db->result($r, 0, 'item_descr') ) > 64 ? substr($this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') ),
                            "acct_ops"          =>  array(
                                'item_hash'   =>  $this->current_invoice['ap_invoice_item'][$i],
                                'memo'        =>  'AP item reversal : ' . ( strlen($this->db->result($r, 0, 'item_descr') ) > 64 ? substr($this->db->result($r, 0, 'item_descr'), 0, 60) . "..." : $this->db->result($r, 0, 'item_descr') )
                            )
                        ) );
                    }

                    array_push( $account_exec, array(
                        "audit_id"          =>  $audit_id,
                        "account_hash"      =>  $this->current_invoice['expense_items'][0]['account_hash'],
                        "amount"            =>  $total_wip_cost,
                        "type"              =>  'AD'
                    ) );

                }

                // If we edit an existing invoice and toggle the item mapping on/off
                //if ($this->invoice_hash && $item_map != $this->current_invoice['item_map']) {
                //    if ($accounting->exec_trans($audit_id, $journal_date, $this->current_invoice['expense_items'][0]['account_hash'], ($item_map == 1 ? bcmul($this->current_invoice['amount'], -1, 2) : $this->current_invoice['amount']), 'AD') == false)
                //       return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                //}

				for ( $i = 0; $i < count($update); $i++ ) {

					if ( ! $this->db->query($update[$i]) ) {

						return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					}
				}

				for ( $i = 0; $i < count($account_exec); $i++ ) {

					if ( $type == 'R' || ! $item_map || ( $item_map == 1 && $account_exec[$i]['item_map'] ) ) {

						if ( ! $accounting->fetch_account_record($account_exec[$i]['account_hash']) )
							return $this->__trigger_error("System error when attempting to fetch chart of account record. Please reload window and try again. <!-- Tried fetching account [ {$account_exec[$i]['account_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						if ( $type != 'C' && $accounting->current_account['account_action'] == 'CR' )
							$account_exec[$i]['amount'] = bcmul($account_exec[$i]['amount'], -1, 2);
						elseif ( $type == 'C' && $accounting->current_account['account_action'] == 'DR' )
							$account_exec[$i]['amount'] = bcmul($account_exec[$i]['amount'], -1, 2);

                        if ( $account_exec[$i]['acct_ops'] && is_array($account_exec[$i]['acct_ops']) ) {

                        	foreach ( $account_exec[$i]['acct_ops'] as $acct_key => $acct_val )
	                        	$accounting->setTransIndex( array(
	                            	$acct_key  =>  $acct_val
	                        	) );
                        }

						if ( ! $accounting->exec_trans($account_exec[$i]['audit_id'], $journal_date, $account_exec[$i]['account_hash'], $account_exec[$i]['amount'], $account_exec[$i]['type'], ( $account_exec[$i]['acct_ops']['memo'] ? $account_exec[$i]['acct_ops']['memo'] : $account_exec[$i]['memo'] ) ) )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					}
				}

				if ( ! $accounting->end_transaction() )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$this->db->end_transaction();

				if ( $from )
					return $invoice_hash;

				$this->content['action'] = 'close';
				if ( $type == 'C' )
					$this->content['page_feedback'] = "Your vendor credit has been saved.";
				elseif ( $type == 'D' )
					$this->content['page_feedback'] = "Your vendor deposit has been saved.";
				elseif ( $type == 'R' )
					$this->content['page_feedback'] = "Your customer refund has been saved.";
				else
					$this->content['page_feedback'] = "Your vendor invoice has been saved.";

				if ( ( $from_class == 'payables' || ! $from_class ) && ( $save_option == 'close' || ! $save_option ) )
					$this->content['jscript_action'] = "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search=$active_search');";
				elseif ( ( $from_class == 'payables' || ! $from_class ) && ( $save_option == 'add_new' || $save_option == 'same_vendor' ) ) {

					$this->content['action'] = 'continue';
					foreach( $_POST as $key => $val ) {

						if ( preg_match('/account_hash_/', $key) || preg_match('/account_name/', $key) || preg_match('/amount_/', $key) || preg_match('/proposal_hash_/', $key) || preg_match('/proposal_no_/', $key) || preg_match('/memo_/', $key) )
							$this->content['jscript'][] = "clear_values('$key');";
					}

					$this->content['jscript'][] = "clear_values('po_no', 'invoice_amount', 'invoice_no', 'notes', 'hold');";
					$this->content['jscript'][] = "\$('item_list_tr').setStyle({'display':'none'});\$('item_map_cntrl').innerHTML='Map';";
					$this->content['jscript'][] = "\$('total_amount_holder').innerHTML = '';";
					$this->content['jscript'][] = "\$('view_po_holder').innerHTML='';";
					$this->content['jscript'][] = "\$('show_item_holder').innerHTML='';";
					$this->content['jscript'][] = "\$('deposit_amt_holder').setStyle({'display':'none'});";
					if ( $save_option == 'add_new' ) {

						$this->content['jscript'][] = "clear_values('po_vendor');";
						$this->content['jscript'][] = "\$('vendor_note_holder').innerHTML='';";
					}

					$this->content['submit_btn'] = $submit_btn;
					$this->content['jscript'][] = "window.setTimeout('agent.call(\'payables\', \'sf_loadcontent\', \'cf_loadcontent\', \'showall\', \'p=$p\', \'order=$order\', \'order_dir=$order_dir\', \'active_search=$active_search\');', 1000);";

				} elseif ( $from_class == 'proposals' && $from_section == 'memo_cost' )
				    $this->content['jscript_action'] = "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'memo_costing', 'proposal_hash=$proposal_hash', 'p=$p', 'order=$order', 'order_dir=$order_dir');";
				else
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'proposal_hash=$parent_proposal_hash', 'otf=1', 'p=$p', 'order=$order', 'order_dir=$order_dir');window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash=$parent_proposal_hash\', \'otf=1\');', 1500)");

				return;

			} else {

				if ( ! $vendor_name ) $this->set_error("err1{$this->popup_id}");
				if ( ! $invoice_amount || bccomp($invoice_amount, 0, 2) != 1 ) $this->set_error("err3{$this->popup_id}");
				if ( ! $invoice_no ) $this->set_error("err4{$this->popup_id}");
				if ( ! $invoice_date ) $this->set_error("err5{$this->popup_id}");
				if ( ! $receipt_date ) $this->set_error("err6{$this->popup_id}");
				if ( ! $due_date ) $this->set_error("err7{$this->popup_id}");

				return $this->__trigger_error("You left some required fields empty! Please check that you have completed the indicated fields below.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
			}

		} elseif ( $btn == 'rm' ) {

			if ( ! $this->fetch_invoice_record($invoice_hash) )
               	return $this->__trigger_error("System error when attempting to fetch invoice record. Please reload window and try again. <!-- Tried fetching invoice [ $invoice_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			if ( $this->current_invoice['deleted'] )
                return $this->__trigger_error("This payable has already been deleted and therefore cannot be changed.", E_USER_NOTICE, __FILE__, __LINE__, 1);

            $posting_date = $this->current_invoice['invoice_date'];

            if ( $this->current_invoice['payments'] ) {

            	$active_payments = false;
				for ( $i = 0; $i < count($this->current_invoice['payments']); $i++ ) {

					if ( ! $this->current_invoice['payments'][$i]['void'] ) {
						$active_payments = true;
						break;
					}
				}
				if ( $active_payments )
					return $this->__trigger_error("This invoice has payments posted against it and cannot be deleted.", E_USER_NOTICE, __FILE__, __LINE__, 1);
            }

			$accounting = new accounting($this->current_hash);
			$audit_id = $accounting->audit_id();

			if ( ! $this->db->start_transaction() )
				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			$accounting->start_transaction( array(
                'proposal_hash'     =>  $this->current_invoice['invoice_proposal'],
                'ap_invoice_hash'   =>  $this->invoice_hash,
			    'vendor_hash'       =>  $this->current_invoice['vendor_hash']
			) );

			if ( $this->current_invoice['type'] == 'C' )
                $this->current_invoice['amount'] = bcmul($this->current_invoice['amount'], -1, 2);

			if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, bcmul($this->current_invoice['amount'], -1, 2), 'AD', "Invoice delete : {$this->current_invoice['invoice_no']}.") )
				return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

			$ap_invoice_total = 0;
			if ( $this->current_invoice['item_map'] && $this->current_invoice['type'] != 'R' ) {

                if ( ! $accounting->fetch_account_record($this->current_invoice['expense_items'][0]['account_hash']) )
                    return $this->__trigger_error("System error encountered when attempting to lookup chart of account. Please reload window and try again. <!-- Tried fetching account [ {$this->current_invoice['expense_items'][0]['account_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

                for ( $i = 0; $i < count($this->current_invoice['ap_invoice_item']); $i++ ) {

                	$item_cost = $this->fetch_item_cost($this->current_invoice['ap_invoice_item'][$i]);
                    if ( $accounting->current_account['account_action'] == 'CR' )
                        $item_cost = bcmul($item_cost, -1, 2);

                    $r = $this->db->query("SELECT
                                               CASE
											   WHEN ( ! ISNULL( t1.obj_id ) ) THEN
											       'work_order_items' ELSE
											       NULL
											   END AS update_table,
                                               CASE
                                               WHEN LENGTH( t1.item_descr ) > 64 THEN
                                                   CONCAT( SUBSTRING( t1.item_descr, 1, 60), '...') ELSE
                                                   t1.item_descr
                                               END AS item_descr
                                           FROM work_order_items t1
                                           WHERE t1.item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'
                                           UNION
                                           SELECT
                                               CASE
                                               WHEN ( ! ISNULL( t1.obj_id ) ) THEN
                                                   'line_items' ELSE
                                                   NULL
                                               END AS update_table,
                                               CASE
                                               WHEN LENGTH( t1.item_descr ) > 64 THEN
                                                   CONCAT( SUBSTRING( t1.item_descr, 1, 60), '...') ELSE
                                                   t1.item_descr
                                               END AS item_descr
                                           FROM line_items t1
                                           WHERE t1.item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'");
                    $item_descr = $this->db->result($r, 0, 'item_descr');
                    $update_table = $this->db->result($r, 0, 'update_table');

                    if ( ! $update_table )
                        return $this->__trigger_error("Failed to lookup item mapping for one or more payable items.", E_USER_ERROR, __FILE__, __LINE__, 1);

                    $this->db->query("UPDATE $update_table
                                      SET ap_invoice_hash = NULL
                                      WHERE item_hash = '{$this->current_invoice['ap_invoice_item'][$i]}'");
                    if ( $this->db->db_error )
                        return $this->__trigger_error("Failed to update lookup item mapping for one or more payable items.", E_USER_ERROR, __FILE__, __LINE__, 1);

                    if ( $this->current_invoice['type'] == 'C' )
                        $item_cost = bcmul($item_cost, -1, 2);

                    $accounting->setTransIndex( array(
                        'item_hash' =>  $this->current_invoice['ap_invoice_item'][$i]
                    ) );

                    if ( ! $accounting->exec_trans($audit_id, $posting_date, $accounting->current_account['account_hash'], bcmul($item_cost, -1, 2), 'AD', $item_descr) )
                        return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

                }

                for ( $i = 1; $i < count($this->current_invoice['expense_items']); $i++ ) {

                    if ( ! $accounting->fetch_account_record($this->current_invoice['expense_items'][$i]['account_hash']) )
                        return $this->__trigger_error("System error encountered when attempting to lookup chart of account. Please reload window and try again. <!-- Tried fetching account [ {$this->current_invoice['expense_items'][$i]['account_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

                    if ( $accounting->current_account['account_action'] == 'CR' )
                        $this->current_invoice['expense_items'][$i]['amount'] = bcmul($this->current_invoice['expense_items'][$i]['amount'], -1, 2);

                    if ( $this->current_invoice['type'] == 'C' )
                        $this->current_invoice['expense_items'][$i]['amount'] = bcmul($this->current_invoice['expense_items'][$i]['amount'], -1, 2);

                    if ( ! $accounting->exec_trans($audit_id, $posting_date, $this->current_invoice['expense_items'][$i]['account_hash'], bcmul($this->current_invoice['expense_items'][$i]['amount'], -1, 2), 'AD', "Invoice delete : {$this->current_invoice['invoice_no']}.") )
                        return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
                }

			} else {

				for ( $i = 0; $i < count($this->current_invoice['expense_items']); $i++ ) {

					if ( ! $accounting->fetch_account_record($this->current_invoice['expense_items'][$i]['account_hash']) )
						return $this->__trigger_error("System error encountered when attempting to lookup chart of account. Please reload window and try again. <!-- Tried fetching account [ {$this->current_invoice['expense_items'][$i]['account_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

					if ( $accounting->current_account['account_action'] == 'CR' )
						$this->current_invoice['expense_items'][$i]['amount'] = bcmul($this->current_invoice['expense_items'][$i]['amount'], -1, 2);

                    if ( $this->current_invoice['type'] == 'C' )
                        $this->current_invoice['expense_items'][$i]['amount'] = bcmul($this->current_invoice['expense_items'][$i]['amount'], -1, 2);

					if ( ! $accounting->exec_trans($audit_id, $posting_date, $this->current_invoice['expense_items'][$i]['account_hash'], bcmul($this->current_invoice['expense_items'][$i]['amount'], -1, 2), 'AD', "Invoice delete : {$this->current_invoice['invoice_no']}.") )
						return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
				}
			}

			if ( ! $this->db->query("UPDATE vendor_payables t1
									 SET
										 t1.timestamp = UNIX_TIMESTAMP(),
										 t1.last_change = '{$this->current_hash}',
										 t1.deleted = 1,
										 t1.deleted_date = CURDATE()
									 WHERE t1.invoice_hash = '{$this->invoice_hash}'")
			) {

				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
			}

			if ( ! $accounting->end_transaction() )
				return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

			$this->db->end_transaction();

			$this->content['action'] = 'close';
			$this->content['page_feedback'] = "Your " . ( $this->current_invoice['type'] == 'R' ? "customer refund " : "vendor invoice " ) . "has been deleted.";

			if ($from_class == 'payables')
				$this->content['jscript_action'] = "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search=$active_search');";
			else
				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'proposal_hash=$parent_proposal_hash', 'otf=1', 'p=$p', 'order=$order', 'order_dir=$order_dir');window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash=$parent_proposal_hash\', \'otf=1\');', 1500)");

			return;

		} elseif ( $btn == 'payqueue' ) { # Submit to pay queue action

			if ( $_POST['integrate'] ) { # Set flush to 2 to return a string from trigger error if call originated from integration, otherwise 1

				$flush = 2;
				$integrate = true;
				$check_no = $_POST['check_no'];
			} else
				$flush = 1;

			$payment_account_hash = $_POST['payment_account_hash'];
			$invoice_hash = $_POST['invoice_hash'];
			$pay_amount = $_POST['pay_amount'];
			$posting_date = $_POST['posting_date'];
            $check_action = $_POST['check_action'];
            $check_input = $_POST['check_input'];
			$vendor_discounts = $_POST['discounts_taken'];
			$vendor_deposits = $_POST['vendor_deposits'];
			$vendor_credits = $_POST['vendor_credits'];
			$p = $_POST['p'];
			$order = $_POST['order'];
			$order_dir = $_POST['order_dir'];
			$jscript_action = $_POST['jscript_action'];
			$submit_btn = 'pay_btn';

			$accounting = new accounting($this->current_hash);

			list($y, $m, $d) = explode('-', $posting_date); # Check posting date
			if ( ! $posting_date || ! checkdate($m, $d, $y) || $accounting->period_ck($posting_date) )
				return $this->__trigger_error( ( $accounting->period_ck($posting_date) ? $accounting->closed_err : "Please enter a valid posted date for these payables to be posted to." ), E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

			if ( ! $accounting->valid_account(DEFAULT_AP_ACCT) ) # Valid AP account
                return $this->__trigger_error("Error encountered during account validation. Please make sure default AP account has been assigned to a valid chart of account. <!-- Tried fetching account [ " . DEFAULT_AP_ACCT . " ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            if ( ! $accounting->valid_account(DEFAULT_VENDOR_DEPOSIT_ACCT) ) # Valid vendor deposit account
                return $this->__trigger_error("Error encountered during account validation. Please make sure default vendor deposit account has been assigned to a valid chart of account. <!-- Tried fetching account [ " . DEFAULT_VENDOR_DEPOSIT_ACCT . " ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            if ( ! $accounting->valid_account(DEFAULT_DISC_TAKEN_ACCT) ) # Valid discounts taken account
                return $this->__trigger_error("Error encountered during account validation. Please make sure default discounts taken account has been assigned to a valid chart of account. <!-- Tried fetching account [ " . DEFAULT_DISC_TAKEN_ACCT . " ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            $invoices = array();
			for ( $i = 0; $i < count($invoice_hash); $i++ ) {

				if ( $invoice_hash[$i] )
					$invoices[] = $invoice_hash[$i];

			}

			if ( ! $payment_account_hash || ! $invoices ) {

				if ( $integrate ) {

					if ( ! $payment_account_hash )
						return "Payment account hash missing";
					else
						return "No invoice to be paid";
				} else {

					if ( ! $payment_account_hash ) $this->set_error('err1');
					return $this->__trigger_error("Before submitting invoices to the pay queue you must select a payment account and at least one invoice to be paid.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}
			}

			$total_pay_amt = 0;
			$audit_id = $accounting->audit_id( count($invoices) );
			$vendor_hash = $_POST['vendor_hash'];
			$check_vendor = array();
			$handwritten_check = array(); // Array to track handwritten check totals

			for ( $i = 0; $i < count($invoices); $i++ ) { # Check for overused credits first

				if ( $vendor_credits[ $invoices[$i] ] )
			        $credit_tracker[ $vendor_hash[ $invoices[$i] ] ] = bcadd($credit_tracker[ $vendor_hash[ $invoices[$i] ] ], preg_replace('/[^-.0-9]/', "", $vendor_credits[ $invoices[$i] ]), 2);

			    if ( $check_action[ $invoice_hash[$i] ] == 2 ) {
					$check_vendor[ $check_input[ $invoice_hash[$i] ] ][] = $vendor_hash[ $invoices[$i] ];
			    	$handwritten_check[ $check_input[ $invoice_hash[$i] ] ] += preg_replace('/[^-.0-9]/', "", $pay_amount[ $invoices[$i] ]); 
			    }

                if ( $check_action[ $invoice_hash[$i] ] == 2 && ( ! trim( $check_input[ $invoice_hash[$i] ] ) || $this->check_taken( trim( $check_input[ $invoice_hash[$i] ] ), $payment_account_hash) ) ) {

                    if ( ! trim( $check_input[ $invoice_hash[$i] ] ) )
                        $f = "Please check that you have entered a valid check number for any payments indicated as hand written checks.";
                    else
                        $f = "Check number [ {$check_input[ $invoice_hash[$i] ]} ] has already been applied to a payment. Please check that you have entered a valid, unique check number.";

                    return $this->__trigger_error($f, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
                }
			}

			while ( list($vendor, $amount) = each($credit_tracker) ) { # Check for exceeded credits

				$open_credits = $this->fetch_vendor_credits($vendor);
			    if ( bccomp($amount, $open_credits, 2) == 1 ) {

                    $r = $this->db->query("SELECT
                                               t1.vendor_name
                                           FROM vendors t1
                                           WHERE t1.vendor_hash = '$vendor'");

                    return $this->__trigger_error("The total vendor credits you entered (\$" . number_format($amount, 2) . ") exceeds the available credits (\$" . number_format($open_credits, 2) . ") for the vendor '" . stripslashes( $this->db->result($r, 0, 'vendor_name') ) . "'. Please make sure the credits used do not exceed those available.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			    }
			}

			while ( list($_check_no, $_vendors) = each($check_vendor) ) { # Check for single vendor per check

			    if ( count( array_unique($_vendors) ) != 1 )
			        return $this->__trigger_error("Duplicate Check Error! Check #{$_check_no} has been assigned to more than one vendor, which is not permitted. Please check that any handwritten checks are assigned to a single vendor.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			for ( $i = 0; $i < count($invoices); $i++ ) {

				if ( ! $this->db->start_transaction() )
					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( ! $this->fetch_invoice_record($invoices[$i], 1) )
                	return $this->__trigger_error("System error when attempting to fetch invoice record. Please reload window and try again. <!-- Tried fetching invoice [ {$invoices[$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                $amount_to_pay = preg_replace('/[^-.0-9]/', "", $pay_amount[ $invoices[$i] ]);
				if ( bccomp($amount_to_pay, 0, 2) == -1 )
				    return $this->__trigger_error("You're trying to pay a negative amount on your bill! Please try again.", E_USER_NOTICE, __FILE__, __LINE__, $flush, false, $submit_btn);

				$credits_used = $deposits_used = $discounts_used = 0;
				if ( $this->current_invoice['type'] != 'R' ) {

					$credits_used = preg_replace('/[^-.0-9]/', "", $vendor_credits[ $invoices[$i] ]);
					$deposits_used = ( $vendor_deposits[ $invoices[$i] ] ? $vendor_deposits[ $invoices[$i] ] : 0 );
					$discounts_used = preg_replace('/[^-.0-9]/', "", $vendor_discounts[ $invoices[$i] ]);
				}

				$queue_hash = rand_hash('vendor_pay_queue', 'queue_hash');
				$payment_hash = rand_hash('vendor_payment', 'payment_hash');

                $remaining_balance = bcsub($this->current_invoice['balance'], $amount_to_pay, 2);
                if ( $credits_used )
                    $remaining_balance = bcsub($remaining_balance, $credits_used, 2);
                if ( $deposits_used )
                    $remaining_balance = bcsub($remaining_balance, $deposits_used, 2);
                if ( $discounts_used )
                    $remaining_balance = bcsub($remaining_balance, $discounts_used, 2);
                

				if ( bccomp($remaining_balance, 0, 2) == -1 )
    				return $this->__trigger_error("The payment amount on invoice {$this->current_invoice['invoice_no']} will result in a negative balance on the invoice. Please make sure your payment amount does not overpay the invoice.", E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
				
				$discount_hash = DEFAULT_DISC_TAKEN_ACCT;
				
				$r = $this->db->query("SELECT `manual_credit`
									   FROM `vendors`
									   WHERE `vendor_hash` = '".$vendor_hash[ $invoices[$i] ]."'");
				$manCredit = $this->db->result($r, 0, 'manual_credit');
				if($manCredit){
						$r = $this->db->query("SELECT `manual_credit_amount`
										   FROM `vendors`
										   WHERE `vendor_hash` = '".$vendor_hash[ $invoices[$i] ]."'");
						$manCreditAmount = $this->db->result($r, 0, 'manual_credit_amount');
						
						$r = $this->db->query("SELECT `manual_credit_account_hash`
											   FROM `vendors`
											   WHERE `vendor_hash` = '".$vendor_hash[ $invoices[$i] ]."'");
						$manCreditAccountHash = $this->db->result($r, 0, 'manual_credit_account_hash');
						
						$discount_hash = $manCreditAccountHash;
						
						//return $this->__trigger_error($manCreditAccountHash, E_USER_ERROR, __FILE__, __LINE__, $flush, false, $submit_btn);
						
						if ( ! $accounting->exec_trans($audit_id, $posting_date, $manCreditAccountHash, (($manCreditAmount / 100) * $this->invoice_info[$i]['amount']), 'CD') )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
						if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, (($manCreditAmount / 100) * $this->invoice_info[$i]['amount']), 'AP') )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
						
				}
				

				if ( ! $this->db->query("UPDATE vendor_payables t1
										  SET
											  t1.timestamp = UNIX_TIMESTAMP(),
											  t1.last_change = '{$this->current_hash}'," .
											  ( bccomp($remaining_balance, 0, 2) != 1 ?
												  "t1.paid_in_full = 1, " : NULL
											  ) . "
											  t1.pay_flag = 0,
											  t1.balance = '$remaining_balance'
										  WHERE t1.invoice_hash = '{$invoices[$i]}'")
				) {

					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}
				
				$row =  $this->db->query("select type from vendor_payables where invoice_hash = '{$invoices[$i]}' ");
				$type = $this->db->result($row, 0, 'type');
				

				if ( ! $integrate && $check_action[ $invoices[$i] ] == 1 ) { # Skip if payment option is set to 2 "handwritten check"

					if ( ! $this->db->query("INSERT INTO vendor_pay_queue
											 (
												 queue_hash,
												 payment_hash,
												 invoice_hash,
												 amount,
												 deposit_used,
												 deposit_hash,
												 discount_taken,
            									 discount_acct_hash,
            									 credit_used,
            									 account_hash,
            									 expense_account
                                             )
        									 VALUES
        									 (
            									 '$queue_hash',
            									 '$payment_hash',
            									 '{$invoices[$i]}',
            									 '$amount_to_pay',
            									 '$deposits_used',
            									 '" . DEFAULT_VENDOR_DEPOSIT_ACCT . "',
            									 '$discounts_used',
            									 '$discount_hash',
            									 '$credits_used',
            									 '$payment_account_hash',
            									 '" . DEFAULT_AP_ACCT . "'
                                             )")
                    ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

				} elseif ( $integrate || $check_action[ $invoices[$i] ] == 2 && $type != "R") { # Check action == 2 "handwritten check"

					$r = $this->db->query("SELECT
											   CASE WHEN ! ISNULL( remit_name ) OR ! ISNULL( remit_street ) THEN
    											   remit_name ELSE
    											   vendor_name
											   END AS name,
											   CASE WHEN ! ISNULL( remit_name ) OR ! ISNULL( remit_street ) THEN
												   remit_street ELSE
												   street
											   END as street,
											   CASE WHEN ! ISNULL( remit_name ) OR ! ISNULL( remit_street ) THEN
												   remit_city ELSE
												   city
											   END as city,
											   CASE WHEN ! ISNULL( remit_name ) OR ! ISNULL( remit_street ) THEN
												   remit_state ELSE
												   state
											   END as state,
											   CASE WHEN ! ISNULL( remit_name ) OR ! ISNULL( remit_street ) THEN
												   remit_zip ELSE
												   zip
											   END as zip
										   FROM vendors
                        				   WHERE vendor_hash = '{$vendor_hash[ $invoices[$i] ]}'");
					if ( $row = $this->db->fetch_assoc($r) ) {

						$remit_addr =
						( $row['name'] ?
    						stripslashes($row['name']) . "\n" : NULL
    					) .
						( $row['street'] ?
	    					stripslashes($row['street']) . "\n" : NULL
	    				) .
	    				( $row['city'] ?
    	    				stripslashes($row['city']) .
    	    				( $row['state'] ?
        	    				", " : NULL
        	    			) : NULL
        	    		) .
        	    		( $row['state'] ?
            	    		"{$row['state']} " : NULL
            	    	) .
            	    	( $row['zip'] ?
                	    	$row['zip'] : NULL
                	    );

						if ( $check_action[ $invoices[$i] ] == 2 )
	                        $check_no = $check_input[ $invoices[$i] ];

	                        //$handwritten_check[ $check_input[ $invoice_hash[$i] ] ]
						if ($integrate || is_numeric($handwritten_check[$check_no])) {
							if ( ! $this->db->query("INSERT INTO check_register
		        									 VALUES
		        									 (
		            									 NULL,
		            									 UNIX_TIMESTAMP(),
		            									 '{$this->current_hash}',
		            									 '$payment_account_hash',
		            									 '" . DEFAULT_AP_ACCT . "',
		            									 '{$vendor_hash[ $invoices[$i] ]}',
		            									 '" . addslashes($remit_addr) . "',
		            									 '$posting_date',
		            									 '$check_no',
		            									 ".((count($check_vendor[$check_no]) == 1 || $integrate) ? 
		            									 "'Invoice {$this->current_invoice['invoice_no']}'" : "''").",
		            									 '".($integrate ? $amount_to_pay : $handwritten_check[$check_no])."',
		            									 0,
		            									 0,
		            									 NULL,
		            									 0
		                                             )")
		                    ) {
	
		                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
		                    }
		                    
		                    // Remove check total from handwritten_check 
		                    unset($handwritten_check[$check_no]);
						}

					} else{
						return $this->__trigger_error("Unable to lookup vendor remittance address. Please make sure assigned vendor is active and all required address fields have been assigned. <!-- Tried fetching vendor [ {$vendor_hash[ $invoices[$i] ]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					}
				}
				else{
					
					$r = $this->db->query("SELECT
							customer_name AS name, street as street, city as city,
							state as state, zip as zip
							FROM customers
							WHERE customer_hash = '{$vendor_hash[ $invoices[$i] ]}'");
					
							if ( $row = $this->db->fetch_assoc($r) ) {
					
							$remit_addr =
							( $row['name'] ?
							stripslashes($row['name']) . "\n" : NULL
							) .
							( $row['street'] ?
							stripslashes($row['street']) . "\n" : NULL
							) .
							( $row['city'] ?
							stripslashes($row['city']) .
							( $row['state'] ?
							", " : NULL
							) : NULL
							) .
							( $row['state'] ?
								"{$row['state']} " : NULL
							) .
							( $row['zip'] ?
									$row['zip'] : NULL
							);
						$check_no = $check_input[ $invoices[$i] ];
					
					//$handwritten_check[ $check_input[ $invoice_hash[$i] ] ]
						if ( ! $this->db->query("INSERT INTO check_register
								VALUES
								(
								NULL,
								UNIX_TIMESTAMP(),
								'{$this->current_hash}',
								'$payment_account_hash',
								'" . DEFAULT_AP_ACCT . "',
								'{$vendor_hash[ $invoices[$i] ]}',
								'" . addslashes($remit_addr) . "',
								'$posting_date',
								'$check_no',
								".((count($check_vendor[$check_no]) == 1 || $integrate) ?
								"'Invoice {$this->current_invoice['invoice_no']}'" : "''").",
								'".($integrate ? $amount_to_pay : $handwritten_check[$check_no])."',
										0,
										0,
										NULL,
										0
								)")
								) {
					
								return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
								}
						}
				}

				if ( ! $this->db->query("INSERT INTO vendor_payment
        								 (
            								 timestamp,
            								 last_change,
            								 id_hash,
            								 payment_hash,
            								 posting_date,
            								 account_hash " .
                            				 ( $integrate || $check_action[ $invoices[$i] ] == 2 ?
                                				 ",
                                				 deposit_hash,
												 discount_taken,
            									 discount_acct_hash,
            									 credit_used,
                                				 check_no,
                                				 invoice_hash,
                                				 payment_amount" : NULL
                            				 ) . "
                                         )
        								 VALUES
        								 (
	        								 UNIX_TIMESTAMP(),
	        								 '{$this->current_hash}',
	        								 '{$this->current_hash}',
	        								 '$payment_hash',
	        								 '$posting_date',
	        								 '$payment_account_hash' " .
	        								 ( $integrate || $check_action[ $invoices[$i] ] == 2 ?
	            								 ",
	            								  '" . DEFAULT_VENDOR_DEPOSIT_ACCT . "',
            									 '$discounts_used',
            									 '$discount_hash',
            									 '$credits_used',
	            								 '$check_no',
	            								 '{$this->current_invoice['invoice_hash']}',
	            								 '$amount_to_pay'" : NULL
	                						 ) . "
                                         )")
				) {

					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				$accounting->setTransIndex( array(
					'proposal_hash'     =>  $this->current_invoice['invoice_proposal'],
					'ap_invoice_hash'   =>  $this->current_invoice['invoice_hash'],
					'vendor_hash'       =>  $this->current_invoice['vendor_hash'],
					'ap_payment_hash'   =>  $payment_hash
				) );

				if ( ! $accounting->exec_trans($audit_id, $posting_date, $payment_account_hash, bcmul($amount_to_pay, -1, 2), 'CD', ( $this->current_invoice['type'] == 'D' ? "Deposit for PO {$this->current_invoice['invoice_no']}" : "Invoice No. {$this->current_invoice['invoice_no']}" ) ) )
				    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, bcmul($amount_to_pay, -1, 2), 'CD', ($this->current_invoice['type'] == 'D' ? "Deposit for PO ".$this->current_invoice['invoice_no'] : "Invoice No. ".$this->current_invoice['invoice_no'])) )
				    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( $this->current_invoice['type'] == 'D' ) { # If the payment is a deposit

					if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_WIP_ACCT, bcmul($amount_to_pay, -1, 2), 'CD', "Vendor deposit payment") )
				    	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_VENDOR_DEPOSIT_ACCT, $amount_to_pay, 'CD', "Vendor deposit payment") )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				} elseif ( $this->current_invoice['type'] == 'I' && bccomp($deposits_used, 0, 2) == 1 ) {

					if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, bcmul($deposits_used, -1, 2), 'CD', "Previous deposits paid") )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_VENDOR_DEPOSIT_ACCT, bcmul($deposits_used, -1, 2), 'CD', "Previous deposits paid") )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}
				if ( bccomp($discounts_used, 0, 2) == 1 ) {

					if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, bcmul($discounts_used, -1, 2), 'CD', "Vendor discounts taken") )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					if ( ! $accounting->exec_trans($audit_id, $posting_date, $discount_hash, ( $accounting->account_action($discount_hash) == 'DR' ? bcmul($discounts_used, -1, 2) : $discounts_used ), 'CD', "Vendor discounts taken") )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				if ( bccomp($credits_used, 0, 2) == 1 ) {

					$credit_reduced = $credits_used;
					$open_credits = $this->fetch_vendor_credits($this->current_invoice['vendor_hash'], 1);
					if ( ! $open_credits )
						return $this->__trigger_error("Unable to fetch vendor credits for payment posting, vendor credit table not available. Please reload window and try again. <!-- Tried looking up vendor credits for vendor [ {$this->current_invoice['vendor_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

					for ( $j = 0; $j < count($open_credits); $j++ ) {

						if ( ( $open_credits[$j]['type'] == 'C' && bccomp($open_credits[$j]['balance'], 0, 2) == 1 ) || ( $open_credits[$j]['type'] == 'I' && bccomp($open_credits[$j]['balance'], 0, 2) == -1 ) ) { # Vendor credit with positive balance or overpayment with negative balance

							$aval_bal = ( $open_credits[$j]['type'] == 'I' ? bcmul($open_credits[$j]['balance'], -1, 2) : $open_credits[$j]['balance'] );
							if ( bccomp($aval_bal, $credit_reduced, 2) == 1 ) {

								$credit_amt_used = $credit_reduced;
								$balance_update = bcsub($aval_bal, $credit_amt_used, 2);
							} else {

								$credit_amt_used = $aval_bal;
								$balance_update = bcsub($aval_bal, $credit_amt_used, 2);
							}

							# Update the credit that we just used and deduct the balance
							if ( ! $this->db->query("UPDATE vendor_payables t1
        											 SET  t1.balance = '" . ( $open_credits[$j]['type'] == 'I' ? bcmul($balance_update, -1, 2) : $balance_update ) . "'
        											 WHERE t1.invoice_hash = '{$open_credits[$j]['invoice_hash']}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                            }

							# Update the credit applied table
							if ( ! $this->db->query("INSERT INTO vendor_credit_applied
        											 VALUES
        											 (
	        											 NULL,
	        											 '$payment_hash',
	        											 '{$open_credits[$j]['invoice_hash']}',
	        											 '$credit_amt_used',
	        											 0,
	        											 0
        											 )")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                            }

							$credit_reduced = bcsub($credit_reduced, $credit_amt_used, 2);
							if ( bccomp($credit_reduced, 0, 2) != 1 )
								break;
						}
					}

					if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, $credits_used, 'CD', "Applied Credit") )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					if ( ! $accounting->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, bcmul($credits_used, -1, 2), 'CD', "Applied Credit") )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}
				
				

				if ( ! $accounting->end_transaction() )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$this->db->end_transaction();

				$audit_id++;
				if ( ! $integrate )
					$this->jscript_action("purgeit('{$invoices[$i]}');");
			}

			if ( $integrate )
				return "Success";
			else {

				$this->content['action'] = 'continue';
				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search=$active_search');" );
			}

		} elseif ( $btn == 'printchecks' ) {

			$pay_queue = $_POST['pay_queue'];
			$check_no = $_POST['check_no'];
			$invoice_no = $_POST['invoice_no'];
			$currency = $_POST['currency'];
			$memo = $_POST['memo'];
			$bill_to = $_POST['bill_to'];
			$preview = $_POST['preview'];
			$checking_acct = $_POST['checking_acct'];

			if ( $rm_from_queue = $_POST['rm_from_queue'] ) {

				if ( $this->fetch_queue_record($_POST['rm_queue_hash']) ) {

					if ( ! $this->fetch_invoice_record($this->current_queue['invoice_hash']) )
	                	return $this->__trigger_error("System error encountered when attempting to lookup vendor invoice record. Please reload window and try again. <!-- Tried fetching vendor invoice [ {$this->current_queue['invoice_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);
					if ( ! $this->fetch_payment_record($this->current_queue['payment_hash']) )
	                	return $this->__trigger_error("System error encountered when attempting to lookup vendor payment queue record. Please reload window and try again. <!-- Tried fetching payment queue [ {$this->current_queue['payment_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

					$this->content['action'] = 'continue';
					if ( ! $this->db->start_transaction() )
						return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

					$accounting = new accounting($this->current_hash);
					$accounting->start_transaction( array(
						'proposal_hash'   =>  $this->current_invoice['invoice_proposal'],
						'ap_invoice_hash' =>  $this->current_invoice['invoice_hash'],
						'vendor_hash'     =>  $this->current_invoice['vendor_hash'],
						'ap_payment_hash' =>  $this->current_queue['payment_hash']
                    ) );

				    $audit_id = $accounting->audit_id();

					if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], $this->current_payment['account_hash'], $this->current_queue['amount'], 'AD', "Removed from pay queue - Invoice: {$this->current_invoice['invoice_no']}") )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

					if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], DEFAULT_AP_ACCT, $this->current_queue['amount'], 'AD', "Removed from pay queue - Invoice: ".$this->current_invoice['invoice_no'], 1) )
					    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

					if ( $this->current_invoice['type'] == 'D' ) { # If it's a deposit

						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], DEFAULT_WIP_ACCT, $this->current_queue['amount'], 'AD', "Purchase Order Deposit") )
						    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], DEFAULT_VENDOR_DEPOSIT_ACCT, bcmul($this->current_queue['amount'], -1, 2), 'AD', "Purchase Order Deposit") )
					    	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

					} elseif ( $this->current_invoice['type'] == 'I' && bccomp($this->current_queue['deposit_used'], 0, 2) == 1 ) {

						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], DEFAULT_AP_ACCT, $this->current_queue['deposit_used'], 'AD', "Previous deposits paid") )
					    	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

					    if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], $this->current_queue['deposit_hash'], $this->current_queue['deposit_used'], 'AD', "Previous deposits paid") )
						    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
					}
					if ( bccomp($this->current_queue['discount_taken'], 0, 2) == 1 ) {

						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], DEFAULT_AP_ACCT, $this->current_queue['discount_taken'], 'AD', "Vendor discounts taken") )
						    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], $this->current_queue['discount_acct_hash'], ( $accounting->account_action($this->current_queue['discount_acct_hash']) == 'DR' ? $this->current_queue['discount_taken'] : bcmul($this->current_queue['discount_taken'], -1, 2) ), 'AD', "Vendor discounts taken") )
					    	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
					}

					if ( bccomp($this->current_queue['credit_used'], 0, 2) == 1 ) {

						$credit_reduced = $this->current_queue['credit_used'];

    					list($open_credits, $total_credit_applied) = $this->fetch_vendor_credit_applied($this->payment_hash);

    					if ( ! $open_credits ) { # Payment was made before vendor_credit_applied table tracking

                            # Trac 1575 - 27AUG2010 - If this condition is met payment is likely over 2 years old. Manual support intervention required. Further documentation can be found in trac ticket
                            return $this->__trigger_error("System error encountered when attempting to lookup credits applied to this payment. Please reload window and try again. <!-- Tried fetching credits for payment [ {$payables->payment_hash} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                            /*
                            $open_credits = $this->fetch_vendor_credits($this->current_invoice['vendor_hash'], 2);
                            $count = count($open_credits);
                            $total_credits = 0;

                            for ( $i = 0; $i < $count; $i++ ) {

                            	$open_credits[$i]['amount'] = bcsub($open_credits[$i]['amount'], $open_credits[$i]['balance'], 2);
                            	$r = $this->db->query("SELECT SUM( t1.amount ) AS total
                            	                       FROM vendor_credit_applied t1
                            	                       WHERE t1.invoice_hash = '{$open_credits[$i]['invoice_hash']}' AND t1.void = 0");
                            	if ( ! $this->db->result($r, 0, 'total') || bccomp($this->db->result($r, 0, 'total'), $open_credits[$i]['amount'], 2) == -1 )
                            		$open_credits[$i]['amount'] = bcsub($open_credits[$i]['amount'], $this->db->result($r, 0, 'total'), 2);
                                else
                                    unset($open_credits[$i]);

                                if ( $open_credits[$i]['amount'] ) {

                                	if ( bccomp( bcadd($total_credits, $open_credits[$i]['amount'], 2), $credit_reduced, 2) == 1 )
                                        $open_credits[$i]['amount'] = bcsub($credit_reduced, $total_credits, 2);

                                    $total_credits = bcadd($total_credits, $open_credits[$i]['amount'], 2);
                                    $vendor_credit_patch[] = $open_credits[$i];
                                }
                                if ( bccomp($total_credits, $credit_reduced, 2) != -1 )
                                    break;
                            }

                            if ( $vendor_credit_patch ) {

                                for ( $i = 0; $i < count($vendor_credit_patch); $i++ ) {

                                    if ( ! $this->db->query("INSERT INTO vendor_credit_applied
                                                             VALUES
                                                             (
	                                                             NULL,
	                                                             '{$this->payment_hash}',
	                                                             '{$vendor_credit_patch[$i]['invoice_hash']}',
	                                                             '{$vendor_credit_patch[$i]['amount']}',
	                                                             0,
	                                                             0
                                                             )")
                                    ) {

                                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                    }
                                }

                                list($open_credits, $total_credit_applied) = $this->fetch_vendor_credit_applied($this->payment_hash);
                            }
                            */
    					}

    					if ( bccomp($total_credit_applied, $this->current_queue['credit_used'], 2) || ! $open_credits ) # Trac #1440
    						return $this->__trigger_error("The credit applied to the current payment cannot be reverted to the original vendor credit. Database lookup error. Please reload window and try again. <!-- Tried reverting credits from payment [ {$this->payment_hash} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

						for ( $j = 0; $j < count($open_credits); $j++ ) {

							if ( ! $this->db->query("UPDATE vendor_payables t1
        											 SET
	        											 t1.timestamp = UNIX_TIMESTAMP(),
	        											 t1.last_change = '{$this->current_hash}',
	        											 t1.balance = t1.balance + " .
	        											 ( $open_credits[$j]['type'] == 'I' ?
    	        											 bcmul($open_credits[$j]['amount'], -1, 2) : $open_credits[$j]['amount']
    	        										 ) . "
        											 WHERE t1.invoice_hash = '{$open_credits[$j]['invoice_hash']}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }

							# Void vendor_credit_applied row
							if ( ! $this->db->query("UPDATE vendor_credit_applied t1
        										     SET
	        										     t1.void = 1,
	        										     t1.void_timestamp = UNIX_TIMESTAMP()
        										     WHERE t1.obj_id = {$open_credits[$j]['obj_id']}")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
						}

						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], DEFAULT_AP_ACCT, bcmul($this->current_queue['credit_used'], -1, 2), 'CD', "Applied Credit") )
						    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

						if ( ! $accounting->exec_trans($audit_id, $this->current_payment['posting_date'], DEFAULT_AP_ACCT, $this->current_queue['credit_used'], 'CD', "Applied Credit") )
						    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
					}

					$invoice_bal = bcadd($this->current_invoice['balance'], $this->current_queue['amount'], 2);
					$invoice_bal = bcadd($invoice_bal, $this->current_queue['deposit_used'], 2);
					$invoice_bal = bcadd($invoice_bal, $this->current_queue['discount_taken'], 2);
					$invoice_bal = bcadd($invoice_bal, $this->current_queue['credit_used'], 2);

					if ( ! $this->db->query("UPDATE vendor_payables t1
        									 SET
	        									 t1.timestamp = UNIX_TIMESTAMP(),
	        									 t1.last_change = '{$this->current_hash}',
	        									 t1.paid_in_full = 0,
	        									 t1.balance = $invoice_bal
        									 WHERE t1.invoice_hash = '{$this->invoice_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					if ( ! $this->db->query("DELETE FROM vendor_payment
        									 WHERE payment_hash = '{$this->payment_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					if ( ! $this->db->query("DELETE FROM vendor_pay_queue
        									 WHERE queue_hash = '{$this->queue_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					if ( ! $accounting->end_transaction() )
                        return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

					$this->db->end_transaction();

					unset($this->ajax_vars['preview'], $_POST['preview']);

					$this->ajax_vars['from_start'] = 1;
					$_POST['starting_check_no'] = $_POST['orig_starting_check'];

					$this->empty_queue();

					return;

				} else
					return $this->__trigger_error("System error encountered when attempting to fetch payment from pay queue. Please reload window and try again. <!-- Tried fetching vendor pay queue hash [ {$_POST['rm_queue_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			if ( $preview ) {

				for ( $i = 0; $i < count($pay_queue); $i++ ) {

					if ( $pay_queue[$i] ) {

						if ( ! $this->fetch_queue_record($pay_queue[$i]) )
                            return $this->__trigger_error("System error encountered when attempting to lookup payment queue record. Please reload window and try again. <!-- Tried fetching pay queue [ {$pay_queue[$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

						$to_pay[] = $pay_queue[$i];
						$check_array[ $check_no[ $pay_queue[$i] ] ][] = $this->current_queue['vendor_hash'];
						$check_date[ $check_no[ $pay_queue[$i] ] ] = $_POST["check_date_{$pay_queue[$i]}"];
					}
				}

				if ( ! $to_pay || ! $check_array )
					return $this->__trigger_error("In order to continue please select at least one invoice to pay along with a valid check number.", E_USER_NOTICE, __FILE__, __LINE__, 1);

				while ( list($assigned_check_no, $vendor_list) = each($check_array) ) {

					$vendor_array_uniq = array_unique($vendor_list);
					if ( count($vendor_array_uniq) > 1 )
						$check_err[] = $assigned_check_no;

					$check_list[] = $assigned_check_no;
				}

				$check_list = array_unique($check_list);
				for ( $i = 0; $i < count($check_list); $i++ ) {

					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM check_register
												WHERE check_no = '{$check_list[$i]}' AND account_hash = '$checking_acct'");
					if ( $this->db->result($result, 0, 'Total') )
						$check_no_dup[] = $check_list[$i];

					if ( strspn($check_list[$i], "0123456789") != strlen($check_list[$i]) )
						$bad_check_no[] = $check_list[$i];
				}

				if ( $bad_check_no )
                    return $this->__trigger_error("One or more of the checks you are trying to print are invalid numbers. Check numbers must not contain anything buy a number.<br /><br />The check(s) in error are: <br /><br />" . implode("<br />", $bad_check_no), E_USER_NOTICE, __FILE__, __LINE__, 1);

                # TODO: Make total invoice count per check dynamic setting
				while ( list($a, $b) = each($check_no) ) # No more than 20 payables on a single check
					$use_check[ $b ]++;

				while ( list($a, $b)  = each($use_check) ) {

					if ( $b > 20 )
						$use_check_err[] = $a;
				}

				if ( $use_check_err )
                    return $this->__trigger_error("You are trying to print more than 20 invoices on single check. In order the allow each of the invoices to print properly on the check coupon, please limit the number of invoices paid on a single check to 20.<br /><br />The check(s) in error are: <br /><br />" . implode("<br />", $use_check_err), E_USER_ERROR, __FILE__, __LINE__, 1);

				if ( $check_err || $check_no_dup ) {

					if ( $check_no_dup ) $this->set_error_msg("One or more of the check numbers you assigned have already been used. Please enter unique check numbers to continue. The check(s) in error are:<br /><br />".implode("<br />", $check_no_dup) );
					if ( $check_err ) $this->set_error_msg("You have assigned the same check number to more than one vendor. Please check that if you choose to consolidate multiple invoices onto a single check, that check number is not shared between more than one vendor.<br /><br />The check number(s) in error are:<br /><br />" . implode("<br />", $check_err) );
                    return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
				}

                if ( ! $this->db->start_transaction() )
                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

				if ( ! $this->db->query("UPDATE vendor_pay_queue
        								 SET ready = 0")
				) {

					return $this->__trigger_error("System error encountered when attempting to initiate vendor pay queue for check run. Unable to set ready flag. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
				}

				for ( $i = 0; $i < count($to_pay); $i++ ) {

					$pay_hash = $to_pay[$i];
					if ( ! $this->fetch_queue_record($pay_hash) )
                        return $this->__trigger_error("System error encountered when attempting to lookup vendor pay queue record for check preview. Please reload window and try again. <!-- Tried fetching pay queue [ $pay_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

					unset($exchange_rate);
					if ( defined('FUNCTIONAL_CURRENCY') && $currency[ $this->current_queue['vendor_hash'] ] && $currency[ $this->current_queue['vendor_hash'] ] != FUNCTIONAL_CURRENCY ) {

						if ( ! isset($sys) )
                            $sys = new system_config();

                        if ( ! $sys->fetch_currency( $currency[ $this->current_queue['vendor_hash'] ] ) )
                            return $this->__trigger_error("System error encountered when attempting to lookup currency rule [ $currency ] for vendor payment. Please check with your system administrator to make sure currency tables have been properly configured. <!-- Tried fetching currency [ $currency ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

                        $exchange_rate = $sys->current_currency['rate'];
                        if ( ! $exchange_rate )
                            return $this->__trigger_error("Unable to apply currency conversion to vendor payment. Exchange rate has not been set for currency [ $currency ]. Please check with your system administrator.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

					if ( ! $this->db->query("UPDATE vendor_pay_queue t1
        									 SET
	        									 t1.ready = 1,
	        									 t1.check_no = '{$check_no[ $pay_hash ]}',
	        									 t1.check_date = '{$check_date[ $check_no[ $pay_hash ] ]}',
	    									     t1.remit_to = '" . addslashes($bill_to[ $this->current_queue['vendor_hash'] ]) . "',
	    									     t1.invoice_no = '" . addslashes($invoice_no[ $pay_hash ] ) . "',
	    					                     t1.currency = " . ( $currency[ $this->current_queue['vendor_hash'] ] ? "'{$currency[ $this->current_queue['vendor_hash'] ]}'" : "NULL" ) . ",
	    					                     t1.exchange_rate = " . ( $exchange_rate ? $exchange_rate : 0 ) . "
        									 WHERE t1.queue_hash = '$pay_hash'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
				}

                $this->db->end_transaction();
				$this->content['html']["check_content_holder{$this->popup_id}"] = $this->empty_queue(1);

			} else {

				if ( ! $this->fetch_pay_queue($checking_acct, 1) )
                    return $this->__trigger_error("System error encountered when attempting to fetch vendor payment queue. Please reload window and try again. <!-- Tried fetching pay queue for account [ $checking_acct ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

				$check_reg = array();
                $orig_starting_check = $_POST['orig_starting_check'];

                if ( ! $this->db->start_transaction() )
                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

				for ( $i = 0; $i < count($this->pay_queue); $i++ ) {

					$payment_hash = $this->pay_queue[$i]['payment_hash'];
					$account_to_print = $this->pay_queue[$i]['account_hash'];
					$check_account[] = $account_to_print;

					if ( ! $payment_hash || ! $account_to_print )
						return $this->__trigger_error("System error encountered when attempting to prepare checks for printing. Payment record missing required account information. Please reload window and try again. <!-- " . ( ! $payment_hash ? "Missing payment hash " : NULL ) . ( ! $account_to_print ? "Missing account hash" : NULL ) . " -->", E_USER_ERROR, __FILE__, __LINE__, 1);

					if ( ! $this->db->query("UPDATE vendor_payment t1
        									 SET
	        									 t1.timestamp = UNIX_TIMESTAMP(),
	        									 t1.last_change = '{$this->current_hash}',
	        									 t1.invoice_hash = '{$this->pay_queue[$i]['invoice_hash']}',
	        									 t1.payment_amount = '{$this->pay_queue[$i]['pay_amount']}',
	        									 t1.deposit_used = '{$this->pay_queue[$i]['deposit_used']}',
	        									 t1.deposit_hash = '{$this->pay_queue[$i]['deposit_hash']}',
	        									 t1.discount_taken = '{$this->pay_queue[$i]['discount_taken']}',
	        									 t1.discount_acct_hash = '{$this->pay_queue[$i]['discount_acct_hash']}',
	        									 t1.credit_used = '{$this->pay_queue[$i]['credit_used']}',
	        									 t1.check_no = '{$this->pay_queue[$i]['check_no']}'
        									 WHERE t1.payment_hash = '$payment_hash'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

				    if ( ! $this->db->query("UPDATE journal t1
        				                     SET t1.check_no = '{$this->pay_queue[$i]['check_no']}'
        				                     WHERE t1.ap_payment_hash = '$payment_hash'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					if ( ! $this->db->query("DELETE FROM vendor_pay_queue
        									 WHERE queue_hash = '{$this->pay_queue[$i]['queue_hash']}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

				    unset($exchange_rate);
					if ( ! in_array($this->pay_queue[$i]['check_no'], $check_reg) ) {

						$check_date = $this->pay_queue[$i]['posted_check_date'];
						$currency = $_POST['currency'][ $this->pay_queue[$i]['check_no'] ];
                        if ( isset($currency) && $currency != FUNCTIONAL_CURRENCY )
                            $exchange_rate = $_POST['exchange_rate'][ $this->pay_queue[$i]['check_no'] ];
                        else
                            unset($currency);

						if ( ! $this->db->query("INSERT INTO check_register
        										 VALUES
        										 (
	        										 NULL,
	        										 UNIX_TIMESTAMP(),
	        										 '{$this->current_hash}',
	        										 '{$this->pay_queue[$i]['account_hash']}',
	        										 '{$this->pay_queue[$i]['expense_account']}',
	        										 '{$this->pay_queue[$i]['vendor_hash']}',
	        										 '" . addslashes($this->pay_queue[$i]['remit_to']) . "',
	        										 '$check_date',
	        										 '{$this->pay_queue[$i]['check_no']}',
	        										 '". addslashes($this->pay_queue[$i]['memo']) . "',
	        										 0,
	        										 0,
	        										 0," .
	        										 ( $currency ? "'$currency'" : "NULL" ) . "," .
	        										 ( $currency ? "'$exchange_rate'" : "0" ) . "
        										 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						if ( ! $this->db->query("INSERT INTO vendor_payment_print
        										 VALUES
        										 (
	        										 NULL,
	        										 UNIX_TIMESTAMP(),
	        										 '{$this->pay_queue[$i]['check_no']}',
	        										 '{$this->pay_queue[$i]['account_hash']}'
        										 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$check_reg[] = $this->pay_queue[$i]['check_no'];
					}

					$payment_queue_hash[] = $payment_hash;
					$check_amount[ $this->pay_queue[$i]['check_no'] ][] = $this->pay_queue[$i]['pay_amount'];
				}

				$single_check_acct = array_unique($check_account);
				$single_check_acct = array_values($single_check_acct);
				if ( count($single_check_acct) != 1 || ! $single_check_acct[0] )
					return $this->__trigger_error("Error when trying to run check batch. Check batch contains multiple payment accounts. Please reload window and try again. <!-- " . ( count($single_check_acct) > 1 ? " Multiple checking accounts: " . implode(", ", $single_check_acct) : "No checking account specified" ) . " -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( $check_amount ) {

					while ( list($check_no, $check_amt) = each($check_amount) ) {

						$total_pay_amount = array_sum($check_amt);
						$check_no_list[] = $check_no;
						if ( ! $this->db->query("UPDATE check_register t1
        										 SET t1.amount = '$total_pay_amount'
        										 WHERE t1.check_no = '$check_no' AND t1.account_hash = '{$single_check_acct[0]}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
					}
				}

				if ( $check_no_list ) {

					$check_no_list = array_unique($check_no_list);
					asort($check_no_list);
				}

				if ( (int)$check_no_list[0] == (int)$orig_starting_check ) {

			    	$next_check_no = ( (int)$check_no_list[0] + 1 );
			    	while ( $this->check_taken($next_check_no, $account_to_print) || in_array($next_check_no, $check_no_list) )
			    	    $next_check_no++;

					if ( ! $next_check_no ) {

					    $last_check_no = array_pop($check_no_list);
					    $next_check_no = $last_check_no++;
					}

					if ( ! $this->db->query("UPDATE accounts t1
        					                 SET t1.next_check_no = '$next_check_no'
        					                 WHERE t1.account_hash = '$account_to_print'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
				}

				$this->db->end_transaction();

				$this->content['html']["check_content_holder{$this->popup_id}"] = $this->empty_queue(2);
			}

			$this->content['action'] = 'continue';
		}
	}

	function innerHTML_vendor_po() {

		$args = func_get_args();
		if (!$args && $this->ajax_vars['qtype']) {
			$qtype = $this->ajax_vars['qtype'];
			$proposal_hash = $this->ajax_vars['proposal_hash'];
			$invoice_type = $this->ajax_vars['invoice_type'];
			$vendor_hash = $this->ajax_vars['vendor_hash'];
			$selected_value = $_POST['po_hash'];
            $popup_id = $this->ajax_vars['popup_id'];
		} elseif ($args) {
			$qtype = $args[0];
			$proposal_hash = $args[1];
			$vendor_hash = $args[2];
			$selected_value = $args[3];
			$popup_id = $args[4];
			$readonly = $args[5];
		} elseif ($_POST['qtype'] == 'balance') {
			$error_node = $_POST['error_node'];
			$invoice_amount = preg_replace('/[^-.0-9]/', "", $_POST['invoice_amount']);
			$focus = $_POST['focus'];
			for ($i = 0; $i < count($error_node); $i++) {
				$amount = preg_replace('/[^-.0-9]/', "", $_POST['amount_'.$error_node[$i]]);
				$total += $amount;
			}
			if ($invoice_amount != 0 && $total != 0 && $invoice_amount - $total != 0 && $focus)
				$this->content['value']['amount_'.$focus] = number_format($invoice_amount - $total, 2);

			$this->content['jscript'] = "totalexp(1);";
			$this->content['action'] = 'continue';
			return;
		}
		
		if ($qtype == 'vendor' && !$selected_value) {
			$po = new purchase_order($proposal_hash);
			$po->fetch_purchase_orders(0, $po->total);
			$this->fetch_proposal_payables($proposal_hash);

			$result = $this->db->query("SELECT t1.po_hash , t1.po_no , t1.currency , t1.exchange_rate , t1.order_amount ,
										SUM(
											CASE
												WHEN t2.type = 'I'
												THEN t2.amount
												ELSE 0
											END
										) AS total_paid_so_far,
										SUM(
											CASE
												WHEN t2.type = 'D'
												THEN t2.amount
												ELSE 0
											END
										) AS total_deposit_paid
										FROM purchase_order t1
										LEFT JOIN vendor_payables t2 ON t2.po_hash = t1.po_hash AND t2.deleted = 0
										WHERE ".($selected_value ? "
                                			t1.po_hash = '$selected_value'" : "t1.vendor_hash = '$vendor_hash' AND t1.proposal_hash = '$proposal_hash' AND t1.deleted = 0")."
										GROUP BY t1.po_hash");
			while ($row = $this->db->fetch_assoc($result)) {
				if (($selected_value && $row['po_hash'] == $selected_value) || !$selected_value) {
	                if ($row['currency'] && $row['exchange_rate']) {
	                    $row['order_amount'] = currency_exchange($row['order_amount'], $row['exchange_rate']);
	                    $tmp_onClick = "setCurrency('".$row['currency']."');";
	                }

					$vendor_po_no[] = $row['po_no'].(!$selected_value ? "&nbsp;&nbsp;&nbsp;&nbsp;$".number_format(bcsub($row['order_amount'], $row['total_paid_so_far']), 2) : NULL);
					$vendor_po_hash[] = $row['po_hash'];
				}
			}

			if (count($vendor_po_no)) {
				$this->content['html']['vendor_po_holder'] = $vendor_po_holder = $this->form->select("po_hash",
                                                                                    				 $vendor_po_no,
                                                                                    				 $selected_value,
                                                                                    				 $vendor_po_hash,
																									"id=po_hash",
                                                                                    				 ($readonly ? "readonly" : "onChange=if(\$('po_hash').value){view_po_win('{$proposal_hash}', \$('po_hash').value);agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'innerHTML_vendor_po', 'proposal_hash=".$proposal_hash."', 'vendor_hash=".$vendor_hash."', 'po_hash='+\$('po_hash').value, 'qtype=vendor', 'popup_id=".$popup_id."');}else{toggle_display('deposit_amt_holder', 'none');}"));

			} elseif ($vendor_hash)
				$this->content['html']['vendor_po_holder'] = $vendor_po_holder = "&nbsp;<img src=\"images/alert.gif\">&nbsp;&nbsp;All POs under this vendor have already been input.";

			if (!$vendor_hash)
				return;

			if ($args) {
				unset($this->content['html']['vendor_po_holder']);
				return $vendor_po_holder;
			}
			$this->content['jscript'][] = "submit_form(\$('popup_id').form, 'payables', 'exec_post', 'refresh_form', 'action=vendor_due_date', 'vendor_hash=$vendor_hash', 'popup_id=$popup_id');";
		} else if ($selected_value && !$args) {
			$result = $this->db->query("SELECT t1.order_amount , t1.currency , t1.exchange_rate ,
										SUM(
											CASE
												WHEN t2.type = 'I'
												THEN t2.amount
												ELSE 0
											END
										) AS total_paid_so_far,
										SUM(
											CASE
												WHEN t2.type = 'D'
												THEN t2.amount
												ELSE 0
											END
										) AS total_deposit_paid
										FROM purchase_order t1
										LEFT JOIN vendor_payables t2 ON t2.po_hash = t1.po_hash AND t2.deleted = 0
										WHERE t1.po_hash = '$selected_value'
										GROUP BY t1.po_hash");
			if ($row = $this->db->fetch_assoc($result)) {
				if ($row['currency'] && $row['exchange_rate']) {
                    $row['order_amount'] = currency_exchange($row['order_amount'], $row['exchange_rate']);
				}
				$this->content['jscript'][] = "\$('amount_' + \$F('first_rand_err')).value='".number_format(bcsub($row['order_amount'], $row['total_paid_so_far']), 2)."';
						\$('account_name_' + \$F('first_rand_err')).value='Work In Progress';
						\$('account_hash_' + \$F('first_rand_err')).value='".DEFAULT_WIP_ACCT."';";

				$this->content['value']['invoice_amount'] = number_format(bcsub($row['order_amount'], $row['total_paid_so_far']), 2);
				$this->content['html']['total_amount_holder'] = '$'.number_format(bcsub($row['order_amount'], $row['total_paid_so_far']), 2);
			}
			if ($val = $this->check_deposits($selected_value)) {
				$this->content['html']['deposit_amt_holder'] = $val;
				$this->content['jscript'][] = "toggle_display('deposit_amt_holder', 'block');";
			} else
				$this->content['jscript'][] = "toggle_display('deposit_amt_holder', 'none');";
		}
	}

	function select_amount() {
		$popup_id = $this->ajax_vars['popup_id'];
	}

	function doit_add_line() {
		$invoice_hash = $_POST['invoice_hash'];
		$error_node = $_POST['error_node'];
		$invoice_amount = preg_replace('/[^-.0-9]/', "", $_POST['invoice_amount']);

		$total = 0;
		for ($i = 0; $i < count($error_node); $i++) {
			$amount = preg_replace('/[^-.0-9]/', "", $_POST['amount_'.$error_node[$i]]);
			$total += $amount;
		}

		for ($i = 0; $i < 3; $i++) {

			$rand_err = rand(500, 5000000);

			$expense_dist_account .= "
			<div style=\"margin-bottom:5px;\">".
				$this->form->text_box("name=account_name_".$rand_err,
										"value=",
										"autocomplete=off",
										"size=25",
										"onBlur=key_clear();",
										"onFocus=if(this.value){select();}else{submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=innerHTML_vendor_po', 'qtype=balance', 'focus=".$rand_err."');}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', 1);}",
                                        "onKeyUp=if(ka==false && this.value){key_call('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', 1);}",
                        				"onKeyDown=clear_values('account_hash_".$rand_err."');").
				$this->form->hidden( array(
    				"account_hash_{$rand_err}"  =>  '',
    				"error_node[]"              =>  $rand_err
				) ) . "
				<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('account_name_".$rand_err."'), 'top')+20, findPos(\$('account_name_".$rand_err."'), 'left')+1);key_list('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>
			</div>";
			$expense_dist_amount .= "
			<div style=\"margin-bottom:5px;\">" .
    			$this->form->text_box("name=amount_{$rand_err}",
                            		  "value=" . ( $i == 0 && $total != 0 && $invoice_amount - $total != 0 ? number_format($invoice_amount - $total, 2) : NULL ),
                            		  "size=10",
                            		  "style=text-align:right",
                            		  "onFocus=if(this.value){select();}else{submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=innerHTML_vendor_po', 'qtype=balance', 'focus=".$rand_err."');}",
                            		  "onBlur=this.value=formatCurrency(this.value);",
    			                      "onChange=totalexp(1);")."
            </div>";
			$expense_dist_memo .= "
			<div style=\"margin-bottom:5px;\">" .
    			$this->form->text_box(
        			"name=memo_{$rand_err}",
        			"value=",
        			"size=23"
    			) . "
    		</div>";
			$expense_dist_proposal .= "
			<div style=\"margin-bottom:5px;\">
				".$this->form->text_box("name=proposal_no_".$rand_err,
										"value=",
										"autocomplete=off",
										"size=10",
										"onBlur=key_clear();",
										"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'proposal_no_".$rand_err."', 'proposal_hash_".$rand_err."', 1);}",
                                        "onKeyUp=if(ka==false && this.value){key_call('payables', 'proposal_no_".$rand_err."', 'proposal_hash_".$rand_err."', 1);}",
			                            "onKeyDown=clear_values('proposal_hash_".$rand_err."');").
				$this->form->hidden(array("proposal_hash_".$rand_err => ''))."
			</div>";
		}
		$this->db->query("UPDATE `vendor_payables`
						  SET `complete` = 0
						  WHERE `invoice_hash` = '$invoice_hash'");

		$this->ajax_vars['invoice_hash'] = $invoice_hash;

		$this->content['html_append']['expense_dist_account'] = $expense_dist_account;
		$this->content['html_append']['expense_dist_amount'] = $expense_dist_amount;
		$this->content['html_append']['expense_dist_memo'] = $expense_dist_memo;
		$this->content['html_append']['expense_dist_proposal'] = $expense_dist_proposal;

		$this->content['action'] = 'continue';
		return;
	}

	function edit_invoice($local=NULL) {

		$this->popup_id = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );

        $this->db->define('convert_currency', 1);

		$invoice_type = $this->ajax_vars['invoice_type'];
		$from_class = $this->ajax_vars['from_class'];
		$from_section = $this->ajax_vars['from_section'];
        $from_accounting = $this->ajax_vars['from_accounting'];

		if ( $this->ajax_vars['proposal_hash'] || $_POST['proposal_hash'] ) {

			$this->proposals = new proposals($this->current_hash);
			if ( ! $this->proposals->fetch_master_record( ( $this->ajax_vars['proposal_hash'] ? $this->ajax_vars['proposal_hash'] : $_POST['proposal_hash'] ) ) )
				return $this->__trigger_error("System error when attempting to fetch proposal record. Could not initiate vendor payable class due to proposal lookup error. Please reload window and try again. <!-- Tried fetching proposal [ " . ( $this->ajax_vars['proposal_hash'] ? $this->ajax_vars['proposal_hash'] : $_POST['proposal_hash'] ) . " ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			$from_proposal = true;

			$this->po = new purchase_order($this->proposals->proposal_hash);
			$this->po->fetch_purchase_orders(0, $this->po->total);

			$po_vendor_hash = $po_vendor_name = array();
			for ( $i = 0; $i < $this->po->total; $i++ ) {

				if ( ! in_array($this->po->po_info[$i]['vendor_hash'], $po_vendor_hash) ) {

					array_push($po_vendor_hash, $this->po->po_info[$i]['vendor_hash']);
					array_push($po_vendor_name, stripslashes($this->po->po_info[$i]['vendor_name']) );
				}
			}
		}

		if ( $this->ajax_vars['invoice_hash'] ) {

			if ( ! $this->fetch_invoice_record($this->ajax_vars['invoice_hash'], 1) )
				return $this->__trigger_error("System error when attempting to fetch invoice record. Please reload window and try again. <!-- Tried fetching invoice [ {$this->ajax_vars['invoice_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			if ( $this->current_invoice['type'] == 'R' )
				$invoice_type = 'R';

			for ( $i = 0; $i < count($this->current_invoice['payments']); $i++ ) {

				if ( ! $this->current_invoice['payments'][$i]['void'] ) {

					$active_payments = true;
					break;
				}
			}

			$expenses = $this->current_invoice['expense_items'];
			if ( $this->ajax_vars['new'] ) {

				$num_expenses = count($expenses);
				for ( $i = 0; $i < $num_expenses; $i++ ) {

					if ( ! bccomp($expenses[$i]['amount'], 0, 2) && ! $expenses[$i]['account_name'] && ! $expenses[$i]['account_hash'] ) {

						if ( $this->db->query("DELETE FROM vendor_payable_expenses
											   WHERE expense_hash = '{$expenses[$i]['expense_hash']}'")
						) {

							unset($expenses[$i]);
							$unset = true;
						}
					}
				}

				if ( $unset ) {

					$expenses = array_values($expenses);
					$this->current_invoice['expense_items'] = $expenses;
				}
			}

			$r = $this->db->query ("SELECT COUNT(*) AS Total
									FROM vendor_pay_queue
									WHERE invoice_hash = '{$this->invoice_hash}'");
			$pay_queue = $this->db->result($r);
		}

		//Find out if the last invoice created, had the item details enabled
		if ( ! $this->invoice_hash ) {

			$r = $this->db->query("SELECT `item_map`
			                       FROM vendor_payables
			                       WHERE `po_hash` != ''
			                       ORDER BY obj_id DESC
			                       LIMIT 1");
			if ($last_invoice_pref = $this->db->result($r, 0, 'item_map'))
	            $item_map = true;
		}

		$this->content['popup_controls']['popup_id'] = $this->popup_id;
		$this->content['popup_controls']['popup_title'] = ($this->invoice_hash ? ($this->current_invoice['type'] == 'D' ? "Deposit" : ($this->current_invoice['type'] == 'C' ? "Credit" : "Bill"))." Summary" : ($invoice_type == 'R' ? "Create a New Customer Refund" : ($invoice_type == 'C' ? "Enter Vendor Credits" : "Create a New Vendor Invoice/Deposit")));
		if (!$valid && !$this->proposals->proposal_hash)
			$this->content['focus'] = "po_vendor";

		$jscript[] = "
		view_po_win = function() {
			var proposal_hash = arguments[0];
			var po_hash = arguments[1];
            var display_pref = '".(($this->current_invoice['po_hash'] && $this->current_invoice['item_map']) || $item_map ? "Hide" : "Map")."';
            \$('show_item_holder').innerHTML='<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"if(\$(\'item_list_tr\').getStyle(\'display\')==\'none\'){\$(\'item_list_tr\').setStyle({\'display\':\'\'});\$(\'item_map\').value=1;\$(\'item_map_cntrl\').innerHTML=\'Hide\';agent.call(\'payables\', \'sf_loadcontent\', \'cf_loadcontent\', \'item_list\', \'po_hash=' + po_hash + '\', \'proposal_hash=' + proposal_hash + '\', \'popup_id={$this->popup_id}\');}else{\$(\'item_list_tr\').setStyle({\'display\':\'none\'});\$(\'item_map\').value=0;\$(\'item_map_cntrl\').innerHTML=\'Map\';\$(\'item_list_holder\').innerHTML=\'<div style=margin:15px 25px;><img src=images/content_loader.gif /></div>\';}\" title=\"Identify individual line items on this payable \"><span id=\"item_map_cntrl\">' + display_pref + '</span> Line Items</a></small>';
			\$('view_po_holder').innerHTML='<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call(\'purchase_order\', \'sf_loadcontent\', \'show_popup_window\', \'edit_po\', \'po_hash='+po_hash+'\', \'proposal_hash='+proposal_hash+'\', \'popup_id=po_win\');\">View Purchase Order</a></small>';

			if (display_pref == 'Hide') {
                \$('item_list_tr').setStyle({'display' : ''});
                agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'item_list', 'proposal_hash='+proposal_hash, 'po_hash='+po_hash, 'popup_id={$this->popup_id}');
            }
		}
		tally_totals = function() {
            var items = document.getElementsByName('ap_invoice_item[]');
            var t = 0;
            for (var i = 0; i < items.length; i++) {
                if (\$(items[i]).checked) {
                    if (\$(items[i]).readAttribute('i_cost'))
                        t += parseFloat( \$(items[i]).readAttribute('i_cost') );

                }
            }
            \$('amount_' + \$F('first_rand_err')).value = formatCurrency(t);
            \$('invoice_amount').value = formatCurrency(totalexp(1, 1));
		}
		totalexp = function() {

    		var ignore_amt = arguments[0];
    		var local = arguments[1];

    		var t = 0;

            \$('expense_dist_amount').childElements().each( function(n, i) {
                if ( \$F(n.firstDescendant()) ) {
                    t +=  \$F( n.firstDescendant() );
            	}
        	} );
        	
            if (t) {

                \$('total_amount_holder').innerHTML = '$' + formatCurrency(t);
                if ( ! ignore_amt ) {
                    \$('invoice_amount').value = formatCurrency(t);
                }
            } else
                \$('total_amount_holder').innerHTML = '$0.00';
			
            if ( local )
                return t;
    	}
		toggle_item_list = function() {
            var proposal_hash = \$F('proposal_hash');
            var po_hash = arguments[0];
            var display_pref = '".(($this->current_invoice['po_hash'] && $this->current_invoice['item_map']) || $item_map ? "Hide" : "Map")."';
            if (!po_hash || po_hash == '') {
                \$('item_list_tr').setStyle({'display' : 'none'});
                \$('show_item_holder').setStyle({'display' : 'none'});
                \$('item_list_holder').innerHTML='<div style=\"margin:15px 25px;\"><img src=images/content_loader.gif /></div>';
                return;
            }

            if (\$('show_item_holder'))
                \$('show_item_holder').innerHTML = '<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"if(\$(\'item_list_tr\').getStyle(\'display\')==\'none\'){\$(\'item_list_tr\').setStyle({\'display\':\'\'});\$(\'item_map\').value=1;\$(\'item_map_cntrl\').innerHTML=\'Hide\';agent.call(\'payables\', \'sf_loadcontent\', \'cf_loadcontent\', \'item_list\', \'po_hash=' + po_hash + '\', \'proposal_hash=' + proposal_hash + '\', \'popup_id=' + \$F('popup_id') + '\')}else{\$(\'item_list_tr\').setStyle({\'display\':\'none\'});\$(\'item_map\').value=0;\$(\'item_map_cntrl\').innerHTML=\'Map\';\$(\'item_list_holder\').innerHTML=\'<div style=margin:15px 25px;><img src=images/content_loader.gif /></div>\'}\" title=\"Identify individual line items on this payable\" ><span id=\"item_map_cntrl\">' + display_pref + '</span> Line Items</a></small>';

            if (display_pref == 'Hide')
                agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'item_list', 'proposal_hash='+proposal_hash, 'po_hash='+po_hash, 'popup_id=' + \$F('popup_id'));

		}
		setCurrency = function() {
            var currency = arguments[0];
            if (\$('currency')) {
                \$('currency').value = currency;
                \$('currency').disabled = 1;
            }
		}
		toggle_ap_display = function() {
            var invoice_type = arguments[0];
            if (invoice_type == 'D' || invoice_type == 'C'){
                if(invoice_type == 'D') {
                    toggle_display('invoice_no_holder{$this->popup_id}', 'none');
                    toggle_display('po_holder{$this->popup_id}', '');
            	}
            	if (invoice_type == 'C') {
                	toggle_display('invoice_no_holder{$this->popup_id}', '');
                	\$('invoice_no_txt{$this->popup_id}').innerHTML = 'Reference';
                } else {
                    \$('invoice_no_txt{$this->popup_id}').innerHTML = 'Invoice';
                }
                toggle_display('invoice_date_tr{$this->popup_id}', 'none');
                toggle_display('invoice_receipt_tr{$this->popup_id}', 'none');
                \$('invoice_amt_txt{$this->popup_id}').innerHTML = '';
                \$('invoice_date_txt{$this->popup_id}').innerHTML = '';
            } else {
                toggle_display('invoice_no_holder{$this->popup_id}', '');
                \$('invoice_no_txt{$this->popup_id}').innerHTML = 'Invoice';
                toggle_display('po_holder{$this->popup_id}', '');
                toggle_display('invoice_date_tr{$this->popup_id}', '');
                toggle_display('invoice_receipt_tr{$this->popup_id}', '');
                \$('invoice_amt_txt{$this->popup_id}').innerHTML = 'Invoice';
                \$('invoice_date_txt{$this->popup_id}').innerHTML = 'Due';
            }
            submit_form(\$('invoice_hash').form, 'payables', 'exec_post', 'refresh_form', 'action=vendor_due_date', 'change=type');
		}";

        if ($this->invoice_hash && ($this->current_invoice['currency'] || $this->current_invoice['vendor_currency'])) {
            $sys = new system_config();
            $currency_ops[] = $sys->home_currency['code'];
            if ($this->current_invoice['vendor_currency'] && !in_array($this->current_invoice['vendor_currency'], $currency_ops))
                $currency_ops[] = $this->current_invoice['vendor_currency'];
            if ($this->current_invoice['currency'] && !in_array($this->current_invoice['vendor_currency'], $currency_ops))
                $currency_ops[] = $this->current_invoice['currency'];

            if (count($currency_ops) < 2)
                unset($currency_ops);
        }

		$tbl = $this->form->form_tag() .
		$this->form->hidden( array(
			"invoice_hash"		=>  $this->invoice_hash,
			"proposal_hash"		=>  $this->proposals->proposal_hash,
			"active_search"		=>	$this->ajax_vars['active_search'],
			"popup_id" 			=>  $this->popup_id,
			"p"					=>  $this->ajax_vars['p'],
			"order"				=>	$this->ajax_vars['order'],
			"order_dir"			=>	$this->ajax_vars['order_dir'],
			"changed"			=>  '',
		    "from_class"   		=>  ( $from_class ? $from_class : 'payables' ),
		    "from_section"  	=>  $from_section,
		    "item_map"      	=>  ( ( $this->invoice_hash && $this->current_invoice['po_hash'] && $this->current_invoice['item_map'] ) || $item_map ? '1' : NULL )
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
								<div style=\"float:right;margin-top:".($this->invoice_hash ? "35px" : "15px").";margin-right:10px;margin-bottom:10px;\">" .
								( $this->p->active_lock ?
									"<div style=\"text-align:right;padding-right:15px;\">" .
									$this->p->lock_stat(
										get_class($this),
										$this->popup_id
									) . "
									</div>"
									:
									( ( $this->invoice_hash && $this->lock && ! $this->current_invoice['deleted'] && $this->p->ck( get_class($this), 'E') ) || ! $this->invoice_hash ?
										$this->form->button(
											"value=Save" . ( $this->invoice_hash || $from_class == 'proposals' ? " &amp; Close" : NULL ),
											"id=invoice_btn",
											"onClick=if(\$F('invoice_hash') && \$F('changed')){if(confirm('You have changed this invoice. Are you sure you want to save the new changes?')){submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'btn=save');this.disabled=1}else {this.disabled=0}}else if(!\$F('invoice_hash') || !\$F('changed')){submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'btn=save');this.disabled=1}"
										) : NULL
									)
								) .
								( ! $this->invoice_hash && $from_class != 'proposals' ?
									"&nbsp;and&nbsp;&nbsp;" .
				                    $this->form->select(
						                "save_option",
			                            array("Close", "Add New", "Add New Same Vendor"),
			                            'close',
			                            array('close', 'add_new', 'same_vendor'),
			                            "blank=1"
			                        ) : NULL
			                    ) . "
								</div>
								<h3 style=\"margin-bottom:5px;color:#00477f;\">" .
			                    ( $this->invoice_hash ?
									( $this->current_invoice['type'] == 'R' ?
										"Customer Refund" :
										( $this->current_invoice['type'] == 'D' ?
											"Vendor Deposit" : "Vendor " . ( $this->current_invoice['type'] == 'C' ? "Credit" : "Invoice" )
										)
									) . " : " . stripslashes($this->current_invoice['vendor_name']) . " - " .
									( $this->current_invoice['type'] == 'R' ?
										$this->current_invoice['customer_invoice']['invoice_no'] : $this->current_invoice['invoice_no']
									)
									:
									( $invoice_type == 'R' ?
										"Create a New Customer Refund"
										:
										( $invoice_type == 'C' ?
											"Enter Vendor Credits" : "Create a New Vendor Invoice/Deposit"
										)
									)
								) . "
								</h3>" .
								( $this->invoice_hash ?
									"<div style=\"padding:5px 0 5px 5px;\">&nbsp;" .
									( $this->lock && $this->invoice_hash && ! $active_payments && ! bccomp($this->current_invoice['amount'], $this->current_invoice['balance'], 2) && ! $pay_queue && ! $this->current_invoice['deleted'] && $this->p->ck(get_class($this), 'D') ?
										"<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to kill and delete this " . ( $invoice_type == 'R' ? "customer deposit" : "vendor invoice" ) . "? This action CAN NOT be undone!')){submit_form(\$('invoice_hash').form, 'payables', 'exec_post', 'refresh_form', 'btn=rm');}\" class=\"link_standard\"><img src=\"images/kill.gif\" title=\"Kill & Delete this " . ( $invoice_type == 'R' ? "customer deposit" : "vendor " . ( $invoice_type == 'D' ? "deposit" : "invoice" ) ) . ".\" border=\"0\" /></a>&nbsp;" : NULL
									) . "
									</div>
									<ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">
										<li class=\"selected\">
											<a href=\"javascript:void(0);\" id=\"cntrl_tcontent1{$this->popup_id}\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\" >" .
											( $this->current_invoice['type'] == 'C' ?
												"Credit" : "Invoice"
											) . " Details
											</a>
										</li>" .
										( $this->current_invoice['type'] != 'C' ?
											"<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent2{$this->popup_id}\"  rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\" >Payment Log</a></li>" : NULL
										) . "
									</ul>" : NULL
								);

							if ( $invoice_type == 'R' ) {

								$tbl .= "
								<div " . ( $this->invoice_hash ? "id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\"" : NULL ) . ">
									<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:95%;\" >
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;\" id=\"err1{$this->popup_id}\">Customer:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
											( bccomp($this->current_invoice['amount'], $this->current_invoice['balance'], 2) ?
												$this->form->hidden( array(
													"po_vendor" => $this->current_invoice['vendor_hash']
												) ) .
												stripslashes($this->current_invoice['vendor_name'])
												:
												$this->form->text_box(
													"name=po_vendor",
													"value=" . stripslashes($this->current_invoice['vendor_name']),
													"autocomplete=off",
													"size=30",
													"onChange=selectItem('changed=1');",
													"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'po_vendor', 'po_vendor_hash', 1, 'type');}",
                                                    "onKeyUp=if(ka==false && this.value){key_call('payables', 'po_vendor', 'po_vendor_hash', 1, 'type');}",
                        							"onBlur=key_clear();",
													"onKeyDown=clear_values('po_vendor_hash');"
												) .
												$this->form->hidden( array(
													"po_vendor_hash" => $this->current_invoice['vendor_hash']
												) )
											) .
											$this->form->hidden( array(
												'type' => 'R'
											) ) . "
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err2{$this->popup_id}\">Original Invoice No:</td>
											<td style=\"background-color:#ffffff;\">
												<div id=\"vendor_po_holder\">" .
												( bccomp($this->current_invoice['amount'], $this->current_invoice['balance'], 2) ?
													$this->form->hidden( array(
														"po_hash" => $this->current_invoice['po_hash']
													) ) .
													$this->current_invoice['customer_invoice']['invoice_no']
													:
													$this->form->text_box(
														"name=po_no",
														"value={$this->current_invoice['customer_invoice']['invoice_no']}",
														"onChange=selectItem('changed=1');",
														"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'po_no', 'po_hash', 1, 'type', 'po_vendor_hash'".($this->invoice_hash ? ", 'invoice_hash'" : NULL).");}",
                                                        "onKeyUp=if(ka==false && this.value){key_call('payables', 'po_no', 'po_hash', 1, 'type', 'po_vendor_hash'" . ( $this->invoice_hash ? ", 'invoice_hash'" : NULL ) . ");}",
                       									"onBlur=key_clear();",
														"onKeyDown=clear_values('po_hash');"
													) .
													$this->form->hidden( array(
														"po_hash" => $this->current_invoice['po_hash']
													) )
												) . "
												</div>
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err3{$this->popup_id}\">Refund Amount:</td>
											<td style=\"background-color:#ffffff;\">" .
												$this->form->text_box(
													"name=invoice_amount",
													"onChange=" . ( $this->invoice_hash ? "selectItem('changed=1');" : NULL ),
													"value=" . ( $this->current_invoice['amount'] ? number_format($this->current_invoice['amount'], 2) : NULL ),
													"onFocus=if ( this.value ) {this.select();}",
													"size=10",
													"style=text-align:right;",
													( bccomp($this->current_invoice['amount'], $this->current_invoice['balance'], 2) ? "readonly" : NULL ),
													( ! bccomp($this->current_invoice['amount'], $this->current_invoice['balance'], 2) ? "onBlur=if(this.value){ this.value=formatCurrency(this.value);if ( \$('amount_{$rand_err_new}') && ! \$F('amount_{$rand_err_new}') ) {\$('amount_{$rand_err_new}').value=this.value; totalexp(1);} }" : NULL )
												) . "
											</td>
										</tr>
										<tr id=\"invoice_date_tr{$this->popup_id}\">
											<td style=\"text-align:right;background-color:#efefef;font-weight:bold;\" id=\"err5{$this->popup_id}\">Refund Date:</td>
											<td style=\"background-color:#ffffff;\" id=\"invoice_date_holder{$this->popup_id}\">";
											$jscript[] = "setTimeout('DateInput(\'invoice_date\', \'true\', \'YYYY-MM-DD\', \'".($this->current_invoice['invoice_date'] && $this->current_invoice['invoice_date'] != '0000-00-00' ? $this->current_invoice['invoice_date'] : date("Y-m-d"))."\', 1, \'invoice_date_holder{$this->popup_id}\')', 45);";
											$tbl .= "
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err7{$this->popup_id}\">Due Date:</td>
											<td style=\"background-color:#ffffff;\" id=\"due_date_holder{$this->popup_id}\">";
											$jscript[] = "setTimeout('DateInput(\'due_date\', \'true\', \'YYYY-MM-DD\', \'".($this->current_invoice['due_date'] && $this->current_invoice['due_date'] != '0000-00-00' ? $this->current_invoice['due_date'] : date("Y-m-d", (defined('CUSTOMER_REFUND_PAY_DAYS') ? strtotime(date("Y-m-d")." +".CUSTOMER_REFUND_PAY_DAYS." days") : strtotime(date("Y-m-d")))))."\', 1, \'due_date_holder{$this->popup_id}\')', 25);";
											$tbl .= "
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;vertical-align:top;\" >Notes:</td>
											<td style=\"background-color:#ffffff;\">".$this->form->text_area("name=notes", "value=".$this->current_invoice['notes'], "rows=3", "cols=40")."</td>
										</tr>";
							} else {

								$rand_err_new = rand(500, 5000000);

								$tbl .= "
								<div " . ( $this->invoice_hash ? "id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\"" : NULL ) . ">" .
									$this->form->hidden( array(
										'first_rand_err' => $rand_err_new
									) ) . "
									<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:95%;\" >
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%;\" id=\"err1{$this->popup_id}\">Vendor:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
											( bccomp($this->current_invoice['amount'], $this->current_invoice['balance'], 2) ?
												$this->form->hidden( array(
													"po_vendor_hash" => $this->current_invoice['vendor_hash']
												) ) .
												stripslashes($this->current_invoice['vendor_name'])
												:
												( $this->proposals->proposal_hash && ! $from_section ?
													( $po_vendor_hash ?
    													$this->form->select(
	    													"po_vendor_hash",
															$po_vendor_name,
															$this->current_invoice['vendor_hash'],
															$po_vendor_hash,
															"onChange=agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'innerHTML_vendor_po', 'proposal_hash={$this->proposals->proposal_hash}', 'vendor_hash='+this.options[this.selectedIndex].value, 'qtype=vendor', 'popup_id={$this->popup_id}'" . ( $this->invoice_hash ? ", 'current_invoice_hash={$this->invoice_hash}'" : NULL ) . ");\$('view_po_holder').innerHTML='';"
														) : "<img src=\"images/alert.gif\" >&nbsp;There are no POs under this proposal"
													)
													:
													$this->form->text_box(
														"name=po_vendor",
														"value=" . stripslashes($this->current_invoice['vendor_name']),
														"autocomplete=off",
														"size=30",
														"onChange=selectItem('changed=1');",
														"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'po_vendor', 'po_vendor_hash', 1);}",
                                                        "onKeyUp=if(ka==false && this.value){key_call('payables', 'po_vendor', 'po_vendor_hash', 1);}",
														"onBlur=key_clear();if(!this.value || this.value == '' || !\$F('po_vendor_hash')){clear_values('po_hash', 'po_no');\$('po_no').disabled=true;\$('po_no').title='Select vendor first!';if(\$('item_list_tr').getStyle('display') == ''){toggle_display('item_list_tr', 'none');}\$('view_po_holder').innerHTML='';\$('show_item_holder').innerHTML='';}",
														"onKeyDown=clear_values('po_vendor_hash');"
													) .
													$this->form->hidden( array(
														"po_vendor_hash" => $this->current_invoice['vendor_hash']
													) )
												)
											) . "
												<span id=\"vendor_note_holder\"></span>
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err8{$this->popup_id}\">Type:</td>
											<td style=\"background-color:#ffffff;\">".(!$this->invoice_hash ?
												$this->form->select("type",
																	($this->proposals->proposal_hash ? array("Bill", "Deposit") : array("Bill", "Deposit", "Credit")),
																	($this->current_invoice['type'] ? $this->current_invoice['type'] : $invoice_type),
																	($this->proposals->proposal_hash ? array("I", "D") : array("I", "D", "C")),
																	"blank=1",
																	"id=invoice_type",
																	"onChange=toggle_ap_display(this.options[this.selectedIndex].value)") :
												$this->form->hidden(array('type' => $this->current_invoice['type'])).($this->current_invoice['type'] == "I" ?
													"Invoice" : ($this->current_invoice['type'] == 'C' ? "Credit" : "Deposit")))."
											</td>
										</tr>".((!$this->invoice_hash && defined('MULTI_CURRENCY')) || ($this->current_invoice['currency'] && $currency_ops) ? "
										<tr id=\"currency_holder\" style=\"display:".($this->current_invoice['currency'] ? "block" : "none")."\">
                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;\">Currency: </td>
                                            <td style=\"background-color:#ffffff;\" id=\"currency_select\">".($this->invoice_hash ?
												$this->current_invoice['currency'] : $this->form->select("currency",
																		                                 $currency_ops,
																		                                 $this->current_invoice['currency'],
																		                                 $currency_ops,
																		                                 "blank"))."
										</tr>" : NULL).($this->invoice_hash && $this->current_invoice['currency'] ? "
                                        <tr >
                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;\">Rate: </td>
                                            <td style=\"background-color:#ffffff;\">".rtrim(trim($this->current_invoice['exchange_rate'], '0'), '.')."%</td>
										</tr>" : NULL)."
										<tr id=\"po_holder{$this->popup_id}\">
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;vertical-align:top;\" id=\"err2{$this->popup_id}\">PO No:</td>
											<td style=\"background-color:#ffffff;\">
												<span id=\"vendor_po_holder\">".($this->current_invoice['amount'] != $this->current_invoice['balance'] ?
													$this->form->hidden(array("po_hash" => $this->current_invoice['po_hash'],
													                          "po_no"   => $this->current_invoice['po_no'])).$this->current_invoice['po_no'] : ($this->invoice_hash && $this->proposals->proposal_hash && !$from_section ?
														$this->innerHTML_vendor_po('vendor', $this->proposals->proposal_hash, $this->current_invoice['vendor_hash'], $this->current_invoice['po_hash'], $this->popup_id, ($this->current_invoice['amount'] != $this->current_invoice['balance'] ? 1 : NULL)) : ($this->proposals->proposal_hash && !$from_section ?
															$this->form->select("vendor_po_hash",
																				array("Select Vendor First..."),
																				NULL, array(""),
																				"disabled",
																				"style=width:195px;"
																				) :
															$this->form->text_box("name=po_no",
																				  "value=".$this->current_invoice['po_no'],
																				  "onChange=selectItem('changed=1');",
																				  (!$this->invoice_hash ? "disabled title=Select vendor first!" : NULL),
																				  "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'po_no', 'po_hash', 1, 'type', 'po_vendor_hash'".($this->invoice_hash ? ", 'invoice_hash'" : NULL).");}",
																				  "onKeyUp=if(ka==false && this.value){key_call('payables', 'po_no', 'po_hash', 1, 'type', 'po_vendor_hash'".($this->invoice_hash ? ", 'invoice_hash'" : NULL).");}",
																				  "onBlur=key_clear();if(!this.value || this.value == ''){clear_values('po_hash');if(\$('currency')){\$('currency').disabled=0;}if(!\$F('po_vendor_hash') || \$F('po_vendor_hash') == ''){this.disabled=true;this.title='Select vendor first!';}if(\$('item_list_tr').getStyle('display') == ''){toggle_display('item_list_tr', 'none');}\$('view_po_holder').innerHTML='';\$('show_item_holder').innerHTML='';}",
																				  "onKeyDown=clear_values('po_hash');"
																				  ).
															$this->form->hidden(array("po_hash" => ($this->current_invoice['po_hash'] ? $this->current_invoice['po_hash'] : NULL))))))."
												</span>
                                                <span style=\"padding-left:10px;\" id=\"view_po_holder\">".($this->current_invoice['po_hash'] && $this->p->ck('purchase_order', 'V') ? "
                                                    <small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'edit_po', 'po_hash=".$this->current_invoice['po_hash']."', 'proposal_hash=".$this->current_invoice['invoice_proposal']."', 'popup_id=po_win');\">View Purchase Order</a></small>" : NULL)."
                                                </span>
                                                <span style=\"padding-left:10px;\" id=\"show_item_holder\">".($this->current_invoice['po_hash'] ? "
                                                    <small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"if(\$('item_list_tr').getStyle('display')=='none'){\$('item_list_tr').setStyle({'display':''});\$('item_map').value=1;\$('item_map_cntrl').innerHTML='Hide';agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'item_list', 'po_hash=".$this->current_invoice['po_hash']."', 'proposal_hash=".$this->current_invoice['invoice_proposal']."', 'invoice_hash=".$this->invoice_hash."', 'popup_id={$this->popup_id}');}else{\$('item_list_tr').setStyle({'display':'none'});\$('item_map').value=0;\$('item_map_cntrl').innerHTML='Map';\$('item_list_holder').innerHTML='<div style=\'margin:15px 25px;\'><img src=\'images/content_loader.gif\' /></div>';}\" title=\"Identify individual line items on this payable \"><span id=\"item_map_cntrl\">".($this->current_invoice['po_hash'] && $this->current_invoice['item_map'] ? "Hide" : "Map")."</span> Line Items</a></small>" : NULL)."
                                                </span>
											</td>
										</tr>
										<tr id=\"item_list_tr\" ".($this->current_invoice['po_hash'] && $this->current_invoice['item_map'] ? "" : "style=\"display:none")."\">
                                            <td colspan=\"2\" style=\"background-color:#ffffff;padding:0;\">
                                                <div id=\"item_list_holder\" style=\"margin:0\">".($this->current_invoice['po_hash'] && $this->current_invoice['item_map'] ?
                                                    $this->item_list($this->invoice_hash, $this->current_invoice['po_hash'], $this->current_invoice['invoice_proposal'], $this->popup_id) : "<div style=\"margin:15px 25px;\"><img src=\"images/content_loader.gif\" /></div>")."
                                                </div>
                                            </td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" nowrap>Hold Payment:</td>
											<td style=\"background-color:#ffffff;\">".$this->form->checkbox("name=hold",
											                                                                "value=1", ($this->current_invoice['hold'] ? "checked" : NULL),
															                                                ($this->current_invoice['amount'] != $this->current_invoice['balance'] ? "disabled" : NULL))."
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;vertical-align:top;\" id=\"err3{$this->popup_id}\" nowrap><span id=\"invoice_amt_txt{$this->popup_id}\">".($this->current_invoice['type'] == 'I' ? "Invoice" : ($this->current_invoice['type'] == 'D' ? "Deposit" : "Invoice"))."</span> Amount:</td>
											<td style=\"background-color:#ffffff;\">" .
												$this->form->text_box(
													"name=invoice_amount",
													"onChange=" . ( $this->invoice_hash ? "selectItem('changed=1');" : NULL ),
													"value=" . ( $this->current_invoice['amount'] ? number_format($this->current_invoice['amount'], 2) : NULL ),
													"size=10",
													"onFocus=if(this.value){this.select();}",
													"style=text-align:right;",
													"onBlur=if(this.value){ this.value=formatCurrency(this.value);if ( \$('amount_{$rand_err_new}') && ! \$F('amount_{$rand_err_new}') && ( ! \$F('item_map') || ( \$F('item_map') && ! \$F('po_hash') ) ) ){\$('amount_{$rand_err_new}').value=this.value; totalexp(1);} }"
												) . "
												<div id=\"deposit_amt_holder\" style=\"display:".($this->invoice_hash && $this->current_invoice['po_hash'] ? "block" : "none").";margin-top:5px;\">".($this->invoice_hash && $this->current_invoice['po_hash'] && $this->current_invoice['type'] == 'I' ? $this->check_deposits($this->current_invoice['po_hash']) : NULL)."</div>
											</td>
										</tr>"
										.($this->invoice_hash ? "
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" nowrap>Open Balance</td>
											<td style=\"background-color:#ffffff;\">".($this->current_invoice['balance'] < 0 ?
												"($".number_format($this->current_invoice['balance'] * -1, 2).")" : "$".number_format($this->current_invoice['balance'], 2))."
											</td>
										</tr>" : NULL)."
										<tr id=\"invoice_no_holder{$this->popup_id}\" ".(!$this->invoice_hash || $this->current_invoice['type'] == "I" ? "" : "style=\"display:none\"").">
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err4{$this->popup_id}\"><span id=\"invoice_no_txt{$this->popup_id}\">".($invoice_type == 'C' || $this->current_invoice['type'] == 'C' ? "Reference" : "Invoice")."</span> No:</td>
											<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=invoice_no", "value=".$this->current_invoice['invoice_no'], "onChange=selectItem('changed=1');", ($this->current_invoice['amount'] != $this->current_invoice['balance'] ? "readonly" : NULL))."</td>
										</tr>
										<tr id=\"invoice_date_tr{$this->popup_id}\" " . ( ( ! $this->invoice_hash || $this->current_invoice['type'] == "I" || $this->current_invoice['type'] == "D" ) && $invoice_type != 'C' && $this->current_invoice['type'] != 'C' ? "" : "style=\"display:none\"" ) . ">
											<td style=\"text-align:right;background-color:#efefef;font-weight:bold;\" id=\"err5{$this->popup_id}\" nowrap>Invoice Date:</td>
											<td style=\"background-color:#ffffff;\" id=\"invoice_date_holder{$this->popup_id}\">" .
											( $this->invoice_hash ?
    											"<span style=\"margin-left:5px;\">" .
                                                date('M j, Y', strtotime($this->current_invoice['invoice_date']) ) .
                                                $this->form->hidden( array(
                                                    'invoice_date' => $this->current_invoice['invoice_date']
                                                ) ) .
                                                "</span>" : NULL
                                            ) . "
											</td>
										</tr>
										<tr id=\"invoice_receipt_tr{$this->popup_id}\" ".((!$this->invoice_hash || $this->current_invoice['type'] == "I") && $invoice_type != 'C' && $this->current_invoice['type'] != 'C' ? "" : "style=\"display:none\"").">
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err6{$this->popup_id}\" nowrap>Date of Receipt:</td>
											<td style=\"background-color:#ffffff;\" id=\"receipt_date_holder{$this->popup_id}\">";
											    $jscript[] = "setTimeout('DateInput(\'receipt_date\', \'true\', \'YYYY-MM-DD\', \'".($this->current_invoice['receipt_date'] && $this->current_invoice['receipt_date'] != '0000-00-00' ? $this->current_invoice['receipt_date'] : date("Y-m-d"))."\', 1, \'receipt_date_holder{$this->popup_id}\')', 18);";
											$tbl .= "
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err7{$this->popup_id}\" nowrap><span id=\"invoice_date_txt{$this->popup_id}\">".($this->invoice_hash && $this->current_invoice['type'] == 'C' ? "" : "Due")."</span> Date:</td>
											<td style=\"background-color:#ffffff;\" id=\"due_date_holder{$this->popup_id}\">";
											    $jscript[] = "setTimeout('DateInput(\'due_date\', \'true\', \'YYYY-MM-DD\', \'".($this->current_invoice['due_date'] && $this->current_invoice['due_date'] != '0000-00-00' ? $this->current_invoice['due_date'] : date("Y-m-d"))."\', 1, \'due_date_holder{$this->popup_id}\')', 25);";
											$tbl .= "
											</td>
										</tr>
										<tr id=\"man_credit{$this->popup_id}\" " . ( ( ! $this->invoice_hash || $this->current_invoice['type'] == "I" || $this->current_invoice['type'] == "D" ) && $invoice_type != 'C' && $this->current_invoice['type'] != 'C' ? "" : "style=\"display:none\"" ) . ">
											<td style=\"text-align:right;background-color:#efefef;font-weight:bold;\" id=\"err8{$this->popup_id}\" nowrap>Exclude Manual Credit?:</td>
											<td style=\"background-color:#ffffff;\">".$this->form->checkbox("name=exclude_credit",
											                                                                "value=1", ($this->current_invoice['exclude_credit'] ? "checked" : NULL),
															                                                ($this->current_invoice['amount'] != $this->current_invoice['balance'] ? "disabled" : NULL))."
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;vertical-align:top;\" >Notes:</td>
											<td style=\"background-color:#ffffff;\">" .
    											$this->form->text_area(
        											"name=notes",
        											"value=" . stripslashes($this->current_invoice['notes']),
        											"rows=3",
        											"cols=40",
        											( $this->invoice_hash ? "onChange=selectItem('changed=1');" : NULL )
        										) . "
        									</td>
										</tr>";

                                    if (!$this->invoice_hash)
                                        $jscript[] = "setTimeout('DateInput(\'invoice_date\', \'true\', \'YYYY-MM-DD\', \'".($this->current_invoice['invoice_date'] && $this->current_invoice['invoice_date'] != '0000-00-00' ? $this->current_invoice['invoice_date'] : date("Y-m-d"))."\', 1, \'invoice_date_holder{$this->popup_id}\', \'\', \'if(\$F(\"po_vendor_hash\")){agent.call(\"payables\", \"sf_loadcontent\", \"cf_loadcontent\", \"vendor_due_date\", \"popup_id={$this->popup_id}\", \"vendor_hash=\"+\$F(\"po_vendor_hash\"), \"invoice_date=\"+document.getElementsByName(\"invoice_date\")[0].value)}\')', 45);";
								}

								for ($i = 0; $i < count($expenses); $i++) {
                                    if ($i == 0)
                                        $rand_err = $rand_err_new;
                                    else
                                        $rand_err = rand(500, 5000000);

									$expense_dist_account .= "
									<div style=\"margin-bottom:5px;\" nowrap>".
										$this->form->text_box("name=account_name_".$rand_err,
																"value=".$expenses[$i]['account_name'],
																"autocomplete=off",
																"size=25",
																"onChange=selectItem('changed=1');",
																($this->invoice_hash && !$this->p->ck(get_class($this), 'E') ? "readonly" : NULL),
																(!$this->invoice_hash || ($this->invoice_hash && $this->lock && $this->p->ck(get_class($this), 'E')) ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', 1);}" : NULL),
																(!$this->invoice_hash || ($this->invoice_hash && $this->lock && $this->p->ck(get_class($this), 'E')) ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', 1);}" : NULL),
																(!$this->invoice_hash || ($this->invoice_hash && $this->lock && $this->p->ck(get_class($this), 'E')) ? "onBlur=key_clear();" : NULL)).
										$this->form->hidden(array("account_hash_".$rand_err => $expenses[$i]['account_hash'], "error_node[]" => $rand_err, "expense_id_".$rand_err => $expenses[$i]['expense_hash']))."
										".(!$this->invoice_hash || ($this->invoice_hash && $this->lock && $this->p->ck(get_class($this), 'E')) ? "
										<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('account_name_".$rand_err."'), 'top')+20, findPos(\$('account_name_".$rand_err."'), 'left')+1);key_list('payables', 'account_name_".$rand_err."', 'account_hash_".$rand_err."', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
									</div>";
									$expense_dist_amount .= "
									<div style=\"margin-bottom:5px;\">".
									    $this->form->text_box("name=amount_".$rand_err,
									                          "onChange=selectItem('changed=1');",
									                          ($this->invoice_hash && !$this->p->ck(get_class($this), 'E') ? "readonly" : NULL),
									                          "value=".number_format($expenses[$i]['amount'], 2),
									                          "size=10",
									                          "style=text-align:right",
									                          "onFocus=if(this.value){select();}else{submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=innerHTML_vendor_po', 'qtype=balance', 'focus=".$rand_err."');}",
									                          "onBlur=this.value=formatCurrency(this.value);",
									                          "onChange=totalexp(1);")."
									</div>";
									$expense_dist_memo .= "
									<div style=\"margin-bottom:5px;\">".
									    $this->form->text_box("name=memo_".$rand_err,
									                          "onChange=selectItem('changed=1');",
									                          ($this->invoice_hash && !$this->p->ck(get_class($this), 'E') ? "readonly" : NULL),
									                          "value=".$expenses[$i]['memo'],
									                          "size=23")."
									</div>";
									$expense_dist_proposal .= "
									<div style=\"margin-bottom:5px;\">".
										$this->form->text_box("name=proposal_no_".$rand_err,
																"value=".($this->current_invoice['po_no'] ? "" : $expenses[$i]['proposal_no']),
																"autocomplete=off",
																"size=10",
																"onChange=selectItem('changed=1');",
																($this->invoice_hash && !$this->p->ck(get_class($this), 'E') ? "readonly" : NULL),
																(!$this->invoice_hash || ($this->invoice_hash && $this->lock && $this->p->ck(get_class($this), 'E')) ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'proposal_no_".$rand_err."', 'proposal_hash_".$rand_err."', 1);}" : NULL),
																(!$this->invoice_hash || ($this->invoice_hash && $this->lock && $this->p->ck(get_class($this), 'E')) ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'proposal_no_".$rand_err."', 'proposal_hash_".$rand_err."', 1);}" : NULL),
																(!$this->invoice_hash || ($this->invoice_hash && $this->lock && $this->p->ck(get_class($this), 'E')) ? "onBlur=key_clear();" : NULL)).
										$this->form->hidden(array("proposal_hash_".$rand_err => $expenses[$i]['proposal_hash']))."
									</div>";
								}

								if ( ! $this->invoice_hash ) {

									for ( $i = 0; $i < 3; $i++ ) {

										$expense_dist_account .= "
										<div style=\"margin-bottom:5px;\" nowrap>" .
											$this->form->text_box(
												"name=account_name_{$rand_err_new}",
												"value=",
												"autocomplete=off",
												"size=25",
												"onBlur=key_clear();",
												"onFocus=if(this.value){select();}else{submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=innerHTML_vendor_po', 'qtype=balance', 'focus=".$rand_err_new."');}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'account_name_".$rand_err_new."', 'account_hash_".$rand_err_new."', 1);}",
											    "onKeyUp=if(ka==false && this.value){key_call('payables', 'account_name_{$rand_err_new}', 'account_hash_{$rand_err_new}', 1);}",
												"onKeyDown=clear_values('account_hash_{$rand_err_new}');"
											) .
											$this->form->hidden( array(
												"account_hash_{$rand_err_new}"	=> '',
												"error_node[]" 					=> $rand_err_new
											) ) . "
											<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('account_name_".$rand_err_new."'), 'top')+20, findPos(\$('account_name_".$rand_err_new."'), 'left')+1);key_list('payables', 'account_name_".$rand_err_new."', 'account_hash_".$rand_err_new."', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>
										</div>";

										$expense_dist_amount .= "
										<div style=\"margin-bottom:5px;\">" .
    										$this->form->text_box(
	    										"name=amount_{$rand_err_new}",
                            					"value=" . ( $this->ajax_vars['remaining_amt'] ? number_format($this->ajax_vars['remaining_amt'], 2) : NULL ),
                            					"size=10",
        										"value=",
                            					"style=text-align:right",
                            					"onFocus=if(this.value){select();}else{submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=innerHTML_vendor_po', 'qtype=balance', 'focus=".$rand_err_new."');}",
                            					"onBlur=this.value=formatCurrency(this.value)",
										        "onChange=totalexp(1);"
    										) . "
                                        </div>";

										$expense_dist_memo .= "
										<div style=\"margin-bottom:5px;\">" .
											$this->form->text_box(
												"name=memo_{$rand_err_new}",
												"value=",
												"size=23"
											) . "
										</div>";

										$expense_dist_proposal .= "
										<div style=\"margin-bottom:5px;\">" .
											$this->form->text_box(
												"name=proposal_no_".$rand_err_new,
												"value=",
												"autocomplete=off",
												"size=10",
												"onBlur=key_clear();",
												"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'proposal_no_".$rand_err_new."', 'proposal_hash_".$rand_err_new."', 1);}",
										        "onKeyUp=if(ka==false && this.value){key_call('payables', 'proposal_no_".$rand_err_new."', 'proposal_hash_".$rand_err_new."', 1);}",
												"onKeyDown=clear_values('proposal_hash_".$rand_err_new."');"
											) .
											$this->form->hidden( array(
												"proposal_hash_{$rand_err_new}" => ''
											) ) . "
										</div>";

										$rand_err_new = rand(500, 5000000);
									}
								}

								$tbl .= "
									<tr>
										<td colspan=\"2\" style=\"background-color:#ffffff;width:100%\">
											<div style=\"padding:5px 0;font-weight:bold;\" id=\"err3a{$this->popup_id}\">
    											Total Expenses:
    											<span id=\"total_amount_holder\">" .
    											( $this->invoice_hash ?
								                    $this->current_invoice['symbol'] . number_format($this->current_invoice['total_expense_amount'], 2) : "&nbsp;"
								                ) . "
    								            </span>
    										</div>
											<div id=\"expense_holder{$this->popup_id}\">";
											$inner_tbl = "
												<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:97%;\" >
													<tr class=\"thead\" style=\"font-size:9pt;\">
														<td class=\"smallfont\" style=\"width:230px;vertical-align:bottom;font-weight:bold;\">Account</td>
														<td class=\"smallfont\" style=\"width:70px;vertical-align:bottom;font-weight:bold;\">Amount</td>
														<td class=\"smallfont\" style=\"vertical-align:bottom;font-weight:bold;\">Memo</td>
														<td class=\"smallfont\" style=\"vertical-align:bottom;font-weight:bold;\">Proposal</td>
													</tr>
													<tr>
														<td style=\"vertical-align:bottom;\" id=\"expense_dist_account\">$expense_dist_account</td>
														<td id=\"expense_dist_amount\">$expense_dist_amount</td>
														<td id=\"expense_dist_memo\">$expense_dist_memo</td>
														<td id=\"expense_dist_proposal\">$expense_dist_proposal</td>
													</tr>";

												$inner_tbl .= "
												</table>" .
												( ( $this->lock && $this->invoice_hash && $this->p->ck(get_class($this), 'E') ) || ! $this->invoice_hash ?
													"<div style=\"float:right;padding:10px 25px;\">
														<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"selectItem('changed=1');submit_form(\$('invoice_hash').form, 'payables', 'exec_post', 'refresh_form', 'action=doit_add_line');\">[<small>add more lines</small>]</a>
													</div>" : NULL
												);

											$tbl .=
											( ! $local ?
												$inner_tbl : NULL
											) . "
											</div>
										</td>
									</tr>
								</table>
							</div>";

							if ($this->invoice_hash && $this->current_invoice['type'] != 'C') {
								$total_payments = count($this->current_invoice['payments']) - $this->current_invoice['voided_payments'];

								$tbl .= "
								<div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">
									<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:600px;margin-top:0;\" class=\"smallfont\">
										<tr>
											<td style=\"padding:0;\">
												 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\" >
													<tr>
														<td style=\"font-weight:bold;vertical-align:middle;background-color:#efefef;\" colspan=\"7\" class=\"smallfont\">".($total_payments <= 0 && $pay_queue ? "
															You have ".$pay_queue." outgoing payment".($pay_queue > 1 ? "s" : NULL)." to be made towards this invoice." : "
															You have ".($total_payments > 0 ? $total_payments." payment".($total_payments > 1 ? "s" : NULL) : "made no payments")." towards this invoice.")."
															<div style=\"margin-left:25px;padding-top:5px;font-weight:normal;font-style:italic;\">
																".($this->current_invoice['balance'] <= 0 ?
																	"This ".($this->current_invoice == 'R' ? "refund" : ($this->current_invoice['type'] == 'D' ? "deposit" : "invoice"))." ".($pay_queue ? "will be paid in full once checks have been printed" : "has been paid in full").($this->current_invoice['balance'] < 0 ?
																		" and has been overpaid by $".number_format($this->current_invoice['balance']*-1, 2) : NULL)."." : "This invoice has an outstanding balance of $".number_format($this->current_invoice['balance'], 2))."
															</div>
														</td>
													</tr>
													<tr class=\"thead\">
														<td style=\"width:35px;\"></td>
														<td style=\"font-weight:normal;\">Check No.</td>
														<td style=\"font-weight:normal;\">Date</td>
														<td style=\"font-weight:normal;text-align:right;\">Discounts</td>
														<td style=\"font-weight:normal;text-align:right;\">Deposits</td>
														<td style=\"font-weight:normal;text-align:right;\">Credits</td>
														<td style=\"font-weight:normal;text-align:right;\">Amount</td>
													</tr>";

												for ( $i = 0; $i < count($this->current_invoice['payments']); $i++ ) {

													$tbl .= "
													<tr " . ( $this->current_invoice['payments'][$i]['void'] ? "style=\"color:#8f8f8f;font-style:italic;\" title=\"This check has been voided\"" : NULL ) . ">
														<td style=\"width:35px;border-bottom:1px solid #cccccc;\" nowrap>" .
    													( ! $this->current_invoice['payments'][$i]['cleared'] && ! $this->current_invoice['payments'][$i]['void'] && ! $this->current_invoice['deleted'] && ! $this->current_invoice['payments'][$i]['deleted'] ?
        													"<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'empty_queue', 'popup_id=line_item', 'preview=2', 'reprint={$this->current_invoice['payments'][$i]['payment_hash']}'" . ( $from_class ? ", 'from_class=$from_class', 'proposal_hash={$this->proposals->proposal_hash}'" : NULL ) . ", 'active_search={$this->ajax_vars['active_search']}');\"><img src=\"images/print_sm.gif\" title=\"Reprint this check\" border=\"0\" /></a>" : NULL
    													) . "&nbsp;" .
														( ! $this->current_invoice['payments'][$i]['void'] && ! $this->current_invoice['payments'][$i]['cleared'] && ! $this->current_invoice['deleted'] && ! $this->current_invoice['payments'][$i]['deleted'] ?
															"<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to void this check? This action CANNOT be undone!')){submit_form(\$('invoice_hash').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_void', 'check_no={$this->current_invoice['payments'][$i]['check_no']}', 'account_hash={$this->current_invoice['payments'][$i]['account_hash']}', 'from_payables={$this->invoice_hash}', 'active_search={$this->ajax_vars['active_search']}', 'from_proposal={$this->proposals->proposal_hash}'" . ( $from_accounting ? ", 'from_accounting={$from_accounting}'" : NULL ) . ");}\"><img src=\"images/void_check.gif\" title=\"Void this check\" border=\"0\" /></a>" : NULL
														) . "
														</td>
														<td style=\"border-bottom:1px solid #cccccc;\">{$this->current_invoice['payments'][$i]['check_no']}</td>
														<td style=\"border-bottom:1px solid #cccccc;\">" . date(DATE_FORMAT, strtotime($this->current_invoice['payments'][$i]['check_date'])) . "</td>
														<td style=\"text-align:right;border-bottom:1px solid #cccccc;\">" .
														( bccomp($this->current_invoice['payments'][$i]['discount_taken'], 0, 2) == 1 ?
															"({$this->current_invoice['payments'][$i]['symbol']}" . number_format($this->current_invoice['payments'][$i]['discount_taken'], 2) . ")" : "&nbsp;"
														) . "
														</td>
														<td style=\"text-align:right;border-bottom:1px solid #cccccc;\">" .
														( bccomp($this->current_invoice['payments'][$i]['deposit_used'], 0, 2) == 1 ?
															"({$this->current_invoice['payments'][$i]['symbol']}" . number_format($this->current_invoice['payments'][$i]['deposit_used'], 2) . ")" : "&nbsp;"
														) . "
														</td>
														<td style=\"text-align:right;border-bottom:1px solid #cccccc;\">" .
														( bccomp($this->current_invoice['payments'][$i]['credit_used'], 0, 2) == 1 ?
															"({$this->current_invoice['payments'][$i]['symbol']}" . number_format($this->current_invoice['payments'][$i]['credit_used'], 2) . ")" : "&nbsp;"
														) . "
														</td>
														<td style=\"text-align:right;border-bottom:1px solid #cccccc;\">
    														{$this->current_invoice['payments'][$i]['symbol']}" . number_format($this->current_invoice['payments'][$i]['payment_amount'], 2) . "
    													</td>
													</tr>";

													if ( ! $this->current_invoice['payments'][$i]['void'] )
														$total_paid = bcadd($total_paid, $this->current_invoice['payments'][$i]['payment_amount'], 2);
												}

												if ( ! $this->current_invoice['payments'] )
													$tbl .= "
														<tr>
															<td colspan=\"7\" style=\"border-bottom:1px solid #cccccc;\" class=\"smallfont\">You have made no payments towards this invoice.</td>
														</tr>";

												$tbl .= "
													<tr>
														<td colspan=\"7\" style=\"background-color:#efefef;text-align:right;\">
                                                            <table>
                                                                <tr>
                                                                    <td style=\"text-align:right;font-weight:bold;padding-bottom:3px;\" >Total Invoice Amount:</td>
                                                                    <td style=\"font-weight:bold;text-align:left;padding-bottom:3px;\" >{$this->current_invoice['symbol']}" . number_format($this->current_invoice['amount'], 2) . "</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"text-align:right;font-weight:bold;padding-bottom:3px;\" >Total Paid:</td>
                                                                    <td style=\"font-weight:bold;text-align:left;padding-bottom:3px;\" >{$this->current_invoice['symbol']}" . number_format($total_paid, 2)."</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"text-align:right;font-weight:bold;\" >Balance Due:</td>
                                                                    <td style=\"font-weight:bold;text-align:left;\" >" .
                                                                    ( bccomp($this->current_invoice['balance'], 0, 2) == -1 ?
                                                                        "({$this->current_invoice['symbol']}" . number_format( bcmul($this->current_invoice['balance'], -1, 2), 2) . ")" : $this->current_invoice['symbol'] . number_format($this->current_invoice['balance'], 2)
                                                                    ) . "
                    												</td>
                                                                </tr>
                                                            </table>
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

		if ( $local )
			return $inner_tbl;
		else {

			if ( $this->invoice_hash )
				array_push($jscript, "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')', 10);");

        if ( $this->ajax_vars['tab_to'] )
            $this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_cinvoice{$this->ajax_vars['tab_to']}{$this->popup_id}\'))', 100)";
        else
            $this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_tcontent1{$this->popup_id}\'))', 100)";

			$this->content["jscript"] = $jscript;


			$this->content['popup_controls']["cmdTable"] = $tbl;
		}

		return;
	}

	function doit_search() {

		$sort = $_POST['sort'];
    	$sort_from_date = $_POST['sort_from_date'];
    	$sort_to_date = $_POST['sort_to_date'];
		$show_bills_due_on = $_POST['show_bills_due_on'];
		$save = $_POST['page_pref'];
		$onReturn = $_POST['onReturn'];
		$detail_search = $_POST['detail_search'];
		$type = $_POST['type'];
		$created_by = $_POST['created_by'];
		if ($created_by && !is_array($created_by))
			$created_by = array($created_by);

		$str = "show=$sort|sort_from_date=$sort_from_date|sort_to_date=$sort_to_date|show_bills_due_on=$show_bills_due_on|type=$type".($created_by ? "|created_by=".@implode("&", $created_by) : NULL);
		if ($save) {
			$r = $this->db->query("SELECT `obj_id`
								   FROM `page_prefs`
								   WHERE `id_hash` = '".$this->current_hash."' AND `class` = '".get_class($this)."'");
			if ($obj_id = $this->db->result($r))
				$this->db->query("UPDATE `page_prefs`
								  SET `pref_str` = '$str'
								  WHERE `obj_id` = $obj_id");
			else
				$this->db->query("INSERT INTO `page_prefs`
								  (`id_hash` , `class` , `pref_str`)
								  VALUES ('".$this->current_hash."' , '".get_class($this)."' , '$str')");

		} else {
			unset($this->page_pref['custom']);
			if ($sort != '*') {
				switch ($sort) {
					case 1:
					if ($onReturn != "paybills")
						$sql_p[] = "vendor_payables.balance = 0";
					break;

					case 2:
					$sql_p[] = "vendor_payables.pay_flag = 1 AND vendor_payables.balance > 0";
					break;

					case 3:
					$sql_p[] = "vendor_payables.balance > 0";
					break;
				}
			} elseif ($onReturn == 'paybills')
				$sql_p[] = "vendor_payables.balance > 0 AND vendor_payables.hold = 0 AND vendor_payables.type != 'C'";

			if ($type && $type != '*')
				$sql_p[] = "vendor_payables.type = '$type'";

			if ($created_by && (count($created_by) == 1 && $created_by[0]) || count($created_by) > 1)
				$sql_p[] = "vendor_payables.created_by ".(count($created_by) == 1 ? "= '".$created_by[0]."'" : "IN (".implode(" , ", $created_by).")");

			if ($sort_from_date)
				$sql_p[] = "vendor_payables.due_date >= '".$sort_from_date."'";
			if ($sort_to_date && strtotime($sort_to_date) > strtotime($sort_from_date))
				$sql_p[] = "vendor_payables.due_date <= '".$sort_to_date."'";
			if ($show_bills_due_on && $sort != '*')
				$sql_p[] = "vendor_payables.due_date <= '".$show_bills_due_on."' AND vendor_payables.balance > 0";

			if ($detail_search) {

				unset($sql_p);

				$invoice_no = $_POST['invoice_no'];
				$po_no = $_POST['po_no'];
				$vendor_filter = $_POST['vendor_filter'];
				if (!is_array($vendor_filter) && $vendor_filter)
					$vendor_filter = array($vendor_filter);

				array_walk($vendor_filter, 'add_quotes', "'");

				$paid_unpaid = $_POST['paid_unpaid'];
				$due_from_date = $_POST['due_from_date'];
				$due_to_date = $_POST['due_to_date'];
				$check_no = $_POST['check_no'];

				$save = $_POST['save'];
				$search_name = $_POST['search_name'];
				if (!$search_name)
					unset($save);

				if ( $invoice_no )
					$sql_p[] = "vendor_payables.invoice_no LIKE '%$invoice_no%'";
				if ( $po_no )
					$sql_p[] = "purchase_order.po_no LIKE '%$po_no%'";
				if ( $paid_unpaid )
					$sql_p[] = "vendor_payables.balance " . ( $paid_unpaid == 1 ? "<=" : ">" ) . " 0";
				if ( is_array($vendor_filter) )
					$sql_p[] = "vendor_payables.vendor_hash IN (" . implode(" , ", $vendor_filter) . ")";
				if ( $due_from_date )
					$sql_p[] = "vendor_payables.due_date >= '$due_from_date'";
				if ( $due_to_date && strtotime($due_to_date) > strtotime($due_from_date) )
					$sql_p[] = "vendor_payables.due_date <= '$due_to_date'";
                if ( $check_no )
                    $sql_p[] = "vendor_payment.check_no = '$check_no'";

				array_walk($vendor_filter, 'strip_quotes');
				$str .= "|invoice_no=$invoice_no|po_no=$po_no|paid_unpaid=$paid_unpaid|due_from_date=$due_from_date|due_to_date=$due_to_date|vendor_filter=" . @implode("&", $vendor_filter) . "|check_no=$check_no";
			}

			if ($sql_p)
				$sql = implode(" AND ", $sql_p);

			$r = $this->db->query("SELECT
    			                       COUNT(*) AS Total
								   FROM vendor_payables
								   LEFT JOIN purchase_order ON purchase_order.po_hash = vendor_payables.po_hash AND purchase_order.deleted = 0
								   LEFT JOIN vendor_payment ON vendor_payment.invoice_hash = vendor_payables.invoice_hash
								   WHERE " .
                       			   ( $sql ?
                                       "$sql AND " : NULL
                       			   ) . "vendor_payables.deleted = 0");
			$total = $this->db->result($r);

			$search_hash = md5(global_classes::get_rand_id(32, "global_classes"));
			while (global_classes::key_exists('search', 'search_hash', $search_hash))
				$search_hash = md5(global_classes::get_rand_id(32, "global_classes"));



			$this->db->query("INSERT INTO `search`
							  (`timestamp` , `search_hash` , `saved` , `search_name` , `search_class` , `user_hash` , `detail_search` , `query` , `total` , `search_str`)
							  VALUES (".time()." , '$search_hash' , '$save' , '$search_name' , 'payables' , '".$this->current_hash."' , '$detail_search' , '".base64_encode($sql)."' , '$total' , '$str')");

			$this->active_search = $search_hash;
		}

		$c_action = $_POST['c_action'];
		$this->content['action'] = ($c_action ? $c_action : 'continue');
		$this->content['jscript_action'] = "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', '".($onReturn ? $onReturn : "showall")."', 'active_search={$this->active_search}');";
		return;
	}

	function account_balance() {
		$account_hash = $_POST['payment_account_hash'];
		$pay_amount = $_POST['pay_amount'];

		$acct = new accounting($this->current_hash);
		$acct->fetch_account_record($account_hash);

		$balance = $acct->current_account['balance'];
		if (is_array($pay_amount)) {
			while (list($invoice_hash, $amt) = each($pay_amount))
				$balance -= $amt;
		}
		if ($balance < 0)
			$amount = "<span style=\"color:#ff0000;\">($".number_format($balance * -1, 2).")</span>";
		else
			$amount = "$".number_format($balance, 2);

		$this->content['action'] = "continue";
		$this->content['html']['payment_account_balance'] = "<strong>Ending Balance: </strong>".$amount;
	}

	function fetch_vendor_credits($vendor_hash, $sum=NULL) {
		if ($sum) {
			if ($sum == 1)
				$result = $this->db->query("SELECT t1.obj_id , t1.invoice_hash , t1.type ,
		                                    IF(ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.amount, ROUND(currency_exchange(t1.amount, t1.exchange_rate, 0), 2)) AS amount ,
		                                    IF(ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.balance, ROUND(currency_exchange(t1.balance, t1.exchange_rate, 0), 2)) AS balance ,
		                                    currency_symbol(t1.currency) AS symbol
											FROM vendor_payables t1
											WHERE t1.vendor_hash = '$vendor_hash' AND t1.deleted = 0
											AND ((t1.type = 'C' AND t1.balance > 0) OR (t1.type = 'I' AND t1.balance < 0))
											ORDER BY t1.invoice_date DESC");
            elseif ($sum == 2)
                $result = $this->db->query("SELECT t1.obj_id , t1.invoice_hash , t1.type ,
                                            IF(ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.amount, ROUND(currency_exchange(t1.amount, t1.exchange_rate, 0), 2)) AS amount ,
                                            IF(ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.balance, ROUND(currency_exchange(t1.balance, t1.exchange_rate, 0), 2)) AS balance ,
                                            FROM vendor_payables t1
                                            WHERE t1.vendor_hash = '$vendor_hash' AND t1.deleted = 0 AND t1.type = 'C'
                                                AND t1.balance < t1.amount
                                            ORDER BY t1.obj_id ASC");
			while ($row = $this->db->fetch_assoc($result))
				$credits[] = $row;

		} else {
			$result = $this->db->query("SELECT
			                            IF(ISNULL(t1.currency) OR ISNULL(@convert_currency), SUM(t1.balance), SUM(ROUND(currency_exchange(t1.balance, t1.exchange_rate, 0), 2))) AS total_credits
										FROM vendor_payables t1
										WHERE t1.vendor_hash = '$vendor_hash' AND t1.type = 'C' AND t1.balance > 0 AND t1.deleted = 0 AND t1.hold = 0");
			$row = $this->db->fetch_assoc($result);
			$credits = $row['total_credits'];
		}

		return $credits;
	}

	function fetch_deposits($po_hash, $currency_convert=false) {
		$total_deposits = $dep1 = $dep2 = $dep3 = 0;
		$result = $this->db->query("SELECT (t1.amount - t1.balance) AS payment , t1.currency , t1.exchange_rate
                                    FROM vendor_payables t1
                                    WHERE t1.po_hash = '$po_hash' AND t1.type = 'D' AND t1.deleted = 0");
		while ($row = $this->db->fetch_assoc($result)) {
			$currency = $row['currency'];
			$currency_rate = $row['exchange_rate'];
			$payment = $row['payment'];
			if ($currency_convert && $currency && $currency_rate)
                $payment = currency_exchange($payment, $currency_rate);

            $dep1 = bcadd($dep1, $payment, 2);
		}

        $result = $this->db->query("SELECT t2.deposit_used , t2.currency , t2.exchange_rate
                                    FROM vendor_payables t1
                                    LEFT JOIN vendor_payment t2 ON t2.invoice_hash = t1.invoice_hash
                                    LEFT JOIN check_register t3 ON t3.check_no = t2.check_no AND t3.account_hash = t2.account_hash
                                    WHERE t1.po_hash = '$po_hash' AND t1.type = 'I' AND t1.deleted = 0 AND t3.void = 0");
        while ($row = $this->db->fetch_assoc($result)) {
            $currency = $row['currency'];
            $currency_rate = $row['exchange_rate'];
            $payment = $row['deposit_used'];
            if ($currency_convert && $currency && $currency_rate)
                $payment = currency_exchange($payment, $currency_rate);

            $dep2 = bcadd($dep2, $payment, 2);
        }

        $result = $this->db->query("SELECT t2.deposit_used , t2.currency , t2.exchange_rate
                                    FROM vendor_payables t1
                                    LEFT JOIN vendor_pay_queue t2 ON t2.invoice_hash = t1.invoice_hash
                                    WHERE t1.po_hash = '$po_hash' AND t1.type = 'I' AND t1.deleted = 0");
        while ($row = $this->db->fetch_assoc($result)) {
            $currency = $row['currency'];
            $currency_rate = $row['exchange_rate'];
            $payment = $row['deposit_used'];
            if ($currency_convert && $currency && $currency_rate)
                $payment = currency_exchange($payment, $currency_rate);

            $dep3 = bcadd($dep3, $payment, 2);
        }

        $total_deposits = bcsub($dep1, $dep2, 2);
        $total_deposits = bcsub($total_deposits, $dep3, 2);

        return $total_deposits;
	}

	function paybills() {
		$this->unlock("_");

		$p = $this->ajax_vars['p'];
        $order = $this->ajax_vars['order'];
        $order_dir = $this->ajax_vars['order_dir'];

		$this->load_class_navigation();
		$this->active_search = $this->ajax_vars['active_search'];

		if (!$this->active_search) {
			$this->search_vars['show'] = $this->page_pref['show'] = 2;
			$this->page_pref['str'] = "vendor_payables.balance > 0 AND vendor_payables.pay_flag = 1 AND vendor_payables.type IN('D', 'R', 'I')";
		}
		$this->fetch_payables(0, $this->total, "vendor_payables.due_date");

		if ($this->invoice_info_refund)
			$customers = new customers($this->current_hash);

		//Get any available vendor credits
		$deposts_remaining_for_po = $vendor_credits = array();
		$border = "border-bottom:1px solid #cccccc;";

		for ( $i = 0; $i < $this->total; $i++ ) {

			if ( $i >= $this->total - 1 )
    			unset($border);

			if ($this->invoice_info[$i]['type'] != 'R') {
				$amt_to_pay = $this->invoice_info[$i]['balance'];
				$total_invoice_amount = bcadd($total_invoice_amount, $amt_to_pay, 2);

				//Fetch any unused vendor credits that may be available for this vendor.
				if (!array_key_exists($this->invoice_info[$i]['vendor_hash'], $vendor_credits)) {
					$vendor_credits[$this->invoice_info[$i]['vendor_hash']] = array("amount"	=>	0,
																   					"valid"	    =>	1);
					$open_balance = $this->fetch_vendor_credits($this->invoice_info[$i]['vendor_hash']);
					if (!$open_balance)
						unset($vendor_credits[$this->invoice_info[$i]['vendor_hash']]['valid']);
					else
						$vendor_credits[$this->invoice_info[$i]['vendor_hash']]['amount'] = $open_balance;

				}

				unset($discounts_taken);
				//See if there are any early payment discounts to take advantage of
				if ($this->invoice_info[$i]['due_date'] != $this->invoice_info[$i]['orig_due_date'] && strtotime(date("Y-m-d")) <= strtotime($this->invoice_info[$i]['due_date'])) {
					$discounts_taken = _round(bcmul($this->invoice_info[$i]['balance'], $this->invoice_info[$i]['payment_discount'], 4), 2);
					$discounts_taken_tbl = "<div>($".number_format(_round(bcmul($this->invoice_info[$i]['balance'], $this->invoice_info[$i]['payment_discount'], 4), 2), 2).")</div>";
					$amt_to_pay = bcsub($amt_to_pay, $discounts_taken, 2);
				}

				unset($deposits);
				if ($this->invoice_info[$i]['type'] != 'D' && $this->invoice_info[$i]['po_hash'] && $deposits = $this->fetch_deposits($this->invoice_info[$i]['po_hash'], 1)) {
					// Take into account deposits spreading over multiple invoices for #1399
					if (!is_numeric($deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']]) && $deposits)
						$deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']] = $deposits;

					if (is_numeric($deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']]) && $deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']] > 0) {

						if (bccomp($deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']], $amt_to_pay, 2) == 1) {
							$deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']] = bcsub($deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']], $amt_to_pay);
							$deposits = $amt_to_pay;
						} else {
							$deposits = $deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']];
							$deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']] = 0;
						}
					} elseif (is_numeric($deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']]) && $deposts_remaining_for_po[$this->invoice_info[$i]['po_hash']] <= 0)
						$deposits = 0;

					if (bccomp($deposits, $amt_to_pay, 2) == 1)
					    $deposits = $amt_to_pay;

					$deposits_tbl = "<div>(" . $this->invoice_info[$i]['symbol'] . number_format($deposits, 2) . ")</div>";
					$amt_to_pay = bcsub($amt_to_pay, $deposits, 2);
				}

				unset($credit_used);
				if (bccomp($vendor_credits[$this->invoice_info[$i]['vendor_hash']]['amount'], 0, 2) == 1) {
					if (bccomp($vendor_credits[$this->invoice_info[$i]['vendor_hash']]['amount'], $amt_to_pay, 2) != -1) {
						$vendor_credits[$this->invoice_info[$i]['vendor_hash']]['amount'] = bcsub($vendor_credits[$this->invoice_info[$i]['vendor_hash']]['amount'], $amt_to_pay, 2);
						$credit_used = $amt_to_pay;
						$amt_to_pay = bcsub($amt_to_pay, $credit_used, 2);
					} else {
						$credit_used = $vendor_credits[$this->invoice_info[$i]['vendor_hash']]['amount'];
						$amt_to_pay = bcsub($amt_to_pay, $vendor_credits[$this->invoice_info[$i]['vendor_hash']]['amount'], 2);
						$vendor_credits[$this->invoice_info[$i]['vendor_hash']]['amount'] = 0;
					}
				}

				$amount_to_pay = bcadd($amount_to_pay, $amt_to_pay, 2);

			} elseif ($this->invoice_info[$i]['type'] == 'R') {
				$amt_to_pay = $this->invoice_info[$i]['balance'];
				$r = $this->db->query("SELECT `customer_name`
									   FROM `customers`
									   WHERE `customer_hash` = '".$this->invoice_info[$i]['vendor_hash']."'");
				$this->invoice_info[$i]['vendor_name'] = $this->db->result($r, 0, 'customer_name');
				$amount_to_pay = bcadd($amount_to_pay, 2, $amt_to_pay);
			}

			$r = $this->db->query("SELECT `manual_credit`
									   FROM `vendors`
									   WHERE `vendor_hash` = '".$this->invoice_info[$i]['vendor_hash']."'");
			$manCredit = $this->db->result($r, 0, 'manual_credit');
			if($manCredit){
				if( !$this->invoice_info[$i]['exclude_credit'] ){
					$r = $this->db->query("SELECT `manual_credit_amount`
										   FROM `vendors`
										   WHERE `vendor_hash` = '".$this->invoice_info[$i]['vendor_hash']."'");
					$manCreditAmount = $this->db->result($r, 0, 'manual_credit_amount');
					$discounts_taken += round(($manCreditAmount / 100) * $this->invoice_info[$i]['amount'],2);
					$amt_to_pay -= $discounts_taken;
					$amount_to_pay = $amt_to_pay;
				}
			}

            $total_amount_to_pay += $amt_to_pay;
			$total_discounts = bcadd($total_discounts, $discounts_taken, 2);
			$total_deposits = bcadd($total_deposits, $deposits, 2);
			$total_credits = bcadd($total_credits, $credit_used, 2);

			if ($this->invoice_info[$i]['currency'] && $this->invoice_info[$i]['exchange_rate'])
				$total_due = bcadd($total_due, currency_exchange($amt_to_pay, $this->invoice_info[$i]['exchange_rate'], 1), 2);
			else
    			$total_due = bcadd($total_due, $amt_to_pay, 2);

			$payment_tbl .= "
			<tr id=\"paytbl1_{$this->invoice_info[$i]['invoice_hash']}\">
				<td style=\"cursor:auto;vertical-align:bottom;$border\" rowspan=\"2\">" .
                    $this->form->checkbox(
                        "name=invoice_hash[]",
						"value={$this->invoice_info[$i]['invoice_hash']}",
						"checked",
						"onClick=\$('pay_btn').disabled=1;submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=amount_tracker');"
                    ) . "
				</td>
				<td style=\"vertical-align:bottom;text-align:left;\" colspan=\"7\">" .
					stripslashes($this->invoice_info[$i]['vendor_name']) .
                    $this->form->hidden( array(
                        "vendor_hash[{$this->invoice_info[$i]['invoice_hash']}]" => $this->invoice_info[$i]['vendor_hash']
                    ) ) . "
				</td>
				<td style=\"vertical-align:bottom;text-align:right;$border\" rowspan=\"2\">
				    <div>" .
                        $this->form->select(
	                        "check_action[{$this->invoice_info[$i]['invoice_hash']}]",
                            array("To be printed", "Hand written"),
                            '',
                            array(1, 2),
                            "onChange=if(this.options[this.selectedIndex].value==1){toggle_display('check_input_{$this->invoice_info[$i]['invoice_hash']}', 'none');\$('check_input__{$this->invoice_info[$i]['invoice_hash']}').value='';}else{toggle_display('check_input_{$this->invoice_info[$i]['invoice_hash']}', 'block');\$('check_input__{$this->invoice_info[$i]['invoice_hash']}').activate();fillCheckNo('{$this->invoice_info[$i]['invoice_hash']}');}",
                            "style=width:150px;",
                            "blank=1"
                        ) . "
				    </div>
				    <div style=\"display:none;margin-top:5px;margin-right:5px;\" id=\"check_input_{$this->invoice_info[$i]['invoice_hash']}\">
                        Check No: " .
                        $this->form->text_box(
	                        "name=check_input[{$this->invoice_info[$i]['invoice_hash']}]",
                            "id=check_input__{$this->invoice_info[$i]['invoice_hash']}",
                            "cid=checkinputclass",
                            "value=",
                            "style=width:50px;text-align:right;"
                        ) . "
				    </div>
				</td>
			</tr>
			<tr id=\"paytbl2_{$this->invoice_info[$i]['invoice_hash']}\">
				<td style=\"padding-top:5px;font-style:italic;$border\">" .
                    ( $this->invoice_info[$i]['type'] == 'I' ?
						"Invoice " . stripslashes($this->invoice_info[$i]['invoice_no']) :
                        ( $this->invoice_info[$i]['type'] == 'D' ?
    						"Vendor Deposit" : "Customer Refund"
                        )
                    ) . "
				</td>
				<td style=\"vertical-align:bottom;$border\" nowrap>" .
                    date(DATE_FORMAT, strtotime($this->invoice_info[$i]['due_date']) ) .
                    ( $this->invoice_info[$i]['due_date'] != $this->invoice_info[$i]['orig_due_date'] && strtotime($this->invoice_info[$i]['due_date']) >= strtotime( date("Y-m-d") ) ?
    					"<span style=\"padding-left:3px;\" title=\"Early payment discount:\nMust be paid by " . date(DATE_FORMAT, strtotime($this->invoice_info[$i]['due_date']) ) . " to receive " . (float)bcmul($this->invoice_info[$i]['payment_discount'], 100, 2) . "% discount\">*</span>" : NULL
                    ) . "
				</td>
				<td style=\"vertical-align:bottom;$border\" class=\"num_field\" nowrap>" .
    				$this->form->hidden( array(
        				"amount_due[{$this->invoice_info[$i]['invoice_hash']}]" => $this->invoice_info[$i]['balance']
    				) ) .
					$this->invoice_info[$i]['symbol'] . number_format($this->invoice_info[$i]['balance'], 2) . "
				</td>
				<td style=\"vertical-align:bottom;$border\" class=\"num_field\" " .
					( $this->invoice_info[$i]['type'] == 'I' && ! $discounts_taken ?
    					"onMouseOver=\"if(\$('td_discounts_{$this->invoice_info[$i]['invoice_hash']}').readAttribute('manual')!=1){\$('td_discounts_{$this->invoice_info[$i]['invoice_hash']}').removeClassName('hide_it');}\" onMouseOut=\"if(\$('td_discounts_{$this->invoice_info[$i]['invoice_hash']}').readAttribute('manual')!=1){\$('td_discounts_{$this->invoice_info[$i]['invoice_hash']}').addClassName('hide_it');}\" onClick=\"if(\$('td_discounts_{$this->invoice_info[$i]['invoice_hash']}').readAttribute('manual')!=1){\$('td_discounts_{$this->invoice_info[$i]['invoice_hash']}').writeAttribute('manual', '1');}\" title=\"Click to manually apply discounts to be taken.\"" : NULL
					) .
					">
				    <div " .
    					( bccomp($discounts_taken, 0, 2) == 0 ?
        					"class=\"hide_it\"" : NULL
    					) . " id=\"td_discounts_{$this->invoice_info[$i]['invoice_hash']}\">" .
						"(" .
    					$this->form->text_box(
        					"name=discounts_taken[{$this->invoice_info[$i]['invoice_hash']}]",
							"value=" . number_format($discounts_taken, 2),
							"size=6",
							"id=discounts_taken_{$this->invoice_info[$i]['invoice_hash']}",
							"style=text-align:right;",
							"onBlur=\$('pay_btn').disabled=1;this.value=formatCurrency(this.value);submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=amount_tracker', 'from_invoice={$this->invoice_info[$i]['invoice_hash']}');",
							"onFocus=select();"
    					) .
    					")
	                </div>
				</td>
				<td style=\"vertical-align:bottom;$border\" class=\"num_field\">" .
					$this->form->hidden( array(
    					"vendor_deposits[{$this->invoice_info[$i]['invoice_hash']}]" => $deposits
					) ) .
					( bccomp($deposits, 0, 2) ?
    					$deposits_tbl : NULL
    				) . "
				</td>
				<td style=\"vertical-align:bottom;$border\" class=\"num_field\">" .
    				( bccomp($vendor_credits[ $this->invoice_info[$i]['vendor_hash'] ]['valid'], 0, 2) == 1 ?
                        $this->form->text_box(
                            "name=vendor_credits[{$this->invoice_info[$i]['invoice_hash']}]",
                            "value=" . number_format($credit_used, 2),
                            "size=10",
                            "id=vendor_credits_{$this->invoice_info[$i]['invoice_hash']}",
                            "style=text-align:right;",
                            "onBlur=\$('pay_btn').disabled=1;if(this.value < 0){this.value=(this.value * -1)}this.value=formatCurrency(this.value);submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=amount_tracker', 'from_invoice={$this->invoice_info[$i]['invoice_hash']}');",
                            "onFocus=select();"
                        ) : "&nbsp;"
                    ) . "
				</td>
				<td style=\"vertical-align:bottom;$border\" class=\"num_field\" >" .
					$this->form->text_box(
    					"name=pay_amount[{$this->invoice_info[$i]['invoice_hash']}]",
						"value=" . number_format($amt_to_pay, 2),
						"size=10",
						"id=pay_amount_{$this->invoice_info[$i]['invoice_hash']}",
						"style=text-align:right;",
						"onChange=\$('pay_btn').disabled=1;if(this.value < 0){this.value=(this.value * -1)}this.value=formatCurrency(this.value);submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=amount_tracker', 'from[{$this->invoice_info[$i]['invoice_hash']}]=pay_amount');",
						"onFocus=select();"
					) . "
				</td>
			</tr>";

		}

		if (defined('DEFAULT_CASH_ACCT')) {
			$accounts = new accounting($this->current_hash);
			if ($accounts->fetch_account_record(DEFAULT_CASH_ACCT))
			    $ending_balance = bcsub($accounts->current_account['balance'], $total_due, 2);
		}

		$due_on_menu = "
		<div id=\"due_on_menu\" class=\"search_suggest\" >
			<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;margin-top:0;\">
				<div style=\"float:right;padding-right:1px;\">
					<a href=\"javascript:void(0);\" onClick=\"toggle_display('due_on_menu', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
				</div>
				Due on or before:
			</div>
			<div class=\"function_menu_item\" style=\"padding-left:10px;\" id=\"due_on_date_holder\">";
			$jscript[] = "setTimeout('DateInput(\'show_bills_due_on\', false, \'YYYY-MM-DD\', \'".($this->search_vars['show'] == 5 && $this->search_vars['show_bills_due_on'] ? $this->search_vars['show_bills_due_on'] : '')."\', 1, \'due_on_date_holder\')', 300);";
			$due_on_menu .= "
			</div>
			<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">
				".$this->form->button("value=Go", "onClick=submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=doit_search', 'onReturn=paybills');")."
			</div>
		</div>";

		# Get the checking accounts
		$checking_account_hash = $checking_account_name = $next_check = array();

		$result = $this->db->query("SELECT
										t1.account_hash,
										t1.account_no,
										t1.account_name,
	                            		t1.balance AS account_balance,
	                            		t1.next_check_no
									FROM accounts t1
									WHERE t1.account_type = 'CA' AND t1.checking = 1
									ORDER BY t1.account_no, account_name ASC");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$account_balance =
			( bccomp($row['account_balance'], 0, 2) == -1 ?
                "(\$".number_format(bcmul($row['account_balance'], -1), 2).")" : "\$".number_format($row['account_balance'], 2));
			array_push($checking_account_hash, $row['account_hash']);
			array_push($checking_account_name,
				( $row['account_no'] ?
					"{$row['account_no']} " : NULL
				) .
				( strlen($row['account_name']) > 20 ?
					substr($row['account_name'], 0, 20) .
					( strlen($row['account_name']) > 23 ?
						" ... " : NULL
					) : $row['account_name']
				) . " {$account_balance}"
			);
			$next_check[ $row['account_hash'] ] = (int)++$row['next_check_no'];
		}

		if ( defined('DEFAULT_CASH_ACCT') && $accounts->account_hash ) {

			if ( ! $checking_account_hash || ! in_array(DEFAULT_CASH_ACCT, $checking_account_hash) ) {

				array_push($checking_account_hash, $accounts->account_hash);
				array_push($checking_account_name,
					( $accounts->current_account['account_no'] ?
						"{$accounts->current_account['account_no']} " : NULL
					) .
					( strlen($accounts->current_account['account_name']) > 20 ?
						substr($accounts->current_account['account_name'], 0, 20) .
						( strlen($accounts->current_account['account_name']) > 23 ?
							" ... " : NULL
						) : $accounts->current_account['account_name']
					) . "  " .
					( bccomp($accounts->current_account['balance'], 0, 2) == -1 ?
						"(\$" . number_format( bcmul($accounts->current_account['balance'], -1, 2), 2) . ")" : "\$" . number_format($accounts->current_account['balance'], 2)
					)
				);

				$next_check[$accounts->account_hash] = (int)++$accounts->current_account['next_check_no'];
			}
		}

		$jscript[] = "
		purgeit = function() {
			var invoice = arguments[0];
			for (var i = 1; i <= 2; i++) {
				if (\$('paytbl' + i + '_' + invoice))
					\$('paytbl' + i + '_' + invoice).remove();
			}
			submit_form(\$('p_test').form, 'payables', 'exec_post', 'refresh_form', 'action=amount_tracker');

			return;
		}
		numOrdA = function() {
            var a = arguments[0];
            var b = arguments[1];
            return (a-b);
		}
		fillCheckNo = function() {
			var invoice_hash = arguments[0];

			var i = 0, c, _elem, _startCheck, _elemhash;
			var _checkList = [];
			var obj = $$('input[cid=checkinputclass]');
			while ( c = obj[i++] ) {

                _elem = \$(c).identify().match(/^check_input__(.*)$/);
                _elemhash = _elem[1];

                if ( _elemhash != invoice_hash && \$F('check_action[' + _elemhash + ']') == 2 && \$F('check_input__' + _elemhash) ) {

                    _startCheck = \$F('check_input__' + _elemhash);
                    _checkList.push( parseInt(_startCheck) );
                }
			}

			if ( _startCheck ) {

				_startCheck++;
				if ( _checkList.indexOf(_startCheck) >= 0 ) {

    				_checkList.sort( numOrdA );
                    _startCheck = _checkList.last();
                    _startCheck++;
				}

			} else {
                _startCheck = \$F('nextcheck_' + \$F('payment_account_hash'));
			}


            if ( ! \$F('check_input__' + invoice_hash) ) {
    			\$('check_input__' + invoice_hash).value = _startCheck;
    		}
		}";

		foreach ( $next_check as $account => $account_check ) {
			$hidden .= $this->form->hidden( array(
				"nextcheck_{$account}"	=>	$account_check
			) );
		}

		$tbl .=
		$this->form->form_tag() .
		$this->form->hidden( array(
			'p_test'	=>	1
		) ) . "
		$hidden
		<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
			<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
				<p id=\"feedback_message\"></p>
		</div>
		<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:1000px;margin-top:0;\">
			<tr>
				<td style=\"padding:0;\">
					 <div style=\"background-color:#ffffff;height:100%;\">
					 	<div style=\"margin:15px 35px;\">
							<div style=\"float:right;margin-top:15px;padding-right:100px;\">
								".$this->form->radio("name=sort", "value=*", ($this->search_vars['show'] == '*' ? "checked" : NULL), "onClick=if(this.checked){submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=doit_search', 'onReturn=paybills');}")."&nbsp;Show all
								&nbsp;&nbsp;
								".$this->form->radio("name=sort", "value=2", ($this->search_vars['show'] == 2 || !$this->active_search ? "checked" : NULL), "onClick=if(this.checked){submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=doit_search', 'onReturn=paybills');}")."&nbsp;Show flagged
								&nbsp;&nbsp;
								".$this->form->radio("name=sort", "value=5", ($this->search_vars['show'] == 5 ? "checked" : NULL), "onMouseOver=position_element(\$('due_on_menu'), findPos(this, 'top')+25, findPos(this, 'left')-50);", "onClick=toggle_display('due_on_menu', 'block');")."&nbsp;Due on or before
								".$due_on_menu."
							</div>
							<h3 style=\"margin-bottom:5px;color:#00477f;\">Pay Invoices</h3>
							<small style=\"margin-left:20px;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir')\"><-- Back</a></small>
							<div style=\"margin:20px;padding:0px;\">
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:100%;\" id=\"paycontent\">
									<tr>
										<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"9\">
											<div id=\"payment_account_balance\" style=\"float:right;padding-top:5px;font-weight:bold;\">".(defined('DEFAULT_CASH_ACCT') ? "Ending Balance: ".($ending_balance < 0 ? "
												<span style=\"color:red;\">($".number_format(($ending_balance * -1), 2).")</span>" : "$".number_format($ending_balance, 2)) : NULL)."
											</div>
											<div style=\"padding-left:5px;font-weight:normal;\">" .
											( $checking_account_hash ?
												"<span id=\"err1\">Payment Account:</span>&nbsp;&nbsp;".
												$this->form->select(
													"payment_account_hash",
													$checking_account_name,
													( defined('DEFAULT_CASH_ACCT') ? DEFAULT_CASH_ACCT : '' ),
													$checking_account_hash,
													"blank=1",
													"onChange=submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=account_balance')"
												) : "<img src=\"images/alert.gif\" />&nbsp;&nbsp;You must first define a checking accounts!"
											) . "
											</div>
											$item_menu
										</td>
									</tr>
									<tr style=\"background-color:#cccccc;\">
										<td class=\"thead\" style=\"width:10px;border-bottom:1px solid #cccccc;\" >".$this->form->checkbox("name=check_all", "value=1", "onClick=checkall(document.getElementsByName('invoice_hash[]'), this.checked);submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=amount_tracker');", "checked")."</td>
										<td class=\"thead\" style=\"font-weight:normal;width:190px;border-bottom:1px solid #cccccc;\">Vendor".($this->invoice_info_refund ? " / Payee" : NULL)."</td>
										<td class=\"thead\" style=\"font-weight:normal;width:100px;border-bottom:1px solid #cccccc;\">Due Date</td>
										<td class=\"thead\" style=\"text-align:right;width:100px;font-weight:normal;border-bottom:1px solid #cccccc;\">Amt Due</td>
										<td class=\"thead\" style=\"text-align:right;width:85px;font-weight:normal;border-bottom:1px solid #cccccc;\">Discounts</td>
										<td class=\"thead\" style=\"text-align:right;width:85px;font-weight:normal;border-bottom:1px solid #cccccc;\">Deposits</td>
										<td class=\"thead\" style=\"text-align:right;width:85px;font-weight:normal;border-bottom:1px solid #cccccc;\">Credits</td>
										<td class=\"thead\" style=\"text-align:right;width:85px;font-weight:normal;border-bottom:1px solid #cccccc;\">Amt To Pay</td>
										<td class=\"thead\" style=\"width:160px;font-weight:normal;border-bottom:1px solid #cccccc;\">Check No.</td>
									</tr>
									$payment_tbl " .
									( $this->total ?
										"<tr style=\"background-color:#efefef;\">
											<td style=\"border-top:1px solid #cccccc;\" colspan=\"2\">&nbsp;</td>
											<td style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;\">Totals:</td>
											<td class=\"num_field\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;\" id=\"total_amount_due_holder\">\$" . number_format($total_invoice_amount, 2) . "</td>
											<td class=\"num_field\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;\" id=\"total_discounts_holder\">" .
        									( bccomp($total_discounts, 0, 2) == 1 ?
												"(\$" . number_format($total_discounts, 2) . ")" : "&nbsp;"
        									) . "
											</td>
											<td class=\"num_field\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;\" id=\"total_deposits_holder\">" .
        									( bccomp($total_deposits, 0, 2) == 1 ?
												"(\$" . number_format($total_deposits, 2) . ")" : "&nbsp;"
        									) . "
											</td>
											<td class=\"num_field\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;\" id=\"total_credits_holder\">" .
        									( bccomp($total_credits, 0, 2) == 1 ?
												"(\$" . number_format($total_credits, 2) . ")" : "&nbsp;"
        									) . "
											</td>
											<td class=\"num_field\" style=\"border-top:1px solid #cccccc;padding-top:8px;font-weight:bold;\" id=\"total_to_pay_holder\">\$" . number_format($total_amount_to_pay, 2) . "</td>
											<td style=\"border-top:1px solid #cccccc;\">&nbsp;</td>
										</tr>" : NULL
									);

								if (!$this->total)
									$tbl .= "
									<tr >
										<td colspan=\"9\">We found no invoices to be paid. To widen your search, use the filter options above.</td>
									</tr>";

								$tbl .= "
								</table>
							</div>" .
							( $this->total ?
    							"<div style=\"margin-left:15px;\">
    								Posting Date:&nbsp;
    								<div style=\"margin-top:3px;\" id=\"posting_date_holder\"></div>
    								<br />" .
    								$this->form->button(
        								"value=Submit To Pay Queue",
                    					"id=pay_btn",
                    					"onClick=if(confirm('Are you sure you want to submit these payables to the pay queue? By doing this, the above payables will be marked as paid and the appropriate debit/credit actions will be made to the G/L.')){submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'btn=payqueue');this.disabled=1}"
    								) . "
    							</div>" : NULL
    						)."
						</div >";
						if ($this->total)
							$jscript[] = "DateInput('posting_date', false, 'YYYY-MM-DD', '".date("Y-m-d")."', 1, 'posting_date_holder');";

			$tbl .= "
					</div>
				</td>
			</tr>
		</table>".
		$this->form->close_form();

		$this->content['jscript'] = $jscript;
		$this->content['html']['place_holder'] = $tbl;
	}

	function doit_reprint_multi_checks() {
		$reprint = $_POST['reprint'];

		if ($reprint) {
			$reprint = explode(", ", $reprint);
			for ($i = 0; $i < count($reprint); $i++) {
				if (trim($reprint[$i])) {
					$result = $this->db->query("SELECT `payment_hash` , `cleared`
												FROM `vendor_payment`
												WHERE `check_no` = '".trim($reprint[$i])."'");
					if ($payment_hash = $this->db->result($result, 0, 'payment_hash')) {
						if ($this->db->result($result, 0, 'cleared') == 0)
							$check[] = $payment_hash;
						else
							$clear_err[] = trim($reprint[$i]);
					} else
						$error[] = trim($reprint[$i]);
				}
			}
			if ($error || $clear_err) {
				$this->content['error'] = 1;
				if ($error)
					$this->content['form_return']['feedback'] = count($error)." of your checks was not found. The check".(count($error) > 1 ? "s in error are" : " in error is")." ".implode(", ", $error);
				if ($clear_err)
					$this->content['form_return']['feedback'] .= ($error ? "<br /><br />In addition, " : NULL).count($clear_err)." of the checks you are at`ting to reprint has already cleared and therefore cannot be reprinted. Please remove check".(count($clear_err) > 1 ? "s" : NULL)." ".implode(", ", $clear_err)." from your list and try again.";
				return;
			}

			$this->ajax_vars['reprint_from_func'] = 1;
			$this->ajax_vars['reprint'] = implode("|", $check);
			$this->ajax_vars['preview'] = 2;
			$this->content['html']['check_content_holder'.$this->popup_id] = $this->empty_queue();

			$this->content['action'] = 'continue';
			return;
		} else {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "You didn't enter any check numbers! Please enter at least 1 check number to reprint.";
			return;
		}
	}

	function check_taken($check_no, $account_hash, $vendor_check_array=NULL, $current_vendor=NULL) {
	    $r = $this->db->query("SELECT COUNT(*) AS Total
	                           FROM `check_register`
	                           WHERE `account_hash` = '$account_hash' AND `check_no` = '$check_no'");
	    if ($this->db->result($r, 0, 'Total') > 0)
	        return true;

	    if (is_array($vendor_check_array) && $current_vendor) {
	    	while (list($vendor_hash, $check_array) = each($vendor_check_array)) {
	    	    if ($vendor_hash != $current_vendor && in_array($check_no, $check_array))
	    	        return true;
	    	}
	    }

	    return false;
	}

	function empty_queue($preview=NULL) {

		if ( $this->ajax_vars['popup_id'] )
			$this->popup_id = $this->ajax_vars['popup_id'];

        if ( $from_class = $this->ajax_vars['from_class'] )
            $proposal_hash = $_POST['proposal_hash'];

		$this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Print Checks";
		if ( $this->ajax_vars['from'] != 'check_reg' ) {

			$this->content['popup_controls']['onclose'] =
			( $this->ajax_vars['from_class'] && $this->ajax_vars['proposal_hash'] ?
    			"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'otf=1', 'proposal_hash={$this->ajax_vars['proposal_hash']}', 'p={$this->ajax_vars['p']}', 'order={$this->ajax_vars['order']}', 'order_dir={$this->ajax_vars['order_dir']}');" : "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p={$this->ajax_vars['p']}', 'order={$this->ajax_vars['order']}', 'order_dir={$this->ajax_vars['order_dir']}', 'active_search={$this->ajax_vars['active_search']}');"
			);
		}

		if ( ! $preview && ( $this->ajax_vars['preview'] || $_POST['preview'] ) )
			$preview = ( $this->ajax_vars['preview'] ? $this->ajax_vars['preview'] : $_POST['preview'] );

	    $acct = new accounting($this->current_hash);

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
    		"popup_id" => $this->popup_id
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"padding:0;\">
						 <div style=\"background-color:#ffffff;height:100%;\" id=\"check_content_holder{$this->popup_id}\">";

						 if ( $preview == 1 ) {

                            if ( $_POST['checking_acct'] )
                                $pay_account = $_POST['checking_acct'];

                            if ( ! $this->fetch_pay_queue($pay_account, $preview, $this->ajax_vars['from_start']) )
                                return $this->__trigger_error('Unable to fetch pay queue for check run. This is most likely a result of a session timeout. Please close this window and retry your transaction.', E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                            $pay_queue = array();
							for ( $i = 0; $i < count($this->pay_queue); $i++ )
								$pay_queue[ $this->pay_queue[$i]['check_no'] ][] = $this->pay_queue[$i];

							$inner_tbl =
							$hidden .
							$this->form->hidden( array(
    							'pay_queue_hidden'       =>  1,
							    'orig_starting_check'    =>  $_POST['orig_starting_check']
							) ) . "
							<div style=\"margin:15px 35px;\">
								<h3 style=\"margin-bottom:5px;color:#00477f;\">Preview Checks</h3>
								<div style=\"margin-left:20px;padding-top:5px;\">
									<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"submit_form(\$('pay_queue_hidden').form, 'payables', 'exec_post', 'refresh_form', 'action=empty_queue', 'starting_check_no=a', 'checking_acct=$pay_account');\"><img src=\"images/go_back.gif\" title=\"Go back and make changes before printing.\" border=\"0\" /></a>
									&nbsp;
									<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"if(confirm('Do you want to post and print the check run?')){submit_form(\$('pay_queue_hidden').form, 'payables', 'exec_post', 'refresh_form', 'btn=printchecks', 'checking_acct=$pay_account');}\"><img src=\"images/print.gif\" title=\"Print checks.\" border=\"0\" /></a>
								</div>";

							if ( $pay_queue ) {

								while ( list($check_no, $check_data) = each($pay_queue) ) {

									$check_total = 0;
									$check_invoice = '';
									$memo = $check_data[0]['memo'];
									$currency = $check_data[0]['pay_queue_currency'];
									$symbol = $check_data[0]['symbol'];
									unset($exchange_rate, $currency_payment);

									if ( $currency && $currency != FUNCTIONAL_CURRENCY ) {

                                        $exchange_rate = $check_data[0]['pay_queue_exchange_rate'];
                                        if ( ! isset($sys) )
                                            $sys = new system_config();

				                        if ( ! $sys->fetch_currency($currency) )
				                            return $this->__trigger_error("Unable to lookup currency rule for check print. Please make sure a valid currency rule has been created and configured for [{$currency}].", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                                        $converted_check_total = 0;
                                        $inner_tbl .=
                                        $this->form->hidden( array(
                                            "currency[$check_no]"         =>  $currency,
                                            "exchange_rate[$check_no]"    =>  $exchange_rate
                                        ) );

									} else
                                        unset($currency);

									for ( $i = 0; $i < count($check_data); $i++ ) {

										$inner_tbl .= $this->form->hidden( array(
    										'pay_queue[]'                                     =>  $check_data[$i]['queue_hash'],
										    "vendor_queue[{$check_data[$i]['queue_hash']}]"   =>  $check_data[$i]['vendor_hash'],
										    "check_no[{$check_data[$i]['queue_hash']}]"       =>  $check_no
										) );

										if ( $currency )
                                            $check_total = bcadd($check_total, $check_data[$i]['converted_amount'], 2);
                                        else
                                            $check_total = bcadd($check_total, $check_data[$i]['pay_amount'], 2);

										$check_invoice .=
										( $check_data[$i]['check_invoice_no'] ?
    										( $check_data[$i]['type'] == 'D' ?
        										"Deposit for " : NULL
    										) . $check_data[$i]['check_invoice_no'] .
    										( $i < count($check_data) - 1 ?
        										", " : NULL
    										) : NULL
    									);
									}

									$inner_tbl .= "
									<div style=\"margin-top:10px;margin-left:25px;margin-bottom:25px;\">
									    <div style=\"background-color:#edf5f8;border:1px solid #6a9cd9;padding:4px 4px 4px 4px;\">
											<table style=\"width:600px;border:1px solid #6a9cd9;\" cellpadding=\"5\" cellspacing=\"0\" >
												<tr>
													<td style=\"width:425px;\">&nbsp;</td>
													<td style=\"width:175px;\" nowrap>
														<table>
															<tr>
																<td style=\"text-align:right;\" nowrap>No.</td>
																<td >$check_no</td>
															</tr>
															<tr>
																<td style=\"text-align:right;\" >Date:</td>
																<td>" . date("Y-m-d", strtotime($check_data[0]['posted_check_date'])) . "</td>
															</tr>
														</table>
													</td>
												</tr>
												<tr>
													<td colspan=\"2\" style=\"vertical-align:bottom;padding-top:25px;\">
                                                        <table cellpadding=\"0\" cellspacing=\"0\" style=\"width:590px;\" >
                                                            <tr>
                                                                <td style=\"text-align:right;font-size:94%;padding-bottom:2px;width:65px;\">
                                                                    Pay to the<br />Order of:
                                                                </td>
                                                                <td style=\"width:400px;vertical-align:bottom;padding-left:10px;padding-bottom:2px;border-bottom:1px solid #8f8f8f;\">" . stripslashes($check_data[0]['vendor_name'])."</td>
                                                                <td style=\"width:125px;vertical-align:bottom;padding-left:10px;text-align:left;\">
                                                                    <span style=\"padding-right:3px;\">$symbol</span>" .number_format($check_total, 2)."
                                								</td>
                                                            </tr>
                                                        </table>
                                                    </td>
												</tr>
												<tr>
													<td colspan=\"2\" style=\"padding-left:10px;padding-top:5px;vertical-align:bottom;\">" . numbertotext($check_total, 1, 1, 1, currency_phrase($currency)) . "</td>
												</tr>
												<tr>
													<td colspan=\"2\">
														<div style=\"margin-top:5px;margin-bottom:5px;margin-left:20px;\">" . nl2br( stripslashes($check_data[0]['remit_to']) ) . "</div>
														<div style=\"padding-top:10px;padding-left:10px;\">
															Memo: " .
                                                            $this->form->text_box(
                                                                "name=memo[$check_no]",
                            									"value=" .
                                                                ( $memo ?
                                                            		$memo :
                                                            		( strlen($check_invoice) > 30 ?
                                                                		substr($check_invoice, 0, 30) : $check_invoice
                                                                	)
                                                                ),
                                                            	"size=45",
                                                            	"maxlength=128",
                                                            	"style=background-color:#edf5f8;border-bottom:1px solid #8f8f8f;border-top:0;border-left:0;border-right:0"
                                                            ) . "
														</div>
													</td>
												</tr>
												<tr>
												    <td colspan=\"2\" style=\"padding:0px;\">
												        <div style=\"margin-left:15px;margin-top:10px;margin-bottom:10px;\"><img src=\"images/check_routing.gif?" . rand(50, 500) . "\" ></div>
												    </td>
												</tr>
											</table>
										</div>
									</div>";
								}
							}

							$inner_tbl .= "
							</div>";

						 } elseif ( $preview == 2 ) {

							if ( $this->ajax_vars['reprint'] ) {

								$active_search = $this->ajax_vars['active_search'];
								$reprint = explode("|", $this->ajax_vars['reprint']);
                                $this->db->query("TRUNCATE TABLE vendor_payment_print");

								for ( $i = 0; $i < count($reprint); $i++ ) {

									if ( strlen($reprint[$i]) == 32 ) {

										if ( ! $this->fetch_payment_record($reprint[$i]) )
    										return $this->__trigger_error("System error encountered when attempting to lookup payment record for reprint. Please reload window and try again. <!-- Tried fetching payment [ {$reprint[$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

										$check_no = $this->current_payment['check_no'];
									} else {

										$check_no = $reprint[$i];
										$account_hash = $this->ajax_vars['account_hash'];
									}

									if ( ! $this->db->query("INSERT INTO vendor_payment_print
        													 VALUES
        													 (
	        													 NULL,
	        													 UNIX_TIMESTAMP(),
	        													 '$check_no',
	        													 '" . ( $account_hash ? $account_hash : $this->current_payment['account_hash'] ) . "'
        													 )")
                                    ) {

                                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);
                                    }
								}
							}

                            $browser = new browser;
                            if($browser->Name != 'MSIE'){
                                $inner_tbl = "
                                    <div style=\"background-color:#ffffff;width:100%;height:400px;margin:0;text-align:center;\">" .
                                    ( $invalid ?
                                        "<div style=\"text-align:center;font-weight:bold;margin-top:25px;\">Invalid check data. Please try again.</div>"
                                        :
                                        "<div id=\"loading{$this->popup_id}\" style=\"width:100%;padding-top:50px;padding-bottom:45px;\">
                                            <h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch{$this->popup_id}\">Loading Checks...</h3>
                                            <div id=\"link_img_holder{$this->popup_id}\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
                                        </div>
                                        <input id=\"popup\" type=\"hidden\" value=\"$this->popup_id\">
                                        <input id=\"url\" type=\"hidden\" value=\"print.php?t=". base64_encode('check') ."\"/>"
                                    ) . "
                                    </div>";

                                $this->content['html']['main_table'.$this->popup_id] = $tbl;
                                $this->content['jscript'][] = "setTimeout(function(){window.open(document.getElementById('url').value,'_blank');popup_windows[document.getElementById('popup').value].hide();},500);";
                            }else{
                                $inner_tbl = "
                                    <div style=\"background-color:#ffffff;width:100%;height:400px;margin:0;text-align:center;\">" .
                                    ( $invalid ?
                                        "<div style=\"text-align:center;font-weight:bold;margin-top:25px;\">Invalid check data. Please try again.</div>"
                                        :
                                        "<div id=\"loading{$this->popup_id}\" style=\"width:100%;padding-top:50px;padding-bottom:45px;\">
                                            <h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch{$this->popup_id}\">Loading Checks...</h3>
                                            <div id=\"link_img_holder{$this->popup_id}\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
                                        </div>
                                        <iframe src=\"print.php?t=" . base64_encode('check') . "\" width=\"100%\" height=\"100%\" style=\"display:none;\" onReadyStateChange=\"if(this.readyState == 4 || this.readyState == 'complete'){toggle_display('loading{$this->popup_id}', 'none');this.style.display='block'}\"></iframe>
                                        <div style=\"padding-top:10px;text-align:left;\">
                                            <small>[<a href=\"print.php?t=" . base64_encode('check') . "\" target=\"_blank\" class=\"link_standard\">Open in a separate window</a>]</small>
                                        </div>"
                                    ) . "
                                    </div>";
                            }


							if ( $reprint ) {

								if ( $this->ajax_vars['reprint_from_func'] )
									return $inner_tbl;
								else {

									$tbl .=
									$this->form->form_tag() .
									$this->form->hidden( array(
    									"popup_id" => $this->popup_id
									) ) . "
									<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
										<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
											<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
												<p id=\"feedback_message{$this->popup_id}\"></p>
										</div>
										<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
											<tr>
												<td style=\"padding:0;\">
													 <div style=\"background-color:#ffffff;height:100%;\" id=\"check_content_holder{$this->popup_id}\">".$inner_tbl."</div>
												</td>
											</tr>
										</table>
									</div>" .
                                    $this->form->close_form();

									$this->content['popup_controls']["cmdTable"] = $inner_tbl;
								}

								return;
						 	}

						 } elseif ( $preview == 3 ) { # Reprint multiple checks

							$tbl .= "
							<div style=\"padding:15px;\">
								To reprint multiple checks, enter the check numbers below, separated by commas (i.e. 1001, 1002). This option is intended for
								checks that will be reprinted on the original check number. If your check was printed incorrectly, you must void that check and reprint
								it from your payables list.
								<div style=\"margin:10px 15px;\">".$this->form->text_area("name=reprint", "rows=3", "cols=75")."</div>
								".$this->form->button("value=Reprint Checks", "onClick=submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=doit_reprint_multi_checks');")."
							</div>";

							$this->content['popup_controls']["cmdTable"] = $tbl;
							return;

						 } else {

						 	if ( $this->ajax_vars['starting_check_no'] || $_POST['starting_check_no'] ) {

                                $checking_acct = ( $this->ajax_vars['checking_acct'] ? $this->ajax_vars['checking_acct'] : $_POST['checking_acct'] );
                                if ( ! $this->fetch_pay_queue($checking_acct, $preview, $this->ajax_vars['from_start']) )
                                    return $this->__trigger_error("An error was encountered while trying to fetch the payment queue. Please refresh this window and retry your transaction.", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);

                                $queue_rm_return = $_POST['rm_from_queue'];
                                if ( ! $acct->fetch_account_record($checking_acct) )
                                    return $this->__trigger_error("System error encountered when attempting to validate checking account record. Please reload window and try again. <!-- Tried fetching [ $checking_acct ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                                $check_no = $starting_check_no = $orig_starting_check = trim( ( $this->ajax_vars['starting_check_no'] ? $this->ajax_vars['starting_check_no'] : $_POST['starting_check_no'] ) );
                                if ( $check_no != 'a' ) {

                                	$r = $this->db->query("SELECT COUNT(*) AS Total
                                	                       FROM check_register t1
                                	                       WHERE t1.account_hash = '$checking_acct' AND t1.check_no = '$check_no'");
                                	if ( $this->db->result($r, 0, 'Total') ) {

                                        $this->content['jscript'] = "\$('pyfeedback_holder').show()";
                                        $this->content['html']['pyfeedback_message'] = 'The check number you entered has already been used. Please check your stock and enter the next un-used check number.';

                                        return;
                                	}

                                } else {

                                    $posted_pay_queue = $_POST['pay_queue'];
                                    $posted_check_no = $_POST['check_no'];
                                    $posted_vendor = $_POST['vendor_queue'];
                                    $orig_starting_check = $_POST['orig_starting_check'];
                                }

                                $grand_total = 0;
                                for ( $i = 0; $i < count($this->pay_queue); $i++ ) {

					                $pay_queue[ $this->pay_queue[$i]['vendor_hash'] ][] = $this->pay_queue[$i];

					                $grand_total = bcadd($grand_total, $this->pay_queue[$i]['pay_amount'], 2);
					                $to_pay++;
								}

								$inner_tbl =
								$this->form->hidden( array(
    								'orig_starting_check' => $orig_starting_check
								) ) . "
								<div style=\"margin:15px 35px;\">" .
									( count($this->pay_queue) ?
										"<div style=\"float:right;margin-top:10px;margin-right:15px;margin-bottom:15px;\">" .
											$this->form->button(
	    										"value=Preview Checks",
	                            				( $acct->account_hash ? "onClick=submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'btn=printchecks', 'preview=true', 'checking_acct=$checking_acct');" : "disabled title=The payment account is invalid, cannot continue." )
	                            			) . "
										</div>" : NULL
                            		)."
									<h3 style=\"margin-bottom:5px;color:#00477f;\">Payments To Be Made</h3>
								    <div style=\"margin-bottom:10px;margin-left:20px;font-weight:bold;\">" .
                                    ( $acct->account_hash ?
							            ( $acct->current_account['account_no'] ?
							                "{$acct->current_account['account_no']} : " : NULL
							            ) . "
							            {$acct->current_account['account_name']}
							            <span style=\"font-weight:normal;\">[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'empty_queue', 'popup_id=line_item');\" class=\"link_standard\">change</a></small>]</span>" : "<img src=\"images/alert.gif\" title=\"The payment account you have selected is invalid!\" />&nbsp;Invalid Payment Account!")."
							        </div>" .
							        ( count($pay_queue) ?
    									"<div style=\"padding-left:10px;font-weight:normal;margin-bottom:10px;\">[<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"checkall($$('input[id=payqueue_checkbox]'))\" style=\"font-weight:normal;\"><small>uncheck all</small></a>]</div>" : NULL
							        ) . "
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:100%;\" >
										<tr>
											<td style=\"font-weight:bold;vertical-align:middle;background-color:#efefef;border-bottom:1px solid #cccccc;\" colspan=\"2\" class=\"smallfont\">" .
        							        ( count($this->pay_queue) ?
												"You have $to_pay payment" . ( count($this->pay_queue) > 1 ? "s" : NULL ) . " to make totaling \$" . number_format($grand_total, 2) . "." : "&nbsp;"
        							        ) . "
											</td>
										</tr>";

                                    if ( $starting_check_no == 'a' && is_array($posted_check_no) ) {

                                        while ( list($qhash, $qcheck) = each($posted_check_no) )
										    $vendor_check[ $posted_vendor[ $qhash ] ][] = $qcheck;

                                        if ( is_array($pay_queue) ) { # Reassign check numbers, if necessary

                                            while ( list($vendor_hash, $queue_array) = each($pay_queue) ) {

                                                if ( is_array($vendor_check[$vendor_hash]) ) {

                                                    $vendor_check[$vendor_hash] = array_unique( $vendor_check[$vendor_hash] );
                                                    asort($vendor_check[$vendor_hash], SORT_NUMERIC);
                                                    $last_check = $use_check = $vendor_check[$vendor_hash][ count($vendor_check[$vendor_hash]) - 1 ];
                                                }

                                                while ( $this->check_taken($use_check, $checking_acct, $vendor_check, $vendor_hash) )
                                                    $use_check++;

                                                for ( $i = 0; $i < count($queue_array); $i++ ) {

                                                    if ( ! in_array($queue_array[$i]['queue_hash'], $posted_pay_queue) ) {

                                                        $check_used[ $use_check ]++;
                                                        if ( $check_used[$use_check] > 20 ) {

                                                        	$use_check++;
                                                        	$check_used[ $use_check ]++;
                                                        }

                                                        $pay_queue[ $vendor_hash ][$i]['check_no'] = $use_check;
                                                    }
                                                }

                                                $use_check++;
                                            }

                                            reset($pay_queue);
                                            unset($check_used);
                                        }
                                    }

									$vendors = new vendors($this->current_hash);
									$customers = new customers($this->current_hash);

									if ( $pay_queue ) {
										while (list($vendor_hash, $queue_array) = each($pay_queue)) {
											$b++;
											$total_paid = 0;
											unset($payment_tbl, $k, $currency_ops);
											if ( $queue_array[0]['type'] == 'R' )
												$customers->fetch_master_record($queue_array[0]['vendor_hash']);
											else
												$vendors->fetch_master_record($queue_array[0]['vendor_hash']);

											if (is_array($vendor_check[$vendor_hash]))
											    $vendor_check[$vendor_hash] = array_unique($vendor_check[$vendor_hash]);

                                            if (defined('MULTI_CURRENCY') && $vendors->current_vendor['currency'] && $vendors->current_vendor['currency'] != FUNCTIONAL_CURRENCY)
                                                $currency_ops = array($vendors->current_vendor['currency'], FUNCTIONAL_CURRENCY);

                                            $count = count($queue_array);
											for ($i = 0; $i < $count; $i++) {
												if ($this->fetch_queue_record($queue_array[$i]['queue_hash']) == false)
                                                    return $this->__trigger_error('Unable to fetch pay queue for vendor payment preview. This could be a result of a session timeout or another user made changes to one or more vendor payments. Please close this window and try your transaction again.', E_USER_ERROR, __FILE__, __LINE__, 1, 2);

												$k++;
												if ($starting_check_no == 'a')
												    $use_check = ($vendors->current_vendor['group_invoices'] && $queue_array[$i]['check_no'] ? $queue_array[$i]['check_no'] : ($check_no ? $check_no++ : $orig_starting_check));
												else {
													if ($queue_rm_return && $_POST['check_no'][$queue_array[$i]['queue_hash']])
														$use_check = $_POST['check_no'][$queue_array[$i]['queue_hash']];
													else
												    	$use_check = ($this->current_queue['check_no'] && $starting_check_no == 'a' ? $this->current_queue['check_no'] : ($vendors->current_vendor['group_invoices'] ? $check_no : $check_no++));
												}
												if (strspn($use_check, '0123456789') != strlen($use_check) && (strspn($posted_check_no[$posted_check_no[$queue_array[$i]['queue_hash']]], '0123456789') == strlen($posted_check_no[$posted_check_no[$queue_array[$i]['queue_hash']]]) || !$posted_check_no[$posted_check_no[$queue_array[$i]['queue_hash']]]))
												    $use_check = ($posted_check_no[$queue_array[$i]['queue_hash']] ? $posted_check_no[$queue_array[$i]['queue_hash']] : ($b > 0 && strspn($use_check, '0123456789') == strlen($use_check) ? $use_check : $orig_starting_check));

                                                while ($this->check_taken($use_check, $checking_acct, $vendor_check, $vendor_hash))
                                                    $use_check++;

                                                if (!is_array($vendor_check[$vendor_hash]) || !in_array($use_check, $vendor_check[$vendor_hash]))
											        $vendor_check[$vendor_hash][] = $use_check;

												$check_used[$use_check]++;
												if ($check_used[$use_check] > 20) {
													$use_check++;

                                                    while ($this->check_taken($use_check, $checking_acct, $vendor_check, $vendor_hash))
                                                        $use_check++;

                                                    if (!in_array($use_check, $vendor_check[$vendor_hash]))
                                                        $vendor_check[$vendor_hash][] = $use_check;

													$check_no = $use_check;
													$check_used[$use_check]++;
												} else
													unset($b);

												$payment_tbl .= "
												<tr>
													<td style=\"width:25px;" . ( $i < $count - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "vertical-align:bottom\" title=\"To include this invoice for payment, keep this box checked.\">" .
													    $this->form->checkbox(
    													    "name=pay_queue[]",
													        "value={$queue_array[$i]['queue_hash']}",
													        "id=payqueue_checkbox",
												            ( ! is_array($posted_pay_queue) || ( is_array($posted_pay_queue) && in_array($queue_array[$i]['queue_hash'], $posted_pay_queue)) ? "checked" : NULL )
												        ) . "
													</td>
													<td style=\"" . ( $i < $count - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "vertical-align:bottom\">" .
    													$this->form->text_box(
        													"name=check_no[{$queue_array[$i]['queue_hash']}]",
                            								"value=$use_check",
                            								"size=8",
												            "maxlength=18",
                            								"onFocus=select()"
    													) . "
        											</td>
													<td style=\"" . ( $i < $count - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "vertical-align:bottom\" class=\"smallfont\">".date(DATE_FORMAT, strtotime($queue_array[$i]['due_date']))."</td>
													<td style=\"" . ( $i < $count - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "vertical-align:bottom\">" .
														$this->form->text_box(
    														"name=invoice_no[{$queue_array[$i]['queue_hash']}]",
															"value=" .
    														( $queue_rm_return && $_POST['invoice_no'][ $queue_array[$i]['queue_hash'] ] ?
    												            $_POST['invoice_no'][ $queue_array[$i]['queue_hash'] ] :
    												            ( $this->current_queue['invoice_no'] ?
        												            $this->current_queue['invoice_no'] : $queue_array[$i]['invoice_no']
        												        )
        												    ),
												            "size=20"
												        ) . "
												    </td>
													<td style=\"padding-right:5px;vertical-align:bottom;text-align:right;" . ( $i < $count - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "\" class=\"smallfont\">
    													" . SYM . number_format($queue_array[$i]['pay_amount'], 2) . "
                                                    </td>
													<td style=\"" . ( $i < $count - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "vertical-align:bottom;\"><a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to remove this invoice from the payment queue? Performing this action will revert the invoice to un-paid.')){submit_form(\$('orig_starting_check').form, 'payables', 'exec_post', 'refresh_form', 'btn=printchecks', 'preview=true', 'checking_acct=$checking_acct', 'rm_from_queue=1', 'rm_queue_hash={$queue_array[$i]['queue_hash']}');}\" title=\"Remove this invoice from the payment queue\"><img src=\"images/void_check.gif\" border=\"0\" /></a></td>
												</tr>";


												$total_paid = bcadd($total_paid, $queue_array[$i]['pay_amount'], 2);
											}

											if ($vendors->current_vendor['group_invoices'])
												$check_no++;

											if ($queue_array[0]['type'] == 'R') {

												$bill_to =
												stripslashes($customers->current_customer['customer_name']) . "\n" .
												( $customers->current_customer['street'] ?
    												stripslashes($customers->current_customer['street']) . "\n" : NULL
    											) .
    											stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_vendor['zip']}";

											} else {

												if ( $vendors->current_vendor['remit_name'] || $vendors->current_vendor['remit_street'] ) {

													$bill_to =
													( $vendors->current_vendor['remit_name'] ?
														stripslashes($vendors->current_vendor['remit_name']) . "\n" : NULL
													) .
													( $vendors->current_vendor['remit_street'] ?
    													stripslashes($vendors->current_vendor['remit_street']) . "\n" : NULL
    												) .
    												( $vendors->current_vendor['remit_city'] ?
        												stripslashes($vendors->current_vendor['remit_city']) .
        												( $vendors->current_vendor['remit_state'] ?
            												", " : NULL
        												) : NULL
        											) .
        											( $vendors->current_vendor['remit_state'] ?
            											"{$vendors->current_vendor['remit_state']} " : NULL
        											) .
        											( $vendors->current_vendor['remit_zip'] ?
            											$vendors->current_vendor['remit_zip'] : NULL
            										);

												} else {

													$bill_to =
													stripslashes($vendors->current_vendor['vendor_name']) . "\n" .
													( $vendors->current_vendor['street'] ?
    													stripslashes($vendors->current_vendor['street']) . "\n" : NULL
    												) .
    												stripslashes($vendors->current_vendor['city']) . ", {$vendors->current_vendor['state']} {$vendors->current_vendor['zip']}";
												}
											}

											$inner_tbl .= "
											<tr >
												<td style=\"padding-left:10px;padding-top:10px;vertical-align:top;font-weight:bold;\" class=\"smallfont\">
													" . stripslashes($queue_array[0]['vendor_name']) . "
													<div style=\"font-weight:normal;padding-top:8px;\">
														Total Due: \$" . number_format($total_paid, 2) . "
													</div>" .
        											( is_array($currency_ops) && count($currency_ops) > 1 ?
														"<div style=\"margin-top:8px;font-weight:normal\">
	                                                        Payment Currency: " .
	                                                        $this->form->select(
    	                                                        "currency[{$queue_array[0]['vendor_hash']}]",
	                                                            $currency_ops,
	                                                            ( $this->current_queue['pay_queue_currency'] ? $this->current_queue['pay_queue_currency'] : $vendors->current_vendor['currency'] ),
	                                                            $currency_ops,
	                                                            "blank=1"
	                                                        ) . "
														</div>" : NULL
                                                    ) . "
												</td>
												<td style=\"text-align:right;padding-right:15px;\">" .
													$this->form->text_area(
    													"name=bill_to[{$queue_array[0]['vendor_hash']}]",
														"value=" .
    													( $queue_rm_return && $_POST['bill_to'][$vendor_hash] ?
    														stripslashes($_POST['bill_to'][$vendor_hash]) :
    														( $this->current_queue['remit_to'] ?
        														stripslashes($this->current_queue['remit_to']) : stripslashes($bill_to)
        													)
        												),
														"rows=4",
														"cols=40"
    												) . "
												</td>
											</tr>
											<tr>
												<td colspan=\"2\" style=\"padding-bottom:25px;" . ( $b < count($pay_queue) - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "\">
													<div style=\"font-weight:bold;margin-top:5px;margin-left:20px;font-size:8pt;\">
														<img src=\"images/collapse.gif\" name=\"imginvoice_holder{$queue_array[0]['vendor_hash']}\" />
														&nbsp;
														<span style=\"cursor:hand;\" onClick=\"shoh('invoice_holder{$queue_array[0]['vendor_hash']}');\" >
    														" . count($queue_array) . " Invoice" . ( count($queue_array) > 1 ? "s" : NULL ) . " for " . stripslashes($queue_array[0]['vendor_name']) . "
    													</span>
													</div>
													<div style=\"margin-top:3px;margin-left:20px;width:95%;display:block;\" id=\"invoice_holder{$queue_array[0]['vendor_hash']}\">
														<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:100%;\">
															<tr style=\"background-color:#efefef;\">
																<td style=\"width:25px;border-bottom:1px solid #cccccc;\">&nbsp;</td>
																<td style=\"font-size:8pt;border-bottom:1px solid #cccccc;\">Check No.</td>
																<td style=\"font-size:8pt;border-bottom:1px solid #cccccc;\">Due Date</td>
																<td style=\"font-size:8pt;border-bottom:1px solid #cccccc;\">Invoice No.</td>
																<td style=\"font-size:8pt;border-bottom:1px solid #cccccc;\" class=\"num_field\">Payment Amt</td>
																<td style=\"border-bottom:1px solid #cccccc;\">&nbsp;</td>
															</tr>
															$payment_tbl
														</table>
													</div>
												</td>
											</tr>";


										}
									}

									if (!count($pay_queue))
										$inner_tbl .= "
										<tr >
											<td colspan=\"2\" class=\"smallfont\">You have no payments in the pay queue.</td>
										</tr>";
									$inner_tbl .= "
									</table>
								</div>";
								if ($starting_check_no != 'a') {
									$this->content['jscript'] = $jscript;
									$this->content['html']['check_content_holder'.$this->popup_id] = $inner_tbl;

                                    return;
								} else {
								    $this->content['action'] = 'continue';

                                    $write_post = true;
								}
							} else {

								$checking_acct = $next_check_no = array();
								$r = $this->db->query("SELECT
                            							   t1.account_hash,
                            							   t2.account_name,
    								                       t2.account_no,
    								                       t2.next_check_no
								                       FROM vendor_pay_queue t1
								                       LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
								                       GROUP BY t1.account_hash");
							    while ( $row = $this->db->fetch_assoc($r) ) {

							    	$checking_acct[ $row['account_hash'] ] = ( $row['account_no'] ? "{$row['account_no']} " : NULL ) . $row['account_name'];
								    $next_check_no[ $row['account_hash'] ] = $row['next_check_no'];
							    }

							    reset($checking_acct);
							    reset($next_check_no);

                                foreach ( $next_check_no as $_account => $_checkno ) {

                                	while ( $this->check_taken($_checkno, $_account) )
                                    	$_checkno++;

                                    $next_check_no[ $_account ] = $_checkno;
                                    $hidden .= $this->form->hidden( array(
                                        "check_{$_account}" =>  $_checkno
                                    ) );
                                }

								$inner_tbl = "
								$hidden
								<div style=\"margin:15px 35px;text-align:left;\">
						            <div id=\"pyfeedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
						                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
						                <p id=\"pyfeedback_message\"></p>
						            </div>
								    <table>" .
    								( $checking_acct ?
                                        "<tr>
                                            <td style=\"width:50%;text-align:right;font-weight:bold;padding-bottom:5px;\">" .
            								( count($checking_acct) == 1 ?
                                                "Printing checks for account:" : "Check run checking account:"
            								) . "
                                            </td>
                                            <td style=\"width:50%;text-align:left;padding-left:5px;font-weight:normal;padding-bottom:5px;\">" .
            								( count($checking_acct) == 1 ?
                                                current($checking_acct) .
                                                $this->form->hidden( array(
                                                    'checking_acct' => key($checking_acct)
                                                ) )
                                                :
                                                $this->form->select(
                                                    "checking_acct",
                                                    array_values($checking_acct),
                                                    '',
                                                    array_keys($checking_acct),
                                                    "blank=1",
                                                    "onChange=if( this.value && \$('check_' + this.options[selectedIndex].value) ){\$('start_check').value=\$F('check_' + this.options[selectedIndex].value);}"
                                                )
                                            ) . "
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style=\"width:50%;text-align:right;font-weight:bold;\">Starting check number:</td>
                                            <td style=\"width:50%;text-align:left;padding-left:5px;\">" .
                                                $this->form->text_box(
                                                    "name=start_check",
                                                    "value={$next_check_no[ key($checking_acct) ]}",
                                                    "size=10"
                                                ) . "&nbsp;" .
                                                $this->form->button(
                                                    "value=Go",
                                                    "onClick=if(\$F('start_check') && !isNaN(\$F('start_check'))){agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'empty_queue', 'popup_id={$this->popup_id}', 'from_start=1', 'starting_check_no='+\$F('start_check'), 'checking_acct='+\$F('checking_acct'), 'p={$this->ajax_vars['p']}', 'order{$this->ajax_vars['order']}', 'order_dir{$this->ajax_vars['order_dir']}')}else{alert('Please enter your starting check number!');}"
                                                ) . "
                                            </td>
                                        </tr>" : "
                                        <tr>
                                            <td colspan=\"2\" style=\"font-weight:bold;\">The payment queue is empty.</td>
                                        </tr>"
                                    ) . "
								    </table>
								</div>";


							}
						}
						$tbl .=
						( ! $preview ?
    						$inner_tbl : NULL
    					) . "
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		if ($preview)
			return $inner_tbl;
		else {
			$this->content['jscript'] = $jscript;
			if ($write_post)
			    $this->content['html']['check_content_holder'.$this->popup_id] = $inner_tbl;
			else
			    $this->content['popup_controls']["cmdTable"] = $tbl;
			return;
		}
	}

	function doit_void_queue() {
		$r = $this->fetch_pay_queue();

	}

	function doit_unflag() {
		$invoice_hash = ($_POST['invoice_hash'] ? $_POST['invoice_hash'] : $this->ajax_vars['invoice_hash']);
		$p = ($_POST['p'] ? $_POST['p'] : $this->ajax_vars['p']);
		$order = ($_POST['order'] ? $_POST['order'] : $this->ajax_vars['order']);
		$order_dir = ($_POST['order_dir'] ? $_POST['order_dir'] : $this->ajax_vars['order_dir']);
		$active_search = ($_POST['active_search'] ? $_POST['active_search'] : $this->ajax_vars['active_search']);
		$proposal_hash = ($_POST['proposal_hash'] ? $_POST['proposal_hash'] : $this->ajax_vars['proposal_hash']);

		$this->db->query("UPDATE `vendor_payables`
						  SET `pay_flag` = 0 , `last_change` = '".$this->current_hash."' , `timestamp` = ".time()."
						  WHERE `invoice_hash` = '$invoice_hash'");

		$this->content['action'] = 'continue';
		$this->content['page_feedback'] = "Pay flag has been removed.";
		$this->content['jscript_action'] = "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', '".($proposal_hash ? "proposal_payables" : "showall")."', 'p=$p', 'order=$order', 'order_dir=$order_dir', '".($proposal_hash ? "proposal_hash=$proposal_hash" : "active_search=$active_search")."'".($proposal_hash ? ", 'otf=1'" : NULL).");".($proposal_hash ? "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash=".$proposal_hash."\', \'otf=1\');', 1500)" : NULL);
	}

	function fetch_commissions_paid($proposal_hash=NULL) {
		if (!$proposal_hash)
			$proposal_hash = $this->proposal_hash;

		if (!$this->proposal_hash)
			return;

		$this->commissions_paid = array();

		$result = $this->db->query("SELECT commissions_paid.* , users.full_name
									FROM `commissions_paid`
									LEFT JOIN `users` ON users.id_hash = commissions_paid.user_hash
									WHERE commissions_paid.proposal_hash = '$proposal_hash'");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->commissions_paid, $row);

		$this->total_commissions_paid = count($this->commissions_paid);
		return;
	}

	function commissions($local=NULL) {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		if (!$this->proposal_hash) {
			$this->proposal_hash = $this->ajax_vars['proposal_hash'];
			$proposals = new proposals($this->current_hash);
			$proposals->fetch_master_record($this->proposal_hash);
		}
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		$this->fetch_commissions_paid();

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\">".($this->total_commissions_paid ? "
								Showing 1 - ".$this->total_commissions_paid." of ".$this->total_commissions_paid." commissions paid against this proposal." : "&nbsp;")."
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"width:150px;vertical-align:bottom;padding-left:15px;\" nowrap>Date Paid</td>
							<td style=\"width:400px;vertical-align:bottom;\">Paid To</td>
							<td style=\"vertical-align:bottom;width:150px;padding-right:10px;\" class=\"num_field\">Proposal Cost</td>
							<td style=\"vertical-align:bottom;width:150px;padding-right:10px;\" class=\"num_field\">Commissions Paid</td>
						</tr>";

						for ($i = 0; $i < $this->total_commissions_paid; $i++) {
							$onClick = "onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_commission', 'commission_hash=".$this->commissions_paid[$i]['commission_hash']."', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item')\"";
							$tbl .= "
							<tr onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" >
								<td style=\"vertical-align:bottom;padding-left:15px;\" style=\"text-align:left;\" $onClick>".date(DATE_FORMAT, strtotime($this->commissions_paid[$i]['paid_date']))."</td>
								<td style=\"vertical-align:bottom;\" $onClick>".$this->commissions_paid[$i]['full_name']."</td>
								<td style=\"vertical-align:bottom;padding-right:10px;\" class=\"num_field\" $onClick>$".number_format($this->commissions_paid[$i]['cost'], 2)."</td>
								<td style=\"vertical-align:bottom;padding-right:10px;\" class=\"num_field\" $onClick>$".number_format($this->commissions_paid[$i]['amount'], 2)."</td>
							</tr>
							<tr>
								<td style=\"background-color:#cccccc;\" colspan=\"4\"></td>
							</tr>";
						}

						if (!$this->total_commissions_paid)
							$tbl .= "
							<tr >
								<td colspan=\"4\">There have been no commissions paid against this proposal.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		if ($local)
			return $tbl;
		else
			$this->content['html']['tcontent7_3'] = $tbl;

		return $tbl;
	}

	function fetch_commission_record($commission_hash) {
		$this->current_commission = array();

		$result = $this->db->query("SELECT commissions_paid.* , users.full_name , proposals.proposal_no ,
									proposals.proposal_descr , customers.customer_name , COUNT(line_items.commission_hash) AS total_lines
									FROM `commissions_paid`
									LEFT JOIN `users` ON users.id_hash = commissions_paid.user_hash
									LEFT JOIN `proposals` ON proposals.proposal_hash = commissions_paid.proposal_hash
									LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
									LEFT JOIN `line_items` ON line_items.commission_hash = commissions_paid.commission_hash
									WHERE commissions_paid.commission_hash = '$commission_hash'
									GROUP BY commissions_paid.commission_hash");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_commission = $row;
			$this->commission_hash = $commission_hash;

			if ($this->current_commission['total_lines']) {
				$result = $this->db->query("SELECT line_items.item_hash , line_items.item_no , vendors.vendor_name ,
											vendor_products.product_name , line_items.sell , (line_items.sell * line_items.qty) AS ext_sell
											FROM `line_items`
											LEFT JOIN `vendors` ON vendors.vendor_hash = line_items.vendor_hash
											LEFT JOIN `vendor_products` ON vendor_products.product_hash = line_items.product_hash
											WHERE line_items.proposal_hash = '".$this->current_commission['proposal_hash']."' AND line_items.commission_hash = '".$this->commission_hash."'");
				while ($row = $this->db->fetch_assoc($result))
					$this->current_commission['line_items'][] = $row;
			}
			return true;
		}

		return false;
	}

	function edit_commission($commission_hash) {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->proposals = new proposals($this->current_hash);
		$valid = $this->fetch_commission_record($this->ajax_vars['commission_hash']);

		$this->content['popup_controls']['popup_title'] = "View &amp; Edit Commission Paid";

		if (!$valid)
			return $this->error_template("The commission record cannot be retrieved.");

		//Line item table first
		$lines = new line_items($this->proposal_hash);

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("commission_hash" => $this->commission_hash,
								  "popup_id" => $this->popup_id))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\">
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
									Proposal Commission : ".$this->current_commission['proposal_no'].($this->current_commission['paid_in_full'] ? "
										<span style=\"font-weight:normal;font-size:9pt;font-style:italic;padding-left:10px;\">(Paid In Full)</span>" : NULL).($this->current_commission['proposal_descr'] ?
											"<div style=\"font-size:8pt;margin-left:25px;margin-top:5px;\">".
												(strlen($this->current_commission['proposal_descr']) > 45 ?
													substr($this->current_commission['proposal_descr'], 0, 43)."..." : $this->current_commission['proposal_descr'])."</div>" : NULL)."
								</h3>
								<div style=\"padding:5px 0 5px 5px;\">
									<!--<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'print_po', 'proposal_hash=".$this->proposal_hash."', 'po_hash=".$this->po_hash."', 'popup_id=print_po');\" class=\"link_standard\"><img src=\"images/print.gif\" title=\"Print this commission record.\" border=\"0\" /></a>
									&nbsp;
									<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to kill and delete this commission record? Any items that have been paid on this commission will be edited to reflect unpaid commissions. Please make sure you are sure this is really what you want to do!')){submit_form($('commission_hash').form, 'payables', 'exec_post', 'refresh_form', 'action=doit_commissions', 'method=rm');}\" class=\"link_standard\"><img src=\"images/kill.gif\" title=\"Delete this commission record.\" border=\"0\" /></a>
									&nbsp;
									-->
								</div>
								<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:90%;\">
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Customer:</td>
										<td style=\"background-color:#ffffff;\">".$this->current_commission['customer_name']."</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Paid To:</td>
										<td style=\"background-color:#ffffff;\">".$this->current_commission['full_name']."</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Paid On:</td>
										<td style=\"background-color:#ffffff;\">".date(DATE_FORMAT, strtotime($this->current_commission['paid_date']))."</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;\">Amount Paid:</td>
										<td style=\"background-color:#ffffff;\">
											$".number_format($this->current_commission['amount'], 2).($this->current_commission['previous_amount'] ?
												"<div style=\"margin:5px 15px;font-size:8pt;font-style:italic;\">Previous Commissions Paid: $".number_format($this->current_commission['previous_amount'], 2)."</div>" : NULL)."
										</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Total Cost:</td>
										<td style=\"background-color:#ffffff;\">$".number_format($this->current_commission['cost'], 2)."</td>
									</tr>
								</table>
								<div style=\"padding-top:10px;font-weight:bold;margin-bottom:5px;\" >
									Commissioned Line Items:
								</div>
								<div style=\"padding-top:0;height:".($this->current_commission['total_lines'] > 5 ? "250px;" : (($this->current_commission['total_lines'] * 50) + 25)."px").";overflow:scroll;overflow-X:hidden;\">
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:100%;\" >
										<tr>
											<td style=\"font-weight:bold;font-size:9pt;background-color:#efefef;\">Vendor / Product</td>
											<td style=\"font-weight:bold;font-size:9pt;background-color:#efefef;\" class=\"num_field\">Item Sell</td>
											<td style=\"font-weight:bold;font-size:9pt;background-color:#efefef;\" class=\"num_field\">Ext Sell</td>
										</tr>
										<tr>
											<td style=\"background-color:#cccccc;\" colspan=\"3\"></td>
										</tr>";
									for ($i = 0; $i < count($this->current_commission['line_items']); $i++) {
										$tbl .= "
										<tr>
											<td style=\"font-size:9pt;font-style:italic;\" colspan=\"3\">".$this->current_commission['line_items'][$i]['vendor_name']." : ".$this->current_commission['line_items'][$i]['product_name']."</td>
										</tr>
										<tr>
											<td style=\"font-size:9pt;\">".$this->current_commission['line_items'][$i]['item_no']."</td>
											<td style=\"font-size:9pt;vertical-align:bottom;\" class=\"num_field\">$".number_format($this->current_commission['line_items'][$i]['sell'], 2)."</td>
											<td style=\"font-size:9pt;vertical-align:bottom;\" class=\"num_field\">$".number_format($this->current_commission['line_items'][$i]['ext_sell'], 2)."</td>
										</tr>".($i < $this->current_commission['total_lines'] ? "
										<tr>
											<td colspan=\"3\" style=\"background-color:#cccccc;\"><td>
										</tr>" : NULL);
									}
								$tbl .= "
									</table>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		//$this->content['popup_controls']['onclose'] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'payables', 'otf=1', 'proposal_hash=".$this->current_commission['proposal_hash']."');";
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function proposal_payables($local=NULL) {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		if (!$this->proposal_hash) {
			$this->proposal_hash = $this->ajax_vars['proposal_hash'];
			$proposals = new proposals($this->current_hash);
			$proposals->fetch_master_record($this->proposal_hash);
		}
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		$order_by_ops = array("timestamp"			=>	"vendor_payables.timestamp",
							  "vendor"				=>	"vendors.vendor_name",
							  "po_no"				=>	"purchase_order.po_no",
							  "invoice_no"			=>	"vendor_payables.invoice_no",
							  "invoice_date"		=>	"vendor_payables.invoice_date",
							  "due_date"			=>	"vendor_payables.due_date", );

		if (!$this->po)
			$this->po = new purchase_order($this->proposal_hash);

		$this->fetch_proposal_payables($this->proposal_hash, $order_by_ops[($order ? $order : $order_by_ops['timestamp'])], $order_dir);

		$tbl .= "
		<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
			<tr>
				<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"8\">".($this->total ? "
					Showing 1 - ".$this->total." of ".$this->total." vendor invoices for this proposal." : NULL)."
					<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->po->total && $this->p->ck(get_class($this), 'A') ? "
						<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'from_class=proposals');\"><img src=\"images/new_invoice.gif\" title=\"New vendor bill/deposit for this proposal\" border=\"0\" /></a>
						&nbsp;" : "&nbsp;").($this->total && $this->p->ck(get_class($this), 'F') ? "
						<a href=\"javascript:void(0);\" onClick=\"submit_form($('checkallflag').form, 'payables', 'exec_post', 'refresh_form', 'action=doit_flag_invoice', 'proposal_hash=".$this->proposal_hash."');\"><img src=\"images/flag2.gif\" title=\"Flag selected invoices for payment\" border=\"0\" /></a>" : "&nbsp;")."
					</div>
				</td>
			</tr>
			<tr class=\"thead\" style=\"font-weight:bold;\">
				<td>".$this->form->checkbox("name=checkallflag", "value=".$this->invoice_info[$i]['invoice_hash'], "onClick=checkall(document.getElementsByName('flag[]'), this.checked);")."</td>
				<td style=\"width:100px;vertical-align:bottom;\">
					<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=vendor', 'order_dir=".($order == "vendor" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'limit=".$limit."');\" style=\"color:#ffffff;text-decoration:underline;\">
						Vendor</a>
					".($order == 'vendor' ?
						"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
				</td>
				<td style=\"width:110px;vertical-align:bottom;\">
					<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=po_no', 'order_dir=".($order == "po_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'limit=".$limit."');\" style=\"color:#ffffff;text-decoration:underline;\">
						PO No.
					</a>
					".($order == 'po_no' ?
						"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
				</td>
				<td style=\"width:110px;vertical-align:bottom;\">
					<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=invoice_no', 'order_dir=".($order == "invoice_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'limit=".$limit."');\" style=\"color:#ffffff;text-decoration:underline;\">
						Invoice No.
					</a>
					".($order == 'invoice_no' ?
						"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
				</td>
				<td style=\"width:110px;vertical-align:bottom;\">
					<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=invoice_date', 'order_dir=".($order == "invoice_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'limit=".$limit."');\" style=\"color:#ffffff;text-decoration:underline;\">
						Invoice Date
					</a>
					".($order == 'invoice_date' ?
						"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
				</td>
				<td style=\"width:110px;vertical-align:bottom;\">
					<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=due_date', 'order_dir=".($order == "due_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'limit=".$limit."');\" style=\"color:#ffffff;text-decoration:underline;\">
						Due Date
					</a>
					".($order == 'due_date' ?
						"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
				</td>
				<td style=\"vertical-align:bottom;\" class=\"num_field\">Amount</td>
				<td style=\"vertical-align:bottom;\" class=\"num_field\">Balance</td>
			</tr>";

			for ($i = 0; $i < $this->total; $i++) {

				if ($this->p->ck(get_class($this), 'V'))
					$onClick = "agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'proposal_hash=".$this->proposal_hash."', 'invoice_hash=".$this->invoice_info[$i]['invoice_hash']."', 'popup_id=line_item', 'new=true', 'from_class=proposals');";

                unset($border_btm);
                if ( $i < ( $this->total - 1 ) )
                    $border_btm = "border-bottom:1px solid #cccccc;";

                $sym = SYM;
	            if ($this->invoice_info[$i]['currency']) {
	                $sym = $cur_sym[$this->invoice_info[$i]['currency']];
	                $this->invoice_info[$i]['amount'] = currency_exchange($this->invoice_info[$i]['amount'], $this->invoice_info[$i]['exchange_rate']);
	                $this->invoice_info[$i]['balance'] = currency_exchange($this->invoice_info[$i]['balance'], $this->invoice_info[$i]['exchange_rate']);
	            }

	            if ( $this->invoice_info[$i]['type'] == 'C' ) {
                    $this->invoice_info[$i]['amount'] = bcmul($this->invoice_info[$i]['amount'], -1, 2);
                    $this->invoice_info[$i]['balance'] = bcmul($this->invoice_info[$i]['balance'], -1, 2);
	            }

				$this->invoice_info[$i]['complete'] = 1;
				$tbl .= "
				<tr ".($this->p->ck(get_class($this), 'V') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">
					<td style=\"cursor:auto;$border_btm\">".($this->invoice_info[$i]['balance'] <= 0 ? "
						<img src=\"images/paid.gif\" title=\"This invoice has been paid in full\" />" : ($this->invoice_info[$i]['pay_flag'] && !$this->invoice_info[$i]['hold'] ? "
							<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'exec_post', 'refresh_form', 'action=doit_unflag', 'from_class=payables', 'invoice_hash=".$this->invoice_info[$i]['invoice_hash']."', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'proposal_hash=".$this->proposal_hash."');\"><img src=\"images/flag.gif\" border=\"0\" title=\"This invoice has been flagged for payment. To unflag the invoice, click here.\" /></a>" : (!$this->invoice_info[$i]['complete'] ?
								"<img src=\"images/alert.gif\" title=\"This invoice is incomplete. Please re-open, complete the expenses and save it\" />" : $this->form->checkbox("name=flag[]", "value=".$this->invoice_info[$i]['invoice_hash'], "title=".($this->invoice_info[$i]['hold'] ? "This bill is on payment hold." : "Check to flag this invoice for payment."), ($this->invoice_info[$i]['hold'] ? "disabled" : NULL)))))."
					</td>
					<td onClick=\"".$onClick."\" style=\"vertical-align:bottom;$border_btm\" style=\"text-align:left;".($this->invoice_info[$i]['hold'] ? "color:#ff0000;" : NULL)."\" ".($this->invoice_info[$i]['hold'] ? "title=\"This invoice is on payment hold.\"" : NULL)." $onClick>".(!$this->invoice_info[$i]['complete'] ? "
						<img src=\"images/alert.gif\" title=\"This invoice is incomplete. Please re-open, complete the expenses and save it\" />&nbsp;&nbsp;" : NULL).
						$this->invoice_info[$i]['vendor_name']."
					</td>
					<td onClick=\"".$onClick."\" style=\"vertical-align:bottom;$border_btm\" style=\"text-align:left;\" $onClick>".($this->invoice_info[$i]['type'] == 'D' ?
						"<i>".$this->invoice_info[$i]['po_no']."</i>" : $this->invoice_info[$i]['po_no'])."
					</td>
					<td onClick=\"".$onClick."\" style=\"vertical-align:bottom;$border_btm\" style=\"text-align:left;\" $onClick>".($this->invoice_info[$i]['type'] == 'D' ?
						"<i>Deposit</i>" : $this->invoice_info[$i]['invoice_no'])."
					</td>
					<td onClick=\"".$onClick."\" style=\"vertical-align:bottom;$border_btm\" $onClick>".date(DATE_FORMAT, strtotime($this->invoice_info[$i]['invoice_date']))."</td>
					<td onClick=\"".$onClick."\" style=\"vertical-align:bottom;$border_btm\" $onClick>".date(DATE_FORMAT, strtotime($this->invoice_info[$i]['due_date']))."</td>
					<td onClick=\"".$onClick."\" style=\"vertical-align:bottom;$border_btm\" class=\"num_field\" $onClick>" .
					( bccomp($this->invoice_info[$i]['amount'], 0, 2) == -1 ?
						"($sym" . number_format(bcmul($this->invoice_info[$i]['amount'], -1, 2), 2) . ")" : "$sym" . number_format($this->invoice_info[$i]['amount'], 2)
					) . "
					<td onClick=\"".$onClick."\" style=\"vertical-align:bottom;$border_btm\" class=\"num_field\" $onClick>" .
					( bccomp($this->invoice_info[$i]['balance'], 0, 2) == -1 ?
						"($sym" . number_format(bcmul($this->invoice_info[$i]['balance'], -1, 2), 2) . ")" : $sym.number_format($this->invoice_info[$i]['balance'], 2)
					) . "
					</td>
				</tr>";
			}

			if ( ! $this->total )
				$tbl .= "
				<tr >
					<td colspan=\"8\">You have no vendor invoices to display under this proposal. </td>
				</tr>";

		$tbl .= "
		</table>";

		if ($local)
			return $tbl;
		else
			$this->content['html']['tcontent7_1'] = $tbl;

		return $tbl;
	}

	function fetch_memo_costs($proposal_hash=NULL) {
		if (!$proposal_hash)
			$proposal_hash = $this->proposal_hash;

		if (!$this->proposal_hash)
			return;

		$this->memo_cost_items = array();

		$result = $this->db->query("SELECT memo_costs.* , a1.account_name as debit_account , a2.account_name as credit_account
									FROM `memo_costs`
									LEFT JOIN `accounts` as a1 ON a1.account_hash = memo_costs.debit_hash
									LEFT JOIN `accounts` as a2 ON a2.account_hash = memo_costs.credit_hash
									WHERE memo_costs.proposal_hash = '$proposal_hash' AND memo_costs.deleted = 0
									ORDER BY memo_costs.entry_date ASC");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->memo_cost_items, $row);

		$this->total_memo_costs = count($this->memo_cost_items);
		return;
	}

	function fetch_memo_cost_record($memo_hash) {
		$result = $this->db->query("SELECT memo_costs.* , a1.account_name as debit_account , a2.account_name as credit_account
									FROM `memo_costs`
									LEFT JOIN `accounts` as a1 ON a1.account_hash = memo_costs.debit_hash
									LEFT JOIN `accounts` as a2 ON a2.account_hash = memo_costs.credit_hash
									WHERE memo_costs.memo_hash = '$memo_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_memo = $row;
			$this->memo_hash = $memo_hash;

			return true;
		}

		return false;
	}

	function edit_memo_cost() {

		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$from = $this->ajax_vars['from'];
		$commission_hash = $this->ajax_vars['commission_hash'];
		$this->popup_id = $this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );

		$this->content['popup_controls']['popup_title'] = "New Memo Cost";

		if ( $this->ajax_vars['memo_hash'] ) {

			if ( ! $this->fetch_memo_cost_record($this->ajax_vars['memo_hash']) )
    			return $this->__trigger_error("System error encountered when attempting to lookup memo record. Please reload window and try again. <!-- Tried fetching memo cost [ {$this->ajax_vars['memo_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

            $this->content['popup_controls']['popup_title'] = "Edit Memo Cost";
		}

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
    		"popup_id"        => $this->popup_id,
    		"memo_hash"       => $this->memo_hash,
    		"proposal_hash"   => $proposal_hash,
    		"p"               => $this->ajax_vars['p'],
    		'order'           => $this->ajax_vars['order'],
    		'order_dir'       => $this->ajax_vars['order_dir'],
    		'from'            => $from,
    		'commission_hash' => $commission_hash
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
								<div style=\"float:right;margin-top:15px;margin-right:25px;margin-bottom:10px;\">" .
									$this->form->button(
    									"value=Save &amp; Close",
    									"id=memo_btn",
    									"onClick=submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=doit_memo_cost');this.disabled=1"
									) . "
								</div>
								<h3 style=\"margin-bottom:5px;color:#00477f;\">Create/Edit Memo Cost</h3>" .
								( $this->memo_hash ?
									"<div style=\"padding:5px 0 5px 5px;\">
										<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this memo cost? This action CAN NOT be undone!')){submit_form(\$('memo_hash').form, 'payables', 'exec_post', 'refresh_form', 'action=doit_memo_cost', 'delete=1');}\" class=\"link_standard\"><img src=\"images/kill.gif\" title=\"Delete this memo cost.\" border=\"0\" /></a>
										&nbsp;
									</div>" : NULL
								) . "
								<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:90%;\" >
									<tr>
										<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;\" id=\"err1{$this->popup_id}\">Entry Date:</td>
										<td style=\"background-color:#ffffff;padding-top:10px;\">" .
										( $this->memo_hash ?
											date(DATE_FORMAT, strtotime($this->current_memo['entry_date']))
											:
											"<span id=\"entry_date_holder{$this->popup_id}\"></span>"
										) .
										"</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;vertical-align:top;\" id=\"err2{$this->popup_id}\">Amount:</td>
										<td style=\"background-color:#ffffff;padding-top:10px;\">" .
											( $this->memo_hash ?
												"\$" . number_format($this->current_memo['amount'], 2)
	                                            :
	    										$this->form->text_box(
	    											"name=amount",
	    											"size=10",
	    											"value=" . ( $this->memo_hash ? number_format($this->current_memo['amount'], 2) : NULL ),
	    											"style=text-align:right;",
	    											"onBlur=if(this.value){this.value=formatCurrency(this.value)}"
	    										)
											) . "
										    <div style=\"margin-top:5px;\">" .
										        $this->form->checkbox(
    										        "name=non_posting",
    										        "value=1",
    										        ( $this->current_memo ? "disabled" : NULL ),
    										        ( $this->current_memo['non_posting'] ? "checked" : NULL ),
    										        "onClick=if(this.checked==1){\$('debit_account').disabled=1;\$('dr_showall').hide();\$('credit_account').disabled=1;\$('cr_showall').hide();}else{\$('debit_account').disabled=0;\$('dr_showall').show();\$('credit_account').disabled=0;\$('cr_showall').show();}")."
										        &nbsp;
										        Is this memo cost non posting?
										    </div>
									    </td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;\" id=\"err3{$this->popup_id}\">Debit Account:</td>
										<td style=\"background-color:#ffffff;padding-top:10px;\">" .
										( $this->memo_hash ?
											( $this->current_memo['non_posting'] == 1 ?
											    "<i>Non-Posting</i>" : $this->current_memo['debit_account']
											)
											:
											$this->form->text_box(
    											"name=debit_account",
												"value=" . ( $this->current_memo ? $this->current_memo['debit_account'] : NULL ),
												"autocomplete=off",
												"size=25",
												"onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'debit_account', 'debit_account_hash', 1);}",
											    "onKeyUp=if(ka==false && this.value){key_call('payables', 'debit_account', 'debit_account_hash', 1);}",
												"onKeyDown=clear_values('debit_account_hash');",
												"onBlur=key_clear();"
											) .
											$this->form->hidden( array(
    											"debit_account_hash" => $this->current_memo['debit_hash']
											) ) . "
											<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('debit_account'), 'top')+20, findPos(\$('debit_account'), 'left')+1);key_list('payables', 'debit_account', 'debit_account_hash', '*');\" style=\"visibility:" . ( $this->current_memo['non_posting'] ? "hidden" : "visible" ) . "\" id=\"dr_showall\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>"
										) . "
										</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;\" id=\"err4{$this->popup_id}\">Credit Account:</td>
										<td style=\"background-color:#ffffff;padding-top:10px;\">" .
										( $this->memo_hash ?
											( $this->current_memo['non_posting'] ?
                                                "<i>Non-Posting</i>" : $this->current_memo['credit_account']
											)
											:
											$this->form->text_box(
    											"name=credit_account",
												"value=" . ( $this->current_memo ? $this->current_memo['credit_account'] : NULL ),
												"autocomplete=off",
												"size=25",
												"onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'credit_account', 'credit_account_hash', 1);}",
											    "onKeyUp=if(ka==false && this.value){key_call('payables', 'credit_account', 'credit_account_hash', 1);}",
												"onKeyDown=clear_values('credit_account_hash');",
												"onBlur=key_clear();"
											) .
											$this->form->hidden( array(
    											"credit_account_hash" => $this->current_memo['credit_hash']
											) ) . "
											 <a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('credit_account'), 'top')+20, findPos(\$('credit_account'), 'left')+1);key_list('payables', 'credit_account', 'credit_account_hash', '*');\" style=\"visibility:" . ( $this->current_memo['non_posting'] ? "hidden" : "visible" ) . "\" id=\"cr_showall\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>"
										) . "
										</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;\" id=\"err5{$this->popup_id}\">Memo:</td>
										<td style=\"background-color:#ffffff;padding-top:10px;\">" .
											$this->form->text_box(
    											"name=memo",
    											"size=50",
    											"value=" . stripslashes($this->current_memo['descr'])
											) . "
										</td>
									</tr>
								</table>
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>" .
		$this->form->close_form();

        if ( ! $this->memo_hash )
            $jscript[] = "setTimeout('DateInput(\'entry_date\', \'true\', \'YYYY-MM-DD\', \'".($this->current_memo['entry_date'] && $this->current_memo['entry_date'] != '0000-00-00' ? $this->current_memo['entry_date'] : date("Y-m-d"))."\', 1, \'entry_date_holder{$this->popup_id}\')', 45)";

		$this->content["jscript"] = $jscript;
		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function doit_memo_cost() {

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$memo_hash = $_POST['memo_hash'];
		$proposal_hash = $_POST['proposal_hash'];
		$commission_hash = $_POST['commission_hash'];
		$delete = $_POST['delete'];
		$from = $_POST['from'];

		$submit_btn = 'memo_btn';

		$accounting = new accounting($this->current_hash);

		if ( $delete ) {

			$feedback = "No changes have been made.";

			if ( $this->fetch_memo_cost_record($memo_hash) ) {

				if ( ! $this->current_memo['non_posting'] ) {

				    $entry_date = $this->current_memo['entry_date'];
	                if ( $accounting->period_ck( $entry_date ) )
                        return $this->__trigger_error($accounting->closed_err, E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				    if ( ! $this->db->start_transaction() )
				    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				    $accounting->start_transaction( array(
                        'proposal_hash' =>  $this->current_memo['proposal_hash']
				    ) );

                    $audit_id = $accounting->audit_id();

				    # Debit account
				    if ( ! $accounting->exec_trans($audit_id, $entry_date, $this->current_memo['debit_hash'], bcmul($this->current_memo['amount'], -1, 2), 'AD', "Memo cost deleted : {$this->current_memo['descr']}") )
				    	return $this->__trigger_error("{$accouting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				    # Credit account
				    if ( ! $accounting->fetch_account_record($this->current_memo['credit_hash']) )
				    	return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ {$this->current_memo['credit_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				    if ( $accounting->current_account['account_action'] == 'DR' )
					   $credit_amount = $this->current_memo['amount'];
				    else
					   $credit_amount = bcmul($this->current_memo['amount'], -1, 2);

				    if ( ! $accounting->exec_trans($audit_id, $entry_date, $this->current_memo['credit_hash'], $credit_amount, 'AD', "Memo Cost Deleted : {$this->current_memo['descr']}") )
				    	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				    if ( ! $accounting->end_transaction() )
                        return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				if ( ! $this->db->query("UPDATE memo_costs t1
        				                 SET
            				                 t1.timestamp = UNIX_TIMESTAMP(),
            				                 t1.deleted = 1,
            				                 t1.deleted_date = CURDATE()
        								 WHERE t1.memo_hash = '$memo_hash'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                $this->db->end_transaction();

				$feedback = "Memo cost has been removed.";

			} else
                return $this->__trigger_error("System error encountered when attempting to lookup memo cost record. Please reload window and try again. <!-- Tried fetching memo cost [ $memo_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

		} else {

			if ( $memo_hash || ( ! $memo_hash && $_POST['entry_date'] && $_POST['amount'] && ( ( ! $_POST['non_posting'] && $_POST['credit_account'] && $_POST['debit_account'] ) || $_POST['non_posting'] == 1 ) && $_POST['memo'] ) ) {

				$amount = preg_replace('/[^-.0-9]/', "", $_POST['amount']);
				$entry_date = $_POST['entry_date'];
				$credit_account = $_POST['credit_account_hash'];
				$debit_account = $_POST['debit_account_hash'];
				$memo = $_POST['memo'];
				$non_posting = $_POST['non_posting'];

				if ( ! $memo_hash && ( ! $non_posting && ( ! $credit_account || ! $debit_account ) ) ) {

                    if ( ! $credit_account ) $this->set_error("err4{$this->popup_id}");
                    if ( ! $debit_account ) $this->set_error("err3{$this->popup_id}");
					return $this->__trigger_error("We can't seem to find the account below. Please make sure you are selecting a valid account.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				if ( ! $memo_hash && bccomp($amount, 0, 2) != 1 ) {

					if ( ! $credit_account ) $this->set_error("err2{$this->popup_id}");
					return $this->__trigger_error("Please enter a valid amount for this memo cost. Amount must be greater than zero.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				if ( ! $non_posting && ! $memo_hash && $accounting->period_ck($entry_date) )
    				return $this->__trigger_error($accounting->closed_err, E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! $this->db->start_transaction() )
                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( $memo_hash ) {

					if ( ! $this->fetch_memo_cost_record($memo_hash) )
						return $this->__trigger_error("System error encountered when attempting to lookup memo cost. Please reload window and try again. <!-- Tried fetching memo [ $memo_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

					if ( stripslashes($this->current_memo['descr']) != $memo ) {

						if ( ! $this->db->query("UPDATE memo_costs t1
        										 SET t1.descr = '" . addslashes($memo) . "'
        										 WHERE t1.memo_hash = '{$this->memo_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						$feedback = "Memo cost has been saved.";

					} else
						$feedback = "No changes have been made.";

				} else {

					$memo_hash = rand_hash('memo_hash', 'memo_hash');

				    if ( ! $non_posting ) {

					    $accounting->start_transaction( array(
    					    'proposal_hash'    =>  $proposal_hash
					    ) );

					    $audit_id = $accounting->audit_id();
				    }

					if ( ! $this->db->query("INSERT INTO memo_costs
        									 VALUES
        									 (
	        									 NULL,
	        									 UNIX_TIMESTAMP(),
	        									 '$this->current_hash}',
	        									 '$memo_hash',
	        									 '$entry_date',
	        									 '$proposal_hash',
	        									 '$debit_account',
	        									 '$credit_account',
	        									 '$amount',
	        									 '$non_posting',
	        									 '" . addslashes($memo) . "',
	        									 0,
	        									 NULL
        									 )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

					if ( ! $non_posting ) {

						if ( ! $accounting->fetch_account_record($credit_account) ) # Credit side
							return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ $credit_account ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						if ( $accounting->current_account['account_action'] == 'DR' )
							$credit_amount = bcmul($amount, -1, 2);
						else
							$credit_amount = $amount;

						if ( ! $accounting->fetch_account_record($debit_account) ) # Debit side
							return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ $debit_account ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						if ( $accounting->current_account['account_action'] == 'CR' )
							$debit_amount = bcmul($amount, -1, 2);
						else
							$debit_amount = $amount;

						if ( ! $accounting->exec_trans($audit_id, $entry_date, $credit_account, $credit_amount, 'ME', $memo) )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
						if ( ! $accounting->exec_trans($audit_id, $entry_date, $debit_account, $debit_amount, 'ME', $memo) )
							return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						if ( ! $accounting->end_transaction() )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

					}

					$feedback = "Memo cost has been recorded.";
				}
				
				$this->db->query("UPDATE `commissions_paid`
						SET `paid_in_full` = 0
						WHERE `proposal_hash` = '$proposal_hash'");

				$this->db->end_transaction();

			} else {

				if ( ! $_POST['entry_date'] ) $this->set_error("err1{$this->popup_id}");
				if ( ! $_POST['amount'] ) $this->set_error("err2{$this->popup_id}");
				if ( ! $_POST['debit_account'] ) $this->set_error("err3{$this->popup_id}");
				if ( ! $_POST['credit_account'] ) $this->set_error("err4{$this->popup_id}");
				if ( ! $_POST['memo'] ) $this->set_error("err5{$this->popup_id}");

				return $this->__trigger_error("Please check that you've completed the required fields, indicated below.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
			}
		}

		$this->content['action'] = 'close';
		if ( $from == 'commission' )
			$this->content['jscript_action'] = "submit_form(\$('popup_id').form, 'reports', 'exec_post', 'refresh_form', 'action=adjust_memo_cost', 'proposal_hash=$proposal_hash', 'memo_hash=$memo_hash', 'commission_hash=$commission_hash');";
		else
			$this->content['jscript_action'] = "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'memo_costing', 'proposal_hash=$proposal_hash', 'p=$p', 'order=$order', 'order_dir=$order_dir');";

		$this->content['page_feedback'] = $feedback;

		return;
	}

	function memo_costing($local=NULL) {

		$proposal_hash = $this->ajax_vars['proposal_hash'];
		if (!$this->proposal_hash) {
			$this->proposal_hash = $this->ajax_vars['proposal_hash'];
			$proposals = new proposals($this->current_hash);
			$proposals->fetch_master_record($this->proposal_hash);
		}

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		$this->fetch_memo_costs();
		$expense_dist = $this->fetch_expense_distributions($this->proposal_hash);

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"5\">".($this->total_memo_costs ? "
								Showing 1 - ".$this->total_memo_costs." of ".$this->total_memo_costs." Memo Costs." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->p->ck(get_class($this), 'A', 'memo_costing') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_memo_cost', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/plus.gif\" title=\"New Memo Cost\" border=\"0\" /></a>" : NULL)."
									&nbsp;
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"vertical-align:bottom;padding-left:15px;width:15%\" nowrap>Entry Date</td>
							<td style=\"vertical-align:bottom;width:40%\" nowrap>Description</td>
							<td style=\"vertical-align:bottom;width:20%;\">Debit Account</td>
							<td style=\"vertical-align:bottom;width:20%;\">Credit Account</td>
							<td style=\"width:150px;vertical-align:bottom;text-align:right;padding-right:10px;\">Amount</td>
						</tr>";

						for ($i = 0; $i < $this->total_memo_costs; $i++) {
							if ($this->p->ck(get_class($this), 'V', 'memo_costing'))
								$onClick = "onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_memo_cost', 'memo_hash=".$this->memo_cost_items[$i]['memo_hash']."', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item')\"";

							$tbl .= "
							<tr ".($onClick ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">
								<td style=\"vertical-align:bottom;padding-left:15px;text-align:left;border-bottom:1px solid #cccccc;\" $onClick>".date(DATE_FORMAT, strtotime($this->memo_cost_items[$i]['entry_date']))."</td>
								<td style=\"vertical-align:bottom;text-align:left;border-bottom:1px solid #cccccc;\" $onClick>".$this->memo_cost_items[$i]['descr']."</td>".($this->memo_cost_items[$i]['non_posting'] == 0 ? "
								    <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" $onClick nowrap>".($this->memo_cost_items[$i]['debit_account'] ?
									   $this->memo_cost_items[$i]['debit_account'] : "SPLIT")."</td>
								    <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" $onClick nowrap>".($this->memo_cost_items[$i]['credit_account'] ?
									   $this->memo_cost_items[$i]['credit_account'] : "SPLIT")."</td>" : "
									<td colspan=\"2\" style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\">Non-Posting</td>")."
								<td style=\"vertical-align:bottom;padding-right:10px;border-bottom:1px solid #cccccc;\" class=\"num_field\" $onClick>$".number_format($this->memo_cost_items[$i]['amount'], 2)."</td>
							</tr>";
						}

						for ($i = 0; $i < count($expense_dist); $i++) {
							if ($this->p->ck(get_class($this), 'V'))
                                $onClick = "onClick = \"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash=".$expense_dist[$i]['invoice_hash']."', 'popup_id=invoice_win', 'from_class=proposals', 'from_section=memo_cost', 'proposal_hash=".$this->proposal_hash."');\"";

                            $tbl .= "
                            <tr ".($onClick ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">
                                <td style=\"vertical-align:bottom;padding-left:15px;text-align:left;border-bottom:1px solid #cccccc;\" $onClick>".$expense_dist[$i]['invoice_date']."</td>
                                <td style=\"vertical-align:bottom;text-align:left;border-bottom:1px solid #cccccc;\" $onClick>
                                    Vendor Payable: ".($expense_dist[$i]['memo'] ?
                                        (strlen($expense_dist[$i]['memo']) > 60 ?
	                                        substr($expense_dist[$i]['memo'], 0, 57)."..." : $expense_dist[$i]['memo']) : stripslashes($expense_dist[$i]['vendor_name']))."
                                </td>
                                <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" $onClick nowrap>".$expense_dist[$i]['debit_account']."</td>
                                <td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" $onClick nowrap>".$expense_dist[$i]['credit_account']."</td>
                                <td style=\"vertical-align:bottom;padding-right:10px;border-bottom:1px solid #cccccc;\" class=\"num_field\" $onClick>$".number_format($expense_dist[$i]['amount'], 2)."</td>
                            </tr>";
						}

						if (!$this->total_memo_costs && !$expense_dist)
							$tbl .= "
							<tr >
								<td colspan=\"5\">There are no memo or job costs listed against this proposal.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		if ($local)
			return $tbl;
		else
			$this->content['html']['tcontent7_2'] = $tbl;

		return $tbl;
	}
	/*
	function proposal_payables($local=NULL) {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		if (!$this->proposal_hash) {
			$this->proposal_hash = $this->ajax_vars['proposal_hash'];
			$proposals = new proposals($this->current_hash);
			$proposals->fetch_master_record($this->proposal_hash, 1);
		}
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ($limit = $this->ajax_vars['limit'])
			$pagnate = $limit;
		else
			$pagnate = MAIN_PAGNATION_NUM;

		if ($proposal_hash && !$this->proposal_hash)
			$valid = $this->fetch_master_record($proposal_hash, 1);

		$order_by_ops = array("timestamp"			=>	"vendor_payables.timestamp",
							  "vendor"				=>	"vendors.vendor_name",
							  "po_no"				=>	"purchase_order.po_no",
							  "invoice_no"			=>	"vendor_payables.invoice_no",
							  "invoice_date"		=>	"vendor_payables.invoice_date",
							  "due_date"			=>	"vendor_payables.due_date", );

		$this->fetch_proposal_payables($this->proposal_hash, $order_by_ops[($order ? $order : $order_by_ops['timestamp'])], $order_dir);

		if (!$this->po)
			$this->po = new purchase_order($this->proposal_hash);

	}*/

	function showall() {

		$this->unlock("_");
		$this->load_class_navigation();

		$this->db->define('convert_currency', 1); # Define the database variable to do on the fly currency conversion

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

		$total_before = $this->total;
		$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
		$p = ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ( $p - 1 );

		$sys = new system_config($this->current_hash);
		$cur_sym = $sys->fetch_currency_symbols();

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ( $end > $this->total )
			$end = $this->total;

		$order_by_ops = array(
			"timestamp"			=>	"vendor_payables.timestamp",
			"vendor"			=>	"vendors.vendor_name",
			"po_no"				=>	"purchase_order.po_no",
			"invoice_no"		=>	"vendor_payables.invoice_no",
			"invoice_date"		=>	"vendor_payables.invoice_date",
			"due_date"			=>	"vendor_payables.due_date"
		);

		$_total = $this->fetch_payables(
			$start_from,
			$end,
			$order_by_ops[ ( isset($order) && $order_by_ops[$order] ? $order : "invoice_date" ) ],
			$order_dir
		);

		if ( $this->active_search )
			$this->page_pref =& $this->search_vars;

		if ( $total_before != $this->total ) {

			$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
			$p = ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p;
			$start_from = MAIN_PAGNATION_NUM * ( $p - 1 );

			$end = $start_from + MAIN_PAGNATION_NUM;
			if ( $end > $this->total )
				$end = $this->total;
		}

		$customer_invoice = new customer_invoice($this->current_hash); # For customer refund invoice types

		$users = new system_config($this->current_hash);
		$users->fetch_users();

		$user_name = array('All Users');
		$user_hash = array('');

		for ( $i = 0; $i < count($users->user_info); $i++ ) {

			array_push($user_name, stripslashes($users->user_info[$i]['full_name']) );
			array_push($user_hash, $users->user_info[$i]['user_hash']);
		}

		unset($users);

		$sort_menu = "
		<div id=\"sort_menu\" class=\"search_suggest\" >
			<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;margin-top:0;\">
				<div style=\"float:right;padding-right:1px;\">
					<a href=\"javascript:void(0);\" onClick=\"toggle_display('sort_menu', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
				</div>
				Sort Options
			</div>
			<div class=\"function_menu_item\" style=\"padding-left:10px;\">
				<small><strong>Show:</strong></small>
				<div style=\"margin-left:10px;padding:5px 0;\">
					<div style=\"padding-bottom:3px;\">" .
					$this->form->radio(
						"name=sort",
						"value=*",
						"id=show",
						( ! $this->page_pref['show'] || $this->page_pref['show'] == '*' ? "checked" : NULL )
					) . " All Payables
					</div>
					<div style=\"padding-bottom:3px;\">" .
					$this->form->radio(
						"name=sort",
						"value=1",
						"id=show",
						( $this->page_pref['show'] == 1 ? "checked" : NULL )
					) . " Only Paid Payables
					</div>
					<div style=\"padding-bottom:3px;\">" .
					$this->form->radio(
						"name=sort",
						"value=2",
						"id=show",
						( $this->page_pref['show'] == 2 ? "checked" : NULL )
					) . " Only Flagged Payables
					</div>
					<div style=\"padding-bottom:3px;\">" .
					$this->form->radio(
						"name=sort",
						"value=3",
						"id=show",
						( $this->page_pref['show'] == 3 ? "checked" : NULL )
					) . " Only Unpaid Payables
					</div>
				</div>
				<small><strong>By Type:</strong></small>
				<div style=\"margin-left:10px;padding:5px 0;\">
					<div style=\"padding-bottom:3px;\">".$this->form->radio("name=type", "value=*", "id=type", (!$this->page_pref['type'] || $this->page_pref['type'] == '*' ? "checked" : NULL))." Show All Types</div>
					<div style=\"padding-bottom:3px;\">".$this->form->radio("name=type", "value=I", "id=show", ($this->page_pref['type'] == 'I' ? "checked" : NULL))." Only Show Bills</div>
					<div style=\"padding-bottom:3px;\">".$this->form->radio("name=type", "value=C", "id=show", ($this->page_pref['type'] == 'C' ? "checked" : NULL))." Only Show Credits</div>
					<div style=\"padding-bottom:3px;\">".$this->form->radio("name=type", "value=R", "id=show", ($this->page_pref['type'] == 'R' ? "checked" : NULL))." Only Show Refunds</div>
					<div style=\"padding-bottom:3px;\">".$this->form->radio("name=type", "value=D", "id=show", ($this->page_pref['type'] == 'D' ? "checked" : NULL))." Only Show Deposits</div>				</div>
				<div >
					<small><strong>With their due dates from:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\" id=\"sort_from_date_holder\"></div>
				</div>
				<div >
					<small><strong>To:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\" id=\"sort_to_date_holder\"></div>
				</div>
				<div >
					<small><strong>Created By:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\">" .
						$this->form->select(
							"created_by[]",
							$user_name,
							( is_array($this->page_pref['created_by']) ? $this->page_pref['created_by'] : array($this->page_pref['created_by']) ),
							$user_hash,
							"multiple",
							"size=4",
							"blank=1",
							"style=width:160px;"
						) . "
					</div>
				</div>
			</div>
			<div class=\"function_menu_item\">
				<div style=\"float:right;padding-right:5px;\">" .
				$this->form->button(
					"value=Go",
					"onClick=submit_form(this.form, 'payables', 'exec_post', 'refresh_form', 'action=doit_search');"
				) . "
				</div>" .
				$this->form->checkbox(
					"name=page_pref",
					"value=1",
					( $this->page_pref['custom'] ? "checked" : NULL )
				) . "&nbsp;&nbsp;<small><i>Remember Preferences</i></small>
			</div>
		</div>";

		$jscript = array(
			"setTimeout('DateInput(\'sort_from_date\', false, \'YYYY-MM-DD\', \'" . ( $this->page_pref['sort_from_date'] ? $this->page_pref['sort_from_date'] : '' ) . "\', 1, \'sort_from_date_holder\')', 500);",
			"setTimeout('DateInput(\'sort_to_date\', false, \'YYYY-MM-DD\', \'".($this->page_pref['sort_to_date'] ? $this->page_pref['sort_to_date'] : '')."\', 1, \'sort_to_date_holder\')', 700);"
		);

		array_push($jscript,
			"var flag_timer = false;
	        var flag_keys = new Hash();
	        var private_flag_keys = new Hash();
	        flagInvoice = function() {
	            var invoice_hash = arguments[0];
	            var flag = arguments[1];

	            flag_keys.set(invoice_hash, (flag ? 1 : 0));
	            if (flag) {
		            \$('flag_1_'+invoice_hash).show();
		            \$('flag_2_'+invoice_hash).hide();
	            } else {
	                \$('flag_2_'+invoice_hash).show();
	                \$('flag_1_'+invoice_hash).hide();
	            }
	            if (flag_timer == false)
	                flag_timer = window.setTimeout('flagPost();', 250);

	    	}
	    	flagPost = function() {
	        	private_flag_keys = flag_keys.clone();
	            flag_keys = new Hash();

	            agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'toggle_pay_flag', private_flag_keys.toQueryString());

	            flag_timer = false;
	    	}"
		);

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
			'p'				=> $p,
			'order'			=> $order,
			'order_dir'		=> $order_dir,
			'active_search' => $this->active_search
		) ) .
		( $this->active_search ?
			"<h3 style=\"color:#00477f;margin:0;padding-left:10px;\">Search Results</h3>
			<div style=\"padding-top:8px;padding-bottom:10px;padding-left:30px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall');\">Show All</a>]</div>" : NULL
		) . "
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"padding-left:10px;padding-top:0\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:bottom;\" colspan=\"8\">
								<div style=\"float:right;font-weight:normal;padding-right:10px;text-align:right;\">" .
									( $this->total ?
										paginate_jscript(
											$num_pages,
											$p,
											'sf_loadcontent',
											'cf_loadcontent',
											'payables',
											'showall',
											$order,
											$order_dir,
											"'active_search={$this->active_search}'"
										) : NULL
									) . "
									<div style=\"padding-top:5px;\">
										<a href=\"javascript:void(0);\" onMouseOver=\"position_element(\$('sort_menu'), findPos(this, 'top')+15, findPos(this, 'left')-100);\" onClick=\"toggle_display('sort_menu', 'block');\"><img src=\"images/arrow_down2.gif\" id=\"sort_arrow_down\" border=\"0\" title=\"Change sorting options\" /></a>
										&nbsp;
										<span style=\"cursor:hand;\" onMouseOver=\"position_element(\$('sort_menu'), findPos(\$('sort_arrow_down'), 'top')+15, findPos(\$('sort_arrow_down'), 'left')-100);\" onClick=\"toggle_display('sort_menu', 'block');\" title=\"Change sorting options\"><small>Sort Options</small></span>
										$sort_menu
									</div>
								</div>" .
								( $this->total ?
									"Showing " . ( $start_from + 1 ) . " - " .
									( $start_from + MAIN_PAGNATION_NUM > $this->total ?
										$this->total : $start_from + MAIN_PAGNATION_NUM
									) . " of " . ( $this->total - $this->invoice_info_refund ) . " Vendor Invoices" .
									( $this->invoice_info_refund > 0 ?
										" and {$this->invoice_info_refund} Customer Refund" .
										( $this->invoice_info_refund > 1 ?
											"s" : NULL
										) : NULL
									) . "." : NULL
								) . "
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
									( $this->p->ck(get_class($this), 'A') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/new_invoice.gif\" title=\"Receive a new vendor invoice\" border=\"0\" /></a>&nbsp;&nbsp;
										<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_type=R', 'popup_id=line_item');\"><img src=\"images/new_refund.gif\" title=\"Create a new customer refund\" border=\"0\" /></a>&nbsp;&nbsp;" : NULL
									) .
									( $this->p->ck(get_class($this), 'PB') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'paybills', 'force_show=2', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/pay_bills.gif\" title=\"Make payments\" border=\"0\" /></a>&nbsp;&nbsp;" : NULL
									) .
									( $this->total_queue && $this->p->ck(get_class($this), 'P') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'empty_queue', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/empty_queue.gif\" title=\"You have checks waiting to be printed\" border=\"0\" /></a>&nbsp;&nbsp;" : NULL
									) . "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'empty_queue', 'preview=3', 'popup_id=reprint_win', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/reprint_multiple_checks.gif\" title=\"Reprint Checks\" border=\"0\" /></a>
									&nbsp;&nbsp;
									<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'search_payables', 'popup_id=search_win', 'active_search={$this->active_search}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/search.gif\" title=\"Search for payables\" border=\"0\" /></a>
									&nbsp;&nbsp;
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"width:20px;\">" .
								$this->form->checkbox(
									"name=checkallflag",
									"onClick=checkall(document.getElementsByName('flag[]'), this.checked);"
								) . "
							</td>
							<td style=\"width:200px;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=vendor', 'order_dir=".($order == "vendor" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">Vendor " . ( $this->invoice_info_refund ? "/ Payee" : NULL ) . "</a>" .
								( $order == 'vendor' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								)."
							</td>
							<td style=\"width:110px;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=po_no', 'order_dir=".($order == "po_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">PO No.</a>" .
								( $order == 'po_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								)."
							</td>
							<td style=\"width:110px;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=invoice_no', 'order_dir=".($order == "invoice_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">Invoice No.</a>" .
								( $order == 'invoice_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								)."
							</td>
							<td style=\"width:110px;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=invoice_date', 'order_dir=".($order == "invoice_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">Invoice Date</a>" .
								( $order == 'invoice_date' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								)."
							</td>
							<td style=\"width:110px;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=due_date', 'order_dir=".($order == "due_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">Due Date</a>" .
								( $order == 'due_date' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								)."
							</td>
							<td style=\"vertical-align:bottom;\" class=\"num_field\">Amount</td>
							<td style=\"vertical-align:bottom;\" class=\"num_field\">Balance</td>
						</tr>";

						$border = "border-bottom:1px solid #cccccc;";
						for ( $i = 0; $i < $_total; $i++ ) {

							if ( $i >= $_total - 1 )
								unset($border);

							if ( $this->invoice_info[$i]['type'] == 'R' )
								$customer_invoice->fetch_invoice_record($this->invoice_info[$i]['po_hash']);
							if ( $this->p->ck(get_class($this), 'V') )
								$onClick = "onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash=".$this->invoice_info[$i]['invoice_hash']."', 'popup_id=line_item', 'new=true', 'active_search={$this->active_search}', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"";

							if ($this->invoice_info[$i]['due_date'] != $this->invoice_info[$i]['orig_due_date'] && strtotime($this->invoice_info[$i]['due_date']) >= strtotime(date("Y-m-d")))
								$days_due = round((strtotime(date("Y-m-d")) - strtotime($this->invoice_info[$i]['due_date'])) / 86400);
							else
								$days_due = round((strtotime(date("Y-m-d")) - strtotime($this->invoice_info[$i]['orig_due_date'])) / 86400);

							$b++;
							$tbl .= "
							<tr " . ( $this->p->ck(get_class($this), 'V') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
								<td style=\"cursor:auto;$border\">" .
								( bccomp($this->invoice_info[$i]['balance'], 0, 2) != 1 ?
									"<img src=\"images/paid.gif\" title=\"This invoice has been paid in full\" />" :
									( ! $this->invoice_info[$i]['hold'] && $this->invoice_info[$i]['type'] != 'C' ?
										"<div id=\"flag_1_{$this->invoice_info[$i]['invoice_hash']}\" style=\"display:" . ( $this->invoice_info[$i]['pay_flag'] ? "block" : "none" ) . "\">
	    									<img src=\"images/flag.gif\" border=\"0\" " . ( $this->p->ck(get_class($this), 'F') ? "title=\"Click here to remove payment flag.\" onMouseOver=\"this.style.cursor='pointer';\" onClick=\"flagInvoice('{$this->invoice_info[$i]['invoice_hash']}', 0);\"" : NULL ) . " />
                                        </div>
                                        <div id=\"flag_2_{$this->invoice_info[$i]['invoice_hash']}\" style=\"display:" . ( $this->invoice_info[$i]['pay_flag'] ? "none" : "block" ) . "\">" .
											$this->form->checkbox(
												"name=flag[]",
											    "value=1",
											    "title=Check to flag this invoice for payment.",
											    "onClick=if(!this.disabled){flagInvoice('{$this->invoice_info[$i]['invoice_hash']}', this.checked);this.checked=0;}",
                        						( ! $this->p->ck(get_class($this), 'F') || $this->invoice_info[$i]['hold'] ? "disabled" : NULL )
                        					) . "
                                        </div>" : "&nbsp;"
                        			)
                        		) . "
								</td>
								<td style=\"vertical-align:bottom;{$border}text-align:left;" . ( $this->invoice_info[$i]['hold'] ? "color:#ff0000;" : NULL ) . "\" " . ( $this->invoice_info[$i]['hold'] ? "title=\"This invoice is on payment hold.\"" : NULL ) . " nowrap $onClick " . ( $this->invoice_info[$i]['type'] == 'R' ? "colspan=\"4\"" : NULL ) . ">" .
                        		( $this->invoice_info[$i]['type'] == 'R' ?
									"Refund - " . stripslashes($customer_invoice->current_invoice['customer_name']) : stripslashes($this->invoice_info[$i]['vendor_name'])
                        		) . "
								</td>" .
                        		( $this->invoice_info[$i]['type'] != 'R' ?
									"<td style=\"vertical-align:bottom;{$border}text-align:left;\" " . ( ! $this->invoice_info[$i]['po_no'] ? $onClick : NULL ) . ">" .
	                        		( $this->invoice_info[$i]['type'] == 'C' ?
										"<i>Vendor Credit</i>" :
		                        		( $this->invoice_info[$i]['po_no'] ?
			                        		( $this->p->ck('purchase_order', 'V') ?
												"<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'edit_po', 'proposal_hash={$this->invoice_info[$i]['proposal_hash']}', 'po_hash={$this->invoice_info[$i]['po_hash']}', 'popup_id=line_item');\" title=\"View this purchase order\" class=\"link_standard\">" : NULL
			                        		) .
											( $this->invoice_info[$i]['type'] == 'D' ?
												"<i>{$this->invoice_info[$i]['po_no']}</i>" : $this->invoice_info[$i]['po_no']
											) .
											( $this->p->ck('purchase_order', 'V') ?
												"</a>" : NULL
											) : NULL
										)
									) . "&nbsp;
									</td>
									<td style=\"vertical-align:bottom;{$border}text-align:left;\" $onClick>" .
									( $this->invoice_info[$i]['type'] == 'D' ?
										"<i>Deposit</i>" : stripslashes($this->invoice_info[$i]['invoice_no'])
									) . "&nbsp;
									</td>
									<td style=\"vertical-align:bottom;$border\" $onClick>" . date(DATE_FORMAT, strtotime($this->invoice_info[$i]['invoice_date'])) . "</td>" : NULL
								) . "
								<td style=\"vertical-align:bottom;$border" .
								( bccomp($this->invoice_info[$i]['balance'], 0, 2) != -1 && bccomp($this->invoice_info[$i]['amount'], 0, 2) != -1 && $days_due > 0 ?
									"color:#ff0000;" :
									( bccomp($this->invoice_info[$i]['balance'], 0, 2) != -1 && $days_due > -10 && $days_due <= 0 ?
										"color:#FFD65C;" : NULL
									)
								) . "\" $onClick>" .
								( $this->invoice_info[$i]['type'] != 'C' ?
									( $this->invoice_info[$i]['due_date'] != $this->invoice_info[$i]['orig_due_date'] && strtotime($this->invoice_info[$i]['due_date']) >= strtotime( date("Y-m-d") ) ?
										date(DATE_FORMAT, strtotime($this->invoice_info[$i]['due_date'])) : date(DATE_FORMAT, strtotime($this->invoice_info[$i]['orig_due_date']))
									) .
									( $this->invoice_info[$i]['due_date'] != $this->invoice_info[$i]['orig_due_date'] && strtotime($this->invoice_info[$i]['due_date']) >= strtotime( date("Y-m-d") ) ?
										"<span style=\"padding-left:3px;\" title=\"Early payment discount:\nMust be paid by " . date(DATE_FORMAT, strtotime($this->invoice_info[$i]['due_date'])) . " to receive " . (float)bcmul($this->invoice_info[$i]['payment_discount'], 100, 4) . "% discount\">*</span>" : NULL
									) : NULL
								) . "&nbsp;
								</td>
								<td style=\"vertical-align:bottom;$border\" class=\"num_field\" $onClick>{$this->invoice_info[$i]['symbol']}" . number_format($this->invoice_info[$i]['amount'], 2) . "</td>
								<td style=\"vertical-align:bottom;$border\" class=\"num_field\" $onClick>" .
								( $this->invoice_info[$i]['balance'] < 0 ?
								    "(" . $this->invoice_info[$i]['symbol'] . number_format( bcmul($this->invoice_info[$i]['balance'], -1, 2), 2) . ")" : $this->invoice_info[$i]['symbol'] . number_format($this->invoice_info[$i]['balance'], 2)
								) . "
								</td>
							</tr>";
						}

						if ( ! $this->total )
							$tbl .= "
							<tr >
								<td colspan=\"8\" class=\"smallfont\">You have no vendor invoices to display.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>" .
        $this->form->close_form();

		$this->content['jscript'] = $jscript;
		$this->content['html']['place_holder'] = $tbl;
	}

	function search_payables() {
		$date = date("U") - 3600;
		$result = $this->db->query("DELETE FROM `search`
							  		WHERE `timestamp` <= '$date'");

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Search Payables";

		$this->content['focus'] = "invoice_no";

		list($search_name, $search_hash) = $this->fetch_searches('payables');

		if ($this->ajax_vars['active_search']) {
			$result = $this->db->query("SELECT `search_str`
										FROM `search`
								  		WHERE `search_hash` = '".$this->ajax_vars['active_search']."'");
			$row = $this->db->fetch_assoc($result);
			$search_vars = permissions::load_search_vars($row['search_str']);
		}
		
		unset($search_vars['vendor_filter']);

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array('popup_id' 		=> $this->content['popup_controls']['popup_id'],
								  'detail_search' 	=> 1,
								  'c_action'		=> 'close'
								  ))."

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
							Filter your payables search criteria below:
						</div>
						<table>
							<tr>
								<td style=\"padding-left:15px;\">
									<fieldset>
										<legend>Invoice Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=invoice_no",
																	"size=25",
																	"maxlength=255",
								  									($search_vars ? "onFocus=this.select()" : NULL),
								  								    "value=".$search_vars['invoice_no'])."
								  		</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;\">
									<fieldset>
										<legend>DealerChoice PO Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=po_no", "value=".$search_vars['po_no'], "size=35", ($search_vars ? "onFocus=this.select()" : NULL), "maxlength=255")."</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Invoice Due Date</legend>
										<div style=\"padding:5px 5px 0 5px;\" id=\"due_from_date{$this->popup_id}\"></div>
										<div style=\"padding:5px;padding-bottom:5px\" id=\"due_to_date{$this->popup_id}\"></div>
									</fieldset>
								</td>";
								$this->content['jscript'][] = "setTimeout('DateInput(\'due_from_date\', false, \'YYYY-MM-DD\', \'".($search_vars['due_from_date'] ? $search_vars['due_from_date'] : NULL)."\', 1, \'due_from_date{$this->popup_id}\', \'From: \')', 350);";
								$this->content['jscript'][] = "setTimeout('DateInput(\'due_to_date\', false, \'YYYY-MM-DD\', \'".($search_vars['due_to_date'] ? $search_vars['due_to_date'] : NULL)."\', 1, \'due_to_date{$this->popup_id}\', \'&nbsp;&nbsp;&nbsp;&nbsp;To: \')', 550);";
								$tbl .= "
								<td style=\"padding-left:15px;vertical-align:top;\" rowspan=\"2\">
									<fieldset>
										<legend>Search By Vendor</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=vendor",
																	"value=",
																	"autocomplete=off",
																	"size=35",
																	"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('reports', 'vendor', 'vendor_hash', 1);}",
								                                    "onKeyUp=if(ka==false && this.value){key_call('reports', 'vendor', 'vendor_hash', 1);}",
																	"onBlur=key_clear();"
																	)."


											<div style=\"margin-top:15px;width:250px;height:75px;overflow:auto;display:none;background-color:#efefef;border:1px outset #cccccc;\" id=\"vendor_filter_holder\">";
											if (is_array($search_vars['vendor_filter']) || $search_vars['vendor_filter']) {
												if (!is_array($search_vars['vendor_filter']))
													$search_vars['vendor_filter'] = array($search_vars['vendor_filter']);
												for ($i = 0; $i < count($search_vars['vendor_filter']); $i++)
													$tbl .= "<div id=\"vendor_".$search_vars['vendor_filter'][$i]."\" style=\"padding:5px 2px 0 5px;\">[<small><a href=\"javascript:void(0);\" onClick=\"remove_el(this.parentElement.parentElement)\" class=\"link_standard\" title=\"Remove from filter\">x</a></small>]&nbsp;".addslashes((strlen(reports::customer_name($search_vars['vendor_filter'][$i])) > 25 ? substr(reports::customer_name($search_vars['vendor_filter'][$i]), 0, 22)."..." : reports::customer_name($search_vars['vendor_filter'][$i])))."<input type=\"hidden\" id=\"vendor_filter\" name=\"vendor_filter[]\" value=\"".$search_vars['vendor_filter'][$i]."\" /></div>";
											}
											$tbl .= "
											</div>
										</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Paid & Unpaid</legend>
										<div style=\"padding:5px;padding-bottom:5px\">" .
											$this->form->select(
    											"paid_unpaid",
    											array("Payables with an open balance", "Payables with a zero balance"),
    											$search_vars['paid_unpaid'],
    											array(2, 1)
                                            ) . "
                                        </div>
									</fieldset>
								</td>
							</tr>
							<tr>
                                <td style=\"padding-left:15px;vertical-align:top;\">
                                    <fieldset>
                                        <legend>Check No</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">" .
                                            $this->form->text_box(
                                                "name=check_no",
                                                "value={$search_vars['check_no']}",
                                                ($search_vars ? "onFocus=this.select()" : NULL),
                                                "maxlength=24"
                                            ) . "
                                        </div>
                                    </fieldset>
                                </td>
                                <td></td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<div style=\"float:right;text-align:right;padding-top:15px;margin-right:25px;\">
				".$this->form->button("value=Search", "id=primary", "onClick=submit_form($('popup_id').form, 'payables', 'exec_post', 'refresh_form', 'action=doit_search');")."
			</div>
			<div style=\"margin:15px;\">
				<table>
					<tr>
						<td>
							".$this->form->checkbox("name=save", "value=1", "onClick=if(this.checked){toggle_display('save_search_box', 'block')}else{toggle_display('save_search_box', 'none')}")."&nbsp;
							Save Search?
						</td>
						<td>".($search_hash ? "
							<div style=\"margin-left:15px;\">
								".$this->form->select("saved_searches", $search_name, '', $search_hash, "style=width:100px", "onChange=if(this.options[this.selectedIndex].value != ''){toggle_display('search_go_btn', 'block');}else{toggle_display('search_go_btn', 'none');}")."&nbsp;
								".$this->form->button("value=Go", "onClick=agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'active_search='+\$F('saved_searches'));window.setTimeout('popup_windows[\'".$this->content['popup_controls']['popup_id']."\'].hide()', 1000);")."
								&nbsp;
								".$this->form->button("value=&nbsp;X&nbsp;", "title=Delete this saved search", "onClick=if(\$('saved_searches') && \$('saved_searches').options[\$('saved_searches').selectedIndex].value){if(confirm('Do you really want to delete your saved search?')){agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'purge_search', 's='+\$('saved_searches').options[\$('saved_searches').selectedIndex].value);\$('saved_searches').remove(\$('saved_searches').selectedIndex);}}")."
							</div>" : NULL)."
						</td>
					</tr>
					<tr>
						<td colspan=\"2\">
							<div style=\"padding-top:5px;display:none;margin-left:5px;\" id=\"save_search_box\">
								Name Your Saved Search:
								<div style=\"margin-top:5px;\">
									".$this->form->text_box("name=search_name", "value=", "size=35", "maxlenth=64")."
								</div>
							</div>
						</td>
					</tr>
				</table>
			</div>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}

	function fetch_vendor_credit_applied($payment_hash) {

		$total = 0;
		$credit = array();

		$r = $this->db->query("SELECT
                            	   t1.obj_id,
                            	   t1.invoice_hash,
                            	   t2.type,
                                   IF( ISNULL(t2.currency) OR ISNULL(@convert_currency), t1.amount, ROUND( currency_exchange(t1.amount, t2.exchange_rate, 0), 2) ) AS amount,
                                   IF( ISNULL(t2.currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(t2.currency) ) AS symbol
							   FROM vendor_credit_applied t1
							   LEFT JOIN vendor_payables t2 ON t2.invoice_hash = t1.invoice_hash
							   WHERE t1.payment_hash = '$payment_hash' AND t1.void != 1");

		while ( $row = $this->db->fetch_assoc($r) ) {

			$credit[] = $row;
			$total = bcadd($total, $row['amount'], 2);
		}

		return array($credit, $total);
	}

	function item_list() {
		$args = func_get_args();
		if ($args) {
			$invoice_hash = $args[0];
			$po_hash = $args[1];
			$proposal_hash = $args[2];
			$popup_id = $args[3];
		} else {
            $invoice_hash = $this->ajax_vars['invoice_hash'];
            $po_hash = $this->ajax_vars['po_hash'];
            $proposal_hash = $this->ajax_vars['proposal_hash'];
            $popup_id = $this->ajax_vars['popup_id'];
		}

		if (!$this->invoice_hash) {
			if ($invoice_hash && $this->fetch_invoice_record($invoice_hash) && count($this->current_invoice['ap_invoice_item']))
	            $ap_item =& $this->current_invoice['ap_invoice_item'];
		} else
            $ap_item =& $this->current_invoice['ap_invoice_item'];

		$po = new purchase_order($proposal_hash);
		if ($po->fetch_po_record($proposal_hash, $po_hash)) {
			$total_items = $po->current_po['total_items'] + $po->current_po['total_work_order_items'];
			$lines = new line_items($proposal_hash, $po->current_po['punch']);
			$pm = new pm_module($proposal_hash);

			for ($i = 0; $i < $total_items; $i++) {
				unset($disabled);
	            if ($valid = $lines->fetch_line_item_record($po->current_po['line_items'][$i])) {
	                $work_order = false;
	                $qty = $lines->current_line['qty'];
	                $ext_cost = $lines->current_line['ext_cost'];
	                if ($po->current_po['currency'] && $po->current_po['exchange_rate'])
                        $ext_cost = currency_exchange($ext_cost, $po->current_po['exchange_rate']);

	                $item_product = $lines->current_line['item_product'];
	                $item_descr = stripslashes($lines->current_line['item_descr']);
	                $item_no = $lines->current_line['item_no'];
	                $line_no = $lines->current_line['po_line_no'];
	                $ap_invoice_hash = $lines->current_line['ap_invoice_hash'];
	                if (defined('AP_LINE_RECEIVE') && !$lines->current_line['receive_date'])
                        $disabled = true;

	                $receive_date = $lines->current_line['receive_date'];
	                if ($lines->current_line['invoice_hash'])
	                    $invoiced_line = true;

	            } else {
	                $pm->fetch_work_order_item($po->current_po['line_items'][$i]);
	                $work_order = true;
	                $qty = $pm->current_item['time'].($pm->current_item['units'] != 'F' ? " ".$pm->units[$pm->current_item['units']].($pm->current_item['time'] > 1 ? "s" : NULL) : NULL);
	                $ext_cost = $pm->current_item['ext_true_cost'];
                    if ($po->current_po['currency'] && $po->current_po['exchange_rate'])
                        $ext_cost = currency_exchange($ext_cost, $po->current_po['exchange_rate']);

	                $item_product = $pm->current_item['item_product'];
	                $item_descr = stripslashes($pm->current_item['item_descr']);
	                $item_no = $pm->current_item['item_no'];
	                $line_no = $pm->current_item['po_line_no'];
	                $ap_invoice_hash = $pm->current_line['ap_invoice_hash'];
	                if ($pm->current_item['line_item_invoice_hash'])
	                    $invoiced_line = true;
	            }

                if (!$ap_invoice_hash || ($ap_invoice_hash && $ap_invoice_hash == $invoice_hash)) {
                	$item_exists = true;
		            $item_tbl .= "
		            <tr>
	                    <td style=\"width:22px;background-color:#ffffff;border-top:1px solid #cccccc;border-right:1px solid #cccccc;\">
	                        ".$this->form->checkbox("name=ap_invoice_item[]",
	                                                "value=".$po->current_po['line_items'][$i],
		                                            "i_cost=".(bccomp($ext_cost, 0, 4) ? $ext_cost : 0),
		                                            ($disabled ? "disabled title=Line has not yet been received and cannot be mapped to payable" : NULL),
		                                            "onClick=tally_totals();".($this->invoice_hash ? "\$('changed').value=1;" : NULL),
		                                            (isset($ap_item) && in_array($po->current_po['line_items'][$i], $ap_item) && !$disabled ? "checked" : NULL)).($work_order ?
		                       $this->form->hidden(array('item_w['.$po->current_po['line_items'][$i].']' => 1)) : NULL)."
	                    </td>
	                    <td style=\"background-color:#ffffff;border-top:1px solid #cccccc;border-right:1px solid #cccccc;text-align:right;\">".$qty."</td>
	                    <td style=\"background-color:#ffffff;border-top:1px solid #cccccc;border-right:1px solid #cccccc;\">
	                        ".$item_no."&nbsp;
	                    </td>
	                    <td style=\"background-color:#ffffff;border-top:1px solid #cccccc;border-right:1px solid #cccccc;\" ".(strlen($item_descr) > 30 ? "title=\"".htmlentities($item_descr, ENT_QUOTES)."\"" : NULL).">".(strlen($item_descr) > 30 ?
	                        substr($item_descr, 0, 27)."..." : $item_descr)."&nbsp;
	                    </td>
	                    <td style=\"background-color:#ffffff;border-top:1px solid #cccccc;border-right:1px solid #cccccc;text-align:right;\">".(bccomp($ext_cost, 0) == -1 ?
	                        "($".number_format($ext_cost * -1, 2).")" : "$".number_format($ext_cost, 2))."
	                    </td>
		            </tr>";
                }
			}
			if (!$item_exists)
                $item_tbl .= "<tr><td style=\"background-color:#ffffff;border-top:1px solid #cccccc;\" colspan=\"5\">No available line items.</td></tr>";

		} else
            $item_tbl = "<tr><td style=\"background-color:#ffffff;\" colspan=\"5\">Unable to fetch purchase order.</td>";


        $tbl = "
        <div style=\"width:600px;".($total_items > 4 ? "height:150px;overflow-y:auto;" : NULL)."overflow-x:hidden;\">
	        <table style=\"width:".($total_items <= 4 ? "600" : "580")."px;\" cellspacing=\"0\" cellpadding=\"4\">
	            <tr>
	                <td style=\"background-color:#efefef;width:22px;border-bottom:1px solid #cccccc;border-right:1px solid #cccccc;text-align:center;\">
    	                ".$this->form->checkbox("name=",
                            	                "onClick=checkall(document.getElementsByName('ap_invoice_item[]'), this.checked);tally_totals();".($this->invoice_hash ? "\$('changed').value=1;" : NULL))."
    	            </td>
	                <td style=\"background-color:#efefef;border-right:1px solid #cccccc;text-align:right;width:70px;\">Qty</td>
	                <td style=\"background-color:#efefef;border-right:1px solid #cccccc;width:150px;\">Item No.</td>
	                <td style=\"background-color:#efefef;border-right:1px solid #cccccc;width:".($total_items <= 4 ? "233" : "213")."px;\">Item Desc</td>
	                <td style=\"background-color:#efefef;text-align:right;width:125px;\">Ext Cost</td>
	            </tr>
	            ". $item_tbl ."
	        </table>
        </div>";

        if ($args)
            return $tbl;

        $this->content['html']['item_list_holder'] = $tbl;
	}

	function fetch_item_cost($item_hash) {
        if (!$item_hash)
            return;

		$r = $this->db->query("SELECT (t1.cost * t1.qty) AS ext_cost , t2.currency , t2.exchange_rate
		                       FROM line_items t1
                               LEFT JOIN purchase_order t2 ON t2.po_hash = t1.po_hash
		                       WHERE t1.item_hash = '$item_hash'");
        if ($row = $this->db->fetch_assoc($r)) {
            $ext_cost = $row['ext_cost'];
            //if ($row['currency'] && $row['exchange_rate'])
                //$ext_cost = currency_exchange($ext_cost, $row['exchange_rate']);
        } else {
	        $r = $this->db->query("SELECT (t1.true_cost * t1.time) AS ext_cost , t2.currency , t2.exchange_rate
	                               FROM work_order_items t1
	                               LEFT JOIN purchase_order t2 ON t2.po_hash = t1.po_hash
	                               WHERE t1.item_hash = '$item_hash'");
            $row = $this->db->fetch_assoc($r);
            $ext_cost = $row['ext_cost'];
            //if ($row['currency'] && $row['exchange_rate'])
                //$ext_cost = currency_exchange($ext_cost, $row['exchange_rate']);
        }

        return $ext_cost;
	}

	function fetch_expense_distributions($proposal_hash) {

        $expense_dist = array();
        $r = $this->db->query("SELECT t1.account_no , t1.account_name
                               FROM accounts t1
                               WHERE t1.account_hash = '".DEFAULT_AP_ACCT."'");
        $ap_account = ($this->db->result($r, 0, 'account_no') ?
            $this->db->result($r, 0, 'account_no')." : " : NULL).$this->db->result($r, 0, 'account_name');

        $r = $this->db->query("SELECT t1.expense_hash , t1.amount , t1.memo , t2.invoice_hash , t2.invoice_date , t2.invoice_no ,
                               t3.account_no , t3.account_name , t4.vendor_name
                               FROM vendor_payable_expenses t1
                               LEFT JOIN vendor_payables t2 ON t2.invoice_hash = t1.invoice_hash
                               LEFT JOIN vendors t4 ON t4.vendor_hash = t2.vendor_hash
                               LEFT JOIN accounts t3 ON t3.account_hash = t1.account_hash
                               WHERE t1.proposal_hash = '$proposal_hash' AND t2.deleted = 0");
        while ($row = $this->db->fetch_assoc($r))
            $expense_dist[] = array('invoice_hash'      =>      $row['invoice_hash'],
                                    'expense_hash'      =>      $row['expense_hash'],
                                    'vendor_name'       =>      $row['vendor_name'],
                                    'amount'            =>      $row['amount'],
                                    'memo'              =>      stripslashes($row['memo']),
                                    'invoice_date'      =>      date("Y-m-d", strtotime($row['invoice_date'])),
                                    'invoice_no'        =>      $row['invoice_no'],
                                    'debit_account'     =>      ($row['account_no'] ? $row['account_no']." : " : NULL).$row['account_name'],
                                    'credit_account'    =>      $ap_account);

        return $expense_dist;
	}

	function toggle_pay_flag() {
        $post_args = $this->ajax_vars;

        foreach ($post_args as $invoice_hash => $flag) {
        	if (strlen($invoice_hash) == 32)
        		$this->db->query("UPDATE vendor_payables t1
        		                  SET t1.pay_flag = '".$flag."'
        		                  WHERE t1.invoice_hash = '".$invoice_hash."'");

        }
	}





}
?>