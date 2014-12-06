<?php
require_once('include/common.php');

if ($_GET['doc_hash'] || $_GET['dhash']) {
	if ($doc_hash = $_GET['doc_hash']) {
		if ($checkout_hash = $_GET['checkout_hash'])
			$db->query("UPDATE `doc_vault`
						SET `checkout` = '$checkout_hash' , `checkout_time` = ".time()."
						WHERE `doc_hash` = '$doc_hash'");
		$result = $db->query("SELECT *
							  FROM `doc_vault`
							  WHERE `doc_hash` = '$doc_hash'");
		if ($row = $db->fetch_assoc($result)) {
			header("Content-type: ".$row['content_type']);
			header("Content-length: ".$row['filesize']);
			header("Content-Disposition: attachment; filename=".$row['file_name']);
			header("Content-Description: ".$row['descr']);
			echo $row['data'];
			exit;
		} else
			die("Error opening file: error 1");
	} elseif ($dhash = $_GET['dhash']) {
		$result = $db->query("SELECT *
							  FROM `doc_vault`
							  WHERE `download_hash` = '$dhash'");
		if ($row = $db->fetch_assoc($result)) {
			header("Content-type: ".$row['content_type']);
			header("Content-length: ".$row['filesize']);
			header("Content-Disposition: attachment; filename=".$row['file_name']);
			header("Content-Description: ".$row['descr']);
			echo $row['data'];
			exit;
		} else
			die("Invalid document ID: error 2");
	}
//Fax image
} elseif ($_GET['fid']) {
	$fid = $_GET['fid'];
	require_once('include/nusoap/lib/nusoap.php');
	$client = new soapclient2("http://ws.interfax.net/dfs.asmx?wsdl", true);

	$params[] = array('Username'  		=> NUSOAP_USER,
					  'Password'   		=> NUSOAP_PASS,
					  'TransactionID'	=> $fid,
					  'MaxItems'		=> 1);
	$fax_result = $client->call("GetFaxImage", $params);
	header("Content-type: application/tif");
	header("Content-length: ".strlen($fax_result['Image']));
	header("Content-Disposition: attachment; filename=fax_image.tif");
	header("Content-Description: Fax Image");
	echo base64_decode($fax_result['Image']);
	exit;
//Excel
} elseif ($_GET['cvsh']) {
	$proposal_hash = $_GET['cvsh'];
	if ($proposal_hash) {
		$p = new proposals($_SESSION['id_hash']);
		$valid = $p->fetch_master_record($proposal_hash);
		if ($valid) {
			require_once('include/xls.class.php');
			$title = "Proposal ".$p->current_proposal['proposal_no'];
			$col_title = array("Vendor","Product","Catalog Code","Item No","Item Descr","Active","Special","Ship To","Tag 1","Tag 2","Tag3","Discount1","Discount2","Discount3","Discount4","Discount5","GP","Percent Off List","Qty","List","Cost","Sell","Ext Cost","Ext Sell");
			$row_title = array("vendor_name","product_name","catalog_code","item_no","item_descr","active","cr","ship_to","item_tag1","item_tag2","item_tag3","discount1","discount2","discount3","discount4","discount5","gp_margin","per_list","qty","list","cost","sell","ext_cost","ext_sell");
			$row_type = array("char","char","char","char","char","char","char","char","char","char","char","num","num","num","num","num","num","num","num","num","num","num","num","num");

			$xls = new xls();
			$xls->xlsBOF($title);
			$xls->xlsWriteLabel(0,$title.($p->current_proposal['customer'] ? " Customer: ".$p->current_proposal['customer'] : NULL)." Generated: ".date(TIMESTAMP_FORMAT));
			$xls->xlsMoveRow(2);
			for ($i = 0; $i < count($col_title); $i++)
				$xls->xlsWriteLabel($i,$col_title[$i]);

			$xls->xlsMoveRow(3);

			$punch = $_GET['punch'];
			$lines = new line_items($p->proposal_hash,$punch);
			$lines->fetch_line_items(0,$lines->total);

			for ($i = 0; $i < count($lines->line_info); $i++) {
				if ($lines->line_info[$i]['ship_to_hash']) {
					list($class,$id,$hash) = explode("|",$lines->line_info[$i]['ship_to_hash']);
					$obj = new $class($_SESSION['id_hash']);
					if ($hash) {
						$obj->fetch_location_record($hash);
						$lines->line_info[$i]['ship_to'] = $obj->current_location['location_name'];
					} else {
						$obj->fetch_master_record($id);
						$lines->line_info[$i]['ship_to'] = $obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"];
					}

					unset($obj);
				}
				for ($j = 0; $j < count($row_title); $j++) {
					if ($row_type[$j] == 'char') {
						$descr = htmlspecialchars_decode($lines->line_info[$i][$row_title[$j]],ENT_QUOTES);
						$descr = preg_replace('/\r/',"", $descr);
						$xls->xlsWriteLabel($j,$descr);
					} else {
						if ($row_title[$j] == 'gp_margin' && $lines->line_info[$i]['gp_type'] == 'L')
							$value = '';
						elseif ( $row_title[$j] == 'per_list' && $lines->line_info[$i]['gp_type'] == 'L' ) {

							$value = math::list_discount( array(
								'list'   =>  $lines->line_info[$i]['list'],
								'sell'   =>  $lines->line_info[$i]['sell']
							) );
						} else
							$value = $lines->line_info[$i][$row_title[$j]];

						$xls->xlsWriteNumber($j,$value);
					}
				}
				$xls->xlsMoveRow();
			}
			$xls->xlsStream();
		}
		exit;
	}
} elseif ($_GET['ehash']) {
	$po_hash = $_GET['ehash'];
	$proposal_hash = $_GET['p'];
	$index = $_GET['i'];

	$po = new purchase_order($proposal_hash);
	if ($valid = $po->fetch_po_record($proposal_hash,$po_hash)) {
		$vendors = new vendors($_SESSION['id_hash']);
		$vendors->fetch_master_record($po->current_po['vendor_hash']);
		if ($vendors->current_vendor['eorder_file']) {
			list($fname,$vendor) = explode(".",$vendors->current_vendor['eorder_file']);
			$fname = "eorder_".$vendor;
		}

		header("content-type: application/octetstream");
		$format = explode("|",$po->current_po['eorder_format']);
		if (count($format) > 1) {
			header("Content-Disposition: filename=".$fname."_".$po->current_po['po_no'].".".$format[$index]);
			$e_data = __unserialize(stripslashes($po->current_po['eorder_data']));
			echo $e_data[$index];
			exit;
		} else {
			header("Content-Disposition: filename=".$fname."_".$po->current_po['po_no'].".".$po->current_po['eorder_format']);
			echo $po->current_po['eorder_data'];
			exit;
		}

		exit;
	} else
		die("Invalid PO hash.");
} elseif ($_GET['elihash']) {
	$proposal_hash = $_GET['elihash'];
	$eorder_title = base64_decode($_GET['t']);
	$file_path = base64_decode($_GET['n']);
	if (!$file_path)
		die("Invalid file path.");
	$file_name = explode("/",$file_path);
	$eorder_file_name = $file_name[count($file_name)-1];
	$eorder_format = $_GET['f'];
	$file = file_get_contents($file_path);

	if ($eorder_format == 'xml') {
		header("Content-type: test/xml");
		header("Content-length: ".filesize($eorder_file_name));
		header("Content-Disposition: filename=".$eorder_file_name);
		header("Content-Description: XML File");
		echo $file;
	} else {
		header("Content-type: application/octetstream");
		header("Content-length: ".filesize($eorder_file_name));
		header("Content-Disposition: attachment; filename=".$eorder_file_name);
		header("Content-Description: $eorder_format File");
		echo $file;
	}
		exit;
} elseif ($_GET['ist']) {
	$reports = new reports($_SESSION['id_hash']);
	require_once('include/xls.class.php');

	$_POST['from'] = 'download';
	$reports->doit_income_statement($_GET['ist']);

	$report_name = (defined('MY_COMPANY_NAME') ?
						MY_COMPANY_NAME : NULL)." Income Statement ".($reports->report_date_start ? date("F j, Y",strtotime($reports->report_date_start))." - " : "Through ").date("F j, Y",strtotime($reports->report_date_end));

	//$col_title = array("Vendor","Product","Catalog Code","Item No","Item Descr","Active","Special","Ship To","Tag 1","Tag 2","Tag3","Discount1","Discount2","Discount3","Discount4","Discount5","GP","Percent Off List","Qty","List","Cost","Sell","Ext Cost","Ext Sell");
	//$row_title = array("vendor_name","product_name","catalog_code","item_no","item_descr","active","cr","ship_to","item_tag1","item_tag2","item_tag3","discount1","discount2","discount3","discount4","discount5","gp_margin","per_list","qty","list","cost","sell","ext_cost","ext_sell");
	//$row_type = array("char","char","char","char","char","char","char","char","char","char","char","num","num","num","num","num","num","num","num","num","num","num","num","num");

	$xls = new xls();
	$xls->xlsBOF($report_name);
	$xls->xlsWriteLabel(0,$report_name." Generated: ".date(TIMESTAMP_FORMAT));
	$xls->xlsMoveRow(2);

	$accounts =& $reports->accounts;
	$child =& $reports->child;
	if (is_array($accounts)) {
		$in =& $accounts['IN'];
		$total_in = 0;
		while (list($account_hash,$info) = each($in)) {
			if ($info['total'] != 0) {
				$total_in += $info['total'];
				$xls->xlsWriteLabel(0,($info['account_no'] ? $info['account_no']." - " : NULL).$info['account_name']);
				$xls->xlsWriteLabel(1,($info['total'] < 0 ? number_format(($info['total'] * -1),2) : number_format($info['total'],2)));

				if ($child[$account_hash]) {
					$child_total = $info['total'];
					while (list($child_hash,$child_info) = each($child[$account_hash])) {
						if ($child_info['total'] != 0) {
							$xls->xlsMoveRow();
							$xls->xlsWriteLabel(0,($info['child_info'] ? $info['child_info']." - " : NULL).$child_info['account_name']);
							$xls->xlsWriteLabel(1,($child_info['total'] < 0 ? number_format(($child_info['total'] * -1),2) : number_format($child_info['total'],2)));

							$total_in += $child_info['total'];
							$child_total += $child_info['total'];

						}

					}
					$xls->xlsWriteLabel(0,"Total ".$child_info['account_name']);
					$xls->xlsWriteLabel(1,number_format($child_total,2));

					//$tbl .= "
					//<tr>
						//<td style=\"background-color:#ffffff;padding-left:15px;padding-bottom:10px;\">Total ".$info['account_name']."</td>
						//<td style=\"background-color:#ffffff;padding-bottom:10px;".($info['total'] < 0 ? "color:#ff0000;" : NULL)."\" class=\"num_field\">".($child_total < 0 ?
							//"($".number_format(($child_total * -1),2).")" : "$".number_format($child_total,2))."
						//</td>
					//</tr>";
				}
			}
			$xls->xlsMoveRow();

			$xls->xlsWriteLabel(0,"Total ".$info['account_name']);
			$xls->xlsWriteLabel(1,number_format($total_in,2));
		}

	}
	$xls->xlsStream();
	exit;
	/*
	for ($i = 0; $i < count($lines->line_info); $i++) {
		if ($lines->line_info[$i]['ship_to_hash']) {
			list($class,$id,$hash) = explode("|",$lines->line_info[$i]['ship_to_hash']);
			$obj = new $class($_SESSION['id_hash']);
			if ($hash) {
				$obj->fetch_location_record($hash);
				$lines->line_info[$i]['ship_to'] = $obj->current_location['location_name'];
			} else {
				$obj->fetch_master_record($id);
				$lines->line_info[$i]['ship_to'] = $obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"];
			}

			unset($obj);
		}
		for ($j = 0; $j < count($row_title); $j++) {
			if ($row_type[$j] == 'char')
				$xls->xlsWriteLabel($j,$lines->line_info[$i][$row_title[$j]]);
			else {
				if ($row_title[$j] == 'gp_margin' && $lines->line_info[$i]['gp_type'] == 'L')
					$value = '';
				elseif ($row_title[$j] == 'per_list' && $lines->line_info[$i]['gp_type'] == 'L')
					$value = math::list_discount($lines->line_info[$i]['list'],$lines->line_info[$i]['sell']);
				else
					$value = $lines->line_info[$i][$row_title[$j]];

				$xls->xlsWriteNumber($j,$value);
			}
		}
		$xls->xlsMoveRow();
	}
	$xls->xlsStream();


					$tbl .= "
					<tr>
						<td style=\"border-top:1px solid #cccccc;background-color:#efefef;padding-left:15px;font-weight:bold;\">Total Income</td>
						<td style=\"border-top:1px solid #cccccc;background-color:#efefef;font-weight:bold;".($total_in < 0 ? "color:#ff0000;" : NULL)."\" class=\"num_field\">".($total_in < 0 ?
							"($".number_format(($total_in * -1),2).")" : "$".number_format($total_in,2))."
						</td>
					</tr>
					<tr>
						<td colspan=\"2\" style=\"border-top:1px solid #cccccc;background-color:#ffffff;font-weight:bold;width:250px;padding-top:15px;\">Expenses</td>
					</tr>";
				$ex =& $accounts['EX'];
				$total_ex = 0;
				while (list($account_hash,$info) = each($ex)) {
					if ($info['total'] != 0) {
						$total_ex += $info['total'];
						$tbl .= "
						<tr>
							<td style=\"background-color:#ffffff;padding-left:15px;\">".$info['account_name']."</td>
							<td style=\"background-color:#ffffff;".($info['total'] < 0 ? "color:#ff0000;" : NULL)."\" class=\"num_field\">".($info['total'] < 0 ?
								"($".number_format(($info['total'] * -1),2).")" : "$".number_format($info['total'],2))."
							</td>
						</tr>";
						if ($this->child[$account_hash]) {
							$child_total = $info['total'];
							while (list($child_hash,$child_info) = each($this->child[$account_hash])) {
								if ($child_info['total'] != 0) {
									$child_total = $info['total'];
									$total_ex += $child_info['total'];
									$tbl .= "
									<tr>
										<td style=\"background-color:#ffffff;padding-left:30px;\">".$child_info['account_name']."</td>
										<td style=\"background-color:#ffffff;".($child_info['total'] < 0 ? "color:#ff0000;" : NULL)."\" class=\"num_field\">".($child_info['total'] < 0 ?
											"($".number_format(($child_info['total'] * -1),2).")" : "$".number_format($child_info['total'],2))."
										</td>
									</tr>";
								}
							}
							$tbl .= "
							<tr>
								<td style=\"background-color:#ffffff;padding-left:15px;padding-bottom:10px;\">Total ".$info['account_name']."</td>
								<td style=\"background-color:#ffffff;padding-bottom:10px;".($info['total'] < 0 ? "color:#ff0000;" : NULL)."\" class=\"num_field\">".($child_total < 0 ?
									"($".number_format(($child_total * -1),2).")" : "$".number_format($child_total,2))."
								</td>
							</tr>";
						}
					}
				}
				$grand_total = $total_in - $total_ex;

					$tbl .= "
					<tr>
						<td style=\"border-top:1px solid #cccccc;border-bottom:1px solid #cccccc;background-color:#efefef;padding-left:15px;font-weight:bold;\">Total Expenses</td>
						<td style=\"border-top:1px solid #cccccc;border-bottom:1px solid #cccccc;background-color:#efefef;font-weight:bold;".($total_ex < 0 ? "color:#ff0000;" : NULL)."\" class=\"num_field\">".($total_ex < 0 ?
							"($".number_format(($total_ex * -1),2).")" : "$".number_format($total_ex,2))."
						</td>
					</tr>
					<tr>
						<td style=\"padding-top:25px;font-size:15px;font-weight:bold;\">Net Income</td>
						<td style=\"font-weight:bold;padding-top:25px;font-size:15px;".($grand_total < 0 ? "color:#ff0000;" : NULL)."\" class=\"num_field\">".($grand_total < 0 ?
							"($".number_format(($grand_total * -1),2).")" : "$".number_format($grand_total,2))."
						</td>
					</tr>
				</table>
			</td>
		</tr>";
	}
	*/


}






















die("Error: error 3");
?>