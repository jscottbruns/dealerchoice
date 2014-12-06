<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class override extends AJAX_library { 
	
	function override($hash) {
		global $db;
		
		$this->db =& $db;
		$this->form = new form;
		$this->current_hash = ($hash ? $hash : $_SESSION['id_hash']);
		$this->content['class_name'] = get_class($this);
	
		$this->dialog_box = $this->form->text_area("name=auth_msg","rows=3","cols=15");
		
		return;
	}

	function _new($class,$obj_hash,$content,$msg=NULL) {
		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `override`
									WHERE `class` = '$class' AND `obj_hash` = '$obj_hash' AND `content` = '$content'");
		if ($this->db->result($result))
			return false;
			
		$override_hash = md5(global_classes::get_rand_id(32,"global_classes"));
		while (global_classes::key_exists('override','override_hash',$override_hash))
			$override_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			
		$queue_hash = md5(global_classes::get_rand_id(32,"global_classes"));
		while (global_classes::key_exists('system_queue','queue_hash',$queue_hash))
			$queue_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			
		$this->db->query("INSERT INTO `override`
						  (`timestamp` , `override_hash` , `queue_hash` , `class` , `content` , `obj_hash` , `request_by` , `request_msg`)
						  VALUES(".time()." , '$override_hash' , '$queue_hash' , '$class' , '$content' , '$obj_hash' , '".$_SESSION['id_hash']."' , '$msg')");
		
		return $override_hash;
	}
	
	function _override() {
		$this->content['action'] = 'continue';
		$this->content['jscript_action'] = "alert('y');";
		return;
	}
	
	function _stat_detail($hash,$local=NULL) {
		
	}
	
	function doit() {
		$class = $_POST['class'];
		$obj_hash = $_POST['obj_hash'];
		$msg = $_POST['auth_msg'];
		$content = $_POST['content'];
		$auth_action = $_POST['auth_action'];
		$auth_holder = $_POST['auth_holder'];
		
		if ($auth_action == '_new') {
			$override_hash = $this->_new($class,$obj_hash,$content,$msg);
			if ($auth_holder) {
				$stat = $this->_stat($override_hash);
				$this->content['html'][$auth_holder] = "Y";
			}
			$this->content['action'] = 'continue';
			return;
		} else
			return $this->$auth_action();
	}
}
?>