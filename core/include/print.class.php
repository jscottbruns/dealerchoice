<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class print_it {

	var $img;
	var $w;
	var $h;

	//How to find the exact middle of the page, might be useful??
	//($this->ez['pageWidth']-$this->ez['rightMargin'])/2+($this->ez['leftMargin'])/2

	function print_it($type=NULL,$template_hash=NULL,$print_pref_hash=NULL) {
		global $db;

		$this->db =& $db;
		$this->db->query("SET NAMES latin1");
		switch ($type) {
			case 'po':
			$class = "po_print";
			//if (!$this->print_pref['use_logo'])
				//$this->print_pref['use_logo'] =
			break;

			case 'customer_invoice':
			$class = "invoice_print";
			break;

			case 'delivery_ticket':
			$class = "delivery_ticket_print";
			break;

			case 'proforma_invoice';
			$class = 'proforma_invoice';
			break;

			default:
			if ($_GET['punch'])
				$class = "proposal_punch_print";
			else
				$class = "proposal_print";
			break;
		}

		// Fetch the user template for this type of document
		$this->template = new templates($_SESSION['id_hash']);
		$this->template->fetch_master_record(($class == "proposal_punch_print" ? "proposal_print" : $class),$template_hash);

		if ($print_pref_hash && $print_pref_hash != 'custom') {
			if ($print_pref_hash == $template_hash)
				$print_pref = $this->template->current_template['print_prefs'];
			else
				$print_pref = $this->template->fetch_print_prefs($class,$print_pref_hash);
		} else
			$print_pref = fetch_user_data($class);

		$this->print_pref = __unserialize(stripslashes($print_pref));

		if ($this->print_pref['use_logo'] && $this->print_pref['print_logo']) {
			$this->img = $this->print_pref['use_logo'];
			if ($this->img) {
				list($this->w,$this->h) = @getimagesize($this->img);
				if (($this->h / $this->w) > .85 && $this->h > 175) {
					$reduce_per = 175 / $this->h;
					$this->w = $this->w * $reduce_per;
					$this->h = 175;
				} elseif ($this->w > 225) {
					$reduce_per = 225 / $this->w;
					$this->h = $this->h * $reduce_per;
					$this->w = 225;
				} else {
					$this->h *= .75;
					$this->w *= .75;
				}
			}
			if (!$this->w)
				unset($this->img);
		}
	}

	function set_logo($logo,$print=NULL,$isFax=0) {
		$this->print_pref['use_logo'] = $logo;
		$this->print_pref['print_logo'] = $print;
		$this->img = $this->print_pref['print_logo'];

		// Grab the first logo in the system variable if the logo is not currently there
		if (!$this->img) {
			$img = fetch_sys_var('company_logo');
			$img = explode("|",$img);
			$this->img = $img[0];
			$this->print_pref['print_logo'] = 1;
			$this->print_pref['use_logo'] = $this->img;
		}

		// Use grayscale logo for faxes if it exists
		if ($isFax) {
			$basename = basename($this->img);
			$basename = explode(".",$basename);
			$logo_basename = $basename[count($basename)-2];
			$logo_image_type = $basename[count($basename)-1]; // Probably jpg but store just in case
			$gray_logo = SITE_ROOT.'core/images/logos/'.$logo_basename."_gray".".".$logo_image_type;
			if (file_exists($gray_logo)) {
				$this->img = $gray_logo;
			}
		}

		if ($this->img) {
			list($this->w,$this->h) = @getimagesize($this->img);
			if (($this->h / $this->w) > .85 && $this->h > 175) {
				$reduce_per = 175 / $this->h;
				$this->w = $this->w * $reduce_per;
				$this->h = 175;
			} elseif ($this->w > 225) {
				$reduce_per = 225 / $this->w;
				$this->h = $this->h * $reduce_per;
				$this->w = 225;
			} else {
				$this->h *= .75;
				$this->w *= .75;
			}
		}
		if (!$this->w)
			unset($this->img);
	}

	function purchase_order($proposal_hash,$po_hash,$mult=0) {
		$proposal = new proposals($_SESSION['id_hash']);
		$po = new purchase_order($proposal_hash);

		$valid2 = $proposal->fetch_master_record($proposal_hash);
		$valid = $po->fetch_po_record($proposal_hash,$po_hash);

		if (!$valid || !$valid2)
			die('Invalid po hash.');

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		if ($mult == 0)
			$this->pdf = new Cezpdf();
		//If we're printing multiple documents back to back
		elseif ($mult > 0) {
			$this->pdf->ezSetMargins(30,30,30,30);
			$this->pdf->stopObject($this->headfoot);
			$this->pdf->stopObject($this->bottomFoot);
			$this->pdf->ezNewPage();
			$this->pdf->ezStopPageNumbers(1,0,$mult - 1);
		}
		$title = "Purchase Order ".$po->current_po['po_no']." : ".$po->current_po['vendor_name'];
		$author = stripslashes($proposal->current_proposal['sales_rep']);
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Author',$author);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);

		//Get the vendor information
		$vendors = new vendors($_SESSION['id_hash']);
		$vendors->fetch_master_record($po->current_po['vendor_hash']);
		$vendor_info =
		stripslashes($vendors->current_vendor['vendor_name']) . "\n" .
		( $vendors->current_vendor['street'] ?
            stripslashes($vendors->current_vendor['street']) . "\n" : NULL
		) .
        stripslashes($vendors->current_vendor['city']) . ", {$vendors->current_vendor['state']} {$vendors->current_vendor['zip']}\n" .
		( $vendors->current_vendor['phone'] ?
            "Phone: {$vendors->current_vendor['phone']}\n": NULL
		) .
		( $vendors->current_vendor['fax'] ?
            "Fax: {$vendors->current_vendor['fax']}\n" : NULL
		);

		if ($po->current_po['ship_to_hash']) {
			list($class,$id,$loc_hash) = explode("|",$po->current_po['ship_to_hash']);
			$obj = new $class($_SESSION['id_hash']);
			if ($loc_hash) {
				$obj->fetch_location_record($loc_hash);
				$ship_to = stripslashes($obj->current_location['location_name']) . "\n" .
				( $obj->current_location['location_street'] ?
    				stripslashes($obj->current_location['location_street']) . "\n" : NULL
    			) .
				stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}\n";

			} else {
				$obj->fetch_master_record($id);
				$ship_to = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"])."\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
				) .
				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{"current_".strrev(substr(strrev($class),1))}['state']} {$obj->{"current_".strrev(substr(strrev($class),1))}['zip']}";
			}
			unset($obj);
		}

		$lines = new line_items($po->proposal_hash,$po->current_po['punch']);
		$pm = new pm_module($po->proposal_hash);
		$item_cols = array("qty" 			=> "Qty",
						   "item_no" 		=> "Item No.",
						   "item_descr" 	=> "Item Description",
						   "list"			=> "List",
						   "cost" 			=> "Net",
						   "ext_cost" 		=> "Total");

        $total_cost = 0;
		for ($i = 0; $i < ($po->current_po['total_items'] + $po->current_po['total_work_order_items']); $i++) {
			if ($valid = $lines->fetch_line_item_record($po->current_po['line_items'][$i])) {
				$item_descr = $lines->current_line['item_descr'];
				$item_no = $lines->current_line['item_no'];
				$qty = $lines->current_line['qty'];
				$list = $lines->current_line['list'];
				if ($po->current_po['currency'] && $po->current_po['exchange_rate'])
                    $list = currency_exchange($list,$po->current_po['exchange_rate']);

				$cost = $lines->current_line['cost'];
                if ($po->current_po['currency'] && $po->current_po['exchange_rate'])
                    $cost = currency_exchange($cost,$po->current_po['exchange_rate']);

				$ext_cost = $lines->current_line['ext_cost'];
                if ($po->current_po['currency'] && $po->current_po['exchange_rate'])
                    $ext_cost = currency_exchange($ext_cost,$po->current_po['exchange_rate']);

				$line_no = $lines->current_line['po_line_no'];
				$product_hash = $lines->current_line['product_hash'];
				$special = $lines->current_line['special'];
			} else {
				$pm->fetch_work_order_item($po->current_po['line_items'][$i]);
				$item_descr = $pm->current_item['item_descr'];
				$item_no = $pm->current_item['item_no'];
				$qty = $pm->current_item['time'].($pm->current_item['units'] != 'F' ? " ".$pm->units[$pm->current_item['units']].($pm->current_item['time'] > 1 ? "s" : NULL) : NULL);
				$cost = $pm->current_item['true_cost'];
                if ($po->current_po['currency'] && $po->current_po['exchange_rate'])
                    $cost = currency_exchange($cost,$po->current_po['exchange_rate']);

				$ext_cost = $pm->current_item['ext_true_cost'];
                if ($po->current_po['currency'] && $po->current_po['exchange_rate'])
                    $ext_cost = currency_exchange($ext_cost,$po->current_po['exchange_rate']);

				$line_no = $pm->current_line['po_line_no'];
				$product_hash = $pm->current_item['product_hash'];
			}
			$descr = $item_descr.($lines->current_line['item_tag1'] ?
									"\n\nTag1: ".$lines->current_line['item_tag1'] : NULL).($lines->current_line['item_tag2'] ?
										"\nTag2: ".$lines->current_line['item_tag2'] : NULL).($lines->current_line['item_tag3'] ?
											"\nTag3: ".$lines->current_line['item_tag3'] : NULL).($special ?
												"\n\nSpecial: ".$special : NULL);
			$import_data = unserialize(stripslashes($lines->current_line['import_data']));
			if (!$import_data)
				$import_data = unserialize($lines->current_line['import_data']);

			$finish =& $import_data['FINISH'];
			if (is_array($finish)) {
				$descr .= "\n\nItem Finishes & Options:\n";
				unset($item_panel);
				for ($j = 0; $j < count($finish); $j++) {
					if ($finish[$j]) {
						unset($item_panel);
						$special_chars = array("â„¢","™");
						if ($finish[$j]['PANEL'])
							$item_panel = $finish[$j]['PANEL'];
						else
							$descr .= str_replace($special_chars,"",$finish[$j]['GROUP_DESCR'])." : ".stripslashes(str_replace($special_chars,"",$finish[$j]['DESCR']))." ".($finish[$j]['NAME'] ? "(".stripslashes(str_replace($special_chars,"",$finish[$j]['NAME'])).")" : NULL)."\n";

						if ($item_panel)
							$descr .= "\nPanel Detail: ".$item_panel;
					}
				}
			}
			$disc = ($lines->current_line['discount1'] > 0 ?
							"  Discount 1: ".($lines->current_line['discount1'] * 100)."% \n" : NULL).($lines->current_line['discount2'] > 0 ?
								"  Discount 2: ".($lines->current_line['discount2'] * 100)."% \n" : NULL).($lines->current_line['discount3'] > 0 ?
									"  Discount 3: ".($lines->current_line['discount3'] * 100)."% \n" : NULL).($lines->current_line['discount4'] > 0 ?
										"  Discount 4: ".($lines->current_line['discount4'] * 100)."% \n" : NULL).($lines->current_line['discount5'] > 0 ?
											"  Discount 5: ".($lines->current_line['discount5'] * 100)."% \n" : NULL);

			// Removed sell discount for ticket # 274
			/*
			if ($lines->current_line['gp_type'] == 'L')
				$descr .= ($disc ?
							"\n" : "\n\n")."Discount Off List: ".math::list_discount($lines->current_line['list'],$lines->current_line['sell'])."%";
            */
			if ($disc)
				$descr .= "\n".$disc;

			$item_data[] = array("item_no" 		=> "Line: ".$line_no."\n\n".$item_no,
								 "item_descr"	=> $descr,
								 "qty"			=> $qty,
								 "list"			=> '$'.number_format($list,2),
								 "cost"			=> '$'.number_format($cost,2),
								 "ext_cost"		=> '$'.number_format($ext_cost,2));
			$total_cost = bcadd($total_cost,$ext_cost,2);

			if ($lines->current_line['discount_id']) {
				if (!@array_key_exists($lines->current_line['discount_id'],$discount))
					$discount[$lines->current_line['discount_id']] = $lines->current_line['discount_descr'];
			}
			$product_list[] = $product_hash;
		}
		$product_list = @array_unique($product_list);
		if (count($product_list) == 1) {
			reset($product_list);
			$vendors->fetch_product_record(current($product_list));
			$po_product = $vendors->current_product['product_name'];
		}

		for ($i = 0; $i < count($lines->proposal_comments); $i++) {
			if ($lines->proposal_comments[$i]['comment_action'] == '*' || ($lines->proposal_comments[$i]['comment_action'] == 1 && $lines->proposal_comments[$i]['vendor_hash'] == $po->current_po['vendor_hash']))
				$comment_data[] = array('comments' => htmlspecialchars($lines->proposal_comments[$i]['comments']));
		}

		$this->headfoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$y_start = 825 - $this->h;
		$y = $this->pdf->y;

		if ($this->print_pref['print_logo'] && $this->img) {
			$this->pdf->ezImage($this->img,5,$this->w,'','left');
			$this->pdf->y = $y;
		}

		$y_start -= 25;
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(2,'round');
		$x_start = 40 + $this->w;
		//PO title
		$text_width = $this->pdf->getTextWidth(20,'Purchase Order');
		$this->pdf->addText(565.28-$text_width-10,814,20,'Purchase Order');
		$width_vendor_name = 515.28-$text_width-$this->w;
		//Vendor
		$this->pdf->line($x_start,811.89,565.28,811.89);
		$this->pdf->addTextWrap(40 + $this->w,815.89,$width_vendor_name,10,"Vendor: ".$po->current_po['vendor_name']);
		//Sales Rep
		$font_height = $this->pdf->getFontHeight(10);
		$width_sales_rep_name = 515.28-$text_width-$this->w;
		$this->pdf->addTextWrap(40 + $this->w,811.89 - $font_height,$width_sales_rep_name,10,"Sales Rep: ".$author);
		//PO number
		$text_width = $this->pdf->getTextWidth(15,$po->current_po['po_no']);
		$font_height = $this->pdf->getFontHeight(15);
		$this->pdf->addText(565.28-$text_width-10,811.89 - $font_height,15,$po->current_po['po_no']);
		//Proposal no
		$text_width = $this->pdf->getTextWidth(10,"Proposal: ".$proposal->current_proposal['proposal_no']);
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText(565.28-$text_width-10,793 - $font_height,10,"Proposal: ".$proposal->current_proposal['proposal_no']);
		//PO date
		$text_width = $this->pdf->getTextWidth(10,"Date: ".date(DATE_FORMAT,strtotime($po->current_po['po_date'])));
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText(565.28-$text_width-10,780 - $font_height,10,"Date: ".date(DATE_FORMAT,strtotime($po->current_po['po_date'])));

		// Fixed trac #529, overlapping when no logo was present
		if (!$this->img) {
			$this->h = 50;
		}

		$this->pdf->line(30,30,565.28,30);
		//Company information
		$this->pdf->addText(30,20,8,MY_COMPANY_NAME);
		$this->pdf->addText(30,12,6,str_replace("\n"," ",MY_COMPANY_ADDRESS)." ".MY_COMPANY_PHONE." (phone) ".MY_COMPANY_FAX." (fax)");

		$this->pdf->ezSetDy(-35);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($this->headfoot,'all');
		$this->pdf->addObject($this->bottomFoot,'all');
		$this->px = $this->pdf->ezStartPageNumbers(550,22,6,'','',1);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		//Show the propose to and ship to information
		$data = array(array("vendor" => $vendor_info, "ship_to" => $ship_to));
		$cols = array("vendor" => "Vendor:", "ship_to" => "Shipping Location:");
		$xpos = 0;
		$this->pdf->ezSetY($this->pdf->ez['pageHeight']-$this->h-$this->pdf->ez['topMargin']);
		if ($this->pdf->y > 770)
			$this->pdf->ezSetY(770);

		$options = array('showLines' 	=> 0,
						 'shaded'		=> 0,
						 'xPos'			=> $xpos,
						 'colGap'		=> 15,
						 'fontSize'		=> 12,
						 'xOrientation'	=> 'right');

		$y = $this->pdf->ezTable($data,$cols,NULL,$options);

		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,$y - 10,565.28,$y - 10);

		$this->pdf->ezSetDy(-15);
		$y_before_comments = $this->pdf->y;
		if (is_array($comment_data)) {
			$cols = array("comments" => "PO Comments:");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> 30,
							 'showHeadings'		=> 0,
							 'fontSize'			=> 12,
							 'titleFontSize'	=> 14,
							 'xOrientation' 	=> 'right',
							 'width'			=> 300,
							 'maxWidth'			=> 300);
			$y_after_comments = $this->pdf->ezTable($comment_data,$cols,'Comments:',$options);
		}

		if ($po_product || $proposal->current_proposal['ship_to_contact_name'] || $proposal->current_proposal['ship_to_contact_phone'] || $proposal->current_proposal['ship_to_contact_fax'] || $po->current_po['po_comment'] || $vendors->current_vendor['account_no'] || ($po->current_po['rq_ship_date'] && $po->current_po['rq_ship_date'] != '0000-00-00') || ($po->current_po['rq_arrive_date'] && $po->current_po['rq_arrive_date'] != '0000-00-00') || $po->current_po['ship_to_contact_name']) {
			$this->pdf->ezSetY($y_before_comments);
			if ($po->current_po['ship_to_contact_name'] || $po->current_po['ship_to_contact_phone'] || $po->current_po['ship_to_contact_fax']) {
				$use_ship_to_contact = true;
			}
			// Add customer po for trac # 1015
			$direct = ($proposal->current_proposal['order_type'] == 'D' ? true : false);
			if ($direct)
				$xtra_txt[] = array('field' => "Customer: ".stripslashes($proposal->current_proposal['customer']));
			if ($direct && $proposal->current_proposal['customer_po'])
				$xtra_txt[] = array('field' => "Customer PO: ".$proposal->current_proposal['customer_po']);
			if ($po->current_po['po_comment'])
				$xtra_txt[] = array('field' =>	htmlspecialchars($po->current_po['po_comment']));
			if (($use_ship_to_contact && $po->current_po['ship_to_contact_name']))
				$xtra_txt[] = array('field' =>	"Shipping Contact: ".($use_ship_to_contact ? $po->current_po['ship_to_contact_name'] : htmlspecialchars($proposal->current_proposal['ship_to_contact_name'])));
			if (($use_ship_to_contact && $po->current_po['ship_to_contact_phone']))
				$xtra_txt[] = array('field' =>	"Contact Phone: ".($use_ship_to_contact ? $po->current_po['ship_to_contact_phone'] : htmlspecialchars($proposal->current_proposal['ship_to_contact_phone'])));
			if (($use_ship_to_contact && $po->current_po['ship_to_contact_fax']))
				$xtra_txt[] = array('field' =>	"Contact Fax: ".($use_ship_to_contact ? $po->current_po['ship_to_contact_fax'] : htmlspecialchars($proposal->current_proposal['ship_to_contact_fax'])));
			if ($vendors->current_vendor['account_no'])
				$xtra_txt[] = array('field' =>	"Account No: ".htmlspecialchars($vendors->current_vendor['account_no']));
			if ($po_product)
				$xtra_txt[] = array('field' =>	"Product: ".htmlspecialchars($po_product));
			if ($po->current_po['rq_ship_date'] && $po->current_po['rq_ship_date'] != '0000-00-00')
				$xtra_txt[] = array('field' =>	"Please ship by: ".date(DATE_FORMAT,strtotime($po->current_po['rq_ship_date'])));
			if ($po->current_po['rq_arrive_date'] && $po->current_po['rq_arrive_date'] != '0000-00-00')
				$xtra_txt[] = array('field' =>	"Please ship to arrive by: ".date(DATE_FORMAT,strtotime($po->current_po['rq_arrive_date'])));

			$options = array('showLines' 		=> 1,
							 'lineCol'			=>	array(.8,.8,.8),
							 'shaded'			=>  0,
							 'shadeCol'			=>  array(.94,.94,.94),
							 'shadeCol2'		=>  array(.94,.94,.94),
							 'xPos'				=> 565.28,
							 'showHeadings'		=> 0,
							 'fontSize'			=> 10,
							 'titleFontSize'	=> 10,
							 'xOrientation' 	=> 'left',
							 'width'			=> 225.28,
							 'maxWidth'			=> 225.28);
			$y = $this->pdf->ezTable($xtra_txt,'','',$options);
			if ($y_after_comments && $y > $y_after_comments)
				$this->pdf->ezSetY($y_after_comments);
		}
		if ($this->h < 50)
            $this->h = 50;

		$this->pdf->ezSetMargins(30 + $this->h,30,30,30);

		$this->pdf->ezSetDy(-5);

		//Show the line items
		$item_options = array("width" 		=> 535.28,
							  "maxWidth"	=> 535.28,
							  'rowGap'		=>	4,
							  'rowGap'		=>	5,
							  'showLines'	=>  2,
							  'lineCol'		=>	array(.8,.8,.8),
							  'shaded'		=>  0,
							  'shadeCol'	=>  array(.94,.94,.94),
							  "cols"		=> array("item_no" 		=>	array('justification'	=>	'left',
																			  'width'			=>	100),
													 "item_descr"	=>  array('justification'	=>	'left',
																			  'width'			=>	220),
													 "qty"			=>  array('justification'	=>	'right'),
													 "list"			=>  array('justification'	=>	'right'),
													 "cost"			=>  array('justification'	=>	'right'),
													 "ext_cost"		=>  array('justification'	=>	'right'),
											)
							  );
		$totals_options = array('xPos'					=>	555.28,
								'width'					=>	350,
								'xOrientation'			=>	'left',
								'showLines'				=>  0,
								'lineCol'				=>  array(.8,.8,.8),
								'showHeadings'			=>  0,
								'shaded'				=>	0,
								'fontSize'				=>	12,
								'rowGap'				=>	4,
								'colGap'				=>  0,
								'cols'					=>	array('field'	=>	array('justification'	=>	'left',
																					  'width'			=>	275),
																  'val'		=>	array('justification'	=>	'right',
																  					  'width'			=>	175)
																  )
								);

		$this->pdf->ezSetDy(-5);

		if ($proposal->current_proposal['shipping_notes']) {
			$cols = array("notes" => "Shipping Notes");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> 30,
							 'showHeadings'		=> 0,
							 'fontSize'			=> 10,
							 'titleFontSize'	=> 12,
							 'xOrientation' 	=> 'right',
							 'width'			=> 535.28,
							 'maxWidth'			=> 535.28);
			$shipping_notes_data = array(array("notes" => $proposal->current_proposal['shipping_notes']));
			$y_after_shipping_notes = $this->pdf->ezTable($shipping_notes_data,$cols,'Shipping Notes:',$options);
			$this->pdf->ezSetDy(-10);
		}

		if (is_array($discount)) {
			while (list($id,$descr) = each($discount))
				$disc_txt[] = $id." (".$descr.")";

			$this->pdf->ezText("Discounting Used: ".implode("; ",$disc_txt));
		}
		$this->pdf->ezSetDy(-5);
		$y_tmp = $this->pdf->y;

		$y = $this->pdf->ezTable($item_data,$item_cols,NULL,$item_options);

        if ($po->current_po['currency'] && $po->current_po['exchange_rate'])
            $this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->getTextWidth(10,"Currency".$po->current_po['currency']." ".rtrim(trim($po->current_po['exchange_rate'], '0'), '.')."%") - 10,($y_tmp + 2),10,"Currency: ".$po->current_po['currency']." ".rtrim(trim($po->current_po['exchange_rate'], '0'), '.')."%");

		$this->pdf->ezSetDy(-15);

		$totals_data = array(array('field'  =>	'Total Order Amount'.$this->pdf->fill_void('.',(275 - $this->pdf->getTextWidth(12,'Total Order Amount'))),
								   'val'	=>	$this->pdf->fill_void('.',(175 - $this->pdf->getTextWidth(12,'$'.number_format($total_cost,2)))).'$'.number_format($total_cost,2)));
		$y = $this->pdf->ezTable($totals_data,NULL,NULL,$totals_options);

		$dep = $po->fetch_po_deposit($po->po_hash);
		if ($dep) {
			$payable = new payables($_SESSION['id_hash']);
			for ($i = 0; $i < count($dep); $i++) {
				$payable->fetch_invoice_record($dep[$i]);
				$total += $payable->current_invoice['amount'];
				$date = $payable->current_invoice['invoice_date'];
			}
			if ($total)
				$this->pdf->ezText("A deposit totaling $".number_format($total,2)." was submitted for payment on ".date(DATE_FORMAT,strtotime($date)),10,array('justification' => 'right','right' => 10));
		}
	}
/**
 * Print delivery tickets to pdf.  Updated to take print options for trac ticket #337.
 *
 * @param string $proposal_hash
 * @param string $po_hash
 * @param num $mult
 * @param string $multi
 */
	function delivery_ticket($proposal_hash,$po_hash,$mult=0,$multi=NULL) {

		$proposal = new proposals($_SESSION['id_hash']);
		$po = new purchase_order($proposal_hash);

		$valid2 = $proposal->fetch_master_record($proposal_hash);
		$valid = $po->fetch_po_record($proposal_hash,$po_hash);

		if (!$valid || !$valid2)
			die('Invalid po hash.');

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		if ($mult == 0)
			$this->pdf = new Cezpdf();
		//If we're printing multiple documents back to back
		elseif ($mult > 0) {
			$this->pdf->ezSetMargins(30,30,30,30);
			$this->pdf->stopObject($this->headfoot);
			$this->pdf->stopObject($this->bottomFoot);
			$this->pdf->ezNewPage();
			$this->pdf->ezStopPageNumbers(1,0,$mult - 1);
		}

		$title = $this->print_pref['title'];
		$author = stripslashes($proposal->current_proposal['sales_rep']);
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Author',$author);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);

		//Get the customer information
		$customers = new customers($_SESSION['id_hash']);

		if (ereg("\|",$proposal->current_proposal['propose_to_hash'])) {
			list($class,$id,$hash) = explode("|",$proposal->current_proposal['propose_to_hash']);
			$customers->fetch_master_record($id);
			if ($hash) {
				$customers->fetch_location_record($hash);
				$propose_to = stripslashes($customers->current_customer['customer_name']) . " : " . stripslashes($customers->current_location['location_name']) . "\n" .
				( $customers->current_location['location_street'] ?
                    stripslashes($customers->current_location['location_street']) . "\n" : NULL
                ) .
                stripslashes($customers->current_location['location_city']) . ", {$customers->current_location['location_state']} {$customers->current_location['location_zip']}";

			} else {
				$propose_to = stripslashes($customers->current_customer['customer_name']) . "\n" .
				( $customers->current_customer['street'] ?
				    stripslashes($customers->current_customer['street']) . "\n" : NULL
				) .
				stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";
			}

		} else {
			$customers->fetch_master_record($proposal->current_proposal['propose_to_hash']);
			$propose_to = stripslashes($customers->current_customer['customer_name']) . "\n" .
			( $customers->current_customer['street'] ?
                stripslashes($customers->current_customer['street']) . "\n" : NULL
            ) .
            stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";
		}

		//Get the vendor information
		$vendors = new vendors($_SESSION['id_hash']);
		$vendors->fetch_master_record($po->current_po['vendor_hash']);
		$vendor_info = stripslashes($vendors->current_vendor['vendor_name']) . "\n" .
		( $vendors->current_vendor['street'] ?
			stripslashes($vendors->current_vendor['street']) . "\n" : NULL
		) .
		stripslashes($vendors->current_vendor['city']) . ", {$vendors->current_vendor['state']} {$vendors->current_vendor['zip']}";

		// Get install location
		if ($proposal->current_proposal['install_addr_hash']) {
			list($class,$id,$loc_hash) = explode("|",$proposal->current_proposal['install_addr_hash']);
			$obj = new $class($_SESSION['id_hash']);
			if ($loc_hash) {
				$obj->fetch_location_record($loc_hash);
				$install_addr = stripslashes($obj->current_location['location_name']) . "\n" .
				( $obj->current_location['location_street'] ?
					stripslashes($obj->current_location['location_street']) . "\n" : NULL
				) .
				stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

			} else {
				$obj->fetch_master_record($id);
				$install_addr = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
    			) .
    			stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{"current_".strrev(substr(strrev($class),1))}['state']} {$obj->{"current_".strrev(substr(strrev($class),1))}['zip']}";
			}
			unset($obj);
		}

		// Get Shipping Location
		if ($po->current_po['ship_to_hash']) {
			list($class,$id,$ship_hash) = explode("|",$po->current_po['ship_to_hash']);
			if ($class) {
				$obj = new $class($this->current_hash);
				if ($ship_hash) {
					if ($loc_valid = $obj->fetch_location_record($ship_hash))
						$ship_to_addr = stripslashes($obj->current_location['location_name']) . "\n" .
						( $obj->current_location['location_street'] ?
    						stripslashes($obj->current_location['location_street']) . "\n" : NULL
    					) .
    					stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

				} else {
					$obj->fetch_master_record($id);
					$ship_to_addr = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
					( $obj->{"current_".strrev(substr(strrev($class),1))}["street"] ?
    					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}["street"]) . "\n" : NULL
    				) .
    				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}["city"]) . ", {$obj->{'current_' . strrev(substr(strrev($class),1))}['state']} {$obj->{'current_' . strrev(substr(strrev($class),1))}['zip']}";
				}
				unset($obj);
			}
		}

		$lines = new line_items($po->proposal_hash);
		$pm = new pm_module($po->proposal_hash);
		// Print columns according to print preferences, for Trac ticket #337
		if (in_array("product",$this->print_pref['item_print_fields']) || in_array("item_no",$this->print_pref['item_print_fields']) || in_array("ack_no",$this->print_pref['item_print_fields']) || in_array("ship_date",$this->print_pref['item_print_fields']))
			$item_cols["item_no"] =	"Product/Item No";
		if (in_array("item_descr",$this->print_pref['item_print_fields']))
			$item_cols["item_descr"] = "Item Description";
		if (in_array("item_tag",$this->print_pref['item_print_fields']))
			$item_cols["item_tag"] = "Item Tagging";
		if (in_array("qty",$this->print_pref['item_print_fields']))
			$item_cols["qty"] = "Qty";
		if (in_array("qty_rcvd",$this->print_pref['item_print_fields']))
			$item_cols["qty_rcvd"] = "Qty Rcvd";
        for ($i = 0; $i < $po->current_po['total_items'] + $po->current_po['total_work_order_items']; $i++) {
            if ($valid = $lines->fetch_line_item_record($po->current_po['line_items'][$i])) {
            	$item_descr = $lines->current_line['item_descr'];
                $lines->fetch_item_details($lines->current_line['item_hash']);
            	if (in_array('item_finish',$this->print_pref['item_print_fields'])) {
					$lines->fetch_line_item_record($lines->line_info[$i]['item_hash']);
					$import_data = unserialize(stripslashes($lines->current_line['import_data']));
					if (!$import_data)
						$import_data = unserialize($lines->current_line['import_data']);

					$finish =& $import_data['FINISH'];
					if (is_array($finish)) {
						$item_descr .= "\n\nItem Finishes & Options:\n";
						for ($j = 0; $j < count($finish); $j++) {
							unset($item_panel);
							$special_chars = array("â„¢","™");
							if ($finish[$j]['PANEL'])
								$item_panel = stripslashes($finish[$j]['PANEL']);
							else{
								$item_descr .= str_replace($special_chars,"",$finish[$j]['GROUP_DESCR'])." : ".stripslashes(str_replace($special_chars,"",$finish[$j]['DESCR']))." ".($finish[$j]['NAME'] ? "(".stripslashes(str_replace($special_chars,"",$finish[$j]['NAME'])).")" : NULL)."\n";
								$item_descr = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $item_descr);
							}
								
							if ($item_panel)
								$item_descr .= "\nPanel Detail: ".$item_panel;
						}
					}
				}
                $item_no =
                (in_array("line_no",$this->print_pref['item_print_fields']) ?
                	"Line: ".$lines->current_line['po_line_no']."\n" : NULL).
                (in_array("vendor",$this->print_pref['item_print_fields']) ?
                    $lines->current_line['item_vendor'] : NULL).
                (in_array("product",$this->print_pref['item_print_fields']) ?
                    (in_array("vendor",$this->print_pref['item_print_fields']) ? " - " : NULL).$lines->current_line['item_product'] : NULL).
                (in_array("item_no",$this->print_pref['item_print_fields']) && $lines->current_line['item_no'] ?
                    "\nItem No: ".(strlen($lines->current_line['item_no']) > 25 ? wordwrap($lines->current_line['item_no'],25,"\n",1) : $lines->current_line['item_no']) : NULL).
                (in_array("ack_no",$this->print_pref['item_print_fields']) && $lines->current_line['ack_no'] ?
                    "\nAck No: ".$lines->current_line['ack_no'] : NULL).
                (in_array("ship_date",$this->print_pref['item_print_fields']) && $lines->current_line['ship_date'] && $lines->current_line['ship_date'] != '0000-00-00' ?
                    "\nShip Date: ".date(DATE_FORMAT, strtotime($lines->current_line['ship_date'])) : NULL).
                (in_array("receive_date",$this->print_pref['item_print_fields']) && $lines->current_line['receive_date'] && $lines->current_line['receive_date'] != '0000-00-00' ?
                    "\nReceive Date: ".date(DATE_FORMAT, strtotime($lines->current_line['receive_date'])) : NULL);
                $qty = strrev(substr(strstr(strrev($lines->current_line['qty']),'.'),1)).(str_replace('0','',substr(strrchr($lines->current_line['qty'],'.'),1)) ?
                    str_replace('0','',strrchr($lines->current_line['qty'],'.')) : NULL);
            } else {
                $pm->fetch_work_order_item($po->current_po['line_items'][$i]);
                $item_descr = $pm->current_item['item_descr'];
                $item_no =
                (in_array("line_no",$this->print_pref['item_print_fields']) ?
                	"Line: ".$pm->current_item['po_line_no']."\n" : NULL).
                (in_array("product",$this->print_pref['item_print_fields']) && $pm->current_item['item_resource'] ?
                	$pm->current_item['item_resource']."\n" : "").
                (in_array("item_no",$this->print_pref['item_print_fields']) && $pm->current_item['item_no'] ?
                	$pm->current_item['item_no'] : "").
                (in_array('ship_date',$this->print_pref['item_print_fields'] && $pm->current_item['ship_date'])
                	? "\n".date(DATE_FORMAT, strtotime($pm->current_item['ship_date'])) : "").
                (in_array('receive_date',$this->print_pref['item_print_fields'] && $pm->current_item['receive_date'])
                	? "\n".date(DATE_FORMAT, strtotime($pm->current_item['receive_date'])) : "");
                $qty = $pm->current_item['time'].($pm->current_item['units'] != 'F' ? " ".$pm->units[$pm->current_item['units']].($pm->current_item['time'] > 1 ? "s" : NULL) : NULL);
            }
            $item_data[] = array("item_no"      => $item_no,
                                 "item_descr"   => $item_descr,
                                 "qty"          => $qty,
                                 "qty_rcvd"     => '',
                                 "item_tag"     => ($lines->current_line['item_tag1'] ?
                                                        $lines->current_line['item_tag1']."\n" : NULL).($lines->current_line['item_tag2'] ?
                                                            $lines->current_line['item_tag2']."\n" : NULL).($lines->current_line['item_tag3'] ?
                                                                $lines->current_line['item_tag3']."\n" : NULL));
        }
		if ($po->current_po['po_comment'])
			$comment_data[] = array('comments' => htmlspecialchars(stripslashes($po->current_po['po_comment'])));

		for ($i = 0; $i < count($lines->proposal_comments); $i++) {
			if ($lines->proposal_comments[$i]['comment_action'] == '*' || ($lines->proposal_comments[$i]['comment_action'] == 1 && $lines->proposal_comments[$i]['vendor_hash'] == $po->current_po['vendor_hash']))
				$comment_data[] = array('comments' => htmlspecialchars(stripslashes($lines->proposal_comments[$i]['comments'])));
		}

		//$this->pdf->stopObject($headfoot);
		$this->headfoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$y_start = 825 - $this->h;
		$y = $this->pdf->y;
		$this->pdf->ezImage($this->img,5,$this->w,'','left');
		$this->pdf->y = $y;
		$y_start -= 25;
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(2,'round');
		$x_start = 40 + $this->w;
		//PO title
		$text_width = $this->pdf->getTextWidth(20,$this->print_pref['title']);
		$this->pdf->addText(565.28-$text_width-10,814,20,$this->print_pref['title']);
		$width_vendor_name = 515.28-$text_width-$this->w;
		//Vendor
		$this->pdf->line($x_start,811.89,565.28,811.89);
		$this->pdf->addTextWrap(40 + $this->w,815.89,$width_vendor_name,10,"Vendor: ".$po->current_po['vendor_name']);
		//Sales Rep
		$font_height = $this->pdf->getFontHeight(10);
		$width_sales_rep_name = 515.28-$text_width-$this->w;
		$this->pdf->addTextWrap(40 + $this->w,811.89 - $font_height,$width_sales_rep_name,10,"Sales Rep: ".$author);
		//PO number
		$font_size = 11;
		$cur_height = 811.89; //Current height for Dealer PO number
		if (in_array("dealer_po",$this->print_pref['gen_print_fields'])) {
			$font_height = $this->pdf->getFontHeight($font_size);
			$text_width = $this->pdf->getTextWidth($font_size,"Purchase Order: ".$po->current_po['po_no']);
			$this->pdf->addText(565.28-$text_width-10,$cur_height - $font_height,$font_size,"Purchase Order: ".$po->current_po['po_no']);
			$cur_height -= 12.89;
		}

		//Customer PO number
		if ($proposal->current_proposal['customer_po']) {
			$cust_po_height = 0;
			if (in_array("customer_po",$this->print_pref['gen_print_fields'])) {
				$cust_po_height = $font_height = $this->pdf->getFontHeight($font_size);
				$text_width = $this->pdf->getTextWidth($font_size,"Customer PO: ".$proposal->current_proposal['customer_po']);
				$this->pdf->addText(565.28-$text_width-10,$cur_height - $font_height,$font_size,"Customer PO: ".$proposal->current_proposal['customer_po']);
				$cur_height -= $cust_po_height;
			}
		}
		//Proposal no
		if (in_array("proposal_no",$this->print_pref['gen_print_fields'])) {
			$text_width = $this->pdf->getTextWidth($font_size,"Proposal: ".$proposal->current_proposal['proposal_no']);
			$font_height = $this->pdf->getFontHeight($font_size);
			$this->pdf->addText(565.28-$text_width-10,$cur_height - $font_height,$font_size,"Proposal: ".$proposal->current_proposal['proposal_no']);
			$cur_height -= 13;
		}
		//PO date
		if (in_array("po_date",$this->print_pref['gen_print_fields'])) {
			$text_width = $this->pdf->getTextWidth($font_size,"PO Date: ".date(DATE_FORMAT,strtotime($po->current_po['po_date'])));
			$font_height = $this->pdf->getFontHeight($font_size);
			$this->pdf->addText(565.28-$text_width-10,$cur_height - $font_height,$font_size,"PO Date: ".date(DATE_FORMAT,strtotime($po->current_po['po_date'])));
		}

		$this->pdf->line(30,30,565.28,30);
		$this->pdf->addText(30,20,8,"Printed on ".date(DATE_FORMAT)." by ".$_SESSION['my_name']);
		$move_to_y = $this->pdf->y - $this->h;
		// Find out which height is lower and set y coordinate to that.
		$move_to_y = (($move_to_y > ($cur_height - 20)) ? $cur_height - 20 : $move_to_y);
		//$this->pdf->ezSetDy(-35);
		$this->pdf->ezSetY($move_to_y);

		if ($this->pdf->y > $cur_height - $font_height)
			$this->pdf->ezSetY($cur_height - $font_height - 15);

		$top_margin = 841.89 - $this->pdf->y;

		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($this->headfoot,'all');
		$this->pdf->addObject($this->bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		if ($proposal->current_proposal['customer_contact']) {
			$customers->fetch_contact_record($proposal->current_proposal['customer_contact']);
			$customer_contact = $customers->current_contact['contact_name']."\n".($customers->current_contact['contact_phone1'] ?
				$customers->current_contact['contact_phone1']." (Phone)\n" : NULL).($customers->current_contact['contact_phone2'] ?
					$customers->current_contact['contact_phone2']." (Phone 2)\n" : NULL).($customers->current_contact['contact_mobile'] ?
						$customers->current_contact['contact_mobile']." (Mobile)\n" : NULL).($customers->current_contact['contact_fax'] ?
							$customers->current_contact['contact_fax']." (Fax)\n" : NULL).($customers->current_contact['contact_email'] ?
								$customers->current_contact['contact_email'] : NULL)." ";
		}
		//Show the propose to and ship to information
		//in_array("item_no",$this->print_pref['item_print_fields'])
		$data = array();
		$num_addresses = 0;

		if (in_array("customer_addr",$this->print_pref['gen_print_fields']) && $propose_to) {
			$data[0]['customer'] = $propose_to;
			$cols['customer'] = "Customer:";
			$num_addresses++;
		}
		if (in_array("customer_contact",$this->print_pref['gen_print_fields']) && $customer_contact) {
			$data[0]['customer_contact'] = $customer_contact;
			$cols['customer_contact'] = "Customer Contact:";
			$num_addresses++;
		}
		if (in_array("install_addr",$this->print_pref['gen_print_fields']) && $install_addr) {
			$data[0]['install_addr'] = $install_addr;
			$cols['install_addr'] = "Installation Location:";
			$num_addresses++;
		}
		if (in_array("ship_to_addr",$this->print_pref['gen_print_fields']) && $ship_to_addr) {
			$data[0]['ship_to_addr'] = $ship_to_addr;
			$cols['ship_to_addr'] = ($this->print_pref['ship_to_title'] ? $this->print_pref['ship_to_title'].(ereg(":",$this->print_pref['ship_to_title']) ? "" : ":") : "Shipping Location:");
			$num_addresses++;
		}
		$xpos = 0;
		$this->pdf->ezSetY($move_to_y);

		// Add vendor location to delivery ticket for trac #679
		if (in_array("vendor_addr",$this->print_pref['gen_print_fields'])) {
			$this->pdf->ezText("Vendor Address:\n\n".$vendor_info,10);
			$this->pdf->ezSetDy(-10);
		}

		$options = array('showLines' 	=> 0,
						 'shaded'		=> 0,
						 'xPos'			=> $xpos,
						 'colGap'		=> ($num_addresses > 3 ? 5 : 15),
						 'width'		=> $this->pdf->ez['pageWidth'] - ($this->pdf->ez['rightMargin'] + $this->pdf->ez['leftMargin']),
						 'maxWidth'		=> $this->pdf->ez['pageWidth'] - ($this->pdf->ez['rightMargin'] + $this->pdf->ez['leftMargin']),
						 'xOrientation'	=> 'right');


		// Control the width of the 4 columns when all four will be shown.  For trac #819.
		$width = ($num_addresses > 3 ? 140 : 180);
		$options_cols = array('customer' 			=> array("width" => $width),
							  'customer_contact'	=> array("width" => $width),
							  'install_addr' 		=> array("width" => $width),
							  'ship_to_addr' 		=> array("width" => $width));
		if ($num_addresses > 2)
			$options['cols'] = $options_cols;


		$y = $this->pdf->ezTable($data,$cols,NULL,$options);
		unset ($options['cols']);

		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,$y - 10,565.28,$y - 10);

		$this->pdf->ezSetDy(-15);
		$y_before_comments = $this->pdf->y;
		$show_xtra_txt = ((in_array('bldg_poc',$this->print_pref['gen_print_fields']) && $proposal->current_proposal['bldg_poc']) || (in_array('bldg_phone',$this->print_pref['gen_print_fields']) && $proposal->current_proposal['bldg_phone']) || (in_array('bldg_fax',$this->print_pref['gen_print_fields']) && $proposal->current_proposal['bldg_fax']) || $this->print_pref['dealer_contact_name'] || $this->print_pref['ticket_comments']);
		if (in_array("po_comments",$this->print_pref['gen_print_fields']) && $comment_data) {
			$cols = array("comments" => "PO Comments:");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> ($show_xtra_txt ? 300 : 45),
							 'showHeadings'		=> 0,
							 'fontSize'			=> 8,
							 'titleFontSize'	=> 10,
							 'xOrientation' 	=> 'right',
							 'maxWidth'			=> ($show_xtra_txt ? 250 : 565));
			$y_after_comments = $this->pdf->ezTable($comment_data,$cols,'Purchase Order Comments:',$options);
			$show_comments = true;
		}

		if ($show_xtra_txt) {
			$this->pdf->y = $y_before_comments;
			$options = array('showLines' 		=> 0,
							 'lineCol'			=>	array(.8,.8,.8),
							 'shaded'			=>  0,
							 'shadeCol'			=>  array(.94,.94,.94),
							 'shadeCol2'		=>  array(.94,.94,.94),
							 'showHeadings'		=> 0,
							 'fontSize'			=> 8,
							 'titleFontSize'	=> 10,
							 'xPos'				=> 45,
							 'xOrientation' 	=> 'right',
							 'maxWidth'			=> ($show_comments ? 250 : 565));
			if (in_array('bldg_poc',$this->print_pref['gen_print_fields']) && $proposal->current_proposal['bldg_poc'])
				$xtra_txt[] = array("field"	=>	"Bldg Mngmt POC: ".$proposal->current_proposal['bldg_poc']);
			if (in_array('bldg_phone',$this->print_pref['gen_print_fields']) && $proposal->current_proposal['bldg_phone'])
				$xtra_txt[] = array("field"	=>	"Bldg Mngmt Phone: ".$proposal->current_proposal['bldg_phone']);
			if (in_array('bldg_fax',$this->print_pref['gen_print_fields']) && $proposal->current_proposal['bldg_fax'])
				$xtra_txt[] = array("field"	=>	"Bldg Mngmt Fax: ".$proposal->current_proposal['bldg_fax']);
			if ($this->print_pref['dealer_contact_name'] && $this->print_pref['dealer_contact_name'])
				$xtra_txt[] = array("field" => "Dealer Contact: ".$this->print_pref['dealer_contact_name']);
			if ($this->print_pref['ticket_comments'] && $this->print_pref['ticket_comments'])
				$xtra_txt[] = array("field" => "Ticket Comments: ".$this->print_pref['ticket_comments']);

			$y = $this->pdf->ezTable($xtra_txt,'','',$options);
			if ($y_after_comments && $y > $y_after_comments)
				$this->pdf->ezSetY($y_after_comments);
		}
		$this->pdf->ezSetMargins($top_margin,30,30,30);
		$this->pdf->ezSetDy(-5);

		$cols = array();

		$cols['item_no']	= 	array('justification'	=>	'left',		'maxWidth'	=>	170);
		$cols['item_descr']	=	array('justification'	=>	'left',		'maxWidth'	=>	200);
		$cols['qty']		= 	array('justification'	=>	'right');
		$cols['qty_rcvd']	=	array('justification'	=>	'right');
		$cols['item_tag']	=	array('justification'	=>	'right');

		//Show the line items
		$item_options = array("width" 		=> 535.28,
							  "maxWidth"	=> 535.28,
							  'rowGap'		=>	4,
							  'showLines'	=>  2,
							  'lineCol'		=>	array(.8,.8,.8),
							  'shaded'		=>  0,
							  'shadeCol'	=>  array(.94,.94,.94),
							  "cols"		=> $cols
							  );
		$totals_options = array('xPos'					=>	555.28,
								'width'					=>	215.28,
								'xOrientation'			=>	'left',
								'showLines'				=>  0,
								'lineCol'				=>  array(.8,.8,.8),
								'showHeadings'			=>  0,
								'shaded'				=>	0,
								'fontSize'				=>	12,
								'rowGap'				=>	4,
								'colGap'				=>  0,
								'cols'					=>	array('field'	=>	array('justification'	=>	'left',
																					  'width'			=>	115),
																  'val'		=>	array('justification'	=>	'right',
																					  'width'			=>	100.28)
																  )
								);

		$this->pdf->ezSetDy(-5);
		$y = $this->pdf->ezTable($item_data,$item_cols,NULL,$item_options);

		// 5/7/08 Changed from 85 to 95 for trac ticket #309
		// 8/6/08 - Removed $this->pdf->ezSetDy(-15) as that was causing the blank page to occur in #309
		if ($y < 95)
			$this->pdf->newPage();

		$this->pdf->addText($this->pdf->ez['leftMargin'],72,12,"Received by: ");
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin']+$this->pdf->getTextWidth(12,"Received by: "),72,400,72);
		$this->pdf->addText(405,72,12,"Date: ");
		$this->pdf->line(405+$this->pdf->getTextWidth(12,"Date: "),72,525,72);
	}

	function po_summary($proposal_hash) {
		$proposal = new proposals($_SESSION['id_hash']);
		$po = new purchase_order($proposal_hash);

		$valid2 = $proposal->fetch_master_record($proposal_hash);

		if (!$valid2)
			die('Invalid po hash.');

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = "Purchase Order Summary : Proposal ".$proposal->current_proposal['proposal_no'];
		$author = $proposal->current_proposal['sales_rep'];
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Author',$author);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);

		$po->fetch_purchase_orders(0,$po->total);

		$headfoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(2,'round');
		$x_start = 40;
		//Vendor
		$this->pdf->line($x_start,811.89,565.28,811.89);
		//PO title
		$text_width = $this->pdf->getTextWidth(20,'Purchase Order Summary');
		$this->pdf->addText(565.28-$text_width-10,814,20,'Purchase Order Summary');
		//PO number
		$text_width = $this->pdf->getTextWidth(15,$po->current_po['po_no']);
		$font_height = $this->pdf->getFontHeight(15);
		//Proposal no
		$text_width = $this->pdf->getTextWidth(10,"Proposal: ".$proposal->current_proposal['proposal_no']);
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText(565.28-$text_width-10,810 - $font_height,10,"Proposal: ".$proposal->current_proposal['proposal_no']);
		//PO date
		$text_width = $this->pdf->getTextWidth(10,"Date: ".date(DATE_FORMAT));
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText(565.28-$text_width-10,797 - $font_height,10,"Date: ".date(DATE_FORMAT));

		$this->pdf->line(30,30,565.28,30);
		$this->pdf->ezSetDy(-35);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($headfoot,'all');
		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->ezSetMargins(30,30,30,30);

		$po->fetch_purchase_orders(0,$po->total);
		for ($i = 0; $i < $po->total; $i++) {
 			unset($po_data);
 			unset($note_data);
			if ($po->po_info[$i]['ship_to_hash']) {
				list($class,$id,$loc_hash) = explode("|",$po->po_info[$i]['ship_to_hash']);
				$obj = new $class($_SESSION['id_hash']);
				if ($loc_hash) {
					$obj->fetch_location_record($loc_hash);
					$ship_to = stripslashes($obj->current_location['location_name']) . "\n" .
					( $obj->current_location['location_street'] ?
						stripslashes($obj->current_location['location_street']) . "\n" : NULL
					) .
					stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";
				} else {
					$obj->fetch_master_record($id);
					$ship_to = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
					( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
						stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
					) .
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{"current_".strrev(substr(strrev($class),1))}['state']} {$obj->{"current_".strrev(substr(strrev($class),1))}['zip']}";
				}
				unset($obj);
			}
			// Added for Trac #275
			$ack_no = $po->fetch_ack_no($po->po_info[$i]['po_hash']);
			$ack_no = @implode(", ",$ack_no);
			$receive_dates = $po->fetch_receive_dates($po->po_info[$i]['po_hash']);
			$receive_dates = @implode(", ",$receive_dates);
			$ship_dates = $po->fetch_ship_dates($po->po_info[$i]['po_hash']);
			$ship_dates = @implode(", ",$ship_dates);

			$po_data[] = array("po_no" 			=> $po->po_info[$i]['po_no'].($po->po_info[$i]['creation_date'] ?
														" \n".date(DATE_FORMAT,$po->po_info[$i]['creation_date']) : NULL).
														($ack_no ? "\nAck No: ".$ack_no : NULL).
														($ship_dates ? "\nShip Date: ".$ship_dates : NULL).
														($receive_dates ? "\nReceive Date: ".$receive_dates : NULL),
							   "vendor"			=> $po->po_info[$i]['vendor_name'].($po->po_info[$i]['product'] ?
														" \n".$po->po_info[$i]['product'] : " \nVarious Products"),
							   "po_amount"		=> "$".number_format($po->po_info[$i]['order_amount'],2),
							   "ship_to"		=> $ship_to);
			
			$notes = $proposal->fetch_proposal_notes($po->po_info[$i]['proposal_hash'], 'o', $po->po_info[$i]['po_hash']);
				
			for ($t = 0; $t < count($notes); $t++){
				$note_data[] = array("notes" => $notes[$t]['full_name']." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $notes[$t]['timestamp']).")".stripslashes($notes[$t]['note']));
			}
			
			$item_options = array("xPos" 		=> 50,
					"xOrientation"	=> 'right',
					"width"	=> 525.28,
					'rowGap'		=>	4,
					'showLines'	=>  2,
					'lineCol'		=>	array(.8,.8,.8),
					'shaded'		=>  0
			);
			
			$item_cols = array("po_no" 		=> "PO No.",
					"vendor" 	=> "Vendor",
					"po_amount" 	=> "Order Amount",
					"ship_to" 	=> "Shipping Location");


            $this->pdf->ezSetSumDy(-15);

			$y = $this->pdf->ezTable($po_data,$item_cols,'',$item_options);
			$this->pdf->setStrokeColor(0,0,0);
			$this->pdf->setLineStyle(1);
			
			$item_options = array("xPos" 		=> 50,
					"xOrientation"	=> 'right',
					"width"	=> 525.28,
					'rowGap'		=>	4,
					'showLines'	=>  2,
					'lineCol'		=>	array(.8,.8,.8),
					'shaded'		=>  0
			);
			
			$item_cols = array("notes" 		=> "Proposal Notes");

            if($note_data){
                $this->pdf->ezSetSumDy(-25);
                $y = $this->pdf->ezTable($note_data,$item_cols,'',$item_options);
                $this->pdf->setStrokeColor(0,0,0);
                $this->pdf->setLineStyle(1);
            }
		}
	}

	function proposal($proposal_hash) {
		$p = new proposals();
		$valid = $p->fetch_master_record($proposal_hash);
		if (!$valid)
			die('Invalid proposal hash.');

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf('LETTER');
		// Find out if using a saved print_prefs
		$punch = $_GET['punch'];
		$custom_prefs_str = fetch_user_data($punch ? "proposal_punch_print" : "proposal_print");
		$custom_prefs = __unserialize(stripslashes($custom_prefs_str));
		if ($custom_prefs['default_prefs'] != "custom")
			$using_template = true;
		else
			$using_template = false;

		if ($punch) {
			$doc_title = $this->print_pref['punch_print']['doc_title'];
			if ($using_template) {
				$print_customer_hash = $custom_prefs['punch_print']['doc_print_customer_hash'];
				$print_ship_to_hash = $custom_prefs['punch_print']['doc_print_ship_to_hash'];
			} else {
				$print_customer_hash = $this->print_pref['punch_print']['doc_print_customer_hash'];
				$print_ship_to_hash = $this->print_pref['punch_print']['doc_print_ship_to_hash'];
			}

			$r = $this->db->query("SELECT customers.customer_name
								   FROM `customers`
								   WHERE customer_hash = '$print_customer_hash'");
			$p->current_proposal['customer'] = $this->db->result($r,0,'customer_name');
		} else
			$doc_title = $this->template->current_template['title']['value'];

		// Set bottom margin up to 45 to avoid overlapping with the printed date and valid through date messages. For trac #1120
		$bottom_margin = 45;

		// Get margins from system vars if they are defined
		$sys_var = fetch_sys_var('PROPOSAL_MARGIN');
		if ($sys_var) {
			$margins = explode(":",$sys_var);
			$this->pdf->ezSetMargins($margins[0],($bottom_margin ? $bottom_margin : $margins[1]),$margins[2],$margins[3]);
		} else
			$this->pdf->ezSetMargins(30,($bottom_margin ? $bottom_margin : 30),30,30);

		// Width of page adjusted for margins
		$adjWidth = $this->pdf->ez['pageWidth'] - $this->pdf->ez['leftMargin'] - $this->pdf->ez['rightMargin'];

		$title = stripslashes($p->current_proposal['customer'])." : $doc_title ".$p->current_proposal['proposal_no'];
		$author = stripslashes($p->current_proposal['sales_rep']);
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Author',$author);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);

		$customers = new customers($_SESSION['id_hash']);
		$customers->fetch_master_record(($print_customer_hash ? $print_customer_hash : $p->current_proposal['customer_hash']));
		$deposit_req = $customers->current_customer['deposit_percent'];

		// Fetch Customer Contact Details.  Added for Trac #850
		if ($p->current_proposal['customer_contact'] && in_array("customer_contact",$this->print_pref['gen_print_fields'])) {
			$customers->fetch_contact_record($p->current_proposal['customer_contact']);
			$contact_info = ($customers->current_contact['contact_name'] ? "\n".$this->template->current_template['attention']['value'].": ".stripslashes($customers->current_contact['contact_name'])."\n" : NULL).($customers->current_contact['contact_phone1'] ?
				$customers->current_contact['contact_phone1']." ".$this->template->current_template['phone']['value']."\n" : NULL).($customers->current_contact['contact_phone2'] ?
					$customers->current_contact['contact_phone2']." ".$this->template->current_template['phone2']['value']."\n" : NULL).($customers->current_contact['contact_mobile'] ?
						$customers->current_contact['contact_mobile']." ".$this->template->current_template['mobile']['value']."\n" : NULL).($customers->current_contact['contact_fax'] ?
							$customers->current_contact['contact_fax']." ".$this->template->current_template['fax']['value']."\n" : NULL).($customers->current_contact['contact_email'] ?
								$customers->current_contact['contact_email'] : NULL)." ";
		}


		//Propose to
		if (ereg("\|",$p->current_proposal['propose_to_hash'])) {
			list($class,$id,$hash) = explode("|",$p->current_proposal['propose_to_hash']);
			$customers->fetch_master_record($id);
			if ($hash) {
				$customers->fetch_location_record($hash);
				$propose_to = stripslashes($customers->current_customer['customer_name']) . " : " . $customers->current_location['location_name'] . "\n" .
				( $customers->current_location['location_street'] ?
    				stripslashes($customers->current_location['location_street']) . "\n" : NULL
    			) .
    			stripslashes($customers->current_location['location_city']) . ", {$customers->current_location['location_state']} {$customers->current_location['location_zip']}";

			} else {
				$propose_to = stripslashes($customers->current_customer['customer_name']) . "\n" .
				( $customers->current_customer['street'] ?
    				stripslashes($customers->current_customer['street']) . "\n" : NULL
    			) .
    			stripslashes( $customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";
			}
		} else {
			$customers->fetch_master_record($p->current_proposal['propose_to_hash']);
			$propose_to = stripslashes($customers->current_customer['customer_name']) . "\n" .
			( $customers->current_customer['street'] ?
    			stripslashes($customers->current_customer['street']) . "\n" : NULL
			) .
			stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";
		}

		if ($p->current_proposal['install_addr_hash']) {
			list($class,$id,$loc_hash) = explode("|",$p->current_proposal['install_addr_hash']);
			$obj = new $class($_SESSION['id_hash']);
			if ($loc_hash) {
				$obj->fetch_location_record($loc_hash);
				$install_loc = stripslashes($obj->current_location['location_name']) . "\n" .
				( $obj->current_location['location_street'] ?
					stripslashes($obj->current_location['location_street']) . "\n" : NULL
				) .
				stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

			} else {
				$obj->fetch_master_record($id);
				$install_loc = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
				) .
				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{"current_".strrev(substr(strrev($class),1))}['state']} {$obj->{"current_".strrev(substr(strrev($class),1))}['zip']}";
			}
			unset($obj);
		}
		if (($print_ship_to_hash ? $print_ship_to_hash : $p->current_proposal['ship_to_hash'])) {
			list($class,$id,$ship_hash) = explode("|",($print_ship_to_hash ? $print_ship_to_hash : $p->current_proposal['ship_to_hash']));
			if ($class) {
				$obj = new $class($this->current_hash);
				if ($ship_hash) {
					if ($loc_valid = $obj->fetch_location_record($ship_hash))
						$ship_to_loc = stripslashes($obj->current_location['location_name']) . "\n" .
						( $obj->current_location['location_street'] ?
    						stripslashes($obj->current_location['location_street']) . "\n" : NULL
    					) .
    					stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";
					else
						$ship_to = "Invalid Shipping Location";
				} else {
					$obj->fetch_master_record($id);
					$ship_to_loc = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
					( $obj->{"current_".strrev(substr(strrev($class),1))}["street"] ?
    					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}["street"]) . "\n" : NULL
    				) .
    				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}["city"]) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
				}
				unset($obj);
			}
		}

		if (in_array("item_no",$this->print_pref['item_print_fields']) || in_array("line_no",$this->print_pref['item_print_fields'])) {
			if (!in_array("item_no",$this->print_pref['item_print_fields']) && in_array("line_no",$this->print_pref['item_print_fields']))
				$item_cols['item_no'] = " ";
			elseif (in_array("item_no",$this->print_pref['item_print_fields'])) {
				$item_no_print = true;
				$item_cols['item_no'] = $this->template->current_template['item_no']['value'];
			}
			if (in_array("line_no",$this->print_pref['item_print_fields']))
				$line_no_print = true;
		}
		if (in_array("item_descr",$this->print_pref['item_print_fields']) || in_array("vendor",$this->print_pref['item_print_fields']) || in_array("product",$this->print_pref['item_print_fields']) || in_array("item_tag",$this->print_pref['item_print_fields']) || in_array("item_finish",$this->print_pref['item_print_fields']))
			$item_cols['item_descr'] = $this->template->current_template['item_descr']['value'];
		if (in_array("qty",$this->print_pref['item_print_fields']))
			$item_cols['qty'] = $this->template->current_template['qty']['value'];
		if (in_array("list",$this->print_pref['item_print_fields']))
			$item_cols['list'] = $this->template->current_template['item_list']['value'];
		if (in_array("ext_list",$this->print_pref['item_print_fields']))
			$item_cols['ext_list'] = $this->template->current_template['ext_list']['value'];
		if (in_array("cost",$this->print_pref['item_print_fields']))
			$item_cols['cost'] = $this->template->current_template['item_cost']['value'];
		if (in_array("ext_cost",$this->print_pref['item_print_fields']))
			$item_cols['ext_cost'] = $this->template->current_template['ext_cost']['value'];
		if (in_array("sell",$this->print_pref['item_print_fields']))
			$item_cols['sell'] = $this->template->current_template['item_sell']['value'];
		if (in_array("ext_sell",$this->print_pref['item_print_fields']))
			$item_cols['ext_sell'] = $this->template->current_template['ext_sell']['value'];

		$item_print_cntrl = "*";
		if (in_array("print_not_booked",$this->print_pref['item_print_fields']))
			$item_print_cntrl = "NB";
		elseif (in_array("print_booked",$this->print_pref['item_print_fields']))
            $item_print_cntrl = "B";
        elseif (in_array("print_invoiced",$this->print_pref['item_print_fields']))
            $item_print_cntrl = "I";

		$item_col_attr = array("item_no" 			=>	array('justification'	=>	'left'),
						 	   "item_descr"			=>  array('justification'	=>	'left'),
							   "qty"				=>  array('justification'	=>	'right'),
							   "list"				=>  array('justification'	=>	'right'),
							   "ext_list"			=>  array('justification'	=>	'right'),
							   "cost"				=>  array('justification'	=>	'right'),
							   "ext_cost"			=>  array('justification'	=>	'right'),
							   "sell"				=>  array('justification'	=>	'right'),
							   "ext_sell"			=>  array('justification'	=>	'right')
							   );

        if ($this->print_pref['currency'] && $this->print_pref['currency'] != FUNCTIONAL_CURRENCY) {
        	$currency = true;
            $sys = new system_config();
            $sys->fetch_currency($this->print_pref['currency']);
            if ($sys->current_currency['rate']) {
            	if ($p->current_proposal['exchange_rate'])
                    $sys->current_currency['rate'] = $p->current_proposal['exchange_rate'];
                   

	            $this->db->define('convert_currency',1);
	            $this->db->define('local_currency',$sys->currency_id);
	            $this->db->define('local_exchange_rate', $sys->currency_rate);
        	}
        	
        }

        $lines = new line_items($p->proposal_hash,$punch);
        $lines->fetch_line_items(0,$lines->total,1);
        $sign = $lines->line_info[0]['symbol'];

		$print_array = array();
		for ($i = 0; $i < $lines->total; $i++) {
			if ($lines->line_info[$i]['active'] && ($item_print_cntrl == '*' || ($item_print_cntrl == 'NB' && $lines->line_info[$i]['po_hash'] == '' && $lines->line_info[$i]['status'] == 0) || ($item_print_cntrl == 'B' && ($lines->line_info[$i]['po_hash'] != '' || $lines->line_info[$i]['status'] == 1) && $lines->line_info[$i]['invoice_hash'] == '') || ($item_print_cntrl == 'I' && $lines->line_info[$i]['invoice_hash'] != '')) && ($lines->line_info[$i]['sell'] > 0 || ($lines->line_info[$i]['sell'] <= 0 && in_array('zero_sell',$this->print_pref['item_print_fields'])))) {
				$descr = (in_array('vendor',$this->print_pref['item_print_fields']) ?
							$lines->line_info[$i]['vendor_name'].(!in_array('product',$this->print_pref['item_print_fields']) ?
								"\n" : NULL) : NULL).(in_array('product',$this->print_pref['item_print_fields']) ?
									(in_array('vendor',$this->print_pref['item_print_fields']) ?
										" - " : NULL).$lines->line_info[$i]['product_name']."\n\n" : NULL).(in_array('item_descr',$this->print_pref['item_print_fields']) ?
											$lines->line_info[$i]['item_descr'] : NULL).(in_array("item_tag",$this->print_pref['item_print_fields']) ?
												($lines->line_info[$i]['item_tag1'] ?
													"\n\n".$this->template->current_template['tag']['value']."1: ".$lines->line_info[$i]['item_tag1'] : NULL).($lines->line_info[$i]['item_tag2'] ?
														"\n".$this->template->current_template['tag']['value']."2: ".$lines->line_info[$i]['item_tag2'] : NULL).($lines->line_info[$i]['item_tag3'] ?
															"\n".$this->template->current_template['tag']['value']."3: ".$lines->line_info[$i]['item_tag3'] : NULL) : NULL);
				if (in_array('item_finish',$this->print_pref['item_print_fields'])) {
					$lines->fetch_line_item_record($lines->line_info[$i]['item_hash']);
					$import_data = unserialize(stripslashes($lines->current_line['import_data']));
					if (!$import_data)
						$import_data = unserialize($lines->current_line['import_data']);

					$finish =& $import_data['FINISH'];
					if (is_array($finish)) {
						$descr .= "\n\n".$this->template->current_template['finishes']['value'].":\n";
						for ($j = 0; $j < count($finish); $j++) {
							unset($item_panel);
							$special_chars = array("â„¢","™");
							if ($finish[$j]['PANEL'])
								$item_panel = stripslashes($finish[$j]['PANEL']);
							else
								$descr .= str_replace($special_chars,"",$finish[$j]['GROUP_DESCR'])." : ".stripslashes(str_replace($special_chars,"",$finish[$j]['DESCR']))." ".($finish[$j]['NAME'] ? "(".stripslashes(str_replace($special_chars,"",$finish[$j]['NAME'])).")" : NULL)."\n";

							if ($item_panel)
								$descr .= "\n".$this->template->current_template['panel_detail']['value'].": ".$item_panel;

						}
					}
				}
				if (in_array('cr',$this->print_pref['item_print_fields'])) {
					$lines->fetch_line_item_record($lines->line_info[$i]['item_hash']);
					$import_data = unserialize(stripslashes($lines->current_line['import_data']));
					if (!$import_data)
						$import_data = unserialize($lines->current_line['import_data']);

					$descr .= "\n\n".$this->template->current_template['item_special']['value'].":\n".stripslashes($import_data['SPECIAL']);
				}

				if (in_array('discounting',$this->print_pref['item_print_fields']) || in_array('gp_margin',$this->print_pref['item_print_fields']) || in_array('list_discount',$this->print_pref['item_print_fields'])) {
					if (in_array('discounting',$this->print_pref['item_print_fields'])) {
						$disc = ($lines->line_info[$i]['discount1'] > 0 ?
										$this->template->current_template['discount']['value']." 1: ".($lines->line_info[$i]['discount1'] * 100)."% \n" : NULL).($lines->line_info[$i]['discount2'] > 0 ?
											$this->template->current_template['discount']['value']." 2: ".($lines->line_info[$i]['discount2'] * 100)."% \n" : NULL).($lines->line_info[$i]['discount3'] > 0 ?
												$this->template->current_template['discount']['value']." 3: ".($lines->line_info[$i]['discount3'] * 100)."% \n" : NULL).($lines->line_info[$i]['discount4'] > 0 ?
													$this->template->current_template['discount']['value']." 4: ".($lines->line_info[$i]['discount4'] * 100)."% \n" : NULL).($lines->line_info[$i]['discount5'] > 0 ?
														$this->template->current_template['discount']['value']." 5: ".($lines->line_info[$i]['discount5'] * 100)."% \n" : NULL);
						if ($disc)
							$descr .= "\n\n".$disc;
					}
					if (in_array('gp_margin',$this->print_pref['item_print_fields']))
						$descr .= ($disc ? "\n" : "\n\n").$this->template->current_template['gp_margin']['value'].": ".$lines->line_info[$i]['gp_margin'];

					if ( in_array('list_discount',$this->print_pref['item_print_fields']) )
						$descr .=
						( $disc || in_array('gp_margin', $this->print_pref['item_print_fields']) && $lines->line_info[$i]['gp_type'] == 'G' ?
							"\n" : "\n\n"
						) .
						"{$this->template->current_template['list_discount']['value']}: " .
						math::list_discount( array(
							'list'    =>  $lines->line_info[$i]['list'],
							'sell'    =>  $lines->line_info[$i]['sell']
						) ) . "%";
				}

				$item_data[] = array("item_no" 		=> ($line_no_print ? $this->template->current_template['line']['value'].": ".$lines->line_info[$i]['line_no']."\n\n" : NULL).($item_no_print ? (strlen($lines->line_info[$i]['item_no']) > 25 ? wordwrap($lines->line_info[$i]['item_no'],25,"\n",1) : $lines->line_info[$i]['item_no']) : NULL),
									 "item_descr"	=> $descr,
									 "qty"			=> strrev(substr(strstr(strrev($lines->line_info[$i]['qty']),'.'),1)).(str_replace('0','',substr(strrchr($lines->line_info[$i]['qty'],'.'),1)) ?
															str_replace('0','',strrchr($lines->line_info[$i]['qty'],'.')) : NULL),
									 "list"			=> $sign.number_format($lines->line_info[$i]['list'],2),
									 "ext_list"		=> $sign.number_format($lines->line_info[$i]['ext_list'],2),
									 "sell"			=> $sign.number_format($lines->line_info[$i]['sell'],2),
									 "ext_sell"		=> $sign.number_format($lines->line_info[$i]['ext_sell'],2),
									 "cost"			=> $sign.number_format($lines->line_info[$i]['cost'],2),
									 "ext_cost"		=> $sign.number_format($lines->line_info[$i]['ext_cost'],2),
									 "item_tag"		=> ($lines->line_info[$i]['item_tag1'] ?
															$lines->line_info[$i]['item_tag1']." \n" : NULL).($lines->line_info[$i]['item_tag2'] ?
																$lines->line_info[$i]['item_tag2']." \n" : NULL).($lines->line_info[$i]['item_tag3'] ?
																	$lines->line_info[$i]['item_tag3'] : NULL));

				if ($lines->line_info[$i]['group_hash'])
					$group_totals[$lines->line_info[$i]['group_hash']] += $lines->line_info[$i]['ext_sell'];
				else
					$group_totals['misc_group'] += $lines->line_info[$i]['ext_sell'];

				if ($lines->line_info[$i]['group_hash'] != $lines->line_info[$i+1]['group_hash'] || !$lines->line_info[$i+1]) {
					if ($lines->line_info[$i]['group_hash'])
						$group_array[count($print_array)] = array('title'	=>	htmlspecialchars($lines->line_info[$i]['group_descr']),
																  'total'	=>	$group_totals[$lines->line_info[$i]['group_hash']]);

					array_push($print_array,$item_data);
					unset($item_data);
				}

				$current_line[] = $lines->line_info[$i]['item_hash'];
				$total_sell = bcadd($total_sell,$lines->line_info[$i]['ext_sell']);
				
			} else {
				// Add lines to group if next line is a different group. Added for Trac #1338
				if ($lines->line_info[$i]['group_hash'] != $lines->line_info[$i+1]['group_hash'] || !$lines->line_info[$i+1]) {
					if ($lines->line_info[$i]['group_hash'])
						$group_array[count($print_array)] = array('title'	=>	htmlspecialchars($lines->line_info[$i]['group_descr']),
																  'total'	=>	$group_totals[$lines->line_info[$i]['group_hash']]);

					array_push($print_array,$item_data);
					unset($item_data);
				}
			}
		}
		if ($item_data) {
            if (is_array($print_array))
                array_push($print_array,$item_data);
            else
                $print_array = array($item_data);
		}


		list($total_tax,$tax_rules,$tax_local,$indiv_tax) = $lines->calc_tax($current_line);

		for ($i = 0; $i < count($lines->proposal_comments); $i++) {
			if ($lines->proposal_comments[$i]['comment_action'] == 2)
				$comment_data[] = array('comments' => htmlspecialchars($lines->proposal_comments[$i]['comments']));
		}

		$headfoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$y_start = 776 - $this->h;
		$y = $this->pdf->y;

		if ($this->print_pref['print_logo'] && $this->img) {
			$this->pdf->ezImage($this->img,5,$this->w,'','left');
			$this->pdf->y = $y;

		}
		$y_start -= 25;
		$this->pdf->ezSetDy(-10);
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(2,'round');
		$x_start = 40 + $this->w;

		// Fetch sales rep info. Added for trac #651 and #665
		$sales_rep = new system_config();
		$sales_rep->fetch_user_record($p->current_proposal['sales_hash']);

		//Sales Rep
		$this->pdf->ezText($this->template->current_template['sales_rep']['value'].": ".$author,10,array('left' => $this->w + 10));
        $cur_x = $this->w + 15 + $this->pdf->ez['leftMargin'] + $this->pdf->getTextWidth(10,$this->template->current_template['sales_rep']['value'].": ".$author);
        $cur_y = $this->pdf->y - 1;
		if (in_array("sales_rep_email",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['email']) {
			if (($this->w + 15 + $this->pdf->ez['leftMargin'] + $this->pdf->getTextWidth(10,"Sales Rep: ".$author) + $this->pdf->getTextWidth(8,$sales_rep->current_user['email'])) >= ($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - 10 - $this->pdf->getTextWidth(15,$p->current_proposal['proposal_no']))) {
                $cur_x = $this->w + 10 + $this->pdf->ez['leftMargin'];
                $cur_y = $this->pdf->y - $this->pdf->getFontHeight(8);
			}
			$this->pdf->addText($cur_x,$cur_y,8,$sales_rep->current_user['email']);
		}
		if (in_array("sales_rep_phone",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['phone']) {
			$cur_y -= $this->pdf->getFontHeight(8) + 1;
			$cur_x = $this->w + 10 + $this->pdf->ez['leftMargin'];

			$this->pdf->addText($cur_x,$cur_y,8,$sales_rep->current_user['phone']." ".$this->template->current_template['phone']['value']);

			$cur_x = $this->w + 15 + $this->pdf->ez['leftMargin'] + $this->pdf->getTextWidth(8,$sales_rep->current_user['phone']." ".$this->template->current_template['phone']['value']);
		} else {
			$cur_y -= $this->pdf->getFontHeight(8);
			$cur_x = $this->w + 10 + $this->pdf->ez['leftMargin'];
		}
        if (in_array("sales_rep_fax",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['fax'])
            $this->pdf->addText($cur_x,$cur_y,8,$sales_rep->current_user['fax']." ".$this->template->current_template['fax']['value']);

		//Proposal title
		$text_width = $this->pdf->getTextWidth(20,$doc_title);
		$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,755,20,$doc_title);
		//Customer
		$width_customer_name = 525.28-$text_width-$this->w;
		$this->pdf->line($x_start,752,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],752);
		$this->pdf->addTextWrap($x_start,756,$width_customer_name,10,$this->template->current_template['customer']['value'].": ".stripslashes($p->current_proposal['customer']));
		//Proposal number
		$text_width = $this->pdf->getTextWidth(15,$p->current_proposal['proposal_no']);
		$font_height = $this->pdf->getFontHeight(15);
		$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,752 - $font_height,15,$p->current_proposal['proposal_no']);
		//Proposal date
		$text_width = $this->pdf->getTextWidth(10,date(DATE_FORMAT));
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,734 - $font_height,10,date(DATE_FORMAT,strtotime($using_template ? $custom_prefs['proposal_date'] : $this->print_pref['proposal_date'])));

		$this->pdf->line($this->pdf->ez['leftMargin'],30,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],30);
		$this->pdf->ezSetDy(-35);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		if (in_array("logo_page_only",$this->print_pref['gen_print_fields'])) {
			$headfoot_after_logo_odd = $this->pdf->openObject();
			$this->pdf->saveState();
			$this->pdf->selectFont('include/fonts/Helvetica.afm');
			$y_start = 776;

			//$y_start -= 25;
			$this->pdf->ezSetDy(-10);
			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(2,'round');
			$x_start = 30;
			//Customer
			$this->pdf->line($x_start,752,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],752);
			$this->pdf->addText($x_start,756,10,$this->template->current_template['customer']['value'].": ".stripslashes($p->current_proposal['customer']));
			//Sales Rep
			$this->pdf->addText($x_start,740,10,$this->template->current_template['sales_rep']['value'].": ".$author."  ".$sales_email);
	        $cur_x = $x_start + 5 + $this->pdf->getTextWidth(10,$this->template->current_template['sales_rep']['value'].": ".$author);
	        $cur_y = 740;
	        if (in_array("sales_rep_email",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['email'])
	            $this->pdf->addText($cur_x,$cur_y,8,$sales_rep->current_user['email']);

	        if (in_array("sales_rep_phone",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['phone']) {
	            $cur_y -= $this->pdf->getFontHeight(8) + 2;
	            $this->pdf->addText($x_start,$cur_y,8,$sales_rep->current_user['phone']." ".$this->template->current_template['phone']['value']);

	            $cur_x = $x_start + 5 + $this->pdf->getTextWidth(8,$sales_rep->current_user['phone']." ".$this->template->current_template['phone']['value']);
	        } else
	            $cur_y -= $this->pdf->getFontHeight(8);

	        if (in_array("sales_rep_fax",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['fax'])
	            $this->pdf->addText($cur_x,$cur_y,8,$sales_rep->current_user['fax']." ".$this->template->current_template['fax']['value']);
			//Proposal title
			$text_width = $this->pdf->getTextWidth(20,'Proposal');
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,755,20,$this->template->current_template['title']['value']);
			//Proposal number
			$text_width = $this->pdf->getTextWidth(15,$p->current_proposal['proposal_no']);
			$font_height = $this->pdf->getFontHeight(15);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,752 - $font_height,15,$p->current_proposal['proposal_no']);
			//Proposal date
			$text_width = $this->pdf->getTextWidth(10,date(DATE_FORMAT));
			$font_height = $this->pdf->getFontHeight(10);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,734 - $font_height,10,date(DATE_FORMAT,strtotime($using_template ? $custom_prefs['proposal_date'] : $this->print_pref['proposal_date'])));

			$this->pdf->line($this->pdf->ez['leftMargin'],30,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],30);
			$this->pdf->ezSetDy(-35);
			$this->pdf->restoreState();
			$this->pdf->closeObject();

			$headfoot_after_logo_even = $this->pdf->openObject();
			$this->pdf->saveState();
			$this->pdf->selectFont('include/fonts/Helvetica.afm');
			$y_start = 776;

			//$y_start -= 25;
			$this->pdf->ezSetDy(-10);
			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(2,'round');
			$x_start = 30;
			//Customer
			$this->pdf->line($x_start,752,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],752);
			$this->pdf->addText($x_start,756,10,$this->template->current_template['customer']['value'].": ".stripslashes($p->current_proposal['customer']));
			//Sales Rep
			$this->pdf->addText($x_start,740,10,$this->template->current_template['sales_rep']['value'].": ".$author."  ".$sales_email);
	        $cur_x = $x_start + 5 + $this->pdf->getTextWidth(10,$this->template->current_template['sales_rep']['value'].": ".$author);
	        $cur_y = 740;
	        if (in_array("sales_rep_email",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['email'])
	            $this->pdf->addText($cur_x,$cur_y,8,$sales_rep->current_user['email']);

	        if (in_array("sales_rep_phone",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['phone']) {
	            $cur_y -= $this->pdf->getFontHeight(8) + 2;
	            $this->pdf->addText($x_start,$cur_y,8,$sales_rep->current_user['phone']." ".$this->template->current_template['phone']['value']);

	            $cur_x = $x_start + 5 + $this->pdf->getTextWidth(8,$sales_rep->current_user['phone']." ".$this->template->current_template['phone']['value']);
	        } else
	            $cur_y -= $this->pdf->getFontHeight(8);

	        if (in_array("sales_rep_fax",$this->print_pref['gen_print_fields']) && $sales_rep->current_user['fax'])
	            $this->pdf->addText($cur_x,$cur_y,8,$sales_rep->current_user['fax']." ".$this->template->current_template['fax']['value']);
			//Proposal title
			$text_width = $this->pdf->getTextWidth(20,$this->template->current_template['title']['value']);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,755,20,$this->template->current_template['title']['value']);
			//Proposal number
			$text_width = $this->pdf->getTextWidth(15,$p->current_proposal['proposal_no']);
			$font_height = $this->pdf->getFontHeight(15);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,752 - $font_height,15,$p->current_proposal['proposal_no']);
			//Proposal date
			$text_width = $this->pdf->getTextWidth(10,date(DATE_FORMAT));
			$font_height = $this->pdf->getFontHeight(10);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,734 - $font_height,10,date(DATE_FORMAT,strtotime($using_template ? $custom_prefs['proposal_date'] : $this->print_pref['proposal_date'])));

			$this->pdf->line($this->pdf->ez['leftMargin'],30,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],30);
			$this->pdf->ezSetDy(-35);
			$this->pdf->restoreState();
			$this->pdf->closeObject();
		}

		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');


		$x_footer_adj = 30;
        //Company information
        if (in_array("company_footer",$this->print_pref['gen_print_fields'])) {
            $this->pdf->addText(30,20,8,MY_COMPANY_NAME);
            $this->pdf->addText(30,12,6,str_replace("\n"," ",MY_COMPANY_ADDRESS)." ".MY_COMPANY_PHONE." ".$this->template->current_template['phone']['value']." ".MY_COMPANY_FAX." ".$this->template->current_template['fax']['value']);
        }

        //Expiration Date
		if (in_array("expiry_date",$this->print_pref['gen_print_fields']) && $p->current_proposal['expiration_date'] && $p->current_proposal['expiration_date'] != '0000-00-00')
            $this->pdf->addText(30,(in_array("company_footer",$this->print_pref['gen_print_fields']) ? 35 : 20),8,$this->template->current_template['proposal_valid']['value']." ".date(DATE_FORMAT,strtotime($p->current_proposal['expiration_date'])));

        //Print Date/Time
        $this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->getTextWidth(7,"Printed On: ".date(DATE_FORMAT." ".TIMESTAMP_FORMAT)),35,7,'Printed On: '.date(DATE_FORMAT." ".TIMESTAMP_FORMAT));

		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($headfoot,(in_array("logo_page_only",$this->print_pref['gen_print_fields']) ? 'add' : 'all'));
		if ($headfoot_after_logo_even) {
			$this->pdf->addObject($headfoot_after_logo_even,'nextodd');
			$this->pdf->addObject($headfoot_after_logo_odd,'nexteven');
		}
		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		if ($this->pdf->y > 770)
			$this->pdf->ezSetY(770);

		if ($this->h < 60) {
			$this->h = 60;
		}

		$this->pdf->ez['topMargin'] = 30 + $this->h;

		//Show the propose to and ship to information
		if (in_array("propose_to",$this->print_pref['gen_print_fields'])) {
			$data_tmp['propose_to'] = $propose_to;
			$cols['propose_to'] = $this->template->current_template['propose_to']['value'].":";
			$header_count++;
		}

		if (in_array("ship_to",$this->print_pref['gen_print_fields'])) {
			$data_tmp['ship_to'] = $ship_to_loc;
			$cols['ship_to'] = $this->template->current_template['ship_to']['value'].":";
			$header_count++;
		}
		if (in_array("install_addr",$this->print_pref['gen_print_fields'])) {
			$data_tmp['install_addr'] = $install_loc;
			$cols['install_addr'] = $this->template->current_template['install']['value'].":";
			$header_count++;
		}

		// Prevent columns from being unevenly spaced in the case of large customer names. For #879.
		$options_cols = array('customer' 			=> array("width" => ($num_addresses > 3  ? "140" : "180")),
							  'install_addr' 		=> array("width" => ($num_addresses > 3  ? "140" : "180")),
							  'ship_to_addr' 		=> array("width" => ($num_addresses > 3  ? "140" : "180")));

		$xpos = 0;
		$this->pdf->ezSetY($this->pdf->ez['pageHeight']-$this->pdf->ez['topMargin']);
		if ($header_count > 0) {
			$data = array($data_tmp);
			$options = array('showLines' 	=> 0,
							 'shaded'		=> 0,
							 'maxWidth'		=> $this->pdf->ez['pageWidth'],
							 'xPos'			=> $xpos,
							 'colGap'		=> ($header_count > 3 ? 5 : 15),
							 'fontSize'		=> $this->template->current_template['header_data']['font_size'],
							 'xOrientation'	=> 'right');
			if ($header_count > 2)
				$options['cols'] = $options_cols;

			$this->pdf->ezTable($data,$cols,NULL,$options);
			$y = $this->pdf->ezText($contact_info,$this->template->current_template['contact_data']['font_size']);
		}
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'],$y - 10,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$y - 10);

		$this->pdf->ezSetDy(-15);

		if (in_array("proposal_descr",$this->print_pref['gen_print_fields'])) {
			$cols = array("descr" => "Proposal Description:");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> 30,
							 'showHeadings'		=> 0,
							 'fontSize'			=> 12,
							 'titleFontSize'	=> 14,
							 'xOrientation' 	=> 'right',
							 'width'			=> $adjWidth,
							 'maxWidth'			=> $adjWidth);
			$proposal_descr[] = array("descr" => $p->current_proposal['proposal_descr']);
			$y = $this->pdf->ezTable($proposal_descr,$cols,NULL,$options);
			$this->pdf->ezSetDy(-5);

			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(1);
			$this->pdf->line($this->pdf->ez['leftMargin'],$y - 10,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$y - 10);
			$this->pdf->ezSetDy(-15);
		}

		// Add customer po for Trac #1400
		if (in_array("customer_po",$this->print_pref['gen_print_fields']))
			$this->pdf->ezText("Customer PO: ".$p->current_proposal['customer_po'],10);

		if (is_array($comment_data)) {
			$cols = array("comments" => "Comments:");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> 30,
							 'showHeadings'		=> 0,
							 'fontSize'			=> $this->template->current_template['comments_data']['font_size'],
							 'titleFontSize'	=> $this->template->current_template['comments']['font_size'],
							 'xOrientation' 	=> 'right',
							 'width'			=> $adjWidth,
							 'maxWidth'			=> $adjWidth);
			$this->pdf->ezText($this->template->current_template['comments']['value'].':',$this->template->current_template['comments']['font_size']);
			$y = $this->pdf->ezTable($comment_data,$cols,'',$options);
			$this->pdf->ezSetDy(-10);
		}

		// Adjust top margin if logo is only printed on first page. Added for #566.
		if (in_array("logo_page_only",$this->print_pref['gen_print_fields']))  {
			$this->pdf->ez['topMargin'] = ($margins[0] ? $margins[0] + 65 : 95);
		}

		//Show the line items
		$item_options = array("width" 		=> $adjWidth,
							  "maxWidth"	=> $adjWidth,
							  'rowGap'		=>	4,
							  'showLines'	=>  2,
							  'lineCol'		=>	array(.8,.8,.8),
							  'shaded'		=>  0,
							  'shadeCol'	=>  array(.94,.94,.94),
							  "cols"		=>  $item_col_attr,

							  );
		$totals_options = array('xPos'					=>	$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - 15,
								'width'					=>	$adjWidth - 120,
								'xOrientation'			=>	'left',
								'showLines'				=>  0,
								'lineCol'				=>  array(.8,.8,.8),
								'showHeadings'			=>  0,
								'shaded'				=>	0,
								'fontSize'				=>	12,
								'rowGap'				=>	4,
								'colGap'				=>  0,
								'cols'					=>	array('field'	=>	array('justification'	=>	'left',
																					  'width'			=>	315),
																  'val'		=>	array('justification'	=>	'right',
																					  'width'			=>	100.28)
																  )
								);

		if (is_array($print_array)) {
			// If there are multiple groups, ensure that each group prints with the same column widths for Trac #1252
			if (count($print_array) > 1) {
				$descr_width = $adjWidth;
				$num_item_opts = $num_main_opts = 0;
				if (in_array('list',$this->print_pref['item_print_fields']))
					$num_item_opts++;
				if (in_array('ext_list',$this->print_pref['item_print_fields']))
					$num_item_opts++;
				if (in_array('cost',$this->print_pref['item_print_fields']))
					$num_item_opts++;
				if (in_array('ext_cost',$this->print_pref['item_print_fields']))
					$num_item_opts++;
				if (in_array('sell',$this->print_pref['item_print_fields']))
					$num_item_opts++;
				if (in_array('ext_sell',$this->print_pref['item_print_fields']))
					$num_item_opts++;
				if (in_array('item_no',$this->print_pref['item_print_fields']) || in_array('line_no',$this->print_pref['item_print_fields'])) {
					$item_no_width = ($num_item_opts > 4 ? 45 : 60);
					$descr_width -= $item_no_width;
				}
				if (in_array('qty',$this->print_pref['item_print_fields'])) {
					$qty_width = ($num_item_opts > 4 ? 27 : 30);
					$descr_width -= $qty_width;
				}
				if ($num_item_opts > 5)
					$item_width = 55;
				elseif ($num_item_opts > 4)
					$item_width = 60;
				elseif ($num_item_opts > 3)
					$item_width = 70;
				elseif ($num_item_opts > 2)
					$item_width = 80;
				else
					$item_width = 85;

				$descr_width -= ($num_item_opts * $item_width);

				$item_col_attr = array("item_no"	=>	array('justification'	=>	'left' , 'width'	=>	$item_no_width),
					 	   "item_descr"			=>  array('justification'	=>	'left' , 'width'	=>	$descr_width),
						   "qty"				=>  array('justification'	=>	'right' , 'width'	=>	$qty_width),
						   "list"				=>  array('justification'	=>	'right' , 'width'	=>	$item_width),
						   "ext_list"			=>  array('justification'	=>	'right' , 'width'	=>	$item_width),
						   "cost"				=>  array('justification'	=>	'right' , 'width'	=>	$item_width),
						   "ext_cost"			=>  array('justification'	=>	'right' , 'width'	=>	$item_width),
						   "sell"				=>  array('justification'	=>	'right' , 'width'	=>	$item_width),
						   "ext_sell"			=>  array('justification'	=>	'right' , 'width'	=>	$item_width)
						   );
				$item_options['cols'] = $item_col_attr;
			}

	        if ($currency == true) {
	            $this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->getTextWidth(10,"Currency".$sys->currency_id." ".rtrim(trim($sys->currency_rate, '0'), '.')."%") - 10,$this->pdf->y - 15,10,"Currency: ".$sys->currency_id." ".rtrim(trim($sys->currency_rate, '0'), '.')."%");
				if ($this->print_pref['group_summary'])
					$this->pdf->ezSetDy(-20);
	        }

            //Add totals for all groups
            $group_subtotal = 0;
            
			// Modified to add group summary only option for Trac #1011
			for ($i = 0; $i < count($print_array); $i++) {
				if (!$this->print_pref['group_summary'] && (!key_exists('total',$group_array[$i]) || is_numeric($group_array[$i]['total']))) { // Added for Trac #1011
					$this->pdf->ezText(($group_array[$i] ? $this->template->current_template['group']['value']." ".$group_array[$i]['title'].":" : NULL),12);
					$this->pdf->ezSetDy(-5);
					$y = $this->pdf->ezTable($print_array[$i],$item_cols,'',$item_options);
				}
				if (($group_array[$i] || $this->print_pref['group_summary']  ) && ($group_array[$i]['total'] > 0 || ($group_array[$i]['total'] <= 0 && in_array('zero_sell',$this->print_pref['item_print_fields']))))  {
					$group_title = ($group_array[$i]['title'] ? $group_array[$i]['title'] : $this->template->current_template['misc_items']['value']);
					$group_total = (is_numeric($group_array[$i]['total']) ? $group_array[$i]['total'] : 0);
                                        
					if (in_array('group_totals',$this->print_pref['gen_print_fields']) || $this->print_pref['group_summary']) {
						// Added for Trac #1325
						if (substr_count($group_title,"\n") > 0) {
							unset($newlines);
							for($j = 0; $j < substr_count($group_title,"\n"); $j++)
								$newlines .= "\n";
							$total_array = array(array('field'	=>	$this->template->current_template['group']['value']." ".$group_title." ".$this->template->current_template['total']['value'].': ',
						   							  'val' 	=> 	$newlines.$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($group_total,2)))).'$'.number_format($group_total,2)
						   						));
						} else {
							$total_array = array(array('field'	=>	$this->template->current_template['group']['value']." ".$group_title." ".$this->template->current_template['total']['value'].': '.$this->pdf->fill_void('.',(315 - $this->pdf->getTextWidth(12,$this->template->current_template['group']['value'].' '.$group_title." ".$this->template->current_template['total']['value'].': '))),
												   		'val' 	=> 	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($group_total,2)))).'$'.number_format($group_total,2)
												   ));
						}
						if (is_numeric($group_array[$i]['total']) || is_numeric($group_totals['misc_group']))
							$y = $this->pdf->ezTable($total_array,NULL,NULL,$totals_options);

					}
					if (in_array("grp_break",$this->print_pref['gen_print_fields']))
						$this->pdf->ezNewPage();
					else
						$this->pdf->ezSetDy(-10);
				} else
					$this->pdf->ezSetDy(-5);
			}
		}
        
		if (in_array("panel_detail",$this->print_pref['gen_print_fields'])) {
			$import_data = $p->current_proposal['import_data'];
			$panel_info =& $import_data['PANEL_TYPE'];

			if ($panel_info) {
				$this->pdf->ezNewPage();
				for ($i = 0; $i < count($panel_info); $i++) {
					$detail =& $panel_info[$i];
					$p_detail = array();

					$p_detail[] = $this->template->current_template['panel_name']['value'].": ".stripslashes($detail['NAME'])."\n".$this->template->current_template['description']['value'].": ".stripslashes($detail['DESCR'])."\n".$this->template->current_template['panel_height']['value'].": ".$detail['HEIGHT']."\n".$this->template->current_template['total_height']['value'].": ".$detail['TOTAL_HEIGH']."\n".$this->template->current_template['width']['value'].": ".$detail['WIDTH'];
					$type_el =& $detail['ELEMENT'];

					for ($j = 0; $j < count($type_el); $j++) {
						$p_detail[] = "\n    ".$this->template->current_template['group']['value']." ".($j + 1).": ".stripslashes($type_el[$j]['DESCR'])." (".$type_el[$j]['PN'].")";

						$finish_grp = $type_el[$j]['FINISH'];
						for ($k = 0; $k < count($finish_grp); $k++) {
							if ($finish_grp[$k])
								$p_detail[] = "        ".$this->template->current_template['fabric_option']['value']." ".($k + 1).": ".stripslashes($finish_grp[$k]['GROUP_DESCR'])." - ".stripslashes($finish_grp[$k]['DESCR']);
						}
					}

					$panel_detail[] = array('panel' => implode("\n",$p_detail));
				}
			}

			if ($panel_detail) {
				$cols = array("panel" => $this->template->current_template['panel_details']['value'].":");
				$options = array('showLines' 		=> 1,
								 'lineCol'			=> array(.8,.8,.8),
								 'shaded'			=> 0,
								 'xPos'				=> 30,
								 'showHeadings'		=> 0,
								 'titleFontSize'	=> 12,
								 'xOrientation' 	=> 'right',
								 'width'			=> $adjWidth,
								 'maxWidth'			=> $adjWidth);
				$y = $this->pdf->ezTable($panel_detail,$cols,$this->template->current_template['panel_details']['value'].':',$options);
				$this->pdf->ezSetDy(-15);
			} else
                $this->pdf->ezSetDy(-15);
		} else
            $this->pdf->ezSetDy(-15);

		$totals_options['width'] = 215.28;
		$totals_options['cols']['field']['width'] = 170;
		//Put the signature line and totals at the bottom, or next page
/*		if ($this->pdf->y - (($this->pdf->getFontHeight(12) * 3) + 12) < (($this->pdf->getFontHeight(12) * 3) + 12)) {
			$this->pdf->ezNewPage();
			$this->pdf->ezSetDy(-100);
		}*/

        if ($currency == true)
            $total_tax = currency_exchange($total_tax,$sys->current_currency['rate'],1);

		if ($group_array && in_array("grp_sum",$this->print_pref['gen_print_fields'])) {
			$y = $this->pdf->ezText($this->template->current_template['grouping_summary']['value'].":",13);
			$this->pdf->ezSetDy(-5);
			while (list($k,$grp) = each($group_array)) {
				if ($grp['total'] != 0 || in_array('zero_sell',$this->print_pref['item_print_fields'])) {
					$y = $this->pdf->ezText("- ".$grp['title']." ".$this->template->current_template['totals']['value'].": ".$sign.number_format($grp['total'],2),11,array('left' =>  10));
					$this->pdf->ezSetDy(-3);
				}
			}

			$this->pdf->ezSetDy(-15);
		}
		if (in_array("item_totals",$this->print_pref['gen_print_fields'])) {
			//Added to display multiple subtotals
			if (in_array("sub_total",$this->print_pref['gen_print_fields'])) {
				$total_sellSub = $total_sell;
				$subtotal_titles = array();
				$subtotal_values = array();
				for ($i = 0; $i < $lines->total; $i++) {
					$vendors = new vendors($_SESSION['id_hash']);
					$vendors->fetch_product_record($lines->line_info[$i]['product_hash']);
					if($vendors->current_product['product_subtotal']){
						$total_sellSub = bcsub($total_sellSub,$lines->line_info[$i]['ext_sell']);
						if(!in_array($vendors->current_product['subtotal_title'], $subtotal_titles)){
							$subtotal_titles[] =  $vendors->current_product['subtotal_title'];
							$subtotal_values[] =  $lines->line_info[$i]['ext_sell'];
						}else{
							$key = array_search($vendors->current_product['subtotal_title'], $subtotal_titles);
							$temp_val = $subtotal_values[$key];
							$subtotal_values[$key] = $temp_val + $lines->line_info[$i]['ext_sell'];
						}
					}
				}
				
				if($total_sellSub > 0){
					$totals_data[] = array('field'  	=>	$this->template->current_template['subtotal']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['subtotal']['value']))),
							'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$sign.number_format($total_sellSub,2)))).$sign.number_format($total_sellSub,2));
				}
				
				$s = 0;
				while($s < count($subtotal_titles) ){
					$totals_data[] = array('field'  	=>  $subtotal_titles[$s].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$subtotal_titles[$s]))),
								   'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$sign.number_format($subtotal_values[$s],2)))).$sign.number_format($subtotal_values[$s],2));
					$s++;
				}
				
			}else{
				$totals_data[] = array('field'  	=>	$this->template->current_template['subtotal']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['subtotal']['value']))),
									   'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$sign.number_format($total_sell,2)))).$sign.number_format($total_sell,2));
			}
		}
		if (bccomp($total_tax,0) == 1 && in_array("tax",$this->print_pref['gen_print_fields'])) {
			if ($lines->tax_country == 'CA' && $indiv_tax) {
				while (list($tax_hash,$local_name) = each($tax_local)) {
					if ($currency == true)
    					$tax_rules[$tax_hash] = currency_exchange($tax_rules[$tax_hash],$sys->current_currency['rate'],1);

	                $totals_data[] = array('field'  =>  $this->template->current_template['tax']['value'].' - '.strtoupper($local_name).$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['tax']['value'].' - '.strtoupper($local_name)))),
	                                       'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$sign.number_format($tax_rules[$tax_hash],2)))).$sign.number_format($tax_rules[$tax_hash],2));
				}

			} else
				$totals_data[] = array('field'  =>	$this->template->current_template['tax']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['tax']['value']))),
									   'val'	=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$sign.number_format($total_tax,2)))).$sign.number_format($total_tax,2));

            $totals_content = 1;
		}
		if (in_array("item_totals",$this->print_pref['gen_print_fields'])) {
			$total_totals = $total_sell + $total_tax;
			$totals_data[] = array('field'  	=>	$this->template->current_template['total_amount']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['total_amount']['value']))),
								   'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$sign.number_format($total_totals,2)))).$sign.number_format($total_totals,2));
			$totals_content = 1;
		}
		if ($totals_content)
			$y = $this->pdf->ezTable($totals_data,NULL,NULL,$totals_options);

		if ($p->current_proposal['order_type'] == "D" && !in_array("hide_po_instr",$this->print_pref['gen_print_fields'])) {
			$direct = $lines->fetch_direct_bill_info();
			$vendors = new vendors($_SESSION['id_hash']);
			$this->pdf->ezNewPage();
			$this->pdf->ezSetDy(-10);
			$i = 0;
			while (list($vendor_hash,$vendor_info) = each($direct)) {
				if ($vendor_info['total_sell'] > 0) {
					if ($vendor_info['type'] == 'D') {
						$po_to = array();
						$vendors->fetch_master_record($vendor_hash);
						$po_to[] = $vendor_info['vendor'];
						$po_to[] = (defined('MY_COMPANY_NAME') ? 'C/O '.MY_COMPANY_NAME : NULL);
						$po_to[] = stripslashes($vendors->current_vendor['street']);

						$po_to[] = stripslashes($vendors->current_vendor['city']) . ", {$vendors->current_vendor['state']} {$vendors->current_vendor['zip']}";
						$po_to[] = ($vendors->current_vendor['phone'] ? $vendors->current_vendor['phone']." ".$this->template->current_template['phone']['value'] : NULL);
						$po_to[] = ($vendors->current_vendor['fax'] ? "".$vendors->current_vendor['fax']." ".$this->template->current_template['fax']['value'] : NULL);
					} else
						$po_to = MY_COMPANY_NAME." \n ".MY_COMPANY_ADDRESS;

					$i++;

					$y = $this->pdf->ezText($this->template->current_template['issue_po_line1']['value'].$i.", ".$this->template->current_template['issue_po_line2']['value'].$sign.number_format($vendor_info['total_sell'],2)." ".$this->template->current_template['issue_po_line3']['value']." ",14);

					if (is_array($po_to)) {
						for ($k = 0; $k < count($po_to); $k++) {
							if ($k == 0 )
								$this->pdf->ezText($po_to[0],12);
							else
								($po_to[$k] ? $this->pdf->ezText(" ".$po_to[$k],12) : NULL);
						}
					} else {
						$this->pdf->ezText($po_to,12);
					}
					$this->pdf->ezSetDy(-10);
				}

			}
		}
		if ($deposit_req * 100 > 0 && in_array("deposit",$this->print_pref['gen_print_fields']))
			$dep_msg = $this->template->current_template['dep_line1']['value']." ".bcmul($deposit_req,100)."% ($".number_format(bcmul($total_sell,$deposit_req),2).") ".$this->template->current_template['dep_line2']['value'];

		$footer_msg = base64_decode($_GET['footer_msg']);
		if (!$footer_msg)
			$footer_msg = fetch_sys_var('proposal_footer_message');
		if ($footer_msg || $dep_msg) {
			if ($y < 200)
				$this->pdf->ezNewPage();

			$this->pdf->ezSetY(200);
			$y = $this->pdf->ezText(($footer_msg ? stripslashes($footer_msg)."\n" : NULL).stripslashes($dep_msg));
		}

		$this->pdf->ezSetY(72);
		$this->pdf->addText($this->pdf->ez['leftMargin'],72,12,$this->template->current_template['accepted_by']['value'].": ");
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin']+$this->pdf->getTextWidth(12,$this->template->current_template['accepted_by']['value'].": "),72,400,72);
		$this->pdf->addText(405,72,12,$this->template->current_template['date']['value'].": ");
		$this->pdf->line(405+$this->pdf->getTextWidth(12,$this->template->current_template['date']['value'].": "),72,525,72);

	}

	/**
	 * Generate a customer invoice to a pdf document.  Updated to allow print options for trac #473
	 *
	 * @param string $proposal_hash
	 * @param string $invoice_hash
	 * @param int $mult
	 */
	function customer_invoice($proposal_hash,$invoice_hash,$mult=0) {
		$proposal = new proposals($_SESSION['id_hash']);
		$valid = $proposal->fetch_master_record($proposal_hash);
		$invoice = new customer_invoice($_SESSION['id_hash']);
		$valid2 = $invoice->fetch_invoice_record($invoice_hash);

		if (!$valid || !$valid2)
			die('Invalid proposal or invoice hash.');
		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		if ($mult == 0)
			$this->pdf = new Cezpdf('Letter');

		// Get margins from system vars if they are defined
		$sys_var = fetch_sys_var('INVOICE_MARGIN');
		if ($sys_var) {
			$margins = explode(":",$sys_var);
			$this->pdf->ezSetMargins($margins[0],$margins[1],$margins[2],$margins[3]);
		} else
			$this->pdf->ezSetMargins(30,30,30,30);

		// Width of page adjusted for margins
		$adjWidth = $this->pdf->ez['pageWidth'] - $this->pdf->ez['leftMargin'] - $this->pdf->ez['rightMargin'];

		//If we're printing multiple documents back to back
		if ($mult > 0) {
			$this->pdf->ez['topMargin'] = ($margin[0] ? $margin[0] : 30);
			$this->pdf->stopObject($this->headfoot);
			$this->pdf->stopObject($this->bottomFoot);
			$this->pdf->ezNewPage();
			$this->pdf->ezStopPageNumbers(1,0,$mult - 1);
		}

		$title = ($this->print_pref['title'] && $this->print_pref['title'] != "Invoice" ? $this->print_pref['title'] : $this->template->current_template['title']['value'])." ".$invoice->current_invoice['invoice_no'];
		$author = stripslashes($proposal->current_proposal['sales_rep']);
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Author',$author);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);
		$title = ($this->print_pref['title'] && $this->print_pref['title'] != "Invoice" ? $this->print_pref['title'] : $this->template->current_template['title']['value']);

		$customers = new customers($_SESSION['id_hash']);
		if ($invoice->current_invoice['transmit_type'] == 'M' && $invoice->current_invoice['transmit_to']) {
			$customer_info = $invoice->current_invoice['transmit_to'];
			if ($invoice->current_invoice['invoice_to'] == 'V') {
				$obj = new vendors($this->current_hash);
				$class = 'vendors';
				$obj->fetch_master_record($invoice->current_invoice['customer_hash']);
				$customer_name = stripslashes($obj->current_vendor['vendor_name']);

	            $customer_info = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
	            ( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
	                stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
	            ) .
	            stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";

				if (in_array("customer_contact",$this->print_pref['gen_print_fields'])) {
					if ($_POST['customer_contact']) {
						$contact_name = stripslashes($_POST['customer_contact']);
						$customer_info .= "\n \n".$this->template->current_template['attention']['value'].": ".$contact_name;
					} elseif ($obj->current_vendor['vendor_contact']) {
						$obj->fetch_contact_record($obj->current_vendor['vendor_contact']);
						$contact_name = stripslashes($obj->current_contact['contact_name']);
						$customer_info .= "\n \n".$this->template->current_template['attention']['value'].": ".$contact_name;
					}
				}
			} else {
				$obj = new customers($this->current_hash);
				$obj->fetch_master_record($proposal->current_proposal['customer_hash']);
				$customer_name = $obj->current_customer['customer_name'];
				$customer_pay_days = $obj->current_customer['payment_terms'];

				//Fetch Customer Contact
				if (in_array("customer_contact",$this->print_pref['gen_print_fields'])) {
					if ($_POST['customer_contact']) {
						$customer_info .= "\n \n".$this->template->current_template['attention']['value'].": ".stripslashes($_POST['customer_contact']);
					} elseif ($proposal->current_proposal['customer_contact']) {
						$obj->fetch_contact_record($proposal->current_proposal['customer_contact']);
						if ($obj->current_contact['contact_name'])
							$customer_info .= "\n \n".$this->template->current_template['attention']['value'].": ".stripslashes($obj->current_contact['contact_name']);
					}
				}
			}
		} elseif ($invoice->current_invoice['invoice_to'] == 'V') {
			$class = 'vendors';
			$id = $invoice->current_invoice['customer_hash'];
			$obj = new vendors($this->current_hash);
			$obj->fetch_master_record($id);

			//Fetch Vendor Contact
			if (in_array("customer_contact",$this->print_pref['gen_print_fields'])) {
				if ($_POST['customer_contact']) {
					$contact_name = stripslashes($_POST['customer_contact']);
				} elseif ($obj->current_vendor['vendor_contact']) {
					$obj->fetch_contact_record($obj->current_vendor['vendor_contact']);
					$contact_name = stripslashes($obj->current_contact['contact_name']);
				}
			}

			$customer_info = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
			( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
			) .
			stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
			$customer_name = $obj->current_vendor['vendor_name'];

		} else {
			$customer_name = $proposal->current_proposal['customer'];
			//Fetch Customer Contact
			if (in_array("customer_contact",$this->print_pref['gen_print_fields'])) {
				if ($_POST['customer_contact']) {
					$contact_name = stripslashes($_POST['customer_contact']);
				} elseif ($proposal->current_proposal['customer_contact']) {
					$customers->fetch_contact_record($proposal->current_proposal['customer_contact']);
					$contact_name = stripslashes($customers->current_contact['contact_name']);
				}
			}

			if ($proposal->current_proposal['customer_hash']) {
				$class = 'customers';
				$id = $proposal->current_proposal['customer_hash'];
				if ($proposal->current_proposal['bill_to_hash'])
					$loc_hash = $proposal->current_proposal['bill_to_hash'];

				$obj = new customers($this->current_hash);
				$obj->fetch_master_record($id);
				$customer_pay_days = $obj->current_customer['payment_terms'];
				if ($loc_hash) {
					$obj->fetch_location_record($loc_hash);
					$customer_info = stripslashes($obj->current_location['location_name']) . "\n" .
					( $obj->current_location['location_street'] ?
						stripslashes($obj->current_location['location_street']) . "\n" : NULL
					) .
					stripslashes($obj->current_location['city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

				} else {
					$customer_info = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
					( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
						stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
					) .
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
				}

				unset($obj);
			}
		}

		if ($proposal->current_proposal['install_addr_hash'] && in_array("install_addr",$this->print_pref['gen_print_fields'])) {
			list($class,$id,$loc_hash) = explode("|",$proposal->current_proposal['install_addr_hash']);
			$obj = new $class($_SESSION['id_hash']);
			if ($loc_hash) {
				$obj->fetch_location_record($loc_hash);
				$install_loc = stripslashes($obj->current_location['location_name']) . "\n" .
				( $obj->current_location['location_street'] ?
					stripslashes($obj->current_location['location_street']) . "\n" : NULL
				) .
				stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

			} else {
				$obj->fetch_master_record($id);
				$install_loc = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
				) .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
			}
			unset($obj);
		}
		$direct_bill = 0;
		if ($invoice->current_invoice['direct_bill'])
			$direct_bill = 1;

        if ($invoice->current_invoice['currency']) {
            $this->db->define('convert_currency',1);
            $this->db->define('local_currency',$invoice->current_invoice['currency']);
            $this->db->define('local_exchange_rate',$invoice->current_invoice['exchange_rate']);
        }

		$lines = new line_items($invoice->proposal_hash);
		$item_cols = array("item_no"      => $this->template->current_template['item_no']['value'],
						   "item_descr"   => $this->template->current_template['item_descr']['value'],
		                   "item_qty"     => $this->template->current_template['qty']['value'],
						   "list"		  => $this->template->current_template['item_list']['value'],
						   "ext_list"     => $this->template->current_template['ext_list']['value']);
		if ($direct_bill) {
			$item_cols['comm'] = $this->template->current_template['item_comm']['value'];
			$item_cols['ext_sell'] = $this->template->current_template['ext_sell']['value'];
			$item_cols['ext_comm'] = $this->template->current_template['ext_comm']['value'];
		} else {
			$item_cols['sell'] = $this->template->current_template['item_sell']['value'];
			$item_cols['ext_sell'] = $this->template->current_template['ext_sell']['value'];
		}
		// Get print preferences for line items
		$line_no_print = in_array("line_no",$this->print_pref['item_print_fields']);
		$item_no_print = in_array("item_no",$this->print_pref['item_print_fields']);
		$zero_sell_print = in_array("zero_sell",$this->print_pref['item_print_fields']);

		// Create the item data array
        for ($i = 0; $i < $invoice->current_invoice['total_items']; $i++) {
            $lines->fetch_line_item_record($invoice->current_invoice['line_items'][$i]);
            $line_array[$i] = $lines->current_line;
        }

        $group_hash_array = array();
		$print_array = array();
		for ($i = 0; $i < $invoice->current_invoice['total_items']; $i++) {
			if ($direct_bill && $line_array[$i]['direct_bill_amt'] == 'S')
				$direct_bill_full_sell = 1;

			unset($disc);
			if (in_array('discounting',$this->print_pref['item_print_fields'])) {
					$disc = ($lines->line_info[$i]['discount1'] > 0 ?
									"\n".$this->template->current_template['discount']['value']." 1: ".($line_array[$i]['discount1'] * 100)."% \n" : NULL).($line_array[$i]['discount2'] > 0 ?
										$this->template->current_template['discount']['value']." 2: ".($line_array[$i]['discount2'] * 100)."% \n" : NULL).($line_array[$i]['discount3'] > 0 ?
											$this->template->current_template['discount']['value']." 3: ".($line_array[$i]['discount3'] * 100)."% \n" : NULL).($line_array[$i]['discount4'] > 0 ?
												$this->template->current_template['discount']['value']." 4: ".($line_array[$i]['discount4'] * 100)."% \n" : NULL).($line_array[$i]['discount5'] > 0 ?
													$this->template->current_template['discount']['value']." 5: ".($line_array[$i]['discount5'] * 100)."% \n" : NULL);
			}

			if ( in_array('list_discount', $this->print_pref['item_print_fields']) )
                $disc .=
                ( $disc && $line_array[$i]['gp_type'] == 'G' ?
    				"\n" : "\n\n"
                ) .
                "{$this->template->current_template['list_discount']['value']}: " .
                math::list_discount( array(
	                'list'  =>  $line_array[$i]['list'],
	                'sell'  =>  $line_array[$i]['sell']
                ) ) . "%";

			// Added for Trac #1274
			if (in_array('item_finish',$this->print_pref['item_print_fields'])) {
				$import_data = unserialize(stripslashes($line_array[$i]['import_data']));
				if (!$import_data)
					$import_data = unserialize($line_array[$i]['import_data']);
				unset($finishes);
				$finish =& $import_data['FINISH'];
				if (is_array($finish)) {
					$finishes .= "\n\n".$this->template->current_template['finishes']['value'].":\n";
					for ($j = 0; $j < count($finish); $j++) {
						unset($item_panel);
						$special_chars = array("â„¢","™");
						if ($finish[$j]['PANEL'])
							$item_panel = stripslashes($finish[$j]['PANEL']);
						else
							$finishes .= str_replace($special_chars,"",$finish[$j]['GROUP_DESCR'])." : ".stripslashes(str_replace($special_chars,"",$finish[$j]['DESCR']))." ".($finish[$j]['NAME'] ? "(".stripslashes(str_replace($special_chars,"",$finish[$j]['NAME'])).")" : NULL)."\n";

						if ($item_panel)
							$finishes .= "\n".$this->template->current_template['panel_detail']['value'].": ".$item_panel;

					}
				}
			}
            $sign = $line_array[0]['symbol'];
   			if (bccomp($line_array[$i]['ext_sell'],0) == 1 || (bccomp($line_array[$i]['ext_sell'],0) == 0 && $zero_sell_print) || bccomp($line_array[$i]['ext_sell'],0) == -1)
				$item_data[] = array("item_no" 									=> ($line_no_print ?
                                                                                        $this->template->current_template['line']['value'].": ".$line_array[$i]['invoice_line_no']."\n" : NULL).
				                                                                    ($item_no_print ?
																						$line_array[$i]['item_no'] : NULL),
									 "item_descr"								=> (in_array("hide_vendor",$this->print_pref['item_print_fields']) ? NULL : stripslashes($line_array[$i]['item_vendor'])).(in_array("product",$this->print_pref['item_print_fields']) ? (in_array("hide_vendor",$this->print_pref['item_print_fields']) ? NULL : " - ").$line_array[$i]['item_product'] : NULL).
									 												((in_array("hide_vendor",$this->print_pref['item_print_fields']) && !in_array("product",$this->print_pref['item_print_fields'])) ? NULL : "\n").
																				    stripslashes($line_array[$i]['item_descr']).$finishes.$disc,
									 "item_qty"									=> strrev(substr(strstr(strrev($line_array[$i]['qty']),'.'),1)).(str_replace('0','',substr(strrchr($line_array[$i]['qty'],'.'),1)) ?
																					str_replace('0','',strrchr($line_array[$i]['qty'],'.')) : NULL),
									 "list"										=> '$'.number_format($line_array[$i]['list'],2),
									 "ext_list"									=>	'$'.number_format($line_array[$i]['ext_list'],2),
									 "comm"										=> '$'.number_format($line_array[$i]['sell'] - $line_array[$i]['cost'],2),
									 ($direct_bill ? "ext_sell" : "sell")		=> ($direct_bill && $line_array[$i]['direct_bill_amt'] == 'S' ?
																					 '$'.number_format($line_array[$i]['ext_sell'],2) : (!$direct_bill ?
																						 '$'.number_format($line_array[$i]['sell'],2) : NULL)),
									 ($direct_bill ? "ext_comm" : "ext_sell")	=> ($direct_bill && $line_array[$i]['direct_bill_amt'] == 'C' ?
																					 '$'.number_format($line_array[$i]['ext_sell'] - $line_array[$i]['ext_cost'],2) : (!$direct_bill ?'$'.number_format($line_array[$i]['ext_sell'],2) : NULL)));

            if ($line_array[$i]['group_hash'])
                $group_totals[$line_array[$i]['group_hash']] += $line_array[$i]['ext_sell'];
			else
				$group_totals['misc_group'] += $line_array[$i]['ext_sell'];

            if (($line_array[$i]['group_hash'] != $line_array[$i+1]['group_hash'] && (in_array("group_total",$this->print_pref['gen_print_fields']) || in_array("group_name",$this->print_pref['gen_print_fields']) || in_array("group_break",$this->print_pref['gen_print_fields']))) || !$line_array[$i+1]) {

                if(in_array($line_array[$i]['group_hash'],$group_hash_array)){

                }else{
                    array_push($group_hash_array,$line_array[$i]['group_hash']);
                    if (in_array("group_total",$this->print_pref['gen_print_fields']) || in_array("group_name",$this->print_pref['gen_print_fields']) || in_array("group_break",$this->print_pref['gen_print_fields']) || $line_array[$i]['group_hash']){
                        $group_array[count($print_array)] = array('title'   =>  htmlspecialchars($line_array[$i]['group_descr']),
                            'total'   =>  $group_totals[$line_array[$i]['group_hash']]);
                    }
                }


                array_push($print_array,$item_data);
                unset($item_data);
            }
		}
		// Remove columns from printing according to preferences
		if (!$item_no_print && !$line_no_print)
			unset($item_cols['item_no']);
		if (!in_array("item_descr",$this->print_pref['item_print_fields']) && !$line_no_print)
			unset($item_cols['item_descr']);
		if (!in_array("item_qty",$this->print_pref['item_print_fields']))
			unset($item_cols['item_qty']);
		if (!in_array("list",$this->print_pref['item_print_fields']))
			unset($item_cols['list']);
		if (!in_array("ext_list",$this->print_pref['item_print_fields']))
			unset($item_cols['ext_list']);
		if (!in_array("sell",$this->print_pref['item_print_fields']))
			unset($item_cols['sell']);
		if (!in_array("ext_sell",$this->print_pref['item_print_fields']))
			unset($item_cols['ext_sell']);
		if ($direct_bill) {
			if (!$direct_bill_full_sell)
				unset($item_cols['ext_sell']);
			else
				unset($item_cols['comm']);
		}
        if (!$print_array[0] && $item_data)
            array_push($print_array,$item_data);

		// Show proposal comments based on preferences
		if (in_array("comments",$this->print_pref['gen_print_fields'])) {
			for ($i = 0; $i < count($lines->proposal_comments); $i++) {
				if ($lines->proposal_comments[$i]['comment_action'] == 2)
					$comment_data[] = array('comments' => htmlspecialchars($lines->proposal_comments[$i]['comments']));
			}
		}
		$headfoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$y_start = 825 - $this->h;
		$y = $this->pdf->y;

		if ($this->print_pref['print_logo'] && $this->img) {
			$this->pdf->ezImage($this->img,5,$this->w,'','left');
			$this->pdf->y = $y;
		}

		$y_start -= 30;
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(2,'round');
		$x_start = 40 + $this->w;
		$rightXpos = $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'];

		//Customer
		$this->pdf->line($x_start,762,$rightXpos,762);
		if ((40 + $this->w + $this->pdf->getTextWidth(8,$this->template->current_template['customer']['value'].": ".$proposal->current_proposal['customer'])) > ($rightXpos-$this->pdf->getTextWidth(20,$this->template->current_template['title']['value'])-10)) {
			$start_pos = ($rightXpos-$this->pdf->getTextWidth(20,$this->template->current_template['title']['value'])-10);

			$cust_name = $this->template->current_template['customer']['value'].": ".$proposal->current_proposal['customer'];
		}
		if ($direct_bill)
			$title = $this->template->current_template['direct_bill_title']['value'];
		$text_width = $this->pdf->getTextWidth(20,$title);
		$width_customer_name = 525.28-$text_width-$this->w;
		$this->pdf->addTextWrap(40 + $this->w,766,$width_customer_name,10,$this->template->current_template['customer']['value'].": ".stripslashes($customer_name));

		//Sales Rep
		$this->pdf->ezText($this->template->current_template['sales_rep']['value'].": ".$author,10,array('left' => $this->w + 10));

		//Invoice title
		$text_width = $this->pdf->getTextWidth(20,$title);
		$this->pdf->addText($rightXpos-$text_width-10,765,20,$title);
		//Invoice number
		$text_width = $this->pdf->getTextWidth(15,$invoice->current_invoice['invoice_no']);
		$font_height = $this->pdf->getFontHeight(15);
		$this->pdf->addText($rightXpos-$text_width-10,762 - $font_height,15,$invoice->current_invoice['invoice_no']);
		//Proposal no
		$text_width = $this->pdf->getTextWidth(10,$this->template->current_template['proposal']['value'].": ".$proposal->current_proposal['proposal_no']);
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText($rightXpos-$text_width-10,744 - $font_height,$this->template->current_template['proposal']['font_size'],$this->template->current_template['proposal']['value'].": ".$proposal->current_proposal['proposal_no']);
		//Invoice date
		$text_width = $this->pdf->getTextWidth($this->template->current_template['invoice_date']['font_size'],$this->template->current_template['invoice_date']['value'].": ".date(DATE_FORMAT,strtotime($invoice->current_invoice['invoice_date'])));
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText($rightXpos-$text_width-10,731 - $font_height,$this->template->current_template['invoice_date']['font_size'],$this->template->current_template['invoice_date']['value'].": ".date(DATE_FORMAT,strtotime($invoice->current_invoice['invoice_date'])));
		//Due Date
		$text_width = $this->pdf->getTextWidth(10,$this->template->current_template['due_date']['value'].": ".((is_numeric($customer_pay_days) && $customer_pay_days == 0) || (!is_numeric($customer_pay_days) && DEFAULT_CUST_PAY_TERMS == 0) ? $this->template->current_template['upon_receipt']['value'] : date(DATE_FORMAT,strtotime($invoice->current_invoice['invoice_date']." + ".(!$customer_pay_days ? DEFAULT_CUST_PAY_TERMS : $customer_pay_days)." days"))));
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText($rightXpos-$text_width-10,718 - $font_height,10,$this->template->current_template['due_date']['value'].": ".((is_numeric($customer_pay_days) && $customer_pay_days == 0) || (!is_numeric($customer_pay_days) && DEFAULT_CUST_PAY_TERMS == 0) ? $this->template->current_template['upon_receipt']['value'] : date(DATE_FORMAT,strtotime($invoice->current_invoice['invoice_date']." + ".(!$customer_pay_days ? DEFAULT_CUST_PAY_TERMS : $customer_pay_days)." days"))));

		$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->ez['bottomMargin'],$rightXpos,$this->pdf->ez['bottomMargin']);
		$this->pdf->ezSetDy(-35);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($headfoot,'all');
		$this->pdf->ezStartPageNumbers($rightXpos-15,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		// Adjust height to prevent overlapping due to short logos. Added for Madden intially. Trac #774
		if ($this->h < 60) {
			$this->h = 60;
		}

		$xpos = 0;
		$this->pdf->ez['topMargin'] = 30 + $this->h;
		$this->pdf->ezSetY($this->pdf->ez['pageHeight']-$this->pdf->ez['topMargin']);

		//Show the customer and install information
		$data = array(array("customer" => $customer_info, "install" => $install_loc));
		$cols = array("customer" => $this->template->current_template['customer']['value'].":", "install" => $this->template->current_template['install']['value'].":");
		//Make it work with the window envelope
		if ($this->pdf->y > 690)
			$this->pdf->ezSetY(690);

		$this->pdf->ezSetDy(5);
		$y_title = $this->pdf->y;
		$this->pdf->ezText($this->template->current_template['customer']['value'].":",12,array('left'	=>	15));
		$this->pdf->ezSetDy(-5);
		$y_content = $this->pdf->y;
		$y = $this->pdf->ezText($customer_info,12,array('left'	=>	15));

		if (in_array("install_addr",$this->print_pref['gen_print_fields'])) {
			$this->pdf->ezSetY($y_title);
			$this->pdf->ezText($this->template->current_template['install']['value'].":",12,array('aleft'	=>	400));
			$this->pdf->ezSetDy(-5);
			$y2 = $this->pdf->ezText($install_loc,12,array('aleft'	=>	400));
			if ($y > $y2) {
				$y = $y2;
			} else {
				$this->pdf->ezSetY($y);
			}
		}

		/*
		$options = array('showLines' 	=> 0,
						 'shaded'		=> 0,
						 'width'		=> $adjWidth,
						 'xPos'			=> $xpos,
						 'colGap'		=> 15,
						 'fontSize'		=> 12,
						 'xOrientation'	=> 'right');
		$y = $this->pdf->ezTable($data,$cols,NULL,$options);
		*/

		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,$y - 10,$rightXpos,$y - 10);

		$this->pdf->ezSetDy(-15);

		// Add proposal description for Trac #1191
		if (in_array("proposal_descr",$this->print_pref['gen_print_fields'])) {
			$cols = array("descr" => "Proposal Description:");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> 30,
							 'showHeadings'		=> 0,
							 'fontSize'			=> 12,
							 'titleFontSize'	=> 14,
							 'xOrientation' 	=> 'right',
							 'width'			=> $adjWidth,
							 'maxWidth'			=> $adjWidth);
			$proposal_descr[] = array("descr" => $proposal->current_proposal['proposal_descr']);
			$y = $this->pdf->ezTable($proposal_descr,$cols,NULL,$options);
			$this->pdf->ezSetDy(-5);

			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(1);
			$this->pdf->line($this->pdf->ez['leftMargin'],$y - 10,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$y - 10);
			$this->pdf->ezSetDy(-15);
		}

		if (is_array($comment_data)) {
			$cols = array("comments" => "Comments:");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> $this->pdf->ez['leftMargin'],
							 'showHeadings'		=> 0,
							 'fontSize'			=> $this->template->current_template['comments_data']['font_size'],
							 'titleFontSize'	=> $this->template->current_template['comments']['font_size'],
							 'xOrientation' 	=> 'right',
							 'width'			=> $adjWidth,
							 'maxWidth'			=> $adjWidth);
			$this->pdf->ezText($this->template->current_template['comments']['value'].':',$this->template->current_template['comments']['font_size']);
			$y = $this->pdf->ezTable($comment_data,$cols,'',$options);
		}

		$this->pdf->ezSetDy(-5);

		if (in_array("customer_po",$this->print_pref['gen_print_fields']) && $proposal->current_proposal['customer_po'])
			$this->pdf->ezText($this->template->current_template['customer_po']['value'].": ".$proposal->current_proposal['customer_po']);

		$this->pdf->ezSetDy(-5);
		$colWidth = ($adjWidth - 320) / 3; // Width of columns other than item_no and item_descr
		//Show the line items

		$item_options = array("width" 		=> $this->pdf->ez['pageWidth'] - $this->pdf->ez['leftMargin'] - $this->pdf->ez['rightMargin'],
							  "maxWidth"	=> $this->pdf->ez['pageWidth'] - $this->pdf->ez['leftMargin'] - $this->pdf->ez['rightMargin'],
							  'rowGap'		=>	4,
							  'showLines'	=>  2,
							  'lineCol'		=>	array(.8,.8,.8),
							  'shaded'		=>  0,
							  'shadeCol'	=>  array(.94,.94,.94),
							  "cols"		=> array("item_no" 		=>	array('justification'	=>	'left'),
													 "item_descr"	=>  array('justification'	=>	'left'),
													 "item_qty"		=>  array('justification'	=>	'right')
												)
							  );
		if ($direct_bill) {
			if (!$direct_bill_full_sell)
				$item_options['cols']['comm'] = array('justification' => 'right');
			else
				$item_options['cols']['ext_sell'] = array('justification' => 'right');

			$item_options['cols']['ext_comm'] = array('justification' => 'right');
		} else {
			$item_options['cols']['list'] = array('justification' => 'right');
			$item_options['cols']['ext_list'] = array('justification' => 'right');
			$item_options['cols']['sell'] = array('justification' => 'right');
			$item_options['cols']['ext_sell'] = array('justification' => 'right');
		}
		$totals_options = array('xPos'					=>	$rightXpos - 10,
								'width'					=>	315.28,
								'xOrientation'			=>	'left',
								'showLines'				=>  0,
								'lineCol'				=>  array(.8,.8,.8),
								'showHeadings'			=>  0,
								'shaded'				=>	0,
								'fontSize'				=>	12,
								'rowGap'				=>	3,
								'colGap'				=>  0,
								'cols'					=>	array('field'	=>	array('justification'	=>	'left',
																					  'width'			=>	250),
																  'val'		=>	array('justification'	=>	'right',
																					  'width'			=>	100.28)
																  )
								);

        if ($invoice->current_invoice['currency'] && $invoice->current_invoice['exchange_rate']) {
            $this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->getTextWidth(10,"Currency".$invoice->current_invoice['currency']." ".rtrim(trim($invoice->current_invoice['exchange_rate'], '0'), '.')."%") - 10,$this->pdf->y - 15,10,"Currency: ".$invoice->current_invoice['currency']." ".rtrim(trim($invoice->current_invoice['exchange_rate'], '0'), '.')."%");
            if ($this->print_pref['invoice_detail'])
				$this->pdf->ezSetDy(-20);
        }

        if (is_array($print_array)) {
            for ($i = 0; $i < count($print_array); $i++) {
            	if (!$this->print_pref['invoice_detail'] && (!is_numeric($group_array[$i]['total']) || ((is_numeric($group_array[$i]['total']) && $group_array[$i]['total'] != 0) || ($group_array[$i]['total'] == 0 && $zero_sell_print)))) {
                	$this->pdf->ezText(($group_array[$i]['title'] && in_array("group_name",$this->print_pref['gen_print_fields']) ? $group_array[$i]['title'].":" : NULL),12);
					$this->pdf->ezSetDy(-5);
            		$y = $this->pdf->ezTable($print_array[$i],$item_cols,'',$item_options);
            	}

                if ($group_array[$i]['title'] || $this->print_pref['invoice_detail']) {
                	$group_title = ($group_array[$i]['title'] ? $group_array[$i]['title'] : $this->template->current_template['misc_items']['value']);
					$group_total = (is_numeric($group_array[$i]['total']) ? $group_array[$i]['total'] : $group_totals['misc_group']);
                    if (in_array('group_total',$this->print_pref['gen_print_fields']) || $this->print_pref['invoice_detail']) {
                    	// Added for Trac #1325
						if (substr_count($group_title,"\n") > 0) {
							unset($newlines);
							for($j = 0; $j < substr_count($group_title,"\n"); $j++)
								$newlines .= "\n";
							$total_array = array(array('field'	=>	$this->template->current_template['group']['value']." ".$group_title." ".$this->template->current_template['total']['value'].': ',
						   							  'val' 	=> 	$newlines.$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($group_total,2)))).'$'.number_format($group_total,2)
						   						));
						} else {
							$total_array = array(array('field'	=>	$group_title.': '.$this->pdf->fill_void('.',(250 - $this->pdf->getTextWidth(12,$group_title.': '))),
												   		'val' 	=> 	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($group_total,2)))).'$'.number_format($group_total,2)
												   ));
						}

						if ((is_numeric($group_array[$i]['total']) && $group_array[$i]['total'] != 0) )
							$y = $this->pdf->ezTable($total_array,NULL,NULL,$totals_options);
                    }
                    if (in_array("group_break",$this->print_pref['gen_print_fields']))
                        $this->pdf->ezNewPage();
                    else
                        $this->pdf->ezSetDy(-10);
                } else
                    $this->pdf->ezSetDy(-5);
            }
        }
		$this->pdf->ezSetDy(-15);

		$totals_options['rowGap'] = 2.5;
		$totals_options['width'] = 215.28;
		$totals_options['cols']['field']['width'] = 170;

		if ($invoice->current_invoice['currency'] && $invoice->current_invoice['exchange_rate']) {
			$invoice->current_invoice['amount'] = currency_exchange($invoice->current_invoice['amount'],$invoice->current_invoice['exchange_rate']);
			$invoice->current_invoice['tax'] = currency_exchange($invoice->current_invoice['tax'],$invoice->current_invoice['exchange_rate']);
			$invoice->current_invoice['finance_charges'] = currency_exchange($invoice->current_invoice['finance_charges'],$invoice->current_invoice['exchange_rate']);
			$invoice->current_invoice['deposit_applied'] = currency_exchange($invoice->current_invoice['deposit_applied'],$invoice->current_invoice['exchange_rate']);
    	}
    	
		//Added to display multiple subtotals
		if (in_array("sub_total",$this->print_pref['gen_print_fields'])) {
			$total_sellSub = $invoice->current_invoice['amount'] - $invoice->current_invoice['tax'] - $invoice->current_invoice['finance_charges'];
			$subtotal_titles = array();
			$subtotal_values = array();
			for ($i = 0; $i < $invoice->current_invoice['total_items']; $i++) {
				$vendors = new vendors($_SESSION['id_hash']);
				$vendors->fetch_product_record($line_array[$i]['product_hash']);
				if($vendors->current_product['product_subtotal']){
					$total_sellSub = bcsub($total_sellSub,$line_array[$i]['ext_sell']);
					if(!in_array($vendors->current_product['subtotal_title'], $subtotal_titles)){
							$subtotal_titles[] =  $vendors->current_product['subtotal_title'];
							$subtotal_values[] =  $line_array[$i]['ext_sell'];
					}else{
							$key = array_search($vendors->current_product['subtotal_title'], $subtotal_titles);
							$temp_val = $subtotal_values[$key];
							$subtotal_values[$key] = $temp_val + $line_array[$i]['ext_sell'];
					}
				}
			}
		
			$invoice_total = $invoice->current_invoice['amount'];
			
			if($total_sellSub > 0){
					$totals_data[] = array('field'  	=>	$this->template->current_template['subtotal']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['subtotal']['value']))),
							'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$sign.number_format($total_sellSub,2)))).$sign.number_format($total_sellSub,2));
			}
					
			$s = 0;
			while($s < count($subtotal_titles) ){
				$totals_data[] = array('field'  	=>  $subtotal_titles[$s].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$subtotal_titles[$s]))),
						'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$sign.number_format($subtotal_values[$s],2)))).$sign.number_format($subtotal_values[$s],2));
				$s++;
			}
		
		}else{
			$invoice_total = $invoice->current_invoice['amount'];
			$totals_data[] = array('field'  =>	$this->template->current_template['subtotal']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['subtotal']['value']))),
							   'val'	=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($invoice->current_invoice['amount'] - $invoice->current_invoice['tax'] - $invoice->current_invoice['finance_charges'],2)))).'$'.number_format($invoice->current_invoice['amount'] - $invoice->current_invoice['tax'] - $invoice->current_invoice['finance_charges'],2));
		}

		if (bccomp($invoice->current_invoice['tax'],0) == 1) {
			if ($lines->tax_country == 'CA')
                list($total_tax,$tax_rules,$tax_local,$indiv_tax) = $lines->fetch_invoice_tax($invoice->invoice_hash);

            if ($lines->tax_country == 'CA' && $indiv_tax) {
	            while (list($tax_hash,$local_name) = each($tax_local))
	                $totals_data[] = array('field'  =>  $this->template->current_template['tax']['value'].' - '.strtoupper($local_name).$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['tax']['value'].' - '.strtoupper($local_name)))),
	                                       'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($tax_rules[$tax_hash],2)))).'$'.number_format($tax_rules[$tax_hash],2));

            } else
	            $totals_data[] = array('field'  =>  $this->template->current_template['tax']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['tax']['value']))),
	                                   'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($invoice->current_invoice['tax'],2)))).'$'.number_format($invoice->current_invoice['tax'],2));
		}
		if ($invoice->current_invoice['deposit_applied'] > 0) {
			$totals_data[] = array('field'  =>	$this->template->current_template['applied_deposit']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['applied_deposit']['value']))),
								   'val'	=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'($'.number_format($invoice->current_invoice['deposit_applied'],2).")"))).'($'.number_format($invoice->current_invoice['deposit_applied'],2).")");
            $invoice_total -= $invoice->current_invoice['deposit_applied'];
		}
		if ($invoice->current_invoice['finance_charges'] > 0)
            $totals_data[] = array('field'  =>  $this->template->current_template['finance_charges']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['finance_charges']['value']))),
                                   'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($invoice->current_invoice['finance_charges'],2)))).'$'.number_format($invoice->current_invoice['finance_charges'],2));

		$totals_data[] = array('field'  =>	$this->template->current_template['total_due']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['total_due']['value']))),
							   'val'	=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($invoice_total,2))))."$".number_format($invoice_total,2));

		if (defined('MULTI_CURRENCY') && $this->current_invoice['currency']) {
            $sys = new system_config();
            $currency = true;
		}

        $total_payments = 0;
        $total_credits = 0;

        $r = $this->db->query("SELECT t1.receipt_amount , t1.applied_from , t1.applied_type , t1.currency , t1.exchange_rate
                               FROM customer_payment t1
                               WHERE t1.invoice_hash = '".$invoice->invoice_hash."' AND t1.deleted = 0");
        while ($row = $this->db->fetch_assoc($r)) {
        	$receipt_amount = $row['receipt_amount'];
        	if ($currency && $row['currency'] && $row['currency'] != $sys->home_currency['code'])
        		$receipt_amount = currency_exchange($row['receipt_amount'],$row['exchange_rate']);

	        if ($row['applied_from'] && $row['applied_type'] == 'C')
	            $total_credits = bcadd($total_credits,$row['receipt_amount'],2);
	        else
	            $total_payments = bcadd($total_payments,$row['receipt_amount'],2);

        }
		if ($total_payments || $total_credits) {
            $balance_total = $invoice_total;

			if ($total_credits != 0) {
		        $totals_data[] = array('field'  =>  $this->template->current_template['applied_credit']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['applied_credit']['value']))),
		                               'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'($'.number_format($total_credits,2).")")))."($".number_format($total_credits,2).")");
	            $balance_total -= $total_credits;
			}
		    if ($total_payments != 0) {
	            $totals_data[] = array('field'  =>  $this->template->current_template['payment_received']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['payment_received']['value']))),
	                                   'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'($'.number_format($total_payments,2).")")))."($".number_format($total_payments,2).")");
		    	$balance_total -= $total_payments;
		    }
            $totals_data[] = array('field'  =>  $this->template->current_template['balance_due']['value'].$this->pdf->fill_void('.',(170 - $this->pdf->getTextWidth(12,$this->template->current_template['balance_due']['value']))),
                                   'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,($balance_total < 0 ? '($'.number_format($balance_total * -1,2).')' : '$'.number_format($balance_total,2))))).($balance_total < 0 ? '($'.number_format($balance_total * -1,2).')' : '$'.number_format($balance_total,2)));
		}

		// Pull footer message from print prefs if printed, or from system config if from invoice generation screen. For Trac #1307
		$footer_msg = ($_GET['fp'] ? $this->print_pref['footer_msg'] : fetch_sys_var('invoice_footer_message'));

		// Find out if the footer message is very long, or has multiple new lines that would cause it to push to the next page.
		$is_long_footer = ((strlen($footer_msg) > 315) || substr_count($footer_msg,"\n") >= 2);

		// Calculate if extra adjustments need to be factored in for long totals or remittance address. For #1305
		$remit_to_ex = explode("\n",$invoice->current_invoice['remit_to_addr']);
		if (count($remit_to_ex) > 4)
			$adj_down1 = ((count($remit_to_ex)) - 4) * 25;
		if (count($totals_data) > 4)
			$adj_down2 = ((count($totals_data)) - 4) * 30;
		$adj_down = ($adj_down1 > $adj_down2 ? $adj_down1 : $adj_down2);

		// Push pricing totals to next page if near bottom
		if ($this->pdf->y < ($is_long_footer ? (132 + $adj_down) : (112 + $adj_down))) {
			$this->pdf->ezNewPage();
			$new_page = true;
			$this->pdf->ezSetDy(-20);
		}

        // Display remit to address if it exists.  Added for trac ticket #307
        if (in_array("remit_to",$this->print_pref['gen_print_fields']) && $invoice->current_invoice['remit_to_addr']) {
            $this->pdf->addText($this->pdf->ez['leftMargin'],($this->pdf->y - 10),$this->template->current_template['please_remit']['font_size'],($this->template->current_template['please_remit']['value'] ? "<b>" : NULL).$this->template->current_template['please_remit']['value'].": ".($this->template->current_template['please_remit']['value'] ? "</b>" : NULL));
            $y_pos = ($this->pdf->y - 15);
            for ($i = 0; $i < count($remit_to_ex); $i++) {
                $y_pos -= 13;
            	$this->pdf->addText($this->pdf->ez['leftMargin'] + 10,$y_pos,$this->template->current_template['please_remit_data']['font_size'],$remit_to_ex[$i]);
            }
        }

		$y = $this->pdf->ezTable($totals_data,NULL,NULL,$totals_options);
		$this->pdf->ezSetDy(-10);

		$this->pdf->ezSetY($is_long_footer ? 102 : 72);

		// Print footer message
		if ($footer_msg)
			$y = $this->pdf->ezText(stripslashes($footer_msg));
	}

	function printable_check() {
		if (file_exists('include/config/check_stock.php')) {
			require_once('include/config/check_stock.php');
			$print_vars = print_vars();
			$check_stock_file = true;
		}

		$payables = new payables($_SESSION['id_hash']);
		$accounting = new accounting($_SESSION['id_hash']);
		$result = $this->db->query("SELECT t1.*
									FROM vendor_payment_print t1
									ORDER BY t1.check_no ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			$r = $this->db->query("SELECT t1.*
								   FROM check_register t1
								   WHERE t1.check_no = '".$row['check_no']."' AND t1.account_hash = '".$row['account_hash']."'");
			$row2 = $this->db->fetch_assoc($r);
			$check[] = $row2;

			$this->db->query("DELETE FROM `vendor_payment_print`
							  WHERE `obj_id` = ".$row['obj_id']);
		}
		for ($i = 0; $i < count($check); $i++) {
			$result = $this->db->query("SELECT t1.* , t2.invoice_no , t2.type as invoice_type ,
									    t2.invoice_date , t2.amount as invoice_amount ,
									    t2.po_hash AS invoice_po
										FROM vendor_payment t1
										LEFT JOIN vendor_payables t2 ON t2.invoice_hash = t1.invoice_hash
										WHERE t1.check_no = '".$check[$i]['check_no']."' AND t1.account_hash = '".$check[$i]['account_hash']."'");
			while ($row = $this->db->fetch_assoc($result))
				$invoices[$i][] = $row;
		}

		if (!$check)
			die('Invalid check data');

		// Start the PDF document ( Letter size x:612 y:792 )
		$this->pdf = new Cezpdf('LETTER');
		$title = "Check Run ".date(DATE_FORMAT);
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $author = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Author',$author);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);

		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$vendors = new vendors($_SESSION['id_hash']);
		$customers = new customers($_SESSION['id_hash']);
		$invoice_types = array('I'	=>	'Bill',
							   'R'	=>	'Refund',
							   'D'	=>	'Deposit');

		$this->db->define('convert_currency',1);
		for ($j = 0; $j < count($check); $j++) {
			$k++;
			if (!$check_stock_file && !$print_vars)
				$print_vars = $accounting->check_stock_coords($check[$j]['account_hash']);

			$accounting->fetch_check_detail($check[$j]['check_no'],$check[$j]['account_hash']);

			$invoice_info_left = array();
			$invoice_info_right = array();
			$check_total = 0;
			$max_credits_to_show = 5;
			if ($invoices[$j]) {
				for ($i = 0; $i < count($invoices[$j]); $i++) {
					unset($credit_no);
					$payables->fetch_invoice_record($invoices[$j][$i]['invoice_hash']);
					$payables->fetch_payment_record($invoices[$j][$i]['payment_hash']);
					$credits = ($payables->current_payment['credit_used'] > 0 ? '($'.number_format($payables->current_payment['credit_used'],2).')' : NULL);

					// Print vendor credit numbers on the check. Added for #378
					if ($payables->current_payment['credit_used'] > 0) {
						$r = $this->db->query("SELECT vp.invoice_no, vca.amount FROM vendor_credit_applied vca
											   LEFT JOIN vendor_payables vp ON vp.invoice_hash = vca.invoice_hash
											   WHERE vca.payment_hash = '".$invoices[$j][$i]['payment_hash']."'");

						// Only display credit number if single credit, otherwise print number of credits used
						$num_rows = $this->db->num_rows($r);
						while($row = $this->db->fetch_assoc($r)) {
							if ($num_rows == 1)
								$credit_no = $row['invoice_no'];
							else
								$credit_no = "$num_rows credits";
						}
					}
					// Update date format to short month version if long month is printing by default. Added for Trac #378
					$invoice_info_left[] = array('invoice_date' 	=> date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($payables->current_invoice['invoice_date'])),
												 'type'				=> $invoice_types[$payables->current_invoice['type']],
												 'invoice_no' 		=> $payables->current_invoice['invoice_no'],
												 'invoice_amount'	=> '$'.number_format($payables->current_invoice['amount'],2),
												 'discounts'		=> ($payables->current_payment['discount_taken'] > 0 ? '($'.number_format($payables->current_payment['discount_taken'],2).')' : NULL),
												 'deposits'			=> ($payables->current_payment['deposit_used'] > 0 ? '($'.number_format($payables->current_payment['deposit_used'],2).')' : NULL),
												 'credits'			=>  $credits,
												 'credit_no'		=>	$credit_no,
										 		 'payment'			=> '$'.number_format($payables->current_payment['payment_amount'],2));


				}
			} else
				$check_total = $accounting->current_check['amount'];

			$payable_to = ($accounting->current_check['vendor_name'] ? $accounting->current_check['vendor_name'] : $accounting->current_check['customer_name']);

			$check_total = $accounting->current_check['amount'];
			$check_no = $accounting->current_check['check_no'];
			$check_date = $accounting->current_check['check_date'];
			$remit_to = stripslashes($accounting->current_check['remit_to']);
			$remit_to = explode("\n",$remit_to);

			$memo = stripslashes($accounting->current_check['memo']);

			//check no
			$this->pdf->addText($print_vars['check_no']['x'],$print_vars['check_no']['y'],10,$check_no);
			//pay to the order of
			$this->pdf->addText($print_vars['payto']['x'],$print_vars['payto']['y'],9,htmlspecialchars(stripslashes($payable_to)));
			$y = $print_vars['remit_to']['y'] - $this->pdf->getFontHeight(9) - $this->pdf->getFontHeight(9);
			for ($i = 0; $i < count($remit_to); $i++) {
				if (trim($remit_to[$i])) {
					$this->pdf->addText($print_vars['remit_to']['x'],$y,9,htmlspecialchars(stripslashes($remit_to[$i])));
					$y -= $this->pdf->getFontHeight(9) + 3;
				}
			}
			//check total text
			$this->pdf->addText($print_vars['check_total']['x'],$print_vars['check_total']['y'],9,htmlspecialchars(numbertotext($check_total,1,1,0,currency_phrase($accounting->current_check['currency']))).$this->pdf->fill_void('*',(377 - $this->pdf->getTextWidth(9,htmlspecialchars(numbertotext($check_total,1,1,0,currency_phrase($accounting->current_check['currency'])))))));
			//check total int
			$this->pdf->addText($print_vars['check_total_int']['x'],$print_vars['check_total_int']['y'],10,$accounting->current_check['symbol'].number_format($check_total,2));
			//Check Date
			$this->pdf->addText($print_vars['check_date']['x'],$print_vars['check_date']['y'],10,date("m/d/Y",strtotime($check_date)));
			//memo
			$this->pdf->addText($print_vars['memo']['x'],$print_vars['memo']['y'],10,htmlspecialchars($memo));

			//Print the coupons
			$this->pdf->ezSetY($print_vars['coupon1']['y']);
			$cols = array("invoice_date" 	=> "Date",
						  "type"			=> "Type",
						  "invoice_no" 		=> "Reference",
						  "invoice_amount"	=> "Amount",
						  "discounts" 		=> "Discounts",
						  "deposits"		=> "Deposits",
						  "credits"			=> "Credits",
						  "credit_no"		=> "Credit No.",
						  "payment" 		=> "Payment");
			$options = array('width'		=> 590,
							 'maxWidth'		=> 590,
							 'fontSize'		=> 7,
							 'titleFontSize'=> 8,
							 'xPos'			=> 15,
							 'xOrientation'	=> 'right',
							 'showLines'	=>  0,
							 'shaded'		=>	0,
							 'rowGap'		=>	1,
							 "cols"			=> array("invoice_no" 		=>	array('justification'	=>	'left',
																				  'width'			=>	65),
													 "invoice_date"		=>  array('justification'	=>	'left',
																				  'width'			=>	65),
													 "invoice_amount"	=>  array('justification'	=>	'right',
																				  'width'			=>	70),
													 "discounts"		=>	array('justification'	=>	'right',
																				  'width'			=>	65),
													 "deposits"			=>	array('justification'	=>	'right',
																				  'width'			=>	65),
													 "credits"			=>	array('justification'	=>	'right',
																				  'width'			=>	65),
													 "credit_no"		=>	array('justification'	=>	'right',
																				  'width'			=>	65),
													 "payment"			=>	array('justification'	=>	'right',
																				  'width'			=>	65),
													 "type"				=>	array('justification'	=>	'left',
																				  'width'			=>	50))
							);
			if ($invoice_info_left)
				$this->pdf->ezTable($invoice_info_left,$cols,NULL,$options);

			$payee_coupon = date("n/j/Y",strtotime($check_date))."   ".htmlspecialchars($payable_to)."   \$".number_format($check_total,2);
			$payee_start_x = $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->getTextWidth(10,$payee_coupon);
			$this->pdf->addText(15,$print_vars['coupon1']['y'] - 230,10,($accounting->current_check['account_no'] ? $accounting->current_check['account_no']." - " : NULL).$accounting->current_check['account_name']);
            $this->pdf->addText($payee_start_x,$print_vars['coupon1']['y'] - 230,10,$payee_coupon);

			//2nd coupon
			$this->pdf->ezSetY($print_vars['coupon2']['y']);
			$options['xPos'] = 15;
			if ($invoice_info_left)
				$this->pdf->ezTable($invoice_info_left,$cols,NULL,$options);

			$this->pdf->addText(15,$print_vars['coupon2']['y'] - 240,10,($accounting->current_check['account_no'] ? $accounting->current_check['account_no']." - " : NULL).$accounting->current_check['account_name']);
            $this->pdf->addText($payee_start_x,$print_vars['coupon2']['y'] - 240,10,$payee_coupon);

            if ($k < count($check))
				$this->pdf->ezNewPage();
		}
	}

	function balance_sheet($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['from_report'] = 1;
		$_POST['doit_print'] = 1;
		$reports->doit_balance_sheet();

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Balance Sheet Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Balance Sheet",16,array('justification' => 'center'));

		$report_title = "Through ".date(DATE_FORMAT,strtotime($reports->report_date));
		if ($reports->compare == 1) {
			$report_title .= " With Comparision Against Previous Cycle";
		} elseif ($reports->compare > 1) {
			$report_title .= " With Comparision Against Previous ".$reports->compare." Cycles";
		}
		$this->pdf->ezText($report_title,12,array('justification' => 'center', 'leading' => 15));
		$y_head = $this->pdf->y;
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 45,45,30,30);

		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		//Expiration Date
		$this->pdf->addText(30,20,8,"Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$item_cols = array("account_name"	=>	"Account",
						   "amount"			=>	"Amount");
		$account_detail = array(array(array()));
		$accounts = array('CA' => array(),
						  'FA' => array(),
						  'CL' => array(),
						  'LL' => array(),
						  'EQ' => array());
		$acct = new accounting($this->current_hash);

		$result = $this->db->query("SELECT accounts.account_name , accounts.account_no , journal.account_hash , account_types.type_code ,
									SUM(ROUND(journal.debit,2)) AS debit_total , SUM(ROUND(journal.credit,2)) AS credit_total
									FROM `journal`
									LEFT JOIN `accounts` ON accounts.account_hash = journal.account_hash
									LEFT JOIN `account_types` ON account_types.type_code = accounts.account_type
									WHERE account_types.account_side = 'B' ".($reports->current_report['sql'] ?
										stripslashes($reports->current_report['sql']) : NULL)."
									GROUP BY journal.account_hash");
		while ($row = $this->db->fetch_assoc($result)) {
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
				$result = $this->db->query("SELECT accounts.account_name , accounts.account_no , journal.account_hash , account_types.type_code ,
											SUM(ROUND(journal.debit,2)) AS debit_total , SUM(ROUND(journal.credit,2)) AS credit_total
											FROM `journal`
											LEFT JOIN `accounts` ON accounts.account_hash = journal.account_hash
											LEFT JOIN `account_types` ON account_types.type_code = accounts.account_type
											WHERE account_types.account_side = 'B' AND ".$sql."
											GROUP BY journal.account_hash");
				while ($row = $this->db->fetch_assoc($result)) {
					$acct->fetch_account_record($row['account_hash']);
					if ($acct->current_account['account_action'] == 'DR')
						$total = round($row['debit_total'] - $row['credit_total'],2);
					elseif ($acct->current_account['account_action'] == 'CR')
						$total = round($row['credit_total'] - $row['debit_total'],2);

					$account_detail[$acct->account_hash][$i]['total'] = $total;
				}
			}
		}

		//Display Account Data
		$cols = array("account"		=>		"Account",
					  "cur"			=>		"Total");
		$width = 350;
		if ($reports->compare) {
			$col_x[0] = 250;
			switch ($reports->compare) {
				case 1:
					$cols['comp1'] = "Compare 1";
					$width = (350 / 2);
					break;
				case 2:
					$cols['comp1'] = "Compare 1";
					$cols['comp2'] = "Compare 2";
					$width = (350 / 3);
					break;
				case 3:
					$cols['comp1'] = "Compare 1";
					$cols['comp2'] = "Compare 2";
					$cols['comp3'] = "Compare 3";
					$width = (350 / 4);
					break;
			}
			for ($i = 1; $i <= $reports->compare; $i++)
				$col_x[$i] = $col_x[$i-1] + 80;
		} else
			$col_x[0] = 318;
		$options_cols = array("account" 	=>	array('width' => 185.28, 'justification'	=>	'left'),
								"cur"		=>  array('width' => $width,    'justification'	=>	'right'),
								"comp1"		=>	array('width' => $width,    'justification'	=>	'right'),
								"comp2"		=>	array('width' => $width,    'justification'	=>	'right'),
								"comp3"		=>	array('width' => $width,    'justification'	=>	'right')
						);
		$options = array('fontSize'			=> 10,
						 'width'			=> $this->pdf->ez['pageWidth'] - ($this->pdf->ez['leftMargin'] + $this->pdf->ez['rightMargin']),
						 'xPos'				=> $this->pdf->ez['leftMargin'],
						 'xOrientation'		=> 'right',
						 'showLines'		=>  2,
						 'lineCol'			=>	array(.8,.8,.8),
						 'shaded'			=>  0,
						 'showHeadings' 	=>  0,
						 'rowGap'			=>	5,
						 'colGap'			=>	5,
						 'titleFontSize'	=>	10,
						 'shadeCol'			=>  array(.94,.94,.94),
						 "cols"				=>  $options_cols
						);
		$options_totals = array('fontSize'			=> 12,
						 		'width'				=> $this->pdf->ez['pageWidth'] - ($this->pdf->ez['leftMargin'] + $this->pdf->ez['rightMargin']),
						 		'xPos'				=> $this->pdf->ez['leftMargin'],
								'xOrientation'		=> 'right',
								'showLines'			=>  2,
								'lineCol'			=>	array(.8,.8,.8),
								'shaded'			=>  2,
								'showHeadings' 		=>  0,
								'rowGap'			=>	5,
								'colGap'			=>	5,
								'titleFontSize'		=>	10,
								'shadeCol'			=>  array(.8,.8,.8),
								'shadeCol2'			=>	array(.8,.8,.8),
						 		'cols'				=>  $options_cols
						);
		$this->pdf->y = $y_head - 10;

		$this->pdf->ezText("<b>Assets</b>",13);
		if ($reports->compare > 1) {
			$this->pdf->addText($col_x[0],$this->pdf->y,11,date("Y-m-d",strtotime($reports->report_date)));
			for ($i = 0; $i < $reports->compare; $i++)
				$this->pdf->addText($col_x[$i+1],$this->pdf->y,11,date("Y-m-d",strtotime($compare_date[$i])));
		}

		$this->pdf->ezText("Current Assets",11,array('left' => 10, 'spacing' => 1.5));
		$ca =& $accounts['CA'];
		$total = array();
		while (list($account_hash,$info) = each($ca)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$this->pdf->ezText($info['account_name'].$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 20 + $this->pdf->getTextWidth(10,$info['account_name']))),10),10,array('left' => 20,'spacing' => 1.5));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$this->pdf->addText($col_x[$i],$this->pdf->y,10,$this->format_dollars_show_zero($account_detail[$account_hash][$i]['total']));
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_assets[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
		}
		$this->pdf->ezText("Total Current Assets".$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 10 + $this->pdf->getTextWidth(11,"Total Current Assets"))),11),11,array('left' => 10, 'spacing' => 1.5));
		for ($i = 0; $i <= $reports->compare; $i++)
			$this->pdf->addText($col_x[$i],$this->pdf->y,11,$this->format_dollars_show_zero($total[$i]));

		$this->pdf->ezText("Long Term Assets",11,array('left' => 10, 'spacing' => 1.5));
		$fa =& $accounts['FA'];
		$total = array();
		while (list($account_hash,$info) = each($fa)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$this->pdf->ezText($info['account_name'].$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 20 + $this->pdf->getTextWidth(10,$info['account_name']))),10),10,array('left' => 20,'spacing' => 1.5));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$this->pdf->addText($col_x[$i],$this->pdf->y,10,$this->format_dollars_show_zero($account_detail[$account_hash][$i]['total']));
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_assets[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
		}
		$this->pdf->ezText("Total Long Term Assets".$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 10 + $this->pdf->getTextWidth(11,"Total Long Term Assets"))),11),11,array('left' => 10, 'spacing' => 1.5));
		for ($i = 0; $i <= $reports->compare; $i++)
			$this->pdf->addText($col_x[$i],$this->pdf->y,11,$this->format_dollars_show_zero($total[$i]));

		$this->pdf->ezText("<b>Total Assets</b>".$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + $this->pdf->getTextWidth(12,"Total Assets"))),12),12,array('spacing' => 1.5));
		for ($i = 0; $i <= $reports->compare; $i++)
			$this->pdf->addText($col_x[$i],$this->pdf->y,12,"<b>".$this->format_dollars_show_zero($total_assets[$i])."</b>");

		$this->pdf->ezText("<b>Liabilities and Equity</b>",13,array('spacing' => 2));

		$this->pdf->ezText("Current Liabilities",11,array('left' => 10, 'spacing' => 1.5));
		$cl =& $accounts['CL'];
		$total = array();
		while (list($account_hash,$info) = each($cl)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$this->pdf->ezText($info['account_name'].$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 20 + $this->pdf->getTextWidth(10,$info['account_name']))),10),10,array('left' => 20,'spacing' => 1.5));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$this->pdf->addText($col_x[$i],$this->pdf->y,10,$this->format_dollars_show_zero($account_detail[$account_hash][$i]['total']));
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_liab[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
		}
		$this->pdf->ezText("Total Current Liabilities".$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 10 + $this->pdf->getTextWidth(11,"Total Current Liabilities"))),11),11,array('left' => 10, 'spacing' => 1.5));
		for ($i = 0; $i <= $reports->compare; $i++)
			$this->pdf->addText($col_x[$i],$this->pdf->y,11,$this->format_dollars_show_zero($total[$i]));

		$this->pdf->ezText("Long Term Liabilities",11,array('left' => 10, 'spacing' => 1.5));
		$ll =& $accounts['LL'];
		$total = array();
		while (list($account_hash,$info) = each($ll)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$this->pdf->ezText($info['account_name'].$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 20 + $this->pdf->getTextWidth(10,$info['account_name']))),10),10,array('left' => 20,'spacing' => 1.5));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$this->pdf->addText($col_x[$i],$this->pdf->y,10,$this->format_dollars_show_zero($account_detail[$account_hash][$i]['total']));
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_liab[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
		}

		$this->pdf->ezText("Total Long Term Liabilities".$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 10 + $this->pdf->getTextWidth(11,"Total Long Term Liabilities"))),11),11,array('left' => 10, 'spacing' => 1.5));
		for ($i = 0; $i <= $reports->compare; $i++)
			$this->pdf->addText($col_x[$i],$this->pdf->y,11,$this->format_dollars_show_zero($total[$i]));

		$this->pdf->ezText("<b>Total Liabilities</b>".$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + $this->pdf->getTextWidth(12,"Total Liabilities"))),12),12,array('spacing' => 1.5));
		for ($i = 0; $i <= $reports->compare; $i++)
			$this->pdf->addText($col_x[$i],$this->pdf->y,12,"<b>".$this->format_dollars_show_zero($total_liab[$i])."</b>");

		$this->pdf->ezText("Shareholder's Equity",11,array('left' => 10, 'spacing' => 1.5));
		$eq =& $accounts['EQ'];
		$total = array();
		while (list($account_hash,$info) = each($eq)) {
			unset($activity);
			for ($i = 0; $i <= $reports->compare; $i++) {
			 	if ($account_detail[$account_hash][$i]['total'] != 0)
					$activity = 1;
			}
			if ($activity || (!$activity && !$reports->current_report['hide_empty'])) {
				$this->pdf->ezText($info['account_name'].$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 20 + $this->pdf->getTextWidth(10,$info['account_name']))),10),10,array('left' => 20,'spacing' => 1.5));
				for ($i = 0; $i <= $reports->compare; $i++) {
					$this->pdf->addText($col_x[$i],$this->pdf->y,10,$this->format_dollars_show_zero($account_detail[$account_hash][$i]['total']));
					$total[$i] += $account_detail[$account_hash][$i]['total'];
					$total_eq[$i] += $account_detail[$account_hash][$i]['total'];
				}
			}
		}
		$this->pdf->ezText("<b>Total Shareholder's Equity</b>".$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + 12 + $this->pdf->getTextWidth(12,"Total Shareholder's Equity"))),12),12,array('spacing' => 1.5));
		for ($i = 0; $i <= $reports->compare; $i++)
			$this->pdf->addText($col_x[$i],$this->pdf->y,12,"<b>".$this->format_dollars_show_zero($total_eq[$i])."</b>");

		$this->pdf->ezText("<b>Total Liabilities and Equity</b>".$this->pdf->fill_void('.',($col_x[0] - ($this->pdf->ez['leftMargin'] + $this->pdf->getTextWidth(13,"<b>Total Liabilities and Equity</b>"))),13),13,array('spacing' => 2));
		for ($i = 0; $i <= $reports->compare; $i++)
			$this->pdf->addText($col_x[$i],$this->pdf->y,13,"<b>".$this->format_dollars_show_zero($total_liab[$i] + $total_eq[$i])."</b>");
		/*
		$assets_title = array(array('account'	=> "<b>Assets</b>",
									'cur' 		=> "<b>".date(DATE_FORMAT,strtotime($reports->report_date))."</b>",
									'comp1'		=> "<b>".date(DATE_FORMAT,strtotime($compare_date[0]))."</b>",
									'comp2'		=> "<b>".date(DATE_FORMAT,strtotime($compare_date[1]))."</b>",
									'comp3'		=> "<b>".date(DATE_FORMAT,strtotime($compare_date[2]))."</b>"));
		$this->pdf->ezTable($assets_title, $cols,"",$options);
		$this->pdf->ezSetDy(-5);

		//Current Assets
		$ca =& $accounts['CA'];
		$total_ca = array("account" => 0, "cur" => 0, "comp1" => 0, "comp2" => 0, "comp3" => 0);
		$detail = array();
		while (list($account_hash,$info) = each($fa)) {
			$total_fa['cur'] += $this->format_dollars_show_zero($account_detail[$account_hash][0]['total']);
			$total_fa['comp1'] += $this->format_dollars_show_zero($account_detail[$account_hash][1]['total']);
			$total_fa['comp2'] += $this->format_dollars_show_zero($account_detail[$account_hash][2]['total']);
			$total_fa['comp3'] += $this->format_dollars_show_zero($account_detail[$account_hash][3]['total']);
			$detail[] = array('account'		=>	"  ".$info['account_name'],
							  'cur'			=>	$this->format_dollars_show_zero($account_detail[$account_hash][0]['total']),
							  'comp1'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][1]['total']),
							  'comp2'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][2]['total']),
							  'comp3'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][3]['total']));
		}
		$detail[] = array('account' => "<b>  Total Long Term Assets</b>",
						  'cur'		=> "<b>".$this->format_dollars_show_zero($total_fa['cur'])."</b>",
						  'comp1'	=> "<b>".$this->format_dollars_show_zero($total_fa['comp1'])."</b>",
						  'comp2'	=> "<b>".$this->format_dollars_show_zero($total_fa['comp2'])."</b>",
						  'comp3'	=> "<b>".$this->format_dollars_show_zero($total_fa['comp3'])."</b>");
		$this->pdf->ezTable($detail,$cols,"Long Term Assets",$options);

		//Total Assets
		$total_assets = array(  "account"	=> "<b>Total Assets</b>",
								"cur" 		=>  "<b>".$this->format_dollars_show_zero($total_ca['cur'] + $total_fa['cur'])."</b>",
								"comp1" 	=> "<b>".$this->format_dollars_show_zero($total_ca['comp1'] + $total_fa['comp1'])."</b>",
								"comp2" 	=> "<b>".$this->format_dollars_show_zero($total_ca['comp2'] + $total_fa['comp2'])."</b>",
								"comp3" 	=> "<b>".$this->format_dollars_show_zero($total_ca['comp3'] + $total_fa['comp3'])."</b>");
		$total_assets_data = array(array(),$total_assets);
		$this->pdf->ezTable($total_assets_data,$cols,"",$options_totals);

		//Current Liabilities
		$this->pdf->ezSetDy(-15);
		$liab_title = array(array(  'account'	=> "<b>Liabilities</b>",
									'cur' 		=> "<b>".date(DATE_FORMAT,strtotime($reports->report_date))."</b>",
									'comp1'		=> "<b>".date(DATE_FORMAT,strtotime($compare_date[0]))."</b>",
									'comp2'		=> "<b>".date(DATE_FORMAT,strtotime($compare_date[1]))."</b>",
									'comp3'		=> "<b>".date(DATE_FORMAT,strtotime($compare_date[2]))."</b>"));
		$this->pdf->ezTable($liab_title, $cols,"",$options);
		$this->pdf->ezSetDy(-5);

		$cl =& $accounts['CL'];
		$total_cl = array("cur" => 0, "comp1" => 0, "comp2" => 0, "comp3" => 0);
		$detail = array();
		while (list($account_hash,$info) = each($cl)) {
			$total_cl['cur'] += $account_detail[$account_hash][0]['total'];
			$total_cl['comp1'] += $account_detail[$account_hash][1]['total'];
			$total_cl['comp2'] += $account_detail[$account_hash][2]['total'];
			$total_cl['comp3'] += $account_detail[$account_hash][3]['total'];
			$detail[] = array('account'		=>	"  ".$info['account_name'],
							  'cur'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][0]['total']),
							  'comp1'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][1]['total']),
							  'comp2'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][2]['total']),
							  'comp3'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][3]['total']));
		}
		$detail[] = array('account' => "<b>  Total Current Liabilities</b>",
						  'cur'		=> "<b>".$this->format_dollars_show_zero($total_cl['cur'])."</b>",
						  'comp1'	=> "<b>".$this->format_dollars_show_zero($total_cl['comp1'])."</b>",
						  'comp2'	=> "<b>".$this->format_dollars_show_zero($total_cl['comp2'])."</b>",
						  'comp3'	=> "<b>".$this->format_dollars_show_zero($total_cl['comp3'])."</b>");
		$this->pdf->ezTable($detail,$cols,"Current Liabilities",$options);
		$this->pdf->ezSetDy(-5);

		//Long Term Liabilities:
		$ll =& $accounts['LL'];
		$total_ll = array("cur" => 0, "comp1" => 0, "comp2" => 0, "comp3" => 0);
		$detail = array();
		while (list($account_hash,$info) = each($ll)) {
			$total_ll['cur'] += $account_detail[$account_hash][0]['total'];
			$total_ll['comp1'] += $account_detail[$account_hash][1]['total'];
			$total_ll['comp2'] += $account_detail[$account_hash][2]['total'];
			$total_ll['comp3'] += $account_detail[$account_hash][3]['total'];
			$detail[] = array('account'		=>	"  ".$info['account_name'],
							  'cur'			=>	$this->format_dollars_show_zero($account_detail[$account_hash][0]['total']),
							  'comp1'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][1]['total']),
							  'comp2'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][2]['total']),
							  'comp3'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][3]['total']));
		}
		$detail[] = array('account' => "<b>  Total Long Term Liabilities</b>",
						  'cur'		=> "<b>".$this->format_dollars_show_zero($total_ll['cur'])."</b>",
						  'comp1'	=> "<b>".$this->format_dollars_show_zero($total_ll['comp1'])."</b>",
						  'comp2'	=> "<b>".$this->format_dollars_show_zero($total_ll['comp2'])."</b>",
						  'comp3'	=> "<b>".$this->format_dollars_show_zero($total_ll['comp3'])."</b>");
		$this->pdf->ezTable($detail,$cols,"Long Term Liabilities",$options);

		//Total Liabilities
		$total_liab = $total_cl + $total_ll;
		$total_liab = array(    "account"	=> "<b>Total Liabilities</b>",
								"cur" 		=> "<b>".$this->format_dollars_show_zero($total_cl['cur'] + $total_ll['cur'])."</b>",
								"comp1" 	=> "<b>".$this->format_dollars_show_zero($total_cl['comp1'] + $total_ll['comp1'])."</b>",
								"comp2" 	=> "<b>".$this->format_dollars_show_zero($total_cl['comp2'] + $total_ll['comp2'])."</b>",
								"comp3" 	=> "<b>".$this->format_dollars_show_zero($total_cl['comp3'] + $total_ll['comp3'])."</b>");
		$total_liab_data = array(array(),$total_liab);
		$this->pdf->ezTable($total_liab_data,$cols,"",$options_totals);
		$this->pdf->ezSetDy(-5);

		//Equity
		$eq =& $accounts['EQ'];
		$total_eq = array("cur" => 0, "comp1" => 0, "comp2" => 0, "comp3" => 0);
		$detail = array();
		while (list($account_hash,$info) = each($eq)) {
			$total_eq['cur'] += $account_detail[$account_hash][0]['total'];
			$total_eq['comp1'] += $account_detail[$account_hash][1]['total'];
			$total_eq['comp2'] += $account_detail[$account_hash][2]['total'];
			$total_eq['comp3'] += $account_detail[$account_hash][3]['total'];
			$detail[] = array('account'		=>	"  ".$info['account_name'],
							  'cur'			=>	$this->format_dollars_show_zero($account_detail[$account_hash][0]['total']),
							  'comp1'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][1]['total']),
							  'comp2'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][2]['total']),
							  'comp3'		=>	$this->format_dollars_show_zero($account_detail[$account_hash][3]['total']));
		}

		$this->pdf->ezTable($detail,$cols,"Shareholder's Equity",$options);

		//Total Equity
		$total_equity = array(  "account"	=> "<b>Total Shareholder's Equity</b>",
								"cur" 		=> "<b>".$this->format_dollars_show_zero($total_eq['cur'])."</b>",
								"comp1" 	=> "<b>".$this->format_dollars_show_zero($total_eq['comp1'])."</b>",
								"comp2" 	=> "<b>".$this->format_dollars_show_zero($total_eq['comp2'])."</b>",
								"comp3" 	=> "<b>".$this->format_dollars_show_zero($total_eq['comp3'])."</b>");
		$total_equity_data = array(array(),$total_equity);
		$this->pdf->ezTable($total_equity_data,$cols,"",$options_totals);
		$this->pdf->ezSetDy(-2);

		//Total Equity and Liabilities
		$total_equity_liab = array( "account"	=> "<b>Total Liabilities and Equity</b>",
									"cur" 		=> "<b>".$this->format_dollars_show_zero($total_eq['cur'] + $total_cl['cur'] + $total_ll['cur'])."</b>",
									"comp1" 	=> "<b>".$this->format_dollars_show_zero($total_eq['comp1'] + $total_cl['comp1'] + $total_ll['comp1'])."</b>",
									"comp2" 	=> "<b>".$this->format_dollars_show_zero($total_eq['comp2'] + $total_cl['comp2'] + $total_ll['comp2'])."</b>",
									"comp3" 	=> "<b>".$this->format_dollars_show_zero($total_eq['comp3'] + $total_cl['comp3'] + $total_ll['comp3'])."</b>");
		$total_equity_liab_data = array(array(),$total_equity_liab);
		$this->pdf->ezTable($total_equity_liab_data,$cols,"",$options_totals);
		*/
		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'],$line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$line_y);
	}

	function work_order($proposal_hash,$order_hash) {
		$proposal = new proposals($_SESSION['id_hash']);
		$valid2 = $proposal->fetch_master_record($proposal_hash);
		if (!$valid2)
			die('Invalid proposal hash.');

		$pm = new pm_module($proposal_hash);
		$valid = $pm->fetch_work_order($order_hash);
		if (!$valid)
			die('Invalid work order hash.');

		$lines = new line_items($proposal_hash);
		$lines->fetch_line_item_record($pm->current_order['line_item_hash']);

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();
		$title = "Work Order ".$pm->current_order['order_no'];
		$author = $proposal->current_proposal['sales_rep'];
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Author',$author);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);

		$this->headfoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$y_start = 825 - $this->h;
		$y = $this->pdf->y;
		$this->pdf->ezImage($this->img,5,$this->w,'','left');
		$this->pdf->y = $y;
		$y_start -= 25;
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(2,'round');
		$x_start = 40 + $this->w;

		$this->pdf->line($x_start,811.89,565.28,811.89);
		$this->pdf->addText(40 + $this->w,815.89,8,"Proj Mngr: ".$proposal->current_proposal['proj_mngr']);
		if ($lines->current_line['item_vendor'])
			$this->pdf->ezText("Vendor: ".$lines->current_line['item_vendor'],8,array('left' => $this->w + 10));
		//PO title
		$text_width = $this->pdf->getTextWidth(20,'Work Order');
		$this->pdf->addText(565.28-$text_width-10,814,20,'Work Order');
		//PO number
		$text_width = $this->pdf->getTextWidth(15,$pm->current_order['order_no']);
		$font_height = $this->pdf->getFontHeight(15);
		$this->pdf->addText(565.28-$text_width-10,811.89 - $font_height,15,$pm->current_order['order_no']);
		//Proposal no
		$text_width = $this->pdf->getTextWidth(10,"Proposal: ".$proposal->current_proposal['proposal_no']);
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText(565.28-$text_width-10,793 - $font_height,10,"Proposal: ".$proposal->current_proposal['proposal_no']);
		//Order date
		$text_width = $this->pdf->getTextWidth(10,"Work Order Date: ".date(DATE_FORMAT,strtotime($pm->current_order['order_date'])));
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText(565.28-$text_width-10,780 - $font_height,10,"Work Order Date: ".date(DATE_FORMAT,strtotime($pm->current_order['order_date'])));
		//Install date
		$install_date = ($proposal->current_proposal['actual_install_date'] != '0000-00-00' || !$proposal->current_proposal['actual_install_date'] ? $proposal->current_proposal['actual_install_date'] : $proposal->current_proposal['target_install_date']);
		$text_width = $this->pdf->getTextWidth(10,"Install Date: ".date(DATE_FORMAT,strtotime($install_date)));
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText(565.28-$text_width-10,767 - $font_height,10,"Install Date: ".(($install_date != '0000-00-00' && $install_date) ? date(DATE_FORMAT,strtotime($install_date)) : ""));

		$this->pdf->line(30,30,565.28,30);
		$this->pdf->ezSetDy(-35);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($this->headfoot,'all');
		$this->pdf->addObject($this->bottomFoot,'all');
		$this->px = $this->pdf->ezStartPageNumbers(550,22,6,'','',1);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->ezSetMargins(30 + $this->h,30,30,30);
		$this->pdf->ezSetDy(-5);

		$customers = new customers($_SESSION['id_hash']);
		$customers->fetch_master_record($proposal->current_proposal['customer_hash']);

		//Propose to
		if (ereg("\|",$proposal->current_proposal['propose_to_hash'])) {
			list($class,$id,$hash) = explode("|",$proposal->current_proposal['propose_to_hash']);
			$customers->fetch_master_record($id);
			if ($hash) {
				$customers->fetch_location_record($hash);
				$propose_to = stripslashes($customers->current_customer['customer_name']) . " : " . $customers->current_location['location_name'] . "\n" .
				( $customers->current_location['location_street'] ?
				    stripslashes($customers->current_location['location_street']) . "\n" : NULL
				) .
				stripslashes($customers->current_location['location_city']) . ", {$customers->current_location['location_state']} {$customers->current_location['location_zip']}";

			} else {
				$propose_to = stripslashes($customers->current_customer['customer_name']) . "\n" .
				( $customers->current_customer['street'] ?
				    stripslashes($customers->current_customer['street']) . "\n" : NULL
				) .
				stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";
			}
		} else {
			$customers->fetch_master_record($proposal->current_proposal['propose_to_hash']);
			$propose_to = stripslashes($customers->current_customer['customer_name']) . "\n" .
			( $customers->current_customer['street'] ?
                stripslashes($customers->current_customer['street']) . "\n" : NULL
            ) .
            stripslashes($customers->current_customer['city']) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}";
		}

		if ($proposal->current_proposal['install_addr_hash']) {

			list($class,$id,$loc_hash) = explode("|", $proposal->current_proposal['install_addr_hash']);
			$obj = new $class($_SESSION['id_hash']);
			if ($loc_hash) {
				$obj->fetch_location_record($loc_hash);
				$install_loc = stripslashes($obj->current_location['location_name']) . "\n" .
				( $obj->current_location['location_street'] ?
					stripslashes($obj->current_location['location_street']) . "\n" : NULL
				) .
				stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

			} else {
				$obj->fetch_master_record($id);
				$install_loc = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
				) .
				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{"current_".strrev(substr(strrev($class),1))}['state']} {$obj->{"current_".strrev(substr(strrev($class),1))}['zip']}";
			}
			unset($obj);
		}
		if ($pm->current_order['ship_to_hash']) {
			list($class,$id,$ship_hash) = explode("|",$pm->current_order['ship_to_hash']);
			if ($class) {
				$obj = new $class($this->current_hash);
				if ($ship_hash) {
					if ($loc_valid = $obj->fetch_location_record($ship_hash)) {
						$ship_to_loc = stripslashes($obj->current_location['location_name']) . "\n" .
						( $obj->current_location['location_street'] ?
    						stripslashes($obj->current_location['location_street']) . "\n" : NULL
    					) .
    					stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

					} else
						$ship_to = "Invalid Shipping Location";

				} else {
					$obj->fetch_master_record($id);
					$ship_to_loc = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
					( $obj->{"current_".strrev(substr(strrev($class),1))}["street"] ?
                        stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}["street"]) . "\n" : NULL
                    ) .
                    stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
				}
				unset($obj);
			}
		}
		if ($propose_to) {
			$data_tmp['propose_to'] = $propose_to;
			$cols['propose_to'] = "Propose To:";
		}
		if ($ship_to_loc) {
			$data_tmp['ship_to'] = $ship_to_loc;
			$cols['ship_to'] = "Shipping Location:";
		}
		if ($install_loc) {
			$data_tmp['install_addr'] = $install_loc;
			$cols['install_addr'] = "Installation Location:";
		}
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);

		$xpos = 0;
		if ($propose_to || $ship_to_loc || $install_loc) {
			$data = array($data_tmp);
			$options = array('showLines' 	=> 0,
							 'shaded'		=> 0,
							 'maxWidth'		=> $this->pdf->ez['pageWidth'],
							 'xPos'			=> $xpos,
							 'colGap'		=> 15,
							 'xOrientation'	=> 'right');

			$y = $this->pdf->ezTable($data,$cols,NULL,$options);
		}
		$this->pdf->ezSetDy(-5);

		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,$this->pdf->y,565.28,$this->pdf->y);
		$this->pdf->ezText($pm->current_order['order_descr'],14);
		$this->pdf->ezSetDy(-5);
		$this->pdf->line(30,$this->pdf->y,565.28,$this->pdf->y);
		$this->pdf->ezSetDy(-5);

		if ($pm->current_order['notes'])
			$y = $this->pdf->ezText($pm->current_order['notes'],8);

		//Show the line items
		$item_options = array("width" 		=> 535.28,
							  "maxWidth"	=> 535.28,
							  'rowGap'		=>	4,
							  'rowGap'		=>	5,
							  'xPos'		=> 30,
							  'showLines'	=>  2,
							  'lineCol'		=>	array(.8,.8,.8),
							  'shaded'		=>  0,
							  'shadeCol'	=>  array(.94,.94,.94),
							  "cols"		=> array("item_no" 		=>	array('justification'	=>	'left',
																			  'width'			=>	100),
													 "item_descr"	=>  array('justification'	=>	'left',
																			  'width'			=>	220),
													 "qty"			=>  array('justification'	=>	'right'),
													 "list"			=>  array('justification'	=>	'right'),
													 "cost"			=>  array('justification'	=>	'right'),
													 "ext_cost"		=>  array('justification'	=>	'right'),
											)
							  );
		if (in_array("item_no",$this->print_pref['item_print_fields']))
			$item_cols['item_no'] = "Item No.";
		if (in_array("item_descr",$this->print_pref['item_print_fields']) || in_array("vendor",$this->print_pref['item_print_fields']) || in_array("product",$this->print_pref['item_print_fields']) || in_array("item_tag",$this->print_pref['item_print_fields']) || in_array("item_finish",$this->print_pref['item_print_fields']))
			$item_cols['item_descr'] = "Item Description";
		if (in_array("qty",$this->print_pref['item_print_fields']))
			$item_cols['qty'] = "Qty";
		if (in_array("list",$this->print_pref['item_print_fields']))
			$item_cols['list'] = "Item List";
		if (in_array("ext_list",$this->print_pref['item_print_fields']))
			$item_cols['ext_list'] = "Ext List";
		if (in_array("sell",$this->print_pref['item_print_fields']))
			$item_cols['sell'] = "Item Sell";
		if (in_array("ext_sell",$this->print_pref['item_print_fields']))
			$item_cols['ext_sell'] = "Ext Sell";

		$item_col_attr = array("item_no" 			=>	array('justification'	=>	'left'),
						 	   "item_descr"			=>  array('justification'	=>	'left'),
							   "qty"				=>  array('justification'	=>	'right'),
							   "list"				=>  array('justification'	=>	'right'),
							   "ext_list"			=>  array('justification'	=>	'right'),
							   "sell"				=>  array('justification'	=>	'right'),
							   "ext_sell"			=>  array('justification'	=>	'right'),
							   );

		$pm->fetch_resources_used();
		for ($i = 0; $i < $pm->total_resources_used; $i++) {
			$item_data[] = array("resource"			 => $pm->resources_used[$i]['resource_name'],
								 "item_descr"		 => $pm->resources_used[$i]['item_descr'],
								 "time"				 => $pm->resources_used[$i]['time'].($pm->resources_used[$i]['units'] == 'D' ?
								 							" Day".($pm->resources_used[$i]['time'] > 1 ? "s" : NULL) : ($pm->resources_used[$i]['units'] == 'HD' ?
																" Half Day".($pm->resources_used[$i]['time'] > 1 ? "s" : NULL) : NULL)),
								 "true_cost"		 => '$'.number_format($pm->resources_used[$i]['true_cost'],2),
								 "internal_cost"	 => '$'.number_format($pm->resources_used[$i]['internal_cost'],2),
								 "true_cost_ext"	 => '$'.number_format($pm->resources_used[$i]['ext_true_cost'],2),
								 "internal_cost_ext" => '$'.number_format($pm->resources_used[$i]['ext_internal_cost'],2));
		}

		$item_cols = array('resource' 			=>	"Resource",
						   'comments'			=>	"Resource Notes",
						   'time'				=>	"Time",
						   'true_cost'			=>	"True Cost",
						   'true_cost_ext'		=>	"Ext True Cost",
						   'internal_cost'		=>	"Internal Cost",
						   'internal_cost_ext'	=>	"Ext Internal Cost");

		$item_col_attr = array("resource" 			=>	array('justification'	=>	'left'),
						 	   "comments"			=>  array('justification'	=>	'left'),
							   "time"				=>  array('justification'	=>	'right'),
							   "true_cost"			=>  array('justification'	=>	'right'),
							   "internal_cost"		=>  array('justification'	=>	'right'),
							   "true_cost_ext"		=>  array('justification'	=>	'right'),
							   "internal_cost_ext"	=>  array('justification'	=>	'right')
							   );
		//Show the line items
		$item_options = array("width" 		=> 535.28,
							  "maxWidth"	=> 535.28,
							  'rowGap'		=>	4,
							  'showLines'	=>  2,
							  'xPos'		=>  30,
							  'xOrientation'=> 'right',
							  'lineCol'		=>	array(.8,.8,.8),
							  'shaded'		=>  0,
							  'colGap'		=>  5,
							  'shadeCol'	=>  array(.94,.94,.94),
							  "cols"		=>  $item_col_attr
							  );

		$this->pdf->ezSetDy(-5);

		if (is_array($item_data))
			$y = $this->pdf->ezTable($item_data,$item_cols,NULL,$item_options);

		$this->pdf->ezSetDy(-10);
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,$this->pdf->y,565.28,$this->pdf->y);
		$this->pdf->ezText("Site Information",12);
		$this->pdf->ezSetDy(-5);
		$this->pdf->line(30,$this->pdf->y,565.28,$this->pdf->y);
		$this->pdf->ezSetDy(-5);

		if ($proposal->current_proposal['customer_contact']) {
			$customers->fetch_contact_record($proposal->current_proposal['customer_contact']);
			$contact = $customers->current_contact['contact_name'].($customers->current_contact['contact_title'] ?
				", ".$customers->current_contact['contact_title']."\n" : NULL).($customers->current_contact['contact_phone1'] ?
					$customers->current_contact['contact_phone1']." (Phone)\n" : NULL).($customers->current_contact['contact_phone2'] ?
						$customers->current_contact['contact_phone2']." (Phone 2)\n" : NULL).($customers->current_contact['contact_mobile'] ?
							$customers->current_contact['contact_mobile']." (Mobile)\n" : NULL).($customers->current_contact['contact_fax'] ?
								$customers->current_contact['contact_fax']." (Fax)\n" : NULL).($customers->current_contact['contact_email'] ?
									$customers->current_contact['contact_email'] : NULL);
			$contact_data[] = array('data' => $contact);
		}
		if ($proposal->current_proposal['bldg_poc'] || $proposal->current_proposal['bldg_phone'] || $proposal->current_proposal['bldg_fax']) {
			$poc = ($proposal->current_proposal['bldg_poc'] ?
				$proposal->current_proposal['bldg_poc']."\n" : NULL).($proposal->current_proposal['bldg_phone'] ?
					$proposal->current_proposal['bldg_phone']." (Phone)\n" : NULL).($proposal->current_proposal['bldg_fax'] ?
						$proposal->current_proposal['bldg_fax']." (Fax)" : NULL);
			$poc_data[] = array('data'	=>	$poc);
		}
			if ($proposal->current_proposal['ship_to_contact_name'] || $proposal->current_proposal['ship_to_contact_phone'] || $proposal->current_proposal['ship_to_contact_fax']) {
			$poc = ($proposal->current_proposal['ship_to_contact_name'] ?
				$proposal->current_proposal['ship_to_contact_name']."\n" : NULL).($proposal->current_proposal['ship_to_contact_phone'] ?
					$proposal->current_proposal['ship_to_contact_phone']." (Phone)\n" : NULL).($proposal->current_proposal['ship_to_contact_fax'] ?
						$proposal->current_proposal['ship_to_contact_fax']." (Fax)" : NULL);
			$shipping_contact_data[] = array('data'	=>	$poc);
		}

		$y_before = $this->pdf->y;
		if ($contact_data) {
			$attr1 = array("width"			=>	175,
						  "maxWidth"		=>	175,
						  'showLines'		=>	0,
						  'showHeadings'	=>	0,
						  'xPos'			=>	30,
						  'xOrientation'	=>	'right',
						  'titleFontSize'	=>	10,
						  'shaded'			=>	0);
			$y = $this->pdf->ezTable($contact_data,NULL,"Customer Contact",$attr1);
			$y_after = $this->pdf->y;
		}
		if ($poc_data) {
			if ($y_before)
				$this->pdf->ezSety($y_before);

			$attr2 = array("width"			=>	175,
						  "maxWidth"		=>	175,
						  'showLines'		=>	0,
						  'showHeadings'	=>	0,
						  'titleFontSize'	=>	10,
						  'xPos'			=>	205,
						  'xOrientation'	=>	'right',
						  'shaded'			=>	0);
			if (!$contact_data) {
				$attr2['xPos'] = 30;
			}
			$y = $this->pdf->ezTable($poc_data,NULL,"Building Management Contact",$attr2);
		}
		if ($shipping_contact_data) {
			if ($y_before)
				$this->pdf->ezSety($y_before);

			$attr3 = array("width"			=>	175,
						  "maxWidth"		=>	175,
						  'showLines'		=>	0,
						  'showHeadings'	=>	0,
						  'titleFontSize'	=>	10,
						  'xPos'			=>	380,
						  'xOrientation'	=>	'right',
						  'shaded'			=>	0);
			if (!$contact_data && !$poc_data) {
				$attr3['xPos'] = 30;
			} elseif (!$poc_data && $contact_data) {
				$attr3['xPos'] = 205;
			} elseif (!$contact_data && $poc_data) {
				$attr3['xPos'] = 205;
			}
			$y = $this->pdf->ezTable($shipping_contact_data,NULL,"Shipping Contact",$attr3);
		}

		if ($y_after)
			$this->pdf->ezSetY($y_after);
		$this->pdf->ezSetDy(-10);

		$space1 = 150;
		$space2 = 25;

		// Options for site and product information tables
		$col_options_site = array(array ('justification' => 'right', 'width'=>$space1),
							array ('justification' => 'left', 'width'=>$space2),
							array ('justification' => 'right', 'width'=>$space1),
							array ('justification' => 'left', 'width'=>$space2),
							array ('justification' => 'right', 'width'=>$space1),
							array ('justification' => 'left', 'width'=>$space2));

		$col_options_prod = array(array ('justification' => 'left', 'width'=>$space1),
							array ('justification' => 'left', 'width'=>$space2),
							array ('justification' => 'right', 'width'=>$space1),
							array ('justification' => 'left', 'width'=>$space2));
		$col_options_seating = array(array('justification' => 'left'), array('justification' => 'left'));

		$info_options = array("width" 		=> 	535.28,
							  "maxWidth"	=>	535.28,
							  "rowGap"		=>	4,
							  "showLines"	=>  2,
							  "lineCol"		=>	array(.8,.8,.8),
							  "shaded"		=>  0,
							  "shadeCol"	=>  array(.94,.94,.94),
							  "showHeadings"=>  0,
							  'colGap'		=>  5,
							  'xPos'		=>	30,
							  "xOrientation"=>	'right',
							  "cols"		=>  $col_options);
		// Add Site Information
		$site_info = array();
		$site_info[] = array("Number of Floors:",$proposal->current_proposal['no_floors'],
							 "Deliver Normal Hours: ",($proposal->current_proposal['option_dlv_normal_hours'] ? "Y" : ""),
							 "Occupied Space: ",($proposal->current_proposal['option_occupied'] ? "Y" : ""));
		$site_info[] = array("Install Normal Hours: ",($proposal->current_proposal['option_inst_normal_hours'] ? "Y" :""),
							 "Building Restrictions:",($proposal->current_proposal['option_bldg_rstr'] ? "Y" : ""),
							 "Certificate of Insurance",($proposal->current_proposal['option_insurance'] ? "Y" : ""));
		$site_info[] = array("Loading Dock: ",($proposal->current_proposal['option_loading_dock'] ? "Y" : ""),
							 "Freight Elevator: ",($proposal->current_proposal['option_freight_elev'] ? "Y" : ""),
							 "Permits: ",($proposal->current_proposal['option_permits'] ? "Y" : ""));
		$site_info[] = array("Stair Carry:",($proposal->current_proposal['option_stair_carry'] ? "Y" : ""),
							 "Move Product Prior: ",($proposal->current_proposal['option_mv_prod_prior'] ? "Y" : ""),
							 "Personal Security Req: ",($proposal->current_proposal['option_security'] ? "Y" : ""));
		$this->pdf->ezTable($site_info,'','',$info_options);
		$this->pdf->ezSetDy(-10);
		// Add Product Information
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,$this->pdf->y,565.28,$this->pdf->y);
		$this->pdf->ezText("Product Information",12);
		$this->pdf->ezSetDy(-5);
		$this->pdf->line(30,$this->pdf->y,565.28,$this->pdf->y);
		$this->pdf->ezSetDy(-5);
		$seating_info = array();
		$seating_info[] = array("Task Seating: ".$proposal->current_proposal['task_seating_prod'],
								"QTY: ".$proposal->current_proposal['task_seating_qty']);
		$seating_info[] = array("Guest Seating: ".$proposal->current_proposal['guest_seating_prod'],
								"QTY: ".$proposal->current_proposal['guest_seating_qty']);
		$info_options['cols'] = $col_options_seating;
		$this->pdf->ezTable($seating_info, '','', $info_options);

		$product_info = array();
		$product_info[] = array("Drawings Provided: ",($proposal->current_proposal['option_dwgs_pvd'] ? "Y" : ""),
							 	"Wall Mntd Product: ",($proposal->current_proposal['option_wall_mntd'] ? "Y" : ""));
		$product_info[] = array("Power Poles: ",($proposal->current_proposal['option_power_poles'] ? "Y" : ""),
							 	"Wood Trim/Elements:",($proposal->current_proposal['option_wood_trim'] ? "Y" : ""));
		$product_info[] = array("Multiple Trips: ",($proposal->current_proposal['option_multi_trip'] ? "Y" : ""));
		$this->pdf->ezSetDy(-10);
		$info_options['cols'] = $col_options_prod;
		$this->pdf->ezTable($product_info,'','',$info_options);

		// Show completion report if both criteria are met
		if ($pm->current_order['line_item_hash'] && $pm->current_order['line_item_status'] > 0) {
			$this->pdf->ezNewPage();
			$this->pdf->ezText("Completion Report",14);
			$this->pdf->ezSetDy(-5);

			// Show customer service ticket options
			$options_cst = array('showLines'=> 0,
							 'shaded'		=> 0,
							 'xPos'			=> 30,
							 'colGap'		=> 5,
							 'xOrientation'	=> 'right',
							 'showHeadings' => 0,
							 'cols'			=> array(array("width" => 100), array("width" => 50), array("width" => 100), array("width" => 50)));
			$cst = array();
			$cst[] = array("Warranty",$this->check_box($pm->current_order['completion_warranty']),"Field Change",$this->check_box($pm->current_order['completion_field_change']));
			$cst[] = array("Punch",$this->check_box($pm->current_order['completion_punch']),"Sign-off",$this->check_box($pm->current_order['completion_sign_off']));
			$cst[] = array("Service Request",$this->check_box($pm->current_order['completion_service_request']),"Delivery",$this->check_box($pm->current_order['completion_delivery']));
			$cst[] = array("Product Request","".$this->check_box($pm->current_order['completion_product_request']),"Completion Form",$this->check_box($pm->current_order['completion_form']));
			$this->pdf->ezTable($cst,'','',$options_cst);
			$this->pdf->ezSetDy(-5);

			$options_table = array('showLines' 	=> 0,
							 'shaded'		=> 0,
							 'width'		=> 535.28,
							 'lineCol'		=>	array(.8,.8,.8),
							 'xPos'			=> 30,
							 'colGap'		=> 5,
							 'xOrientation'	=> 'right',
							 'showHeadings' => 0);
			$comp_report = array();

			if ($pm->current_order['po_hash']) {
				$po = new purchase_order();
				$po->fetch_po_record($proposal_hash,$pm->current_order['po_hash']);
				$po_no = $po->current_po['po_no'];
			}
			$contact_phone = ($customers->current_contact['contact_mobile'] ? $customers->current_contact['contact_mobile'] : ($customers->current_contact['contact_phone1'] ?  $customers->current_contact['contact_phone1'] : $customers->current_contact['contact_phone2']));
			$comp_report[] = array("Customer: ".stripslashes($customers->current_customer['customer_name']), "Purchase Order No: ".$po_no);
			$comp_report[] = array("Customer Contact: ".$customers->current_contact['contact_name'], "Proposal No: ".$proposal->current_proposal['proposal_no']);
			$comp_report[] = array("Phone No: ".$contact_phone ,"Work Order No: ".$pm->current_order['order_no']);
			$this->pdf->ezSetDy(-5);
			$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y);
			$this->pdf->ezSetDy(-5);
			$this->pdf->ezTable($comp_report,'','',$options_table);
			$this->pdf->ezSetDy(-5);
			$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y);
			$this->pdf->ezSetDy(-5);
			$cols = array();
			$data_tmp = array();

			if ($install_loc) {
				$data_tmp['install_addr'] = $install_loc;
				$cols['install_addr'] = "Installation Location:";
			}
			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(1);

			$font_headings = 10; // font size of headings of tables
			$data = array($data_tmp);
			$options_loc = array('showLines' 	=> 0,
								 'shaded'		=> 0,
								 'lineCol'		=>	array(.8,.8,.8),
								 'width'		=> 250,
								 'xPos'			=> 30,
								 'xOrientation' => 'right',
								 'colGap'		=> 5,
								 'xOrientation'	=> 'right',
								 'showHeadings' => 1);
			$this->pdf->ezTable($data,$cols,NULL,$options_loc);
			$this->pdf->ezSetDy(-10);
			$this->pdf->ezText("Completion Notes/Service Required:",$font_headings);
			$this->pdf->ezSetDy(-5);
			$data_comp_notes = $pm->current_order['completion_notes'] ? array(array($pm->current_order['completion_notes']),array(""),array("")) : array(array(""),array(""),array(""),array(""));

			$options_table["cols"] = array(array("width = 550"));
			$options_table["showLines"] = 2;
			$this->pdf->ezTable($data_comp_notes,'','',$options_table);
			$this->pdf->ezSetDy(-10);

			$options_punch = array('showLines' 	=> 2,
							 'shaded'		=> 0,
							 'width'		=> 535.28,
							 'xPos'			=> 30,
							 'lineCol'		=>	array(.8,.8,.8),
							 'colGap'		=> 5,
							 'xOrientation'	=> 'right',
							 'showHeadings' => 0,
							 'cols'			=> array(array("width" => 100), array("width" => 100), array("width" => 60), array("width" => 115),array("width" => 75),array("width" => 85.28)));

			$options_approved = array('showLines'=> 0,
							 'shaded'		=> 0,
							 'xPos'			=> 320,
							 'colGap'		=> 5,
							 'xOrientation'	=> 'right',
							 'showHeadings' => 0,
							 'cols'			=> array(array("width" => 75), array("width" => 50), array("width" => 75), array("width" => 50)));

			// Display punch issues and discrepancies
			$this->pdf->ezText("Product Issues & Discrepancies:",$font_headings);
			$pm->fetch_completion_report_punch();
			$options_action = array('showLines' 	=> 2,
									'shaded' 		=> 0,
									'width'			=> 535.28,
									'xPos'			=> 30,
									'lineCol'		=>	array(.8,.8,.8),
									'colGap'		=> 5,
									'xOrientation' 	=> 'right',
									'showHeadings' 	=> 0,
									'cols'			=> array(array('width' => 100), array('width' => 435.28)));

			// If no punch items, print out two empty tables, otherwise print out a table for each punch item
			$num = (sizeof($pm->completion_order_punch) > 0 ?  sizeof($pm->completion_order_punch) : 2);
			for($i = 0; $i < $num; $i++) {
				$data_approved = array(array("Approved",$this->check_box($pm->completion_order_punch[$i]['punch_approved']),"Resolved",$this->check_box($pm->completion_order_punch[$i]['punch_resolved'])));
				$this->pdf->ezTable($data_approved,'','',$options_approved);
				$this->pdf->ezSetDy(-5);

				$data_punch = array();
				$data_punch[0] = array("Product","Location","Resp.","Manufacturer", "Model No.", "Finish");
				$data_punch[1] = array("".$pm->completion_order_punch[$i]['punch_product'],"".$pm->completion_order_punch[$i]['punch_location'],"".$pm->completion_order_punch[$i]['punch_resp'],"".$pm->completion_order_punch[$i]['punch_vendor'],"".$pm->completion_order_punch[$i]['punch_model_no'],"".$pm->completion_order_punch[$i]['punch_finish']);
				$this->pdf->ezTable($data_punch,"","",$options_punch);

				$data_action = array();
				$data_action[0] = array("Discrepancy: ",$pm->completion_order_punch[$i]['punch_descrepancy']);
				$data_action[1] = array("Action Taken: ",$pm->completion_order_punch[$i]['punch_action']);
				$this->pdf->ezTable($data_action,"","",$options_action);
				$this->pdf->ezSetDy(-10);
			}

			// Print ship to location if exists, otherwise print an empty table

			list($class,$id,$hash) = explode("|",$lines->current_line['ship_to_hash']);
			$obj = new $class($this->current_hash);
				if ($hash) {
					$obj->fetch_location_record($hash);
					$loc1 = stripslashes($obj->current_location['location_name']) . "\n" .
					( $obj->current_location['location_street'] ?
						stripslashes($obj->current_location['location_street']) . "\n" : NULL
					) .
					stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

				} else {
					$obj->fetch_master_record($id);
					$loc1 = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
					( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
						stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
					) .
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
				}
				unset($obj, $data_tmp);

			if ($loc1) {
				$data_tmp['punch_ship_to'] = $loc1;
			} else {
				$data_tmp['punch_ship_to'] = "\n\n\n";
			}
			$data_ship_loc = array($data_tmp);
			$cols_punch['punch_ship_to'] = "Shipping Address for Punch Items:";
			$this->pdf->ezTable($data_ship_loc,$cols_punch,'',$options_loc);
			$this->pdf->ezSetDy(-10);
			if ($this->pdf->y < 100)
				$this->pdf->ezNewPage();
			$this->pdf->ezText("Signature below indicates receipt of product in an undamaged, fully functional condition and/or completion of work in a satisfactory manner.  Any exceptions must be noted above.",$font_headings);
			$this->pdf->ezSetDy(-5);
			$options_action['cols'] = array(array('width' => 120),array('width' => 250),array('width' => 65.28), array('width' => 100));
			$signature = array();
			$signature[0] = array("Customer Signature:","","Date:","");
			$signature[1] = array("Print Name:","","","");
			$this->pdf->ezTable($signature,"","",$options_action);
			$this->pdf->ezSetDy(-10);
			$signature[0] = array("Installer Signature:","","Date:","");
			$signature[1] = array("Print Name:","","","");
			$this->pdf->ezTable($signature,"","",$options_action);
		}
	}
/**
 * Generates a pdf document of an accounts receivable report given the report_hash.
 *
 * @param String $report_hash
 */
	function ar_report($report_hash) {
		$reports = new reports($this->current_hash);
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for ar_report object.
		$reports->doit_ar();
		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Accounts Receivable Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		switch ($reports->current_report['timeframe']) {
			case '*':
			$report_title = "As of ".date(DATE_FORMAT);
			break;

			case 1:
			$report_title = "Invoices dated ".date("Y");
			break;

			case 2:
			$report_title = "Invoices dated ".(date("Y") -  1);
			break;

			case 3:
			$period_info = get_period_info(date("Y-m-d"));
			$report_title = "Invoices dated between ".date(DATE_FORMAT,strtotime($period_info['period_start']))." and ".date(DATE_FORMAT,strtotime($period_info['period_end']));
			break;

			case 4:
			$period_info = get_previous_period(date("Y-m-d"));
			$report_title = "Invoices dated between ".date(DATE_FORMAT,strtotime($period_info['period_start']))." and ".date(DATE_FORMAT,strtotime($period_info['period_end']));
			break;

			case 5:
			$report_title = "Invoices dated between ".date(DATE_FORMAT,strtotime($reports->current_report['sort_from_date']))." and ".date(DATE_FORMAT,strtotime($reports->current_report['sort_to_date']));
			break;

		}
		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Accounts Receivable Report",16,array('justification' => 'center'));
		$this->pdf->ezText($reports->report_title,12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array("invoice_no"	  =>	"Invoice No.",
						"invoice_date" =>	"Invoice Date",
						"due_date" 			=> "Due Date",
						"orig_amt"			=> "Orig Amt",
						"balance"			=> "Balance",
						"balance_current"	=> "Current",
						"balance_sched1"	=> $reports->current_report['age1']." Days",
						"balance_sched2"	=> $reports->current_report['age2']." Days",
						"balance_sched3" 	=> $reports->current_report['age3']." Days");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$space = 80;  // space between invoice detail columns
		$space2 = 50; // space between aging schedule columns
		$start2 = $start + 2.5 * $space; // X coordinate of aging schedule columns
		$col_orig_amt = $start2;
		$col_bal = $start2 + 1 * $space2;
		$col_curr = $start2 + 2 * $space2;
		$col_s1 = $start2 + 3 * $space2;
		$col_s2 = $start2 + 4 * $space2;
		$col_s3 = $start2 + 5 * $space2;
		$right_adj = 15; // adjustment for right justified columns (aging schedules)
		$font_size = 8;

		// Print header for each column
        $this->pdf->ezSetDy(-20);
        if ($reports->current_report['showdetail'] == 2) {
            $this->pdf->addText($start, $this->pdf->y,$font_size,"<b>".$cols['invoice_no']."</b>");
            $this->pdf->addText($start + $space + 20, $this->pdf->y,$font_size,"<b>".$cols['invoice_date']."</b>");
            $this->pdf->addText($start + 2 * $space, $this->pdf->y,$font_size,"<b>".$cols['due_date']."</b>");
        }
        $this->pdf->addText($col_orig_amt + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['orig_amt']."</b>");
        $this->pdf->addText($col_bal + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance']."</b>");
        $this->pdf->addText($col_curr + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance_current']."</b>");
        $this->pdf->addText($col_s1 + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance_sched1']."</b>");
        $this->pdf->addText($col_s2 + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance_sched2']."</b>");
        $this->pdf->addText($col_s3 + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance_sched3']."</b>");
        $this->pdf->setStrokeColor(0,0,0);
        $this->pdf->setLineStyle(1);
        $this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

        $y_head = $this->pdf->y;
        $this->pdf->restoreState();
        $this->pdf->closeObject();
        $this->pdf->addObject($headerObj,'all');

        $this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

        // Print footer
        $bottomFoot = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        $this->pdf->setStrokeColor(.8,.8,.8);
        $this->pdf->setLineStyle(1);
        $this->pdf->line(30,30,565.28,30);
        $this->pdf->restoreState();
        $this->pdf->closeObject();

        $this->pdf->addObject($bottomFoot,'all');
        $this->pdf->ezStartPageNumbers(550,22,6);
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        // Options for each column
        $options_columns = array("invoice_no" => array ('justification' => 'left', 'width'=>$space),
	                             "invoice_date" => array ('justification' => 'left', 'width'=>$space),
	                             "due_date" => array ('justification' => 'left', 'width'=>$space + .5 * $space2),
	                             "orig_amt" => array ('justification' => 'right', 'width'=>$space2),
	                             "balance" => array ('justification' => 'right', 'width'=>$space2),
	                             "balance_current" => array ('justification' => 'right', 'width'=>$space2),
	                             "balance_sched1" => array ('justification' => 'right', 'width'=>$space2),
	                             "balance_sched2" => array ('justification' => 'right', 'width'=>$space2),
	                             "balance_sched3" => array ('justification' => 'right', 'width'=>$space2)
        );
        $options_details = array('fontSize'    => 7,
		                         'width'            => 535.28,//$this->pdf->ez['pageWidth'] - ($this->pdf->ez['leftMargin'] + $this->pdf->ez['rightMargin']) - (45 - $this->pdf->ez['rightMargin']),
		                         'xPos'             => 60,
		                         'xOrientation'     => 'right',
		                         'showLines'        =>  0,
		                         'lineCol'          =>  array(.8,.8,.8),
		                         'shaded'           =>  0,
		                         'showHeadings'     =>  0,
		                         'rowGap'           =>  5,
		                         'colGap'           =>  5,
		                         'titleFontSize'    =>  10,
		                         'shadeCol'         =>  array(.94,.94,.94),
		                         'cols'             =>  $options_columns
                                );


        $vendors = new vendors($this->current_hash);
        $proposals = new proposals($this->current_hash);

        if (is_array($reports->results)) {
            $k = 0;

            while (list($vendor_hash,$invoice_array) = each($reports->results)) {
                $total_invoice = $total_balance = $total_current = $total_sched1 = $total_sched2 = $total_sched3 = 0;
                
                $current_total_receivable_simple = $invoice_array['total_receivable'];
                $current_balance_total_simple = $invoice_array['balance_total'];
                $invoiceHash = $invoice_array['invoice_hash'];
                $r = $this->db->query("SELECT amount,date FROM finance_charges where invoice_hash = '$invoiceHash'");
                while ($row = $this->db->fetch_assoc($r)) {
                	switch($reports->current_report['timeframe']){
                		case 1:
                			if(strtotime($row['date']) > strtotime(date("Y")."-01-01") && strtotime($row['date']) < strtotime(date("Y")."-12-31")){
                				$current_total_receivable_simple += $row['amount'];
                				$current_balance_total_simple += $row['amount'];
                			}else{
                				$current_total_receivable_simple -= $row['amount'];
                				$current_balance_total_simple -= $row['amount'];
                			}
                			break;
                
                		case 2:
                			if(strtotime($row['date']) > strtotime(date("Y")."-01-01 -1 year") && strtotime($row['date']) < strtotime(date("Y")."-12-31 -1 year")){
                				$current_total_receivable_simple += $row['amount'];
                				$current_balance_total_simple += $row['amount'];
                			}else{
                				$current_total_receivable_simple -= $row['amount'];
                				$current_balance_total_simple -= $row['amount'];
                			}
                			break;
                
                		case 3:
                			$period_info = get_period_info(date("Y-m-d"));
                			if(strtotime($row['date']) > strtotime($period_info['period_start']) && strtotime($row['date']) < strtotime($period_info['period_end'])){
                				$current_total_receivable_simple += $row['amount'];
                				$current_balance_total_simple += $row['amount'];
                			}else{
                				$current_total_receivable_simple -= $row['amount'];
                				$current_balance_total_simple -= $row['amount'];
                			}
                			break;
                
                		case 4:
                			$period_info = get_previous_period(date("Y-m-d"));
                			if(strtotime($row['date']) > strtotime($period_info['period_start']) && strtotime($row['date']) < strtotime($period_info['period_end'])){
                				$current_total_receivable_simple += $row['amount'];
                				$current_balance_total_simple += $row['amount'];
                			}else{
                				$current_total_receivable_simple -= $row['amount'];
                				$current_balance_total_simple -= $row['amount'];
                			}
                			break;
                
                		case 5:
                			$sort_from_date = $reports->current_report['sort_from_date'];
                			$sort_to_date = $reports->current_report['sort_to_date'];
                			if (strtotime($row['date']) > strtotime($sort_from_date) && strtotime($row['date']) < strtotime($sort_to_date)) {
                				$current_total_receivable_simple += $row['amount'];
                				$current_balance_total_simple += $row['amount'];
                			}else{
                				$current_total_receivable_simple -= $row['amount'];
                				$current_balance_total_simple -= $row['amount'];
                			}
                			break;
                
                		default:
                			$current_total_receivable_simple += $row['amount'];
                			$current_balance_total_simple += $row['amount'];
                	}
                
                }

                $this->pdf->setStrokeColor(0,0,0);
                if ($reports->current_report['showdetail'] == 2)
                    $this->pdf->setColor(.8,.8,.8);
                else {
                    if ($k % 2 == 0)
                        $this->pdf->setColor(.8,.8,.8);
                    else
                        $this->pdf->setColor(1,1,1);
                }

                $this->pdf->setLineStyle(1);
                $this->pdf->line($this->pdf->ez['leftMargin'],
                                 $this->pdf->y + 16,
                                 $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],
                                 $this->pdf->y + 16
                );

                $this->pdf->line($this->pdf->ez['leftMargin'],
                                 $this->pdf->y - 1,
                                 $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],
                                 $this->pdf->y - 1
                );

                $this->pdf->setStrokeColor(.7, .7, .7);
                $this->pdf->setColor(.7, .7, .7);
                $this->pdf->filledRectangle($this->pdf->ez['leftMargin'],
                                            $this->pdf->y,
                                            $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],
                                            15
                );

                $this->pdf->setColor(0, 0, 0);
                $this->pdf->ezSetDy(15);

                $std_y_pos = $this->pdf->y;
                $this->pdf->ezText(($invoice_array['customer_name'] ? stripslashes($invoice_array['customer_name']) : stripslashes($invoice_array['vendor_name'])), 12);
                if ($reports->current_report['showdetail'] == 2)
                    $this->pdf->ezSetDy(-10);
                else
                    $std_y_pos = $this->pdf->y;

                if ($reports->current_report['showdetail'] == 2) {
                	$j = 0;
                    while (list($invoice_hash,$details) = each($reports->invoice_detail[$vendor_hash])) {
                        // Calculate totals
                        for ($i = 0; $i < count($details); $i++) {
                        	
                        	$current_total_receivable = $details[$i]['total_receivable'];
                        	$current_balance_total = $details[$i]['balance_total'];
                        	$hasFinanceChargeInDate = false;
                        	$invoiceHash = $details[0]['invoice_hash'];
                        	$r = $this->db->query("SELECT amount,date FROM finance_charges where invoice_hash = '$invoiceHash'");
                        	while ($row = $this->db->fetch_assoc($r)) {
                        		switch($reports->current_report['timeframe']){
                        			case 1:
                        				if(strtotime($row['date']) > strtotime(date("Y")."-01-01") && strtotime($row['date']) < strtotime(date("Y")."-12-31")){
                        					$current_total_receivable += $row['amount'];
                        					$current_balance_total += $row['amount'];
                        					$hasFinanceChargeInDate = true;
                        				}else{
                        					$current_total_receivable -= $row['amount'];
                        					$current_balance_total -= $row['amount'];
                        				}
                        				break;
                        				 
                        			case 2:
                        				if(strtotime($row['date']) > strtotime(date("Y")."-01-01 -1 year") && strtotime($row['date']) < strtotime(date("Y")."-12-31 -1 year")){
                        					$current_total_receivable += $row['amount'];
                        					$current_balance_total += $row['amount'];
                        					$hasFinanceChargeInDate = true;
                        				}else{
                        					$current_total_receivable -= $row['amount'];
                        					$current_balance_total -= $row['amount'];
                        				}
                        				break;
                        				 
                        			case 3:
                        				$period_info = get_period_info(date("Y-m-d"));
                        				if(strtotime($row['date']) > strtotime($period_info['period_start']) && strtotime($row['date']) < strtotime($period_info['period_end'])){
                        					$current_total_receivable += $row['amount'];
                        					$current_balance_total += $row['amount'];
                        					$hasFinanceChargeInDate = true;
                        				}else{
                        					$current_total_receivable -= $row['amount'];
                        					$current_balance_total -= $row['amount'];
                        				}
                        				break;
                        				 
                        			case 4:
                        				$period_info = get_previous_period(date("Y-m-d"));
                        				if(strtotime($row['date']) > strtotime($period_info['period_start']) && strtotime($row['date']) < strtotime($period_info['period_end'])){
                        					$current_total_receivable += $row['amount'];
                        					$current_balance_total += $row['amount'];
                        					$hasFinanceChargeInDate = true;
                        				}else{
                        					$current_total_receivable -= $row['amount'];
                        					$current_balance_total -= $row['amount'];
                        				}
                        				break;
                        	
                        			case 5:
                        				$sort_from_date = $reports->current_report['sort_from_date'];
                        				$sort_to_date = $reports->current_report['sort_to_date'];
                        				if (strtotime($row['date']) > strtotime($sort_from_date) && strtotime($row['date']) < strtotime($sort_to_date)) {
                        					$current_total_receivable += $row['amount'];
                        					$current_balance_total += $row['amount'];
                        					$hasFinanceChargeInDate = true;
                        				}else{
                        					$current_total_receivable -= $row['amount'];
                        					$current_balance_total -= $row['amount'];
                        				}
                        				break;
                        				 
                        			default:
                        				$current_total_receivable += $row['amount'];
                        				$current_balance_total += $row['amount'];
                        				$hasFinanceChargeInDate = true;
                        		}
                        		 
                        	}
                        	
                            $total_balance = bcadd($total_balance,$current_balance_total,2);
                            $total_invoice = bcadd($total_invoice,$current_total_receivable,2);

                            $total_current = bcadd($total_current,$details[$i]['balance_current'],2);
                            $total_sched1 = bcadd($total_sched1,$details[$i]['balance_sched1'],2);
                            $total_sched2 = bcadd($total_sched2,$details[$i]['balance_sched2'],2);
                            $total_sched3 = bcadd($total_sched3,$details[$i]['balance_sched3'],2);
                        }


	                    // Show contact info for Trac #1395
							unset($contact_info);
							if ($reports->current_report['contact_detail'] == 2 && $details[0]['contact_hash']) {
								if ($details[0]['contact_name'] || $details[0]['contact_email']) {
									$contact_info .=
									($details[0]['contact_name'] ?
										stripslashes($details[0]['contact_name']) . "  " : NULL) .
									($details[0]['contact_email'] ?
										$details[0]['contact_email'] : NULL);
	
									$contact_info .= "\n";
								}
								if ($details[0]['contact_phone1'] || $details[0]['contact_phone2']) {
									$contact_info .=
									($details[0]['contact_phone1'] ?
										$details[0]['contact_phone1'] . " (p)  " : NULL) .
									($details[0]['contact_phone2'] ?
										$details[0]['contact_phone2'] . " (p)" : NULL);
	
									$contact_info .= "\n";
								}
								if ($details[0]['contact_mobile'] || $details[0]['contact_fax']) {
									$contact_info .=
									($details[0]['contact_mobile'] ?
										$details[0]['contact_mobile'] . " (m)  " : NULL) .
									($details[0]['contact_fax'] ?
										$details[0]['contact_fax'] . " (f)" : NULL);
	
									$contact_info .= "\n";
								}
							}
							if ( $j > 0 ) {
								$this->pdf->setLineStyle(.5);
								$this->pdf->setColor(.7, .7, .7);
	                            $this->pdf->line($this->pdf->ez['leftMargin'],
	                                             $this->pdf->y,
	                                             $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],
	                                             $this->pdf->y);
	                            $this->pdf->setColor(0, 0, 0);
							}

                            $this->pdf->ezText(($details[0]['proposal_no'] ? "Proposal: ".$details[0]['proposal_no']." - ".$details[0]['proposal_descr'] : "No Proposal"),9, array("aleft"=>50));
						if ($contact_info) {
							$this->pdf->ezSetDy(-1);
							$this->pdf->ezText($contact_info,8, array("aleft"=>60));
							$this->pdf->ezSetDy(-1);
						}
						$this->pdf->ezSetDy(-10);
						$total_balance += $details[$i]['balance_total'];
                        $total_invoice += $details[$i]['total_receivable'];
						foreach ($details as $key => $val) {

							$due_date = ((is_numeric($val['payment_terms']) && $val['payment_terms'] == 0) || (!is_numeric($val['payment_terms']) && DEFAULT_CUST_PAY_TERMS == 0) ? " Upon Receipt" : date(DATE_FORMAT,strtotime($val['invoice_date']." +".(is_numeric($val['payment_terms']) ? $val['payment_terms'] : DEFAULT_CUST_PAY_TERMS)." days")));
							$orig_amt = $this->format_dollars_show_zero($current_total_receivable);
							$bal = $this->format_dollars_show_zero($current_balance_total);
							if($val['type'] == 'C'){
								$this->pdf->addText($start, $this->pdf->y,$font_size, "Credit: ".$val['credit_no'] );
							}else{
								$this->pdf->addText($start, $this->pdf->y,$font_size,($val['type'] == 'I' ? "Invoice: ".$val['invoice_no'] : (!$proposal_hash ? "Unapplied " : NULL)."Deposit"));
							}
							$this->pdf->addText($start + $space + 20, $this->pdf->y,$font_size,date(DATE_FORMAT,strtotime($val['invoice_date'])));
							if($val['due_date'])
									$this->pdf->addText($start + 2 * $space, $this->pdf->y,$font_size,date(DATE_FORMAT,strtotime($val['due_date'])));
							$this->pdf->addTextWrap($col_orig_amt, $this->pdf->y,$space2, $font_size,number_format($val['total_receivable'],2),'right');
							$this->pdf->addTextWrap($col_bal, $this->pdf->y, $space2, $font_size,number_format($val['balance_total'],2),'right');
							$this->pdf->addTextWrap($col_curr, $this->pdf->y, $space2, $font_size,$this->format_dollars($val['balance_current']),'right');
							$this->pdf->addTextWrap($col_s1, $this->pdf->y, $space2, $font_size,$this->format_dollars($val['balance_sched1']),'right');
							$this->pdf->addTextWrap($col_s2, $this->pdf->y, $space2, $font_size,$this->format_dollars($val['balance_sched2']),'right');
							$this->pdf->addTextWrap($col_s3, $this->pdf->y, $space2, $font_size,$this->format_dollars($val['balance_sched3']),'right');
							$this->pdf->ezSetDy(-10);
						}

					$j++;
                    }
                    $grand_total['invoice'] += $total_invoice;
                    $grand_total['balance'] += $total_balance;
                    $grand_total['current'] += $total_current;
                    $grand_total['sched1'] += $total_sched1;
                    $grand_total['sched2'] += $total_sched2;
                    $grand_total['sched3'] += $total_sched3;
                } else {
                	
                    $grand_total['invoice'] += $current_total_receivable_simple;
                    $grand_total['balance'] += $current_balance_total_simple;
                    $grand_total['current'] += $invoice_array['balance_current'];
                    $grand_total['sched1'] += $invoice_array['balance_sched1'];
                    $grand_total['sched2'] += $invoice_array['balance_sched2'];
                    $grand_total['sched3'] += $invoice_array['balance_sched3'];
                }

                // Display totals for each schedule that has a balance
                if ($reports->current_report['showdetail'] == 1)
                    $this->pdf->y = $std_y_pos;

                $this->pdf->setLineStyle(1);
                if ($invoice_array['total_receivable'] != 0 || $total_invoice != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                    	$this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_orig_amt + $right_adj,$this->pdf->y,$col_orig_amt + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_orig_amt,($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars_show_zero(($reports->current_report['showdetail'] == 2 ? $total_invoice : $current_total_receivable_simple)), 'right');
                }
                if ($invoice_array['balance_total'] != 0 || $total_balance != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                    	$this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_bal + $right_adj, $this->pdf->y,$col_bal + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_bal, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars_show_zero(($reports->current_report['showdetail'] == 2 ? $total_balance : $current_balance_total_simple)), 'right');
                }
                if ($invoice_array['balance_current'] != 0 || $total_current != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                    	$this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_curr + $right_adj, $this->pdf->y,$col_curr + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_curr, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars(($reports->current_report['showdetail'] == 2 ? $total_current : $invoice_array['balance_current'])), 'right');
                }
                if ($invoice_array['balance_sched1'] != 0 || $total_sched1 != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                    	$this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_s1 + $right_adj, $this->pdf->y,$col_s1 + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_s1, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars(($reports->current_report['showdetail'] == 2 ? $total_sched1 : $invoice_array['balance_sched1'])), 'right');
                }
                if ($invoice_array['balance_sched2'] != 0 || $total_sched2 != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                    	$this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_s2 + $right_adj, $this->pdf->y,$col_s2 + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_s2, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars(($reports->current_report['showdetail'] == 2 ? $total_sched2 : $invoice_array['balance_sched2'])), 'right');
                }
                if ($invoice_array['balance_sched3'] != 0 || $total_sched3 != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                    	$this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_s3 + $right_adj, $this->pdf->y,$col_s3 + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_s3, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars(($reports->current_report['showdetail'] == 2 ? $total_sched3 : $invoice_array['balance_sched3'])), 'right');
                }
                $this->pdf->ezSetDy($reports->current_report['showdetail'] == 2 ? -35 : -22);

                $k++;
            }

            // Calculate and display the grand total of each aging schedule.
            $grand_total_all = $grand_total['current'] + $grand_total['sched1'] + $grand_total['sched2'] + $grand_total['sched3'];
            $this->pdf->setStrokeColor(0,0,0);
            $this->pdf->setLineStyle(1,NULL,NULL);

            $this->pdf->line($col_orig_amt + $right_adj, $this->pdf->y,$col_orig_amt + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_orig_amt, $this->pdf->y - 15, $space2, 7, $this->format_dollars_show_zero($grand_total['invoice']), 'right');
            $this->pdf->line($col_bal + $right_adj, $this->pdf->y,$col_bal + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_bal, $this->pdf->y - 15, $space2, 7, $this->format_dollars_show_zero($grand_total['balance']), 'right');
            $this->pdf->line($col_curr + $right_adj, $this->pdf->y,$col_curr + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_curr, $this->pdf->y - 15, $space2, 7, $this->format_dollars($grand_total['current']), 'right');
            $this->pdf->line($col_s1 + $right_adj, $this->pdf->y,$col_s1 + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_s1, $this->pdf->y - 15, $space2, 7, $this->format_dollars($grand_total['sched1']), 'right');
            $this->pdf->line($col_s2 + $right_adj, $this->pdf->y,$col_s2 + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_s2, $this->pdf->y - 15, $space2, 7, $this->format_dollars($grand_total['sched2']), 'right');
            $this->pdf->line($col_s3 + $right_adj, $this->pdf->y,$col_s3 + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_s3, $this->pdf->y - 15, $space2, 7, $this->format_dollars($grand_total['sched3']), 'right');
		
		}

	}
/**
 * Generates a pdf document of an accounts payable report given the report_hash.
 *
 * @param String $report_hash
 */
    function ap_report($report_hash) {
        $reports = new reports($this->current_hash);
        $_POST['report_hash'] = $report_hash;
        $_POST['doit_print'] = 1;

        // Populate data for ar_report object.
        $reports->doit_ap();
        // Start the PDF document ( A4 size x:595.28 y:841.89 )
        $this->pdf = new Cezpdf();

        $title = MY_COMPANY_NAME." Accounts Payable Report";
        $creation_date = date(TIMESTAMP_FORMAT,time());

        $this->pdf->addInfo('Title',$title);
        $this->pdf->addInfo('CreationDate',$creation_date);

        switch ($reports->current_report['timeframe']) {
            case '*':
                $report_title = "As of ".date(DATE_FORMAT);
                break;

            case 1:
                $report_title = "Invoices dated ".date("Y");
                break;

            case 2:
                $report_title = "Invoices dated ".(date("Y") -  1);
                break;

            case 3:
                $period_info = get_period_info(date("Y-m-d"));
                $report_title = "Invoices dated between ".date(DATE_FORMAT,strtotime($period_info['period_start']))." and ".date(DATE_FORMAT,strtotime($period_info['period_end']));
                break;

            case 4:
                $period_info = get_previous_period(date("Y-m-d"));
                $report_title = "Invoices dated between ".date(DATE_FORMAT,strtotime($period_info['period_start']))." and ".date(DATE_FORMAT,strtotime($period_info['period_end']));
                break;

            case 5:
                $report_title = "Invoices dated between ".date(DATE_FORMAT,strtotime($reports->current_report['sort_from_date']))." and ".date(DATE_FORMAT,strtotime($reports->current_report['sort_to_date']));
                break;

        }

        // Print header
        $headerObj = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');
        $this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
        $this->pdf->ezText("Accounts Payable Report",16,array('justification' => 'center'));
        $this->pdf->ezText($reports->report_title,12,array('justification' => 'center', 'leading' => 15));
        $this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
        $this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
        //  Column headers
        $cols = array("invoice_no"    =>    "Invoice No.",
            "invoice_date" =>   "Invoice Date",
            "due_date"          => "Due Date",
            "orig_amt"          => "Orig Amt",
            "balance"           => "Balance",
            "balance_current"   => "Current",
            "balance_sched1"    => $reports->current_report['age1']." Days",
            "balance_sched2"    => $reports->current_report['age2']." Days",
            "balance_sched3"    => $reports->current_report['age3']." Days");

        // Calculate x coordinate for each column
        $start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
        $space = 80;  // space between invoice detail columns
        $space2 = 50; // space between aging schedule columns
        $start2 = $start + 2.5 * $space; // X coordinate of aging schedule columns
        $col_orig_amt = $start2;
        $col_bal = $start2 + 1 * $space2;
        $col_curr = $start2 + 2 * $space2;
        $col_s1 = $start2 + 3 * $space2;
        $col_s2 = $start2 + 4 * $space2;
        $col_s3 = $start2 + 5 * $space2;
        $right_adj = 15; // adjustment for right justified columns (aging schedules)
        $font_size = 8;

        // Print header for each column
        $this->pdf->ezSetDy(-20);
        if ($reports->current_report['showdetail'] == 2) {
            $this->pdf->addText($start, $this->pdf->y,$font_size,"<b>".$cols['invoice_no']."</b>");
            $this->pdf->addText($start + $space, $this->pdf->y,$font_size,"<b>".$cols['invoice_date']."</b>");
            $this->pdf->addText($start + 2 * $space, $this->pdf->y,$font_size,"<b>".$cols['due_date']."</b>");
        }
        $this->pdf->addText($col_orig_amt + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['orig_amt']."</b>");
        $this->pdf->addText($col_bal + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance']."</b>");
        $this->pdf->addText($col_curr + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance_current']."</b>");
        $this->pdf->addText($col_s1 + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance_sched1']."</b>");
        $this->pdf->addText($col_s2 + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance_sched2']."</b>");
        $this->pdf->addText($col_s3 + $right_adj, $this->pdf->y,$font_size,"<b>".$cols['balance_sched3']."</b>");
        $this->pdf->setStrokeColor(0,0,0);
        $this->pdf->setLineStyle(1);
        $this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

        $y_head = $this->pdf->y;
        $this->pdf->restoreState();
        $this->pdf->closeObject();
        $this->pdf->addObject($headerObj,'all');

        $this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

        // Print footer
        $bottomFoot = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        $this->pdf->setStrokeColor(.8,.8,.8);
        $this->pdf->setLineStyle(1);
        $this->pdf->line(30,30,565.28,30);
        $this->pdf->restoreState();
        $this->pdf->closeObject();

        $this->pdf->addObject($bottomFoot,'all');
        $this->pdf->ezStartPageNumbers(550,22,6);
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        // Options for each column
        $options_columns = array("invoice_no" => array ('justification' => 'left', 'width'=>$space),
            "invoice_date" => array ('justification' => 'left', 'width'=>$space),
            "due_date" => array ('justification' => 'left', 'width'=>$space + .5 * $space2),
            "orig_amt" => array ('justification' => 'right', 'width'=>$space2),
            "balance" => array ('justification' => 'right', 'width'=>$space2),
            "balance_current" => array ('justification' => 'right', 'width'=>$space2),
            "balance_sched1" => array ('justification' => 'right', 'width'=>$space2),
            "balance_sched2" => array ('justification' => 'right', 'width'=>$space2),
            "balance_sched3" => array ('justification' => 'right', 'width'=>$space2)
        );
        $options_details = array('fontSize'    => 7,
            'width'            => 535.28,//$this->pdf->ez['pageWidth'] - ($this->pdf->ez['leftMargin'] + $this->pdf->ez['rightMargin']) - (45 - $this->pdf->ez['rightMargin']),
            'xPos'             => 60,
            'xOrientation'     => 'right',
            'showLines'        =>  0,
            'lineCol'          =>  array(.8,.8,.8),
            'shaded'           =>  0,
            'showHeadings'     =>  0,
            'rowGap'           =>  5,
            'colGap'           =>  5,
            'titleFontSize'    =>  10,
            'shadeCol'         =>  array(.94,.94,.94),
            'cols'             =>  $options_columns
        );


        $vendors = new vendors($this->current_hash);
        $proposals = new proposals($this->current_hash);

        if (is_array($reports->results)) {
            $k = 0;

            while (list($vendor_hash,$invoice_array) = each($reports->results)) {
                $total_invoice = $total_balance = $total_current = $total_sched1 = $total_sched2 = $total_sched3 = 0;
                $first_time = true;
                $move_y = false;
                while (list($invoice_hash,$details) = each($reports->invoice_detail[$vendor_hash])) {
                    $previous_po_hash = '';
                    foreach ($details as $key => $val) {
                        $deposit_amount = 0;
                        $temp_balance = 0;
                        $dep_left = 0;

                        if(defined('ACTIVATE_AP_DEPOSITS') && ACTIVATE_AP_DEPOSITS == 1 && $val['type'] == 'D'){
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
													WHERE vendor_payables.po_hash = '".$val['po_hash']."' AND vendor_payables.type = 'I' AND vendor_payables.deleted = 0 AND check_register.void != 1
												) -
												(
                                                    SELECT (CASE
                                                           WHEN !ISNULL(vendor_pay_queue.deposit_used)
                                                               THEN SUM(vendor_pay_queue.deposit_used)
                                                               ELSE 0
                                                           END)
                                                    FROM vendor_payables
                                                    LEFT JOIN vendor_pay_queue ON vendor_pay_queue.invoice_hash = vendor_payables.invoice_hash
                                                    WHERE vendor_payables.po_hash = '".$val['po_hash']."' AND vendor_payables.type = 'I' AND vendor_payables.deleted = 0
												)
										) AS total_dep ,
										SUM(vendor_payables.amount - vendor_payables.balance) AS initial_dep
										FROM `vendor_payables`
										WHERE `po_hash` = '".$val['po_hash']."' AND `type` = 'D' AND vendor_payables.deleted = 0");
                            if ($row = $this->db->fetch_assoc($result)) {
                                if ($row['initial_dep']) {
                                    $total_dep = $row['total_dep'];
                                    $initial_dep = $row['initial_dep'];
                                    if ($initial_dep > 0 && $total_dep > 0)
                                        $dep_left = ($initial_dep >= $total_dep ?  $total_dep : 0);

                                }
                            }

                            $result = $this->db->query("SELECT balance FROM vendor_payables where po_hash = '".$val['po_hash']."' and deleted = 0 and type = 'I'");
                            while ($row = $this->db->fetch_assoc($result)) {
                                $temp_balance += $row['balance'];
                            }
                            if($temp_balance > 0 && $dep_left == 0){
                                //unset($details[$i]);
                            }else if(strpos($reports->report_title,'open balance') !== false && $dep_left == 0){

                            }else{
                                $deposit_amount = ($dep_left > 0 ?$dep_left:$val['total_amount_paid']);
                                $val['balance_total'] -= $deposit_amount;
                                if($val['balance_day'] == 0){
                                    $val['balance_current'] -= $deposit_amount;
                                } else if($val['balance_day'] == 1){
                                    $val['balance_sched1'] -= $deposit_amount;
                                } else if($val['balance_day'] == 2){
                                    $val['balance_sched2'] -= $deposit_amount;
                                } else if($val['balance_day'] == 3){
                                    $val['balance_sched3'] -= $deposit_amount;
                                }
                            }
                        }

                        if(defined('ACTIVATE_AP_DEPOSITS') && ACTIVATE_AP_DEPOSITS == 1 && strpos($reports->report_title,'open balance') !== false && $val['type'] == 'D' && $dep_left == 0){
                            //Do Nothing
                        }else{
                            $move_y = true;
                            if($first_time){
                                $first_time = false;
                                $this->pdf->setStrokeColor(0,0,0);
                                if ($reports->current_report['showdetail'] == 2)
                                    $this->pdf->setColor(.8,.8,.8);
                                else {
                                    if ($k % 2 == 0)
                                        $this->pdf->setColor(.8,.8,.8);
                                    else
                                        $this->pdf->setColor(1,1,1);
                                }

                                $this->pdf->setLineStyle(1);
                                $this->pdf->line($this->pdf->ez['leftMargin'],
                                    $this->pdf->y + 16,
                                    $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],
                                    $this->pdf->y + 16
                                );

                                $this->pdf->line($this->pdf->ez['leftMargin'],
                                    $this->pdf->y - 1,
                                    $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],
                                    $this->pdf->y - 1
                                );

                                $this->pdf->setStrokeColor(.7, .7, .7);
                                $this->pdf->setColor(.7, .7, .7);
                                $this->pdf->filledRectangle($this->pdf->ez['leftMargin'],
                                    $this->pdf->y,
                                    $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],
                                    15
                                );

                                $this->pdf->setColor(0, 0, 0);
                                $this->pdf->ezSetDy(15);

                                $std_y_pos = $this->pdf->y;
                                $this->pdf->ezText(stripslashes($invoice_array['vendor_name']),9,array('left' => 1.5));
                                if ($reports->current_report['showdetail'] == 2)
                                    $this->pdf->ezSetDy(-15);
                                else
                                    $std_y_pos = $this->pdf->y;
                            }
                            if ($reports->current_report['showdetail'] == 2 && $previous_po_hash != $val['proposal_hash']){
                                $previous_po_hash = $val['proposal_hash'];
                                $this->pdf->ezText(($details[0]['po_no'] ? "Purchase Order: ".$details[0]['po_no']." ".($details[0]['po_product'] ? $details[0]['po_product'] : NULL) : NULL).($details[0]['proposal_hash'] ? " - Proposal ".$details[0]['proposal_no']." - ".(strlen($details[0]['proposal_descr']) > 45 ? substr(stripslashes($details[0]['proposal_descr']),0,42)."..." : stripslashes($details[0]['proposal_descr'])) : NULL),8, array("aleft"=>50));
                                ($details[0]['po_no']  ? $this->pdf->ezSetDy(-15) : NULL);
                            }

                            $total_balance = bcadd($total_balance,$val['balance_total'],2);
                            $total_invoice = bcadd($total_invoice,$val['total_payable'],2);

                            $total_current = bcadd($total_current,$val['balance_current'],2);
                            $total_sched1 = bcadd($total_sched1,$val['balance_sched1'],2);
                            $total_sched2 = bcadd($total_sched2,$val['balance_sched2'],2);
                            $total_sched3 = bcadd($total_sched3,$val['balance_sched3'],2);

                            $orig_amt = $this->format_dollars_show_zero($val['total_payable']);
                            $bal = $this->format_dollars_show_zero($val['balance_total']);
                            $title = ($val['type'] == 'I' ?
                                    "Invoice: " : ($val['type'] == 'D' ?
                                        "Deposit: " : ($val['type'] == 'C' ?
                                            "Credit: " : "Refund: "))).$val['invoice_no'];

                            if ($reports->current_report['showdetail'] == 2) {
                                $this->pdf->addText($start, $this->pdf->y,$font_size,$title);
                                $this->pdf->addText($start + $space + 30, $this->pdf->y,$font_size,date(DATE_FORMAT,strtotime($val['invoice_date'])));
                                $this->pdf->addText($start + 2 * $space, $this->pdf->y,$font_size,date(DATE_FORMAT,strtotime($val['due_date'])));
                                $this->pdf->addTextWrap($col_orig_amt, $this->pdf->y,$space2, $font_size,$orig_amt,'right');
                                $this->pdf->addTextWrap($col_bal, $this->pdf->y, $space2, $font_size,$bal,'right');
                                $this->pdf->addTextWrap($col_curr, $this->pdf->y, $space2, $font_size,$this->format_dollars($val['balance_current']),'right');
                                $this->pdf->addTextWrap($col_s1, $this->pdf->y, $space2, $font_size,$this->format_dollars($val['balance_sched1']),'right');
                                $this->pdf->addTextWrap($col_s2, $this->pdf->y, $space2, $font_size,$this->format_dollars($val['balance_sched2']),'right');
                                $this->pdf->addTextWrap($col_s3, $this->pdf->y, $space2, $font_size,$this->format_dollars($val['balance_sched3']),'right');
                                $this->pdf->ezSetDy(-10);
                            }
                        }
                    }
                }
                $grand_total['invoice'] = bcadd($grand_total['invoice'],$total_invoice,2);
                $grand_total['balance'] = bcadd($grand_total['balance'],$total_balance,2);
                $grand_total['current'] = bcadd($grand_total['current'],$total_current,2);
                $grand_total['sched1'] = bcadd($grand_total['sched1'],$total_sched1,2);
                $grand_total['sched2'] = bcadd($grand_total['sched2'],$total_sched2,2);
                $grand_total['sched3'] = bcadd($grand_total['sched3'],$total_sched3,2);

                // Display totals for each schedule that has a balance
                if ($reports->current_report['showdetail'] == 1)
                    $this->pdf->y = $std_y_pos;

                $this->pdf->setLineStyle(1);
                if ($total_invoice != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                        $this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_orig_amt + $right_adj,$this->pdf->y,$col_orig_amt + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_orig_amt,($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars_show_zero($total_invoice), 'right');
                }
                if ($total_balance != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                        $this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_bal + $right_adj, $this->pdf->y,$col_bal + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_bal, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars_show_zero($total_balance), 'right');
                }
                if ($total_current != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                        $this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_curr + $right_adj, $this->pdf->y,$col_curr + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_curr, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars($total_current), 'right');
                }
                if ($total_sched1 != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                        $this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_s1 + $right_adj, $this->pdf->y,$col_s1 + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_s1, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars($total_sched1), 'right');
                }
                if ($total_sched2 != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                        $this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_s2 + $right_adj, $this->pdf->y,$col_s2 + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_s2, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars($total_sched2), 'right');
                }
                if ($total_sched3 != 0) {
                    if ($reports->current_report['showdetail'] == 2) {
                        $this->pdf->setColor(.7, .7, .7);
                        $this->pdf->line($col_s3 + $right_adj, $this->pdf->y,$col_s3 + $space2, $this->pdf->y);
                    }
                    $this->pdf->setColor(0, 0, 0);
                    $this->pdf->addTextWrap($col_s3, ($reports->current_report['showdetail'] == 1 ? $this->pdf->y : $this->pdf->y - 10), $space2, 7, $this->format_dollars($total_sched3), 'right');
                }

                if($move_y){
                    $this->pdf->ezSetDy($reports->current_report['showdetail'] == 2 ? -35 : -22);
                }

                $k++;
            }

            // Calculate and display the grand total of each aging schedule.
            $grand_total_all = $grand_total['current'] + $grand_total['sched1'] + $grand_total['sched2'] + $grand_total['sched3'];
            $this->pdf->setStrokeColor(0,0,0);
            $this->pdf->setLineStyle(1,NULL,NULL);

            $this->pdf->line($col_orig_amt + $right_adj, $this->pdf->y,$col_orig_amt + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_orig_amt, $this->pdf->y - 15, $space2, 7, $this->format_dollars_show_zero($grand_total['invoice']), 'right');
            $this->pdf->line($col_bal + $right_adj, $this->pdf->y,$col_bal + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_bal, $this->pdf->y - 15, $space2, 7, $this->format_dollars_show_zero($grand_total['balance']), 'right');
            $this->pdf->line($col_curr + $right_adj, $this->pdf->y,$col_curr + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_curr, $this->pdf->y - 15, $space2, 7, $this->format_dollars($grand_total['current']), 'right');
            $this->pdf->line($col_s1 + $right_adj, $this->pdf->y,$col_s1 + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_s1, $this->pdf->y - 15, $space2, 7, $this->format_dollars($grand_total['sched1']), 'right');
            $this->pdf->line($col_s2 + $right_adj, $this->pdf->y,$col_s2 + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_s2, $this->pdf->y - 15, $space2, 7, $this->format_dollars($grand_total['sched2']), 'right');
            $this->pdf->line($col_s3 + $right_adj, $this->pdf->y,$col_s3 + $space2, $this->pdf->y);
            $this->pdf->addTextWrap($col_s3, $this->pdf->y - 15, $space2, 7, $this->format_dollars($grand_total['sched3']), 'right');

        }

    }

/**
 * Generates a pdf document of a customer balance summary report given the report_hash.
 *
 * @param String $report_hash
 */
	function customer_balance($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_customer_balance();

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Customer Balance Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);
			switch ($reports->current_report['timeframe']) {
			// All dates
			case '*':
				$report_title = "Invoices Dated Before ".date(DATE_FORMAT);
			break;

			// Today
			case 1:
				$report_title = "Invoices Dated ".date(DATE_FORMAT);
			break;

			// Yesterday
			case 2:
				$report_title = "Invoices Dated ".date(DATE_FORMAT,strtotime(date("Y-m-d")." -1 day"));
			break;

			// Time frames that have start and end dates
			case 3:
			case 4:
			case 5:
			case 6:
			case 7:
			case 8:
				$report_title = "Invoices Dated Between ".date(DATE_FORMAT,strtotime($reports->report_date_start))." and ".date(DATE_FORMAT, strtotime($reports->report_date_end));
			break;

			//Date range
			case 9:
			if ($reports->report_date_start && $reports->report_date_end)
				$report_title = "Invoices Dated Between ".date(DATE_FORMAT, strtotime($reports->report_date_start))." and ".date(DATE_FORMAT, strtotime($reports->report_date_end));
			else
				$report_title = "Invoices Dated Between ".date(DATE_FORMAT, strtotime($reports->report_date_start))." and ".date(DATE_FORMAT);
			break;
		}

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Customer Balance Report",16,array('justification' => 'center'));
		$this->pdf->ezText($report_title,12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array("date"	  =>	"Date",
						"type" 		=> "Type",
						"space"		=> "",
						"space2"	=> "",
					    "due_date" 	=> "Due Date",
						"amount" 	=> "Amount",
						"balance" 	=> "Balance");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$space = 90;  // space between invoice detail columns
		$space2 = 70; // space between aging schedule columns
		$col_s2 = $start + 4 * $space;
		$col_s3 = $start + 5 * $space;
		$right_adj = 15; // adjustment for right justified columns (aging schedules)
		$font_size = 8;
		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addText($start, $this->pdf->y,$font_size,"<b>".$cols['date']."</b>");
		$this->pdf->addText($start + $space, $this->pdf->y,$font_size,"<b>".$cols['type']."</b>");
		$this->pdf->addText($start + 3 * $space + 15, $this->pdf->y,$font_size,"<b>".$cols['due_date']."</b>");
		$this->pdf->addText($start + 4 * $space + 15, $this->pdf->y,$font_size,"<b>".$cols['amount']."</b>");
		$this->pdf->addText($start + 5 * $space + 15, $this->pdf->y,$font_size,"<b>".$cols['balance']."</b>");
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		// Options for each column
		$options_columns =
						array ("date" => array ('justification' => 'left', 'width'=>$space),
							"type" => array ('justification' => 'left', 'width'=>$space),
							"space" => array ('justification' => 'right', 'width'=>$space2 - $right_adj),
							"due_date" => array ('justification' => 'right', 'width'=>$space),
							"amount" => array ('justification' => 'right', 'width'=>$space),
							"balance" => array ('justification' => 'right', 'width'=>$space - 5));
		$options_details =
						array('fontSize'	=> 7,
						 'width'			=> 535.28,
						 'xPos'				=> 60,
						 'xOrientation'		=> 'right',
						 'showLines'		=>  0,
						 'lineCol'			=>	array(.8,.8,.8),
						 'shaded'			=>  0,
						 'showHeadings' 	=>  0,
						 'rowGap'			=>	5,
						 'colGap'			=>	5,
						 'titleFontSize'	=>	10,
						 'shadeCol'			=>  array(.94,.94,.94),
						 'cols'				=>	$options_columns
						);


		$customers = new customers($this->current_hash);
		$vendors = new vendors($this->current_hash);
		$proposals = new proposals($this->current_hash);
		$data = $reports->results;

		if (is_array($data)) {
			$k = 0;
			$total = $total_amount = $total_balance = 0;
			while (list($customer_hash, $proposal_array) = each($reports->results)) {
				$customer_amount = $customer_balance  = 0;

				reset($proposal_array);
				$current = current($proposal_array);

				$bottom_line_y = $this->pdf->y;
				$top_line_y = $bottom_line_y + 15;
				$x = 45;

				$this->pdf->setStrokeColor(.7,.7,.7);
				$this->pdf->setColor(.8,.8,.8);
				$this->pdf->setLineStyle(1);
				$this->pdf->line($this->pdf->ez['leftMargin'],$top_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$top_line_y);
				$this->pdf->line($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$bottom_line_y);
				$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],15);

				$this->pdf->setColor(0,0,0);
				$this->pdf->ezSetDy(15);
				$this->pdf->ezText(($current[0]['customer_name'] ? stripslashes($current[0]['customer_name']) : stripslashes($current[0]['vendor_name'])), 12);
				$this->pdf->ezSetDy(-10);


				while (list($proposal_hash,$details) = each($proposal_array)) {
					// Calculate totals
					$details_f = array();
					$amt = $bal = 0;
					for ($i = 0; $i < count($details); $i++) {
						if ($details[$i]['type'] == 'D') {
								$amt -= $details[$i]['amount'];
								$bal -= $details[$i]['balance'];
								$customer_amount -= $details[$i]['amount'];
								$customer_balance -= $details[$i]['balance'];
							} else {
								$amt += $details[$i]['amount'];
								$bal += $details[$i]['balance'];
								$customer_amount += $details[$i]['amount'];
								$customer_balance += $details[$i]['balance'];
							}

						// Rebuild $details array with correct formatting and store in $details_f
							$details_f[] = array("date" 	=> $details[$i]['invoice_date'],
										"type" 				=> ($details[$i]['type'] == 'I' ? "Invoice ".$details[$i]['invoice_no'] : "Deposit"),
										"due_date" 			=> ($details[$i]['type'] != 'D' ? ((is_numeric($details[$i]['payment_terms']) && $details[$i]['payment_terms'] == 0) || (!is_numeric($details[$i]['payment_terms']) && DEFAULT_CUST_PAY_TERMS == 0) ? " Upon Receipt" : date(DATE_FORMAT,strtotime($details[$i]['invoice_date']." +".(is_numeric($details[$i]['payment_terms']) ? $details[$i]['payment_terms'] : DEFAULT_CUST_PAY_TERMS)." days"))) : NULL),
										"amount"			=> $this->format_dollars_show_zero(($details[$i]['type'] == 'D' ? $details[$i]['amount'] * -1 : $details[$i]['amount'])),
										"balance"		 	=> $this->format_dollars_show_zero(($details[$i]['type'] == 'D' ? $details[$i]['balance'] * -1 : $details[$i]['balance'])));

					}
					if ($reports->current_report['showdetail'] == 2) {
						$this->pdf->ezText(($proposal_hash ? "Proposal: ".$details[0]['proposal_no']." - ".$details[0]['proposal_descr'] : "Unapplied Deposits"),8, array("aleft"=>50));
						$this->pdf->ezTable($details_f, $cols, "", $options_details);
						// Display total for proposal if multiple invoices
						if (count($details_f) > 1) {
							$this->pdf->setStrokeColor(.8,.8,.8);
							$this->pdf->setLineStyle(.5);
							$this->pdf->line($col_s2 + 10, $this->pdf->y,$col_s3 + $space2 - 20, $this->pdf->y);
							$this->pdf->addTextWrap($col_s2 - 10, $this->pdf->y - 15, $space2 - 5, 7, $this->format_dollars($amt), 'right');
							$this->pdf->addTextWrap($col_s3 - 15, $this->pdf->y - 15, $space2 - 5, 7, $this->format_dollars($bal), 'right');
							$this->pdf->ezsetdy(-20);
						}
					}
				}

				$total_amount += $customer_amount;
				$total_balance += $customer_balance;

				// Display totals for amount and balance
				$this->pdf->setStrokeColor(.8,.8,.8);
				$this->pdf->setLineStyle(.5);
				if ($reports->current_report['showdetail'] == 2)
					$this->pdf->line($col_s2 + 10, $this->pdf->y,$col_s3 + $space2 - 20, $this->pdf->y);
				$this->pdf->addTextWrap($col_s2 - 10, $this->pdf->y - 10, $space2 - 5, 7, "<b>".$this->format_dollars($customer_amount)."</b>", 'right');
				$this->pdf->addTextWrap($col_s3 - 15, $this->pdf->y - 10, $space2 - 5, 7, "<b>".$this->format_dollars($customer_balance)."</b>", 'right');
				$this->pdf->ezSetDy(-30);
			}

			// Calculate and display the total customer balance
			$this->pdf->setStrokeColor(.8,.8,.8);
			$this->pdf->setLineStyle(1,NULL,NULL);
			$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y);
			$this->pdf->addTextWrap($this->pdf->ez['leftMargin'],$this->pdf->y - 15,300, 10,"<b>Total Customer Balance</b>");
			$this->pdf->addTextWrap($col_s2 - 10, $this->pdf->y - 15, $space2 - 5, 10, "<b>".$this->format_dollars($total_amount)."</b>", 'right');
			$this->pdf->addTextWrap($col_s3 - 15, $this->pdf->y - 15, $space2 - 5, 10, "<b>".$this->format_dollars($total_balance)."</b>", 'right');
		}
	}

/**
 * Generates a pdf document of a cash requirements report given the report_hash.
 *
 * @param String $report_hash
 */
	function cash_requirements($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_cash_requirements();

		$sched_start_date = $reports->current_report['sched_start_date'];
		$day_sched = $reports->current_report['day_schedule'];

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Cash Requirements Report ";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Cash Requirements Report",16,array('justification' => 'center'));
		$this->pdf->ezText("As of ".date(DATE_FORMAT,strtotime($sched_start_date)),12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addTextWrap(375, $this->pdf->y + 60,200, 8, "Printed on ".date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT))." at ".date(TIMESTAMP_FORMAT),'right');
		//  Column headers
		//date(DATE_FORMAT,strtotime($val['invoice_date']." +".($val['payment_terms'] ? $val['payment_terms'] : DEFAULT_CUST_PAY_TERMS)." days")),
		$cols = array(	"proposal_no"	=>	"Proposal No.",
						"invoice_no"	=>	"Invoice No.",
						"invoice_date"	=>	"Invoice Date",
						"due_date" 		=>	"Due Date",
						"expected" 		=>	"Expected",
						"past"			=>	"Past",
						"sched1"		=>	date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($sched_start_date." +". 1 * $day_sched." days")),
						"sched2"		=>	date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($sched_start_date." +". 2 * $day_sched." days")),
						"sched3"		=>	date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($sched_start_date." +". 3 * $day_sched." days")),
						"future"		=>	"Future");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 20; // X coordinate of first column
		$space = 70;  // space between invoice detail columns
		$space2 = 57;
		$right_adj = 15;
		$col2 = $start + $space;
		$col3 = $col2 + $space;
		$col4 = $col3 + $space2;
		$col5 = $col4 + 30;
		$col6 = $col5 + $space2;
		$col7 = $col6 + $space2;
		$col8 = $col7 + $space2;
		$col9 = $col8 + $space2;
		$font_size = 7;

		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addTextWrap($start, $this->pdf->y,$space, $font_size,"<b>".$cols['proposal_no']."</b>");
		$this->pdf->addTextWrap($col2, $this->pdf->y,$space, $font_size,"<b>".$cols['invoice_no']."</b>");
		$this->pdf->addTextWrap($col3, $this->pdf->y,$space,$font_size,"<b>".$cols['invoice_date']."</b>");
		$this->pdf->addTextWrap($col4, $this->pdf->y,$space,$font_size,"<b>".$cols['due_date']."</b>");
		$this->pdf->addTextWrap($col5, $this->pdf->y, $space2, $font_size,"<b>".$cols['past']."</b>",'right');
		$this->pdf->addTextWrap($col6, $this->pdf->y, $space2, $font_size,"<b>".$cols['sched1']."</b>",'right');
		$this->pdf->addTextWrap($col7, $this->pdf->y, $space2, $font_size,"<b>".$cols['sched2']."</b>", 'right');
		$this->pdf->addTextWrap($col8, $this->pdf->y, $space2, $font_size,"<b>".$cols['sched3']."</b>",'right');
		$this->pdf->addTextWrap($col9, $this->pdf->y, $space2, $font_size,"<b>".$cols['future']."</b>",'right');
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		if (is_array($reports->results)) {
			reset($reports->results);
			$k = 0;
			$font_size = 7;
			while (list($customer_hash, $invoice_array) = each($reports->results)) {
				$total_past = $total_sched1 = $total_sched2 = $total_sched3 = $total_future = 0;

				$bottom_line_y = $this->pdf->y;
				$top_line_y = $bottom_line_y + 15;
				$x = 45;

				$this->pdf->setStrokeColor(.7,.7,.7);
				$this->pdf->setColor(.8,.8,.8);
				$this->pdf->setLineStyle(1);
				$this->pdf->line($this->pdf->ez['leftMargin'],$top_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$top_line_y);
				$this->pdf->line($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$bottom_line_y);
				$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],15);

				$this->pdf->setColor(0,0,0);
				$this->pdf->ezSetDy(15);
				$this->pdf->ezText("  ".$invoice_array['vendor_name'], 12);
				$this->pdf->ezSetDy(-10);

				for ($i = 0; $i < count($invoice_array['pay_schedule']); $i++) {
					$invoice_amt = array();
					$is_credit = ($invoice_array['pay_schedule'][$i]['invoice']['type'] == 'C');
						switch ($invoice_array['pay_schedule'][$i]['sched']) {
							case 'past':
								if ($is_credit)
									$total_past -= $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								else
									$total_past += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['past'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;

							case '1':
								if ($is_credit)
									$total_sched1 -= $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								else
									$total_sched1 += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['sched1'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;

							case '2':
								if ($is_credit)
									$total_sched2 -= $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								else
									$total_sched2 += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['sched2'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;

							case '3':
								if ($is_credit)
									$total_sched3 -= $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								else
									$total_sched3 += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['sched3'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;

							case 'future':
								if ($is_credit)
									$total_future -= $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								else
									$total_future += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['future'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;
						}

							if ($reports->current_report['showdetail'] == 2) {
								$this->pdf->addTextWrap($start, $this->pdf->y,$space, $font_size, ($invoice_array['pay_schedule'][$i]['invoice']['proposal_no'] ? "Proposal ".$invoice_array['pay_schedule'][$i]['invoice']['proposal_no'] : NULL));
								$this->pdf->addTextWrap($col2, $this->pdf->y,$space, $font_size, ($invoice_array['pay_schedule'][$i]['invoice']['type'] == 'I' ? "Invoice: " : ($invoice_array['pay_schedule'][$i]['invoice']['type'] == 'D' ? "Deposit: " : ($invoice_array['pay_schedule'][$i]['invoice']['type'] == 'C' ? "Credit: " : "Customer Refund: "))).$invoice_array['pay_schedule'][$i]['invoice']['invoice_no']);
								$this->pdf->addTextWrap($col3, $this->pdf->y,$space,$font_size,date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($invoice_array['pay_schedule'][$i]['invoice']['invoice_date'])));
								$this->pdf->addTextWrap($col4, $this->pdf->y,$space,$font_size,date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($invoice_array['pay_schedule'][$i]['invoice']['due_date'])));
								$this->pdf->addTextWrap($col5, $this->pdf->y, $space2, $font_size,$this->format_dollars($is_credit ? (-1 * $invoice_amt['past']) : $invoice_amt['past']),'right');
								$this->pdf->addTextWrap($col6, $this->pdf->y, $space2, $font_size,$this->format_dollars($is_credit ? (-1 * $invoice_amt['sched1']) : $invoice_amt['sched1']),'right');
								$this->pdf->addTextWrap($col7, $this->pdf->y, $space2, $font_size,$this->format_dollars($is_credit ? (-1 * $invoice_amt['sched2']) : $invoice_amt['sched2']),'right');
								$this->pdf->addTextWrap($col8, $this->pdf->y, $space2, $font_size,$this->format_dollars($is_credit ? (-1 * $invoice_amt['sched3']) : $invoice_amt['sched3']),'right');
								$this->pdf->addTextWrap($col9, $this->pdf->y, $space2, $font_size,$this->format_dollars($is_credit ? (-1 * $invoice_amt['future']) : $invoice_amt['future']),'right');
								$this->pdf->ezSetDy(-10);
							}

				}
					$total = $total_past + $total_sched1 + $total_sched2 + $total_sched3 + $total_future;

					$grand_total['past'] += $total_past;
					$grand_total['sched1'] += $total_sched1;
					$grand_total['sched2'] += $total_sched2;
					$grand_total['sched3'] += $total_sched3;
					$grand_total['future'] += $total_future;

					// Display totals for each schedule that has a balance
					$this->pdf->setStrokeColor(0,0,0);
					$this->pdf->setLineStyle(.5,'','',array(2,1));
					$s = 5;
					$h = 10;

					if ($total_past != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col5 + $right_adj, $this->pdf->y,$col5 + $space2, $this->pdf->y);
						$this->pdf->addTextWrap($col5, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_past), 'right');
					}
					if ($total_sched1 != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col6 + $right_adj, $this->pdf->y,$col6 + $space2, $this->pdf->y);
						$this->pdf->addTextWrap($col6, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_sched1), 'right');
					}
					if ($total_sched2 != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col7 + $right_adj, $this->pdf->y,$col7 + $space2, $this->pdf->y);
						$this->pdf->addTextWrap($col7, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_sched2), 'right');
					}
					if ($total_sched3 != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col8 + $right_adj, $this->pdf->y,$col8 + $space2, $this->pdf->y);
						$this->pdf->addTextWrap($col8, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_sched3), 'right');
					}
					if ($total_future != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col9 + $right_adj, $this->pdf->y,$col9 + $space2, $this->pdf->y);
						$this->pdf->addTextWrap($col9, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_future), 'right');
					}

					$this->pdf->ezSetDy(-30);

			}

			// Calculate and display the grand total of each aging schedule.
			$grand_total_all = $grand_total['past'] + $grand_total['sched1'] + $grand_total['sched2'] + $grand_total['sched3'] + $grand_total['future'];

			// Display totals for each column
			$bottom_line_y = $this->pdf->y;
			$top_line_y = $bottom_line_y + 20;
			$s = 5;

			$this->pdf->setStrokeColor(.7,.7,.7);
			$this->pdf->setColor(.8,.8,.8);
			$this->pdf->setLineStyle(1);
			$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],15);
			$this->pdf->setColor(0,0,0);
			$this->pdf->addTextWrap($col5, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['past'])."</b>", 'right');
			$this->pdf->addTextWrap($col6, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['sched1'])."</b>", 'right');
			$this->pdf->addTextWrap($col7, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['sched2'])."</b>", 'right');
			$this->pdf->addTextWrap($col8, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['sched3'])."</b>", 'right');
			$this->pdf->addTextWrap($col9, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['future'])."</b>", 'right');
			$this->pdf->ezSetDy(-40);

		}



	}

/**
 * Generates a pdf document of a status report given the report_hash.
 *
 * @param String $report_hash
 */
	function status_report($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		//TODO what method should I call? reports class does not handle status reports
		$reports->doit_status_report();
		echo "<pre> report data is ".print_r($reports->results,1)."</pre>";
		die();
	}

/**
 * Generates a pdf document of a cash disbursements report given the report_hash.
 *
 * @param String $report_hash
 */
	function cash_disp($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_cash_disp();

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Cash Disbursements Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		switch ($reports->current_report['timeframe']) {
			//Today
			case 1:
			$report_title = "Checks dated ".date(DATE_FORMAT);
			break;

			//Yesterday
			case 2:
				$report_title = "Checks dated ".date(DATE_FORMAT,strtotime(date("Y-m-d")." -1 day"));
			break;

			//This week
			case 3:
				$week_start = date(DATE_FORMAT,strtotime(date("Y-m-d")." -".date("w")." days"));
				$week_end = date(DATE_FORMAT,strtotime(date("Y-m-d")." +".(6 - date("w"))." days"));
				$report_title = "Checks dated between ".$week_start." and ".$week_end;
			break;

			//Last week
			case 4:
				$last_week = date(DATE_FORMAT,strtotime(date("Y-m-d")." -1 week"));
				$week_start = date(DATE_FORMAT,strtotime($last_week." -".date("w")." days"));
				$week_end = date(DATE_FORMAT,strtotime($last_week." +".(6 - date("w",strtotime($last_week)))." days"));
				$report_title = "Checks dated between ".$week_start." and ".$week_end."";
			break;

			//This month
			case 5:
				$report_title = "Checks dated between ".date(DATE_FORMAT, strtotime(date("Y-m-01")))." and ".date(DATE_FORMAT,strtotime(date("Y-m-".date("t"))));
			break;

			//Last Month
			case 6:
				if (date("m") == 1) {
					$month = 12;
					$year = date("Y") - 1;
				} else {
					$month = date("m") - 1;
					$year = date("Y");
				}
				$report_title = "Checks dated between ".date(DATE_FORMAT,strtotime($year."-".$month."-01"))." and ".date(DATE_FORMAT,strtotime($year."-".$month."-".date("t",strtotime($year."-".$month."-01"))));
			break;

			//This qtr
			case 7:
				$current_qtr = get_quarter(date("Y-m-d"));
				if ($current_qtr == 1) {
					$previous_qtr = get_quarter(date("Y-m-d",strtotime(date("Y-m-d")." -4 months")));
					list($start,$end) = get_quarter_dates($previous_qtr,date("Y",strtotime(date("Y-m-d")." -4 months")));
				} else
					list($start,$end) = get_quarter_dates($current_qtr - 1,date("Y"));

					$report_title = "Checks dated between ".date(DATE_FORMAT,strtotime($start))." and ".date(DATE_FORMAT, strtotime($end));
			break;

			//Last qtr
			case 8:
				$current_qtr = get_quarter(date("Y-m-d"));
				if ($current_qtr == 1) {
					$previous_qtr = get_quarter(date("Y-m-d",strtotime(date("Y-m-d")." -4 months")));
					list($start,$end) = get_quarter_dates($previous_qtr,date("Y",strtotime(date("Y-m-d")." -4 months")));
				} else
					list($start,$end) = get_quarter_dates($current_qtr - 1,date("Y"));

				$report_title = "Checks dated between ".date(DATE_FORMAT, strtotime($start))." and ".date(DATE_FORMAT,strtotime($end));
			break;

			//This period
			case 9:
				$period_info = get_period_info(date("Y-m-d"));
				$report_title = "Checks dated between ".date(DATE_FORMAT, strtotime($period_info['period_start']))." and ".date(DATE_FORMAT, strtotime($period_info['period_end']));
			break;

			//Last period
			case 10:
				$period_info = get_previous_period(date("Y-m-d"));
				$report_title = "Checks dated between ".date(DATE_FORMAT, strtotime($period_info['period_start']))." and ".date(DATE_FORMAT, strtotime($period_info['period_end']));
			break;

			//This year
			case 11:
				$report_title = "Checks dated ".date("Y");
			break;

			//Last year
			case 12:
				 $report_title = "Checks dated ".(date("Y") - 1);
			break;

			//To Date
			case '*':
				$report_title = "Checks dated through ".date(DATE_FORMAT);
			break;

			//Date range
			case 13:
			if ($reports->current_report['sort_from_date'] && $reports->current_report['sort_to_date'])
				$report_title = "Checks dated between ".date(DATE_FORMAT, strtotime($reports->current_report['sort_from_date']))." and ".date(DATE_FORMAT, strtotime($reports->current_report['sort_to_date']));
			else
    			$report_title = "Checks dated between ".date(DATE_FORMAT, strtotime($reports->current_report['sort_from_date']))." and ".date(DATE_FORMAT);
			break;

		}

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Cash Disbursements Report",16,array('justification' => 'center'));
		$this->pdf->ezText($report_title,12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array("payee"	  		=> "Payee",
						"check_no"		=> "Check No.",
						"check_date"	=> "Check Date",
						"check_amt"		=> "Check Amount");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$col1 = $start;
		$col2 = 200;
		$col3 = 350;
		$col4 = 450;
		$width1 = 150;
		$width2 = 100;
		$right_adj = 15; // adjustment for right justified columns (aging schedules)
		$font_size_headers = 9;

		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addTextWrap($col1, $this->pdf->y,$width1,$font_size_headers,"<b>".$cols['payee']."</b>",'left');
		$this->pdf->addTextWrap($col2, $this->pdf->y,$width1,$font_size_headers,"<b>".$cols['check_no']."</b>",'left');
		$this->pdf->addTextWrap($col3, $this->pdf->y,$width2,$font_size_headers,"<b>".$cols['check_date']."</b>",'right');
		$this->pdf->addTextWrap($col4, $this->pdf->y,$width2,$font_size_headers,"<b>".$cols['check_amt']."</b>",'right');

		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$font_size_cust = 8;
		$font_size_detail = 7;
		$this->pdf->ezSetDy(-5);
		$this->pdf->setStrokeColor(.8,.8,.8);
		reset($reports->payment);
		if (is_array($reports->payment)) {
			$total_payments = 0;
			while (list($vendor_hash,$check_array) = each($reports->payment)) {
				$displayOnce = true;
				while (list($check_no,$check_data) = each($check_array)) {
					if ($displayOnce) {
						// Print vendor or customer name for Trac #1514
						$vendor_name = ($check_data[0]['vendor_name'] ? $check_data[0]['vendor_name'] : $check_data[0]['customer_name']);
						$this->pdf->addTextWrap($col1,$this->pdf->y,$width1,$font_size_cust + 1,stripslashes($vendor_name));
						$displayOnce = false;
						$this->pdf->ezSetDy(-15);
					}
					for ($i = 0; $i < count($check_data); $i++) {
						$total_payments += $check_data[$i]['total_payment'];
						$this->pdf->addTextWrap($col2,$this->pdf->y,$width1,$font_size_cust,($check_data[$i]['check_no'] ? $check_data[$i]['check_no'] : "Unassigned") );
						$this->pdf->addTextWrap($col3,$this->pdf->y,$width2,$font_size_cust,date(DATE_FORMAT,strtotime($check_data[$i]['check_date'])),'right');
						$this->pdf->addTextWrap($col4,$this->pdf->y,$width2,$font_size_cust,$this->format_dollars_show_zero($check_data[$i]['total_payment']),'right');
						$this->pdf->ezSetDy(-10);
						if ($reports->current_report['detail']) {
							for ($j = 0; $j < count($reports->detail[$vendor_hash][$check_data[$i]['check_no']]['invoice_no']); $j++) {
								$invoice_no = $reports->detail[$vendor_hash][$check_data[$i]['check_no']]['invoice_no'][$j];
								$this->pdf->addTextWrap($col2,$this->pdf->y,$width1,$font_size_detail, $invoice_no,'right');
								$this->pdf->addTextWrap($col3,$this->pdf->y,$width2,$font_size_detail,date(DATE_FORMAT,strtotime($reports->detail[$vendor_hash][$check_data[$i]['check_no']]['date'][$j])),'right');
								$this->pdf->addTextWrap($col4,$this->pdf->y,$width2,$font_size_detail,$this->format_dollars_show_zero($reports->detail[$vendor_hash][$check_data[$i]['check_no']]['amount'][$j]),'right');
								$this->pdf->ezSetDy(-10);
							}
						}
					}
					$this->pdf->ezSetDy(-10);
				}
				$this->pdf->line($this->pdf-> ez['leftMargin'],$this->pdf->y + 10,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y + 10);
				$this->pdf->ezSetDy(-5);
			}
			$this->pdf->addTextWrap($col4,$this->pdf->y,$width2,11,"<b>".$this->format_dollars($total_payments)."</b>",'right');
		}


	}

/**
 * Generates a pdf document of a invoiced sales report given the report_hash.
 *
 * @param String $report_hash

	function invoiced_sales($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		$_POST['from_report'] = 1;

		// Populate data for reports object.
		$reports->doit_invoiced_sales_report();
		echo "<pre> report data is ".print_r($reports->results,1)."</pre>";
		die();

	}

/**
 * Generates a pdf document of a bookings report given the report_hash.
 *
 * @param String $report_hash

	function bookings_report($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		$_POST['from_report'] = 1;

		// Populate data for reports object.
		$reports->doit_bookings_report();
		//TODO Implement Bookings report
		echo "<pre> bookings report data is ".print_r($reports->results,1)."</pre>";
		die();

	}


 * Generates a pdf document of a commissions paid report given the report_hash.
 *
 * @param String $report_hash
 */

	function commissions_paid_report($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_commissions_paid_report();


		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Commissions Paid Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		if ($reports->report_date_start && $reports->report_date_end)
			$report_title = "Invoices Dated Between ".date(DATE_FORMAT, strtotime($reports->report_date_start))." and ".date(DATE_FORMAT, strtotime($reports->report_date_end));
		else
			$report_title = "Invoices Dated Between ".date(DATE_FORMAT, strtotime($reports->report_date_start))." and ".date(DATE_FORMAT);

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Commissions Paid Report",16,array('justification' => 'center'));
		$this->pdf->ezText($report_title,12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array("product"	  	=> "Product",
						"sell"		=> "Sell",
					    "cost" 		=> "Cost",
						"profit" 	=> "Profit",
						"gp" 		=> "GP Margin");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$space = 180;
		$space2 = 80;
		$right_adj = 25; // adjustment for right justified columns
		$col2 = $start + $space + $right_adj;
		$col3 = $col2 + $space2;
		$col4 = $col3 + $space2;
		$col5 = $col4 + $space2;
		$font_size = 8;

		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addText($start, $this->pdf->y,$font_size,"<b>".$cols['product']."</b>");
		$this->pdf->addText($col2 + 5, $this->pdf->y,$font_size,"<b>".$cols['sell']."</b>");
		$this->pdf->addText($col3 + 5, $this->pdf->y,$font_size,"<b>".$cols['cost']."</b>");
		$this->pdf->addText($col4 + 5, $this->pdf->y,$font_size,"<b>".$cols['profit']."</b>");
		$this->pdf->addText($col5 + 5, $this->pdf->y,$font_size,"<b>".$cols['gp']."</b>");
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		// Options for each column
		$options_columns =
						array ("product" => array ('justification' => 'left', 'width'=>$space),
							"sell" => array ('justification' => 'right', 'width'=>$space2),
							"cost" => array ('justification' => 'right', 'width'=>$space2),
							"profit" => array ('justification' => 'right', 'width'=>$space2),
							"gp" => array ('justification' => 'right', 'width'=>$space2));
		$options_details =
						array('fontSize'	=> 8,
						 'width'			=> 535.28,
						 'xPos'				=> 60,
						 'xOrientation'		=> 'right',
						 'showLines'		=>  0,
						 'lineCol'			=>	array(.8,.8,.8),
						 'shaded'			=>  0,
						 'showHeadings' 	=>  0,
						 'rowGap'			=>	5,
						 'colGap'			=>	5,
						 'titleFontSize'	=>	10,
						 'shadeCol'			=>  array(.94,.94,.94),
						 'cols'				=>	$options_columns
						);

		if (is_array($reports->results)) {
			$k = 0;
			$total = $total_amount = $total_balance = 0;
			reset($reports->results);
			while(list($sales_hash,$proposal_array) = each($reports->results)) {
				$commission_paid = $total_commission_paid = $previous_commission_paid = $current_commission_paid = $grand_total_sell = $grand_total_cost = $k = 0;

				$bottom_line_y = $this->pdf->y;
				$top_line_y = $bottom_line_y + 15;
				$x = 45;

				$this->pdf->setStrokeColor(.7,.7,.7);
				$this->pdf->setColor(.8,.8,.8);
				$this->pdf->setLineStyle(1);
				$this->pdf->line($this->pdf->ez['leftMargin'],$top_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$top_line_y);
				$this->pdf->line($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$bottom_line_y);
				$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],15);
				$this->pdf->setColor(0,0,0);
				$this->pdf->addText($this->pdf->ez['leftMargin'],$bottom_line_y + 2, 10, $reports->user_name($sales_hash));

				$total_overhead = 0;
				while (list($commission_hash,$product_array) = each($proposal_array)) {
					// Calculate totals
					$details_f = array();
					$this->pdf->ezSetDy(-15);
					$commission_paid = $total_sell = $total_cost = $total_profit = 0;
					$this->pdf->addText(50,$this->pdf->y,8,"Proposal: ".$product_array[0]['proposal_no']." - ".$product_array[0]['proposal_descr']);
					$this->pdf->ezSetDy(-10);
					$this->pdf->addText(50,$this->pdf->y,8,stripslashes($product_array[0]['customer_name']));
					$this->pdf->ezSetDy(-5);
					for ($i = 0; $i < count($product_array); $i++) {
						// Rebuild $details array with correct formatting and store in $details_f
						$details_f[] = array(
    						"product" 	    => ( $product_array[$i]['product_name'] ? $product_array[$i]['product_name'] : "Miscellaneous" ),
							"sell" 			=> $this->format_dollars_show_zero($product_array[$i]['total_product_sell'], 2),
							"cost" 			=> $this->format_dollars_show_zero($product_array[$i]['total_product_cost'], 2),
							"profit"		=> $this->format_dollars_show_zero( bcsub($product_array[$i]['total_product_sell'], $product_array[$i]['total_product_cost'], 2), 2),
							"gp"			=>
        						(float)bcmul( math::gp_margin( array(
            						'cost'    =>  $product_array[$i]['total_product_cost'],
            						'sell'    =>  $product_array[$i]['total_product_sell']
                                ) ), 100, 4) . "%"
						);

						$total_sell = bcadd($total_sell, $product_array[$i]['total_product_sell'], 2);
						$total_cost = bcadd($total_cost, $product_array[$i]['total_product_cost'], 2);
					}
					

                        $commission_paid = $product_array[0]['amount'];
                        $total_commission_paid = bcadd($total_commission_paid,bcadd($commission_paid,$product_array[0]['previous_amount']));
                        $previous_commission_paid = bcadd($previous_commission_paid,$product_array[0]['previous_amount']);
                        $current_commission_paid = bcadd($current_commission_paid,$commission_paid);
                        

					$this->pdf->ezTable($details_f, $cols, "", $options_details);
					
					if ($product_array[0]['total_product_credits']) {
						$this->pdf->ezSetDy(-10);
						$this->pdf->addTextWrap($col1 + 43, $this->pdf->y, $space2, 8, "Vendor Credits", 'right');
						$this->pdf->addTextWrap($col3 - $right_adj, $this->pdf->y, $space2 - 10, 8, $this->format_dollars_show_zero($product_array[0]['total_product_credits']*-1), 'right');
						$this->pdf->ezSetDy(-10);
						$total_cost -= $product_array[0]['total_product_credits'];
					}
					
					$overhead_factor = 0;
						
					if ($reports->results_misc[$commission_hash]['OVERHEAD'] && $reports->results_misc[$commission_hash]['OVERHEAD']['rate']) {
						if ($reports->results_misc[$commission_hash]['OVERHEAD']['apply_to'] == 'C')
							$overhead_factor = _round($total_cost * $reports->results_misc[$commission_hash]['OVERHEAD']['rate'], 2);
						elseif ($reports->results_misc[$commission_hash]['OVERHEAD']['apply_to'] == 'S')
						$overhead_factor = _round($total_sell * $reports->results_misc[$commission_hash]['OVERHEAD']['rate'], 2);
					
						$this->pdf->ezSetDy(-10);
						$this->pdf->addTextWrap($col1 + 52, $this->pdf->y, $space2, 8, "Company Overhead Factor", 'right');
						$this->pdf->addTextWrap($col3 - $right_adj, $this->pdf->y, $space2 - 10, 8, $this->format_dollars_show_zero($overhead_factor), 'right');
						$this->pdf->ezSetDy(-10);
						$total_cost += $overhead_factor;
						$total_overhead += $overhead_factor;
					}
					
					if ($reports->results_misc[$commission_hash]['MEMO']) {
						for ($i = 0; $i < count($reports->results_misc[$commission_hash]['MEMO']); $i++) {
							$this->pdf->ezSetDy(-10);
							$this->pdf->addTextWrap($col1 + 60, $this->pdf->y, $space2, 8, (strlen($reports->results_misc[$commission_hash]['MEMO'][$i]['descr']) > 45 ?
																	substr($reports->results_misc[$commission_hash]['MEMO'][$i]['descr'], 0, 42)."..." : $reports->results_misc[$commission_hash]['MEMO'][$i]['descr']), 'left');
							$this->pdf->addTextWrap($col3 - $right_adj, $this->pdf->y, $space2 - 10, 8, $this->format_dollars_show_zero($reports->results_misc[$commission_hash]['MEMO'][$i]['amount']), 'right');
							$this->pdf->ezSetDy(-10);
							$total_cost += $reports->results_misc[$commission_hash]['MEMO'][$i]['amount'];
						}
					}
					
					$customerCredit = 0;
						
					$result = $this->db->query("SELECT (`amount` - `tax`) as amount
												FROM `customer_credit`
												WHERE deleted = 0 AND `proposal_hash` = '". $commission_hash ."'");
					while ($row = $this->db->fetch_assoc($result)){
						$customerCredit = $row['amount'];
						$this->pdf->ezSetDy(-10);
						$this->pdf->addTextWrap($col1 + 43, $this->pdf->y, $space2, 8, "Customer Credits", 'right');
						$this->pdf->addTextWrap($col2 - $right_adj, $this->pdf->y, $space2 - 10, 8, $this->format_dollars_show_zero($customerCredit * -1), 'right');
						$this->pdf->ezSetDy(-10);
						$total_sell -= $customerCredit;
					}

                    $customerRefund = 0;

                    $result = $this->db->query("SELECT vp.amount
												FROM vendor_payables vp
												left join customer_invoice i on i.invoice_hash = vp.po_hash
												WHERE vp.deleted = 0 and vp.type = 'R' AND i.proposal_hash = '". $commission_hash ."'");
                    while ($row = $this->db->fetch_assoc($result)){
                        $customerRefund = $row['amount'];
                        $this->pdf->ezSetDy(-10);
                        $this->pdf->addTextWrap($col1 + 43, $this->pdf->y, $space2, 8, "Customer Refund", 'right');
                        $this->pdf->addTextWrap($col2 - $right_adj, $this->pdf->y, $space2 - 10, 8, $this->format_dollars_show_zero($customerRefund * -1), 'right');
                        $this->pdf->ezSetDy(-10);
                        $total_sell -= $customerRefund;
                    }
					
					$grand_total_sell = bcadd($grand_total_sell,$total_sell);
					$grand_total_cost = bcadd($grand_total_cost,$total_cost);

					// Calculate and display the total commissions paid per proposal
					$this->pdf->setStrokeColor(.8,.8,.8);

					$this->pdf->line($col2 - 10,$this->pdf->y,$col2 + $space2 - $right_adj - 10, $this->pdf->y);
					$this->pdf->line($col3 - 10,$this->pdf->y,$col3 + $space2 - $right_adj - 10, $this->pdf->y);
					$this->pdf->line($col4 - 10,$this->pdf->y,$col4 + $space2 - $right_adj - 10, $this->pdf->y);
					$this->pdf->line($col5 - 10,$this->pdf->y,$col5 + $space2 - $right_adj - 10, $this->pdf->y);
					$this->pdf->ezsetdy(-10);
					$this->pdf->addTextWrap($col2 - $right_adj, $this->pdf->y, $space2 - 10, 8, $this->format_dollars_show_zero($total_sell), 'right');
					$this->pdf->addTextWrap($col3 - $right_adj, $this->pdf->y, $space2 - 10, 8, $this->format_dollars_show_zero($total_cost), 'right');
					$this->pdf->addTextWrap($col4 - $right_adj, $this->pdf->y, $space2 - 10, 8, $this->format_dollars_show_zero($total_sell - $total_cost), 'right');
					$this->pdf->addTextWrap($col5 - $right_adj, $this->pdf->y, $space2 - 10, 8, (float)bcmul( math::gp_margin( array(
	    					'cost' =>  $total_cost,
	    					'sell' =>  $total_sell
						) ), 100, 4) . "%", 'right');
					$this->pdf->ezSetDy(-10);
					if (bccomp(0,$product_array[0]['previous_amount'])) {
						$this->pdf->addText(50,$this->pdf->y,8,"Previous Commissions Paid:".$this->format_dollars_show_zero($product_array[0]['previous_amount']));
						$this->pdf->ezSetDy(-10);
					}
                    $this->pdf->addText(50,$this->pdf->y,8,"Current Commissions Paid:".$this->format_dollars_show_zero($product_array[0]['amount']));
                    $this->pdf->ezSetDy(-10);
                    $this->pdf->addText(50,$this->pdf->y,8,"Total Commissions Paid:".$this->format_dollars_show_zero(bcadd($product_array[0]['amount'],$product_array[0]['previous_amount'],2)));
                    $this->pdf->ezSetDy(-10);
					$this->pdf->addText(50,$this->pdf->y,8,"Paid On: ".date(DATE_FORMAT,strtotime($product_array[0]['paid_date']))."  ".($product_array[0]['paid_in_full'] ? "<i>This commission is paid in full </i>" : ""));
					$this->pdf->ezSetDy(-10);
					$this->pdf->setLineStyle(1);
					$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y);
				}
				$all_total_commission = bcadd($all_total_commission,bcadd($total_commission_paid,$previous_commission_paid));
				$all_current_commission = bcadd($all_current_commission,$current_commission_paid);
				$all_previous_commission = bcadd($all_previous_commission,$previous_commission_paid);
				$sales_commission_array[$sales_hash] = array('full_name'			=>	$reports->user_name($sales_hash),
															 'total_sell'			=>  $grand_total_sell,
														     'total_cost'			=>	$grand_total_cost,
				                                             'previous_commission'  =>  $previous_commission_paid,
				                                             'current_commission'   =>  $current_commission_paid,
														     'total_commission'		=>  $total_commission_paid,
                                                                                                                      'total_overhead' => $total_overhead);

			}
			// Add totals for ticket #257
			if (bccomp($all_total_commission,0) || bccomp($all_current_commission,0) || bccomp($all_previous_commission,0)) {
				$space2 = 58;
				$col1 = $this->pdf->ez['leftMargin'];
				$col2 = $col1 + 1 * $space2;
				$col3 = $col1 + 2 * $space2;
				$col4 = $col1 + 3 * $space2;
				$col5 = $col1 + 4 * $space2;
				$col6 = $col1 + 5 * $space2;
				$col7 = $col1 + 6 * $space2;
				$col8 = $col1 + 7 * $space2;
				$col9 = $col1 + 8 * $space2;
				$this->pdf->setColor(.8,.8,.8);
				$this->pdf->ezSetDy(-10);
				$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$this->pdf->y - 2,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],12);
				$this->pdf->setColor(0,0,0);
				$this->pdf->addTextWrap($col1, $this->pdf->y, $space2, 8, "<b>Sales Rep</b>", 'left');
				$this->pdf->addTextWrap($col2, $this->pdf->y, $space2, 8, "<b>Total Sell</b>", 'right');
				$this->pdf->addTextWrap($col3, $this->pdf->y, $space2, 8, "<b>Total Cost</b>", 'right');
				$this->pdf->addTextWrap($col4, $this->pdf->y, $space2, 8, "<b>Total Profit</b>", 'right');
				$this->pdf->addTextWrap($col5, $this->pdf->y, $space2, 8, "<b>Total Margin</b>", 'right');
				$this->pdf->addTextWrap($col6, $this->pdf->y, $space2, 8, "<b>Previous Commissions</b>", 'right');
				$this->pdf->addTextWrap($col7, $this->pdf->y, $space2, 8, "<b>Current Commissions</b>", 'right');
				$this->pdf->addTextWrap($col8, $this->pdf->y, $space2, 8, "<b>Total Commissions</b>", 'right');
				$this->pdf->addTextWrap($col9 + 10, $this->pdf->y, $space2, 8, "<b>Total Overhead</b>", 'right');
				$this->pdf->ezSetDy(-10);

				while (list($sales_hash,$sales_array) = each($sales_commission_array)) {
					$this->pdf->addTextWrap($col1, $this->pdf->y, $space2, 8, $sales_array['full_name'], 'left');
					$this->pdf->addTextWrap($col2, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($sales_array['total_sell']), 'right');
					$this->pdf->addTextWrap($col3, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($sales_array['total_cost']), 'right');
					$this->pdf->addTextWrap($col4, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($sales_array['total_sell'] - $sales_array['total_cost']), 'right');
					$this->pdf->addTextWrap($col5, $this->pdf->y, $space2, 8, (float)bcmul( math::gp_margin( array(
						'cost' =>  $sales_array['total_cost'],
						'sell' =>  $sales_array['total_sell']
						) ), 100, 4) . "%", 'right');
					$this->pdf->addTextWrap($col6, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($sales_array['previous_commission']), 'right');
					$this->pdf->addTextWrap($col7, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($sales_array['current_commission']), 'right');
					$this->pdf->addTextWrap($col8, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($sales_array['total_commission']), 'right');
					$this->pdf->addTextWrap($col9, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($sales_array['total_overhead']), 'right');

					$total_net_sell = bcadd($total_net_sell,$sales_array['total_sell']);
					$total_net_cost = bcadd($total_net_cost,$sales_array['total_cost']);
                    $grand_total_commission_paid = bcadd($grand_total_commission_paid,$sales_array['total_commission']);
                    $grand_current_commission_paid = bcadd($grand_current_commission_paid,$sales_array['current_commission']);
                    $grand_previous_commission_paid = bcadd($grand_previous_commission_paid,$sales_array['previous_commission']);
                    $grand_total_overhead = bcadd($grand_total_overhead,$sales_array['total_overhead']);
					$this->pdf->ezSetDy(-10);
				}
				$this->pdf->ezSetDy(5);
				$this->pdf->line($col2 + 10,$this->pdf->y,$col2 + $space2, $this->pdf->y);
				$this->pdf->line($col3 + 10,$this->pdf->y,$col3 + $space2, $this->pdf->y);
				$this->pdf->line($col4 + 10,$this->pdf->y,$col4 + $space2, $this->pdf->y);
				$this->pdf->line($col5 + 10,$this->pdf->y,$col5 + $space2, $this->pdf->y);
				$this->pdf->line($col6 + 10,$this->pdf->y,$col6 + $space2, $this->pdf->y);
				$this->pdf->line($col7 + 10,$this->pdf->y,$col7 + $space2, $this->pdf->y);
				$this->pdf->line($col8 + 10,$this->pdf->y,$col8 + $space2, $this->pdf->y);
				$this->pdf->line($col9 + 10,$this->pdf->y,$col9 + $space2, $this->pdf->y);
				$this->pdf->ezSetDy(-10);
				$this->pdf->addTextWrap($col2, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($total_net_sell), 'right');
				$this->pdf->addTextWrap($col3, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($total_net_cost), 'right');
				$this->pdf->addTextWrap($col4, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($total_net_sell - $total_net_cost), 'right');
				$this->pdf->addTextWrap($col5, $this->pdf->y, $space2, 8, (float)bcmul( math::gp_margin( array(
    				'cost'  =>  $total_net_cost,
    				'sell'  =>  $total_net_sell
				) ), 100, 4) . "%", 'right');
                $this->pdf->addTextWrap($col6, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($grand_previous_commission_paid), 'right');
                $this->pdf->addTextWrap($col7, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($grand_current_commission_paid), 'right');
                $this->pdf->addTextWrap($col8, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($grand_total_commission_paid), 'right');
                $this->pdf->addTextWrap($col9, $this->pdf->y, $space2, 8, $this->format_dollars_show_zero($grand_total_overhead), 'right');

			}


		}


	}
/**
 * Generates a pdf document of a commission report given the report_hash.
 *
 * @param String $report_hash
 */
	function commission_report($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_commission_report();
		//TODO Implement Commission Report
		echo "<pre> commission report data is ".print_r($reports->results,1)."</pre>";
		die();

	}

/**
 * Generates a pdf document of a product sales report given the report_hash.
 *
 * @param String $report_hash
 */
	function product_sales_report($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_product_sales_report();

		echo "<pre> product sales report data is ".print_r($reports->results,1)."</pre>";
		die();

	}

/**
 * Generates a pdf document of a backlog report given the report_hash.
 *
 * @param String $report_hash
 */
	function backlog_report($report_hash) {
		$reports = new reports($this->current_hash);
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		$_POST['paginate'] = 1;

		// Populate data for reports object.
		$reports->doit_backlog_report();
		reset($reports->backlog);

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Backlog Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);
		$report_detail_title = array("","Line items remaining to be invoiced","Proposals partially booked showing line items remaining to be booked","Line items booked but not yet received","Line items booked but not yet shipped","Line items booked but not yet delivered","Line items booked and shipped but not yet invoiced","Line items booked and received but not yet invoiced","Line items booked, shipped and received but not yet invoiced","Proposals not yet booked");

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Backlog Report",16,array('justification' => 'center'));
		$this->pdf->ezSetDy(-10);
		$this->pdf->ezText("<i>".$report_detail_title[$reports->current_report['report_detail']]."</i>",9,array('justification' => 'center'));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$space = 130;  // space between invoice detail columns
		$space2 = 55;
		$right_adj = 15;
		$col1 = $col1_h = $start;
		$col1_t = $this->pdf->ez['leftMargin'];
		$col2 = $col1 + $space2/2.5;
		$col2_h = $col1 + 2.5 * $space2;
		$col2_t = 165;
		$col3_h = $col1 + 6.5 * $space2;
		$col3_t = 345;
		$col3 = $col2 + $space2;
		$col4 = $col3 + $space - 2.5 * $right_adj;
		$col4_t = 500;
		$col5 = $col4 + $space2;
		$col6 = $col5 + $space2;
		$col7 = $col6 + $space2;
		$col8 = $col7 + $space2;
		$col9 = $col8 + $space2;
		$col10 = $col9 + $space2-10;

		// Set font sizes for each element in printed report
		$font_size_h1 = 12; // Sales Rep Name
		$font_size_h2 = 10; // Company Name
		$font_size_h3 = 10; // Proposal Description
		$font_size_h4 = 9; // Proposal Summary Info
		$font_size_h5 = 7;  // Line Item Headers
		$font_size_h6 = 7;  // Line Item Details
		$font_size_h7 = 8;  // Proposal Totals
		$font_size_h8 = 9;  // Sales Rep Totals
		$font_size_h9 = 8; // Sales Rep Headers

		if (is_array($reports->backlog)) {
			unset($grand_totals);

				//  Display each sales rep and their totals.
				while (list($sales_hash,$proposal_array) = each($reports->backlog)) {
					$a = 1;
					unset($sales_rep_totals);
					$this->pdf->setColor(.7,.7,.7);
					$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$this->pdf->y - 18,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],15);
					$this->pdf->setColor(0,0,0);
					$this->pdf->ezText("   <b>".stripslashes($reports->user_name($sales_hash))."</b>",$font_size_h1);
					$this->pdf->addTextWrap($col7 - (2 * $right_adj),$this->pdf->y,$space2 + $right_adj,$font_size_h9,"Ext Cost",'right');
					$this->pdf->addTextWrap($col8 - (2 * $right_adj),$this->pdf->y,$space2  + $right_adj,$font_size_h9,"Ext Sell",'right');
					$this->pdf->addTextWrap($col9 - (2.5 * $right_adj),$this->pdf->y,$space2 + $right_adj,$font_size_h9,"Profit",'right');
					$this->pdf->addText($col10,$this->pdf->y,$font_size_h9,"GP");
					$this->pdf->ezSetDy(-15);
					$num_proposals = count($proposal_array);
					$n = 0;

					// Display each proposal and its totals for the sales rep.
					while (list($proposal_hash,$proposal_list) = each($proposal_array)) {
						$this->pdf->addText($col1,$this->pdf->y,$font_size_h2,"<b>".stripslashes($reports->customer_name($proposal_list[0]['customer_hash']))."</b>");
						$this->pdf->ezSetDy(-15);
						$this->pdf->addText($col1,$this->pdf->y,$font_size_h3,"<b>Proposal: ".$proposal_list[0]['proposal_no']." - ".$proposal_list[0]['proposal_descr']."</b>");
						$this->pdf->ezSetDy(-20);
						$this->pdf->addText($col1_h,$this->pdf->y,$font_size_h4,"Created: ".date(DATE_FORMAT,$proposal_list[0]['creation_date']));
						$this->pdf->addText($col2_h,$this->pdf->y,$font_size_h4,"Amount Invoiced: ".$this->format_dollars_show_zero($proposal_list[0]['total_invoiced']));
						$this->pdf->addText($col3_h,$this->pdf->y,$font_size_h4,"Amount Received: ".$this->format_dollars_show_zero($proposal_list[0]['total_received']));
						$this->pdf->ezSetDy(-15);
						$this->pdf->addText($col1_h,$this->pdf->y,$font_size_h4,"Booked: ".date(DATE_FORMAT,strtotime($proposal_list[0]['po_date'])));
						$this->pdf->addText($col2_h,$this->pdf->y,$font_size_h4,"Remaining To Invoice: ".$this->format_dollars_show_zero($proposal_list[0]['to_invoice']));
						$this->pdf->addText($col3_h,$this->pdf->y,$font_size_h4,"Deposits Received: ".$this->format_dollars_show_zero($proposal_list[0]['total_deposits']));
						$this->pdf->ezSetDy(-20);
						$this->pdf->setColor(.9,.9,.9);
						$this->pdf->filledRectangle($col1,$this->pdf->y - 2,$this->pdf->ez['pageWidth'] - $col1 - $this->pdf->ez['leftMargin'],10);
						$this->pdf->setColor(0,0,0);
						if ($reports->current_report['detail']) {
							$this->pdf->addText($col1,$this->pdf->y,$font_size_h5,"Qty");
							$this->pdf->addText($col2,$this->pdf->y,$font_size_h5,"Item No.");
							$this->pdf->addText($col3,$this->pdf->y,$font_size_h5,"Item Descr.");
							$this->pdf->addText($col4,$this->pdf->y,$font_size_h5,"Ack No.");
							$this->pdf->addText($col5,$this->pdf->y,$font_size_h5,"Ship Date");
							$this->pdf->addText($col6,$this->pdf->y,$font_size_h5,"Rcv Date");
							$this->pdf->addTextWrap($col7 - (2 * $right_adj),$this->pdf->y,$space2 + $right_adj,$font_size_h5,"Ext Cost",'right');
							$this->pdf->addTextWrap($col8 - (2 * $right_adj),$this->pdf->y,$space2  + $right_adj,$font_size_h5,"Ext Sell",'right');
							$this->pdf->addTextWrap($col9 - (2.5 * $right_adj),$this->pdf->y,$space2 + $right_adj,$font_size_h5,"Profit",'right');
							$this->pdf->addText($col10 ,$this->pdf->y,$font_size_h5,"GP");
							$this->pdf->ezSetDy(-15);
						}
						$reports->fetch_backlog_items($proposal_hash);
						$sub_detail = $reports->show_po_details($proposal_hash,'backlog',1);

						$po = new purchase_order($proposal_hash);
						$num_backlog_items = count($reports->backlog_items);
						$m = 1;
						unset($proposal_totals);

						// Display each line item for the proposal.
						while(list($po_hash,$po_array) = each($sub_detail[$proposal_hash])) {
							$ack_no = $po->fetch_ack_no($po_hash);
							if ($po_hash != 'UNORDERED' && $reports->current_report['detail']) {
								$this->pdf->addText($col1,$this->pdf->y,$font_size_h6,"Purchase Order: ".$po_array['po_info']['po_no']." : ".trim($po_array['po_info']['vendor_name'])." : ".$po_array['po_info']['po_product'].($po_array['po_info']['total_ack_lines'] > 0 ?
                                                " ".($po_array['po_info']['total_unack_lines'] == 0 ? "Fully" : "Partially")." Acknowledged" : NULL));
								$this->pdf->ezSetDy(-15);
							}
							$item_array =& $po_array['item_array'];
							for ($i = 0; $i < count($item_array); $i++) {
								if ($item_array[$i]['item_hash'])
									$proposal_totals['ext_cost'] += $item_array[$i]['ext_cost'];
								else
									$proposal_totals['ext_cost'] -= $item_array[$i]['credit_amount'];
								$proposal_totals['ext_sell'] += $item_array[$i]['ext_sell'];
								if ($reports->current_report['detail']) {
									// Include credits in line items if credit, otherwise display items for Trac #1447
									if ($item_array[$i]['item_hash']) {
										$this->pdf->addText($col1,$this->pdf->y,$font_size_h6,$item_array[$i]['qty']);
										$this->pdf->addText($col2,$this->pdf->y,$font_size_h6,(strlen($item_array[$i]['item_no']) > 15 ? substr($item_array[$i]['item_no'],0,12)."..." : $item_array[$i]['item_no']));
										preg_match_all('/[A-Z]/', $item_array[$i]['item_descr'], $uc_letters);
										$max_length = (count($uc_letters[0]) > 10 ? 18 : 27);
										$this->pdf->addText($col3,$this->pdf->y,$font_size_h6,(strlen($item_array[$i]['item_descr']) > $max_length ? substr($item_array[$i]['item_descr'],0,$max_length - 3)."..." : $item_array[$i]['item_descr']));
										$this->pdf->addText($col4,$this->pdf->y,$font_size_h6,$item_array[$i]['ack_no']);
										$this->pdf->addText($col5,$this->pdf->y,$font_size_h6,($item_array[$i]['ship_date'] && $item_array[$i]['ship_date'] != '0000-00-00' ? date(DATE_FORMAT,strtotime($item_array[$i]['ship_date'])) : " "));
										$this->pdf->addText($col6,$this->pdf->y,$font_size_h6,($item_array[$i]['receive_date'] && $item_array[$i]['receive_date'] != '0000-00-00' ? date(DATE_FORMAT,strtotime($item_array[$i]['receive_date'])) : " "));
										$this->pdf->addTextWrap($col7 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj, $font_size_h6,$this->format_dollars_show_zero($item_array[$i]['ext_cost']),'right');
										$this->pdf->addTextWrap($col8 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj, $font_size_h6,$this->format_dollars_show_zero($item_array[$i]['ext_sell']),'right');
										$this->pdf->addTextWrap($col9 - 2.5 * $right_adj,$this->pdf->y,$space2 + $right_adj, $font_size_h6,$this->format_dollars_show_zero($item_array[$i]['ext_sell'] - $item_array[$i]['ext_cost']),'right');
										$gp = (float)bcmul( math::gp_margin( array(
											'cost'    =>  $item_array[$i]['ext_cost'],
											'sell'    =>  $item_array[$i]['ext_sell']
										) ), 100, 4);
										$this->pdf->addTextWrap($col10 - 2.5 * $right_adj,$this->pdf->y,$space2,$font_size_h6,($gp < 0 ? "(".$gp."%)" : $gp."%"),'right');
									} else {
										$this->pdf->addText($col1,$this->pdf->y,$font_size_h6,"Applied Credit ".$item_array[$i]['credit_no']);
										$this->pdf->addTextWrap($col7 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj, $font_size_h6,$this->format_dollars_show_zero($item_array[$i]['credit_amount']),'right');
									}
									$this->pdf->ezSetDy(-5);
									$this->pdf->setStrokeColor(.8,.8,.8);
									$this->pdf->setLineStyle(.5);
									if ($m < $num_backlog_items)
										$this->pdf->line($col1, $this->pdf->y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y);
									elseif ($i+1 < count($item_array))
									 	$this->pdf->line($col1, $this->pdf->y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y);

									$this->pdf->setColor(0,0,0);
									$this->pdf->ezSetDy(-10);
								}
							}
							$m++;
						}

						// Add proposal totals to sales rep totals
						$sales_rep_totals['ext_cost'] += $proposal_totals['ext_cost'];
						$sales_rep_totals['ext_sell'] += $proposal_totals['ext_sell'];
						$line_adj = 10;

						if ($reports->current_report['detail']) {
							// Print totals for proposal
							$this->pdf->setLineStyle(.75);
							$this->pdf->setStrokeColor(.7,.7,.7);
							$this->pdf->addTextWrap($col2 - $right_adj,$this->pdf->y,$space * 2 + $space2,$font_size_h7,"<b>Total for Proposal ".$proposal_list[0]['proposal_no'].":</b>",'right');
							$this->pdf->line($col7,$this->pdf->y + $line_adj,$col7 - 2 * $right_adj + $space2 + $right_adj, $this->pdf->y + $line_adj);
							$this->pdf->addTextWrap($col7 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($proposal_totals['ext_cost']),'right');
							$this->pdf->line($col8,$this->pdf->y + $line_adj,$col8 - 2 * $right_adj + $space2 + $right_adj, $this->pdf->y + $line_adj);
							$this->pdf->addTextWrap($col8 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($proposal_totals['ext_sell']),'right');
							$this->pdf->line($col9,$this->pdf->y + $line_adj,$col9 - 2.5 * $right_adj + $space2 + $right_adj,$this->pdf->y + $line_adj);
							$this->pdf->addTextWrap($col9 - 2.5 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($proposal_totals['ext_sell'] - $proposal_totals['ext_cost']),'right');
							$gp = (float)bcmul( math::gp_margin( array(
    							'cost'   =>  $proposal_totals['ext_cost'],
    							'sell'   =>  $proposal_totals['ext_sell']
							) ), 100, 4);
							$this->pdf->line($col10 - $right_adj/2, $this->pdf->y + $line_adj,$col10 - 2.5 * $right_adj + $space2,$this->pdf->y + $line_adj);
							$this->pdf->addTextWrap($col10 - 2.5 * $right_adj,$this->pdf->y,$space2,$font_size_h7,($gp < 0 ? "($gp%)" : "$gp%"),'right');
							$this->pdf->ezSetDy(-10);
							$n++;
							$this->pdf->setLineStyle(1);
							$this->pdf->setStrokeColor(0,0,0);
							if ($n < $num_proposals)
								$this->pdf->line($col1, $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);
							$this->pdf->ezSetDy(-20);
							if (($this->pdf->y < 100) && ($n < $num_proposals))
								$this->pdf->ezNewPage();
						}
					}


					// Add sales rep totals to grand totals
					$grand_totals['ext_cost'] += $sales_rep_totals['ext_cost'];
					$grand_totals['ext_sell'] += $sales_rep_totals['ext_sell'];

					// Print totals for sales rep
					$line_adj = 10;
					if ($this->pdf->y < $this->pdf->ez['topMargin'] - 20)
						$this->pdf->ezSetDy(10);
					$this->pdf->setLineStyle(.85);
					$this->pdf->setStrokeColor(.6,.6,.6);
					$this->pdf->addTextWrap($col2 - $right_adj,$this->pdf->y,$space * 2 + $space2,$font_size_h4,"<b>Totals for ".stripslashes($reports->user_name($sales_hash)).":</b>",'right');
					$this->pdf->line($col7,$this->pdf->y + $line_adj,$col7 - 2 * $right_adj + $space2 + $right_adj, $this->pdf->y + $line_adj);
					$this->pdf->addTextWrap($col7 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($sales_rep_totals['ext_cost']),'right');
					$this->pdf->line($col8,$this->pdf->y + $line_adj,$col8 - 2 * $right_adj + $space2 + $right_adj, $this->pdf->y + $line_adj);
					$this->pdf->addTextWrap($col8 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($sales_rep_totals['ext_sell']),'right');
					$this->pdf->line($col9,$this->pdf->y + $line_adj,$col9 - 2.5 * $right_adj + $space2 + $right_adj,$this->pdf->y + $line_adj);
					$this->pdf->addTextWrap($col9 - 2.5 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($sales_rep_totals['ext_sell'] - $sales_rep_totals['ext_cost']),'right');
					$gp = (float)bcmul( math::gp_margin( array(
						'cost' =>  $sales_rep_totals['ext_cost'],
						'sell' =>  $sales_rep_totals['ext_sell']
					) ), 100, 4);
					$this->pdf->line($col10 - $right_adj/2, $this->pdf->y + $line_adj,$col10 - 2.5 * $right_adj + $space2,$this->pdf->y + $line_adj);
					$this->pdf->addTextWrap($col10 - 2.5 * $right_adj,$this->pdf->y,$space2,$font_size_h7,($gp < 0 ? "($gp%)" : "$gp%"),'right');
					$this->pdf->ezSetDy(-10);

					if (($this->pdf->y < 100))
						$this->pdf->ezNewPage();
				}

			// Print grand totals
			$line_adj2 = 12;
			$this->pdf->ezSetDy(-10);
			if ($this->pdf->y > $this->pdf->ez['topMargin'] - 20)
				$this->pdf->ezSetDy(-10);
			$this->pdf->setLineStyle(1);
			$this->pdf->setStrokeColor(.5,.5,.5);
			$this->pdf->addTextWrap($col2 - $right_adj,$this->pdf->y,$space * 2 + $space2,$font_size_h4,"<b>Report Total:</b>",'right');
			$this->pdf->line($col7,$this->pdf->y + $line_adj,$col7 - 2 * $right_adj + $space2 + $right_adj, $this->pdf->y + $line_adj);
			$this->pdf->addTextWrap($col7 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($grand_totals['ext_cost']),'right');
			$this->pdf->line($col8,$this->pdf->y + $line_adj,$col8 - 2 * $right_adj + $space2 + $right_adj, $this->pdf->y + $line_adj);
			$this->pdf->addTextWrap($col8 - 2 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($grand_totals['ext_sell']),'right');
			$this->pdf->line($col9,$this->pdf->y + $line_adj,$col9 - 2.5 * $right_adj + $space2 + $right_adj,$this->pdf->y + $line_adj);
			$this->pdf->addTextWrap($col9 - 2.5 * $right_adj,$this->pdf->y,$space2 + $right_adj,$font_size_h7,$this->format_dollars_show_zero($grand_totals['ext_sell'] - $grand_totals['ext_cost']),'right');
			$gp = (float)bcmul( math::gp_margin( array(
				'cost'   =>  $grand_totals['ext_cost'],
				'sell'   =>  $grand_totals['ext_sell']
			) ), 100, 4);
			$this->pdf->line($col10 - $right_adj/2, $this->pdf->y + $line_adj,$col10 - 2.5 * $right_adj + $space2,$this->pdf->y + $line_adj);
			$this->pdf->addTextWrap($col10 - 2.5 * $right_adj,$this->pdf->y,$space2,$font_size_h7,($gp < 0 ? "($gp%)" : "$gp%"),'right');
			$this->pdf->ezSetDy(-10);
		}
	}

/**
 * Generates a pdf document of a vendor balance summary report given the report_hash.
 *
 * @param String $report_hash
 */
	function vendor_balance($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_vendor_balance();

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Vendor Balance Summary Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Vendor Balance Report",16,array('justification' => 'center'));
		$this->pdf->ezText($reports->report_title,12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array("date"	  =>	"Date",
						"type" 		=> "Type",
						"space"		=> "",
						"space2"	=> "",
					    "due_date" 	=> "Due Date",
						"amount" 	=> "Amount",
						"balance" 	=> "Balance");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$space = 90;  // space between invoice detail columns
		$space2 = 70; // space between aging schedule columns
		$col_s2 = $start + 4 * $space;
		$col_s3 = $start + 5 * $space;
		$right_adj = 15; // adjustment for right justified columns (aging schedules)
		$font_size = 8;
		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addText($start, $this->pdf->y,$font_size,"<b>".$cols['date']."</b>");
		$this->pdf->addText($start + $space, $this->pdf->y,$font_size,"<b>".$cols['type']."</b>");
		$this->pdf->addText($start + 3 * $space + 15, $this->pdf->y,$font_size,"<b>".$cols['due_date']."</b>");
		$this->pdf->addText($start + 4 * $space + 15, $this->pdf->y,$font_size,"<b>".$cols['amount']."</b>");
		$this->pdf->addText($start + 5 * $space + 15, $this->pdf->y,$font_size,"<b>".$cols['balance']."</b>");
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		// Options for each column
		$options_columns =
						array ("date" => array ('justification' => 'left', 'width'=>$space),
							"type" => array ('justification' => 'left', 'width'=>$space),
							"space" => array ('justification' => 'right', 'width'=>$space2 - $right_adj),
							"due_date" => array ('justification' => 'right', 'width'=>$space),
							"amount" => array ('justification' => 'right', 'width'=>$space),
							"balance" => array ('justification' => 'right', 'width'=>$space - 5));
		$options_details =
						array('fontSize'	=> 7,
						 'width'			=> 535.28,
						 'xPos'				=> 60,
						 'xOrientation'		=> 'right',
						 'showLines'		=>  0,
						 'lineCol'			=>	array(.8,.8,.8),
						 'shaded'			=>  0,
						 'showHeadings' 	=>  0,
						 'rowGap'			=>	5,
						 'colGap'			=>	5,
						 'titleFontSize'	=>	10,
						 'shadeCol'			=>  array(.94,.94,.94),
						 'cols'				=>	$options_columns
						);


		$vendors = new vendors($this->current_hash);

		if (is_array($reports->results)) {
			reset($reports->results);
			$k = 0;
			$total = $total_amount = $total_balance = 0;
			while (list($vendor_hash, $po_array) = each($reports->results)) {
				$vendor_total = $vendor_balance  = 0;

				reset($po_array);
				$current = current($po_array);

				$bottom_line_y = $this->pdf->y;
				$top_line_y = $bottom_line_y + 15;
				$x = 45;

				$this->pdf->setStrokeColor(.7,.7,.7);
				$this->pdf->setColor(.8,.8,.8);
				$this->pdf->setLineStyle(1);
				$this->pdf->line($this->pdf->ez['leftMargin'],$top_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$top_line_y);
				$this->pdf->line($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$bottom_line_y);
				$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],15);

				$this->pdf->setColor(0,0,0);
				$this->pdf->ezSetDy(15);
				$this->pdf->ezText($current[0]['vendor_name'], 12);
				$this->pdf->ezSetDy(-10);


				while (list($po_hash,$details) = each($po_array)) {
					// Calculate totals
					$details_f = array();
					$amt = $bal = 0;
					for ($i = 0; $i < count($details); $i++) {
						if ($details[$i]['type'] == 'D' || $details[$i]['type'] == 'C') {
								$amt -= $details[$i]['amount'];
								$bal -= $details[$i]['balance'];
								$vendor_total -= $details[$i]['amount'];
								$vendor_balance -= $details[$i]['balance'];
								$total -= $details[$i]['amount'];
								$total_balance -= $details[$i]['balance'];
							} else {
								$amt += $details[$i]['amount'];
								$bal += $details[$i]['balance'];
								$vendor_total += $details[$i]['amount'];
								$vendor_balance += $details[$i]['balance'];
								$total += $details[$i]['amount'];
								$total_balance += $details[$i]['balance'];
							}

						// Rebuild $details array with correct formatting and store in $details_f
							$details_f[] = array("date" 	=> $details[$i]['invoice_date'],
										"type" 				=> ($details[$i]['type'] == 'D' ? "Deposit" : ($details[$i]['type'] == 'I' ? "Bill" : "Vendor Credit"). " " .$details[$i]['invoice_no']),
										"due_date" 			=> ($details[$i]['type'] != 'D' ? date(DATE_FORMAT,strtotime($details[$i]['due_date'])) : ""),
										"amount"			=> $this->format_dollars_show_zero(($details[$i]['type'] == 'D' || $details[$i]['type'] == 'C' ? $details[$i]['amount'] * -1 : $details[$i]['amount'])),
										"balance"		 	=> $this->format_dollars_show_zero(($details[$i]['type'] == 'D' || $details[$i]['type'] == 'C' ? $details[$i]['balance'] * -1 : $details[$i]['balance'])));

					}
					if ($reports->current_report['showdetail'] == 2) {
						$this->pdf->ezText("Purchase Order: ".$details[$i]['po_no'],8, array("aleft"=>50));
						$this->pdf->ezTable($details_f, $cols, "", $options_details);
						// Display total for proposal if multiple invoices
						if (count($details_f) > 1) {
							$this->pdf->setStrokeColor(.8,.8,.8);
							$this->pdf->setLineStyle(.5);
							$this->pdf->line($col_s2 + 10, $this->pdf->y,$col_s3 + $space2 - 20, $this->pdf->y);
							$this->pdf->addTextWrap($col_s2 - 10, $this->pdf->y - 15, $space2 - 5, 7, $this->format_dollars($amt), 'right');
							$this->pdf->addTextWrap($col_s3 - 15, $this->pdf->y - 15, $space2 - 5, 7, $this->format_dollars($bal), 'right');
							$this->pdf->ezSetDy(-20);
						}
					}
				}

				// Display totals for amount and balance
				$this->pdf->setStrokeColor(.8,.8,.8);
				$this->pdf->setLineStyle(.5);
				if ($reports->current_report['showdetail'] == 2)
					$this->pdf->line($col_s2 + 10, $this->pdf->y,$col_s3 + $space2 - 20, $this->pdf->y);
				$this->pdf->addTextWrap($col_s2 - 10, $this->pdf->y - 10, $space2 - 5, 7, "<b>".$this->format_dollars_show_zero($vendor_total)."</b>", 'right');
				$this->pdf->addTextWrap($col_s3 - 15, $this->pdf->y - 10, $space2 - 5, 7, "<b>".$this->format_dollars_show_zero($vendor_balance)."</b>", 'right');
				$this->pdf->ezSetDy(-30);
			}

			// Calculate and display the total customer balance
			$this->pdf->setStrokeColor(.8,.8,.8);
			$this->pdf->setLineStyle(1,NULL,NULL);
			$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y);
			$this->pdf->addTextWrap($this->pdf->ez['leftMargin'],$this->pdf->y - 15,300, 10,"<b>Total Vendor Balance</b>");
			$this->pdf->addTextWrap($col_s2 - 10, $this->pdf->y - 15, $space2 - 5, 10, "<b>".$this->format_dollars_show_zero($total)."</b>", 'right');
			$this->pdf->addTextWrap($col_s3 - 15, $this->pdf->y - 15, $space2 - 5, 10, "<b>".$this->format_dollars_show_zero($total_balance)."</b>", 'right');
		}
	}


/**
 * Generates a pdf document of a purchase order report given the report_hash.
 *
 * @param String $report_hash
 */
	function po_report($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_po_report();
		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Vendor Discounting Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Purchase Order Report",16,array('justification' => 'center'));
		$this->pdf->ezText($reports->report_title,12,array('justification' => 'center'));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column

		$space2 = 80;
		$col1 = $start;
		$col2 = $col1+ $space2;
		$col3 = $col2 + $space2;
		$col4 = $col3 + $space2-9.72;
		$col5 = $col4 + $space2;
		$col6 = $col5 + $space2;

		$font_size_header = 8;

		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$font_size = 8;
		$po_cols = array ( "po_no"			=> "PO No.",
						"date" 			=> "Date",
						"order_cost" 	=> "Order Cost",
						"sell_amount" 	=> "Sell Amount",
						"profit" 		=> "Profit",
						"gp_margin" 	=> "GP Margin"
						);
		// Options for each column
		$po_col_options =
						array ("po_no"		=> array ('justification' => 'left'),
							"date" 			=> array ('justification' => 'left'),
							"order_cost" 	=> array ('justification' => 'right'),
							"sell_amount" 	=> array ('justification' => 'right'),
							"profit" 		=> array ('justification' => 'right'),
							"gp_margin" 	=> array ('justification' => 'right')
						);
		$po_options =
						array('fontSize'	=> $font_size,
						 'width'			=> $this->pdf->ez['pageWidth'] - 4 * $this->pdf->ez['rightMargin'],
						 'xPos'				=> $col1,
						 'xOrientation'		=> 'right',
						 'showLines'		=>  2,
						 'lineCol'			=>	array(.8,.8,.8),
						 'shaded'			=>  0,
						 'showHeadings' 	=>  1,
						 'rowGap'			=>	0,
						 'colGap'			=>	5,
						 'options'			=>	$po_col_options
						);

		if (is_array($reports->po)) {
			$grand_total = 0;
			reset($reports->po);
			while (list($vendor_hash,$proposal_array) = each($reports->po)) {
				$vendor_total = 0;
				$isFirst = true;
				while (list($proposal_hash,$proposal_list) = each($proposal_array)) {
					// Print Vendor name only first pass through
					if ($isFirst) {
						$this->pdf->addText($this->pdf->ez['leftMargin'],$this->pdf->y,12,$proposal_list[0]['vendor_name']);
						$this->pdf->ezSetDy(-15);
						$isFirst = false;
					}
					$this->pdf->addText($col1,$this->pdf->y,10,"<b>Proposal ".$proposal_list[0]['proposal_no'].": ".stripslashes($proposal_list[0]['customer_name'])."</b>");
					$this->pdf->addTextWrap($col5,$this->pdf->y,160,10,"Sales Rep: ".stripslashes($proposal_list[0]['full_name']),'right');
					$this->pdf->ezSetDy(-5);

					$po_data = array();
					$total_cost = $total_sell = $total_profit = 0;

					foreach ( $proposal_list as $po ) {

						array_push($po_data, array(
    						"po_no"			=>	$po['po_no'],
							"date"			=>	date(DATE_FORMAT,strtotime($po['po_date'])),
							"order_cost"	=>	$this->format_dollars_show_zero($po['order_amount']),
							"sell_amount"	=> 	$this->format_dollars_show_zero($po['ext_sell']),
							"profit"		=>	$this->format_dollars_show_zero($po['ext_sell'] - $po['order_amount']),
							"gp_margin"		=>	(float)bcmul( math::gp_margin( array(
    							'cost'    =>  $po['order_amount'],
    							'sell'    =>  $po['ext_sell']
    						) ), 100, 4) . "%"
						) );

						$total_cost = bcadd($total_cost, $po['order_amount'], 2);
						$total_sell = bcadd($total_sell, $po['ext_sell'], 2);
						$total_profit = bcadd($total_profit, bcsub($po['ext_sell'], $po['order_amount'], 2), 2);
					}

					array_push($po_data, array(
    					"po_no"			=>	'',
						"date"			=>	'',
						"order_cost"	=>	$this->format_dollars_show_zero($total_cost),
						"sell_amount"	=> 	$this->format_dollars_show_zero($total_sell),
						"profit"		=>	$this->format_dollars_show_zero($total_profit),
						"gp_margin"		=>	(float)bcmul( math::gp_margin( array(
        					'cost' =>  $total_cost,
        					'sell' =>  $total_sell
    					) ), 100, 4) . "%"
					) );

					$this->pdf->ezTable($po_data,$po_cols,'',$po_options);
					$this->pdf->ezSetDy(-15);
					$vendor_total = bcadd($vendor_total, $total_cost, 2);
				}

				$this->pdf->addTextWrap($col5,$this->pdf->y,$space2,10,"<b>Total:</b>",'right');
				$this->pdf->addTextWrap($col6,$this->pdf->y,$space2,10,"<b>".$this->format_dollars_show_zero($vendor_total)."</b>",'right');
				$this->pdf->ezSetDy(-20);
				$grand_total += $vendor_total;
			}
			$this->pdf->addTextWrap($col5-$space2,$this->pdf->y,$space2*2,10,"<b>Purchase Order Total:</b>",'right');
			$this->pdf->addTextWrap($col6-15,$this->pdf->y,$space2+15,10,"<b>".$this->format_dollars_show_zero($grand_total)."</b>",'right');

		}
	}

/**
 * Generates a pdf document of a vendor discounting report given the report_hash.
 *
 * @param String $report_hash
 */
	function vendor_discounting($report_hash) {
		$reports = new reports($this->current_hash);

		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_vendor_discounting();

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Vendor Discounting Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Vendor Discounting Report",16,array('justification' => 'center'));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array("type"	  		=>	"Type",
					"discount_id"		=>	"Discount ID",
					"effective_date"	=> "Effective Date",
					"expiration_date"	=> "Expiration Date");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$space = 230;  // space between invoice detail columns
		$space2 = 85;
		$col1 = $start;
		$col2 = $col1+ $space;
		$col3 = $col2 + $space2;
		$col4 = $col3 + $space2;
		$font_size_header = 8;

		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addTextWrap($col1, $this->pdf->y, $space2, $font_size_header,"<b>".$cols['type']."</b>");
		$this->pdf->addTextWrap($col2, $this->pdf->y, $space2, $font_size_header,"<b>".$cols['discount_id']."</b>",'left');
		$this->pdf->addTextWrap($col3, $this->pdf->y, $space2, $font_size_header,"<b>".$cols['effective_date']."</b>", 'right');
		$this->pdf->addTextWrap($col4, $this->pdf->y, $space2, $font_size_header,"<b>".$cols['expiration_date']."</b>",'right');
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$font_size = 8;
		// Options for each column
		$options_columns =
						array ("product"=> array ('justification' => 'left'),
							"buy" 	=> array ('justification' => 'left'),
							"buy2" 		=> array ('justification' => 'right'),
							"sell" 		=> array ('justification' => 'right'),
							"gp" 		=> array ('justification' => 'right'));
		$options_details =
						array('fontSize'	=> 7,
						 'width'			=> $this->pdf->ez['pageWidth'] - 3 * $this->pdf->ez['leftMargin'],
						 'xPos'				=> $col1,
						 'xOrientation'		=> 'right',
						 'showLines'		=>  2,
						 'lineCol'			=>	array(.8,.8,.8),
						 'shaded'			=>  0,
						 'showHeadings' 	=>  1,
						 'rowGap'			=>	0,
						 'colGap'			=>	5,
						);
		$options_text =
						array('fontSize'	=> 8,
						 'width'			=> $this->pdf->ez['pageWidth'] - 3 * $this->pdf->ez['leftMargin'],
						 'xPos'				=> $col1,
						 'xOrientation'		=> 'right',
						 'showLines'		=>  1,
						 'lineCol'			=>	array(.8,.8,.8),
						 'shaded'			=>  0,
						 'showHeadings' 	=>  0,
						 'rowGap'			=>	5,
						 'colGap'			=>	5,
						);

		reset($reports->discount_details);
		if (is_array($reports->results)) {
			while (list($vendor_hash,$discount_array) = each($reports->results)) {
				reset($discount_array);
				$current = current($discount_array);
				$this->pdf->addTextWrap($this->pdf->ez['leftMargin'] + 15,$this->pdf->y,$space,9,"<b>".stripslashes($current[0]['vendor_name'])."</b>");
				$this->pdf->ezSetDy(-15);
				while (list($discount_hash,$details) = each($discount_array)) {
					$this->pdf->addTextWrap($col1, $this->pdf->y, $space, $font_size,($details[0]['discount_type'] == 'C' ? "Customer Discount : ".stripslashes($details[0]['customer_name']) : "Standard Discount ".($details[0]['discount_descr'] ? " (".$details[0]['discount_descr'].")" : NULL)));
					$this->pdf->addTextWrap($col2, $this->pdf->y, $space2, $font_size, $details[0]['discount_id']);
					$this->pdf->addTextWrap($col3, $this->pdf->y, $space2, $font_size, date(DATE_FORMAT,strtotime($details[0]['discount_effective_date'])),'right');
					$this->pdf->addTextWrap($col4, $this->pdf->y, $space2, $font_size, date(DATE_FORMAT,strtotime($details[0]['discount_expiration_date'])),'right');
					$this->pdf->ezSetDy(-10);
					if ($reports->current_report['showdetail'] == 2) {

						$cols = array("product"	=>	"<b>Product</b>",
									"buy"		=>	"",
									"buy2"		=>	"<b>Buy Discount</b>",
						   			"sell"		=>	"<b>Sell Discount</b>",
						   			"gp" 		=>	"<b>GP Margin</b>");
						$details_f = array();

						$show_tiers = false;
						for ($i = 0; $i < count($reports->discount_details[$discount_hash]); $i++) {
							unset($frf_item);
							if ($reports->discount_details[$discount_hash][$i]['discount_frf']) {
								$discount_frf = explode("|",$reports->discount_details[$discount_hash][$i]['discount_frf']);
								$frf_item = array();
								for ($k = 0; $k < count($discount_frf); $k++) {
									list($a,$b) = explode("=",$discount_frf[$k]);
									$a = str_replace("discount","product",$a);
									$frf_item[$a] = $b;
								}
							}
							$buy2 = $buy = $sell = $gp = "";
							$product = ($reports->discount_details[$discount_hash][$i]['product_hash'] == '*' ? "All Products" : $reports->discount_details[$discount_hash][$i]['product_name']).
								(($frf_item || $reports->discount_details[$discount_hash][$i]['discount_frf_quoted']) ? ($frf_item ? "\n  If under ".$this->format_dollars_show_zero($frf_item['product_frf_amount']). " ".
									($frf_item['product_frf_amount_type'] == 'L' ? "List" : "Net")."\n    Then add ".(($frf_item['product_frf_amount_add_type'] == 'P') ? ($frf_item['product_frf_amount_add'] *100)."% of ".
										(($frf_item['product_frf_amount_add_type_of'] == 'L') ? " List" : "Net") : $this->format_dollars_show_zero($frf_item['product_frf_amount_add'])) : NULL).
											($charges['product_frf_quoted'] ? "" : NULL).(($reports->discount_details[$discount_hash][$i]['discount_frf_quoted']) ? "This freight needs to be quoted!" : NULL) : NULL);
							if ($reports->discount_details[$discount_hash][$i]['discount_type'] == 'F') {
								$buy2 = ($reports->discount_details[$discount_hash][$i]['buy_discount1'] != 0 ?
											($reports->discount_details[$discount_hash][$i]['buy_discount1'] * 100)."%" : " ").($reports->discount_details[$discount_hash][$i]['buy_discount2'] != 0 ?
												" ".($reports->discount_details[$discount_hash][$i]['buy_discount2'] * 100)."%" : NULL).($reports->discount_details[$discount_hash][$i]['buy_discount3'] != 0 ?
													" ".($reports->discount_details[$discount_hash][$i]['buy_discount3'] * 100)."%" : NULL).($reports->discount_details[$discount_hash][$i]['buy_discount4'] != 0 ?
														" ".($reports->discount_details[$discount_hash][$i]['buy_discount4'] * 100)."%" : NULL).($reports->discount_details[$discount_hash][$i]['buy_discount5'] != 0 ?
															" ".($reports->discount_details[$discount_hash][$i]['buy_discount5'] * 100)."%" : NULL);
								$sell = ($reports->discount_details[$discount_hash][$i]['sell_discount'] != 0 ? ($reports->discount_details[$discount_hash][$i]['sell_discount'] * 100)."%" : "");
								$gp = ($reports->discount_details[$discount_hash][$i]['gp_margin'] != 0 ? ($reports->discount_details[$discount_hash][$i]['gp_margin'] * 100)."%" : "");
							} else {
								for ($j = 1; $j < 7; $j++) {
									if ($reports->discount_details[$discount_hash][$i]['tier'.$j.'_from'] != 0) {
										$buy .= ($reports->discount_details[$discount_hash][$i]['tier'.$j.'_to'] != 0 ? "From $".
											number_format($reports->discount_details[$discount_hash][$i]['tier'.$j.'_from'],2).
												" To $".number_format($reports->discount_details[$discount_hash][$i]['tier'.$j.'_to'],2) :
													"Over $".number_format($reports->discount_details[$discount_hash][$i]['tier'.$j.'_from'],2))."\n";
										$buy2 .= ($reports->discount_details[$discount_hash][$i]['tier'.$j.'_buy_discount'] * 100)."%\n";
										$sell .= ($reports->discount_details[$discount_hash][$i]['tier'.$j.'_sell_discount'] != 0 ? ($reports->discount_details[$discount_hash][$i]['tier'.$j.'_sell_discount'] * 100)."% \n" : "N/A \n");
									}
								}
								$show_tiers = true;
							}
							$details_f[] = array("product" 	=> $product,
												 "buy"		=> $buy,
												 "buy2"		=> $buy2,
												 "sell"		=> $sell,
												 "gp"		=> $gp);
						}
						if (sizeof($details_f) < 1) {
							$text = array(array("<b>There are no product discounts defined for this vendor discount.</b>"));
							$this->pdf->ezTable($text,'','',$options_text);
						} else {
							if (!$show_tiers)
								unset($cols["buy"]);
							$this->pdf->ezTable($details_f,$cols,"",$options_details);
						}
						$this->pdf->ezSetDy(-15);
					}
				}
			}
		}
	}

/**
 * Generates a pdf document of a cash flow exp report given the report_hash.
 *
 * @param String $report_hash
 */
	function cash_flow_exp($report_hash) {
		$reports = new reports($this->current_hash);
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;
		// Populate data for reports object.
		$reports->doit_cash_flow_exp();
		$sched_start_date = $reports->current_report['sched_start_date'];
		$day_sched = $reports->current_report['day_schedule'];

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Cash Flow Expectations Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Cash Flow Expectations Report",16,array('justification' => 'center'));
		$this->pdf->ezText("As of ".date(DATE_FORMAT,strtotime($sched_start_date)),12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		//date(DATE_FORMAT,strtotime($val['invoice_date']." +".($val['payment_terms'] ? $val['payment_terms'] : DEFAULT_CUST_PAY_TERMS)." days")),
		$cols = array("invoice_no"	  =>	"Invoice No.",
						   "invoice_date" =>	"Invoice Date",
						   "due_date" => "Due Date",
						   "expected" => "Expected",
						   "past"	  => "Past",
						   "sched1"   => date(DATE_FORMAT,strtotime($sched_start_date." +".$day_sched." days")),
						   "sched2"   => date(DATE_FORMAT,strtotime($sched_start_date." +". 2 * $day_sched." days")),
						   "sched3"   => date(DATE_FORMAT,strtotime($sched_start_date." +". 3 * $day_sched." days")),
						   "future"   => "Future");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$space = 60;  // space between invoice detail columns
		$space2 = 55;
		$right_adj = 15;
		$col5 = $start + 3 * $space + $space2 - 10;
		$col6 = $col5 + $space2 + 5;
		$col7 = $col6 + $space2 + 5;
		$col8 = $col7 + $space2 + 5;
		$col9 = $col8 + $space2 -15;
		$font_size = 8;

		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addText($start, $this->pdf->y,$font_size,"<b>".$cols['invoice_no']."</b>");
		$this->pdf->addText($start + $space, $this->pdf->y,$font_size,"<b>".$cols['invoice_date']."</b>");
		$this->pdf->addText($start + 2 * $space, $this->pdf->y,$font_size,"<b>".$cols['due_date']."</b>");
		$this->pdf->addText($start + 3 * $space, $this->pdf->y,$font_size,"<b>".$cols['expected']."</b>");
		$this->pdf->addTextWrap($col5, $this->pdf->y, $space2, $font_size,"<b>".$cols['past']."</b>",'right');
		$this->pdf->addTextWrap($col6, $this->pdf->y, $space2, $font_size,"<b>".$cols['sched1']."</b>",'right');
		$this->pdf->addTextWrap($col7, $this->pdf->y, $space2, $font_size,"<b>".$cols['sched2']."</b>", 'right');
		$this->pdf->addTextWrap($col8, $this->pdf->y, $space2, $font_size,"<b>".$cols['sched3']."</b>",'right');
		$this->pdf->addTextWrap($col9, $this->pdf->y, $space2, $font_size,"<b>".$cols['future']."</b>",'right');
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		// Options for each column
		$options_columns =
						array ("invoice_no" => array ('justification' => 'left', 'width'=>$space),
							"invoice_date" => array ('justification' => 'left', 'width'=>$space),
							"due_date" => array ('justification' => 'left', 'width'=>$space),
							"expected" => array ('justification' => 'left', 'width'=>$space),
							"past" 	=> array ('justification' => 'right', 'width'=>$space2),
							"sched1" => array ('justification' => 'right', 'width'=>$space2),
							"sched2" => array ('justification' => 'right', 'width'=>$space2),
							"sched3" => array ('justification' => 'right', 'width'=>$space2),
							"future" => array ('justification' => 'right', 'width'=>$space2 - 5));
		$options_details =
						array('fontSize'	=> 7,
						 'width'			=> 535.28,
						 'xPos'				=> 60,
						 'xOrientation'		=> 'right',
						 'showLines'		=>  0,
						 'lineCol'			=>	array(.8,.8,.8),
						 'shaded'			=>  0,
						 'showHeadings' 	=>  0,
						 'rowGap'			=>	5,
						 'colGap'			=>	5,
						 'titleFontSize'	=>	10,
						 'shadeCol'			=>  array(.94,.94,.94),
						 'cols'				=>	$options_columns
						);

		if (is_array($reports->results)) {
			$k = 0;

			while (list($customer_hash, $invoice_array) = each($reports->results)) {
				$total_past = $total_sched1 = $total_sched2 = $total_sched3 = $total_future = 0;

				$bottom_line_y = $this->pdf->y;
				$top_line_y = $bottom_line_y + 15;
				$x = 45;

				$this->pdf->setStrokeColor(.7,.7,.7);
				$this->pdf->setColor(.8,.8,.8);
				$this->pdf->setLineStyle(1);
				$this->pdf->line($this->pdf->ez['leftMargin'],$top_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$top_line_y);
				$this->pdf->line($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$bottom_line_y);
				$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],15);

				$this->pdf->setColor(0,0,0);
				$this->pdf->ezSetDy(15);
				$this->pdf->ezText(stripslashes($invoice_array['customer_name']), 12);
				$this->pdf->ezSetDy(-10);
				$details_f = array();

				for ($i = 0; $i < count($invoice_array['pay_schedule']); $i++) {
					$invoice_amt = array();
						switch ($invoice_array['pay_schedule'][$i]['sched']) {
							case 'past':
								$total_past += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['past'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;

							case '1':
								$total_sched1 += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['sched1'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;

							case '2':
								$total_sched2 += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['sched2'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;

							case '3':
								$total_sched3 += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['sched3'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;

							case 'future':
								$total_future += $invoice_array['pay_schedule'][$i]['invoice']['balance'];
								$invoice_amt['future'] = $invoice_array['pay_schedule'][$i]['invoice']['balance'];
							break;
						}

							$details_f[] = array("invoice_no" 		=> $invoice_array['pay_schedule'][$i]['invoice']['invoice_no'],
										"invoice_date" 		=> date(DATE_FORMAT,strtotime($invoice_array['pay_schedule'][$i]['invoice']['invoice_date'])),
										"due_date" 			=> $invoice_array['pay_schedule'][$i]['invoice']['due_date'],
										"expected"			=> date(DATE_FORMAT,$invoice_array['pay_schedule'][$i]['invoice']['exp_date']),
										"past"				=> $this->format_dollars($invoice_amt['past']),
										"sched1"		 	=> $this->format_dollars($invoice_amt['sched1']),
										"sched2"		 	=> $this->format_dollars($invoice_amt['sched2']),
										"sched3"		 	=> $this->format_dollars($invoice_amt['sched3']),
										"future"			=> $this->format_dollars($invoice_amt['future']));

				}
					if ($reports->current_report['showdetail'] == 2)
						$this->pdf->ezTable($details_f, $cols, "", $options_details);
					$total = $total_past + $total_sched1 + $total_sched2 + $total_sched3 + $total_future;

					$grand_total['past'] += $total_past;
					$grand_total['sched1'] += $total_sched1;
					$grand_total['sched2'] += $total_sched2;
					$grand_total['sched3'] += $total_sched3;
					$grand_total['future'] += $total_future;

					// Display totals for each schedule that has a balance
					$this->pdf->setStrokeColor(0,0,0);
					$this->pdf->setLineStyle(.5,'','',array(2,1));
					$s = 5;
					$h = 10;
					if ($total_past != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col5 + $right_adj, $this->pdf->y,$col5 + $space2 + $s, $this->pdf->y);
						$this->pdf->addTextWrap($col5 + $s, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_past), 'right');
					}
					if ($total_sched1 != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col6 + $right_adj, $this->pdf->y,$col6 + $space2 + $s, $this->pdf->y);
						$this->pdf->addTextWrap($col6 + $s, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_sched1), 'right');
					}
					if ($total_sched2 != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col7 + $right_adj, $this->pdf->y,$col7 + $space2 - $s, $this->pdf->y);
						$this->pdf->addTextWrap($col7  - $s, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_sched2), 'right');
					}
					if ($total_sched3 != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col8 + $right_adj, $this->pdf->y,$col8 + $space2 - 2 * $s, $this->pdf->y);
						$this->pdf->addTextWrap($col8 - 2 * $s, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_sched3), 'right');
					}
					if ($total_future != 0) {
						if ($reports->current_report['showdetail'] == 2)
							$this->pdf->line($col9 + $right_adj, $this->pdf->y,$col9 + $space2, $this->pdf->y);
						$this->pdf->addTextWrap($col9, $this->pdf->y - $h, $space2, 7, $this->format_dollars($total_future), 'right');
					}

					$this->pdf->ezSetDy(-30);

			}

			// Calculate and display the grand total of each aging schedule.
			$grand_total_all = $grand_total['past'] + $grand_total['sched1'] + $grand_total['sched2'] + $grand_total['sched3'] + $grand_total['future'];

			// Display totals for each column
			$bottom_line_y = $this->pdf->y;
			$top_line_y = $bottom_line_y + 20;
			$s = 5;

			$this->pdf->setStrokeColor(.7,.7,.7);
			$this->pdf->setColor(.8,.8,.8);
			$this->pdf->setLineStyle(1);
			$this->pdf->filledRectangle($this->pdf->ez['leftMargin'],$bottom_line_y,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'],15);
			$this->pdf->setColor(0,0,0);
			$this->pdf->addTextWrap($col5 + $s, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['past'])."</b>", 'right');
			$this->pdf->addTextWrap($col6 + $s, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['sched1'])."</b>", 'right');
			$this->pdf->addTextWrap($col7, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['sched2'])."</b>", 'right');
			$this->pdf->addTextWrap($col8, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['sched3'])."</b>", 'right');
			$this->pdf->addTextWrap($col9, $bottom_line_y + 2, $space2, 8, "<b>".$this->format_dollars($grand_total['future'])."</b>", 'right');
			$this->pdf->ezSetDy(-40);

		}

	}
/**
 * Generates a pdf document of a cash receipts report given the report_hash.
 *
 * @param String $report_hash
 */
	function cash_receipts($report_hash) {
		$reports = new reports($this->current_hash);
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for reports object.
		$reports->doit_cash_receipts();

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Cash Receipts Report ";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		switch ($reports->current_report['timeframe']) {
			//Today
			case 1:
				$report_title = "Cash receipts dated ".date(DATE_FORMAT);
			break;

			//Yesterday
			case 2:
				$report_title = "Cash receipts dated ".date(DATE_FORMAT,strtotime(date("Y-m-d")." -1 day"));
			break;

			//This week
			case 3:
				$week_start = date(DATE_FORMAT,strtotime(date("Y-m-d")." -".date("w")." days"));
				$week_end = date(DATE_FORMAT,strtotime(date("Y-m-d")." +".(6 - date("w"))." days"));
				$report_title = "Cash receipts dated between ".$week_start. " and ".$week_end;
			break;

			//Last week
			case 4:
				$last_week = date(DATE_FORMAT,strtotime(date("Y-m-d")." -1 week"));
				$week_start = date(DATE_FORMAT,strtotime($last_week." -".date("w")." days"));
				$week_end = date(DATE_FORMAT,strtotime($last_week." +".(6 - date("w",strtotime($last_week)))." days"));
				$report_title = "Cash receipts dated between ".$week_start." and ".$week_end;
			break;

			//This month
			case 5:
				$report_title = "Cash receipts dated between ".date(DATE_FORMAT, strtotime(date("Y-m-01")))." and ".date(DATE_FORMAT,strtotime(date("Y-m-".date("t"))));
			break;

			//Last Month
			case 6:
				if (date("m") == 1) {
					$month = 12;
					$year = date("Y") - 1;
				} else {
					$month = date("m") - 1;
					$year = date("Y");
				}
				$report_title = "Cash receipts dated between ".date(DATE_FORMAT,strtotime($year."-".$month."-01"))." and ".date(DATE_FORMAT,strtotime($year."-".$month."-".date("t",strtotime($year."-".$month."-01"))));
			break;

			//This qtr
			case 7:
				$current_qtr = get_quarter(date("Y-m-d"));
				if ($current_qtr == 1) {
					$previous_qtr = get_quarter(date("Y-m-d",strtotime(date("Y-m-d")." -4 months")));
					list($start,$end) = get_quarter_dates($previous_qtr,date("Y",strtotime(date("Y-m-d")." -4 months")));
				} else
					list($start,$end) = get_quarter_dates($current_qtr - 1,date("Y"));

					$report_title = "Cash receipts dated between ".date(DATE_FORMAT,strtotime($start))." and ".date(DATE_FORMAT, strtotime($end));
			break;

			//Last qtr
			case 8:
				$current_qtr = get_quarter(date("Y-m-d"));
				if ($current_qtr == 1) {
					$previous_qtr = get_quarter(date("Y-m-d",strtotime(date("Y-m-d")." -4 months")));
					list($start,$end) = get_quarter_dates($previous_qtr,date("Y",strtotime(date("Y-m-d")." -4 months")));
				} else
					list($start,$end) = get_quarter_dates($current_qtr - 1,date("Y"));

				$report_title = "Cash receipts dated between ".date(DATE_FORMAT, strtotime($start))." and ".date(DATE_FORMAT,strtotime($end));
			break;

			//This period
			case 9:
				$period_info = get_period_info(date("Y-m-d"));
				$report_title = "Cash receipts dated between ".date(DATE_FORMAT, strtotime($period_info['period_start']))." and ".date(DATE_FORMAT, strtotime($period_info['period_end']));
			break;

			//Last period
			case 10:
				$period_info = get_previous_period(date("Y-m-d"));
				$report_title = "Cash receipts dated between ".date(DATE_FORMAT, strtotime($period_info['period_start']))." and ".date(DATE_FORMAT, strtotime($period_info['period_end']));
			break;

			//This year
			case 11:
				$report_title = "Cash receipts dated ".date("Y");
			break;

			//Last year
			case 12:
				 $report_title = "Cash receipts dated ".(date("Y") - 1);
			break;

			//Date range
			case 13:
			if ($reports->current_report['sort_from_date'] && $reports->current_report['sort_to_date'])
				$report_title = "Cash receipts dated between ".date(DATE_FORMAT, strtotime($reports->current_report['sort_from_date']))." and ".date(DATE_FORMAT, strtotime($reports->current_report['sort_to_date']));
			else
				$report_title = "Cash receipts dated between ".date(DATE_FORMAT, strtotime($reports->current_report['sort_from_date']))." and ".date(DATE_FORMAT);
			break;

		}

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Cash Receipts Report",16,array('justification' => 'center'));
		$this->pdf->ezText($report_title,12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array("customer"	  	=> "Customer",
						"check_no"		=> "Check No.",
						"receipt_date"	=> "Receipt Date",
						"receipt_amt"	=> "Receipt Amount");

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$col1 = $start;
		$col2 = 200;
		$col3 = 350;
		$col4 = 450;
		$width1 = 150;
		$width2 = 100;
		$right_adj = 15; // adjustment for right justified columns (aging schedules)
		$font_size_headers = 9;

		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addTextWrap($col1, $this->pdf->y,$width1,$font_size_headers,"<b>".$cols['customer']."</b>",'left');
		$this->pdf->addTextWrap($col2, $this->pdf->y,$width1,$font_size_headers,"<b>".$cols['check_no']."</b>",'left');
		$this->pdf->addTextWrap($col3, $this->pdf->y,$width2,$font_size_headers,"<b>".$cols['receipt_date']."</b>",'right');
		$this->pdf->addTextWrap($col4, $this->pdf->y,$width2,$font_size_headers,"<b>".$cols['receipt_amt']."</b>",'right');

		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$font_size_cust = 8;
		$font_size_detail = 7;
		$this->pdf->ezSetDy(-5);
		$this->pdf->setStrokeColor(.8,.8,.8);
		if (is_array($reports->payment)) {
			$total_receipts = 0;
			while (list($customer_hash,$check_array) = each($reports->payment)) {
				$displayOnce = true;
				$isData = false;
				$total_check_receipt = 0;
				while (list($check_no,$check_data) = each($check_array)) {
					if ($displayOnce) {
						$cust_name = ($check_data[0]['customer_name'] ? $check_data[0]['customer_name'] : $check_data[0]['vendor_name']);
						if ($cust_name && $cust_name != "") {
							$this->pdf->addTextWrap($col1,$this->pdf->y,$width1,$font_size_cust + 1,"".stripslashes($cust_name)."");
							$displayOnce = false;
							$this->pdf->ezSetDy(-15);
							$isData = true;
						}
					}
					if ($isData) {
						for ($i = 0; $i < count($check_data); $i++) {
							$total_check_receipt += $check_data[$i]['total_reciept'];
							$total_receipts += $check_data[$i]['total_reciept'];
							$this->pdf->addTextWrap($col2,$this->pdf->y,$width1,$font_size_cust,($check_data[$i]['check_no'] ? $check_data[$i]['check_no'] : "Unassigned") );
							$this->pdf->addTextWrap($col3,$this->pdf->y,$width2,$font_size_cust,date(DATE_FORMAT,strtotime($check_data[$i]['receipt_date'])),'right');
							$this->pdf->addTextWrap($col4,$this->pdf->y,$width2,$font_size_cust,$this->format_dollars_show_zero($check_data[$i]['total_reciept']),'right');
							$this->pdf->ezSetDy(-15);
							if ($reports->current_report['detail']) {
								for ($j = 0; $j < count($reports->detail[$customer_hash][$check_data[$i]['check_no']]['invoice_no']); $j++) {
									$invoice_no = $reports->detail[$customer_hash][$check_data[$i]['check_no']]['invoice_no'][$j];
									$this->pdf->addTextWrap($col2,$this->pdf->y,$width1,$font_size_detail, ($invoice_no ? "Invoice ".$invoice_no : "Deposit"),'right');
									$this->pdf->addTextWrap($col3,$this->pdf->y,$width2,$font_size_detail,date(DATE_FORMAT,strtotime($reports->detail[$customer_hash][$check_data[$i]['check_no']]['date'][$j])),'right');
									$this->pdf->addTextWrap($col4,$this->pdf->y,$width2,$font_size_detail,$this->format_dollars_show_zero($reports->detail[$customer_hash][$check_data[$i]['check_no']]['amount'][$j]),'right');
									$this->pdf->ezSetDy(-15);
								}
							}
						}
						$this->pdf->ezSetDy(-15);
					}
				}
				if ($isData) {
					$this->pdf->line($col4+30,$this->pdf->y + 20,$col4+$width2,$this->pdf->y + 20);
					$this->pdf->addTextWrap($col4,$this->pdf->y + 5,$width2,$font_size_cust + 1,$this->format_dollars_show_zero($total_check_receipt),'right');
					$this->pdf->ezSetDy(-10);
					$this->pdf->line($this->pdf-> ez['leftMargin'],$this->pdf->y + 10,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y + 10);
					$this->pdf->ezSetDy(-5);
				}
			}
			$this->pdf->addTextWrap($col4,$this->pdf->y,$width2,11,"<b>".$this->format_dollars($total_receipts)."</b>",'right');
		}

	}
/**
 * Generates a pdf document of a sales tax liability report given the report_hash.
 *
 * @param String $report_hash
 */
	function sales_tax($report_hash) {
		$reports = new reports($this->current_hash);
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for sales_tax reports object.
		$reports->doit_sales_tax();

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();
		$title = MY_COMPANY_NAME." Sales Tax Liability Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);
		$report_title = "Invoices dated between ".date(DATE_FORMAT,strtotime($reports->report_date_start))." and ".date(DATE_FORMAT,strtotime($reports->report_date_end));

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Sales Tax Liability Report",16,array('justification' => 'center'));
		$this->pdf->ezText($report_title,12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array(
		    "invoice_no"        =>  "Invoice",
			"invoice_date"      =>  "Invoice Date",
		    "invoice_total"     =>  "Total Sale",
		    "non_taxable"       =>  "Non-Taxable",
		    "taxable"           =>  "Taxable",
    		"rate"              =>  "Rate",
		    "tax_collected"     =>  "Collected",
		    "liability"         =>  "Liability",
		    "install_loc"       =>  "Installation Location"
		);

		// Calculate x coordinate for each column
		$start = $this->pdf->ez['leftMargin'] + 30; // X coordinate of first column
		$space = 60;  // space between first set of columns
		$space2 = 55; // space between second set of columns
		$right_adj = 10; // adjustment for right justified columns
		$start2 = $start + $space + $right_adj;
		$col1 = $start;
		$col2 = $start + $space;
		$col3 = $start2 + $space2 - $right_adj;
		$col4 = $start2 + 2 * $space2;
		$col5 = $start2 + 3 * $space2;
		$col6 = $start2 + 4 * $space2;
		$col7 = $start2 + 4.5 * $space2;
		$col8 = $start2 + 5.25 * $space2;
		$col9 = $start2 + 6.25 * $space2;
		$font_size = 8;

		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addText($start, $this->pdf->y, $font_size, "<b>{$cols['invoice_no']}</b>");
		$this->pdf->addText($col2, $this->pdf->y, $font_size, "<b>{$cols['invoice_date']}</b>");
		$this->pdf->addText($col3, $this->pdf->y, $font_size, "<b>{$cols['invoice_total']}</b>");
        $this->pdf->addText($col4, $this->pdf->y, $font_size, "<b>{$cols['non_taxable']}</b>");
		$this->pdf->addText($col5 + 10, $this->pdf->y, $font_size, "<b>{$cols['taxable']}</b>");
		$this->pdf->addText($col6, $this->pdf->y, $font_size, "<b>{$cols['rate']}</b>");
		$this->pdf->addText($col7, $this->pdf->y, $font_size, "<b>{$cols['tax_collected']}</b>");
		$this->pdf->addText($col8, $this->pdf->y, $font_size, "<b>{$cols['liability']}</b>");
		$this->pdf->addText($col9, $this->pdf->y, $font_size, "<b>{$cols['install_loc']}</b>");
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		
		$total_sell = $total_non = $total_tax = $total_coll = $total_owed = 0;
		reset( $reports->customers );
		
		$this->pdf->setStrokeColor(.7, .7, .7);
		$this->pdf->setColor(.8, .8, .8);
		$this->pdf->setLineStyle(1);
		
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y + 15, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y + 15);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y);
		$this->pdf->filledRectangle($this->pdf->ez['leftMargin'], $this->pdf->y, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'], 15);
		$this->pdf->setColor(0,0,0);
		$this->pdf->addText($this->pdf->ez['leftMargin'] + 5, $this->pdf->y + 2, 12, "<b>{$reports->tax_state}</b>");
		
		$this->pdf->ezSetDy(-10);

		$k = 0;
		if ( $reports->state_tax ) {

            while ( list($customer_hash, $customer) = each( $reports->customers ) ) {

            	if ( $reports->state_tax[ $customer_hash ] ) {

            		if ( $k > 0 )
                        $this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y + 15, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y + 15);

                    $this->pdf->addText($this->pdf->ez['leftMargin'] + 10, $this->pdf->y, 9, $customer['customer_name']);
                    $this->pdf->ezSetDy(-10);
                    if ( $customer['city'] && $customer['state'] && $customer['zip'] ) {
                        $this->pdf->addText($this->pdf->ez['leftMargin'] + 10, $this->pdf->y, 9, "{$customer['city']}, {$customer['state']} {$customer['zip']}");
                        $this->pdf->ezSetDy(-10);
                    }
                    if ( $customer['tax_id'] ) {
                        $this->pdf->addText($this->pdf->ez['leftMargin'] + 10, $this->pdf->y, 9, "Tax Exemption ID: {$customer['tax_id']}");
                        $this->pdf->ezSetDy(-10);
                    }

                    $this->pdf->ezSetDy(-10);

                    reset($reports->state_tax[ $customer_hash ]);
                    while ( list($invoice_hash, $invoice_detail) = each($reports->state_tax[ $customer_hash ]) ) {

                        $this->pdf->addText($col1, $this->pdf->y, 5, $invoice_detail['invoice_no']);
                        $this->pdf->addText($col2, $this->pdf->y, 5, $invoice_detail['invoice_date']);
                        $this->pdf->addTextWrap($col3, $this->pdf->y, 35, 5, $this->format_dollars_show_zero( $invoice_detail['invoice_net'] ), 'right');
                        $this->pdf->addTextWrap($col4, $this->pdf->y, 40, 5, $this->format_dollars_show_zero( bcsub($invoice_detail['invoice_net'], $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['taxable'], 2) ), 'right');
                        $this->pdf->addTextWrap($col5, $this->pdf->y, 35, 5, $this->format_dollars_show_zero($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['taxable']), 'right');
                        $this->pdf->addTextWrap($col6, $this->pdf->y, 15, 5, trim_decimal( bcmul($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['rate'], 100, 4) ) . "%", 'right');
                        $this->pdf->addTextWrap($col7, $this->pdf->y, 30, 5, $this->format_dollars_show_zero($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['tax_collected']), 'right');
                        $this->pdf->addTextWrap($col8, $this->pdf->y, 35, 5, $this->format_dollars_show_zero($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['liability']), 'right');
                        $this->pdf->addTextWrap($col9, $this->pdf->y, 2 * $space2, 5, ( strlen($invoice_detail['install_city']) > 25 ? strlen($invoice_detail['install_city'], 0, 23) : $invoice_detail['install_city'] ) . ", {$invoice_detail['install_state']} {$invoice_detail['install_zip']}", 'left');

                        $this->pdf->ezSetDy(-10);

                        $total_sell = bcadd($total_sell, $invoice_detail['invoice_net'], 2);
                        $total_non = bcadd($total_non, bcsub($invoice_detail['invoice_net'], $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['taxable'], 2), 2);
                        $total_tax = bcadd($total_tax, $reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['taxable'], 2);
                        $total_coll = $total_coll + round($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['tax_collected'], 2);
                        $total_owed = $total_owed + round($reports->local_tax[ $reports->default_tax ][ $customer_hash ][ $invoice_hash ]['liability'], 2);

                        if ( $reports->credit[ $invoice_hash ][ $reports->default_tax ] ) {

                        	reset($reports->credit[ $invoice_hash ][ $reports->default_tax ]);
                            while ( list($credit_hash, $credit_detail) = each($reports->credit[ $invoice_hash ][ $reports->default_tax ]) ) {

                                $this->pdf->addText($col1, $this->pdf->y, 5, $credit_detail['credit_no']);
                            	$this->pdf->addTextWrap($col3, $this->pdf->y, 35, 5, $this->format_dollars_show_zero( bcmul($credit_detail['credit_net'], -1, 2) ), 'right');
                            	$this->pdf->addTextWrap($col5, $this->pdf->y, 35, 5, $this->format_dollars_show_zero( bcmul($credit_detail['credit_net'], -1, 2) ), 'right');
                            	$this->pdf->addTextWrap($col6, $this->pdf->y, 15, 5, trim_decimal( bcmul($credit_detail['rate'], 100, 4) ) . "%", 'right');
                                $this->pdf->addTextWrap($col7, $this->pdf->y, 30, 5, $this->format_dollars_show_zero( bcmul($credit_detail['tax_collected'], -1, 2) ), 'right');
                                $this->pdf->addTextWrap($col8, $this->pdf->y, 35, 5, $this->format_dollars_show_zero( bcmul($credit_detail['liability'], -1, 2) ), 'right');

                                $this->pdf->ezSetDy(-10);

                                $total_sell = bcsub($total_sell, $credit_detail['credit_net'], 2);
                                $total_tax = bcsub($total_tax, $credit_detail['credit_net'], 2);
                                $total_coll = bcsub($total_coll, $credit_detail['tax_collected'], 2);
                                $total_owed = bcsub($total_owed, $credit_detail['liability'], 2);
                            }
                        }
                    }

                    $k++;
                    $this->pdf->ezSetDy(-10);
            	}
            }

            $this->pdf->ezSetDy(-10);
            $this->pdf->setLineStyle(1, '', array(4, 2));

            $this->pdf->line($col3 - 12, $this->pdf->y + 10, $col3 + 35, $this->pdf->y + 10);
            $this->pdf->addTextWrap($col3 - 10, $this->pdf->y, 45, 7, $this->format_dollars_show_zero( $total_sell ), 'right');

            $this->pdf->line($col4 - 12, $this->pdf->y + 10, $col4 + 40, $this->pdf->y + 10);
            $this->pdf->addTextWrap($col4 - 5, $this->pdf->y, 45, 7, $this->format_dollars_show_zero( $total_non ) , 'right');

            $this->pdf->line($col5 - 5, $this->pdf->y + 10, $col5 + 35, $this->pdf->y + 10);
            $this->pdf->addTextWrap($col5 - 5, $this->pdf->y, 40, 7, $this->format_dollars_show_zero( $total_tax ) , 'right');

            $this->pdf->line($col6 - 5, $this->pdf->y + 10, $col6 + 15, $this->pdf->y + 10);

            $this->pdf->line($col7 - 5, $this->pdf->y + 10, $col7 + 30, $this->pdf->y + 10);
            $this->pdf->addTextWrap($col7 - 5, $this->pdf->y, 35, 7, $this->format_dollars_show_zero( $total_coll ) , 'right');

            $this->pdf->line($col8 - 5, $this->pdf->y + 10, $col8 + 35, $this->pdf->y + 10);
            $this->pdf->addTextWrap($col8 - 5, $this->pdf->y, 40, 7, $this->format_dollars_show_zero( $total_owed ) , 'right');

            $this->pdf->ezSetDy(-25);

            $total_sell = $total_non = $total_tax = $total_coll = $total_owed = 0;

            reset( $reports->tax_rules );
            reset( $reports->local_tax[ $tax_hash ] );
            while ( list($tax_hash, $tax_rule) = each($reports->tax_rules) ) {

                if ( $reports->local_tax[ $tax_hash ] && $tax_hash != $reports->default_tax ) {

		            $this->pdf->setStrokeColor(.7, .7, .7);
		            $this->pdf->setColor(.8, .8, .8);
		            $this->pdf->setLineStyle(1);

		            $this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y + 15, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y + 15);
		            $this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y);
		            $this->pdf->filledRectangle($this->pdf->ez['leftMargin'], $this->pdf->y, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->ez['leftMargin'], 15);
		            $this->pdf->setColor(0,0,0);
		            $this->pdf->addText($this->pdf->ez['leftMargin'] + 5, $this->pdf->y + 2, 12, "<b>{$tax_rule['local']}</b>");

		            $this->pdf->ezSetDy(-10);

		            $k = 0;
                    reset( $reports->customers );
                    while ( list($customer_hash, $customer) = each($reports->customers) ) {

                        if ( $reports->local_tax[ $tax_hash ][ $customer_hash ] ) {

		                    if ( $k > 0 )
		                        $this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y + 15, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y + 15);

		                    $this->pdf->addText($this->pdf->ez['leftMargin'] + 10, $this->pdf->y, 9, $customer['customer_name']);
		                    $this->pdf->ezSetDy(-10);
		                    if ( $customer['city'] && $customer['state'] && $customer['zip'] ) {
		                        $this->pdf->addText($this->pdf->ez['leftMargin'] + 10, $this->pdf->y, 9, "{$customer['city']}, {$customer['state']} {$customer['zip']}");
		                        $this->pdf->ezSetDy(-10);
		                    }
		                    if ( $customer['tax_id'] ) {
		                        $this->pdf->addText($this->pdf->ez['leftMargin'] + 10, $this->pdf->y, 9, "Tax Exemption ID: {$customer['tax_id']}");
		                        $this->pdf->ezSetDy(-10);
		                    }

		                    $this->pdf->ezSetDy(-10);

                            reset( $reports->local_tax[ $tax_hash ][ $customer_hash ] );
                            while ( list($invoice_hash, $invoice_detail) = each($reports->local_tax[ $tax_hash ][ $customer_hash ]) ) {

		                        		$this->pdf->addText($col1, $this->pdf->y, 5, $reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_no']);
		                        		$this->pdf->addText($col2, $this->pdf->y, 5, $reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_date']);
                                        $this->pdf->addTextWrap($col3, $this->pdf->y, 35, 5, $this->format_dollars_show_zero( $reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_net'] ), 'right');
                                        $this->pdf->addTextWrap($col4, $this->pdf->y, 40, 5, $this->format_dollars_show_zero( bcsub($reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_net'], $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['taxable'], 2) ), 'right');
                                        $this->pdf->addTextWrap($col5, $this->pdf->y, 35, 5, $this->format_dollars_show_zero($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['taxable']), 'right');
                                        $this->pdf->addTextWrap($col6, $this->pdf->y, 15, 5, trim_decimal( bcmul($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['rate'], 100, 4) ) . "%", 'right');
                                        $this->pdf->addTextWrap($col7, $this->pdf->y, 30, 5, $this->format_dollars_show_zero($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['tax_collected']), 'right');
                                        $this->pdf->addTextWrap($col8, $this->pdf->y, 35, 5, $this->format_dollars_show_zero($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['liability']), 'right');
                                        $this->pdf->addTextWrap($col9, $this->pdf->y, 2 * $space2, 5, ( strlen($reports->state_tax[ $customer_hash ][ $invoice_hash ]['install_city']) > 25 ? strlen($reports->state_tax[ $customer_hash ][ $invoice_hash ]['install_city'], 0, 23) : $reports->state_tax[ $customer_hash ][ $invoice_hash ]['install_city'] ) . ", {$reports->state_tax[ $customer_hash ][ $invoice_hash ]['install_state']} {$reports->state_tax[ $customer_hash ][ $invoice_hash ]['install_zip']}", 'left');

		                        $this->pdf->ezSetDy(-10);

		                        $total_sell = bcadd($total_sell, $reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_net'], 2);
		                        $total_non = bcadd($total_non, bcsub($reports->state_tax[ $customer_hash ][ $invoice_hash ]['invoice_net'], $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['taxable'], 2), 2);
		                        $total_tax = bcadd($total_tax, $reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['taxable'], 2);
		                        $total_coll = $total_coll + round($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['tax_collected'],2);
		                        $total_owed = $total_owed + round($reports->local_tax[ $tax_hash ][ $customer_hash ][ $invoice_hash ]['liability'],2);

		                        if ( $reports->credit[ $invoice_hash ][ $tax_hash ] ) {

		                        	reset($reports->credit[ $invoice_hash ][ $tax_hash ]);
		                            while ( list($credit_hash, $credit_detail) = each($reports->credit[ $invoice_hash ][ $tax_hash ]) ) {

		                                $this->pdf->addText($col1, $this->pdf->y, 5, $credit_detail['credit_no']);
		                                $this->pdf->addTextWrap($col3, $this->pdf->y, 35, 5, $this->format_dollars_show_zero( bcmul($credit_detail['credit_net'], -1, 2) ), 'right');
		                                $this->pdf->addTextWrap($col5, $this->pdf->y, 35, 5, $this->format_dollars_show_zero( bcmul($credit_detail['credit_net'], -1, 2) ), 'right');
		                                $this->pdf->addTextWrap($col6, $this->pdf->y, 15, 5, trim_decimal( bcmul($credit_detail['rate'], 100, 4) ) . "%", 'right');
		                                $this->pdf->addTextWrap($col7, $this->pdf->y, 30, 5, $this->format_dollars_show_zero( bcmul($credit_detail['tax_collected'], -1, 2) ), 'right');
		                                $this->pdf->addTextWrap($col8, $this->pdf->y, 35, 5, $this->format_dollars_show_zero( bcmul($credit_detail['liability'], -1, 2) ), 'right');

		                                $this->pdf->ezSetDy(-10);

                                        $total_sell = bcsub($total_sell, $credit_detail['credit_net'], 2);
                                        $total_tax = bcsub($total_tax, $credit_detail['credit_net'], 2);
                                        $total_coll = $total_coll - $credit_detail['tax_collected'];
                                        $total_owed = $total_owed - $credit_detail['liability'];
		                            }
		                        }

                            }

                            $k++;
                            $this->pdf->ezSetDy(-10);
                        }
                    }

                    $this->pdf->ezSetDy(-10);
                    $this->pdf->setLineStyle(1, '', array(4, 2));

					$this->pdf->line($col3 - 12, $this->pdf->y + 10, $col3 + 35, $this->pdf->y + 10);
		            $this->pdf->addTextWrap($col3 - 10, $this->pdf->y, 45, 7, $this->format_dollars_show_zero( $total_sell ), 'right');
		
		            $this->pdf->line($col4 - 12, $this->pdf->y + 10, $col4 + 40, $this->pdf->y + 10);
		            $this->pdf->addTextWrap($col4 - 5, $this->pdf->y, 45, 7, $this->format_dollars_show_zero( $total_non ) , 'right');
		
		            $this->pdf->line($col5 - 5, $this->pdf->y + 10, $col5 + 35, $this->pdf->y + 10);
		            $this->pdf->addTextWrap($col5 - 5, $this->pdf->y, 40, 7, $this->format_dollars_show_zero( $total_tax ) , 'right');
		
		            $this->pdf->line($col6 - 5, $this->pdf->y + 10, $col6 + 15, $this->pdf->y + 10);
		
		            $this->pdf->line($col7 - 5, $this->pdf->y + 10, $col7 + 30, $this->pdf->y + 10);
		            $this->pdf->addTextWrap($col7 - 5, $this->pdf->y, 35, 7, $this->format_dollars_show_zero( $total_coll ) , 'right');
		
		            $this->pdf->line($col8 - 5, $this->pdf->y + 10, $col8 + 35, $this->pdf->y + 10);
		            $this->pdf->addTextWrap($col8 - 5, $this->pdf->y, 40, 7, $this->format_dollars_show_zero( $total_owed ) , 'right');
                    

                    $this->pdf->ezSetDy(-25);

                    $total_sell = $total_non = $total_tax = $total_coll = $total_owed = 0;
                }
            }
		}else {
			
			$this->pdf->ezSetDy(-10);
			
			$this->pdf->line($col3 - 12, $this->pdf->y + 10, $col3 + 35, $this->pdf->y + 10);
			$this->pdf->addTextWrap($col3 - 10, $this->pdf->y, 45, 7, $this->format_dollars_show_zero( $total_sell ), 'right');
			
			$this->pdf->line($col4 - 12, $this->pdf->y + 10, $col4 + 40, $this->pdf->y + 10);
			$this->pdf->addTextWrap($col4 - 5, $this->pdf->y, 45, 7, $this->format_dollars_show_zero( $total_non ) , 'right');
			
			$this->pdf->line($col5 - 5, $this->pdf->y + 10, $col5 + 35, $this->pdf->y + 10);
			$this->pdf->addTextWrap($col5 - 5, $this->pdf->y, 40, 7, $this->format_dollars_show_zero( $total_tax ) , 'right');
			
			$this->pdf->line($col6 - 5, $this->pdf->y + 10, $col6 + 15, $this->pdf->y + 10);
			
			$this->pdf->line($col7 - 5, $this->pdf->y + 10, $col7 + 30, $this->pdf->y + 10);
			$this->pdf->addTextWrap($col7 - 5, $this->pdf->y, 35, 7, $this->format_dollars_show_zero( $total_coll ) , 'right');
			
			$this->pdf->line($col8 - 5, $this->pdf->y + 10, $col8 + 35, $this->pdf->y + 10);
			$this->pdf->addTextWrap($col8 - 5, $this->pdf->y, 40, 7, $this->format_dollars_show_zero( $total_owed ) , 'right');
		}
	}

/**
 * Generates a pdf document of a trial balance report given the report_hash.
 *
 * @param String $report_hash
 */
	function trial_balance($report_hash) {
		$reports = new reports($this->current_hash);
		$_POST['report_hash'] = $report_hash;
		$_POST['doit_print'] = 1;

		// Populate data for ar_report object.
		$reports->doit_trial_balance();

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Trial Balance Report";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Trial Balance",16,array('justification' => 'center'));

		$report_title = ($reports->report_date_start ? " Between Dates ".date(DATE_FORMAT,strtotime($reports->report_date_start)). " and ".date(DATE_FORMAT,strtotime($reports->report_date_end)) :
								"Through ".date(DATE_FORMAT,strtotime($reports->report_date_end)));

		$this->pdf->ezText($report_title,12,array('justification' => 'center', 'leading' => 15));

		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
		$this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
		//  Column headers
		$cols = array("account_detail"	=>	"Account",
						   "debits" 	=>	"Debit",
						   "credits" 	=>	"Credit");

		// Calculate x coordinate for each column
		$col1 = $this->pdf->ez['leftMargin'] + 30;
		$col2 = 300;
		$col3 = 450;
		$right_adj = 25;

		// Print header for each column
		$this->pdf->ezSetDy(-20);
		$this->pdf->addText($col1, $this->pdf->y,9,"<b>".$cols['account_detail']."</b>");
		$this->pdf->addTextWrap($col2, $this->pdf->y,($col3 - $col2 - 10), 9,"<b>".$cols['debits']."</b>",'right');
		$this->pdf->addTextWrap($col3, $this->pdf->y,(($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']) - $col3), 9,"<b>".$cols['credits']."</b>",'right');
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$debit_total = $credit_total = 0;
		$total_accounts = count($reports->detail);
		$j = 0;
		$this->pdf->setStrokeColor(.8,.8,.8);
		foreach ($reports->detail as $acct) {
			$debit = $credit = 0;
			$j++;
			if ($acct['account_hash'] != DEFAULT_PROFIT_ACCT) {
				if (($acct['account_action'] == 'DR' && $acct['total'] > 0) || ($acct['account_action'] == 'CR' && $acct['total'] < 0)) {
					if ($acct['total'] < 0)
						$acct['total'] *= -1;

					$debit = $acct['total'];
					$debit_total += $debit;
				} elseif (($acct['account_action'] == 'CR' && $acct['total'] > 0) || ($acct['account_action'] == 'DR' && $acct['total'] < 0)) {
					if ($acct['total'] < 0)
						$acct['total'] *= -1;

					$credit = $acct['total'];
					$credit_total += $credit;
				}

				$font_size = 10;
				$font_size2 = 6;
				$this->pdf->addText($col1, $this->pdf->y + 1, $font_size, $acct['account_no']." - ". $acct['account_name']);
				$this->pdf->addTextWrap($col2, $this->pdf->y + 1, ($col3 - $col2 - 10), $font_size, $this->format_dollars($debit), 'right');
				$this->pdf->addTextWrap($col3, $this->pdf->y + 1, (($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']) - $col3), $font_size, $this->format_dollars($credit), 'right');

				if ($j < $total_accounts && $reports->current_report['detail'] != 2) {
					$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y - 3, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y - 3);
					$this->pdf->ezSetDy(-1);
				}
				if ($reports->current_report['detail'] == 2) {
					for ($i = 0; $i < count($reports->sub_detail[$acct['account_hash']][0]); $i++) {
						$this->pdf->ezSetDy(-5);
						$this->pdf->addTextWrap($col1 + 15, $this->pdf->y - 3, ($col2 - $col1 - 10), $font_size2, $reports->sub_detail[$acct['account_hash']][0][$i],'left');
						$this->pdf->addTextWrap($col2, $this->pdf->y - 3,($col3 - $col2 - 10), $font_size2, $this->format_dollars($reports->sub_detail[$acct['account_hash']][1][$i]),'right');
						$this->pdf->addTextWrap($col3, $this->pdf->y - 3,(($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']) - $col3), $font_size2, $this->format_dollars($reports->sub_detail[$acct['account_hash']][2][$i]),'right');
						//if (($j < $total_accounts && $i < count($reports->sub_detail[$acct['account_hash']][0]) - 1) {
							$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y - 5, $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'], $this->pdf->y - 5  );
							$this->pdf->ezSetDy(-7);
						//}
					}
				}
				$this->pdf->ezSetDy(-15);

			}
		}
		$this->pdf->ezSetDy(-10);
		$this->pdf->setStrokeColor(0,0,0);
		$this->pdf->line($col2,$this->pdf->y + 15,($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']),$this->pdf->y + 15);
		$this->pdf->addTextWrap($col2, $this->pdf->y, ($col3 - $col2 - 10), 10, $this->format_dollars($debit_total), 'right');
		$this->pdf->addTextWrap($col3, $this->pdf->y, (($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']) - $col3), 10, $this->format_dollars($credit_total), 'right');
	}
	/**
	 * Return a correctly formatted dollar amount string given a decimal number. Returns null if 0.
	 * Ex:  format_dollars(47282.492) returns $47,282.49
	 * Ex2: format_dollars(-52.22) returns ($52.22)
	 * Ex3: format_dollars(0) returns NULL
	 */
	function format_dollars($number) {
		return ($number != 0 ? ($number < 0 ? "($".number_format($number * -1,2).")" : "$".number_format($number,2)) : NULL);
	}
	/**
	 * Return a correctly formatted dollar amount string given a decimal number. Returns $0.00 if 0.
	 * Ex:  format_dollars(47282.492) returns $47,282.49
	 * Ex2: format_dollars(-52.22) returns ($52.22)
	 * Ex3: format_dollars(0) returns $0.00
	 */
	function format_dollars_show_zero($number) {
		return ($number != 0 ? ($number < 0 ? "($".number_format($number * -1,2).")" : "$".number_format($number,2)) : "$0.00");
	}
	/**
	 * Return a check box, either empty or checked given a boolean value.
	 * Ex:  0 returns [ ]
	 * Ex2: 1 returns [X]
	 */
	function check_box($isChecked) {
		return ($isChecked ? "[ X ]" : "[   ]");
	}

	/**
	 * Print a pro forma invoice
	 *
	 * @param unknown_type $proposal_hash
	 */
	function proforma_invoice($proposal_hash) {
		$p = new proposals();
		$valid = $p->fetch_master_record($proposal_hash);
		if (!$valid)
			die('Invalid proposal hash.');
		$proforma_items = $_SESSION['proforma_items'];

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		// Get margins from system vars if they are defined
		$sys_var = fetch_sys_var('PROPOSAL_MARGIN');
		if ($sys_var) {
			$margins = explode(":",$sys_var);
			$this->pdf->ezSetMargins($margins[0],$margins[1],$margins[2],$margins[3]);
		} else
			$this->pdf->ezSetMargins(30,30,30,30);

		// Width of page adjusted for margins
		$adjWidth = $this->pdf->ez['pageWidth'] - $this->pdf->ez['leftMargin'] - $this->pdf->ez['rightMargin'];
		$rightXpos = $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'];

		$title = stripslashes($p->current_proposal['customer'])." : ".($this->print_pref['title'] == "Pro Forma Invoice" ? $this->template->current_template['title']['value'] : $this->print_pref['title'])." ".$this->print_pref['invoice_no'];
		$author = stripslashes($p->current_proposal['sales_rep']);
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Author',$author);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);

		$customers = new customers($_SESSION['id_hash']);
		$customers->fetch_master_record($p->current_proposal['customer_hash']);
		$deposit_req = $customers->current_customer['deposit_percent'];

		$customer_name = stripslashes($p->current_proposal['customer']);
		// Fetch Customer Contact
		if ($p->current_proposal['customer_contact']) {
			$customers->fetch_contact_record($p->current_proposal['customer_contact']);
			$contact_name = $customers->current_contact['contact_name'];
		}

		// Fetch customer address
		if ($p->current_proposal['customer_hash']) {
			$class = 'customers';
			$id = $p->current_proposal['customer_hash'];
			if ($p->current_proposal['bill_to_hash'])
				$loc_hash = $p->current_proposal['bill_to_hash'];

			$obj = new customers($this->current_hash);
			$obj->fetch_master_record($id);
			if ($loc_hash) {
				$obj->fetch_location_record($loc_hash);
				$customer_info = stripslashes($obj->current_location['location_name']) . "\n" .
				( $obj->current_location['location_street'] ?
					stripslashes($obj->current_location['location_street']) . "\n" : NULL
				) .
				stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

			} else {
				$customer_info = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
				) .
				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
			}

			unset($obj);
		}

		// Fetch install address
		if ($p->current_proposal['install_addr_hash']) {
			list($class,$id,$loc_hash) = explode("|",$p->current_proposal['install_addr_hash']);
			$obj = new $class($_SESSION['id_hash']);
			if ($loc_hash) {
				$obj->fetch_location_record($loc_hash);
				$install_loc = stripslashes($obj->current_location['location_name']) . "\n" .
				( $obj->current_location['location_street'] ?
					stripslashes($obj->current_location['location_street']) . "\n" : NULL
				) .
				stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

			} else {
				$obj->fetch_master_record($id);
				$install_loc = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
				) .
				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
			}
			unset($obj);
		}

		// Fetch ship to address
		if ($p->current_proposal['ship_to_hash']) {
			list($class,$id,$ship_hash) = explode("|",$p->current_proposal['ship_to_hash']);
			if ($class) {
				$obj = new $class($this->current_hash);
				if ($ship_hash) {
					if ($loc_valid = $obj->fetch_location_record($ship_hash)) {
						$ship_to_loc = stripslashes($obj->current_location['location_name']) . "\n" .
						( $obj->current_location['location_street'] ?
    						stripslashes($obj->current_location['location_street']) . "\n" : NULL
    					) .
    					stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";
					} else
						$ship_to = "Invalid Shipping Location";

				} else {
					$obj->fetch_master_record($id);
					$ship_to_loc = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
					( $obj->{"current_".strrev(substr(strrev($class),1))}["street"] ?
    					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}["street"]) . "\n" : NULL
    				) .
    				stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}["city"]) . ", {$obj->{'current_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
				}
				unset($obj);
			}
		}

		$lines = new line_items($p->proposal_hash);
		$lines->fetch_line_items(0,$lines->total,1);
		if (in_array("item_no",$this->print_pref['item_print_fields']) || in_array("line_no",$this->print_pref['item_print_fields'])) {
			if (!in_array("item_no",$this->print_pref['item_print_fields']) && in_array("line_no",$this->print_pref['item_print_fields']))
				$item_cols['item_no'] = " ";
			elseif (in_array("item_no",$this->print_pref['item_print_fields'])) {
				$item_no_print = true;
				$item_cols['item_no'] = $this->template->current_template['item_no']['value'];
			}
			if (in_array("line_no",$this->print_pref['item_print_fields']))
				$line_no_print = true;
		}
		if (in_array("item_descr",$this->print_pref['item_print_fields']) || in_array("vendor",$this->print_pref['item_print_fields']) || in_array("product",$this->print_pref['item_print_fields']) || in_array("item_tag",$this->print_pref['item_print_fields']) || in_array("item_finish",$this->print_pref['item_print_fields']))
			$item_cols['item_descr'] = $this->template->current_template['item_descr']['value'];
		if (in_array("qty",$this->print_pref['item_print_fields']))
			$item_cols['qty'] = $this->template->current_template['qty']['value'];
		if (in_array("list",$this->print_pref['item_print_fields']))
			$item_cols['list'] = $this->template->current_template['item_list']['value'];
		if (in_array("ext_list",$this->print_pref['item_print_fields']))
			$item_cols['ext_list'] = $this->template->current_template['ext_list']['value'];
		if (in_array("cost",$this->print_pref['item_print_fields']))
			$item_cols['cost'] = $this->template->current_template['item_cost']['value'];
		if (in_array("ext_cost",$this->print_pref['item_print_fields']))
			$item_cols['ext_cost'] = $this->template->current_template['ext_cost']['value'];
		if (in_array("sell",$this->print_pref['item_print_fields']))
			$item_cols['sell'] = $this->template->current_template['item_sell']['value'];
		if (in_array("ext_sell",$this->print_pref['item_print_fields']))
			$item_cols['ext_sell'] = $this->template->current_template['ext_sell']['value'];

		$item_col_attr = array("item_no" 			=>	array('justification'	=>	'left'),
						 	   "item_descr"			=>  array('justification'	=>	'left'),
							   "qty"				=>  array('justification'	=>	'right'),
							   "list"				=>  array('justification'	=>	'right'),
							   "ext_list"			=>  array('justification'	=>	'right'),
							   "cost"				=>  array('justification'	=>	'right'),
							   "ext_cost"			=>  array('justification'	=>	'right'),
							   "sell"				=>  array('justification'	=>	'right'),
							   "ext_sell"			=>  array('justification'	=>	'right')
							   );

		$print_array = array();

		// Fetch proforma item hashes from SESSION variable
		$proforma_items = $_SESSION['proforma_items'];
		$show_detail = ($proforma_items ? true : false);

		// Loop through each line item to create descriptions as well as calculate totals
		for ($i=0; $i < count($proforma_items); $i++) {
			$lines->fetch_line_item_record($proforma_items[$i]);
			if ($lines->current_line['active'] && ($lines->current_line['sell'] > 0) || in_array('zero_sell',$this->print_pref['item_print_fields'])) {
				$descr = (in_array('vendor',$this->print_pref['item_print_fields']) ?
							$lines->current_line['item_vendor'].(!in_array('product',$this->print_pref['item_print_fields']) ?
								"\n" : NULL) : NULL).(in_array('product',$this->print_pref['item_print_fields']) ?
									(in_array('vendor',$this->print_pref['item_print_fields']) ?
										" - " : NULL).$lines->current_line['item_product']."\n\n" : NULL).(in_array('item_descr',$this->print_pref['item_print_fields']) ?
											$lines->current_line['item_descr'] : NULL).(in_array("item_tag",$this->print_pref['item_print_fields']) ?
												($lines->current_line['item_tag1'] ?
													"\n\n".$this->template->current_template['tag']['value']."1: ".$lines->current_line['item_tag1'] : NULL).($lines->current_line['item_tag2'] ?
														"\n".$this->template->current_template['tag']['value']."2: ".$lines->current_line['item_tag2'] : NULL).($lines->current_line['item_tag3'] ?
															"\n".$this->template->current_template['tag']['value']."3: ".$lines->current_line['item_tag3'] : NULL) : NULL);
				if (in_array('item_finish',$this->print_pref['item_print_fields'])) {
					$lines->fetch_line_item_record($lines->current_line['item_hash']);
					$import_data = unserialize(stripslashes($lines->current_line['import_data']));
					if (!$import_data)
						$import_data = unserialize($lines->current_line['import_data']);

					$finish =& $import_data['FINISH'];
					if (is_array($finish)) {
						$descr .= "\n\n".$this->template->current_template['finishes']['value'].":\n";
						for ($j = 0; $j < count($finish); $j++) {
							unset($item_panel);
							$special_chars = array("â„¢","™");
							if ($finish[$j]['PANEL'])
								$item_panel = $finish[$j]['PANEL'];
							else
								$descr .= str_replace($special_chars,"",$finish[$j]['GROUP_DESCR'])." : ".stripslashes(str_replace($special_chars,"",$finish[$j]['DESCR']))." ".($finish[$j]['NAME'] ? "(".stripslashes(str_replace($special_chars,"",$finish[$j]['NAME'])).")" : NULL)."\n";

							if ($item_panel)
								$descr .= "\n".$this->template->current_template['panel_detail']['value'].": ".$item_panel;

						}
					}
				}
				if (in_array('cr',$this->print_pref['item_print_fields'])) {
					$lines->fetch_line_item_record($lines->current_line['item_hash']);
					$import_data = unserialize(stripslashes($lines->current_line['import_data']));
					if (!$import_data)
						$import_data = unserialize($lines->current_line['import_data']);

					$descr .= "\n\n".$this->template->current_template['item_special']['value'].":\n".$import_data['SPECIAL'];
				}

				if (in_array('discounting',$this->print_pref['item_print_fields']) || in_array('gp_margin',$this->print_pref['item_print_fields']) || in_array('list_discount',$this->print_pref['item_print_fields'])) {
					if (in_array('discounting',$this->print_pref['item_print_fields'])) {
						$disc = ($lines->current_line['discount1'] > 0 ?
										$this->template->current_template['discount']['value']." 1: ".($lines->current_line['discount1'] * 100)."% \n" : NULL).($lines->current_line['discount2'] > 0 ?
											$this->template->current_template['discount']['value']." 2: ".($lines->current_line['discount2'] * 100)."% \n" : NULL).($lines->current_line['discount3'] > 0 ?
												$this->template->current_template['discount']['value']." 3: ".($lines->current_line['discount3'] * 100)."% \n" : NULL).($lines->current_line['discount4'] > 0 ?
													$this->template->current_template['discount']['value']." 4: ".($lines->current_line['discount4'] * 100)."% \n" : NULL).($lines->current_line['discount5'] > 0 ?
														$this->template->current_template['discount']['value']." 5: ".($lines->current_line['discount5'] * 100)."% \n" : NULL);
						if ($disc)
							$descr .= "\n\n".$disc;
					}
					if (in_array('gp_margin',$this->print_pref['item_print_fields']))
						$descr .= ($disc ? "\n" : "\n\n").$this->template->current_template['gp_margin']['value'].": ".$lines->current_line['gp_margin'];

					if ( in_array('list_discount', $this->print_pref['item_print_fields']) )
						$descr .=
						( $disc || in_array('gp_margin', $this->print_pref['item_print_fields']) && $lines->current_line['gp_type'] == 'G' ?
    						"\n" : "\n\n"
						) .
						"{$this->template->current_template['list_discount']['value']}: " .
						math::list_discount( array(
							'list'    =>  $lines->current_line['list'],
							'sell'    =>  $lines->current_line['sell']
						) ) . "%";
				}

				$item_data[] = array("item_no" 		=> ($line_no_print ? $this->template->current_template['line']['value'].": ".$lines->current_line['line_no']."\n\n" : NULL).($item_no_print ? $lines->current_line['item_no'] : NULL),
									 "item_descr"	=> $descr,
									 "qty"			=> strrev(substr(strstr(strrev($lines->current_line['qty']),'.'),1)).(str_replace('0','',substr(strrchr($lines->current_line['qty'],'.'),1)) ?
															str_replace('0','',strrchr($lines->current_line['qty'],'.')) : NULL),
									 "list"			=> '$'.number_format($lines->current_line['list'],2),
									 "ext_list"		=> '$'.number_format($lines->current_line['ext_list'],2),
									 "sell"			=> '$'.number_format($lines->current_line['sell'],2),
									 "ext_sell"		=> '$'.number_format($lines->current_line['ext_sell'],2),
									 "cost"			=> '$'.number_format($lines->current_line['cost'],2),
									 "ext_cost"		=> '$'.number_format($lines->current_line['ext_cost'],2),
									 "item_tag"		=> ($lines->current_line['item_tag1'] ?
															$lines->current_line['item_tag1']." \n" : NULL).($lines->current_line['item_tag2'] ?
																$lines->current_line['item_tag2']." \n" : NULL).($lines->current_line['item_tag3'] ?
																	$lines->current_line['item_tag3'] : NULL));

				if ($lines->current_line['group_hash'])
					$group_totals[$lines->current_line['group_hash']] += $lines->current_line['ext_sell'];

				// Find group hash of next item if exists
				$next_item_hash = $proforma_items[$i+1];
				if ($next_item_hash) {
					$result = $this->db->query("SELECT line_items.group_hash
												FROM line_items
												WHERE item_hash = '$next_item_hash'");
					$row = $this->db->fetch_assoc($result);
					$next_group_hash = $row['group_hash'];
				}
				if ($lines->line_info[$i]['group_hash'] != $lines->line_info[$i+1]['group_hash'] || !$lines->line_info[$i+1]) {
					if ($lines->line_info[$i]['group_hash'])
						$group_array[count($print_array)] = array('title'	=>	nl2br(htmlspecialchars($lines->current_line['group_descr'])),
																  'total'	=>	$group_totals[$lines->current_line['group_hash']]);

					array_push($print_array,$item_data);
					unset($item_data);
				}
				$total_sell += $lines->current_line['ext_sell'];
			} else {
				// Add lines to group if next line is a different group. Added for Trac #1338
				if ($lines->line_info[$i]['group_hash'] != $lines->line_info[$i+1]['group_hash'] || !$lines->line_info[$i+1]) {
					if ($lines->line_info[$i]['group_hash'])
						$group_array[count($print_array)] = array('title'	=>	htmlspecialchars($lines->line_info[$i]['group_descr']),
																  'total'	=>	$group_totals[$lines->line_info[$i]['group_hash']]);

					array_push($print_array,$item_data);
					unset($item_data);
				}
			}
		}
        if ($item_data) {
            if (is_array($print_array))
                array_push($print_array,$item_data);
            else
                $print_array = array($item_data);
        }

		list($total_tax,$tax_rules,$tax_local,$indiv_tax) = $lines->calc_tax($proforma_items);

		for ($i = 0; $i < count($lines->proposal_comments); $i++) {
			if ($lines->proposal_comments[$i]['comment_action'] == 2)
				$comment_data[] = array('comments' => htmlspecialchars($lines->proposal_comments[$i]['comments']));
		}

		$headfoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$y_start = 825 - $this->h;
		$y = $this->pdf->y;

		if ($this->print_pref['print_logo'] && $this->img) {
			$this->pdf->ezImage($this->img,5,$this->w,'','left');
			$this->pdf->y = $y;

		}
		$y_start -= 25;
		$this->pdf->ezSetDy(-10);
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(2,'round');
		$x_start = 40 + $this->w;

		//Sales Rep
		$this->pdf->ezText($this->template->current_template['sales_rep']['value'].": ".$author,10,array('left' => $this->w + 10));
		//Pro Forma Invoice title
		$title = ($this->print_pref['title'] == "Pro Forma Invoice" ? $this->template->current_template['title']['value'] : $this->print_pref['title']);
		$text_width = $this->pdf->getTextWidth(20,$title);
		$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,804,20,$title);
		//Customer
		$width_customer_name = 525.28-$text_width-$this->w;
		$this->pdf->line($x_start,801.89,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],801.89);
		$this->pdf->addTextWrap($x_start,805.89,$width_customer_name,10,$this->template->current_template['customer']['value'].": ".stripslashes($p->current_proposal['customer']));
		//Pro Forma Invoice number
		$text_width = $this->pdf->getTextWidth(15,$this->print_pref['invoice_no']);
		$font_height = $this->pdf->getFontHeight(15);
		$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,801.89 - $font_height,15,$this->print_pref['invoice_no']);
		//Proposal date
		$text_width = $this->pdf->getTextWidth(10,date(DATE_FORMAT));
		$font_height = $this->pdf->getFontHeight(10);
		$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,783 - $font_height,10,date(DATE_FORMAT));

		$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->ez['bottomMargin'],$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->ez['bottomMargin']);
		$this->pdf->ezSetDy(-35);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		if (in_array("logo_page_only",$this->print_pref['gen_print_fields'])) {
			$headfoot_after_logo_odd = $this->pdf->openObject();
			$this->pdf->saveState();
			$this->pdf->selectFont('include/fonts/Helvetica.afm');
			$y_start = 825;

			//$y_start -= 25;
			$this->pdf->ezSetDy(-10);
			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(2,'round');
			$x_start = 40;
			//Customer
			$this->pdf->line($x_start,801.89,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],801.89);
			$this->pdf->addText($x_start,805.89,10,$this->template->current_template['customer']['value'].": ".stripslashes($p->current_proposal['customer']));
			//Sales Rep
			$this->pdf->addText($x_start,789.89,10,$this->template->current_template['sales_rep']['value'].": ".$author);
			//Pro Forma Invoice title
			$text_width = $this->pdf->getTextWidth(20,$title);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,804,20,$title);
			//Pro Forma Invoice number
			$text_width = $this->pdf->getTextWidth(15,$this->print_pref['invoice_no']);
			$font_height = $this->pdf->getFontHeight(15);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,801.89 - $font_height,15,$this->print_pref['invoice_no']);
			//Proposal date
			$text_width = $this->pdf->getTextWidth(10,date(DATE_FORMAT));
			$font_height = $this->pdf->getFontHeight(10);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,783 - $font_height,10,date(DATE_FORMAT));

			$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->ez['bottomMargin'],$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->ez['bottomMargin']);
			$this->pdf->ezSetDy(-35);
			$this->pdf->restoreState();
			$this->pdf->closeObject();

			$headfoot_after_logo_even = $this->pdf->openObject();
			$this->pdf->saveState();
			$this->pdf->selectFont('include/fonts/Helvetica.afm');
			$y_start = 825;

			//$y_start -= 25;
			$this->pdf->ezSetDy(-10);
			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(2,'round');
			$x_start = 40;
			//Customer
			$this->pdf->line($x_start,801.89,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],801.89);
			$this->pdf->addText($x_start,805.89,10,$this->template->current_template['customer']['value'].": ".stripslashes($p->current_proposal['customer']));
			//Sales Rep
			$this->pdf->addText($x_start,789.89,10,$this->template->current_template['sales_rep']['value'].": ".$author);
			//Proposal title
			$text_width = $this->pdf->getTextWidth(20,$title);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,804,20,$title);
			//Proposal number
			$text_width = $this->pdf->getTextWidth(15,$this->print_pref['invoice_no']);
			$font_height = $this->pdf->getFontHeight(15);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,801.89 - $font_height,15,$this->print_pref['invoice_no']);
			//Proposal date
			$text_width = $this->pdf->getTextWidth(10,date(DATE_FORMAT));
			$font_height = $this->pdf->getFontHeight(10);
			$this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin']-$text_width-10,783 - $font_height,10,date(DATE_FORMAT));

			$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->ez['bottomMargin'],$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->ez['bottomMargin']);
			$this->pdf->ezSetDy(-35);
			$this->pdf->restoreState();
			$this->pdf->closeObject();
		}

		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($headfoot,(in_array("logo_page_only",$this->print_pref['gen_print_fields']) ? 'add' : 'all'));
		if ($headfoot_after_logo_even) {
			$this->pdf->addObject($headfoot_after_logo_even,'nextodd');
			$this->pdf->addObject($headfoot_after_logo_odd,'nexteven');
		}
		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		if ($this->pdf->y > 770)
			$this->pdf->ezSetY(770);

		$this->pdf->ez['topMargin'] = 30 + $this->h + 25;

		//Show the customer, install, and ship to information
		if (in_array("customer",$this->print_pref['gen_print_fields']) && $customer_info) {
			$data_tmp['customer'] = $customer_info;
			$cols['customer'] = $this->template->current_template['customer']['value'].":";
			$header_count++;
		} if (in_array("ship_to",$this->print_pref['gen_print_fields']) && $ship_to_loc) {
			$data_tmp['ship_to'] = $ship_to_loc;
			$cols['ship_to'] = $this->template->current_template['ship_to']['value'].":";
			$header_count++;
		} if (in_array("install_addr",$this->print_pref['gen_print_fields']) && $install_loc) {
			$data_tmp['install_addr'] = $install_loc;
			$cols['install_addr'] = $this->template->current_template['install']['value'].":";
			$header_count++;
		}

		$xpos = 0;
		$this->pdf->ezSetY($this->pdf->ez['pageHeight']-$this->pdf->ez['topMargin']);
		if ($header_count > 0) {
			$data = array($data_tmp);
			$options = array('showLines' 	=> 0,
							 'shaded'		=> 0,
							 'maxWidth'		=> $this->pdf->ez['pageWidth'],
							 'xPos'			=> $xpos,
							 'colGap'		=> 15,
							 'fontSize'		=> 12,
							 'xOrientation'	=> 'right');

			$y = $this->pdf->ezTable($data,$cols,NULL,$options);
		}
		$this->pdf->setStrokeColor(0,0.2,0.4);
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'],$y - 10,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$y - 10);

		$this->pdf->ezSetDy(-15);

		if (in_array("invoice_descr",$this->print_pref['gen_print_fields'])) {
			$cols = array("invoice_descr" => "Invoice Description:");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> 30,
							 'showHeadings'		=> 0,
							 'fontSize'			=> 12,
							 'titleFontSize'	=> 14,
							 'xOrientation' 	=> 'right',
							 'width'			=> $adjWidth,
							 'maxWidth'			=> $adjWidth);
			$invoice_descr[] = array("invoice_descr" => ($this->print_pref['invoice_descr'] ? $this->print_pref['invoice_descr'] : $p->current_proposal['proposal_descr']));
			$y = $this->pdf->ezTable($invoice_descr,$cols,NULL,$options);
			$this->pdf->ezSetDy(-5);

			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(1);
			$this->pdf->line($this->pdf->ez['leftMargin'],$y - 10,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$y - 10);
			$this->pdf->ezSetDy(-15);
		}

		if (is_array($comment_data)) {
			$cols = array("comments" => "Comments:");
			$options = array('showLines' 		=> 0,
							 'shaded'			=> 0,
							 'xPos'				=> 30,
							 'showHeadings'		=> 0,
							 'fontSize'			=> $this->template->current_template['comments_data']['font_size'],
							 'titleFontSize'	=> $this->template->current_template['comments']['font_size'],
							 'xOrientation' 	=> 'right',
							 'width'			=> $adjWidth,
							 'maxWidth'			=> $adjWidth);
			$this->pdf->ezText($this->template->current_template['comments']['value'].':',$this->template->current_template['comments']['font_size']);
			$y = $this->pdf->ezTable($comment_data,$cols,'',$options);
			$this->pdf->ezSetDy(-10);
		}

		// Added for Trac #1058
		if (in_array("customer_po",$this->print_pref['gen_print_fields']) && $p->current_proposal['customer_po']) {
			$this->pdf->ezText("Customer PO".$this->template->current_template['customer_po']['value'].": ".$p->current_proposal['customer_po']);
			$this->pdf->ezSetDy(-5);
		}

		// Adjust top margin if logo is only printed on first page. Added for #566.
		if (in_array("logo_page_only",$this->print_pref['gen_print_fields']))  {
			$this->pdf->ez['topMargin'] = ($margins[0] ? $margins[0] + 50 : 80);
		}

		//Show the line items
		$item_options = array("width" 		=> $adjWidth,
							  "maxWidth"	=> $adjWidth,
							  'rowGap'		=>	4,
							  'showLines'	=>  2,
							  'lineCol'		=>	array(.8,.8,.8),
							  'shaded'		=>  0,
							  'shadeCol'	=>  array(.94,.94,.94),
							  "cols"		=>  $item_col_attr,

							  );
		$totals_options = array('xPos'					=>	$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - 10,
								'width'					=>	$adjWidth - 120,
								'xOrientation'			=>	'left',
								'showLines'				=>  0,
								'lineCol'				=>  array(.8,.8,.8),
								'showHeadings'			=>  0,
								'shaded'				=>	0,
								'fontSize'				=>	12,
								'rowGap'				=>	4,
								'colGap'				=>  0,
								'cols'					=>	array('field'	=>	array('justification'	=>	'left',
																					  'width'			=>	315),
																  'val'		=>	array('justification'	=>	'right',
																					  'width'			=>	100.28)
																  )
								);

		// Show line items if line items were originally selected
		if ($show_detail) {
			if (is_array($print_array)) {
				for ($i = 0; $i < count($print_array); $i++) {
					$this->pdf->ezText(($group_array[$i] ? $this->template->current_template['group']['value']." ".$group_array[$i]['title'].":" : NULL),12);
					$this->pdf->ezSetDy(-5);
					$y = $this->pdf->ezTable($print_array[$i],$item_cols,'',$item_options);

					if ($group_array[$i]) {
						if (in_array('group_totals',$this->print_pref['gen_print_fields'])) {
							$total_array = array(array('field'	=>	$this->template->current_template['group']['value'].' '.$group_array[$i]['title'].' '.$this->template->current_template['total']['value'].': '.$this->pdf->fill_void('.',(315 - $this->pdf->getTextWidth(12,$this->template->current_template['group']['value'].' '.$group_array[$i]['title'].' '.$this->template->current_template['total']['value'].': '))),
													   'val' 	=> 	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($group_array[$i]['total'],2)))).'$'.number_format($group_array[$i]['total'],2)
													   ));
							$y = $this->pdf->ezTable($total_array,NULL,NULL,$totals_options);
						}
						if (in_array("grp_break",$this->print_pref['gen_print_fields']))
							$this->pdf->ezNewPage();
						else
							$this->pdf->ezSetDy(-10);
					} else
						$this->pdf->ezSetDy(-5);
				}
			}
		}

		$this->pdf->ezSetDy(-15);

		if (in_array("panel_detail",$this->print_pref['gen_print_fields'])) {
			$import_data = $p->current_proposal['import_data'];
			$panel_info =& $import_data['PANEL_TYPE'];

			if ($panel_info) {
				$this->pdf->ezNewPage();
				for ($i = 0; $i < count($panel_info); $i++) {
					$detail =& $panel_info[$i];
					$p_detail = array();

					$p_detail[] = $this->template->current_template['panel_name']['value'].": ".stripslashes($detail['NAME'])."\n".$this->template->current_template['description']['value'].": ".stripslashes($detail['DESCR'])."\n".$this->template->current_template['panel_height']['value'].": ".$detail['HEIGHT']."\n".$this->template->current_template['total_height']['value'].": ".$detail['TOTAL_HEIGH']."\n".$this->template->current_template['width']['value'].": ".$detail['WIDTH'];
					$type_el =& $detail['ELEMENT'];

					for ($j = 0; $j < count($type_el); $j++) {
						$p_detail[] = "\n    ".$this->template->current_template['element']['value']." ".($j + 1).": ".$type_el[$j]['DESCR']." (".$type_el[$j]['PN'].")";

						$finish_grp = $type_el[$j]['FINISH'];
						for ($k = 0; $k < count($finish_grp); $k++) {
							if ($finish_grp[$k])
								$p_detail[] = "        ".$this->template->current_template['fabric_option']['value']." ".($k + 1).": ".$finish_grp[$k]['GROUP_DESCR']." - ".$finish_grp[$k]['DESCR'];
						}
					}



					$panel_detail[] = array('panel' => implode("\n",$p_detail));
				}
			}

			if ($panel_detail) {
				$cols = array("panel" => $this->template->current_template['panel_details']['value'].":");
				$options = array('showLines' 		=> 1,
								 'lineCol'			=> array(.8,.8,.8),
								 'shaded'			=> 0,
								 'xPos'				=> 30,
								 'showHeadings'		=> 0,
								 'titleFontSize'	=> 12,
				                 'rowGap'           => 4,
								 'xOrientation' 	=> 'right',
								 'width'			=> $adjWidth,
								 'maxWidth'			=> $adjWidth);
				$y = $this->pdf->ezTable($panel_detail,$cols,$this->template->current_template['panel_details']['value'].':',$options);
				$this->pdf->ezSetDy(-15);
			}
		}
		$totals_options['width'] = 215.28;
		$totals_options['cols']['field']['width'] = 115;

		// Only show group totals if showing line items
		if ($show_detail) {
			if ($group_array && in_array("grp_sum",$this->print_pref['gen_print_fields'])) {
				$y = $this->pdf->ezText($this->template->current_template['grouping_summary']['value'].":",13);
				$this->pdf->ezSetDy(-5);
				while (list($k,$grp) = each($group_array)) {
					if ($grp['total'] != 0 || in_array('zero_sell',$this->print_pref['item_print_fields'])) {
						$y = $this->pdf->ezText("- ".htmlspecialchars($grp['title'])." ".$this->template->current_template['totals']['value'].": $".number_format($grp['total'],2),11,array('left'   =>  10));
						$this->pdf->ezSetDy(-3);
					}
				}

				$this->pdf->ezSetDy(-15);
			}
		}

		// Print invoice message if exists
		if ($this->print_pref['invoice_msg']) {
			$this->pdf->ezText($this->print_pref['invoice_msg'],12);
			$this->pdf->ezSetDy(-10);
		}

		// Calculate total sell of proposal if no line items were selected
		if (count($proforma_items) == 0  && in_array("item_totals",$this->print_pref['gen_print_fields'])) {
			// Fetch only active line items
			$line_items = $lines->fetch_line_items_short(1);
			$total_sell = 0;
			foreach ($line_items as $line) {
				$total_sell += $line['ext_sell'];
			}
		}
		if (in_array("item_totals",$this->print_pref['gen_print_fields'])) {
			$totals_data[] = array('field'  	=>	$this->template->current_template['subtotal']['value'].$this->pdf->fill_void('.',(115 - $this->pdf->getTextWidth(12,$this->template->current_template['subtotal']['value']))),
								   'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($total_sell,2)))).'$'.number_format($total_sell,2));
		}
		if (in_array("tax",$this->print_pref['gen_print_fields']) && $total_tax > 0) {
			if ($lines->tax_country == 'CA' && $indiv_tax) {
                while (list($tax_hash,$local_name) = each($tax_local))
                    $totals_data[] = array('field'  =>  $this->template->current_template['tax']['value'].' - '.strtoupper($local_name).$this->pdf->fill_void('.',(115 - $this->pdf->getTextWidth(12,$this->template->current_template['tax']['value'].' - '.strtoupper($local_name)))),
                                           'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($tax_rules[$tax_hash],2)))).'$'.number_format($tax_rules[$tax_hash],2));
			} else
				$totals_data[] = array('field'  =>	$this->template->current_template['tax']['value'].$this->pdf->fill_void('.',(115 - $this->pdf->getTextWidth(12,$this->template->current_template['tax']['value']))),
									   'val'	=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format($total_tax,2)))).'$'.number_format($total_tax,2));
			$totals_content = 1;
		}
		if (in_array("item_totals",$this->print_pref['gen_print_fields'])) {
			$totals_data[] = array('field'  	=>	$this->template->current_template['total_amount']['value'].$this->pdf->fill_void('.',(115 - $this->pdf->getTextWidth(12,$this->template->current_template['total_amount']['value']))),
								   'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'$'.number_format(round($total_sell + $total_tax,2),2)))).'$'.number_format(round($total_sell + $total_tax,2),2));
			$totals_content = 1;
		}
		$r = $this->db->query("SELECT SUM(customer_invoice.amount) AS deposit_total
		                       FROM customer_invoice
		                       WHERE customer_invoice.proposal_hash = '".$lines->proposal_hash."' AND customer_invoice.type = 'D' AND deleted = 0");
		$deposit_total = $this->db->result($r,0,'deposit_total');
	    if ($deposit_total > 0)
            $totals_data[] = array('field'  =>  "Deposit Received".$this->pdf->fill_void('.',(115 - $this->pdf->getTextWidth(12,"Deposit Received"))),
                                   'val'    =>  $this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,'($'.number_format($deposit_total,2).")"))).'($'.number_format($deposit_total,2).")");

		if (in_array("total_due",$this->print_pref['gen_print_fields'])) {
			if ($this->print_pref['deposit_amount'])
				$total_due = '$'.number_format($this->print_pref['deposit_amount'],2);
			else
				$total_due = '$'.number_format(round(($total_sell + $total_tax - $deposit_total) * ($this->print_pref['percent'] * .01),2),2);

			$totals_data[] = array('field'  	=>	$this->template->current_template['total_due']['value'].$this->pdf->fill_void('.',(115 - $this->pdf->getTextWidth(12,$this->template->current_template['total_due']['value']))),
								   'val'		=>	$this->pdf->fill_void('.',(100.28 - $this->pdf->getTextWidth(12,$total_due))).$total_due);
			$totals_content = 1;
		}

		$footer_msg = $this->print_pref['footer_msg'];

		// Find out if the footer message is very long, or has multiple new lines that would cause it to push to the next page.
		$is_long_footer = ((strlen($footer_msg) > 315) || substr_count($footer_msg,"\n") >= 2);

		// Modified remittance address and totals data for Trac #1313
		if ($totals_content || $this->print_pref['remit_to_addr']) {
			// Calculate if extra adjustments need to be factored in for long totals or remittance address. For #1305
			$remit_to_ex = explode("\n",$this->print_pref['remit_to_addr']);
			if (count($remit_to_ex) > 4)
				$adj_down1 = ((count($remit_to_ex)) - 4) * 25;
			if (count($totals_data) > 4)
				$adj_down2 = ((count($totals_data)) - 4) * 30;
			$adj_down = ($adj_down1 > $adj_down2 ? $adj_down1 : $adj_down2);

			// Push pricing totals to next page if near bottom
			if ($this->pdf->y < ($is_long_footer ? (132 + $adj_down) : (112 + $adj_down))) {
				$this->pdf->ezNewPage();
				$new_page = true;
				$this->pdf->ezSetDy(-20);
			}

			// Display remit to address if it exists.  Added for trac ticket #307
	        if ($this->print_pref['remit_to_addr']) {
	            $this->pdf->addText($this->pdf->ez['leftMargin'],($this->pdf->y - 10),$this->template->current_template['please_remit']['font_size'],($this->template->current_template['please_remit']['value'] ? "<b>" : NULL).$this->template->current_template['please_remit']['value'].": ".($this->template->current_template['please_remit']['value'] ? "</b>" : NULL));
	            $y_pos = ($this->pdf->y - 15);
	            for ($i = 0; $i < count($remit_to_ex); $i++) {
	                $y_pos -= 13;
	            	$this->pdf->addText($this->pdf->ez['leftMargin'] + 10,$y_pos,$this->template->current_template['please_remit_data']['font_size'],$remit_to_ex[$i]);
	            }
	        }
			if ($totals_content)
				$y = $this->pdf->ezTable($totals_data,NULL,NULL,$totals_options);
		}

		$footer_msg = $this->print_pref['footer_msg'];
		$this->pdf->ezSetY($is_long_footer ? 102 : 72);

		// Print footer message
		if ($footer_msg)
			$y = $this->pdf->ezText(stripslashes($footer_msg));

		//  Add proforma invoice to file vault
		if ($this->print_pref['save_doc']) {
			$pdf = $this->pdf->output();
			$content_type = 'application/pdf';
			$doc_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			while (global_classes::key_exists('doc_vault','doc_hash',$doc_hash))
				$doc_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			$this->db->query("INSERT INTO `doc_vault`
						  (`timestamp` , `last_change` , `doc_hash` , `proposal_hash` , `file_name` , `file_type` , `content_type` , `descr` , `filesize` , `data`)
							VALUES (".time()." , '".$this->current_hash."' , '$doc_hash' , '".$p->current_proposal['proposal_hash']."' , 'proforma_".$this->print_pref['invoice_no'].".pdf' , 'PDF' , '$content_type' , '".$this->print_pref['title']." (".$this->print_pref['invoice_no'].") generated on ".date(DATE_FORMAT." ".TIMESTAMP_FORMAT).". This ".$this->print_pref['title']." document was automatically placed in the file vault.' , '".strlen($pdf)."' , '".addslashes($pdf)."')");
		}
	}

/**
 * Generates a pdf document of the income statement given the report_hash.
 *
 * @param String $report_hash
 */
    function income_statement($report_hash) {
        $reports = new reports($this->current_hash);
        $reports->fetch_report_settings($report_hash);
        $_POST['report_hash'] = $report_hash;
        $_POST['doit_print'] = 1;

        // Populate data for reports object.
        $reports->doit_income_statement($report_hash);

        // Start the PDF document ( A4 size x:595.28 y:841.89 )
        $this->pdf = new Cezpdf();

        $title = MY_COMPANY_NAME." Income Statement";
        $creation_date = date(TIMESTAMP_FORMAT,time());

        $this->pdf->addInfo('Title',$title);
        $this->pdf->addInfo('CreationDate',$creation_date);

        // Print header
        $headerObj = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');
        $this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
        $this->pdf->ezText("Income Statement",16,array('justification' => 'center'));
        $this->pdf->ezText(($reports->report_date_start ? date(DATE_FORMAT,strtotime($reports->report_date_start))." - " : "Through ").date(DATE_FORMAT,strtotime($reports->report_date_end)),12,array('justification' => 'center'));
        $this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
        $this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));
        $this->pdf->setStrokeColor(0,0,0);
        $this->pdf->setLineStyle(1);
        $this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

        $y_head = $this->pdf->y;
        $this->pdf->restoreState();
        $this->pdf->closeObject();
        $this->pdf->addObject($headerObj,'all');

        $this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

        // Print footer
        $bottomFoot = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        $this->pdf->setStrokeColor(.8,.8,.8);
        $this->pdf->setLineStyle(1);
        $this->pdf->line(30,30,565.28,30);
        $this->pdf->restoreState();
        $this->pdf->closeObject();

        $this->pdf->addObject($bottomFoot,'all');
        $this->pdf->ezStartPageNumbers(550,22,6);
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        // Calculate x coordinate for each column
        $start = $this->pdf->ez['leftMargin']; // X coordinate of first column
        $space = 400;  // Space between columns
        $space2 = 100;
        $col1 = $start  + 15;
        $col2 = $start + $space;
        $child_adj = 15;
        $col_adj = 36;

        // Set font sizes for each element in printed report
        $font_size_h1 = 12; // Sales Rep Name
        $font_size_h2 = 10; // Company Name
        $font_size_h3 = 10; // Proposal Description

        $accounts =& $reports->accounts;
        if (is_array($accounts)) {
            // Income
            $in =& $accounts['IN'];
            reset($in);
            $total_in = 0;
            $this->pdf->addText($start, $this->pdf->y,$font_size_h2,"<b>Income</b>");
            $this->pdf->ezSetDy(-20);
            while (list($account_hash,$info) = each($in)) {
            	if ($info['total'] != 0 || !$reports->current_report['hide_empty'] || $info['placeholder'] == 1 || $reports->child[$account_hash]) {
					$total_in += $info['total'];
					$this->pdf->addText($col1, $this->pdf->y,$font_size_h2,($info['account_no'] ? $info['account_no']." - " : NULL).$info['account_name']);
					$this->pdf->addTextWrap($col2, $this->pdf->y,$space2,$font_size_h2,$this->format_dollars_show_zero($info['total']),'right');
					$this->pdf->ezSetDy(-20);
					if ($reports->child[$account_hash]) {
						$child_total = $info['total'];
						reset($reports->child[$account_hash]);
						while(list($child_hash,$child_info) = each($reports->child[$account_hash])) {
                            if ($child_info['total'] != 0 || !$reports->current_report['hide_empty']) {
								$total_in += $child_info['total'];
								$child_total += $child_info['total'];
								$this->pdf->addText($col1 + $child_adj, $this->pdf->y,$font_size_h2,($child_info['account_no'] ? $child_info['account_no']." - " : NULL).$child_info['account_name']);
								$this->pdf->addTextWrap($col2, $this->pdf->y,$space2,$font_size_h2,$this->format_dollars_show_zero($child_info['total']),'right');
								$this->pdf->ezSetDy(-20);
                            }
						}
						$this->pdf->setStrokeColor(.7,.7,.7);
						$this->pdf->setLineStyle(1);
						$this->pdf->line($col2 + $col_adj,$this->pdf->y + 12,$col2 + $space2,$this->pdf->y + 12);
						$this->pdf->addText($col1,$this->pdf->y,$font_size_h2,"Total ".$info['account_name']);
						$this->pdf->addTextWrap($col2,$this->pdf->y,$space2, $font_size_h2,$this->format_dollars_show_zero($child_total),'right');
						$this->pdf->ezSetDy(-25);
					}
            	}

            }
            //  Total Income
            $this->pdf->setColor(.7,.7,.7);
            $this->pdf->filledRectangle($start,$this->pdf->y - 3,$space + $space2,15);
            $this->pdf->setColor(0,0,0);
            $this->pdf->addText($col1,$this->pdf->y,$font_size_h2,"<b>Total Income</b>");
            $this->pdf->addTextWrap($col2,$this->pdf->y,$space2,$font_size_h2,"<b>".$this->format_dollars_show_zero($total_in)."</b>",'right');
            $this->pdf->ezSetDy(-25);

            // Cost of Goods Sold
            $cg =& $accounts['CG'];
            reset($cg);
            $total_cg = 0;
            $this->pdf->addText($start, $this->pdf->y,$font_size_h2,"<b>Cost of Goods Sold</b>");
            $this->pdf->ezSetDy(-20);
            while (list($account_hash,$info) = each($cg)) {
                if ($info['total'] != 0 || !$reports->current_report['hide_empty'] || $info['placeholder'] == 1 || $reports->child[$account_hash]) {
                    $total_cg += $info['total'];
                    $this->pdf->addText($col1, $this->pdf->y,$font_size_h2,($info['account_no'] ? $info['account_no']." - " : NULL).$info['account_name']);
                    $this->pdf->addTextWrap($col2, $this->pdf->y,$space2,$font_size_h2,$this->format_dollars_show_zero($info['total']),'right');
                    $this->pdf->ezSetDy(-20);
                    if ($reports->child[$account_hash]) {
                        $child_total = $info['total'];
                        reset($reports->child[$account_hash]);
                        while(list($child_hash,$child_info) = each($reports->child[$account_hash])) {
                            if ($child_info['total'] != 0 || !$reports->current_report['hide_empty']) {
                                $total_cg += $child_info['total'];
                                $child_total += $child_info['total'];
                                $this->pdf->addText($col1 + $child_adj, $this->pdf->y,$font_size_h2,($child_info['account_no'] ? $child_info['account_no']." - " : NULL).$child_info['account_name']);
                                $this->pdf->addTextWrap($col2, $this->pdf->y,$space2,$font_size_h2,$this->format_dollars_show_zero($child_info['total']),'right');
                                $this->pdf->ezSetDy(-20);
                            }
                        }
                        $this->pdf->setStrokeColor(.7,.7,.7);
                        $this->pdf->setLineStyle(1);
                        $this->pdf->line($col2 + $col_adj,$this->pdf->y + 12,$col2 + $space2,$this->pdf->y + 12);
                        $this->pdf->addText($col1,$this->pdf->y,$font_size_h2,"Total ".$info['account_name']);
                        $this->pdf->addTextWrap($col2,$this->pdf->y,$space2,$font_size_h2,$this->format_dollars_show_zero($child_total),'right');
                        $this->pdf->ezSetDy(-25);
                    }
                }
            }

            // Total COGS
            $this->pdf->setColor(.7,.7,.7);
            $this->pdf->filledRectangle($start,$this->pdf->y - 3,$space + $space2,15);
            $this->pdf->setColor(0,0,0);
            $this->pdf->addText($col1,$this->pdf->y,$font_size_h2,"<b>Total Cost of Goods Sold</b>");
            $this->pdf->addTextWrap($col2,$this->pdf->y,$space2,$font_size_h2,"<b>".$this->format_dollars_show_zero($total_cg)."</b>",'right');
            $this->pdf->ezSetDy(-30);

            // Gross Profit
            $this->pdf->addText($start,$this->pdf->y,$font_size_h1,"<b>Gross Profit</b>");
            $this->pdf->addTextWrap($col2,$this->pdf->y,$space2,$font_size_h1,"<b>".$this->format_dollars_show_zero($total_in - $total_cg)."</b>",'right');
            $this->pdf->ezSetDy(-30);

            // Expenses
            $ex =& $accounts['EX'];
            reset($ex);
            $total_ex = 0;
            $this->pdf->addText($start, $this->pdf->y,$font_size_h2,"<b>Expenses</b>");
            $this->pdf->ezSetDy(-20);
            while (list($account_hash,$info) = each($ex)) {
            	if ($info['total'] != 0 || !$reports->current_report['hide_empty'] || $info['placeholder'] == 1 || $reports->child[$account_hash]) {
					$total_ex += $info['total'];
					$this->pdf->addText($col1, $this->pdf->y,$font_size_h2,($info['account_no'] ? $info['account_no']." - " : NULL).$info['account_name']);
					$this->pdf->addTextWrap($col2, $this->pdf->y,$space2,$font_size_h2,$this->format_dollars_show_zero($info['total']),'right');
					$this->pdf->ezSetDy(-20);
					if ($reports->child[$account_hash]) {
						$child_total = $info['total'];
						reset($reports->child[$account_hash]);
						while(list($child_hash,$child_info) = each($reports->child[$account_hash])) {
							if ($child_info['total'] != 0 || !$reports->current_report['hide_empty']) {
								$total_ex += $child_info['total'];
								$child_total += $child_info['total'];
								$this->pdf->addText($col1 + $child_adj, $this->pdf->y,$font_size_h2,($child_info['account_no'] ? $child_info['account_no']." - " : NULL).$child_info['account_name']);
								$this->pdf->addTextWrap($col2, $this->pdf->y,$space2,$font_size_h2,$this->format_dollars_show_zero($child_info['total']),'right');
								$this->pdf->ezSetDy(-20);
							}
						}
						$this->pdf->setStrokeColor(.7,.7,.7);
						$this->pdf->setLineStyle(1);
						$this->pdf->line($col2 + $col_adj,$this->pdf->y + 12,$col2 + $space2,$this->pdf->y + 12);
						$this->pdf->addText($col1,$this->pdf->y,$font_size_h2,"Total ".$info['account_name']);
						$this->pdf->addTextWrap($col2,$this->pdf->y,$space2,$font_size_h2,$this->format_dollars_show_zero($child_total),'right');
						$this->pdf->ezSetDy(-25);
					}
            	}
            }
            // Total Expenses
            $this->pdf->setColor(.7,.7,.7);
            $this->pdf->filledRectangle($start,$this->pdf->y - 3,$space + $space2,15);
            $this->pdf->setColor(0,0,0);
            $this->pdf->addText($col1,$this->pdf->y,$font_size_h2,"<b>Total Expenses</b>");
            $this->pdf->addTextWrap($col2,$this->pdf->y,$space2,$font_size_h2,"<b>".$this->format_dollars_show_zero($total_ex)."</b>",'right');
            $this->pdf->ezSetDy(-30);

            $total_ex += $total_cg;

            // Net Income
            $this->pdf->addText($start,$this->pdf->y,$font_size_h1,"<b>Net Income</b>");
            $this->pdf->addTextWrap($col2,$this->pdf->y,$space2,$font_size_h1,"<b>".$this->format_dollars_show_zero($total_in - $total_ex)."</b>",'right');
        }
    }

    function vendor_1099IRS($file_name) {

        //Get the URL passed variables
        $f1 = urldecode($_GET['f1']);
        $f2 = urldecode($_GET['f2']);
        $f3 = urldecode($_GET['f3']);
        $f4 = urldecode($_GET['f4']);
        $f5 = urldecode($_GET['f5']);
        $f6 = urldecode($_GET['f6']);
        $f7 = urldecode($_GET['f7']);
        $f8 = urldecode($_GET['f8']);
        $f9 = urldecode($_GET['f9']);
        $f10 = urldecode($_GET['f10']);
        $f13 = urldecode($_GET['f13']);
        $f14 = urldecode($_GET['f14']);
        $f15a = urldecode($_GET['f15a']);
        $f15b = urldecode($_GET['f15b']);
        $f16 = urldecode($_GET['f16']);
        $f17 = urldecode($_GET['f17']);
        $f18 = urldecode($_GET['f18']);
        $tax_id = urldecode($_GET['tax_id']);
        $vendor_hash = $_GET['vendor_hash'];
    	$vendor_acctno = urldecode($_GET['vendor_acctno']);
    	$vendor_tinNot = $_GET['vendor_tinNot'];
    	$f_void = $_GET['f_void'];
    	$f_corrected = $_GET['f_corrected'];
    	$year = $_GET['year'];

    	$vendors = new vendors();
    	$vendors->fetch_master_record($vendor_hash);

    	$recipient = array();
    	$recipient['name'] = $vendors->current_vendor['vendor_name'];
    	$file_name = preg_replace('/[^A-Za-z0-9]/', "", $recipient['name']);
    	if (strlen($file_name) > 12)
            $file_name = substr($file_name,0,12);

        $file_name = strtolower($file_name)."_f1099-misc.pdf";

        if ( $vendors->current_vendor['street'] ) {

        	$_address = explode("\n", stripslashes($vendors->current_vendor['street']) );
        	if ( count($_address) > 2 ) {

        		$recipient['address'] = "{$_address[0]}\n{$_address[1]}";
        		for ( $i = 2; $i <= count($_address); $i++ )
        			$recipient['address'] .= " {$_address[$i]}";

        	} else
            	$recipient['address'] = stripslashes($vendors->current_vendor['street']);
        }


    	$recipient['city_state_zip'] = stripslashes($vendors->current_vendor['city']) . ", {$vendors->current_vendor['state']} {$vendors->current_vendor['zip']}";
    	$recipient['tax_id'] = $vendors->current_vendor['tax_id'];

        $payer = array();
        $payer['name'] = (defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : '');
        $payer['address'] = (defined('MY_COMPANY_ADDRESS') ? MY_COMPANY_ADDRESS : '');

        if (defined('MY_COMPANY_ZIP') && !ereg(MY_COMPANY_ZIP,$payer['address']))
            $payer['address'] .= " ".MY_COMPANY_ZIP;

        $payer['phone'] = (defined('MY_COMPANY_PHONE') ? MY_COMPANY_PHONE : '');

        $pdf = new FPDI();

        $template_pages = array(1,2,3);

        for ($i = 0; $i < 3; $i++) {
		        $pdf->AddPage();
				// Use a hard coded templates directory for now because only the 2011 form works  
				// $template_file = realpath( TEMPLATE_DIR . "pdf/f1099msc_{$year}.pdf" );
		        $template_file = realpath( "/var/www/html/templates.dealer-choice.com/pdf/f1099msc_11.pdf" );
		        if ( ! $template_file || ! file_exists( $template_file ) ) {
	
	                print "PDF Error. Can't load PDF template for report printing";
	                exit;
		        }
	
		        $pdf->setSourceFile( $template_file );
	
		        // import page 1
		        $tplIdx = $pdf->importPage($template_pages[$i],'/MediaBox');
	
		        $page_size = $pdf->getTemplateSize($tplIdx);
	
		        // use the imported page and place it at point 10,10 with a width of 100 mm
		        $pdf->useTemplate($tplIdx,0, 0, $page_size['w'], $page_size['h']);
	
	
	
		        // now write some text above the imported page
		        $pdf->SetFont('Arial');
		        $pdf->SetFontSize('10');
		        $pdf->SetTextColor(0,0,0);
	
		        if ($i != 2 && $i != 3) {
			        $pdf->SetXY(60.0,11.0);
			        $pdf->Write(0,($f_void ? 'X' : NULL));
		        }
	            $pdf->SetXY(80.0,11.0);
	            $pdf->Write(0,($f_corrected ? 'X' : NULL));
	
		        $pdf->SetLeftMargin(18);
	
		        $pdf->SetY(26);
		        $pdf->Write(0,$payer['name']);
		        $pdf->Ln(5);
		        $pdf->Write(4,$payer['address']);
		        $pdf->Ln(6);
		        $pdf->Write(4,$payer['phone']);
	
		        $pdf->SetY(61);
		        $pdf->Write(0,$tax_id);
	
		        $pdf->SetX(61);
		        $pdf->Write(0,$recipient['tax_id']);
	
		        $pdf->SetXY(16,72);
		        $pdf->Write(0,$recipient['name']);
	
		        $pdf->SetXY(16,84);
		        $pdf->Write(4,$recipient['address']);
	
		        $pdf->SetXY(16,97);
		        $pdf->Write(0,$recipient['city_state_zip']);
	
		        $pdf->SetXY(15,110);
		        $pdf->Write(0,$vendor_acctno);
	
		        $pdf->SetXY(88.5,109.5);
		        $pdf->Write(0,($vendor_tinNot ? 'X' : NULL));
	
		        $pdf->SetXY(14,125.0);
		        $pdf->Write(0,$f15a);
	
		        $pdf->SetXY(57,125.0);
		        $pdf->Write(0,$f15b);
	
		        $pdf->SetXY(100,23.5);
		        $pdf->Write(0,$f1);
		        $pdf->SetXY(100,36.5);
		        $pdf->Write(0,$f2);
		        $pdf->SetXY(100,45.0);
		        $pdf->Write(0,$f3);
		        $pdf->SetXY(100,61.5);
		        $pdf->Write(0,$f5);
		        $pdf->SetXY(100,79);
		        $pdf->Write(0,$f7);
		        $pdf->SetXY(129.0,91.5);
		        $pdf->Write(0,($f9 ? 'X' : NULL));
		        $pdf->SetXY(100,113.0);
		        $pdf->Write(0,$f13);
		        $pdf->SetXY(100,121.5);
		        $pdf->Write(0,$f16);
	
		        $pdf->SetXY(136,45);
		        $pdf->Write(0,$f4);
		        $pdf->SetXY(136,62.0);
		        $pdf->Write(0,$f6);
		        $pdf->SetXY(136,79.0);
		        $pdf->Write(0,$f8);
		        $pdf->SetXY(136,91.5);
		        $pdf->Write(0,$f10);
		        $pdf->SetXY(136,112.5);
		        $pdf->Write(0,$f14);
		        $pdf->SetXY(136,121);
		        $pdf->Write(0,$f17);
	
		        $pdf->SetXY(172,121.5);
		        $pdf->Write(0,$f18);
	
		        $pdf->endTemplate($tplIdx);
        }

        $pdf->Output($file_name, 'I');
		exit;
    }

    function vendor_1099_std($report_hash) {
        $reports = new reports($this->current_hash);

        $_POST['report_hash'] = $report_hash;
        $_POST['doit_print'] = 1;

        // Populate data for reports object.
        $reports->doit_vendor_1099();

        // Start the PDF document ( A4 size x:595.28 y:841.89 )
        $this->pdf = new Cezpdf();

        $title = MY_COMPANY_NAME." Vendor 1099 Report";
        $creation_date = date(TIMESTAMP_FORMAT,time());

        $this->pdf->addInfo('Title',$title);
        $this->pdf->addInfo('CreationDate',$creation_date);

        // Print header
        $headerObj = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');
        $this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
        $this->pdf->ezText("Vendor 1099 Report",16,array('justification' => 'center'));
        $this->pdf->ezText($reports->report_title,12,array('justification' => 'center', 'leading' => 15));
        $this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
        $this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));

        $y_head = $this->pdf->y;
        $this->pdf->restoreState();
        $this->pdf->closeObject();
        $this->pdf->addObject($headerObj,'all');

        $this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

        // Print footer
        $bottomFoot = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        $this->pdf->setStrokeColor(.8,.8,.8);
        $this->pdf->setLineStyle(1);
        $this->pdf->line(30,30,565.28,30);
        $this->pdf->restoreState();
        $this->pdf->closeObject();

        $this->pdf->addObject($bottomFoot,'all');
        $this->pdf->ezStartPageNumbers(550,22,6);
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        $options_details =
                        array('fontSize'    => 7,
                         'width'            => 535.28,
                         'xPos'             => 60,
                         'xOrientation'     => 'right',
                         'showLines'        =>  0,
                         'lineCol'          =>  array(.8,.8,.8),
                         'shaded'           =>  0,
                         'showHeadings'     =>  0,
                         'rowGap'           =>  5,
                         'colGap'           =>  5,
                         'titleFontSize'    =>  10,
                         'shadeCol'         =>  array(.94,.94,.94),
                         'cols'             =>  $options_columns
                        );


        $vendors = new vendors($this->current_hash);

        $cols = array("vendor"          =>    "Vendor",
                      "tax_id"          =>    "Tax ID",
                      "payment_amount"  =>    "Payment Amount");

        $options_details = array('fontSize'         =>  9,
                                 'width'            =>  550,
		                         'showLines'        =>  2,
		                         'lineCol'          =>  array(.8,.8,.8),
		                         'shaded'           =>  0,
		                         'showHeadings'     =>  1,
		                         'rowGap'           =>  5,
		                         'colGap'           =>  5,
		                         'titleFontSize'    =>  12,
		                         'shadeCol'         =>  array(.94,.94,.94),
		                         'cols'             =>  array ("vendor"            => array ('justification' => 'left'),
								                               "tax_id"            => array ('justification' => 'left'),
								                               "payment_amount"    => array ('justification' => 'right')));


        if (is_array($reports->results)) {
            for ($i = 0; $i < count($reports->results); $i++) {
            	$vendor = stripslashes($reports->results[$i]['vendor_name']) . " - " .
            	( $reports->results[$i]['street'] ?
                    stripslashes($reports->results[$i]['street']) : NULL
                ) .
                stripslashes($reports->results[$i]['city']) . ", {$reports->results[$i]['state']} {$reports->results[$i]['zip']}";

                $item_data[] = array("vendor"           => $vendor,
                                     "tax_id"           => $reports->results[$i]['tax_id'],
                                     "payment_amount"   => '$'.number_format($reports->results[$i]['total_payment'],2));
            }
            $this->pdf->ezTable($item_data,$cols,NULL,$options_details);
        }

    }
/**
 * Generates a pdf document of a check run report given the report_hash.
 *
 * @param String $report_hash
 */
	function check_run($report_hash) {
		$reports = new reports($this->current_hash);
		$reports->fetch_report_settings($report_hash);

		$width1 = 70;
		$width2 = 90;
		$col1 = 45;
		$col2 = 120;
		$col3 = 150;
		$col4 = 195;
		$col5 = 270;
		$col6 = 335;
		$col7 = 400;
		$col8 = 475;
		$col9 = 500;
		$font_size = 7;
		$font_size2 = 8;
		$right_adj = 10;

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();

		$title = MY_COMPANY_NAME." Check Run Report ";
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('CreationDate',$creation_date);

		// Print header
		$headerObj = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');
		$this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
		$this->pdf->ezText("Check Run Report",16,array('justification' => 'center'));
		$this->pdf->ezText($reports->current_report['report_title'],12,array('justification' => 'center', 'leading' => 15));
		$this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']." on ".date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT))." at ".date(TIMESTAMP_FORMAT));
		$this->pdf->ezSetDy(-15);
		$this->pdf->addTextWrap($col1,$this->pdf->y,$width2,$font_size,"<b>Date</b>");
		$this->pdf->addTextWrap($col2,$this->pdf->y,$width1,$font_size,"<b>Type</b>");
		$this->pdf->addTextWrap($col3,$this->pdf->y,$width2,$font_size,"<b>Reference</b>");
		$this->pdf->addTextWrap($col4 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>Amount</b>",'right');
		$this->pdf->addTextWrap($col5 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>Discounts</b>",'right');
		$this->pdf->addTextWrap($col6 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>Deposits</b>",'right');
		$this->pdf->addTextWrap($col7 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>Credits</b>",'right');
		$this->pdf->addTextWrap($col8,$this->pdf->y,$width1,$font_size,"<b>Credit No.</b>");
		$this->pdf->addTextWrap($col9 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>Payment</b>",'right');
		$this->pdf->setLineStyle(1);
		$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['leftMargin'] + 5,$this->pdf->y - 5);

		$y_head = $this->pdf->y;
		$this->pdf->restoreState();
		$this->pdf->closeObject();
		$this->pdf->addObject($headerObj,'all');

		$this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

		// Print footer
		$bottomFoot = $this->pdf->openObject();
		$this->pdf->saveState();
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->pdf->setStrokeColor(.8,.8,.8);
		$this->pdf->setLineStyle(1);
		$this->pdf->line(30,30,565.28,30);
		$this->pdf->restoreState();
		$this->pdf->closeObject();

		$this->pdf->addObject($bottomFoot,'all');
		$this->pdf->ezStartPageNumbers(550,22,6);
		$this->pdf->selectFont('include/fonts/Helvetica.afm');

		$this->db->query("CALL check_run('{$reports->current_report['account_hash']}','{$reports->current_report['date_start']}','{$reports->current_report['date_end']}');");
		$sql = stripslashes($reports->current_report['sql']);
		$r = $this->db->query("SELECT * FROM _tmp_reports_data_check_run".($sql ? "
							   WHERE $sql" : NULL)."
							   GROUP BY check_no");

		if ($this->db->num_rows($r) > 0) {
			while($row = $this->db->fetch_assoc($r)) {
				$r2 = $this->db->query("SELECT * FROM _tmp_reports_data_check_run
										WHERE check_no = '".$row['check_no']."' AND invoice_no IS NOT NULL");
				$this->pdf->addTextWrap(30,$this->pdf->y,$width1,$font_size2,$row['check_no']);
				$this->pdf->addTextWrap($col2,$this->pdf->y,$width1,$font_size2,date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($row['check_date'])));
				$this->pdf->addTextWrap($col4,$this->pdf->y,200,$font_size2,stripslashes($row['payee_name']));
				$this->pdf->addTextWrap($col9,$this->pdf->y,$width1,$font_size2,$row['check_symbol'].number_format($row['check_amount'],2),'right');
				if ($this->db->num_rows($r) > 0)
					$this->pdf->ezSetDy(-10);

				while($invoice = $this->db->fetch_assoc($r2)) {
					$this->pdf->addTextWrap($col1,$this->pdf->y,$width2,$font_size,date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($invoice['invoice_date'])));
					$this->pdf->addTextWrap($col2,$this->pdf->y,$width1,$font_size,$invoice['invoice_type']);
					$this->pdf->addTextWrap($col3,$this->pdf->y,$width2,$font_size,$invoice['invoice_no']);
					$this->pdf->addTextWrap($col4,$this->pdf->y,$width1,$font_size,($invoice['invoice_amount'] > 0 ? $invoice['invoice_symbol'].number_format($invoice['invoice_amount'],2) : NULL),'right');
					$this->pdf->addTextWrap($col5,$this->pdf->y,$width1,$font_size,($invoice['discount_amount'] > 0 ? $invoice['payment_symbol'].number_format($invoice['discount_amount'],2) : NULL),'right');
					$this->pdf->addTextWrap($col6,$this->pdf->y,$width1,$font_size,($invoice['deposit_amount'] > 0 ? $invoice['payment_symbol'].number_format($invoice['deposit_amount'],2) : NULL),'right');
					$this->pdf->addTextWrap($col7 - $right_adj,$this->pdf->y,$width1,$font_size,($invoice['credit_applied'] > 0 ? $invoice['credit_symbol'].number_format($invoice['credit_applied'],2) : NULL),'right');
					$this->pdf->addTextWrap($col8,$this->pdf->y,$width2,$font_size,$invoice['credit_no']);
					$this->pdf->ezSetDy(-10);
                }
                $this->pdf->ezSetDy(-15);
			}
		}
	}
	
	function fetch_product_record($product_hash) {
		$result = $this->db->query("SELECT *
				FROM `vendor_products`
				WHERE `product_hash` = '$product_hash'");
		$row = $this->db->fetch_array($result);
		return $row;
	}

    function wip_report($report_hash) {
        $reports = new reports($this->current_hash);

        $_POST['report_hash'] = $report_hash;
        $_POST['doit_print'] = 1;
        $reports->doit_wip_report(1,'PDF');

        $showdetail = $reports->current_report['showdetail'];
        if ($reports->current_report['proposal_filter']) {
            $proposal_filter = $reports->current_report['proposal_filter'];
            if (!is_array($proposal_filter))
                $proposal_filter = array($proposal_filter);

            $proposal_filter = array_unique($proposal_filter);
            $proposal_filter = array_values($proposal_filter);
            array_walk($proposal_filter,'add_quotes',"'");
        }

        // Start the PDF document ( A4 size x:595.28 y:841.89 )
        $this->pdf = new Cezpdf();

        $title = MY_COMPANY_NAME." WIP Detail Report";
        $creation_date = date(TIMESTAMP_FORMAT,time());

        $this->pdf->addInfo('Title',$title);
        $this->pdf->addInfo('CreationDate',$creation_date);

        // Print header
        $headerObj = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');
        $this->pdf->ezText((defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL),16,array('justification' => 'center'));
        $this->pdf->ezText("WIP Detail Report",16,array('justification' => 'center'));
        $this->pdf->ezText($reports->report_title,12,array('justification' => 'center', 'leading' => 15));
        $this->pdf->addText(20,$this->pdf->y + 60, 8, "Printed by ".$_SESSION['my_name']);
        $this->pdf->addText(470, $this->pdf->y + 60, 8, "Printed on ".date(DATE_FORMAT)." at ".date(TIMESTAMP_FORMAT));

        $y_head = $this->pdf->y;
        $this->pdf->restoreState();
        $this->pdf->closeObject();
        $this->pdf->addObject($headerObj,'all');

        $this->pdf->ezSetMargins(($this->pdf->ez['pageHeight'] - $y_head) + 20,30,30,30);

        // Print footer
        $bottomFoot = $this->pdf->openObject();
        $this->pdf->saveState();
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        $this->pdf->setStrokeColor(.8,.8,.8);
        $this->pdf->setLineStyle(1);
        $this->pdf->line(30,30,565.28,30);
        $this->pdf->restoreState();
        $this->pdf->closeObject();

        $this->pdf->addObject($bottomFoot,'all');
        $this->pdf->ezStartPageNumbers(550,22,6);
        $this->pdf->selectFont('include/fonts/Helvetica.afm');

        bcscale(2);
        $pos_x = $this->pdf->ez['leftMargin'];
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>PO No.</b>");
        $pos_0 = $pos_x;
        $pos_x += 50;
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>Last Entry</b>");
        $pos_1 = $pos_x;
        $pos_x += 50;
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>Total Cost</b>");
        $pos_2 = $pos_x + $this->pdf->getTextWidth(8,"<b>Total Cost</b>");
        $pos_x += 62;
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>Total Sell</b>");
        $pos_3 = $pos_x + $this->pdf->getTextWidth(8,"<b>Total Sell</b>");
        $pos_x += 62;
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>Profit</b>");
        $pos_4 = $pos_x + $this->pdf->getTextWidth(8,"<b>Profit</b>");
        $pos_x += 62;
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>WIP Debits</b>");
        $pos_5 = $pos_x + $this->pdf->getTextWidth(8,"<b>WIP Debits</b>");
        $pos_x += 62;
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>WIP Credits</b>");
        $pos_6 = $pos_x + $this->pdf->getTextWidth(8,"<b>WIP Credits</b>");
        $pos_x += 62;
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>Reconciled</b>");
        $pos_7 = $pos_x + $this->pdf->getTextWidth(8,"<b>Reconciled</b>");
        $pos_x += 62;
        $this->pdf->addText($pos_x,$this->pdf->y,8,"<b>WIP Balance</b>");
        $pos_8 = $pos_x + $this->pdf->getTextWidth(8,"<b>WIP Balance</b>");
        $this->pdf->setStrokeColor(0,0.2,0.4);
        $this->pdf->setLineStyle(1);
        $this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y - 4,$this->pdf->ez['pageWidth']-$this->pdf->ez['rightMargin'],$this->pdf->y - 4);
        $this->pdf->ezSetDy(-5);

        $l = $cost = $sell = $dr = $cr = $reconciled = 0;
        $current_loop = array();
        $r = $reports->db_obj->query("SELECT t1.po_hash , t1.proposal_hash , t1.proposal_no , t1.ap_invoice_hash , t1.ar_invoice_hash ,
                                      SUM(t1.ap_total) AS ap_wip_total , SUM(t1.ar_total) AS ar_wip_total , SUM(t1.reconciled_total) AS reconciled
                                      FROM _tmp_reports_data_wip t1".(count($proposal_filter) ? "
                                      WHERE t1.proposal_hash IN (".implode(" , ",$proposal_filter).")" : NULL)."
                                      GROUP BY t1.po_hash , t1.proposal_hash ".($reports->current_report['wip_balance'] == 1 || $reports->current_report['wip_balance'] == 2 ? "
                                          HAVING (IFNULL(ap_wip_total,0) - IFNULL(ar_wip_total,0) - IFNULL(reconciled,0))".($reports->current_report['wip_balance'] == 1 ? " != 0 " : " = 0") : NULL)."
                                      ORDER BY t1.proposal_no , t1.po_hash ASC",1);
        while ($row = $reports->db_obj->fetch_assoc($r)) {
            # Print the totals for each proposal
            if (count($current_loop) > 0 && $row['proposal_hash'] != $current_loop['proposal_hash']) {
                $this->pdf->ezSetDy(-15);

                $this->pdf->setLineStyle(1,'',array(4,2));

                $this->pdf->line($pos_2 - 35,$this->pdf->y + 9,$pos_2,$this->pdf->y + 9);
                $this->pdf->addtext($pos_2 - $this->pdf->getTextWidth(8,number_format($cost,2)),$this->pdf->y,8,number_format($cost,2));

                $this->pdf->line($pos_3 - 35,$this->pdf->y + 9,$pos_3,$this->pdf->y + 9);
                $this->pdf->addtext($pos_3 - $this->pdf->getTextWidth(8,number_format($sell,2)),$this->pdf->y,8,number_format($sell,2));

                $this->pdf->line($pos_4 - 35,$this->pdf->y + 9,$pos_4,$this->pdf->y + 9);
                $this->pdf->addtext($pos_4 - $this->pdf->getTextWidth(8,number_format(bcsub($sell,$cost),2)),$this->pdf->y,8,number_format(bcsub($sell,$cost),2));

                $this->pdf->line($pos_5 - 35,$this->pdf->y + 9,$pos_5,$this->pdf->y + 9);
                $this->pdf->addtext($pos_5 - $this->pdf->getTextWidth(8,number_format($dr,2)),$this->pdf->y,8,number_format($dr,2));

                $this->pdf->line($pos_6 - 35,$this->pdf->y + 9,$pos_6,$this->pdf->y + 9);
                $this->pdf->addtext($pos_6 - $this->pdf->getTextWidth(8,number_format($cr,2)),$this->pdf->y,8,number_format($cr,2));

                $this->pdf->line($pos_7 - 35,$this->pdf->y + 9,$pos_7,$this->pdf->y + 9);
                $this->pdf->addtext($pos_7 - $this->pdf->getTextWidth(8,number_format($reconciled,2)),$this->pdf->y,8,number_format($reconciled,2));

                $this->pdf->line($pos_8 - 35,$this->pdf->y + 9,$pos_8,$this->pdf->y + 9);
                $this->pdf->addtext($pos_8 - $this->pdf->getTextWidth(8,number_format($net,2)),$this->pdf->y,8,number_format($net,2));


                $total_cost = bcadd($total_cost,$cost);
                $total_sell = bcadd($total_sell,$sell);
                $total_dr = bcadd($total_dr,$dr);
                $total_cr = bcadd($total_cr,$cr);
                $total_reconciled = bcadd($total_reconciled,$reconciled);

                $l = $cost = $sell = $dr = $cr = $reconciled = $net = 0;
            }

            if (count($current_loop) == 0 || (count($current_loop) > 0 && $row['proposal_hash'] != $current_loop['proposal_hash'])) {
                $r_2 = $this->db->query("SELECT t1.proposal_descr , t3.customer_name
                                         FROM proposals t1
                                         LEFT JOIN customers t3 ON t3.customer_hash = t1.customer_hash
                                         WHERE t1.proposal_hash = '{$row['proposal_hash']}'");
                $proposal_detail = $this->db->fetch_assoc($r_2);

                $m++;
                if ($row['proposal_no']) {
                	$this->pdf->ezSetDy(-18);
                    $this->pdf->addText($this->pdf->ez['leftMargin'],$this->pdf->y,9,"Proposal {$row['proposal_no']} - {$proposal_detail['customer_name']} - ".(strlen($proposal_detail['proposal_descr']) > 45 ? substr($proposal_detail['proposal_descr'],0,43)."..." : $proposal_detail['proposal_descr']));
			        $this->pdf->setStrokeColor(0,0.2,0.4);
			        $this->pdf->setLineStyle(1,'',array());
                    $this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->y - 4,$this->pdf->ez['pageWidth']-$this->pdf->ez['rightMargin'],$this->pdf->y - 4);
                    $this->pdf->ezSetDy(-5);
                } else
                    $this->pdf->ezSetDy(-6);

            }

            if ($showdetail == 1 || ($showdetail == 2 && $row['po_hash'] != $current_loop['po_hash'])) {
                $dr = bcadd($dr,$row['ap_wip_total']);
                $cr = bcadd($cr,$row['ar_wip_total']);
                $reconciled = bcadd($reconciled,$row['reconciled']);
                $net = bcadd($net,bcsub($row['ap_wip_total'],bcsub($row['ar_wip_total'],$row['reconciled'])));

                $item_array = array();
                $cost = $sell = 0;
                if ($row['po_hash']) {
	                $r_2 = $this->db->query("SELECT SUM(t2.cost * t2.qty) AS total_cost , SUM(t2.sell * t2.qty) AS total_sell ,
	                                         t1.po_no , t1.order_amount
	                                         FROM purchase_order t1
	                                         LEFT JOIN line_items t2 ON t2.po_hash = t1.po_hash
	                                         WHERE t1.po_hash = '{$row['po_hash']}'
	                                         GROUP BY t1.po_hash");
	                $item_array = $this->db->fetch_assoc($r_2);

	                $cost = bcadd($cost,$item_array['total_cost']);
	                $sell = bcadd($sell,$item_array['total_sell']);
                }
                unset($last_entry);
                if ($row['po_hash']) {
	                $r_2 = $this->db->query("SELECT DISTINCT t1.invoice_date
	                                         FROM vendor_payables AS t1
	                                         LEFT JOIN vendor_payable_expenses t2 ON t2.invoice_hash = t1.invoice_hash
	                                         WHERE t1.po_hash = '{$row['po_hash']}' AND t2.account_hash = '{$this->current_report['default_wip_account']}' AND t1.deleted = 0
	                                         UNION ALL
	                                         SELECT DISTINCT t1.invoice_date
	                                         FROM customer_invoice AS t1
	                                         LEFT JOIN line_items t2 ON t2.invoice_hash = t1.invoice_hash
	                                         WHERE t2.po_hash = '{$row['po_hash']}' AND t2.wip_account_hash = '{$this->current_report['default_wip_account']}' AND t2.direct_bill_amt != 'C' AND t1.deleted = 0
	                                         ORDER BY invoice_date DESC");
	                while ($row_2 = $this->db->fetch_assoc($r_2)) {
	                    if (!$last_entry || strtotime($row_2['invoice_date']) > strtotime($last_entry))
	                        $last_entry = $row_2['invoice_date'];
	                }
                }
                $this->pdf->ezSetDy(-10);

		        $this->pdf->addtext($pos_0,$this->pdf->y,8,$item_array['po_no']);
		        $this->pdf->addtext($pos_1,$this->pdf->y,8,($last_entry['last_date'] ? date(DATE_FORMAT,strtotime($last_entry)) : ""));
		        $this->pdf->addtext($pos_2 - $this->pdf->getTextWidth(8,($row['po_hash'] ? number_format($item_array['total_cost'],2) : "N/A")),$this->pdf->y,8,($row['po_hash'] ? number_format($item_array['total_cost'],2) : "N/A"));
		        $this->pdf->addtext($pos_3 - $this->pdf->getTextWidth(8,($row['po_hash'] ? number_format($item_array['total_sell'],2) : "N/A")),$this->pdf->y,8,($row['po_hash'] ? number_format($item_array['total_sell'],2) : "N/A"));
		        $this->pdf->addtext($pos_4 - $this->pdf->getTextWidth(8,($row['po_hash'] ? number_format(bcsub($item_array['total_sell'],$item_array['total_cost']),2) : "N/A")),$this->pdf->y,8,($row['po_hash'] ? number_format(bcsub($item_array['total_sell'],$item_array['total_cost']),2) : "N/A"));
		        $this->pdf->addtext($pos_5 - $this->pdf->getTextWidth(8,number_format($row['ap_wip_total'],2)),$this->pdf->y,8,number_format($row['ap_wip_total'],2));
		        $this->pdf->addtext($pos_6 - $this->pdf->getTextWidth(8,number_format($row['ar_wip_total'],2)),$this->pdf->y,8,number_format($row['ar_wip_total'],2));
		        $this->pdf->addtext($pos_7 - $this->pdf->getTextWidth(8,($row['po_hash'] ? number_format($row['reconciled'],2) : "N/A")),$this->pdf->y,8,($row['po_hash'] ? number_format($row['reconciled'],2) : "N/A"));
		        $this->pdf->addtext($pos_8 - $this->pdf->getTextWidth(8,number_format(bcsub($row['ap_wip_total'],bcsub($row['ar_wip_total'],$row['reconciled'])),2)),$this->pdf->y,8,number_format(bcsub($row['ap_wip_total'],bcsub($row['ar_wip_total'],$row['reconciled'])),2));

                $l++;
            }

            $current_loop = $row;
        }

        $this->pdf->ezSetDy(-15);

        $this->pdf->setLineStyle(1,'',array(4,2));

        $this->pdf->line($pos_2 - 35,$this->pdf->y + 9,$pos_2,$this->pdf->y + 9);
        $this->pdf->addtext($pos_2 - $this->pdf->getTextWidth(8,number_format($cost,2)),$this->pdf->y,8,number_format($cost,2));

        $this->pdf->line($pos_3 - 35,$this->pdf->y + 9,$pos_3,$this->pdf->y + 9);
        $this->pdf->addtext($pos_3 - $this->pdf->getTextWidth(8,number_format($sell,2)),$this->pdf->y,8,number_format($sell,2));

        $this->pdf->line($pos_4 - 35,$this->pdf->y + 9,$pos_4,$this->pdf->y + 9);
        $this->pdf->addtext($pos_4 - $this->pdf->getTextWidth(8,number_format(bcsub($sell,$cost),2)),$this->pdf->y,8,number_format(bcsub($sell,$cost),2));

        $this->pdf->line($pos_5 - 35,$this->pdf->y + 9,$pos_5,$this->pdf->y + 9);
        $this->pdf->addtext($pos_5 - $this->pdf->getTextWidth(8,number_format($dr,2)),$this->pdf->y,8,number_format($dr,2));

        $this->pdf->line($pos_6 - 35,$this->pdf->y + 9,$pos_6,$this->pdf->y + 9);
        $this->pdf->addtext($pos_6 - $this->pdf->getTextWidth(8,number_format($cr,2)),$this->pdf->y,8,number_format($cr,2));

        $this->pdf->line($pos_7 - 35,$this->pdf->y + 9,$pos_7,$this->pdf->y + 9);
        $this->pdf->addtext($pos_7 - $this->pdf->getTextWidth(8,number_format($reconciled,2)),$this->pdf->y,8,number_format($reconciled,2));

        $this->pdf->line($pos_8 - 35,$this->pdf->y + 9,$pos_8,$this->pdf->y + 9);
        $this->pdf->addtext($pos_8 - $this->pdf->getTextWidth(8,number_format($net,2)),$this->pdf->y,8,number_format($net,2));

        $total_cost = bcadd($total_cost,$cost);
        $total_sell = bcadd($total_sell,$sell);
        $total_dr = bcadd($total_dr,$dr);
        $total_cr = bcadd($total_cr,$cr);
        $total_reconciled = bcadd($total_reconciled,$reconciled);

        $l = $cost = $sell = $dr = $cr = $reconciled = 0;

        $r = $reports->db_obj->query("SELECT SUM(t1.ap_total) AS wip_ap_total , SUM(t1.ar_total) AS wip_ar_total ,
                                   SUM(t1.reconciled_total) AS reconciled_total ,
                                   ((SUM(t1.ar_total) - SUM(t1.reconciled_total)) - SUM(t1.ap_total)) AS wip_net_total
                                   FROM _tmp_reports_data_wip t1".($proposal_filter ? "
                                   WHERE t1.proposal_hash IN (".implode(" , ",$proposal_filter).")" : NULL).($reports->current_report['wip_balance'] == 1 || $reports->current_report['wip_balance'] == 2 ? ");
                                   HAVING IFNULL(wip_net_total,0) ".($reports->current_report['wip_balance'] == 1 ? "!= 0" : "= 0") : NULL));

        $wip_totals = $reports->db_obj->fetch_assoc($r);

        $this->pdf->ezSetDy(-25);

        $this->pdf->setLineStyle(1,'',array(4,2));

        $this->pdf->line($pos_2 - 45,$this->pdf->y + 9,$pos_2,$this->pdf->y + 9);
        $this->pdf->addtext($pos_2 - $this->pdf->getTextWidth(8,'<b>'.number_format($total_cost,2).'</b>'),$this->pdf->y,8,'<b>'.number_format($total_cost,2).'</b>');

        $this->pdf->line($pos_3 - 45,$this->pdf->y + 9,$pos_3,$this->pdf->y + 9);
        $this->pdf->addtext($pos_3 - $this->pdf->getTextWidth(8,'<b>'.number_format($total_sell,2).'</b>'),$this->pdf->y,8,'<b>'.number_format($total_sell,2).'</b>');

        $this->pdf->line($pos_4 - 45,$this->pdf->y + 9,$pos_4,$this->pdf->y + 9);
        $this->pdf->addtext($pos_4 - $this->pdf->getTextWidth(8,'<b>'.number_format(bcsub($total_sell,$total_cost),2).'</b>'),$this->pdf->y,8,'<b>'.number_format(bcsub($total_sell,$total_cost),2).'</b>');

        $this->pdf->line($pos_5 - 45,$this->pdf->y + 9,$pos_5,$this->pdf->y + 9);
        $this->pdf->addtext($pos_5 - $this->pdf->getTextWidth(8,'<b>'.number_format($wip_totals['wip_ap_total'],2).'</b>'),$this->pdf->y,8,'<b>'.number_format($wip_totals['wip_ap_total'],2).'</b>');

        $this->pdf->line($pos_6 - 45,$this->pdf->y + 9,$pos_6,$this->pdf->y + 9);
        $this->pdf->addtext($pos_6 - $this->pdf->getTextWidth(8,'<b>'.number_format($wip_totals['wip_ar_total'],2).'</b>'),$this->pdf->y,8,'<b>'.number_format($wip_totals['wip_ar_total'],2).'</b>');

        $this->pdf->line($pos_7 - 45,$this->pdf->y + 9,$pos_7,$this->pdf->y + 9);
        $this->pdf->addtext($pos_7 - $this->pdf->getTextWidth(8,'<b>'.number_format($wip_totals['reconciled_total'],2).'</b>'),$this->pdf->y,8,'<b>'.number_format($wip_totals['reconciled_total'],2).'</b>');

        $this->pdf->line($pos_8 - 45,$this->pdf->y + 9,$pos_8,$this->pdf->y + 9);
        $this->pdf->addtext($pos_8 - $this->pdf->getTextWidth(8,'<b>'.number_format($wip_totals['wip_net_total'],2).'</b>'),$this->pdf->y,8,'<b>'.number_format($wip_totals['wip_net_total'],2).'</b>');

    }
	/**
	 * Generate a customer statement report to a pdf document. Added for Trac #1156.
	 *
	 * @param string $report_hash
	 */
	function customer_statement($report_hash) {
		$reports = new reports($this->current_hash);
		$reports->fetch_report_settings($report_hash);

		// Start the PDF document ( A4 size x:595.28 y:841.89 )
		$this->pdf = new Cezpdf();
		$this->pdf->ezSetMargins(30,30,30,30);

		// Calculate x coordinate for each column
		$width1 = 70;
		$width2 = 90;
		$col1 = 45;
		$col2 = 100;
		$col3 = 170;
		$col4 = 240;
		$col5 = 290;
		$col6 = 340;
		$col7 = 390;
		$col8 = 440;
		$col9 = 490;
		$col10 = 540;
		$font_size = 7;
		$font_size2 = 8;
		$right_adj = 10;

		//  Column headers
		$cols = array("invoice_no"	  		=>	"Invoice No.",
						"invoice_date" 		=>	"Invoice Date",
						"due_date" 			=> "Due Date",
						"orig_amt"			=> "Orig Amt",
						"payments"			=> "Payments",
						"balance"			=> "Balance",
						"balance_current"	=> "Current",
						"balance_sched1"	=> $reports->current_report['age1']." Days",
						"balance_sched2"	=> $reports->current_report['age2']." Days",
						"balance_sched3" 	=> $reports->current_report['age3']." Days");

		$title = "Customer Statment";
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);
		$producer = $users->current_user['full_name'];
		$creation_date = date(TIMESTAMP_FORMAT,time());

		$this->pdf->addInfo('Title',$title);
		$this->pdf->addInfo('Producer',$producer);
		$this->pdf->addInfo('CreationDate',$creation_date);
		$title = "Customer Statement";

		$sql = stripslashes($reports->current_report['sql']);
		$this->db->query("SET @DEFAULT_CUST_PAY_TERMS = ".DEFAULT_CUST_PAY_TERMS);
		$this->db->query("CALL customer_statement('{$reports->current_report['compare_date']}','{$reports->current_report['age1']}','{$reports->current_report['age2']}','{$reports->current_report['age3']}');");

		$result = $this->db->query("SELECT t1.customer_hash , t1.invoice_to , t1.customer_name
									FROM _tmp_reports_data_customer_statement t1".($sql ? "
									WHERE ".$sql : NULL)."
									GROUP BY customer_hash
									ORDER BY customer_name");

		while ($row = $this->db->fetch_assoc($result)) {
			$customers = new customers($_SESSION['id_hash']);
			$customer_name = stripslashes($row['customer_name']);
			$customer_hash = $row['customer_hash'];
			unset($customer_info,$contact_name);
			if ($row['invoice_to'] == 'V') {
				$obj = new vendors($this->current_hash);
				$class = 'vendors';
				$obj->fetch_master_record($customer_hash);
				if ($obj->current_vendor['vendor_contact']) {
					$obj->fetch_contact_record($obj->current_vendor['vendor_contact']);
					$contact_name = stripslashes($obj->current_contact['contact_name']);
				}
				$customer_info = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
                    stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
                ) .
                stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{'urrent_'.strrev(substr(strrev($class),1))}['state']} {$obj->{'current_'.strrev(substr(strrev($class),1))}['zip']}";
			} else {
				$obj = new customers($this->current_hash);
				$class = 'customers';
				$obj->fetch_master_record($customer_hash);
				$id = $customer_hash;

				// Fetch Customer Contact
				if ($row['customer_contact']) {
					$obj->fetch_contact_record($row['customer_contact']);
					if ($obj->current_contact['contact_name'])
						$contact_name = stripslashes($obj->current_contact['contact_name']);
				}
				$customer_info = stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"]) . "\n" .
				( $obj->{"current_".strrev(substr(strrev($class),1))}['street'] ?
					stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['street']) . "\n" : NULL
				) .
                stripslashes($obj->{"current_".strrev(substr(strrev($class),1))}['city']) . ", {$obj->{"current_".strrev(substr(strrev($class),1))}['state']} {$obj->{"current_".strrev(substr(strrev($class),1))}['zip']}";
			}

	/*      TODO Do currency
	 		if ($invoice->current_invoice['currency']) {
	            $this->db->define('convert_currency',1);
	            $this->db->define('local_currency',$invoice->current_invoice['currency']);
	            $this->db->define('local_exchange_rate',$invoice->current_invoice['exchange_rate']);
	        }
	*/
			$headfoot = $this->pdf->openObject();
			$this->pdf->saveState();
			$this->pdf->selectFont('include/fonts/Helvetica.afm');
			$y_start = 825 - $this->h;
			$this->pdf->y = 825;

			if ($this->img) {
				$this->pdf->ezImage($this->img,5,$this->w,'','left');
				$this->pdf->y = $y;
			}

			$y_start -= 30;
			$this->pdf->setStrokeColor(0,0.2,0.4);
			$this->pdf->setLineStyle(2,'round');
			$x_start = 40 + $this->w;
			$rightXpos = $this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'];

			//Customer
			$this->pdf->line($x_start,811.89,$rightXpos,811.89);
			$text_width = $this->pdf->getTextWidth(20,$title);
			$width_customer_name = 525.28-$text_width-$this->w;
			$this->pdf->addTextWrap(40 + $this->w,815.89,$width_customer_name,10,"Customer: ".$customer_name);
			//Invoice title
			$text_width = $this->pdf->getTextWidth(20,$title);
			$this->pdf->addText($rightXpos-$text_width-10,814,20,$title);
			//Statement Date
			$text_width = $this->pdf->getTextWidth(10,"Statement Date: ".date(DATE_FORMAT));
			$font_height = $this->pdf->getFontHeight(10);
			$this->pdf->addText($rightXpos-$text_width-10,811.89 - $font_height,10,"Statement Date: ".date(DATE_FORMAT));

			$this->pdf->line($this->pdf->ez['leftMargin'],$this->pdf->ez['bottomMargin'],$rightXpos,$this->pdf->ez['bottomMargin']);

			// Adjust height to prevent overlapping due to short logos. Added for Madden intially. Trac #774
			if ($this->h < 60) {
				$this->h = 60;
			}

			$xpos = 0;
			$this->pdf->ez['topMargin'] = 30 + $this->h;
			$this->pdf->ezSetY($this->pdf->ez['pageHeight']-$this->pdf->ez['topMargin']);

			//Make it work with the window envelope
			if ($this->pdf->y > 690)
				$this->pdf->ezSetY(690);

			$this->pdf->ezSetDy(5);
			$y_title = $this->pdf->y;
			$this->pdf->ezText("Customer:",12);
			$this->pdf->ezSetDy(-5);
			$y_content = $this->pdf->y;
			$y = $this->pdf->ezText($customer_info,12);

			// Print header for each column
			$this->pdf->ezSetDy(-20);
			$this->pdf->addTextWrap($col1,$this->pdf->y,$width2,$font_size,"<b>".$cols['invoice_no']."</b>");
			$this->pdf->addTextWrap($col2,$this->pdf->y,$width1,$font_size,"<b>".$cols['invoice_date']."</b>");
			$this->pdf->addTextWrap($col3,$this->pdf->y,$width2,$font_size,"<b>".$cols['due_date']."</b>");
			$this->pdf->addTextWrap($col4 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>".$cols['orig_amt']."</b>");
			$this->pdf->addTextWrap($col5 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>".$cols['payments']."</b>");
			$this->pdf->addTextWrap($col6 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>".$cols['balance']."</b>");
			$this->pdf->addTextWrap($col7 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>".$cols['balance_current']."</b>");
			$this->pdf->addTextWrap($col8 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>".$cols['balance_sched1']."</b>");
			$this->pdf->addTextWrap($col9 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>".$cols['balance_sched2']."</b>");
			$this->pdf->addTextWrap($col10 - $right_adj,$this->pdf->y,$width1,$font_size,"<b>".$cols['balance_sched3']."</b>");

			$this->pdf->setStrokeColor(0,0,0);
			$this->pdf->setLineStyle(1);
			$this->pdf->line($this->pdf->ez['leftMargin'], $this->pdf->y - 5,$this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'],$this->pdf->y - 5);

			$this->pdf->setStrokeColor(0,0,0);
			$this->pdf->setLineStyle(1);
			$this->pdf->line(30,$y - 10,$rightXpos,$y - 10);
			$this->pdf->ezSetDy(-15);

			$this->pdf->restoreState();
			$this->pdf->closeObject();
			$this->pdf->addObject($headfoot);
			$this->pdf->ezStartPageNumbers($rightXpos-15,22,6);
			$this->pdf->selectFont('include/fonts/Helvetica.afm');

			$show_proposal_no = true;
			$result2 = $this->db->query("SELECT * FROM _tmp_reports_data_customer_statement
										 WHERE customer_hash = '$customer_hash'".($sql ? " AND $sql" : NULL)."
										 GROUP BY proposal_hash");

			$total_invoice = $total_receipts = $total_balance = $total_current = $total_sched1 = $total_sched2 = $total_sched3 = 0;
			while ($proposals = $this->db->fetch_assoc($result2)) {
				$proposal_hash = $proposals['proposal_hash'];
				if ($proposals['proposal_no']) {
					$this->pdf->addText(30,$this->pdf->y,$font_size,"Proposal: ".$proposals['proposal_no']);
					$this->pdf->ezSetDy(-10);

				}
				$result3 = $this->db->query("SELECT t1.* , t2.obj_id as payment_details
											 FROM _tmp_reports_data_customer_statement t1
											 LEFT JOIN customer_payment t2 ON t2.invoice_hash = t1.invoice_hash
											 LEFT JOIN accounts t3 ON t3.account_hash = t2.account_hash
											 WHERE proposal_hash = '$proposal_hash'".($sql ? " AND $sql" : NULL)."
											 GROUP BY invoice_hash");

				while ($details = $this->db->fetch_assoc($result3)) {
					$total_balance = bcadd($total_balance,$details['balance_total'],2);
					$total_invoice = bcadd($total_invoice,$details['total_receivable'],2);
					$total_receipts = bcadd($total_receipts,$details['total_receipts'],2);
					$total_current = bcadd($total_current,$details['balance_current']);
					$total_sched1 = bcadd($total_sched1,$details['balance_sched1']);
					$total_sched2 = bcadd($total_sched2,$details['balance_sched2']);
					$total_sched3 = bcadd($total_sched3,$details['balance_sched3']);
					$invoice_hash = $details['invoice_hash'];

					$this->pdf->addTextWrap($col1,$this->pdf->y,$width2,$font_size,($details['type'] == 'I' ? $details['invoice_no'] : (!$proposal_hash ? "Unapplied " : NULL)."Deposit"));
					$this->pdf->addTextWrap($col2,$this->pdf->y,$width2,$font_size,date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($details['invoice_date'])));
					$this->pdf->addTextWrap($col3,$this->pdf->y,$width2,$font_size,date((ereg('F',DATE_FORMAT) ? 'M jS, Y' : DATE_FORMAT),strtotime($details['due_date'])));
					$this->pdf->addTextWrap($col3+20,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($details['total_receivable']),'right');
					$this->pdf->addTextWrap($col4,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($details['total_receipts']),'right');
					$this->pdf->addTextWrap($col5,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($details['balance_total']),'right');
					$this->pdf->addTextWrap($col6,$this->pdf->y,$width1,$font_size,$this->format_dollars($details['balance_current']),'right');
					$this->pdf->addTextWrap($col7,$this->pdf->y,$width1,$font_size,$this->format_dollars($details['balance_sched1']),'right');
					$this->pdf->addTextWrap($col8,$this->pdf->y,$width1,$font_size,$this->format_dollars($details['balance_sched2']),'right');
					$this->pdf->addTextWrap($col9,$this->pdf->y,$width1,$font_size,$this->format_dollars($details['balance_sched3']),'right');
					$this->pdf->ezSetDy(-10);
					/* TODO Convert this to pdf format
					if ($details['payment_details'] && $reports->current_report['showdetail'] == 2) {
						$result4 = $this->db->query("SELECT t1.receipt_amount , t1.check_no , t1.receipt_date , t1.reconciled , t1.applied_type , t1.applied_from ,
													 t1.currency_adjustment , t2.account_name , t2.account_no , t3.account_name as reconciled_account
													 FROM customer_payment t1
													 LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
													 LEFT JOIN accounts t3 ON t3.account_hash = t1.applied_from
													 WHERE invoice_hash = '$invoice_hash' AND t1.deleted = 0");
						echo "
							<tr>
								<td style=\"background-color:#ffffff;font-size:8pt;\" colspan =\"5\">
									<div style=\"float:right;\">
										<table>
											<tr>
												<td>Check/Credit No.</td>
												<td>Receipt Date</td>
												<td>Account</td>
												<td class=\"num_field\">Currency Adj</td>
												<td class=\"num_field\">Amount</td>
											</tr>";
						while ($payments = $this->db->fetch_assoc($result4)) {
							echo "
											<tr>
												<td class=\"smallfont\">".$payments['check_no']."</td>
												<td class=\"smallfont\">".date(DATE_FORMAT,strtotime($payments['receipt_date']))."</td>
												<td class=\"smallfont\">".($payments['applied_from'] ?
													($payments['reconciled'] ?
													    $payments['reconciled_account'] : "<i>Applied from Customer ".($payments['applied_type'] == 'C' ?
															"Credit" : "Deposit")."</i>") : $payments['account_name'])."
												</td>
												<td class=\"smallfont\" style=\"text-align:right;\">".($payments['currency_adjustment'] != 0 ?
													($payments['currency_adjustment'] < 0 ?
														"($".number_format($payments['currency_adjustment'] * -1,2).")" : "$".number_format($payments['currency_adjustment'],2)) : NULL)."
												</td>
												<td class=\"smallfont\" style=\"text-align:right;\">$".number_format($payments['receipt_amount'],2)."</td>
											</tr>";
						}
						echo "			</table>
									</div>
								</td>
								<td style=\"background-color:#ffffff;font-size:8pt;\" colspan =\"5\">&nbsp;</td>
							</tr>";
					}*/
				}
			}

			$this->pdf->addText(30,$this->pdf->y,$font_size,"Totals:");
			$this->pdf->addTextWrap($col3+20,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($total_invoice),'right');
			$this->pdf->addTextWrap($col4,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($total_receipts),'right');
			$this->pdf->addTextWrap($col5,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($total_balance),'right');
			$this->pdf->addTextWrap($col6,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($total_current),'right');
			$this->pdf->addTextWrap($col7,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($total_sched1),'right');
			$this->pdf->addTextWrap($col8,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($total_sched2),'right');
			$this->pdf->addTextWrap($col9,$this->pdf->y,$width1,$font_size,$this->format_dollars_show_zero($total_sched3),'right');

			$this->pdf->ezNewPage();
			$this->pdf->ezStopPageNumbers(1,0);
		}

/*        if ($invoice->current_invoice['currency'] && $invoice->current_invoice['exchange_rate']) {
            $this->pdf->addText($this->pdf->ez['pageWidth'] - $this->pdf->ez['rightMargin'] - $this->pdf->getTextWidth(10,"Currency".$invoice->current_invoice['currency']." ".rtrim(trim($invoice->current_invoice['exchange_rate'], '0'), '.')."%") - 10,$this->pdf->y - 15,10,"Currency: ".$invoice->current_invoice['currency']." ".rtrim(trim($invoice->current_invoice['exchange_rate'], '0'), '.')."%");
            if ($this->print_pref['invoice_detail'])
				$this->pdf->ezSetDy(-20);
        }
*/
	}
}
?>
