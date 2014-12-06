<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed

class chat extends AJAX_library {

	public $current_hash;
	public $users;
	public $total;

	function chat() {
		global $db;
	
		$this->current_hash = $_SESSION['id_hash'];
		$this->db =& $db;
		$this->form = new form;
		
		$result = $this->db->query("SELECT users.id_hash , users.full_name
									FROM `session`
									LEFT JOIN users ON users.id_hash = session.id_hash
									WHERE session.id_hash != '".$this->current_hash."'");
		while ($row = $this->db->fetch_assoc($result))
			$this->users[$row['id_hash']] = stripslashes($row['full_name']);
		
		$this->total = count($this->users);
	}

	function doit() {
		$action = $_POST['action'];
		$this->popup_id = $_POST['popup_id'];	
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		if ($action == 'confirm') {
			$answer = $_POST['answer'];
			$chat_hash = $_POST['chat_hash'];
			$name = $_POST['name'];
			
			if ($answer == 'n') 
				$this->db->query("UPDATE `chat`
								  SET `closed` = 1 , `close_time` = ".time()."
								  WHERE `chat_hash` = '$chat_hash'");
			else 
				$this->content['jscript_action'] = "agent.call('chat','sf_loadcontent','show_popup_window','window','popup_id=chatwin','chat_hash=".$chat_hash."','name=$name');";
			
			$this->content['action'] = 'close';

			return;
		} elseif ($action == 'start') {
			$chat_user = $_POST['chat_user'];
			$chat_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			while (global_classes::key_exists('chat','chat_hash',$chat_hash))
				$chat_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			
			$users = new system_config();
			$users->fetch_user_record($chat_user);
			$this->db->query("INSERT INTO `chat`
							  (`timestamp` , `user_hash` ,`recipient_hash` , `chat_hash`)
							  VALUES (".time()." , '".$this->current_hash."' , '$chat_user' , '$chat_hash')");
							  
			$this->content['html']['chatbody'] = "<div style=\"font-weight:bold;padding-bottom:15px;\">Session started: ".date(TIMESTAMP_FORMAT)."</div>";
			$this->content['html']['chattitle'] = $users->current_user['full_name'];
			$this->content['value']['chat_hash'] = $_SESSION['chat_hash'] = $chat_hash;
			$this->content['action'] = 'continue';
			$this->content['jscript'][] = "toggle_display('chatfooter','block');";
			$this->content['focus'] = 'chatmsg';
			$this->content['jscript'][] = "startpolling('".$chat_hash."');";
			return;
		} elseif ($action == 'closeit') {
			$chat_hash = ($_POST['chat_hash'] ? $_POST['chat_hash'] : $_SESSION['chat_hash']);
			if ($chat_hash) 
				$this->db->query("UPDATE `chat`
								  SET `close_time` = ".time()." , `closed` = 1
								  WHERE `chat_hash` = '$chat_hash'");
			unset($_SESSION['chat_hash']);
			return;
		}
		$chat_hash = $_POST['chat_hash'];
		$message = $_POST['chatmsg'];
		
		$this->db->query("INSERT INTO `chat_content`
						  (`chat_hash` , `sender` , `message` , `post_time`)
						  VALUES ('$chat_hash' , '".$this->current_hash."' , '$message' , NOW())");
		$this->content['action'] = 'continue';
		$this->content['focus'] = 'chatmsg';
		$this->content['jscript'][] = "\$('chatmsg').value=''";
	}

	function poll() {
		$chat_last_id = $_SESSION['chat_last_id'];
		$chat_hash = $_POST['chat_hash'];
		if (!$chat_hash)
			return;
			
		$this->db->query("UPDATE `chat`
						  SET `timestamp` = ".time()."
						  WHERE `chat_hash` = '$chat_hash'");
						   	
		$result = $this->db->query("SELECT chat_content.sender as sender , chat_content.message , date_format(chat_content.post_time, '%h:%i') as post_time , 
									users.full_name , chat_content.obj_id , chat.closed , chat.close_time
									FROM `chat_content`
									LEFT JOIN `users` ON users.id_hash = chat_content.sender
									LEFT JOIN `chat` ON chat.chat_hash = chat_content.chat_hash
									WHERE chat_content.chat_hash = '$chat_hash' AND chat_content.obj_id >= ".($chat_last_id ? $chat_last_id : 0)."
									ORDER BY chat_content.obj_id ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			$id = $row['obj_id'];
			$txt .= $row['full_name']." (".$row['post_time'].") -> ".$row['message']."<br />\n";
			if ($row['sender'] != $_SESSION['id_hash'])
				$new_msg = 1;
				
			if ($row['closed']) {
				$txt .= "Session Closed - ".date(TIMESTAMP_FORMAT,$row['close_time'])."<br />\n";
				$this->content['jscript'][] = '$(\'primary\').disabled=1';
			}
		}
		if ($txt) {
			++$id;
			$_SESSION['chat_last_id'] = $id;
			$this->content['html_append']['chatbody'] = $txt;
			if ($new_msg)
				$this->content['jscript'][] = "playSound();";
			return;
		} else {
			$r = $this->db->query("SELECT chat.closed , chat.close_time , session.session_id
								   FROM `chat`
								   LEFT JOIN session ON session.id_hash = chat.user_hash
								   WHERE chat.chat_hash = '$chat_hash'");
			if ($this->db->result($r,0,'closed') == 1 || !$this->db->result($r,0,'session_id')) {
				$this->content['html_append']['chatbody'] = "<br /><strong>Session Closed - (".date(TIMESTAMP_FORMAT,$this->db->result($r,0,'close_time')).")</strong><br />\n";
				unset($_SESSION['chat_last_id']);
				$this->content['jscript'][] = "chatpoll.stop();";
			}
		}
	}
	
	function chat_prompt() {
		$chat_hash = $this->ajax_vars['chat_hash'];
		if (!$chat_hash)
			return;
			
		$result = $this->db->query("SELECT chat.chat_hash , users.full_name
									FROM `chat`
									LEFT JOIN `users` ON users.id_hash = chat.user_hash
									WHERE chat.chat_hash = '$chat_hash'
									LIMIT 1");
		if ($this->db->num_rows($result)) {
			$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
			$this->content['popup_controls']['popup_title'] = "Instant Message Request";
			$this->content['popup_controls']['popup_width'] = '290px';
			$this->content['popup_controls']['popup_height'] = '200px';
			$this->content['popup_controls']['popup_scrolling'] = '0';
			$this->content['popup_controls']['popup_resize'] = '0';
			$this->content['popup_controls']["cmdTable"] = "
			<table style=\"width:100%;height:100%;background-color:#ffffff;border:1px solid #cccccc;\">
				<tr>
					<td style=\"color:#16387c;text-align:center;font-weight:bold;\">
						".$this->db->result($result,0,'full_name')." is attempting to start a chat session with you. Do you want to proceed?
						<div style=\"padding-top:15px;\">
						".$this->form->form_tag().$this->form->hidden(array("popup_id" => $this->popup_id, "chat_hash" => $chat_hash, "name" => base64_encode($this->db->result($result,0,'full_name')))).
						$this->form->button("value=Yes","onClick=submit_form(this.form,'chat','exec_post','refresh_form','action=confirm','answer=y')")."&nbsp;&nbsp;".$this->form->button("value=No","onClick=submit_form(this.form,'chat','exec_post','refresh_form','action=confirm','answer=n')").
						$this->form->close_form()."
						</div>
					</td>
				</tr>
			</table>";
		}	
	}
	
	function window() {
		if ($chat_hash = $this->ajax_vars['chat_hash']) {			
			$r = $this->db->query("SELECT chat.timestamp , users.full_name , chat_content.* , session.session_id ,
								   date_format(chat_content.post_time, '%h:%i') as post_time
								   FROM `chat`
								   LEFT JOIN chat_content ON chat_content.chat_hash = chat.chat_hash
								   LEFT JOIN users ON users.id_hash = chat_content.sender
								   LEFT JOIN session ON session.id_hash = chat.user_hash
								   WHERE chat.chat_hash = '$chat_hash'");
			while ($row = $this->db->fetch_assoc($r)) {
				if ($row['message'])
					$txt .= $row['full_name']." (".$row['post_time'].") -> ".$row['message']."<br />\n";
				
				$name = $row['full_name']." ".date(TIMESTAMP_FORMAT,$row['timestamp']);
				$title = base64_decode($this->ajax_vars['name']);
				if (!$row['session_id']) {
					$txt .= "<br /><strong>Session closed (user is no longer logged in)</strong>";
					$closed = true;
					unset($_SESSION['chat_hash'],$_SESSION['chat_last_id']);
				}
				$_SESSION['chat_last_id'] = $row['obj_id'];
			}		
			$_SESSION['chat_last_id']++;	
		}
		
		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Instant Messaging";
		$this->content['popup_controls']['popup_width'] = '290px';
		$this->content['popup_controls']['popup_height'] = '350px';
		$this->content['popup_controls']['popup_scrolling'] = '0';
		$this->content['popup_controls']['popup_resize'] = '0';
		$this->content['popup_controls']['onclose'] = "if(chatpoll){chatpoll.stop();}agent.poll.start();agent.call('chat','exec_post','nothing','chat_hash=$chat_hash','action=closeit')";
		
		$this->content['jscript'][] = "		
		startpolling = function(chat_hash) {
			agent.poll.stop();
			chatpoll = new Ajax.PeriodicalUpdater('',
												   agent.url,{
												   parameters : 'aa_ajax=1&aa_sobj=chat&aa_sfunc=sf_loadcontent&aa_sfunc_args[]=poll&chat_hash='+chat_hash+'&fdata='+Form.serialize($('form_tag')),
												   frequency : 2,
												   onSuccess : function(resp){cf_loadcontent(resp.responseXML);}
												   });
			return;
		}";
		if ($chat_hash && !$closed) {
			$this->content['jscript'][] = "window.setTimeout('startpolling(\'".$chat_hash."\');',100);";
			$this->content['focus'] = "chatmsg";
		}
		$tbl = 
		$this->form->form_tag().$this->form->hidden(array('chat_hash' => $chat_hash, "popup_id" => $this->popup_id))."
		<div id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<div style=\"width:278px;background-color:#efefef;border-top:1px solid #9f9f9f;border-bottom:1px solid #9f9f9f;margin:5px 0;font-weight:bold;padding:3px\" id=\"chattitle\">
			".($chat_hash ? 
				$title : "Choose your recipient:")." 
			</div>
			<div style=\"width:273px;background-color:#efefef;border:1px solid #9f9f9f;margin:5px 0;height:280px;overflow:auto;padding:5px;\" id=\"chatbody\" >
			".($chat_hash ? 
				$txt : 
				(is_array($this->users) ? $this->form->select("chat_user",array_values($this->users),'',array_keys($this->users),'size=10','style=width:260px;background-color:#ffffff;','blank=1',"ondblclick=submit_form($('chat_hash').form,'chat','exec_post','refresh_form')") : "There are no users online at this time")."
				<div style=\"padding-top:10px;margin-left:5px;\">".(is_array($this->users) ? $this->form->button("value=Go","onClick=if(\$F('chat_user')){submit_form($('chat_hash').form,'chat','exec_post','refresh_form','action=start');window.setTimeout('\$(\'chatmsg\').focus()',500);}else{alert('Please select a recipient to continue.');}") : NULL)."</div>")."
			</div>
			<div style=\"width:283px;margin:5px 0;padding:0px;text-align:left;display:".($chat_hash ? "block" : "none").";\" id=\"chatfooter\">".
				$this->form->text_box("name=chatmsg","size=35","style=background-color:#ffffff;")."&nbsp;".$this->form->button("value=Go","id=primary","onClick=submit_form($('chat_hash').form,'chat','exec_post','refresh_form')")."
			</div>
		</div>".$this->form->close_form();
		
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}


 	


	
















}
?>