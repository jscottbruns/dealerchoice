<?php
/*EFILE Template for TeknionLLC*/
class eorder_teknion {

	public $title = "Teknion E-Order Template File";
	public $buffer;
	public $send_method = "EMAIL";
	public $user_error = array();

	function eorder_teknion($vendor_hash,$proposal_hash=NULL) {
		global $db;

		$this->db =& $db;
		$this->form = new form();
		$this->current_hash = $_SESSION['id_hash'];
		$this->proposal_hash = $proposal_hash;
		$this->vendor_hash = $vendor_hash;

		$this->vendors_obj = new vendors($this->current_hash);
		$this->vendors_obj->fetch_master_record($this->vendor_hash);

		$this->proposals = new proposals($this->current_hash);
		$this->proposals->fetch_master_record($this->proposal_hash);

		$this->customers = new customers($this->current_hash);

		$this->einput_mandatory = array('TYP' => 1,
										'DCNO' => 1,
										'SQN' => 1,
										'CBD' => 1,
										'CBP' => 1,
										'CBH' => 1,
										'AHC' => 1,
										'AHP' => 1,
										'TST' => 1
										);

		$this->import_data =& $this->proposals->current_proposal['import_data'];
		if (!$this->import_data)
			$this->import_data = array();

		if ($this->import_data['SETUP']['ORIGINATING_FORMAT'] == 'OFDAXML')
            $sv = '3.0';
        elseif ( preg_match('/^ProjectSpec Version (.*)$/', $this->import_data['SETUP']['ORIGINATING_VERSION'], $matches) )
            $sv = $matches[1];

		$sif = array("SF=", //Sif file name
					 "ST=STANDARD_SIF_ORDER;SIF-IO ".($this->import_data['SETUP']['ORIGINATING_SOFTWARE'] ? " v".$this->import_data['SETUP']['ORIGINATING_VERSION'] : NULL), //Specification software
					 "SV=".($sv ? $sv : ($this->import_data['SETUP']['ORIGINATING_VERSION'] ? $this->import_data['SETUP']['ORIGINATING_VERSION'] : ($this->import_data['SETUP']['ORIGINATING_FORMAT'] == 'SIF' ? "GIZA" : NULL))), //Software version
					 "HQT=1",//.$this->import_data['_c']['SETUP']['_a']['packaging'], //eaches or packages
					 "C1=", //sell charge amount
					 "CD=", //charge description
					 "CA=", //charge formula
					 "CB=", //buy charge
					 "CAB=", //charge formula
					 "C2=", //sell charge amount
					 "CD=", //charge description
					 "CA=", //charge formula
					 "CB=", //buy charge
					 "CAB=", //charge formula
					 "C3=", //sell charge amount
					 "CD=", //charge description
					 "CA=", //charge formula
					 "CB=", //buy charge
					 "CAB=", //charge formula
					 "CR1=", //credit amount
					 "CRD=", //credit description
					 "CRA=", //credit formula
					 "CR2=", //credit amount
					 "CRD=", //credit description
					 "CRA=", //credit formula
					 "CR3=", //credit amount
					 "CRD=", //credit description
					 "CRA=", //credit formula
					 "T1=", //tax amount
					 "TD=", //tax description
					 "TA=", //tax applied to which fields ???
					 "T2=", //tax amount
					 "TD=", //tax description
					 "TA=", //tax applied to which fields ???
					 "T3=", //tax amount
					 "TD=", //tax description
					 "TA=", //tax applied to which fields ???
					 "END=FACTORS", //Data one specific
					 "OE=TeknionOrderEntry" //Order entry module, dependant on which spec application was used
					 );

		$this->buffer['header'] = $sif;
		return;
	}

	function validate_user_input($data) {
		$flag = true;

		while (list($field,$val) = each($data)) {
			if ($this->einput_mandatory[$field] && !$val) {
				$this->user_error[$field] = 1;
				$flag = false;
			}
		}

		return $flag;
	}

