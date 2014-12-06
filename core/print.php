<?php
require_once('include/common.php');
require_once('include/pdf.class.php');

$type = base64_decode($_GET['t']);
$template_hash = $_GET['m'];
$print_pref_hash = $_GET['p'];

if ($type) {
	require_once('include/print.class.php');
	$print_it = new print_it($type, $template_hash, $print_pref_hash);
	if ($type == 'proposal')
		$print_it->proposal($_GET['h']);
	elseif ($type == 'po') {
		//Multiple POs
		if (ereg("\|",$_GET['h'])) {
			$po_hash_array = explode("|",$_GET['h']);
			for ($i = 0; $i < count($po_hash_array); $i++)
				$print_it->purchase_order($_GET['h2'],$po_hash_array[$i],$i);

		} else
			$print_it->purchase_order($_GET['h2'],$_GET['h']);
	} elseif ($type == 'customer_invoice') {
		//Multiple POs
		if (ereg("\|",$_GET['h'])) {
			$invoice_hash_array = explode("|",$_GET['h']);
			for ($i = 0; $i < count($invoice_hash_array); $i++)
				$print_it->customer_invoice($_GET['h2'],$invoice_hash_array[$i],$i);

		} else {
			$_POST['customer_contact'] = urldecode($_GET['c']);
			$print_it->customer_invoice($_GET['h2'],$_GET['h']);
		}
	} elseif ($type == 'po_summary')
		$print_it->po_summary($_GET['h']);
	elseif ($type == 'delivery_ticket') {
		//Multiple POs
		if (ereg("\|",$_GET['h'])) {
			$po_hash_array = explode("|",$_GET['h']);
			for ($i = 0; $i < count($po_hash_array); $i++)
				$print_it->delivery_ticket($_GET['h2'],$po_hash_array[$i],$i,($po_hash_array ? true : false));

		} else
			$print_it->delivery_ticket($_GET['h2'],$_GET['h']);

	} elseif ($type == 'check')
		$print_it->printable_check($_GET['h']);
	elseif ($type == 'balance_sheet')
		$print_it->balance_sheet($_GET['h']);
	elseif ($type == 'work_order')
		$print_it->work_order($_GET['h'],$_GET['h2']);
	elseif ($type == 'ar_report')
		$print_it->ar_report($_GET['h']);
	elseif ($type == 'ap_report')
		$print_it->ap_report($_GET['h']);
	elseif ($type == 'trial_balance')
		$print_it->trial_balance($_GET['h']);
	elseif ($type == 'cash_flow_exp')
		$print_it->cash_flow_exp($_GET['h']);
	elseif ($type == 'cash_requirements')
		$print_it->cash_requirements($_GET['h']);
	elseif ($type == 'cash_receipts')
		$print_it->cash_receipts($_GET['h']);
	elseif ($type == 'sales_tax')
		$print_it->sales_tax($_GET['h']);
	elseif ($type == 'customer_balance')
		$print_it->customer_balance($_GET['h']);
	elseif ($type == 'cash_disp')
		$print_it->cash_disp($_GET['h']);
	elseif ($type == 'vendor_balance')
		$print_it->vendor_balance($_GET['h']);
	elseif ($type == 'po_report')
		$print_it->po_report($_GET['h']);
	elseif ($type == 'vendor_discounting')
		$print_it->vendor_discounting($_GET['h']);
	elseif ($type == 'backlog_report')
		$print_it->backlog_report($_GET['h']);
	elseif ($type == 'invoiced_sales')
		$print_it->invoiced_sales($_GET['h']);
	elseif ($type == 'bookings_report')
		$print_it->bookings_report($_GET['h']);
	elseif ($type == 'product_sales')
		$print_it->product_sales_report($_GET['h']);
	elseif ($type == 'commissions_paid')
		$print_it->commissions_paid_report($_GET['h']);
	elseif ($type == 'commission_report')
		$print_it->commission_report($_GET['h']);
	elseif ($type == 'income')
		$print_it->income_statement($_GET['h']);
	elseif ($type == 'proforma_invoice')
		$print_it->proforma_invoice($_GET['h']);
    elseif ($type == 'vendor_1099_std')
        $print_it->vendor_1099_std($_GET['h']);
    elseif ($type == 'vendor_1099') {
    	//define('FPDF_FONTPATH','/var/www/html/dev2.dealer-choice.com/core/include/pdf/font/');
        require_once('include/pdf/fpdf.php');
        require_once('include/pdf/fpdi.php');

        $print_it->vendor_1099IRS('1099MISC_', $pdf);
        exit;
    } elseif ($type == 'wip_report') {
        $print_it->wip_report($_GET['h']);
    } elseif ($type == 'check_run')
		$print_it->check_run($_GET['h']);
	elseif ($type == 'customer_statement')
		$print_it->customer_statement($_GET['h']);
	else
		exit;

	$print_it->pdf->ezStream();
}
exit;
?>