<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class proposals extends AJAX_library {

	public $total;

	public $proposal_info = array();

	public $current_proposal;

	public $content = '';
	public $ajax_vars;

	private $form;
	private $check;

	public $status_icons = array(
    	'H'	=>	array(
        	"img"	=>	"<img src=\"images/red.gif\">",
			"msg"	=>	"On hold, pending more information"
    	),
		'B'	=>	array(
    		"img"	=>	"<img src=\"images/green.gif\">",
			"msg"	=>	"Booked"
    	),
		'PI'	=>	array(
    		"img"	=>	"<img src=\"images/blue.gif\">",
			"msg"	=>	"Partially Invoiced"
    	),
		'FI'	=>	array(
    		"img"	=>	"<img src=\"images/blue.gif\">",
			"msg"	=>	"Fully Invoiced"
    	),
		'PL'	=>	array(
    		"img"	=>	"<img src=\"images/yellow.gif\">",
			"msg"	=>	"Punchlist"
    	),
		'C'	=>  array(
    		'img' =>	"<img src=\"images/black.gif\" />",
			'msg' =>	"Complete"
    	)
    );

	function proposals($passedHash=NULL) {
		global $db;

		$this->form = new form;
		$this->db =& $db;
		$this->cust = new custom_fields(get_class($this));

		if ($passedHash)
			$this->current_hash = $passedHash;
		else
			$this->current_hash = $_SESSION['id_hash'];

		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);
		//On an infrequent basis, archive proposals that haven't been touched over X days

		if (!$_POST['active_search'] && $this->page_pref = $this->p->page_pref(get_class($this))) {
			if ($this->page_pref['show'] != '*')
				$sql_p[] = ($this->page_pref['show'] == 2 ?
					"proposals.archive = 1" : "proposals.archive = 0");
			if ($this->page_pref['sort_from_date'])
				$sql_p[] = "proposals.creation_date >= ".strtotime($this->page_pref['sort_from_date']);
			if ($this->page_pref['sort_to_date'] && strtotime($this->page_pref['sort_to_date']) > strtotime($this->page_pref['sort_from_date']))
				$sql_p[] = "proposals.creation_date <= ".strtotime($this->page_pref['sort_to_date']);
			if ($this->page_pref['sales_rep']) {
				if (!is_array($this->page_pref['sales_rep']))
					$this->page_pref['sales_rep'] = array($this->page_pref['sales_rep']);

				$this->page_pref['sales_rep'] = array_unique($this->page_pref['sales_rep']);
				$this->page_pref['sales_rep'] = array_values($this->page_pref['sales_rep']);
				array_walk($this->page_pref['sales_rep'], 'add_quotes', "'");
				$sql_p[] = "proposals.sales_hash IN (".implode(" , ", $this->page_pref['sales_rep']).")";
				array_walk($this->page_pref['sales_rep'], 'strip_quotes');
			}
			if (!$this->active_search)
				$this->page_pref['custom'] = 1;
		}
		if ($sql_p) {
			$sql = implode(" AND ", $sql_p);
			$this->page_pref['str'] = $sql;

			if ($sql) {
				if (ereg("line_items", $sql))
					$s_line_items = true;
				if (ereg("purchase_order", $sql))
					$s_purchase_order = true;
				if (ereg("customer_invoice", $sql))
					$s_customer_invoice = true;
			}

			$result = $this->db->query("SELECT COUNT(proposals.obj_id) as Total
										FROM `proposals`
										LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash".($s_purchase_order ? "
										LEFT JOIN `purchase_order` ON purchase_order.proposal_hash = proposals.proposal_hash AND purchase_order.deleted = 0" : NULL).($s_customer_invoice ? "
									    LEFT JOIN `customer_invoice` ON customer_invoice.proposal_hash = proposals.proposal_hash AND customer_invoice.deleted = 0" : NULL).($s_line_items ? "
									    LEFT JOIN `line_items` ON line_items.proposal_hash = proposals.proposal_hash" : NULL)."
										LEFT JOIN `users` ON users.id_hash = proposals.sales_hash".($sql ? "
										WHERE $sql
										GROUP BY proposals.proposal_hash" : NULL));
			$this->total = $this->db->num_rows($result);
		} else {
			if ($_POST['active_search']) {
				$result = $this->db->query("SELECT `detail_search` , `query` , `total` , `search_str`
											FROM `search`
											WHERE `search_hash` = '".$_POST['active_search']."'");
				$row = $this->db->fetch_assoc($result);
				$total = $row['total'];
				$sql = base64_decode($row['query']);

				if ($sql) {
					if (ereg("line_items", $sql))
						$s_line_items = true;
					if (ereg("purchase_order", $sql))
						$s_purchase_order = true;
					if (ereg("customer_invoice", $sql))
						$s_customer_invoice = true;

					$sql = trim($sql);
					if (strtoupper(substr($sql, 0, 3)) == 'AND')
						$sql = substr($sql, 3);
				}
				$r = $this->db->query("SELECT COUNT(proposals.obj_id) AS Total
									   FROM `proposals`
									   LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash".($s_purchase_order ? "
									   LEFT JOIN `purchase_order` ON purchase_order.proposal_hash = proposals.proposal_hash AND purchase_order.deleted = 0" : NULL).($s_customer_invoice ? "
									   LEFT JOIN `customer_invoice` ON customer_invoice.proposal_hash = proposals.proposal_hash AND customer_invoice.deleted = 0" : NULL).($s_line_items ? "
									   LEFT JOIN `line_items` ON line_items.proposal_hash = proposals.proposal_hash" : NULL)."
									   LEFT JOIN `users` ON users.id_hash = proposals.sales_hash".($sql ? "
									   WHERE $sql
									   GROUP BY proposals.proposal_hash" : NULL));
				$this->total = $this->db->num_rows($r);

				if ($this->total != $total)
					$this->db->query("UPDATE `search`
									  SET `total` = '".$this->total."'
									  WHERE `search_hash` = '".$_POST['active_search']."'");

			} else {
				// Limit proposal view to only those owned by sales rep for Trac #1221
				if ($this->p->ck(get_class($this), 'S'))
					$sql = "proposals.sales_hash = '".$_SESSION['id_hash']."' ";

				$result = $this->db->query("SELECT COUNT(*) AS Total
											FROM `proposals`".($sql ? "
											WHERE $sql" : NULL));
				$this->total = $this->db->result($result);
			}
		}
	}

	function __destruct() {
		$this->content = '';
	}

	function fetch_proposals($start, $order_by=NULL, $order_dir=NULL) {

		$end = MAIN_PAGNATION_NUM;
        $this->proposal_info = array();
        $_total = 0;

		if ( $this->active_search ) {

			$result = $this->db->query("SELECT
                                			t1.detail_search,
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

		if ( $sql ) {

			if ( preg_match('/line_items/', $sql) )
				$s_line_items = true;
			if ( preg_match('/purchase_order/', $sql) )
				$s_purchase_order = true;
			if ( preg_match('/customer_invoice/', $sql) )
				$s_customer_invoice = true;
		}

		if ( $this->p->ck(get_class($this), 'S') ) { # Trac #1221

			$sql .=
			( $sql ?
    			" AND " : NULL
			) . "proposals.sales_hash = '{$_SESSION['id_hash']}' ";
		}

	    if ( $order_by || $sql ) {

            $pattern = array(
                '/\bproposals\.(.*)\b/U',
                '/\bcustomers\.(.*)\b/U',
                '/\bpurchase_order\.(.*)\b/U',
                '/\bcustomer_invoice\.(.*)\b/U',
                '/\bline_items\.(.*)\b/U',
                '/\busers\.(.*)\b/U'
            );
            $replace = array(
                't1.$1',
                't2.$1',
                't3.$1',
                't4.$1',
                't5.$1',
                't6.$1'
            );

            if ( $order_by )
                $order_by = preg_replace($pattern, $replace, $order_by);

            if ( $sql )
                $sql = preg_replace($pattern, $replace, $sql);
        }

		$result = $this->db->query("SELECT
	                            		t1.proposal_status,
	                            		t1.status_comment,
	                                    t1.user_status,
	                                    t1.user_status_comment,
	                                    t1.proposal_hash,
	                                    t1.proposal_no,
										t1.proposal_descr,
										t1.creation_date,
										t2.customer_name,
										t6.full_name
							  		FROM proposals t1
									LEFT JOIN customers t2 ON t2.customer_hash = t1.customer_hash " .
                            		( $s_purchase_order ?
    									"LEFT JOIN purchase_order t3 ON t3.proposal_hash = t1.proposal_hash AND t3.deleted = 0 " : NULL
                            		) .
                            		( $s_customer_invoice ?
    								    "LEFT JOIN customer_invoice t4 ON t4.proposal_hash = t1.proposal_hash AND t4.deleted = 0 " : NULL
                            		) .
                            		( $s_line_items ?
    									"LEFT JOIN line_items t5 ON t5.proposal_hash = t1.proposal_hash" : NULL
                            		) . "
									LEFT JOIN users t6 ON t6.id_hash = t1.sales_hash " .
                            		( $sql ?
    							  		"WHERE $sql
                            			AND t1.deleted = 0
    									GROUP BY t1.proposal_hash " : "WHERE t1.deleted = 0 "
    								) .
    								( $order_by ?
    							  		"ORDER BY $order_by " .
        								( $order_dir ?
            								$order_dir : "ASC"
            							) : NULL
            						) . "
							  		LIMIT $start, $end");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$_total++;
			array_push($this->proposal_info, $row);
		}

		return $_total;
	}

	function fetch_master_record($proposal_hash, $edit=false) {
		if (!$proposal_hash)
			return;


		$result = $this->db->query("SELECT proposals.proposal_hash AS parent_proposal_hash , proposals.* , proposal_design.* , proposal_install.* ,
									customers.customer_name AS customer , arch_design.name AS arch ,
									vendors.vendor_name AS direct_vendor , customers.gsa AS gsa_customer ,
									customers.tax_exempt_id AS tax_exempt_customer
								    FROM `proposals`
									LEFT JOIN `proposal_design` ON proposal_design.proposal_hash = proposals.proposal_hash
									LEFT JOIN `proposal_install` ON proposal_install.proposal_hash = proposals.proposal_hash
									LEFT JOIN `customers` ON customers.customer_hash = proposals.customer_hash
									LEFT JOIN `vendors` ON vendors.vendor_hash = proposals.direct_vendor_hash
									LEFT JOIN `arch_design` ON arch_design.arch_hash = proposals.arch_hash
								    WHERE proposals.proposal_hash = '$proposal_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_proposal = $row;
			$this->proposal_hash = $row['proposal_hash'];

            $r = $this->db->query("SELECT COUNT(*) AS total
                                   FROM `line_items`
                                   WHERE `proposal_hash` = '".$this->proposal_hash."' AND `active` = 1 AND `status` = '1'");
            $this->current_proposal['invoiced_line_items'] = $this->db->result($r, 0, 'total');

            $r = $this->db->query("SELECT COUNT(*) AS total
                                   FROM `line_items`
                                   WHERE `proposal_hash` = '".$this->proposal_hash."' AND `invoice_hash` != '' AND `active` = 1");
            $this->current_proposal['booked_line_items'] = $this->db->result($r, 0, 'total');

			unset($this->current_proposal['status']);
			if ($this->current_proposal['invoiced_line_items'] >= $this->current_proposal['booked_line_items'] && $this->current_proposal['booked_line_items'] > 0)
				$this->current_proposal['status'] = 2;
			elseif ($this->current_proposal['booked_line_items'] > 0)
				$this->current_proposal['status'] = 1;

			if ($edit)
				$this->lock = $this->content['row_lock'] = $this->p->lock("proposals", $this->proposal_hash, ($this->ajax_vars['new'] ? '' : $this->popup_id));

			$users = new system_config();
			//For each of the users, fetch their record
			$users->fetch_user_record($this->current_proposal['sales_hash']);
			$this->current_proposal['sales_rep'] = $users->current_user['full_name'];
			$this->current_proposal['sales_rep_unique_id'] = $users->current_user['unique_id'];

            if ($this->current_proposal['sales_hash_2']) {
                $users->fetch_user_record($this->current_proposal['sales_hash_2']);
                $this->current_proposal['sales_rep_2'] = $users->current_user['full_name'];
            }
			if ($this->current_proposal['designer_hash']) {
				$users->fetch_user_record($this->current_proposal['designer_hash']);
				$this->current_proposal['designer'] = $users->current_user['full_name'];
			}
			if ($this->current_proposal['sales_coord_hash']) {
				$users->fetch_user_record($this->current_proposal['sales_coord_hash']);
				$this->current_proposal['sales_coord'] = $users->current_user['full_name'];
			}
			if ($this->current_proposal['proj_mngr_hash']) {
				$users->fetch_user_record($this->current_proposal['proj_mngr_hash']);
				$this->current_proposal['proj_mngr'] = $users->current_user['full_name'];
			}
			unset($users);

			if ($this->current_proposal['propose_to_hash']) {
				if ($this->current_proposal['propose_to_hash'] != $this->current_proposal['customer_hash']) {
					list($class, $id, $hash) = explode("|", $this->current_proposal['propose_to_hash']);
					if (!$class) {
						$class = 'customers';
						$id = $this->current_proposal['propose_to_hash'];
					}
					if (!$id)
						$id = $this->current_proposal['propose_to_hash'];
					$obj = new customers($this->current_hash);
					$obj->fetch_master_record($id);
					$this->current_proposal['propose_to'] = $obj->current_customer['customer_name'];
					if ($hash) {
						$obj->fetch_location_record($hash);
						$this->current_proposal['propose_to'] .= " : ".$obj->current_location['location_name'];
					}
					unset($obj);
				} else
					$this->current_proposal['propose_to'] = $this->current_proposal['customer'];
			}

			//For the ship to and install address fields, explode them and fetch their real info
			$location_detail = array('install_addr_hash' 	=> $this->current_proposal['install_addr_hash'],
									 'ship_to_hash'		 	=> $this->current_proposal['ship_to_hash']
									 );
			while (list($key, $info) = each($location_detail)) {
				if ($info) {
					list($class, $id, $hash) = explode("|", $info);
					$obj = new $class($this->current_hash);
					if ($hash) {
						$obj->fetch_location_record($hash);
						$this->current_proposal[strrev(substr(strrev($key), 5))] = $obj->current_location['location_name'];
						$this->current_proposal[strrev(substr(strrev($key), 5))."_city"] = $obj->current_location['location_city'];
						$this->current_proposal[strrev(substr(strrev($key), 5))."_state"] = $obj->current_location['location_state'];
                        $this->current_proposal[strrev(substr(strrev($key), 5))."_zip"] = $obj->current_location['location_zip'];
                        $this->current_proposal[strrev(substr(strrev($key), 5))."_country"] = $obj->current_location['location_country'];
                        $this->current_proposal[strrev(substr(strrev($key), 5))."_tax_lock"] = $obj->current_location['location_tax_lock'];
                        $this->current_proposal[strrev(substr(strrev($key), 5))."_account_no"] = $obj->current_location['location_account_no'];
					} else {
						$obj->fetch_master_record($id);
						$this->current_proposal[strrev(substr(strrev($key), 5))] = $obj->{"current_".strrev(substr(strrev($class), 1))}[strrev(substr(strrev($class), 1))."_name"];
						$this->current_proposal[strrev(substr(strrev($key), 5))."_city"] = $obj->{"current_".strrev(substr(strrev($class), 1))}["city"];
						$this->current_proposal[strrev(substr(strrev($key), 5))."_state"] = $obj->{"current_".strrev(substr(strrev($class), 1))}["state"];
                        $this->current_proposal[strrev(substr(strrev($key), 5))."_zip"] = $obj->{"current_".strrev(substr(strrev($class), 1))}["zip"];
                        $this->current_proposal[strrev(substr(strrev($key), 5))."_country"] = $obj->{"current_".strrev(substr(strrev($class), 1))}["country"];
                        $this->current_proposal[strrev(substr(strrev($key), 5))."_tax_lock"] = $obj->{"current_".strrev(substr(strrev($class), 1))}["tax_lock"];
                        $this->current_proposal[strrev(substr(strrev($key), 5))."_account_no"] = $obj->{"current_".strrev(substr(strrev($class), 1))}["account_no"];
					}

					unset($obj);
				}
			}

			//Import data
			if ($row['import_data'])
				$this->current_proposal['import_data'] = __unserialize(stripslashes($this->current_proposal['import_data']));

			//Check the install and design queue
			if ($this->current_proposal['design_queue']) {
				$result = $this->db->query("SELECT `timestamp` , `group_hash` , `received_by` , `received_on` as receive_time
											FROM `messages`
											WHERE `queue_hash` = '".$this->current_proposal['design_queue']."'");
				$this->current_proposal['design_queue'] = $this->db->fetch_assoc($result);
			}
			if ($this->current_proposal['work_order_rqst']) {
				$result = $this->db->query("SELECT `timestamp`
											FROM `work_orders`
											WHERE `proposal_hash` = '".$this->proposal_hash."' AND `order_complete` = 0");
				$this->current_proposal['work_order_rqst_timestamp'] = $this->db->fetch_assoc($result);
			}
			if ($this->current_proposal['pm_request_queue']) {
				$result = $this->db->query("SELECT `received_on` , `received_by`
											FROM `messages`
											WHERE `queue_hash` = '".$this->current_proposal['pm_request_queue']."'");
				$this->current_proposal['pm_request_timestamp'] = $this->db->fetch_assoc($result, 0, 'received_on');
				$this->current_proposal['pm_request_received_by'] = user_name($this->db->fetch_assoc($result, 0, 'received_by'));
			}

			$result = $this->db->query("SELECT COUNT(proposals.proposal_hash) AS Total
										FROM `proposals`
										LEFT JOIN `purchase_order` ON purchase_order.proposal_hash = proposals.proposal_hash
										LEFT JOIN `customer_invoice` ON customer_invoice.proposal_hash = proposals.proposal_hash AND customer_invoice.deleted = 0
										WHERE (purchase_order.proposal_hash = '".$this->proposal_hash."' AND purchase_order.deleted = 0) OR (customer_invoice.proposal_hash = '".$this->proposal_hash."' AND customer_invoice.deleted = 0)");
			$this->current_proposal['rm_lock'] = $this->db->result($result);

			//Invoiced
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `line_items`
										WHERE `proposal_hash` = '".$this->proposal_hash."' AND `invoice_hash` != ''");
			$this->current_proposal['invoiced'] = $this->db->result($result, 0, 'Total');

			return true;
		}

		return false;
	}
	/**
	 * Fetch the employees full name given the proposal_hash and employee's role in proposal -
	 * Acceptable values are: sales, designer, sales_coord, or proj_mngr
	 *
	 * @param string $proposal_hash
	 * @param string $role - sales, designer, sales_coord, or proj_mngr
	 * @return string
	 */
	function fetch_full_name($proposal_hash, $role) {
		if (!$proposal_hash)
			return;
		// Check if role is valid type
		if ($role != 'sales' || $role != 'designer' || $role != 'sales_coord' || $role != 'proj_mngr')
			return;

		$result = $this->db->query("SELECT users.full_name
									FROM users
									WHERE id_hash =
									(SELECT proposals.".$role."_hash
										FROM proposals
										WHERE proposal_hash = '$proposal_hash' and deleted = 0)");
		$row = $this->db->fetch_assoc($result);

		return $row['full_name'];
	}
	/**
	 * Fetch the customer contact name given the proposal_hash.
	 *
	 * @param string
	 */
	function fetch_customer_contact_name($proposal_hash) {
		if (!$proposal_hash)
			return;

		$result = $this->db->query("SELECT t2.contact_name AS c
									FROM proposals t1
									LEFT JOIN customer_contacts t2 ON t2.contact_hash = t1.customer_contact
									WHERE t1.proposal_hash = '$proposal_hash' and t1.deleted = 0");
		return $this->db->result($result, 0, 'c');
	}

	function load_import_catalogs($proposal_hash) {
		$result = $this->db->query("SELECT proposals.import_data
						  			FROM `proposals`
						  			WHERE `proposal_hash` = '$proposal_hash' and deleted = 0");
		$row = $this->db->fetch_assoc($result);
		$data = __unserialize(stripslashes($row['import_data']));

		$data = $data['_c']['CATALOGS']['_c']['CATALOG'];
		if (!$data[0])
			$data = array($data);

		for ($i = 0; $i < count($data); $i++)
			$catalog[$data[$i]['_a']['code']] = $data[$i];

		unset($data);

		return $catalog;
	}

	function fetch_panel_type($proposal_hash, $panel_id) {
		$result = $this->db->query("SELECT proposals.import_data
						  			FROM `proposals`
						  			WHERE `proposal_hash` = '$proposal_hash'");
		$row = $this->db->fetch_assoc($result);
		$import_data = __unserialize(stripslashes($row['import_data']));

		if (is_array($import_data['PANEL_TYPE'])) {
			for ($i = 0; $i < count($import_data['PANEL_TYPE']); $i++) {
				$a .= "-".$import_data['PANEL_TYPE'][$i]['NAME']."-";
				if ($import_data['PANEL_TYPE'][$i]['NAME'] == $panel_id) {
					$this->panel_type =& $import_data['PANEL_TYPE'][$i];
					break;
				}
			}
		}

		return;
	}

	function drop_panel($proposal_hash, $panel_id) {
		if (!$panel_id)
			return;

		$result = $this->db->query("SELECT proposals.import_data
						  			FROM `proposals`
						  			WHERE `proposal_hash` = '$proposal_hash'");
		$row = $this->db->fetch_assoc($result);
		$import_data = __unserialize(stripslashes($row['import_data']));

		if ($import_data['PANEL_TYPE']) {
			for ($i = 0; $i < count($import_data['PANEL_TYPE']); $i++) {
				if ($import_data['PANEL_TYPE'][$i]['NAME'] == $panel_id) {
					unset($import_data['PANEL_TYPE'][$i]);
					break;
				}
			}
			$import_data['PANEL_TYPE'] = array_values($import_data['PANEL_TYPE']);
			if (count($import_data['PANEL_TYPE']) == 0)
                unset($import_data['PANEL_TYPE']);

            if (is_array($import_data)) {
				$data = addslashes(serialize($import_data));
				$this->db->query("UPDATE `proposals`
								  SET `import_data` = '$data'
								  WHERE `proposal_hash` = '$proposal_hash'");
            }
		}
	}

	function change_bill_to() {

		$this->popup_id = $this->ajax_vars['popup_id'];
		if ( $this->ajax_vars['proposal_hash'] ) {

			if ( ! $this->fetch_master_record($this->ajax_vars['proposal_hash']) )
				return $this->__trigger_error("System error when attempting to fetch proposal record. Please reload window and try again. <!-- Tried fetching proposal [ {$this->ajax_vars['proposal_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
		}

		//$tmp_qtype = $this->ajax_vars['tmp_qtype'];
		$customer_hash = ( $this->ajax_vars['customer_hash'] ? $this->ajax_vars['customer_hash'] : $this->ajax_vars['_hash'] );
		$customers = new customers($this->current_hash);

		if ( $customers->fetch_master_record($customer_hash) ) {

			if ( $customers->total_locations > 0 ) {

				$_total = $customers->fetch_all_locations(
					0,
					"location_name",
					"ASC"
				);
				
				$loc_tbl .= "
				<div class=\"function_menu_item\" title=\"" . htmlentities($customers->current_customer['street'], ENT_QUOTES) . "\n" . htmlentities($customers->current_customer['city'], ENT_QUOTES) . ", {$customers->current_customer['state']} {$customers->current_customer['zip']}\">" .
					$this->form->radio(
						"name=bill_to_change",
						"value={$customers->customer_hash}"
					) . "&nbsp;" .
					( strlen($customers->current_customer['customer_name']) > 25 ?
						stripslashes( substr($customers->current_customer['customer_name'], 0, 23) ) . "..." : stripslashes($customers->current_customer['customer_name'])
					) . " <span style=\"font-style:italic;\">(Primary)</span>
				</div>";

				for ( $i = 0; $i < count($customers->customer_locations); $i++ ) {

					$loc_tbl .= "
					<div class=\"function_menu_item\" title=\"{$customers->customer_locations[$i]['location_street']}\n{$customers->customer_locations[$i]['location_city']}, {$customers->customer_locations[$i]['location_state']} {$customers->customer_locations[$i]['location_zip']}\">" .
                        $this->form->radio(
                            "name=bill_to_change",
                            "value={$customers->customer_locations[$i]['location_hash']}"
                        ) . "&nbsp;" .
                        ( strlen($customers->customer_locations[$i]['location_name']) > 25 ?
							stripslashes( substr($customers->customer_locations[$i]['location_name'], 0, 23) ) . "..." : stripslashes($customers->customer_locations[$i]['location_name'])
						) . "
					</div>";
				}
			} else
				$loc_tbl = "
				<div style=\"padding:5px;color:#000000;\">
					This customer has no additional locations. To add a billing or shipping location under this customer,
					click edit, then add a location under the locations tab.
				</div>";
		} else
			return $this->__trigger_error("System error when attempting to fetch customer record. Please reload window and try again. <!-- Tried fetching customer [ {$this->current_proposal['customer_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		$_total++;

		$tbl = "
		<div id=\"".($tmp_qtype == 'propose_to_' ? "" : "bill_to_")."changer{$this->popup_id}\" class=\"function_menu\" style=\"width:275px;display:block;\">
			<div style=\"float:right;padding:3px\">
				<a href=\"javascript:void(0);\" onClick=\"toggle_display('".($tmp_qtype == 'propose_to_' ? "" : "bill_to_")."changer".$po_hash."', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
			</div>
			<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">
				Select ".($tmp_qtype == 'propose_to' ? "Propose To " : "Customer Billing ")."Address:
			</div>
			<table cellpadding=\"0\" cellspacing=\"1\" style=\"width:100%;\">
				<tr>
					<td style=\"width:40%;background-color:#ffffff;\">
						<div style=\"color:#000000;height:" . ( $_total < 3 ? ( $_total * 29 ) : "86" ) . "px;margin-right:1px;overflow:auto;\" >
							$loc_tbl
						</div>
					</td>
				</tr>
				<tr>
					<td colspan=\"2\" style=\"text-align:right;padding:5px;background-color:#bbc7ce;\">" .
					$this->form->button(
						"value=Go",
                       	"onclick=window.setTimeout(function(){toggle_display('" . ( $tmp_qtype == 'propose_to_' ? "" : "bill_to_" ) . "changer{$this->popup_id}', 'none');}, 100); submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_change_bill_to', 'tmp_qtype=$tmp_qtype');"
					) . "
					</td>
				</tr>
			</table>
		</div>";

		$this->content['html'][ ( $tmp_qtype ? "propose_to_" : "bill_to_" ) . "changer_holder{$this->popup_id}"] = $tbl;
		return;
	}

	function doit_change_bill_to() {

		$bill_to_hash = $_POST['bill_to_change'];
		$customer_hash = $_POST['customer_hash'];
		$proposal_hash = $_POST['proposal_hash'];

		if ( $proposal_hash ) { # As opposed to a new proposal in the process of creation

			if ( ! $this->db->query("UPDATE proposals t1
									 SET
										 t1.timestamp = UNIX_TIMESTAMP(),
										 t1.last_change = '{$this->current_hash}',
										 t1.bill_to_hash = " . ( $customer_hash && $customer_hash == $bill_to_hash ? "NULL" : "'$bill_to_hash'" ) . "
									 WHERE `proposal_hash` = '$proposal_hash'")
			) {

				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, 2);
			}
		}

		$this->content['value']['bill_to_hash'] = ( $customer_hash && $customer_hash == $bill_to_hash ? '' : $bill_to_hash );
		$this->content['action'] = 'continue';
		$this->content['jscript_action'] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'innerHTML_customer', 'qtype=bill_to', 'id=$customer_hash', 'bill_to_hash=$bill_to_hash');";
		return;
	}

	//innerHTML_customer($this->lines->current_line['vendor_hash'], 'vendor', 'vendor_product_holder', $this->lines->current_line['product_hash'])
	function innerHTML_customer() {

		if ( ! func_get_args() ) {

			$id = $this->ajax_vars['id'];
			$orig_qtype = $qtype = $this->ajax_vars['qtype'];
			$selected_value = $this->ajax_vars["contact_hash"];
			$jscript_action = $this->ajax_vars['jscript_action'];
			$bill_to_hash = $this->ajax_vars['bill_to_hash'];
		} else {

			$args = func_get_args();

			$id = $args[0];
			$orig_qtype = $qtype = $args[1];
			$request = $args[2];
			$selected_value = $args[3];
			$readonly = $args[4];
			$bill_to_hash = $args[5];
			$no_update = $args[6];
			$disabled = $args[7];
		}

		if ( $qtype == 'install_addr' || $qtype == 'ship_to' || $qtype == 'po_ship_to' ) {

			$tmp_qtype = $qtype;
			$tmp_id = $id;

			list($qtype_call, $id, $location_hash) = explode("|", $id);
			$holder =
			( $qtype == 'install_addr' ?
    			'install_addr_holder' :
    			( $qtype == 'po_ship_to' ?
        			'po_ship_to_info' : 'ship_to_holder'
    			)
    		);
			$qtype = strrev(substr(strrev($qtype_call), 1));
		}

		if ( $qtype == 'customer' || $qtype == 'propose_to' || $qtype == 'bill_to' ) {

			$qtype_call = 'customers';
			if ( $qtype == 'propose_to' && preg_match('/\|/', $id) ) {

				list($c, $id, $location_hash) = explode("|", $id);
				$propose_to_location = $location_hash;
			}

			$obj = new customers($this->current_hash);
			if ( $location_hash ) {

				if ( $obj->fetch_location_record($location_hash) )
    				$details =& $obj->current_location;

        		$pre = 'location_';

			} else {

				if ( $obj->fetch_master_record($id) ) {

	                if ( $qtype == 'bill_to' && $bill_to_hash != $id ) { # If changing billto to location

	                    $obj->fetch_location_record($bill_to_hash);
	                    $details =& $obj->current_location;
	                    $pre = "location_";
	                } else
	                    $details =& $obj->current_customer;

	                if ( ! $args && $qtype == 'customer' && ! $tmp_qtype )
	                    $this->content['jscript'] = "if(!\$F('propose_to')){selectItem('propose_to={$details['customer_name']}', 'propose_to_hash=$id');agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'innerHTML_customer', 'qtype=propose_to', 'id=$id');}";
				}
			}

			if ( ! $holder ) {

				if ( $qtype == 'propose_to' ) {

					$tmp_qtype = 'propose_to';
					$qtype = 'customer';
					$holder = 'propose_to_details_holder';
				} else {

					$holder = 'customer_details_holder';
					if ( $qtype != 'bill_to' )
						$contact_holder = 'customer_contact_holder';
				}
			}
			if ( $qtype == 'customer' )
				$this->content['html']['install_location_add'] = "<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_customer', 'tab_to=tcontent4', 'customer_hash=$id', 'parent_win_open=add_from_proposal', 'jscript_action=void(0);')\"><img src=\"images/plus.gif\" title=\"Add a new location under {$obj->current_customer['customer_name']}\" border=\"0\" /></a>";

		} elseif ( $qtype == 'vendor' || $qtype == 'direct_vendor' ) {

			$qtype_call = 'vendors';
			$obj = new vendors($this->current_hash);
			if ( $location_hash ) {

				if ( $obj->fetch_location_record($location_hash) )
                    $details =& $obj->current_location;

				$pre = 'location_';

			} else {

				if ( $obj->fetch_master_record($id) )
                    $details =& $obj->current_vendor;
			}
			if ( $qtype == 'direct_vendor' ) {

				$qtype = 'vendor';
				$holder = 'direct_vendor_details_holder';
			}

		} elseif ( $qtype == 'arch' ) {

			$qtype_call = 'arch_design';
			$obj = new arch_design($this->current_hash);
			if ( $obj->fetch_master_record($id) )
                $details =& $obj->current_arch;

			if ( ! $holder ) {

				$holder = 'arch_details_holder';
				$contact_holder = 'arch_contact_holder';
			}

		} elseif ( $qtype == 'item_vendor' || $qtype == 'func_vendor' || $qtype == 'item_po' ) {

			$func_vendor = $this->ajax_vars['func_vendor'];
			$result = $this->db->query("SELECT t1.product_hash , t1.product_name , t1.catalog_code
										FROM vendor_products t1
										WHERE ( t1.vendor_hash = '$id' OR ISNULL(t1.vendor_hash) ) AND t1.product_active = 1 AND t1.deleted = 0
										ORDER BY t1.product_name ASC");
			while ( $row = $this->db->fetch_assoc($result) ) {

				$product_hash[] = $row['product_hash'];
				$product_name[] = $row['product_name'] . ( $row['catalog_code'] ? "&nbsp;&nbsp;&nbsp;&nbsp;[{$row['catalog_code']}]" : NULL );
			}

			$select = $this->form->select(
                ( $qtype == 'item_vendor' ? "item_product_hash" : "func_product_hash" ),
                $product_name,
                $selected_value,
                $product_hash,
                "TABINDEX=3",
                "style=width:195px;",
                ( $qtype == 'item_vendor' ?
                    ( $readonly ?
                        "disabled" :
                        ( $qtype != 'item_po' ?
                            "onChange=" .
                            ( $no_update ?
                                "\$('edit_change').value=1" : "submit_form(\$('$qtype').form, 'proposals', 'exec_post', 'refresh_form', 'action=fill_discounts');"
                            ) : NULL
                        )
                    ) : NULL
                ),
                ( ( $this->lines->item_hash && ! $this->p->ck(get_class($this), 'E', 'item_details') ) || $disabled ? "disabled" : NULL ),
                ( $func_vendor ? "blank=1" : NULL )
            );
			if ( $args && $request )
				return $select;

		    if ( $jscript_action )
				$this->content['jscript'] = $jscript_action;

			$this->content['html'][ ( $qtype == 'item_vendor' ? 'vendor_product_holder' : 'func_product_holder' ) ] = $select;
			return;
		}

		$this->content['html'][$holder] =
		( $qtype == 'bill_to' && $details["{$pre}name"] ?
			stripslashes($details["{$pre}name"]) . "<br />" : NULL
		) .
		( $details["{$pre}street"] ?
			nl2br( stripslashes($details["{$pre}street"]) ) . "<br />" : NULL
		) .
		( $details["{$pre}city"] ?
            stripslashes($details["{$pre}city"]) .
            ( $details["{$pre}state"] || $details["{$pre}zip"] ?
                ", &nbsp;" : NULL
            ) : NULL
        ) .
        ( $details["{$pre}state"] ?
            stripslashes($details["{$pre}state"]) : NULL
        ) .
        ( $details["{$pre}zip"] ?
            "&nbsp;{$details["{$pre}zip"]}" : NULL
        ) . "
        <div>" .
		( $this->p->ck($qtype_call, 'E') ?
			"[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('$qtype_call', 'sf_loadcontent', 'show_popup_window', 'edit_" .
    		( $qtype == 'bill_to' ?
        		'customer' : $qtype
    		) . "', 'parent_win_open', 'parent_win={$this->popup_id}', 'popup_id=sub_win', '" .
    		( $qtype == 'arch' ?
        		'arch_hash' :
        		( $qtype == 'bill_to' ?
            		'customer' : $qtype
        		) . "_hash"
        	) . "=$id', 'tab_to=" .
        	( $qtype == 'bill_to' ?
            	"tcontent4" : "tcontent1"
        	) . "', " .
        	( $qtype == 'bill_to' ?
            	"'location_action=addnew', 'location_hash=$bill_to_hash'" : "''"
        	) . ", 'jscript_action=void(0);', 'onclose_action=agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'innerHTML_customer\', \'id=" .
        	( $tmp_id ?
            	$tmp_id : $id
            ) . "\', \'qtype=" .
            ( $tmp_qtype ?
               	$tmp_qtype : $qtype
            ) . "\', " .
            ( $qtype == 'bill_to' ?
                "\'bill_to_hash=$bill_to_hash\'" : "\'contact_hash='+($('{$qtype}_contact') ? \$F('{$qtype}_contact') : '')+'\'"
            ) . ");');\" title=\"Edit this information\">edit</a></small>]&nbsp;&nbsp;" : NULL
        ) .
		( ! $tmp_qtype && ( $qtype == 'customer' || $qtype == 'bill_to' ) && $this->current_proposal['status'] != 2 && $obj->total_locations > 0 ?
			"<span id=\"bill_to_changer_holder{$this->popup_id}\"></span>[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'change_bill_to', 'popup_id={$this->popup_id}', 'proposal_hash={$this->proposal_hash}', '_hash=$id');\">change bill to</a></small>]" : NULL
		) . "
		</div>" .
        ( $tmp_qtype == 'po_ship_to' ?
            "&nbsp;&nbsp;
            <span id=\"po_change_save\" style=\"margin-left:35px;\">" .
            $this->form->button(
                "value=Save",
                "onClick=\$('po_change_save').innerHTML='Saving...';submit_form(\$('form_placeholder').form, 'purchase_order', 'exec_post', 'refresh_form', 'method=update_shipto');"
            ) . "
            </span>" : NULL
        );

		if ( $args && $request && $request == $holder ) {

			$return = $this->content['html'][$holder];
			unset($this->content['html'][$holder]);

			return $return;
		}

		if ( ! $args ) {

			if ( $tmp_qtype == 'install_addr' )
				$this->load_site_details($tmp_id, 'I');
		}

		if ( ( $qtype != 'bill_to' && $obj->total_contacts ) || ( $qtype == 'customer' && ! $obj->total_contacts ) ) {

            if ( $qtype == 'customer' ) { # Contact hash linked to propose to entity

            	$propose_to_hash = $_POST['propose_to_hash'];
                if ( ! $propose_to_hash )
                    $propose_to_hash = $_POST['id'];

                if ( preg_match('/\|/', $propose_to_hash) )
                    list($c, $id, $propose_to_location) = explode("|", $propose_to_hash);

	            $obj = new customers($this->current_hash);
	            $obj->fetch_master_record($id);
            }

			$obj->fetch_contacts( array(
				'start_from'			=>	0,
				'end'					=>	$obj->total_contacts,
				'propose_to_location'	=>	$propose_to_location
			) );

			if ( $qtype == 'arch' )
				$c_details =& $obj->arch_contacts;
			else
				$c_details =& $obj->customer_contacts;

			for ( $i = 0; $i < count($c_details); $i++ ) {

				$select_in[] = $c_details[$i]['contact_hash'];
				$select_out[] = $c_details[$i]['contact_name'] .
				( $c_details[$i]['contact_title'] ?
    				", &nbsp;" .
    				( strlen($c_details[$i]['contact_title']) > 35 ?
        				substr($c_details[$i]['contact_title'], 0, 35) . "..." : $c_details[$i]['contact_title']
        			) : NULL
        		);
			}
			if ( ! $contact_holder )
                $contact_holder = 'customer_contact_holder';
		}

		if ( $tmp_qtype != 'install_addr' && $tmp_qtype != 'ship_to' && $qtype != 'bill_to' ) {

			if ( $this->p->ck($qtype_call, 'A', 'contacts') ) {

				$select_in[] = "ADDNEW";
				$select_out[] = "Add New";
			}

			if ( $contact_holder && is_array($select_out) )
				$this->content['html'][$contact_holder] = $this->form->select(
    				"{$qtype}_contact",
    				$select_out,
    				$selected_value,
    				$select_in,
    				"style=width:200px;",
    				( $this->p->ck($qtype_call, 'A', 'contacts') ?
        				"onChange=if(this.options[this.selectedIndex].value=='ADDNEW'){agent.call('{$qtype_call}', 'sf_loadcontent', 'show_popup_window', 'edit_{$qtype}', 'parent_win_open=add_from_proposal', 'tab_to=" . ( $qtype == 'arch' ? "tcontent2" : "tcontent3" ) . "', 'parent_win={$this->popup_id}', 'popup_id=sub_win', '" . ( $qtype == 'arch' ? 'arch_hash' : "{$qtype}_hash" ) . "=$id', 'jscript_action=void(0);', 'onclose_action=agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'innerHTML_customer\', \'id=" . ( $tmp_id ? $tmp_id : $id ) . "\', \'qtype=" . ( $tmp_qtype ? $tmp_qtype : $qtype ) . "\', \'contact_hash='+($('{$qtype}_contact') ? \$F('{$qtype}_contact') : '')+'\');');}else{agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'innerHTML_customer_contact', 'qtype={$qtype}', 'contact_hash='+this.options[this.selectedIndex].value);" . ( $this->proposal_hash ? "auto_save();" : NULL ) . "}" : NULL
    				),
    				( $this->proposal_hash && ! $this->p->ck(get_class($this), 'E', 'project') ? "disabled" : NULL )
    			);
		}

		if ( $args && $request && $request == $contact_holder ) {

			$return = $this->content['html'][$contact_holder];
			unset($this->content['html'][$contact_holder]);

			return $return;
		}
		if ( $selected_value )
			$this->innerHTML_customer_contact();

		return;
	}

	function innerHTML_customer_contact($propose_to_location) {
		if (!func_get_args()) {
			$contact_hash = $this->ajax_vars['contact_hash'];
			$qtype = $this->ajax_vars['qtype'];
		} else {
			$args = func_get_args();
			$contact_hash = $args[0];
			$qtype = $args[1];
			$request = $args[2];
		}

		if ($qtype == 'customer') {
			$obj = new customers($this->current_hash);
			$obj->fetch_contact_record($contact_hash);
			$details =& $obj->current_contact;
			$pre = "contact_";

			$holder = 'customer_contact_details_holder';

		} elseif ($qtype == 'arch') {
			$obj = new arch_design($this->current_hash);
			$obj->fetch_contact_record($contact_hash);
			$details =& $obj->current_contact;
			$pre = "contact_";

			$holder = 'arch_contact_details_holder';
		}
		$this->content['html'][$holder] = ($details[$pre.'phone1'] ?
			$details[$pre.'phone1']." (Phone)<br />" : NULL).($details[$pre.'phone2'] ?
				$details[$pre.'phone2']." (Phone 2)<br />" : NULL).($details[$pre.'mobile'] ?
					$details[$pre.'mobile']." (Mobile)<br />" : NULL).($details[$pre.'fax'] ?
						$details[$pre.'fax']." (Fax)<br />" : NULL).($details[$pre.'email'] ?
							$details[$pre.'email'] : NULL)."&nbsp;";

		if ($args && $request == $holder) {
			$return = $this->content['html'][$holder];
			unset($this->content['html'][$holder]);
			return $return;
		}

		return;
	}

	function reorder_lines($proposal_hash=NULL, $punch=NULL) {

		if ( ! $this->proposal_hash )
			$proposal_hash = $proposal_hash;

	 	if ( ! $proposal_hash )
			return false;

		$result = $this->db->query("SELECT t1.item_hash, t1.group_hash
									FROM line_items t1
									WHERE t1.proposal_hash = '$proposal_hash' AND t1.punch = " . ( $punch ? 1 : 0 ) . "
									ORDER BY t1.line_no ASC");
		while ( $row = $this->db->fetch_assoc($result) )
			$array[] = $row;

		$count = count($array);
		$line_no = 0;
		$line_pos = array();

		for ( $i = 0; $i < $count; $i++ ) {

			if ( $array[$i]['group_hash'] && ! array_key_exists($array[$i]['item_hash'], $line_pos) ) {

				$line_pos[ $array[$i]['item_hash'] ] = ++$line_no;
				for ( $j = $i; $j < $count; $j++ ) {

					if ( $array[$j]['group_hash'] == $array[$i]['group_hash'] && ! array_key_exists($array[$j]['item_hash'], $line_pos) )
						$line_pos[ $array[$j]['item_hash'] ] = ++$line_no;
				}

			} elseif ( ! array_key_exists($array[$i]['item_hash'], $line_pos) )
				$line_pos[ $array[$i]['item_hash'] ] = ++$line_no;
		}

		if ( $line_pos ) {

			reset($line_pos);
			while ( list($item_hash, $no) = each($line_pos) ) {

				if ( ! $this->db->query("UPDATE line_items t1
										 SET t1.line_no = '$no'
										 WHERE t1.item_hash = '$item_hash'")
                ) {

                	$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                	return false;
                }
			}
		}

		return true;
	}

	function doit_line_group() {
		$btn = $_POST['actionBtn'];
		$method = $_POST['method'];
		$notag = $_POST['notag'];
		$proposal_hash = $_POST['proposal_hash'];
		$group_hash = $_POST['group_hash'];
		$jscript_action = $_POST['jscript_action'];
		$limit = $_POST['limit'];
		$sum = $_POST['sum'];
		$line_item = $_POST['line_item'];
		if ($punch = $_POST['punch'])
			$line_item = $_POST['punch_item'];

		$this->lines = new line_items($proposal_hash, $punch);
		if ($group_hash) {
			$valid = $this->lines->fetch_line_group_record($group_hash);

			if ($valid === true) {
				$edit = true;
				$action_delete = $_POST['delete'];
			} else
				unset($group_hash);
		}
		if ($sum) {
			$actual_lines = array();
			for ($i = 0; $i < count($line_item); $i++) {
				if ($line_item[$i]) {
					list($type, $id) = explode("|", urldecode($line_item[$i]));

					switch ($type) {
						case 'vendor':
						$result = $this->db->query("SELECT `item_hash`
													FROM `line_items`
													WHERE `proposal_hash` = '$proposal_hash' AND `vendor_hash` = '$id'");
						while ($row = $this->db->fetch_assoc($result))
							array_push($actual_lines, $row['item_hash']);

						break;

						case 'prod':
						$result = $this->db->query("SELECT `item_hash`
													FROM `line_items`
													WHERE `proposal_hash` = '$proposal_hash' AND `product_hash` = '$id'");
						while ($row = $this->db->fetch_assoc($result))
							array_push($actual_lines, $row['item_hash']);
						break;
					}
				}
			}
			$line_item = array_values($actual_lines);
			$line_item = array_unique($line_item);
		}
		if ($method == 'tag') {
			//If the group already exists, append these lines to the existing group
			$z = 0;
			for ($i = 0; $i < count($line_item); $i++) {
				if ($line_item[$i]) {
					$z++;
					$tag_item[] = $line_item[$i];
					$action = true;
				}
			}
			$this->lines->add_to_group($tag_item, $group_hash);

			unset($this->ajax_vars, $this->lines);
			$this->content['page_feedback'] = ($action ? 'Selected line items have been '.($group_hash ? 'added to' : 'removed from').' the group.' : 'You selected no line items. No changes have been made.');
			$this->content['action'] = ($notag ? 'close' : 'continue');
			if ($punch)
				$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$proposal_hash');");
			else
				$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', '".($sum ? "summarized" : "line_items")."', 'otf=1', 'proposal_hash=$proposal_hash', 'limit=".$limit."');");
			return;
		} else {
			if ($action_delete || $_POST['group_descr']) {
				if ($action_delete) {
					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM `line_items`
												WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '$group_hash' AND `punch` = ".($punch ? 0 : 1));
					if ($this->db->result($result) > 0)
						$reload = true;

					$this->db->query("DELETE FROM `line_groups`
									  WHERE `group_hash` = '$group_hash' AND `proposal_hash` = '$proposal_hash'");
					$this->db->query("UPDATE `line_items`
									  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `group_hash` = NULL
									  WHERE `proposal_hash` = '$proposal_hash' && `group_hash` = '$group_hash'");

					$this->reorder_lines($proposal_hash, $punch);
					unset($this->ajax_vars, $this->lines);
					$this->content['page_feedback'] = 'The group has been removed from this proposal.';
					$this->content['action'] = ($notag ? 'close' : 'continue');
					if ($punch)
						$this->content['jscript_action'] = "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$proposal_hash');";
					else
						$this->content['jscript_action'] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_items', 'otf=1', 'proposal_hash=$proposal_hash');";

					if ($reload)
						$this->content['jscript'][] = "window.setTimeout('".($punch ? "agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'line_items\', \'otf=1\', \'proposal_hash=$proposal_hash\');" : "agent.call(\'pm_module\', \'sf_loadcontent\', \'cf_loadcontent\', \'punch\', \'proposal_hash=$proposal_hash\');")."', 1000);";
					return;
				} else {
					$group_descr = $_POST['group_descr'];
					$group_items = $_POST['group_items'];

					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM `line_items`
												WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '$group_hash' AND `punch` = ".($punch ? 0 : 1));
					if ($this->db->result($result) > 0)
						$reload = true;

					$result = $this->db->query("SELECT `item_hash`
												FROM `line_items`
												WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '$group_hash' AND `punch` = ".($punch ? 1 : 0));
					while ($row = $this->db->fetch_assoc($result))
						$current_group_members[] = $row['item_hash'];

					$rm_from_group = @array_diff($current_group_members, is_array($group_items) ? $group_items : array());
					if (is_array($this->lines->current_group['group_items']) && is_array($group_items))
						$add_to_group = @array_diff($group_items, $this->lines->current_group['group_items']);
					else
						$add_to_group = $group_items;

					if ($group_hash) {
						if (count($rm_from_group)) {
							$rm_from_group = array_values($rm_from_group);
							$this->lines->add_to_group($rm_from_group);
						}
						if (count($add_to_group)) {
							$add_to_group = array_values($add_to_group);
							$this->lines->add_to_group($add_to_group, $group_hash);
						}
						$this->db->query("UPDATE `line_groups`
										  SET `group_descr` = '$group_descr'
										  WHERE `group_hash` = '$group_hash'");
						$this->content['page_feedback'] = 'The group has been updated.';
					} else {
						$group_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('line_groups', 'group_hash', $group_hash))
							$group_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$this->db->query("INSERT INTO `line_groups`
										  (`last_change` , `group_hash` , `proposal_hash` , `group_descr`)
										  VALUES ('".$this->current_hash."' , '$group_hash' , '$proposal_hash' , '$group_descr')");
						if (count($group_items))
							$this->lines->add_to_group($group_items, $group_hash);

						$this->content['page_feedback'] = 'The group has been added '.(count($group_items) ? 'and the selected lines have been added to the new group' : NULL).'.';
					}
				}
				unset($this->ajax_vars, $this->lines);
				$this->content['action'] = ($notag ? 'close' : 'continue');
				if ($punch)
					$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$proposal_hash');");
				else
					$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_items', 'otf=1', 'proposal_hash=$proposal_hash', 'limit=".$limit."');");

				if ($reload)
					$this->content['jscript'][] = "window.setTimeout('".($punch ? "agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'line_items\', \'otf=1\', \'proposal_hash=$proposal_hash\');" : "agent.call(\'pm_module\', \'sf_loadcontent\', \'cf_loadcontent\', \'punch\', \'proposal_hash=$proposal_hash\');")."', 1000);";

				return;
			} else {
				$this->content['error'] = 1;
				if (!$_POST['group_descr']) $this->content['form_return']['err']['err1'.$this->popup_id] = 1;
				$this->content['form_return']['feedback'] = "You left some required fields blank! Please check the indicated fields and try again.";
				return;
			}
		}
	}

	function doit_line_comment() {
		$btn = $_POST['actionBtn'];
		$proposal_hash = $_POST['proposal_hash'];
		$comment_hash = $_POST['comment_hash'];
		$jscript_action = $_POST['jscript_action'];
		$limit = $_POST['limit'];
		$punch = $_POST['punch'];

		$this->lines = new line_items($proposal_hash);
		if ($comment_hash) {
			$valid = $this->lines->fetch_line_comment_record($comment_hash);

			if ($valid === true) {
				$edit = true;
				$action_delete = $_POST['delete'];
			} else
				unset($comment_hash);
		}

		if ($action_delete || (($_POST['comment_action'] == '*' || $_POST['comment_action'] == 2 || ($_POST['comment_action'] == 1 && $_POST['comment_vendor'])) && $_POST['comments'])) {
			if ($action_delete) {
				$this->db->query("DELETE FROM `line_comments`
								  WHERE `comment_hash` = '$comment_hash' AND `proposal_hash` = '$proposal_hash'");

				unset($this->ajax_vars, $this->lines);
				$this->content['action'] = 'close';
				$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_items', 'otf=1', 'proposal_hash=$proposal_hash', 'limit=".$limit."');");
				return;
			} else {
				if ($_POST['comment_action'] == 1 && !$_POST['comment_vendor_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err1'.$this->popup_id] = 1;
					$this->content['form_return']['feedback'] = "We can't seem to find the vendor you entered below. To have this comment appear on all vendor POs, select the option below. To have this comment appear on only a single vendor PO, enter the vendor below.";
					return;
				}

				$comment_vendor_hash = $_POST['comment_vendor_hash'];
				$comment_action = $_POST['comment_action'];
				$comments = $_POST['comments'];

				if ($comment_action == '*')
					unset($comment_vendor_hash);

				if ($comment_hash)
					$this->db->query("UPDATE `line_comments`
									  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `vendor_hash` = '$comment_vendor_hash' , `comment_action` = '$comment_action' , `comments` = '$comments'
									  WHERE `comment_hash` = '$comment_hash'");
				else {
					$comment_hash = md5(global_classes::get_rand_id(32, "global_classes"));
					while (global_classes::key_exists('line_comments', 'comment_hash', $comment_hash))
						$comment_hash = md5(global_classes::get_rand_id(32, "global_classes"));

					$this->db->query("INSERT INTO `line_comments`
									  (`timestamp` , `last_change` , `comment_hash` , `proposal_hash` , `punch` , `vendor_hash` , `comment_action` , `comments`)
									  VALUES (".time()." , '".$this->current_hash."' , '$comment_hash' , '$proposal_hash' , '$punch' , '$comment_vendor_hash' , '$comment_action' , '$comments')");
				}
			}
		} else {
			$this->content['error'] = 1;
			if ($_POST['comment_action'] == 1 && !$_POST['comment_vendor']) $this->content['form_return']['err']['err1'.$this->popup_id] = 1;
			if (!$_POST['comments']) $this->content['form_return']['err']['err2'.$this->popup_id] = 1;
			$this->content['form_return']['feedback'] = "Please check to make sure you have either included a vendor for this comment or selected an option below the vendor field. Also, please make sure you have added comments to continue.";
			return;
		}

		unset($this->ajax_vars, $this->lines);
		$this->content['action'] = 'close';
		if ($punch)
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$proposal_hash');");
		else
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_items', 'otf=1', 'proposal_hash=$proposal_hash', 'limit=".$limit."');");
	}

	function doit_import_export() {

		set_time_limit(0);

		$proposal_hash = $_POST['proposal_hash'];
		$comment_hash = $_POST['comment_hash'];
		$jscript_action = $_POST['jscript_action'];
		$method = $_POST['method'];
		$punch = $_POST['punch'];

		$import_from = $_POST['import_from'];
		$proposal_hash = $_POST['proposal_hash'];
		$file_name = rawurldecode($_POST['file_name']);
		$file_type = $_POST['file_type'];
		$file_size = $_POST['file_size'];
		$file_error = $_POST['file_error'];

		$this->db->query("UPDATE user_data t1
						  SET t1.timestamp = UNIX_TIMESTAMP(), t1.key = 'proposal_import_pref', t1.value = '$import_from'
						  WHERE t1.id_hash = '{$this->current_hash}' AND t1.key = 'proposal_import_pref'");
		if ( ! $this->db->affected_rows() )
			$this->db->query("INSERT INTO user_data
							  VALUES ( NULL, UNIX_TIMESTAMP(), '{$this->current_hash}', 'proposal_import_pref', '$import_from' )");

		if ( $_POST['finish'] == 1 ) {

			if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			$import_groups = $_POST['import_groups'];
			$import_lines = $_POST['import_lines'];
			$replace_lines = $_POST['replace_lines'];

			if ( $method == 'proposal' ) {

				$import_pricing = $_POST['import_pricing'];

				if ( ! $this->fetch_master_record($proposal_hash) )
                    return $this->__trigger_error("System error encountered during proposal lookup. Unable to fetch proposal record for item import.", E_USER_ERROR, __FILE__, __LINE__, 1);

				$lines = new line_items($proposal_hash, $punch);

				for ( $i = 0; $i < count($replace_lines); $i++ ) { # Delete indicated lines

					$lines->fetch_line_item_record($replace_lines[$i]);
					$panel_id = $lines->current_line['panel_id'];

					if ( ! $lines->current_line['status'] && ! $lines->current_line['invoice_hash'] ) {

						if ( ! $this->db->query("DELETE t1.*, t2.*
												 FROM line_items t1
												 LEFT JOIN tax_collected t2 ON t2.item_hash = t1.item_hash
												 WHERE t1.item_hash = '{$replace_lines[$i]}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
					}

					unset($lines->current_line, $lines->item_hash);
					if ( $panel_id )
						$drop_panel[] = $panel_id;
				}

				$lines = new line_items($proposal_hash, $punch);
				if ( ! $lines->total ) {

					if ( ! $this->db->query("UPDATE proposals
        									 SET import_data = ''
        									 WHERE proposal_hash = '$proposal_hash'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
				}
				else { # Trac #1251

					$r = $this->db->query("SELECT line_no
										   FROM line_items
										   WHERE proposal_hash = '$proposal_hash' AND punch = '" . ( $punch ? '1' : '0' ) . "'
										   ORDER BY line_no DESC
										   LIMIT 1");
					$line_no_inc = $this->db->result($r, 0, 'line_no');
				}

				$proposal_import_hash = $_POST['proposal_import_hash'];

				$tmp_p = new proposals($this->current_hash);
				if ( ! $tmp_p->fetch_master_record($proposal_import_hash) )
                    return $this->__trigger_error("System error encountered during proposal lookup. Unable to lookup proposal for import.", E_USER_ERROR, __FILE__, __LINE__, 1);

				$import_from = $tmp_p->current_proposal['import_from'];
				$tmp_l = new line_items($tmp_p->proposal_hash);

				for ( $i = 0; $i < count($import_groups); $i++ ) {

					unset($group_hash);
					if ( $import_groups[$i] ) {

						$group[] = $import_groups[$i];
						$tmp_l->fetch_line_group_record($import_groups[$i]);

						$r = $this->db->query("SELECT group_hash
											   FROM line_groups
											   WHERE proposal_hash = '$proposal_hash' AND group_descr = '" . addslashes($tmp_l->current_group['group_descr']) . "'");
						$group_hash = $this->db->result($r, 0, 'group_hash');
						
						if ( ! $group_hash ) {
						
							$group_hash = rand_hash('line_groups', 'group_hash');
							if ( ! $this->db->query("INSERT INTO line_groups
									VALUES
									(
									NULL,
									UNIX_TIMESTAMP(),
									'{$this->current_hash}',
									'$group_hash' ,
									'$proposal_hash',
									'" . addslashes($tmp_l->current_group['group_descr']) . "'
							)")
							) {
						
							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
						}
						}

					}
				}

				for ( $i = 0; $i < count($import_lines); $i++ ) {

					if ( $import_lines[$i] ) {

						if ( ! $tmp_l->fetch_line_item_record($import_lines[$i]) )
    						return $this->__trigger_error("System error during line item lookup. Unable to fetch line item for item import.", E_USER_ERROR, __FILE__, __LINE__, 1);

						$new_item['vendor_hash'] = $tmp_l->current_line['vendor_hash'];
						$new_item['product_hash'] = $tmp_l->current_line['product_hash'];
						$new_item['ship_to_hash'] = $this->current_proposal['ship_to_hash'];

						$new_item['qty'] = $tmp_l->current_line['qty'];
						$num_pkg = $item_data['DIMENSIONS']['NUM_PKG'];

						$new_item['item_no'] = addslashes($tmp_l->current_line['item_no']);
						$new_item['item_descr'] = addslashes($tmp_l->current_line['item_descr']);

						if ( $punch )
							$new_item['punch'] = 1;

						$new_item['import_complete'] = $tmp_l->current_line['import_complete'];
						$new_item['special'] = $tmp_l->current_line['special'];
						if ( $tmp_l->current_line['panel_id'] )
							$new_item['panel_id'] = $panel_type[] = $tmp_l->current_line['panel_id'];

						$new_item['list'] = $tmp_l->current_line['list'];

						unset($_POST['discount1'], $_POST['discount2'], $_POST['discount3'], $_POST['discount4'], $_POST['discount5'], $_POST['list_discount'], $_POST['gp_margin']); # Trac #538

						if ( $import_pricing ) {

                            $new_item['discount1'] = $tmp_l->current_line['discount1'];
                            $new_item['discount2'] = $tmp_l->current_line['discount2'];
                            $new_item['discount3'] = $tmp_l->current_line['discount3'];
                            $new_item['discount4'] = $tmp_l->current_line['discount4'];
                            $new_item['discount5'] = $tmp_l->current_line['discount5'];

                            $new_item['cost'] = $tmp_l->current_line['cost'];
                            $new_item['sell'] = $tmp_l->current_line['sell'];
                            $new_item['gp_type'] = $tmp_l->current_line['gp_type'];
                            $new_item['gp_type'] = $tmp_l->current_line['gp_type'];
						} else {

                            $discounting = $this->fill_discounts( array(
                                'item_vendor_hash'      =>  $new_item['vendor_hash'],
                                'item_product_hash'     =>  $new_item['product_hash'],
                                'proposal_hash'         =>  $this->proposal_hash,
                                'item_no'               =>  $new_item['item_no']
                            ) );

							$new_item['discount_hash'] = $discounting['discount_hash'];
							$new_item['discount_item_hash'] = $discounting['discount_item_hash'];

							$new_item['discount1'] = bcmul($new_item['discount1'], .01, 4);
							$new_item['discount2'] = bcmul($new_item['discount2'], .01, 4);
							$new_item['discount3'] = bcmul($new_item['discount3'], .01, 4);
							$new_item['discount4'] = bcmul($new_item['discount4'], .01, 4);
							$new_item['discount5'] = bcmul($new_item['discount5'], .01, 4);

							if ( $discounting['list_discount'] ) {

								$list_discount = $discounting['list_discount'];
								$new_item['gp_type'] = 'L';
							} elseif ( $discounting['gp_margin'] ) {

								$gp_margin = $discounting['gp_margin'];
								$new_item['gp_type'] = 'G';
							}

                            $values = $this->validate_line_item( array(
                                'local'         =>  1,
                                'qty'           =>  $new_item['qty'],
                                'list'          =>  $new_item['list'],
                                'discount1'     =>  $new_item['discount1'],
                                'discount2'     =>  $new_item['discount2'],
                                'discount3'     =>  $new_item['discount3'],
                                'discount4'     =>  $new_item['discount4'],
                                'discount5'     =>  $new_item['discount5'],
                                'list_discount' =>  $list_discount,
                                'gp_margin'     =>  $gp_margin
                            ) );

							$new_item['cost'] = $values['cost'];
							$new_item['sell'] = $values['sell'];
						}

						$new_item['item_tag1'] = addslashes($tmp_l->current_line['item_tag1']);
						$new_item['item_tag2'] = addslashes($tmp_l->current_line['item_tag2']);
						$new_item['item_tag3'] = addslashes($tmp_l->current_line['item_tag3']);

						$new_item['line_no'] = ++$line_no_inc; # Trac #1251

						$item_hash = rand_hash('item_hash_index', 'item_hash');
						if ( $tmp_l->current_line['group_hash'] && in_array($tmp_l->current_line['group_hash'], $group) )
							$add_to_group[ $tmp_l->current_line['group_hash'] ][] = $item_hash;


						if ( ! $this->db->query("INSERT INTO item_hash_index
        										 VALUES
        										 (
            										 '$item_hash'
            									 )")
                        ) {

                            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$item_added++;
						$new_item = $this->db->prepare_query($new_item, "line_items", 'INSERT');
						if ( ! $this->db->query("INSERT INTO line_items
												  (
		    										  timestamp,
		    										  last_change,
		    										  item_hash,
		    										  proposal_hash,
		    										  " . implode(", ", array_keys($new_item) ) . ",
		    										  import_data
		    									  )
												  VALUES
												  (
		    										  UNIX_TIMESTAMP(),
		    										  '{$this->current_hash}',
		    										  '$item_hash',
		    										  '$proposal_hash',
		    										  " . implode(", ", array_values($new_item) ) . ",
		    										  '" . addslashes($tmp_l->current_line['import_data']) . "')"
		    									  )
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$lines->item_tax($item_hash);

						# Proposal Status Tract #1443
						if ( $punch ) {

							if ( ! $this->db->query("UPDATE proposals
													 SET proposal_status = 'PL', status_comment = 'Punchlist created as of " . date(DATE_FORMAT) . "'
													 WHERE proposal_hash = '{$proposal_hash}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
						}
					}
				}

				if ( is_array($tmp_p->current_proposal['import_data']['PANEL_TYPE']) && is_array($panel_type) ) {

					$panel_matrix = $tmp_p->current_proposal['import_data']['PANEL_TYPE'];
					$count = count($tmp_p->current_proposal['import_data']['PANEL_TYPE']);
					for ( $k = 0; $k < $count; $k++ ) {

						if ( ! in_array($panel_matrix[$k]['NAME'], $panel_type) )
							unset($tmp_p->current_proposal['import_data']['PANEL_TYPE'][$k]);
					}

					$panel_matrix = array_values($tmp_p->current_proposal['import_data']['PANEL_TYPE']);

					for ( $k = 0; $k < count($panel_matrix); $k++ ) {

						$new_panels[] = array(
    						"NAME"    => $panel_matrix[$k]['NAME'],
    						"EL"      => $k
						);
					}

					if ( is_array($this->current_proposal['import_data']['PANEL_TYPE']) ) {

						for ( $k = 0; $k < count($this->current_proposal['import_data']['PANEL_TYPE']); $k++ ) {

							$existing_panels_name[] = $this->current_proposal['import_data']['PANEL_TYPE'][$k]['NAME'];
							$existing_panels_el[] = $k;
						}
					}

					if ( ! is_array($existing_panels_name) )
						$no_previous_panels = true;

					for ( $k = 0; $k < count($new_panels); $k++ ) {

						if ( $no_previous_panels )
							$this->current_proposal['import_data']['PANEL_TYPE'][] = $panel_matrix[ $new_panels[$k]['EL'] ];
						else {

							if ( in_array($new_panels[$k]['NAME'], $existing_panels_name) )
								$this->current_proposal['import_data']['PANEL_TYPE'][ $existing_panels_el[array_search($new_panels[$k]['NAME'], $existing_panels_name)] ] = $panel_matrix[ $new_panels[$k]['EL'] ];
							else
								$this->current_proposal['import_data']['PANEL_TYPE'][] = $panel_matrix[ $new_panels[$k]['EL'] ];
						}
					}
				}

				if ( is_array($tmp_p->current_proposal['import_data']['SETUP']) ) {

					$import_data['SETUP'] = $tmp_p->current_proposal['import_data']['SETUP'];
					$import_data['SETUP']['IMPORT_DATE'] = time();
				}

				if ( $add_to_group ) {

					for ( $i = 0; $i < list($group_hash, $item_array_add) = each($add_to_group); $i++ ){
						$tmp_l->fetch_line_group_record($import_groups[$i]);
						$r = $this->db->query("SELECT group_hash
								FROM line_groups
								WHERE proposal_hash = '$proposal_hash' AND group_descr = '" . addslashes($tmp_l->current_group['group_descr']) . "'");
						$group_hash = $this->db->result($r, 0, 'group_hash');
						$lines->add_to_group($item_array_add, $group_hash);
					}
						
				} else
					$this->reorder_lines($this->proposal_hash);

			} else {

				try {

					$this->xmlPar = new xmlParser($file_name, $import_from, $proposal_hash);
				} catch ( Exception $e ) {

					return $this->__trigger_error($e->getMessage(), E_USER_ERROR, $e->getFile(), $e->getLine(), 1);
				}

				$this->xmlPar->xml2ary();

				if ( ! $this->fetch_master_record($proposal_hash) )
    				return $this->__trigger_error("System error encountered during proposal lookup. Unable to fetch proposal record for item import.", E_USER_ERROR, __FILE__, __LINE__, 1);

				$lines = new line_items($proposal_hash, $punch);

				for ( $i = 0; $i < count($replace_lines); $i++ ) {

					if ( $replace_lines[$i] )
						$replace_it[] = $replace_lines[$i];
				}
				if ( $replace_it ) {

					for ( $i = 0; $i < count($replace_it); $i++ ) {

						if ( $lines->fetch_line_item_record($replace_it[$i]) ) {

							$panel_id = $lines->current_line['panel_id'];
							if ( ! $lines->current_line['po_hash'] && ! $lines->current_line['invoice_hash'] ) {

								if ( ! $this->db->query("DELETE line_items.*, tax_collected.*
														 FROM line_items
														 LEFT JOIN tax_collected ON tax_collected.item_hash = line_items.item_hash
														 WHERE line_items.item_hash = '{$replace_it[$i]}'")
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                }
							}

							unset($lines->current_line, $lines->item_hash);
							if ( $panel_id )
								$drop_panel[] = $panel_id;
						}
					}
				}

				$lines = new line_items($proposal_hash, $punch);
				if ( ! $lines->total ) {

					if ( ! $this->db->query("UPDATE proposals
											 SET import_data = ''
											 WHERE proposal_hash = '$proposal_hash'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					$line_no_inc = 0;

				} else { # Get the line num of last item

					$r = $this->db->query("SELECT line_no
										   FROM line_items
										   WHERE proposal_hash = '$proposal_hash' AND punch = '" . ( $punch ? '1' : '0' ) . "'
										   ORDER BY line_no DESC
										   LIMIT 1");
					$line_no_inc = $this->db->result($r, 0, 'line_no');
				}

				$this->xmlPar->load_refs();
				for ( $i = 0; $i < count($this->xmlPar->project['ITEM']); $i++ ) {

					if ( trim($this->xmlPar->project['ITEM'][$i]['SPEC_GROUP']) ) {

						$group_data[ $this->xmlPar->project['ITEM'][$i]['SPEC_GROUP'] ][] =& $this->xmlPar->project['ITEM'][$i];
						$num_grps++;
					} else
						$group_data['DEALERCHOICE_DEFAULT'][] =& $this->xmlPar->project['ITEM'][$i];
				}

		        if ( defined('MULTI_CURRENCY') && MULTI_CURRENCY == 1 ) {

		            $sys = new system_config();
		            $multi_currency = 1;
		            $stored_currencies = array();
		        }

				$i = 0;
				while ( list($group_name, $group_items) = each($group_data) ) {

					unset($group_hash);
					if ( $group_name != 'DEALERCHOICE_DEFAULT' && is_array($import_groups) && in_array("group_{$i}", $import_groups) ) {

						$r = $this->db->query("SELECT group_hash
											   FROM line_groups
											   WHERE proposal_hash = '$proposal_hash' AND group_descr = '" . addslashes($group_name) . "'");
						$group_hash = $this->db->result($r, 0, 'group_hash');

						if ( ! $group_hash ) {

							$group_hash = rand_hash('line_groups', 'group_hash');
							if ( ! $this->db->query("INSERT INTO line_groups
        											 VALUES
        											 (
        											     NULL,
            											 UNIX_TIMESTAMP(),
            											 '{$this->current_hash}',
            											 '$group_hash',
            											 '$proposal_hash',
            											 '" . addslashes($group_name) . "'
            										  )")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
						}
					}

					for ( $j = 0; $j < count($group_items); $j++ ) {

						if ( is_array($import_lines) && in_array("{$i}_{$j}", $import_lines) ) {

							$item_data =& $group_items[$j];
							$new_item = array();

							if ( $item_data['CATALOG_CODE'] == 'OF1' && strlen($item_data['PN']) > 3 ) { # OFUSA specific catalog code extraction

                                $item_data['CATALOG_CODE'] = substr($item_data['PN'], 0, 3);
                                $item_data['PN'] = substr($item_data['PN'], 3);
							}

							$vendor_info_db = vendors::fetch_from_cat_code($item_data['CATALOG_CODE']);
							if ( count($vendor_info_db) > 1 ) {

								$vendor_info_db = vendors::fetch_from_cat_code($item_data['CATALOG_CODE'], $_POST["select_vendor{$i}_{$j}"]);
								$vendor_info = $vendor_info_db[0];
							} else
								$vendor_info = $vendor_info_db[0];

							$new_item['vendor_hash'] = $vendor_info['vendor_hash'];
							$new_item['product_hash'] = $vendor_info['product_hash'];
							if ( $this->current_proposal['ship_to_hash'] )
								$new_item['ship_to_hash'] = $this->current_proposal['ship_to_hash'];

							$new_item['qty'] = $item_data['QTY'];
							$packaging = $item_data['PACKAGING'];
							$num_pkg = $item_data['DIMENSIONS']['NUM_PKG'];
							if ( $packaging == 0 && $num_pkg > 1 )
								$new_item['qty'] = ceil( bcmul($new_item['qty'], $num_pkg, 2) );

							$new_item['item_no'] = addslashes($item_data['PN']);
							
							//here
							$item_data['DESCR'] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $item_data['DESCR']);
							
							$new_item['contract_code'] = $item_data['CONTRACT_CODE'];
							$new_item['item_descr'] = forceUTF8($item_data['DESCR']);
							$new_item['item_descr'] = addslashes($new_item['item_descr']);
							$new_item['line_no'] = ++$line_no_inc;
							if ( $punch )
								$new_item['punch'] = 1;

							$new_item['import_complete'] = $item_data['COMPLETE'];
							$new_item['special'] = addslashes($item_data['SPECIAL']);
							if ( $item_data['PANEL_TYPE_DESCR'] )
								$new_item['panel_id'] = $panel_type[] = $item_data['PANEL_TYPE_DESCR'];

							# Pricing
							$item_price = $item_data['PRICE'];
							$new_item['list'] = $item_price['LIST'];
							$new_item['cost'] = $item_price['BUY'];
							$new_item['sell'] = $item_price['SELL'];

							unset($discounting);

							if ( is_array($item_price['BUY_DISCOUNT']) ) {

								for ( $k = 0; $k < count($item_price['BUY_DISCOUNT']); $k++ ) {

									if ( $k == 4 )
										break;

									if ( isset($item_price['BUY_DISCOUNT'][$k]['DISCOUNT']) ) {

										$buy_disc = 1;
										$discounting['discount' . ( $k + 1 )] = $new_item['discount' . ( $k + 1 )] = $item_price['BUY_DISCOUNT'][$k]['DISCOUNT'];
									}
								}
							}

							if ( $item_price['LIST_DISCOUNT'] || $item_price['SELL_DISCOUNT'] ) {

								if ( array_key_exists('SELL_DISCOUNT', $item_price) )
                                    $item_price['LIST_DISCOUNT'] = $item_price['SELL_DISCOUNT'];


								$list_discount = $item_price['LIST_DISCOUNT'];
								$new_item['gp_type'] = 'L';
							} else
								unset($list_discount, $new_item['gp_type']);

							# Trac #1629 - 6/9/2010 Below condition removed to preserve all imported values
							#
							# if ( ! bccomp($new_item['list'], $new_item['cost'], 2) && bccomp($new_item['list'], 0, 2) ) {
                            #
							#	$cost_before = $new_item['cost'];
							#	unset($new_item['cost']);
							# }

							# Trac #1429
							if ( $item_price['DISCOUNT_CATEGORY'] ) {

								$r = $this->db->query("SELECT
                            							   dd.obj_id,
                            							   dd.discount_hash,
                            							   dd.item_hash
													   FROM discounting d
													   LEFT JOIN discount_details dd ON d.discount_hash = dd.discount_hash
													   WHERE vendor_hash = '{$new_item['vendor_hash']}'
    													   AND customer_hash = '{$this->current_proposal['customer_hash']}'
    													   AND discount_code = '{$item_price['DISCOUNT_CATEGORY']}'");
								if ( $row = $this->db->fetch_assoc($r) ) {

									$new_item['discount_hash'] = $row['discount_hash'];
									$new_item['discount_item_hash'] = $row['item_hash'];
								} else {

									$r = $this->db->query("SELECT
                            								   dd.obj_id,
                            								   dd.discount_hash,
                            								   dd.item_hash
    													   FROM discounting d
    													   LEFT JOIN discount_details dd ON d.discount_hash = dd.discount_hash
    													   WHERE vendor_hash = '{$new_item['vendor_hash']}'
        													   AND customer_hash = ''
        													   AND discount_code = '{$item_price['DISCOUNT_CATEGORY']}'");
									if( $row = $this->db->fetch_assoc($r) ) {

    									$new_item['discount_hash'] = $row['discount_hash'];
    									$new_item['discount_item_hash'] = $row['item_hash'];
									}
								}
							}

							# Trac #1629 - 6/9/2010 Below condition removed to preserve all imported values
							#
							# Trac #1118
							# if ( ! bccomp($new_item['list'], $new_item['sell'], 2) && bccomp($new_item['list'], 0, 2) && bccomp($cost_before, 0, 2) )
							#	unset($new_item['sell']);

							# Trac #538
							unset($_POST['discount1'], $_POST['discount2'], $_POST['discount3'], $_POST['discount4'], $_POST['discount5'], $_POST['list_discount'], $_POST['gp_margin']);

							if ( ! bccomp($new_item['cost'], 0, 2) || ! bccomp($new_item['sell'], 0, 2) ) {

								if ( $list_discount || $buy_disc ) {

									if ( $discounting['discount1'] )
										$new_item['discount1'] = ( bccomp($discounting['discount1'], 1, 2) == -1 ? bcmul($discounting['discount1'], 100, 4) : $discounting['discount1'] );
									if ( $discounting['discount2'] )
										$new_item['discount2'] = ( bccomp($discounting['discount2'], 1, 2) == -1 ? bcmul($discounting['discount2'], 100, 4) : $discounting['discount2'] );
									if ( $discounting['discount3'] )
										$new_item['discount3'] = ( bccomp($discounting['discount3'], 1, 2) == -1 ? bcmul($discounting['discount3'], 100, 4) : $discounting['discount3'] );
									if ( $discounting['discount4'] )
										$new_item['discount4'] = ( bccomp($discounting['discount4'], 1, 2) == -1 ? bcmul($discounting['discount4'], 100, 4) : $discounting['discount4'] );
									if ( $discounting['discount5'] )
										$new_item['discount5'] = ( bccomp($discounting['discount5'], 1, 2) == -1 ? bcmul($discounting['discount5'], 100, 4) : $discounting['discount5'] );

									if ( $list_discount )
										$new_item['list_discount'] = ( bccomp($list_discount, 1, 2) == -1 ? bcmul($list_discount, 100, 4) : $list_discount );

								} else {

		                            $discounting = $this->fill_discounts( array(
		                                'item_vendor_hash'      =>  $new_item['vendor_hash'],
		                                'item_product_hash'     =>  $new_item['product_hash'],
		                                'proposal_hash'         =>  $this->proposal_hash,
		                                'item_no'               =>  $new_item['item_no']
		                            ) );

									$new_item['discount_hash'] = $discounting['discount_hash'];
									$new_item['discount_item_hash'] = $discounting['discount_item_hash'];

                                    if ( $discounting['discount1'] )
                                        $new_item['discount1'] = ( bccomp($discounting['discount1'], 1, 2) == -1 ? bcmul($discounting['discount1'], 100, 4) : $discounting['discount1'] );
                                    if ( $discounting['discount2'] )
                                        $new_item['discount2'] = ( bccomp($discounting['discount2'], 1, 2) == -1 ? bcmul($discounting['discount2'], 100, 4) : $discounting['discount2'] );
                                    if ( $discounting['discount3'] )
                                        $new_item['discount3'] = ( bccomp($discounting['discount3'], 1, 2) == -1 ? bcmul($discounting['discount3'], 100, 4) : $discounting['discount3'] );
                                    if ( $discounting['discount4'] )
                                        $new_item['discount4'] = ( bccomp($discounting['discount4'], 1, 2) == -1 ? bcmul($discounting['discount4'], 100, 4) : $discounting['discount4'] );
                                    if ( $discounting['discount5'] )
                                        $new_item['discount5'] = ( bccomp($discounting['discount5'], 1, 2) == -1 ? bcmul($discounting['discount5'], 100, 4) : $discounting['discount5'] );

									if ( $discounting['list_discount'] ) {

										$list_discount = $new_item['list_discount'] = ( bccomp($discounting['list_discount'], 1, 2) == -1 ? bcmul($discounting['list_discount'], 100, 4) : $discounting['list_discount'] );
										$new_item['gp_type'] = 'L';
									} elseif ( $discounting['gp_margin'] ) {

										$gp_margin = $new_item['gp_margin'] = ( bccomp($discounting['gp_margin'], 1, 2) == -1 ? bcmul($discounting['gp_margin'], 100, 4) : $discounting['gp_margin'] );
										$new_item['gp_type'] = 'G';
									}
								}

								$values = $this->validate_line_item( array(
								    'local'         =>  1,
								    'qty'           =>  $new_item['qty'],
								    'list'          =>  $new_item['list'],
								    'discount1'     =>  $new_item['discount1'],
								    'discount2'     =>  $new_item['discount2'],
								    'discount3'     =>  $new_item['discount3'],
								    'discount4'     =>  $new_item['discount4'],
								    'discount5'     =>  $new_item['discount5'],
								    'list_discount' =>  $new_item['list_discount'],
								    'gp_margin'     =>  $new_item['gp_margin']
								) );

								if ( ! bccomp($new_item['cost'], 0, 2) )
									$new_item['cost'] = $values['cost'];
								if ( ! bccomp($new_item['sell'], 0, 2) )
									$new_item['sell'] = $values['sell'];
							}

							if ( $new_item['discount1'] && bccomp($new_item['discount1'], 1, 2) == 1 )
    							$new_item['discount1'] = bcmul($new_item['discount1'], .01, 4);
                            if ( $new_item['discount2'] && bccomp($new_item['discount2'], 1, 2) == 1 )
                                $new_item['discount2'] = bcmul($new_item['discount2'], .01, 4);
                            if ( $new_item['discount3'] && bccomp($new_item['discount3'], 1, 2) == 1 )
                                $new_item['discount3'] = bcmul($new_item['discount3'], .01, 4);
                            if ( $new_item['discount4'] && bccomp($new_item['discount4'], 1, 2) == 1 )
                                $new_item['discount4'] = bcmul($new_item['discount4'], .01, 4);
                            if ( $new_item['discount5'] && bccomp($new_item['discount5'], 1, 2) == 1 )
                                $new_item['discount5'] = bcmul($new_item['discount5'], .01, 4);

                            if ( $multi_currency && $item_data['PRICE_ZONE'] != $sys->home_currency['code'] ) {

                            	if ( ! isset($stored_currencies[$item_data['PRICE_ZONE']]) ) {

                                    if ( $sys->fetch_currency($item_data['PRICE_ZONE']) )
                                        $stored_currencies[ $sys->currency_id ] = $sys->current_currency['rate'];

                            	}

                            	if ( isset($stored_currencies[ $item_data['PRICE_ZONE'] ]) ) {

                                    if ( $new_item['list'] )
                                        $new_item['list'] = currency_exchange($new_item['list'], $stored_currencies[ $item_data['PRICE_ZONE'] ], 1);
                                    if ( $new_item['cost'] )
                                        $new_item['cost'] = currency_exchange($new_item['cost'], $stored_currencies[ $item_data['PRICE_ZONE'] ], 1);
                                    if ( $new_item['sell'] )
                                        $new_item['sell'] = currency_exchange($new_item['sell'], $stored_currencies[ $item_data['PRICE_ZONE'] ], 1);
                            	}
                            }

							$item_tag = $item_data['TAG'];
							for ( $k = 0; $k < 3; $k++ ) {

								if ( $item_tag[$k]['TYPE'] && $item_tag[$k]['VALUE'] )
									$new_item['item_tag' . ( $k + 1 )] = addslashes($item_tag[$k]['VALUE']);
							}

							$item_hash = rand_hash('item_hash_index', 'item_hash');
							if ( $group_hash )
								$add_to_group[$group_hash][] = $item_hash;

							if ( ! $this->db->query("INSERT INTO item_hash_index
        											 VALUES
        											 (
            											 '$item_hash'
            										 )")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
                            //here
                            for ($t = 0; $t < count($item_data['FINISH']); $t++) {
                            	$item_data['FINISH'][$t]['DESCR'] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '',  $item_data['FINISH'][$t]['DESCR']);
                            	continue;
                            }

							$item_added++;
							$new_item = $this->db->prepare_query($new_item, "line_items", 'INSERT');
							if ( ! $this->db->query("INSERT INTO line_items
        											 (
            											 timestamp,
            											 last_change,
            											 item_hash,
            											 proposal_hash,
            											 " . implode(", ", array_keys($new_item) ) . ",
            											 import_data
            										 )
        											 VALUES
        											 (
            											 UNIX_TIMESTAMP(),
            											 '{$this->current_hash}',
            											 '$item_hash',
            											 '$proposal_hash',
            											 " . implode(", ", array_values($new_item) ) . ",
            											 '" . addslashes( serialize($item_data) ) . "'
            										 )")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }

							$lines->item_tax($item_hash);

							# Trac #1443
							if ( $punch ) {

								if ( ! $this->db->query("UPDATE proposals
														 SET proposal_status = 'PL', status_comment = 'Punchlist created as of " . date(DATE_FORMAT) . "'
														 WHERE proposal_hash = '{$proposal_hash}'")
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                }
							}
						}
					}

					$i++;
				}

				if ( is_array($this->xmlPar->project['PANEL_TYPE']) && is_array($panel_type) ) {

					$panel_matrix = $this->xmlPar->project['PANEL_TYPE'];
					$count = count($this->xmlPar->project['PANEL_TYPE']);
					for ( $k = 0; $k < $count; $k++ ) {

						if ( ! in_array($panel_matrix[$k]['NAME'], $panel_type) )
							unset($this->xmlPar->project['PANEL_TYPE'][$k]);
					}

					$panel_matrix = array_values($this->xmlPar->project['PANEL_TYPE']);

					for ( $k = 0; $k < count($panel_matrix); $k++ ) {

						$new_panels[] = array(
    						"NAME"    => $panel_matrix[$k]['NAME'],
    						"EL"      => $k
						);
					}

					if ( is_array($this->current_proposal['import_data']['PANEL_TYPE']) ) {

						for ( $k = 0; $k < count($this->current_proposal['import_data']['PANEL_TYPE']); $k++ ) {

							$existing_panels_name[] = $this->current_proposal['import_data']['PANEL_TYPE'][$k]['NAME'];
							$existing_panels_el[] = $k;
						}
					}

					if ( ! is_array($existing_panels_name) )
						$no_previous_panels = true;

					for ( $k = 0; $k < count($new_panels); $k++ ) {

						if ( $no_previous_panels )
							$this->current_proposal['import_data']['PANEL_TYPE'][] = $panel_matrix[ $new_panels[$k]['EL'] ];
						else {

							if ( in_array($new_panels[$k]['NAME'], $existing_panels_name) )
								$this->current_proposal['import_data']['PANEL_TYPE'][ $existing_panels_el[array_search($new_panels[$k]['NAME'], $existing_panels_name)] ] = $panel_matrix[ $new_panels[$k]['EL'] ];
							else
								$this->current_proposal['import_data']['PANEL_TYPE'][] = $panel_matrix[ $new_panels[$k]['EL'] ];
						}
					}
				}
			}

			if ( is_array($this->current_proposal['import_data']['PANEL_TYPE']) )
				$this->current_proposal['import_data']['PANEL_TYPE'] = array_values($this->current_proposal['import_data']['PANEL_TYPE']);

			if ( $this->xmlPar )
				$import_data['SETUP'] = $this->xmlPar->project['SETUP'];

			if ( $this->current_proposal['import_data']['PANEL_TYPE'] )
				$import_data['PANEL_TYPE'] = $this->current_proposal['import_data']['PANEL_TYPE'];

			if ( $import_data )
				$import_data = addslashes( serialize($import_data) );

			if ( ! $this->db->query("UPDATE proposals
        							 SET
            							 timestamp = UNIX_TIMESTAMP(),
            							 last_change = '{$this->current_hash}',
            							 final = 0,
            							 import_from = '$import_from' " .
            							 ( $import_data ?
                							 ", import_data = '$import_data'" : NULL
            							 ) . "
        							 WHERE proposal_hash = '$proposal_hash'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			if ( $drop_panel ) {

				for ( $k = 0; $k < count($drop_panel); $k++ ) {

					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM line_items
												WHERE proposal_hash = '$proposal_hash' AND panel_id = '{$drop_panel[$k]}'");
					if ( ! $this->db->result($result, 0, 'Total') )
						$this->drop_panel($proposal_hash, $drop_panel[$k]);
				}
			}

			if ( $add_to_group ) {

				while ( list($group_hash, $item_array_add) = each($add_to_group) )
					$lines->add_to_group($item_array_add, $group_hash);
			} else
				$this->reorder_lines($proposal_hash);

			if ( $item_added )
				$this->unset_final($proposal_hash, $punch);

            $this->db->end_transaction();

			unset($this->ajax_vars, $this->current_proposal);
			$this->content['action'] = 'close';

			if ( $punch )
				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$proposal_hash');" );
			else
				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_items', 'otf=1', 'proposal_hash=$proposal_hash');" );

			return;

		} else {

			if ( $import_from == 'PR' && $method == 'proposal' ) {

				if ( ! $this->fetch_master_record($proposal_hash) )
                    return $this->__trigger_error("System error encountered during proposal lookup. Unable to fetch proposal record for item import.", E_USER_ERROR, __FILE__, __LINE__, 1);

				$this->ajax_vars['proposal_import_hash'] = $_POST['import_proposal_hash'];
				$this->ajax_vars['proposal_import_no'] = $_POST['import_proposal_no'];

				$this->ajax_vars['method'] = 'proposal';
				$this->ajax_vars['import_from'] = $_POST['import_proposal_hash'];
				$this->ajax_vars['action'] = 'import';
				$this->content['action'] = 'continue';
				$this->content['html']['import_confirm_holder'] = $this->import_export(1);
				$this->content['jscript'][] = "setTimeout('expandcontent(\$(\'cntrl_tcontent2{$this->popup_id}\'))', 100)";

				$this->content['jscript'][] = "$('feedback_holder{$this->popup_id}').style.display='none'";

				return;

			} else {

				store_user_data("proposal_import", $import_from);

				if ( $file_name && ! $file_error ) {

					if ( ! $this->fetch_master_record($proposal_hash) )
    					return $this->__trigger_error("System error encountered during proposal lookup. Unable to fetch proposal record for item import.", E_USER_ERROR, __FILE__, __LINE__, 1);

    				try {

						$this->xmlPar = new xmlParser($file_name, $import_from, $proposal_hash);
    				} catch ( Exception $e ) {

    					$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?proposal_hash={$this->proposal_hash}&class=proposals&action=doit_import_export&method_action={$action}&current_hash={$this->current_hash}&popup_id={$this->popup_id}', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
    					return $this->__trigger_error($e->getMessage() . " <!-- Referrer " . find_root( __FILE__ ) . ":" . __LINE__ . " -->", E_USER_ERROR, $e->getFile(), $e->getLine(), 1);
    				}

					if ( ! $this->xmlPar->error ) {

						$this->xmlPar->xmlFile['type'] = $file_type;
						$this->xmlPar->xmlFile['size'] = $file_size;
						$this->xmlPar->xmlFile['error'] = $file_error;
						$this->xmlPar->xml2ary();

						if ( ! $this->xmlPar->error ) {

							$this->ajax_vars['action'] = 'import';
							$this->content['action'] = 'continue';
							$this->content['html']['import_confirm_holder'] = $this->import_export(1);
							$this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_tcontent2{$this->popup_id}\'))', 100)";

							$this->content['jscript'][] = "$('feedback_holder{$this->popup_id}').style.display='none'";
						} else {

							$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?proposal_hash={$this->proposal_hash}&class=proposals&action=doit_import_export&method_action={$action}&current_hash={$this->current_hash}&popup_id={$this->popup_id}', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
							return $this->__trigger_error("There was a problem with the file you imported. {$this->xmlPar->error}", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}
					} else {

						$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?proposal_hash={$this->proposal_hash}&class=proposals&action=doit_import_export&method_action={$action}&current_hash={$this->current_hash}&popup_id={$this->popup_id}', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
						return $this->__trigger_error("There was a problem with the file you imported. {$this->xmlPar->error}", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

				} else {

					$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?proposal_hash={$this->proposal_hash}&class=proposals&action=doit_import_export&method_action={$action}&current_hash={$this->current_hash}&popup_id={$this->popup_id}', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
					return $this->__trigger_error("There was a problem with the file you imported. Please check to make sure the file is valid and not damaged and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
				}
			}

			unset($this->ajax_vars, $this->current_proposal, $this->line_items);
			return;
		}
	}

	function validate_line_item() {

		if ( $param = func_get_arg(0) ) {

			$local = 1;
			$qty = $param['qty'];
			$list = $param['list'];
			$discount1 = $param['discount1'];
			$discount2 = $param['discount2'];
			$discount3 = $param['discount3'];
			$discount4 = $param['discount4'];
			$discount5 = $param['discount5'];

			$cost = $param['cost'];
		    $gp_margin = $param['gp_margin'];
			$list_discount = $param['list_discount'];
			$sell = $param['sell'];
			$from_edit = $param['from_edit'];
			$booked = $param['booked'];
		}

		if ( ! $local ) {

			$from_field = $_POST['from_field'];
			$qty = $_POST['qty'];
			$list = preg_replace('/[^-.0-9]/', "", $_POST['list']);
			$discount1 = preg_replace('/[^.0-9]/', "", $_POST['discount1']);
			$discount2 = preg_replace('/[^.0-9]/', "", $_POST['discount2']);
			$discount3 = preg_replace('/[^.0-9]/', "", $_POST['discount3']);
			$discount4 = preg_replace('/[^.0-9]/', "", $_POST['discount4']);
			$discount5 = preg_replace('/[^.0-9]/', "", $_POST['discount5']);

			$cost = preg_replace('/[^-.0-9]/', "", $_POST['cost']);
			$gp_margin = preg_replace('/[^-.0-9]/', "", $_POST['gp_margin']);
			$list_discount = preg_replace('/[^-.0-9]/', "", $_POST['list_discount']);
			$sell = preg_replace('/[^-.0-9]/', "", $_POST['sell']);
			$from_edit = $_POST['from_edit'];
			$booked = $_POST['booked'];
		}

		$discount1 = bcmul($discount1, .01, 4);
		$discount2 = bcmul($discount2, .01, 4);
		$discount3 = bcmul($discount3, .01, 4);
		$discount4 = bcmul($discount4, .01, 4);
		$discount5 = bcmul($discount5, .01, 4);

		if ( $list ) {

			if ( is_numeric($discount1) && bccomp($discount1, 0, 4) ) {

				$cost = _round( bcsub($list, bcmul($list, $discount1, 4), 4) );
				if ( is_numeric($discount2) && bccomp($discount2, 0, 4) ) {

					$cost = _round( bcsub($cost, bcmul($cost, $discount2, 4), 4) );
					if ( is_numeric($discount3) && bccomp($discount3, 0, 4) ) {

	                    $cost = _round(bcsub($cost, bcmul($cost, $discount3, 4), 4));
						if ( is_numeric($discount4) && bccomp($discount4, 0, 4) ) {

	                        $cost = _round( bcsub($cost, bcmul($cost, $discount4, 4), 4) );
							if ( is_numeric($discount5) && bccomp($discount5, 0, 4) )
	                            $cost = _round( bcsub($cost, bcmul($cost, $discount5, 4), 4) );

						}
					}
				}
			}
		}

		if ( is_numeric($cost) && is_numeric($gp_margin) && ! $from_edit && ! $booked ) {

			if ( ! bccomp($gp_margin, 0, 4) ) # Trac #1366
				$sell = $cost;
			else
				$sell = math::gp_margin( array(
    				'cost'      =>  $cost,
    				'margin'    =>  $gp_margin
				) );

			if ( $qty )
				$this->content['html']["ext_sell{$this->popup_id}"] = _round( bcmul($sell, $qty, 4), 2);
			if ( $from_field != 'sell' )
				$return['sell'] = $this->content['value']['sell'] = ( $local ? _round($sell, 2) : '$' . number_format( _round($sell, 2), 2) );

			$this->content['html']["gp_margin{$this->popup_id}"] =
			( defined('MARGIN_FLAG') && bccomp( bcmul($gp_margin, .01, 4), MARGIN_FLAG, 4) == -1 ?
    			"<span style=\"color:red;\">" : NULL
			) . "{$gp_margin}&nbsp;%" .
			( defined('MARGIN_FLAG') && bccomp( bcmul($gp_margin, .01, 4), MARGIN_FLAG, 4) == -1 ?
    			"</span>" : NULL
    		);

		} elseif ( is_numeric($list) && $list_discount && $from_field != 'sell' && ! $from_edit && ! $booked ) {

			$sell = math::list_discount( array(
				'list'   =>  $list,
				'disc'   =>  $list_discount
			) );
			$return['sell'] = $this->content['value']['sell'] = ( $local ? $sell : '$' . number_format( _round($sell, 2), 2) );
		}

		if ( is_numeric($cost) ) {

			if ( $from_field != 'cost' )
				$return['cost'] = $this->content['value']['cost'] = ( $local ? _round($cost, 2) : '$' . number_format( _round($cost, 2), 2) );
			if ( is_numeric($qty) )
				$this->content['html']["extended_cost{$this->popup_id}"] = '$' . number_format( _round( bcmul($qty, $cost, 4), 2), 2);
			if ( is_numeric($sell) ) {

				$gp_margin = math::gp_margin( array(
					'cost'  =>  $cost,
					'sell'  =>  $sell
				) );
				$gp_margin = (float)bcmul($gp_margin, 100, 4);
				if ( bccomp($gp_margin, 0, 2) == 1 && ! $list_discount && $from_field != 'sell' && $from_field != 'gp' && ! $booked )
					$this->content['value']['gp_margin'] = $gp_margin;

				$this->content['html']["gp_margin{$this->popup_id}"] =
				( defined('MARGIN_FLAG') && bccomp( bcmul($gp_margin, .01, 4), MARGIN_FLAG, 4) == -1 ?
                    "<span style=\"color:red;\">" .
    				( bccomp($gp_margin, 0, 2) == -1 ?
                        "(" .
        				( bccomp($gp_margin, -100, 2) == -1 ?
                            "100" : bcmul($gp_margin, -1, 2)
        				) . " %)" : "$gp_margin %"
        			) . "</span>" : "$gp_margin %"
        		);

				if ( is_numeric($qty) ) {

					$profit = bcsub( bcmul($sell, $qty, 4), bcmul($cost, $qty, 4), 4);
					$this->content['html']["extended_sell{$this->popup_id}"] = '$'.number_format(_round(bcmul($sell, $qty, 4), 2), 2);
					$this->content['html']["profit_dollars{$this->popup_id}"] =
					( bccomp($profit, 0, 2) == -1 ?
                        "<span style=\"color:red;\">(\$" . number_format( _round( bcmul($profit, -1, 2), 2), 2) . ")" : '$' . number_format( _round($profit, 2), 2)
					);
				}

			} elseif ( ! is_numeric($gp_margin) ) {

				$this->content['html']["extended_sell{$this->popup_id}"] = '';
				$this->content['html']["profit_dollars{$this->popup_id}"] = '';
				$this->content['html']["gp_margin{$this->popup_id}"] = '';
			}
		}

		if ( $local ) {

			unset($this->content['value'], $this->content['html']);
			return $return;
		} else {

			$this->content['submit_btn'] = 'submit_btn';
			$this->content['action'] = 'continue';
		}
		return;
	}

	function fill_discounts() {

        if ( $param = func_get_arg(0) ) {

        	$local = 1;
        	$item_vendor_hash = $param['item_vendor_hash'];
        	$item_product_hash = $param['item_product_hash'];
        	$proposal_hash = $param['proposal_hash'];
        	$selected_discount_hash = $param['discount_hash'];
        	$selected_item = $param['selected_item_discount'];
        	$item_hash = $param['item_hash'];
        	$item_no = $param['item_no'];
        	$func_update = $param['func_update'];
        	$proposal_final = $param['proposal_final'];
        }

        if ( ! $local ) {

			$item_vendor_hash = $_POST['item_vendor_hash'];
			$item_product_hash = $_POST['item_product_hash'];
			$proposal_hash = $_POST['proposal_hash'];
			$selected_discount_hash = $_POST['discount_hash'];
			$item_hash = $_POST['item_hash'];
			$item_no = trim($_POST['item_no']);
			$func_update = $_POST['func_update'];
        }

		if ( ! $item_vendor_hash || ! $proposal_hash )
            return;

		# Trac #1281
		$r = $this->db->query("SELECT customer_hash
							   FROM proposals
							   WHERE proposal_hash = '$proposal_hash' and deleted = 0");
		$customer_hash = $this->db->result($r, 0, 'customer_hash');

		if ( ! $customer_hash )
			return;

        if ( $item_hash && ! $selected_discount_hash ) # If item but no discount provided
            $selected_discount_hash = '_';

		# Trac #1281 - proposals::fetch_master_record removed

		$discounting = new discounting($item_vendor_hash, 'V');

		# Checking for valid discounts
		if ( $discounting->total && $discounting->total_valid ) {

			$this->content['html']["discount_message{$this->popup_id}"] = '';
			$discounting->fetch_proposal_discounts($customer_hash);

			# Item discounting is not disabled, no pre-selected discount, origin not from function menu
			if ( $selected_discount_hash != '_' && ! $selected_discount_hash && ! $func_update ) {

				for ( $i = 0; $i < count($discounting->discounts); $i++ ) {

					if ( $discounting->discounts[$i]['discount_type'] == 'C' && $discounting->discounts[$i]['customer_hash'] == $customer_hash && strtotime($discounting->discounts[$i]['discount_expiration_date']) >= strtotime( date("Y-m-d") ) ) {

						$selected_discount_hash = $discounting->discounts[$i]['discount_hash']; # Discount is valid customer discount matching proposal customer
						break;

					} elseif ( $discounting->customer_gsa && $discounting->discounts[$i]['discount_type'] == 'S' && $discounting->discounts[$i]['discount_gsa'] ) {

						$selected_discount_hash = $discounting->discounts[$i]['discount_hash']; # GSA customer, standard GSA discount
						break;
					}

					if ( $discounting->discounts[$i]['discount_type'] == 'S' && $discounting->discounts[$i]['discount_default'] )
						$default_standard_disc = $discounting->discounts[$i]['discount_hash']; # Set default standard discount if needed
				}
			}

			if ( $selected_discount_hash != '_' && ! $selected_discount_hash && ! $func_update && $default_standard_disc )
				$selected_discount_hash = $default_standard_disc; # No specified discount, no function menu and default standard exists

			# Trac #1677
			#if ( $selected_discount_hash != '_' && ! $selected_discount_hash && ! $func_update )
			#	$selected_discount_hash = $discounting->discounts[0]['discount_hash']; # No discount specified and no function menu

			if ( $selected_discount_hash != '_' && $selected_discount_hash ) # Haven't specified no discount and discount choosen from above steps
				$discounting->fetch_discount_record($selected_discount_hash);

			if ( $item_product_hash && $selected_discount_hash ) { # Check discount for current product

				list( $discount_item_hash, $product_hash, $nothing, $nothing, $nothing, $expire_date, $discount_item_no) = $discounting->fetch_discount_items_short($selected_discount_hash);

				if ( $selected_discount_hash != '_' && ! in_array($item_product_hash, $product_hash) ) { # No product discount found and no catchall product discount, defaulting to standard

					$ck = false;
					for ( $i = 0; $i < count($discount_item_hash); $i++ ) {

						if ( ( $product_hash[$i] == '*' && ! isset($discount_item_no[$i]) ) || ( $product_hash[$i] == '*' && isset($discount_item_no[$i]) && $discount_item_no[$i] == $item_no ) )
                            $ck = true; # Check product discount for catchall or matching current item no

					}

					if ( ! $ck ) {

						$discounting->fetch_default_standard();

						if ( $discounting->default_standard_discount ) { # If default standard exists

		                    $return["discount_message{$this->popup_id}"] = $this->content['html']["discount_message{$this->popup_id}"] = "
		                    <div style=\"width:190px;padding-left:4px;font-style:italic;\">
		                        Can't find a discount for the selected product!" .
    		                    ( $discounting->default_standard_discount ? "
    		                        Defaulting to {$discounting->default_standard_discount['discount_descr']}" : NULL
    		                    ) . "
		                    </div>";

							$selected_discount_hash = $discounting->default_standard_discount['discount_hash'];
							$discounting->fetch_discount_record($discounting->default_standard_discount['discount_hash']);
						}

					} else
                        $return["discount_message{$this->popup_id}"] = $this->content['html']["discount_message{$this->popup_id}"] = '';
				} else
					$return["discount_message{$this->popup_id}"] = $this->content['html']["discount_message{$this->popup_id}"] = '';
			}

			if ( ! $local ) {

				$this->content['html']["discount_id{$this->popup_id}"] =
				( $selected_discount_hash == '_' ?
                    "No Discount Used" : $discounting->current_discount['discount_id']
				);
				$this->content['html']["discount_descr{$this->popup_id}"] =
				( $selected_discount_hash == '_' ?
                    "" :
    				( strlen($discounting->current_discount['discount_descr']) > 15 ?
                        wordwrap($discounting->current_discount['discount_descr'], 15, "<br />") : $discounting->current_discount['discount_descr']
                    )
                );
				$this->content['html']["discount_expiration{$this->popup_id}"] =
				( $selected_discount_hash == '_' || ! $discounting->current_discount['discount_expiration_date'] ?
                    "" : "<span " .
    				( strtotime($discounting->current_discount['discount_expiration_date']) < strtotime( date("Y-m-d") ) ?
                        "style=\"font-weight:bold;color:red;\" title=\"This discount has expired!\"" :
        				( strtotime($discounting->current_discount['discount_expiration_date']) - 2592000 <= strtotime( date("Y-m-d") ) ?
                            "style=\"font-weight:bold;color:#FFD65C;\" title=\"This discount expires within the month!\"" : NULL
        				)
        			) . ">" . date("M j, Y", strtotime($discounting->current_discount['discount_expiration_date'])) . "</span>"
        		);
			}

			if ( count($discounting->discounts) >= 1 ) { # Provide discount menu if more than one applicable discount

				$return['disc_menu'] = $this->content['html']["disc_menu{$this->popup_id}"] = "
				<div style=\"padding-top:5px;padding-right:5px;\">
					[<a href=\"javascript:void(0);\" onClick=\"position_element($('disc_menu_holder'), findPos('disc_menu_holder_x', 'top'), findPos('disc_menu_holder_x', 'left'));toggle_display('disc_menu_holder', 'block');\" class=\"link_standard\"><small>Change</small></a>]
				</div>
				<div id=\"disc_menu_holder\" class=\"function_menu\" style=\"text-align:left;\">
					<div style=\"float:right;padding:3px\">
						<a href=\"javascript:void(0);\" onClick=\"toggle_display('disc_menu_holder', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
					</div>
					<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Use Discount:</div>";

				$return['disc_menu'] .= "
					<div class=\"function_menu_item\" title=\"Do not use a discount\">" .
						$this->form->radio(
    						"name=discount_hash",
                            "value=_",
    						( $selected_discount_hash == '_' ? "checked" : NULL ),
                            "id=no_disc"
    					) . "&nbsp;
						<a href=\"javascript:void(0);\" style=\"color:#000000;text-decoration:none;\" onClick=\"\$('no_disc').checked=1;\"><i>Do not apply a discount</i></a>
					</div>";

				if ( $func_update ) {

					$this->content['html']["func_disc_menu{$this->popup_id}"] .= "
						<div style=\"padding-top:5px;\" title=\"Do not use a discount\">" .
							$this->form->radio(
    							"name=func_discount_hash",
                                "value=_",
    							( $selected_discount_hash == '_' ? "checked" : NULL ),
                                "id=no_disc"
    						) . "&nbsp;
							<a href=\"javascript:void(0);\" style=\"color:#000000;text-decoration:none;\" onClick=\"\$('no_disc').checked=1;\"><i>Do not apply a discount</i></a>
						</div>";
				} else {

	                $this->content['html']["disc_menu{$this->popup_id}"] .= "
	                    <div class=\"function_menu_item\" title=\"Do not use a discount\">" .
	                        $this->form->radio(
    	                        "name=discount_hash",
                                "value=_",
    	                        ( $selected_discount_hash == '_' ? "checked" : NULL ),
                                "id=no_disc"
    	                    ) . "&nbsp;
	                        <a href=\"javascript:void(0);\" style=\"color:#000000;text-decoration:none;\" onClick=\"\$('no_disc').checked=1;\"><i>Do not apply a discount</i></a>
	                    </div>";
				}

				for ( $i = 0; $i < count($discounting->discounts); $i++ ) {

					if ( strtotime($discounting->discounts[$i]['discount_expiration_date']) >= strtotime( date("Y-m-d") ) ) {

						if ( $local ) {

							$return['disc_menu'] .= "
							<div class=\"function_menu_item\" title=\"" . htmlentities($discounting->discounts[$i]['discount_descr'], ENT_QUOTES) . " ({$discounting->discounts[$i]['discount_id']})\">" .
								$this->form->radio(
    								"name=discount_hash",
                                    "value={$discounting->discounts[$i]['discount_hash']}",
                                    ( $discounting->discounts[$i]['discount_hash'] == $selected_discount_hash ? "checked" : NULL ),
                                    "id=disc_{$i}"
                                ) . "&nbsp;
								<a href=\"javascript:void(0);\" style=\"color:#000000;text-decoration:none;\" onClick=\"\$('disc_{$i}').checked=1;\">" .
								( strlen($discounting->discounts[$i]['discount_descr']) > 20 ?
									substr($discounting->discounts[$i]['discount_descr'], 0, 18) . "..." : $discounting->discounts[$i]['discount_descr']
								) . "
								</a>
							</div>";
						} else {

							if ( $func_update ) {

								$this->content['html']["func_disc_menu{$this->popup_id}"] .= "
								<div style=\"padding-top:5px;\">" .
									$this->form->radio(
    									"name=func_discount_hash",
                                        "value={$discounting->discounts[$i]['discount_hash']}",
                                        ( $discounting->discounts[$i]['discount_hash'] == $selected_discount_hash ? "checked" : NULL ),
                                        "id=disc_{$i}"
                                    ) . "&nbsp;
									<a href=\"javascript:void(0);\" style=\"color:#000000;text-decoration:none;\" onClick=\"\$('disc_{$i}').checked=1;\">{$discounting->discounts[$i]['discount_descr']} ({$discounting->discounts[$i]['discount_id']})</a>
								</div>";
							} else {

	                            $this->content['html']["disc_menu{$this->popup_id}"] .= "
	                            <div class=\"function_menu_item\" title=\"". htmlentities($discounting->discounts[$i]['discount_descr'], ENT_QUOTES) . " ({$discounting->discounts[$i]['discount_id']})\">" .
	                                $this->form->radio(
    	                                "name=discount_hash",
                                        "value={$discounting->discounts[$i]['discount_hash']}",
                                        ( $discounting->discounts[$i]['discount_hash'] == $selected_discount_hash ? "checked" : NULL ),
                                        "id=disc_{$i}"
                                    ) . "&nbsp;
	                                <a href=\"javascript:void(0);\" style=\"color:#000000;text-decoration:none;\" onClick=\"\$('disc_{$i}').checked=1;\">" .
	                                ( strlen($discounting->discounts[$i]['discount_descr']) > 20 ?
	                                    substr($discounting->discounts[$i]['discount_descr'], 0, 18) . "..." : $discounting->discounts[$i]['discount_descr']
	                                ) . "
	                                </a>
	                            </div>";
							}
						}
					}
				}

				if ( $local )
					$return['disc_menu'] .= "
						<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">" .
        					$this->form->button(
                                "value=Go",
                                "onclick=toggle_display('disc_menu_holder', 'none');submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=fill_discounts');setTimeout(function(){validateItem()},750);"
        					) . "
        				</div>
					</div>";
				else
					$this->content['html']["disc_menu{$this->popup_id}"] .= "
						<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">" .
        					$this->form->button(
            					"value=Go", "
            					onclick=toggle_display('disc_menu_holder', 'none');submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=fill_discounts');setTimeout(function(){validateItem()},750);"
        					) . "
        				</div>
					</div>";

			} elseif ( $selected_discount_hash ) {

				$return['disc_menu'] = $this->content['html']["disc_menu{$this->popup_id}"] = $this->form->hidden( array(
    				'discount_hash' => $selected_discount_hash
				) );
			}

			if ( $local )
				$return['discount_hash'] = $discounting->discount_hash;
			else
				$this->content['value']['discount_hash'] = $discounting->discount_hash;

			# Item level discounting
			$discounting->fetch_discount_items(0, $discounting->total_discounts);

			$item_disc_selected = false; # Current discount standard type
			if ( $discounting->current_discount && $discounting->current_discount['discount_type'] == 'S' ) {

				if ( $item_product_hash && is_numeric( in_multi_array($discounting->discount_items, $item_product_hash, 'product_hash') ) ) {

                    for ( $i = 0; $i < count($discounting->discount_items); $i++ ) {

                    	if ( $discounting->discount_items[$i]['product_hash'] == $item_product_hash && ( ( isset($discounting->discount_items[$i]['item_no']) && $discounting->discount_items[$i]['item_no'] == $item_no ) || ! isset($discounting->discount_items[$i]['item_no']) ) ) {

                    		if ( isset($discounting->discount_items[$i]['item_no']) && $discounting->discount_items[$i]['item_no'] == $item_no ) {

                                $item_disc_selected = $discounting->discount_items[$i]['item_hash'];
                                break;
                            }
                            if ( ! isset($discounting->discount_items[$i]['item_no']) )
                                $product_discount = $discounting->discount_items[$i]['item_hash'];

                        }
    				}

                    if ( $item_disc_selected || $product_discount )
                        $discounting->fetch_item_record( ( $item_disc_selected ? $item_disc_selected : $product_discount ) );

				} elseif ( is_numeric( in_multi_array($discounting->discount_items, '*', 'product_hash') ) ) {

                    for ( $i = 0; $i < count($discounting->discount_items); $i++ ) {

                        if ( $discounting->discount_items[$i]['product_hash'] == '*' && ( ( isset($discounting->discount_items[$i]['item_no']) && $discounting->discount_items[$i]['item_no'] == $item_no ) || ! isset($discounting->discount_items[$i]['item_no']) ) ) {

                            if ( isset($discounting->discount_items[$i]['item_no']) && $discounting->discount_items[$i]['item_no'] == $item_no ) {

                                $item_disc_selected = $discounting->discount_items[$i]['item_hash'];
                                break;
                            }
                            if ( ! isset($discounting->discount_items[$i]['item_no']) )
                                $product_discount = $discounting->discount_items[$i]['item_hash'];

                        }
                    }

                    if ( $item_disc_selected || $product_discount )
                        $discounting->fetch_item_record( ( $item_disc_selected ? $item_disc_selected : $product_discount ) );
				}

				$return['discount_type'] = $discounting->current_item['discount_type'];
				if ( bccomp($discounting->current_item['buy_discount1'], 0, 4) ){
    				$return['discount1'] = $this->content['value']['discount1'] = (float)bcmul($discounting->current_item['buy_discount1'], 100, 4);
				}else{
					$return['discount1'] = $this->content['value']['discount1'] = 0;
				}
    			if ( bccomp($discounting->current_item['buy_discount2'], 0, 4) ){
    				$return['discount2'] = $this->content['value']['discount2'] = (float)bcmul($discounting->current_item['buy_discount2'], 100, 4);
    			}else{
    				$return['discount2'] = $this->content['value']['discount2'] = 0;
    			}
    			
                if ( bccomp($discounting->current_item['buy_discount3'], 0, 4) ){
                	$return['discount3'] = $this->content['value']['discount3'] = (float)bcmul($discounting->current_item['buy_discount3'], 100, 4);
                }else{
                	$return['discount3'] = $this->content['value']['discount3'] = 0;
                }
                
				if ( bccomp($discounting->current_item['buy_discount4'], 0, 4) ){
    				$return['discount4'] = $this->content['value']['discount4'] = (float)bcmul($discounting->current_item['buy_discount4'], 100, 4);
    			}else{
    				$return['discount4'] = $this->content['value']['discount4'] = 0;
    			}
    			
				if ( bccomp($discounting->current_item['buy_discount5'], 0, 4) ){
    				$return['discount5'] = $this->content['value']['discount5'] = (float)bcmul($discounting->current_item['buy_discount5'], 100, 4);
    			}else{
    				$return['discount5'] = $this->content['value']['discount5'] = 0;
    			}
    			
				if ( bccomp($discounting->current_item['sell_discount'], 0, 4) ){
    				$return['list_discount'] = $this->content['value']['list_discount'] = (float)bcmul($discounting->current_item['sell_discount'], 100, 4);
				}else{
					$return['list_discount'] = $this->content['value']['list_discount'] = 0;
				}

				if ( bccomp($discounting->current_item['gp_margin'], 0, 2) == 1 ) {

					$return['gp_margin'] = $this->content['value']['gp_margin'] = (float)bcmul($discounting->current_item['gp_margin'], 100, 4);
					$return['list_discount'] = $this->content['value']['list_discount'] = '';
				} else
					$return['gp_margin'] = $this->content['value']['gp_margin'] = '';

				$return['discount_item_hash'] = $this->content['value']['discount_item_hash'] = $discounting->item_hash;

				if ( $discounting->current_item['discount_type'] == 'T' && ! $proposal_final ) {

					$return["discount_message{$this->popup_id}"] .= "<i>This discount contains tiered<br />discounting which will be applied<br />during finalization.</i>";
					$this->content['html']["discount_message{$this->popup_id}"] .= "<i>This discount contains tiered<br />discounting which will be applied<br />during finalization.</i>";
				}

			} elseif ( $discounting->current_discount && $discounting->current_discount['discount_type'] == 'C' ) {

				if ( $item_product_hash && is_numeric( in_multi_array($discounting->discount_items, $item_product_hash, 'product_hash') ) ) {

                    for ( $i = 0; $i < count($discounting->discount_items); $i++ ) {

                        if ( $discounting->discount_items[$i]['product_hash'] == $item_product_hash && ( ( isset($discounting->discount_items[$i]['item_no']) && $discounting->discount_items[$i]['item_no'] == $item_no ) || ! isset($discounting->discount_items[$i]['item_no']) ) ) {

                            if ( isset($discounting->discount_items[$i]['item_no']) && $discounting->discount_items[$i]['item_no'] == $item_no ) {

                                $item_disc_selected = $discounting->discount_items[$i]['item_hash'];
                                break;
                            }
                        	if ( ! isset($discounting->discount_items[$i]['item_no']) )
	                        	$product_discount = $discounting->discount_items[$i]['item_hash'];

                        }
                    }

					if ( $item_disc_selected || $product_discount )
    					$discounting->fetch_item_record( ( $item_disc_selected ? $item_disc_selected : $product_discount ) );

				} elseif ( is_numeric( in_multi_array($discounting->discount_items, '*', 'product_hash') ) ) {

                    for ( $i = 0; $i < count($discounting->discount_items); $i++ ) {

                        if ( $discounting->discount_items[$i]['product_hash'] == '*' && ( ( isset($discounting->discount_items[$i]['item_no']) && $discounting->discount_items[$i]['item_no'] == $item_no ) || ! isset($discounting->discount_items[$i]['item_no']) ) ) {

                            if ( isset($discounting->discount_items[$i]['item_no']) && $discounting->discount_items[$i]['item_no'] == $item_no ) {

                                $item_disc_selected = $discounting->discount_items[$i]['item_hash'];
                                break;
                            }
                            if ( ! isset($discounting->discount_items[$i]['item_no']) )
                                $product_discount = $discounting->discount_items[$i]['item_hash'];
                        }
                    }
                    if ( $item_disc_selected || $product_discount )
                        $discounting->fetch_item_record( ( $item_disc_selected ? $item_disc_selected : $product_discount ) );
				}

				$return['discount_type'] = $discounting->current_item['discount_type'];

				# Standard and customer discounts exist
			if ( bccomp($discounting->current_item['buy_discount1'], 0, 4) ){
    				$return['discount1'] = $this->content['value']['discount1'] = (float)bcmul($discounting->current_item['buy_discount1'], 100, 4);
				}else{
					$return['discount1'] = $this->content['value']['discount1'] = 0;
				}
    			if ( bccomp($discounting->current_item['buy_discount2'], 0, 4) ){
    				$return['discount2'] = $this->content['value']['discount2'] = (float)bcmul($discounting->current_item['buy_discount2'], 100, 4);
    			}else{
    				$return['discount2'] = $this->content['value']['discount2'] = 0;
    			}
    			
                if ( bccomp($discounting->current_item['buy_discount3'], 0, 4) ){
                	$return['discount3'] = $this->content['value']['discount3'] = (float)bcmul($discounting->current_item['buy_discount3'], 100, 4);
                }else{
                	$return['discount3'] = $this->content['value']['discount3'] = 0;
                }
                
				if ( bccomp($discounting->current_item['buy_discount4'], 0, 4) ){
    				$return['discount4'] = $this->content['value']['discount4'] = (float)bcmul($discounting->current_item['buy_discount4'], 100, 4);
    			}else{
    				$return['discount4'] = $this->content['value']['discount4'] = 0;
    			}
    			
				if ( bccomp($discounting->current_item['buy_discount5'], 0, 4) ){
    				$return['discount5'] = $this->content['value']['discount5'] = (float)bcmul($discounting->current_item['buy_discount5'], 100, 4);
    			}else{
    				$return['discount5'] = $this->content['value']['discount5'] = 0;
    			}
    			
				if ( bccomp($discounting->current_item['sell_discount'], 0, 4) ){
    				$return['list_discount'] = $this->content['value']['list_discount'] = (float)bcmul($discounting->current_item['sell_discount'], 100, 4);
				}else{
					$return['list_discount'] = $this->content['value']['list_discount'] = 0;
				}

				if ( bccomp($discounting->current_item['gp_margin'], 0, 2) == 1 ) {

					$return['gp_margin'] = $this->content['value']['gp_margin'] = (float)bcmul($discounting->current_item['gp_margin'], 100, 4);
					$return['list_discount'] = $this->content['value']['list_discount'] = '';
				} else
					$return['gp_margin'] = $this->content['value']['gp_margin'] = '';

				$return['discount_item_hash'] = $this->content['value']['discount_item_hash'] = $discounting->item_hash;

				if ( $discounting->current_item['discount_type'] == 'T' && ! $proposal_final ) {

					$return["discount_message{$this->popup_id}"] .= "<i>This discount contains tiered<br />discounting which will be applied<br />during finalization.</i>";
					$this->content['html']["discount_message{$this->popup_id}"] .= "<i>This discount contains tiered<br />discounting which will be applied<br />during finalization.</i>";
				}
			}else{
				$return['discount1'] = $this->content['value']['discount1'] = 0;
				$return['discount2'] = $this->content['value']['discount2'] = 0;
				$return['discount3'] = $this->content['value']['discount3'] = 0;
				$return['discount4'] = $this->content['value']['discount4'] = 0;
				$return['discount5'] = $this->content['value']['discount5'] = 0;
				$return['list_discount'] = $this->content['value']['list_discount'] = 0;
			}

		} else {

			$this->content['html']["discount_expiration{$this->popup_id}"] = $this->content['html']["discount_descr{$this->popup_id}"] = $this->content['html']["discount_id{$this->popup_id}"] = $return['discount1'] = $this->content['value']['discount1'] = $return['discount2'] = $this->content['value']['discount2'] = $return['discount3'] = $this->content['value']['discount3'] = $return['discount4'] = $this->content['value']['discount4'] = $return['discount5'] = $this->content['value']['discount5'] = '';
			if ( $func_update )
				$this->content['html']["func_disc_menu{$this->popup_id}"] .= "
				<div style=\"padding-top:5px;font-style:italic\">
					There are no applicable discounts for the selected vendor.
				</div>";
			else
                $this->content['html']["discount_message{$this->popup_id}"] .= "
                <div style=\"padding-top:5px;font-style:italic\">
                    There are no applicable discounts<br />for the selected vendor.
                </div>";
		}

		if ( $local ) {

			unset($this->content['value']);
			return $return;
		} else {

			if ( $item_hash )
				$this->content['jscript'] = "lineItemValidator.prototype.validateItem();";

			$this->content['action'] = 'continue';
		}

		return;
	}

	function doit_line_item() {

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$btn = $_POST['actionBtn'];
		$method = $_POST['method'];
		$method_action = $_POST['method_action'];
		$proposal_hash = $_POST['proposal_hash'];
		$jscript_action = $_POST['jscript_action'];
		$limit = $_POST['limit'];
		$copy_from = $_POST['copy_from'];
		$line_item = $_POST['line_item'];
		$submit_btn = 'lineItemBtn';

		if ( $punch = $_POST['punch'] )
			$line_item = $_POST['punch_item'];

		$this->line_items = new line_items($proposal_hash, $punch);
		if ( $item_hash = $_POST['item_hash'] ) {

			if ( ! $this->line_items->fetch_line_item_record($item_hash) )
                return $this->__trigger_error("System error encountered during line item lookup. Unable to perform database lookup for line item edit. Please reload window and try again. <!-- Tried fetching item [ $item_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			$edit = true;
			$action_delete = $_POST['delete'];

		} elseif ( $sum = $_POST['sum'] ) {

			$actual_lines = array();

			for ( $i = 0; $i < count($line_item); $i++ ) {

				if ( $line_item[$i] ) {

					list($type, $id) = explode("|", urldecode($line_item[$i]) );

					switch ( $type ) {

						case 'vendor':

						$result = $this->db->query("SELECT item_hash
													FROM line_items
													WHERE
    													proposal_hash = '$proposal_hash'
    													AND vendor_hash = '$id'
    													AND punch = " .  ( $punch ? '1' : '0' ) );
						while ( $row = $this->db->fetch_assoc($result) )
							array_push($actual_lines, $row['item_hash']);

						break;

						case 'prod':

						$result = $this->db->query("SELECT item_hash
													FROM line_items
													WHERE
    													proposal_hash = '$proposal_hash'
    													AND product_hash = '$id'
    													AND punch = " . ( $punch ? '1' : '0' ) );
						while ( $row = $this->db->fetch_assoc($result) )
							array_push($actual_lines, $row['item_hash']);

						break;
					}
				}
			}

			$line_item = array_values($actual_lines);
			$line_item = array_unique($line_item);
		}

		if ( $method == 'tag' ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			unset($this->lines->current_line, $this->lines->item_hash);

			for ( $i = 0; $i < count($line_item); $i++ ) {

				if ( ! $this->line_items->fetch_line_item_record($line_item[$i]) )
                    return $this->__trigger_error("System error encountered during line item lookup. Unable to perform database lookup for line item edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

				switch ( $method_action ) {

					case 'rm': # Delete item

					if ( $this->line_items->item_hash && $this->p->ck(get_class($this), 'D', 'item_details') ) {

						if ( $this->line_items->current_line['status'] )
    						return $this->__trigger_error("One or more of your selected items could not be deleted because the item is booked and/or invoiced. Please check your input and try again. No changes have been made.", E_USER_NOTICE, __FILE__, __LINE__, 1);

                        $panel_id = $this->line_items->current_line['panel_id'];
                        $final_action = true;
                        $action = true;

                        if ( ! $this->db->query("DELETE t1.*, t2.*
                                                 FROM line_items t1
                                                 LEFT JOIN tax_collected t2 ON t2.item_hash = t1.item_hash
                                                 WHERE t1.item_hash = '{$this->line_items->item_hash}'")
                        ) {

                            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

                        unset($this->line_items->current_line, $this->line_items->item_hash);
                        $rm_line = 1;

                        if ( $panel_id ) {

                            $result = $this->db->query("SELECT COUNT(*) AS Total
                                                        FROM line_items
                                                        WHERE proposal_hash = '{$this->line_items->proposal_hash}' AND panel_id = '$panel_id'");
                            if ( ! $this->db->result($result) )
                                $this->drop_panel($this->line_items->proposal_hash, $panel_id);
						}
					}

					break;

					case 'inactive': # Flag item as inactive

					if ( $this->line_items->item_hash ) {

                        if ( $this->line_items->current_line['status'] )
                            return $this->__trigger_error("One or more of your selected items could not be flagged inactive because the item is booked and/or invoiced. Please check your input and try again. No changes have been made.", E_USER_NOTICE, __FILE__, __LINE__, 1);

                        $action = true;
                        $final_action = true;

                        if ( ! $this->db->query("UPDATE line_items t1
                                                 SET t1.active = IF( t1.active = '1', '0', '1')
                                                 WHERE item_hash = '{$this->line_items->item_hash}'")
                        ) {

                            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

                        unset($this->line_items->current_line, $this->line_items->item_hash);
					}

					break;
				}
			}

			if ( $rm_line )
				$this->reorder_lines($this->line_items->proposal_hash, $punch);

			if ( $final_action && $this->line_items->item_hash && $this->line_items->current_line['item_code'] )
				unset($final_action); # Default item added during finalization

			if ( $final_action )
				$this->unset_final($this->line_items->proposal_hash, $punch);

			unset($this->ajax_vars);

			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM line_items
										WHERE proposal_hash = '{$this->line_items->proposal_hash}'");
			if ( ! $this->db->result($result) ) {

				if ( ! $this->db->query("UPDATE proposals
        								 SET import_data = ''
        								 WHERE proposal_hash = '{$this->line_items->proposal_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
			}

			$this->db->end_transaction();

			$this->content['page_feedback'] = $jscript_action . ( $action ? 'Line items have been updated' : 'No line items selected' );
			$this->content['action'] = 'continue';

			if ( $punch )
				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : ( $sum ? "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'summarized', 'otf=1', 'proposal_hash={$this->line_items->proposal_hash}', 'punch=1');" : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash={$this->line_items->proposal_hash}');" ) );
			else
				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', '" . ( $sum && $this->line_items->total > 0 ? "summarized" : "line_items" ) . "', 'otf=1', 'proposal_hash={$this->line_items->proposal_hash}', 'limit=$limit');" );

			return;

		} else {

			if ( $action_delete || ( $_POST['item_vendor'] && $_POST['item_descr'] && $_POST['item_product_hash'] && $_POST['qty'] ) ) {

	            if ( ! $this->db->start_transaction() )
	                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( $action_delete ) {

					if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

						$final_action = true;

						if ( ! $this->db->query("DELETE t1.*, t2.*
												 FROM line_items t1
												 LEFT JOIN tax_collected t2 ON t2.item_hash = t1.item_hash
												 WHERE t1.item_hash = '{$this->line_items->item_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						$panel_id  = $this->line_items->current_line['panel_id'];
						unset($this->line_items->current_line, $this->line_items->item_hash);

						if ( $panel_id ) {

							$result = $this->db->query("SELECT COUNT(*) AS Total
														FROM line_items
														WHERE proposal_hash = '{$this->line_items->proposal_hash}' AND panel_id = '$panel_id'");
							if ( ! $this->db->result($result) )
								$this->drop_panel($this->line_items->proposal_hash, $panel_id);
						}

						if ( ! $this->reorder_lines($this->line_items->proposal_hash, $punch) )
    						return $this->__trigger_error("System error encountered when attempting to reindex line items. Please try your request again.", E_USER_ERROR, __FILE__, __LINE__, 1);

						$this->content['page_feedback'] = "Line item has been deleted.";

					} else
                        $this->content['page_feedback'] = "Line item could not be deleted because it has been booked and/or invoiced.";

				} else {

					if ( ! $_POST['item_vendor_hash'] || ( trim($_POST['item_ship_to']) && ! $_POST['item_ship_to_hash'] ) ) {

						if ( trim($_POST['item_ship_to']) && ! $_POST['item_ship_to_hash'] ) $this->content['form_return']['err']["err4{$this->popup_id}"] = 1;
						if ( ! $_POST['item_vendor_hash'] ) $this->content['form_return']['err']["err1{$this->popup_id}"] = 1;

						return $this->__trigger_error("We can't seem to find the " . ( ! $_POST['item_vendor_hash'] && trim($_POST['item_ship_to']) && ! $_POST['item_ship_to_hash'] ? "vendor or ship to location" : ( ! $_POST['item_vendor_hash'] ? "vendor" : "ship to location" ) ) . " you entered. Please make sure you are entering a valid " . ( ! $_POST['item_vendor_hash'] && ! $_POST['item_ship_to_hash'] ? "vendor and ship to location" : ( ! $_POST['item_vendor_hash'] ? "vendor" : "ship to location" ) ), E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					}

					$next_action = $_POST['next_action'];
					$item['work_order_hash'] = $work_order_hash = $_POST['work_order_import'];

					if ( $item['work_order_hash'] ) {

						if ( ! $this->line_items->item_hash ) {

							$result = $this->db->query("SELECT COUNT(*) AS Total
														FROM line_items
														WHERE proposal_hash = '{$this->line_items->proposal_hash}' AND work_order_hash = '{$item['work_order_hash']}'");
							if ( $this->db->result($result) )
								return $this->__trigger_error("A line already exists for the work order you are trying to import. Please make sure you are not creating a duplicate line item for this work order.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

						}

						$pm = new pm_module($this->line_items->proposal_hash);
						if ( ! $pm->fetch_work_order($item['work_order_hash']) )
                            return $this->__trigger_error("System error encountered during work order lookup. Unable to perform database lookup for work order edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

						$pm_ship_to = $pm->current_order['ship_to_hash'];
						unset($pm);
					}

					$item['vendor_hash'] = $_POST['item_vendor_hash'];
					$item['ship_to_hash'] = $_POST['item_ship_to_hash'];
					$item['product_hash'] = $_POST['item_product_hash'];
					$item['discount_hash'] = $_POST['discount_hash'];
					$item['discount_item_hash'] = $_POST['discount_item_hash'];
					$item['qty'] = preg_replace('/[^.0-9]/', "", $_POST['qty']);

					if ( bccomp($item['qty'], 0, 2) != 1 ) {

						$this->set_error("err7{$this->popup_id}");
                        return $this->__trigger_error("Please enter a valid quantity before continuing.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
    				}

					$item['item_no'] = trim($_POST['item_no']);
					$item['item_descr'] = trim( strip_tags($_POST['item_descr']) );
					$item['item_tag1'] = trim($_POST['item_tag1']);
					$item['item_tag2'] = trim($_POST['item_tag2']);
					$item['item_tag3'] = trim($_POST['item_tag3']);
					$item['discount1'] = preg_replace('/[^.0-9]/', "", $_POST['discount1']);
					$item['discount2'] = preg_replace('/[^.0-9]/', "", $_POST['discount2']);
					$item['discount3'] = preg_replace('/[^.0-9]/', "", $_POST['discount3']);
					$item['discount4'] = preg_replace('/[^.0-9]/', "", $_POST['discount4']);
					$item['discount5'] = preg_replace('/[^.0-9]/', "", $_POST['discount5']);
					$item['gp_margin'] = preg_replace('/[^.0-9]/', "", $_POST['gp_margin']);
					$item['import_complete'] = 1;
					$item['list_discount'] = preg_replace('/[^.0-9]/', "", $_POST['list_discount']);
					
					if ( $item['list_discount'] )
						$item['gp_type'] = 'L';
					else
						$item['gp_type'] = 'G';

					$item['list'] = preg_replace('/[^-.0-9]/', "", $_POST['list']);
					$item['cost'] = preg_replace('/[^-.0-9]/', "", $_POST['cost']);
					if(!$this->line_items->current_line['income_account_hash'])
						$item['sell'] = preg_replace('/[^-.0-9]/', "", $_POST['sell']);
					
					$itemno = $item['item_no'];
					$itemdesc = $item['item_descr'];
					$list = $item['list'];
					$vendor = $_POST['item_vendor'];
					

					if ( !$this->line_items->current_line['invoice_hash'] && (bccomp($item['discount1'], 100, 2) == 1 || bccomp($item['discount2'], 100, 2) == 1 || bccomp($item['discount3'], 100, 2) == 1 || bccomp($item['discount4'], 100, 2) == 1 || bccomp($item['discount5'], 100, 2) == 1 || bccomp($item['gp_margin'], 100, 2) == 1 )) {

						if ( bccomp($item['discount1'], 100, 2) == 1 || bccomp($item['discount2'], 100, 2) == 1 || bccomp($item['discount3'], 100, 2) == 1 || bccomp($item['discount4'], 100, 2) == 1 || bccomp($item['discount5'], 100, 2) == 1 ) $this->content['form_return']['err']["err9{$this->popup_id}"] = 1;
						if ( bccomp($item['gp_margin'], 100, 2) == 1 ) $this->content['form_return']['err']["err11{$this->popup_id}"] = 1;

						return $this->__trigger_error("Please check that you have entered your discounts and GP margin correctly. Discounts and GP margin should be entered a percentages not decimals. For example, a discount of 69.75% should be entered as 69.75, not .6975.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
					}

					$item['discount1'] = bcmul($item['discount1'], .01, 4);
					$item['discount2'] = bcmul($item['discount2'], .01, 4);
					$item['discount3'] = bcmul($item['discount3'], .01, 4);
					$item['discount4'] = bcmul($item['discount4'], .01, 4);
					$item['discount5'] = bcmul($item['discount5'], .01, 4);

					if ( $save_location = $_POST['save_location'] ) {

						if ( $save_location != 'N' ) {

							if ( $save_location == 'E' )
								$item['line_no'] = $this->line_items->total + 1;
							elseif ( $save_location == 'B' )
								$item['line_no'] = 1;
							else
								$item['line_no'] = ++$save_location;

							$result = $this->db->query("SELECT group_hash
														FROM line_items
														WHERE proposal_hash = '{$this->line_items->proposal_hash}' AND line_no = {$item['line_no']} AND punch = " . ( $punch ? '1' : '0' ) );
							if ( $row = $this->db->fetch_assoc($result) )
								$item['group_hash'] = $row['group_hash'];
							else
								$item['group_hash'] = '';

							if ( $save_location != 'E' ) {

								if ( ! $this->db->query("UPDATE line_items
        											     SET line_no = ( line_items.line_no + 1 )
    		          									 WHERE proposal_hash = '{$this->line_items->proposal_hash}' AND punch = " . ( $punch ? '1' : '0' ) . ( $save_location != 'B' ? " AND line_no >= '$save_location'" : NULL ) )
                                ) {

                                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                                }
							}
						}
					}

					if ( $this->line_items->item_hash ) {

						$item['import_complete'] = $this->line_items->current_line['import_complete'];

						if ( $this->line_items->current_line['po_hash'] && ( bccomp($item['cost'], $this->line_items->current_line['cost'], 2) || bccomp($item['qty'], $this->line_items->current_line['qty'], 2) ) ) {

							$r = $this->db->query("SELECT ROUND( SUM( t1.qty * t1.cost ), 2) AS ext_po_cost
												   FROM line_items t1
												   WHERE t1.proposal_hash = '{$this->line_items->proposal_hash}' AND t1.po_hash = '{$this->line_items->current_line['po_hash']}'");
							$li_cost = $this->db->result($r, 0, 'ext_po_cost');

                            $r = $this->db->query("SELECT ROUND( SUM( t1.time * t1.true_cost ), 2) AS ext_wo_cost
                                                   FROM work_order_items t1
                                                   WHERE t1.po_hash = '{$this->line_items->current_line['po_hash']}'");
                            $wo_cost = $this->db->result($r, 0, 'ext_wo_cost');

							$po_cost = bcadd($wo_cost, $li_cost, 2);
							$po_order_amount = bcadd( bcsub($po_cost, $this->line_items->current_line['ext_cost'], 2), _round( bcmul($item['cost'], $item['qty'], 4), 2), 2);

							if ( ! $this->db->query("UPDATE purchase_order t1
													 SET t1.order_amount = '$po_order_amount'
													 WHERE po_hash = '{$this->line_items->current_line['po_hash']}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $sub);
                            }
						}

						if ( $this->line_items->current_line['invoice_hash'] && bccomp($item['cost'], $this->line_items->current_line['cost'], 2) ) {

							$accounting = new accounting($this->current_hash);
							$accounting->start_transaction( array(
	                            'customer_hash'    =>  $invoice->current_invoice['customer_hash'],
	                            'ar_invoice_hash'  =>  $this->line_items->current_line['invoice_hash'],
	                            'proposal_hash'    =>  $this->line_items->current_line['proposal_hash'],
	                            'item_hash'        =>  $this->line_items->item_hash
							) );

							$audit_id = $accounting->audit_id();

			                $cost_adj = bcsub( _round( bcmul($item['cost'], $this->line_items->current_line['qty'], 4), 2), $this->line_items->current_line['ext_cost'], 2);

			                $invoice = new customer_invoice($this->current_hash);
			                if ( ! $invoice->fetch_invoice_record($this->line_items->current_line['invoice_hash']) )
			                    return $this->__trigger_error("System error encountered during invoice lookup. Unable to fetch customer invoice for edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

			                $vendors = new vendors($this->current_hash);
			                if ( ! $vendors->fetch_product_record($this->line_items->current_line['product_hash']) )
			                    return $this->__trigger_error("System error encountered during item product lookup. Unable to fetch item product for invoice edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                        if ( ! $accounting->exec_trans($audit_id, $invoice->current_invoice['invoice_date'], $vendors->current_product['product_expense_account'], $cost_adj, 'AD', "Line Item Adjustment : {$this->line_items->current_line['item_product']}" . ( $this->line_items->current_line['item_no'] ? "&nbsp;({$this->line_items->current_line['item_no']})" : NULL ) ) )
	                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                        if ( $invoice->current_invoice['direct_bill'] && $this->line_items->current_line['direct_bill_amt'] == 'C' ) {

	                            if ( ! $accounting->exec_trans($audit_id, $invoice->current_invoice['invoice_date'], DEFAULT_AR_ACCT, bcmul($cost_adj, -1, 2), 'AD', "Direct Invoice Adjustment (line item adjustment) : {$invoice->current_invoice['invoice_no']}") )
	                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $sub);
	                        }

	                        if ( ! $invoice->current_invoice['direct_bill'] || ( $invoice->current_invoice['direct_bill'] && $this->line_items->current_line['direct_bill_amt'] == 'S' ) ) {

	                            if ( ! $accounting->exec_trans($audit_id, $invoice->current_invoice['invoice_date'], DEFAULT_WIP_ACCT, bcmul($cost_adj, -1, 2), 'AD', "Line Item Adjustment : {$this->line_items->current_line['item_product']}" . ( $this->line_items->current_line['item_no'] ? "&nbsp;({$this->line_items->current_line['item_no']})" : NULL ) ) )
	                                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	                        }
						}

						$update_t = true;

						$item = $this->db->prepare_query($item, "line_items", 'UPDATE');
						if ( ! $this->db->query("UPDATE line_items t1
												 SET
		    										 t1.timestamp = UNIX_TIMESTAMP(),
		    										 last_change = '{$this->current_hash}',
		    										 " . implode(", ", $item) . "
												 WHERE item_hash = '{$this->line_items->item_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

                        $this->content['page_feedback'] = 'Your line item has been updated.';

					} else {

						$item_hash = rand_hash('item_hash_index', 'item_hash');
						if ( ! $this->db->query("INSERT INTO item_hash_index
        										 VALUES
        										 (
            										 '$item_hash'
            									 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

						if ( $punch ) {

							$item['punch'] = 1;
							$item['line_no'] = 10000;
						}
						if ( $copy_from ) {

							$r = $this->db->query("SELECT t1.import_data
												   FROM line_items t1
												   WHERE t1.item_hash = '$copy_from'");
							if ( $copy_data = $this->db->result($r, 0, 'import_data') ) {

								$item['import_data'] = addslashes( stripslashes($copy_data) );
							}
						}

						$this->content['page_feedback'] = "No changes have been made";

						$item = $this->db->prepare_query($item, "line_items", 'INSERT');
						if ( $item ) {
							
							if(defined('ACTIVATE_ITEM_LIBRARY') && ACTIVATE_ITEM_LIBRARY == 1){
								
								$r = $this->db->query("SELECT t1.item_number
														 FROM item_library t1
														 WHERE t1.item_number = '$itemno'
														 AND t1.item_desc = '$itemdesc'
														 AND t1.vendor_hash = '$vendor'");
								if ( !$this->db->result($r, 0, 'item_number') ) {
									
									if ( ! $this->db->query("INSERT INTO item_library
														(
															vendor_hash,
															item_number,
															item_desc,
															list_price
														)
														VALUES
														(
															'$vendor',
															'$itemno',
															'$itemdesc',
															'$list'
														)")
									) {
								
										return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
									  }
								}
							}
							
							if ( ! $this->db->query("INSERT INTO line_items
													 (
	    		    									 timestamp,
	    		    									 last_change,
	    		    									 item_hash,
	    		    									 proposal_hash,
	    		    									 " . implode(", ", array_keys($item) ) . "
			                        				 )
													 VALUES
													 (
	    		    									 UNIX_TIMESTAMP(),
	    		    									 '{$this->current_hash}',
	    		    									 '$item_hash',
	    		    									 '{$this->line_items->proposal_hash}',
        	    		        						 " . implode(", ", array_values($item) ) . "
			    									 )")
	                        ) {

	                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	                        }

                            $this->content['page_feedback'] = 'Line item has been saved';
						}
					}

					if ( $cost_adj ) {

		                if ( ! $accounting->end_transaction() )
                            return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                        $this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->line_items->proposal_hash}\', \'otf=1\');', 1500)";
					}
				}

				if ( ! $this->line_items->item_hash || ( $this->line_items->item_hash && ! $this->line_items->current_line['item_code'] ) ) {

					if ( ! $this->unset_final($this->line_items->proposal_hash, $punch) ) # Unfinalize if the item was not a system default
    					return $this->__trigger_error("System error encountered when attempting to remove proposal finalization flags. Please try your request again.", E_USER_ERROR, __FILE__, __LINE__, 1);
				}

                unset($this->line_items);

				$this->line_items = new line_items($proposal_hash, $punch);
                if ( ! $this->line_items->item_tax($item_hash) ) # Sales tax
                    return $this->__trigger_error("A system error was encountered during item sales tax update. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				if ( ! $this->line_items->total && ! $punch ) {

					$no_lines = 1;
					if ( ! $this->db->query("UPDATE proposals
											 SET import_data = ''
											 WHERE proposal_hash = '{$this->line_items->proposal_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

				} elseif ( $punch ) {

					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM line_items
												WHERE proposal_hash = '{$this->line_items->proposal_hash}' AND punch = 1");
					if ( ! $this->db->result($result) ) {

						$no_lines = 1;
						if ( ! $this->db->query("UPDATE proposals
        										 SET import_data = ''
        										 WHERE proposal_hash = '{$this->line_items->proposal_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }
					}
				}

				if ( ! $no_lines ) {

					if ( ! $this->reorder_lines($this->line_items->proposal_hash, $punch) )
    					return $this->__trigger_error("System error encountered when attempting to reindex line items. Please try your request again.", E_USER_ERROR, __FILE__, __LINE__, 1);
				}

				$this->db->end_transaction();

				unset($this->ajax_vars);
				$this->content['action'] = 'close';

				if ( $punch )
					$this->content['jscript_action'] = "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash={$this->line_items->proposal_hash}')";
				else
					$this->content['jscript_action'] =
					( $work_order_hash && $pm_ship_to ?
    					"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'update_work_order_ship_to', 'proposal_hash={$this->line_items->proposal_hash}', 'order_hash=$work_order_hash', 'popup_id=popup_" . rand(500, 5000) . "');" : NULL
					) .
					( $jscript_action ?
    					$jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_items', 'otf=1', 'proposal_hash={$this->line_items->proposal_hash}', 'limit=$limit', 'p=$p', 'order=$order', 'order_dir=$order_dir');"
    				);

				return true;

			} else {

				if ( ! $_POST['item_vendor'] ) $this->content['form_return']['err']["err1{$this->popup_id}"] = 1;
				if ( ! $_POST['item_descr'] ) $this->content['form_return']['err']["err3{$this->popup_id}"] = 1;
				if ( ! $_POST['item_product_hash'] ) $this->content['form_return']['err']["err6{$this->popup_id}"] = 1;
				if ( ! $_POST['qty'] ) $this->content['form_return']['err']["err7{$this->popup_id}"] = 1;

				return $this->__trigger_error("You left some required fields blank! Please check the indicated fields and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			}
		}
	}

	function update_work_order_ship_to() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['proposal_hash'] || !$this->ajax_vars['order_hash'])
			return;

		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$order_hash = $this->ajax_vars['order_hash'];

		$pm = new pm_module($proposal_hash);
		$pm->fetch_work_order($order_hash);

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Update Shipping Location";

		$result = $this->db->query("SELECT line_items.ship_to_hash
									FROM `line_items`
									WHERE line_items.proposal_hash = '$proposal_hash' AND (line_items.work_order_hash != '$order_hash' OR ISNULL(line_items.work_order_hash)) AND line_items.po_hash = ''
									GROUP BY line_items.ship_to_hash");
		while ($row = $this->db->fetch_assoc($result)) {
			if ($row['ship_to_hash']) {

				$Y = true;
				list($class, $id, $hash) = explode("|", $row['ship_to_hash']);
				$obj = new $class($this->current_hash);

				if ($hash) {

					$obj->fetch_location_record($hash);
					$ship_to = "{$obj->current_location['location_name']}\n" .
					( $obj->current_location['location_street'] ?
                        stripslashes($obj->current_location['location_street']) . "\n" : NULL
                    ) .
                    stripslashes($obj->current_location['location_city']) . ", {$obj->current_location['location_state']} {$obj->current_location['location_zip']}";

				} else {

					$obj->fetch_master_record($id);
					$ship_to = $obj->{ "current_" . strrev( substr( strrev($class), 1) ) }[strrev( substr( strrev($class), 1) ) . "_name"] . "\n" .
					( $obj->current_location['street'] ?
                        stripslashes($obj->current_location['street']) . "\n" : NULL
                    ) .
                    stripslashes($obj->current_location['city']) . ", {$obj->current_location['state']} {$obj->current_location['zip']}";
				}

				$ship_option .= "
				<div style=\"padding-top:15px;margin-left:25px;\">" .
					$this->form->checkbox(
    					"name=change_ship_to[]",
    					"value={$row['ship_to_hash']}",
    					"checked"
					) .
					"&nbsp;Line items shipping to $ship_to
				</div>";

				unset($obj);
			}
		}

		$tbl .=
		$this->form->form_tag() .
		$this->form->hidden( array(
    		'popup_id'        => $this->popup_id,
    		'proposal_hash'   => $proposal_hash,
    		'order_hash'      => $order_hash
		) ) . "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
			<tr>
				<td style=\"background-color:#ffffff;padding:15px 20px 25px 20px;\">
					<div style=\"padding-bottom:15px;\">
						The shipping location stored under the work order you just imported is ".$pm->current_order['ship_to'].". Any shipping locations
						found in the line items of your proposal are listed below. These locations are different from the shipping location found
						on this work order. To preserve any of the shipping location(s) presented below please uncheck the box to the left of the
						location(s). If left checked, the line items will inherit the shipping location of this imported work order.

							$ship_option

						<div style=\"padding:25px 25px 25px 0;\">" .
							$this->form->button(
    							"value=Update",
    							"onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_function', 'ship_to_hash={$pm->current_order['ship_to_hash']}', 'work_order_update=1');"
							) . "
						</div>
					</div>
				</td>
			</tr>
		</table>" .
        $this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function doit_line_function() {

		$proposal_hash = $_POST['proposal_hash'];
		$jscript_action = $_POST['jscript_action'];
		$continue_action = $_POST['continue_action'];
		$function = $_POST['function'];
		$line_item = $_POST['line_item'];
		$limit = $_POST['limit'];
		$sum = $_POST['sum'];
		$discount_hash = $_POST['func_discount_hash'];
		
		if ( $punch = $_POST['punch'] ) {

			$line_item = $_POST['punch_item'];
			$pm_module = new pm_module($proposal_hash);
		}

		$work_order_update = $_POST['work_order_update'];

		if ( ! $this->fetch_master_record($proposal_hash) )
    		return $this->__trigger_error("System error encountered during proposal lookup. Unable to fetch proposal for database update. Please reload proposal window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

		$this->line_items = new line_items($proposal_hash, $punch);
		if ( $function != 'discount_id' )
			$not_really_sum = 1;

		if ( $function == 'discount_id' ) {

			if ( ! $_POST['func_discount_hash'] )
				return $this->__trigger_error("Please select a discount to be applied to the vendor group.", E_USER_NOTICE, __FILE__, __LINE__, 1);

			$discounting = new discounting($_POST['item_vendor_hash'], 'v');
			$discounting->fetch_discount_record($discount_hash);

			list($item_hash, $product_hash) = $discounting->fetch_discount_items_short($discounting->discount_hash);

			$line_item[] = "vendor|{$_POST['item_vendor_hash']}";
			if ( $sum )
				$not_really_sum = 1;
			else
				$sum = 1;

			if ( $punch )
				$sum = $not_really_sum = 1;

		} elseif ( $function == 'smart_group' ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

			$tag = $_POST['smart_tag'];
			if ( is_array($tag) ) {

				for ( $i = 0; $i < count($tag); $i++ ) {

					if ( ! $this->line_items->fetch_line_item_record($tag[$i]) )
    					return $this->__trigger_error("System error encountered during line item lookup. Unable to fetch line item for database edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

					$r = $this->db->query("SELECT t1.group_hash
										   FROM line_groups t1
										   WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t1.group_descr = '" . addslashes($this->line_items->current_line['item_tag1']) . "'");
					$group_hash = $this->db->result($r, 0, 'group_hash');

					if ( ! $group_hash ) {

						$group_hash = rand_hash('line_groups', 'group_hash');
						if ( ! $this->db->query("INSERT INTO line_groups
        										 VALUES
        										 (
            										 NULL,
            										 UNIX_TIMESTAMP(),
            										 '{$this->current_hash}',
            										 '$group_hash',
            										 '{$proposal_hash}',
            										 '" . addslashes($this->line_items->current_line['item_tag1']) . "'
            									 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
					}

					$result = $this->db->query("SELECT item_hash
												FROM line_items
												WHERE proposal_hash = '{$this->line_items->proposal_hash}' AND item_tag1 = '" . addslashes($this->line_items->current_line['item_tag1']) . "' AND punch = " . ( $punch ? 1 : 0) );
					while ( $row = $this->db->fetch_assoc($result) )
						$add_to_group[ $group_hash ][] = $row['item_hash'];

					$update = true;
				}
			}

			if ( $add_to_group ) {

				while ( list($group_hash, $item_array) = each($add_to_group) ) {

					if ( ! $this->line_items->add_to_group($item_array, $group_hash) )
    					return $this->__trigger_error("System error encountered when grouping line items. Please try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
				}
			}

			$this->db->end_transaction();

			$this->content['action'] = 'close';
			if ($punch)
				$this->content['jscript_action'] = ( $sum && $not_really_sum ? "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'summarized', 'otf=1', 'proposal_hash=$proposal_hash', 'punch=1');" : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$proposal_hash');");
			else
				$this->content['jscript_action'] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', '" . ( $sum && $not_really_sum ? 'summarized' : 'line_items')."', 'otf=1', 'proposal_hash=$proposal_hash', 'limit=$limit');";

			$this->content['page_feedback'] = ( $update ? "Your line items have been grouped according to tag." : "No line items were found to group. This may be due to none of the line items having a value set for Tag1." );

			return;

		}

		if ( $sum ) {

			$actual_lines = array();
			for ( $i = 0; $i < count($line_item); $i++ ) {

				if ( $line_item[$i] ) {

					list($type, $id, $vendor_id) = explode("|", urldecode($line_item[$i]));

					if (!$id) # Trac #1513
						$no_vendor = true;

					switch ( $type ) {

						case 'vendor':

						$result = $this->db->query("SELECT item_hash
													FROM line_items
													WHERE proposal_hash = '$proposal_hash' AND vendor_hash = '" . ( $id ? $id : "0" ) . "' AND punch = " . ( $punch ? 1 : 0 ) );
						while ( $row = $this->db->fetch_assoc($result) )
							array_push($actual_lines, $row['item_hash']);

						break;

						case 'prod':

						$result = $this->db->query("SELECT item_hash
													FROM line_items
													WHERE proposal_hash = '$proposal_hash' AND product_hash = '$id' AND vendor_hash = '$vendor_id' AND punch = " . ( $punch ? 1 : 0 ) );
						while ( $row = $this->db->fetch_assoc($result) )
							array_push($actual_lines, $row['item_hash']);

						break;
					}
				}
			}

			$line_item = array_values($actual_lines);
			$line_item = array_unique($line_item);

		} elseif ( $work_order_update ) {

			$change_ship_to = $_POST['change_ship_to'];
			$ship_to_hash = $_POST['func_ship_to_hash'] = $_POST['ship_to_hash'];
			$_POST['func_ship_to'] = 'nothing';
			$function = "shipto";
			$continue_action = "close";
			$line_item = array();

			if ( $change_ship_to ) {

				for ( $i = 0; $i < count($change_ship_to); $i++ ) {

					if ( $change_ship_to[$i] ) {

						$result = $this->db->query("SELECT item_hash
													FROM line_items
													WHERE proposal_hash = '$proposal_hash' AND status = 0 AND ship_to_hash = '{$change_ship_to[$i]}' AND punch = " . ( $punch ? 1 : 0 ) );
						while ( $row = $this->db->fetch_assoc($result) ) {

							if ( ! in_array($row['item_hash'], $line_item) )
								array_push($line_item, $row['item_hash']);
						}
					}
				}

			} else {

				$result = $this->db->query("SELECT item_hash
											FROM line_items
											WHERE proposal_hash = '$proposal_hash' AND status = 0 AND punch = " . ( $punch ? 1 : 0 ) );
				while ( $row = $this->db->fetch_assoc($result) )
					array_push($line_item, $row['item_hash']);

			}

			$action = true;
			list($tmp_class, $tmp_id, $tmp_hash) = explode("|", $ship_to_hash);

			$tmp_obj = new $tmp_class($this->current_hash);
			if ( $tmp_hash ) {

				$tmp_obj->fetch_location_record($tmp_hash);
				$this->content['value']['ship_to'] = $tmp_obj->current_location['location_name'];
			} else {

				$tmp_obj->fetch_master_record($tmp_id);
				$this->content['value']['ship_to'] = $tmp_obj->{"current_" . strrev( substr( strrev($tmp_class), 1) )}[strrev( substr( strrev($tmp_class), 1) ) . "_name"];
			}

			$this->content['value']['ship_to_hash'] = $ship_to_hash;
			$this->content['html']['ship_to_holder'] = $this->innerHTML_customer($ship_to_hash, 'ship_to', 'ship_to_holder', $ship_to_hash);
			unset($tmp_obj);
		}

        if ( ! $this->db->start_transaction() )
            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
        
        
		$totalCost = 0;
		$totalList = 0;
		$totalSell = 0;
		
		for ( $i = 0; $i < count($line_item); $i++ ) {

			if ( ! $this->line_items->fetch_line_item_record($line_item[$i]) )
                return $this->__trigger_error("System error during line item lookup. Unable to fetch line item for database edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

			switch ( $function ) {

				case 'discounting':

				if ( ( $_POST['discount1'] && bccomp($_POST['discount1'], 0, 2) == -1 ) || ( $_POST['discount2'] && bccomp($_POST['discount2'], 0, 2) == -1 ) || ( $_POST['discount3'] && bccomp($_POST['discount3'], 0, 2) == -1 ) || ( $_POST['discount4'] && bccomp($_POST['discount4'], 0, 2) == -1 ) || ( $_POST['discount5'] && bccomp($_POST['discount5'], 0, 2) == -1 ) ) {

					$this->content['form_return']['err']["err1{$this->popup_id}"] = 1;
					return $this->__trigger_error("Please check that you have entered your discounts correctly. Discounts should be entered a percentages not decimals. For example, a discount of 69.75% should be entered as 69.75, not .6975.", E_USER_ERROR, __FILE__, __LINE__, 1);
				}

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					$action = true;
					$item = array();

					if ( $this->line_items->current_line['item_code'] )
						$feedback = "One or more of you line items could not be updated because it was automatically created during finalization.";
					else {

						$return_val = $this->validate_line_item( array(
                            'local'     =>  1,
						    'qty'       =>  $this->line_items->current_line['qty'],
                            'list'      =>  $this->line_items->current_line['list'],
						    'discount1' =>  $_POST['discount1'],
							'discount2' =>  $_POST['discount2'],
							'discount3' =>  $_POST['discount3'],
							'discount4' =>  $_POST['discount4'],
							'discount5' =>  $_POST['discount5'],
						) );

						$item['discount1'] = bcmul($_POST['discount1'], .01, 4);
						$item['discount2'] = bcmul($_POST['discount2'], .01, 4);
						$item['discount3'] = bcmul($_POST['discount3'], .01, 4);
						$item['discount4'] = bcmul($_POST['discount4'], .01, 4);
						$item['discount5'] = bcmul($_POST['discount5'], .01, 4);
						$item['cost'] = $return_val['cost'];

						$item = $this->db->prepare_query($item, "line_items", 'UPDATE');
						if ( ! $this->db->query("UPDATE line_items
        										 SET
            										 timestamp = UNIX_TIMESTAMP(),
            										 last_change = '{$this->current_hash}',
            										 " . implode(", ", $item) . "
        										 WHERE item_hash = '{$this->line_items->item_hash}'")
						) {

							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
						}

						$unset_final = true;
					}
				}

				break;

				case 'discount_id':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					if ( $discount_hash == '_' ) {

						if ( ! $this->db->query("UPDATE line_items
												 SET
    												 timestamp = UNIX_TIMESTAMP(),
    												 last_change = '{$this->current_hash}',
    												 discount_hash = '_',
    												 discount_item_hash = '',
    												 gp_type = 'G'
												 WHERE item_hash = '{$this->line_items->item_hash}'")
						) {

							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
						}

						$action = true;

						if ( $_POST['clear_discounts'] ) { # Trac #1374

							$item['discount1'] = $item['discount2'] = $item['discount3'] = $item['discount4'] = $item['discount5'] = 0;
							$item['cost'] = $item['sell'] = 0;
							$item['gp_type'] = 'G';

							$item = $this->db->prepare_query($item, "line_items", 'UPDATE');
							if ( ! $qr = $this->db->query("UPDATE line_items
       													   SET
           													   timestamp = UNIX_TIMESTAMP(),
           													   last_change = '{$this->current_hash}',
           													   " . implode(", ", $item) . "
													  	   WHERE item_hash = '{$this->line_items->item_hash}'")
							) {

								return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
							}
						}

					} else {

						if ( $discounting->total_discounts ) {

							if ( in_array($this->line_items->current_line['product_hash'], $product_hash) || in_array("*", $product_hash) ) {

								$action = $unset_final = true;

    							if ( ! $discounting->fetch_item_record( $item_hash[ array_search($this->line_items->current_line['product_hash'], $product_hash) ] ) )
                                    return $this->__trigger_error("System error during discount item lookup. Unable to fetch discount item for database update. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

                                $gp_margin = $list_discount = 0;

								if ( $discounting->current_item['discount_type'] == 'F' ) {

									if ( bccomp($discounting->current_item['gp_margin'], 0, 4) == 1 ) {

										$item['gp_type'] = 'G';
										$gp_margin = (float)bcmul($discounting->current_item['gp_margin'], 100, 4);

									} elseif ( bccomp($discounting->current_item['sell_discount'], 0, 4) == 1 ) {

										$item['gp_type'] = 'L';
										$list_discount = (float)bcmul($discounting->current_item['sell_discount'], 100, 4);
									}

									$values = $this->validate_line_item( array(
                                        'local'           =>  1,
                                        'qty'             =>  $this->line_items->current_line['qty'],
                                        'list'            =>  $this->line_items->current_line['list'],
                                        'discount1'       =>  bcmul($discounting->current_item['buy_discount1'], 100, 4),
										'discount2'       =>  bcmul($discounting->current_item['buy_discount2'], 100, 4),
										'discount3'       =>  bcmul($discounting->current_item['buy_discount3'], 100, 4),
										'discount4'       =>  bcmul($discounting->current_item['buy_discount4'], 100, 4),
										'discount5'       =>  bcmul($discounting->current_item['buy_discount5'], 100, 4),
									    'gp_margin'       =>  ( $gp_margin && ! $list_discount ? $gp_margin : NULL ),
									    'list_discount'   =>  $list_discount
									) );

									$item['discount1'] = $discounting->current_item['buy_discount1'];
									$item['discount2'] = $discounting->current_item['buy_discount2'];
									$item['discount3'] = $discounting->current_item['buy_discount3'];
									$item['discount4'] = $discounting->current_item['buy_discount4'];
									$item['discount5'] = $discounting->current_item['buy_discount5'];
                                    $item['gp_margin'] = bcmul($gp_margin, .01, 4);
                                    $item['list_discount'] = bcmul($list_discount, .01, 4);
									$item['cost'] = $values['cost'];

									if ( ! $item['gp_type'] )
        								$item['gp_type'] = 'G';

                                    if ( bccomp($values['sell'], 0, 2) || bccomp($item['gp_margin'], 0, 4) || bccomp($item['list_discount'], 0, 4) )
                                        $item['sell'] = $values['sell'];

									$item['discount_hash'] = $discounting->discount_hash;
									$item['discount_item_hash'] = $discounting->item_hash;

									$item = $this->db->prepare_query($item, "line_items", 'UPDATE');
									if ( ! $this->db->query("UPDATE line_items
		        										     SET
    		            										 timestamp = UNIX_TIMESTAMP(),
		            											 last_change = '{$this->current_hash}',
		            											 " . implode(", ", $item) . "
		        											 WHERE item_hash = '{$this->line_items->item_hash}'")
                                    ) {

                                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                    }
								} else {

									if ( ! $this->db->query("UPDATE line_items
															 SET
    															 timestamp = UNIX_TIMESTAMP(),
    															 last_change = '{$this->current_hash}',
    															 discount_hash = '{$discounting->discount_hash}',
    															 discount_item_hash = '{$discounting->item_hash}'
															 WHERE item_hash = '{$this->line_items->item_hash}'")
									) {

										return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
									}
								}

							} else
								$not_in_discount = true;

						} else
                            $no_discounts = true;
					}
				}

				if ( ! $action && ( $not_in_discount || $no_discounts ) )
					$feedback = ( $not_in_discount ? "One or more line items could not be applied to the selected discount because those item's product lines are not found within the selected discount." : "The discount could not be applied because there are no product discounts listed under the selected discount." );

				if ( $not_really_sum )
					unset($sum);

				break;

				case 'gp':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					$action = true;
					$item = array();
					if ( bccomp($_POST['gp_margin'], 100, 2) == 1 || ( $_POST['gp_margin'] && bccomp($_POST['gp_margin'], 1, 2) == -1 ) || ( $_POST['list_discount'] && ( bccomp($_POST['list_discount'], 100, 2) == 1 || bccomp($_POST['list_discount'], 1, 2) == -1 ) ) ) {

						if ( $_POST['gp_margin'] && ( bccomp($_POST['gp_margin'], 100, 2) == 1 || bccomp($_POST['gp_margin'], 1, 2) == -1 ) ) $this->set_error("err1{$this->popup_id}");
						if ( $_POST['list_discount'] && ( bccomp($_POST['list_discount'], 100, 2) == 1 || bccomp($_POST['list_discount'], 1, 2) == -1 ) ) $this->set_error("err2{$this->popup_id}");

						return $this->__trigger_error("Please check that you have entered your discounts and GP margin correctly. Discounts and GP margin should be entered a percentages not decimals. For example, a discount of 69.75% should be entered as 69.75, not .6975.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

					if ( $this->line_items->current_line['item_code'] )
                        $feedback = "One or more of you line items could not be updated because it was automatically created during finalization.";
					else {

						$gp_margin = preg_replace('/[^-.0-9]/', "", $_POST['gp_margin']);
						$list_discount = preg_replace('/[^-.0-9]/', "", $_POST['list_discount']);

						if ( $list_discount )
							$item['gp_type'] = 'L';
						else
							$item['gp_type'] = 'G';

						$return_val = $this->validate_line_item( array(
                            'local'         =>  1,
						    'qty'           =>  $this->line_items->current_line['qty'],
						    'list'          =>  $this->line_items->current_line['list'],
						    'cost'          =>  $this->line_items->current_line['cost'],
                            'gp_margin'     =>  $gp_margin,
						    'list_discount' =>  $list_discount,
						    'discount1'     =>  bcmul($this->line_items->current_line['discount1'], 100, 4),
						    'discount2'     =>  bcmul($this->line_items->current_line['discount2'], 100, 4),
						    'discount3'     =>  bcmul($this->line_items->current_line['discount3'], 100, 4),
						    'discount4'     =>  bcmul($this->line_items->current_line['discount4'], 100, 4),
						    'discount5'     =>  bcmul($this->line_items->current_line['discount5'], 100, 4)
						) );

						$item['sell'] = $return_val['sell'];

						$item = $this->db->prepare_query($item, "line_items", 'UPDATE');
						if ( ! $this->db->query("UPDATE line_items
												 SET
    	    										 timestamp = UNIX_TIMESTAMP(),
	    											 last_change = '{$this->current_hash}',
	    											 " . implode(", ", $item) . "
												 WHERE item_hash = '{$this->line_items->item_hash}'")
						) {

							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
						}

						$unset_final = true;
					}
				}

				break;

				case 'roundup':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					$action = true;

					if ( $this->line_items->current_line['item_code'] )
                        $feedback = "One or more of you line items could not be updated because it was automatically created during finalization.";
					else {

						if ( ! $this->db->query("UPDATE line_items
												 SET
                                                     timestamp = UNIX_TIMESTAMP(),
                                                     last_change = '{$this->current_hash}',
    												 sell = '" . ceil($this->line_items->current_line['sell']) . "'
												 WHERE item_hash = '{$this->line_items->item_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$unset_final = true;
					}
				}

				break;

				case 'rounddown':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					$action = true;

					if ( $this->line_items->current_line['item_code'] )
                        $feedback = "One or more of you line items could not be updated because it was automatically created during finalization.";
					else {

						if ( ! $this->db->query("UPDATE line_items
        										 SET
                                                     timestamp = UNIX_TIMESTAMP(),
                                                     last_change = '{$this->current_hash}',
            										 sell = '" . floor($this->line_items->current_line['sell']) . "'
        										 WHERE item_hash = '{$this->line_items->item_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$unset_final = true;
					}
				}

				break;

				case 'zerosell':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					$action = true;

					if ( $this->line_items->current_line['item_code'] )
                        $feedback = "One or more of you line items could not be updated because it was automatically created during finalization.";
					else {

						if ( ! $this->db->query("UPDATE line_items
        										 SET
                                                     timestamp = UNIX_TIMESTAMP(),
                                                     last_change = '{$this->current_hash}',
            										 sell = 0
        										 WHERE item_hash = '{$this->line_items->item_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$unset_final = true;
					}
				}

				break;

				case 'zerocost':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					$action = true;

					if ( $this->line_items->current_line['item_code'] )
                        $feedback = "One or more of you line items could not be updated because it was automatically created during finalization.";
					else {

						if ( ! $this->db->query("UPDATE line_items
        										 SET
                                                     timestamp = UNIX_TIMESTAMP(),
                                                     last_change = '{$this->current_hash}',
            										 cost = 0
        										 WHERE item_hash = '{$this->line_items->item_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$unset_final = true;

					}
				}

				break;

				case 'list':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					$action = true;
					$item = array();

					if ( bccomp($_POST['list_adjust'], 100, 2) != -1 || bccomp($_POST['list_adjust'], -100, 2) != 1 ) {

						$this->content['form_return']['err']["err1{$this->popup_id}"] = 1;
						return $this->__trigger_error("You entered an incorrect value for your list adjustment. To increase your list price by 10%, simply enter 10. To decrease, enter -10.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

					if ( $this->line_items->current_line['item_code'] )
                        $feedback = "One or more of you line items could not be updated because it was automatically created during finalization.";
					else {

						if ( bccomp($_POST['list_adjust'], 0, 2) == -1 ) {

							$neg = true;
							$_POST['list_adjust'] = bcmul($_POST['list_adjust'], -1, 4);
						}

						$adj_amount = _round( bcmul($this->line_items->current_line['list'], bcmul($_POST['list_adjust'], .01, 4), 4), 2);

						if ( $neg )
							$item['list'] = bcsub($this->line_items->current_line['list'], $adj_amount, 2);
						else
							$item['list'] = bcadd($this->line_items->current_line['list'], $adj_amount, 2);

						if ( $this->line_items->current_line['gp_type'] == 'G' ) {

							$gp_margin = math::gp_margin( array(
    							'cost'   =>  $this->line_items->current_line['cost'],
    							'sell'   =>  $this->line_items->current_line['sell']
							) );
						} else {

							$list_discount = math::list_discount( array(
								'list'   =>  $this->line_items->current_line['list'],
								'sell'   =>  $this->line_items->current_line['sell']
							) );
						}


						$return_val = $this->validate_line_item( array(
                            'local'         =>  1,
						    'list'          =>  $item['list'],
                            'discount1'     =>  bcmul($this->line_items->current_line['discount1'], 100, 4),
							'discount2'     =>  bcmul($this->line_items->current_line['discount2'], 100, 4),
							'discount3'     =>  bcmul($this->line_items->current_line['discount3'], 100, 4),
							'discount4'     =>  bcmul($this->line_items->current_line['discount4'], 100, 4),
							'discount5'     =>  bcmul($this->line_items->current_line['discount5'], 100, 4),
                            'gp_margin'     =>  $gp_margin,
						    'list_discount' =>  $list_discount
						) );

						$item['cost'] = $return_val['cost'];
						$item['sell'] = $return_val['sell'];
						$item['gp_type'] = $this->line_items->current_line['gp_type'];

						$item = $this->db->prepare_query($item, "line_items", 'UPDATE');
						if ( ! $this->db->query("UPDATE line_items
        										 SET
            										 timestamp = UNIX_TIMESTAMP(),
            										 last_change = '{$this->current_hash}',
            										 " . implode(", ", $item) . "
        										 WHERE item_hash = '{$this->line_items->item_hash}'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$unset_final = true;

					}
				}

				break;

				case 'products':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					if ( $_POST['func_vendor'] ) {

						$func_vendor = $_POST['func_vendor'];
						$func_vendor_hash = $_POST['func_vendor_hash'];
						$func_product_hash = $_POST['func_product_hash'];

						if ( ! $func_vendor_hash ) {

							$this->content['form_return']['err']["err1{$this->popup_id}"] = 1;
							return $this->__trigger_error("We can't seem to find the vendor you entered. If you're trying to create a new vendor please do so in the vendors section.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

						$action = true;

						if ( ( $this->line_items->current_line['item_code'] || $this->line_items->current_line['import_data'] ) && ! $no_vendor ){
	                        $feedback = "Changing line items that were imported or created during finalization may cause problems with your order in the Vendors order entry process. By making this change you assume all responsiblity.";
	                        
	                        if ( ! $this->db->query("UPDATE line_items
	                        		SET
	                        		timestamp = UNIX_TIMESTAMP(),
	                        		vendor_hash = '$func_vendor_hash',
	                        		product_hash = '$func_product_hash'
	                        		WHERE item_hash = '{$this->line_items->item_hash}'")
	                        		) {
	                        
	                        		return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
	                        }
	                        
	                        $unset_final = true;
	                        
						}else {

							if ( ! $this->db->query("UPDATE line_items
        											 SET
            											 timestamp = UNIX_TIMESTAMP(),
            											 vendor_hash = '$func_vendor_hash',
            											 product_hash = '$func_product_hash'
        											 WHERE item_hash = '{$this->line_items->item_hash}'")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }

							$unset_final = true;

						}

					} else {

						$this->content['form_return']['err']["err1{$this->popup_id}"] = 1;
						return $this->__trigger_error("Please check to make sure you completed the indicated fields and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}
				}

				break;

				case 'shipto':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					$action = true;
					$item = array();

					if ( ! $_POST['func_ship_to'] ) {

						$this->content['form_return']['err']["err1{$this->popup_id}"] = 1;
						return $this->__trigger_error("Please select a shipping location to continue. If you are trying to create a new shipping location, please navigate to customers, select you customer and add a new location under the locations tab.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					} elseif ( $_POST['func_ship_to'] && ! $_POST['func_ship_to_hash'] ) {

						$this->content['form_return']['err']["err1{$this->popup_id}"] = 1;
						return $this->__trigger_error("We can't seem to find the shipping location you entered. If you are trying to create a new shipping location, please navigate to customers, select you customer and add a new location under the locations tab.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

					$ship_to = $_POST['func_ship_to_hash'];
					if ( ! $this->db->query("UPDATE line_items
        									 SET
            									 timestamp = UNIX_TIMESTAMP(),
            									 last_change = '{$this->current_hash}',
            									 ship_to_hash = '$ship_to'
        									 WHERE item_hash = '{$this->line_items->item_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
				}

				break;

				case 'tagging':

				if ( $this->line_items->item_hash && ! $this->line_items->current_line['status'] ) {

					if ( $_POST['item_tag1'] )
						$item_tag1 = trim($_POST['item_tag1']);
					if ( $_POST['item_tag2'] )
						$item_tag2 = trim($_POST['item_tag2']);
					if ( $_POST['item_tag3'] )
						$item_tag3 = trim($_POST['item_tag3']);

					if ( $this->line_items->current_line['item_code'] )
                        $feedback = "One or more of you line items could not be updated because it was automatically created during finalization.";
					else {

						if ( $item_tag1 || $item_tag2 || $item_tag3 ) {

							$action = true;

							if ( ! $this->db->query("UPDATE line_items
        										     SET
            										     timestamp = UNIX_TIMESTAMP(),
            										     last_change = '{$this->current_hash}' " .
            										     ( $item_tag1 ?
                										     ",
                										     item_tag1 = '" . addslashes($item_tag1) . "'" : NULL
            										     ) .
            										     ( $item_tag2 ?
                										     ",
                										     item_tag2 = '" . addslashes($item_tag2) . "'" : NULL
            										     ) .
            										     ( $item_tag3 ?
                										     ",
                										     item_tag3 = '" . addslashes($item_tag3) . "'" : NULL
            										     ) . "
    											     WHERE item_hash = '{$this->line_items->item_hash}'")
							) {

								return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
							}

							$unset_final = true;

						} else
    						$feedback = "No values were entered in any of the tag fields!";
					}
				}

				break;
				
				case 'proposal_fee':
					
					$totalCost += $this->line_items->current_line['ext_cost'];
					$totalList += $this->line_items->current_line['ext_list'];
					$totalSell += $this->line_items->current_line['ext_sell'];
					$item['line_no'] = $this->line_items->total + 1;
		
				break;
			}
		}
		
		if($function == "proposal_fee"){
			$item['vendor_hash'] = $_POST['item_vendor_hash'];
			$item['ship_to_hash'] = $_POST['item_ship_to_hash'];
			$item['product_hash'] = $_POST['item_product_hash'];
			$item['item_no'] = trim($_POST['item_no']);
			$item['item_descr'] = trim( strip_tags($_POST['item_descr']) );
			$item['item_tag1'] = trim($_POST['item_tag1']);
			$item['item_tag2'] = trim($_POST['item_tag2']);
			$item['item_tag3'] = trim($_POST['item_tag3']);
				
			//here
			$item['prop_amount'] = trim($_POST['prop_amount']);
			$item['prop_amount_add_type'] = trim($_POST['prop_amount_add_type']);
			$item['prop_amount_type'] = trim($_POST['prop_amount_type']);
			$item['gp_margin'] = preg_replace('/[^.0-9]/', "", $_POST['gp_margin']);
			$item['list_discount'] = preg_replace('/[^.0-9]/', "", $_POST['list_discount']);
			$item['qty'] = 1;
				
			if($item['prop_amount_add_type'] == "D"){
				$item['cost'] = $item['prop_amount'];
			}else{
				$item['prop_amount'] = $item['prop_amount'] / 100; 
				if($item['prop_amount_type'] == "L"){
					$item['cost'] = $item['prop_amount'] * $totalList;
				}else if($item['prop_amount_type'] == "C"){
					$item['cost'] = $item['prop_amount'] * $totalCost;
				}else if($item['prop_amount_type'] == "S"){
					$item['cost'] = $item['prop_amount'] * $totalSell;
				}
			}
			
			if($item['gp_margin']){
				$item['gp_margin'] = $item['gp_margin'] / 100;
				$item['sell'] = ($item['cost'] /(1 - $item['gp_margin']));
			}else if($item['list_discount']){
				$item['list_discount'] = $item['list_discount'] / 100;
				$item['sell'] = ($totalList * (1 - $item['list_discount']));
			}
			//return $this->__trigger_error($item['list_discount'], E_USER_ERROR , __FILE__, __LINE__, 1, false, $submit_btn);
				
			$item_hash = rand_hash('item_hash_index', 'item_hash');
			if ( ! $this->db->query("INSERT INTO item_hash_index
					VALUES
					(
					'$item_hash'
			)")
			) {
						
			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
			}
				
				
			$item = $this->db->prepare_query($item, "line_items", 'INSERT');
			if ( $item ) {
				$action = true;
					
				if ( ! $this->db->query("INSERT INTO line_items
				(
														timestamp,
														last_change,
														item_hash,
	    		    									proposal_hash,
	    		    									" . implode(", ", array_keys($item) ) . "
			                        				 )
													 VALUES
													(
						UNIX_TIMESTAMP(),
						'{$this->current_hash}',
						'$item_hash',
						'{$this->line_items->proposal_hash}',
						" . implode(", ", array_values($item) ) . "
				)")
				) {
					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
				}
			
			}
		}

		if ( $unset_final ) {

			if ( ! $this->unset_final($proposal_hash, $punch) )
                return $this->__trigger_error("System error encountered when attempting to unset proposal finalization flags. Please try your request again.", E_USER_ERROR, __FILE__, __LINE__, 1);
		}

		$this->db->end_transaction();

		unset($this->ajax_vars, $this->current_proposal, $this->line_items);

		if (count($line_item) && !$action)
		    $this->content['page_feedback'] = ($feedback ? $feedback : "The line item".(count($line_item) > 1 ? "s" : NULL)." you selected cannot be updated because they have already been booked and/or invoiced.");
		else
		    $this->content['page_feedback'] = ($action || $feedback ? ($feedback ? $feedback : 'Line items have been updated.') : 'You selected no line items. No changes have been made.');
		$this->content['action'] = ($continue_action ? $continue_action : "close");
		if ($punch)
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : ($sum && $not_really_sum ? "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'summarized', 'otf=1', 'proposal_hash=$proposal_hash', 'punch=1');" : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$proposal_hash');"));
		else
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', '".($sum && $not_really_sum ? 'summarized' : 'line_items')."', 'otf=1', 'proposal_hash=$proposal_hash');");
	}

	function doit_final() {

		$proposal_hash = $_POST['proposal_hash'];
		$limit = $_POST['limit'];
		$sumarized = $_POST['sum'];
		$punch = $_POST['punch'];
		$popup_id = $_POST['popup_id'];

		$lines = new line_items($proposal_hash, $punch);
		$disc_item = $_POST['discount_item_hash'];
		$k = 0;

		# Discounting items
		for ( $i = 0; $i < count($disc_item); $i++ ) {

			$lines->fetch_line_item_record($disc_item[$i]);

			if ( $_POST["{$disc_item[$i]}_discount_hash"])
				$upd_item[] = "`discount_hash` = '" . $_POST["{$disc_item[$i]}_discount_hash"] . "'";

			if ( $_POST["{$disc_item[$i]}_list_discount"] ) {

				$_POST["{$disc_item[$i]}_list_discount"] = bcmul($_POST["{$disc_item[$i]}_list_discount"], 1, 4);
				if ( $_POST["{$disc_item[$i]}_list_discount"] === 0 )
					$gp_type = 'G';
				else {

					$gp_type = 'L';
					$upd_item[] = "`gp_type` = 'L'";
					$upd_item[] = "`sell` = '" . math::list_discount( array(
						'list' =>  $lines->current_line['list'],
						'disc' =>  $_POST["{$disc_item[$i]}_list_discount"]
					) ) . "'";
				}
			}

			for ( $j = 1; $j < 6; $j++ ) {

				if ( $_POST["{$disc_item[$i]}_discount{$j}"] ) {

					$_POST["{$disc_item[$i]}_discount{$j}"] = bcmul($_POST["{$disc_item[$i]}_discount{$j}"], 1, 4);
					$upd_item[] = "`discount{$j}` = '" . bcmul($_POST["{$disc_item[$i]}_discount{$j}"], .01, 4) . "'";
					$disc_update = true;
				}
			}

			if ( $upd_item ) {

				if ( $disc_update ) {

					for ( $j = 1; $j < 6; $j++ )
						$discount_{$j} = $_POST["{$disc_item[$i]}_discount{$j}"];

					$item_cost = $this->validate_line_item( array(
                        'local'         =>  1,
                        'qty'           =>  $lines->current_line['qty'],
                        'list'          =>  $lines->current_line['list'],
                        'gp_margin'     =>  (float)bcmul( math::gp_margin( array(
        					'cost' =>  $lines->current_line['cost'],
        					'sell' =>  $lines->current_line['sell']
    					) ), 100, 4),
                        'discount1'     =>  $discount_{1},
                        'discount2'     =>  $discount_{2},
                        'discount3'     =>  $discount_{3},
                        'discount4'     =>  $discount_{4},
                        'discount5'     =>  $discount_{5},
					) );

					$upd_item[] = "`cost` = '{$item_cost['cost']}'";

					if ( $gp_type != 'L' ) {

						$upd_item[] = "`gp_type` = 'G'";
						if ( $_POST['gp_margin'] )
							$upd_item[] = "`sell` = '" . math::gp_margin( array(
								'cost'   =>  $item_cost['cost'],
								'margin' =>  $_POST['gp_margin']
							) ) . "'";

					}
				}

				$update_item[$k] = $upd_item;
				$update_item_hash[$k] = $disc_item[$i];
				$k++;
			}

			unset($upd_item);
		}

		//Tiered Discounts Next
		if ( is_array($_POST['tiered_disc']) ) {

			while ( list($rand_id, $product_hash) = each($_POST['tiered_disc']) ) {

				if ( $product_hash && $_POST["tier_apply_{$product_hash}"] )
					$tier[ $product_hash ][] = $rand_id;
			}
		}

		$k = 0;
		if ( is_array($tier) ) {

			$line_items = $lines->fetch_line_items_short();

			while ( list($product_hash, $tier_array) = each($tier) ) {

				for ( $i = 0; $i < count($tier_array); $i++ ) {

                    unset($_POST['discount1'], $sell_disc, $gp_margin, $_POST['list_discount'], $_POST['gp_margin']);

					$vendor_hash = $_POST["vendor_hash_{$tier_array[$i]}"];
					$discount_hash = $_POST["disc_hash_{$tier_array[$i]}"];
					$discount_item_hash = $_POST["discount_item_hash_{$tier_array[$i]}"];
					$discount1 = $_POST["discount1_{$tier_array[$i]}"];
					$sell_disc = $_POST["sell_disc_{$tier_array[$i]}"];
					$install_sell = $_POST["install_sell_{$tier_array[$i]}"];
					$gp_margin = $_POST["gp_margin_{$tier_array[$i]}"];

					for ( $j = 0; $j < count($line_items); $j++ ) {

						if ( $line_items[$j]['vendor_hash'] == $vendor_hash && ( $line_items[$j]['product_hash'] == $product_hash || ( $product_hash == '*' && ! array_key_exists($line_items[$j]['product_hash'], $tier) ) ) && $line_items[$j]['discount_hash'] == $discount_hash && $line_items[$j]['po_hash'] == null) {

							if ( bccomp($sell_disc, 0, 2) == 1 )
								$l_discount = bcmul($sell_disc, 100, 4);
							elseif ( ! bccomp($sell_disc, 0, 2) && bccomp($gp_margin, 0, 2) == 1 )
								$g_margin = $gp_margin;
							elseif ( ! bccomp($sell_disc, 0, 2) && ! bccomp($gp_margin, 0, 2) && $line_items[$j]['cost'] && bccomp($line_items[$j]['sell'], 0, 2) )
								$g_margin = (float)bcmul( math::gp_margin( array(
									'cost'  =>  $line_items[$j]['cost'],
									'sell'  =>  $line_items[$j]['sell']
								) ), 100, 4);
							else {

								if ( ! $proposal_gp ) { # Trac #1233

									$total_sell = $total_cost = 0;
									for ( $l = 0; $l < count($line_items); $l++ ) {

										$total_sell = bcadd($total_sell, $line_items[$l]['sell'], 2);
										$total_cost = bcadd($total_cost, $line_items[$l]['cost'], 3);
									}

									$g_margin = (float)bcmul( math::gp_margin( array(
										'cost' =>  $total_cost,
										'sell' =>  $total_sell
									) ), 100, 4);
								}
							}

                            $values = $this->validate_line_item( array(
                                'local'         =>  1,
                                'qty'           =>  $line_items[$j]['qty'],
                                'list'          =>  $line_items[$j]['list'],
                                'discount1'     =>  bcmul($discount1, 100, 4),
                                'list_discount' =>  $l_discount,
                                'gp_margin'     =>  $g_margin
                            ) );

							$cost = $values['cost'];

                            //Ticket 378 fix
							//if ( bccomp($line_items[$j]['sell'], 0, 2) ) # Trac #1233
							//	$sell = $line_items[$j]['list'] - ($line_items[$j]['list'] * $sell_disc);
							//else
								$sell = $values['sell'];

								
								$tier_item[$k] = "UPDATE line_items
											  SET discount_item_hash = '$discount_item_hash', discount1 = '$discount1', " .
											  ( bccomp($sell_disc, 0, 2) == 1 ?
    											  "gp_type = 'L', cost = '$cost', sell = '$sell' " : "gp_type = 'G', cost = '$cost', sell = '$sell' "
											  ) . "
											  WHERE item_hash = '{$line_items[$j]['item_hash']}'";
							
							$k++;
						}
					}
				}
			}
		}

		//Vendor/Product Charges Next
		if ( is_array($_POST['vendor_charges']) ) {

			$vendor = array();
			while ( list($rand_id, $vendor_hash) = each($_POST['vendor_charges']) ) {

				if ( $vendor_hash ) {

					if ( ! is_array($vendor[ $vendor_hash ]) )
                        $vendor[ $vendor_hash ] = array();

					array_push($vendor[ $vendor_hash ], $rand_id);
				}
			}
		}

		$k = 0;
		if ( $vendor ) {

			$line_no = $lines->total;
			while ( list($vendor_hash, $charges) = each($vendor) ) {

				for ( $i = 0; $i < count($charges); $i++ ) {

					$charge_id = $_POST["charge_type{$charges[$i]}"];
					if ( $_POST[ $charge_id . $charges[$i] ] ) {

						unset($product_hash);
						$product_hash = $_POST["product_hash{$charges[$i]}"];
						$descr = $_POST["charge_val{$charges[$i]}"]['item_descr'];
						$cost = preg_replace('/[^-.0-9]/', "", $_POST["cost{$charges[$i]}"]);
						$sell = preg_replace('/[^-.0-9]/', "", $_POST["charge_val{$charges[$i]}"]['sell']);

						if ( ! $descr || ! $sell || bccomp($sell, 0, 2) == -1 ) {

							if ( ! $descr ) $this->content['form_return']['err']["err{$charges[$i]}_descr"] = 1;
							if ( ! $sell || bccomp($sell, 0, 2) == -1 ) $this->content['form_return']['err']["err{$charges[$i]}_sell"] = 1;
							$this->content['error'] = 1;
						} else {

							$item[$k]['item_code'] = $charge_id;
							switch ( $item[$k]['item_code'] ) {

								case 'frf':

								if ( defined('FRF_PRODUCT_HASH') )
									$item[$k]['product_hash'] = FRF_PRODUCT_HASH;
								elseif ( ! $fee_err ) {

									$fee_err = 1;
									$this->content['error'] = 1;
									$this->content['form_return']['feedback'][] = "There is no product associated with one or more of your misc fees. Please have your systems administrator assign each misc fee with a product/service. This can be done under the system config tab, system settings.";
								}

								break;

								case 'fsf':

								if ( defined('FSF_PRODUCT_HASH') )
									$item[$k]['product_hash'] = FSF_PRODUCT_HASH;
								elseif ( ! $fee_err ) {

									$fee_err = 1;
									$this->content['error'] = 1;
									$this->content['form_return']['feedback'][] = "There is no product associated with one or more of your misc fees. Please have your systems administrator assign each misc fee with a product/service. This can be done under the system config tab, system settings.";
								}

								break;

								case 'cbd':

								if ( defined('CBD_PRODUCT_HASH') )
									$item[$k]['product_hash'] = CBD_PRODUCT_HASH;
								elseif ( ! $fee_err ) {

									$fee_err = 1;
									$this->content['error'] = 1;
									$this->content['form_return']['feedback'][] = "There is no product associated with one or more of your misc fees. Please have your systems administrator assign each misc fee with a product/service. This can be done under the system config tab, system settings.";
								}

								break;

								case 'sof':

								if ( defined('SOF_PRODUCT_HASH') )
									$item[$k]['product_hash'] = SOF_PRODUCT_HASH;
								elseif ( ! $fee_err ) {

									$fee_err = 1;
									$this->content['error'] = 1;
									$this->content['form_return']['feedback'][] = "There is no product associated with one or more of your misc fees. Please have your systems administrator assign each misc fee with a product/service. This can be done under the system config tab, system settings.";
								}

								break;
							}

							$item[$k]['vendor_hash'] = $vendor_hash;
							$result = $this->db->query("SELECT t1.ship_to_hash
														FROM line_items t1
														WHERE t1.proposal_hash = '$proposal_hash' AND t1.vendor_hash = '$vendor_hash'
														LIMIT 1");
							$item[$k]['ship_to_hash'] = $this->db->result($result, 0, 'ship_to_hash');
							$item[$k]['line_no'] = $line_no++;
							$item[$k]['qty'] = 1;
							$item[$k]['item_code_parent'] = $product_hash;
							$item[$k]['item_descr'] = $descr;
							$item[$k]['gp_margin'] = math::gp_margin( array(
								'cost'   =>  $cost,
								'sell'   =>  $sell
							) );
							$item[$k]['gp_type'] = 'G';
							$item[$k]['list'] = 0;
							$item[$k]['cost'] = $cost;
							$item[$k]['sell'] = $sell;
							if ( $punch )
								$item[$k]['punch'] = 1;

							$k++;
						}
					}
				}
			}
		}

		//Errors last
		if ( is_array($_POST['error_item']) ) {

			$error_item = array();
			while ( list($rand_id, $item_hash) = each($_POST['error_item']) ) {

				if ( $item_hash )
					array_push($error_item, $item_hash);
			}
		}

		if ( $error_item ) {

			$error_up = array();
			for ( $i = 0; $i < count($error_item); $i++ ) {

				$ship_to = $_POST["item_ship_to-{$error_item[$i]}"];
				$ship_to_hash = $_POST["item_ship_to_hash-{$error_item[$i]}"];
				if ( $ship_to && ! $ship_to_hash ) {

					$this->content['error'] = 1;
					$this->content['form_return']['err']["err1_{$error_item[$i]}"] = 1;
				} elseif ( $ship_to && $ship_to_hash )
					array_push($error_up, "UPDATE line_items t1
        								   SET t1.ship_to_hash = '$ship_to_hash'
        								   WHERE t1.item_hash = '{$error_item[$i]}'");

				$vendor = $_POST["final_vendor-{$error_item[$i]}"];
				$vendor_hash = $_POST["final_vendor_hash-{$error_item[$i]}"];
				if ( $vendor && ! $vendor_hash ) {

					$this->content['error'] = 1;
					$this->content['form_return']['err']["err1_finalvendor_{$error_item[$i]}"] = 1;
				} elseif ( $vendor && $vendor_hash )
					array_push($error_up, "UPDATE line_items t1
        								   SET t1.vendor_hash = '$vendor_hash'
        								   WHERE t1.item_hash = '{$error_item[$i]}'");
			}
		}

		if ( $_POST['install_proposal'] ) {

			if ( ! $_POST['install_proposal_hash'] ) {

				$this->content['error'] = 1;
				$this->content['form_return']['err']['install_proposal'] = 1;
			} else
				array_push($error_up, "UPDATE proposal_install t1
        							   SET t1.install_addr_hash = '{$_POST['install_proposal_hash']}'
        							   WHERE t1.proposal_hash = '$proposal_hash'");
		}

		if ( $this->content['error'] ) {

			$this->content['form_return']['feedback'][] = "The information you entered below is invalid. Please check the indicated fields and try again.";
			return $this->__trigger_error($this->content['form_return']['feedback'], E_USER_ERROR, __FILE__, __LINE__, 1);
		}

		if ( $this->fetch_master_record($proposal_hash) ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

			if ( ! $this->db->query("DELETE t1.*, t2.*
									 FROM line_items t1
									 LEFT JOIN tax_collected t2 ON t2.item_hash = t1.item_hash
									 WHERE
		    							 t1.proposal_hash = '{$this->proposal_hash}'
		    							 AND t1.po_hash = ''
		    							 AND t1.item_code != ''
		    							 AND t1.punch = " . ( $punch ? 1 : 0 ) )
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			for ( $i = 0; $i < count($update_item); $i++ ) {

				if ( ! $this->db->query("UPDATE line_items t1
        								 SET
            								 t1.timestamp = UNIX_TIMESTAMP(),
            								 last_change = '{$this->current_hash}',
            								 " . implode(", ", $update_item[$i]) . "
        								 WHERE item_hash = '{$update_item_hash[$i]}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
			}
			if ( $ship_to_hash && ! $this->current_proposal['ship_to_hash'] ) {

				if ( ! $this->db->query("UPDATE proposal_install
										 SET ship_to_hash = '$ship_to_hash'
										 WHERE proposal_hash = '{$this->proposal_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
			}

			for ( $i = 0; $i < count($tier_item); $i++ ) {

				if ( ! $this->db->query($tier_item[$i]) ) {

					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
				}
			}

			for ( $i = 0; $i < count($error_up); $i++ ) {

				if ( ! $this->db->query($error_up[$i]) ) {

					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
				}
			}

			for ( $i = 0; $i < count($item); $i++ ) {

				if ( $item[$i] ) {

					$item_hash = rand_hash('item_hash_index', 'item_hash');
					if ( ! $this->db->query("INSERT INTO item_hash_index
	        								 VALUES('$item_hash')")
	                ) {

	                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
	                }

					$item_q = $this->db->prepare_query($item[$i], "line_items", 'INSERT');
					if ( ! $this->db->query("INSERT INTO line_items
	        								 (
	            								 timestamp,
	            								 last_change,
	            								 item_hash,
	            								 proposal_hash,
	            								 " . implode(", ", array_keys($item_q)) . "
    	            						 )
        									 VALUES
        									 (
            									 UNIX_TIMESTAMP(),
            									 '{$this->current_hash}',
            									 '$item_hash',
            									 '{$this->proposal_hash}',
            									 " . implode(", ", array_values($item_q)) . "
            								 )")
	                ) {

	                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
	                }
				}
			}

			if ( $item )
				$this->reorder_lines($proposal_hash, $punch);

	        if ( ! $this->db->query("DELETE FROM tax_collected
        	                         WHERE proposal_hash = '{$this->proposal_hash}' AND invoice_hash = '' AND punch = " . ( $punch ? 1 : 0 ) )
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

	        if ( $this->current_proposal['install_addr_country'] == 'US' && $tax_rules = $_POST['tax_rules'] ) {

	            $install_hash = $_POST['install_hash'];
                $product = array();

	            $result = $this->db->query("SELECT t2.product_hash
	                                        FROM line_items t1
	                                        LEFT JOIN vendor_products t2 ON t2.product_hash = t1.product_hash
	                                        WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t1.invoice_hash = '' AND t1.punch = " . ( $punch ? 1 : 0 ) . " AND t2.product_taxable = 1
	                                        GROUP BY t2.product_hash");
	            while ( $row = $this->db->fetch_assoc($result) )
	                array_push($product, $row['product_hash']);

	            $tax_up = $tax_loc_up = array();

	            $sys = new system_config($this->current_hash);
	            for( $i = 0; $i < count($tax_rules); $i++ ) {

	                if ( $tax_rules[$i] ) {

	                    if ( ! $sys->fetch_tax_rule($tax_rules[$i]) )
                            return $this->__trigger_error("System error during tax rule lookup. Unable to fetch tax rule for database edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

	                    if ( $sys->current_tax['incomplete'] ) {

	                        $this->content['error'] = 1;
	                        $this->content['form_return']['feedback'][] = "The tax rule for {$sys->current_tax['state_name']}" . ( $sys->current_tax['local'] ? ", {$sys->current_tax['local']}" : NULL ) . " has not been associated with a liability account. Please have your system administrator re configure the sales tax rule.";
	                    }

	                    for ( $j = 0; $j < count($product); $j++ ) { # For each of the products on our proposal, see if that local collects tax

	                        if ( in_array($product[$j], $sys->current_tax['product']) ) {

	                            $result = $this->db->query("SELECT item_hash
	                                                        FROM line_items
	                                                        WHERE proposal_hash = '{$this->proposal_hash}' AND product_hash = '{$product[$j]}' AND invoice_hash = '' AND active = 1 AND punch = " . ( $punch ? 1 : 0) );
	                            while ( $row = $this->db->fetch_assoc($result) ) {

	                                array_push($tax_up, "INSERT INTO tax_collected
        	                                             (
            	                                             timestamp,
            	                                             proposal_hash,
            	                                             item_hash,
            	                                             tax_hash,
            	                                             rate,
	                                						 maximum,
            	                                             punch
            	                                         )
        	                                             VALUES
        	                                             (
            	                                             UNIX_TIMESTAMP(),
            	                                             '{$proposal_hash}',
            	                                             '{$row['item_hash']}',
            	                                             '{$sys->tax_hash}',
            	                                             '{$sys->current_tax['rate']}',
            	                                             '{$sys->current_tax['maximum']}',
            	                                             " . ( $punch ? 1 : 0 ) . "
            	                                         )"
                                    );
	                            }
	                        }
	                    }

	                    array_push($tax_loc_up, "INSERT INTO location_tax
        	                                     VALUES( NULL, '$install_hash', '{$tax_rules[$i]}')");
	                }
	            }

	        } elseif ( $this->current_proposal['install_addr_country'] == 'CA' ) {

	        	$result = $this->db->query("SELECT item_hash
                                            FROM line_items
                                            WHERE proposal_hash = '{$this->proposal_hash}' AND invoice_hash = '' AND active = 1 AND punch = " . ( $punch ? 1 : 0 ) );
                while ( $row = $this->db->fetch_assoc($result) ) {

                    if ( ! $lines->item_tax($row['item_hash']) ) {

                    	return $this->__trigger_error("System error when attempting to finalize line item sales tax. Please reload window and try again. <!-- Tried applying CA sales tax on line [ {$row['item_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);
                    }
                }

	        }

	        if ( $tax_up ) {

                for ( $i = 0; $i < count($tax_up); $i++ ) {

                    if ( ! $this->db->query($tax_up[$i]) ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
                }
	        }

            if ( ! $this->db->query("DELETE FROM location_tax
                                     WHERE location_hash = '{$_POST['install_hash']}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }
            if ( $tax_loc_up ) {

                for ( $i = 0; $i < count($tax_loc_up); $i++ ) {

                    if ( ! $this->db->query($tax_loc_up[$i]) ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
                }
            }

			if ( $this->current_proposal['order_type'] == 'D' ) {

				$direct_bill = $_POST['direct_bill'];
				$lines->summarized_lines();
				$sum = array();

				for ( $i = 0; $i < count($lines->line_info); $i++ ) {

					if ( $lines->line_info[$i]['active'] )
						$sum[ $lines->line_info[$i]['vendor_hash'] ][] = $lines->line_info[$i]['item_hash'];
				}

				if ( $sum ) {

					while ( list($vendor_group, $direct_vendor) = each($direct_bill) ) {

						for ( $i = 0; $i < count($sum[$vendor_group]); $i++ ) {

							if ( ! $this->db->query("UPDATE line_items
        											 SET direct_bill_vendor = '" . ( $direct_vendor == 'OPEN' ? "" : $direct_vendor ) . "'
        											 WHERE item_hash = '{$sum[ $vendor_group ][$i]}'")
							) {

								return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
							}
						}
					}
				}
			}

			# Currency and exchange rate assignment
			if ( defined('MULTI_CURRENCY') && $_POST['currency'] ) {

				$currency = $_POST['currency'];
				if ( $_POST['exchange_rate'] )
                    $exchange_rate = preg_replace('/[^.0-9]/', "", $_POST['exchange_rate']);

                if ( ! $this->db->query("UPDATE proposals t1
		                                  SET
		                                      t1.currency = " .
			                                  ( $currency != FUNCTIONAL_CURRENCY ?
			                                      "'$currency'" : "NULL"
			                                  ) . ",
			                                  t1.exchange_rate = " .
			                                  ( $currency != FUNCTIONAL_CURRENCY ?
		                                          "'$exchange_rate'" : "0"
			                                  ) . "
		                                  WHERE t1.proposal_hash = '{$this->proposal_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
			}

			$r = $this->db->query("SELECT COUNT(*) AS Total
								   FROM purchase_order t1
								   WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t1.deleted = 0");
			$po_status  = $this->db->result($r, 0, 'Total');

			if ( ! $this->db->query("UPDATE proposals t1
									  SET
		    							  t1.timestamp = UNIX_TIMESTAMP(),
		    							  t1.last_change = '{$this->current_hash}', " .
		    							  ( $punch ?
		        							  "t1.punch_final = 1,
		        							  t1.punch_final_timestamp = UNIX_TIMESTAMP(), "
		        							  :
		        							  "t1.final = 1,
		        							  t1.final_timestamp = UNIX_TIMESTAMP(), "
		                                  ) . "
		                                  t1.expiration_date = " . ( ! $po_status ? "'" . date("Y-m-d", time() + 2592000) . "'" : "NULL" ) . "
									  WHERE t1.proposal_hash = '{$this->proposal_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }
		}

		$this->db->end_transaction();

		unset($this->ajax_vars, $this->current_proposal);

		$this->content['page_feedback'] = "Finalization functions have been applied and your proposal has been marked final.";
		$this->content['action'] = 'continue';

		if ( $punch )
			$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash={$this->proposal_hash}');" );
        else
			$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', '" . ( $sumarized ? "summarized" : "line_items" ) . "', 'otf=1', 'proposal_hash={$this->proposal_hash}');" );

		return;
	}

	function unset_final($proposal_hash=NULL, $punch=NULL) {

        if ( func_num_args() ) {

        	$proposal_hash = func_get_arg(0);
            $punch = func_get_arg(1);
        } elseif ( $_POST['proposal_hash'] ) {

        	$proposal_hash = $_POST['proposal_hash'];
        	$punch = $_POST['punch'];
        	$local = 1;
        } else {

            $proposal_hash = $this->ajax_vars['proposal_hash'];
            $punch = $this->ajax_vars['punch'];
            $local = 1;
        }

		if ( ! $proposal_hash )
			return false;

		if ( $punch )
			$final_col = 'punch_';

		$manual = $this->ajax_vars['manual'];
		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM line_items
									WHERE proposal_hash = '$proposal_hash' AND po_hash = '' AND punch = " . ( $punch ? 1 : 0 ) );

		if ( $this->db->result($result, 0, 'Total') ) {

			if ( ! $this->db->query("UPDATE proposals t1
        							 SET
            							 t1.timestamp = UNIX_TIMESTAMP(),
            							 t1.last_change = '{$this->current_hash}',
            							 t1.{$final_col}final = 0,
            							 t1.{$final_col}final_timestamp = 0
        							 WHERE t1.proposal_hash = '$proposal_hash' AND t1.{$final_col}final = '1'")
            ) {

            	$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            	return false;
            }

			if ( $this->db->affected_rows() == 1 ) {

				if ( ! $this->db->query("DELETE FROM line_items
        								 WHERE
            								 proposal_hash = '$proposal_hash'
            								 AND item_code != ''
            								 AND status = 0
            								 AND punch = " . ( $punch ? 1 : 0 ) )
                ) {

                	$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                	return false;
                }

				if ( ! $this->db->query("DELETE t2.*
										 FROM line_items t1
										 LEFT JOIN tax_collected t2 ON t2.item_hash = t1.item_hash
										 WHERE
    		    							 t2.proposal_hash = '$proposal_hash'
		    								 AND t2.invoice_hash = ''
		    								 AND t1.status = 0
		    								 AND t1.punch = " . ( $punch ? 1 : 0 ) )
                ) {

                	$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                	return false;
                }
			}

			if ( $local ) {

				unset($this->ajax_vars, $this->current_proposal, $this->line_items);
				$this->content['page_feedback'] = "Finalization functions have been removed from this proposal.";
				$this->content['action'] = 'continue';

				if ( $punch )
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$proposal_hash');" );
				else
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', '" . ( $sum ? "summarized" : "line_items" ) . "', 'otf=1', 'proposal_hash=$proposal_hash', 'limit=$limit');" );
			}

		} else {

			$result = $this->db->query("SELECT t1.{$final_col}final AS final
										FROM proposals t1
										WHERE t1.proposal_hash = '$proposal_hash'");
			if ( ! $this->db->result($result, 0, 'final') ) {

				if ( ! $this->db->query("UPDATE proposals t1
										 SET
		    								 t1.timestamp = UNIX_TIMESTAMP(),
		    								 last_change = '{$this->current_hash}',
		    								 t1.{$final_col}final = 1,
		    								 t1.{$final_col}final_timestamp = UNIX_TIMESTAMP()
										 WHERE t1.proposal_hash = '$proposal_hash'")
                ) {

                	$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                	return false;
                }
			}

			if ( $local ) {

				$this->content['page_feedback'] = "There are no line items to unfinalize.";
				$this->content['action'] = 'continue';
			}
		}

		$this->line_item_menu();
		return true;
	}

	function doit_search() {
		$sort = $_POST['sort'];
    	$sort_from_date = $_POST['sort_from_date'];
    	$sort_to_date = $_POST['sort_to_date'];
    	$sales_rep_match = $_POST['sales_rep_match'];
		
		if ( ! is_array($sales_rep_match) && $sales_rep_match )
			$sales_rep_match = array($sales_rep_match);

		if ( ! $sales_rep_match[0] )
			unset($sales_rep_match);

		$save = $_POST['page_pref'];
		$detail_search = $_POST['detail_search'];

		$str = "show=$sort|sort_from_date=$sort_from_date|sort_to_date=$sort_to_date|sales_rep=".@implode("&", $sales_rep_match);

		if ( $save ) {

			$r = $this->db->query("SELECT t1.obj_id
								   FROM page_prefs t1
								   WHERE t1.id_hash = '{$this->current_hash}' AND t1.class = '" . get_class($this) . "'");
			if ( $obj_id = $this->db->result($r, 0, 'obj_id') ) {

				if ( ! $this->db->query("UPDATE page_prefs t1
        								 SET t1.pref_str = '$str'
        								 WHERE t1.obj_id = $obj_id")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
			} else {

				if ( ! $this->db->query("INSERT INTO page_prefs
           								 VALUES
           								 (
	           								 NULL,
	           								 '{$this->current_hash}',
	           								 '" . get_class($this) . "',
	           								 '',
	           								 '$str'
           								 )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
			}

		} else {

			$sql_p = array();
			unset($this->page_pref['custom']);

			if ( ! $sort )
				$sort = '*';
			if ( $sort != '*' ) {

				array_push(
    				$sql_p,
    				( $sort == 2 ?
    					"proposals.archive = 1" : "proposals.archive = 0"
    				)
    			);
			}

			if ( $sort_from_date )
				array_push($sql_p, "proposals.creation_date >= " . strtotime($sort_from_date) );

			if ( $sort_to_date && strtotime($sort_to_date) > strtotime($sort_from_date) )
				array_push($sql_p, "proposals.creation_date <= " . strtotime($sort_to_date) );
			if ( is_array($sales_rep_match) && count($sales_rep_match) ) {

				$sales_rep_match = array_unique($sales_rep_match);
				$sales_rep_match = array_values($sales_rep_match);
				array_walk($sales_rep_match, 'add_quotes', "'");
				array_push($sql_p, "proposals.sales_hash IN (" . implode(", ", $sales_rep_match) . ")");
				array_walk($sales_rep_match, 'strip_quotes');
			}

			if ( $detail_search ) {

				$sql_p = array();

				// check if proposal number field is called 'proposal' or 'proposal_no'
				$proposal_no = $_POST['proposal'] ? trim($_POST['proposal']) : trim($_POST['proposal_no']);
					
				$po_no = trim($_POST['po_no']);
				if ( ! is_array($sales_rep) && $sales_rep )
					$sales_rep = array($sales_rep);

				$customer_filter = $_POST['customer_filter'];
				if ( ! is_array($customer_filter) && $customer_filter )
					$customer_filter = array($customer_filter);

				$active_archived = $_POST['active_archived'];
				$invoice_no = trim($_POST['invoice_no']);
				$dc_po_no = trim($_POST['dc_po_no']);
				$proposal_status = $_POST['proposal_status'];
				$user_status = $_POST['user_status'];
				$ack_no = trim($_POST['ack_no']);
				$direct_bill = $_POST['direct_bill'];
				$item_no = trim($_POST['item_no']);
				$descr = trim($_POST['descr']);
				if ( $descr )
					array_push($sql_p, "proposals.proposal_descr LIKE '%" . addslashes($descr) . "%'");

				$save = $_POST['save'];
				$search_name = $_POST['search_name'];
				if ( ! $search_name )
					unset($save);

				if ( $proposal_no )
					array_push($sql_p, "proposals.proposal_no LIKE '%$proposal_no%'");
				if ( $dc_po_no )
					array_push($sql_p, "purchase_order.po_no LIKE '%$dc_po_no%'");
				if ( $po_no )
					array_push($sql_p, "proposals.customer_po LIKE '%$po_no%'");
				if ( is_array($sales_rep_match) && count($sales_rep_match) ) {

					$sales_rep_match = array_unique($sales_rep_match);
					$sales_rep_match = array_values($sales_rep_match);
					array_walk($sales_rep_match, 'add_quotes', "'");
					array_push($sql_p, "proposals.sales_hash IN (" . implode(", ", $sales_rep_match) . ")");
					array_walk($sales_rep_match, 'strip_quotes');
				}

				if ( is_array($customer_filter) && count($customer_filter) ) {

					$customer_filter = array_unique($customer_filter);
					$customer_filter = array_values($customer_filter);
					array_walk($customer_filter, 'add_quotes', "'");
					array_push($sql_p, "proposals.customer_hash IN (" . implode(", ", $customer_filter) . ")");
					array_walk($customer_filter, 'strip_quotes');
				}

				if ( $active_archived )
					array_push($sql_p, "proposals.archive = " . ( $active_archived == 2 ? 1 : 0 ) );
				if ( $invoice_no )
					array_push($sql_p, " ( customer_invoice.invoice_no LIKE '%$invoice_no%' AND customer_invoice.deleted = 0 )");
				if ( $proposal_status ) {

					if ( $proposal_status == 1 )
						array_push($sql_p, "proposals.status = '0'");
					elseif ( $proposal_status == 2 )
						array_push($sql_p, "proposals.status = '1'");
					elseif ( $proposal_status == 3 )
						array_push($sql_p, "proposals.status = '2'");
					elseif ( $proposal_status == 4 )
						array_push($sql_p, "proposals.status >= '1' AND proposals.proposal_status = 'PL'");
					elseif ( $proposal_status == 'C' )
						array_push($sql_p, "proposals.proposal_status = 'C'");
				}

				if ( $user_status )
                    array_push($sql_p, "proposals.user_status = '$user_status'");
				if ( $ack_no )
					array_push($sql_p, "line_items.ack_no LIKE '$ack_no%'");
				if ( $direct_bill )
					array_push($sql_p, "proposals.order_type = '$direct_bill'");
				if ( $item_no )
					array_push($sql_p, "line_items.item_no LIKE '$item_no%'");
			}

			if ( $sql_p )
				$sql = implode(" AND ", $sql_p);

			if ( $sql ) {

				if ( preg_match('/line_items/', $sql) )
					$s_line_items = true;
				if ( preg_match('/purchase_order/', $sql) )
					$s_purchase_order = true;
				if ( preg_match('/customer_invoice/', $sql) )
					$s_customer_invoice = true;
			}

			$r = $this->db->query("SELECT COUNT( proposals.obj_id ) AS Total
								   FROM proposals
								   LEFT JOIN customers ON customers.customer_hash = proposals.customer_hash " .
                        		   ( $s_purchase_order ?
    								   "LEFT JOIN purchase_order ON purchase_order.proposal_hash = proposals.proposal_hash AND purchase_order.deleted = 0 " : NULL
                        		   ) .
                        		   ( $s_customer_invoice ?
    								   "LEFT JOIN customer_invoice ON customer_invoice.proposal_hash = proposals.proposal_hash " : NULL
                        		   ) .
                        		   ( $s_line_items ?
    								   "LEFT JOIN line_items ON line_items.proposal_hash = proposals.proposal_hash " : NULL
                        		   ) . "
								   LEFT JOIN users ON users.id_hash = proposals.sales_hash " .
                        		   ( $sql ?
    								   "WHERE $sql
    								   GROUP BY proposals.proposal_hash" : NULL
    							   ) );
			$total = $this->db->num_rows($r, 0, 'Total');

			$search_hash = rand_hash('search', 'search_hash');
			if ( ! $this->db->query("INSERT INTO search
        							 VALUES
        							 (
	        							 NULL,
	        							 UNIX_TIMESTAMP(),
	        							 '$search_hash',
	        							 '$save',
	        							 '$search_name',
	        							 'proposals',
	        							 '{$this->current_hash}',
	        							 '$detail_search',
	        							 '" . base64_encode($sql) . "',
	        							 '$total',
	        							 '$str'
        							 )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			$this->active_search = $search_hash;
		}

		$this->content['action'] = ( $detail_search ? 'close' : 'continue' );
		$this->content['jscript_action'] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'active_search={$this->active_search}');";

		return;
	}

	function doit_submit_to() {
		$submit_to = $_POST['submit_to'];
		$submit_msg = $_POST['submit_msg'];
		$proposal_hash = $_POST['proposal_hash'];

		$r = $this->db->query("SELECT `proposal_no` , `proposal_descr`
							   FROM `proposals`
							   WHERE `proposal_hash` = '$proposal_hash'");
		$proposal_no = $this->db->result($result, 0, 'proposal_no');
		$proposal_descr = $this->db->result($result, 0, 'proposal_descr');

		$submit_msg .= "\n\nClick <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'edit_proposal', 'proposal_hash=".$proposal_hash."');\">here</a> to jump to this proposal.";
		for ($i = 0; $i < count($submit_to); $i++) {
			if ($submit_to[$i]) {
				$subject = "Proposal ".$proposal_no." has been submitted to the ".group_name($submit_to[$i])." group.";

				$message_hash = md5(global_classes::get_rand_id(32, "global_classes"));
				while (global_classes::key_exists('messages', 'message_hash', $message_hash))
					$message_hash = md5(global_classes::get_rand_id(32, "global_classes"));

				$y = true;
				$this->db->query("INSERT INTO `messages`
								  (`timestamp` , `message_hash` , `sender_hash` , `group_hash` , `subject` , `message`)
								  VALUES (".time()." , '$message_hash' , '".$this->current_hash."' , '".$submit_to[$i]."' , '$subject' , '".addslashes($submit_msg)."')");
			}
		}

		$this->content['action'] = 'continue';
		$this->content['page_feedback'] = ($y ? "The proposal has been added to the system queue for the selected group(s)." : "You selected no group(s) to submit to.");
		return;
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
			$this->content['html']['pm_request_holder_2'] = $this->form->checkbox("name=pm_request", "value=1", ($this->p->ck(get_class($this), 'E', 'install') ? "onClick=if(this.checked){\$('proj_mngr').value='';\$('proj_mngr_hash').value='';}auto_save();" : NULL));
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

			$this->content['html']['install_request_holder_1'] = "Submit Quote Rqst?";
			$this->content['html']['install_request_holder_2'] = $this->form->checkbox("name=install_request", "value=1", ($this->p->ck(get_class($this), 'E', 'install') ? "onClick=auto_save();" : NULL));
			break;

			case 'design':
			$result = $this->db->query("SELECT `design_queue`
										FROM `proposal_design`
										WHERE `proposal_hash` = '$proposal_hash'");
			if ($queue_hash = $this->db->result($result))
				$this->db->query("DELETE FROM `messages`
								  WHERE `queue_hash` = '$queue_hash'");

			$this->db->query("UPDATE `proposal_design`
							  SET `design_queue` = ''
							  WHERE `proposal_hash` = '$proposal_hash'");

			$this->content['html']['design_request_holder_1'] = "Submit Design Request?";
			$this->content['html']['design_request_holder_2'] = $this->form->checkbox("name=design_request", "value=1", ($this->p->ck(get_class($this), 'E', 'design')  ? "onClick=if(this.checked){\$('designer').value='';\$('designer_hash').value='';}".($valid ? "auto_save();" : NULL) : NULL));
			break;
		}
		return;
	}

	function doit() {
		global $err, $errStr;

		$this->check = new Validator;

		$action = $_POST['action'];
		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$active_search = $_POST['active_search'];
		$proposal_hash = $_POST['proposal_hash'];
		$auto_save = $_POST['auto_save'];
		$auto_focus = $_POST['auto_focus'];

		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		$active_search = $_POST['active_search'];
		if ($active_search)
			$this->active_search = $active_search;

		if ($action)
			return $this->$action();

		if ($btn == 'Search')
			return $this->do_search();

		if ($btn == 'Save Proposal' || $btn == 'Update Proposal') {
			if ($_POST['proposal_descr'] && $_POST['proposal_no'] && $_POST['customer'] && $_POST['propose_to'] && $_POST['sales_rep']) {
				//General info area
				$general['proposal_descr'] = $_POST['proposal_descr'];
				$general['proposal_no'] = $proposal_no = $_POST['proposal_no'];
				$general['customer'] = $_POST['customer'];
				$general['customer_hash'] = $_POST['customer_hash'];
				$general['bill_to_hash'] = $_POST['bill_to_hash'];
				$general['propose_to'] = $_POST['propose_to'];
				$general['propose_to_hash'] = $_POST['propose_to_hash'];
				$general['customer_contact'] = $_POST['customer_contact'];
				$general['customer_po'] = $_POST['customer_po'];
				$general['sales_rep'] = $_POST['sales_rep'];
                $general['sales_hash'] = $_POST['sales_hash'];
				$general['sales_rep_2'] = $_POST['sales_rep_2'];
				$general['sales_hash_2'] = $_POST['sales_hash_2'];
				$general['designer'] = $_POST['designer'];
				$general['designer_hash'] = $_POST['designer_hash'];
				$general['sales_coord'] = $_POST['sales_coord'];
				$general['sales_coord_hash'] = $_POST['sales_coord_hash'];
				$general['proj_mngr'] = $_POST['proj_mngr'];
				$general['proj_mngr_hash'] = $_POST['proj_mngr_hash'];
				$general['arch'] = $_POST['arch'];
				$general['arch_hash'] = $_POST['arch_hash'];
				$general['arch_contact'] = $_POST['arch_contact'];
				$general['expiration_date'] = $_POST['expiration_date'];
				$general['close_date'] = $_POST['close_date'];
				$general['booking_probability'] = $_POST['booking_probability'];
				$general['commission_team'] = $commission_team = $_POST['commission_team'];
				$general['order_type'] = $_POST['order_type'];
				if (!$general['order_type'])
					unset($general['order_type']);
				//$general['direct_vendor_hash'] = $_POST['direct_vendor_hash'];
				$general['user_status'] = $_POST['user_status'];
				$general['user_status_comment'] = $_POST['user_status_comment'];
				$general['archive'] = $_POST['archive'];
				if (!$proposal_hash)
					$proposal_notes = $_POST['proposal_notes'];
				if ($pm_request = $_POST['pm_request'])
					unset($general['proj_mngr_hash']);
				else
					$general['pm_request'] = 0;

				if ($custom = $_POST['custom']) {
                    while (list($cust_key, $cust_val) = each($custom)) {
                        $this->cust->fetch_custom_field($cust_key);
                        if ($this->cust->current_field['required'] && !trim($cust_val)) {
                            $this->content['form_return']['err']['err_custom'.$this->cust->current_field['obj_id']] = 1;
                            $this->content['error'] = 1;
                            $this->content['form_return']['feedback'][] = "Please make sure you complete the indicated fields below.";
                        }
                        if ($this->cust->current_field['field_type'] == 'select_multiple')
                            $general[$this->cust->current_field['col_name']] = @implode("|", $cust_val);
                        else
                            $general[$this->cust->current_field['col_name']] = trim($cust_val);
                    }
				}

				//Check to make sure the proposal number doesn't exist
				if ($btn == 'Save Proposal') {
					if (defined('PROPOSAL_SEED') && substr($general['proposal_no'], 0, strlen(PROPOSAL_SEED)) == PROPOSAL_SEED && str_replace(PROPOSAL_SEED, "", $general['proposal_no']) == fetch_sys_var('NEXT_PROPOSAL_NUM')) {
						$proposal_no = str_replace(PROPOSAL_SEED, "", $general['proposal_no']);
						$seed = $inline = true;
					} elseif ($general['proposal_no'] == fetch_sys_var('NEXT_PROPOSAL_NUM')) {
						$proposal_no = $general['proposal_no'];
						$inline = true;
					} else
						$proposal_no = $general['proposal_no'];

					while (field_exists("proposals", "proposal_no", $proposal_no))
						$proposal_no++;

					$general['proposal_no'] = ($seed ? PROPOSAL_SEED : NULL).$proposal_no;
				}
				//If we've entere an incorrect customer, return
				if (($general['customer'] && !$general['customer_hash'] && $auto_focus != 'customer') || ($general['propose_to'] && !$general['propose_to_hash'] && $auto_focus != 'propose_to')) {
					if (!$general['customer_hash']) $this->content['form_return']['err']['err1_3'.$this->popup_id] = 1;
					if (!$general['propose_to_hash']) $this->content['form_return']['err']['err1_17'.$this->popup_id] = 1;
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = "We can't seem to find the customer you entered below. If you are creating a new customer, please click the icon next to the customer field to create a new customer, then re submit.";
				}

				if ($general['bill_to_hash']) {
					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM `locations`
												WHERE `entry_hash` = '".$general['customer_hash']."' AND `location_hash` = '".$general['bill_to_hash']."'");
					if ($this->db->result($result) == 0) {
						$this->content['error'] = 1;
						$this->content['form_return']['err']['err1_3'.$this->popup_id] = 1;
						$this->content['form_return']['feedback'][] = "The bill to address you entered can't be found. Please make sure you are entering a valid billing location.";
					}
				}

				if ($general['commission_team'] == '*') {
					$total_rate = 0;
					$commission_rand = $_POST['commission_rand'];
					for ($i = 0; $i < count($commission_rand); $i++) {
	                    if ($commission_rand[$i] != 0 && $_POST['commission_hash_'.$commission_rand[$i]] && $_POST['commission_user_'.$commission_rand[$i]] && bccomp($_POST['commission_user_rate_'.$commission_rand[$i]], 0, 4) != 0) {
	                        $commission_team_user[] = array('user_hash'    =>  $_POST['commission_hash_'.$commission_rand[$i]],
				                                            'rate'         =>  $_POST['commission_user_rate_'.$commission_rand[$i]]);
	                        $total_rate = bcadd($total_rate, $_POST['commission_user_rate_'.$commission_rand[$i]], 4);
	                    }
	                }
	                if (bccomp($total_rate, 100, 4) != 0) {
                        $this->content['error'] = 1;
                        $this->content['form_return']['feedback'][] = "In order to create a new commission team, the total rates distributed across your team members must equal 100%";
                        $this->set_error('err_commteam');
                        unset($general['commission_team']);
	                }
				}

				if ($this->content['error']) {
					$this->content['submit_btn'] = "proposal_btn";
					return;
				}

				//If we've entered an incorrect sales rep, designer, sales coord, or proj mngr, return
				if (($general['sales_rep'] && !$general['sales_hash'] && $auto_focus != 'sales_rep') || ($general['sales_rep_2'] && !$general['sales_hash_2'] && $auto_focus != 'sales_rep_2') || ($general['designer'] && !$general['designer_hash'] && $auto_focus != 'designer') || ($general['sales_coord'] && !$general['sales_coord_hash'] && $auto_focus != 'sales_coord') || ($general['proj_mngr'] && !$general['proj_mngr_hash'] && $auto_focus != 'proj_mngr')) {
					if (!$auto_save) {
                        if ($general['sales_rep'] && !$general['sales_hash'] && $auto_focus != 'sales_rep') $this->content['form_return']['err']['err1_5'.$this->popup_id] = 1;
                        if ($general['sales_rep_2'] && !$general['sales_hash_2'] && $auto_focus != 'sales_rep_2') $this->content['form_return']['err']['err1_5a'.$this->popup_id] = 1;
						if ($general['designer'] && !$general['designer_hash']) $this->content['form_return']['err']['err1_6'.$this->popup_id] = 1;
						if ($general['sales_coord'] && !$general['sales_coord_hash']) $this->content['form_return']['err']['err1_7'.$this->popup_id] = 1;
						if ($general['proj_mngr'] && !$general['proj_mngr_hash']) $this->content['form_return']['err']['err1_8'.$this->popup_id] = 1;
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = "The information you entered below is invalid. If you are trying to enter a new internal user please contact your system adminstrator.";
					}
				}

				if ($this->content['error']) {
					$this->content['submit_btn'] = "proposal_btn";
					return;
				}
				//If we've entered an incorrect A&D firm
				if ($general['arch'] && !$general['arch_hash']) {
					$this->content['form_return']['err']['err1_9'.$this->popup_id] = 1;
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = "We can't seem to find the A&D firm you entered below. If you are creating a new firm, please click the icon next to the A&D field to create a new firm, then re submit.";
				}
				if (($general['close_date'] && !checkdate(substr($general['close_date'], 5, 2), substr($general['close_date'], 8), substr($general['close_date'], 0, 4))) || !checkdate(substr($general['expiration_date'], 5, 2), substr($general['expiration_date'], 8), substr($general['expiration_date'], 0, 4))) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = "The date(s) you entered for the indicated fields are invalid. Please enter a valid date.";
					if (!checkdate(substr($general['expiration_date'], 5, 2), substr($general['expiration_date'], 8), substr($general['expiration_date'], 0, 4)))$this->content['form_return']['err']['err1_13'.$this->popup_id] = 1;
					if ($general['close_date'] && !checkdate(substr($general['close_date'], 5, 2), substr($general['close_date'], 8), substr($general['close_date'], 0, 4))) $this->content['form_return']['err']['err1_14'.$this->popup_id] = 1;
				}

				$general['booking_probability'] *= .01;
				if ($general['booking_probability'] && (!$this->check->is_allnumbers($general['booking_probability']*100) || $general['booking_probability'] > 1)) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = "Please check that your probability is percentage number between 0 and 100.";
					$this->content['form_return']['err']['err1_15'.$this->popup_id] = 1;
				}
				/*
				if ($general['order_type'] == 'D' && !$general['direct_vendor_hash']) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = (!$general['direct_vendor'] ?
						"You haven't indicated which vendor to use for your direct bill. Please choose your vendor from the Direct Vendor field below." : "We can't seem to find the vendor you indicated for your direct bill. If you are trying to create a new vendor please use the plus icon next to the vendor field.");
					$this->content['form_return']['err']['err1_17'.$this->popup_id] = 1;
				}
				*/
				if ($this->content['error']) {
					$this->content['submit_btn'] = "proposal_btn";
					return;
				}

				//Design Info
				$design['dwg_date'] = $_POST['dwg_date'];
				$design['bom_date'] = $_POST['bom_date'];
				$design['option_bid'] = $_POST['option_bid'];
				$design['option_gsa'] = $_POST['option_gsa'];
				$design['option_xpress'] = $_POST['option_xpress'];
				$design['option_value_eng'] = $_POST['option_value_eng'];
				$design['option_bldg_shell_prvd'] = $_POST['option_bldg_shell_prvd'];
				$design['option_typ'] = $_POST['option_typ'];
				$design['option_field_msr_req'] = $_POST['option_field_msr_req'];
				$design['option_inv_req'] = $_POST['option_inv_req'];
				$design['option_pres_brds'] = $_POST['option_pres_brds'];
				$design['option_spec_tag'] = $_POST['option_spec_tag'];
				$design['option_install_tag'] = $_POST['option_install_tag'];
				$design['wrkstn_qty'] = $_POST['wrkstn_qty'];
				$design['wrkstn_prod'] = $_POST['wrkstn_prod'];
				$design['office_qty'] = $_POST['office_qty'];
				$design['office_prod'] = $_POST['office_prod'];
				$design['option_ancillary'] = $_POST['option_ancillary'];
				$design['anc_prod'] = $_POST['anc_prod'];
				if (!$proposal_hash)
					$design_notes = $_POST['design_notes'];
				$design_request = $_POST['design_request'];

				//If either of the date fields are in the past, return
				if (!$proposal_hash) {
					if (($design['dwg_date'] && strtotime($design['dwg_date']) < strtotime(date("Y-m-d"))) || ($design['bom_date'] && strtotime($design['bom_date']) < strtotime(date("Y-m-d")))) {
						if ($design['dwg_date'] && strtotime($design['dwg_date']) < strtotime(date("Y-m-d"))) $this->content['form_return']['err']['err2_1'.$this->popup_id] = 1;
						if ($design['bom_date'] && strtotime($design['bom_date']) < strtotime(date("Y-m-d"))) $this->content['form_return']['err']['err2_2'.$this->popup_id] = 1;
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The date you entered for the indicated field(s) is in the past. Please enter a valid date and try again.";
						$this->content['submit_btn'] = "proposal_btn";
						return;
					}
				}
				//If either of the qty fields are not numeric, return
				if (($design['wrkstn_qty'] && !$this->check->is_allnumbers($design['wrkstn_qty'])) || ($design['office_qty'] && !$this->check->is_allnumbers($design['office_qty']))) {
					if ($design['wrkstn_qty'] && !$this->check->is_allnumbers($design['wrkstn_qty'])) $this->content['form_return']['err']['err2_3'.$this->popup_id] = 1;
					if ($design['office_qty'] && !$this->check->is_allnumbers($design['office_qty'])) $this->content['form_return']['err']['err2_4'.$this->popup_id] = 1;
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = "For the quantity fields below please enter only numbers.";
				}

				//If we've entered a qty with no product or a product with no qty, return
				if (($design['wrkstn_qty'] && !$design['wrkstn_prod']) || ($design['wrkstn_prod'] && !$design['wrkstn_qty']) || ($design['office_qty'] && !$design['office_prod']) || ($design['office_prod'] && !$design['office_qty'])) {
					if ($design['wrkstn_qty'] && !$design['wrkstn_prod']) $this->content['form_return']['err']['err2_6'.$this->popup_id] = 1;
					if ($design['wrkstn_prod'] && !$design['wrkstn_qty']) $this->content['form_return']['err']['err2_3'.$this->popup_id] = 1;
					if ($design['office_qty'] && !$design['office_prod']) $this->content['form_return']['err']['err2_7'.$this->popup_id] = 1;
					if ($design['office_prod'] && !$design['office_qty']) $this->content['form_return']['err']['err2_4'.$this->popup_id] = 1;
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = "Please complete the fields below where indicated.";
				}

				if ($this->content['error']) {
					$this->content['submit_btn'] = "proposal_btn";
					return;
				}

				//Install Info
				$install['target_install_date'] = $_POST['target_install_date'];
				$install['actual_install_date'] = $_POST['actual_install_date'];
				$install['ship_to_contact_name'] = $_POST['ship_to_contact_name'];
				$install['ship_to_contact_phone'] = $_POST['ship_to_contact_phone'];
				$install['ship_to_contact_fax'] = $_POST['ship_to_contact_fax'];
				$install['shipping_notes'] = $_POST['shipping_notes'];
				$install['return_date'] = $_POST['return_date'];
				$install['install_type'] = $_POST['install_type'];
				$install['start_date'] = $_POST['start_date'];
				$install['install_days'] = $_POST['install_days'];
				$install['install_addr'] = $_POST['install_addr'];
				$install['install_addr_hash'] = $_POST['install_addr_hash'];
				$install['ship_to'] = $_POST['ship_to'];
				$install['ship_to_hash'] = $_POST['ship_to_hash'];
				$install['bldg_poc'] = $_POST['bldg_poc'];
				$install['bldg_phone'] = $_POST['bldg_phone'];
				$install['bldg_fax'] = $_POST['bldg_fax'];
				$install['option_security'] = $_POST['option_security'];
				$install['option_insurance'] = $_POST['option_insurance'];
				$install['option_permits'] = $_POST['option_permits'];
				$install['no_floors'] = $_POST['no_floors'];
				$install['option_inst_normal_hours'] = $_POST['option_inst_normal_hours'];
				$install['option_loading_dock'] = $_POST['option_loading_dock'];
				$install['option_stair_carry'] = $_POST['option_stair_carry'];
				$install['option_occupied'] = $_POST['option_occupied'];
				$install['option_dlv_normal_hours'] = $_POST['option_dlv_normal_hours'];
				$install['option_bldg_rstr'] = $_POST['option_bldg_rstr'];
				$install['option_freight_elev'] = $_POST['option_freight_elev'];
				$install['option_mv_prod_prior'] = $_POST['option_mv_prod_prior'];
				$install['task_seating_prod'] = $_POST['task_seating_prod'];
				$install['task_seating_qty'] = $_POST['task_seating_qty'];
				$install['guest_seating_prod'] = $_POST['guest_seating_prod'];
				$install['guest_seating_qty'] = $_POST['guest_seating_qty'];
				$install['option_dwgs_pvd'] = $_POST['option_dwgs_pvd'];
				$install['option_power_poles'] = $_POST['option_power_poles'];
				$install['option_multi_trip'] = $_POST['option_multi_trip'];
				$install['option_wall_mntd'] = $_POST['option_wall_mntd'];
				$install['option_wood_trim'] = $_POST['option_wood_trim'];
				if (!$proposal_hash)
					$install_notes = $_POST['install_notes'];

				$install_request = $_POST['install_request'];

				//If either of the date fields are in the past, return
				if (!$proposal_hash) {
					if (($install['return_date'] && strtotime($install['return_date']) < strtotime(date("Y-m-d"))) || ($install['start_date'] && strtotime($install['start_date']) < strtotime(date("Y-m-d")))) {
						if ($install['return_date'] && strtotime($install['return_date']) < strtotime(date("Y-m-d"))) $this->content['form_return']['err']['err3_1'.$this->popup_id] = 1;
						if ($install['start_date'] && strtotime($install['start_date']) < strtotime(date("Y-m-d"))) $this->content['form_return']['err']['err3_2'.$this->popup_id] = 1;
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The date you entered for the indicated field(s) is in the past. Please enter a valid date and try again.";
						$this->content['submit_btn'] = "proposal_btn";
						return;
					}
				}
				//If any of the qty fields are not numeric, return
				if (($install['install_days'] && !$this->check->is_allnumbers($install['install_days'])) || ($install['no_floors'] && !$this->check->is_allnumbers($install['no_floors'])) || ($install['task_seating_qty'] && !$this->check->is_allnumbers($install['task_seating_qty'])) || ($install['guest_seating_qty'] && !$this->check->is_allnumbers($install['guest_seating_qty']))) {
					if ($install['install_days'] && !$this->check->is_allnumbers($install['install_days'])) $this->content['form_return']['err']['err3_3'.$this->popup_id] = 1;
					if ($install['no_floors'] && !$this->check->is_allnumbers($install['no_floors'])) $this->content['form_return']['err']['err3_9'.$this->popup_id] = 1;
					if ($install['guest_seating_qty'] && !$this->check->is_allnumbers($install['guest_seating_qty'])) $this->content['form_return']['err']['err3_12'.$this->popup_id] = 1;
					if ($install['task_seating_qty'] && !$this->check->is_allnumbers($install['task_seating_qty'])) $this->content['form_return']['err']['err3_11'.$this->popup_id] = 1;
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = "For the quantity fields below please enter only numbers.";
					$this->content['submit_btn'] = "proposal_btn";
					return;
				}

				//If we've entered an incorrect sales rep, designer, sales coord, or proj mngr, return
				if (($install['ship_to'] && !$install['ship_to_hash'] && $auto_focus != 'ship_to') || ($install['install_addr'] && !$install['install_addr_hash'] && $auto_focus != 'install_addr')) {
					if ($install['ship_to'] && !$install['ship_to_hash']) $this->content['form_return']['err']['err3_5'.$this->popup_id] = 1;
					if ($install['install_addr'] && !$install['install_addr_hash']) $this->content['form_return']['err']['err3_4'.$this->popup_id] = 1;
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = $auto_focus."The information you entered below is invalid. If you are trying to enter a new location please navigate to customers/vendors, select your customer or vendor and add the new location under the locations tab.";
					$this->content['submit_btn'] = "proposal_btn";
					return;
				}

				//If we've entered a qty with no product or a product with no qty, return
				if (($install['task_seating_qty'] && !$install['task_seating_prod']) || ($install['task_seating_prod'] && !$install['task_seating_qty']) || ($install['guest_seating_qty'] && !$install['guest_seating_prod']) || ($install['guest_seating_prod'] && !$install['guest_seating_qty'])) {
					if ($install['task_seating_qty'] && !$install['task_seating_prod']) $this->content['form_return']['err']['err3_10'.$this->popup_id] = 1;
					if ($install['task_seating_prod'] && !$install['task_seating_qty']) $this->content['form_return']['err']['err3_11'.$this->popup_id] = 1;
					if ($install['guest_seating_qty'] && !$install['guest_seating_prod']) $this->content['form_return']['err']['err3_12'.$this->popup_id] = 1;
					if ($install['guest_seating_prod'] && !$install['guest_seating_qty']) $this->content['form_return']['err']['err3_13'.$this->popup_id] = 1;
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'][] = "Please complete the fields below where indicated.";
					$this->content['submit_btn'] = "proposal_btn";
					return;
				}

				if ($btn == 'Save Proposal') {
					$proposal_hash = md5(global_classes::get_rand_id(32, "global_classes"));
					while (global_classes::key_exists('proposals', 'proposal_hash', $proposal_hash))
						$proposal_hash = md5(global_classes::get_rand_id(32, "global_classes"));
				} else
				    $this->db->query("DELETE FROM commission_team_user
				                      WHERE `proposal_hash` = '$proposal_hash'");

				if ($install_request && $this->p->ck(get_class($this), 'E', 'install')) {
					$install['work_order_rqst'] = time();
					if (defined('INSTALL_MOD_GROUP')) {
						$message_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('messages', 'message_hash', $message_hash))
							$message_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$work_order_queue = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('messages', 'queue_hash', $work_order_queue))
							$work_order_queue = md5(global_classes::get_rand_id(32, "global_classes"));

						$msg = "This message has been sent to all members of the ".group_name(INSTALL_MOD_GROUP)." group.\n\nA work order request has been submitted by ".user_name($this->current_hash)." for Proposal ".$general['proposal_no']." (".$general['proposal_descr'].").\n\nYou may view all pending work orders <a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'work_order_list');\" class=\"link_standard\">here</a>, or <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'show_popup_window', 'edit_work_order', 'popup_id=new_work_order', 'proposal_hash=".$proposal_hash."');\">click here</a> to create the work now.</a>";

						$this->db->query("INSERT INTO `messages`
										  (`timestamp` , `message_hash` , `queue_hash` , `sender_hash` , `group_hash` , `subject` , `message`)
										  VALUES(".time()." , '$message_hash' , '$work_order_queue' , '".$this->current_hash."' , '".INSTALL_MOD_GROUP."' , 'Work Order Request - ".$general['proposal_no']."' , '".addslashes($msg)."')");

						$install['work_order_queue'] = $work_order_queue;
					}
					if ($auto_save) {
						$this->content['html']['install_request_holder_1'] = "Quote Rqst Submitted";
						$this->content['html']['install_request_holder_2'] = date(DATE_FORMAT." ".TIMESTAMP_FORMAT, time())."&nbsp;[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'doit_cancel_rqst', 'type=work_order', 'proposal_hash=".$proposal_hash."');\" class=\"link_standard\">cancel</a></small>]";
					}
				}
				if ($design_request && $this->p->ck(get_class($this), 'E', 'design')) {
					if (defined('DESIGN_MOD_GROUP')) {
						$message_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('messages', 'message_hash', $message_hash))
							$message_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$design_queue = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('messages', 'queue_hash', $design_queue))
							$design_queue = md5(global_classes::get_rand_id(32, "global_classes"));

						$msg = "This message has been sent to all members of the ".group_name(DESIGN_MOD_GROUP)." group.\n\nA design request has been submitted by ".user_name($this->current_hash)." for Proposal ".$general['proposal_no']." (".$general['proposal_descr'].").\n\nYou may click <a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'edit_proposal', 'proposal_hash=".$proposal_hash."');\" class=\"link_standard\">here</a> to jump to the proposal.";

						$this->db->query("INSERT INTO `messages`
										  (`timestamp` , `message_hash` , `queue_hash` , `sender_hash` , `group_hash` , `subject` , `message`)
										  VALUES(".time()." , '$message_hash' , '$design_queue' , '".$this->current_hash."' , '".DESIGN_MOD_GROUP."' , 'Design Request - ".$general['proposal_no']."' , '".addslashes($msg)."')");


						$design['design_queue'] = $design_queue;
					}
					if ($auto_save) {
						$this->content['html']['design_request_holder_1'] = "Design Request Submitted";
						$this->content['html']['design_request_holder_2'] = date(DATE_FORMAT." ".TIMESTAMP_FORMAT, time())."&nbsp;[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'doit_cancel_rqst', 'type=design', 'proposal_hash=".$proposal_hash."');\">cancel</a></small>]";
					}
				}
				if ($pm_request && $this->p->ck(get_class($this), 'E', 'install')) {
					if (defined('PM_MOD_GROUP')) {
						$general['pm_request'] = time();
						$message_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('messages', 'message_hash', $message_hash))
							$message_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$general['pm_request_queue'] = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('messages', 'queue_hash', $general['pm_request_queue']))
							$general['pm_request_queue'] = md5(global_classes::get_rand_id(32, "global_classes"));

						$msg = "This message has been sent to all members of the ".group_name(PM_MOD_GROUP)." group.\n\nA project manager has been requested by ".user_name($this->current_hash)." for Proposal ".$general['proposal_no']." (".$general['proposal_descr'].").\n\nYou may click <a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'edit_proposal', 'proposal_hash=".$proposal_hash."');\" class=\"link_standard\">here</a> to jump directly to the proposal, click <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'calendar');\">here</a> to view all those proposals pending a project manager, or click <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'show_popup_window', 'view_job', 'proposal_hash=".$proposal_hash."', 'popup_id=work_order_detail')\">here</a> to view the install details for the proposal. You may also assign yourself in this step.";

						$this->db->query("INSERT INTO `messages`
										  (`timestamp` , `message_hash` , `queue_hash` , `sender_hash` , `group_hash` , `subject` , `message`)
										  VALUES(".time()." , '$message_hash' , '".$general['pm_request_queue']."' , '".$this->current_hash."' , '".PM_MOD_GROUP."' , 'Project Management Request - ".$general['proposal_no']."' , '".addslashes($msg)."')");

						$general['proj_mngr_hash'] = '';
					}
					if ($auto_save) {
						$this->content['html']['pm_request_holder_1'] = "PM Request Submitted";
						$this->content['html']['pm_request_holder_2'] = date(DATE_FORMAT." ".TIMESTAMP_FORMAT, time())."&nbsp;[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'doit_cancel_rqst', 'type=pm', 'proposal_hash=".$proposal_hash."');\">cancel</a></small>]";
					}
				}

				if (is_array($custom)) {
				    while (list($col, $val) = each($custom))
				        $general['custom_'.$col] = $val;
				}

				if ($btn == 'Save Proposal') {
					//Error checking complete, move on the processing
					$general = $this->db->prepare_query($general, "proposals", 'INSERT');
					$this->db->query("INSERT INTO `proposals`
									  (`id_hash` , `proposal_hash` , `creation_date` , ".implode(" , ", array_keys($general)).")
									  VALUES('".$this->current_hash."' , '$proposal_hash' , ".time()." , ".implode(" , ", array_values($general)).")");
					$design = $this->db->prepare_query($design, "proposal_design", 'INSERT');
					$this->db->query("INSERT INTO `proposal_design`
									  (`proposal_hash` ".(is_array($design) ? ", ".implode(" , ", array_keys($design)) : NULL).")
									  VALUES('$proposal_hash' ".(is_array($design) ? ", ".implode(" , ", array_values($design)) : NULL).")");
					$install = $this->db->prepare_query($install, "proposal_install", 'INSERT');
					$this->db->query("INSERT INTO `proposal_install`
									  (`proposal_hash` ".(is_array($install) ? ", ".implode(" , ", array_keys($install)) : NULL).")
									  VALUES('$proposal_hash' ".(is_array($install) ? ", ".implode(" , ", array_values($install)) : NULL).")");

					if ($proposal_notes) {
						$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('proposal_notes', 'note_hash', $note_hash))
							$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$proposal_notes = mb_convert_encoding($proposal_notes, 'HTML-ENTITIES');
						$this->db->query("INSERT INTO `proposal_notes`
										  (`timestamp` , `user_hash` , `proposal_hash` , `note_hash` , `type` , `note`)
										  VALUES (".time()." , '".$this->current_hash."' , '$proposal_hash' , '$note_hash' , 'p' , '$proposal_notes')");
					}
					if ($design_notes) {
						$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('proposal_notes', 'note_hash', $note_hash))
							$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$design_notes = mb_convert_encoding($design_notes, 'HTML-ENTITIES');
						$this->db->query("INSERT INTO `proposal_notes`
										  (`timestamp` , `user_hash` , `proposal_hash` , `note_hash` , `type` , `note`)
										  VALUES (".time()." , '".$this->current_hash."' , '$proposal_hash' , '$note_hash' , 'd' , '$design_notes')");
					}
					if ($install_notes) {
						$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('proposal_notes', 'note_hash', $note_hash))
							$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$install_notes = mb_convert_encoding($install_notes, 'HTML-ENTITIES');
						$this->db->query("INSERT INTO `proposal_notes`
										  (`timestamp` , `user_hash` , `proposal_hash` , `note_hash` , `type` , `note`)
										  VALUES (".time()." , '".$this->current_hash."' , '$proposal_hash' , '$note_hash' , 'i' , '$install_notes')");
					}
					if ($po_notes) {
						$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('proposal_notes', 'note_hash', $note_hash))
							$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$po_notes = mb_convert_encoding($po_notes, 'HTML-ENTITIES');
						$this->db->query("INSERT INTO `proposal_notes`
										  (`timestamp` , `user_hash` , `proposal_hash` , `note_hash` , `type` , `note`, `type_hash`)
										  VALUES (".time()." , '".$this->current_hash."' , '$proposal_hash' , '$note_hash' , 'i' , '$po_notes' , '$type_hash')");
					}

					//Update the next proposal number in line if the proposal no the user choose was in line to be auto incremented
					if ($inline)
						update_sys_data('NEXT_PROPOSAL_NUM', ++$proposal_no);

					$this->content['page_feedback'] = "Your new proposal has been created and is shown below.";
					$this->content['action'] = 'close';
					$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'edit_proposal', 'popup_id=".$this->popup_id."', 'proposal_hash=$proposal_hash', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search=$active_search', 'new=1');");
				} elseif ($btn == 'Update Proposal') {
					$valid = $this->fetch_master_record($proposal_hash);
					if ($auto_save) {
						if ($this->current_proposal['proposal_descr'] != $general['proposal_descr'])
							$this->content['html']['proposal_descr_holder'] = (strlen($this->current_proposal['proposal_descr']) > 50 ?
												substr($this->current_proposal['proposal_descr'], 0, 45)."..." : $this->current_proposal['proposal_descr']);
					}
					if ($this->current_proposal['order_type'] != $general['order_type'] || $this->current_proposal['customer_hash'] != $general['customer_hash'] || $this->current_proposal['sales_hash'] != $general['sales_hash'] || $this->current_proposal['install_addr_hash'] != $install['install_addr_hash'])
						$this->unset_final($proposal_hash);

					if ($this->p->ck(get_class($this), 'E', 'project')) {
						$general = $this->db->prepare_query($general, "proposals", 'UPDATE');
						$this->db->query("UPDATE `proposals`
										  SET `timestamp`= ".time()." , `last_change` = '".$this->current_hash."' , ".implode(" , ", $general)."
										  WHERE `proposal_hash` = '$proposal_hash'");
					}
					if ($this->p->ck(get_class($this), 'E', 'design')) {
						$design = $this->db->prepare_query($design, "proposal_design", 'UPDATE');
						$this->db->query("UPDATE `proposal_design`
										  SET ".implode(" , ", $design)."
										  WHERE `proposal_hash` = '$proposal_hash'");
					}
					if ($this->p->ck(get_class($this), 'E', 'install')) {
						$install = $this->db->prepare_query($install, "proposal_install", 'UPDATE');
						$this->db->query("UPDATE `proposal_install`
										  SET ".implode(" , ", $install)."
										  WHERE `proposal_hash` = '$proposal_hash'");
					}

					$this->content['action'] = 'continue';
					if (!$auto_save) {
						$this->content['page_feedback'] = "Your proposal has been updated.";
						//$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'edit_proposal', 'proposal_hash=".$proposal_hash."', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search=".$active_search."')");
					}
				}
                if ($commission_team == '*') {
                    for ($i = 0; $i < count($commission_team_user); $i++)
                        $this->db->query("INSERT INTO commission_team_user
                                          (`timestamp` , `proposal_hash` , `id_hash` , `rate`)
                                          VALUES (".time()." , '$proposal_hash' , '".$commission_team_user[$i]['user_hash']."' , '".bcmul($commission_team_user[$i]['rate'], .01, 6)."')");

                }

				return;
			} else {
				if ($auto_save && $this->popup_id) {
					unset($this->content['submit_btn']);
					$this->content['action'] = 'continue';
					return;
				}
				if (!$_POST['proposal_descr']) $this->content['form_return']['err']['err1_1'.$this->popup_id] = 1;
				if (!$_POST['proposal_no']) $this->content['form_return']['err']['err1_2'.$this->popup_id] = 1;
				if (!$_POST['customer']) $this->content['form_return']['err']['err1_3'.$this->popup_id] = 1;
				if (!$_POST['propose_to']) $this->content['form_return']['err']['err1_17'.$this->popup_id] = 1;
				if (!$_POST['sales_rep']) $this->content['form_return']['err']['err1_5'.$this->popup_id] = 1;
				$this->content['error'] = 1;
				$this->content['submit_btn'] = "proposal_btn";
				$this->content['form_return']['feedback'] = "You left some required fields blank! Please check the indicated fields below and try again.<br />";
			}
		}

		if ($btn == 'Delete Proposal') {
			$valid = $this->fetch_master_record($proposal_hash);

			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `purchase_order`
										WHERE `proposal_hash` = '$proposal_hash' AND purchase_order.deleted = 0");
			if ($this->current_proposal['status'] >= 3 || $this->db->result($result) > 0) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "This proposal ".($this->current_proposal['status'] == 3 ? "is active, has been booked" : "has previously been booked, is completed")." and therefore cannot be deleted.";
				return;
			}

			$this->db->query("UPDATE `proposals`
							  SET deleted = 1
							  WHERE `proposal_hash` = '$proposal_hash'");
// 			$this->db->query("DELETE FROM `proposal_design`
// 							  WHERE `proposal_hash` = '$proposal_hash'");
// 			$this->db->query("DELETE FROM `proposal_install`
// 							  WHERE `proposal_hash` = '$proposal_hash'");
// 			$this->db->query("DELETE FROM `proposal_notes`
// 							  WHERE `proposal_hash` = '$proposal_hash'");
// 			$this->db->query("DELETE FROM `tax_collected`
// 							  WHERE `proposal_hash` = '$proposal_hash'");
//             $this->db->query("DELETE FROM `commission_team_user`
//                               WHERE `proposal_hash` = '$proposal_hash'");
			$this->content['page_feedback'] = "Your proposal has been deleted.";
			$this->content['action'] = 'continue';
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search=$active_search');");
			return;
		}
	}


	function tab_error($id) {
		return "<img src=\"images/errtab.gif\" id=\"".$id."\" style=\"display:block;position:absolute;border:0;	\">";
	}

	function fetch_proposal_notes($proposal_hash, $type, $type_hash=NULL) {
		if (!$proposal_hash)
			return;

		if($type == 'o') //$type_hash != NULL) // ticket #15
			$result = $this->db->query("SELECT proposal_notes.* , users.full_name
					FROM `proposal_notes`
					LEFT JOIN users ON users.id_hash = proposal_notes.user_hash
					WHERE `proposal_hash` = '$proposal_hash' AND `type` = '$type'
					AND `type_hash` = '$type_hash'
					ORDER BY `timestamp` DESC");
		else
		$result = $this->db->query("SELECT proposal_notes.* , users.full_name
									FROM `proposal_notes`
									LEFT JOIN users ON users.id_hash = proposal_notes.user_hash
									WHERE `proposal_hash` = '$proposal_hash' AND `type` = '$type'
									ORDER BY `timestamp` DESC");
		while ($row = $this->db->fetch_assoc($result))
			$notes[] = $row;


		return $notes;
	}

	function fetch_note_record($note_hash) {
		$result = $this->db->query("SELECT proposal_notes.* , users.full_name
									FROM `proposal_notes`
									LEFT JOIN users ON users.id_hash = proposal_notes.user_hash
									WHERE `note_hash` = '$note_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_note = $row;
			$this->note_hash = $note_hash;
			$this->proposal_hash = $row['proposal_hash'];

			return true;
		}

		return false;
	}
	
	function remove_non_utf8_chars($string) {
		
		$return_string = forceUTF8($string);

		return $return_string;
	}

	function doit_line_note() {
		$note_type = $_POST['note_type'];
		$proposal_hash = $_POST['proposal_hash'];
		$note_hash = $_POST['note_hash'];
		$type_hash = $_POST['type_hash'];	// added purchase order hash (generically: type_hash) for po notes (ticket #15)
		$note = strip_tags($_POST['note']);
		$note = mb_convert_encoding($note, 'HTML-ENTITIES');
		$note = addslashes(str_replace("&Acirc;", "", $this->remove_non_utf8_chars($note)));

		if ($valid = $this->fetch_note_record($note_hash)) {
			if ($_POST['delete']) {
				$this->db->query("DELETE FROM `proposal_notes`
								  WHERE `note_hash` = '$note_hash'");
				$this->content['page_feedback'] = "Note has been removed.";
			} elseif ($note) {
				$this->db->query("UPDATE `proposal_notes`
								  SET `timestamp` = ".time()." , `user_hash` = '".$this->current_hash."' , `note` = '$note'
								  WHERE `note_hash` = '$note_hash'");
				$this->content['page_feedback'] = "Note has been saved.";
			} else
				$this->content['page_feedback'] = "No changes have been made.";
		} else {
			$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));
			while (global_classes::key_exists('proposal_notes', 'note_hash', $note_hash))
				$note_hash = md5(global_classes::get_rand_id(32, "global_classes"));
			
			// Add hash for purchase orders (ticket #15)
			if($note_type == 'o') {
				$this->db->query("INSERT INTO `proposal_notes`
							  (`timestamp` , `note_hash` , `type` , `proposal_hash` , `user_hash` , `note` , `type_hash`)
							  VALUES (".time()." , '$note_hash' , '$note_type' , '$proposal_hash' , '".$this->current_hash."' , '$note' , '$type_hash')");
			}
			else { // not a purchase order
			$this->db->query("INSERT INTO `proposal_notes`
							  (`timestamp` , `note_hash` , `type` , `proposal_hash` , `user_hash` , `note`)
							  VALUES (".time()." , '$note_hash' , '$note_type' , '$proposal_hash' , '".$this->current_hash."' , '$note')");
			}
			$this->content['page_feedback'] = "Note has been saved.";
		}
		$this->content['action'] = 'close';

		$notes = $this->fetch_proposal_notes($proposal_hash, $note_type, $type_hash);
		$this->content['html'][$note_type.'_note_holder'] = '';
		for ($i = 0; $i < count($notes); $i++)
			$this->content['html'][$note_type.'_note_holder'] .= "
			<div style=\"padding-left:5px;padding-bottom:5px;\">
				<strong>".$notes[$i]['full_name']." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $notes[$i]['timestamp']).")</strong> ".($notes[$i]['user_hash'] == $this->current_hash ? "
					[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'proposal_hash=".$proposal_hash."', 'popup_id=note_win', 'note_type=$note_type', 'note_hash=".$notes[$i]['note_hash']."', 'type_hash=".$notes[$i]['type_hash']."')\">edit</a></small>]" : NULL)." : ".stripslashes($notes[$i]['note'])."
			</div>";
		return;
	}

	function edit_note() {
		$proposal_hash 	= $this->ajax_vars['proposal_hash'];
		if ($note_hash = $this->ajax_vars['note_hash'])
			$valid = $this->fetch_note_record($note_hash);

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Notes" : "Create A New Note");

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("note_hash" => $this->note_hash,
								  "proposal_hash" => $proposal_hash,
								  "popup_id" => $this->popup_id,
								  "type_hash" => $this->ajax_vars['type_hash'],	// added for ticket #15
								  "note_type" => $this->ajax_vars['note_type']))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:15px 20px;\">
						<table >
							<tr>
								<td style=\"vertical-align:top;padding-left:20px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2".$this->popup_id."\">Note: </div>
									".$this->form->text_area("name=note", "rows=7", "cols=75", "value=".stripslashes($this->current_note['note']))."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;\">
									<div style=\"text-align:left;padding:15px;\">
										".$this->form->button("value=Save Note", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_note');")."
										&nbsp;&nbsp;".($valid ?
										$this->form->button("value=Delete Note", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_note', 'delete=1');") : NULL)."
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}


	function edit_proposal() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$popup_id = $this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$from_report = $this->ajax_vars['from_report'];
		if ($this->ajax_vars['new'])
			unset($popup_id, $this->content['popup_controls']['popup_id'], $this->popup_id);

		if ($this->ajax_vars['proposal_hash']) {
			$valid = $this->fetch_master_record($this->ajax_vars['proposal_hash'], 1);

			if ($valid === false)
				unset($this->ajax_vars['proposal_hash'], $valid);
		}
		if (!$valid || ($valid && $popup_id)) {
			$this->content['popup_controls']['popup_title'] = ($valid ? "View/Edit Proposal : ".$this->current_proposal['proposal_no'] : "Create A New Proposal");
			if ($valid) {
				$this->content['popup_controls']['popup_width'] = "950px";
				$this->content['popup_controls']['popup_resize'] = "false";
			} else
				$this->content['focus'] = 'proposal_descr';

		} else {
			$r = $this->db->query("SELECT `group_hash` , `group_name`
								   FROM `user_groups`
								   WHERE `submit_to` = 1");
			while ($row = $this->db->fetch_assoc($r)) {
				$submit_to_h[] = $row['group_hash'];
				$submit_to_n[] = $row['group_name'];
			}

			if ($submit_to_h) {
				$submit_to = "
				<div id=\"submit_to_menu\" class=\"function_menu\" >
					<div style=\"float:right;padding:5px\">
						<a href=\"javascript:void(0);\" onClick=\"toggle_display('submit_to_menu', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
					</div>
					<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Submit To Group:</div>";
				for ($i = 0; $i < count($submit_to_h); $i++)
					$submit_to .= "
					<div class=\"function_menu_item\">".$this->form->checkbox("name=submit_to[]", "value=".$submit_to_h[$i])."&nbsp;".$submit_to_n[$i]."</div>";

				$submit_to .= "
				<div class=\"function_menu_item\" style=\"padding-right:5px;\">
					<i>Message:</i><br />
					".$this->form->text_area("name=submit_msg", "cols=27", "rows=2")."
					<div style=\"padding-top:5px;text-align:right;\">
						".$this->form->button("value=Go", "onclick=toggle_display('submit_to_menu', 'none');submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_submit_to', 'continue_action=continue');setTimeout('clear_values(\'submit_msg\')', 1000);")."
					</div>
				</div>
				</div>";

			}
		}

		if ($this->p->ck(get_class($this), 'E') && $valid) {
			$this->content['jscript'][] = "
			var auto_save_timer = 0;
			var current_saved = true;
			auto_save = function() {
				if (auto_save_timer == 0) {
					auto_save_timer = window.setTimeout('doit_auto_save()', 2000);
					document.images['save_proposal_btn'].src = 'images/save_proposal.gif';
					current_saved = false;
					document.images['save_proposal_btn'].title = 'Save this proposal.';
				}
			}
			doit_auto_save = function() {
				auto_save_timer = 0;
				if (!\$('id_save_proposal_btn'))
					return;
				if (document.images['save_proposal_btn']) {
					document.images['save_proposal_btn'].src = 'images/save_proposal_done.gif';
					document.images['save_proposal_btn'].title = 'This proposal is saved and up to date.';
					current_saved = true;
					submit_form(\$('proposal_hash').form, 'proposals', 'exec_post', 'refresh_form', 'actionBtn=Update Proposal', 'auto_save=true');
					if (\$('auto_focus'))
						\$('auto_focus').value = '';
				}
			}";
		} else
            $this->content['jscript'][] = "window.setTimeout(function(){\$('proposal_descr').focus();}, 500);";

		// Always increment the next proposal number when starting a new proposal to prevent duplicates.
		// This may result in wasted proposal numbers but that is a better option that having duplicates.
		// Added the !$valid condition for trac ticket  # 379
		if (defined('NEXT_PROPOSAL_NUM') && !$valid) {
			$proposal_no = fetch_sys_var('NEXT_PROPOSAL_NUM');
			update_sys_data('NEXT_PROPOSAL_NUM', $proposal_no + 1);
		}
		if (!$proposal_no) {
			$r = $this->db->query("SELECT `proposal_no`
								   FROM `proposals`
								   ORDER BY `proposal_no` DESC
								   LIMIT 1");
			if ($proposal_no = $this->db->result($r, 0, 'proposal_no')) {
				if (defined('PROPOSAL_SEED') && ereg(PROPOSAL_SEED, $proposal_no))
					$proposal_no = str_replace(PROPOSAL_SEED, '', $proposal_no);

				$proposal_no++;
			} else
				$proposal_no = 1000;
		}

		//Find out the last sales rep
		if (!$valid) {
			$r = $this->db->query("SELECT proposals.sales_hash , u1.full_name AS sales_rep ,
								  proposals.sales_coord_hash , u2.full_name AS coord_name
								   FROM `proposals`
								   LEFT JOIN users AS u1 ON u1.id_hash = proposals.sales_hash
								   LEFT JOIN users AS u2 ON u2.id_hash = proposals.sales_coord_hash
								   WHERE proposals.id_hash = '".$this->current_hash."'
								   ORDER BY proposals.obj_id DESC
								   LIMIT 1");
			$last_input = $this->db->fetch_assoc($r);
		}

        if ($proposal_status = fetch_sys_var('PROPOSAL_STATUS_LIST')) {
            $proposal_status = __unserialize(stripslashes($proposal_status));
            $status_option = "
            <select name=\"user_status\" class=\"txtSearch\" ".($valid ? "onChange=auto_save();" : NULL).">
                <option></option>";
            for ($i = 0; $i < count($proposal_status); $i++)
                $status_option .= "
                <option ".($this->current_proposal['user_status'] == $proposal_status[$i]['tag'] ? "selected" : NULL)." style=\"color:".$proposal_status[$i]['color'].";".($proposal_status[$i]['bold'] ? "font-weight:bold;" : NULL)."\">".$proposal_status[$i]['tag']."</option>";

            $status_option .= "
            </select>";
        }

        $this->content['jscript'][] = "
        commission_split_save = function() {
            var t_r = $$('input[cid=com_rate]');
            var t_u = $$('input[cid=com_user]');
            var t_total = 0;

	        for (var i = 0; i < t_r.length; i++) {
	            var user = \$F(t_u[i]);
	            var val = parseFloat( \$F(t_r[i]).toString().replace(/[^.0-9]/g, '') );
	            if ((!val || val == 0) && user) {
	                alert('Please make sure that you have entered a commission rate for each of your commission users.');
	                return false;
	            }
	            t_total += val;
	        }
            if (t_total != 100) {
                alert('Please check that the sum of your commission rates equals 100%.');
                return false;
            }

            \$('commission_team_controls').hide();
            \$('comm_save_label').show();
            auto_save();
            window.setTimeout(function(){\$('commission_team_controls').show();\$('comm_save_label').hide();}, 3000);
        }
        ";
		$tbl = $this->form->form_tag().
		$this->form->hidden(array("proposal_hash" => $this->proposal_hash,
								  "limit" => $this->ajax_vars['limit'],
								  "active_search" => $this->ajax_vars['active_search'],
								  "p" => $p,
								  "order" => $order,
								  "order_dir" => $order_dir,
								  "auto_focus" => '',
								  "popup_id" => (!$valid ? $this->popup_id : NULL))).
		($valid ? ($from_report ? "
		<div class=\"panel\">" : NULL)."
		<h3 style=\"margin-bottom:5px;color:#00477f;margin-top:0;\" >
			Proposal ".$this->current_proposal['proposal_no']." : <span id=\"proposal_descr_holder\" >".(strlen($this->current_proposal['proposal_descr']) > 50 ?
				substr($this->current_proposal['proposal_descr'], 0, 45)."..." : $this->current_proposal['proposal_descr'])."</span>
		</h3>
		<div style=\"padding:5px 0 5px 5px;\">".(!$from_report ? "
			<a href=\"javascript:void(0);\" onClick=\"".($this->ajax_vars['back'] ? $this->ajax_vars['back'] : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search=".$this->ajax_vars['active_search']."');")."\"><img src=\"images/go_back.gif\" title=\"Close this proposal and ".($this->ajax_vars['back'] ? "return to the previous page." : "go back to proposal list.")."\" border=\"0\" /></a>
			&nbsp;" : NULL).($this->lock && $this->p->ck(get_class($this), 'E') ? "
			<a href=\"javascript:void(0);\" onClick=\"submit_form($('proposal_hash').form, 'proposals', 'exec_post', 'refresh_form', 'actionBtn=Update Proposal');\" class=\"link_standard\"><img src=\"images/save_proposal_done.gif\" id=\"id_save_proposal_btn\" name=\"save_proposal_btn\" title=\"Save this proposal.\" border=\"0\" /></a>
			&nbsp;" : NULL).(!$this->current_proposal['rm_lock'] && $this->lock && $this->p->ck(get_class($this), 'D') ? "
			<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this proposal? This action CANNOT be undone!')){submit_form($('proposal_hash').form, 'proposals', 'exec_post', 'refresh_form', 'actionBtn=Delete Proposal');}\" class=\"link_standard\"><img src=\"images/delete_proposal.gif\" title=\"Delete this proposal.\" border=\"0\" /></a>
			&nbsp;" : NULL).($submit_to && $valid ? "
			<a href=\"javascript:void(0);\" onClick=\"position_element($('submit_to_menu'), findPos(this, 'top')+20, findPos(this, 'left'));toggle_display('submit_to_menu', 'block');\" class=\"link_standard\"><img src=\"images/submit_to.gif\" title=\"Submit proposal to a group or department.\" border=\"0\" /></a>".$submit_to : NULL)."
			<span style=\"margin-left:600px;\">".$this->p->lock_stat(get_class($this))."</span>
		</div>" : NULL)."
		<div ".(!$this->proposal_hash ? "class=\"panel\"" : NULL)." id=\"main_table".(!$valid ? $this->popup_id : NULL)."\">
			<div id=\"feedback_holder".(!$valid ? $this->popup_id : NULL)."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".(!$valid ? $this->popup_id : NULL)."\"></p>
			</div>
			<ul id=\"maintab".$this->popup_id."\" class=\"shadetabs\">".($this->p->ck(get_class($this), 'V', 'project') ? "
				<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent1".$this->popup_id."\" onClick=\"expandcontent(this);\" >Project Info</a></li>" : NULL).($this->p->ck(get_class($this), 'V', 'design') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent2".$this->popup_id."\" onClick=\"expandcontent(this);\" >Design</a></li>" : NULL).($this->p->ck(get_class($this), 'V', 'install') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent3".$this->popup_id."\" onClick=\"expandcontent(this);\">Install</a></li>" : NULL).($valid ? ($this->p->ck(get_class($this), 'L', 'item_details') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent4\" onClick=\"expandcontent(this);\">Item Details</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'purchase_order') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent5\" onClick=\"expandcontent(this);\">Purchase Orders</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'customer_invoice') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent6\" onClick=\"expandcontent(this);\">Receivables</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'vendor_payables') || $this->p->ck(get_class($this), 'L', 'memo_costing') ||$this->p->ck(get_class($this), 'L', 'commissions_paid') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent7\" onClick=\"expandcontent(this);\">Payables</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'doc_vault') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent8\" onClick=\"expandcontent(this);\">File Vault</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'pm_module') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent9\" onClick=\"expandcontent(this);\">Service & Punch</a></li>" : NULL).($this->p->ck(get_class($this), 'V', 'ledger') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent10\" onClick=\"expandcontent(this);\">Ledger</a></li>" : NULL) : NULL)."
			</ul>";
		if ($this->p->ck(get_class($this), 'V', 'project')) {
			unset($this->cust->class_fields);
			$this->cust->fetch_class_fields('project', NULL, 1);

			$tbl .= "
			<div id=\"tcontent1".$this->popup_id."\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"".($valid ? "main" : "popup")."_tab_table\">
					<tr>
						<td style=\"background-color:#ffffff;padding-top:10px;vertical-align:top;\" colspan=\"2\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"width:50%;font-weight:bold;\">
										<span id=\"err1_1".$this->popup_id."\">Proposal Description: *</span>
										<div style=\"padding-top:5px;\">
											".$this->form->text_box("name=proposal_descr",
																	"value=".stripslashes($this->current_proposal['proposal_descr']),
																	"autocomplete=off",
																	"size=45",
																	"maxlength=255",
																	"style=font-weight:bold;",
																	($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "onChange=auto_save();") : NULL))."
										</div>
									</td>
									<td style=\"width:50%;font-weight:bold;\" >
										<span id=\"err1_2".$this->popup_id."\">Proposal No: *</span>
										<div style=\"padding-top:5px;\">
											".$this->form->text_box("name=proposal_no", "value=".($this->current_proposal['proposal_no'] ? $this->current_proposal['proposal_no'] : (defined('PROPOSAL_SEED') ? PROPOSAL_SEED : NULL).$proposal_no), "autocomplete=off", "size=15", "maxlength=12", "style=font-weight:bold;", ($valid ? "readonly" : ''))."
										</div>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\">
								<tr>
									<td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err1_3".$this->popup_id."\">Customer: *</td>
									<td style=\"vertical-align:top;\">
										".$this->form->text_box("name=customer",
																"value=".stripslashes($this->current_proposal['customer']),
																"autocomplete=off",
																"size=30",
																($valid && $this->current_proposal['status'] >= 1 ? "title=Customer cannot be changed because the proposal has been booked".($this->current_proposal['status'] > 1 ? " and invoiced" : NULL)."." : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project') && !$this->current_proposal['status']) ? "onFocus=".($valid ? "\$('auto_focus').value='customer';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'customer', 'customer_hash', 1);}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project') && !$this->current_proposal['status']) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'customer', 'customer_hash', 1);}" : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project') && !$this->current_proposal['status']) ? "onBlur=key_clear();".($valid ? "auto_save();" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project') && !$this->current_proposal['status']) ? "onKeyDown=if(event.keyCode!=9){clear_values('customer_hash');clear_innerHTML('customer_contact_holder', 'customer_contact_details_holder', 'customer_details_holder');}" : NULL),
                                                                ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') || $this->current_proposal['status'] >= 1 ? "readonly" : NULL) : NULL)
																).
										$this->form->hidden(array("customer_hash" => $this->current_proposal['customer_hash'],
																  "bill_to_hash"  => $this->current_proposal['bill_to_hash'])).($this->p->ck('customers', 'A') && !$this->current_proposal['status'] ? "
										<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_customer', 'parent_win_open', 'parent_win=".$this->popup_id."', 'popup_id=sub_win', 'jscript_action=javascript:void(0);');\"><img src=\"images/plus.gif\" border=\"0\" title=\"Create a new customer\"></a>" : NULL)."
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:100px;vertical-align:top;\" ></td>
									<td id=\"customer_details_holder\">" .
									( $this->current_proposal['customer_hash'] ?
										$this->innerHTML_customer($this->current_proposal['customer_hash'], ( $this->current_proposal['bill_to_hash'] ? 'bill_to' : 'customer' ), 'customer_details_holder', NULL, NULL, $this->current_proposal['bill_to_hash']) : NULL
									) . "
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err1_12".$this->popup_id."\">Customer PO:</td>
									<td>".$this->form->text_box("name=customer_po",
																"value=".$this->current_proposal['customer_po'],
																"size=30", "maxlength=64",
																($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "onChange=auto_save();") : NULL))."
									</td>
								</tr>";
                                if ($this->cust->class_fields['project'][1]) {
                                    $sector_fields =& $this->cust->class_fields['project'][1];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td>".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
                                }

                                $tbl .= "
							</table>
						</td>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"5\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"text-align:right;width:100px;\" id=\"err1_5".$this->popup_id."\">Sales Rep: *</td>
									<td >
										".$this->form->text_box("name=sales_rep",
																"value=".($this->current_proposal['sales_rep'] ? stripslashes($this->current_proposal['sales_rep']) : stripslashes($last_input['sales_rep'])),
																"autocomplete=off",
																"size=30",
																($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onFocus=".($valid ? "\$('auto_focus').value='sales_rep';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'sales_rep', 'sales_hash', 1);}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'sales_rep', 'sales_hash', 1);}" : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onBlur=key_clear();".($valid ? "auto_save();" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyDown=if(event.keyCode!=9){clear_values('sales_hash');}" : NULL)).
										$this->form->hidden(array("sales_hash" => ($this->current_proposal['sales_hash'] ? $this->current_proposal['sales_hash'] : $last_input['sales_hash'])))."
									</td>
								</tr>
                                <tr>
                                    <td style=\"text-align:right;width:100px;\" id=\"err1_5a".$this->popup_id."\">Secondary Rep: </td>
                                    <td >
                                        ".$this->form->text_box("name=sales_rep_2",
                                                                "value=".($this->current_proposal['sales_rep_2'] ? stripslashes($this->current_proposal['sales_rep_2']) : stripslashes($last_input['sales_rep_2'])),
                                                                "autocomplete=off",
                                                                "size=30",
                                                                ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : NULL) : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onFocus=".($valid ? "\$('auto_focus').value='sales_rep_2';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'sales_rep_2', 'sales_hash_2', 1);}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'sales_rep_2', 'sales_hash_2', 1);}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onBlur=key_clear();".($valid ? "auto_save();" : NULL) : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyDown=if(event.keyCode!=9){clear_values('sales_hash_2');}" : NULL)).
                                        $this->form->hidden(array("sales_hash_2" => ($this->current_proposal['sales_hash_2'] ? $this->current_proposal['sales_hash_2'] : $last_input['sales_hash_2'])))."
                                    </td>
                                </tr>
								<tr>
									<td style=\"text-align:right;width:100px;\" id=\"err1_7".$this->popup_id."\">Sales Coord: </td>
									<td >
										".$this->form->text_box("name=sales_coord",
																"autocomplete=off",
																"value=".($this->current_proposal['sales_coord'] ? stripslashes($this->current_proposal['sales_coord']) : stripslashes($last_input['coord_name'])),
																"size=30",
																($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : NULL) : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onFocus=".($valid ? "\$('auto_focus').value='sales_coord';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'sales_coord', 'sales_coord_hash', 1);}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'sales_coord', 'sales_coord_hash', 1);}" : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onBlur=key_clear();".($valid ? "auto_save();" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyDown=if(event.keyCode!=9){clear_values('sales_coord_hash');}" : NULL)).
										$this->form->hidden(array("sales_coord_hash" => ($this->current_proposal['sales_coord_hash'] ? $this->current_proposal['sales_coord_hash'] : $last_input['sales_coord_hash'])))."
									</td>
								</tr>";
								if ($this->cust->class_fields['project'][2]) {
									$sector_fields =& $this->cust->class_fields['project'][2];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td>".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
								}
								$tbl .= "
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\">
								<tr>
									<td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err1_17".$this->popup_id."\">Propose To: *</td>
									<td style=\"vertical-align:top;\">
										".$this->form->text_box("name=propose_to",
																"value=".stripslashes($this->current_proposal['propose_to']),
																"autocomplete=off",
																"size=30",
																($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onFocus=".($valid ? "\$('auto_focus').value='propose_to';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'propose_to', 'propose_to_hash', 1)}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'propose_to', 'propose_to_hash', 1);}" : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onBlur=key_clear();".($valid ? "auto_save();" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyDown=if(event.keyCode!=9){clear_values('propose_to_hash');clear_innerHTML('propose_to_details_holder');}" : NULL)).
										$this->form->hidden(array("propose_to_hash" => ($this->current_proposal['propose_to_hash'] ? $this->current_proposal['propose_to_hash'] : '')))."
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:100px;vertical-align:top;\" ></td>
									<td id=\"propose_to_details_holder\">" .
									( $this->current_proposal['propose_to_hash'] ?
										$this->innerHTML_customer($this->current_proposal['propose_to_hash'], 'propose_to', 'propose_to_details_holder') : NULL
									) . "
									</td>
								<tr>
									<td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err1_4".$this->popup_id."\">Contact:</td>
									<td >
										<div id=\"customer_contact_holder\">" .
    									( $this->current_proposal ?
    										$this->innerHTML_customer($this->current_proposal['customer_hash'], 'customer', 'customer_contact_holder', $this->current_proposal['customer_contact']) : NULL
    									) . "
										</div>
										<div id=\"customer_contact_details_holder\" style=\"padding-top:5px;\">" .
    									( $this->current_proposal ?
    										$this->innerHTML_customer_contact($this->current_proposal['customer_contact'], 'customer', 'customer_contact_details_holder') : NULL
    									) . "
										</div>
									</td>
								</tr>";
                                if ($this->cust->class_fields['project'][3]) {
                                    $sector_fields =& $this->cust->class_fields['project'][3];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td>".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
                                }

                                $tbl .= "
							</table>
						</td>
						<td style=\"background-color:#ffffff;padding-top:10px;vertical-align:top;width:50%\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"text-align:right;width:100px;\" id=\"err1_9".$this->popup_id."\">A&D Firm:</td>
									<td >".
										$this->form->text_box("name=arch",
															  "autocomplete=off",
															  "value=".stripslashes($this->current_proposal['arch']),
															  "size=30",
															  ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : NULL) : NULL),
															  (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onFocus=".($valid ? "\$('auto_focus').value='arch';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'arch', 'arch_hash', 1);}" : NULL),
                                                              (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'arch', 'arch_hash', 1);}" : NULL),
															  (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onBlur=key_clear();".($valid ? "auto_save();" : NULL) : NULL),
															  (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "onKeyDown=if(event.keyCode!=9){clear_values('arch_hash');clear_innerHTML('arch_contact_holder', 'arch_contact_details_holder', 'arch_details_holder');}" : NULL)).
										$this->form->hidden(array("arch_hash" => ($this->current_proposal['arch_hash'] ? $this->current_proposal['arch_hash'] : ''))).($this->p->ck('arch_design', 'A') ? "
										<a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design', 'sf_loadcontent', 'show_popup_window', 'edit_arch', 'parent_win_open', 'parent_win=".$this->popup_id."', 'popup_id=sub_win', 'jscript_action=javascript:void(0);');\"><img src=\"images/plus.gif\" border=\"0\" title=\"Create a new A&D firm\"></a>" : NULL)."
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:100px;vertical-align:top;\" ></td>
									<td>
										<div id=\"arch_details_holder\">" .
										( $this->current_proposal && $this->current_proposal['arch_hash'] ?
    										$this->innerHTML_customer($this->current_proposal['arch_hash'], 'arch', 'arch_details_holder') : NULL
    									) . "
										</div>
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err1_10".$this->popup_id."\">Contact:</td>
									<td >
										<div id=\"arch_contact_holder\">" .
    									( $this->current_proposal && $this->current_proposal['arch_hash'] ?
    										$this->innerHTML_customer($this->current_proposal['arch_hash'], 'arch', 'arch_contact_holder', $this->current_proposal['arch_contact']) : NULL
    									) . "
										</div>
										<div id=\"arch_contact_details_holder\" style=\"padding-top:5px;\">" .
    									( $this->current_proposal && $this->current_proposal['arch_contact'] ?
    										$this->innerHTML_customer_contact($this->current_proposal['arch_contact'], 'arch', 'arch_contact_details_holder') : NULL
    									) . "
										</div>
									</td>
								</tr>";
                                if ($this->cust->class_fields['project'][4]) {
                                    $sector_fields =& $this->cust->class_fields['project'][4];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td>".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
                                }

                                $tbl .= "
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"text-align:right;width:20%;vertical-align:top;\" id=\"err1_13".$this->popup_id."\">Expiration Date:</td>
									<td style=\"vertical-align:top;width:60%;\">
										<div id=\"expiration_date_holder".$this->popup_id."\">";
										if (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')))
											$this->content["jscript"][] = "setTimeout('DateInput(\'expiration_date\', \'true\', \'YYYY-MM-DD\', \'".($this->current_proposal['expiration_date'] && date("Y", strtotime($this->current_proposal['expiration_date'])) > 2000 ? $this->current_proposal['expiration_date'] : date("Y-m-d", time()+2592000))."\', 1, \'expiration_date_holder".$this->popup_id."\', \'\', \'".($valid ? "auto_save()" : NULL)."\')', 18);";
										else
											$tbl .=
											($this->current_proposal['expiration_date'] && $this->current_proposal['expiration_date'] != '0000-00-00' ?
												date(DATE_FORMAT, strtotime($this->current_proposal['expiration_date'])) : NULL);
										$tbl .= "
										</div>
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;vertical-align:top;\" id=\"err1_14".$this->popup_id."\">Probable Close Date:</td>
									<td style=\"vertical-align:top;\">
										<div id=\"close_date_holder".$this->popup_id."\">";
										if (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'project')))
											$this->content["jscript"][] = "setTimeout('DateInput(\'close_date\', false, \'YYYY-MM-DD\', \'".($this->current_proposal['close_date'] && date("Y", strtotime($this->current_proposal['close_date'])) > 2000 ? $this->current_proposal['close_date'] : NULL)."\', 1, \'close_date_holder".$this->popup_id."\', \'\', \'".($valid ? "auto_save()" : NULL)."\')', 19);";

										else
											$tbl .=
											($this->current_proposal['close_date'] && $this->current_proposal['close_date'] != '0000-00-00' ?
												date(DATE_FORMAT, strtotime($this->current_proposal['close_date'])) : NULL);
										$tbl .= "
										</div>
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;vertical-align:top;\" id=\"err1_15".$this->popup_id."\">Probability:</td>
									<td style=\"vertical-align:top;\">".$this->form->text_box("name=booking_probability",
																							  "value=".($this->current_proposal['booking_probability'] * 100),
																							  "size=2",
																							  "maxlength=3",
																							  "style=text-align:right;",
																							  ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "onChange=auto_save();") : NULL))."&nbsp;%
									</td>
                                </tr>";

                                $r = $this->db->query("SELECT t1.name , t1.commission_hash
                                                       FROM commission_tables t1
                                                       WHERE t1.active = 1 AND t1.type = 'T'");
                                while ($row = $this->db->fetch_assoc($r)) {
                                    $commission_t[] = $row['name'];
                                    $commission_h[] = $row['commission_hash'];
                                }
                                $commission_t[] = "Custom Commission Team";
                                $commission_h[] = "*";

                                $tbl .= "
                                <tr>
                                    <td style=\"text-align:right;vertical-align:top;\" >Commission Team:</td>
                                    <td style=\"vertical-align:top;\">".$this->form->select("commission_team",
                                                                                            $commission_t,
                                                                                            ($this->current_proposal['commission_team'] ? $this->current_proposal['commission_team'] : (isset($commission_team_user) ? '*' : NULL)),
                                                                                            $commission_h,
                                                                                            "onChange=if(this.options[this.selectedIndex].value=='*'){toggle_display('custom_commission_team', 'block');}else{toggle_display('custom_commission_team', 'none');}".($valid && $this->p->ck(get_class($this), 'E', 'project') ? "if(this.options[this.selectedIndex].value != '*'){auto_save();}" : NULL),
                                                                                            "style=width:200px;")."
                                    </td>
                                </tr>
                                <tr id=\"custom_commission_team\" style=\"display:".($this->proposal_hash && $this->current_proposal['commission_team'] == '*' ? 'block' : 'none')."\">
                                    <td style=\"text-align:left;vertical-align:top;width:90%\" colspan=\"2\">
                                        <div style=\"float:right;".($this->proposal_hash ? "margin-left:25px;width:90%" : "width:100%;")."\">
	                                        <fieldset >
	                                            <legend id=\"err_commteam\">Commission Team Members</legend>
	                                            <div style=\"padding-left:8px;padding-top:8px;\" id=\"commission_team_members_proposal\">";

	                                    if ($this->proposal_hash && $this->current_proposal['commission_team'] == '*') {
		                                    $r = $this->db->query("SELECT t1.id_hash AS user_hash , t1.rate , t2.full_name AS team_member
		                                                           FROM commission_team_user t1
		                                                           LEFT JOIN users t2 ON t2.id_hash = t1.id_hash
		                                                           WHERE t1.proposal_hash = '".$this->proposal_hash."'");
		                                    while ($row = $this->db->fetch_assoc($r)) {
	                                            $rand_id = rand(5000, 500000);

	                                            $tbl .=
	                                            $this->form->hidden(array('commission_rand[]'  =>  $rand_id))."
	                                            <div style=\"margin:5px;\" id=\"comm_row_".$rand_id."\">
	                                                <span style=\"margin-right:10px;\">
	                                                    User:&nbsp;&nbsp;".
	                                                    $this->form->text_box("name=commission_user_".$rand_id,
	                                                                          "value=".stripslashes($row['team_member']),
	                                                                          "autocomplete=off",
	                                                                          "size=25",
	                                                                          "cid=com_user",
	                                                                          ($this->p->ck(get_class($this), 'E', 'project') ? "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'), '".$this->popup_id."');if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}" : NULL),
	                                                                          ($this->p->ck(get_class($this), 'E', 'project') ? "onKeyUp=if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}" : NULL),
	                                                                          ($this->p->ck(get_class($this), 'E', 'project') ? "onBlur=key_clear();" : NULL),
	                                                                          (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : NULL)).
	                                                    $this->form->hidden(array('commission_hash_'.$rand_id =>  $row['user_hash']))."
	                                                </span>
	                                                <span>
	                                                    Rate: ".
	                                                    $this->form->text_box("name=commission_user_rate_".$rand_id,
	                                                                          "value=".trim_decimal(bcmul($row['rate'], 100, 6)),
	                                                                          "size=3",
	                                                                          "style=text-align:right;",
	                                                                          "cid=com_rate",
	                                                                          "onFocus=calculate_balance(this, 'cid=com_rate');this.select();",
	                                                                          (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "onChange=if(isNumeric(this.value)==false){this.value='';}"))."
	                                                </span>
	                                                <span style=\"margin-left:5px;\">
                                                        <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"commission_user_rm('".$rand_id."');\"><img src=\"images/b_drop_small.gif\" border=\"0\" title=\"Remove\" /></a>
	                                                </span>
	                                            </div>";
		                                    }

	                                    } else {
	                                        $rand_id = rand(5000, 500000);

	                                        $tbl .= $this->form->hidden(array('commission_rand[]'  =>  $rand_id))."
	                                        <div style=\"margin:5px;\" id=\"comm_row_".$rand_id."\">
	                                            <span style=\"margin-right:10px;\">
	                                                User:&nbsp;&nbsp;".
	                                                $this->form->text_box("name=commission_user_".$rand_id,
	                                                                      "value=",
	                                                                      "autocomplete=off",
	                                                                      "size=".($this->proposal_hash ? 25 : 18),
	                                                                      "cid=com_user",
	                                                                      "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'), '".$this->popup_id."');if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}",
	                                                                      "onKeyUp=if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}",
	                                                                      "onBlur=key_clear();if(this.value && \$F('commission_hash_".$rand_id."') && \$F('commission_user_rate_".$rand_id."')){\$('first_comm_user_rm_".$rand_id."').show();}").
	                                                $this->form->hidden(array('commission_hash_'.$rand_id =>  ''))."
	                                            </span>
	                                            <span>
	                                                Rate: ".
	                                                $this->form->text_box("name=commission_user_rate_".$rand_id,
	                                                                      "value=",
	                                                                      "size=3",
	                                                                      "style=text-align:right;",
	                                                                      "cid=com_rate",
	                                                                      "onBlur=if(this.value && \$F('commission_hash_".$rand_id."')){\$('first_comm_user_rm_".$rand_id."').show();}",
	                                                                      "onFocus=calculate_balance(this, 'cid=com_rate');this.select();",
	                                                                      "onChange=if(isNumeric(this.value)==false){this.value='';}")."
	                                            </span>
								                <span style=\"margin-left:5px;display:none;\" id=\"first_comm_user_rm_".$rand_id."\">
								                    <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"commission_user_rm('".$rand_id."');\"><img src=\"images/b_drop_small.gif\" border=\"0\" title=\"Remove\" /></a>
								                </span>
	                                        </div>";
	                                    }

                                $tbl .= "
                                                </div>".(!$this->proposal_hash || ($valid && $this->p->ck(get_class($this), 'E', 'project')) ? "
	                                            <div style=\"float:right;".($this->proposal_hash ? "margin-right:20px" : "margin-right:5px").";margin-top:3px;\" id=\"commission_team_controls\">
	                                                [<a href=\"javascript:void(0);\" onClick=\"commission_split(1, '".$this->proposal_hash."');\" class=\"link_standard\">Next</a>]".($this->proposal_hash ? "
	                                                &nbsp;&nbsp;
                                                    [<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"commission_split_save();\">Save</a>]" : NULL)."
	                                            </div>".($this->proposal_hash ? "
	                                            <div style=\"float:right;margin-right:".($this->proposal_hash ? "20" : "5")."px;margin-top:3px;display:none;\" id=\"comm_save_label\"><img src=\"images/ajax-loader.gif\" border=\"0\" /></div>" : NULL) : NULL)."
                                            </fieldset>
                                        </div>
                                    </td>
                                </tr>";

                                if ($this->cust->class_fields['project'][5]) {
                                    $sector_fields =& $this->cust->class_fields['project'][5];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td>".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
                                }

                                $tbl .= "
							</table>
						</td>
						<td style=\"background-color:#ffffff;padding-top:10px;vertical-align:top;width:50%\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"text-align:right;width:30%;\" id=\"err1_16".$this->popup_id."\">Order Type:</td>
									<td id=\"order_type_holder\">".($valid && (!$this->p->ck(get_class($this), 'E', 'project') || $this->current_proposal['status'] > 0) ?
										($this->current_proposal['order_type'] == 'D' ?
											"Direct" : "Normal") : $this->form->select("order_type", array("Normal", "Direct"), $this->current_proposal['order_type'], array('N', 'D'), "blank=true", ($valid ? "onChange=auto_save();" : NULL)))."
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:30%;\">Proposal Status:</td>
									<td>".$status_option."</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:30%;\">Status Note:</td>
									<td>".$this->form->text_box("name=user_status_comment",
                            									"value=".$this->current_proposal['user_status_comment'],
                            									"maxlength=128",
										                        "size=25",
                        										($valid ? "onChange=auto_save();" : NULL))."
                                    </td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:30%;\">Active/Archive:</td>
									<td>".$this->form->select("archive", array("Active", "Archived"), $this->current_proposal['archive'], array(0, 1), ($valid ? "onChange=auto_save();" : NULL))."</td>
                                </tr>";
                                if ($this->cust->class_fields['project'][6]) {
                                    $sector_fields =& $this->cust->class_fields['project'][6];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"text-align:right;width:30%;vertical-align:top;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td>".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'project') ? "readonly" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
                                }

                                $tbl .= "
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;padding-left:30px;padding-bottom:10px;\" colspan=\"2\">
							<div style=\"padding-bottom:5px;\" id=\"err1_11".$this->popup_id."\">Proposal Notes:</div>";
						if ($this->proposal_hash) {
							$tbl .= "
							<div style=\"margin-left:10px;padding-bottom:5px;\">
								[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'note_type=p', 'proposal_hash=".$this->proposal_hash."', 'popup_id=note_win')\">add a note</a></small>]
							</div>
							<div style=\"padding-top:5px;height:75px;width:90%;overflow:auto;border:1px outset #cccccc;background-color:#efefef;\" id=\"p_note_holder\">";
							$notes = $this->fetch_proposal_notes($this->proposal_hash, 'p');
							for ($i = 0; $i < count($notes); $i++)
								$tbl .= "
								<div style=\"padding-left:5px;padding-bottom:5px;\">
									<strong>".$notes[$i]['full_name']." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $notes[$i]['timestamp']).")</strong> ".($notes[$i]['user_hash'] == $this->current_hash ? "
										[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'note_type=p', 'proposal_hash=".$this->proposal_hash."', 'popup_id=note_win', 'note_hash=".$notes[$i]['note_hash']."')\">edit</a></small>]" : NULL)." : ".stripslashes($notes[$i]['note'])."
								</div>";

							$tbl .= "
							</div>";
						} else
							$tbl .= "
							<div style=\"padding-top:5px;\">
							".$this->form->text_area("name=proposal_notes",
														 "value=",
														 "cols=100",
														 "rows=4")."
							</div>";

						$tbl .= "
						</td>
					</tr>
				</table>
			</div>";
		}
		if ($this->p->ck(get_class($this), 'V', 'design')) {
			unset($this->cust->class_fields);
            $this->cust->fetch_class_fields('design', NULL, 1);

			$tbl .= "
			<div id=\"tcontent2".$this->popup_id."\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"".($valid ? "main" : "popup")."_tab_table\">
					<tr>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" class=\"smallfont\">
								<tr>
									<td style=\"text-align:right;width:30%;\" id=\"err1_6".$this->popup_id."\">Designer: </td>
									<td >
										".$this->form->text_box("name=designer",
																"autocomplete=off",
																"value=".stripslashes($this->current_proposal['designer']),
																($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "readonly" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'design')) ? "onFocus=".($valid ? "\$('auto_focus').value='designer';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'designer', 'designer_hash', 1);}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'design')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'designer', 'designer_hash', 1);}" : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'design')) ? "onBlur=key_clear();setTimeout('if(\$F(\'designer_hash\') && $(\'design_request\')){\$(\'design_request\').checked=0};', 500);".($valid ? "auto_save();" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'design')) ? "onKeyDown=if(event.keyCode!=9){clear_values('designer_hash');}" : NULL)).
										$this->form->hidden(array("designer_hash" => ($this->current_proposal['designer_hash'] ? $this->current_proposal['designer_hash'] : '')))."
									</td>
								</tr>".(defined('DESIGN_MOD_GROUP') ? "
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;\" id=\"design_request_holder_1\">".($this->current_proposal['design_queue'] ?
										($this->current_proposal['design_queue']['receive_time'] ?
											"Design Request Received" : "Design Request Submitted") : "Submit Design Request?")."
									</td>
									<td style=\"background-color:#ffffff;\" id=\"design_request_holder_2\">".($this->current_proposal['design_queue'] ?
										($this->current_proposal['design_queue']['receive_time'] ?
											date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $this->current_proposal['design_queue']['receive_time']) : date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $this->current_proposal['design_queue']['timestamp'])."&nbsp;[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'doit_cancel_rqst', 'type=design', 'proposal_hash=".$this->proposal_hash."');\">cancel</a></small>]") : $this->form->checkbox("name=design_request", "value=1", ($this->p->ck(get_class($this), 'E', 'design') ? "onClick=if(this.checked){\$('designer').value='';\$('designer_hash').value='';}".($valid ? "auto_save();" : NULL) : NULL)))."
									</td>
								</tr>" : NULL)."
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;padding-top:25px;\" id=\"err2_1".$this->popup_id."\">Drawings Due:</td>
									<td style=\"background-color:#ffffff;padding-top:25px;\">
										<div id=\"dwg_date_holder".$this->popup_id."\">";
										if (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'design')))
											$this->content["jscript"][] = "setTimeout('DateInput(\'dwg_date\', false, \'YYYY-MM-DD\', \'".($this->current_proposal['dwg_date'] && date("Y", strtotime($this->current_proposal['dwg_date'])) > 2000 ? $this->current_proposal['dwg_date'] : NULL)."\', 1, \'dwg_date_holder".$this->popup_id."\', \'\', \'".($valid ? "auto_save()" : NULL)."\')', 20);";
										else
											$tbl .=
											($this->current_proposal['dwg_date'] && $this->current_proposal['dwg_date'] != '0000-00-00' ?
												date(DATE_FORMAT, strtotime($this->current_proposal['dwg_date'])) : NULL);
										$tbl .= "
										</div>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;\" id=\"err2_2".$this->popup_id."\">BOM Due:</td>
									<td style=\"background-color:#ffffff;\">
										<div id=\"bom_date_holder".$this->popup_id."\">";
										if (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'design')))
											$this->content["jscript"][] .= "setTimeout('DateInput(\'bom_date\', false, \'YYYY-MM-DD\', \'".($this->current_proposal['bom_date'] && date("Y", strtotime($this->current_proposal['bom_date'])) > 2000 ? $this->current_proposal['bom_date'] : NULL)."\', 1, \'bom_date_holder".$this->popup_id."\', \'\', \'".($valid ? "auto_save()" : NULL)."\')', 21);";
										else
											$tbl .=
											($this->current_proposal['bom_date'] && $this->current_proposal['bom_date'] != '0000-00-00' ?
												date(DATE_FORMAT, strtotime($this->current_proposal['bom_date'])) : NULL);
										$tbl .= "
										</div>
									</td>
								</tr>";
                                if ($this->cust->class_fields['design'][1]) {
                                    $sector_fields =& $this->cust->class_fields['design'][1];

                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"text-align:right;width:100px;vertical-align:top;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td>".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "readonly" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
                                }
                            $tbl .= "
							</table>
						</td>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;\">
							<table cellspacing=\"1\" cellpadding=\"5\" style=\"width:100%;margin-top:0;\" class=\"smallfont\">
								<tr>
									<td style=\"background-color:#ffffff;width:33%;\">
										".$this->form->checkbox("name=option_value_eng", "value=1", ($this->current_proposal['option_value_eng'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Value Engineer
									</td>
									<td style=\"background-color:#ffffff;width:33%;\">
										".$this->form->checkbox("name=option_field_msr_req", "value=1", ($this->current_proposal['option_field_msr_req'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Field Measure Rqrd
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_inv_req", "value=1", ($this->current_proposal['option_inv_req'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Inventory Rqrd
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_install_tag", "value=1", ($this->current_proposal['option_install_tag'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Install Tagging
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_typ", "value=1", ($this->current_proposal['option_typ'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Typicals/Isometrics
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_pres_brds", "value=1", ($this->current_proposal['option_pres_brds'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Presentation Boards
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;width:33%;\">
										".$this->form->checkbox("name=option_spec_tag", "value=1", ($this->current_proposal['option_spec_tag'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Spec Tagging
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_bldg_shell_prvd", "value=1", ($this->current_proposal['option_bldg_shell_prvd'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Building Shell Prvd
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;text-align:left;padding-left:25px;\" colspan=\"2\">
										BID: &nbsp;
										".$this->form->checkbox("name=option_bid", "value=1", ($this->current_proposal['option_bid'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."
										&nbsp;&nbsp;GSA:&nbsp;
										".$this->form->checkbox("name=option_gsa", "value=1", ($this->current_proposal['option_gsa'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."
										&nbsp;&nbsp;
										XPRESS:&nbsp;
										".$this->form->checkbox("name=option_xpress", "value=1", ($this->current_proposal['option_xpress'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."
									</td>
								</tr>";
                                if ($this->cust->class_fields['design'][2]) {
                                    $sector_fields =& $this->cust->class_fields['design'][2];
                                    $length = ceil(count($sector_fields) / 2);
                                    $j = 0;
                                    for ($i = 0; $i < $length; $i++) {
                                    	$index = $i + $j;
                                        $tbl .= "
		                                <tr>
		                                    <td style=\"background-color:#ffffff;width:33%;\" ".($sector_fields[$index] ? "id=\"err_custom".$sector_fields[$index]['obj_id']."\"" : NULL).">".($sector_fields[$index] ?
	                                            $this->cust->build_input($sector_fields[$index],
	                                                                     $this->current_proposal[$sector_fields[$index]['col_name']],
	                                                                     ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
                                                ".$sector_fields[$index]['field_name'] : "&nbsp;")."
		                                    </td>
		                                    <td style=\"background-color:#ffffff;\" ".($sector_fields[$index+1] ? "id=\"err_custom".$sector_fields[$index+1]['obj_id']."\"" : NULL).">".($sector_fields[$index+1] ?
                                                $this->cust->build_input($sector_fields[$index+1],
                                                                         $this->current_proposal[$sector_fields[$index+1]['col_name']],
                                                                         ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
                                                ".$sector_fields[$index+1]['field_name'] : "&nbsp;")."
		                                    </td>
		                                </tr>";
                                        $j++;
                                    }
                                }
                            $tbl .= "
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;vertical-align:top;\" colspan=\"2\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" class=\"smallfont\">
								<tr>
									<td style=\"width:85px;text-align:right;\" id=\"err2_3".$this->popup_id."\">No. Wrkstns: </td>
									<td>".$this->form->text_box("name=wrkstn_qty",
																"size=2",
																"maxlength=11",
																"value=".$this->current_proposal['wrkstn_qty'],
																($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "readonly" : "onChange=auto_save();") : NULL))."
									</td>
									<td style=\"text-align:right;\" id=\"err2_6".$this->popup_id."\">Product: </td>
									<td >".$this->form->text_box("name=wrkstn_prod",
																 "size=75",
																 "maxlength=255",
																 "value=".stripslashes($this->current_proposal['wrkstn_prod']),
																 ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "readonly" : "onChange=auto_save();") : NULL))."
									</td>
								</tr>
								<tr>
									<td style=\"width:85px;text-align:right;\" id=\"err2_4".$this->popup_id."\">No. Offices: </td>
									<td>".$this->form->text_box("name=office_qty",
																"size=2",
																"maxlength=11",
																"value=".$this->current_proposal['office_qty'],
																($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "readonly" : "onChange=auto_save();") : NULL))."
									</td>
									<td style=\"text-align:right;\" id=\"err2_7".$this->popup_id."\">Product: </td>
									<td >".$this->form->text_box("name=office_prod",
																 "size=75",
																 "maxlength=255",
																 "value=".stripslashes($this->current_proposal['office_prod']),
																 ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "readonly" : "onChange=auto_save();") : NULL))."
									</td>
								</tr>
								<tr>
									<td style=\"width:85px;text-align:right;\" id=\"err2_5".$this->popup_id."\">Ancillary: </td>
									<td>".$this->form->checkbox("name=option_ancillary", "value=1", ($this->current_proposal['option_ancillary'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "onClick=auto_save();") : NULL))."</td>
									<td style=\"text-align:right;\" id=\"err2_8".$this->popup_id."\">Product: </td>
									<td >".$this->form->text_box("name=anc_prod",
																 "size=75",
																 "maxlength=255",
																 "value=".stripslashes($this->current_proposal['anc_prod']),
																 ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "readonly" : "onChange=auto_save();") : NULL))."
									</td>
								</tr>";
                                if ($this->cust->class_fields['design'][3]) {
                                    $sector_fields =& $this->cust->class_fields['design'][3];
                                    $length = ceil(count($sector_fields) / 2);
                                    $j = 0;
                                    for ($i = 0; $i < $length; $i++) {
                                        $index = $i + $j;
                                        $tbl .= "
		                                <tr>
		                                    <td style=\"width:85px;text-align:right;\" ".($sector_fields[$index] ? "id=\"err_custom".$sector_fields[$index]['obj_id']."\"" : NULL).">".($sector_fields[$index] ?
                                                $sector_fields[$index]['field_name'] : "&nbsp;").":
                                            </td>
		                                    <td>".($sector_fields[$index] ?
                                                $this->cust->build_input($sector_fields[$index],
                                                                         $this->current_proposal[$sector_fields[$index]['col_name']],
                                                                         ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL)) : "&nbsp;")."
		                                    </td>
		                                    <td style=\"text-align:right;\" ".($sector_fields[$index] ? "id=\"err_custom".$sector_fields[$index+1]['obj_id']."\"" : NULL).">".($sector_fields[$index+1] ?
                                                $sector_fields[$index+1]['field_name'] : "&nbsp;").":
                                            </td>
                                            <td>".($sector_fields[$index+1] ?
                                                $this->cust->build_input($sector_fields[$index+1],
                                                                         $this->current_proposal[$sector_fields[$index+1]['col_name']],
                                                                         ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL)) : "&nbsp;")."
                                            </td>
                                        </tr>";
                                        $j++;
                                    }
                                }

                            $tbl .= "
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;padding-left:30px;padding-bottom:10px;\" colspan=\"2\">
							<div style=\"padding-bottom:5px;\" id=\"err2_9".$this->popup_id."\">Design Notes:</div>";
							if ($this->proposal_hash) {
								$tbl .= "
								<div style=\"margin-left:10px;padding-bottom:5px;\">
									[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'note_type=d', 'proposal_hash=".$this->proposal_hash."', 'popup_id=note_win')\">add a note</a></small>]
								</div>
								<div style=\"padding-top:5px;height:75px;width:90%;overflow:auto;border:1px outset #cccccc;background-color:#efefef;\" id=\"d_note_holder\">";
								$notes = $this->fetch_proposal_notes($this->proposal_hash, 'd');
								for ($i = 0; $i < count($notes); $i++)
									$tbl .= "
									<div style=\"padding-left:5px;padding-bottom:5px;\">
										<strong>".$notes[$i]['full_name']." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $notes[$i]['timestamp']).")</strong> ".($notes[$i]['user_hash'] == $this->current_hash ? "
											[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'note_type=d', 'proposal_hash=".$this->proposal_hash."', 'popup_id=note_win', 'note_hash=".$notes[$i]['note_hash']."')\">edit</a></small>]" : NULL)." : ".stripslashes($notes[$i]['note'])."
									</div>";

								$tbl .= "
								</div>";
							} else
								$tbl .= "
								<div style=\"padding-top:5px;\">
								".$this->form->text_area("name=design_notes",
															 "value=",
															 "cols=100",
															 "rows=4")."
								</div>";
							$tbl .= "
						</td>
					</tr>
				</table>
			</div>";
		}
		if ($this->p->ck(get_class($this), 'V', 'install')) {
			unset($this->cust->class_fields);
            $this->cust->fetch_class_fields('install', NULL, 1);

			if ($valid) {
				$pm = new pm_module($this->proposal_hash);
				$pm->work_order_filter = 1;
				$pm->fetch_work_orders();
			}
			$tbl .= "
			<div id=\"tcontent3".$this->popup_id."\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"".($valid ? "main" : "popup")."_tab_table\">
					<tr>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" class=\"smallfont\" >
								<tr>
									<td style=\"text-align:right;;\" id=\"err1_8".$this->popup_id."\">Project Mngr: </td>
									<td  colspan=\"3\">
										".$this->form->text_box("name=proj_mngr",
																"autocomplete=off",
																"value=".stripslashes($this->current_proposal['proj_mngr']),
																($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onFocus=".($valid ? "\$('auto_focus').value='proj_mngr';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'proj_mngr', 'proj_mngr_hash', 1);}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'proj_mngr', 'proj_mngr_hash', 1);}" : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onBlur=key_clear();setTimeout('if(\$F(\'proj_mngr_hash\') && $(\'pm_request\')){\$(\'pm_request\').checked=0};', 500);".($valid ? "auto_save();" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onKeyDown=if(event.keyCode!=9){clear_values('proj_mngr_hash');}" : NULL)).
										$this->form->hidden(array("proj_mngr_hash" => ($this->current_proposal['proj_mngr_hash'] ? $this->current_proposal['proj_mngr_hash'] : '')))."
									</td>
								</tr>".(defined('PM_MOD_GROUP') ? "
								<tr>
									<td style=\"text-align:right;\" id=\"pm_request_holder_1\" >".($this->current_proposal['pm_request'] ?
										"PM Request Submitted:" : "Submit PM Request?")."
									</td>
									<td style=\"vertical-align:bottom;\" id=\"pm_request_holder_2\" colspan=\"3\">".($this->current_proposal['pm_request'] ?
										date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $this->current_proposal['pm_request'])."&nbsp;[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'doit_cancel_rqst', 'type=pm', 'proposal_hash=".$this->proposal_hash."');\">cancel</a></small>]" : $this->form->checkbox("name=pm_request", "value=1", ($this->p->ck(get_class($this), 'E', 'install') ? "onClick=if(this.checked){\$('proj_mngr').value='';\$('proj_mngr_hash').value='';}".($valid ? "auto_save();" : NULL) : NULL)))."
									</td>
								</tr>" : NULL)."
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;vertical-align:top;padding-top:10px;\">Delivery Only:</td>
									<td style=\"background-color:#ffffff;padding-top:10px;\">".$this->form->checkbox("name=install_type", "id=delivery_only", "value=D", ($this->current_proposal['install_type'] == 'D' ? "checked" : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=if(this.checked){\$('installation').checked=0;\$('installation_only').checked=0;\$('reconfig_only').checked=0}auto_save();") : NULL))."</td>
									<td style=\"background-color:#ffffff;vertical-align:top;padding-top:10px;text-align:right;\">Delivery & Installation:</td>
									<td style=\"background-color:#ffffff;vertical-align:top;padding-top:10px;text-align:left\">".$this->form->checkbox("name=install_type", "id=installation", "value=DI", ($this->current_proposal['install_type'] == 'DI' ? "checked" : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=if(this.checked){\$('delivery_only').checked=0;\$('installation_only').checked=0;\$('reconfig_only').checked=0}auto_save();") : NULL))."</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;vertical-align:top;\">Installation Only:</td>
									<td style=\"background-color:#ffffff;\">".$this->form->checkbox("name=install_type", "id=installation_only", "value=IO", ($this->current_proposal['install_type'] == 'IO' ? "checked" : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=if(this.checked){\$('delivery_only').checked=0;\$('installation').checked=0;\$('reconfig_only').checked=0}auto_save();") : NULL))."</td>
									<td style=\"background-color:#ffffff;vertical-align:top;text-align:right;\">Reconfig Only:</td>
									<td style=\"background-color:#ffffff;vertical-align:top;text-align:left;\">".$this->form->checkbox("name=install_type", "id=reconfig_only", "value=IO", ($this->current_proposal['install_type'] == 'RO' ? "checked" : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=if(this.checked){\$('delivery_only').checked=0;\$('installation').checked=0;\$('installation_only').checked=0}auto_save();") : NULL))."</td>
								</tr>";
                                if ($this->cust->class_fields['install'][1]) {
                                    $sector_fields =& $this->cust->class_fields['install'][1];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
		                                    <td style=\"background-color:#ffffff;text-align:right;vertical-align:top;padding-top:10px;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
		                                    <td style=\"background-color:#ffffff;padding-top:10px;\" colspan=\"3\">
                                                ".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'design') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
		                                    </td>
                                        </tr>";
                                    }
                                }
							$tbl .= "
							</table>
						</td>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" class=\"smallfont\" >
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;width:150px;\" id=\"err3_2".$this->popup_id."\">Target<br />Install/Delivery Date:</td>
									<td style=\"background-color:#ffffff;\">
										<div id=\"target_install_date_holder".$this->popup_id."\">";
										if (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')))
											$this->content["jscript"][] .= "setTimeout('DateInput(\'target_install_date\', false, \'YYYY-MM-DD\', \'".($this->current_proposal['target_install_date'] && date("Y", strtotime($this->current_proposal['target_install_date'])) > 2000 ? $this->current_proposal['target_install_date'] : NULL)."\', 1, \'target_install_date_holder".$this->popup_id."\', \'\', \'".($valid ? "auto_save()" : NULL)."\')', 23);";
										else
											$tbl .=
											($this->current_proposal['target_install_date'] && $this->current_proposal['target_install_date'] != '0000-00-00' ?
												date(DATE_FORMAT, strtotime($this->current_proposal['target_install_date'])) : NULL);
										$tbl .= "
										</div>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;width:150px;\" id=\"err3_15".$this->popup_id."\">Scheduled<br />Install/Delivery Date:</td>
									<td style=\"background-color:#ffffff;\">
										<div id=\"actual_install_start_date_holder".$this->popup_id."\">";
										if (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')))
											$this->content["jscript"][] .= "setTimeout('DateInput(\'actual_install_date\', false, \'YYYY-MM-DD\', \'".($this->current_proposal['actual_install_date'] && date("Y", strtotime($this->current_proposal['actual_install_date'])) > 2000 ? $this->current_proposal['actual_install_date'] : NULL)."\', 1, \'actual_install_start_date_holder".$this->popup_id."\', \'\', \'".($valid ? "auto_save()" : NULL)."\')', 24);";
										else
											$tbl .=
											($this->current_proposal['actual_install_date'] && $this->current_proposal['actual_install_date'] != '0000-00-00' ?
												date(DATE_FORMAT, strtotime($this->current_proposal['actual_install_date'])) : NULL);
										$tbl .= "
										</div>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;width:150px;\" id=\"err3_3".$this->popup_id."\">Install Time Available:</td>
									<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=install_days", "size=2", "maxlength=11", "value=".$this->current_proposal['install_days'], ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))." Days</td>
								</tr>".(defined('INSTALL_MOD_GROUP') ? "
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;padding-top:10px;\" id=\"install_request_holder_1\">".($this->current_proposal['work_order_rqst'] ?
										($this->current_proposal['work_order_rqst_timestamp'] ?
											"Quote Rqst Received" : "Quote Rqst Submitted") : "Submit Quote Rqst?")."
									</td>
									<td style=\"background-color:#ffffff;padding-top:10px;\"id=\"install_request_holder_2\">".($this->current_proposal['work_order_rqst'] ?
										($this->current_proposal['work_order_rqst_timestamp']['timestamp'] ?
											@date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $this->current_proposal['work_order_rqst_timestamp']['timestamp']) : date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $this->current_proposal['work_order_rqst'])."&nbsp;[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'doit_cancel_rqst', 'type=work_order', 'proposal_hash=".$this->proposal_hash."');\">cancel</a></small>]") : $this->form->checkbox("name=install_request", "value=1", ($valid ? ($this->p->ck(get_class($this), 'E', 'install') ? "onClick=auto_save();" : "disabled") : NULL)))."
									</td>
								</tr>" : NULL);
                                if ($this->cust->class_fields['install'][2]) {
                                    $sector_fields =& $this->cust->class_fields['install'][2];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"background-color:#ffffff;text-align:right;vertical-align:top;padding-top:10px;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td style=\"background-color:#ffffff;\">
                                                ".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
                                }
							$tbl .= "
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" class=\"smallfont\">
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;vertical-align:top;\" id=\"err3_4".$this->popup_id."\">Install Location:</td>
									<td style=\"background-color:#ffffff;\" nowrap>".
										$this->form->text_box("name=install_addr",
															  "value=".stripslashes($this->current_proposal['install_addr']),
															  "autocomplete=off",
															  ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : NULL) : NULL),
															  (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onFocus=".($valid ? "\$('auto_focus').value='install_addr';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'install_addr', 'install_addr_hash', 1);}" : NULL),
                                                              (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'install_addr', 'install_addr_hash', 1);}" : NULL),
															  (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onBlur=key_clear();".($valid ? "auto_save();" : NULL) : NULL),
															  (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onKeyDown=if(event.keyCode!=9){clear_values('install_addr_hash');clear_innerHTML('install_addr_holder');}" : NULL)).
										$this->form->hidden(array("install_addr_hash" => ($this->current_proposal['install_addr_hash'] ? $this->current_proposal['install_addr_hash'] : '')))."
										<span id=\"install_location_add\">".($this->current_proposal['customer_hash'] ?
											"<a href=\"javascript:void(0);\" onClick=\"agent.call('customers', 'sf_loadcontent', 'show_popup_window', 'edit_customer', 'tab_to=tcontent4', 'customer_hash=".$this->current_proposal['customer_hash']."', 'parent_win_open=add_from_proposal', 'jscript_action=void(0);', 'popup_id=customer_win')\"><img src=\"images/plus.gif\" title=\"Add a new location under ".stripslashes($this->current_proposal['customer'])."\" border=\"0\" /></a>" : NULL)."
										</span>
										<div id=\"install_addr_holder\" style=\"padding-top:5px\">".($this->current_proposal['install_addr_hash'] ?
										$this->innerHTML_customer($this->current_proposal['install_addr_hash'], 'install_addr', 'install_addr_holder', $this->current_proposal['install_addr_hash']) : NULL)."
										</div>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;text-align:right;vertical-align:top;\" id=\"err3_5".$this->popup_id."\">Ship To Location: </td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->text_box("name=ship_to",
															    "value=".stripslashes($this->current_proposal['ship_to']),
																"autocomplete=off",
																 ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onFocus=".($valid ? "\$('auto_focus').value='ship_to';" : NULL)."position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'ship_to', 'ship_to_hash', 1);}" : NULL),
                                                                (!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'ship_to', 'ship_to_hash', 1);}" : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onBlur=key_clear();".($valid ? "auto_save();" : NULL) : NULL),
																(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'install')) ? "onKeyDown=if(event.keyCode!=9){clear_values('ship_to_hash');clear_innerHTML('ship_to_holder');}" : NULL)).
										$this->form->hidden(array("ship_to_hash" => ($this->current_proposal['ship_to_hash'] ? $this->current_proposal['ship_to_hash'] : '')))."
										<div id=\"ship_to_holder\" style=\"padding-top:5px\">".($this->current_proposal['ship_to_hash'] ?
										$this->innerHTML_customer($this->current_proposal['ship_to_hash'], 'ship_to', 'ship_to_holder', $this->current_proposal['ship_to_hash']) : NULL)."
										</div>
									</td>
								</tr>";
                                if ($this->cust->class_fields['install'][3]) {
                                    $sector_fields =& $this->cust->class_fields['install'][3];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        $tbl .= "
                                        <tr>
                                            <td style=\"background-color:#ffffff;text-align:right;vertical-align:top;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</td>
                                            <td style=\"background-color:#ffffff;\">
                                                ".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
                                            </td>
                                        </tr>";
                                    }
                                }
							$tbl .= "
							</table>
						</td>
						<td style=\"background-color:#ffffff;width:50%;padding-top:10px;vertical-align:top;\">
							<div style=\"padding-bottom:5px;\">Work Orders:</div>
							<div style=\"width:95%;height:".($pm->total_work_orders > 2 ? "150" : "100")."px;overflow:auto;\" id=\"work_order_holder\">";
							if ($valid && $pm->total_work_orders) {
								$tbl .= "
								<table class=\"tborder\" style=\"width:95%;\" cellspacing=\"0\" cellpadding=\"8\" >
									<tr>
										<td style=\"background-color:#efefef;border-bottom:1px solid #cccccc;\">Order No.</td>
										<td style=\"background-color:#efefef;border-bottom:1px solid #cccccc;\">Order Descr.</td>".($this->p->ck("pm_module", "VT", "work_orders") ? "
										<td style=\"background-color:#efefef;border-bottom:1px solid #cccccc;text-align:right;\">True Cost</td>" : NULL)."
										<td style=\"background-color:#efefef;border-bottom:1px solid #cccccc;text-align:right;\">Internal Cost</td>
									</tr>";
								for ($i = 0; $i < $pm->total_work_orders; $i++) {
									if ($this->p->ck("pm_module", "V", "work_orders"))
										$onClick = "onClick=\"agent.call('pm_module', 'sf_loadcontent', 'show_popup_window', 'edit_work_order', 'order_hash=".$pm->work_orders[$i]['order_hash']."', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item')\"";
									$tbl .= "
									<tr ".($this->p->ck("pm_module", "V", "work_orders") ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">
										<td $onClick>".$pm->work_orders[$i]['order_no']."</td>
										<td $onClick>".(strlen($pm->work_orders[$i]['order_descr']) > 45 ?
											substr($pm->work_orders[$i]['order_descr'], 0, 40)."..." : $pm->work_orders[$i]['order_descr'])."</td>".($this->p->ck("pm_module", "VT", "work_orders") ? "
										<td $onClick class=\"num_field\" style=\"padding-right:5px;\">".($pm->work_orders[$i]['total_true_cost'] > 0 ?
											"$".number_format($pm->work_orders[$i]['total_true_cost'], 2) : NULL)."</td>" : NULL)."
										<td $onClick class=\"num_field\" style=\"padding-right:5px;\">".($pm->work_orders[$i]['total_internal_cost'] > 0 ?
											"$".number_format($pm->work_orders[$i]['total_internal_cost'], 2) : NULL)."</td>
									</tr>".($i < $pm->total_work_orders - 1 ? "
									<tr>
										<td style=\"background-color:#cccccc;\" colspan=\"4\"></td>
									</tr>" : NULL);
								}
								$tbl .= "
								</table>";
							} else
								$tbl .= "
								<div style=\"padding:5px 10px;font-style:italic;\">There are no work orders to show.</i>";
							$tbl .= "
							</div>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;\" colspan=\"2\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"vertical-align:top;width:300px;\">
									    <table>
									        <tr>
									            <td>
			                                        <div id=\"err3_6".$this->popup_id."\" >Shipping Contact Name:</div>
			                                        ".$this->form->text_box("name=ship_to_contact_name",
                        			                                        "size=40",
                        			                                        "maxlength=128",
                        			                                        "value=".stripslashes($this->current_proposal['ship_to_contact_name']),
                                                							($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))."
									            </td>
									        </tr>
									        <tr>
									            <td>
			                                        <div id=\"err3_7".$this->popup_id."\">Phone:</div>
			                                        ".$this->form->text_box("name=ship_to_contact_phone",
                        			                                        "maxlength=32",
                        			                                        "value=".$this->current_proposal['ship_to_contact_phone'],
                                                							($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))."
									            </td>
									        </tr>
									        <tr>
                                                <td>
			                                        <div id=\"err3_8".$this->popup_id."\">Fax:</div>
			                                        ".$this->form->text_box("name=ship_to_contact_fax",
                        			                                        "maxlength=32",
                        			                                        "value=".$this->current_proposal['ship_to_contact_fax'],
                                                							($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))."
                                                </td>
									        </tr>";
		                                if ($this->cust->class_fields['install'][5]) {
		                                    $sector_fields =& $this->cust->class_fields['install'][5];
		                                    for ($i = 0; $i < count($sector_fields); $i++) {
		                                        $tbl .= "
		                                        <tr>
		                                            <td>
		                                                <div id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</div>
		                                                ".$this->cust->build_input($sector_fields[$i],
		                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
		                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."
		                                            </td>
		                                        </tr>";
		                                    }
		                                }

		                                $tbl .= "
									    </table>
									</td>
									<td style=\"vertical-align:top;\">
										<div >Shipping Notes:</div>
										".$this->form->text_area("name=shipping_notes",
                         										 "cols=60",
                         										 "rows=6",
                         										 "value=".stripslashes($this->current_proposal['shipping_notes']),
                                                							($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL));

                                    if ($this->cust->class_fields['install'][6]) {
                                        $sector_fields =& $this->cust->class_fields['install'][6];
                                        for ($i = 0; $i < count($sector_fields); $i++) {
                                            $tbl .= "
                                            <div style=\"margin-top:5px;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</div>
                                            ".$this->cust->build_input($sector_fields[$i],
                                                                       $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                       ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL));

                                        }
                                    }

                                    $tbl .= "
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;\" colspan=\"2\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"vertical-align:top;\">
										<div id=\"err3_6".$this->popup_id."\" >Bldg Mngmt POC:</div>
										".$this->form->text_box("name=bldg_poc",
                        										"size=56",
                        										"maxlength=255",
                        										"value=".stripslashes($this->current_proposal['bldg_poc']),
                                                                ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL));

			                            if ($this->cust->class_fields['install'][7]) {
	                                        $sector_fields =& $this->cust->class_fields['install'][7];
	                                        for ($i = 0; $i < count($sector_fields); $i++) {
	                                            $tbl .= "
	                                            <div style=\"margin-top:5px;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</div>
	                                            ".$this->cust->build_input($sector_fields[$i],
	                                                                       $this->current_proposal[$sector_fields[$i]['col_name']],
	                                                                       ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL));

	                                        }
	                                    }

                                    $tbl .= "
									</td>
									<td style=\"vertical-align:top;\">
										<div id=\"err3_7".$this->popup_id."\">Phone:</div>
										".$this->form->text_box("name=bldg_phone", "maxlength=32", "value=".$this->current_proposal['bldg_phone'], ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL));

                                        if ($this->cust->class_fields['install'][8]) {
                                            $sector_fields =& $this->cust->class_fields['install'][8];
                                            for ($i = 0; $i < count($sector_fields); $i++) {
                                                $tbl .= "
                                                <div style=\"margin-top:5px;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</div>
                                                ".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL));

                                            }
                                        }

                                    $tbl .= "
									</td>
									<td style=\"vertical-align:top;\">
										<div id=\"err3_8".$this->popup_id."\">Fax:</div>
										".$this->form->text_box("name=bldg_fax", "maxlength=32", "value=".$this->current_proposal['bldg_fax'], ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL));

                                        if ($this->cust->class_fields['install'][9]) {
                                            $sector_fields =& $this->cust->class_fields['install'][9];
                                            for ($i = 0; $i < count($sector_fields); $i++) {
                                                $tbl .= "
                                                <div style=\"margin-top:5px;\" id=\"err_custom".$sector_fields[$i]['obj_id']."\">".$sector_fields[$i]['field_name'].":</div>
                                                ".$this->cust->build_input($sector_fields[$i],
                                                                           $this->current_proposal[$sector_fields[$i]['col_name']],
                                                                           ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL));

                                            }
                                        }
                                    $tbl .= "
									</td>
								</tr>";

							$tbl .= "
							</table>
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#efefef;font-weight:bold;color:#00477f;font-style:italic;padding-left:15px;\">Site Information</td>
						<td style=\"background-color:#efefef;font-weight:bold;color:#00477f;font-style:italic;padding-left:15px;\">Product Information</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;width:50%;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"3\" style=\"width:100%;margin-top:0;\" class=\"smallfont\">
								<tr>
									<td style=\"background-color:#ffffff;width:33%;\">
										".$this->form->text_box("name=no_floors", "size=2", "maxlength=3", "value=".$this->current_proposal['no_floors'], ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))."&nbsp;
										<span id=\"err3_9".$this->popup_id."\">No. Floors</span>
									</td>
									<td style=\"background-color:#ffffff;width:33%;\">
										".$this->form->checkbox("name=option_dlv_normal_hours", "value=1", ($this->current_proposal['option_dlv_normal_hours'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Dlvr Nrml Hours
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_inst_normal_hours", "value=1", ($this->current_proposal['option_inst_normal_hours'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Install Nrml Hours
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_bldg_rstr", "value=1", ($this->current_proposal['option_bldg_rstr'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Bldg Restrictions
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_loading_dock", "value=1", ($this->current_proposal['option_loading_dock'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Loading Dock
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_freight_elev", "value=1", ($this->current_proposal['option_freight_elev'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Freight Elevator
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;width:33%;\">
										".$this->form->checkbox("name=option_stair_carry", "value=1", ($this->current_proposal['option_stair_carry'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Stair Carry
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_mv_prod_prior", "value=1", ($this->current_proposal['option_mv_prod_prior'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Move Product Prior
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;width:33%;\">
										".$this->form->checkbox("name=option_occupied", "value=1", ($this->current_proposal['option_occupied'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Occupied Space
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_permits", "value=1", ($this->current_proposal['option_permits'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Permits
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;\" >
										".$this->form->checkbox("name=option_insurance", "value=1", ($this->current_proposal['option_insurance'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Cert. of Insurance
									</td>
									<td style=\"background-color:#ffffff;\" >
										".$this->form->checkbox("name=option_security", "value=1", ($this->current_proposal['option_security'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Personnel Scrty Req
									</td>
								</tr>";
                                if ($this->cust->class_fields['install'][10]) {
                                    $sector_fields =& $this->cust->class_fields['install'][10];
                                    $length = ceil(count($sector_fields) / 2);
                                    $j = 0;
                                    for ($i = 0; $i < $length; $i++) {
                                        $index = $i + $j;
                                        $tbl .= "
                                        <tr>
		                                    <td style=\"background-color:#ffffff;width:33%;\" ".($sector_fields[$index] ? "id=\"err_custom".$sector_fields[$index]['obj_id']."\"" : NULL).">".($sector_fields[$index] ?
                                                $this->cust->build_input($sector_fields[$index],
                                                                         $this->current_proposal[$sector_fields[$index]['col_name']],
                                                                         ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."&nbsp;
		                                        ".$sector_fields[$index]['field_name'] : "&nbsp;")."
		                                    </td>
                                            <td style=\"background-color:#ffffff;\" ".($sector_fields[$index+1] ? "id=\"err_custom".$sector_fields[$index+1]['obj_id']."\"" : NULL).">".($sector_fields[$index+1] ?
                                                $this->cust->build_input($sector_fields[$index+1],
                                                                         $this->current_proposal[$sector_fields[$index+1]['col_name']],
                                                                         ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."&nbsp;
                                                ".$sector_fields[$index+1]['field_name'] : "&nbsp;")."
                                            </td>
		                                </tr>";
                                        $j++;
                                    }
                                }
							$tbl .= "
							</table>
						</td>
						<td style=\"background-color:#ffffff;width:50%;vertical-align:top;\">
							<table cellspacing=\"1\" cellpadding=\"5\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"text-align:right;\" id=\"err3_10".$this->popup_id."\">Task Seating:</td>
									<td>".$this->form->text_box("name=task_seating_prod", "size=30", "maxlength=128", "value=".stripslashes($this->current_proposal['task_seating_prod']), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))."&nbsp;</td>
									<td style=\"text-align:right;\" id=\"err3_11".$this->popup_id."\">QTY:</td>
									<td>".$this->form->text_box("name=task_seating_qty", "size=2", "maxlength=5", "value=".$this->current_proposal['task_seating_qty'], ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))."&nbsp;</td>
								</tr>
								<tr>
									<td style=\"text-align:right;\" id=\"err3_12".$this->popup_id."\">Guest Seating:</td>
									<td>".$this->form->text_box("name=guest_seating_prod", "size=30", "maxlength=128", "value=".stripslashes($this->current_proposal['guest_seating_prod']), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))."&nbsp;</td>
									<td style=\"text-align:right;\" id=\"err3_13".$this->popup_id."\">QTY:</td>
									<td>".$this->form->text_box("name=guest_seating_qty", "size=2", "maxlength=5", "value=".$this->current_proposal['guest_seating_qty'], ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "readonly" : "onChange=auto_save()") : NULL))."&nbsp;</td>
								</tr>
							</table>
							<table cellspacing=\"1\" cellpadding=\"5\" style=\"width:100%;margin-top:0;\" >
								<tr>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_dwgs_pvd", "value=1", ($this->current_proposal['option_dwgs_pvd'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Drawings Provided
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_wall_mntd", "value=1", ($this->current_proposal['option_wall_mntd'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Wall Mntd Product
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_power_poles", "value=1", ($this->current_proposal['option_power_poles'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Power Poles
									</td>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_wood_trim", "value=1", ($this->current_proposal['option_wood_trim'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Wood Trim/Elements
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#ffffff;\">
										".$this->form->checkbox("name=option_multi_trip", "value=1", ($this->current_proposal['option_multi_trip'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
										Multiple Trips
									</td>
									<td style=\"background-color:#ffffff;\">
                                        ".$this->form->checkbox("name=option_oversized", "value=1", ($this->current_proposal['option_oversized'] ? 'checked' : NULL), ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "onClick=auto_save();") : NULL))."&nbsp;
                                        Oversized
									</td>
								</tr>";
                                if ($this->cust->class_fields['install'][11]) {
                                    $sector_fields =& $this->cust->class_fields['install'][11];
                                    for ($i = 0; $i < count($sector_fields); $i++) {
                                        if ($sector_fields[$i]['field_type'] == 'checkbox')
                                            $checkbox[] =& $sector_fields[$i];
                                        else
                                            $misc[] =& $sector_fields[$i];
                                    }
                                    if (count($checkbox)) {
                                        $length = ceil(count($checkbox) / 2);
                                        $j = 0;
                                        for ($i = 0; $i < $length; $i++) {
                                        	$index = $i + $j;
                                        	$tbl .= "
	                                        <tr>
	                                            <td style=\"background-color:#ffffff;width:33%;\" ".($checkbox[$index] ? "id=\"err_custom".$checkbox[$index]['obj_id']."\"" : NULL).">".($checkbox[$index] ?
	                                                $this->cust->build_input($checkbox[$index],
	                                                                         $this->current_proposal[$checkbox[$index]['col_name']],
	                                                                         ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($checkbox[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."&nbsp;
	                                                ".$checkbox[$index]['field_name'] : "&nbsp;")."
	                                            </td>
	                                            <td style=\"background-color:#ffffff;\" ".($checkbox[$index+1] ? "id=\"err_custom".$checkbox[$index+1]['obj_id']."\"" : NULL).">".($checkbox[$index+1] ?
	                                                $this->cust->build_input($checkbox[$index+1],
	                                                                         $this->current_proposal[$checkbox[$index+1]['col_name']],
	                                                                         ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($checkbox[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."&nbsp;
	                                                ".$checkbox[$index+1]['field_name'] : "&nbsp;")."
	                                            </td>
	                                        </tr>";
                                            $j++;
                                        }
                                    }
	                                $tbl .= "
	                                </table>";
                                    if (count($misc)) {
	                                    $tbl .= "
	                                    <table cellspacing=\"1\" cellpadding=\"5\" style=\"width:100%;margin-top:0;\" >";

                                        for ($i = 0; $i < count($misc); $i++)
                                            $tbl .= "
			                                <tr>
			                                    <td style=\"text-align:right;\" id=\"err_custom".$misc[$i]['obj_id']."\">".$misc[$i]['field_name'].":</td>
			                                    <td>";
	                                                $this->cust->build_input($sector_fields[$index],
	                                                                         $this->current_proposal[$sector_fields[$index]['col_name']],
	                                                                         ($valid ? (!$this->p->ck(get_class($this), 'E', 'install') ? "disabled" : "on".($sector_fields[$i]['field_type'] == 'checkbox' ? "Click" : "Change")."=auto_save();") : NULL))."&nbsp;
			                                    </td>
			                                </tr>";

                                        $tbl .= "
                                        </table>";
                                    }
                                } else
									$tbl .= "
									</table>";

						$tbl .= "
						</td>
					</tr>
					<tr>
						<td style=\"background-color:#ffffff;padding-left:30px;padding-bottom:10px;\" colspan=\"2\">
							<div style=\"padding-bottom:5px;\" id=\"err3_14".$this->popup_id."\">Install Notes:</span>";
							if ($this->proposal_hash) {
								$tbl .= "
								<div style=\"margin-left:10px;padding-bottom:5px;\">
									[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'note_type=i', 'proposal_hash=".$this->proposal_hash."', 'popup_id=note_win')\">add a note</a></small>]
								</div>
								<div style=\"padding-top:5px;height:75px;width:90%;overflow:auto;border:1px outset #cccccc;background-color:#efefef;\" id=\"i_note_holder\">";
								$notes = $this->fetch_proposal_notes($this->proposal_hash, 'i');
								for ($i = 0; $i < count($notes); $i++)
									$tbl .= "
									<div style=\"padding-left:5px;padding-bottom:5px;\">
										<strong>".$notes[$i]['full_name']." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $notes[$i]['timestamp']).")</strong> ".($notes[$i]['user_hash'] == $this->current_hash ? "
											[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_note', 'note_type=i', 'proposal_hash=".$this->proposal_hash."', 'popup_id=note_win', 'note_hash=".$notes[$i]['note_hash']."')\">edit</a></small>]" : NULL)." : ".stripslashes($notes[$i]['note'])."
									</div>";

								$tbl .= "
								</div>";
							} else
								$tbl .= "
								<div style=\"padding-top:5px;\">
								".$this->form->text_area("name=install_notes",
															 "value=",
															 "cols=100",
															 "rows=4")."
								</div>";
							$tbl .= "
							</div>
						</td>
					</tr>
				</table>
			</div>";
		}
		$tbl .= ($valid ? ($this->p->ck(get_class($this), 'L', 'item_details') ? "
			<div id=\"tcontent4\" class=\"tabcontent\">".$this->line_items()."</div>" : NULL).($this->p->ck(get_class($this), 'L', 'purchase_order') ? "
			<div id=\"tcontent5\" class=\"tabcontent\">".$this->purchase_orders()."</div>" : NULL).($this->p->ck(get_class($this), 'L', 'customer_invoice') ? "
			<div id=\"tcontent6\" class=\"tabcontent\">".$this->customer_invoices()."</div>" : NULL).($this->p->ck(get_class($this), 'L', 'vendor_payables') || $this->p->ck(get_class($this), 'L', 'memo_costing') || $this->p->ck(get_class($this), 'L', 'commissions_paid') ? "
			<div id=\"tcontent7\" class=\"tabcontent\">".$this->payables()."</div>" : NULL).($this->p->ck(get_class($this), 'L', 'doc_vault') ? "
			<div id=\"tcontent8\" class=\"tabcontent\">".$this->doc_vault()."</div>" : NULL).($this->p->ck(get_class($this), 'L', 'pm_module') ? "
			<div id=\"tcontent9\" class=\"tabcontent\">".$this->service()."</div>" : NULL).($this->p->ck(get_class($this), 'V', 'ledger') ? "
			<div id=\"tcontent10\" class=\"tabcontent\">".$this->ledger()."</div>" : NULL) : NULL).
		(!$valid && $this->p->ck(get_class($this), 'A') ? "
			<div style=\"padding:15px 25px;\">
				".$this->form->button("value=Save Proposal", "id=proposal_btn", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'actionBtn=Save Proposal');this.disabled=1")."
			</div>" : NULL)."
		</div>".($valid && $from_report ? "</div>" : NULL).
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab".$this->popup_id."\')', 10);";
		if ($valid && !$from_report)
			$this->content['html']['place_holder'] = $tbl;
		else
			$this->content['popup_controls']["cmdTable"] = $tbl;

		return;

	}

	function load_site_details($hash) {
		if ($hash) {
			$result = $this->db->query("SELECT proposal_install.*
										FROM `proposal_install`
										LEFT JOIN `proposals` ON proposals.proposal_hash = proposal_install.proposal_hash
										WHERE MATCH(proposal_install.install_addr_hash) AGAINST('$hash') AND (sales_hash = '".$_SESSION['id_hash']."' OR sales_coord_hash = '".$_SESSION['id_hash']."')
										ORDER BY proposals.timestamp DESC
										LIMIT 1");
			if ($row = $this->db->fetch_assoc($result)) {
				($row['bldg_poc'] ? $this->content['value']['bldg_poc'] = stripslashes($row['bldg_poc']) : NULL);
				($row['bldg_phone'] ? $this->content['value']['bldg_phone'] = stripslashes($row['bldg_phone']) : NULL);
				($row['bldg_fax'] ? $this->content['value']['bldg_fax'] = stripslashes($row['bldg_fax']) : NULL);

				($row['no_floors'] ? $this->content['value']['no_floors'] = $row['no_floors'] : NULL);
				($row['option_dlv_normal_hours'] ? $this->content['jscript'][] = "\$('option_dlv_normal_hours').checked=1" : NULL);
				($row['option_inst_normal_hours'] ? $this->content['jscript'][] = "\$('option_inst_normal_hours').checked=1" : NULL);
				($row['option_bldg_rstr'] ? $this->content['jscript'][] = "\$('option_bldg_rstr').checked=1" : NULL);
				($row['option_loading_dock'] ? $this->content['jscript'][] = "\$('option_loading_dock').checked=1" : NULL);
				($row['option_freight_elev'] ? $this->content['jscript'][] = "\$('option_freight_elev').checked=1" : NULL);
				($row['option_stair_carry'] ? $this->content['jscript'][] = "\$('option_stair_carry').checked=1" : NULL);
				($row['option_mv_prod_prior'] ? $this->content['jscript'][] = "\$('option_mv_prod_prior').checked=1" : NULL);
				($row['option_occupied'] ? $this->content['jscript'][] = "\$('option_occupied').checked=1" : NULL);
				($row['option_insurance'] ? $this->content['jscript'][] = "\$('option_insurance').checked=1" : NULL);
				($row['option_security'] ? $this->content['jscript'][] = "\$('option_security').checked=1" : NULL);
				($row['option_permits'] ? $this->content['jscript'][] = "\$('option_permits').checked=1" : NULL);
			}
		}
		return;
	}

	function service() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];

		if (!$this->proposal_hash)
			$valid = $this->fetch_master_record($proposal_hash);

		$pm = new pm_module($this->proposal_hash, $this->lock);

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"font-weight:bold;background-color:#efefef;padding:15px;\">
					<ul id=\"maintab2\" class=\"shadetabs\">".($this->p->ck(get_class($this), 'L', 'punch') ? "
						<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent9_1\" onClick=\"expandcontent(this);\" >Punchlist</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'work_orders') ? "
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent9_2\" onClick=\"expandcontent(this);\" >Work Orders</a></li>" : NULL)."
					</ul>".($this->p->ck(get_class($this), 'L', 'punch') ? "
					<div id=\"tcontent9_1\" class=\"tabcontent\">".$pm->punch(1)."</div>" : NULL).($this->p->ck(get_class($this), 'L', 'work_orders') ? "
					<div id=\"tcontent9_2\" class=\"tabcontent\">".$pm->work_orders(1)."</div>" : NULL)."
				</td>
			</tr>
		</table>";

		if ($this->ajax_vars['otf'])
			$this->content['html']['tcontent9'] = $tbl;
		else {
			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab2\')', 20);";
			return $tbl;
		}
		return $tbl;
	}

	function doc_vault() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if (!$this->proposal_hash)
			$valid = $this->fetch_master_record($proposal_hash);

		$doc_vault = new doc_vault();
		$doc_vault->fetch_files($this->proposal_hash, $order, $order_dir);

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"7\">".($doc_vault->total ? "
								Showing 1 - ".$doc_vault->total." of ".$doc_vault->total." Files Under Proposal ".$this->current_proposal['proposal_no']."." : NULL)."
								<div style=\"padding-top:5px;font-weight:normal;\">".($this->p->ck(get_class($this), 'A', 'doc_vault') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('doc_vault', 'sf_loadcontent', 'show_popup_window', 'new_doc', 'popup_id=doc_vault', 'proposal_hash=".$this->proposal_hash."');\"><img src=\"images/new_doc.gif\" title=\"Upload and save a file\" border=\"0\" /></a>
									&nbsp;" : "&nbsp;").($doc_vault->total ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('mail', 'sf_loadcontent', 'show_popup_window', 'agent', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item');\"><img src=\"images/send.gif\" title=\"Open the mail & fax terminal\" border=\"0\" /></a>
									&nbsp;" : NULL)."
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td></td>
							<td style=\"width:20%;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'doc_vault', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=file_name', 'order_dir=".($order == "file_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
									File Name</a>
								".($order == 'file_name' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"width:15%;vertical-align:bottom;\">File Type</td>
							<td style=\"vertical-align:bottom;width:30%;\" >Description</td>
							<td style=\"vertical-align:bottom;width:5%;padding-right:10px;\" >Public</td>
							<td style=\"vertical-align:bottom;width:10%;padding-right:10px;\" class=\"num_field\">Size</td>
							<td style=\"vertical-align:bottom;width:10%;padding-right:10px;\" class=\"num_field\">Timestamp</td>
						</tr>";

						for ($i = 0; $i < $doc_vault->total; $i++) {
							$tbl .= "
							<tr title=\"".($doc_vault->proposal_docs[$i]['checkout'] ?
									"This file has been checked out by ".($doc_vault->proposal_docs[$i]['checkout'] == $_SESSION['id_hash'] ? "me" : $doc_vault->proposal_docs[$i]['file_owner']). " at ".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $doc_vault->proposal_docs[$i]['checkout_time']) : "This file is available for download.")."\">
								<td nowrap style=\"vertical-align:bottom;\">".($this->p->ck(get_class($this), 'V', 'doc_vault') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('doc_vault', 'sf_loadcontent', 'show_popup_window', 'view_file', 'doc_hash=".$doc_vault->proposal_docs[$i]['doc_hash']."', 'proposal_hash=".$this->proposal_hash."', 'popup_id=view_file');\"><img src=\"images/file_view.gif\" title=\"".($doc_vault->proposal_docs[$i]['checkout'] == $_SESSION['id_hash'] ? "Download this file again." : "Download and view this file without checking it out.")."\" border=\"0\" /></a>" : NULL)."
									&nbsp;".($doc_vault->proposal_docs[$i]['checkout'] ?
									($doc_vault->proposal_docs[$i]['checkout'] == $_SESSION['id_hash'] ? ($this->p->ck(get_class($this), 'E', 'doc_vault') ? "
										<a href=\"javascript:void(0);\" onClick=\"agent.call('doc_vault', 'sf_loadcontent', 'show_popup_window', 'new_doc', 'popup_id=doc_vault', 'doc_hash=".$doc_vault->proposal_docs[$i]['doc_hash']."', 'proposal_hash=".$this->proposal_hash."');\"><img src=\"images/checkin_file.gif\" title=\"Check this file back in.\" border=\"0\" /></a>" : NULL) : "<img src=\"images/file_locked.gif\" title=\"This file has been checked out by ".$doc_vault->proposal_docs[$i]['file_owner']." at ".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $doc_vault->proposal_docs[$i]['checkout_time'])."\" border=\"0\" /></a>") : "
									".($this->p->ck(get_class($this), 'E', 'doc_vault') ? "<a href=\"javascript:void(0);\" onClick=\"agent.call('doc_vault', 'sf_loadcontent', 'show_popup_window', 'view_file', 'doc_hash=".$doc_vault->proposal_docs[$i]['doc_hash']."', 'proposal_hash=".$this->proposal_hash."', 'popup_id=view_file', 'checkout=true');\"><img src=\"images/save_doc.gif\" title=\"Checkout this file\" border=\"0\" /></a>" : NULL))."
									&nbsp;".($this->p->ck(get_class($this), 'D', 'doc_vault') && (!$doc_vault->proposal_docs[$i]['checkout'] || $doc_vault->proposal_docs[$i]['checkout'] == $_SESSION['id_hash']) ? "
									<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this file? This action CANNOT be undone!')){submit_form($('proposal_hash').form, 'doc_vault', 'exec_post', 'refresh_form', 'action=doit_exec_file', 'proposal_hash=".$this->proposal_hash."', 'doc_hash=".$doc_vault->proposal_docs[$i]['doc_hash']."', 'doc_action=rm');}\"><img src=\"images/rm_doc.gif\" title=\"Delete this file\" border=\"0\" /></a>" : NULL)."
								</td>
								<td style=\"vertical-align:bottom;\" style=\"text-align:left;\">".(strlen($doc_vault->proposal_docs[$i]['file_name']) > 25 ?
									"<span title=\"".htmlspecialchars($doc_vault->proposal_docs[$i]['file_name'])."\">".substr($doc_vault->proposal_docs[$i]['file_name'], 0, 23)."...</span>" : $doc_vault->proposal_docs[$i]['file_name'])."</td>
								<td style=\"vertical-align:bottom;\">".$doc_vault->proposal_docs[$i]['file_type']."</td>
								<td style=\"vertical-align:bottom;\" ".(strlen($doc_vault->proposal_docs[$i]['descr']) > 65 ? "title=\"".htmlspecialchars($doc_vault->proposal_docs[$i]['descr'])."\"" : NULL).">".(strlen($doc_vault->proposal_docs[$i]['descr']) > 65 ?
									substr($doc_vault->proposal_docs[$i]['descr'], 0, 62)."..." : $doc_vault->proposal_docs[$i]['descr'])."</td>
								<td style=\"vertical-align:bottom;\">".($doc_vault->proposal_docs[$i]['public'] ? "Y" : "N")."</td>
								<td style=\"vertical-align:bottom;\" class=\"num_field\" nowrap>".fsize_unit_convert($doc_vault->proposal_docs[$i]['filesize'])."</td>
								<td style=\"vertical-align:bottom;\" class=\"num_field\" nowrap>".date(DATE_FORMAT." ".TIMESTAMP_FORMAT, $doc_vault->proposal_docs[$i]['timestamp'])."</td>
							</tr>
							<tr>
								<td style=\"background-color:#cccccc;\" colspan=\"7\"></td>
							</tr>";
						}

						if (!$doc_vault->total)
							$tbl .= "
							<tr >
								<td colspan=\"7\">There are no documents or files stored under this proposal.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		if ($this->ajax_vars['otf'])
			$this->content['html']['tcontent8'] = $tbl;
		else
			return $tbl;

		return $tbl;
	}

	function ledger() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$p = $this->ajax_vars['p'];

		if ($proposal_hash && !$this->proposal_hash)
			$valid = $this->fetch_master_record($proposal_hash, 1);

		$this->accounting = new accounting($this->current_hash, $this->proposal_hash);

		$total = $this->accounting->journal_total;
		$num_pages = ceil($this->accounting->journal_total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);
		$end = $start_from + MAIN_PAGNATION_NUM;
		if ($end > $total)
			$end = $total;

		$this->accounting->fetch_journal($start_from, $end);

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:100%;\" >
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">".($this->accounting->journal_total ? "
								<div style=\"float:right;font-weight:normal;padding-right:10px;\">
									".paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'proposals', 'ledger', $order, $order_dir, "'otf=1', 'proposal_hash=".$this->proposal_hash."'")."
								</div>
								Showing ".($start_from + 1)." - ".($start_from + MAIN_PAGNATION_NUM > $this->accounting->journal_total ? $this->accounting->journal_total : $start_from + MAIN_PAGNATION_NUM)." of ".$this->accounting->journal_total." Journal Entries for Proposal ".$this->current_proposal['proposal_no']."." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->lock && $this->p->ck(get_class($this), 'V', 'ledger') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('accounting', 'sf_loadcontent', 'show_popup_window', 'new_journal_entry', 'popup_id=line_item', 'proposal_hash=".$this->proposal_hash."', 'p=$p');\"><img src=\"images/new.gif\" title=\"Create a new journal entry\" border=\"0\" /></a>
									&nbsp;" : "&nbsp;")."
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"width:175px;vertical-align:bottom;padding-left:10px;\">Date/Time</td>
							<td style=\"vertical-align:bottom;\">Type</td>
							<td >Account</td>
							<td >Memo</td>
							<td class=\"num_field\" >Debit</td>
							<td class=\"num_field\" style=\"padding-right:10px;\">Credit</td>
						</tr>";

						for ($i = 0; $i < ($end - $start_from); $i++) {
							$b++;
							$dr = $cr = 0;
							$journal_info = $this->accounting->fetch_journal_trasactions($this->accounting->journal_info[$i]['audit_id'], 1);
							unset($title, $show_title);
							$c = 0;
							for ($j = 0; $j < count($journal_info); $j++) {
								if ($journal_info[$j]['account_hash'] != DEFAULT_PROFIT_ACCT || $journal_info[$j]['trans_type'] == 'GJ') {
									$zero_entry = (bccomp($journal_info[$j]['debit'], 0, 4) == 0 && bccomp($journal_info[$j]['credit'], 0, 4) == 0 ? true : false);
									if ($zero_entry == false || ($zero_entry == true && $c < 3)) {
										switch ($journal_info[$j]['trans_type']) {
											case 'AR':
											case 'CR':
											if ($journal_info[$j]['record_hash'])
												$onClick = "onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', '".($journal_info[$j]['trans_type'] == 'CR' ? 'new_deposit' : 'edit_invoice')."', 'invoice_hash=".$journal_info[$j]['record_hash']."', 'popup_id=line_item');\"";
											break;

											case 'AP':
											case 'CD':
											if ($journal_info[$j]['record_hash'])
												$onClick = "onClick=\"agent.call('payables', 'sf_loadcontent', 'show_popup_window', '".($journal_info[$j]['trans_type'] == 'AR' ? "edit_invoice" : "new_deposit")."', 'invoice_hash=".$journal_info[$j]['record_hash']."', 'popup_id=line_item');\"";
											break;

											case 'ME':
											if ($journal_info[$j]['record_hash'])
												$onClick = "onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'edit_invoice', 'invoice_hash=".$journal_info[$j]['record_hash']."', 'popup_id=line_item');\"";
											break;
										}
										$title = ($onClick ? "
													" : NULL)
														.date(DATE_FORMAT, strtotime($journal_info[$j]['date']))."&nbsp;".($journal_info[$j]['timestamp'] ?
															"<br />".date(TIMESTAMP_FORMAT, $journal_info[$j]['timestamp']) : NULL).($onClick ?
															"</a>" : NULL);

										$dr = $dr + $journal_info[$j]['debit'];
										$cr = $cr + $journal_info[$j]['credit'];
									
										$tbl .= "
										<tr >
											<td style=\"vertical-align:top;font-size:8pt;\">".(!$show_title ?
												date(DATE_FORMAT, strtotime($journal_info[$j]['date']))."&nbsp;".($journal_info[$j]['timestamp'] ?
															date(TIMESTAMP_FORMAT, $journal_info[$j]['timestamp']) : NULL) : ($c == 1 && ($journal_info[$j]['customer_name'] || $journal_info[$j]['vendor_name']) ?
																($journal_info[$j]['customer_name'] ? "<i>Customer: ".$journal_info[$j]['customer_name']."</i>" : "<i>Vendor: ".$journal_info[$j]['vendor_name']."</i>") : (($c == 1 && !$journal_info[$j]['customer_name'] && !$journal_info[$j]['vendor_name']) || ($c == 2 && ($journal_info[$j]['customer_name'] || $journal_info[$j]['vendor_name'])) && $journal_info[$j]['full_name'] ?
																	"<div style=\"font-style:italic;margin-left:8px;\">".$journal_info[$j]['full_name']."</div>" : NULL)))."
											</td>
											<td style=\"vertical-align:top;font-size:8pt;\">".(!$show_title ?
													($onClick ?
														"<a href=\"javascript:void(0);\" $onClick class=\"link_standard\">" : NULL).
															$this->accounting->trans_type['type'][array_search($journal_info[$j]['trans_type'], $this->accounting->trans_type['code'])].($onClick ? "</a>" : NULL) : "&nbsp;")."
											</td>
											<td style=\"vertical-align:top;font-size:8pt;\">".($journal_info[$j]['account_no'] ?
												$journal_info[$j]['account_no']." - " : NULL).$journal_info[$j]['account_name']."
											</td>
											<td style=\"vertical-align:top;font-size:8pt;\">".stripslashes($journal_info[$j]['memo'])."</td>
											<td style=\"vertical-align:top;font-size:8pt;\" class=\"num_field\">".($journal_info[$j]['debit'] > 0 ?
												"$".number_format(trim_decimal($journal_info[$j]['debit']), 2) : NULL)."
											</td>
											<td style=\"vertical-align:top;font-size:8pt;\" class=\"num_field\" style=\"padding-right:10px;\">".($journal_info[$j]['credit'] > 0 ?
												"$".number_format(trim_decimal($journal_info[$j]['credit']), 2) : NULL)."
											</td>
										</tr>";
										$show_title = 1;
										$c++;
									}
								}
								unset($zero_entry);
							}
							$tbl .= "
							<tr>
								<td colspan=\"4\">".
									($c == 2 && ($journal_info[$j-1]['customer_name'] || $journal_info[$j-1]['vendor_name']) && $journal_info[$j-1]['full_name'] ?
										"<div style=\"font-style:italic;margin-left:8px;\">".$journal_info[$j-1]['full_name']."</div>" : NULL)."
								</td>
								<td class=\"num_field\" style=\"border-top:1px solid #8c8c8c;font-weight:bold;font-size:8pt;\">$".number_format(_round($dr, 2), 2)."</td>
								<td class=\"num_field\" style=\"border-top:1px solid #8c8c8c;font-weight:bold;font-size:8pt;\" title=\"Audit ID: ".$this->accounting->journal_info[$i]['audit_id']."\">$".number_format(_round($cr, 2), 2)."</td>
							</tr>";

							if ($b < ($end - $start_from))
								$tbl .= "
								<tr>
									<td style=\"background-color:#cccccc;\" colspan=\"6\"></td>
								</tr>";
							next($this->accounting->journal_info);
						}

						if (!$this->accounting->journal_info)
							$tbl .= "
							<tr >
								<td colspan=\"6\">There is no G/L activity under this proposal.</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		if ($this->ajax_vars['otf'])
			$this->content['html']['tcontent10'] = $tbl;
		else
			return $tbl;

		return $tbl;
	}

	function customer_invoices() {

        $proposal_hash = $this->ajax_vars['proposal_hash'];
        $p = $this->ajax_vars['p'];
        $order = $this->ajax_vars['order'];
        $order_dir = $this->ajax_vars['order_dir'];

        $pagnate = MAIN_PAGNATION_NUM;

        if ( $proposal_hash && ! $this->proposal_hash )
            $this->fetch_master_record($proposal_hash, 1);

        $this->invoice = new customer_invoice( array(
            'user_hash'     =>  $this->current_hash,
            'proposal_hash' =>  $this->proposal_hash
        ) );

        $this->credit = new customer_credits( array(
            'user_hash'     =>  $this->current_hash,
            'proposal_hash' =>  $this->proposal_hash
        ) );

        $tbl = "
        <table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
            <tr>
                <td style=\"padding:0;font-weight:bold;background-color:#efefef;padding:15px;\">
                    <ul id=\"receivables_maintab2\" class=\"shadetabs\">
                        <li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent6_1\" onClick=\"expandcontent(this);\" >Customer Invoices</a></li>
                        <li ><a href=\"javascript:void(0);\" rel=\"tcontent6_2\" onClick=\"expandcontent(this);\" >Customer Credits</a></li>
                    </ul>
                    <div id=\"tcontent6_1\" class=\"tabcontent\">" . $this->invoice->proposal_invoices(1) . "</div>
                    <div id=\"tcontent6_2\" class=\"tabcontent\">" . $this->credit->proposal_credits(1) . "</div>
                </td>
            </tr>
        </table>";


        if ($this->ajax_vars['otf'])
            $this->content['html']['tcontent6'] = $tbl;
        else {
            $this->content["jscript"][] = "setTimeout('initializetabcontent(\'receivables_maintab2\')', 15);";
            return $tbl;
        }
	}

	function payables() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$pagnate = MAIN_PAGNATION_NUM;

		if ($proposal_hash && !$this->proposal_hash)
			$valid = $this->fetch_master_record($proposal_hash, 1);

		$this->payables = new payables($this->current_hash);
		$this->payables->proposal_hash = $this->proposal_hash;

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;font-weight:bold;background-color:#efefef;padding:15px;\">
					<ul id=\"payables_maintab2\" class=\"shadetabs\">".($this->p->ck(get_class($this), 'L', 'vendor_payables') ? "
						<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent7_1\" onClick=\"expandcontent(this);\" >Vendor Bills</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'memo_costing') ? "
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent7_2\" onClick=\"expandcontent(this);\" >Memo Costs</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'commissions_paid') ? "
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent7_3\" onClick=\"expandcontent(this);\" >Commissions Paid</a></li>" : NULL)."
					</ul>".($this->p->ck(get_class($this), 'L', 'vendor_payables') ? "
					<div id=\"tcontent7_1\" class=\"tabcontent\">".$this->payables->proposal_payables(1)."</div>" : NULL).($this->p->ck(get_class($this), 'L', 'memo_costing') ? "
					<div id=\"tcontent7_2\" class=\"tabcontent\">".$this->payables->memo_costing(1)."</div>" : NULL).($this->p->ck(get_class($this), 'L', 'commissions_paid') ? "
					<div id=\"tcontent7_3\" class=\"tabcontent\">".$this->payables->commissions(1)."</div>" : NULL)."
				</td>
			</tr>
		</table>";


		if ($this->ajax_vars['otf'])
			$this->content['html']['tcontent7'] = $tbl;
		else {
			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'payables_maintab2\')', 10);";
			return $tbl;
		}
	}

	function purchase_orders() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ($limit = $this->ajax_vars['limit'])
			$pagnate = $limit;
		else
			$pagnate = MAIN_PAGNATION_NUM;

		if ($proposal_hash && !$this->proposal_hash)
			$valid = $this->fetch_master_record($proposal_hash, 1);

		$this->po = new purchase_order($this->proposal_hash);
		$lines = new line_items($this->proposal_hash);

		$total = $this->po->total;
		$num_pages = ceil($this->po->total / $pagnate);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = $pagnate * ($p - 1);
		$end = $start_from + $pagnate;
		if ($end > $this->po->total)
			$end = $this->po->total;

		$order_by_ops = array("po_no"			=>	"purchase_order.po_no",
							  "creation_date"	=>	"purchase_order.creation_date",
							  "vendor_hash"		=>	"purchase_order.vendor_hash");

		$this->po->fetch_purchase_orders($start_from, $end, $order_by_ops[($order ? $order : $order_by_ops['po_no'])], $order_dir);

		$new_po_menu ="
		<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">
			[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'new_po', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item');\" class=\"link_standard\">new purchase order</a></small>]
		</div>";

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"7\" class=\"smallfont\">".($this->po->total ? "
								<div style=\"float:right;font-weight:normal;padding-right:10px;\">
									".paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'proposals', 'purchase_orders', $order, $order_dir, "'otf=1', 'proposal_hash=".$this->proposal_hash."'").($num_pages > 1 ? "
									<div style=\"margin-top:1px;text-align:right;padding-top:5px;\">
										[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'order=".$order."', 'order_dir=".$order_dir."', 'limit=".$total."');\">Show All</a></small>]
									</div>" : ($this->ajax_vars['limit'] ? "
										<div style=\"margin-top:1px;text-align:right;padding-top:5px;\">
										[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'order=".$order."', 'order_dir=".$order_dir."');\">Paginate</a></small>]
									</div>" : "&nbsp;"))."
								</div>
								Showing ".($start_from + 1)." - ".($start_from + MAIN_PAGNATION_NUM > $this->po->total ? $this->po->total : $start_from + MAIN_PAGNATION_NUM)." of ".$this->po->total." Purchase Orders for Proposal ".$this->current_proposal['proposal_no']."." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->lock && $this->p->ck('purchase_order', 'A') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'new_po', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item');\"><img src=\"images/new_po.gif\" title=\"Create purchase orders\" border=\"0\" /></a>" : NULL)."
									&nbsp;&nbsp;".($this->po->total ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'print_po', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'print_sum=1');\"><img src=\"images/print.gif\" title=\"Print a summary of all purchase orders\" border=\"0\" /></a>
									&nbsp;&nbsp;
									<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'print_tickets', 'proposal_hash=".$this->proposal_hash."', 'po_hash=*', 'popup_id=print_po');\"><img src=\"images/print_delivery_ticket.gif\" title=\"Print delivery tickets for all purchase orders\" border=\"0\" /></a>" : NULL)."
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"width:22px;\"></td>
							<td style=\"width:75px;vertical-align:bottom;\">
								<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=po_no', 'order_dir=".($order == "po_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'limit=".$limit."');\" style=\"color:#ffffff;text-decoration:underline;\">
									PO No.</a>
								".($order == 'po_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"width:110px;vertical-align:bottom;\" nowrap>
								<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=creation_date', 'order_dir=".($order == "creation_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'limit=".$limit."');\" style=\"color:#ffffff;text-decoration:underline;\">
									Creation Date
								</a>
								".($order == 'creation_date' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"width:300px;vertical-align:bottom;\">
							".($order == 'vendor' ?
								"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
								<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'p=$p', 'order=vendor_hash', 'order_dir=".($order == "vendor_hash" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."', 'limit=".$limit."');\" style=\"color:#ffffff;text-decoration:underline;\">
									Vendor</a>
								".($order == 'vendor_hash' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td style=\"vertical-align:bottom;\">Product</td>
							<td style=\"vertical-align:bottom;\">Sent By</td>
							<td style=\"vertical-align:bottom;\" class=\"num_field\" style=\"vertical-align:bottom;\">Order Amount</td>
						</tr>";
						$tt = array("E" 	=>  "Email",
									"F" 	=>  "Fax",
									"EE"	=>	"E-Order",
									"ES"	=>	"N/A",
									""		=>	"Mail");
						for ($i = 0; $i < ($end - $start_from); $i++) {
							$ack_no = $this->po->fetch_ack_no($this->po->po_info[$i]['po_hash']);

							$tbl .= "
							<tr ".($this->p->ck('purchase_order', 'V') ? "onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'edit_po', 'proposal_hash=".$this->proposal_hash."', 'po_hash=".$this->po->po_info[$i]['po_hash']."', 'popup_id=line_item');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">
								<td style=\"width:22px;border-bottom:1px solid #cccccc;\" class=\"smallfont\">".$this->po->po_icons[$this->po->po_info[$i]['status']]."&nbsp;</td>
								<td style=\"vertical-align:bottom;text-align:left;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>".$this->po->po_info[$i]['po_no']."</td>
								<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick nowrap>".($this->po->po_info[$i]['creation_date'] ?
									date("M jS Y g:ia", $this->po->po_info[$i]['creation_date']) : "&nbsp;")."</td>
								<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>".($this->po->po_info[$i]['fully_ack'] ? "
									<img src=\"images/check.gif\" title=\"This purchase order is fully acknowledged\" />&nbsp;&nbsp;" : "&nbsp;").
									stripslashes($this->po->po_info[$i]['vendor_name']).($ack_no ?
										" <small title=\"Acknowledgement number(s)\">(".@implode(", ", $ack_no).")</small>" : "&nbsp;")."
								</td>
								<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\" ".(strlen($this->po->po_info[$i]['po_product']) > 40 ? "title=\"".addslashes($this->po->po_info[$i]['po_product'])."\"" : NULL).">".($this->po->po_info[$i]['po_product'] ?
									(strlen($this->po->po_info[$i]['po_product']) > 40 ?
										substr($this->po->po_info[$i]['po_product'], 0, 37)."..." : $this->po->po_info[$i]['po_product']) : "<i>Various Products</i>")."
								</td>
								<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\" >".($this->po->po_info[$i]['transmit_type'] ?
									$tt[$this->po->po_info[$i]['transmit_type']] : "N/A")."
								</td>
								<td style=\"vertical-align:bottom;text-align:right;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>".($this->po->po_info[$i]['order_amount'] < 0 ?
								    "($".number_format($this->po->po_info[$i]['order_amount'] * -1, 2).")" : "$".number_format($this->po->po_info[$i]['order_amount'], 2))."
								</td>
							</tr>";
						}

						if (!$this->po->total)
							$tbl .= "
							<tr >
								<td colspan=\"7\" class=\"smallfont\">You have no purchase orders to display under this proposal. </td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		if ($this->ajax_vars['otf'])
			$this->content['html']['tcontent5'] = $tbl;
		else
			return $tbl;

		return $tbl;
	}

	function function_input() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['proposal_hash'])
			return;

		$function = $this->ajax_vars['function'];
		$punch = $this->ajax_vars['punch'];

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];

		switch ($function) {
			case 'discounting':
			$title = "Update Discounting On Selected Items";
			$this->content['focus'] = "discount1";
			break;

			case 'discount_id':
			$title = "Change the Discount ID";
			break;

			case 'gp':
			$title = "Update GP On Selected Items";
			$this->content['focus'] = "gp_margin";
			break;

			case 'list':
			$title = "Adjust List Pricing On Selected Items";
			$this->content['focus'] = "list_adjust";
			break;

			case 'products':
			$title = "Change Vendor and/or Products and Services On Selected Groups";
			break;

			case 'shipto':
			$title = "Change Shipping Location On Selected Items";
			$this->content['focus'] = "func_ship_to";
			break;

			case 'tagging':
			$title = "Add Tagging On Selected Items";
			$this->content['focus'] = "item_tag1";
			break;

			case 'smart_group':
			$title = "Automatically Groups By Item Tag";
			break;
			
			case 'proposal_fee':
			$title = "Add Proposal Fee";
			break;
		}
		$this->content['popup_controls']['popup_title'] = $title;

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("proposal_hash" => $this->ajax_vars['proposal_hash'],
								  "sum" => $this->ajax_vars['sum'],
								  "limit" => $this->ajax_vars['limit'],
								  "popup_id" => $this->popup_id,
								  "function" => $function,
								  "punch"	 => $punch,
								  'continue_action' => 'close'))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:10px 25px;\">";
					switch ($function) {
						case 'smart_group';
						$lines = new line_items($this->ajax_vars['proposal_hash'], $punch);
						$items = $lines->fetch_line_items_short();
						$tbl .= "
						<div style=\"padding-bottom:15px;\" id=\"err1".$this->popup_id."\">
							This function will automatically group your line items by the information found in Tag 1 of your line item. You may uncheck the groups
							below to prevent that group from being created. If the group already exists, the line items matching that tag will be added to that group.
							<div style=\"padding-left:25px;padding-top:10px;\">";
							for ($i = 0; $i < count($items); $i++) {
								if ($items[$i]['item_tag1'])
									$item_group[$items[$i]['item_tag1']] = $items[$i]['item_hash'];
							}
							if (is_array($item_group)) {
								reset($item_group);
								while (list($tag_name, $item_hash) = each($item_group))
									$tbl .= "
									<div style=\"padding-top:5px;\">
									".$this->form->checkbox("name=smart_tag[]", "value=".$item_hash, "checked")."
									&nbsp;".
									(strlen($tag_name) > 30 ?
										substr($tag_name, 0, 27)."..." : $tag_name)."
									</div>";

							}
							$tbl .= "
							</div>
						</div>";
						break;
						
						case 'proposal_fee';
						
							$lines = new line_items($this->ajax_vars['proposal_hash'], $punch);
							$line_items = $_POST['line_item'];
							$items = explode(',', $line_items);
							$totalCost = 0;
							$totalList = 0;
							$totalSell = 0;
							
							if(count($items) > 1){
								for ($t = 0; $t < (count($items)-1); $t++) {
									$lines->fetch_line_item_record($items[$t]);
								
									$totalCost += $lines->current_line['ext_cost'];
									$totalList += $lines->current_line['ext_list'];
									$totalSell += $lines->current_line['ext_sell'];
								}	
							}else{
								$lines->fetch_line_item_record($items[0]);
								
								$totalCost += $lines->current_line['ext_cost'];
								$totalList += $lines->current_line['ext_list'];
								$totalSell += $lines->current_line['ext_sell'];
							}
							
						
							$tbl .="<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">".($valid ? "
									<tr>
									<td style=\"background-color:#ffffff;padding:0;\">".($valid && $this->lines->current_line['status'] > 0 &&
											// Show edit booked/invoiced line item button only if user has edit po permission or edit customer invoice line items permission. For ticket #118.
											($this->lines->current_line['invoice_hash'] ? $this->p->ck('customer_invoice', 'E', 'line_items') : $this->p->ck('purchase_order', 'E', NULL)) ? "
											<div id=\"edit_booked_line_holder\" onClick=\"editLineItem('$invoice_lock');\" style=\"cursor:hand;float:right;margin-right:10px;width:58px;height:23px;background-color:#eaeaea;border-left:1px solid #8f8f8f;border-right:1px solid #8f8f8f;\" title=\"Edit this ".($this->lines->current_line['invoice_hash'] ? "invoiced" : " booked")." line item\">
											<div style=\"margin-top:2px;margin-left:4px;\">
											<div style=\"float:right;font-weight:bold;margin-right:5px;margin-top:3px;\">Edit</div>
											<img src=\"images/edit_pencil.gif\">
											</div>
											</div>" : NULL)."
									<div style=\"padding-left:5px;\">".($this->lines->current_line ? ($this->p->ck($perm_class, 'D', $perm_content) && !$this->lines->current_line['status'] && $this->lock ? "
											<a href=\"javascript:void(0);\" onClick=\"if (confirm('Are you sure you want to delete this line item? This action CAN NOT be undone!')){submit_form(\$('item_vendor').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'delete=1');}\" class=\"link_standard\"><img src=\"images/rm_lineitem.gif\" title=\"Delete selected line items\" border=\"0\" /></a>
											&nbsp;" : NULL).($this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status'] && $this->lock ? "
													<a href=\"javascript:void(0);\" onClick=\"submit_form($('proposal_placeholder').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'method=tag', 'method_action=inactive', 'proposal_hash=".$this->proposal_hash."', 'line_item[]=".$this->lines->item_hash."');\" class=\"link_standard\"><img src=\"images/line_inactive.gif\" title=\"Toggle the selected items between active & inactive.\" border=\"0\" /></a>
													&nbsp;" : NULL) : NULL).($this->lines->current_line['import_data'] ? "
															<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'view_details', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->item_hash."', 'popup_id=item_details');\" class=\"link_standard\"><img src=\"images/item_details.gif\" title=\"View item details from original import.\" border=\"0\" style=\"margin-top:2px;\" /></a>
															&nbsp;" : "&nbsp;").($this->lines->current_line && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lock ?
																	"<img src=\"images/lock.gif\" title=\"Read-Only\" />&nbsp;<strong>This proposal is being edited by another user</strong>" : $item_menu)."
									</div>
									</td>
									</tr>" : NULL)."
									<tr>
									<td style=\"background-color:#d6d6d6;padding-left:20px;\">
									<table style=\"width:100%\" >
									<tr>
									<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1".$this->popup_id."\">Vendor: </div>
									".$this->form->text_box("name=item_vendor",
											"value=".($copy_from ? stripslashes($copy_from->current_line['item_vendor']) : stripslashes($this->lines->current_line['item_vendor'])),
											"autocomplete=off",
											"size=30",
											"TABINDEX=1",
											($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
											($valid && (!$this->p->ck($perm_class, 'E', $perm_content) || $this->lines->current_line['status']) ? "readonly" : NULL),
											(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'item_vendor', 'item_vendor_hash', 1);}" : NULL),
											(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'item_vendor', 'item_vendor_hash', 1);}" : NULL),
											(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onBlur=key_clear();if( \$('item_vendor_hash') && !\$F('item_vendor_hash') ){\$('item_product_hash').value='';\$('item_product_hash').disabled='true';}" : NULL),
											(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onKeyDown=clear_values('item_vendor_hash');" : NULL)).
											$this->form->hidden(array("item_vendor_hash" => ($copy_from ? $copy_from->current_line['vendor_hash'] : $this->lines->current_line['vendor_hash'])))."
											</td>
											<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2".$this->popup_id."\">Item Number: </div>
									".$this->form->text_box("name=item_no",
											"value=".($copy_from ? $copy_from->current_line['item_no'] : $this->lines->current_line['item_no']),
											"maxlength=64",
											"TABINDEX=4",
											($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
											($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
											(!$this->lines->current_line['status'] ? "onBlur=if(\$F('item_vendor_hash')){submit_form(\$('item_vendor').form, 'proposals', 'exec_post', 'refresh_form', 'action=fill_discounts', 'from_input=item_no');}" : NULL),
											(($valid && !$this->p->ck($perm_class, 'E', $perm_content)) || ($valid && $this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status']) ? "readonly" : NULL),
											($this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status'] ? "title=This item was created from an import, editing has been disabled." : NULL)
									)."
									</td>
									<td rowspan=\"4\" style=\"vertical-align:top;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err3".$this->popup_id."\">Item Description: </div>
									".$this->form->text_area("name=item_descr",
															 "rows=11",
															 "TABINDEX=8",
															 "cols=35",
															 ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
															 ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															 "value=".($copy_from ? stripslashes($copy_from->current_line['item_descr']) : stripslashes($this->lines->current_line['item_descr'])),
															 (($valid && !$this->p->ck($perm_class, 'E', $perm_content)) || ($this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status']) ? "readonly" : NULL),
															 ($this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status'] ? "title=This item was created from an import, editing has been disabled." : NULL)
															 )."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err4".$this->popup_id."\">Ship To: </div>
									".$this->form->text_box("name=item_ship_to",
															"value=".($copy_from && $copy_from->current_line['item_ship_to'] ? stripslashes($copy_from->current_line['item_ship_to']) : ($this->lines->item_hash ? stripslashes($this->lines->current_line['item_ship_to']) : stripslashes($this->current_proposal['ship_to']))),
															"autocomplete=off",
															"size=30",
															"TABINDEX=2",
															($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
															($valid && (!$this->p->ck($perm_class, 'E', $perm_content) || $this->lines->current_line['status']) ? "readonly" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'item_ship_to', 'item_ship_to_hash', 1);}" : NULL),
                                                            (!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'item_ship_to', 'item_ship_to_hash', 1);}" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onBlur=key_clear();" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onKeyDown=clear_values('item_ship_to_hash');" : NULL)).
									$this->form->hidden(array("item_ship_to_hash" => ($copy_from && $copy_from->current_line['item_ship_to'] ? $copy_from->current_line['ship_to_hash'] : ($this->lines->current_line['ship_to_hash'] ? $this->lines->current_line['ship_to_hash'] : $this->current_proposal['ship_to_hash']))))."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err5".$this->popup_id."\">Item Tagging: </div>
									".$this->form->text_box("name=item_tag1",
															"value=".($copy_from ? $copy_from->current_line['item_tag1'] : $this->lines->current_line['item_tag1']),
															"TABINDEX=5",
															"maxlength=128",
									                        ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
									                        ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err6".$this->popup_id."\">Product/Service: </div>
									<div id=\"vendor_product_holder\" ".($this->lines->current_line['invoice_hash'] ? "style=\"margin-left:10px;font-weight:bold;\"" : NULL).">".($this->lines->current_line['invoice_hash'] ?
										$this->lines->current_line['item_product'].$this->form->hidden(array('item_product_hash' => $this->lines->current_line['product_hash'])) : ($copy_from->current_line['vendor_hash'] || $this->lines->current_line['vendor_hash'] ?
											$this->innerHTML_customer($copy_from->current_line['vendor_hash'] ? $copy_from->current_line['vendor_hash'] : $this->lines->current_line['vendor_hash'], 'item_vendor', 'vendor_product_holder', ($copy_from ? $copy_from->current_line['product_hash'] : $this->lines->current_line['product_hash']), NULL, NULL, ($this->lines->current_line['status'] > 0 ? 1 : NULL), ($this->lines->current_line['status'] > 0 ? 1 : NULL)) : $this->form->select("item_product_hash", array("Select Vendor First..."), NULL, array(""), "disabled", "style=width:195px;")))."
									</div>
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err13".$this->popup_id."\">Item Tagging (2): </div>
									".$this->form->text_box("name=item_tag2",
															"value=".($copy_from ? $copy_from->current_line['item_tag2'] : $this->lines->current_line['item_tag2']),
															"TABINDEX=6",
															"maxlength=128",
										                    ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
										                    ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;padding-top:5px;\" id=\"discount_message".$this->popup_id."\">".($this->lines->item_hash && !$this->lines->current_line['status'] ?
									$disc_vals['discount_message'.$this->popup_id] : NULL)."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err15".$this->popup_id."\">Item Tagging (3): </div>
									".$this->form->text_box("name=item_tag3",
															"value=".($copy_from ? $copy_from->current_line['item_tag3'] : $this->lines->current_line['item_tag3']),
															"maxlength=128",
															"TABINDEX=7",
									                        ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
									                        ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#ffffff;padding-left:20px;\">".($this->lines->current_line['work_order_hash'] ? "
						<div style=\"font-style:italic;\">Work Order : <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'show_popup_window', 'edit_work_order', 'popup_id=work_order_item', 'proposal_hash=".$this->proposal_hash."', 'order_hash=".$this->lines->current_line['work_order_hash']."');\" title=\"View this work order.\">".$this->lines->current_line['work_order_no']."</a></div>" : NULL)."
						<table style=\"width:100%\" cellpadding=\"3\">
							<tr>
								<td style=\"width:100px;\">Total List:</td>
								<td>". number_format($totalList,2) ."</td>
							</tr>
							<tr>
								<td style=\"width:100px;\">Total Cost:</td>
								<td>". number_format($totalCost,2) ."</td>
							</tr>
							<tr>
								<td style=\"width:100px;\">Total Sell:</td>
								<td>". number_format($totalSell,2) ."</td>
							</tr>
						</table>
						<table style=\"width:100%\" cellpadding=\"3\">
							<tr>
								<td style=\"background-color:#ffffff;vertical-align:top;\">
									<span id=\"err2_13{$this->popup_id}\">Add &nbsp;".
									$this->form->text_box("name=prop_amount",
														  "size=8",
														  "style=text-align:right;")."&nbsp;".
									$this->form->select("prop_amount_add_type",
												  		array("dollars","%"),
														"",
												  		array("D","P"))."
									&nbsp;<span id=\"err2_15{$this->popup_id}\">of</span>&nbsp;".
									$this->form->select("prop_amount_type",
												  		array("List","Cost","Sell"),
												  		"",
												  		array("L","C","S"))."
									
									</span>
								</td>
							</tr>
						</table>
						<table style=\"width:100%\" cellpadding=\"3\">
							<tr>
								<td style=\"width:70px;\" id=\"err11{$this->popup_id}\">" .
								( ! $this->lines->current_line['invoice_hash'] || ( $this->lines->current_line['invoice_hash'] && $this->lines->current_line['gp_type'] == 'G' ) ?
									"GP Margin:" : "&nbsp;"
								) . "
								</td>
								<td style=\"vertical-align:bottom;\">" .
								( ! $this->lines->current_line['invoice_hash'] ?
									$this->form->hidden(array(
    									'tmp_gp_margin'        => '',
    									'tmp_list_discount'    => ''
									) ) .
									$this->form->text_box(
                                        "name=gp_margin",
                                        "value=" .
                                        ( $copy_from ?
                                            ( $copy_from->current_line['gp_type'] == 'G' && bccomp($copy_from->current_line['gp_margin'], 0, 4) ?
                                                (float)$copy_from->current_line['gp_margin'] : NULL
                                            )
                                            :
                                            ( $this->lines->current_line['gp_type'] == 'G' && bccomp($this->lines->current_line['gp_margin'], 0, 4) ?
                                                (float)$this->lines->current_line['gp_margin'] : NULL
                                            )
                                        ),
                                        "maxlength=6",
                                        "size=5",
                                        ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        "style=text-align:right;",
                                        "onBlur=this.value=formatNumber(this.value);",
                                        "tabindex=18",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "disabled" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onFocus=\$('tmp_gp_margin').value=formatNumber( this.value );this.select();" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onKeyDown=if(\$F('tmp_gp_margin') != formatNumber( this.value )){clear_values('list_discount')}" : NULL
                                        )
                                    ) . "&nbsp;%"
                                    :
                                    $this->form->hidden( array(
                                        'gp_margin' =>  $this->lines->current_line['gp_margin']
                                    ) ) .
									( $this->lines->current_line['gp_type'] == 'G' ?
										"{$this->lines->current_line['gp_margin']}&nbsp;%" : "&nbsp;"
									)
								) .
								( ! $this->lines->current_line['invoice_hash'] ?
									"&nbsp;<strong>OR</strong>&nbsp;" : NULL
								) .
								( ! $this->lines->current_line['invoice_hash'] ?
									$this->form->text_box(
                                        "name=list_discount",
                                        "value=" .
    									( $this->lines->current_line['gp_type'] == 'L' ?
        									math::list_discount( array(
	        									'list' =>  $this->lines->current_line['list'],
	        									'sell' =>  $this->lines->current_line['sell']
        									) ) :
        									( $copy_from->current_line['gp_type'] == 'L' ?
            									math::list_discount( array(
	            									'list' =>  $copy_from->current_line['list'],
	            									'sell' =>  $copy_from->current_line['sell']
            									) ) : NULL
            								)
            							),
                                        "maxlength=6",
                                        "size=5",
                                        ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        "style=text-align:right;",
                                        "onBlur=this.value=formatNumber(this.value);",
                                        "tabindex=19",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "disabled" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onFocus=\$('tmp_list_discount').value=formatNumber( this.value );this.select()" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onKeyDown=if(\$F('tmp_list_discount') != formatNumber( this.value )){clear_values('gp_margin')}" : NULL
                                        )
                                    ) . "&nbsp;% Discount Off List"
                                    :
                                    $this->form->hidden( array(
                                        'list_discount' =>  math::list_discount( array(
                                            'list'  =>  $this->lines->current_line['list'],
                                            'sell'  =>  $this->lines->current_line['sell']
                                        ) )
                                    ) ) .
									( $this->lines->current_line['gp_type'] == 'L' ?
										math::list_discount( array(
											'list'    =>  $this->lines->current_line['list'],
											'sell'    =>  $this->lines->current_line['sell']
										) ) . "&nbsp;% Discount Off List" : "&nbsp;"
									)
								) . "
								</td>
							</tr>
						</table>";
						
						break;

						case 'discounting':
						$tbl .= "
						<div style=\"padding-bottom:15px;\" id=\"err1".$this->popup_id."\">
							Enter a discount to update the selected items/groups.
						</div>
						<div style=\"padding-left:25px;\">
							".$this->form->text_box('name=discount1', 'value=', 'size=4', 'maxlength=6', 'style=text-align:right;')."&nbsp;%&nbsp;
							".$this->form->text_box('name=discount2', 'value=', 'size=4', 'maxlength=6', 'style=text-align:right;')."&nbsp;%&nbsp;
							".$this->form->text_box('name=discount3', 'value=', 'size=4', 'maxlength=6', 'style=text-align:right;')."&nbsp;%&nbsp;
							".$this->form->text_box('name=discount4', 'value=', 'size=4', 'maxlength=6', 'style=text-align:right;')."&nbsp;%&nbsp;
							".$this->form->text_box('name=discount5', 'value=', 'size=4', 'maxlength=6', 'style=text-align:right;')."&nbsp;%&nbsp;
						</div>";
						break;

						case 'discount_id':
						$tbl .= $this->form->hidden(array('func_update' => 1));
						$lines = new line_items($this->ajax_vars['proposal_hash'], $punch);
						$lines->summarized_lines();
						for ($i = 0; $i < count($lines->line_info); $i++) {
							if (!$lines->line_info[$i]['status'])
								$vendor[$lines->line_info[$i]['vendor_hash']] = $lines->line_info[$i]['vendor_name'];
						}
						$tbl .= "
						<div style=\"padding-bottom:15px;\" id=\"err1".$this->popup_id."\">
							Please select which vendor group you would like to apply a new discount ID to.
						</div>
						<div style=\"padding-left:25px;\">";
						if (is_array($vendor)) {
							$y = 1;
							while (list($hash, $name) = each($vendor))
								$tbl .= "<div style=\"padding-top:5px;\">".$this->form->radio("name=item_vendor_hash", "value=".$hash, "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=fill_discounts');")."&nbsp;".$name."</div>";
						} else
							$tbl .= "<div style=\"padding-top:5px;\">".(!count($lines->line_info) ?
								"There are no lines on your proposal, therefore you are unable to make any discount changes." : "There are no line items available for discounting update. The reason for this is because either all lines have already been ordered or those lines have been marked inactive.")."</div>";
						$tbl .= "
						</div>".($y ? "
						<div style=\"padding-top:15px;\" id=\"err1".$this->popup_id."\">
							Now select which discount you would like to apply:
						</div>
						<div style=\"padding-left:25px;\">
							<div style=\"padding-top:5px;\" id=\"func_disc_menu".$this->popup_id."\"></div>
						</div>" : NULL);
						break;

						case 'gp':
						$tbl .= "
						<div style=\"padding-bottom:15px;\" >
							Use the options below to add a GP margin or apply a sell price as a percentage off the list price.
							<br /><br />
							Complete one of the options below and the items/groups you have checked will be updated to reflect the GP change.
						</div>
						<div style=\"padding-left:25px;\">
							<span id=\"err1".$this->popup_id."\">GP Margin:</span>
							".$this->form->hidden(array('func_gp_margin' => '', 'func_list_discount' => '')).
							$this->form->text_box("name=gp_margin", "value=", "maxlength=6", "size=5", "style=text-align:right;", "onFocus=$('func_gp_margin').value=this.value", "onKeyDown=if($F('func_gp_margin')!=this.value){clear_values('list_discount')}")."&nbsp;%
							&nbsp;<span id=\"err2".$this->popup_id."\"><strong>OR</strong></span>&nbsp;
							".$this->form->text_box("name=list_discount", "value=", "maxlength=6", "size=5", "style=text-align:right;", "onFocus=$F('func_list_discount').value=this.value", "onKeyDown=if($F('func_list_discount')!=this.value){clear_values('gp_margin')}")."&nbsp;%
							Discount Off List
						</div>";
						break;

						case 'list':
						$tbl .= "
						<div style=\"padding-bottom:15px;\" >
							Enter a percentage, either negative or positive, to adjust the list price on the selected items/groups.
							<br /><br />
							For example, if you enter -15,
							the list price(s) of the items/groups you have selected will be decreased by 15%.
						</div>
						<div style=\"padding-left:25px;\" id=\"err1".$this->popup_id."\">
							Change list pricing by&nbsp;&nbsp;".$this->form->text_box('name=list_adjust', 'value=', 'size=4', 'maxlength=6', 'style=text-align:right;')."&nbsp;%&nbsp;
						</div>";
						break;

						case 'products':
						$tbl .= "
						<div style=\"padding-bottom:15px;\" >
							Change the vendor/product lines on the selected groups by entering the vendor and product information below.
							<br /><br />
							Please be cautious that you have selected the correct groups in your proposal summary before clicking update.
						</div>
						<div style=\"padding-left:25px;\" id=\"err1".$this->popup_id."\">
							Vendor:<br />
							".$this->form->text_box("name=func_vendor",
													"value=",
													"autocomplete=off",
													"size=30",
                                                    "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'func_vendor', 'func_vendor_hash', 1);}",
                                                    "onKeyUp=if(ka==false && this.value){key_call('proposals', 'func_vendor', 'func_vendor_hash', 1);}",
                            						"onBlur=key_clear();if(!\$F('func_vendor_hash')){\$('func_product_hash').value='';\$('func_product_hash').disabled=true;}",
													"onKeyDown=clear_values('func_vendor_hash');").
							$this->form->hidden(array("func_vendor_hash" => ''))."
						</div>
						<div style=\"padding-left:25px;padding-top:5px;\" id=\"err2".$this->popup_id."\">
							<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err6".$this->popup_id."\">Product/Service: </div>
							<div id=\"func_product_holder\">".$this->form->select("func_product_hash", array("Select Vendor First..."), NULL, array(""), "disabled", "style=width:195px;")."</div>
						</div>";
						// Find the number of items with no vendor and have import data
						$r = $this->db->query("SELECT COUNT(*) as total
												FROM line_items
												WHERE proposal_hash = '".$this->ajax_vars['proposal_hash']."' AND `import_data` != '' AND `vendor_hash` = '0'");

						// Prompt a confirm statement for Trac #1513
						if ($this->db->result($r, 0, 'total') > 0)
							$onClick = "if(confirm('Are you sure you want to update the vendor for these lines? There is spec data contained in these lines and it will not be changed by DealerChoice. This action CANNOT be undone! To avoid this message, update your Vendor database to include the vendor being imported as well as the Product/Catalog code(s).  Please contact DealerChoice support if you need assistance.')){submit_form(\$('popup_id').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_function', ".($punch ? "getCheckedItems('punch_item[]')" : "getCheckedItems('line_item[]')").");}";
						break;

						case 'shipto':
						$tbl .= "
						<div style=\"padding-bottom:15px;\" >
							Use the box below to select a shipping location for the selected items/groups.
						</div>
						<div style=\"padding-left:25px;\">
							<div style=\"padding-bottom:5px;\" id=\"err1".$this->popup_id."\">Shipping Location:</div>
							".$this->form->text_box("name=func_ship_to",
													"value=",
													"autocomplete=off",
													"size=30",
													"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'func_ship_to', 'func_ship_to_hash', 1);}",
                                                    "onKeyUp=if(ka==false && this.value){key_call('proposals', 'func_ship_to', 'func_ship_to_hash', 1);}",
						                            "onBlur=key_clear();",
													"onKeyDown=clear_values('func_ship_to_hash');").
							$this->form->hidden(array("func_ship_to_hash" => ''))."
							<div id=\"func_ship_to_holder\" style=\"padding-top:5px\"></div>
						</div>";
						break;

						case 'tagging':
						$tbl .= "
						<div style=\"padding-bottom:15px;\" id=\"err1".$this->popup_id."\">
							Enter tagging information below to apply to the selected items.
						</div>
						<div style=\"padding-left:25px;\">
						<table>
    						<tr>
        						<td>
        							<div style=\"margin-bottom:5px;\" >Tag 1:</div>" .
        							$this->form->text_box(
            							"name=item_tag1",
            							"maxlength=128"
        							) . "
        						</td>
        						<td style=\"padding-left:15px;\">
		                            <div style=\"margin-bottom:5px;\" >Tag 2:</div>" .
		                            $this->form->text_box(
		                                "name=item_tag2",
		                                "maxlength=128"
		                            ) . "
        						</td>
                                <td style=\"padding-left:15px;\">
		                            <div style=\"margin-bottom:5px;\" >Tag 3:</div>" .
		                            $this->form->text_box(
		                                "name=item_tag3",
		                                "maxlength=128"
		                            ) . "
        						</td>
        					</tr>
        				</table>
						</div>";
						break;

					}
					$onClick = ($onClick ? $onClick : "submit_form(\$('popup_id').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_function', ".($punch ? "getCheckedItems('punch_item[]')" : "getCheckedItems('line_item[]')").");");
					if ($function == 'discount_id')
						$onClick = "if(\$('no_disc').checked){
										if(confirm('You have chosen to not apply a discount ID.  Click OK if you wish to remove ALL OF THE DISCOUNTING PERCENTAGES in addition to the discount ID.  Clicking CANCEL will leave the discount percentages intact and clear only the discount ID.')){
											submit_form(\$('popup_id').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_function', 'clear_discounts=1', ".($punch ? "getCheckedItems('punch_item[]')" : "getCheckedItems('line_item[]')").");
										} else {
											".$onClick."
										}
									} else {
										".$onClick."
									}";

					$tbl .= "
						<div style=\"text-align:left;padding:25px 15px 5px 10px;\">
							".$this->form->button("value=Update", "id=primary", "onClick=$onClick")."
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function line_pos() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$item_hash = $this->ajax_vars['item_hash'];
		$dir = $this->ajax_vars['dir'];
		if ($punch = $this->ajax_vars['punch'])
			$pm = new pm_module($proposal_hash, 1);

		$lines = new line_items($proposal_hash, $punch);
		$lines->fetch_line_item_record($item_hash);
		$line_no = $lines->current_line['line_no'];
		$group_hash = $lines->current_line['group_hash'];

		if ($punch)
			$total_lines = $pm->total_punch;
		else
			$total_lines = $lines->total;

		if ($dir == 'down') {
			$next_line_no = $lines->current_line['line_no'] + 1;
			if ($next_line_no <= ($total_lines + 1)) {
				//Get the details of the next line item
				$result = $this->db->query("SELECT `item_hash` , `group_hash`
											FROM `line_items`
											WHERE `proposal_hash` = '$proposal_hash' AND `line_no` = '$next_line_no' AND `punch` = ".($punch ? 1 : 0));
				$row = $this->db->fetch_assoc($result);
				//This means we're moving our line into a group
				if ($row['group_hash'] && $row['group_hash'] != $lines->current_line['group_hash'])
					$this->db->query("UPDATE `line_items`
									  SET `group_hash` = '".$row['group_hash']."'
									  WHERE `item_hash` = '".$lines->item_hash."'");
				elseif (!$row['group_hash'] && $lines->current_line['group_hash'])
					$this->db->query("UPDATE `line_items`
									  SET `group_hash` = ''
									  WHERE `item_hash` = '".$lines->item_hash."'");
				else {
					//If the line is already the last line in the group, remove it
					if ($lines->current_line['group_hash'] && $lines->current_line['line_no'] == $lines->group_line_pos($lines->current_line['group_hash'], 'l'))
						$remove_from_group = true;

					//Update the line number that we're taking place of
					$this->db->query("UPDATE `line_items`
									  SET `line_no` = ".$lines->current_line['line_no']."
									  WHERE `proposal_hash` = '$proposal_hash' AND `line_no` = $next_line_no AND `punch` = ".($punch ? 1 : 0));
					//Finally, update our line number
					$this->db->query("UPDATE `line_items`
									  SET `line_no` = $next_line_no ".($remove_from_group ? ", `group_hash` = ''" : NULL)."
									  WHERE `item_hash` = '".$lines->item_hash."'");
				}
			}
		} elseif ($dir == 'up') {
			$prev_line_no = $lines->current_line['line_no'] - 1;
			if ($prev_line_no >= 1) {
				//Get the details of the next line item
				$result = $this->db->query("SELECT `item_hash` , `group_hash`
											FROM `line_items`
											WHERE `proposal_hash` = '$proposal_hash' AND `line_no` = '$prev_line_no' AND `punch` = ".($punch ? 1 : 0));
				$row = $this->db->fetch_assoc($result);
				if ($row['group_hash'] && $row['group_hash'] != $lines->current_line['group_hash'])
					$this->db->query("UPDATE `line_items`
									  SET `group_hash` = '".$row['group_hash']."'
									  WHERE `item_hash` = '".$lines->item_hash."'");
				elseif (!$row['group_hash'] && $lines->current_line['group_hash'])
					$this->db->query("UPDATE `line_items`
									  SET `group_hash` = ''
									  WHERE `item_hash` = '".$lines->item_hash."'");
				else {
					//If the line is already the last line in the group, remove it
					if ($lines->current_line['group_hash'] && $lines->current_line['line_no'] == $lines->group_line_pos($lines->current_line['group_hash']))
						$remove_from_group = true;

					//Update the line number that we're taking place of
					$this->db->query("UPDATE `line_items`
									  SET `line_no` = ".$lines->current_line['line_no']."
									  WHERE `proposal_hash` = '$proposal_hash' AND `line_no` = $prev_line_no AND `punch` = ".($punch ? 1 : 0));
					//Finally, update our line number
					$this->db->query("UPDATE `line_items`
									  SET `line_no` = $prev_line_no ".($remove_from_group ? ", `group_hash` = ''" : NULL)."
									  WHERE `item_hash` = '".$lines->item_hash."'");

				}
			}
		} elseif ($dir == 'top') {
			//See if the last line is part of a group
			$result = $this->db->query("SELECT `item_hash` , `group_hash`
										FROM `line_items`
										WHERE `proposal_hash` = '$proposal_hash' AND `line_no` = 1 AND `punch` = ".($punch ? 1 : 0));
			$row = $this->db->fetch_assoc($result);
			if ($row['group_hash'])
				$add_to_group = $row['group_hash'];
			elseif ($lines->current_line['group_hash'])
				$remove_from_group = true;

			//Update the line number of everything south of 1
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no + 1)
							  WHERE `proposal_hash` = '$proposal_hash' AND `line_no` >= 1 AND `line_no` < ".$lines->current_line['line_no']." AND `punch` = ".($punch ? 1 : 0));
			//Finally, update our line number
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = 1 ".($remove_from_group || $add_to_group ? ", `group_hash` = '".($add_to_group ? $add_to_group : NULL)."'" : NULL)."
							  WHERE `item_hash` = '".$lines->item_hash."'");
		} elseif ($dir == 'bottom') {
			//See if the last line is part of a group
			$result = $this->db->query("SELECT `item_hash` , `group_hash`
										FROM `line_items`
										WHERE `proposal_hash` = '$proposal_hash' AND `line_no` = '".$total_lines."' AND `punch` = ".($punch ? 1 : 0));
			$row = $this->db->fetch_assoc($result);
			if ($row['group_hash'])
				$add_to_group = $row['group_hash'];
			elseif ($lines->current_line['group_hash'])
				$remove_from_group = true;

			//Update the line number of everything north of last line
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no - 1)
							  WHERE `proposal_hash` = '$proposal_hash' AND `line_no` > ".$lines->current_line['line_no']." AND `punch` = ".($punch ? 1 : 0));
			//Finally, update our line number
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = ".$total_lines." ".($remove_from_group  || $add_to_group ? ", `group_hash` = '".($add_to_group ? $add_to_group : NULL)."'" : NULL)."
							  WHERE `item_hash` = '".$lines->item_hash."'");
		} elseif ($dir == 'group_top') {
			if (!$lines->current_line['group_hash'])
				return;

			//Get the number of lines in the group we're moving
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `line_items`
										WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$lines->current_line['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
			$num_grp_items = $this->db->result($result);

			$first_group_item = $lines->group_line_pos($lines->current_line['group_hash']);
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no + $num_grp_items)
							  WHERE `proposal_hash` = '$proposal_hash' AND `line_no` < $first_group_item AND `punch` = ".($punch ? 1 : 0));

			//Figure out how many rows we need to adjust
			$adj = $first_group_item - 1;
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no - $adj)
							  WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$lines->current_line['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
			$this->reorder_lines($proposal_hash);
		} elseif ($dir == 'group_bottom') {
			if (!$lines->current_line['group_hash'])
				return;

			//Get the number of lines in the group we're moving
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `line_items`
										WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$lines->current_line['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
			$num_grp_items = $this->db->result($result);

			$last_group_item = $lines->group_line_pos($lines->current_line['group_hash'], 'l');
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no - $num_grp_items)
							  WHERE `proposal_hash` = '$proposal_hash' AND `line_no` > $last_group_item AND `punch` = ".($punch ? 1 : 0));

			//Figure out how many rows we need to adjust
			$adj = $total_lines - $num_grp_items;
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no + $adj)
							  WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$lines->current_line['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
			$this->reorder_lines($proposal_hash);
		} elseif ($dir == 'group_up') {
			if (!$lines->current_line['group_hash'] || $lines->group_line_pos($lines->current_line['group_hash']) == 1)
				return;

			//Get the number of lines in the group we're moving
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `line_items`
										WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$lines->current_line['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
			$num_grp_items = $this->db->result($result);

			$first_group_item = $lines->group_line_pos($lines->current_line['group_hash']);
			//See if the next line up is part of a new group
			$result = $this->db->query("SELECT `group_hash` , `line_no`
										FROM `line_items`
										WHERE `proposal_hash` = '$proposal_hash' AND `line_no` = '".($first_group_item - 1)."' AND `punch` = ".($punch ? 1 : 0));
			$row = $this->db->fetch_assoc($result);
			if ($row['group_hash']) {
				$result = $this->db->query("SELECT COUNT(line_items.item_hash) as total_items
											FROM `line_items`
											WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$row['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
				$adj = $this->db->result($result);
				$adj_group_hash = $row['group_hash'];
			} else
				$adj = 1;

			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no + $num_grp_items)
							  WHERE `proposal_hash` = '$proposal_hash' AND ".($adj_group_hash ? "`group_hash` = '$adj_group_hash'" : "`line_no` = ".($first_group_item - 1))." AND `punch` = ".($punch ? 1 : 0));
			//Figure out how many rows we need to adjust
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no - $adj)
							  WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$lines->current_line['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
			$this->reorder_lines($proposal_hash);

		} elseif ($dir == 'group_down') {
			if (!$lines->current_line['group_hash'] || $lines->group_line_pos($lines->current_line['group_hash'], 'l') == $total_lines)
				return;

			//Get the number of lines in the group we're moving
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `line_items`
										WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$lines->current_line['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
			$num_grp_items = $this->db->result($result);

			$last_group_item = $lines->group_line_pos($lines->current_line['group_hash'], 'l');
			//See if the next line down is part of a new group
			$result = $this->db->query("SELECT `group_hash` , `line_no`
										FROM `line_items`
										WHERE `proposal_hash` = '$proposal_hash' AND `line_no` = '".($last_group_item + 1)."' AND `punch` = ".($punch ? 1 : 0));
			$row = $this->db->fetch_assoc($result);
			if ($row['group_hash']) {
				$result = $this->db->query("SELECT COUNT(line_items.item_hash) as total_items
											FROM `line_items`
											WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$row['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
				$adj = $this->db->result($result);
				$adj_group_hash = $row['group_hash'];
			} else
				$adj = 1;

			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no - $num_grp_items)
							  WHERE `proposal_hash` = '$proposal_hash' AND ".($adj_group_hash ? "`group_hash` = '$adj_group_hash'" : "`line_no` = ".($last_group_item + 1))." AND `punch` = ".($punch ? 1 : 0));
			//Figure out how many rows we need to adjust
			$this->db->query("UPDATE `line_items`
							  SET `line_no` = (line_items.line_no + $adj)
							  WHERE `proposal_hash` = '$proposal_hash' AND `group_hash` = '".$lines->current_line['group_hash']."' AND `punch` = ".($punch ? 1 : 0));
			$this->reorder_lines($proposal_hash);

		}
		if ($punch)
			$this->content['jscript'][] = "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=".$proposal_hash."');";
		else
			$this->content['jscript'][] = "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_items', 'otf=1', 'proposal_hash=".$proposal_hash."');";
		return;
	}

	function line_item_menu($local=NULL) {
		if (!$this->proposal_hash) {
			$proposal_hash = $this->ajax_vars['proposal_hash'];

			$valid = $this->fetch_master_record($proposal_hash);

			if (!$valid)
				return;

			$lock = 1;
		} else
			$lock = $this->lock;

		if (!$lock && $this->ajax_vars['new'])
			$lock = 1;

		if (!$this->lines)
			$this->lines = new line_items($this->proposal_hash);

		$result = $this->db->query("SELECT `final`
									FROM `proposals`
									WHERE `proposal_hash` = '".$this->proposal_hash."'");
		$final = $this->db->result($result);

		$item_menu .= ($this->p->ck(get_class($this), 'A', 'item_details') ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_item', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item');\" class=\"link_standard\"><img src=\"images/plus.gif\" title=\"Add new line item\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this), 'D', 'item_details') ? "
		<a href=\"javascript:void(0);\" onClick=\"if (confirm('Are you sure you want to delete the selected line items? This action CAN NOT be undone!')){submit_form($('check_all').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'method=tag', 'method_action=rm', 'proposal_hash=".$this->proposal_hash."');}\" class=\"link_standard\"><img src=\"images/rm_lineitem.gif\" title=\"Delete selected line items\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this), 'E', 'item_details') ? "
		<a href=\"javascript:void(0);\" onClick=\"submit_form($('check_all').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'method=tag', 'method_action=inactive', 'proposal_hash=".$this->proposal_hash."');\" class=\"link_standard\"><img src=\"images/line_inactive.gif\" title=\"Toggle the selected items between active & inactive.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this), 'E', 'item_details') ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_group', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group');\" class=\"link_standard\"><img src=\"images/new_group.gif\" title=\"Create & edit proposal groups\" border=\"0\" /></a>
		&nbsp;" : NULL);
		if (count($this->lines->proposal_groups['group_descr']) && $this->p->ck(get_class($this), 'E', 'item_details')) {
			$item_menu .= "
			<a href=\"javascript:void(0);\" onClick=\"position_element($('groupList'), findPos(this, 'top')+20, findPos(this, 'left'));toggle_display('groupList', 'block');\" class=\"link_standard\"><img src=\"images/addto_group.gif\" title=\"Add selected items to a group\" border=\"0\" /></a>
			<div id=\"groupList\" class=\"function_menu\">
				<div style=\"float:right;padding:3px\">
					<a href=\"javascript:void(0);\" onClick=\"toggle_display('groupList', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
				</div>
				<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Add Selected Items To:</div>";
			for ($i = 0; $i < count($this->lines->proposal_groups['group_descr']); $i++)
				$item_menu .= "
				<div class=\"function_menu_item\">
					".$this->form->radio('name=group_hash', 'value='.$this->lines->proposal_groups['group_hash'][$i])."&nbsp;
					".$this->lines->proposal_groups['group_descr'][$i]."
				</div>";

			$item_menu .= "
				<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">".$this->form->button("value=Go", "onclick=submit_form($('check_all').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_group', 'method=tag', 'proposal_hash=".$this->proposal_hash."');")."</div>
			</div>
			&nbsp;
			<a href=\"javascript:void(0);\"  onClick=\"submit_form($('check_all').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_group', 'method=tag', 'proposal_hash=".$this->proposal_hash."');\" class=\"link_standard\"><img src=\"images/rm_fromgroup.gif\" title=\"Remove selected items from group\" border=\"0\" /></a>
			&nbsp;";
		}
		//href=\"print.php?t=".base64_encode(proposal)."&h=".$this->proposal_hash."\"
		$item_menu .=($this->p->ck(get_class($this), 'A', 'item_details') ? "
		<a href=\"javascript:void(0);\"  onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_comment', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item');\" class=\"link_standard\"><img src=\"images/line_note.gif\" title=\"Add a new comment line.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this), 'A', 'item_details') ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'import_export', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'action=import');\" class=\"link_standard\"><img src=\"images/import.gif\" title=\"Import items into this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this), 'V', 'item_details') && $this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'export', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'action=export');\" class=\"link_standard\"><img src=\"images/export.gif\" title=\"Export items from this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this), 'E', 'item_details') && $this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"position_element($('item_functions'), findPos(this, 'top')+20, findPos(this, 'left'));toggle_display('item_functions', 'block');\" class=\"link_standard\"><img src=\"images/line_function.gif\" title=\"Perform a function on the selected items.\" border=\"0\" /></a>
		<div id=\"item_functions\" class=\"function_menu\" >
			<div style=\"float:right;padding:5px\">
				<a href=\"javascript:void(0);\" onClick=\"toggle_display('item_functions', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
			</div>
			<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Function Menu:</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('item_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=discounting', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group');}")."&nbsp;Discounting</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('item_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=discount_id', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group');}")."&nbsp;Change Discount ID</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('item_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=gp', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group');}")."&nbsp;GP Margins</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', 'value=roundup')."&nbsp;Round Sell Price Up</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', 'value=rounddown')."&nbsp;Round Sell Price Down</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', 'value=zerosell')."&nbsp;Update Items To Zero Sell</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', 'value=zerocost')."&nbsp;Update Items To Zero Cost</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('item_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=list', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group');}")."&nbsp;Adjust List Pricing</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('item_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=shipto', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group');}")."&nbsp;Change Shipping Location</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('item_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=tagging', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group');}")."&nbsp;Add Tagging Information</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('item_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=smart_group', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group');}")."&nbsp;Smart Grouping</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('item_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input',  'function=proposal_fee', 'proposal_hash=".$this->proposal_hash."', ".($punch ? "getCheckedItemsAsString('punch_item[]')" : "getCheckedItemsAsString('line_item[]')").", 'popup_id=line_group');}")."&nbsp;Add Proposal Fee</div>
			<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">".$this->form->button("value=Go", "onclick=toggle_display('item_functions', 'none');submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_function', 'continue_action=continue');")."</div>
		</div>
		&nbsp;" : NULL)."
		<a href=\"javascript:void(0);\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'show_popup_window', 'import_work_orders', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item');\" class=\"link_standard\"><img src=\"images/import_workorders.gif\" title=\"Import work orders.\" border=\"0\" /></a>
		&nbsp;".($this->lines->total > 0 && $this->p->ck(get_class($this), 'V', 'item_details') ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'summarized', 'otf=1', 'proposal_hash=".$this->proposal_hash."');\" class=\"link_standard\"><img src=\"images/summary.gif\" title=\"Summarize line items.\" border=\"0\" /></a>
		&nbsp;" : NULL).(!$final && $this->p->ck(get_class($this), 'E', 'item_details') && $this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\"  onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'finalize', 'otf=1', 'proposal_hash=".$this->proposal_hash."'".($this->popup_id ? ", 'popup_id=".$this->popup_id."'" : NULL).");\" class=\"link_standard\"><img src=\"images/finalize.gif\" title=\"Finalize this proposal.\" border=\"0\" /></a>
		&nbsp;" : ($this->p->ck(get_class($this), 'E', 'item_details') && $this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\"  onClick=\"submit_form($('proposal_hash').form, 'proposals', 'exec_post', 'refresh_form', 'action=unset_final', 'manual=1');\" class=\"link_standard\"><img src=\"images/undo_final.gif\" title=\"Remove finalization flags from this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'print_proposal', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', '');\" class=\"link_standard\"><img src=\"images/print.gif\" title=\"Print this proposal.\" border=\"0\" /></a>
		&nbsp;
		<a href=\"javascript:void(0);\" onClick=\"agent.call('mail', 'sf_loadcontent', 'show_popup_window', 'agent', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'cc=proposals');\" class=\"link_standard\"><img src=\"images/send.gif\" title=\"Open the mail & fax terminal.\" border=\"0\" /></a>
		&nbsp;" : NULL));

		if ($local)
			return ($lock ? $item_menu : "&nbsp;");
		else
			$this->content['html']['line_item_funcs'] = $item_menu;
	}

	function uncheck_lines($from=NULL, $action=NULL) {
		if (!$this->p->ck(get_class($this), 'E', 'item_details'))
			return;

		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$dis = $this->ajax_vars['dis'];
		if (!$from && !$action) {
            $from = $this->ajax_vars['from'];
            $action = $this->ajax_vars['action'];
		}
        if ($from == 'po') {
            if ($action == 'rm')
                $sql = "line_items.status = 0";
            elseif ($action == 'new')
                $sql = "line_items.status = 1 AND line_items.invoice_hash = ''";
        } elseif ($from == 'invoice') {
            if ($action == 'rm')
                $sql = "line_items.invoice_hash = '' AND line_items.status = 1";
            elseif ($action == 'new')
                $sql = "line_items.invoice_hash != ''";
        }

        if ($sql) {
            $r = $this->db->query("SELECT line_items.obj_id
                                   FROM `line_items`
                                   WHERE line_items.proposal_hash = '$proposal_hash' AND ".$sql);
            while ($row = $this->db->fetch_assoc($r)) {
                if ($from == 'po')
                    $this->content['jscript'][] = "\$('img_".$row['obj_id']."').innerHTML='".($action == 'new' ? "<img src=\'images/green.gif\' title=\'This line has been booked\' />" : "")."'";
                elseif ($from == 'invoice')
                    $this->content['jscript'][] = "\$('img_".$row['obj_id']."').innerHTML='<img src=\'images/".($action == 'rm' ? "green" : "blue").".gif\' title=\'This line has been ".($action == 'rm' ? "booked" : "invoiced")."\' />'";
            }
        }

        if ($from == 'po') {
			$result = $this->db->query("SELECT proposals.order_type , COUNT(purchase_order.po_hash) AS Total
										FROM `proposals`
										LEFT JOIN `purchase_order` ON purchase_order.proposal_hash = proposals.proposal_hash AND purchase_order.deleted = 0
										WHERE proposals.proposal_hash = '$proposal_hash'
										GROUP BY proposals.proposal_hash");
			if ($this->db->result($result, 0, 'Total') == 0)
				$this->content['html']['order_type_holder'] = $this->form->select("order_type", array("Normal", "Direct"), $this->db->result($result, 0, 'order_type'), array('N', 'D'), "blank=true", "onChange=auto_save();");
			else
				$this->content['html']['order_type_holder'] = ($this->db->result($result, 0, 'order_type') == 'D' ? "Direct" : "Normal");
        }
	}

	function line_items() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ($proposal_hash && !$this->proposal_hash)
			$valid = $this->fetch_master_record($proposal_hash, 1);

		$this->lines = new line_items($this->proposal_hash);
		$order_by_ops = array("qty"			=>	"line_items.qty",
							  "obj_id"		=>	"line_items.obj_id",
							  "item_no"		=>	"vendors.vendor_name , line_items.item_no",
							  "item_cost"	=>	"line_items.cost",
							  "item_sell"	=>	"line_items.sell",
							  "ext_sell"	=>	"ext_sell",
							  "line_no"		=>	"line_items.line_no",
							  "gp"			=>	"gp_margin");

		$this->lines->fetch_line_items(0, $this->lines->total, $order_by_ops[($order ? $order : 'line_no')], ($order_dir ? $order_dir : "ASC"));
		$item_menu = $this->line_item_menu(1);
		
		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" class=\"table_list\">
							<tr>
								<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"8\" class=\"smallfont\">".($this->lines->total ? "
									Showing 1 - ".$this->lines->total." of ".$this->lines->total." Line Items for Proposal ".$this->current_proposal['proposal_no']."." : NULL)."
									<div style=\"padding-left:5px;padding-top:8px;\" id=\"line_item_funcs\">".($this->lock || $this->ajax_vars['new'] == 1 ? $item_menu : "&nbsp;")."</div>
								</td>
							</tr>
						<tbody style=\"display: block; max-height: 500px; overflow-y: auto;\">
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td style=\"width:10px;\">".$this->form->checkbox("name=check_all", "value=1", "onClick=checkall(document.getElementsByName('line_item[]'), this.checked);")."</td>
							<td style=\"width:75px;vertical-align:bottom;\" nowrap>Qty</td>
							<td style=\"width:150px;vertical-align:bottom;\">Item No.</td>
							<td style=\"width:285px;vertical-align:bottom;\">Item Descr.</td>
							<td style=\"width:105px;text-align:right;vertical-align:bottom;\" nowrap>Item List</td>
							<td style=\"width:105px;text-align:right;vertical-align:bottom;\" nowrap>Item Cost</td>
							<td style=\"width:100px;text-align:right;vertical-align:bottom;\" nowrap>Item Sell</td>
							<td style=\"width:100px;text-align:right;vertical-align:bottom;\" nowrap>Ext Sell</td>
							<td style=\"width:100px;text-align:right;vertical-align:bottom;\" nowrap>GP</td>
						</tr>
						";
		
		
		
						//Comments first
						if (count($this->lines->proposal_comments)) {
							$comments = "
							<table style=\"width:90%;\" cellpadding=\"3\">";
							for ($j = 0; $j < count($this->lines->proposal_comments); $j++)
								$comments .= "
								<tr ".($this->p->ck(get_class($this), 'E', 'item_details') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_comment', 'comment_hash=".$this->lines->proposal_comments[$j]['comment_hash']."', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'limit=".$limit."', 'order=$order', 'order_dir=$order_dir');\" title=\"Edit/Delete this comment.\"" : NULL)." />
									<td class=\"smallfont\" style=\"".($j == 0 ? "border-top:1px solid #cccccc;" : NULL)."border-bottom:1px solid #cccccc;\">
										".(($this->lines->proposal_comments[$j]['comment_action'] == 1 && $this->lines->proposal_comments[$j]['comment_vendor']) || $this->lines->proposal_comments[$j]['comment_action'] == '*' ? "
											<strong>".($this->lines->proposal_comments[$j]['comment_action'] == 1 ?
												$this->lines->proposal_comments[$j]['comment_vendor'] : "All Vendor Pos")." : </strong>" : NULL)."
										".nl2br($this->lines->proposal_comments[$j]['comments'])."
									</td>
								</tr>";
							$comments .= "
							</table>";

							$tbl .= "
							<tr style=\"background-color:#FFFF99;\">
								<td colspan=\"2\" style=\"border-bottom:2px solid #cccccc;\">&nbsp;</td>
								<td colspan=\"7\" style=\"height:13px;padding:5px;border-bottom:2px solid #cccccc;font-style:italic;\" class=\"smallfont\">
									<img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('note_holder');\" name=\"imgnote_holder\" />
									&nbsp;
									<span onClick=\"shoh('note_holder');\" style=\"cursor:hand;\">".count($this->lines->proposal_comments)." Comment".(count($this->lines->proposal_comments) > 1 ? "s" : NULL)."</span>
									<div id=\"note_holder\" style=\"display:none;padding-top:10px;margin-left:25px;font-style:normal;\">".$comments."</div>
								</td>
							</tr>
							";
						}

						$group_colors = array("group_row1", "group_row2");
						$group_header = array("#D6D6D6", "#C1DAF0");
						$num_groups = 0;
						for ($i = 0; $i < $this->lines->total; $i++) {
							$b++;
							if ($this->p->ck(get_class($this), 'V', 'item_details'))
								$onClick = "onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_item', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'popup_id=line_item', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'limit=".$limit."', 'row_lock=".$this->lock."');\"";

							if ($this->lines->line_info[$i]['group_hash'] && $this->lines->line_info[$i]['group_hash'] != $this->lines->line_info[$i-1]['group_hash']) {
								$num_groups++;
								$tbl .= "
								<tr style=\"background-color:".$group_header[($num_groups % 2)]."\">
									<td ></td>
									<td colspan=\"8\" style=\"font-weight:bold;border-bottom:1px solid #cccccc;\" class=\"smallfont\">".
								        $this->form->checkbox("onClick=checkall($$('input[group=".$this->lines->line_info[$i]['group_hash']."]'), this.checked)")."
								        &nbsp;".($this->p->ck(get_class($this), 'E', 'item_details') ? "
										<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_group', 'proposal_hash=".$this->proposal_hash."', 'group_hash=".$this->lines->line_info[$i]['group_hash']."', 'popup_id=line_group', 'limit=$limit', 'order=$order', 'order_dir=$order_dir');\" class=\"link_standard\" title=\"Edit/Delete this group\">
											Group: ".nl2br($this->lines->line_info[$i]['group_descr'])."
										</a>" : "Group: ".nl2br($this->lines->line_info[$i]['group_descr']))."
									</td>
								</tr>";
							}

							if ($this->lines->line_info[$i]['group_hash'] && $this->lines->line_info[$i]['active']) {
								$grp_cost += $this->lines->line_info[$i]['cost'];
								$grp_ext_cost += ($this->lines->line_info[$i]['cost'] * $this->lines->line_info[$i]['qty']);
								$grp_sell += $this->lines->line_info[$i]['sell'];
								$grp_ext_sell += $this->lines->line_info[$i]['ext_sell'];
							}
							$tbl .= "
							<div class=\"p-shadow\" id=\"menu_rt_".$this->lines->line_info[$i]['item_hash']."\">
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" style=\"border-top: 1px solid #a9a9a9;".($this->lines->line_info[$i]['line_no'] == 1 ? "border-bottom: 1px solid #a9a9a9;" : NULL)."\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_item', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'copy_from=".$this->lines->line_info[$i]['item_hash']."');\">Copy as a new line item</div>".($this->lines->line_info[$i]['line_no'] > 1 ? "
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" style=\"border-top: 1px solid #a9a9a9;\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_pos', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'dir=top');\">Move to top</div>
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_pos', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'dir=up');\">Move up</div>" : NULL).($this->lines->line_info[$i]['line_no'] < $this->lines->total ? "
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_pos', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'dir=down');\">Move down</div>
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_pos', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'dir=bottom');\">Move to bottom</div>" : NULL).($this->lines->line_info[$i]['group_hash'] ? ($this->lines->group_line_pos($this->lines->line_info[$i]['group_hash']) > 1 ? "
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" style=\"border-top: 1px solid #a9a9a9;\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_pos', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'dir=group_top');\">Move group to top</div>
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_pos', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'dir=group_up');\">Move group up</div>" : NULL).($this->lines->group_line_pos($this->lines->line_info[$i]['group_hash'], 'l') < $this->lines->total ? "
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" ".($this->lines->group_line_pos($this->lines->line_info[$i]['group_hash']) == 1 ? "style=\"border-top: 1px solid #a9a9a9;\"" : NULL)." onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_pos', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'dir=group_down');\">Move group down</div>
								<div onMouseOver=\"this.style.background='#cccccc';this.style.cursor='hand'\" onMouseout=\"this.style.background='#ffffff'\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_pos', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->line_info[$i]['item_hash']."', 'dir=group_bottom');\">Move group to bottom</div>" : NULL) : NULL)."
							</div>
							<tr ".($this->lines->line_info[$i]['group_hash'] ? "class=\"".$group_colors[($num_groups % 2)]."\"" : NULL)." ".(!$this->lines->line_info[$i]['active'] ? "style=\"color:#858585;\"" : NULL)." onContextMenu=\"return showmenuie5('menu_rt_".$this->lines->line_info[$i]['item_hash']."')\" $onClick id=\"vendor_top".$this->lines->line_info[$i]['item_hash']."\" ".(!$this->lines->line_info[$i]['active'] ? "class=\"inactive_line\"" : NULL)." ".($this->p->ck(get_class($this), 'V', 'item_details') ? "onMouseOver=\"$('vendor_content".$this->lines->line_info[$i]['item_hash']."').className='item_row_over';this.className='item_row_over'\" onMouseOut=\"$('vendor_content".$this->lines->line_info[$i]['item_hash']."').className='".($this->lines->line_info[$i]['group_hash'] ? $group_colors[($num_groups % 2)] : NULL)."';this.className='".($this->lines->line_info[$i]['group_hash'] ? $group_colors[($num_groups % 2)] : NULL)."'\"" : NULL)." >
								<td style=\"text-align:center;\">".(!$this->lines->line_info[$i]['import_data'] && !$this->lines->line_info[$i]['import_complete'] ? "
									<img src=\"images/alert.gif\" title=\"This line item has been incompletely specified!\" />" : NULL)."
								</td>
								<td colspan=\"8\" style=\"padding:5px 0 0 10px;font-style:italic;\" class=\"smallfont\">".(!$this->current_proposal['final'] && !$this->lines->line_info[$i]['po_hash'] && !$this->lines->line_info[$i]['invoice_hash'] && $this->lines->line_info[$i]['discount_type'] == 'T' ? "
									<div style=\"float:right;\">Tiered discounting to be applied at finalization</div>" : "<div style=\"float:right;padding-right:5px;\" id=\"img_".$this->lines->line_info[$i]['obj_id']."\">".($this->lines->line_info[$i]['status'] > 0 ?
                						($this->lines->line_info[$i]['invoice_hash'] ?
                    						"<img src=\"images/blue.gif\" title=\"This line has been invoiced\" />" : "<img src=\"images/green.gif\" title=\"This line has been booked\" />") : NULL).
                					"</div>").
									"<span style=\"padding-right:5px;\">Line ".$this->lines->line_info[$i]['line_no']." : </span>".
									($this->lines->line_info[$i]['vendor_name'] ?
										stripslashes($this->lines->line_info[$i]['vendor_name']) : "<img src=\"images/alert.gif\" title=\"No vendor!\">&nbsp;&nbsp;No assigned vendor").($this->lines->line_info[$i]['product_name'] ? "
											: ".$this->lines->line_info[$i]['product_name'] : NULL).($this->lines->line_info[$i]['cr'] ? "
												<div style=\"color:#ff0000;padding-top:5px;margin-left:15px;\">
													Item Special: ".$this->lines->line_info[$i]['cr']."</div>" : NULL)."
								</td>
							</tr>
							<tr ".($this->lines->line_info[$i]['group_hash'] ? "class=\"".$group_colors[($num_groups % 2)]."\"" : NULL)." ".(!$this->lines->line_info[$i]['active'] ? "style=\"color:#858585;\"" : NULL)." onContextMenu=\"return showmenuie5('menu_rt_".$this->lines->line_info[$i]['item_hash']."')\" id=\"vendor_content".$this->lines->line_info[$i]['item_hash']."\" ".(!$this->lines->line_info[$i]['active'] ? "class=\"inactive_line\" title=\"This line is inactive.\"" : NULL)." ".($this->p->ck(get_class($this), 'V', 'item_details') ? "onMouseOver=\"$('vendor_top".$this->lines->line_info[$i]['item_hash']."').className='item_row_over';this.className='item_row_over'\" onMouseOut=\"$('vendor_top".$this->lines->line_info[$i]['item_hash']."').className='".($this->lines->line_info[$i]['group_hash'] ? $group_colors[($num_groups % 2)] : NULL)."';this.className='".($this->lines->line_info[$i]['group_hash'] ? $group_colors[($num_groups % 2)] : NULL)."'\"" : NULL)." >
								<td style=\"vertical-align:bottom;cursor:auto;border-bottom:1px solid #cccccc;\" class=\"smallfont\">".
									$this->form->checkbox("name=line_item[]",
									                      "value=".$this->lines->line_info[$i]['item_hash'],
									                      (!$this->p->ck(get_class($this), 'E', 'item_details') ? 'disabled' : NULL),
									                      "id=checkbox_".$this->lines->line_info[$i]['item_hash'],
									                      ($this->lines->line_info[$i]['group_hash'] ? "group=".$this->lines->line_info[$i]['group_hash'] : NULL))."
								</td>
								<td style=\"vertical-align:bottom;text-align:left;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>".rtrim(trim($this->lines->line_info[$i]['qty'], '0'), '.')."</td>
								<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>
									".$this->lines->line_info[$i]['item_no']."&nbsp;
								</td>
								<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" class=\"smallfont\" ".(strlen($this->lines->line_info[$i]['item_descr']) > 60 ? "title=\"".htmlspecialchars($this->lines->line_info[$i]['item_descr'], ENT_QUOTES)."\"" : NULL)." $onClick>".(strlen($this->lines->line_info[$i]['item_descr']) > 70 ?
									wordwrap(substr(stripslashes($this->lines->line_info[$i]['item_descr']), 0, 65), 35, "<br />", true)."..." : (strlen($this->lines->line_info[$i]['item_descr']) > 30 ?
										wordwrap(stripslashes($this->lines->line_info[$i]['item_descr']), 30, "<br />", true) : nl2br(stripslashes($this->lines->line_info[$i]['item_descr']))))."&nbsp;
								</td>
								<td style=\"vertical-align:bottom;text-align:right;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>" .
								( bccomp($this->lines->line_info[$i]['list'], 0, 2) == -1 ?
    								"(\$" . number_format( bcmul($this->lines->line_info[$i]['list'], -1, 2), 2) . ")" : "\$" . number_format($this->lines->line_info[$i]['list'], 2)
								) . "
								</td>
								<td style=\"vertical-align:bottom;text-align:right;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>" .
								( bccomp($this->lines->line_info[$i]['cost'], 0, 2) == -1 ?
    								"(\$" . number_format( bcmul($this->lines->line_info[$i]['cost'], -1, 2), 2) . ")" : "\$" . number_format($this->lines->line_info[$i]['cost'], 2)
								) . "
								</td>
								<td style=\"vertical-align:bottom;text-align:right;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>" .
								( bccomp($this->lines->line_info[$i]['sell'], 0, 2) == -1 ?
    								"(\$" . number_format( bcmul($this->lines->line_info[$i]['sell'], -1, 2),2) . ")" : "\$" . number_format($this->lines->line_info[$i]['sell'], 2)
								) . "
								</td>
								<td style=\"vertical-align:bottom;text-align:right;border-bottom:1px solid #cccccc;\" class=\"smallfont\" $onClick>" .
								( bccomp($this->lines->line_info[$i]['ext_sell'], 0, 2) == -1 ?
                                    "(\$" . number_format( bcmul($this->lines->line_info[$i]['ext_sell'], -1, 2),2) . ")" : "\$" . number_format($this->lines->line_info[$i]['ext_sell'], 2)
								) . "
								</td>
								<td class=\"smallfont\" style=\"vertical-align:bottom;text-align:right;border-bottom:1px solid #cccccc;" .
								( defined('MARGIN_FLAG') && bccomp( bcmul($this->lines->line_info[$i]['gp_margin'], .01, 4), MARGIN_FLAG, 4) == -1 ?
    								"color:red;" : NULL
								) . "\" $onClick>" .
								( $this->lines->line_info[$i]['gp_margin'] ?
									_round($this->lines->line_info[$i]['gp_margin'], 4)." %" :
									( bccomp($this->lines->line_info[$i]['cost'], 0, 2) == 1 && ! bccomp($this->lines->line_info[$i]['sell'], 0, 2) ?
										"<span style=\"padding-right:15px;\"><img src=\"images/alert.gif\" title=\"Negative GP Margin!\" /></span>" : NULL
									)
								) . "
								</td>
							</tr>";

							if ($this->lines->line_info[$i]['group_hash'] && (!$this->lines->line_info[$i+1]['group_hash'] || $this->lines->line_info[$i+1]['group_hash'] != $this->lines->line_info[$i]['group_hash'])) {
								$tbl .= "
								<tr style=\"background-color:".$group_header[($num_groups % 2)]."\">
									<td colspan=\"3\" style=\"border-bottom:1px solid #cccccc;\">&nbsp;</td>
									<td colspan=\"4\" style=\"font-weight:bold;text-align:right;padding-right:15px;border-bottom:1px solid #cccccc;\" class=\"smallfont\">Group ".$this->lines->line_info[$i]['group_descr']." Total:</td>
									<td style=\"vertical-align:bottom;font-weight:bold;text-align:right;border-bottom:1px solid #cccccc;\" class=\"smallfont\">$".number_format($grp_ext_sell, 2)."</td>
									<td class=\"smallfont\" style=\"vertical-align:bottom;font-weight:bold;text-align:right;border-bottom:1px solid #cccccc;" .
        								( defined('MARGIN_FLAG') && math::gp_margin( array(
		        								'cost'  =>  $grp_ext_cost,
		        								'sell'  =>  $grp_ext_sell
	        								) ) < MARGIN_FLAG ?
                								"color:red;" : NULL
        								) . "\">" .
        								(float)bcmul( math::gp_margin( array(
	        								'cost'  =>  $grp_ext_cost,
	        								'sell'  =>  $grp_ext_sell
        								) ), 100, 4) . "%
        							</td>
								</tr>";

								unset($grp_cost, $grp_ext_cost, $grp_sell, $grp_ext_sell);
							}

							if ($this->lines->line_info[$i]['active']) {
								$total_cost += $this->lines->line_info[$i]['cost'];
								$total_ext_cost += ($this->lines->line_info[$i]['cost'] * $this->lines->line_info[$i]['qty']);
								$total_sell += $this->lines->line_info[$i]['sell'];
								$total_ext_sell += $this->lines->line_info[$i]['ext_sell'];
							}
						}

						if (!$this->lines->total)
							$tbl .= "
							<tr >
								<td>&nbsp;</td>
								<td colspan=\"8\">You have no line items to display. ".($this->p->ck(get_class($this), 'A', 'item_details') ? "
									<a href=\"javascript:void(0);\"  onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_item', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'limit=$limit');\" class=\"link_standard\">Create a new line item now</a>." : NULL)."
								</td>
							</tr>";
						elseif ($this->lines->total) {
							$tbl .= "
							<tr style=\"background-color:#cccccc;\">
								<td colspan=\"4\" style=\"border-top:2px solid #9f9f9f;\">&nbsp;</td>
								<td style=\"vertical-align:bottom;font-weight:bold;padding-top:20px;border-top:2px solid #9f9f9f;\" class=\"num_field\"><!--$".number_format($total_cost, 2)."-->&nbsp;</td>
								<td style=\"vertical-align:bottom;font-weight:bold;padding-top:20px;border-top:2px solid #9f9f9f;\" class=\"num_field\"><!--$".number_format($total_sell, 2)."-->&nbsp;</td>
								<td style=\"vertical-align:bottom;font-weight:bold;padding-top:20px;border-top:2px solid #9f9f9f;\" class=\"num_field\"><!--$".number_format($total_sell, 2)."-->&nbsp;</td>
								<td style=\"vertical-align:bottom;font-weight:bold;padding-top:20px;border-top:2px solid #9f9f9f;\" class=\"num_field\">$".number_format($total_ext_sell, 2)."</td>
								<td class=\"num_field\" style=\"vertical-align:bottom;padding-top:20px;font-weight:bold;border-top:2px solid #9f9f9f;" .
        							( defined('MARGIN_FLAG') && math::gp_margin( array(
	            							'cost'   =>  $total_ext_cost,
	            							'sell'   =>  $total_ext_sell
            							) ) < MARGIN_FLAG ?
                							"color:red;" : NULL
            						) . "\">" .
            						(float)bcmul( math::gp_margin( array(
	            						'cost'    =>  $total_ext_cost,
	            						'sell'    =>  $total_ext_sell
            						) ), 100, 4) . "%
            					</td>
							</tr>";
						}

			$tbl .= "
					</tbody>
					</table>
				</td>
			</tr>
		</table>";
		if ($this->ajax_vars['otf'])
			$this->content['html']['tcontent4'] = $tbl;
		else
			return $tbl;

		return $tbl;
	}

	function summarized() {
		if ($proposal_hash = $this->ajax_vars['proposal_hash'])
			$valid = $this->fetch_master_record($proposal_hash);
		else
			return;

		$punch = $this->ajax_vars['punch'];
		$this->lines = new line_items($this->proposal_hash, $punch);
        $this->lines->update_sales_tax();

		$this->lines->summarized_lines();

		$item_menu = ($this->p->ck(get_class($this), 'E', 'item_details') ? "
		<a href=\"javascript:void(0);\" onClick=\"if (confirm('Are you sure you want to delete the selected vendors/products? This action CAN NOT be undone!')){submit_form($('function_ctrl').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'method=tag', 'sum=1', 'method_action=rm', 'proposal_hash=".$this->proposal_hash."'".($punch ? ", 'punch=1'" : NULL).");}\" class=\"link_standard\"><img src=\"images/rm_lineitem.gif\" title=\"Delete selected line items\" border=\"0\" /></a>
		&nbsp;
		<a href=\"javascript:void(0);\" onClick=\"submit_form($('function_ctrl').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'method=tag', 'method_action=inactive', 'proposal_hash=".$this->proposal_hash."', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");\" class=\"link_standard\"><img src=\"images/line_inactive.gif\" title=\"Toggle the selected items between active & inactive.\" border=\"0\" /></a>
		&nbsp;" : NULL);
		if (count($this->lines->proposal_groups['group_descr']) && $this->p->ck(get_class($this), 'E', 'item_details')) {
			$item_menu .= "
			<a href=\"javascript:void(0);\" onClick=\"position_element($('groupList'), findPos(this, 'top')+20, findPos(this, 'left'));toggle_display('groupList', 'block');\" class=\"link_standard\"><img src=\"images/addto_group.gif\" title=\"Add selected items to a group\" border=\"0\" /></a>
			<div id=\"groupList\" class=\"function_menu\">
				<div style=\"float:right;padding:3px\">
					<a href=\"javascript:void(0);\" onClick=\"toggle_display('groupList', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
				</div>
				<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Add Selected Vendors To:</div>";
			for ($i = 0; $i < count($this->lines->proposal_groups['group_descr']); $i++)
				$item_menu .= "
				<div class=\"function_menu_item\">
					".$this->form->radio('name=group_hash', 'value='.$this->lines->proposal_groups['group_hash'][$i])."&nbsp;
					".$this->lines->proposal_groups['group_descr'][$i]."
				</div>";

			$item_menu .= "
				<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">".$this->form->button("value=Go", "onclick=submit_form($('function_ctrl').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_group', 'method=tag', 'proposal_hash=".$this->proposal_hash."', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");")."</div>
			</div>
			&nbsp;";
		}
		$item_menu .= ($this->p->ck(get_class($this), 'V', 'item_details') && $this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'export', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'action=export'".($punch ? ", 'punch=1'" : NULL).");\" class=\"link_standard\"><img src=\"images/export.gif\" title=\"Export items from this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->p->ck(get_class($this), 'E', 'item_details') ? "
		<a href=\"javascript:void(0);\" onClick=\"position_element($('".($punch ? "punch" : "item")."_functions'), findPos(this, 'top')+20, findPos(this, 'left'));toggle_display('".($punch ? "punch" : "item")."_functions', 'block');\" class=\"link_standard\"><img src=\"images/line_function.gif\" title=\"Perform a function on the selected items.\" border=\"0\" /></a>
		<div id=\"".($punch ? "punch" : "item")."_functions\" class=\"function_menu\">
			<div style=\"float:right;padding:3px\">
				<a href=\"javascript:void(0);\" onClick=\"toggle_display('".($punch ? "punch" : "item")."_functions', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
			</div>
			<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Function Menu:</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('".($punch ? "punch" : "item")."_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=discounting', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");}")."&nbsp;Discounting</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('".($punch ? "punch" : "item")."_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=discount_id', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");}")."&nbsp;Change Discount ID</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('".($punch ? "punch" : "item")."_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=gp', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");}")."&nbsp;GP Margins</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', 'value=roundup')."&nbsp;Round Sell Price Up</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', 'value=rounddown')."&nbsp;Round Sell Price Down</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', 'value=zerosell')."&nbsp;Update Items To Zero Sell</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', 'value=zerocost')."&nbsp;Update Items To Zero Cost</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('".($punch ? "punch" : "item")."_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=list', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");}")."&nbsp;Adjust List Pricing</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('".($punch ? "punch" : "item")."_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=products', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");}")."&nbsp;Change Vendor/Products</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('".($punch ? "punch" : "item")."_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=shipto', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");}")."&nbsp;Change Shipping Location</div>
			<div class=\"function_menu_item\">".$this->form->radio('name=function', "onClick=if(this.checked){this.checked=false;toggle_display('".($punch ? "punch" : "item")."_functions', 'none');agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'function_input', 'function=tagging', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_group', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");}")."&nbsp;Add Tagging Information</div>
			<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">".$this->form->button("value=Go", "name=function_ctrl", "onclick=toggle_display('".($punch ? "punch" : "item")."_functions', 'none');submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_function', 'continue_action=continue', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");")."</div>
		</div>
		&nbsp;" : NULL).(!$this->current_proposal['final'] && $this->p->ck(get_class($this), 'E', 'item_details') && $this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\"  onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'finalize', 'otf=1', 'proposal_hash=".$this->proposal_hash."', 'sum=1'".($punch ? ", 'punch=1'" : NULL).");\" class=\"link_standard\"><img src=\"images/finalize.gif\" title=\"Finalize this proposal.\" border=\"0\" /></a>
		&nbsp;" : ($this->p->ck(get_class($this), 'E', 'item_details') && $this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\"  onClick=\"submit_form($('proposal_hash').form, 'proposals', 'exec_post', 'refresh_form', 'action=unset_final', 'manual=1'".($punch ? ", 'punch=1'" : NULL).");\" class=\"link_standard\"><img src=\"images/undo_final.gif\" title=\"Remove finalization flags from this proposal.\" border=\"0\" /></a>
		&nbsp;" : NULL).($this->lines->total > 0 ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'print_proposal', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item'".($punch ? ", 'punch=1'" : NULL).");\" class=\"link_standard\"><img src=\"images/print.gif\" title=\"Print this proposal.\" border=\"0\" /></a>
		&nbsp;".(!$punch ? "
		<a href=\"javascript:void(0);\" onClick=\"agent.call('mail', 'sf_loadcontent', 'show_popup_window', 'agent', 'proposal_hash=".$this->proposal_hash."', 'popup_id=line_item', 'cc=proposals'".($punch ? ", 'punch=1'" : NULL).");\" class=\"link_standard\"><img src=\"images/send.gif\" title=\"Open the mail & fax terminal.\" border=\"0\" /></a>
		&nbsp;" : NULL) : NULL));

		$tbl .= "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <div style=\"background-color:#ffffff;height:100%;\">
					 	<div style=\"margin:15px 35px;\">
							<h3 style=\"margin-bottom:5px;color:#00477f;\">".($punch ? "Punchlist" : "Proposal")." Summary</h3>
							<small style=\"margin-left:20px;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"".($punch ? "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$this->proposal_hash');" : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'line_items', 'otf=1', 'proposal_hash=".$this->proposal_hash."')")."\" style=\"font-weight:normal;\"><-- Back</a></small>
							<div style=\"margin:20px;width:800px;padding:0;\">
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
									<tr>
										<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">".$item_menu."</td>
									</tr>
									<tr style=\"background-color:#cccccc;\">
										<td class=\"thead\" >Vendor / Product Line</td>
										<td class=\"thead\" style=\"text-align:right;\">List</td>
										<td class=\"thead\" style=\"text-align:right;\">Cost</td>
										<td class=\"thead\" style=\"text-align:right;\">Sell</td>
										<td class=\"thead\" style=\"text-align:right;\">Profit</td>
										<td class=\"thead\" style=\"text-align:right;\">GP</td>
									</tr>
									<tr>
										<td colspan=\"6\" style=\"background-color:#cccccc;\"></td>
									</tr>";

								for ($i = 0; $i < count($this->lines->line_info); $i++) {
									$sum[$this->lines->line_info[$i]['vendor_hash']]['name'] = $this->lines->line_info[$i]['vendor_name'];
									if ($this->lines->line_info[$i]['product_hash']) {
										$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['name'] = $this->lines->line_info[$i]['product_name'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['list'] += $this->lines->line_info[$i]['ext_list'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['cost'] += $this->lines->line_info[$i]['ext_cost'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['sell'] += $this->lines->line_info[$i]['ext_sell'];
									} //else {
										$sum[$this->lines->line_info[$i]['vendor_hash']]['list'] += $this->lines->line_info[$i]['ext_list'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['cost'] += $this->lines->line_info[$i]['ext_cost'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['sell'] += $this->lines->line_info[$i]['ext_sell'];
									//}
								}

								if (is_array($sum)) {
									while (list($id, $info) = each($sum)) {
										$tbl .= "
										<tr >
											<td style=\"vertical-align:top;font-weight:bold;padding-left:10px;\" colspan=\"6\">
												".$this->form->checkbox("name=".($punch ? "punch_item[]" : "line_item[]"), "value=vendor|".$id, (!$this->p->ck(get_class($this), 'E', 'item_details') ? "disabled" : NULL))."
												&nbsp;
												".($info['name'] ?
													$info['name'] : "<img src=\"images/alert.gif\" title=\"No vender!\" />&nbsp;&nbsp;<i>No assigned vendor!</i>")."
											</td>
										</tr>";
										if ($info['products']) {
											while (list($prod_id, $prod_info) = each($info['products'])) {
												$tbl .= "
												<tr >
													<td style=\"border-bottom:1px solid #cccccc;vertical-align:top;padding-left:45px;font-style:italic;\" >
														".$this->form->checkbox("name=".($punch ? "punch_item[]" : "line_item[]"), "value=prod|".$prod_id."|$id", (!$this->p->ck(get_class($this), 'E', 'item_details') ? "disabled" : NULL))
														."&nbsp;&nbsp;".
														$prod_info['name']."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;\">".math::format_num($prod_info['list'])."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;\">".math::format_num($prod_info['cost'])."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;\">".math::format_num($prod_info['sell'])."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;\">".math::format_num($prod_info['sell'] - $prod_info['cost'])."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;" .
														( defined('MARGIN_FLAG') && math::gp_margin( array(
	    														'cost'    =>  $prod_info['cost'],
	    														'sell'    =>  $prod_info['sell']
    														) ) < MARGIN_FLAG ?
        														"color:red;" : NULL
    													) . "\">" .
    													( bccomp($prod_info['cost'], 0, 2) == 1 && bccomp($prod_info['sell'], 0, 2) == 1 ?
    														bccomp( (float)bcmul( math::gp_margin( array(
        														'cost'    =>  $prod_info['cost'],
        														'sell'    =>  $prod_info['sell']
    														) ), 100, 4), 0, 4) == -1 ?
        														"(" .
        														( bccomp( (float)bcmul( math::gp_margin( array(
            														'cost'    =>  $prod_info['cost'],
            														'sell'    =>  $prod_info['sell']
        														) ), 100, 4), 100, 4) == -1 ?
            														"100" : (float)bcmul( bcmul( math::gp_margin( array(
                														'cost'    =>  $prod_info['cost'],
                														'sell'    =>  $prod_info['sell']
            														) ), 100, 4), -1, 4)
            													) . ")" : (float)bcmul( math::gp_margin( array(
	            													'cost' =>  $prod_info['cost'],
	            													'sell' =>  $prod_info['sell']
            													) ), 100, 4) . "%" : "&nbsp;"
            												) . "
													</td>
												</tr>";

												$list = bcadd($list, $prod_info['list'], 2);
												$cost = $cost + $prod_info['cost'];
												$sell = bcadd($sell, $prod_info['sell'], 2);
											}

											if (bccomp($list, $info['list']) || bccomp($cost, $info['cost']) || bccomp($sell, $info['sell']))
												$tbl .= "
												<tr >
													<td style=\"border-bottom:1px solid #cccccc;vertical-align:top;padding-left:45px;font-style:italic;\" >
														".$this->form->checkbox("name=".($punch ? "punch_item[]" : "line_item[]"), "value=prod|", (!$this->p->ck(get_class($this), 'E', 'item_details') ? "disabled" : NULL))
														."&nbsp;&nbsp;".
														"Misc/Other</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;\">".math::format_num(round($info['list'] - $list, 4))."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;\">".math::format_num(round($info['cost'] - $cost, 4))."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;\">".math::format_num(round($info['sell'] - $sell, 4))."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;\">".math::format_num(round(round($info['sell'] - $sell, 4) - round($info['cost'] - $cost, 4), 4))."</td>
													<td class=\"num_field\" style=\"border-bottom:1px solid #cccccc;" .
														( defined('MARGIN_FLAG') && math::gp_margin( array(
																'cost'    =>  bcsub($info['cost'], $cost, 2),
																'sell'    =>  bcsub($info['sell'], $sell, 2)
															) ) < MARGIN_FLAG ?
    															"color:red;" : NULL
														) . "\">" .
														( bccomp($info['cost'], 0, 2) == 1 && bccomp($info['sell'], 0, 2) == 1 ?
    														bccomp( bcmul( math::gp_margin( array(
        														'cost'    =>  bcsub($info['cost'], $cost, 2),
        														'sell'    =>  bcsub($info['sell'], $sell, 2)
    														) ), 100, 4), 0, 4) == -1 ?
        														"(" .
        														( bccomp( bcmul( math::gp_margin( array(
            														'cost'    =>  bcsub($info['cost'], $cost, 2),
            														'sell'    =>  bcsub($info['sell'], $sell, 2)
        														) ), 100, 4), -100, 4) == -1 ?
            														"100" : (float)bcmul( bcmul( math::gp_margin( array(
                														'cost'    =>  bcsub($info['cost'], $cost, 2),
                														'sell'    =>  bcsub($info['sell'], $sell, 2)
            														) ), 100, 4), -1, 4)
            													) . ")" : (float)bcmul( math::gp_margin( array(
                													'cost' =>  bcsub($info['cost'], $cost, 2),
                													'sell' =>  bcsub($info['sell'], $sell, 2)
            													) ), 100, 4) . "%" : NULL
            												) . "
													</td>
												</tr>";

											$cost = $sell = $profit = 0;
										}
										
										$total_list = $total_list + $info['list'];
										$total_cost += $info['cost'];
										$total_cost = round($total_cost, 2);
										$total_sell += $info['sell'];
										$total_profit = bcadd($total_profit, bcsub($info['sell'], $info['cost'], 2), 2);
										//if ($info['cost'] || $info['sell']) && !$info['products']) {
										$tbl .= "
										<tr style=\"background-color:#efefef;\">
											<td style=\"padding-top:10px;border-top:1px solid #cccccc;border-bottom:1px solid #cccccc;\">&nbsp;</td>
											<td class=\"num_field\" style=\"padding-top:10px;vertical-align:bottom;border-top:1px solid #cccccc;border-bottom:1px solid #cccccc;font-weight:bold;\">".math::format_num($info['list'])."</td>
											<td class=\"num_field\" style=\"padding-top:10px;vertical-align:bottom;border-top:1px solid #cccccc;border-bottom:1px solid #cccccc;font-weight:bold;\">".math::format_num($info['cost'])."</td>
											<td class=\"num_field\" style=\"padding-top:10px;vertical-align:bottom;border-top:1px solid #cccccc;border-bottom:1px solid #cccccc;font-weight:bold;\">".math::format_num($info['sell'])."</td>
											<td class=\"num_field\" style=\"padding-top:10px;vertical-align:bottom;border-top:1px solid #cccccc;border-bottom:1px solid #cccccc;font-weight:bold;\">".math::format_num(($info['sell'] - $info['cost']))."</td>
											<td class=\"num_field\" style=\"padding-top:10px;vertical-align:bottom;border-top:1px solid #cccccc;border-bottom:1px solid #cccccc;font-weight:bold;" .
        										( defined('MARGIN_FLAG') && bccomp( math::gp_margin( array(
		        										'cost'    =>  $info['cost'],
		        										'sell'    =>  $info['sell']
	        										) ), (float)MARGIN_FLAG, 4) ?
    	        										"color:red;" : NULL
	        									) . "\">" .
	        									( bccomp($info['cost'], 0, 2) == 1 && bccomp($info['sell'], 0, 2) == 1 ?
    												( bccomp( bcmul( math::gp_margin( array(
    													'cost'  =>  $info['cost'],
    													'sell'  =>  $info['sell']
    												) ), 100, 4), 0, 4) == -1 ?
        												"(" .
        												( bccomp( bcmul( math::gp_margin( array(
	        												'cost'  =>  $info['cost'],
	        												'sell'  =>  $info['sell']
        												) ), 100, 4), -100, 4) == -1 ?
            												"100" : (float)bcmul( bcmul( math::gp_margin( array(
		        												'cost'  =>  $info['cost'],
		        												'sell'  =>  $info['sell']
                											) ), 100, 4), -1, 4)
                										) . ")" : (float)bcmul( math::gp_margin( array(
	                										'cost'    =>  $info['cost'],
	                										'sell'    =>  $info['sell']
                										) ), 100, 4)
                									) . "%" : "&nbsp;"
                								) . "
											</td>
										</tr>";

										

										unset($list, $cost, $sell, $profit);

										$i++;
									}

								} else
									$tbl .= "
										<tr >
											<td style=\"vertical-align:top;font-weight:bold;padding-left:10px;\" colspan=\"6\">
												You have no active punchlist line items.
											</td>
										</tr>";

								if ( bccomp($this->current_proposal['overhead_rate'], 0, 5) ) {

                                    $overhead_rate = (float)$this->current_proposal['overhead_rate'];
                                    $overhead_type = $this->current_proposal['overhead_type'];

                                    if ($overhead_type == 'C') {

                                        $overhead_factor = _round( bcmul($total_cost, $overhead_rate, 4), 2);
                                        $total_cost = $total_cost + $overhead_factor;
                                    } elseif ( $overhead_type == 'S' ) {

                                        $overhead_factor = _round( bcmul($total_sell, $overhead_rate, 4), 2);
                                        $total_cost = $total_cost + $overhead_factor;
                                    }

								} elseif ($overhead_hash = global_classes::calculate_overhead($this->proposal_hash)) {
									$s = new system_config($this->current_hash);
									$a = $s->fetch_overhead_record($overhead_hash);

									$overhead_rate = $s->current_overhead['rate'];
									if ($s->current_overhead['apply_to'] == 'C') {
										$overhead_factor = _round($total_cost * $overhead_rate, 2);
										$total_cost = $total_cost + $overhead_factor;
									} elseif ($s->current_overhead['apply_to'] == 'S') {
										$overhead_factor = _round($total_sell * $overhead_rate, 2);
										$total_cost = $total_cost + $overhead_factor;
									}
								} elseif (defined('OVERHEAD_TYPE')) {
									$overhead_rate = (float)OVERHEAD_RATE;

									if (OVERHEAD_TYPE == 'C') {
										$overhead_factor = _round($total_cost * $overhead_rate, 2);
										$total_cost = $total_cost + $overhead_factor;
									} elseif (OVERHEAD_TYPE == 'S') {
										$overhead_factor = _round($total_sell * $overhead_rate, 2);
										$total_cost = $total_cost + $overhead_factor;
									}
									$total_profit = $total_sell - $total_cost;
								}

								//Get the sales tax info
								list($total_tax, $tax_rules, $tax_local, $indiv_tax) = $this->lines->calc_tax();
								if ($total_tax) {
									$sell_before = $total_sell;
									$total_sell += $total_tax;
								} else
									$sell_before = $total_sell;

								if ($this->lines->tax_country == 'CA' && $indiv_tax) {
									$k = 0;
								    while (list($tax_hash, $local_name) = each($tax_local)) {
								    	$k++;
								        $tax_tbl .= "
								        <div ".($k < count($tax_local) ? "style=\"margin-bottom:5px;\"" : NULL).">
                                            ".strtoupper($local_name).": ".math::format_num($tax_rules[$tax_hash])."
								        </div>";
								    }
								} else
                                    $tax_tbl = math::format_num($total_tax);


								$blended_gp = math::gp_margin( array(
									'cost'  =>  $total_cost,
									'sell'  =>  $sell_before
								) );
								if ($com_hash = global_classes::calculate_commission($this->proposal_hash)) {
									$s = new system_config($this->current_hash);
									$s->fetch_commission_record($com_hash);
									while (list($tier, $rule_array) = each($s->current_commission['rules'])) {
										if ($blended_gp >= $rule_array['tier_start'] && $blended_gp <= $rule_array['tier_end']) {
											if ($rule_array['tier_action'] == 'P') {
												$com_rate = _round($blended_gp, 2);
												$com_due = _round($total_profit * $com_rate, 2);
											} elseif ($rule_array['tier_action'] == 1) {
												$com_rate = $rule_array['tier_rate'];
												$com_due = _round($total_profit * $com_rate, 2);
											}
										}
									}
								}

								$tbl .= "
									<tr >
										<td style=\"padding-top:20px;text-align:right;background-color:#ffffff;color:#000000;font-size:14px;font-weight:bold;\" colspan=\"6\">
											<table style=\"width:350px;background-color:#cccccc;float:right;\" cellpadding=\"5\" cellspacing=\"1\">
												<tr>
													<td style=\"text-align:right;padding:5px;background-color:#efefef;\">Total List:</td>
													<td style=\"padding:5px;background-color:#efefef;\">".math::format_num($total_list)."</td>
												</tr>".($overhead_factor > 0 ? "
												<tr>
													<td style=\"text-align:right;padding:5px;background-color:#efefef;\">Overhead Factor:</td>
													<td style=\"padding:5px;background-color:#efefef;\">$".number_format($overhead_factor, 2)."</td>
												</tr>" : NULL)."
												<tr>
													<td style=\"text-align:right;padding:5px;background-color:#efefef;\">Total Cost:</td>
													<td style=\"padding:5px;background-color:#efefef;\">".math::format_num($total_cost)."</td>
												</tr>".($total_tax > 0 ? "
												<tr>
													<td style=\"text-align:right;padding:5px;background-color:#efefef;\">Total Sell Before Tax:</td>
													<td style=\"padding:5px;background-color:#efefef;\">".math::format_num($sell_before)."</td>
												</tr>
												<tr>
													<td style=\"text-align:right;padding:5px;background-color:#efefef;vertical-align:top;\">Sales Tax:</td>
													<td style=\"padding:5px;background-color:#efefef;\">".$tax_tbl."</td>
												</tr>" : NULL)."
												<tr>
													<td style=\"text-align:right;padding:5px;background-color:#efefef;\">Total Sell:</td>
													<td style=\"padding:5px;background-color:#efefef;\">".math::format_num($total_sell)."</td>
												</tr>
												<tr>
													<td style=\"text-align:right;padding:5px;background-color:#efefef;\">Total Profit Dollars:</td>
													<td style=\"padding:5px;background-color:#efefef;\">".math::format_num($total_profit)."</td>
												</tr>
												<tr>
													<td style=\"text-align:right;padding:5px;background-color:#efefef;\">Blended GP Margin:</td>
													<td style=\"padding:5px;background-color:#efefef;" .
                        								( defined('MARGIN_FLAG') && bccomp( math::gp_margin( array(
	                            								'cost'  =>  $total_cost,
	                            								'sell'  =>  $total_sell
	                        								) ), (float)MARGIN_FLAG, 4) ?
    	                        								"color:#ff0000;" : NULL
	                        							) . "\">" .
	                        							( bccomp( bcmul($blended_gp, 100, 4), 0, 4) == -1 ?
                                                            "(" .
    	                        							( bccomp( bcmul($blended_gp, 100, 4), -100, 4) == -1 ?
                                								"100" : (float)bcmul( bcmul($blended_gp, 100, 4), -1, 4)
    	                        							) . ")" : (float)bcmul($blended_gp, 100, 4)
    	                        						) . "%
													</td>
												</tr>" .
    	                        				( bccomp($com_due, 0, 4) == 1 ?
													"<tr>
														<td style=\"text-align:right;padding:5px;background-color:#efefef;vertical-align:top;\">Commission:</td>
														<td style=\"padding:5px;background-color:#efefef;\">
															\$" . number_format($com_due, 2) . "
															<div style=\"padding-top:5px;\">" . (float)bcmul($com_rate, 100, 4) . " %</div>
														</td>
													</tr>" : NULL
    	                        				) . "
											</table>
										</td>
									</tr>
								</table>
							</div>
						</div >";

			$tbl .= "
					</div>
				</td>
			</tr>
		</table>";

		if ($punch) {
			if ($this->ajax_vars['otf'])
				$this->content['html']['tcontent9_1'] = $tbl;
			else
				return $tbl;
		} else {
			if ($this->ajax_vars['otf'])
				$this->content['html']['tcontent4'] = $tbl;
			else
				return $tbl;
		}
	}

	function finalize_show_tax() {
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$install_hash = $this->ajax_vars['install_hash'];

		//$this->content['jscript'] = "alert('$proposal_hash' + '$install_hash');";
	}

	function finalize($local=NULL) {

		if ( ! $this->fetch_master_record($this->ajax_vars['proposal_hash']) )
			return $this->__trigger_error("System error encountered when attempting to lookup proposal record. Please reload window and try again. <!-- Tried fetching proposal [ {$this->ajax_vars['proposal_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		$punch = $this->ajax_vars['punch'];
		$this->lines = new line_items($this->proposal_hash, $punch);
		if ( $this->ajax_vars['sum'] )
			$sum_view = true;

		$this->lines->fetch_line_items(0, $this->lines->total);

		$tbl =
        $this->form->hidden( array(
            'popup_id'  => $this->ajax_vars['popup_id'],
            'punch'     => $punch
        ) ) . "
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;\">
					 <div style=\"background-color:#ffffff;height:100%;\">
					 	<div style=\"margin:15px 35px;\">
							<h3 style=\"margin-bottom:5px;color:#00477f;\">
    							Finalize " .
                                ( $punch ?
                                    "Punchlist " : NULL
                                ) . "Proposal
                            </h3>
							<small style=\"margin-left:20px;\"><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"".($punch ? "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$this->proposal_hash');" : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', '".($sum_view ? "summarized" : "line_items")."', 'otf=1', 'proposal_hash=".$this->proposal_hash."')")."\"><-- Back</a></small>
							<div style=\"margin:20px;width:800px;padding:0;\">
								<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
									<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
										<p id=\"feedback_message\"></p>
								</div>
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
									<tr>
										<td style=\"vertical-align:middle;\">
											Before printing, emailing, or faxing your proposal you must mark it as final. The finalization functions shown below will be
											performed and added to the proposal.
											<br /><br />
											<strong>To prevent any changes below from being executed, simply uncheck that item.</strong>
										</td>
									</tr>";

								//Discounting Conflicts
								for ( $i = 0; $i < count($this->lines->line_info); $i++ ) {

									if ( ! $this->lines->line_info[$i]['status'] && $this->lines->line_info[$i]['active'] && bccomp($this->lines->line_info[$i]['list'], 0, 2) == 1 && ! $this->lines->line_info[$i]['item_code'] ) {

										$lines[$i] =& $this->lines->line_info[$i];

										$discounting = new discounting($lines[$i]['vendor_hash'], 'v');

			                            $discount = $this->fill_discounts( array(
			                                'item_vendor_hash'      =>  $lines[$i]['vendor_hash'],
			                                'item_product_hash'     =>  $lines[$i]['product_hash'],
			                                'proposal_hash'         =>  $this->current_proposal['proposal_hash'],
			                                'item_no'               =>  $lines[$i]['item_no']
			                            ) );

										if ( ! $discounting->fetch_discount_record($discount['discount_hash']) )
    										continue;

										$disc_tbl .= "
										<tr>
											<td style=\"padding:8px;vertical-align:top;width:45%\">
												<div style=\"font-weight:bold;padding-bottom:5px;\">" .
													stripslashes($lines[$i]['vendor_name']) .
													( $lines[$i]['product_name'] ?
														": " . stripslashes($lines[$i]['product_name']) : NULL
													) . "
												</div>".($lines[$i]['item_no'] ? "
												<div style=\"padding-bottom:5px;\">
													Item: ".$lines[$i]['item_no']."
												</div>" : NULL)."
												<div style=\"padding-bottom:10px;\">
													Descr: ".(strlen($lines[$i]['item_descr']) > 45 ?
														substr($lines[$i]['item_descr'], 0, 45)."..." : $lines[$i]['item_descr'])."
												</div>
												<div style=\"padding-bottom:5px;\">
													Qty: ".$lines[$i]['qty']."
												</div>
												<div style=\"padding-bottom:5px;\">
													List: ".math::format_num($lines[$i]['list'])."
												</div>
												<div style=\"padding-bottom:5px;\">
													Cost: ".math::format_num($lines[$i]['cost'])."
												</div>
											</td>
											<td style=\"vertical-align:top;width:55%\">
												<div style=\"font-weight:bold;padding-bottom:5px;\">
													<img src=\"images/alert.gif\" />
													&nbsp;
													Discounting Conflict:
												</div>
												<table style=\"background-color:#cccccc;width:100%;\" cellpadding=\"5\" cellspacing=\"1\">
													<tr>
														<td style=\"background-color:#efefef;text-align:right;vertical-align:top;\">Discount Used:</td>
														<td style=\"background-color:#ffffff;\">" .
														( $lines[$i]['discount_hash'] ?
															( $lines[$i]['discount_hash'] == '_' ?
																"None" : stripslashes($lines[$i]['discount_descr']) . " ({$lines[$i]['discount_id']})"
															) : "None"
														) . "
														</td>
													</tr>";

													# Only show discount error for Trac #1343
													if ( ( $lines[$i]['discount_hash'] != $discounting->current_discount['discount_hash'] ) && ( ( ! $discounting->current_discount['discount_hash'] && $lines[$i]['discount_hash'] != '_' ) || $discounting->current_discount['discount_hash'] ) ) {

														$flag1 = 1;
														$flag++;

														$disc_tbl .= "
														<tr>
															<td style=\"background-color:#efefef;text-align:right;vertical-align:middle;color:#ff0000\">Disc Expected:</td>
															<td style=\"background-color:#ffffff;color:#ff0000\">" .
																$this->form->checkbox(
    																"name={$lines[$i]['item_hash']}_discount_hash",
    																"value={$discounting->current_discount['discount_hash']}",
    																"id=disc_err_check",
    																"title=To perform the expected action, leave this box checked.",
    																"checked"
																) . "&nbsp;" .
																( $discounting->current_discount['discount_hash'] ?
																	stripslashes($discounting->current_discount['discount_descr']) . " " .
																	( $discounting->current_discount['discount_id'] ?
																		"({$discounting->current_discount['discount_id']})" : NULL
																	) : "None"
																) . "
															</td>
														</tr>";
													}

													$disc_tbl .= "
													<tr>
														<td colspan=\"2\" style=\"padding:5px;background-color:#ffffff\">&nbsp;";

														if ( $discount['discount_type'] == 'F' ) {

															$disc_tbl .= "
															<table cellpadding=\"0\" style=\"width:90%;\">
																<tr>" .
																$this->form->hidden( array(
																    'discount_item_hash[]' => $lines[$i]['item_hash']
																) );

																for ( $j = 1; $j < 6; $j++ ) {

																	if ( bccomp( bcmul($lines[$i]["discount{$j}"], 100, 4), 0, 4) == 1 || bccomp($discount["discount{$j}"], 0, 4) == 1 ) {

																		if ( bccomp( bcmul($lines[$i]["discount{$j}"], 100, 4), $discount["discount{$j}"], 4) ) {

																			$flag1 = 1;
																			$flag++;

																			$style = "color:#ff0000;";
																			$check = $this->form->checkbox(
    																			"name={$lines[$i]['item_hash']}_discount{$j}",
    																			"value=" . ( $discount["discount{$j}"] ? $discount["discount{$j}"] : 'zero' ),
    																			"id=disc_err_check",
    																			"title=To perform the expected action, leave this box checked.",
    																			"checked"
																			) . "&nbsp;";
																		} else
																			unset($style, $check);

																		$disc_tbl .=
																		( $j == 4  ?
    																		"</tr><tr>" : NULL
																		) . "
																		<td style=\"vertical-align:top;\">
																			<table style=\"background-color:#cccccc;text-align:center;\" cellpadding=\"5\" cellspacing=\"1\">
																				<tr>
																					<td colspan=\"2\" style=\"" . ( ! $check ? "padding:7px 0;" : NULL ) . "background-color:#efefef;$style\">
																					$check
																					Disc $j:
																					</td>
																				</tr>
																				<tr >
																					<td style=\"background-color:#ffffff;vertical-align:top;$style;\">
																						<div style=\"text-align:center;padding-bottom:5px;\"><small>USED:</small></div>" .
																						( $lines[$i]["discount{$j}"] ?
																							(float)bcmul($lines[$i]["discount{$j}"], 100, 4) . "%" : ""
																						) . "
																					</td>
																					<td style=\"background-color:#ffffff;vertical-align:top;$style;\">
																						<div style=\"text-align:center;padding-bottom:5px;\"><small>EXPECTED:</small></div>" .
																						( $discount["discount{$j}"] ?
																							$discount["discount{$j}"] . "%" : ""
																						) . "
																					</td>
																				</tr>
																			</table>
																		</td>";
																	}
																}

																if ( $lines[$i]['gp_type'] == 'L' || $discount['list_discount'] ) {

																	$_list_disc = math::list_discount( array(
																		'list' =>  $lines[$i]['list'],
																		'sell' =>  $lines[$i]['sell']
																	) );
																	if ( ( $discount['list_discount'] && $lines[$i]['gp_type'] != 'L' ) || ( bccomp($_list_disc, $discount['list_discount'], 4) && bccomp($lines[$i]['list'], 0, 2) == 1 ) ) {

																		$flag1 = 1;
																		$flag++;
																		$style = "color:#ff0000;";
																		$check = $this->form->checkbox("name=".$lines[$i]['item_hash']."_list_discount", "value=".($discount['list_discount'] ? $discount['list_discount'] : 'zero'), "id=disc_err_check", "title=To perform the expected action, leave this box checked.", "checked")."&nbsp;";
																	} else
																		unset($style, $check);

																	$disc_tbl .= "
																	<td>
																		<table style=\"background-color:#cccccc;text-align:center;\" cellpadding=\"5\" cellspacing=\"1\">
																			<tr >
																				<td colspan=\"2\" style=\"".(!$check ? "padding:7px 0;" : NULL)."background-color:#efefef;$style\">
																					$check
																					Sell Disc:
																				</td>
																			</tr>
																			<tr $style>
																				<td style=\"vertical-align:top;background-color:#ffffff;$style\">
																					<div style=\"text-align:center;padding-bottom:5px;\"><small>USED:</small></div>" .
																					( $lines[$i]['gp_type'] == 'G' ?
    																					"&nbsp;" : math::list_discount( array(
	    																					'list' =>  $lines[$i]['list'],
	    																					'sell' =>  $lines[$i]['sell']
    																					) ) . "%"
    																				) . "
																				</td>
																				<td style=\"vertical-align:top;background-color:#ffffff;$style\">
																					<div style=\"text-align:center;padding-bottom:5px;\"><small>EXPECTED:</small></div>" .
																					( $discount['list_discount'] ?
																						(float)$discount['list_discount'] . "%" : NULL
																					) . "
																				</td>
																			</tr>
																		</table>
																	</td>";
																}
															$disc_tbl .= "
																</tr>
															</table>";
														}
														$disc_tbl .= "
														</td>
													</tr>
												</table>
											</td>
										</tr>
										<tr>
											<td style=\"background-color:#cccccc;\" colspan=\"2\"></td>
										</tr>";
										if ($flag) {
											$num_flags += $flag;
											$disc_tbl2 .= $disc_tbl;
										}
										unset($flag, $disc_tbl);
									}
								}
								//Discounting Summary
								if ($num_flags > 0) {
									$final_flag = true;
									$tbl .= "
									<tr style=\"background-color:#cccccc;\">
										<td class=\"thead\" >
											Finalization : Discounting Conflicts (".$num_flags.")
											<span style=\"padding-left:10px;font-weight:normal;\">[<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"if(confirm('Are you sure you want to toggle all shown discount conflicts? Please make sure all discounting conflicts are intended, as DealerChoice will make no changes.')){checkall($$('input[id=disc_err_check]'), 0)}\" style=\"font-weight:normal;\"><small>uncheck all</small></a>]</span>
										</td>
									</tr>
									<tr >
										<td>
											<div>
												<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">".
													$disc_tbl2 ."
												</table>
											</div>
										</td>
									</tr>";
								}

								//Tiered Discounting
								$this->lines->summarized_lines();
								for ($i = 0; $i < count($this->lines->line_info); $i++) {
									if (!$this->lines->line_info[$i]['status'] && $this->lines->line_info[$i]['active'] == 1) {
                                        $total_gp_cost += $this->lines->line_info[$i]['ext_cost'];
                                        $total_gp_sell += $this->lines->line_info[$i]['ext_sell'];

										$count[$this->lines->line_info[$i]['product_hash']] += 1;
										$sum[$this->lines->line_info[$i]['discount_hash']][$this->lines->line_info[$i]['vendor_hash']]['vendor_name'] = $this->lines->line_info[$i]['vendor_name'];
										$sum[$this->lines->line_info[$i]['discount_hash']][$this->lines->line_info[$i]['vendor_hash']]['total_list'] += $this->lines->line_info[$i]['ext_list'];
										$sum[$this->lines->line_info[$i]['discount_hash']][$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['name'] = $this->lines->line_info[$i]['product_name'];
										if ($this->lines->line_info[$i]['vendor_product_hash'] == $this->lines->line_info[$i]['vendor_hash'])
											$sum[$this->lines->line_info[$i]['discount_hash']][$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['list'] += $this->lines->line_info[$i]['ext_list'];

										$sum[$this->lines->line_info[$i]['discount_hash']][$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['gp_margin'] += $this->lines->line_info[$i]['gp_margin'];
										$sum[$this->lines->line_info[$i]['discount_hash']][$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['total_items'] = $count[$this->lines->line_info[$i]['product_hash']];
									}
								}

								$rand_id = array();
								if (is_array($sum)) {
									while (list($disc_hash, $vendor_info) = each($sum)) {
										while (list($vendor_hash, $product_info) = each($vendor_info)) {
											$tier_tbl = '';
											$disc_found = array();
											$discounting = new discounting($vendor_hash, "V");
											list($disc_item_hash, $disc_products, $discount_types, $discount_descr, $discount_id) = $discounting->fetch_discount_items_short($disc_hash);
											while (list($product_hash, $product_details) = each($product_info['products'])) {
												if (is_array($disc_products) && in_array($product_hash, $disc_products) && $discount_types[array_search($product_hash, $disc_products)] == 'T') {
													$key = array_search($product_hash, $disc_products);
													$discounting->fetch_discount_record($disc_hash);
													$discounting->fetch_item_record($disc_item_hash[$key]);

													$disc_found[] = array("product_hash" => $product_hash,
																		  "product_name" => $product_details['name'],
																		  "list"		 =>	$product_details['list']);
													$disc_flag = true;
													$tiered_disc_gp = true;

													$tier_tbl .= "
													<div style=\"font-weight:bold;padding-bottom:5px;\">" .
														$this->form->checkbox(
    														"name=tier_apply_{$product_hash}",
    														"value=1",
    														"checked",
    														"title=Uncheck this box to prevent tiered discounting from being applied"
														) . "&nbsp;
														Tiered Discount for " . stripslashes($product_details['name']) . ":
													</div>
													<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">
														<tr>
															<td style=\"background-color:#efefef;text-align:right;vertical-align:top;\">Discount Used:</td>
															<td style=\"background-color:#ffffff;\">" . stripslashes($discount_descr[$key]) . " ({$discount_id[$key]})</td>
														</tr>
														<tr>
															<td colspan=\"2\" style=\"background-color:#ffffff;\">";

															for ( $i = 1; $i < 7; $i++ ) {

																if ( bccomp($product_details['list'], $discounting->current_item["tier{$i}_from"], 2) != -1  && ( bccomp($product_details['list'], $discounting->current_item["tier{$i}_to"], 2) == -1 || ( $i > 1 && bccomp($discounting->current_item["tier{$i}_from"], 0, 2) == 1 && ! bccomp($discounting->current_item["tier{$i}_to"], 0, 2) ) ) ) {

																	$buy_disc = $discounting->current_item["tier{$i}_buy_discount"];
																	$sell_disc = $discounting->current_item["tier{$i}_sell_discount"];
																	$install_sell = $discounting->current_item["tier{$i}_install_sell"];

																	$cost = bcsub($product_details['list'], _round( bcmul($product_details['list'], $buy_disc, 4), 2), 2);
																	$sell = bcsub($product_details['list'], _round( bcmul($product_details['list'], $sell_disc, 4), 2), 2);

																	$item_rand = rand(500, 500000);
																	while ( in_array($item_rand, $rand_id) )
																		$item_rand = rand(500, 500000);

																	array_push($rand_id, $item_rand);

																	$tier_tbl .= $this->form->hidden( array(
    																	"disc_hash_{$item_rand}"           => $disc_hash,
																		"discount_item_hash_{$item_rand}"  => $disc_item_hash[$key],
																		"discount1_{$item_rand}"           => $buy_disc,
																		"sell_disc_{$item_rand}"		   => $sell_disc,
																		"install_sell_{$item_rand}"		   => $install_sell,
																		"vendor_hash_{$item_rand}"		   => $vendor_hash,
																		"tiered_disc[{$item_rand}]"		   => $product_hash
																	) ) . "
																	<div style=\"padding:5px;\">
																		<div style=\"padding-bottom:4px;\">
																			Total List for " . stripslashes($product_details['name']) . ": \$" . number_format($product_details['list'], 2) . "
																		</div>" .
    																	( ! bccomp($discounting->current_item["tier{$i}_to"], 0, 2) ?
    																		"Over \$" . number_format($discounting->current_item["tier{$i}_from"], 2)
        																	:
    																		"From \$" .
        																	( $i == 1 ?
            																	"1" : number_format($discounting->current_item["tier{$i}_from"], 2)
        																	) . "
    																		To \$" . number_format($discounting->current_item["tier{$i}_to"], 2)
    																	) . ":
																		<div style=\"padding-top:10px;\">
																			<table style=\"background-color:#cccccc;\" cellpadding=\"5\" cellspacing=\"1\">
																				<tr>
																					<td style=\"background-color:#efefef;text-align:right;\">Buy Discount: " . (float)bcmul($buy_disc, 100, 4) . "%</td>
																					<td style=\"background-color:#ffffff;padding-left:5px;font-weight:bold;text-align:right;\">Cost: \$" . number_format($cost, 2) . "</td>
																				</tr>".($sell_disc > 0 ? "
																				<tr>
																					<td style=\"background-color:#efefef;text-align:right;\">Sell Discount: " . (float)bcmul($sell_disc, 100, 4) . "%</td>
																					<td style=\"background-color:#ffffff;padding-left:5px;font-weight:bold;text-align:right;\">Sell: \$" . number_format($sell, 2) . "</td>
																				</tr>" : NULL)."
																				<tr>
																					<td style=\"background-color:#efefef;text-align:right;vertical-align:top;\">
																						GP Margin:
																					</td>
																					<td style=\"background-color:#ffffff;padding-left:5px;font-weight:bold;\">" .
                                                                                    ( bccomp($sell_disc, 0, 4) == 1 ?
                                                                                        (float)bcmul( math::gp_margin( array(
                                                                                            'cost'   =>  $cost,
                                                                                            'sell'   =>  $sell
                                                                                        ) ), 100, 4) . " %"
                                                                                        :
                                                                                        $this->form->text_box(
                                                                                            "name=gp_margin_{$item_rand}",
                                                                                            "value=",
                                                                                            "size=3",
                                                                                            "style=text-align:right;"
                                                                                        ) . " %
                                                                                        <div style=\"font-weight:normal;padding-top:5px;\"><small>(leave blank to<br />use current GP)</small></div>"
                                                                                    ) . "
																					</td>
																				</tr>" .
                                                                                ( bccomp($install_sell, 0, 2) == 1 ?
																					"<tr>
																						<td style=\"background-color:#efefef;text-align:right;\">Install Sell: " . (float)bcmul($install_sell, 100, 4) . " %</td>
																						<td style=\"background-color:#ffffff;padding-left:5px;font-weight:bold;\"></td>
																					</tr>" : NULL
                                                                                ) . "
																			</table>
																		</div>
																	</div>";

																	break;
																}
															}

														$tier_tbl .= "
															</td>
														</tr>
													</table>";
												}
											}

											if ( is_array($disc_products) && in_array('*', $disc_products) && $discount_types[ array_search('*', $disc_products) ] == 'T' ) {

												$decr_list = 0;
												for ( $i = 0; $i < count($disc_found); $i++ )
													$decr_list = bcadd($decr_list, $disc_found[$i]['list'], 2);

												$decr_list = bcsub($product_info['total_list'], $decr_list, 2);

												if ( count($product_info['products']) > count($disc_found) && bccomp($decr_list, 0, 2) == 1 ) {

													$disc_flag = true;
													$key = array_search('*', $disc_products);
													$discounting->fetch_discount_record($disc_hash);
													$discounting->fetch_item_record($disc_item_hash[$key]);

													$tier_tbl .= "
													<div style=\"font-weight:bold;padding-bottom:5px;padding-top:10px;\">" .
                                                        $this->form->checkbox(
                                                            "name=tier_apply_*",
                                                            "value=1",
                                                            "checked",
                                                            "title=Uncheck this box to prevent tiered discounting from being applied"
                                                        ) . "&nbsp;
														Tiered Discount for " . stripslashes($product_info['vendor_name']) . ":
													</div>
													<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">
														<tr>
															<td style=\"background-color:#efefef;text-align:right;vertical-align:top;\">Discount Used:</td>
															<td style=\"background-color:#ffffff;\">" . stripslashes($discount_descr[$key]) . " ({$discount_id[$key]})</td>
														</tr>
														<tr>
															<td colspan=\"2\" style=\"background-color:#ffffff;\">";

															for ( $i = 1; $i < 7; $i++ ) {

																if ( bccomp($decr_list, $discounting->current_item["tier{$i}_from"], 2) != -1 && ( bccomp($decr_list, $discounting->current_item["tier{$i}_to"], 2) == -1 || ( $i > 1 && bccomp($discounting->current_item["tier{$i}_from"], 0, 2) == 1 && ! bccomp($discounting->current_item["tier{$i}_to"], 0, 2) ) ) ) {

																	$buy_disc = $discounting->current_item["tier{$i}_buy_discount"];
																	$sell_disc = $discounting->current_item["tier{$i}_sell_discount"];
																	$install_sell = $discounting->current_item["tier{$i}_install_sell"];

																	$cost = bcsub($decr_list - _round( bcmul($decr_list, $buy_disc, 4), 2), 2);
																	$sell = bcsub($decr_list - _round( bcmul($decr_list, $sell_disc, 4), 2), 2);

																	$item_rand = rand(500, 500000);
																	while (in_array($item_rand, $rand_id))
																		$item_rand = rand(500, 500000);

																	array_push($rand_id, $item_rand);

																	$tier_tbl .= $this->form->hidden( array(
    																	"disc_hash_{$item_rand}"			=> $disc_hash,
																		"discount_item_hash_{$item_rand}" 	=> $disc_item_hash[$key],
																		"discount1_{$item_rand}"			=> $buy_disc,
																		"sell_disc_{$item_rand}"			=> $sell_disc,
																		"install_sell_{$item_rand}"			=> $install_sell,
																		"vendor_hash_{$item_rand}"			=> $vendor_hash,
																		"tiered_disc[{$item_rand}]"   		=> '*'
																	) ) . "
																	<div style=\"padding:5px;\">
																		<div style=\"padding-bottom:4px;\">
																			Total List for " . stripslashes($product_info['vendor_name']) . " <small>(all products)</small>: \$" . number_format($decr_list, 2) . "
																		</div>" .
    																	( ! bccomp($discounting->current_item["tier{$i}_to"], 0, 2) ?
    																		"Over \$" . number_format($discounting->current_item["tier{$i}_from"], 2)
        																	:
    																		"From \$" .
        																	( $i == 1 ?
            																	"1" : number_format($discounting->current_item["tier{$i}_from"], 2)
        																	) . "
    																		To \$" . number_format($discounting->current_item["tier{$i}_to"], 2)
        																) . ":
																		<div style=\"padding-top:10px;\">
																			<table style=\"background-color:#cccccc;\" cellpadding=\"5\" cellspacing=\"1\">
																				<tr>
																					<td style=\"background-color:#efefef;text-align:right;\">Buy Discount: " . (float)bcmul($buy_disc, 100, 4) . "%</td>
																					<td style=\"background-color:#ffffff;padding-left:5px;font-weight:bold;\">Cost: \$" . number_format($cost, 2) . "</td>
																				</tr>" .
                																( bccomp($sell_disc, 0, 4) == 1 ?
																					"<tr>
																						<td style=\"background-color:#efefef;text-align:right;\">Sell Discount: " . (float)bcmul($sell_disc, 100, 4) . "%</td>
																						<td style=\"background-color:#ffffff;padding-left:5px;font-weight:bold;\">Sell: \$" . number_format($sell, 2) . "</td>
																					</tr>" : NULL
                																) . "
																				<tr>
																					<td style=\"background-color:#efefef;vertical-align:top;text-align:right;\">
																						GP Margin:
																					</td>
																					<td style=\"background-color:#ffffff;padding-left:5px;font-weight:bold;\">" .
                                                                                    ( bccomp($sell_disc, 0, 2) == 1 ?
                                                                                        (float)bcmul( math::gp_margin( array(
                                                                                            'cost'   =>  $cost,
                                                                                            'sell'   =>  $sell
                                                                                        ) ), 100, 4)
                                                                                        :
                                                                                        $this->form->text_box(
                                                                                            "name=gp_margin_{$item_rand}",
                                                                                            "value=",
                                                                                            "size=3",
                                                                                            "style=text-align:right;"
                                                                                        ) . " %
                                                                                        <div style=\"font-weight:normal;padding-top:5px;\"><small>(leave blank to<br />use current GP)</small></div>"
                                                                                    ) . "
																					</td>
																				</tr>" .
																				( bccomp($install_sell, 0, 2) == 1 ?
																					"<tr>
																						<td style=\"background-color:#efefef;text-align:right;\">Install Sell: \$" . (float)bcmul($install_sell, 100, 2) . "%</td>
																						<td style=\"background-color:#ffffff;padding-left:5px;font-weight:bold;\"></td>
																					</tr>" : NULL
																				) . "
																			</table>
																		</div>
																	</div>";

																	break;
																}
															}

														$tier_tbl .= "
															</td>
														</tr>
													</table>";
												}
											}

											if ($disc_flag) {
												$l++;
												$tier_tbl_parent .= ($l > 1 ? "
												<tr>
													<td style=\"background-color:#cccccc;\" colspan=\"2\"></td>
												</tr>" : NULL)."
												<tr>
													<td style=\"padding:8px;vertical-align:top;width:45%\">
														<div style=\"font-weight:bold;padding-bottom:10px;\">
															".$product_info['vendor_name']."
														</div>
														<div style=\"padding-bottom:5px;\">
															Total List: ".math::format_num($product_info['total_list'])."
														</div>
													</td>
													<td style=\"vertical-align:top;width:55%\">".$tier_tbl."</td>
												</tr>";
												unset($disc_flag, $disc_found, $tier_tbl);
											}
										}
									}
								}

								//Tiered Discounting Summary
								if ($tier_tbl_parent) {
									$final_flag = true;
									$tbl .= "
									<tr style=\"background-color:#cccccc;\">
										<td class=\"thead\" >Finalization : Tiered Discounting</td>
									</tr>
									<tr >
										<td>
											<div>
												<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
													".$tier_tbl_parent."
												</table>
											</div>
										</td>
									</tr>";
								}
								//Vendor Charges
								//$this->lines->summarized_lines();
								$sum = array();
								$charges = array();
								for ($i = 0; $i < count($this->lines->line_info); $i++) {
									if (!$this->lines->line_info[$i]['status']) {
										if (!$sum[$this->lines->line_info[$i]['vendor_hash']]) {
											$result = $this->db->query("SELECT vendors.vendor_name , vendors.sof ,
																		vendors.sof_quoted , vendors.frf ,
																		vendors.frf_quoted , vendors.fsf ,
																		vendors.fsf_quoted , vendors.cbd
																		FROM `vendors`
																		WHERE vendors.vendor_hash = '".$this->lines->line_info[$i]['vendor_hash']."'");

											if ($row = $this->db->fetch_assoc($result))
												$sum[$this->lines->line_info[$i]['vendor_hash']]['charges'] = $row;
										}
										if (!$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]) {
											$result = $this->db->query("SELECT vendor_products.product_frf , vendor_products.product_frf_quoted
																		FROM `vendor_products`
																		WHERE `product_hash` = '".$this->lines->line_info[$i]['product_hash']."'");
											if ($row = $this->db->fetch_assoc($result))
												$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['charges'] = $row;

											if ($this->lines->line_info[$i]['discount_item_hash']) {
												$result = $this->db->query("SELECT `discount_frf` , `discount_frf_quoted`
																			FROM `discount_details`
																			WHERE `item_hash` = '".$this->lines->line_info[$i]['discount_item_hash']."' AND `product_hash` = '".$this->lines->line_info[$i]['product_hash']."'");
												$row = $this->db->fetch_assoc($result);
												$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['charges']['product_frf'] = $row['discount_frf'];
												if ($row['discount_frf_quoted'] == 1)
													$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['charges']['product_frf_quoted'] = $row['discount_frf_quoted'];

											}

										}
										$sum[$this->lines->line_info[$i]['vendor_hash']]['name'] = $this->lines->line_info[$i]['vendor_name'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['total_cost'] += $this->lines->line_info[$i]['ext_cost'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['total_list'] += $this->lines->line_info[$i]['ext_list'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['name'] = $this->lines->line_info[$i]['product_name'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['discount_item_hash'] = $this->lines->line_info[$i]['discount_item_hash'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['cost'] += $this->lines->line_info[$i]['ext_cost'];
										$sum[$this->lines->line_info[$i]['vendor_hash']]['products'][$this->lines->line_info[$i]['product_hash']]['list'] += $this->lines->line_info[$i]['ext_list'];
									}
								}

								if (is_array($sum)) {
									while (list($id, $info) = each($sum)) {
										$total_cost = 0;
										$total_list = 0;
										$prod_freight = array("list" => 0, "cost" => 0);
										$rand_id = array();
										// Updated for Trac #490
										unset($vendor_chrg_tbl, $frf_item, $sof_item, $fsf_item, $cbd_item);

										$l++;
										while (list($prod_id, $prod_info) = each($info['products'])) {
											$total_cost += $prod_info['cost'];
											$total_list += $prod_info['list'];

											if ($prod_info['charges']['product_frf'] || $prod_info['charges']['product_frf_quoted']) {
												$item_rand = rand(500, 500000);
												while (in_array($item_rand, $rand_id))
													$item_rand = rand(500, 500000);

												array_push($rand_id, $item_rand);

												if ($prod_info['charges']['product_frf']) {
													$frf = explode("|", $prod_info['charges']['product_frf']);
													$frf_item = array();
													for ($k = 0; $k < count($frf); $k++) {
														list($a, $b) = explode("=", $frf[$k]);
														$b = str_replace("}", "", $b);
														$frf_item[$a] = $b;
													}
												}
											}

											if ((($prod_info['list'] > 0 || $prod_info['cost'] > 0) && $prod_info['charges']['product_frf_quoted']) || ($prod_info['list'] > 0 || $prod_info['cost'] > 0) && $prod_info['charges']['product_frf'] && $frf_item['product_frf_amount'] && ($prod_info['charges']['product_frf_quoted'] || ($frf_item['product_frf_amount_type'] == 'L' && $prod_info['list'] < $frf_item['product_frf_amount']) || ($frf_item['product_frf_amount_type'] == 'N' && $prod_info['cost'] < $frf_item['product_frf_amount']))) {
												$amount_to_add = $frf_item['product_frf_amount_add'];
												if ($frf_item['product_frf_amount_add_type'] == 'P') {
													$amount_to_add = ($frf_item['product_frf_amount_add_type_of'] == 'L' ?
														$prod_info['list'] : $prod_info['cost']) * $amount_to_add;
												}
												if ($amount_to_add > 0 || $charges['product_frf_quoted']) {
													$prod_freight['list'] += $prod_info['list'];
													$prod_freight['cost'] += $prod_info['cost'];
													$product_flag = true;
													$chrg_flag++;

													$chrg_tbl .= "
													<tr>
														<td style=\"padding:8px;vertical-align:top;width:45%;\">
															<div style=\"font-style:italic;padding-bottom:10px;margin-left:40px;\">
																".$prod_info['name']."
															</div>
															<div style=\"padding-bottom:5px;margin-left:40px;\">
																Total List: ".math::format_num($prod_info['list'])."
															</div>
															<div style=\"padding-bottom:5px;margin-left:40px;\">
																Total Cost: ".math::format_num($prod_info['cost'])."
															</div>
														</td>
														<td style=\"vertical-align:top;width:55%\">
															<div style=\"font-weight:bold;padding-bottom:5px;\">".
																$this->form->hidden(array("charge_type".$item_rand => 'frf',
																						  "product_hash".$item_rand => $prod_id,
																						  "vendor_charges[".$item_rand."]" => $id)).(!$prod_info['charges']['product_frf_quoted'] ?
																$this->form->hidden(array("cost".$item_rand => $amount_to_add)) : NULL).
																$this->form->checkbox("name=frf".$item_rand, "value=1", "title=Check this box to apply this freight charge", "checked")."
																&nbsp;
																Product Freight Charge:
															</div>
															<table style=\"background-color:#cccccc;width:80%;\" cellpadding=\"5\" cellspacing=\"1\">
																<tr>
																	<td style=\"background-color:#efefef;\">".($frf_item ? "
																		If under ".math::format_num($frf_item['product_frf_amount'])." ".($frf_item['product_frf_amount_type'] == 'L' ? "
																			List" : "Net")."
																		<div style=\"padding:5px;\">
																			Then add ".($frf_item['product_frf_amount_add_type'] == 'P' ?
																				($frf_item['product_frf_amount_add']*100)."% of ".($frf_item['product_frf_amount_add_type_of'] == 'L' ? "
																					List" : "Net") : math::format_num($frf_item['product_frf_amount_add']))."
																		</div>" : NULL).($prod_info['charges']['product_frf_quoted'] ? "
																		This freight needs to be quoted!" : NULL)."
																	</td>
																</tr>
																<tr>
																	<td style=\"background-color:#ffffff;\">
																		<table style=\"width:100%;\">
																			<tr>
																				<td style=\"text-align:right;\">Description: </td>
																				<td>".$this->form->text_box("name=charge_val".$item_rand."[item_descr]", "value=".$info['name']." ".$prod_info['name']." Freight Charge", "maxlength=255", "size=30")."</td>
																			</tr>
																			<tr>
																				<td style=\"text-align:right;\">Cost: </td>
																				<td nowrap>".($prod_info['charges']['product_frf_quoted'] ?
																					$this->form->text_box("name=cost".$item_rand,
																					                      "value=".number_format($amount_to_add, 2),
																					                      "maxlength=13",
																					                      "size=8",
																					                      "style=text-align:right;",
																					                      "onFocus=this.select()",
																					                      "onBlur=if(this.value){this.value=formatCurrency(this.value)}") : "$".number_format($amount_to_add, 2))."
																					&nbsp;&nbsp;
																					Sell:
																					".$this->form->text_box("name=charge_val".$item_rand."[sell]",
																					                        "id=charge_val".$item_rand."_sell",
																					                        "value=".number_format($amount_to_add, 2),
																					                        "maxlength=13",
																					                        "size=8",
																					                        "style=text-align:right;",
																					                        "onFocus=this.select()",
																					                        "onBlur=this.value=formatCurrency(this.value);")."
			                                                                        <span style=\"margin-left:15px;\">
			                                                                            [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'gp_calculator', 'popup_id=gp_window', 'item=charge_val".$item_rand."_sell', 'rand_id=".$item_rand."'".(!$info['charges']['product_frf_quoted'] ? ", 'cost=".$amount_to_add."'" : NULL).");\">gp margin</a></small>]
			                                                                        </span>
																				</td>
																			</tr>
																		</table>
																	</td>
																</tr>
															</table>
														</td>
													</tr>";
												}
											}
										}

										if ($info['charges']['sof']) {
											$sof = explode("|", $info['charges']['sof']);
											$sof_item = array();
											for ($k = 0; $k < count($sof); $k++) {
												list($a, $b) = explode("=", $sof[$k]);
												$sof_item[$a] = $b;
											}
										}
										if ((($total_cost > 0 || $total_list > 0) && $info['charges']['sof_quoted']) || (($total_cost > 0 || $total_list > 0) && $info['charges']['sof'] && $sof_item['sof_amount'] && (($sof_item['sof_amount_type'] == 'L' && $total_list < $sof_item['sof_amount']) || ($sof_item['sof_amount_type'] == 'N' && $total_cost < $sof_item['sof_amount'])) && strtotime($sof_item['sof_effective_date']) <= strtotime(date("Y-m-d")) && (!$sof_item['sof_expire_date'] || ($sof_item['sof_expire_date'] && strtotime($sof_item['sof_expire_date']) > strtotime(date("Y-m-d")))))) {
											$amount_to_add = $sof_item['sof_amount_add'];
											if ($sof_item['sof_amount_add_type'] == 'P') {
												$amount_to_add = ($sof_item['sof_amount_add_type_of'] == 'L' ?
													$total_list : $total_cost) * $amount_to_add;
											}
											if ($amount_to_add > 0 || $info['charges']['sof_quoted']) {
												$vendor_flag = true;
												$chrg_flag++;

												$item_rand = rand(500, 500000);
												while (in_array($item_rand, $rand_id))
													$item_rand = rand(500, 500000);

												array_push($rand_id, $item_rand);

												$vendor_chrg_tbl .= "
												<div style=\"font-weight:bold;padding-bottom:5px;\">".
													$this->form->hidden(array("charge_type".$item_rand => 'sof',
																			  "vendor_charges[".$item_rand."]" => $id)).(!$info['charges']['sof_quoted'] ?
													$this->form->hidden(array("cost".$item_rand => $amount_to_add)) : NULL)."
													".$this->form->checkbox("name=sof".$item_rand, "value=1", "title=Check this box to apply this small order fee", "checked")."
													&nbsp;
													Small Order Fee:
												</div>
												<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">
													<tr>
														<td style=\"background-color:#efefef;\">".($sof_item ? "
															If under ".math::format_num($sof_item['sof_amount'])." ".($sof_item['sof_amount_type'] == 'L' ? "
																List" : "Net")."
															<div style=\"padding:5px;\">
																Then add ".($sof_item['sof_amount_add_type'] == 'P' ?
																	($sof_item['sof_amount_add']*100)."% of ".($sof_item['sof_amount_add_type_of'] == 'L' ? "
																		List" : "Net") : math::format_num($sof_item['sof_amount_add']))."
															</div>" : NULL).($info['charges']['sof_quoted'] ? "
															This small order fee needs to be quoted!" : NULL)."
														</td>
													</tr>
													<tr>
														<td style=\"background-color:#ffffff;\">
															<table style=\"width:100%;\">
																<tr>
																	<td style=\"text-align:right;\" id=\"err".$item_rand."_descr\">Description: </td>
																	<td>".$this->form->text_box("name=charge_val".$item_rand."[item_descr]",
																	                            "value=".$info['name']." Small Order Fee",
																	                            "maxlength=255",
																	                            "size=35")."
                                                                    </td>
																</tr>
																<tr>
																	<td style=\"text-align:right;\">Cost: </td>
																	<td nowrap>".($info['charges']['sof_quoted'] ?
																		$this->form->text_box("name=cost".$item_rand,
																		                      "value=".number_format($amount_to_add, 2),
																		                      "maxlength=13",
																		                      "size=8",
																		                      "style=text-align:right",
																		                      "onFocus=this.select()",
																		                      "onBlur=if(this.value){this.value=formatCurrency(this.value);}") : "$".number_format($amount_to_add, 2))."
																		&nbsp;&nbsp;
																		<span id=\"err".$item_rand."_sell\">Sell: </span>
																		".$this->form->text_box("name=charge_val".$item_rand."[sell]",
																		                        "id=charge_val".$item_rand."_sell",
																		                        "value=".number_format($amount_to_add, 2),
																		                        "maxlength=13",
																		                        "size=8",
																		                        "style=text-align:right;",
																		                        "onFocus=this.select()",
																		                        "onBlur=this.value=formatCurrency(this.value);")."
																		<span style=\"margin-left:15px;\">
                                                                            [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'gp_calculator', 'popup_id=gp_window', 'item=charge_val".$item_rand."_sell', 'rand_id=".$item_rand."'".(!$info['charges']['sof_quoted'] ? ", 'cost=".$amount_to_add."'" : NULL).");\">gp margin</a></small>]
																		</span>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>";
											}
										}

										// Added for Trac #490
										unset($frf_item);

										if ($info['charges']['frf'] && !$info['charges']['product_frf']) {
											$frf = explode("|", $info['charges']['frf']);
											$frf_item = array();
											for ($k = 0; $k < count($frf); $k++) {
												list($a, $b) = explode("=", $frf[$k]);
												$frf_item[$a] = $b;
											}
										}

										$frt_list = $total_list - $prod_freight['list'];
										$frt_cost = $total_cost - $prod_freight['cost'];

										if ((($frt_cost > 0 || $frt_list > 0) && $info['charges']['frf_quoted']) || (($frt_cost > 0 || $frt_list > 0) && $info['charges']['frf'] && $frf_item['frf_amount'] && (($frf_item['frf_amount_type'] == 'L' && $frt_list < $frf_item['frf_amount']) || ($frf_item['frf_amount_type'] == 'N' && $frt_cost < $frf_item['frf_amount'])) && strtotime($frf_item['frf_effective_date']) <= strtotime(date("Y-m-d")) && (!$frf_item['frf_expire_date'] || ($frf_item['frf_expire_date'] && strtotime($frf_item['frf_expire_date']) > strtotime(date("Y-m-d")))))) {
											$amount_to_add = $frf_item['frf_amount_add'];
											if ($frf_item['frf_amount_add_type'] == 'P') {
												$amount_to_add = ($frf_item['frf_amount_add_type_of'] == 'L' ?
													$frt_list : $frt_cost) * $amount_to_add;
											}
											if ($amount_to_add > 0 || $info['charges']['frf_quoted']) {
												$vendor_flag = true;
												$chrg_flag++;

												$item_rand = rand(500, 500000);
												while (in_array($item_rand, $rand_id))
													$item_rand = rand(500, 500000);

												array_push($rand_id, $item_rand);

												$vendor_chrg_tbl .= "
												<div style=\"font-weight:bold;padding-bottom:5px;padding-top:10px\">".
													$this->form->hidden(array("charge_type".$item_rand => 'frf',
																			  "vendor_charges[".$item_rand."]" => $id)).(!$info['charges']['frf_quoted'] ?
													$this->form->hidden(array("cost".$item_rand => $amount_to_add)) : NULL)."
													".$this->form->checkbox("name=frf".$item_rand, "value=1", "title=Check this box to apply this freight charge", "checked")."
													&nbsp;
													Freight Charge:
												</div>
												<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">
													<tr>
														<td style=\"background-color:#efefef;\">".($frf_item ? "
															If under ".math::format_num($frf_item['frf_amount'])." ".($frf_item['frf_amount_type'] == 'L' ? "
																List" : "Net")."
															<div style=\"padding:5px;\">
																Then add ".($frf_item['frf_amount_add_type'] == 'P' ?
																	($frf_item['frf_amount_add']*100)."% of ".($frf_item['frf_amount_add_type_of'] == 'L' ? "
																		List" : "Net") : math::format_num($frf_item['frf_amount_add']))."
															</div>" : NULL).($info['charges']['frf_quoted'] ? "
															This freight charge needs to be quoted!" : NULL)."
														</td>
													</tr>
													<tr>
														<td style=\"background-color:#ffffff;\">
															<table style=\"width:100%;\">
																<tr>
																	<td style=\"text-align:right;\" id=\"err".$item_rand."_descr\">Description: </td>
																	<td>".$this->form->text_box("name=charge_val".$item_rand."[item_descr]",
    																	                        "value=".$info['name']." Freight Charge",
    																	                        "maxlength=255",
    																	                        "size=35")."
                                                                    </td>
																</tr>
																<tr>
																	<td style=\"text-align:right;\">Cost: </td>
																	<td nowrap>".($info['charges']['frf_quoted'] ?
																		$this->form->text_box("name=cost".$item_rand,
																		                      "value=".number_format($amount_to_add, 2),
																		                      "maxlength=13",
																		                      "size=8",
																		                      "style=text-align:right;",
                        																	  "onFocus=this.select()",
																		                      "onBlur=this.value=formatCurrency(this.value);") : "$".number_format($amount_to_add, 2))."
																		&nbsp;&nbsp;
																		<span id=\"err".$item_rand."_sell\">Sell: </span>
																		".$this->form->text_box("name=charge_val".$item_rand."[sell]",
																		                        "id=charge_val".$item_rand."_sell",
																		                        "value=".number_format($amount_to_add, 2),
																		                        "maxlength=13",
																		                        "size=8",
																		                        "style=text-align:right;",
                        																		"onFocus=this.select()",
																		                        "onBlur=this.value=formatCurrency(this.value);")."
                                                                        <span style=\"margin-left:15px;\">
                                                                            [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'gp_calculator', 'popup_id=gp_window', 'item=charge_val".$item_rand."_sell', 'rand_id=".$item_rand."'".(!$info['charges']['frf_quoted'] ? ", 'cost=".$amount_to_add."'" : NULL).");\">gp margin</a></small>]
                                                                        </span>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>";
											}
										}
										//Fuel Surcharge Fee
										if ($info['charges']['fsf']) {
											$fsf = explode("|", $info['charges']['fsf']);
											$fsf_item = array();
											for ($k = 0; $k < count($fsf); $k++) {
												list($a, $b) = explode("=", $fsf[$k]);
												$fsf_item[$a] = $b;
											}
										}

										if ((($total_cost > 0 || $total_list > 0) && $info['charges']['fsf_quoted']) || (($total_cost > 0 || $total_list > 0) && $info['charges']['fsf'] && $fsf_item['fsf_amount_add'] && strtotime($fsf_item['fsf_effective_date']) <= strtotime(date("Y-m-d")) && (!$fsf_item['fsf_expire_date'] || ($fsf_item['fsf_expire_date'] && strtotime($fsf_item['fsf_expire_date']) > strtotime(date("Y-m-d")))))) {
											$amount_to_add = $fsf_item['fsf_amount_add'];
											if ($fsf_item['fsf_amount_add_type'] == 'P') {
												$amount_to_add = ($fsf_item['fsf_amount_add_type_of'] == 'L' ?
													$total_list : $total_cost) * $amount_to_add;
											}
											if ($amount_to_add > 0 || $info['charges']['fsf_quoted']) {
												$vendor_flag = true;
												$chrg_flag++;

												$item_rand = rand(500, 500000);
												while (in_array($item_rand, $rand_id))
													$item_rand = rand(500, 500000);

												array_push($rand_id, $item_rand);

												$vendor_chrg_tbl .= "
												<div style=\"font-weight:bold;padding-bottom:5px;padding-top:10px\">".
													$this->form->hidden(array("charge_type".$item_rand => 'fsf',
																			  "vendor_charges[".$item_rand."]" => $id)).(!$info['charges']['fsf_quoted'] ?
													$this->form->hidden(array('cost'.$item_rand => $amount_to_add)) : NULL)."
													".$this->form->checkbox("name=fsf".$item_rand, "value=1", "title=Check this box to apply this fuel surcharge", "checked")."
													&nbsp;
													Fuel Surcharge:
												</div>
												<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">
													<tr>
														<td style=\"background-color:#efefef;\">".($fsf_item ? "
															Add ".($fsf_item['fsf_amount_add_type'] == 'P' ?
																($fsf_item['fsf_amount_add']*100)."% of ".($fsf_item['fsf_amount_add_type_of'] == 'L' ? "
																	List" : "Net") : math::format_num($fsf_item['fsf_amount_add'])) : NULL).($info['charges']['fsf_quoted'] ? "
															This fuel surcharge needs to be quoted!" : NULL)."
														</td>
													</tr>
													<tr>
														<td style=\"background-color:#ffffff;\">
															<table style=\"width:100%;\">
																<tr>
																	<td style=\"text-align:right;\" id=\"err".$item_rand."_descr\">Description: </td>
																	<td>".$this->form->text_box("name=charge_val".$item_rand."[item_descr]",
																	                            "value=".$info['name']." Fuel Surcharge Fee",
																	                            "maxlength=255",
																	                            "size=35")."
                                                                    </td>
																</tr>
																<tr>
																	<td style=\"text-align:right;\">Cost: </td>
																	<td >".($info['charges']['fsf_quoted'] ?
																		$this->form->text_box("name=cost".$item_rand,
																		                      "value=".number_format($amount_to_add, 2),
																		                      "maxlength=13",
																		                      "size=8",
																		                      "style=text-align:right;",
                        																	  "onFocus=this.select()",
																		                      "onBlur=if(this.value){this.value=formatCurrency(this.value);}") : "$".number_format($amount_to_add, 2))."
																		&nbsp;&nbsp;
																		<span id=\"err".$item_rand."_sell\">Sell: </span>
																		".$this->form->text_box("name=charge_val".$item_rand."[sell]",
																		                        "id=charge_val".$item_rand."_sell",
																		                        "value=".number_format($amount_to_add, 2),
																		                        "maxlength=13",
																		                        "size=8",
																		                        "style=text-align:right;",
                        																		"onFocus=this.select()",
																		                        "onBlur=this.value=formatCurrency(this.value);")."
                                                                        <span style=\"margin-left:15px;\">
                                                                            [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'gp_calculator', 'popup_id=gp_window', 'item=charge_val".$item_rand."_sell', 'rand_id=".$item_rand."'".(!$info['charges']['fsf_quoted'] ? ", 'cost=".$amount_to_add."'" : NULL).");\">gp margin</a></small>]
                                                                        </span>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>";
											}
										}
										//CBD Fee
										if ($info['charges']['cbd']) {
											$cbd = explode("|", $info['charges']['cbd']);
											$cbd_item = array();
											for ($k = 0; $k < count($cbd); $k++) {
												list($a, $b) = explode("=", $cbd[$k]);
												$cbd_item[$a] = $b;
											}
										}

										if (($total_cost > 0 || $total_list > 0) && $info['charges']['cbd'] && $cbd_item['cbd_amount_add'] && strtotime($cbd_item['cbd_effective_date']) <= strtotime(date("Y-m-d")) && (!$cbd_item['cbd_expire_date'] || ($cbd_item['cbd_expire_date'] && strtotime($cbd_item['cbd_expire_date']) > strtotime(date("Y-m-d"))))) {
											$amount_to_add = $cbd_item['cbd_amount_add'];
											if ($amount_to_add > 0) {
												$vendor_flag = true;
												$chrg_flag++;

												$item_rand = rand(500, 500000);
												while (in_array($item_rand, $rand_id))
													$item_rand = rand(500, 500000);

												array_push($rand_id, $item_rand);

												$vendor_chrg_tbl .= "
												<div style=\"font-weight:bold;padding-bottom:5px;padding-top:10px\">".
													$this->form->hidden(array("charge_type".$item_rand => 'cbd',
																			  'cost'.$item_rand => $amount_to_add,
																			  "vendor_charges[".$item_rand."]" => $id))."
													".$this->form->checkbox("name=cbd".$item_rand, "value=1", "title=Check this box to apply this CBD fee", "checked")."
													&nbsp;
													Call Before Delivery Charge:
												</div>
												<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">
													<tr>
														<td style=\"background-color:#efefef;\">
															Add ".math::format_num($cbd_item['cbd_amount_add'])."
														</td>
													</tr>
													<tr>
														<td style=\"background-color:#ffffff;\">
															<table style=\"width:100%;\">
																<tr>
																	<td style=\"text-align:right;\" id=\"err".$item_rand."_descr\">Description: </td>
																	<td>".$this->form->text_box("name=charge_val".$item_rand."[item_descr]",
																	                            "value=".$info['name']." Call Before Delivery Charge",
																	                            "maxlength=255",
																	                            "size=35")."
                                                                    </td>
																</tr>
																<tr>
																	<td style=\"text-align:right;\">Cost: </td>
																	<td >
																		$".number_format($amount_to_add, 2)."
																		&nbsp;&nbsp;
																		<span id=\"err".$item_rand."_sell\">Sell: </span>
																		".$this->form->text_box("name=charge_val".$item_rand."[sell]",
													                                            "id=charge_val".$item_rand."_sell",
																		                        "value=".number_format($amount_to_add, 2),
																		                        "maxlength=13",
																		                        "size=8",
																		                        "style=text-align:right;",
                                            													"onFocus=this.select()",
																		                        "onBlur=this.value=formatCurrency(this.value);")."
                                                                        <span style=\"margin-left:15px;\">
                                                                            [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'gp_calculator', 'popup_id=gp_window', 'item=charge_val".$item_rand."_sell', 'rand_id=".$item_rand."', 'cost=".$amount_to_add."');\">gp margin</a></small>]
                                                                        </span>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>";
											}
										}
										if ($vendor_flag || $product_flag) {
											$vendor_chrg_tbl_parent .= "
											<tr>
												<td style=\"padding:8px;vertical-align:top;width:45%\">
													<div style=\"font-weight:bold;padding-bottom:10px;\">
														".$info['name']."
													</div>
													<div style=\"padding-bottom:5px;\">
														Total List: ".math::format_num($total_list)."
													</div>
													<div style=\"padding-bottom:5px;\">
														Total Cost: ".math::format_num($total_cost)."
													</div>
												</td>
												<td style=\"vertical-align:top;width:55%\">".
													$vendor_chrg_tbl . ($product_flag ? $chrg_tbl : NULL)."
												</td>
											</tr>";
											unset($chrg_tbl);
										}
										unset($vendor_flag, $product_flag);
									}
								}

								if ($vendor_flag || $chrg_flag) {
									$final_flag = true;
									$tbl .= "
									<tr style=\"background-color:#cccccc;\">
										<td class=\"thead\" >Finalization : Vendor Charges & Fees (".$chrg_flag.")</td>
									</tr>
									<tr >
										<td>
											<div>
												<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
													".($vendor_flag || $chrg_flag ? $vendor_chrg_tbl_parent : NULL)."
												</table>
											</div>
										</td>
									</tr>";
									unset($flag, $vendor_flag, $chrg_flag);
								}
								unset($this->lines->line_info);
								$this->lines->fetch_line_items(0, $this->lines->total, "vendors.vendor_name , vendor_products.product_name", "ASC");
								$rand_id = array();
								$l = 0;

								for ($i = 0; $i < $this->lines->total; $i++) {
									if (!$this->lines->line_info[$i]['status']) {
										unset($err_item);
										$item_rand = rand(500, 500000);
										while (in_array($item_rand, $rand_id))
											$item_rand = rand(500, 500000);

										array_push($rand_id, $item_rand);

										if (!$this->lines->line_info[$i]['ship_to_hash']) {
											$error_flag++;
											if (!$this->current_proposal['ship_to'] && !$ship_to_hash && !$ship_to) {
												for ($j = 0; $j < $this->lines->total; $j++) {
													if ($this->lines->line_info[$j]['ship_to_hash']) {
														$ship_to = $this->lines->line_info[$j]['ship_to_hash'];
														list($class, $id, $hash) = explode("|", $ship_to);
														$obj = new $class($this->current_hash);
														if ($hash) {
															$obj->fetch_location_record($hash);
															$ship_to = $obj->current_location['location_name'];
															$ship_to_hash = $this->lines->line_info[$j]['ship_to_hash'];
														} else {
															$obj->fetch_master_record($id);
															$ship_to = $obj->{"current_".strrev(substr(strrev($class), 1))}[strrev(substr(strrev($class), 1))."_name"];;
															$ship_to_hash = $this->lines->line_info[$j]['ship_to_hash'];
														}

														unset($obj);
														break;
													}
												}
											}

											$err_item[] = "
											<tr>
												<td style=\"background-color:#efefef;text-align:right;vertical-align:middle;width:45%;\" id=\"err1_".$this->lines->line_info[$i]['item_hash']."\">Ship To Location:</td>
												<td style=\"background-color:#ffffff;\">
													".$this->form->text_box("name=item_ship_to-".$this->lines->line_info[$i]['item_hash'],
																			"value=".$ship_to,
																			"autocomplete=off",
																			"size=30",
																			"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'item_ship_to-".$this->lines->line_info[$i]['item_hash']."', 'item_ship_to_hash-".$this->lines->line_info[$i]['item_hash']."', 1);}",
                                                                            "onKeyUp=if(ka==false && this.value){key_call('proposals', 'item_ship_to-".$this->lines->line_info[$i]['item_hash']."', 'item_ship_to_hash-".$this->lines->line_info[$i]['item_hash']."', 1);}",
                                											"onBlur=key_clear();",
																			"onKeyDown=clear_values('item_ship_to_hash-".$this->lines->line_info[$i]['item_hash']."');").
													$this->form->hidden(array("item_ship_to_hash-".$this->lines->line_info[$i]['item_hash'] => $ship_to_hash))."
												</td>
											</tr>";
										}
										if (!$this->lines->line_info[$i]['vendor_hash']) {
											$err_item[] = "
											<tr>
												<td style=\"background-color:#efefef;text-align:right;vertical-align:middle;width:45%;\" id=\"err1_finalvendor_".$this->lines->line_info[$i]['item_hash']."\">Vendor:</td>
												<td style=\"background-color:#ffffff;\">
													".$this->form->text_box("name=final_vendor-".$this->lines->line_info[$i]['item_hash'],
																			"value=",
																			"autocomplete=off",
																			"size=30",
																			"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'final_vendor-".$this->lines->line_info[$i]['item_hash']."', 'final_vendor_hash-".$this->lines->line_info[$i]['item_hash']."', 1);}",
                                                                            "onKeyUp=if(ka==false && this.value){key_call('proposals', 'final_vendor-".$this->lines->line_info[$i]['item_hash']."', 'final_vendor_hash-".$this->lines->line_info[$i]['item_hash']."', 1);}",
																			"onBlur=key_clear();",
																			"onKeyDown=clear_values('final_vendor_hash-".$this->lines->line_info[$i]['item_hash']."');").
													$this->form->hidden(array("final_vendor_hash-".$this->lines->line_info[$i]['item_hash'] => $ship_to_hash)).
													$this->form->hidden(array("die_flag[final_vendor-".$this->lines->line_info[$i]['item_hash']."]" => 1,
																			 "die_flag_label[final_vendor-".$this->lines->line_info[$i]['item_hash']."]" => "err1_finalvendor_".$this->lines->line_info[$i]['item_hash'],
																			 "die_flag[final_vendor_hash-".$this->lines->line_info[$i]['item_hash']."]" => 1))."
												</td>
											</tr>";

											$error_flag++;
										}
										if ($this->lines->line_info[$i]['vendor_hash'] && !$this->lines->line_info[$i]['product_hash']) {
											unset($vendor_product_name, $vendor_product_hash);
											$r = $this->db->query("SELECT vendor_products.product_name , vendor_products.product_hash
																   FROM `vendor_products`
																   WHERE vendor_products.vendor_hash = '".$this->lines->line_info[$i]['vendor_hash']."' OR vendor_products.vendor_hash = NULL
																   ORDER BY vendor_products.product_name ASC");
											while ($row = $this->db->fetch_assoc($r)) {
												$vendor_product_name[] = $row['product_name'];
												$vendor_product_hash[] = $row['product_hash'];
											}
											$err_item[] = "
											<tr>
												<td style=\"background-color:#efefef;text-align:right;vertical-align:middle;width:45%;\" id=\"err1_finalproduct_".$this->lines->line_info[$i]['item_hash']."\">Missing Product:</td>
												<td style=\"background-color:#ffffff;\">".
													$this->form->select("vendor_product_".$this->lines->line_info[$i]['item_hash'],
																		$vendor_product_name,
																		NULL,
																		$vendor_product_hash,
																		"blank=1").
													$this->form->hidden(array("die_flag[vendor_product_".$this->lines->line_info[$i]['item_hash']."]" => 1,
																			  "die_flag_label[vendor_product_".$this->lines->line_info[$i]['item_hash']."]" => "err1_finalproduct_".$this->lines->line_info[$i]['item_hash']))."
												</td>
											</tr>";
											$die_flag = true;
											$error_flag++;
										}
										if (!$this->lines->line_info[$i]['import_data'] && !$this->lines->line_info[$i]['import_complete']) {
											$err_item[] = "
											<tr>
												<td style=\"background-color:#ffffff;vertical-align:middle;padding-left:15px;\" colspan=\"2\">
													<img src=\"images/alert.gif\" />
													&nbsp;&nbsp;
													<i>Incomplete Specification!</i>
												</td>
											</tr>";
											$error_flag++;
										}
									}
									if (is_array($err_item)) {
										$l++;

										$error_check_tbl .= ($l > 1 ? "
										<tr>
											<td colspan=\"2\" style=\"background-color:#cccccc;\"></td>
										</tr>" : NULL)."
										<tr>
											<td style=\"padding:8px;vertical-align:top;width:45%\">
												<div style=\"font-weight:bold;padding-bottom:5px;\">
													".$this->lines->line_info[$i]['vendor_name'].
													($this->lines->line_info[$i]['product_name'] ? "
														: ".$this->lines->line_info[$i]['product_name'] : NULL)."
												</div>".($this->lines->line_info[$i]['item_no'] ? "
												<div style=\"padding-bottom:5px;\">
													Item: ".$this->lines->line_info[$i]['item_no']."
												</div>" : NULL)."
												<div style=\"padding-bottom:10px;\">
													Descr: ".(strlen($this->lines->line_info[$i]['item_descr']) > 45 ?
														substr($this->lines->line_info[$i]['item_descr'], 0, 45)."..." : $this->lines->line_info[$i]['item_descr'])."
												</div>
											</td>
											<td style=\"vertical-align:top;width:55%\">
												<div style=\"font-weight:bold;\">
													<img src=\"images/alert.gif\" />
													&nbsp;
													Error Found:
												</div>
												<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">
													".implode("\n", $err_item).
													$this->form->hidden(array("error_item[".$item_rand."]" => $this->lines->line_info[$i]['item_hash']))."
												</table>
											</td>
										</tr>";
									}
								}
								if (!$this->current_proposal['install_addr_hash']) {
									$error_flag++;
									$error_check_tbl .= $this->form->hidden(array('install_proposal_hash' => $ship_to_hash))."
									<tr>
										<td style=\"background-color:#cccccc;\" colspan=\"2\"></td>
									</tr>
									<tr>
										<td style=\"padding:8px;vertical-align:top;width:45%;font-weight:bold;\">
											Your proposal has no installation location!
										</td>
										<td style=\"vertical-align:top;width:55%\">
											<div style=\"font-weight:bold;padding-bottom:9px;\">
												<img src=\"images/alert.gif\" />
												&nbsp;
												Error Found:
											</div>
											<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">
												<tr>
													<td style=\"background-color:#efefef;text-align:right;vertical-align:middle;width:45%;\" id=\"err1_proposal_install\">Installation Location:</td>
													<td style=\"background-color:#ffffff;\">
														".$this->form->text_box("name=install_proposal",
																				"value=".$ship_to,
																				"autocomplete=off",
																				"size=30",
																				"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'install_proposal', 'install_proposal_hash', 1);}",
                                                                                "onKeyUp=if(ka==false && this.value){key_call('proposals', 'install_proposal', 'install_proposal_hash', 1);}",
                                            									"onBlur=key_clear();",
																				"onKeyDown=clear_values('install_proposal_hash');")."
													</td>
												</tr>
											</table>
										</td>
									</tr>";
								}
								if ($error_flag) {
									$final_flag = true;
									$tbl .= "
									<tr style=\"background-color:#cccccc;\">
										<td class=\"thead\" >Finalization : Error Checking (".$error_flag.")</td>
									</tr>
									<tr >
										<td>
											<div>
												<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
													".$error_check_tbl."
												</table>
											</div>
										</td>
									</tr>";
									unset($error_check_tbl, $error_flag);
								}

								if (defined('ACCOUNT_BALANCE_RULE') && (ACCOUNT_BALANCE_RULE == 'PROPOSAL' || ACCOUNT_BALANCE_RULE == 'BOTH') && defined('ACCOUNT_BALANCE_AMT')) {
									$customer_obj = new customers($this->current_hash);
									$customer_obj->fetch_master_record($this->current_proposal['customer_hash']);

									$account_balance_amt = ACCOUNT_BALANCE_AMT * 1;
									$total_amt = $customer_obj->current_customer['balance_sched1'] + $customer_obj->current_customer['balance_sched2'] + $customer_obj->current_customer['balance_sched3'];
									if (defined('ACCOUNT_BALANCE_SCHED')) {
										$sched = substr(strrev(ACCOUNT_BALANCE_SCHED), 0, 1);
										switch($sched) {
											case 1:
											$current_amt = $customer_obj->current_customer['balance_sched1'];
											break;

											case 2:
											$current_amt = $customer_obj->current_customer['balance_sched2'];
											break;

											case 3:
											$current_amt = $customer_obj->current_customer['balance_sched3'];
											break;
										}
									} else
										$current_amt = $total_amt;

									if (($account_balance_amt < 1 && ($current_amt / $total_amt >= $account_balance_amt)) || ($account_balance_amt > 1 && $current_amt >= $account_balance_amt))
										$balance_error = true;
										$error_check_tbl = "Customer balance is greater than rules:
										<br />
										Total Balance: ".number_format($total_amt, 2)."
										<br />
										Rule Limit: ".$account_balance_amt."
										<br />
										Rule Applied To: ".ACCOUNT_BALANCE_SCHED."
										<br />
										We found that ".number_format($current_amt, 2)." is larger than the allowed ".number_format($account_balance_amt, 2);
								}

								if ($balance_error) {
									$final_flag = true;
									$tbl .= "
									<tr style=\"background-color:#cccccc;\">
										<td class=\"thead\" >Finalization : Customer Receivables Check (".$error_flag.")</td>
									</tr>
									<tr >
										<td>
											<div>
												<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
														".$error_check_tbl."
												</table>
											</div>
										</td>
									</tr>";
								}

								//GP Margin Flag
								//$margin = gp_margin($total_gp_cost, $total_gp_sell);
								//

								if ( defined('MARGIN_FLAG') && bccomp($total_gp_cost, 0, 2) == 1 ) {

									$minimum_gp = (float)bcmul(MARGIN_FLAG, 100, 4);

									$_gp = math::gp_margin( array(
    									'cost' =>  $total_gp_cost,
    									'sell' =>  $total_gp_sell
									) );
									if ( ! $tiered_disc_gp && bccomp( bcmul($_gp, 100, 4), $minimum_gp, 4) == -1 ) {

										if ( defined('MARGIN_FLAG_AUTH') ) {

											$halt = 1;
											$auth_link = "
											<!--[<small><a href=\"javascript:void(0);\" onClick=\"toggle_display('gp_flag_auth', 'block');\" class=\"link_standard\">request override</a></small>]-->
											<div id=\"gp_flag_auth\" class=\"function_menu\" >
												<div style=\"float:right;padding:5px\">
													<a href=\"javascript:void(0);\" onClick=\"toggle_display('gp_flag_auth', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
												</div>
												<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Message <small>(optional)</small>:</div>
												<div class=\"function_menu_item\" >
													".$this->form->text_area("name=auth_msg", "rows=3", "cols=25")."
												</div>
												<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">
													".$this->form->button("value=Go", "onClick=submit_form(this.form, 'override', 'exec_post', 'refresh_form', 'auth_action=_new', 'obj_hash=".$this->proposal_hash."', 'class=proposals', 'content=gp_margin_auth', 'auth_holder=gp_flag_auth_holder')")."
												</div>
											</div>";
										}
										$final_flag = true;
										$tbl .= "
										<tr style=\"background-color:#cccccc;\">
											<td class=\"thead\" >Finalization : GP Minimum Margin Alert</td>
										</tr>
										<tr >
											<td>
												<div>
													<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
														<tr>
															<td style=\"background-color:#efefef;border-right:1px solid #cccccc;text-align:right;vertical-align:middle;width:45%;\"><img src=\"images/alert.gif\">&nbsp;&nbsp;Your proposal is lower than the minimum allowed GP Margin!</td>
															<td style=\"background-color:#ffffff;padding-top:12px;\">
																Minimum Allowed GP Margin: ".(float)(MARGIN_FLAG * 100)."%
																<br />
																Your Blended GP Margin: <span style=\"color:#ff0000;font-weight:bold;\">" .
                        										( bccomp( bcmul( math::gp_margin( array(
	                            										'cost'    =>  $total_gp_cost,
	                            										'sell'    =>  $total_gp_sell
	                        										) ), 100, 4), -100, 4) == -1 ?
    	                        										"(100) "
    	                        										:
    	                        										(float)bcmul( math::gp_margin( array(
        	                        										'cost'   =>  $total_gp_cost,
        	                        										'sell'   =>  $total_gp_sell
    	                        										) ), 100, 4) . " "
    	                        								) . "%</span>" .
    	                        								( $auth_link ?
																	"&nbsp;<span id=\"gp_flag_auth_holder\">$auth_link</span>" : NULL
    	                        								) . "
															</td>
														</tr>
													</table>
												</div>
											</td>
										</tr>";
									}
								}

								//Direct Bills
								if ($this->current_proposal['order_type'] == 'D') {
									$this->lines->summarized_lines();
									$sum = array();
									for ($i = 0; $i < count($this->lines->line_info); $i++) {
										if ($this->lines->line_info[$i]['active'] && !$this->lines->line_info[$i]['status']) {
											$sum[$this->lines->line_info[$i]['vendor_hash']]['name'] = $this->lines->line_info[$i]['vendor_name'];
											$sum[$this->lines->line_info[$i]['vendor_hash']]['total_sell'] += $this->lines->line_info[$i]['ext_sell'];
											$sum[$this->lines->line_info[$i]['vendor_hash']]['total_cost'] += $this->lines->line_info[$i]['ext_cost'];
										}
										$direct_bill_vendor_select[$this->lines->line_info[$i]['vendor_hash']]['name'] = $this->lines->line_info[$i]['vendor_name'];
									}
									if (is_array($direct_bill_vendor_select)) {
										while (list($id, $info) = each($direct_bill_vendor_select)) {
											$direct_vendor_hash[] = $id;
											$direct_vendor_name[] = "Direct bill to ".$info['name'];
										}
										$direct_vendor_hash[] = "OPEN";
										$direct_vendor_name[] = "Treat as a normal (open) market business";
									}

									reset($sum);
									if (is_array($sum)) {
										while (list($id, $info) = each($sum)) {
											$direct_bill_tbl .= "
											<tr>
												<td style=\"padding:8px;vertical-align:top;width:45%\">
													<div style=\"font-weight:bold;padding-bottom:10px;\">
														".$info['name']."
													</div>
													<div style=\"padding-bottom:5px;\">
														Total Sell: ".math::format_num($info['total_sell'])."
													</div>
													<div style=\"padding-bottom:5px;\">
														Total Cost: ".math::format_num($info['total_cost'])."
													</div>
												</td>
												<td style=\"vertical-align:top;width:55%\">
													".$this->form->select("direct_bill[".$id."]",
											                              $direct_vendor_name,
											                              '',
											                              $direct_vendor_hash,
											                              'blank=1',
											                              "id2=direct_action")."
												</td>
											</tr>";
										}
									}
									if ($direct_bill_tbl) {
										$final_flag = true;
										$tbl .= "
										<tr style=\"background-color:#cccccc;\">
											<td class=\"thead\" >Finalization : Direct Bill Vendor Information</td>
										</tr>
										<tr >
											<td>
												Of the vendor(s) listed below, please indicate which vendor(s) should be treated as direct bill vendors. Multiple vendors may
												be combined into a single direct bill vendor if one vendor is handling the paper for multiple vendors.
												<div id=\"direct_action_holder\" style=\"padding-top:5px;\">
													<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">	".$direct_bill_tbl ."</table>
												</div>
											</td>
										</tr>";
										$this->content['jscript'][] = "
										direct_bill_ck = function() {
											var val = 'x';
											var act = \$('direct_action_holder').getElementsBySelector('[id2=\"direct_action\"]');
											act.each(function(item){
												alert(\$F('direct_bill['+item.value+']'))
												if (item.value != 'OPEN' && \$F('direct_bill['+item.value+']') != item.value)
													val = 'n';
											});

											return (val=='n'?0:1);
										}";
									}
								}

								//Tax
								if ($this->current_proposal['install_addr_hash'] && $this->current_proposal['install_addr_country'] == 'US' && $this->current_proposal['install_addr_state'] && !$this->current_proposal['gsa_customer'] && !$this->current_proposal['tax_exempt_customer']) {
									//Get the product list
									$result = $this->db->query("SELECT vendor_products.product_hash
																FROM `line_items`
																LEFT JOIN vendor_products on vendor_products.product_hash = line_items.product_hash
																WHERE line_items.proposal_hash = '".$this->proposal_hash."' AND line_items.invoice_hash = '' AND line_items.active = 1 AND line_items.punch = ".($punch ? 1 : 0)." AND vendor_products.product_taxable = 1
																GROUP BY vendor_products.product_hash");
									while ($row = $this->db->fetch_assoc($result))
										$product[] = $row['product_hash'];

									if ($product) {
										$sys = new system_config($this->current_hash);
										$sys->fetch_tax_tables($this->current_proposal['install_addr_country'], $this->current_proposal['install_addr_state'], $product);
										$a = 0;

										$tax_name[] = "Don't apply any tax rules";
										$tax_hash[] = '';
										if (is_array($sys->tax_tables)) {
											while (list($state, $info) = each($sys->tax_tables)) {
												$a++;
												$tax_name[] = $info['state']['state_name']." (".($info['state']['rate'] * 100)."%)";
												$tax_hash[] = $info['state']['tax_hash'];
												if ($info['local']) {
													for ($i = 0; $i < count($info['local']); $i++) {
														$a++;
													    $tax_name[] = "&nbsp;&nbsp;&nbsp;&nbsp;--&nbsp;".$info['local'][$i]['local']." (".($info['local'][$i]['rate'] * 100)."%)";
													    $tax_hash[] = $info['local'][$i]['tax_hash'];
													}
												}
											}
										}
									}
									if (!$a)
										unset($tax_hash, $tax_name);

									if ($tax_hash) {
										$tax_lock = $this->lines->tax_lock($this->current_proposal['install_addr_hash']);
                                        $selected_tax = $this->fetch_location_tax($this->current_proposal['install_addr_hash']);

										$tax_tbl = $this->form->hidden(array('install_hash' => $this->current_proposal['install_addr_hash'])).
										$this->form->select("tax_rules[]",
										                    $tax_name,
										                    $selected_tax,
										                    $tax_hash,
										                    "blank=1",
										                    "multiple",
										                    "size=".(count($tax_hash) > 3 ? "4" : count($tax_hash) + 1),
										                    "style=width:250px;",
										                    ($tax_lock ? "disabled title=Tax rule editing has been disabled for this customer/location. If the tax rules are incorrect, you may changed them by editing the customer/location." : NULL),
										                    "onClick=if(this.options[0].selected){for(var i=1;i<this.options.length;i++){this.options[i].selected=0}}");

                                        if ($tax_lock) {
                                            for ($i = 0; $i < count($selected_tax); $i++)
                                                $tax_tbl .= $this->form->hidden(array('tax_rules[]' =>  $selected_tax[$i]));
                                        }
									}
									if ($tax_tbl && $this->current_proposal['install_addr_country'] == 'US') {
										$final_flag = true;
										$tbl .= "
										<tr style=\"background-color:#cccccc;\">
											<td class=\"thead\" >Finalization : Assign Your Sales Tax Rules</td>
										</tr>
										<tr >
											<td>
												<div>
													<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
														<tr>
															<td style=\"background-color:#efefef;border-right:1px solid #cccccc;text-align:right;vertical-align:middle;width:45%;\">
																There are tax rules established within the state of ".$sys->tax_tables[$this->current_proposal['install_addr_state']]['state']['state_name'].".
																Please determine which tax rules should be applied to this proposal.
															</td>
															<td style=\"background-color:#ffffff;padding-top:12px;\">".$tax_tbl."</td>
														</tr>
													</table>
												</div>
											</td>
										</tr>";
									}
								}

								if (defined('MULTI_CURRENCY')) {
									$r = $this->db->query("SELECT t1.currency AS customer_currency , t2.rate AS current_rate
									                       FROM customers t1
									                       LEFT JOIN currency t2 ON t2.code = t1.currency
									                       WHERE t1.customer_hash = '{$this->current_proposal['customer_hash']}'");
	                                $customer_currency = $this->db->result($r, 0, 'customer_currency');

								    if ($customer_currency && $customer_currency != FUNCTIONAL_CURRENCY) {
								    	$final_flag = true;
								    	$exchange_rate = $this->db->result($r, 0, 'current_rate');

	                                    $this->content['jscript'][] = "
	                                    display_currency_rate = function() {
	                                        var default_rate = '{$exchange_rate}';
	                                        var posted_rate = \$F('exchange_rate');
	                                        var selected_curr = \$F('currency');
	                                        var functional_curr = '".FUNCTIONAL_CURRENCY."';

	                                        if (selected_curr == functional_curr) {
                                                \$('exchange_rate').disable();
	                                        } else {
                                                if (\$('exchange_rate').disabled) {
    	                                            \$('exchange_rate').enable();
    	                                        }
	                                        }
	                                    }";

								    	$currency_ops = array($customer_currency, FUNCTIONAL_CURRENCY);
								    	if (isset($this->current_proposal['currency']) && !in_array($this->current_proposal['currency'], $currency_ops))
                                            array_push($currency_ops, $this->current_proposal['currency']);

		                                $tbl .= "
		                                <tr style=\"background-color:#cccccc;\">
		                                    <td class=\"thead\" >Finalization : Currency Assignment</td>
		                                </tr>
		                                <tr >
		                                    <td>
		                                        <div>
		                                            <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
		                                                <tr>
		                                                    <td style=\"vertical-align:top;background-color:#efefef;border-right:1px solid #cccccc;text-align:right;width:45%;\">
                                                                <div style=\"margin-bottom:11px;margin-top:2px;\">
                                                                    Proposal Currency Assignment:
                                                                </div>
                                                                Exchange Rate:
		                                                    </td>
		                                                    <td style=\"background-color:#ffffff;\">
                                                                <div style=\"margin-bottom:5px;\">
                                                                    ".$this->form->select("currency",
		                                                                                  $currency_ops,
		                                                                                  (isset($this->current_proposal['currency']) ?
    		                                                                                  $this->current_proposal['currency'] : $customer_currency),
		                                                                                  $currency_ops,
		                                                                                  "blank=1",
		                                                                                  "onChange=display_currency_rate();")."
                                                                </div>
                                                                ".$this->form->text_box("name=exchange_rate",
										                                                "value=".(isset($this->current_proposal['currency']) ?
		                                                                                    rtrim(trim($this->current_proposal['exchange_rate'], '0'), '.') : rtrim(trim($exchange_rate, '0'), '.')),
										                                                "size=7",
										                                                "maxlength=16",
										                                                "style=text-align:right;",
										                                                "onFocus=this.select();")."&nbsp;%
                                                                <span style=\"margin-left:15px;font-style:italic;\">
                                                                    Today's Exchange Rate is ".rtrim(trim($exchange_rate, '0'), '.')."%
                                                                </span>
		                                                    </td>
		                                                </tr>
		                                            </table>
		                                        </div>
		                                    </td>
		                                </tr>";
								    }
								}

								if ($final_flag)
									$tbl .= "
									<tr>
										<td style=\"text-align:right;padding:15px 45px 15px 0;\">".
											$this->form->button("value=Finalize", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_final');", ($halt ? " title=One or more of the finalization conflicts requires an override before continuing." : NULL))."
										</td>
									</tr>";
								else {
									if ($this->current_proposal['install_addr_country'] == 'CA') {
										$this->db->query("DELETE FROM tax_collected
										                  WHERE tax_collected.proposal_hash = '".$this->proposal_hash."'");

                                        $result = $this->db->query("SELECT `item_hash`
                                                                    FROM `line_items`
                                                                    WHERE `proposal_hash` = '".$this->proposal_hash."' AND `invoice_hash` = '' AND `active` = 1 AND `punch` = ".($punch ? 1 : 0));
                                        while ($row = $this->db->fetch_assoc($result))
                                            $this->lines->item_tax($row['item_hash']);

									}

									if ($punch)
										$this->db->query("UPDATE `proposals`
														  SET `punch_final` = 1 , `punch_final_timestamp` = ".time()."
														  WHERE `proposal_hash` = '".$this->proposal_hash."'");
									else
										$this->db->query("UPDATE `proposals`
														  SET `final` = 1 , `final_timestamp` = ".time().($po_status == 0 ? " , `expiration_date` = '".date("Y-m-d", time()+2592000)."'" : NULL)."
														  WHERE `proposal_hash` = '".$this->proposal_hash."'");

									$tbl .= "
									<tr>
										<td>
											<div style=\"margin-left:25px;padding-top:20px;font-weight:bold;\">
												<img src=\"images/check.gif\" />
												&nbsp;
												Discounting Errors : <span style=\"font-weight:normal;\">None</span>
											</div>
											<div style=\"margin-left:25px;padding-top:20px;font-weight:bold;\">
												<img src=\"images/check.gif\" />
												&nbsp;
												Tiered Discounting : <span style=\"font-weight:normal;\">None Applicable</span>
											</div>
											<div style=\"margin-left:25px;padding-top:20px;font-weight:bold;\">
												<img src=\"images/check.gif\" />
												&nbsp;
												Vendor Charges &amp; Fees : <span style=\"font-weight:normal;\">None</span>
											</div>
											<div style=\"margin-left:25px;padding-top:20px;font-weight:bold;\">
												<img src=\"images/check.gif\" />
												&nbsp;
												Proposal Errors : <span style=\"font-weight:normal;\">None</span>
											</div>
											<div style=\"margin-left:25px;padding-top:20px;padding-bottom:20px;font-weight:bold;\">
												<img src=\"images/check.gif\" />
												&nbsp;
												Customer Account Balance : <span style=\"font-weight:normal;\">Good Standing</span>
											</div>
											<div style=\"padding-bottom:10px;\">
												Your proposal looks good! We found no errors and found no situations to apply finalization functions.
												<div style=\"padding-top:10px;padding-left:10px;\">
													<a href=\"javascript:void(0);\" onClick=\"".($punch ? "agent.call('pm_module', 'sf_loadcontent', 'cf_loadcontent', 'punch', 'proposal_hash=$this->proposal_hash');" : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', '".($sum_view ? "summarized" : "line_items")."', 'otf=1', 'proposal_hash=".$this->proposal_hash."');")."\" class=\"link_standard\">Click here to continue</a>.
												</div>
											</div>
										</td>
									</tr>";
								}
								$tbl .= "
								</table>
							</div>
						</div >
					</div>
				</td>
			</tr>
		</table>";

		if ($punch) {
			if ($this->ajax_vars['otf'])
				$this->content['html']['tcontent9_1'] = $tbl;
			else
				return $tbl;
		} else {
			if ($this->ajax_vars['otf'])
				$this->content['html']['tcontent4'] = $tbl;
			else
				return $tbl;
		}
	}

	function fetch_location_tax($location_hash) {
		$result = $this->db->query("SELECT `tax_hash`
									FROM `location_tax`
									WHERE `location_hash` = '$location_hash'");
		while ($row = $this->db->fetch_assoc($result))
			$tax[] = $row['tax_hash'];

		return $tax;
	}

	function print_proposal() {
		$this->popup_id = $this->content['popup_controls']['popup_id'] = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id']);
		$this->content['popup_controls']['popup_title'] = "Print Proposal";
		$proposal_hash = ($this->ajax_vars['proposal_hash'] ? $this->ajax_vars['proposal_hash'] : $_POST['proposal_hash']);

		if ($_POST['doit_print']) {
			$this->content['action'] = 'continue';

			$proposal_hash = $_POST['proposal_hash'];
			$punch = $_POST['punch'];
			$print_pref['gen_print_fields'] = $gen_print_fields = $_POST['gen_print_fields'];
			$print_pref['item_print_fields'] = $item_print_fields = $_POST['item_print_fields'];
			$print_pref['group_summary'] = $group_summary = $_POST['group_summary']; // Added for Trac #1011
			if ($_POST['currency'])
    			$print_pref['currency'] = $_POST['currency'];
			

			$footer_msg = $_POST['footer_msg'];
			$print_pref['print_logo'] = $print_logo = $_POST['print_logo'];
			$print_pref['use_logo'] = $use_logo = $_POST['use_logo'];
			$template_hash = $print_pref['default_template'] = $_POST['use_template'];
			$print_pref_hash = $print_pref['default_prefs'] = $_POST['use_prefs'];
			$proposal_date = $print_pref['proposal_date'] = $_POST['proposal_date'];

			if (!is_array($item_print_fields) && !$group_summary) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'][] = "No item print options selected! Please select at least one line item print field in order to print line items.";
				$this->content['form_return']['err']['err_item_print_fields'] = 1;
				$this->content['submit_btn'] = "print_prop_btn";
				return;
			}

			$template = new templates();

			//Punchlist print ops
			if ($punch) {
				$doc_print_customer_hash = $_POST['doc_print_customer_hash'];
				$doc_print_ship_to_hash = $_POST['doc_print_ship_to_hash'];
				$punch_print = $_POST['punch_print'];
				$print_pref['punch_print'] = $punch_print;
				$print_pref['punch_print']['doc_print_customer_hash'] = $doc_print_customer_hash;
				$print_pref['punch_print']['doc_print_ship_to_hash'] = $doc_print_ship_to_hash;
				$print_prefs_str = addslashes(serialize($print_pref));
				store_user_data("proposal_punch_print", $print_prefs_str);
			} else {
				$print_prefs_str = addslashes(serialize($print_pref));
				store_user_data("proposal_print", $print_prefs_str);
			}

    		$template->doit_save_prefs(($punch ? "proposal_punch_print" : "proposal_print"), $print_prefs_str);

			if ($template->error) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = $template->error_msg;
				$this->content['form_return']['err']['saved_prefs_name'] = 1;
				$this->content['submit_btn'] = "print_prop_btn";
				return;
			}

			$tbl .= "
			<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;text-align:left;\">
				<div id=\"loading".$this->popup_id."\" style=\"width:100%;padding-top:50px;padding-bottom:45px;text-align:center;\">
					<h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch".$this->popup_id."\">Loading Document...</h3>
					<div id=\"link_img_holder".$this->popup_id."\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
				</div>
				<input id=\"url\" type=\"hidden\" value=\"print.php?t=".base64_encode('proposal')."&h=".$proposal_hash."&use_logo=$use_logo&print_logo=$print_logo&footer_msg=".base64_encode($footer_msg)."&punch=$punch&m=$template_hash&p=$print_pref_hash\"/>
				<input id=\"popup\" type=\"hidden\" value=\"$this->popup_id\">
			</div>";
			$this->content['html']['main_table'.$this->popup_id] = $tbl;
			$this->content['jscript'][] = "window.open(document.getElementById('url').value,'_blank');";
			
			$this->content['jscript'][] = "popup_windows[document.getElementById('popup').value].hide();";
			
			
			
			
		} else {
			$logo = explode("|", fetch_sys_var('company_logo'));
			for ($i = 0; $i < count($logo); $i++) {
				$base_name = basename($logo[$i]);
				if (!ereg("_gray", $base_name)) {
					$logo_in[] = $logo[$i];
					$logo_out[] = $base_name;
				}
			}

			$punch = $this->ajax_vars['punch'];
			$template = new templates();

			$print_ops = array("line_no"		=>	"Line Numbers",
							   "vendor"			=>	"Vendor Name",
							   "product"		=>	"Product Name",
							   "item_no"		=>	"Item Number",
							   "item_descr"		=>	"Item Description",
							   "qty" 			=> 	"Item Quantity",
							   "list"			=>	"Item List Pricing",
							   "ext_list"		=>	"Extended List Pricing",
							   "sell"			=>	"Item Sell",
							   "ext_sell"		=>	"Extended Sell",
							   "item_tag"		=>	"Item Tagging",
							   "item_finish"	=>	"Item Finishes & Options",
							   "zero_sell"		=>	"Zero Sell Items",
							   "discounting"	=>	"Buy Discounting",
							   "gp_margin"		=>	"GP Margin",
							   "list_discount"	=>	"Customer Discounting",
							   "cr"				=>	"Item Special");
				$print_ops['cost'] = "Item Cost";
				$print_ops['ext_cost'] = "Extended Cost";

            if ($punch && $print_pref = fetch_user_data("proposal_punch_print"))
                $print_pref = __unserialize(stripslashes($print_pref));
            elseif (!$punch && $print_pref = fetch_user_data("proposal_print"))
                $print_pref = __unserialize(stripslashes($print_pref));

            // Initially show the customer print preferences if the user has no defaults, or last time they chose custom
            if (($print_pref['default_prefs'] == "custom" && $this->p->ck("proposals", 'P')) || !$print_pref['default_prefs'] || $template->show_custom_prefs($punch ? "proposal_punch_print" : "proposal_print"))
            	$show_custom_prefs = true;

            $selected =& $print_pref['item_print_fields'];

            $item_print = "
            <select name=\"item_print_fields[]\" class=\"txtSearch\" style=\"width:300px;height:100px\" multiple>";
            while (list($in, $out) = each($print_ops))
                $item_print .= "
                <option value=\"".$in."\" ".((is_array($selected) && (array_key_exists($in, $selected) || in_array($in, $selected))) || (!is_array($selected) && ($selected == $in)) ? "selected" : NULL).">".$out."</option>";

            $item_print .= "
            <optgroup label=\"Print lines that are:\">
                <option value=\"print_not_booked\" ".((is_array($selected) && (array_key_exists('print_not_booked', $selected) || in_array('print_not_booked', $selected))) || (!is_array($selected) && $selected == 'print_not_booked') ? "selected" : NULL).">Not Booked</option>
                <option value=\"print_booked\" ".((is_array($selected) && (array_key_exists('print_booked', $selected) || in_array('print_booked', $selected))) || (!is_array($selected) && $selected == 'print_booked') ? "selected" : NULL).">Booked But Not Invoiced</option>
                <option value=\"print_invoiced\" ".((is_array($selected) && (array_key_exists('print_invoiced', $selected) || in_array('print_invoiced', $selected))) || (!is_array($selected) && $selected == 'print_invoiced') ? "selected" : NULL).">Invoiced</option>
            </optgroup>";
            $item_print .= "
            </select>";

			$gen_print_ops = array("logo_page_only"		=>	"Print Logo on First Page Only",
								   "proposal_descr"		=>	"Proposal Description",
								   "item_totals"		=>	"Proposal Totals",
								   "group_totals"		=>	"Group Totals",
								   "grp_break"			=>	"Page Break After Groups",
								   "grp_sum"			=>	"Group Summary",
								   "tax"				=>	"Tax Amount Due",
								   "deposit" 			=> 	"Deposit Requirements",
								   "propose_to"			=>	"Propose To",
								   "customer_contact"	=>	"Customer Contact",
								   "ship_to"			=>	"Shipping Location",
								   "install_addr"		=>	"Installation Location",
			                       "expiry_date"        =>  "Proposal Valid Thru Date",
								   "panel_detail"		=>	"Panel Attribute Details",
			                       "company_footer"     =>  "Company Contact Details in Footer",
								   "sales_rep_phone"	=>	"Sales Rep Contact Phone",
								   "sales_rep_fax"		=>	"Sales Rep Contact Fax",
								   "sales_rep_email"	=>	"Sales Rep Contact Email",
								   "hide_po_instr"		=>	"Hide PO Instructions",
								   "customer_po"		=>	"Customer PO",
									"sub_total"			=>	"Display Sub Totals");

			if ($punch) {
				$r = $this->db->query("SELECT t1.customer_hash , t2.customer_name , t2.currency , t3.ship_to_hash
									   FROM proposals t1
									   LEFT JOIN proposal_install t3 ON t3.proposal_hash = t1.proposal_hash
									   LEFT JOIN customers t2 ON t2.customer_hash = t1.customer_hash
									   WHERE t1.proposal_hash = '$proposal_hash' AND t1.deleted = 0");
                if ($row = $this->db->fetch_assoc($r)) {
	                $customer_hash = $this->db->result($r, 0, 'customer_hash');
	                $doc_print_customer = $this->db->result($r, 0, 'customer_name');
	                $doc_print_currency = $this->db->result($r, 0, 'currency');
	                if ($ship_to_hash = $this->db->result($r, 0, 'ship_to_hash')) {
                        list($class, $record_hash, $location_hash) = explode("|", $ship_to_hash);
                        $column = ($class == 'customers' ? 't1.customer_name' : 't1.vendor_name');
                        $key = ($class == 'customers' ? 't1.customer_hash' : 't1.vendor_hash');
                        if ($location_hash)
                        	$r_2 = $this->db->query("SELECT t1.location_name AS ship_to_name
                                                     FROM locations t1
                                                     WHERE t1.location_hash = '".$location_hash."' AND entry_type = '".substr($class, 0, 1)."'");
                        else
                            $r_2 = $this->db->query("SELECT ".$column." AS ship_to_name
                                                     FROM ".$class." t1
                                                     WHERE ".$key." = '".$record_hash."'");

                        $doc_print_ship_to = $this->db->result($r_2, 0, 'ship_to_name');
	                }
                }
			} elseif (defined('MULTI_CURRENCY')) {
                $r = $this->db->query("SELECT t1.currency AS customer_currency , t2.currency AS proposal_currency
                                       FROM customers t1
                                       LEFT JOIN proposals t2 ON t2.customer_hash = t1.customer_hash
                                       WHERE t2.proposal_hash = '$proposal_hash'");
                $proposal_currency = $this->db->result($r, 0, 'proposal_currency');
                $currency_ops = array($proposal_currency, FUNCTIONAL_CURRENCY);
                $currency_ops[] = 'USD';
                if ($this->db->result($r, 0, 'customer_currency') && !in_array($this->db->result($r, 0, 'customer_currency'), $currency_ops))
                    array_push($currency_ops, $this->db->result($r, 0, 'customer_currency'));
			}

			$tbl .= $this->form->form_tag().
			$this->form->hidden(array("proposal_hash" 	=> $proposal_hash,
									  "popup_id" 		=> $this->popup_id,
									  "punch"			=> $punch))."
			<div id=\"main_table".$this->popup_id."\" style=\"margin-top:0;padding-top:0\">
				<div class=\"panel\" style=\"margin-top:0;height:100%;\">
					<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
						<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
							<p id=\"feedback_message".$this->popup_id."\"></p>
					</div>
					<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
						<tr>
							<td style=\"background-color:#ffffff;padding-left:20px;padding-bottom:5px;\">
								<div style=\"float:right;margin-top:10px;margin-right:25px;\">
									".$this->form->button("value=Print Proposal", "id=print_prop_btn", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=print_proposal', 'doit_print=1');")."
								</div>
								<div style=\"margin-bottom:15px;margin-top:5px;font-weight:bold;\">Proposal Print Options</div>
								<div style=\"margin-left:15px;margin-bottom:15px;margin-top:15px;\">
									<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">".
										$template->template_select($punch ? "proposal_punch_print" : "proposal_print", ($punch ? 5 : 4), NULL, NULL, $this->popup_id, $proposal_hash)."
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
												".$this->form->select("gen_print_fields[]",
			                                                          array_values($gen_print_ops),
			                                                          $print_pref['gen_print_fields'],
			                                                          array_keys($gen_print_ops),
			                                                          "blank=1",
			                                                          "multiple",
			                                                          "style=width:300px;height:100px")."
											</td>
										</tr>
										<tr id=\"print_pref3\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\" id=\"err_item_print_fields\">
												Line Item Print Fields:
												<div style=\"padding-top:5px;font-style:italic;\"><small>hold cntrl key for multiple</small></div>
											</td>
											<td style=\"background-color:#ffffff;\">
												".$item_print."
											</td>
										</tr>
										<tr id=\"print_pref4\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Proposal Details:</td>
											<td style=\"background-color:#ffffff;\">
											".$this->form->select("group_summary",
    	                                                          array("Print Line Item Details", "Print Summarized by Group"),
    	                                                          $print_pref['group_summary'],
    	                                                          array(0, 1),
    	                                                          "blank=1")."
											</td>
										</tr>".(count($currency_ops) > 1 ? "
                                        <tr>
                                            <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;\">Display Pricing in Currency:</td>
                                            <td style=\"background-color:#ffffff;\">".
                                                $this->form->select("currency",
                                                                    $currency_ops,
                                                                    $proposal_currency,
                                                                    $currency_ops,
                                                                    "blank=1")."
                                            </td>
                                        </tr>" : NULL)."
                                        <tr>
                                            <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Proposal Date:</td>
                                            <td style=\"background-color:#ffffff;\" id=\"proposal_date_holder".$this->popup_id."\">";
                                            $this->content['jscript'][] = "setTimeout('DateInput(\'proposal_date\', true, \'YYYY-MM-DD\', \'".date("Y-m-d")."\', 1, \'proposal_date_holder".$this->popup_id."\')', 500);";
			                                $tbl .= "
                                            </td>
                                        </tr>
										<tr>
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Proposal Footer Message:</td>
											<td style=\"background-color:#ffffff;\">".$this->form->text_area("name=footer_msg", "value=".fetch_sys_var("proposal_footer_message"), "cols=45", "rows=4")."</td>
										</tr>".($punch ? "
										<tr id=\"print_pref5\" ".($show_custom_prefs ? "" : "style=\"display:none").";\">
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Document Title: </td>
											<td style=\"background-color:#ffffff;\">
												".$this->form->text_box("name=punch_print[doc_title]",
																		"value=".($print_pref['punch_print']['doc_title'] ? $print_pref['punch_print']['doc_title'] : "Punchlist Proposal"),
																		"size=30")."
											</td>
										</tr>
										<tr>
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Customer: </td>
											<td style=\"background-color:#ffffff;\">
													".$this->form->text_box("name=doc_print_customer",
																			"value=".$doc_print_customer,
																			"autocomplete=off",
																			"size=30",
																			"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'doc_print_customer', 'doc_print_customer_hash', 1);}",
                                                                            "onKeyUp=if(ka==false && this.value){key_call('proposals', 'doc_print_customer', 'doc_print_customer_hash', 1);}",
        			                                                        "onBlur=key_clear();",
																			"onKeyDown=clear_values('doc_print_customer_hash');").
													$this->form->hidden(array("doc_print_customer_hash" => $customer_hash))."
											</td>
										</tr>
										<tr>
											<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Shipping Location: </td>
											<td style=\"background-color:#ffffff;\">
													".$this->form->text_box("name=doc_print_ship_to",
																			"value=".$doc_print_ship_to,
																			"autocomplete=off",
																			"size=30",
																			"onFocus=position_element($('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'doc_print_ship_to', 'doc_print_ship_to_hash', 1);}",
                                                                            "onKeyUp=if(ka==false && this.value){key_call('proposals', 'doc_print_ship_to', 'doc_print_ship_to_hash', 1);}",
                        													"onBlur=key_clear();",
																			"onKeyDown=clear_values('doc_print_ship_to_hash');").
													$this->form->hidden(array("doc_print_ship_to_hash" => $ship_to_hash))."
											</td>
										</tr>
										" : NULL).
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
	
	function edit_item() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		$this->lock = ($this->ajax_vars['row_lock'] ? $this->ajax_vars['row_lock'] : NULL);

		if ( ! $this->ajax_vars['proposal_hash'] )
			return;

		$this->fetch_master_record($this->ajax_vars['proposal_hash']);

		if ($this->ajax_vars['copy_from']) {
			$copy_from = new line_items($this->proposal_hash);
			$valid = $copy_from->fetch_line_item_record($this->ajax_vars['copy_from']);
			if (!$valid)
				unset($copy_from);
			else
				$this->lock = 1;
		}

		$this->lines = new line_items($this->proposal_hash, $this->ajax_vars['punch']);
        if ($this->lines->punch) {
            $perm_class = 'pm_module';
            $perm_content = 'punchlist';
        } else {
            $perm_class = 'proposals';
            $perm_content = 'item_details';
        }

		if ($this->ajax_vars['item_hash']) {
			if ($valid = $this->lines->fetch_line_item_record($this->ajax_vars['item_hash'])) {
				if (count($this->lines->proposal_groups['group_descr']) && $this->p->ck($perm_class, 'E', $perm_content) && $this->lock) {
					$item_menu .= "
					<a href=\"javascript:void(0);\" onClick=\"position_element($('groupListUp'), 65, 80);toggle_display('groupListUp', 'block');\" class=\"link_standard\"><img src=\"images/addto_group.gif\" title=\"Add this item to a group\" border=\"0\" /></a>
					<div id=\"groupListUp\" class=\"function_menu\">
						<div style=\"float:right;padding:3px\">
							<a href=\"javascript:void(0);\" onClick=\"toggle_display('groupListUp', 'none');\"><img src=\"images/close.gif\" border=\"0\"></a>
						</div>
						<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Add Selected Items To:</div>";
					for ($i = 0; $i < count($this->lines->proposal_groups['group_descr']); $i++)
						$item_menu .= "
						<div class=\"function_menu_item\">
							".$this->form->radio('name=group_hash', 'value='.$this->lines->proposal_groups['group_hash'][$i])."&nbsp;
							".$this->lines->proposal_groups['group_descr'][$i]."
						</div>";

					$item_menu .= "
						<div class=\"function_menu_item\" style=\"text-align:right;padding-right:5px;\">".$this->form->button("value=Go", "onclick=toggle_display('groupListUp', 'none');submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_group', 'method=tag', 'proposal_hash=".$this->proposal_hash."', 'line_item[]=".$this->lines->item_hash."');")."</div>
					</div>
					&nbsp;".($this->lines->current_line['group_hash'] ? "
					<a href=\"javascript:void(0);\"  onClick=\"submit_form($('item_vendor').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_group', 'method=tag', 'proposal_hash=".$this->proposal_hash."', 'line_item[]=".$this->lines->item_hash."');\" class=\"link_standard\"><img src=\"images/rm_fromgroup.gif\" title=\"Remove selected items from group\" border=\"0\" /></a>
					&nbsp;" : NULL);
				}
				if (!$valid || ($valid && !$this->lines->current_line['status'] && $this->lock)) {
					$discounting = new discounting($this->lines->current_line['vendor_hash'], 'v');
					$discounting->fetch_proposal_discounts($this->current_proposal['customer_hash']);

                    $disc_vals = $this->fill_discounts( array(
                        'item_vendor_hash'      =>  $this->lines->current_line['vendor_hash'],
                        'item_product_hash'     =>  $this->lines->current_line['product_hash'],
                        'proposal_hash'         =>  $this->current_proposal['proposal_hash'],
                        'item_no'               =>  $this->lines->current_line['item_no'],
                        'discount_hash'         =>  $this->lines->current_line['discount_hash'],
                        'item_hash'             =>  $this->lines->item_hash,
                        'proposal_final'        =>  ( $this->lines->punch ? $this->current_proposal['punch_final'] : $this->current_proposal['final'] )
                    ) );
				}
				if ($this->lines->current_line['work_order_hash'])
					$work_order_hash = $this->lines->current_line['work_order_hash'];
			} else
				unset($this->ajax_vars['item_hash']);

		} elseif ($this->ajax_vars['work_order_import']) {
			$work_order = true;
			$pm = new pm_module($this->current_hash);
			if ($pm_valid = $pm->fetch_work_order($this->ajax_vars['work_order_import'])) {
				$work_order_hash = $pm->order_hash;
				(object)$copy_from;
				$copy_from->current_line['item_vendor'] = $pm->current_order['vendor_name'];
				$copy_from->current_line['vendor_hash'] = $pm->current_order['vendor_hash'];
				$copy_from->current_line['item_ship_to'] = $pm->current_order['ship_to'];
				$copy_from->current_line['ship_to_hash'] = $pm->current_order['ship_to_hash'];
				$copy_from->current_line['item_no'] = $pm->current_order['order_no'];
				$copy_from->current_line['item_descr'] = $pm->current_order['order_descr'].($pm->current_order['notes'] ? "\n\n".$pm->current_order['notes'] : NULL);
				$copy_from->current_line['qty'] = "1.00";
				$copy_from->current_line['cost'] = ($pm->current_order['total_internal_cost'] > 0 ? $pm->current_order['total_internal_cost'] : $pm->current_order['total_true_cost']);
				if ($this->ajax_vars['punch'])
					$punch_import = 1;

	            $r = $this->db->query("SELECT vendors.vendor_name , line_items.vendor_hash , line_items.product_hash
	                                   FROM `line_items`
	                                   LEFT JOIN vendors ON vendors.vendor_hash = line_items.vendor_hash
	                                   WHERE line_items.proposal_hash = '".$this->proposal_hash."' AND !ISNULL(line_items.work_order_hash) AND line_items.work_order_hash != '' ".($punch_import ? "AND line_items.punch = 1" : NULL)."
	                                   LIMIT 1");
	            if ($last_wo_vendor = $this->db->fetch_assoc($r)) {
                    $copy_from->current_line['vendor_hash'] = $last_wo_vendor['vendor_hash'];
                    $copy_from->current_line['item_vendor'] = stripslashes($last_wo_vendor['vendor_name']);
                    $copy_from->current_line['product_hash'] = $last_wo_vendor['product_hash'];
	            }
			}
		}
		if (!defined('SPEC_ITEM_EDIT'))
			define('SPEC_ITEM_EDIT', 0);

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($valid ? "View & Edit".($this->lines->current_line['invoice_hash'] ?
                                                    		" an Invoiced" : ($this->lines->current_line['status'] == 1 ?
                                                        		" a Booked" : NULL))." Line Item" : "Create A New Line Item".($work_order_hash ? " From A Work Order" : NULL));

		if ($copy_from)
			$this->content['jscript'][] = "setTimeout('submit_form(\$(\'item_hash\').form, \'proposals\', \'exec_post\', \'refresh_form\', \'action=validate_line_item\')', 500);";

        if ($this->lines->current_line['invoice_hash'] && defined('PO_INVOICE_EDIT') && PO_INVOICE_EDIT == 1)
            $invoice_lock = 1;

        if ( ! $this->lines->current_line['status'] ) {

    		$this->content['popup_controls']['onload'] = "var item_validator = new lineItemValidator(['qty', 'list', 'discount1', 'discount2', 'discount3', 'discount4', 'discount5', 'cost', 'gp_margin', 'list_discount', 'sell'], ['keyup', 'keyup', 'keyup', 'keyup', 'keyup', 'keyup', 'keyup', 'change|keyup', 'blur|keyup', 'keyup', 'keyup'])";
            $this->content['popup_controls']['onclose'] = "lineItemValidator.prototype.stopListening(['qty', 'list', 'discount1', 'discount2', 'discount3', 'discount4', 'discount5', 'cost', 'gp_margin', 'list_discount', 'sell'], ['keyup', 'keyup', 'keyup', 'keyup', 'keyup', 'keyup', 'keyup', 'change|keyup', 'blur|keyup', 'keyup', 'keyup']);";
        }
        
        if( $this->lines->current_line['contract_code'] != "" ){
        	$tr = "<tr>
						<td style=\"vertical-align:top;text-align:right;\">Contract Code:</td>
						<td id=\"contract_code{$this->popup_id}\">" .
						$this->lines->current_line['contract_code']
						. "</td>
					</tr>";
        }else{
        	$tr = "";
        }
        
        
        if(defined('ACTIVATE_ITEM_LIBRARY') && ACTIVATE_ITEM_LIBRARY == 1){

			$tbl = $this->form->form_tag("line_item_edit").
			$this->form->hidden(array("item_hash"               =>  $this->lines->item_hash,
									  "punch"                   =>  ($punch_import || $this->lines->current_line['punch'] ? 1 : NULL),
									  "proposal_hash"           =>  $this->proposal_hash,
									  "popup_id"                =>  $this->popup_id,
									  'p'                       =>  $this->ajax_vars['p'],
			                          'proposal_placeholder'    =>  1,
									  'copy_from'               =>  ($copy_from ? $copy_from->item_hash : NULL),
									  'order'                   =>  $this->ajax_vars['order'],
									  'order_dir'               =>  $this->ajax_vars['order_dir'],
									  'work_order_import'       =>  $work_order_hash,
			                          'from_edit'               =>  $this->ajax_vars['from_edit'],
			                          'booked'                  =>  ( $this->lines->current_line['status'] ? 1 : NULL ),
			                          'MARGIN_FLAG'             =>  ( defined('MARGIN_FLAG') ? MARGIN_FLAG : 0 ) ) ) .
			( $valid && $this->lines->current_line['status'] > 0 ?
	        $this->form->hidden(array('edit_change' =>  '')) : NULL)."
			<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0;height:100%;\">
				<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
					<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
						<p id=\"feedback_message".$this->popup_id."\"></p>
				</div>
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">".($valid ? "
					<tr>
						<td style=\"background-color:#ffffff;padding:0;\">".($valid && $this->lines->current_line['status'] > 0 &&
							// Show edit booked/invoiced line item button only if user has edit po permission or edit customer invoice line items permission. For ticket #118. 
							($this->lines->current_line['invoice_hash'] ? $this->p->ck('customer_invoice', 'E', 'line_items') : $this->p->ck('purchase_order', 'E', NULL)) ? "
						    <div id=\"edit_booked_line_holder\" onClick=\"editLineItem('$invoice_lock');\" style=\"cursor:hand;float:right;margin-right:10px;width:58px;height:23px;background-color:#eaeaea;border-left:1px solid #8f8f8f;border-right:1px solid #8f8f8f;\" title=\"Edit this ".($this->lines->current_line['invoice_hash'] ? "invoiced" : " booked")." line item\">
	    					    <div style=\"margin-top:2px;margin-left:4px;\">
	                                <div style=\"float:right;font-weight:bold;margin-right:5px;margin-top:3px;\">Edit</div>
	    					        <img src=\"images/edit_pencil.gif\">
	    					    </div>
	    					</div>" : NULL)."
	                        <div style=\"padding-left:5px;\">".($this->lines->current_line ? ($this->p->ck($perm_class, 'D', $perm_content) && !$this->lines->current_line['status'] && $this->lock ? "
							<a href=\"javascript:void(0);\" onClick=\"if (confirm('Are you sure you want to delete this line item? This action CAN NOT be undone!')){submit_form(\$('item_vendor').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'delete=1');}\" class=\"link_standard\"><img src=\"images/rm_lineitem.gif\" title=\"Delete selected line items\" border=\"0\" /></a>
							&nbsp;" : NULL).($this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status'] && $this->lock ? "
							<a href=\"javascript:void(0);\" onClick=\"submit_form($('proposal_placeholder').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'method=tag', 'method_action=inactive', 'proposal_hash=".$this->proposal_hash."', 'line_item[]=".$this->lines->item_hash."');\" class=\"link_standard\"><img src=\"images/line_inactive.gif\" title=\"Toggle the selected items between active & inactive.\" border=\"0\" /></a>
							&nbsp;" : NULL) : NULL).($this->lines->current_line['import_data'] ? "
							<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'view_details', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->item_hash."', 'popup_id=item_details');\" class=\"link_standard\"><img src=\"images/item_details.gif\" title=\"View item details from original import.\" border=\"0\" style=\"margin-top:2px;\" /></a>
							&nbsp;" : "&nbsp;").($this->lines->current_line && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lock ?
							"<img src=\"images/lock.gif\" title=\"Read-Only\" />&nbsp;<strong>This proposal is being edited by another user</strong>" : $item_menu)."
							</div>
						</td>
					</tr>" : NULL)."
					<tr>
						<td style=\"background-color:#d6d6d6;padding-left:20px;\">
							<table style=\"width:100%\" >
								<tr>
									<td style=\"vertical-align:top;padding-bottom:5px;\">
										<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1".$this->popup_id."\">Vendor: </div>
										".$this->form->text_box("name=item_vendor",
																"value=".($copy_from ? stripslashes($copy_from->current_line['item_vendor']) : stripslashes($this->lines->current_line['item_vendor'])),
																"autocomplete=off",
																"size=30",
																"TABINDEX=1",
	                                                            ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
																($valid && (!$this->p->ck($perm_class, 'E', $perm_content) || $this->lines->current_line['status']) ? "readonly" : NULL),
																(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'item_vendor', 'item_vendor_hash', 1);}" : NULL),
	                                                            (!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'item_vendor', 'item_vendor_hash', 1);}" : NULL),
																(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onBlur=key_clear();if( \$('item_vendor_hash') && !\$F('item_vendor_hash') ){\$('item_product_hash').value='';\$('item_product_hash').disabled='true';}" : NULL),
																(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onKeyDown=clear_values('item_vendor_hash');" : NULL)).
										$this->form->hidden(array("item_vendor_hash" => ($copy_from ? $copy_from->current_line['vendor_hash'] : $this->lines->current_line['vendor_hash'])))."
									</td>
									<td style=\"vertical-align:top;padding-bottom:5px;\">
										<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2".$this->popup_id."\">Item Number: </div>
										".$this->form->text_box("name=item_no",
															"value=".($copy_from ? stripslashes($copy_from->current_line['item_no']) : stripslashes($this->lines->current_line['item_no'])),
															"autocomplete=off",
															"size=30",
															"TABINDEX=4",
                                                            ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'item_no', '', 1);}" : NULL),
                                                            (!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'item_no', '', 1);}" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onKeyDown=clear_values('');" : NULL))."
									</td>
									<td rowspan=\"4\" style=\"vertical-align:top;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err3".$this->popup_id."\">Item Description: </div>
									".$this->form->text_area("name=item_descr",
															 "rows=11",
															 "TABINDEX=8",
															 "cols=35",
															 ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
															 ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															 "value=".($copy_from ? stripslashes($copy_from->current_line['item_descr']) : stripslashes($this->lines->current_line['item_descr'])),
															 (($valid && !$this->p->ck($perm_class, 'E', $perm_content)) || ($this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status']) ? "readonly" : NULL),
															 ($this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status'] ? "title=This item was created from an import, editing has been disabled." : NULL)
															 )."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err4".$this->popup_id."\">Ship To: </div>
									".$this->form->text_box("name=item_ship_to",
															"value=".($copy_from && $copy_from->current_line['item_ship_to'] ? stripslashes($copy_from->current_line['item_ship_to']) : ($this->lines->item_hash ? stripslashes($this->lines->current_line['item_ship_to']) : stripslashes($this->current_proposal['ship_to']))),
															"autocomplete=off",
															"size=30",
															"TABINDEX=2",
															($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
															($valid && (!$this->p->ck($perm_class, 'E', $perm_content) || $this->lines->current_line['status']) ? "readonly" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'item_ship_to', 'item_ship_to_hash', 1);}" : NULL),
                                                            (!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'item_ship_to', 'item_ship_to_hash', 1);}" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onBlur=key_clear();" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onKeyDown=clear_values('item_ship_to_hash');" : NULL)).
									$this->form->hidden(array("item_ship_to_hash" => ($copy_from && $copy_from->current_line['item_ship_to'] ? $copy_from->current_line['ship_to_hash'] : ($this->lines->current_line['ship_to_hash'] ? $this->lines->current_line['ship_to_hash'] : $this->current_proposal['ship_to_hash']))))."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err5".$this->popup_id."\">Item Tagging: </div>
									".$this->form->text_box("name=item_tag1",
															"value=".($copy_from ? $copy_from->current_line['item_tag1'] : $this->lines->current_line['item_tag1']),
															"TABINDEX=5",
															"maxlength=128",
									                        ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
									                        ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err6".$this->popup_id."\">Product/Service: </div>
									<div id=\"vendor_product_holder\" ".($this->lines->current_line['invoice_hash'] ? "style=\"margin-left:10px;font-weight:bold;\"" : NULL).">".($this->lines->current_line['invoice_hash'] ?
										$this->lines->current_line['item_product'].$this->form->hidden(array('item_product_hash' => $this->lines->current_line['product_hash'])) : ($copy_from->current_line['vendor_hash'] || $this->lines->current_line['vendor_hash'] ?
											$this->innerHTML_customer($copy_from->current_line['vendor_hash'] ? $copy_from->current_line['vendor_hash'] : $this->lines->current_line['vendor_hash'], 'item_vendor', 'vendor_product_holder', ($copy_from ? $copy_from->current_line['product_hash'] : $this->lines->current_line['product_hash']), NULL, NULL, ($this->lines->current_line['status'] > 0 ? 1 : NULL), ($this->lines->current_line['status'] > 0 ? 1 : NULL)) : $this->form->select("item_product_hash", array("Select Vendor First..."), NULL, array(""), "disabled", "style=width:195px;")))."
									</div>
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err13".$this->popup_id."\">Item Tagging (2): </div>
									".$this->form->text_box("name=item_tag2",
															"value=".($copy_from ? $copy_from->current_line['item_tag2'] : $this->lines->current_line['item_tag2']),
															"TABINDEX=6",
															"maxlength=128",
										                    ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
										                    ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;padding-top:5px;\" id=\"discount_message".$this->popup_id."\">".($this->lines->item_hash && !$this->lines->current_line['status'] ?
									$disc_vals['discount_message'.$this->popup_id] : NULL)."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err15".$this->popup_id."\">Item Tagging (3): </div>
									".$this->form->text_box("name=item_tag3",
															"value=".($copy_from ? $copy_from->current_line['item_tag3'] : $this->lines->current_line['item_tag3']),
															"maxlength=128",
															"TABINDEX=7",
									                        ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
									                        ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#ffffff;padding-left:20px;\">".($this->lines->current_line['work_order_hash'] ? "
						<div style=\"font-style:italic;\">Work Order : <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'show_popup_window', 'edit_work_order', 'popup_id=work_order_item', 'proposal_hash=".$this->proposal_hash."', 'order_hash=".$this->lines->current_line['work_order_hash']."');\" title=\"View this work order.\">".$this->lines->current_line['work_order_no']."</a></div>" : NULL)."
						<table style=\"width:100%\" cellpadding=\"3\">
							<tr>
								<td style=\"vertical-align:bottom;width:150px;text-align:right;\" id=\"err7".$this->popup_id."\">Proposal Line No: </td>
								<td>Line ". $this->lines->current_line['line_no'] ."</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err7".$this->popup_id."\">Quantity:</td>
								<td >" .
                                ( $this->lines->current_line['invoice_hash'] ?
                                    $this->form->hidden( array(
                                        'qty' =>  $this->lines->current_line['qty']
                                    ) ) . rtrim( trim( $this->lines->current_line['qty'], '0'), '.')
                                    :
									$this->form->text_box(
                                        "name=qty",
                                        "value=" .
    									( $copy_from ?
                                            trim_decimal( $copy_from->current_line['qty'] ) :
                                            ( $this->lines->item_hash ?
                                                trim_decimal( $this->lines->current_line['qty'] ) : NULL
                                            )
                                        ),
                                        "size=5",
                                        ( ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ) || $work_order_hash || ( $this->ajax_vars['from_edit'] == 'invoice' && $this->ajax_vars['invoice_hash'] ) ?
                                            "readonly" : NULL
                                        ),
                                        "maxlength=13",
                                        "tabindex=10",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "style=text-align:right;",
                                        ( $work_order_hash ?
                                            "title=This line is a work order. Update the work order resources in order to change cost." : NULL
                                        ),
                                        "onFocus=this.select();",
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        )
                                    )
                                ) . "
								</td>
								<td rowspan=\"7\" style=\"width:250px;vertical-align:top;background-color:#efefef;border:1px solid #9f9f9f;\">" .
									$this->form->hidden( array(
    									"discount_hash"        =>
                                        ( $copy_from->current_line['discount_hash'] ?
                                            $copy_from->current_line['discount_hash'] : $this->lines->current_line['discount_hash']
                                        ),
                                        "discount_item_hash"   =>
                                        ( $copy_from->current_line['discount_item_hash'] ?
                                            $copy_from->current_line['discount_item_hash'] : $this->lines->current_line['discount_item_hash']
                                        )
                                    ) ) . "
									<table>".$tr."
										<tr>
											<td style=\"vertical-align:top;text-align:right;\">Discount ID:</td>
											<td id=\"discount_id{$this->popup_id}\">" .
											( $copy_from->current_line['discount_id'] || $this->lines->current_line['discount_id'] ?
												( $copy_from->current_line['discount_id'] ?
    												$copy_from->current_line['discount_id'] : $this->lines->current_line['discount_id']
        										)
        										:
    											( $copy_from->item_hash || $this->lines->item_hash ?
                                                    "No Discount Used" : NULL
    											)
    										) . "
											</td>
										</tr>
										<tr>
											<td style=\"vertical-align:top;text-align:right;\" id=\"disc_menu_holder_x\">
												Description:
												<div id=\"disc_menu{$this->popup_id}\" " . ( $this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "style=\"display:none;\"" : NULL ) . ">" .
												( $this->lines->item_hash ?
													$disc_vals['disc_menu']	: NULL
												) . "
												</div>
											</td>
											<td style=\"vertical-align:top;\" id=\"discount_descr{$this->popup_id}\">" .
											( $copy_from->current_line['discount_descr'] || $this->lines->current_line['discount_descr'] ?
												( strlen($this->lines->current_line['discount_descr']) > 15 || strlen($copy_from->current_line['discount_descr']) > 15 ?
													wordwrap( $copy_from->current_line['discount_descr'] ? $copy_from->current_line['discount_descr'] : $this->lines->current_line['discount_descr'], 15, "<br />", true)
													:
													( $copy_from->current_line['discount_descr'] ?
    													$copy_from->current_line['discount_descr'] : $this->lines->current_line['discount_descr']
    												)
    											) : NULL
    										) . "
											</td>
										</tr>
										<tr>
											<td style=\"vertical-align:top;text-align:right;\">Expiration:</td>
											<td id=\"discount_expiration{$this->popup_id}\"></td>
										</tr>
										<tr>
											<td style=\"text-align:right;padding-top:20px;\">Extended Cost: </td>
											<td style=\"padding-left:5px;padding-top:20px;\" id=\"extended_cost{$this->popup_id}\">" .
											( $this->lines->current_line['ext_cost'] ? "
												\$".number_format($this->lines->current_line['ext_cost'], 2) :
    											( $copy_from->current_line['ext_cost'] ?
        											$copy_from->current_line['ext_cost'] : NULL
        										)
        									) . "
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;\">Extended Sell: </td>
											<td style=\"padding-left:5px;\" id=\"extended_sell{$this->popup_id}\">" .
											( $this->lines->current_line['ext_sell'] ? "
												\$".number_format($this->lines->current_line['ext_sell'], 2) :
    											( $copy_from->current_line['ext_sell'] ?
        											$copy_from->current_line['ext_sell'] : NULL
        										)
        									) . "
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold;padding-top:10px;\">Profit Dollars: </td>
											<td style=\"font-weight:bold;padding-top:10px;padding-left:5px;\" id=\"profit_dollars{$this->popup_id}\">" .
											( bccomp( bcsub($this->lines->current_line['ext_sell'], $this->lines->current_line['ext_cost'], 2), 0, 2) ?
												( bccomp( bcsub($this->lines->current_line['ext_sell'], $this->lines->current_line['ext_cost'], 2), 0, 2) == -1 ? "
													<span style=\"color:red;\">(\$" . number_format( bcmul( bcsub($this->lines->current_line['ext_sell'], $this->lines->current_line['ext_cost'], 2), -1, 2), 2) . ")</span>" : "\$" . number_format(bcsub($this->lines->current_line['ext_sell'], $this->lines->current_line['ext_cost'], 2), 2)
												) : NULL
											) . "
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold;\" id=\"extended_cost{$this->popup_id}\">GP Margin: </td>
											<td style=\"font-weight:bold;padding-left:5px;\" id=\"gp_margin{$this->popup_id}\">" .
											( $this->lines->current_line['gp_margin'] ?
												( $this->lines->current_line['gp_margin'] && defined('MARGIN_FLAG') && bccomp( bcmul($this->lines->current_line['gp_margin'], .01, 4), MARGIN_FLAG, 4) ?
													"<span style=\"color:red;\">" : NULL
												) .
												"{$this->lines->current_line['gp_margin']}&nbsp;%" .
												( $this->lines->current_line['gp_margin'] && defined('MARGIN_FLAG') && bccomp( bcmul($this->lines->current_line['gp_margin'], .01, 4), MARGIN_FLAG, 4) ?
    												"</span>" : NULL
												) : NULL
											) . "
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err8{$this->popup_id}\">Item List Price:</td>
								<td >" .
    								$this->form->text_box(
                                        "name=list",
                                        "value=" .
        								( $this->lines->current_line['list'] ?
                                            number_format($this->lines->current_line['list'], 2) :
                                            ( $copy_from->current_line['list'] ?
                                                number_format($copy_from->current_line['list'], 2) : NULL
                                            )
                                        ),
                                        "maxlength=13",
                                        "size=10",
                                        "onFocus=this.select()",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "tabindex=11",
                                        "style=text-align:right;",
                                        "onBlur=this.value=formatCurrency(this.value);",
                                        ( $valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL
                                        )
                                    ) . "
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err9{$this->popup_id}\">Discounting:</td>
								<td nowrap>" .
									$this->form->text_box(
                                        "name=discount1",
                                        "value=" .
    									( $copy_from && bccomp($copy_from->current_line['discount1'], 0, 6) ?
        									trim_decimal( bcmul($copy_from->current_line['discount1'], 100, 6) ) :
        									( bccomp($this->lines->current_line['discount1'], 0, 6) ?
            									trim_decimal( bcmul($this->lines->current_line['discount1'], 100, 6) ) : NULL
            								)
            							),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;" .
                                    $this->form->text_box(
                                        "name=discount2",
                                        "value=" .
                                        ( $copy_from && bccomp($copy_from->current_line['discount2'], 0, 6) ?
                                            trim_decimal( bcmul($copy_from->current_line['discount2'], 100, 6) ) :
                                            ( bccomp($this->lines->current_line['discount2'], 0, 6) ?
                                                trim_decimal( bcmul($this->lines->current_line['discount2'], 100, 6) ) : NULL
                                            )
                                        ),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;" .
                                    $this->form->text_box(
                                        "name=discount3",
                                        "value=" .
                                        ( $copy_from && bccomp($copy_from->current_line['discount3'], 0, 6) ?
                                            trim_decimal( bcmul($copy_from->current_line['discount3'], 100, 6) ) :
                                            ( bccomp($this->lines->current_line['discount3'], 0, 6) ?
                                                trim_decimal( bcmul($this->lines->current_line['discount3'], 100, 6) ) : NULL
                                            )
                                        ),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;" .
                                    $this->form->text_box(
                                        "name=discount4",
                                        "value=" .
                                        ( $copy_from && bccomp($copy_from->current_line['discount4'], 0, 6) ?
                                            trim_decimal( bcmul($copy_from->current_line['discount4'], 100, 6) ) :
                                            ( bccomp($this->lines->current_line['discount4'], 0, 6) ?
                                                trim_decimal( bcmul($this->lines->current_line['discount4'], 100, 6) ) : NULL
                                            )
                                        ),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;" .
                                    $this->form->text_box(
                                        "name=discount5",
                                        "value=" .
                                        ( $copy_from && bccomp($copy_from->current_line['discount5'], 0, 6) ?
                                            trim_decimal( bcmul($copy_from->current_line['discount5'], 100, 6) ) :
                                            ( bccomp($this->lines->current_line['discount5'], 0, 6) ?
                                                trim_decimal( bcmul($this->lines->current_line['discount5'], 100, 6) ) : NULL
                                            )
                                        ),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err10{$this->popup_id}\">Item Cost:</td>
								<td style=\"vertical-align:bottom;\">" .
    								$this->form->text_box(
                                        "name=cost",
                                        "value=" .
        								( $this->lines->current_line['cost'] ?
                                            number_format($this->lines->current_line['cost'], 2) :
                                            ( $copy_from ?
                                                number_format($copy_from->current_line['cost'], 2) : NULL
                                            )
                                        ),
                                        "maxlength=15",
                                        "size=10",
                                        "tabindex=17",
                                        "onFocus=this.select()",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        ( ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ) || $work_order_hash ?
                                            "readonly" : NULL
                                        ),
                                        ( $work_order_hash ?
                                            "title=This line is a work order. Update the work order resources in order to change cost." : NULL
                                        ),
                                        "style=text-align:right;",
                                        "onBlur=if(this.value){this.value=formatCurrency(this.value);}"
                                    ) . "
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err11{$this->popup_id}\">" .
								( ! $this->lines->current_line['invoice_hash'] || ( $this->lines->current_line['invoice_hash'] && $this->lines->current_line['gp_type'] == 'G' ) ?
									"GP Margin:" : "&nbsp;"
								) . "
								</td>
								<td style=\"vertical-align:bottom;\">" .
								( ! $this->lines->current_line['invoice_hash'] ?
									$this->form->hidden(array(
    									'tmp_gp_margin'        => '',
    									'tmp_list_discount'    => ''
									) ) .
									$this->form->text_box(
                                        "name=gp_margin",
                                        "value=" .
                                        ( $copy_from ?
                                            ( $copy_from->current_line['gp_type'] == 'G' && bccomp($copy_from->current_line['gp_margin'], 0, 4) ?
                                                (float)$copy_from->current_line['gp_margin'] : NULL
                                            )
                                            :
                                            ( $this->lines->current_line['gp_type'] == 'G' && bccomp($this->lines->current_line['gp_margin'], 0, 4) ?
                                                (float)$this->lines->current_line['gp_margin'] : NULL
                                            )
                                        ),
                                        "maxlength=6",
                                        "size=5",
                                        ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        "style=text-align:right;",
                                        "onBlur=this.value=formatNumber(this.value);",
                                        "tabindex=18",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "disabled" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onFocus=\$('tmp_gp_margin').value=formatNumber( this.value );this.select();" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onKeyDown=if(\$F('tmp_gp_margin') != formatNumber( this.value )){clear_values('list_discount')}" : NULL
                                        )
                                    ) . "&nbsp;%"
                                    :
                                    $this->form->hidden( array(
                                        'gp_margin' =>  $this->lines->current_line['gp_margin']
                                    ) ) .
									( $this->lines->current_line['gp_type'] == 'G' ?
										"{$this->lines->current_line['gp_margin']}&nbsp;%" : "&nbsp;"
									)
								) .
								( ! $this->lines->current_line['invoice_hash'] ?
									"&nbsp;<strong>OR</strong>&nbsp;" : NULL
								) .
								( ! $this->lines->current_line['invoice_hash'] ?
									$this->form->text_box(
                                        "name=list_discount",
                                        "value=" .
    									( $this->lines->current_line['gp_type'] == 'L' ?
        									math::list_discount( array(
	        									'list' =>  $this->lines->current_line['list'],
	        									'sell' =>  $this->lines->current_line['sell']
        									) ) :
        									( $copy_from->current_line['gp_type'] == 'L' ?
            									math::list_discount( array(
	            									'list' =>  $copy_from->current_line['list'],
	            									'sell' =>  $copy_from->current_line['sell']
            									) ) : NULL
            								)
            							),
                                        "maxlength=6",
                                        "size=5",
                                        ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        "style=text-align:right;",
                                        "onBlur=this.value=formatNumber(this.value);",
                                        "tabindex=19",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "disabled" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onFocus=\$('tmp_list_discount').value=formatNumber( this.value );this.select()" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onKeyDown=if(\$F('tmp_list_discount') != formatNumber( this.value )){clear_values('gp_margin')}" : NULL
                                        )
                                    ) . "&nbsp;% Discount Off List"
                                    :
                                    $this->form->hidden( array(
                                        'list_discount' =>  math::list_discount( array(
                                            'list'  =>  $this->lines->current_line['list'],
                                            'sell'  =>  $this->lines->current_line['sell']
                                        ) )
                                    ) ) .
									( $this->lines->current_line['gp_type'] == 'L' ?
										math::list_discount( array(
											'list'    =>  $this->lines->current_line['list'],
											'sell'    =>  $this->lines->current_line['sell']
										) ) . "&nbsp;% Discount Off List" : "&nbsp;"
									)
								) . "
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err12{$this->popup_id}\">Item Sell Price:</td>
								<td style=\"vertical-align:bottom;\">" .
								( $this->lines->current_line['invoice_hash'] ?
                                    $this->form->hidden( array(
                                        'sell'   =>  $this->lines->current_line['sell']
                                    ) ) . "\$" . number_format($this->lines->current_line['sell'], 2)
									:
									$this->form->text_box(
                                        "name=sell",
                                        "value=" .
                                        ( $this->lines->current_line['sell'] ?
                                            number_format($this->lines->current_line['sell'], 2) :
                                            ( $copy_from ?
                                                number_format($copy_from->current_line['sell'], 2) : NULL
                                            )
                                        ),
                                        "maxlength=13",
                                        "size=10",
                                        "onFocus=this.select();",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "disabled" : NULL
                                        ),
                                        ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        "style=text-align:right;",
                                        "tabindex=20",
                                        "onChange=clear_values('gp_margin');" .
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "\$('edit_change').value=1" : NULL
                                        ),
                                        "onBlur=this.value=formatCurrency(this.value);"
                                    )
                                ) . "
								</td>
							</tr>
							<tr>
								<td colspan=\"2\">&nbsp;</td>
							</tr>
						</table>
					</td>
				</tr>";
		}else{
			
			$tbl = $this->form->form_tag("line_item_edit").
			$this->form->hidden(array("item_hash"               =>  $this->lines->item_hash,
					"punch"                   =>  ($punch_import || $this->lines->current_line['punch'] ? 1 : NULL),
					"proposal_hash"           =>  $this->proposal_hash,
					"popup_id"                =>  $this->popup_id,
					'p'                       =>  $this->ajax_vars['p'],
					'proposal_placeholder'    =>  1,
					'copy_from'               =>  ($copy_from ? $copy_from->item_hash : NULL),
					'order'                   =>  $this->ajax_vars['order'],
					'order_dir'               =>  $this->ajax_vars['order_dir'],
					'work_order_import'       =>  $work_order_hash,
					'from_edit'               =>  $this->ajax_vars['from_edit'],
					'booked'                  =>  ( $this->lines->current_line['status'] ? 1 : NULL ),
					'MARGIN_FLAG'             =>  ( defined('MARGIN_FLAG') ? MARGIN_FLAG : 0 ) ) ) .
					( $valid && $this->lines->current_line['status'] > 0 ?
							$this->form->hidden(array('edit_change' =>  '')) : NULL)."
							<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0;height:100%;\">
							<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
							<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
							<p id=\"feedback_message".$this->popup_id."\"></p>
							</div>
							<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">".($valid ? "
									<tr>
									<td style=\"background-color:#ffffff;padding:0;\">".($valid && $this->lines->current_line['status'] > 0 &&
											// Show edit booked/invoiced line item button only if user has edit po permission or edit customer invoice line items permission. For ticket #118.
											($this->lines->current_line['invoice_hash'] ? $this->p->ck('customer_invoice', 'E', 'line_items') : $this->p->ck('purchase_order', 'E', NULL)) ? "
											<div id=\"edit_booked_line_holder\" onClick=\"editLineItem('$invoice_lock');\" style=\"cursor:hand;float:right;margin-right:10px;width:58px;height:23px;background-color:#eaeaea;border-left:1px solid #8f8f8f;border-right:1px solid #8f8f8f;\" title=\"Edit this ".($this->lines->current_line['invoice_hash'] ? "invoiced" : " booked")." line item\">
											<div style=\"margin-top:2px;margin-left:4px;\">
											<div style=\"float:right;font-weight:bold;margin-right:5px;margin-top:3px;\">Edit</div>
											<img src=\"images/edit_pencil.gif\">
											</div>
											</div>" : NULL)."
									<div style=\"padding-left:5px;\">".($this->lines->current_line ? ($this->p->ck($perm_class, 'D', $perm_content) && !$this->lines->current_line['status'] && $this->lock ? "
											<a href=\"javascript:void(0);\" onClick=\"if (confirm('Are you sure you want to delete this line item? This action CAN NOT be undone!')){submit_form(\$('item_vendor').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'delete=1');}\" class=\"link_standard\"><img src=\"images/rm_lineitem.gif\" title=\"Delete selected line items\" border=\"0\" /></a>
											&nbsp;" : NULL).($this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status'] && $this->lock ? "
													<a href=\"javascript:void(0);\" onClick=\"submit_form($('proposal_placeholder').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item', 'method=tag', 'method_action=inactive', 'proposal_hash=".$this->proposal_hash."', 'line_item[]=".$this->lines->item_hash."');\" class=\"link_standard\"><img src=\"images/line_inactive.gif\" title=\"Toggle the selected items between active & inactive.\" border=\"0\" /></a>
													&nbsp;" : NULL) : NULL).($this->lines->current_line['import_data'] ? "
															<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'view_details', 'proposal_hash=".$this->proposal_hash."', 'item_hash=".$this->lines->item_hash."', 'popup_id=item_details');\" class=\"link_standard\"><img src=\"images/item_details.gif\" title=\"View item details from original import.\" border=\"0\" style=\"margin-top:2px;\" /></a>
															&nbsp;" : "&nbsp;").($this->lines->current_line && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lock ?
																	"<img src=\"images/lock.gif\" title=\"Read-Only\" />&nbsp;<strong>This proposal is being edited by another user</strong>" : $item_menu)."
									</div>
									</td>
									</tr>" : NULL)."
									<tr>
									<td style=\"background-color:#d6d6d6;padding-left:20px;\">
									<table style=\"width:100%\" >
									<tr>
									<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1".$this->popup_id."\">Vendor: </div>
									".$this->form->text_box("name=item_vendor",
											"value=".($copy_from ? stripslashes($copy_from->current_line['item_vendor']) : stripslashes($this->lines->current_line['item_vendor'])),
											"autocomplete=off",
											"size=30",
											"TABINDEX=1",
											($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
											($valid && (!$this->p->ck($perm_class, 'E', $perm_content) || $this->lines->current_line['status']) ? "readonly" : NULL),
											(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'item_vendor', 'item_vendor_hash', 1);}" : NULL),
											(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'item_vendor', 'item_vendor_hash', 1);}" : NULL),
											(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onBlur=key_clear();if( \$('item_vendor_hash') && !\$F('item_vendor_hash') ){\$('item_product_hash').value='';\$('item_product_hash').disabled='true';}" : NULL),
											(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onKeyDown=clear_values('item_vendor_hash');" : NULL)).
											$this->form->hidden(array("item_vendor_hash" => ($copy_from ? $copy_from->current_line['vendor_hash'] : $this->lines->current_line['vendor_hash'])))."
											</td>
											<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2".$this->popup_id."\">Item Number: </div>
									".$this->form->text_box("name=item_no",
											"value=".($copy_from ? $copy_from->current_line['item_no'] : $this->lines->current_line['item_no']),
											"maxlength=64",
											"TABINDEX=4",
											($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
											($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
											(!$this->lines->current_line['status'] ? "onBlur=if(\$F('item_vendor_hash')){submit_form(\$('item_vendor').form, 'proposals', 'exec_post', 'refresh_form', 'action=fill_discounts', 'from_input=item_no');}" : NULL),
											(($valid && !$this->p->ck($perm_class, 'E', $perm_content)) || ($valid && $this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status']) ? "readonly" : NULL),
											($this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status'] ? "title=This item was created from an import, editing has been disabled." : NULL)
									)."
									</td>
									<td rowspan=\"4\" style=\"vertical-align:top;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err3".$this->popup_id."\">Item Description: </div>
									".$this->form->text_area("name=item_descr",
															 "rows=11",
															 "TABINDEX=8",
															 "cols=35",
															 ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
															 ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															 "value=".($copy_from ? stripslashes($copy_from->current_line['item_descr']) : stripslashes($this->lines->current_line['item_descr'])),
															 (($valid && !$this->p->ck($perm_class, 'E', $perm_content)) || ($this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status']) ? "readonly" : NULL),
															 ($this->lines->current_line['import_data'] && SPEC_ITEM_EDIT == 0 && !$this->lines->current_line['status'] ? "title=This item was created from an import, editing has been disabled." : NULL)
															 )."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err4".$this->popup_id."\">Ship To: </div>
									".$this->form->text_box("name=item_ship_to",
															"value=".($copy_from && $copy_from->current_line['item_ship_to'] ? stripslashes($copy_from->current_line['item_ship_to']) : ($this->lines->item_hash ? stripslashes($this->lines->current_line['item_ship_to']) : stripslashes($this->current_proposal['ship_to']))),
															"autocomplete=off",
															"size=30",
															"TABINDEX=2",
															($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
															($valid && (!$this->p->ck($perm_class, 'E', $perm_content) || $this->lines->current_line['status']) ? "readonly" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'item_ship_to', 'item_ship_to_hash', 1);}" : NULL),
                                                            (!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content) && !$this->lines->current_line['status']) ? "onKeyUp=if(ka==false && this.value){key_call('proposals', 'item_ship_to', 'item_ship_to_hash', 1);}" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onBlur=key_clear();" : NULL),
															(!$valid || ($valid && $this->p->ck($perm_class, 'E', $perm_content)) ? "onKeyDown=clear_values('item_ship_to_hash');" : NULL)).
									$this->form->hidden(array("item_ship_to_hash" => ($copy_from && $copy_from->current_line['item_ship_to'] ? $copy_from->current_line['ship_to_hash'] : ($this->lines->current_line['ship_to_hash'] ? $this->lines->current_line['ship_to_hash'] : $this->current_proposal['ship_to_hash']))))."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err5".$this->popup_id."\">Item Tagging: </div>
									".$this->form->text_box("name=item_tag1",
															"value=".($copy_from ? $copy_from->current_line['item_tag1'] : $this->lines->current_line['item_tag1']),
															"TABINDEX=5",
															"maxlength=128",
									                        ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
									                        ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err6".$this->popup_id."\">Product/Service: </div>
									<div id=\"vendor_product_holder\" ".($this->lines->current_line['invoice_hash'] ? "style=\"margin-left:10px;font-weight:bold;\"" : NULL).">".($this->lines->current_line['invoice_hash'] ?
										$this->lines->current_line['item_product'].$this->form->hidden(array('item_product_hash' => $this->lines->current_line['product_hash'])) : ($copy_from->current_line['vendor_hash'] || $this->lines->current_line['vendor_hash'] ?
											$this->innerHTML_customer($copy_from->current_line['vendor_hash'] ? $copy_from->current_line['vendor_hash'] : $this->lines->current_line['vendor_hash'], 'item_vendor', 'vendor_product_holder', ($copy_from ? $copy_from->current_line['product_hash'] : $this->lines->current_line['product_hash']), NULL, NULL, ($this->lines->current_line['status'] > 0 ? 1 : NULL), ($this->lines->current_line['status'] > 0 ? 1 : NULL)) : $this->form->select("item_product_hash", array("Select Vendor First..."), NULL, array(""), "disabled", "style=width:195px;")))."
									</div>
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err13".$this->popup_id."\">Item Tagging (2): </div>
									".$this->form->text_box("name=item_tag2",
															"value=".($copy_from ? $copy_from->current_line['item_tag2'] : $this->lines->current_line['item_tag2']),
															"TABINDEX=6",
															"maxlength=128",
										                    ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
										                    ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:top;padding-top:5px;\" id=\"discount_message".$this->popup_id."\">".($this->lines->item_hash && !$this->lines->current_line['status'] ?
									$disc_vals['discount_message'.$this->popup_id] : NULL)."
								</td>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err15".$this->popup_id."\">Item Tagging (3): </div>
									".$this->form->text_box("name=item_tag3",
															"value=".($copy_from ? $copy_from->current_line['item_tag3'] : $this->lines->current_line['item_tag3']),
															"maxlength=128",
															"TABINDEX=7",
									                        ($valid && $this->lines->current_line['status'] > 0 ? "disabled" : NULL),
									                        ($valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL),
															($valid && !$this->p->ck($perm_class, 'E', $perm_content) ? "readonly" : NULL))."
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#ffffff;padding-left:20px;\">".($this->lines->current_line['work_order_hash'] ? "
						<div style=\"font-style:italic;\">Work Order : <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('pm_module', 'sf_loadcontent', 'show_popup_window', 'edit_work_order', 'popup_id=work_order_item', 'proposal_hash=".$this->proposal_hash."', 'order_hash=".$this->lines->current_line['work_order_hash']."');\" title=\"View this work order.\">".$this->lines->current_line['work_order_no']."</a></div>" : NULL)."
						<table style=\"width:100%\" cellpadding=\"3\">
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err7".$this->popup_id."\">Quantity:</td>
								<td >" .
                                ( $this->lines->current_line['invoice_hash'] ?
                                    $this->form->hidden( array(
                                        'qty' =>  $this->lines->current_line['qty']
                                    ) ) . rtrim( trim( $this->lines->current_line['qty'], '0'), '.')
                                    :
									$this->form->text_box(
                                        "name=qty",
                                        "value=" .
    									( $copy_from ?
                                            trim_decimal( $copy_from->current_line['qty'] ) :
                                            ( $this->lines->item_hash ?
                                                trim_decimal( $this->lines->current_line['qty'] ) : NULL
                                            )
                                        ),
                                        "size=5",
                                        ( ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ) || $work_order_hash || ( $this->ajax_vars['from_edit'] == 'invoice' && $this->ajax_vars['invoice_hash'] ) ?
                                            "readonly" : NULL
                                        ),
                                        "maxlength=13",
                                        "tabindex=10",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "style=text-align:right;",
                                        ( $work_order_hash ?
                                            "title=This line is a work order. Update the work order resources in order to change cost." : NULL
                                        ),
                                        "onFocus=this.select();",
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        )
                                    )
                                ) . "
								</td>
								<td rowspan=\"7\" style=\"width:250px;vertical-align:top;background-color:#efefef;border:1px solid #9f9f9f;\">" .
									$this->form->hidden( array(
    									"discount_hash"        =>
                                        ( $copy_from->current_line['discount_hash'] ?
                                            $copy_from->current_line['discount_hash'] : $this->lines->current_line['discount_hash']
                                        ),
                                        "discount_item_hash"   =>
                                        ( $copy_from->current_line['discount_item_hash'] ?
                                            $copy_from->current_line['discount_item_hash'] : $this->lines->current_line['discount_item_hash']
                                        )
                                    ) ) . "
									<table>".$tr."
										<tr>
											<td style=\"vertical-align:top;text-align:right;\">Discount ID:</td>
											<td id=\"discount_id{$this->popup_id}\">" .
											( $copy_from->current_line['discount_id'] || $this->lines->current_line['discount_id'] ?
												( $copy_from->current_line['discount_id'] ?
    												$copy_from->current_line['discount_id'] : $this->lines->current_line['discount_id']
        										)
        										:
    											( $copy_from->item_hash || $this->lines->item_hash ?
                                                    "No Discount Used" : NULL
    											)
    										) . "
											</td>
										</tr>
										<tr>
											<td style=\"vertical-align:top;text-align:right;\" id=\"disc_menu_holder_x\">
												Description:
												<div id=\"disc_menu{$this->popup_id}\" " . ( $this->lines->current_line['item_code'] || $this->lines->current_line['status'] ? "style=\"display:none;\"" : NULL ) . ">" .
												( $this->lines->item_hash ?
													$disc_vals['disc_menu']	: NULL
												) . "
												</div>
											</td>
											<td style=\"vertical-align:top;\" id=\"discount_descr{$this->popup_id}\">" .
											( $copy_from->current_line['discount_descr'] || $this->lines->current_line['discount_descr'] ?
												( strlen($this->lines->current_line['discount_descr']) > 15 || strlen($copy_from->current_line['discount_descr']) > 15 ?
													wordwrap( $copy_from->current_line['discount_descr'] ? $copy_from->current_line['discount_descr'] : $this->lines->current_line['discount_descr'], 15, "<br />", true)
													:
													( $copy_from->current_line['discount_descr'] ?
    													$copy_from->current_line['discount_descr'] : $this->lines->current_line['discount_descr']
    												)
    											) : NULL
    										) . "
											</td>
										</tr>
										<tr>
											<td style=\"vertical-align:top;text-align:right;\">Expiration:</td>
											<td id=\"discount_expiration{$this->popup_id}\"></td>
										</tr>
										<tr>
											<td style=\"text-align:right;padding-top:20px;\">Extended Cost: </td>
											<td style=\"padding-left:5px;padding-top:20px;\" id=\"extended_cost{$this->popup_id}\">" .
											( $this->lines->current_line['ext_cost'] ? "
												\$".number_format($this->lines->current_line['ext_cost'], 2) :
    											( $copy_from->current_line['ext_cost'] ?
        											$copy_from->current_line['ext_cost'] : NULL
        										)
        									) . "
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;\">Extended Sell: </td>
											<td style=\"padding-left:5px;\" id=\"extended_sell{$this->popup_id}\">" .
											( $this->lines->current_line['ext_sell'] ? "
												\$".number_format($this->lines->current_line['ext_sell'], 2) :
    											( $copy_from->current_line['ext_sell'] ?
        											$copy_from->current_line['ext_sell'] : NULL
        										)
        									) . "
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold;padding-top:10px;\">Profit Dollars: </td>
											<td style=\"font-weight:bold;padding-top:10px;padding-left:5px;\" id=\"profit_dollars{$this->popup_id}\">" .
											( bccomp( bcsub($this->lines->current_line['ext_sell'], $this->lines->current_line['ext_cost'], 2), 0, 2) ?
												( bccomp( bcsub($this->lines->current_line['ext_sell'], $this->lines->current_line['ext_cost'], 2), 0, 2) == -1 ? "
													<span style=\"color:red;\">(\$" . number_format( bcmul( bcsub($this->lines->current_line['ext_sell'], $this->lines->current_line['ext_cost'], 2), -1, 2), 2) . ")</span>" : "\$" . number_format(bcsub($this->lines->current_line['ext_sell'], $this->lines->current_line['ext_cost'], 2), 2)
												) : NULL
											) . "
											</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold;\" id=\"extended_cost{$this->popup_id}\">GP Margin: </td>
											<td style=\"font-weight:bold;padding-left:5px;\" id=\"gp_margin{$this->popup_id}\">" .
											( $this->lines->current_line['gp_margin'] ?
												( $this->lines->current_line['gp_margin'] && defined('MARGIN_FLAG') && bccomp( bcmul($this->lines->current_line['gp_margin'], .01, 4), MARGIN_FLAG, 4) ?
													"<span style=\"color:red;\">" : NULL
												) .
												"{$this->lines->current_line['gp_margin']}&nbsp;%" .
												( $this->lines->current_line['gp_margin'] && defined('MARGIN_FLAG') && bccomp( bcmul($this->lines->current_line['gp_margin'], .01, 4), MARGIN_FLAG, 4) ?
    												"</span>" : NULL
												) : NULL
											) . "
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err8{$this->popup_id}\">Item List Price:</td>
								<td >" .
    								$this->form->text_box(
                                        "name=list",
                                        "value=" .
        								( $this->lines->current_line['list'] ?
                                            number_format($this->lines->current_line['list'], 2) :
                                            ( $copy_from->current_line['list'] ?
                                                number_format($copy_from->current_line['list'], 2) : NULL
                                            )
                                        ),
                                        "maxlength=13",
                                        "size=10",
                                        "onFocus=this.select()",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "tabindex=11",
                                        "style=text-align:right;",
                                        "onBlur=this.value=formatCurrency(this.value);",
                                        ( $valid && $this->lines->current_line['status'] ? "onChange=\$('edit_change').value=1" : NULL
                                        )
                                    ) . "
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err9{$this->popup_id}\">Discounting:</td>
								<td nowrap>" .
									$this->form->text_box(
                                        "name=discount1",
                                        "value=" .
    									( $copy_from && bccomp($copy_from->current_line['discount1'], 0, 6) ?
        									trim_decimal( bcmul($copy_from->current_line['discount1'], 100, 6) ) :
        									( bccomp($this->lines->current_line['discount1'], 0, 6) ?
            									trim_decimal( bcmul($this->lines->current_line['discount1'], 100, 6) ) : NULL
            								)
            							),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;" .
                                    $this->form->text_box(
                                        "name=discount2",
                                        "value=" .
                                        ( $copy_from && bccomp($copy_from->current_line['discount2'], 0, 6) ?
                                            trim_decimal( bcmul($copy_from->current_line['discount2'], 100, 6) ) :
                                            ( bccomp($this->lines->current_line['discount2'], 0, 6) ?
                                                trim_decimal( bcmul($this->lines->current_line['discount2'], 100, 6) ) : NULL
                                            )
                                        ),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;" .
                                    $this->form->text_box(
                                        "name=discount3",
                                        "value=" .
                                        ( $copy_from && bccomp($copy_from->current_line['discount3'], 0, 6) ?
                                            trim_decimal( bcmul($copy_from->current_line['discount3'], 100, 6) ) :
                                            ( bccomp($this->lines->current_line['discount3'], 0, 6) ?
                                                trim_decimal( bcmul($this->lines->current_line['discount3'], 100, 6) ) : NULL
                                            )
                                        ),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;" .
                                    $this->form->text_box(
                                        "name=discount4",
                                        "value=" .
                                        ( $copy_from && bccomp($copy_from->current_line['discount4'], 0, 6) ?
                                            trim_decimal( bcmul($copy_from->current_line['discount4'], 100, 6) ) :
                                            ( bccomp($this->lines->current_line['discount4'], 0, 6) ?
                                                trim_decimal( bcmul($this->lines->current_line['discount4'], 100, 6) ) : NULL
                                            )
                                        ),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;" .
                                    $this->form->text_box(
                                        "name=discount5",
                                        "value=" .
                                        ( $copy_from && bccomp($copy_from->current_line['discount5'], 0, 6) ?
                                            trim_decimal( bcmul($copy_from->current_line['discount5'], 100, 6) ) :
                                            ( bccomp($this->lines->current_line['discount5'], 0, 6) ?
                                                trim_decimal( bcmul($this->lines->current_line['discount5'], 100, 6) ) : NULL
                                            )
                                        ),
                                        "size=3",
                                        "onFocus=this.select()",
                                        "maxlength=6",
                                        "tabindex=12",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        "onBlur=this.value=formatNumber(this.value);",
                                        ( $valid && !$this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        "style=text-align:right;"
                                    ) . "&nbsp;%&nbsp;
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err10{$this->popup_id}\">Item Cost:</td>
								<td style=\"vertical-align:bottom;\">" .
    								$this->form->text_box(
                                        "name=cost",
                                        "value=" .
        								( $this->lines->current_line['cost'] ?
                                            number_format($this->lines->current_line['cost'], 2) :
                                            ( $copy_from ?
                                                number_format($copy_from->current_line['cost'], 2) : NULL
                                            )
                                        ),
                                        "maxlength=15",
                                        "size=10",
                                        "tabindex=17",
                                        "onFocus=this.select()",
                                        ( $valid && $this->lines->current_line['status'] > 0 ?
                                            "disabled" : NULL
                                        ),
                                        ( ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ) || $work_order_hash ?
                                            "readonly" : NULL
                                        ),
                                        ( $work_order_hash ?
                                            "title=This line is a work order. Update the work order resources in order to change cost." : NULL
                                        ),
                                        "style=text-align:right;",
                                        "onBlur=if(this.value){this.value=formatCurrency(this.value);}"
                                    ) . "
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err11{$this->popup_id}\">" .
								( ! $this->lines->current_line['invoice_hash'] || ( $this->lines->current_line['invoice_hash'] && $this->lines->current_line['gp_type'] == 'G' ) ?
									"GP Margin:" : "&nbsp;"
								) . "
								</td>
								<td style=\"vertical-align:bottom;\">" .
								( ! $this->lines->current_line['invoice_hash'] ?
									$this->form->hidden(array(
    									'tmp_gp_margin'        => '',
    									'tmp_list_discount'    => ''
									) ) .
									$this->form->text_box(
                                        "name=gp_margin",
                                        "value=" .
                                        ( $copy_from ?
                                            ( $copy_from->current_line['gp_type'] == 'G' && bccomp($copy_from->current_line['gp_margin'], 0, 4) ?
                                                (float)$copy_from->current_line['gp_margin'] : NULL
                                            )
                                            :
                                            ( $this->lines->current_line['gp_type'] == 'G' && bccomp($this->lines->current_line['gp_margin'], 0, 4) ?
                                                (float)$this->lines->current_line['gp_margin'] : NULL
                                            )
                                        ),
                                        "maxlength=6",
                                        "size=5",
                                        ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        "style=text-align:right;",
                                        "onBlur=this.value=formatNumber(this.value);",
                                        "tabindex=18",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "disabled" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onFocus=\$('tmp_gp_margin').value=formatNumber( this.value );this.select();" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onKeyDown=if(\$F('tmp_gp_margin') != formatNumber( this.value )){clear_values('list_discount')}" : NULL
                                        )
                                    ) . "&nbsp;%"
                                    :
                                    $this->form->hidden( array(
                                        'gp_margin' =>  $this->lines->current_line['gp_margin']
                                    ) ) .
									( $this->lines->current_line['gp_type'] == 'G' ?
										"{$this->lines->current_line['gp_margin']}&nbsp;%" : "&nbsp;"
									)
								) .
								( ! $this->lines->current_line['invoice_hash'] ?
									"&nbsp;<strong>OR</strong>&nbsp;" : NULL
								) .
								( ! $this->lines->current_line['invoice_hash'] ?
									$this->form->text_box(
                                        "name=list_discount",
                                        "value=" .
    									( $this->lines->current_line['gp_type'] == 'L' ?
        									math::list_discount( array(
	        									'list' =>  $this->lines->current_line['list'],
	        									'sell' =>  $this->lines->current_line['sell']
        									) ) :
        									( $copy_from->current_line['gp_type'] == 'L' ?
            									math::list_discount( array(
	            									'list' =>  $copy_from->current_line['list'],
	            									'sell' =>  $copy_from->current_line['sell']
            									) ) : NULL
            								)
            							),
                                        "maxlength=6",
                                        "size=5",
                                        ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        "style=text-align:right;",
                                        "onBlur=this.value=formatNumber(this.value);",
                                        "tabindex=19",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "disabled" : NULL
                                        ),
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "onChange=\$('edit_change').value=1" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onFocus=\$('tmp_list_discount').value=formatNumber( this.value );this.select()" : NULL
                                        ),
                                        ( ! $valid || ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) ?
                                            "onKeyDown=if(\$F('tmp_list_discount') != formatNumber( this.value )){clear_values('gp_margin')}" : NULL
                                        )
                                    ) . "&nbsp;% Discount Off List"
                                    :
                                    $this->form->hidden( array(
                                        'list_discount' =>  math::list_discount( array(
                                            'list'  =>  $this->lines->current_line['list'],
                                            'sell'  =>  $this->lines->current_line['sell']
                                        ) )
                                    ) ) .
									( $this->lines->current_line['gp_type'] == 'L' ?
										math::list_discount( array(
											'list'    =>  $this->lines->current_line['list'],
											'sell'    =>  $this->lines->current_line['sell']
										) ) . "&nbsp;% Discount Off List" : "&nbsp;"
									)
								) . "
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;width:100px;text-align:right;\" id=\"err12{$this->popup_id}\">Item Sell Price:</td>
								<td style=\"vertical-align:bottom;\">" .
								( $this->lines->current_line['invoice_hash'] ?
                                    $this->form->hidden( array(
                                        'sell'   =>  $this->lines->current_line['sell']
                                    ) ) . "\$" . number_format($this->lines->current_line['sell'], 2)
									:
									$this->form->text_box(
                                        "name=sell",
                                        "value=" .
                                        ( $this->lines->current_line['sell'] ?
                                            number_format($this->lines->current_line['sell'], 2) :
                                            ( $copy_from ?
                                                number_format($copy_from->current_line['sell'], 2) : NULL
                                            )
                                        ),
                                        "maxlength=13",
                                        "size=10",
                                        "onFocus=this.select();",
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "disabled" : NULL
                                        ),
                                        ( $valid && ! $this->p->ck($perm_class, 'E', $perm_content) ?
                                            "readonly" : NULL
                                        ),
                                        "style=text-align:right;",
                                        "tabindex=20",
                                        "onChange=clear_values('gp_margin');" .
                                        ( $valid && $this->lines->current_line['status'] ?
                                            "\$('edit_change').value=1" : NULL
                                        ),
                                        "onBlur=this.value=formatCurrency(this.value);"
                                    )
                                ) . "
								</td>
							</tr>
							<tr>
								<td colspan=\"2\">&nbsp;</td>
							</tr>
						</table>
					</td>
				</tr>";
		}
								
								

			if ( $this->lines->current_line['status'] ) {

				$tbl .= "
				<tr>
					<td style=\"background-color:#d6d6d6;padding-left:20px;\">
						<table style=\"width:100%\" >
							<tr>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:10px;font-weight:bold;\">
										Item Status: Ordered" .
                        				( $this->lines->current_line['ship_date'] && $this->lines->current_line['ship_date'] != '0000-00-00' ?
											", Shipped" : NULL
                        				) .
                        				( $this->lines->current_line['receive_date'] && $this->lines->current_line['receive_date'] != '0000-00-00' ?
											", Received" : NULL
                            			) .
                            			( $this->lines->current_line['deliver_date'] && $this->lines->current_line['deliver_date'] != '0000-00-00' ?
											", Delivered" : NULL
                            			) .
                            			( $this->lines->current_line['invoice_hash'] ?
											" &amp; Invoiced" : NULL
                            			) . "
									</div>
									<div style=\"margin-left:15px;padding-bottom:5px;\">
										<table style=\"width:90%;\" style=\"background-color:#8f8f8f;\" cellpadding=\"5\" cellspacing=\"1\">
											<tr>
												<td style=\"background-color:#efefef;\">Purchase Order:</td>
												<td style=\"background-color:#efefef;\">Est. Ship Date:</td>
												<td style=\"background-color:#efefef;\">Receive Date:</td>
												<td style=\"background-color:#efefef;\">Delivery Date:</td>
												<td style=\"background-color:#efefef;\">Invoice Date:</td>
											</tr>
											<tr>
												<td style=\"background-color:#ffffff;vertical-align:bottom;\">";

												if ($this->lines->current_line['work_order_hash']) {

													$po = new purchase_order($this->proposal_hash);
													list($wo_po_hash, $wo_po_no, $wo_po_date) = $po->work_order_po_list($this->lines->current_line['work_order_hash']);

													$tbl .= "
													<div style=\"height:30px;overflow:auto;\">";

													for ($i = 0; $i < count($wo_po_hash); $i++) {

														$tbl .= "
														<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'edit_po', 'proposal_hash={$this->proposal_hash}', 'po_hash={$wo_po_hash[$i]}', 'popup_id=line_item_detail');\" class=\"link_standard\">{$wo_po_no[$i]}</a>
														<div style=\"padding-top:5px;padding-bottom:5px\">" . date(DATE_FORMAT . " " . TIMESTAMP_FORMAT, $wo_po_date[$i]) . "</div>";
													}

													$tbl .= "
													</div>";

												} else {

													$tbl .= "
													<a href=\"javascript:void(0);\" onClick=\"agent.call('purchase_order', 'sf_loadcontent', 'show_popup_window', 'edit_po', 'proposal_hash={$this->proposal_hash}', 'po_hash={$this->lines->current_line['po_hash']}', 'popup_id=line_item_detail');\" class=\"link_standard\">" .
													( $this->lines->current_line['po_no'] ?
    													$this->lines->current_line['po_no'] : "Purchase Order"
    												) . "
    												</a>
													<div style=\"padding-top:5px;\">" . date(DATE_FORMAT . " " . TIMESTAMP_FORMAT, $this->lines->current_line['po_creation_date']) . "</div>";
												}

											$tbl .= "
												</td>
												<td style=\"background-color:#ffffff;vertical-align:bottom;\">" .
    											( $this->lines->current_line['ship_date'] && $this->lines->current_line['ship_date'] != '0000-00-00' ?
													date(DATE_FORMAT, strtotime( $this->lines->current_line['ship_date'] ) ) : NULL
												) . "
												</td>
												<td style=\"background-color:#ffffff;vertical-align:bottom;\">" .
												( $this->lines->current_line['receive_date'] && $this->lines->current_line['receive_date'] != '0000-00-00' ?
													date(DATE_FORMAT, strtotime( $this->lines->current_line['receive_date'] ) ) : NULL
												) . "
												</td>
												<td style=\"background-color:#ffffff;vertical-align:bottom;\">" .
												( $this->lines->current_line['deliver_date'] && $this->lines->current_line['deliver_date'] != '0000-00-00' ?
													date(DATE_FORMAT, strtotime( $this->lines->current_line['receive_date'] ) ) : NULL
												) . "
												</td>
												<td style=\"background-color:#ffffff;vertical-align:bottom;\">" .
												( $this->lines->current_line['invoice_hash'] ?
													"<a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'proposal_hash={$this->proposal_hash}', 'invoice_hash={$this->lines->current_line['invoice_hash']}', 'popup_id=line_item_detail');\" class=\"link_standard\">{$this->lines->current_line['invoice_no']}</a>
												 	<div style=\"padding-top:5px;\">".date(DATE_FORMAT, strtotime($this->lines->current_line['invoice_date']))."</div>" : NULL
												 ) . "
												</td>
											</tr>
										</table>
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>";
			}

			if ($this->lines->total > 0) {

				$save_op_out = array("At the beginning of your line items");
				$save_op_in  = array("B");

				if ($this->lines->item_hash) {
					$save_op_out[] = "Back where it was";
					$save_op_in[] = "N";
				}

				for ($i = 1; $i <= $this->lines->total; $i++) {

					$save_op_out[] = "After line number $i";
					$save_op_in[] = $i;
				}

				$save_op_out[] = "At the end of your line items";
				$save_op_in[] = "E";

				$save_location = $this->form->select("save_location", $save_op_out, $copy_from ? $copy_from->current_line['line_no'] : $this->lines->item_hash ? 'N' : 'E', $save_op_in, "blank=1", "tabindex=22", $valid && $this->lines->current_line['status'] ? "disabled" : NULL);
			}

			if ( $this->ajax_vars['from_edit'] == 'po' && $this->ajax_vars['po_hash'] )
                $btn_action = "submit_form(this.form, 'purchase_order', 'exec_post', 'refresh_form', 'method=edit_line', 'po_hash={$this->ajax_vars['po_hash']}', 'parent_popup_id={$this->ajax_vars['parent_popup_id']}');";
			elseif ( $this->ajax_vars['from_edit'] == 'invoice' && $this->ajax_vars['invoice_hash'] )
                $btn_action = "submit_form(this.form, 'customer_invoice', 'exec_post', 'refresh_form', 'method=edit_line', 'invoice_hash={$this->ajax_vars['invoice_hash']}', 'parent_popup_id={$this->ajax_vars['parent_popup_id']}');";
			elseif ( ! $this->ajax_vars['from_edit'] )
                $btn_action = "submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_item');";


			$tbl .= "
			</table>
			<div style=\"text-align:left;padding:15px;\">" .
			( ( $valid && $this->p->ck($perm_class, 'E', $perm_content) ) || ( !$valid && $this->p->ck($perm_class, 'A', $perm_content) ) ?
    			$this->form->button(
                    "name=submit_btn",
                    "value=Save",
        			"id=lineItemBtn",
                    "tabindex=21",
                    ( $valid && ! $this->lock ?
                        "disabled title=This proposal is being edited by another user" :
                        ( $valid && $this->lines->current_line['status'] ?
                            "disabled" : NULL
                        )
                    ),
                    "onClick=if( ( \$('edit_change') && ( ( \$F('edit_change') == 1 && confirm('This line item is " . ( $this->lines->current_line['invoice_hash'] ? "invoiced" : "booked" ) . " and you have made changes. Are you sure you want to save your changes? Any changes made to this item will be reflected in the corresponding " . ( $this->lines->current_line['invoice_hash'] ? "invoice" : "purchase order" ) . ".') ) || \$F('edit_change') == '' ) ) || ! \$('edit_change') ){ $btn_action;this.disabled=1; }"
                ) . "&nbsp;" .
                ( $save_location ? "
                    <span style=\"padding-top:5px;padding-left:5px;\">&nbsp;and insert this item&nbsp;{$save_location}</span>" : NULL
                ) : NULL
            ) . "
			</div>
		</div>" .
		$this->form->close_form();

		if ( ! $valid )
			$this->content['focus'] = 'item_vendor';

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function doit_save_finish() {
		$proposal_hash = $_POST['proposal_hash'];
		$item_hash = $_POST['item_hash'];

		$this->lines = new line_items($proposal_hash);
		$this->lines->fetch_line_item_record($item_hash);
		$item_data =& __unserialize(stripslashes($this->lines->current_line['import_data']));

		if ($this->lines->current_line['panel_id']) {
			$this->fetch_panel_type($proposal_hash, $this->lines->current_line['panel_id']);
			$this->fetch_master_record($proposal_hash);
			$proposal_import_data =& $this->current_proposal['import_data'];
		}

		$finish = $_POST['FINISH'];
		for ($i = 0; $i < count($finish); $i++) {
			while (list($key, $val) = each($finish[$i])) {
				if ($finish[$i][$key] != $item_data['FINISH'][$i][$key]) {
					$update = true;
					$item_data['FINISH'][$i][$key] = $finish[$i][$key];
				}
			}
		}

		$notes = $_POST['NOTES'];
		while (list($key, $val) = each($notes)) {
			if ($notes[$key] != $item_data['NOTES'][$key]['VALUE']) {
				$update = true;
				$item_data['NOTES'][$key]['VALUE'] = $notes[$key];
			}
		}
		$dimensions = $_POST['DIMENSIONS'];
		while (list($key, $val) = each($dimensions)) {
			if ($dimensions[$key] != $item_data['DIMENSIONS'][$key]) {
				$update = true;
				$item_data['DIMENSIONS'][$key] = $dimensions[$key];
			}
		}
		$panel = $_POST['PANEL'];
		if ($panel) {
			for ($i = 0; $i < count($proposal_import_data); $i++) {
				if ($proposal_import_data['PANEL_TYPE'][$i]['NAME'] == $this->lines->current_line['panel_id']) {
					while (list($key, $val) = each($panel)) {
						if (!is_array($val)) {
							if ($proposal_import_data['PANEL_TYPE'][$i][$key] != $val) {
								$panel_update = true;
								$proposal_import_data['PANEL_TYPE'][$i][$key] = $val;
							}
						}
					}
					if ($panel['ELEMENT']) {
						for ($j = 0; $j < count($panel['ELEMENT']); $j++) {
							while (list($key, $val) = each($panel['ELEMENT'][$j])) {
								if (!is_array($val)) {
									if ($proposal_import_data['PANEL_TYPE'][$i]['ELEMENT'][$j][$key] != $val) {
										$panel_update = true;
										$proposal_import_data['PANEL_TYPE'][$i]['ELEMENT'][$j][$key] = $val;
									}
								}
							}
							if ($panel['ELEMENT'][$j]['FINISH']) {
								for ($k = 0; $k < count($panel['ELEMENT'][$j]['FINISH']); $k++) {
									while (list($key2, $val2) = each($panel['ELEMENT'][$j]['FINISH'][$k])) {
										if ($proposal_import_data['PANEL_TYPE'][$i]['ELEMENT'][$j]['FINISH'][$k][$key2] != $val2) {
											$proposal_import_data['PANEL_TYPE'][$i]['ELEMENT'][$j]['FINISH'][$k][$key2] = $val2;
											$panel_update = true;
										}
									}
								}
							}
						}
					}
					break;
				}
			}
		}

		if ($update)
			$this->db->query("UPDATE `line_items`
							  SET `import_data` = '".addslashes(serialize($item_data))."'
							  WHERE `item_hash` = '$item_hash'");
		if ($panel_update)
			$this->db->query("UPDATE `proposals`
							  SET `import_data` = '".addslashes(serialize($proposal_import_data))."'
							  WHERE `proposal_hash` = '$proposal_hash'");


		$this->content['page_feedback'] = ($update || $panel_update ? "Item attributes have been saved." : "No changes have been made.");
		$this->content['action'] = "continue";
		return;
	}

	function view_details() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['proposal_hash'] && !$this->ajax_vars['item_hash'])
			return $this->__trigger_error("A valid proposal hash or item hash are missing within the request.", E_USER_ERROR, __FILE__, __LINE__, 1, 1);

		$this->lines = new line_items($this->ajax_vars['proposal_hash']);
		$this->lines->fetch_line_item_record($this->ajax_vars['item_hash']);
		$details = $this->lines->fetch_item_details($this->ajax_vars['item_hash']);

		$import_data = __unserialize(stripslashes($details['import_data']));

		if ($this->lines->current_line['panel_id'])
			$this->fetch_panel_type($this->ajax_vars['proposal_hash'], $this->lines->current_line['panel_id']);

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "View Item Details";

		$proposal_import_data = $this->fetch_import_data($this->ajax_vars['proposal_hash']);

		if (!defined('SPEC_ITEM_EDIT'))
			define('SPEC_ITEM_EDIT', 0);

		$tbl .=
		(SPEC_ITEM_EDIT == 1 ?
    		$this->form->form_tag().
    		$this->form->hidden(array("proposal_hash" => $this->lines->proposal_hash,
                            		  "item_hash" => $this->lines->item_hash)) : NULL)."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:10px 25px;\">
						<h3 style=\"margin-bottom:5px;color:#00477f;\">
							<div style=\"font-weight:bold;padding-bottom:5px;\">
								Item No: ".$details['item_no']."
							</div>".(SPEC_ITEM_EDIT == 1 ? "
							<div style=\"float:right;padding-right:25px;\">".$this->form->button("value=Save Attributes", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_save_finish');")."</div>" : NULL)."
							<div style=\"font-weight:bold;padding-bottom:5px;margin-left:9px;\">
								Vendor: ".$details['item_vendor'].($details['item_product'] ? " - ".$details['item_product'] : NULL)."
							</div>
						</h3>".($import_data['CATALOG_CODE'] ? "
						<div style=\"margin:10px 0;\">
							<fieldset style=\"padding-left:15px;\">
								<legend style=\"font-weight:bold;color:#00477f;\">Electronic Catalog Details</legend>
								<div style=\"padding:10px;\">
									<table style=\"background-color:#cccccc;width:500px;\" cellpadding=\"5\" cellspacing=\"1\">
										<tr>
											<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">Catalog Code:</td>
											<td style=\"padding:5px;background-color:#efefef;\">".$import_data['CATALOG_CODE']."</td>
										</tr>".($import_data['CATALOG_EFFECTIVE_DATE'] ? "
										<tr>
											<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">Effective Date:</td>
											<td style=\"padding:5px;background-color:#efefef;\">".$import_data['CATALOG_EFFECTIVE_DATE']."</td>
										</tr>" : NULL).($import_data['CATALOG_VERSION'] ? "
										<tr>
											<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">Version:</td>
											<td style=\"padding:5px;background-color:#efefef;\">".$import_data['CATALOG_VERSION']."</td>
										</tr>" : NULL).($proposal_import_data['SETUP']['NAME'] ? "
										<tr>
											<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">Project Name:</td>
											<td style=\"padding:5px;background-color:#efefef;\">".$proposal_import_data['SETUP']['NAME']."</td>
										</tr>" : NULL).($proposal_import_data['SETUP']['FILE_NAME'] ? "
										<tr>
											<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">File Name:</td>
											<td style=\"padding:5px;background-color:#efefef;\">".$proposal_import_data['SETUP']['FILE_NAME']."</td>
										</tr>" : NULL)."
									</table>
								</div>
							</fieldset>
						</div>" : NULL);
					if ($import_data['NOTES']) {
						for ($i = 0; $i < count($import_data['NOTES']); $i++) {
							if ($import_data['NOTES'][$i]['VALUE'])
								$note .= "
								<tr>
									<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">".str_replace("_", " ", strtoupper(substr($import_data['NOTES'][$i]['TYPE'], 0, 1)).strtolower(substr($import_data['NOTES'][$i]['TYPE'], 1))).":</td>
									<td style=\"padding:5px;background-color:#efefef;\">".(SPEC_ITEM_EDIT == 1 ?
										$this->form->text_box("name=NOTES[".$i."]",
                    										  "size=40",
                    										  "value=".htmlspecialchars(stripslashes($import_data['NOTES'][$i]['VALUE']))) : htmlspecialchars(stripslashes($import_data['NOTES'][$i]['VALUE'])))."
                                    </td>
								</tr>";
						}
						if ($note)
							$tbl .= "
							<div style=\"margin:10px 0;\">
								<fieldset style=\"padding-left:15px;\">
									<legend style=\"font-weight:bold;color:#00477f;\">Item Notes</legend>
									<div style=\"padding:10px;\">
										<table style=\"background-color:#cccccc;width:500px;\" cellpadding=\"5\" cellspacing=\"1\">
    										".$note."
    									</table>
									</div>
								</fieldset>
							</div>";
					}
					if ($import_data['FINISH']) {
						$tbl .= "
						<fieldset style=\"padding-left:15px;\">
							<legend style=\"font-weight:bold;color:#00477f;\">Finishes &amp; Options</legend>
							<div style=\"padding:10px;\">
								<table style=\"background-color:#cccccc;width:500px;\" cellpadding=\"5\" cellspacing=\"1\">";
								for ($i = 0; $i < count($import_data['FINISH']); $i++) {
									$tbl .= "
									<tr>
										<td colspan=\"2\" style=\"padding-left:5px;font-weight:bold;\">Finish/Option: ".($i + 1)."</td>
									</tr>";
									while (list($key, $val) = each($import_data['FINISH'][$i])) {
										$tbl .= "
										<tr>
											<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">".str_replace("_", " ", strtoupper(substr($key, 0, 1)).strtolower(substr($key, 1))).":</td>
											<td style=\"padding:5px;background-color:#efefef;\">".(SPEC_ITEM_EDIT == 1 ?
												$this->form->text_box("name=FINISH[".$i."][".$key."]",
                        											  "value=".htmlspecialchars(stripslashes($val))) : htmlspecialchars(stripslashes($val)))."
											</td>
										</tr>";
									}
								}
								$tbl .= "
								</table>
							</div>
						</fieldset>";
					}
					if ($this->panel_type) {
						$panel_id = $this->panel_type['NAME'];
						$tbl .= "
						<div style=\"margin-top:10px;\">
							<fieldset style=\"padding-left:15px;\">
								<legend style=\"font-weight:bold;color:#00477f;\">Panel Matrix:</legend>
								<div style=\"padding:10px;\">
									<table style=\"background-color:#cccccc;width:500px;\" cellpadding=\"5\" cellspacing=\"1\">
									<tr>
										<td colspan=\"2\" style=\"padding-left:5px;font-weight:bold;\">Panel Attributes:</td>
									</tr>";
								foreach ($this->panel_type as $key=>$val) {
									if (!is_array($val)) {
										$tbl .= "
										<tr>
											<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">".str_replace("_", " ", strtoupper(substr($key, 0, 1)).strtolower(substr($key, 1))).":</td>
											<td style=\"padding:5px;background-color:#efefef;\">".(SPEC_ITEM_EDIT == 1 && $key != 'NAME' ?
												$this->form->text_box("name=PANEL[".$key."]",
                       												  "value=".htmlspecialchars(stripslashes($val))) : htmlspecialchars(stripslashes($val)))."
                                            </td>
										</tr>";
									}
								}
								$tbl .= "
									</table>";
								if (is_array($this->panel_type['ELEMENT'])) {
									for ($i = 0; $i < count($this->panel_type['ELEMENT']); $i++) {
										$tbl .= "
										<div style=\"margin-top:10px;\">
											<fieldset style=\"padding-left:15px;width:90%;\">
												<legend style=\"padding-top:5px;font-weight:bold;color:#00477f;\">Element ".($i + 1)."</legend>
												<div style=\"padding:10px;\">
													<table style=\"background-color:#cccccc;width:500px;\" cellpadding=\"5\" cellspacing=\"1\">
														<tr>
															<td colspan=\"2\" style=\"padding-left:5px;font-weight:bold;\">Element Attributes:</td>
														</tr>";
													foreach ($this->panel_type['ELEMENT'][$i] as $key=>$val) {
														if (!is_array($val))
															$tbl .= "
															<tr>
																<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">".str_replace("_", " ", strtoupper(substr($key, 0, 1)).strtolower(substr($key, 1))).":</td>
																<td style=\"padding:5px;background-color:#efefef;\">".(SPEC_ITEM_EDIT == 1 ?
																	$this->form->text_box("name=PANEL[ELEMENT][".$i."][".$key."]",
                        																  "value=".htmlspecialchars(stripslashes($val))) : htmlspecialchars(stripslashes($val)))."
                                                                </td>
															</tr>";
													}
											$tbl .= "
													</table>";
													if (is_array($this->panel_type['ELEMENT'][$i]['FINISH'])) {
														$tbl .= "
														<div style=\"margin-top:10px;\">
															<fieldset style=\"padding-left:15px;width:75%;\">
																<legend style=\"padding-top:5px;font-weight:bold;color:#00477f;\">Element Finishes &amp; Options</legend>
																<div style=\"padding:10px;\">
																	<table style=\"background-color:#cccccc;width:90%;\" cellpadding=\"5\" cellspacing=\"1\">";

																	for ($j = 0; $j < count($this->panel_type['ELEMENT'][$i]['FINISH']); $j++) {
																		$tbl .= "
																		<tr>
																			<td colspan=\"2\" style=\"padding-left:5px;font-weight:bold;\">Finish/Option: ".($j + 1)."</td>
																		</tr>";
																		foreach ($this->panel_type['ELEMENT'][$i]['FINISH'][$j] as $key=>$val) {
																			$tbl .= "
																			<tr>
																				<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">".str_replace("_", " ", strtoupper(substr($key, 0, 1)).strtolower(substr($key, 1))).":</td>
																				<td style=\"padding:5px;background-color:#efefef;\">".(SPEC_ITEM_EDIT == 1 ?
																					$this->form->text_box("name=PANEL[ELEMENT][".$i."][FINISH][".$j."][".$key."]",
                          																				  "value=".htmlspecialchars(stripslashes($val))) : htmlspecialchars(stripslashes($val)))."
                                                                                </td>
																			</tr>";
																		}
																	}
																	$tbl .= "
																	</table>
																</div>
															</fieldset>
														</div>";
													}

										$tbl .= "
												</div>
											</fieldset>
										</div>";
									}
								}
							$tbl .= "
								</div>
							</fieldset>
						</div>";
					}
					if ($import_data['DIMENSIONS']) {
						$tbl .= "
						<div style=\"margin:10px 0;\">
							<fieldset style=\"padding-left:15px;\">
								<legend style=\"font-weight:bold;color:#00477f;\">Dimensions</legend>
								<div style=\"padding:10px;\">
									<table style=\"background-color:#cccccc;width:500px;\" cellpadding=\"5\" cellspacing=\"1\">";

								while (list($key, $val) = each($import_data['DIMENSIONS']))
									$tbl .= "
									<tr>
										<td style=\"text-align:right;padding:5px;background-color:#efefef;width:100px;font-weight:bold;\">".str_replace("_", " ", strtoupper(substr($key, 0, 1)).strtolower(substr($key, 1))).":</td>
										<td style=\"padding:5px;background-color:#efefef;\">".(SPEC_ITEM_EDIT == 1 ?
											$this->form->text_box("name=DIMENSIONS[".$key."]",
                    											  "value=".htmlspecialchars(stripslashes($val))) : htmlspecialchars(stripslashes($val)))."
                                        </td>
									</tr>";

							$tbl .= "
									</table>
								</div>
							</fieldset>
						</div>";
					}
					$tbl .= "
					</td>
				</tr>
			</table>
		</div>".
        (SPEC_ITEM_EDIT == 1 ?
			$this->form->close_form() : NULL);

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function edit_group() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['proposal_hash'])
			return $this->error_template("We were unable to identify the proposal you are currently working in. Please exit the current screen and re enter into the proposal. We're sorry for the inconvenience.");

		$this->fetch_master_record($this->ajax_vars['proposal_hash']);
		$punch = $this->ajax_vars['punch'];
		$this->lines = new line_items($this->proposal_hash, $punch);
		if ($this->ajax_vars['group_hash']) {
			$valid = $this->lines->fetch_line_group_record($this->ajax_vars['group_hash']);

			if ($valid === false)
				unset($this->ajax_vars['group_hash']);
		}

		$this->lines->fetch_line_items(0, $this->lines->total);
		for ($i = 0; $i < $this->lines->total; $i++) {
			$lines_in[] = $this->lines->line_info[$i]['item_hash'];
			$lines_out[] = (strlen($this->lines->line_info[$i]['item_descr']) + strlen($this->lines->line_info[$i]['vendor_name']) > 30 ?
				strtoupper($this->lines->line_info[$i]['vendor_name'])." - ".substr($this->lines->line_info[$i]['item_descr'], 0, 25)."..." : strtoupper($this->lines->line_info[$i]['vendor_name'])." - ".$this->lines->line_info[$i]['item_descr']);
		}

		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `line_items`
									WHERE `proposal_hash` = '$this->proposal_hash' AND `group_hash` = '".$this->lines->group_hash."' AND `punch` = ".($punch ? 0 : 1));
		if ($this->db->result($result) > 0)
			$onclick_alert = "This group is being used in the ".($punch ? "item details" : "punchlist")." section of this proposal. If this group is removed, it will also be removed from the ".($punch ? "item details" : "punchlist")." section. Do you wish to proceed?";

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Group" : "Create A New Group");

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("group_hash" => $this->lines->group_hash, "limit" => $this->ajax_vars['limit'], "proposal_hash" => $this->proposal_hash, "popup_id" => $this->popup_id, "punch" => $punch))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:10px 25px;\">
						<table >
							<tr>
								<td>
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1".$this->popup_id."\">Group Description: </div>
									".$this->form->text_area("name=group_descr", "value=".$this->lines->current_group['group_descr'], "rows=5", "cols=45")."
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">".($lines_out ? "
									<div style=\"padding-bottom:5px;font-weight:bold;\" >Add these line items to the new group: </div>
									".$this->form->select("group_items[]", $lines_out, $this->lines->current_group['group_items'], $lines_in, "multiple", "size=5", "style=width:300px;", "blank=1") : NULL)."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;\">
									<div style=\"text-align:left;padding:15px;\">
										".$this->form->button("value=Save", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_group', 'notag=1');")."
										&nbsp;&nbsp;".($valid ?
										$this->form->button("value=Delete Group", "onClick=".($onclick_alert ? "if(confirm('".$onclick_alert."')){submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_group', 'delete=1', 'notag=1');}" : "submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_group', 'delete=1', 'notag=1');")) : NULL)."
									</div>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">".($this->lines->proposal_groups['group_descr'] ? "
									<div style=\"padding-bottom:5px;font-weight:bold;\" >Groups stored within this proposal: </div>
									".$this->form->select("current_groups", $this->lines->proposal_groups['group_descr'], $this->lines->group_hash, $this->lines->proposal_groups['group_hash'], "onChange=agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_group', 'proposal_hash=".$this->proposal_hash."', 'group_hash='+this.options[this.selectedIndex].value, 'popup_id=line_group', 'notag=1', 'punch=$punch');")."
									<small><i>Select group to edit/delete</i></small>" : NULL)."
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function import_export($import_hash=NULL) {

		if ( $this->ajax_vars['popup_id'] )
			$this->popup_id = $this->ajax_vars['popup_id'];

		if ( $this->ajax_vars['proposal_hash'] ) {

			if ( ! $this->fetch_master_record($this->ajax_vars['proposal_hash']) )
    			return $this->__trigger_error("System error encountered when attempting to lookup proposal record. Please reload window and try again. <!-- Tried fetching proposal [ {$this->ajax_vars['proposal_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
		}

		$this->lines = new line_items($this->proposal_hash, $this->ajax_vars['punch']);

		$action = $this->ajax_vars['action'];
		$import_error = $this->ajax_vars['import_error'];

		if ( ! $import_hash ) {

			$this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : 'popup_' . rand(10, 1000) );
			$this->popup_id = $this->content['popup_controls']['popup_id'];
			$this->content['popup_controls']['popup_title'] = ($action == "import" ? "Import Items Into Proposal" : "Export Items From Proposal");
		}

		$import_pref = fetch_user_data("proposal_import");

		$tbl_head =
		$this->form->form_tag() .
		$this->form->hidden( array(
    		"proposal_hash"   =>  $this->proposal_hash,
    		"action"          =>  $action,
    		"punch"           =>  $this->lines->punch,
    		"popup_id"        =>  $this->popup_id,
    		"import_hash"     =>  $import_hash
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<div id=\"import_confirm_holder\">";
				$tbl = "
				<ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">
					<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent1{$this->popup_id}\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\">Select Import File</a></li>
					<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent2{$this->popup_id}\" rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\">Import Preview</a></li>
					<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent3{$this->popup_id}\" rel=\"tcontent3{$this->popup_id}\" onClick=\"expandcontent(this);\">Existing Line Items</a></li>
				</ul>
				<div id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\">
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;\" class=\"popup_tab_table\">
						<tr>
							<td style=\"background-color:#ffffff;padding:15px 20px;\">" .
								( $import_hash ?
									"<div style=\"padding:10px 5px;margin-bottom:10px;background-color:#cccccc;border:1px solid black;\">" .
	    							( $this->ajax_vars['method'] == 'proposal' ?
										"Importing From Proposal {$this->ajax_vars['proposal_import_no']}" : "Current Import File: " . basename($this->xmlPar->xmlFile['name'])
	    							) . "
									</div>" : NULL
	    						) . "
								<table >
									<tr>
										<td>
											<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1{$this->popup_id}\">Import From: </div>" .
											$this->form->select(
												"import_from",
                                				array(
                                    				"OFDA XML (version 2 or higher)",
                                                    "ExpressXML (DesignExpress)",
                                                    "SIF",
                                                    "ProjectMatrix SIF (Custom SIF)",
                                                    "Another Proposal"
                                				),
                                                ( $import_hash && $this->ajax_vars['method'] == 'proposal' ?
	                                                "PR" :
	                                                ( $_POST['import_from'] ?
		                                                $_POST['import_from'] : $import_pref
		                                            )
		                                        ),
                                                array(
                                                    "OFDAXML",
                                                    "EXPRESSXML",
                                                    "SIF",
                                                    "PRJMTRX",
                                                    "PR"
                                                ),
												"blank=1",
												"onChange=if(this.options[this.selectedIndex].value=='PR'){toggle_display('import_file_box{$this->popup_id}', 'none');toggle_display('proposal_search{$this->popup_id}', 'block');}else{toggle_display('import_file_box{$this->popup_id}', 'block');toggle_display('proposal_search{$this->popup_id}', 'none');}"
                                            )."
										</td>
									</tr>
									<tr>
										<td >
											<div id=\"import_file_box{$this->popup_id}\" style=\"display:" . ( $import_hash && $this->ajax_vars['method'] == 'proposal' ? "none" : "block" ) . ";\">" .
											upload_form(
												$this->form,
												array(
													'popup_id'		=>	$this->popup_id,
													'label'			=>	'Import File: ',
													'url'			=>	"upload.php?proposal_hash={$this->proposal_hash}&class=proposals&action=doit_import_export&method_action=$action&current_hash={$this->current_hash}&popup_id={$this->popup_id}",
													'file_name'		=>	$this->xmlPar->xmlFile['name'],
													'file_type'		=>	$this->xmlPar->xmlFile['type'],
													'file_size'		=>	$this->xmlPar->xmlFile['size'],
													'file_error'	=>	$this->xmlPar->xmlFile['error']
												)
											) . "
											</div>
											<div id=\"proposal_search{$this->popup_id}\" style=\"display:" . ( $import_hash && $this->ajax_vars['method'] == 'proposal' ? "block" : "none" ) . "\">
												<div style=\"padding-top:5px;padding-bottom:5px;font-weight:bold;\" id=\"err2a{$this->popup_id}\">Proposal Number: </div>" .
												$this->form->text_box(
    												"name=import_proposal_no",
													"value={$this->ajax_vars['proposal_import_no']}",
													"autocomplete=off",
													"size=30",
													"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'import_proposal_no', 'import_proposal_hash', 1);}",
                                                    "onKeyUp=if(ka==false && this.value){key_call('proposals', 'import_proposal_no', 'import_proposal_hash', 1);}",
    												"onBlur=key_clear();",
													"onKeyDown=clear_values('import_proposal_hash');"
												) .
												$this->form->hidden( array(
    												"import_proposal_hash" => $this->ajax_vars['proposal_import_hash']
												) ) . "
												<span style=\"padding-left:5px;\">" .
													$this->form->button(
    													"value=Next",
    													"onClick=if(\$F('import_proposal_hash')){submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_import_export', 'method=proposal');}else{return alert('Please select a valid proposal.');}"
													) . "
												</span>
												<div style=\"margin-top:5px;\">" .
												    $this->form->checkbox(
    												    "name=import_pricing",
                        								"value=1",
												        ( $_POST['import_pricing'] ? "checked" : NULL )
												    ) . "&nbsp;Import Pricing?
												</div>
											</div>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>
				<div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">";

				if ( $import_hash ) {

					if ( $this->ajax_vars['method'] == 'proposal' ) {

						$tmp_proposal = new proposals($this->current_hash);
						$tmp_valid = $tmp_proposal->fetch_master_record($this->ajax_vars['proposal_import_hash']);

						$item_tbl .=
						$this->form->hidden( array(
    						"method"                  =>  "proposal",
    						"proposal_import_hash"    =>  $this->ajax_vars['proposal_import_hash']
						) ) . "
						<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td style=\"width:10px;vertical-align:bottom;\">" .
            						$this->form->checkbox(
                						"name=check_all",
                						"value=1",
                						'checked',
                						"onClick=checkall(document.getElementsByName('import_lines[]'), this.checked);"
            						) . "
            					</td>
								<td></td>
								<td style=\"width:60px;vertical-align:bottom;\">Qty</td>
								<td style=\"width:150px;vertical-align:bottom;\">Vendor &amp; Item</td>
								<td style=\"vertical-align:bottom;\">Item Descr.</td>
							</tr>";

						$tmp_lines = new line_items($tmp_proposal->proposal_hash);
						$tmp_lines->fetch_line_items(0, $tmp_lines->total);

						$border = "border-bottom:1px solid #cccccc;";
						$_total = $tmp_lines->total;

						for ( $i = 0; $i < $tmp_lines->total; $i++ ) {

							if ( $i >= $_total - 1 )
								unset($border);

							if ( ! $tmp_lines->line_info[$i]['item_code'] ) {

								if ( ( $tmp_lines->line_info[$i]['group_hash'] && $tmp_lines->line_info[$i]['group_hash'] != $tmp_lines->line_info[ $i - 1 ]['group_hash'] ) || ( ! $tmp_lines->line_info[$i]['group_hash'] && $tmp_lines->line_info[ $i - 1 ]['group_hash'] ) ) {

									$item_tbl .= "
									<tr style=\"background-color:#efefef;width:100%;\">
										<td colspan=\"5\" style=\"border-bottom:1px solid #cccccc;font-weight:bold;padding-top:15px;\">" .
										( ! $tmp_lines->line_info[$i]['group_hash'] && $tmp_lines->line_info[ $i - 1 ]['group_hash'] ?
											"<span style=\"margin-left:30px;\">Ungrouped Items</span>"
											:
											$this->form->checkbox(
    											"name=import_groups[]",
    											"value={$tmp_lines->line_info[$i]['group_hash']}",
    											'checked'
    										) . "
											&nbsp;
											Group: " . nl2br($tmp_lines->line_info[$i]['group_descr'])
										) . "
										</td>
									</tr>";
								}

								$new_item['item_descr'] = mb_convert_encoding($item_data['DESCR'], 'HTML-ENTITIES');
								$new_item['item_descr'] = addslashes(str_replace("&Acirc;", "", $new_item['item_descr']));

								$item_tbl .= "
								<tr>
									<td " . ( $border ? "style=\"$border\"" : NULL ) . ">&nbsp;</td>
									<td style=\"vertical-align:bottom;$border\">" .
        								$this->form->checkbox(
            								'name=import_lines[]',
            								"value={$tmp_lines->line_info[$i]['item_hash']}",
            								'checked',
            								"title=Check whether or not to import this item."
        								) . "
        							</td>
									<td style=\"vertical-align:bottom;$border\">{$tmp_lines->line_info[$i]['qty']}</td>
									<td style=\"vertical-align:bottom;$border\">
										<div style=\"padding-bottom:3px;font-style:italic;\">" . stripslashes($tmp_lines->line_info[$i]['vendor_name']) . "</div>
										<div>{$tmp_lines->line_info[$i]['item_no']}</div>
									</td>
									<td style=\"vertical-align:bottom;$border\">" .
									( strlen($tmp_lines->line_info[$i]['item_descr']) > 70 ?
										wordwrap( substr( mb_convert_encoding($tmp_lines->line_info[$i]['item_descr'], 'HTML_ENTITIES'), 0, 65), 35, "<br />", true) . "..."
										:
										( strlen($tmp_lines->line_info[$i]['item_descr']) > 30 ?
											wordwrap( mb_convert_encoding($tmp_lines->line_info[$i]['item_descr'], 'HTML-ENTITIES'), 30, "<br />", true) : nl2br( mb_convert_encoding($tmp_lines->line_info[$i]['item_descr'], 'HTML-ENTITIES'))
										)
									) . "
									</td>
								</tr>";

							} else {

								$_total--;
							}
						}

						if ( ! $tmp_lines->total )
							$item_tbl .= "
							<tr >
								<td colspan=\"5\" style=\"margin-left:30px;\">The proposal you selected has no line items. Please select a different proposal and try again.</td>
							</tr>";

						$item_tbl .= "
						</table>";

					} else {

						$this->xmlPar->load_refs();

						for ( $i = 0; $i < count($this->xmlPar->project['ITEM']); $i++ ) {

							if ( trim($this->xmlPar->project['ITEM'][$i]['SPEC_GROUP']) ) {

								$group_data[ $this->xmlPar->project['ITEM'][$i]['SPEC_GROUP'] ][] =& $this->xmlPar->project['ITEM'][$i];
								$num_grps++;
							} else
								$group_data['DEALERCHOICE_DEFAULT'][] =& $this->xmlPar->project['ITEM'][$i];;
						}

						$item_tbl .= "
						<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\">
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td style=\"width:10px;vertical-align:bottom;\">" .
            						$this->form->checkbox(
                						"name=check_all",
                						"value=1",
                						'checked',
                						"onClick=checkall(document.getElementsByName('import_lines[]'), this.checked);"
                					) . "
                				</td>
								<td></td>
								<td style=\"width:60px;vertical-align:bottom;\">Qty</td>
								<td style=\"width:150px;vertical-align:bottom;\">Vendor &amp; Item</td>
								<td style=\"vertical-align:bottom;\">Item Descr.</td>
							</tr>";

						$i = 0;
						if ( $group_data ) {

							while ( list($group_name, $group_items) = each($group_data) ) {

								if ( $group_name != 'DEALERCHOICE_DEFAULT' ) {

									$item_tbl .= "
									<tr style=\"background-color:#efefef;width:100%;\">
										<td class=\"smallfont\" colspan=\"5\" style=\"font-weight:bold;padding-top:15px;border-bottom:1px solid #cccccc;\">" .
											$this->form->checkbox(
    											"name=import_groups[]",
    											"value=group_{$i}",
    											'checked'
											) . "
											&nbsp;
											Group: " . nl2br($group_name) . "
										</td>
									</tr>";
								}

								$border = "border-bottom:1px solid #cccccc;";
								for ( $j = 0; $j < count($group_items); $j++ ) {

									$item_data =& $group_items[$j];

									if ( $i >= count($group_data) - 1 && ! $group_items[ $j + 1 ] )
										unset($border);

									if ( $item_data['CATALOG_CODE'] ) {

										if ( $item_data['CATALOG_CODE'] == 'OF1' && strlen($item_data['PN']) > 3 ) { # OFUSA specific catalog code extraction

                                            $item_data['CATALOG_CODE'] = substr($item_data['PN'], 0, 3);
											$item_data['PN'] = substr($item_data['PN'], 3);
										}

										$vendor_info = vendors::fetch_from_cat_code($item_data['CATALOG_CODE']);
									}

								    if ( count($vendor_info) > 1 ) {

										$multi = true;
										unset($v_in, $v_out);
										for ( $k = 0; $k < count($vendor_info); $k++ ) {

											if ( $vendor_info[$k]['vendor_hash'] && $vendor_info[$k]['vendor_name'] ) {

												$v_in[] = "{$vendor_info[$k]['vendor_hash']}|{$vendor_info[$k]['product_hash']}";
												$v_out[] = "{$vendor_info[$k]['vendor_name']} : {$vendor_info[$k]['product_name']}";
											}
										}

										if ( is_array($v_in) && is_array($v_out) ) {

											$vendor_select = $this->form->select(
    											"select_vendor{$i}_{$j}",
												$v_out,
												'',
												$v_in,
												'blank=true',
												( ! $first ? "onChange=for(var i=0;i<this.form.elements.length;i++){if(this.form.elements[i].name.substring(0, 13) == 'select_vendor'){this.form.elements[i].value=this.options[this.selectedIndex].value}}" : NULL ),
												"style=width:200px;"
    										);
										} else
											$vendor_select = "<img src=\"images/alert.gif\">&nbsp;No vendor match!";

										$first = true;

									} else {

										unset($vendor_select);
										$vendor_info = $vendor_info[0];
									}

									# $descr = mb_convert_encoding($item_data['DESCR'], 'HTML-ENTITIES'); # Disable per Trac #1666

									$item_tbl .= "
									<tr>
										<td " . ( $border ? "style=\"$border\"" : NULL ) . ">&nbsp;</td>
										<td style=\"vertical-align:bottom;$border\">" .
        									$this->form->checkbox(
            									'name=import_lines[]',
            									"value={$i}_{$j}",
            									'checked',
            									"title=Check whether or not to import this item."
        									) . "
        								</td>
										<td style=\"vertical-align:bottom;$border\">{$item_data['QTY']}</td>
										<td style=\"vertical-align:bottom;$border\">
											<div style=\"padding-bottom:3px;font-style:italic;\">" .
											( $vendor_select ?
												$vendor_select :
												( $vendor_info ?
													"{$vendor_info['vendor_name']} : {$vendor_info['product_name']}" : "<i>Unknown Vendor</i>"
												)
											) . "
											</div>
											<div " . ( $vendor_select ? "style=\"color:#ff0000;\"" : NULL ) . ">{$item_data['PN']}</div>
										</td>
										<td style=\"vertical-align:bottom;$border\">" .
                                        ( strlen($item_data['DESCR']) > 70 ?
                                            wordwrap( substr($item_data['DESCR'], 0, 65), 35, "<br />", true) . "..."
                                            :
                                            ( strlen($item_data['DESCR']) > 30 ?
                                                wordwrap($item_data['DESCR'], 30, "<br />", true) : nl2br($item_data['DESCR'])
                                            )
                                        ) . "
										</td>
									</tr>";
								}

								$i++;
							}

						} else {

							$item_tbl .= "
							<tr>
								<td style=\"margin-left:30px;background-color:#cccccc;\" colspan=\"5\">Your import file appears to be empty!</td>
							</tr>";
						}

						$item_tbl .= "
						</table>";
					}
				}

				$tbl .= "
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;\" class=\"popup_tab_table\">
						<tr>
							<td style=\"background-color:#ffffff;padding:15px 20px;\" >
								<div style=\"padding:0 10px 10px 10px;\">" .
                				( $import_hash ?
                    				( $this->ajax_vars['method'] == 'proposal' ?
    									"The below list represents line items found in the proposal to be imported. All itmes are checked by default.
										To import only selected items into your proposal, check those items. Unchecked items WILL NOT be imported.
										<br /><br />
										To prevent items from being grouped as shown, simply uncheck the group and that group will not be imported."
                        				:
										"The below list represents items found in your import file. All itmes are checked by default.
										To import only selected items into your proposal, check those items. Unchecked items WILL NOT be imported.
										<br /><br />
										To prevent items from being grouped as shown, simply uncheck the group and that group will not be imported." .
                        				( $multi ?
											"<div style=\"font-weight:bold;color:#ff0000;padding-top:5px;\">
												One or more of your imported line items has either been matched to more than one vendor or cannot be matched against any vendor.
												If given the option, please select the correct vendor for those lines with item numbers highlighted in red.
											</div>" : NULL
                        				)
                        			)
                        			:
									"After selecting an import file, the items contained in that file will be shown here for you to preview. You may selectively choose which items to import."
								) . "
								</div>
								$item_tbl
							</td>
						</tr>
					</table>
				</div>
				<div id=\"tcontent3{$this->popup_id}\" class=\"tabcontent\">
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;\" class=\"popup_tab_table\">
						<tr>
							<td style=\"background-color:#ffffff;padding:15px 20px;\" >
								<div style=\"padding:0 10px 10px 10px;\">" .
     								( $import_hash ?
										"The below list represents line items currently found in your proposal. The line items shown are those contained
										in your proposal PRIOR to your import. By checking the line items below you are indicating that you would like to
										overwrite that item during your import.
										<br /><br />
										To preserve line items during your import, simply leave them unchecked."
	     								:
										"After importing your file you will have the option to indicate which of your existing line items you would like to overwrite, and which you like to keep. You must first select your import file."
									) . "
								</div>";

							if ( $import_hash ) {

								$this->lines->fetch_line_items(0, $this->lines->total);

								$tbl .= "
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" class=\"table_list\" style=\"width:100%;\">
									<tr class=\"thead\" style=\"font-weight:bold;\">
										<td style=\"width:10px;vertical-align:bottom;\">" .
            								$this->form->checkbox(
                								"name=check_all",
                								"value=1",
                								"onClick=checkall(document.getElementsByName('replace_lines[]'), this.checked);"
            								) . "
            							</td>
										<td style=\"width:60px;vertical-align:bottom;\">Qty</td>
										<td style=\"width:150px;vertical-align:bottom;\">Vendor &amp; Item</td>
										<td style=\"vertical-align:bottom;\">Item Descr.</td>
									</tr>";

								for ( $i = 0; $i < $this->lines->total; $i++ ) {

									if ( ! $this->lines->line_info[$i]['status'] )
										$line_info[] =& $this->lines->line_info[$i];
								}

								for ( $i = 0; $i < count($line_info); $i++ ) {

									$tbl .= "
									<tr >
										<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\">" .
        									$this->form->checkbox(
            									"name=replace_lines[]",
            									"value={$line_info[$i]['item_hash']}",
            									"title=Check this box to overwrite this line item upon file import"
        									) . "
        								</td>
										<td class=\"smallfont\" style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;text-align:left;\">" .
											strrev( substr( strstr( strrev($line_info[$i]['qty']), '.'), 1) ) .
											( str_replace('0', '', substr( strrchr($line_info[$i]['qty'], '.'), 1) ) ?
												str_replace('0', '', strrchr($line_info[$i]['qty'], '.') ) : NULL
											) . "
										</td>
										<td class=\"smallfont\" style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\">
											<div style=\"padding-bottom:3px;font-style:italic;\">
												{$line_info[$i]['vendor_name']}" .
												( $line_info[$i]['product_name'] ?
													" : {$line_info[$i]['product_name']}" : NULL
												) . "
											</div>
											<div>{$line_info[$i]['item_no']}</div>
										</td>
										<td class=\"smallfont\" style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;\" >" .
	                                        ( strlen($line_info[$i]['item_descr']) > 100 ?
												nl2br( substr($line_info[$i]['item_descr'], 0, 100) ) . "..." : nl2br( $line_info[$i]['item_descr'] )
											) . "&nbsp;
										</td>
									</tr>";
								}

								if ( ! $line_info )
									$tbl .= "
									<tr >
										<td></td>
										<td class=\"smallfont\" colspan=\"4\">There are either no line items in your proposal, or none that are available for delete.</td>
									</tr>";

								$tbl .= "
								</table>";
							}

							$tbl .= "
							</td>
						</tr>
					</table>
				</div>" .
                ( $import_hash ?
					"<div style=\"text-align:left;padding:15px;\">" .
						$this->form->button(
	    					"value=Complete Import",
	    					"onClick=if(getCheckedItems('import_lines[]').length > 0){this.disabled=1;submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_import_export', 'finish=1');}else{alert('You need to select at least 1 line in order to complete the import!');}"
						) . "
					</div>" : NULL
				);

			$tbl_foot = "
			</div>
		</div>" .
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')', 10);";

		if ( $import_hash )
			return $tbl;

		$this->content['popup_controls']["cmdTable"] = $tbl_head . $tbl . $tbl_foot;
		$this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_tcontent1{$this->popup_id}\'))', 100)";

		return;
	}

	/**
	 * Allows a user to export line items from a proposal either as an excel file or a vendor specific electronic order format.
	 *
	 */
	function export() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['proposal_hash'] && !$this->proposal_hash)
			return;

		$punch = $this->ajax_vars['punch'];
		$this->fetch_master_record($this->proposal_hash ? $this->proposal_hash : $this->ajax_vars['proposal_hash']);
		$this->lines = new line_items($this->proposal_hash, $punch);

		// Get current line items

		$this->lines->fetch_line_items(0, $this->lines->total);
		$vendor_hashess = array();

		for ($i = 0; $i < $this->lines->total; $i++) {
			$vendor_hashes[] = $this->lines->line_info[$i]['vendor_hash'];
		}
		//Remove all duplicate vendor hashes
		$vendor_hashes = array_unique($vendor_hashes);
		$vendors = new vendors();
		$export_options = array("Export all items to spreadsheet");
		$export_options_key = array("xls");
		unset($efile_obj, $user_input);

		foreach ($vendor_hashes as $vendor_hash) {
			$vendors->fetch_master_record($vendor_hash);
			if ($vendors->current_vendor['eorder_file'] && file_exists(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file'])) {
				$export_options[] = "Export all items to electronic format for ".$vendors->current_vendor['vendor_name'];
				$export_options_key[] = $vendor_hash;
				include_once(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file']);
				$efile_class = explode(".", $vendors->current_vendor['eorder_file']);
				$efile_class_name = "eorder_".$efile_class[1];
				$efile_obj[$vendors->current_vendor['vendor_hash']] = new $efile_class_name($vendors->current_vendor['vendor_hash'], $this->proposal_hash);
				$user_input[$vendors->current_vendor['vendor_hash']] = $efile_obj[$vendors->current_vendor['vendor_hash']]->user_input($vendors->current_vendor['vendor_hash'], $this);

			}
		}

		$export_error = $this->ajax_vars['export_error'];
		$this->content['popup_controls']['popup_id'] = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : 'popup_'.rand(99, 99999));
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Export Items From Proposal";

		$user_input_tbl = $toggle_display = "";
		foreach($user_input as $vhash => $value) {
			$user_input_tbl .= "
			<div id=\"user_input_".$vhash."\" style=\"display:none\">".$value."</div>";
			$toggle_display .= "toggle_display('user_input_$vhash', 'none');";
		}
		$tbl_head = $this->form->form_tag().
		$this->form->hidden(array("proposal_hash" => $this->proposal_hash, "action" => $action, "punch" => $punch, "popup_id" => $this->popup_id, "export_hash" => $export_hash))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<div id=\"import_confirm_holder\">";
				$tbl = "
				<div>
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;\" class=\"popup_tab_table\">
						<tr>
							<td style=\"background-color:#ffffff;padding:15px 20px;\">
								<table >
									<tr>
										<td>
											<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1".$this->popup_id."\">Export Line Items As: </div>
											".$this->form->select("export_to", $export_options, ($_POST['export_to'] ? $_POST['export_to'] : $export_pref), $export_options_key, "blank=1", "onChange=if(\$('user_input_'+this.options[this.selectedIndex].value)){".$toggle_display."toggle_display('user_input_'+this.options[this.selectedIndex].value, 'block');toggle_display('input_holder', 'block');}else{toggle_display('input_holder', 'none');}")."
										</td>
									</tr>
									<tr id=\"input_holder\" style=\"display:block\">
										<td >".$user_input_tbl."</td>
									</tr>
									<tr>
										<td>".
											$this->form->button("value=Export", "id=export_btn", "onClick=if(\$('export_to').options[\$('export_to').selectedIndex].value=='xls'){agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'export_it', 'popup_id=export_line_items', 'proposal_hash=".$this->proposal_hash."', 'export_type=xls', 'punch=$punch');
																							}else{submit_form(\$('popup_id').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_export', 'vhash='+\$('export_to').options[\$('export_to').selectedIndex].value, 'punch=$punch')}")."
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>";
			$tbl_foot = "
			</div>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl_head.$tbl.$tbl_foot;
		return;
	}
	/**
	 * Processes the export function of line items for a specific vendor.  Creates the files in the tmp directory.
	 *
	 */
	function doit_export() {
		$vendor_hash = $_POST['vhash'];
		$proposal_hash = $_POST['proposal_hash'];
		$vendors = new vendors();
		$vendors->fetch_master_record($vendor_hash);

		if (!file_exists(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file'])) {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'][] = "The E-Order template can not be found. Please make sure your vendor has a valid DealerChoice E-Order template file attached saved within the vendor profile.";
			$this->content['submit_btn'] = "export_btn";
			return;
		}
		include_once(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file']);
		$efile_class = explode(".", $vendors->current_vendor['eorder_file']);
		$efile_class_name = "eorder_".$efile_class[1];
		$efile_obj = new $efile_class_name($vendor_hash, $proposal_hash);

		// Validate user input from efile obj if method exists, otherwise validate against the mandatory fields
		if (method_exists($efile_obj, "validate_user_input")) {
			$user_error = $efile_obj->validate_user_input($_POST["einput_".$vendor_hash]);

				if ($user_error == false) {
					while (list($field, $on) = each($efile_obj->user_error))
						$this->content['form_return']['err']["err_".$vendor_hash."_".$field] = 1;

					$this->content['error'] = 1;
					if ($efile_obj->error_message)
						$feedback = $efile_obj->error_message;
				}
		}
		if ($this->content['error']) {
			$this->content['error'] = 1;
			$this->content['submit_btn'] = "export_btn";
			$this->content['form_return']['feedback'] = ($feedback ? $feedback : "You left some required fields blank within the e-order input! Please check the indicated fields below and try again.");
			return;
		}
		$lines = new line_items($proposal_hash);
		$item_array = $lines->fetch_line_item_array($vendor_hash);
		if (count($item_array) == 0) {
			$this->content['error'] = 1;
			$this->content['submit_btn'] = "export_btn";
			$this->content['form_return']['feedback'] = "You have already ordered all the product for this vendor.  Please see the purchase order record or file vault if you wish to download the electronic purchase order.";
		}
		$ship_to_hash = $_POST['ship_to_hash_'.$po_hash[$i]];
		$no_items = $_POST['no_items_'.$po_hash[$i]];
		$vendor_info = $_POST['vendor_info_'.$po_hash[$i]];
		$order_total = $_POST['order_total_'.$po_hash[$i]];
		$total_list = $_POST['total_list_'.$po_hash[$i]];
		$total_sell = $_POST['total_sell_'.$po_hash[$i]];
		$vendor_deposit = $_POST['vendor_deposit_'.$po_hash[$i]];
		$transmit_type = $_POST['transmit_type_'.$po_hash[$i]];

		$eorder_args = array();
		$eorder_args['po_date'] = date("Y-m-d");
		$einput = $_POST["einput_".$vendor_hash];
		reset($einput);
		while (list($field, $val) = each($einput))
			$eorder_args[$field] = $val;

		$eorder_args['total_list'] = $total_list;
		$eorder_args['total_sell'] = $total_sell;
		$eorder_args['total_cost'] = $order_total;
		reset ($eorder_args);
		// Build
		$efile_obj->build_header($eorder_args, $item_array);
		list($eorder_file_name, $format) = $efile_obj->output(true, "_line_items");
		for ($j = 0; $j < count($format); $j++) {
			$eorder_format[] = $format[$j];
			$eorder_title[] = $vendors->current_vendor['vendor_name']."_line_items.".$format[$j];
		}

		$this->content['action'] = 'close';
		//$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purchase_orders', 'otf=1', 'proposal_hash=".$this->proposals->proposal_hash."', 'limit=".$limit."');");
		$this->content['jscript'][] = "agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'export_it', 'proposal_hash=".$proposal_hash."', 'eorder_title=".implode("|", $eorder_title)."', 'eorder_file_name=".addslashes(implode("|", $eorder_file_name))."', 'eorder_format=".implode("|", $eorder_format)."', 'export_type=eorder', 'popup_id=edownload');";

	}
	/**
	 * Display a download iframe that allows the user to right click on each generated electronic file and save it.
	 *
	 */
	function export_it() {
		$this->popup_id = $this->content['popup_controls']['popup_id'] = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : $_POST['popup_id']);
		$this->content['popup_controls']['popup_title'] = "Export Line Items to Spreadsheet";
		$proposal_hash = $_POST['proposal_hash'];
		$export_type = $this->ajax_vars['export_type'];
		$punch = $this->ajax_vars['punch'];
		$eorder_title = explode("|", $this->ajax_vars['eorder_title']);
		$eorder_file_name = explode("|", $this->ajax_vars['eorder_file_name']);
		$eorder_format = explode("|", stripslashes($this->ajax_vars['eorder_format']));

		switch($export_type) {
			case 'xls':
				$tbl .= "
				<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;text-align:left;\">
					<div style=\"margin-bottom:5px;margin-left:10px;\">
					<br> Right click on the link below, click 'Save Target As' and select a location on your computer.<br><br> &nbsp;
						<img src=\"images/excel.gif\">&nbsp;&nbsp;<a href=\"download.php?cvsh=".$proposal_hash."&punch=$punch\"  target=\"_blank\" class=\"link_standard\">Save Line Items</a>
					</div>
				</div>"; break;
			case 'eorder':
				$tbl .= "
				<div style=\"background-color:#ffffff;width:100%;height:100%;margin:0;\">
					<div style=\"margin:20px 15px;\">
					    The following Electronic file(s) were created.  <br /><br />
					    <b>Please note that these are not purchase orders and should be used for pricing only!  Any items already ordered will not appear in these files.</b>
						<br /><br />
						Right click on the link below, click 'Save Target As' and select a location on your computer.
						<div style=\"margin-top:15px;\">";
						for ($i = 0; $i < count($eorder_title); $i++) {
							$tbl .= "
							<div style=\"margin-bottom:5px;margin-left:10px;\">
								<img src=\"images/save_proposal.gif\" />
								&nbsp;
								<a href=\"download.php?elihash=".$proposal_hash."&f=".$eorder_format[$i]."&n=".base64_encode($eorder_file_name[$i])."&t=".base64_encode($eorder_title[$i])."\" target=\"_blank\" class=\"link_standard\">".$eorder_title[$i].
									(count($eorder_format) > 1 ? " (".$eorder_format[$i]." file)" : NULL) ."</a>
							</div>";
						}
						$tbl .= "
						</div>
					</div>
				</div>"; break;

		}

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}

	function edit_comment() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if (!$this->ajax_vars['proposal_hash'])
			return;

		$this->fetch_master_record($this->ajax_vars['proposal_hash']);

		$this->lines = new line_items($this->proposal_hash);
		if ($this->ajax_vars['comment_hash']) {
			$valid = $this->lines->fetch_line_comment_record($this->ajax_vars['comment_hash']);

			if ($valid === false)
				unset($this->ajax_vars['comment_hash']);
		}

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Comment" : "Create A New Comment");

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("comment_hash" => $this->lines->comment_hash, "limit" => $this->ajax_vars['limit'], "proposal_hash" => $this->proposal_hash, "popup_id" => $this->popup_id, "punch" => $this->ajax_vars['punch']))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:15px 20px;\">
						<table >
							<tr>
								<td style=\"vertical-align:top;padding-bottom:5px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err1".$this->popup_id."\">Vendor: </div>
									".$this->form->text_box("name=comment_vendor",
															"value=".$this->lines->current_comment['vendor_name'],
															"autocomplete=off",
															"size=30",
															"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('proposals', 'comment_vendor', 'comment_vendor_hash', 1);}",
                                                            "onKeyUp=if(ka==false && this.value){key_call('proposals', 'comment_vendor', 'comment_vendor_hash', 1);}",
                                                    		"onBlur=key_clear();",
															"onKeyDown=clear_values('comment_vendor_hash');").
									$this->form->hidden(array("comment_vendor_hash" => ($this->lines->current_comment['vendor_hash'] ? $this->lines->current_comment['vendor_hash'] : NULL)))."
									<div style=\"padding:5px 0 0 5px;\">
										".$this->form->radio("name=comment_action", "value=*", ($this->lines->current_comment['comment_action'] == '*' ? "checked" : NULL))."&nbsp;Apply To All Vendor POs
									</div>
									<div style=\"padding:5px 0 0 5px;\">
										".$this->form->radio("name=comment_action", "value=1", ($this->lines->current_comment['comment_action'] == 1 ? "checked" : NULL))."&nbsp;Apply To Above Vendor POs
									</div>
									<div style=\"padding:5px 0 0 5px;\">
										".$this->form->radio("name=comment_action", "value=2", (!$this->lines->current_comment['comment_hash'] || $this->lines->current_comment['comment_action'] == 2 ? "checked" : NULL))."&nbsp;Apply To Proposal Only
									</div>
								</td>
								<td rowspan=\"2\" style=\"vertical-align:top;padding-left:20px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2".$this->popup_id."\">Comments: </div>
									".$this->form->text_area("name=comments", "rows=7", "cols=55", "value=".$this->lines->current_comment['comments'])."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;\">
									<div style=\"text-align:left;padding:15px;\">
										".$this->form->button("value=Save", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_comment');")."
										&nbsp;&nbsp;".($valid ?
										$this->form->button("value=Delete Comment", "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_line_comment', 'delete=1');") : NULL)."
									</div>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function showall() {

		$this->unlock("_");

		$this->load_class_navigation();
		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

		$this->popup_id = $this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : 'popup_win_list' );

		$this->proposals($this->current_hash);

		$total_1 = $this->total;
		$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ( $end > $this->total )
			$end = $this->total;

		$order_by_ops = array(
    		"proposal_no"		=>	"proposals.proposal_no",
			"customer_name"		=>	"customers.customer_name",
			"creation_date"		=>	"proposals.creation_date",
			"sales_rep"			=>	"users.full_name"
		);

		$_total = $this->fetch_proposals(
    		$start_from,
    		$order_by_ops[ ( isset($order) && $order_by_ops[ $order ] ? $order : "creation_date" ) ],
    		( $order_dir ? $order_dir : "DESC" )
    	);

		if ( $this->active_search )
			$this->page_pref =& $this->search_vars;

		if ( $this->total != $total_1 ) {

			$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = MAIN_PAGNATION_NUM * ($p - 1);

			$end = $start_from + MAIN_PAGNATION_NUM;
			if ( $end > $this->total )
				$end = $this->total;
		}

		$user_name = $user_hash = array();

		if ( $this->p->ck(get_class($this), 'S') ) { # Trac #1221

			array_push($user_name, stripslashes($_SESSION['my_name']) );
			array_push($user_hash, $_SESSION['id_hash']);
		} else {

			$users = new system_config($this->current_hash);
			$users->fetch_users();

			array_push($user_name, "All Sales Reps");
			array_push($user_hash, "");

			for ( $i = 0; $i < count($users->user_info); $i++ ) {

				array_push($user_name, stripslashes($users->user_info[$i]['full_name']) );
				array_push($user_hash, $users->user_info[$i]['user_hash']);
			}

			unset($users);
		}

		$sort_menu =
		$this->form->form_tag() .
		( $this->p->ck('proposals', 'S') ?
    		$this->form->hidden( array(
        		'sales_rep_match[]' => $_SESSION['id_hash']
    		) ) : NULL
    	) . "
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
                    	( ! $this->page_pref || $this->page_pref['show'] == '*' ? "checked" : NULL)
                    ) . " All Proposals
                    </div>
					<div style=\"padding-bottom:3px;\">" .
                    $this->form->radio(
                        "name=sort",
                        "value=1",
                        ( $this->page_pref['show'] == 1 ? "checked" : NULL )
                    ) . " Only Active Proposals
                    </div>
					<div style=\"padding-bottom:3px;\">" .
                    $this->form->radio(
                        "name=sort",
                        "value=2",
                        ( $this->page_pref['show'] == 2 ? "checked" : NULL )
                    ) . " Only Archived Proposals
                    </div>
				</div>
				<div >
					<small><strong>With creation dates from:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\" id=\"sort_from_date_holder\"></div>
				</div>
				<div >
					<small><strong>To:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\" id=\"sort_to_date_holder\"></div>
				</div>
				<div >
					<small><strong>And where the sales rep matches:</strong></small>
					<div style=\"margin-left:10px;padding:5px 0;\">" .
						$this->form->select(
    						"sales_rep_match[]",
    						$user_name,
    						( is_array($this->page_pref['sales_rep']) ?
        						$this->page_pref['sales_rep'] :
        						( $this->p->ck('proposals', 'S') ?
            						$_SESSION['id_hash'] : array($this->page_pref['sales_rep'])
            					)
            				),
            				$user_hash,
            				"multiple",
            				"size=4",
            				"blank=1",
            				"style=width:160px;",
            				( $this->p->ck('proposals', 'S') ? "disabled" : NULL )
            			) . "
					</div>
				</div>
			</div>
			<div class=\"function_menu_item\">
				<div style=\"float:right;padding-right:5px;\">" .
                $this->form->button(
                    "value=Go",
                    "onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_search');"
                ) . "
                </div>" .
				$this->form->checkbox(
    				"name=page_pref",
    				"value=1",
    				( $this->page_pref['custom'] ? "checked" : NULL )
    			) . "&nbsp;&nbsp;<small><i>Remember Preferences</i></small>
			</div>
		</div>" .
    	$this->form->close_form();

    	$this->content['jscript'] = array(
            "setTimeout('DateInput(\'sort_from_date\', false, \'YYYY-MM-DD\', \'".($this->page_pref['sort_from_date'] ? $this->page_pref['sort_from_date'] : '')."\', 1, \'sort_from_date_holder\')', 500);",
            "setTimeout('DateInput(\'sort_to_date\', false, \'YYYY-MM-DD\', \'".($this->page_pref['sort_to_date'] ? $this->page_pref['sort_to_date'] : '')."\', 1, \'sort_to_date_holder\')', 700);"
        );

        if ( $s = fetch_sys_var('PROPOSAL_STATUS_LIST') ) {

            $s = __unserialize( stripslashes($s) );

            for ( $i = 0; $i < count($s); $i++ )
                $status[ $s[$i]['tag'] ] = $s[$i];

        }

		$tbl =
		( $this->active_search && $this->detail_search ?
    		"<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
    		<div style=\"padding-top:8px;padding-left:25px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall');\">Show All</a>]</div>" : NULL
		) . "
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"padding:15;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">
								<div style=\"float:right;text-align:right;font-weight:normal;padding-right:10px;\">" .
	                        		( $this->total ?
										paginate_jscript(
	    									$num_pages,
	    									$p,
	    									'sf_loadcontent',
	    									'cf_loadcontent',
	    									'proposals',
	    									'showall',
	    									$order,
	    									$order_dir,
	    									"'active_search={$this->active_search}'",
	    									"popup_id={$this->popup_id}'"
	    								) : NULL
	    							) . "
									<div style=\"padding-top:5px;\">
										<a href=\"javascript:void(0);\" onMouseOver=\"position_element($('sort_menu'), findPos(this, 'top')+15, findPos(this, 'left')-100);\" onClick=\"toggle_display('sort_menu', 'block');\"><img src=\"images/arrow_down2.gif\" id=\"sort_arrow_down\" border=\"0\" title=\"Change sorting options\" /></a>
										&nbsp;
										<span style=\"cursor:hand;\" onMouseOver=\"position_element($('sort_menu'), findPos($('sort_arrow_down'), 'top')+15, findPos($('sort_arrow_down'), 'left')-100);\" onClick=\"toggle_display('sort_menu', 'block');\" title=\"Change sorting options\"><small>Sort Options</small></span>
										$sort_menu
									</div>
								</div>" .
								( $this->total ?
									"Showing " . ( $start_from + 1 ) . " - " .
        							( $start_from + MAIN_PAGNATION_NUM > $this->total ?
            							$this->total : $start_from + MAIN_PAGNATION_NUM
            						) . " of {$this->total} Proposals." : NULL
								) . "
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
								( $this->p->ck(get_class($this), 'A') ?
									"<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_proposal', 'popup_id=main_popup');\"><img src=\"images/new.gif\" title=\"Create a new proposal\" border=\"0\" /></a>&nbsp;&nbsp;" : NULL
								) . "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'search_proposals', 'popup_id=main_popup', 'active_search=".$this->active_search."');\"><img src=\"images/search.gif\" title=\"Search proposals\" border=\"0\" /></a>
									"./*QuickSearch for Proposal Number (ticket #21)*/
									$this->form->form_tag()
									."
											Proposal Number: ".
											$this->form->text_box("name=proposal",
													"value=",
													"autocomplete=off",
													"size=20",
													"onKeyDown=if(event.keyCode==13){submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_search');}"
													// Below: removed code for AJAX dropdown menu (couldn't get it to proposal number)
													//",onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('reports', 'proposal', 'proposal_hash', 1);}",
                                                    //"onKeyUp=if(ka==false && this.value){key_call('reports', 'proposal', 'proposal_hash', 1);}",
                                                    //"onBlur=key_clear();"
											)."&nbsp". // extra space b/w text box and Go button.
											$this->form->button("value=Go",
													"id=primary",
													"onClick=submit_form(this.form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_search');"
											).
											$this->form->hidden( array(
													'detail_search' => 1
											) ).
											$this->form->close_form();
						$tbl .= "							
			</div>
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=proposal_no', 'order_dir=".($order == "proposal_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search=".$this->active_search."'" : NULL).", 'popup_id=".$this->popup_id."');\" style=\"color:#ffffff;text-decoration:underline;\">Proposal No.</a>" .
								( $order == 'proposal_no' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
							</td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=customer_name', 'order_dir=".($order == "customer_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search=".$this->active_search."'" : NULL).", 'popup_id=".$this->popup_id."');\" style=\"color:#ffffff;text-decoration:underline;\">Customer</a>" .
								( $order == 'customer_name' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
							</td>
							<td >Description</td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=creation_date', 'order_dir=".($order == "creation_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search=".$this->active_search."'" : NULL).", 'popup_id=".$this->popup_id."');\" style=\"color:#ffffff;text-decoration:underline;\">Creation Date</a>" .
								( $order == 'creation_date' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
							</td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=sales_rep', 'order_dir=".($order == "sales_rep" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ", 'active_search=".$this->active_search."'" : NULL).", 'popup_id=".$this->popup_id."');\" style=\"color:#ffffff;text-decoration:underline;\">Sales Rep</a>" .
								( $order == 'sales_rep' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
							</td>
							<td>Status</td>
						</tr>";

						$border = "border-bottom:1px solid #cccccc;";
						for ( $i = 0; $i < $_total; $i++ ) {

							if ( $i >= $_total - 1 )
    							unset($border);

							$tbl .= "
							<tr " . ( $this->p->ck(get_class($this), 'V') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'edit_proposal', 'proposal_hash={$this->proposal_info[$i]['proposal_hash']}', 'p=$p', 'order=$order', 'order_dir=$order_dir', 'active_search={$this->active_search}');\"" : NULL ) . ">
								<td " . ( $border ? "style=\"$border\"" : NULL ) . ">{$this->proposal_info[$i]['proposal_no']}" .
    								( $this->proposal_info[$i]['proposal_status'] ?
    									"&nbsp;&nbsp;<span title=\"" . htmlentities($this->status_icons[ $this->proposal_info[$i]['proposal_status'] ]['msg'], ENT_QUOTES) . "\n" . htmlentities( stripslashes($this->proposal_info[$i]['status_comment']), ENT_QUOTES) . "\">" . $this->status_icons[ $this->proposal_info[$i]['proposal_status'] ]['img'] . "</span>" : NULL
    								) . "
    							</td>
								<td " . ( $border ? "style=\"$border\"" : NULL ) . ">" . stripslashes($this->proposal_info[$i]['customer_name']) . "</td>
								<td title=\"". $this->proposal_info[$i]['proposal_descr'] ."\" " . ( $border ? "style=\"$border\"" : NULL ) . ">" .
    							( strlen($this->proposal_info[$i]['proposal_descr']) > 55 ?
									substr( stripslashes($this->proposal_info[$i]['proposal_descr']), 0, 55) . "...." : stripslashes($this->proposal_info[$i]['proposal_descr'])
								) . "
								</td>
								<td " . ( $border ? "style=\"$border\"" : NULL ) . ">" . date(DATE_FORMAT . " " . TIMESTAMP_FORMAT, $this->proposal_info[$i]['creation_date']) . "</td>
								<td " . ( $border ? "style=\"$border\"" : NULL ) . ">" . stripslashes($this->proposal_info[$i]['full_name']) . "</td>
								<td style=\"$border" .
								( isset($status[$this->proposal_info[$i]['user_status']]['color']) ?
    								"color:{$status[$this->proposal_info[$i]['user_status']]['color']};" : NULL
								) .
								( $status[ $this->proposal_info[$i]['user_status'] ]['bold'] == 1 ?
    								"font-weight:bold;" : NULL
								) .
								"\">" .
								( $this->proposal_info[$i]['user_status'] ?
                                    "<span " .
    								( $this->proposal_info[$i]['user_status_comment'] ?
        								"title=\"" . htmlspecialchars($this->proposal_info[$i]['user_status_comment']) . "\"" : NULL
    								) .
    								">" . stripslashes($this->proposal_info[$i]['user_status']) . "</span>" : "&nbsp;"
    							) . "
                                </td>
							</tr>";
						}

						if ( ! $this->total )
							$tbl .= "
							<tr >
								<td colspan=\"6\">" .
    							( $this->active_search ?
									"<div style=\"padding-top:10px;font-weight:bold;\">" .
        							( $this->detail_search ?
										"Your search " : "Your sort options "
        							) . "returned empty result set
        							</div>" : "You have no proposals to display. " .
        							( $this->p->ck(get_class($this), 'A') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_proposal', 'popup_id=main_popup');\">Create a new proposal now.</a>" : NULL
        							)
        						) . "
								</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		$this->content['html']['place_holder'] = $tbl;
	}

	function search_proposals() {
		global $stateNames, $states;

		$date = date("U") - 3600;
		$result = $this->db->query("DELETE FROM `search`
							  		WHERE `timestamp` <= '$date'");

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Search Proposals";
		$this->content['focus'] = 'proposal_no';

		if ($this->ajax_vars['active_search']) {
			$result = $this->db->query("SELECT `search_str`
										FROM `search`
								  		WHERE `search_hash` = '".$this->ajax_vars['active_search']."'");
			$row = $this->db->fetch_assoc($result);
			$search_vars = permissions::load_search_vars($row['search_str']);
		}

		// Limit user list if permission checked for Trac #1221
		if ($this->p->ck(get_class($this), 'S')) {
			$user_name[] = $_SESSION['my_name'];
			$user_hash[] = $_SESSION['id_hash'];
		} else {
			$users = new system_config($this->current_hash);
			$users->fetch_users();
			$user_name[] = "All Sales Reps";
			$user_hash[] = "";
			for ($i = 0; $i < count($users->user_info); $i++) {
				$user_name[] = stripslashes($users->user_info[$i]['full_name']);
				$user_hash[] = $users->user_info[$i]['user_hash'];
			}
			unset($users);
		}
		$this->content['focus'] = "proposal_no";

		list($search_name, $search_hash) = $this->fetch_searches('proposals');

        if ($proposal_status = fetch_sys_var('PROPOSAL_STATUS_LIST')) {
            $proposal_status = __unserialize(stripslashes($proposal_status));

            if (count($proposal_status) && $proposal_status[0]) {
	            $status_option = "
	            <select name=\"user_status\" class=\"txtSearch\" style=\"width:175px;\">
	                <option></option>";
	            for ($i = 0; $i < count($proposal_status); $i++)
	                $status_option .= "
	                <option style=\"color:".$proposal_status[$i]['color'].";".($proposal_status[$i]['bold'] ? "font-weight:bold;" : NULL)."\">".$proposal_status[$i]['tag']."</option>";

	            $status_option .= "
	            </select>";
            }
        }

		$tbl =
		$this->form->form_tag() .
		( $this->p->ck('proposals', 'S') ?
    		$this->form->hidden( array(
        		'sales_rep_match[]' => $_SESSION['id_hash']
    		) ) : NULL
    	) .
		$this->form->hidden( array(
    		'popup_id' 		=> $this->content['popup_controls']['popup_id'],
			'detail_search' => 1
        ) ) . "
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
							Filter your proposal search criteria below:
						</div>
						<table>
							<tr>
								<td style=\"padding-left:15px;\">
									<fieldset>
										<legend>Proposal Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=proposal_no", "size=15", "maxlength=12")."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;\">
									<fieldset>
										<legend>Customer PO Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=po_no", "size=35", "maxlength=255")."</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Sales Rep</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->select("sales_rep_match[]", $user_name, ($this->p->ck('proposals', 'S') ? $_SESSION['id_hash'] : ''), $user_hash, "multiple=multiple", "size=6", "cols=5", "blank=1", ($this->p->ck('proposals', 'S') ? "disabled" : NULL))."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Search By Customer</legend>
										<div style=\"padding:5px;padding-bottom:5px\">" .
											$this->form->text_box(
    											"name=customer",
												"value=",
												"autocomplete=off",
												"size=37",
												"onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('reports', 'customer', 'customer_hash', 1);}",
                                                "onKeyUp=if(ka==false && this.value){key_call('reports', 'customer', 'customer_hash', 1);}",
                                    			"onBlur=key_clear();"
											) . "
											<div style=\"margin-top:15px;width:250px;height:45px;overflow:auto;display:none;background-color:#efefef;border:1px outset #cccccc;\" id=\"customer_filter_holder\"></div>
										</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Active/Archived</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->select("active_archived", array("Active", "Archived"), '', array(1, 2))."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Purchase Order Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=dc_po_no", "size=35", "maxlength=255")."</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Proposal Status</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->select("proposal_status", array("Proposed", "Booked", "Invoiced", "Punchlist", "Complete"), '', array(1, 2, 3, 4, 'C'))."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
                                    <fieldset>
                                        <legend>Custom Status</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">".($status_option ?
                                            $status_option : "<i>Custom status list not defined</i>")."
                                        </div>
                                    </fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Direct Bill</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->select("direct_bill", array("Direct Bill", "Normal Business"), '', array('D', 'N'))."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Item/Part/Product Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=item_no", "size=35", "maxlength=255")."</div>
									</fieldset>
								</td>
							</tr>
							<tr>
								<td style=\"padding-left:15px;vertical-align:top;\">
									<fieldset>
										<legend>Invoice Number</legend>
										<div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=invoice_no", "size=15", "maxlength=64")."</div>
									</fieldset>
								</td>
								<td style=\"padding-left:15px;vertical-align:top;\">
                                    <fieldset>
                                        <legend>Acknowledgment Number</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">".$this->form->text_box("name=ack_no", "size=35", "maxlength=255")."</div>
                                    </fieldset>
								</td>
							</tr>
							<tr>
                                <td style=\"padding-left:15px;vertical-align:top;\" colspan=\"2\">
                                    <fieldset>
                                        <legend>Proposal Description</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->text_box("name=descr", "size=68", "maxlength=255")."
                                        </div>
                                    </fieldset>
                                </td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			<div style=\"float:right;text-align:right;padding-top:15px;margin-right:25px;\">" .
			$this->form->button("value=Search",
                    			"id=primary",
                    			"onClick=submit_form(\$('popup_id').form, 'proposals', 'exec_post', 'refresh_form', 'action=doit_search');"
			) . "
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
								".$this->form->button("value=Go", "onClick=agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'active_search='+\$F('saved_searches'));window.setTimeout('popup_windows[\'".$this->content['popup_controls']['popup_id']."\'].hide()', 1000);")."
								&nbsp;
								".$this->form->button("value=&nbsp;X&nbsp;", "title=Delete this saved search", "onClick=if(\$('saved_searches') && \$('saved_searches').options[\$('saved_searches').selectedIndex].value){if(confirm('Do you really want to delete your saved search?')){agent.call('proposals', 'sf_loadcontent', 'cf_loadcontent', 'purge_search', 's='+\$('saved_searches').options[\$('saved_searches').selectedIndex].value);\$('saved_searches').remove(\$('saved_searches').selectedIndex);}}")."
							</div>" : NULL)."
						</td>
					</tr>
					<tr>
						<td colspan=\"2\">
							<div style=\"padding-top:5px;display:none;margin-left:5px;\" id=\"save_search_box\">
								Name Your Saved Search:
								<div style=\"margin-top:5px;\">" .
									$this->form->text_box(
    									"name=search_name",
    									"value=",
    									"size=35",
    									"maxlenth=64",
    									"style=background-color:#fff;"
									) . "
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

	function fetch_import_data($proposal_hash) {
		$r = $this->db->query ("SELECT proposals.import_data
								FROM `proposals`
								WHERE proposals.proposal_hash = '$proposal_hash'");
		if ($row = $this->db->fetch_assoc($r))
			return __unserialize(stripslashes($row['import_data']));

		return false;
	}

	function gp_calculator() {
        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "GP Calculator";
		$this->content['popup_controls']['popup_width'] = "210px;";
		$this->content['popup_controls']['popup_height'] = "75px;";
		$this->content['popup_controls']['popup_resize'] = 0;
		$this->content['popup_controls']['popup_scrolling'] = 0;
		$this->content['focus'] = "margin";

		$item = $this->ajax_vars['item'];
		$rand_id = $this->ajax_vars['rand_id'];
		$cost = $this->ajax_vars['cost'];

		$this->content['jscript'][] = "
		gp_margin = function() {
            margin = parseFloat( \$F('margin').toString().replace(/[^.0-9]/g, '') );
            if ( margin > 100 ) {

                margin = 100;
                \$('margin').value = 100;
            } else if ( margin < 0 ) {

                \$('margin').value = '';
                \$('{$item}').value = '0.00';
                return;
            }

            margin = margin.toFixed(2);
            var cost = " . ( $cost ? $cost : "\$F('cost{$rand_id}')" ) . ";
            cost = parseFloat( cost.toString().replace(/[^.0-9]/g, '') );

            var i = new lineItemValidator();
            var sell = i.GPMargin(cost, null, margin);

            if ( \$('{$item}') ) {
                \$('{$item}').value = formatCurrency( sell );
            }
		}
		";

        $tbl = $this->form->form_tag()."
        <div class=\"panel\" id=\"main_table".$this->popup_id."\">
            <div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message".$this->popup_id."\"></p>
            </div>
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:190px;height:75px;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;\">
                        Enter your desired GP margin below.
                        <div style=\"padding-top:10px;\">
	                        GP Margin:&nbsp;
	                        ".$this->form->text_box("name=margin",
	                                                "value=",
	                                                "style=width:45px;text-align:right;",
                                                    "onFocus=this.select();")."&nbsp;%&nbsp;
	                        ".$this->form->button("value=Go", "id=primary", "onClick=gp_margin();")."
	                    </div>
                    </td>
                </tr>
            </table>
        </div>".
        $this->form->close_form();

		$this->content['popup_controls']['cmdTable'] = $tbl;
	}
}
?>