	function user_input($hash_index,$proposal_obj,$item_array=NULL) {
		$this->lines = new line_items($this->proposals->proposal_hash);
		for ($i = 0; $i < count($item_array); $i++) {
			$a = $this->lines->fetch_line_item_record($item_array[$i]['item_hash']);
			$discount_used[] = $this->lines->current_line['discount_hash'];
		}

		$discount_used = array_unique($discount_used);
		if (count($discount_used) > 0) {
			$this->discounting = new discounting($this->vendor_hash,'v');
			if (count($discount_used) > 1) {
				for ($i = 0; $i < count($discount_used); $i++) {
					if ($valid = $this->discounting->fetch_discount_record($discount_used[$i]))
						if ($this->discounting->current_discount['discount_type'] == 'C') {
							$discount_id = $this->discounting->current_discount['discount_id'];
							break;
						} elseif ($this->discounting->current_discount['discount_type'] == 'S' && $this->discounting->current_discount['discount_default'] == 1)
							$discount_id = $this->discounting->current_discount['discount_id'];
						elseif ($this->discounting->current_discount['discount_type'] == 'S' && !$args['discount_id'])
							$discount_id = $this->discounting->current_discount['discount_id'];

				}
			} elseif ($discount_used[0] && $valid = $this->discounting->fetch_discount_record($discount_used[0]))
				$discount_id = $this->discounting->current_discount['discount_id'];

		}
		// Find out if it is direct
		$direct = ($this->proposals->current_proposal['order_type'] == 'D' ? 1 : 0);

		/*
		$e_fields[] = array("type"		=>	"select",
						    "name"		=>	"einput_".$hash_index."|TYP",
							"legend"	=>	"Order Type: *",
						    "value"		=>	"",
							"id"		=>	"TYP",
						    "a1"		=>	array("Regular","Run Through","Moch Up","Quick Ship","Internal R/T"),
						    "a2"		=>	array("Regular","Run Through","Moch Up","Quick Ship","Internal R/T"));
		$e_fields[] = array("type"		=>	"text_box",
						    "name"		=>	"einput_".$hash_index."|CNO",
							"legend"	=>	"Acct No: *",
							"id"		=>	"CNO",
						    "value"		=>	$this->vendors_obj->current_vendor['account_no']);
		$e_fields[] = array("type"		=>	"text_box",
						    "name"		=>	"einput_".$hash_index."|SQN",
							"legend"	=>	"SQ No: *",
							"id"		=>	"SQN",
						    "value"		=>	$discount_id);
		$e_fields[] = array("type"		=>	"text_box",
						    "name"		=>	"einput_".$hash_index."|CBD",
							"legend"	=>	"CBD Name: *",
							"id"		=>	"CBD",
						    "value"		=>	($proposal_obj->current_proposal['ship_to_contact_name'] ? $proposal_obj->current_proposal['ship_to_contact_name'] : NULL));
		$e_fields[] = array("type"		=>	"text_box",
						    "name"		=>	"einput_".$hash_index."|CBP",
							"legend"	=>	"CBD Phone: *",
							"id"		=>	"CBP",
						    "value"		=>	($proposal_obj->current_proposal['ship_to_contact_phone'] ? $proposal_obj->current_proposal['ship_to_contact_phone'] : NULL));
		$e_fields[] = array("type"		=>	"text_box",
						    "name"		=>	"einput_".$hash_index."|CBH",
							"legend"	=>	"CBD Hours: *",
							"id"		=>	"CBH",
						    "value"		=>	"24");
		$e_fields[] = array("type"		=>	"text_box",
						    "name"		=>	"einput_".$hash_index."|AHC",
							"legend"	=>	"After Hours<br />Contact: *",
							"id"		=>	"AHC",
						    "value"		=>	($proposal_obj->current_proposal['ship_to_contact_name'] ? $proposal_obj->current_proposal['ship_to_contact_name'] : NULL));
		$e_fields[] = array("type"		=>	"text_box",
						    "name"		=>	"einput_".$hash_index."|AHP",
							"legend"	=>	"Phone: *",
							"id"		=>	"AHP",
						    "value"		=>	($proposal_obj->current_proposal['ship_to_contact_name'] ? $proposal_obj->current_proposal['ship_to_contact_name'] : NULL));

		return $e_fields;
		*/
		$tbl .= "
		<table>
			<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_TYP\">Order Type: *</td>
				<td style=\"padding-left:5px;\">".$this->form->select("einput_".$hash_index."[TYP]",array("Regular","Run Through","Moch Up","Quick Ship","Internal R/T"),"",array("Regular","Run Through","Moch Up","Quick Ship","Internal R/T"),"blank=1","style=width:100px;")."</td>
			</tr>
			<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_DCNO\">Acct No: *</td>
				<td style=\"padding-left:5px;\">".$this->form->text_box("name=einput_".$hash_index."[DCNO]","value=".$this->vendors_obj->current_vendor['account_no'],"size=20")."</td>
			</tr>".($direct ? "
			<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_CCNO\">Direct Bill To No: </td>
				<td style=\"padding-left:5px;\">".$this->form->text_box("name=einput_".$hash_index."[CCNO]","value=","size=20")."</td>
			</tr>
			" : NULL)
			."<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_SQN\">SQ No: *</td>
				<td style=\"padding-left:5px;\">".$this->form->text_box("name=einput_".$hash_index."[SQN]","value=".$discount_id,"size=20")."</td>
			</tr>
			<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_CBD\">CBD Name: *</td>
				<td style=\"padding-left:5px;\">".$this->form->text_box("name=einput_".$hash_index."[CBD]","value=".($proposal_obj->current_proposal['ship_to_contact_name'] ? $proposal_obj->current_proposal['ship_to_contact_name'] : NULL),"size=20")."</td>
			</tr>
			<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_CBP\">CBD Phone: *</td>
				<td style=\"padding-left:5px;\">".$this->form->text_box("name=einput_".$hash_index."[CBP]","value=".($proposal_obj->current_proposal['ship_to_contact_phone'] ? $proposal_obj->current_proposal['ship_to_contact_phone'] : NULL),"size=20")."</td>
			</tr>
			<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_CBH\">CBD Hours: *</td>
				<td style=\"padding-left:5px;\">".$this->form->text_box("name=einput_".$hash_index."[CBH]","value=24","size=20")."</td>
			</tr>
			<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_AHC\">After Hours<br />Contact: *</td>
				<td style=\"padding-left:5px;\">".$this->form->text_box("name=einput_".$hash_index."[AHC]","value=".($proposal_obj->current_proposal['ship_to_contact_name'] ? $proposal_obj->current_proposal['ship_to_contact_name'] : NULL),"size=20")."</td>
			</tr>
			<tr>
				<td style=\"text-align:right;font-size:9pt;\" id=\"err_".$hash_index."_AHP\">Phone: *</td>
				<td style=\"padding-left:5px;\">".$this->form->text_box("name=einput_".$hash_index."[AHP]","value=".($proposal_obj->current_proposal['ship_to_contact_phone'] ? $proposal_obj->current_proposal['ship_to_contact_phone'] : NULL),"size=20")."</td>
			</tr>
		</table>";

		return $tbl;
	}

