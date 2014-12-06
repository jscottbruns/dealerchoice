<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class line_items extends AJAX_library {

	public $total;
	public $active_search;

	public $item_hash;
	public $proposal_hash;
	public $current_hash;
	public $punch;

	public $line_info = array();
	public $current_line;
	public $proposal_groups = array();
	public $current_group;
	public $proposal_comments = array();
	public $current_comment;

	public $tax_country;
	public $tax_state;
	public $installation_hash;
	public $tax_exempt;

	function line_items($proposal_hash, $punch=NULL) {
		global $db;

		$this->db =& $db;
		$this->proposal_hash = $proposal_hash;
		$this->current_hash = $_SESSION['id_hash'];
		if ($punch)
			$this->punch = 1;

		$result = $this->db->query("SELECT t1.install_addr_hash ,
				CASE
				WHEN SUBSTR(t1.install_addr_hash, 1, 9) = 'customers'
				THEN SUBSTRING(t1.install_addr_hash, 11, 32)
				ELSE NULL
				END AS customer_hash ,
				CASE
				WHEN SUBSTR(t1.install_addr_hash, 1, 9) = 'customers' AND SUBSTRING(t1.install_addr_hash, 44, 5) != ''
				THEN SUBSTRING(t1.install_addr_hash, 44, 32)
				ELSE NULL
				END AS cust_location_hash ,
				CASE
				WHEN SUBSTR(t1.install_addr_hash, 1, 7) = 'vendors'
				THEN SUBSTRING(t1.install_addr_hash, 9, 32)
				ELSE NULL
				END AS vendor_hash ,
				CASE
				WHEN SUBSTR(t1.install_addr_hash, 1, 7) = 'vendors' AND SUBSTRING(t1.install_addr_hash, 44, 5) != ''
				THEN SUBSTRING(t1.install_addr_hash, 42, 32)
				ELSE NULL
				END AS vend_location_hash , t3.tax_exempt_id , t3.gsa ,
				t3.pst_exempt , t3.gst_exempt , t3.hst_exempt , t3.qst_exempt ,
				CASE
				WHEN t2.order_type = 'D'
				THEN 1
				ELSE NULL
				END AS direct
				FROM proposal_install t1
				LEFT JOIN proposals t2 ON t2.proposal_hash = t1.proposal_hash
				LEFT JOIN customers t3 ON t3.customer_hash = t2.customer_hash
				WHERE t1.proposal_hash = '".$this->proposal_hash."' AND t1.install_addr_hash != ''");
		$row = $this->db->fetch_assoc($result);
		if ($row['customer_hash']) {
			$r = $this->db->query($row['cust_location_hash'] ? "
					SELECT locations.location_country , locations.location_state
					FROM locations
					WHERE locations.entry_hash = '".$row['customer_hash']."' AND locations.location_hash = '".$row['cust_location_hash']."' " : "
					SELECT customers.country , customers.state
					FROM customers
					WHERE customers.customer_hash = '".$row['customer_hash']."'");
		} elseif ($row['vendor_hash'])
		$r = $this->db->query($row['vend_location_hash'] ? "
				SELECT locations.location_country , locations.location_state
				FROM locations
				WHERE locations.entry_hash = '".$row['vendor_hash']."' AND locations.location_hash = '".$row['vend_location_hash']."' " : "
				SELECT vendors.country , vendors.state
				FROM vendors
				WHERE vendors.vendor_hash = '".$row['vendor_hash']."'");
		if ($country = $this->db->result($r, 0, $row['cust_location_hash'] || $row['vend_location_hash'] ? 'location_country' : 'country'));
		$this->tax_country = $country;
		if ($state = $this->db->result($r, 0, $row['cust_location_hash'] || $row['vend_location_hash'] ? 'location_state' : 'state'));
		$this->tax_state = $state;
		if ($row['install_addr_hash'])
			$this->installation_hash = $row['install_addr_hash'];
		if ($row['direct'] || $row['gsa'] || $row['tax_exempt_id']) {
			$this->tax_exempt = true;
			if ($this->tax_country == 'CA' && $row['tax_exempt_id']) {
				if ($row['pst_exempt'])
					$this->tax_exempt_province['pst'] = 1;
				if ($row['gst_exempt'])
					$this->tax_exempt_province['gst'] = 1;
				if ($row['hst_exempt'])
					$this->tax_exempt_province['hst'] = 1;
			}
		}
		$result = $this->db->query("SELECT COUNT(*) AS Total
				FROM `line_items`
				WHERE `proposal_hash` = '".$this->proposal_hash."' AND `punch` = ".($punch ? 1 : 0));
		$this->total = $this->db->result($result);

		$this->proposal_groups = array();
		$result = $this->db->query("SELECT line_groups.group_descr , line_groups.group_hash
				FROM `line_groups`
				WHERE `proposal_hash` = '".$this->proposal_hash."'");
		while ($row = $this->db->fetch_assoc($result)) {
			$this->proposal_groups['group_hash'][] = $row['group_hash'];
			$this->proposal_groups['group_descr'][] = $row['group_descr'];
		}

		$this->proposal_comments = array();
		$result = $this->db->query("SELECT line_comments.* , vendors.vendor_name as comment_vendor
				FROM `line_comments`
				LEFT JOIN vendors ON vendors.vendor_hash = line_comments.vendor_hash
				WHERE line_comments.proposal_hash = '".$this->proposal_hash."' AND `punch` = ".($this->punch ? 1 : 0));
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->proposal_comments, $row);

		$this->line_info = array();
		$this->current_line = '';
		$this->current_group = '';
		$this->current_comment = '';
	}

	function group_line_pos($group_hash, $pos='f') {
		$result = $this->db->query("SELECT `line_no`
				FROM `line_items`
				WHERE `group_hash` = '$group_hash' AND `punch` = ".($this->punch ? 1 : 0)."
				ORDER BY line_items.line_no ".($pos == 'f' ? "ASC" : "DESC")."
				LIMIT 1");
		return $this->db->result($result);
	}

	function add_to_group($item_hash_array, $group_hash=NULL) {

		if ( $group_hash ) {

			$result = $this->db->query("SELECT t1.line_no
					FROM line_items t1
					WHERE t1.group_hash = '$group_hash' AND t1.punch = " . ( $this->punch ? 1 : 0 ) . "
					ORDER BY t1.line_no DESC
					LIMIT 1");
			$last_line_no = $this->db->result($result);
		}

		if ( ! $last_line_no )
			$last_line_no = $this->total;

		for ( $i = 0; $i < count($item_hash_array); $i++ ) {

			if ( ! $this->db->query("UPDATE line_items
					SET group_hash = '$group_hash' , line_no = '" . ( ++$last_line_no ) . "'
					WHERE item_hash = '{$item_hash_array[$i]}'")
					) {

				$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
				return false;
			}
		}

		# Reindex all line items
		$result = $this->db->query("SELECT t1.item_hash, t1.group_hash
				FROM line_items t1
				WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t1.punch = " . ( $this->punch ? 1 : 0 ) . "
				ORDER BY t1.line_no ASC");
		while ( $row = $this->db->fetch_assoc($result) )
			$array[] = $row;

		$count = count($array);
		$line_pos = array();
		$line_no = 0;

		for ( $i = 0; $i < $count; $i++ ) {

			if ( $array[$i]['group_hash'] && ! array_key_exists($array[$i]['item_hash'], $line_pos) ) {

				$line_pos[$array[$i]['item_hash']] = ++$line_no;
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
						WHERE item_hash = '$item_hash'")
				) {

					$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
					return false;
				}
			}
		}

		return true;
	}

	function fetch_line_items($start, $end, $active=NULL) {

		$this->line_info = array();
		$_total = 0;

		if ( $this->active_search ) {

			$result = $this->db->query("SELECT
					t1.query,
					t1.total
					FROM search t1
					WHERE t1.search_hash = '{$this->active_search}'");
			if ( $row = $this->db->fetch_assoc($result) ) {

				$this->total = $row['total'];
				$sql = __unserialize( base64_decode($row['query']) );
			}
		}

		if ( $active && $active != 1 )
			unset($active);

		if ( $sql ) {

			$pattern = array(
					'/\bline_items\.(.*)\b/U',
					'/\bline_groups\.(.*)\b/U',
					'/\bvendors\.(.*)\b/U',
					'/\bvendor_products\.(.*)\b/U',
					'/\bdiscounting\.(.*)\b/U',
					'/\bdiscount_details\.(.*)\b/U',
			);
			$replace = array(
					't1.$1',
					't2.$1',
					't3.$1',
					't4.$1',
					't5.$1',
					't6.$1'
			);

			if ( $sql )
				$sql = preg_replace($pattern, $replace, $sql);
		}

		$result = $this->db->query("SELECT
				t1.obj_id,
				t1.timestamp,
				t1.last_change,
				t1.item_hash,
				t1.line_no,
				t1.direct_bill_vendor,
				t1.direct_bill_amt,
				t1.item_code,
				t1.group_hash,
				t1.proposal_hash,
				t1.po_hash,
				t1.invoice_hash, t1.ack_no,
				t1.active,
				t1.status,
				t1.import_complete,
				t1.special as cr,
				t1.vendor_hash,
				t1.ship_to_hash,
				t1.product_hash,
				t1.panel_id,
				t1.qty,
				t1.item_no,
				t1.item_descr,
				t1.item_tag1,
				t1.item_tag2,
				t1.item_tag3,
				t1.discount_hash,
				t1.discount_item_hash,
				t1.discount1,
				t1.discount2,
				t1.discount3,
				t1.discount4,
				t1.discount5,
				t1.gp_type,
				IF( ISNULL(@local_currency) OR ISNULL(@convert_currency), t1.list, ROUND( currency_exchange(t1.list, @local_exchange_rate, 0), 2) ) AS list,
				IF( ISNULL(@local_currency) OR ISNULL(@convert_currency), t1.cost, ROUND( currency_exchange(t1.cost, @local_exchange_rate, 0), 2) ) AS cost,
				IF( ISNULL(@local_currency) OR ISNULL(@convert_currency), t1.sell, ROUND( currency_exchange(t1.sell, @local_exchange_rate, 0), 2) ) AS sell,
				IF( ISNULL(@local_currency) OR ISNULL(@convert_currency), ( t1.list * t1.qty ), ( ROUND( currency_exchange(t1.list, @local_exchange_rate, 0), 2) * t1.qty) ) AS ext_list,
				IF( ISNULL(@local_currency) OR ISNULL(@convert_currency), ( t1.sell * t1.qty ), ( ROUND( currency_exchange(t1.sell, @local_exchange_rate, 0), 2) * t1.qty) ) AS ext_sell,
				IF( ISNULL(@local_currency) OR ISNULL(@convert_currency), ( t1.cost * t1.qty ), ( ROUND( currency_exchange(t1.cost, @local_exchange_rate, 0), 2) * t1.qty) ) AS ext_cost,
				ISNULL( t1.import_data ) AS import_data,
				IF( ISNULL(@local_currency) OR ISNULL(@convert_currency), FORMAT( ( gp_margin(t1.cost, t1.sell, NULL) * 100 ) , 2), FORMAT( ( gp_margin( currency_exchange(t1.cost, @local_exchange_rate, 0), currency_exchange( t1.sell, @local_exchange_rate, 0), NULL) * 100 ), 2) ) AS gp_margin,
				t3.vendor_name,
				t2.group_descr,
				t4.product_name,
				t4.catalog_code,
				t4.separate_po,
				t5.discount_descr,
				t5.discount_id,
				t6.discount_type,
				IF( ISNULL(@local_currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(@local_currency) ) AS symbol
				FROM line_items t1
				LEFT JOIN line_groups t2 ON t2.group_hash = t1.group_hash
				LEFT JOIN vendors t3 ON t3.vendor_hash = t1.vendor_hash
				LEFT JOIN vendor_products t4 ON t4.product_hash = t1.product_hash
				LEFT JOIN discounting t5 ON t5.discount_hash = t1.discount_hash
				LEFT JOIN discount_details t6 ON t6.item_hash = t1.discount_item_hash
				WHERE
				t1.proposal_hash = '{$this->proposal_hash}'
				AND
				t1.punch = " . ( $this->punch ? 1 : 0 ) .
				( $active ?
						" AND t1.active = 1 " : NULL
				) .
				( $sql ?
						" AND $sql " : NULL
				) . "
				ORDER BY t1.line_no ASC " .
				( isset($start) && isset($end) ?
						"LIMIT $start, $end" : NULL
				) );
		while ( $row = $this->db->fetch_assoc($result) ) {

			$_total++;
			array_push($this->line_info, $row);
		}

		if ( $this->total != $_total )
			$this->total = $_total;

		return $_total;
	}

	function fetch_line_items_short($active=NULL, $order_by=NULL) {
		if (!$this->proposal_hash || !$this->total)
			return;

		$result = $this->db->query("SELECT line_items.item_hash , line_items.group_hash , line_items.item_code ,
				line_items.vendor_hash , vendors.vendor_name AS item_vendor , line_items.invoice_hash ,
				line_items.item_tag1 , vendor_products.product_name AS item_product , line_items.po_hash ,
				line_items.product_hash , line_items.discount_hash , line_items.gp_type ,
				line_items.list , line_items.cost , line_items.sell , line_items.qty ,
				(line_items.sell * line_items.qty) AS ext_sell , (line_items.cost * line_items.qty) AS ext_cost ,
				line_items.active , line_items.status , line_items.item_descr, line_items.line_no
				FROM line_items
				LEFT JOIN vendors ON vendors.vendor_hash = line_items.vendor_hash
				LEFT JOIN vendor_products ON vendor_products.product_hash = line_items.product_hash
				WHERE line_items.proposal_hash = '".$this->proposal_hash."' AND `punch` = ".($this->punch ? 1 : 0).(is_numeric($active) ? " AND line_items.active = $active" : NULL).
				($order_by ? " ORDER BY $order_by" : NULL));
		while ($row = $this->db->fetch_assoc($result))
			$line_item[] = $row;

		return $line_item;
	}
	/**
	 * Returns an array of unordered item hashes for the current proposal filtered by vendor if given.
	 *
	 * @param string $vendor_hash
	 * @return array
	 */
	function fetch_line_item_array($vendor_hash=NULL) {
		if (!$this->proposal_hash || !$this->total)
			return;

		$result = $this->db->query("SELECT line_items.item_hash
				FROM line_items
				WHERE line_items.proposal_hash = '".$this->proposal_hash."' AND `punch` = ".($this->punch ? 1 : 0).($vendor_hash ? " AND `vendor_hash` = '$vendor_hash'" : NULL)." AND `po_hash` = '' AND `active` = 1");
		while ($row = $this->db->fetch_assoc($result))
			$line_item[] = $row['item_hash'];

		return $line_item;
	}

	function is_valid_line($item_hash) {
		$result = $this->db->query("SELECT COUNT(*) AS Total
				FROM `line_items`
				WHERE `item_hash` = '$item_hash'");
		if ($this->db->result($result) != 0)
			return true;

		return false;
	}

	function fetch_line_item_record($item_hash) {
		if (!$item_hash)
			return;

		$result = $this->db->query("SELECT t1.* , t2.vendor_name AS item_vendor ,
				IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), t1.cost, ROUND(currency_exchange(t1.cost, @local_exchange_rate, 0), 2)) AS cost ,
				IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), t1.sell, ROUND(currency_exchange(t1.sell, @local_exchange_rate, 0), 2)) AS sell ,
				IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), t1.list, ROUND(currency_exchange(t1.list, @local_exchange_rate, 0), 2)) AS list ,
				IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), (t1.sell * t1.qty), (ROUND(currency_exchange(t1.sell, @local_exchange_rate, 0), 2) * t1.qty)) AS ext_sell ,
				IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), (t1.cost * t1.qty), (ROUND(currency_exchange(t1.cost, @local_exchange_rate, 0), 2) * t1.qty)) AS ext_cost ,
				IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), (t1.list * t1.qty), (ROUND(currency_exchange(t1.list, @local_exchange_rate, 0), 2) * t1.qty)) AS ext_list ,
				IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), FORMAT( ( gp_margin(t1.cost, t1.sell, NULL) * 100 ) , 2), FORMAT( ( gp_margin(currency_exchange(t1.cost, @local_exchange_rate, 0), currency_exchange(t1.sell, @local_exchange_rate, 0), NULL) * 100 ) , 2)) AS gp_margin ,
				t3.product_name as item_product , t3.product_hash as item_product_hash ,
				t3.catalog_code as item_catalog_code , t3.separate_po ,
				t3.product_taxable AS taxable_product , t4.discount_id ,
				t4.discount_descr , t4.discount_expiration_date ,
				t5.discount_type , t6.po_no , t6.creation_date AS po_creation_date ,
				t7.invoice_no , t7.invoice_date AS invoice_date , t8.order_no as work_order_no ,
				t9.group_descr ,
				IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), currency_symbol(NULL), currency_symbol(@local_currency)) AS symbol
				FROM line_items t1
				LEFT JOIN vendors t2 ON t2.vendor_hash = t1.vendor_hash
				LEFT JOIN vendor_products t3 ON t3.product_hash = t1.product_hash
				LEFT JOIN discounting t4 ON t4.discount_hash = t1.discount_hash
				LEFT JOIN discount_details t5 ON t5.item_hash = t1.discount_item_hash
				LEFT JOIN purchase_order t6 ON t6.po_hash = t1.po_hash
				LEFT JOIN customer_invoice t7 ON t7.invoice_hash = t1.invoice_hash
				LEFT JOIN work_orders t8 ON t8.order_hash = t1.work_order_hash
				LEFT JOIN line_groups t9 ON t9.group_hash = t1.group_hash
				WHERE t1.item_hash = '$item_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_line = $row;
			$this->item_hash = $item_hash;

			if ( $row['work_order_hash'] ) {

				$r2 = $this->db->query("SELECT SUM( t1.true_cost * t1.time ) AS work_order_cost
						FROM work_order_items t1
						WHERE t1.order_hash = '{$row['work_order_hash']}'");
				$this->current_line['work_order_cost'] = $this->db->result($r2, 0, 'work_order_cost_ext');
			}

			if ( $this->current_line['ship_to_hash'] ) {

				list($class, $id, $hash) = explode("|", $this->current_line['ship_to_hash']);
				$obj = new $class($this->current_hash);
				if ($hash) {

					$obj->fetch_location_record($hash);
					$this->current_line['item_ship_to'] = $obj->current_location['location_name'];
				} else {

					$obj->fetch_master_record($id);
					$this->current_line['item_ship_to'] = $obj->{"current_" . strrev( substr( strrev($class), 1) ) }[strrev( substr( strrev($class), 1) ) . "_name"];
				}

				unset($obj);
			}

			return true;
		}

		return false;
	}

	function fetch_item_details($item_hash) {
		$result = $this->db->query("SELECT line_items.import_data , line_items.item_no ,
				vendors.vendor_name AS item_vendor ,
				vendor_products.product_name as item_product
				FROM `line_items`
				LEFT JOIN vendors ON vendors.vendor_hash = line_items.vendor_hash
				LEFT JOIN vendor_products ON vendor_products.product_hash = line_items.product_hash
				WHERE line_items.proposal_hash = '".$this->proposal_hash."' AND line_items.item_hash = '$item_hash'");
		return $this->db->fetch_assoc($result);
	}

	function summarized_lines() {
		$this->line_info = array();
		if ($this->total > 0) {
			$result = $this->db->query("SELECT line_items.item_hash , line_items.item_code , line_items.status , line_items.active ,
					(line_items.sell * line_items.qty) AS ext_sell ,
					(line_items.cost * line_items.qty) AS ext_cost ,
					(line_items.list * line_items.qty) AS ext_list ,
					FORMAT((line_items.sell - line_items.cost) / line_items.sell * 100 , 2) AS gp_margin ,
					line_items.discount_hash , line_items.discount_item_hash ,
					vendors.vendor_hash , vendors.vendor_name ,
					vendor_products.product_hash , vendor_products.vendor_hash AS vendor_product_hash ,
					vendor_products.product_name
					FROM `line_items`
					LEFT JOIN `vendors` ON vendors.vendor_hash = line_items.vendor_hash
					LEFT JOIN `vendor_products` ON vendor_products.product_hash = line_items.product_hash
					WHERE line_items.proposal_hash = '".$this->proposal_hash."' AND line_items.active = 1 AND line_items.punch = ".($this->punch ? 1 : 0)."
					ORDER BY vendors.vendor_name , vendor_products.product_name ASC");
			while ($row = $this->db->fetch_assoc($result))
				array_push($this->line_info, $row);
		}
	}

	function fetch_direct_bill_info($item_array=NULL) {

		$vendors = new vendors($this->current_hash);
		if ($item_array) {
			for ($i = 0; $i < count($item_array); $i++) {
				$this->fetch_line_item_record($item_array[$i]);
				$line_info[$this->current_line['direct_bill_vendor']][$this->current_line['vendor_hash']] = array("status"              =>	$this->current_line['status'],
						"direct_bill_vendor"	=>	$this->current_line['direct_bill_vendor'],
						"direct_bill_amt"		=>	$this->current_line['direct_bill_amt'],
						"vendor_hash"			=>  $this->current_line['vendor_hash'],
						"vendor_name"			=>	$this->current_line['item_vendor']);
			}
		} else {
			$result = $this->db->query("SELECT line_items.status , line_items.direct_bill_vendor , line_items.direct_bill_amt ,
					line_items.vendor_hash , vendors.vendor_name
					FROM `line_items`
					LEFT JOIN `vendors` ON vendors.vendor_hash = line_items.vendor_hash
					WHERE line_items.proposal_hash = '".$this->proposal_hash."' AND line_items.active = 1");
			while ($row = $this->db->fetch_assoc($result))
				$line_info[$row['direct_bill_vendor']][$row['vendor_hash']] = $row;
		}

		if (is_array($line_info)) {
			while (list($direct_bill_vendor, $vendor_group) = each($line_info)) {
				$item_hash = '';
				$total_sell = $total_com = 0;
				if ($direct_bill_vendor && strlen($direct_bill_vendor) == 32) {
					$vendors->fetch_master_record($direct_bill_vendor);
					while (list($group, $info) = each($vendor_group)) {
						$result = $this->db->query("SELECT line_items.item_hash ,
								IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), (line_items.sell * line_items.qty), (ROUND(currency_exchange(line_items.sell, @local_exchange_rate, 0), 2) * line_items.qty)) AS ext_sell ,
								IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), (line_items.cost * line_items.qty), (ROUND(currency_exchange(line_items.cost, @local_exchange_rate, 0), 2) * line_items.qty)) AS ext_cost ,
								IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), (line_items.list * line_items.qty), (ROUND(currency_exchange(line_items.list, @local_exchange_rate, 0), 2) * line_items.qty)) AS ext_list
								FROM `line_items`
								WHERE `proposal_hash` = '".$this->proposal_hash."' AND `vendor_hash` = '".$group."'");
						while ($row = $this->db->fetch_assoc($result)) {
							if (!is_array($item_array) || (is_array($item_array) && in_array($row['item_hash'], $item_array))) {
								$total_sell = bcadd($total_sell, $row['ext_sell'], 2);
								$total_com = bcadd($total_com, bcsub($row['ext_sell'], $row['ext_cost'], 2), 2);

								$item_hash[] = $row['item_hash'];
								$collect[$group] = $info['direct_bill_amt'];
							}
						}
					}
					$direct[$direct_bill_vendor] = array("vendor"          => $vendors->current_vendor['vendor_name'],
							"vendor_hash"     => $direct_bill_vendor,
							"total_sell"      => $total_sell,
							"total_com"       => $total_com,
							"collect"         => $collect,
							"type"            => 'D',
							"items"           => $item_hash);
				} else {
					unset($item_hash, $total);
					while (list($group, $info) = each($vendor_group)) {
						$result = $this->db->query("SELECT line_items.item_hash ,
								IF(ISNULL(@local_currency) OR ISNULL(@convert_currency), (line_items.sell * line_items.qty), (ROUND(currency_exchange(line_items.sell, @local_exchange_rate, 0), 2) * line_items.qty)) AS ext_sell
								FROM `line_items`
								WHERE `proposal_hash` = '".$this->proposal_hash."' AND `vendor_hash` = '".$group."'");
						while ($row = $this->db->fetch_assoc($result)) {
							if (!is_array($item_array) || (is_array($item_array) && in_array($row['item_hash'], $item_array))) {
								$total_sell = bcadd($total_sell, $row['ext_sell'], 2);
								$item_hash[] = $row['item_hash'];
							}
						}
						$direct['OPEN'] = array("vendor"      => $info['vendor_name'],
								"vendor_hash" => $group,
								"total_sell"  => $total_sell,
								"collect"     => $info['direct_bill_amt'],
								"type"	      => 'O',
								"items"       => $item_hash);
					}
				}
			}
		}

		return $direct;
	}

	function fetch_line_group_record($group_hash) {
		$result = $this->db->query("SELECT line_groups.* , COUNT(line_items.item_hash) AS total_group_items
				FROM `line_groups`
				LEFT JOIN line_items ON line_items.group_hash = line_groups.group_hash
				WHERE line_groups.group_hash = '$group_hash'
				GROUP BY line_groups.group_hash");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_group = $row;
			$this->group_hash = $group_hash;
			if ($this->current_group['total_group_items'] > 0) {
				$result = $this->db->query("SELECT line_items.item_hash
						FROM line_items
						WHERE line_items.group_hash = '$group_hash'");
				while ($row = $this->db->fetch_assoc($result))
					$this->current_group['group_items'][] = $row['item_hash'];
			}
			return true;
		}

		return false;
	}

	/**
	 * Determines whether the line item in question is the last item with a sell in its group.
	 * Return true if it is the last item, and false otherwise.  Added for Trac #478.
	 *
	 * @param string $group_hash
	 * @param string $line_no
	 * @param string $proposal_hash
	 * @return boolean isLast
	 */
	function is_last_sell_item_in_group($group_hash, $line_no, $proposal_hash) {
		$result = $this->db->query("SELECT line_no, sell
				FROM line_items
				WHERE group_hash = '$group_hash' AND line_no > '$line_no' AND proposal_hash = '$proposal_hash'
				ORDER BY line_no");
		$isLast = true;
		while ($row = $this->db->fetch_assoc($result)) {
			if ($row['sell'] > 0) {
				$isLast = false;
			}
		}
		return $isLast;
	}

	function fetch_line_comment_record($comment_hash) {
		$result = $this->db->query("SELECT line_comments.* , vendors.vendor_name
				FROM `line_comments`
				LEFT JOIN `vendors` ON vendors.vendor_hash = line_comments.vendor_hash
				WHERE `comment_hash` = '$comment_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_comment = $row;
			$this->comment_hash = $comment_hash;

			return true;
		}

		return false;
	}

	function calc_tax($item_array=NULL) {

		$r = $this->db->query("SELECT
				t2.obj_id AS valid_proposal, t1.gsa AS gsa_customer, t1.tax_exempt_id AS tax_exempt_customer,
				t1.gst_exempt, t1.pst_exempt, t1.hst_exempt, t1.qst_exempt
				FROM customers t1
				LEFT JOIN proposals t2 ON t2.customer_hash = t1.customer_hash
				WHERE t2.proposal_hash = '{$this->proposal_hash}'");
		if ( $this->db->result($r, 0, 'valid_proposal') ) {

			$gsa_customer = $this->db->result($r, 0, 'gsa_customer');
			if ( $tax_exempt_customer = $this->db->result($r, 0, 'tax_exempt_customer') ) {

				if ( $this->tax_country == 'CA' ) {

					if ( $this->db->result($r, 0, 'gst_exempt') )
						$tax_exempt_province['gst'] = 1;
					if ( $this->db->result($r, 0, 'pst_exempt') )
						$tax_exempt_province['pst'] = 1;
					if ( $this->db->result($r, 0, 'hst_exempt') )
						$tax_exempt_province['hst'] = 1;
					if ( $this->db->result($r, 0, 'qst_exempt') )
						$tax_exempt_province['qst'] = 1;
				}
			}

			if ( ! is_array($item_array) ) {

				$item_array = array();
				$r = $this->db->query("SELECT t1.item_hash
						FROM line_items t1
						WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t1.punch = " . ( $this->punch ? 1 : 0 ) . " AND t1.active = 1");
				while ( $row = $this->db->fetch_assoc($r) )
					array_push($item_array, $row['item_hash']);
			}

			# Resync the tax collected table so that the tax amount are accurate
			if ( is_array($item_array) ) {

				for ( $i = 0; $i < count($item_array); $i++ )
					$this->item_tax( $item_array[$i] );
			}

			if ( $gsa_customer || ( $this->tax_country == 'US' && $tax_exempt_customer ) )
				return;

			if ( $this->tax_country == 'CA' ) {

				$tax_map = array();
				$r = $this->db->query("SELECT t1.local, t1.rate, t1.tax_hash
						FROM tax_table t1
						WHERE t1.country = 'CA' AND t1.state = '{$this->tax_state}'");
				while ( $tax_row = $this->db->fetch_assoc($r) ) {

					if ( ! $tax_exempt_customer || ( $tax_exempt_customer && ! $tax_exempt_province[ $tax_row['local'] ] ) ) {

						$tax_map[ strtolower($tax_row['local']) ] = array(
								'tax_hash'  => $tax_row['tax_hash'],
								'rate'      => $tax_row['rate']
						);
					}
				}

				$item_sales = $tax_local = array();
				$r = $this->db->query("SELECT
						t1.obj_id, t1.tax_hash, t1.invoice_hash,
						ROUND( ( t2.sell * t2.qty ), 2) AS ext_sell,
						t2.item_hash, t3.local AS tax_local, t1.rate AS tax_rate,
						t3.rate AS current_rate, ROUND( ( t2.sell * t2.qty ) * t1.rate, 4) AS item_tax
						FROM tax_collected t1
						LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
						LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
						WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t2.punch = " . ( $this->punch ? 1 : 0 ) . " AND t2.active = 1 AND t2.invoice_hash = ''");
				while ( $row = $this->db->fetch_assoc($r) ) {

					if ( ! is_array($item_array) || ( is_array($item_array) && in_array($row['item_hash'], $item_array) ) ) {

						if ( ! $tax_exempt_customer || ( $tax_exempt_customer && ! $tax_exempt_province[ $row['tax_local'] ] ) ) {

							if ( ! $row['invoice_hash'] && bccomp($row['tax_rate'], $row['current_rate'], 5) ) {

								$row['tax_rate'] = $row['current_rate'];
								$this->db->query("UPDATE tax_collected
										SET timestamp = UNIX_TIMESTAMP(), rate = '{$row['current_rate']}'
										WHERE obj_id = '{$row['obj_id']}'");
							}

							$tax_local[ $row['tax_hash'] ] = $row['tax_local'];
							$item_sales[ $row['item_hash'] ][ $row['tax_local'] ] = array(
									'ext_sell'    =>  $row['ext_sell'],
									'tax_hash'    =>  $row['tax_hash'],
									'item_tax'    =>  $row['item_tax'],
									'rate'        =>  $row['tax_rate']
							);
						}
					}
				}

				$tax = $tax_table = array();
				$total_ext_sell = 0;
				while ( list($item_hash, $local_tax) = each($item_sales) ) {

					$k = 0;
					while ( list($local_tax_code, $item_details) = each($local_tax) ) {

						$tax_table[ $item_details['tax_hash'] ] = _round( bcadd($tax_table[ $item_details['tax_hash'] ], $item_details['item_tax'], 4), 2);
						if ( $k == 0 )
							$total_ext_sell = bcadd($total_ext_sell, $item_details['ext_sell']);

						$k++;
					}
				}

				if ( $this->tax_state == 'QC' || $this->tax_state == 'PE' ) {

					if ( $tax_table[ $tax_map['gst']['tax_hash'] ] && $tax_map['pst']['rate'] ) {

						$tax_table[ $tax_map['pst']['tax_hash'] ] = _round( bcmul( bcadd($total_ext_sell, $tax_table[ $tax_map['gst']['tax_hash'] ], 4), $tax_map['pst']['rate'], 4), 2);
					}
				}

				if ( bccomp($tax_table[ $tax_map['gst']['tax_hash'] ], 0, 2) == 1 && bccomp($tax_table[ $tax_map['pst']['tax_hash'] ], 0, 2) == 1 )
					$indiv_tax = 1;

				$total = 0;
				if ( is_array($tax_table) )
					$total = array_sum($tax_table);

				if ( bccomp($total, 0, 2) == 1 )
					return array($total, $tax_table, $tax_local, $indiv_tax);

				return 0;
			}

			$hasMaximum = false;
			$result = $this->db->query("SELECT
					t1.*,
					t3.maximum AS tax_maximum
					FROM tax_collected t1
					LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
					LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
					WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t2.punch = " . ( $this->punch ? 1 : 0 ) . " AND t2.active = 1");
			while ( $row = $this->db->fetch_assoc($result) ) {
				if($row['tax_maximum'] > 0){
					$hasMaximum = true;
					break;
				}
			}

			if($hasMaximum){
				$tax_hash = array();
				$result = $this->db->query("SELECT
						t1.*,
						t3.rate AS current_rate,
						t3.local AS tax_local,
						t3.maximum AS tax_maximum,
						ROUND( ( t2.sell * t2.qty ), 2) AS ext_sell ,
						ROUND( ( t2.sell * t2.qty ) * t1.rate, 4) AS item_tax
						FROM tax_collected t1
						LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
						LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
						WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t2.punch = " . ( $this->punch ? 1 : 0 ) . " AND t2.active = 1");
				while ( $row = $this->db->fetch_assoc($result) ) {
					 
					if ( ! $tax_exempt_customer ) {
						 
						if ( ! is_array($item_array) || ( is_array($item_array) && in_array($row['item_hash'], $item_array) ) ) { # Rate has change, update tax_collected table
							 
							if ( ! $row['invoice_hash'] && bccomp($row['rate'], $row['current_rate'], 5) ) {
							 
							$row['rate'] = $row['current_rate'];
							$this->db->query("UPDATE tax_collected
									SET timestamp = UNIX_TIMESTAMP(), rate = '{$row['current_rate']}'
									WHERE obj_id = '{$row['obj_id']}'");
							 
						}
						 
						if(empty($tax_hash) || !in_array($row['tax_hash'],$tax_hash))
							$tax_hash[] = $row['tax_hash'];

						$ext_sell[ $row['tax_hash'] ] = bcadd($ext_sell[ $row['tax_hash'] ],  $row['ext_sell'], 4);
						$maximum[ $row['tax_hash'] ] = $row['tax_maximum'];
						$rate[ $row['tax_hash'] ] = $row['current_rate'];
						$item_tax = $row['item_tax'];
						 
						$tax_local[ $row['tax_hash'] ] = $row['tax_local'];
						$tax_table[ $row['tax_hash'] ] = bcadd($tax_table[ $row['tax_hash'] ], $item_tax, 4);
						}
					}
				}
				 
				 
				for($i = 0;$i < count($tax_hash); $i++){
					$hash = $tax_hash[$i];
					if($maximum[$hash] > 0 && $ext_sell[$hash] > $maximum[$hash])
						$tax_table[$hash] = bcmul($maximum[$hash],  $rate[$hash]);
				}
				 
				$total = 0;
				if ( is_array($tax_table) )
					$total = array_sum($tax_table);
				 
				if ( bccomp($total, 0) == 1 )
					return array($total, $tax_table, $tax_local);
				 
			}else{
				$result = $this->db->query("SELECT
						t1.*,
						t3.rate AS current_rate,
						t3.local AS tax_local,
						ROUND( ( t2.sell * t2.qty ), 2) AS ext_sell ,
						ROUND( ( t2.sell * t2.qty ) * t1.rate, 4) AS item_tax
						FROM tax_collected t1
						LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
						LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
						WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t2.punch = " . ( $this->punch ? 1 : 0 ) . " AND t2.active = 1");
				while ( $row = $this->db->fetch_assoc($result) ) {
					 
					if ( ! $tax_exempt_customer ) {
						 
						if ( ! is_array($item_array) || ( is_array($item_array) && in_array($row['item_hash'], $item_array) ) ) { # Rate has change, update tax_collected table
							 
							if ( ! $row['invoice_hash'] && bccomp($row['rate'], $row['current_rate'], 5) ) {
							 
							$row['rate'] = $row['current_rate'];
							$this->db->query("UPDATE tax_collected
									SET timestamp = UNIX_TIMESTAMP(), rate = '{$row['current_rate']}'
									WHERE obj_id = '{$row['obj_id']}'");

						}
						 
						$ext_sell = $row['ext_sell'];
						$item_tax = $row['item_tax'];
						 
						$tax_local[ $row['tax_hash'] ] = $row['tax_local'];
						$tax_table[ $row['tax_hash'] ] = bcadd($tax_table[ $row['tax_hash'] ], $item_tax, 4);
						}
					}
				}
				 
				$total = 0;
				if ( is_array($tax_table) )
					$total = array_sum($tax_table);
				 
				if ( bccomp($total, 0) == 1 )
					return array($total, $tax_table, $tax_local);
			}

			return 0;
		}
	}

	function item_tax($item_hash) {

		if ( ! $this->tax_exempt || $this->tax_country == 'CA' ) {

			if ( $this->fetch_line_item_record($item_hash) && ! $this->current_line['invoice_hash'] ) {

				$r = $this->db->query("SELECT t1.timestamp
						FROM tax_collected t1
						WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t1.item_hash = '{$this->item_hash}'
						LIMIT 1");

				$last_tax_insert = $this->db->result($r, 0, 'timestamp');
				if ( ! $last_tax_insert || ( time() - $last_tax_insert > 120 ) ) {

					if ( $last_tax_insert > 0 ) {

						if ( ! $this->db->query("DELETE FROM tax_collected
								WHERE proposal_hash = '{$this->proposal_hash}' AND item_hash = '{$this->item_hash}'")
								) {

							$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
							return false;
						}
					}

					if ( $this->installation_hash ) {

						if ( $this->tax_country == 'CA' && $this->tax_state ) {

							$r = $this->db->query("SELECT t1.tax_hash, t1.rate, t1.local
									FROM tax_table t1
									WHERE t1.country = '{$this->tax_country}' AND t1.state = '{$this->tax_state}'");
							while ( $row2 = $this->db->fetch_assoc($r) ) {

								if ( ! $this->tax_exempt || ( $this->tax_exempt && ! $this->tax_exempt_province[ $row2['local'] ] ) ) {

									if ( ! $this->db->query("INSERT INTO tax_collected
											(
											proposal_hash,
											item_hash,
											tax_hash,
											rate,
											timestamp,
											punch
									)
											VALUES
											(
											'{$this->proposal_hash}',
											'{$this->item_hash}',
											'{$row2['tax_hash']}',
											'{$row2['rate']}',
											UNIX_TIMESTAMP(),
											" . ( $this->punch ? '1' : '0' ) . "
											)")
											) {

										$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
										return false;
									}
								}
							}

						} elseif ( $this->tax_country == 'US' && $this->current_line['taxable_product'] ) {

							$r = $this->db->query("SELECT t1.tax_hash, t1.rate
									FROM tax_table t1
									LEFT JOIN product_tax t2 ON t2.tax_hash = t1.tax_hash
									LEFT JOIN location_tax t3 ON t3.tax_hash = t1.tax_hash
									WHERE t2.product_hash = '{$this->current_line['product_hash']}' AND t3.location_hash = '{$this->installation_hash}' AND t1.active = 1
									GROUP BY t1.tax_hash");
							while ( $row = $this->db->fetch_assoc($r) ) {

								if ( ! $this->db->query("INSERT INTO tax_collected
										(
										proposal_hash,
										item_hash,
										tax_hash,
										rate,
										timestamp,
										punch
								)
										VALUES
										(
										'{$this->proposal_hash}',
										'{$this->item_hash}',
										'{$row['tax_hash']}',
										'{$row['rate']}',
										UNIX_TIMESTAMP(),
										" . ( $this->punch ? '1' : '0' ) . "
										)")
										) {

									$this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
									return false;
								}
							}
						}
					}
				}
			}
		}

		return true;
	}

	function tax_lock($hash) {
		$loc = explode("|", $hash);
		if ($loc < 2 || $loc[1] == 'vendors')
			return 2;

		$r = $this->db->query("SELECT ".(count($loc) == 3 ? "locations.location_tax_lock" : "customers.tax_lock")."
				FROM ".(count($loc) == 3 ? "locations" : "customers")."
				WHERE ".(count($loc) == 3 ? "locations.location_hash" : "customers.customer_hash")." = '".(count($loc) == 3 ? $loc[2] : $loc[1])."'");
		if ($this->db->result($r))
			return true;

		return false;
	}

	function fetch_invoice_tax($invoice_hash, $item_list=NULL) {
		if ( ! $invoice_hash )
			return;

		if ( isset($item_list) && ! is_array($item_list) )
			$item_list = array($item_list);

		if ( $this->tax_country == 'CA' ) {

			$tax_map = array();
			$r = $this->db->query("SELECT t2.local, t1.rate, t1.tax_hash
					FROM tax_collected t1
					LEFT JOIN tax_table t2 ON t2.tax_hash = t1.tax_hash
					WHERE t1.invoice_hash = '$invoice_hash'
					GROUP BY t1.tax_hash");
			while ( $row = $this->db->fetch_assoc($r) )
				$tax_map[ strtolower($row['local']) ] = array(
						'tax_hash'  => $row['tax_hash'],
						'rate'      => $row['rate']
				);


			$item_sales = $tax_local = array();
			$r = $this->db->query("SELECT t1.tax_hash, ROUND(t2.sell * t2.qty, 2) AS ext_sell, t2.item_hash ,
					t3.local AS tax_local, t1.rate AS tax_rate, ROUND((t2.sell * t2.qty) * t1.rate, 4) AS item_tax
					FROM tax_collected t1
					LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
					LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
					WHERE t1.invoice_hash = '$invoice_hash'");
			while ( $row = $this->db->fetch_assoc($r) ) {

				if ( ! isset($item_list) || ( isset($item_list) && in_array($row['item_hash'], $item_list) ) ) {
					$tax_local[ $row['tax_hash'] ] = $row['tax_local'];
					$item_sales[ $row['item_hash'] ][ $row['tax_local'] ] = array(
							'ext_sell'    =>  $row['ext_sell'],
							'tax_hash'    =>  $row['tax_hash'],
							'item_tax'    =>  $row['item_tax'],
							'rate'        =>  $row['tax_rate']
					);
				}
			}

			$tax = $tax_table = array();
			$total_ext_sell = 0;

			while ( list($item_hash, $local_tax) = each($item_sales) ) {

				$k = 0;
				while ( list($local_tax_code, $item_details) = each($local_tax) ) {

					$tax_table[ $item_details['tax_hash'] ] = _round( bcadd($tax_table[ $item_details['tax_hash'] ], $item_details['item_tax'], 4), 2);
					if ( $k == 0 )
						$total_ext_sell = bcadd($total_ext_sell, $item_details['ext_sell'], 2);

					$k++;
				}
			}

			if ( $this->tax_state == 'QC' || $this->tax_state == 'PE' ) {

				if ( $tax_table[ $tax_map['gst']['tax_hash'] ] && $tax_map['pst'] )
					$tax_table[ $tax_map['pst']['tax_hash'] ] = _round( bcmul( bcadd($total_ext_sell, $tax_table[ $tax_map['gst']['tax_hash'] ], 4), $tax_map['pst']['rate'], 4), 2);

			}

			if ( bccomp($tax_table[$tax_map['gst']['tax_hash']], 0, 2) == 1 && bccomp($tax_table[$tax_map['pst']['tax_hash']], 0, 2) == 1 )
				$indiv_tax = 1;

			$total = 0;
			if ( is_array($tax_table) )
				$total = array_sum($tax_table);

			return ( bccomp($total, 0, 2) == 1 ? array($total, $tax_table, $tax_local, $indiv_tax) : false );
		}

		$hasMaximum = false;
		$result = $this->db->query("SELECT
				t1.*,
				t3.maximum AS tax_maximum
				FROM tax_collected t1
				LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
				LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
				WHERE t1.proposal_hash = '{$this->proposal_hash}' AND t2.punch = " . ( $this->punch ? 1 : 0 ) . " AND t2.active = 1");
		while ( $row = $this->db->fetch_assoc($result) ) {
			if($row['tax_maximum'] > 0){
				$hasMaximum = true;
				break;
			}
		}

		if($hasMaximum){
			$tax_hash = array();
			$total_tax = 0;
			$r = $this->db->query("SELECT
					t1.*,
					t3.rate AS current_rate,
					t3.local AS tax_local,
					t3.maximum AS tax_maximum,
					ROUND( ( t2.sell * t2.qty ), 2) AS ext_sell ,
					ROUND( ( t2.sell * t2.qty ) * t1.rate, 4) AS tax_amount
					FROM tax_collected t1
					LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
					LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
					WHERE t1.invoice_hash = '$invoice_hash' " .
					( isset( $item_list ) && count( $item_list ) == 1 ?
							"AND t1.item_hash = '{$item_list[0]}' " : NULL
							) );
			while ( $row = $this->db->fetch_assoc($r) ) {

				if ( ! isset($item_list) || ( isset($item_list) && in_array($row['item_hash'], $item_list) ) ) {

					$total_tax = bcadd($total_tax, $row['tax_amount'], 4);
					$tax_table[ $row['tax_hash'] ] = bcadd($tax_table[ $row['tax_hash'] ], $row['tax_amount'], 4);
					$tax_local[ $row['tax_hash'] ] = $row['tax_local'];
					$ext_sell[ $row['tax_hash'] ] = bcadd($ext_sell[ $row['tax_hash'] ],  $row['ext_sell'], 4);
					$maximum[ $row['tax_hash'] ] = $row['tax_maximum'];
					$rate[ $row['tax_hash'] ] = $row['current_rate'];

					if(empty($tax_hash) || !in_array($row['tax_hash'],$tax_hash))
						$tax_hash[] = $row['tax_hash'];

				}
			}
			 
			for($i = 0;$i < count($tax_hash); $i++){
				$hash = $tax_hash[$i];
				if($maximum[$hash] > 0 && $ext_sell[$hash] > $maximum[$hash])
					$tax_table[$hash] = bcmul($maximum[$hash],  $rate[$hash]);
			}
			 
			if ( is_array($tax_table) )
				$total_tax = array_sum($tax_table);
			 
		}else{
			$total_tax = 0;
			$r = $this->db->query("SELECT
					t1.tax_hash,
					t1.item_hash,
					t3.local AS tax_local,
					ROUND( ( t2.sell * t2.qty ) * t1.rate, 4) AS tax_amount
					FROM tax_collected t1
					LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
					LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
					WHERE t1.invoice_hash = '$invoice_hash' " .
					( isset( $item_list ) && count( $item_list ) == 1 ?
							"AND t1.item_hash = '{$item_list[0]}' " : NULL
							) );
			while ( $row = $this->db->fetch_assoc($r) ) {

				if ( ! isset($item_list) || ( isset($item_list) && in_array($row['item_hash'], $item_list) ) ) {

					$total_tax = bcadd($total_tax, $row['tax_amount'], 4);
					$tax_table[ $row['tax_hash'] ] = bcadd($tax_table[ $row['tax_hash'] ], $row['tax_amount'], 4);
					$tax_local[ $row['tax_hash'] ] = $row['tax_local'];
				}
			}
		}

		return array($total_tax, $tax_table, $tax_local);
	}

	function set_invoice_tax_flags($invoice_hash) {

		if ( ! $invoice_hash )
			return false;

		$r = $this->db->query("SELECT t1.install_hash, t1.install_state, t1.install_country
				FROM customer_invoice t1
				WHERE t1.invoice_hash = '$invoice_hash'");
		if ( $row = $this->db->fetch_assoc($r) ) {

			$install_hash = $row['install_hash'];
			if ( $row['install_state'] && $row['install_country'] ) {

				$this->tax_country = $row['install_country'];
				$this->tax_state = $row['install_state'];
			}
			if ( $row['install_hash'] )
				$this->installation_hash = $row['install_hash'];
		}
	}

	function update_sales_tax( $item_array=NULL ) {

		if ( ! $item_array || ! is_array($item_array) ) {

			$item_array = array();
			$r = $this->db->query("SELECT item_hash
					FROM line_items
					WHERE proposal_hash = '{$this->proposal_hash}' AND invoice_hash = '' AND punch = " . ( $this->punch ? "1" : "0" ) . " AND active = 1");
			while ( $row = $this->db->fetch_assoc($r) )
				$item_array[] = $row['item_hash'];
		}

		for ( $i = 0; $i < count($item_array); $i++ )
			$this->item_tax($item_array[$i]);
	}
	/**
	 * If return_arary is true, return an array of tax rules with amount, otherwise return the total tax for a given item_hash. Added for Trac #1119.
	 *
	 * @param string $item_hash
	 * @param boolean $return_array
	 * @param boolean $credits_only
	 * @param string $credit_hash
	 * @return unknown
	 */
	function fetch_item_tax($item_hash, $return_array=false, $credits_only=false) {

		if ( ! $item_hash ) {

			$this->__trigger_error(__METHOD__ . " called without required argument [item_hash]", E_USER_ERROR, __FILE__, __LINE__);
			return;
		}

		if ( $return_array ) {

			if ( $credits_only ) {

				$r = $this->db->query("SELECT ROUND( ( t1.amount * -1 ), 4) AS tax, t1.tax_hash, t1.account_hash, t1.rate
						FROM tax_collected t1
						WHERE t1.item_hash = '$item_hash'");
			} else {

				$r = $this->db->query("SELECT ROUND( ( t2.sell * t2.qty ) * t1.rate, 4) AS tax, t1.tax_hash, t1.account_hash, t1.rate
						FROM tax_collected t1
						LEFT JOIN line_items t2 ON t1.item_hash = t2.item_hash
						WHERE t1.item_hash = '$item_hash'");
			}

			while ( $row = $this->db->fetch_assoc($r) ) {

				$tax_arr[ $row['tax_hash'] ] = $row;
			}

			return $tax_arr;

		} else {

			if ( $credits_only ) {

				$r = $this->db->query("SELECT ROUND( SUM( t1.amount * -1 ), 4) AS tax
						FROM tax_collected t1
						WHERE t1.item_hash = '$item_hash'");
			} else {

				$r = $this->db->query("SELECT ROUND( SUM( ( t2.sell * t2.qty) * t1.rate ), 4) AS tax
						FROM tax_collected t1
						LEFT JOIN line_items t2 ON t1.item_hash = t2.item_hash
						WHERE t1.item_hash = '$item_hash'");
			}

			$tax = $this->db->result($r, 0, 'tax');

			return ( $tax ? $tax : 0 );
		}
	}

	/**
	 * Insert new row into tax collected table for customer credit adjustments to tax. Added for Trac #1119.
	 * Note that the tax amount entered into the tax collected table is the extended amount, not unit.
	 *
	 * @param string $item_hash
	 * @param string $tax_hash
	 * @param double $amount
	 * @param string $credit_hash
	 */
	# TODO: 4 JUNE 2010 - DEPRECIATED
	function credit_item_tax($item_hash, $tax_hash, $amount, $credit_hash) {

		if ( ! bccomp($amount, 0, 4) )
			return true;

		$result = $this->db->query("SELECT t1.*
				FROM tax_collected t1
				WHERE t1.item_hash = '$item_hash' AND t1.tax_hash = '$tax_hash' AND t1.credit_hash = ''");
		if ( $row = $this->db->fetch_assoc($result) ) {

			if ( $this->db->query("INSERT INTO tax_collected
					( timestamp, proposal_hash, item_hash, tax_hash, rate, punch, invoice_hash, account_hash, amount, credit_hash )
					VALUES( UNIX_TIMESTAMP(), '{$row['proposal_hash']}', '{$row['item_hash']}', '{$row['tax_hash']}', '{$row['rate']}', '{$row['punch']}', '{$row['invoice_hash']}', '{$row['account_hash']}', '" . bcmul($amount, -1, 4) . "', '$credit_hash' )")
					) {

				return true;
			}
		}

		return false;
	}

	/**
	 * Find items applied on a given customer credit
	 *
	 * @param string $credit_hash
	 * @return array with item hash as index and amount applied as value
	 */
	function fetch_credit_items($credit_hash) {
		$result = $this->db->query("SELECT t1.amount, t1.item_hash
				FROM customer_credit_item t1
				LEFT JOIN customer_credit t2 on t1.credit_hash = t2.credit_hash
				WHERE t1.credit_hash = '$credit_hash' and t2.deleted = 0");
		while ($row = $this->db->fetch_assoc($result))
			$tax_items[$row['item_hash']] = $row['amount'];

		return $tax_items;
	}
}
?>