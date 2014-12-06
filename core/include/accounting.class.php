<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class accounting extends AJAX_library {

	public $total;
	public $journal_total;
	public $check_total;

	public $current_hash;

	public $account_info = array();
	public $journal_info = array();
	public $current_account = array();
	public $current_check = array();

	public $journal_detail = array();
	

	public $trans_type = array(
    	"code"  => 	array(
        	"AR",
			"AP",
			"CD",
			"CR",
			"GJ",
			"PR",
			"AD",
			"ME",
			"VC",
		    "CL",
		    "FC",
		    "CC"
		),
        "type"	=>	array(
            "Invoice",
			"Bill",
			"Check",
			"Cash Receipt",
			"General Journal",
			"Payroll",
			"Adjustment",
			"Memo Cost",
			"Vendor Credit",
	        "Closing",
	        "Finance Charges",
	        "Customer Credit"
		)
	);

	public $closed_err = "The transaction could not be completed because the period has already been closed.";
	protected $acct_ops = array();

	private $form;
	public $err;

	public $transaction;
	public $transaction_exec;
	public $account_balance;
	public $debug;
	public $debug_trans;

	public $correction_codes;

	function accounting($passedHash=NULL, $proposal_hash=NULL, $debug=NULL) {

		global $db;

		if ( $passedHash )
			$this->current_hash = $passedHash;
		else
			$this->current_hash = $_SESSION['id_hash'];

		$this->form = new form;
		$this->db =& $db;
		$this->content['class_name'] = get_class($this);

		if ( $debug )
            $this->debug = 1;

		if ( $proposal_hash )
			$this->proposal_hash = $proposal_hash;

		$this->p = new permissions($this->current_hash);
		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `accounts`
									WHERE `parent_hash` = ''");
		$this->total = $this->db->result($result);

		if ( $_POST['active_search'] ) {

			$result = $this->db->query("SELECT
                                    		t1.search_class,
                                    		t1.detail_search,
                                    		t1.query,
                                    		t1.total,
                                    		t1.search_str
										FROM search t1
										WHERE t1.search_hash = '{$_POST['active_search']}'");
			$row = $this->db->fetch_assoc($result);
			if ( $row['search_class'] == 'check_reg' ) {

				$sql_str = unserialize( base64_decode($row['query']) );

				$sql = $sql_str['sql'];
				$having = $sql_str['having'];

				$r = $this->db->query("SELECT COUNT( check_register.obj_id ) AS Total
									   FROM check_register
									   LEFT JOIN vendor_payment ON vendor_payment.check_no = check_register.check_no AND vendor_payment.account_hash = check_register.account_hash
									   LEFT JOIN vendor_payables ON vendor_payables.invoice_hash = vendor_payment.invoice_hash " .
                    				   ( $sql ?
    									   "WHERE $sql" : NULL
                    				   ) . "
									   GROUP BY check_register.check_no");
				$this->check_total = $this->db->num_rows($r);
				$check_reg = true;

			} elseif ( $row['search_class'] == 'journal' ) {

				$total = $row['total'];
				$sql_str = unserialize(base64_decode($row['query']));
                $sql = $sql_str['sql'];

		        $r = $this->db->query("SELECT COUNT(t1.obj_id) AS Total
		                               FROM journal t1
		                               LEFT JOIN proposals t2 ON t2.proposal_hash = t1.proposal_hash
		                               LEFT JOIN customer_invoice t3 ON t3.invoice_hash = t1.ar_invoice_hash
		                               LEFT JOIN customer_payment t4 ON t4.payment_hash = t1.ar_payment_hash
		                               LEFT JOIN vendor_payables t5 ON t5.invoice_hash = t1.ap_invoice_hash
		                               LEFT JOIN vendor_payment t6 ON t6.payment_hash = t1.ap_payment_hash " .
                        		       ( $sql ?
    		                               "WHERE $sql" : NULL
                        		       ) . "
		                               GROUP BY t1.audit_id");
				$this->journal_total = $this->db->num_rows($r);

				if ( $this->journal_total != $total ) {

					if ( ! $this->db->query("UPDATE search t1
        									 SET t1.total = '{$this->total}'
        									 WHERE t1.search_hash = '{$_POST['active_search']}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
                    }
				}

				$journal = true;

			} elseif ( $row['search_class'] == 'accounts' ) {

                $sql_str = unserialize( base64_decode($row['query']) );

                $sql = $sql_str['sql'];
				$r = $this->db->query("SELECT COUNT(*) AS Total
									   FROM accounts t1 " .
                        			   ( $sql ?
    									   "WHERE $sql" : NULL
                        			   ) );
                $this->total = $this->db->result($r, 0, 'Total');

				if ( ! $this->db->query("UPDATE search t1
        								 SET t1.total = '{$this->total}'
        								 WHERE t1.search_hash = '{$_POST['active_search']}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
                }
			}
		}

		if ( ! $this->proposal_hash && ! $check_reg ) {

			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `check_register`");
			$this->check_total = $this->db->result($result, 0, 'Total');
		}

		if ( ! $journal ) {

			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `journal` " .
                                		( $this->proposal_hash ?
    										"WHERE journal.proposal_hash = '{$this->proposal_hash}'" : NULL
                                		) . "
										GROUP BY journal.audit_id");
			$this->journal_total = $this->db->num_rows($result, 0, 'Total');
		}

		return;
	}

	function init_check_reg($account_hash) {
	    if (!$account_hash) {
	   	    $this->check_total = 0;
	   	    return;
	    }

	    $r = $this->db->query("SELECT COUNT(*) AS Total
	                           FROM `check_register`
	                           WHERE `account_hash` = '$account_hash'");
	    $this->check_total = $this->db->result($r);
	    $this->selected_check_reg = $account_hash;
	}

	function __destruct() {
		$this->content = '';
	}

	function fetch_accounts($start, $end, $order_by=NULL, $order_dir=NULL) {

		$this->account_info = array();

		if ( $this->active_search ) {

			$result = $this->db->query("SELECT
	                            			t1.query,
	                            			t1.total
								  		FROM search t1
								  		WHERE t1.search_hash = '{$this->active_search}'");

			if ( $row = $this->db->fetch_assoc($result) ) {

    			$this->total = $row['total'];
    			$sql_str = unserialize( base64_decode($row['query']) );

                $sql = $sql_str['sql'];
                $having = $sql_str['having'];
			}
		}

		$result = $this->db->query("SELECT
                                		t1.account_name,
                                		t1.account_no,
                                		t1.account_hash,
                                		t1.balance,
                                		t3.type as type_name,
    									t2.account_name AS child_account_name,
    									t2.account_no AS child_account_no,
    									t2.account_hash AS child_account_hash,
    									t2.balance AS child_account_balance
									FROM accounts t1
									LEFT JOIN account_types t3 ON t3.type_code = t1.account_type
									LEFT JOIN accounts t2 ON t2.parent_hash = t1.account_hash " .
                            		( $sql ?
    									"WHERE $sql " : NULL
                            		) .
                            		( $order_by ?
    							  		"ORDER BY $order_by " .
                                		( $order_dir ?
                                    		$order_dir : "ASC"
                                    	) : NULL
                                    ) );
		while( $row = $this->db->fetch_assoc($result) ) {

			if ( is_array($this->account_info[ $row['account_hash'] ]) ) {

				$child[] = $row['child_account_hash'];
				$this->account_info[ $row['account_hash'] ]['children'][] = $row;
			} else {

				$this->account_info[$row['account_hash']]['account_info'] = $row;
				if ( $row['child_account_hash'] ) {

					$this->account_info[ $row['account_hash'] ]['children'][] = $row;
					$child[] = $row['child_account_hash'];
				}
			}
		}

		reset( $this->account_info );
		if ( is_array($child) ) {

			while ( list($account_hash) = each($this->account_info) ) {

				if ( in_array($account_hash, $child) )
					unset($this->account_info[ $account_hash ]);
			}
		}

		$this->total = count($this->account_info);
		$this->account_info = array_slice($this->account_info, $start, $end);
	}

	function fetch_accounts_by_type($type, $checking=NULL) {
		if (is_array($type)) {
			for ($i = 0; $i < count($type); $i++)
                $type[$i] = "'".$type[$i]."'";

            $type_str = "t1.account_type IN (".implode(", ", $type).")";
		} else
            $type_str = "t1.account_type = '$type'";

		$result = $this->db->query("SELECT t1.account_name , t1.account_no , t1.account_hash , t1.balance , t1.parent_hash AS is_child ,
								   account_types.type as type_name ,
									t2.account_name AS child_account_name , t2.account_no AS child_account_no , t2.account_hash AS child_account_hash , t2.balance AS child_account_balance
									FROM `accounts` AS t1
									LEFT JOIN `account_types` ON account_types.type_code = t1.account_type
									LEFT JOIN `accounts` AS t2 ON t2.parent_hash = t1.account_hash
									WHERE $type_str ".($checking ? "AND t1.checking = 1" : NULL)."
									ORDER BY t1.account_no ASC ");
		while($row = $this->db->fetch_assoc($result)) {
			if (is_array($account_info[$row['account_hash']])) {
				$child[] = $row['child_account_hash'];
				$account_info[$row['account_hash']]['children'][] = $row;
			} else {
				$account_info[$row['account_hash']]['account_info'] = $row;
				if ($row['child_account_hash']) {
					$account_info[$row['account_hash']]['children'][] = $row;
					$child[] = $row['child_account_hash'];
				}
			}
		}

		reset($account_info);
		if (is_array($child)) {
			while (list($account_hash) = each($account_info)) {
				if (in_array($account_hash, $child))
					unset($account_info[$account_hash]);
			}
		}

		reset($account_info);
		while (list($account_hash) = each($account_info)) {
			$hash[] = $account_hash;
			$name[] = ($account_info[$account_hash]['account_info']['account_no'] ?
			             $account_info[$account_hash]['account_info']['account_no']." - " : NULL).$account_info[$account_hash]['account_info']['account_name'];
			$is_child[] = 0;

			if ($account_info[$account_hash]['children']) {
				for ($i = 0; $i < count($account_info[$account_hash]['children']); $i++) {
					$hash[] = $account_info[$account_hash]['children'][$i]['child_account_hash'];
					$name[] = "&nbsp;&nbsp;--&nbsp;&nbsp;".($account_info[$account_hash]['children'][$i]['child_account_no'] ?
					               $account_info[$account_hash]['children'][$i]['child_account_no']." - " : NULL).$account_info[$account_hash]['children'][$i]['child_account_name'];
					$is_child[] = 1;
				}
			}
		}

		return array($hash, $name, $is_child);
	}

	function fetch_account_record($account_hash) {

		$this->current_account = array();
		unset($this->account_hash);

		$result = $this->db->query("SELECT t1.* , account_types.type as type_name ,
									account_types.account_type as account_action , account_types.account_side ,
									t2.account_name AS child_account_name , t2.account_no AS child_account_no ,
									t2.account_hash AS child_account_hash
									FROM `accounts` AS t1
									LEFT JOIN `account_types` ON account_types.type_code = t1.account_type
									LEFT JOIN `accounts` t2 ON t2.parent_hash = t1.account_hash
									WHERE t1.account_hash = '$account_hash'");
		while($row = $this->db->fetch_assoc($result)) {
			$i++;
			if ($i == 1) {
				$this->current_account = $row;
				$this->account_hash = $row['account_hash'];
			}
			if ($row['child_account_hash']) {
	            if ($row['child_account_hash']) {
	                $r = $this->db->query("SELECT accounts.balance AS child_account_balance
	                                       FROM accounts
	                                       WHERE accounts.account_hash = '".$row['child_account_hash']."'");
	                $row['child_account_balance'] = $this->db->result($r, 0, 'child_account_balance');
	            }

				$this->current_account['children'][] = $row;
			}
		}

		if ($this->account_hash) {
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `journal`
										WHERE `account_hash` = '".$this->account_hash."'");
			if ($this->db->result($result))
				$this->current_account['lock'] = 1;

			return true;
		}

		return false;
	}

	function fetch_account_types() {
		$result = $this->db->query("SELECT `type` , `type_code` , `account_type` , `account_side`
									FROM `account_types`
									ORDER BY `type` ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			$type[] = $row['type'];
			$code[] = $row['type_code'];
			$account_type[] = $row['account_type'];
			$account_side[] = $row['account_side'];
		}

		return array($type, $code, $account_type, $account_side);
	}

	function doit_config() {
		$final = $_POST['final'];
		$fiscal_month = $_POST['fiscal_month'];
		$fiscal_day = $_POST['fiscal_day'];
		$fiscal_year = $_POST['fiscal_year'];
		//This is the fiscal year we are setting up
		$year_config = $_POST['year_config'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$period_type = $_POST['period_type'];
		$config_complete = $_POST['period_config_complete'];
		$wip_min_amt = preg_replace('/[^-.0-9]/', "", $_POST['wip_min_amt']);
		$wip_clearing_acct = $_POST['wip_clearing_acct'];
		$wip_clearing_acct_hash = $_POST['wip_clearing_acct_hash'];
		$toggle_layout = $_POST['toggle_layout'];

		if (!$toggle_layout && $year_config && !checkdate($fiscal_month, $fiscal_day, $fiscal_year)) {
			$this->content['error'] = 1;
			$this->content['form_return']['err']['err_date'] = 1;
			$this->content['form_return']['feedback'] = "Please check that you have entered a valid date for the indicated field.";
			return;
		} elseif ($toggle_layout) {
            $r = $this->db->query("SELECT `period_start` , `period_end`
                                   FROM period_dates
                                   WHERE `fiscal_year` = '$year_config'
                                   ORDER BY `period` ASC");
            while ($row = $this->db->fetch_assoc($r))
                $tmp_period[] = $row;

            if ($tmp_period) {
                for ($i = 0; $i < count($tmp_period); $i++) {
                	if ($i >= 0) {
                		if ($i == 0)
                        	$first_day = $tmp_period[$i]['period_start'];
                        elseif ($i > 0 && $i < count($tmp_period) - 1) {
                        	if (date("Y-m-d", strtotime($tmp_period[$i]['period_end']." +1 day")) != date("Y-m-d", strtotime($tmp_period[$i+1]['period_start']))) {
                                $invalid = true;
                                break;
                        	}
                        }
                	}
                	if ($i == count($tmp_period) - 1)
                        $last_day = $tmp_period[$i]['period_end'];
                }
                if ($invalid)
                    $this->content['html']['prev_period_config'] = "Selected fiscal year has been configured incorrectly.";
                else {
                    if (!$first_day || !$last_day || (date("L", strtotime($first_day)) == 1 && (strtotime($last_day) - strtotime($first_day)) / 86400 != 365) || (date("L", strtotime($first_day)) == 0 && (strtotime($last_day) - strtotime($first_day)) / 86400 != 364))
	                    $this->content['html']['prev_period_config'] = "Selected fiscal year has been configured incorrectly.";
                    else
                        $this->content['html']['prev_period_config'] = "Selected fiscal year has been configured correctly.";
                }
            } else
                $this->content['html']['prev_period_config'] = "Selected fiscal year has not been configured.";

		}
		if (!$toggle_layout && ($wip_clearing_acct || $wip_min_amt)) {
			if (!$wip_clearing_acct_hash || $wip_min_amt <= 0 || !$wip_min_amt) {
				$this->content['error'] = 1;
				if (!$wip_clearing_acct_hash) $this->content['form_return']['err']['err_wip_acct'] = 1;
				if (!$wip_min_amt || $wip_min_amt <= 0) $this->content['form_return']['err']['err_wip_amt'] = 1;
				$this->content['form_return']['feedback'] = "Please check that your WIP account and minimum amount are correct. The errors we found are indicated in red.";
				return;
			}
		}
		if ($final == 1) {
			if ($period_type == 5 && !$config_complete) {
				$this->content['error'] = 1;
				$this->content['form_return']['err']['err_layout'] = 1;
				$this->content['form_return']['feedback'] = "Your period closing dates have not been configured completely. Please make sure your closing dates reflect an entire year.";
				return;
			}
			if ($year_config) {
				$result = $this->db->query("SELECT *
											FROM `period_dates`
											WHERE `fiscal_year` = '$year_config'");
				while ($row = $this->db->fetch_assoc($result))
					$period_data[$row['period']] = $row;

				for ($i = 0; $i < count($period_data); $i++) {
					if (!$period_data[$i + 1]['closed'])
						break;
				}
				$period_start = $i + 1;

				$total_periods = $_POST['total_periods'];
				for ($i = $period_start; $i <= $total_periods; $i++) {
					list($year, $month, $day) = explode("-", $_POST['period'.$i]);
					if (!checkdate($month, $day, $year)) {
						$this->content['error'] = 1;
						$this->content['form_return']['err']['err'.$i] = 1;
					}
					if ($i > 1 && $_POST['period'.$i] && strtotime($_POST['period'.$i]) <= strtotime($_POST['period'.($i - 1)])) {
						$this->content['error'] = 1;
						$this->content['form_return']['err']['err'.$i] = 1;
						$this->content['form_return']['err']['err'.($i-1)] = 1;
					}
					if ($period_data[$i-1]['closed'] && strtotime($_POST['period'.$i]) <= strtotime($period_data[$i-1]['period_end'])) {
						$this->content['error'] = 1;
						$this->content['form_return']['err']['err'.$i] = 1;
						$this->content['form_return']['feedback'] = "You are attempting to overlap a period with one that has already closed. Please check your dates on the indicated fields.";
						return;
					}
					if ($i == 1)
						$period[$i]['open'] = $fiscal_year."-".$fiscal_month."-".$fiscal_day;
					else {
						if ($period_data[$i-1]['closed'])
							$period[$i]['open'] = date("Y-m-d", strtotime($period_data[$i-1]['period_end'])+86400);
						else
							$period[$i]['open'] = date("Y-m-d", strtotime($_POST['period'.($i-1)])+86400);
					}
					$period[$i]['close'] = $_POST['period'.$i];
				}
				if ($this->content['error']) {
					$this->content['form_return']['feedback'] = "Please check that you have entered a valid date for the indicated field.";
					return;
				}
				if (date("Y-m-d", strtotime($_POST['period'.$total_periods])+86400) != date("Y-m-d", strtotime(($fiscal_year + 1)."-".$fiscal_month."-".$fiscal_day))) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err'.$total_periods] = 1;
					$this->content['form_return']['feedback'] = "Incomplete periods! Please make sure that the closing date of your final period completes a full year and ends on ".date("Y-m-d", strtotime(($fiscal_year + 1)."-".$fiscal_month."-".$fiscal_day)-86400).".";
					return;
				}

				for ($i = $period_start; $i <= $total_periods; $i++) {
					$result = $this->db->query("SELECT `obj_id`
												FROM `period_dates`
												WHERE `fiscal_year` = '$year_config' AND `period` = '".$i."'");
					if ($obj_id = $this->db->result($result))
						$this->db->query("UPDATE `period_dates`
										  SET `fiscal_year` = '$year_config' , `period` = '".$i."' , `period_start` = '".$period[$i]['open']."' , `period_end` = '".$period[$i]['close']."'
										  WHERE `obj_id` = '$obj_id'");
					else
						$this->db->query("INSERT INTO `period_dates`
										  (`fiscal_year` , `period` , `period_start` , `period_end`)
										  VALUES ('$year_config' , '".$i."' , '".$period[$i]['open']."' , '".$period[$i]['close']."')");
				}
				update_sys_data('FISCAL_PERIOD_CONFIG', $period_type);
				$_SESSION['FISCAL_PERIOD_CONFIG'] = $period_type;
				update_sys_data('FISCAL_YEAR_START', $fiscal_month."/".$fiscal_day);
				$_SESSION['FISCAL_YEAR_START'] = $fiscal_month."/".$fiscal_day;
				$update = true;
			}
			if ($wip_clearing_acct_hash && $wip_min_amt) {
				if ((defined('AUTO_WIP_CLEARING_ACCT') && AUTO_WIP_CLEARING_ACCT != $wip_clearing_acct_hash) || !defined('AUTO_WIP_CLEARING_ACCT')) {
					$update = true;
					update_sys_data('AUTO_WIP_CLEARING_ACCT', $wip_clearing_acct_hash);
					$_SESSION['AUTO_WIP_CLEARING_ACCT'] = $wip_clearing_acct_hash;
					$this->db->query("UPDATE `system_vars`
							  		  SET `var_val` = '$wip_clearing_acct_hash'
							  		  WHERE `var_name` = 'AUTO_WIP_CLEARING_ACCT'");
				}
				if ((defined('AUTO_WIP_MIN_AMT') && AUTO_WIP_MIN_AMT != $wip_min_amt) || !defined('AUTO_WIP_MIN_AMT')) {
					$update = true;
					update_sys_data('AUTO_WIP_MIN_AMT', $wip_min_amt);
					$_SESSION['AUTO_WIP_MIN_AMT'] = $wip_min_amt;
					$this->db->query("UPDATE `system_vars`
							  		  SET `var_val` = '$wip_min_amt'
							  		  WHERE `var_name` = 'AUTO_WIP_MIN_AMT'");
				}
			}
			if ($_POST['DEFAULT_AR_ACCT']) {
				if (!$_POST['DEFAULT_AR_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_AR_ACCT'] = 1;
				}
				if (!defined('DEFAULT_AR_ACCT') || (defined('DEFAULT_AR_ACCT') && $_POST['DEFAULT_AR_ACCT_hash'] != DEFAULT_AR_ACCT))
					$new_val['DEFAULT_AR_ACCT'] = $_POST['DEFAULT_AR_ACCT_hash'];
			}
			if ($_POST['DEFAULT_AP_ACCT']) {
				if (!$_POST['DEFAULT_AP_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_AP_ACCT'] = 1;
				}
				if (!defined('DEFAULT_AP_ACCT') || (defined('DEFAULT_AP_ACCT') && $_POST['DEFAULT_AP_ACCT_hash'] != DEFAULT_AP_ACCT))
					$new_val['DEFAULT_AP_ACCT'] = $_POST['DEFAULT_AP_ACCT_hash'];
			}
			if ($_POST['DEFAULT_WIP_ACCT']) {
				if (!$_POST['DEFAULT_WIP_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_WIP_ACCT'] = 1;
				}
				if (!defined('DEFAULT_WIP_ACCT') || (defined('DEFAULT_WIP_ACCT') && $_POST['DEFAULT_WIP_ACCT_hash'] != DEFAULT_WIP_ACCT))
					$new_val['DEFAULT_WIP_ACCT'] = $_POST['DEFAULT_WIP_ACCT_hash'];
			}
			if ($_POST['DEFAULT_COGS_ACCT']) {
				if (!$_POST['DEFAULT_COGS_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_COGS_ACCT'] = 1;
				}
				if (!defined('DEFAULT_COGS_ACCT') || (defined('DEFAULT_COGS_ACCT') && $_POST['DEFAULT_COGS_ACCT_hash'] != DEFAULT_COGS_ACCT))
					$new_val['DEFAULT_COGS_ACCT'] = $_POST['DEFAULT_COGS_ACCT_hash'];
			}
			if ($_POST['DEFAULT_CASH_ACCT']) {
				if (!$_POST['DEFAULT_CASH_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_CASH_ACCT'] = 1;
				}
				if (!defined('DEFAULT_CASH_ACCT') || (defined('DEFAULT_CASH_ACCT') && $_POST['DEFAULT_CASH_ACCT_hash'] != DEFAULT_CASH_ACCT))
					$new_val['DEFAULT_CASH_ACCT'] = $_POST['DEFAULT_CASH_ACCT_hash'];
			}
			if ($_POST['DEFAULT_CUST_DEPOSIT_ACCT']) {
				if (!$_POST['DEFAULT_CUST_DEPOSIT_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_CUST_DEPOSIT_ACCT'] = 1;
				}
				if (!defined('DEFAULT_CUST_DEPOSIT_ACCT') || (defined('DEFAULT_CUST_DEPOSIT_ACCT') && $_POST['DEFAULT_CUST_DEPOSIT_ACCT_hash'] != DEFAULT_CUST_DEPOSIT_ACCT))
					$new_val['DEFAULT_CUST_DEPOSIT_ACCT'] = $_POST['DEFAULT_CUST_DEPOSIT_ACCT_hash'];
			}
			if ($_POST['DEFAULT_VENDOR_DEPOSIT_ACCT']) {
				if (!$_POST['DEFAULT_VENDOR_DEPOSIT_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_VENDOR_DEPOSIT_ACCT'] = 1;
				}
				if (!defined('DEFAULT_VENDOR_DEPOSIT_ACCT') || (defined('DEFAULT_VENDOR_DEPOSIT_ACCT') && $_POST['DEFAULT_VENDOR_DEPOSIT_ACCT_hash'] != DEFAULT_VENDOR_DEPOSIT_ACCT))
					$new_val['DEFAULT_VENDOR_DEPOSIT_ACCT'] = $_POST['DEFAULT_VENDOR_DEPOSIT_ACCT_hash'];
			}
			if ($_POST['DEFAULT_DISC_TAKEN_ACCT']) {
				if (!$_POST['DEFAULT_DISC_TAKEN_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_DISC_TAKEN_ACCT'] = 1;
				}
				if (!defined('DEFAULT_DISC_TAKEN_ACCT') || (defined('DEFAULT_DISC_TAKEN_ACCT') && $_POST['DEFAULT_DISC_TAKEN_ACCT_hash'] != DEFAULT_DISC_TAKEN_ACCT))
					$new_val['DEFAULT_DISC_TAKEN_ACCT'] = $_POST['DEFAULT_DISC_TAKEN_ACCT_hash'];
			}
			if ($_POST['DEFAULT_PROFIT_ACCT']) {
				if (!$_POST['DEFAULT_PROFIT_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_PROFIT_ACCT'] = 1;
				}
				if (!defined('DEFAULT_PROFIT_ACCT') || (defined('DEFAULT_PROFIT_ACCT') && $_POST['DEFAULT_PROFIT_ACCT_hash'] != DEFAULT_PROFIT_ACCT))
					$new_val['DEFAULT_PROFIT_ACCT'] = $_POST['DEFAULT_PROFIT_ACCT_hash'];
			}
			if ($_POST['DEFAULT_WIP_ACCT']) {
				if (!$_POST['DEFAULT_WIP_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_DEFAULT_WIP_ACCT'] = 1;
				}
				if (!defined('DEFAULT_WIP_ACCT') || (defined('DEFAULT_WIP_ACCT') && $_POST['DEFAULT_WIP_ACCT_hash'] != DEFAULT_WIP_ACCT))
					$new_val['DEFAULT_WIP_ACCT'] = $_POST['DEFAULT_WIP_ACCT_hash'];
			}

			//small order fee
			if ($_POST['SMALL_ORDER_FEE_INCOME_ACCT']) {
				if (!$_POST['SMALL_ORDER_FEE_INCOME_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_SMALL_ORDER_FEE_INCOME_ACCT'] = 1;
				}
				if (!defined('SMALL_ORDER_FEE_INCOME_ACCT') || (defined('SMALL_ORDER_FEE_INCOME_ACCT') && $_POST['SMALL_ORDER_FEE_INCOME_ACCT_hash'] != SMALL_ORDER_FEE_INCOME_ACCT))
					$new_val['SMALL_ORDER_FEE_INCOME_ACCT'] = $_POST['SMALL_ORDER_FEE_INCOME_ACCT_hash'];
			}
			if ($_POST['SMALL_ORDER_FEE_COGS_ACCT']) {
				if (!$_POST['SMALL_ORDER_FEE_COGS_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_SMALL_ORDER_FEE_COGS_ACCT'] = 1;
				}
				if (!defined('SMALL_ORDER_FEE_COGS_ACCT') || (defined('SMALL_ORDER_FEE_COGS_ACCT') && $_POST['SMALL_ORDER_FEE_COGS_ACCT_hash'] != SMALL_ORDER_FEE_COGS_ACCT))
					$new_val['SMALL_ORDER_FEE_COGS_ACCT'] = $_POST['SMALL_ORDER_FEE_COGS_ACCT_hash'];
			}

			//freight fee
			if ($_POST['FREIGHT_INCOME_ACCT']) {
				if (!$_POST['FREIGHT_INCOME_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_FREIGHT_INCOME_ACCT'] = 1;
				}
				if (!defined('FREIGHT_INCOME_ACCT') || (defined('FREIGHT_INCOME_ACCT') && $_POST['FREIGHT_INCOME_ACCT_hash'] != FREIGHT_INCOME_ACCT))
					$new_val['FREIGHT_INCOME_ACCT'] = $_POST['FREIGHT_INCOME_ACCT_hash'];
			}
			if ($_POST['FREIGHT_COGS_ACCT']) {
				if (!$_POST['FREIGHT_COGS_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_FREIGHT_COGS_ACCT'] = 1;
				}
				if (!defined('FREIGHT_COGS_ACCT') || (defined('FREIGHT_COGS_ACCT') && $_POST['FREIGHT_COGS_ACCT_hash'] != FREIGHT_COGS_ACCT))
					$new_val['FREIGHT_COGS_ACCT'] = $_POST['FREIGHT_COGS_ACCT_hash'];
			}

			//fuel fee
			if ($_POST['FUEL_SURCHARGE_INCOME_ACCT']) {
				if (!$_POST['FUEL_SURCHARGE_INCOME_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_FUEL_SURCHARGE_INCOME_ACCT'] = 1;
				}
				if (!defined('FUEL_SURCHARGE_INCOME_ACCT') || (defined('FUEL_SURCHARGE_INCOME_ACCT') && $_POST['FUEL_SURCHARGE_INCOME_ACCT_hash'] != FUEL_SURCHARGE_INCOME_ACCT))
					$new_val['FUEL_SURCHARGE_INCOME_ACCT'] = $_POST['FUEL_SURCHARGE_INCOME_ACCT_hash'];
			}
			if ($_POST['FUEL_SURCHARGE_COGS_ACCT']) {
				if (!$_POST['FUEL_SURCHARGE_COGS_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_FUEL_SURCHARGE_COGS_ACCT'] = 1;
				}
				if (!defined('FUEL_SURCHARGE_COGS_ACCT') || (defined('FUEL_SURCHARGE_COGS_ACCT') && $_POST['FUEL_SURCHARGE_COGS_ACCT_hash'] != FUEL_SURCHARGE_COGS_ACCT))
					$new_val['FUEL_SURCHARGE_COGS_ACCT'] = $_POST['FUEL_SURCHARGE_COGS_ACCT_hash'];
			}

			//cbd fee
			if ($_POST['CBD_INCOME_ACCT']) {
				if (!$_POST['CBD_INCOME_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_CBD_INCOME_ACCT'] = 1;
				}
				if (!defined('CBD_INCOME_ACCT') || (defined('CBD_INCOME_ACCT') && $_POST['CBD_INCOME_ACCT_hash'] != CBD_INCOME_ACCT))
					$new_val['CBD_INCOME_ACCT'] = $_POST['CBD_INCOME_ACCT_hash'];
			}
			if ($_POST['CBD_COGS_ACCT']) {
				if (!$_POST['CBD_COGS_ACCT_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_CBD_COGS_ACCT'] = 1;
				}
				if (!defined('CBD_COGS_ACCT') || (defined('CBD_COGS_ACCT') && $_POST['CBD_COGS_ACCT_hash'] != CBD_COGS_ACCT))
					$new_val['CBD_COGS_ACCT'] = $_POST['CBD_COGS_ACCT_hash'];
			}

			if ($this->content['error']) {
				$this->content['form_return']['feedback'] = "The account(s) you selected, indicated in red, are not valid. Please select valid accounts.";
				return;
			}
			if ($new_val) {
				while (list($var_name, $var_val) = each($new_val)) {
					$_SESSION[$var_name] = $var_val;
					update_sys_data($var_name, $var_val);
				}
				$update = true;
				$this->db->query("UPDATE `session`
								  SET `reload_session` = 1
								  WHERE `obj_id` > 0");
			}

			$this->content['action'] = 'continue';
			$this->content['page_feedback'] = ($update ? "Your business cycle has been configured and saved." : "No changes have been made");
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'show_journal', 'p=$p', 'order=$order', 'order_dir=$order_dir');");
			return;
		} else {
			if ($change_year = $_POST['change_year'])
				$this->content['value']['fiscal_year'] = $change_year;

			if (!$period_type)
				$period_type = (defined('FISCAL_PERIOD_CONFIG') ? FISCAL_PERIOD_CONFIG : 1);

			$this->content['value']['period_type'] = $period_type;
			$period_start = $_POST['period_start'];
			list($this->content['html']['period_layout_holder'], $this->content['jscript']) = $this->config($period_type, $fiscal_month."/".$fiscal_day, $period_start);
			$this->content['action'] = 'continue';
			returrn;
		}
	}

	function doit_close_period() {
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$close_array = $_POST['period'];
		for ($i = 0; $i < count($close_array); $i++) {
			if ($close_array[$i])
				$close_period[] = $close_array[$i];
		}
		if (!count($close_period)) {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "You have not selected a period to close. Please check the box cooresponding to the period you wish to close.";
			return;
		}

		for ($i = 0; $i < count($close_period); $i++) {
			$result = $this->db->query("SELECT *
										FROM `period_dates`
										WHERE `obj_id` = ".$close_period[$i]."");
			$period_info = $this->db->fetch_assoc($result);

			if ($period_info['closed'] == 1) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "This period has already been closed. Please check to make sure someone else hasn't already performed this action.";
				return;
			}
			$this->db->query("UPDATE `period_dates`
							  SET `close_timestamp` = ".time()." , `closed_by` = '".$this->current_hash."' , `closed` = 1
							  WHERE `fiscal_year` = '".$period_info['fiscal_year']."' AND `period` = '".$period_info['period']."'");
		}
		$this->content['action'] = 'continue';
		$this->content['page_feedback'] = "Fiscal year ".$period_info['fiscal_year']." period ".$period_info['period']." has been closed.";
		$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'close_period', 'p=$p', 'order=$order', 'order_dir=$order_dir');");
		return;
	}


	function doit() {

		$action = $_POST['action'];
		$btn = $_POST['btn'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$jscript_action = $_POST['jscript_action'];

		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		if ($action)
			return $this->$action();

		if ( $btn == 'saveaccount' ) {

			if ( ! $this->db->start_transaction() )
    			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			if ( $_POST['account_type'] && $_POST['account_name'] ) {

				$account_hash = $_POST['account_hash'];
				$account_type = $_POST['account_type'];
				$parent_hash = $_POST['parent_hash'];
				$active = $_POST['active'];
				$account_name = trim($_POST['account_name']);
				$account_no = trim($_POST['account_no']);
				$checking = $_POST['checking'];
				$next_check_no = $_POST['next_check_no'];

				if ( $account_type != 'CA' )
					unset($checking, $next_check_no);

				$balance = preg_replace('/[^-.0-9]/', "", $_POST['balance']);

				if ( ! $account_hash && $account_no ) {

					if ( $account_name || $account_no ) {

                        $result = $this->db->query("SELECT COUNT(*) AS Total
                                                    FROM accounts t1
                                                    WHERE " .
                                                    ( $account_no && $account_name ?
                                                        "t1.account_no = '$account_no' AND t1.account_name = '" . addslashes($account_name) . "'" :
                                                        ( $account_name && ! $account_no ?
                                                            "t1.account_name = '" . addslashes($account_name) . "' AND t1.account_no = ''" : NULL
                                                        )
                                                    ) );
                        if ( $this->db->result($result, 0, 'Total') ) {

                        	$this->set_error("err1_4{$this->popup_id}");
                        	return $this->__trigger_error("An account already exists under the account name or number you entered. Please check to make sure you are not creating a duplicate.", E_USER_ERROR, __FILE__, __LINE__, 1);
                        }
					}
				}

				if ( $next_check_no && strspn($next_check_no, "0123456789") != strlen($next_check_no) )
    				return $this->__trigger_error("The next check number you entered is not a valid number. Please make sure you are entering only a number.", E_USER_ERROR, __FILE__, __LINE__, 1);

				if ( $account_hash ) {

					if ( ! $this->fetch_account_record($account_hash) )
    					return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ $account_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

    				$sql = array();

					( $account_type != $this->current_account['account_type'] ? array_push($sql, "t1.account_type = '$account_type'" ) : NULL );
					( $active != $this->current_account['active'] ? array_push($sql, "t1.active = '$active'" ) : NULL );
					( $account_name != $this->current_account['account_name'] ? array_push($sql, "t1.account_name = '$account_name'" ) : NULL );
					( $account_no != $this->current_account['account_no'] ? array_push($sql, "t1.account_no = '$account_no'" ) : NULL );
					( $parent_hash != $this->current_account['parent_hash'] ? array_push($sql, "t1.parent_hash = '$parent_hash'" ) : NULL );
					( $checking != $this->current_account['checking'] ? array_push($sql, "t1.checking = '$checking'" ) : NULL );
                    ( $next_check_no != $this->current_account['next_check_no'] ? array_push($sql, "t1.next_check_no = '$next_check_no'" ) : NULL );

                    $feedback = "No changes have been made.";

					if ( $sql ) {

						if ( ! $this->db->query("UPDATE accounts t1
        										 SET
            										 t1.timestamp = UNIX_TIMESTAMP(),
            										 t1.last_change = '{$this->current_hash}',
            										 " . implode(",\n", $sql) . "
        										 WHERE t1.account_hash = '{$this->account_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$feedback = "Your account has been updated.";
					}

				} else {

					$account_hash = rand_hash('accounts', 'account_hash');

					if ( ! $this->db->query("INSERT INTO accounts
        									 (
            									 timestamp,
            									 last_change,
            									 account_hash,
            									 parent_hash,
            									 account_name,
            									 active,
            									 account_no,
            									 account_type,
            									 checking,
            									 next_check_no,
            									 balance
                                             )
        									 VALUES
        									 (
	        									 UNIX_TIMESTAMP(),
	        									 '{$this->current_hash}',
	        									 '$account_hash',
	        									 '$parent_hash',
	        									 '$account_name',
	        									 '$active',
	        									 '$account_no',
	        									 '$account_type',
	        									 '$checking', " .
	        									 ( $next_check_no ?
	            									 $next_check_no : 0
	                                             ) . ",
	                                             '$balance'
                                             )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
                    
					$feedback = "Account has been created.";
				}

				$this->db->end_transaction();

				$this->content['action'] = 'close';
				$this->content['page_feedback'] = $feedback;
				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir');" );

			} else {

				if ( ! $_POST['account_type'] ) $this->set_error("err1_1{$this->popup_id}");
				if ( ! $_POST['account_name'] ) $this->set_error("err1_3{$this->popup_id}");

				return $this->__trigger_error("Please check that you have completed the required fields.", E_USER_ERROR, __FILE__, __LINE__, 1);
			}

		} elseif ( $btn == 'rmaccount' ) {

			$account_hash = $_POST['account_hash'];

			if ( ! $this->fetch_account_record($account_hash) )
    			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			if ( $this->current_account['lock'] || $this->current_account['sys_lock'] )
				return $this->__trigger_error(
    				"We are unable to delete this account because " .
					( $this->current_account['lock'] ?
	                    "we found journal entries that have been made under this account" : "this account is a system account"
					) . ". To prevent this account from being used uncheck the active box.", E_USER_ERROR, __FILE__, __LINE__, 1);

			if ( ! $this->db->query("DELETE FROM accounts
        							 WHERE account_hash = '{$this->account_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

		    if ( ! $this->db->query("UPDATE accounts t1
    		                         SET t1.parent_hash = ''
        		                     WHERE t1.parent_hash = '{$this->account_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			$feedback = "Your account has been deleted.";
			$this->content['action'] = 'close';
			$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir');" );

		} elseif ( $btn == 'saveentry' ) {

			$trans_type = $_POST['trans_type'];
			$entry_date = $_POST['entry_date'];

			$_POST['account_name'] = array_values($_POST['account_name']);
			$_POST['account_hash'] = array_values($_POST['account_hash']);
			$_POST['debit'] = array_values($_POST['debit']);
			$_POST['credit'] = array_values($_POST['credit']);
			$_POST['memo'] = array_values($_POST['memo']);
			$_POST['name'] = array_values($_POST['name']);
			$_POST['name_hash'] = array_values($_POST['name_hash']);

			$account_name = $_POST['account_name'];
			$account_hash = $_POST['account_hash'];
			$debit = $_POST['debit'];
			$credit = $_POST['credit'];
			$memo = $_POST['memo'];
			$entity_name = $_POST['name'];
			$entity_hash = $_POST['name_hash'];

			$p = $_POST['p'];
			$order = $_POST['order'];
			$order_dir = $_POST['order_dir'];
			$journal_proposal_no = $_POST['journal_proposal_no'];
			$journal_proposal_hash = $_POST['journal_proposal_hash'];
			$journal_memo_amt = $_POST['journal_memo_amt'];

			$journal_checking_acct = $_POST['journal_checking_acct'];
			$journal_check_no = $_POST['journal_check_no'];
			$journal_check_remit_to = $_POST['journal_check_remit_to'];
			$journal_check_payee = $_POST['journal_check_payee'];
			$journal_check_payee_hash = $_POST['journal_check_payee_hash'];

			$proposal_hash = $_POST['proposal_hash'];

			$submit_btn = 'journal_btn';

			if ( $trans_type && $entry_date ) {

				if ( ( $trans_type == 'ME' && ! $journal_proposal_no ) || ( $trans_type == 'ME' && ! $journal_memo_amt ) || ( $trans_type == 'CD' && ! $journal_check_no ) || ( $trans_type == 'CD' && ! $journal_checking_acct) || ($trans_type == 'CD' && !$journal_check_payee) || ($trans_type == 'CD' && ! $journal_check_payee_hash ) ) {

					if ( $trans_type == 'ME' ) {

						$this->set_error_msg("In order to create a memo cost, you must associate it with a proposal and include a memo amount. Please make sure the indicated fields below are completed.");
						if ( ! $journal_proposal_no )
							$this->set_error('err_proposal_no');
						if ( ! $journal_memo_amt )
							$this->set_error('err_memo_amt');

					} elseif ( $trans_type == 'CD' ) {

						$this->set_error_msg("In order to create a check entry please complete the indicated fields below.");
						if ( ! $journal_check_no )
							$this->set_error('err_check_no');
						if ( ! $journal_checking_acct )
							$this->set_error('err_check_acct');
						if ( ! $journal_check_payee )
							$this->set_error('err_check_payee');
						if ($journal_check_payee && !$journal_check_payee_hash) {

							$this->set_error_msg("We can't seem to find the payee you indicated below. Please make sure you select a valid payee from the list shown.");
							$this->set_error('err_check_payee');
						}
					}

					return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				if ( ! $this->db->start_transaction() )
					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$this->start_transaction( array(
	    			'proposal_hash'  =>  ( $journal_proposal_hash ? $journal_proposal_hash : $proposal_hash ),
	    			'check_no'       =>  $journal_check_no,
	    			'vendor_hash'    =>  $journal_check_payee_hash,
	        		'id_hash'        =>  $this->current_hash
				) );

				if ( $trans_type == 'CD' ) {

					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM check_register t1
												WHERE t1.check_no = '$journal_check_no' AND t1.account_hash = '$journal_checking_acct'");
					if ( $this->db->result($result) ) {

						$this->set_error('err_check_no');
						return $this->__trigger_error("The check number you entered, $journal_check_no, has already been used and is found in the check register under the specified checking account. Please enter a check number that has not been used before continuing.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
					}
				}

				if ( $trans_type == 'ME' && ! $journal_proposal_hash ) {

					$this->set_error('err_proposal_no');
					return $this->__trigger_error("We can't seem to find the proposal you indicated below. Please make sure you select a valid proposal from the list provided.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				}

				if ( $this->period_ck($entry_date) && ! $_POST['period_closed_ok'] ) {

					if($this->p->ck('accounting', 'P', 'period')){
						$jscript[] = "document.getElementById(\"proceedJournal\").style.display = \"block\";";
						$this->content["jscript"] = $jscript;
      					return $this->__trigger_error($this->closed_err, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
					}else{
						return $this->__trigger_error($this->closed_err, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
					}

				}

				$dr = 0;
				$cr = 0;

				for ( $i = 0; $i < count($account_hash); $i++ ) {

					$dr = bcadd($dr, preg_replace('/[^.0-9]/', "", $_POST['debit'][$i]), 2);
					$cr = bcadd($cr, preg_replace('/[^.0-9]/', "", $_POST['credit'][$i]), 2);
					if ( $account_name[$i] && ! $account_hash[$i] && ( $_POST['debit'][$i] || $_POST['credit'][$i] ) ) {

						$account_error = 1;
						$this->set_error("err{$i}");
					}
				}

				if ( $account_error )
					return $this->__trigger_error("We can't seem to find the account indicated below. Please make sure you select a valid account.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( bccomp($cr, $dr, 2) || ( ! $dr && ! $cr ) )
					return $this->__trigger_error("DR: $dr CR: $cr " . ( ! $dr && ! $cr ? "Your journal entry is empty. Please complete at least 1 debit and 1 credit to continue." : "Your journal entry is out of balance (Debit: \$" . number_format($dr, 2) . " Credit: \$" . number_format($cr, 2) . "). Please make sure that your credit entries are equal to your debit entries." ), E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

                $audit_id = $this->audit_id();

				if ( $trans_type == 'CD' ) {

                    $check_flag = 1;
                    $check_amt = 0;
                    $ca_more_than_one = 0;
				}

				for ( $i = 0; $i < count($account_hash); $i++ ) {

					$dr = preg_replace('/[^.0-9]/', "", $_POST['debit'][$i]);
					$cr = preg_replace('/[^.0-9]/', "", $_POST['credit'][$i]);

					if ( $account_hash[$i] && ( bccomp($dr, 0, 2) || bccomp($cr, 0, 2) ) ) {

                        if ( ! $this->fetch_account_record($account_hash[$i]) )
                            return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ {$account_hash[$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                        if ( $this->current_account['account_action'] == 'DR' ) {

                            if ( bccomp($cr, 0, 2) )
                                $cr = bcmul($cr, -1, 2);
                        } else {

                            if ( bccomp($dr, 0, 2) )
                                $dr = bcmul($dr, -1, 2);
                        }

                        if ( $trans_type == 'CD' ) {

							if ( $i == 0 )
                                $memo[$i] .= ( trim( $memo[$i] ) ? " - " : NULL ) . "Check No: $journal_check_no";

                            if ( $this->current_account['account_type'] == 'CA' ) {
                            	if( !$ca_more_than_one ){
                            		$check_amt = preg_replace('/[^-.0-9]/', "", $_POST['credit'][$i]);
                            		$ca_more_than_one = 1;
                            		unset( $check_flag );
                            	}else{
                            		return $this->__trigger_error("Error - Only one checking account per journal check entry allowed", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                            	}
                                
                            }
                        }

                        $this->setTransIndex( array(
	                        'customer_hash',
	                        'vendor_hash'
                        ), 1);

                        if ( $entity_name[$i] && $entity_hash[$i] ) {

                        	$matches = array();
                        	if ( preg_match('/^(customer|vendor)_([a-zA-Z0-9]{32})$/', $entity_hash[$i], $matches) ) {

								$this->setTransIndex( array(
									"{$matches[1]}_hash"	=>	$matches[2]
								) );
                        	}

                        } elseif ( $journal_check_payee_hash ) {

                        	$this->setTransIndex( array(
	                        	'vendor_hash'	=>	$journal_check_payee_hash
                        	) );
                        }

					    if ( bccomp($dr, 0, 2) ) {

							if ( ! $this->exec_trans($audit_id, $entry_date, $account_hash[$i], $dr, $trans_type, addslashes( $memo[$i] )) )
								return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					    }
                        if ( bccomp($cr, 0, 2) ) {

                            if ( ! $this->exec_trans($audit_id, $entry_date, $account_hash[$i], $cr, $trans_type, addslashes( $memo[$i] )) )
                                return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }
					}
				}

				if ( $trans_type == 'CD' ) {

					if ( $check_flag )
                        return $this->__trigger_error("Entries categorized as 'Cash Disbursements' must include at least one debit/credit entry against an account flagged as a checking account.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					
					if ( ! $this->db->query("INSERT INTO check_register
        									 (
	        									 timestamp,
	        									 last_change,
	        									 account_hash,
	        									 payee,
	        									 remit_to,
	        									 check_date,
	        									 check_no,
	        									 memo,
	        									 amount
        									 )
        									 VALUES
        									 (
	        									 UNIX_TIMESTAMP(),
	        									 '{$this->current_hash}',
	        									 '$journal_checking_acct',
	        									 '$journal_check_payee_hash',
	        									 '$journal_check_remit_to',
	        									 '$entry_date',
	        									 '$journal_check_no',
	        									 '" . addslashes($memo[0]) . "',
	        									 '$check_amt'
        									 )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

				} elseif ( $trans_type == 'ME' ) {

					$memo_hash = rand_hash('memo_hash', 'memo_hash');

					if ( ! $this->db->query("INSERT INTO memo_costs
        									 (
	        									 timestamp,
	        									 last_change,
	        									 memo_hash,
	        									 entry_date,
	        									 proposal_hash,
	        									 amount,
	        									 descr
        									 )
        									 VALUES
        									 (
	        									 UNIX_TIMESTAMP(),
	        									 '{$this->current_hash}',
	        									 '$memo_hash',
	        									 '$entry_date',
	        									 '$journal_proposal_hash',
	        									 '$journal_memo_amt',
	        									 '" . addslashes($memo[0]) . "'
        									 )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }
				}

				if ( ! $this->end_transaction() )
                    return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$this->db->end_transaction();

				$this->content['action'] = 'close';
				$this->content['page_feedback'] = "Your journal entry has been saved.";
				if ( $proposal_hash )
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'ledger', 'p=$p', 'proposal_hash=$proposal_hash', 'otf=1');" );
				else
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'show_journal', 'p=$p', 'order=$order', 'order_dir=$order_dir');" );

			} else {

				if ( ! $trans_type ) $this->set_error("err1{$this->popup_id}");
				if ( ! $entry_date ) $this->set_error("err1{$this->popup_id}");
				return $this->__trigger_error("Please check that you completed the indicated fields.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
			}
		}
	}

	function do_search($search_q, $local=NULL) {
		if (!is_array($search_q) || !count($search_q))
			return;

		while (list($q_col, $q_info) = each($search_q)) {
			if ($q_info['comp_op']) {
				$combine = " AND ";
				$sql[] = $q_col." ".$q_info['comp_op']." '".$q_info['comp_var']."'";
			} else {
				$combine = " OR ";
				for ($i = 0; $i < count($q_info); $i++)
					$sql[] = $q_col." ".$q_info[$i]['comp_op']." '".$q_info[$i]['comp_var']."'";
			}
		}
		if (!is_array($sql))
			return;

		$result = $this->db->query("SELECT t1.obj_id
							  		FROM `accounts` as t1
							  		WHERE ".implode($combine, $sql));
		$total = $this->db->num_rows($result);
		$search_hash = md5(global_classes::get_rand_id(32, "global_classes"));
		while (global_classes::key_exists('search', 'search_hash', $search_hash))
			$search_hash = md5(global_classes::get_rand_id(32, "global_classes"));

		$this->db->query("INSERT INTO `search`
						 (`timestamp` , `search_hash` , `query` , `total` , `variables`)
						 VALUES (".time()." , '$search_hash' , '".base64_encode(serialize(implode($combine, $sql)))."' , '$total' , '".base64_encode(serialize($search_q))."')");

		$this->total = $total;
		$this->active_search = $search_hash;
		//$this->content['action'] = 'close';
		//$this->content['jscript_action'] = "agent.call('customers', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'active_search=$search_hash');";
		return;
	}

	function innerHTML_accounts() {
		if (!func_get_args() && $this->ajax_vars['qtype']) {
			$qtype = $this->ajax_vars['qtype'];
			$account_type = $this->ajax_vars['account_type'];
			$selected_value = $this->ajax_vars['parent_hash'];
		} elseif (func_get_args()) {
			$args = func_get_args();
			$qtype = $args[0];
			$account_type = $args[1];
			$selected_value = $args[2];
			$local = 1;
		}
		list($hash, $name, $is_child) = $this->fetch_accounts_by_type($account_type);

		for ($i = 0; $i < count($hash); $i++) {
			if ($hash[$i] != $this->current_account['account_hash'] && !$is_child[$i])
			    $option[] = "<option value=\"".$hash[$i]."\" ".($selected_value && $selected_value == $hash[$i] ? "selected" : NULL).">".$name[$i]."</option>";

		}
		if (!is_array($option)) {
			if ($local)
                return "<i>There are no applicable parent accounts.</i>";

            $this->content['html'][$qtype] = "<i>There are no applicable parent accounts.</i>";
			return;
		}
		$select = "<select name=\"parent_hash\" class=\"txtSearch\" style=\"width:375px;\"><option></option>".implode("\n", $option)."</select>";

		if ($local)
			return $select;
		else
			$this->content['html'][$qtype] = $select;
		return;
	}

	function fetch_account_activity($account_hash, $start) {

		$end = SUB_PAGNATION_NUM;
		$detail = array();

        $result = $this->db->query("SELECT
	                                    t1.*,
	                                    t1.check_no AS payment_check_no,
	                                    t2.proposal_no
                                    FROM journal t1
                                    LEFT JOIN proposals t2 ON t2.proposal_hash = t1.proposal_hash
                                    WHERE t1.account_hash = '$account_hash'
                                    ORDER BY t1.obj_id DESC " .
                                    ( is_numeric($start) ?
                                        "LIMIT $start, $end" : NULL
                                    ) );
		while ( $row = $this->db->fetch_assoc($result) )
			$detail[] = $row;

		return $detail;
	}

	function edit_account() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		list($type, $code) = $this->fetch_account_types();

		if ( $account_hash = $this->ajax_vars['account_hash'] ) {

			if ( ! $this->fetch_account_record($account_hash) )
                return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ $account_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

            $type = $code = array();
			list($e_name, $e_code, $e_type, $e_side) = $this->fetch_account_types();

			for ( $i = 0; $i < count($e_name); $i++ ) {

				if ( $e_type[$i] == $this->current_account['account_action'] && $e_side[$i] == $this->current_account['account_side'] ) {

					array_push($type, $e_name[$i]);
					array_push($code, $e_code[$i]);
				}
			}

			if ( count($type) <= 1 || $this->account_hash == DEFAULT_PROFIT_ACCT )
                $account_type_name = $e_name[ array_search($this->current_account['account_type'], $e_code) ];

			unset($e_name, $e_code, $e_type, $e_side);

		}

		$this->content['popup_controls']['popup_id'] = $this->popup_id = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );
		$this->content['popup_controls']['popup_title'] = ( $this->account_hash ? "Edit Account : " . ( $this->current_account['account_no'] ? "{$this->current_account['account_no']} - " : NULL ) . ( strlen($this->current_account['account_name']) > 65 ? substr($this->current_account['account_name'], 0, 63) . "..." : stripslashes($this->current_account['account_name']) ) : "Create New Account" );

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
    		"account_hash" => $this->account_hash,
			"popup_id" => $this->popup_id,
			"p" => $p,
			"order" => $order,
			"order_dir" => $order_dir
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>" .
			( $this->account_hash ?
				"<ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">
					<li class=\"selected\" ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent1{$this->popup_id}\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\">General</a></li>
					<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent2{$this->popup_id}\" rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\">Activity</a></li>
				</ul>
				<div id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\">" : NULL
			) . "
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#ffffff;padding-top:10px\" id=\"err1_1{$this->popup_id}\" nowrap>Account Type: *</td>
						<td style=\"background-color:#ffffff;padding-top:10px\">" .
							( ! $this->account_hash || ( $this->account_hash && count($type) && $this->account_hash != DEFAULT_PROFIT_ACCT ) ?
								$this->form->select(
	    							"account_type",
	                    			$type,
	                    			$this->current_account['account_type'],
	                    			$code,
	                    			"onChange=agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'innerHTML_accounts', 'account_type=' + this.options[ this.selectedIndex ].value, 'qtype=subaccount_holder{$this->popup_id}');if(this.options[this.selectedIndex].value=='CA'){toggle_display('checking_holder{$this->popup_id}', 'block');}else{toggle_display('checking_holder{$this->popup_id}', 'none');}",
	                    			( $this->account_hash ? "blank=1" : NULL )
	                    		) : $account_type_name
	                    	) .
                            ( ( $this->account_hash && $this->current_account['account_type'] == "CA" ) || ! $this->account_hash ?
								"<div style=\"padding-top:10px;padding-left:5px;display:" . ( $this->account_hash && $this->current_account['account_type'] == 'CA' ? "block" : "none" ) . "\" id=\"checking_holder{$this->popup_id}\">" .
									$this->form->checkbox(
    									"name=checking",
										"value=1",
										( $this->current_account['checking'] ? "checked" : NULL ),
										"onClick=if(this.checked){toggle_display('check_no_holder', 'block');toggle_display('check_stock_holder', 'block');}else{toggle_display('check_no_holder', 'none');toggle_display('check_stock_holder', 'none');}"
									) . "
									Will you write checks from this account?
									<!--<div style=\"margin-left:25px;display:" . ( $this->current_account['checking'] ? "block" : "none" ) . "\" id=\"check_stock_holder\"><small>[<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'edit_check_stock', 'account_hash=" . ( $this->account_hash ? $this->account_hash : 'rand_' . rand(50, 5000) ) . "', 'popup_id=check_stock');\">edit check stock</a>]</small></div>-->
									<div style=\"margin-top:5px;display:" . ( $this->current_account['checking'] ? "block" : "none" ) . "\" id=\"check_no_holder\">" .
									    $this->form->text_box(
    									    "name=next_check_no",
								            "value={$this->current_account['next_check_no']}",
								            "size=7",
								            "style=text-align:right;"
									    ) . "
								        What is the next check number to print?
									</div>
								</div>" : NULL
                            ) . "
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_2{$this->popup_id}\">Active: </td>
						<td style=\"background-color:#ffffff;\">" .
    						$this->form->checkbox(
        						"name=active",
        						"value=1",
        						( ! $this->account_hash || $this->current_account['active'] ? "checked" : NULL )
        					) . "
        				</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_3{$this->popup_id}\">Account Name: *</td>
						<td style=\"background-color:#ffffff;\">" .
							$this->form->text_box(
    							"name=account_name",
    							"value=" . stripslashes($this->current_account['account_name']),
    							"maxlength=128",
    							"size=35"
							) .
							( $this->current_account['system'] ?
								"&nbsp;&nbsp;<small><i>(System Account" . ( $this->current_account['sys_lock'] ? " and Cannot be Deleted" : NULL ) . ")</i></small>" : NULL
							) . "
							<div style=\"padding-top:10px;\">
								<i>Parent Account: </i>
								<div style=\"padding-top:5px;margin-left:10px;padding-bottom:5px;\" id=\"subaccount_holder{$this->popup_id}\">" .
								( $this->account_hash ?
									$this->innerHTML_accounts(
    									"subaccount_holder{$this->popup_id}",
    									$this->current_account['account_type'],
    									$this->current_account['parent_hash']
    								)
    								:
    								$this->form->select_disabled( array(
        								"name"      =>  "parent_hash",
    								    "message"   =>  "Select type of account first..."
    								) )
        						) . "
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_4{$this->popup_id}\">Account Number: </td>
						<td style=\"background-color:#ffffff;\">" .
    						$this->form->text_box(
        						"name=account_no",
        						"value={$this->current_account['account_no']}",
        						"maxlength=16",
        						"size=15"
        					) . "
        				</td>
					</tr>
				</table>" .
            ( $this->account_hash ?
    			"</div>
                <div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">" . $this->account_history_list(1) . "</div>" : NULL
            ) . "
			<div style=\"padding:10px;text-align:right;padding-right:45px;\">" .
	            ( ! $this->account_hash || ( $this->account_hash && $this->p->ck(get_class($this), 'E', 'accounts') ) ?
					$this->form->button(
	    				"value=Save Account",
	    				"onClick=submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'btn=saveaccount');"
					) : NULL
				) . "
				&nbsp;&nbsp;" .
				( ( ! $this->account_hash || ! $this->current_account['lock'] ) && ! $this->current_account['sys_lock'] && $this->p->ck(get_class($this), 'D', 'accounts') && $this->account_hash ?
					$this->form->button(
    					"value=Delete Account",
    					"onClick=if( confirm('Are you sure you want to delete this account? The action CANNOT be undone!') ){submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'btn=rmaccount');}"
					) : NULL
				) . "
			</div>
		</div>" .
		$this->form->close_form();

		if ( $this->account_hash ) {

			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')', 10);";
			if ( $this->ajax_vars['tab_to'] )
				$this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_{$this->ajax_vars['tab_to']}{$this->popup_id}\'))', 100)";
		}

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function account_history_list() {

		$local = func_get_arg(0);

		if ( ! $local ) {

			$p = $this->ajax_vars['p'];
    		$this->popup_id = $this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );
		}

		if ( ! $this->account_hash ) {

			if ( $account_hash = $this->ajax_vars['account_hash'] ) {

				if ( ! $this->fetch_account_record($account_hash) )
				    return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ $account_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);
			}
		}

		$result = $this->db->query("SELECT COUNT(*) AS Total
                        			FROM journal t1
                        			WHERE t1.account_hash = '{$this->account_hash}'");
		$total = $this->db->result($result, 0, 'Total');

		$num_pages = ceil( $total / SUB_PAGNATION_NUM );
		$p = ( ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p );
		$start_from = SUB_PAGNATION_NUM * ( $p - 1 );
		$end = $start_from + SUB_PAGNATION_NUM;
		if ( $end > $total )
			$end = $total;

		$order_by_ops = array(
    		"date"	=>	"journal.date"
    	);

		$detail = $this->fetch_account_activity($this->account_hash, $start_from);

		$tbl = "
		<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">" .
                    		( $total > 0 ?
								"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" .
	                        		paginate_jscript(
	                            		$num_pages,
	                            		$p,
	                            		'sf_loadcontent',
	                            		'cf_loadcontent',
	                            		'accounting',
	                            		'account_history_list',
	                            		$order,
	                            		$order_dir,
	                            		"'otf=1', 'account_hash={$this->account_hash}', 'popup_id={$this->popup_id}'"
	                            	) . "
                            	</div>
								Showing " . ( $start_from + 1 ) . " - " .
	                            ( $start_from + SUB_PAGNATION_NUM > $total ?
    	                            $total : $start_from + SUB_PAGNATION_NUM
    	                        ) . " of $total Entries for " . stripslashes($this->current_account['account_name']) . "." : "&nbsp;"
    	                    ) . "
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"font-size:9pt;\">Date</td>
							<td style=\"font-size:9pt;\">Type</td>
							<td style=\"font-size:9pt;\">Proposal</td>
							<td style=\"font-size:9pt;text-align:right;\">Debit</td>
							<td style=\"font-size:9pt;text-align:right;\">Credit</td>
							<td style=\"font-size:9pt;\">Memo</td>
						</tr>";

                        $border = "border-bottom:1px solid #cccccc";
						for ( $i = 0; $i < ($end - $start_from); $i++ ) {

							$b++;
							if ( $b >= ( $end - $start_from ) )
    							unset($border);

							$tbl .= "
							<tr id=\"tr1{$detail[$i]['audit_id']}\" title=\"View Journal Entry {$detail[$i]['audit_id']}\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'view_journal_detail', 'audit_id={$detail[$i]['audit_id']}', 'popup_id=detail_win')\" title=\"Click to view entire journal entry for audit id {$detail[$i]['audit_id']}\" onMouseOver=\"\$(this).addClassName('item_row_over');\" onMouseOut=\"\$(this).removeClassName('item_row_over');\">
								<td style=\"font-size:8pt;$border\">" . date(DATE_FORMAT, strtotime($detail[$i]['date']) ) . "</td>
								<td style=\"font-size:8pt;$border\">" .
								( $detail[$i]['trans_type'] ?
									$this->trans_type['type'][ array_search($detail[$i]['trans_type'], $this->trans_type['code']) ] : "&nbsp;"
								) .
								( $detail[$i]['trans_type'] == 'CD' && ( $detail[$i]['payment_check_no'] || $detail[$i]['check_no'] ) ?
                                    "&nbsp;" .
    								( $detail[$i]['payment_check_no'] ?
    									$detail[$i]['payment_check_no'] : $detail[$i]['check_no']
    								) : "&nbsp;"
    							) . "
								</td>
								<td style=\"font-size:8pt;$border\">" .
    							( $detail[$i]['proposal_no'] ?
									$detail[$i]['proposal_no'] : "&nbsp;"
								) . "
								</td>
								<td style=\"font-size:8pt;;text-align:right;$border\">" .
								( bccomp($detail[$i]['debit'], 0, 4) == 1 ?
									"\$" . number_format( _round($detail[$i]['debit'], 2), 2) : "&nbsp;"
								) . "
								</td>
								<td style=\"font-size:8pt;;text-align:right;$border\">" .
								( bccomp($detail[$i]['credit'], 0, 4) == 1 ?
									"\$" . number_format( _round($detail[$i]['credit'], 2), 2) : "&nbsp;"
								) . "
								</td>
								<td style=\"font-size:8pt;$border\">" .
								( strlen($detail[$i]['memo']) > 25 ?
									stripslashes( substr($detail[$i]['memo'], 0, 25) ) . "..." : stripslashes($detail[$i]['memo'])
								) . "&nbsp;
								</td>
							</tr>";
						}

						if ( ! $b )
							$tbl .= "
							<tr>
								<td colspan=\"6\">There is no activity to show under this account.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		if ( $local )
			return $tbl;

		if ( $this->ajax_vars['otf'] == 1 )
			$this->content['html']["tcontent2{$this->popup_id}"] = $tbl;

		return;
	}

	function doit_search_journal() {

		$detail_search = $_POST['detail_search'];

		$check_no = preg_replace('/[^0-9]/', "", $_POST['check_no']);
		$type = $_POST['type'];
		$invoice_no = $_POST['invoice_no'];
		$from_account = $_POST['from_account'];
        if ( $from_account && ! is_array($from_account) )
            $from_account = array($from_account);

		$customer_filter = $_POST['customer_filter'];
		if ( $customer_filter && ! is_array($customer_filter) )
			$customer_filter = array($customer_filter);

		$vendor_filter = $_POST['vendor_filter'];
		if ( $vendor_filter && ! is_array($vendor_filter) )
			$vendor_filter = array($vendor_filter);

		if ( ! $from_account[0] )
			unset($from_account);

		$proposal_filter = $_POST['proposal_filter'];
		if ( ! is_array($proposal_filter) && $proposal_filter )
			$proposal_filter = array($proposal_filter);

		$date_start = $_POST['date_start'];
		$date_end = $_POST['date_end'];

		$save = $_POST['save'];
		$search_name = $_POST['search_name'];
		if ( ! $search_name )
			unset($save);

        $sql_p = array();

		if ( $check_no )
			array_push($sql_p, "t6.check_no = '$check_no'");

		if ( $type )
			array_push($sql_p, "t1.trans_type = '$type'");

		if ( is_array($proposal_filter) && count($proposal_filter) ) {

			$proposal_filter = array_unique($proposal_filter);
			$proposal_filter = array_values($proposal_filter);
			array_walk($proposal_filter, 'add_quotes', "'");
			array_push($sql_p, "t1.proposal_hash " . ( count($proposal_filter) == 1 ? "= {$proposal_filter[0]}" : "IN (" . implode(", ", $proposal_filter) . ")" ) );
			array_walk($proposal_filter, 'strip_quotes');
		}

		if ( is_array($from_account) && count($from_account) ) {

			$from_account = array_unique($from_account);
			$from_account = array_values($from_account);
			array_walk($from_account, 'add_quotes', "'");
			array_push($sql_p, "t1.account_hash " . ( count($from_account) == 1 ? "= {$from_account[0]}" : "IN (" . implode(", ", $from_account) . ")" ) );
			array_walk($from_account, 'strip_quotes');
		}

		if ( is_array($customer_filter) && count($customer_filter) ) {

			$customer_filter = array_unique($customer_filter);
			$customer_filter = array_values($customer_filter);
			array_walk($customer_filter, 'add_quotes', "'");
			array_push($sql_p, "t1.customer_hash " . ( count($customer_filter) == 1 ? "= {$customer_filter[0]}" : "IN (" . implode(", ", $customer_filter) . ")" ) );
			array_walk($customer_filter, 'strip_quotes');
		}
		if ( is_array($vendor_filter) && count($vendor_filter) ) {

			$vendor_filter = array_unique($vendor_filter);
			$vendor_filter = array_values($vendor_filter);
			array_walk($vendor_filter, 'add_quotes', "'");
			array_push($sql_p, "t1.vendor_hash " . ( count($vendor_filter) == 1 ? "= {$vendor_filter[0]}" : "IN (" . implode(", ", $vendor_filter) . ")" ) );
			array_walk($vendor_filter, 'strip_quotes');
		}

		if ( $date_start && ! $date_end )
			array_push($sql_p, "t1.date >= '$date_start'");
		elseif ( $date_end && ! $date_start )
            array_push($sql_p, "t1.date <= '$date_end'");
		elseif ( $date_end && $date_start ) {

			if ( strtotime($date_start) > strtotime($date_end) ) {

				unset($date_start);
                array_push($sql_p, "t1.date <= '$date_end'");
			} else
    			array_push($sql_p, "t1.date BETWEEN '$date_start' AND '$date_end'");
		}

		if ( $invoice_no )
			array_push($sql_p, " ( t3.invoice_no LIKE '$invoice_no%' OR t5.invoice_no LIKE '$invoice_no%' )");

        $str = "check_no=$check_no|type=$type|invoice_no=$invoice_no|from_account=$from_account|customer_filter=" . implode("&", $customer_filter) . "|vendor_filter=" . implode("&", $vendor_filter) . "|proposal_filter=" . implode("&", $proposal_filter) . "|date_start=$date_start|date_end=$date_end";

		if ( $sql_p )
			$sql = implode(" AND ", $sql_p);

        $r = $this->db->query("SELECT COUNT(t1.obj_id) AS Total
                               FROM journal t1
                               LEFT JOIN proposals t2 ON t2.proposal_hash = t1.proposal_hash
                               LEFT JOIN customer_invoice t3 ON t3.invoice_hash = t1.ar_invoice_hash
                               LEFT JOIN customer_payment t4 ON t4.payment_hash = t1.ar_payment_hash
                               LEFT JOIN vendor_payables t5 ON t5.invoice_hash = t1.ap_invoice_hash
                               LEFT JOIN vendor_payment t6 ON t6.payment_hash = t1.ap_payment_hash " .
                               ( $sql ?
                                   "WHERE $sql " : NULL
                               ) . "
                               GROUP BY t1.audit_id");
		$total = $this->db->num_rows($r);

		$search_hash = rand_hash('search', 'search_hash');
		$sql_str = array(
    		'sql'    =>  $sql
		);

		if ( ! $this->db->query("INSERT INTO search
    						     VALUES
    						     (
	    						     NULL,
	    						     UNIX_TIMESTAMP(),
	    						     '$search_hash',
	    						     '$save',
	    						     '$search_name',
	    						     'journal',
	    						     '{$this->current_hash}',
	    						     '$detail_search',
	    						     '" . base64_encode( serialize($sql_str) ) . "',
	    						     '$total',
	    						     '" . addslashes($str) . "'
    						     )")
        ) {

            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
        }

		$this->active_search = $search_hash;

		$this->content['action'] = ( $detail_search ? 'close' : 'continue' );
		$this->content['jscript_action'] = "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'show_journal', 'active_search={$this->active_search}');";

		return;
	}

	function fetch_check_detail($check_no, $account_hash) {
		$result = $this->db->query("SELECT t1.* ,
                                    IF(ISNULL(t1.currency) OR ISNULL(@convert_currency), t1.amount, ROUND(currency_exchange(t1.amount, t1.exchange_rate, 0), 2)) AS amount ,
                                    IF(ISNULL(t1.currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(t1.currency)) AS symbol ,
                                    IF(ISNULL(t1.currency) OR ISNULL(@convert_currency), currency_phrase(NULL), currency_phrase(t1.currency)) AS currency_phrase ,
                            		t5.account_name , t5.account_no ,
									t6.account_name as expense_account_name , t6.account_no as expense_account_no , t2.customer_name ,
									t3.vendor_name
							  		FROM `check_register` t1
									LEFT JOIN `customers` t2 ON t2.customer_hash = t1.payee
                                    LEFT JOIN `vendors` t3 ON t3.vendor_hash = t1.payee
									LEFT JOIN `accounts` t5 ON t5.account_hash = t1.account_hash
									LEFT JOIN `accounts` t6 ON t6.account_hash = t1.expense_account
							  		WHERE t1.check_no = '$check_no' AND t1.account_hash = '$account_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_check = $row;

			# Find out how many vendor invoices were paid with this check
			$this->current_check['total_invoices_paid'] = 0;
			$r = $this->db->query("SELECT t1.obj_id AS counter
			                       FROM vendor_payment t1
		                           WHERE t1.check_no = '{$this->current_check['check_no']}' AND t1.account_hash = '{$this->current_check['account_hash']}'
		                           GROUP BY t1.invoice_hash");
            while ($row_2 = $this->db->fetch_assoc($r))
                $this->current_check['total_invoices_paid']++;

			return true;
		}

		return false;
	}


	function fetch_check_register($start, $end=NULL, $order_by=NULL, $order_dir=NULL) {
		$doit_excel = $_POST['doit_excel'];
		if ($doit_excel) {
			$order_by = "check_register.check_no , check_register.check_date";
			$order_dir = "DESC";
		}
		if (!$end && !$from_xls)
			$end = MAIN_PAGNATION_NUM;

		if ($this->active_search) {
			$result = $this->db->query("SELECT `search_class` , `detail_search` , `query` , `total` , `search_str`
										FROM `search`
								  		WHERE `search_hash` = '".$this->active_search."'");
			$row = $this->db->fetch_assoc($result);
			if ($row['search_class'] == 'check_reg') {
				$sql_str = unserialize(base64_decode($row['query']));

                $sql = $sql_str['sql'];
                $having = $sql_str['having'];

				unset($this->page_pref['custom']);
				$this->detail_search = $row['detail_search'];
				$this->search_vars = $this->p->load_search_vars($row['search_str']);
			}
		}

		$this->check_register = array();
		$result = $this->db->query("SELECT check_register.* , SUM(check_register.amount) as totalAmount, accounts.account_name , accounts.account_no ,
									t1.account_name as expense_account_name , t1.account_no as expense_account_no , customers.customer_name ,
									vendors.vendor_name , COUNT(vendor_payment.check_no) AS total_invoices_paid
							  		FROM `check_register`
									LEFT JOIN `customers` ON customers.customer_hash = check_register.payee
									LEFT JOIN `vendors` ON vendors.vendor_hash = check_register.payee
                                    LEFT JOIN `vendor_payment` ON vendor_payment.check_no = check_register.check_no AND vendor_payment.account_hash = check_register.account_hash AND vendor_payment.payment_amount = check_register.amount
                                    LEFT JOIN `vendor_payables` ON vendor_payables.invoice_hash = vendor_payment.invoice_hash
									LEFT JOIN `accounts` ON accounts.account_hash = check_register.account_hash
									LEFT JOIN `accounts` AS t1 ON t1.account_hash = check_register.expense_account
							  		WHERE ".($doit_excel ? "accounts.checking = 1 AND accounts.active = 1" : (!$this->active_search ? "check_register.account_hash = '".$this->selected_check_reg."' " : ($sql ?
                                        $sql : NULL)))."
									GROUP BY check_register.check_no".($order_by ? "
							  		ORDER BY $order_by ".($order_dir ? $order_dir : "ASC") : NULL).($doit_excel ? NULL : "
							  		LIMIT $start , $end"));
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->check_register, $row);

	}

	/*
	 * Fetches the journal entries for bank reconciliation tool
	 *
	 */
	function fetch_bank_reconcile($account_hash, $start, $end=NULL, $order_by=NULL, $order_dir=NULL) {
		if (!$start)
			$start = 0;
		if (!$end)
			$end = MAIN_PAGNATION_NUM;

		if ($this->active_search) {
			$result = $this->db->query("SELECT `search_class` , `detail_search` , `query` , `total` , `search_str`
										FROM `search`
								  		WHERE `search_hash` = '".$this->active_search."'");
			$row = $this->db->fetch_assoc($result);
			if ($row['search_class'] == 'check_reg') {
				$this->total = $row['total'];
				$sql_str = unserialize(base64_decode($row['query']));

                $sql = $sql_str['sql'];
                $having = $sql_str['having'];

				unset($this->page_pref['custom']);
				$this->detail_search = $row['detail_search'];
				$this->search_vars = $this->p->load_search_vars($row['search_str']);
			}
		}
		$month_back = date("Y-m-d", strtotime(date("Y-m-d")." -1 month"));
		$sql = "CASE WHEN ". "journal.date < '".$month_back."' THEN journal.cleared=0 ELSE journal.date >= '".$month_back."' END and journal.account_hash = '".$account_hash."'";

		$this->bank_reconcile = array();
		$result = $this->db->query("SELECT journal.* , accounts.account_name , accounts.account_no
							  		FROM `journal`
                                    LEFT JOIN `accounts` ON accounts.account_hash = journal.account_hash
									".($sql ? "WHERE ".$sql : NULL)."
									ORDER BY journal.date DESC LIMIT $start, $end");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->bank_reconcile, $row);

	}


	function doit_new_check() {

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

		if ( $_POST['journal_check_payee'] && $_POST['payment_account_hash'] && $_POST['account_name'] && $_POST['amount'] && $_POST['check_no'] && $_POST['check_date'] ) {

			if ( ! $_POST['journal_check_payee_hash'] ) {

				$this->set_error("err2{$this->popup_id}");
				return $this->__trigger_error("We can't seem to find the payee you entered. Please make sure you are selecting a valid payee.", E_USER_NOTICE, __FILE__, __LINE__, 1);
			}
			if ( ! $_POST['payment_account_hash'] ) {

				$this->set_error("err3{$this->popup_id}");
				return $this->__trigger_error("We can't seem to find the cash account you entered. Please make sure you are selecting a valid cash account.", E_USER_NOTICE, __FILE__, __LINE__, 1);
			}
			if ($_POST['account_name'] && !$_POST['account_hash']) {

				$this->set_error("err3a{$this->popup_id}");
				return $this->__trigger_error("We can't seem to find the account you entered, indicated below. Please make sure you are selecting a valid account.", E_USER_NOTICE, __FILE__, __LINE__, 1);
			}

			$check_no = preg_replace('/[^0-9]/', "", $_POST['check_no']);
			$payee_name = $_POST['journal_check_payee'];
			$remit_to = $_POST['journal_check_remit_to'];
			$payee = $_POST['journal_check_payee_hash'];
			$account_hash = $_POST['payment_account_hash'];
			$expense_account = $_POST['account_hash'];
            $selected_check_reg = $_POST['selected_check_reg'];

			# Check for duplicate check number
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `check_register`
										WHERE `check_no` = '$check_no' AND `account_hash` = '$account_hash'");
			if ( $this->db->result($result) ) {

				$this->set_error("err1{$this->popup_id}");
				return $this->__trigger_error("The check number you entered has already been assigned. Please enter a unique check number.", E_USER_ERROR, __FILE__, __LINE__, 1);
			}

			$amount = preg_replace('/[^-.0-9]/', "", $_POST['amount']);
			if ( bccomp($amount, 0, 2) == -1 ) {

				$this->set_error("err4{$this->popup_id}");
				return $this->__trigger_error("Your check amount is invalid! Please enter you check amount, which must be greater than 0.", E_USER_ERROR, __FILE__, __LINE__, 1);
			}

			if ( ! $this->fetch_account_record($expense_account) )
				return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ $expense_account ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			if ( $this->current_account['account_action'] == 'CR' )
				$exp_amount = bcmul($amount, -1, 2);
			else
				$exp_amount = $amount;

			$check_date = $_POST['check_date'];
			list($year, $month, $day) = explode("-", $check_date);
			if ( ! checkdate($month, $day, $year) ) {

				$this->set_error("err5{$this->popup_id}");
				return $this->__trigger_error("Your check date is invalid. Please enter a valid check date.", E_USER_NOTICE, __FILE__, __LINE__, 1);
			}

			if ( $this->period_ck($check_date) )
				return $this->__trigger_error($this->closed_err, E_USER_ERROR, __FILE__, __LINE__, 1);

			$memo = $_POST['memo'];

			$audit_id = $this->audit_id();

			if ( ! $this->db->start_transaction() )
				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			$this->start_transaction( array(
				'check_no'       =>  $check_no,
				'vendor_hash'    =>  $payee
			) );

			if ( ! $this->db->query("INSERT INTO check_register
        							 (
	        							 timestamp,
	        							 last_change,
	        							 account_hash,
	        							 expense_account,
	        							 payee,
	        							 remit_to,
	        							 check_date,
	        							 check_no,
	        							 memo,
	        							 amount
        							 )
        							 VALUES
        							 (
	        							 UNIX_TIMESTAMP(),
	        							 '{$this->current_hash}',
	        							 '$account_hash',
	        							 '$expense_account',
	        							 '$payee',
	        							 '$remit_to',
	        							 '$check_date',
	        							 '$check_no',
	        							 '" . addslashes($memo) . "',
	        							 '$amount'
        							 )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            if ( ! $this->exec_trans($audit_id, $check_date, $account_hash, bcmul($amount, -1, 2), 'CD', $memo . ( $memo ? " - " : NULL ) . "Check No. $check_no") )
                return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
            if ( ! $this->exec_trans($audit_id, $check_date, $expense_account, $exp_amount, 'CD', $memo . ( $memo ? " - " : NULL ) . "Check No. $check_no") )
                return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);


            if ( ! $this->db->query("UPDATE accounts t1
                                     SET t1.next_check_no = '" . ( ++$check_no ) . "'
                                     WHERE t1.account_hash = '$account_hash'")
            ) {

               	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            if ( ! $this->end_transaction() )
                return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

            $this->db->end_transaction();

            $this->content['action'] = 'close';
            $this->content['page_feedback'] = "Your check entry has been recorded.";
            $this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'account_hash=$selected_check_reg');" );

            return;

		} else {

			if ( ! $_POST['check_no'] ) $this->set_error("err1{$this->popup_id}");
			if ( ! $_POST['journal_check_payee'] ) $this->set_error("err2{$this->popup_id}");
			if ( ! $_POST['payment_account_hash'] ) $this->set_error("err3{$this->popup_id}");
			if ( ! $_POST['account_name'] || ! $_POST['account_hash'] ) $this->set_error("err3a{$this->popup_id}");
			if ( ! $_POST['amount'] ) $this->set_error("err4{$this->popup_id}");
			if ( ! $_POST['check_date'] ) $this->set_error("err5{$this->popup_id}");

			return $this->__trigger_error("Please check to make sure you have completed the required fields.", E_USER_NOTICE, __FILE__, __LINE__, 1);
		}
	}

	function edit_check_reg() {
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$account_hash = $this->ajax_vars['account_hash'];
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		$this->content['popup_controls']['popup_id'] = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id']);
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "New Check Entry";

		$this->fetch_account_record(($account_hash ? $account_hash : DEFAULT_CASH_ACCT));

        $r = $this->db->query("SELECT accounts.account_hash , accounts.account_no , accounts.account_name
                               FROM accounts
                               WHERE accounts.account_type = 'CA' AND accounts.checking = 1");
        while ($row = $this->db->fetch_assoc($r))
            $checking_acct[$row['account_hash']] = ($row['account_no'] ? $row['account_no']." : " : NULL).$row['account_name'];

        if (defined('DEFAULT_CASH_ACCT') && !array_key_exists(DEFAULT_CASH_ACCT, $checking_acct))
            $lookup[] = DEFAULT_CASH_ACCT;
        if (!array_key_exists($this->account_hash, $checking_acct))
            $lookup[] = $this->account_hash;

        for ($i = 0; $i < count($lookup); $i++) {
            $r = $this->db->query("SELECT `account_no` , `account_name`
                                   FROM `accounts`
                                   WHERE `account_hash` = '".$lookup[$i]."'");
            if ($row = $this->db->fetch_assoc($r))
                $checking_acct[$lookup[$i]] = ($row['account_no'] ? $row['account_no']." : " : NULL).$row['account_name'];
        }
		@asort($checking_acct);
		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("popup_id" => $this->popup_id,
		                          "p" => $p,
		                          "order" => $order,
		                          "order_dir" => $order_dir,
		                          "selected_check_reg" => $account_hash))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0;\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\">".($error ?
						$err_feedback : NULL)."</p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\">
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err1".$this->popup_id."\">Check Number: *</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">".
						$this->form->text_box("name=check_no",
											  "value=".($this->current_account['next_check_no'] ? $this->current_account['next_check_no'] : NULL),
						                      "maxlength=18",
											  "size=15")."
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\">
						<div id=\"err2".$this->popup_id."\">Payee: *</div>
						<div style=\"margin-top:12px;\">Remit To:</div>
					</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">
						".$this->form->text_box("name=journal_check_payee",
												"value=",
												"autocomplete=off",
												"size=40",
												"onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('accounting', 'journal_check_payee', 'journal_check_payee_hash', 1);}",
						                        "onKeyUp=if(ka==false && this.value){key_call('accounting', 'journal_check_payee', 'journal_check_payee_hash', 1);}",
												"onBlur=key_clear();",
												"onKeyDown=if(event.keyCode!=9){clear_values('journal_check_payee_hash');}").
						$this->form->hidden(array("journal_check_payee_hash" => ''))."
						<div style=\"width:250px;margin-top:5px;\">
							".$this->form->text_area("name=journal_check_remit_to", "rows=3", "cols=39")."
						</div>
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err3".$this->popup_id."\">Cash Account: *</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">".
                        $this->form->select("payment_account_hash",
                                            array_values($checking_acct),
                                            $this->account_hash,
                                            array_keys($checking_acct),
                                            "blank=1")."
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err3a".$this->popup_id."\">Expense Account: *</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">".
						$this->form->text_box("name=account_name",
											  "value=",
											  "autocomplete=off",
											  "size=40",
											  "onFocus=select();position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('accounting', 'account_name', 'account_hash', 1);}",
						                      "onKeyUp=if(ka==false && this.value){key_call('accounting', 'account_name', 'account_hash', 1);}",
											  "onBlur=key_clear();if(!\$F('account_name')){\$('account_hash').value='';}",
											  "onKeyDown=if(event.keyCode!=9){clear_values('account_hash');}").
						$this->form->hidden(array("account_hash" => ''))."
						<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('account_name'), 'top')+20, findPos(\$('account_name'), 'left')+1);key_list('payables', 'account_name', 'account_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err4".$this->popup_id."\">Check Amount: *</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">".
						$this->form->text_box("name=amount",
											  "size=10",
											  "onBlur=if(this.value){this.value=formatCurrency(this.value);}",
											  "style=text-align:right;")."
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err5".$this->popup_id."\">Check Date: *</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">
						<span id=\"check_date_holder".$this->popup_id."\"></span>";
						$jscript[] = "setTimeout('DateInput(\'check_date\', \'true\', \'YYYY-MM-DD\', \'".date("Y-m-d")."\', 1, \'check_date_holder".$this->popup_id."\')', 45)";
						$tbl .= "
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\">Memo: </td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">".
						$this->form->text_box("name=memo",
											  "size=40")."
					</td>
				</tr>
			</table>
			<div style=\"padding:10px;text-align:right;padding-right:45px;\">
				".$this->form->button("value=Save Entry", "onClick=submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_new_check');")."
			</div>
		</div>".
		$this->form->close_form();

		if ($local)
			return $inner_tbl;

		$this->content["jscript"] = $jscript;
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function toggle_clear_flag() {
        $post_args = $this->ajax_vars;
        $account_hash = $post_args['account_hash'];
        $from_report = $post_args['from_report'];

        foreach ($post_args as $check_no => $flag) {
            if ($check_no != 'account_hash' && $check_no != 'from_report') {

	        	if ($from_report)
	            	list($check_no, $account_hash) = explode("|", $check_no);

            	$r = $this->db->query("SELECT t1.check_no
            	                       FROM check_register t1
            	                       WHERE t1.check_no = '".$check_no."' AND t1.account_hash = '".$account_hash."' AND t1.void = 0");
            	if ($this->db->result($r, 0, 'check_no')) {
	                $this->db->query("UPDATE check_register t1
	                                  SET t1.cleared = '".$flag."'
	                                  WHERE t1.check_no = '".$check_no."' AND t1.account_hash = '".$account_hash."'");
	                if (!$from_report)
    	                $this->content['jscript'][] = "\$('void_".$check_no."').style.visibility = '".($flag == 1 ? 'hidden' : 'visible')."';";
            	} else {
                    $clear_err[] = $check_no;
                    if ($from_report)
                        $this->content['jscript'][] = "\$('clear_2_".$check_no."_".$account_hash."').show();\$('clear_1_".$check_no."_".$account_hash."').hide();\$('cleared_".$check_no."_".$account_hash."').disabled=1";
                    else
                        $this->content['jscript'][] = "\$('void_".$check_no."').style.visibility = 'hidden';\$('clear_2_".$check_no."').show();\$('clear_1_".$check_no."').hide();\$('cleared_".$check_no."').disabled=1";
            	}
            }
        }
        if ($clear_err)
            $this->content['jscript'][] = "alert('Error : Check number".(count($clear_err) > 1 ? "s ".implode(", ", $clear_err)." have" : " ".$clear_err[0]." has")." been voided and therefore cannot be flagged as cleared.');";
	}

	function doit_void() {

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$check_no = $_POST['check_no'];
		$account_hash = $_POST['account_hash'];
		$selected_check_reg = $_POST['selected_check_reg'];
		$from_payables = $_POST['from_payables'];
		$from_proposal = $_POST['from_proposal'];
		$active_search = $_POST['active_search'];
        if ( $from_accounting = $_POST['from_accounting'] )
            $invoice_popup = $_POST['popup_id'];

		if ( $this->fetch_check_detail($check_no, $account_hash) ) {

			if ( $this->current_check['cleared'] || $this->current_check['void'] )
				return $this->__trigger_error("This check " . ( $this->current_check['void'] ? "has already been voided" : "cannot be voided because it has been flagged as cleared" ) . ".", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			$posting_date = date("Y-m-d");

			if ( ! $this->db->start_transaction() )
				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);

			$this->start_transaction( array(
				'check_no'       =>  $check_no,
				'vendor_hash'    =>  $this->current_check['payee']
			) );

			$audit_id = $this->audit_id();

			if ( ! $this->current_check['expense_account'] ) { # If the check was created through manual journal entry
				$result = $this->db->query("SELECT *
											FROM journal
											WHERE check_no = '{$this->current_check['check_no']}' AND auto = 0");
				while ( $row = $this->db->fetch_assoc($result) ) {
						
					if ( $this->fetch_account_record($row['account_hash']) ) {

						if ( $row['debit'] != 0 )
							$amount = $row['debit'];
						else
							$amount = $row['credit'];
						
						if ( $this->current_account['account_action'] == 'DR' ) {

							if ( $row['debit'] != 0)
								$amount = bcmul($row['debit'], -1, 2);
							else
								$amount = $row['credit'];
						} else {

							if ( $row['credit'] != 0)
								$amount = bcmul($row['credit'], -1, 2);
							else
								$amount = $row['debit'];
						}

						if ( ! $this->exec_trans($audit_id, $posting_date, $row['account_hash'], $amount, 'AD', "Voided Check : {$this->current_check['check_no']}") )
							return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
						

					} else
						return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ {$row['account_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
				}

			} else {

				# Return the money to a/p and the used cash account
				if ( ! $this->fetch_account_record($this->current_check['account_hash']) )
    				return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ {$this->current_check['account_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				if ( ! $this->exec_trans($audit_id, $posting_date, $this->account_hash, $this->current_check['amount'], 'AD', "Voided Check : {$this->current_check['check_no']}") )
					return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				if ( ! $this->fetch_account_record($this->current_check['expense_account']) )
					return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ {$this->current_check['expense_account']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				if ( $this->current_account['account_action'] == 'CR' )
					$exp_amount = $this->current_check['amount'];
				else
					$exp_amount = bcmul($this->current_check['amount'], -1, 2);

				if ( ! $this->exec_trans($audit_id, $posting_date, $this->account_hash, $exp_amount, 'AD', "Voided Check : {$this->current_check['check_no']}") )
					return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				$payables = new payables($this->current_hash);
				$result = $this->db->query("SELECT t1.*
											FROM vendor_payment t1
											WHERE t1.check_no = '{$this->current_check['check_no']}' AND t1.account_hash = '{$this->current_check['account_hash']}'");
				while ( $row = $this->db->fetch_assoc($result) ) {

					if ( ! $payables->fetch_invoice_record($row['invoice_hash']) )
    					return $this->__trigger_error("System error encountered when attempting to lookup payable record. Please reload window and try again. <!-- Tried fetching payable [ {$row['invoice_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

					if ( ! $payables->fetch_payment_record($row['payment_hash']) )
                        return $this->__trigger_error("System error encountered when attempting to lookup vendor payment record. Please reload window and try again. <!-- Tried fetching payment hash [ {$row['payment_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

					$this->setTransIndex( array(
						'ap_invoice_hash'  =>  $payables->invoice_hash,
						'ap_payment_hash'  =>  $row['payment_hash'],
						'proposal_hash'    =>  $payables->current_invoice['invoice_proposal']
					) );

					$balance = bcadd($payables->current_invoice['balance'], $payables->current_payment['payment_amount'], 2);
					$balance = bcadd($balance, $payables->current_payment['deposit_used'], 2);
					$balance = bcadd($balance, $payables->current_payment['discount_taken'], 2);
					$balance = bcadd($balance, $payables->current_payment['credit_used'], 2);

					if ( $payables->current_invoice['type'] == 'D' ) { # If it's a deposit

						if ( ! $this->exec_trans($audit_id, $posting_date, DEFAULT_WIP_ACCT, $payables->current_payment['payment_amount'], 'AD', 'Purchase order deposit') )
							return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

						if ( ! $this->exec_trans($audit_id, $posting_date, DEFAULT_VENDOR_DEPOSIT_ACCT, bcmul($payables->current_payment['payment_amount'], -1, 2), 'AD', 'Purchase order deposit') )
							return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

					} elseif ( $payables->current_invoice['type'] == 'I' && bccomp($payables->current_payment['deposit_used'], 0, 2) == 1 ) {

						if ( ! $this->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, $payables->current_payment['deposit_used'], 'AD', 'Previous deposits paid') )
							return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                        if ( $this->account_action($payables->current_payment['deposit_hash']) == 'CR' )
                            $payables->current_payment['deposit_used'] = bcmul($payables->current_payment['deposit_used'], -1, 2);

						if ( ! $this->exec_trans($audit_id, $posting_date, $payables->current_payment['deposit_hash'], $payables->current_payment['deposit_used'], 'AD', 'Previous deposits paid') )
							return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
					}

					if ( bccomp($payables->current_payment['discount_taken'], 0, 2) == 1 ) {

						if ( ! $this->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, $payables->current_payment['discount_taken'], 'AD', 'Vendor discounts taken') )
							return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                        if ( $this->account_action($payables->current_payment['discount_acct_hash']) == 'CR' )
                            $payables->current_payment['discount_taken'] = bcmul($payables->current_payment['discount_taken'], -1, 2);

						if ( ! $this->exec_trans($audit_id, $posting_date, $payables->current_payment['discount_acct_hash'], $payables->current_payment['discount_taken'], 'AD', 'Vendor discounts taken') )
							return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

					}

                    if ( bccomp($payables->current_payment['credit_used'], 0, 2) == 1 ) {

                        $credit_reduced = $payables->current_payment['credit_used'];
                        list($open_credits, $total_credit_applied) = $payables->fetch_vendor_credit_applied($payables->payment_hash);

                        if ( ! $open_credits ) { # Payment was made before vendor_credit_applied tracking table

                        	# Trac 1575 - 27AUG2010 - If this condition is met payment is likely over 2 years old. Manual support intervention required. Further documentation can be found in trac ticket
                            return $this->__trigger_error("System error encountered when attempting to lookup credits applied to this payment. Please reload window and try again. <!-- Tried fetching credits for payment [ {$payables->payment_hash} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                            # $open_credits = $payables->fetch_vendor_credits($payables->current_invoice['vendor_hash'], 2);
                            # $count = count($open_credits);
                            # $total_credits = 0;

                            # for ( $i = 0; $i < $count; $i++ ) {

                            #     $open_credits[$i]['amount'] = bcsub($open_credits[$i]['amount'], $open_credits[$i]['balance'], 2);
                            #     $r = $this->db->query("SELECT SUM( t1.amount ) AS total
                            #                            FROM vendor_credit_applied t1
                            #                            WHERE t1.invoice_hash = '{$open_credits[$i]['invoice_hash']}' AND t1.void = 0");
                            #     if ( ! $this->db->result($r, 0, 'total') || bccomp($this->db->result($r, 0, 'total'), $open_credits[$i]['amount'], 2) == -1 )
                            #         $open_credits[$i]['amount'] = bcsub($open_credits[$i]['amount'], $this->db->result($r, 0, 'total'), 2);
                            #     else
                            #         unset($open_credits[$i]);

                            #     if ( $open_credits[$i]['amount'] ) {

                            #         if ( bccomp( bcadd($total_credits, $open_credits[$i]['amount'], 2), $credit_reduced, 2) == 1 )
                            #             $open_credits[$i]['amount'] = bcsub($credit_reduced, $total_credits, 2);

                            #         $total_credits = bcadd($total_credits, $open_credits[$i]['amount'], 2);
                            #         $vendor_credit_patch[] = $open_credits[$i];
                            #     }

                            #     if ( bccomp($total_credits, $credit_reduced, 2) != -1 )
                            #         break;
                            # }

                            # if ( $vendor_credit_patch ) {

                            #     for ( $i = 0; $i < count($vendor_credit_patch); $i++ ) {

                            #         if ( ! $this->db->query("INSERT INTO vendor_credit_applied
                            #                                  VALUES
                            #                                  (
	                        #                                      NULL,
	                        #                                      '{$payables->payment_hash}',
	                        #                                      '{$vendor_credit_patch[$i]['invoice_hash']}',
	                        #                                      '{$vendor_credit_patch[$i]['amount']}',
	                        #                                      0,
	                        #                                      0
                            #                                  )")
                            #         ) {

                            #         	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);
                            #         }
                            #     }

                            #     list($open_credits, $total_credit_applied) = $payables->fetch_vendor_credit_applied($payables->payment_hash);
                            # }
                        }

                        if ( bccomp($total_credit_applied, $payables->current_payment['credit_used'], 2) || ! $open_credits )
                            return $this->__trigger_error("System error encountered when attempting to revert vendor credit balance used in payment to original credit invoice. Please reload window and try again. <!-- Payment w/ vendor credit applied [ {$payables->payment_hash} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

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

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);
                            }

                            # Update the vendor credit applied table to show void
                            if ( ! $this->db->query("UPDATE vendor_credit_applied t1
                                                     SET
                                                         t1.void = 1,
                                                         t1.void_timestamp = UNIX_TIMESTAMP()
                                                     WHERE t1.obj_id = {$open_credits[$j]['obj_id']}")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);
                            }
                        }

                        if ( ! $this->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, bcmul($payables->current_payment['credit_used'], -1, 2), 'AD', "Applied Credit") )
                            return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

                        if ( ! $this->exec_trans($audit_id, $posting_date, DEFAULT_AP_ACCT, $payables->current_payment['credit_used'], 'AD', "Applied Credit") )
                            return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
                    }

					if ( ! $this->db->query("UPDATE vendor_payables t1
        									 SET
	        									 t1.timestamp = UNIX_TIMESTAMP(),
	        									 t1.last_change = '{$this->current_hash}',
	        									 t1.paid_in_full = 0,
	        									 t1.balance = '$balance'
        									 WHERE t1.invoice_hash = '{$payables->invoice_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);
                    }
				}
			}

			if ( ! $this->db->query("UPDATE check_register t1
                    				 SET t1.void = 1
        							 WHERE t1.check_no = '{$this->current_check['check_no']}' AND t1.account_hash = '$account_hash'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);
            }

			if ( ! $this->end_transaction() )
                return $this->__trigger_error("{$this->err['errno']} - {$this->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			$this->db->end_transaction();

			$this->content['action'] = 'continue';
			$this->content['page_feedback'] = "Check {$this->current_check['check_no']} has been voided.";
			if ( $from_payables && ! $from_proposal && ! $from_accounting )
				$this->content['jscript_action'] = "agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash=$from_payables', 'popup_id=line_item', 'new=true');window.setTimeout('agent.call(\'payables\', \'sf_loadcontent\', \'cf_loadcontent\', \'showall\', \'p=$p\', \'order=$order\', \'order_dir=$order_dir\')', 100);";
			elseif ( $from_proposal && ! $from_accounting )
				$this->content['jscript_action'] = "agent.call('payables', 'sf_loadcontent', 'cf_loadcontent', 'proposal_payables', 'proposal_hash=$from_proposal', 'otf=1', 'p=$p', 'order=$order', 'order_dir=$order_dir');window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash=$from_proposal\', \'otf=1\');', 1500)";
			else {

				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'account_hash=$selected_check_reg');");
                if ( $from_payables )
                    $this->content['jscript'][] = "window.setTimeout('agent.call(\'payables\', \'sf_loadcontent\', \'show_popup_window\', \'edit_invoice\', \'invoice_hash=$from_payables\', \'popup_id=$invoice_popup\', \'from_accounting=$from_accounting\');', 100);";
			}

			return true;
		} else
			return $this->__trigger_error("System error encountered when attempting to lookup check register record. Please reload window and try again. <!-- Tried fetching check [ $check_no ] under account [ $account_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
	}

	function show_invoices_paid() {
		$check_no = $this->ajax_vars['check_no'];
		$account_hash = $this->ajax_vars['account_hash'];

		$result = $this->db->query("SELECT DISTINCT vendor_payables.invoice_hash , vendor_payables.invoice_no ,
									vendor_payables.invoice_date , vendor_payment.payment_amount AS amount , check_register.void
									FROM `vendor_payables`
									LEFT JOIN `vendor_payment` ON vendor_payment.invoice_hash = vendor_payables.invoice_hash
									LEFT JOIN `check_register` ON check_register.check_no = vendor_payment.check_no
									WHERE vendor_payment.check_no = '$check_no' AND vendor_payment.account_hash = '$account_hash'");
		while ($row = $this->db->fetch_assoc($result))
			$tbl .= "<tr onMouseOver=\"this.className=\'item_row_over\'\" onMouseOut=\"this.className=\'white_bg\'\" onClick=\"agent.call(\'payables\', \'sf_loadcontent\', \'show_popup_window\', \'edit_invoice\', \'invoice_hash=".$row['invoice_hash']."\', \'popup_id=invoice_detail\', \'from_accounting=1\');\" class=\"white_bg\" title=\"View this invoice\"><td style=\"padding-left:10px;font-size:8pt;".($row['void'] ? "color:#8f8f8f;font-style:italic;" : NULL)."\">Invoice ".str_replace("'", "\'", stripslashes($row['invoice_no']))."</td><td style=\"padding-left:5px;font-size:8pt;".($row['void'] ? "color:#8f8f8f;font-style:italic;" : NULL)."\">".($row['invoice_date'] ? date(DATE_FORMAT, strtotime($row['invoice_date'])) : "&nbsp;")."</td><td style=\"text-align:right;padding-right:5px;font-size:8pt;".($row['void'] ? "color:#8f8f8f;font-style:italic;" : NULL)."\">$".number_format($row['amount'], 2)."</td></tr>";

		$tbl = "<div><table id=\"invoice_tbl_".$check_no."\" style=\"background-color:#cccccc;\" cellpadding=\"3\" cellspacing=\"1\"><tr><td style=\"background-color:#efefef;font-weight:bold;font-size:8pt;padding-left:10px;width:50%;\">Invoice No</td><td style=\"background-color:#efefef;font-weight:bold;font-size:8pt;padding-left:5px;width:25%;\">Date</td><td style=\"background-color:#efefef;font-weight:bold;font-size:8pt;text-align:right;padding-right:5px;width:25%;\">Amount</td></tr>".$tbl."</table></div>";

        $this->content['jscript'][] = "\$('invoice_list_".$check_no."').insert('".$tbl."');";
        $this->content['jscript'][] = "pos_reg_el('invoice_tbl_".$check_no."')";
		$this->content['jscript'][] = "toggle_display('invoice_list_".$check_no."', 'block');";
		return;
	}

	function doit_search_check_reg() {
		$detail_search = $_POST['detail_search'];
		$check_no = $_POST['check_no'];
		$invoice_no = trim($_POST['invoice_no']);
		if ($invoice_no)
			$invoice_no = explode(", ", $invoice_no);
		if ($invoice_no) {
			for ($i = 0; $i < count($invoice_no); $i++) {
				if (trim($invoice_no[$i]))
					$search_invoice[] = trim($invoice_no[$i]);
			}
			unset($invoice_no);
			$invoice_no = $search_invoice;
		}
		$from_account = $_POST['from_account'];
		$payee_filter = $_POST['customer_vendor_filter'];
		if (!is_array($payee_filter) && $payee_filter)
			$payee_filter = array($payee_filter);

		$cleared = $_POST['cleared'];
		$amount = preg_replace('/[^-.0-9]/', "", $_POST['amount']);
		$date_start = $_POST['date_start'];
		$date_end = $_POST['date_end'];

		$account_hash = $_POST['account_hash'];

		$save = $_POST['save'];
		$search_name = $_POST['search_name'];
		if (!$search_name)
			unset($save);

		if ($check_no)
			$sql_p[] = "check_register.check_no = '".$check_no."'";
		if ($invoice_no)
			$sql_p[] = "vendor_payables.invoice_no LIKE '%".$dc_po_no."%'";
		if ($from_account)
			$sql_p[] = "check_register.account_hash = '".$from_account."'";
		if (is_array($payee_filter) && count($payee_filter)) {
			$payee_filter = array_unique($payee_filter);
			$payee_filter = array_values($payee_filter);
			array_walk($payee_filter, 'add_quotes', "'");
			$sql_p[] = "check_register.payee IN (".implode(" , ", $payee_filter).")";
			array_walk($payee_filter, 'strip_quotes');
		}
		if (is_array($invoice_no) && count($invoice_no)) {
			$invoice_no = array_unique($invoice_no);
			$invoice_no = array_values($invoice_no);
			array_walk($invoice_no, 'add_quotes', "'");
			$sql_p[] = "vendor_payables.invoice_no IN (".implode(" , ", $invoice_no).")";
			array_walk($invoice_no, 'strip_quotes');
		}
		if ($cleared)
			$sql_p[] = "check_register.cleared = ".($cleared == 1 ? 1 : 0);
		if (is_numeric($amount))
			$sql_p[] = "check_register.amount = $amount";
        if ($date_start && !$date_end)
            $sql_p[] = "check_register.check_date >= '".$date_start."'";
        elseif ($date_end && !$date_start)
            $sql_p[] = "check_register.check_date <= '".$date_end."'";
        elseif ($date_end && $date_start) {
            if (strtotime($date_start) > strtotime($date_end)) {
                unset($date_start);
                $sql_p[] = "check_register.check_date <= '".$date_end."'";
            } else
                $sql_p[] = "check_register.check_date BETWEEN '".$date_start."' AND '".$date_end."'";
        }

        $str = "check_no=$check_no|invoice_no=".@implode(", ", $invoice_no)."|from_account=$from_account|vendor_filter=".@implode("&", $payee_filter)."|cleared=$cleared|amount=$amount|date_start=$date_start|date_end=$date_end";

		if ($sql_p)
			$sql = implode(" AND ", $sql_p);

		$search_hash = md5(global_classes::get_rand_id(32, "global_classes"));
		while (global_classes::key_exists('search', 'search_hash', $search_hash))
			$search_hash = md5(global_classes::get_rand_id(32, "global_classes"));

	    $sql_str = array('sql'     =>  $sql,
	                     'having'  =>  $having);

		$this->db->query("INSERT INTO `search`
						  (`timestamp` , `search_hash` , `saved` , `search_name` , `search_class` , `user_hash` , `detail_search` , `query` , `total` , `search_str`)
						  VALUES (".time()." , '$search_hash' , '$save' , '$search_name' , 'check_reg' , '".$this->current_hash."' , '$detail_search' , '".base64_encode(serialize($sql_str))."' , '$total' , '$str')");

		$this->active_search = $search_hash;


		$this->content['action'] = ($detail_search ? 'close' : 'continue');
		$this->content['jscript_action'] = "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'active_search=".$this->active_search."', 'account_hash=$account_hash');";
		return;
	}

	function search_check_reg() {
		$date = date("U") - 3600;
		$result = $this->db->query("DELETE FROM `search`
							  		WHERE `timestamp` <= '$date'");

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Search Check Register";
		$this->content['focus'] = "check_no";

        if ($this->ajax_vars['active_search']) {
            $result = $this->db->query("SELECT `search_str`
                                        FROM `search`
                                        WHERE `search_hash` = '".$this->ajax_vars['active_search']."'");
            $row = $this->db->fetch_assoc($result);
            $search_vars = permissions::load_search_vars($row['search_str']);
        }

        if ($search_vars['vendor_filter'] && !is_array($search_vars['vendor_filter']))
            $search_vars['vendor_filter'] = array($search_vars['vendor_filter']);

		list($search_name, $search_hash) = $this->fetch_searches('check_reg');

		list($hash, $name) = $this->fetch_accounts_by_type('CA', 1);
		$tbl .= $this->form->form_tag().
		$this->form->hidden(array('popup_id' 		=> $this->content['popup_controls']['popup_id'],
								  'detail_search' 	=> 1,
		                          'account_hash'    => $this->ajax_vars['account_hash']
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
							Filter your check register search criteria below:
						</div>
						<table>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Check Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
    										".$this->form->text_box("name=check_no",
    										                        "value=".$search_vars['check_no'],
    										                        "maxlength=24")."
                                        </div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;\">
									<fieldset>
										<legend>Vendor Invoice Number(s)</legend>
										<small style=\"font-style:italic;margin-left:5px;\">separate multiple by comma</small>
										<div style=\"padding:5px;padding-bottom:5px\">
    										".$this->form->text_box("name=invoice_no",
    										                        "size=35",
								                                    "value=".$search_vars['invoice_no'],
    										                        "maxlength=255")."
                                        </div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Paid from account: </legend>
										<div style=\"padding:5px;padding-bottom:5px\">
    										".$this->form->select("from_account",
                                								  $name,
                                								  $search_vars['from_account'],
                                								  $hash)."
                                        </div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\" rowspan=\"2\">
									<fieldset>
										<legend>Search By Payee</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=customer_vendor",
																	"value=",
																	"autocomplete=off",
																	"size=37",
																	"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('reports', 'customer_vendor', 'customer_hash', 1);}",
                                								    "onKeyUp=if(ka==false && this.value){key_call('reports', 'customer_vendor', 'customer_hash', 1);}",
																	"onBlur=key_clear();"
																	)."


											<div style=\"margin-top:15px;width:250px;height:45px;overflow:auto;display:block;background-color:#efefef;border:1px outset #cccccc;\" id=\"customer_vendor_filter_holder\">";
                                            if (is_array($search_vars['vendor_filter'])) {
                                                for ($i = 0; $i < count($search_vars['vendor_filter']); $i++)
                                                    $tbl .= "<div id=\"customer_vendor_".$search_vars['vendor_filter'][$i]."\" style=\"padding:5px 2px 0 5px;\">[<small><a href=\"javascript:void(0);\" onClick=\"remove_el(this.parentElement.parentElement)\" class=\"link_standard\" title=\"Remove from filter\">x</a></small>]&nbsp;".addslashes((strlen(reports::customer_name($search_vars['vendor_filter'][$i])) > 25 ? substr(reports::customer_name($search_vars['vendor_filter'][$i]), 0, 22)."..." : reports::customer_name($search_vars['vendor_filter'][$i])))."<input type=\"hidden\" id=\"customer_vendor_filter\" name=\"customer_vendor_filter[]\" value=\"".$search_vars['vendor_filter'][$i]."\" /></div>";
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
										<legend>Cleared/Outstanding</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
    										".$this->form->select("cleared",
																   array("Cleared", "Outstanding"),
																   $search_vars['cleared'],
																   array(1, 2))."
                                        </div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Check Amount</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
    										".$this->form->text_box("name=amount",
    										                        "onBlur=if(this.value){this.value=formatCurrency(this.value);}",
																    "style=text-align:right;",
																    "value=".($search_vars['amount'] ? number_format($search_vars['amount'], 2) : NULL))."
                                        </div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset style=\"width:90%;\">
										<legend>Date Range</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											<span id=\"date_start_holder\"></span>
											<span id=\"date_end_holder\"></span>";
										$this->content['jscript'][] = "
											setTimeout('DateInput(\'date_start\', false, \'YYYY-MM-DD\', \'".($search_vars['date_start'] ? $search_vars['date_start'] : NULL)."\', 1, \'date_start_holder".$this->popup_id."\', \'<span id=\"err1".$this->popup_id."\">From:</span>&nbsp;\')', 20);
											setTimeout('DateInput(\'date_end\', false, \'YYYY-MM-DD\', \'".($search_vars['date_end'] ? $search_vars['date_end'] : NULL)."\', 1, \'date_end_holder".$this->popup_id."\', \'<span id=\"err2".$this->popup_id."\">&nbsp;Thru:</span>&nbsp;\')', 41);";

										$tbl .= "
										</div>
									</fieldset>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<div style=\"float:right;text-align:right;padding-top:15px;margin-right:25px;\">
				".$this->form->button("value=Search", "id=primary", "onClick=submit_form($('popup_id').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_search_check_reg');")."
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
								".$this->form->button("value=Go", "onClick=agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'active_search='+\$F('saved_searches'));window.setTimeout('popup_windows[\'".$this->content['popup_controls']['popup_id']."\'].hide()', 1000);")."
								&nbsp;
								".$this->form->button("value=&nbsp;X&nbsp;", "title=Delete this saved search", "onClick=if(\$('saved_searches') && \$('saved_searches').options[\$('saved_searches').selectedIndex].value){if(confirm('Do you really want to delete your saved search?')){agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'purge_search', 's='+\$('saved_searches').options[\$('saved_searches').selectedIndex].value);\$('saved_searches').remove(\$('saved_searches').selectedIndex);}}")."
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

	function fetch_account_reg($account_hash) {

	}

	function account_reg() {
		$this->unlock("_");
		$this->load_class_navigation();
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

		$num_pages = ceil($this->check_total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ($end > $this->check_total)
			$end = $this->check_total;

		$order_by_ops = array("date"			=>	"check_register.check_date",
							  "payee"			=>	"check_register.payee",
							  "check_no"		=>	"check_register.check_no");

		$r = $this->db->query("SELECT accounts.account_hash , accounts.account_no , accounts.account_name
							   FROM `accounts`
							   WHERE accounts.account_type = 'CA'");


		$tbl .= ($this->active_search && $this->detail_search ? "
		<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
		<div style=\"padding-top:8px;padding-left:25px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg');\">Show All</a>]</div>" : NULL)."
		".$this->form->form_tag().$this->form->hidden(array('test' => 1))."
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"padding-left:10px;padding-top:0\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"4\" style=\"width:90%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:bottom;\" colspan=\"7\">
								<div style=\"float:right;font-weight:normal;padding-right:10px;text-align:right;\">".($this->check_total ? "
									".paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'accounting', 'check_reg', $order, $order_dir, "'active_search=".$this->active_search."'") : NULL)."
								</div>".($this->check_total ? "
								Showing ".($start_from + 1)." - ".($start_from + MAIN_PAGNATION_NUM > $this->check_total ? $this->check_total : $start_from + MAIN_PAGNATION_NUM)." of ".$this->check_total." Account Entries." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->p->ck(get_class($this), 'A', 'check_reg') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'edit_account_reg', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/new.gif\" title=\"New Entry\" border=\"0\" /></a>
									&nbsp;" : NULL)."
									<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'search_account_reg', 'popup_id=line_item');\"><img src=\"images/search.gif\" title=\"Search account register\" border=\"0\" /></a>
									&nbsp;
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"vertical-align:bottom;width:100px;\" rowspan=\"2\">Date</td>
							<td style=\"vertical-align:top;width:150px;\">Type</td>
							<td style=\"width:30%;\">Payee</td>
							<td style=\"vertical-align:bottom;\" rowspan=\"2\">Memo</td>
							<td style=\"vertical-align:bottom;padding-right:5px;width:115px;\" class=\"num_field\" rowspan=\"2\">Amount</td>
							<td style=\"vertical-align:bottom;padding-right:5px;width:115px;\" class=\"num_field\" rowspan=\"2\">Balance</td>
							<td style=\"vertical-align:bottom;padding-right:5px;width:30px;\" class=\"num_field\" rowspan=\"2\">Cleared</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"\">Reference</td>
							<td style=\"width:30%;\">Account</td>
						</tr>";
						for ($i = 0; $i < ($end - $start_from); $i++) {
							$b++;
							$tbl .= "
							<tr ".($this->check_register[$i]['void'] ? "style=\"color:#8f8f8f;font-style:italic;\" title=\"This check has been voided\"" : NULL).">
								<td style=\"vertical-align:bottom;\" style=\"text-align:left;\" rowspan=\"2\">".date(DATE_FORMAT, strtotime($this->account_reg[$i]['date']))."</td>
								<td >Type</td>
								<td >Payee</td>
								<td  rowspan=\"2\">Memo</td>
								<td  rowspan=\"2\" class=\"num_field\">Amount</td>
								<td  rowspan=\"2\" class=\"num_field\">Balance</td>
								<td  rowspan=\"2\" class=\"num_field\">Cleared</td>
							</tr>
							<tr>
								<td  >Reference</td>
								<td  >Account</td>
							</tr>".($b < ($end - $start_from) ? "
							<tr>
								<td style=\"background-color:#cccccc;\" colspan=\"7\"></td>
							</tr>" : NULL);
						}

						if (!$this->check_total)
							$tbl .= "
							<tr >
								<td colspan=\"7\">Account register is empty.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>".$this->form->close_form();

		$this->content['html']['place_holder'] = $tbl;

	}

	function check_reg() {
		$this->unlock("_");
		$this->load_class_navigation();

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

        $selected_checking_acct = fetch_user_data("last_check_reg_acct");
		$account_hash = $this->ajax_vars['account_hash'];
        if (!$account_hash)
            $account_hash = $selected_checking_acct;

		if (!$account_hash) {
            $r = $this->db->query("SELECT accounts.account_hash ,
                                   (
                                       SELECT check_register.account_hash
                                       FROM check_register
                                       ORDER BY obj_id DESC
                                       LIMIT 1
                                   ) AS last_account_used
                                   FROM accounts
                                   WHERE accounts.account_type = 'CA' AND accounts.checking = 1");
            $row = $this->db->fetch_assoc($r);
            if ($row['account_hash'])
                $account_hash = $row['account_hash'];
            else
                $account_hash = $row['last_account_used'];
        }

        if (!$this->active_search)
            $this->init_check_reg($account_hash);

		$num_pages = ceil($this->check_total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ($end > $this->check_total)
			$end = $this->check_total;

		$order_by_ops = array("check_date"			=>	"check_register.check_date",
							  "payee"				=>	"check_register.payee",
							  "obj_id"				=>	"check_register.obj_id",
							  "check_no"			=>	"check_register.check_no");

		if ($this->check_total)
		    $this->fetch_check_register($start_from, $end, ($order ? $order_by_ops[$order] : "check_register.obj_id"), ($order_dir ? $order_dir : "DESC"));

		$this->fetch_account_record(DEFAULT_AP_ACCT);
		$AP = $this->current_account['account_name'];
		unset($this->current_account, $this->account_hash);

		$r = $this->db->query("SELECT `account_no` , `account_name` , `account_hash`
		                       FROM accounts
		                       WHERE account_type = 'CA' AND checking = 1");
	    while ($row = $this->db->fetch_assoc($r))
	        $checking_accts[$row['account_hash']] = ($row['account_no'] ? $row['account_no']." : " : NULL).$row['account_name'];

	    $checking_acct_hash = array_keys($checking_accts);
        array_walk($checking_acct_hash, 'add_quotes', "'");
	    $r = $this->db->query("SELECT accounts.account_no , accounts.account_name , accounts.account_hash
	                           FROM check_register
	                           LEFT JOIN accounts ON accounts.account_hash = check_register.account_hash".(is_array($checking_acct_hash) ? "
	                           WHERE check_register.account_hash NOT IN (".implode(" , ", $checking_acct_hash).")" : NULL)."
	                           GROUP BY check_register.account_hash");
        while ($row = $this->db->fetch_assoc($r))
            $checking_accts[$row['account_hash']] = ($row['account_no'] ? $row['account_no']." : " : NULL).$row['account_name'];

        asort($checking_accts);
	    array_walk($checking_acct_hash, 'strip_quotes');

	    store_user_data('last_check_reg_acct', $account_hash);

        $this->content['jscript'][] = "
        var clear_timer = false;

        var clear_keys = new Hash();
        var private_clear_keys = new Hash();
        clearCheck = function() {
            var ck_no = arguments[0];
            var clear_flag = arguments[1];

            clear_keys.set(ck_no, (clear_flag == 1 ? 1 : 0));
            if (clear_flag == 1) {
                \$('clear_1_'+ck_no).show();
                \$('clear_2_'+ck_no).hide();
            } else {
                \$('clear_2_'+ck_no).show();
                \$('clear_1_'+ck_no).hide();
            }

            if (clear_timer == false)
                clear_timer = window.setTimeout('clearPost();', 1000);
        }
        clearPost = function() {
            private_clear_keys = clear_keys.clone();
            clear_keys = new Hash();

            agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'toggle_clear_flag', 'account_hash=".$account_hash."', private_clear_keys.toQueryString());

            clear_timer = false;
        }";

		$tbl .= ($this->active_search && $this->detail_search ? "
		<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
		<div style=\"padding-top:8px;padding-left:25px;font-weight:bold;\">
    		[<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'account_hash=".$this->ajax_vars['account_hash']."');\">Show All</a>]
        </div>" : NULL)."
		".$this->form->form_tag().$this->form->hidden(array('test' => 1))."
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\" id=\"asdf\">
			<tr>
				<td style=\"padding-left:10px;padding-top:0\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\" id=\"content_tbl\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:bottom;\" colspan=\"6\">
								<div style=\"float:right;font-weight:normal;padding-right:10px;text-align:right;\">".($this->check_total ? "
									".paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'accounting', 'check_reg', $order, $order_dir, "'active_search=".$this->active_search."', 'account_hash=".$this->selected_check_reg."'") : NULL).(!$this->active_search ? "
								    <div style=\"margin-top:5px;\">
								        Account: ".$this->form->select("account_hash",
		                                                                array_values($checking_accts),
		                                                                $account_hash,
		                                                                array_keys($checking_accts),
		                                                                "blank=1",
		                                                                "onChange=agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'account_hash='+this.options[this.selectedIndex].value)")."
								    </div>" : "&nbsp;")."
								</div>".($this->check_total ? "
								Showing ".($start_from + 1)." - ".($start_from + MAIN_PAGNATION_NUM > $this->check_total ? $this->check_total : $start_from + MAIN_PAGNATION_NUM)." of ".$this->check_total." Check Entries." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->p->ck(get_class($this), 'A', 'check_reg') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'edit_check_reg', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'account_hash=".$this->selected_check_reg."');\"><img src=\"images/new.gif\" title=\"New Entry\" border=\"0\" /></a>
									&nbsp;" : NULL)."
									<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'search_check_reg', 'popup_id=line_item', 'account_hash=".$this->selected_check_reg."', 'active_search=".$this->active_search."');\"><img src=\"images/search.gif\" title=\"Search check register\" border=\"0\" /></a>
									&nbsp;
									<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('reports', 'sf_loadcontent', 'show_popup_window', 'print_it_xls', 'type=check_reg', 'report_hash=".$this->report_hash."', 'popup_id=xls_win')\"><img src=\"images/excel.gif\" title=\"Export report into a spreadsheet.\" border=\"0\" /></a>
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\" id=\"trs\">
							<td style=\"vertical-align:bottom;width:150px;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'p=$p', 'order=check_no', 'order_dir=".($order == "check_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'account_hash=".$this->selected_check_reg."'".($this->active_search ? ", 'active_search=".$this->active_search."'" : NULL).");\" style=\"color:#ffffff;text-decoration:underline;\">
									Check No.</a>
								".($order == 'check_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"vertical-align:bottom;width:105px\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'p=$p', 'order=check_date', 'order_dir=".($order == "check_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'account_hash=".$this->selected_check_reg."'".($this->active_search ? ", 'active_search=".$this->active_search."'" : NULL).");\" style=\"color:#ffffff;text-decoration:underline;\">
									Date</a>
								".($order == 'check_date' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"vertical-align:bottom;\" id=\"payee_col\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'check_reg', 'p=$p', 'order=payee', 'order_dir=".($order == "payee" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'account_hash=".$this->selected_check_reg."'".($this->active_search ? ", 'active_search=".$this->active_search."'" : NULL).");\" style=\"color:#ffffff;text-decoration:underline;\">
									Payee</a>
								".($order == 'payee' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"vertical-align:bottom;\">Memo</td>
							<td style=\"vertical-align:bottom;padding-right:10px;width:175px\" class=\"num_field\">Amount</td>
							<td id=\"cleared_col\" style=\"vertical-align:bottom;padding-right:10px;width:50px\" class=\"num_field\">Cleared</td>
						</tr>";
						for ($i = 0; $i < ($end - $start_from); $i++) {
							$b++;
							$tbl .= "
							<tr ".($this->check_register[$i]['void'] ? "style=\"color:#8f8f8f;font-style:italic;\" title=\"This check has been voided\"" : NULL).">
								<td style=\"vertical-align:bottom;\" style=\"text-align:left;padding-left:10px;\" rowspan=\"2\">
									<table cellpadding=\"0\" cellspacing=\"0\">
										<tr>
											<td colspan=\"2\" style=\"font-weight:bold;\">".$this->check_register[$i]['check_no']."</td>
										</tr>
										<tr>
											<td style=\"padding-top:7px;\" nowrap>
												<div id=\"void_".$this->check_register[$i]['check_no']."\" style=\"padding-right:5px;visibility:".($this->check_register[$i]['void'] == 0 && $this->check_register[$i]['cleared'] == 0 ? "visible" : "hidden")."\">".($this->p->ck(get_class($this), 'L', 'check_reg') ? "
													<a href=\"javascript:void(0);\" onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'empty_queue', 'popup_id=line_item', 'preview=2', 'reprint=".$this->check_register[$i]['check_no']."', 'account_hash=".$this->check_register[$i]['account_hash']."', 'from=check_reg');\"><img src=\"images/print_sm.gif\" title=\"Reprint this check\" border=\"0\"/></a>" : NULL).($this->p->ck(get_class($this), 'D', 'check_reg') ? "
													<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to void this check? This action CANNOT be undone!')){submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_void', 'check_no=".$this->check_register[$i]['check_no']."', 'account_hash=".$this->check_register[$i]['account_hash']."', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'selected_check_reg=".$this->selected_check_reg."');}\"><img src=\"images/void_check.gif\" title=\"Void this check\" border=\"0\" /></a>" : NULL)."
												</div>
											</td>
											<td style=\"padding-top:7px;padding-left:5px;\">".($this->check_register[$i]['account_name'] ?
												($this->check_register[$i]['account_no'] ?
													$this->check_register[$i]['account_no']." - " : NULL).(strlen($this->check_register[$i]['account_name']) > 35 ?
														substr($this->check_register[$i]['account_name'], 0, 33)."..." : $this->check_register[$i]['account_name']) : "&nbsp;")."
											</td>
										</tr>
									</table>
								</td>
								<td style=\"vertical-align:bottom;\" style=\"text-align:left;\" >".date(DATE_FORMAT, strtotime($this->check_register[$i]['check_date']))."</td>
								<td style=\"vertical-align:bottom;\" style=\"text-align:left;\">".($this->check_register[$i]['total_invoices_paid'] ? "
									<a href=\"javascript:void(0);\" class=\"link_standard\" ".($this->check_register[$i]['void'] ? "style=\"color:#8f8f8f;\"" : NULL)." onClick=\"if(hide_row('".$this->check_register[$i]['check_no']."')){return;} else{agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'show_invoices_paid', 'check_no=".$this->check_register[$i]['check_no']."', 'account_hash=".$account_hash."');}\" title=\"View invoices paid by this check\">" : NULL).
										($this->check_register[$i]['customer_name'] ?
    										stripslashes($this->check_register[$i]['customer_name']) : stripslashes($this->check_register[$i]['vendor_name'])).($this->check_register[$i]['total_invoices_paid'] > 0 ?
									"</a>" : NULL)."
								</td>
								<td style=\"vertical-align:bottom;\" style=\"text-align:left;\" rowspan=\"2\">".$this->check_register[$i]['memo']."</td>
								<td style=\"vertical-align:bottom;padding-right:10px;vertical-align:bottom;\" class=\"num_field\" rowspan=\"2\">$".number_format($this->check_register[$i]['totalAmount'], 2)."</td>
								<td style=\"vertical-align:bottom;padding-right:10px;vertical-align:bottom;\" class=\"num_field\" rowspan=\"2\" id=\"cleared_".$this->check_register[$i]['check_no']."\">
	                                <div id=\"clear_1_".$this->check_register[$i]['check_no']."\" style=\"display:".($this->check_register[$i]['cleared'] ? "block" : "none")."\">
	                                    <img src=\"images/check.gif\" border=\"0\" ".($this->p->ck(get_class($this), 'E', 'check_reg') ? "title=\"Remove cleared flag from this check\" onMouseOver=\"this.style.cursor='pointer';\" onClick=\"clearCheck('".$this->check_register[$i]['check_no']."', 0);\"" : NULL)." />
	                                </div>
	                                <div id=\"clear_2_".$this->check_register[$i]['check_no']."\" style=\"display:".($this->check_register[$i]['cleared'] == 1 ? "none" : "block")."\">".
	                                    $this->form->checkbox("name=cleared_".$this->check_register[$i]['check_no'],
	                                                          "value=1",
	                                                          "onClick=if(!this.disabled){clearCheck('".$this->check_register[$i]['check_no']."', this.checked);this.checked=0;}",
	                                                          (!$this->p->ck(get_class($this), 'E', 'check_reg') || $this->check_register[$i]['void'] ? "disabled" : NULL))."
	                                </div>
								</td>
							</tr>
							<tr ".($this->check_register[$i]['void'] ? "style=\"color:#8f8f8f;font-style:italic;\" title=\"This check has been voided\"" : NULL).">
								<td colspan=\"2\">".($this->check_register[$i]['expense_account_name'] ?
									($this->check_register[$i]['expense_account_no'] ?
										$this->check_register[$i]['expense_account_no']." - " : NULL).(strlen($this->check_register[$i]['expense_account_name']) > 40 ?
											substr($this->check_register[$i]['expense_account_name'], 0, 37)."..." : $this->check_register[$i]['expense_account_name']) : "<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'refresh_form', 'doit_search_journal', 'audit_id=".$this->check_register[$i]['audit_id']."')\" class=\"link_standard\" ".($this->check_register[$i]['void'] ? "style=\"color:#8f8f8f;\"" : NULL)." title=\"View journal details\">SPLIT</a>")."
								</td>
							</tr>".($this->check_register[$i]['total_invoices_paid'] ? "
							<tr>
								<td colspan=\"6\"><div id=\"invoice_list_".$this->check_register[$i]['check_no']."\" style=\"float:right;display:none;\"></div></td>
							</tr>" : NULL).($b < ($end - $start_from) ? "
							<tr>
								<td style=\"background-color:#cccccc;\" colspan=\"6\"></td>
							</tr>" : NULL);
						}

						if (!$this->check_total)
							$tbl .= "
							<tr >
								<td colspan=\"6\">Check register is empty.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>".
		$this->form->close_form();

        $this->content['jscript'][] = "
        hide_row = function() {
            var check_no = arguments[0];
            var tbl_el = $('invoice_list_' + check_no).childElements();

            if (tbl_el.length) {
                var i=0, c;
                while (c = tbl_el[i++])
                   c.remove();

                $('invoice_list_'+check_no).setStyle({display: 'none'});
                return true;
            }

            return false;
        }
        pos_reg_el = function() {
            var el = arguments[0];
            var pos_x = \$('content_tbl').getWidth() - \$('payee_col').positionedOffset().toString().substring(0, \$('payee_col').positionedOffset().toString().indexOf(', '));
            var pos_rt = \$('cleared_col').getWidth();
            pos_x -= pos_rt;

            \$(el).setStyle({width: pos_x + 'px', marginRight: pos_rt + 'px'});
        }";

		$this->content['html']['place_holder'] = $tbl;

	}

	function bank_rec_prompt() {
		if ($_SESSION['bank_rec'] && !$this->ajax_vars['bank_rec_adj']) {
		    $this->doit_bank_rec();
		    return;
		}

		$this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Reconcile Bank Accounts";
		$this->content['popup_controls']['popup_height'] = "275px";
		$change_acct = $this->ajax_vars['change_acct'];

		$r = $this->db->query("SELECT account_hash , account_no , account_name
		                       FROM `accounts`
		                       WHERE `account_type` = 'CA' AND `checking` = 1");
		while ($row = $this->db->fetch_assoc($r))
		    $accounts[$row['account_hash']] = ($row['account_no'] ? $row['account_no']." - " : NULL).$row['account_name'];

		if ($this->ajax_vars['account_hash'])
		    $account_hash = $this->ajax_vars['account_hash'];
		elseif (!$account_hash && defined('DEFAULT_CASH_ACCT'))
		    $account_hash = DEFAULT_CASH_ACCT;
		elseif (!$account_hash && $accounts) {
		    reset($accounts);
			$account_hash = key($accounts);
		}
		$this->fetch_account_record($account_hash);

		$r = $this->db->query("SELECT accounts.account_no , accounts.account_name , accounts.account_hash ,
		                       account_types.type
		                       FROM `accounts`
		                       LEFT JOIN `account_types` ON account_types.type_code = accounts.account_type
		                       ORDER BY accounts.account_no , accounts.account_name ASC");
		while ($row = $this->db->fetch_assoc($r))
		    $account_values[$row['account_hash']] = ($row['account_no'] ? $row['account_no']." : " : NULL).$row['account_name']."&nbsp;&nbsp;&nbsp;&nbsp;".$row['type'];

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("popup_id" => $this->popup_id))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\">";
		    $inner_tbl = "
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>".($this->current_account ? "
			<h4 style=\"margin-bottom:5px;color:#00477f;\">".($this->current_account['account_no'] ?
		        $this->current_account['account_no']." - " : NULL).$this->current_account['account_name']."
		    </h4>" : NULL).($accounts ? "
		    <div style=\"margin-top:0;margin-bottom:10px;margin-left:20px;\" id=\"err_1a\">
                Bank Account: ".$this->form->select("account_hash",
		                                             array_values($accounts),
		                                             $account_hash,
		                                             array_keys($accounts),
		                                             "blank=1",
		                                             "onChange=agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'bank_rec_prompt', 'account_hash='+this.options[this.selectedIndex].value, 'change_acct=1', 'bank_rec_adj=1', 'popup_id=".$this->popup_id."')")."
		    </div>" : NULL)."
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"table_body\">
				<tr>
					<td class=\"smallfont\" style=\"text-align:center;background-color:#ffffff;vertical-align:top;padding-top:10px;width:100%;\">".($this->current_account ? "
						<div style=\"margin-bottom:15px;width:80%;\">
							<div style=\"padding-top:10px;padding-bottom:10px;\">
								<div style=\"font-weight:bold;margin-top:5px;margin-bottom:5px;\">
								    What is your statement date &amp; balance?
								</div>
							    <table style=\"width:75%;background-color:#efefef;border:1px solid #cccccc;\" >
									<tr>
										<td style=\"padding-top:10px;\">
											<span id=\"err_1\">When is your statement dated?</span>
											<div style=\"margin-top:10px;\" id=\"statement_date_holder".$this->popup_id."\"></div>
										</td>
									</tr>
									<tr>
										<td style=\"padding-top:10px;padding-bottom:10px;\">
											<span id=\"err_2\">What is the ending balance<br />as shown in your statement?</span>
											<div style=\"margin-top:10px;\">
												".$this->form->text_box("name=ending_balance",
																		"value=".($_SESSION['bank_rec'][$this->account_hash]['general']['ending_balance'] ? number_format($_SESSION['bank_rec'][$this->account_hash]['general']['ending_balance'], 2) : NULL),
																		"onBlur=if(this.value){this.value=formatCurrency(this.value);}",
																		"style=text-align:right;background-color:#ffffff;",
																		"size=15")."
											</div>
										</td>
									</tr>
								</table>
							</div>
						</div>
						<div style=\"margin-bottom:15px;width:80%;\">
							<div style=\"font-weight:bold;margin-top:5px;margin-bottom:5px;\">Does your statement show any bank service charges?</div>
							<table style=\"width:75%;background-color:#efefef;border:1px solid #cccccc;\" cellpadding=\"2\" cellspacing=\"5\" >
								<tr>
									<td style=\"width:40%;vertical-align:top;\">
										<span id=\"err_3\">Service Charge:</span>
										<div style=\"margin-top:10px;\">
											".$this->form->text_box("name=service_charge",
																	"value=".($_SESSION['bank_rec'][$this->account_hash]['service_charge']['amount'] ? number_format($_SESSION['bank_rec'][$this->account_hash]['service_charge']['amount'], 2) : NULL),
																	"onBlur=if(this.value){this.value=formatCurrency(this.value);}",
																	"style=text-align:right;background-color:#ffffff;",
																	"size=10")."
										</div>
									</td>
									<td style=\"vertical-align:top;text-align:left;\">
										<span id=\"err_4\">Date:</span>
										<div style=\"margin-top:7px;\" id=\"service_charge_date_holder".$this->popup_id."\"></div>
									</td>
								</tr>
								<tr>
									<td colspan=\"2\" style=\"padding-bottom:10px;\">
										<span id=\"err_5\">Expense Account:</span>
										<div style=\"margin-top:10px;\">".
											$this->form->select("service_charge_account",
																 array_values($account_values),
																 ($_SESSION['bank_rec'][$this->account_hash]['service_charge']['account_hash'] ? $_SESSION['bank_rec'][$this->account_hash]['service_charge']['account_hash'] : NULL),
																 array_keys($account_values),
																 'style=width:300px')."
									    </div>
									</td>
								</tr>
							</table>
							<div style=\"font-weight:bold;margin-top:15px;margin-bottom:5px;\">Does your statement show any interest earned?</div>
							<table style=\"width:75%;background-color:#efefef;border:1px solid #cccccc;\" cellpadding=\"2\" cellspacing=\"1\" >
								<tr>
									<td style=\"padding-top:15px;width:40%;vertical-align:top;background-color:#efefef\">
										<span id=\"err_6\">Interest Earned</span>
										<div style=\"margin-top:10px;margin-left:15px;\">
											".$this->form->text_box("name=interest_earned",
																	"value=".($_SESSION['bank_rec'][$this->account_hash]['interest_earned']['amount'] ? number_format($_SESSION['bank_rec'][$this->account_hash]['interest_earned']['amount'], 2) : NULL),
																	"onBlur=if(this.value){this.value=formatCurrency(this.value);}",
																	"style=text-align:right;background-color:#ffffff;",
																	"size=10")."
										</div>
									</td>
									<td style=\"padding-top:15px;vertical-align:top;text-align:left;\">
										<span id=\"err_7\">Date:</span>
										<div style=\"margin-top:7px;\" id=\"interest_earned_date_holder".$this->popup_id."\"></div>
									</td>
								</tr>
								<tr>
									<td colspan=\"2\" style=\"padding-bottom:10px;\">
										<span id=\"err_8\">Interest Account:</span>
                                        <div style=\"margin-top:10px;\">".
                                            $this->form->select("interest_earned_account",
                                                                 array_values($account_values),
                                                                 ($_SESSION['bank_rec'][$this->account_hash]['interest_earned']['account_hash'] ? $_SESSION['bank_rec'][$this->account_hash]['interest_earned']['account_hash'] : NULL),
                                                                 array_keys($account_values),
                                                                 'style=width:300px')."
                                        </div>
									</td>
								</tr>
							</table>
						</div>
						<div style=\"margin-top:15px;margin-bottom:10px;\">
                            ".$this->form->button("value=Continue", "onClick=submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_bank_rec', 'prompt=1');")."
						</div>" : "<img src=\"images/alert.gif\" />
						          &nbsp;You have no checking accounts defined! Please make sure you checking accounts are indicated as
						          checking accounts by selecting the checkbox within the appropriate chart of account.")."
					</td>
				</tr>
			</table>";
		$tbl_close = "
		</div>";
		$jscript[] = "setTimeout('DateInput(\'statement_date\', \'true\', \'YYYY-MM-DD\', \'".($_SESSION['bank_rec'][$this->account_hash]['general']['statement_date'] ? $_SESSION['bank_rec'][$this->account_hash]['general']['statement_date'] : date("Y-m-d"))."\', 1, \'statement_date_holder".$this->popup_id."\')', 45)";
		$jscript[] = "setTimeout('DateInput(\'service_charge_date\', \'false\', \'YYYY-MM-DD\', \'".($_SESSION['bank_rec'][$this->account_hash]['service_charge']['date'] ? $_SESSION['bank_rec'][$this->account_hash]['service_charge']['date'] : date("Y-m-d"))."\', 1, \'service_charge_date_holder".$this->popup_id."\')', 55)";
		$jscript[] = "setTimeout('DateInput(\'interest_earned_date\', \'false\', \'YYYY-MM-DD\', \'".($_SESSION['bank_rec'][$this->account_hash]['interest_earned']['date'] ? $_SESSION['bank_rec'][$this->account_hash]['interest_earned']['date'] : date("Y-m-d"))."\', 1, \'interest_earned_date_holder".$this->popup_id."\')', 55)";

        $this->content["jscript"] = $jscript;
		if ($change_acct)
		    $this->content['html']['main_table'.$this->popup_id] = $inner_tbl;
		else
		    $this->content['popup_controls']["cmdTable"] = $tbl.$inner_tbl.$tbl_close;
		return;
	}

	function doit_bank_rec() {
	    $prompt = $_POST['prompt'];
	    $account_hash = $_POST['account_hash'];
	    if (!$account_hash) {
	        reset($_SESSION['bank_rec']);
	        if (count($_SESSION['bank_rec']) > 1) {
	            while (list($account_hash, $a) = each($_SESSION['bank_rec']))
	            	$t[$account_hash] = $a['timestamp'];

	            arsort($t);
	            reset($t);
	            $account_hash = key($t);
	        } else
	            $account_hash = key($_SESSION['bank_rec']);
	    }
	    $statement_date = $_POST['statement_date'];
	    list($year, $month, $day) = explode("-", $statement_date);
	    $ending_balance = preg_replace('/[^-.0-9]/', "", $_POST['ending_balance']);
        $bank_rec = array();

        if ($_SESSION['bank_rec'][$account_hash] && !$_POST['bank_rec_adj'])
            $this->content['html']['place_holder'] = $this->bank_reconcile($_SESSION['bank_rec'][$account_hash]);

	    if ($prompt == 1) {
	    	//Service Charge
	    	$service_charge = preg_replace('/[^-.0-9]/', "", $_POST['service_charge']);
	    	$service_charge_date = $_POST['service_charge_date'];
	    	list($svc_year, $svc_month, $svc_day) = explode("-", $service_charge_date);
	    	$service_charge_account = $_POST['service_charge_account'];
	    	//Interest Earned
	    	$interest_earned = preg_replace('/[^-.0-9]/', "", $_POST['interest_earned']);
	    	$interest_earned_date = $_POST['interest_earned_date'];
	    	list($int_year, $int_month, $int_day) = explode("-", $interest_earned_date);
	    	$interest_earned_account = $_POST['interest_earned_account'];

	    	if ($account_hash && $statement_date && $ending_balance && checkdate($month, $day, $year)) {
	    	    $bank_rec['general'] = array('statement_date'     =>  $statement_date,
	    	                                 'ending_balance'     =>  $ending_balance,
	    	                                 'account_hash'       =>  $account_hash);

	    		if ($service_charge > 0 && ((!$service_charge_date  || !checkdate($svc_month, $svc_day, $svc_year) || !$service_charge_account) || strtotime($service_charge_date) > strtotime($statement_date))) {
	    		    $this->content['error'] = 1;
	    		    if (!$service_charge_date) {
	    		        $this->content['form_return']['err']['err_4'] = 1;
	    		        $this->content['form_return']['feedback'][] = "You failed to enter a valid date for the service charge, as found on your bank statement.";
	    		    } elseif (!checkdate($svc_month, $svc_day, $svc_year)) {
                        $this->content['form_return']['err']['err_4'] = 1;
                        $this->content['form_return']['feedback'][] = "The date you entered for the service charge is not valid. Please choose a valid date.";
	    		    } elseif (!$service_charge_account) {
	    		    	$this->content['form_return']['err']['err_5'] = 1;
	    		    	$this->content['form_return']['feedback'][] = "In order to record your service charge, please choose a valid expense account in which to classify it.";
	    		    } elseif (strtotime($service_charge_date) > strtotime($statement_date)) {
                        $this->content['form_return']['err']['err_4'] = 1;
                        $this->content['form_return']['feedback'][] = "Please make sure the date you enter for the service charge is within the date range of your statement closing date.";
	    		    }
	    		} else
	    		    $bank_rec['service_charge'] = array('amount'         =>  $service_charge,
	    		                                        'date'           =>  $service_charge_date,
	    		                                        'account_hash'   =>  $service_charge_account);

	    		if ($interest_earned > 0 && ((!$interest_earned_date || !$interest_earned_account) || strtotime($interest_earned_date) > strtotime($statement_date))) {
	    		    $this->content['error'] = 1;
                    if (!$interest_earned_date) {
                        $this->content['form_return']['err']['err_7'] = 1;
                        $this->content['form_return']['feedback'][] = "You failed to enter a valid date for the interest earned, as found on your bank statement.";
                    } elseif (!checkdate($int_month, $int_day, $int_year)) {
                        $this->content['form_return']['err']['err_7'] = 1;
                        $this->content['form_return']['feedback'][] = "The date you entered for the interest earned is not valid. Please choose a valid date.";
                    } elseif (!$interest_earned_account) {
                        $this->content['form_return']['err']['err_8'] = 1;
                        $this->content['form_return']['feedback'][] = "In order to record your interest earned, please choose a valid expense account in which to classify it.";
                    } elseif (strtotime($interest_earned_date) > strtotime($statement_date)) {
                        $this->content['form_return']['err']['err_7'] = 1;
                        $this->content['form_return']['feedback'][] = "Please make sure the date you enter for the interest earned is within the date range of your statement closing date.";
                    }
	    		} else
	    		    $bank_rec['interest_earned'] = array('amount'        =>  $interest_earned,
	    		                                         'date'          =>  $interest_earned_date,
	    		                                         'account_hash'  =>  $interest_earned_account);

	    		$bank_rec['timestamp'] = time();
	    		if ($this->content['error'])
	    		    return;

    		    $_SESSION['bank_rec'][$account_hash] = $bank_rec;
    		    $this->content['action'] = 'close';


	    		$this->content['html']['place_holder'] = $this->bank_reconcile($bank_rec);
	    		return;
	    	} else {
	    	    $this->content['error'] = 1;
	    	    if (!$account_hash) {
	    	    	$this->content['form_return']['err']['err_1a'] = 1;
	    	        $this->content['form_return']['feedback'][] = "Please select a valid checking account in which to reconcile.";
	    	    }
	    	    if (!$statement_date) {
	    	    	$this->content['form_return']['err']['err_1'] = 1;
	    	        $this->content['form_return']['feedback'][] = "Please make sure to include a valid statement date.";
	    	    }
	    	    if (!checkdate($month, $day, $year)) {
                    $this->content['form_return']['err']['err_1'] = 1;
                    $this->content['form_return']['feedback'][] = "The statement date you entered is invalid, please select a valid statement date.";
	    	    }
	    	    if (!$ending_balance) {
	    	    	$this->content['form_return']['err']['err_2'] = 1;
	    	        $this->content['form_return']['feedback'][] = "Please enter a valid ending balance as found on your bank statement.";
	    	    }

	    	    return;
	    	}
	    }
	}

	function amount_tracker() {
		$account_hash = $_POST['account_hash'];
        $money_in = $_POST['reconcile']['money_in'];
        $money_out = $_POST['reconcile']['money_out'];
        $opening_balance = $_POST['opening_balance'];
        $statement_ending_balance = $_POST['ending_balance'];
        $_SESSION['bank_rec'][$account_hash]['money_out'] = array();
        $_SESSION['bank_rec'][$account_hash]['money_in'] = array();

        $total_in = $total_out = 0;
        while (list($key, $amount) = each($money_in)) {
            $total_in += $amount;
            $_SESSION['bank_rec'][$account_hash]['money_in'][] = $key;
        }
        while (list($key, $amount) = each($money_out)) {
            $total_out += $amount;
            $_SESSION['bank_rec'][$account_hash]['money_out'][] = $key;
        }

        $closing_balance = $opening_balance + $total_in - $total_out;
        $cleared_difference = $statement_ending_balance - $closing_balance;

        $this->content['html']['closing_balance'] = ($closing_balance < 0 ? "($".number_format($closing_balance * -1, 2).")" : "$".number_format($closing_balance, 2));
        $this->content['html']['cleared_difference'] = ($cleared_difference < 0 ? "($".number_format($cleared_difference * -1, 2).")" : "$".number_format($cleared_difference, 2));
        $this->content['html']['total_in'] = ($total_in < 0 ? "($".number_format($total_in, 2).")" : "$".number_format($total_in, 2));
        $this->content['html']['total_out'] = ($total_out < 0 ? "($".number_format($total_out, 2).")" : "$".number_format($total_out, 2));
        $this->content['action'] = 'continue';
	}

	function bank_reconcile($bank_rec=NULL) {
		if ($account_hash = $bank_rec['general']['account_hash'])
		    $valid = $this->fetch_account_record($account_hash);

		$statement_date = $bank_rec['general']['statement_date'];
		$rand_id = array();
        /*
		//Start with the customer payment table
		$r = $this->db->query("SELECT customer_payment.payment_hash , customer_payment.receipt_amount , customer_invoice.invoice_no ,
		                      customer_payment.receipt_date , customer_payment.receipt_type ,
		                      customer_payment.check_no , customer_payment.cleared ,
		                       CASE
		                       WHEN customer_invoice.invoice_to = 'C'
		                           THEN customers.customer_name
		                           ELSE vendors.vendor_name
		                       END AS payee
		                       FROM customer_payment
                               LEFT JOIN customer_invoice ON customer_invoice.invoice_hash = customer_payment.invoice_hash
		                       LEFT JOIN customers ON customers.customer_hash = customer_invoice.customer_hash
                               LEFT JOIN vendors ON vendors.vendor_hash = customer_invoice.customer_hash
		                       WHERE customer_payment.receipt_date <= '".$statement_date."' AND customer_payment.account_hash = '".$this->account_hash."' AND customer_payment.reconciled = 0
		                       ORDER BY customer_payment.receipt_date ASC");
		while ($row = $this->db->fetch_assoc($r)) {
		    $money_in[strtotime($row['receipt_date'])][] = array('date'           =>  $row['receipt_date'],
		                                                         'amount'         =>  $row['receipt_amount'],
		                                                         'record_hash'    =>  $row['payment_hash'],
		                                                         'invoice_hash'   =>  $row['invoice_hash'],
		                                                         'invoice_no'     =>  $row['invoice_no'],
		                                                         'reference'      =>  $row['check_no'],
		                                                         'payee'          =>  $row['payee'],
		                                                         'table'          =>  'customer_payment',
		                                                         'type'           =>  'Customer Payment',
		                                                         'comments'       =>  $row['comments'],
		                                                         'cleared'        =>  $row['cleared']);
		    $record_hash[] = $row['payment_hash'];
		    $total_in += $row['receipt_amount'];
		}
		/*
        //Vendor payment table
        $r = $this->db->query("SELECT vendor_payment.payment_hash , vendor_payment.payment_amount , vendor_payment.posting_date ,
                               vendor_payment.check_no , vendor_payment.timestamp ,
                               vendor_payment.cleared , vendor_payment.void ,
                               CASE
                               WHEN vendor_payables.type = 'R'
                                   THEN customers.customer_name
                                   ELSE vendors.vendor_name
                               END AS payee ,
                               (
                                   SELECT check_register.check_date
                                   FROM check_register
                                   WHERE check_register.check_no = vendor_payment.check_no AND check_register.account_hash = '".$this->account_hash."'
                               ) AS check_date ,
                               (
                                   SELECT check_register.cleared
                                   FROM check_register
                                   WHERE check_register.check_no = vendor_payment.check_no AND check_register.account_hash = '".$this->account_hash."'
                               ) AS check_cleared ,
                                (
                                   SELECT check_register.void
                                   FROM check_register
                                   WHERE check_register.check_no = vendor_payment.check_no AND check_register.account_hash = '".$this->account_hash."'
                               ) AS check_void
                               FROM vendor_payment
                               LEFT JOIN vendor_payables ON vendor_payables.invoice_hash = vendor_payment.invoice_hash
                               LEFT JOIN vendors ON vendors.vendor_hash = vendor_payables.vendor_hash
                               LEFT JOIN customers ON customers.customer_hash = vendor_payables.vendor_hash
                               WHERE vendor_payment.posting_date <= '".$statement_date."' AND vendor_payment.account_hash = '".$this->account_hash."' AND vendor_payment.check_no != 0
                               ORDER BY vendor_payment.posting_date ASC");
        while ($row = $this->db->fetch_assoc($r))
        	$check_no[$row['check_no']][] = $row;

        while (list($check_no, $row) = each($check_no)) {
        	$check_total = 0;
        	for ($i = 0; $i < count($row); $i++)
        	    $check_total +=	$row['payment_amount'];

            $money_out[strtotime($row['posting_date'])][] = array('date'          =>  ($row['posting_date'] ? $row['posting_date'] : date("Y-m-d", $row['timestamp'])),
                                                                  'check_date'    =>  $row['check_date'],
                                                                 'amount'         =>  $check_total,
                                                                 'check_no'       =>  $check_no,
                                                                 'reference'      =>  $row['check_no'],
                                                                 'void'           =>  $row['check_void'],
                                                                 'payee'          =>  $row['payee'],
                                                                 'type'           =>  'Vendor Payment',
                                                                 'cleared'        =>  $row['check_cleared']);
            $total_out += $check_total;
        }
        */
        //General Journal
        $r = $this->db->query("SELECT journal.* ,
                               CASE
                               WHEN !ISNULL(journal.customer_hash) OR !ISNULL(journal.vendor_hash)
                                   THEN CASE
                                        WHEN !ISNULL(journal.customer_hash)
                                            THEN (
	                                            SELECT customers.customer_name
	                                            FROM customers
	                                            WHERE customers.customer_hash = journal.customer_hash
                                            )
                                            ELSE (
                                                SELECT vendors.vendor_name
                                                FROM vendors
                                                WHERE vendors.vendor_hash = journal.vendor_hash
                                            )
                                        END
                                   ELSE NULL
                               END AS payee
                               FROM journal
                               WHERE journal.account_hash = '".$this->account_hash."' AND journal.date <= '$statement_date'
                               ORDER BY journal.date ASC , journal.obj_id ASC");
        while ($row = $this->db->fetch_assoc($r)) {
            /*if ($row['debit'] > 0) {
                $money_in[strtotime($row['date'])][] = array('date'           =>  $row['date'],
                                                             'amount'         =>  $row['debit'],
                                                             'record_hash'    =>  $row['journal_hash'],
                                                             'reference'      =>  $row['check_no'],
                                                             'payee'          =>  $row['payee'],
                                                             'table'          =>  'journal',
                                                             'type'           =>  'General Journal '.($row['trans_type'] != 'GJ' ?
                                                                                        $this->trans_type[$row['trans_type']] : NULL),
                                                             'comments'       =>  $row['memo'],
                                                             'cleared'        =>  $row['cleared']);
                $total_in += $row['debit'];
            }
			*/
            if ($row['credit'] > 0) {
            	if ($row['ap_payment_hash'] && $row['check_no'])
            	    $check_no[$row['check_no']][] = $row;
            	else {
	                $money_out[strtotime($row['date'])][] = array('date'           =>  $row['date'],
	                                                              'amount'         =>  $row['credit'],
	                                                              'record_hash'    =>  $row['journal_hash'],
	                                                              'reference'      =>  $row['check_no'],
	                                                              'table'          =>  'journal',
	                                                              'payee'          =>  $row['payee'],
	                                                              'type'           =>  'General Journal '.($row['trans_type'] != 'GJ' ?
	                                                                                        $this->trans_type[$row['trans_type']] : NULL),
	                                                              'comments'       =>  $row['memo'],
	                                                              'cleared'        =>  $row['cleared']);
	                $total_out += $row['credit'];
            	}
            }
        }
        if (is_array($check_no)) {
            while (list($ck, $row) = each($check_no)) {
                for ($i = 0; $i < count($row); $i++)
                	$check_total += $row['credit'];

                $money_out[strtotime($row['date'])][] = array('date'        =>      $row[0]['date'],
                                                              'amount'      =>      $check_total,
                                                              'check_no'    =>      $ck,
                                                              'type'        =>      ($row[0]['ap_payment_hash'] ? "Bill Payment" : "Check"),
                                                              'cleared'     =>      $row[0]['cleared']);
            }
        }

        if ($bank_rec['service_charge']['amount']) {
            $money_out[strtotime($bank_rec['service_charge']['date'])][] = array('date'          =>  $bank_rec['service_charge']['date'],
			                                                                      'amount'        =>  $bank_rec['service_charge']['amount'],
			                                                                      'account_hash'  =>  $bank_rec['service_charge']['account_hash'],
			                                                                      'reference'     =>  'SVC CHRG',
			                                                                      'type'          =>  'Misc',
			                                                                      'auto'          =>  1);
            $total_out += $bank_rec['service_charge']['amount'];
        }

        if ($bank_rec['interest_earned']['amount']) {
            $money_in[strtotime($bank_rec['interest_earned']['date'])][] = array('date'          =>  $bank_rec['interest_earned']['date'],
			                                                                      'amount'        =>  $bank_rec['interest_earned']['amount'],
			                                                                      'account_hash'  =>  $bank_rec['interest_earned']['account_hash'],
			                                                                      'reference'     =>  'INTEREST',
			                                                                      'type'          =>  'Misc',
			                                                                      'auto'          =>  1);
            $total_in += $bank_rec['interest_earned']['amount'];
        }
        if (is_array($money_in))
            ksort($money_in, SORT_NUMERIC);
        if (is_array($money_out))
            ksort($money_out, SORT_NUMERIC);

        $opening_balance = 0;
        if ($_SESSION['bank_rec'][$this->account_hash])
        if ($past_dates = fetch_sys_var('bank_reconcile')) {
            $past_dates = __unserialize(stripslashes($past_dates));
            if (is_array($past_dates[$this->account_hash])) {
                sort($past_dates[$this->account_hash], SORT_NUMERIC);
                $current_statement_date = strtotime($statement_date);
                for ($i = 0; $i < count($past_dates[$this->account_hash]); $i++) {
                    if ($past_dates[$this->account_hash][$i] < $current_statement_date && (!$past_dates[$this->account_hash][$i+1] || $past_dates[$this->account_hash][$i+1] >= $current_statement_date)) {
                    	$last_statement_date = date("Y-m-d", $past_dates[$this->account_hash][$i]);
                    	break;
                    }
                }
            }
        }
        if ($last_statement_date) {
            $r = $this->db->query("SELECT SUM(journal.debit) - SUM(journal.credit) AS total
                                   FROM journal
                                   WHERE journal.account_hash = '".$this->account_hash."' AND journal.date <= '$last_statement_date'");
            $opening_balance = $this->db->result($r, 0, 'total');
        }
        $closing_balance = $opening_balance;

        $tbl = $this->form->form_tag().
        $this->form->hidden(array('account_hash'    =>  $this->account_hash,
                                  'opening_balance' =>  $opening_balance,
                                  'ending_balance'  =>  $bank_rec['general']['ending_balance'],
                                  'statement_date'  =>  $statement_date)).($this->account_hash ? "
        <h3 style=\"margin-bottom:5px;color:#00477f;\" >
            ".($this->current_account['account_no'] ?
                $this->current_account['account_no']." : " : NULL).$this->current_account['account_name']."
        </h3>" : "<img src=\"images/alert.gif\" />&nbsp;No Account Selected!")."
        <div style=\"margin-bottom:10px;margin-left:20px;\">
            [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'bank_rec_prompt', 'bank_rec_adj=1', 'popup_id=back_rec_win');\">change account</a></small>]
        </div>
        <table cellpadding=\"0\" cellspacing=\"3\" width=\"100%\">
            <tr>
                <td style=\"padding-left:10px;padding-top:0\">
                    <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
                        <tr>
                            <td style=\"vertical-align:bottom;\">
                                <div style=\"font-weight:bold;margin-bottom:5px;margin-top:5px;\">
                                    Deposits and Other Debits
                                    &nbsp;:&nbsp;".($total_in ? "
                                    $".number_format($total_in, 2) : NULL)."
                                </div>
			                    <table cellspacing=\"1\" cellpadding=\"4\" style=\"width:90%;font:10pt verdana, geneva;background-color:#cccccc\">
                                    <tr style=\"font-weight:bold;background-color:#efefef;\">
                                        <td style=\"width:15px;vertical-align:bottom;\">".$this->form->checkbox("onClick=checkall($$('input[tag=checkbox_in]'), this.checked);submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'action=amount_tracker')")."</td>
                                        <td style=\"vertical-align:bottom;width:10%\">Date</td>
                                        <td style=\"vertical-align:bottom;width:15%\">Type</td>
                                        <td style=\"vertical-align:bottom;width:20%\">Reference</td>
                                        <td style=\"vertical-align:bottom;width:45%\">Payee</td>
                                        <td style=\"vertical-align:bottom;width:10%;text-align:center;\" class=\"num_field\">Amount</td>
                                    </tr>";
                            reset($money_in);
                            while (list($date, $array) = each($money_in)) {
                                for ($i = 0; $i < count($array); $i++) {
                                    if ($array[$i]['cleared'] || $array[$i]['type'] == 'Misc' || in_array($array[$i]['record_hash']."|".$array[$i]['table'], $_SESSION['bank_rec'][$this->account_hash]['money_in']))
                                        $reconciled_in += $array[$i]['amount'];

                                	$tbl .= "
                                	<tr>
                                	    <td style=\"background-color:#ffffff;\">".
                                	        $this->form->checkbox("name=reconcile[money_in][".$array[$i]['record_hash']."|".$array[$i]['table']."]",
                                	                              "value=".$array[$i]['amount'],
                                	                              "tag=checkbox_in",
                                	                              "onClick=submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'action=amount_tracker')",
                                	                              ($array[$i]['cleared'] || $array[$i]['type'] == 'Misc' || in_array($array[$i]['record_hash']."|".$array[$i]['table'], $_SESSION['bank_rec'][$this->account_hash]['money_in']) ? "checked" : NULL))."
                                	    </td>
                                	    <td style=\"background-color:#ffffff;\">".date(DATE_FORMAT, strtotime($array[$i]['date']))."</td>
                                	    <td style=\"background-color:#ffffff;\">".$array[$i]['type']."</td>
                                        <td style=\"background-color:#ffffff;\">".($array[$i]['table'] == 'journal' && !$array[$i]['reference'] ?
                                	                                               (strlen($array[$i]['comments']) > 45 ?
                                	                                                   substr($array[$i]['comments'], 0, 43)."..." : $array[$i]['comments']) : $array[$i]['reference'])."
                                	    </td>
                                        <td style=\"background-color:#ffffff;\">".$array[$i]['payee']."</td>
                                        <td style=\"background-color:#ffffff;\" class=\"num_field\">$".number_format($array[$i]['amount'], 2)."</td>
                                	</tr>";
                                }
                            }
                            $closing_balance += $reconciled_in;

                            $tbl .= "
                                    <tr style=\"background-color:#efefef;\">
                                        <td colspan=\"5\" style=\"text-align:right;font-weight:bold;\">
                                            Total Reconciled:
                                        </td>
                                        <td style=\"text-align:right;font-weight:bold;\">
                                            <div id=\"total_in\">".($reconciled_in < 0 ? "
                                                ($".number_format($reconciled_in * -1, 2).")" : "$".number_format($reconciled_in, 2))."
                                            </div>
                                        </td>
                                    </tr>
			                    </table>
                            </td>
                        </tr>
                        <tr>
                            <td style=\"vertical-align:bottom;\">
                                <div style=\"font-weight:bold;margin-bottom:5px;margin-top:20px;\">
                                    Checks and Payments
                                    &nbsp;:&nbsp;".($total_out ? "
                                    $".number_format($total_out, 2) : NULL)."
                                </div>
                                <table cellspacing=\"1\" cellpadding=\"4\" style=\"width:90%;font:10pt verdana, geneva;background-color:#AAC8C8\">
                                    <tr style=\"font-weight:bold;background-color:#efefef\">
                                        <td style=\"width:15px;vertical-align:bottom;\">".$this->form->checkbox("onClick=checkall($$('input[tag=checkbox_out]'), this.checked);submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'action=amount_tracker')")."</td>
                                        <td style=\"vertical-align:bottom;width:10%\">Date</td>
                                        <td style=\"vertical-align:bottom;width:15%\">Type</td>
                                        <td style=\"vertical-align:bottom;width:20%\">Reference</td>
                                        <td style=\"vertical-align:bottom;width:45%\">Payee</td>
                                        <td style=\"vertical-align:bottom;width:10%;text-align:center;\" class=\"num_field\">Amount</td>
                                    </tr>";
                            reset($money_out);
                            while (list($date, $array) = each($money_out)) {
                                for ($i = 0; $i < count($array); $i++) {
                                	if ($array[$i]['cleared'] || @in_array($array[$i]['record_hash']."|".$array[$i]['table'], $_SESSION['bank_rec'][$this->account_hash]['money_out']))
                                	    $reconciled_out += $array[$i]['amount'];

                                    $tbl .= "
                                    <tr>
                                        <td style=\"background-color:#ffffff;\">".
                                            $this->form->checkbox("name=reconcile[money_out][".$array[$i]['record_hash']."|".$array[$i]['table']."]",
                                                                  "value=".$array[$i]['amount'],
                                                                  "tag=checkbox_out",
                                                                  "onClick=submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'action=amount_tracker')",
                                                                  ($array[$i]['cleared'] || $array[$i]['type'] == 'Misc' || @in_array($array[$i]['record_hash']."|".$array[$i]['table'], $_SESSION['bank_rec'][$this->account_hash]['money_out']) ? "checked" : NULL))."
                                        <td style=\"background-color:#ffffff;\">".date(DATE_FORMAT, strtotime($array[$i]['date']))."</td>
                                        <td style=\"background-color:#ffffff;\">".$array[$i]['type']."</td>
                                        <td style=\"background-color:#ffffff;\">".($array[$i]['table'] == 'journal' && !$array[$i]['reference'] ?
                                                                                   (strlen($array[$i]['comments']) > 45 ?
                                                                                       substr($array[$i]['comments'], 0, 43)."..." : $array[$i]['comments']) : $array[$i]['reference'])."</td>
                                        <td style=\"background-color:#ffffff;\">".$array[$i]['payee']."</td>
                                        <td style=\"background-color:#ffffff;\" class=\"num_field\">$".number_format($array[$i]['amount'], 2)."</td>
                                    </tr>";
                                }
                            }
                            $closing_balance -= $reconciled_out;

                            $tbl .= "
                                    <tr style=\"background-color:#efefef;\">
                                        <td colspan=\"5\" style=\"text-align:right;font-weight:bold;\">
                                            Total Reconciled:
                                        </td>
                                        <td style=\"text-align:right;font-weight:bold;\">
                                            <div id=\"total_out\">".($reconciled_out < 0 ? "
                                                ($".number_format($reconciled_out * -1, 2).")" : "$".number_format($reconciled_out, 2))."
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div style=\"margin-top:15px;\">
                                    <table cellspacing=\"1\" cellpadding=\"4\">
                                        <tr>
                                            <td style=\"text-align:right;\">Opening Balance:</td>
                                            <td>".($opening_balance < 0 ?
                                                "($".number_format($opening_balance * -1, 2).")" : "$".number_format($opening_balance, 2))."
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:right;\">Statement Ending Date:</td>
                                            <td>".date(DATE_FORMAT, strtotime($bank_rec['general']['statement_date']))."</td>
                                            <td style=\"border-left:1px solid #cccccc;\" rowspan=\"2\">
                                                <div style=\"margin-left:5px;\">
                                                    [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'bank_rec_prompt', 'bank_rec_adj=1', 'popup_id=back_rec_win');\" class=\"link_standard\">edit</a></small>]
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:right;\">Statement Ending Balance:</td>
                                            <td>".($bank_rec['general']['ending_balance'] < 0 ?
                                                "($".number_format($bank_rec['general']['ending_balance'], 2).")" : "$".number_format($bank_rec['general']['ending_balance'], 2))."
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:right;\">Closing Balance:</td>
                                            <td><div id=\"closing_balance\">".($closing_balance < 0 ?
                                                "($".number_format($closing_balance * -1, 2).")" : "$".number_format($closing_balance, 2))."
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:right;font-weight:bold\">Difference:</td>
                                            <td><div id=\"cleared_difference\">".($bank_rec['general']['ending_balance'] - $closing_balance < 0 ?
                                                "($".number_format(($bank_rec['general']['ending_balance'] - $closing_balance) * -1, 2).")" : "$".number_format($bank_rec['general']['ending_balance'] - $closing_balance, 2))."
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        ".$this->form->close_form();

        return $tbl;
	}

    /**
     * Search chart of accounts by account number, account name, or account type.  Implemented for trac #265.
     *
     */
    function search_accounts() {
        global $stateNames, $states;

        $date = date("U") - 3600;
        $result = $this->db->query("DELETE FROM `search`
                                    WHERE `timestamp` <= '$date'");

        $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Search Accounts";
        $this->content['focus'] = 'account_no';

        if ($this->ajax_vars['active_search']) {
            $result = $this->db->query("SELECT `search_str`
                                        FROM `search`
                                        WHERE `search_hash` = '".$this->ajax_vars['active_search']."'");
            $row = $this->db->fetch_assoc($result);
            $search_vars = permissions::load_search_vars($row['search_str']);
            $this->active_search = $this->ajax_vars['active_search'];
        }

        list($search_name, $search_hash) = $this->fetch_searches('accounts');

        $tbl .= $this->form->form_tag().
        $this->form->hidden(array('popup_id'        => $this->content['popup_controls']['popup_id'],
                                  'detail_search'   => 1
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
                            Filter your accounts search criteria below:
                        </div>
                        <table>
                            <tr>
                                <td style=\"padding-left:15px;\">
                                    <fieldset>
                                        <legend>Account Number</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=account_no", "value=".$search_vars['account_no'], "size=15", "maxlength=12")."</div>
                                    </fieldset>
                                </td>
                                <td style=\"padding-left:15px;\">
                                    <fieldset>
                                        <legend>Account Name</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=account_name", "value=".$search_vars['account_name'], "size=35", "maxlength=255")."</div>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;vertical-align:top;\">
                                    <fieldset>
                                        <legend>Account Types</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">".$this->form->select("account_types[]", array("Current Assets", "Long Term Assets", "Current Liabilities", "Long Term Liabilities", "Equity", "Income", "Expenses"), $search_vars['account_types'], array("CA", "FA", "CL", "LL", "EQ", "IN", "EX"), "multiple=multiple", "size=6", "cols=5", "blank=1")."</div>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <div style=\"float:right;text-align:right;padding-top:15px;margin-right:25px;\">
                ".$this->form->button("value=Search", "id=primary", "onClick=submit_form($('popup_id').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_search_accounts');")."
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
								".$this->form->button("value=Go", "onClick=agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'active_search='+\$F('saved_searches'));window.setTimeout('popup_windows[\'".$this->content['popup_controls']['popup_id']."\'].hide()', 1000);")."
								&nbsp;
								".$this->form->button("value=&nbsp;X&nbsp;", "title=Delete this saved search", "onClick=if(\$('saved_searches') && \$('saved_searches').options[\$('saved_searches').selectedIndex].value){if(confirm('Do you really want to delete your saved search?')){agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'purge_search', 's='+\$('saved_searches').options[\$('saved_searches').selectedIndex].value);\$('saved_searches').remove(\$('saved_searches').selectedIndex);}}")."
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


    /**
     * Performs the posting and database queries for searching accounts. Implemented for trac #265.
     *
     */
    function doit_search_accounts() {
        $account_no = $_POST['account_no'];
        $account_name = $_POST['account_name'];
        $account_types = $_POST['account_types'];
        if (!is_array($account_types) && $account_types)
            $account_types = array($account_types);

        if (!$account_types[0])
            unset($account_types);
        $save = $_POST['page_pref'];
        $detail_search = $_POST['detail_search'];

        $str = "account_no=$account_no|account_name=$account_name|account_types=".@implode("&", $account_types);
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
            if ($account_no)
                $sql_p[] = "t1.account_no LIKE '$account_no%'";
            if ($account_name)
                $sql_p[] = "t1.account_name LIKE '%$account_name%'";
            if (is_array($account_types) && count($account_types)) {
                $account_types = array_unique($account_types);
                $account_types = array_values($account_types);
                array_walk($account_types, 'add_quotes', "'");
                $sql_p[] = "t1.account_type IN (".implode(" , ", $account_types).")";
                array_walk($account_types, 'strip_quotes');
            }

            $save = $_POST['save'];
            $search_name = $_POST['search_name'];
            if (!$search_name)
                unset($save);

            if ($sql_p)
                $sql = implode(" AND ", $sql_p);

            $s = array('sql'    =>  $sql);

            $search_hash = md5(global_classes::get_rand_id(32, "global_classes"));
            while (global_classes::key_exists('search', 'search_hash', $search_hash))
                $search_hash = md5(global_classes::get_rand_id(32, "global_classes"));

            $this->db->query("INSERT INTO `search`
                              (`timestamp` , `search_hash` , `saved` , `search_name` , `search_class` , `user_hash` , `detail_search` , `query` , `total` , `search_str`)
                              VALUES (".time()." , '$search_hash' , '$save' , '$search_name' , 'accounts' , '".$this->current_hash."' , '$detail_search' , '".base64_encode(serialize($s))."' , '$total' , '$str')");

            $this->active_search = $search_hash;
        }

        $this->content['action'] = "close";
        $this->content['jscript_action'] = "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'active_search=".$this->active_search."', 'detail_search=$detail_search');";
        return;
    }

	/**
	 * Performs the posting and database queries for searching accounts. Implemented for trac #265.
	 *
	 */
	function showall() {

		$this->unlock("_");
		$this->load_class_navigation();

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];
		$this->detail_search = $this->ajax_vars['detail_search'];

		$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ( $end > $this->total )
			$end = $this->total;

		if ( $this->active_search )
			$this->page_pref =& $this->search_vars;

		if ( $order && ! $order_by_ops[$order] )
    		unset($order);

		$order_by_ops = array(
    		"timestamp"			=>	"t1.timestamp",
			"account_name"		=>	"t1.account_name",
			"account_no"		=>	"t1.account_no",
			"account_type"		=>	"t1.account_type, t1.account_no, t1.account_name"
		);

		$this->fetch_accounts($start_from, $end, ( $order ? $order_by_ops[$order] : $order_by_ops['account_type'] ), $order_dir);
		list($type, $code) = $this->fetch_account_types();

		$tbl =
		( $this->active_search && $this->detail_search ?
    		"<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
    		<div style=\"padding-top:8px;padding-left:25px;font-weight:bold;margin-bottom:15px;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'showall');\">Show All</a>]</div>" : NULL
		) . "
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"padding:15px;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:bottom;\" colspan=\"4\">
								<div style=\"float:right;font-weight:normal;padding-right:10px;text-align:right;\">" .
                        		( $this->total ?
									paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'accounting', 'showall', $order, $order_dir, "'active_search={$this->active_search}', 'popup_id={$this->popup_id}', 'show=" . ( is_array($show) ? implode("|", $show) : $show ) . "'") : NULL
								) . "
								</div>" .
								( $this->total ?
    								"Showing " . ( $start_from + 1 ) . " - " .
    								( $start_from + MAIN_PAGNATION_NUM > $this->total ?
        								$this->total : $start_from + MAIN_PAGNATION_NUM
        							) . " of {$this->total} Accounts." : NULL
        						) . "
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
	        						( $this->p->ck(get_class($this), 'A', 'accounts') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'edit_account', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/new.gif\" title=\"Create a new account\" border=\"0\" /></a>&nbsp;" : NULL
	        						) . "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'search_accounts', 'popup_id=line_item', 'active_search={$this->active_search}');\"><img src=\"images/search.gif\" title=\"Search for an account\" border=\"0\" /></a>&nbsp;
									<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('reports', 'sf_loadcontent', 'show_popup_window', 'print_it_xls', 'type=accounts', 'popup_id=xls_accounts', 'title=Chart of Accounts', 'active_search={$this->active_search}');\"><img src=\"images/excel.gif\" title=\"Export chart of accounts into a spreadsheet.\" border=\"0\" /></a>
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"width:125px;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=account_no', 'order_dir=".($order == "account_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'show=".(is_array($show) ? implode("|", $show) : $show)."');\" style=\"color:#ffffff;text-decoration:underline;\">Account No.</a>" .
								( $order == 'account_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
							</td>
							<td style=\"vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=account_name', 'order_dir=".($order == "account_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'show=".(is_array($show) ? implode("|", $show) : $show)."');\" style=\"color:#ffffff;text-decoration:underline;\">Account Name</a>" .
								( $order == 'account_name' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
							</td>
							<td style=\"width:250px;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=account_type', 'order_dir=".($order == "account_type" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'show=".(is_array($show) ? implode("|", $show) : $show)."');\" style=\"color:#ffffff;text-decoration:underline;\">Account Type</a>" .
								( ! $order || $order == 'account_type' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
    							) . "
							</td>
							<td style=\"vertical-align:bottom;padding-right:10px;\" class=\"num_field\">Balance</td>
						</tr>";

                        $border = "border-bottom:1px solid #cccccc;";

						reset($this->account_info);
						for ( $i = 0; $i < ( $end - $start_from ); $i++ ) {

							$account_info = current($this->account_info);
							$b++;

							if ( $b >= ( $end - $start_from ) )
                                unset($border);

							$tbl .= "
							<tr " . ( $this->p->ck(get_class($this), 'V', 'accounts') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'edit_account', 'account_hash=" . key($this->account_info) . "', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"" : NULL ) . ">
								<td style=\"vertical-align:bottom;text-align:left;padding-left:10px;$border;\" >" .
								( $account_info['account_info']['account_no'] ?
    								$account_info['account_info']['account_no'] : "&nbsp;"
								) . "
								</td>
								<td style=\"vertical-align:bottom;text-align:left;$border;\" >" . stripslashes($account_info['account_info']['account_name']) . "</td>
								<td style=\"vertical-align:bottom;text-align:left;$border;\" >{$account_info['account_info']['type_name']}</td>
								<td style=\"vertical-align:bottom;padding-right:10px;$border;\" class=\"num_field\" >" .
								( bccomp($account_info['account_info']['balance'], 0, 2) ?
									( bccomp($account_info['account_info']['balance'], 0, 2) == -1 ?
										"<span style=\"color:#ff0000;\">(\$" . number_format( bcmul($account_info['account_info']['balance'], -1, 2), 2) . ")</span>" : "\$" . number_format($account_info['account_info']['balance'], 2)
									) : "&nbsp;"
								) . "
								</td>
							</tr>";

							if ( $account_info['children'] ) {

								$total_balance = $account_info['account_info']['balance'];

								for ( $j = 0; $j < count($account_info['children']); $j++ ) {

									$total_balance = bcadd($total_balance, $account_info['children'][$j]['child_account_balance'], 2);

									$tbl .= "
									<tr " . ( $this->p->ck(get_class($this), 'V', 'accounts') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'edit_account', 'account_hash={$account_info['children'][$j]['child_account_hash']}', 'popup_id=line_item');\"" : NULL ) . " >
										<td style=\"vertical-align:bottom;$border;text-align:left;padding-left:20px;\" >
											<img src=\"images/tree_down1.gif\" />&nbsp;
											{$account_info['children'][$j]['child_account_no']}
										</td>
										<td style=\"vertical-align:bottom;$border;text-align:left;padding-left:30px;\" >" . stripslashes($account_info['children'][$j]['child_account_name']) . "</td>
										<td style=\"vertical-align:bottom;$border;text-align:left;\" ></td>
										<td style=\"vertical-align:bottom;padding-right:10px;$border;\" class=\"num_field\" >" .
										( bccomp($account_info['children'][$j]['child_account_balance'], 0, 2) ?
											( bccomp($account_info['children'][$j]['child_account_balance'], 0, 2) == -1 ?
												"(\$" . number_format( bcmul($account_info['children'][$j]['child_account_balance'], -1, 2), 2) . ")" : "\$" . number_format($account_info['children'][$j]['child_account_balance'], 2)
											) : "&nbsp;"
										) . "
										</td>
									</tr>";
								}

								if ( bccomp($total_balance, 0, 2) )
									$tbl .= "
									<tr>
										<td colspan=\"3\" " . ( $border ? "style=\"$border;\"" : NULL ) . ">&nbsp;</td>
										<td style=\"padding-right:10px;$border;\" class=\"num_field\">
											<div style=\"border-top:1px dashed #000000;padding-top:5px;width:100px;\">" .
        									( bccomp($total_balance, 0, 2) == -1 ?
												"(\$" . number_format( bcmul($total_balance, -1, 2), 2) . ")" : "\$" . number_format($total_balance, 2)
        									) . "
											</div>
										</td>
									</tr>";

							}

							next($this->account_info);
						}

						if ( ! $this->total )
							$tbl .= "
							<tr >
								<td colspan=\"4\">You have no accounts defined.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		$this->content['html']['place_holder'] = $tbl;
	}

	function period_ck($date) {
		list($y, $m, $d) = explode('-', $date);
		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `period_dates`
									WHERE `fiscal_year` = '$y' AND '$date' BETWEEN `period_start` AND `period_end` AND `closed` = 1");
		return $this->db->result($result);
	}

	function reverse_trans($account_hash, $debit=0, $credit=0) {
		if ($debit == 0 && $credit == 0)
			return 0;

		$this->fetch_account_record($account_hash);
		$account_type = $this->current_account['account_action'];

		switch($account_type) {
			case 'DR':
			if ($debit > 0)
				return $debit * -1;
			elseif ($credit > 0)
				return $credit;
			break;

			case 'CR':
			if ($credit > 0)
				return $credit * -1;
			elseif ($debit > 0)
				return $debit;
			break;
		}

	}

	function exec_trans($audit_id, $date, $account_hash, $amount, $type, $memo=NULL, $force=NULL, $force_side=NULL) {

		if ( ( ! bccomp($amount, 0, 4) || ! $amount ) && ! $force )
			return true;

		if ( ! $date ) {

			$this->err = array(
				'errno'  =>  'DC998',
				'err'    =>  'Invalid date parameter provided during transaction posting'
			);
			return false;
		}

		if ( $this->fetch_account_record($account_hash) ) {

	        $account_type = $this->current_account['account_action'];
	        if ( ! $account_type ) {

	        	$this->err = array(
    	        	'errno'	=>	'DC997',
	        		'err'	=>	"Chart of account configuration error. Unable to identify account type. <!-- Account lookup: [ $account_hash ] -->"
	        	);
	        	return false;
	        }

	        if ( float_length($amount) > 4 )
                $amount = _round($amount, 4);

			$debit = $credit = 0;

			if ( bccomp($amount, 0, 4) || $force ) {

				if ( $account_type == 'DR' ) {

					if ( bccomp($amount, 0, 4) == 1 )
						$debit = $amount;
					else
						$credit = bcmul($amount, -1, 4);
				} else {

					if ( bccomp($amount, 0, 4) == 1 )
						$credit = $amount;
					else
						$debit = bcmul($amount, -1, 4);
				}
			}

			if ( $this->transaction ) {

				$this->transaction_exec['dr'] = $this->transaction_exec['dr'] + $debit;
				$this->transaction_exec['cr'] = $this->transaction_exec['cr'] + $credit;

				if ( defined('DEBUG') )
					$this->transaction_exec['track'][] = array(
    					'account_hash'   =>  $this->current_account['account_hash'],
					    'account_name'   =>  $this->current_account['account_name'],
					    'account_type'   =>  $this->current_account['account_type'],
					    'debit'          =>  $debit,
					    'credit'         =>  $credit,
					    'memo'           =>  $memo
					);
			}

			$journal_hash = rand_hash('journal', 'journal_hash');
			$cols = array(
                'date'              =>  $date,
                'proposal_hash'     =>  NULL,
                'ar_invoice_hash'   =>  NULL,
                'ar_credit_hash'    =>  NULL,
                'ap_invoice_hash'   =>  NULL,
                'customer_hash'     =>  NULL,
                'vendor_hash'       =>  NULL,
                'check_no'          =>  NULL,
                'ar_payment_hash'   =>  NULL,
                'ap_payment_hash'   =>  NULL,
                'audit_id'          =>  $audit_id,
                'item_hash'         =>  NULL,
                'account_hash'      =>  $account_hash,
                'trans_type'        =>  $type,
                'memo'              =>  addslashes($memo),
                'currency'          =>  NULL,
                'exchange_rate'     =>  NULL
			);

			while ( list($col, $val) = each($cols) ) {

				if ( ( $val === NULL || ! $val ) && $this->acct_ops[ $col ] )
					$cols[ $col ] = $this->acct_ops[ $col ];
				elseif ( ! $val )
					unset( $cols[ $col ] );
			}

			if ( bccomp($debit, 0, 4) )
				$cols['debit'] = $debit;
			elseif ( bccomp($credit, 0, 4) )
				$cols['credit'] = $credit;

			$keys = array_keys($cols);
			$vals = array_values($cols);

			array_walk($keys, 'add_quotes', "`");
			array_walk($vals, 'add_quotes', "'");

			for ( $i = 0; $i < count($vals); $i++ ) {

				if ( $vals[$i] == "'NULL'" )
					unset($vals[$i], $keys[$i]);
			}

			if ( $this->debug ) {

                $this->debug_trans[] = array(
                    'account'          =>  "{$this->current_account['account_no']} - {$this->current_account['account_name']}",
                    'dr'               =>  $debit,
                    'cr'               =>  $credit,
                    'memo'             =>  $memo,
                    'type'             =>  $type,
                    'item_hash'        =>  $this->acct_ops['item_hash'],
                    'ar_invoice_hash'  =>  $this->acct_ops['ar_invoice_hash']
                );
			}

			if ( ! $this->db->query("INSERT INTO journal
    								 (
	    								 timestamp,
	    								 id_hash,
	    								 journal_hash,
	    								 " . implode(", ", array_values($keys) ) . "
    								 )
    								 VALUES
    								 (
	    								 UNIX_TIMESTAMP(),
	    								 '{$this->current_hash}',
	    								 '$journal_hash',
	    								 " . implode(", ", array_values($vals) ) . "
    								 )")
            ) {

                $this->err = array (
                    'errno' =>  "M{$this->db->db_errno}",
                    'err'   =>  $this->db->db_error
                );

                $this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__);
                return false;
            }

			if ( $this->current_account['account_side'] == 'P' ) {

				if ( ! $this->update_profit($audit_id, $date, $debit, $credit, $cols) ) {

					$this->__trigger_error("Unable to update journal transaction profit entry.", E_USER_ERROR, __FILE__, __LINE__);
					$this->err = array(
						'errno'    =>  'DCC996',
						'err'      =>  'System error encountered during transaction profit update. Journal transaction failed.'
					);

					return false;
				}

			}

			return true;

		} else {

			$this->err = array(
    			'errno'	=>	'DC1300',
				'err'	=>	"Invalid chart of account parameter provided during transaction posting. <!-- Tried to select account hash [ $account_hash ] -->"
			);
			return false;
		}
	}

	function update_profit($audit_id, $date, $debit=0, $credit=0, $value_array=NULL) {

		if ( defined('DEFAULT_PROFIT_ACCT') && $this->fetch_account_record(DEFAULT_PROFIT_ACCT) ) {

			$value_array['debit'] = $value_array['credit'] = 0;

			if ( bccomp($debit, 0, 4) == 1 )
				$value_array['debit'] = $debit;
			elseif ( bccomp($credit, 0, 4) == 1 )
				$value_array['credit'] = $credit;

			$journal_hash = rand_hash('journal', 'journal_hash');

			$value_array['auto'] = 1;
			$value_array['date'] = $date;
			$value_array['journal_hash'] = $journal_hash;
			$value_array['audit_id'] = $audit_id;
			$value_array['account_hash'] = DEFAULT_PROFIT_ACCT;

			$keys = array_keys($value_array);
			$vals = array_values($value_array);

			array_walk($keys, 'add_quotes', "`");
			array_walk($vals, 'add_quotes', "'");

            if ( $this->debug ) {

                $this->debug_trans[] = array(
                    'account'          =>  'PROFIT',
                    'dr'               =>  $value_array['debit'],
                    'cr'               =>  $value_array['credit'],
                    'memo'             =>  $value_array['memo'],
                    'type'             =>  $type,
                    'item_hash'        =>  $value_array['item_hash'],
                    'ar_invoice_hash'  =>  $value_array['ar_invoice_hash']
                );
            }

			if ( ! $this->db->query("INSERT INTO journal
								    (
									    timestamp,
									    id_hash,
									    " . implode(", ", array_values($keys) ) . "
								    )
    								VALUES
    								(
	    								UNIX_TIMESTAMP(),
	    								'{$this->current_hash}',
	    								" . implode(", ", array_values($vals) ) . "
    								)")
			 ) {

			 	$this->__trigger_error("{$this->db->errno} - {$this->db->error}", E_DATABASE_ERROR, __FILE__, __LINE__);
                $this->err = array (
                    'errno' =>  "M{$this->db->db_errno}",
                    'err'   =>  $this->db->db_error
                );

                return false;
			}

			return true;

		} else {

			$this->__trigger_error("Account Lookup Error: " . ( defined('DEFAULT_PROFIT_ACCT') ? "Unable to fetch default profit account. <!-- Tried fetching account [ " . DEFAULT_PROFIT_ACCT . "] -->" : "Missing constant definition. DEFAULT_PROFIT_ACCT has not been defined." ), E_USER_ERROR, __FILE__, __LINE__);
			$this->err = array(
    			'errno'	=>	'DC1301',
				'err'	=>	'Unable to fetch valid chart of account. Tried to select account hash [' . DEFAULT_PROFIT_ACCT . ']'
			);

			return false;
		}
	}

	function audit_log($audit_id, $record_hash=NULL, $id_hash=NULL, $title=NULL) {
		return;
	}

	function fetch_journal_entries($audit_id, $item_hash=NULL) {

		$result = $this->db->query("SELECT t1.*
									FROM journal t1
									WHERE t1.audit_id = '$audit_id' AND t1.auto = 0 " .
									( $item_hash ?
										"AND t1.item_hash = '$item_hash'" : "AND ISNULL( t1.item_hash )"
									));
		while ( $row = $this->db->fetch_assoc($result) )
			$entry[] = $row;

		return $entry;
	}

	function doit_add_line() {
		$line_int = $_POST['line_int'];
		$this->content['html']["line_holder".$this->popup_id] = $this->new_journal_entry($line_int);
		$this->content['action'] = 'continue';
		return;
	}

	function doit_import_csv() {

		$import_from = $_POST['import_from'];
		$proposal_hash = $_POST['proposal_hash'];
		$file_name = $_POST['file_name'];
		$file_type = $_POST['file_type'];
		$file_size = $_POST['file_size'];
		$file_error = $_POST['file_error'];
		$csv_format = $_POST['csv_format'];
		$journal_popup_id = $_POST['journal_popup_id'];

		if ( $file_name && ! $file_error && strtolower( strrev( substr( strrev( $file_name ), 0, strpos( strrev( $file_name ), '.' ) ) ) ) == 'csv' ) {

            if ( $csv_format == 1 )
                $col_count = 4;
            elseif ( $csv_format == 2 )
                $col_count = 6;
            elseif ( $csv_format == 3 )
                $col_count = 7;

			if ( $file_name ) {
				$warnings = $errors = 0;

				$row = 0;
				$fh = fopen($file_name, "r");
				while ( ( $data = fgetcsv($fh, 1000, ",") ) !== FALSE ) {

					$d++;
					$num = count($data);
					if ( $row == 0 ) {

						if ( $csv_format == 1 && ( $num < 3 || $num > 4 ) ) {

							$this->content['error'] = 1;
							$this->content['form_return']['feedback'] = "$num The CSV file is not valid! The CSV file should contain at least 3 columns, and not more than 4 columns. Column format should be as follows: <br />(1) Account No/Name, (2) Debit, (3) Credit, (4) Memo <i>optional</i>.";
							break;
						}
                        elseif ( $csv_format == 2 && ( $num < 5 || $num > 6 ) ) {

                            $this->content['form_return']['feedback'] = "The CSV file is not valid! The CSV file should contain at least 5 columns, and not more than 6 columns. Column format should be as follows: <br />(1) Account No/Name, (2) Opening, (3) Debit, (4) Credit, (5) Closing, (6) Memo <i>optional</i>.";
                            $this->content['error'] = 1;
                            break;
                        }
                        elseif ( $csv_format == 3 && ( $num < 6 || $num > 7 ) ) {

                            $this->content['form_return']['feedback'] = "The CSV file is not valid! The CSV file should contain at least 6 columns, and not more than 7 columns. Column format should be as follows: <br />(1) Account No/Name, (2) Opening, (3) Debit, (4) Credit, (5) Current, (6) Closing, (7) Memo <i>optional</i>.";
                            $this->content['error'] = 1;
                            break;
                        }
					}

                    $account_info = array();
                    unset($account_no, $account_name, $delimeter);
					if ( trim( $data[0] ) ) {

						for ( $c = 0; $c < $col_count; $c++ ) {

							unset($amount); # Validate if we're working with a float field
							if ( $c > 0 ) {

	                            $matches = array();
	                            if ( preg_match('/^\$?\s?(-?[0-9]*)\.?([0-9]{0,4})?$/', trim( str_replace(', ', '', $data[$c]) ), $matches) ) {

	                                $amount = ( $matches[1] ? $matches[1] : 0 ) . '.' . ( $matches[2] ? $matches[2] : 0 );
	                                $amount = (float)$amount;
	                            }
							}

							# Account number
							if ( $c == 0 ) {

								unset($account_info['memo']);
                                $matches = array();
								if ( preg_match('/^([0-9\.]*)?\s?([\s\-\:]?)\s?(.*)$/', trim( $data[0] ), $matches) ) {

                                    $account_no = $matches[1];
                                    $delimeter = $matches[2];
                                    $account_name = $matches[3];

                                    $result = $this->db->query("SELECT t1.account_hash , t1.account_name , t1.account_no, t2.account_type
                                                                FROM accounts t1
                                                                LEFT JOIN " . "account_types t2 ON t2.type_code = t1.account_type
                                                                WHERE " .
                                                                ( $account_no ?
                                                                    "t1.account_no = '$account_no' " .
                                                                    ( $account_name ?
                                                                        "OR " : NULL
                                                                    ) : NULL
                                                                ) .
                                                                ( $account_name ?
                                                                    "t1.account_name LIKE '$account_name%' " .
                                                                    ( $delimeter ?
                                                                        "OR " : NULL
                                                                    ) : NULL
                                                                ) .
                                                                ( $delimeter ?
                                                                    "t1.account_no = '{$account_no}{$delimeter}{$account_name}'" : NULL
                                                                ) );
                                    if ( $num_rows = $this->db->num_rows( $result ) ) {

                                    	if ( $num_rows > 1 )
                                            $warnings++;

                                        $db_row = $this->db->fetch_assoc( $result );
                                        $account_info['account'] = array(
                                            "account_name"  =>  $db_row['account_name'],
                                            "account_hash"  =>  $db_row['account_hash'],
                                            "account_no"    =>  $db_row['account_no'],
                                            "matches"       =>  $num_rows
                                        );

                                    } else {

                                        $errors++;
                                        $account_info['account'] = array(
                                            "error"         =>  1,
                                            "account_name"  =>  $account_name,
                                            "account_no"    =>  $account_no
                                        );
                                    }
                                }

                            # (1) Debit (2&3) opening balance
							} elseif ( $c == 1 ) {

								if ( $amount ) {

	                                if ( $csv_format == 1 )
	                                    $account_info['dr'] = $amount;
	                                else
	                                	$account_info['open'] = $amount;

								}

                            # (1) Credit (2&3) debit
							} elseif ( $c == 2 ) {

                                if ( $amount ) {

                                    if ( $csv_format == 1 )
                                        $account_info['cr'] = $amount;
                                    else
                                    	$account_info['dr'] = $amount;

                                }

                            # (1) Memo (2&3) credit
							} elseif ( $c == 3 ) {

                                if( $csv_format == 1 )
                                    $account_info['memo'] = trim( $data[3] );
                                else if ( $amount )
                                    $account_info['cr'] = $amount;

                            # (2) Closing balance (3) current balance
							} elseif ( $c == 4 ) {

                                if ( $amount ) {

                                    if ( $csv_format == 2 )
                                        $account_info['close'] = $amount;
                                    elseif ( $csv_format == 3 )
                                        $account_info['current'] = $amount;

                                }

                            # (2) Memo (3) Closing balance
                            } elseif ( $c == 5 ) {

                                //if ( $amount ) {

                                    if ( $csv_format == 2 )
                                        $account_info['memo'] = trim( $data[5] );
                                    elseif ( $csv_format == 3 )
                                        $account_info['close'] = $amount;

                                //}

                            # (3) Memo
                            } elseif ( $c == 6 ) {

                            	if ( $csv_format == 3 )
                                    $account_info['memo'] = trim( $data[6] );

                            }

							$csv[$row] = $account_info;
						}
					} else {

						# General error
					}

					$row++;
				}

				if ( $this->content['error'] ) {

					$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?proposal_hash=123&class=accounting&action=doit_import_csv&current_hash=".$this->current_hash."&popup_id=".$this->popup_id."', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
					return;
				}

				$tbl =
				( $warnings || $errors ? "
                    <div style=\"margin-top:10px;margin-bottom:10px;\">
                        <img src=\"images/alert.gif\" />&nbsp;
                        Multiple errors and/or warnings have been found during your CSV import.
                        <br />
                        Acounts highlighted in <span style=\"color:#FF0000\">red</span> indicate accounts that could not be confidently matched.
                        <br />
                        Accounts highlighted in <span style=\"color:#FF9900;\">orange</span> indicate accounts that were with multiple matches.
                        <br /><br />
                        Please correct any errors and warnings before continuing.
                    </div>" : NULL
				) . "
				<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:97%;\" >
					<tr style=\"font-weight:bold;background-color:#efefef;font-size:8pt\">
						<td style=\"border-bottom:1px solid #cccccc;width:153px;\">Account</td>
						<td style=\"border-bottom:1px solid #cccccc;width:77px;text-align:right;\">Debit</td>
						<td style=\"border-bottom:1px solid #cccccc;width:77px;text-align:right;\">Credit</td>
						<td style=\"border-bottom:1px solid #cccccc;padding-left:10px;width:185px;\">Memo</td>
						<td style=\"border-bottom:1px solid #cccccc;\">Customer/Vendor</td>
					</tr>";

				$csv = array_values($csv);
                $total_csv = count($csv);
				$total_dr = $total_cr = 0;

				for ( $i = 0; $i < count($csv); $i++ ) {

					if ( bccomp($csv[$i]['cr'], 0, 4) == -1 )
                        $csv[$i]['cr']= bcmul($csv[$i]['cr'], -1, 4);

					$csv_dr = bcadd($csv_dr, $csv[$i]['dr'], 4);
					$csv_cr = bcadd($csv_cr, $csv[$i]['cr'], 4);

					if ( $csv[$i]['account'] && ( bccomp(0, $csv[$i]['dr'], 2) || bccomp(0, $csv[$i]['cr'], 2) ) ) {

						if ( bccomp($csv[$i]['dr'], 0, 2) == -1 )
                            $csv[$i]['dr'] = bcmul($csv[$i]['dr'], -1, 2);
                        if ( bccomp($csv[$i]['cr'], 0, 2) == -1 )
                            $csv[$i]['cr'] = bcmul($csv[$i]['cr'], -1, 2);

						$tbl .= "
						<tr >
							<td style=\"vertical-align:bottom;\" nowrap>".
								$this->form->text_box(
    								"name=account_name[$i]",
									"id=err{$i}",
									"value=" . ( $csv[$i]['account']['account_no'] ? "{$csv[$i]['account']['account_no']} " : NULL ) . $csv[$i]['account']['account_name'],
									"autocomplete=off",
									"style=width:125px",
									( $csv[$i]['account']['error'] || $csv[$i]['account']['matches'] > 1 ? "style=color:#" . ( $csv[$i]['account']['matches'] > 1 ? "FF9900" : "FF0000" ) . ";" : NULL ),
									"onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'account_name[$i]', 'account_hash[$i]', 1);}",
									"onKeyUp=if(ka==false && this.value){key_call('payables', 'account_name[$i]', 'account_hash[$i]', 1);}",
									"onKeyDown=if(event.keyCode!=9){clear_values('account_hash[$i]');}",
									"onBlur=key_clear();"
								) .
								$this->form->hidden( array(
    								"account_hash[$i]" => $csv[$i]['account']['account_hash']
								) ) . "
								<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('account_name[$i]'), 'top')+20, findPos(\$('account_name[$i]'), 'left')+1);key_list('payables', 'account_name[$i]', 'account_hash[$i]', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>
							</td>
							<td style=\"text-align:right;\">" .
								$this->form->text_box(
    								"name=debit[$i]",
									"id=dr_$i",
									"value=" . ( bccomp($csv[$i]['dr'], 0, 2) == 1 ? number_format($csv[$i]['dr'], 2) : NULL ),
									"style=text-align:right;width:70px",
									"onFocus=select();",
			                        "onChange=tally(this);"
								) .
                                $this->form->hidden( array(
                                    "prev_dr_$i"   =>  ( bccomp($csv[$i]['dr'], 0, 2) == 1 ? $csv[$i]['dr'] : 0 ),
                                    "prev_cr_$i"   =>  ( bccomp($csv[$i]['cr'], 0, 2) == 1 ? $csv[$i]['cr'] : 0 )
                                ) ) . "
							</td>
							<td style=\"text-align:right;\">" .
                                $this->form->text_box(
	                                "name=credit[$i]",
									"id=cr_$i",
									"value=" . ( bccomp($csv[$i]['cr'], 0, 2) == 1 ? number_format($csv[$i]['cr'], 2) : NULL ),
									"style=text-align:right;width:70px",
									"onFocus=select()",
			                        "onChange=tally(this);"
                                ) . "
							</td>
							<td>" .
                                $this->form->text_box(
                                    "name=memo[$i]",
                                    "value=" . ( $csv[$i]['memo'] ? $csv[$i]['memo'] : NULL ),
                                    "style=width:180px",
                                    "maxlength=64"
                                ) . "
                            </td>
                            <td>" .
                                $this->form->text_box(
                                    "name=name[$i]",
                                    "id=err{$i}",
                                    "value={$_POST['name'][$i]}",
                                    "autocomplete=off",
                                    "style=width:135px",
                                    "tabindex=" . $tabindex++,
                                    "onFocus=if( this.value ) { select(); } position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('accounting', 'name[$i]', 'name_hash[$i]', 1);}",
                                    "onKeyUp=if(ka==false && this.value){key_call('accounting', 'name[$i]', 'name_hash[$i]', 1);}",
                                    "onBlur=key_clear();"
                                ) .
                                $this->form->hidden(array(
                                    "name_hash[$i]" => $_POST['name_hash'][$i]
                                ) ) . "

                            </td>
						</tr>";

					}
				}

				$tbl .= "
				    <tr>
                        <td>&nbsp;</td>
                        <td style=\"text-align:right;\">
                            <div style=\"margin-top:10px;font-weight:bold;border-top:1px solid black;width:95%;\" id=\"total_dr_holder\">\$" . number_format($csv_dr, 2) . "</div>
                        </td>
                        <td style=\"text-align:right;\">
                            <div style=\"margin-top:10px;font-weight:bold;border-top:1px solid black;width:95%;\" id=\"total_cr_holder\">\$" . number_format($csv_cr, 2) . "</div>
                        </td>
                        <td>&nbsp;</td>
				    </tr>
				</table>
                <div style=\"float:right;padding:10px 25px 0 0;\">
                    <a href=\"javascript:void(0);\" onClick=\"submit_form(\$('popup_id').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_add_line', 'line_int=" . (++$i) . "');\"><img src=\"images/plus.gif\" title=\"Add more lines\" border=\"0\" /></a>
                </div>" .
                $this->form->hidden( array(
                    'total_dr'    =>  $csv_dr,
                    'total_cr'    =>  $csv_cr
                ) );
			}

            $this->content['action'] = 'close';

			if ( ! $journal_popup_id ) {

				$rand_file = 'tempcsv_' . rand(100, 1000);
				while ( file_exists(UPLOAD_DIR . $rand_file) )
    				$rand_file = 'tempcsv_' . rand(100, 1000);

				$fh = fopen($rand_file, "w");
				fwrite($fh, $tbl);
				fclose($fh);

				$this->content['jscript'] = "agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'new_journal_entry', 'popup_id=journal_win', 'csvimport=$rand_file');";
				return;
			}

			$this->content['html']["line_holder{$journal_popup_id}"] = $tbl;

			return;

		} else {

			$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?proposal_hash=123&class=accounting&action=doit_import_csv&current_hash=".$this->current_hash."&popup_id=".$this->popup_id."', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
			$this->content['form_return']['feedback'] = ( $file_type != "application/octet-stream" ? "The file you are importing is not a valid CSV file. Please make sure when creating a CSV file, you follow the instructions provided." : "There was a problem with the file you imported. Please check to make sure the file is valid and not damaged and try again.");
			$this->content['error'] = 1;

			return;
		}
	}

	function upload_journal_entry() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		$this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Create a Journal Entry From a CSV Upload";

		$tbl =
		$this->form->form_tag('upload_journal') .
		$this->form->hidden( array(
    		"proposal_hash"       =>  '',
    		"popup_id"            =>  $this->popup_id,
    		"journal_popup_id"    =>  $this->ajax_vars['journal_popup_id']
		) ) . "
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:15px 20px;\">
						By creating a journal entry from a CSV file, you can quickly and easily create large journal entries in a single step.
						<br /><br />
						To get started, create
						an excel spreadsheet with 4 columns. The first row of the columns should contain the actual journal information, not a column header. The first
						column should coorespond to the account number. Make sure you enter only the account number, not the name. The second column should contain your
						debit entries. Only enter numbers, however commas will be accepted if they are found. Please don't enter any dollar signs. The third column is the
						same as the second, but should contain your credit entries. Finally, the last column should contain any memo to be created with that line. This column
						is optional.
						<br /><br />
						When you are finished, from the File menu, choose 'Save As', then under the file type, choose CSV (comma separated values).
                        <div style=\"margin-top:10px;margin-bottom:10px;\">
                            <div style=\"margin-bottom:4px;font-weight:bold;\">CSV Column Format</div>".
                            $this->form->select("csv_format",
                                                 array("Account, debit, credit, and optionally memo",
                                                       "Account, opening balance, debit, credit, closing and optionally memo",
                                                       "Account, opening balance, debit, credit, current, closing and optionally memo"),
                                                 '',
                                                 array(1, 2, 3),
                                                 "blank=1")."
                        </div>
						<div style=\"font-weight:bold;\" id=\"err2".$this->popup_id."\">CSV File: </div>
						<div id=\"iframe\"><iframe src=\"upload.php?proposal_hash=123&class=accounting&action=doit_import_csv&current_hash=".$this->current_hash."&popup_id=".$this->popup_id."&form_name=upload_journal\" frameborder=\"0\" style=\"height:50px;\"></iframe></div>
						<div id=\"progress_holder\"></div>" .
						$this->form->hidden(array("file_name" 		=>		$this->xmlPar->xmlFile['name'],
												  "file_type" 		=>		$this->xmlPar->xmlFile['type'],
												  "file_size" 		=>		$this->xmlPar->xmlFile['size'],
												  "file_error" 		=>		$this->xmlPar->xmlFile['error']
												  )
											)."
					</td>
				</tr>
			</table>
		</div>".$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}

	function vendor_remit_to() {

		if ( $vendor_hash = $this->ajax_vars['vendor_hash'] ) {

			$vendors = new vendors($this->current_hash);
			$vendors->fetch_master_record($vendor_hash);

			if ( $vendors->current_vendor['remit_street'] ) {

				$remit_to =
				( $vendors->current_vendor['remit_name'] ?
					stripslashes($vendors->current_vendor['remit_name']) . "\n" : NULL
    			) .
				stripslashes($vendors->current_vendor['remit_street']) . "\r\n" .
                stripslashes($vendors->current_vendor['remit_city']) . ", {$vendors->current_vendor['remit_state']} {$vendors->current_vendor['remit_zip']}";

			} else {

				$remit_to =
				stripslashes($vendors->current_vendor['vendor_name']) . "\n" .
				( $vendors->current_vendor['street'] ?
    				stripslashes($vendors->current_vendor['street']) . "\r\n" : NULL
    			) .
    			stripslashes($vendors->current_vendor['city']) . ", {$vendors->current_vendor['state']} {$vendors->current_vendor['zip']}";
			}
		}
		$this->content['value']['journal_check_remit_to'] = $remit_to;
		return;
	}

	function fetch_checking_accounts() {

		$checking = array();

		$result = $this->db->query("SELECT
										t1.account_no,
										t1.account_name,
										t1.account_hash,
										t1.next_check_no
									FROM accounts t1
									WHERE t1.account_type = 'CA' AND t1.checking = 1
									ORDER BY t1.account_no, t1.account_name ASC");
		while ( $row = $this->db->fetch_assoc($result) ) {

			array_push($checking, array(
				'account_hash'	=>	$row['account_hash'],
				'account_name'	=>	( $row['account_no'] ? "{$row['account_no']} : " : NULL ) . stripslashes($row['account_name']),
				'check_no'		=>	$row['next_check_no']
			) );
		}

		return $checking;
	}

	function new_journal_entry($local=NULL) {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		if ( $this->ajax_vars['popup_id'] )
			$this->popup_id = $this->ajax_vars['popup_id'];

		$this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Make a Journal Entry";

		$csvimport = $this->ajax_vars['csvimport'];

		$checking = $this->fetch_checking_accounts(); # Get the checking accounts for journal type - check

		for ( $i = 0; $i < count($checking); $i++ ) {

			$c_key[] = $checking[$i]['account_hash'];
			$c_val[] = $checking[$i]['account_name'];
			$c_num .= $this->form->hidden( array(
				"next_{$checking[$i]['account_hash']}"	=>	$checking[$i]['check_no']
			) );
			if ( $i == 0 )
				$initial_num = $checking[$i]['check_no'];
		}

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
			"popup_id"		=>	$this->popup_id,
			"p"				=>	$p,
			"order"			=>	$order,
			"order_dir"		=>	$order_dir,
			"proposal_hash"	=>	$this->ajax_vars['proposal_hash']
		) ) .
		$c_num . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\">" .
					( $error ?
						$err_feedback : NULL
					) . "
					</p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"background-color:#ffffff;padding:10px 0 10px 25px;width:50%;\">
						<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1{$this->popup_id}\">Entry Type:</div>" .
						$this->form->select(
							"trans_type",
							array_slice($this->trans_type['type'], 2),
							'GJ',
							array_slice($this->trans_type['code'], 2),
							"blank=1",
							"onChange=if ( this.options[ this.selectedIndex ].value == 'CD' ) { toggle_display('journal_check_input{$this->popup_id}', 'block'); toggle_display('journal_memo_input{$this->popup_id}', 'none'); } else if ( this.options[ this.selectedIndex ].value == 'ME' ) { toggle_display('journal_check_input{$this->popup_id}', 'none');toggle_display('journal_memo_input{$this->popup_id}', 'block'); } else { toggle_display('journal_check_input{$this->popup_id}', 'none');toggle_display('journal_memo_input{$this->popup_id}', 'none'); }"
						) . "
						<div style=\"margin-top:10px;display:none;\" id=\"journal_check_input{$this->popup_id}\">
							<table>
								<tr>
									<td style=\"text-align:right;\" id=\"err_check_acct\">Checking Acct:</td>
									<td>" .
									( $c_val ?
										$this->form->select(
											"journal_checking_acct",
											$c_val,
											'',
											$c_key,
											"blank=1",
											"style=width:200px;",
											"onChange=if(this.options[this.selectedIndex].value){\$('journal_check_no').value=\$F('next_' + this.options[this.selectedIndex].value);}"
										) : "<img src=\"images/alert.gif\" /> No checking accounts defined!"
									) . "
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;\" id=\"err_check_no\">Check No:</td>
									<td>" .
										$this->form->text_box(
											"name=journal_check_no",
											"value=$initial_num",
											"size=10"
										) . "
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;\" id=\"err_check_payee\">Check Payee:</td>
									<td>" .
										$this->form->text_box(
											"name=journal_check_payee",
											"value=",
											"autocomplete=off",
											"size=30",
											"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('accounting', 'journal_check_payee', 'journal_check_payee_hash', 1);}",
										    "onKeyUp=if(ka==false && this.value){key_call('accounting', 'journal_check_payee', 'journal_check_payee_hash', 1);}",
											"onBlur=key_clear();"
										) .
										$this->form->hidden( array(
											"journal_check_payee_hash"	=>	''
										) ) . "
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;vertical-align:top;\">Remit To:</td>
									<td>" .
										$this->form->text_area(
											"name=journal_check_remit_to",
											"rows=3",
											"cols=30"
										) . "
									</td>
								</tr>
							</table>
						</div>
						<div style=\"margin-top:10px;display:none;\" id=\"journal_memo_input{$this->popup_id}\">
							<div style=\"margin-bottom:5px;\" id=\"err_proposal_no\">Proposal No:</div>" .
								$this->form->text_box(
									"name=journal_proposal_no",
									"value=",
									"autocomplete=off",
									"onBlur=key_clear();",
									"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'journal_proposal_no', 'journal_proposal_hash', 1);}",
									"onKeyUp=if(ka==false && this.value){key_call('payables', 'journal_proposal_no', 'journal_proposal_hash', 1);}"
								) .
								$this->form->hidden( array(
									"journal_proposal_hash"	=>	''
								) ) . "
						</div>
					</td>
					<td style=\"background-color:#ffffff;padding:10px 0 10px 25px;width:50%;vertical-align:top;\" >
						<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2{$this->popup_id}\">Entry Date:</div>
						<span id=\"entry_date_holder{$this->popup_id}\"></span>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#ffffff;padding:10px;\" colspan=\"2\" >
						<div style=\"padding-bottom:5px\">
							To create a journal entry from a CSV file <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'upload_journal_entry', 'popup_id=upload_journal_win', 'journal_popup_id={$this->popup_id}');\">click here</a>.
						</div>
						<div id=\"line_holder{$this->popup_id}\">";

						if ( $csvimport && file_exists($csvimport) ) {

							$inner_tbl = file_get_contents($csvimport);

						} else {

							$inner_tbl = "
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"3\" style=\"width:680px;\" >
									<tr style=\"font-weight:bold;background-color:#efefef;font-size:8pt\">
										<td style=\"border-bottom:1px solid #cccccc;width:153px;\">Account</td>
										<td style=\"border-bottom:1px solid #cccccc;text-align:right;width:77px;\">Debit</td>
										<td style=\"border-bottom:1px solid #cccccc;text-align:right;width:77px;\">Credit</td>
										<td style=\"border-bottom:1px solid #cccccc;padding-left:10px;width:185px;\">Memo</td>
										<td style=\"border-bottom:1px solid #cccccc;\">Customer/Vendor</td>
									</tr>";

								$line_int = ( $local ? $local : 5 );
								$tabindex = 10;
								$total_dr = $total_cr = 0;

								for ( $i = 0; $i < $line_int; $i++ ) {

									if ( $local ) {

									    $total_dr = bcadd($total_dr, preg_replace('/[^.0-9]/', "", $_POST['debit'][$i]), 2);
									    $total_cr = bcadd($total_cr, preg_replace('/[^.0-9]/', "", $_POST['credit'][$i]), 2);
									}

									$inner_tbl .= "
									<tr >
										<td style=\"vertical-align:bottom;width:153px;\" nowrap>" .
	                                        $this->form->text_box(
												"name=account_name[$i]",
												"id=err{$i}",
												"value=" . ( $_POST['account_name'][$i] ? $_POST['account_name'][$i] : NULL ),
												"autocomplete=off",
												"style=width:125px",
												"tabindex=" . $tabindex++,
												"onFocus=if( this.value ) { select(); } position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('accounting', 'account_name[$i]', 'account_hash[$i]', 1);}",
												"onKeyUp=if(ka==false && this.value){key_call('accounting', 'account_name[$i]', 'account_hash[$i]', 1);}",
												"onBlur=key_clear();"
											) .
											$this->form->hidden(array(
	    										"account_hash[$i]"	=>	( $_POST['account_hash'][$i] ? $_POST['account_hash'][$i] : NULL )
											) ) . "
											<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('account_name[$i]'), 'top')+20, findPos(\$('account_name[$i]'), 'left')+1);key_list('accounting', 'account_name[$i]', 'account_hash[$i]', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>
										</td>
										<td style=\"text-align:right;width:77px;\">".
											$this->form->text_box(
												"name=debit[$i]",
												"id=dr_$i",
												"value=" . ( $_POST['debit'][$i] ? $_POST['debit'][$i] : NULL ),
												"tabindex=" . $tabindex++,
												"style=text-align:right;width:70px;",
												"onFocus=select();",
												"onChange=tally(this);"
											) .
											$this->form->hidden( array(
	                                            "prev_dr_{$i}"   =>  ( $_POST['debit'][$i] ? preg_replace('/[^.0-9]/', "", $_POST['debit'][$i]) : 0),
											    "prev_cr_{$i}"   =>  ( $_POST['credit'][$i] ? preg_replace('/[-.0-9]/', "", $_POST['credit'][$i]) : 0),
											) ) . "
										</td>
										<td style=\"text-align:right;width:77px;\">" .
											$this->form->text_box(
											    "name=credit[$i]",
											    "id=cr_{$i}",
											    "value=" . ( $_POST['credit'][$i] ? $_POST['credit'][$i] : NULL ),
											    "tabindex=" . $tabindex++,
											    "style=text-align:right;width:70px;",
											    "onFocus=select();",
											    "onChange=tally(this);"
											) . "
										</td>
										<td style=\"padding-left:10px;\">" .
											$this->form->text_box(
												"name=memo[$i]",
												"tabindex=" . $tabindex++,
												"value=" . ( $_POST['memo'][$i] ? $_POST['memo'][$i] : NULL ),
												"style=width:180px",
												"maxlength=64"
											) . "
										</td>
										<td>" .
											$this->form->text_box(
												"name=name[$i]",
												"id=err{$i}",
												"value=" . ( $_POST['name'][$i] ? $_POST['name'][$i] : NULL ),
												"autocomplete=off",
												"style=width:135px",
												"tabindex=" . $tabindex++,
												"onFocus=if( this.value ) { select(); } position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('accounting', 'name[$i]', 'name_hash[$i]', 1);}",
												"onKeyUp=if(ka==false && this.value){key_call('accounting', 'name[$i]', 'name_hash[$i]', 1);}",
												"onBlur=key_clear();"
											) .
											$this->form->hidden(array(
	    										"name_hash[$i]"	=>	( $_POST['name_hash'][$i] ? $_POST['name_hash'][$i] : NULL )
											) ) . "
										</td>
									</tr>";
								}

								$inner_tbl .= "
				                    <tr>
				                        <td><div style=\"margin-top:10px;font-weight:bold;border-top:1px solid #8f8f8f;width:95%;text-align:right;\">&nbsp;</div></td>
				                        <td style=\"text-align:right;\">
				                            <div style=\"margin-top:10px;font-weight:bold;border-top:1px solid #8f8f8f;width:95%;text-align:right;\" id=\"total_dr_holder\">\$" .
											( $local ?
												number_format($total_dr, 2) : "0.00"
											) . "
											</div>
				                        </td>
				                        <td style=\"text-align:right;\">
				                            <div style=\"margin-top:10px;font-weight:bold;border-top:1px solid #8f8f8f;width:95%;text-align:right;\" id=\"total_cr_holder\">\$" .
											( $local ?
												number_format($total_cr, 2) : "0.00"
											) . "
											</div>
				                        </td>
				                        <td><div style=\"margin-top:10px;font-weight:bold;border-top:1px solid #8f8f8f;width:95%;text-align:right;\">&nbsp;</div></td>
				                        <td><div style=\"margin-top:10px;font-weight:bold;border-top:1px solid #8f8f8f;width:95%;text-align:right;\">&nbsp;</div></td>
				                    </tr>
								</table>
								<div style=\"float:right;padding:10px 25px 0 0;\">
									<a href=\"javascript:void(0);\" onClick=\"submit_form(\$('popup_id').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_add_line', 'line_int=" . (++$i) . "');\"><img src=\"images/plus.gif\" title=\"Add more lines\" border=\"0\" /></a>
								</div>
								<div id=\"proceedJournal\" style=\"padding:10px;text-align:right;padding-right:45px;display:none;\">" .
									"<br /><br />If you would like to continue, click below:<br /><br />" .
									$this->form->button(
			    						"value=Proceed Anyway",
			    						"onClick=\$('journal_btn').disabled=1;submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'btn=saveentry', 'period_closed_ok=1');"
									) . "
								</div>" .
											
											
		                        $this->form->hidden( array(
		                            'total_dr'    =>  ( $local ? $total_dr : 0 ),
		                            'total_cr'    =>  ( $local ? $total_cr : 0 )
		                        ) );
						}

						$tbl .=
						( ! $local ?
    						$inner_tbl : NULL
    					) . "
						</div>
					</td>
				</tr>
			</table>
			<div style=\"padding:10px;text-align:right;padding-right:45px;\">" .
				$this->form->button(
					"value=Save Entry",
					"id=journal_btn",
					"onClick=submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'btn=saveentry');this.disabled=1"
				) . "
			</div>
		</div>" .
		$this->form->close_form();

        if ( $local )
            return $inner_tbl;

        $this->content["jscript"][] = "
        tally = function() {
            var el = arguments[0];

            el.value = formatCurrency( el.value.replace(/[-, ]/g, '') );

            el.id.match(/^([dc]r)_(\d*)$/);
            var acct_type = RegExp.$1;
            var el_count = RegExp.$2;

            var curr_val = parseFloat( \$F(el).toString().replace(/[^.0-9]/g, '') );
        	if(isNaN(curr_val)){
    			curr_val = 0.0;
    		}
            var prev_val = parseFloat( \$F('prev_' + acct_type + '_' + el_count).toString().replace(/[^.0-9]/g, '') );
            var total = parseFloat( \$F('total_' + acct_type).toString().replace(/[^.0-9]/g, '') );

            var diff = ( curr_val - prev_val );
            diff = Math.round(diff * Math.pow(10, parseInt(2))) / Math.pow(10, parseInt(2));

            \$('prev_' + acct_type + '_' + el_count).value = curr_val;

            var new_total = ( total + diff );
            \$('total_' + acct_type ).value = new_total;
            \$('total_' + acct_type + '_holder').innerHTML = '\$' + ( ! new_total ? '0.00' : formatCurrency( new_total ) );

        }";

		$this->content["jscript"][] = "setTimeout('DateInput(\'entry_date\', \'true\', \'YYYY-MM-DD\', \'" . date("Y-m-d") . "\', 1, \'entry_date_holder{$this->popup_id}\')', 45)";;
		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function getLastDayOfMonth($month, $year) {

		return idate('d', mktime(0, 0, 0, ($month + 1), 0, $year));
	}

	function config() {

		if ( $local = func_get_arg(0) ) {

			$fiscal_start = func_get_arg(1);
			$period_start = func_get_arg(2);
		}

		$this->unlock("_");
		$this->load_class_navigation();

		$p = ( $_POST['p'] ? $_POST['p'] : $this->ajax_vars['p'] );
		$order = ( $_POST['order'] ? $_POST['order'] : $this->ajax_vars['order'] );
		$order_dir = ( $_POST['order_dir'] ? $_POST['order_dir'] : $this->ajax_vars['order_dir'] );
		$fiscal_period_config = $_POST['fiscal_period_config'];

        if ( ! $fiscal_period_config && defined('FISCAL_PERIOD_CONFIG') )
    		$fiscal_period_config = FISCAL_PERIOD_CONFIG;

		if ( defined('FISCAL_YEAR_START') )
			list($fiscal_month, $fiscal_day) = explode("/", FISCAL_YEAR_START);

		$date = strtotime("2007-01-01");
		for ( $i = 1; $i <= 12; $i++ ) {

			$month_name[] = date("F", strtotime("2007-{$i}-01"));
			$month_int[] = $i;
		}
		for ( $i = 1; $i <= 31; $i++ )
			$days[] = $i;

		$period_types = array(
    		1 =>  "Standard, last day of each month",
    		2 =>  "13 week period using 4-4-5",
    		3 =>  "13 week period using 5-4-4",
    		4 =>  "13 week period using 4-5-4",
    		5 =>  "Let me define my own"
		);

		# Find out the first date ever posted to the journal
		$r = $this->db->query("SELECT YEAR( t1.date ) AS first_year
		                       FROM journal t1
		                       ORDER BY t1.date ASC
		                       LIMIT 1");
		if ( $first_year = $this->db->result($r, 0, 'first_year') ) {

			while ( $first_year != date("Y") ) {

				$years[] = $first_year;
				$first_year++;
			}
		}
		for ( $i = 0; $i < 5; $i++ )
			$years[] = date("Y", strtotime( date("Y") . " +{$i} years") );

		if ( $local )
			$jscript[] = "\$('fiscal_month').disabled=0;\$('fiscal_day').disabled=0;\$('fiscal_year').disabled=0;\$('period_type').disabled=0;";

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
            'test'      =>  1,
    		'p'         =>  $p,
			'order'     =>  $order,
			'order_dir' =>  $order_dir
		) ) . "
		<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
			<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
				<p id=\"feedback_message\"></p>
		</div>
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <div style=\"background-color:#ffffff;height:100%;\">
					 	<div style=\"margin:15px 35px;\">
							<h3 style=\"margin-bottom:5px;color:#00477f;\">Configure Your Business Cycle &amp; Settings</h3>
							<div style=\"float:right;\">" .
                    		( $this->p->ck(get_class($this), 'E', 'config') ?
								$this->form->button(
    								"name=null",
    								"value=Save Settings",
    								"onClick=submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'final=1');"
								) : NULL
							) . "
							</div>
							<small style=\"margin-left:20px;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'show_journal', 'p=$p', 'order=$order', 'order_dir=$order_dir')\"><-- Back</a></small>
							<div style=\"margin:20px;width:800px;padding:0;\">
								<fieldset>
									<legend>Fiscal Year Period Configuration</legend>
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
										<tr>
											<td style=\"padding:10px 25px;\">
												What fiscal year are you setting up?
												<div style=\"margin-left:25px;padding-top:5px;\">" .
													$this->form->select(
    													"year_config",
                    									$years,
                    									$_POST['year_config'],
                    									$years,
                    									"onChange=submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'toggle_layout=1', 'change_year=' + this.options[ this.selectedIndex ].value);"
                    								) . "
                                                    <span style=\"margin-left:15px;font-style:italic;\" id=\"prev_period_config\"></span>
												</div>
											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;\">
												<span id=\"err_date\">When is the first day of the fiscal year for the year you are configuring?</span>
												<div style=\"margin-left:25px;padding-top:5px;\">" .
													$this->form->select(
    													"fiscal_month",
    													$month_name,
    													$fiscal_month,
    													$month_int,
    													"blank=1",
    													"disabled",
    													"onChange=submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'toggle_layout=1')"
    												) . "&nbsp;" .
													$this->form->select(
    													"fiscal_day",
    													$days,
    													$fiscal_day,
    													$days,
    													"blank=1",
    													"disabled",
    													"onChange=submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'toggle_layout=1')"
    												) . "&nbsp;" .
													$this->form->select(
    													"fiscal_year",
    													$years,
    													( $_POST['fiscal_year'] ? $_POST['fiscal_year'] : ( $_POST['year_config'] ? $_POST['year_config'] : date("Y") ) ),
    													$years,
    													"blank=1",
    													"disabled",
    													"onChange=submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'toggle_layout=1')"
    												) . "
												</div>
											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;\">
												What type of periods do you operate?
												<div style=\"margin-left:25px;padding-top:5px;\">".
													$this->form->select(
    													"period_type",
    													array_values($period_types),
    													( $local ? $local : ( $_POST['year_config'] ? $fiscal_period_config : NULL ) ),
    													array_keys($period_types),
    													"disabled",
    													"onChange=if ( ! \$F('year_config') ) { alert('Please select the fiscal year you are configuring before changing this field.'); this.value='' } else { submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'toggle_layout=1'); }"
    												) . "
												</div>
											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;\" id=\"period_layout_holder\">";

											if ( $local ) {

												$year = $_POST['year_config'];
												$year_start = ( $_POST['fiscal_year'] && $_POST['fiscal_year'] != $_POST['year_config'] ? $_POST['year_config'] : $year );

												list($month, $day) = explode("/", $fiscal_start);
												if ( $local == 1 || $local == 5 ) {

													if ( $month )
    													$month--;

												} elseif ( $local >= 2 && $local < 5 ) {

													$date = "{$fiscal_start}/{$year_start}";
													$w_i = 0;
												}

												$inner_tbl = "
												<span id=\"err_layout\">Layout your period closing dates:</span>
												[<small><a href=\"javascript:void(0);\" onClick=\"submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'period_start=');\"\" class=\"link_standard\">reset</a></small>]
												<table>";

												if ( $local < 5 ) {

													for ( $i = 1; $i <= 12; $i++ ) {

														$result = $this->db->query("SELECT
	                            														t1.period_end,
	                            														t1.closed
																					FROM period_dates t1
																					WHERE t1.fiscal_year = '$year' AND t1.period = '$i'");
														$closed = $this->db->result($result, 0, 'closed');
														$period_end = $this->db->result($result, 0, 'period_end');

														switch ( $local ) {

															case 1:

															$inner_tbl .= "
															<td>
																<div style=\"margin-left:25px;padding-top:10px;\">
																	<span id=\"err{$i}\">Period $i " . ( $closed ? "is closed as of: " : "will close on: ") . "</span>
																	<div style=\"padding-top:5px;margin-left:10px;\" id=\"period_{$i}_holder\">" .
																	( $closed ?
																		date(DATE_FORMAT, strtotime($period_end)) : NULL
																	) . "
																	</div>
																</div>
															</td>" .
                                                            ( $i % 3 == 0 ?
                                                                "</tr>" .
                                                                ( $i < 12 ?
                                                                    "<tr>" : NULL
                                                                ) : NULL
                                                            );

															if ( ! $closed )
																$jscript[] = "setTimeout('DateInput(\'period{$i}\', \'true\', \'YYYY-MM-DD\', \'" . ( $period_end ? $period_end : "{$year_start}-" . ( $i + $month ) . "-" . $this->getLastDayOfMonth($i + $month, $year_start) ) . "\', 1, \'period_{$i}_holder\')', " . ( $i * 20 ) . ");";

															break;

															case 2:
															case 3:
															case 4:

															if ( $local == 2 )
																$weeks = array(4, 4, 5);
															elseif ( $local == 3 )
																$weeks = array(5, 4, 4);
															elseif ( $local == 4 )
																$weeks = array(4, 5, 4);

															$inner_tbl .= "
															<td>
																<div style=\"margin-left:25px;padding-top:10px;\">
																	<span id=\"err{$i}\">Period $i " . ( $closed ? "is closed as of: " : "will close on: " ) . "</span>
																	<div style=\"padding-top:5px;margin-left:10px;\" id=\"period_{$i}_holder\">" .
																	( $closed ?
																		date(DATE_FORMAT, strtotime($period_end)) : NULL
																	) . "
																	</div>
																</div>
															</td>" .
															( $i % 3 == 0 ?
    															"</tr>" .
    															( $i < 12 ?
        															"<tr>" : NULL
    															) : NULL
    														);

															$date = date("Y-m-d", strtotime("$date +{$weeks[ $w_i ]} weeks"));
															if ( ! $closed )
																$jscript[] = "setTimeout('DateInput(\'period{$i}\', \'true\', \'YYYY-MM-DD\', \'$date\', 1, \'period_{$i}_holder\')', " . ( $i * 20 ) . ");";

															$w_i = ( $i % 3 == 0 ? 0 : $w_i + 1 );

															break;
														}
													}

													$total_periods = 12;

												} elseif ( $local == 5 ) {

													$result = $this->db->query("SELECT t1.*
																				FROM period_dates t1
																				WHERE t1.fiscal_year = '$year'");
													while ( $row = $this->db->fetch_assoc($result) )
														$period_data[ $row['period'] ] = $row;

													if ( $period_data && count($period_data) > 1 )
														$period_start = count($period_data);

													if ( ! $period_start ) {

														$start = 1;
														$period_start = 1;
													}

													for ( $i = 1; $i <= $period_start; $i++ ) {

														if ( $period_data[$i] )
															$_POST["period{$i}"] = $period_data[$i]['period_end'];

														if ( $start )
															$use_date = "{$year}-" . ( $i + $month ) . "-" . $this->getLastDayOfMonth($i + $month, $year);
														if ( $_POST["period{$i}"] )
															$use_date = $_POST["period{$i}"];
														elseif ( $_POST["period" . ( $i - 1 )] ) {

															$use_date = date("Y", strtotime($_POST["period" . ( $i - 1 )] . " +15 days")) . "-" . date("m", strtotime($_POST["period" . ( $i - 1 )] . " +15 days")) . "-" . $this->getLastDayOfMonth( date("m", strtotime($_POST["period" . ( $i - 1 )] . " +15 days")), date("Y", strtotime($_POST["period" . ( $i - 1 )] . " +15 days") ) );
															if ( strtotime($use_date) > strtotime("{$fiscal_start}/" . ( $year + 1 ) ) )
																$use_date = date("Y-m-d", strtotime("{$fiscal_start}/" . ( $year + 1 ) ) - 86400);
														}

														$inner_tbl .=
														( $start ?
    														"<tr>" : NULL
														) . "
															<td style=\"vertical-align:top;width:33%\">
																<div style=\"margin-left:25px;padding-top:10px;\">
																	Period $i " .
																	( $period_data[$i]['closed'] ?
    																	"is closed as of: " : "will close on: [<small><a href=\"javascript:void(0);\" onClick=\"submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'period_start=" . ( $i - 1 ) . "');\"\" class=\"link_standard\">x</a></small>]"
                                                                    ) . "
																	<div style=\"padding-top:5px;margin-left:10px;\" id=\"period_{$i}_holder\">" .
																	( $period_data[$i]['closed'] ?
																		date(DATE_FORMAT, strtotime($period_data[$i]['period_end'])) : NULL
																	) . "
																	</div>
																</div>
															</td>";

                                                        if ( $i % 3 == 0 ) {

                                                            $inner_tbl .=
                                                            "</tr>" .
                                                            ( $i < 12 ?
                                                                "<tr>" : NULL
                                                            );
                                                        } elseif ( $i == $period_start ) {

                                                        	for ( $j = 0; $j < ( 3 - $i % 3 ); $j++ )
                                                                $inner_tbl .= "
                                                                <td style=\"width:33%\">&nbsp;</td>";

                                                        }

														if ( ! $period_data[$i]['closed'] )
															$jscript[] = "setTimeout('DateInput(\'period{$i}\', \'true\', \'YYYY-MM-DD\', \'" . ( $period_data[$row['period']]['period_end'] ? $period_data[$row['period']]['period_end'] : $use_date ) . "\', 1, \'period_{$i}_holder\')', " . ( $i * 20 ) . ");";

														if ( $i == $period_start && strtotime($use_date) + 86400 < strtotime("{$fiscal_start}/" . ( $year + 1 ) ) ) {

															$next_incr = $i;
															$next_cntrl = "
															[ <small><a href=\"javascript:void(0);\" onClick=\"submit_form(\$('test').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_config', 'period_start=" . ( $i + 1 ) . "');\" style=\"color:#000\">Next</a></small> ]";
														} elseif ( strtotime($use_date) + 86400 >= strtotime("{$fiscal_start}/" . ( $year + 1 ) ) ) {

															$next_cntrl =
															$this->form->hidden( array(
    															"period_config_complete" =>  1
															) );
														}
													}

													$total_periods = $i;
													if ( $next_cntrl )
														$inner_tbl .= "
														<tr>
															<td colspan=\"3\" style=\"width:100%;text-align:right;\">
                                                                <div style=\"margin-top:15px;margin-right:" . ( $next_incr % 3 ? ( 200 * ( 3 - ( $next_incr % 3 ) ) ) : "25px" ) . "px;\">$next_cntrl</div>
															</td>
														</tr>";
												}

												$inner_tbl .=
												$this->form->hidden( array(
    												'total_periods' =>  $total_periods
												) ) . "
												</table>";
											}

									$tbl .= "
											</td>
										</tr>
									</table>
								</fieldset>
								<fieldset style=\"margin-top:10px;\">
									<legend>WIP Auto Reconciliation</legend>
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
										<tr>
											<td style=\"padding:10px 25px;\">
												Occasionally, work-in-progress (WIP) money may be left in the WIP account due to small billing discrepencies.
												By entering an amount and account below we can reconcile outstanding balances up to the amount you enter, and
												clear those balances into the clearing account you select below. If you leave this area blank this automatic function will not be available to you.
												<br /><br />
												What is the minimum amount you would like have automatically reconciled? The average amount is around $250.
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px;\">".
													$this->form->text_box("name=wip_min_amt",
																		  "id=err_wip_amt",
																		  "value=".($_POST['wip_min_amt'] ? $_POST['wip_min_amt'] : (defined('AUTO_WIP_MIN_AMT') ? number_format(AUTO_WIP_MIN_AMT, 2) : NULL)),
																		  "size=8",
																		  (!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																		  "style=text-align:right",
																		  "onBlur=if(this.value){this.value=formatCurrency(this.value);}",
																		  "onFocus=select();")."
												</div>
												Which account would you like to reconcile these amounts into?
												<div style=\"margin-left:25px;padding-top:5px;\">";
												if ($_POST['wip_clearing_acct'] || defined('AUTO_WIP_CLEARING_ACCT'))
													$this->fetch_account_record(($_POST['wip_clearing_acct_hash'] ? $_POST['wip_clearing_acct_hash'] : (defined('AUTO_WIP_CLEARING_ACCT') ? AUTO_WIP_CLEARING_ACCT : NULL)));

													$tbl .=
													$this->form->text_box("name=wip_clearing_acct",
																			"id=err_wip_acct",
																			"value=".($this->account_hash ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'wip_clearing_acct', 'wip_clearing_acct_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'wip_clearing_acct', 'wip_clearing_acct_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('wip_clearing_acct_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("wip_clearing_acct_hash" => ($_POST['wip_clearing_acct_hash'] ? $_POST['wip_clearing_acct_hash'] : ($this->account_hash ? $this->account_hash : NULL)))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('wip_clearing_acct'), 'top')+20, findPos(\$('wip_clearing_acct'), 'left')+1);key_list('payables', 'wip_clearing_acct', 'wip_clearing_acct_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
										</tr>
									</table>
								</fieldset>
								<fieldset style=\"margin-top:10px;\">
									<legend>Account Aging</legend>
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
										<tr>
											<td style=\"padding:10px 25px;\" colspan=\"3\">
												Balance sheet accounts can be aged at any interval you choose. Aging is calculated in real time, therefore changing
												your aging schedule midway through the year will not cause errors in your accounting.

											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;width:33%\">
												Account aging schedule 1:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px;\">".
													$this->form->text_box("name=ACCOUNT_AGING_SCHED1",
																		  "id=err_ACCOUNT_AGING_SCHED1",
																		  "value=".($_POST['ACCOUNT_AGING_SCHED1'] ? $_POST['ACCOUNT_AGING_SCHED1'] : (defined('ACCOUNT_AGING_SCHED1') ? ACCOUNT_AGING_SCHED1 : 30)),
																		  "size=8",
																		  (!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																		  "style=text-align:right",
																		  "onFocus=select();")."
												</div>
											</td>
											<td style=\"padding:10px 25px;width:33%\">
												Account aging schedule 2:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px;\">".
													$this->form->text_box("name=ACCOUNT_AGING_SCHED2",
																		  "id=err_ACCOUNT_AGING_SCHED2",
																		  "value=".($_POST['ACCOUNT_AGING_SCHED2'] ? $_POST['ACCOUNT_AGING_SCHED2'] : (defined('ACCOUNT_AGING_SCHED2') ? ACCOUNT_AGING_SCHED2 : 60)),
																		  "size=8",
																		  (!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																		  "style=text-align:right",
																		  "onFocus=select();")."
												</div>
											</td>
											<td style=\"padding:10px 25px;width:33%\">
												Account aging schedule 3:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px;\">".
													$this->form->text_box("name=ACCOUNT_AGING_SCHED3",
																		  "id=err_ACCOUNT_AGING_SCHED3",
																		  "value=".($_POST['ACCOUNT_AGING_SCHED3'] ? $_POST['ACCOUNT_AGING_SCHED3'] : (defined('ACCOUNT_AGING_SCHED3') ? ACCOUNT_AGING_SCHED3 : 90)),
																		  "size=8",
																		  (!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																		  "style=text-align:right",
																		  "onFocus=select();")."
												</div>
											</td>
										</tr>
									</table>
								</fieldset>
								<fieldset style=\"margin-top:10px;\">
									<legend>Assigning Your Default Accounts</legend>
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
										<tr>
											<td style=\"padding:10px 25px;\" colspan=\"3\">
												There are certain actions such as creating customer invoices and receiving payment that have a direct
												impact on your company's accounting. In order to prevent employees from potentially assigning these
												transactions to incorrect accounts you can assign your default accounts here. Defaults accounts are
												never set in stone and can always be changed.
											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;width:33%;vertical-align:top;\">
												What is your default <span title=\"Cost of Goods Sold\">COGS</span><br />account?
												<div style=\"margin-left:5px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['DEFAULT_COGS_ACCT'] || defined('DEFAULT_COGS_ACCT'))
													$this->fetch_account_record(($_POST['DEFAULT_COGS_ACCT_hash'] ? $_POST['DEFAULT_COGS_ACCT_hash'] : (defined('DEFAULT_COGS_ACCT') ? DEFAULT_COGS_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=DEFAULT_COGS_ACCT",
																			"id=err_DEFAULT_COGS_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'DEFAULT_COGS_ACCT', 'DEFAULT_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'DEFAULT_COGS_ACCT', 'DEFAULT_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('DEFAULT_COGS_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("DEFAULT_COGS_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('DEFAULT_COGS_ACCT'), 'top')+20, findPos(\$('DEFAULT_COGS_ACCT'), 'left')+1);key_list('payables', 'DEFAULT_COGS_ACCT', 'DEFAULT_COGS_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
											<td style=\"padding:10px 25px;width:33%;vertical-align:top;\">
												What is your default Cash<br />account?
												<div style=\"margin-left:5px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['DEFAULT_CASH_ACCT'] || defined('DEFAULT_CASH_ACCT'))
													$this->fetch_account_record(($_POST['DEFAULT_CASH_ACCT_hash'] ? $_POST['DEFAULT_CASH_ACCT_hash'] : (defined('DEFAULT_CASH_ACCT') ? DEFAULT_CASH_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=DEFAULT_CASH_ACCT",
																			"id=err_DEFAULT_CASH_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'DEFAULT_CASH_ACCT', 'DEFAULT_CASH_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'DEFAULT_CASH_ACCT', 'DEFAULT_CASH_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('DEFAULT_CASH_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("DEFAULT_CASH_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('DEFAULT_CASH_ACCT'), 'top')+20, findPos(\$('DEFAULT_CASH_ACCT'), 'left')+1);key_list('payables', 'DEFAULT_CASH_ACCT', 'DEFAULT_CASH_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
											<td style=\"padding:10px 25px;width:33%;vertical-align:top;\">
												What is the default Work In Progress account?
												<div style=\"margin-left:5px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['DEFAULT_WIP_ACCT'] || defined('DEFAULT_WIP_ACCT'))
													$this->fetch_account_record(($_POST['DEFAULT_WIP_ACCT'] ? $_POST['DEFAULT_WIP_ACCT'] : (defined('DEFAULT_WIP_ACCT') ? DEFAULT_WIP_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=DEFAULT_WIP_ACCT",
																			"id=err_DEFAULT_WIP_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'DEFAULT_WIP_ACCT', 'DEFAULT_WIP_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'DEFAULT_WIP_ACCT', 'DEFAULT_WIP_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('SALES_TAX_PAYABLE_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("DEFAULT_WIP_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('DEFAULT_WIP_ACCT'), 'top')+20, findPos(\$('SALES_TAX_PAYABLE'), 'left')+1);key_list('payables', 'DEFAULT_WIP_ACCT', 'DEFAULT_WIP_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
										</tr>
									</table>
								</fieldset>
								<fieldset style=\"margin-top:10px;\">
									<legend>Miscellaneous Vendor Charges &amp; Fees</legend>
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
										<tr>
											<td style=\"padding:10px 25px;\" colspan=\"2\">
												When DealerChoice finds miscellaneous vendor charges &amp; fees such as fuel surcharges and freight fees, those fees
												must be tied against a valid COGS and Income account. Please assign those defaults below:
											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;width:50%;\">
												Default income account for Small Order Fees:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['SMALL_ORDER_FEE_INCOME_ACCT'] || defined('SMALL_ORDER_FEE_INCOME_ACCT'))
													$this->fetch_account_record(($_POST['SMALL_ORDER_FEE_INCOME_ACCT_hash'] ? $_POST['SMALL_ORDER_FEE_INCOME_ACCT_hash'] : (defined('SMALL_ORDER_FEE_INCOME_ACCT') ? SMALL_ORDER_FEE_INCOME_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=SMALL_ORDER_FEE_INCOME_ACCT",
																			"id=err_SMALL_ORDER_FEE_INCOME_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'SMALL_ORDER_FEE_INCOME_ACCT', 'SMALL_ORDER_FEE_INCOME_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'SMALL_ORDER_FEE_INCOME_ACCT', 'SMALL_ORDER_FEE_INCOME_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('SMALL_ORDER_FEE_INCOME_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("SMALL_ORDER_FEE_INCOME_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('SMALL_ORDER_FEE_INCOME_ACCT'), 'top')+20, findPos(\$('SMALL_ORDER_FEE_INCOME_ACCT'), 'left')+1);key_list('payables', 'SMALL_ORDER_FEE_INCOME_ACCT', 'SMALL_ORDER_FEE_INCOME_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
											<td style=\"padding:10px 25px;width:50%;vertical-align:top;\">
												Default COGS account for Small Order Fees:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['SMALL_ORDER_FEE_COGS_ACCT'] || defined('SMALL_ORDER_FEE_COGS_ACCT'))
													$this->fetch_account_record(($_POST['SMALL_ORDER_FEE_COGS_ACCT_hash'] ? $_POST['SMALL_ORDER_FEE_COGS_ACCT_hash'] : (defined('SMALL_ORDER_FEE_COGS_ACCT') ? SMALL_ORDER_FEE_COGS_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=SMALL_ORDER_FEE_COGS_ACCT",
																			"id=err_SMALL_ORDER_FEE_COGS_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'SMALL_ORDER_FEE_COGS_ACCT', 'SMALL_ORDER_FEE_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'SMALL_ORDER_FEE_COGS_ACCT', 'SMALL_ORDER_FEE_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('SMALL_ORDER_FEE_COGS_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("SMALL_ORDER_FEE_COGS_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('SMALL_ORDER_FEE_COGS_ACCT'), 'top')+20, findPos(\$('SMALL_ORDER_FEE_COGS_ACCT'), 'left')+1);key_list('payables', 'SMALL_ORDER_FEE_COGS_ACCT', 'SMALL_ORDER_FEE_COGS_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;width:50%;\">
												Default income account for Freight Fees:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['FREIGHT_INCOME_ACCT'] || defined('FREIGHT_INCOME_ACCT'))
													$this->fetch_account_record(($_POST['FREIGHT_INCOME_ACCT_hash'] ? $_POST['FREIGHT_INCOME_ACCT_hash'] : (defined('FREIGHT_INCOME_ACCT') ? FREIGHT_INCOME_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=FREIGHT_INCOME_ACCT",
																			"id=err_FREIGHT_INCOME_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'FREIGHT_INCOME_ACCT', 'FREIGHT_INCOME_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'FREIGHT_INCOME_ACCT', 'FREIGHT_INCOME_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('FREIGHT_INCOME_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("FREIGHT_INCOME_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('FREIGHT_INCOME_ACCT'), 'top')+20, findPos(\$('FREIGHT_INCOME_ACCT'), 'left')+1);key_list('payables', 'FREIGHT_INCOME_ACCT', 'FREIGHT_INCOME_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
											<td style=\"padding:10px 25px;width:50%;vertical-align:top;\">
												Default COGS account for Freight Fees:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['FREIGHT_COGS_ACCT'] || defined('FREIGHT_COGS_ACCT'))
													$this->fetch_account_record(($_POST['FREIGHT_COGS_ACCT_hash'] ? $_POST['FREIGHT_COGS_ACCT_hash'] : (defined('FREIGHT_COGS_ACCT') ? FREIGHT_COGS_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=FREIGHT_COGS_ACCT",
																			"id=err_FREIGHT_COGS_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'FREIGHT_COGS_ACCT', 'FREIGHT_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'FREIGHT_COGS_ACCT', 'FREIGHT_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('FREIGHT_COGS_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("FREIGHT_COGS_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('FREIGHT_COGS_ACCT'), 'top')+20, findPos(\$('FREIGHT_COGS_ACCT'), 'left')+1);key_list('payables', 'FREIGHT_COGS_ACCT', 'FREIGHT_COGS_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;width:50%;\">
												Default income account for Fuel Surcharge Fees:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['FUEL_SURCHARGE_INCOME_ACCT'] || defined('FUEL_SURCHARGE_INCOME_ACCT'))
													$this->fetch_account_record(($_POST['FUEL_SURCHARGE_INCOME_ACCT_hash'] ? $_POST['FUEL_SURCHARGE_INCOME_ACCT_hash'] : (defined('FUEL_SURCHARGE_INCOME_ACCT') ? FUEL_SURCHARGE_INCOME_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=FUEL_SURCHARGE_INCOME_ACCT",
																			"id=err_FUEL_SURCHARGE_INCOME_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'FUEL_SURCHARGE_INCOME_ACCT', 'FUEL_SURCHARGE_INCOME_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'FUEL_SURCHARGE_INCOME_ACCT', 'FUEL_SURCHARGE_INCOME_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('FUEL_SURCHARGE_INCOME_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("FUEL_SURCHARGE_INCOME_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('FUEL_SURCHARGE_INCOME_ACCT'), 'top')+20, findPos(\$('FUEL_SURCHARGE_INCOME_ACCT'), 'left')+1);key_list('payables', 'FUEL_SURCHARGE_INCOME_ACCT', 'FUEL_SURCHARGE_INCOME_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
											<td style=\"padding:10px 25px;width:50%;vertical-align:top;\">
												Default COGS account for Fuel Surcharge Fees:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['FUEL_SURCHARGE_COGS_ACCT'] || defined('FUEL_SURCHARGE_COGS_ACCT'))
													$this->fetch_account_record(($_POST['FUEL_SURCHARGE_COGS_ACCT_hash'] ? $_POST['FUEL_SURCHARGE_COGS_ACCT_hash'] : (defined('FUEL_SURCHARGE_COGS_ACCT') ? FUEL_SURCHARGE_COGS_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=FUEL_SURCHARGE_COGS_ACCT",
																			"id=err_FUEL_SURCHARGE_COGS_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'FUEL_SURCHARGE_COGS_ACCT', 'FUEL_SURCHARGE_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'FUEL_SURCHARGE_COGS_ACCT', 'FUEL_SURCHARGE_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('FUEL_SURCHARGE_COGS_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("FUEL_SURCHARGE_COGS_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('FUEL_SURCHARGE_COGS_ACCT'), 'top')+20, findPos(\$('FUEL_SURCHARGE_COGS_ACCT'), 'left')+1);key_list('payables', 'FUEL_SURCHARGE_COGS_ACCT', 'FUEL_SURCHARGE_COGS_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
										</tr>
										<tr>
											<td style=\"padding:10px 25px;width:50%;\">
												Default income account for CBD Fees:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['CBD_INCOME_ACCT'] || defined('CBD_INCOME_ACCT'))
													$this->fetch_account_record(($_POST['CBD_INCOME_ACCT_hash'] ? $_POST['CBD_INCOME_ACCT_hash'] : (defined('CBD_INCOME_ACCT') ? CBD_INCOME_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=CBD_INCOME_ACCT",
																			"id=err_CBD_INCOME_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'CBD_INCOME_ACCT', 'CBD_INCOME_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'CBD_INCOME_ACCT', 'CBD_INCOME_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('CBD_INCOME_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("CBD_INCOME_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('CBD_INCOME_ACCT'), 'top')+20, findPos(\$('CBD_INCOME_ACCT'), 'left')+1);key_list('payables', 'CBD_INCOME_ACCT', 'CBD_INCOME_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
											<td style=\"padding:10px 25px;width:50%;vertical-align:top;\">
												Default COGS account for CBD Fees:
												<div style=\"margin-left:25px;padding-top:5px;padding-bottom:10px\">";
												unset($this->current_account);
												if ($_POST['CBD_COGS_ACCT'] || defined('CBD_COGS_ACCT'))
													$this->fetch_account_record(($_POST['CBD_COGS_ACCT_hash'] ? $_POST['CBD_COGS_ACCT_hash'] : (defined('CBD_COGS_ACCT') ? CBD_COGS_ACCT : NULL)));
													$tbl .=
													$this->form->text_box("name=CBD_COGS_ACCT",
																			"id=err_CBD_COGS_ACCT",
																			"value=".($this->current_account['account_hash'] ? ($this->current_account['account_no'] ? $this->current_account['account_no']." - " : NULL).$this->current_account['account_name'] : NULL),
																			"autocomplete=off",
																			"size=25",
																			(!$this->p->ck(get_class($this), 'E', 'config') ? "readonly" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onFocus=if(this.value){select();}position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('payables', 'CBD_COGS_ACCT', 'CBD_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyUp=if(ka==false && this.value){key_call('payables', 'CBD_COGS_ACCT', 'CBD_COGS_ACCT_hash', 1);}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onKeyDown=if(event.keyCode!=9){clear_values('CBD_COGS_ACCT_hash');}" : NULL),
																			($this->p->ck(get_class($this), 'E', 'config') ? "onBlur=key_clear();" : NULL)).
													$this->form->hidden(array("CBD_COGS_ACCT_hash" => ($this->current_account['account_hash'] ? $this->current_account['account_hash'] : NULL))).($this->p->ck(get_class($this), 'E', 'config') ? "
													<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('CBD_COGS_ACCT'), 'top')+20, findPos(\$('CBD_COGS_ACCT'), 'left')+1);key_list('payables', 'CBD_COGS_ACCT', 'CBD_COGS_ACCT_hash', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>" : NULL)."
												</div>
											</td>
										</tr>
									</table>
								</fieldset>
							</div>
						</div >
					</div>
				</td>
			</tr>
		</table>".
		$this->form->close_form();

		if ($local)
			return array($inner_tbl, $jscript);


		$this->content['html']['place_holder'] = $tbl;
	}

    function close_period() {
        $this->unlock("_");
        $this->load_class_navigation();
        $p = ($_POST['p'] ? $_POST['p'] : $this->ajax_vars['p']);
        $order = ($_POST['order'] ? $_POST['order'] : $this->ajax_vars['order']);
        $order_dir = ($_POST['order_dir'] ? $_POST['order_dir'] : $this->ajax_vars['order_dir']);
        $period = $this->ajax_vars['period'];
        $fiscal_year = $this->ajax_vars['fiscal_year'];

        if ($open = $this->ajax_vars['open'])
            $this->db->query("UPDATE `period_dates`
                              SET `closed` = 0
                              WHERE `obj_id` = '$open'");


        if (!$fiscal_year) {
            $period_info = get_period_info(date("Y-m-d"));
            $fiscal_year = $period_info['fiscal_year'];
        }

        $date = "2007-07-17";
        $result = $this->db->query("SELECT `obj_id` , `closed` , `fiscal_year` , `period` , `period_end` , `period_start`
                                    FROM `period_dates`
                                    WHERE `fiscal_year` = '".$fiscal_year."'
                                    ORDER BY `period` ASC");
        while ($row = $this->db->fetch_assoc($result))
            $open_periods[$row['fiscal_year']][$row['period']] = array('close_date'     => $row['period_end'],
                                                                       'period_start'   => $row['period_start'],
                                                                       'closed'         => $row['closed'],
                                                                       'obj_id'         => $row['obj_id']);

        if (!$open_periods)
            unset($fiscal_year);

        $tbl .= $this->form->form_tag().$this->form->hidden(array('p' => $p, 'order' => $order, 'order_dir' => $order_dir, 'fiscal_year' => $fiscal_year))."
        <div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
            <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                <p id=\"feedback_message\"></p>
        </div>
        <table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
            <tr>
                <td style=\"padding:0;\" id=\"inner_tbl_holder\">
                     <div style=\"background-color:#ffffff;height:100%;\">
                        <div style=\"margin:15px 35px;\">
                            <h3 style=\"margin-bottom:5px;color:#00477f;\">Perform Period Closings</h3>
                            <small style=\"margin-left:20px;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'show_journal', 'p=$p', 'order=$order', 'order_dir=$order_dir')\"><-- Back</a></small>
                            <div style=\"margin:20px;width:800px;padding:0;\">
                                <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
                                    <tr>
                                        <td style=\"padding:10px 25px;\" >
                                                What period would you like to close?
                                                <div style=\"margin-left:25px;padding-top:10px;font-weight:bold;\">
                                                    ".($fiscal_year ? "
                                                        Fiscal Year ".$fiscal_year : "Current fiscal year period layout has not been configured.")."
                                                    <span style=\"padding-left:10px;font-weight:normal;\">
                                                        [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'close_period', 'fiscal_year=".date("Y", strtotime($fiscal_year."-01-01 -1 year"))."')\">previous year</a></small>]
                                                        &nbsp;&nbsp;
                                                        [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'close_period', 'fiscal_year=".date("Y", strtotime($fiscal_year."-01-01 +1 year"))."')\">next year</a></small>]
                                                    </span>

                                                </div>";

                                            if (is_array($open_periods)) {
                                                $year =& $open_periods[$fiscal_year];
                                                reset($year);
                                                $first_period = current($year);
                                                end($year);
                                                $last_period = current($year);

                                                unset($year);
                                                $closed = 0;
                                                while (list($year, $period) = each($open_periods)) {
                                                    reset($period);
                                                    while (list($period_no, $closing) = each($period)) {
                                                        $i++;
                                                        if ($closing['closed'])
                                                            $closed++;

                                                        $tbl .= "
                                                        <div style=\"margin-left:50px;padding-top:10px;\">
                                                            ".($closing['closed'] ?
                                                                "<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"if(confirm('Are you sure you want to reopen this period?')){agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'close_period', 'fiscal_year=$fiscal_year', 'open=".$closing['obj_id']."');}\"><img src=\"images/check.gif\" border=\"0\" title=\"Click to reopen this period.\" /></a>" : $this->form->checkbox("name=period[]", "value=".$closing['obj_id']))."
                                                            &nbsp;
                                                            Period ".$period_no." ending ".date(DATE_FORMAT, strtotime($closing['close_date']))."
                                                        </div>";
                                                    }
                                                }

                                                if ($closed == count($open_periods[$fiscal_year])) {

                                                    $r = $this->db->query("SELECT SUM(journal.debit - journal.credit) AS total_expense
                                                                           FROM journal
                                                                           LEFT JOIN accounts ON accounts.account_hash = journal.account_hash
                                                                           WHERE journal.date BETWEEN '".$first_period['period_start']."' AND '".$last_period['close_date']."' AND (accounts.account_type = 'EX' || accounts.account_type = 'CG')");
                                                    $expense_balance = $this->db->result($r, 0, 'total_expense');
                                                    if ($expense_balance != 0) {
                                                        $r = $this->db->query("SELECT SUM(journal.credit - journal.debit) AS total_income
                                                                               FROM journal
                                                                               LEFT JOIN accounts ON accounts.account_hash = journal.account_hash
                                                                               WHERE journal.date BETWEEN '".$first_period['period_start']."' AND '".$last_period['close_date']."' AND accounts.account_type = 'IN'");
                                                        $income_balance = $this->db->result($r, 0, 'total_income');
                                                    }
                                                    if (bccomp($expense_balance, 0, 2) == 0 && bccomp($expense_balance, 0, 2) == 0)
                                                        $year_balanced = 1;
                                                }

                                                $tbl .= "
                                                <div style=\"margin-top:25px;margin-left:25px;\">
                                                    ".$this->form->button("value=Close Period", "onClick=submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_close_period')").($closed == count($open_periods[$fiscal_year]) ?
                                                        "&nbsp;&nbsp;".$this->form->button("value=Close Year",
                                                                                           "onClick=agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'close_year', 'fiscal_year=".$fiscal_year."');",
                                                                                           ($year_balanced ? "disabled" : NULL),
                                                                                           ($year_balanced ? "title=There are no accounts in the current year that require closing entries. The year ".$fiscal_year." has already been closed." : NULL),
                                                                                           "style=width:115px;") : NULL)."
                                                </div>";
                                            } else
                                                $tbl .= "
                                                <div style=\"margin-left:25px;padding-top:10px;\">".($fiscal_year ? "
                                                    There are no open periods available for closing at this time." : "<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'config', 'p=$p', 'order=$order', 'order_dir=$order_dir');\" class=\"link_standard\">Click here to configure fiscal year period layout.</a>")."
                                                </div>";
                                            $tbl .= "
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>".
        $this->form->close_form();

        $this->content['html']['place_holder'] = $tbl;
        return;
    }

	function fetch_journal($start, $end, $order_by=NULL, $order_dir=NULL) {

		if ( ! $end )
    		$end = MAIN_PAGNATION_NUM;

		if ( $this->active_search ) {

			$result = $this->db->query("SELECT
    			                            t1.query,
    			                            t1.total
								  		FROM search t1
								  		WHERE t1.search_hash = '{$this->active_search}'");
			if ( $row = $this->db->fetch_assoc($result) ) {

    			$this->journal_total = $row['total'];
    			$sql_str = unserialize( base64_decode($row['query']) );

                $sql = $sql_str['sql'];
			}

		}

		$this->journal_info = array();

        $r = $this->db->query("SELECT
                                   t1.audit_id,
                                   t1.trans_type,
                                   t1.proposal_hash
                               FROM journal t1 " .
                               ( $sql ?
    							   "LEFT JOIN proposals t2 ON t2.proposal_hash = t1.proposal_hash
                                   LEFT JOIN customer_invoice t3 ON t3.invoice_hash = t1.ar_invoice_hash
    							   LEFT JOIN customer_payment t4 ON t4.payment_hash = t1.ar_payment_hash
    							   LEFT JOIN vendor_payables t5 ON t5.invoice_hash = t1.ap_invoice_hash
    							   LEFT JOIN vendor_payment t6 ON t6.payment_hash = t1.ap_payment_hash
                                   LEFT JOIN customer_credit t7 ON t7.credit_hash = t1.ar_credit_hash " : NULL
                               ) .
                               ( $sql || $this->proposal_hash ?
                                   "WHERE " .
                                   ( $this->proposal_hash ?
                                       "t1.proposal_hash = '{$this->proposal_hash}' " .
                                       ( $sql ?
                                           "AND " : NULL
                                       ) : NULL
                                   ) . $sql : NULL
                               ) . "
                               GROUP BY t1.audit_id
                               ORDER BY t1.date DESC, t1.obj_id DESC " .
                               ( is_numeric($start) ?
                                   "LIMIT $start, $end" : NULL
                               ));
		while ( $row = $this->db->fetch_assoc($r) )
            array_push($this->journal_info, $row);

		return;
	}

	function fetch_journal_trasactions($audit_id, $show_profit=false) {

		$result = $this->db->query("SELECT
                                		t1.*,
                                		t1.check_no AS payment_check_no,
                                		t2.account_name,
                                		t2.account_no,
                                		t3.proposal_no,
                                		t3.proposal_descr,
                                		t4.invoice_no AS ar_invoice_no,
                                        t5.invoice_no AS ap_invoice_no,
                                        t6.customer_name,
                                        t7.vendor_name,
                                        t8.full_name
									FROM journal t1
									LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
									LEFT JOIN proposals t3 ON t3.proposal_hash = t1.proposal_hash
									LEFT JOIN customer_invoice t4 ON t4.invoice_hash = t1.ar_invoice_hash
									LEFT JOIN vendor_payables t5 ON t5.invoice_hash = t1.ap_invoice_hash
									LEFT JOIN customers t6 ON t6.customer_hash = t1.customer_hash
									LEFT JOIN vendors t7 ON t7.vendor_hash = t1.vendor_hash
									LEFT JOIN users t8 ON t8.id_hash = t1.id_hash
									WHERE t1.audit_id = '$audit_id' " .
									( ! $show_profit ?
    									"AND t1.account_hash != '" . DEFAULT_PROFIT_ACCT . "'" : NULL
									) . "
									ORDER BY t1.obj_id ASC");
		while ($row = $this->db->fetch_assoc($result)) {

			if ( $row['full_name'] )
				$row['full_name'] = htmlspecialchars( stripslashes($row['full_name']) );

			$journal_info[] = $row;
		}

		return $journal_info;
	}

	function show_journal() {

		$this->unlock("_");
		$this->load_class_navigation();
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

		$total_1 = $this->journal_total;
		$num_pages = ceil($this->journal_total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ( $end > $this->journal_total )
			$end = $this->journal_total;

		$order_by_ops = array(
    		"timestamp"			=>	"journal.timestamp",
			"date"				=>	"journal.date",
			"account_name"		=>	"accounts.account_name",
			"audit_id"			=>	"journal.audit_id",
			"type"				=>	"journal.trans_type"
		);

		$this->active_search = $this->ajax_vars['active_search'];
		$tbl = $this->fetch_journal($start_from, $end, "journal.obj_id", ($order_dir ? $order_dir : "DESC"));
		//if ($this->active_search)
			//$this->page_pref =& $this->search_vars;

		if ($this->journal_total != $total_1) {
			$num_pages = ceil($this->journal_total / MAIN_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = MAIN_PAGNATION_NUM * ($p - 1);

			$end = $start_from + MAIN_PAGNATION_NUM;
			if ($end > $this->journal_total)
				$end = $this->journal_total;

		}

		$tbl .= ($this->active_search ? "
		<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
		<div style=\"padding-top:8px;padding-left:25px;padding-bottom:10px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'show_journal');\">Show All</a>]</div>" : NULL)."
		<table cellpadding=\"0\" cellspacing=\"3\" width=\"100%\">
			<tr>
				<td style=\"padding-left:10px;padding-top:0\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\" >
						<tr>
							<td style=\"font-weight:bold;vertical-align:bottom;\" colspan=\"8\">" .
	                        	( $this->journal_total ?
									"<div style=\"float:right;font-weight:normal;padding-right:10px;text-align:right;\">" . paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'accounting', 'show_journal', $order, $order_dir, "'active_search={$this->active_search}', 'popup_id={$this->popup_id}', 'show=" . ( is_array($show) ? implode("|", $show) : $show ) . "'") . "</div>
									Showing " . ( $start_from + 1 ) . " - " .
	                            	( $start_from + MAIN_PAGNATION_NUM > $this->journal_total ?
	                                	$this->journal_total : $start_from + MAIN_PAGNATION_NUM
	                                ) . " of {$this->journal_total} Journal Entries." : NULL
                                ) . "
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
                                    ( $this->p->ck(get_class($this), 'A', 'journal') ?
    									"<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'new_journal_entry', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/new.gif\" title=\"Create a new journal entry\" border=\"0\" /></a>&nbsp;&nbsp;" : NULL
                                    ) . "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'search_journal_entries', 'popup_id=line_item', 'active_search={$this->active_search}');\"><img src=\"images/search.gif\" title=\"Search journal\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;&nbsp;" .
                                    ( $this->p->ck(get_class($this), 'E', 'period') ?
    									"<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'close_period', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/close_period.gif\" title=\"Perform period closing functions\" border=\"0\" /></a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" : NULL
                                    ) .
                                    ( $this->p->ck(get_class($this), 'V', 'config') ?
    									"<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'config', 'p=$p', 'order=$order', 'order_dir=$order_dir');\"><img src=\"images/configure_acct.gif\" title=\"Configure your business cycle and settings\" border=\"0\" /></a>&nbsp;&nbsp;" : NULL
                                    ) . "
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"width:175px;vertical-align:bottom;padding-left:10px;\">Date</td>
							<td style=\"vertical-align:bottom;\">Type</td>
							<td style=\"vertical-align:bottom;\">ID</td>
							<td >Proposal</td>
							<td >Account</td>
							<td >Memo</td>
							<td class=\"num_field\" >Debit</td>
							<td class=\"num_field\" style=\"padding-right:10px;\">Credit</td>
						</tr>";

						for ( $i = 0; $i < ($end - $start_from); $i++ ) {

							$journal_info = $this->fetch_journal_trasactions($this->journal_info[$i]['audit_id'], 1);
							unset($title, $show_title, $to_be_printed, $a_1, $a_2, $onClick);

                            $b++;
                            $dr = $cr = $c = $total_rows = 0;
							for ( $j = 0; $j < count($journal_info); $j++ ) {

								if ( $journal_info[$j]['account_hash'] != DEFAULT_PROFIT_ACCT || ( $journal_info[$j]['trans_type'] == 'GJ' && ! $journal_info[$j]['auto'] ) )
								    $total_rows++;
							}

							for ( $j = 0; $j < count($journal_info); $j++ ) {

								if ( $journal_info[$j]['account_hash'] != DEFAULT_PROFIT_ACCT || ($journal_info[$j]['trans_type'] == 'GJ' && ! $journal_info[$j]['auto'] ) ) {

									if ( ! $show_title ) {

										switch ( $journal_info[$j]['trans_type'] ) {

											case 'AR':
											if ( $journal_info[$j]['ar_invoice_hash'] )
												$onClick = "onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash={$journal_info[$j]['ar_invoice_hash']}', 'popup_id=ar_invoice_win');\"";

											$title = "Invoice" .
											( $journal_info[$j]['ar_invoice_no'] ?
    											": " .
    											( $onClick ?
    											    "<a href=\"javascript:void(0);\" class=\"link_standard\" $onClick>" : NULL
    											) . $journal_info[$j]['ar_invoice_no'] .
    											( $onClick ?
    											    "</a>" : NULL
    											) : NULL
    										);
										    break;

	                                        case 'CR':
	                                        $title = "Receipt";
	                                        if ( $journal_info[$j]['ar_invoice_hash'] ) {

	                                        	$r = $this->db->query("SELECT t1.type
	                                        	                       FROM customer_invoice t1
	                                        	                       WHERE t1.invoice_hash = '{$journal_info[$j]['ar_invoice_hash']}'");
	                                        	$t = $this->db->result($r, 0, 'type');
	                                        	if ( $t )
	                                               $onClick = "onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', '" . ( $t == 'I' ? "edit_invoice" : "new_deposit" ) . "', 'invoice_hash={$journal_info[$j]['ar_invoice_hash']}', 'payment_hash={$journal_info[$j]['ar_payment_hash']}', 'popup_id=ar_invoice_win', 'tab_to=tcontent2');\"";

	                                            $title .=
	                                            ( $journal_info[$j]['ar_invoice_no'] ?
    	                                            ": " .
    	                                            ( $onClick ?
    	                                                "<a href=\"javascript:void(0);\" class=\"link_standard\" $onClick>" : NULL
    	                                            ) . $journal_info[$j]['ar_invoice_no'] .
    	                                            ( $onClick ?
    	                                                "</a>" : NULL
    	                                            ) : NULL
    	                                        );
	                                        }
	                                        break;

	                                        case 'AP':
	                                        if ($journal_info[$j]['ap_invoice_hash'])
	                                            $onClick = "onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash=".$journal_info[$j]['ap_invoice_hash']."', 'popup_id=ap_invoice_win');\"";

                                            $title = "Bill".($journal_info[$j]['ap_invoice_no'] ?
                                                         ": ".($onClick ?
                                                                 "<a href=\"javascript:void(0);\" class=\"link_standard\" ".$onClick.">" : NULL).
                                                         $journal_info[$j]['ap_invoice_no'].($onClick ?
                                                             "</a>" : NULL) : NULL);

	                                        break;

											case 'CD':
											$title = "Payment";
											if ($journal_info[$j]['ap_payment_hash']) {
												if ($journal_info[$j]['payment_check_no']) {
												    $onClick = "onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash=".$journal_info[$j]['ap_invoice_hash']."', '', 'popup_id=ap_invoice_win');\"";
											        $title .= ($journal_info[$j]['payment_check_no'] ?
											                     ": ".($onClick ?
											                         "<a href=\"javascript:void(0);\" class=\"link_standard\" $onClick>" : NULL).
											                     $journal_info[$j]['payment_check_no'].($onClick ?
											                         "</a>" : NULL) : NULL);


												} else {
											        $r = $this->db->query("SELECT COUNT(*) AS Total
											                               FROM `vendor_pay_queue`
											                               WHERE `payment_hash` = '".$journal_info[$j]['ap_payment_hash']."'");
											        if ($this->db->result($r))
											            $title .= ": <i>Pending</i>";
											    }
											} elseif ($journal_info[$i]['check_no'])
											    $title .= ": ".$journal_info[$i]['check_no'];

											break;

											case 'AD':
                                            if ($journal_info[$j]['ar_invoice_hash']) {
                                            	$title = "A/R ";
                                                $onClick = "onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash=".$journal_info[$j]['ar_invoice_hash']."', 'popup_id=ar_invoice_win');\"";
                                            } elseif ($journal_info[$j]['ap_invoice_hash']) {
                                                $title = "A/P ";
                                            	$onClick = "onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash=".$journal_info[$j]['ap_invoice_hash']."', 'popup_id=ap_invoice_win');\"";
                                            }
                                            if ($journal_info[$j]['ar_credit_hash']) {
                                                $title = "A/R Credit ";
                                                $onClick = "onClick=\"agent.call('customer_credits', 'sf_loadcontent', 'show_popup_window', 'edit_credit', 'credit_hash=".$journal_info[$j]['ar_credit_hash']."', 'popup_id=ar_credit_win');\"";
                                            }
                                            $title .= "Adjustment";
                                            $title .= ($journal_info[$j]['ar_invoice_no'] || $journal_info[$j]['ap_invoice_no'] ?
                                                         ": ".($onClick ?
                                                                "<a href=\"javascript:void(0);\" class=\"link_standard\" ".$onClick.">" : NULL).
                                                         ($journal_info[$j]['ar_invoice_no'] ?
                                                            $journal_info[$j]['ar_invoice_no'] : $journal_info[$j]['ap_invoice_no']).($onClick ?
                                                             "</a>" : NULL) : NULL);
                                            if ($journal_info[$j]['ar_credit_hash'])
                                                $title = "<a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits', 'sf_loadcontent', 'show_popup_window', 'edit_credit', 'credit_hash=".$journal_info[$j]['ar_credit_hash']."', 'popup_id=ar_credit_win');\" class=\"link_standard\">A/R Credit Adjustment</a>";

                                            break;

											case 'GJ':
											$title = "General Journal";
											break;

											case 'ME':
											$title = "Memo Cost";
											break;

											case 'PR':
											$title = "Payroll";
											break;

											case 'VC':
											$title = "Vendor Credit";
											break;

											case 'CC':
                                            $title = ($journal_info[$j]['ar_credit_hash'] ?
                                                         "<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('customer_credits', 'sf_loadcontent', 'show_popup_window', 'edit_credit', 'credit_hash=".$journal_info[$j]['ar_credit_hash']."', 'popup_id=ar_credit_win');\">" : NULL).
                                                     "A/R Credit".
                                                    ($journal_info[$j]['ar_credit_hash'] ?
                                                         "</a>" : NULL);
											break;

											case 'CL':
											$title = "Fiscal Year Closing";
											break;
										}

									}
									$dr = $dr + $journal_info[$j]['debit'];
									$cr = $cr + $journal_info[$j]['credit'];
									if(strpos($journal_info[$j]['memo'], 'Invoice reversal') === 0 && $cr != $dr){
										$journal_info[$j]['credit'] = $journal_info[$j]['credit'] + .01;
										$cr = $cr + .01;
									}
									$tbl .= "
									<tr >
										<td style=\"vertical-align:top;\">".(!$show_title ?
										    date(DATE_FORMAT, strtotime($journal_info[$j]['date']))."&nbsp;" : ($c == 1 && ($journal_info[$j]['customer_name'] || $journal_info[$j]['vendor_name']) ?
										        ($journal_info[$j]['customer_name'] ?
										            "<i>Customer: ".$journal_info[$j]['customer_name']."</i>" : "<i>Vendor: ".$journal_info[$j]['vendor_name']."</i>") : NULL).
										        (($c == 1 && !$journal_info[$j]['customer_name'] && !$journal_info[$j]['vendor_name']) || ($c == 2 && ($journal_info[$j]['customer_name'] || $journal_info[$j]['vendor_name'])) && $journal_info[$j]['full_name'] ?
										            "<div style=\"font-style:italic;margin-left:8px;\">".$journal_info[$j]['full_name'].($journal_info[$j]['timestamp'] ?
										                "&nbsp".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $journal_info[$j]['timestamp']) : NULL)."
										            </div>" : NULL))."
										</td>".(!$show_title ? "
										<td style=\"vertical-align:top;\" rowspan=\"".($total_rows + 1)."\">".$title."</td>
										<td style=\"vertical-align:top;\" rowspan=\"".($total_rows + 1)."\">".$journal_info[$j]['audit_id']."</td>" : NULL)."
										<td>".(!$show_title ?
											($journal_info[$j]['proposal_hash'] ?
											    "<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_proposal', 'proposal_hash=".$journal_info[$j]['proposal_hash']."', 'popup_id=proposal_win', 'from_report=1');\" title=\"View proposal\">" : NULL).
											$journal_info[$j]['proposal_no'] : NULL).($journal_info[$j]['proposal_hash'] ?
											    "</a>" : NULL)."
										</td>
										<td style=\"vertical-align:bottom;\">".($journal_info[$j]['account_no'] ?
											$journal_info[$j]['account_no']." - " : NULL).$journal_info[$j]['account_name']."
										</td>
										<td style=\"vertical-align:bottom;\">".stripslashes($journal_info[$j]['memo'])."</td>
										<td style=\"vertical-align:bottom;\" class=\"num_field\">".($journal_info[$j]['debit'] > 0 ?
											"$".number_format(trim_decimal($journal_info[$j]['debit']), 2) : NULL)."
										</td>
										<td style=\"vertical-align:bottom;\" class=\"num_field\" style=\"padding-right:10px;\">".($journal_info[$j]['credit'] > 0 ?
											"$".number_format(trim_decimal($journal_info[$j]['credit']), 2) : NULL)."
										</td>
									</tr>";
									$show_title = 1;
									$c++;
								}
							}
							$tbl .= "
							<tr>
								<td colspan=\"6\" style=\"border-bottom:1px solid #cccccc;\">".($c == 2 && ($journal_info[$j-1]['customer_name'] || $journal_info[$j-1]['vendor_name']) && $journal_info[$j-1]['full_name'] ?
                                    "<div style=\"font-style:italic;margin-left:8px;\">".$journal_info[$j-1]['full_name'].($journal_info[$j-1]['timestamp'] ?
                                        "&nbsp;".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $journal_info[$j-1]['timestamp']) : NULL)."
                                    </div>" : "&nbsp;")."
								</td>
								<td class=\"num_field\" style=\"border-top:1px solid #8c8c8c;font-weight:bold;font-size:9pt;border-bottom:1px solid #cccccc;\">$".number_format(_round($dr, 2), 2)."</td>
								<td class=\"num_field\" style=\"border-top:1px solid #8c8c8c;font-weight:bold;font-size:9pt;border-bottom:1px solid #cccccc;\" title=\"Internal audit id: ".$this->journal_info[$i]['audit_id']."\">$".number_format(_round($cr, 2), 2)."</td>
							</tr>";

							next($this->journal_info);
						}

						if (!$this->journal_total)
							$tbl .= "
							<tr >
								<td colspan=\"8\">Your journal is empty.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		$this->content['html']['place_holder'] = $tbl;
	}

	function search_journal_entries() {
		$date = date("U") - 3600;
		$result = $this->db->query("DELETE FROM `search`
							  		WHERE `timestamp` <= '$date'");

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Search General Journal";
		$this->content['focus'] = "audit_id";

        if ($this->ajax_vars['active_search']) {
            $result = $this->db->query("SELECT `search_str`
                                        FROM `search`
                                        WHERE `search_hash` = '".$this->ajax_vars['active_search']."'");
            $row = $this->db->fetch_assoc($result);
            $search_vars = permissions::load_search_vars($row['search_str']);
        }

		list($search_name, $search_hash) = $this->fetch_searches('journal');

		$this->fetch_accounts(0, $this->total);
		$acct_name[] = "All Accounts";
		$acct_hash[] = "";
		while (list($hash, $detail) = each($this->account_info)) {
			$acct_name[] = ($detail['account_info']['account_no'] ? $detail['account_info']['account_no']." : " : NULL).stripslashes($detail['account_info']['account_name']);
			$acct_hash[] = $detail['account_info']['account_hash'];
			if ($detail['children']) {
				for ($i = 0; $i < count($detail['children']); $i++) {
					$acct_name[] = "&nbsp;&nbsp;&nbsp;&nbsp;--".($detail['children'][$i]['account_no'] ? $detail['children'][$i]['account_no']." : " : NULL).stripslashes($detail['children'][$i]['account_name']);
					$acct_hash[] = $detail['children'][$i]['account_hash'];
				}
			}
		}

		if ($search_vars['customer_filter'] && !is_array($search_vars['customer_filter']))
            $search_vars['customer_filter'] = array($search_vars['customer_filter']);

        if ($search_vars['vendor_filter'] && !is_array($search_vars['vendor_filter']))
            $search_vars['vendor_filter'] = array($search_vars['vendor_filter']);

        if ($search_vars['proposal_filter'] && !is_array($search_vars['proposal_filter']))
            $search_vars['proposal_filter'] = array($search_vars['proposal_filter']);

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array('popup_id' 		=> $this->content['popup_controls']['popup_id'],
								  'detail_search' 	=> 1
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
							Filter your journal search criteria below:
						</div>
						<table>
							<tr>
								<td style=\"padding-left:15px;\">
									<fieldset>
										<legend>Check Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
    										".$this->form->text_box("name=check_no",
								                                    "value=".$search_vars['check_no'],
								                                    "style=width:200px;")."
    									</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\" rowspan=\"2\">
									<fieldset>
										<legend>Search By Proposal</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=proposal",
																	"value=",
																	"autocomplete=off",
																	"size=37",
																	"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('reports', 'proposal', 'proposal_hash', 1);}",
								                                    "onKeyUp=if(ka==false && this.value){key_call('reports', 'proposal', 'proposal_hash', 1);}",
																	"onBlur=key_clear();"
																	)."
											<div style=\"margin-top:15px;width:250px;height:45px;overflow:auto;display:block;background-color:#efefef;border:1px outset #cccccc;\" id=\"proposal_filter_holder\">";
                                            if (is_array($search_vars['proposal_filter'])) {
                                                for ($i = 0; $i < count($search_vars['proposal_filter']); $i++)
                                                    $tbl .= "<div id=\"proposal_".$search_vars['proposal_filter'][$i]."\" style=\"padding:5px 2px 0 5px;\">[<small><a href=\"javascript:void(0);\" onClick=\"remove_el(this.parentElement.parentElement)\" class=\"link_standard\" title=\"Remove from filter\">x</a></small>]&nbsp;".reports::proposal_no($search_vars['proposal_filter'][$i])."<input type=\"hidden\" id=\"proposal_filter\" name=\"proposal_filter[]\" value=\"".$search_vars['proposal_filter'][$i]."\" /></div>";
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
										<legend>Transaction Type</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
    										".$this->form->select("type",
																	array("Invoice", "Bill", "Cash Receipt", "Check", "General Journal", "Payroll", "Adjustment", "Memo Cost"),
																	$search_vars['type'],
																	array("AR", "AP", "CR", "CD", "GJ", "PR", "AD", "ME"))."
    									</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Matching Account: </legend>
										<div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->select("from_account[]",
																	$acct_name,
																	$search_vars['from_account'],
																	$acct_hash,
																	"multiple",
																	"size=5",
																	"style=width:200px;",
																	"blank=1")."
                                        </div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Search By Vendor</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=vendor",
																	"value=",
																	"autocomplete=off",
																	"size=37",
																	"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('reports', 'vendor', 'vendor_hash', 1);}",
																	"onKeyUp=if(ka==false && this.value){key_call('reports', 'vendor', 'vendor_hash', 1);}",
																	"onBlur=key_clear();"
																	)."
											<div style=\"margin-top:15px;width:250px;height:45px;overflow:auto;display:block;background-color:#efefef;border:1px outset #cccccc;\" id=\"vendor_filter_holder\">";
                                            if (is_array($search_vars['vendor_filter'])) {
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
									<fieldset style=\"width:90%;\">
										<legend>Date Range</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											<span id=\"date_start_holder\"></span>
											<span id=\"date_end_holder\"></span>";
										$this->content['jscript'][] = "
											setTimeout('DateInput(\'date_start\', false, \'YYYY-MM-DD\', \'".($search_vars['date_start'] ? $search_vars['date_start'] : NULL)."\', 1, \'date_start_holder".$this->popup_id."\', \'<span id=\"err1".$this->popup_id."\">From:</span>&nbsp;\')', 20);
											setTimeout('DateInput(\'date_end\', false, \'YYYY-MM-DD\', \'".($search_vars['date_end'] ? $search_vars['date_end'] : NULL)."\', 1, \'date_end_holder".$this->popup_id."\', \'<span id=\"err2".$this->popup_id."\">&nbsp;Thru:</span>&nbsp;\')', 41);";

										$tbl .= "
										</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\" rowspan=\"2\">
									<fieldset>
										<legend>Search By Customer</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=customer",
																	"value=",
																	"autocomplete=off",
																	"size=37",
																	"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('reports', 'customer', 'customer_hash', 1);}",
										                            "onKeyUp=if(ka==false && this.value){key_call('reports', 'customer', 'customer_hash', 1);}",
																	"onBlur=key_clear();"
																	)."
											<div style=\"margin-top:15px;width:250px;height:45px;overflow:auto;display:block;background-color:#efefef;border:1px outset #cccccc;\" id=\"customer_filter_holder\">";
                                            if (is_array($search_vars['customer_filter'])) {
                                                for ($i = 0; $i < count($search_vars['customer_filter']); $i++)
                                                    $tbl .= "<div id=\"customer_".$search_vars['customer_filter'][$i]."\" style=\"padding:5px 2px 0 5px;\">[<small><a href=\"javascript:void(0);\" onClick=\"remove_el(this.parentElement.parentElement)\" class=\"link_standard\" title=\"Remove from filter\">x</a></small>]&nbsp;".addslashes((strlen(reports::customer_name($search_vars['customer_filter'][$i])) > 25 ? substr(reports::customer_name($search_vars['customer_filter'][$i]), 0, 22)."..." : reports::customer_name($search_vars['customer_filter'][$i])))."<input type=\"hidden\" id=\"customer_filter\" name=\"customer_filter[]\" value=\"".$search_vars['customer_filter'][$i]."\" /></div>";
                                            }
                                        $tbl .= "
											</div>
										</div>
									</fieldset>
								</tr>
							</tr>
							<tr>
								<td style=\"padding-left:15px;padding-top:5px;vertical-align:top;\">
									<fieldset>
										<legend>Customer/Vendor Invoice No.</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=invoice_no",
																	"value=".$search_vars['invoice_no'],
																	"style=width:200px;")."
										</div>
									</fieldset>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<div style=\"float:right;text-align:right;padding-top:15px;margin-right:25px;\">
				".$this->form->button("value=Search", "id=primary", "onClick=submit_form($('popup_id').form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_search_journal');")."
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
								".$this->form->button("value=Go", "onClick=agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'show_journal', 'active_search='+\$F('saved_searches'));window.setTimeout('popup_windows[\'".$this->content['popup_controls']['popup_id']."\'].hide()', 1000);")."
								&nbsp;
								".$this->form->button("value=&nbsp;X&nbsp;", "title=Delete this saved search", "onClick=if(\$('saved_searches') && \$('saved_searches').options[\$('saved_searches').selectedIndex].value){if(confirm('Do you really want to delete your saved search?')){agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'purge_search', 's='+\$('saved_searches').options[\$('saved_searches').selectedIndex].value);\$('saved_searches').remove(\$('saved_searches').selectedIndex);}}")."
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

	function update_account_balance($account_balance) {
		# Trac #1415 - Replaced application level account balance updates with database trigger 'account_balance'
		$r = $this->db->query("SELECT COUNT(*)
		                       FROM information_schema.TRIGGERS
		                       WHERE TRIGGER_NAME = 'account_balance'");
		if ($this->db->result($r) == 1)
    		return true;

		if (!is_array($account_balance))
			return;

		while (list($account_hash, $no) = each($account_balance)) {
			$r = $this->db->query("SELECT account_types.account_type
								   FROM accounts
								   LEFT JOIN account_types ON account_types.type_code = accounts.account_type
								   WHERE accounts.account_hash = '".$account_hash."'");
			if ($account_action = $this->db->result($r, 0, 'account_type')) {
				//Update account balance
				$this->db->query("UPDATE `accounts`
								  SET accounts.balance =
								  (
								  	SELECT SUM(journal.".($account_action == 'DR' ?
								  		"debit" : "credit").") - SUM(journal.".($account_action == 'DR' ?
								  			"credit" : "debit").")
									FROM journal
									WHERE journal.account_hash = '".$account_hash."'
								  )
								  WHERE accounts.account_hash = '".$account_hash."'");
				if ($this->db->db_error) {
					$this->err = array ('errno'	=>	'M'.$this->db->db_errno,
										'err'	=>	$this->db->db_error);
					return false;
				}
			} else {
				$this->err = array ('errno'	=>	999,
									'err'	=>	"Unable to determine account type when updating account balance.");
				return false;
			}
		}

		return true;
	}

	function start_transaction() {

		$this->acct_ops = array();
		if ( $param = func_get_arg(0) ) {

			foreach ( $param as $key => $val ) {

                if ( ! $val )
                    $val = 'NULL';

				$this->acct_ops[ $key ] = $val;
			}
		}

		$this->transaction = true;
		$this->transaction_exec = array(
    		'dr'	=>	0,
            'cr'	=>	0
    	);

		$this->account_balance = array();
		if ( $this->debug )
            $this->debug_trans = array();

	}

	function end_transaction() {

		$this->transaction = false;

                $this->transaction_exec['dr'] = round($this->transaction_exec['dr'],2);
                $this->transaction_exec['cr'] = round($this->transaction_exec['cr'], 2);
 		if ( bccomp( $this->transaction_exec['dr'], $this->transaction_exec['cr'], 2) ) {
			if( bcsub($this->transaction_exec['dr'], $this->transaction_exec['cr'], 2) == .01 || bcsub($this->transaction_exec['dr'], $this->transaction_exec['cr'], 2) ==  -.01){
				$this->account_balance = $this->transaction_exec = array();

				return true;
			}else{
				$this->err = array(
	     			'errno'	=>	999,
	                 'err'	=>	"Final transaction is not in balance, cannot continue. Debit = " . $this->transaction_exec['dr'] . " Credit = " . $this->transaction_exec['cr']
	     		);
	
	 			return false;
			}
 		}

		$this->account_balance = $this->transaction_exec = array();

		return true;
	}

	function edit_check_stock() {
		$this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Edit Check Stock";
		$this->content['popup_controls']['popup_height'] = "800px";
		$this->content['popup_controls']['popup_width'] = "650px";
		$this->content['popup_controls']['popup_resize'] = "false";

		$account_hash = $this->ajax_vars['account_hash'];
		if (strlen($account_hash) == 32) {
			if ($valid = $this->fetch_account_record($account_hash) === false)
				return $this->__trigger_error("asdf", E_USER_ERROR, __FILE__, __LINE__, 1, 1);
		} else {
			$rand = $account_hash;
			unset($account_hash);
		}

		$this->content['jscript'][] = "
		ts = function() {
			var type = arguments[0];
			alert(type);
		}";

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("account_hash" => $account_hash,
								  "popup_id" => $this->popup_id,
								  "account_rand"	=>	$rand))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0;position:relative;\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:600px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"vertical-align:top;background-color:#ffffff;padding-left:5px;\">
						<div style=\"padding:10px;\">
							<div style=\"margin-left:10px;\">What is your check stock layout?</div>
							<div style=\"margin-left:10px;margin-top:8px;\">
								".$this->form->select("check_stock",
													   array("Check, Coupon, Coupon", "Coupon, Check, Coupon", "Coupon, Coupon, Check"),
													   $this->current_account['check_stock'],
													   array("top", "middle_stock", "bottom_stock"),
													   "blank=1",
													   "onChange=ts(this.options[this.selectedIndex].value)")."
							</div>
						</div>

						<div style=\"position:relative;width:306pt;height:396pt;background-color:#efefef;border:1px solid #8f8f8f;margin:20px;\" id=\"check_canvas\">
							<h6 style=\"position:absolute;top:132pt;left:0;width:306pt;border-top:3px dotted #000000;z-index:10\">&nbsp;</h6>
							<h6 style=\"position:absolute;top:264pt;left:0;width:306pt;border-top:3px dotted #000000;z-index:10\">&nbsp;</h6>
						</div>

					</td>
				</tr>
			</table>
		</div>";

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}

	function check_stock_coords($account_hash) {

		$r = $this->db->query("SELECT check_stock , check_stock_coords
							   FROM accounts
							   WHERE accounts.account_hash = '$account_hash'");
		if ($row = $this->db->fetch_assoc($r)) {
			$check_stock = $row['check_stock'];
			$coords = explode("\n", $row['check_stock_coords']);

			for ($i = 0; $i < count($coords); $i++) {
				if (trim($coords[$i]) == '--'.$check_stock) {
					$print_vars = array();
					continue;
				}
				if (trim($coords[$i]) == '--END'.$check_stock)
					break;

				$v = explode("|", trim($coords[$i]));
				$print_vars[$v[0]][$v[1]] = $v[2];
			}

			return $print_vars;
		}

		return false;
	}

	function export_debug($proposal_hash, $audit_id=NULL) {

        $csv_output = "Chart of Account, Trans Type, Debit, Credit, Memo, Line No., Item No., Qty, Cost, Ext Cost, Sell, Ext Sell";
        $csv_output .= "\n";
        $debit_total = $credit_total = 0;
        $lines = new line_items($proposal_hash);

        for ($i = 0; $i < count($this->debug_trans); $i++) {
        	if ($this->debug_trans[$i]['item_hash']) {
	            $valid = $lines->fetch_line_item_record($this->debug_trans[$i]['item_hash']);

	            $csv_output .= $this->debug_trans[$i]['account'].", ".$this->debug_trans[$i]['type'].", ".$this->debug_trans[$i]['dr'].", ".$this->debug_trans[$i]['cr'].", ".$this->debug_trans[$i]['memo'].", ".$lines->current_line['line_no'].", ".$lines->current_line['item_no'].", ".$lines->current_line['qty'].", ".$lines->current_line['cost'].", ".$lines->current_line['ext_cost'].", ".$lines->current_line['sell'].", ".$lines->current_line['ext_sell']."\n";

        	} else
                $csv_output .= $this->debug_trans[$i]['account'].", ".$this->debug_trans[$i]['type'].", ".$this->debug_trans[$i]['dr'].", ".$this->debug_trans[$i]['cr'].", ,, ,, ,, ,, \n";

        }

        $fh = fopen("/var/www/html/home.dealer-choice.com/core/tmp/audit_debug.csv", "w+");
        fwrite($fh, $csv_output);
        fclose($fh);
	}

    function close_year() {
        $fiscal_year = $this->ajax_vars['fiscal_year'];
        $finished = $this->ajax_vars['finished'];

        if ($finished) {

        } else {
	        $result = $this->db->query("SELECT `obj_id` , `closed` , `fiscal_year` , `period` , `period_end` , `period_start`
	                                    FROM `period_dates`
	                                    WHERE `fiscal_year` = '".$fiscal_year."'
	                                    ORDER BY `period` ASC");
	        while ($row = $this->db->fetch_assoc($result))
	            $open_periods[$row['fiscal_year']][$row['period']] = array('close_date'     => $row['period_end'],
	                                                                       'period_start'   => $row['period_start'],
	                                                                       'closed'         => $row['closed'],
	                                                                       'obj_id'         => $row['obj_id']);

	        if (is_array($open_periods)) {
	            $year =& $open_periods[$fiscal_year];
	            reset($year);
	            $first_period = current($year);
	            end($year);
	            $last_period = current($year);
	            $a = print_r($first_period, 1);
	            unset($year);
	        } else
	            return $this->__trigger_error("Unable to find period layout for year $fiscal_year.", E_USER_ERROR, __FILE__, __LINE__, 1);


	        $profit_acct = fetch_sys_var('DEFAULT_PROFIT_ACCT');
	        $r = $this->db->query("SELECT SUM(journal.debit - journal.credit) AS debit_total ,
	                               SUM(journal.credit - journal.debit) AS credit_total ,
	                               accounts.account_hash , accounts.account_no , accounts.account_name AS account_name ,
	                               accounts.account_type
	                               FROM journal
	                               LEFT JOIN accounts ON accounts.account_hash = journal.account_hash
	                               WHERE journal.date BETWEEN '".$first_period['period_start']."' AND '".$last_period['close_date']."'
	                               AND (accounts.account_type = 'EX' OR accounts.account_type = 'CG' OR accounts.account_type = 'IN' OR journal.account_hash = '$profit_acct')
	                               GROUP BY journal.account_hash");
	        while ($row = $this->db->fetch_assoc($r)) {
	        	if (bccomp($row['debit_total'], 0, 2) != 0 || bccomp($row['credit_total'], 0, 2)) {

		        	if ($row['account_hash'] == $profit_acct)
		                $profit_info = $row;
		        	else {
			            $tmp[$row['account_hash']] = $row;
			            $order[$row['account_hash']] = ($row['account_no'] ? $row['account_no'] : $row['account_name']);
		        	}
	        	}
	        }

	        if (is_array($order)) {
	            natsort($order);
	            while (list($account_hash) = each($order))
	                $account[$account_hash] = $tmp[$account_hash];
	        }
        }
        $tbl .=
        $this->form->form_tag().
        $this->form->hidden(array('fiscal_year' => $fiscal_year))."
        <div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
            <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                <p id=\"feedback_message\"></p>
        </div>
        <table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
            <tr>
                <td style=\"padding:0;\" id=\"inner_tbl_holder\">
                     <div style=\"background-color:#ffffff;height:100%;\">
                        <div style=\"margin:15px 35px;\">
                            <h3 style=\"margin-bottom:5px;color:#00477f;\">Year Closing : $fiscal_year</h3>
                            <small style=\"margin-left:20px;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'close_period', 'fiscal_year=$fiscal_year')\"><-- Back</a></small>
                            <div style=\"margin:20px;width:800px;padding:0;\">
                                <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
                                    <tr>
                                        <td style=\"padding:10px 25px;\" >";
                                        if ($finished)
                                            $tbl .= "
                                            <div style=\"margin-bottom:25px;\">
                                                Fiscal year $fiscal_year has been closed successfully.
                                            </div>";
                                        else {
                                        	$tbl .= "
                                            <div style=\"margin-bottom:25px;\">
                                                The following transaction will be made to close fiscal year $fiscal_year. Please review and ensure the closing balances below.
                                                When you're satisfied, click the 'Close Year' button at the botton.
                                            </div>
                                            <div style=\"margin-left:25px;\">
									            <table style=\"background-color:#8f8f8f;\" cellspacing=\"1\" cellpadding=\"5\">
									                <tr>
									                    <td style=\"width:400px;background-color:#efefef;font-weight:bold;\">Account</td>
									                    <td style=\"width:200px;background-color:#efefef;text-align:right;font-weight:bold;\">Ending Balance</td>
									                    <td style=\"width:200px;background-color:#efefef;text-align:right;font-weight:bold;\">Closing Entry</td>
									                </tr>";

									            while (list($account_hash, $account_info) = each($account)) {
									            	unset($title, $name);
									            	$name = ($account_info['account_no'] ?
                                                                $account_info['account_no']." - " : NULL).$account_info['account_name'];
                                                    if (strlen($name) > 40)
                                                    	$name = substr($name, 0, 38).'...';

                                                    $active_close = 1;
									            	$tbl .= "
									            	<tr>
                                                        <td style=\"background-color:#efefef;\">$name</td>
                                                        <td style=\"background-color:#ffffff;text-align:right;\">".($account_info['account_type'] == 'IN' ?
                                                            ($account_info['credit_total'] < 0 ?
                                                                "($".number_format($account_info['credit_total'] * -1, 2).")" : "$".number_format($account_info['credit_total'], 2)) :
                                                            ($account_info['debit_total'] < 0 ?
                                                                "($".number_format($account_info['debit_total'] * -1, 2).")" : "$".number_format($account_info['debit_total'], 2)))."
                                                        </td>
                                                        <td style=\"background-color:#ffffff;text-align:right;\">".($account_info['account_type'] == 'IN' ?
                                                            ($account_info['credit_total'] < 0 ?
                                                                "$".number_format($account_info['credit_total'] * -1, 2) : "($".number_format($account_info['credit_total'], 2).")") :
                                                            ($account_info['debit_total'] < 0 ?
                                                                "$".number_format($account_info['debit_total'] * -1, 2) : "($".number_format($account_info['debit_total'], 2).")"))."

                                                        </td>
									            	</tr>";
									            }
									            unset($this->current_account, $this->account_hash);
									            if ($profit_info) {
									            	if ($this->fetch_account_record(RETAINED_EARNINGS) == true)
	                                                    $tbl .= "
	                                                    <tr>
	                                                        <td style=\"background-color:#efefef;font-weight:bold;\">
	                                                            ".($this->current_account['account_no'] ?
	                                                                   $this->current_account['account_no']." - " : NULL).$this->current_account['account_name']."
	                                                        </td>
	                                                        <td style=\"background-color:#ffffff;text-align:right;\">N/A</td>
	                                                        <td style=\"background-color:#ffffff;text-align:right;font-weight:bold;\">".($profit_info['credit_total'] < 0 ?
	                                                            "($".number_format($profit_info['credit_total'] * -1, 2).")" : "$".number_format($profit_info['credit_total'], 2))."
	                                                        </td>
	                                                    </tr>";
                                                }

                                            $tbl .= "
									            </table>".($active_close ? "
										            <div style=\"margin:25px;\">".($profit_info && !$this->account_hash ?
										               "<img src=\"images/alert.gif\" />&nbsp;Your default retained earnings account has not been defined!" : $this->form->button("value=Close Year ".$fiscal_year, "onClick=if(confirm('Are you sure you want to close fiscal year ".$fiscal_year."? This will result is a journal entry being created dated on the last day of the fiscal year with all your closing balances.')){submit_form(this.form, 'accounting', 'exec_post', 'refresh_form', 'action=doit_close_year');}"))."
										            </div>" : NULL)."
                                            </div>";
                                        }

                                        $tbl .= "
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>".
        $this->form->close_form();

        $this->content['html']['place_holder'] = $tbl;
        return;
    }

    function doit_close_year() {

        $fiscal_year = $_POST['fiscal_year'];

        $result = $this->db->query("SELECT `obj_id` , `closed` , `fiscal_year` , `period` , `period_end` , `period_start`
                                    FROM `period_dates`
                                    WHERE `fiscal_year` = '".$fiscal_year."'
                                    ORDER BY `period` ASC");
        while ( $row = $this->db->fetch_assoc($result) )
            $open_periods[ $row['fiscal_year'] ][ $row['period'] ] = array(
                'close_date'     => $row['period_end'],
                'period_start'   => $row['period_start'],
                'closed'         => $row['closed'],
                'obj_id'         => $row['obj_id']
            );

        if ( $open_periods ) {

            $year =& $open_periods[ $fiscal_year ];
            reset($year);

            $first_period = current($year);
            end($year);

            $last_period = current($year);
            unset($year);

        } else
            return $this->__trigger_error("Unable to find period layout for year $fiscal_year.", E_USER_ERROR, __FILE__, __LINE__, 1);

        $close_date = $last_period['close_date'];

        $profit_acct = fetch_sys_var('DEFAULT_PROFIT_ACCT');
        $r = $this->db->query("SELECT
                                   SUM( t1.debit - t1.credit ) AS debit_total ,
                                   SUM( t1.credit - t1.debit ) AS credit_total ,
                                   t2.account_hash,
                                   t2.account_no,
                                   t2.account_name AS account_name,
                                   t2.account_type
                               FROM journal t1
                               LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
                               WHERE
                                   t1.date BETWEEN '{$first_period['period_start']}' AND '{$last_period['close_date']}'
                                   AND
                                   (
                                       t2.account_type IN('EX', 'CG', 'IN') OR t1.account_hash = '$profit_acct'
                                   )
                               GROUP BY t1.account_hash");
        while ( $row = $this->db->fetch_assoc($r) ) {

            if ( $row['account_hash'] == $profit_acct )
                $profit_info = $row;

            else {
                $tmp[ $row['account_hash'] ] = $row;
                $order[ $row['account_hash'] ] = ( $row['account_no'] ? $row['account_no'] : $row['account_name'] );
            }
        }

        if ( is_array($order) ) {

            natsort($order);
            while ( list($account_hash) = each($order) )
                $account[ $account_hash ] = $tmp[ $account_hash ];
        }

        $audit_id = $this->audit_id();

        if ( ! $this->db->start_transaction() )
            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

        $memo = "Fiscal year $fiscal_year closing entry.";
    	while ( list($account_hash, $account_info) = each($account) ) {

    		$debit_amt = $credit_amt = $amt = 0;
    		if ( ! $this->fetch_account_record($account_hash) )
                return $this->__trigger_error("System error encountered when attempting to lookup chart of account record. Please reload window and try again. <!-- Tried fetching account [ {$account_info['account_name']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

            if ( $account_info['account_type'] == 'IN' && bccomp($account_info['credit_total'], 0, 4) ) {

            	$amt = bcmul($account_info['credit_total'], -1, 4);
            	if ( bccomp($amt, 0, 4) == -1 )
                    $debit_amt = bcmul($amt, -1, 4);
                else
                    $credit_amt = $amt;

            } elseif ( ( $account_info['account_type'] == 'EX' || $account_info['account_type'] == 'CG' ) && bccomp($account_info['debit_total'], 0, 4) ) {

                $amt = bcmul($account_info['debit_total'], -1, 4);
            	if ( bccomp($amt, 0, 4) == -1 )
                    $credit_amt = bcmul($amt, -1, 4);
                else
                    $debit_amt = $amt;

            }
            if ( bccomp($debit_amt, 0, 4) || bccomp($credit_amt, 0, 4) ) {
			
            	# TODO: Remove to allow trigger to handle balance update
	            if ( ! $this->db->query("UPDATE accounts t1
        	                             SET t1.balance = ( t1.balance + $amt )
        	                             WHERE t1.account_hash = '{$this->account_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

	            $journal_hash = rand_hash('journal', 'journal_hash');

	            if ( ! $this->db->query("INSERT INTO journal
        	                             (
            	                             timestamp,
            	                             date,
            	                             id_hash,
            	                             journal_hash,
            	                             audit_id,
            	                             account_hash,
            	                             debit,
            	                             credit,
            	                             trans_type,
            	                             memo
            	                         )
        	                             VALUES
        	                             (
	        	                             UNIX_TIMESTAMP(),
	        	                             '$close_date',
	        	                             '{$this->current_hash}',
	        	                             '$journal_hash',
	        	                             '$audit_id',
	        	                             '$account_hash',
	        	                             '$debit_amt',
	        	                             '$credit_amt',
	        	                             'CL',
	        	                             '" . addslashes($memo) . "'
        	                             )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
            }
    	}

    	$debit_amt = $credit_amt = $amt = 0;
    	$retained_earnings = $this->fetch_account_record(RETAINED_EARNINGS);
    	if ( $profit_info && $retained_earnings ) {

    		if ( bccomp($profit_info['credit_total'], 0, 4) == -1 ) {

                $debit_amt = bcmul($profit_info['credit_total'], -1, 4);
                $profit_cr = bcmul($profit_info['credit_total'], -1, 4);

    		} elseif ( bccomp($profit_info['credit_total'], 0, 4) == 1 ) {

                $credit_amt = $profit_info['credit_total'];
                $profit_dr = $profit_info['credit_total'];
    		}

            if ( ! $this->db->query("UPDATE accounts t1
                                     SET t1.balance = ( t1.balance + {$profit_info['credit_total']} )
                                     WHERE t1.account_hash = '{$this->account_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            $journal_hash = rand_hash('journal', 'journal_hash');

    		$memo = "Fiscal year $fiscal_year closing - Retained Earnings.";
            if ( ! $this->db->query("INSERT INTO journal
                                     (
	                                     timestamp,
	                                     date,
	                                     id_hash,
	                                     journal_hash,
	                                     audit_id,
	                                     account_hash,
	                                     debit,
	                                     credit,
	                                     trans_type,
	                                     memo
                                     )
                                     VALUES
                                     (
	                                     UNIX_TIMESTAMP(),
	                                     '$close_date',
	                                     '{$this->current_hash}',
	                                     '$journal_hash',
	                                     '$audit_id',
	                                     '{$this->account_hash}',
	                                     '$debit_amt',
	                                     '$credit_amt',
	                                     'CL',
	                                     '" . addslashes($memo) . "'
                                     )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            if ( ! $this->db->query("UPDATE accounts t1
                                     SET t1.balance = ( t1.balance + " . bcmul($profit_info['credit_total'], -1, 4) . " )
                                     WHERE t1.account_hash = '{$profit_info['account_hash']}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            $journal_hash = rand_hash('journal', 'journal_hash');

            $memo = "Fiscal year $fiscal_year closing - Profit.";
            if ( ! $this->db->query("INSERT INTO journal
                                     (
	                                     timestamp,
	                                     date,
	                                     id_hash,
	                                     journal_hash,
	                                     audit_id,
	                                     account_hash,
	                                     debit,
	                                     credit,
	                                     trans_type,
	                                     memo
                                     )
                                     VALUES
                                     (
	                                     UNIX_TIMESTAMP(),
	                                     '$close_date',
	                                     '{$this->current_hash}',
	                                     '$journal_hash',
	                                     '$audit_id',
	                                     '{$profit_info['account_hash']}',
	                                     '$profit_dr',
	                                     '$profit_cr',
	                                     'CL',
	                                     '" . addslashes($memo) . "'
                                     )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }
    	} else
    	    return $this->__trigger_error("Unable to close profit/retained earnings.", E_USER_ERROR, __FILE__, __LINE__, 1);

        $this->db->end_transaction();

    	$this->content['action'] = 'continue';
    	$this->content['jscript'][] = "agent.call('accounting', 'sf_loadcontent', 'cf_loadcontent', 'close_year', 'fiscal_year=$fiscal_year', 'finished=1');";
    }

    function fetch_correction_codes() {

    	$this->correction_codes = array();

    	$r = $this->db->query("SELECT correction_codes.* , accounts.account_no ,
                        	   accounts.account_name , accounts.obj_id AS valid_account
    	                       FROM correction_codes
    	                       LEFT JOIN accounts ON accounts.account_hash = correction_codes.account_hash
    	                       ORDER BY correction_codes.code ASC");
    	while ($row = $this->db->fetch_assoc($r)) {
            $this->correction_codes[$row['code']] = $row;
    	}

    	return count($this->correction_codes);
    }

    function fetch_correction_code($correction_hash) {

        $r = $this->db->query("SELECT
                                   correction_codes.*,
                                   accounts.account_no,
                                   accounts.account_name,
                                   accounts.obj_id AS valid_account
                               FROM correction_codes
                               LEFT JOIN accounts ON accounts.account_hash = correction_codes.account_hash
                               WHERE correction_codes.correction_hash = '$correction_hash'
                               ORDER BY correction_codes.code ASC");
        while ( $row = $this->db->fetch_assoc($r) ) {

            $this->current_code = $row;
            $this->correction_hash = $correction_hash;

            return true;
        }

        return false;
    }

    function account_action($account_hash) {
        if (!$account_hash)
            return false;

    	$r = $this->db->query("SELECT t2.account_type
							   FROM accounts t1
							   LEFT JOIN account_types t2 ON t2.type_code = t1.account_type
							   WHERE t1.account_hash = '$account_hash'");

        return $this->db->result($r, 0, 'account_type');
    }

    /**
     * Return account name and number given account hash
     *
     * @param string $account_hash
     * @return string
     */
	function fetch_account_name($account_hash) {
		$result = $this->db->query("SELECT `account_no` , `account_name` , `account_hash`
									FROM `accounts`
									WHERE `account_hash` = '$account_hash'");
		$row = $this->db->fetch_assoc($result);
		$acct_name = ( trim($row['account_no']) ? trim($row['account_no']) . " : " : NULL ) . stripslashes($row['account_name']);
		return $acct_name;

	}

    /**
     * Return unique audit_id for journal transaction
     *
     * @param
     * @return int
     */
	function audit_id ($inc=1) {

		$audit_id = fetch_sys_var('NEXT_AUDIT_ID');
		update_sys_data('NEXT_AUDIT_ID', ( $audit_id + $inc ) );

		return $audit_id;
	}

	/**
	 * Description
	 *
	 * @param  Place    $where  Where something interesting takes place
	 * @param  integer  $repeat How many times something interesting should happen
	 * @throws Some_Exception_Class If something interesting cannot happen
	 * @return Status
	 */

	function setTransIndex() {

		$unset = func_get_arg(1);
        if ( $param = func_get_arg(0) ) {

            foreach ( $param as $key => $val ) {

            	if ( $unset )
                    unset( $this->acct_ops[ $val ] );
            	else {

	                if ( ! $val )
	                    $val = 'NULL';

	                $this->acct_ops[ $key ] = $val;
            	}
            }
        }
	}

	function fetch_journal_detail($audit_id) {

		$this->journal_detail = array();
		$t = 0;

		$r = $this->db->query("SELECT
		                           FROM_UNIXTIME(t1.timestamp) AS entrytime,
			                       t1.date,
			                       t1.check_no,
			                       ROUND( t1.debit, 2) AS debit,
			                       ROUND( t1.credit, 2) AS credit,
			                       t1.trans_type,
			                       t1.memo,
			                       CASE
			                       WHEN t2.account_no != '' THEN
				                       CONCAT(t2.account_no, ' ', t2.account_name) ELSE
				                       t2.account_name
			                       END AS account,
			                       t3.proposal_no,
			                       t3.proposal_descr,
			                       t4.full_name,
			                       CASE
			                       WHEN ! ISNULL(t1.customer_hash) THEN
				                       t5.customer_name ELSE
				                       t6.vendor_name
			                       END AS customer_vendor,
                                   t7.invoice_no AS ar_invoice_no,
                                   t8.invoice_no AS ap_invoice_no,
                                   t9.credit_no AS ar_credit_no
		                       FROM journal t1
		                       LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
		                       LEFT JOIN proposals t3 ON t3.proposal_hash = t1.proposal_hash
		                       LEFT JOIN users t4 ON t4.id_hash = t1.id_hash
		                       LEFT JOIN customers t5 ON t5.customer_hash = t1.customer_hash
		                       LEFT JOIN vendors t6 ON t6.vendor_hash = t1.vendor_hash
		                       LEFT JOIN customer_invoice t7 ON t7.invoice_hash = t1.ar_invoice_hash
		                       LEFT JOIN vendor_payables t8 ON t8.invoice_hash = t1.ap_invoice_hash
		                       LEFT JOIN customer_credit t9 ON t9.credit_hash = t1.ar_credit_hash
		                       WHERE t1.audit_id = '$audit_id' and t1.auto = 0
		                       ORDER BY t1.obj_id ASC");
        if ( $this->db->num_rows($r) ) {

        	$this->journal_detail = array(
            	'audit_id'         =>  $audit_id,
            	'date'             =>  $this->db->result($r, 0, 'date'),
            	'time'             =>  $this->db->result($r, 0, 'entrytime'),
                'type'             =>  $this->db->result($r, 0, 'trans_type'),
            	'check_no'         =>  $this->db->result($r, 0, 'check_no'),
                'proposal_no'      =>  $this->db->result($r, 0, 'proposal_no'),
            	'proposal_descr'   =>  $this->db->result($r, 0, 'proposal_descr'),
            	'ar_invoice_no'    =>  $this->db->result($r, 0, 'ar_invoice_no'),
            	'ap_invoice_no'    =>  $this->db->result($r, 0, 'ap_invoice_no'),
            	'ar_credit_no'     =>  $this->db->result($r, 0, 'ar_credit_no'),
                'user'             =>  $this->db->result($r, 0, 'full_name'),
                'total'            =>  0,
                'rows'             =>  array()
        	);

        	if ( $this->db->seek($r, 0) ) {

		        while ( $row = $this->db->fetch_assoc($r) ) {

		        	$t++;
		        	array_push($this->journal_detail['rows'], $row);
		        }
        	}

        	$this->journal_detail['total'] = $t;
	        return true;
        }

        return false;
	}

    function view_journal_detail() {

        $this->content['popup_controls']['popup_id'] = $this->popup_id = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );
        $this->content['popup_controls']['popup_title'] = "Journal Entry Detail";
        $this->content['popup_controls']['popup_height'] = "450px";
        $this->content['popup_controls']['popup_width'] = "850px";

        if ( ! $this->fetch_journal_detail($this->ajax_vars['audit_id']) )
        	return $this->__trigger_error("System error encountered when attempting to lookup journal record. Please reload window and try again. <!-- Tried fetching audit_id [ {$this->ajax_vars['audit_id']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

        $border = "border-bottom:1px solid #cccccc;";

        # TODO: When displaying customer/vendor in header area, consider that multiple customers/vendors may be assigned to a single journal entry

        $tbl =
        $this->form->form_tag() . "
        <div class=\"panel\" id=\"main_table{$this->popup_id}\">
            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message{$this->popup_id}\"></p>
            </div>
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:800px;height:425px;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"table-layout:fixed;background-color:#ffffff;vertical-align:top;width:97%;\">
                        <h3 style=\"color:#00467f;margin-bottom:8px;\">Viewing Journal Entry: {$this->journal_detail['audit_id']}</h3>
                        <div style=\"margin-left:10px;margin-bottom:15px;\">
	                        <table cellpadding=\"2\">
	                            <tr>
	                                <td style=\"width:200px;\"><strong>Entry Type:</strong> " . $this->trans_type['type'][ array_search($this->journal_detail['type'], $this->trans_type['code']) ] . "</td>
	                                <td style=\"width:275px\"><strong>Proposal No:</strong> {$this->journal_detail['proposal_no']}</td>
	                                <td style=\"width:200px\"><strong>A/R Invoice:</strong> {$this->journal_detail['ar_invoice_no']}</td>
	                            </tr>
	                            <tr>
    	                            <td><strong>Date:</strong> " . date(DATE_FORMAT, strtotime($this->journal_detail['date']) ) . "</td>
    	                            <td>
			                            <strong>Proposal Descr:</strong> " .
			                            ( strlen($this->journal_detail['proposal_descr']) > 25 ?
			                                stripslashes( substr($this->journal_detail['proposal_descr'], 0, 23) ) . ".." : stripslashes($this->journal_detail['proposal_descr'])
			                            ) . "

			                        </td>
			                        <td><strong>A/R Credit:</strong> {$this->journal_detail['ar_credit_no']}</td>
			                    </tr>
			                    <tr>
	                                <td><strong>User:</strong> " . stripslashes($this->journal_detail['user']) . "</td>
	                                <td>
			                            <strong>Check No:</strong> " .
			                            ( $this->journal_detail['check_no'] ?
			                                $this->journal_detail['check_no'] : "&nbsp;"
			                            ) . "

		                            </td>
		                            <td><strong>A/P Invoice:</strong> {$this->journal_detail['ap_invoice_no']}</td>
	                            </tr>
	                        </table>
                        </div>
                        <table class=\"tborder\" style=\"table-layout:fixed;font-size:8pt;\" cellpadding=\"4\" cellspacing=\"0\">
                            <col style=\"width:175px\">
                            <col style=\"width:250px\">
                            <col style=\"width:85px\">
                            <col style=\"width:85px\">
                            <col style=\"width:155px\">
	                        <tr>
	                            <td style=\"background-color:#efefef;font-weight:bold;$border;\">Account</td>
	                            <td style=\"background-color:#efefef;font-weight:bold;$border;\">Memo</td>
	                            <td style=\"background-color:#efefef;font-weight:bold;text-align:right;$border;\">Debit</td>
	                            <td style=\"background-color:#efefef;font-weight:bold;text-align:right;$border;\">Credit</td>
	                            <td style=\"background-color:#efefef;font-weight:bold;padding-left:8px;$border;\">Customer/Vendor</td>
	                        </tr>";

	                        $dr = $cr = 0;

	                        for ( $i = 0; $i < $this->journal_detail['total']; $i++ ) {

	                        	if ( $i >= $this->journal_detail['total'] - 1 )
    	                        	unset($border);

    	                        $dr = bcadd($dr, $this->journal_detail['rows'][$i]['debit'], 4);
    	                        $cr = bcadd($cr, $this->journal_detail['rows'][$i]['credit'], 4);

	                        	$tbl .= "
	                        	<tr>
                                    <td style=\"vertical-align:bottom;$border\" " . ( strlen($this->journal_detail['rows'][$i]['account']) > 25 ? "title=\"" . htmlentities($this->journal_detail['rows'][$i]['account'], ENT_QUOTES) . "\"" : NULL ) . ">" .
                                    ( strlen($this->journal_detail['rows'][$i]['account']) > 25 ?
                                        stripslashes( substr($this->journal_detail['rows'][$i]['account'], 0, 23) ) . ".." : stripslashes($this->journal_detail['rows'][$i]['account'])
                                    ) . "
    	                        	</td>
                                    <td style=\"$border\">" .
                                    ( $this->journal_detail['rows'][$i]['memo'] ?
                                        stripslashes($this->journal_detail['rows'][$i]['memo']) : "&nbsp;"
                                    ) . "
                                    </td>
                                    <td style=\"vertical-align:bottom;text-align:right;$border\">" .
                                    ( bccomp($this->journal_detail['rows'][$i]['debit'], 0, 4) == 1 ?
                                        "\$" . number_format( _round($this->journal_detail['rows'][$i]['debit'], 2), 2) : "&nbsp;"
                                    ) . "
                                    </td>
                                    <td style=\"vertical-align:bottom;text-align:right;$border\">" .
                                    ( bccomp($this->journal_detail['rows'][$i]['credit'], 0, 4) == 1 ?
                                        "\$" . number_format( _round($this->journal_detail['rows'][$i]['credit'], 2), 2) : "&nbsp;"
                                    ) . "
                                    </td>
                                    <td style=\"vertical-align:bottom;$border;padding-left:8px;\">" .
                                    ( $this->journal_detail['rows'][$i]['customer_vendor'] ?
                                        ( strlen($this->journal_detail['rows'][$i]['customer_vendor']) > 22 ?
                                            substr($this->journal_detail['rows'][$i]['customer_vendor'], 0, 20) . ".." : stripslashes($this->journal_detail['rows'][$i]['customer_vendor'])
                                        ) : "&nbsp;"
                                    ) . "
                                    </td>
	                        	</tr>";
	                        }

	                    $tbl .= "
		                    <tr>
    		                    <td colspan=\"2\">&nbsp;</td>
    		                    <td style=\"text-align:right;\">
        		                    <div style=\"padding-top:5px;border-top:1px dashed #000;\">\$" . number_format( _round($dr, 2), 2) . "</div>
    		                    </td>
                                <td style=\"text-align:right;\">
                                    <div style=\"padding-top:5px;border-top:1px dashed #000;\">\$" . number_format( _round($cr, 2), 2) . "</div>
                                </td>
    		                    <td>&nbsp;</td>
		                    </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>" .
        $this->form->close_form();

        $this->content['popup_controls']['cmdTable'] = $tbl;

    }

    /**
     * Queries the accounts table to check whether account is valid
     *
     * @param  string $account_hash Account identifier
     * @return bool
     */
    function valid_account($account_hash) {

        $r = $this->db->query("SELECT obj_id
                               FROM accounts
                               WHERE account_hash = '$account_hash'");
        if ( $valid = $this->db->result($r, 0, 'obj_id') ) {

        	return true;
        }

        return false;
    }

}