	function build_header($args,&$item_array) {
		//Find out if it's a direct bill
		$this->lines = new line_items($this->proposals->proposal_hash);
		$this->lines->fetch_line_item_record($item_array[0]);
		if ($this->lines->current_line['direct_bill_vendor'] && $this->lines->current_line['direct_bill_vendor'] == $this->vendor_hash) {
			$direct = 1;
			$args['direct_bill'] = "Direct Bill";
			$this->customers->fetch_master_record($this->proposals->current_proposal['customer_hash']);
			$customer_name['name'] = $this->customers->current_customer['customer_name'];
			$customer_name['street'] = explode("\n", $this->customers->current_customer['street']);
			$customer_name['city'] = $this->customers->current_customer['city'];
			$customer_name['state'] = $this->customers->current_customer['state'];
			$customer_name['zip'] = $this->customers->current_customer['zip'];
			$customer_name['country'] = $this->customers->current_customer['country'];
		} else
			$args['direct_bill'] = "Dealer Bill";

		//Get the ship to
		list($class,$id,$hash) = explode("|",$this->lines->current_line['ship_to_hash']);
		if ($class) {
			$obj = new $class($this->current_hash);
			if ($hash) {
				if ($loc_valid = $obj->fetch_location_record($hash)) {

					$ship_to['name'] = $obj->current_location['location_name'];
					$ship_to['street'] = explode("\n", $obj->current_location['location_street']);
					$ship_to['city'] = $obj->current_location['location_city'];
					$ship_to['state'] = $obj->current_location['location_state'];
					$ship_to['zip'] = $obj->current_location['location_zip'];
					$ship_to['country'] = $obj->current_location['location_country'];
				}
			} else {
				$obj->fetch_master_record($id);
				$ship_to['name'] = $obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"];
				$ship_to['street'] = explode("\n", $obj->{"current_".strrev(substr(strrev($class),1))}["street"]);
				$ship_to['city'] = $obj->{"current_".strrev(substr(strrev($class),1))}["city"];
				$ship_to['state'] = $obj->{"current_".strrev(substr(strrev($class),1))}["state"];
				$ship_to['zip'] = $obj->{"current_".strrev(substr(strrev($class),1))}["zip"];
				$ship_to['country'] = $obj->{"current_".strrev(substr(strrev($class),1))}["country"];
				$ship_to['phone'] = $obj->{"current_".strrev(substr(strrev($class),1))}["phone"];
				$ship_to['fax'] = $obj->{"current_".strrev(substr(strrev($class),1))}["fax"];
			}
			unset($obj);
		}
		if (!$args['SQN']) {
			//Get the discount ID
			for ($i = 0; $i < count($item_array); $i++) {
				$this->lines->fetch_line_item_record($item_array[$i]);
				$discount_used[] = $this->lines->current_line['discount_hash'];
			}
			$discount_used = array_unique($discount_used);
			if (count($discount_used) > 0) {
				$this->discounting = new discounting($this->vendor_hash,'v');
				if (count($discount_used) > 1) {
					for ($i = 0; $i < count($discount_used); $i++) {
						if ($valid = $this->discounting->fetch_discount_record($discount_used[$i]))
							if ($this->discounting->current_discount['discount_type'] == 'C') {
								$args['discount_id'] = $this->discounting->current_discount['discount_id'];
								break;
							} elseif ($this->discounting->current_discount['discount_type'] == 'S' && $this->discounting->current_discount['discount_default'] == 1)
								$args['discount_id'] = $this->discounting->current_discount['discount_id'];
							elseif ($this->discounting->current_discount['discount_type'] == 'S' && !$args['discount_id'])
								$args['discount_id'] = $this->discounting->current_discount['discount_id'];

					}
				} elseif ($discount_used[0] && $valid = $this->discounting->fetch_discount_record($discount_used[0]))
					$args['discount_id'] = $this->discounting->current_discount['discount_id'];

			}
		} else
			$args['discount_id'] = $args['SQN'];

		$my_name = explode(" ",$_SESSION['my_name']);
		$company_addr = explode("\n", MY_COMPANY_ADDRESS);

		$sif = array("TYP=".$args['TYP'], //Order type, required user input in PO window **
					 "CON=".$args['po_no'], //PO number
					 "POA=".$args['total_list'], //Total amount of PO list
					 "PNT=".$args['total_cost'], // Total amount of PO Net
					 "DLT=".(defined('MY_COMPANY_PHONE') ? MY_COMPANY_PHONE : NULL), //Dealer Telephone
					 "DLN=".(defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL), //Company name
					 "DEM=".($_SESSION['my_email'] ? $_SESSION['my_email'] : NULL), //Dealer Email
					 //"VIA=Bestway", //Ship via ???
					 //"STE=", //Shipping terms ???
					 "SBG=".$args['direct_bill'], //Direct or dealer bill
					 "SQN=".($args['discount_id'] ? $args['discount_id'] : $args['SQN']), //Discount ID
					 "DAT=".($args['po_date'] ? date("m/d/Y",strtotime($args['po_date'])) : NULL), //Order date
					 "DAR=".($args['rq_ship_date'] ? date("m/d/Y",strtotime($args['rq_ship_date'])) : ($args['rq_arrive_date'] ? date("m/d/Y",strtotime($args['rq_arrive_date'])) : NULL)), //Order requested date
					 "DAS=".($args['rq_ship_date'] ? "Ship" : "Arrival"), // Order requested date, arrival or ship
					 "CBD=".$args['CBD'], //CBD ???
					 "CBH=".$args['CBH'], //Hours to CBD ???
					 "CBP=".$args['CBP'], //Number to CBD ???
					 "AHC=".$args['AHC'], //After business hours contact ???
					 "AHP=".$args['AHP'], //After business hours phone ???
					 "REM=".$args['po_comment'], //PO Comments
					 //"PIN=", //PIN number for teknion sales rep ???
					 //"FNR=", //Field for halcon
					 //"DSG=", //Field for halcon
					 //"ACR=", //Field for halcon
					 //"EDL=", //Field for halcon
					 "DLC=".$_SESSION['my_name'],
					 "CTB=Bill_To", //Begin bill to info
					 (!$direct ? "FNM=" . stripslashes( trim( $my_name[0] ) ) : NULL), //First name
					 (!$direct ? "LNM=".trim( stripslashes( $my_name[1] ) ) . ( $my_name[2] ? trim( stripslashes(" {$my_name[2]}") ) : NULL ) : NULL ), //Last name
					 "CMP=" . stripslashes( ( $direct ? $customer_name['name'] : ( defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL ) ) ), //Company name
					 "AD1=" . stripslashes( ( $direct ? $customer_name['street'][0] : $company_addr[0] ) ), //Address1
					 "AD2=".stripslashes( ( $direct ? $customer_name['street'][1] . ( $customer_name['street'][2] ? "\n{$customer_name['street'][2]}" : NULL ) : $company_addr[1] ) ), //Address2
					 "AD3=".stripslashes( ( $direct ? $customer_name['city'] : $company_addr[2] ) ), //Address3
					 "AD4=".stripslashes( ( $direct ? $customer_name['state'] : $company_addr[3] ) ), //Address4
					 "CNT=".($direct ? $customer_name['country'] : (defined('MY_COMPANY_COUNTRY') ? MY_COMPANY_COUNTRY : NULL)), //Country
					 "ZIP=".($direct ? $customer_name['zip'] : (defined('MY_COMPANY_ZIP') ? MY_COMPANY_ZIP : NULL)), //Zip
					 (!$direct ? "PHO=".(defined('MY_COMPANY_PHONE') ? MY_COMPANY_PHONE : NULL) : NULL), //Phone
					 (!$direct ? "FAX=".(defined('MY_COMPANY_FAX') ? MY_COMPANY_FAX : NULL) : NULL), //Fax
					 (!$direct ? "EML=".$_SESSION['my_email'] : NULL),
					 //"STC=",
					 //"PTR=",
					 //"CRD=",
					 //"CNM",
					 //"CTY=",
					 "CNO=".($direct ? $args['CCNO'] : $args['DCNO']),
					 "END=CTB", //End bill to info
					 "CTB=Ship_To", //Begin ship to info
					 //"FNM=",
					 //"LNM=",
					 "CMP=" . stripslashes( $ship_to['name'] ),
					 "AD1=" . stripslashes( $ship_to['street'][0] ),
					 "AD2=" . stripslashes( $ship_to['street'][1] . ( $ship_to['street'][2] ? "\n{$ship_to['street'][2]}" : NULL ) ),
					 "AD3=" . stripslashes( $ship_to['city'] ),
					 "AD4=" . stripslashes( $ship_to['state'] ),
					 "CNT=".$ship_to['country'],
					 "ZIP=".$ship_to['zip'],
					 "PHO=".$ship_to['phone'],
					 "FAX=".$ship_to['fax'],
					 //"EML=",
					 //"STC=",
					 //"PTR=",
					 //"CRD=",
					 //"CNM=",
					 //"CTY=",
					 "CNO=",
					 "END=CTB",
					 "CTB=Dealer",
					 "CMP=" . stripslashes( $ship_to['name'] ),
					 "AD1=" . stripslashes( $ship_to['street'][0] ),
					 "AD2=" . stripslashes( $ship_to['street'][1] . ( $ship_to['street'][2] ? "\n{$ship_to['street'][2]}" : NULL ) ),
					 "AD3=".$ship_to['city'],
					 "AD4=".$ship_to['state'],
					 "CNT=".$ship_to['country'],
					 "ZIP=".$ship_to['zip'],
					 "CNO=".$args['DCNO'],
					 "END=CTB",//End dealer info (for direct bill)
					 "CTB=Dealer_Contact", //Begin dealer contact info
					 "FNM=".$my_name[0], //First name
					 "LNM=".$my_name[1].($my_name[2] ? " ".$my_name[2] : NULL), //Last name
					 "CMP=".(defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL), //Company name
					 "AD1=" . stripslashes( trim( $company_addr[0] ) ), //Address1
					 "AD2=" . stripslashes( trim( $company_addr[1] ) ), //Address2
					 "AD3=" . stripslashes( trim( $company_addr[2] ) ), //Address3
					 "AD4=" . stripslashes( trim( $company_addr[3] ) ), //Address4
					 "CNT=".(defined('MY_COMPANY_COUNTRY') ? MY_COMPANY_COUNTRY : NULL), //Country
					 "ZIP=".(defined('MY_COMPANY_ZIP') ? MY_COMPANY_ZIP : NULL), //Zip
					 "PHO=".(defined('MY_COMPANY_PHONE') ? MY_COMPANY_PHONE : NULL), //Phone
					 "FAX=".(defined('MY_COMPANY_FAX') ? MY_COMPANY_FAX : NULL), //Fax
					 "EML=".$_SESSION['my_email'],
					 //"STC=",
					 //"PTR=",
					 //"CRD=",
					 //"CNM",
					 //"CTY=",
					 "CNO=".$args['DCNO'],
					 "END=CTB", //End dealer contact info
					 "END=OE");
		$this->buffer['header2'] = $sif;

		if ($this->import_data['PANEL_TYPE'])
			$this->build_panel_info($item_array);
		else
			$this->buffer['panel_matrix'] = array();

		$this->build_line_items($item_array);
	}

