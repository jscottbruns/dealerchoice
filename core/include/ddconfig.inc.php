<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
$p = new permissions($_SESSION['id_hash']);
echo "
<script>
function createjsDOMenu() {";
	//Customers menu
	if ($p->ck('customers','L') || $p->ck('customers','A','rcv_pmt') || $p->ck('customers','A','customer_invoice') || $p->ck('customer_credits','A') || $p->ck('customer_credits','L')) {
		$customers = true;
		echo  "
		mainMenu1 = new jsDOMenu(200);
		with (mainMenu1) {".($p->ck('customers','L') ? "
			addMenuItem(new menuItem(\"Customer List\",\"item1\", \"code:agent.call('customers','sf_loadcontent','cf_loadcontent','showall');\"));" : NULL).($p->ck('customers','A','rcv_pmt') ? "
			addMenuItem(new menuItem(\"Receive Payments\", \"\", \"code:agent.call('customers','sf_loadcontent','show_popup_window','receive_payment','popup_id=customer_popup');\"));" : NULL).($p->ck('customer_credits','A') || $p->ck('customer_credits','L') ? "
            addMenuItem(new menuItem(\"Customer Credits\", \"item2\", \"code:agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall');\"));" : NULL)."
    	}".($p->ck('customers','A') || $p->ck('customers','L') ? "
		mainMenu1_1 = new jsDOMenu(200);
		with (mainMenu1_1) {".($p->ck('customers','A') ? "
			addMenuItem(new menuItem(\"Create a New Customer\", \"\", \"code:agent.call('customers','sf_loadcontent','show_popup_window','edit_customer','popup_id=customer_popup');\"));" : NULL).($p->ck('customers','L') ? "
			addMenuItem(new menuItem(\"Search for a Customer\", \"\", \"code:agent.call('customers','sf_loadcontent','show_popup_window','search_customers','popup_id=customer_popup');\"));" : NULL)."
		}
		mainMenu1.items.item1.setSubMenu(mainMenu1_1); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL).($p->ck('customer_credits','A') || $p->ck('customer_credits','L') ? "
        mainMenu1_2 = new jsDOMenu(200);
        with (mainMenu1_2) {".($p->ck('customer_credits','A') ? "
            addMenuItem(new menuItem(\"Create Customer Credit\", \"\", \"code:agent.call('customer_credits','sf_loadcontent','show_popup_window','edit_credit','popup_id=cc_new_win');\"));" : NULL).($p->ck('customer_credits','L') ? "
            addMenuItem(new menuItem(\"Search Customer Credits\", \"\", \"code:agent.call('customer_credits','sf_loadcontent','show_popup_window','search_credits','popup_id=cc_search_win');\"));" : NULL)."
        }
        mainMenu1.items.item2.setSubMenu(mainMenu1_2); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL);
	}
	//Vendors menu
	if ($p->ck('vendors','L') || $p->ck('proposals','A','purchase_order') || $p->ck('payables','L')) {
		$vendors = true;
		echo  "
		mainMenu2 = new jsDOMenu(205);
		with (mainMenu2) {".($p->ck('vendors','L') ? "
			addMenuItem(new menuItem(\"Vendor List\",\"item1\", \"code:agent.call('vendors','sf_loadcontent','cf_loadcontent','showall');\"));" : NULL).($p->ck('payables','L') ? "
			addMenuItem(new menuItem(\"Receive & Pay Bills\", \"item2\", \"code:agent.call('payables','sf_loadcontent','cf_loadcontent','showall');\"));" : NULL). "
		}".($p->ck('vendors','L') && $p->ck('vendors','A') || $p->ck('vendors','L') ? "
		mainMenu2_1 = new jsDOMenu(175);
		with (mainMenu2_1) {".($p->ck('vendors','A') ? "
			addMenuItem(new menuItem(\"Create a New Vendor\", \"\", \"code:agent.call('vendors','sf_loadcontent','show_popup_window','edit_vendor','popup_id=vendor_popup');\"));" : NULL).($p->ck('vendors','L') ? "
			addMenuItem(new menuItem(\"Search for a Vendor\", \"\", \"code:agent.call('vendors','sf_loadcontent','show_popup_window','search_vendors','popup_id=vendor_popup');\"));" : NULL)."
		}
		mainMenu2.items.item1.setSubMenu(mainMenu2_1); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL).($p->ck('payables','L') && $p->ck('payables','A') ? "
		mainMenu2_2 = new jsDOMenu(210);
		with (mainMenu2_2) {
			addMenuItem(new menuItem(\"New Vendor Bill\", \"\", \"code:agent.call('payables','sf_loadcontent','show_popup_window','edit_invoice','popup_id=line_item');\"));
			addMenuItem(new menuItem(\"Receive Vendor Credits\", \"\", \"code:agent.call('payables','sf_loadcontent','show_popup_window','edit_invoice','invoice_type=C','popup_id=line_item');\"));
			addMenuItem(new menuItem(\"Create a Customer Refund\", \"\", \"code:agent.call('payables','sf_loadcontent','show_popup_window','edit_invoice','invoice_type=R','popup_id=line_item');\"));
		}
		mainMenu2.items.item2.setSubMenu(mainMenu2_2); " : NULL);
	}

	if ($p->ck('proposals','A') || $p->ck('proposals','L')) {
		$proposals = true;
		//Proposals menu
		echo  "
		mainMenu4 = new jsDOMenu(200);
		with (mainMenu4) {".($p->ck('proposals','L') ? "
			addMenuItem(new menuItem(\"Proposal List\", \"item1\", \"code:agent.call('proposals','sf_loadcontent','cf_loadcontent','showall');\"));" : NULL).($p->ck('pm_module','V','schedule') || $p->ck('pm_module','L','work_orders') ? "
			addMenuItem(new menuItem(\"Project Management\", \"item2\"));" : NULL)."
		}".($p->ck('proposals','A') || $p->ck('proposals','L') ? "
		mainMenu4_1 = new jsDOMenu(200);
		with (mainMenu4_1) {".($p->ck('proposals','A') ? "
			addMenuItem(new menuItem(\"Create a New Proposal\", \"\", \"code:agent.call('proposals','sf_loadcontent','show_popup_window','edit_proposal','popup_id=main_popup');\"));" : NULL).($p->ck('proposals','L') ? "
			addMenuItem(new menuItem(\"Search for a Proposal\", \"\", \"code:agent.call('proposals','sf_loadcontent','show_popup_window','search_proposals','popup_id=main_popup');\"));" : NULL)."
		}
		mainMenu4.items.item1.setSubMenu(mainMenu4_1); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL).($p->ck('pm_module','V','schedule') || $p->ck('pm_module','L','work_orders') ? "
		mainMenu4_2 = new jsDOMenu(200);
		with (mainMenu4_2) {".($p->ck('pm_module','L','schedule') ? "
			addMenuItem(new menuItem(\"Install & Delivery Schedule\", \"\", \"code:agent.call('pm_module','sf_loadcontent','cf_loadcontent','calendar');\"));" : NULL).($p->ck('pm_module','V','work_orders') ? "
			addMenuItem(new menuItem(\"Work Orders\", \"\", \"code:agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list');\"));" : NULL)."
		}
		mainMenu4.items.item2.setSubMenu(mainMenu4_2);" : NULL);
	}
	if ($_SESSION['is_mngr'] || $p->ck('system_config','L','users') || $p->ck('system_config','L','company_settings') || $p->ck('system_config','L','system_maintain') || $p->ck('system_config','L','commission_tables')) {
		$system = true;
		//System menu
		/*
		($_SESSION['is_mngr'] ? "
			addMenuItem(new menuItem(\"System Overrides\",\"\",\"code:agent.call('override','sf_loadcontent','show_popup_window','override_win','popup_id=override_popup');\"));" : NULL)
		*/
		echo  "
		mainMenu5 = new jsDOMenu(200);
		with (mainMenu5) {".($p->ck('system_config','L','users') || $p->ck('system_config','L','company_settings') || $p->ck('system_config','L','system_maintain') || $p->ck('system_config','L','commission_tables') ? "
			addMenuItem(new menuItem(\"System Configuration\",\"\",\"code:agent.call('system_config','sf_loadcontent','cf_loadcontent','edit_system');\"));" : NULL)."
		}";
	}
	if ($p->ck('arch_design','A') || $p->ck('arch_design','L')) {
		$a_d = true;
		//a&d menu
		echo  "
		mainMenu6 = new jsDOMenu(125);
		with (mainMenu6) {".($p->ck('arch_design','L') ? "
			addMenuItem(new menuItem(\"A & D List\",\"item1\",\"code:agent.call('arch_design','sf_loadcontent','cf_loadcontent','showall');\"));" : NULL)."
		}".($p->ck('arch_design','A') || $p->ck('arch_design','L') ? "
		mainMenu6_1 = new jsDOMenu(200);
		with (mainMenu6_1) {".($p->ck('arch_design','A') ? "
			addMenuItem(new menuItem(\"Create a New A & D Firm\", \"\", \"code:agent.call('arch_design','sf_loadcontent','show_popup_window','edit_arch','popup_id=main_popup');\"));" : NULL).($p->ck('arch_design','L') ? "
			addMenuItem(new menuItem(\"Search A & D Firms\", \"\", \"code:agent.call('arch_design','sf_loadcontent','show_popup_window','search_archs','popup_id=main_popup');\"));" : NULL)."
		}
		mainMenu6.items.item1.setSubMenu(mainMenu6_1); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL);
	}

	if ($p->ck('accounting','L','journal') || $p->ck('accounting','L','accounts') || $p->ck('accounting','V','check_reg') || $p->ck('accounting','D','period') || $p->ck('accounting','V','config')) {
		$accounting = true;
		//Accounting menu
		echo  "
		mainMenu3 = new jsDOMenu(225);
		with (mainMenu3) {".($p->ck('accounting','L','journal') ? "
			addMenuItem(new menuItem(\"General Journal\",\"item1\",\"code:agent.call('accounting','sf_loadcontent','cf_loadcontent','show_journal');\"));" : NULL).($p->ck('accounting','L','accounts') ? "
			addMenuItem(new menuItem(\"Chart of Accounts\", \"item2\", \"code:agent.call('accounting','sf_loadcontent','cf_loadcontent','showall');\"));" : NULL).($p->ck('accounting','V','check_reg') ? "
			addMenuItem(new menuItem(\"Check Register\",\"\",\"code:agent.call('accounting','sf_loadcontent','cf_loadcontent','check_reg');\"));" : NULL).($p->ck('accounting','V','bank_rec') ? "
			addMenuItem(new menuItem(\"Reconcile Bank Accounts\",\"\",\"code:agent.call('accounting','sf_loadcontent','show_popup_window','bank_rec_prompt','popup_id=back_rec_win');\"));" : NULL)."
	}".($p->ck('accounting','L','journal') || $p->ck('accounting','D','period') || $p->ck('accounting','E','config') ? "
		mainMenu3_1 = new jsDOMenu(200);
		with (mainMenu3_1) {".($p->ck('accounting','L','journal') ? "
			addMenuItem(new menuItem(\"View General Journal\", \"item1_1\", \"code:agent.call('accounting','sf_loadcontent','cf_loadcontent','show_journal');\"));" : NULL).($p->ck('accounting','E','period') ? "
			addMenuItem(new menuItem(\"Perform Period Closings\", \"\", \"code:agent.call('accounting','sf_loadcontent','cf_loadcontent','close_period');\"));" : NULL).($p->ck('accounting','E','config') ? "
			addMenuItem(new menuItem(\"Business Cycle Settings\", \"\", \"code:agent.call('accounting','sf_loadcontent','cf_loadcontent','config');\"));" : NULL)."
		}
		mainMenu3.items.item1.setSubMenu(mainMenu3_1); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL).($p->ck('accounting','A','journal') || $p->ck('accounting','L','journal') ? "
		mainMenu3_1_1 = new jsDOMenu(200);
		with (mainMenu3_1_1) {".($p->ck('accounting','A','journal') ? "
		addMenuItem(new menuItem(\"Create Journal Entries\", \"\", \"code:agent.call('accounting','sf_loadcontent','show_popup_window','new_journal_entry','popup_id=line_item');\"));
		addMenuItem(new menuItem(\"CSV Trial Balance Import\", \"\", \"code:agent.call('accounting','sf_loadcontent','show_popup_window','upload_journal_entry','popup_id=upload_journal_win');\"));" : NULL).($p->ck('accounting','L','journal') ? "
		addMenuItem(new menuItem(\"Search Journal Entries\", \"\", \"code:agent.call('accounting','sf_loadcontent','show_popup_window','search_journal_entries','popup_id=line_item');\"));" : NULL)."
		}
		mainMenu3_1.items.item1_1.setSubMenu(mainMenu3_1_1); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL).($p->ck('accounting','A','accounts') ? "
		mainMenu3_2 = new jsDOMenu(175);
		with (mainMenu3_2) {
			addMenuItem(new menuItem(\"Create New Accounts\", \"\", \"code:agent.call('accounting','sf_loadcontent','show_popup_window','edit_account','popup_id=line_item');\"));
		}
		mainMenu3.items.item2.setSubMenu(mainMenu3_2); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL);
	}
	if ($p->ck('reports','V','ar') || $p->ck('reports','V','ar_reconcile') || $p->ck('reports','V','cash_receipts') || $p->ck('reports','V','cash_flow_exp') || $p->ck('reports','V','customer_balance'))
		$ar = true;
	if ($p->ck('reports','V','ap') || $p->ck('reports','V','cash_req') || $p->ck('reports','V','cash_disp') || $p->ck('reports','V','vendor_balance') || $p->ck('reports','V','sales_tax') || $p->ck('reports','V','po') || $p->ck('reports','V','vendor_discounting') || $p->ck('reports','V','wip_reconcile') || $p->ck('reports','V','1099'))
		$ap = true;
	if ($p->ck('reports','V','commissions') || $p->ck('reports','V','work_order') || $p->ck('reports','V','job_costing') || $p->ck('reports','V','product_sales') || $p->ck('reports','V','bookings') || $p->ck('reports','V','invoiced_sales') || $p->ck('reports','V','backlog'))
		$proposals = true;
	if ($p->ck('reports','V','check_reconcile') || $p->ck('reports','V','check_reg') || $p->ck('reports','V','cash_flow') || $p->ck('reports','V','trial_balance') || $p->ck('reports','V','income_stmtm') || $p->ck('reports','V','balance_sheet'))
		$finance = true;

	$reports = new reports();
	$reports->fetch_custom_reports();
	if (count($reports->custom_reports)) {
		while (list($report_hash,$report_info) = each($reports->custom_reports)) {
			$saved_category = $report_info['saved_catagory'];
			unset ($doit_func);
			switch ($saved_category) {
				case 'ar_report':
					$doit_func = "doit_ar";
				break;

				case 'ap_report':
					$doit_func = "doit_ap";
				break;

				case 'invoiced_sales':
					$doit_func = "doit_invoiced_sales_report";
				break;

				default:
					$doit_func = "doit_$saved_category";
				break;
			}
			$custom_reports .= "addMenuItem(new menuItem(\"".htmlspecialchars((strlen($report_info['report_name']) > 32 ? substr($report_info['report_name'],0,30)."..." : $report_info['report_name']))."\", \"\", \"code:agent.call('reports','sf_loadcontent','cf_loadcontent','$doit_func','report_hash=".$report_hash."','from_saved=1');\"));\n";
		}
	} else
		$custom_reports = "addMenuItem(new menuItem(\"No Saved Reports\", \"\", \"code:void(0);\"));";

	$reports->fetch_shared_reports();
	if (count($reports->shared_reports)) {
		while (list($report_hash,$report_info) = each($reports->shared_reports)) {
			$saved_category = $report_info['saved_catagory'];
			unset ($doit_func);
			switch ($saved_category) {
				case 'ar_report':
					$doit_func = "doit_ar";
				break;

				case 'ap_report':
					$doit_func = "doit_ap";
				break;

				case 'invoiced_sales':
					$doit_func = "doit_invoiced_sales_report";
				break;

				default:
					$doit_func = "doit_$saved_category";
				break;
			}
			$shared_reports .= "addMenuItem(new menuItem(\"".htmlspecialchars((strlen($report_info['report_name']) > 32 ? substr($report_info['report_name'],0,30)."..." : $report_info['report_name']))."\", \"\", \"code:agent.call('reports','sf_loadcontent','cf_loadcontent','$doit_func','report_hash=".$report_hash."','from_saved=1');\"));\n";
		}
	} else
		$shared_reports = "addMenuItem(new menuItem(\"No Shared Reports\", \"\", \"code:void(0);\"));";

	//if ($p->ck('proposals','A') || $p->ck('proposals','L') || $p->ck('status_reports','L') || $p->ck('proposals','R')) {
		$reports = true;
		//Reports menu			addMenuItem(new menuItem(\"Reports Navigator\", \"\",\"code:agent.call('reports','sf_loadcontent','cf_loadcontent','navigator');\"));
		echo  "
		mainMenu7 = new jsDOMenu(225);
		with (mainMenu7) {
			addMenuItem(new menuItem(\"Reports Navigator\", \"\", \"code:agent.call('reports','sf_loadcontent','cf_loadcontent','navigator');\"));".($ar ? "
			addMenuItem(new menuItem(\"Customers & Receivables\", \"item1\", \"\"));" : NULL).($ap ? "
			addMenuItem(new menuItem(\"Vendors & Payables\", \"item2\", \"\"));" : NULL).($proposals ? "
			addMenuItem(new menuItem(\"Proposals & Sales\", \"item3\", \"\"));" : NULL).($finance ? "
			addMenuItem(new menuItem(\"Financial\", \"item5\", \"\"));" : NULL)."
			addMenuItem(new menuItem(\"My Saved Reports\", \"item6\", \"\"));
			addMenuItem(new menuItem(\"Shared Reports\", \"item7\", \"\"));
		}".($ar ? "
		mainMenu7_1 = new jsDOMenu(250);
		with (mainMenu7_1) {".($p->ck('reports','V','ar') ? "
			addMenuItem(new menuItem(\"Accounts Receivable\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','ar_report','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','ar_reconcile') ? "
			addMenuItem(new menuItem(\"Accounts Receivable Reconciliation\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','ar_reconcile','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','cash_receipts') ? "
			addMenuItem(new menuItem(\"Cash Receipts Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','cash_receipts','popup_id=report_filter');\"));" : NULL).($asdf && $p->ck('reports','V','cash_flow_exp') ? "
			addMenuItem(new menuItem(\"Cash Flow Expectations\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','cash_flow_exp','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','customer_balance') ? "
			addMenuItem(new menuItem(\"Customer Balance Summary\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','customer_balance','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','customer_balance') ? "
			addMenuItem(new menuItem(\"Customer Statement\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','customer_statement','popup_id=report_filter');\"));" : NULL)."
		}
		mainMenu7.items.item1.setSubMenu(mainMenu7_1);" : NULL)."
		".($ap ? "
		mainMenu7_2 = new jsDOMenu(225);
		with (mainMenu7_2) {".($p->ck('reports','V','ap') ? "
			addMenuItem(new menuItem(\"Accounts Payable\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','ap_report','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','cash_req') ? "
			addMenuItem(new menuItem(\"Cash Requirements\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','cash_requirements','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','cash_disp') ? "
			addMenuItem(new menuItem(\"Cash Disbursements\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','cash_disp','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','vendor_balance') ? "
			addMenuItem(new menuItem(\"Vendor Balance Summary\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','vendor_balance','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','sales_tax') ? "
			addMenuItem(new menuItem(\"Sales Tax Liability\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','sales_tax','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','po') ? "
			addMenuItem(new menuItem(\"Purchase Order Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','po_report','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','vendor_discounting') ? "
			addMenuItem(new menuItem(\"Vendor Discounting\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','vendor_discounting','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','wip_reconcile') ? "
			addMenuItem(new menuItem(\"WIP Reconciliation\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','wip_reconcile','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','1099') ? "
            addMenuItem(new menuItem(\"WIP Detail Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','wip_report','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','1099') ? "
			addMenuItem(new menuItem(\"Vendor 1099 Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','vendor_1099','popup_id=report_filter');\"));" : NULL)."
		}
		mainMenu7.items.item2.setSubMenu(mainMenu7_2);" : NULL)."
		".($proposals ? "
		mainMenu7_3 = new jsDOMenu(225);
		with (mainMenu7_3) {".($p->ck('reports','V','project_status') ? "
			addMenuItem(new menuItem(\"Project Status Reports\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','status_report','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','backlog') ? "
			addMenuItem(new menuItem(\"Backlog Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','backlog_report','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','invoiced_sales') ? "
			addMenuItem(new menuItem(\"Invoiced Sales Summary\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','invoiced_sales','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','bookings') ? "
			addMenuItem(new menuItem(\"Bookings Report Summary\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','bookings_report','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','product_sales') ? "
			addMenuItem(new menuItem(\"Product Sales Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','product_sales_report','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','job_costing') ? "
			addMenuItem(new menuItem(\"Job Costing Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','job_costing','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','commissions') ? "
			addMenuItem(new menuItem(\"Commissions Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','commission_report','popup_id=report_filter');\"));
			addMenuItem(new menuItem(\"Commissions Paid Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','commissions_paid_report','popup_id=report_filter');\"));" : NULL).($asdf && $p->ck('reports','V','work_order') ? "
			addMenuItem(new menuItem(\"Work Order Bookings Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','work_order_bookings','popup_id=report_filter');\"));" : NULL)."
		}
		mainMenu7.items.item3.setSubMenu(mainMenu7_3);" : NULL)."

		".($finance ? "
		mainMenu7_5 = new jsDOMenu(225);
		with (mainMenu7_5) {".($p->ck('reports','V','balance_sheet') ? "
			addMenuItem(new menuItem(\"Balance Sheet\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','balance_sheet','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','income_stmtm') ? "
			addMenuItem(new menuItem(\"Income Statement\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','income_statement','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','trial_balance') ? "
			addMenuItem(new menuItem(\"Trial Balance\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','trial_balance','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','cash_flow') ? "
			addMenuItem(new menuItem(\"Statement of Cash Flows\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','cash_flows','popup_id=report_filter');\"));" : NULL).($p->ck('reports','V','check_reconcile') ? "
            addMenuItem(new menuItem(\"Check Reconciliation Report\", \"\", \"code:agent.call('reports','sf_loadcontent','cf_loadcontent','check_reconcile');\"));" : NULL).($p->ck('reports','V','check_run') ? "
            addMenuItem(new menuItem(\"Check Run Report\", \"\", \"code:agent.call('reports','sf_loadcontent','show_popup_window','check_run','popup_id=report_filter');\"));" : NULL)."
		}
		mainMenu7.items.item5.setSubMenu(mainMenu7_5);" : NULL)."

		mainMenu7_6 = new jsDOMenu(225);
		with (mainMenu7_6) {
			".$custom_reports."
		}
		mainMenu7.items.item6.setSubMenu(mainMenu7_6);

		mainMenu7_7 = new jsDOMenu(225);
		with (mainMenu7_7) {
			".$shared_reports."
		}
		mainMenu7.items.item7.setSubMenu(mainMenu7_7);";

	echo  "
	mainMenu8 = new jsDOMenu(200);
	with (mainMenu8) {
        addMenuItem(new menuItem(\"Fax & Email Terminal\",\"\",\"code:agent.call('mail','sf_loadcontent','show_popup_window','agent','popup_id=line_item');\"));
        addMenuItem(new menuItem(\"DealerChoice User Manual\",\"\",\"code:window.open('http://help.dc-sysllc.com','_blank')\"));
        addMenuItem(new menuItem(\"Release Notes\",\"\",\"code:window.open('http://wiki.dc-sysllc.com','_blank')\"));".($asdf ? "
        addMenuItem(new menuItem(\"DealerChoice Support\",\"item1\",\"\"));" : NULL)."
	};".($asdf ? "
	mainMenu8_1 = new jsDOMenu(200);
	with (mainMenu8_1) {
		addMenuItem(new menuItem(\"New Support Ticket\", \"\", \"code:agent.call('support','sf_loadcontent','show_popup_window','support_form');\"));
	}
	mainMenu8.items.item1.setSubMenu(mainMenu8_1); //ASSOCIATE SUB MENU WITH PARENT MENU ITEM" : NULL)."
	menuBar = new jsDOMenuBar(); //CREATE MAIN MENU ITEMS
	with (menuBar) {
		addMenuBarItem(new menuBarItem(\"Home\",\"\",\"\",\"\",\"index.php\"));".($customers ? "
		addMenuBarItem(new menuBarItem(\"Customers\", mainMenu1));" : NULL).($vendors ? "
		addMenuBarItem(new menuBarItem(\"Vendors\", mainMenu2));" : NULL).($a_d ? "
		addMenuBarItem(new menuBarItem(\"A & D\",mainMenu6));" : NULL).($proposals ? "
		addMenuBarItem(new menuBarItem(\"Proposals\", mainMenu4));" : NULL).($system ? "
		addMenuBarItem(new menuBarItem(\"System\",mainMenu5));" : NULL).($accounting ? "
		addMenuBarItem(new menuBarItem(\"Accounting\", mainMenu3));" : NULL)."
		addMenuBarItem(new menuBarItem(\"Reports\", mainMenu7));
		addMenuBarItem(new menuBarItem(\"Help & Communications\",mainMenu8));
	}
	menuBar.moveTo(11, 5);
}
</script>
";
//($p->ck('accounting','V','account_reg') ? "
//            addMenuItem(new menuItem(\"Account Register\",\"\",\"code:agent.call('accounting','sf_loadcontent','cf_loadcontent','account_reg');\"));" : NULL)
unset($reports);
?>