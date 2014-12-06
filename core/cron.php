<?php
##DOCUMENT_ROOT##
$crond = 1;
set_time_limit(0);
$cron_db = array('dealerchoice_hm_FLOORING','dealerchoice_hm_FURNITURE');
if (file_exists(SITE_ROOT.'core/include/config/live_database_list'))
	$cron_db = file(SITE_ROOT.'core/include/config/live_database_list');
else
	$cron_db = array($db_name);
	
require_once('include/common.php');

for ($i = 0; $i < count($cron_db); $i++) {
	$db->select_db($cron_db[$i]);
	$mail_queue = fetch_sys_var('MAIL_QUEUE');
	
	//Dump the mail/fax queue
	if ($mail_queue) {
		$hash = $_GET['hash'];
		$mail = new mail();
		$mail->empty_message_queue();
	} 
	//Clear old sessions
	$user_timeout = fetch_sys_var('USER_TIMEOUT');
	if (!$user_timeout)
		$user_timeout = 5;
		
	$time = time() - ($user_timeout * 60);
	$result = $db->query("SELECT `id_hash` 
						  FROM `session` 
						  WHERE `time` < '$time'");
	while ($row = $db->fetch_assoc($result)) 
		$login_class->user_logout($row['id_hash']);
	
	//Delete old search records
	$time = time() - 86400;
	$db->query("DELETE FROM `search`
				WHERE `timestamp` < $time ");
				
	//Make sure that there are no outstanding database processes are defunct
	$r = $db->query("SELECT `connection_id`
					 FROM user_stack");
	$process_id = array();
	if ($db->num_rows($r) > 0) {
		$result = $db->query("SHOW PROCESSLIST");
		while ($row = $db->fetch_assoc($result)) {
			if ($row['User'] == $db_username && $row['db'] == $cron_db[$i] && $row['Command'] != 'Sleep')
				$process_id[] = $row['Id'];	
		}
		while ($row2 = $db->fetch_assoc($r)) {
			if (!in_array($row2['connection_id'],$process_id)) 
				$db->query("DELETE FROM user_stack
							WHERE `connection_id` = ".$row2['connection_id']);
							
		}
	}
}
exit;	

?>
