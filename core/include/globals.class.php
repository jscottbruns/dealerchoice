<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class global_classes {
	function get_rand_id($length,$class=NULL) {
		if ($length > 0) { 
			$rand_id = "";
			for($i = 1; $i <= $length; $i++) {
				mt_srand((double)microtime() * 1000000);
				$num = mt_rand(1,36);
				
				$rand_id .= ($class ? global_classes::assign_rand_value($num) : $this->assign_rand_value($num));
			}
		}
		
		return $rand_id;
	} 
	
	
	function assign_rand_value($num) {
		// accepts 1 - 36
		switch($num) {
			case "1":
			$rand_value = "a";
			break;
			case "2":
			$rand_value = "b";
			break;
			case "3":
			$rand_value = "c";
			break;
			case "4":
			$rand_value = "d";
			break;
			case "5":
			$rand_value = "e";
			break;
			case "6":
			$rand_value = "f";
			break;
			case "7":
			$rand_value = "g";
			break;
			case "8":
			$rand_value = "h";
			break;
			case "9":
			$rand_value = "i";
			break;
			case "10":
			$rand_value = "j";
			break;
			case "11":
			$rand_value = "k";
			break;
			case "12":
			$rand_value = "l";
			break;
			case "13":
			$rand_value = "m";
			break;
			case "14":
			$rand_value = "n";
			break;
			case "15":
			$rand_value = "o";
			break;
			case "16":
			$rand_value = "p";
			break;
			case "17":
			$rand_value = "q";
			break;
			case "18":
			$rand_value = "r";
			break;
			case "19":
			$rand_value = "s";
			break;
			case "20":
			$rand_value = "t";
			break;
			case "21":
			$rand_value = "u";
			break;
			case "22":
			$rand_value = "v";
			break;
			case "23":
			$rand_value = "w";
			break;
			case "24":
			$rand_value = "x";
			break;
			case "25":
			$rand_value = "y";
			break;
			case "26":
			$rand_value = "z";
			break;
			case "27":
			$rand_value = "0";
			break;
			case "28":
			$rand_value = "1";
			break;
			case "29":
			$rand_value = "2";
			break;
			case "30":
			$rand_value = "3";
			break;
			case "31":
			$rand_value = "4";
			break;
			case "32":
			$rand_value = "5";
			break;
			case "33":
			$rand_value = "6";
			break;
			case "34":
			$rand_value = "7";
			break;
			case "35":
			$rand_value = "8";
			break;
			case "36":
			$rand_value = "9";
			break;
		}
		
		return $rand_value;
	}
	
	function key_exists($table,$col,$val) {
		global $db;
		
		$result = $db->query("SELECT COUNT(*) AS Total
							  FROM `$table`
							  WHERE `$col` = '$val'");
		
		if ($db->result($result) > 0) return true;
		return false;				
	}

	function validate_email($email) {
		return (ereg('^[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+'. '@'. '[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+\.' . '[-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~]+$', $email));
	}

	function validateUsername($username) {
		global $db;
		$result = $db->query("SELECT COUNT(*) AS Total 
							  FROM `users` 
							  WHERE `user_name` = '$username'");
	
		//Can't be a duplicate in the DB
		if ($db->result($result) > 0) {
			return false;
		} 
		//Must contain at least one of these
		if (strspn($username, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-") == 0) {
			return false;
		}
		//must contain all legal characters
		if (strspn($username, "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_") != strlen($username)) {
			return false;
		}
		
		//illegal names
		if (eregi("^((root)|(bin)|(support)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|(halt)|(mail)|(news)|(uucp)|(operator)|(admin)|(games)|(mysql)|(billing)|(bugs)|(error)|(info)|(jeff)|(operations)|(staff)|(everybody)|(httpd)|(nobody)|(dummy)|(www)|(cvs)|(shell)|(ftp)|(irc)|(debian)|(ns)|(download))$", $username)) {
			return false;
		}
		//Some unix thing
		if (eregi("^(anoncvs_)", $username)) {
			return false;
		}
			
	
		return true;
	}

	function email_exists($email) {
		global $db;
	
		$result = $db->query("SELECT COUNT(*) AS Total FROM `user_login` WHERE `email` = '$email'");
		
		if ($db->result($result) > 0)
			return false;
	
		return true;
	}

	function Decrypt($string) {
		global $secret_hash_padding;
		$result = '';
		for($i=1; $i<=strlen($string); $i++) {
			$char = substr($string, $i-1, 1);
			$keychar = substr($secret_hash_padding, ($i % strlen($secret_hash_padding))-1, 1);
			$char = chr(ord($char)-ord($keychar));
			$result .= $char;
		}
		return $result;
	}
	
	function Encrypt($string) {
		global $secret_hash_padding;
	
		$result = '';
		for($i=1; $i<=strlen($string); $i++) {
			$char = substr($string, $i-1, 1);
			$keychar = substr($secret_hash_padding, ($i % strlen($secret_hash_padding))-1, 1);
			$char = chr(ord($char)+ord($keychar));
			$result .= $char;
		}
		return $result;
	}
	
	function calculate_commission($proposal_hash) {
		global $db;
		
		$r = $this->db->query("SELECT proposals.customer_hash , proposals.sales_hash ,
								(
									SELECT customers.gsa
									FROM customers
									WHERE customers.customer_hash = proposals.customer_hash
								) AS customer_gsa ,
							 	(
							 		SELECT users.commission_hash
							 		FROM users
							 		WHERE users.id_hash = proposals.sales_hash
							 	) AS commission_hash
							   FROM `proposals`
							   WHERE proposals.proposal_hash = '".$proposal_hash."'");
		$customer_hash = $this->db->result($r,0,'customer_hash');
		$sales_hash = $this->db->result($r,0,'sales_hash');
		$commission_hash = $this->db->result($r,0,'commission_hash');
		$gsa = $this->db->result($r,0,'customer_gsa');
		
		//First check for customer commission rule
		$result = $db->query("SELECT `commission_hash` , `type`
							  FROM `commission_tables`
							  WHERE `active` = 1 
							  	AND 
							  	(
							  		('".date("Y-m-d")."' BETWEEN `effective_date` AND `expiration_date`) 
							  		OR 
							  		(`effective_date` = '' AND `expiration_date` = '') 
							  		OR
							  		(`effective_date` != '' AND '".date("Y-m-d")."' >= `effective_date`) 
							  		OR 
							  		(`expiration_date` != '' AND '".date("Y-m-d")."' <= `expiration_date`)
							  	)
							  	AND 
							  	(
							  		(`type` = 'C' AND `customer_hash` = '".$customer_hash."') ".($gsa ? "
									OR `type` = 'G'" : NULL).($commission_hash ? "
									OR 
									(`type` = 'D' AND `commission_hash` = '".$commission_hash."')" : NULL)."
								)");
		while ($row = $db->fetch_assoc($result))
			$com[$row['type']] = $row;
			
		unset($p,$c,$s);
		if ($com['C']) 
			return $com['C']['commission_hash'];
		elseif ($com['G']) 
			return $com['G']['commission_hash'];
		elseif ($com['D']) 
			return $com['D']['commission_hash'];
		
	}

	function calculate_overhead($proposal_hash) {
		global $db;
		
		$p = new proposals($_SESSION['id_hash']);
		$p->fetch_master_record($proposal_hash);
		
		$c = new customers($_SESSION['id_hash']);
		$c->fetch_master_record($p->current_proposal['customer_hash']);
		
		$s = new system_config($_SESSION['id_hash']);
		$s->fetch_user_record($p->current_proposal['sales_hash']);
		
		//First check for customer commission rule
		$result = $db->query("SELECT `overhead_hash` , `type` 
							  FROM `overhead_rules`
							  WHERE `active` = 1 
							  	AND (('".date("Y-m-d")."' BETWEEN `effective_date` AND `expiration_date`) OR (`effective_date` = '' AND `expiration_date` = '') OR (`effective_date` != '' AND ".date("Y-m-d")." >= `effective_date`) OR (`expiration_date` != '' AND ".date("Y-m-d")." <= `expiration_date`))
							  	AND ((`type` = 'C' AND `customer_hash` = '".$p->current_proposal['customer_hash']."') ".($c->current_customer['gsa'] ? "
									OR `type` = 'G'" : NULL)."
										 OR `type` = 'D')");
		while ($row = $db->fetch_assoc($result))
			$overhead[$row['type']] = $row;
			
		unset($p,$c,$s);
		if ($overhead['C']) 
			return $overhead['C']['overhead_hash'];
		elseif ($overhead['G']) 
			return $overhead['G']['overhead_hash'];
		elseif ($overhead['D']) 
			return $overhead['D']['overhead_hash'];
		
	}
}

?>