	function build_line_items(&$item_array) {
		$sif = array();

		for ($i = 0; $i < count($item_array); $i++) {
			$this->lines->fetch_line_item_record($item_array[$i]);
			$item_detail =& __unserialize(stripslashes($this->lines->current_line['import_data']));

			for ($k = 0; $k < count($item_detail['NOTES']); $k++) {
				if ($item_detail['NOTES'][$k]['TYPE'] == 'sif')
					$sif_note = $item_detail['NOTES'][$k]['VALUE'];
				elseif ($item_detail['NOTES'][$k]['TYPE'] == 'report')
					$report_note = $item_detail['NOTES'][$k]['VALUE'];
			}

			#$pd = str_replace("&Acirc;", "",$this->lines->current_line['item_descr']);
			#$pd = str_replace("&deg;","",$pd);
			array_push( $sif,
    			"PN={$this->lines->current_line['item_no']}",
				"PD=" . stripslashes($pd),
				( $this->lines->current_line['special'] ? "XX={$this->lines->current_line['special']}" : NULL ),
				"CPL={$this->lines->current_line['import_complete']}",
				"QT={$this->lines->current_line['qty']}",
				"MC={$item_detail['CATALOG_CODE']}",
				"MV={$item_detail['CATALOG_VERSION']}",
							"ME=".($item_detail['CATALOG_EFFECTIVE_DATE'] ? date("m/d/Y",strtotime($item_detail['CATALOG_EFFECTIVE_DATE'])) : NULL),
				"VC={$item_detail['VENDOR_CODE']}",
				"VD=" . stripslashes($item_detail['VENDOR_DESCR']),
				"VR={$item_detail['ROUNDING']}",
				( $item_detail['PANEL_TYPE_ID'] ? "TYPE={$item_detail['PANEL_TYPE_ID']}" : NULL ),
							"CUR=USA",
				"PL={$this->lines->current_line['list']}",
				"PB={$this->lines->current_line['cost']}",
				"CC={$item_detail['PRODUCT_CODE']}",
				"CP=" . stripslashes($item_detail['PRODUCT_DESCR']),
				"CT={$item_detail['PRODUCT_SECTOR']}",
				($this->lines->current_line['item_tag1'] ? "U1=" . stripslashes($this->lines->current_line['item_tag1']) : NULL ),
				($this->lines->current_line['item_tag2'] ? "U2=" . stripslashes($this->lines->current_line['item_tag2']) : NULL ),
				($this->lines->current_line['item_tag3'] ? "U3=" . stripslashes($this->lines->current_line['item_tag3']) : NULL ),
				"HT={$item_detail['DIMENSIONS']['HEIGHT']}",
				"WD={$item_detail['DIMENSIONS']['WIDTH']}",
				"DP={$item_detail['DIMENSIONS']['DEPTH']}",
				"WT={$item_detail['DIMENSIONS']['WEIGHT']}",
				"VO={$item_detail['DIMENSIONS']['VOLUME']}",
				"NT={$item_detail['DIMENSIONS']['NUM_PKG']}",
				( $sif_note ? "NL=" . stripslashes($sif_note) : NULL ),
				( $report_note ? "NE=" . stripslashes($report_note) : NULL )
			);

			$item_finish = $item_detail['FINISH'];
			for ($j = 0; $j < count($item_finish); $j++) {

				array_push( $sif,
    				"OSL={$item_finish[$j]['LEVEL']}",
					"OG={$item_finish[$j]['GROUP_DESCR']}",
					"ON=" . stripslashes($item_finish[$j]['NAME']),
					"OD=" . stripslashes($item_finish[$j]['DESCR']),
					"OP={$item_finish[$j]['PRICE']}",
					( $item_finish[$j]['PANEL'] ? "TK={$item_finish[$j]['PANEL']}" : NULL ),
					"END=OSL"
				);

			}
		}

		$this->buffer['body'] = $sif;
	}

