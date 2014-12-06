<?php
require_once('include/common.php');

if ($_SESSION['O_GZIP'])
    header("Content-Encoding: gzip");

//if ($referer['host'] != SITE_DOMAIN || !$report_hash) {
//    header('HTTP/1.1 403 Forbidden');
//    exit;
//}


if ($login_class->user_isloggedin() == false) {
    echo $login_class->error_template($login_class->err);
    ob_end_flush();
    $db->close();
    exit;
}

$type = base64_decode($_GET['t']);
if ($type) {
	require_once('include/xls.class.php');
	require_once('include/reports.class.php');
	require_once('include/print.class.php');
	if ($type == 'trial_balance') {
		$reports = new reports();
		$report_hash = $_GET['h'];
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		$_POST['doit_excel'] = 1;

		$reports->doit_trial_balance();
		$report_title = ($reports->report_date_start ? " Between Dates ".date(DATE_FORMAT,strtotime($reports->report_date_start)). " and ".date(DATE_FORMAT,strtotime($reports->report_date_end)) :
			"Through ".date(DATE_FORMAT,strtotime($reports->report_date_end)));

		// Start the XLS document
		$xls = new xls();
		$xls->xlsBOF("Trial Balance");
		$xls->xlsWriteLabel(0,"Trial Balance");
		$xls->xlsWriteLabel(1,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$col0 = 0;
		$col1 = 1;
		$col2 = 2;
		$col3 = 3;
		$col4 = 4;
		$col5 = 5;
		$col6 = 6;
		$col7 = 7;
		$col8 = 8;
		$col9 = 9;
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel(0,$report_title);
		$xls->xlsMoveRow();
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel($col0,"Account No");
		$xls->xlsWriteLabel($col1,"Account Name");
		//$xls->xlsWriteLabel($col2,"Opening");
		$xls->xlsWriteLabel($col3,"Debit");
		$xls->xlsWriteLabel($col4,"Credit");
		$xls->xlsWriteLabel($col5,"Memo");
		$xls->xlsWriteLabel($col6,"Check Number");
		if ($reports->current_report['detail'] == 2) {
			$xls->xlsWriteLabel($col7,"Date");
			$xls->xlsWriteLabel($col8,"Type");
			$xls->xlsWriteLabel($col9,"Proposal No");
		}
		//$xls->xlsWriteLabel($col5,"Current");
		//$xls->xlsWriteLabel($col6,"Closing");
		$xls->xlsMoveRow();
		$debit_total = $credit_total = 0;
		reset($reports->detail);

		$total_accounts = count($reports->detail);
		$j = 0;

		foreach ($reports->detail as $acct) {
			$debit = $credit = 0;
			$j++;
			if ($acct['account_hash'] != DEFAULT_PROFIT_ACCT) {
				if (($acct['account_action'] == 'DR' && $acct['total'] > 0) || ($acct['account_action'] == 'CR' && $acct['total'] < 0)) {
					if ($acct['total'] < 0)
						$acct['total'] *= -1;

					$debit = $acct['total'];
					if ($reports->current_report['detail'] == 1)
						$debit_total += $debit;
				} elseif (($acct['account_action'] == 'CR' && $acct['total'] > 0) || ($acct['account_action'] == 'DR' && $acct['total'] < 0)) {
					if ($acct['total'] < 0)
						$acct['total'] *= -1;

					$credit = $acct['total'];
					if ($reports->current_report['detail'] == 1)
						$credit_total += $credit;
				}
				//Account No
				$xls->xlsWriteLabel($col0,$acct['account_no']);
				//Account Name
				$xls->xlsWriteLabel($col1,$acct['account_name']);
				if ($reports->current_report['detail'] == 1) {
					$xls->xlsWriteNumber($col3,$debit);
					$xls->xlsWriteNumber($col4,$credit);
				}
				if ($reports->current_report['detail'] == 2) {
					for ($i = 0; $i < count($reports->sub_detail[$acct['account_hash']][0]); $i++) {
						if ($reports->current_report['detail'] == 2) {
							$debit_total +=	$reports->sub_detail[$acct['account_hash']][1][$i];
							$credit_total += $reports->sub_detail[$acct['account_hash']][2][$i];
						}

						//Memo
						$xls->xlsWriteLabel($col5,html_entity_decode($reports->sub_detail[$acct['account_hash']][5][$i]));
						//Check Number
						$xls->xlsWriteLabel($col6,html_entity_decode($reports->sub_detail[$acct['account_hash']][6][$i]));
						//Debit
						if ($reports->sub_detail[$acct['account_hash']][1][$i] != 0)
							$xls->xlsWriteNumber($col3,$reports->sub_detail[$acct['account_hash']][1][$i]);
						//Credit
						if ($reports->sub_detail[$acct['account_hash']][2][$i] != 0)
							$xls->xlsWriteNumber($col4,$reports->sub_detail[$acct['account_hash']][2][$i]);
						//Date
						$xls->xlsWriteLabel($col7,$reports->sub_detail[$acct['account_hash']][3][$i]);
						//Type
						$xls->xlsWriteLabel($col8,$reports->sub_detail[$acct['account_hash']][0][$i]);
						//Proposal No
						$xls->xlsWriteLabel($col9,$reports->sub_detail[$acct['account_hash']][4][$i]);

						$xls->xlsMoveRow();
					}
				}
			}
			$xls->xlsMoveRow();
		}

		$xls->xlsMoveRow();
		$xls->xlsWriteNumber($col3, $debit_total);
		$xls->xlsWriteNumber($col4, $credit_total);
		$xls->xlsStream();
	} elseif ($type == 'accounts' || $type == 'customers' || $type == 'vendors') {
		$hide_cols = array("obj_id","timestamp","last_change");
		$active_search = $_GET['s'];
		if ($active_search) {
			$result = $db->query("SELECT `query` , `total`
						  		  FROM `search`
						  		  WHERE `search_hash` = '".$active_search."'");
			$row = $db->fetch_assoc($result);
			$active_search_sql = base64_decode($row['query']);
		}
		switch ($type) {

			case 'customers':

				$title = "Customer List";
				$hide_cols[] = "customer_hash";
				$order_by = "customer_name";
				$left_join = "LEFT JOIN proposals ON proposals.customer_hash = customers.customer_hash ";
				$group_by = "customer_name";

				$col_headers = array(
                    "customer_name" => "Customer Name",
                    "customer_no" => "Customer No.",
                    "active" => "Active",
                    "street" => "Street",
                    "city" => "City",
                    "state" => "State",
                    "zip" => "Zip",
                    "country" => "Country",
                    "phone" => "Phone No.",
                    "fax" => "Fax No.",
                    "duns_no" => "Duns No.",
                    "account_no" => "Account No.",
                    "credit_limit" => "Credit Limit",
                    "credit_hold" => "Credit Hold",
                    "deposit_percent" => "Deposit Percent",
                    "tax_exempt_id" => "Tax Exempt Id",
                    "gsa" => "GSA",
                    "po_required" => "PO Required",
                    "payment_terms" => "Payment Terms",
                    "invoice_reminder" => "Invoice Reminder",
                    "reminder_days" => "Reminder Days",
                    "finance_charges" => "Finance Charges",
                    "finance_percent" => "Finance Percent",
                    "finance_days" => "Finance Days",
                    "finance_resend" => "Finance Resend"
				);

			break;

			case 'accounts':
				$active_search = $_GET['s'];
				$title = "Chart of Accounts";
				if ($active_search_sql)
					$active_search_sql = str_replace("t1","a",$active_search_sql);

				$sql = "SELECT account_no, account_name,
						(SELECT account_name
						 FROM accounts ma
						 WHERE ma.account_hash = a.parent_hash
						) as parent_acct,
						t.type, balance, active, private, checking
						FROM accounts a
						LEFT JOIN account_types t on a.account_type = t.type_code".($active_search_sql ? "
						WHERE $active_search_sql" : NULL)."
						ORDER BY account_no";
				$col_headers = array("account_no"	=> "Account No",
									"account_name"	=> "Account Name",
									"parent_acct"	=> "Parent Account",
									"type"			=> "Account Type",
									"balance"		=> "Balance",
									"active"		=> "Active",
									"private"		=> "Private",
									"checking"		=> "Checking");

			break;

			case 'vendors':
				$title = "Vendor List";
				$hide_cols[] = "vendor_hash";
				$hide_cols[] = "eorder_file";
				$hide_cols[] = "eorder_data";
				$order_by = "vendor_name";
                $col_headers = array(
                    "vendor_name"   => "Vendor Name",
                    "vendor_no"     => "Vendor No.",
                    "active"        => "Active",
                    "street"        => "Street",
                    "city"          => "City",
                    "state"         => "State",
                    "zip"           => "Zip",
                    "country"       => "Country",
                    "phone"         => "Phone No.",
                    "fax"           => "Fax No.",
                    "account_no"    => "Account No.",
                    "tax_id"        => "Tax Id",
                    "1099"          => "1099",
                    "order_email"   => "Order Email",
                    "order_fax"     => "Order Fax",
                    "order_method"  => "Default Order Method",
                    "deposit_percent"   => "Deposit Percent",
                    "payment_discount"  => "Payment Discount",
                    "payment_terms" => "Payment Terms",
                    "discount_days" => "Discount Days",
                    "group_invoices"    => "Group Invoices",
                    "po_comment"    => "PO Comment",
                    "sof"           => "Small Order Fee",
                    "sof_quoted"    => "Small Order Fee Quoted",
                    "frf"           => "Freight Terms",
                    "frf_quoted"    => "Freight Terms Quoted",
                    "fsf"           => "Fuel Surcharge",
                    "fsf_quoted"    => "Fuel Surcharge Quoted",
                    "cbd"           => "Call Before Delivery",
                    "remit_name"    => "Remit Name",
                    "remit_street"  => "Remit Street",
                    "remit_city"    => "Remit City",
                    "remit_state"   => "Remit State",
                    "remit_zip"     => "Remit Zip"
                );
		}
		$xls = new xls();
		$xls->xlsBOF($title);
		$xls->xlsWriteLabel(0,$title);
		$xls->xlsWriteLabel(1,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$xls->xlsMoveRow();
		$xls->xlsMoveRow();
		$select = implode(", $type.",array_keys($col_headers));
		$select = $type.".".$select;
		$result = $db->query(($sql ? $sql : "SELECT $select FROM ".$type.($active_search_sql ? " $left_join WHERE $active_search_sql" : NULL).($group_by ? " GROUP BY $group_by" : NULL)." ORDER BY ".$order_by));
		$first = true;
		while ($db_row = $db->fetch_assoc($result)) {
			$i = 0;
			foreach ($hide_cols as $col) {
				unset($db_row[$col]);
			}
			reset($hide_cols);
			if ($first) {
				$keys = array_keys($db_row);
				foreach ($keys as $col) {
					$xls->xlsWriteLabel($i,$col_headers[$col]);
					if ($col == 'sof')
						$sof_col = $i;
					if ($col == 'frf')
						$frf_col = $i;
					if ($col == 'fsf')
						$fsf_col = $i;
					if ($col == 'cbd')
						$cbd_col = $i;
					$i++;
				}
				reset($db_row);
				$i = 0;
				$xls->xlsMoveRow();
			}
			foreach ($db_row as $col) {
				if (is_numeric($col))
					$xls->xlsWriteNumber($i,$col);
				elseif ($type == 'vendors') {
					if ($i == $sof_col && $col) {
						// Small order fee
						$sof = explode("|",$col);
						foreach ($sof as $sof_el) {
							list($a,$b) = explode("=",$sof_el);
								$vendor[$a] = $b;
						}
						$sof_str = "If under ".print_it::format_dollars($vendor['sof_amount'])." ".($vendor['sof_amount_type'] == "L" ? "List" : "Net")." then add ".
									($vendor['sof_amount_add_type'] == 'P' ? ($vendor['sof_amount_add'] * 100)."% of ".($vendor['sof_amount_add_type_of'] == "L" ? "List" : "Net") :
										print_it::format_dollars($vendor['sof_amount_add'])).". Effective ".date(DATE_FORMAT,strtotime($vendor['sof_effective_date'])).
											($vendor['sof_expire_date'] ? " thru ".date(DATE_FORMAT,strtotime($vendor['sof_expire_date'])) : "");
						$xls->xlsWriteLabel($i,$sof_str);
					} elseif ($i == $frf_col && $col) {
						// Freight fee
						$frf = explode("|",$col);
						foreach ($frf as $frf_el) {
							list($a,$b) = explode("=",$frf_el);
								$vendor[$a] = $b;
						}
						$frf_str = "If under ".print_it::format_dollars($vendor['frf_amount'])." ".($vendor['frf_amount_type'] == "L" ? "List" : "Net")." then add ".
									($vendor['frf_amount_add_type'] == 'P' ? ($vendor['frf_amount_add'] * 100)."% of ".($vendor['frf_amount_add_type_of'] == "L" ? "List" : "Net") :
										print_it::format_dollars($vendor['frf_amount_add'])).". Effective ".date(DATE_FORMAT,strtotime($vendor['frf_effective_date'])).
											($vendor['frf_expire_date'] ? " thru ".date(DATE_FORMAT,strtotime($vendor['frf_expire_date'])) : "");
						$xls->xlsWriteLabel($i,$frf_str);
					} elseif ($i == $fsf_col && $col) {
						// Fuel surcharge
						$fsf = explode("|",$col);
						foreach ($fsf as $fsf_el) {
							list($a,$b) = explode("=",$fsf_el);
								$vendor[$a] = $b;
						}
						$fsf_str = ($vendor['fsf_amount_add_type'] == 'P' ? ($vendor['fsf_amount_add'] * 100)."% of ".($vendor['fsf_amount_add_type_of'] == "L" ? "List" : "Net") :
										print_it::format_dollars($vendor['fsf_amount_add'])).". Effective ".date(DATE_FORMAT,strtotime($vendor['fsf_effective_date'])).
											($vendor['fsf_expire_date'] ? " thru ".date(DATE_FORMAT,strtotime($vendor['fsf_expire_date'])) : "");

						$xls->xlsWriteLabel($i,$fsf_str);
					} elseif ($i == $cbd_col && $col) {
						// CBD Fee
						$cbd = explode("|",$col);
						foreach ($cbd as $cbd_el) {
							list($a,$b) = explode("=",$cbd_el);
								$vendor[$a] = $b;
						}
						$cbd_str = print_it::format_dollars($vendor['cbd_amount_add'])." - Effective ".date(DATE_FORMAT,strtotime($vendor['cbd_effective_date'])).
											($vendor['cbd_expire_date'] ? " thru ".date(DATE_FORMAT,strtotime($vendor['cbd_expire_date'])) : "");

						$xls->xlsWriteLabel($i,$cbd_str);
					} else
						$xls->xlsWriteLabel($i,$col);
				} else
					$xls->xlsWriteLabel($i,$col);
				$i++;
			}

			$xls->xlsMoveRow();
			$first = false;

		}
		$xls->xlsStream();
	} elseif ($type == 'sales_tax') {

		$reports = new reports();
		$report_hash = $_GET['h'];
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		$_POST['doit_excel'] = 1;

		// Populate data for sales_tax reports object.
		$reports->doit_sales_tax();
		$report_title = MY_COMPANY_NAME." Sales Tax Liability Report";

		// Start the XLS document
		$xls = new xls();
		$xls->xlsBOF("Sales Tax Liability Report");
		$xls->xlsWriteLabel(0,"Sales Tax Liability Report");
		$xls->xlsWriteLabel(1,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));

		$xls->xlsMoveRow();
		$xls->xlsWriteLabel(0,$report_title);

		$xls->xlsMoveRow();
		$xls->xlsMoveRow();

		$xls->xlsWriteLabel(0, "Invoice");
		$xls->xlsWriteLabel(1, "Invoice Date");
		$xls->xlsWriteLabel(2, "Total Sale");
		$xls->xlsWriteLabel(3, "Non-Taxable");
        $xls->xlsWriteLabel(4, "Taxable");
        $xls->xlsWriteLabel(5, "Rate");
		$xls->xlsWriteLabel(6, "Collected");
		$xls->xlsWriteLabel(7, "Liability");
		$xls->xlsWriteLabel(8, "Install Location");
		$xls->xlsMoveRow();

		if ( $reports->state_tax) {

            $xls->xlsWriteLabel(0, $reports->tax_state);
            $xls->xlsMoveRow();
            $xls->xlsMoveRow();

            $total_sell = $total_non = $total_tax = $total_coll = $total_owed = 0;

            reset( $reports->customers );
			while ( list($customer_hash, $customer) = each( $reports->customers ) ) {

				if ( $reports->state_tax[ $customer_hash ] ) {

					$xls->xlsWriteLabel(0, $customer['customer_name'] . ( $customer['city'] && $customer['state'] && $customer['zip'] ? " {$customer['city']}, {$customer['state']} {$customer['zip']}" : NULL ) . ( $customer['tax_id'] ? " Tax Exemption ID: {$customer['tax_id']}" : NULL ) );
                    $xls->xlsMoveRow();

                    reset($reports->state_tax[ $customer_hash ]);
                    while ( list($invoice_hash, $invoice_detail) = each($reports->state_tax[ $customer_hash ]) ) {

                    	$xls->xlsWriteLabel(0, $invoice_detail['invoice_no']);
                    	$xls->xlsWriteLabel(1, $invoice_detail['invoice_date']);
                    	$xls->xlsWriteNumber(2, $invoice_detail['invoice_net']);
                    	$xls->xlsWriteNumber(3, bcsub($invoice_detail['invoice_net'], $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['taxable'], 2) );
                    	$xls->xlsWriteNumber(4, $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['taxable']);
                    	$xls->xlsWriteNumber(5, bcmul($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['rate'], 100, 4));
                    	$xls->xlsWriteNumber(6, $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['tax_collected']);
                    	$xls->xlsWriteNumber(7, $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['liability']);
                    	$xls->xlsWriteLabel(8, "{$invoice_detail['install_city']}, {$invoice_detail['install_state']} {$invoice_detail['install_zip']}");

                    	$xls->xlsMoveRow();

                        $total_sell = bcadd($total_sell, $invoice_detail['invoice_net'], 2);
                        $total_non = bcadd($total_non, bcsub($invoice_detail['invoice_net'], $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['taxable'], 2), 2);
                        $total_tax = bcadd($total_tax, $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['taxable'], 2);
                        $total_coll = $total_coll + round($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['tax_collected'],2);
                        $total_owed = $total_owed + round($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['liability'],2);

                        if ( $reports->credit[ $invoice_hash ][ $reports->default_tax ] ) {

                            reset($reports->credit[ $invoice_hash ][ $reports->default_tax ]);
                            while ( list($credit_hash, $credit_detail) = each($reports->credit[ $invoice_hash ][ $reports->default_tax ]) ) {

                                $xls->xlsWriteLabel(0, $credit_detail['credit_no']);
                                $xls->xlsWriteNumber(2, bcmul($credit_detail['credit_net'], -1, 2) );
                                $xls->xlsWriteNumber(4, bcmul($credit_detail['credit_net'], -1, 2) );
                                $xls->xlsWriteNumber(5, bcmul($credit_detail['rate'], 100, 4) );
                                $xls->xlsWriteNumber(6, bcmul($credit_detail['tax_collected'], -1, 2) );
                                $xls->xlsWriteNumber(7, bcmul($credit_detail['liability'], -1, 2) );

                                $xls->xlsMoveRow();

                                $total_sell = bcsub($total_sell, $credit_detail['credit_net'], 2);
                                $total_tax = bcsub($total_tax, $credit_detail['credit_net'], 2);
                                $total_coll = $total_coll - $credit_detail['tax_collected'];
                                $total_owed = $total_owed - $credit_detail['liability'];
                            }
                        }

                    }
				}
			}

			$xls->xlsMoveRow();

			$xls->xlsWriteLabel(0, "------------");
			$xls->xlsWriteLabel(1, "------------");
			$xls->xlsWriteLabel(2, "------------");
			$xls->xlsWriteLabel(3, "------------");
			$xls->xlsWriteLabel(4, "------------");
			$xls->xlsWriteLabel(5, "------------");
			$xls->xlsWriteLabel(6, "------------");
			$xls->xlsWriteLabel(7, "------------");
			$xls->xlsWriteLabel(8, "------------");

			$xls->xlsMoveRow();

            $xls->xlsWriteNumber(2, _round($total_sell, 2));
            $xls->xlsWriteNumber(3, _round($total_non, 2));
            $xls->xlsWriteNumber(4, _round($total_tax, 2));
            $xls->xlsWriteNumber(6, _round($total_coll, 2));
            $xls->xlsWriteNumber(7, _round($total_owed, 2));

            $xls->xlsMoveRow();
            $xls->xlsMoveRow();

            $total_sell = $total_non = $total_tax = $total_coll = $total_owed = 0;

            reset( $reports->tax_rules );
            reset( $reports->local_tax[ $tax_hash ] );
            while ( list($tax_hash, $tax_rule) = each($reports->tax_rules) ) {

                if ( $reports->local_tax[ $tax_hash ] && $tax_hash != $reports->default_tax ) {

                    $xls->xlsWriteLabel(0, $tax_rule['local']);
                    $xls->xlsMoveRow();
                    $xls->xlsMoveRow();

                    reset( $reports->customers );
                    while ( list($customer_hash, $customer) = each($reports->customers) ) {

                        if ( $reports->local_tax[ $tax_hash ][ $customer_hash ] ) {

		                    $xls->xlsWriteLabel(0, $customer['customer_name'] . ( $customer['city'] && $customer['state'] && $customer['zip'] ? " {$customer['city']}, {$customer['state']} {$customer['zip']}" : NULL ) . ( $customer['tax_id'] ? " Tax Exemption ID: {$customer['tax_id']}" : NULL ) );
		                    $xls->xlsMoveRow();

                            reset( $reports->local_tax[ $tax_hash ][ $customer_hash ] );
                            while ( list($invoice_hash, $invoice_detail) = each($reports->local_tax[ $tax_hash ][ $customer_hash ]) ) {

                                $xls->xlsWriteLabel(0, $reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_no']);
                                $xls->xlsWriteLabel(1, $reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_date']);
                                $xls->xlsWriteNumber(2, $reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_net']);
                                $xls->xlsWriteNumber(3, bcsub($reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_net'], $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['taxable'], 2) );
                                $xls->xlsWriteNumber(4, $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['taxable']);
                                $xls->xlsWriteNumber(5, bcmul($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['rate'], 100, 4) );
                                $xls->xlsWriteNumber(6, $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['tax_collected']);
                                $xls->xlsWriteNumber(7, $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['liability']);
                                $xls->xlsWriteLabel(8, "{$reports->state_tax[ $customer_hash ][ $invoice_hash ]['install_city']}, {$reports->state_tax[ $customer_hash ][ $invoice_hash ]['install_state']} {$reports->state_tax[ $customer_hash ][ $invoice_hash ]['install_zip']}");

                                $xls->xlsMoveRow();

                                $total_sell = bcadd($total_sell, $reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_net'], 2);
                                $total_non = bcadd($total_non, bcsub($reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_net'], $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['taxable'], 2), 2);
                                $total_tax = $total_tax + $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['taxable'];
		                        $total_coll = $total_coll + round($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['tax_collected'],2);
		                        $total_owed = $total_owed + round($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['liability'],2);

                                if ( $reports->credit[ $invoice_hash ][ $tax_hash ] ) {

                                    reset($reports->credit[ $invoice_hash ][ $tax_hash ]);
                                    while ( list($credit_hash, $credit_detail) = each($reports->credit[ $invoice_hash ][ $tax_hash ]) ) {

                                        $xls->xlsWriteLabel(0, $credit_detail['credit_no']);
                                        $xls->xlsWriteNumber(2, bcmul($credit_detail['credit_net'], -1, 2) );
                                        $xls->xlsWriteNumber(4, bcmul($credit_detail['credit_net'], -1, 2) );
                                        $xls->xlsWriteNumber(5, bcmul($credit_detail['rate'], 100, 4) );
                                        $xls->xlsWriteNumber(6, bcmul($credit_detail['tax_collected'], -1, 2));
                                        $xls->xlsWriteNumber(7, bcmul($credit_detail['liability'], -1, 2) );

                                        $xls->xlsMoveRow();

                                        $total_sell = bcsub($total_sell, $credit_detail['credit_net'], 2);
                                        $total_tax = bcsub($total_tax, $credit_detail['credit_net'], 2);
                                       	$total_coll = $total_coll - $credit_detail['tax_collected'];
                                        $total_owed = $total_owed - $credit_detail['liability'];
                                    }
                                }
                            }
                        }
                    }

                    $xls->xlsMoveRow();

                    $xls->xlsWriteLabel(0, "------------");
		            $xls->xlsWriteLabel(1, "------------");
		            $xls->xlsWriteLabel(2, "------------");
		            $xls->xlsWriteLabel(3, "------------");
		            $xls->xlsWriteLabel(4, "------------");
		            $xls->xlsWriteLabel(5, "------------");
		            $xls->xlsWriteLabel(6, "------------");
		            $xls->xlsWriteLabel(7, "------------");
		            $xls->xlsWriteLabel(8, "------------");

		            $xls->xlsMoveRow();

		            $xls->xlsWriteNumber(2, _round($total_sell, 2));
		            $xls->xlsWriteNumber(3, _round($total_non, 2));
		            $xls->xlsWriteNumber(4, _round($total_tax, 2));
		            $xls->xlsWriteNumber(6, _round($total_coll, 2));
		            $xls->xlsWriteNumber(7, _round($total_owed, 2));

		            $xls->xlsMoveRow();
		            $xls->xlsMoveRow();

		            $total_sell = $total_non = $total_tax = $total_coll = $total_owed = 0;
                }
            }
		}

		$xls->xlsStream();

	} elseif($type == 'cash_disp') {
		$reports = new reports();
		$report_hash = $_GET['h'];
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		$_POST['doit_excel'] = 1;

		// Populate data for sales_tax reports object.
		$reports->doit_cash_disp();
		$report_title = MY_COMPANY_NAME." Cash Disbursements Report";

		// Start the XLS document
		$xls = new xls();
		$xls->xlsBOF("Cash Disbursements Report");
		$xls->xlsWriteLabel(0,"Cash Disbursements Report");
		$xls->xlsWriteLabel(1,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$col0 = 0;
		$col1 = 1;
		$col2 = 2;
		$col3 = 3;
		$col4 = 4;
		$col5 = 5;
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel(0,$report_title);
		$xls->xlsMoveRow();
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel($col1,"Payee");
		$xls->xlsWriteLabel($col2,"Check No.");
		if ($reports->current_report['detail'])
			$xls->xlsWriteLabel($col3,"Invoice/Deposit Details");
		$xls->xlsWriteLabel($col4,"Check Date");
		$xls->xlsWriteLabel($col5,"Check Amount");
		$xls->xlsMoveRow();


		reset($reports->payment);
		if (is_array($reports->payment)) {
			$total_payments = 0;
			while (list($vendor_hash,$check_array) = each($reports->payment)) {
				$displayOnce = true;
				while (list($check_no,$check_data) = each($check_array)) {
					// Print vendor or customer name for Trac #1514
					$vendor_name = ($check_data[0]['vendor_name'] ? $check_data[0]['vendor_name'] : $check_data[0]['customer_name']);
					$xls->xlsWriteLabel($col1,stripslashes($vendor_name));
					$displayOnce = false;
					for ($i = 0; $i < count($check_data); $i++) {
						$total_payments += $check_data[$i]['total_payment'];
						$xls->xlsWriteLabel($col2,($check_data[$i]['check_no'] ? $check_data[$i]['check_no'] : "Unassigned") );
						$xls->xlsWriteLabel($col4,date(DATE_FORMAT,strtotime($check_data[$i]['check_date'])));
						$xls->xlsWriteNumber($col5,$check_data[$i]['total_payment']);
						$xls->xlsMoveRow();
						if ($reports->current_report['detail']) {
							for ($j = 0; $j < count($reports->detail[$vendor_hash][$check_data[$i]['check_no']]['invoice_no']); $j++) {
								$invoice_no = $reports->detail[$vendor_hash][$check_data[$i]['check_no']]['invoice_no'][$j];
								$xls->xlsWriteLabel($col3,$invoice_no);
								$xls->xlsWriteLabel($col4,date(DATE_FORMAT,strtotime($reports->detail[$vendor_hash][$check_data[$i]['check_no']]['date'][$j])));
								$xls->xlsWriteNumber($col5,$reports->detail[$vendor_hash][$check_data[$i]['check_no']]['amount'][$j]);
								$xls->xlsMoveRow();
							}
						}
					}
				}
			}
			$xls->xlsWriteLabel($col1,"Total");
			$xls->xlsWriteNumber($col5,$total_payments);
		}
		$xls->xlsStream();
	} elseif ($type == 'cash_receipts') {
		$reports = new reports();
		$report_hash = $_GET['h'];
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		$_POST['doit_excel'] = 1;

		// Populate data for cash_receipts reports object.
		$reports->doit_cash_receipts();
		$report_title = MY_COMPANY_NAME." Cash Receipts Report";

		// Start the XLS document
		$xls = new xls();
		$xls->xlsBOF("Cash Receipts Report");
		$xls->xlsWriteLabel(0,"Cash Receipts Report");
		$xls->xlsWriteLabel(1,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$col0 = 0;
		$col1 = 1;
		$col2 = 2;
		$col3 = 3;
		$col4 = 4;
		$col5 = 5;
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel(0,$report_title);
		$xls->xlsMoveRow();
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel($col1,"Customer");
		$xls->xlsWriteLabel($col2,"Check No.");
		if ($reports->current_report['detail'])
			$xls->xlsWriteLabel($col3,"Invoice/Deposit Details");
		$xls->xlsWriteLabel($col4,"Receipt Date");
		$xls->xlsWriteLabel($col5,"Receipt Amount");
		$xls->xlsMoveRow();

		if (is_array($reports->payment)) {
			$total_receipts = 0;
			while (list($customer_hash,$check_array) = each($reports->payment)) {
				$displayOnce = true;
				$isData = false;
				$total_check_receipt = 0;
				while (list($check_no,$check_data) = each($check_array)) {
					$cust_name = stripslashes($check_data[0]['customer_name']);
					if ($cust_name && $cust_name != "") {
						$isData = true;
						for ($i = 0; $i < count($check_data); $i++) {
							$total_check_receipt += $check_data[$i]['total_reciept'];
							$total_receipts += $check_data[$i]['total_reciept'];
							$xls->xlsWriteLabel($col1,$cust_name);
							$xls->xlsWriteLabel($col2,($check_data[$i]['check_no'] ? $check_data[$i]['check_no'] : "Unassigned"));
							$xls->xlsWriteLabel($col4,date(DATE_FORMAT,strtotime($check_data[$i]['receipt_date'])));
							$xls->xlsWriteNumber($col5,round($check_data[$i]['total_reciept'],2));
							$xls->xlsMoveRow();
							if ($reports->current_report['detail']) {
								for ($j = 0; $j < count($reports->detail[$customer_hash][$check_data[$i]['check_no']]['invoice_no']); $j++) {
									$invoice_no = $reports->detail[$customer_hash][$check_data[$i]['check_no']]['invoice_no'][$j];
									$xls->xlsWriteLabel($col1,$cust_name);
									$xls->xlsWriteLabel($col3,($invoice_no ? "Invoice ".$invoice_no : "Deposit"));
									$xls->xlsWriteLabel($col4,date(DATE_FORMAT,strtotime($reports->detail[$customer_hash][$check_data[$i]['check_no']]['date'][$j])));
									$xls->xlsWriteNumber($col5,round($reports->detail[$customer_hash][$check_data[$i]['check_no']]['amount'][$j],2));
									$xls->xlsMoveRow();
								}
							}
						}
					}
				}
				if ($isData) {
					$xls->xlsWriteLabel($col1,"Total for ".$cust_name);
					$xls->xlsWriteNumber($col5,round($total_check_receipt,2));
					$xls->xlsMoveRow();
				}
			}
			$xls->xlsWriteLabel($col1,"Total");
			$xls->xlsWriteNumber($col5,$total_receipts);
		}

		$xls->xlsStream();
	} elseif ($type == 'bookings_report') {
		$reports = new reports();
		$report_hash = $_GET['h'];
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		$_POST['doit_excel'] = 1;

		// Populate data for cash_receipts reports object.
		$reports->doit_bookings_report();
		$report_title = MY_COMPANY_NAME." Bookings Report";

		// Start the XLS document
		$xls = new xls();
		$xls->xlsBOF("Bookings Report");
		$xls->xlsWriteLabel(0,"Bookings Report");
		$xls->xlsWriteLabel(1,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$col0 = 0;
		$col1 = 1;
		$col2 = 2;
		$col3 = 3;
		$col4 = 4;
		$col5 = 5;
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel(0,$report_title);
		$xls->xlsMoveRow();
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel($col1,"Sales Rep");
		if ($reports->current_report['detail'])
			$xls->xlsWriteLabel($col2,"Proposal Details");
		$xls->xlsWriteLabel($col3,"Total Net");
		$xls->xlsWriteLabel($col4,"Total Sell");
		$xls->xlsWriteLabel($col5,"Margin");
		$xls->xlsMoveRow();
		reset($reports->results);
		while (list($sales_hash,$data) = each($reports->results)) {
			if ($sales_hash) {
				$xls->xlsWriteLabel($col1,stripslashes($data['full_name']));
				$xls->xlsWriteNumber($col3,$data['total_cost']);
				$xls->xlsWriteNumber($col4,$data['total_sell']);
				$xls->xlsWriteNumber($col5, (float)bcmul( math::gp_margin( array(
    				'cost'  =>  $data['total_cost'],
    				'sell'  =>  $data['total_sell']
				) ), 100, 4) );
				$total_sell += $data['total_sell'];
				$total_cost += $data['total_cost'];
				$xls->xlsMoveRow();

				if ($reports->current_report['detail']) {
					for($i=0;$i<count($reports->results[$sales_hash]['detail']);$i++) {
						$xls->xlsWriteLabel($col2,$reports->results[$sales_hash]['detail'][$i]['prop_details']);
						$xls->xlsWriteNumber($col3,$reports->results[$sales_hash]['detail'][$i]['po_cost']);
						$xls->xlsWriteNumber($col4,$reports->results[$sales_hash]['detail'][$i]['po_sell']);
						$xls->xlsWriteNumber($col5,$reports->results[$sales_hash]['detail'][$i]['po_gp']);
						$xls->xlsMoveRow();
					}
				}
			}
		}

		// Show totals
		$xls->xlsWriteLabel($col1,"Totals");
		$xls->xlsWriteNumber($col3, $total_cost);
		$xls->xlsWriteNumber($col4, $total_sell);
		$xls->xlsWriteNumber($col5, (float)bcmul( math::gp_margin( array(
		'cost'    =>  $total_cost,
		'sell'    =>  $total_sell
		) ), 100, 4) );

		$xls->xlsStream();
	} elseif ($type == 'check_reg') {
		$accounting = new accounting();

		// Populate data for check_reg accounting object.
		$_POST['doit_excel'] = 1;
		$accounting->fetch_check_register(0);
		$report_title = MY_COMPANY_NAME." Check Register";

		// Start the XLS document
		$xls = new xls();
		$xls->xlsBOF("Check Register");
		$xls->xlsWriteLabel(0,"Check Register");
		$xls->xlsWriteLabel(1,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$col0 = 0;
		$col1 = 1;
		$col2 = 2;
		$col3 = 3;
		$col4 = 4;
		$col5 = 5;
		$col6 = 6;
		$col7 = 7;
		$col8 = 8;
		$col9 = 9;
		$xls->xlsMoveRow();
		$xls->xlsWriteLabel(0,$report_title);
		$xls->xlsMoveRow();
		$xls->xlsMoveRow();

		$xls->xlsWriteLabel($col0,"Check No.");
		$xls->xlsWriteLabel($col1,"Date");
		$xls->xlsWriteLabel($col2,"Payee");
		$xls->xlsWriteLabel($col3,"Memo");
		$xls->xlsWriteLabel($col4,"Amount");
		$xls->xlsWriteLabel($col5,"Cleared");
		$xls->xlsWriteLabel($col6,"Void");
		$xls->xlsWriteLabel($col7,"Checking Account");
		$xls->xlsWriteLabel($col8,"Expense Account");
		$xls->xlsMoveRow();

		for ($i = 0; $i < $accounting->check_total; $i++) {

			$xls->xlsWriteLabel($col0,$accounting->check_register[$i]['check_no']);
			$xls->xlsWriteLabel($col1,date(DATE_FORMAT,strtotime($accounting->check_register[$i]['check_date'])));
			$xls->xlsWriteLabel($col2,($accounting->check_register[$i]['customer_name'] ?
    										stripslashes($accounting->check_register[$i]['customer_name']) : stripslashes($accounting->check_register[$i]['vendor_name'])));
			$xls->xlsWriteLabel($col3,stripslashes($accounting->check_register[$i]['memo']));
			$xls->xlsWriteNumber($col4,$accounting->check_register[$i]['amount']);
			$xls->xlsWriteLabel($col5,($accounting->check_register[$i]['cleared'] ? "Y" : "N"));
			$xls->xlsWriteLabel($col6,($accounting->check_register[$i]['void'] ? "VOID" : NULL));
			$xls->xlsWriteLabel($col7,($accounting->check_register[$i]['account_name'] ?
												($accounting->check_register[$i]['account_no'] ?
													$accounting->check_register[$i]['account_no']." - " : NULL).(strlen($accounting->check_register[$i]['account_name']) > 35 ?
														substr($accounting->check_register[$i]['account_name'],0,33)."..." : $accounting->check_register[$i]['account_name']) : ""));
			$xls->xlsWriteLabel($col8,($accounting->check_register[$i]['expense_account_no'] ?
										$accounting->check_register[$i]['expense_account_no']." - " : NULL).(strlen($accounting->check_register[$i]['expense_account_name']) > 40 ?
											substr($accounting->check_register[$i]['expense_account_name'],0,37)."..." : $accounting->check_register[$i]['expense_account_name']));
			$xls->xlsMoveRow();
		}
		$xls->xlsStream();
	} elseif ($type == 'vendor_1099_std') {
        $reports = new reports();
        $report_hash = $_GET['h'];
        $_POST['report_hash'] = $report_hash;
        $_POST['doit_print'] = 1;
        $_POST['doit_excel'] = 1;

        // Populate data for cash_receipts reports object.
        $reports->doit_vendor_1099();
        $report_title = MY_COMPANY_NAME." Vendor 1099 Report";

        // Start the XLS document
        $xls = new xls();
        $xls->xlsBOF("Vendor 1099 Report");
        $xls->xlsWriteLabel(0,$report_title);
        $xls->xlsWriteLabel(1,$reports->report_title);
        $xls->xlsWriteLabel(2,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
        $col0 = 0;
        $col1 = 1;
        $col2 = 2;
        $col3 = 3;
        $col4 = 4;
        $col5 = 5;
        $col6 = 6;
        $col7 = 7;
        $col8 = 8;

        $xls->xlsMoveRow();
        $xls->xlsMoveRow();
        $xls->xlsWriteLabel($col0,"Vendor");
        $xls->xlsWriteLabel($col1,"Street 1");
        $xls->xlsWriteLabel($col2,"Street 2");
        $xls->xlsWriteLabel($col3,"City");
        $xls->xlsWriteLabel($col4,"State");
        $xls->xlsWriteLabel($col5,"Zip");
        $xls->xlsWriteLabel($col6,"Phone");
        $xls->xlsWriteLabel($col7,"Tax ID");
        $xls->xlsWriteLabel($col8,"Payment Amount");
        $xls->xlsMoveRow();

        if ($reports->results) {

            for ($i = 0; $i < count($reports->results); $i++) {

                $vendor =
                stripslashes($reports->results[$i]['vendor_name']) . " - " .
                ( $reports->results[$i]['street'] ?
                    stripslashes($reports->results[$i]['street']) . " " : NULL
                ) .
                stripslashes($reports->results[$i]['city']) . ", {$reports->results[$i]['state']} {$reports->results[$i]['zip']}";

                $xls->xlsWriteLabel($col0, stripslashes($reports->results[$i]['vendor_name']));
                $xls->xlsWriteLabel($col1, stripslashes($reports->results[$i]['street']));
                $xls->xlsWriteLabel($col3, stripslashes($reports->results[$i]['city']));
                $xls->xlsWriteLabel($col4, $reports->results[$i]['state']);
                $xls->xlsWriteLabel($col5, $reports->results[$i]['zip']);
                $xls->xlsWriteLabel($col6, $reports->results[$i]['phone']);
                $xls->xlsWriteLabel($col7, $reports->results[$i]['tax_id']);
                $xls->xlsWriteNumber($col8, $reports->results[$i]['total_payment']);
                $xls->xlsMoveRow();
            }
        }

        $xls->xlsStream();
	} elseif ($type == 'reports::wip_report' || $type == 'reports::doit_wip_report') {

		$path = realpath( getcwd() . "/tmp/" );
        $tmpfname = $path . "WIPDetailExport_" . date("Ymd") . "_" . rand(50, 5000) . ".xls";
        $format = 'excel';

        $report_hash = $_GET['h'];
        $reports = new reports();
        $reports->fetch_report_settings($report_hash);

        exec("perl xls_dump.pl --report_hash=$report_hash --tmp_file=\"$tmpfname\" --format=$format");

        if (!$_GET['stream'] || $_GET['stream'] == 1) {
	        header("Pragma: public");
	        header("Expires: 0");
	        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	        header("Cache-Control: private",false);
	        header("Content-Type: application/vnd.ms-excel");
	        header("Content-Disposition: attachment; filename=\"".basename($tmpfname)."\";");
	        header("Content-Transfer-Encoding: binary");
	        header("Content-Length: ".@filesize($tmpfname));
	        set_time_limit(0);
	        @readfile($tmpfname) or die("File not found.");
        }
	} elseif ($type == 'balance_sheet') {
        $reports = new reports();
        $report_hash = $_GET['h'];
        $_POST['report_hash'] = $report_hash;
        $_POST['from_report'] = 1;
		$_POST['doit_print'] = 1;
		$reports->doit_balance_sheet();
		
        $report_title = MY_COMPANY_NAME." Balance Sheet";

		if ($reports->compare == 1) {
			$report_title .= " With Comparision Against Previous Cycle";
		} elseif ($reports->compare > 1) {
			$report_title .= " With Comparision Against Previous ".$reports->compare." Cycles";
		}

        // Start the XLS document
        $xls = new xls();
        $xls->xlsBOF("Balance Sheet");
        $xls->xlsWriteLabel(0,$report_title);
        $xls->xlsWriteLabel(2,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
        $col0 = 0;
        $col1 = 1;
        $col2 = 2;
        $col3 = 3;
        $col4 = 4;
        $col5 = 5;
        $col6 = 6;
        
        $item_cols = array("account_name"	=>	"Account",
						   "amount"			=>	"Amount");
		$account_detail = array(array(array()));
		$accounts = array('CA' => array(),
						  'FA' => array(),
						  'CL' => array(),
						  'LL' => array(),
						  'EQ' => array());
		$acct = new accounting();

		$result = $db->query("SELECT accounts.account_name , accounts.account_no , journal.account_hash , account_types.type_code ,
									SUM(ROUND(journal.debit,2)) AS debit_total , SUM(ROUND(journal.credit,2)) AS credit_total
									FROM `journal`
									LEFT JOIN `accounts` ON accounts.account_hash = journal.account_hash
									LEFT JOIN `account_types` ON account_types.type_code = accounts.account_type
									WHERE account_types.account_side = 'B' ".($reports->current_report['sql'] ?
										stripslashes($reports->current_report['sql']) : NULL)."
									GROUP BY journal.account_hash");
		while ($row = $db->fetch_assoc($result)) {
			$acct->fetch_account_record($row['account_hash']);
			if ($acct->current_account['account_action'] == 'DR')
				$total = round($row['debit_total'] - $row['credit_total'],2);
			elseif ($acct->current_account['account_action'] == 'CR')
				$total = round($row['credit_total'] - $row['debit_total'],2);

			$account_detail[$acct->account_hash][0]['total'] = $total;
			$accounts[$row['type_code']][$acct->account_hash] = array("account_name" => $row['account_name'],
																	  "total"		 => $total);
		}

		if ($reports->compare) {
			$date = $reports->report_date;
			for ($i = 1; $i <= $reports->compare; $i++) {
				switch ($reports->timeframe) {
					//To Date
					case '*':
					list($year,$month,$day) = explode("-",$date);
					$date = (--$year)."-".$month."-".$day;
					$compare_date[] = $date;
					$sql = "journal.date < '".$date."'";

					break;

					//This Month
					case 1:
					list($year,$month,$day) = explode("-",$date);
					if ($month == 1) {
						$date = (--$year)."-12-".date("t",strtotime($year."-12-01"));
					} else
						$date = $year."-".(--$month)."-".date("t",strtotime($year."-".$month."-01"));

					$compare_date[] = $date;
					$sql = "journal.date < '".$date."'";
					break;

					//Last Month
					case 2:
					list($year,$month,$day) = explode("-",$date);
					if ($month == 1) {
						$date = (--$year)."-12-".date("t",strtotime($year."-12-01"));
					} else
						$date = $year."-".(--$month)."-".date("t",strtotime($year."-".$month."-01"));

					$compare_date[] = $date;
					$sql = "journal.date < '".$date."'";
					break;

					//This Quarter
					case 3:
					$current_qtr = get_quarter($date);
					if ($current_qtr == 1) {
						$previous_qtr = get_quarter(strtotime($date." -4 months"));
						list($start,$date) = get_quarter_dates($previous_qtr,date("Y",strtotime($date." -4 months")));
					} else
						list($start,$date) = get_quarter_dates($current_qtr - 1,date("Y",strtotime($date)));

					$compare_date[] = $date;
					$sql = "journal.date < '".$date."'";
					break;

					//Last Quarter
					case 4:
					$current_qtr = get_quarter($date);
					if ($current_qtr == 1) {
						$previous_qtr = get_quarter(strtotime($date." -4 months"));
						list($start,$date) = get_quarter_dates($previous_qtr,date("Y",strtotime($date." -4 months")));
					} else
						list($start,$date) = get_quarter_dates($current_qtr - 1,date("Y",strtotime($date)));

					$compare_date[] = $date;
					$sql = "journal.date < '".$date."'";
					break;

					//This Period
					case 5:
					$period_info = get_previous_period($date);
					if ($period_info['period_end']) {
						$date = $period_info['period_end'];
						$compare_date[] = $date;
						$sql = "journal.date < '".$date."'";
					}
					break;

					//Last Period
					case 6:
					$period_info = get_previous_period($date);
					if ($period_info['period_end']) {
						$date = $period_info['period_end'];
						$compare_date[] = $date;
						$sql = "journal.date < '".$date."'";
					}
					break;

					//This Year
					case 7:
					list($year,$month,$day) = explode("-",$date);
					$date = (--$year)."-".$month."-".date("t",strtotime($year."-".$month."-01"));
					$compare_date[] = $date;
					$sql = "journal.date < '".$date."'";
					break;

					//Last Year
					case 8:
					list($year,$month,$day) = explode("-",$date);
					$date = (--$year)."-".$month."-".date("t",strtotime($year."-".$month."-01"));
					$compare_date[] = $date;
					$sql = "journal.date < '".$date."'";
					break;

					//Specific Date
					case 9:
					list($year,$month,$day) = explode("-",$date);
					$year--;
					if ($day > date("t",strtotime($year."-".$month."-01")))
						$day = date("t",strtotime($year."-".$month."-01"));

					$date = $year."-".$month."-".$day;
					$compare_date[] = $date;
					$sql = "journal.date < '".$date."'";
					break;
				}
				$result = $db->query("SELECT accounts.account_name , accounts.account_no , journal.account_hash , account_types.type_code ,
											SUM(ROUND(journal.debit,2)) AS debit_total , SUM(ROUND(journal.credit,2)) AS credit_total
											FROM `journal`
											LEFT JOIN `accounts` ON accounts.account_hash = journal.account_hash
											LEFT JOIN `account_types` ON account_types.type_code = accounts.account_type
											WHERE account_types.account_side = 'B' AND ".$sql."
											GROUP BY journal.account_hash");
				while ($row = $db->fetch_assoc($result)) {
					$acct->fetch_account_record($row['account_hash']);
					if ($acct->current_account['account_action'] == 'DR')
						$total = round($row['debit_total'] - $row['credit_total'],2);
					elseif ($acct->current_account['account_action'] == 'CR')
						$total = round($row['credit_total'] - $row['debit_total'],2);

					$account_detail[$acct->account_hash][$i]['total'] = $total;
				}
			}
		}
		
		$xls->xlsMoveRow();
        $xls->xlsMoveRow();
        $xls->xlsWriteLabel($col0,"Assets");
		if ($reports->compare > 0) {
			$xls->xlsWriteLabel($col1,date("Y-m-d"));
			for ($i = 0; $i < $reports->compare; $i++)
				$xls->xlsWriteLabel($col.($i+2),date("Y-m-d",strtotime($compare_date[$i])));
		}
        $xls->xlsMoveRow();
        $xls->xlsWriteLabel($col0,"Current Assets");
        $xls->xlsMoveRow();
        
		$ca =& $accounts['CA'];
		$total = array();
		while (list($account_hash,$info) = each($ca)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$xls->xlsWriteLabel($col0, stripslashes($info['account_name']));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$xls->xlsWriteNumber($col.($i+1),$account_detail[$account_hash][$i]['total']);
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_assets[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
			$xls->xlsMoveRow();
		}
		
		$xls->xlsWriteLabel($col0, 'Total Current Assets');
		for ($i = 0; $i <= $reports->compare; $i++){
			$xls->xlsWriteNumber($col.($i+1), $total[$i]);
		}
        $xls->xlsMoveRow();
            
        $xls->xlsWriteLabel($col0,"Long Term Assets");
        $xls->xlsMoveRow();
        
		$fa =& $accounts['FA'];
		$total = array();
		while (list($account_hash,$info) = each($fa)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$xls->xlsWriteLabel($col0, stripslashes($info['account_name']));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$xls->xlsWriteNumber($col.($i+1),$account_detail[$account_hash][$i]['total']);
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_assets[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
			$xls->xlsMoveRow();
		}
		
		$xls->xlsWriteLabel($col0, 'Total Long Term Assets');
		for ($i = 0; $i <= $reports->compare; $i++){
			$xls->xlsWriteNumber($col.($i+1),$total[$i]);
		}
        $xls->xlsMoveRow();
            
        $xls->xlsWriteLabel($col0,"Total Assets");
        for ($i = 0; $i <= $reports->compare; $i++){
			$xls->xlsWriteNumber($col.($i+1), $total_assets[$i]);
        }
        $xls->xlsMoveRow();
            
        $xls->xlsWriteLabel($col0,"Liabilities");
        $xls->xlsMoveRow();
        $xls->xlsWriteLabel($col0,"Current Liabilities");
        $xls->xlsMoveRow();
		
		$cl =& $accounts['CL'];
		$total = array();
		while (list($account_hash,$info) = each($cl)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$xls->xlsWriteLabel($col0, stripslashes($info['account_name']));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$xls->xlsWriteNumber($col.($i+1),$account_detail[$account_hash][$i]['total']);
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_liab[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
			$xls->xlsMoveRow();
		}
		
		
        $xls->xlsWriteLabel($col0, 'Total Current Liabilities');
        for ($i = 0; $i <= $reports->compare; $i++){
			$xls->xlsWriteNumber($col.($i+1),$total[$i]);
        }
        $xls->xlsMoveRow();
            
        $xls->xlsWriteLabel($col0,"Long Term Liabilities");
        $xls->xlsMoveRow();
        
		$ll =& $accounts['LL'];
		$total = array();
		while (list($account_hash,$info) = each($ll)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$xls->xlsWriteLabel($col0, stripslashes($info['account_name']));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$xls->xlsWriteNumber($col.($i+1),$account_detail[$account_hash][$i]['total']);
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_liab[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
			$xls->xlsMoveRow();
		}
		
		$xls->xlsWriteLabel($col0, 'Total Long Term Liabilities');
		for ($i = 0; $i <= $reports->compare; $i++){
			$xls->xlsWriteNumber($col.($i+1), $total[$i]);
		}
        $xls->xlsMoveRow();
            
        $xls->xlsWriteLabel($col0,"Total Liabilities");
        for ($i = 0; $i <= $reports->compare; $i++){
			$xls->xlsWriteNumber($col.($i+1), $total_liab[$i]);
        }
        $xls->xlsMoveRow();
            
        $xls->xlsWriteLabel($col0,"Shareholder's Equity");
        $xls->xlsMoveRow();

		$eq =& $accounts['EQ'];
		$total = array();
		while (list($account_hash,$info) = each($eq)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$xls->xlsWriteLabel($col0, stripslashes($info['account_name']));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$xls->xlsWriteNumber($col.($i+1),$account_detail[$account_hash][$i]['total']);
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_eq[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
			$xls->xlsMoveRow();
		}
		
		$xls->xlsWriteLabel($col0, "Total Shareholder's Equity");
		for ($i = 0; $i <= $reports->compare; $i++){
			$xls->xlsWriteNumber($col.($i+1),$total_eq[$i]);
		}
        $xls->xlsMoveRow();
            
        $xls->xlsWriteLabel($col0, "Total Liabilities and Equity");
        for ($i = 0; $i <= $reports->compare; $i++){
			$xls->xlsWriteNumber($col.($i+1),$total_liab[$i] + $total_eq[$i]);
        }
        $xls->xlsMoveRow();
		
      
        $xls->xlsStream();
	} elseif ($type == 'income') {
        $reports = new reports();
        $report_hash = $_GET['h'];
        $_POST['report_hash'] = $report_hash;
        $_POST['doit_print'] = 1;

        // Populate data for cash_receipts reports object.
        $reports->doit_income_statement($report_hash);
        $report_title = MY_COMPANY_NAME." Income Statement";

        // Start the XLS document
        $xls = new xls();
        $xls->xlsBOF("Income Statement");
        $xls->xlsWriteLabel(0,$report_title);
        $xls->xlsWriteLabel(2,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
        $col0 = 0;
        $col1 = 1;
        $col2 = 2;
        $col3 = 3;
        $col4 = 4;

        $xls->xlsMoveRow();
        $xls->xlsMoveRow();
        $xls->xlsWriteLabel($col0,"Income");
        $xls->xlsMoveRow();
		$accounts =& $reports->accounts;
        if (is_array($accounts)) {
            // Income
            $in =& $accounts['IN'];
            reset($in);
            $total_in = 0;
            
            while (list($account_hash,$info) = each($in)) {
            	if ($info['total'] != 0 || !$reports->current_report['hide_empty'] || $info['placeholder'] == 1 || $reports->child[$account_hash]) {
					$total_in += $info['total'];
					$xls->xlsWriteLabel($col1, stripslashes(($info['account_no'] ? $info['account_no']." - " : NULL).$info['account_name']));
                 	$xls->xlsWriteNumber($col2, stripslashes($info['total']));
                 	$xls->xlsMoveRow();
            	}
            }
            
            $xls->xlsWriteLabel($col1, 'Total Income');
            $xls->xlsWriteNumber($col2, $total_in);
            $xls->xlsMoveRow();
            
            $xls->xlsWriteLabel($col0,"Cost of Goods Sold");
        	$xls->xlsMoveRow();
        	
        	$cg =& $accounts['CG'];
            reset($cg);
            $total_cg = 0;
            while (list($account_hash,$info) = each($cg)) {
                if ($info['total'] != 0 || !$reports->current_report['hide_empty'] || $info['placeholder'] == 1 || $reports->child[$account_hash]) {
                    $total_cg += $info['total'];
                    $xls->xlsWriteLabel($col1, stripslashes(($info['account_no'] ? $info['account_no']." - " : NULL).$info['account_name']));
                 	$xls->xlsWriteNumber($col2, stripslashes($info['total']));
                 	$xls->xlsMoveRow();
                }
            }
            
            $xls->xlsWriteLabel($col1, 'Total Cost of Goods Sold');
            $xls->xlsWriteNumber($col2, $total_cg);
            $xls->xlsMoveRow();
            
            $xls->xlsWriteLabel($col1,"Gross Profit");
            $xls->xlsWriteNumber($col2, $total_in - $total_cg);
            $xls->xlsMoveRow();
            
            $xls->xlsWriteLabel($col0,"Expenses");
	        $xls->xlsMoveRow();
	        
	        $ex =& $accounts['EX'];
            reset($ex);
            $total_ex = 0;
            while (list($account_hash,$info) = each($ex)) {
            	if ($info['total'] != 0 || !$reports->current_report['hide_empty']) {
					$total_ex += $info['total'];
            		$xls->xlsWriteLabel($col1, stripslashes(($info['account_no'] ? $info['account_no']." - " : NULL).$info['account_name']));
                 	$xls->xlsWriteNumber($col2, stripslashes($info['total']));
                 	$xls->xlsMoveRow();
                 	if ($reports->child[$account_hash]) {
                 		$child_total = $info['total'];
                 		reset($reports->child[$account_hash]);
                 		while(list($child_hash,$child_info) = each($reports->child[$account_hash])) {
                 			if ($child_info['total'] != 0 || !$reports->current_report['hide_empty']) {
                 				$total_ex += $child_info['total'];
                 				$child_total += $child_info['total'];
                 				$xls->xlsWriteLabel($col1, stripslashes(($child_info['account_no'] ? $child_info['account_no']." - " : NULL).$child_info['account_name']));
                 				$xls->xlsWriteNumber($col2, stripslashes($child_info['total']));
                 				$xls->xlsMoveRow();
                 			}
                 		}
                 		$xls->xlsWriteLabel($col1, stripslashes("Total ".$info['account_name']));
                 		$xls->xlsWriteNumber($col2, $child_total);
                 		$xls->xlsMoveRow();
                 	}
                }
            }
            
            $xls->xlsWriteLabel($col1, 'Total Expenses');
            $xls->xlsWriteNumber($col2, $total_ex);
            $xls->xlsMoveRow();
            
            $xls->xlsWriteLabel($col1, "Net Profit");
            $xls->xlsWriteNumber($col2, $total_in - $total_cg - $total_ex);
            $xls->xlsMoveRow();
            
		}
      
        $xls->xlsStream();
	} elseif ($type == 'checkRec') {
        $reports = new reports();
        $report_hash = $_GET['h'];
        $_POST['report_hash'] = $report_hash;

        // Populate data for cash_receipts reports object.
        $reports->check_reconcile();
        $report_title = MY_COMPANY_NAME." Check Reconciliation Report";

        // Start the XLS document
        $xls = new xls();
        $xls->xlsBOF("Check Reconciliation Report");
        $xls->xlsWriteLabel(0,$report_title);
        $xls->xlsWriteLabel(2,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
        $col0 = 0;
        $col1 = 1;
        $col2 = 2;
        $col3 = 3;
        $col4 = 4;

        $xls->xlsMoveRow();
        $xls->xlsMoveRow();
        $xls->xlsWriteLabel($col0,"Check No.");
        $xls->xlsWriteLabel($col1,"Account");
        $xls->xlsWriteLabel($col2,"Check Date");
        $xls->xlsWriteLabel($col3,"Payee");
        $xls->xlsWriteLabel($col4,"Amount");
        $xls->xlsMoveRow();
		if ($reports->results) {

            for ($i = 0; $i < count($reports->results); $i++) {
            	$xls->xlsWriteLabel($col0, stripslashes($reports->results[$i]['check_no']));
        		$xls->xlsWriteLabel($col1, stripslashes($reports->results[$i]['account_no']. " : " .$reports->results[$i]['account_name']));
        		$xls->xlsWriteLabel($col2, stripslashes($reports->results[$i]['check_date']));
        		$xls->xlsWriteLabel($col3, stripslashes($reports->results[$i]['vendor_name']));
        		$xls->xlsWriteLabel($col4, stripslashes($reports->results[$i]['amount']));
                $xls->xlsMoveRow();
            }
		}
      
        $xls->xlsStream();
	} elseif ($type == 'check_run') {
        $reports = new reports();
        $report_hash = $_GET['h'];
        $_POST['doit_print'] = 0;
        $_POST['report_hash'] = $report_hash;

        // Populate data for cash_receipts reports object.
        $reports->fetch_report_settings($report_hash);
        $report_title = MY_COMPANY_NAME." Check Run Report";

        // Start the XLS document
        $xls = new xls();
        $xls->xlsBOF("Check Run Report");
        $xls->xlsWriteLabel(0,$report_title);
        $xls->xlsWriteLabel(2,"Generated on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
        $col0 = 0;
        $col1 = 1;
        $col2 = 2;
        $col3 = 3;
        $col4 = 4;
        $col5 = 5;
        $col6 = 6;
        $col7 = 7;
        $col8 = 8;
        $col9 = 9;

        $xls->xlsMoveRow();
        $xls->xlsMoveRow();
        $xls->xlsWriteLabel($col0,"Check No.");
        $xls->xlsWriteLabel($col1,"Date");
        $xls->xlsWriteLabel($col2,"Type");
        $xls->xlsWriteLabel($col3,"Reference");
        $xls->xlsWriteLabel($col4,"Amount");
        $xls->xlsWriteLabel($col5,"Discounts");
        $xls->xlsWriteLabel($col6,"Deposits");
        $xls->xlsWriteLabel($col7,"Credits");
        $xls->xlsWriteLabel($col8,"Credit No.");
        $xls->xlsWriteLabel($col9,"Payment");
        $xls->xlsMoveRow();
		
		$db->query("CALL check_run('{$reports->current_report['account_hash']}','{$reports->current_report['date_start']}','{$reports->current_report['date_end']}');");
		$sql = stripslashes($reports->current_report['sql']);
		$r = $db->query("SELECT * FROM _tmp_reports_data_check_run".($sql ? "
							   WHERE $sql" : NULL)."
							   GROUP BY check_no");

		if ($db->num_rows($r) > 0) {
			while($row = $db->fetch_assoc($r)) {
				$r2 = $db->query("SELECT * FROM _tmp_reports_data_check_run
										WHERE check_no = '".$row['check_no']."' AND invoice_no IS NOT NULL");
				$xls->xlsWriteLabel($col0,$row['check_no']);
				$xls->xlsWriteLabel($col1,date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($row['check_date'])));
				$xls->xlsWriteLabel($col3,stripslashes($row['payee_name']));
				$xls->xlsWriteLabel($col9,$row['check_symbol'].number_format($row['check_amount'],2));
				if ($db->num_rows($r) > 0)
				$xls->xlsMoveRow();

				while($invoice = $db->fetch_assoc($r2)) {
					$xls->xlsWriteLabel($col1,date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($invoice['invoice_date'])));
					$xls->xlsWriteLabel($col2,$invoice['invoice_type']);
					$xls->xlsWriteLabel($col3,$invoice['invoice_no']);
					$xls->xlsWriteLabel($col4,($invoice['invoice_amount'] > 0 ? $invoice['invoice_symbol'].number_format($invoice['invoice_amount'],2) : NULL));
					$xls->xlsWriteLabel($col5,($invoice['discount_amount'] > 0 ? $invoice['payment_symbol'].number_format($invoice['discount_amount'],2) : NULL));
					$xls->xlsWriteLabel($col6,($invoice['deposit_amount'] > 0 ? $invoice['payment_symbol'].number_format($invoice['deposit_amount'],2) : NULL));
					$xls->xlsWriteLabel($col7,($invoice['credit_applied'] > 0 ? $invoice['credit_symbol'].number_format($invoice['credit_applied'],2) : NULL));
					$xls->xlsWriteLabel($col8,$invoice['credit_no']);
					$xls->xlsMoveRow();
                }
                $xls->xlsMoveRow();
			}
		}
      
        $xls->xlsStream();
	}
}
?>