<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class pm_module extends AJAX_library {

	var $punch_items = array();
	var $units = array(
    	'H'  => "Hour",
		'D'  => "Day",
		'HD' => "Half Day"
	);

	public $order_hash;
	public $item_hash;

	public $current_item = array();
    public $current_order = array();

	public $status_icons = array(
    	'BK'	=>	array(
        	"img"	=>	"<img src=\"images/green.gif\">",
            "msg"	=>	"Booked"
    	),
		'PR'	=>	array(
    		"img"	=>	"",
			"msg"	=>	"Proposed"
    	),
		'IN'	=>	array(
    		"img"	=>	"<img src=\"images/blue.gif\">",
			"msg"	=>	"Invoiced"
    	)
    );

	function pm_module($proposal_hash=NULL,$proposal_lock=NULL) {
		global $db;

		$this->current_hash = $_SESSION['id_hash'];

		if ($proposal_hash) {
			$this->proposal_hash = $proposal_hash;
			if ($proposal_lock)
				$this->lock = $proposal_lock;
		}

		$this->db =& $db;
		$this->form = new form;
		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);

		if (!$_POST['active_search'] && $this->page_pref = $this->p->page_pref(get_class($this))) {
			if ($this->page_pref['show'] != '*')
				$sql_p[] = ($this->page_pref['show'] == 2 ?
					"line_items.po_hash = ''" : "line_items.po_hash != '1'");
			if ($this->page_pref['sort_from_date'] && !$this->page_pref['sort_to_date'])
				$sql_p[] = "work_orders.order_date >= '".$this->page_pref['sort_from_date']."'";
			elseif ($this->page_pref['sort_from_date'] && $this->page_pref['sort_to_date'] && strtotime($this->page_pref['sort_to_date']) > strtotime($this->page_pref['sort_from_date']))
				$sql_p[] = "work_orders.order_date BETWEEN '".$this->page_pref['sort_from_date']."' AND '".$this->page_pref['sort_to_date']."'";
			if ($this->page_pref['sales_rep']) {
				if (!is_array($this->page_pref['sales_rep']))
					$this->page_pref['sales_rep'] = array($this->page_pref['sales_rep']);

				$this->page_pref['sales_rep'] = array_unique($this->page_pref['sales_rep']);
				$this->page_pref['sales_rep'] = array_values($this->page_pref['sales_rep']);
				array_walk($this->page_pref['sales_rep'],'add_quotes',"'");
				$sql_p[] = "proposals.sales_hash IN (".implode(" , ",$this->page_pref['sales_rep']).")";
				array_walk($this->page_pref['sales_rep'],'strip_quotes');
			}
			if (!$this->active_search)
				$this->page_pref['custom'] = 1;
		}
		if ($proposal_hash) {
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `work_orders`
										WHERE `proposal_hash` = '$proposal_hash'");
			$this->total_work_orders = $this->db->result($result);
		} else {
			if ($sql_p) {
				$sql = implode(" AND ",$sql_p);
				$this->page_pref['str'] = $sql;
				$result = $this->db->query("SELECT COUNT(work_orders.obj_id) as Total
											FROM `work_orders`
											LEFT JOIN `proposals` ON proposals.proposal_hash = work_orders.proposal_hash
											LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
											LEFT JOIN `line_items` ON line_items.work_order_hash = work_orders.order_hash".($sql ? "
											WHERE ".$sql : NULL)."
											GROUP BY work_orders.order_hash");
				$this->total_work_orders = $this->db->num_rows($result);
			} else {
				if ($_POST['active_search']) {
					$result = $this->db->query("SELECT `detail_search` , `query` , `total` , `search_str`
												FROM `search`
												WHERE `search_hash` = '".$_POST['active_search']."'");
					$row = $this->db->fetch_assoc($result);
					$total = $row['total'];
					$sql = base64_decode($row['query']);

					$r = $this->db->query("SELECT COUNT(work_orders.obj_id) as Total
											FROM `work_orders`
											LEFT JOIN `proposals` ON proposals.proposal_hash = work_orders.proposal_hash
											LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
											LEFT JOIN `line_items` ON line_items.work_order_hash = work_orders.order_hash".($sql ? "
											WHERE ".$sql : NULL)."
											GROUP BY work_orders.order_hash");
					$this->total_work_orders = $this->db->num_rows($r);

					if ($this->total_work_orders != $total)
						$this->db->query("UPDATE `search`
										  SET `total` = '".$this->total_work_orders."'
										  WHERE `search_hash` = '".$_POST['active_search']."'");

				} else {
					// Limit proposal view to only those owned by sales rep for Trac #1221
					if ($this->p->ck('proposals','S')) {
						$sql = "LEFT JOIN proposals p
								ON w.proposal_hash = p.proposal_hash
								WHERE p.sales_hash = '".$_SESSION['id_hash']."'";
					}
					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM `work_orders` w
												".($sql ? $sql : NULL));
					$this->total_work_orders = $this->db->result($result);
				}
			}
		}
		$result = $this->db->query("SELECT COUNT(line_items.obj_id) AS Total , proposals.punch_final
									FROM `line_items`
									LEFT JOIN `proposals` ON proposals.proposal_hash = line_items.proposal_hash
									WHERE line_items.proposal_hash = '$proposal_hash' AND line_items.punch = 1
									GROUP BY line_items.proposal_hash");
		$this->total_punch = $this->db->result($result,0,'Total');
		$this->punch_final = $this->db->result($result,0,'punch_final');
	}

	function fetch_work_orders($start=NULL,$order_by=NULL,$order_dir=NULL) {
		$end = MAIN_PAGNATION_NUM;

		if ($this->active_search) {
			$result = $this->db->query("SELECT `detail_search` , `query` , `total` , `search_str`
										FROM `search`
								  		WHERE `search_hash` = '".$this->active_search."'");
			$row = $this->db->fetch_assoc($result);
			$this->total = $row['total'];
			$sql = base64_decode($row['query']);
			unset($this->page_pref['custom']);
			$this->detail_search = $row['detail_search'];
			$this->search_vars = $this->p->load_search_vars($row['search_str']);
		} elseif ($this->page_pref['str'])
			$sql = $this->page_pref['str'];

		// Limit proposal view to only those owned by sales rep for Trac #1221
		if ($this->p->ck('proposals','S'))
			$sql .= ($sql ? " AND " : NULL)."proposals.sales_hash = '".$_SESSION['id_hash']."' ";

		if ($order_by == "order_no" || $order_by == "work_orders.order_no")
			$order_by = "int_order_no";

		$this->work_orders = array();
		$result = $this->db->query("SELECT work_orders.order_hash , work_orders.order_no , work_orders.order_complete ,
									work_orders.order_date , work_orders.order_descr ,
									SUM(work_order_items.true_cost * work_order_items.time) AS total_true_cost ,
									SUM(work_order_items.internal_cost * work_order_items.time) AS total_internal_cost ,
									line_items.item_hash AS line_item_hash , line_items.po_hash AS line_item_po_hash , line_items.invoice_hash AS line_item_invoice_hash ,
									proposals.proposal_no , proposals.proposal_hash , customers.customer_name , users.full_name as sales_rep,
									CAST(substr(order_no,6) as SIGNED) as int_order_no, line_items.status as status
									FROM `work_orders`
									LEFT JOIN `proposals` ON proposals.proposal_hash = work_orders.proposal_hash
									LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
									LEFT JOIN `work_order_items` ON work_order_items.order_hash = work_orders.order_hash
									LEFT JOIN `line_items` ON line_items.work_order_hash = work_orders.order_hash
									LEFT JOIN `users` ON users.id_hash = proposals.sales_hash ".($this->proposal_hash ? "
									WHERE work_orders.proposal_hash = '".$this->proposal_hash."' ".($this->work_order_filter >= 1 ?
										"AND work_orders.order_complete = 1" : NULL) : ($sql ?
											"WHERE ".$sql : NULL))."
									GROUP BY work_orders.order_hash".($order_by ? "
							  		ORDER BY $order_by ".($order_dir ? $order_dir : "ASC").($order_by == "int_order_no" ? ", order_no" : NULL) : NULL).(is_numeric($start) ? "
							  		LIMIT $start , $end" : NULL));
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->work_orders,$row);

		if ($this->work_order_filter)
			unset($this->work_order_filter);
		if ($this->proposal_hash)
			$this->total_work_orders = count($this->work_orders);
		return;
	}

	function fetch_install_jobs($date) {
		// Limit user list if permission checked for Trac #1221
		if ($this->p->ck('proposals','S')) {
			$sql = " AND proposals.sales_hash = '".$_SESSION['id_hash']."'";
		}
		$result = $this->db->query("SELECT proposals.proposal_hash , proposals.proposal_no , proposals.proj_mngr_hash ,
									proposal_install.install_start_time , customers.customer_name as customer , users.full_name as pm
									FROM `proposals`
									LEFT JOIN `proposal_install` ON proposal_install.proposal_hash = proposals.proposal_hash
									LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
									LEFT JOIN `users` ON users.id_hash = proposals.proj_mngr_hash
									WHERE proposal_install.actual_install_date = '$date'".($sql ? $sql : NULL)."
									ORDER BY proposal_install.install_start_time");
		while ($row = $this->db->fetch_assoc($result))
			$install[] = $row;

		return $install;
	}

	function doit() {
		$action = $_POST['action'];
		$btn = $_POST['btn'];
		$start_date = $_POST['start_date'];
		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		if ($action)
			return $this->$action();

		if ($btn == 'save') {
			$proposal_hash = $_POST['proposal_hash'];
			$actual_install_date = $_POST['actual_install_date'];
			$start_hour = $_POST['start_hour'];
			$start_min = $_POST['start_min'];
			$start_am_pm = $_POST['start_am_pm'];
			$proj_mngr = $_POST['proj_mngr'];
			$proj_mngr_hash = $_POST['proj_mngr_hash'];

			if ($proj_mngr && !$proj_mngr_hash) {
				$this->content['error'] = 1;
				$this->content['form_return']['err']['err1'.$this->popup_id] = 1;
				$this->content['form_return']['feedback'] = "The project manager you entered cannot be found. Please make sure you are entering a valid user.";
				return;
			}
			if ($start_hour && $start_min >= 0) {
				if (!$start_am_pm || !$actual_install_date) {
					$this->content['error'] = 1;
					if (!$start_am_pm) {
						$this->content['form_return']['err']['err3'.$this->popup_id] = 1;
						$this->content['form_return']['feedback'] = "Please enter a valid time.";
					}
					if (!$actual_install_date) {
						$this->content['form_return']['err']['err2'.$this->popup_id] = 1;
						$this->content['form_return']['feedback'] = "In order to enter a start time, please enter a valid date.";
					}
					return;
				}
				if ($start_min == 0)
					$start_min = '00';
				$start_time = strtotime($actual_install_date." ".$start_hour.":".$start_min." ".$start_am_pm);
			}
			$p = new proposals($this->current_hash);
			$p->fetch_master_record($proposal_hash);
			if ($p->current_proposal['actual_install_date'] != $actual_install_date)
				$sql_i[] = "`actual_install_date` = '$actual_install_date'";
			if ($p->current_proposal['proj_mngr_hash'] != $proj_mngr_hash) {
				$sql[] = "`proj_mngr_hash` = '$proj_mngr_hash'";
				$sql[] = "`pm_request` = 0";
				$sql[] = "`pm_request_queue` = ''";

				if ($p->current_proposal['pm_request_queue']) {
					$result = $this->db->query("SELECT `sender_hash`
												FROM `messages`
												WHERE `queue_hash` = '".$p->current_proposal['pm_request_queue']."'");
					if ($row = $this->db->fetch_assoc($result)) {

					}
				}
			}
			if ($p->current_proposal['install_start_time'] != $start_time)
				$sql_i[] = "`install_start_time` = '$start_time'";

			if ($sql || $sql_i) {
				if ($sql_i)
					$this->db->query("UPDATE `proposal_install`
									  SET ".implode(" , ",$sql_i)."
									  WHERE `proposal_hash` = '$proposal_hash'");
				if ($sql)
					$this->db->query("UPDATE `proposals`
									  SET ".implode(" , ",$sql)."
									  WHERE `proposal_hash` = '$proposal_hash'");
				$this->content['page_feedback'] = "Proposal has been updated.";
			} else
				$this->content['page_feedback'] = "No changes have been made.";

			$this->content['action'] = 'close';
			$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','calendar','start_date=$start_date','active_search=".$this->active_search."');";
			return;
		}
	}

	function fetch_punch_items($order_by=NULL,$order_dir=NULL) {
		$this->punch_items = array();
		$result = $this->db->query("SELECT line_items.timestamp , line_items.last_change , line_items.item_hash ,
									line_items.line_no ,
									line_items.item_code , line_items.group_hash , line_items.proposal_hash ,
									line_items.po_hash , line_items.invoice_hash , line_items.ack_no ,
									line_items.active , line_items.status , line_items.import_complete ,
									line_items.special as cr , line_items.vendor_hash , line_items.ship_to_hash , line_items.product_hash ,
									line_items.panel_id , line_items.qty , line_items.item_no ,
									line_items.item_descr , line_items.item_tag1 , line_items.item_tag2 ,
									line_items.item_tag3 , line_items.discount_hash , line_items.discount_item_hash ,
									line_items.discount1 , line_items.discount2 , line_items.discount3 ,
									line_items.discount4 , line_items.discount5 , line_items.gp_type ,
									line_items.list , line_items.cost , line_items.sell ,
									SUBSTRING(line_items.import_data,1,5) AS `import_data` , (line_items.sell * line_items.qty) AS ext_sell ,
									(line_items.cost * line_items.qty) AS ext_cost ,
									FORMAT((line_items.sell - line_items.cost) / line_items.sell * 100 , 2) AS gp_margin ,
									vendors.vendor_name , line_groups.group_descr , vendor_products.product_name ,
									vendor_products.catalog_code , vendor_products.separate_po ,
									discounting.discount_descr , discounting.discount_id , discount_details.discount_type
									FROM `line_items`
									LEFT JOIN line_groups ON line_groups.group_hash = line_items.group_hash
									LEFT JOIN vendors ON vendors.vendor_hash = line_items.vendor_hash
									LEFT JOIN vendor_products ON vendor_products.product_hash = line_items.product_hash
									LEFT JOIN discounting ON discounting.discount_hash = line_items.discount_hash
									LEFT JOIN discount_details ON discount_details.item_hash = line_items.discount_item_hash
									WHERE line_items.proposal_hash = '".$this->proposal_hash."' AND line_items.punch = 1
							  		ORDER BY line_items.line_no ASC");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->punch_items,$row);

		$this->total_punch = count($this->punch_items);
		return;
	}

	function doit_new_punch() {
		$punch_item = $_POST['punch_item_list'];
		$proposal_hash = $_POST['proposal_hash'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$action_from = $_POST['action_from'];

		$proposals = new proposals($this->current_hash);
		$item_no = 10000;
		if ($action_from == 'existing') {
			for ($i = 0; $i < count($punch_item); $i++) {
				if ($punch_item[$i])
					$punch[] = $punch_item[$i];
			}
			if (!$punch) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "Please select at least 1 line item to be reorderd.";
				return;
			}

			$lines = new line_items($proposal_hash,1);
			for ($i = 0; $i < count($punch); $i++) {
				$lines->fetch_line_item_record($punch[$i]);

				$item_hash = md5(global_classes::get_rand_id(32,"global_classes"));
				while (global_classes::key_exists('item_hash_index','item_hash',$item_hash))
					$item_hash = md5(global_classes::get_rand_id(32,"global_classes"));

				$this->db->query("INSERT INTO `item_hash_index`
								  (`item_hash`)
								  VALUES('$item_hash')");

				$this->db->query("INSERT INTO `line_items`
								  (`timestamp` , `last_change` , `item_hash` , `punch` , `line_no` , `proposal_hash` , `vendor_hash` , `ship_to_hash` , `product_hash` ,
								  `import_complete` , `special` , `panel_id` , `qty` , `item_no` , `item_descr` , `item_tag1` , `item_tag2` , `item_tag3` ,
								  `discount_hash` , `discount_item_hash` , `discount1` , `discount2` , `discount3` , `discount4` , `discount5` , `list` , `cost` , `import_data`)
								  VALUES (".time()." , '".$this->current_hash."' , '$item_hash' ,  '1' , '".($item_no++)."' , '$proposal_hash' , '".$lines->current_line['vendor_hash']."' ,
								  '".$lines->current_line['ship_to_hash']."' , '".$lines->current_line['product_hash']."' ,
								  '".$lines->current_line['import_complete']."' , '".addslashes($lines->current_line['special'])."' , '".addslashes($lines->current_line['panel_id'])."' ,
								  '".$lines->current_line['qty']."' , '".addslashes($lines->current_line['item_no'])."' , '".addslashes($lines->current_line['item_descr'])."' ,
								  '".addslashes($lines->current_line['item_tag1'])."' , '".addslashes($lines->current_line['item_tag2'])."' , '".addslashes($lines->current_line['item_tag3'])."' ,
								  '".$lines->current_line['discount_hash']."' , '".$lines->current_line['discount_item_hash']."' , '".$lines->current_line['discount1']."' ,
								  '".$lines->current_line['discount2']."' , '".$lines->current_line['discount3']."' , '".$lines->current_line['discount4']."' , '".$lines->current_line['discount5']."' ,
								  '".$lines->current_line['list']."' , '".$lines->current_line['cost']."' , '".addslashes($lines->current_line['import_data'])."')");
                $lines->item_tax($item_hash);
			}
			$this->db->query("UPDATE `proposals`
							  SET `proposal_status` = 'PL' , `status_comment` = 'Punchlist created as of ".date(DATE_FORMAT,time())."'
							  WHERE `proposal_hash` = '".$proposal_hash."'");
			$proposals->reorder_lines($proposal_hash,1);
			$proposals->unset_final($proposal_hash,1);
		} else {

			$proposals->popup_id = $this->popup_id;
			if ($result = $proposals->doit_line_item()) {
				$this->db->query("UPDATE `proposals`
								  SET `proposal_status` = 'PL' , `status_comment` = 'Punchlist created as of ".date(DATE_FORMAT,time())."'
								  WHERE `proposal_hash` = '".$proposal_hash."'");
			} else {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = $proposals->content['form_return']['feedback'];
				$this->content['form_return']['err'] = $proposals->content['form_return']['err'];
				return;
			}
		}

		$this->content['action'] = 'close';
		$this->content['page_feedback'] = "Punchlist has been created.";
		$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','punch','proposal_hash=$proposal_hash','order=$order','order_dir=$order_dir');";
		return;
	}

	function import_work_orders() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['proposal_hash'])
			return;

		if (!$this->proposal_hash)
			$this->proposal_hash = $this->ajax_vars['proposal_hash'];

		$function = $this->ajax_vars['function'];
		$punch = $this->ajax_vars['punch'];

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Import Work Orders Into This Proposal";

		$this->work_order_filter = 1;
		$this->fetch_work_orders(0);
		if ($this->total_work_orders > 0) {
			$tmp_tbl .= "
			<div style=\"padding-bottom:15px;font-weight:bold;\">To get started, please select the work order you would like to import:</div>";
			for ($i = 0; $i < $this->total_work_orders; $i++) {
				if (!$this->work_orders[$i]['line_item_hash']) {
					$Y = 1;
					$tmp_tbl .= "
					<div style=\"padding-top:5px;margin-left:25px;\">
						".$this->form->radio("name=work_order_import",
						                     "value=".$this->work_orders[$i]['order_hash'],
					                         "id=work_order_import_".$this->work_orders[$i]['order_hash'],
						                     "onClick=if(this.checked){agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','proposal_hash=".$this->proposal_hash."','popup_id={$this->popup_id}','work_order_import=".$this->work_orders[$i]['order_hash']."','punch=$punch');}").
                        "&nbsp;<a href=\"javascript:void(0);\" onClick=\"\$('work_order_import_".$this->work_orders[$i]['order_hash']."').checked=1;agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','proposal_hash=".$this->proposal_hash."','popup_id={$this->popup_id}','work_order_import=".$this->work_orders[$i]['order_hash']."','punch=$punch');\" style=\"text-decoration:none;color:#000000;\">".$this->work_orders[$i]['order_no']." - ".$this->work_orders[$i]['order_descr']."</a>
					</div>";
				}
			}
		}

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
			<tr>
				<td style=\"background-color:#ffffff;padding:15px 20px 25px 20px;\">";
				if (!$Y)
					$tbl .= "
					<div style=\"padding-bottom:15px;font-weight:bold;\">There are no work orders available for importing at this time.</div>";
				else
					$tbl .= $tmp_tbl;
			$tbl .= "
				</td>
			</tr>
		</table>";
		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function edit_item() {

		$this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->proposal_hash = $this->ajax_vars['proposal_hash'];
		$this->content['popup_controls']['popup_title'] = "Create New Punchlist Item";

		$proposals = new proposals($this->current_hash);
		if ( ! $proposals->fetch_master_record($this->proposal_hash) )
			return $this->__trigger_error("System error when attempting to fetch proposal record. Please reload window and try again. <!-- Tried fetching proposal [ {$this->proposal_hash} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		$this->lines = new line_items($this->proposal_hash);
		if ( $this->ajax_vars['item_hash'] ) {

			if ( ! $this->lines->fetch_line_item_record($this->ajax_vars['item_hash']) )
				return $this->__trigger_error("System error when attempting to fetch line item record. Please reload window and try again. <!-- Tried fetching item [ {$this->ajax_vars['item_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			$this->content['popup_controls']['popup_title'] = "Edit Punchlist Item";
		}

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
			"item_hash"		=> $this->lines->item_hash,
			"punch"			=> 1,
			"proposal_hash" => $this->proposal_hash,
			"popup_id"		=> $this->popup_id,
			'p' 			=> $this->ajax_vars['p'],
			'order' 		=> $this->ajax_vars['order'],
			'order_dir' 	=> $this->ajax_vars['order_dir']
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;height:100%;\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"padding:0;font-weight:bold;background-color:#efefef;padding:15px;\">
						<ul id=\"maintab3\" class=\"shadetabs\">
							<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent_punch_1{$this->popup_id}\" onClick=\"expandcontent(this);\" >Damaged Items</a></li>
							<li ><a href=\"javascript:void(0);\" rel=\"tcontent_punch_2{$this->popup_id}\" onClick=\"expandcontent(this);\" >New Items</a></li>
						</ul>
						<div id=\"tcontent_punch_1{$this->popup_id}\" class=\"tabcontent\">
							<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
								<tr>
									<td style=\"padding:10px;background-color:#ffffff;font-weight:normal;\">
										<div style=\"padding-bottom:15px;padding-left:5px;\">
											<div style=\"float:right;margin-left:25px;margin-top:8px;margin-bottom:10px;\">
												".$this->form->button("value=Create Punch","id=primary","onClick=submit_form($('popup_id').form,'pm_module','exec_post','refresh_form','action=doit_new_punch','action_from=existing');")."
											</div>
											If any of the existing line items need to be reordered due to damaged or short ordered product, select those items
											from the list below and click the button to the right.
										 </div>
										 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" class=\"table_list\" >
											<tr class=\"thead\" style=\"font-weight:bold;\">
												<td style=\"width:10px;\">".$this->form->checkbox("name=check_all","value=1","onClick=checkall(document.getElementsByName('punch_item_list[]'),this.checked);")."</td>
												<td style=\"width:200px;vertical-align:bottom;\">Item No.</td>
												<td style=\"width:300px;vertical-align:bottom;\">Item Descr.</td>
												<td style=\"width:90px;text-align:right;vertical-align:bottom;\">Item Cost</td>
											</tr>";

											$__total = 0;
											$_total = $this->lines->fetch_line_items(
												0,
												$this->lines->total
											);

											for ( $i = 0; $i < $this->lines->total; $i++ ) {

												if ( $this->lines->line_info[$i]['status'] && ! $this->lines->line_info[$i]['item_code'] )
													$__total++;
											}


											$border = "border-bottom:1px solid #cccccc;";
											for ( $i = 0; $i < $this->lines->total; $i++ ) {

												if ( $i >= $__total - 1 )
													unset($border);

												if ( $this->lines->line_info[$i]['status'] && ! $this->lines->line_info[$i]['item_code'] ) {

													$line_exists = true;

													$tbl .= "
													<tr style=\"background-color:#efefef;\">
														<td></td>
														<td colspan=\"3\" style=\"padding:5px 0 0 10px;font-style:italic;\">" .
															stripslashes($this->lines->line_info[$i]['vendor_name']) .
															( $this->lines->line_info[$i]['product_name'] ?
																	": " . stripslashes($this->lines->line_info[$i]['product_name']) : NULL
															) . "
														</td>
													</tr>
													<tr style=\"background-color:#efefef;\">
														<td style=\"vertical-align:bottom;cursor:auto;$border\" >" .
															$this->form->checkbox(
																"name=punch_item_list[]",
																"value={$this->lines->line_info[$i]['item_hash']}"
															) . "
														</td>
														<td style=\"vertical-align:bottom;$border\">
															<div >{$this->lines->line_info[$i]['item_no']}</div>
														</td>
														<td style=\"vertical-align:bottom;$border\" " .
														( strlen($this->lines->line_info[$i]['item_descr']) > 70 ?
															"title=\"" . htmlentities($this->lines->line_info[$i]['item_descr'], ENT_QUOTES) . "\"" : NULL
														) . " $onClick>" .
														( strlen($this->lines->line_info[$i]['item_descr']) > 70 ?
															wordwrap( substr($this->lines->line_info[$i]['item_descr'], 0, 65), 35, "<br />") . "..." :
															( strlen($this->lines->line_info[$i]['item_descr']) > 35 ?
																wordwrap($this->lines->line_info[$i]['item_descr'], 35, "<br />") : nl2br($this->lines->line_info[$i]['item_descr'])
															)
														) . "
														</td>
														<td style=\"vertical-align:bottom;$border\" class=\"num_field\">\$" . number_format($this->lines->line_info[$i]['cost'], 2) . "</td>
													</tr>";

												} else {

													$__total--;
												}
											}

											if ( ! $line_exists ) {

												$tbl .= "
												<tr style=\"background-color:#ffffff\">
													<td></td>
													<td colspan=\"4\" style=\"padding-top:15px;\">There are no line items that qualify for punchlist.</td>
												</tr>";
											}
								$tbl .= "
											</table>
										</div>
									</td>
								</tr>
							</table>
						</div>
						<div id=\"tcontent_punch_2{$this->popup_id}\" class=\"tabcontent\">
							<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
								<tr>
									<td style=\"padding:10px;background-color:#ffffff;font-weight:normal;\">
										<div style=\"padding-bottom:15px;padding-left:5px;\">
											<div style=\"float:right;margin-right:25px;margin-top:8px;margin-bottom:10px;\">
												".$this->form->button("value=Create Punch","id=primary","onClick=submit_form($('popup_id').form,'pm_module','exec_post','refresh_form','action=doit_new_punch','action_from=new');")."
											</div>
											Use the form below to create a new punch item. Use this area only if you are creating punch for an item that is not
											currently listed in the item details of this proposal.
										</div>
									</td>
								</tr>
								<tr>
									<td style=\"padding:10px;background-color:#d6d6d6;font-weight:normal;\">
										<table style=\"width:100%\" >
											<tr>
												<td style=\"vertical-align:top;padding-bottom:5px;\">
													<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1{$this->popup_id}\">Vendor: </div>
													".$this->form->text_box("name=item_vendor",
																			"value=".stripslashes($this->lines->current_line['item_vendor']),
																			"autocomplete=off",
																			($this->lines->current_line['status'] ? "readonly" : NULL),
																			"size=30",
																			"TABINDEX=1",
																			(!$valid || $valid ? "onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('proposals','item_vendor','item_vendor_hash',1);}" : NULL),
																			(!$valid || $valid ? "onKeyUp=if(ka==false && this.value){key_call('proposals','item_vendor','item_vendor_hash',1);}" : NULL),
																			(!$valid || $valid ? "onBlur=key_clear();if(!\$F('item_vendor_hash')){\$('item_product_hash').value='';\$('item_product_hash').disabled='true';submit_form(this.form,'proposals','exec_post','refresh_form','action=fill_discounts');}" : NULL),
																			(!$valid || $valid ? "onKeyDown=clear_values('item_vendor_hash');" : NULL)).
													$this->form->hidden(array("item_vendor_hash" => $this->lines->current_line['vendor_hash']))."
												</td>
												<td style=\"vertical-align:top;padding-bottom:5px;\">
													<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2{$this->popup_id}\">Item Number: </div>
													".$this->form->text_box("name=item_no",
																			"value=".$this->lines->current_line['item_no'],
																			"maxlength=64",
																			"TABINDEX=4",
																			($this->lines->current_line['import_data'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																			($this->lines->current_line['import_data'] ? "title=This item was created from an import, editing has been disabled." : NULL)
																			)."
												</td>
												<td rowspan=\"4\" style=\"vertical-align:top;\">
													<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err3{$this->popup_id}\">Item Description: </div>
													".$this->form->text_area("name=item_descr",
																			 "rows=11",
																			 "TABINDEX=8",
																			 "cols=35",
																			 "value=".stripslashes($this->lines->current_line['item_descr']),
																			 ($this->lines->current_line['import_data'] || $this->lines->current_line['status'] ? "readonly" : ''),
																			 ($this->lines->current_line['import_data'] ? "title=This item was created from an import, editing has been disabled." : NULL)
																			 )."
												</td>
											</tr>
											<tr>
												<td style=\"vertical-align:top;padding-bottom:5px;\">
													<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err4{$this->popup_id}\">Ship To: </div>
													".$this->form->text_box("name=item_ship_to",
																			"value=".($this->lines->item_hash ? $this->lines->current_line['item_ship_to'] : $this->current_proposal['ship_to']),
																			"autocomplete=off",
																			"size=30",
																			"TABINDEX=2",
																			($this->lines->current_line['status'] ? "readonly" : NULL),
																			(!$valid || $valid ? "onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('proposals','item_ship_to','item_ship_to_hash',1);}" : NULL),
																			(!$valid || $valid ? "onKeyUp=if(ka==false && this.value){key_call('proposals','item_ship_to','item_ship_to_hash',1);}" : NULL),
																			(!$valid || $valid ? "onBlur=key_clear();" : NULL),
																			(!$valid || $valid ? "onKeyDown=clear_values('item_ship_to_hash');" : NULL)).
													$this->form->hidden(array("item_ship_to_hash" => ($this->lines->current_line['ship_to_hash'] ? $this->lines->current_line['ship_to_hash'] : $this->current_proposal['ship_to_hash'])))."
												</td>
												<td style=\"vertical-align:top;padding-bottom:5px;\">
													<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err5{$this->popup_id}\">Item Tagging: </div>
													".$this->form->text_box("name=item_tag1","value=".$this->lines->current_line['item_tag1'],"TABINDEX=5","maxlength=128",($this->lines->current_line['status'] ? "readonly" : NULL))."
												</td>
											</tr>
											<tr>
												<td style=\"vertical-align:top;\">
													<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err6{$this->popup_id}\">Product/Service: </div>
													<div id=\"vendor_product_holder\">".($this->lines->current_line['vendor_hash'] ?
														$proposals->innerHTML_customer($this->lines->current_line['vendor_hash'],'item_vendor','vendor_product_holder',$this->lines->current_line['product_hash'],($this->lines->current_line['status'] ? 1 : NULL)) : $this->form->select("item_product_hash",array("Select Vendor First..."),NULL,array(""),"disabled","style=width:195px;"))."
													</div>
												</td>
												<td style=\"vertical-align:top;padding-bottom:5px;\">
													<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err13{$this->popup_id}\">Item Tagging (2): </div>
													".$this->form->text_box("name=item_tag2","value=".$this->lines->current_line['item_tag2'],"TABINDEX=6","maxlength=128",($this->lines->current_line['status'] ? "readonly" : NULL))."
												</td>
											</tr>
											<tr>
												<td style=\"vertical-align:top;padding-top:5px;\" id=\"discount_message{$this->popup_id}\">".($this->lines->item_hash ?
													$disc_vals['discount_message'.$this->popup_id] : NULL)."
												</td>
												<td style=\"vertical-align:top;padding-bottom:5px;\">
													<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err15{$this->popup_id}\">Item Tagging (3): </div>
													".$this->form->text_box("name=item_tag3","value=".$this->lines->current_line['item_tag3'],"maxlength=128","TABINDEX=7",($this->lines->current_line['status'] ? "readonly" : NULL))."
												</td>
											</tr>
										</table>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;padding-left:20px;font-weight:normal;\">
										<table style=\"width:100%\" cellpadding=\"3\" >
											<tr>
												<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err7{$this->popup_id}\">Quantity:</td>
												<td >".$this->form->text_box("name=qty",
																			 "value=".strrev(substr(strstr(strrev($this->lines->current_line['qty']),'.'),1)).(str_replace('0','',substr(strrchr($this->lines->current_line['qty'],'.'),1)) ?
																				str_replace('0','',strrchr($this->lines->current_line['qty'],'.')) : NULL),
																			 "size=5",
																			 ($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																			 "maxlength=13",
																			 "style=text-align:right;",
																			 ($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																			 ((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "onKeyUp=if(this.value){toggle_submit('submit_btn',true);submit_form(this.form,'proposals','exec_post','refresh_form','action=validate_line_item')}" : NULL)
																			 )."
												</td>
												<td rowspan=\"7\" style=\"width:250px;vertical-align:top;background-color:#efefef;border:1px solid #9f9f9f;\">".
													$this->form->hidden(array("discount_hash" => ($copy_from->current_line['discount_hash'] ? $copy_from->current_line['discount_hash'] : $this->lines->current_line['discount_hash']),"discount_item_hash" => ($copy_from->current_line['discount_item_hash'] ? $copy_from->current_line['discount_item_hash'] : $this->lines->current_line['discount_item_hash'])))."
													<table >
														<tr>
															<td style=\"vertical-align:top;text-align:right;\">Discount ID:</td>
															<td id=\"discount_id{$this->popup_id}\">".($copy_from->current_line['discount_id'] || $this->lines->current_line['discount_id'] ?
																($copy_from->current_line['discount_id'] ? $copy_from->current_line['discount_id'] : $this->lines->current_line['discount_id']) : ($copy_from->item_hash || $this->lines->item_hash ? "No Discount Used" : NULL))."
															</td>
														</tr>
														<tr>
															<td style=\"vertical-align:top;text-align:right;\" id=\"disc_menu_holder_x\">
																Description:
																<div id=\"disc_menu{$this->popup_id}\" ".($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "style=\"display:none;\"" : NULL).">".($this->lines->item_hash ?
																	$disc_vals['disc_menu']	: NULL)."
																</div>
															</td>
															<td style=\"vertical-align:top;\" id=\"discount_descr{$this->popup_id}\">".($copy_from->current_line['discount_descr'] || $this->lines->current_line['discount_descr'] ?
																(strlen($this->lines->current_line['discount_descr']) > 15 || strlen($copy_from->current_line['discount_descr']) > 15 ?
																	wordwrap(($copy_from->current_line['discount_descr'] ? $copy_from->current_line['discount_descr'] : $this->lines->current_line['discount_descr']),15,"<br />",true) : ($copy_from->current_line['discount_descr'] ? $copy_from->current_line['discount_descr'] : $this->lines->current_line['discount_descr'])) : NULL)."
															</td>
														</tr>
														<tr>
															<td style=\"vertical-align:top;text-align:right;\">Expiration:</td>
															<td id=\"discount_expiration{$this->popup_id}\">".($copy_from->current_line['discount_expiration_date'] || $this->lines->current_line['discount_expiration_date'] ? "
																<span ".(strtotime(($copy_from->current_line['discount_expiration_date'] ? $copy_from->current_line['discount_expiration_date'] : $this->lines->current_line['discount_expiration_date'])) < strtotime(date("Y-m-d")) ?
																	"style=\"font-weight:bold;color:red;\" title=\"This discount is expired!\"" : (strtotime(($copy_from->current_line['discount_expiration_date'] ? $copy_from->current_line['discount_expiration_date'] : $this->lines->current_line['discount_expiration_date']))-2592000 <= strtotime(date("Y-m-d")) ?
																		"style=\"font-weight:bold;color:#FFD65C;\" title=\"This discount expires in less than a month!\"" : NULL)).">".date("M j, Y",strtotime(($copy_from->current_line['discount_expiration_date'] ? $copy_from->current_line['discount_expiration_date'] : $this->lines->current_line['discount_expiration_date'])))."</span>" : NULL)."
															</td>
														</tr>
														<tr>
															<td style=\"text-align:right;padding-top:20px;\">Extended Cost: </td>
															<td style=\"padding-left:5px;padding-top:20px;\" id=\"extended_cost{$this->popup_id}\">".($this->lines->current_line['ext_cost'] ? "
																$".number_format($this->lines->current_line['ext_cost'],2) : NULL)."
															</td>
														</tr>
													</table>
												</td>
											</tr>
											<tr>
												<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err8{$this->popup_id}\">Item List Price:</td>
												<td >".$this->form->text_box("name=list",
																			 "value=".($this->lines->current_line['list'] ? number_format($this->lines->current_line['list'],2) : NULL),
																			 "maxlength=13",
																			 "size=10",
																			 ($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																			 "style=text-align:right;",
																			 "onBlur=this.value=formatCurrency(this.value);",
																			 ($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																			 ((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "onKeyUp=if(this.value){toggle_submit('submit_btn',true);submit_form(this.form,'proposals','exec_post','refresh_form','action=validate_line_item')}" : NULL)
																			 )."
												</td>
											</tr>
											<tr>
												<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err9{$this->popup_id}\">Discounting:</td>
												<td >
													".$this->form->text_box('name=discount1',
																			'value='.($copy_from ? ($copy_from->current_line['discount1']*100) : ($this->lines->current_line['discount1'] != 0 ? ($this->lines->current_line['discount1']*100) : NULL)),
																			'size=4',
																			'maxlength=6',
																			($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																			'style=text-align:right;',
																			($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																			((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "onKeyUp=toggle_submit('submit_btn',true);submit_form(this.form,'proposals','exec_post','refresh_form','action=validate_line_item')" : NULL))."&nbsp;%&nbsp;
													".$this->form->text_box('name=discount2',
																			'value='.($copy_from ? ($copy_from->current_line['discount2']*100) : ($this->lines->current_line['discount2'] != 0 ? ($this->lines->current_line['discount2']*100) : NULL)),
																			'size=4',
																			'maxlength=6',
																			($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																			'style=text-align:right;',
																			($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																			((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "onKeyUp=toggle_submit('submit_btn',true);submit_form(this.form,'proposals','exec_post','refresh_form','action=validate_line_item')" : NULL))."&nbsp;%&nbsp;
													".$this->form->text_box('name=discount3',
																			'value='.($copy_from ? ($copy_from->current_line['discount3']*100) : ($this->lines->current_line['discount2'] != 0 ? ($this->lines->current_line['discount3']*100) : NULL)),
																			'size=4',
																			($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																			'maxlength=6',
																			'style=text-align:right;',
																			($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																			((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "onKeyUp=toggle_submit('submit_btn',true);submit_form(this.form,'proposals','exec_post','refresh_form','action=validate_line_item')" : NULL))."&nbsp;%&nbsp;
													".$this->form->text_box('name=discount4',
																			'value='.($copy_from ? ($copy_from->current_line['discount4']*100) : ($this->lines->current_line['discount2'] != 0 ? ($this->lines->current_line['discount4']*100) : NULL)),
																			'size=4',
																			($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																			'maxlength=6',
																			'style=text-align:right;',
																			($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																			((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "onKeyUp=toggle_submit('submit_btn',true);submit_form(this.form,'proposals','exec_post','refresh_form','action=validate_line_item')" : NULL))."&nbsp;%&nbsp;
													".$this->form->text_box('name=discount5',
																			'value='.($copy_from ? ($copy_from->current_line['discount5']*100) : ($this->lines->current_line['discount2'] != 0 ? ($this->lines->current_line['discount5']*100) : NULL)),
																			'size=4',
																			($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																			'maxlength=6',
																			'style=text-align:right;',
																			($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																			((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "onKeyUp=toggle_submit('submit_btn',true);submit_form(this.form,'proposals','exec_post','refresh_form','action=validate_line_item')" : NULL))."&nbsp;%&nbsp;
												</td>
											</tr>
											<tr>
												<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err10{$this->popup_id}\">Item Cost:</td>
												<td style=\"vertical-align:bottom;\">".$this->form->text_box("name=cost",
																											 "value=".($this->lines->current_line['cost'] ? number_format($this->lines->current_line['cost'],2) : NULL),
																											 "maxlength=15",
																											 "size=10",
																											 ($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																											 "style=text-align:right;",
																											 "onBlur=this.value=formatCurrency(this.value);",
																											 ($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																											 ((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "" : NULL)
																											 ).
																						$this->form->hidden(array('gp_margin' 		=> 	0,
																												  'list_discount'	=> 	0,
																												  'sell'			=>  0))."
												</td>
											</tr>
											<tr>
												<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err12{$this->popup_id}\">Item Sell Price:</td>
												<td style=\"vertical-align:bottom;\">".$this->form->text_box("name=sell",
																											 "value=".($this->lines->current_line['sell'] ? number_format($this->lines->current_line['sell'],2) : ($copy_from ? number_format($copy_from->current_line['sell'],2) : NULL)),
																											 "maxlength=13",
																											 "size=10",
																											 ($valid && !$this->p->ck(get_class($this),'E','item_details') ? "readonly" : NULL),
																											 "style=text-align:right;",
																											 ($this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "readonly" : NULL),
																											 "onChange=clear_values('gp_margin')",
																											 "onBlur=this.value=formatCurrency(this.value);",
																											 ((!$valid || ($valid && $this->p->ck(get_class($this),'E','item_details'))) && !$this->lines->current_line['status'] ? "onKeyUp=if(this.value){toggle_submit('submit_btn',true);submit_form(this.form,'proposals','exec_post','refresh_form','action=validate_line_item','from_field=sell')}" : NULL))."
												</td>
											</tr>
											<tr>
												<td colspan=\"2\"></td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;

		if (!$valid)
			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab3\')',20);";
		return;
	}

	function punch_item_menu($local=NULL,$punch_final=NULL) {
		if (!$this->proposal_hash) {
			$proposal_hash = $this->ajax_vars['proposal_hash'];
			$this->proposals = new proposals($this->current_hash);

			$valid = $this->proposals->fetch_master_record($proposal_hash);

			$lock = 1;
		} else
			$lock = $this->lock;

		$this->lines = new line_items($this->proposal_hash,1);
		if ($punch_final || $this->proposals->current_proposal['punch_final'])
			$punch_final = 1;

		$item_menu = ($this->p->ck(get_class($this),'A','punchlist') ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_item','proposal_hash=".$this->proposal_hash."','popup_id=line_item');\" class=\"link_standard\"><img src=\"images/plus.gif\" title=\"Add new line item\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this),'D','punchlist') ? "
		<a href=\"javascript:void(0);\" onClick=\"if (confirm('Are you sure you want to delete the selected line items? This action CAN NOT be undone!')){submit_form($('check_all').form,'proposals','exec_post','refresh_form','action=doit_line_item','method=tag','method_action=rm','proposal_hash=".$this->proposal_hash."','punch=1');}\" class=\"link_standard\"><img src=\"images/rm_lineitem.gif\" title=\"Delete selected line items\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this),'E','punchlist') ? "
		<a href=\"javascript:void(0);\" onClick=\"submit_form($('check_all').form,'proposals','exec_post','refresh_form','action=doit_line_item','method=tag','method_action=inactive','proposal_hash=".$this->proposal_hash."','punch=1');\" class=\"link_standard\"><img src=\"images/line_inactive.gif\" title=\"Toggle the selected items between active & inactive.\" border=\"0\" /></a>
		&nbsp;
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_group','proposal_hash=".$this->proposal_hash."','popup_id=line_group','punch=1');\" class=\"link_standard\"><img src=\"images/new_group.gif\" title=\"Create & edit proposal groups\" border=\"0\" /></a>
		&nbsp;" : NULL);
		if (count($this->lines->proposal_groups['group_descr']) && $this->p->ck(get_class($this),'E','punchlist')) {
			$item_menu .= "
			<a href=\"javascript:void(0);\" onClick=\"position_element($('punch_groupList'),findPos(this,'top')+20,findPos(this,'left'));toggle_display('punch_groupList','block');\" class=\"link_standard\"><img src=\"images/addto_group.gif\" title=\"Add selected items to a group\" border=\"0\" /></a>
			<div id=\"punch_groupList\" class=\"function_menu\">
				<div style=\"float:right;padding:3px\">
					<a href=\"javascript:void(0);\" onClick=\"toggle_display('punch_groupList','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
				</div>
				<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Add Selected Items To:</div>";
			for ($i = 0; $i < count($this->lines->proposal_groups['group_descr']); $i++)
				$item_menu .= "
				<div class=\"function_menu_item\">
					".$this->form->radio('name=group_hash','value='.$this->lines->proposal_groups['group_hash'][$i])."&nbsp;
					".$this->lines->proposal_groups['group_descr'][$i]."
				</div>";

			$item_menu .= "
				<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">".$this->form->button("value=Go","onclick=submit_form($('check_all').form,'proposals','exec_post','refresh_form','action=doit_line_group','method=tag','proposal_hash=".$this->proposal_hash."','punch=1');")."</div>
			</div>
			&nbsp;
			<a href=\"javascript:void(0);\"  onClick=\"submit_form($('check_all').form,'proposals','exec_post','refresh_form','action=doit_line_group','method=tag','proposal_hash=".$this->proposal_hash."','punch=1');\" class=\"link_standard\"><img src=\"images/rm_fromgroup.gif\" title=\"Remove selected items from group\" border=\"0\" /></a>
			&nbsp;";
		}

		$item_menu .= ($this->p->ck(get_class($this),'E','punchlist') ? "
		<a href=\"javascript:void(0);\"  onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_comment','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1');\" class=\"link_standard\"><img src=\"images/line_note.gif\" title=\"Add a new comment line.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this),'V','punchlist') && $this->total_punch > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','export','proposal_hash=".$this->proposal_hash."','popup_id=line_item','action=export','punch=1');\" class=\"link_standard\"><img src=\"images/export.gif\" title=\"Export items from this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this),'E','punchlist') ? "
		<a href=\"javascript:void(0);\"  onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','import_export','proposal_hash=".$this->proposal_hash."','popup_id=line_item','action=import','punch=1');\" class=\"link_standard\"><img src=\"images/import.gif\" title=\"Import items into this proposal.\" border=\"0\" /></a>
		&nbsp;".($this->total_punch > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"position_element($('punch_functions'),findPos(this,'top')+20,findPos(this,'left'));toggle_display('punch_functions','block');\" class=\"link_standard\"><img src=\"images/line_function.gif\" title=\"Perform a function on the selected items.\" border=\"0\" /></a>
		<div id=\"punch_functions\" class=\"function_menu\" >
			<div style=\"float:right;padding:5px\">
				<a href=\"javascript:void(0);\" onClick=\"toggle_display('punch_functions','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
			</div>
			<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Function Menu:</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function',"onClick=if(this.checked){this.checked=false;toggle_display('punch_functions','none');agent.call('proposals','sf_loadcontent','show_popup_window','function_input','function=discounting','proposal_hash=".$this->proposal_hash."','popup_id=line_group','punch=1');}")."&nbsp;Discounting</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function',"onClick=if(this.checked){this.checked=false;toggle_display('punch_functions','none');agent.call('proposals','sf_loadcontent','show_popup_window','function_input','function=discount_id','proposal_hash=".$this->proposal_hash."','popup_id=line_group','punch=1');}")."&nbsp;Change Discount ID</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function',"onClick=if(this.checked){this.checked=false;toggle_display('punch_functions','none');agent.call('proposals','sf_loadcontent','show_popup_window','function_input','function=gp','proposal_hash=".$this->proposal_hash."','popup_id=line_group','punch=1');}")."&nbsp;GP Margins</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function','value=roundup')."&nbsp;Round Sell Price Up</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function','value=rounddown')."&nbsp;Round Sell Price Down</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function','value=zerosell')."&nbsp;Update Items To Zero Sell</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function','value=zerocost')."&nbsp;Update Items To Zero Cost</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function',"onClick=if(this.checked){this.checked=false;toggle_display('punch_functions','none');agent.call('proposals','sf_loadcontent','show_popup_window','function_input','function=list','proposal_hash=".$this->proposal_hash."','popup_id=line_group','punch=1');}")."&nbsp;Adjust List Pricing</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function',"onClick=if(this.checked){this.checked=false;toggle_display('punch_functions','none');agent.call('proposals','sf_loadcontent','show_popup_window','function_input','function=shipto','proposal_hash=".$this->proposal_hash."','popup_id=line_group','punch=1');}")."&nbsp;Change Shipping Location</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function',"onClick=if(this.checked){this.checked=false;toggle_display('punch_functions','none');agent.call('proposals','sf_loadcontent','show_popup_window','function_input','function=tagging','proposal_hash=".$this->proposal_hash."','popup_id=line_group','punch=1');}")."&nbsp;Add Tagging Information</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function',"onClick=if(this.checked){this.checked=false;toggle_display('punch_functions','none');agent.call('proposals','sf_loadcontent','show_popup_window','function_input','function=smart_group','proposal_hash=".$this->proposal_hash."','popup_id=line_group','punch=1');}")."&nbsp;Smart Grouping</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function','value=reindex')."&nbsp;Re-number Line Items</div>
			<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">".$this->form->button("value=Go","onclick=toggle_display('punch_functions','none');submit_form(this.form,'proposals','exec_post','refresh_form','action=doit_line_function','continue_action=continue','punch=1');")."</div>
		</div>
		&nbsp;" : NULL).($this->total_punch > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','import_work_orders','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1');\" class=\"link_standard\"><img src=\"images/import_workorders.gif\" title=\"Import work orders.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this),'V','punchlist') ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals','sf_loadcontent','cf_loadcontent','summarized','otf=1','proposal_hash=".$this->proposal_hash."','punch=1');\" class=\"link_standard\"><img src=\"images/summary.gif\" title=\"Summarize line items.\" border=\"0\" /></a>
		&nbsp;" : NULL) : NULL).(!$punch_final && $this->total_punch > 0 ? ($this->p->ck(get_class($this),'E','punchlist') ? "
		<a href=\"javascript:void(0);\"  onClick=\"agent.call('proposals','sf_loadcontent','cf_loadcontent','finalize','otf=1','proposal_hash=".$this->proposal_hash."','punch=1');\" class=\"link_standard\"><img src=\"images/finalize.gif\" title=\"Finalize this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL) : ($punch_final && $this->total_punch > 0 ? ($this->p->ck(get_class($this),'E','punchlist') ? "
		<a href=\"javascript:void(0);\"  onClick=\"submit_form($('proposal_hash').form,'proposals','exec_post','refresh_form','action=unset_final','manual=1','punch=1');\" class=\"link_standard\"><img src=\"images/undo_final.gif\" title=\"Remove finalization flags from this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL) : NULL).($this->p->ck(get_class($this),'V','punchlist') && $this->total_punch > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','print_proposal','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1');\" class=\"link_standard\"><img src=\"images/print.gif\" title=\"Print this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL))."&nbsp;";

		if ($local)
			return $item_menu;
		else
			$this->content['html']['punch_item_funcs'] = $item_menu;

	}

	function po_invoice_menu($proposal_hash) {
		$r = $this->db->query("SELECT `punch_final`
							   FROM `proposals`
							   WHERE `proposal_hash` = '$proposal_hash'");
		if ($final = $this->db->result($r,0,'punch_final'))
			return ($this->p->ck(get_class($this),'CP','punchlist') ? "
			[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order','sf_loadcontent','show_popup_window','new_po','proposal_hash=".$proposal_hash."','popup_id=line_item','punch=1');\" class=\"link_standard\">create purchase orders</a></small>]" : NULL).($this->p->ck(get_class($this),'CI','punchlist') ? "
			<div style=\"padding-top:5px;\">
				[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice','sf_loadcontent','show_popup_window','new_invoice','proposal_hash=".$proposal_hash."','popup_id=line_item','punch=1');\" class=\"link_standard\">create invoices</a></small>]
			</div>" : NULL);

		return "&nbsp;";
	}

	function punch($local=NULL) {
		$proposal_hash = ($this->proposal_hash ? $this->proposal_hash : $this->ajax_vars['proposal_hash']);
		if (!$this->proposal_hash) {
			$this->proposal_hash = $this->ajax_vars['proposal_hash'];
			$this->proposals = new proposals($this->current_hash);
			$this->proposals->fetch_master_record($this->proposal_hash,1);
			if (!$this->lock)
				$this->lock = $this->proposals->lock;
		}

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];


		$this->lines = new line_items($this->proposal_hash,1);
		$this->fetch_punch_items();

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"8\">".($this->total_punch ? "
								<div style=\"float:right;font-weight:normal;text-align:right;\" id=\"po_invoice_links\">".$this->po_invoice_menu($this->proposal_hash)."</div>
								Showing 1 - ".$this->total_punch." of ".$this->total_punch." Punchlist &amp; Service Items." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;\" id=\"punch_item_funcs\">".$this->punch_item_menu(1,$this->punch_final)."</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"width:10px;\">".$this->form->checkbox("name=punch_check_all","value=1","onClick=checkall(document.getElementsByName('punch_item[]'),this.checked);")."</td>
							<td style=\"width:75px;vertical-align:bottom;\" nowrap>Qty</td>
							<td style=\"width:150px;vertical-align:bottom;\">Item No.</td>
							<td style=\"width:285px;vertical-align:bottom;\">Item Descr.</td>
							<td style=\"width:105px;text-align:right;vertical-align:bottom;\" nowrap>Item Cost</td>
							<td style=\"width:100px;text-align:right;vertical-align:bottom;\" nowrap>Item Sell</td>
							<td style=\"width:100px;text-align:right;vertical-align:bottom;\" nowrap>Ext Sell</td>
							<td style=\"width:100px;text-align:right;vertical-align:bottom;\" nowrap>GP</td>
						</tr>";

						//Comments first
						if (count($this->lines->proposal_comments)) {
							$comments = "
							<table style=\"width:90%;\" cellpadding=\"3\">";
							for ($j = 0; $j < count($this->lines->proposal_comments); $j++)
								$comments .= ($j == 0 ? "
								<tr>
									<td style=\"background-color:#cccccc;\" ></td>
								</tr>" : NULL)."
								<tr ".($this->p->ck(get_class($this),'V','punchlist') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_comment','comment_hash=".$this->lines->proposal_comments[$j]['comment_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1');\" title=\"Edit/Delete this comment.\"" : NULL).">
									<td>
										".(($this->lines->proposal_comments[$j]['comment_action'] == 1 && $this->lines->proposal_comments[$j]['comment_vendor']) || $this->lines->proposal_comments[$j]['comment_action'] == '*' ? "
											<strong>".($this->lines->proposal_comments[$j]['comment_action'] == 1 ?
												$this->lines->proposal_comments[$j]['comment_vendor'] : "All Vendor Pos")." : </strong>" : NULL)."
										".nl2br($this->lines->proposal_comments[$j]['comments'])."
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#cccccc;\" ></td>
								</tr>";
							$comments .= "
							</table>";

							$tbl .= "
							<tr style=\"background-color:#FFFF99;\">
								<td colspan=\"2\" style=\"border-bottom:2px solid #cccccc;\">&nbsp;</td>
								<td colspan=\"6\" style=\"height:13px;padding:5px;border-bottom:2px solid #cccccc;font-style:italic;\">
									<img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('punch_note_holder');\" name=\"imgpunch_note_holder\" />
									&nbsp;
									<span onClick=\"shoh('punch_note_holder');\" style=\"cursor:hand;\">".count($this->lines->proposal_comments)." Comment".(count($this->lines->proposal_comments) > 1 ? "s" : NULL)."</span>
									<div id=\"punch_note_holder\" style=\"display:none;padding-top:10px;margin-left:25px;font-style:normal;\">".$comments."</div>
								</td>
							</tr>";
						}

						$group_colors = array("group_row1","group_row2");
						$group_header = array("#D6D6D6","#C1DAF0");
						$num_groups = 0;
						for ($i = 0; $i < $this->total_punch; $i++) {
							$b++;
							if ($this->p->ck(get_class($this),'V','punchlist'))
    							$onClick = "";

							if ($this->punch_items[$i]['group_hash'] && $this->punch_items[$i]['group_hash'] != $this->punch_items[$i-1]['group_hash']) {
								$num_groups++;
								$tbl .= "
								<tr style=\"background-color:".$group_header[($num_groups % 2)]."\">
									<td ></td>
									<td colspan=\"7\" style=\"font-weight:bold;\">
										<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_group','proposal_hash=".$this->proposal_hash."','group_hash=".$this->punch_items[$i]['group_hash']."','popup_id=line_group','punch=1');\" class=\"link_standard\" title=\"Edit/Delete this group\">
											Group: ".nl2br($this->punch_items[$i]['group_descr'])."
										</a>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#cccccc;\" colspan=\"8\"></td>
								</tr>";
							}

							if ($this->punch_items[$i]['group_hash'] && $this->punch_items[$i]['active']) {
								$grp_cost += $this->punch_items[$i]['cost'];
								$grp_ext_cost += ($this->punch_items[$i]['cost'] * $this->punch_items[$i]['qty']);
								$grp_sell += $this->punch_items[$i]['sell'];
								$grp_ext_sell += $this->punch_items[$i]['ext_sell'];
							}
							$tbl .= "
							<tr ".($this->punch_items[$i]['group_hash'] ? "class=\"".$group_colors[($num_groups % 2)]."\"" : NULL)." ".(!$this->punch_items[$i]['active'] ? "style=\"color:#858585;\"" : NULL)." id=\"vendor_top".$this->punch_items[$i]['item_hash']."\" ".(!$this->punch_items[$i]['active'] ? "class=\"inactive_line\"" : NULL)." ".($this->p->ck(get_class($this),'V','punchlist') ? "onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','item_hash=".$this->punch_items[$i]['item_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1','row_lock=".$this->lock."')\" onMouseOver=\"\$('vendor_content".$this->punch_items[$i]['item_hash']."').className='item_row_over';this.className='item_row_over'\" onMouseOut=\"\$('vendor_content".$this->punch_items[$i]['item_hash']."').className='".($this->punch_items[$i]['group_hash'] ? $group_colors[($num_groups % 2)] : NULL)."';this.className='".($this->punch_items[$i]['group_hash'] ? $group_colors[($num_groups % 2)] : NULL)."'\"" : NULL).">
								<td style=\"text-align:center;\">".(!$this->punch_items[$i]['import_data'] && !$this->punch_items[$i]['import_complete'] && !$this->punch_items[$i]['item_code'] ? "
									<img src=\"images/alert.gif\" title=\"This line item has been incompletely specified!\" />" : NULL)."
								</td>
								<td colspan=\"7\" style=\"padding:5px 0 0 10px;font-style:italic;\">".(!$this->current_proposal['final'] && $this->punch_items[$i]['discount_type'] == 'T' ? "
									<div style=\"float:right;\">Tiered discounting to be applied at finalization</div>" : NULL).
									"<span style=\"padding-right:5px;\">Line ".$this->punch_items[$i]['line_no']." : </span>".
									($this->punch_items[$i]['vendor_name'] ?
										stripslashes($this->punch_items[$i]['vendor_name']) : "<img src=\"images/alert.gif\" title=\"No vendor!\">&nbsp;&nbsp;No assigned vendor").($this->punch_items[$i]['product_name'] ? "
											: ".$this->punch_items[$i]['product_name'] : NULL).($this->punch_items[$i]['cr'] ? "
												<div style=\"color:#ff0000;padding-top:5px;margin-left:15px;\">
													Item Special: ".$this->punch_items[$i]['cr']."</div>" : NULL)."
								</td>
							</tr>
							<tr ".($this->punch_items[$i]['group_hash'] ? "class=\"".$group_colors[($num_groups % 2)]."\"" : NULL)." ".(!$this->punch_items[$i]['active'] ? "style=\"color:#858585;\"" : NULL)." id=\"vendor_content".$this->punch_items[$i]['item_hash']."\" ".(!$this->punch_items[$i]['active'] ? "class=\"inactive_line\" title=\"This line is inactive.\"" : NULL)." ".($this->p->ck(get_class($this),'V','punchlist') ? "onMouseOver=\"$('vendor_top".$this->punch_items[$i]['item_hash']."').className='item_row_over';this.className='item_row_over'\" onMouseOut=\"$('vendor_top".$this->punch_items[$i]['item_hash']."').className='".($this->punch_items[$i]['group_hash'] ? $group_colors[($num_groups % 2)] : NULL)."';this.className='".($this->punch_items[$i]['group_hash'] ? $group_colors[($num_groups % 2)] : NULL)."'\"" : NULL)." >
								<td style=\"vertical-align:bottom;cursor:auto;\" >".$this->form->checkbox("name=punch_item[]","value=".$this->punch_items[$i]['item_hash'],($this->punch_items[$i]['status'] ? 'disabled' : NULL),"id=checkbox_".$this->punch_items[$i]['item_hash'])."</td>
								<td style=\"vertical-align:bottom;\" style=\"text-align:left;\" ".($this->p->ck(get_class($this),'V','punchlist') ? "onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','item_hash=".$this->punch_items[$i]['item_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1','row_lock=".$this->lock."')\"" : NULL).">".
									strrev(substr(strstr(strrev($this->punch_items[$i]['qty']),'.'),1)).(str_replace('0','',substr(strrchr($this->punch_items[$i]['qty'],'.'),1)) ?
										str_replace('0','',strrchr($this->punch_items[$i]['qty'],'.')) : NULL)."
								</td>
								<td style=\"vertical-align:bottom;\" ".($this->p->ck(get_class($this),'V','punchlist') ? "onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','item_hash=".$this->punch_items[$i]['item_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1','row_lock=".$this->lock."')\"" : NULL).">
									<div >".$this->punch_items[$i]['item_no']."</div>
								</td>
								<td style=\"vertical-align:bottom;\" ".(strlen($this->punch_items[$i]['item_descr']) > 60 ? "title=\"".htmlspecialchars($this->punch_items[$i]['item_descr'])."\"" : NULL)." ".($this->p->ck(get_class($this),'V','punchlist') ? "onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','item_hash=".$this->punch_items[$i]['item_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1','row_lock=".$this->lock."')\"" : NULL).">".(strlen($this->punch_items[$i]['item_descr']) > 70 ?
									wordwrap(substr(stripslashes($this->punch_items[$i]['item_descr']),0,65),35,"<br />",true)."..." : (strlen($this->punch_items[$i]['item_descr']) > 30 ?
										wordwrap(stripslashes($this->punch_items[$i]['item_descr']),30,"<br />",true) : nl2br(stripslashes($this->punch_items[$i]['item_descr']))))."
								</td>
								<td style=\"vertical-align:bottom;\" class=\"num_field\" ".($this->p->ck(get_class($this),'V','punchlist') ? "onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','item_hash=".$this->punch_items[$i]['item_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1','row_lock=".$this->lock."')\"" : NULL).">$".number_format($this->punch_items[$i]['cost'],2)."</td>
								<td style=\"vertical-align:bottom;\" class=\"num_field\" ".($this->p->ck(get_class($this),'V','punchlist') ? "onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','item_hash=".$this->punch_items[$i]['item_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1','row_lock=".$this->lock."')\"" : NULL).">$".number_format($this->punch_items[$i]['sell'],2)."</td>
								<td style=\"vertical-align:bottom;\" class=\"num_field\" ".($this->p->ck(get_class($this),'V','punchlist') ? "onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','item_hash=".$this->punch_items[$i]['item_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1','row_lock=".$this->lock."')\"" : NULL).">$".number_format($this->punch_items[$i]['ext_sell'],2)."</td>
								<td class=\"num_field\" style=\"vertical-align:bottom;".(defined('MARGIN_FLAG') && $this->punch_items[$i]['gp_margin']*.01 < MARGIN_FLAG ? "color:red;" : NULL)."\" ".($this->p->ck(get_class($this),'V','punchlist') ? "onClick=\"agent.call('proposals','sf_loadcontent','show_popup_window','edit_item','item_hash=".$this->punch_items[$i]['item_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=line_item','punch=1','row_lock=".$this->lock."')\"" : NULL).">".($this->punch_items[$i]['gp_margin'] ?
									round($this->punch_items[$i]['gp_margin'],4)." %" : ($this->punch_items[$i]['cost'] > 0 && $this->punch_items[$i]['sell'] == 0 ?
										"<span style=\"padding-right:15px;\"><img src=\"images/alert.gif\" title=\"Negative GP Margin!\" /></span>" : NULL))."
								</td>
							</tr>
							<tr>
								<td style=\"background-color:#cccccc;\" colspan=\"8\"></td>
							</tr>";

							if ($this->punch_items[$i]['group_hash'] && (!$this->punch_items[$i+1]['group_hash'] || $this->punch_items[$i+1]['group_hash'] != $this->punch_items[$i]['group_hash'])) {
								$tbl .= "
								<tr style=\"background-color:".$group_header[($num_groups % 2)]."\">
									<td colspan=\"2\"></td>
									<td colspan=\"4\" style=\"font-weight:bold;text-align:right;padding-right:15px;\">Group ".$this->punch_items[$i]['group_descr']." Total:</td>
									<td style=\"vertical-align:bottom;font-weight:bold;\" class=\"num_field\">$".number_format($grp_ext_sell,2)."</td>
									<td class=\"num_field\" style=\"vertical-align:bottom;font-weight:bold;" .
        								( defined('MARGIN_FLAG') && math::gp_margin( array(
	        								'cost'  =>  $grp_ext_cost,
	        								'sell'  =>  $grp_ext_sell
        								) ) < MARGIN_FLAG ?
            								"color:red;" : NULL
            							) . "\">" .
            							(float)bcmul( math::gp_margin( array(
	            							'cost'   =>  $grp_ext_cost,
	            							'sell'   =>  $grp_ext_sell
            							) ), 100, 4) . "%
            						</td>
								</tr>
								<tr>
									<td style=\"background-color:#cccccc;\" colspan=\"8\"></td>
								</tr>";

								unset($grp_cost,$grp_ext_cost,$grp_sell,$grp_ext_sell);
							}

							if ($this->punch_items[$i]['active']) {
								$total_cost += $this->punch_items[$i]['cost'];
								$total_ext_cost += ($this->punch_items[$i]['cost'] * $this->punch_items[$i]['qty']);
								$total_sell += $this->punch_items[$i]['sell'];
								$total_ext_sell += $this->punch_items[$i]['ext_sell'];
							}
						}

						if (!$this->total_punch)
							$tbl .= "
							<tr >
								<td colspan=\"8\">There is no punchlist under this proposal.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		if ($local)
			return $tbl;
		else
			$this->content['html']['tcontent9_1'] = $tbl;

		return $tbl;
	}

	function work_orders() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		if (!$this->proposal_hash) {
			$this->proposal_hash = $this->ajax_vars['proposal_hash'];
			$proposals = new proposals($this->current_hash);
			$proposals->fetch_master_record($this->proposal_hash);
		}
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		$this->fetch_work_orders();

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">".($this->total_work_orders ? "
								Showing 1 - ".$this->total_work_orders." of ".$this->total_work_orders." Work Orders." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->p->ck(get_class($this),'A','work_orders') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','proposal_hash=".$this->proposal_hash."','popup_id=work_order_detail');\"><img src=\"images/plus.gif\" title=\"New Work Order\" border=\"0\" /></a>" : NULL)."
								</div>
								&nbsp;
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"vertical-align:bottom;padding-left:15px;\" nowrap>
								<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_orders','otf=1','proposal_hash=".$this->proposal_hash."','order=order_no','order_dir=".($order == "order_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
									Order No.</a>
								".($order == 'order_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"vertical-align:bottom;\">Order Date</td>
							<td style=\"width:300px;vertical-align:bottom;\">Description</td>".($this->p->ck(get_class($this),'VT','work_orders') ? "
							<td style=\"vertical-align:bottom;\" class=\"num_field\">True Cost</td>" : NULL)."
							<td style=\"vertical-align:bottom;\" class=\"num_field\">Internal Cost</td>
							<td style=\"vertical-align:bottom;padding-right:5px;\" class=\"num_field\">Complete</td>
						</tr>";

						for ($i = 0; $i < $this->total_work_orders; $i++)
							$tbl .= "
							<tr ".($this->p->ck(get_class($this),'V','work_orders') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','order_hash=".$this->work_orders[$i]['order_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=work_order_detail')\"" : NULL)." >
								<td style=\"vertical-align:bottom;padding-left:15px;\" style=\"text-align:left;\" >".$this->work_orders[$i]['order_no']."</td>
								<td style=\"vertical-align:bottom;\" >".date(DATE_FORMAT,strtotime($this->work_orders[$i]['order_date']))."</td>
								<td style=\"vertical-align:bottom;\" ".(strlen($this->work_orders[$i]['order_descr']) > 60 ? "title=\"".$this->work_orders[$i]['order_descr']."\"" : NULL)." >".(strlen($this->work_orders[$i]['order_descr']) > 70 ?
									wordwrap(substr($this->work_orders[$i]['order_descr'],0,65),35,"<br />",true)."..." : (strlen($this->work_orders[$i]['order_descr']) > 30 ?
										wordwrap($this->work_orders[$i]['order_descr'],30,"<br />",true) : nl2br($this->work_orders[$i]['order_descr'])))."
								</td>".($this->p->ck(get_class($this),'VT','work_orders') ? "
								<td style=\"text-align:right;\">$".number_format($this->work_orders[$i]['total_true_cost'],2)."</td>" : NULL)."
								<td style=\"text-align:right;\">$".number_format($this->work_orders[$i]['total_internal_cost'],2)."</td>
								<td style=\"text-align:right;padding-right:20px\">".($this->work_orders[$i]['order_complete'] ? "<img src=\"images/check.gif\" />" : NULL)."</td>
							</tr>
							<tr>
								<td style=\"background-color:#cccccc;\" colspan=\"6\"></td>
							</tr>";

						if (!$this->total_work_orders)
							$tbl .= "
							<tr >
								<td colspan=\"6\">There are no work orders against this proposal.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		if ($local)
			return $tbl;
		else
			$this->content['html']['tcontent9_2'] = $tbl;

		return $tbl;
	}

	function print_work_order() {
		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$order_hash = $this->ajax_vars['order_hash'];
		$this->content['popup_controls']['popup_title'] = "Print Work Orders";
		$url = "t=".base64_encode('work_order')."&h=".$proposal_hash."&h2=".$order_hash;


		$tbl .= "
		<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;text-align:center;\">
			<div id=\"loading{$this->popup_id}\" style=\"width:100%;padding-top:50px;padding-bottom:45px;\">
				<h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch{$this->popup_id}\">Loading Document...</h3>
				<div id=\"link_img_holder{$this->popup_id}\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
			</div>
			<iframe src=\"print.php?".$url."\" width=\"100%\" height=\"100%\" style=\"display:none;\" onReadyStateChange=\"if(this.readyState == 4 || this.readyState == 'complete'){toggle_display('loading{$this->popup_id}','none');this.style.display='block'}\"></iframe>
			<div style=\"padding-top:10px;text-align:left;\">
				<small>[<a href=\"print.php?".$url."\" target=\"_blank\" class=\"link_standard\">Open in a separate window</a>]</small>
			</div>
		</div>";

		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function fetch_work_order($order_hash) {
		$this->current_order = array();
		$result = $this->db->query("SELECT work_orders.* , line_items.item_hash as line_item_hash ,
									line_items.po_hash as line_item_po_hash , line_items.invoice_hash as line_item_invoice_hash ,
									line_items.status as line_item_status , proposals.proposal_status ,
									purchase_order.po_hash , customer_invoice.invoice_hash ,
									SUM(work_order_items.true_cost * work_order_items.time) AS total_true_cost ,
									SUM(work_order_items.internal_cost * work_order_items.time) AS total_internal_cost
									FROM `work_orders`
									LEFT JOIN `work_order_items` ON work_order_items.order_hash = work_orders.order_hash
									LEFT JOIN `line_items` ON line_items.work_order_hash = work_orders.order_hash
									LEFT JOIN `purchase_order` ON purchase_order.po_hash = line_items.po_hash
									LEFT JOIN `customer_invoice` ON customer_invoice.invoice_hash = line_items.invoice_hash
									LEFT JOIN `proposals` ON proposals.proposal_hash = work_orders.proposal_hash
									WHERE work_orders.order_hash = '$order_hash'
									GROUP BY work_order_items.order_hash");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_order = $row;
			$this->order_hash = $order_hash;

			if ($this->current_order['ship_to_hash']) {
				list($class,$id,$hash) = explode("|",$this->current_order['ship_to_hash']);
				$obj = new $class($this->current_hash);
				if ($hash) {
					$obj->fetch_location_record($hash);
					$this->current_order['ship_to'] = $obj->current_location['location_name'];
				} else {
					$obj->fetch_master_record($id);
					$this->current_order['ship_to'] = $obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"];;
				}

				unset($obj);
			}


			return true;
		}

		return false;
	}

	function doit_work_order() {
		$order_hash = $_POST['order_hash'];
		$delete = $_POST['delete'];
		$proposal_hash = $_POST['proposal_hash'];
		$from = $_POST['from'];

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

		if ($delete == 1) {
			$this->db->query("DELETE work_orders.* , work_order_items.*
							  FROM `work_orders`
							  LEFT JOIN `work_order_items` ON work_order_items.order_hash = work_orders.order_hash
							  WHERE work_orders.order_hash = '$order_hash'");
			$this->content['action'] = 'close';
			$this->content['page_feedback'] = "Work order has been deleted.";
			if ($from)
				$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_orders','proposal_hash=$proposal_hash','p=$p','order=$order','order_dir=$order_dir')";
			else
				$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','p=$p','order=$order','order_dir=$order_dir');";

			return;
		}

		if ($_POST['order_descr'] && $_POST['order_no'] && $_POST['order_date']) {
			$order_no = $_POST['order_no'];
			$order_complete = $_POST['order_complete'];
			$order_descr = $_POST['order_descr'];
			$order_date = $_POST['order_date'];
			$ship_to_hash = $_POST['ship_to_hash'];
			$ship_to = $_POST['ship_to'];
			$notes = $_POST['notes'];
			$proposal_status = $_POST['proposal_status'];
			$status_comment = $_POST['status_comment'];

			if (!$order_hash) {
				$result = $this->db->query("SELECT COUNT(*) AS Total
											FROM `work_orders`
											WHERE `order_no` = '$order_no'");
				if ($this->db->result($result) > 0) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = "The order number you input is already in use. Please use a unique order number.";
					$this->content['form_return']['err']['err3'.$this->popup_id] = 1;
					return;
				}
			}

			if ($ship_to && !$ship_to_hash) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "We can't seem to find the shipping location you selected. Please make sure you are selecting a valid shipping location from the list.";
				$this->content['form_return']['err']['err3a'.$this->popup_id] = 1;
				return;
			}

			if ($order_hash) {
				$valid = $this->fetch_work_order($order_hash);

				$this->db->query("UPDATE `work_orders`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `order_complete` = '$order_complete' , `order_descr` = '$order_descr' , `ship_to_hash` = '$ship_to_hash' , `notes` = '$notes'
								  WHERE `order_hash` = '{$this->order_hash}'");
				if ($order_complete) {
					if (!$this->current_order['order_complete']) {
						//See if this was submitted via a queue
						$result = $this->db->query("SELECT sales_hash
													FROM proposals
													WHERE proposal_hash = '$proposal_hash'");
						if ($row = $this->db->fetch_assoc($result)) {
							$sender = $row['sales_hash'];

							$message_hash = md5(global_classes::get_rand_id(32,"global_classes"));
							while (global_classes::key_exists('messages','message_hash',$message_hash))
								$message_hash = md5(global_classes::get_rand_id(32,"global_classes"));

							$msg = "Hello ".user_name($sender)."-\n\nYour work order has been completed by ".user_name($this->current_hash)." and is available for preview under the Install Info tab of your <a href=\"javascript:void(0);\" onClick=\"agent.call('proposals','sf_loadcontent','cf_loadcontent','edit_proposal','proposal_hash=".$proposal_hash."')\" class=\"link_standard\">proposal.</a>\n\nAfter reviewing your work order, you may import it into your item details by clicking on the Item Details tab and clicking the icon corresponding to 'Import Work Orders'.";

							$this->db->query("INSERT INTO `messages`
											  (`timestamp` , `message_hash` , `sender_hash` , `recipient_hash` , `subject` , `message`)
											  VALUES(".time()." , '$message_hash' , '".$this->current_hash."' , '$sender' , 'Work order ".$this->current_order['order_no']." is complete.' , '".addslashes($msg)."')");

						}
					}
					$this->db->query("UPDATE `proposal_install`
									  SET `work_order_rqst` = 0 , `work_order_queue` = ''
									  WHERE `proposal_hash` = '$proposal_hash'");
					if ($this->current_order['proposal_status'] == 'H' || $proposal_status || $status_comment)
						$this->db->query("UPDATE `proposals`
										  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `proposal_status` = '".($proposal_status ? $proposal_status : NULL)."' , `status_comment` = '".($status_comment ? $status_comment : "Work order completed")."'
										  WHERE `proposal_hash` = '$proposal_hash'");

				}

				$this->content['action'] = 'close';
				$this->content['jscript_action'] = ($from == 'list' ? "agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','p=$p','order=$order','order_dir=$order_dir')" : "agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_orders','proposal_hash=$proposal_hash')");
				$this->content['page_feedback'] = "Work order has been updated.";
			} else {
				$order_hash = md5(global_classes::get_rand_id(32,"global_classes"));
				while (global_classes::key_exists('work_orders','order_hash',$order_hash))
					$order_hash = md5(global_classes::get_rand_id(32,"global_classes"));

				$this->db->query("INSERT INTO `work_orders`
								  (`timestamp` , `last_change` , `order_hash` , `proposal_hash` , `ship_to_hash` , `order_no` , `order_descr` , `order_date` , `notes` )
								  VALUES (".time()." , '".$this->current_hash."' , '$order_hash' , '$proposal_hash' , '$ship_to_hash' , '$order_no' , '$order_descr' , '$order_date' , '$notes' )");


				// Updated for trac ticket #425
				$intended_wo_no = (defined('WORK_ORDER_SEED') ? WORK_ORDER_SEED : NULL).NEXT_WORK_ORDER_NUM; // What the number should be if the customer didn't change the defaul
				$intended_wo_no_base = NEXT_WORK_ORDER_NUM;
				while (field_exists("work_orders","order_no",$intended_wo_no)) {
					$intended_wo_no++;
					$intended_wo_no_base++;
					$updated = true; // Next work order number automatically incremented
				}
				if ($updated)
					update_sys_data('NEXT_WORK_ORDER_NUM',$intended_wo_no_base);
				// Only increment the next work order number if they use something similiar to the current next wo num
				if (ereg($intended_wo_no_base,$order_no))
					update_sys_data('NEXT_WORK_ORDER_NUM',$intended_wo_no_base + 1);

				if ($proposal_status || $status_comment)
					$this->db->query("UPDATE `proposals`
									  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `proposal_status` = '$proposal_status' , `status_comment` = '$status_comment'
									  WHERE `proposal_hash` = '$proposal_hash'");

				$this->content['action'] = 'continue';
				$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','order_hash=$order_hash','proposal_hash=$proposal_hash','popup_id={$this->popup_id}');setTimeout('agent.call(\'pm_module\',\'sf_loadcontent\',\'cf_loadcontent\',\'work_orders\',\'order_hash=".$order_hash."\',\'proposal_hash=".$proposal_hash."\')',500);";
				$this->content['page_feedback'] = "Work order has been created. You may now begin adding resources.";
			}

		} else {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "Please make sure you complete the indicated fields.";
			if (!$_POST['order_descr']) $this->content['form_return']['err']['err1'.$this->popup_id] = 1;
			if (!$_POST['order_date']) $this->content['form_return']['err']['err2'.$this->popup_id] = 1;
			if (!$_POST['order_no']) $this->content['form_return']['err']['err3'.$this->popup_id] = 1;
			return;
		}
	}

	function doit_save_completion_form() {
		$order_hash = $_POST['order_hash'];

		$comp['completion_date'] = $_POST['completion_date'];
		$comp['completion_total_time'] = $_POST['completion_total_time']*1;
		$comp['completion_warranty'] = $_POST['completion_warranty'];
		$comp['completion_punch'] = $_POST['completion_punch'];
		$comp['completion_service_request'] = $_POST['completion_service_request'];
		$comp['completion_product_request'] = $_POST['completion_product_request'];
		$comp['completion_field_change'] = $_POST['completion_field_change'];
		$comp['completion_sign_off'] = $_POST['completion_sign_off'];
		$comp['completion_delivery'] = $_POST['completion_delivery'];
		$comp['completion_form'] = $_POST['completion_form'];
		$comp['completion_notes'] = $_POST['completion_notes'];
		$comp['completion_sign_off_name'] = $_POST['completion_sign_off_name'];

		$comp = $this->db->prepare_query($comp,"work_orders",'UPDATE');
		$this->db->query("UPDATE `work_orders`
						  SET ".@implode(" , ",$comp)."
						  WHERE `order_hash` = '$order_hash'");
		$this->content['page_feedback'] = "Completion report has been saved.";
		$this->content['action'] = 'continue';
		return;
	}

	function edit_work_order() {

		$this->popup_id = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id'] );

		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$proposals = new proposals($this->current_hash);

		if ( $this->ajax_vars['order_hash'] ) {

			if ( ! $this->fetch_work_order($this->ajax_vars['order_hash']) )
    			return $this->__trigger_error("System error encountered when attempting to lookup work order record. Please reload window and try again. <!-- Tried fetching work order [ {$this->ajax_vars['order_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			$proposal_hash = $this->current_order['proposal_hash'];
		} elseif ( $proposal_hash ) {

			if ( ! $proposals->fetch_master_record($proposal_hash) )
    			return $this->__trigger_error("System error encountered when attempting to lookup proposal record. Please reload window and try again. <!-- Tried fetching proposal [ $proposal_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
		}

		$this->content['popup_controls']['popup_id'] = $this->popup_id;
		$this->content['popup_controls']['popup_title'] = ( $this->order_hash ? "View / Edit Work Order" : "Create a New Work Order" );

		if ( ! $this->order_hash ) { # Trac #425

			$next_work_order_num = ( defined('WORK_ORDER_SEED') ? WORK_ORDER_SEED : NULL ) . NEXT_WORK_ORDER_NUM;
			while ( field_exists("work_orders", "order_no", $next_work_order_num) )
				$next_work_order_num++;
		}

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
    		"order_hash"      => $this->order_hash,
			"proposal_hash"   => $proposal_hash,
			"popup_id"        => $this->popup_id,
			"from"            => $this->ajax_vars['from']
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
								<div style=\"float:right;padding-right:15px;margin-top:35px;\">" .
								$this->p->lock_stat(
    								get_class($this),
    								$this->popup_id
    							) . "
    							</div>
								<h3 style=\"margin-bottom:5px;color:#00477f;\">" .
    							( $this->order_hash ?
									"Edit Work Order : {$this->current_order['order_no']}"
        							:
        							"New Work Order" .
        							( $proposal_hash ?
            							" : Proposal {$proposals->current_proposal['proposal_no']} (" .
            							( strlen($proposals->current_proposal['proposal_descr']) > 20 ?
                							stripslashes( substr($proposals->current_proposal['proposal_descr'], 0, 17) ) . "..." : stripslashes($proposals->current_proposal['proposal_descr'])
                						) . ")" : NULL
                					)
                				) . "
                				</h3>" .
                				( $this->p->ck( get_class($this), 'V') ?
    								"<div style=\"font-size:9pt;margin-left:15px;margin-bottom:10px;\">[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals','sf_loadcontent','cf_loadcontent','edit_proposal','proposal_hash=$proposal_hash','back=agent.call(\'pm_module\',\'sf_loadcontent\',\'cf_loadcontent\',\'work_order_list\')');\" title=\"View this proposal\">view this proposal</a></small>]</div>" : NULL
                				) . "
								<div style=\"padding:5px 0 5px 5px;\">" .
								( $this->order_hash ?
									"<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','print_work_order','popup_id=print_win','proposal_hash=$proposal_hash','order_hash={$this->order_hash}');\"><img src=\"images/print.gif\" title=\"Print this work order\" border=\"0\"></a>" : NULL
								) .
								( $proposal_hash && $this->p->ck( get_class($this), 'E', 'work_orders') ?
    								"&nbsp;&nbsp;" .
    								( ! $this->current_order['line_item_hash'] ?
    									"<a href=\"javascript:void(0);\" onClick=\"submit_form(\$('order_hash').form,'pm_module','exec_post','refresh_form','action=doit_work_order');\" class=\"link_standard\"><img src=\"images/save_proposal.gif\" title=\"Save this work order.\" border=\"0\" /></a>" : "<img src=\"images/save_proposal_done.gif\" title=\"This work order has already been imported into a proposal and cannot be changed.\" />"
    								) . "&nbsp;&nbsp;" : NULL
    							) .
    							( $this->order_hash && ! $this->current_order['line_item_hash'] && $this->p->ck( get_class($this), 'D', 'work_orders') ?
									"<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this work order? This action CAN NOT be undone!')){submit_form($('order_hash').form,'pm_module','exec_post','refresh_form','action=doit_work_order','delete=1');}\" class=\"link_standard\"><img src=\"images/kill.gif\" title=\"Delete this work order.\" border=\"0\" /></a>" : NULL
    							) . "
								</div>" .
    							( $this->order_hash ?
									"<ul id=\"work_order_maintab{$this->popup_id}\" class=\"shadetabs\">
										<li class=\"selected\"><a href=\"javascript:void(0);\" rel=\"work_order_tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\" >Scope of Work</a></li>" .
										( $this->p->ck(get_class($this), 'VR', 'work_orders') ?
    										"<li ><a href=\"javascript:void(0);\" rel=\"work_order_tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\" >Resources </a></li>" : NULL
										) .
										( $this->current_order['line_item_hash'] && $this->current_order['line_item_status'] > 0 ?
    										"<li ><a href=\"javascript:void(0);\" rel=\"work_order_tcontent3{$this->popup_id}\" onClick=\"expandcontent(this);\" >Completion Report</a></li>" : NULL
										) . "
									</ul>
									<div id=\"work_order_tcontent1{$this->popup_id}\" class=\"tabcontent\">" : NULL
								);

								if ( $proposal_hash ) {

									$tbl .= "
									<table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:90%;\" >
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%\" id=\"err1{$this->popup_id}\">Description:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
												$this->form->text_box(
    												"name=order_descr",
    												"value=" . stripslashes($this->current_order['order_descr']),
    												"size=40",
    												"maxlength=255"
												) . "
											</td>
										</tr>" .
										( $this->order_hash ?
										"<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%\" >Complete:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
												$this->form->checkbox(
												    "name=order_complete",
												    "value=1",
    												( $this->current_order['order_complete'] ? "checked" : NULL )
    											) . "
											</td>
										</tr>" .
    									( $this->p->ck(get_class($this), 'VT', 'work_orders') ?
											"<tr>
												<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%\" >Total True Cost:</td>
												<td style=\"background-color:#ffffff;padding-top:10px;\" id=\"wo_true_cost{$this->popup_id}\">\$" . number_format($this->current_order['total_true_cost'], 2) . "</td>
											</tr>" : NULL
										) . "
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%\" >Total Internal Cost:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\" id=\"wo_internal_cost{$this->popup_id}\">\$" . number_format($this->current_order['total_internal_cost'], 2) . "</td>
										</tr>" : NULL
										) . "
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%\" id=\"err3{$this->popup_id}\">Order No:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
    											$this->form->text_box(
        											"name=order_no",
        											"value=" . ( $this->current_order['order_no'] ? $this->current_order['order_no'] : $next_work_order_num ),
        											"size=15",
        											( $this->order_hash ? "readonly" : NULL ),
        											"maxlength=12"
        										) . "
        									</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%\" id=\"err2{$this->popup_id}\">Work Order Date:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">
												<div id=\"order_date_holder{$this->popup_id}\"></div>
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%\">Order Status:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
    											$this->form->select(
        											"proposal_status",
        											array("On Hold, Pending Information"),
        											( in_array($proposals->current_proposal['proposal_status'], array('H')) ? $proposals->current_proposal['proposal_status'] : NULL ),
        											array('H')
        										) . "
        									</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%\">Status Note:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
        										$this->form->text_box(
            										"name=status_comment",
            										"value=" . ( $proposals->current_proposal['proposal_status'] == 'H' ? $proposals->current_proposal['status_comment'] : NULL ),
            										"maxlength=128"
            									) . "
            								</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%;vertical-align:top;\" id=\"err3a{$this->popup_id}\">Ship To Location:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
												$this->form->text_box(
    												"name=ship_to",
													"value=" . stripslashes($this->current_order['ship_to']),
													"autocomplete=off",
													"size=40",
													"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('proposals','ship_to','ship_to_hash',1);}",
										            "onKeyUp=if(ka==false && this.value){key_call('proposals','ship_to','ship_to_hash',1);}",
													"onBlur=key_clear();",
													"onKeyDown=clear_values('ship_to_hash');clear_innerHTML('ship_to_holder');"
												) .
												$this->form->hidden( array(
    												"ship_to_hash" => $this->current_order['ship_to_hash']
												) ) . "
												<div id=\"ship_to_holder\" style=\"padding-top:5px\">" .
												( $this->current_order['ship_to_hash'] ?
													$proposals->innerHTML_customer(
    													$this->current_order['ship_to_hash'],
    													'ship_to',
    													'ship_to_holder',
    													$this->current_order['ship_to_hash']
    												) : NULL
    											) . "
												</div>
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#efefef;text-align:right;font-weight:bold;vertical-align:top;padding-top:10px;width:25%\">Notes:</td>
											<td style=\"background-color:#ffffff;padding-top:10px;\">" .
    											$this->form->text_area(
        											"name=notes",
        											"value=" . stripslashes($this->current_order['notes']),
        											"cols=40",
        											"rows=5"
        										) . "
        									</td>
										</tr>" .
        								( $this->current_order['line_item_hash'] ?
											"<tr>
												<td style=\"background-color:#ffffff;vertical-align:top;padding-top:10px;\" colspan=\"2\">
													<div style=\"padding-bottom:10px;font-weight:bold;padding-left:15px;\">" .
													( $this->current_order['line_item_status'] > 0 ?
														"A purchase order has been generated for this work order" .
    													( $this->current_order['invoice_hash'] ?
															" and an invoice has been generated." : "."
    													) : "Work order has been proposed, but not yet ordered."
    												) . "
													</div>
												</td>
											</tr>" : NULL
										) . "
									</table>";
								} else {

									$this->content['focus'] = 'work_order_proposal_no';

									$tbl .= "
									<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;\">
										<tr>
											<td style=\"background-color:#ffffff;padding:15px 20px;\">
												<div style=\"padding-bottom:15px;font-weight:bold;\">To get started, please enter the proposal number for which you are creating a new work order.</div>" .
												$this->form->text_box(
    												"name=work_order_proposal_no",
													"value=",
													"autocomplete=off",
													"size=30",
													"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('mail','work_order_proposal_no','work_order_proposal_hash',1,'popup_id');}",
									                "onKeyUp=if(ka==false && this.value){key_call('mail','work_order_proposal_no','work_order_proposal_hash',1,'popup_id');}",
													"onBlur=key_clear();",
													"onKeyDown=clear_values('work_order_proposal_hash');"
												) .
												$this->form->hidden( array(
    												"work_order_proposal_hash" => ''
												) ) . "
											</td>
										</tr>
									</table>";
								}

								$tbl .=
								( $this->order_hash ?
    								"</div>" .
    								( $this->p->ck(get_class($this), 'VR', 'work_orders') ?
        								"<div id=\"work_order_tcontent2{$this->popup_id}\" class=\"tabcontent\">" . $this->resource_input(1) . "</div>" : NULL
    								) .
    								( $this->current_order['line_item_hash'] && $this->current_order['line_item_status'] > 0 ?
        								"<div id=\"work_order_tcontent3{$this->popup_id}\" class=\"tabcontent\">
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
											<tr>
												<td style=\"background-color:#efefef;padding:15px;\">
													<ul id=\"work_order_subtab1{$this->popup_id}\" class=\"shadetabs\">
														<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"work_order_tcontent3_1{$this->popup_id}\" onClick=\"expandcontent(this);\" >General</a></li>
														<li ><a href=\"javascript:void(0);\" rel=\"work_order_tcontent3_2{$this->popup_id}\" onClick=\"expandcontent(this);\" >Product Issues</a></li>
													</ul>
													<div id=\"work_order_tcontent3_1{$this->popup_id}\" class=\"tabcontent\">
														<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
															<tr>
																<td style=\"background-color:#d6d6d6;border-bottom:1px solid #AAC8C8;padding-left:10px;\">
																	<table style=\"font-size:9pt\" >
																		<tr>
																			<td style=\"text-align:right;font-weight:bold;\" id=\"err3_1{$this->popup_id}\" class=\"smallfont\">Completion Date: </td>
																			<td id=\"completion_date_holder{$this->popup_id}\"></td>
																			<td style=\"padding-left:25px;text-align:right;font-weight:bold;\" id=\"err3_2{$this->popup_id}\" class=\"smallfont\">Total Hours Worked: </td>
																			<td >" .
																			$this->form->text_box(
    																			"name=completion_total_time",
																				"value=" . strrev( substr( strstr( strrev($this->current_order['completion_total_time']), '.'), 1) ) .
    																			( str_replace('0', '', substr( strrchr($this->current_order['completion_total_time'], '.'), 1) ) ?
    																				str_replace('0', '', strrchr($this->current_order['completion_total_time'], '.') ) : NULL
    																			),
																				"size=7",
																				"style=text-align:right"
    																		) . "
																			</td>
																	</table>
																</td>
															</tr>
															<tr>
																<td style=\"text-align:center;\">
																	<table style=\"width:100%;font-size:10pt;text-align:left;\">
																		<tr>
																			<td class=\"smallfont\">" .
    																		$this->form->checkbox(
        																		"name=completion_warranty",
        																		"value=1",
        																		( $this->current_order['completion_warranty'] ? "checked" : NULL)
        																	) . "&nbsp;Warranty
        																	</td>
																			<td class=\"smallfont\">" .
    																		$this->form->checkbox(
        																		"name=completion_punch",
        																		"value=1",
        																		( $this->current_order['completion_punch'] ? "checked" : NULL)
    																		) . "&nbsp;Punch
    																		</td>
																			<td class=\"smallfont\">" .
    																		$this->form->checkbox(
        																		"name=completion_service_request",
        																		"value=1",
        																		( $this->current_order['completion_service_request'] ? "checked" : NULL )
        																	) . "&nbsp;Service Request
        																	</td>
																			<td class=\"smallfont\">" .
    																		$this->form->checkbox(
        																		"name=completion_product_request",
        																		"value=1",
        																		( $this->current_order['completion_product_request'] ? "checked" : NULL )
        																	) . "&nbsp;Product Request
        																	</td>
																		</tr>
																		<tr>
																			<td class=\"smallfont\">" .
    																		$this->form->checkbox(
	    																		"name=completion_field_change",
	    																		"value=1",
	    																		($this->current_order['completion_field_change'] ? "checked" : NULL)
    																		) . "&nbsp;Field Change
    																		</td>
																			<td class=\"smallfont\">" .
    																		$this->form->checkbox(
	    																		"name=completion_sign_off",
	    																		"value=1",
	    																		($this->current_order['completion_sign_off'] ? "checked" : NULL)
    																		) . "&nbsp;Sign-Off
    																		</td>
																			<td class=\"smallfont\">" .
    																		$this->form->checkbox(
	    																		"name=completion_delivery",
	    																		"value=1",
	    																		($this->current_order['completion_delivery'] ? "checked" : NULL)
    																		) . "&nbsp;Delivery
    																		</td>
																			<td class=\"smallfont\">" .
    																		$this->form->checkbox(
	    																		"name=completion_form",
	    																		"value=1",
	    																		($this->current_order['completion_form'] ? "checked" : NULL)
    																		) . "&nbsp;Completion Form
    																		</td>
																		</tr>
																	</table>
																</td>
															</tr>
															<tr>
																<td style=\"background-color:#d6d6d6;border-top:1px solid #AAC8C8;padding-left:10px;\">
																	<table style=\"width:100%;font-size:9pt\" >
																		<tr>
																			<td style=\"vertical-align:top;padding-bottom:5px;\" colspan=\"2\" class=\"smallfont\">
																				<div style=\"padding-bottom:5px;\" id=\"err3_4{$this->popup_id}\">Completion Notes/Service Required: </div>" .
																				$this->form->text_area(
    																				"name=completion_notes",
    																				"value=" . stripslashes($this->current_order['completion_notes']),
    																				"rows=6",
    																				"cols=85"
																				) . "
																			</td>
																		</tr>
																		<tr>
																			<td style=\"vertical-align:top;padding-bottom:5px;\" colspan=\"2\" class=\"smallfont\">
																				<div style=\"padding-bottom:5px;\" id=\"err3_4{$this->popup_id}\">Signed Off By: </div>" .
																				$this->form->text_box(
    																				"name=completion_sign_off_name",
    																				"value=" . stripslashes($this->current_order['comletion_sign_off_name']),
    																				"size=65",
    																				"maxlength=128"
    																			) . "
																			</td>
																		</tr>
																	</table>
																</td>
															</tr>
														</table>
														<div style=\"margin:15px;\">" .
    													( $this->p->ck(get_class($this), "A", "schedule") ?
															$this->form->button(
    															"value=Save Information",
    															"name=submit_btn",
    															"onClick=submit_form(this.form,'pm_module','exec_post','refresh_form','action=doit_save_completion_form');"
															) : NULL
														) . "
														</div>
													</div>
													<div id=\"work_order_tcontent3_2{$this->popup_id}\" class=\"tabcontent\">" . $this->product_completion_punch(1) . "</div>
												</td>
											</tr>
										</table>
									</div>" : NULL
    								) : NULL
    							) . "
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>" .
        $this->form->close_form();

        $this->content['jscript'][] = "setTimeout('DateInput(\'order_date\',true, \'YYYY-MM-DD\',\'" . ( $this->current_order['order_date'] ? $this->current_order['order_date'] : date("Y-m-d") ) . "\',1,\'order_date_holder{$this->popup_id}\')', 19);";

		if ( $this->order_hash ) {

			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'work_order_maintab{$this->popup_id}\')',10);";
			if ( $this->current_order['line_item_hash'] && $this->current_order['line_item_status'] > 0 ) {

				$this->content['jscript'][] = "setTimeout('DateInput(\'completion_date\',false, \'YYYY-MM-DD\',\'" . ( $this->current_order['completion_date'] && $this->current_order['completion_date'] != '0000-00-00' ? $this->current_order['completion_date'] : NULL ) . "\',1,\'completion_date_holder{$this->popup_id}\')', 25);";
				$this->content['jscript'][] = "setTimeout('initializetabcontent(\'work_order_subtab1{$this->popup_id}\')',20);";
			}

			$this->content['popup_controls']['onclose'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_orders','proposal_hash=$proposal_hash','p=$p','order=$order','order_dir=$order_dir')";
		}

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function fetch_completion_report_punch() {
		if (!$this->order_hash)
			return;

		$this->completion_order_punch = array();
		$result = $this->db->query("SELECT work_order_punchlist.* , vendors.vendor_name as punch_vendor
									FROM `work_order_punchlist`
									LEFT JOIN `vendors` ON vendors.vendor_hash = work_order_punchlist.punch_vendor_hash
									WHERE `order_hash` = '{$this->order_hash}'");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->completion_order_punch,$row);

		$this->total_order_punch = count($this->completion_order_punch);
		return;
	}

	function fetch_completion_punch_item($item_hash) {
		$result = $this->db->query("SELECT work_order_punchlist.* , vendors.vendor_name as punch_vendor
									FROM `work_order_punchlist`
									LEFT JOIN `vendors` ON vendors.vendor_hash = work_order_punchlist.punch_vendor_hash
									WHERE `item_hash` = '".$this->ajax_vars['completion_item_hash']."'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_order_punch = $row;
			$this->completion_item_hash = $item_hash;

			return true;
		}

		return false;
	}


	function doit_save_completion_item() {
		$item_hash = $_POST['completion_item_hash'];
		$order_hash = $_POST['order_hash'];
		$delete = $_POST['delete'];

		if ($delete == 1) {
			$this->db->query("DELETE FROM `work_order_punchlist`
							  WHERE `item_hash` = '$item_hash'");
			$this->content['page_feedback'] = "Item has been removed from your work order.";
			$this->content['action'] = 'continue';
			$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','product_completion_punch','action=list','order_hash=$order_hash','popup_id={$this->popup_id}')";
			return;
		}
		if ($_POST['punch_vendor'] && $_POST['punch_descrepancy']) {
			if (!$_POST['punch_vendor_hash']) {
				$this->content['error'] = 1;
				$this->content['form_return']['err']['err3_2_1'.$this->popup_id] = 1;
				$this->content['form_return']['feedback'] = "We can't seem to find the manufacturer you selected. Please make sure you are selecting a valid manufacturer.";
				return;
			}

			$punch['punch_vendor_hash'] = $_POST['punch_vendor_hash'];
			$punch['punch_descrepancy'] = $_POST['punch_descrepancy'];
			$punch['punch_product'] = $_POST['punch_product'];
			$punch['punch_model_no'] = $_POST['punch_model_no'];
			$punch['punch_finish'] = $_POST['punch_finish'];
			$punch['punch_action'] = $_POST['punch_action'];
			$punch['punch_location'] = $_POST['punch_location'];
			$punch['punch_resp'] = $_POST['punch_resp'];
			$punch['punch_approved'] = $_POST['punch_approved'];
			$punch['punch_resolved'] = $_POST['punch_resolved'];

			if ($item_hash) {
				$punch = $this->db->prepare_query($punch,"work_order_punchlist",'UPDATE');
				$this->db->query("UPDATE `work_order_punchlist`
								  SET ".@implode(" , ",$punch)."
								  WHERE `item_hash` = '$item_hash'");
				$this->content['page_feedback'] = "Product item has been updated.";
			} else {
				$item_hash = md5(global_classes::get_rand_id(32,"global_classes"));
				while (global_classes::key_exists('work_order_punchlist','item_hash',$item_hash))
					$item_hash = md5(global_classes::get_rand_id(32,"global_classes"));

				$punch = $this->db->prepare_query($punch,"work_order_punchlist",'INSERT');
				$this->db->query("INSERT INTO `work_order_punchlist`
								  (`item_hash` , `order_hash` ".(is_array($punch) ? ", ".implode(" , ",array_keys($punch)) : NULL).")
								  VALUES('$item_hash' , '$order_hash' ".(is_array($punch) ? ", ".implode(" , ",array_values($punch)) : NULL).")");
				$this->content['page_feedback'] = "Product item has been added.";
			}
			$this->content['action'] = 'continue';
			$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','product_completion_punch','action=list','order_hash=$order_hash','popup_id={$this->popup_id}')";
			return;
		} else {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "You left some required fields blank. Please completed the indicated fields.";
			if (!$_POST['punch_descrepancy']) $this->content['form_return']['err']['err3_2_2'.$this->popup_id] = 1;
			if (!$_POST['punch_vendor']) $this->content['form_return']['err']['err3_2_1'.$this->popup_id] = 1;
			return;
		}
	}

	function product_completion_punch($local=NULL) {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];
		elseif ($_POST['popup_id'])
			$this->popup_id = $_POST['popup_id'];

		$action = $this->ajax_vars['action'];
		if ($this->ajax_vars['order_hash'] && !$this->order_hash) {
			$valid = $this->fetch_work_order($this->ajax_vars['order_hash']);

			if (!$valid)
				unset($this->ajax_vars['order_hash']);
			elseif ($this->ajax_vars['completion_item_hash']) {
				$action = 'add';
				if ($this->ajax_vars['completion_item_hash']) {
					$valid2 = $this->fetch_completion_punch_item($this->ajax_vars['completion_item_hash']);
					if (!$valid2)
						unset($this->ajax_vars['completion_item_hash']);
				}
			}
		}

		if ($action == 'add') {
			$tbl = $this->form->hidden(array('completion_item_hash' => $this->completion_item_hash))."
			<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
				<tr>
					<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\" class=\"smallfont\">
						New Product Issue or Discrepancy
						<div style=\"padding-left:5px;font-weight:normal;padding-top:8px;\">
							<div style=\"float:right;padding-right:25px;\" class=\"smallfont\">
								".$this->form->checkbox("name=punch_approved","value=1",($this->current_order_punch['punch_approved'] ? "checked" : NULL))."&nbsp;Approved
								&nbsp;&nbsp;&nbsp;
								".$this->form->checkbox("name=punch_resolved","value=1",($this->current_order_punch['punch_resolved'] ? "checked" : NULL))."&nbsp;Resolved
							</div>
							<small><a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','product_completion_punch','order_hash={$this->order_hash}','popup_id={$this->popup_id}','action=list')\" class=\"link_standard\"><-- back</a></small>
						</div>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#d6d6d6;border-top:1px solid #AAC8C8;padding-left:20px;\">
						<table style=\"width:100%;font-size:9pt\" >
							<tr>
								<td style=\"vertical-align:top;\" colspan=\"2\" class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" id=\"err3_2_1{$this->popup_id}\">Manufacturer: </div>
									".$this->form->text_box("name=punch_vendor",
															"value=".$this->current_order_punch['punch_vendor'],
															"autocomplete=off",
															"size=30",
															"TABINDEX=1",
															"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('proposals','punch_vendor','punch_vendor_hash',1);}",
			                                                "onKeyUp=if(ka==false && this.value){key_call('proposals','punch_vendor','punch_vendor_hash',1);}",
															"onBlur=key_clear();if(!\$F('punch_vendor_hash')){\$('punch_vendor').value='';}",
															"onKeyDown=clear_values('punch_vendor_hash');").
									$this->form->hidden(array("punch_vendor_hash" => $this->current_order_punch['punch_vendor_hash']))."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\" rowspan=\"2\" class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" id=\"err3_2_2{$this->popup_id}\">Discrepancy: </div>
									".$this->form->text_area("name=punch_descrepancy","value=".$this->current_order_punch['punch_descrepancy'],"rows=4","cols=45")."
								</td>
							</tr>
							<tr>
								<td colspan=\"2\" class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" >Product: </div>
									".$this->form->text_box("name=punch_product",
															"value=".$this->current_order_punch['punch_product'],
															"size=30",
															"maxlength=128")."
								</td>
							</tr>
							<tr>
								<td  class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" >Model No: </div>
									".$this->form->text_box("name=punch_model_no",
															"value=".$this->current_order_punch['punch_model_no'],
															"size=10",
															"maxlength=128")."
								</td>
								<td class=\"smallfont\" >
									<div style=\"padding-bottom:5px;\" >Finish: </div>
									".$this->form->text_box("name=punch_finish",
															"value=".$this->current_order_punch['punch_finish'],
															"size=10",
															"maxlength=64")."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\" rowspan=\"2\" class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" id=\"err2{$this->popup_id}\">Action Taken: </div>
									".$this->form->text_area("name=punch_action","value=".$this->current_order_punch['punch_action'],"rows=4","cols=45")."
								</td>
							</tr>
							<tr>
								<td  class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" >Location: </div>
									".$this->form->text_box("name=punch_location",
															"value=".$this->current_order_punch['punch_location'],
															"size=10",
															"maxlength=128")."
								</td>
								<td  class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" >Resp: </div>
									".$this->form->text_box("name=punch_resp",
															"value=".$this->current_order_punch['punch_resp'],
															"size=10",
															"maxlength=32")."
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<div style=\"margin:15px;\">
				".$this->form->button("value=Save Product Issue","name=submit_btn","onClick=submit_form(this.form,'pm_module','exec_post','refresh_form','action=doit_save_completion_item');")."
				&nbsp;&nbsp;".($this->completion_item_hash ? "
				".$this->form->button("value=Delete Product Issue","onClick=if(confirm('Are you sure you want to remove this product issue from your completion report?')){submit_form(this.form,'pm_module','exec_post','refresh_form','action=doit_save_completion_item','delete=1');}") : NULL)."
			</div>";
		} else {
			$this->fetch_completion_report_punch();

			$tbl .= "
			<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
				<tr>
					<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\" class=\"smallfont\">
						Product Issues &amp; Discrepancies
						<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->p->ck(get_class($this),"A","schedule") ? "
							<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','product_completion_punch','action=add','order_hash={$this->order_hash}','popup_id={$this->popup_id}');\"><img src=\"images/plus.gif\" title=\"New product discrepancy.\" border=\"0\" /></a>" : "&nbsp;")."
						</div>
					</td>
				</tr>
				<tr class=\"thead\" style=\"font-weight:bold;\">
					<td style=\"vertical-align:bottom;\">Manufacturer</td>
					<td style=\"vertical-align:bottom;\">Product</td>
					<td style=\"vertical-align:bottom;\">Model No.</td>
					<td style=\"vertical-align:bottom;text-align:right;width:20px;padding-right:5px;\" class=\"num_field\">Resolved</td>
				</tr>";
				for ($i = 0; $i < $this->total_order_punch; $i++)
					$tbl .= "
					<tr onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','product_completion_punch','order_hash={$this->order_hash}','completion_item_hash=".$this->completion_order_punch[$i]['item_hash']."','action=add','popup_id={$this->popup_id}')\">
						<td class=\"smallfont\">".$this->completion_order_punch[$i]['punch_vendor']."</td>
						<td class=\"smallfont\">".(strlen($this->completion_order_punch[$i]['punch_product']) > 45 ?
							substr($this->completion_order_punch[$i]['punch_product'],0,43)."..." : $this->completion_order_punch[$i]['punch_product'])."</td>
						<td class=\"smallfont\">".(strlen($this->completion_order_punch[$i]['punch_model_no']) > 25 ?
							substr($this->completion_order_punch[$i]['punch_model_no'],0,23)."..." : $this->completion_order_punch[$i]['punch_model_no'])."</td>
						<td style=\"width:20px;padding-right:5px;text-align:right;\"  class=\"smallfont\">".($this->completion_order_punch[$i]['punch_resolved'] ?
							"Y" : NULL)."
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#cccccc;\" colspan=\"4\"></td>
					</tr>";

				if (!$this->total_order_punch)
					$tbl .= "
					<tr >
						<td colspan=\"4\" class=\"smallfont\">There are no product issues or discrepancies to show for this completion report.</td>
					</tr>";

				$tbl .= "
			</table>";
		}
		if ($local)
			return $tbl;
		else
			$this->content['html']["work_order_tcontent3_2".$this->popup_id] = $tbl;
	}

	function fetch_resources_used() {
		$this->resources_used = array();
		$result = $this->db->query("SELECT work_order_items.* , resources.resource_name , resources.vendor_hash ,
									(work_order_items.item_sell * work_order_items.time) AS ext_sell ,
									(work_order_items.true_cost * work_order_items.time) AS ext_true_cost ,
									(work_order_items.internal_cost * work_order_items.time) AS ext_internal_cost
									FROM `work_order_items`
									LEFT JOIN resources ON resources.resource_hash = work_order_items.resource_hash
									WHERE work_order_items.order_hash = '{$this->order_hash}'");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->resources_used,$row);

		$this->total_resources_used = count($this->resources_used);
		return;
	}

	function is_valid_line($item_hash) {
		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `work_order_items`
									WHERE `item_hash` = '$item_hash'");
		if ($this->db->result($result) != 0)
			return true;

		return false;
	}

	function fetch_work_order_item($item_hash) {

		$this->current_item = array();
		unset($this->item_hash);

		$result = $this->db->query("SELECT
										t1.*,
										t1.po_line_no AS line_no,
										t2.resource_name as item_resource,
										t2.vendor_hash,
										( t1.true_cost * t1.time ) AS ext_true_cost,
										( t1.internal_cost * t1.time ) AS ext_internal_cost,
										t4.item_no,
										t4.sell as item_sell,
										t4.invoice_hash as line_item_invoice_hash,
										t4.item_hash as line_item_hash,
										t4.po_hash as line_item_po_hash,
										t5.product_name as item_product,
										t5.product_hash
									FROM work_order_items t1
									LEFT JOIN resources t2 ON t2.resource_hash = t1.resource_hash
									LEFT JOIN work_orders t3 ON t3.order_hash = t1.order_hash
									LEFT JOIN line_items t4 ON t4.work_order_hash = t3.order_hash
									LEFT JOIN vendor_products t5 ON t5.product_hash = t4.product_hash
									WHERE t1.item_hash = '$item_hash'");
		if ( $row = $this->db->fetch_assoc($result) ) {

			$this->current_item = $row;
			$this->item_hash = $item_hash;

			return true;
		}

		return false;
	}

	function fill_resource_data() {
		$item_resource_hash = $_POST['item_resource_hash'];
		$order_hash = $_POST['order_hash'];
		$unit = $_POST['resource_unit'];

		$sys = new system_config();
		$sys->fetch_resource_record($item_resource_hash);

		switch ($unit) {
			case 'H':
			if ($sys->current_resource['cost_hour'] > 0)
				$this->content['value']['item_true_cost'] = number_format($sys->current_resource['cost_hour'],2);
			if ($sys->current_resource['sell_hour'] > 0)
				$this->content['value']['item_internal_cost'] = number_format($sys->current_resource['sell_hour'],2);
			break;

			case 'D':
			if ($sys->current_resource['cost_day'] > 0)
				$this->content['value']['item_true_cost'] = number_format($sys->current_resource['cost_day'],2);
			if ($sys->current_resource['sell_day'] > 0)
				$this->content['value']['item_internal_cost'] = number_format($sys->current_resource['sell_day'],2);
			break;

			case 'HD':
			if ($sys->current_resource['cost_halfday'] > 0)
				$this->content['value']['item_true_cost'] = number_format($sys->current_resource['cost_halfday'],2);
			if ($sys->current_resource['sell_halfday'] > 0)
				$this->content['value']['item_internal_cost'] = number_format($sys->current_resource['sell_halfday'],2);
			break;
		}

		$this->content['action'] = 'continue';
		$this->content['jscript'][] = "submit_form(\$('order_hash').form,'pm_module','exec_post','refresh_form','action=validate_resource_item')";
		return;
	}

    function validate_resource_item($local=NULL) {
        $from_field = $_POST['from_field'];
        $time = ($_POST['time'] ? preg_replace('/[^.0-9]/', "",$_POST['time'])*1 : NULL);
        $item_true_cost = ($_POST['item_true_cost'] ? preg_replace('/[^.0-9]/', "",$_POST['item_true_cost'])*1 : 0);
        $item_internal_cost = ($_POST['item_internal_cost'] ? preg_replace('/[^.0-9]/', "",$_POST['item_internal_cost'])*1 : 0);
        $item_sell = ($_POST['item_sell'] ? preg_replace('/[^.0-9]/', "",$_POST['item_sell'])*1 : NULL);

        if (is_numeric($item_true_cost)) {
            if ($from_field != 'item_true_cost')
                $return['item_true_cost'] = $this->content['value']['item_true_cost'] = ($local ? _round($item_true_cost,2) : '$'.number_format($item_true_cost,2));

            if (is_numeric($time))
                $this->content['html']['extended_true_cost'.$this->popup_id] = ($time * $item_true_cost < 0 ? '($'.number_format(($time * $item_true_cost) * -1,2).')' : '$'.number_format($time * $item_true_cost,2));
        } else
            $this->content['html']['extended_true_cost'.$this->popup_id] = '';

        if (is_numeric($item_internal_cost)) {
            if ($from_field != 'item_internal_cost')
                $return['item_internal_cost'] = $this->content['value']['item_internal_cost'] = ($local ? _round($item_internal_cost,2) : '$'.number_format($item_internal_cost,2));

            if (is_numeric($time))
                $this->content['html']['extended_internal_cost'.$this->popup_id] = ($time * $item_internal_cost < 0 ? '($'.number_format(($time * $item_internal_cost) * -1,2).')' : '$'.number_format($time * $item_internal_cost,2));
            if (is_numeric($item_true_cost)) {
                if (is_numeric($time)) {
                    $this->content['html']['extended_internal_cost'.$this->popup_id] = ($item_internal_cost * $time < 0 ? '($'.number_format(($item_internal_cost * $time) * -1,2).')' : '$'.number_format($item_internal_cost * $time,2));
                    $this->content['html']['profit_dollars_internal'.$this->popup_id] = (($item_internal_cost * $time) - ($item_true_cost * $time) < 0 ? "<span style=\"color:red;\">($".number_format((($item_internal_cost * $time) - ($item_true_cost * $time)) * -1,2).")</span>" : "$".number_format(($item_internal_cost * $time) - ($item_true_cost * $time),2));
                }
            }
        } else {
            $this->content['html']['extended_internal_cost'.$this->popup_id] = '';
            $this->content['html']['profit_dollars_internal'.$this->popup_id] = '';
        }

        if ($local) {
            unset($this->content['value'],$this->content['html']);
            return $return;
        } else {
            $this->content['submit_btn'] = 'submit_btn';
            $this->content['action'] = 'continue';
        }
        return;
    }

	function doit_save_item() {
		$item_hash = $_POST['item_hash'];
		$order_hash = $_POST['order_hash'];
		$delete = $_POST['delete'];

		if ($delete == 1) {
			$this->db->query("DELETE FROM `work_order_items`
							  WHERE `item_hash` = '$item_hash'");
			$this->content['page_feedback'] = "Item has been removed from your work order.";
			$this->content['action'] = 'continue';
			$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','resource_input','action=list','order_hash=$order_hash','popup_id={$this->popup_id}')";
			return;
		}
		if ($_POST['item_resource'] && ($_POST['resource_unit'] == 'F' || ($_POST['resource_unit'] != 'F' && $_POST['time'] > 0))) {
			if (!$_POST['item_resource_hash']) {
				$this->content['error'] = 1;
				$this->content['form_return']['err']['err1'.$this->popup_id] = 1;
				$this->content['form_return']['feedback'] = "We can't seem to find the resource you selected. Please make sure you are selecting a valid resource.";
				return;
			}

			$resource['resource_hash'] = $_POST['item_resource_hash'];
			$resource['units'] = $_POST['resource_unit'];
			$resource['time'] = $resource_time = ($resource['units'] == 'F' ? 1 : $_POST['time']*1);
			$resource['item_descr'] = $_POST['item_descr'];
			$resource['true_cost'] = preg_replace('/[^.0-9]/', "",$_POST['item_true_cost']);
			$resource['internal_cost'] = $resource_internal_cost = preg_replace('/[^.0-9]/', "",$_POST['item_internal_cost']);
			$resource['item_sell'] = preg_replace('/[^.0-9]/', "",$_POST['item_sell']);

			if ($item_hash) {
				$this->fetch_work_order($order_hash);
				$this->fetch_work_order_item($item_hash);

				$resource = $this->db->prepare_query($resource,"work_order_items",'UPDATE');
				$this->db->query("UPDATE `work_order_items`
								  SET ".@implode(" , ",$resource)."
								  WHERE `item_hash` = '$item_hash'");

                if ($this->current_item['po_hash'] && ($resource['time'] != $this->current_item['time'] || $resource['true_cost'] != $this->current_item['true_cost'])) {
                    $r = $this->db->query("SELECT SUM(line_items.qty * line_items.cost) AS ext_po_cost
                                           FROM line_items
                                           WHERE line_items.po_hash = '".$this->current_item['po_hash']."'");
                    $li_cost = $this->db->result($r,0,'ext_po_cost') * 1;
                    $this->db->free_result($r);

                    $r = $this->db->query("SELECT SUM(work_order_items.time * work_order_items.true_cost) AS ext_wo_cost
                                           FROM work_order_items
                                           WHERE work_order_items.po_hash = '".$this->current_item['po_hash']."'");
                    $wo_cost = $this->db->result($r,0,'ext_wo_cost') * 1;

                    $po_order_amount = $li_cost + $wo_cost;
                    $this->db->query("UPDATE `purchase_order`
                                      SET `order_amount` = '$po_order_amount'
                                      WHERE `po_hash` = '".$this->current_item['po_hash']."'");
                }
                $r = $this->db->query("SELECT `proposal_hash` , `punch`
                                       FROM line_items
                                       WHERE `item_hash` = '".$this->current_item['line_item_hash']."'");
                $proposal_hash = $this->db->result($r,0,'proposal_hash');
                $punch = $this->db->result($r,0,'punch');
                if ($proposal_hash && $this->current_item['line_item_hash'] && bccomp($resource['internal_cost'],$this->current_item['internal_cost'],2) != 0) {
                    $l = new line_items($proposal_hash,$punch);
                    if ($l->fetch_line_item_record($this->current_item['line_item_hash'])) {
                        $item_cost = $l->current_line['cost'] - $this->current_item['ext_internal_cost'];
                        $item_cost += ($resource_internal_cost * $resource_time);

                        $this->db->query("UPDATE line_items
                                          SET `cost` = '$item_cost'
                                          WHERE `item_hash` = '".$l->item_hash."'");
                    }
                }
				$this->content['page_feedback'] = "Item has been updated.";
				if ($_POST['new_win']) {
				    $this->content['action'] = 'close';
				    $this->content['jscript'][] = "agent.call('purchase_order','sf_loadcontent','show_popup_window','edit_po','proposal_hash=".$this->current_order['proposal_hash']."','po_hash=".$this->current_item['po_hash']."','popup_id=".$_POST['po_win']."');";
				    return;
				}
			} else {
				$item_hash = md5(global_classes::get_rand_id(32,"global_classes"));
				while (global_classes::key_exists('item_hash_index','item_hash',$item_hash))
					$item_hash = md5(global_classes::get_rand_id(32,"global_classes"));

				$this->db->query("INSERT INTO `item_hash_index`
								  (`item_hash`)
								  VALUES('$item_hash')");
				$resource = $this->db->prepare_query($resource,"work_order_items",'INSERT');
				$this->db->query("INSERT INTO `work_order_items`
								  (`item_hash` , `order_hash` ".(is_array($resource) ? ", ".implode(" , ",array_keys($resource)) : NULL).")
								  VALUES('$item_hash' , '$order_hash' ".(is_array($resource) ? ", ".implode(" , ",array_values($resource)) : NULL).")");
				$this->content['page_feedback'] = "Item has been added.";
			}

			$result = $this->db->query("SELECT SUM(work_order_items.true_cost * work_order_items.time) AS total_true_cost , SUM(work_order_items.internal_cost * work_order_items.time) AS total_internal_cost
										FROM `work_order_items`
										WHERE work_order_items.order_hash = '$order_hash'");
			$total_true_cost = $this->db->result($result,0,'total_true_cost');
			$total_internal_cost = $this->db->result($result,0,'total_internal_cost');

			$this->content['html']['wo_true_cost'.$this->popup_id] = "$".number_format($total_true_cost,2);
			$this->content['html']['wo_internal_cost'.$this->popup_id] = "$".number_format($total_internal_cost,2);

			$this->content['action'] = 'continue';
			$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','resource_input','action=list','order_hash=$order_hash','popup_id={$this->popup_id}')";
			return;
		} else {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "You left some required fields blank. Please completed the indicated fields.";
			if (!$_POST['item_resource']) $this->content['form_return']['err']['err1'.$this->popup_id] = 1;
			if (!$_POST['time']) $this->content['form_return']['err']['err3ab'.$this->popup_id] = 1;
			return;
		}
	}

	function resource_input($local=NULL) {
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
        $new_win = $this->ajax_vars['new_win'];
		$po_win = $this->ajax_vars['po_win'];

		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];
		elseif ($_POST['popup_id'])
			$this->popup_id = $_POST['popup_id'];

		$action = $this->ajax_vars['action'];
		if ($this->ajax_vars['order_hash'] && !$this->order_hash) {
			$valid = $this->fetch_work_order($this->ajax_vars['order_hash']);

			if (!$valid)
				unset($this->ajax_vars['order_hash']);
			elseif ($this->ajax_vars['item_hash']) {
				$action = 'add';
				if ($this->ajax_vars['item_hash'] && !$this->item_hash) {
					$valid2 = $this->fetch_work_order_item($this->ajax_vars['item_hash']);
					if (!$valid2)
						unset($this->ajax_vars['item_hash']);
				}
			}
		}

		if ($action == 'add') {
			$tbl = $this->form->hidden(array('item_hash' => $this->item_hash,
			                                 'new_win'  =>  $new_win,
			                                 'po_win'  =>  $po_win))."
			<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
				<tr>
					<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\" class=\"smallfont\">
						".($this->item_hash ?
						    "Edit A" : "New")." Work Order Resource
						<div style=\"padding-left:5px;font-weight:normal;\">".(!$new_win ? "
							<small><a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','resource_input','order_hash={$this->order_hash}','popup_id={$this->popup_id}','action=list')\" class=\"link_standard\"><-- back</a></small>" : NULL)."
						</div>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#d6d6d6;border-top:1px solid #AAC8C8;padding-left:20px;\">
						<table style=\"width:100%;font-size:9pt\" >
							<tr>
								<td style=\"vertical-align:top;font-weight:bold;\" class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" id=\"err1{$this->popup_id}\">Resource: </div>
									".$this->form->text_box("name=item_resource",
															"value=".$this->current_item['item_resource'],
															"autocomplete=off",
															"size=30",
															"TABINDEX=1",
			                                                ($new_win ? "readonly" : NULL),
															(!$new_win ? "onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('pm_module','item_resource','item_resource_hash',1);}" : NULL),
															(!$new_win ? "onKeyUp=if(ka==false && this.value){key_call('pm_module','item_resource','item_resource_hash',1);}" : NULL),
															(!$new_win ? "onBlur=key_clear();if(!\$F('item_resource_hash')){\$('item_resource').value='';submit_form(this.form,'pm_module','exec_post','refresh_form','action=fill_resource_data');}" : NULL),
															(!$new_win ? "onKeyDown=clear_values('item_resource_hash');" : NULL)).
									$this->form->hidden(array("item_resource_hash" => $this->current_item['resource_hash']))."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;font-weight:bold;\" rowspan=\"2\" class=\"smallfont\">
									<div style=\"padding-bottom:5px;\" id=\"err2{$this->popup_id}\">Description: </div>
									".$this->form->text_area("name=item_descr","value=".$this->current_item['item_descr'],"rows=4","cols=45")."
								</td>
							</tr>
							<tr>
								<td class=\"smallfont\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err3ab{$this->popup_id}\">Time Quoted: </div>
									<span id=\"quoted_time{$this->popup_id}\" style=\"visibility:".($this->current_item['units'] == 'F' ? "hidden" : "visible").";\">
										".$this->form->text_box("name=time",
																"value=".$this->current_item['time'],
																"size=6",
																"style=text-align:right;",
																"onKeyUp=if(this.value){toggle_submit('submit_btn',true);submit_form(this.form,'pm_module','exec_post','refresh_form','action=validate_resource_item')}")."
									</span>
									&nbsp;
									".$this->form->select("resource_unit",
														   array("Hour(s)","Day(s)","Half Day(s)","Fixed"),
														   $this->current_item['units'],
														   array("H","D","HD","F"),
														   "blank=1",
														   "onChange=if(this.options[this.selectedIndex].value=='F'){\$('quoted_time{$this->popup_id}').style.visibility='hidden';\$F('time').value='';}else{\$('quoted_time{$this->popup_id}').style.visibility='visible';}submit_form(this.form,'pm_module','exec_post','refresh_form','action=fill_resource_data')")."
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;padding-left:20px;vertical-align:top;\">
						<table style=\"width:100%;font-size:9pt\" cellpadding=\"3\" >
							<tr>
								<td style=\"vertical-align:top;width:100px;padding-top:10px;text-align:right;font-weight:bold;padding-bottom:5px\" class=\"smallfont\">True Cost: </td>
								<td style=\"vertical-align:top;padding-top:10px;\">
									".$this->form->text_box("name=item_true_cost",
															"value=".$this->current_item['true_cost'],
															"size=10",
															"onBlur=if(this.value){this.value=formatCurrency(this.value)}",
														    "onFocus=this.select();",
															"style=text-align:right;",
															(!$new_win ? "onKeyUp=if(this.value){toggle_submit('submit_btn',true);submit_form(this.form,'pm_module','exec_post','refresh_form','action=validate_resource_item','from_field=item_true_cost')}" : NULL))."
								</td>
								<td style=\"width:350px;vertical-align:top;background-color:#efefef;border:1px solid #9f9f9f;\" rowspan=\"2\">
									<table>
										<tr>
											<td style=\"text-align:right;font-weight:bold;padding-bottom:4px;\" class=\"smallfont\">True Cost Ext: </td>
											<td style=\"padding-left:5px;padding-bottom:4px;\" id=\"extended_true_cost{$this->popup_id}\" class=\"smallfont\">".($this->item_hash ?
                                                ($this->current_item['ext_true_cost'] < 0 ?
                                                    "($".number_format($this->current_item['ext_true_cost'] * -1,2).")" : "$".number_format($this->current_item['ext_true_cost'],2))	: NULL)."
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold;padding-bottom:4px;\" class=\"smallfont\">Internal Cost Ext: </td>
											<td style=\"padding-left:5px;padding-bottom:4px;\" id=\"extended_internal_cost{$this->popup_id}\" class=\"smallfont\">".($this->item_hash ?
												($this->current_item['ext_internal_cost'] < 0 ?
												    "($".number_format($this->current_item['ext_internal_cost'] * -1,2).")" : "$".number_format($this->current_item['ext_internal_cost'],2)) : NULL)."
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold;\" class=\"smallfont\">Internal Profit Dollars: </td>
											<td style=\"font-weight:bold;padding-left:5px;\" id=\"profit_dollars_internal{$this->popup_id}\" class=\"smallfont\">".($this->item_hash ?
												($this->current_item['ext_internal_cost'] - $this->current_item['ext_true_cost'] < 0 ?
												    "($".number_format(($this->current_item['ext_internal_cost'] - $this->current_item['ext_true_cost']) * -1,2).")" : "$".number_format($this->current_item['ext_internal_cost'] - $this->current_item['ext_true_cost'],2)) : NULL)."
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;width:100px;text-align:right;font-weight:bold;padding-bottom:5px\" class=\"smallfont\">Internal Cost: </td>
								<td style=\"vertical-align:top;\" class=\"smallfont\">
									".$this->form->text_box("name=item_internal_cost",
															"value=".$this->current_item['internal_cost'],
															"size=10",
															"onBlur=if(this.value){this.value=formatCurrency(this.value)}",
															"style=text-align:right;",
            												"onFocus=this.select();",
															"onKeyUp=if(this.value){toggle_submit('submit_btn',true);submit_form(this.form,'pm_module','exec_post','refresh_form','action=validate_resource_item','from_field=item_internal_cost')}")."
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
            <div style=\"margin:15px;\">".($this->current_order['line_item_hash'] && !$new_win ?
                "<i>This resource has been imported into a line item. Changes can be made from the line item.</i>" :
                    $this->form->button("value=Save Resource",
                                          "name=submit_btn",
                                          ($this->current_order['line_item_hash'] && !$new_win ?
                                              "disabled" : ($this->current_item['line_item_hash'] ?
                                                  "onClick=if(confirm('This work order has been imported into your line items. Updating the resource internal cost will update the cost of its line item. Do you want to proceed?')){submit_form(this.form,'pm_module','exec_post','refresh_form','action=doit_save_item');}" : "onClick=submit_form(this.form,'pm_module','exec_post','refresh_form','action=doit_save_item');")),
                                          ($this->current_order['line_item_hash'] && !$new_win ?
                                              "title=\"This work order has already been imported into a proposal and cannot be changed.\"" : NULL))."
                    &nbsp;&nbsp;".($this->item_hash ? "
                    ".$this->form->button("value=Delete Resource",
                                          ($this->current_order['line_item_hash'] ?
                                              "disabled" : "onClick=if(confirm('Are you sure you want to remove this item from your work order?')){submit_form(this.form,'pm_module','exec_post','refresh_form','action=doit_save_item','delete=1');}"),($this->current_order['line_item_hash'] ? "title=\"This work order has already been imported into a proposal and cannot be changed.\"" : NULL)) : NULL))."
            </div>";
            if (!$this->item_hash)
                $this->content['focus'] = "item_resource";
		} else {
			$this->fetch_resources_used();

			$tbl .= "
			<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
				<tr>
					<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"".($this->p->ck(get_class($this),'VT','work_orders') ? "4" : "3")."\" class=\"smallfont\">
						<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->order_hash && $this->p->ck(get_class($this),'AR','work_orders') ?
							(!$this->current_order['line_item_hash'] ? "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','resource_input','action=add','order_hash={$this->order_hash}','popup_id=".($this->popup_id ? $this->popup_id : "work_order_detail")."');\"><img src=\"images/plus.gif\" title=\"Add a new resource to this work order.\" border=\"0\" /></a>" : "<img src=\"images/plus_disabled.gif\" title=\"This work order has already been imported into a proposal and cannot be changed.\" />") : "&nbsp;")."
						</div>
					</td>
				</tr>
				<tr class=\"thead\" style=\"font-weight:bold;\">
					<td style=\"vertical-align:bottom;\" nowrap>Resource</td>
					<td style=\"vertical-align:bottom;\">Time</td>".($this->p->ck(get_class($this),'VT','work_orders') ? "
					<td style=\"width:100px;vertical-align:bottom;text-align:right;\">True Cost</td>" : NULL)."
					<td style=\"width:100px;vertical-align:bottom;text-align:right;\">Internal Cost</td>
				</tr>";
				for ($i = 0; $i < $this->total_resources_used; $i++) {
					$tbl .= "
					<tr onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','resource_input','item_hash=".$this->resources_used[$i]['item_hash']."','order_hash={$this->order_hash}','action=add','popup_id={$this->popup_id}')\">
						<td class=\"smallfont\">".$this->resources_used[$i]['resource_name']."</td>
						<td class=\"smallfont\">".($this->resources_used[$i]['units'] == 'F' ?
							"Fixed" :
							strrev(substr(strstr(strrev($this->resources_used[$i]['time']),'.'),1)).(str_replace('0','',substr(strrchr($this->resources_used[$i]['time'],'.'),1)) ?
								str_replace('0','',strrchr($this->resources_used[$i]['time'],'.')) : NULL)."&nbsp;
							".$this->units[$this->resources_used[$i]['units']].($this->resources_used[$i]['time'] > 1 ?
								"s" : NULL))."
						</td>".($this->p->ck(get_class($this),'VT','work_orders') ? "
						<td style=\"text-align:right;\" class=\"smallfont\">$".number_format($this->resources_used[$i]['ext_true_cost'],2)."</td>" : NULL)."
						<td style=\"text-align:right;\" class=\"smallfont\">$".number_format($this->resources_used[$i]['ext_internal_cost'],2)."</td>
					</tr>
					<tr>
						<td style=\"background-color:#cccccc;\" colspan=\"4\"></td>
					</tr>";

					if ($this->p->ck(get_class($this),'VT','work_orders'))
						$total_true_cost += $this->resources_used[$i]['ext_true_cost'];

					$total_internal_cost += $this->resources_used[$i]['ext_internal_cost'];
					$total_sell += $this->resources_used[$i]['ext_sell'];
				}

				if (!$this->total_resources_used)
					$tbl .= "
					<tr >
						<td colspan=\"".($this->p->ck(get_class($this),'VT','work_orders') ? "4" : "3")."\">
						".(!$this->order_hash ?
							"Before creating resources you must save the new work order." : "There are no resources used in this work order.")."
						</td>
					</tr>";
				else
					$tbl .= "
					<tr>
						<td style=\"background-color:#9f9f9f;height:2px;\" colspan=\"".($this->p->ck(get_class($this),'VT','work_orders') ? "4" : "3")."\"></td>
					</tr>
					<tr style=\"background-color:#cccccc;\">
						<td colspan=\"2\">".($this->p->ck(get_class($this),'VT','work_orders') ? "
						<td style=\"vertical-align:bottom;font-weight:bold;padding-top:20px;\" class=\"num_field\">$".number_format($total_true_cost,2)."</td>" : NULL)."
						<td style=\"vertical-align:bottom;font-weight:bold;padding-top:20px;\" class=\"num_field\">$".number_format($total_internal_cost,2)."</td>
					</tr>";

				$tbl .= "
			</table>";
		}
		if ($local)
			return $tbl;
		elseif ($new_win) {
	        $this->content['popup_controls']['popup_id'] = $this->popup_id;
	        $this->content['popup_controls']['popup_title'] = "Edit a Booked Work Order Resource";

            $tbl = $this->form->form_tag().$this->form->hidden(array('popup_id' =>  $this->popup_id,'order_hash'    =>  $this->current_item['order_hash']))."
	        <div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;height:100%;\">
	            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
	                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
	                    <p id=\"feedback_message{$this->popup_id}\"></p>
	            </div>
	            <table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
	                <tr>
	                    <td style=\"background-color:#ffffff;padding:0;\">".$tbl."</td>
                    </tr>
                </table>
            </div>".
            $this->form->close_form();

            $this->content['popup_controls']["cmdTable"] = $tbl;
		} else
			$this->content['html']["work_order_tcontent2".$this->popup_id] = $tbl;

	}


	function view_job() {
		$proposals = new proposals($this->current_hash);
		$start_date = $this->ajax_vars['start_date'];
		if ($proposal_hash = $this->ajax_vars['proposal_hash']) {
			$valid = $proposals->fetch_master_record($proposal_hash);

			if (!$valid)
				unset($this->ajax_vars['proposal_hash']);

			if ($proposals->current_proposal['customer_contact']) {
				$customers = new customers($this->current_hash);
				$customers->fetch_contact_record($proposals->current_proposal['customer_contact']);
			}
			if ($proposals->current_proposal['install_addr_hash']) {
				list($class,$id,$hash) = explode("|",$proposals->current_proposal['install_addr_hash']);
				$obj = new $class($this->current_hash);
				if ($hash) {
					$obj->fetch_location_record($hash);
					$proposals->current_proposal[strrev(substr(strrev($key),5))] = $obj->current_location['location_name'];
					$install_street = $obj->current_location['location_street'];
					$install_city = $obj->current_location['location_city'];
					$install_state = $obj->current_location['location_state'];
					$install_zip = $obj->current_location['location_zip'];
				} else {
					$obj->fetch_master_record($id);
					$proposals->current_proposal[strrev(substr(strrev($key),5))] = $obj->{"current_".strrev(substr(strrev($class),1))}[strrev(substr(strrev($class),1))."_name"];;
					$install_street = $obj->{"current_".strrev(substr(strrev($class),1))}['street'];
					$install_city = $obj->{"current_".strrev(substr(strrev($class),1))}['city'];
					$install_state = $obj->{"current_".strrev(substr(strrev($class),1))}['state'];
					$install_zip = $obj->{"current_".strrev(substr(strrev($class),1))}['zip'];
				}
				unset($obj);
				if ($install_street && $install_city && $install_state && $install_zip)
					$install_str = $install_street."+".$install_city.",+".$install_state."+".$install_zip;
			}
		}

		if ($proposal_hash)
			$this->proposal_hash = $proposal_hash;

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Proposal Installation Details";

		$hour = array(12,1,2,3,4,5,6,7,8,9,10,11);
		for ($i = 0; $i < 60; $i++) {
			$min_in[] = $i;
			$min_out[] = ($i < 10 ? '0' : NULL).$i;
		}
		$this->work_order_filter = 1;
		$this->fetch_work_orders();

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("proposal_hash" => $proposal_hash,
								  "popup_id" => $this->popup_id,
								  "start_date" => $start_date))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:15px 20px;\">
						<h3 style=\"margin-bottom:5px;color:#00477f;\" >
							Proposal ".$proposals->current_proposal['proposal_no']." : <span id=\"proposal_descr_holder\" >".(strlen($proposals->current_proposal['proposal_descr']) > 50 ?
								substr($proposals->current_proposal['proposal_descr'],0,45)."..." : $proposals->current_proposal['proposal_descr'])."</span>
						</h3>
						<div style=\"margin-left:15px;margin-bottom:10px;\">
							<div style=\"float:right;margin-right:75px;\">
								<span id=\"err1{$this->popup_id}\">Project Mngr:</span>
								&nbsp;
								".$this->form->text_box("name=proj_mngr",
														"autocomplete=off",
														"value=".$proposals->current_proposal['proj_mngr'],
														"size=20",
														"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('proposals','proj_mngr','proj_mngr_hash',1);}",
								                        "onKeyUp=if(ka==false && this.value){key_call('proposals','proj_mngr','proj_mngr_hash',1);}",
														"onBlur=setTimeout('if(\$F(\'proj_mngr_hash\') && $(\'pm_request\')){\$(\'pm_request\').checked=0};',500)",
														"onKeyDown=clear_values('proj_mngr_hash');").
								$this->form->hidden(array("proj_mngr_hash" => ($proposals->current_proposal['proj_mngr_hash'] ? $proposals->current_proposal['proj_mngr_hash'] : '')))."
								&nbsp;
								[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"selectItem('proj_mngr=".$_SESSION['my_name']."','proj_mngr_hash=".$_SESSION['id_hash']."');\">assign me</a></small>]
							</div>
							[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals','sf_loadcontent','cf_loadcontent','edit_proposal','proposal_hash=".$proposals->proposal_hash."','back=agent.call(\'pm_module\',\'sf_loadcontent\',\'cf_loadcontent\',\'calendar\')');\">open this proposal</a></small>]
						</div>
						<ul id=\"pmcal_maintab{$this->popup_id}\" class=\"shadetabs\" style=\"margin-top:15px;\">
							<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"pmcal_tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\" >Project Info</a></li>
							<li ><a href=\"javascript:void(0);\" rel=\"pmcal_tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\" >Install Info</a></li>
							<li ><a href=\"javascript:void(0);\" rel=\"pmcal_tcontent3{$this->popup_id}\" onClick=\"expandcontent(this);\" >File Vault</a></li>
							<li ><a href=\"javascript:void(0);\" rel=\"pmcal_tcontent4{$this->popup_id}\" onClick=\"expandcontent(this);\" >Work Orders</a></li>
						</ul>
						<div id=\"pmcal_tcontent1{$this->popup_id}\" class=\"tabcontent\">
							<table cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#efefef;border:1px solid #8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">
								<tr>
									<td style=\"text-align:right;width:25%;padding-top:10px;vertical-align:top;font-weight:bold;\">Customer: </td>
									<td style=\"text-align:left;width:25%;padding-top:10px;\">
										".$proposals->current_proposal['customer']."
										<div id=\"customer_details_holder\">".$proposals->innerHTML_customer($proposals->current_proposal['customer_hash'],'customer','customer_details_holder')."</div>
									</td>
									<td style=\"text-align:right;width:25%;padding-top:15px;vertical-align:top;font-weight:bold;\">
										<span id=\"err2{$this->popup_id}\">Install Date:</span> ".($proposals->current_proposal['install_days'] ? "
										<div style=\"padding-top:20px;\">Available Days:</div>" : NULL)."
										<div style=\"padding-top:15px;\" id=\"err3{$this->popup_id}\">Start Time:</div>
									</td>
									<td style=\"text-align:left;width:25%;padding-top:10px;padding-right:20px;vertical-align:top;\">
										<div id=\"install_date{$this->popup_id}\">";
										$this->content["jscript"][] .= "setTimeout('DateInput(\'actual_install_date\', false, \'YYYY-MM-DD\',\'".($proposals->current_proposal['actual_install_date'] && date("Y",strtotime($proposals->current_proposal['actual_install_date'])) > 2000 ? $proposals->current_proposal['actual_install_date'] : NULL)."\',1,\'install_date{$this->popup_id}\')',24);";
									$tbl .= "
										</div>".($proposals->current_proposal['install_days'] ? "
										<div style=\"padding-top:14px;\">".$proposals->current_proposal['install_days']."</div>" : NULL)."
										<div style=\"padding-top:10px;\">
											".$this->form->select("start_hour",$hour,$proposals->current_proposal['start_time'],$hour)."&nbsp;
											".$this->form->select("start_min",$min_out,$proposals->current_proposal['start_time'],$min_in)."&nbsp;
											".$this->form->select("start_am_pm",array("AM","PM"),$proposals->current_proposal['start_time'],array('AM','PM'))."&nbsp;
										</div>
									</td>
								</tr>
								<tr>
									<td colspan=\"4\" style=\"text-align:center;\"><hr style=\"width:90%;\" /></td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:25%;vertical-align:top;font-weight:bold;\">Contact: </td>
									<td style=\"text-align:left;width:25%;vertical-align:top;\">".($customers->current_contact ?
										$customers->current_contact['contact_name'] : NULL)."
										<div id=\"customer_contact_details_holder\">".($proposals->current_proposal['customer_contact'] ?
											$proposals->innerHTML_customer_contact($proposals->current_proposal['customer_contact'],'customer','customer_contact_details_holder') : NULL)."
										</div>
									</td>
									<td style=\"text-align:right;width:25%;vertical-align:top;font-weight:bold;\">Bldg Mngmt POC: </td>
									<td style=\"text-align:left;width:25%;vertical-align:top;\">
										".$proposals->current_proposal['bldg_poc']."<br />".($proposals->current_proposal['bldg_phone'] ?
										$proposals->current_proposal['bldg_phone']." (phone)<br />" : NULL).($proposals->current_proposal['bldg_fax'] ?
											$proposals->current_proposal['bldg_fax']." (fax)" : NULL)."
									</td>
								</tr>
								<tr>
									<td colspan=\"4\" style=\"text-align:center;\"><hr style=\"width:90%;\" /></td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:25%;vertical-align:top;font-weight:bold;\">
										Install Location: ".($install_str ?
											"<div style=\"padding-top:5px;margin-right:10px;font-weight:normal;\">
												<a class=\"link_standard\" href=\"http://maps.google.com/maps?f=q&hl=en&geocode=&q=".$install_str."&ie=UTF8&z=16&iwloc=addr&om=1\" target=\"_blank\" title=\"Google Maps\">Map It</a>
											</div>" : NULL)."
									</td>
									<td style=\"text-align:left;width:25%;vertical-align:top;\">
										".$proposals->current_proposal['install_addr']."
										<div id=\"install_addr_holder\" >".($proposals->current_proposal['install_addr_hash'] ?
											$proposals->innerHTML_customer($proposals->current_proposal['install_addr_hash'],'install_addr','install_addr_holder',$proposals->current_proposal['install_addr_hash']) : NULL)."
										</div>
									</td>
									<td style=\"text-align:right;width:25%;vertical-align:top;font-weight:bold;\">Shipping Location: </td>
									<td style=\"text-align:left;width:25%;vertical-align:top;\">
										".$proposals->current_proposal['ship_to']."
										<div id=\"ship_to_holder\" >".($proposals->current_proposal['ship_to_hash'] ?
											$proposals->innerHTML_customer($proposals->current_proposal['ship_to_hash'],'ship_to','ship_to_holder',$proposals->current_proposal['ship_to_hash']) : NULL)."
										</div>
									</td>
								</tr>
							</table>
						</div>
						<div id=\"pmcal_tcontent2{$this->popup_id}\" class=\"tabcontent\">
							<table cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#efefef;border:1px solid #8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">
								<tr>
									<td style=\"text-align:left;background-color:#ffffff;border-bottom:1px solid #cccccc;padding:10px;vertical-align:top;font-weight:bold;\">Site Information:</td>
								</tr>
								<tr>
									<td style=\"padding-top:10px;vertical-align:top;\">
										<table style=\"width:100%;margin-top:0;\">
											<tr>
												<td style=\"width:33%;padding-left:25px;\">No. Floors: ".$proposals->current_proposal['no_floors']."</td>
												<td style=\"width:33%;\">Dlvr Nrml Hours: ".($proposals->current_proposal['option_dlv_normal_hours'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Install Nrml Hours: ".($proposals->current_proposal['option_inst_normal_hours'] ? 'Y' : NULL)."</td>
											</tr>
											<tr>
												<td style=\"width:33%;padding-left:25px;\">Building Restrictions: ".($proposals->current_proposal['option_bldg_rstr'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Loading Dock: ".($proposals->current_proposal['option_loading_dock'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Freight Elevator: ".($proposals->current_proposal['option_freight_elev'] ? 'Y' : NULL)."</td>
											</tr>
											<tr>
												<td style=\"width:33%;padding-left:25px;\">Stair Carry: ".($proposals->current_proposal['option_stair_carry'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Move Product Prior: ".($proposals->current_proposal['option_mv_prod_prior'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Occupied Space: ".($proposals->current_proposal['option_occupied'] ? 'Y' : NULL)."</td>
											</tr>
											<tr>
												<td style=\"width:33%;padding-left:25px;\">Permits: ".($proposals->current_proposal['option_permits'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Certificate of Insurance: ".($proposals->current_proposal['option_insurance'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\"></td>
											</tr>
										</table>
									</td>
								</tr>
								<tr>
									<td style=\"text-align:left;background-color:#ffffff;border-bottom:1px solid #cccccc;border-top:1px solid #cccccc;padding:10px;vertical-align:top;font-weight:bold;\">Product Information:</td>
								</tr>
								<tr>
									<td >
										<table cellspacing=\"1\" cellpadding=\"5\" style=\"width:100%;margin-top:0;\" >
											<tr>
												<td style=\"padding-left:31px;\">Task Seating: ".$this->form->text_box("name=","size=50","maxlength=128","value=".$proposals->current_proposal['task_seating_prod'],"readonly")."&nbsp;</td>
												<td style=\"padding-right:25px;\">QTY: ".($proposals->current_proposal['task_seating_qty'] > 0 ? $proposals->current_proposal['task_seating_qty'] : NULL)."&nbsp;</td>
											</tr>
											<tr>
												<td style=\"padding-left:25px;\">Guest Seating: ".$this->form->text_box("name=","size=50","maxlength=128","value=".$proposals->current_proposal['guest_seating_prod'],"readonly")."&nbsp;</td>
												<td style=\"padding-right:25px;\">QTY: ".($proposals->current_proposal['guest_seating_qty'] > 0 ? $proposals->current_proposal['guest_seating_qty'] : NULL)."&nbsp;</td>
											</tr>
										</table>
									</td>
								</tr>
								<tr>
									<td>
										<table cellspacing=\"1\" cellpadding=\"5\" style=\"width:100%;margin-top:0;\" >
											<tr>
												<td style=\"width:33%;padding-left:25px;\">Drawings Provided: ".($proposals->current_proposal['option_dwgs_pvd'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Wall Mntd Product: ".($proposals->current_proposal['option_wall_mntd'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Power Poles: ".($proposals->current_proposal['option_power_poles'] ? 'Y' : NULL)."</td>
											</tr>
											<tr>
												<td style=\"width:33%;padding-left:25px;\">Wood Trim/Elements: ".($proposals->current_proposal['option_wood_trim'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\">Multiple Trips: ".($proposals->current_proposal['option_multi_trip'] ? 'Y' : NULL)."</td>
												<td style=\"width:33%;\"></td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</div>
						<div id=\"pmcal_tcontent3{$this->popup_id}\" class=\"tabcontent\">".$this->doc_vault($proposals->proposal_hash)."</div>
						<div id=\"pmcal_tcontent4{$this->popup_id}\" class=\"tabcontent\">
							<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
								<tr>
									<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"3\">".($this->total_work_orders ? "
										Showing 1 - ".$this->total_work_orders." of ".$this->total_work_orders." Work Orders." : "&nbsp;")."
									</td>
								</tr>
								<tr class=\"thead\" style=\"font-weight:bold;\">
									<td style=\"vertical-align:bottom;padding-left:15px;font-size:9pt;\" nowrap>
										<a href=\"javascript:void(0);\" style=\"font-size:9pt;\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_orders','otf=1','proposal_hash=".$this->proposal_hash."','order=order_no','order_dir=".($order == "order_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
											Order No.</a>
										".($order == 'order_no' ?
											"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
									</td>
									<td style=\"vertical-align:bottom;font-size:9pt;\">Order Date</td>
									<td style=\"vertical-align:bottom;font-size:9pt;\">Description</td>
								</tr>";

								for ($i = 0; $i < $this->total_work_orders; $i++) {
									$onClick = "onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','order_hash=".$this->work_orders[$i]['order_hash']."','proposal_hash=".$this->proposal_hash."','popup_id=work_order_detail')\"";
									$tbl .= "
									<tr onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" >
										<td style=\"vertical-align:bottom;padding-left:15px;font-size:9pt;\" style=\"text-align:left;\" $onClick>".$this->work_orders[$i]['order_no']."</td>
										<td style=\"vertical-align:bottom;font-size:9pt;\" $onClick>".date(DATE_FORMAT,strtotime($this->work_orders[$i]['order_date']))."</td>
										<td style=\"vertical-align:bottom;font-size:9pt;\" ".(strlen($this->work_orders[$i]['order_descr']) > 60 ? "title=\"".$this->work_orders[$i]['order_descr']."\"" : NULL)." $onClick>".(strlen($this->work_orders[$i]['order_descr']) > 70 ?
											wordwrap(substr($this->work_orders[$i]['order_descr'],0,65),35,"<br />",true)."..." : (strlen($this->work_orders[$i]['order_descr']) > 30 ?
												wordwrap($this->work_orders[$i]['order_descr'],30,"<br />",true) : nl2br($this->work_orders[$i]['order_descr'])))."
										</td>
									</tr>
									<tr>
										<td style=\"background-color:#cccccc;\" colspan=\"3\"></td>
									</tr>";
								}

								if (!$this->total_work_orders)
									$tbl .= "
									<tr >
										<td colspan=\"3\">There are no work orders against this proposal.</td>
									</tr>";

					$tbl .= "
							</table>
						</div>
					</td>
				</tr>
			</table>
			<div style=\"text-align:left;padding:15px;\">
				".$this->form->button("value=Save Job","onClick=submit_form(this.form,'pm_module','exec_post','refresh_form','btn=save');")."
			</div>
		</div>".
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'pmcal_maintab{$this->popup_id}\')',10);";
		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function doc_vault($local=NULL) {
		if (!$this->popup_id)
			$this->popup_id = $this->ajax_vars['popup_id'];

		if ($local)
			$proposal_hash = $local;
		else
			$proposal_hash = $this->ajax_vars['proposal_hash'];

		$doc_vault = new doc_vault();
		$doc_vault->fetch_files($proposal_hash);
		$tbl = "
		<table cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#efefef;border:1px solid #8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">
			<tr>
				<td colspan=\"3\" style=\"text-align:left;background-color:#ffffff;border-bottom:1px solid #8f8f8f;padding:10px;vertical-align:bottom;\">
					<div id=\"iframe\" style=\"float:right;margin-bottom:0\"><iframe src=\"upload.php?proposal_hash=".$proposal_hash."&class=doc_vault&action=doit_doc_vault&from=pm_module&current_hash=".$current_hash."&popup_id={$this->popup_id}\" frameborder=\"0\" style=\"height:50px;\"></iframe></div>
					<div id=\"progress_holder\"></div>".
						$this->form->hidden(array("file_name" 		=>		'',
												  "file_type" 		=>		'',
												  "file_size" 		=>		'',
												  "file_error" 		=>		''
												  )
											).($doc_vault->total > 0 ? "
					Showing ".$doc_vault->total." file".($doc_vault->total > 1 ? "s" : NULL)."." : NULL)."
				</td>
			</tr>
			<tr>
				<td style=\"font-weight:bold;\">File Name</td>
				<td style=\"font-weight:bold;\">File Type</td>
				<td style=\"font-weight:bold;\">File Size</td>
			</tr>";
		for ($i = 0; $i < $doc_vault->total; $i++) {
			$tbl .= "
			<tr>
				<td style=\"border-top:1px solid #cccccc;background-color:#ffffff\">".($this->p->ck('proposals','D','doc_vault') && !$doc_vault->proposal_docs[$i]['checkout'] ? "
					<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this file? This action CANNOT be undone!')){submit_form($('proposal_hash').form,'doc_vault','exec_post','refresh_form','action=doit_exec_file','from=pm_module','popup_id={$this->popup_id}','proposal_hash=".$proposal_hash."','doc_hash=".$doc_vault->proposal_docs[$i]['doc_hash']."','doc_action=rm');}\"><img src=\"images/rm_doc.gif\" title=\"Delete this file\" border=\"0\" /></a>" : NULL)."
					<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('doc_vault','sf_loadcontent','show_popup_window','view_file','doc_hash=".$doc_vault->proposal_docs[$i]['doc_hash']."','proposal_hash=".$proposal_hash."','popup_id={$this->popup_id}');\">
						".$doc_vault->proposal_docs[$i]['file_name']."</a>
				</td>
				<td style=\"border-top:1px solid #cccccc;background-color:#ffffff\">".$doc_vault->proposal_docs[$i]['file_type']."</td>
				<td style=\"border-top:1px solid #cccccc;background-color:#ffffff\">".fsize_unit_convert($doc_vault->proposal_docs[$i]['filesize'])."</td>
			</tr>";
		}
		$tbl .= "
		</table>";


		if ($local)
			return $tbl;
		else
			$this->content['html']["pmcal_tcontent3".$this->popup_id] = $tbl;
	}

	function calendar() {
		$start_date = $this->ajax_vars['start_date'];
		$view = $this->ajax_vars['view'];
		if (!$start_date)
			$start_date = date("Y-m-d");
		else
			$start_date = date("Y-m-d",$start_date);

		$view = 3;
		if ($view == 3)
			$start_date = date("Y-m-01",strtotime($start_date));

		$DefaultStart = $start_date;

		if (!$view || $view == 1 || $view == 3) {
			$loop = 7;

			switch ($view == 3 ? date("w",strtotime($start_date)) : date("w")) {
				case 0:
					$DaysToAd = array("","+1 days","+2 days","+3 days","+4 days","+5 days","+6 days");
					break;
				case 1:
					$DaysToAd = array("-1 days","","+1 days","+2 days","+3 days","+4 days","+5 days");
					break;
				case 2:
					$DaysToAd = array("-2 days","-1 days","","+1 days","+2 days","+3 days","+4 days");
					break;
				case 3:
					$DaysToAd = array("-3 days","-2 days","-1 days","","+1 days","+2 days","+3 days");
					break;
				case 4:
					$DaysToAd = array("-4 days","-3 days","-2 days","-1 days","","+1 days","+2 days");
					break;
				case 5:
					$DaysToAd = array("-5 days","-4 days","-3 days","-2 days","-1 days","","+1 days");
					break;
				case 6:
					$DaysToAd = array("","+2 days","+3 days","+4 days","+5 days","+6 days","+7 days");
					break;
			}
		} elseif ($view == 2) {
			$loop = 14;

			switch (date("w")) {
				case 0:
					$DaysToAd = array("","+1 days","+2 days","+3 days","+4 days","+5 days","+6 days","+7 days","+8 days","+9 days","+10 days","+11 days","+12 days","+13 days");
					break;
				case 1:
					$DaysToAd = array("-1 days","","+1 days","+2 days","+3 days","+4 days","+5 days","+6 days","+7 days","+8 days","+9 days","+10 days","+11 days","+12 days");
					break;
				case 2:
					$DaysToAd = array("-2 days","-1 days","","+1 days","+2 days","+3 days","+4 days","+5 days","+6 days","+7 days","+8 days","+9 days","+10 days","+11 days");
					break;
				case 3:
					$DaysToAd = array("-3 days","-2 days","-1 days","","+1 days","+2 days","+3 days","+4 days","+5 days","+6 days","+7 days","+8 days","+9 days","+10 days");
					break;
				case 4:
					$DaysToAd = array("-4 days","-3 days","-2 days","-1 days","","+1 days","+2 days","+3 days","+4 days","+5 days","+6 days","+7 days","+8 days","+9 days");
					break;
				case 5:
					$DaysToAd = array("-5 days","-4 days","-3 days","-2 days","-1 days","","+1 days","+2 days","+3 days","+4 days","+5 days","+6 days","+7 days","+8 days");
					break;
				case 6:
					$DaysToAd = array("","+2 days","+3 days","+4 days","+5 days","+6 days","+7 days","+8 days","+9 days","+10 days","+11 days","+12 days","+13 days","+14 days");
					break;
			}
		}
		// Limit user list if permission checked for Trac #1221
		if ($this->p->ck('proposals','S'))
			$sql = " AND proposals.sales_hash = '".$_SESSION['id_hash']."'";
		$result = $this->db->query("SELECT proposals.proposal_hash , proposals.proposal_no , customers.customer_name
									FROM `proposals`
									LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
									WHERE proposals.proj_mngr_hash = '' AND proposals.pm_request > 0 AND proposals.closed = 0 AND proposals.archive = 0".($sql ? $sql : NULL));
		while ($row = $this->db->fetch_assoc($result))
			$pending_jobs[] = $row;

		$result = $this->db->query("SELECT proposals.proposal_hash , proposals.proposal_no , customers.customer_name
									FROM `proposals`
									LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
									WHERE proposals.proj_mngr_hash = '".$_SESSION['id_hash']."' AND proposals.closed = 0 AND proposals.archive = 0".($sql ? $sql : NULL));
		while ($row = $this->db->fetch_assoc($result))
			$my_jobs[] = $row;

		$tbl .= "
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"vertical-align:top;\">
					<div style=\"margin-bottom:5px;\">
						 <table class=\"tborder\" cellspacing=\"1\" cellpadding=\"6\" style=\"width:90%;background-color:#cccccc\">
							<tr>";
							if (count($pending_jobs)) {
								$tbl .= "
								<td style=\"font-weight:bold;vertical-align:top;background-color:#ffffff;width:50%\">
									Pending Jobs (".count($pending_jobs).")
									<div style=\"height:100px;overflow:auto;font-weight:normal;\">";
									for ($i = 0; $i < count($pending_jobs); $i++) {
										$tbl .=
										"<div style=\"padding:5px;\">".($this->p->ck(get_class($this),"E","schedule") ? "
											<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','view_job','proposal_hash=".$pending_jobs[$i]['proposal_hash']."','popup_id=work_order_detail')\" title=\"View &amp; edit this job\">
												".$pending_jobs[$i]['customer_name']." : ".$pending_jobs[$i]['proposal_no']."</a>" : $pending_jobs[$i]['customer_name']." : ".$pending_jobs[$i]['proposal_no'])."
										</div>";
									}
									$tbl .= "
									</div>
								</td>";
							}
							if (count($my_jobs)) {
								$tbl .= "
								<td style=\"width:50%;font-weight:bold;vertical-align:top;background-color:#ffffff;\">
									My Jobs (".count($my_jobs)."):
									<div style=\"height:100px;overflow:auto;font-weight:normal;\">";
									for ($i = 0; $i < count($my_jobs); $i++) {
										$tbl .=
										"<div style=\"padding:5px;\">".($this->p->ck(get_class($this),"E","schedule") ? "
											<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','view_job','proposal_hash=".$my_jobs[$i]['proposal_hash']."','popup_id=work_order_detail')\" title=\"View &amp; edit this job\">
												".$my_jobs[$i]['customer_name']." : ".$my_jobs[$i]['proposal_no']."</a>" : $my_jobs[$i]['customer_name']." : ".$my_jobs[$i]['proposal_no'])."
										</div>";
									}
									$tbl .= "
									</div>
								</td>";
							}
							$tbl .= "
							</tr>
						</table>
					</div>
				</td>
			</tr>
			<tr>
				<td >
					 <table class=\"tborder\" cellspacing=\"1\" cellpadding=\"6\" style=\"width:90%;background-color:#cccccc\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;background-color:#ffffff;\" colspan=\"7\">
								<div style=\"float:right;font-weight:normal;padding-right:10px;\">
									<div style=\"padding-top:5px;display:none;\">
										<a href=\"javascript:void(0);\" onMouseOver=\"position_element($('sort_menu'),findPos(this,'top')+15,findPos(this,'left')-100);\" onClick=\"toggle_display('sort_menu','block');\"><img src=\"images/arrow_down2.gif\" id=\"sort_arrow_down\" border=\"0\" title=\"Change sorting options\" /></a>
										&nbsp;
										<span style=\"cursor:hand;\" onMouseOver=\"position_element($('sort_menu'),findPos($('sort_arrow_down'),'top')+15,findPos($('sort_arrow_down'),'left')-100);\" onClick=\"toggle_display('sort_menu','block');\" title=\"Change sorting options\"><small>Sort Options</small></span>
										".$sort_menu."
									</div>
								</div>
								Installation &amp; Delivery Schedule
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td colspan=\"7\" style=\"text-align:center;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','calendar','start_date=".strtotime($start_date." -1 month")."');\" style=\"color:#ffffff\"><<</a>&nbsp;&nbsp;&nbsp;".date("F Y",strtotime($start_date))."&nbsp;&nbsp;&nbsp;<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','calendar','start_date=".strtotime($start_date." +1 month")."');\" style=\"color:#ffffff\">>></a>
							</td>
						</tr>
						<tr>";

						for ($i = 0; $i < $loop; $i++) {
							$tbl .= "
							<td class=\"sched_rowHead\" style=\"font-weight:bold;text-align:center;background:#efefef ;padding:2px 0\">
								".date("D",strtotime("$start_date $DaysToAd[$i]"))."
							</td>";
						}
						for ($p = 1; $p < 6; $p++) {
							$tbl .= "
							<tr >";

							for ($i = 0; $i < $loop; $i++) {
								$jobs = $this->fetch_install_jobs(date("Y-m-d",strtotime("$start_date $DaysToAd[$i]")));
								$tbl .= "
								<td style=\"width:14.28%;vertical-align:top;".(date("Y-m-d") == date("Y-m-d",strtotime("$start_date $DaysToAd[$i]")) ? "background-color:yellow;" : "background-color:#ffffff;")."\">
									<div style=\"font-weight:bold;\">".date("d",strtotime("$start_date $DaysToAd[$i]"))."</div>";
									for ($k = 0; $k < count($jobs); $k++) {
										$title = $jobs[$k]['customer']." : ".$jobs[$k]['proposal_no'];
										$tbl .=
										"<div style=\"margin-top:10px;padding:4px;background-color:#efefef;border:1px solid #8c8c8c\" title=\"".$title."\">
											".($this->p->ck(get_class($this),"A","schedule") ? "
												<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','view_job','proposal_hash=".$jobs[$k]['proposal_hash']."','popup_id=work_order_detail','start_date=".strtotime("$start_date $DaysToAd[$i]")."')\" title=\"View &amp; edit this job\">".(strlen($title) > 22 ?
													substr($title,0,20)."..." : $title)."</a>" : (strlen($title) > 22 ?
														substr($title,0,20)."..." : $title))."
											<div style=\"font-style:italic;padding-top:5px;\">".($jobs[$k]['install_start_time'] ?
												"<span style=\"font-style:normal;\">".date("g:i a",$jobs[$k]['install_start_time'])."</span>" : NULL)."
												".($jobs[$k]['pm'] ?
													(strlen($jobs[$k]['pm'] > 22) ?
														substr($jobs[$k]['pm'],0,20)."..." : $jobs[$k]['pm']) : "<img src=\"images/alert.gif\" title=\"There is no project manager assigned to this job!\">")."
											</div>
										</div>";
									}
								if (!count($jobs))
									$tbl .=
									"<div><br /><br /><br /></div>";
								$tbl .= "
								</td>
								";
							}
							$tbl .= "
							</tr>";

							$start_date = date("Y-m-d",strtotime("$start_date +1 week"));
						}

						$tbl .= "
						</tr>
					</table>
				</td>
			</tr>
		</table>";
			/*
			for ($p = 1; $p < ($view == 3 ? 6 : 2); $p++) {
				if ($view == 3)
					$tbl .= "
					<tr style=\"display:block;\" id=\"tr_".$community_hash."|".$this->lot_hash."[$p]\">";

				for ($i = 0; $i < $loop; $i++) {
					if ($this->p)
						$prnt[$p-1][date("D",strtotime("$StartDate $DaysToAd[$i]"))]['date'] = date("d",strtotime("$StartDate $DaysToAd[$i]"));

					$dayNumber = $this->getDayNumber(strtotime($this->current_lot['start_date']),strtotime("$StartDate $DaysToAd[$i]"));
					unset($primary_keys,$reminder_keys,$appt_keys,$appt_count,$reminder_count,$otherAppts);

					$match_task = preg_grep("/^$dayNumber$/",$this->current_lot['phase']);
					list($holiday_date,$holiday_name) = $this->getHolidays(date("Y-m-d",strtotime("$StartDate $DaysToAd[$i]")));

					$tbl .= "
					<td class=\"sched_days\" style=\"text-align:left;background-color:".$this->GetDayColor($dayNumber,$this->current_lot['start_date'],$holiday_date).";\" valign=\"top\">
						<table style=\"font-weight:bold;padding:3px 0 8px 5px;background-color:#d9d9d9;width:100%;\" >
							<tr>
								<td style=\"font-size:8pt;\" nowrap>".
								($view == 3 ?
									"
									<div style=\"float:right;\">
										<a href=\"scheduleDaily.php?date=".strtotime("$StartDate $DaysToAd[$i]")."&view=".$_REQUEST['view'].($_REQUEST['view_lot'] ? "&view_lot=".$_REQUEST['view_lot']."&view_community=".$_REQUEST['view_community'] : NULL)."\" style=\"text-decoration:underline;\" title=\"Jump To Daily View\">
											".date("M d",strtotime("$StartDate $DaysToAd[$i]"))."
										</a>
									</div>" : NULL).($dayNumber > 0 ? "
									Day: $dayNumber" : NULL)."
								</td>
							</tr>
						</table>";
					while (list($key) = each($match_task)) {
						list($task_type) = $this->break_code($this->current_lot['task'][$key]);
						if (in_array($task_type,$primary))
							$primary_keys[] = $key;
						elseif (in_array($task_type,$reminders)) {
							$reminder_keys[] = $key;
							if ($this->current_lot['sched_status'][$key] != 4)
								$reminder_count++;
						}
						elseif (in_array($task_type,$appts)) {
							$appt_keys[] = $key;
							if ($this->current_lot['sched_status'][$key] != 4)
								$appt_count++;
						}

						$otherAppts = $this->otherAppointments($this->lot_hash,$this->current_lot['start_date'],$dayNumber);
					}

					if ($this->p)
						$prnt[$p-1][date("D",strtotime("$StartDate $DaysToAd[$i]"))]['tasks'] = array();

					//Labor Tasks First
					if (count($primary_keys) > 0) {
						$tbl .= "
						<table cellspacing=\"0\">";
						for ($j = 0; $j < count($primary_keys); $j++) {
							if ($this->p)
								array_push($prnt[$p-1][date("D",strtotime("$StartDate $DaysToAd[$i]"))]['tasks'],$this->current_lot['task'][$primary_keys[$j]]."|".$this->current_lot['sched_status'][$primary_keys[$j]]."|"."-".$this->profile_object->getTaskName($this->current_lot['task'][$primary_keys[$j]]));

							$tbl .= "
							<tr>
								<td style=\"padding:5px 2px 0 0;vertical-align:top;".($_REQUEST['action'] == "edit_task" && $this->current_lot['task'][$primary_keys[$j]] == $this->task_id && $this->lot_hash == $this->edit_lot ? "background-color:yellow;" : NULL)."\"><img src=\"images/arrow2.gif\"></td>
								<td style=\"".($_REQUEST['action'] == "edit_task" && $this->current_lot['task'][$primary_keys[$j]] == $this->task_id && $this->lot_hash == $this->edit_lot ? "background-color:yellow;" : NULL).($this->p ? ";font-size:13;" : ";font-size:11;")."\">
									".($this->p ?
										"<div style=\"".$this->setColor($this->current_lot['sched_status'][$primary_keys[$j]],$this->current_lot['task'][$primary_keys[$j]])."\">" : "
											<a href=\"".($index ? "schedule.php" : NULL)."?action=edit_task&view=$view".($_REQUEST['view_lot'] ? "&view_lot=".$_REQUEST['view_lot']."&view_community=".$_REQUEST['view_community'] : NULL)."&GoToDay=".strtotime($StartDate)."&task_id=".$this->current_lot['task'][$primary_keys[$j]]."&community=$community_hash&lot_hash=".$this->lot_hash."#".$this->lot_hash."\" style=\"".$this->setColor($this->current_lot['sched_status'][$primary_keys[$j]],$this->current_lot['task'][$primary_keys[$j]])."\">")."
									".$this->profile_object->getTaskName($this->current_lot['task'][$primary_keys[$j]]).
									($this->current_lot['duration'][$primary_keys[$j]] > 1 && !ereg("-",$this->current_lot['task'][$primary_keys[$j]]) ?
										"(1/".$this->current_lot['duration'][$primary_keys[$j]].")" : (ereg("-",$this->current_lot['task'][$primary_keys[$j]]) ?
											"(".substr($this->current_lot['task'][$primary_keys[$j]],(strpos($this->current_lot['task'][$primary_keys[$j]],"-") + 1))."/".$this->current_lot['duration'][$primary_keys[$j]].")" : NULL))."
									".($this->p ?
										"</div>" : "
											</a>")."
								</td>
							</tr>";
							}
						$tbl .= "
						</table>";
					}

					//Reminders Next
					if (count($reminder_keys) > 0 && (!$this->p || ($this->p && $prefs->row['sched_show_reminders'] == 1))) {
						unset($reminder_array);
						for ($j = 0; $j < count($reminder_keys); $j++)
							$reminder_array[] = $this->current_lot['task'][$reminder_keys[$j]];

						$tbl .= "
						<table>".(!$y ? "
							<tr>
								<td style=\"padding:5px 2px 0 0;vertical-align:top;\"><img src=\"images/collapse.gif\" name=\"imgrm_".$this->lot_hash."_$dayNumber\" style=\"display:none;\"></td>
								<td class=\"menutitle\" nowrap>
									<a href=\"javascript:void(0);\" onClick=\"shoh('rm_".$this->lot_hash."_$dayNumber')\" style=\"color:blue;".(!$reminder_count ? "text-decoration:line-through" : NULL)."\">
									<div style=\"font-size:11px;\">$reminder_count Reminders</div>
									</a>
								</td>
							</tr>" : NULL)."
							<tr style=\"text-align:left;display:".($y ? "block;" : ($_REQUEST['action'] == "edit_task" && in_array($this->task_id,$reminder_array) && $this->lot_hash == $this->edit_lot ? "block;" : "none;")).";\" id=\"rm_".$this->lot_hash."_$dayNumber\">
								<td></td>
								<td>
									<table cellspacing=\"0\">";
								for ($j = 0; $j < count($reminder_keys); $j++) {
									if ($this->p && $prefs->row['sched_show_reminders'] == 1)
										array_push($prnt[$p-1][date("D",strtotime("$StartDate $DaysToAd[$i]"))]['tasks'],$this->current_lot['task'][$reminder_keys[$j]]."|".$this->current_lot['sched_status'][$reminder_keys[$j]]."|"."-".$this->profile_object->getTaskName($this->current_lot['task'][$reminder_keys[$j]]));

									$tbl .= "
										<tr>
											<td style=\"padding:5px 2px 0 0;vertical-align:top;".($_REQUEST['action'] == "edit_task" && $this->current_lot['task'][$reminder_keys[$j]] == $this->task_id && $this->lot_hash == $this->edit_lot ? "background-color:yellow;" : NULL)."\"><img src=\"images/arrow2.gif\"></td>
											<td ".($_REQUEST['action'] == "edit_task" && $this->current_lot['task'][$reminder_keys[$j]] == $this->task_id && $this->lot_hash == $this->edit_lot ? "style=\"background-color:yellow;\"" : NULL).">
												".($y ?
													"<div style=\"".$this->setColor($this->current_lot['sched_status'][$reminder_keys[$j]],$this->current_lot['task'][$reminder_keys[$j]])."\">" : "
														<a href=\"".($index ? "schedule.php" : NULL)."?action=edit_task&view=$view".($_REQUEST['view_lot'] ? "&view_lot=".$_REQUEST['view_lot']."&view_community=".$_REQUEST['view_community'] : NULL)."&GoToDay=".strtotime($StartDate)."&task_id=".$this->current_lot['task'][$reminder_keys[$j]]."&community=$community_hash&lot_hash=".$this->lot_hash."#".$this->lot_hash."\" style=\"".$this->setColor($this->current_lot['sched_status'][$reminder_keys[$j]],$this->current_lot['task'][$reminder_keys[$j]])."\">")."
												".$this->profile_object->getTaskName($this->current_lot['task'][$reminder_keys[$j]])."
												".($y ?
													"</div>" : "
														</a>")."
											</td>
										</tr>";
								}
						$tbl .= "
									</table>
								</td>
							</tr>
						</table>";
					}

					//Appointments Last
					if ((count($appt_keys) > 0 || $otherAppts) && (!$this->p || ($this->p && $prefs->row['sched_show_appts'] == 1))) {
						unset($appt_array);
						for ($j = 0; $j < count($appt_keys); $j++)
							$appt_array[] = $this->current_lot['task'][$appt_keys[$j]];

						$appt_count += count($otherAppts);

						$tbl .= "
						<table>".(!$y ? "
							<tr>
								<td style=\"padding:5px 2px 0 0;vertical-align:top;\"><img src=\"images/collapse.gif\" name=\"imgapp_".$this->lot_hash."_$dayNumber\" style=\"display:none;\"></td>
								<td class=\"menutitle\">
									<a href=\"javascript:void(0);\" onClick=\"shoh('app_".$this->lot_hash."_$dayNumber')\" style=\"color:blue;".(!$appt_count ? "text-decoration:line-through" : NULL)."\">
									$appt_count Appt(s)
									</a>
								</td>
							</tr>" : NULL)."
							<tr style=\"width:auto;text-align:left;display:".($y ? "block;" : ($_REQUEST['action'] == "edit_task" && @in_array($this->task_id,$appt_array) && $this->lot_hash == $this->edit_lot ? "block;" : "none;")).";\" id=\"app_".$this->lot_hash."_$dayNumber\">
								<td></td>
								<td>
									<table cellspacing=\"0\">";
								for ($j = 0; $j < count($appt_keys); $j++) {
									if ($this->p && $prefs->row['sched_show_appts'] == 1)
										array_push($prnt[$p-1][date("D",strtotime("$StartDate $DaysToAd[$i]"))]['tasks'],$this->current_lot['task'][$appt_keys[$j]]."|".$this->current_lot['sched_status'][$appt_keys[$j]]."|"."-".$this->profile_object->getTaskName($this->current_lot['task'][$appt_keys[$j]]));

									$tbl .= "
										<tr>
											<td style=\"padding:5px 2px 0 0;vertical-align:top;".($_REQUEST['action'] == "edit_task" && $this->current_lot['task'][$appt_keys[$j]] == $this->task_id && $this->lot_hash == $this->edit_lot ? "background-color:yellow;" : NULL)."\"><img src=\"images/arrow2.gif\"></td>
											<td ".($_REQUEST['action'] == "edit_task" && $this->current_lot['task'][$appt_keys[$j]] == $this->task_id && $this->lot_hash == $this->edit_lot ? "style=\"background-color:yellow;\"" : NULL).">
												".($y ?
													"<div style=\"".$this->setColor($this->current_lot['sched_status'][$appt_keys[$j]],$this->current_lot['task'][$appt_keys[$j]])."\">" : "
														<a href=\"".($index ? "schedule.php" : NULL)."?action=edit_task&view=$view".($_REQUEST['view_lot'] ? "&view_lot=".$_REQUEST['view_lot']."&view_community=".$_REQUEST['view_community'] : NULL)."&task_id=".$this->current_lot['task'][$appt_keys[$j]]."&lot_hash=".$this->lot_hash."&community=$community_hash&GoToDay=".strtotime($StartDate)."#".$this->lot_hash."\" style=\"".$this->setColor($this->current_lot['sched_status'][$appt_keys[$j]],$this->current_lot['task'][$appt_keys[$j]])."\">")."
												".$this->profile_object->getTaskName($this->current_lot['task'][$appt_keys[$j]])."
												".($y ? "
													</div>" : "
														</a>")."
											</td>
										</tr>";
								}
								for ($j = 0; $j < count($otherAppts); $j++) {
									$result = $db->query("SELECT `title` , `start_date` , `all_day`
														  FROM `appointments`
														  WHERE `obj_id` = '".$otherAppts[$j]."'");
									$row = $db->fetch_assoc($result);

									if (!$row['all_day'])
										$time = "(".date("g:ia",$row['start_date']).")";

									$tbl .= "
										<tr>
											<td style=\"padding:5px 2px 0 0;vertical-align:top;\"><img src=\"images/arrow2.gif\"></td>
											<td>".($y ?
													"<div style=\"".$this->setColor($this->current_lot['sched_status'][$appt_keys[$j]],$this->current_lot['task'][$appt_keys[$j]])."\">" : "
														<a href=\"appt.php?cmd=add&eventID=".base64_encode($otherAppts[$j])."\" style=\"".$this->setColor($this->current_lot['sched_status'][$appt_keys[$j]],$this->current_lot['task'][$appt_keys[$j]])."\">")."
												".$row['title']."&nbsp;$time
												".($y ?
													"</div>" : "
														</a>")."
											</td>
										</tr>";
								}
						$tbl .= "
									</table>
								</td>
							</tr>
						</table>";
					}

					if ($this->p && count($prnt[$p-1][date("D",strtotime("$StartDate $DaysToAd[$i]"))]['tasks']) == 0)
						$prnt[$p-1][date("D",strtotime("$StartDate $DaysToAd[$i]"))]['tasks'] = array(" ");

					if ($holiday_date) {
						$tbl .= "
						<table>
							<tr>
								<td style=\"padding-top:25px;font-style:italic\">
								".implode("<br />",$holiday_name)."
								</td>
							</tr>
						</table>";
					}

					$tbl .= "
					</td>";

				}
				if ($view == 3) {
					$tbl .= "</tr>";
					$StartDate = date("Y-m-d",strtotime("$StartDate +1 week"));
				}
			}

		}*/

		$this->content['html']['place_holder'] = $tbl;
	}

	function doit_cancel_rqst() {
		$type = $this->ajax_vars['type'];
		$proposal_hash = $this->ajax_vars['proposal_hash'];

		switch ($type) {
			case 'pm':
			$result = $this->db->query("SELECT `pm_request_queue`
										FROM `proposals`
										WHERE `proposal_hash` = '$proposal_hash'");
			if ($queue_hash = $this->db->result($result))
				$this->db->query("DELETE FROM `messages`
								  WHERE `queue_hash` = '$queue_hash'");

			$this->db->query("UPDATE `proposals`
							  SET `pm_request` = 0 , `pm_request_queue` = ''
							  WHERE `proposal_hash` = '$proposal_hash'");

			$this->content['html']['pm_request_holder_1'] = "Submit PM Request?";
			$this->content['html']['pm_request_holder_2'] = $this->form->checkbox("name=pm_request","value=1",($this->p->ck(get_class($this),'E','install') ? "onClick=if(this.checked){\$('proj_mngr').value='';\$('proj_mngr_hash').value='';}auto_save();" : NULL));
			break;

			case 'work_order':
			$result = $this->db->query("SELECT `work_order_queue`
										FROM `proposal_install`
										WHERE `proposal_hash` = '$proposal_hash'");
			if ($queue_hash = $this->db->result($result))
				$this->db->query("DELETE FROM `messages`
								  WHERE `queue_hash` = '$queue_hash'");

			$this->db->query("UPDATE `proposal_install`
							  SET `work_order_rqst` = 0 , `work_order_queue` = ''
							  WHERE `proposal_hash` = '$proposal_hash'");

			$result = $this->db->query("SELECT proposals.proposal_no , proposals.proposal_hash , customers.customer_name , proposal_install.work_order_rqst
										FROM `proposals`
										LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
										LEFT JOIN `proposal_install` ON proposal_install.proposal_hash = proposals.proposal_hash
										WHERE proposals.closed = 0 AND proposals.archive = 0 AND proposal_install.work_order_rqst > 0
										ORDER BY proposal_install.work_order_rqst ASC");
			while ($row = $this->db->fetch_assoc($result))
				$pending_work_orders[] = $row;


			$pending = "
			Pending Work Orders (".count($pending_work_orders).")
			<div style=\"height:100px;overflow:auto;font-weight:normal;padding-top:15px;\">";
			for ($i = 0; $i < count($pending_work_orders); $i++) {
				$pending .=
				"<div style=\"padding:5px;\">
					[<small><a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to cancel this quote request?')){agent.call('pm_module','sf_loadcontent','cf_loadcontent','doit_cancel_rqst','type=work_order','proposal_hash=".$pending_work_orders[$i]['proposal_hash']."');}\" title=\"Cancel the work order request\" class=\"link_standard\">X</a></small>]
					&nbsp;
					".date(DATE_FORMAT." ".TIMESTAMP_FORMAT,$pending_work_orders[$i]['work_order_rqst'])." : ".($this->p->ck(get_class($this),"A","work_orders") ? "
					<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','proposal_hash=".$pending_work_orders[$i]['proposal_hash']."','popup_id=work_order_detail');\" title=\"Create a work order for this proposal\">
						".$pending_work_orders[$i]['customer_name']." : ".$pending_work_orders[$i]['proposal_no']."</a>" : $pending_work_orders[$i]['customer_name']." : ".$pending_work_orders[$i]['proposal_no'])."
				</div>";
			}
			$tbl .= "
			</div>";

			$this->content['html']['pending_work_order_list'] = $pending;
			break;

		}
		return;
	}

	function doit_search() {
		$sort = $_POST['sort'];
    	$sort_from_date = $_POST['sort_from_date'];
    	$sort_to_date = $_POST['sort_to_date'];
    	$sales_rep_match = $_POST['sales_rep_match'];
		if (!is_array($sales_rep_match) && $sales_rep_match)
			$sales_rep_match = array($sales_rep_match);

		if (!$sales_rep_match[0])
			unset($sales_rep_match);

		$save = $_POST['page_pref'];
		$detail_search = $_POST['detail_search'];

		$str = "show=$sort|sort_from_date=$sort_from_date|sort_to_date=$sort_to_date|sales_rep=".@implode("&",$sales_rep_match);
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
			if (!$sort)
				$sort = '*';
			if ($sort != '*')
				$sql_p[] = ($sort == 2 ?
					"line_items.po_hash = ''" : "line_items.po_hash != ''");
			if ($sort_from_date && !$sort_to_date)
				$sql_p[] = "work_orders.order_date >= '".$sort_from_date."'";
			elseif ($sort_from_date && $sort_to_date && strtotime($sort_to_date) > strtotime($sort_from_date))
				$sql_p[] = "work_orders.order_date BETWEEN '".$sort_from_date."' AND '".$sort_to_date."'";
			if (is_array($sales_rep_match) && count($sales_rep_match)) {
				$sales_rep_match = array_unique($sales_rep_match);
				$sales_rep_match = array_values($sales_rep_match);
				array_walk($sales_rep_match,'add_quotes',"'");
				$sql_p[] = "proposals.sales_hash IN (".implode(" , ",$sales_rep_match).")";
				array_walk($sales_rep_match,'strip_quotes');
			}
			if ($detail_search) {
				unset($sql_p);
				$proposal_no = $_POST['proposal_no'];
				$wo_no = $_POST['wo_no'];
				$customer_filter = $_POST['customer_filter'];
				if (!is_array($customer_filter) && $customer_filter)
					$customer_filter = array($customer_filter);

				$descr = $_POST['descr'];
				$wo_status = $_POST['wo_status'];

				$save = $_POST['save'];
				$search_name = $_POST['search_name'];
				if (!$search_name)
					unset($save);

				if ($proposal_no)
					$sql_p[] = "proposals.proposal_no LIKE '%".$proposal_no."%'";
				if ($wo_no)
					$sql_p[] = "work_orders.order_no LIKE '%".$wo_no."%'";
				if (is_array($sales_rep_match) && count($sales_rep_match)) {
					$sales_rep_match = array_unique($sales_rep_match);
					$sales_rep_match = array_values($sales_rep_match);
					array_walk($sales_rep_match,'add_quotes',"'");
					$sql_p[] = "proposals.sales_hash IN (".implode(" , ",$sales_rep_match).")";
					array_walk($sales_rep_match,'strip_quotes');
				}
				if (is_array($customer_filter) && count($customer_filter)) {
					$customer_filter = array_unique($customer_filter);
					$customer_filter = array_values($customer_filter);
					array_walk($customer_filter,'add_quotes',"'");
					$sql_p[] = "proposals.customer_hash IN (".implode(" , ",$customer_filter).")";
					array_walk($customer_filter,'strip_quotes');
				}
				if ($descr)
					$sql_p[] = "work_orders.order_descr LIKE '%".$descr."%'";
				if ($wo_status)
					$sql_p[] = "line_items.status = '".($wo_status == 1 ? "0" : "1")."' AND line_items.invoice_hash ".($wo_status == 3 ? " <> " : " = ")."''";
			}

			if ($sql_p)
				$sql = implode(" AND ",$sql_p);

			$r = $this->db->query("SELECT work_orders.obj_id
								   FROM `work_orders`
								   LEFT JOIN `proposals` ON proposals.proposal_hash = work_orders.proposal_hash
								   LEFT JOIN `line_items` ON line_items.work_order_hash = work_orders.order_hash".($sql ? "
								   WHERE $sql" : NULL)."
								   GROUP BY work_orders.order_hash");
			$total = $this->db->num_rows($r);
			$search_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			while (global_classes::key_exists('search','search_hash',$search_hash))
				$search_hash = md5(global_classes::get_rand_id(32,"global_classes"));

			$this->db->query("INSERT INTO `search`
							  (`timestamp` , `search_hash` , `saved` , `search_name` , `search_class` , `user_hash` , `detail_search` , `query` , `total` , `search_str`)
							  VALUES (".time()." , '$search_hash' , '$save' , '$search_name' , 'work_orders' , '".$this->current_hash."' , '$detail_search' , '".base64_encode($sql)."' , '$total' , '$str')");

			$this->active_search = $search_hash;
		}

		$this->content['action'] = ($detail_search ? 'close' : 'continue');
		$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','active_search=".$this->active_search."');";
		return;
	}

	function search_work_orders() {
		global $stateNames,$states;

		$date = date("U") - 3600;
		$result = $this->db->query("DELETE FROM `search`
							  		WHERE `timestamp` <= '$date'");

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Search Work Orders";
		$this->content['focus'] = 'proposal_no';

		// Limit user list if permission checked for Trac #1221
		if ($this->p->ck('proposals','S')) {
			$user_name[] = $_SESSION['my_name'];
			$user_hash[] = $_SESSION['id_hash'];
		} else {
			$users = new system_config($this->current_hash);
			$users->fetch_users();
			$user_name[] = "All Sales Reps";
			$user_hash[] = "";
			for ($i = 0; $i < count($users->user_info); $i++) {
				$user_name[] = $users->user_info[$i]['full_name'];
				$user_hash[] = $users->user_info[$i]['user_hash'];
			}
			unset($users);
		}
		$this->content['focus'] = "wo_no";

		list($search_name,$search_hash) = $this->fetch_searches('work_orders');

		$tbl .= $this->form->form_tag().($this->p->ck('proposals','S') ? $this->form->hidden(array('sales_rep_match[]' => $_SESSION['id_hash'])) : NULL).
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
							Filter your work order search criteria below:
						</div>
						<table>
							<tr>
								<td style=\"padding-left:15px;\">
									<fieldset>
										<legend>Work Order Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=wo_no","size=15","maxlength=12")."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;\">
									<fieldset>
										<legend>Proposal Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=proposal_no","size=35","maxlength=12")."</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Sales Rep</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->select("sales_rep_match[]",$user_name,($this->p->ck('proposals','S') ? $_SESSION['id_hash'] : ''),$user_hash,"multiple=multiple","size=6","cols=5","blank=1",($this->p->ck('proposals','S') ? "disabled" : NULL))."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Search By Customer</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
											".$this->form->text_box("name=customer",
																	"value=",
																	"autocomplete=off",
																	"size=37",
																	"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('reports','customer','customer_hash',1);}",
								                                    "onKeyUp=if(ka==false && this.value){key_call('reports','customer','customer_hash',1);}",
																	"onBlur=key_clear();"
																	)."


											<div style=\"margin-top:15px;width:250px;height:45px;overflow:auto;display:block;background-color:#efefef;border:1px outset #cccccc;\" id=\"customer_filter_holder\"></div>
										</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Work Order Status</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->select("wo_status",array("Proposed","Booked","Invoiced"),'',array(1,2,3))."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Work Order Description</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=descr","size=35","maxlength=64")."</div>
									</fieldset>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<div style=\"float:right;text-align:right;padding-top:15px;margin-right:25px;\">
				".$this->form->button("value=Search","id=primary","onClick=submit_form($('popup_id').form,'pm_module','exec_post','refresh_form','action=doit_search');")."
			</div>
			<div style=\"margin:15px;\">
				<table>
					<tr>
						<td>
							".$this->form->checkbox("name=save","value=1","onClick=if(this.checked){toggle_display('save_search_box','block')}else{toggle_display('save_search_box','none')}")."&nbsp;
							Save Search?
						</td>
						<td>".($search_hash ? "
							<div style=\"margin-left:15px;\">
								".$this->form->select("saved_searches",$search_name,'',$search_hash,"style=width:100px","onChange=if(this.options[this.selectedIndex].value != ''){toggle_display('search_go_btn','block');}else{toggle_display('search_go_btn','none');}")."&nbsp;
								".$this->form->button("value=Go","onClick=agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','active_search='+\$F('saved_searches'));window.setTimeout('popup_windows[\'".$this->content['popup_controls']['popup_id']."\'].hide()',1000);")."
								&nbsp;
								".$this->form->button("value=&nbsp;X&nbsp;","title=Delete this saved search","onClick=if(\$('saved_searches') && \$('saved_searches').options[\$('saved_searches').selectedIndex].value){if(confirm('Do you really want to delete your saved search?')){agent.call('pm_module','sf_loadcontent','cf_loadcontent','purge_search','s='+\$('saved_searches').options[\$('saved_searches').selectedIndex].value);\$('saved_searches').remove(\$('saved_searches').selectedIndex);}}")."
							</div>" : NULL)."
						</td>
					</tr>
					<tr>
						<td colspan=\"2\">
							<div style=\"padding-top:5px;display:none;margin-left:5px;\" id=\"save_search_box\">
								Name Your Saved Search:
								<div style=\"margin-top:5px;\">
									".$this->form->text_box("name=search_name","value=","size=35","maxlenth=64")."
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


	function work_order_list() {
		$this->unlock("_");

		$this->load_class_navigation();
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

		$num_pages = ceil($this->total_work_orders / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ($end > $this->total_work_orders)
			$end = $this->total_work_orders;

		$order_by_ops = array("proposal_no"			=>	"proposals.proposal_no",
							  "work_order_no"		=>	"work_orders.order_no",
							  "customer_hash"		=>	"customers.customer_hash",
							  "order_date"			=>	"work_orders.order_date",
							  "sales_rep"			=>	"users.full_name");


		$tbl = $this->fetch_work_orders($start_from,$order_by_ops[($order ? $order : "order_no")],$order_dir);
		if ($this->active_search)
			$this->page_pref =& $this->search_vars;

		// Limit user list if permission checked for Trac #1221
		if ($this->p->ck('proposals','S')) {
			$user_name[] = $_SESSION['my_name'];
			$user_hash[] = $_SESSION['id_hash'];
		} else {
			$users = new system_config($this->current_hash);
			$users->fetch_users();
			for ($i = 0; $i < count($users->user_info); $i++) {
				$user_name[] = $users->user_info[$i]['full_name'];
				$user_hash[] = $users->user_info[$i]['user_hash'];
			}

			unset($users);
		}
		$sort_menu = $this->form->form_tag().($this->p->ck('proposals','S') ? $this->form->hidden(array('sales_rep_match[]' => $_SESSION['id_hash'])) : NULL)."
		<div id=\"sort_menu\" class=\"search_suggest\" >
			<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;margin-top:0;\">
				<div style=\"float:right;padding-right:1px;\">
					<a href=\"javascript:void(0);\" onClick=\"toggle_display('sort_menu','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
				</div>
				Sort Options
			</div>
			<div class=\"function_menu_item\" style=\"padding-left:10px;\">
				<small><strong>Show:</strong></small>
				<div style=\"margin-left:10px;padding:5px 0;\">
					<div style=\"padding-bottom:3px;\">".$this->form->radio("name=sort","value=*",(!$this->page_pref || $this->page_pref['show'] == '*' ? "checked" : NULL))." All Work Orders</div>
					<div style=\"padding-bottom:3px;\">".$this->form->radio("name=sort","value=1",($this->page_pref['show'] == 1 ? "checked" : NULL))." Only Booked Work Orders</div>
					<div style=\"padding-bottom:3px;\">".$this->form->radio("name=sort","value=2",($this->page_pref['show'] == 2 ? "checked" : NULL))." Only Proposed Work Orders</div>
				</div>
				<div >
					<small><strong>With dates from:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\" id=\"sort_from_date_holder\">";
					$jscript[] = "setTimeout('DateInput(\'sort_from_date\',false,\'YYYY-MM-DD\',\'".($this->page_pref['sort_from_date'] ? $this->page_pref['sort_from_date'] : '')."\',1,\'sort_from_date_holder\')',500);";
					$sort_menu .= "
					</div>
				</div>
				<div >
					<small><strong>To:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\" id=\"sort_to_date_holder\">";
					$jscript[] = "setTimeout('DateInput(\'sort_to_date\',false,\'YYYY-MM-DD\',\'".($this->page_pref['sort_to_date'] ? $this->page_pref['sort_to_date'] : '')."\',1,\'sort_to_date_holder\')',700);";
					$sort_menu .= "
					</div>
				</div>
				<div >
					<small><strong>And where the sales rep matches:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\">
						".$this->form->select("sales_rep_match[]",$user_name,(is_array($this->page_pref['sales_rep']) ? $this->page_pref['sales_rep'] : ($this->p->ck('proposals','S') ? $_SESSION['id_hash'] : array($this->page_pref['sales_rep']))),$user_hash,"multiple","size=4","blank=1","style=width:160px;",($this->p->ck('proposals','S') ? "disabled" : NULL))."
					</div>
				</div>
			</div>
			<div class=\"function_menu_item\">
				<div style=\"float:right;padding-right:5px;\">".$this->form->button("value=Go","onClick=submit_form(this.form,'pm_module','exec_post','refresh_form','action=doit_search');")."</div>
				".$this->form->checkbox("name=page_pref","value=1",($this->page_pref['custom'] ? "checked" : NULL))."&nbsp;&nbsp;<small><i>Remember Preferences</i></small>
			</div>
		</div>".$this->form->close_form();

		$result = $this->db->query("SELECT proposals.proposal_no , proposals.proposal_hash , customers.customer_name , proposal_install.work_order_rqst
									FROM `proposals`
									LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
									LEFT JOIN `proposal_install` ON proposal_install.proposal_hash = proposals.proposal_hash
									WHERE proposals.closed = 0 AND proposals.archive = 0 AND proposal_install.work_order_rqst > 0
									ORDER BY proposal_install.work_order_rqst ASC");
		while ($row = $this->db->fetch_assoc($result))
			$pending_work_orders[] = $row;

		if (count($pending_work_orders)) {
			$tbl .= "
			<div style=\"margin-bottom:5px;margin-left:20px;\" >
				 <table class=\"tborder\" cellspacing=\"1\" cellpadding=\"6\" style=\"width:75%;background-color:#cccccc\">
					<tr>
						<td style=\"font-weight:bold;vertical-align:top;background-color:#ffffff;width:50%\" id=\"pending_work_order_list\">
							Pending Work Orders (".count($pending_work_orders).")
							<div style=\"height:100px;overflow:auto;font-weight:normal;padding-top:15px;\">";
							for ($i = 0; $i < count($pending_work_orders); $i++) {
								$tbl .=
								"<div style=\"padding:5px;\">
									[<small><a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to cancel this quote request?')){agent.call('pm_module','sf_loadcontent','cf_loadcontent','doit_cancel_rqst','type=work_order','proposal_hash=".$pending_work_orders[$i]['proposal_hash']."');}\" title=\"Cancel the work order request\" class=\"link_standard\">X</a></small>]
									&nbsp;
									".date(DATE_FORMAT." ".TIMESTAMP_FORMAT,$pending_work_orders[$i]['work_order_rqst'])." : ".($this->p->ck(get_class($this),"A","work_orders") ? "
									<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','proposal_hash=".$pending_work_orders[$i]['proposal_hash']."','popup_id=work_order_detail');\" title=\"Create a work order for this proposal\">
										".$pending_work_orders[$i]['customer_name']." : ".$pending_work_orders[$i]['proposal_no']."</a>" : $pending_work_orders[$i]['customer_name']." : ".$pending_work_orders[$i]['proposal_no'])."
								</div>";
							}
							$tbl .= "
							</div>
						</td>
					</tr>
				</table>
			</div>";
		}

		$tbl .= ($this->active_search && $this->detail_search ? "
		<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
		<div style=\"padding-top:8px;padding-left:25px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list');\">Show All</a>]</div>" : NULL)."
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"padding:15;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"7\">
								<div style=\"float:right;text-align:right;font-weight:normal;padding-right:10px;\">".($this->total_work_orders ? "
									".paginate_jscript($num_pages,$p,'sf_loadcontent','cf_loadcontent','pm_module','work_order_list',$order,$order_dir,"'active_search=".$this->active_search."'","popup_id={$this->popup_id}'") : NULL)."
									<div style=\"padding-top:5px;\">
										<a href=\"javascript:void(0);\" onMouseOver=\"position_element($('sort_menu'),findPos(this,'top')+15,findPos(this,'left')-100);\" onClick=\"toggle_display('sort_menu','block');\"><img src=\"images/arrow_down2.gif\" id=\"sort_arrow_down\" border=\"0\" title=\"Change sorting options\" /></a>
										&nbsp;
										<span style=\"cursor:hand;\" onMouseOver=\"position_element($('sort_menu'),findPos($('sort_arrow_down'),'top')+15,findPos($('sort_arrow_down'),'left')-100);\" onClick=\"toggle_display('sort_menu','block');\" title=\"Change sorting options\"><small>Sort Options</small></span>
										".$sort_menu."
									</div>
								</div>".($this->total_work_orders ? "
								Showing ".($start_from + 1)." - ".($start_from + MAIN_PAGNATION_NUM > $this->total_work_orders ? $this->total_work_orders : $start_from + MAIN_PAGNATION_NUM)." of ".$this->total_work_orders." Work Orders." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->p->ck(get_class($this),'A','work_orders') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','popup_id=work_order_detail');\"><img src=\"images/new.gif\" title=\"Create a new work order\" border=\"0\" /></a>" : NULL)."
									&nbsp;".($this->p->ck(get_class($this),'L','work_orders') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','search_work_orders','popup_id=main_popup','active_search=".$this->active_search."');\"><img src=\"images/search.gif\" title=\"Search work orders\" border=\"0\" /></a>" : NULL)."
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','p=$p','order=work_order_no','order_dir=".($order == "work_order_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search=".$this->active_search."'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">
									Work Order No.</a>
								".($order == 'work_order_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','p=$p','order=proposal_no','order_dir=".($order == "proposal_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search=".$this->active_search."'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">
									Proposal No.</a>
								".($order == 'proposal_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','p=$p','order=customer_hash','order_dir=".($order == "customer_hash" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search=".$this->active_search."'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">
									Customer</a>
								".($order == 'customer_hash' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td >Description</td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','p=$p','order=order_date','order_dir=".($order == "order_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search=".$this->active_search."'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">
									Order Date</a>
								".($order == 'order_date' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','cf_loadcontent','work_order_list','p=$p','order=sales_rep','order_dir=".($order == "sales_rep" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search=".$this->active_search."'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">
									Sales Rep</a>
								".($order == 'sales_rep' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td class=\"num_field\" style=\"padding-right:5px;\">Complete</td>
						</tr>";
						for ($i = 0; $i < ($end - $start_from); $i++) {
							unset($order_status);
							if ($this->work_orders[$i]['line_item_invoice_hash'])
								$order_status = $this->status_icons['IN'];
							elseif ($this->work_orders[$i]['status'] == 1)
								$order_status = $this->status_icons['BK'];
							elseif ($this->work_orders[$i]['line_item_hash'])
								$order_status = $this->status_icons['PR'];

							$b++;
							$tbl .= "
							<tr ".($this->p->ck(get_class($this),'V','work_orders') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','popup_id=work_order_detail','order_hash=".$this->work_orders[$i]['order_hash']."','p=$p','order=$order','order_dir=$order_dir','active_search=".$this->active_search."');\"" : NULL).">
								<td>".$this->work_orders[$i]['order_no'].($order_status ?
									"&nbsp;&nbsp;<span title=\"".$order_status['msg']."\">".$order_status['img']."</span>" : NULL)."
								</td>
								<td>".$this->work_orders[$i]['proposal_no']."</td>
								<td>".stripslashes($this->work_orders[$i]['customer_name'])."</td>
								<td>".(strlen($this->work_orders[$i]['order_descr']) > 55 ?
									stripslashes(substr($this->work_orders[$i]['order_descr'],0,55))."...." : stripslashes($this->work_orders[$i]['order_descr']))."</td>
								<td >".date(DATE_FORMAT,strtotime($this->work_orders[$i]['order_date']))."</td>
								<td >".stripslashes($this->work_orders[$i]['sales_rep'])."</td>
								<td class=\"num_field\" style=\"padding-right:15px;\">".($this->work_orders[$i]['order_complete'] ? "<img src=\"images/check.gif\" />" : NULL)."</td>
							</tr>".($b < ($end - $start_from) ? "
							<tr>
								<td style=\"background-color:#cccccc;\" colspan=\"7\"></td>
							</tr>" : NULL);
						}

						if (!$this->total_work_orders)
							$tbl .= "
							<tr >
								<td colspan=\"6\">".($this->active_search ?
									"<div style=\"padding-top:10px;font-weight:bold;\">".($this->detail_search ?
										"Your search " : "Your sort options ")."returned no results.</div>" : "You have no work orders to display. ".($this->p->ck(get_class($this),'A','work_orders') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','popup_id=work_order_detail');\">Create a new work order now.</a>" : NULL))."
								</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		$this->content['jscript'] = $jscript;
		$this->content['html']['place_holder'] = $tbl;
	}













}
?>