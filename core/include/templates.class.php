<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
/**
 * The templates class contains all the logic involving print templates.  Added for trac #511.
 *
 */
class templates extends AJAX_library {
	
	public $error;
	public $error_msg;
	
	/**
	 * Constructor for templates class.  
	 *
	 * @param string $passedHash
	 * @return templates
	 */
	function templates($passedHash=NULL) {
		global $db;
		
		$this->form = new form;
		$this->db =& $db;
		$this->cust = new custom_fields(get_class($this));
	
		if ($passedHash)
			$this->current_hash = $passedHash;
		else 
			$this->current_hash = $_SESSION['id_hash'];
		
		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);
	}
	/**
	 * Fetch all data for a given template and store in this->current_template.
	 *
	 * @param string $content
	 * @param string $template_hash
	 */
	function fetch_master_record($content, $template_hash=NULL) {
		$result = $this->db->query("SELECT * 
								    FROM templates
								    WHERE `key` = '$content' AND ".($template_hash ? "
								    `template_hash` = '$template_hash'" : "`default` = '1'"));
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_template = $row;
			$result2 = $this->db->query("SELECT * 
										 FROM template_data
										 WHERE `template_hash` = '".$row['template_hash']."'");
			while ($row2 = $this->db->fetch_assoc($result2)) {
				$this->current_template[$row2['key']] = $row2;
				stripslashes(trim($this->current_template[$row['key']]['value']));
			}
		}
	}
	
	/**
	 * Fetch only the print prefs string for the given print_pref_hash.
	 *
	 * @param string $content
	 * @param string $print_pref_hash
	 */
	function fetch_print_prefs($content, $print_pref_hash) {
		$result = $this->db->query("SELECT print_prefs 
								    FROM templates
								    WHERE `key` = '$content' AND 
								    `template_hash` = '$print_pref_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			return $row['print_prefs'];
		}
		//TODO throw error
		return false;
	}
	/**
	 * Create the drop down box for selecting both templates and saved print preferences.  The drop down 
	 * for templates only exists if more than one template is present.  The saved print preferences pulldown
	 * always exists and only contains "Your Print Preferences" if no saved print prefs exist.  
	 *
	 * @param string $content
	 * @param int $num_rows
	 * @param int $z
	 * @return string $select
	 */
	function template_select($content, $num_rows, $z=NULL, $table=NULL, $popup_id=NULL, $proposal_hash=NULL, $hash2=NULL) {
		if ($content == 'invoice_print')
			$invoice_hash = $hash2;
		else 
			$po_hash = $hash2;
		
		$print_pref = fetch_user_data($content);
		$print_pref = __unserialize(stripslashes($print_pref));
		$show_delete = false; 
		
		// Fetch saved templates and print preferences 
		$result = $this->db->query("SELECT *
									FROM templates t
									WHERE `key` = '$content' AND (`public` = '1' OR `owner_hash` = '".$this->current_hash."') AND `active` = '1'");
		while ($row = $this->db->fetch_assoc($result)) {
			// Only show the delete icon if one of more of the saved templates is owned by the current user
			if ($this->current_hash == $row['owner_hash'])
				$show_delete = true;
			if (!$row['prefs_only']) {
				$templates_in[] = $row['template_hash'];
				$templates_out[] = stripslashes($row['template_name']);
			}
			if ($row['default']) 
				$templates_default = $row['template_hash'];
			
			if ($row['print_prefs']) {
				$prefs_in[] = $row['template_hash'];
				$prefs_out[] = stripslashes($row['template_name']);
				if ($row['default']) 
					$prefs_default = $row['template_hash'];
			}		
		}
		
		if ($print_pref['default_template']) {
			$templates_default = $print_pref['default_template']; 
		}
		if ($print_pref['default_prefs']) {
			$prefs_default = $print_pref['default_prefs'];
		}

		// Find out which permissions class to use
		switch ($content) {
			case 'proposal_print':
			$this->perm_class = "proposals";
			break;
			
			case 'proforma_invoice':
			case 'invoice_print':
			$this->perm_class = "customer_invoice";
			break;
			
			case 'purchase_order':
			case 'delivery_ticket_print':
			$this->perm_class = "purchase_order";
			break;
			
		}
		
		// Add custom print preferences if user has permissions or if no saved prefs exist.	
		if ($this->p->ck($this->perm_class,'P') || !$prefs_in) {
			if (is_array($prefs_in)) {
				$custom_in = array("custom");
				$custom_out = array("Select my print options".($num_rows > 0 ? " below" : NULL));
				$prefs_in = array_merge($custom_in,$prefs_in);
				$prefs_out = array_merge($custom_out,$prefs_out);
			} else {
				$prefs_in = array("custom");
				$prefs_out = array("Select my print options".($num_rows > 0 ? " below" : NULL));
			}
		}
		
		// Create the toggle display statements for each print pref
		for($i=0;$i<$num_rows;$i++) {
			$block .= "toggle_display('print_pref".($i+1)."','block');";
			$none .= "toggle_display('print_pref".($i+1)."','none');";
		}
		
		if ($num_rows > 0) {
			$block .= "toggle_display('save_prefs','block');";
			$none .= "toggle_display('save_prefs','none');";
		}
		
		// Show templates drop down if more than 1 saved template exists
		if (count($templates_in) > 1) {
			if ($table)
                $select = array($this->form->select("use_template$z",
		                                            $templates_out, 
		                                            $templates_default, 
		                                            $templates_in, 
		                                            "blank=1"));			 
			else
				$select = "
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;padding-top:10px;\">Print Template:</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">
						".$this->form->select("use_template$z",
				                              $templates_out, 
				                              $templates_default, 
				                              $templates_in, 
				                              "blank=1")."
					</td>
				</tr>";
		}
		
		// Show saved print prefs drop down
		if ($table)
            $select[] = $this->form->select("use_prefs$z", 
                                            $prefs_out, 
                                            $prefs_default, 
                                            $prefs_in,
                                            "blank=1",
                                            "style=width:".($num_rows > 0 ? "215px;" : "175px;"),
                                            "onChange=if(this.options[this.selectedIndex].value=='custom'){".$block."}else{".$none."}");            		  
		else
			$select .= "
			<tr>
				<td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;vertical-align:top;padding-top:10px;\">Print Prefs:</td>
				<td style=\"background-color:#ffffff;padding-top:10px;\" nowrap>
					".$this->form->select("use_prefs$z", 
			                              $prefs_out, 
			                              $prefs_default, 
			                              $prefs_in,
			                              "blank=1",
			                              "style=width:".($num_rows > 0 ? "215px;" : "175px;"),
			                              "onChange=if(this.options[this.selectedIndex].value=='custom'){".$block."}else{".$none."}").($show_delete ? "	
						&nbsp;<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"submit_form(\$('use_prefs$z').form,'templates','exec_post','refresh_form','action=doit_delete_template','template_hash='+\$('use_prefs$z').options[\$('use_prefs$z').selectedIndex].value,'popup_id=$popup_id','proposal_hash=$proposal_hash'".($invoice_hash ? ",'invoice_hash=$invoice_hash'" : NULL).($po_hash ? ",'po_hash=$po_hash'" : NULL).");\"><img src=\"images/rm_lineitem.gif\" border=\"0\" title=\"Delete this saved template.\" /></a>" : NULL)."
				</td>
			</tr>";
			                              
		return $select;
	}
	/**
	 * Create the html row for saving print preferences.   
	 *
	 * @param boolean $display - Whether or not the row should start off being displayed
	 * @return string $tr
	 */
	function save_print_prefs($display=NULL) {
		// If user doesn't have permissions to create/add saved print prefs, then return nothing.
		if (!$this->p->ck($this->perm_class,'P')) 
			return;
			 
		// Create the html tr for saving print preferences	
		$tr = "<tr id=\"save_prefs\" ".($display ? "" : "style=\"display:none").";\">
					<td style=\"text-align:right;background-color:#efefef;vertical-align:top;\">Save These Print <br />Preferences?</td>
					<td style=\"background-color:#ffffff;\">
						".$this->form->checkbox("name=save","value=1","onClick=toggle_display('saved_prefs_name',this.checked?'block':'none');toggle_display('saved_prefs_descr',this.checked?'block':'none');")."
						<div style=\"padding-top:5px;display:none;\" id=\"saved_prefs_name\">
							What should this set of print preferences be called?
							<br />
							".$this->form->text_box("name=prefs_name",
													"value=",
													"autocomplete=off",
													"size=30",
													"style=margin-top:5px;margin-bottom:10px;"
													)."
						</div>
						<div style=\"padding-top:5px;display:none;\" id=\"saved_prefs_descr\">
							Optional description:
							<br />
							".$this->form->text_area("name=prefs_descr",
													"value=",
													"rows=3",
													"cols=50",
													"style=margin-top:5px;"
													)."
							<div style=\"padding-top:5px\">														
								".$this->form->checkbox("name=prefs_public","value=1","checked")."&nbsp;Make these public?
							</div>
						</div>
					</td>
				</tr>";
		return $tr;
	
	}
	/**
	 * Doit function for error checking and implementing the backend portion of saving print preferences.
	 *
	 * @param string $content
	 * @param string $print_prefs_str
	 */
	function doit_save_prefs($content,$print_prefs_str) {
		$this->error = 0;
		$save = $_POST['save'];
		$prefs_name = $_POST['prefs_name'];
		$prefs_descr = $_POST['prefs_descr'];
		$prefs_public = $_POST['prefs_public'];
		
		// Check for duplicates
		if ($save) {
			if (!$prefs_name) {
				$this->error = 1;
				$this->error_msg = "In order to save these print preferences, you must give this set a name.";
				return;
			}
			
			$result = $this->db->query("SELECT COUNT(*) FROM templates 
										WHERE template_name = '".addslashes($prefs_name)."' AND `key` = '$content'");
			if ($this->db->result($result)) {
				$this->error = 1;
				$this->error_msg = "That name is already in use.  Please make sure you're not making a duplicate.";
				return;
			}
			
			// Insert saved print template into the database 
			$prefs_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			while (global_classes::key_exists('templates','template_hash',$prefs_hash))
				$prefs_hash = md5(global_classes::get_rand_id(32,"global_classes"));
			$this->db->query("INSERT INTO `templates` (`template_hash`, `owner_hash`, `key`, `template_name`, `template_descr`, `default`, `active`, `public` , `prefs_only` , `font_style`, `print_prefs`) VALUES
					('$prefs_hash', '".$this->current_hash."', '$content', '".addslashes($prefs_name)."' , '".addslashes($prefs_descr)."' , 0 , 1, '$prefs_public' , 1 , '', '$print_prefs_str') ");
			
		}
		
	}
	/**
	 * Delete template if current user is the owner
	 *
	 * @param string $template_hash
	 */
	function doit_delete_template($template_hash=NULL,$popup_id=NULL) {
		$template_hash = ($template_hash ? $template_hash : $_POST['template_hash']);
		$popup_id = ($popup_id ? $popup_id : $_POST['popup_id']);
		$id_hash = $this->current_hash;
		$result = $this->db->query("SELECT * FROM templates where template_hash = '$template_hash'");
		$row = $this->db->fetch_assoc($result);
		$template_name = stripslashes($row['template_name']);
		$key = $row['key'];
		
		// Check if template_hash is for custom print prefs
		if ($template_hash == "custom") {
			$this->content['page_feedback'] = "Invalid option! You must select a template to delete.";
			$this->content['action'] = 'continue';
			return;
		}
		
		// Find the class and method for the template
		switch($key) {
			case "proposal_print":
			case "proposal_punch_print":
				$class = "proposals";
				$method = "print_proposal";
			break;
			
			case 'delivery_ticket_print':
				$class = "purchase_order";
				$method = "print_tickets";
			break;
			
			case 'proforma_invoice':
				$class = "customer_invoice";
				$method = "new_proforma";
			break;
			
			case 'invoice_print':
				$class = "customer_invoice";
				$method = "print_invoice";
			break;
			
		}

		// Check if user is the owner
		if ($id_hash != $row['owner_hash']) {
			$this->content['page_feedback'] = "You must be the owner of \"$template_name\" in order to delete it.";
			$this->content['action'] = 'continue';
			return;
		}
		
		// Delete template
		$this->db->query("DELETE FROM templates WHERE `template_hash` = '$template_hash'");
		
		// Update print preferences to have custom be the default template
		if ($print_pref = fetch_user_data($key)) { 
			$print_pref = __unserialize(stripslashes($print_pref));
			$print_pref['default_prefs'] = 'custom';
			$print_prefs_str = addslashes(serialize($print_pref));
			store_user_data($key,$print_prefs_str);	
		}
		
		$this->content['page_feedback'] = "The template \"$template_name\" has been deleted.";
		$this->content['action'] = 'close';
		$this->content['jscript_action'] = "agent.call('$class','sf_loadcontent','show_popup_window','$method','proposal_hash=".$this->proposal_hash."','popup_id=".$this->popup_id."'".($this->invoice_hash ? ",'invoice_hash=".$this->invoice_hash."'" : NULL).($this->po_hash ? ",'po_hash=".$this->po_hash."'" : NULL).");";
	}
/**
 * General doit function that calls other doit functions
 *
 * @return unknown
 */
	function doit() {
		global $err,$errStr;
		
		$this->check = new Validator;
		
		$action = $_POST['action'];
		$btn = $_POST['actionBtn'];

		$this->popup_id = $_POST['popup_id'];	
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		$this->proposal_hash = $_POST['proposal_hash'];
		$this->po_hash = $_POST['po_hash'];
		$this->invoice_hash = $_POST['invoice_hash'];
		
		$active_search = $_POST['active_search'];
		if ($active_search)
			$this->active_search = $active_search;
		
		if ($action)
			return $this->$action();
			
		return;
	}	
	/**
	 * Return true if there are no active print prefs templates to display, return false otherwise
	 *
	 * @param string $content
	 * @return boolean 
	 */
	function show_custom_prefs($content) {
		// Find number of active print prefs templates 
		$result = $this->db->query("SELECT COUNT(*) as count
									FROM templates t
									WHERE `key` = '$content' AND (`public` = '1' OR `owner_hash` = '".$this->current_hash."') 
									AND `active` = '1' AND `print_prefs` <> ''");
		$count = $this->db->result($result,0,'count');
		return ($count > 0 ? false : true);
	}
}
?>