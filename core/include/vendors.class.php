<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed

class vendors extends AJAX_library {

	public $total;
	public $total_contacts;
	public $total_locations;
	public $total_products;

	public $vendor_hash;
    public $contact_hash;
    public $location_hash;
    public $product_hash;

	public $vendor_info = array();
	public $vendor_contacts = array();
	public $vendor_locations = array();
	public $vendor_products = array();

	public $current_vendor;
	public $current_contact;
	public $current_location;
	public $current_product;

	public $ajax_vars;

	private $form;
	private $check;

	function vendors($passedHash=NULL) {
		global $db;

		$this->form = new form;
		$this->db =& $db;

		if ($passedHash)
			$this->current_hash = $passedHash;
		else
			$this->current_hash = $_SESSION['id_hash'];

		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);

        if ($_POST['active_search'])
            $result = $this->db->query("SELECT `total` AS Total
                                        FROM `search`
                                        WHERE `search_hash` = '".$_POST['active_search']."'");
        else
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `vendors`
										WHERE vendors.deleted = 0");

		$this->total = $this->db->result($result,0,'Total');
        $this->cust = new custom_fields(get_class($this));
	}

	function __destruct() {
		$this->content = '';
	}

	function fetch_vendors($start,$order_by=NULL,$order_dir=NULL,$showall=NULL) {
		if (!$end)
			$end = MAIN_PAGNATION_NUM;

		if ( ! defined('ACCOUNT_AGING_SCHED1') )
			define('ACCOUNT_AGING_SCHED1', 30);
		if ( ! defined('ACCOUNT_AGING_SCHED2') )
			define('ACCOUNT_AGING_SCHED2', 60);
		if ( ! defined('ACCOUNT_AGING_SCHED3') )
			define('ACCOUNT_AGING_SCHED3', 90);

		if ($this->active_search) {
			$result = $this->db->query("SELECT `query` , `total` , `search_str`
									    FROM `search`
									    WHERE `search_hash` = '".$this->active_search."'");
			$row = $this->db->fetch_assoc($result);
			$this->total = $row['total'];
			$sql = base64_decode($row['query']);
            $this->search_vars = $this->p->load_search_vars($row['search_str']);
		}
        if ($showall)
            $result = $this->db->query("SELECT vendors.obj_id
                                        FROM `vendors`
                                        LEFT JOIN discounting ON discounting.vendor_hash = vendors.vendor_hash AND discounting.deleted = 0
                                        LEFT JOIN vendor_products ON vendor_products.vendor_hash = vendors.vendor_hash AND vendor_products.deleted = 0
                                        WHERE ".($sql ? $sql." AND " : NULL)."vendors.deleted = 0
                                        GROUP BY vendors.obj_id".($order_by ? "
                                        ORDER BY $order_by ".($order_dir ? $order_dir : "ASC") : NULL)."
                                        LIMIT $start , $end");
        else
			$result = $this->db->query("SELECT vendors.vendor_hash , vendors.vendor_name , vendors.city ,
			                            vendors.state , vendors.account_no ,
										(SUM(CASE
											WHEN (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN vendor_payables.type = 'C' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN vendor_payables.type = 'D' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END)
										) AS balance_total ,
										(SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END)
										) AS balance_current ,
										(SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END)
										) AS balance_sched1 ,
										(SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END)
										) AS balance_sched2 ,
										(SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END) -
										SUM(CASE
											WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
												THEN vendor_payables.balance
												ELSE 0
											END)
										) AS balance_sched3
								  		FROM `vendors`
										LEFT JOIN vendor_payables ON vendor_payables.vendor_hash = vendors.vendor_hash AND vendor_payables.deleted = 0
										LEFT JOIN discounting ON discounting.vendor_hash = vendors.vendor_hash AND discounting.deleted = 0
										LEFT JOIN vendor_products ON vendor_products.vendor_hash = vendors.vendor_hash AND vendor_products.deleted = 0
								  		WHERE ".($sql ? $sql." AND " : NULL)."vendors.deleted = 0
										GROUP BY vendors.vendor_hash".($order_by ? "
								  		ORDER BY $order_by ".($order_dir ? $order_dir : "ASC") : NULL)."
								  		LIMIT $start , $end");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->vendor_info,$row);

	}

