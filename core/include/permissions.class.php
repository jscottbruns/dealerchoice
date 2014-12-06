<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class permissions extends search {

	protected $db;

	public $perm_user;
	public $permission;
	public $remote_user;

	public $active_lock = array();

	function permissions($perm_user,$group=NULL,$sys_config=NULL) {
		global $db;

		$this->db =& $db;
		$this->perm_user = $perm_user;
		if ($group)
			$this->group_hash  = $perm_user;

		if ($this->perm_user != $_SESSION['id_hash'] || $sys_config) {
			$this->fetch_user_permissions();
			$this->remote_user = true;
		}
		/*
		else {
			$this->permission = array();
			$result = $this->db->query("SELECT `group_hash`
										FROM `user_subscribe`
										WHERE `id_hash` = '".$this->perm_user."'");
			while ($row = $this->db->fetch_assoc($result))
				$group[] = "'".$row['group_hash']."'";

			if ($_SESSION['user_name'] == 'root')
				$result = $this->db->query("SELECT permissions.*
											FROM `permissions`
											WHERE permissions.obj_id > 0");
			else
				$result = $this->db->query("SELECT permissions. *
											FROM `permissions`
											LEFT JOIN user_permissions ON user_permissions.permission_hash = permissions.permission_hash
											LEFT JOIN users ON users.id_hash = user_permissions.id_hash
											LEFT JOIN user_subscribe ON user_subscribe.id_hash = users.id_hash
											WHERE user_permissions.id_hash = '".$this->perm_user."'".(is_array($group) ? "
											OR user_permissions.group_hash
											IN (".implode(" , ",$group).")" : NULL)."
											GROUP BY permissions.permission_hash");
			while ($row = $this->db->fetch_assoc($result))
				$this->permission[$row['class']][($row['content'] ? $row['content'] : 'default')][] = $row['permission'];

		}
		*/
		return;
	}

	function fetch_user_permissions() {
		$this->permission = array();

		$result = $this->db->query("SELECT permissions.*
									FROM `permissions`
									LEFT JOIN `user_permissions` ON user_permissions.permission_hash = permissions.permission_hash
									WHERE user_permissions.".($this->group_hash ? "group_hash" : "id_hash")." = '".$this->perm_user."'");
		while ($row = $this->db->fetch_assoc($result))
			$this->permission[$row['class']][($row['content'] ? $row['content'] : 'default')][] = $row['permission'];

	}

	//This function deletes all permissions for the current user under a given class, in anticipation of someone else editing the permissions
	function prepare_permissions($class) {
		$this->db->query("DELETE user_permissions.*
						  FROM `user_permissions`
						  LEFT JOIN permissions ON permissions.permission_hash = user_permissions.permission_hash
						  WHERE user_permissions.".($this->group_hash ? "group_hash" : "id_hash")." = '".$this->perm_user."' AND permissions.class = '$class'");

		return;
	}

	function grant($perm, $class) {

		if ( func_num_args() == 3 )
			$content = func_get_arg(2);

		$result = $this->db->query("SELECT COUNT(t1.permission_hash) AS Total
									FROM user_permissions t1
									LEFT JOIN permissions t2 ON t2.permission_hash = t1.permission_hash
									WHERE t1." .
									( $this->group_hash ?
										"group_hash" : "id_hash"
									) . " = '{$this->perm_user}' AND t2.class = '$class' AND " .
									( $content ?
										"t2.content = '$content'" : "ISNULL(t2.content)"
									) . " AND t2.permission = '$perm'");
		if ( ! $this->db->result($result, 0, 'Total') ) {

			$r = $this->db->query("SELECT t1.permission_hash
								   FROM permissions t1
								   WHERE t1.class = '$class' AND " .
								   ( $content ?
									   "t1.content = '$content' " : "ISNULL(t1.content) "
								   ) . " AND t1.permission = '$perm'");
			if ( $perm_hash = $this->db->result($r, 0, 'permission_hash') ) {

				if ( ! $this->db->query("INSERT INTO user_permissions
									     ( " .
										     ( $this->group_hash ?
											     "group_hash" : "id_hash"
										     ) . ",
										     permission_hash
									     )
									     VALUES
									     (
										     '{$this->perm_user}',
										     '$perm_hash'
									     )")
				) {

					ajax_library::__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__);
					return false;
				}

				return true;
			}
		}
	}

	function revoke($perm,$class,$content) {
		$this->db->query("DELETE user_permissions.*
						  FROM `user_permissions`
						  LEFT JOIN `permissions` ON permissions.permission_hash = user_permissions.permission_hash
						  WHERE user_permissions.".($this->group_hash ? "group_hash" : "id_hash")." = '".$this->perm_user."' AND permissions.class = '$class' AND ".($content ? "permissions.content = '$content'" : "ISNULL(permissions.content)")." AND permissions.permission = '$perm'");
	}

	//Checking the current users permissions
	function ck($class,$perm,$content=NULL) {
		if ($this->remote_user)
			$perm_array =& $this->permission;
		else
			$perm_array =& $_SESSION['permission'];

		// Prevent logging error for Trac #1270
		if (!is_array($perm_array))
			return false;

		if (@in_array($perm,$perm_array[$class][($content ? $content : "default")]))
			return true;

		return false;
	}

	function lock($table_name,$record_hash,$popup_id,$user=NULL) {
		if (!$user)
			$user = $this->perm_user;

		$this->db->query("DELETE FROM `table_lock`
						  WHERE table_lock.user_hash = '$user' AND (table_lock.popup_id = '$popup_id'".($table_name == 'proposals' ? " OR (`table_name` = 'proposals' AND `popup_id` = '')" : NULL).")");

		//First see if the record is already locked, if so, check to see if the user is still active
		$result = $this->db->query("SELECT table_lock.obj_id , table_lock.user_hash , users.full_name ,
									users.user_name , table_lock.timestamp as lock_time , session.time as session_time
									FROM `table_lock`
									LEFT JOIN `session` ON session.id_hash = table_lock.user_hash
									LEFT JOIN `users` ON users.id_hash = table_lock.user_hash
									WHERE table_lock.table_name = '$table_name' AND table_lock.record_hash = '$record_hash'");
		$row = $this->db->fetch_assoc($result);

		//A user has locked the record
		if (($row['lock_time'] && !$row['session_time']) || !$row['lock_time'] || ($row['lock_time'] && $row['user_hash'] == $this->perm_user) || $row['user_name'] == 'root') {
			//The user susequently logged out and left an old lock record

			if (!$row['session_time'])
				$this->db->query("DELETE FROM `table_lock`
								  WHERE table_lock.record_hash = '$record_hash'");
            if ($row['user_hash'] == $this->perm_user) {
                $this->db->query("UPDATE `table_lock`
                                  SET `timestamp` = ".time()."
                                  WHERE `obj_id` = ".$row['obj_id']);
                unset($this->active_lock);
                $this->lock_id = $row['obj_id'];
            } else {
				$this->db->query("INSERT INTO `table_lock`
								  (`timestamp` , `user_hash` , `table_name` , `record_hash` , `popup_id`)
								  VALUES (".time()." , '$user' , '$table_name' , '$record_hash' , '$popup_id')");

				$this->lock_id = $this->db->insert_id();
				unset($this->active_lock);
            }
			return $this->lock_id;
		}

		$this->active_lock = array('table_name' 	=>	$table_name,
								   'lock_id'		=>	$row['obj_id'],
								   'record_hash'	=>  $record_hash,
								   'full_name'		=>	$row['full_name'],
								   'timestamp'  	=>	$row['lock_time']);
		return ($_SESSION['user_name'] == 'root' ? 9999 : false);
	}

	function unlock($id) {
		$this->db->query("DELETE FROM `table_lock`
						  WHERE `obj_id` = '$id'");
		return;
	}

	function lock_stat($cc,$popup_id=NULL) {
		if ($this->active_lock)
			return "
			<span style=\"cursor:hand;\" title=\"This record is currently in use:\n\nUser: ".stripslashes($this->active_lock['full_name'])."\nTimestamp: ".date(TIMESTAMP_FORMAT,$this->active_lock['timestamp'])."\n\nClick to notify this user.\" onClick=\"agent.call('".$cc."','sf_loadcontent','nothing','notify','row_id=".$this->active_lock['lock_id']."');\">
				<img src=\"images/lock.gif\" id=\"record_status_holder".$popup_id."\" />&nbsp;&nbsp;<b>This record is currently open</b>
			</span>";
	}

	function notify($id) {
		$this->db->query("UPDATE `table_lock`
						  SET `notify` = '".$this->perm_user."'
						  WHERE `obj_id` = '$id'");
		//if ($this->db->affected_rows())
			return true;

		return false;
	}

	function is_locked($table_name,$record_hash) {
		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `table_lock`
									WHERE `table_name` = '$table_name' AND `record_hash` = '$record_hash'");
		if ($this->db->result($result))
			return true;

		return false;
	}


	function page_pref($class,$func=NULL) {
		$result = $this->db->query("SELECT *
									FROM `page_prefs`
									WHERE `id_hash` = '".$this->perm_user."' AND `class` = '$class'".($func ? " AND `func` = '$func'" : NULL));
		if ($row = $this->db->fetch_assoc($result))
			return $this->load_search_vars($row['pref_str']);

	}

	function load_search_vars($str) {
		$pref = explode("|",$str);
		for ($i = 0; $i < count($pref); $i++) {
			list($var,$val) = explode("=",$pref[$i]);
			if (ereg("&",$val))
				$val = explode("&",$val);

			$page_pref[$var] = $val;
		}

		return $page_pref;
	}
}
?>