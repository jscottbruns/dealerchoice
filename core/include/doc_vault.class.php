<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed

class doc_vault extends AJAX_library {
	
	var $current_doc = array();
	var $proposal_docs = array();
	var $total;
	
	function doc_vault() {
		global $db;
	
		$this->form = new form;
		$this->db =& $db;
		$this->content['class_name'] = get_class($this);

		$this->current_hash = $_SESSION['id_hash'];
	}
	
	function fetch_files($proposal_hash,$order_by=NULL,$order_dir=NULL) {
		$this->proposal_docs = array();
		
		$result = $this->db->query("SELECT doc_vault.timestamp , doc_vault.doc_hash , doc_vault.public , doc_vault.checkout , doc_vault.checkout_time , 
									doc_vault.file_name , doc_vault.file_type , doc_vault.content_type ,
									doc_vault.descr , doc_vault.filesize , users.full_name as file_owner
						  			FROM `doc_vault`
									LEFT JOIN users ON users.id_hash = doc_vault.checkout
						  			WHERE `proposal_hash` = '".$proposal_hash."'".($order ? "
							  		ORDER BY $order ".($order_dir ? $order_dir : "ASC") : NULL));
		
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->proposal_docs,$row);
		
		$this->total = count($this->proposal_docs);
		return;
	}
	
