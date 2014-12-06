<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class discounting {

	public $invoke_from;
	public $vendor_hash;
	public $customer_hash;

	public $total;
	public $total_discounts;

	public $discounts = array();
	public $current_discount;
	public $default_standard_discount;

	public $discount_items = array();
	public $current_item;

	function discounting($id,$invoke_from=NULL) {
		global $db;

		$this->db =& $db;
		$this->current_hash = $_SESSION['id_hash'];
		$this->invoke_from = strtoupper($invoke_from);

		if ($this->invoke_from == 'V')
			$this->vendor_hash = $id;
		else {
			$this->invoke_from == 'C';
			$this->customer_hash = $id;
		}

		if ($this->invoke_from) {
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `discounting`
										WHERE ".($this->invoke_from == 'V' ?
											"discounting.vendor_hash = '".$this->vendor_hash."'" : "discounting.customer_hash = '".$this->customer_hash."'")." AND discounting.deleted = 0");
			$this->total = $this->db->result($result);

            $result = $this->db->query("SELECT COUNT(*) AS Total
                                        FROM `discounting`
                                        WHERE ".($this->invoke_from == 'V' ?
                                            "discounting.vendor_hash = '".$this->vendor_hash."'" : "discounting.customer_hash = '".$this->customer_hash."'")." AND DATEDIFF(discounting.discount_expiration_date, CURDATE()) >= 0 AND discounting.deleted = 0");
            $this->total_valid = $this->db->result($result);
		}
	}

	function fetch_discounts($start, $order_by=NULL, $order_dir=NULL) { # Fetch the discount records for a given vendor

		$this->discounts = array();
		$total = 0;

		if ( ! $end )
			$end = MAIN_PAGNATION_NUM;

		if ( $order_by ) {

			$pattern = array('/^discounting\.(.*)$/', '/^vendors\.(.*)$/', '/^customers\.(.*)$/');
	        $replace = array('t1.$1', 't2.$1', 't3.$1');
	        $order_by = preg_replace($pattern, $replace, $order_by);
		}

		$result = $this->db->query("SELECT
	                            		t1.*,
	                            		t2.vendor_name AS discount_vendor,
	                            		CASE
	                            		WHEN t1.discount_type = 'C' THEN
		                            		t3.customer_name ELSE
    	                            		'Standard Discount'
	                            		END AS customer_name
									FROM discounting t1
									LEFT JOIN vendors t2 ON t2.vendor_hash = t1.vendor_hash
									LEFT JOIN customers t3 ON t3.customer_hash = t1.customer_hash
									WHERE " .
                                	( $this->invoke_from == 'V' ?
                                        "t1.vendor_hash = '{$this->vendor_hash}' " : "t1.customer_hash = '{$this->customer_hash}' "
                                	) . "AND t1.deleted = 0 " .
                                	( $order_by ?
    									"ORDER BY $order_by " .
                                    	( $order_dir ?
                                        	$order_dir : "ASC"
                                        ) : NULL
                                    ) . "
							  		LIMIT $start, $end");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$total++;
			array_push($this->discounts, $row);
		}

		return $total;
	}

	function fetch_proposal_discounts($customer_hash) {
		$this->discounts = array();
		$customer = new customers($this->current_hash);
		$customer->fetch_master_record($customer_hash);
		if ($customer->current_customer['gsa'])
			$this->customer_gsa = true;

		unset($customer);
		$result = $this->db->query("SELECT discounting.* , COUNT(discount_details.discount_hash) AS total_discounts
								    FROM `discounting`
									LEFT JOIN `discount_details` ON discount_details.discount_hash = discounting.discount_hash AND discount_details.deleted = 0
								    WHERE discounting.vendor_hash = '".$this->vendor_hash."'
									AND (
										(discounting.discount_type = 'C' AND discounting.customer_hash = '$customer_hash')
										OR (discounting.discount_type = 'S' ".(!$this->customer_gsa ? "
    										AND discounting.discount_gsa = 0" : NULL).")
									)
									AND '".date("Y-m-d")."' BETWEEN discounting.discount_effective_date AND discounting.discount_expiration_date
									AND discounting.deleted = 0
									GROUP BY discounting.discount_hash
									ORDER BY discounting.discount_descr ASC");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->discounts,$row);

		$key = in_multi_array($this->discounts,$customer_hash,'customer_hash');
		if (is_numeric($key)) {
			$count = count($this->discounts);

			$this->discounts = array_values($this->discounts);
		}
	}

	function fetch_discount_record($discount_hash) {
		$result = $this->db->query("SELECT discounting.* , customers.customer_name AS discount_customer ,
									customers.customer_hash AS discount_customer_hash , vendors.vendor_name AS discount_vendor ,
									vendors.vendor_hash AS discount_vendor_hash , COUNT(t1.discount_hash) AS total_discounts
							  		FROM `discounting`
                                    LEFT JOIN discount_details t1 ON t1.discount_hash = discounting.discount_hash AND t1.deleted = 0
									LEFT JOIN customers ON customers.customer_hash = discounting.customer_hash
									LEFT JOIN vendors ON vendors.vendor_hash = discounting.vendor_hash
							  		WHERE discounting.discount_hash = '$discount_hash'
									GROUP BY discounting.discount_hash");
		if ($row = $this->db->fetch_assoc($result)) {

			$this->current_discount = $row;
			$this->discount_hash = $this->current_discount['discount_hash'];
			$this->total_discounts = $row['total_discounts'];

			return true;
		}

		return false;
	}

	function fetch_discount_items($start, $end, $order_by=NULL, $order_dir=NULL, $parent_only=false) {

		if ( ! $this->discount_hash )
			return 0;

        $this->discount_items = array();
        $total = 0;

        if ( ! $end )
            $end = MAIN_PAGNATION_NUM;

        if ( $order_by ) {

            $pattern = array('/^discount_details\.(.*)$/', '/^vendor_products\.(.*)$/');
            $replace = array('t1.$1', 't2.$1', 't3.$1');
            $order_by = preg_replace($pattern, $replace, $order_by);
        }

		$result = $this->db->query("SELECT
                                		t1.*,
                                		t2.product_name,
                                		t2.catalog_code
									FROM discount_details t1
									LEFT JOIN vendor_products t2 ON t2.product_hash = t1.product_hash
									WHERE t1.discount_hash = '{$this->discount_hash}' AND t1.deleted = 0 " .
									( $order_by ?
    							  		"ORDER BY $order_by " .
    									( $order_dir ?
        									$order_dir : "ASC"
        								) : NULL
        							) . "
							  		LIMIT $start, $end");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$total++;
			array_push($this->discount_items, $row);
		}

		return $total;
	}

	function fetch_default_standard() {
		if (!$this->vendor_hash)
			return;

		$result = $this->db->query("SELECT discounting.*, vendors.vendor_name AS discount_vendor , vendors.vendor_hash AS discount_vendor_hash
							  		FROM `discounting`
									LEFT JOIN `vendors` ON vendors.vendor_hash = discounting.vendor_hash
							  		WHERE discounting.vendor_hash = '".$this->vendor_hash."' AND discounting.discount_default = 1
							  		AND '".date("Y-m-d")."' BETWEEN discounting.discount_effective_date AND discounting.discount_expiration_date
							  		AND discounting.deleted = 0
									GROUP BY discounting.discount_hash");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->default_standard_discount = $row;

			return true;
		}

	}

	function fetch_discount_items_short($discount_hash) {
		if ($discount_hash == '_')
			return;

		$result = $this->db->query("SELECT discount_details.item_hash , discount_details.product_hash ,
									discount_details.item_no , discount_details.discount_type ,
									discounting.discount_descr , discounting.discount_id ,
									discounting.discount_expiration_date
									FROM `discount_details`
									LEFT JOIN discounting ON discounting.discount_hash = discount_details.discount_hash
									WHERE discount_details.discount_hash = '$discount_hash' AND discount_details.deleted = 0");
		while ($row = $this->db->fetch_assoc($result)) {
			$item_hash[] = $row['item_hash'];
			$product_hash[] = $row['product_hash'];
			$discount_type[] = $row['discount_type'];
			$discount_descr[] = $row['discount_descr'];
			$discount_id[] = $row['discount_id'];
			$expiration_date[] = $row['discount_expiration_date'];
			$item_no[] = $row['item_no'];
		}

		return array($item_hash,$product_hash,$discount_type,$discount_descr,$discount_id,$expiration_date,$item_no);
	}


	function fetch_item_record($item_hash) {
		if (!$this->discount_hash)
			return;

		if ($this->total_discounts) {
			$result = $this->db->query("SELECT discount_details.* , vendor_products.product_name , vendor_products.catalog_code
										FROM `discount_details`
										LEFT JOIN vendor_products ON vendor_products.product_hash = discount_details.product_hash
										WHERE discount_details.discount_hash = '".$this->discount_hash."' && discount_details.item_hash = '$item_hash'");
			if ($row = $this->db->fetch_assoc($result)) {
				$this->current_item = $row;
				$this->item_hash = $this->current_item['item_hash'];
				$this->discount_hash = $this->current_item['discount_hash'];

				if ($this->current_item['discount_frf']) {
					$discount_frf = explode("|",$this->current_item['discount_frf']);
					foreach ($discount_frf as $discount_frf_el) {
						$discount_frf_el = str_replace('}', '', $discount_frf_el);
						list($a,$b) = explode("=",$discount_frf_el);
						$a_2 = str_replace("product_","discount_",$a);
						$this->current_item[$a] = $b;
						$this->current_item[$a_2] = $b;
					}
				}

				return true;
			}
		}
		return false;
	}


	function compare($customer_hash,$data) {
		$this->fetch_proposal_discounts($customer_hash);

		$flag = array();
	}




























}
?>