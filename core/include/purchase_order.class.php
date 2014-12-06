<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class purchase_order extends AJAX_library {

	public $total;

	public $po_info = array();
	public $current_po = array();
	public $po_status = array(
    	'0' => 	'Transmission Failed',
		'1' =>	'Transmission Pending',
		'2' =>	'Transmission In Progress',
		'3' =>	'Transmission Successful',
		'4' =>	'',
		'5' =>	'Transmission Cancelled'
	);
	public $po_icons = array(
        '0' =>		"<img src=\"images/po_failed.gif\" title=\"Purchase Order Transmission Failed\" />",
		'1' =>		"<img src=\"images/po_pending.gif\" title=\"Purchase Order Transmission Pending\" />",
		'2' =>		"<img src=\"images/po_sending.gif\" title=\"Purchase Order Transmission In Progress\" />",
		'3' =>		"<img src=\"images/po_sent.gif\" title=\"Purchase Order Transmission Successful\" />",
		'4' =>		'',
		'5' =>		"<img src=\"images/po_failed.gif\" title=\"Purchase Order Transmission Cancelled By User\" />"
	);

	function purchase_order($proposal_hash, $obj_id=false) {
		global $db;

		$this->form = new form;
		$this->db =& $db;

		$this->current_hash = $_SESSION['id_hash'];
		$this->validate = new Validator;

		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);
		if ($proposal_hash) {
			if ($obj_id) {
                $r = $this->db->query("SELECT COUNT(purchase_order.obj_id) AS Total , proposals.proposal_hash
                                       FROM purchase_order
                                       LEFT JOIN proposals ON proposals.proposal_hash = purchase_order.proposal_hash
                                       WHERE proposals.obj_id = $proposal_hash AND purchase_order.deleted = 0
                                       GROUP BY purchase_order.proposal_hash");
                $this->proposal_hash = $this->db->result($r, 0, 'proposal_hash');
                $this->total = $this->db->result($r, 0, 'Total');
			} else {
				$this->proposal_hash = $proposal_hash;
				$result = $this->db->query("SELECT COUNT(*) AS Total
											FROM `purchase_order`
											WHERE `proposal_hash` = '".$this->proposal_hash."' AND purchase_order.deleted = 0");
				$this->total = $this->db->result($result);
			}
			if ($this->total) {
				$result = $this->db->query("SELECT COUNT(*) AS Total
											FROM `purchase_order`
											WHERE `proposal_hash` = '".$this->proposal_hash."' AND `punch` = 1 AND purchase_order.deleted = 0");
				$this->total_punch = $this->db->result($result);
			}
		}
	}

	function fetch_purchase_orders($start, $end, $order_by=NULL, $order_dir=NULL) {
		$this->po_info = array();
		if ($this->active_search) {
			$result = $this->db->query("SELECT `query` , `total`
									    FROM `search`
									    WHERE `search_hash` = '".$this->active_search."'");
			$row = $this->db->fetch_assoc($result);
			$this->total = $row['total'];
			$sql = __unserialize(base64_decode($row['query']));
		}

		if ($this->total > 0) {
			$result = $this->db->query("SELECT DISTINCT purchase_order.* , vendors.vendor_name
										FROM `purchase_order`
										LEFT JOIN vendors ON vendors.vendor_hash = purchase_order.vendor_hash
										LEFT JOIN proposal_notes ON proposal_notes.type_hash = purchase_order.po_hash
										WHERE purchase_order.proposal_hash = '".$this->proposal_hash."' AND ".($sql ?
                                			$sql." AND " : NULL)."purchase_order.deleted = 0 ".($order_by ? "
    											ORDER BY ".$order_by.($order_dir ? "
    												$order_dir" : " ASC") : "ORDER BY po_no ASC")."
										LIMIT $start , $end");
			while ($row = $this->db->fetch_assoc($result))
				array_push($this->po_info, $row);

		}
	}


	function fetch_po_record($proposal_hash, $po_hash, $edit=false) {
		if (!$po_hash || !$proposal_hash)
			return;

		$result = $this->db->query("SELECT t1.* , t2.vendor_name , SUM( t3.sell * t3.qty ) AS total_sell ,
									SUM( t3.cost * t3.qty ) AS total_cost , SUM( t3.list * t3.qty ) AS total_list ,
									IF(ISNULL(t1.currency), NULL, ROUND(currency_exchange(t1.order_amount, t1.exchange_rate, 0), 2)) AS order_amount_converted
									FROM purchase_order t1
									LEFT JOIN vendors t2 ON t2.vendor_hash = t1.vendor_hash
									LEFT JOIN line_items t3 ON t3.po_hash = t1.po_hash
									WHERE t1.po_hash = '$po_hash'
									GROUP BY t1.po_hash");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_po = $row;
			$this->current_po['line_items'] = array();
			$this->proposal_hash = $proposal_hash;
			$this->po_hash = $po_hash;

            # Total Payables
            $this->current_po['total_payables'] = 0;
            $r = $this->db->query("SELECT t1.obj_id
                                   FROM vendor_payables t1
                                   WHERE t1.po_hash = '".$this->po_hash."' AND t1.deleted = 0");
            while ($row_2 = $this->db->fetch_assoc($r))
                $this->current_po['total_payables']++;

            # Total Items
            $this->current_po['total_items'] = $this->current_po['total_ack_lines'] = 0;
            $r = $this->db->query("SELECT t1.obj_id , t1.ack_no
                                   FROM line_items t1
                                   WHERE t1.po_hash = '".$this->po_hash."'");
            while ($row_2 = $this->db->fetch_assoc($r)) {
                $this->current_po['total_items']++;
                if ($row_2['ack_no'])
                    $this->current_po['total_ack_lines']++;
            }

            # Work Order Items
            $this->current_po['total_work_order_items'] = 0;
            $r = $this->db->query("SELECT t1.obj_id
                                   FROM work_order_items t1
                                   WHERE t1.po_hash = '".$this->po_hash."'");
            while ($row_2 = $this->db->fetch_assoc($r))
                $this->current_po['total_work_order_items']++;

			if ($edit)
				$this->lock = $this->content['row_lock'] = $this->p->lock("purchase_order", $this->po_hash, $this->popup_id);

			unset ($this->current_po['fully_ack']);

			// Only set PO as fully_ack if it is not a work order PO and it meets other criteria.
			if ($this->current_po['total_ack_lines'] >= ($this->current_po['total_items'] + $this->current_po['total_work_order_items']) && ($this->current_po['total_items'] + $this->current_po['total_work_order_items']) > 0)
                $this->current_po['fully_ack'] = 1;

			list($class, $id, $hash) = explode("|", $this->current_po['ship_to_hash']);

			if ( $class ) {

				$obj = new $class($this->current_hash);
				if ( $hash ) {

					if ( $loc_valid = $obj->fetch_location_record($hash) ) {

						$this->current_po['ship_to'] =
						stripslashes($obj->current_location['location_name']) . "<br />" .
						( $obj->current_location['location_street'] ?
    						stripslashes($obj->current_location['location_street']) . "\n" : NULL
    					) .
    					stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

					} else
						$this->current_po['ship_to'] = "<i>Invalid Shipping Location</i>";
				} else {

					$obj->fetch_master_record($id);
					$this->current_po['ship_to'] =
					stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }[strrev( substr( strrev($class), 1) ) . "_name"]) . "<br />" .
					( $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["street"] ?
    					stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }["street"]) . "<br />" : NULL
    				) .
    				stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }["city"]) . ", " . $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["state"] . " " . $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["zip"];
				}

				unset($obj);
			}

			if ( $this->current_po['total_items'] > 0 || $this->current_po['total_work_order_items'] ) {

				$result = $this->db->query("SELECT line_items.item_hash , line_items.po_line_no
											FROM `line_items`
											WHERE line_items.proposal_hash = '$proposal_hash' AND line_items.po_hash = '$po_hash'
											UNION
											SELECT work_order_items.item_hash , work_order_items.po_line_no
											FROM `work_order_items`
											WHERE work_order_items.po_hash = '$po_hash'");
				while ($row = $this->db->fetch_assoc($result))
					$order[$row['item_hash']] = $row['po_line_no'];


                asort($order);
                while (list($h) = each($order))
                    $this->current_po['line_items'][] = $h;

                unset($order);
			}
			return true;
		}
		return false;
	}

	function fetch_po_deposit($po_hash) {
		$result = $this->db->query("SELECT `invoice_hash`
									FROM `vendor_payables`
									WHERE `type` = 'D' AND `po_hash` = '$po_hash' AND vendor_payables.deleted = 0");
		while ($row = $this->db->fetch_assoc($result))
			$dep[] = $row['invoice_hash'];

		return $dep;
	}

	function doit_new_po() {
		$po_items = $_POST['po_item'];
		$step = $_POST['step'];
		$final = $_POST['final'];
		$punch = $_POST['punch'];

		if (!$step && !$final) {
			for ($i = 0; $i < count($po_items); $i++) {
				if ($po_items[$i])
					$line_item[] = $po_items[$i];
			}

			if (count($line_item)) {
				$this->lines = new line_items($this->proposals->proposal_hash);
				$this->vendors = new vendors($this->current_hash);
				$this->pm = new pm_module($this->proposals->proposal_hash);

				$po_data = $line_item;

				$po_data = array();
				$hash_index = array();
				for ($i = 0; $i < count($line_item); $i++) {
					$this->lines->fetch_line_item_record($line_item[$i]);
					if (!$this->lines->current_line['item_code']) {
						if ($this->lines->current_line['work_order_hash']) {
							$this->pm->fetch_work_order($this->lines->current_line['work_order_hash']);
							$this->pm->fetch_resources_used();
							for ($j = 0; $j < $this->pm->total_resources_used; $j++)
								$po_data[$this->pm->resources_used[$j]['vendor_hash']][$this->lines->current_line['ship_to_hash']][$this->lines->current_line['product_hash']][] = array("item_hash"			=>	$this->lines->item_hash,
																																														 "item_type"			=>	"W",
																																														 "resource_hash"		=>	$this->pm->resources_used[$j]['resource_hash'],
																																														 "product_hash"			=>	$this->lines->current_line['product_hash'],
																																														 "vendor_hash"			=>	$this->pm->resources_used[$j]['vendor_hash'],
																																														 "work_order_item_hash"	=>	$this->pm->resources_used[$j]['item_hash']);

						} else
							$po_data[$this->lines->current_line['vendor_hash']][$this->lines->current_line['ship_to_hash']][$this->lines->current_line['product_hash']][] = array("item_hash"		=>	$line_item[$i],
																																											  	  "item_type"		=>	"S",
																																											      "product_hash"	=>	$this->lines->current_line['product_hash'],
																																											      "vendor_hash"		=>	$this->lines->current_line['vendor_hash']);
					} else //[$this->lines->current_line['ship_to_hash']] 2nd element
						$freight_array[$this->lines->current_line['vendor_hash']][] = array("item_hash"			=>	$line_item[$i],
																						  	"item_type"			=>	"S",
																						    "product_hash"		=>	$this->lines->current_line['product_hash'],
																						    "vendor_hash"		=>	$this->lines->current_line['vendor_hash'],
																							"item_code_parent"	=>	$this->lines->current_line['item_code_parent']);

				}
				while (list($vendor_hash, $arr) = each($po_data)) {
					$count = count($freight_array[$vendor_hash]);
					while (list($ship_to_hash, $prod_arr) = each($arr)) {
						$po_item_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('purchase_order', 'po_hash', $po_item_hash))
							$po_item_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$hash_index[$po_item_hash] = array();
						while (list($product_hash, $item_array) = each($prod_arr)) {
							$this->vendors->fetch_product_record($product_hash);
							if ($this->vendors->current_product['separate_po']) {
								$tmp_po_hash = md5(global_classes::get_rand_id(32, "global_classes"));
								while (global_classes::key_exists('purchase_order', 'po_hash', $tmp_po_hash))
									$tmp_po_hash = md5(global_classes::get_rand_id(32, "global_classes"));

								$separate_po[$tmp_po_hash] = 1;
							} else {
								if (!$first_general_po[$vendor_hash])
									$first_general_po[$vendor_hash] = $po_item_hash;

								unset($tmp_po_hash);
							}
							for ($i = 0; $i < count($item_array); $i++)
								$hash_index[($tmp_po_hash ? $tmp_po_hash : $po_item_hash)][] = $item_array[$i];

							for ($i = 0; $i < $count; $i++) {
								if ($freight_array[$vendor_hash][$i]['item_code_parent'] == $product_hash) {
									$hash_index[($tmp_po_hash ? $tmp_po_hash : $po_item_hash)][] = $freight_array[$vendor_hash][$i];
									unset($freight_array[$vendor_hash][$i]);
								}
							}
						}
					}
					if (!$first_general_po[$vendor_hash])
						$first_general_po[$vendor_hash] = $tmp_po_hash;

					foreach ($freight_array[$vendor_hash] as $i => $a) {
						if (!$a['item_code_parent']) {
							$hash_index[$first_general_po[$vendor_hash]][] = $a;
							unset($freight_array[$vendor_hash][$i]);
						}
					}
				}
				if ($freight_array) {
                    while (list($vendor_hash, $a) = each($freight_array)) {
                    	$tmp_array = array_values($freight_array[$vendor_hash]);
                        if (count($freight_array[$vendor_hash])) {
	                        $po_item_hash = md5(global_classes::get_rand_id(32, "global_classes"));
	                        while (global_classes::key_exists('purchase_order', 'po_hash', $po_item_hash))
	                            $po_item_hash = md5(global_classes::get_rand_id(32, "global_classes"));

	                        $hash_index[$po_item_hash] = array();
                            for ($i = 0; $i < count($tmp_array); $i++){
	                            $hash_index[$po_item_hash][] = $tmp_array[$i];
                            }
                        }
                    }
				}
			} else {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "You selected no line items! In order to create a new purchase order you need to select at least 1 line item you intend to order.";
				return;
			}
			
			$this->db->query("UPDATE `commissions_paid`
					SET `paid_in_full` = 0
					WHERE `proposal_hash` = '{$this->proposals->proposal_hash}'");

			unset($this->ajax_vars);
			$this->ajax_vars['po_data'] =& $hash_index;
			$this->ajax_vars['separate_po'] =& $separate_po;
			$this->ajax_vars['step'] = 2;
			$this->ajax_vars['punch'] = $punch;
			$this->ajax_vars['proposal_hash'] = $this->proposals->proposal_hash;
			$this->ajax_vars['popup_id'] = $this->popup_id;
			$this->content['action'] = 'continue';
			list($this->content['html']['po_content'.$this->popup_id], $this->content['jscript']) = $this->new_po(1);
			return;
		} elseif ($final == 1) {
			$po_hash = $_POST['po_hash'];
			$vendors = new vendors($this->current_hash);
			$submit_btn = 'po_btn';

			// Fetch user preferences for PO Generation only
			if ($print_pref_po_gen = fetch_user_data("po_gen"))
				$print_pref_po_gen = __unserialize(stripslashes($print_pref_po_gen));
			else
				$print_pref_po_gen = array();

			for ($i = 0; $i < count($po_hash); $i++) {
				$po = array();
				$vendor_hash = $_POST['vendor_hash_'.$po_hash[$i]];
				$vendors->fetch_master_record($vendor_hash);
				$ship_to_hash = $_POST['ship_to_hash_'.$po_hash[$i]];
				$no_items = $_POST['no_items_'.$po_hash[$i]];
				$vendor_info = $_POST['vendor_info_'.$po_hash[$i]];
				$order_total = $_POST['order_total_'.$po_hash[$i]];
				$total_list = $_POST['total_list_'.$po_hash[$i]];
				$total_sell = $_POST['total_sell_'.$po_hash[$i]];
				$currency = $_POST['currency_'.$po_hash[$i]];

				$item_array = $_POST['item_'.$po_hash[$i]];
				$work_order_item_array = $_POST['work_order_item_'.$po_hash[$i]];

				$transmit_type = $_POST['transmit_type_'.$po_hash[$i]];
				$print_pref_po_gen[$vendor_hash]['order_method_type'] = $transmit_type;
				if ($transmit_type == 'E') {
					$transmit_to = $_POST['transmit_to_email_'.$po_hash[$i]];
					$transmit_to_cc = (trim($_POST['transmit_to_cc_'.$po_hash[$i]]) ? explode("\n", $_POST['transmit_to_cc_'.$po_hash[$i]]) : NULL);
				} elseif ($transmit_type == 'F')
					$transmit_to = $_POST['transmit_to_fax_'.$po_hash[$i]];
				elseif ($transmit_type == 'EE' || $transmit_type == 'ES') {
					if (!file_exists(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file'])) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The E-Order template can not be found. Please make sure your vendor has a valid DealerChoice E-Order template file attached saved within the vendor profile.";
						$this->content['submit_btn'] = "po_btn";
						return;
					}
					include_once(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file']);
					$efile_class = explode(".", $vendors->current_vendor['eorder_file']);
					$efile_class_name = "eorder_".$efile_class[1];
					$efile_obj = new $efile_class_name($vendor_hash, $this->proposals->proposal_hash, $po_hash[$i]);

					if ($transmit_type == 'EE' && $efile_obj->send_method == 'EMAIL') {
						$transmit_to = $_POST['transmit_to_email_'.$po_hash[$i]];
						$transmit_to_cc = (trim($_POST['transmit_to_cc_'.$po_hash[$i]]) ? explode("\n", $_POST['transmit_to_cc_'.$po_hash[$i]]) : NULL);
					} elseif ($transmit_type == 'EE' && !is_null($efile_obj->send_method))
						$transmit_to = 'SOAP';

					// Validate user input from efile obj if method exists, otherwise validate against the mandatory fields
					if (method_exists($efile_obj, "validate_user_input")) {
						$user_error = $efile_obj->validate_user_input($_POST["einput_".$po_hash[$i]]);

						if ($user_error == false) {
							while (list($field, $on) = each($efile_obj->user_error))
								$this->content['form_return']['err']["err_".$po_hash[$i]."_".$field] = 1;

							$this->content['error'] = 1;
							if ($efile_obj->error_message)
								$feedback = $efile_obj->error_message;
						}
					}
					if ($this->content['error']) {
						$this->content['error'] = 1;
						$this->content['submit_btn'] = "po_btn";
						$this->content['form_return']['feedback'] = ($feedback ? $feedback : "You left some required fields blank within the e-order input! Please check the indicated fields below and try again.");
						return;
					}

				}

                if ($currency) {
                    $sys = new system_config();
                    if ($sys->fetch_currency($currency) == false)
                        return $this->__trigger_error("Unable to fetch currency for PO creation. Please make sure currency rule has been properly configured.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    if (!$sys->home_currency['account_hash'])
                        return $this->__trigger_error("You have not defined a valid chart of account to be used for capturing gain/loss on currency exchange.", E_USER_NOTICE, __FILE__, __LINE__, 1, $submit_btn);
                }

				$rq_ship_date = $_POST['rq_ship_date_'.$po_hash[$i]];
				$rq_arrive_date = $_POST['rq_arrive_date_'.$po_hash[$i]];
				$rq_deliver_date = $_POST['einput_req_deliver_date_'.$po_hash[$i]];
				$file_vault = $_POST['file_vault_'.$po_hash[$i]];

				if ($rq_deliver_date && strtotime($rq_deliver_date) < strtotime(date("Y-m-d")))  {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err_'.$po_hash[$i].'_DeliveryDate'] = 1;
					$this->content['form_return']['feedback'][] = "The date you entered is not valid.";
				}

				if (($rq_arrive_date && strtotime($rq_arrive_date) < strtotime(date("Y-m-d"))) || ($rq_ship_date && strtotime($rq_ship_date) < strtotime(date("Y-m-d"))) || ($rq_arrive_date && $rq_ship_date && strtotime($rq_ship_date) > strtotime($rq_arrive_date))) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err1_'.$po_hash[$i]] = 1;
					$this->content['form_return']['err']['err2_'.$po_hash[$i]] = 1;
					$this->content['form_return']['feedback'][] = "The dates you entered are not valid. Please make sure that your requested arrival date falls after the ship date.";
				}

				if (($transmit_type == 'E' || ($transmit_type == 'EE' && $efile_obj->send_method == 'EMAIL')) && $transmit_to_cc) {
					$count = count($transmit_to_cc);
					for ($j = 0; $j < $count; $j++) {
						if (trim($transmit_to_cc[$j])) {
							if (!$this->validate->is_email(trim($transmit_to_cc[$j]))) {
								$email_cc_err = 1;
								$this->content['error'] = 1;
								$invalid_cc[] = $transmit_to_cc[$j];
								$this->content['form_return']['err']['err3a_'.$po_hash[$j]] = 1;
							}
						} else
							unset($transmit_to_cc[$i]);
					}
					unset($count);
				}

				if ($transmit_type != 'ES' && ($transmit_type && !$transmit_to) || ($transmit_type == 'F' && !$this->validate->is_phone($transmit_to)) || ($transmit_type == 'E' && !$this->validate->is_email($transmit_to)) || ($transmit_type == 'EE' && $efile_obj->send_method == 'EMAIL' && !$this->validate->is_email($transmit_to))) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err3_'.$po_hash[$i]] = 1;
					if ($transmit_type == 'E' && !$this->validate->is_email($transmit_to)) {
						$email_err = 1;
						$invalid_email[] = $transmit_to;
					}
					if ($transmit_type == 'F' && !$this->validate->is_phone($transmit_to)) {
						$fax_err = 1;
						$invalid_fax[] = $transmit_to;
					}
					if ($transmit_type && !$transmit_to)
						$transmit_err = 1;
				}
			}

			if ($this->content['error']) {
				if ($email_cc_err)
					$this->content['form_return']['feedback'][] = "One or more of the CC email addresses you entered is invalid. Please check the indicated fields below and enter a valid email address. ".($invalid_cc ? "Invalid email(s): ".implode("; ", $invalid_cc) : NULL);
				if ($email_err && $transmit_to)
					$this->content['form_return']['feedback'][] = "One or more of the email addresses you entered is invalid. Please check the indicated fields below and enter a valid email address. ".($invalid_email ? "Invalid email(s): ".implode("; ", $invalid_email) : NULL);
				if ($fax_err && $transmit_to)
					$this->content['form_return']['feedback'][] = "One or more of the fax number(s) you entered is invalid. Please check the indicated fields below and enter a valid fax number. ".($invalid_fax ? "Invalid fax(s): ".implode("; ", $invalid_fax) : NULL);
				if ($transmit_err)
					$this->content['form_return']['feedback'][] = $a."You failed to include a fax number or email address to submit your order(s) to. Please check the indicated fields below and ensure that you have included this information.";

				$this->content['submit_btn'] = "po_btn";
				return;
			}

			$sales_rep_unique_id = $this->proposals->current_proposal['sales_rep_unique_id'];
			for ($i = 0; $i < count($po_hash); $i++) {
				$po = array();
				$vendor_hash = $_POST['vendor_hash_'.$po_hash[$i]];
				$vendors->fetch_master_record($vendor_hash);
				$ship_to_hash = $_POST['ship_to_hash_'.$po_hash[$i]];
				$no_items = $_POST['no_items_'.$po_hash[$i]];
				$vendor_info = $_POST['vendor_info_'.$po_hash[$i]];
				$order_total = $_POST['order_total_'.$po_hash[$i]];
				$total_list = $_POST['total_list_'.$po_hash[$i]];
				$total_sell = $_POST['total_sell_'.$po_hash[$i]];
				$vendor_deposit = $_POST['vendor_deposit_'.$po_hash[$i]];
				$use_logo = $_POST['use_logo_'.$po_hash[$i]];
				$print_logo = $_POST['print_logo_'.$po_hash[$i]];
				$item_array = $_POST['item_'.$po_hash[$i]];
				$work_order_item_array = $_POST['work_order_item_'.$po_hash[$i]];
                $currency = $_POST['currency_'.$po_hash[$i]];

				$transmit_type = $_POST['transmit_type_'.$po_hash[$i]];
				unset($efile_obj);
				if ($transmit_type == 'E') {
					$transmit_to = $_POST['transmit_to_email_'.$po_hash[$i]];
					$transmit_to_cc = (trim($_POST['transmit_to_cc_'.$po_hash[$i]]) ? explode("\n", $_POST['transmit_to_cc_'.$po_hash[$i]]) : NULL);
				} elseif ($transmit_type == 'F')
					$transmit_to = $_POST['transmit_to_fax_'.$po_hash[$i]];
				elseif ($transmit_type == 'EE' || $transmit_type == 'ES') {
					include_once(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file']);
					$efile_class = explode(".", $vendors->current_vendor['eorder_file']);
					$efile_class_name = "eorder_".$efile_class[1];
					$efile_obj = new $efile_class_name($vendor_hash, $this->proposals->proposal_hash, $po_hash[$i]);

					if ($transmit_type == 'EE' && $efile_obj->send_method == 'EMAIL') {
						$transmit_to_cc = (trim($_POST['transmit_to_cc_'.$po_hash[$i]]) ? explode("\n", $_POST['transmit_to_cc_'.$po_hash[$i]]) : NULL);
						$transmit_to = $_POST['transmit_to_email_'.$po_hash[$i]];
					}

					$eorder_args = array();
					$eorder_args['po_date'] = date("Y-m-d");
					$einput = $_POST["einput_".$po_hash[$i]];
					reset($einput);
					while (list($field, $val) = each($einput))
						$eorder_args[$field] = $val;

					$eorder_args['total_list'] = $total_list;
					$eorder_args['total_sell'] = $total_sell;
					$eorder_args['total_cost'] = $order_total;
				}

				$rq_ship_date = $eorder_args['rq_ship_date'] = $_POST['rq_ship_date_'.$po_hash[$i]];
				$rq_arrive_date = $eorder_args['rq_arrive_date'] = ($_POST['einput_req_deliver_date_'.$po_hash[$i]] ? $_POST['einput_req_deliver_date_'.$po_hash[$i]] : $_POST['rq_arrive_date_'.$po_hash[$i]]);
				$file_vault = $_POST['file_vault_'.$po_hash[$i]];
				$po_comment = $eorder_args['po_comment'] = $_POST['po_comment_'.$po_hash[$i]];
				$po_product = $_POST['po_product_'.$po_hash[$i]];
				$ship_to_contact_name = htmlspecialchars($_POST['ship_to_contact_name_'.$po_hash[$i]], ENT_QUOTES);
				$ship_to_contact_phone = htmlspecialchars($_POST['ship_to_contact_phone_'.$po_hash[$i]], ENT_QUOTES);
				$ship_to_contact_fax = htmlspecialchars($_POST['ship_to_contact_fax_'.$po_hash[$i]], ENT_QUOTES);

				if (!$po_no)
					$po_no = fetch_sys_var('NEXT_PO_NUM');

				if (!$po_no) {
					$r = $this->db->query("SELECT `po_no`
										   FROM `purchase_order`
										   ORDER BY `po_no` DESC
										   LIMIT 1");
					if ($po_no = $this->db->result($r, 0, 'po_no'))
						$po_no++;
					else
						$po_no = 1000;
				}

                $seed = (defined('PO_SEED') ? PO_SEED : '');
				while ($this->po_exists_seed($po_no, $seed) > 0)
					$po_no++;

                $po_item_hash = md5(global_classes::get_rand_id(32, "global_classes"));
                while (global_classes::key_exists('purchase_order', 'po_hash', $po_item_hash))
                    $po_item_hash = md5(global_classes::get_rand_id(32, "global_classes"));

				$this->db->query("INSERT INTO purchase_order
								  (`timestamp` , `po_hash`, `po_no`)
								  VALUES (".time()." , '$po_item_hash', '$po_no')");
				$po_obj_id = $this->db->insert_id();

				/*
				$r = $this->db->query("SELECT COUNT(*) AS Total
									   FROM `purchase_order`
									   WHERE `po_no` = '$po_no'");
				if ($this->db->result($r, 0, 'Total')) {
					$r = $this->db->query("SELECT `po_no`
										   FROM `purchase_order`
										   ORDER BY `po_no` DESC
										   LIMIT 1");
					if ($po_no = $this->db->result($r, 0, 'po_no'))
						$po_no++;
					else
						$po_no = 1000;
				}
				*/

				update_sys_data('NEXT_PO_NUM', $po_no + 1);

				$eorder_args['po_no'] = (defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no;
				if ($efile_obj) {
					$efile_obj->build_header($eorder_args, $item_array);
					if ($transmit_type == 'EE' || $transmit_type == 'ES') {
						list($data, $format) = $efile_obj->output();
						// Some vendors, such as Haworth, require two eorders files (sif and cif).  This statement takes care of such eorder templates.

					}
					if ($transmit_type == 'ES') {
						for ($j = 0; $j < count($format); $j++) {
							$eorder_format[] = $format[$j];
							$eorder_title[] = $vendors->current_vendor['vendor_name']." : ".(defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no;
						}
					}
				} else
					unset($data, $format);

				$line_no = 0;
				for ($k = 0; $k < $no_items; $k++) {
					$line_no++;
					if ($work_order_item_array[$k]) {
						$work_order_line_update[] = $item_array[$k];
						$this->db->query("UPDATE `work_order_items`
										  SET `po_line_no` = '$line_no' , `po_hash` = '$po_item_hash' , `status` = 1
										  WHERE `item_hash` = '".$work_order_item_array[$k]."'");
						if ($this->db->db_error) {
							$line_trans_error = 1;
							break;
						}
					} else {
						$this->db->query("UPDATE `line_items`
										  SET `po_line_no` = '$line_no' , `po_hash` = '$po_item_hash' , `status` = 1
										  WHERE `item_hash` = '".$item_array[$k]."'");
						if ($this->db->db_error) {
							$line_trans_error = 1;
							break;
						}
					}
				}

				if ($line_trans_error) {
					$this->revert_po($po_item_hash);
					return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}

                if ($currency) {
                    $sys = new system_config();
                    if ($currency != $sys->home_currency['code']) {
	                    $sys->fetch_currency($currency);

	                    $currency_rate = $sys->current_currency['rate'];
	                    $currency_account = $sys->home_currency['account_hash'];
                    } else
                        unset($currency);
                }

				$this->db->query("UPDATE purchase_order
								  SET
								    last_change = '{$this->current_hash}',
								    proposal_hash = '{$this->proposals->proposal_hash}',
								    po_no = '" . ( defined('PO_SEED') ? PO_SEED : NULL ) . ( $sales_rep_unique_id ? $sales_rep_unique_id . '-' : NULL ) . $po_no . "',
								    punch = '$punch',
								    po_product = '$po_product',
								    status = '" . ( $transmit_type == 'EE' && $efile_obj->send_method == 'SOAP' ? 3 : ( $transmit_type && $transmit_type != 'ES' ? 1 : 4 ) ) . "',
								    creation_date = UNIX_TIMESTAMP(),
								    po_date = '" . date('Y-m-d') . "',
								    transmit_type = '$transmit_type',
								    transmit_to = '" . ( $efile_obj->send_method == 'SOAP' ? $efile_obj->ws_transmit_to : $transmit_to ) . "',
								    transmit_to_cc = '" . ( is_array($transmit_to_cc) ? implode("; ", $transmit_to_cc) : NULL ) . "',
								    vendor_hash = '$vendor_hash',
								    ship_to_hash = '$ship_to_hash',
								    ship_to_contact_name = '$ship_to_contact_name',
								    ship_to_contact_phone = '$ship_to_contact_phone',
								    ship_to_contact_fax = '$ship_to_contact_fax',
								    order_amount = '$order_total',
								    rq_ship_date = '$rq_ship_date',
								    rq_arrive_date = '$rq_arrive_date',
								    po_comment = '" . addslashes($po_comment) . "',
								    eorder_format = '" . implode("|", $format) . "',
								    eorder_data = '" . ( count($data) > 1 ? addslashes( serialize( $data ) ) : addslashes( $data[0] ) ) . "',
								    transmit_timestamp = " . ( $transmit_type == 'EE' && $efile_obj->send_method == 'SOAP' ? "UNIX_TIMESTAMP()" : "''" ) . ",
								    transmit_confirm = '" . ( $transmit_type == 'EE' && $efile_obj->send_method == 'SOAP' && $efile_obj->transaction_id ? $efile_obj->transaction_id : NULL ) . "'
								    " .
								    ( $currency ? ",
								        currency = '$currency',
								        exchange_rate = '$currency_rate',
								        currency_account = '$currency_account' " : NULL
								    ) . "
                                  WHERE po_hash = '$po_item_hash'");
				if ($this->db->db_error)
					return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ($transmit_type == 'E' || $transmit_type == 'F' || ($transmit_type == 'EE' && $efile_obj->send_method == 'EMAIL')) {
					$args['subject'] = "Purchase Order ".(defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no;
					$args['email_body'] = "";
					$args['type'] = $transmit_type;
					$args['po_hash'] = $po_item_hash;
					$args['proposal_hash'] = $this->proposals->proposal_hash;
					$args['recipient_list'] = $args['fax'] = $transmit_to;
					$args['to_cc'] = $transmit_to_cc;
					$args['file_vault'] = $file_vault;
					$args['date'] = date("Y-m-d");
					$args['print_logo'] = $print_logo;
					$args['use_logo'] = $use_logo;
					$args['vendor_hash'] = $vendor_hash;
					if ($transmit_type == 'EE') {
						unset($args['attached']);
						$v_name = str_replace(", ", "", $vendors->current_vendor['vendor_name']);
						$args['eorder'] = 'purchase_order|'.$po_item_hash.'|eorder_'.$v_name.'_'.(defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no.'.'.$format[0];
					} else
						$args['attached'] = 'purchase_order|'.$po_item_hash.'|Purchase_Order_'.(defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no.'.pdf';

					$send = new mail;
					$res = $send->doit_send($args);
					if ($res == false)
						$queue_err[] = (defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no."|".$send->content['form_return']['feedback'];
				} elseif ($transmit_type == 'ES') {
					for ($j = 0; $j < count($data); $j++)
						$eorder[] = $po_item_hash;
				} elseif ($transmit_type == 'EE' && $efile_obj->send_method == 'SOAP') {
					$file_name = $efile_obj->output(1, $po_no);
					$_POST['po_hash'] = $po_item_hash;
					if ($efile_obj->send_purchase_order($file_name[0][0]) === false) {
						if ($i > 0) {
							$this->jscript_action("agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposals->proposal_hash."');");
							$this->jscript_action("window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'uncheck_lines\', \'proposal_hash=".$this->proposals->proposal_hash."\', \'from=po\', \'action=new\');', 2000);");
						}
						$this->revert_po($po_item_hash);
						return $this->__trigger_error($efile_obj->ws_error_code." : ".$efile_obj->ws_error_message, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
					}
					// Add confirmation number to purchase order record if exists
					if ($efile_obj->transaction_id) {
						$this->db->query("UPDATE `purchase_order`
										  SET `transmit_confirm` = '".$efile_obj->transaction_id."'
										  WHERE po_hash = '$po_item_hash'");
					}

				} else
					$po_to_print[] = $po_item_hash;

				if ($vendors->current_vendor['deposit_percent'] && $vendor_deposit && defined('DEFAULT_WIP_ACCT')) {
					unset($_POST['action'], $_POST['final'], $_POST['account_name'], $_POST['account_hash'], $_POST['account'], $_POST['memo'], $_POST['flag']);

					$_POST['error_node'][] = $rand = rand(500, 5000000);
					$_POST['po_vendor'] = $vendors->current_vendor['vendor_name'];
					$_POST['po_vendor_hash'] = $vendor_hash;
					$_POST['proposal_hash'] = $this->proposals->proposal_hash;
					$_POST['po_hash'] = $po_item_hash;
					$_POST['po_no'] = (defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no;
					$_POST['type'] = 'D';
					$_POST['invoice_date'] = date("Y-m-d");
					$_POST['receipt_date'] = date("Y-m-d");
					$_POST['due_date'] = date("Y-m-d");
					$_POST['btn'] = 'save';
					$_POST['from'] = 1;

					$accounting = new accounting($this->current_hash);
					$accounting->fetch_account_record(DEFAULT_WIP_ACCT);
					$_POST['account_name_'.$rand] = $accounting->current_account['account_name'];
					$_POST['account_hash_'.$rand] = DEFAULT_WIP_ACCT;

                    if ($currency) {
                        $_POST['currency'] = $currency;
                        $deposit_amt = _round(bcmul($order_total, $vendors->current_vendor['deposit_percent'], 4), 2);
                        $_POST['invoice_amount'] = _round(bcmul($deposit_amt, $currency_rate, 4), 2);
                        $_POST['amount_'.$rand] = $_POST['invoice_amount'];
                    } else {
                        $_POST['invoice_amount'] = _round(bcmul($order_total, $vendors->current_vendor['deposit_percent'], 4), 2);
    					$_POST['amount_'.$rand] = $_POST['invoice_amount'];
                    }
					$_POST['memo_'.$rand] = "Vendor deposit for PO ".$_POST['po_no'];

					$payables = new payables($this->current_hash);
					$invoice_hash = $payables->doit();
					unset($_POST['temp_hash'], $rand, $_POST['error_node'], $_POST['po_vendor'], $_POST['po_vendor_hash'], $_POST['proposal_hash'], $_POST['po_hash'], $_POST['po_no'], $_POST['type'], $_POST['invoice_date'], $_POST['receipt_date'], $_POST['due_date'], $_POST['btn'], $_POST['from'], $_POST['account_name'], $_POST['account_hash'], $_POST['amount'], $_POST['memo']);

					$_POST['flag'][] = $invoice_hash;
					$payables->doit_flag_invoice();
					unset($_POST['flag']);
					unset($payables);
					$this->content['jscript'][] = "window.setTimeout('agent.call(\'payables\', \'sf_loadcontent\', \'cf_loadcontent\', \'proposal_payables\', \'proposal_hash=".$this->proposals->proposal_hash."\');', 1500);";
					$this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash=".$this->proposals->proposal_hash."\', \'otf=1\');', 3000)";
				}

				if ($transmit_type == 'ES' || $transmit_type == 'EE') {
					//Put it in the file vaule
					switch ($format) {
						case 'sif':
						case 'cif':
						$content_type == 'application/octet-stream';
						break;

						case 'xml':
						$content_type == 'text/xml';
						break;
					}
					$doc_vault_efile = true;

					for ($j = 0; $j < count($data); $j++) {
						$doc_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('doc_vault', 'doc_hash', $doc_hash))
							$doc_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$this->db->query("INSERT INTO `doc_vault`
									     (`timestamp` , `last_change` , `doc_hash` , `proposal_hash` , `file_name` , `file_type` , `content_type` , `descr` , `filesize` , `data`)
									     VALUES (".time()." , '".$this->current_hash."' , '$doc_hash' , '".$this->proposals->proposal_hash."' , 'eorder_".$v_name."_".(defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no.".".$format[$j]."' , '$format[$j]' , '$content_type' , 'Electronic purchase order (".(defined('PO_SEED') ? PO_SEED : NULL).($sales_rep_unique_id ? $sales_rep_unique_id."-" : NULL).$po_no.") generated on ".date(DATE_FORMAT." ".TIMESTAMP_FORMAT).". This E-Order file was automatically placed in the file vault.' , '".strlen($data[$j])."' , '".addslashes($data[$j])."')");
					}
				}

				$po_no++;
				$this->jscript_action("po_purge('".$po_hash[$i]."');");
			}
			if (count($work_order_line_update)) {
				$work_order_line_update = array_unique($work_order_line_update);
				for ($i = 0; $i < count($work_order_line_update); $i++)
					$this->db->query("UPDATE `line_items`
									  SET `status` = 1
									  WHERE `item_hash` = '".$work_order_line_update[$i]."'");
			}
			$result = $this->db->query("SELECT `status`
										FROM `proposals`
										WHERE `proposal_hash` = '".$this->proposals->proposal_hash."'");
			$current_status = $this->db->result($result);
			$this->db->query("UPDATE `proposals`
							  SET ".($current_status != 2 ? "`status` = 1 , `proposal_status` = 'B', `status_comment` = 'Booked as of ".date(DATE_FORMAT, time())."' , " : NULL)." `invoiced` = 0
							  WHERE `proposal_hash` = '".$this->proposals->proposal_hash."'");
			if ($queue_err) {

			}

			if ($print_logo) {
				$print_pref['print_logo'] = $print_logo;
				$print_pref['use_logo'] = $use_logo;

				store_user_data("po_print", addslashes(serialize($print_pref)));
			}
			store_user_data("po_gen", addslashes(serialize($print_pref_po_gen)));

			if ($po_to_print)
				$jscript_action = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'purchase_orders\', \'otf=1\', \'proposal_hash=".$this->proposals->proposal_hash."\', \'limit=".$limit."\');', 1000);agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'print_po', 'proposal_hash=".$this->proposals->proposal_hash."', 'po_hash=".implode("|", $po_to_print)."', 'popup_id=print_po', 'exec=1');";

			$this->content['action'] = 'close';//$next_action;
			$this->content['page_feedback'] = count($po_hash).' purchase order'.(count($po_hash) > 1 ? 's have ' : ' has ').'been created.';
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposals->proposal_hash."', 'limit=".$limit."');");
			if ($eorder)
				$this->content['jscript'][] = "agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'download_eorder', 'proposal_hash=".$this->proposals->proposal_hash."', 'eorder=".implode("|", $eorder)."', 'eorder_title=".implode("|", $eorder_title)."', 'eorder_format=".implode("|", $eorder_format)."', 'popup_id=edownload');";

			$this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'uncheck_lines\', \'proposal_hash=".$this->proposals->proposal_hash."\', \'from=po\', \'action=new\');', 2000);";
			if ($doc_vault_efile)
				$this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'doc_vault\', \'otf=1\', \'proposal_hash=".$this->proposals->proposal_hash."\');', 3000);";
			
			$this->db->query("UPDATE `commissions_paid`
					SET `paid_in_full` = 0
					WHERE `proposal_hash` = '{$this->proposals->proposal_hash}'");

			return;
		}
	}

	function work_order_po_list($order_hash) {
		$result = $this->db->query("SELECT purchase_order.po_no , purchase_order.po_hash , purchase_order.creation_date
									FROM `work_order_items`
									LEFT JOIN purchase_order ON purchase_order.po_hash = work_order_items.po_hash
									LEFT JOIN work_orders ON work_orders.order_hash = work_order_items.order_hash
									WHERE work_orders.order_hash = '$order_hash'");
		while ($row = $this->db->fetch_assoc($result)) {
			if (!@in_array($row['po_hash'], $po_hash)) {
				$po_no[] = $row['po_no'];
				$po_hash[] = $row['po_hash'];
				$po_date[] = $row['creation_date'];
			}
		}

		return array($po_hash, $po_no, $po_date);
	}

	function doit() {
		$action = $_POST['action'];
		$method = $_POST['method'];
		$jscript_action = $_POST['jscript_action'];

		if ($proposal_hash = $_POST['proposal_hash']) {
			$this->proposals = new proposals($this->current_hash);
			$valid = $this->proposals->fetch_master_record($proposal_hash);
			if (!$this->proposal_hash)
				$this->purchase_order($this->proposals->proposal_hash);
		}
		$po_hash = $_POST['po_hash'];
		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];
		$this->popup_id = $this->content['popup_controls']['popup_id'] = $_POST['popup_id'];
		$parent_popup_id = $_POST['parent_popup_id'];

		if ( $action )
			return $this->$action();

		if ( $method == 'tag' ) {

			$exec = $_POST['exec'];
			$line_items = $_POST['po_line'];
			$po_hash = $_POST['po_hash'];
			$lines = new line_items($this->proposal_hash);

			if ( ! $this->fetch_po_record($this->proposal_hash, $po_hash) )
				return $this->__trigger_error("Unable to fetch purchase order record. Tried to fetch PO [$po_hash].", E_USER_ERROR, __FILE__, __LINE__, 1);

			if ( ! $this->db->start_transaction() )
				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			if ( $exec == 'delete' ) {

				$pm = new pm_module($proposal_hash);
				if ( count($line_items) >= ( $this->current_po['total_work_order_items'] + $this->current_po['total_items'] ) )
					return $this->__trigger_error("Unable to delete all lines from a purchase order.  If you would like to replace current lines with new ones, please add the new lines first before deleting.", E_USER_NOTICE, __FILE__, __LINE__, 1);

				$total_cost = 0;

				for ( $i = 0; $i < count($line_items); $i++ ) {

					if ( $line_items[$i] ) {

						$valid = $lines->fetch_line_item_record($line_items[$i]);
						if ( ! $valid )
						    $valid = $pm->fetch_work_order_item($line_items[$i]);

						if ( ! $valid )
							return $this->__trigger_error("Unable to fetch line item record. Tried to fetch item [".$line_items[$i]."].", E_USER_ERROR, __FILE__, __LINE__, 1);

						if ( ! $lines->current_line['invoice_hash'] && ! $lines->current_line['line_item_invoice_hash'] ) {

							$update = true;
							if ( $pm->item_hash ) {

                                $total_cost = bcadd($total_cost, $pm->current_item['ext_true_cost'], 2);
                                if ( ! $this->db->query("UPDATE work_order_items t1
                                                         SET
	                                                         t1.status = 0,
	                                                         t1.po_line_no = 0,
	                                                         t1.po_hash = ''
                                                         WHERE t1.item_hash = '{$pm->item_hash}'")
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                }

                                # If this was the only work order resource on the line, unset the po flag
                                $r = $this->db->query("SELECT COUNT(*) AS Total
                                                       FROM work_order_items
                                                       WHERE `po_hash` = '{$this->po_hash}'");
                                if ( ! $this->db->result($r, 0, 'Total') ) {

                                    if ( ! $this->db->query("UPDATE line_items t1
                                                             SET
                                                                 t1.last_change = '{$this->current_hash}',
                                                                 t1.timestamp = UNIX_TIMESTAMP(),
                                                                 t1.status = 0
                                                             WHERE t1.item_hash = '{$pm->current_item['line_item_hash']}'")
                                    ) {

                                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                    }
                                }

							} else {

								$total_cost = bcadd($total_cost, $lines->current_line['ext_cost'], 2);
								if ( ! $this->db->query("UPDATE line_items t1
        											 	 SET
	        											 	 t1.last_change = '{$this->current_hash}',
	        											 	 t1.timestamp = UNIX_TIMESTAMP(),
	        											 	 t1.status = 0,
	        											 	 t1.po_line_no = 0,
	        											 	 t1.po_hash = '',
	        											 	 t1.ack_no = '',
	        											 	 t1.ship_date = NULL,
	        											 	 t1.receive_date = NULL,
	        											 	 t1.deliver_date = NULL
													  	 WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t1.item_hash = '{$lines->item_hash}'")
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                }
							}

						} else
							$err_feedback = "<img src=\"images/alert.gif\" />&nbsp;&nbsp;One or more line items have already been invoiced and therefore cannot be removed from this purchase order.<br /><br />";
					}
				}

				if ( $update ) {

					# Reindex the line numbers
					$result = $this->db->query("SELECT t1.item_hash
												FROM line_items t1
												WHERE t1.po_hash = '{$this->po_hash}'
												ORDER BY t1.po_line_no ASC");
					while ( $row = $this->db->fetch_assoc($result) )
						$reindex[] = $row['item_hash'];

					for ( $i = 0; $i < count($reindex); $i++ ) {

						if ( ! $this->db->query("UPDATE line_items t1
        										 SET t1.po_line_no = '" . ( $i + 1 ) . "'
        										 WHERE t1.item_hash = '{$reindex[$i]}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
					}

					if ( bccomp($total_cost, 0, 2) ) {

						$new_cost = bcsub($this->current_po['order_amount'], $total_cost, 2);
						if ( ! $this->db->query("UPDATE purchase_order t1
        										 SET
                                                     t1.last_change = '{$this->current_hash}',
                                                     t1.timestamp = UNIX_TIMESTAMP(),
            										 t1.order_amount = '$new_cost'
           										 WHERE t1.po_hash = '{$this->po_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
						if ($this->db->db_error)
							return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_DATABASE_ERROR, __FILE__, __LINE__, 1);
					}
                    $this->content['jscript'][] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'uncheck_lines', 'proposal_hash=".$proposal_hash."', 'from=po', 'action=rm');";
				}

			} else {
				$ship_date = $_POST['ship_date'];
				$receive_date = $_POST['receive_date'];
				$ack_no = $_POST['ack_no'];

				if ($ship_date)
					$sql[] = "`ship_date` = '$ship_date'";
				if ($receive_date)
					$sql[] = "`receive_date` = '$receive_date'";
				if ($ack_no)
					$sql[] = "`ack_no` = '$ack_no'";

				if (!$sql) {
					$this->content['action'] = 'continue';//$next_action;
					$this->content['page_feedback'] = 'No changes have been made.';

					$this->db->end_transaction();
					return;
				}
				for ($i = 0; $i < count($line_items); $i++) {
					if ($line_items[$i]) {
						$update = true;
						$this->db->query("UPDATE `line_items`
										  SET `last_change` = '".$this->current_hash."' , `timestamp` = ".time()." , ".implode(" , ", $sql)."
										  WHERE `proposal_hash` = '$proposal_hash' AND `item_hash` = '".$line_items[$i]."'");
						if ($this->db->db_error)
							return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_DATABASE_ERROR, __FILE__, __LINE__, 1);
					}
				}
			}

			if ($update) {
				if ($this->fetch_po_record($this->proposal_hash, $po_hash) === false)
					return $this->__trigger_error("Unable to fetch PO record. Tried to fetch PO [$po_hash].", E_USER_ERROR, __FILE__, __LINE__, 1);

				for ($i = 0; $i < $this->current_po['total_items']; $i++) {
					if ($lines->fetch_line_item_record($this->current_po['line_items'][$i]) === false)
						return $this->__trigger_error("Unable to fetch line item record. Tried to fetch item [".$this->current_po['line_items'][$i]."]", E_USER_ERROR, __FILE__, __LINE__, 1);

					if (!$lines->current_line['ack_no']) {
						$no_full_ack = 1;
						break;
					}
				}
				if (!$no_full_ack) {
					$this->db->query("UPDATE `purchase_order`
									  SET `fully_ack` = 1
									  WHERE `po_hash` = '$po_hash'");
					if ($this->db->db_error)
						return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_DATABASE_ERROR, __FILE__, __LINE__, 1);

					$update = true;
				} else {
					$this->db->query("UPDATE `purchase_order`
									  SET `fully_ack` = 0
									  WHERE `po_hash` = '$po_hash'");
					if ($this->db->db_error)
						return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error, E_DATABASE_ERROR, __FILE__, __LINE__, 1);

					$update = true;
				}
			}

			$this->db->end_transaction();

			$this->content['action'] = 'continue';//$next_action;
			$jscript_action = str_replace(":", "=", $jscript_action);

			$this->content['page_feedback'] = $err_feedback.($update ? 'Purchase order lines have been updated. '.(!$no_full_ack ? 'This purchase order is fully acknowledged.' : NULL) : 'No changes have been made.');
			$this->content['jscript_action'] = ($jscript_action ? stripslashes($jscript_action) : "agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'edit_po', 'proposal_hash=".$proposal_hash."', 'po_hash=".$po_hash."', 'popup_id=line_item');");

		} elseif ( $method == 'edit_line' ) {

			$item_hash = $_POST['item_hash'];
			$po_hash = $_POST['po_hash'];
            $submit_btn = 'lineItemBtn';

			$lines = new line_items($this->proposal_hash);
			if ( ! $this->fetch_po_record($this->proposals->proposal_hash, $po_hash) )
				return $this->__trigger_error("System error encountered when attempting to lookup PO record. Please reload window and try again. <!-- Tried fetching PO [ $po_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			if ( $item_hash ) {
				if ( ! $lines->fetch_line_item_record($item_hash) )
					return $this->__trigger_error("System error encountered when attempting to lookup item record. Please reload window and try again. <!-- Tried fetching item [ $item_hash  ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			}

			$item_descr = $_POST['item_descr'];
			$item_no = $_POST['item_no'];
            $item_tag1 = $_POST['item_tag1'];
            $item_tag2 = $_POST['item_tag2'];
            $item_tag3 = $_POST['item_tag3'];
			$product_hash = $_POST['item_product_hash'];
			if ( $item_hash && ! $product_hash )
				return $this->__trigger_error("In order to continue you must select a valid product/service for your line item.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

			$qty = preg_replace('/[^-.0-9]/', "", $_POST['qty']);
			$list = preg_replace('/[^-.0-9]/', "", $_POST['list']);
			$cost = preg_replace('/[^-.0-9]/', "", $_POST['cost']);
			if(!$lines->current_line['income_account_hash']){
				$sell = preg_replace('/[^-.0-9]/', "", $_POST['sell']);
			}else{
				$sell = $lines->current_line['sell'];
			}

            $gp_margin = preg_replace('/[^-.0-9]/', "", $_POST['gp_margin']);
            $list_discount = preg_replace('/[^-.0-9]/', "", $_POST['list_discount']);

			$discount1 = preg_replace('/[^.0-9]/', "", $_POST['discount1']);
            $discount2 = preg_replace('/[^.0-9]/', "", $_POST['discount2']);
            $discount3 = preg_replace('/[^.0-9]/', "", $_POST['discount3']);
            $discount4 = preg_replace('/[^.0-9]/', "", $_POST['discount4']);
            $discount5 = preg_replace('/[^.0-9]/', "", $_POST['discount5']);

			if ( ! $this->db->start_transaction() )
				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			if ( $item_hash ) {

				if ( ! $lines->fetch_line_item_record($item_hash) )
					return $this->__trigger_error("System error encountered when attempting to lookup item record. Please reload window and try again. <!-- Tried fetching item [ $item_hash  ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( $lines->current_line['direct_bill_vendor'] && $lines->current_line['direct_bill_amt'] )
					$direct_bill = true;

				$sql_up = array();
				
				$discount1 = bcmul($discount1, .01, 4);
				$discount2 = bcmul($discount2, .01, 4);
				$discount3 = bcmul($discount3, .01, 4);
				$discount4 = bcmul($discount4, .01, 4);
				$discount5 = bcmul($discount5, .01, 4);

				( bccomp($list, $lines->current_line['list'], 2) ? array_push($sql_up, "t1.list = '$list'") : NULL );
				( bccomp($qty, $lines->current_line['qty'], 2) ? array_push($sql_up, "t1.qty = '$qty'") : NULL );
                ( bccomp($cost, $lines->current_line['cost'], 2) ? array_push($sql_up, "t1.cost = '$cost'") : NULL );
                ( bccomp($sell, $lines->current_line['sell'], 2) ? array_push($sql_up, "t1.sell = '$sell'") : NULL );

                array_push($sql_up, "t1.discount1 = '$discount1'");
                array_push($sql_up, "t1.discount2 = '$discount2'");
                array_push($sql_up, "t1.discount3 = '$discount3'");
                array_push($sql_up, "t1.discount4 = '$discount4'");
                array_push($sql_up, "t1.discount5 = '$discount5'");

                ( $item_no != $lines->current_line['item_no'] ? array_push($sql_up, "t1.item_no = '$item_no'") : NULL );
                ( strlen( base64_encode($item_descr) ) != strlen( base64_encode( stripslashes($lines->current_line['item_descr']) ) ) ? array_push($sql_up, "t1.item_descr = '$item_descr'") : NULL );
                ( $item_tag1 != $lines->current_line['item_tag1'] ? array_push($sql_up, "t1.item_tag1 = '$item_tag1'") : NULL );
                ( $item_tag2 != $lines->current_line['item_tag2'] ? array_push($sql_up, "t1.item_tag2 = '$item_tag2'") : NULL );
                ( $item_tag3 != $lines->current_line['item_tag3'] ? array_push($sql_up, "t1.item_tag3 = '$item_tag3'") : NULL );

                ( $product_hash != $lines->current_line['product_hash'] ? array_push($sql_up, "t1.product_hash = '$product_hash'") : NULL );

                if ( bccomp($cost, $lines->current_line['cost'], 2) || bccomp($qty, $lines->current_line['qty'], 2) || bccomp($sell, $lines->current_line['sell'], 2) || bccomp($product_hash, $lines->current_line['product_hash'], 2) ) {

                    if ( bccomp($cost, $lines->current_line['cost'], 2) || bccomp($qty, $lines->current_line['qty'], 2) ) {

                    	$cost_change = true;

						$cost_adj = bcsub( bcmul($cost, $qty, 2), $lines->current_line['ext_cost'], 2);
						$new_po_amount = bcadd($this->current_po['order_amount'], $cost_adj, 2);
                    }

                    if ( bccomp($sell, $lines->current_line['sell'], 2) || bccomp($qty, $lines->current_line['qty'], 2) ) {

                    	$sell_change = true;

                        $sell_adj = bcsub( bcmul($sell, $qty, 2), $lines->current_line['ext_sell'], 2);
                    }

					if ( $lines->current_line['invoice_hash'] ) {

						$customer_invoice = new customer_invoice( array(
							'user_hash'       =>  $this->current_hash,
							'proposal_hash'   =>  $this->proposals->proposal_hash
						) );

						if ( ! $customer_invoice->fetch_invoice_record($lines->current_line['invoice_hash']) )
    						return $this->__trigger_error("System error encountered when attempting to lookup xyz record. Please reload window and try again. <!-- Tried fetching xyz [  ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						$accounting = new accounting($this->current_hash);

						$accounting->start_transaction( array(
    						'item_hash'       =>  $lines->item_hash,
	                        'proposal_hash'   =>  $this->proposals->proposal_hash,
	                        'ar_invoice_hash' =>  $lines->current_line['invoice_hash'],
	                        'customer_hash'   =>  ( $direct_bill ? $lines->current_line['direct_bill_vendor'] : $this->proposals->current_proposal['customer_hash'] )
                        ) );

                        $audit_id = $accounting->audit_id();

                        $vendors = new vendors($this->current_hash);

                        if ( $lines->current_line['work_order_cost_ext'] )
                            $ext_cost = $lines->current_line['work_order_cost_ext'];
                        else
                            $ext_cost = $lines->current_line['ext_cost'];

                        if ( ! $vendors->fetch_product_record($lines->current_line['product_hash']) )
                        	return $this->__trigger_error("Unable to fetch line item product/service. Please reload window and try again. <!-- Tried to fetch product [ {$lines->current_line['product_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                        # If we changed the products, make an adjusting entry
						if ( $product_hash != $lines->current_line['product_hash'] ) {

							$product_update = true;

							$tmp_vendor_obj = new vendors($this->current_hash);
							if ( ! $tmp_vendor_obj->fetch_product_record($product_hash) )
    							return $this->__trigger_error("System error encountered when attempting to lookup product record. Please reload window and try again. <!-- Tried fetching product [ $product_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

							if ( $vendors->current_product['product_income_account'] != $tmp_vendor_obj->current_product['product_income_account'] || $vendors->current_product['product_expense_account'] != $tmp_vendor_obj->current_product['product_expense_account'] ) {

								unset($tmp_vendor_obj);
								if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], $vendors->current_product['product_income_account'], bcmul($lines->current_line['ext_sell'], -1, 2), 'AD', "Purchase Order ({$this->current_po['po_no']}) product adjustment") )
									return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                            if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], $vendors->current_product['product_expense_account'], bcmul($ext_cost, -1, 2), 'AD', "Purchase Order ({$this->current_po['po_no']}) product adjustment") )
	                            	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                            if ( ! $direct_bill || ( $direct_bill && $lines->current_line['direct_bill_amt'] == 'S' ) ) {

	                                if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], DEFAULT_WIP_ACCT, $ext_cost, 'AD', "Purchase Order ({$this->current_po['po_no']}) product adjustment") )
	                                	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	                            }

	                            unset($vendors->current_product);
	                            if ( ! $vendors->fetch_product_record($product_hash) )
	                            	return $this->__trigger_error("System error when attempting to fetch item product/service. Please reload window and try again. <!-- Tried to fetch product [ $product_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                            if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], $vendors->current_product['product_income_account'], $lines->current_line['ext_sell'], 'AD', "Purchase Order ({$this->current_po['po_no']}) product adjustment") )
	                            	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                            if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], $vendors->current_product['product_expense_account'], $ext_cost, 'AD', "Purchase Order ({$this->current_po['po_no']}) product adjustment") )
	                            	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                            if ( ! $direct_bill || ( $direct_bill && $lines->current_line['direct_bill_amt'] == 'S' ) ) {

	                                if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], DEFAULT_WIP_ACCT, bcmul($ext_cost, -1, 2), 'AD', "Purchase Order ({$this->current_po['po_no']}) product adjustment") )
	                                	return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	                            }
							} else {

                                unset($vendors->current_product);
                                if ( ! $vendors->fetch_product_record($product_hash) )
                                	return $this->__trigger_error("System error encountered when attempting to fetch item product/service. Please reload window and try again. <!-- Tried to fetch product [ $product_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
							}
						}

						if ( $cost_change || $sell_change ) {

							if ( $cost_change ) {

								if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], $vendors->current_product['product_expense_account'], $cost_adj, 'AD', "Purchase Order ({$this->current_po['po_no']}) cost adjustment") )
	                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

								if ( $direct_bill && $lines->current_line['direct_bill_amt'] == 'C' && ! $sell_change ) {

									if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], DEFAULT_AR_ACCT, bcmul($cost_adj, -1, 2), 'AD', "Purchase Order ({$this->current_po['po_no']}) cost adjustment") )
		                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
								}

								if ( ! $direct_bill || ( $direct_bill && $lines->current_line['direct_bill_amt'] == 'S' ) ) {

		                            if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], DEFAULT_WIP_ACCT, bcmul($cost_adj, -1, 2), 'AD', "Purchase Order ({$this->current_po['po_no']}) cost adjustment") )
		                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
								}
							}

							if ( $sell_change ) {

                                if ( $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], $vendors->current_product['product_income_account'], $sell_adj, 'AD', "Purchase Order ({$this->current_po['po_no']}) item sell adjustment") )
                                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                                if ( $direct_bill && $lines->current_line['direct_bill_amt'] == 'C' && $cost_change )
                                    $temp_adj = bcsub($sell_adj, $cost_adj, 2);

                                if ( $accounting->exec_trans($audit_id, $customer_invoice->current_invoice['invoice_date'], DEFAULT_AR_ACCT, ( is_numeric($temp_adj) ? $temp_adj : $sell_adj ), 'AD', "Purchase Order ({$this->current_po['po_no']}) item sell adjustment") )
                                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
							}

							if ( $sell_change || ( $direct_bill && $lines->current_line['direct_bill_amt'] == 'C' && $cost_change ) ) {

								if ( $direct_bill && $lines->current_line['direct_bill_amt'] == 'C' && $cost_change && ! $sell_change )
								    $temp_adj = bcmul($cost_adj, -1, 2);

	                            $invoice_total = bcadd($invoice->current_invoice['amount'], (is_numeric($temp_adj) ? $temp_adj : $sell_adj), 2);
	                            $invoice_bal = bcadd($invoice->current_invoice['balance'], (is_numeric($temp_adj) ? $temp_adj : $sell_adj), 2);

	                            if ( ! $this->db->query("UPDATE customer_invoice t1
        	                                             SET
            	                                             t1.amount = '$invoice_total',
            	                                             t1.balance = '$invoice_bal'
        	                                             WHERE t1.invoice_hash = '{$lines->current_line['invoice_hash']}'")
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                                }
							}
						}

						$this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->proposals->proposal_hash}\', \'otf=1\');', 1500)";
					}
				}
				

				if ( $sql_up )
					$sql = "UPDATE line_items t1
							SET
								t1.last_change = '{$this->current_hash}',
								t1.timestamp = UNIX_TIMESTAMP(),
								" . implode(", ", $sql_up) . "
							WHERE t1.proposal_hash = '{$this->proposals->proposal_hash}' AND t1.item_hash = '$item_hash'";
			} else {

				$addfrom = $_POST['addfrom'];
				$new_po_amount = $this->current_po['order_amount'];

				if ( $addfrom == 'lines' ) {

					$line_items = $_POST["po_item{$this->popup_id}"];
					$line_item = array();

					for ( $i = 0; $i < count($line_items); $i++ ) {

						if ( $line_items[$i] )
							array_push($line_item, $line_items[$i]);
					}

					if ( ! $line_item )
						return $this->__trigger_error("You selected no line items! Please select at least one line to continue.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

					$result = $this->db->query("SELECT t1.po_line_no
												FROM line_items t1
												WHERE t1.po_hash = '$po_hash'
												ORDER BY t1.po_line_no DESC
												LIMIT 1");
					$last_line = $this->db->result($result);

					for ( $i = 0; $i < count($line_item); $i++ ) {

						$last_line++;
						if ( ! $lines->fetch_line_item_record($line_item[$i]) )
							return $this->__trigger_error("Unable to fetch line item record. Please reload window and try again. <!-- Tried to fetch item [ {$line_item[$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						$new_po_amount = bcadd($new_po_amount, $lines->current_line['ext_cost'], 2);
						$cost_change = true;

						if ( ! $this->db->query("UPDATE line_items t1
        										 SET
            										 t1.last_change = '{$this->current_hash}',
            										 t1.timestamp = UNIX_TIMESTAMP(),
            										 t1.po_line_no = '$last_line',
            										 t1.status = 1,
            										 t1.po_hash = '$po_hash'
        										WHERE t1.item_hash = '{$line_item[$i]}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }
					}

					$sql = "UPDATE purchase_order t1
							SET t1.fully_ack = 0
							WHERE t1.po_hash = '$po_hash'";

				} elseif ( $addfrom == 'new' ) {

					if ( $item_descr && ( strspn($cost, "-0123456789.") == strlen($cost) || ! $cost ) && ( strspn($sell, "-0123456789.") == strlen($sell) || ! $sell ) && bccomp($qty, 1, 2) != -1 ) {

						$item_hash = rand_hash('item_hash_index', 'item_hash');

						if ( ! $this->db->query("INSERT INTO `item_hash_index`
        										 VALUES('$item_hash')")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						$new_po_amount = bcadd($new_po_amount, bcmul($cost, $qty, 2), 2);
                        $cost_change = true;

						$new_item['po_hash'] = $this->po_hash;
						$new_item['status'] = 1;
						$new_item['vendor_hash'] = $this->current_po['vendor_hash'];
						$new_item['ship_to_hash'] = $this->current_po['ship_to_hash'];
						$new_item['product_hash'] = $product_hash;
						$new_item['qty'] = $qty;
						$new_item['item_no'] = $item_no;
						$new_item['item_descr'] = addslashes($item_descr);
						$new_item['gp_type'] = 'G';
						$new_item['list'] = $list;
						$new_item['cost'] = $cost;
						$new_item['sell'] = $sell;

						$result = $this->db->query("SELECT `invoice_line_no`
													FROM `line_items`
													WHERE `invoice_hash` = '{$this->invoice_hash}'
													ORDER BY `invoice_line_no` DESC
													LIMIT 1");
						$last_line = $this->db->result($result);
						$new_item['po_line_no'] = ++$last_line;

						$new_item = $this->db->prepare_query($new_item, "line_items", 'INSERT');
						if ( ! $this->db->query("INSERT INTO line_items
        										 (
	        										 timestamp,
	        										 last_change,
	        										 item_hash,
	        										 proposal_hash,
	        										 " . implode(", ", array_keys($new_item) ) . "
        										 )
        										 VALUES
        										 (
	        										 UNIX_TIMESTAMP(),
	        										 '{$this->current_hash}',
	        										 '$item_hash',
	        										 '{$this->proposal_hash}',
	        										 " . implode(", ", array_values($new_item) ) . "
        										 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						if ( ! $lines->item_tax($item_hash) )
                            return $this->__trigger_error("System error encountered when attempting to update line item sales tax. Please reload windown and try again. <!-- Tried calling line_items::item_tax [ $item_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						$sql = "UPDATE `purchase_order`
								SET `fully_ack` = 0
								WHERE `po_hash` = '$po_hash'";
					} else
						return $this->__trigger_error("In order to create a new item you must include at least a description, the product/service, a quantity, cost and sell.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
				}
			}

			if ( $sql ) {
				
				$update = true;
				if ( ! $this->db->query($sql) )
                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    
				if ( $product_update ) {

                    $r = $this->db->query("SELECT vendor_products.product_name
                                           FROM `line_items`
                                           LEFT JOIN vendor_products ON vendor_products.product_hash = line_items.product_hash
                                           WHERE line_items.po_hash = '".$this->po_hash."'
                                           GROUP BY vendor_products.product_name");
                    while ( $row = $this->db->fetch_assoc($r) )
                        $po_product_update[] = $row['product_name'];

				}

				if ( $cost_change || $po_product_update ) {

					if ( is_numeric($new_po_amount) )
						$s[] = "`order_amount` = '$new_po_amount'";
					if ( $po_product_update )
						$s[] = "`po_product` = '" . implode(", ", $po_product_update) . "'";

					if ( $s ) {

						if ( ! $this->db->query("UPDATE purchase_order
        										 SET " . implode(", ", $s) . "
        										 WHERE `po_hash` = '{$this->po_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }
					}
				}

                $this->content['jscript'][] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'uncheck_lines', 'proposal_hash=$proposal_hash', 'from=po', 'action=new');";
			}

			$this->db->end_transaction();
			

			$this->content['action'] = 'close';//$next_action;
			$this->content['page_feedback'] = ($update ? 'Purchase order item has been updated.' : 'No changes have been made.');
			$this->content['jscript_action'] = "agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'edit_po', 'proposal_hash={$this->proposals->proposal_hash}', 'po_hash=$po_hash', 'popup_id=$parent_popup_id');";

		} elseif ($method == 'delete_po') {

			if ( ! $this->fetch_po_record($this->proposals->proposal_hash, $po_hash) )
				return $this->__trigger_error("System error encountered when attempting to lookup purchase order record. Please reload window and try again. <!-- Tried fetching PO [ $po_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			if ( ! $this->db->start_transaction() )
				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			$lines = new line_items($this->proposal_hash);
			$pm = new pm_module($this->proposals->proposal_hash);

			$total_items = $this->current_po['total_items'] + $this->current_po['total_work_order_items'];
			for ( $i = 0; $i < $total_items; $i++ ) {

				if ( $lines->is_valid_line($this->current_po['line_items'][$i]) ) {

					if ( ! $this->db->query("UPDATE line_items t1
        									 SET
	        									 t1.timestamp = UNIX_TIMESTAMP(),
	        									 t1.last_change = '{$this->current_hash}',
	        									 t1.status = 0,
	        									 t1.po_line_no = 0,
	        									 t1.po_hash = '',
	        									 t1.ack_no = '',
	        									 t1.ship_date = '',
	        									 t1.receive_date = '',
	        									 t1.deliver_date = ''
        									 WHERE t1.item_hash = '{$this->current_po['line_items'][$i]}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

				} elseif ( $pm->is_valid_line($this->current_po['line_items'][$i]) ) {

					$work_order_line_item[] = $this->current_po['line_items'][$i];
					if ( ! $this->db->query("UPDATE work_order_items t1
        									 SET
	        									 t1.status = 0,
	        									 t1.po_hash = ''
        									 WHERE t1.item_hash = '{$this->current_po['line_items'][$i]}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

				} else
					return $this->__trigger_error("System error encountered when attempting to lookup purchase order line item record. Please reload window and try again. <!-- Tried fetching PO item [ {$this->current_po['line_items'][$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);
			}

			if ( $work_order_line_item ) { # If a work order, find the original line item and unset the status

				$work_order_line_item = array_unique($work_order_line_item);
				for ( $i = 0; $i < count($work_order_line_item); $i++ ) {

					if ( $pm->fetch_work_order_item($work_order_line_item[$i]) ) {

						if ( $pm->item_hash ) {

							if ( ! $this->db->query("UPDATE line_items t1
        											 SET
	        											 t1.timestamp = UNIX_TIMESTAMP(),
	        											 t1.last_change = '{$this->current_hash}',
	        											 t1.status = 0,
	        											 t1.po_hash = '',
	        											 t1.ack_no = '',
	        											 t1.ship_date = '',
	        											 t1.receive_date = '',
	        											 t1.deliver_date = ''
        											 WHERE t1.item_hash = '{$pm->current_item['line_item_hash']}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
						}

					} else
						return $this->__trigger_error("System error encountered when attempting to lookup work order line item record. Please reload window and try again. <!-- Tried fetching work order item [ {$work_order_line_item[$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);
				}
			}

			if ( ! $this->db->query("UPDATE purchase_order t1
        			                 SET
	        			                 t1.deleted = 1,
	        			                 t1.deleted_date = CURDATE()
        			                 WHERE t1.po_hash = '{$this->po_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			# Check to see if any deposits were made against this po
			$dep = $this->fetch_po_deposit($this->po_hash);

			if ( $dep ) {

				$all_accounts = array();
				$payables = new payables($this->current_hash);
				for ( $i = 0; $i < count($dep); $i++ ) {

					if ( ! $payables->fetch_invoice_record($dep[$i]) )
						return $this->__trigger_error("System error encountered when attempting to lookup payable record. Please reload window and try again. <!-- Tried fetching payable [ {$dep[$i]} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

					if ( ! $payables->current_invoice['payments'] && ! bccomp($payables->current_invoice['balance'], $payables->current_invoice['amount'], 2) ) {

						$accounting = new accounting($this->current_hash);

						$accounting->start_transaction( array(
							'ap_invoice_hash'     =>  $payables->invoice_hash,
							'vendor_hash'         =>  $payables->current_invoice['vendor_hash'],
							'proposal_hash'       =>  $this->current_po['proposal_hash']
                        ) );

						$audit_id = $accounting->audit_id();

						if ( ! $accounting->exec_trans($audit_id, $payables->current_invoice['invoice_date'], DEFAULT_AP_ACCT, bcmul($payables->current_invoice['amount'], -1, 2), 'AD', "Vendor deposit delete - {$payables->current_invoice['invoice_no']}") )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

						for ( $i = 0; $i < count($payables->current_invoice['expense_items']); $i++ ) {

							if ( ! $accounting->exec_trans($audit_id, $payables->current_invoice['invoice_date'], $payables->current_invoice['expense_items'][$i]['account_hash'], bcmul($payables->current_invoice['expense_items'][$i]['amount'], -1, 2), 'AD', "Vendor deposit delete - {$payables->current_invoice['invoice_no']}") )
                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);
						}

						if ( ! $this->db->query("UPDATE vendor_payables t1
        										 SET
        										 t1.timestamp = UNIX_TIMESTAMP(),
        										 t1.last_change = '{$this->current_hash}',
        										 t1.deleted = 1,
        										 t1.deleted_date = CURDATE()
        										 WHERE t1.invoice_hash = '{$payables->invoice_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						if ( ! $accounting->end_transaction() )
    						return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1);

					} else
						$payment_made = true;
				}
			}

			$this->db->end_transaction();

			if ( ! $this->db->query("DELETE FROM message_queue
        							 WHERE cc = '" . __CLASS__ . "' AND hash = '{$this->proposals->proposal_hash}' AND hash2 = '{$this->po_hash}'")
			) {

				$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
			}

			if ( $this->total == 1 ) {

				if ( ! $this->db->query("UPDATE proposals t1
        								 SET
	        								 t1.status = 0,
	        								 t1.proposal_status = '',
	        								 t1.status_comment = ''
        								 WHERE t1.proposal_hash = '{$this->proposals->proposal_hash}' AND t1.status = 1")
                ) {

                	$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
                }
			}

			$this->content['action'] = 'close';
			$this->content['page_feedback'] = ( $payment_made ? "Purchase order has been deleted, however a deposit was already issued and must be voided manually." : 'Your purchase order had been deleted and all items have been reverted to unordered.' );
			$this->content['jscript_action'] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash={$this->proposals->proposal_hash}');";
			$this->content['jscript'] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'uncheck_lines', 'proposal_hash={$this->proposals->proposal_hash}', 'from=po', 'action=rm');";

		} elseif ( $method == 'update_shipto' ) {

            $po_hash = $_POST['po_hash'];
            $proposal_hash = $_POST['proposal_hash'];

            $po_ship_to = $_POST['po_ship_to'];
            $po_ship_to_hash = $_POST['po_ship_to_hash'];
            if ($po_ship_to_hash) {
                $this->db->query("UPDATE purchase_order
                                  SET ship_to_hash = '$po_ship_to_hash'
                                  WHERE po_hash = '$po_hash'");

	            list($class, $id, $hash) = explode("|", $po_ship_to_hash);
	            if ($class) {
	                $obj = new $class($this->current_hash);
	                if ($hash) {

	                    if ( $loc_valid = $obj->fetch_location_record($hash) ) {

	                        $ship_to =
	                        stripslashes($obj->current_location['location_name']) . "<br />" .
	                        ( $obj->current_location['location_street'] ?
    	                        stripslashes($obj->current_location['location_street']) . "<br />" : NULL
    	                    ) .
    	                    stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

	                    } else
	                        $ship_to = "<i>Invalid Shipping Location</i>";

	                } else {

	                    $obj->fetch_master_record($id);
	                    $ship_to =
	                    stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }[strrev( substr( strrev($class), 1) ) . "_name"]) . "<br />" .
	                    ( $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["street"] ?
    	                    stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }["street"]) . "<br />" : NULL
    	                ) .
    	                stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }["city"]) . ", " . $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["state"] . " " . $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["zip"];
	                }

	                unset($obj);
	            }

                $this->content['html']['ship_to_address_holder'] = $ship_to;

            } else
                $this->__trigger_error("Invalid shipping location! The location you entered could not be found in either your customer or vendor database!", E_USER_NOTICE, __FILE__, __LINE__, 1);

            $this->content['jscript'][] = "toggle_display('po_change_ship_to', 'none');toggle_display('po_ship_to_holder', 'block');\$('po_ship_to').value='';\$('po_ship_to_hash').value='';\$('po_ship_to_info').innerHTML='';";
            $this->content['action'] = 'continue';
		}
		
		$this->db->query("UPDATE `commissions_paid`
				SET `paid_in_full` = 0
				WHERE `proposal_hash` = '{$this->proposals->proposal_hash}'");

		return;
	}

	function download_eorder() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$eorder = explode("|", $this->ajax_vars['eorder']);
		$eorder_title = explode("|", $this->ajax_vars['eorder_title']);
		$eorder_format = explode("|", $this->ajax_vars['eorder_format']);

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Save E-Orders";

		$tbl .= "
		<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;\">
			<div style=\"margin:20px 15px;\">
			    The following E-Order file(s) were created.
				<br />
				Right click on the link below, click 'Save Target As' and select a location on your computer.
				<div style=\"margin-top:15px;\">";
				for ($i = 0; $i < count($eorder); $i++) {
					$tbl .= "
					<div style=\"margin-bottom:5px;margin-left:10px;\">
						<img src=\"images/save_proposal.gif\" />
						&nbsp;
						<a href=\"download.php?ehash=".$eorder[$i]."&p=".$proposal_hash."&i=".$i."\" target=\"_blank\" class=\"link_standard\">".$eorder_title[$i].
							(count($eorder_format) > 1 ? " (".$eorder_format[$i]." file)" : NULL) ."</a>
					</div>";
				}
				$tbl .= "
				</div>
			</div>
		</div>";

		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function print_po() {
		$this->content['popup_controls']['popup_id'] = $this->popup_id = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id']);
		$proposal_hash = ($_POST['proposal_hash'] ? $_POST['proposal_hash'] : $this->ajax_vars['proposal_hash']);
		$po_hash = ($_POST['po_hash'] ? $_POST['po_hash'] : $this->ajax_vars['po_hash']);
		$exec = ($_POST['exec'] ? $_POST['exec'] : $this->ajax_vars['exec']);
		if ($print_sum = $this->ajax_vars['print_sum']) {
			$this->content['popup_controls']['popup_title'] = "Purchase Order Summary";
			$url = "t=".base64_encode('po_summary')."&h=".$proposal_hash;
		} else {
			$this->content['popup_controls']['popup_title'] = "Print Purchase Orders";
			$url = "t=".base64_encode('po')."&h=".$po_hash."&h2=".$proposal_hash;
		}


		if ($_POST['doit_print'] || $print_sum || $exec) {
			if (!$print_sum && !$exec) {
				$this->content['action'] = 'continue';

				$proposal_hash = $_POST['proposal_hash'];
				$print_pref['print_logo'] = $print_logo = $_POST['print_logo'];
				$print_pref['use_logo'] = $use_logo = $_POST['use_logo'];

				store_user_data("po_print", addslashes(serialize($print_pref)));
			}
			$tbl .= "
					<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;text-align:center;\">
				<div id=\"loading{$this->popup_id}\" style=\"width:100%;padding-top:50px;padding-bottom:45px;\">
					<h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch{$this->popup_id}\">Loading Document...</h3>
					<div id=\"link_img_holder{$this->popup_id}\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
				</div>
				<input id=\"url\" type=\"hidden\" value=\"print.php?".$url."\"/>
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

			if ($print_pref = fetch_user_data("po_print"))
				$print_pref = __unserialize(stripslashes($print_pref));

			$tbl .= $this->form->form_tag().
			$this->form->hidden(array("proposal_hash" => $proposal_hash, "po_hash" => $po_hash, "popup_id" => $this->popup_id))."
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
									".$this->form->button("value=Print Purchase Order", "onClick=submit_form(this.form, 'purchase_order', 'exec_post', 'refresh_form', 'action=print_po', 'doit_print=1');")."
								</div>
								<div style=\"margin-bottom:15px;margin-top:5px;font-weight:bold;\">Purchase Order Print Options</div>
								<div style=\"margin-left:15px;margin-bottom:15px;margin-top:15px;\">
									<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">
										<tr>
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;padding-top:10px;\">Company Logo:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">
												".$this->form->select("use_logo", $logo_out, $print_pref['use_logo'], $logo_in, "blank=1")."
												<div style=\"padding-top:5px;\">
													".$this->form->checkbox("name=print_logo", "value=1", ($print_pref['print_logo'] ? "checked" : NULL))."&nbsp;Print Logo?
												</div>
											</td>
										</tr>
									</table>
								</div>
							</td>
						</tr>
					</table>
				</div>
			</div>".$this->form->close_form();

		}
		$this->content['popup_controls']["cmdTable"] = $tbl;
		//$this->content['jscript'][] = "window.open(document.getElementById('url').value,'_blank');";
		
		//$this->content['jscript'][] = "popup_windows[document.getElementById('popup').value].hide();";
		return;
	}
	/**
	 * Manages print options for delivery ticket. Added for trac ticket #337.
	 *
	 */
	function print_tickets() {
		$this->content['popup_controls']['popup_id'] = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id']);
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Print Delivery Tickets";
		$proposal_hash = ($this->ajax_vars['proposal_hash'] ? $this->ajax_vars['proposal_hash'] : $_POST['proposal_hash']) ;
		$proj_mngr = proposals::fetch_full_name($proposal_hash, 'proj_mngr');
		$sales_coord = proposals::fetch_full_name($proposal_hash, 'sales_coord');
		$contact_name = ($proj_mngr ? $proj_mngr : $sales_coord);
		$po_hash = ($this->ajax_vars['po_hash'] ? $this->ajax_vars['po_hash'] : $_POST['po_hash']);
		$action = $this->ajax_vars['action'];

		if ($_POST['doit_print']) {
			$this->content['action'] = 'continue';
			// Added ticket comments for trac #298
			$print_pref['gen_print_fields'] = $gen_print_fields = $_POST['gen_print_fields'];
			$print_pref['item_print_fields'] = $item_print_fields = $_POST['item_print_fields'];
			$print_pref['print_logo'] = $print_logo = $_POST['print_logo'];
			$print_pref['use_logo'] = $use_logo = $_POST['use_logo'];
			$print_pref['title'] = $title = htmlspecialchars($_POST['title'], ENT_QUOTES);
			$print_pref['ship_to_title'] = $ship_to_title = htmlspecialchars($_POST['ship_to_title'], ENT_QUOTES);
			$print_pref['ticket_comments'] = $ticket_comments = htmlspecialchars($_POST['ticket_comments'], ENT_QUOTES);
			$print_pref['dealer_contact_name'] = $dealer_contact_name = htmlspecialchars($_POST['dealer_contact_name'], ENT_QUOTES);
			$template_hash = $print_pref['default_template'] = $_POST['use_template'];
			$print_pref_hash = $print_pref['default_prefs'] = $_POST['use_prefs'];
			$print_prefs_str = addslashes(serialize($print_pref));

			$template = new templates();
			$template->doit_save_prefs("delivery_ticket_print", $print_prefs_str);

			store_user_data("delivery_ticket_print", $print_prefs_str);

			if ($template->error) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = $template->error_msg;
				$this->content['form_return']['err']['saved_prefs_name'] = 1;
				$this->content['submit_btn'] = "delivery_ticket_btn";
				return;
			}
			if ($po_hash == '*') {
				$po_hash = array();
				$result = $this->db->query("SELECT `po_hash`
											FROM `purchase_order`
											WHERE `proposal_hash` = '$proposal_hash'
											AND `deleted` = 0");
				while ($row = $this->db->fetch_assoc($result))
					$po_hash[] = $row['po_hash'];
			}

			//if ($action == 'p') {
				$tbl .= "
				<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;text-align:center;\">
					<div id=\"loading{$this->popup_id}\" style=\"width:100%;padding-top:50px;padding-bottom:45px;\">
						<h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch{$this->popup_id}\">Loading Document...</h3>
						<div id=\"link_img_holder{$this->popup_id}\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
					</div>
                    <input id=\"url\" type=\"hidden\" value=\"print.php?t=".base64_encode('delivery_ticket')."&h=".(is_array($po_hash) ? implode("|", $po_hash) : $po_hash)."&h2=".$proposal_hash."&m=$template_hash&p=$print_pref_hash\"/>
                    <input id=\"popup\" type=\"hidden\" value=\"$this->popup_id\">
                </div>";

			$this->content['html']["main_table".$this->popup_id] = $tbl;
            $this->content['jscript'][] = "setTimeout(function(){window.open(document.getElementById('url').value,'_blank');popup_windows[document.getElementById('popup').value].hide();},500);";

			return;

		} else {
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
							   "item_tag"		=>	"Item Tagging",
							   "item_finish"	=>	"Item Finishes & Options",
							   "ack_no"			=>	"Acknowledgement Number",
							   "ship_date"		=>	"Ship Date",
						       "receive_date"	=>	"Receive Date",
							   "qty_rcvd"		=>	"Quantity Received");

			$gen_print_ops = array("customer_addr"		=>	"Customer",
								   "customer_contact"	=>	"Customer Contact",
								   "ship_to_addr"		=>	"Shipping Location",
								   "install_addr"		=>	"Installation Location",
								   "vendor_addr"		=>	"Vendor Address",
								   "dealer_po"			=>	"Dealer PO",
								   "customer_po"		=>	"Customer PO",
								   "proposal_no"		=>	"Proposal No",
								   "po_date"			=>	"PO Date",
								   "bldg_poc"			=>	"Bldg Mngmt POC",
								   "bldg_phone"			=>	"Bldg Mngmt Phone",
								   "bldg_fax"			=>	"Bldg Mngmt Fax",
								   "po_comments"		=>	"Purchase Order Comments");
			if ($print_pref = fetch_user_data("delivery_ticket_print")) {
				$print_pref = __unserialize(stripslashes($print_pref));
			}
			// Create default print options if user data does not exist
			if (!$print_pref) {
				$print_pref['gen_print_fields'] = array('customer_addr', 'install_addr', 'dealer_po', 'proposal_no', 'po_date');
				$print_pref['item_print_fields'] = array('product', 'item_no', 'item_descr', 'qty', 'item_tag', 'ack_no', 'ship_date', 'qty_rcvd');
				$print_pref['print_logo'] = 1;
				$print_pref['use_logo'] = $logo_out[0];
			}

			// Initially show the customer print preferences if the user has no defaults, or last time they chose custom
            if (($print_pref['default_prefs'] == "custom" && $this->p->ck("purchase_order", 'P')) || !$print_pref['default_prefs'] || $template->show_custom_prefs("delivery_ticket_print"))
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
									".$this->form->button("value=Generate Ticket", "id=delivery_ticket_btn", "onClick=submit_form(this.form, 'purchase_order', 'exec_post', 'refresh_form', 'action=print_tickets', 'doit_print=1', 'po_hash=$po_hash', 'proposal_hash=$proposal_hash');")."
								</div>
								<div style=\"margin-bottom:15px;margin-top:5px;font-weight:bold;\">Delivery Ticket Print Options</div>
								<div style=\"margin-left:15px;margin-bottom:15px;margin-top:15px;\">
									<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">".
										$template->template_select("delivery_ticket_print", 7, NULL, NULL, $this->popup_id, $proposal_hash, $po_hash)."
										<tr id=\"print_pref1\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
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
												".$this->form->select("gen_print_fields[]", array_values($gen_print_ops), $print_pref['gen_print_fields'], array_keys($gen_print_ops), "blank=1", "multiple", "style=width:200px;height:100px")."
											</td>
										</tr>
										<tr id=\"print_pref3\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">
												Line Item Print Fields:
												<div style=\"padding-top:5px;font-style:italic;\"><small>hold cntrl key for multiple</small></div>
											</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->select("item_print_fields[]", array_values($print_ops), $print_pref['item_print_fields'], array_keys($print_ops), "blank=1", "multiple", "style=width:200px;height:100px")."
											</td>
										</tr>
										<tr id=\"print_pref4\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Document Title:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=title",
																		"value=".($print_pref['title'] ? $print_pref['title'] : "Delivery Ticket"),
																		"maxlength=36",
																		"style=width:200px")."
											</td>
										</tr>
										<tr id=\"print_pref5\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Shipping Location Title:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=ship_to_title",
																		"value=".($print_pref['ship_to_title'] ? $print_pref['ship_to_title'] : "Shipping Location"),
																		"maxlength=36",
																		"style=width:200px")."
											</td>
										</tr>
										<tr id=\"print_pref6\"".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Dealer Contact:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=dealer_contact_name",
																		"value=".($contact_name ? $contact_name : $print_pref['dealer_contact_name']),
																		"maxlength=64",
																		"style=width:200px")."
											</td>
										</tr>
										<tr id=\"print_pref7\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Ticket Comments:</td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_area("name=ticket_comments",
																		 "value=".$print_pref['ticket_comments'],
																		 "rows=4",
																		 "cols=52")."
											</td>
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

	function fetch_ack_no($po_hash) {
		$result = $this->db->query("SELECT line_items.ack_no
									FROM `purchase_order`
									LEFT JOIN line_items ON line_items.po_hash = purchase_order.po_hash
									WHERE purchase_order.po_hash = '$po_hash'
									GROUP BY line_items.ack_no");
		while ($row = $this->db->fetch_assoc($result)) {
			if ($row['ack_no'])
				$ack_no[] = $row['ack_no'];
		}
		return $ack_no;
	}

	function fetch_ship_dates($po_hash) {
		$result = $this->db->query("SELECT line_items.ship_date
									FROM `purchase_order`
									LEFT JOIN line_items ON line_items.po_hash = purchase_order.po_hash
									WHERE purchase_order.po_hash = '$po_hash'
									GROUP BY line_items.ship_date");
		while ($row = $this->db->fetch_assoc($result)) {
			if ($row['ship_date'] && $row['ship_date'] != "0000-00-00")
				$ship_date[] = date(DATE_FORMAT, strtotime($row['ship_date']));
		}
		return $ship_date;
	}

	function fetch_receive_dates($po_hash) {
		$result = $this->db->query("SELECT line_items.receive_date
									FROM `purchase_order`
									LEFT JOIN line_items ON line_items.po_hash = purchase_order.po_hash
									WHERE purchase_order.po_hash = '$po_hash'
									GROUP BY line_items.receive_date");
		while ($row = $this->db->fetch_assoc($result)) {
			if ($row['receive_date'] && $row['receive_date'] != "0000-00-00")
				$receive_date[] = date(DATE_FORMAT, strtotime($row['receive_date']));
		}
		return $receive_date;
	}

	function edit_po() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->proposals = new proposals($this->current_hash);
		$valid1 = $this->proposals->fetch_master_record($this->ajax_vars['proposal_hash']);
		$valid2 = $this->fetch_po_record($this->ajax_vars['proposal_hash'], $this->ajax_vars['po_hash'], 1);

		$this->content['popup_controls']['popup_title'] = "Purchase Order Summary";

		if (!$valid1 || !$valid2)
			return $this->error_template("The PO record cannot be retrieved. The PO may have been recently deleted or the required link could not be found.");

		$pm = new pm_module($this->current_hash);
		$tt = array("E" 	=>  "Email",
					"F" 	=>  "Fax",
					"EE"	=>	"E-Order",
					"ES"	=>	"N/A",
					""		=>	"Mail");

		$ack_no = $this->fetch_ack_no($this->po_hash);

		//Line item table first
		$lines = new line_items($this->proposal_hash);
		$pm = new pm_module($this->proposal_hash);
		$total_items = $this->current_po['total_items'] + $this->current_po['total_work_order_items'];

        if ($this->current_po['currency'])
            $sys = new system_config();

		for ($i = 0; $i < $total_items; $i++) {
			if ($valid = $lines->fetch_line_item_record($this->current_po['line_items'][$i])) {
				$work_order = false;
				$qty = $lines->current_line['qty'];
				$cost = $lines->current_line['cost'];
				$ext_cost = $lines->current_line['ext_cost'];
				$sell = $lines->current_line['sell'];
				$ext_sell = $lines->current_line['ext_sell'];
				$item_product = $lines->current_line['item_product'];
				$item_descr = $lines->current_line['item_descr'];
				$item_no = (strlen($lines->current_line['item_no']) > 25 ? wordwrap($lines->current_line['item_no'], 25, "<br />\n", 1) : $lines->current_line['item_no']);
				$line_no = $lines->current_line['po_line_no'];
				if ($lines->current_line['invoice_hash'])
					$invoiced_line = true;
			} else {
				$pm->fetch_work_order_item($this->current_po['line_items'][$i]);
				$work_order = true;
				$qty = $pm->current_item['time'].($pm->current_item['units'] != 'F' ? " ".$pm->units[$pm->current_item['units']].($pm->current_item['time'] > 1 ? "s" : NULL) : NULL);
				$cost = $pm->current_item['true_cost'];
				$ext_cost = $pm->current_item['ext_true_cost'];
				$item_product = $pm->current_item['item_product'];
				$item_descr = $pm->current_item['item_descr'];
				$item_no = $pm->current_item['item_no'];
				$line_no = $pm->current_item['po_line_no'];

				if ($pm->current_item['line_item_invoice_hash'])
					$invoiced_line = true;
			}
			// Create the eorder array if necessary
			$eorder_format = explode("|", $this->current_po['eorder_format']);
			unset($eorder);
			for($j = 0; $j < count($eorder_format); $j++) {
				$eorder[] = $this->current_po['po_hash'];
			}
			if (count($eorder) > 0) {
				$vendors = new vendors($_SESSION['id_hash']);
				$vendors->fetch_master_record($this->current_po['vendor_hash']);
				if ($vendors->current_vendor['eorder_file']) {
					list($fname, $vendor) = explode(".", $vendors->current_vendor['eorder_file']);
					$fname = "eorder_".$vendor;
				}
				for ($k = 0; $k < count($eorder); $k++) {
					$eorder_title[] = $fname."_".$this->current_po['po_no'].".".$eorder_format[$k];
				}
			}

			$item_table .= "
			<tr style=\"background-color:#efefef;\">
				<td class=\"smallfont\" colspan=\"3\" style=\"font-weight:bold;\">
					".$this->form->checkbox("name=po_line[]", "value=".$this->current_po['line_items'][$i])."
					&nbsp;".(strlen($item_descr) > 65 ?
                        "<span title=\"".htmlspecialchars($item_descr)."\">".stripslashes(substr($item_descr, 0, 60))."...</span>" : stripslashes($item_descr))."
					&nbsp;&nbsp;
					<span style=\"font-weight:normal;\">".($this->p->ck(get_class($this), 'E') ? "
						[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_item', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".($work_order ? $pm->current_item['line_item_hash'] : $this->current_po['line_items'][$i])."', 'po_hash=".$this->po_hash."', 'parent_popup_id={$this->popup_id}', 'popup_id=line_item_detail', 'wo=$work_order', 'from_edit=po', 'row_lock=".$this->lock."');\" class=\"link_standard\">edit</a></small>]".($work_order ? "
						    &nbsp;&nbsp;
						    [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'show_popup_window', 'resource_input', 'item_hash=".$this->current_po['line_items'][$i]."', 'order_hash=".$pm->current_item['order_hash']."', 'action=add', 'popup_id=work_order_item', 'new_win=1', 'po_win={$this->popup_id}')\" class=\"link_standard\" title=\"Edit work order resource\">edit resource</a></small>]" : NULL)."
					</span>" : NULL)."
				</td>
			</tr>
			<tr style=\"background-color:#efefef;\">
				<td class=\"smallfont\" colspan=\"3\" style=\"padding-bottom:0;padding-top:0;\">
					Line: ".$line_no."
					<span style=\"font-style:italic;padding-left:5px;\">
						".$item_product.($work_order ? "
						<small>(Work Order Resource)</small>" : NULL)."
					</span>
				</td>
			</tr>
			<tr style=\"background-color:#efefef;\">
				<td class=\"smallfont\" style=\"vertical-align:bottom;\">
					<div>Qty: ".$qty."</div>
					<div style=\"padding-top:5px;\">Item Cost: ".($cost < 0 ?
					   "($".number_format($cost * -1, 2).")" : "$".number_format($cost, 2))."</div>
					<div style=\"padding-top:5px;\">Ext Cost: ".($ext_cost < 0 ?
					   "($".number_format($ext_cost * -1, 2).")" : "$".number_format($ext_cost, 2))."</div>
				</td>
				<td class=\"smallfont\" style=\"vertical-align:".(!$work_order ? "bottom" : "top").";\">
					<div>Item No: ".$item_no."</div>".(!$work_order ? "
					<div style=\"padding-top:5px;\">Item Sell: ".($lines->current_line['sell'] < 0 ?
					   "($".number_format($lines->current_line['sell'] * -1, 2).")" : "$".number_format($lines->current_line['sell'], 2))."</div>
					<div style=\"padding-top:5px;\">Ext Sell: ".($lines->current_line['ext_sell'] < 0 ?
					   "($".number_format($lines->current_line['ext_sell'] * -1, 2).")" : "$".number_format($lines->current_line['ext_sell'], 2))."</div>" : NULL)."
				</td>
				<td class=\"smallfont\" style=\"vertical-align:bottom;\">".(!$work_order ? "
					<div>Ack No: ".$lines->current_line['ack_no']."</div>
					<div style=\"padding-top:5px;\">Ship Date: ".($lines->current_line['ship_date'] && $lines->current_line['ship_date'] != '0000-00-00' ? date(DATE_FORMAT, strtotime($lines->current_line['ship_date'])) : NULL)."</div>
					<div style=\"padding-top:5px;\">Receive Date: ".($lines->current_line['receive_date'] && $lines->current_line['receive_date'] != '0000-00-00' ? date(DATE_FORMAT, strtotime($lines->current_line['receive_date'])) : NULL)."</div>" : NULL)."
				</td>
			</tr>".($i < $total_items - 1 ? "
			<tr>
				<td colspan=\"3\" style=\"background-color:#cccccc;\"></td>
			</tr>" : NULL);
		}

		//See if any vendor deposits were made for this PO
		if ($dep = $this->fetch_po_deposit($this->po_hash)) {
			$payables = new payables($this->current_hash);
			for ($i = 0; $i < count($dep); $i++) {
				$payables->fetch_invoice_record($dep[$i]);
				$total_dep_made += $payables->current_invoice['amount'];
			}
			$vendor_dep_tbl = "
			<div style=\"margin-top:5px;\">Vendor deposits totaling $".number_format($total_dep_made, 2)." were made toward this PO.</div>";
		}

		// Find obj_id of record in message queue for this purchase order if transmission is pending.  Added for Trac #34.
		if ($this->current_po['status'] == 1) {
			$r = $this->db->query("SELECT * FROM `message_queue`
										WHERE `hash2` = '".$this->po_hash."' AND `vendor_hash` = '".$this->current_po['vendor_hash']."'");
			$message_id = $this->db->result($r, 0, 'obj_id');
		}

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("po_hash" => $this->po_hash,
								  "proposal_hash" => $this->proposal_hash,
								  "popup_id" => $this->popup_id,
		                          'form_placeholder'    =>  1))."
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
									Purchase Order : ".$this->current_po['po_no'].($this->current_po['fully_ack'] || count($ack_no) ? "
										<span style=\"font-weight:normal;font-size:9pt;font-style:italic;padding-left:10px;\">".($this->current_po['fully_ack'] ?
											"Fully Acknowledged" : "Partially Acknowledged").($ack_no ?
												" <small title=\"Ack Number(s)\">(".@implode(", ", $ack_no).")</small>" : NULL)."</span>" : NULL)."
                                    <div style=\"margin-left:25px;margin-top:5px;font-size:9pt;font-style:italic\">
                                        ".stripslashes($this->current_po['po_product'])."
                                    </div>
								</h3>
								<div style=\"padding:5px 0 5px 5px;\">
									<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'print_po', 'proposal_hash=".$this->proposal_hash."', 'po_hash=".$this->po_hash."', 'popup_id=print_po');\" class=\"link_standard\"><img src=\"images/print.gif\" title=\"Print this purchase order.\" border=\"0\" /></a>
									<!--".($this->current_po['status'] != 1 && $this->current_po['status'] != 2 ? "&nbsp;
									<a href=\"javascript:void(0);\" onClick=\"if(confirm('This purchase order was already sent to ".$this->current_po['vendor_name'].". Are you sure you want to re-send?')){".($this->current_po['status'] != 4 ? "if(confirm('When you re-send this PO, do you want to mark it as a duplicate? If so, click OK, otherwise click CANCEL.')){}" : NULL)."}\" class=\"link_standard\"><img src=\"images/resend.gif\" title=\"Re-send this purchase order to ".$this->current_po['vendor_name'].".\" border=\"0\" /></a>" : NULL)."
									-->&nbsp;".($this->p->ck(get_class($this), 'D') ?
									($invoiced_line || $this->current_po['total_payables'] ? "
									<img src=\"images/kill_disabled.gif\" title=\"This PO cannot be deleted because ".($invoiced_line ? "one or more of its line items have already been invoiced." : "payables have already been received against it.")."\" />&nbsp;" : "
									<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to kill and delete this purchase order? All items will be reverted to un-sold and the PO will be deleted. This action CAN NOT be undone!')){submit_form(\$('form_placeholder').form, 'purchase_order', 'exec_post', 'refresh_form', 'method=delete_po');}\" class=\"link_standard\"><img src=\"images/kill.gif\" title=\"Kill & Delete this purchase order.\" border=\"0\" /></a>
									&nbsp;") : NULL)."
									<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'print_tickets', 'proposal_hash=".$this->proposal_hash."', 'po_hash=".$this->po_hash."', 'popup_id=print_po');\" class=\"link_standard\"><img src=\"images/print_delivery_ticket.gif\" title=\"Print delivery tickets for this purchase order.\" border=\"0\" /></a>
									&nbsp;
									<a href=\"javascript:void(0);\" onClick=\"agent.call('mail', 'sf_loadcontent', 'show_popup_window', 'agent', 'proposal_hash=".$this->proposal_hash."', 'po_hash=".$this->po_hash."', 'popup_id=line_item2', 'cc=purchase_order');\" class=\"link_standard\"><img src=\"images/send.gif\" title=\"Open the communication terminal.\" border=\"0\" /></a>
								</div>
								<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:90%;\">
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Customer:</td>
										<td style=\"background-color:#ffffff;\">".stripslashes($this->proposals->current_proposal['customer'])."</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Sales Rep:</td>
										<td style=\"background-color:#ffffff;\">".stripslashes($this->proposals->current_proposal['sales_rep'])."</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Vendor:</td>
										<td style=\"background-color:#ffffff;\">".$this->current_po['vendor_name']."</td>
                                    </tr>".($this->current_po['currency'] ? "
                                    <tr>
                                        <td style=\"background-color:#efefef;text-align:right;font-weight:bold;\">Currency: </td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->current_po['currency']."
                                            <span style=\"padding-left:5px;font-style:italic;\">Amounts shown in ".$sys->home_currency['code']."</span>
                                        </td>
                                    </tr>".($this->current_po['currency'] != $sys->home_currency['code'] ? "
                                    <tr>
                                        <td style=\"background-color:#efefef;text-align:right;font-weight:bold;\">Rate: </td>
                                        <td style=\"background-color:#ffffff;\">".rtrim(trim($this->current_po['exchange_rate'], '0'), '.')."</td>
                                    </tr>" : NULL) : NULL)."
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Purchased On:</td>
										<td style=\"background-color:#ffffff;\">".date(DATE_FORMAT, strtotime($this->current_po['po_date']))."</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;\">Order Amount:</td>
										<td style=\"background-color:#ffffff;\">".($this->current_po['order_amount'] < 0 ?
											"($".number_format($this->current_po['order_amount'] * -1, 2).")" : "$".number_format($this->current_po['order_amount'], 2)).($vendor_dep_tbl ?
												$vendor_dep_tbl : NULL)."
										</td>
									</tr>
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Total Sell:</td>
										<td style=\"background-color:#ffffff;\">".($this->current_po['total_work_order_items'] ?
											"<small><i>This PO is split against a work order.</i></small>" : ($this->current_po['total_sell'] < 0 ?
    											"($".number_format($this->current_po['total_sell'] * -1, 2).")" : "$".number_format($this->current_po['total_sell'], 2)))."
										</td>
									</tr>".(!$this->current_po['total_work_order_items'] ? "
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Total List:</td>
										<td style=\"background-color:#ffffff;\">".($this->current_po['total_list'] < 0 ?
                                            "($".number_format($this->current_po['total_list'] * -1, 2).")" : "$".number_format($this->current_po['total_list'], 2))."
                                        </td>
									</tr>" : NULL).($this->current_po['rq_ship_date'] != "0000-00-00" ? "
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Req Ship Date:</td>
										<td style=\"background-color:#ffffff;\">".date(DATE_FORMAT, strtotime($this->current_po['rq_ship_date']))."</td>
									</tr>" : NULL).($this->current_po['rq_arrive_date'] != "0000-00-00" ? "
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;\">Req Arrival Date:</td>
										<td style=\"background-color:#ffffff;\">".date(DATE_FORMAT, strtotime($this->current_po['rq_arrive_date']))."</td>
									</tr>" : NULL)."
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;\">Shipping To:</td>
										<td style=\"background-color:#ffffff;\">
                                            <div style=\"display:block\" id=\"po_ship_to_holder\">
	    										<span id=\"ship_to_address_holder\">".stripslashes($this->current_po['ship_to'])."</span>".($this->p->ck(get_class($this), 'E') ?
	        										"<span style=\"margin-left:15px;\">
	                                                    [<a href=\"javascript:void(0);\" onClick=\"toggle_display('po_change_ship_to', 'block');toggle_display('po_ship_to_holder', 'none');\$('po_ship_to').focus();\" class=\"link_standard\">change</a>]
	        										</span>" : NULL)."
                                            </div>
                                            <div style=\"display:none;\" id=\"po_change_ship_to\">
		                                        ".$this->form->text_box("name=po_ship_to",
		                                                                "autocomplete=off",
												                        "size=20",
					                                                    "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'po_ship_to', 'po_ship_to_hash', 1);}",
					                                                    "onKeyUp=if(ka==false && this.value){key_call('proposals', 'po_ship_to', 'po_ship_to_hash');}",
					                                                    "onBlur=key_clear();",
		                                                                "onKeyDown=if(event.keyCode!=9){clear_values('po_ship_to_hash');clear_innerHTML('po_ship_to_info');}")."
                                                <span style=\"margin-left:15px;\">
                                                    [<a href=\"javascript:void(0);\" onClick=\"vtobj.reset_keyResults();key_clear();toggle_display('po_change_ship_to', 'none');toggle_display('po_ship_to_holder', 'block');\$('po_ship_to').value='';\$('po_ship_to_info').innerHTML='';\" class=\"link_standard\">cancel</a>]
                                                </span>".
		                                        $this->form->hidden(array("po_ship_to_hash" => ''))."
		                                        <div id=\"po_ship_to_info\" style=\"padding-top:5px\"></div>
                                            </div>
    									</td>
									</tr>".($this->current_po['transmit_type'] ? "
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;\">Sent By:</td>
										<td style=\"background-color:#ffffff;vertical-align:bottom;\">".$tt[$this->current_po['transmit_type']].($this->current_po['status'] == 0 || $this->current_po['status'] == 3 ?
												" on ".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $this->current_po['transmit_timestamp']) : NULL)."<br>".
										   ($this->current_po['transmit_type'] == 'EE' || $this->current_po['transmit_type'] == 'ES' ? "<div style=\"padding-top:5px;padding-bottom:5px;font-style:italic;\">An E-Order file has been created</div>
												&nbsp;[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'download_eorder', 'proposal_hash=".$this->proposals->proposal_hash."', 'eorder=".implode("|", $eorder)."', 'eorder_title=".implode("|", $eorder_title)."', 'eorder_format=".$this->current_po['eorder_format']."', 'popup_id=edownload');\"class=\"link_standard\">View E-Order</a></small>]" : NULL).
											($this->current_po['transmit_type'] != 'ES' ? "
											<div style=\"padding-top:5px;\">
												".$this->po_icons[$this->current_po['status']]."
												&nbsp;
												<i><small>(".$this->po_status[$this->current_po['status']].($this->current_po['transmit_timestamp'] ? " on ".date(DATE_FORMAT, $this->current_po['transmit_timestamp'])." at ".date(TIMESTAMP_FORMAT, $this->current_po['transmit_timestamp']) : NULL).")</small></i>".
											($message_id ? "&nbsp;<a href=\"javascript:void(0);\" onClick=\"if (confirm('Are you sure you want to remove the purchase order from the outgoing message queue? This action CAN NOT be undone!')){submit_form(\$('po_hash').form, 'mail', 'exec_post', 'refresh_form', 'action=doit_rm', 'id=".$message_id."', 'kill_po=1', 'proposal_hash=".$this->proposal_hash."', 'po_hash=".$this->po_hash."', 'popup_id={$this->popup_id}');}\"><img src=\"images/void_check.gif\" title=\"Remove from queue\" border=\"0\" /></a>" : NULL)."
											</div> " : NULL)."
										</td>
									</tr>".($this->current_po['transmit_type'] != 'ES' ? "
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;\">Sent To:</td>
										<td style=\"background-color:#ffffff;vertical-align:bottom;\">".$this->current_po['transmit_to']."</td>
									</tr>" : NULL).($this->current_po['transmit_confirm'] ? "
									<tr>
										<td style=\"background-color:#efefef;text-align:right;width:125px;font-weight:bold;vertical-align:top;\">Confirmation No:</td>
										<td style=\"background-color:#ffffff;vertical-align:bottom;\">".$this->current_po['transmit_confirm']."</td>
									</tr>" : NULL) : NULL).
									//Purchase Order Notes input box (ticket #15)
									"
									<tr>
						<td style=\"background-color:#ffffff;font-weight:bold;padding-left:30px;padding-bottom:10px;\" colspan=\"2\">
							<div style=\"padding-bottom:5px;\" id=\"err3_14".$this->popup_id."\">Purchase Order Notes:</span>";
									if ($this->proposal_hash) {
										$tbl .= "
								<div style=\"margin-left:10px;padding-bottom:5px;\">
									[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'note_type=o', 'proposal_hash=".$this->proposal_hash."', 'type_hash=".$this->po_hash."', 'popup_id=note_win')\">add a note</a></small>]
								</div>
								<div style=\"padding-top:5px;height:75px;width:90%;overflow:auto;border:1px outset #cccccc;background-color:#efefef;\" id=\"o_note_holder\">";
										$notes = $this->proposals->fetch_proposal_notes($this->proposal_hash, 'o', $this->po_hash);
									
										for ($i = 0; $i < count($notes); $i++)
											$tbl .= "
											<div style=\"padding-left:5px;padding-bottom:5px;\">
										<strong>".$notes[$i]['full_name']." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $notes[$i]['timestamp']).")</strong> ".($notes[$i]['user_hash'] == $this->current_hash ? "
																			[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'note_type=o', 'proposal_hash=".$this->proposal_hash."', 'popup_id=note_win', 'note_hash=".$notes[$i]['note_hash']."', 'type_hash=".$this->po_hash."')\">edit</a></small>]" : NULL)." : ".stripslashes($notes[$i]['note'])."
																			</div>";
								$tbl .= "
										</div>";
									
							} else
												$tbl .= "
												<div style=\"padding-top:5px;\">
								".$this->form->text_area("name=po_notes",
												"value=",
												"cols=100",
														"rows=4")."
														</div>";
																	
														$tbl .= "
																</div>
																</td>
								</table>
								<div style=\"padding-top:10px;font-weight:bold;\" >
									Item Summary:
									<div id=\"summary{$this->popup_id}\"></div>
								</div>
								<div style=\"padding:5px 0 5px 5px;\">".($this->p->ck(get_class($this), 'E') && (!$invoiced_line || ($invoiced_line && (!defined('PO_INVOICE_EDIT') || PO_INVOICE_EDIT == 0))) ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'edit_item', 'proposal_hash=".$this->proposal_hash."', 'po_hash=".$this->po_hash."', 'parent_popup_id={$this->popup_id}', 'popup_id=line_item_detail');\" class=\"link_standard\"><img src=\"images/plus.gif\" title=\"Add a line item to this purchase order.\" border=\"0\" /></a>
									&nbsp;
									<a href=\"javascript:void(0);\" onClick=\"if (confirm('Are you sure you want to delete the selected line item(s)? These items have already been ordered and submitted to this vendor. This action CAN NOT be undone!')){submit_form($('ack_no').form, 'purchase_order', 'exec_post', 'refresh_form', 'method=tag', 'exec=delete');}\" class=\"link_standard\"><img src=\"images/rm_lineitem.gif\" title=\"Delete selected line items\" border=\"0\" /></a>
									&nbsp;" : NULL);
								if ($this->p->ck(get_class($this), 'U')) {
									$result = $this->db->query("SELECT `eorder_file`
																FROM `vendors`
																WHERE `vendor_hash` = '".$this->current_po['vendor_hash']."'");
									$eorder_file_info = $this->db->fetch_assoc($result);
									$eorder_file = $eorder_file_info['eorder_file'];

									if ($eorder_file && file_exists(SITE_ROOT.'core/images/e_profile/'.$eorder_file)) {
										include_once(SITE_ROOT.'core/images/e_profile/'.$eorder_file);
										$efile_class = explode(".", $eorder_file);
										$efile_class_name = "eorder_".$efile_class[1];

										$efile_obj = new $efile_class_name($this->current_po['vendor_hash'], $this->current_po['proposal_hash']);
										if (method_exists($efile_obj, "e_ack"))
											$e_ack = "&nbsp;<a href=\"javascript:void(0);\" onClick=\"\" class=\"link_standard\"><img src=\"images/e_ack.gif\" title=\"Compare this PO against an electronic acknowlegement.\" border=\"0\" /></a>";
									}
									$tbl .= "
									<a href=\"javascript:void(0);\" onClick=\"position_element($('summary{$this->popup_id}'), findPos($('summary{$this->popup_id}'), 'top')+25, findPos($('summary{$this->popup_id}'), 'left'));toggle_display('poList', 'block');focus_to('ack_no');\" class=\"link_standard\"><img src=\"images/edit_po_item.gif\" title=\"Update acknowledgement numbers, shipping and receiving dates on selected items.\" border=\"0\" /></a>".($e_ack ?
									"&nbsp;".$e_ack : NULL)."
									<div id=\"poList\" class=\"function_menu\" style=\"width:275px;\">
										<div style=\"float:right;padding:3px\">
											<a href=\"javascript:void(0);\" onClick=\"toggle_display('poList', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
										</div>
										<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Enter Item Information Below:</div>
										<div class=\"function_menu_item\">
											<table style=\"width:100%;\" cellpadding=\"0\" cellspacing=\"0\">
												<tr>
													<td style=\"text-align:right;width:35%;padding-right:5px;\">Ack No:</td>
													<td>".$this->form->text_box("name=ack_no", "value=", "size=25")."</td>
												</tr>
											</table>
										</div>
										<div class=\"function_menu_item\">
											<table style=\"width:100%;\" cellpadding=\"0\" cellspacing=\"0\">
												<tr>
													<td style=\"text-align:right;width:35%;padding-right:5px;\">Ship Date: </td>
													<td id=\"po_ship_date{$this->popup_id}\">";
													$this->content['jscript'][] = "setTimeout('DateInput(\'ship_date\', false, \'YYYY-MM-DD\', \'\', 1, \'po_ship_date{$this->popup_id}\')', 19);";
													$tbl .= "
													</td>
												</tr>
											</table>
										</div>
										<div class=\"function_menu_item\">
											<table style=\"width:100%;\" cellpadding=\"0\" cellspacing=\"0\">
												<tr>
													<td style=\"text-align:right;width:35%;padding-right:5px;\">Receive Date: </td>
													<td id=\"po_receive_date{$this->popup_id}\">";
													$this->content['jscript'][] = "setTimeout('DateInput(\'receive_date\', false, \'YYYY-MM-DD\', \'\', 1, \'po_receive_date{$this->popup_id}\')', 19);";
													$tbl .= "
													</td>
												</tr>
											</table>
										</div>
										<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">
    										".$this->form->button("value=Save",
                        										  "onclick=submit_form(\$('form_placeholder').form, 'purchase_order', 'exec_post', 'refresh_form', 'method=tag');window.setTimeout(function(){toggle_display('poList', 'none');}, 500);")."
        								</div>
									</div>
									&nbsp;&nbsp;";
								}
								$tbl .= "
									[<small><a href=\"javascript:void(0);\" onClick=\"checkall(document.getElementsByName('po_line[]'), this.checked);\" class=\"link_standard\">check all</a></small>]
								</div>
								<div style=\"padding-top:0;height:200px;overflow-y:auto;overflow-x:hidden;\">
									<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:97%;\" >
										".$item_table."
									</table>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']['onclose'] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposals->proposal_hash."');";
 		if ($work_order)
			$this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'line_items\', \'proposal_hash=".$this->proposals->proposal_hash."\', \'otf=1\');', 3000)";
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function edit_item() {

		$this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = ( $item_hash ? "Edit Purchase Order Line Item" : "Add Line Item To This Purchase Order" );

		if ( ! $this->ajax_vars['proposal_hash'] || ! $this->ajax_vars['po_hash'] )
			return $this->__trigger_error("System error encountered during purchase order line item edit/add. Please reload window and try again. <!-- Ajax request missing popup identifier -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$proposals = new proposals($this->current_hash);
        $parent_popup_id = $this->ajax_vars['parent_popup_id'];
		$po_hash = $this->ajax_vars['po_hash'];
		$this->fetch_po_record($proposal_hash, $po_hash);
		$wo_item = $this->ajax_vars['wo'];
		$item_hash = $this->ajax_vars['item_hash'];

		if ( $item_hash ) {

			if ( $wo_item ) {

				$pm = new pm_module($this->proposal_hash);
				if ( ! $pm->fetch_work_order_item($item_hash) )
	    			return $this->__trigger_error("System error encountered when attempting to lookup purchase order line item record. Please reload window and try again. <!-- Tried fetching work order item [ $item_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				$work_order = true;
				$qty = $pm->current_item['time'];
				$unit = $pm->current_item['units'];
				$cost = $pm->current_item['true_cost'];
				$ext_cost = $pm->current_item['ext_true_cost'];
				$sell = $pm->current_item['item_sell'];
				$item_product = $pm->current_item['item_product'];
				$item_product_hash = $pm->current_line['product_hash'];
				$item_descr = $pm->current_item['item_descr'];
				$item_no = $pm->current_item['item_no'];
				$line_no = $pm->current_item['po_line_no'];
				if ( $pm->current_item['line_item_invoice_hash'] ) {

					$invoice_hash = $pm->current_item['line_item_invoice_hash'];
					$invoiced_line = true;
				}

			} else {

				$lines = new line_items($proposal_hash, $this->current_po['punch']);
				if ( ! $lines->fetch_line_item_record($item_hash) )
	    			return $this->__trigger_error("System error encountered when attempting to lookup purchase order line item record. Please reload window and try again. <!-- Tried fetching line item [ $item_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

				$work_order = false;
				$qty = $lines->current_line['qty'];
				$list = $lines->current_line['list'];
				$cost = $lines->current_line['cost'];
				$ext_cost = $lines->current_line['ext_cost'];
				$sell = $lines->current_line['sell'];
				$ext_sell = $lines->current_line['ext_sell'];
				$item_product = $lines->current_line['item_product'];
				$item_product_hash = $lines->current_line['product_hash'];
				$item_descr = $lines->current_line['item_descr'];
				$item_no = $lines->current_line['item_no'];
				$line_no = $lines->current_line['po_line_no'];
				$item_code = $lines->current_line['item_code'];
				$import_data = ($lines->current_line['import_data'] ? true : false);
				if ( $lines->current_line['invoice_hash'] ) {

					$invoice_hash = $lines->current_line['invoice_hash'];
					$invoiced_line = true;
				}
			}
		}

        if ( ! $proposals->fetch_master_record($proposal_hash) )
            return $this->__trigger_error("System error encountered when attempting to lookup proposal record for purchase order edit. Please reload window and try again. <!-- Tried fetching proposal [ $proposal_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

        $lines = new line_items($proposals->proposal_hash, $this->current_po['punch']);
        if ( $this->current_po['punch'] )
            $final = $proposals->current_proposal['punch_final'];
        else
            $final = $proposals->current_proposal['final'];

        if ( $final ) {

            $lines->fetch_line_item_record($this->current_po['line_items'][0]);

            $item_list = $lines->fetch_line_items_short();
            for ($i = 0; $i < count($item_list); $i++) {
                if (!$item_list[$i]['po_hash']) {
                    $good = true;
                    break;
                }
            }
            if ($good)
                $lines->fetch_line_items(0, $lines->total, 1);

        }

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
    		"po_hash"         =>  $po_hash,
			"proposal_hash"   =>  $proposal_hash,
		    "parent_popup_id" =>  $parent_popup_id,
			"popup_id"        =>  $this->popup_id,
			"item_hash"       =>  $item_hash,
			"work_order"	  =>  $work_order
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
            <table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\" >
                <tr>
                    <td style=\"background-color:#ffffff;padding:20px;\">
                         <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" class=\"table_list\" >
                            <tr class=\"thead\" style=\"font-weight:bold;\">
                                <td style=\"width:10px;\">" .
                                    $this->form->checkbox(
                                        "name=check_all",
                                        "value=1",
                                        "onClick=checkall(document.getElementsByName('po_item{$this->popup_id}[]'), this.checked);"
                                    ) . "
                                </td>
                                <td style=\"width:200px;vertical-align:bottom;\">Item No.</td>
                                <td style=\"width:300px;vertical-align:bottom;\">Item Descr.</td>
                                <td style=\"width:90px;text-align:right;vertical-align:bottom;\">Item Cost</a></td>
                                <td style=\"width:100px;text-align:right;vertical-align:bottom;padding-right:5px;\">Ext Cost</td>
                            </tr>";

                            $border = "border-bottom:1px solid #cccccc;";
                            for ( $i = 0; $i < $lines->total; $i++ ) {
                            	
                            	$line_item_descr = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $lines->line_info[$i]['item_descr']);

                            	if ( $i >= $lines->total - 1 )
                                	unset($border);

                                if ( $lines->line_info[$i]['vendor_hash'] == $this->current_po['vendor_hash'] && $lines->line_info[$i]['active'] && ! $lines->line_info[$i]['status'] ) {

                                    $line_exists = true;
                                    if ( $lines->line_info[$i]['group_hash'] && $lines->line_info[$i]['group_hash'] != $lines->line_info[$i-1]['group_hash'] ) {

                                        $tbl .= "
                                        <tr style=\"background-color:#d6d6d6;\">
                                            <td colspan=\"5\" style=\"font-weight:bold;padding-top:15px;\">Group: " . nl2br( stripslashes($lines->line_info[$i]['group_descr']) ) . ":</td>
                                        </tr>
                                        <tr>
                                            <td colspan=\"5\" style=\"background-color:#cccccc;\"></td>
                                        </tr>";
                                    }

                                    if ( $lines->line_info[$i]['group_hash'] && $lines->line_info[$i]['active'] ) {

                                        $grp_cost = bcadd($grp_cost, $lines->line_info[$i]['cost'], 2);
                                        $grp_ext_cost = bcadd($grp_ext_cost, bcmul($lines->line_info[$i]['cost'], $lines->line_info[$i]['qty'], 2), 2);
                                    }

                                    $tbl .= "
                                    <tr style=\"background-color:#efefef;\">
                                        <td></td>
                                        <td colspan=\"4\" style=\"padding:5px 0 0 10px;font-style:italic;font-weight:bold;\">" .
                                            stripslashes($lines->line_info[$i]['vendor_name']) .
                                            ( $lines->line_info[$i]['product_name'] ?
                                                    ": " . stripslashes($lines->line_info[$i]['product_name']) : NULL
                                            ) .
                                            $lines->line_info[$i]['seperate_po'] . "
                                        </td>
                                    </tr>
                                    <tr style=\"background-color:#efefef;\">
                                        <td style=\"vertical-align:bottom;cursor:auto;$border\" >" .
                                            $this->form->checkbox(
                                                "name=po_item{$this->popup_id}[]",
                                                "value={$lines->line_info[$i]['item_hash']}",
                                                ( ! $lines->line_info[$i]['vendor_hash'] || ! $lines->line_info[$i]['product_hash'] ? "disabled title=This item has no assigned " . ( ! $lines->line_info[$i]['vendor_hash'] ? "vendor" : "product" ) . " and cannot be added to this PO!" : NULL )
                                            ) . "
                                        </td>
                                        <td style=\"vertical-align:bottom;$border\">
                                            <div >" . stripslashes($lines->line_info[$i]['item_no']) . "</div>
                                        </td>
                                        <td style=\"vertical-align:bottom;$border\" " .
                                            ( strlen($line_item_descr) > 70 ?
                                                "title=\"" . htmlentities($line_item_descr, ENT_QUOTES) . "\"" : NULL
                                            ) . " $onClick>" .
                                            ( strlen($line_item_descr) > 70 ?
                                                wordwrap(substr($line_item_descr, 0, 65), 35, "<br />") . "..." :
                                                ( strlen($line_item_descr) > 35 ?
                                                    wordwrap( stripslashes($line_item_descr, 35, "<br />") ) : nl2br( stripslashes($line_item_descr) )
                                                )
                                            ) . "
                                        </td>
                                        <td style=\"vertical-align:bottom;$border\" class=\"num_field\">\$" . number_format($lines->line_info[$i]['cost'], 2) . "</td>
                                        <td style=\"vertical-align:bottom;padding-right:5px;$border\" class=\"num_field\">\$" . number_format($lines->line_info[$i]['ext_cost'], 2) . "</td>
                                    </tr>";

                                    if ( $lines->line_info[$i]['group_hash'] && ( ! $lines->line_info[$i+1]['group_hash'] || $lines->line_info[$i+1]['group_hash'] != $lines->line_info[$i]['group_hash'] ) ) {

                                        $tbl .= "
                                        <tr style=\"background-color:#d6d6d6\">
                                            <td></td>
                                            <td colspan=\"4\" style=\"font-weight:bold;padding-top:15px;\">End of Group: " . stripslashes($lines->line_info[$i]['group_descr']) . "</td>
                                        </tr>";

                                        unset($grp_cost, $grp_ext_cost);
                                    }
                                }
                            }

                            if ( ! $line_exists ) {

                                $tbl .= "
                                <tr style=\"background-color:#ffffff\">
                                    <td></td>
                                    <td colspan=\"4\" style=\"padding-top:15px;\">" .
                                    ( $final ?
                                        "There are no line items eligible to be added to this purchase order.
                                        <br /><br />
                                        Please make sure your line items have been added under the 'Item Details' tab and have not been marked as in-active."
                                        :
                                        "You must finalize your proposal prior to creating purchase orders!"
                                    ) . "
                                    </td>
                                </tr>";
                            }

                        $tbl .= "
                        </table>
                    </td>
                </tr>
            </table>
            <div style=\"padding-top:10px;padding-left:25px;\">" .
                $this->form->button(
                    "value=Save & Update",
                    "onClick=submit_form(this.form, 'purchase_order', 'exec_post', 'refresh_form', 'method=edit_line', 'addfrom=lines');"
                ) . "
            </div>";

		$tbl .= "
		</div>";

		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function new_po($local=NULL) {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->proposals && !$this->proposals->proposal_hash) {
			if (!$this->ajax_vars['proposal_hash'])
				return;

			$this->proposals = new proposals($this->current_hash);
			$this->proposals->fetch_master_record($this->ajax_vars['proposal_hash']);
		}
		$lines = new line_items($this->proposals->proposal_hash);
		if ($this->ajax_vars['punch'] == 1) {
			$pm = new pm_module($this->proposals->proposal_hash);
			$pm->fetch_punch_items();

			for ($i = 0; $i < $pm->total_punch; $i++) {
				if (!$pm->punch_items[$i]['status']) {
					$good = true;
					$po_item[] = $pm->punch_items[$i]['item_hash'];
					$po_item_group[] = $pm->punch_items[$i]['group_hash'];

					$line_item[] =& $pm->punch_items[$i]['item_hash'];
				}
			}
			$lines->total = count($po_item);
		} else {
			if ($this->proposals->current_proposal['final']) {
				$total_sell = 0;
				$lines->fetch_line_items(0, $lines->total);
				$a = true;
				for ($i = 0; $i < $lines->total; $i++) {
					if ($lines->line_info[$i]['po_hash'] || $lines->line_info[$i]['invoice_hash'])
                        unset($lines->line_info[$i], $a);
                    else {
						$total_sell += $lines->line_info[$i]['ext_sell'];
						$po_item[] = $lines->line_info[$i]['item_hash'];
						$po_item_group[] = $lines->line_info[$i]['group_hash'];
                    }
				}
                if (!$a)
                    $lines->line_info = array_values($lines->line_info);
			}
		}

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Create Purchase Orders";
		$step = $this->ajax_vars['step'];

		$pm = new pm_module($this->current_hash);

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("punch" => $this->ajax_vars['punch'],
		                          "proposal_hash" => $this->proposals->proposal_hash,
		                          "popup_id" => $this->popup_id,
		                          "step" => $step,
		                          "form_placeholder"    =>  1))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<div id=\"po_content{$this->popup_id}\">";
			if (!$step) {
				$tbl .= "
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
					<tr>
						<td style=\"padding:0;\">
							 <div style=\"background-color:#ffffff;height:100%;\">
								<div style=\"margin:15px 35px;\">
									<h3 style=\"margin-bottom:5px;color:#00477f;\">Select Line Items</h3>
									<div style=\"margin-left:25px;margin-top:8px;margin-bottom:10px;\">
										".$this->form->button("value=Next -->", "id=primary", "onClick=submit_form(\$('form_placeholder').form, 'purchase_order', 'exec_post', 'refresh_form', 'action=doit_new_po');")."
									</div>
									<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
										<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
											<p id=\"feedback_message\"></p>
									</div>
									 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" class=\"table_list\">
										<tr class=\"thead\" style=\"font-weight:bold;\">
											<td style=\"width:10px;\">".$this->form->checkbox("name=check_all", "value=1", "onClick=checkall(document.getElementsByName('po_item[]'), this.checked);")."</td>
											<td style=\"width:200px;vertical-align:bottom;\">Item No.</td>
											<td style=\"width:300px;vertical-align:bottom;\">Item Descr.</td>
											<td style=\"width:90px;text-align:right;vertical-align:bottom;\" nowrap>Item Cost</td>
											<td style=\"width:100px;text-align:right;vertical-align:bottom;padding-right:5px;\">Ext Cost</td>
										</tr>";

										$group_colors = array("group_row1", "group_row2");
										$group_header = array("#D6D6D6", "#C1DAF0");
										for ($i = 0; $i < $lines->total; $i++) {
											if ($this->ajax_vars['punch']) {
												$lines->fetch_line_item_record($po_item[$i]);
												$lines->line_info[$i] =& $lines->current_line;
												$lines->line_info[$i]['vendor_name'] = $lines->current_line['item_vendor'];
											}

											if ($lines->line_info[$i]['status'] || !$lines->line_info[$i]['active'])
												unset($lines->line_info[$i]['group_hash']);

											if ($lines->line_info[$i]['active'] && !$lines->line_info[$i]['status']) {
												$line_exists = true;
												unset($next_group_hash, $prev_group_hash);
												if ($po_item[$i+1]) {
													$r = $this->db->query("SELECT `group_hash`
																		   FROM `line_items`
																		   WHERE `item_hash` = '".$po_item[$i+1]."'");
													$next_group_hash = $this->db->result($r, 0, 'group_hash');
												}
												if ($po_item[$i-1]) {
													$r = $this->db->query("SELECT `group_hash`
																		   FROM `line_items`
																		   WHERE `item_hash` = '".$po_item[$i-1]."'");
													$prev_group_hash = $this->db->result($r, 0, 'group_hash');
												}

												if ($lines->line_info[$i]['group_hash'] && $lines->line_info[$i]['group_hash'] != $prev_group_hash) {
													$num_groups++;
													$tbl .= "
													<tr style=\"background-color:".$group_header[($num_groups % 2)]."\">
														<td class=\"smallfont\" colspan=\"5\" style=\"font-weight:bold;padding-top:15px;padding-left:45px;\">
														     ".$this->form->checkbox("onClick=checkall($$('input[pgroup=".$lines->line_info[$i]['group_hash']."]'), this.checked);")."&nbsp;
														     Group: ".nl2br($lines->line_info[$i]['group_descr'])."
														</td>
													</tr>
													<tr>
														<td colspan=\"5\" style=\"background-color:#cccccc;\"></td>
													</tr>";
												}
												if ($lines->line_info[$i]['work_order_hash']) {
													$pm->fetch_work_order($lines->line_info[$i]['work_order_hash']);
													$lines->line_info[$i]['cost'] = $pm->current_order['total_true_cost'];
													$lines->line_info[$i]['ext_cost'] = $lines->line_info[$i]['cost'] * $lines->line_info[$i]['qty'];
												}

												if ($lines->line_info[$i]['group_hash'] && $lines->line_info[$i]['active']) {
													$grp_cost += $lines->line_info[$i]['cost'];
													$grp_ext_cost += ($lines->line_info[$i]['cost'] * $lines->line_info[$i]['qty']);
												}

												$tbl .= "
												<tr ".($lines->line_info[$i]['group_hash'] ? "class=\"".$group_colors[($num_groups % 2)]."\"" : NULL).">
													<td class=\"smallfont\" colspan=\"5\" style=\"padding:5px 0 0 10px;\">".(!$this->ajax_vars['punch'] ? "
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
													<td class=\"smallfont\" style=\"vertical-align:bottom;cursor:auto;\" >
														".$this->form->checkbox("name=po_item[]",
														                        "value=".$lines->line_info[$i]['item_hash'],
												                                ($lines->line_info[$i]['group_hash'] ? "pgroup=".$lines->line_info[$i]['group_hash']  : NULL),
												                                (!$lines->line_info[$i]['vendor_hash'] || !$lines->line_info[$i]['product_hash'] ? "disabled title=This item has no assigned ".(!$lines->line_info[$i]['vendor_hash'] ? "vendor" : "product")." and cannot be ordered. Please correct any errors before cutting purchase orders." : NULL))."
													</td>
													<td class=\"smallfont\" style=\"vertical-align:bottom;\">
														<div >".$lines->line_info[$i]['item_no']."</div>
													</td>
													<td class=\"smallfont\" style=\"vertical-align:bottom;\" ".(strlen($lines->line_info[$i]['item_descr']) > 70 ? "title=\"".$lines->line_info[$i]['item_descr']."\"" : NULL)." $onClick>".(strlen($lines->line_info[$i]['item_descr']) > 70 ?
														wordwrap(substr($lines->line_info[$i]['item_descr'], 0, 65), 35, "<br />", true)."..." : (strlen($lines->line_info[$i]['item_descr']) > 35 ?
															wordwrap($lines->line_info[$i]['item_descr'], 25, "<br />", true) : nl2br($lines->line_info[$i]['item_descr'])))."
													</td>
													<td class=\"smallfont\" style=\"vertical-align:bottom;text-align:right;\">$".number_format($lines->line_info[$i]['cost'], 2)."</td>
													<td class=\"smallfont\" style=\"vertical-align:bottom;padding-right:5px;text-align:right;\">$".number_format($lines->line_info[$i]['ext_cost'], 2)."</td>
												</tr>
												<tr>
													<td style=\"background-color:#cccccc;\" colspan=\"5\"></td>
												</tr>";

												if ($lines->line_info[$i]['group_hash'] && (!$po_item[$i+1] || ($po_item[$i+1] && !$next_group_hash) || $next_group_hash != $lines->line_info[$i]['group_hash'])) {
													$tbl .= "
													<tr style=\"background-color:".$group_header[($num_groups % 2)]."\">
														<td></td>
														<td class=\"smallfont\" colspan=\"4\" style=\"font-weight:bold;padding-top:15px;text-align:right;padding-right:10px;\">Group ".$lines->line_info[$i]['group_descr']." Total: $".number_format($grp_ext_cost, 2)."</td>
													</tr>";

													unset($grp_cost, $grp_ext_cost);
												}
											}
										}

										if (!$line_exists) {
											$tbl .= "
												<tr style=\"background-color:#ffffff\">
													<td></td>
													<td class=\"smallfont\" colspan=\"4\" style=\"padding-top:15px;\">".($this->ajax_vars['punch'] ? "
														There are no punchlist items available for purchase at this time." :
														($this->proposals->current_proposal['final'] ? "
															All line items available for order have already been ordered! Make sure any line items you
															may be trying to order are not marked as in-active." : "You must finalize your proposal prior
															to creating purchase orders!"))."
													</td>
												</tr>";
										}
							$tbl .= "
									</table>
								</div>
							</div>
						</td>
					</tr>
				</table>";
			} else {
				$po_data = $this->ajax_vars['po_data'];
				$separate_po = $this->ajax_vars['separate_po'];

				$doc_vault = new doc_vault();
				$doc_vault->fetch_files($this->proposals->proposal_hash);
				for ($i = 0; $i < $doc_vault->total; $i++) {
					$file_name[] = $doc_vault->proposal_docs[$i]['file_name'].($doc_vault->proposal_docs[$i]['file_descr'] ? (strlen($doc_vault->proposal_docs[$i]['file_descr']) > 15 ? substr($doc_vault->proposal_docs[$i]['file_descr'], 0, 15)."..." : $doc_vault->proposal_docs[$i]['file_descr']) : NULL);
					$file_hash[] = $doc_vault->proposal_docs[$i]['doc_hash'];
				}


				$jscript[] = "
				toggle_submit_views = function() {
					var view = arguments[0];
					var hash = arguments[1];

					switch (view) {
						case 'E':
						toggle_display('submit_to_tr_' + hash, 'block');
						toggle_display('submit_to_cc_' + hash, 'block');
						if (\$('eorder_input_' + hash))
							toggle_display('eorder_input_' + hash, 'none');
						toggle_display('submit_to_fax_' + hash, 'none');
						toggle_display('submit_to_email_' + hash, 'block');
						break;

						case 'F':
						toggle_display('submit_to_tr_' + hash, 'block');
						toggle_display('submit_to_cc_' + hash, 'none');
						if (\$('eorder_input_' + hash))
							toggle_display('eorder_input_' + hash, 'none');
						toggle_display('submit_to_fax_' + hash, 'block');
						toggle_display('submit_to_email_' + hash, 'none');
						break;

						case 'EE':
						toggle_display('submit_to_tr_' + hash, 'block');
						toggle_display('submit_to_cc_' + hash, 'block');
						toggle_display('eorder_input_' + hash, 'block');
						toggle_display('submit_to_fax_' + hash, 'none');
						toggle_display('submit_to_email_' + hash, 'block');
						break;

						case 'ES':
						toggle_display('submit_to_tr_' + hash, 'none');
						toggle_display('submit_to_cc_' + hash, 'none');
						if (\$('eorder_input_' + hash))
							toggle_display('eorder_input_' + hash, 'block');
						toggle_display('submit_to_fax_' + hash, 'none');
						toggle_display('submit_to_email_' + hash, 'none');
						break;

						default:
						toggle_display('submit_to_tr_' + hash, 'none');
						toggle_display('submit_to_cc_' + hash, 'none');
						if (\$('eorder_input_' + hash))
							toggle_display('eorder_input_' + hash, 'none');
						toggle_display('submit_to_fax_' + hash, 'none');
						toggle_display('submit_to_email_' + hash, 'none');
						break;
					}
				}
				po_purge = function() {
					var po_hash = arguments[0];
					if (\$('p_' + po_hash))
						\$('p_' + po_hash).remove();
				}";

				// Fetch user preferences po generation
				if ($print_pref = fetch_user_data("po_gen"))
					$print_pref = __unserialize(stripslashes($print_pref));

				$content_tbl = "
				<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0;\">
					<tr>
						<td class=\"smallfont\" style=\"vertical-align:middle;\">
							Please review your purchase orders before completing this step.
							The following purchase orders will be created and are summarized below:
							<div style=\"width:100%;\">";

							while (list($hash_index, $items) = each($po_data)) {
								if (count($items)) {
									$this->vendors->fetch_master_record($items[0]['vendor_hash']);
									$vendor_name = $this->vendors->current_vendor['vendor_name'];

									$this->lines->fetch_line_item_record($items[0]['item_hash']);
									list($class, $id, $hash) = explode("|", $this->lines->current_line['ship_to_hash']);

                                    if (defined('MULTI_CURRENCY') && $this->vendors->current_vendor['currency']) {
	                                    $sys = new system_config();

	                                    $vendor_currency = $sys->home_currency;
	                                    if ($sys->home_currency['code'] != $this->vendors->current_vendor['currency']) {
	                                        $sys->fetch_currency($this->vendors->current_vendor['currency']);
	                                        $vendor_currency = $sys->current_currency;
	                                    }
                                    } else
                                        unset($vendor_currency);

									unset($ship_to);
									if ($class) {

										$obj = new $class($this->current_hash);
										if ($hash) {

											if ($loc_valid = $obj->fetch_location_record($hash)) {

												$ship_to =
												stripslashes($obj->current_location['location_name']) . "<br />" .
												( $obj->current_location['location_street'] ?
    												stripslashes($obj->current_location['location_street']) . "<br />" : NULL
    											) .
    											stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

											} else
												$ship_to = "<i>Invalid Shipping Location</i>";

										} else {

											if ( $obj->fetch_master_record($id) ) {

												$ship_to =
												stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }[strrev( substr( strrev($class), 1) ) . "_name"]) . "<br />" .
												( $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["street"] ?
    												stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }["street"]) . "<br />" : NULL
    											) .
    											stripslashes($obj->{"current_" . strrev( substr( strrev($class), 1) ) }["city"]) . ", " . $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["state"] . " " . $obj->{"current_" . strrev( substr( strrev($class), 1) ) }["zip"];
											}
										}

										unset($obj);
									}

									if ( $separate_po[$hash_index] ) {
										$this->vendors->fetch_product_record($items[0]['product_hash']);

										$vendor_name .= " : ".$this->vendors->current_product['product_name'];
										$order_method = $this->vendors->current_product['product_order_method'];
										$submit_to_email = ($this->vendors->current_product['product_order_email'] ?
												$this->vendors->current_product['product_order_email'] : $this->vendors->current_vendor['order_email']);
										$submit_to_fax = ($this->vendors->current_product['product_order_fax'] ?
												$this->vendors->current_product['product_order_fax'] : $this->vendors->current_vendor['order_fax']);
										if ($order_method == 'product_order_email') {
											$order_method = 'Email';
											$order_method_type = 'E';
										} else {
											$order_method = 'Fax';
											$order_method_type = 'F';
										}
									} else {
										$order_method = $this->vendors->current_vendor['order_method'];
										$submit_to_email = $this->vendors->current_vendor['order_email'];
										$submit_to_fax = $this->vendors->current_vendor['order_fax'];
										if ($order_method == 'order_email') {
											$order_method = 'Email';
											$order_method_type = 'E';
										}
										elseif ($order_method == 'order_fax' || !$order_method) {
											$order_method = 'Fax';
											$order_method_type = 'F';
										}
									}

									if (array_key_exists('order_method_type', $print_pref[$this->vendors->current_vendor['vendor_hash']])) {
										$order_method_type = $print_pref[$this->vendors->current_vendor['vendor_hash']]['order_method_type'];
									}
									unset($print_pref, $item_detail_list);

									$total_amt = 0;
									$total_list = 0;
									$total_sell = 0;
									$item_detail_list = '';
									if ($file_name)
										$file_vault = $this->form->select("file_vault_".$hash_index."[]", $file_name, NULL, $file_hash, "blank=1", "multiple", "size=".(count($file_name) > 4 ? "4" : count($file_name)+1), "style=width:250px;");

									unset($po_product, $product_listing, $t);
									for ($i = 0; $i < count($items); $i++) {
										$this->lines->fetch_line_item_record($items[$i]['item_hash']);
										if ($items[$i]['item_type'] == 'W') {
											$pm->fetch_work_order_item($items[$i]['work_order_item_hash']);
											$ext_cost = $pm->current_item['ext_true_cost'];
											$qty = strrev(substr(strstr(strrev($pm->current_item['time']), '.'), 1)).(str_replace('0', '', substr(strrchr($pm->current_item['time'], '.'), 1)) ?
														str_replace('0', '', strrchr($pm->current_item['time'], '.')) : NULL);
										} else {
											$ext_cost = $this->lines->current_line['ext_cost'];
											$ext_list = $this->lines->current_line['ext_list'];
											$ext_sell = $this->lines->current_line['ext_sell'];
											$qty = strrev(substr(strstr(strrev($this->lines->current_line['qty']), '.'), 1)).(str_replace('0', '', substr(strrchr($this->lines->current_line['qty'], '.'), 1)) ?
														str_replace('0', '', strrchr($this->lines->current_line['qty'], '.')) : NULL);
										}
										if (!$this->lines->current_line['item_code'])
											$product_listing[] = $this->lines->current_line['product_hash'];

										$total_amt = bcadd($total_amt, $ext_cost);
										$total_list = bcadd($total_list, $ext_list);
										$total_sell = bcadd($total_sell, $ext_sell);

										$item_detail_list .= "
										<tr title=\"" . ( $items[$i]['item_type'] == 'W' ? $pm->current_item['item_descr'] : $this->lines->current_line['item_descr'] ) . "\">
											<td class=\"smallfont\" " . ( $i < count($items) - 1 ? "style=\"border-bottom:1px solid #cccccc;\"" : NULL ) . ">" .
	                                        $this->form->hidden( array(
    	                                        "item_{$hash_index}[]"             =>  $items[$i]['item_hash'],
	                                            "work_order_item_{$hash_index}[]"  =>  ( $items[$i]['item_type'] == 'W' ? $items[$i]['work_order_item_hash'] : NULL )
	                                        ) ) .
    										( strlen($this->lines->current_line['item_product']) > 35 ?
												substr($this->lines->current_line['item_product'], 0, 33) . "..." : $this->lines->current_line['item_product']
											) . "
											</td>
											<td class=\"smallfont\" style=\"text-align:right;padding-right:10px;".($i < count($items) - 1 ? "border-bottom:1px solid #cccccc;" : NULL)."\" >".$qty.($items[$i]['item_type'] == 'W' && $pm->current_item['units'] != 'F' ? "&nbsp;".$pm->units[$pm->current_item['units']].($qty > 1 ? "s" : NULL) : NULL)."</td>
											<td class=\"smallfont\" ".($i < count($items) - 1 ? "style=\"border-bottom:1px solid #cccccc;\"" : NULL).">".
											( $this->lines->current_line['item_no'] ?
                                                $this->lines->current_line['item_no'] : "&nbsp;"
                                            ) . "
                                            </td>
											<td class=\"smallfont\" style=\"text-align:right;".($i < count($items) - 1 ? "border-bottom:1px solid #cccccc;" : NULL)."\" >".(($items[$i]['item_type'] == 'W' && bccomp($pm->current_item['true_cost'], 0) == -1) || ($items[$i]['item_type'] != 'W' && bccomp($this->lines->current_line['cost'], 0) == -1) ?
												"($".number_format(($items[$i]['item_type'] == 'W' ? bcmul($pm->current_item['true_cost'], -1) : bcmul($this->lines->current_line['cost'], -1)), 2).")" : "$".number_format(($items[$i]['item_type'] == 'W' ? $pm->current_item['true_cost'] : $this->lines->current_line['cost']), 2))."
                                            </td>
										</tr>";
									}
									unset($deposit_required);
									if ($this->vendors->current_vendor['deposit_percent'] > 0 && defined('DEFAULT_WIP_ACCT'))
										$deposit_required = $total_amt * $this->vendors->current_vendor['deposit_percent'];

									unset($efile_input, $efile_obj, $submit_via_out, $submit_via_in);
									if ($this->vendors->current_vendor['eorder_file'] && file_exists(SITE_ROOT.'core/images/e_profile/'.$this->vendors->current_vendor['eorder_file'])) {
										$submit_via_out = array("Generate electronic order and let me save it", "Fax", "Email", "Don't send, just let me print it");
										$submit_via_in =  array("ES", "F", "E", "");

										include_once(SITE_ROOT.'core/images/e_profile/'.$this->vendors->current_vendor['eorder_file']);
										$efile_class = explode(".", $this->vendors->current_vendor['eorder_file']);
										$efile_class_name = "eorder_".$efile_class[1];
										$efile_obj = new $efile_class_name($this->vendors->vendor_hash, $this->proposals->proposal_hash);

										if ($efile_obj->send_method) {
											$submit_via_out = array_merge(array("Generate electronic order and send it"), $submit_via_out);
											$submit_via_in = array_merge(array("EE"), $submit_via_in);
										}

										// Add special function to toggle date fields. Added for #1290
										if (@in_array('rq_deliver_date', $efile_obj->e_order_jscript)) {
											$jscript[] = "
												toggle_eorder_views_$hash_index = function() {
													var view = arguments[0];
													var hash = arguments[1];

													switch (view) {
														case 'EE':
														case 'ES':
														if (\$('req_arrive_date_tr_' + hash))
															toggle_display('req_arrive_date_tr_' + hash, 'none');
														if (\$('req_ship_date_tr_' + hash))
															toggle_display('req_ship_date_tr_' + hash, 'none');
														break;

														default:
														if (\$('req_arrive_date_tr_' + hash))
															toggle_display('req_arrive_date_tr_' + hash, 'block');
														if (\$('req_ship_date_tr_' + hash))
															toggle_display('req_ship_date_tr_' + hash, 'block');
														break;
													}
												}";
										}

										$efile_input = $efile_obj->user_input($hash_index, $this->proposals, $items);
										if ($efile_input)
											$efile_input = "<div style=\"padding-top:5px;display:".($order_method_type == 'EE' || $order_method_type == 'ES' ? "block" : "none").";\" id=\"eorder_input_".$hash_index."\">".$efile_input."</div>";
										if (@in_array('rq_deliver_date', $efile_obj->e_order_jscript))
											$jscript[] = "setTimeout('DateInput(\'einput_req_deliver_date_".$hash_index."\', \'\', \'YYYY-MM-DD\', \'\', 1, \'eorder_rq_deliver_date_holder_".$hash_index."\')', 2000);";

									} else {
										$submit_via_out = array("Fax", "Email", "Don't send, just let me print it");
										$submit_via_in = array("F", "E", "");
									}

									$product_listing = @array_unique($product_listing);
									$product_listing = @array_values($product_listing);
									for ($i = 0; $i < count($product_listing); $i++) {
										$r = $this->db->query("SELECT `product_name`
															   FROM `vendor_products`
															   WHERE `product_hash` = '".$product_listing[$i]."'");
										$po_product[] = $this->db->result($r, 0, 'product_name');
									}
									$po_product_name = @implode(", ", $po_product);
									$deposit_total_sell += $total_sell;

									$logo = explode("|", fetch_sys_var('company_logo'));
									for ($i = 0; $i < count($logo); $i++) {
										$logo_in[] = $logo[$i];
										$logo_out[] = basename($logo[$i]);
									}

									if ($print_pref = fetch_user_data("po_print"))
										$print_pref = __unserialize(stripslashes($print_pref));

									$content_tbl .= "
									<table style=\"width:98%;\" id=\"p_".$hash_index."\">
										<tr>
											<td style=\"width:100%;vertical-align:top;\">".
											$this->form->hidden(array("po_hash[]"					=> 	$hash_index,
																	  "po_product_".$hash_index		=>	(strlen($po_product_name) > 128 ? substr($po_product_name, 0, 125)."..." : $po_product_name),
																	  "vendor_hash_".$hash_index	=>	$items[0]['vendor_hash'],
																	  "ship_to_hash_".$hash_index	=>	$this->lines->current_line['ship_to_hash'],
																	  "no_items_".$hash_index		=>	count($items),
																	  "order_total_".$hash_index	=>	$total_amt,
																	  "total_list_".$hash_index     =>	$total_list,
																	  "total_sell_".$hash_index     =>	$total_sell,
																	  "vendor_info_".$hash_index	=>	($separate_po[$hash_index] ? $this->lines->current_line['product_hash'] : NULL),
																	  ))."
												<fieldset style=\"margin-top:15px;\">
													<legend style=\"font-weight:bold;\">#".(++$k)." - ".$vendor_name."</legend>
													<div style=\"margin:10px 20px;\">
														<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#cccccc;width:98%;\">
															<tr>
																<td style=\"text-align:right;background-color:#efefef;width:25%;\" nowrap>Purchase Amt:</td>
																<td style=\"background-color:#ffffff;width:75%;\">$".number_format($total_amt, 2)."</td>
															</tr>".($deposit_required ? "
															<tr>
																<td style=\"text-align:right;background-color:#efefef;\" nowrap>Vendor<br />Deposit Required:</td>
																<td style=\"background-color:#ffffff;\">
																	".$this->form->checkbox("name=vendor_deposit_".$hash_index, "value=1", "checked", "title=Uncheck this box to skip the vendor deposit")."
																	&nbsp;
																	$".number_format($deposit_required, 2)."
																	<div style=\"margin-top:5px;font-style:italic;\">This deposit will be created automatically</div>
																</td>
															</tr>" : NULL).(defined('MULTI_CURRENCY') && $vendor_currency && $vendor_currency['code'] != $sys->home_currency['code'] ? "
                                                            <tr>
                                                                <td style=\"text-align:right;background-color:#efefef;vertical-align:top;\" class=\"smallfont\">Currency:</td>
                                                                <td class=\"smallfont\" style=\"background-color:#ffffff;\">
                                                                    ".$this->form->select("currency_".$hash_index,
                                                                                           array($vendor_currency['code'], $sys->home_currency['code']),
                                                                                           $vendor_currency['code'],
                                                                                           array($vendor_currency['code'], $sys->home_currency['code']),
                                                                                           "blank=1")."
                                                                    <span style=\"padding-left:5px;font-style:italic;\">(Shown in ".$sys->home_currency['code'].")</span>
                                                                </td>
                                                            </tr>" : NULL)."
															<tr>
																<td style=\"text-align:right;background-color:#efefef;vertical-align:top;\" nowrap>Submit Via:</td>
																<td style=\"background-color:#ffffff;\">
																	".$this->form->select("transmit_type_".$hash_index,
																							$submit_via_out,
																							$order_method_type,
																							$submit_via_in,
																							"blank=1",
																							"onChange=toggle_submit_views(this.options[this.selectedIndex].value, '".$hash_index."');".(@in_array('rq_deliver_date', $efile_obj->e_order_jscript) ? "toggle_eorder_views_$hash_index(this.options[this.selectedIndex].value, '".$hash_index."');" : NULL))."
																	".$efile_input."
																</td>
															</tr>
															<tr id=\"submit_to_tr_".$hash_index."\" style=\"display:".($order_method_type == 'E' || $order_method_type == 'EE' || $order_method_type == 'F' ? "block" : "none").";\">
																<td style=\"text-align:right;background-color:#efefef;\" id=\"err3_".$hash_index."\" nowrap>Submit To:</td>
																<td style=\"background-color:#ffffff;\">
																	<div id=\"submit_to_fax_".$hash_index."\" style=\"display:".($order_method_type == 'F' ? "block" : "none").";\">".$this->form->text_box("name=transmit_to_fax_".$hash_index, "value=".$submit_to_fax)."</div>
																	<div id=\"submit_to_email_".$hash_index."\" style=\"display:".($order_method_type == 'E' || $order_method_type == 'EE' ? "block" : "none").";\">".$this->form->text_box("name=transmit_to_email_".$hash_index, "value=".$submit_to_email)."</div>
																</td>
															</tr>
															<tr id=\"submit_to_cc_".$hash_index."\" style=\"display:".($order_method_type == 'E' || $order_method_type == 'EE' ? "block" : "none").";\">
																<td style=\"text-align:right;background-color:#efefef;vertical-align:top;padding-top:20px;\" id=\"err3a_".$hash_index."\" nowrap>CC:</td>
																<td style=\"background-color:#ffffff;\">
																	<div style=\"margin-bottom:5px;font-style:italic;\"><small>(separate multiple emails with line breaks)</small></div>
																	".$this->form->text_area("name=transmit_to_cc_".$hash_index, "value=", "rows=3", "cols=35")."
																</td>
															</tr>
															<tr>
																<td style=\"text-align:right;background-color:#efefef;vertical-align:top;\" nowrap>Company Logo:</td>
																<td style=\"background-color:#ffffff;\">
																	".$this->form->select("use_logo_".$hash_index, $logo_out, $print_pref['use_logo'], $logo_in, "blank=1", "style=width:325px")."
																	<div style=\"padding-top:5px;\">
																		".$this->form->checkbox("name=print_logo_".$hash_index, "value=1", ($print_pref['print_logo'] ? "checked" : NULL))."&nbsp;Print Logo?
																	</div>
																</td>
															</tr>
															<tr>
																<td style=\"text-align:right;background-color:#efefef;vertical-align:top;\" nowrap>Attach Files:</td>
																<td style=\"background-color:#ffffff;\">".($file_vault ?
																	$file_vault : "The file vault is empty")."</td>
															</tr>
															<tr>
																<td style=\"text-align:right;background-color:#efefef;\" nowrap>PO Comment:</td>
																<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=po_comment_".$hash_index, "value=".$this->vendors->current_vendor['po_comment'], "size=40", "maxlength=255")."</td>
															</tr>
															<tr>
																<td style=\"text-align:right;vertical-align:top;background-color:#efefef;\">Ship To:</td>
																<td style=\"background-color:#ffffff;\"	>".($ship_to ?
																	$ship_to : "<div style=\"padding-left:5px;\"><img src=\"images/alert.gif\" title=\"There is no shipping address listed!\" /></div>")."
																</td>
															</tr>
															<tr>
																<td style=\"text-align:right;vertical-align:top;background-color:#efefef;\">Shipping Contact:</td>
																<td style=\"background-color:#ffffff;\"	>".$this->form->text_box("name=ship_to_contact_name_".$hash_index, "value=".$this->proposals->current_proposal['ship_to_contact_name'], "size=40", "maxlength=40")."
																</td>
															</tr>
															<tr>
																<td style=\"text-align:right;vertical-align:top;background-color:#efefef;\">Contact Phone:</td>
																<td style=\"background-color:#ffffff;\"	>".$this->form->text_box("name=ship_to_contact_phone_".$hash_index, "value=".$this->proposals->current_proposal['ship_to_contact_phone'], "size=25", "maxlength=40")."
																</td>
															</tr>
															<tr>
																<td style=\"text-align:right;vertical-align:top;background-color:#efefef;\">Contact Fax:</td>
																<td style=\"background-color:#ffffff;\"	>".$this->form->text_box("name=ship_to_contact_fax_".$hash_index, "value=".$this->proposals->current_proposal['ship_to_contact_fax'], "size=25", "maxlength=40")."
																</td>
															</tr>
															<tr id=\"req_ship_date_tr_".$hash_index."\"".(@in_array('rq_deliver_date', $efile_obj->e_order_jscript) && ($order_method_type == 'EE' || $order_method_type == 'ES') ? 'style=\"display:none' : '')."\">
																<td style=\"text-align:right;vertical-align:top;background-color:#efefef;\" id=\"err1_".$hash_index."\">Req Ship Date:</td>
																<td style=\"vertical-align:top;background-color:#ffffff;\">
																	<div id=\"rq_ship_date_holder_".$hash_index."\">";
																	$jscript[] = "setTimeout('DateInput(\'rq_ship_date_".$hash_index."\', ".($this->current_proposal['rq_ship_date'] ? 'true' : 'false').", \'YYYY-MM-DD\', \'".($this->current_proposal['rq_ship_date'] ? $this->current_proposal['rq_ship_date'] : NULL)."\', 1, \'rq_ship_date_holder_".$hash_index."\')', 19);";
																	$content_tbl .= "
																	</div>
																</td>
															</tr>
															<tr id=\"req_arrive_date_tr_".$hash_index."\"".(@in_array('rq_deliver_date', $efile_obj->e_order_jscript) && ($order_method_type == 'EE' || $order_method_type == 'ES') ? 'style=\"display:none' : '')."\">
																<td style=\"text-align:right;vertical-align:top;background-color:#efefef;\" id=\"err2_".$hash_index."\">Req Arrive Date:</td>
																<td style=\"vertical-align:top;background-color:#ffffff;\">
																	<div id=\"rq_arrive_date_holder_".$hash_index."\">";
																	$jscript[] = "setTimeout('DateInput(\'rq_arrive_date_".$hash_index."\', ".($this->current_proposal['rq_arrive_date'] ? 'true' : 'false').", \'YYYY-MM-DD\', \'".($this->current_proposal['rq_arrive_date'] ? $this->current_proposal['rq_arrive_date'] : NULL)."\', 1, \'rq_arrive_date_holder_".$hash_index."\')', 19);";
																	$content_tbl .= "
																	</div>
																</td>
															</tr>
														</table>
													</div>
													<div style=\"margin:10px 20px;\">
													    <div style=\"font-weight:bold;margin-bottom:5px;\">Items To Be Purchased (" . count($items) . "):</div>" .
	                                                        ( count($items) > 15 ?
	    														"<div style=\"height:500px;overflow:auto;\">" : NULL
	                                                        ) . "
															<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:98%;\">
																<tr class=\"thead\" style=\"font-weight:bold;\">
																	<td>Product</td>
																	<td style=\"text-align:right;padding-right:8px;\">Qty</td>
																	<td>Item No.</td>
																	<td style=\"text-align:right;\">Item Cost</td>
																</tr>
																$item_detail_list
															</table>" .
															( count($items) > 15 ?
        														"</div>" : NULL
															) . "
													</div>
												</fieldset>
											</td>
										</tr>
									</table>";
								}
							}

						$content_tbl .= "
							</div>
						</td>
					</tr>
				</table>";
				$customers = new customers($this->current_hash);
				$customers->fetch_master_record($this->proposals->current_proposal['customer_hash']);
				if ( $customers->current_customer['deposit_percent'] > 0 ) {

					$invoice = new customer_invoice($this->current_hash);
					$invoice->set_values( array(
    					"customer_hash"    =>  $this->proposals->current_proposal['customer_hash']
					) );
					$deposits = $invoice->fetch_customer_deposits($this->proposals->proposal_hash);
					for ($i = 0; $i < count($deposits); $i++) {
						if ($deposits[$i]['proposal_hash'] == $this->proposals->proposal_hash)
							$total_deposits += $deposits[$i]['balance'];
					}
					$cust_deposit_required = $deposit_total_sell * $customers->current_customer['deposit_percent'];
					if ($cust_deposit_required && (!$total_deposits || ($total_deposits && $total_deposits < $cust_deposit_required - ((float)DEPOSIT_THRESHOLD * $cust_deposit_required))))
						$cust_deposit_required = $cust_deposit_required;
				}
				if ((!$this->ajax_vars['punch'] && $cust_deposit_required > 0 && $cust_deposit_required > $total_deposits) || ($customers->current_customer['po_required'] && !$this->proposals->current_proposal['customer_po'])) {
					$auth = true;
					$auth_msg = (!$this->ajax_vars['punch'] && $cust_deposit_required > 0 && $cust_deposit_required > $total_deposits ? "
					<img src=\"images/alert.gif\" title=\"Minimum deposit has not been received!\" />
					&nbsp;
					<strong>A minimum customer deposit of $".number_format($cust_deposit_required, 2)." is required in order to proceed.</strong>
					<div style=\"text-align:right;display:none;\">".($_SESSION['is_mngr'] ?
						"[<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('override', 'exec_post', 'refresh_form', 'auth_action=_override', 'auth_btn=po_auth_btn', 'auth_hash=".$this->proposals->proposal_hash."', 'auth_content=deposit_rq', 'auth_class=purchase_order')\" title=\"Override this requirement\"><small>override</small></a>]" : "[<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"\" title=\"Request an override for this requirement\"><small>request override</small></a>]")."
					</div>" :
					"<img src=\"images/alert.gif\" title=\"Minimum deposit has not been received!\" />
					&nbsp;
					<strong>A customer PO is required in order to proceed.</strong>");
				}
				$inner_tbl = "
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
					<tr>
						<td style=\"padding:0;\">
							 <div style=\"background-color:#ffffff;height:100%;\">
								<div style=\"margin:15px 35px;\">
									<h3 style=\"margin-bottom:5px;color:#00477f;\">
										Review Your Purchase Orders
									</h3>
									<div style=\"margin-bottom:10px;\">
										<div style=\"float:right;padding-top:10px;padding-bottom:5px;text-align:right;\">".($auth ?
											$auth_msg : NULL).
											"<div style=\"padding-top:10px;\">".$this->form->button("value=Place Orders", "id=po_btn", "onClick=submit_form(\$('form_placeholder').form, 'purchase_order', 'exec_post', 'refresh_form', 'action=doit_new_po', 'final=1');this.disabled=1")."</div>
										</div>
										<small style=\"margin-left:20px;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'new_po', 'proposal_hash=".$this->proposals->proposal_hash."', 'popup_id=line_item', 'punch=$punch');\"><-- Back</a></small>
									</div>
									<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
										<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
											<p id=\"feedback_message\"></p>
									</div>
									".$content_tbl."
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
		else
			$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function po_exists($po_no) {
		$r = $this->db->query("SELECT COUNT(*) AS Total
							   FROM purchase_order
							   WHERE purchase_order.po_no = '$po_no' OR purchase_order.po_no LIKE '%-".$po_no."'");
		return $this->db->result($r, 0, 'Total');
	}
    
    /**
     * Checks if po with the same seed and number exists
     * 
     * If the po number is the same, but the seed is different, this
     * method will not count them as the same.
     * 
     * @param int $po_no
     * @param int $seed
     * 
     * @return int $total
     */
    function po_exists_seed($po_no, $seed) {
        $r = $this->db->query("SELECT COUNT(*) AS Total
                               FROM purchase_order
                               WHERE purchase_order.po_no = '{$seed}{$po_no}' OR purchase_order.po_no REGEXP '{$seed}([a-zA-Z0-9]*-)?{$po_no}'");
        return $this->db->result($r, 0, 'Total');
    }
    
	function revert_po($po_hash) {
		$this->db->query("UPDATE work_order_items
						  SET `po_line_no` = 0 , `po_hash` = '' , `status` = 0
						  WHERE `po_hash` = '$po_hash'");
		$this->db->query("UPDATE line_items
						  SET `po_line_no` = 0 , `po_hash` = '' , `status` = 0
						  WHERE `po_hash` = '$po_hash'");
		$this->db->query("DELETE FROM purchase_order
						  WHERE `po_hash` = '$po_hash'");
	}










}
?>