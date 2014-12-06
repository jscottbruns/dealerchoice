<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class alerts extends AJAX_library {
	
	public $total;
	public $class_names = array('proposals'			=>	'Proposals',
							 	'purchase_order'	=>	"Purchase Orders",
							 	'customer_invoice' 	=>	"Customer Invoice",
							 	'payables'			=>	"Vendor Bills");
	
	function alerts() {
		global $db;
		
		$this->form = new form;
		$this->db =& $db;
		$this->current_hash = $_SESSION['id_hash'];		
		$this->system_mailbox = array('INBOX','SENT','DELETED');
		$this->mailbox = ($_POST['mailbox'] ? $_POST['mailbox'] : $this->ajax_vars['mailbox']);
		if (!$this->mailbox) 
			$this->mailbox = 'INBOX';
		
	    $this->groups = $_SESSION['group'];
		if (is_array($this->groups))
			array_walk($this->groups,'add_quotes',"'");
		
		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `messages`
									WHERE `deleted` = ".($this->mailbox == 'DELETED' ? "1" : "0 ".($this->mailbox != 'SENT' ? " AND messages.mailbox = '".$this->mailbox."'" : NULL))." AND (`".($this->mailbox == 'SENT' ? "sender_hash" : "recipient_hash")."` = '".$this->current_hash."' ".(is_array($this->groups) && $this->mailbox != 'SENT' ? "
										 OR messages.group_hash IN (".implode(" , ",$this->groups).")" : NULL).")"); 
		$this->total = $this->db->result($result);

		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `messages`
									WHERE `deleted` = 0 AND `received_on` = ".($this->mailbox == 'DELETED' ? "1" : "0 ".($this->mailbox != 'SENT' ? " AND messages.mailbox = '".$this->mailbox."'" : NULL))." AND (`".($this->mailbox == 'SENT' ? "sender_hash" : "recipient_hash")."` = '".$this->current_hash."' ".(is_array($this->groups) && $this->mailbox != 'SENT' ? "
										 OR messages.group_hash IN (".implode(" , ",$this->groups).")" : NULL).")"); 
		$this->total_unread = $this->db->result($result);

		return;
	}
	
	function quick_fetch($class,$hash1,$hash2=NULL) {
		switch ($class) {
			case 'proposals':
			$r = $this->db->query("SELECT customers.customer_name , proposals.proposal_no
								   FROM `proposals`
								   LEFT JOIN customers ON customers.customer_hash = proposals.customer_hash
								   WHERE proposals.proposal_hash = '$hash1'");
			return $this->db->fetch_assoc($r);
			break;
		}
	}
	
	function poll() {
		$this->fetch_messages(0,$this->total,"messages.obj_id");
		
		$last_msg_id = $this->messages[count($this->messages)-1];		
		if ($last_msg_id['obj_id'] != $_SESSION['message_id'] && count($this->messages)) {
			$_SESSION['message_id'] = $last_msg_id['obj_id'];
			$notify = true;
		}

		if ($this->total_unread)
			$this->content['html']['status_alert_count'] = '('.$this->total_unread.')';
		else
			$this->content['html']['status_alert_count'] = '';

		$result = $this->db->query("SELECT table_lock.obj_id , users.full_name
									FROM `table_lock`
									LEFT JOIN `users` ON users.id_hash = table_lock.notify
									WHERE table_lock.user_hash = '".$this->current_hash."' AND !ISNULL(table_lock.notify)
									LIMIT 1");
		if ($this->db->num_rows($result)) {
			$this->content['jscript'][] = "alert('".$this->db->result($result,0,'full_name')." is requesting access to the record you are viewing. Please close the record when you are finished working.')";
			$this->db->query("UPDATE `table_lock`
							  SET `notify` = NULL
							  WHERE `obj_id` = ".$this->db->result($result,0,'obj_id'));
		}
		$result = $this->db->query("SELECT chat.chat_hash , users.full_name
									FROM `chat`
									LEFT JOIN `users` ON users.id_hash = chat.user_hash
									WHERE chat.recipient_hash = '".$this->current_hash."' AND chat.closed = 0 AND chat.ack = 0
									LIMIT 1");
		if ($this->db->num_rows($result)) {
			$notify = true; 
				
			$this->content['jscript'][] = "agent.call('chat','sf_loadcontent','show_popup_window','chat_prompt','popup_id=chatprompt','chat_hash=".$this->db->result($result,0,'chat_hash')."');";
			//$this->db->query("UPDATE `chat`
							 //SET `ack` = 1
							  //WHERE `chat_hash` = '".$this->db->result($result,0,'chat_hash')."'");
		}
		if ($notify)
			$this->content['jscript'][] = "playSound();";
		
		$this->db->query("UPDATE `chat`
						  SET `closed` = 1 , `close_time` = ".time()."
						  WHERE `closed` = 0 AND (`user_hash` = '".$this->current_hash."' OR `recipient_hash` = '".$this->current_hash."') AND `timestamp` < '".(time() - 15)."'");
		return;	
	}
	
	function doit() {
		global $err,$errStr, $db;
		
		$this->check = new Validator;
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$action = $_POST['action'];
		$jscript_action = $_POST['jscript_action'];
		$action = $_POST['action'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id = $_POST['popup_id'];

		if ($action)
			return $this->$action();
	}
	

	function fetch_messages($start,$end=NULL,$order_by=NULL,$order_dir=NULL) {
		if (!$end)
			$end = SUB_PAGNATION_NUM;
		
		$this->messages = array();
		$result = $this->db->query("SELECT messages.timestamp , messages.message_hash , messages.sender_hash ,
									messages.recipient_hash , messages.group_hash , messages.subject , 
									messages.received_on , t1.full_name AS sender_name ,  
									CASE
									WHEN messages.recipient_hash != ''
                                        THEN t2.full_name
                                        ELSE user_groups.group_name
                                    END AS recipient								
									FROM `messages`
                                    LEFT JOIN users t1 ON t1.id_hash = messages.sender_hash
                                    LEFT JOIN users t2 ON t2.id_hash = messages.recipient_hash
                                    LEFT JOIN user_groups ON user_groups.group_hash = messages.group_hash                                                                        
									WHERE `deleted` = ".($this->mailbox == 'DELETED' ? "1" : "0 ".($this->mailbox != 'SENT' ? " AND messages.mailbox = '".$this->mailbox."'" : NULL))." AND (`".($this->mailbox == 'SENT' ? "sender_hash" : "recipient_hash")."` = '".$this->current_hash."' ".(is_array($this->groups) && $this->mailbox != 'SENT' ? "
										 OR messages.group_hash IN (".implode(" , ",$this->groups).")" : NULL).")".($order_by ? "
							  				ORDER BY messages.timestamp ".($order_dir ? $order_dir : "ASC") : NULL)."
							  		LIMIT $start , $end");
		while ($row = $this->db->fetch_assoc($result)) 
			array_push($this->messages,$row);
			
	}
	
	function fetch_message($message_hash) {
		$result = $this->db->query("SELECT messages.* , t1.full_name as sender , t1.user_name as sender_user_name , t2.full_name as recipient_name
									FROM `messages`
									LEFT JOIN `users` as t1 ON t1.id_hash = messages.sender_hash
									LEFT JOIN `users` as t2 ON t2.id_hash = messages.recipient_hash
									WHERE messages.message_hash = '$message_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->message_hash = $message_hash;
			$this->current_message = $row;
			
			return true;
		}
						
		return false;
	}
	
	function fetch_mailboxes() {
		$result = $this->db->query("SELECT `mailbox_hash` , `mailbox_name`
									FROM `message_box`
									WHERE `mailbox_owner` = '".$this->current_hash."'
									ORDER BY `mailbox_name` ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			$mbox_hash[] = $row['mailbox_hash'];
			$mbox_name[] = " -- ".$row['mailbox_name'];
		}
		
		return array($mbox_hash,$mbox_name);
	}
	
	function doit_send() {
		$message_hash = $_POST['message_hash'];
		$mailbox = $_POST['mailbox'];
				
		if ($_POST['rm'] == 1) {
			if ($_POST['delete'] == 1)
				$this->db->query("DELETE FROM `messages`
								  WHERE `message_hash` = '$message_hash'");
			else 
				$this->db->query("UPDATE `messages`
								  SET `deleted` = 1
								  WHERE `message_hash` = '$message_hash'");
			
			$this->content['page_feedback'] = "Message has been deleted.";
			$this->content['action'] = 'continue';
			$this->content['jscript_action'] = "agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','otf=1','popup_id=".$this->popup_id."','mailbox=".$mailbox."');";
			return;
		} elseif ($_POST['unread'] == 1) {
			$this->db->query("UPDATE `messages`
							  SET `received_by` = '' , `received_on` = ''
							  WHERE `message_hash` = '$message_hash'");
			$this->content['page_feedback'] = "Message has been marked as unread.";
			$this->content['action'] = 'continue';
			$this->content['jscript_action'] = "agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','otf=1','popup_id=".$this->popup_id."');";
			return;
		} elseif ($_POST['read'] == 1) {
			$this->db->query("UPDATE `messages`
							  SET `received_by` = '".$this->current_hash."' , `received_on` = ".time()."
							  WHERE `message_hash` = '$message_hash'");
			$this->content['page_feedback'] = "Message has been marked as read.";
			$this->content['action'] = 'continue';
			$this->content['jscript_action'] = "agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','otf=1','popup_id=".$this->popup_id."','action=read','message_hash=$message_hash');";
			return;
		} elseif ($_POST['move_msg'] == 1) {
			if ($move_to = $_POST['move_to']) {
				
				$this->db->query("UPDATE `messages`
								  SET `deleted` = ".($move_to == "DELETED" ? "1" : "0")." , `mailbox` = '$move_to'
								  WHERE `message_hash` = '$message_hash'");
				$this->content['jscript_action'] = "agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','popup_id=".$this->popup_id."','otf=1','mailbox=".$move_to."');";
				$this->content['page_feedback'] = "Message has been moved.";
			} else {
				$this->content['page_feedback'] = "You didn't select which folder to move your message to. No changes have been made.";
				$this->content['jscript_action'] = "agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','popup_id=".$this->popup_id."','otf=1','mailbox=".$move_to."');";
			}
			$this->content['action'] = 'continue';
			return;
		} else {
			$to = $_POST['to'];
			$msg = strip_tags($_POST['msg']);
			$subject = $_POST['subject'];
			
			if ($to) {
				$rcp = explode("\n",$to);
				for ($i = 0; $i < count($rcp); $i++) {
					preg_match("/(.*?) <(.*?)>/", $rcp[$i], $matches);				
					$addr = ($matches[2] ? trim($matches[2]) : trim($rcp[$i]));
					if ($addr) {
						$result = $this->db->query("SELECT `id_hash`
													FROM `users`
													WHERE `user_name` = '$addr'");
						if (!$this->db->result($result)) {
							$e = true;
							$ef[] = strip_tags($addr);
						} else
							$send_to[] = $this->db->result($result);
					}
				}
				if ($e) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = count($ef)." recipient".(count($ef) > 1 ? "s are" : " is")." invalid. The invalid recipient".(count($ef) > 1 ? "s are" : " is").":<br />".implode("; ",$ef);
					return false;
				}
				
				for ($i = 0; $i < count($send_to); $i++) {
					$message_hash = md5(global_classes::get_rand_id(32,"global_classes"));
					while (global_classes::key_exists('messages','message_hash',$message_hash))
						$message_hash = md5(global_classes::get_rand_id(32,"global_classes"));
					
					$this->db->query("INSERT INTO `messages`
									  (`timestamp` , `message_hash` , `sender_hash` , `recipient_hash` , `subject` , `message`)
									  VALUES(".time()." , '$message_hash' , '".$this->current_hash."' , '".$send_to[$i]."' , '$subject' , '$msg')");
					$this->message_email($message_hash);
				}
				
				$this->content['action'] = "continue";
				$this->content['page_feedback'] = "Message sent";
				$this->content['jscript_action'] = "agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','otf=1','popup_id=".$this->popup_id."');";
				return;
			} else {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = 'In order to continue you must enter at least one recipient.';
				$this->content['form_return']['err']['err1'.$this->popup_id] = 1;
			}
			return;
		}
	}
	
	//Send an internal message the users email
	function message_email($message_hash) {
		$result = $this->db->query("SELECT messages.* , t1.full_name as recipient_name , t1.email as recipient_email , t2.full_name as sender_name , t2.email as sender_email
									FROM `messages`
									LEFT JOIN `users` AS t1 ON t1.id_hash = messages.recipient_hash
									LEFT JOIN `users` AS t2 ON t2.id_hash = messages.sender_hash
									WHERE messages.message_hash = '$message_hash' AND t1.receive_email = 1 AND t1.email != ''");
		if ($row = $this->db->fetch_assoc($result)) {
			$recipient_name = stripslashes($row['recipient_name']);
			$recipient_email = $row['recipient_email'];
			$sender_name = stripslashes($row['sender_name']);
			$sender_email = $row['sender_email'];
			
			$url = LINK_ROOT;
			$subject = stripslashes($row['subject']);
			$message_body = stripslashes($row['message']);
			$from_system = ($row['sender_hash'] == 'SYSTEM' ? "system message" : "message from ".$sender_name);
			$message = <<<EOT
Greetings-

You have received a new $from_system titled $subject. You can view your message below, or log into DealerChoice at $url and view your message under the Messages tab on the top of the page. 

--Start of message--------------------

$message_body
EOT;
			
			$mail = new PHPMailer();
			$mail->AddAddress($recipient_email);

			$mail->Body = $message;
			$mail->AltBody = $message;
			$mail->From = "noreply@dc-sysllc.com";
			$mail->FromName = "DealerChoice System Notification";
			
			$mail->IsSMTP();
			$mail->Host = SMTP_HOST;
			$mail->SMTPAuth = true;
			$mail->Username = SMTP_USER;
			$mail->Password = SMTP_PASS;
			$mail->Subject = "DealerChoice ".($row['sender_hash'] == 'SYSTEM' ? "system message" : "message from ".$sender_name)." - ".(strlen($subject) > 45 ? substr($subject,0,40)."..." : $subject); 
			
			$mail_result = $mail->Send();
		}
	}

	function message_win() {
		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Messages";
		
		$action = $this->ajax_vars['action'];
		if ($this->ajax_vars['message_hash']) {
			$valid = $this->fetch_message($this->ajax_vars['message_hash']);
			if ($valid === false)
				unset($this->ajax_vars['message_hash']);
		}
		
		$mbox_name = array_slice($this->system_mailbox,0,1);
		$mbox_hash = array_slice($this->system_mailbox,0,1);
		list($user_mbox_hash,$user_mbox_name) = $this->fetch_mailboxes();
		if (is_array($user_mbox_hash)) {
			$mbox_name = $mbox_move_name = array_merge($mbox_name,$user_mbox_name);
			$mbox_hash = $mbox_move_hash = array_merge($mbox_hash,$user_mbox_hash);
		}
		
		$mbox_name = array_merge($mbox_name,array_slice($this->system_mailbox,1));
		$mbox_hash = array_merge($mbox_hash,array_slice($this->system_mailbox,1));

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];		
		
		$tbl .= $this->form->form_tag().
		$this->form->hidden(array('popup_id' 		=> 	$this->popup_id))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<div id=\"msg_content_holder".$this->popup_id."\">";
			
			if ($action == 'new') {
				if ($this->ajax_vars['reply_hash']) {
					$valid = $this->fetch_message($this->ajax_vars['reply_hash']);
					$reply_to = $this->current_message['sender']." &lt;".strtolower($this->current_message['sender_user_name'])."&gt;";
					$reply_subject = "Re: ".$this->current_message['subject'];
					$reply_msg = "\n\n\n--------------------------------------------------------------------\n".$this->current_message['message'];
				}
				$inner_tbl = "
				<div style=\"float:right;padding-top:25px;margin-right:55px;\">
					".$this->form->button("value=Send Message","onClick=submit_form(this.form,'alerts','exec_post','refresh_form','action=doit_send')")."
				</div>	
				<h3 style=\"margin-bottom:5px;color:#00477f;\" >New Message</h3>
				<div style=\"margin-left:15px;margin-bottom:10px;\">
					<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','otf=1','popup_id=".$this->popup_id."'".($this->ajax_vars['reply_hash'] ? ",'action=read','message_hash=".$this->ajax_vars['reply_hash']."'" : NULL).",'mailbox=".$this->mailbox."');\"><-- Back</a></small>
				</div>			
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#ffffff;width:25%;font-weight:bold;\" id=\"err1".$this->popup_id."\">
							To:
							<div style=\"padding-top:5px;font-weight:normal;\">
								[<small><a href=\"javascript:void(0);\" onClick=\"toggle_display('email_search_menu','block');\$('contact_email').focus();\" class=\"link_standard\">search</a></small>]
								<div id=\"email_search_menu\" class=\"function_menu\" >
									<div style=\"float:right;padding:5px\">
										<a href=\"javascript:void(0);\" onClick=\"toggle_display('email_search_menu','none');toggle_display('keyResults','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
									</div>
									<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;text-align:left\">Search Contacts:</div>
									<div class=\"function_menu_item\" >
										<div style=\"padding-bottom:5px;text-align:left\">Search for a contact:</div>
										".$this->form->text_box("name=contact_email",
																"value=",
																"autocomplete=off",
																"size=30",
																"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('alerts','contact_email','contact_email',1);}",
                                                                "onKeyUp=if(ka==false && this.value){key_call('alerts','contact_email','contact_email',1);}",				
																"onBlur=key_clear();")."
									</div>
							</div>
						</td>
						<td style=\"background-color:#ffffff;\">
    						".$this->form->text_area("name=to",
				                                     "value=".$reply_to,
                             						 "cols=55",
                             						 "rows=3")."
        				</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#ffffff;width:25%;font-weight:bold;\" id=\"err2".$this->popup_id."\">Subject:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=subject","value=".$reply_subject,"size=75","maxlength=255")."</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#ffffff;width:25%;font-weight:bold;\" id=\"err3".$this->popup_id."\">Message:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_area("name=msg","cols=75","rows=6","value=".$reply_msg)."</td>
					</tr>
				</table>";
			} elseif ($action == 'read') {
				$inner_tbl = $this->form->hidden(array('mailbox' => $this->mailbox))."
				<h3 style=\"margin-bottom:5px;color:#00477f;\" >Message Detail</h3>
				<div style=\"margin-left:15px;margin-bottom:10px;\">
					<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','otf=1','popup_id=".$this->popup_id."','mailbox=".$this->mailbox."','p=$p','order=$order','order_dir=$order_dir');\"><-- Back</a></small>
				</div>";	
				if ($valid) {
					if (!$this->current_message['received_on']) {
						$this->db->query("UPDATE `messages`
										  SET `received_by` = '".$this->current_hash."' , `received_on` = ".time()."
										  WHERE `message_hash` = '".$this->message_hash."'");
						$this->current_message['received_on'] = time();
						$this->current_message['received_by'] = $this->current_hash;
					}
					$move_menu = "<div class=\"function_menu_item\">".$this->form->select('move_to',$mbox_name,($this->current_message['deleted'] == 1 ? 'DELETED' : $this->current_message['mailbox']),$mbox_hash,"onChange=submit_form(\$('popup_id').form,'alerts','exec_post','refresh_form','action=doit_send','move_msg=1');","blank=1")."</div>";
					$inner_tbl .= $this->form->hidden(array('message_hash' => $this->message_hash, 'mailbox' => $this->mailbox))."
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
						<tr>
							<td style=\"vertical-align:top;background-color:#efefef;\">
								<div style=\"margin-left:15px;\">
									<div style=\"float:right;padding-right:25px;text-align:right;\">
										".($this->mailbox != 'SENT' ? 
											($this->current_message['sender_hash'] != 'SYSTEM' ? 
												"<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','popup_id=".$this->popup_id."','otf=1','action=new','p=$p','order=$order','order_dir=$order_dir','mailbox=".$this->mailbox."','reply_hash=".$this->message_hash."','reply_method=1');\"><small>reply</small></a>
												" : NULL)."&nbsp;|&nbsp;
												<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"".($this->mailbox == 'DELETED' ? "if(confirm('This will permanently delete this message. Do you want to continue?')){" : NULL)."submit_form(\$('popup_id').form,'alerts','exec_post','refresh_form','action=doit_send','rm=1'".($this->mailbox == 'DELETED' ? ",'delete=1'" : NULL).");".($this->mailbox == 'DELETED' ? "}" : NULL)."\"><small>delete</small></a>
												".($this->current_message['received_on'] > 0 ? "
													&nbsp;|&nbsp;
													<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"submit_form(\$('popup_id').form,'alerts','exec_post','refresh_form','action=doit_send','unread=1');\"><small>mark unread</small></a>" : NULL).($this->mailbox != 'SENT' ? 
													"&nbsp;|&nbsp;
													<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"position_element($('mailboxList'),115,650);toggle_display('mailboxList','block');\"><small>move</small></a>
													<div id=\"mailboxList\" class=\"function_menu\">
														<div style=\"float:right;padding:3px\">
															<a href=\"javascript:void(0);\" onClick=\"toggle_display('mailboxList','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
														</div>
														<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Move To Folder:</div>
														".$move_menu."
													</div>" : NULL)."
													<div style=\"padding-top:5px;\">
														<small>
															Received On: ".date(DATE_FORMAT." ".TIMESTAMP_FORMAT,$this->current_message['received_on'])."
															<br />
															By: ".user_name($this->current_message['received_by'])."
														</small>
													</div>" : NULL)."
									</div>
									<table>
										<tr>
											<td style=\"text-align:right;font-weight:bold\">Subject: </td>
											<td>".$this->current_message['subject']."</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold\">From: </td>
											<td>".($this->current_message['sender_hash'] == 'SYSTEM' ? 
												"DealerChoice" : $this->current_message['sender'])."</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold\">Date: </td>
											<td>".date(DATE_FORMAT." ".TIMESTAMP_FORMAT,$this->current_message['timestamp'])."</td>
										</tr>
										<tr>
											<td style=\"text-align:right;font-weight:bold\">To: </td>
											<td>".($this->current_message['group_hash'] ? 
												user_name($this->current_hash) : $this->current_message['recipient_name'])."</td>
										</tr>
									</table>
								</div>
							</td>
						</tr>
					</table>
					<p style=\"margin:1px;\"></p>
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
						<tr>
							<td style=\"vertical-align:top;background-color:#ffffff;padding:10px;\">
								<div style=\"margin-left:15px;\">
								".nl2br(stripslashes($this->current_message['message']))."
								</div>
							</td>
						</tr>
					</table>";
				} else {
					$inner_tbl .= "
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
						<tr>
							<td style=\"vertical-align:top;background-color:#efefef;text-align:center;padding:45px;font-weight:bold;\">
								<img src=\"images/alert.gif\" />&nbsp;Invalid message hash
								<div style=\"padding-top;10px;font-weight:normal;\">The may have been deleted or is no longer valid.</div>
							</td>
						</tr>
					</table>";
				}
			} else {
				$total = $this->total;
				$num_pages = ceil($this->total / SUB_PAGNATION_NUM);
				$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
				$start_from = SUB_PAGNATION_NUM * ($p - 1);
				
				$end = $start_from + SUB_PAGNATION_NUM;
				if ($end > $this->total)
					$end = $this->total;
				
				$order_by_ops = array("timestamp"		=>	"messages.timestamp",
									  "sender"			=>	"users.full_name");
		
				$inner_tbl = $this->fetch_messages($start_from,NULL,$order_by_ops[($order ? $order : "timestamp")],($order_dir ? $order_dir : "DESC"));
				
				$inner_tbl .= "
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td style=\"padding:0;\">
							 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
								<tr>
									<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\" class=\"smallfont\">
										<div style=\"float:right;font-weight:normal;padding-right:10px;\">".($this->total ? "
											".paginate_jscript($num_pages,$p,'sf_loadcontent','cf_loadcontent','alerts','message_win',$order,$order_dir,"'otf=1','popup_id=".$this->popup_id."','mailbox=".$this->mailbox."'") : NULL)."
											<div style=\"padding-top:5px;\">
												".$this->form->select("mailbox",$mbox_name,($this->ajax_vars['mailbox'] ? $this->ajax_vars['mailbox'] : 'INBOX'),$mbox_hash,"blank=1","onChange=agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','otf=1','popup_id=".$this->popup_id."','mailbox='+this.options[this.selectedIndex].value);")."
												<!--[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('alerts','sf_loadcontent','show_popup_window','manage_folders','popup_id=folder_win');\">edit</small>]-->
											</div>
										</div>".($this->total ? "
										Showing ".($start_from + 1)." - ".($start_from + MAIN_PAGNATION_NUM > $this->total ? $this->total : $start_from + MAIN_PAGNATION_NUM)." of ".$this->total." Message".($this->total > 1 ? "s" : NULL) : NULL)."
										<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">
											<a href=\"javascript:void(0);\" onClick=\"agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','popup_id=".$this->popup_id."','otf=1','action=new','p=$p','order=$order','order_dir=$order_dir','mailbox=".$this->mailbox."');\"><img src=\"images/new_mail.gif\" alt=\"New message\" border=\"0\" /></a>
											&nbsp;
										</div>
									</td>
								</tr>
								<tr class=\"thead\" style=\"font-weight:bold;\">
									<td >
										<a href=\"javascript:void(0);\" onClick=\"agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','popup_id=".$this->popup_id."','otf=1','p=$p','order=sender','order_dir=".($order == "sender" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."','mailbox=".$this->mailbox."');\" style=\"color:#ffffff;text-decoration:underline;\">
											From</a>
										".($order == 'sender' ? 
											"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
									</td>
									<td>
                                        <a href=\"javascript:void(0);\" onClick=\"agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','popup_id=".$this->popup_id."','otf=1','p=$p','order=recipient','order_dir=".($order == "recipient" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."','mailbox=".$this->mailbox."');\" style=\"color:#ffffff;text-decoration:underline;\">
                                            Recipient</a>
                                        ".($order == 'recipient' ? 
                                            "&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."									
									</td>
									<td >
										<a href=\"javascript:void(0);\" onClick=\"agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','popup_id=".$this->popup_id."','otf=1','p=$p','order=timestamp','order_dir=".($order == "timestamp" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."','mailbox=".$this->mailbox."');\" style=\"color:#ffffff;text-decoration:underline;\">
											Date</a>
										".($order == 'timestamp' ? 
											"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
									</td>
									<td >Subject</td>
								</tr>";
								for ($i = 0; $i < ($end - $start_from); $i++) {	
									$b++;
									
									$inner_tbl .= "
									<tr onClick=\"agent.call('alerts','sf_loadcontent','cf_loadcontent','message_win','otf=1','popup_id=".$this->popup_id."','action=read','message_hash=".$this->messages[$i]['message_hash']."','p=$p','order=$order','order_dir=$order_dir','mailbox=".$this->mailbox."');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\">
										<td nowrap style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;".(!$this->messages[$i]['received_on'] ? "font-weight:bold;" : NULL)."\" class=\"smallfont\">".($this->messages[$i]['sender_hash'] == 'SYSTEM' ? "DealerChoice" : stripslashes($this->messages[$i]['sender_name']))."&nbsp;</td>
										<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;".(!$this->messages[$i]['received_on'] ? "font-weight:bold;" : NULL)."\" class=\"smallfont\">".stripslashes($this->messages[$i]['recipient']).($this->messages[$i]['group_hash'] ? 
                                                " Group" : NULL)."&nbsp;
									    </td>
										<td nowrap style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;".(!$this->messages[$i]['received_on'] ? "font-weight:bold;" : NULL)."\" class=\"smallfont\">".date(DATE_FORMAT." ".TIMESTAMP_FORMAT,$this->messages[$i]['timestamp'])."&nbsp;</td>
										<td style=\"vertical-align:bottom;border-bottom:1px solid #cccccc;".(!$this->messages[$i]['received_on'] ? "font-weight:bold;" : NULL)."\" title=\"".htmlentities($this->messages[$i]['subject'],ENT_QUOTES)."\" class=\"smallfont\">".(strlen($this->messages[$i]['subject']) > 40 ? 
											substr($this->messages[$i]['subject'],0,37)."..." : $this->messages[$i]['subject'])."&nbsp;</td>
									</tr>
									<tr>";
								}
								if (!$this->total)
									$inner_tbl .= "
									<tr>
										<td colspan=\"4\" class=\"smallfont\">You have no messages.</td>
									</tr>";
								
					$inner_tbl .= "
							</table>
						</td>
					</tr>
				</table>";
			}
		
		$tbl_end .= "
			</div>
		</div>".$this->form->close_form();


		if ($this->ajax_vars['otf']) 
			$this->content['html']["msg_content_holder".$this->popup_id] = $inner_tbl;
		else
			$this->content['popup_controls']["cmdTable"] = $tbl.$inner_tbl.$tbl_end;	
	}
	
	function manage_folders() {
		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Manage Folders";
		$this->content['popup_controls']['popup_width'] = "200px";
		$this->content['popup_controls']['popup_height'] = "200";
		
		$tbl .= $this->form->form_tag().
		$this->form->hidden(array('popup_id' 		=> 	$this->popup_id))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<div>asdf</div>
		<div>";
			
		$this->content['popup_controls']['cmdTable'] = $tbl;
	}
}
























?>