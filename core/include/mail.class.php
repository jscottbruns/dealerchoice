<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed

class mail extends AJAX_library {

	public $total_sent;
	public $total_failed;

	function mail($passedHash=NULL) {
		global $db;

		$this->form = new form;
		$this->db =& $db;
		$this->check = new Validator;

		if ($passedHash)
			$this->current_hash = $passedHash;
		else
			$this->current_hash = $_SESSION['id_hash'];

		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);
	}

	function doit() {
		global $err,$errStr;

		$action = $_POST['action'];
		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];

		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		if ($action)
			return $this->$action();
	}

	function doit_send($args=NULL) {
		//The calling class
		require_once(SITE_ROOT.'core/include/pdf.class.php');
		require_once(SITE_ROOT.'core/include/print.class.php');
		$cc = ($_POST['cc'] ? $_POST['cc'] : $args['cc']);
		$proposal_hash = ($_POST['proposal_hash'] ? $_POST['proposal_hash'] : $args['proposal_hash']);
		$type = ($_POST['type'] ? $_POST['type'] : $args['type']);
		$attached = ($_POST['attached'] ? $_POST['attached'] : $args['attached']);
		if ($attached) {
			if (!is_array($attached))
				$attached = array($attached);
			else {
				$c = count($attached);
				for ($i = 0; $i < $c; $i++) {
					if (!$attached[$c])
						unset($attached[$c]);
				}
				$attached = array_values($attached);
			}
		}
		if ($args['vendor_hash']) {
			$vendor_hash = $args['vendor_hash'];
			$vendors = new vendors($this->current_hash);
			$vendors->fetch_master_record($vendor_hash);

			if ($vendors->current_vendor['eorder_file'] && file_exists(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file'])) {
				include_once(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file']);
				$efile_class = explode(".",$vendors->current_vendor['eorder_file']);
				$efile_class_name = "eorder_".$efile_class[1];

				$efile_obj = new $efile_class_name($vendor_hash,$this->proposals->proposal_hash);
			}
		}

		if ($type == 'E' || ($type == 'EE' && is_object($efile_obj) && $efile_obj->send_method == 'EMAIL')) {
			$msg_subject = ($_POST['subject'] ? $_POST['subject'] : $args['subject']);
			$body = nl2br(($_POST['email_body'] ? $_POST['email_body'] : $args['email_body']));
			$file_vault = $_POST['file_vault_email'];
			$to = ($_POST['recipient_list'] ? $_POST['recipient_list'] : $args['recipient_list']);
			$to = explode("\n",$to);
			$to_cc = ($_POST['to_cc'] ? $_POST['to_cc'] : $args['to_cc']);
			if ($to_cc && !is_array($to_cc))
				$to_cc = explode("\n",$to_cc);

			$contact_list = $_POST['recipient_contacts_email'];
			if (is_array($to) && is_array($contact_list)) {
				$rcp = array_merge($to,$contact_list);
				$rcp = array_values($rcp);
			} elseif (!is_array($to))
				$rcp = array_values($contact_list);
			elseif (!is_array($contact_list))
				$rcp = array_values($to);

			$rcp = array_unique($rcp);
			if (in_array('',$rcp))
				unset($rcp[array_search('',$rcp)]);

			$rcp = array_values($rcp);
			if (!count($rcp)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "Please enter at least 1 recipient to continue.";
				return false;
			}

			for ($i = 0; $i < count($rcp); $i++) {
				preg_match("/(.*?) <(.*?)>/", $rcp[$i], $matches);
				$addr = ($matches[2] ? trim($matches[2]) : $rcp[$i]);
				$realname = ($matches[1] ? $matches[1] : NULL);
				if (!global_classes::validate_email(trim($addr))) {
					$e = true;
					$ef[] = $addr;
				}
			}
			unset($matches);

			for ($i = 0; $i < count($to_cc); $i++) {
				preg_match("/(.*?) <(.*?)>/", $to_cc[$i], $matches);
				$addr = ($matches[2] ? trim($matches[2]) : $to_cc[$i]);
				$realname = ($matches[1] ? $matches[1] : NULL);
				if (!global_classes::validate_email(trim($addr))) {
					$e = true;
					$ef[] = $addr;
				}
			}
			if ($e) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = count($ef)." email recipient".(count($ef) > 1 ? "s are" : " is")." invalid. The invalid recipient".(count($ef) > 1 ? "s are" : " is").":<br />".implode("; ",$ef);
				return false;
			}

		} elseif ($type == 'F') {
			$msg_subject = ($_POST['subject'] ? $_POST['subject'] : $args['subject']);
			$body = ($_POST['fax_body'] ? $_POST['fax_body'] : $args['fax_body']);
			$file_vault = $_POST['file_vault_fax'];
			$to_name = ($_POST['to'] ? $_POST['to'] : $args['to']);
			$to_addr = ($_POST['fax'] ? $_POST['fax'] : $args['fax']);
			if (!$this->check->is_phone($to_addr)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "The fax number you entered is invalid. Please enter a valid fax number in this format: (123-123-1234)";
				return false;
			}
		}
		$date = ($_POST['date'] ? $_POST['date'] : $args['date']);
		if ($args)
			$file_vault = $args['file_vault'];
		if ($body) {
			$alt_body = strip_tags($body);
			$alt_body = str_replace("<p>","\n",$alt_body);
		}
		$users = new system_config();
		$users->fetch_user_record($_SESSION['id_hash']);

		$from_addr = $users->current_user['email'];
		$from_name = mysql_real_escape_string($users->current_user['full_name']);

		$auto_append = fetch_sys_var('company_docs_auto_append');
		$auto_append = explode("|",$auto_append);

		if ($auto_append) {
			for ($z = 0; $z < count($auto_append); $z++) {
				$auto_append_split = explode(":",$auto_append[$z]);
				for ($v = 0; $v < count($auto_append_split); $v++) {
					if ($auto_append_split[$v] == 'proposals')
						$auto_append_split[$v] = 'proposal';

				}
				$auto_append[$z] = implode(":",$auto_append_split);
			}
		}

		$open_files = array();
		$open_file_names = array();

		for ($z = 0; $z < count($attached); $z++) {
			if ($attached[$z]) {
				list($class,$hash2,$attached_file) = explode("|",$attached[$z]);
					unset($print_type, $print_pref, $template_hash, $print_pref_hash);
					switch ($class) {
						case 'customer_invoice':
						$print_type = "invoice_print";
						break;

						case 'delivery_ticket':
						$print_type = "delivery_ticket_print";
						break;

						case 'proposal':
						$print_type = "proposal_print";
						break;
					}
					// Get preferences if template/print prefs can exist.
					if ($print_type) {
						$print_pref = fetch_user_data($print_type);
						$print_pref = __unserialize(stripslashes($print_pref));
						$template_hash = $print_pref['default_template'];
						$print_pref_hash = $print_pref['default_prefs'];
					}
				$print_it = new print_it($class,$template_hash,$print_pref_hash);
				$isFax = ($type == 'F' ? 1 : 0);
				$isEmail = ($type == 'E' ? 1 : 0);
				// Set the logo
				if ($args['print_logo'])
					$print_it->set_logo($args['print_logo'],$args['use_logo'],$isFax);
				elseif ($isFax || $isEmail)
					$print_it->set_logo($print_it->img,$print_it->img,$isFax);

				$print_it->{$class}($proposal_hash,$hash2);

				//See if there's anything to auto append
				if ($class && !$auto_append_class[$class]) {
					for ($i = 0; $i < count($auto_append); $i++) {
						if (@in_array($class,explode(":",$auto_append[$i])))
							$auto_append_class[$class][] = $i;

					}
				}

				$file = tempnam(UPLOAD_DIR,'pdf');
				$file_name = $attached_file;
				$subject = $attached_file;

				$fh = fopen($file,'wb');
				$open_files[] = $file;
				$open_file_names[] = $file_name;
				$cc[] = $class;
				$item_hash[] = $hash2;

				$print_it->pdf->addInfo('Title',$subject);
				$print_it->pdf->addInfo('Author',$from_name);
				$print_it->pdf->addInfo('Producer',$from_name);
				$print_it->pdf->addInfo('CreationDate',$date);
				if (!$fh) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = "The directory ".SITE_ROOT."core/tmp does not have the necessary priviliges to write. Please ensure the user running the web server has write priviliges to this directory.";
					return false;
				}
				fwrite($fh,$print_it->pdf->ezOutput());
				fclose($fh);
				chmod($file,0755);
			}
		}

		if ($type == 'EE') {
			list($class,$hash2,$file_name) = explode("|",($_POST['eorder'] ? $_POST['eorder'] : $args['eorder']));
			$fh = fopen(UPLOAD_DIR.$file_name,'wb');
			$open_files[] = UPLOAD_DIR.$file_name;
			$open_file_names[] = $file_name;

			$po = new purchase_order($this->current_hash);
			$po->fetch_po_record($proposal_hash,$hash2);

			$cc[] = $class;
			$item_hash[] = $hash2;

			fwrite($fh,$po->current_po['eorder_data']);
			fclose($fh);
			chmod(UPLOAD_DIR.$file_name,0755);
		}

		if ($type == 'F')
			global $interfax_file_types;
		else {
			for ($i = 0; $i < count($rcp); $i++) {
				preg_match("/(.*?) <(.*?)>/", $rcp[$i], $matches);
				$email_address[] = ($matches[2] ? trim($matches[2]) : $rcp[$i]);
				$email_name[] = ($matches[1] ? $matches[1] : NULL);
			}
		}
		if ($file_vault && !is_array($file_vault))
			$file_vault = array($file_vault);

		if (count($file_vault)) {
			$doc_vault = new doc_vault();
			for ($i = 0; $i < count($file_vault); $i++) {
				if ($file_vault[$i]) {
					$doc_vault->fetch_doc_record($file_vault[$i]);
					//if ($type == 'E' && $doc_vault->current_doc['filesize'] > 1048576) {
					//	$dhash = md5(global_classes::get_rand_id(32,"global_classes"));
					//	while (global_classes::key_exists('doc_vault','download_hash',$dhash))
					//		$dhash = md5(global_classes::get_rand_id(32,"global_classes"));
					//
					//	$this->db->query("UPDATE `doc_vault`
					//					  SET `download_hash` = '".$dhash."'
					//					  WHERE `doc_hash` = '".$doc_vault->doc_hash."'");
					//
					//	$link_attach_html[] = "<a href=\"".LINK_ROOT."core/download.php?dhash=".$dhash."\" target=\"_blank\">".$doc_vault->current_doc['file_name']."</a>";
					//	$link_attach_txt[] = $doc_vault->current_doc['file_name']." - ".LINK_ROOT."core/download.php?dhash=".$dhash;
					//} else {
						$path = tempnam(UPLOAD_DIR,"attach");
						$open_files[] = $path;
						if ($type == 'F' && !in_array(strtoupper(strrev(substr(strrev(basename($doc_vault->current_doc['file_name'])),0,strpos(strrev(basename($doc_vault->current_doc['file_name'])),'.')))),$interfax_file_types)) {
							$this->content['error'] = 1;
							$this->content['form_return']['feedback'] = "Unallowed file format! The file (".basename($doc_vault->current_doc['file_name']).") is not an allowed file type for fax transmission. Allowed types are: ".implode(", ",$interfax_file_types).".";
							return false;
						}
						$open_file_names[] = basename($doc_vault->current_doc['file_name']);
						$fh = fopen($path,'wb');
						fwrite($fh,$doc_vault->fetch_data($doc_vault->doc_hash));
						fclose($fh);
						chmod($path,"0755");
					//}
				}
			}
		}
		if ($auto_append_class) {
			$docs = fetch_sys_var('company_docs');
			$docs = explode("|",$docs);
			while (list($aa_class,$aa_docs) = each($auto_append_class)) {
				for ($i = 0; $i < count($aa_docs); $i++) {
					$aa_file = basename($docs[$aa_docs[$i]]);
					$path = tempnam(UPLOAD_DIR,"attach");
					$open_files[] = $path;
					//if ($type == 'F' && !in_array(strtoupper(strrev(substr(strrev(basename($aa_file)),0,strpos(strrev(basename($aa_file)),'.')))),$interfax_file_types)) {
						//$this->content['error'] = 1;
						//$this->content['form_return']['feedback'] = "Unallowed file format! The file (".$aa_file.") is not an allowed file type for fax transmission. Allowed types are: ".implode(", ",$interfax_file_types).".";
						//return false;
					//}
					$open_file_names[] = $aa_file;
					$fh = fopen($path,'wb');
					fwrite($fh,fread(fopen(SITE_ROOT.'core/images/docs/'.$aa_file,'r'),filesize(SITE_ROOT.'core/images/docs/'.$aa_file)));
					fclose($fh);
					chmod($path,"0755");
				}
			}

		}
		if (!$open_files) {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "Please select at least 1 file to send.";
			return false;
		}
		if (!$msg_subject)
			$msg_subject = ($proposal->current_proposal ? "Proposal ".$proposal->current_proposal['proposal_no']." files" : "Files")." from $from_name";

		$AltBody = $alt_body.($type == 'E' && $link_attach_txt ?
			"\nAttached Files\n---------------------------\n".implode("\n",$link_attach_txt) : NULL);
		if ($type == 'E')
			$mail_body = $body;

		if ($type == 'EE' && is_object($efile_obj)) {
			if ($efile_obj->send_method == 'EMAIL')
				$type = 'E';
			else
				$type = $efile_obj->send_method;
		}
		$print_pref_hash = $args['use_prefs'];
		$template_hash = $args['use_template'];

		$this->db->query("INSERT INTO `message_queue`
						  (`timestamp` , `cc` , `hash` , `hash2` , `vendor_hash` , `user` , `type` , `date` , `to_addr` , `to_name` , `to_cc` , `from_addr` , `from_name` , `txt_body` , `html_body` , `subject` , `attachments` , `file_name`,  `print_pref_hash` , `template_hash`)
						  VALUES (".time()." , '".@implode("|",$cc)."' , '$proposal_hash' , '".@implode("|",$item_hash)."' , ".($vendor_hash ? "'$vendor_hash'" : "NULL")." , '".$this->current_hash."' , '$type' , '$date' , '".($type == 'E' || $type == 'EE' ? @implode("|",$email_address) : $to_addr)."' , '".($type == 'E' ? @implode("|",$email_name) : $to_name)."' , '".(is_array($to_cc) ? implode("|",$to_cc) : $to_cc)."' , '$from_addr' , '$from_name' , '".($type == 'E' ? base64_encode($AltBody) : NULL)."' , '".($type == 'E' ? base64_encode($mail_body) : base64_encode($body))."' , '$msg_subject' , '".@implode("|",$open_files)."' , '".@implode("|",$open_file_names)."' , '$print_pref_hash' , '$template_hash')");

		$this->content['action'] = 'close';
		$this->content['page_feedback'] = "Message has been added to the outgoing mail/fax queue.";
		return true;
	}

	function doit_rm() {
		$id = $_POST['id'];
		$kill_po = $_POST['kill_po'];
		$proposal_hash = $_POST['proposal_hash'];
		$po_hash = $_POST['po_hash'];
		$this->popup_id = $_POST['popup_id'];

		$result = $this->db->query("SELECT * FROM `message_queue`
						  WHERE `obj_id` = '$id'");
		$row = $this->db->fetch_assoc($result);
		if ($row['cc'] == 'purchase_order' && $row['vendor_hash']) {
			 $this->db->query("UPDATE `purchase_order`
			 				   SET `status` = '5', `transmit_timestamp` = ".time()."
						  	   WHERE `po_hash` = '".$row['hash2']."'");
		}

		$this->db->query("DELETE FROM `message_queue`
						  WHERE `obj_id` = '$id'");

		if ($kill_po)
			$this->content['jscript_action'] = "agent.call('purchase_order','sf_loadcontent','show_popup_window','edit_po','proposal_hash=$proposal_hash','po_hash=$po_hash','popup_id=".$this->popup_id."')";

		$this->content['action'] = 'close';
		$this->content['page_feedback'] = "Message has been removed from the queue.";
		return true;
	}

	function empty_message_queue() {
		global $config_private;

		$this->total_sent = 0;
		$this->total_failed = 0;

		if (!defined('SMTP_HOST'))
			define('SMTP_HOST',$config_private['SMTP_HOST']);
		if (!defined('SMTP_USER'))
			define('SMTP_USER',$config_private['SMTP_USER']);
		if (!defined('SMTP_PASS'))
			define('SMTP_PASS',$config_private['SMTP_PASS']);
		if (!defined('NUSOAP_USER'))
			define('NUSOAP_USER',$config_private['NUSOAP_USER']);
		if (!defined('NUSOAP_PASS'))
			define('NUSOAP_PASS',$config_private['NUSOAP_PASS']);

		$result = $this->db->query("SELECT *
									FROM `message_queue`");

		while ($row = $this->db->fetch_assoc($result)) {
			if ($row['type'] == 'E' || $row['type'] == 'EE') {
				$mail = new PHPMailer();
				//if ($_SESSION['user_name'] == 'jsbruns')
					//$mail->IsHTML(true);

				$rcp = explode("|",$row['to_addr']);

				for ($i = 0; $i < count($rcp); $i++) {
					if (trim($rcp[$i]))
						$mail->AddAddress($rcp[$i]);
				}
				$to_cc = explode("|",$row['to_cc']);
				for ($i = 0; $i < count($to_cc); $i++) {
					if (trim($to_cc[$i]))
						$mail->AddCC($to_cc[$i]);
				}
				$mail->Body = base64_decode($row['html_body']);
				$mail->AltBody = base64_decode($row['txt_body']);
				$mail->From = $row['from_addr'];
				$mail->FromName = $row['from_name'];

				$mail->IsSMTP();
				$mail->Host = SMTP_HOST;
				$mail->SMTPAuth = true;
				$mail->Username = SMTP_USER;
				$mail->Password = SMTP_PASS;
				$mail->Subject = $row['subject'];

				$cc  = explode("|",$row['cc']);
				$hash2  = explode("|",$row['hash2']);
				$attached  = explode("|",$row['attachments']);
				$file_name  = explode("|",$row['file_name']);
				for ($i = 0; $i < count($attached); $i++)
					$mail->AddAttachment($attached[$i],$file_name[$i]);

				$mail_result = $mail->Send();

				if ($mail_result == false && $row['queue_attempts_remaining'] > 1) {
					$this->total_failed++;
					$this->db->query("UPDATE `message_queue`
									  SET `queue_attempts_remaining` = message_queue.queue_attempts_remaining - 1
									  WHERE `obj_id` = '".$row['obj_id']."'");
				}
				if ($mail_result == true || $q_attempts <= 1) {
					if ($mail_result == false && $q_attempts <= 1) {
						$this->total_failed++;
						$failed = true;
						$message_hash = md5(global_classes::get_rand_id(32,"global_classes"));
						while (global_classes::key_exists('messages','message_hash',$message_hash))
							$message_hash = md5(global_classes::get_rand_id(32,"global_classes"));

						// Added for #1227
						switch ($row['cc']) {
							case 'proposal':
							$link = "agent.call('mail','sf_loadcontent','show_popup_window','agent','popup_id=line_item','proposal_hash=".$row['hash']."','tab_to=tcontent2');";
							break;

							case 'purchase_order':
							$link = "agent.call('purchase_order','sf_loadcontent','show_popup_window','edit_po','proposal_hash=".$row['hash']."','po_hash=".$row['hash2']."','popup_id=line_item')";
							break;

							case 'customer_invoice':
							$link = "agent.call('purchase_order','sf_loadcontent','show_popup_window','edit_invoice','proposal_hash=".$row['hash']."','invoice_hash=".$row['hash2']."','popup_id=line_item')";
							break;

							case 'doc_vault':
							$link = "agent.call('mail','sf_loadcontent','show_popup_window','agent','popup_id=line_item','proposal_hash=".$row['hash']."','tab_to=tcontent2');";
							break;
						}
						$msg = "Hello-<br /><br />We're sorry, but an outgoing email has failed after 4 attempts. The subject of this email is '".stripslashes($row['subject'])."' and was sent to: <br /><br />".@implode("",$rcp)."<br /><br />Please note that this email may be delivered later at the discretion of the mail server so you may want to check with the recipient or send again.<br /><br />".($link ? "You may see more details of the email by <a href=\"javascript:void(0);\" onClick=\"".$link."\" class=\"link_standard\">clicking here.</a>" : NULL);
						$this->db->query("INSERT INTO `messages`
										  (`timestamp` , `message_hash` , `sender_hash` , `recipient_hash` , `subject` , `message`)
										  VALUES(".time()." , '$message_hash' , 'SYSTEM' , '".$row['user']."' , 'Email transmission failed' , '".addslashes($msg)."')");
					} else
                        $this->total_sent++;

					for ($i = 0; $i < count($attached); $i++)
						unlink($attached[$i]);

					$this->db->query("INSERT INTO `message_log`
									  (`timestamp` , `type` , `cc` , `hash` , `hash2` , `user` , `result` , `destination` , `to_cc` , `subject`)
									  VALUES (".time()." , 'E' , '".$row['cc']."' , '".$row['hash']."' , '".$row['hash2']."' , '".$row['user']."' , '".($mail_result == false || $failed ? "-1" : "0")."' , '".@implode("; ",$rcp)."' , '".@implode("; ",$to_cc)."' , '".addslashes($row['subject'])."')");

					$this->db->query("DELETE FROM `message_queue`
									  WHERE `obj_id` = '".$row['obj_id']."'");

					for ($i = 0; $i < count($hash2); $i++) {
						switch ($cc[$i]) {
							case 'purchase_order':
							$this->db->query("UPDATE `purchase_order`
											  SET `status` = ".($mail_result == false ? 0 : 3)." , `transmit_timestamp` = ".time()."
											  WHERE `po_hash` = '".$hash2[$i]."'");
							break;

							case 'customer_invoice':
							$this->db->query("UPDATE `customer_invoice`
											  SET `status` = ".($mail_result == false ? 0 : 3)." , `transmit_timestamp` = ".time()."
											  WHERE `invoice_hash` = '".$hash2[$i]."'");
							break;
						}
					}
				}
			//Fax
			} elseif ($row['type'] == 'F') {
				//Build the pdf cover page
				$print_pref = fetch_user_data("proposal_print");
				$print_pref = unserialize(stripslashes($print_pref));

				$img = $print_pref['use_logo'];
				if (!$img) {
					$img = fetch_sys_var('company_logo');
					$img = explode("|",$img);
					$img = $img[0];
				}
				if ($img)
					list($w,$h) = getimagesize($img);

				$fax_no = str_replace("-","",$row['to_addr']);
				$fax_no = str_replace("(","",$fax_no);
				$fax_no = str_replace(")","",$fax_no);
				$fax_no = trim(str_replace(" ","",$fax_no));
				$to_name = $row['to_name'];
				$from_name = $row['from_name'];
				$from_addr = $row['from_addr'];
				$msg  = base64_decode($row['html_body']);
				$subject = $row['subject'];
				$attached  = explode("|",$row['attachments']);
				$attached_name  = explode("|",$row['file_name']);

				$ftype = array();
				$fsize = array();
				unset($data,$fdata);
				for ($i = 0; $i < count($attached); $i++) {
					$ftype[] = strtoupper(strrev(substr(strrev(basename($attached_name[$i])),0,strpos(strrev(basename($attached_name[$i])),'.'))));
					$fdata = file_get_contents($attached[$i]);
					$fsize[] = strlen($fdata);
					$data .= $fdata;
				}

				require_once('nusoap/lib/nusoap.php');
				$client = new soapclient2("http://ws.interfax.net/dfs.asmx?wsdl", true);
				$params = array();
				$params[] = array('Username'         => NUSOAP_USER,
								  'Password'         => NUSOAP_PASS,
								  'FaxNumbers'       => (!ereg("\+",$fax_no) ? "+1" : NULL).$fax_no,
								  'Contacts'		 => $row['to_name'],
								  'FilesData'		 =>	base64_encode($data),
								  'FileTypes'		 =>	implode(";",$ftype),
								  'FileSizes'		 =>	implode(";",$fsize),
								  'Postpone'		 =>	"2001-04-25T20:31:00-04:00",
								  'IsHighResolution' => 0,
								  'CSID'			 =>	'SelectionSheet.com',
								  'Subject'          => $subject,
								  'ReplyAddress'	 => $_SESSION['my_email']
								  );

				$fax_r = $client->call("SendfaxEx_2", $params);
				$fax_r_code = $fax_r['SendfaxEx_2Result'];

				if ($fax_r_code >= 0) {
					$this->total_sent++;
					for ($i = 0; $i < count($attached); $i++)
						unlink($attached[$i]);

					$this->db->query("INSERT INTO `message_log`
									  (`timestamp` , `cc` , `hash` , `hash2` , `user` , `transaction_id` , `destination` , `subject` , `attempts_remaining`)
									  VALUES (".time()." , '".$row['cc']."' , '".$row['hash']."' , '".$row['hash2']."' , '".$row['user']."' , '$fax_r_code' , '".$row['to_addr']."' , '".addslashes($subject)."' , 3)");

					$this->db->query("DELETE FROM `message_queue`
									  WHERE `obj_id` = '".$row['obj_id']."'");

					switch ($row['cc']) {
						case 'purchase_order':
						$this->db->query("UPDATE `purchase_order`
										  SET `status` = 2 , `transmit_timestamp` = ".time()."
										  WHERE `po_hash` = '".$row['hash2']."'");
						break;

						case 'customer_invoice':
						$this->db->query("UPDATE `customer_invoice`
										  SET `status` = 2 , `transmit_timestamp` = ".time()."
										  WHERE `invoice_hash` = '".$row['hash2']."'");
						break;
					}
				} else {
					$this->total_failed++;
					//TODO: Write to an error log
					$this->db->query("UPDATE `message_queue`
									  SET `error` = 1 , `transaction_id` = '".$fax_r_code."'
									  WHERE `obj_id` = '".$row['obj_id']."'");
				}
			} elseif ($row['type'] == 'SOAP' && $row['vendor_hash']) {
				$vendors = new vendors();
				$vendors->fetch_master_record($row['vendor_hash']);
				if ($vendors->current_vendor['eorder_file'] && file_exists(SITE_ROOT.'core/images/e_profile/'.$vendors->current_vendor['eorder_file'])) {
					include_once(SITE_ROOT.'core/images/e_profile/'.$eorder_file);
					$efile_class = explode(".",$eorder_file);
					$efile_class_name = "eorder_".$efile_class[1];

					$efile_obj = new $efile_class_name($row['vendor_hash'],$row['hash']);
					$efile_obj->send_purchase_order($row['attachments']);
				}
			}
		}

		//For faxes recently sent, go get the status
		require_once(SITE_ROOT.'core/include/nusoap/lib/nusoap.php');
		$client = new soapclient2("http://ws.interfax.net/dfs.asmx?wsdl", true);
		$result = $this->db->query("SELECT *
									FROM `message_log`
									WHERE `type` = 'F' AND `timestamp` < ".(time())." AND (`result` = '' OR (`result` != 0 AND `attempts_remaining` > 0))");
		while ($row = $this->db->fetch_assoc($result)) {
			$trans_id = $row['transaction_id'];
			$obj_id = $row['obj_id'];
			unset($params,$fax_result);
			$params[] = array('Username'  	=> NUSOAP_USER,
							  'Password'   	=> NUSOAP_PASS,
							  'Verb'		=> 'EQ',
							  'VerbData'	=> $trans_id,
							  'MaxItems'	=> 1);
			$fax_result = $client->call("FaxQuery", $params);

			$dest = $fax_result['FaxQueryResult']['FaxItemEx']['DestinationFax'];
			$pages = $fax_result['FaxQueryResult']['FaxItemEx']['PagesSent'];
			$subject = $fax_result['FaxQueryResult']['FaxItemEx']['Subject'];
			$status = $fax_result['FaxQueryResult']['FaxItemEx']['Status'];
			if ($status != 0) {
				global $interfax_result_code;
				$attempts_remaining = $fax_result['FaxQueryResult']['FaxItemEx']['RetriesToPerform'] - $fax_result['FaxQueryResult']['FaxItemEx']['TrialsPerformed'];
				if ($attempts_remaining == 0) {
					$message_hash = md5(global_classes::get_rand_id(32,"global_classes"));
					while (global_classes::key_exists('messages','message_hash',$message_hash))
						$message_hash = md5(global_classes::get_rand_id(32,"global_classes"));

					switch ($row['cc']) {
						case 'proposal':
						$link = "agent.call('mail','sf_loadcontent','show_popup_window','agent','popup_id=line_item','proposal_hash=".$row['hash']."','tab_to=tcontent2');";
						break;

						case 'purchase_order':
						$link = "agent.call('purchase_order','sf_loadcontent','show_popup_window','edit_po','proposal_hash=".$row['hash']."','po_hash=".$row['hash2']."','popup_id=line_item')";
						break;

						case 'customer_invoice':
						$link = "agent.call('purchase_order','sf_loadcontent','show_popup_window','edit_invoice','proposal_hash=".$row['hash']."','invoice_hash=".$row['hash2']."','popup_id=line_item')";
						break;

						case 'doc_vault':
						$link = "agent.call('mail','sf_loadcontent','show_popup_window','agent','popup_id=line_item','proposal_hash=".$row['hash']."','tab_to=tcontent2');";
						break;
					}
					$this->total_failed++;
					$msg = "Hello-<br /><br />We're sorry, but an outgoing fax to #".$row['destination']." has failed to be delivered. The returned error code is ".$status." (".($interfax_result_code[$status] ? $interfax_result_code[$status] : "General delivery failure").").<br /><br />".($link ? "You may resend the fax by <a href=\"javascript:void(0);\" onClick=\"".$link."\" class=\"link_standard\">clicking here.</a>" : NULL);

					$this->db->query("INSERT INTO `messages`
									  (`timestamp` , `message_hash` , `sender_hash` , `recipient_hash` , `subject` , `message`)
									  VALUES(".time()." , '$message_hash' , 'SYSTEM' , '".$row['user']."' , 'Fax transmission failed' , '".addslashes($msg)."')");
				}
			}
			if ($trans_id)
				$this->db->query("UPDATE `message_log`
								  SET `timestamp` = ".time()." , `result` = '$status' , `destination` = '$dest' , `pages` = '$pages', `subject` = '".addslashes($subject)."' , `attempts_remaining` = '$attempts_remaining'
								  WHERE `transaction_id` = '".$trans_id."'");

			switch ($row['cc']) {
				case 'purchase_order':
				$this->db->query("UPDATE `purchase_order`
								  SET `status` = ".($status == 0 ? "3" : ($attempts_remaining > 0 ? "2" : "0"))."
								  WHERE `po_hash` = '".$row['hash2']."'");
				break;

				case 'customer_invoice':
				$this->db->query("UPDATE `customer_invoice`
								  SET `status` = ".($status == 0 ? "3" : ($attempts_remaining > 0 ? "2" : "0"))."
								  WHERE `invoice_hash` = '".$row['hash2']."'");
				break;
			}
		}
	}

	function fetch_message_list($proposal_hash) {
		$result = $this->db->query("SELECT *
									FROM `message_queue`
									WHERE `hash` = '$proposal_hash'
									ORDER BY obj_id DESC");
		while ($row = $this->db->fetch_assoc($result))
			$log[] = $row;

		$result = $this->db->query("SELECT *
									FROM `message_log`
									WHERE `hash` = '$proposal_hash'
									ORDER BY obj_id DESC");
		while ($row = $this->db->fetch_assoc($result))
			$log[] = $row;

		return $log;
	}

	function format_fax_no($no) {
		if (substr($no,3,1) == '-')
			return $no;

		$no = substr($no,3);
		return substr($no,0,3)."-".substr($no,3,3)."-".substr($no,6);
	}

	function agent() {
		global $interfax_result_code;

		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		if ($this->ajax_vars['proposal_hash']) {
			$cc = $this->ajax_vars['cc'];
			$proposal = new proposals($_SESSION['id_hash']);
			$hash = $this->ajax_vars['proposal_hash'];
			$valid = $proposal->fetch_master_record($hash);
			$hidden = $this->form->hidden(array('proposal_hash' => $proposal->proposal_hash));
			$file_attach[] = $this->form->checkbox("name=attached[]","value=proposal|".$proposal->current_proposal['proposal_hash']."|Proposal_".$proposal->current_proposal['proposal_no'].".pdf",($cc == 'proposals' && $proposal->current_proposal['final'] ? "checked" : (!$proposal->current_proposal['final'] ? "disabled" : NULL)),(!$proposal->current_proposal['final'] ? "title=Before you are able to fax or email this proposal, it must be finalized." : NULL))."&nbsp;Proposal_".$proposal->current_proposal['proposal_no'].".pdf";
			if ($proposal->current_proposal['customer_contact']) {
				$cust = new customers($this->current_hash);
				$cust->fetch_contact_record($proposal->current_proposal['customer_contact']);
				$to_name = $cust->current_contact['contact_name'];
				$to_addr = $cust->current_contact['contact_fax'];
			}
			$customers = new customers($_SESSION['id_hash']);
			$customers->fetch_master_record($proposal->current_proposal['customer_hash']);
			if ( $customers->total_contacts ) {

				$customers->fetch_contacts( array(
					'start_from'	=>	0,
					'end'			=>	$customers->total_contacts,
					'order_by'		=>	"t1.contact_name",
				) );
				for ($i = 0; $i < count($customers->customer_contacts); $i++) {
					if ($customers->customer_contacts[$i]['contact_email']) {
						$contact_email_name[] = $customers->customer_contacts[$i]['contact_name']." <".$customers->customer_contacts[$i]['contact_email'].">";
						$contact_email[] = $customers->customer_contacts[$i]['contact_email'];
					}
					if ($customers->customer_contacts[$i]['contact_fax']) {
						$contact_fax_name[] = $customers->customer_contacts[$i]['contact_name']." (".$customers->customer_contacts[$i]['contact_fax'].")";
						$contact_fax[] = $customers->customer_contacts[$i]['contact_fax'];
					}
				}

			}
			$from = $_SESSION['my_name'];
			if (count($contact_email_name))
				$multi_email_select = $this->form->select("recipient_contacts_email",$contact_email_name,NULL,$contact_email,"multiple","size=4","style=width:250px;","blank=1");
			if (count($contact_fax_name))
				$multi_fax_select = $this->form->select("recipient_contacts_fax",$contact_fax_name,NULL,$contact_fax,"multiple","size=4","style=width:250px;","blank=1");

			$doc_vault = new doc_vault();
			$doc_vault->fetch_files($proposal->proposal_hash);

			for ($i = 0; $i < $doc_vault->total; $i++) {
				$file_name[] = $doc_vault->proposal_docs[$i]['file_name'].($doc_vault->proposal_docs[$i]['file_descr'] ? (strlen($doc_vault->proposal_docs[$i]['file_descr']) > 15 ? substr($doc_vault->proposal_docs[$i]['file_descr'],0,15)."..." : $doc_vault->proposal_docs[$i]['file_descr']) : NULL);
				$file_hash[] = $doc_vault->proposal_docs[$i]['doc_hash'];
			}

			if (count($file_name)) {
				$file_vault_email = $this->form->select("file_vault_email[]",$file_name,'',$file_hash,"multiple","blank=1","size=4","style=width:200px;");
				$file_vault_fax = $this->form->select("file_vault_fax[]",$file_name,'',$file_hash,"multiple","blank=1","size=4","style=width:200px;");
			}

			//Get all the invoices
			$customer_invoice = new customer_invoice($this->current_hash);
			$customer_invoice->set_values( array(
    			"proposal_hash"  =>  $proposal->proposal_hash
			) );
			$customer_invoice->customer_invoice();
			$customer_invoice->fetch_customer_invoices(0,$customer_invoice->total);
			for ($i = 0; $i < $customer_invoice->total; $i++) {
				if ($customer_invoice->invoice_info[$i]['type'] == 'I')
					$file_attach[] = $this->form->checkbox("name=attached[]","value=customer_invoice|".$customer_invoice->invoice_info[$i]['invoice_hash']."|Customer_Invoice_".$customer_invoice->invoice_info[$i]['invoice_no'].".pdf",($cc == 'customer_invoice' && $this->ajax_vars['invoice_hash'] == $customer_invoice->invoice_info[$i]['invoiceo_hash'] ? "checked" : NULL))."&nbsp;Customer_Invoice_".$customer_invoice->invoice_info[$i]['invoice_no'].".pdf";
			}
			//Get the purchase orders
			$purchase_order = new purchase_order($proposal->proposal_hash);
			$purchase_order->fetch_purchase_orders(0,$purchase_order->total);
			for ($i = 0; $i < $purchase_order->total; $i++) {
				$file_attach[] = $this->form->checkbox("name=attached[]","value=purchase_order|".$purchase_order->po_info[$i]['po_hash']."|Purchase_Order_".$purchase_order->po_info[$i]['po_no'].".pdf",($cc == 'purchase_order' && $this->ajax_vars['po_hash'] == $purchase_order->po_info[$i]['po_hash'] ? "checked" : NULL))."&nbsp;Purchase_Order_".$purchase_order->po_info[$i]['po_no'].".pdf";
				$file_attach[] = $this->form->checkbox("name=attached[]","value=delivery_ticket|".$purchase_order->po_info[$i]['po_hash']."|Delivery_Ticket_".$purchase_order->po_info[$i]['po_no'].".pdf",($cc == 'delivery_ticket' && $this->ajax_vars['po_hash'] == $purchase_order->po_info[$i]['po_hash'] ? "checked" : NULL))."&nbsp;Delivery_Ticket_".$purchase_order->po_info[$i]['po_no'].".pdf";
			}

			$btn_value = "Send";
			$log = $this->fetch_message_list($proposal->proposal_hash);
		}

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Email & Fax Communications Window";

		$tbl .= $this->form->form_tag().$hidden.$this->form->hidden(array('msg_test'	=>	1)).
		$this->form->hidden(array("limit" => $this->ajax_vars['limit'], "popup_id" => $this->popup_id))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>";
		if ($this->ajax_vars['proposal_hash']) {
			$tbl .= "
			<ul id=\"maintab\" class=\"shadetabs\">
				<li class=\"selected\" ><a href=\"javascript:void(0);\" id=\"cntrl_tcontent1\" rel=\"tcontent1\" onClick=\"expandcontent(this);\" >Message Terminal</a></li>
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent2\" id=\"cntrl_tcontent2\" onClick=\"expandcontent(this);\" >Message Log</a></li>
			</ul>
			<div id=\"tcontent1\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
					<tr>
						<td style=\"background-color:#ffffff;padding:15px 20px;\">
							<div style=\"padding-bottom:15px;font-weight:bold;\">
								Message Type:&nbsp;&nbsp;".$this->form->select("type",array("Email Message","Fax Message"),NULL,array('E','F'),"blank=1","onChange=if(this.options[options.selectedIndex].value=='E'){toggle_display('email_holder','block');toggle_display('fax_holder','none');}else{toggle_display('email_holder','none');toggle_display('fax_holder','block');}")."
							</div>
							<div style=\"margin-left:25px;padding-bottom:3px;".(count($file_attach) > 6 ? "height:75px;overflow:auto;background-color:#efefef;border:1px outset #cccccc;width:90%;margin-bottom:10px" : NULL)."\">
								".$a."
								<table style=\"width:90%;\">";
							for ($i = 0; $i < count($file_attach); $i++) {
								$tbl .= "
								<tr>
									<td style=\"width:50%;\">".$file_attach[$i]."</td>
									<td style=\"width:50%;\">".($file_attach[$i+1] ?
										$file_attach[$i+1] : NULL)."
									</td>
								</tr>";
								$i++;

							}
							$tbl .= "
								</table>
							</div>
							<div id=\"email_holder\" style=\"margin-left:25px;display:".(!$type || $type == 'E' ? "block" : "none")."\">
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:90%;\" class=\"smallfont\">
									<tr>
										<td style=\"vertical-align:top;\" colspan=\"2\" class=\"smallfont\">
											<div style=\"padding-bottom:5px;\">
												<div style=\"padding-bottom:5px;\">Recipient Email: [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"toggle_display('email_search_menu','block');\$('contact_email').focus();\">search</a></small>]</div>
												<div id=\"email_search_menu\" class=\"function_menu\" >
													<div style=\"float:right;padding:5px\">
														<a href=\"javascript:void(0);\" onClick=\"toggle_display('email_search_menu','none');toggle_display('keyResults','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
													</div>
													<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Search Contacts:</div>
													<div class=\"function_menu_item\" >
														<div style=\"padding-bottom:15px;\">Search for a contact:</div>
														".$this->form->text_box("name=contact_email",
																				"value=",
																				"autocomplete=off",
																				"size=30",
																				"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('mail','contact_email','contact_email',1);}",
							                                                    "onKeyUp=if(ka==false && this.value){key_call('mail','contact_email','contact_email',1);}",
																				"onBlur=key_clear();")."
													</div>
												</div>
												<small><i>Multiple recipients separated by line break</i></small>
											</div>
											".$this->form->text_area("name=recipient_list","value=","rows=4","cols=84")."

										</td>
									</tr>
									<tr>
										<td colspan=\"2\" class=\"smallfont\">
											<div style=\"padding-bottom:5px;\">Subject: </div>
											".$this->form->text_box("name=subject","value=","size=85")."
										</td>
									</tr>
									<tr>
										<td style=\"vertical-align:top;\" class=\"smallfont\">
											<div style=\"padding-bottom:5px;\">
												Message Body
												<br />
												<small><i>Optional</i></small>
											</div>
											".$this->form->text_area("name=email_body","value=","rows=3","cols=44")."
										</td>
										<td>
											<div style=\"padding-bottom:5px;\" class=\"smallfont\">
												Attachments From File Vault
												<br />
												<small><i>Use cntrl key for multiple select</i></small>
											</div>".
											($file_vault_email ? $file_vault_email : $this->form->select("nothing",array("File vault is empty"),NULL,array('nothing'),"blank=1","multiple","size=4","style=width:250px;","disabled"))."
										</td>
									</tr>
									<tr>
										<td colspan=\"2\" style=\"vertical-align:bottom;text-align:left;\">
											".$this->form->button("value=".$btn_value,"onClick=submit_form(this.form,'mail','exec_post','refresh_form','action=doit_send');this.disabled=1;")."
										</td>
									</tr>
								</table>
							</div>
							<div id=\"fax_holder\" style=\"margin-left:25px;display:".($type == 'F' ? "block" : "none")."\">
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:90%;\" class=\"smallfont\" >
									<tr>
										<td style=\"vertical-align:top;width:100px;\">
											<div style=\"padding-bottom:5px;\">To: [<small><a href=\"javascript:void(0);\" onClick=\"toggle_display('fax_search_menu','block');\$('contact_fax').focus();\" class=\"link_standard\">search</a></small>]</div>
											<div id=\"fax_search_menu\" class=\"function_menu\" >
												<div style=\"float:right;padding:5px\">
													<a href=\"javascript:void(0);\" onClick=\"toggle_display('fax_search_menu','none');toggle_display('keyResults','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
												</div>
												<div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;\">Search Contacts:</div>
												<div class=\"function_menu_item\" >
													<div style=\"padding-bottom:15px;\">Search for a contact:</div>
													".$this->form->text_box("name=contact_fax",
																			"value=",
																			"autocomplete=off",
																			"size=30",
																			"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('mail','contact_fax','contact_fax',1);}",
											                                "onKeyUp=if(ka==false && this.value){key_call('mail','contact_fax','contact_fax',1);}",
																			"onBlur=key_clear();")."
												</div>
											</div>
											".$this->form->text_box("name=to","size=30","maxlength=128","value=".$to_name)."
										</td>
										<td style=\"vertical-align:top;\">
											<div style=\"padding-bottom:5px;\">Fax:</div>
											".$this->form->text_box("name=fax","size=30","maxlength=255","value=".$to_addr)."
										</td>
									</tr>
									<tr>
										<td style=\"vertical-align:top;\">
											<div style=\"padding-bottom:5px;\">From:</div>
											".$this->form->text_box("name=from","size=30","maxlength=255","value=".$from)."
										</td>
										<td style=\"vertical-align:top;\">
											<div style=\"padding-bottom:5px;\">Date:</div>
											".$this->form->text_box("name=date","size=30","maxlength=12","value=".date(DATE_FORMAT))."
										</td>
									</tr>
									<tr>
										<td style=\"vertical-align:top;\" >
											<div style=\"padding-bottom:5px;\">
												Re:
												<br />
												<small><i>Optional</i></small>
											</div>
											".$this->form->text_area("name=fax_body","value=","rows=4","cols=44")."
										</td>
										<td style=\"vertical-align:top;\" >
											<div style=\"padding-bottom:5px;\">
												File Vault:
												<br />
												<small><i>Use cntrl key for multiple select</i></small>
											</div>
											".($file_vault_fax ? $file_vault_fax : $this->form->select("nothing",array("File vault is empty"),NULL,array('nothing'),"blank=1","multiple","size=4","style=width:250px;","disabled"))."
										</td>
									</tr>
									<tr>
										<td colspan=\"2\">
											<div style=\"padding-top:30px;padding-right:15px;\">
												".$this->form->button("value=".$btn_value,"onClick=submit_form(this.form,'mail','exec_post','refresh_form','action=doit_send');")."
											</div>
										</td>
									</tr>
								</table>
							</div>
						</td>
					</tr>
				</table>
			</div>
			<div id=\"tcontent2\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td style=\"padding:0;\">
							 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
								<tr>
									<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">".(count($log) ? "
										Showing 1 - ".count($log)." of ".count($log)." Messages." : "&nbsp;")."
									</td>
								</tr>
								<tr class=\"thead\" style=\"font-weight:bold;\">
									<td >Type</td>
									<td >Timestamp</td>
									<td >Recipient</td>
									<td >Subject</td>
									<td >Status</td>
									<td></td>
								</tr>";
								$b = 0;
								for ($i = 0; $i < count($log); $i++) {
									$b++;

									$tbl .= "
									<tr >
										<td>".($log[$i]['type'] == 'E' ? "Email" : ($log[$i]['type'] == 'W' ? "EDI" : "Fax"))."</td>
										<td>".date(DATE_FORMAT." ".TIMESTAMP_FORMAT,$log[$i]['timestamp'])."</td>
										<td title=\"".($log[$i]['to_addr'] ? $log[$i]['to_addr'] : $log[$i]['destination'])."\">".($log[$i]['to_addr'] ?
											(strlen($log[$i]['to_addr']) > 15 ?
												substr($log[$i]['to_addr'],0,15)."..." : $log[$i]['to_addr']) : ($log[$i]['type'] == 'F' ?
													$this->format_fax_no($log[$i]['destination']) : $log[$i]['destination']))."
										</td>
										<td title=\"".$log[$i]['subject']."\">".(strlen($log[$i]['subject']) > 14 ? substr($log[$i]['subject'],0,14)."..." : $log[$i]['subject'])."</td>
										<td style=\"font-style:italic;\">".($log[$i]['type'] == 'F' ?
											($log[$i]['transaction_id'] && $log[$i]['result'] === '' ?
												"In progress" : (!$log[$i]['transaction_id'] && $log[$i]['result'] == '' ?
													"Pending&nbsp;&nbsp;<a href=\"javascript:void(0);\" onClick=\"submit_form(\$('msg_test').form,'mail','exec_post','refresh_form','action=doit_rm','id=".$log[$i]['obj_id']."');\"><img src=\"images/void_check.gif\" title=\"Remove from queue\" border=\"0\" /></a>" : ($log[$i]['result'] == 0 ?
														"<img src=\"images/interfax_ok.gif\" />&nbsp;Successful" : ($log[$i]['result'] > 0 ?
															"<img src=\"images/interfax_failed.gif\" ".($interfax_result_code[$log[$i]['result']] ? "title=\"".$interfax_result_code[$log[$i]['result']]."\"" : NULL)." />&nbsp;Failed" : "Preparing")))) : (array_key_exists('resend',$log[$i]) ? "Pending&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this message from the outgoing message queue?')){submit_form(\$('popup_id').form,'mail','exec_post','refresh_form','action=doit_rm','id=".$log[$i]['obj_id']."');}\"><img src=\"images/void_check.gif\" title=\"Remove from queue\" border=\"0\" /></a>" : ($log[$i]['result'] == 0 ? "Sent" : "<img src=\"images/interfax_failed.gif\" />&nbsp;Failed")))."
										</td>
										<td style=\"text-align:right;\">".($log[$i]['transaction_id'] && $log[$i]['type'] == 'F' ? "
											<a href=\"download.php?fid=".$log[$i]['transaction_id']."\" target=\"_blank\"><img src=\"images/show_tif.gif\" title=\"View fax image\" border=\"0\" /></a>" : NULL)."
										</td>
									</tr>".($b < count($log) ? "
									<tr>
										<td style=\"background-color:#cccccc;\" colspan=\"6\"></td>
									</tr>" : NULL);
								}
								if (!count($log))
									$tbl .= "
									<tr>
										<td colspan=\"6\">There are no messages to show.</td>
									</tr>";

					$tbl .= "
							</table>
						</td>
					</tr>
				</table>
			</div>";
		} else {
			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:15px 20px;\">
						<div style=\"padding-bottom:15px;font-weight:bold;\">To get started, please enter the proposal number:</div>
						".$this->form->text_box("name=proposal_no",
												"value=",
												"autocomplete=off",
												"size=30",
												"onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('mail','proposal_no','proposal_hash',1);}",
			                                    "onKeyUp=if(ka==false && this.value){key_call('mail','proposal_no','proposal_hash',1);}",
												"onBlur=key_clear();",
												"onKeyDown=clear_values('proposal_hash');").
						$this->form->hidden(array("proposal_hash" => ''))."
					</td>
				</tr>
			</table>";
			$this->content['focus'] = "proposal_no";
		}
		$tbl .= "
		</div>".
		$this->form->close_form();

		if ($this->ajax_vars['proposal_hash'])
			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab\')',10);";
		else
			$this->content['focus'] = 'proposal_no';

		$this->content['popup_controls']["cmdTable"] = $tbl;

		if ($this->ajax_vars['tab_to'])
			$this->content['jscript'][] = "setTimeout('expandcontent($(\'cntrl_".$this->ajax_vars['tab_to']."\'))',100)";
		return;
	}
}

////////////////////////////////////////////////////
// PHPMailer - PHP email class
//
// Class for sending email using either
// sendmail, PHP mail(), or SMTP.  Methods are
// based upon the standard AspEmail(tm) classes.
//
// Copyright (C) 2001 - 2003  Brent R. Matzelle
//
// License: LGPL, see LICENSE
////////////////////////////////////////////////////

/**
 * PHPMailer - PHP email transport class
 * @package PHPMailer
 * @author Brent R. Matzelle
 * @copyright 2001 - 2003 Brent R. Matzelle
 */
class PHPMailer {
    /////////////////////////////////////////////////
    // PUBLIC VARIABLES
    /////////////////////////////////////////////////

    /**
     * Email priority (1 = High, 3 = Normal, 5 = low).
     * @var int
     */
    var $Priority          = 3;

    /**
     * Sets the CharSet of the message.
     * @var string
     */
    var $CharSet           = "iso-8859-1";

    /**
     * Sets the Content-type of the message.
     * @var string
     */
    var $ContentType        = "text/plain";

    /**
     * Sets the Encoding of the message. Options for this are "8bit",
     * "7bit", "binary", "base64", and "quoted-printable".
     * @var string
     */
    var $Encoding          = "8bit";

    /**
     * Holds the most recent mailer error message.
     * @var string
     */
    var $ErrorInfo         = "";

    /**
     * Sets the From email address for the message.
     * @var string
     */
    var $From               = "noreply@dc-sysllc.com";

    /**
     * Sets the From name of the message.
     * @var string
     */
    var $FromName           = "DealerChoice";

    /**
     * Sets the Sender email (Return-Path) of the message.  If not empty,
     * will be sent via -f to sendmail or as 'MAIL FROM' in smtp mode.
     * @var string
     */
    var $Sender            = "noreply@dc-sysllc.com";

    /**
     * Sets the Subject of the message.
     * @var string
     */
    var $Subject           = "";

    /**
     * Sets the Body of the message.  This can be either an HTML or text body.
     * If HTML then run IsHTML(true).
     * @var string
     */
    var $Body               = "";

    /**
     * Sets the text-only body of the message.  This automatically sets the
     * email to multipart/alternative.  This body can be read by mail
     * clients that do not have HTML email capability such as mutt. Clients
     * that can read HTML will view the normal Body.
     * @var string
     */
    var $AltBody           = "";

    /**
     * Sets word wrapping on the body of the message to a given number of
     * characters.
     * @var int
     */
    var $WordWrap          = 0;

    /**
     * Method to send mail: ("mail", "sendmail", or "smtp").
     * @var string
     */
    var $Mailer            = "mail";

    /**
     * Sets the path of the sendmail program.
     * @var string
     */
    var $Sendmail          = "/usr/sbin/sendmail";

    /**
     * Path to PHPMailer plugins.  This is now only useful if the SMTP class
     * is in a different directory than the PHP include path.
     * @var string
     */
    var $PluginDir         = "";

    /**
     *  Holds PHPMailer version.
     *  @var string
     */
    var $Version           = "1.73";

    /**
     * Sets the email address that a reading confirmation will be sent.
     * @var string
     */
    var $ConfirmReadingTo  = "";

    /**
     *  Sets the hostname to use in Message-Id and Received headers
     *  and as default HELO string. If empty, the value returned
     *  by SERVER_NAME is used or 'localhost.localdomain'.
     *  @var string
     */
    var $Hostname          = "dc-sysllc.com";

    /////////////////////////////////////////////////
    // SMTP VARIABLES
    /////////////////////////////////////////////////

    /**
     *  Sets the SMTP hosts.  All hosts must be separated by a
     *  semicolon.  You can also specify a different port
     *  for each host by using this format: [hostname:port]
     *  (e.g. "smtp1.example.com:25;smtp2.example.com").
     *  Hosts will be tried in order.
     *  @var string
     */
    var $Host        = SMTP_HOST;

    /**
     *  Sets the default SMTP server port.
     *  @var int
     */
    var $Port        = 25;

    /**
     *  Sets the SMTP HELO of the message (Default is $Hostname).
     *  @var string
     */
    var $Helo        = "";

    /**
     *  Sets SMTP authentication. Utilizes the Username and Password variables.
     *  @var bool
     */
    var $SMTPAuth     = false;

    /**
     *  Sets SMTP username.
     *  @var string
     */
    var $Username     = "";

    /**
     *  Sets SMTP password.
     *  @var string
     */
    var $Password     = "";

    /**
     *  Sets the SMTP server timeout in seconds. This function will not
     *  work with the win32 version.
     *  @var int
     */
    var $Timeout      = 15;

    /**
     *  Sets SMTP class debugging on or off.
     *  @var bool
     */
    var $SMTPDebug    = false;

    /**
     * Prevents the SMTP connection from being closed after each mail
     * sending.  If this is set to true then to close the connection
     * requires an explicit call to SmtpClose().
     * @var bool
     */
    var $SMTPKeepAlive = false;

    /**#@+
     * @access private
     */
    var $smtp            = NULL;
    var $to              = array();
    var $cc              = array();
    var $bcc             = array();
    var $ReplyTo         = array();
    var $attachment      = array();
    var $CustomHeader    = array();
    var $message_type    = "";
    var $boundary        = array();
    var $language        = array();
    var $error_count     = 0;
    var $LE              = "\n";
    /**#@-*/

    /////////////////////////////////////////////////
    // VARIABLE METHODS
    /////////////////////////////////////////////////

	function build_html($html,$file=NULL) {
		if (!$file)
			$file = EMAIL_DOCS."default_template.html";

		$fp = fopen($file,"r");
		while (!feof($fp))
			$data .= fread($fp,1024);

		$data = str_replace("<!--BODY-->",$html,$data);

		return $data;
	}

    /**
     * Sets message type to HTML.
     * @param bool $bool
     * @return void
     */
    function IsHTML($bool) {
        if($bool == true)
            $this->ContentType = "text/html";
        else
            $this->ContentType = "text/plain";
    }

    /**
     * Sets Mailer to send message using SMTP.
     * @return void
     */
    function IsSMTP() {
        $this->Mailer = "smtp";
    }

    /**
     * Sets Mailer to send message using PHP mail() function.
     * @return void
     */
    function IsMail() {
        $this->Mailer = "mail";
    }

    /**
     * Sets Mailer to send message using the $Sendmail program.
     * @return void
     */
    function IsSendmail() {
        $this->Mailer = "sendmail";
    }

    /**
     * Sets Mailer to send message using the qmail MTA.
     * @return void
     */
    function IsQmail() {
        $this->Sendmail = "/var/qmail/bin/sendmail";
        $this->Mailer = "sendmail";
    }


    /////////////////////////////////////////////////
    // RECIPIENT METHODS
    /////////////////////////////////////////////////

    /**
     * Adds a "To" address.
     * @param string $address
     * @param string $name
     * @return void
     */
    function AddAddress($address, $name = "") {
        $cur = count($this->to);
        $this->to[$cur][0] = trim($address);
        $this->to[$cur][1] = $name;
    }

    /**
     * Adds a "Cc" address. Note: this function works
     * with the SMTP mailer on win32, not with the "mail"
     * mailer.
     * @param string $address
     * @param string $name
     * @return void
    */
    function AddCC($address, $name = "") {
        $cur = count($this->cc);
        $this->cc[$cur][0] = trim($address);
        $this->cc[$cur][1] = $name;
    }

    /**
     * Adds a "Bcc" address. Note: this function works
     * with the SMTP mailer on win32, not with the "mail"
     * mailer.
     * @param string $address
     * @param string $name
     * @return void
     */
    function AddBCC($address, $name = "") {
        $cur = count($this->bcc);
        $this->bcc[$cur][0] = trim($address);
        $this->bcc[$cur][1] = $name;
    }

    /**
     * Adds a "Reply-to" address.
     * @param string $address
     * @param string $name
     * @return void
     */
    function AddReplyTo($address, $name = "") {
        $cur = count($this->ReplyTo);
        $this->ReplyTo[$cur][0] = trim($address);
        $this->ReplyTo[$cur][1] = $name;
    }


    /////////////////////////////////////////////////
    // MAIL SENDING METHODS
    /////////////////////////////////////////////////

    /**
     * Creates message and assigns Mailer. If the message is
     * not sent successfully then it returns false.  Use the ErrorInfo
     * variable to view description of the error.
     * @return bool
     */
    function Send() {
        $header = "";
        $body = "";
        $result = true;

        if((count($this->to) + count($this->cc) + count($this->bcc)) < 1)
        {
            $this->SetError($this->Lang("provide_address"));
            return false;
        }

        // Set whether the message is multipart/alternative
        if(!empty($this->AltBody))
            $this->ContentType = "multipart/alternative";

        $this->error_count = 0; // reset errors
        $this->SetMessageType();
        $header .= $this->CreateHeader();
        $body = $this->CreateBody();

        if($body == "") { return false; }

        // Choose the mailer
        switch($this->Mailer)
        {
            case "sendmail":
                $result = $this->SendmailSend($header, $body);
                break;
            case "mail":
                $result = $this->MailSend($header, $body);
                break;
            case "smtp":
                $result = $this->SmtpSend($header, $body);
                break;
            default:
            $this->SetError($this->Mailer . $this->Lang("mailer_not_supported"));
                $result = false;
                break;
        }

		return $result;
    }

    /**
     * Sends mail using the $Sendmail program.
     * @access private
     * @return bool
     */
    function SendmailSend($header, $body) {
        if ($this->Sender != "")
            $sendmail = sprintf("%s -oi -f %s -t", $this->Sendmail, $this->Sender);
        else
            $sendmail = sprintf("%s -oi -t", $this->Sendmail);

        if(!@$mail = popen($sendmail, "w"))
        {
            $this->SetError($this->Lang("execute") . $this->Sendmail);
            return false;
        }

        fputs($mail, $header);
        fputs($mail, $body);

        $result = pclose($mail) >> 8 & 0xFF;
        if($result != 0)
        {
            $this->SetError($this->Lang("execute") . $this->Sendmail);
            return false;
        }

        return true;
    }

    /**
     * Sends mail using the PHP mail() function.
     * @access private
     * @return bool
     */
    function MailSend($header, $body) {
        $to = "";
        for($i = 0; $i < count($this->to); $i++)
        {
            if($i != 0) { $to .= ", "; }
            $to .= $this->to[$i][0];
        }

        if ($this->Sender != "" && strlen(ini_get("safe_mode"))< 1)
        {
            $old_from = ini_get("sendmail_from");
            ini_set("sendmail_from", $this->Sender);
            $params = sprintf("-oi -f %s", $this->Sender);
            $rt = @mail($to, $this->EncodeHeader($this->Subject), $body,
                        $header, $params);
        }
        else
            $rt = @mail($to, $this->EncodeHeader($this->Subject), $body, $header);

        if (isset($old_from))
            ini_set("sendmail_from", $old_from);

        if(!$rt)
        {
            $this->SetError($this->Lang("instantiate"));
            return false;
        }

        return true;
    }

    /**
     * Sends mail via SMTP using PhpSMTP (Author:
     * Chris Ryan).  Returns bool.  Returns false if there is a
     * bad MAIL FROM, RCPT, or DATA input.
     * @access private
     * @return bool
     */
    function SmtpSend($header, $body) {
       // include_once($this->PluginDir . "class.smtp.php");
        $error = "";
        $bad_rcpt = array();

        if(!$this->SmtpConnect())
            return false;

        $smtp_from = ($this->Sender == "") ? $this->From : $this->Sender;
        if(!$this->smtp->Mail($smtp_from))
        {
            $error = $this->Lang("from_failed") . $smtp_from;
            $this->SetError($error);
            $this->smtp->Reset();
            return false;
        }

        // Attempt to send attach all recipients
        for($i = 0; $i < count($this->to); $i++)
        {
            if(!$this->smtp->Recipient($this->to[$i][0]))
                $bad_rcpt[] = $this->to[$i][0];
        }
        for($i = 0; $i < count($this->cc); $i++)
        {
            if(!$this->smtp->Recipient($this->cc[$i][0]))
                $bad_rcpt[] = $this->cc[$i][0];
        }
        for($i = 0; $i < count($this->bcc); $i++)
        {
            if(!$this->smtp->Recipient($this->bcc[$i][0]))
                $bad_rcpt[] = $this->bcc[$i][0];
        }

        if(count($bad_rcpt) > 0) // Create error message
        {
            for($i = 0; $i < count($bad_rcpt); $i++)
            {
                if($i != 0) { $error .= ", "; }
                $error .= $bad_rcpt[$i];
            }
            $error = $this->Lang("recipients_failed") . $error;
            $this->SetError($error);
            $this->smtp->Reset();
            return false;
        }

        if(!$this->smtp->Data($header . $body))
        {
            $this->SetError($this->Lang("data_not_accepted"));
            $this->smtp->Reset();
            return false;
        }
        if($this->SMTPKeepAlive == true)
            $this->smtp->Reset();
        else
            $this->SmtpClose();

        return true;
    }

    /**
     * Initiates a connection to an SMTP server.  Returns false if the
     * operation failed.
     * @access private
     * @return bool
     */
    function SmtpConnect() {
        if($this->smtp == NULL) { $this->smtp = new SMTP(); }

        $this->smtp->do_debug = $this->SMTPDebug;
        $hosts = explode(";", $this->Host);
        $index = 0;
        $connection = ($this->smtp->Connected());

        // Retry while there is no connection
        while($index < count($hosts) && $connection == false)
        {
            if(strstr($hosts[$index], ":"))
                list($host, $port) = explode(":", $hosts[$index]);
            else
            {
                $host = $hosts[$index];
                $port = $this->Port;
            }

            if($this->smtp->Connect($host, $port, $this->Timeout))
            {
                if ($this->Helo != '')
                    $this->smtp->Hello($this->Helo);
                else
                    $this->smtp->Hello($this->ServerHostname());

                if($this->SMTPAuth)
                {
                    if(!$this->smtp->Authenticate($this->Username,
                                                  $this->Password))
                    {
                        $this->SetError($this->Lang("authenticate"));
                        $this->smtp->Reset();
                        $connection = false;
                    }
                }
                $connection = true;
            }
            $index++;
        }
        if(!$connection)
            $this->SetError($this->Lang("connect_host"));

        return $connection;
    }

    /**
     * Closes the active SMTP session if one exists.
     * @return void
     */
    function SmtpClose() {
        if($this->smtp != NULL)
        {
            if($this->smtp->Connected())
            {
                $this->smtp->Quit();
                $this->smtp->Close();
            }
        }
    }

    /**
     * Sets the language for all class error messages.  Returns false
     * if it cannot load the language file.  The default language type
     * is English.
     * @param string $lang_type Type of language (e.g. Portuguese: "br")
     * @param string $lang_path Path to the language file directory
     * @access public
     * @return bool
     */
    function SetLanguage($lang_type, $lang_path = "include/language/") {
		if(file_exists(SITE_ROOT.'core/'.$lang_path.'phpmailer.lang-'.$lang_type.'.php')) {
            include(SITE_ROOT.'core/'.$lang_path.'phpmailer.lang-'.$lang_type.'.php');
        } else if(file_exists(SITE_ROOT.'core/'.$lang_path.'phpmailer.lang-en.php')) {
            include(SITE_ROOT.'core/'.$lang_path.'phpmailer.lang-en.php');
        } else {
            $this->SetError("Could not load language file!");
            return false;
        }
        $this->language = $PHPMAILER_LANG;

        return true;
    }

    /////////////////////////////////////////////////
    // MESSAGE CREATION METHODS
    /////////////////////////////////////////////////

    /**
     * Creates recipient headers.
     * @access private
     * @return string
     */
    function AddrAppend($type, $addr) {
        $addr_str = $type . ": ";
        $addr_str .= $this->AddrFormat($addr[0]);
        if(count($addr) > 1)
        {
            for($i = 1; $i < count($addr); $i++)
                $addr_str .= ", " . $this->AddrFormat($addr[$i]);
        }
        $addr_str .= $this->LE;

        return $addr_str;
    }

    /**
     * Formats an address correctly.
     * @access private
     * @return string
     */
    function AddrFormat($addr) {
        if(empty($addr[1]))
            $formatted = $addr[0];
        else
        {
            $formatted = $this->EncodeHeader($addr[1], 'phrase') . " <" .
                         $addr[0] . ">";
        }

        return $formatted;
    }

    /**
     * Wraps message for use with mailers that do not
     * automatically perform wrapping and for quoted-printable.
     * Original written by philippe.
     * @access private
     * @return string
     */
    function WrapText($message, $length, $qp_mode = false) {
        $soft_break = ($qp_mode) ? sprintf(" =%s", $this->LE) : $this->LE;

        $message = $this->FixEOL($message);
        if (substr($message, -1) == $this->LE)
            $message = substr($message, 0, -1);

        $line = explode($this->LE, $message);
        $message = "";
        for ($i=0 ;$i < count($line); $i++)
        {
          $line_part = explode(" ", $line[$i]);
          $buf = "";
          for ($e = 0; $e<count($line_part); $e++)
          {
              $word = $line_part[$e];
              if ($qp_mode and (strlen($word) > $length))
              {
                $space_left = $length - strlen($buf) - 1;
                if ($e != 0)
                {
                    if ($space_left > 20)
                    {
                        $len = $space_left;
                        if (substr($word, $len - 1, 1) == "=")
                          $len--;
                        elseif (substr($word, $len - 2, 1) == "=")
                          $len -= 2;
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);
                        $buf .= " " . $part;
                        $message .= $buf . sprintf("=%s", $this->LE);
                    }
                    else
                    {
                        $message .= $buf . $soft_break;
                    }
                    $buf = "";
                }
                while (strlen($word) > 0)
                {
                    $len = $length;
                    if (substr($word, $len - 1, 1) == "=")
                        $len--;
                    elseif (substr($word, $len - 2, 1) == "=")
                        $len -= 2;
                    $part = substr($word, 0, $len);
                    $word = substr($word, $len);

                    if (strlen($word) > 0)
                        $message .= $part . sprintf("=%s", $this->LE);
                    else
                        $buf = $part;
                }
              }
              else
              {
                $buf_o = $buf;
                $buf .= ($e == 0) ? $word : (" " . $word);

                if (strlen($buf) > $length and $buf_o != "")
                {
                    $message .= $buf_o . $soft_break;
                    $buf = $word;
                }
              }
          }
          $message .= $buf . $this->LE;
        }

        return $message;
    }

    /**
     * Set the body wrapping.
     * @access private
     * @return void
     */
    function SetWordWrap() {
        if($this->WordWrap < 1)
            return;

        switch($this->message_type)
        {
           case "alt":
              // fall through
           case "alt_attachments":
              $this->AltBody = $this->WrapText($this->AltBody, $this->WordWrap);
              break;
           default:
              $this->Body = $this->WrapText($this->Body, $this->WordWrap);
              break;
        }
    }

    /**
     * Assembles message header.
     * @access private
     * @return string
     */
    function CreateHeader() {
        $result = "";

        // Set the boundaries
        $uniq_id = md5(uniqid(time()));
        $this->boundary[1] = "b1_" . $uniq_id;
        $this->boundary[2] = "b2_" . $uniq_id;

        $result .= $this->HeaderLine("Date", $this->RFCDate());
        if($this->Sender == "")
            $result .= $this->HeaderLine("Return-Path", trim($this->From));
        else
            $result .= $this->HeaderLine("Return-Path", trim($this->Sender));

        // To be created automatically by mail()
        if($this->Mailer != "mail")
        {
            if(count($this->to) > 0)
                $result .= $this->AddrAppend("To", $this->to);
            else if (count($this->cc) == 0)
                $result .= $this->HeaderLine("To", "undisclosed-recipients:;");
            if(count($this->cc) > 0)
                $result .= $this->AddrAppend("Cc", $this->cc);

        }

        $from = array();
        $from[0][0] = trim($this->From);
        $from[0][1] = $this->FromName;
        $result .= $this->AddrAppend("From", $from);

        // sendmail and mail() extract Bcc from the header before sending
        if((($this->Mailer == "sendmail") || ($this->Mailer == "mail")) && (count($this->bcc) > 0))
            $result .= $this->AddrAppend("Bcc", $this->bcc);

        if(count($this->ReplyTo) > 0)
            $result .= $this->AddrAppend("Reply-to", $this->ReplyTo);

        // mail() sets the subject itself
        if($this->Mailer != "mail")
            $result .= $this->HeaderLine("Subject", $this->EncodeHeader(trim($this->Subject)));

        $result .= sprintf("Message-ID: <%s@%s>%s", $uniq_id, $this->ServerHostname(), $this->LE);
        $result .= $this->HeaderLine("X-Priority", $this->Priority);
        $result .= $this->HeaderLine("X-Mailer", "PHPMailer [version " . $this->Version . "]");

        if($this->ConfirmReadingTo != "")
        {
            $result .= $this->HeaderLine("Disposition-Notification-To",
                       "<" . trim($this->ConfirmReadingTo) . ">");
        }

        // Add custom headers
        for($index = 0; $index < count($this->CustomHeader); $index++)
        {
            $result .= $this->HeaderLine(trim($this->CustomHeader[$index][0]),
                       $this->EncodeHeader(trim($this->CustomHeader[$index][1])));
        }
        $result .= $this->HeaderLine("MIME-Version", "1.0");

        switch($this->message_type)
        {
            case "plain":
                $result .= $this->HeaderLine("Content-Transfer-Encoding", $this->Encoding);
                $result .= sprintf("Content-Type: %s; charset=\"%s\"",
                                    $this->ContentType, $this->CharSet);
                break;
            case "attachments":
                // fall through
            case "alt_attachments":
                if($this->InlineImageExists())
                {
                    $result .= sprintf("Content-Type: %s;%s\ttype=\"text/html\";%s\tboundary=\"%s\"%s",
                                    "multipart/related", $this->LE, $this->LE,
                                    $this->boundary[1], $this->LE);
                }
                else
                {
                    $result .= $this->HeaderLine("Content-Type", "multipart/mixed;");
                    $result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
                }
                break;
            case "alt":
                $result .= $this->HeaderLine("Content-Type", "multipart/alternative;");
                $result .= $this->TextLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
        }

        if($this->Mailer != "mail")
            $result .= $this->LE.$this->LE;

        return $result;
    }

    /**
     * Assembles the message body.  Returns an empty string on failure.
     * @access private
     * @return string
     */
    function CreateBody() {
        $result = "";

        $this->SetWordWrap();

        // Stripslashes added for Trac #726
        $this->Body = stripslashes($this->Body);
        $this->AltBody = stripslashes($this->AltBody);

        switch($this->message_type)
        {
            case "alt":
                $result .= $this->GetBoundary($this->boundary[1], "",
                                              "text/plain", "");
                $result .= $this->EncodeString($this->AltBody, $this->Encoding);
                $result .= $this->LE.$this->LE;
                $result .= $this->GetBoundary($this->boundary[1], "",
                                              "text/html", "");

                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE.$this->LE;

                $result .= $this->EndBoundary($this->boundary[1]);
                break;
            case "plain":
                $result .= $this->EncodeString($this->Body, $this->Encoding);
                break;
            case "attachments":
                $result .= $this->GetBoundary($this->boundary[1], "", "", "");
                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE;

                $result .= $this->AttachAll();
                break;
            case "alt_attachments":
                $result .= sprintf("--%s%s", $this->boundary[1], $this->LE);
                $result .= sprintf("Content-Type: %s;%s" .
                                   "\tboundary=\"%s\"%s",
                                   "multipart/alternative", $this->LE,
                                   $this->boundary[2], $this->LE.$this->LE);

                // Create text body
                $result .= $this->GetBoundary($this->boundary[2], "",
                                              "text/plain", "") . $this->LE;

                $result .= $this->EncodeString($this->AltBody, $this->Encoding);
                $result .= $this->LE.$this->LE;

                // Create the HTML body
                $result .= $this->GetBoundary($this->boundary[2], "",
                                              "text/html", "") . $this->LE;

                $result .= $this->EncodeString($this->Body, $this->Encoding);
                $result .= $this->LE.$this->LE;

                $result .= $this->EndBoundary($this->boundary[2]);

                $result .= $this->AttachAll();
                break;
        }
        if($this->IsError())
            $result = "";

        return $result;
    }

    /**
     * Returns the start of a message boundary.
     * @access private
     */
    function GetBoundary($boundary, $charSet, $contentType, $encoding) {
        $result = "";
        if($charSet == "") { $charSet = $this->CharSet; }
        if($contentType == "") { $contentType = $this->ContentType; }
        if($encoding == "") { $encoding = $this->Encoding; }

        $result .= $this->TextLine("--" . $boundary);
        $result .= sprintf("Content-Type: %s; charset = \"%s\"",
                            $contentType, $charSet);
        $result .= $this->LE;
        $result .= $this->HeaderLine("Content-Transfer-Encoding", $encoding);
        $result .= $this->LE;

        return $result;
    }

    /**
     * Returns the end of a message boundary.
     * @access private
     */
    function EndBoundary($boundary) {
        return $this->LE . "--" . $boundary . "--" . $this->LE;
    }

    /**
     * Sets the message type.
     * @access private
     * @return void
     */
    function SetMessageType() {
        if(count($this->attachment) < 1 && strlen($this->AltBody) < 1)
            $this->message_type = "plain";
        else
        {
            if(count($this->attachment) > 0)
                $this->message_type = "attachments";
            if(strlen($this->AltBody) > 0 && count($this->attachment) < 1)
                $this->message_type = "alt";
            if(strlen($this->AltBody) > 0 && count($this->attachment) > 0)
                $this->message_type = "alt_attachments";
        }
    }

    /**
     * Returns a formatted header line.
     * @access private
     * @return string
     */
    function HeaderLine($name, $value) {
        return $name . ": " . $value . $this->LE;
    }

    /**
     * Returns a formatted mail line.
     * @access private
     * @return string
     */
    function TextLine($value) {
        return $value . $this->LE;
    }

    /////////////////////////////////////////////////
    // ATTACHMENT METHODS
    /////////////////////////////////////////////////

    /**
     * Adds an attachment from a path on the filesystem.
     * Returns false if the file could not be found
     * or accessed.
     * @param string $path Path to the attachment.
     * @param string $name Overrides the attachment name.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return bool
     */
    function AddAttachment($path, $name = "", $encoding = "base64",
                           $type = "application/octet-stream") {
        if(!@is_file($path))
        {
            $this->SetError($this->Lang("file_access") . $path);
            return false;
        }

        $filename = basename($path);
        if($name == "")
            $name = $filename;

        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $path;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $name;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = false; // isStringAttachment
        $this->attachment[$cur][6] = "attachment";
        $this->attachment[$cur][7] = 0;

        return true;
    }

    /**
     * Attaches all fs, string, and binary attachments to the message.
     * Returns an empty string on failure.
     * @access private
     * @return string
     */
    function AttachAll() {
        // Return text of body
        $mime = array();

        // Add all attachments
        for($i = 0; $i < count($this->attachment); $i++)
        {
            // Check for string attachment
            $bString = $this->attachment[$i][5];
            if ($bString)
                $string = $this->attachment[$i][0];
            else
                $path = $this->attachment[$i][0];

            $filename    = $this->attachment[$i][1];
            $name        = $this->attachment[$i][2];
            $encoding    = $this->attachment[$i][3];
            $type        = $this->attachment[$i][4];
            $disposition = $this->attachment[$i][6];
            $cid         = $this->attachment[$i][7];

            $mime[] = sprintf("--%s%s", $this->boundary[1], $this->LE);
            $mime[] = sprintf("Content-Type: %s; name=\"%s\"%s", $type, $name, $this->LE);
            $mime[] = sprintf("Content-Transfer-Encoding: %s%s", $encoding, $this->LE);

            if($disposition == "inline")
                $mime[] = sprintf("Content-ID: <%s>%s", $cid, $this->LE);

            $mime[] = sprintf("Content-Disposition: %s; filename=\"%s\"%s",
                              $disposition, $name, $this->LE.$this->LE);

            // Encode as string attachment
            if($bString)
            {
                $mime[] = $this->EncodeString($string, $encoding);
                if($this->IsError()) { return ""; }
                $mime[] = $this->LE.$this->LE;
            }
            else
            {
                $mime[] = $this->EncodeFile($path, $encoding);
                if($this->IsError()) { return ""; }
                $mime[] = $this->LE.$this->LE;
            }
        }

        $mime[] = sprintf("--%s--%s", $this->boundary[1], $this->LE);

        return join("", $mime);
    }

    /**
     * Encodes attachment in requested format.  Returns an
     * empty string on failure.
     * @access private
     * @return string
     */
    function EncodeFile ($path, $encoding = "base64") {
        if(!@$fd = fopen($path, "rb"))
        {
            $this->SetError($this->Lang("file_open") . $path);
            return "";
        }
        $magic_quotes = get_magic_quotes_runtime();
        set_magic_quotes_runtime(0);
        $file_buffer = fread($fd, filesize($path));
        $file_buffer = $this->EncodeString($file_buffer, $encoding);
        fclose($fd);
        set_magic_quotes_runtime($magic_quotes);

        return $file_buffer;
    }

    /**
     * Encodes string to requested format. Returns an
     * empty string on failure.
     * @access private
     * @return string
     */
    function EncodeString ($str, $encoding = "base64") {
        $encoded = "";
        switch(strtolower($encoding)) {
          case "base64":
              // chunk_split is found in PHP >= 3.0.6
              $encoded = chunk_split(base64_encode($str), 76, $this->LE);
              break;
          case "7bit":
          case "8bit":
              $encoded = $this->FixEOL($str);
              if (substr($encoded, -(strlen($this->LE))) != $this->LE)
                $encoded .= $this->LE;
              break;
          case "binary":
              $encoded = $str;
              break;
          case "quoted-printable":
              $encoded = $this->EncodeQP($str);
              break;
          default:
              $this->SetError($this->Lang("encoding") . $encoding);
              break;
        }
        return $encoded;
    }

    /**
     * Encode a header string to best of Q, B, quoted or none.
     * @access private
     * @return string
     */
    function EncodeHeader ($str, $position = 'text') {
      $x = 0;

      switch (strtolower($position)) {
        case 'phrase':
          if (!preg_match('/[\200-\377]/', $str)) {
            // Can't use addslashes as we don't know what value has magic_quotes_sybase.
            $encoded = addcslashes($str, "\0..\37\177\\\"");

            if (($str == $encoded) && !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str))
              return ($encoded);
            else
              return ("\"$encoded\"");
          }
          $x = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
          break;
        case 'comment':
          $x = preg_match_all('/[()"]/', $str, $matches);
          // Fall-through
        case 'text':
        default:
          $x += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
          break;
      }

      if ($x == 0)
        return ($str);

      $maxlen = 75 - 7 - strlen($this->CharSet);
      // Try to select the encoding which should produce the shortest output
      if (strlen($str)/3 < $x) {
        $encoding = 'B';
        $encoded = base64_encode($str);
        $maxlen -= $maxlen % 4;
        $encoded = trim(chunk_split($encoded, $maxlen, "\n"));
      } else {
        $encoding = 'Q';
        $encoded = $this->EncodeQ($str, $position);
        $encoded = $this->WrapText($encoded, $maxlen, true);
        $encoded = str_replace("=".$this->LE, "\n", trim($encoded));
      }

      $encoded = preg_replace('/^(.*)$/m', " =?".$this->CharSet."?$encoding?\\1?=", $encoded);
      $encoded = trim(str_replace("\n", $this->LE, $encoded));

      return $encoded;
    }

    /**
     * Encode string to quoted-printable.
     * @access private
     * @return string
     */
    function EncodeQP ($str) {
        $encoded = $this->FixEOL($str);
        if (substr($encoded, -(strlen($this->LE))) != $this->LE)
            $encoded .= $this->LE;

        // Replace every high ascii, control and = characters
        $encoded = preg_replace('/([\000-\010\013\014\016-\037\075\177-\377])/e',
                  "'='.sprintf('%02X', ord('\\1'))", $encoded);
        // Replace every spaces and tabs when it's the last character on a line
        $encoded = preg_replace("/([\011\040])".$this->LE."/e",
                  "'='.sprintf('%02X', ord('\\1')).'".$this->LE."'", $encoded);

        // Maximum line length of 76 characters before CRLF (74 + space + '=')
        $encoded = $this->WrapText($encoded, 74, true);

        return $encoded;
    }

    /**
     * Encode string to q encoding.
     * @access private
     * @return string
     */
    function EncodeQ ($str, $position = "text") {
        // There should not be any EOL in the string
        $encoded = preg_replace("[\r\n]", "", $str);

        switch (strtolower($position)) {
          case "phrase":
            $encoded = preg_replace("/([^A-Za-z0-9!*+\/ -])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
            break;
          case "comment":
            $encoded = preg_replace("/([\(\)\"])/e", "'='.sprintf('%02X', ord('\\1'))", $encoded);
          case "text":
          default:
            // Replace every high ascii, control =, ? and _ characters
            $encoded = preg_replace('/([\000-\011\013\014\016-\037\075\077\137\177-\377])/e',
                  "'='.sprintf('%02X', ord('\\1'))", $encoded);
            break;
        }

        // Replace every spaces to _ (more readable than =20)
        $encoded = str_replace(" ", "_", $encoded);

        return $encoded;
    }

    /**
     * Adds a string or binary attachment (non-filesystem) to the list.
     * This method can be used to attach ascii or binary data,
     * such as a BLOB record from a database.
     * @param string $string String attachment data.
     * @param string $filename Name of the attachment.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return void
     */
    function AddStringAttachment($string, $filename, $encoding = "base64",
                                 $type = "application/octet-stream") {
        // Append to $attachment array
        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $string;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $filename;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = true; // isString
        $this->attachment[$cur][6] = "attachment";
        $this->attachment[$cur][7] = 0;
    }

    /**
     * Adds an embedded attachment.  This can include images, sounds, and
     * just about any other document.  Make sure to set the $type to an
     * image type.  For JPEG images use "image/jpeg" and for GIF images
     * use "image/gif".
     * @param string $path Path to the attachment.
     * @param string $cid Content ID of the attachment.  Use this to identify
     *        the Id for accessing the image in an HTML form.
     * @param string $name Overrides the attachment name.
     * @param string $encoding File encoding (see $Encoding).
     * @param string $type File extension (MIME) type.
     * @return bool
     */
    function AddEmbeddedImage($path, $cid, $name = "", $encoding = "base64",
                              $type = "application/octet-stream") {

        if(!@is_file($path))
        {
            $this->SetError($this->Lang("file_access") . $path);
            return false;
        }

        $filename = basename($path);
        if($name == "")
            $name = $filename;

        // Append to $attachment array
        $cur = count($this->attachment);
        $this->attachment[$cur][0] = $path;
        $this->attachment[$cur][1] = $filename;
        $this->attachment[$cur][2] = $name;
        $this->attachment[$cur][3] = $encoding;
        $this->attachment[$cur][4] = $type;
        $this->attachment[$cur][5] = false; // isStringAttachment
        $this->attachment[$cur][6] = "inline";
        $this->attachment[$cur][7] = $cid;

        return true;
    }

    /**
     * Returns true if an inline attachment is present.
     * @access private
     * @return bool
     */
    function InlineImageExists() {
        $result = false;
        for($i = 0; $i < count($this->attachment); $i++)
        {
            if($this->attachment[$i][6] == "inline")
            {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /////////////////////////////////////////////////
    // MESSAGE RESET METHODS
    /////////////////////////////////////////////////

    /**
     * Clears all recipients assigned in the TO array.  Returns void.
     * @return void
     */
    function ClearAddresses() {
        $this->to = array();
    }

    /**
     * Clears all recipients assigned in the CC array.  Returns void.
     * @return void
     */
    function ClearCCs() {
        $this->cc = array();
    }

    /**
     * Clears all recipients assigned in the BCC array.  Returns void.
     * @return void
     */
    function ClearBCCs() {
        $this->bcc = array();
    }

    /**
     * Clears all recipients assigned in the ReplyTo array.  Returns void.
     * @return void
     */
    function ClearReplyTos() {
        $this->ReplyTo = array();
    }

    /**
     * Clears all recipients assigned in the TO, CC and BCC
     * array.  Returns void.
     * @return void
     */
    function ClearAllRecipients() {
        $this->to = array();
        $this->cc = array();
        $this->bcc = array();
    }

    /**
     * Clears all previously set filesystem, string, and binary
     * attachments.  Returns void.
     * @return void
     */
    function ClearAttachments() {
        $this->attachment = array();
    }

    /**
     * Clears all custom headers.  Returns void.
     * @return void
     */
    function ClearCustomHeaders() {
        $this->CustomHeader = array();
    }


    /////////////////////////////////////////////////
    // MISCELLANEOUS METHODS
    /////////////////////////////////////////////////

    /**
     * Adds the error message to the error container.
     * Returns void.
     * @access private
     * @return void
     */
    function SetError($msg) {
        $this->error_count++;
        $this->ErrorInfo = $msg;
    }

    /**
     * Returns the proper RFC 822 formatted date.
     * @access private
     * @return string
     */
    function RFCDate() {
        $tz = date("Z");

        $tzs = ($tz < 0) ? "-" : "+";
        $tz = abs($tz);
        $tz = ($tz/3600)*100 + ($tz%3600)/60;
        $result = sprintf("%s %s%04d", date("D, j M Y H:i:s"), $tzs, $tz);

        return $result;
    }

    /**
     * Returns the appropriate server variable.  Should work with both
     * PHP 4.1.0+ as well as older versions.  Returns an empty string
     * if nothing is found.
     * @access private
     * @return mixed
     */
    function ServerVar($varName) {
        global $HTTP_SERVER_VARS;
        global $HTTP_ENV_VARS;

        if(!isset($_SERVER))
        {
            $_SERVER = $HTTP_SERVER_VARS;
            if(!isset($_SERVER["REMOTE_ADDR"]))
                $_SERVER = $HTTP_ENV_VARS; // must be Apache
        }

        if(isset($_SERVER[$varName]))
            return $_SERVER[$varName];
        else
            return "";
    }

    /**
     * Returns the server hostname or 'localhost.localdomain' if unknown.
     * @access private
     * @return string
     */
    function ServerHostname() {
        if ($this->Hostname != "")
            $result = $this->Hostname;
        elseif ($this->ServerVar('SERVER_NAME') != "")
            $result = $this->ServerVar('SERVER_NAME');
        else
            $result = "localhost.localdomain";

        return $result;
    }

    /**
     * Returns a message in the appropriate language.
     * @access private
     * @return string
     */
    function Lang($key) {
        if(count($this->language) < 1)
            $this->SetLanguage("en"); // set the default language
        if(isset($this->language[$key]))
            return $this->language[$key];
        else
            return "Language string failed to load: " . $key .'*';
    }

    /**
     * Returns true if an error occurred.
     * @return bool
     */
    function IsError() {
        return ($this->error_count > 0);
    }

    /**
     * Changes every end of line from CR or LF to CRLF.
     * @access private
     * @return string
     */
    function FixEOL($str) {
        $str = str_replace("\r\n", "\n", $str);
        $str = str_replace("\r", "\n", $str);
        $str = str_replace("\n", $this->LE, $str);
        return $str;
    }

    /**
     * Adds a custom header.
     * @return void
     */
    function AddCustomHeader($custom_header) {
        $this->CustomHeader[] = explode(":", $custom_header, 2);
    }
}

////////////////////////////////////////////////////
// SMTP - PHP SMTP class
//
// Version 1.02
//
// Define an SMTP class that can be used to connect
// and communicate with any SMTP server. It implements
// all the SMTP functions defined in RFC821 except TURN.
//
// Author: Chris Ryan
//
// License: LGPL, see LICENSE
////////////////////////////////////////////////////

/**
 * SMTP is rfc 821 compliant and implements all the rfc 821 SMTP
 * commands except TURN which will always return a not implemented
 * error. SMTP also provides some utility methods for sending mail
 * to an SMTP server.
 * @package PHPMailer
 * @author Chris Ryan
 */
class SMTP
{
    /**
     *  SMTP server port
     *  @var int
     */
    var $SMTP_PORT = 25;

    /**
     *  SMTP reply line ending
     *  @var string
     */
    var $CRLF = "\r\n";

    /**
     *  Sets whether debugging is turned on
     *  @var bool
     */
    var $do_debug;       # the level of debug to perform

    /**#@+
     * @access private
     */
    var $smtp_conn;      # the socket to the server
    var $error;          # error if any on the last call
    var $helo_rply;      # the reply the server sent to us for HELO
    /**#@-*/

    /**
     * Initialize the class so that the data is in a known state.
     * @access public
     * @return void
     */
    function SMTP() {
        $this->smtp_conn = 0;
        $this->error = null;
        $this->helo_rply = null;

        $this->do_debug = 0;
    }

    /*************************************************************
     *                    CONNECTION FUNCTIONS                  *
     ***********************************************************/

    /**
     * Connect to the server specified on the port specified.
     * If the port is not specified use the default SMTP_PORT.
     * If tval is specified then a connection will try and be
     * established with the server for that number of seconds.
     * If tval is not specified the default is 30 seconds to
     * try on the connection.
     *
     * SMTP CODE SUCCESS: 220
     * SMTP CODE FAILURE: 421
     * @access public
     * @return bool
     */
    function Connect($host,$port=0,$tval=30) {
        # set the error val to null so there is no confusion
        $this->error = null;

        # make sure we are __not__ connected
        if($this->connected()) {
            # ok we are connected! what should we do?
            # for now we will just give an error saying we
            # are already connected
            $this->error =
                array("error" => "Already connected to a server");
            return false;
        }

        if(empty($port)) {
            $port = $this->SMTP_PORT;
        }

        #connect to the smtp server
        $this->smtp_conn = @fsockopen($host,    # the host of the server
                                     $port,    # the port to use
                                     $errno,   # error number if any
                                     $errstr,  # error message if any
                                     $tval);   # give up after ? secs
        # verify we connected properly
        if(empty($this->smtp_conn)) {
            $this->error = array("error" => "Failed to connect to server",
                                 "errno" => $errno,
                                 "errstr" => $errstr);
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": $errstr ($errno)" . $this->CRLF;
            }
            return false;
        }

        # sometimes the SMTP server takes a little longer to respond
        # so we will give it a longer timeout for the first read
        // Windows still does not have support for this timeout function
        if(substr(PHP_OS, 0, 3) != "WIN")
           socket_set_timeout($this->smtp_conn, $tval, 0);

        # get any announcement stuff
        $announce = $this->get_lines();


        # set the timeout  of any socket functions at 1/10 of a second
        //if(function_exists("socket_set_timeout"))
        //   socket_set_timeout($this->smtp_conn, 0, 100000);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $announce;
        }

        return true;
    }

    /**
     * Performs SMTP authentication.  Must be run after running the
     * Hello() method.  Returns true if successfully authenticated.
     * @access public
     * @return bool
     */
    function Authenticate($username, $password) {
        // Start authentication
        fputs($this->smtp_conn,"AUTH LOGIN" . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($code != 334) {
            $this->error =
                array("error" => "AUTH not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }

        // Send encoded username
        fputs($this->smtp_conn, base64_encode($username) . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($code != 334) {
            $this->error =
                array("error" => "Username not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }

        // Send encoded password
        fputs($this->smtp_conn, base64_encode($password) . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($code != 235) {
            $this->error =
                array("error" => "Password not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }

        return true;
    }

    /**
     * Returns true if connected to a server otherwise false
     * @access private
     * @return bool
     */
    function Connected() {
        if(!empty($this->smtp_conn)) {
            $sock_status = socket_get_status($this->smtp_conn);
            if($sock_status["eof"]) {
                # hmm this is an odd situation... the socket is
                # valid but we aren't connected anymore
                if($this->do_debug >= 1) {
                    echo "SMTP -> NOTICE:" . $this->CRLF .
                         "EOF caught while checking if connected";
                }
                $this->Close();
                return false;
            }
            return true; # everything looks good
        }
        return false;
    }

    /**
     * Closes the socket and cleans up the state of the class.
     * It is not considered good to use this function without
     * first trying to use QUIT.
     * @access public
     * @return void
     */
    function Close() {
        $this->error = null; # so there is no confusion
        $this->helo_rply = null;
        if(!empty($this->smtp_conn)) {
            # close the connection and cleanup
            fclose($this->smtp_conn);
            $this->smtp_conn = 0;
        }
    }


    /***************************************************************
     *                        SMTP COMMANDS                       *
     *************************************************************/

    /**
     * Issues a data command and sends the msg_data to the server
     * finializing the mail transaction. $msg_data is the message
     * that is to be send with the headers. Each header needs to be
     * on a single line followed by a <CRLF> with the message headers
     * and the message body being seperated by and additional <CRLF>.
     *
     * Implements rfc 821: DATA <CRLF>
     *
     * SMTP CODE INTERMEDIATE: 354
     *     [data]
     *     <CRLF>.<CRLF>
     *     SMTP CODE SUCCESS: 250
     *     SMTP CODE FAILURE: 552,554,451,452
     * SMTP CODE FAILURE: 451,554
     * SMTP CODE ERROR  : 500,501,503,421
     * @access public
     * @return bool
     */
    function Data($msg_data) {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Data() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"DATA" . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 354) {
            $this->error =
                array("error" => "DATA command not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }

        # the server is ready to accept data!
        # according to rfc 821 we should not send more than 1000
        # including the CRLF
        # characters on a single line so we will break the data up
        # into lines by \r and/or \n then if needed we will break
        # each of those into smaller lines to fit within the limit.
        # in addition we will be looking for lines that start with
        # a period '.' and append and additional period '.' to that
        # line. NOTE: this does not count towards are limit.

        # normalize the line breaks so we know the explode works
        $msg_data = str_replace("\r\n","\n",$msg_data);
        $msg_data = str_replace("\r","\n",$msg_data);
        $lines = explode("\n",$msg_data);

        # we need to find a good way to determine is headers are
        # in the msg_data or if it is a straight msg body
        # currently I'm assuming rfc 822 definitions of msg headers
        # and if the first field of the first line (':' sperated)
        # does not contain a space then it _should_ be a header
        # and we can process all lines before a blank "" line as
        # headers.
        $field = substr($lines[0],0,strpos($lines[0],":"));
        $in_headers = false;
        if(!empty($field) && !strstr($field," ")) {
            $in_headers = true;
        }

        $max_line_length = 998; # used below; set here for ease in change

        while(list(,$line) = @each($lines)) {
            $lines_out = null;
            if($line == "" && $in_headers) {
                $in_headers = false;
            }
            # ok we need to break this line up into several
            # smaller lines
            while(strlen($line) > $max_line_length) {
                $pos = strrpos(substr($line,0,$max_line_length)," ");

                # Patch to fix DOS attack
                if(!$pos) {
                    $pos = $max_line_length - 1;
                }

                $lines_out[] = substr($line,0,$pos);
                $line = substr($line,$pos + 1);
                # if we are processing headers we need to
                # add a LWSP-char to the front of the new line
                # rfc 822 on long msg headers
                if($in_headers) {
                    $line = "\t" . $line;
                }
            }
            $lines_out[] = $line;

            # now send the lines to the server
            while(list(,$line_out) = @each($lines_out)) {
                if(strlen($line_out) > 0)
                {
                    if(substr($line_out, 0, 1) == ".") {
                        $line_out = "." . $line_out;
                    }
                }
                fputs($this->smtp_conn,$line_out . $this->CRLF);
            }
        }

        # ok all the message data has been sent so lets get this
        # over with aleady
        fputs($this->smtp_conn, $this->CRLF . "." . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => "DATA not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }
        return true;
    }

    /**
     * Expand takes the name and asks the server to list all the
     * people who are members of the _list_. Expand will return
     * back and array of the result or false if an error occurs.
     * Each value in the array returned has the format of:
     *     [ <full-name> <sp> ] <path>
     * The definition of <path> is defined in rfc 821
     *
     * Implements rfc 821: EXPN <SP> <string> <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE FAILURE: 550
     * SMTP CODE ERROR  : 500,501,502,504,421
     * @access public
     * @return string array
     */
    function Expand($name) {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Expand() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"EXPN " . $name . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => "EXPN not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }

        # parse the reply and place in our array to return to user
        $entries = explode($this->CRLF,$rply);
        while(list(,$l) = @each($entries)) {
            $list[] = substr($l,4);
        }

        return $list;
    }

    /**
     * Sends the HELO command to the smtp server.
     * This makes sure that we and the server are in
     * the same known state.
     *
     * Implements from rfc 821: HELO <SP> <domain> <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE ERROR  : 500, 501, 504, 421
     * @access public
     * @return bool
     */
    function Hello($host="") {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Hello() without being connected");
            return false;
        }

        # if a hostname for the HELO wasn't specified determine
        # a suitable one to send
        if(empty($host)) {
            # we need to determine some sort of appopiate default
            # to send to the server
            $host = "localhost";
        }

        // Send extended hello first (RFC 2821)
        if(!$this->SendHello("EHLO", $host))
        {
            if(!$this->SendHello("HELO", $host))
                return false;
        }

        return true;
    }

    /**
     * Sends a HELO/EHLO command.
     * @access private
     * @return bool
     */
    function SendHello($hello, $host) {
        fputs($this->smtp_conn, $hello . " " . $host . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER: " . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => $hello . " not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }

        $this->helo_rply = $rply;

        return true;
    }

    /**
     * Gets help information on the keyword specified. If the keyword
     * is not specified then returns generic help, ussually contianing
     * A list of keywords that help is available on. This function
     * returns the results back to the user. It is up to the user to
     * handle the returned data. If an error occurs then false is
     * returned with $this->error set appropiately.
     *
     * Implements rfc 821: HELP [ <SP> <string> ] <CRLF>
     *
     * SMTP CODE SUCCESS: 211,214
     * SMTP CODE ERROR  : 500,501,502,504,421
     * @access public
     * @return string
     */
    function Help($keyword="") {
        $this->error = null; # to avoid confusion

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Help() without being connected");
            return false;
        }

        $extra = "";
        if(!empty($keyword)) {
            $extra = " " . $keyword;
        }

        fputs($this->smtp_conn,"HELP" . $extra . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 211 && $code != 214) {
            $this->error =
                array("error" => "HELP not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }

        return $rply;
    }

    /**
     * Starts a mail transaction from the email address specified in
     * $from. Returns true if successful or false otherwise. If True
     * the mail transaction is started and then one or more Recipient
     * commands may be called followed by a Data command.
     *
     * Implements rfc 821: MAIL <SP> FROM:<reverse-path> <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE SUCCESS: 552,451,452
     * SMTP CODE SUCCESS: 500,501,421
     * @access public
     * @return bool
     */
    function Mail($from) {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Mail() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"MAIL FROM:<" . $from . ">" . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => "MAIL not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }
        return true;
    }

    /**
     * Sends the command NOOP to the SMTP server.
     *
     * Implements from rfc 821: NOOP <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE ERROR  : 500, 421
     * @access public
     * @return bool
     */
    function Noop() {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Noop() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"NOOP" . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => "NOOP not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }
        return true;
    }

    /**
     * Sends the quit command to the server and then closes the socket
     * if there is no error or the $close_on_error argument is true.
     *
     * Implements from rfc 821: QUIT <CRLF>
     *
     * SMTP CODE SUCCESS: 221
     * SMTP CODE ERROR  : 500
     * @access public
     * @return bool
     */
    function Quit($close_on_error=true) {
        $this->error = null; # so there is no confusion

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Quit() without being connected");
            return false;
        }

        # send the quit command to the server
        fputs($this->smtp_conn,"quit" . $this->CRLF);

        # get any good-bye messages
        $byemsg = $this->get_lines();

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $byemsg;
        }

        $rval = true;
        $e = null;

        $code = substr($byemsg,0,3);
        if($code != 221) {
            # use e as a tmp var cause Close will overwrite $this->error
            $e = array("error" => "SMTP server rejected quit command",
                       "smtp_code" => $code,
                       "smtp_rply" => substr($byemsg,4));
            $rval = false;
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $e["error"] . ": " .
                         $byemsg . $this->CRLF;
            }
        }

        if(empty($e) || $close_on_error) {
            $this->Close();
        }

        return $rval;
    }

    /**
     * Sends the command RCPT to the SMTP server with the TO: argument of $to.
     * Returns true if the recipient was accepted false if it was rejected.
     *
     * Implements from rfc 821: RCPT <SP> TO:<forward-path> <CRLF>
     *
     * SMTP CODE SUCCESS: 250,251
     * SMTP CODE FAILURE: 550,551,552,553,450,451,452
     * SMTP CODE ERROR  : 500,501,503,421
     * @access public
     * @return bool
     */
    function Recipient($to) {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Recipient() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"RCPT TO:<" . $to . ">" . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250 && $code != 251) {
            $this->error =
                array("error" => "RCPT not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));

            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }
        return true;
    }

    /**
     * Sends the RSET command to abort and transaction that is
     * currently in progress. Returns true if successful false
     * otherwise.
     *
     * Implements rfc 821: RSET <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE ERROR  : 500,501,504,421
     * @access public
     * @return bool
     */
    function Reset() {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Reset() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"RSET" . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => "RSET failed",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }

        return true;
    }

    /**
     * Starts a mail transaction from the email address specified in
     * $from. Returns true if successful or false otherwise. If True
     * the mail transaction is started and then one or more Recipient
     * commands may be called followed by a Data command. This command
     * will send the message to the users terminal if they are logged
     * in.
     *
     * Implements rfc 821: SEND <SP> FROM:<reverse-path> <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE SUCCESS: 552,451,452
     * SMTP CODE SUCCESS: 500,501,502,421
     * @access public
     * @return bool
     */
    function Send($from) {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Send() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"SEND FROM:" . $from . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => "SEND not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }
        return true;
    }

    /**
     * Starts a mail transaction from the email address specified in
     * $from. Returns true if successful or false otherwise. If True
     * the mail transaction is started and then one or more Recipient
     * commands may be called followed by a Data command. This command
     * will send the message to the users terminal if they are logged
     * in and send them an email.
     *
     * Implements rfc 821: SAML <SP> FROM:<reverse-path> <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE SUCCESS: 552,451,452
     * SMTP CODE SUCCESS: 500,501,502,421
     * @access public
     * @return bool
     */
    function SendAndMail($from) {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                "error" => "Called SendAndMail() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"SAML FROM:" . $from . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => "SAML not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }
        return true;
    }

    /**
     * Starts a mail transaction from the email address specified in
     * $from. Returns true if successful or false otherwise. If True
     * the mail transaction is started and then one or more Recipient
     * commands may be called followed by a Data command. This command
     * will send the message to the users terminal if they are logged
     * in or mail it to them if they are not.
     *
     * Implements rfc 821: SOML <SP> FROM:<reverse-path> <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE SUCCESS: 552,451,452
     * SMTP CODE SUCCESS: 500,501,502,421
     * @access public
     * @return bool
     */
    function SendOrMail($from) {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                "error" => "Called SendOrMail() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"SOML FROM:" . $from . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250) {
            $this->error =
                array("error" => "SOML not accepted from server",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }
        return true;
    }

    /**
     * This is an optional command for SMTP that this class does not
     * support. This method is here to make the RFC821 Definition
     * complete for this class and __may__ be implimented in the future
     *
     * Implements from rfc 821: TURN <CRLF>
     *
     * SMTP CODE SUCCESS: 250
     * SMTP CODE FAILURE: 502
     * SMTP CODE ERROR  : 500, 503
     * @access public
     * @return bool
     */
    function Turn() {
        $this->error = array("error" => "This method, TURN, of the SMTP ".
                                        "is not implemented");
        if($this->do_debug >= 1) {
            echo "SMTP -> NOTICE: " . $this->error["error"] . $this->CRLF;
        }
        return false;
    }

    /**
     * Verifies that the name is recognized by the server.
     * Returns false if the name could not be verified otherwise
     * the response from the server is returned.
     *
     * Implements rfc 821: VRFY <SP> <string> <CRLF>
     *
     * SMTP CODE SUCCESS: 250,251
     * SMTP CODE FAILURE: 550,551,553
     * SMTP CODE ERROR  : 500,501,502,421
     * @access public
     * @return int
     */
    function Verify($name) {
        $this->error = null; # so no confusion is caused

        if(!$this->connected()) {
            $this->error = array(
                    "error" => "Called Verify() without being connected");
            return false;
        }

        fputs($this->smtp_conn,"VRFY " . $name . $this->CRLF);

        $rply = $this->get_lines();
        $code = substr($rply,0,3);

        if($this->do_debug >= 2) {
            echo "SMTP -> FROM SERVER:" . $this->CRLF . $rply;
        }

        if($code != 250 && $code != 251) {
            $this->error =
                array("error" => "VRFY failed on name '$name'",
                      "smtp_code" => $code,
                      "smtp_msg" => substr($rply,4));
            if($this->do_debug >= 1) {
                echo "SMTP -> ERROR: " . $this->error["error"] .
                         ": " . $rply . $this->CRLF;
            }
            return false;
        }
        return $rply;
    }

    /*******************************************************************
     *                       INTERNAL FUNCTIONS                       *
     ******************************************************************/

    /**
     * Read in as many lines as possible
     * either before eof or socket timeout occurs on the operation.
     * With SMTP we can tell if we have more lines to read if the
     * 4th character is '-' symbol. If it is a space then we don't
     * need to read anything else.
     * @access private
     * @return string
     */
    function get_lines() {
        $data = "";
        while($str = fgets($this->smtp_conn,515)) {
            if($this->do_debug >= 4) {
                echo "SMTP -> get_lines(): \$data was \"$data\"" .
                         $this->CRLF;
                echo "SMTP -> get_lines(): \$str is \"$str\"" .
                         $this->CRLF;
            }
            $data .= $str;
            if($this->do_debug >= 4) {
                echo "SMTP -> get_lines(): \$data is \"$data\"" . $this->CRLF;
            }
            # if the 4th character is a space then we are done reading
            # so just break the loop
            if(substr($str,3,1) == " ") { break; }
        }
        return $data;
    }

}

?>