	function build_panel_info(&$item_array) {
		for ($i = 0; $i < count($item_array); $i++) {
			$this->lines->fetch_line_item_record($item_array[$i]);
			if ($this->lines->current_line['panel_id'])
				$panel_used[] = $this->lines->current_line['panel_id'];
		}
		if (!is_array($panel_used))
			return;
		else {
			$panel_used = array_unique($panel_used);
			$panel_used = array_values($panel_used);
		}
		$panel_info = $this->import_data['PANEL_TYPE'];
		$sif = array("GR=TYPES");

		for ($i = 0; $i < count($panel_info); $i++) {
			$detail =& $panel_info[$i];
			if (in_array($detail['NAME'],$panel_used)) {

				array_push($sif,
    				"TP=TYPE",
								"TPN=".$detail['NAME'], //Type name
								"TPD=".stripslashes($detail['DESCR']), //Panel descr
								"TMC=".$detail['CATALOG_CODE'], //Catalog code
								"TMV=".$detail['CATALOG_VERSION'], //Catalog version
								"TME=".($detail['CATALOG_EFFECTIVE_DATE'] ? date("m/d/Y",strtotime($detail['CATALOG_EFFECTIVE_DATE'])) : NULL), //Catalog effective date
								"TFR=".$detail['FRAME_ID'], //Frame ID
								"THT=".$detail['HEIGHT'],
								"TTH=".$detail['TOTAL_HEIGHT'] //Height of frame
								);

				$type_el =& $detail['ELEMENT'];
				for ($j = 0; $j < count($type_el); $j++) {

					if ( $type_el[$j]['REQUIRED'] || ( ! $type_el[$j]['REQUIRED'] && $type_el[$j]['PN'] && $type_el[$j]['DESCR'] ) ) {

						array_push( $sif,
    						"IT=ITEM",
							"ITP={$type_el[$j]['PN']}",
							"ITD=" . stripslashes($type_el[$j]['DESCR']),
							"IOR={$type_el[$j]['ORDER']}",
							"ISD={$type_el[$j]['SIDE']}",
							"IHT={$type_el[$j]['HEIGHT']}",
							"IFR={$type_el[$j]['REQUIRED']}",
							"IFS={$type_el[$j]['SINGLE']}",
							"IMG={$type_el[$j]['MAIN_GROUP']}",
							"ICG={$type_el[$j]['CURR_GROUP']}",
                            ( $type_el[$j]['SPECIAL'] ? "IXX={$type_el[$j]['SPECIAL']}" : NULL )
						);

						$finish_grp = $type_el[$j]['FINISH'];
						for ($k = 0; $k < count($finish_grp); $k++) {

							if ( $finish_grp[$k] ) {

								array_push( $sif,
    								"ISL={$finish_grp[$k]['LEVEL']}",
									"ION=" . stripslashes($finish_grp[$k]['NAME']),
									"IOD=" . stripslashes($finish_grp[$k]['DESCR']),
									"IOG=" . stripslashes($finish_grp[$k]['GROUP_DESCR']),
									"IOP={$finish_grp[$k]['PRICE']}",
									"END=ISL"
								);
						}
					}

						array_push( $sif,
    						"END=IT"
						);
					}
				}

				array_push( $sif,
    				"END=TP"
				);
			}
		}

		array_push( $sif,
    		"END=GR"
		);

		$this->buffer['panel_matrix'] = $sif;
		return;
	}

	function output($file_name=NULL,$id=NULL) {
		if (!is_array($this->buffer['panel_matrix']))
			$this->buffer['panel_matrix'] = array();
		if (!is_array($this->buffer['body']))
			$this->buffer['body'] = array();
		if (!is_array($this->buffer['header']))
			$this->buffer['header'] = array();
		if (!is_array($this->buffer['header2']))
			$this->buffer['header2'] = array();

		$buffer = @array_merge($this->buffer['header'],$this->buffer['header2'],$this->buffer['panel_matrix'],$this->buffer['body']);

		$count = count($buffer);
		for ($i = 0; $i < $count; $i++) {
			if (!$buffer[$i])
				unset($buffer[$i]);
		}
		$buffer = array_values($buffer);

		if ($file_name) {
			$fh = fopen(SITE_ROOT.'core/tmp/'.get_class($this).'_'.$id.'.sif','w+');
			fwrite($fh,implode("\r\n",$buffer));
			fclose($fh);

			return array(array(SITE_ROOT.'core/tmp/'.get_class($this).'_'.$id.'.sif'),array("sif"));
		} else
			return array(array(implode("\r\n",$buffer)),array("sif"));



	}
}
?>