	function doit_exec_file() {
		$doc_hash = $_POST['doc_hash'];
		$from = $_POST['from'];
		$proposal_hash = $_POST['proposal_hash'];
		$jscript_action = $_POST['jscript_action'];
		$doc_action = $_POST['doc_action'];
		
		if ($doc_action == 'rm') {
			$this->db->query("DELETE FROM `doc_vault`
							  WHERE `doc_hash` = '$doc_hash'");
			
			$this->content['page_feedback'] = "Your file has been deleted.";
		} elseif ($doc_action == 'checkout') {
			$this->content['jscript'] = "window.open('download.php?doc_hash=".$doc_hash."');";
		}
		$this->content['action'] = 'continue';
		if ($from == 'pm_module') {
			$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','doc_vault','otf=1','proposal_hash=$proposal_hash','popup_id=".$this->popup_id."');";
		} else
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('proposals','sf_loadcontent','cf_loadcontent','doc_vault','otf=1','proposal_hash=$proposal_hash');");
		return;
	}
	
	function fetch_data($doc_hash) {
		$result = $this->db->query("SELECT `data`
									FROM `doc_vault`
							  		WHERE `doc_hash` = '$doc_hash'");
		return $this->db->result($result);
	}
	
	function fetch_doc_record($doc_hash) {
		$this->current_doc = array();
		$result = $this->db->query("SELECT `timestamp` , `public`, `last_change` , `checkout` , `checkout_time` , `proposal_hash` , `file_name` , `file_type` , `content_type` , `descr` , `filesize`
									FROM `doc_vault`
									WHERE `doc_hash` = '$doc_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_doc = $row;
			$this->doc_hash = $doc_hash;
			return true;
		}
		
		return false;
	}
	
	function view_file() {
		$doc_hash = $this->ajax_vars['doc_hash'];
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		$checkout = $this->ajax_vars['checkout'];
		
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];
		
		$result = $this->db->query("SELECT `file_name` , `content_type`
									FROM `doc_vault`
									WHERE `doc_hash` = '$doc_hash'");
		$row = $this->db->fetch_assoc($result);
		
		$this->content['popup_controls']['popup_id'] = $this->popup_id;
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = $row['file_name'];
		
		$tbl = "
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0;\">
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"width:100%;height:100%;\">
				<tr>
					<td style=\"padding:0;\">
						 <div style=\"background-color:#ffffff;height:100%;\">
						 	<div style=\"display:block;width:100%;text-align:center;padding:55px 0;\">
								<h3 style=\"color:#00477f;margin-bottom:5px;display:block;\" id=\"link_fetch".$this->popup_id."\">Fetching Your File</h3>
								<h3 style=\"color:#00477f;margin-bottom:5px;display:none;\" id=\"link_have".$this->popup_id."\">File Retrieved</h3>
								<div id=\"link_img_holder".$this->popup_id."\" style=\"display:block;\"><img src=\"images/file_loading.gif\" /></div>
								<div style=\"padding-top:5px;display:none;\" id=\"link_holder".$this->popup_id."\">
									<img src=\"images/file_save.gif\" border=\"0\" alt=\"Click the link to download.\" />
									&nbsp;&nbsp;
									<a href=\"download.php?doc_hash=".$doc_hash.($checkout ? "&checkout_hash=".$_SESSION['id_hash'] : NULL)."\" class=\"link_standard\" onClick=\"setTimeout('popup_windows[\'".$this->popup_id."\'].hide();agent.call(\'proposals\',\'sf_loadcontent\',\'cf_loadcontent\',\'doc_vault\',\'otf=1\',\'proposal_hash=$proposal_hash\');',3000);\" target=\"_blank\">".$row['file_name']."</a>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>";
		
		$this->content['jscript'][] = "setTimeout(\"toggle_display('link_img_holder".$this->popup_id."','none');toggle_display('link_holder".$this->popup_id."','block');toggle_display('link_fetch".$this->popup_id."','none');toggle_display('link_have".$this->popup_id."','block');\",2000);";
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}
	
	function doit_doc_vault() {
		$doc_hash = $_POST['doc_hash'];
		$public = $_POST['public'];
		$file_name = $file_loc = $_POST['file_name'];
		$file_size = $_POST['file_size'];
		$file_type = $content_type = $_POST['file_type'];
		$file_error = $_POST['file_error'];
		$file_descr = $_POST['file_descr'];
		$current_hash = $_POST['current_id_hash'];
		$proposal_hash = $_POST['proposal_hash'];
		$upload_error = $_POST['error'];
		
		if ($file_error || $upload_error) {
			$this->content['jscript'] = "remove_element('progress_holder','progress_div');remove_element('iframe');create_element('iframe','iframe','src=upload.php?proposal_hash=".$this->proposal_hash."&class=doc_vault&action=doit_doc_vault&method_action=".$action."&current_hash=".$this->current_hash."&popup_id=".$this->popup_id."','frameBorder=0','height=50px');toggle_display('iframe','block');";
			$this->content['form_return']['feedback'] = ($file_error ? 
				"There was a problem with the file you imported. Please check to make sure the file is valid and not damaged and try again." : "There was a problem uploading your file. The server timed out and wouldn't accept the file. Try uploading again.");
			$this->content['error'] = 1;
			return;
		} else {
			if ($doc_hash) {
				$this->fetch_doc_record($doc_hash);
				if ($this->current_doc['content_type'] != $file_type || $this->current_doc['file_type'] != strtoupper(substr(strrchr($file_name,"."),1))) {
					$this->content['error'] = 1;
					$this->content['jscript'] = "remove_element('progress_holder','progress_div');remove_element('iframe');create_element('iframe','iframe','src=upload.php?proposal_hash=".$this->proposal_hash."&class=doc_vault&action=doit_doc_vault&method_action=".$action."&current_hash=".$this->current_hash."&popup_id=".$this->popup_id."','frameBorder=0','height=30px');toggle_display('iframe','block');";
					$this->content['form_return']['feedback'] = "The file you are checking in is not the same type as the file you checked out. Please make sure you are checking in the correct file. If this is a new file please save it as a new file.";
					return;
				}
				$data = addslashes(fread(fopen($file_name,'r'),filesize($file_name)));
				$file_type = strtoupper(substr(strrchr($file_name,"."),1));
				if (substr($file_name,0,1) == 'C' || substr($file_name,0,1) == 'c')
					$file_name = substr(strrchr($file_name,"/"),1);
				else
					$file_name = substr(strrchr($file_name,"\\"),1);
				$file_name = basename($file_name);
				$this->db->query("UPDATE `doc_vault`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `public` = '$public' , `checkout` = '' , `checkout_time` = '' , `file_name` = '".($file_name ? $file_name : $this->current_doc['file_name'])."' , `descr` = '$file_descr' , `filesize` = '$file_size' , `data` = '$data'
								  WHERE `doc_hash` = '$doc_hash'");
				unlink($file_loc);
				$this->content['page_feedback'] = "Your file has been checked back in.";
			} else {
				$doc_hash = md5(global_classes::get_rand_id(32,"global_classes"));
				while (global_classes::key_exists('doc_vault','doc_hash',$doc_hash))
					$doc_hash = md5(global_classes::get_rand_id(32,"global_classes"));
				
				$handle = fopen($file_name,'r');
				$data = addslashes(fread($handle,filesize($file_name)));
				fclose($handle);
				$file_type = strtoupper(substr(strrchr($file_name,"."),1));
				if (substr($file_name,0,1) == 'C' || substr($file_name,0,1) == 'c')
					$file_name = substr(@strrchr($file_name,"/"),1);
				elseif (strrchr($file_name,"\\"))
					$file_name = substr(@strrchr($file_name,"\\"),1);
				else
					$file_name = basename($file_name);
				
				$this->db->query("INSERT INTO `doc_vault`
								  (`timestamp` , `last_change` , `doc_hash` , `public` , `proposal_hash` , `file_name` , `file_type` , `content_type` , `descr` , `filesize` , `data`)
								  VALUES (".time()." , '$current_hash' , '$doc_hash' , '$public' , '$proposal_hash' , '$file_name' , '$file_type' , '$content_type' , '$file_descr' , '$file_size' , '$data')");
				unlink($file_loc);
				
				
				$this->content['page_feedback'] = "Your file has been saved to this proposal.";
			}
			if ($_POST['from'] == 'pm_module') {
				$this->content['action'] = 'continue';
				$this->content['jscript_action'] = "agent.call('pm_module','sf_loadcontent','cf_loadcontent','doc_vault','otf=1','proposal_hash=$proposal_hash','popup_id=".$this->popup_id."');";
			} else {
				$this->content['action'] = 'close';
				$this->content['jscript_action'] = "agent.call('proposals','sf_loadcontent','cf_loadcontent','doc_vault','otf=1','proposal_hash=$proposal_hash');";
			}
			return;
		}
	}
	
	function doit() {
		$action = $_POST['action'];
		$this->popup_id = $_POST['popup_id'];	
		$this->content['popup_controls']['popup_id'] = $this->popup_id;
		
		if ($action)
			return $this->$action();
		
	}
	
	function new_doc() {
		$current_hash = $this->ajax_vars['current_hash'];
		$proposal_hash = $this->ajax_vars['proposal_hash'];
		if ($doc_hash = $this->ajax_vars['doc_hash'])
			$this->fetch_doc_record($doc_hash);
			
		if ($this->ajax_vars['popup_id'])
			$this->popup_id = $this->ajax_vars['popup_id'];

		$this->content['popup_controls']['popup_id'] = $this->popup_id;
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($doc_hash ? "Check Your File Back In" : "Upload & Save a File");
		
		$tbl = $this->form->form_tag().
		$this->form->hidden(array("proposal_hash" => $proposal_hash, "doc_hash" => $doc_hash, "popup_id" => $this->popup_id))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0;\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"padding:0;\">
						 <div style=\"background-color:#ffffff;height:100%;\">
							<div style=\"margin:15px 35px;\">
								<table >
									<tr>
										<td >
											<div style=\"padding-bottom:15px;\">
												Use this window to save files and documents to your proposal. Maximum file size is 32MB. Larger files
												may take longer to save. Feel free to minimize this window and continue working while your file is 
												being saved.
											</div>
										</td>
									</tr>
									<tr>
										<td >
											<div style=\"padding:5px 0;font-weight:bold;\" id=\"err1".$this->popup_id."\">File Description: </div>
											<div>".$this->form->text_area("name=file_descr","value=".stripslashes($this->current_doc['descr']),"cols=45","rows=3")."</div>
										</td>
									</tr>
									<tr>
										<td >
											<div style=\"padding:5px 0;font-weight:bold;\" id=\"err2".$this->popup_id."\">Make this file public? </div>
											<div>".$this->form->checkbox("name=public","value=1","title=Make this file available to view by your customers.",($this->current_doc['public'] ? "checked" : NULL))."</div>
										</td>
									</tr>
									<tr>
										<td >
											<div style=\"padding:5px 0;font-weight:bold;\" id=\"err3".$this->popup_id."\">File: </div>
											<div id=\"iframe\"><iframe src=\"upload.php?proposal_hash=".$proposal_hash."&class=doc_vault&action=doit_doc_vault&current_hash=".$current_hash."&popup_id=".$this->popup_id."\" frameborder=\"0\" style=\"height:50px;\"></iframe></div>
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
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();;
        $this->content['action'] = 'close';
		
		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}
}