	function fetch_master_record($vendor_hash,$edit=false) {
		if (!$vendor_hash)
			return;

		if ( ! defined('ACCOUNT_AGING_SCHED1') )
			define('ACCOUNT_AGING_SCHED1', 30);
		if ( ! defined('ACCOUNT_AGING_SCHED2') )
			define('ACCOUNT_AGING_SCHED2', 60);
		if ( ! defined('ACCOUNT_AGING_SCHED3') )
			define('ACCOUNT_AGING_SCHED3', 90);

		$result = $this->db->query("SELECT vendors.* ,
									(SUM(CASE
										WHEN (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN vendor_payables.type = 'C' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN vendor_payables.type = 'D' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END)
									) AS balance_total ,
									(SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END)
									) AS balance_current ,
									(SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END)
									) AS balance_sched1 ,
									(SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END)
									) AS balance_sched2 ,
									(SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END) -
									SUM(CASE
										WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
											THEN vendor_payables.balance
											ELSE 0
										END)
									) AS balance_sched3
									FROM `vendors`
									LEFT JOIN vendor_payables ON vendor_payables.vendor_hash = vendors.vendor_hash AND vendor_payables.deleted = 0
									WHERE vendors.vendor_hash = '$vendor_hash'
									GROUP BY vendors.vendor_hash");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_vendor = $row;
			$this->vendor_hash = $this->current_vendor['vendor_hash'];

			//small order fee
			if ($this->current_vendor['sof']) {
				$sof = explode("|",$this->current_vendor['sof']);
				foreach ($sof as $sof_el) {
					list($a,$b) = explode("=",$sof_el);
					$this->current_vendor[$a] = $b;
				}
			}
			//freight fee
			if ($this->current_vendor['frf']) {
				$frf = explode("|",$this->current_vendor['frf']);
				foreach ($frf as $frf_el) {
					list($a,$b) = explode("=",$frf_el);
					$this->current_vendor[$a] = $b;
				}
			}
			//fuel surcharge
			if ($this->current_vendor['fsf']) {
				$fsf = explode("|",$this->current_vendor['fsf']);
				foreach ($fsf as $fsf_el) {
					list($a,$b) = explode("=",$fsf_el);
					$this->current_vendor[$a] = $b;
				}
			}
			//CBD Fee
			if ($this->current_vendor['cbd']) {
				$cbd = explode("|",$this->current_vendor['cbd']);
				foreach ($cbd as $cbd_el) {
					list($a,$b) = explode("=",$cbd_el);
					$this->current_vendor[$a] = $b;
				}
			}

			$result = $this->db->query("SELECT COUNT(*) AS Total
									    FROM `locations`
									    WHERE `entry_hash` = '{$this->current_vendor['vendor_hash']}' && `entry_type` = 'v' AND `deleted` = 0");
			$this->total_locations = $this->db->result($result);

			$result = $this->db->query("SELECT COUNT(*) AS Total
									    FROM `vendor_contacts`
									    WHERE `vendor_hash` = '{$this->current_vendor['vendor_hash']}' AND `deleted` = 0");
			$this->total_contacts = $this->db->result($result);

			$result = $this->db->query("SELECT COUNT(*) AS Total
									    FROM `vendor_products`
									    WHERE `vendor_hash` = '{$this->current_vendor['vendor_hash']}' AND `deleted` = 0");
			$this->total_products = $this->db->result($result);

			$this->discounting = new discounting($this->vendor_hash,'V');
			if ($edit)
				$this->lock = $this->content['row_lock'] = $this->p->lock("vendors",$this->vendor_hash,$this->popup_id);

			return true;
		}

		return false;
	}


	function fetch_locations($start, $order_by=NULL, $order_dir=NULL) {

		$end = SUB_PAGNATION_NUM;
        $this->vendor_locations = array();
        $_total = 0;

		$result = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
								    FROM locations t1
								    WHERE t1.entry_hash = '{$this->vendor_hash}' && t1.entry_type = 'v' AND t1.deleted = 0");
		$this->total_locations = $this->db->result($result);

		$result = $this->db->query("SELECT t1.*
								    FROM locations t1
								    WHERE t1.entry_hash = '{$this->vendor_hash}' && t1.entry_type = 'v' AND t1.deleted = 0 " .
								    ( $order_by ?
    								    "ORDER BY $order_by " .
    								    ( $order_dir ?
        								    $order_dir : "DESC"
        								) : NULL
        							) . "
								    LIMIT $start, $end");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$_total++;
			array_push($this->vendor_locations, $row);
		}

        return $_total;
	}

	function fetch_location_record($location_hash) {

		$result = $this->db->query("SELECT locations.*
								    FROM `locations`
								    WHERE locations.location_hash = '$location_hash'");
		if ($row = $this->db->fetch_assoc($result)) {

			$this->location_hash = $location_hash;
			$this->current_location = $row;

			return true;
		}

		return false;
	}

	function fetch_contacts($start, $order_by=NULL, $order_dir=NULL) {

		if ( $param = func_get_arg(0) ) {

        	$start = $param['start_from'];
			$end = $param['end'];
			$order_by = $param['order_by'];
			$order_dir = $param['order_dir'];
        }

        if ( ! $end )
			$end = SUB_PAGNATION_NUM;

		$this->vendor_contacts = array();
		$_total = 0;

		$result = $this->db->query("SELECT COUNT(*) AS Total
							  	    FROM vendor_contacts
							  		WHERE vendor_hash = '{$this->vendor_hash}' AND deleted = 0");
		$this->total_contacts = $this->db->result($result, 0, 'Total');

        if ( ! $this->total_contacts )
            return 0;

		$result = $this->db->query("SELECT t1.*
								    FROM vendor_contacts t1
								    WHERE t1.vendor_hash = '{$this->vendor_hash}' AND t1.deleted = 0 " .
								    ( $order_by ?
    								    "ORDER BY $order_by " .
    								    ( $order_dir ?
        								    $order_dir : "DESC"
        								) : NULL
        							) . "
								    LIMIT $start, $end");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$_total++;
			array_push($this->vendor_contacts, $row);
		}

        return $_total;
	}

	function fetch_contact_record($contact_hash) {

		$result = $this->db->query("SELECT vendor_contacts.*
							  		FROM `vendor_contacts`
							  		WHERE vendor_contacts.contact_hash = '$contact_hash'");
		if ($row = $this->db->fetch_assoc($result)) {

			$this->contact_hash = $contact_hash;
			$this->current_contact = $row;

			return true;
		}

		return false;
	}
	function fetch_products($start,$end=NULL,$order_by=NULL,$order_dir=NULL) {
		if (!$end)
			$end = SUB_PAGNATION_NUM;

		$result = $this->db->query("SELECT COUNT(*) AS Total
							  	    FROM `vendor_products`
							  		WHERE `vendor_hash` = '{$this->current_vendor['vendor_hash']}' AND `deleted` = 0");
		$this->total_products = $this->db->result($result);

		if ($this->total_products) {
			if (!$order_by)
				$order_by = "vendor_products.product_name";

			$result = $this->db->query("SELECT vendor_products.*
									    FROM `vendor_products`
									    WHERE `vendor_hash` = '{$this->current_vendor['vendor_hash']}' AND `deleted` = 0".($order_by ? "
									    ORDER BY $order_by ".($order_dir ? $order_dir : "ASC") : NULL)."
									    LIMIT $start , $end");
			while ($row = $this->db->fetch_assoc($result))
				array_push($this->vendor_products,$row);

		}
	}

	function fetch_product_record($product_hash) {

		$result = $this->db->query("SELECT vendor_products.*
							  		FROM `vendor_products`
							  		WHERE vendor_products.product_hash = '$product_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_product = $row;
			$this->product_hash = $product_hash;

			# Freight Fee
			if ($this->current_product['product_frf']) {
				$product_frf = explode("|",$this->current_product['product_frf']);
				foreach ($product_frf as $product_frf_el) {
					list($a,$b) = explode("=",$product_frf_el);
					$this->current_product[$a] = $b;
				}
			}
			if ($this->current_product['product_taxable']) {
				$r = $this->db->query("SELECT `tax_hash`
									   FROM `product_tax`
									   WHERE `product_hash` = '$product_hash'");
				while ($row2 = $this->db->fetch_assoc($r))
					$this->current_product['tax_hash'][] = $row2['tax_hash'];

			}

			return true;
		}

		return false;
	}

	function fetch_from_cat_code($code,$vendor_hash=NULL) {
		if (!$code)
			return;

		list($vendor_hash,$product_hash) = explode("|",$vendor_hash);

		$result = $this->db->query("SELECT vendors.vendor_name , vendors.vendor_hash ,
									vendor_products.product_name , vendor_products.product_hash
									FROM `vendors`
									LEFT JOIN `vendor_products` ON vendor_products.vendor_hash = vendors.vendor_hash AND vendor_products.deleted = 0 AND vendor_products.product_active = 1
									WHERE ".($vendor_hash ?
		                                "vendors.vendor_hash = '$vendor_hash' AND ".($product_hash ?
		                                    "vendor_products.product_hash = '$product_hash' AND " : NULL) : NULL).
		                            "vendor_products.catalog_code = '$code' AND vendors.active = 1 AND vendors.deleted = 0");
        while ($row = $this->db->fetch_assoc($result))
			$vendor[] = $row;

		return $vendor;
	}

	function ck_product_vendor($vendor_hash,$product_hash) {
		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `vendor_products`
									WHERE `vendor_hash` = '$vendor_hash' AND `product_hash` = '$product_hash' AND `deleted` = 0");
		if ($this->db->result($result))
			return true;
		else
			return false;
	}

	function fetch_vendor_charges($vendor_hash,$product_hash,$discount_item_hash=NULL) {
		if (!vendors::ck_product_vendor($vendor_hash,$product_hash))
			unset($product_hash);

		$result = $this->db->query("SELECT vendors.vendor_name , vendors.sof , vendors.sof_quoted , vendors.frf ,
									vendors.frf_quoted , vendors.fsf , vendors.fsf_quoted , vendors.cbd ".($product_hash ? ", vendor_products.product_name ,
									vendor_products.product_frf , vendor_products.product_frf_quoted" : NULL)."
									FROM vendors".($product_hash ? "
									LEFT JOIN vendor_products ON vendor_products.vendor_hash = vendors.vendor_hash" : NULL)."
									WHERE vendors.vendor_hash = '$vendor_hash' ".($product_hash ? "AND vendor_products.product_hash = '$product_hash'" : NULL));
		$charges = $this->db->fetch_assoc($result);
		if ($charges['product_frf'])
			unset($charges['frf'],$charges['frf_quoted']);

		if ($discount_item_hash) {
			$result = $this->db->query("SELECT `discount_frf` , `discount_frf_quoted`
										FROM `discount_details`
										WHERE `item_hash` = '$discount_item_hash'");
			$row = $this->db->fetch_assoc($result);
			if ($row['discount_frf'])
				$charges['product_frf'] = $row['discount_frf'];
			if ($row['discount_frf_quoted'] == 1)
				$charges['product_frf_quoted'] = $row['discount_frf_quoted'];

		}

		return $charges;
	}

	function doit_vendor_location() {

        if ( func_num_args() > 0 ) {

            $vendor_hash = func_get_arg(0);
            $new_vendor = func_get_arg(1);
        }

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

		if ( ! $new_vendor && ! $this->current_vendor )
    		$this->fetch_master_record($vendor_hash);

        if ( $location_hash = $_POST['location_hash'] ) {

            if ( ! $this->fetch_location_record($location_hash) )
                return $this->__trigger_error("A system error was encountered when trying to lookup vendor location for database update. Please re-load vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

            $edit_location = true;
            $action_delete = $_POST['delete'];
        }

		$location['location_name'] = addslashes( trim($_POST['location_name']) );
        $location['location_account_no'] = $_POST['location_account_no'];
		$location['location_street'] = addslashes( trim($_POST['location_street']) );
		$location['location_city'] = addslashes( trim($_POST['location_city']) );
		$location['location_state'] = $_POST['location_state'];
		$location['location_zip'] = $_POST['location_zip'];
		$location['location_country'] = $_POST['location_country'];

        if ( ! $location['location_name'] || ! $location['location_city'] || ! $location['location_state'] || ! $location['location_country'] ) {

            if ( ! $location['location_name'] ) $this->set_error("err4_1{$this->popup_id}");
            if ( ! $location['location_city'] ) $this->set_error("err4_3{$this->popup_id}");
            if ( ! $location['location_state'] ) $this->set_error("err4_4{$this->popup_id}");
            if ( ! $location['location_country'] ) $this->set_error("err4_5a{$this->popup_id}");

            if ( $new_customer ) {

                $this->__trigger_error("Please check that you have completed all required vendor location fields. Missing or invalid fields are indicated in red.", E_USER_NOTICE, __FILE__, __LINE__);
                return false;
            }

            return $this->__trigger_error("Please check that you have completed all required fields, indicated below.", E_USER_NOTICE, __FILE__, __LINE__, 1);
        }

		if ( ! $this->check->is_zip($location['location_zip']) ) {

			$err = 1;
			$this->content['form_return']['feedback'][] = $this->check->ERROR;
			$this->set_error("err4_5{$this->popup_id}");

			return false;
		}

        if ( $custom = $_POST['custom'] ) {

            while ( list($cust_key, $cust_val) = each($custom) ) {

                $this->cust->fetch_custom_field($cust_key);
                if ( $this->cust->current_field['required'] && ! trim($cust_val) ) {

                    $this->set_error("err_custom{$this->cust->current_field['obj_id']}");
                    $err = 1;
                    $e = 1;
                }

                $location[ $this->cust->current_field['col_name'] ] = trim($cust_val);
            }
            if ( $e )
                $this->set_error_msg("Please check that you have completed the required vendor location fields indicated below.");
        }

        if ( $err ) {

            if ( $new_vendor )
                return false;
            else
                return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
        }

        if ( ! $new_vendor ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
        }

		if ( $edit_location ) {

			if ( $action_delete ) {

				if ( ! $this->db->query("UPDATE locations t1
						                 SET
    						                 t1.deleted = 1,
    						                 t1.deleted_date = CURDATE()
										 WHERE t1.location_hash = '{$this->location_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				$this->content['page_feedback'] = "Your location has been deleted.";

			} else {

                $location = $this->db->prepare_query($location, "locations", 'UPDATE');
                if ( ! $this->db->query("UPDATE locations
		                                 SET
		                                     timestamp = UNIX_TIMESTAMP(),
		                                     last_change = '{$this->current_hash}',
		                                      " . implode(", ", $location) . "
		                                 WHERE location_hash = '{$this->location_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

                $this->content['page_feedback'] = "Your location has been updated";
			}

			unset($this->current_location);

		} else {

			$location_hash = rand_hash('locations', 'location_hash');

            $location = $this->db->prepare_query($location, "locations", 'INSERT');
            if ( ! $this->db->query("INSERT INTO locations
		                             (
		                                  timestamp,
		                                  last_change,
		                                  entry_hash,
		                                  entry_type,
		                                  location_hash,
		                                  " . implode(", ", array_keys($location) ) . "
		                              )
		                              VALUES
		                              (
		                                  UNIX_TIMESTAMP(),
		                                  '{$this->current_hash}',
		                                  '$vendor_hash',
		                                  'v',
		                                  '$location_hash',
		                                  " . implode(", ", array_values($location) ) . "
		                              )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            $this->content['page_feedback'] = "Location has been added";

		}

        if ( $new_vendor )
            return true;

        $this->db->end_transaction();

		unset($this->ajax_vars);
		if ( $vendor_hash ) {

			$this->fetch_master_record($vendor_hash);
			$this->ajax_vars['vendor_hash'] = $vendor_hash;
		}

		$this->ajax_vars['p'] = $p;
		$this->ajax_vars['order'] = $order;
		$this->ajax_vars['order_dir'] = $order_dir;

		$this->content['action'] = 'continue';
		$this->content['html']["tcontent4{$this->popup_id}"] = $this->location_info_form();

		return;

	}

	function doit_vendor_contact() {

        if ( func_num_args() > 0 ) {

            $vendor_hash = func_get_arg(0);
            $new_vendor = func_get_arg(1);
        }

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

        if ( ! $new_vendor && ! $this->current_vendor )
            $this->fetch_master_record($vendor_hash);

		if ( $contact_hash = $_POST['contact_hash'] ) {

			if ( ! $this->fetch_contact_record($contact_hash) )
    			return $this->__trigger_error("A system error was encountered when trying to lookup vendor contact for database update. Please re-load vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

			$edit_contact = true;
			$action_delete = $_POST['delete'];
		}

		$contact['contact_name'] = addslashes( trim($_POST['contact_name']) );
		$contact['contact_title'] = addslashes( trim($_POST['contact_title']) );
		$contact['contact_phone1'] = $_POST['contact_phone'];
		$contact['contact_phone2'] = $_POST['contact_phone2'];
		$contact['contact_mobile'] = $_POST['contact_mobile'];
		$contact['contact_fax'] = $_POST['contact_fax'];
		$contact['contact_email'] = $_POST['contact_email'];
		$contact['contact_user_name'] = $_POST['contact_user_name'];
		$contact['contact_password'] = $_POST['contact_password'];

        if ( ! $contact['contact_name'] ) {

            if ( ! $contact['contact_name'] ) $this->set_error("err3_1{$this->popup_id}");

            if ( $new_customer ) {

                $this->__trigger_error("Please check that you have completed all required vendor contact fields. Missing or invalid fields are indicated in red.", E_USER_NOTICE, __FILE__, __LINE__);
                return false;
            }

            return $this->__trigger_error("Please check that you have completed all required fields, indicated below.", E_USER_NOTICE, __FILE__, __LINE__, 1);
        }

		if ( $contact['contact_email'] && ! $this->check->is_email($contact['contact_email']) ) {

			$err = 1;
			$this->set_error_msg($this->check->ERROR);
			$this->set_error("err3_7{$this->popup_id}");
		}
		if ( $contact['contact_fax'] && ! $this->check->is_phone($contact['contact_fax']) ) {

			$err = 1;
			$this->set_error_msg($this->check->ERROR);
			$this->set_error("err3_6{$this->popup_id}");
		}

        if ( $custom = $_POST['custom'] ) {

            while ( list($cust_key, $cust_val) = each($custom) ) {

                $this->cust->fetch_custom_field($cust_key);
                if ( $this->cust->current_field['required'] && ! trim($cust_val) ) {

                    $this->set_error("err_custom{$this->cust->current_field['obj_id']}");
                    $err = 1;
                    $e = 1;
                }

                $contact[ $this->cust->current_field['col_name'] ] = trim($cust_val);
            }
            if ( $e )
                $this->set_error_msg("Please confirm that you have completed the required vendor contact fields indicated below.");
        }

        if ( $err ) {

            if ( $new_vendor )
                return false;
            else
                return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
        }

        if ( ! $new_vendor ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
        }

		if ( $edit_contact ) {

			if ( $action_delete ) {

				if ( ! $this->db->query("UPDATE vendor_contacts t1
						                 SET
    						                 t1.deleted = 1,
    						                 t1.deleted_date = CURDATE()
										 WHERE t1.contact_hash = '{$this->contact_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				$this->content['page_feedback'] = "Your contact has been deleted.";

			} else {

				if ( ! $contact ) {

					$this->content['page_feedback'] = 'No changes have been made.';
					return;
				}

                $contact = $this->db->prepare_query($contact, "vendor_contacts", 'UPDATE');
                if ( ! $this->db->query("UPDATE vendor_contacts
		                                 SET
    		                                 timestamp = UNIX_TIMESTAMP(),
    		                                 last_change = '{$this->current_hash}' ,
    		                                  " . implode(", ", $contact) . "
		                                 WHERE contact_hash = '{$this->contact_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

    			$this->content['page_feedback'] = "Your contact has been updated.";

			}

			unset($this->current_contact);

		} else {

			$contact_hash = rand_hash('vendor_contacts', 'contact_hash');

            $contact = $this->db->prepare_query($contact, "vendor_contacts", 'INSERT');
            if ( ! $this->db->query("INSERT INTO vendor_contacts
		                              (
		                                  timestamp,
		                                  last_change,
		                                  vendor_hash,
		                                  contact_hash,
		                                  " . implode(", ", array_keys($contact) ) . "
		                              )
		                              VALUES
		                              (
		                                  UNIX_TIMESTAMP(),
		                                  '{$this->current_hash}',
		                                  '$vendor_hash',
		                                  '$contact_hash',
		                                  " . implode(", ", array_values($contact) ) . "
		                              )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }
		}

        if ( $new_vendor )
            return true;

        $this->db->end_transaction();

		unset($this->ajax_vars);
		if ( $vendor_hash ) {

			$this->fetch_master_record($vendor_hash);
			$this->ajax_vars['vendor_hash'] = $vendor_hash;
		}

		$this->ajax_vars['p'] = $p;
		$this->ajax_vars['order'] = $order;
		$this->ajax_vars['order_dir'] = $order_dir;

		$this->content['action'] = 'continue';
		$this->content['html']["tcontent3{$this->popup_id}"] = $this->contact_info_form();

		return;

	}

	function doit_vendor_product() {

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

		if ( $product_hash = $_POST['product_hash'] ) {

			if ( ! $this->fetch_product_record($product_hash) )
    			return $this->__trigger_error("A system error was encountered when trying to lookup vendor product for database update. Please re-load vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

    		$edit_product = true;
			$action_delete = $_POST['delete'];
		}

		if ( $edit_product && $action_delete ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

			if ( ! $this->db->query("UPDATE vendor_products
					                  SET deleted = 1, deleted_date = CURDATE()
									  WHERE product_hash = '$product_hash'")
            ) {

                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
            }

			$this->content['page_feedback'] = "Your product has been deleted.";

			$this->db->end_transaction();

			unset($this->ajax_vars, $this->current_product);
			$this->fetch_master_record($this->vendor_hash);
			$this->ajax_vars['vendor_hash'] = $this->vendor_hash;

			$this->ajax_vars['action'] = $product_next_action;
			$this->content['action'] = 'continue';
			if ( $jscript_action )
				$this->content["jscript"][] = $jscript_action;

			$this->content['html']["tcontent5{$this->popup_id}"] = $this->product_info_form();
			return;
		}

		if ( $_POST['product_name'] && $_POST['product_income_account'] && $_POST['product_expense_account'] ) {

			$product['product_name'] = addslashes($_POST['product_name']);
			$product['product_active'] = $_POST['product_active'];
			$product['catalog_code'] = $_POST['catalog_code'];
			$product['separate_po'] = $_POST['separate_po'];

	        if ( $custom = $_POST['custom'] ) {

	            while ( list($cust_key, $cust_val) = each($custom) ) {

	                $this->cust->fetch_custom_field($cust_key);
	                if ( $this->cust->current_field['required'] && ! trim($cust_val) ) {

	                    $this->set_error("err_custom{$this->cust->current_field['obj_id']}");
	                    $this->content['error'] = 1;
	                    $e = 1;
	                }

	                $product[ $this->cust->current_field['col_name'] ] = trim($cust_val);
	            }

	            if ( $e )
	                $this->set_error_msg("Please make sure you complete the indicated fields below.");
	        }

			if ( $this->content['error'] )
                return $this->__trigger_error( ( is_array($this->content['form_return']['feedback']) ? implode("<br />", $this->content['form_return']['feedback']) : $this->content['form_return']['feedback'] ), E_USER_NOTICE, __FILE__, __LINE__, 1);

			$product['product_order_email'] = $_POST['product_order_email'];
			$product['product_order_fax'] = $_POST['product_order_fax'];
			$product['product_order_method'] = $_POST['product_order_method'];

			# Freight Fee
			$product['product_frf_quoted'] = $_POST['product_frf_quoted'];
			$product_frf_amount = preg_replace('/[^.0-9]/', "", $_POST['product_frf_amount']);
			if ( bccomp($product_frf_amount, 0, 2) == 1 ) {

				if ( $_POST['product_frf_amount_type'] && $_POST['product_frf_amount_add'] && $_POST['product_frf_amount_add_type'] && $_POST['product_frf_effective_date'] ) {

					$product_frf_amount_type = $_POST['product_frf_amount_type'];
					$product_frf_amount_add = preg_replace('/[^.0-9]/', "", $_POST['product_frf_amount_add']);
					$product_frf_amount_add_type = $_POST['product_frf_amount_add_type'];
					if ($product_frf_amount_add_type == "P") {

						if ( bccomp($product_frf_amount_add, 100, 2) == 1 ) {

							$this->set_error("err5_7{$this->popup_id}");
							$this->set_error("err5_11{$this->popup_id}");
							return $this->__trigger_error("Please check that you have entered your add on percent correctly. Percentages should be entered as greater than 1. For example, a percent of 69.75% should be entered as 69.75, not .6975.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

						$product_frf_amount_add = bcmul($product_frf_amount_add, .01, 4);
						$product_frf_amount_add_type_of = $_POST['product_frf_amount_add_type_of'];
                        if ( ! $product_frf_amount_add_type_of ) {

	                        $this->set_error("err5_7{$this->popup_id}");
	                        $this->set_error("err5_12{$this->popup_id}");
	                        return $this->__trigger_error("In order to create a small order fee you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
	                    }
					}

					$product_frf_effective_date = $_POST['product_frf_effective_date'];
					$product_frf_expire_date = $_POST['product_frf_expire_date'];
					$product_frf_account = $_POST['product_frf_account'];
					if ( ! checkdate( substr($product_frf_effective_date, 5, 2), substr($product_frf_effective_date, 8, 2), substr($product_frf_effective_date, 0, 4) ) || ( $product_frf_expire_date && strtotime($product_frf_expire_date) <= strtotime($product_frf_effective_date) ) || ( $product_frf_expire_date && ! checkdate( substr($product_frf_expire_date, 5, 2), substr($product_frf_expire_date, 8, 2), substr($product_frf_expire_date, 0, 4) ) ) ) {

						$this->set_error("err5_7{$this->popup_id}");
						if ( ! checkdate( substr($product_frf_effective_date, 5, 2), substr($product_frf_effective_date, 8, 2), substr($product_frf_effective_date, 0, 4) ) ) $this->set_error("err5_14{$this->popup_id}");
						if ( $product_frf_expire_date && ! checkdate( substr($product_frf_expire_date, 5, 2), substr($product_frf_expire_date, 8, 2), substr($product_frf_expire_date, 0, 4) ) ) $this->set_error("err5_15{$this->popup_id}");
						if ( $product_frf_expire_date && strtotime($product_frf_expire_date) <= strtotime($product_frf_effective_date) ) $this->set_error("err5_15{$this->popup_id}");
						return $this->__trigger_error("There is an error in the dates you entered. Please make sure that you entered valid dates and that your expiration date is not before your effective date.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

					$product['product_frf'] = "product_frf_amount={$product_frf_amount}|product_frf_amount_type={$product_frf_amount_type}|product_frf_amount_add={$product_frf_amount_add}|product_frf_amount_add_type={$product_frf_amount_add_type}|product_frf_amount_add_type_of={$product_frf_amount_add_type_of}|product_frf_effective_date={$product_frf_effective_date}|product_frf_expire_date={$product_frf_expire_date}|product_frf_account={$product_frf_account}";

				} else {

					$this->set_error("err5_7{$this->popup_id}");
					if ( ! $_POST['product_frf_amount_type'] ) $this->set_error("err5_12{$this->popup_id}");
					if ( ! $_POST['product_frf_amount_add'] ) $this->set_error("err5_11{$this->popup_id}");
					if ( ! $_POST['product_frf_amount_add_type'] ) $this->set_error("err5_11{$this->popup_id}");
					if ( ! $_POST['product_frf_effective_date'] ) $this->set_error("err5_14{$this->popup_id}");
					if ( ! $_POST['product_frf_account'] ) $this->set_error("err5_13{$this->popup_id}");

                    return $this->__trigger_error("In order to create add ons for freight terms you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
				}

			} else
				$product['product_frf'] = '';

			$product['product_income_account'] = $_POST['product_income_account'];
			$product['product_expense_account'] = $_POST['product_expense_account'];
			if ( $product['product_taxable'] = $_POST['product_taxable'] ) {


				$tax_hash = array();
				$tax_states = $_POST['product_tax'];
				for ( $i = 0; $i < count($tax_states); $i++ ) {

					if ( $tax_states[$i] )
						array_push($tax_hash, $tax_states[$i]);
				}
				if ( ! $tax_hash ) {

					$this->set_error("err5_9{$this->popup_id}");
					return $this->__trigger_error("In order to make a product taxable you must specify which states collect tax for this product.", E_USER_NOTICE, __FILE__, __LINE__, 1);
				}
			}

			$product_next_action = $_POST['product_next_action'];
			$jscript_action = $_POST['prod_jscript_action'];

			if ( ! $product['product_order_method'] && ( ( $product['product_order_email'] && ! $product['product_order_fax'] ) || ( $product['product_order_fax'] && $product['product_order_email'] ) ) )
				$product['product_order_method'] = ( $product['product_order_email'] ? 'product_order_email' : 'product_order_fax' );

			if ( $product['product_order_email'] && ! $this->check->is_email($product['product_order_email']) ) {

				$this->content['error'] = 1;
				$this->set_error_msg($this->check->ERROR);
				$this->set_error("err5_4{$this->popup_id}");
			}

			if ( $product['product_order_fax'] && ! $this->check->is_phone($product['product_order_fax']) ) {

				$this->content['error'] = 1;
				$this->set_error_msg($this->check->ERROR);
				$this->set_error("err5_5{$this->popup_id}");
			}

			if ( ( $product['product_order_email'] || $product['product_order_fax'] ) && ! $product['product_order_method'] ) {

				$this->content['error'] = 1;
				$this->set_error_msg("You have entered both an email address and fax number for purchase orders. Please indicated which you would like to use your default method for placing orders for this product.");
				$this->set_error("err5_6{$this->popup_id}");
			}

			if ( $this->content['error'] )
				return $this->__trigger_error( ( is_array($this->content['form_return']['feedback']) ? implode("<br />", $this->content['form_return']['feedback']) : $this->content['form_return']['feedback'] ), E_USER_NOTICE, __FILE__, __LINE__, 1);

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

			if ( $edit_product ) {

				if ( is_array($tax_hash) ) {

					for ( $i = 0; $i < count($tax_hash); $i++ ) {

						if ( ! in_array($tax_hash[$i], $this->current_product['tax_hash']) ) {

							if ( ! $this->db->query("INSERT INTO product_tax
													 VALUES ( NULL, '$product_hash', '{$tax_hash[$i]}')")
							) {

								return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
							}
						}
					}
					if ( is_array($this->current_product['tax_hash']) ) {

						for ( $i = 0; $i < count($this->current_product['tax_hash']); $i++ ) {

							if ( ! in_array($this->current_product['tax_hash'][$i], $tax_hash) ) {

								if ( ! $this->db->query("DELETE FROM product_tax
        												 WHERE product_hash = '$product_hash' AND tax_hash = '{$this->current_product['tax_hash'][$i]}'")
								) {

									return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
								}
							}
						}
					}

				} elseif ( is_array($this->current_product['tax_hash']) ) {

					if ( ! $this->db->query("DELETE FROM product_tax
        									 WHERE product_hash = '$product_hash'")
					) {

						return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
					}
				}

				if ( is_array($this->current_product['tax_hash']) && ! $product['product_taxable'] ) {

					if ( ! $this->db->query("DELETE FROM product_tax
        									 WHERE product_hash = '$product_hash'")
					) {

						return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
					}
				}

				$product = $this->db->prepare_query($product, "vendor_products", 'UPDATE');
				if ( ! $this->db->query("UPDATE vendor_products
										 SET
    										 timestamp = UNIX_TIMESTAMP(), last_change = '{$this->current_hash}',
    										 " . implode(", ", $product) . "
										 WHERE product_hash = '$product_hash'")
				) {

                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
				}

				$this->content['page_feedback'] = "Vendor product has been updated";

			} else {

				$product_hash = rand_hash('vendor_products', 'product_hash');

				$product = $this->db->prepare_query($product, "vendor_products", 'INSERT');
				if ( ! $this->db->query("INSERT INTO vendor_products
										  (
		    								  timestamp, last_change, vendor_hash, product_hash, " . implode(", ", array_keys($product) ) . "
		            					  )
										  VALUES
										  (
		    								  UNIX_TIMESTAMP(), '{$this->current_hash}', '{$this->vendor_hash}', '$product_hash', " . implode(", ", array_values($product) ) . "
		    							  )")
				) {

					return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
				}

				if ( $tax_hash ) {

					for ( $i = 0; $i < count($tax_hash); $i++ ) {

						if ( ! $this->db->query("INSERT INTO product_tax
        										 VALUES ( NULL, '$product_hash' , '{$tax_hash[$i]}')")
						) {

							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
						}
					}
				}

				$this->content['page_feedback'] = "Your vendor product has been added.";

			}

			$this->db->end_transaction();

			unset($this->ajax_vars,$this->current_product);
			$this->fetch_master_record($this->vendor_hash);
			$this->ajax_vars['vendor_hash'] = $this->vendor_hash;

			$this->ajax_vars['action'] = $product_next_action;
			$this->content['action'] = 'continue';
			if ( $jscript_action )
				$this->content["jscript"][] = $jscript_action;

			$this->ajax_vars['p'] = $p;
			$this->ajax_vars['order'] = $order;
			$this->ajax_vars['order_dir'] = $order_dir;

			$this->content['html']["tcontent5{$this->popup_id}"] = $this->product_info_form();
			return;

		} else {

			if ( ! $_POST['product_name'] ) $this->set_error("err5_1{$this->popup_id}");
			if ( ! $_POST['product_income_account'] ) $this->set_error("err5_8{$this->popup_id}");
			if ( ! $_POST['product_expense_account'] ) $this->set_error("err5_8{$this->popup_id}");

			return $this->__trigger_error("You left some required fields blank. Please check the indicated fields and try again.", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
		}
	}

	function doit_search() {

        $name = trim($_POST['vendor']);
        $vendor_no = preg_replace('/[^0-9]/', "",$_POST['vendor_no']);
        $account_no = trim($_POST['account_no']);
        $state = $_POST['state'];
        $country = $_POST['country'];
        $tax_id = trim($_POST['tax_id']);
        $discount_id = trim($_POST['discount_id']);
        $currency = $_POST['currency'];
        $order_method = $_POST['order_method'];
        $v_1099 = $_POST['v_1099'];
        $dep_req = $_POST['dep_req'];
        $vendor_fees = $_POST['vendor_fees'];

        if ($name){
        	$name = addslashes($name);
        	$sql_p[] = "vendors.vendor_name LIKE '".$name."%'";
        }
            
        if ($vendor_no)
            $sql_p[] = "vendors.vendor_no = '".$vendor_no."'";
        if ($account_no)
            $sql_p[] = "vendors.account_no = '".$account_no."'";
        if ($state)
            $sql_p[] = "vendors.state = '".$state."'";
        if ($country)
            $sql_p[] = "vendors.country = '".$country."'";
        if ($tax_id)
            $sql_p[] = "vendors.tax_id = '".$tax_id."'";
        if ($discount_id)
            $sql_p[] = "discounting.discount_id = '".$discount_id."'";
        if ($currency)
            $sql_p[] = "vendors.currency = '".$currency."'";
        if ($order_method)
            $sql_p[] = "vendors.order_method = '".$order_method."'";
        if ($v_1099)
            $sql_p[] = "vendors.1099 = '".$v_1099."'";
        if ($dep_req)
            $sql_p[] = "vendors.deposit_percent != 0";
        if ($vendor_fees) {
        	for ($i = 0; $i < count($vendor_fees); $i++) {
                switch ($vendor_fees[$i]) {
                	case 'sof':
                    $fee_sql[] = "(vendors.sof != '' OR vendors.sof_quoted = 1)";
                	break;

                    case 'frf':
                    $fee_sql[] = "(vendors.frf != '' OR vendors.frf_quoted = 1)";
                    break;

                    case 'pfrf':
                    $product_freight = 1;
                    $fee_sql[] = "(vendor_products.product_frf != '' OR vendor_products.product_frf_quoted = 1)";
                    break;

                    case 'fsf':
                    $fee_sql[] = "(vendors.fsf != '' OR vendors.fsf_quoted = 1)";
                    break;

                    case 'cbd':
                    $fee_sql[] = "vendors.cbd != ''";
                    break;
                }
        	}
        	if ($fee_sql)
                $sql_p[] = "(".implode(", ",$fee_sql).")";

        }

		if ($sql_p)
			$sql = implode(" AND ",$sql_p);

        $str = "name=$name|vendor_no=$vendor_no|account_no=$account_no|state=$state|country=$country|tax_id=$tax_id|currency=$currency|discount_id=$discount_id|v_1099=$v_1099|dep_req=$dep_req|order_method=$order_method|vendor_fees=".implode("&",$vendor_fees);

		$r = $this->db->query("SELECT vendors.obj_id
							   FROM `vendors`
							   LEFT JOIN discounting ON discounting.vendor_hash = vendors.vendor_hash AND discounting.deleted = 0
							   LEFT JOIN vendor_products ON vendor_products.vendor_hash = vendors.vendor_hash  AND vendor_products.deleted = 0
							   WHERE ".($sql ? $sql." AND " : NULL)."vendors.deleted = 0
		                       GROUP BY vendors.vendor_hash");
		$total = $this->db->num_rows($r);

		$search_hash = md5(global_classes::get_rand_id(32,"global_classes"));
		while (global_classes::key_exists('search','search_hash',$search_hash))
			$search_hash = md5(global_classes::get_rand_id(32,"global_classes"));

		$this->db->query("INSERT INTO `search`
						  (`timestamp` , `search_hash` , `query` , `total` , `search_str`)
						  VALUES (".time()." , '$search_hash' , '".base64_encode($sql)."' , '$total' , '$str')");

		$this->active_search = $search_hash;

		$this->content['action'] = 'close';
		$this->content['jscript_action'] = "agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','active_search=".$this->active_search."');";
		return;
	}

	function doit_vendor_discount() {

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

		$discount_next_action = $_POST['discount_next_action'];

		if ( $discount_hash = $_POST['discount_hash'] ) {

			if ( ! $this->discounting->fetch_discount_record($discount_hash) )
    			return $this->__trigger_error("A system error was encountered when trying to lookup vendor location for database update. Please re-load vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

            $edit_discount = true;
			$action_delete = $_POST['delete'];
		}

        if ( ! $this->db->start_transaction() )
            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

		if ( $action_delete ) {

			if ( ! $this->db->query("UPDATE discounting t1
		                             LEFT JOIN discount_details t2 ON t2.discount_hash = t1.discount_hash
		                             SET
                                         t1.timestamp = UNIX_TIMESTAMP(),
                                         t1.last_change = '{$this->current_hash}',
		                                 t1.deleted = 1,
		                                 t1.deleted_date = CURDATE(),
		                                 t2.deleted = 1,
		                                 t2.deleted_date = CURDATE()
									 WHERE t1.discount_hash = '{$this->discounting->discount_hash}'")
			) {

				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
			}

			$this->content['form_return']['feedback'] = "Discount has been deleted";

		} else {

			if ( $_POST['discount_descr'] && $_POST['discount_id'] && $_POST['discount_effective_date'] && $_POST['discount_expiration_date'] ) {

				if ( $_POST['discount_type'] == 'C' && ! $_POST['discount_customer'] ) {

					$e = 1;
					$this->set_error("err6_2{$this->popup_id}");
					$this->set_error_msg("In order to create a customer discount you must select a customer to apply the discount to.");
				}

				if ( $_POST['discount_type'] == 'C' && ! $_POST['discount_customer_hash'] ) {

					$e = 1;
					$this->set_error("err6_2{$this->popup_id}");
					$this->set_error_msg("We can't seem to find the customer you entered below. If you are trying to create a new customer, please use the plus icon below.");
				}

				if ( ! checkdate(substr($_POST['discount_effective_date'], 5, 2), substr($_POST['discount_effective_date'], 8), substr($_POST['discount_effective_date'], 0, 4) ) || ! checkdate( substr($_POST['discount_expiration_date'], 5, 2), substr($_POST['discount_expiration_date'], 8), substr($_POST['discount_expiration_date'], 0, 4) ) ) {

					$e = 1;
					if ( ! checkdate( substr($_POST['discount_effective_date'], 5, 2), substr($_POST['discount_effective_date'], 8), substr($_POST['discount_effective_date'], 0, 4) ) ) $this->set_error("err6_5{$this->popup_id}");
					if ( ! checkdate( substr($_POST['discount_expiration_date'], 5, 2), substr($_POST['discount_expiration_date'], 8), substr($_POST['discount_expiration_date'], 0, 4) ) ) $this->set_error("err6_6{$this->popup_id}");
					$this->set_error_msg("The dates you entered, indicated below are invalid. Please check that you have entered valid dates and try again.");
				}

				if ( strtotime($_POST['discount_expiration_date']) <= strtotime($_POST['discount_effective_date']) ) {

					$e = 1;
					$this->set_error("err6_5{$this->popup_id}");
					$this->set_error("err6_6{$this->popup_id}");
					$this->set_error_msg("Please check your dates below. If looks like your expiration date falls before your effective date.");
				}

				if ( $e )
					return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__ , __LINE__, 1);

				$discount['discount_type'] = $_POST['discount_type'];
				if ( $discount['discount_type'] == 'C' )
					$discount['customer_hash'] = $_POST['discount_customer_hash'];

				if ( $discount['customer_hash'] && ! $this->discounting->discount_hash ) {

					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM discounting
												WHERE discounting.vendor_hash = '{$this->vendor_hash}' AND discounting.customer_hash = '{$discount['customer_hash']}' AND discounting.deleted = 0");
					if ($this->db->result($result)) {

						$this->set_error("err6_2{$this->popup_id}");
						return $this->__trigger_error("A discount record already exists under this vendor for the customer you entered. If you are trying to change the customer's discount record, please go to the respective discount and edit it.", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
					}
				}

				$discount['discount_descr'] = $_POST['discount_descr'];
				$discount['discount_default'] = $_POST['discount_default'];
				if ( $discount['discount_default'] ) {

					if ( ! $this->db->query("UPDATE discounting
									  SET discount_default = 0
									  WHERE vendor_hash = '{$this->vendor_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
				}

				$discount['discount_gsa'] = $_POST['discount_gsa'];
				$discount['discount_id'] = $_POST['discount_id'];
				$discount['discount_effective_date'] = $_POST['discount_effective_date'];
				$discount['discount_expiration_date'] = $_POST['discount_expiration_date'];

				if ( ! $this->discounting->discount_hash ) {

					$discount_hash = rand_hash('discounting', 'discount_hash');

					$discount = $this->db->prepare_query($discount, "discounting", 'INSERT');
					if ( ! $this->db->query("INSERT INTO discounting
        									 (
            									 timestamp,
            									 last_change,
            									 discount_hash,
            									 vendor_hash,
            									 " . implode(", ", array_keys($discount) ) . "
            								 )
        									 VALUES
        									 (
            									 UNIX_TIMESTAMP(),
            									 '{$this->current_hash}',
            									 '$discount_hash',
            									 '{$this->vendor_hash}',
            									 " . implode(", ", array_values($discount) ) . "
            								 )")
					) {

                        return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
					}

				} else {

					$discount = $this->db->prepare_query($discount, "discounting", 'UPDATE');
					if ( ! $this->db->query("UPDATE discounting
											 SET
    											 timestamp = UNIX_TIMESTAMP(),
    											 last_change = '{$this->current_hash}',
    											 " . implode(", ", $discount) . "
											 WHERE discount_hash = '{$this->discounting->discount_hash}'")
					) {

						return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
					}
				}
			} else {

				if ( ! $_POST['discount_descr'] ) $this->set_error("err6_3{$this->popup_id}");
				if ( ! $_POST['discount_id'] ) $this->set_error("err6_4{$this->popup_id}");
				if ( ! $_POST['discount_effective_date'] ) $this->set_error("err6_5{$this->popup_id}");
				if ( ! $_POST['discount_expiration_date'] ) $this->set_error("err6_6{$this->popup_id}");

				return $this->__trigger_error("You left some required fields blank when creating your discount. Please check the indicated fields and try again.", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
			}
		}

		$this->db->end_transaction();

		unset($this->ajax_vars,$this->discounting);
		$this->fetch_master_record($this->vendor_hash);
		$this->ajax_vars['vendor_hash'] = $this->vendor_hash;

		$this->ajax_vars['action'] = $discount_next_action;

		$this->ajax_vars['p'] = $p;
		$this->ajax_vars['order'] = $order;
		$this->ajax_vars['order_dir'] = $order_dir;

		$this->content['action'] = 'continue';
		$this->content['html']["tcontent6{$this->popup_id}"] = $this->discount_info_form();
	}

	function doit_vendor_discount_item() {

		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

        $disc_type = $_POST['disc_type'];

		if ( $discount_hash = $_POST['discount_hash'] ) {

            if ( ! $this->discounting->fetch_discount_record($discount_hash) )
                return $this->__trigger_error("We can't seem to find the discount record that you're trying to update. It's possible that it may have been removed by another user.",E_USER_ERROR,__FILE__,__LINE__,1);

            $action_delete = $_POST['delete'];
		}

        if ( $item_hash = $_POST['item_hash'] ) {

            if ( ! $this->discounting->fetch_item_record($item_hash) )
                return $this->__trigger_error("System error encountered when attempting to lookup discount item record for edit. Please reload window and try again. <!-- Tried fetching discount item [ $item_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

            $action_delete = $_POST['delete'];
        }

        if ( ! $this->db->start_transaction() )
            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

		if ( $action_delete ) {

			if ( ! $this->db->query("UPDATE discount_details t1
        			                 SET
            			                 t1.deleted = 1,
            			                 t1.deleted_date = CURDATE()
        							 WHERE t1.discount_hash = '{$this->discounting->discount_hash}' && t1.item_hash = '{$this->discounting->item_hash}'")
			) {

				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
			}

			$this->content['form_return']['feedback'] = "Discount item has been deleted";

		} else {

			if ( ( ! $this->discounting->item_hash && count($_POST['discount_product_hash']) > 0 ) || ( ! $this->discounting->item_hash && count($_POST['discount_product_hash_2']) > 0 ) || $this->discounting->item_hash ) {

				$disc_type = $_POST['disc_type'];
				$discount_type = $_POST['discount_type'];
				$discount_descr = addslashes($_POST['discount_descr']);

				if ( $disc_type == 1 )
    				$product_hash = $_POST['discount_product_hash'];
    			elseif ( $disc_type == 2 )
                    $product_hash = $_POST['discount_product_hash_2'];

                if ( ! is_array($product_hash) )
                    $product_hash = array($product_hash);

				$discount_frf_quoted = $_POST['discount_frf_quoted'];
				$discount_frf_amount = preg_replace('/[^.0-9]/', "", $_POST['discount_frf_amount']);
				if ( bccomp($discount_frf_amount, 0, 2) == 1 ) {

					if ( $_POST['discount_frf_amount_type'] && $_POST['discount_frf_amount_add'] && $_POST['discount_frf_amount_add_type'] && $_POST['discount_frf_effective_date'] ) {

						$discount_frf_amount_type = $_POST['discount_frf_amount_type'];
						$discount_frf_amount_add = preg_replace('/[^.0-9]/', "", $_POST['discount_frf_amount_add']);
						$discount_frf_amount_add_type = $_POST['discount_frf_amount_add_type'];
						if ( $discount_frf_amount_add_type == "P" ) {

							if ( bccomp($discount_frf_amount_add, 100, 2) == 100 ) {

								$this->set_error("err6_41{$this->popup_id}");
								return $this->__trigger_error("Please check that you have entered your add on percent correctly. Percentages should be entered as greater than 1. For example, a percent of 69.75% should be entered as 69.75, not .6975.", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
							}

							$discount_frf_amount_add = bcmul($discount_frf_amount_add, .01, 4);
							$discount_frf_amount_add_type_of = $_POST['discount_frf_amount_add_type_of'];

                            if ( ! $discount_frf_amount_add_type_of ) {

	                            $this->set_error("err6_42{$this->popup_id}");
	                            return $this->__trigger_error("In order to create a small order fee you must complete the fields indicated below. Please check your data and try again.", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
	                        }
						}

						$discount_frf_effective_date = $_POST['discount_frf_effective_date'];
						$discount_frf_expire_date = $_POST['discount_frf_expire_date'];
						$discount_frf_account = $_POST['discount_frf_account'];
						if ( ! checkdate( substr($discount_frf_effective_date, 5, 2), substr($discount_frf_effective_date, 8, 2), substr($discount_frf_effective_date, 0, 4) ) || ( $discount_frf_expire_date && strtotime($discount_frf_expire_date) <= strtotime($discount_frf_effective_date) ) || ( $discount_frf_expire_date && ! checkdate( substr($discount_frf_expire_date, 5, 2), substr($discount_frf_expire_date, 8, 2), substr($discount_frf_expire_date, 0, 4) ) ) ) {

							if ( ! checkdate( substr($discount_frf_effective_date, 5, 2), substr($discount_frf_effective_date, 8, 2), substr($discount_frf_effective_date, 0, 4) ) ) $this->set_error("err6_44{$this->popup_id}");
							if ( $discount_frf_expire_date && ! checkdate( substr($discount_frf_expire_date, 5, 2), substr($discount_frf_expire_date, 8, 2), substr($discount_frf_expire_date, 0, 4) ) ) $this->set_error("err6_45{$this->popup_id}");
							if ( $discount_frf_expire_date && strtotime($discount_frf_expire_date) <= strtotime($discount_frf_effective_date) ) $this->set_error("err6_45{$this->popup_id}");

							return $this->__trigger_error("There looks to be something wrong with your dates below. Please make sure that you entered valid dates and that your expiration date is not before your effective date.", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
						}

						$discount_frf = "product_frf_amount=$discount_frf_amount}|product_frf_amount_type=$discount_frf_amount_type}|product_frf_amount_add=$discount_frf_amount_add}|product_frf_amount_add_type=$discount_frf_amount_add_type}|product_frf_amount_add_type_of=$discount_frf_amount_add_type_of}|product_frf_effective_date=$discount_frf_effective_date}|product_frf_expire_date=$discount_frf_expire_date}|product_frf_account=$discount_frf_account}";

					} else {

						if ( ! $_POST['discount_frf_amount_type'] ) $this->set_error("err6_42{$this->popup_id}");
						if ( ! $_POST['discount_frf_amount_add'] ) $this->set_error("err6_41{$this->popup_id}");
						if ( ! $_POST['discount_frf_amount_add_type'] ) $this->set_error("err6_41{$this->popup_id}");
						if ( ! $_POST['discount_frf_effective_date'] ) $this->set_error("err6_44{$this->popup_id}");
						if ( ! $_POST['discount_frf_account'] ) $this->set_error("err6_43{$this->popup_id}");

						return $this->__trigger_error("In order to create add ons for freight terms you must complete the fields indicated below. Please check your data and try again.", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);
					}

				} else
					$discount_frf = '';

                if ( $disc_type == 2 ) {

                    $_POST['discount_item_no'] = trim($_POST['discount_item_no']);
                    if ( ! $this->discounting->item_hash ) {

	                    $item_discount = explode("\n", $_POST['discount_item_no']);
	                    foreach ( $item_discount as $key => $val ) {

	                        if ( ! trim($val) || trim($val) == 'Enter each item number on a new line...' )
	                            unset($item_discount[$key]);
	                    }
                    } else
                        $item_discount = trim($_POST['discount_item_no']);

                    if ( ( ! $this->discounting->item_hash && ! count($item_discount) ) || ( $this->discounting->item_hash && ! $item_discount ) ) {

                        if ( ! $this->discounting->item_hash ) $this->set_error('disc_type_selector');
                    	$this->set_error('item_no_label');

                        return $this->__trigger_error( ( $this->discounting->item_hash ? "The discount you are updating has been created as an item level discount. In order to continue, please make sure that your discount includes an item number.<br /><br />If your revised discount no longer includes this particular item number, you may delete this discount item to prevent it from being used." : "You indicated that you are creating a discount to be applied on specific items within the selected product line. Unfortunately, you didn't include any item numbers to be used within the discount.<br /><br />Please make sure you include at least one item number or change the type of discount that you are creating." ), E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }

                } else
                    $item_discount = array();

                # Check for duplications
                if ( $disc_type == 2 && ( ! $this->discounting->item_hash || ( $this->discounting->item_hash && $item_discount != $this->discounting->current_item['item_no'] ) ) ) {

                    if ( $this->discounting->item_hash ) {

                        $dup_item = array($item_discount);
                        $product_hash = array($this->discounting->current_item['product_hash']);
                    } else
                        $dup_item = $item_discount;

                    for ( $i = 0; $i < count($product_hash); $i++ ) {

                        for ( $j = 0; $j < count($dup_item); $j++ ) {

                            $r = $this->db->query("SELECT COUNT(*) AS Total
                                                   FROM discount_details t1
                                                   WHERE
                                                       t1.discount_hash = '{$this->discounting->discount_hash}'
                                                       AND
                                                       t1.product_hash = '{$product_hash[$i]}'
                                                       AND
                                                       t1.item_no = '{$dup_item[$j]}'
                                                       AND
                                                       t1.deleted = 0");
                            if ( $this->db->result($r, 0, 'Total') )
                                $dup[] = $dup_item[$j];
                        }
                    }

                    if ( $dup ) {

                        $this->set_error('item_no_label');
                        return $this->__trigger_error("We found duplicate item number discounts in the discount you are ".($this->discounting->item_hash ? "editing" : "saving").". Please check the following item numbers for duplications: ".implode(", ",$dup).".",E_USER_NOTICE,__FILE__,__LINE__,1);
                    }
                }

                if ( $disc_type == 3 ) {

                    $_POST['discount_code'] = trim($_POST['discount_code']);
                    if ( ! $this->discounting->item_hash ) {

                        $discount_code = explode("\n", $_POST['discount_code']);
                        foreach ( $discount_code as $key => $val ) {

                            $discount_code[$key] = trim($val);
                            if ( ! trim($val) || trim($val) == 'Enter each discount code on a new line...' )
                                unset($discount_code[$key]);
                        }

                    } else
                        $discount_code = trim($_POST['discount_code']);

                    if ( ( ! $this->discounting->item_hash && ! count($discount_code) ) || ( $this->discounting->item_hash && ! $discount_code ) ) {

                        if ( ! $this->discounting->item_hash )
                            $this->set_error('disc_type_selector');

                        $this->set_error("discount_code_label{$this->popup_id}");
                        return $this->__trigger_error( ( $this->discounting->item_hash ? "The discount you are updating has been created based on item discount code. In order to continue, please make sure that you've enter a valid discount code.<br /><br />If your revised discount no longer includes this particular item number, you may delete this discount item to prevent it from being used." : "You indicated that you are creating a discount to be based on discount code. You failed to include any discount codes to be used within the discount.<br /><br />Please make sure you include at least one discount code or change the type of discount that you are creating." ), E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }
                }

                if ( $disc_type == 3 && ( ! $this->discounting->item_hash || ( $this->discounting->item_hash && $discount_code != $this->discounting->current_item['discount_code'] ) ) ) { # Check for duplications

                    if ( $this->discounting->item_hash ) {

                        $dup_item = array($discount_code);
                    } else
                        $dup_item = $item_discount;

                    for ( $i = 0; $i < count($dup_item); $i++ ) {

                        $r = $this->db->query("SELECT COUNT(*) AS Total
                                               FROM discount_details t1
                                               WHERE t1.discount_hash = '{$this->discounting->discount_hash}' AND t1.discount_code = '{$dup_item[$i]}' AND t1.deleted = 0");
                        if ( $this->db->result($r, 0, 'Total') )
                            $dup[] = $dup_item[$j];
                    }

                    if ( $dup ) {

                        $this->set_error("discount_code_label{$this->popup_id}");
                        return $this->__trigger_error("We found duplicate discount codes in the discount you are " . ( $this->discounting->item_hash ? "editing" : "saving" ) . ". Please check the following discount codes for duplications: " . implode(", ", $dup), E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }
                }

				if ( $discount_type == 'F' ) { # Non-tiered discounting

                    $buy_discount1 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount1']);
                    if ( $buy_discount1 )
                        $buy_discount1 = bcmul($buy_discount1, .01, 4);

                    $buy_discount2 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount2']);
                    if ( $buy_discount2 )
                        $buy_discount2 = bcmul($buy_discount2, .01, 4);

                    $buy_discount3 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount3']);
                    if ( $buy_discount3 )
                        $buy_discount3 = bcmul($buy_discount3, .01, 4);

                    $buy_discount4 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount4']);
                    if ( $buy_discount4 )
                        $buy_discount4 = bcmul($buy_discount4, .01, 4);

                    $buy_discount5 = preg_replace('/[^.0-9]/', "", $_POST['buy_discount5']);
                    if ( $buy_discount5 )
                        $buy_discount5 = bcmul($buy_discount5, .01, 4);

                    $gp_margin = preg_replace('/[^.0-9]/', "", $_POST['gp_margin']);
                    if ( $gp_margin )
                        $gp_margin = bcmul($gp_margin, .01, 4);

                    $sell_discount = preg_replace('/[^.0-9]/', "", $_POST['sell_discount']);
                    if ( $sell_discount )
                        $sell_discount = bcmul($sell_discount, .01, 4);

                    if ( bccomp($sell_discount, 0, 4) )
                        unset($gp_margin);

                    $install_sell = preg_replace('/[^.0-9]/', "", $_POST['install_sell']);
                    if ( $install_sell )
                        $install_sell = bcmul($install_sell, .01, 4);

				                    $sell_disc_amt = bcsub(100, bcmul(100, $sell_discount, 4), 4);
                    if ( is_numeric($buy_discount1) ) {

                        $total_disc_amt = bcsub(100, bcmul(100, $buy_discount1, 4), 4);
                        if ( is_numeric($buy_discount2) ) {

                            $total_disc_amt = bcsub($total_disc_amt, bcmul($total_disc_amt, $buy_discount2, 4), 4);
                            if ( is_numeric($buy_discount3) ) {

                                $total_disc_amt = bcsub($total_disc_amt, bcmul($total_disc_amt, $buy_discount3, 4), 4);
                                if ( is_numeric($buy_discount4) ) {

                                    $total_disc_amt = bcsub($total_disc_amt, bcmul($total_disc_amt, $buy_discount4, 4), 4);
                                    if ( is_numeric($buy_discount5) )
                                        $total_disc_amt = bcsub($total_disc_amt, bcmul($total_disc_amt, $buy_discount5, 4), 4);

                                }
                            }
                        }
                    }

                    if ( $sell_discount && bccomp($total_disc_amt, $sell_disc_amt, 4) >= 0 ) {

                        $this->set_error("err6_10a{$this->popup_id}");
                        return $this->__trigger_error("Your list discount cannot be greater than your buy discount. Please check the indicated fields and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }

					unset($sell_disc_amt, $total_disc_amt);

					if ( ! $this->discounting->item_hash ) { # Create new product discount

                        if ( $disc_type == 3 )
                            $disc_loop =& $discount_code;
                        else
                            $disc_loop =& $item_discount;

                        if ( $disc_loop && ! is_array($disc_loop) )
                            $disc_loop = array($disc_loop);
                        elseif ( ! is_array($disc_loop) || ! count($disc_loop) )
                            $disc_loop = array(NULL);

                        if ( $product_hash && ! is_array($product_hash) )
                            $product_hash = array($product_hash);
                        elseif ( ! is_array($product_hash) || ! $product_hash )
                            $product_hash = array(NULL);

                        for ( $i = 0; $i < count($product_hash); $i++ ) {

                            reset($disc_loop);
                            foreach ( $disc_loop as $loop_disc_key => $loop_disc_val ) {

                                $item_hash = rand_hash('discount_details', 'item_hash');

                                if ( ! $this->db->query("INSERT INTO discount_details
                                                         (
                                                             discount_hash,
                                                             item_hash,
                                                             product_hash, " .
                                                             ( $disc_type == 2 || $disc_type == 3 ?
                                                                 ( $disc_type == 2 ?
                                                                     "item_no, " : "discount_code, "
                                                                 ) : NULL
                                                             ) .
                                                             ( $disc_type == 3 ?
                                                                 "discount_descr," : NULL
                                                             ) . "
                                                             discount_frf,
                                                             discount_frf_quoted,
                                                             discount_type,
                                                             buy_discount1,
                                                             buy_discount2,
                                                             buy_discount3,
                                                             buy_discount4,
                                                             buy_discount5,
                                                             sell_discount,
                                                             gp_margin,
                                                             install_sell
                                                         )
                                                         VALUES
                                                         (
                                                             '$discount_hash',
                                                             '$item_hash',
                                                             '{$product_hash[$i]}', " .
                                                             ( $disc_type == 2 || $disc_type == 3 ?
                                                                 ( $loop_disc_val ?
                                                                     "'$loop_disc_val', " : "NULL, "
                                                                 ) : NULL
                                                             ) .
                                                             ( $disc_type == 3 ?
                                                                 ( $discount_descr ?
                                                                     "'$discount_descr', " : "NULL, "
                                                                 ) : NULL
                                                             ) . "
                                                             '$discount_frf',
                                                             '$discount_frf_quoted',
                                                             '$discount_type',
                                                             '$buy_discount1',
                                                             '$buy_discount2',
                                                             '$buy_discount3',
                                                             '$buy_discount4',
                                                             '$buy_discount5',
                                                             '$sell_discount',
                                                             '$gp_margin',
                                                             '$install_sell'
                                                         )")
                                ) {

                                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                }
                            }
                        }

					} else {

						$sql = array();

                        ( $this->discounting->current_item['item_no'] != $item_discount ? array_push($sql, "t1.item_no = " . ( $item_discount ? "'$item_discount'" : "NULL" ) ) : NULL);
                        ( bccomp($this->discounting->current_item['discount_type'], $discount_type, 4) ? array_push($sql, "t1.discount_type = '$discount_type'") : NULL);
                        ( $this->discounting->current_item['discount_code'] != $discount_code ? array_push($sql, "t1.discount_code = " . ( $discount_code ? "'$discount_code'" : "NULL" ) ) : NULL );
                        ( bccomp($this->discounting->current_item['buy_discount1'], $buy_discount1, 4) ? array_push($sql, "t1.buy_discount1 = '$buy_discount1'") : NULL);
                        ( bccomp($this->discounting->current_item['buy_discount2'], $buy_discount2, 4) ? array_push($sql, "t1.buy_discount2 = '$buy_discount2'") : NULL);
                        ( bccomp($this->discounting->current_item['buy_discount3'], $buy_discount3, 4) ? array_push($sql, "t1.buy_discount3 = '$buy_discount3'") : NULL);
                        ( bccomp($this->discounting->current_item['buy_discount4'], $buy_discount4, 4) ? array_push($sql, "t1.buy_discount4 = '$buy_discount4'") : NULL);
                        ( bccomp($this->discounting->current_item['buy_discount5'], $buy_discount5, 4) ? array_push($sql, "t1.buy_discount5 = '$buy_discount5'") : NULL);
                        ( bccomp($this->discounting->current_item['sell_discount'], $sell_discount, 4) ? array_push($sql, "t1.sell_discount = '$sell_discount'") : NULL);
                        ( bccomp($this->discounting->current_item['gp_margin'], $gp_margin, 4) ? array_push($sql, "t1.gp_margin = '$gp_margin'") : NULL);
                        ( bccomp($this->discounting->current_item['install_sell'], $install_sell, 4) ? array_push($sql, "t1.install_sell = '$install_sell'") : NULL);
                        ( $this->discounting->current_item['discount_frf'] != $discount_frf ? array_push($sql, "t1.discount_frf = '$discount_frf'") : NULL);
                        ( $this->discounting->current_item['discount_frf_quoted'] != $discount_frf_quoted ? array_push($sql, "t1.discount_frf_quoted = '$discount_frf_quoted'" ) : NULL );

                        if ( $sql ) {

                            if ( ! $this->db->query("UPDATE discount_details t1
                                                     SET " . implode(",\n", $sql) . "
                                                     WHERE t1.discount_hash = '{$this->discounting->discount_hash}' && t1.item_hash = '{$this->discounting->item_hash}'")
                            ) {

                                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
                        }
					}

				} elseif ( $discount_type == 'T' ) {

                    # Tier 1
                    $tier1_from = $_POST['tier1_from'];
                    $tier1_to = preg_replace('/[^-.0-9]/', "", $_POST['tier1_to']);
                    $tier1_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier1_buy_discount']);
                    if ( bccomp($tier1_buy_discount, 0, 4) )
                        $tier1_buy_discount = bcmul($tier1_buy_discount, .01, 4);

                    $tier1_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier1_sell_discount']);
                    if ( bccomp($tier1_sell_discount, 0, 4) )
                        $tier1_sell_discount = bcmul($tier1_sell_discount, .01, 4);

                    $tier1_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier1_install_sell']);
                    if ( bccomp($tier1_install_sell, 0, 4) )
                        $tier1_install_sell = bcmul($tier1_install_sell, .01, 4);

                    # Tier 2
                    $tier2_from = preg_replace('/[^-.0-9]/', "", $_POST['tier2_from']);
                    $tier2_to = preg_replace('/[^-.0-9]/', "", $_POST['tier2_to']);
                    $tier2_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier2_buy_discount']);
                    if ( bccomp($tier2_buy_discount, 0, 4) )
                        $tier2_buy_discount = bcmul($tier2_buy_discount, .01, 4);

                    $tier2_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier2_sell_discount']);
                    if ( bccomp($tier2_sell_discount, 0, 4) )
                        $tier2_sell_discount = bcmul($tier2_sell_discount, .01, 4);

                    $tier2_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier2_install_sell']);
                    if ( bccomp($tier2_install_sell, 0, 4) )
                        $tier2_install_sell = bcmul($tier2_install_sell, .01, 4);

                    # Tier 3
                    $tier3_from = preg_replace('/[^-.0-9]/', "", $_POST['tier3_from']);
                    $tier3_to = preg_replace('/[^-.0-9]/', "", $_POST['tier3_to']);
                    $tier3_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier3_buy_discount']);
                    if ( bccomp($tier3_buy_discount, 0, 4) )
                        $tier3_buy_discount = bcmul($tier3_buy_discount, .01, 4);

                    $tier3_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier3_sell_discount']);
                    if ( bccomp($tier3_sell_discount, 0, 4) )
                        $tier3_sell_discount = bcmul($tier3_sell_discount, .01, 4);

                    $tier3_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier3_install_sell']);
                    if ( bccomp($tier3_install_sell, 0, 4) )
                        $tier3_install_sell = bcmul($tier3_install_sell, .01, 4);

                    # Tier 4
                    $tier4_from = preg_replace('/[^-.0-9]/', "", $_POST['tier4_from']);
                    $tier4_to = preg_replace('/[^-.0-9]/', "", $_POST['tier4_to']);
                    $tier4_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier4_buy_discount']);
                    if ( bccomp($tier4_buy_discount, 0, 4) )
                        $tier4_buy_discount = bcmul($tier4_buy_discount, .01, 4);

                    $tier4_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier4_sell_discount']);
                    if ( bccomp($tier4_sell_discount, 0, 4) )
                        $tier4_sell_discount = bcmul($tier4_sell_discount, .01, 4);

                    $tier4_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier4_install_sell']);
                    if ( bccomp($tier4_install_sell, 0, 4) )
                        $tier4_install_sell = bcmul($tier4_install_sell, .01, 4);

                    # Tier 5
                    $tier5_from = preg_replace('/[^.0-9]/', "", $_POST['tier5_from']);
                    $tier5_to = preg_replace('/[^.0-9]/', "", $_POST['tier5_to']);
                    $tier5_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier5_buy_discount']);
                    if ( bccomp($tier5_buy_discount, 0, 4) )
                        $tier5_buy_discount = bcmul($tier5_buy_discount, .01, 4);

                    $tier5_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier5_sell_discount']);
                    if ( bccomp($tier5_sell_discount, 0, 4) )
                        $tier5_sell_discount = bcmul($tier5_sell_discount, .01, 4);

                    $tier5_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier5_install_sell']);
                    if ( bccomp($tier5_install_sell, 0, 4) )
                        $tier5_install_sell = bcmul($tier5_install_sell, .01, 4);

                    # Tier 6
                    $tier6_from = preg_replace('/[^.0-9]/', "", $_POST['tier6_from']);
                    $tier6_to = preg_replace('/[^.0-9]/', "", $_POST['tier6_to']);
                    $tier6_buy_discount = preg_replace('/[^.0-9]/', "", $_POST['tier6_buy_discount']);
                    if ( bccomp($tier6_buy_discount, 0, 4) )
                        $tier6_buy_discount = bcmul($tier6_buy_discount, .01, 4);

                    $tier6_sell_discount = preg_replace('/[^.0-9]/', "", $_POST['tier6_sell_discount']);
                    if ( bccomp($tier6_sell_discount, 0, 4) )
                        $tier6_sell_discount = bcmul($tier6_sell_discount, .01, 4);

                    $tier6_install_sell = preg_replace('/[^.0-9]/', "", $_POST['tier6_install_sell']);
                    if ( bccomp($tier6_install_sell, 0, 4) )
                        $tier6_install_sell = bcmul($tier6_install_sell, .01, 4);

                    if ( ! $tier1_to || ! $tier1_buy_discount || ! $tier2_buy_discount || ( $tier3_buy_discount && ! $tier2_to ) || ( $tier3_to && ! $tier3_buy_discount ) || ( $tier4_buy_discount && ! $tier3_to ) || ( $tier6_buy_discount && ! $tier5_to ) || ( $tier5_buy_discount && ! $tier4_to ) ) {

                        if ( ! $tier1_to ) $this->set_error("err6_13{$this->popup_id}");
                        if ( ! $tier1_buy_discount ) $this->set_error("err6_14{$this->popup_id}");
                        if ( ! $tier2_buy_discount ) $this->set_error("err6_18{$this->popup_id}");
                        if ( $tier3_buy_discount && ! $tier2_to ) $this->set_error("err6_17{$this->popup_id}");
                        if ( $tier4_buy_discount && ! $tier3_to ) $this->set_error("err6_21{$this->popup_id}");
                        if ( $tier3_to && ! $tier3_buy_discount ) $this->set_error("err6_22{$this->popup_id}");
                        if ( $tier3_to && ! $tier4_buy_discount ) $this->set_error("err6_26{$this->popup_id}");
                        if ( $tier4_to && ! $tier4_buy_discount ) $this->set_error("err6_52{$this->popup_id}");
                        if ( $tier4_to && ! $tier5_buy_discount ) $this->set_error("err6_52{$this->popup_id}");
                        if ( $tier5_to && ! $tier5_buy_discount ) $this->set_error("err6_52{$this->popup_id}");
                        if ( $tier5_to && ! $tier6_buy_discount ) $this->set_error("err6_56{$this->popup_id}");
                        if ( $tier5_buy_discount && ! $tier4_to ) $this->set_error("err6_25{$this->popup_id}");
                        if ( $tier6_buy_discount && ! $tier5_to ) $this->set_error("err6_51{$this->popup_id}");

                        return $this->__trigger_error("In order to create a tiered discount, please complete the indicated fields.", E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }

					if ( ( $tier6_buy_discount && $tier6_to && ( bccomp($tier6_to, $tier5_to, 2) != 1 ) ) || ( $tier5_buy_discount && $tier5_to && ( bccomp($tier5_to, $tier4_to, 2) != 1 ) ) || ( $tier4_buy_discount && $tier4_to && ( bccomp($tier4_to, $tier3_to, 2) != 1 ) ) || ( $tier3_buy_discount && $tier3_to && bccomp($tier3_to, $tier2_to, 2) != 1 ) ) {

						if ( $tier4_buy_discount && $tier4_to && bccomp($tier4_to, $tier3_to, 2) != 0 ) {

							$this->set_error("err6_21{$this->popup_id}");
							$this->set_error("err6_25{$this->popup_id}");
						}

						if ( $tier3_buy_discount && $tier3_to && bccomp($tier3_to, $tier2_to, 2) != 1 ) {

							$this->set_error("err6_21{$this->popup_id}");
							$this->set_error("err6_17{$this->popup_id}");
						}

						if ( $tier5_buy_discount && $tier5_to && bccomp($tier5_to, $tier4_to, 2) != 1 ) {

							$this->set_error("err6_51{$this->popup_id}");
							$this->set_error("err6_25{$this->popup_id}");
						}

						if ( $tier6_buy_discount && $tier6_to && bccomp($tier6_to, $tier5_to, 2) != 1 ) {

							$this->set_error("err6_51{$this->popup_id}");
							$this->set_error("err6_55{$this->popup_id}");
						}

						return $this->__trigger_error("Your tiered dollar amounts are invalid. Dollar amounts must increase through out your tiers.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

                    if ( ! $this->check->is_percent($tier1_buy_discount) || ! $this->check->is_percent($tier2_buy_discount) || ( $tier3_buy_discount && ! $this->check->is_percent($tier3_buy_discount) ) || ( $tier4_buy_discount && ! $this->check->is_percent($tier4_buy_discount) ) || ( $tier1_sell_discount && ! $this->check->is_percent($tier1_sell_discount) ) || ( $tier1_install_sell && ! $this->check->is_percent($tier1_install_sell) ) || ( $tier2_sell_discount && ! $this->check->is_percent($tier2_sell_discount) ) || ( $tier2_install_sell && ! $this->check->is_percent($tier2_install_sell) ) || ( $tier3_sell_discount && ! $this->check->is_percent($tier3_sell_discount) ) || ( $tier4_sell_discount && ! $this->check->is_percent($tier4_sell_discount) ) || ( $tier5_buy_discount && ! $this->check->is_percent($tier5_buy_discount) ) || ( $tier6_buy_discount && ! $this->check->is_percent($tier6_buy_discount) ) || ( $tier5_sell_discount && ! $this->check->is_percent($tier5_sell_discount) ) || ( $tier6_sell_discount && ! $this->check->is_percent($tier6_sell_discount) ) ) {

                        if ( ! $this->check->is_percent($tier1_buy_discount) ) $this->set_error("err6_14{$this->popup_id}");
                        if ( ! $this->check->is_percent($tier2_buy_discount) ) $this->set_error("err6_18{$this->popup_id}");
                        if ( $tier3_buy_discount && ! $this->check->is_percent($tier3_buy_discount) ) $this->set_error("err6_22{$this->popup_id}");
                        if ( $tier4_buy_discount && ! $this->check->is_percent($tier4_buy_discount) ) $this->set_error("err6_26{$this->popup_id}");

                        if ( $tier1_sell_discount && ! $this->check->is_percent($tier1_sell_discount) ) $this->set_error("err6_15{$this->popup_id}");
                        if ( $tier1_install_sell && ! $this->check->is_percent($tier1_install_sell) ) $this->set_error("err6_28{$this->popup_id}");

                        if ( $tier2_sell_discount && ! $this->check->is_percent($tier2_sell_discount) ) $this->set_error("err6_19{$this->popup_id}");
                        if ( $tier2_install_sell && ! $this->check->is_percent($tier2_install_sell) ) $this->set_error("err6_29{$this->popup_id}");

                        if ( $tier3_sell_discount && ! $this->check->is_percent($tier3_sell_discount) ) $this->set_error("err6_23{$this->popup_id}");
                        if ( $tier3_install_sell && ! $this->check->is_percent($tier3_install_sell) ) $this->set_error("err6_30{$this->popup_id}");

                        if ( $tier4_sell_discount && ! $this->check->is_percent($tier4_sell_discount) ) $this->set_error("err6_27{$this->popup_id}");
                        if ( $tier4_install_sell && ! $this->check->is_percent($tier4_install_sell) ) $this->set_error("err6_31{$this->popup_id}");

                        return $this->__trigger_error("The percentage you entered for the indicated field is invalid. Percentages should be entered as numbers, not decimals. For example, to enter 65.35%, enter it as 65.35, not .6535.", E_USER_NOTICE, __FILE__, __LINE__, 1);
                    }

                    $tier1_from = .01;
                    $tier2_from = bcadd($tier1_to, .01, 2);
                    $tier3_from = bcadd($tier2_to, .01, 2);
                    $tier4_from = bcadd($tier3_to, .01, 2);
                    $tier5_from = bcadd($tier4_to, .01, 2);
                    $tier6_from = bcadd($tier5_to, .01, 2);

                    if ( ! $tier4_sell_discount )
                        unset($tier4_sell_discount);
                    if ( ! $tier3_sell_discount )
                        unset($tier3_sell_discount);
                    if ( ! $tier2_sell_discount )
                        unset($tier2_sell_discount);
                    if ( ! $tier1_sell_discount )
                        unset($tier1_sell_discount);

					if ( ! $this->discounting->item_hash ) { # Create new product discount

                        if ( $disc_type == 3 )
                            $disc_loop =& $discount_code;
                        else
                            $disc_loop =& $item_discount;

                        if ( $disc_loop && ! is_array($disc_loop) )
                            $disc_loop = array($disc_loop);
                        elseif ( ! is_array($disc_loop) || ! count($disc_loop) )
                            $disc_loop = array(NULL);

                        if ( $product_hash && ! is_array($product_hash) )
                            $product_hash = array($product_hash);
                        elseif ( ! $product_hash || ! is_array($product_hash) )
                            $product_hash = array(NULL);

                        for ( $i = 0; $i < count($product_hash); $i++ ) {

                            reset($disc_loop);
                            foreach ( $disc_loop as $loop_disc_key => $loop_disc_val ) {

                                $item_hash = rand_hash('discount_details', 'item_hash');

                                if ( ! $this->db->query("INSERT INTO discount_details
                                                         (
                                                             discount_hash,
                                                             item_hash,
                                                             product_hash, " .
                                                             ( $disc_type == 2 || $disc_type == 3 ?
                                                                 ( $disc_type == 2 ?
                                                                     "item_no, " : "discount_code, "
                                                                 ) : NULL
                                                             ) .
                                                             ( $disc_type == 3 ?
                                                                 "discount_descr, " : NULL
                                                             ) . "
                                                             discount_frf,
                                                             discount_frf_quoted,
                                                             discount_type,
                                                             tier1_from,
                                                             tier1_to,
                                                             tier1_buy_discount,
                                                             tier1_sell_discount,
                                                             tier1_install_sell,
                                                             tier2_from,
                                                             tier2_to,
                                                             tier2_buy_discount,
                                                             tier2_sell_discount,
                                                             tier2_install_sell,
                                                             tier3_from,
                                                             tier3_to,
                                                             tier3_buy_discount,
                                                             tier3_sell_discount,
                                                             tier3_install_sell,
                                                             tier4_from,
                                                             tier4_to,
                                                             tier4_buy_discount,
                                                             tier4_sell_discount,
                                                             tier4_install_sell,
                                                             tier5_from,
                                                             tier5_to,
                                                             tier5_buy_discount,
                                                             tier5_sell_discount,
                                                             tier6_from,
                                                             tier6_to,
                                                             tier6_buy_discount,
                                                             tier6_sell_discount
                                                         )
                                                         VALUES
                                                         (
                                                             '$discount_hash',
                                                             '$item_hash', " .
                                                             ( $product_hash[$i] ?
                                                                 "'{$product_hash[$i]}'" : "NULL"
                                                             ) . ", " .
                                                             ( $disc_type == 2 || $disc_type == 3 ?
                                                                 ( $loop_disc_val ?
                                                                     "'$loop_disc_val', " : "NULL, "
                                                                 ) : NULL
                                                             ) . "
                                                             '$discount_frf',
                                                             '$discount_frf_quoted',
                                                             '$discount_type',
                                                             '$tier1_from',
                                                             '$tier1_to',
                                                             '$tier1_buy_discount',
                                                             '$tier1_sell_discount',
                                                             '$tier1_install_sell',
                                                             '$tier2_from',
                                                             '$tier2_to',
                                                             '$tier2_buy_discount',
                                                             '$tier2_sell_discount',
                                                             '$tier2_install_sell',
                                                             '$tier3_from',
                                                             '$tier3_to',
                                                             '$tier3_buy_discount',
                                                             '$tier3_sell_discount',
                                                             '$tier3_install_sell',
                                                             '$tier4_from',
                                                             '$tier4_to',
                                                             '$tier4_buy_discount',
                                                             '$tier4_sell_discount',
                                                             '$tier4_install_sell',
                                                             '$tier5_from',
                                                             '$tier5_to',
                                                             '$tier5_buy_discount',
                                                             '$tier5_sell_discount',
                                                             '$tier6_from',
                                                             '$tier6_to',
                                                             '$tier6_buy_discount',
                                                             '$tier6_sell_discount'
                                                         )")
                                ) {

                                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                                }
                            }
                        }

					} else { # Update an existing product discount
						
						$sql = array();

                        array_push($sql, "t1.item_no = ''" );
                        array_push($sql, "t1.discount_type = '$discount_type'" );
                        array_push($sql, "t1.tier1_from = '$tier1_from'" );
                        array_push($sql, "t1.tier1_to = '$tier1_to'" );
                        array_push($sql, "t1.tier1_buy_discount = '$tier1_buy_discount'" );
                        array_push($sql, "t1.tier1_sell_discount = '$tier1_sell_discount'" );
                        array_push($sql, "t1.tier1_install_sell = '$tier1_install_sell'" );
                        array_push($sql, "t1.tier2_from = '$tier2_from'" );
                        array_push($sql, "t1.tier2_to = '$tier2_to'" );
                        array_push($sql, "t1.tier2_buy_discount = '$tier2_buy_discount'" );
                        array_push($sql, "t1.tier2_sell_discount = '$tier2_sell_discount'" );
                        array_push($sql, "t1.tier2_install_sell = '$tier2_install_sell'" );
                        array_push($sql, "t1.tier3_from = '$tier3_from'" );
                        array_push($sql, "t1.tier3_to = '$tier3_to'" );
                        array_push($sql, "t1.tier3_buy_discount = '$tier3_buy_discount'" );
                        array_push($sql, "t1.tier3_sell_discount = '$tier3_sell_discount'" );
                        array_push($sql, "t1.tier3_install_sell = '$tier3_install_sell'" );
                        array_push($sql, "t1.tier4_from = '$tier4_from'" );
                        array_push($sql, "t1.tier4_to = '$tier4_to'" );
                        array_push($sql, "t1.tier4_buy_discount = '$tier4_buy_discount'" );
                        array_push($sql, "t1.tier4_sell_discount = '$tier4_sell_discount'" );
                        array_push($sql, "t1.tier4_install_sell = '$tier4_install_sell'" );
                        array_push($sql, "t1.tier5_from = '$tier5_from'" );
                        array_push($sql, "t1.tier5_to = '$tier5_to'" );
                        array_push($sql, "t1.tier5_buy_discount = '$tier5_buy_discount'" );
                        array_push($sql, "t1.tier5_sell_discount = '$tier5_sell_discount'" );
                        array_push($sql, "t1.tier6_from = '$tier6_from'" );
                        array_push($sql, "t1.tier6_to = '$tier6_to'" );
                        array_push($sql, "t1.tier6_buy_discount = '$tier6_buy_discount'" );
                        array_push($sql, "t1.tier6_sell_discount = '$tier6_sell_discount'" );
                        array_push($sql, "t1.discount_frf = '$discount_frf'" );
                        array_push($sql, "t1.discount_frf_quoted = '$discount_frf_quoted'" );
                        
                        if ( $sql ) {

                            if (  ! $this->db->query("UPDATE discount_details t1
                                                     SET " . implode(", ", $sql) . "
                                                     WHERE t1.discount_hash = '{$this->discounting->discount_hash}' && t1.item_hash = '{$this->discounting->item_hash}'")
                            ) {

                                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
                        }
					}
				}

			} else {

				$this->set_error("err6_7{$this->popup_id}");
				return $this->__trigger_error("You left some required fields blank when attempting to create item/product discount. Please check the indicated fields and try again.", E_USER_NOTICE, __FILE__ , __LINE__, 1);
			}
		}

		$this->db->end_transaction();

		unset($this->ajax_vars, $this->discounting);
		$this->fetch_master_record($this->vendor_hash);
		$this->ajax_vars['vendor_hash'] = $this->vendor_hash;
		$this->ajax_vars['discount_hash'] = $discount_hash;

		$this->ajax_vars['p'] = $p;
		$this->ajax_vars['order'] = $order;
		$this->ajax_vars['order_dir'] = $order_dir;

		$this->ajax_vars['action'] = 'showtable';
		$this->content['action'] = 'continue';
		$this->content['html']["tcontent6{$this->popup_id}"] = $this->discount_info_form();
	}

	function doit() {

		$this->check = new Validator;
		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$vendor_hash = $_POST['vendor_hash'];
		$action = $_POST['action'];

		if ( $vendor_hash ) {

			if ( ! $this->fetch_master_record($vendor_hash) )
    			return $this->__trigger_error("Error encountered when trying to lookup vendor record. Unable to continue with vendor update, please close window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
		}

		$jscript_action = $_POST['jscript_action'];
		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		$active_search = $_POST['active_search'];
		if ($active_search)
			$this->active_search = $active_search;

		if ($action)
			return $this->$action();

		if ($btn == 'AddLocationLine')
			$this->doit_vendor_location($vendor_hash);

		if ($btn == 'AddContactLine')
			$this->doit_vendor_contact($vendor_hash);

		if ($btn == 'AddProductLine')
			return $this->doit_vendor_product();

		if ($btn == 'AddDiscountLine')
			return $this->doit_vendor_discount();

		if ($btn == 'AddDiscountItem')
			return $this->doit_vendor_discount_item();

		if ( $btn == "Add Vendor" || $btn == "Update Vendor" ) {

			if ( trim($_POST['vendor_name']) && trim($_POST['city']) && trim($_POST['state']) && trim($_POST['country']) ) {

				# General tab
				$vendor['vendor_name'] = addslashes( trim($_POST['vendor_name']) );
				$vendor['vendor_no'] = preg_replace('/[^0-9]/', "", $_POST['vendor_no']);
				$vendor['active'] = $_POST['active'];
				$vendor['street'] = addslashes( trim($_POST['street']) );
				$vendor['city'] = addslashes( trim($_POST['city']) );
				$vendor['state'] = $_POST['state'];
				$vendor['zip'] = trim($_POST['zip']);
				$vendor['country'] = $_POST['country'];
				$vendor['phone'] = $_POST['phone'];
				$vendor['fax'] = $_POST['fax'];
				$vendor['account_no'] = preg_replace('/[^-.0-9a-zA-Z]/', "", $_POST['account_no']);
				$vendor['order_email'] = $_POST['order_email'];
				$vendor['order_fax'] = $_POST['order_fax'];
				$vendor['order_method'] = $_POST['order_method'];

				if ( $_POST['order_method'] == 'order_eorder' ) {

					$e_interface_input = $_POST['e_interface_input'];
					if ( $this->vendor_hash && $this->current_vendor['eorder_file'] && file_exists(SITE_ROOT . "core/images/e_profile/{$this->current_vendor['eorder_file']}") ) {

						include_once(SITE_ROOT . "core/images/e_profile/{$this->current_vendor['eorder_file']}");

						$efile_class = explode(".", $this->current_vendor['eorder_file']);
						$efile_class_name = "eorder_{$efile_class[1]}";
						$efile_obj = new $efile_class_name($this->vendor_hash);

						while ( list($key, $val) = each($e_interface_input) ) {

							if ( $efile_obj->electronic_interface_req_fields[ $key ] && ! trim($val) ) {

								$err = 1;
								$this->set_error("err_eorder_{$key}");
							}
						}
					}

					if ( $err )
						return $this->__trigger_error("In order to use electronic order as your default order method, you must complete the indicated fields below. Please obtain this information from your system administrator.", E_USER_ERROR, __FILE__, __LINE__, 1);

					if ( $e_interface_input )
						$vendor['eorder_data'] = addslashes( serialize($e_interface_input) );
				}

				if ( $vendor['order_email'] && ! $this->check->is_email($vendor['order_email']) ) {

					$err = 1;
					$this->set_error_msg($this->check->ERROR);
					$this->set_error("err1_10{$this->popup_id}");
				}
				if ( $vendor['order_fax'] && ! $this->check->is_phone($vendor['order_fax']) ) {

					$err = 1;
					$this->set_error_msg($this->check->ERROR);
					$this->set_error("err1_11{$this->popup_id}");
				}
				if ( ( $vendor['order_method'] == 'order_fax' && ! $vendor['order_fax']) || ( $vendor['order_method'] == 'order_email' && ! $vendor['order_email'] ) ) {

					$err = 1;
					$this->set_error_msg("You have indicated that you prefer to submit your POs to this vendor via " . ($vendor['order_method'] == 'order_fax' ? "fax" : "email" ) . ", however you did not supply a valid ".($vendor['order_method'] == 'order_fax' ? "fax number" : "email address").". Please complete the indicated fields and try again.");
					if ( $vendor['order_method'] == 'order_fax' && ! $vendor['order_fax'] ) $this->set_error("err1_11{$this->popup_id}");
					if ( $vendor['order_method'] == 'order_email' && ! $vendor['order_email'] ) $this->set_error("err1_10{$this->popup_id}");
				}

                if ( $err )
                    return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);

				# Payment Tab
				$vendor['deposit_percent'] = preg_replace('/[^.0-9]/', "", $_POST['deposit_percent']);
				$vendor['payment_discount'] = preg_replace('/[^.0-9]/', "", $_POST['payment_discount']);
				$vendor['discount_days'] = preg_replace('/[^0-9]/', "", $_POST['discount_days']);
				$vendor['payment_terms'] = preg_replace('/[^0-9]/', "", $_POST['payment_terms']);
				$vendor['group_invoices'] = $_POST['group_invoices'];
				$vendor['tax_id'] = $_POST['tax_id'];
                $vendor['1099'] = $_POST['1099'];
                $vendor['currency'] = $_POST['currency'];
				$vendor['po_comment'] = addslashes($_POST['po_comment']);
				$vendor['manual_credit'] = $_POST['manual_credit'];
				$vendor['manual_credit_amount'] = $_POST['manual_credit_amount'];
				$account_name = $_POST['account_name'];
				$account_hash = $_POST['account_hash'];
				$vendor['manual_credit_account'] = $account_name[0];
				$vendor['manual_credit_account_hash'] = $account_hash[0];
				
				//$this->__trigger_error($account_hash[0], E_USER_ERROR, __FILE__, __LINE__, 1);
				
				
				# Small order fee
				$vendor['sof_quoted'] = $_POST['sof_quoted'];
				$sof_amount = preg_replace('/[^.0-9]/', "", $_POST['sof_amount']);
				if ( bccomp($sof_amount, 0, 2) == 1 ) {

					if ( $_POST['sof_amount_type'] && $_POST['sof_amount_add'] && $_POST['sof_amount_add_type'] && $_POST['sof_effective_date'] ) {

						$sof_amount_type = $_POST['sof_amount_type'];
						$sof_amount_add = preg_replace('/[^.0-9]/', "", $_POST['sof_amount_add']);
						$sof_amount_add_type = $_POST['sof_amount_add_type'];
						if ( $sof_amount_add_type == "P" ) {

							if ( bccomp($sof_amount_add, 100, 2) == 1 ) {

								$this->set_error("err2_4{$this->popup_id}");
								$this->set_error("err2_15{$this->popup_id}");
								return $this->__trigger_error("Please check that you have entered your add on percent correctly. Percentages should be entered as greater than 1. For example, a percent of 69.75% should be entered as 69.75, not .6975.", E_USER_ERROR, __FILE__, __LINE__, 1);
							}

							$sof_amount_add = bcmul($sof_amount_add, .01, 4);
							$sof_amount_add_type_of = $_POST['sof_amount_add_type_of'];
							if ( ! $sof_amount_add_type_of ) {

								$this->set_error("err2_4{$this->popup_id}");
								$this->set_error("err2_16{$this->popup_id}");
								return$this->__trigger_error("In order to create a small order fee you must complete the fields indicated below. Please check your data and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
							}
						}

						$sof_effective_date = $_POST['sof_effective_date'];
						$sof_expire_date = $_POST['sof_expire_date'];
						$sof_account = $_POST['sof_account'];
						if ( ! checkdate( substr($sof_effective_date, 5, 2), substr($sof_effective_date, 8, 2), substr($sof_effective_date, 0, 4) ) || ($sof_expire_date && strtotime($sof_expire_date) <= strtotime($sof_effective_date)) || ( $sof_expire_date && ! checkdate( substr($sof_expire_date, 5, 2), substr($sof_expire_date, 8, 2), substr($sof_expire_date, 0, 4) ) ) ) {

							$this->set_error("err2_4{$this->popup_id}");
							if ( ! checkdate( substr($sof_effective_date, 5, 2), substr($sof_effective_date, 8, 2), substr($sof_effective_date, 0, 4) ) ) $this->set_error("err2_17{$this->popup_id}");
							if ( $sof_expire_date && ! checkdate( substr($sof_expire_date, 5, 2), substr($sof_expire_date, 8, 2), substr($sof_expire_date, 0, 4) ) ) $this->set_error("err2_18{$this->popup_id}");
							if ( $sof_expire_date && strtotime($sof_expire_date) <= strtotime($sof_effective_date) ) $this->set_error("err2_18{$this->popup_id}");
							return $this->__trigger_error("There looks to be something wrong with your dates below. Please make sure that you entered valid dates and that your expiration date is not before your effective date.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

						$vendor['sof'] = "sof_amount={$sof_amount}|sof_amount_type={$sof_amount_type}|sof_amount_add={$sof_amount_add}|sof_amount_add_type={$sof_amount_add_type}|sof_amount_add_type_of={$sof_amount_add_type_of}|sof_effective_date={$sof_effective_date}|sof_expire_date={$sof_expire_date}|sof_account={$sof_account}";

					} else {

						$this->set_error("err2_4{$this->popup_id}");
						if ( ! $_POST['sof_amount_type'] ) $this->set_error("err2_13{$this->popup_id}");
						if ( ! $_POST['sof_amount_add'] ) $this->set_error("err2_15{$this->popup_id}");
						if ( ! $_POST['sof_amount_add_type'] ) $this->set_error("err2_15{$this->popup_id}");
						if ( ! $_POST['sof_effective_date'] ) $this->set_error("err2_17{$this->popup_id}");
						if ( ! $_POST['sof_account'] ) $this->set_error("err2_19{$this->popup_id}");
						return $this->__trigger_error("In order to create a small order fee you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

				} else
					$vendor['sof'] = '';

				# Freight Fee
				$vendor['frf_quoted'] = $_POST['frf_quoted'];
				$frf_amount = preg_replace('/[^.0-9]/', "", $_POST['frf_amount']);
				if ( bccomp($frf_amount, 0, 2) == 1 ) {

					if ( $_POST['frf_amount_type'] && $_POST['frf_amount_add'] && $_POST['frf_amount_add_type'] && $_POST['frf_effective_date'] ) {

						$frf_amount_type = $_POST['frf_amount_type'];
						$frf_amount_add = preg_replace('/[^.0-9]/', "", $_POST['frf_amount_add']);
						$frf_amount_add_type = $_POST['frf_amount_add_type'];
						if ( $frf_amount_add_type == "P" ) {

							if ( bccomp($frf_amount_add, 100, 2) == 1 ) {

								$this->set_error("err2_5{$this->popup_id}");
								$this->set_error("err2_21{$this->popup_id}");
								return $this->__trigger_error("Please check that you have entered your add on percent correctly. Percentages should be entered as greater than 1. For example, a percent of 69.75% should be entered as 69.75, not .6975.", E_USER_NOTICE, __FILE__, __LINE__, 1);
							}

							$frf_amount_add = bcmul($frf_amount_add, .01, 4);
							$frf_amount_add_type_of = $_POST['frf_amount_add_type_of'];
							if ( ! $frf_amount_add_type_of ) {

                                $this->set_error("err2_5{$this->popup_id}");
                                $this->set_error("err2_22{$this->popup_id}");
                                return $this->__trigger_error("In order to create a small order fee you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
							}
						}

						$frf_effective_date = $_POST['frf_effective_date'];
						$frf_expire_date = $_POST['frf_expire_date'];
						$frf_account = $_POST['frf_account'];
						if ( ! checkdate( substr($frf_effective_date, 5, 2), substr($frf_effective_date, 8, 2), substr($frf_effective_date, 0, 4) ) || ( $frf_expire_date && strtotime($frf_expire_date) <= strtotime($frf_effective_date) ) || ( $frf_expire_date && ! checkdate( substr($frf_expire_date, 5, 2), substr($frf_expire_date, 8, 2), substr($frf_expire_date, 0, 4) ) ) ) {

							$this->set_error("err2_5{$this->popup_id}");
							if ( ! checkdate( substr($frf_effective_date, 5, 2), substr($frf_effective_date, 8, 2), substr($frf_effective_date, 0, 4) ) ) $this->set_error("err2_23{$this->popup_id}");
							if ( $frf_expire_date && ! checkdate( substr($frf_expire_date, 5, 2), substr($frf_expire_date, 8, 2), substr($frf_expire_date, 0, 4) ) ) $this->set_error("err2_24{$this->popup_id}");
							if ( $frf_expire_date && strtotime($frf_expire_date) <= strtotime($frf_effective_date) ) $this->set_error("err2_24{$this->popup_id}");
							return $this->__trigger_error("There looks to be something wrong with your dates below. Please make sure that you entered valid dates and that your expiration date is not before your effective date.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

						$vendor['frf'] = "frf_amount={$frf_amount}|frf_amount_type={$frf_amount_type}|frf_amount_add={$frf_amount_add}|frf_amount_add_type={$frf_amount_add_type}|frf_amount_add_type_of={$frf_amount_add_type_of}|frf_effective_date={$frf_effective_date}|frf_expire_date={$frf_expire_date}|frf_account={$frf_account}";

					} else {

						$this->set_error("err2_5{$this->popup_id}");
						if ( ! $_POST['frf_amount_type'] ) $this->set_error("err2_20{$this->popup_id}");
						if ( ! $_POST['frf_amount_add'] ) $this->set_error("err2_21{$this->popup_id}");
						if ( ! $_POST['frf_amount_add_type'] ) $this->set_error("err2_21{$this->popup_id}");
						if ( ! $_POST['frf_effective_date'] ) $this->set_error("err2_24{$this->popup_id}");
						if ( ! $_POST['frf_account'] ) $this->set_error("err2_25{$this->popup_id}");

						return $this->__trigger_error("In order to create add ons for freight terms you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

				} else
					$vendor['frf'] = '';

				# Fuel Surcharge Fee
				$vendor['fsf_quoted'] = $_POST['fsf_quoted'];
				$fsf_amount_add = preg_replace('/[^.0-9]/', "", $_POST['fsf_amount_add']);
				if ( bccomp($fsf_amount_add, 0, 2) == 1 ) {

					if ( $_POST['fsf_amount_add_type'] && $_POST['fsf_effective_date'] ) {

						$fsf_amount_add_type = $_POST['fsf_amount_add_type'];
						if ( $fsf_amount_add_type == "P" ) {

							if ( bccomp($fsf_amount_add, 100, 2) == 1 ) {

								$this->set_error("err2_6{$this->popup_id}");
								return $this->__trigger_error("Please check that you have entered your add on percent correctly. Percentages should be entered as greater than 1. For example, a percent of 69.75% should be entered as 69.75, not .6975.", E_USER_NOTICE, __FILE__, __LINE__, 1);
							}

							$fsf_amount_add = bcmul($fsf_amount_add, .01, 4);
							$fsf_amount_add_type_of = $_POST['fsf_amount_add_type_of'];
							if ( ! $fsf_amount_add_type_of ) {

								$this->set_error("err2_6{$this->popup_id}");
								return $this->__trigger_error("In order to create a small order fee you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
							}
						}

						$fsf_effective_date = $_POST['fsf_effective_date'];
						$fsf_expire_date = $_POST['fsf_expire_date'];
						$fsf_account = $_POST['fsf_account'];
						if ( ! checkdate( substr($fsf_effective_date, 5, 2), substr($fsf_effective_date, 8, 2), substr($fsf_effective_date, 0, 4) ) || ( $fsf_expire_date && strtotime($fsf_expire_date) <= strtotime($fsf_effective_date) ) || ( $fsf_expire_date && ! checkdate( substr($fsf_expire_date, 5, 2), substr($fsf_expire_date, 8, 2), substr($fsf_expire_date, 0, 4) ) ) ) {

							$this->set_error("err2_6{$this->popup_id}");
							if ( ! checkdate( substr($fsf_effective_date, 5, 2), substr($fsf_effective_date, 8, 2), substr($fsf_effective_date, 0, 4) ) ) $this->set_error("err2_26{$this->popup_id}");
							if ( $fsf_expire_date && ! checkdate( substr($fsf_expire_date, 5, 2), substr($fsf_expire_date, 8, 2), substr($fsf_expire_date, 0, 4) ) ) $this->set_error("err2_27{$this->popup_id}");
							if ( $fsf_expire_date && strtotime($fsf_expire_date) <= strtotime($fsf_effective_date) ) $this->set_error("err2_27{$this->popup_id}");
							return $this->__trigger_error("There looks to be something wrong with your dates below. Please make sure that you entered valid dates and that your expiration date is not before your effective date.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

						$vendor['fsf'] = "fsf_amount_add={$fsf_amount_add}|fsf_amount_add_type={$fsf_amount_add_type}|fsf_amount_add_type_of={$fsf_amount_add_type_of}|fsf_effective_date={$fsf_effective_date}|fsf_expire_date={$fsf_expire_date}|fsf_account={$fsf_account}";

					} else {

						$this->set_error("err2_6{$this->popup_id}");
						if ( ! $_POST['fsf_effective_date'] ) $this->set_error("err2_26{$this->popup_id}");
						if ( ! $_POST['fsf_account'] ) $this->set_error("err2_28{$this->popup_id}");
						return $this->__trigger_error("In order to create add ons for freight terms you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

				} else
					$vendor['fsf'] = '';

				# CBD Fee
				$cbd_amount_add = preg_replace('/[^.0-9]/', "", $_POST['cbd_amount_add']);
				if ( bccomp($cbd_amount_add, 0, 2) == 1 ) {

					if ( $_POST['cbd_effective_date'] ) {

						$cbd_effective_date = $_POST['cbd_effective_date'];
						$cbd_expire_date = $_POST['cbd_expire_date'];
						$cbd_account = $_POST['cbd_account'];
						if ( ! checkdate( substr($cbd_effective_date, 5, 2), substr($cbd_effective_date, 8, 2), substr($cbd_effective_date, 0, 4) ) || ( $cbd_expire_date && strtotime($cbd_expire_date) <= strtotime($cbd_effective_date) ) || ( $cbd_expire_date && ! checkdate( substr($cbd_expire_date, 5, 2), substr($cbd_expire_date, 8, 2), substr($cbd_expire_date, 0, 4) ) ) ) {

							$this->set_error("err2_7{$this->popup_id}");
							if ( ! checkdate( substr($cbd_effective_date, 5, 2), substr($cbd_effective_date, 8, 2), substr($cbd_effective_date, 0, 4) ) ) $this->set_error("err2_29{$this->popup_id}");
							if ( $cbd_expire_date && ! checkdate( substr($cbd_expire_date, 5, 2), substr($cbd_expire_date, 8, 2), substr($cbd_expire_date, 0, 4) ) ) $this->set_error("err2_30{$this->popup_id}");
							if ( $cbd_expire_date && strtotime($cbd_expire_date) <= strtotime($cbd_effective_date) ) $this->set_error("err2_30{$this->popup_id}");
							return $this->__trigger_error("There looks to be something wrong with your dates below. Please make sure that you entered valid dates and that your expiration date is not before your effective date.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

						$vendor['cbd'] = "cbd_amount_add={$cbd_amount_add}|cbd_effective_date={$cbd_effective_date}|cbd_expire_date={$cbd_expire_date}|cbd_account={$cbd_account}";

					} else {

						$this->set_error("err2_7{$this->popup_id}");
						if ( ! $_POST['cbd_effective_date'] ) $this->set_error("err2_29{$this->popup_id}");
						if ( ! $_POST['cbd_account'] ) $this->set_error("err2_31{$this->popup_id}");
						return $this->__trigger_error("In order to create add ons for freight terms you must complete the fields indicated below. Please check your data and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
					}

				} else
					$vendor['cbd'] = '';


					if ( $_POST['remit_street'] && $_POST['remit_city'] && $_POST['remit_state'] && $_POST['remit_zip'] && $_POST['remit_country'] ) {

						$error = false;
						
						if ( ! $_POST['remit_street'] ){
							$this->set_error("err2_9{$this->popup_id}");
							$error = true;
						}
						if ( ! $_POST['remit_city'] ){
							$this->set_error("err2_10{$this->popup_id}");
							$error = true;
						}
						if ( ! $_POST['remit_state'] ){
							$this->set_error("err2_11{$this->popup_id}");
							$error = true;
						}
						if ( ! $_POST['remit_zip'] ){
							$this->set_error("err2_12{$this->popup_id}");
							$error = true;
						}
						if ( ! $_POST['remit_country'] ){
							$this->set_error("err2_13{$this->popup_id}");
							$error = true;
						}
						
						if($error){
							return $this->__trigger_error("In order to create a billing remittance address for this vendor please complete the indicated fields.", E_USER_NOTICE, __FILE__, __LINE__, 1);
						}

					}
					
					$vendor['remit_street'] = addslashes( trim($_POST['remit_street']) );
					$vendor['remit_city'] = addslashes( trim($_POST['remit_city']) );
					$vendor['remit_state'] = $_POST['remit_state'];
					$vendor['remit_zip'] = $_POST['remit_zip'];
					$vendor['remit_country'] = $_POST['remit_country'];
					$vendor['remit_name'] = $_POST['remit_name'];

				

				if ( $vendor['deposit_percent'] && ( bccomp($vendor['deposit_percent'], 100, 2) == 1 || bccomp($vendor['deposit_percent'], 1, 2) == -1 ) ) {

					$err = 1;
					$this->set_error_msg("Please check that you have entered a valid deposit percentage.");
					$this->set_error("err2_1{$this->popup_id}");
				}

				$vendor['deposit_percent'] = bcmul($vendor['deposit_percent'], .01, 4);

				if ( $vendor['payment_discount'] && ( bccomp($vendor['payment_discount'], 100, 2) == 1 || bccomp($vendor['payment_discount'], 1, 2) == -1 ) ) {

					$err = 1;
					$this->set_error_msg("Please check that you have entered a valid percentage for the payment discount");
					$this->set_error("err2_2{$this->popup_id}");
				}
				if ( $vendor['payment_discount'] )
    				$vendor['payment_discount'] = bcmul($vendor['payment_discount'], .01, 4);

				if ( ( $vendor['payment_discount'] && ! $vendor['discount_days'] ) || ( ! $vendor['payment_discount'] && $vendor['discount_days'] ) ) {

					$err = 1;
					$this->set_error_msg("In order to apply vendor payment discounting, please include both the payment discount and the days prior to due date.");
					$this->set_error("err2_2{$this->popup_id}");
				}

		        if ( $custom = $_POST['custom'] ) {

		            while ( list($cust_key, $cust_val) = each($custom) ) {

		                $this->cust->fetch_custom_field($cust_key);
		                if ( $this->cust->current_field['required'] && ! trim($cust_val) ) {

		                    $this->set_error("err_custom{$this->cust->current_field['obj_id']}");
		                    $err = 1;
                            $e = 1;
		                }

		                $vendor[ $this->cust->current_field['col_name'] ] = trim($cust_val);
		            }

		            if ( $e )
                        $this->set_error_msg("Please make sure you complete the indicated fields below.");
		        }

				if ( $err )
					return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);

                if ( ! $this->db->start_transaction() )
                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

				if ( $btn == "Add Vendor" ) {

					$vendor_hash = rand_hash('customer_vendor_hash_index', 'hash');
					if ( ! $this->db->query("INSERT INTO customer_vendor_hash_index
        									 VALUES
        									 (
            								 	 '$vendor_hash'
            								 )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

                    if ( ! $vendor ) {

                    	$this->content['feedback'] = "No changes have been made.";
                    	return;
                    }

					$vendor = $this->db->prepare_query($vendor, "vendors", 'INSERT');
					if ( ! $this->db->query("INSERT INTO vendors
											 (
												 timestamp,
												 last_change,
												 vendor_hash,
			                                     " . implode(", ", array_keys($vendor) ) . "
		                                     )
											 VALUES
											 (
    											 UNIX_TIMESTAMP(),
    											 '{$this->current_hash}',
    											 '$vendor_hash',
        		        					     " . implode(", ", array_values($vendor) ) . "
		                                     )")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

                    if ( trim($_POST['contact_name']) ) {

						if ( ! $this->doit_vendor_contact($vendor_hash, 1) ) {

	                        return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
						}
                    }

                    if ( trim($_POST['location_name']) ) {

	                    if ( ! $this->doit_vendor_location($vendor_hash, 1) ) {

	                        return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
	                    }
                    }

                    $this->db->end_transaction();

					$this->content['page_feedback'] = "Vendor has been added";
					$this->content['action'] = 'close';
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=$order','order_dir=$order_dir','active_search={$this->active_search}')" );

				} elseif ( $btn == "Update Vendor" ) {

					$vendor = $this->db->prepare_query($vendor, "vendors", 'UPDATE');
					if ( ! $this->db->query("UPDATE vendors
											 SET
		    									 timestamp = UNIX_TIMESTAMP(),
		    									 last_change = '{$this->current_hash}',
		    									  " . implode(", ", $vendor) . "
											 WHERE vendor_hash = '{$this->vendor_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

                    $this->db->end_transaction();

					$this->content['page_feedback'] = "Your vendor has been updated.";

					$this->content['action'] = 'close';
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=$order','order_dir=$order_dir','active_search={$this->active_search}')" );
				}

				return;

			} else {

				if ( ! $_POST['vendor_name']) $this->set_error("err1_1{$this->popup_id}");
				if ( ! $_POST['city']) $this->set_error("err1_4{$this->popup_id}");
				if ( ! $_POST['state']) $this->set_error("err1_5{$this->popup_id}");
				if ( ! $_POST['country']) $this->set_error("err1_6a{$this->popup_id}");

				return $this->__trigger_error("You left some required fields blank! Please check the indicated fields below and try again.<br />", E_USER_NOTICE, __FILE__, __LINE__, 1);

			}

		} elseif ($btn == "Delete Vendor") {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

			if ( ! $this->db->query("UPDATE vendors t1
		                              LEFT JOIN discounting t2 ON t2.vendor_hash = t1.vendor_hash
		                              LEFT JOIN discount_details t3 ON t3.discount_hash = t2.discount_hash
		                              LEFT JOIN vendor_products t4 ON t4.vendor_hash = t1.vendor_hash
		                              LEFT JOIN vendor_contacts t5 ON t5.vendor_hash = t1.vendor_hash
		                              LEFT JOIN locations t6 ON t6.entry_hash = t1.vendor_hash AND t6.entry_type = 'v'
		                              SET t1.deleted = 1, t1.deleted_date = CURDATE(),
		                                  t2.deleted = 1, t2.deleted_date = CURDATE(),
		                                  t3.deleted = 1, t3.deleted_date = CURDATE(),
		                                  t4.deleted = 1, t4.deleted_date = CURDATE(),
		                                  t5.deleted = 1, t5.deleted_date = CURDATE(),
		                                  t6.deleted = 1, t6.deleted_date = CURDATE()
									  WHERE t1.vendor_hash = '$vendor_hash'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            $this->db->end_transaction();

			$this->content['page_feedback'] = "Vendor has been removed";

			$this->content['action'] = 'close';
			$this->content['jscript_action'] = "agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=$order','order_dir=$order_dir','active_search={$this->active_search}')";

			return;
		}
	}

	function doit_e_profile() {

		$file_name = rawurldecode($_POST['file_name']);
		$ext = strtolower( substr( strrev( basename($file_name) ), 0, 3) );
		$file_type = $_POST['file_type'];
		$file_size = $_POST['file_size'];
		$file_error = $_POST['file_error'];
		$parent_popup_id = $_POST['parent_popup_id'];

		if ( $_POST['rm'] ) {

			if ( ! $this->db->query("UPDATE vendors t1
        						     SET
        						         t1.timestamp = UNIX_TIMESTAMP(),
        						         t1.last_change = '{$this->current_hash}',
            						     t1.eorder_file = ''
        						     WHERE t1.vendor_hash = '{$this->vendor_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			unlink( TEMPLATE_DIR . $this->current_vendor['eorder_file']);

			$this->content['page_feedback'] = "E-Order Template Removed";
			$this->content['jscript'] = "agent.call('vendors', 'sf_loadcontent', 'show_popup_window', 'edit_vendor', 'vendor_hash={$this->vendor_hash}', 'popup_id=$parent_popup_id')";
			$this->content['action'] = 'close';

			return;
		}

		if ( $ext == "php" && ! $file_error && $file_size > 0 && file_exists($file_name) ) {

			$contents = file($file_name);

			if ( preg_match('/EFILE Template/', $contents[1]) ) {

				if ( copy($file_name, TEMPLATE_DIR . basename($file_name) ) ) {

					if ( ! $this->db->query("UPDATE vendors t1
        									 SET
	        									 t1.timestamp = UNIX_TIMESTAMP(),
	        									 t1.last_change = '{$this->current_hash}',
	        									 t1.eorder_file = '" . basename($file_name) . "'
        									 WHERE t1.vendor_hash = '{$this->vendor_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					$this->content['page_feedback'] = "E-Order Template saved";
					$this->content['jscript'] = "agent.call('vendors', 'sf_loadcontent', 'show_popup_window', 'edit_vendor', 'vendor_hash={$this->vendor_hash}', 'popup_id=$parent_popup_id')";
					$this->content['action'] = 'close';

					return;

				} else {

					$this->set_error_msg("Error encountered during file copy. Unable to copy E-Order Template into destination directory " . basename(TEMPLATE_DIR) );
					$e = 1;
				}

			} else {

				$this->set_error_msg("The file you uploaded is not a valid DealerChoice E-Order File. Please make sure you are using the proper file issued by DealerChoice.");
				$e = 1;
			}

		} else {

            $this->set_error_msg(
            ( $ext != "php" ?
                "The file you uploaded is not a PHP file. Please make sure you are uploading a valid PHP E-Order file that was issued by DealerChoice."
                :
                ( $file_error ?
                    "There was a problem with the file you imported. Please check to make sure the file is valid and not damaged and try again." : "There was a problem uploading your file. The server timed out and wouldn't accept the file. Try uploading again."
                )
            ) );
			$e = 1;
		}

		if ( $e ) {

			unlink($file_name);

			$this->content['jscript'] = "remove_element('progress_holder', 'progress_div'); remove_element('iframe'); create_element('iframe', 'iframe', 'src=upload.php?class=vendors&action=doit_e_profile&current_hash={$this->current_hash}&popup_id={$this->popup_id}','frameBorder=0','height=50px');toggle_display('iframe','block');";
            return $this->__trigger_error(NULL, E_USER_NOTICE, __FILE__, __LINE__, 1);
		}

		return;
	}

	function edit_e_profile() {

		if ( ! $this->fetch_master_record($this->ajax_vars['vendor_hash']) )
    		return $this->__trigger_error("System error encountered when attempting to lookup vendor record. Please reload window and try again. <!-- Tried fetching vendor [ {$this->ajax_vars['vendor_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Add/Edit E-Order Template";

		$tbl =
		$this->form->form_tag().
		$this->form->hidden( array(
    		"vendor_hash"     => $this->ajax_vars['vendor_hash'],
			"popup_id"        => $this->popup_id,
			"parent_popup_id" => $this->ajax_vars['parent_popup_id'],
			"proposal_hash"   => 1,
			"p"               => $this->ajax_vars['p'],
			"order"           => $this->ajax_vars['order'],
			"order_dir"       => $this->ajax_vars['order_dir'],
			"active_search"	  => $this->ajax_vars['active_search']
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<h3 style=\"color:#00477f;margin-bottom:5px;margin-left:10px;display:block;\">" . stripslashes($this->current_vendor['vendor_name']) . "</h3>
			<div style=\"margin-left:15px;margin-bottom:15px;margin-top:15px;\">
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:90%;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;padding:10px;\">
							Vendors that are able to accept electronic orders outside of the standard purchase order can be set up by attaching a DealerChoice E-Order Template file. " .
                			( defined('TEMPLATE_DIR') && $this->current_vendor['eorder_file'] && file_exists(TEMPLATE_DIR . $this->current_vendor['eorder_file']) ?
								"<br /><br />
								To change the E-Order template to be used for this vendor, select the file below.
								<div style=\"padding:15px 5px;background-color:#cccccc;border:1px solid black;margin-top:10px;\">
									Current E-Order Template: \"{$this->current_vendor['eorder_file']}\"
									&nbsp;&nbsp;
									[<small><a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to remove this E-Order template?')){submit_form(\$('vendor_hash').form,'vendors','exec_post','refresh_form','action=doit_e_profile','rm=1','popup_id={$this->popup_id}','parent_popup_id=".$this->ajax_vars['parent_popup_id']."');}\" class=\"link_standard\">delete</a></small>]
								</div>" : NULL
							) . "
							<div style=\"padding:10px 0;font-weight:bold;\" id=\"err2{$this->popup_id}\">E-Order Template File: </div>
							<div id=\"iframe\"><iframe src=\"upload.php?class=vendors&action=doit_e_profile&current_hash=".$this->current_hash."&popup_id={$this->popup_id}\" frameborder=\"0\" style=\"height:50px;\"></iframe></div>
							<div id=\"progress_holder\"></div>".
							$this->form->hidden(array("file_name" 		=>		'',
													  "file_type" 		=>		'',
													  "file_size" 		=>		'',
													  "file_error" 		=>		''
													  )
												)."
						</td>
					</tr>
				</table>
			</div>
		</div>".$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}

	function doit_vendor_note() {
		$vendor_hash = $_POST['vendor_hash'];
		$note_hash = $_POST['note_hash'];
		$note = strip_tags($_POST['note']);

		if ($valid = $this->fetch_note_record($note_hash)) {
			if ($_POST['delete']) {
				$this->db->query("DELETE FROM `customer_vendor_notes`
								  WHERE `note_hash` = '$note_hash'");
				$this->content['page_feedback'] = "Note has been removed.";
			} elseif ($note) {
				$this->db->query("UPDATE `customer_vendor_notes`
								  SET `timestamp` = ".time()." , `user_hash` = '".$this->current_hash."' , `comment` = '$note'
								  WHERE `note_hash` = '$note_hash'");
				$this->content['page_feedback'] = "Note has been saved.";
			} else
				$this->content['page_feedback'] = "No changes have been made.";
		} else {
			$note_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			while (global_classes::key_exists('customer_vendor_notes','note_hash',$note_hash))
				$note_hash = md5(global_classes::get_rand_id(32,"global_classes"));

			$this->db->query("INSERT INTO `customer_vendor_notes`
							  (`timestamp` , `note_hash` , `type` , `entry_hash` , `user_hash` , `comment`)
							  VALUES (".time()." , '$note_hash' , 'V' , '$vendor_hash' , '".$this->current_hash."' , '$note')");
			$this->content['page_feedback'] = "Note has been saved.";
		}
		$this->content['action'] = 'close';

		$notes = $this->fetch_vendor_notes($vendor_hash,'V');
		$this->content['html']['note_holder'] = '';
		for ($i = 0; $i < count($notes); $i++)
			$this->content['html']['note_holder'] .= "
			<div style=\"padding-left:5px;padding-bottom:5px;\">
				<strong>".$notes[$i]['full_name']." (".date(DATE_FORMAT." ".TIMESTAMP_FORMAT,$notes[$i]['timestamp']).")</strong> ".($notes[$i]['user_hash'] == $this->current_hash && $this->p->ck(get_class($this),'E','general') ? "
					[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','show_popup_window','edit_note','vendor_hash=".$vendor_hash."','popup_id=note_win','note_hash=".$notes[$i]['note_hash']."')\">edit</a></small>]" : NULL)." : ".$notes[$i]['comment']."
			</div>";

		return;
	}

	function edit_note() {
		$vendor_hash = $this->ajax_vars['vendor_hash'];
		if ($note_hash = $this->ajax_vars['note_hash'])
			$valid = $this->fetch_note_record($note_hash);

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Vendor Notes" : "Create A New Vendor Note");

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("note_hash" => $this->note_hash,
								  "vendor_hash" => $vendor_hash,
								  "popup_id" => $this->popup_id))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:15px 20px;\">
						<table >
							<tr>
								<td style=\"vertical-align:top;padding-left:20px;\">
									<div style=\"padding-bottom:5px;font-weight:bold;\" id=\"err2{$this->popup_id}\">Note: </div>
									".$this->form->text_area("name=note","rows=7","cols=75","value=".$this->current_note['comment'])."
								</td>
							</tr>
							<tr>
								<td style=\"vertical-align:bottom;\">
									<div style=\"text-align:left;padding:15px;\">
										".$this->form->button("value=Save Note","onClick=submit_form(this.form,'vendors','exec_post','refresh_form','action=doit_vendor_note');")."
										&nbsp;&nbsp;".($valid ?
										$this->form->button("value=Delete Note","onClick=submit_form(this.form,'vendors','exec_post','refresh_form','action=doit_vendor_note','delete=1');") : NULL)."
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

	function fetch_vendor_notes($vendor_hash) {
		if (!$vendor_hash)
			return;

		$result = $this->db->query("SELECT customer_vendor_notes.* , users.full_name
									FROM `customer_vendor_notes`
									LEFT JOIN users ON users.id_hash = customer_vendor_notes.user_hash
									WHERE `entry_hash` = '$vendor_hash' AND `type` = 'V'
									ORDER BY `timestamp` DESC");
		while ($row = $this->db->fetch_assoc($result))
			$notes[] = $row;


		return $notes;
	}

	function fetch_note_record($note_hash) {
		$result = $this->db->query("SELECT customer_vendor_notes.* , users.full_name
									FROM `customer_vendor_notes`
									LEFT JOIN users ON users.id_hash = customer_vendor_notes.user_hash
									WHERE `note_hash` = '$note_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_note = $row;
			$this->note_hash = $note_hash;
			$this->vendor_hash = $row['vendor_hash'];

			return true;
		}

		return false;
	}


	function edit_vendor() {
		global $stateNames, $states, $country_codes, $country_names;

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Create A New Vendor";

		if ( $this->ajax_vars['vendor_hash'] ) {

			if ( ! $this->fetch_master_record($this->ajax_vars['vendor_hash'], 1) ) {

				return $this->__trigger_error("A system error was encountered when trying to lookup vendor contact for database update. Please re-load vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
			}

			$this->content['popup_controls']['popup_title'] = "Edit Vendor : " . stripslashes($this->current_vendor['vendor_name']);
		}

		if ($this->ajax_vars['onclose_action'])
			$this->content['popup_controls']['onclose'] = $this->ajax_vars['onclose_action'];

		if ( $this->vendor_hash ) {

			$vendor_notes = "
			<div style=\"padding-top:5px;height:45px;width:90%;overflow:auto;border:1px outset #cccccc;background-color:#efefef;\" id=\"note_holder\">";

			$notes = $this->fetch_vendor_notes($this->vendor_hash, 'V');
			for ( $i = 0; $i < count($notes); $i++ ) {

				$vendor_notes .= "
				<div style=\"padding-left:5px;padding-bottom:5px;\">
					<strong>" . stripslashes($notes[$i]['full_name']) . " (" . date(DATE_FORMAT . " " . TIMESTAMP_FORMAT, $notes[$i]['timestamp']) . ")</strong> " .
    				( $notes[$i]['user_hash'] == $this->current_hash && $this->p->ck(get_class($this), 'E', 'general') ? "
						[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','show_popup_window','edit_note','vendor_hash={$this->vendor_hash}','popup_id=note_win','note_hash={$notes[$i]['note_hash']}')\">edit</a></small>]" : NULL
					) . " : " . stripslashes($notes[$i]['comment']) . "
				</div>";
			}

			$vendor_notes .= "
			</div>";

			if ( defined('TEMPLATE_DIR') && $this->current_vendor['eorder_file'] && file_exists(TEMPLATE_DIR . $this->current_vendor['eorder_file']) ) {

				include_once(TEMPLATE_DIR . $this->current_vendor['eorder_file']);

				$efile_class = explode(".", $this->current_vendor['eorder_file']);
				$efile_class_name = "eorder_{$efile_class[1]}";

				$efile_obj = new $efile_class_name($this->vendor_hash);

				if ( method_exists($efile_obj, "electronic_interface") )
					$e_interface_input = $efile_obj->electronic_interface();
			}

		} else {

			$vendor_notes .= "
			<div style=\"padding-top:5px;\">" .
    			$this->form->text_area(
                    "name=vendor_notes",
                    "value=",
                    ( ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL ),
                    "cols=75",
                    "rows=4"
                ) . "
			</div>";
		}

        if ( defined('MULTI_CURRENCY') ) {

            $sys = new system_config();
            $sys->fetch_currencies();

            for ( $i = 0; $i < count($sys->currency); $i++ ) {

                $currency_out[] = $sys->currency[$i]['name'];
                $currency_in[] = $sys->currency[$i]['code'];
            }
        }

        unset($this->cust->class_fields);
        $this->cust->fetch_class_fields('general');

        if ( $this->cust->class_fields['general'] ) {

            $fields =& $this->cust->class_fields['general'];
            for ( $i = 0; $i < count($fields); $i++ ) {

                $general_custom_fields .= "
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err_custom{$fields[$i]['obj_id']}\">{$fields[$i]['field_name']}: " . ( $fields[$i]['required'] ? "*" : NULL ) . "</td>
                    <td style=\"background-color:#ffffff;\">" .
                        $this->cust->build_input(
                            $fields[$i],
                            $this->current_vendor[ $fields[$i]['col_name'] ]
                        ) . "
                    </td>
                </tr>";
            }
        }

		$tbl .=
		$this->form->form_tag().
		$this->form->hidden( array(
            "vendor_hash"       =>  $this->ajax_vars['vendor_hash'],
            "popup_id"          =>  $this->popup_id,
            "jscript_action"    =>  $this->ajax_vars['jscript_action'],
            "p"                 =>  $this->ajax_vars['p'],
            "order"             =>  $this->ajax_vars['order'],
            "order_dir"         =>  $this->ajax_vars['order_dir'],
            "active_search"     =>  $this->ajax_vars['active_search']
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<div style=\"text-align:right;padding:15px 15px 5px 15px;\" >" .
    			( ! $this->vendor_hash || ( $this->lock && $this->vendor_hash && $this->p->ck(get_class($this), 'E') ) ?
    				$this->form->button(
        				"value=" . ( $this->vendor_hash ? "Update Vendor" : "Add Vendor" ),
        				"onClick=submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=" . ( $this->vendor_hash ? "Update Vendor" : "Add Vendor" ) . "');"
    				) : NULL) . "
				&nbsp;" .
                ( $this->lock && $this->vendor_hash && $this->p->ck(get_class($this), 'D') ?
    				$this->form->button(
        				"value=Delete Vendor",
        				"onClick=if ( confirm('Are you sure you want to delete this vendor? This action CANNOT be undone!') ) { submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=Delete Vendor');}",
        				( $this->current_vendor['rm_lock'] ? "disabled title=This vendor is linked against records elsewhere in the system and cannot be delete." : NULL )
        			) : NULL) .
        			$this->p->lock_stat(get_class($this), $this->popup_id) . "
			</div>
			<div style=\"padding:15px 0 0 15px;font-weight:bold;\"></div>
			<ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">" .
			( ! $this->vendor_hash || ( $this->vendor_hash && $this->p->ck(get_class($this), 'V', 'general') ) ? "
				<li class=\"selected\"><a href=\"javascript:void(0);\" id=\"cntrl_tcontent1{$this->popup_id}\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\">General</a></li>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'payments') ? "
				<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent2{$this->popup_id}\" rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\">Payments</a></li>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'contacts') ? "
				<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent3{$this->popup_id}\" rel=\"tcontent3{$this->popup_id}\" onClick=\"expandcontent(this);\">Contacts</a></li>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'locations') ? "
				<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent4{$this->popup_id}\" rel=\"tcontent4{$this->popup_id}\" onClick=\"expandcontent(this);\">Locations</a></li>" : NULL
			) .
			( $this->current_vendor['vendor_hash'] ?
    			( $this->p->ck(get_class($this), 'V', 'products') ? "
    				<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent5{$this->popup_id}\" rel=\"tcontent5{$this->popup_id}\" onClick=\"expandcontent(this);\">Products</a></li>" : NULL
    			) .
    			( $this->p->ck(get_class($this), 'V', 'discounting') ? "
    				<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent6{$this->popup_id}\" rel=\"tcontent6{$this->popup_id}\" onClick=\"expandcontent(this);\">Discounting</a></li>" : NULL
    			) .
    			( $this->p->ck(get_class($this), 'V', 'stats') ? "
    				<li ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent7{$this->popup_id}\" rel=\"tcontent7{$this->popup_id}\" onClick=\"expandcontent(this);\">Vendor Stats</a></li>" : NULL
    			) : NULL
    		) . "
			</ul>" .
    		( $this->p->ck(get_class($this), 'V', 'general') ? "
			<div id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;width:35%;padding-top:15px;vertical-align:top;\" id=\"err1_1{$this->popup_id}\">
							Vendor Name: *
						</td>
						<td style=\"background-color:#ffffff;padding-top:15px;\">" .
							$this->form->text_box(
                                "name=vendor_name",
                                "value=" . stripslashes($this->current_vendor['vendor_name']),
                                "size=35",
                                "maxlength=255",
                                ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL ),
                                ( ! $this->vendor_hash ? "onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('vendors','vendor_name','vendor_name',1);}" : NULL ),
                                ( ! $this->vendor_hash ? "onKeyUp=if(ka==false && this.value){key_call('vendors','vendor_name','vendor_name',1);}" : NULL ),
                                ( ! $this->vendor_hash ? "onBlur=key_clear();" : NULL )
                            ) . "
                            <div style=\"padding-top:5px;\">" .
                                $this->form->checkbox(
                                    "name=active",
                                    "value=1",
                                    ( $this->current_vendor['active'] || ! $this->current_vendor ? "checked" : NULL),
                                    ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "disabled" : NULL)
                                ) .
                                "&nbsp;Active
                            </div>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_3{$this->popup_id}\">Street: </td>
						<td style=\"background-color:#ffffff;\">" .
    						$this->form->text_area(
        						"name=street",
        						"value=" . stripslashes($this->current_vendor['street']),
        						"rows=3",
        						"cols=35",
        						( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
        					) .
        				"</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_4{$this->popup_id}\">City: *</td>
						<td style=\"background-color:#ffffff;\">" .
    						$this->form->text_box(
        						"name=city",
        						"value=" . stripslashes($this->current_vendor['city']),
        						"size=15",
        						"maxlength=255",
        						( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
        					) . "
        				</td>
					</tr>
                    <tr>
                        <td  style=\"text-align:right;background-color:#ffffff;\" id=\"err1_5{$this->popup_id}\">State: *</td>
                        <td style=\"background-color:#ffffff;\">" .
                        ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'general') ?
                            ( in_array($this->current_vendor['state'], $states) ?
                                $stateNames[ array_search($this->current_vendor['state'], $states) ] : $this->current_vendor['state']
                            ) .
                            $this->form->hidden( array(
	                            'state'	=>	$this->current_vendor['state']
                            ) ) :
                            "<input
                                type=\"hidden\"
                                name=\"stateSelect_default\"
                                id=\"stateSelect_default\"
                                value=\"{$this->current_vendor['state']}\" >
                            <div>
                                <select id=\"stateSelect\" name=\"state\"></select>
                            </div>"
                        ) . "
                        </td>
                    </tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_6{$this->popup_id}\">Zip: </td>
						<td style=\"background-color:#ffffff;\">" .
    						$this->form->text_box(
        						"name=zip",
        						"value={$this->current_vendor['zip']}",
        						"size=7",
        						"maxlength=10",
        						( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
        					) . "
            			</td>
					</tr>
                    <tr>
                        <td  style=\"text-align:right;background-color:#ffffff;\" id=\"err1_6a{$this->popup_id}\">Country: *</td>
                        <td style=\"background-color:#ffffff;\">" .
                        ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'general') ?
                            $country_names[ array_search(
                                ( $this->current_vendor['country'] ?
                                    $this->current_vendor['country']
                                    :
                                    ( defined('MY_COMPANY_COUNTRY') ?
                                        MY_COMPANY_COUNTRY : NULL
                                    )
                                ), $country_codes) ] .
                            $this->form->hidden( array(
                                'country' =>
                                ( $this->current_vendor['country'] ?
                                    $this->current_vendor['country'] :
                                        ( defined('MY_COMPANY_COUNTRY') ?
                                            MY_COMPANY_COUNTRY : NULL
                                        )
                                    )
                            ) )
                            :
                            "<input
                                type=\"hidden\"
                                name=\"countrySelect_default\"
                                id=\"countrySelect_default\"
                                value=\"" .
                                ( $this->current_vendor['country'] ?
                                    $this->current_vendor['country'] :
                                    ( defined('MY_COMPANY_COUNTRY') ?
                                        MY_COMPANY_COUNTRY : 'US'
                                    )
                                ) . "\">
                            <div>
                                <select id=\"countrySelect\" name=\"country\" onchange=\"updateState(this.id);if(\$('product_tax_holder')){if(this.value=='US'){\$('product_tax_holder').setStyle({'display':'block'});}else{\$('product_tax_holder').setStyle({'display':'none'});}}\"></select>
                            </div>"
                        ) . "
                        </td>
                    </tr>
					<tr>
						<td style=\"background-color:#ffffff;text-align:right;\" id=\"err1_7{$this->popup_id}\">Phone:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=phone","value=".$this->current_vendor['phone'],"size=18","maxlength=32",($this->vendor_hash && !$this->p->ck(get_class($this),'E','general') ? "readonly" : NULL))."</td>
					</tr>
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_8{$this->popup_id}\">Fax:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=fax","value=".$this->current_vendor['fax'],"size=18","maxlength=32",($this->vendor_hash && !$this->p->ck(get_class($this),'E','general') ? "readonly" : NULL))."</td>
					</tr>
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_8{$this->popup_id}\">Vendor No:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=vendor_no","value=".($this->current_vendor['vendor_no'] ? $this->current_vendor['vendor_no'] : NULL),"size=7","maxlength=11",($this->vendor_hash && !$this->p->ck(get_class($this),'E','general') ? "readonly" : NULL))."</td>
					</tr>
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_9{$this->popup_id}\">Vendor Account No:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=account_no","value=".$this->current_vendor['account_no'],"size=25","maxlength=128",($this->vendor_hash && !$this->p->ck(get_class($this),'E','general') ? "readonly" : NULL))."</td>
					</tr>
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_10{$this->popup_id}\">Electronic Order Email:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=order_email","value=".$this->current_vendor['order_email'],"size=35","maxlength=255",($this->vendor_hash && !$this->p->ck(get_class($this),'E','general') ? "readonly" : NULL))."</td>
					</tr>
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_11{$this->popup_id}\">Electronic Order Fax:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=order_fax","value=".$this->current_vendor['order_fax'],"size=18","maxlength=255",($this->vendor_hash && !$this->p->ck(get_class($this),'E','general') ? "readonly" : NULL))."</td>
					</tr>
                    <tr>
                        <td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_11{$this->popup_id}\">
                            E-Order Template:
                        </td>
                        <td style=\"background-color:#ffffff;\">" .
                        ( $efile_obj ?
                            "<a href=\"javascript:void(0);\" title=\"Add/Edit this vendor's assigned E-Order Template\" onClick=\"agent.call('vendors', 'sf_loadcontent', 'show_popup_window', 'edit_e_profile', 'popup_id=vendor_e_profile', 'vendor_hash={$this->vendor_hash}', 'parent_popup_id={$this->popup_id}');\" class=\"link_standard\">{$this->current_vendor['eorder_file']}</a>" : "<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors', 'sf_loadcontent', 'show_popup_window', 'edit_e_profile', 'popup_id=vendor_e_profile', 'vendor_hash={$this->vendor_hash}', 'parent_popup_id={$this->popup_id}');\" class=\"link_standard\">Assign E-Order Template</a>"
                        ) . "
                        </td>
                    </tr>
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_12{$this->popup_id}\">Default Order Method: </td>
						<td style=\"background-color:#ffffff;\">" .
							$this->form->radio(
    							"name=order_method",
    							"value=order_fax",
    							( $this->current_vendor['order_method'] == 'order_fax' ? "checked" : NULL ),
    							( $this->vendor_hash && ! $this->p->ck(get_class($this),'E','general') ? "disabled" : NULL ),
    							( $efile_obj && $e_interface_input ? "onClick=if(this.checked){toggle_display('eorder_input','none');}" : NULL )
    						) . "&nbsp;Fax
							<div style=\"padding-top:5px;\">" .
							$this->form->radio(
    							"name=order_method",
    							"value=order_email",
    							( $this->current_vendor['order_method'] == 'order_email' ? "checked" : NULL ),
    							( $this->vendor_hash && ! $this->p->ck(get_class($this),'E','general') ? "disabled" : NULL ),
    							( $efile_obj && $e_interface_input ? "onClick=if(this.checked){toggle_display('eorder_input','none');}" : NULL )
    						) . "&nbsp;Email
							</div>" .
    						( $efile_obj && $efile_obj->send_method ?
    							"<div style=\"padding-top:5px;\">" .
								$this->form->radio(
    								"name=order_method",
    								"value=order_eorder",
    								( $this->current_vendor['order_method'] == 'order_eorder' ? "checked" : NULL ),
    								( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "disabled" : NULL ),
    								( $e_interface_input ? "onClick=if(this.checked){toggle_display('eorder_input','block');}" : NULL )
    							) . "&nbsp;Electronic Interface
								</div>" .
    							( $e_interface_input ?
    								"<div id=\"eorder_input\" style=\"margin:10px 15px;display:" . ( $this->current_vendor['order_method'] == 'order_eorder' ? "block" : "none" ) . "\">$e_interface_input</div>" : NULL
    							) : NULL
							) . "
							<div style=\"padding-top:5px;\">" .
							$this->form->radio(
    							"name=order_method",
    							"value=",
    							( $this->current_vendor['order_method'] == '' ? "checked" : NULL ),
    							( $this->vendor_hash && ! $this->p->ck(get_class($this),'E','general') ? "disabled" : NULL ),
    							( $efile_obj && $e_interface_input ? "onClick=if(this.checked){toggle_display('eorder_input','none');}" : NULL )
    						) . "&nbsp;<i>none</i>
							</div>
						</td>
					</tr>
					" . $general_custom_fields . "
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\">
							Vendor Notes:".($this->p->ck(get_class($this),'E','general') ? "
							<div style=\"padding-top:5px;\">
								[<small><a class=\"link_standard\" href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','show_popup_window','edit_note','vendor_hash=".$this->vendor_hash."','popup_id=note_win')\">add a note</a></small>]
							</div>" : NULL)."
						</td>
						<td style=\"background-color:#ffffff;\">".$vendor_notes."</td>
					</tr>
				</table>
			</div>" : NULL);
		if ($this->p->ck(get_class($this),'V','payments')) {
			$tbl .= "
			<div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:15px;\" id=\"err2_1{$this->popup_id}\">Required Deposit Percentage:</td>
						<td style=\"background-color:#ffffff;padding-top:15px;\">".$this->form->text_box("name=deposit_percent","value=".($this->current_vendor['deposit_percent'] && $this->current_vendor['deposit_percent'] != 0 ? $this->current_vendor['deposit_percent'] * 100 : NULL),"size=4","maxlength=4","style=text-align:right;",($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL))." %</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_2{$this->popup_id}\">Early Payment Discount:</td>
						<td style=\"background-color:#ffffff;\">
							".$this->form->text_box("name=payment_discount","value=".($this->current_vendor['payment_discount'] != 0 ? $this->current_vendor['payment_discount'] * 100 : NULL),"size=4","maxlength=3","style=text-align:right;",($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL))."&nbsp;%&nbsp;
							if paid
							".$this->form->text_box("name=discount_days","value=".($this->current_vendor['discount_days'] ? $this->current_vendor['discount_days'] : NULL),"size=2","maxlength=2",($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL))." days prior to due date
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_3{$this->popup_id}\">Vendor's Payment Terms:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=payment_terms","value=".($this->current_vendor['payment_terms'] ? $this->current_vendor['payment_terms'] : NULL),"size=5","maxlength=3","style=text-align:right;",($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL))." days</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_3{$this->popup_id}\">Tax ID Number:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=tax_id","value=".$this->current_vendor['tax_id'],"maxlength=64",($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL))."</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_3{$this->popup_id}\">1099 Vendor:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->checkbox("name=1099","value=1",($this->current_vendor['1099'] ? "checked" : NULL),($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "disabled" : NULL))."</td>
                    </tr>".(defined('MULTI_CURRENCY') ? "
                    <tr>
                        <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\">Default Currency:</td>
                        <td style=\"background-color:#ffffff;\">
                            ".$this->form->select("currency",
                                                   $currency_out,
                                                   ($this->current_vendor['currency'] ? $this->current_vendor['currency'] : $sys->home_currency['code']),
                                                   $currency_in,
                                                   (!$this->p->ck(get_class($this),'E','payments') ? "disabled" : NULL),
                                                   "blank=1").
                            (!$this->p->ck(get_class($this),'E','payments') && $this->current_vendor['currency'] ?
                                $this->form->hidden(array('currency' => $this->current_vendor['currency'])) : NULL)."
                        </td>
                    </tr>" : NULL)."
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\">Group Invoices Into Single Check?</td>
						<td style=\"background-color:#ffffff;\">
							".$this->form->checkbox("name=group_invoices","value=1",(!$this->current_vendor || $this->current_vendor['group_invoices'] ? "checked" : NULL),($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "disabled" : NULL))."
							&nbsp;
							<small>(Multiple invoices can be grouped and paid in a single check)</small>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" >Comment To Appear On POs:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=po_comment","value=".$this->current_vendor['po_comment'],"size=35","maxlength=128",($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL))."</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err2_4{$this->popup_id}\">
							Small Order Fee:
							<div style=\"margin-top:10px;font-style:italic;\">
								<small>Quoted?</small>
								".$this->form->checkbox("name=sof_quoted","value=1",($this->current_vendor['sof_quoted'] ? "checked" : NULL))."
							</div>
						</td>
						<td style=\"background-color:#ffffff;vertical-align:top;\">
							<span id=\"err2_13{$this->popup_id}\">If under $</span>&nbsp;".
							$this->form->text_box("name=sof_amount",
												  "value=".($this->current_vendor['sof_amount'] ? number_format($this->current_vendor['sof_amount'],2) : NULL),
												  "size=8",
												  "style=text-align:right;",
												  "onBlur=this.value=formatCurrency(this.value);",
												  ($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL)
												  )."
							&nbsp;".($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ?
										$this->form->hidden(array('sof_amount_type' => $this->current_vendor['sof_amount_type'])).
										($this->current_vendor['sof_amount_type'] == 'L' ?
											"List" : "Net")	: $this->form->select("sof_amount_type",
																			  		array("List","Net"),
																			  		$this->current_vendor['sof_amount_type'],
																			  		array("L","N")))."
							&nbsp;<span id=\"err2_15{$this->popup_id}\">then add</span> ".
							$this->form->text_box("name=sof_amount_add",
												  "value=".($this->current_vendor['sof_amount_add_type'] == 'P' ? $this->current_vendor['sof_amount_add']*100 : ($this->current_vendor['sof_amount_add'] > 0 ? number_format($this->current_vendor['sof_amount_add'],2) : NULL)),
												  "size=4",
												  "style=text-align:right;",
												  ($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL)
												  )."
							&nbsp;".($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ?
										$this->form->hidden(array('sof_amount_add_type' => $this->current_vendor['sof_amount_add_type'])).
										($this->current_vendor['sof_amount_add_type'] == 'D' ?
											'dollars' : '%') :
												$this->form->select("sof_amount_add_type",
															  		array("dollars","%"),
															  		$this->current_vendor['sof_amount_add_type'],
															  		array("D","P"),"onChange=if(this.options[this.selectedIndex].value=='P'){\$('sof_amount_add_type_of_holder{$this->popup_id}').style.visibility='visible';}else{\$('sof_amount_add_type_of_holder{$this->popup_id}').style.visibility='hidden';}"))."
							<span id=\"sof_amount_add_type_of_holder{$this->popup_id}\" style=\"visibility:".($this->current_vendor['sof_amount_add_type'] == 'P' ? 'visible' : 'hidden').";\">
								<span id=\"err2_16{$this->popup_id}\">of</span>".($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ?
									$this->form->hidden(array('sof_amount_add_type_of' => $this->current_vendor['sof_amount_add_type_of'])).
									($this->current_vendor['sof_amount_add_type_of'] == 'L' ?
										" List" : " Net") :
											$this->form->select("sof_amount_add_type_of",
																array("List","Net"),
																$this->current_vendor['sof_amount_add_type_of'],
																array("L","N")))."
							</span>
							<table >
								<tr>
									<td style=\"padding-left:10px;padding-top:5px;\" id=\"sof_effective_date_holder{$this->popup_id}\"></td>
									<td style=\"padding-top:5px;\" id=\"sof_expire_date_holder{$this->popup_id}\">";
									if (!$this->vendor_hash || ($this->vendor_hash && $this->p->ck(get_class($this),'E','payments')))
										$this->content["jscript"][] = "
										setTimeout('DateInput(\'sof_effective_date\',".($this->current_product['sof_effective_date'] ? "true" : "false").", \'YYYY-MM-DD\',\'".($this->current_vendor['sof_effective_date'] ? $this->current_vendor['sof_effective_date'] : NULL)."\',1,\'sof_effective_date_holder{$this->popup_id}\',\'<span id=\"err2_17{$this->popup_id}\">Effective:</span>&nbsp;\')',20);
										setTimeout('DateInput(\'sof_expire_date\', false, \'YYYY-MM-DD\',\'".($this->current_vendor['sof_expire_date'] ? $this->current_vendor['sof_expire_date'] : NULL)."\',1,\'sof_expire_date_holder{$this->popup_id}\',\'<span id=\"err2_18{$this->popup_id}\">Thru:</span>&nbsp;\')',41);";
									else
										$tbl .=
										($this->current_vendor['sof_effective_date'] ?
											"Effective: ".date(DATE_FORMAT,strtotime($this->current_vendor['sof_effective_date'])).
										    $this->form->hidden(array('sof_effective_date' => $this->current_vendor['sof_effective_date'])) : NULL).
										($this->current_vendor['sof_expire_date'] ?
											"&nbsp;&nbsp;&nbsp;Thru: ".date(DATE_FORMAT,strtotime($this->current_vendor['sof_expire_date'])).
										    $this->form->hidden(array('sof_expire_date' => $this->current_vendor['sof_expire_date'])) : NULL);
								$tbl .= "
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err2_5{$this->popup_id}\">
							Freight Terms:
							<div style=\"margin-top:10px;font-style:italic;\">
								<small>Quoted?</small>
								".$this->form->checkbox("name=frf_quoted","value=1",($this->current_vendor['frf_quoted'] ? "checked" : NULL))."
							</div>
						</td>
						<td style=\"background-color:#ffffff;vertical-align:top;\">
							<span id=\"err2_20{$this->popup_id}\">If under $</span>&nbsp;".
							$this->form->text_box("name=frf_amount",
												  "value=".($this->current_vendor['frf_amount'] ? number_format($this->current_vendor['frf_amount'],2) : NULL),
												  "size=8",
												  "style=text-align:right;",
												  "onBlur=this.value=formatCurrency(this.value);",
												  ($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL)
												  )."
							&nbsp;".($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ?
										$this->form->hidden(array('frf_amount_type' => $this->current_vendor['frf_amount_type'])).
										($this->current_vendor['frf_amount_type'] == 'L' ?
											"List" : "Net") :
												$this->form->select("frf_amount_type",
															  		array("List","Net"),
															  		$this->current_vendor['frf_amount_type'],
															  		array("L","N")))."
							&nbsp;<span id=\"err2_21{$this->popup_id}\">then add </span>
							".$this->form->text_box("name=frf_amount_add",
												    "value=".($this->current_vendor['frf_amount_add_type'] == 'P' ? $this->current_vendor['frf_amount_add']*100 : ($this->current_vendor['frf_amount_add'] ? number_format($this->current_vendor['frf_amount_add'],2) : NULL)),
													"size=4",
													"style=text-align:right;",
													 ($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL)
													)."
							&nbsp;".($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ?
										$this->form->hidden(array('frf_amount_add_type' => $this->current_vendor['frf_amount_add_type'])).
										($this->current_vendor['frf_amount_add_type'] == 'D' ?
											"dollars" : "%") :
												$this->form->select("frf_amount_add_type",
																array("dollars","%"),
																$this->current_vendor['frf_amount_add_type'],
																array("D","P"),
																"onChange=if(this.options[this.selectedIndex].value=='P'){\$('freight_amount_add_type_of_holder{$this->popup_id}').style.visibility='visible';}else{\$('freight_amount_add_type_of_holder{$this->popup_id}').style.visibility='hidden';}"))."
							<span id=\"freight_amount_add_type_of_holder{$this->popup_id}\" style=\"visibility:".($this->current_vendor['frf_amount_add_type'] == 'P' ? 'visible' : 'hidden').";\">
								<span id=\"err2_22{$this->popup_id}\">of</span>".($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ?
								$this->form->hidden(array('frf_amount_add_type_of' => $this->current_vendor['frf_amount_add_type_of'])).
								($this->current_vendor['frf_amount_add_type_of'] == 'L' ?
									" List" : " Net") :
										$this->form->select("frf_amount_add_type_of",
															array("List","Net"),
															$this->current_vendor['frf_amount_add_type_of'],
															array("L","N")))."
							</span>
							<table>
								<tr>
									<td style=\"padding-left:10px;padding-top:5px;\" id=\"frf_effective_date_holder{$this->popup_id}\"></td>
									<td style=\"padding-top:5px;\" id=\"frf_expire_date_holder{$this->popup_id}\">";
									if (!$this->vendor_hash || ($this->vendor_hash && $this->p->ck(get_class($this),'E','payments')))
										$this->content["jscript"][] = "										setTimeout('DateInput(\'frf_effective_date\', ".($this->current_vendor['frf_effective_date'] ? "true" : "false").", \'YYYY-MM-DD\',\'".($this->current_vendor['frf_effective_date'] ? $this->current_vendor['frf_effective_date'] : NULL)."\',1,\'frf_effective_date_holder{$this->popup_id}\',\'<span id=\"err2_23{$this->popup_id}\">Effective:</span>&nbsp;\')',60);
										setTimeout('DateInput(\'frf_expire_date\', false, \'YYYY-MM-DD\',\'".($this->current_vendor['frf_expire_date'] ? $this->current_vendor['frf_expire_date'] : NULL)."\',1,\'frf_expire_date_holder{$this->popup_id}\',\'<span id=\"err2_24{$this->popup_id}\">Thru:</span>&nbsp;\')',81);";
									else
										$tbl .=
										($this->current_vendor['frf_effective_date'] ?
											"Effective: ".date(DATE_FORMAT,strtotime($this->current_vendor['frf_effective_date'])).
										    $this->form->hidden(array('frf_effective_date' => $this->current_vendor['frf_effective_date'])) : NULL).
										($this->current_vendor['frf_expire_date'] ?
											"&nbsp;&nbsp;&nbsp;Thru: ".date(DATE_FORMAT,strtotime($this->current_vendor['frf_expire_date'])).
										    $this->form->hidden(array('frf_expire_date' => $this->current_vendor['frf_expire_date'])) : NULL);
								$tbl .= "
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err2_6{$this->popup_id}\">
							Fuel Surcharge:
							<div style=\"margin-top:10px;font-style:italic;\">
								<small>Quoted?</small>
								".$this->form->checkbox("name=fsf_quoted","value=1",($this->current_vendor['fsf_quoted'] ? "checked" : NULL))."
							</div>
						</td>
						<td style=\"background-color:#ffffff;vertical-align:top;\">
							".$this->form->text_box("name=fsf_amount_add",
													"value=".($this->current_vendor['fsf_amount_add_type'] == 'P' ? $this->current_vendor['fsf_amount_add']*100 : ($this->current_vendor['fsf_amount_add'] ? number_format($this->current_vendor['fsf_amount_add'],2) : NULL)),
													"size=9",
													"style=text-align:right;",
													($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL)
													)."
							&nbsp;".($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ?
										$this->form->hidden(array('fsf_amount_add_type' => $this->current_vendor['fsf_amount_add_type'])).
										($this->current_vendor['fsf_amount_add_type'] ?
											'dollars' : '%') :
												$this->form->select("fsf_amount_add_type",
																array("dollars","%"),
																$this->current_vendor['fsf_amount_add_type'],
																array("D","P"),
																"onChange=if(this.options[this.selectedIndex].value=='P'){\$('fsf_amount_add_type_of_holder{$this->popup_id}').style.visibility='visible';}else{\$('fsf_amount_add_type_of_holder{$this->popup_id}').style.visibility='hidden';$('fsf_amount_add').value=formatCurrency($F('fsf_amount_add'));}"))."
							<span id=\"fsf_amount_add_type_of_holder{$this->popup_id}\" style=\"visibility:".($this->current_vendor['fsf_amount_add_type'] == 'P' ? 'visible' : 'hidden').";\">
								of ".($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ?
										$this->form->hidden(array('fsf_amount_add_type_of' => $this->current_vendor['fsf_amount_add_type_of'])).
										($this->current_vendor['fsf_amount_add_type_of'] == 'L' ?
											" List" : " Net") :
												$this->form->select("fsf_amount_add_type_of",
																	array("List","Net"),
																	$this->current_vendor['fsf_amount_add_type_of'],
																	array("L","N")))."
							</span>
							<table>
								<tr>
									<td style=\"padding-left:10px;padding-top:5px;\" id=\"fsf_effective_date_holder{$this->popup_id}\"></td>
									<td style=\"padding-top:5px;\" id=\"fsf_expire_date_holder{$this->popup_id}\">";
									if (!$this->vendor_hash || ($this->vendor_hash && $this->p->ck(get_class($this),'E','payments')))
										$this->content["jscript"][] = "
										setTimeout('DateInput(\'fsf_effective_date\',".($this->current_vendor['fsf_effective_date'] ? "true" : "false").", \'YYYY-MM-DD\',\'".($this->current_vendor['fsf_effective_date'] ? $this->current_vendor['fsf_effective_date'] : NULL)."\',1,\'fsf_effective_date_holder{$this->popup_id}\',\'<span id=\"err2_26{$this->popup_id}\">Effective:</span>&nbsp;\')',100);
										setTimeout('DateInput(\'fsf_expire_date\',false, \'YYYY-MM-DD\',\'".($this->current_vendor['fsf_expire_date'] ? $this->current_vendor['fsf_expire_date'] : NULL)."\',1,\'fsf_expire_date_holder{$this->popup_id}\',\'<span id=\"err2_27{$this->popup_id}\">Thru:</span>&nbsp;\')',120);";
									else
										$tbl .=
										($this->current_vendor['fsf_effective_date'] ?
											"Effective: ".date(DATE_FORMAT,strtotime($this->current_vendor['fsf_effective_date'])).
										    $this->form->hidden(array('fsf_effective_date' => $this->current_vendor['fsf_effective_date'])) : NULL).
										($this->current_vendor['fsf_expire_date'] ?
											"&nbsp;&nbsp;&nbsp;Thru: ".date(DATE_FORMAT,strtotime($this->current_vendor['fsf_expire_date'])).
										    $this->form->hidden(array('fsf_expire_date' => $this->current_vendor['fsf_expire_date'])) : NULL);

								$tbl .= "
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err2_7{$this->popup_id}\">Call Before Delivery Charge:</td>
						<td style=\"background-color:#ffffff;vertical-align:top;\">
							$ ".$this->form->text_box("name=cbd_amount_add",
													  "value=".($this->current_vendor['cbd_amount_add'] ? number_format($this->current_vendor['cbd_amount_add'],2) : NULL),
												  	  "size=7",
												  	  "style=text-align:right;",
												  	  "onBlur=this.value=formatCurrency(this.value);",
													  ($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL)
												  	  )."
							<table>
								<tr>
									<td style=\"padding-left:10px;padding-top:5px;\" id=\"cbd_effective_date_holder{$this->popup_id}\"></td>
									<td style=\"padding-top:5px;\" id=\"cbd_expire_date_holder{$this->popup_id}\">";
									if (!$this->vendor_hash || ($this->vendor_hash && $this->p->ck(get_class($this),'E','payments')))
										$this->content["jscript"][] = "
										setTimeout('DateInput(\'cbd_effective_date\',".($this->current_vendor['cbd_effective_date'] ? "true" : "false").", \'YYYY-MM-DD\',\'".($this->current_vendor['cbd_effective_date'] ? $this->current_vendor['cbd_effective_date'] : NULL)."\',1,\'cbd_effective_date_holder{$this->popup_id}\',\'<span id=\"err2_29{$this->popup_id}\">Effective:</span>&nbsp;\')',140);
										setTimeout('DateInput(\'cbd_expire_date\', false, \'YYYY-MM-DD\',\'".($this->current_vendor['cbd_expire_date'] ? $this->current_vendor['cbd_expire_date'] : NULL)."\',1,\'cbd_expire_date_holder{$this->popup_id}\',\'<span id=\"err2_30{$this->popup_id}\">Thru:</span>&nbsp;\')',160);";
									else
										$tbl .=
										($this->current_vendor['cbd_effective_date'] ?
											"Effective: ".date(DATE_FORMAT,strtotime($this->current_vendor['cbd_effective_date'])).
										    $this->form->hidden(array('cbd_effective_date' => $this->current_vendor['cbd_effective_date'])) : NULL).
										($this->current_vendor['cbd_expire_date'] ?
											"&nbsp;&nbsp;&nbsp;Thru: ".date(DATE_FORMAT,strtotime($this->current_vendor['cbd_expire_date'])).
										    $this->form->hidden(array('cbd_expire_date' => $this->current_vendor['cbd_expire_date'])) : NULL);

								$tbl .= "
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err2_8{$this->popup_id}\">Manual Credit:</td>
						<td style=\"background-color:#ffffff;vertical-align:top;\">
							Activate Manual Credit? ".$this->form->checkbox("name=manual_credit","value=1",($this->current_vendor['manual_credit'] ? "checked" : NULL))."
							</br></br>Account: " .
			                                    $this->form->text_box(
												"name=account_name[0]",
												"id=err{0}",
												"value=" . ( $this->current_vendor['manual_credit_account'] ? $this->current_vendor['manual_credit_account'] : NULL ),
												"autocomplete=off",
												"style=width:125px",
												"tabindex=" . $tabindex++,
												"onFocus=if( this.value ) { select(); } position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('accounting', 'account_name[0]', 'account_hash[0]', 1);}",
												"onKeyUp=if(ka==false && this.value){key_call('accounting', 'account_name[0]', 'account_hash[0]', 1);}",
												"onBlur=key_clear();"
											) .
											$this->form->hidden(array(
	    										"account_hash[0]"	=>	( $this->current_vendor['manual_credit_account_hash'] ? $this->current_vendor['manual_credit_account_hash'] : NULL )
											) ) . "
											<a href=\"javascript:void(0);\" onClick=\"position_element(\$('keyResults'), findPos(\$('account_name[0]'), 'top')+20, findPos(\$('account_name[0]'), 'left')+1);key_list('accounting', 'account_name[0]', 'account_hash[0]', '*');\"><img src=\"images/arrow_down.gif\" border=\"0\" title=\"Show all accounts\" /></a>
							</br></br>Amount % ".$this->form->text_box("name=manual_credit_amount",
													  "value=".($this->current_vendor['manual_credit_amount'] ? number_format($this->current_vendor['manual_credit_amount'],2) : NULL),
												  	  "size=7",
												  	  "style=text-align:right;",
												  	  "onBlur=this.value=formatCurrency(this.value);",
													  ($this->vendor_hash && !$this->p->ck(get_class($this),'E','payments') ? "readonly" : NULL)
												  	  )."
						</td>
					</tr>";

			        unset($this->cust->class_fields);
			        $this->cust->fetch_class_fields('payment');
			        if ($this->cust->class_fields['payment']) {
			            $fields =& $this->cust->class_fields['payment'];
			            for ($i = 0; $i < count($fields); $i++)
			                $tbl .= "
			                <tr>
			                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err_custom".$fields[$i]['obj_id']."\">".$fields[$i]['field_name'].": ".($fields[$i]['required'] ? "*" : NULL)."</td>
			                    <td style=\"background-color:#ffffff;\">".$this->cust->build_input($fields[$i],
			                                                                                       $this->current_vendor[$fields[$i]['col_name']])."
			                    </td>
			                </tr>";
			        }

					$tbl .= "
					<tr>
						<td colspan=\"2\" style=\"background-color:#efefef;font-weight:bold;color:#00477f;font-style:italic;padding-left:15px;\">
							Billing Remittance Information
							<span style=\"text-size:75%;font-style:italic;\">If different from general info</span>
						</td>
					</tr>
                    <tr>
                        <td style=\"text-align:right;background-color:#ffffff;width:35%;padding-top:15px;\" id=\"err2_8{$this->popup_id}\">Remittance Name:</td>
                        <td style=\"background-color:#ffffff;padding-top:15px;\">" .
                            $this->form->text_box(
                                "name=remit_name",
                                "value=" . stripslashes($this->current_vendor['remit_name']),
                                "size=35",
                                "maxlength=255",
                                ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL)
                            ) . "
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:right;background-color:#ffffff;width:35%;vertical-align:top;\" id=\"err2_9{$this->popup_id}\">Street:</td>
                        <td style=\"background-color:#ffffff;\">" .
                            $this->form->text_area(
                                "name=remit_street",
                                "value=" . stripslashes($this->current_vendor['remit_street']),
                                "rows=2",
                                "cols=35",
                                ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL)
                            ) . "
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:right;background-color:#ffffff;width:35%;\" id=\"err2_10{$this->popup_id}\">City:</td>
                        <td style=\"background-color:#ffffff;\">" .
                            $this->form->text_box(
                                "name=remit_city",
                                "value=" . stripslashes($this->current_vendor['remit_city']),
                                "maxlength=255",
                                ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL)
                            ) . "
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:right;background-color:#ffffff;width:35%;\" id=\"err2_11{$this->popup_id}\">State:</td>
                        <td style=\"background-color:#ffffff;\">" .
                        ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ?
                            ( in_array($this->current_vendor['remit_state'], $states) ?
                                $stateNames[ array_search($this->current_vendor['remit_state'], $states) ] : $this->current_vendor['remit_state']
                            ) :
                            "<input
                                type=\"hidden\"
                                name=\"remit_stateSelect_default\"
                                id=\"remit_stateSelect_default\"
                                value=\"{$this->current_vendor['remit_state']}\" >
                            <div><select id=\"remit_stateSelect\" name=\"remit_state\" ></select></div>"
                        ) . "
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:right;background-color:#ffffff;width:35%;\" id=\"err2_12{$this->popup_id}\">Zip:</td>
                        <td style=\"background-color:#ffffff;\">" .
                            $this->form->text_box(
                                "name=remit_zip",
                                "value=" . stripslashes($this->current_vendor['remit_zip']),
                                "size=7",
                                "maxlength=10",
                                ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ? "readonly" : NULL)
                            ) . "
                        </td>
                    </tr>
                    <tr>
                        <td style=\"text-align:right;background-color:#ffffff;width:35%;\" id=\"err2_13{$this->popup_id}\">Country:</td>
                        <td style=\"background-color:#ffffff;\">" .
                        ( $this->vendor_hash && ! $this->p->ck(get_class($this), 'E', 'payments') ?
                            $country_names[ array_search(
                                ( $this->current_vendor['remit_country'] ?
                                    $this->current_vendor['remit_country']
                                    :
                                    ( defined('MY_COMPANY_COUNTRY') ?
                                        MY_COMPANY_COUNTRY : NULL
                                    )
                                ), $country_codes) ] .
                            $this->form->hidden( array(
                                'remit_country' =>
                                ( $this->current_vendor['remit_country'] ?
                                    $this->current_vendor['remit_country'] :
                                        ( defined('MY_COMPANY_COUNTRY') ?
                                            MY_COMPANY_COUNTRY : NULL
                                        )
                                    )
                            ) )
                            :
                            "<input
                                type=\"hidden\"
                                name=\"remit_countrySelect_default\"
                                id=\"remit_countrySelect_default\"
                                value=\"" .
                                ( $this->current_vendor['remit_country'] ?
                                    $this->current_vendor['remit_country']
                                    :
                                    ( defined('MY_COMPANY_COUNTRY') ?
                                        MY_COMPANY_COUNTRY : 'US'
                                    )
                                ) . "\">
                            <div><select id=\"remit_countrySelect\" name=\"remit_country\" onchange=\"updateState(this.id);\"></select></div>"
                        ) . "
                        </td>
                    </tr>
				</table>
			</div>";
		}
		$tbl .= ($this->p->ck(get_class($this),'V','contacts') ? "
			<div id=\"tcontent3{$this->popup_id}\" class=\"tabcontent\">
				".$this->contact_info_form()."
			</div>" : NULL).($this->p->ck(get_class($this),'V','locations') ? "
			<div id=\"tcontent4{$this->popup_id}\" class=\"tabcontent\">
				".$this->location_info_form()."
			</div>" : NULL).($this->vendor_hash ? ($this->p->ck(get_class($this),'V','products') ? "
			<div id=\"tcontent5{$this->popup_id}\" class=\"tabcontent\">
				".$this->product_info_form()."
			</div>" : NULL).($this->p->ck(get_class($this),'V','discounting') ? "
			<div id=\"tcontent6{$this->popup_id}\" class=\"tabcontent\">
				".$this->discount_info_form()."
			</div>" : NULL).($this->p->ck(get_class($this),'V','stats') ? "
			<div id=\"tcontent7{$this->popup_id}\" class=\"tabcontent\">
				".$this->vendor_stats_form()."
			</div>" : NULL) : NULL)."
			<div style=\"text-right;padding:15px;\" >".(!$this->vendor_hash || ($this->lock && $this->vendor_hash && $this->p->ck(get_class($this),'E')) ?
				$this->form->button("value=".($this->vendor_hash ? "Update Vendor" : "Add Vendor"),"onClick=submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=".($this->vendor_hash ? "Update Vendor" : "Add Vendor")."');") : NULL)."
				&nbsp;".($this->lock && $this->vendor_hash && $this->p->ck(get_class($this),'D') ?
				$this->form->button("value=Delete Vendor","onClick=if(confirm('Are you sure you want to delete this vendor? This action CANNOT be undone!')) {submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=Delete Vendor');}") : NULL).$this->p->lock_stat(get_class($this),$this->popup_id)."
			</div>
		</div>".
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')',10);";
		$this->content['jscript'][] = "setTimeout(function(){initCountry('countrySelect remit_countrySelect location_countrySelect', 'stateSelect remit_stateSelect location_stateSelect');}, 500);";
		if ($this->ajax_vars['tab_to'])
			$this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_".$this->ajax_vars['tab_to'].$this->popup_id."\'))',100)";

		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function vendor_stats_form() {
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		$invoice = new payables($this->current_hash);
		$invoice->set_values( array(
    		"vendor_hash" =>  $this->current_vendor['vendor_hash']
		) );
		$invoice->fetch_open_payables();

		$tbl = "
		<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
						<tr>
							<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\">
								Vendor Statistics for ".$this->current_vendor['vendor_name']." as of ".date(TIMESTAMP_FORMAT)."
								<div style=\"margin:10px;width:600px;padding:0;\">
									<fieldset>
										<legend>Open Bills (".count($invoice->invoice_info).")</legend>
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
											<tr>
												<td style=\"padding:10px 25px;\" class=\"smallfont\">";
												if (count($invoice->invoice_info)) {
													$tbl .= "
													<div ".(count($invoice->invoice_info) > 3 ? "style=\"height:100px;overflow:auto;\"" : NULL).">
														<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:95%;\">
															<tr>
																<td class=\"smallfont\" style=\"background-color:#efefef;\">Invoice No.</td>
																<td class=\"smallfont\" style=\"background-color:#efefef;\">Due Date</td>
																<td class=\"smallfont\" style=\"background-color:#efefef;\">Invoice Amount</td>
																<td class=\"smallfont\" style=\"background-color:#efefef;\">Amount Due</td>
															</tr>
															<tr>
																<td colspan=\"4\" style=\"background-color:#cccccc;\"</td>
															</tr>";
														for ($i = 0; $i < count($invoice->invoice_info); $i++) {
															$tbl .= "
															<tr onClick=\"agent.call('payables','sf_loadcontent','show_popup_window','edit_invoice','invoice_hash=".$invoice->invoice_info[$i]['invoice_hash']."','popup_id=line_item','new=true');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\" >
																<td class=\"smallfont\">".$invoice->invoice_info[$i]['invoice_no']."</td>
																<td class=\"smallfont\">".date(DATE_FORMAT,strtotime($invoice->invoice_info[$i]['due_date']))."</td>
																<td class=\"smallfont\">$".number_format($invoice->invoice_info[$i]['amount'],2)."</td>
																<td class=\"smallfont\">$".number_format($invoice->invoice_info[$i]['balance'],2)."</td>
															</tr>".($i < count($invoice->invoice_info) - 1 ? "
															<tr>
																<td colspan=\"4\" style=\"background-color:#cccccc;\"</td>
															</tr>" : NULL);
														}
													$tbl .= "
														</table>
													</div>";
												} else
													$tbl .= "There are no open bills for this vendor.";

												$tbl .= "
												</td>
											</tr>
										</table>
									</fieldset>
									<fieldset style=\"margin-top:10px\">
										<legend>Outstanding A/P</legend>
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
											<tr>
												<td class=\"smallfont\" style=\"padding:10px 0 0 25px\" colspan=\"2\">
													Total Outstanding: ".($this->current_vendor['balance_total'] < 0 ?
														"<span style=\"color:#ff0000;\">($".number_format($this->current_vendor['balance_total'] * -1,2).")</span>" : "$".number_format($this->current_vendor['balance_total'],2))."
												</td>
											</tr>".($this->current_vendor['balance_total'] != 0 ? "
											<tr>
												<td style=\"padding-left:45px;\" >
													<table style=\"width:100%;\">
														<tr>
															<td class=\"smallfont\" style=\"width:50%;\">
																<div >
																	Total Current: ".($this->current_vendor['balance_current'] < 0 ?
																		"<span style=\"color:#ff0000;\">($".number_format($this->current_vendor['balance_current'] * -1,2).")</span>" : "$".number_format($this->current_vendor['balance_current'],2))."
																</div>
																<div style=\"padding-top:5px;\">
																	Total Over ".ACCOUNT_AGING_SCHED1.": ".($this->current_vendor['balance_sched1'] < 0 ?
																		"<span style=\"color:#ff0000;\">($".number_format($this->current_vendor['balance_sched1'] * -1,2).")</span>" : "$".number_format($this->current_vendor['balance_sched1'],2))."
																</div>
															</td>
															<td class=\"smallfont\" style=\"width:50%;\">
																<div style=\"padding-top:5px;\">
																	Total Over ".ACCOUNT_AGING_SCHED2.": ".($this->current_vendor['balance_sched2'] < 0 ?
																		"<span style=\"color:#ff0000;\">($".number_format($this->current_vendor['balance_sched2'] * -1,2).")</span>" : "$".number_format($this->current_vendor['balance_sched2'],2))."
																</div>
																<div style=\"padding-top:5px;\">
																	Total Over ".ACCOUNT_AGING_SCHED3.": ".($this->current_vendor['balance_sched3'] < 0 ?
																		"<span style=\"color:#ff0000;\">($".number_format($this->current_vendor['balance_sched3'] * -1,2).")</span>" : "$".number_format($this->current_vendor['balance_sched3'],2))."
																</div>
															</td>
														</tr>
													</table>
												</td>
											</tr>" : NULL)."
										</table>
									</fieldset>";
									$current_qtr = get_quarter();
									list($qtr_start,$qtr_end) = get_quarter_dates($current_qtr);

									$result = $this->db->query("SELECT
																SUM(purchase_order.order_amount) AS overall_booked ,
																SUM(CASE
																	WHEN purchase_order.po_date BETWEEN '".date("Y-m-01")."' AND CURDATE()
																		THEN purchase_order.order_amount
																		ELSE 0
																	END) AS booked_mtd ,
																SUM(CASE
																	WHEN purchase_order.po_date BETWEEN '".$qtr_start."' AND CURDATE()
																		THEN purchase_order.order_amount
																		ELSE 0
																	END) AS booked_qtd ,
																SUM(CASE
																	WHEN purchase_order.po_date BETWEEN '".date("Y-01-01")."' AND CURDATE()
																		THEN purchase_order.order_amount
																		ELSE 0
																	END) AS booked_ytd
															   FROM `purchase_order`
															   WHERE purchase_order.vendor_hash = '".$this->vendor_hash."' AND purchase_order.deleted = 0");
									$row = $this->db->fetch_assoc($result);

									$tbl .= "
									<fieldset style=\"margin-top:10px\">
										<legend>Purchase Order Booking</legend>
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
											<tr>
												<td class=\"smallfont\" style=\"padding-left:25px;\ colspan=\"2\">
													<div style=\"padding-top:5px;\">
														Total Booked Sales:
														<div style=\"margin-left:45px;\">
															<table style=\"width:100%;\">
																<tr>
																	<td style=\"width:50%;\">
																		<table>
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;padding-top:5px;\">MTD:</td>
																				<td class=\"smallfont\">$".number_format($row['booked_mtd'],2)."</td>
																			</tr>
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;padding-top:5px;\">QTD:</td>
																				<td class=\"smallfont\">$".number_format($row['booked_qtd'],2)."</td>
																			</tr>
																		</table>
																	</td>
																	<td style=\"width:50%;\">
																		<table>
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;padding-top:5px;\">YTD:</td>
																				<td class=\"smallfont\">$".number_format($row['booked_ytd'],2)."</td>
																			</tr>
																			<tr>
																				<td class=\"smallfont\" style=\"text-align:right;padding-top:5px;\">Overall:</td>
																				<td class=\"smallfont\">$".number_format($row['overall_booked'],2)."</td>
																			</tr>
																		</table>
																	</td>
																</tr>
															</table>
														</div>
													</div>
												</td>
											</tr>
										</table>
									</fieldset>
								</div>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>";

		if ($this->ajax_vars['otf'])
			$this->content['html']["tcontent6{$this->popup_id}"] = $tbl;
		else
			return $tbl;
	}

	function discount_info_form() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		# If we're already editing a vendor
		if ( $this->ajax_vars['vendor_hash'] && ! $this->vendor_hash ) {

			if ( ! $this->fetch_master_record($this->ajax_vars['vendor_hash']) )
    			return $this->__trigger_error("System error encountered when attempting to lookup vendor record. Please reload window and try again. <!-- Tried fetching vendor [ {$this->ajax_vars['vendor_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);
		}

		$action = $this->ajax_vars['action'];

		if ( $this->ajax_vars['discount_hash'] ) {

			if ( ! $this->discounting->fetch_discount_record($this->ajax_vars['discount_hash']) )
    			return $this->__trigger_error("System error encountered when attempting to lookup discount record. Please reload window and try again. <!-- Tried fetching discount [ {$this->ajax_vars['discount_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

    		if ( $this->ajax_vars['item_hash'] ) {

	            if ( ! $this->discounting->fetch_item_record($this->ajax_vars['item_hash']) )
	                return $this->__trigger_error("System error encountered when attempting to lookup discount item record. Please reload window and try again. <!-- Tried fetching discount item [ {$this->ajax_vars['item_hash']} ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);
    		}
		}

		$discount_items_exists = $products_out = $products_in = $products_out1 = $products_in1 = array();

        $products_out1[] = "** APPLY ON ALL PRODUCTS **";
        $products_in1[] = "*";

		if ( $this->discounting->discount_hash ) {

			if ( ! $this->discounting->item_hash ) {

				$r = $this->db->query("SELECT t1.product_hash
				                       FROM discount_details t1
				                       WHERE t1.discount_hash = '{$this->discounting->discount_hash}' AND ISNULL( t1.item_no ) AND t1.deleted = 0");
				while ( $row = $this->db->fetch_assoc($r) )
	                array_push($discount_items_exists, $row['product_hash']);
			}

			$this->fetch_products(0,$this->total_products);
			for ( $i = 0; $i < $this->total_products; $i++ ) {

				if ( ! in_array($this->vendor_products[$i]['product_hash'], $discount_items_exists) ) {

					$products_out[] = $this->vendor_products[$i]['product_name'];
					$products_in[] = $this->vendor_products[$i]['product_hash'];
				}

	            $products_out1[] = $this->vendor_products[$i]['product_name'];
	            $products_in1[] = $this->vendor_products[$i]['product_hash'];
			}
		}

		if ( $action == 'addnew' ) { # Input form to add new discount parent record

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">" .
    			( $this->discounting->discount_hash ?
	                "<tr>
	                    <td style=\"background-color:#e6e6e6;text-align:left;vertical-align:bottom;\" colspan=\"2\" class=\"smallfont\">
	                        <div style=\"float:right;padding-right:15px;\">
	                            <a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','action=showtable','vendor_hash={$this->current_vendor['vendor_hash']}','discount_hash={$this->discounting->discount_hash}');\" ><img src=\"images/table.gif\" border=\"0\" style=\"margin-bottom:0\" /></a>
	                            <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','action=showtable','vendor_hash={$this->current_vendor['vendor_hash']}','discount_hash={$this->discounting->discount_hash}');\" >view discount table</a>
	                        </div>
	                        <div id=\"discount_standard_holder{$this->popup_id}\" style=\"padding-top:5px;display:".(!$this->discounting->current_discount || $this->discounting->current_discount['discount_type'] == 'S' ? "block" : "none")."\">
	                            ".$this->form->checkbox("name=discount_default","value=1",($this->discounting->current_discount['discount_default'] ? "checked" : NULL))."
	                            &nbsp;
	                            Make this the default standard discount for " .
    	                        ( strlen($this->current_vendor['vendor_name']) > 17 ?
	                                stripslashes( substr($this->current_vendor['vendor_name'], 0, 17) ) . "..." : stripslashes($this->current_vendor['vendor_name'])
	                            ) . "?
	                        </div>
	                    </td>
	                </tr>" : NULL
                ) . "
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:10px;width:30%;vertical-align:top;\" id=\"err6_1{$this->popup_id}\">Discount Type:</td>
					<td style=\"background-color:#ffffff;padding-top:10px;vertical-align:top;\">" .
    					( $this->discounting->discount_hash ?
    						( $this->discounting->current_discount['discount_type'] == 'C' ?
        						"Customer Discount" : "Standard Discount"
    						) . "
							<span >" .
								( $this->discounting->current_discount['discount_type'] == 'C' ?
									$this->form->hidden( array(
    									'discount_customer'        => 1,
	                         			'discount_customer_hash'   => $this->discounting->current_discount['customer_hash'],
	                         			'discount_type'            => 'C'
									) ) . ": "
									:
									$this->form->hidden( array(
    									'discount_type' => $this->discounting->current_discount['discount_type']
									) )
								) .
								stripslashes($this->discounting->current_discount['discount_customer']) . "
							</span>"
							:
							$this->form->select(
    							"discount_type",
    							array("Standard Discount", "Customer Discount"),
    							$this->discounting->current_discount['discount_type'],
    							array('S', 'C'),
    							"blank=1",
    							"onChange=if(this.options[this.selectedIndex].value=='C'){\$('discount_customer_holder{$this->popup_id}').style.display='block';\$('discount_standard_holder{$this->popup_id}').style.display='none';}else{\$('discount_customer_holder{$this->popup_id}').style.display='none';clear_values('discount_customer','discount_customer_hash');\$('discount_standard_holder{$this->popup_id}').style.display='block';}"
    						) . "
							<div id=\"discount_customer_holder{$this->popup_id}\" style=\"padding-top:5px;display:".($this->discounting->current_discount['discount_type'] == 'C' ? 'block' : 'none')."\">
								<div id=\"err6_2{$this->popup_id}\">Customer:</div>" .
								$this->form->text_box(
    								"name=discount_customer",
									"value=" . stripslashes($this->discounting->current_discount['discount_customer']),
									"autocomplete=off",
									"size=30",
									"onFocus=position_element($('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(\$F('discount_customer_hash')==''){clear_values('discount_customer');}key_call('proposals','discount_customer','discount_customer_hash',1);",
									"onBlur=vtobj.reset_keyResults();key_clear();if(\$F('discount_descr')==''){setTimeout('if(\$F(\'discount_customer_hash\')){fill_input(\'discount_descr=discount_customer\')}',700);}",
									"onKeyDown=clear_values('discount_customer_hash');"
								) .
								$this->form->hidden( array(
    								"discount_customer_hash" => $this->discounting->current_discount['discount_customer_hash']
								) ) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('customers','sf_loadcontent','show_popup_window','edit_customer','parent_win_open','parent_win={$this->popup_id}','popup_id=sub_win','jscript_action=javascript:void(0);');\"><img src=\"images/plus.gif\" border=\"0\" title=\"Create a new customer\"></a>
							</div>" .
							$this->form->hidden( array(
    							'discount_next_action' => 'showtable'
							) )
						) . "
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err6_3{$this->popup_id}\">Discount Description:</td>
					<td style=\"background-color:#ffffff;\">" .
						$this->form->text_box(
    						"name=discount_descr",
    						"value=" . stripslashes($this->discounting->current_discount['discount_descr']),
    						"size=25",
    						"maxlength=64"
						) . "
						<div style=\"padding-top:5px;\">" .
							$this->form->checkbox(
    							"name=discount_gsa",
    							"value=1",
    							( $this->discounting->current_discount['discount_gsa'] ? "checked" : NULL )
    						) . "
							GSA?
						</div>
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err6_4{$this->popup_id}\">Discount ID:</td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_box(
        					"name=discount_id",
        					"value={$this->discounting->current_discount['discount_id']}",
        					"size=10",
        					"maxlength=24"
    					) . "
    				</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err6_5{$this->popup_id}\">Effective Date:</td>
					<td style=\"background-color:#ffffff;\" id=\"discount_effective_date{$this->popup_id}\">";

					$this->content["jscript"][] = "
					setTimeout('DateInput(\'discount_effective_date\', true, \'YYYY-MM-DD\',\'".($this->discounting->current_discount['discount_effective_date'] ? $this->discounting->current_discount['discount_effective_date'] : date("Y-m-d"))."\',1,\'discount_effective_date{$this->popup_id}\')',30);";

					$tbl .= "
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err6_6{$this->popup_id}\">Expiration Date:</td>
					<td style=\"background-color:#ffffff;\" id=\"discount_expiration_date{$this->popup_id}\">";

					$this->content["jscript"][] = "
					setTimeout('DateInput(\'discount_expiration_date\', true, \'YYYY-MM-DD\',\'".($this->discounting->current_discount['discount_expiration_date'] ? $this->discounting->current_discount['discount_expiration_date'] : date("Y-m-d",time()+15552000))."\',1,\'discount_expiration_date{$this->popup_id}\')',30);";

					$tbl .= "
					</td>
				</tr>" .
				( $this->current_vendor['vendor_hash'] ?
					"<tr>
						<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
							<div style=\"float:right;\">" .
								( $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this),'E','discounting') ?
    								$this->form->hidden( array(
        								'discount_hash' => $this->discounting->current_discount['discount_hash']
    								) ) . "Update Discount"
    								:
    								( ! $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this),'A','discounting') ?
        								"Add Discount" : NULL
    								)
    							) . "
								&nbsp;&nbsp;" .
								( ( $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this),'E','discounting') ) || ( ! $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this),'A','discounting') ) ?
    								$this->form->button(
        								"value=+",
        								"onClick=submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddDiscountLine');"
        							) : NULL
        						) .
								( $this->discounting->current_discount['discount_hash'] && $this->p->ck(get_class($this),'D','discounting') ?
    								"&nbsp;&nbsp;Delete Discount&nbsp;&nbsp;" .
    								$this->form->button(
        								"value=X",
        								"onClick=if (confirm('Are you sure you want to delete this discount? This action CANNOT be undone!')){submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddDiscountLine','delete=true');}"
    								) : NULL
    							) . "
							</div>" .
    						( $this->current_vendor['vendor_hash'] ?
    							"<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->vendor_hash}','p=$p','order=$order','order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>" : NULL
    						) . "
						</td>
					</tr>" : NULL
                ) . "
			</table>";

		} elseif ( $this->vendor_hash && $this->discounting->discount_hash && $action == 'showtable' ) { # Table showing all product/item discounts

			$total = $this->discounting->total_discounts;

			$num_pages = ceil($this->discounting->total_discounts / SUB_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ($p - 1);
			$end = $start_from + SUB_PAGNATION_NUM;

			if ( $end > $this->discounting->total_discounts )
				$end = $this->discounting->total_discounts;

			$order_by_ops = array(
    			"product_name"	=>	"vendor_products.product_name",
			    "item_no"       =>  "discount_details.item_no",
                "obj_id"        =>  "discount_details.obj_id"
			);

			$_total = $this->discounting->fetch_discount_items(
    			$start_from,
    			SUB_PAGNATION_NUM,
    			$order_by_ops[ ( isset($order) && $order_by_ops[ $order ] ? $order : 'obj_id' ) ],
    			$order_dir
    		);

			$tbl = "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\">
				<tr>
					<td style=\"text-align:left;background-color:#efefef;color:#00477f;padding-top:10px;vertical-align:top;\">
						<div style=\"font-weight:normal;font-size:8pt\">
							<img src=\"images/folder.gif\" />&nbsp;
							<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','vendor_hash={$this->current_vendor['vendor_hash']}');\" style=\"color:#00477f;text-decoration:none;\">Vendor Discounting</a> >
							<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','vendor_hash={$this->current_vendor['vendor_hash']}','action=addnew','discount_hash={$this->discounting->discount_hash}');\" style=\"color:#00477f;text-decoration:none;\">" . stripslashes($this->discounting->current_discount['discount_descr']) . "</a>
						</div>
						<div style=\"padding-top:5px;\">
							<img src=\"images/tree_down.gif\" />&nbsp;
							<span style=\"margin-bottom:5px;margin-top:10px;font-weight:bold;\">Item & Product Discounts</span>
						</div>
					</td>
				</tr>
				<tr>
					<td style=\"padding:0px;\">
                        <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:700px;table-layout:fixed;\" >
                            <col style=\"width:200px\">
                            <col style=\"width:150px\">
                            <col style=\"width:200px\">
                            <col style=\"width:75px\">
                            <col style=\"width:75px\">
							<tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"5\">" .
	    							( $this->discounting->total_discounts ?
										"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" .
	        							paginate_jscript(
	            							$num_pages,
	            							$p,
	            							'sf_loadcontent',
	            							'cf_loadcontent',
	            							'vendors',
	            							'discount_info_form',
	            							$order,
	            							$order_dir,
	            							"'otf=1','vendor_hash={$this->current_vendor['vendor_hash']}','popup_id={$this->popup_id}','discount_hash={$this->discounting->discount_hash}','action=showtable'"
	            						) . "
	            						</div>
										Showing " . ( $start_from + 1 ) . " - " .
	            						( $start_from + SUB_PAGNATION_NUM > $this->discounting->total_discounts ?
	                						$this->discounting->total_discounts : $start_from + SUB_PAGNATION_NUM
	                					) . " of {$this->discounting->total_discounts} Product Discounts for " .
	                					( $this->discounting->current_discount['discount_type'] == 'C' ?
	                    					stripslashes($this->discounting->current_discount['discount_customer']) : stripslashes($this->discounting->current_discount['discount_descr'])
	                    				) . "." : NULL
	                    			) . "
									<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">" .
	                    			( $this->p->ck(get_class($this),'A','discounting') ?
										"[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','action=newprod_discount','discount_hash={$this->discounting->discount_hash}','vendor_hash={$this->current_vendor['vendor_hash']}');\" class=\"link_standard\">New Discount</a></small>]" : NULL
	                    			) . "
									</div>
								</td>
							</tr>
                            <tr class=\"thead\" style=\"font-weight:bold;\">
                                <td>
                                    <a href=\"javascript:void(0);\" onClick=\"agent.call('vendors', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'vendor_hash={$this->vendor_hash}', 'discount_hash={$this->discounting->discount_hash}', 'action=showtable', 'p=$p', 'order=product_name', 'order_dir=".($order == "product_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Product</a>" .
                                    ( $order == 'product_name' ?
                                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                    ) . "
                                </td>
                                <td>
                                    <a href=\"javascript:void(0);\" onClick=\"agent.call('vendors', 'sf_loadcontent', 'cf_loadcontent', 'discount_info_form', 'otf=1', 'popup_id={$this->popup_id}', 'vendor_hash={$this->vendor_hash}', 'discount_hash={$this->discounting->discount_hash}', 'action=showtable', 'p=$p', 'order=item_no', 'order_dir=".($order == "item_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Item/Code</a>" .
                                    ( $order == 'item_no' ?
                                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                    ) . "
                                </td>
                                <td style=\"text-align:right;padding-right:5px;\">Buy Disc</td>
                                <td style=\"text-align:right;\">List Disc</td>
                                <td style=\"text-align:right;\">Margin</td>
                            </tr>";

                            $style = "border-bottom:1px solid #cccccc;";
							for ( $i = 0; $i < $_total; $i++ ) {

                                if ( $i >= $_total - 1 )
                                    unset($style);

								$tbl .= "
								<tr " . ( $this->p->ck(get_class($this),'V','discounting') ? "onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','otf=1','popup_id={$this->popup_id}','action=newprod_discount','vendor_hash={$this->vendor_hash}','discount_hash={$this->discounting->discount_hash}','item_hash={$this->discounting->discount_items[$i]['item_hash']}','p=$p','order=$order','order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">" .
                                    ( $this->discounting->discount_items[$i]['product_hash'] == '*' ?
                                        "<td colspan=\"2\" " . ( $style ? "style=\"$style\"" : NULL ) . ">** All Products **</td>"
                                        :
                                        ( $this->discounting->discount_items[$i]['discount_code'] ?
                                            "<td style=\"$style\">[ DISCOUNT CODE ]</td>"
                                            :
                                            "<td " . ( $style ? "style=\"$style\"" : NULL ) . ">" .
                                            ( strlen($this->discounting->discount_items[$i]['product_name']) > 23 ?
                                                stripslashes( substr($this->discounting->discount_items[$i]['product_name'], 0, 20) ) . '...' : stripslashes($this->discounting->discount_items[$i]['product_name'])
                                            ) . "
                                            </td>"
                                        ) . "
	                                    <td style=\"$style\">" .
	                                    ( $this->discounting->discount_items[$i]['discount_code'] ?
	                                        $this->discounting->discount_items[$i]['discount_code'] : $this->discounting->discount_items[$i]['item_no']
	                                    ) . "&nbsp;
	                                    </td>"
                                    ) .
                                    ( $this->discounting->discount_items[$i]['discount_type'] == "T" ?
                                        "<td style=\"text-align:right;padding-right:5px;$style\" colspan=\"3\">[Tiered Discount]</td>"
                                        :
                                        "<td style=\"text-align:right;padding-right:5px;$style\">" .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount1'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount1'], 100, 4), 4) . "%&nbsp;" : "&nbsp;"
                                            ) .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount2'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount2'], 100, 4), 4) . "%&nbsp;" : NULL
                                            ) .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount3'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount3'], 100, 4), 4) . "%&nbsp;" : NULL
                                            ) .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount4'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount4'], 100, 4), 4) . "%&nbsp;" : NULL
                                            ) .
                                            ( bccomp($this->discounting->discount_items[$i]['buy_discount5'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['buy_discount5'], 100, 4), 4) . "%&nbsp;" : NULL
                                            ) . "
                                        </td>
                                        <td style=\"width:15%;text-align:right;$style\">" .
                                            ( bccomp($this->discounting->discount_items[$i]['sell_discount'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['sell_discount'], 100, 4), 4) . "%" : "&nbsp;"
                                            ) . "
                                        </td>
                                        <td style=\"width:15%;text-align:right;$style\">" .
                                            ( bccomp($this->discounting->discount_items[$i]['gp_margin'], 0, 4) == 1 ?
                                                round( bcmul($this->discounting->discount_items[$i]['gp_margin'], 100, 4), 2) . "%" : "&nbsp;"
                                            ) . "
                                        </td>"
                                    ) . "
                                </tr>";
							}

                            if ( ! $this->discounting->total_discounts )
                                $tbl .= "
                                <tr >
                                    <td colspan=\"5\" class=\"smallfont\">Discount has no associated product/item level discounts</td>
                                </tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";

		} elseif ( $this->vendor_hash && $action == 'newprod_discount') { # Create a new PRODUCT discount for a specific discount record under a vendor

			if ( ! $this->discounting->item_hash ) { # Repeat the last user action

				$new_action = 1;

				$r = $this->db->query("SELECT
                            			   t1.item_no,
                            			   t1.discount_code
				                       FROM discount_details t1
				                       LEFT JOIN discounting t2 ON t2.discount_hash = t1.discount_hash
				                       WHERE t2.vendor_hash = '{$this->vendor_hash}'
				                       ORDER BY t1.obj_id DESC
				                       LIMIT 1");
				if ( $this->db->result($r, 0, 'item_no') )
                    $new_action = 2;
				elseif ( $this->db->result($r, 0, 'discount_code') )
					$new_action = 3;
			}

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" colspan=\"2\" style=\"text-align:left;background-color:#efefef;color:#00477f;padding-top:10px;vertical-align:top;\">
						<div style=\"font-weight:normal;font-size:8pt\">
							<img src=\"images/folder.gif\" />
							&nbsp;
							<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','vendor_hash={$this->current_vendor['vendor_hash']}');\" style=\"color:#00477f;text-decoration:none;\">Vendor Discounting</a> >
							<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','vendor_hash={$this->current_vendor['vendor_hash']}','action=addnew','discount_hash={$this->discounting->discount_hash}');\" style=\"color:#00477f;text-decoration:none;\">".$this->discounting->current_discount['discount_descr']."</a> >
							<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','vendor_hash={$this->current_vendor['vendor_hash']}','action=showtable','discount_hash={$this->discounting->discount_hash}','p=$p','order=$order','order_dir=$order_dir');\" style=\"color:#00477f;text-decoration:none;\">".($this->discounting->current_discount['discount_type'] == 'C' ? "Customer" : "Standard")." Discount Table</a>
						</div>
						<div style=\"padding-top:5px;\">
							<img src=\"images/tree_down.gif\" />
							&nbsp;
							<span style=\"margin-bottom:5px;margin-top:10px;font-weight:bold;\">" .
                            ( $this->discounting->item_hash ?
        						"Edit" : "Create New"
                            ) . " Item/Product Discount" .
                            ( $this->discounting->item_hash ?
                                " : " .
                                ( $this->discounting->current_item['product_hash'] == '*' ?
                					"** All Products **" : stripslashes($this->discounting->current_item['product_name'])
                                ) : NULL
                            ) . "
							</span>
						</div>
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"background-color:#ffffff;padding-top:10px;padding-left:45px;vertical-align:top;\" >" .
	                    ( ! $this->discounting->item_hash ? "
	                        <div id=\"disc_type_selector\">What type of discount are you creating?</div>
                            <div style=\"margin-top:8px;margin-bottom:10px;\">
                                <table cellpadding=\"2\" cellspacing=\"1\" style=\"background-color:#cccccc;\">
                                    <tr>
                                        <td id=\"disc_type_op_1\" style=\"background-color:#efefef;\">" .
                                            $this->form->radio(
                                                "name=disc_type",
                                                "id=disc_type_radio1",
                                                "value=1",
                                                ( ! $products_out && in_array('*', $discount_items_exists) ? "disabled title=There are no remaining products available for discounting. You may add additional products under Vendors->Products." : NULL ),
                                                "onClick=if(this.checked){toggle_display('discount_1_content{$this->popup_id}','block');toggle_display('discount_2_content{$this->popup_id}','none');toggle_display('discount_3_content{$this->popup_id}','none');}",
                                                ( $new_action == 1 && ( ! $discount_items_exists || ! in_array('*', $discount_items_exists) ) ? "checked" : NULL ),
                                                "style=border:0;padding:3px;"
                                            ) . "
                                        </td>
                                        <td id=\"disc_td_op_1\" style=\"background-color:#efefef;\">" .
                                            ( ! $products_out && in_array('*', $discount_items_exists) ?
                                                "<span style=\"color:8f8f8f;\" title=\"There are no remaining products available for discounting. You may add additional products under Vendors->Products.\">&nbsp;&nbsp;A discount to be applied to an entire product line</span>"
                                                :
                                                "<a href=\"javascript:void(0);\" onClick=\"if(\$('disc_type_radio1')){\$('disc_type_radio1').checked=1;toggle_display('discount_1_content{$this->popup_id}','block');toggle_display('discount_2_content{$this->popup_id}','none');toggle_display('discount_3_content{$this->popup_id}','none');}\" style=\"text-decoration:none;color:#000000;\" onMouseOver=\"\$('disc_type_op_1').setStyle({backgroundColor:'#c1d2ee'});\$('disc_td_op_1').setStyle({backgroundColor:'#c1d2ee'});\" onMouseOut=\"\$('disc_type_op_1').setStyle({backgroundColor:'#efefef'});\$('disc_td_op_1').setStyle({backgroundColor:'#efefef'});\">&nbsp;&nbsp;A discount to be applied to an entire product line</a>"
                                            ) . "
                                        </td>
                                    </tr>
                                    <tr>
                                        <td id=\"disc_type_op_2\" style=\"background-color:#efefef;\">" .
                                            $this->form->radio(
                                                "name=disc_type",
                                                "id=disc_type_radio2",
                                                "value=2",
                                                ( $new_action == 2 || ( ! $products_out && in_array('*', $discount_items_exists) ) ? "checked" : NULL ),
                                                "onClick=if(this.checked){toggle_display('discount_2_content{$this->popup_id}','block');toggle_display('discount_1_content{$this->popup_id}','none');toggle_display('discount_3_content{$this->popup_id}','none');}",
                                                "style=border:0;padding:3px;"
                                            ) . "
                                        </td>
                                        <td id=\"disc_td_op_2\" style=\"background-color:#efefef;\">
                                            <a href=\"javascript:void(0);\" onClick=\"if(\$('disc_type_radio2')){\$('disc_type_radio2').checked=1;toggle_display('discount_2_content{$this->popup_id}','block');toggle_display('discount_1_content{$this->popup_id}','none');toggle_display('discount_3_content{$this->popup_id}','none');}\" style=\"text-decoration:none;color:#000000;\" onMouseOver=\"\$('disc_type_op_2').setStyle({backgroundColor:'#c1d2ee'});\$('disc_td_op_2').setStyle({backgroundColor:'#c1d2ee'});\" onMouseOut=\"\$('disc_type_op_2').setStyle({backgroundColor:'#efefef'});\$('disc_td_op_2').setStyle({backgroundColor:'#efefef'});\">&nbsp;&nbsp;A discount to be applied only on specific items within a product line</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td id=\"disc_type_op_3\" style=\"background-color:#efefef;\">" .
                                            $this->form->radio(
                                                "name=disc_type",
                                                "id=disc_type_radio3",
                                                "value=3",
                                                ( $new_action == 3 ? "checked" : NULL ),
                                                "onClick=if(this.checked){toggle_display('discount_3_content{$this->popup_id}','block');toggle_display('discount_1_content{$this->popup_id}','none');toggle_display('discount_2_content{$this->popup_id}','none');}",
                                                "style=border:0;padding:3px;"
                                            ) . "
                                        </td>
                                        <td id=\"disc_td_op_3\" style=\"background-color:#efefef;\">
                                            <a href=\"javascript:void(0);\" onClick=\"if(\$('disc_type_radio3')){\$('disc_type_radio3').checked=1;toggle_display('discount_3_content{$this->popup_id}','block');toggle_display('discount_1_content{$this->popup_id}','none');toggle_display('discount_2_content{$this->popup_id}','none');}\" style=\"text-decoration:none;color:#000000;\" onMouseOver=\"\$('disc_type_op_3').setStyle({backgroundColor:'#c1d2ee'});\$('disc_td_op_3').setStyle({backgroundColor:'#c1d2ee'});\" onMouseOut=\"\$('disc_type_op_3').setStyle({backgroundColor:'#efefef'});\$('disc_td_op_3').setStyle({backgroundColor:'#efefef'});\">&nbsp;&nbsp;A discount to be applied based on item discount code</a>
                                        </td>
                                    </tr>
                                </table>
                            </div>"
	                        :
                            $this->form->hidden( array(
                                'disc_type' =>
                                ( $this->discounting->current_item['item_no'] ?
                                    2 :
                                    ( $this->discounting->current_item['discount_code'] ?
                                        3 : 1
                                    )
                                )
                            ) )
                        ) . "
						<div " . ( ! $this->discounting->item_hash  ? "style=\"margin-top:10px;\"" : NULL ) . " id=\"err6_7{$this->popup_id}\">
                            <div id=\"discount_1_content{$this->popup_id}\" style=\"display:" . ( ( ! $this->discounting->item_hash && $new_action == 1 && ( ! is_array($discount_items_exists) || ! in_array('*', $discount_items_exists) ) ) || ( $this->discounting->item_hash && ! $this->discounting->current_item['item_no'] && ! $this->discounting->current_item['discount_code'] ) ? "block" : "none").";padding-top:10px;\">
                                <div style=\"padding-top:5px;\" id=\"product_listing_label{$this->popup_id}\">" .
                                ( $this->discounting->item_hash ?
                                    "Viewing discount for: " : "Which product(s) should be used for this discount?"
                                ) . "
                                </div>
								<div style=\"padding-top:5px;padding-bottom:10px;\" id=\"product_listing{$this->popup_id}\">" .
    								( $this->discounting->item_hash ?
    									"<strong>" .
        								( $this->discounting->current_item['product_hash'] == '*' ?
        									"** All Products **" : stripslashes($this->discounting->current_item['product_name'])
        								) . "
        								</strong>"
        								:
    									$this->form->select(
        									"discount_product_hash[]",
                    						( $products_out ?
                            					( ! in_array('*', $discount_items_exists) ?
                                					array_merge( array("** APPLY ON ALL PRODUCTS **"), $products_out) : $products_out
                                				)
                                				:
                                				( ! $discount_items_exists || ! in_array('*',$discount_items_exists) ?
                                					array("** APPLY ON ALL PRODUCTS **") : NULL
                                				)
                                			),
                                            $this->discounting->current_item['product_hash'],
                                            ( $products_in ?
                                                ( ! in_array('*',$discount_items_exists) ?
                                                    array_merge( array("*"), $products_in) : $products_in
                                                )
                                                :
                                                ( ! $discount_items_exists || ! in_array('*',$discount_items_exists) ?
                                                    array("*") : NULL
                                                )
                                            ),
                                            "multiple",
                                            "size=5",
                                            "style=width:225px;",
                                            "blank=1",
                                            "onClick=if(this.options[this.options.length-1].selected){for(var i=this.options.length-2;i>=0;i--){if(this.options[i].selected){this.options[i].selected=0;}}}"
                                        )
                                    ) . "
								</div>
                            </div>
							<div id=\"discount_2_content{$this->popup_id}\" style=\"display:" . ( ( ! $this->discounting->item_hash && $new_action == 2 ) || ( ! $this->discounting->item_hash && ( ! is_array($products_out) && is_array($discount_items_exists) && in_array('*', $discount_items_exists) ) ) || ( $this->discounting->item_hash && $this->discounting->current_item['item_no'] ) ? "block" : "none" ) . ";padding-top:10px;\">
                                <div style=\"padding-top:5px;\" id=\"item_discount_label{$this->popup_id}\">" .
                                ( $this->discounting->item_hash ?
                                    "Item discount for product line:" : "Apply a discount to specific items within the product line:"
                                ) . "
                                </div>
                                <div style=\"padding-top:5px;margin-bottom:10px;\" id=\"item_discount{$this->popup_id}\">" .
                                ( $this->discounting->item_hash ?
                                    "<strong>" .
                                    ( $this->discounting->current_item['product_hash'] == '*' ?
                                        "** All Products **" : stripslashes($this->discounting->current_item['product_name'])
                                    ) . "
                                    </strong>"
                                    :
                                    $this->form->select(
                                        "discount_product_hash_2[]",
                                        $products_out1,
                                        $this->discounting->current_item['product_hash'],
                                        $products_in1,
                                        "blank=1"
                                    )
                                ) . "
                                </div>
                                <div id=\"item_no_label\">" .
                                ( $this->discounting->item_hash ?
                                    "Item Number: *" : "Which item numbers should be included in this discount? *"
                                ) . "
                                </div>
                                <div style=\"padding-bottom:10px;padding-top:5px;\" id=\"item_listing{$this->popup_id}\">" .
                                ( $this->discounting->item_hash ?
                                    $this->form->text_box(
                                        "name=discount_item_no",
                                        "value={$this->discounting->current_item['item_no']}",
                                        "maxlength=64"
                                    )
                                    :
                                    $this->form->text_area(
                                        "name=discount_item_no",
										"value=Enter each item number on a new line...",
										"onFocus=if(this.value=='Enter each item number on a new line...'){this.value='';}",
										"rows=4",
										"cols=55"
                                    )
                                ) . "
                                </div>
							</div>
							<div id=\"discount_3_content{$this->popup_id}\" style=\"display:" . ( ( ! $this->discounting->item_hash && $new_action == 3 ) || $this->discounting->current_item['discount_code'] ? "block" : "none" ) . ";padding-bottom:10px;padding-top:5px;\">
                                <div style=\"padding-top:5px;\" id=\"discount_code_label{$this->popup_id}\">" .
                                ( $this->discounting->item_hash ?
                                    "Discount Code: *" : "Which discount codes should be included in this discount? *"
                                ) . "
                                </div>
								<div id=\"discount_code{$this->popup_id}\">" .
    								( $this->discounting->item_hash ?
										$this->form->text_box(
	    									"name=discount_code",
											"value={$this->discounting->current_item['discount_code']}",
											"maxlength=12"
										)
										:
										$this->form->text_area(
	                                        "name=discount_code",
	                                        "value=Enter each discount code on a new line...",
	                                        "onFocus=if(this.value=='Enter each discount code on a new line...'){this.value='';}",
	                                        "rows=4",
	                                        "cols=55"
										)
									) . "
								</div>
								<div style=\"margin-top:5px;\" id=\"discount_code_descr{$this->popup_id}\">
									Discount Description:
								</div>
								<div>" .
									$this->form->text_box(
    									"name=discount_descr",
										"value=" . stripslashes($this->discounting->current_item['discount_descr']),
										"maxlength=64",
										"style=width:225px;"
									) . "
								</div>
							</div>
						</div>
						<div " . ( ! $this->discounting->item_hash  ? "style=\"margin-top:5px;\"" : NULL ) . " id=\"err6_8\">" .
                            ($this->discounting->item_hash ? "
    							Discounting Method:" : "Where the discounting method is: "
                            ) . "
						</div>
						<div style=\"padding-top:5px;\">" .
							$this->form->select(
    							'discount_type',
	                            array("Non-Tiered or Multi-Level Discount", "Tiered Discount by List Price"),
	                            $this->discounting->current_item['discount_type'],
	                            array('F', 'T'),
	                            "onChange=toggle_display('tiered_table{$this->popup_id}',this.options[this.selectedIndex].value=='T'?'block':'none');toggle_display('fixed_table{$this->popup_id}',this.options[this.selectedIndex].value=='T'?'none':'block');",
	                            "blank=1"
	                        ) . "
						</div>
						<div id=\"tiered_table{$this->popup_id}\" style=\"display:" . ( $this->discounting->current_item['discount_type'] == 'T' ? "block" : "none" ) . ";margin-top:10px;padding:10px 0;\">
							<table style=\"width:95%;\" cellpadding=\"5\">
								<tr>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 1:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_12{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ 0.00
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_13{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier1_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier1_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier1_to'], 2) : NULL ),
                                                        'size=13',
                                                        'maxlength=13',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier2_from').innerHTML='$ '+formatCurrency(\$F('tier1_to'), .01);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_14{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier1_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier1_buy_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier1_buy_discount'], 100, 4): NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_15{$this->popup_id}\">% Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier1_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier1_sell_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier1_sell_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 2:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_16{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier2_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier2_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier2_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_17{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier2_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier2_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier2_to'], 2) : NULL ),
                                                        'size=13',
                                                        'maxlength=13',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier3_from').innerHTML='$ '+formatCurrency(\$F('tier2_to'), .01);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_18{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier2_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier2_buy_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier2_buy_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_19{$this->popup_id}\">List Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier2_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier2_sell_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier2_sell_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 3:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_20{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier3_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier3_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier3_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_21{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier3_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier3_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier3_to'], 2) : NULL ),
                                                        'size=13',
                                                        'maxlength=13',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier4_from').innerHTML='$ '+formatCurrency(\$F('tier3_to'), .01);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_22{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier3_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier3_buy_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier3_buy_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_23{$this->popup_id}\">Discount Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier3_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier3_sell_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier3_sell_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 4:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_24{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier4_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier4_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier4_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_25{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier4_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier4_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier4_to'], 2) : NULL ),
                                                        'size=13',
                                                        'maxlength=13',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier5_from').innerHTML='$ '+formatCurrency(\$F('tier4_to'), .01);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_26{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier4_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier4_buy_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier4_buy_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_27{$this->popup_id}\">Discount Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier4_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier4_sell_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier4_sell_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
								</tr>
								<tr>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 5:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_50{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier5_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier5_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier5_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_51{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier5_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier5_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier5_to'], 2) : NULL ),
                                                        'size=13',
                                                        'maxlength=13',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);\$('tier6_from').innerHTML='$ '+formatCurrency(\$F('tier5_to'), .01);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_52{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier5_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier5_buy_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier5_buy_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_53{$this->popup_id}\">Discount Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier5_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier5_sell_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier5_sell_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
									<td style=\"background-color:#cccccc;border:1px solid #00477f;\">
                                        <table >
                                            <tr>
                                                <td colspan=\"2\" style=\"font-weight:bold;\">Tier 6:</td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_54{$this->popup_id}\">From: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    <div id=\"tier6_from\">" .
                                                    ( bccomp($this->discounting->current_item['tier6_from'], 0, 2) == 1 ?
                                                        "\$ " . number_format($this->discounting->current_item['tier6_from'], 2) : NULL
                                                    ) . "
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_55{$this->popup_id}\">To: </td>
                                                <td style=\"vertical-align:bottom;padding-left:5px;\">
                                                    \$ " .
                                                    $this->form->text_box(
                                                        'name=tier6_to',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier6_to'], 0, 2) == 1 ? number_format($this->discounting->current_item['tier6_to'], 2) : NULL ),
                                                        'size=13',
                                                        'maxlength=13',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onBlur=this.value=formatCurrency(this.value);"
                                                    ) . "
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_56{$this->popup_id}\">Buy Discount: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier6_buy_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier6_buy_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier6_buy_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"text-align:right;\" id=\"err6_57{$this->popup_id}\">Discount Off List: </td>
                                                <td style=\"vertical-align:bottom;padding-left:17px;\">" .
                                                    $this->form->text_box(
                                                        'name=tier6_sell_discount',
                                                        'value=' . ( bccomp($this->discounting->current_item['tier6_sell_discount'], 0, 4) == 1 ? (float)bcmul($this->discounting->current_item['tier6_sell_discount'], 100, 4) : NULL ),
                                                        'size=7',
                                                        'maxlength=6',
                                                        'style=text-align:right;',
                                                        'onFocus=this.select();',
                                                        "onChange=validate_decimal(this);"
                                                    ) . "&nbsp;%
                                                </td>
                                            </tr>
                                        </table>
									</td>
								</tr>
							</table>
						</div>
						<div style=\"display:".($this->discounting->current_item['discount_type'] == 'F' || !$this->discounting->item_hash ? "block" : "none").";margin-top:10px;margin-left:10px;padding:10px;\" id=\"fixed_table{$this->popup_id}\">
							<table >
								<tr>
									<td style=\"text-align:right;width:150px;\" id=\"err6_9{$this->popup_id}\">Buy Discount: </td>
									<td >" .
                                        $this->form->text_box(
                                            'name=buy_discount1',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount1'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount1'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
                                            "onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;" .
                                        $this->form->text_box(
                                            'name=buy_discount2',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount2'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount2'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
                                            "onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;" .
                                        $this->form->text_box(
                                            'name=buy_discount3',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount3'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount3'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
                                            "onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;" .
                                        $this->form->text_box(
                                            'name=buy_discount4',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount4'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount4'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
                                            "onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;" .
                                        $this->form->text_box(
                                            'name=buy_discount5',
                                            'value=' . ( bccomp($this->discounting->current_item['buy_discount5'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['buy_discount5'], 100, 4), 4) : NULL ),
                                            'size=5',
                                            'maxlength=6',
                                            'style=text-align:right;',
                                            "onChange=validate_decimal(this);",
                                            "onFocus=this.select();"
                                        ) . "&nbsp;%&nbsp;
									</td>
								</tr>
								<tr>
									<td style=\"text-align:right;width:150px;\" id=\"err6_10{$this->popup_id}\">GP Margin: </td>
									<td>" .
										$this->form->hidden( array(
    										'tmp_gp_margin'       => '',
    										'tmp_list_discount'   => ''
										) ) .
                                        $this->form->text_box(
                                            "name=gp_margin",
                                            "value=" . ( bccomp($this->discounting->current_item['gp_margin'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['gp_margin'], 100, 4), 4) : NULL ),
                                            "maxlength=6",
                                            "size=5",
                                            "style=text-align:right;",
                                            "onFocus=\$('tmp_gp_margin').value=this.value;this.select();",
                                            "onKeyDown=if(\$F('tmp_gp_margin')!=this.value){clear_values('sell_discount')}",
                                            "onChange=validate_decimal(this);"
                                        ) . "&nbsp;%
                                        &nbsp;<strong>OR</strong>&nbsp;&nbsp;" .
                                        $this->form->text_box(
                                            "name=sell_discount",
                                            "value=" . ( bccomp($this->discounting->current_item['sell_discount'], 0, 4) == 1 ? round( bcmul($this->discounting->current_item['sell_discount'], 100, 4), 4) : NULL ),
                                            "size=5",
                                            "maxlength=6",
                                            "style=text-align:right;",
                                            "onFocus=\$('tmp_list_discount').value=this.value;this.select();",
                                            "onKeyDown=if(\$F('tmp_list_discount')!=this.value){clear_values('gp_margin')}",
                                            "onChange=validate_decimal(this);"
                                        ) . "&nbsp;%
                                        <span id=\"err6_10a{$this->popup_id}\">Discount Off List</span>
									</td>
								</tr>
							</table>
						</div>
						<div style=\"margin-top:10px;padding:10px 0;\">
							If this discount has custom freight terms, enter them here:
							<div style=\"padding-top:8px;margin-bottom:10px;\">
								<span id=\"err6_40{$this->popup_id}\">If under $</span>&nbsp;" .
								$this->form->text_box(
    								"name=discount_frf_amount",
									"value=" . ( bccomp($this->discounting->current_item['discount_frf_amount'], 0, 2) == 1 ? number_format($this->discounting->current_item['discount_frf_amount'], 2) : NULL ),
									"size=9",
									"style=text-align:right;",
									"onBlur=this.value=formatCurrency(this.value);",
								    "onFocus=this.select();"
								) . "
								&nbsp;" .
								$this->form->select(
    								"discount_frf_amount_type",
    								array("List", "Net"),
    								$this->discounting->current_item['discount_frf_amount_type'],
    								array("L", "N")
    							) . "
								&nbsp;<span id=\"err6_41{$this->popup_id}\">then add </span>" .
								$this->form->text_box(
    								"name=discount_frf_amount_add",
									"value=" .
                                    ( $this->discounting->current_item['discount_frf_amount_add_type'] == 'P' ?
                                        round( bcmul($this->discounting->current_item['discount_frf_amount_add'], 100, 4), 4) :
                                        ( bccomp($this->discounting->current_item['discount_frf_amount_add'], 0, 2) == 1 ?
                                            number_format($this->discounting->current_item['discount_frf_amount_add'], 2) : NULL
                                        )
                                    ),
									"size=6",
									"style=text-align:right;",
                                    "onFocus=this.select();"
								) . "
								&nbsp;" .
								$this->form->select(
    								"discount_frf_amount_add_type",
    								array("dollars", "%"),
    								$this->discounting->current_item['discount_frf_amount_add_type'],
    								array("D", "P"),
    								"onChange=if(this.options[this.selectedIndex].value=='P'){\$('discount_freight_amount_add_type_of_holder{$this->popup_id}').style.visibility='visible';}else{\$('discount_freight_amount_add_type_of_holder{$this->popup_id}').style.visibility='hidden';}"
    							) . "
								<span id=\"discount_freight_amount_add_type_of_holder{$this->popup_id}\" style=\"visibility:" . ( $this->discounting->current_item['discount_frf_amount_add_type'] == 'P' ? "visible" : "hidden" ) . ";\">
									<span id=\"err6_42{$this->popup_id}\">of</span> " .
									$this->form->select(
    									"discount_frf_amount_add_type_of",
                                        array("List", "Net"),
                                        $this->discounting->current_item['discount_frf_amount_add_type_of'],
                                        array("L", "N")
                                    ) . "
								</span>
								<table>
									<tr>
										<td style=\"padding-left:10px;padding-top:5px;\" id=\"discount_frf_effective_date_holder{$this->popup_id}\"></td>
										<td style=\"padding-top:5px;\" id=\"discount_frf_expire_date_holder{$this->popup_id}\"></td>
									</tr>
								</table>";

								$this->content["jscript"][] = "
								setTimeout('DateInput(\'discount_frf_effective_date\', " . ( $this->discounting->current_item['discount_frf_effective_date'] ? 'true' : 'false' ) . ", \'YYYY-MM-DD\',\'".($this->discounting->current_item['discount_frf_effective_date'] ? $this->discounting->current_item['discount_frf_effective_date'] : NULL)."\',1,\'discount_frf_effective_date_holder{$this->popup_id}\',\'<span id=\"err6_44{$this->popup_id}\">Effective:</span>&nbsp;\')',22);
								setTimeout('DateInput(\'discount_frf_expire_date\', " . ( $this->discounting->current_item['discount_frf_expire_date'] ? 'true' : 'false' ) . ", \'YYYY-MM-DD\',\'".($this->discounting->current_item['discount_frf_expire_date'] ? $this->discounting->current_item['discount_frf_expire_date'] : NULL)."\',1,\'discount_frf_expire_date_holder{$this->popup_id}\',\'<span id=\"err6_45{$this->popup_id}\">Thru:</span>&nbsp;\')',23);";

							$tbl .= "
							</div>
							Are the freight terms on this discount quoted?
							<div style=\"padding-top:5px;margin-left:15px;\">" .
								$this->form->checkbox(
    								"name=discount_frf_quoted",
    								"value=1",
    								( $this->discounting->current_item['discount_frf_quoted'] ? "checked" : NULL )
        						) . "
								Quoted?
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
						<div style=\"float:right;\">" .
							$this->form->hidden( array(
    							'discount_hash' => $this->discounting->discount_hash
							) ) .
							( $this->discounting->item_hash && $this->p->ck(get_class($this),'E','discounting') ?
    							$this->form->hidden( array(
        							'item_hash' => $this->discounting->item_hash
        						) ) . "Update Discount" :
        						( ! $this->discounting->item_hash && $this->p->ck(get_class($this),'A','discounting') ?
            						"Add Product Discount" : NULL
        						) ) . "
							&nbsp;&nbsp;" .
							( ( $this->discounting->item_hash && $this->p->ck(get_class($this),'E','discounting') ) || ( ! $this->discounting->item_hash && $this->p->ck(get_class($this),'A','discounting') ) ?
    							$this->form->button(
        							"value=+",
        							"onClick=submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddDiscountItem');"
    							) : NULL
    						) .
							( $this->discounting->item_hash && $this->p->ck(get_class($this),'D','discounting') ?
    							"&nbsp;&nbsp;Delete Discount&nbsp;&nbsp;" .
    							$this->form->button(
        							"value=X",
        							"onClick=if (confirm('Are you sure you want to delete this product discount? This action CANNOT be undone!')){submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddDiscountItem','delete=true');}"
    							) : NULL
    						) . "
						</div>
						<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','vendor_hash={$this->current_vendor['vendor_hash']}','action=showtable','discount_hash={$this->discounting->discount_hash}','p=$p','order=$order','order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>
					</td>
				</tr>
			</table>";

            $this->content['jscript'][] = "
            validate_decimal = function() {

                var el = arguments[0];
                var elval = parseFloat( \$F(el) ).toFixed(4);

                if ( elval > 100 )
                    \$(el).value = '100';
                if ( elval < 0 )
                    \$(el).value = '';

            }";

		} elseif ( $this->vendor_hash ) { # Show all the discount records listed under a specific vendor

            $p = $this->ajax_vars['p'];
            $order = $this->ajax_vars['order'];
            $order_dir = $this->ajax_vars['order_dir'];

            $total = $this->discounting->total;
            $num_pages = ceil($this->discounting->total / SUB_PAGNATION_NUM);

            $p = ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p;

            $start_from = SUB_PAGNATION_NUM * ($p - 1);
            $end = $start_from + SUB_PAGNATION_NUM;
            if ( $end > $this->discounting->total )
                $end = $this->discounting->total;

			$order_by_ops = array(
    			"discount_descr"				=>	"discounting.discount_descr",
				"discount_id"					=>	"discounting.discount_id",
				"discount_expiration_date"		=>	"discounting.discount_expiration_date",
                "customer"                      =>  "customers.customer_name"
			);

			$_total = $this->discounting->fetch_discounts(
    			$start_from,
    			$order_by_ops[ ( isset($order) && $order_by_ops[$order] ? $order : 'discount_descr' ) ],
    			$order_dir
    		);

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;margin-top:0;\">
				<tr>
					<td style=\"padding:0px;\">
                        <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:700px;table-layout:fixed\" >
    						<col style=\"width:250px;\">
    						<col style=\"width:125px;\">
    						<col style=\"width:215px;\">
    						<col style=\"width:110px;\">
                            <tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\">" .
	                    			( $this->discounting->total ?
										"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" . paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'vendors', 'discount_info_form', $order, $order_dir, "'otf=1', 'vendor_hash={$this->vendor_hash}', 'popup_id={$this->popup_id}'") . "</div>
										Showing " . ( $start_from + 1 ) . " - " .
	                        			( $start_from + SUB_PAGNATION_NUM > $this->discounting->total ?
	                            			$this->discounting->total : $start_from + SUB_PAGNATION_NUM
	                            		) . " of {$this->discounting->total} Discount Records for " . stripslashes($this->current_vendor['vendor_name']) : NULL
	                            	) . "
									<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">" .
    	                            	( $this->p->ck(get_class($this),'A','discounting') ?
    										"[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','popup_id={$this->popup_id}','otf=1','action=addnew','vendor_hash={$this->vendor_hash}');\" class=\"link_standard\">Add New Discount</a></small>]" : NULL
    	                            	) . "
									</div>
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=discount_descr','order_dir=".($order == "discount_descr" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Discount Description</a>" .
									( $order == 'discount_descr' ?
										"&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
									) . "
								</td>
								<td>
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=discount_id','order_dir=".($order == "discount_id" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Discount ID</a>" .
									( $order == 'discount_id' ?
										"&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
									) . "
								</td>
								<td>
    								<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=customer','order_dir=".($order == "customer" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Customer</a>" .
                                    ( $order == 'customer' ?
                                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                    ) . "
								</td>
								<td style=\"text-align:right;padding-right:8px;\">" .
                                    ( $order == 'discount_expiration_date' ?
                                        "<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">&nbsp;" : NULL
                                    ) . "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=discount_expiration_date','order_dir=".($order == "discount_expiration_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Expiry Date</a>
								</td>
							</tr>";

							$border = "border-bottom:1px solid #cccccc;";
							for ( $i = 0; $i < $_total; $i++ ) {

                                if ( $i >= $_total - 1 )
                                    unset($border);

								$tbl .= "
								<tr " . ( $this->p->ck(get_class($this),'V','discounting') ? "onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','discount_info_form','otf=1','popup_id={$this->popup_id}','action=addnew','vendor_hash={$this->vendor_hash}','discount_hash={$this->discounting->discounts[$i]['discount_hash']}','p=$p','order=$order','order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
                                    <td " . ( $this->discounting->discounts[$i]['discount_default'] ? "style=\"font-weight:bold;font-style:italic;$border\" title=\"This is the default standard discount\"" : ( $border ? "style=\"$border\"" : NULL ) ) . " nowrap>" .
                                    ( strlen($this->discounting->discounts[$i]['discount_descr']) > 25 ?
                                        substr($this->discounting->discounts[$i]['discount_descr'], 0, 25) . "..." : $this->discounting->discounts[$i]['discount_descr']
                                    ) . "
                                    </td>
									<td class=\"smallfont\" " . ( $border ? "style=\"$border\"" : NULL ) . ">" . $this->discounting->discounts[$i]['discount_id'] . "&nbsp;</td>
                                    <td " . ( $border || $this->discounting->discounts[$i]['discount_type'] == 'S' ? "style=\"$border" . ( $this->discounting->discounts[$i]['discount_type'] == 'S' ? "font-style:italic;" : NULL ) . "\"" : NULL ) . ">
                                        " . stripslashes($this->discounting->discounts[$i]['customer_name']) . "
                                    </td>
									<td class=\"smallfont\" style=\"text-align:right;padding-right:8px;$border" .
                                        ( strtotime($this->discounting->discounts[$i]['discount_expiration_date']) < strtotime(date("Y-m-d") ) ?
    										"color:#ff0000" :
                                            ( strtotime($this->discounting->discounts[$i]['discount_expiration_date']) - 2592000 <= strtotime( date("Y-m-d") ) ?
    											"font-weight:bold;color:#FFD65C" : "color:#000000"
                                            )
                                        ) .
                                    "\">" . date('m-d-Y', strtotime($this->discounting->discounts[$i]['discount_expiration_date'])) . "</td>
								</tr>";

                                unset($style);
							}

							if ( ! $this->discounting->total )
								$tbl .= "
								<tr>
									<td class=\"smallfont\" colspan=\"3\">No discounts have been created under this vendor.</td>
								</tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";
		}

		if ( $this->ajax_vars['otf'] )
			$this->content['html']["tcontent6{$this->popup_id}"] = $tbl;
		else
			return $tbl;
	}

	function contact_info_form() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ( $this->ajax_vars['popup_id'] )
			$this->popup_id = $this->ajax_vars['popup_id'];

        if ( $this->ajax_vars['vendor_hash'] && ! $this->vendor_hash ) {

            if ( ! $this->fetch_master_record($this->ajax_vars['vendor_hash']) ) {

                return $this->__trigger_error("A system error was encountered when trying to lookup vendor contact for database update. Please re-load vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
            }
        }

		$action = $this->ajax_vars['action'];

		if ( $this->ajax_vars['contact_hash'] ) {

			if ( ! $this->fetch_contact_record($this->ajax_vars['contact_hash'], 1) ) {

                return $this->__trigger_error("A system error was encountered when trying to lookup vendor contact for database update. Please re-load vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
			}
		}

		if ( ! $this->current_vendor['vendor_hash'] || $action == 'addnew' || $this->ajax_vars['parent_win_open'] == 'add_from_proposal') {

            $this->cust->fetch_class_fields('contact');

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:15px;\" id=\"err3_1{$this->popup_id}\">Contact Name: *</td>
					<td style=\"background-color:#ffffff;padding-top:15px;\">".$this->form->text_box("name=contact_name","value=".$this->current_contact['contact_name'],"size=35","maxlength=255")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_2{$this->popup_id}\">Title:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_title","value=".$this->current_contact['contact_title'],"size=20","maxlength=128")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_3{$this->popup_id}\">Phone:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_phone","value=".$this->current_contact['contact_phone1'],"size=18","maxlength=32")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_4{$this->popup_id}\">Phone 2:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_phone2","value=".$this->current_contact['contact_phone2'],"size=18","maxlength=32")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_5{$this->popup_id}\">Mobile:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_mobile","value=".$this->current_contact['contact_mobile'],"size=18","maxlength=32")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_6{$this->popup_id}\">Fax:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_fax","value=".$this->current_contact['contact_fax'],"size=18","maxlength=32")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_7{$this->popup_id}\">Email:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_email","value=".$this->current_contact['contact_email'],"size=25","maxlength=128")."</td>
				</tr>";
                if ($this->cust->class_fields['contact']) {
                    $fields =& $this->cust->class_fields['contact'];
                    for ($i = 0; $i < count($fields); $i++)
                        $tbl .= "
                        <tr>
                            <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err_custom".$fields[$i]['obj_id']."\">".$fields[$i]['field_name'].": ".($fields[$i]['required'] ? "*" : NULL)."</td>
                            <td style=\"background-color:#ffffff;\">".$this->cust->build_input($fields[$i],
                                                                                               $this->current_contact[$fields[$i]['col_name']])."
                            </td>
                        </tr>";

                }
				if ( $this->vendor_hash ) {

				    $tbl .= "
					<tr>
						<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
							<div style=\"float:right;\">" .
								( $this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'E','contacts') ?
    								$this->form->hidden( array(
        								'contact_hash' => $this->current_contact['contact_hash']
    								) ) . "Update Contact" :
    								( ! $this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'A','contacts') ?
        								"Add Contact" : NULL
    								)
    							) . "&nbsp;&nbsp;" .
								( ( $this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'E','contacts') ) || ( ! $this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'A','contacts') ) ?
    								$this->form->button(
        								"value=+",
        								"onClick=if (\$F('contact_name')) {submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddContactLine');} else alert('Please enter at least a name for your contact before trying to submit.')"
    								) : NULL
    							) .
								( $this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'D','contacts') ?
    								"&nbsp;&nbsp;Delete Contact&nbsp;&nbsp;" .
    								$this->form->button(
        								"value=X",
        								"onClick=if (confirm('Are you sure you want to delete this contact? This action CANNOT be undone!')){submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddContactLine','delete=true');}"
    								) : NULL
    							) . "
							</div>
							<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','contact_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->vendor_hash}','p=$p','order=$order','order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>
						</td>
					</tr>";
				}

            $tbl .= "
			</table>";

		} elseif ( $this->vendor_hash ) {

			$total = $this->total_contacts;
			$num_pages = ceil($this->total_contacts / SUB_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ($p - 1);
			$end = $start_from + SUB_PAGNATION_NUM;
			if ( $end > $this->total_contacts )
				$end = $this->total_contacts;

			$order_by_ops = array(
    			"contact_name"	=>	"t1.contact_name"
			);

			$_total = $this->fetch_contacts( array(
    			'start_from'		=>	$start_from,
    			'order_by'			=>	$order_by_ops[ $order ],
    			'order_dir'			=>	$order_dir
    		) );

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\">" .
                        			( $this->total_contacts ?
    									"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" .
                            			paginate_jscript(
                                			$num_pages,
                                			$p,
                                			'sf_loadcontent',
                                			'cf_loadcontent',
                                			'vendors',
                                			'contact_info_form',
                                			$order,
                                			$order_dir,
                                			"'otf=1','vendor_hash={$this->vendor_hash}','popup_id={$this->popup_id}'"
                                		) . "
                                    	</div>
    									Showing " . ( $start_from + 1 ) . " - " .
                                		( $start_from + SUB_PAGNATION_NUM > $this->total_contacts ?
                                    		$this->total_contacts : $start_from + SUB_PAGNATION_NUM
                                    	) . " of {$this->total_contacts} Contacts for " . stripslashes($this->current_vendor['vendor_name']) : NULL
                                    ) .
                                    ( $this->p->ck(get_class($this), 'A', 'contacts') ?
										"<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">
											[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','contact_info_form','popup_id={$this->popup_id}','otf=1','action=addnew','vendor_hash={$this->vendor_hash}');\" class=\"link_standard\">Add New Contact</a></small>]
										</div>" : NULL
									) . "
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','contact_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=contact_name','order_dir=".($order == "contact_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">Name</a>" .
									( $order == 'contact_name' ?
										"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
									) . "
								</td>
								<td >Title</td>
								<td >Phone</td>
								<td >Email</td>
							</tr>";

                            $border = "style=\"border-bottom:1px solid #cccccc;\"";
							for ( $i = 0; $i < ($end - $start_from); $i++ ) {

								if ( $i >= $_total - 1 )
    								unset($border);

								$tbl .= "
								<tr " . ( $this->p->ck(get_class($this),'V','contacts') ? "onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','contact_info_form','otf=1','popup_id={$this->popup_id}','action=addnew','vendor_hash={$this->vendor_hash}','contact_hash={$this->vendor_contacts[$i]['contact_hash']}','p=$p','order=$order','order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
									<td $border>" . stripslashes($this->vendor_contacts[$i]['contact_name']) . "</td>
									<td $border>" .
									( $this->vendor_contacts[$i]['contact_title'] ?
    									$this->vendor_contacts[$i]['contact_title'] : "&nbsp;"
									) . "
									</td>
									<td $border>" .
									( $this->vendor_contacts[$i]['contact_phone1'] ?
    									$this->vendor_contacts[$i]['contact_phone1'] : "&nbsp;"
									) . "
									</td>
									<td $border>" .
									( $this->vendor_contacts[$i]['contact_email'] ?
    									$this->vendor_contacts[$i]['contact_email'] : "&nbsp;"
									) . "
									</td>
								</tr>";
							}

							if ( ! $this->total_contacts )
								$tbl .= "
								<tr>
									<td colspan=\"4\" class=\"smallfont\">You have no contacts listed under this vendor.</td>
								</tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";
		}

		if ( $this->ajax_vars['otf'] )
			$this->content['html']["tcontent3{$this->popup_id}"] = $tbl;
		else
			return $tbl;
	}

	function product_info_form() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		//If we're already editing a vendor
		if ($this->ajax_vars['vendor_hash'] && !$this->vendor_hash) {
			$this->fetch_master_record($this->ajax_vars['vendor_hash']);

			if ($valid === false)
				unset($this->ajax_vars['vendor_hash']);
		}

		$action = $this->ajax_vars['action'];

		//If we're editing a current contact under this vendor
		if ($this->ajax_vars['product_hash']) {
			$this->fetch_product_record($this->ajax_vars['product_hash']);
			if ($valid === false)
				unset($this->ajax_vars['product_hash']);
		}

		$accounting = new accounting($this->current_hash);
		list($in_hash,$in_name) = $accounting->fetch_accounts_by_type('IN');
        list($ex_hash,$ex_name) = $accounting->fetch_accounts_by_type(array('EX','CG'));

   		$sys = new system_config($this->current_hash);
   		$sys->fetch_tax_tables('US');

		if ( ! $this->vendor_hash || $action == 'addnew') {
			$this->cust->fetch_class_fields('product');

			$tbl .= $this->form->hidden(array('prod_jscript_action' => ''))."
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:15px;vertical-align:top;\" id=\"err5_1{$this->popup_id}\">Product Name or Description:</td>
					<td style=\"background-color:#ffffff;padding-top:15px;\">
						".$this->form->text_box("name=product_name","value=".$this->current_product['product_name'],"size=35","maxlength=128")."
						<div style=\"padding-top:5px;\">".$this->form->checkbox("name=product_active","value=1",(!$this->current_product || $this->current_product['product_active'] ? "checked" : NULL))."&nbsp;Active?</div>
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err5_2{$this->popup_id}\">Catalog Code:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=catalog_code","value=".$this->current_product['catalog_code'],"onKeyUp=this.value=this.value.toUpperCase()","size=5","maxlength=8")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err5_3{$this->popup_id}\">Cut Separate Purchase Order?</td>
					<td style=\"background-color:#ffffff;\">".$this->form->checkbox("name=separate_po","value=1",($this->current_product['separate_po'] ? "checked" : NULL))."</td>
				</tr>";
                if ($this->cust->class_fields['product']) {
                    $fields =& $this->cust->class_fields['product'];
                    for ($i = 0; $i < count($fields); $i++)
                        $tbl .= "
                        <tr>
                            <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err_custom".$fields[$i]['obj_id']."\">".$fields[$i]['field_name'].":</td>
                            <td style=\"background-color:#ffffff;\">".$this->cust->build_input($fields[$i],
									                                                           $this->current_product[$fields[$i]['col_name']])."
                            </td>
                        </tr>";

                }

                $tbl .= "
				<tr>
					<td colspan=\"2\" style=\"background-color:#efefef;color:#00477f;font-style:italic;padding-left:15px;\">
						If purchase orders for this product are submitted differently than is listed under the General tab, enter below:
					</td>
				</tr>
				<tr>
					<td style=\"text-align:right;background-color:#ffffff;\" id=\"err5_4{$this->popup_id}\">Electronic Order Email:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=product_order_email","value=".$this->current_product['product_order_email'],"size=35","maxlength=255")."</td>
				</tr>
				<tr>
					<td style=\"text-align:right;background-color:#ffffff;\" id=\"err5_5{$this->popup_id}\">Electronic Order Fax:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=product_order_fax","value=".$this->current_product['product_order_fax'],"size=18","maxlength=255")."</td>
				</tr>
				<tr>
					<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err5_6{$this->popup_id}\">Default Order Method:</td>
					<td style=\"background-color:#ffffff;\">
						".$this->form->radio("name=product_order_method","value=product_order_fax",($this->current_product['product_order_method'] == 'product_order_fax' ? "checked" : NULL))."&nbsp;Place orders by fax
						<div style=\"padding-top:5px;\">
						".$this->form->radio("name=product_order_method","value=product_order_email",($this->current_product['product_order_method'] == 'product_order_email' ? "checked" : NULL))."&nbsp;Place orders by email
						</div>
					</td>
				</tr>
				<tr>
					<td colspan=\"2\" style=\"background-color:#efefef;color:#00477f;font-style:italic;padding-left:15px;\">
						If this product has freight terms different than those found in the Payments tab, enter below:
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err5_7{$this->popup_id}\">
						Freight Terms:
						<div style=\"margin-top:10px;font-style:italic;\">
							<small>Quoted?</small>
							".$this->form->checkbox("name=product_frf_quoted","value=1",($this->current_product['product_frf_quoted'] ? "checked" : NULL))."
						</div>
					</td>
					<td style=\"background-color:#ffffff;\">
						<span id=\"err5_10{$this->popup_id}\">If under $</span>&nbsp;".
						$this->form->text_box("name=product_frf_amount",
											  "value=".($this->current_product['product_frf_amount'] ? number_format($this->current_product['product_frf_amount'],2) : NULL),
											  "size=8",
											  "style=text-align:right;",
											  "onBlur=this.value=formatCurrency(this.value);"
											  )."
						&nbsp;".$this->form->select("product_frf_amount_type",array("List","Net"),$this->current_product['product_frf_amount_type'],array("L","N"))."
						&nbsp;<span id=\"err5_11{$this->popup_id}\">then add </span>
						".$this->form->text_box("name=product_frf_amount_add",
												"value=".($this->current_product['product_frf_amount_add_type'] == 'P' ? $this->current_product['product_frf_amount_add']*100 : ($this->current_product['product_frf_amount_add'] > 0 ? number_format($this->current_product['product_frf_amount_add'],2) : NULL)),
												"size=6",
												"style=text-align:right;"
												)."
						&nbsp;".$this->form->select("product_frf_amount_add_type",array("dollars","%"),$this->current_product['product_frf_amount_add_type'],array("D","P"),"onChange=if(this.options[this.selectedIndex].value=='P'){\$('product_freight_amount_add_type_of_holder{$this->popup_id}').style.visibility='visible';}else{\$('product_freight_amount_add_type_of_holder{$this->popup_id}').style.visibility='hidden';}")."
						<span id=\"product_freight_amount_add_type_of_holder{$this->popup_id}\" style=\"visibility:".($this->current_product['product_frf_amount_add_type'] == 'P' ? "visible" : "hidden").";\">
							<span id=\"err5_12{$this->popup_id}\">of</span> ".$this->form->select("product_frf_amount_add_type_of",array("List","Net"),$this->current_product['product_frf_amount_add_type_of'],array("L","N"))."
						</span>
						<table>
							<tr>
								<td style=\"padding-left:10px;padding-top:5px;\" id=\"product_frf_effective_date_holder{$this->popup_id}\"></td>
								<td style=\"padding-top:5px;\" id=\"product_frf_expire_date_holder{$this->popup_id}\"></td>
							</tr>
						</table>";
						$this->content["jscript"][] = "
						setTimeout('DateInput(\'product_frf_effective_date\', ".($this->current_product['product_frf_effective_date'] ? 'true' : 'false').", \'YYYY-MM-DD\',\'".($this->current_product['product_frf_effective_date'] ? $this->current_product['product_frf_effective_date'] : NULL)."\',1,\'product_frf_effective_date_holder{$this->popup_id}\',\'<span id=\"err5_14{$this->popup_id}\">Effective:</span>&nbsp;\')',22);
						setTimeout('DateInput(\'product_frf_expire_date\', false, \'YYYY-MM-DD\',\'".($this->current_product['product_frf_expire_date'] ? $this->current_product['product_frf_expire_date'] : NULL)."\',1,\'product_frf_expire_date_holder{$this->popup_id}\',\'<span id=\"err5_15{$this->popup_id}\">Thru:</span>&nbsp;\')',23);";
						$tbl .= "
					</td>
				</tr>
				<tr>
					<td colspan=\"2\" style=\"background-color:#efefef;color:#00477f;font-style:italic;padding-left:15px;\">
						Please assign the income account, expense account, and tax status to be used for this product:
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err5_8{$this->popup_id}\">Income Account: *</td>
					<td style=\"background-color:#ffffff;\">".$this->form->select("product_income_account",$in_name,$this->current_product['product_income_account'],$in_hash)."
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err5_10{$this->popup_id}\">Expense Account: *</td>
					<td style=\"background-color:#ffffff;\">".$this->form->select("product_expense_account",$ex_name,($this->current_product['product_expense_account'] ? $this->current_product['product_expense_account'] : (defined('DEFAULT_COGS_ACCT') ? DEFAULT_COGS_ACCT : NULL)),$ex_hash)."
					</td>
				</tr>";
				if ($sys->total_tax_rules) {
					$tbl .= "
					<tr id=\"product_tax_holder\">
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err5_9{$this->popup_id}\">Taxable?</td>
						<td style=\"background-color:#ffffff;vertical-align:bottom;\">
                            <div style=\"float:right;margin-right:60px;font-size:92%;margin-top:8px;display:".($this->current_product['product_taxable'] ? "block" : "none")."\" id=\"product_taxable_checkall{$this->popup_id}\">
                                [<a href=\"javascript:void(0);\" onClick=\"checkall(document.getElementsByName('product_tax[]'),this.checked);\" class=\"link_standard\">check all</a>]
                            </div>
							".$this->form->checkbox("name=product_taxable",
                        							"value=1",
                                					($this->current_product['product_taxable'] ? "checked" : NULL),
                                					"onClick=if(this.checked){toggle_display('tax_holder{$this->popup_id}','block');toggle_display('product_taxable_checkall{$this->popup_id}','block');}else{toggle_display('tax_holder{$this->popup_id}','none');toggle_display('product_taxable_checkall{$this->popup_id}','none');}")."
							<div style=\"margin-top:5px;border-top:1px solid #8f8f8f;border-left:1px solid #8f8f8f;border-bottom:1px solid #8f8f8f;width:90%;".($sys->total_tax_rules > 3 ? "height:100px;overflow-y:auto;" : NULL)."display:".($this->current_product['product_taxable'] ? "block" : "none")."\" id=\"tax_holder{$this->popup_id}\">
								<table style=\"width:".($sys->total_tax_rules > 3 ? "94" : "100")."%;\" cellpadding=\"4\" cellspacing=\"1\">";
								while (list($state,$info) = each($sys->tax_tables)) {
									$tbl .= "
									<tr style=\"background-color:#efefef;\">
										<td style=\"border-bottom:1px solid #8f8f8f;border-right:1px solid #8f8f8f;\">
											".$this->form->checkbox("name=product_tax[]","value=".$info['state']['tax_hash'],($this->current_product['product_taxable'] && @in_array($info['state']['tax_hash'],$this->current_product['tax_hash']) ? "checked" : NULL)).
											"&nbsp;&nbsp;".
											$info['state']['state_name']."
										</td>
									</tr>";
									if ($info['local']) {
										for ($i = 0; $i < count($info['local']); $i++)
											$tbl .= "
											<tr style=\"background-color:#efefef;\">
												<td style=\"padding-left:35px;border-right:1px solid #8f8f8f;font-size:95%;border-bottom:1px solid #8f8f8f;\">
													".$this->form->checkbox("name=product_tax[]","id=tax_".$info['state']['state_name'],"value=".$info['local'][$i]['tax_hash'],($this->current_product['product_taxable'] && @in_array($info['local'][$i]['tax_hash'],$this->current_product['tax_hash']) ? "checked" : NULL)).
													"&nbsp;&nbsp;".
													$info['local'][$i]['local']."
												</td>
											</tr>";
									}
								}
							$tbl .= "
								</table>
							</div>
						</td>
					</tr>";
				}
				$tbl .= ($this->current_vendor['vendor_hash'] ? "
				<tr>
					<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
						<div style=\"float:right;\">
							".($this->current_product['product_hash'] && $this->p->ck(get_class($this),'E','products') ? $this->form->hidden(array('product_hash' => $this->current_product['product_hash']))."Update Product" : (!$this->current_product['product_hash'] && $this->p->ck(get_class($this),'A','products') ? "Add Product" : NULL))."
							&nbsp;&nbsp;".
							(($this->current_product['product_hash'] && $this->p->ck(get_class($this),'E','products')) || (!$this->current_product['product_hash'] && $this->p->ck(get_class($this),'A','products')) ? $this->form->button("value=+","onClick=if ($F('product_name')) {submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddProductLine');} else alert('Please enter a name for this product before submitting.')") : NULL)."
							".($this->current_product['product_hash'] && $this->p->ck(get_class($this),'D','products') ? "&nbsp;&nbsp;Delete Product&nbsp;&nbsp;".$this->form->button("value=X","onClick=if (confirm('Are you sure you want to delete this product? This action CANNOT be undone!')){submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddProductLine','delete=true');}",($this->current_product['rm_lock'] ? "disabled title=This product is used elsewhere and cannot be deleted." : NULL)) :
							((!$this->current_product['product_hash'] && $this->p->ck(get_class($this),'A','products')) || ($this->current_product['product_hash'] && $this->p->ck(get_class($this),'E','products')) ?
								"<div style=\"padding-top:5px;\">&nbsp;and then&nbsp;&nbsp;".$this->form->select("product_next_action",array("Return to product listings","Add another product"),($this->ajax_vars['product_action'] ? $this->ajax_vars['product_action'] : 'return'),array('','addnew'),'blank=1',"onChange=if(this.options[this.selectedIndex].value=='addnew'){\$('prod_jscript_action').value=\$('product_name').focus();}")."</div>" : NULL))."
						</div>".($this->current_vendor['vendor_hash'] ? "
						<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','product_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=$order','order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>" : NULL)."
					</td>
				</tr>" : NULL)."
			</table>";

		} elseif ( $this->vendor_hash ) {

			$total = $this->total_products;
			$num_pages = ceil($this->total_products / SUB_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ($p - 1);
			$end = $start_from + SUB_PAGNATION_NUM;
			if ($end > $this->total_products)
				$end = $this->total_products;

			$order_by_ops = array("product_name"	=>	"vendor_products.product_name",
								  "catalog_code"	=>	"vendor_products.catalog_code");

			$this->fetch_products($start_from,NULL,$order_by_ops[$order],$order_dir);

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"3\">".($this->total_products ? "
									<div style=\"float:right;font-weight:normal;padding-right:10px;\">".paginate_jscript($num_pages,$p,'sf_loadcontent','cf_loadcontent','vendors','product_info_form',$order,$order_dir,"'otf=1','vendor_hash={$this->current_vendor['vendor_hash']}','popup_id={$this->popup_id}'")."</div>
									Showing ".($start_from + 1)." - ".($start_from + SUB_PAGNATION_NUM > $this->total_products ? $this->total_products : $start_from + SUB_PAGNATION_NUM)." of ".$this->total_products." Products for ".$this->current_vendor['vendor_name']."." : NULL)."
									<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">".($this->p->ck(get_class($this),'A','products') ? "
										[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','product_info_form','popup_id={$this->popup_id}','otf=1','action=addnew','vendor_hash={$this->current_vendor['vendor_hash']}');\" class=\"link_standard\">Add New Product</a></small>]" : NULL)."
									</div>
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','product_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=product_name','order_dir=".($order == "product_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
										Product Name</a>
									".($order == 'product_name' ?
										"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
								</td>
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','product_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=catalog_code','order_dir=".($order == "catalog_code" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
										Catalog Code</a>
									".($order == 'catalog_code' ?
										"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
								</td>
								<td>Separate PO</td>
							</tr>";
							for ($i = 0; $i < ($end - $start_from); $i++) {
								$b++;
								$tbl .= "
								<tr ".($this->p->ck(get_class($this),'V','products') ? "onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','product_info_form','otf=1','popup_id={$this->popup_id}','action=addnew','vendor_hash={$this->current_vendor['vendor_hash']}','product_hash=".$this->vendor_products[$i]['product_hash']."','p=$p','order=$order','order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">
									<td class=\"smallfont\">".$this->vendor_products[$i]['product_name']."</td>
									<td class=\"smallfont\">".$this->vendor_products[$i]['catalog_code']."</td>
									<td class=\"smallfont\" style=\"padding-left:20px;\">".($this->vendor_products[$i]['separate_po'] ? 'Y' : NULL)."</td>
								</tr>".($b < ($end - $start_from) ? "
								<tr>
									<td style=\"background-color:#cccccc;\" colspan=\"3\"></td>
								</tr>" : NULL);
							}
							if (!$this->total_products)
								$tbl .= "
								<tr>
									<td colspan=\"3\" class=\"smallfont\">You have no products listed under this vendor.</td>
								</tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";
		}
		if ($this->ajax_vars['otf'])
			$this->content['html']["tcontent5{$this->popup_id}"] = $tbl;
		else
			return $tbl;
	}

	function location_info_form() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ( $this->ajax_vars['popup_id'] )
			$this->popup_id = $this->ajax_vars['popup_id'];

		if ( $this->ajax_vars['vendor_hash'] && ! $this->vendor_hash ) {

			if ( ! $this->fetch_master_record($this->ajax_vars['vendor_hash']) )
                unset($this->ajax_vars['vendor_hash']);
		}

		$this->content['jscript'][] = "setTimeout(function(){initCountry('location_countrySelect','location_stateSelect');},500);";
		$action = $this->ajax_vars['action'];

		if ( $this->ajax_vars['location_hash'] ) {

			if ( ! $this->fetch_location_record($this->ajax_vars['location_hash']) ) {

				unset($this->ajax_vars['location_hash']);
				return $this->__trigger_error("A system error was encountered when attempting to lookup vendor location. Please reload vendor window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
			}
		}

		if ( ! $this->vendor_hash || $action == 'addnew' ) {

            if ( $action == 'addnew' )
                $this->content['focus'] = 'location_name';

            $this->cust->fetch_class_fields('location');

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" style=\"padding-top:15px;text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err4_1{$this->popup_id}\">Location Name: *</td>
					<td style=\"background-color:#ffffff;padding-top:15px;\">" .
    					$this->form->text_box(
                            "name=location_name",
                            "value=" . stripslashes($this->current_location['location_name']),
                            "size=35",
                            "maxlength=128",
        					( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL )
        				) . "
        			</td>
				</tr>
                <tr>
                    <td class=\"smallfont\" style=\"padding-top:10px;text-align:right;background-color:#ffffff;width:35%;\">Account No:</td>
                    <td style=\"background-color:#ffffff;\">" .
        				$this->form->text_box(
            				"name=location_account_no",
            				"value={$this->current_location['location_account_no']}",
            				"size=15",
            				"maxlength=32",
        				    ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL )
        				) . "
        			</td>
                </tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err4_2{$this->popup_id}\">Street: </td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_area(
        					"name=location_street",
        					"value=" . stripslashes($this->current_location['location_street']),
        					"rows=3",
    					    "cols=35",
    					    ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL )
    					) . "
    				</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err4_3{$this->popup_id}\">City: *</td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_box(
        					"name=location_city",
        					"value=" . stripslashes($this->current_location['location_city']),
        					"size=30",
        					"maxlength=128",
    					    ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL )
        				) . "
        			</td>
				</tr>
                <tr>
                    <td  style=\"text-align:right;background-color:#ffffff;\" id=\"err4_4{$this->popup_id}\">State: *</td>
                    <td style=\"background-color:#ffffff;\">
                        <input
                            type=\"hidden\"
                            name=\"location_stateSelect_default\"
                            id=\"location_stateSelect_default\"
                            value=\"{$this->current_location['location_state']}\" >
                        <div><select id=\"location_stateSelect\" name=\"location_state\" " . ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "disabled" : NULL ) . "></select></div>
                    </td>
                </tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err4_5{$this->popup_id}\">Zip: </td>
					<td style=\"background-color:#ffffff;\">" .
    					$this->form->text_box(
        					"name=location_zip",
        					"value={$this->current_location['location_zip']}",
        					"size=7",
        					"maxlength=10",
    					    ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "readonly" : NULL )
    					) . "
    				</td>
				</tr>
                <tr>
                    <td  style=\"text-align:right;background-color:#ffffff;\" id=\"err4_5a{$this->popup_id}\">Country: *</td>
                    <td style=\"background-color:#ffffff;\">
                        <input
                            type=\"hidden\"
                            name=\"location_countrySelect_default\"
                            id=\"location_countrySelect_default\"
                            value=\"" .
                            ( $this->current_location['location_country'] ?
                                $this->current_location['location_country'] :
                                ( defined('MY_COMPANY_COUNTRY') ?
                                    MY_COMPANY_COUNTRY : 'US'
                                )
                            ) . "\">
                        <div><select id=\"location_countrySelect\" name=\"location_country\" " . ( $this->location_hash && ! $this->p->ck(get_class($this), 'E', 'locations') ? "disabled" : NULL ) . " onchange=\"updateState(this.id);\"></select></div>
                    </td>
                </tr>";

                if ( $this->cust->class_fields['location'] ) {

                    $fields =& $this->cust->class_fields['location'];
                    for ( $i = 0; $i < count($fields); $i++ ) {

                        $tbl .= "
                        <tr>
                            <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err_custom{$fields[$i]['obj_id']}\">{$fields[$i]['field_name']}: " . ( $fields[$i]['required'] ? "*" : NULL ) . "</td>
                            <td style=\"background-color:#ffffff;\">" .
                                $this->cust->build_input(
                                    $fields[$i],
                                    $this->current_location[ $fields[$i]['col_name'] ]
                                ) . "
                            </td>
                        </tr>";
                    }
                }

				if ( $this->vendor_hash ) {

				    $tbl .= "
					<tr>
						<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
							<div style=\"float:right;\">" .
								( $this->location_hash && $this->p->ck(get_class($this), 'E', 'locations') ?
    								$this->form->hidden( array(
        								'location_hash' => $this->location_hash
        							) ) . "Update Location"
        							:
        							( ! $this->location_hash && $this->p->ck(get_class($this), 'A', 'locations') ?
            							"Add Location" : NULL
        							)
        						) . "
								&nbsp;&nbsp;" .
								( ( $this->location_hash && $this->p->ck(get_class($this), 'E', 'locations') ) || ( ! $this->location_hash && $this->p->ck(get_class($this), 'A', 'locations') ) ?
    								$this->form->button(
        								"value=+",
        								"onClick=submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddLocationLine');"
    								) : NULL
    							) .
								( $this->location_hash && $this->p->ck(get_class($this), 'D', 'locations') ?
    								"&nbsp;&nbsp;Delete Location&nbsp;&nbsp;" .
    								$this->form->button(
        								"value=X",
        								"onClick=if( confirm('Are you sure you want to delete this location? This action CANNOT be undone!')){ submit_form(this.form,'vendors','exec_post','refresh_form','actionBtn=AddLocationLine','delete=true');}",
        								( $this->current_location['rm_lock'] ? "disabled title=This location is linked to records elsewhere in the system and cannot be deleted." : NULL)
        							) : NULL
        						) . "
							</div>" .
        					( $this->vendor_hash ? "
    							<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','location_info_form','popup_id={$this->popup_id}','otf=1','vendor_hash={$this->vendor_hash}','p=$p','order=$order','order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>" : NULL
    						) . "
						</td>
					</tr>";
				}

            $tbl .= "
			</table>";

		} elseif ( $this->vendor_hash ) {

			$total = $this->total_locations;
			$num_pages = ceil($this->total_locations / SUB_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ($p - 1);

			$end = $start_from + SUB_PAGNATION_NUM;
			if ( $end > $this->total_locations )
				$end = $this->total_locations;

			$order_by_ops = array(
    			"location_name"		    =>	"t1.location_name",
			    "location_account_no"   =>  "t1.location_account_no",
				"location_address"	    =>  "t1.location_state, t1.location_city"
			);

			$_total = $this->fetch_locations(
    			$start_from,
    			$order_by_ops[ ( $order ? $order : "location_name" ) ],
    			( $order_dir ? $order_dir : "ASC" )
    		);

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td class=\"smallfont\" style=\"font-weight:bold;vertical-align:middle;\" colspan=\"3\">" .
	                    			( $this->total_locations ?
										"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" .
    	                    			paginate_jscript(
        	                    			$num_pages,
        	                    			$p,
        	                    			'sf_loadcontent',
        	                    			'cf_loadcontent',
        	                    			'vendors',
        	                    			'location_info_form',
        	                    			$order,
        	                    			$order_dir,
        	                    			"'otf=1','vendor_hash={$this->current_vendor['vendor_hash']}','popup_id={$this->popup_id}'"
        	                    		) . "
        	                    		</div>
										Showing " . ( $start_from + 1 ) . " - " .
	                        			( $start_from + SUB_PAGNATION_NUM > $this->total_locations ?
	                            			$this->total_locations : $start_from + SUB_PAGNATION_NUM
	                            		) . " of {$this->total_locations} Locations for " . stripslashes($this->current_vendor['vendor_name']) . "." : NULL
	                            	) . "
									<div style=\"padding-left:15px;padding-top:5px;font-weight:normal\">" .
                                	( $this->p->ck(get_class($this),'A','locations') ?
										"[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','location_info_form','otf=1','action=addnew','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}');\" class=\"link_standard\">Add New Location</a></small>]" : NULL
                                	) . "
									</div>
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','location_info_form','popup_id={$this->popup_id}','otf=1','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=location_name','order_dir=" . ( $order == "location_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "');\" style=\"color:#ffffff;text-decoration:underline;\">Location Name</a>" .
									( $order == 'location_name' ?
										"&nbsp;<img src=\"images/" .
    									( $order_dir == 'ASC' ?
        									"s_asc.png" : "s_desc.png"
    									) . "\">" : NULL
    								) . "
								</td>
                                <td >
                                    <a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','location_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=location_account_no','order_dir=" . ( $order == "location_account_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC') . "');\" style=\"color:#ffffff;text-decoration:underline;\">Account No.</a>" .
                                    ( $order == 'location_account_no' ?
                                        "&nbsp;<img src=\"images/" .
                                        ( $order_dir == 'ASC' ?
                                            "s_asc.png" : "s_desc.png"
                                        ) . "\">" : NULL
                                    ) . "
                                </td>
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','location_info_form','otf=1','popup_id={$this->popup_id}','vendor_hash={$this->current_vendor['vendor_hash']}','p=$p','order=location_address','order_dir=" . ( $order == "location_address" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "');\" style=\"color:#ffffff;text-decoration:underline;\">Location Address</a>" .
                                    ( $order == 'location_address' ?
                                        "&nbsp;<img src=\"images/" .
                                        ( $order_dir == 'ASC' ?
                                            "s_asc.png" : "s_desc.png"
                                        ) . "\">" : NULL
                                    ) . "
								</td>
							</tr>";

                            $border = "style=\"border-bottom:1px solid #cccccc;\"";
							for ( $i = 0; $i < $_total; $i++ ) {

								if ( $i >= $_total - 1 )
    								unset($border);

								$tbl .= "
								<tr " . ( $this->p->ck(get_class($this), 'V', 'locations') ? "onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','location_info_form','otf=1','popup_id={$this->popup_id}','action=addnew','vendor_hash={$this->current_vendor['vendor_hash']}','location_hash={$this->vendor_locations[$i]['location_hash']}','p=$p','order=$order','order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
                                    <td $border>" .
                                    ( strlen($this->vendor_locations[$i]['location_name']) > 34 ?
                                        stripslashes( substr($this->vendor_locations[$i]['location_name'], 0, 34) ) . "..." : stripslashes($this->vendor_locations[$i]['location_name'])
                                    ) . "
                                    </td>
                                    <td $border>" .
                                    ( $this->vendor_locations[$i]['location_account_no'] ?
                                        $this->vendor_locations[$i]['location_account_no'] : "&nbsp;"
                                    ) . "
                                    </td>
                                    <td $border>" . stripslashes($this->vendor_locations[$i]['location_city']) . ", {$this->vendor_locations[$i]['location_state']} </td>
								</tr>";
							}
							if ( ! $this->total_locations )
								$tbl .= "
								<tr>
									<td colspan=\"3\" class=\"smallfont\">You have no locations listed under this vendor.</td>
								</tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";
		}

		if ( $this->ajax_vars['otf'] )
			$this->content['html']["tcontent4{$this->popup_id}"] = $tbl;
		else
			return $tbl;
	}


	function showall() {

		$this->unlock("_");
		if ( $p = $this->load_class_navigation() )
			return;

		$form = new form;

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

		$this->popup_id = $this->content['popup_controls']['popup_id'] = ( $this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : 'popup_win_list' );

		$this->vendors($this->current_hash);

		$total_1 = $this->total;
		$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ( $end > $this->total )
			$end = $this->total;

		$order_by_ops = array(
    		"vna"			=>	"vendors.vendor_name",
		    "ano"           =>  "vendors.account_no",
			"vlo"			=>	"vendors.state , vendors.city",
			"vbcur"			=>  "balance_current",
			"vbsched1"		=>  "balance_sched1",
			"vbsched2"		=>  "balance_sched2",
			"vbsched3"		=>  "balance_sched3",
			"balance_total"	=>	"balance_total"
		);

		$this->fetch_vendors(
    		$start_from,
    		$order_by_ops[ ( isset($order) && $order_by_ops[$order] ? $order : "vna" ) ],
    		$order_dir,
    		1
    	);

		if ( $this->total != $total_1 ) {

			$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
			$p = ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p;
			$start_from = MAIN_PAGNATION_NUM * ($p - 1);

			$end = $start_from + MAIN_PAGNATION_NUM;
			if ( $end > $this->total )
				$end = $this->total;

		}

		$tbl .=
		( $this->active_search ?
    		"<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
    		<div style=\"padding-top:8px;padding-left:25px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall');\">Show All</a>]</div>" : NULL
		) . "
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"padding:15;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"8\">" .
	                    		( $this->total ?
									"<div style=\"float:right;font-weight:normal;padding-right:10px;\">" .
	                        		paginate_jscript(
	                            		$num_pages,
	                            		$p,
	                            		'sf_loadcontent',
	                            		'cf_loadcontent',
	                            		'vendors',
	                            		'showall',
	                            		$order,
	                            		$order_dir,
	                            		"'active_search={$this->active_search}'",
	                            		"popup_id={$this->popup_id}'"
	                            	) . "
	                            	</div>
									Showing " . ( $start_from + 1 ) . " - " .
	                            	( $start_from + MAIN_PAGNATION_NUM > $this->total ?
	                                	$this->total : $start_from + MAIN_PAGNATION_NUM
	                                ) . " of {$this->total} Vendors." : NULL
                                ) . "
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
                                    ( $this->p->ck(get_class($this),'A') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','show_popup_window','edit_vendor','popup_id=main_popup','active_search={$this->active_search}','p=$p','order=$order','order_dir=$order_dir');\"><img src=\"images/new.gif\" title=\"Create a new vendor\" border=\"0\" /></a>&nbsp;" : NULL
	                                ) . "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','show_popup_window','search_vendors','popup_id=main_popup','active_search={$this->active_search}','p=$p','order=$order','order_dir=$order_dir');\"><img src=\"images/search.gif\" title=\"Search vendors\" border=\"0\" /></a>
									&nbsp;" .
									( $this->p->ck(get_class($this),'A','rcv_pmt') ?
    									"<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','show_popup_window','receive_payment','popup_id=main_popup','active_search={$this->active_search}','p=$p','order=$order','order_dir=$order_dir');\"><img src=\"images/new_deposit.gif\" title=\"Receive vendor payments on direct bills\" border=\"0\" /></a>" : NULL
    								) . "&nbsp;
									<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('reports','sf_loadcontent','show_popup_window','print_it_xls','type=vendors','popup_id=xls_vendors','title=Vendor List','active_search={$this->active_search}');\"><img src=\"images/excel.gif\" title=\"Export vendor list into a spreadsheet.\" border=\"0\" /></a>
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=vna','order_dir=".($order == "vna" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search={$this->active_search}'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Vendor Name</a>" .
								( $order == 'vna' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
							</td>
                            <td>
                                <a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=ano','order_dir=".($order == "ano" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search={$this->active_search}'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Account No.</a>" .
                                ( $order == 'ano' ?
                                    "&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
                                ) . "
                            </td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=vlo','order_dir=".($order == "vlo" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search={$this->active_search}'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Location</a>" .
								( $order == 'vlo' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
							</td>
							<td style=\"text-align:right;\">" .
								( $order == 'vbcur' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=vbcur','order_dir=".($order == "vbcur" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search={$this->active_search}'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Current</a>
							</td>
							<td style=\"text-align:right;\" nowrap>" .
								( $order == 'vbsched1' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=vbsched1','order_dir=".($order == "vbsched1" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search={$this->active_search}'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Past " . ACCOUNT_AGING_SCHED1 . "</a>
							</td>
							<td style=\"text-align:right;\" nowrap>" .
								( $order == 'vbsched2' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=vbsched2','order_dir=".($order == "vbsched2" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search={$this->active_search}'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Past ".ACCOUNT_AGING_SCHED2."</a>
							</td>
							<td style=\"text-align:right;\" nowrap>" .
								( $order == 'vbsched3' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=vbsched3','order_dir=".($order == "vbsched3" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search={$this->active_search}'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Past ".ACCOUNT_AGING_SCHED3."</a>
							</td>
							<td style=\"text-align:right;padding-right:10px;\">" .
								( $order == 'total' ?
    								"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL
								) . "
								<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','cf_loadcontent','showall','p=$p','order=total','order_dir=".($order == "total" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search={$this->active_search}'" : NULL).",'popup_id={$this->popup_id}');\" style=\"color:#ffffff;text-decoration:underline;\">Total</a>
							</td>
						</tr>";

						$border = "border-bottom:1px solid #AAC8C8;";
						for ( $i = 0; $i < ($end - $start_from); $i++ ) {

							$vendor_info = $this->vendor_list_detail($this->vendor_info[$i]['obj_id']);

							$b++;
							if ( $this->p->ck(get_class($this),'V') )
								$onClick = "onClick=\"agent.call('vendors','sf_loadcontent','show_popup_window','edit_vendor','popup_id={$this->popup_id}','vendor_hash={$vendor_info['vendor_hash']}','active_search={$this->active_search}','p=$p','order=$order','order_dir=$order_dir');\"";

							if ( $b >= ( $end - $start_from ) )
    							unset($border);

							$tbl .= "
							<tr " . ( $this->p->ck(get_class($this),'V') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
                                <td $onClick " . ( $border ? "style=\"$border;\"" : NULL ) . ">" . stripslashes($vendor_info['vendor_name']) . "&nbsp;</td>
                                <td $onClick " . ( $border ? "style=\"$border;\"" : NULL ) . ">" .
                                ( $vendor_info['account_no'] ?
                                    $vendor_info['account_no'] : "&nbsp;"
                                ) . "
                                </td>
								<td $onClick " . ( $border ? "style=\"$border;\"" : NULL ) . ">" .
									( $vendor_info['city'] ?
	    								stripslashes($vendor_info['city']) . ", " : NULL
	    							) . "{$vendor_info['state']}&nbsp;
	    						</td>
								<td $onClick style=\"text-align:right;$border;" . ( $vendor_info['balance_current'] < 0 ? "color:red;" : NULL ) . "\">" .
								( bccomp($vendor_info['balance_current'], 0, 2) ?
	    							( bccomp($vendor_info['balance_current'], 0, 2) == -1 ?
	    								"(\$" . number_format( bcmul($vendor_info['balance_current'], -1, 2), 2) . ")" : "\$" . number_format($vendor_info['balance_current'], 2)
	    							) : "&nbsp;"
	    						) . "
    							</td>
								<td $onClick style=\"text-align:right;$border;" . ( $vendor_info['balance_sched1'] < 0 ? "color:red;" : NULL ) . "\">" .
								( bccomp($vendor_info['balance_sched1'], 0, 2) ?
	                                ( bccomp($vendor_info['balance_sched1'], 0, 2) == -1 ?
	                                    "(\$" . number_format( bcmul($vendor_info['balance_sched1'], -1, 2), 2) . ")" : "\$" . number_format($vendor_info['balance_sched1'], 2)
	                                ) : "&nbsp;"
	                            ) . "
								</td>
								<td $onClick style=\"text-align:right;$border;" . ( $vendor_info['balance_sched2'] < 0 ? "color:red;" : NULL ) . "\">" .
								( bccomp($vendor_info['balance_sched2'], 0, 2) ?
	                                ( bccomp($vendor_info['balance_sched2'], 0, 2) == -1 ?
	                                    "(\$" . number_format( bcmul($vendor_info['balance_sched2'], -1, 2), 2) . ")" : "\$" . number_format($vendor_info['balance_sched2'], 2)
	                                ) : "&nbsp;"
                                ) . "
								</td>
								<td $onClick style=\"text-align:right;$border;" . ( $vendor_info['balance_sched3'] < 0 ? "color:red;" : NULL ) . "\">" .
								( bccomp($vendor_info['balance_sched3'], 0, 2) ?
	                                ( bccomp($vendor_info['balance_sched3'], 0, 2) == -1 ?
	                                    "(\$" . number_format( bcmul($vendor_info['balance_sched3'], -1, 2), 2) . ")" : "\$" . number_format($vendor_info['balance_sched3'], 2)
	                                ) : "&nbsp;"
	                            ) . "
								</td>
								<td $onClick style=\"text-align:right;$border;padding-right:10px;font-weight:bold;" . ( $vendor_info['balance_total'] < 0 ? "color:red;" : NULL ) . "\">" .
								( bccomp($vendor_info['balance_total'], 0, 2) ?
	                                ( bccomp($vendor_info['balance_total'], 0, 2) == -1 ?
	                                    "(\$" . number_format( bcmul($vendor_info['balance_total'], -1, 2), 2) . ")" : "\$" . number_format($vendor_info['balance_total'], 2)
	                                ) : "&nbsp;"
                                ) . "
								</td>
							</tr>";
						}

						if ( ! $this->total )
							$tbl .= "
							<tr >
								<td colspan=\"8\">" .
    							( $this->active_search ?
									"<div style=\"padding-top:10px;font-weight:bold;\">Search returned empty result set</div>"
        							:
        							"You have no vendors to display. " .
        							( $this->p->ck(get_class($this),'A') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('vendors','sf_loadcontent','show_popup_window','edit_vendor','popup_id=main_popup');\">Create a new vendor now.</a>" : NULL
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

	function search_vendors() {
        global $stateNames,$states,$country_codes,$country_names;

		$date = date("U") - 3600;
		$result = $this->db->query("DELETE FROM `search`
							  		WHERE `timestamp` <= '$date'");

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Search Vendors";
		$this->content['focus'] = "vendor";

        if ($this->ajax_vars['active_search']) {
            $result = $this->db->query("SELECT `search_str`
                                        FROM `search`
                                        WHERE `search_hash` = '".$this->ajax_vars['active_search']."'");
            $row = $this->db->fetch_assoc($result);
            $search_vars = permissions::load_search_vars($row['search_str']);
        }

        if (defined('MULTI_CURRENCY')) {
            $sys = new system_config();
            $sys->fetch_currencies();

            for ($i = 0; $i < count($sys->currency); $i++) {
                $currency_out[] = $sys->currency[$i]['name'];
                $currency_in[] = $sys->currency[$i]['code'];
            }
        }

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array('popup_id' => $this->content['popup_controls']['popup_id']))."
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
                            Filter your vendor search criteria below:
                        </div>
                        <table>
                            <tr>
                                <td style=\"padding-left:15px;\">
                                    <fieldset>
                                        <legend>Vendor Name</legend>
										<div style=\"padding:5px;padding-bottom:5px\">
												".$this->form->text_box("name=vendor",
																		"value=",
																		"autocomplete=off",
																		"size=30",
																		"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('customer_vendor','vendor','vendor_hash',1);}",
                                                                        "onKeyUp=if(ka==false && this.value){key_call('customer_vendor','vendor','vendor_hash',1);}",
																		"onBlur=key_clear();"
																		).
            									$this->form->hidden(array("vendor_hash" => $last_input['vendor_hash']))."
                                        </div>
                                    </fieldset>
                                </td>
                                <td style=\"padding-left:15px;\">
                                    <fieldset>
                                        <legend>Account Number</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->text_box("name=account_no",
                                                                    "value=".$search_vars['account_no'],
                                                                    "size=15",
                                                                    "onFocus=this.select()",
                                                                    "maxlength=64")."
                                        </div>
                                    </fieldset>
                                </td>
                                <td style=\"padding-left:15px;vertical-align:top;\">
                                    <fieldset>
                                        <legend>Vendor Number</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->text_box("name=vendor_no",
                                                                    "value=".$search_vars['vendor_no'],
                                                                    "size=15",
                                                                    "onFocus=this.select()",
                                                                    "maxlength=64")."
                                        </div>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;\" colspan=\"3\">
                                    <table>
                                        <tr>
                                            <td >
                                                <fieldset>
                                                    <legend>Located In</legend>
                                                    <div style=\"padding:5px;padding-bottom:5px\">
                                                        ".$this->form->select("state",
                                                                              $stateNames,
                                                                              $search_vars['state'],
                                                                              $states)."
                                                    </div>
                                                </fieldset>
                                            </td>
                                            <td >
                                                <fieldset>
                                                    <legend>Country</legend>
                                                    <div style=\"padding:5px;padding-bottom:5px\">
                                                        ".$this->form->select("country",
                                                                              $country_names,
                                                                              $search_vars['country'],
                                                                              $country_codes)."
                                                    </div>
                                                </fieldset>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;text-align:left\" colspan=\"3\">
                                    <table>
                                        <tr>
                                            <td >
                                                <fieldset>
                                                    <legend>Tax ID</legend>
                                                    <div style=\"padding:5px;padding-bottom:5px\">
                                                        ".$this->form->text_box("name=tax_id",
                                                                                "value=".$search_vars['tax_id'],
                                                                                "size=15",
                                                                                "onFocus=this.select()",
                                                                                "maxlength=12")."
                                                    </div>
                                                </fieldset>
                                            </td>
                                            <td >
                                                <fieldset>
                                                    <legend>Discount ID</legend>
                                                    <div style=\"padding:5px;padding-bottom:5px\">
                                                        ".$this->form->text_box("name=discount_id",
                                                                                "value=".$search_vars['discount_id'],
                                                                                "size=15",
                                                                                "onFocus=this.select()",
                                                                                "maxlength=24")."
                                                    </div>
                                                </fieldset>
                                            </td>".(defined('MULTI_CURRENCY') ? "
                                            <td style=\"padding-left:15px;vertical-align:top;\">
                                                <fieldset>
                                                    <legend>Vendor Currency</legend>
                                                    <div style=\"padding:5px;padding-bottom:5px\">
                                                        ".$this->form->select("currency",
                                                                              $currency_out,
                                                                              $search_vars['currency'],
                                                                              $currency_in)."
                                                    </div>
                                                </fieldset>
                                            </td>" : NULL)."
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;\" colspan=\"3\">
                                    <table>
                                        <tr>
                                            <td >
                                                <div>
	                                                <fieldset>
	                                                    <legend>Default Order Method</legend>
	                                                    <div style=\"padding:5px;padding-bottom:5px\">
	                                                        ".$this->form->select("order_method",
	                                                                               array('Fax','Email','Electronic Interface'),
	                                                                               $search_vars['order_method'],
	                                                                               array('order_fax','order_email','order_eorder'))."
	                                                    </div>
	                                                </fieldset>
	                                            </div>
	                                            <div style=\"margin-left:15px;margin-top:10px;\">
                                                    ".$this->form->checkbox("name=v_1099",
                                                                            "value=1",
                                                                            ($search_vars['v_1099'] ? "checked" : NULL))."
                                                    &nbsp;1099 Vendor?
	                                            </div>
	                                            <div style=\"margin-left:15px;margin-top:10px;\">
                                                    ".$this->form->checkbox("name=dep_req",
                                                                            "value=1",
                                                                            ($search_vars['dep_req'] ? "checked" : NULL))."
                                                    &nbsp;Deposit Required?
	                                            </div>
                                            </td>
                                            <td style=\"vertical-align:middle\">
                                                <div style=\"margin-left:25px;\">
	                                                <fieldset>
	                                                    <legend>Misc Vendor Fees</legend>
	                                                    <div style=\"padding:5px;padding-bottom:5px\">
	                                                        ".$this->form->select("vendor_fees[]",
	                                                                               array("Small Order Fees","Vendor Freight Fees","Product Specific Freight Fees","Fuel Surcharge","Call Before Delivery Fees"),
	                                                                               ($search_vars['vendor_fees'] && is_array($search_vars['vendor_fees']) ? $search_vars['vendor_fees'] : array($search_vars['vendor_fees'])),
	                                                                               array("sof","frf","pfrf","fsf","cbd"),
	                                                                               "blank=1",
	                                                                               "multiple",
	                                                                               "style=width:250px;",
	                                                                               "size=5")."
	                                                    </div>
	                                                </fieldset>
	                                            </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
			<div style=\"text-align:right;padding:15px;\">
				".$this->form->button("value=Search","id=primary","onClick=submit_form($('popup_id').form,'vendors','exec_post','refresh_form','action=doit_search');")."
			</div>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}

	function vendor_list_detail($obj_id) {

		if ($obj_id) {
            $result = $this->db->query("SELECT vendors.vendor_hash , vendors.vendor_name , vendors.city ,
                                        vendors.state , vendors.account_no ,
                                        (SUM(CASE
                                            WHEN (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN vendor_payables.type = 'C' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN vendor_payables.type = 'D' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END)
                                        ) AS balance_total ,
                                        (SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) < ".ACCOUNT_AGING_SCHED1." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END)
                                        ) AS balance_current ,
                                        (SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED1." AND ".(ACCOUNT_AGING_SCHED2 - 1)." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END)
                                        ) AS balance_sched1 ,
                                        (SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) BETWEEN ".ACCOUNT_AGING_SCHED2." AND ".(ACCOUNT_AGING_SCHED3 - 1)." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END)
                                        ) AS balance_sched2 ,
                                        (SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND (vendor_payables.type = 'I' OR vendor_payables.type = 'R') AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND vendor_payables.type = 'C' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END) -
                                        SUM(CASE
                                            WHEN DATEDIFF(CURDATE(),vendor_payables.due_date) >= ".ACCOUNT_AGING_SCHED3." AND vendor_payables.type = 'D' AND vendor_payables.balance != 0
                                                THEN vendor_payables.balance
                                                ELSE 0
                                            END)
                                        ) AS balance_sched3
                                        FROM `vendors`
                                        LEFT JOIN vendor_payables ON vendor_payables.vendor_hash = vendors.vendor_hash AND vendor_payables.deleted = 0
                                        WHERE vendors.obj_id = $obj_id
                                        GROUP BY vendors.vendor_hash");
            return $this->db->fetch_assoc($result);
		}
	}
}

?>
