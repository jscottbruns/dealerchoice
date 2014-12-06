<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
//subs.class.php
class arch_design extends AJAX_library {

	public $total;
	public $total_contacts;

	public $arch_info = array();
	public $arch_contacts = array();

	public $current_arch;
	public $current_contact;

	public $content = '';
	public $ajax_vars;

	private $form;
	private $check;

	function arch_design($passedHash=NULL) {
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

		$result = $this->db->query("SELECT COUNT(*) AS Total
							  		FROM `arch_design`
							  		WHERE `deleted` = 0");
		$this->total = $this->db->result($result);

	}

	function __destruct() {
		$this->content = '';
	}

	function fetch_archs($start,$order_by=NULL,$order_dir=NULL) {
		$end = MAIN_PAGNATION_NUM;

		if ($this->active_search) {
			$result = $this->db->query("SELECT `query` , `total`
								  		FROM `search`
								  		WHERE `search_hash` = '".$this->active_search."'");
			$row = $this->db->fetch_assoc($result);
			$this->total = $row['total'];
			$sql = base64_decode($row['query']);
		}

		$result = $this->db->query("SELECT arch_design.*
								    FROM `arch_design`
								    WHERE ".($sql ? $sql." AND " : NULL)."arch_design.deleted = 0".($order_by ? "
								    ORDER BY $order_by ".($order_dir ? $order_dir : "ASC") : NULL)."
								    LIMIT $start , $end");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->arch_info,$row);
	}

	function fetch_master_record($arch_hash,$edit=false) {

		$result = $this->db->query("SELECT arch_design.*
								    FROM `arch_design`
								    WHERE arch_design.arch_hash = '$arch_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_arch = $row;
			$this->arch_hash = $arch_hash;

			$result = $this->db->query("SELECT COUNT(*) AS Total
								  		FROM `arch_contacts`
								  		WHERE `arch_hash` = '".$this->current_arch['arch_hash']."' AND `deleted` = 0");
			$this->total_contacts = $this->db->result($result);

			if ($edit)
				$this->lock = $this->content['row_lock'] = $this->p->lock("arch_design",$this->arch_hash,$this->popup_id);

			return true;
		}

		return false;
	}


	function fetch_contacts() {

		if ( $param = func_get_arg(0) ) {

        	$start = $param['start_from'];
			$end = $param['end'];
			$order_by = $param['order_by'];
			$order_dir = $param['order_dir'];
        }

        if ( ! $end )
			$end = SUB_PAGNATION_NUM;

		$this->arch_contacts = array();
		$_total = 0;

		$result = $this->db->query("SELECT COUNT(*) AS Total
							  		FROM `arch_contacts`
							  		WHERE `arch_hash` = '{$this->arch_hash}' AND `deleted` = 0");
		$this->total_contacts = $this->db->result($result, 0, 'Total');

		if ( ! $this->total_contacts )
			return 0;

		$result = $this->db->query("SELECT arch_contacts.*
							  		FROM `arch_contacts`
							  		WHERE `arch_hash` = '{$this->arch_hash}' AND `deleted` = 0 " .
							  		( $order_by ?
								  		"ORDER BY $order_by " .
								  		( $order_dir ?
									  		$order_dir : "DESC"
									  	) : NULL
									) . "
							  		LIMIT $start, $end");
		while ( $row = $this->db->fetch_assoc($result) ) {

			$_total++;
			array_push($this->arch_contacts, $row);
		}

		return $_total;
	}

	function fetch_contact_record($contact_hash) {
		$result = $this->db->query("SELECT arch_contacts.*
							  		FROM `arch_contacts`
							  		WHERE arch_contacts.contact_hash = '$contact_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_contact = $row;

			return true;
		}

		return false;
	}



	function do_search() {

	}


	function doit_arch_contact() {

        if ( func_num_args() > 0 ) {

            $arch_hash = func_get_arg(0);
            $new_arch = func_get_arg(1);
        }

        if ( ! $new_arch && ! $this->current_arch )
            $this->fetch_master_record($arch_hash);

        if ( $contact_hash = $_POST['contact_hash'] ) {

            if ( ! $this->fetch_contact_record($contact_hash) )
                return $this->__trigger_error("A system error was encountered when trying to lookup A&D contact for database update. Please re-load A&D window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

            $edit_contact = true;
            $action_delete = $_POST['delete'];
        }

		$contact_name = addslashes( trim($_POST['contact_name']) );
		if ( ! $contact_name )
			return false;

		$contact_title = addslashes( trim($_POST['contact_title']) );
		$contact_phone = $_POST['contact_phone'];
		$contact_phone2 = $_POST['contact_phone2'];
		$contact_mobile = $_POST['contact_mobile'];
		$contact_fax = $_POST['contact_fax'];
		$contact_email = $_POST['contact_email'];

		if ( $contact_email && !$this->check->is_email($contact_email) ) {

			$err = 1;
			$this->content['form_return']['feedback'][] = $this->check->ERROR;
			$this->content['form_return']['err']["err3_7{$this->popup_id}"] = 1;
		}

		if ( $contact_fax && !$this->check->is_phone($contact_fax) ) {

			$err = 1;
			$this->content['form_return']['feedback'][] = $this->check->ERROR;
			$this->content['form_return']['err']["err3_6{$this->popup_id}"] = 1;
		}

        if ( $err )
            return ( $new_arch ? false : $this->__trigger_error( ( is_array($this->content['form_return']['feedback']) ? implode("<br />", $this->content['form_return']['feedback']) : "Error encountered: {$this->content['form_return']['feedback']}"), E_USER_NOTICE, __FILE__, __LINE__, 1) );

        if ( ! $new_arch ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
        }

		if ( $edit_contact ) {

			if ( $action_delete ) {

				if ( ! $this->db->query("UPDATE arch_contacts t1
        				                 SET t1.deleted = 1, t1.deleted_date = CURDATE()
        								 WHERE t1.contact_hash = '$contact_hash'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				$this->content['page_feedback'] = "Contact has been deleted";

			} else {

				$sql = array();

				( $contact_name != $this->current_contact['contact_name'] ? array_push($sql, "contact_name = '$contact_name'") : NULL );
				( $contact_title != $this->current_contact['contact_title'] ? array_push($sql, "contact_title = '$contact_title'") : NULL );
				( $contact_phone != $this->current_contact['contact_phone'] ? array_push($sql, "contact_phone1 = '$contact_phone'") : NULL );
				( $contact_phone2 != $this->current_contact['contact_phone2'] ? array_push($sql, "contact_phone2 = '$contact_phone2'") : NULL );
				( $contact_mobile != $this->current_contact['contact_mobile'] ? array_push($sql, "contact_mobile = '$contact_mobile'") : NULL );
				( $contact_fax != $this->current_contact['contact_fax'] ? array_push($sql, "contact_fax = '$contact_fax'") : NULL );
				( $contact_email != $this->current_contact['contact_email'] ? array_push($sql, "contact_email = '$contact_email'") : NULL );

				if ( $sql ) {

					if ( ! $this->db->query("UPDATE arch_contacts
        									 SET
            									 timestamp = UNIX_TIMESTAMP(), last_change = '{$this->current_hash}',
            									 " . implode(", ", $sql) . "
        									 WHERE contact_hash = '$contact_hash'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					$this->content['page_feedback'] = "Contact has been updated";

				} else
					$this->content['page_feedback'] = "No changes have been made";
			}

			unset($this->current_contact);

		} else {

			$contact_hash = rand_hash('arch_contacts', 'contact_hash');
			if ( ! $this->db->query("INSERT INTO arch_contacts
        							 VALUES
        							 (
            						 	 NULL, UNIX_TIMESTAMP(), '{$this->current_hash}', '$arch_hash', '$contact_hash', 0, NULL, '$contact_name', '$contact_title', '$contact_phone', '$contact_phone2', '$contact_mobile', '$contact_fax', '$contact_email'
                    				 )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

			$this->content['page_feedback'] = "Your new contact has been added.";
		}

        if ( $new_arch )
            return true;

        $this->db->end_transaction();

		unset($this->ajax_vars);
		if ( $arch_hash ) {

			$this->fetch_master_record($arch_hash);
			$this->ajax_vars['arch_hash'] = $arch_hash;
		}
		$this->content['action'] = 'continue';
		$this->content['html']["tcontent2{$this->popup_id}"] = $this->contact_info_form();

		return;

	}

	function doit_search() {
		$name = $_POST['name'];

		if ($name)
			$sql_p[] = "arch_design.name LIKE '".$name."%'";
		if ($sql_p)
			$sql = implode(" AND ",$sql_p);

		$r = $this->db->query("SELECT COUNT(*) AS Total
							   FROM `arch_design`
							   WHERE ".($sql ? $sql." AND " : NULL)."arch_design.deleted = 0");
		$total = $this->db->result($r);

		$search_hash = md5(global_classes::get_rand_id(32,"global_classes"));
		while (global_classes::key_exists('search','search_hash',$search_hash))
			$search_hash = md5(global_classes::get_rand_id(32,"global_classes"));

		$this->db->query("INSERT INTO `search`
						  (`timestamp` , `search_hash` , `query` , `total` , `search_str`)
						  VALUES (".time()." , '$search_hash' , '".base64_encode($sql)."' , '$total' , '$str')");

		$this->active_search = $search_hash;

		$this->content['action'] = 'close';
		$this->content['jscript_action'] = "agent.call('arch_design','sf_loadcontent','cf_loadcontent','showall','active_search=".$this->active_search."');";
		return;
	}

	function doit() {

		$btn = $_POST['actionBtn'];
		$p = $_POST['p'];
		$order = $_POST['order'];
		$order_dir = $_POST['order_dir'];
		$action = $_POST['action'];
		$arch_hash = $_POST['arch_hash'];
		$jscript_action = $_POST['jscript_action'];
		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		$active_search = $_POST['active_search'];
		if ($active_search)
			$this->active_search = $active_search;

		if ($action)
			return $this->$action();

		if ($btn == 'AddContactLine')
			$this->doit_arch_contact($arch_hash);

		if ( $btn == "Add Firm" || $btn == "Update Firm" ) {

			if ( $_POST['name'] && $_POST['street'] && $_POST['city'] && $_POST['state'] && $_POST['zip'] && $_POST['phone'] ) {

				# General info
				$name = addslashes($_POST['name']);
				$street = addslashes($_POST['street']);
				$city = addslashes($_POST['city']);
				$state = $_POST['state'];
				$zip = $_POST['zip'];
				$country = $_POST['country'];
				$phone = $_POST['phone'];
				$fax = $_POST['fax'];

				if ( ! $this->check->is_phone($phone) ) {

					$err = 1;
					$this->content['form_return']['feedback'][] = $this->check->ERROR;
					$this->content['form_return']['err']["err1_7{$this->popup_id}"] = 1;
				}

				if ( $fax && ! $this->check->is_phone($fax) ) {

					$err = 1;
					$this->content['form_return']['feedback'][] = $this->check->ERROR;
					$this->content['form_return']['err']["err1_8{$this->popup_id}"] = 1;
				}

				if ( ! $this->check->is_zip($zip) ) {

					$err = 1;
					$this->content['form_return']['feedback'][] = $this->check->ERROR;
					$this->content['form_return']['err']["err1_6{$this->popup_id}"] = 1;
				}

				if ( $err )
					return $this->__trigger_error( ( is_array($this->content['form_return']['feedback']) ? implode("<br />", $this->content['form_return']['feedback']) : "Errors encountered: {$this->content['form_return']['feedback']}" ), E_USER_NOTICE, __FILE__, __LINE__, 1);

                if ( ! $this->db->start_transaction() )
                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

				if ( $btn == "Add Firm" ) {

					$arch_hash = rand_hash('arch_design', 'arch_hash');

					if ( ! $this->db->query("INSERT INTO arch_design
                        					(
                            					timestamp, last_change, arch_hash, name, street, city, state, zip, country, phone, fax
                            				)
                            				VALUES
                            				(
                                                UNIX_TIMESTAMP(), '{$this->current_hash}', '$arch_hash', '$name', '$street', '$city', '$state', '$zip', '$country', '$phone', '$fax'
                                            )")
					) {

						return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
					}

                    if ( ! $this->doit_arch_contact($arch_hash, 1) && $_POST['contact_name'] != "" ) {

                        if ( $this->content['form_return']['feedback'] )
                            $errmsg = ( is_array($this->content['form_return']['feedback']) ? implode("<br />\n", $this->content['form_return']['feedback']) : $this->content['form_return']['feedback'] );

                        return $this->__trigger_error( ( $errmsg ? $errmsg : "Error encountered while attempting to create new A&D contact. Please try again." ), E_USER_ERROR, __FILE__, __LINE__, 1);
                    }

					$this->content['action'] = 'close';
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('arch_design','sf_loadcontent','cf_loadcontent','showall')" );

				} elseif ( $btn == "Update Firm" ) {

                    $valid = $this->fetch_master_record($arch_hash);

                    $sql = array();

                    ( $name != $this->current_arch['name'] ? array_push($sql, "name = '$name'") : NULL);
					( $street != $this->current_arch['street'] ? array_push($sql, "street = '$street'") : NULL);
					( $city != $this->current_arch['city'] ? array_push($sql, "city = '$city'") : NULL);
					( $state != $this->current_arch['state'] ? array_push($sql, "state = '$state'") : NULL);
					( $zip != $this->current_arch['zip'] ? array_push($sql, "zip = '$zip'") : NULL);
					( $country != $this->current_arch['country'] ? array_push($sql, "country = '$country'") : NULL);
					( $phone != $this->current_arch['phone'] ? array_push($sql, "phone = '$phone'") : NULL);
					( $fax != $this->current_arch['fax'] ? array_push($sql, "fax = '$fax'") : NULL);

					if ( $sql ) {

						if ( ! $this->db->query("UPDATE arch_design
        										 SET
            										 timestamp = UNIX_TIMESTAMP(), last_change = '{$this->current_hash}',
            										 " . implode(", ", $sql) . "
        										 WHERE arch_hash = '$arch_hash'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

						$this->content['page_feedback'] = "Firm has been updated";

					} else
						$this->content['page_feedback'] = "No changes have been made";

					$this->content['action'] = 'close';
					$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('arch_design','sf_loadcontent','cf_loadcontent','showall','p=$p','order=$order','order_dir=$order_dir','active_search={$this->active_search}')" );
				}

				$this->db->end_transaction();

				return;

			} else {

				if ( ! $_POST['name'] ) $this->content['form_return']['err']["err1_1{$this->popup_id}"] = 1;
				if ( ! $_POST['street'] ) $this->content['form_return']['err']["err1_3{$this->popup_id}"] = 1;
				if ( ! $_POST['city'] ) $this->content['form_return']['err']["err1_4{$this->popup_id}"] = 1;
				if ( ! $_POST['state'] ) $this->content['form_return']['err']["err1_5{$this->popup_id}"] = 1;
				if ( ! $_POST['zip'] ) $this->content['form_return']['err']["err1_6{$this->popup_id}"] = 1;
				if ( ! $_POST['phone'] ) $this->content['form_return']['err']["err1_7{$this->popup_id}"] = 1;

				return $this->__trigger_error("You left some required fields blank! Please check the indicated fields below and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
			}

		} elseif ( $btn == "Delete Firm" ) {

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__ , __LINE__, 1);

			if ( ! $this->db->query("UPDATE arch_design t1
					                 LEFT JOIN arch_contacts t2 ON t2.arch_hash = t1.arch_hash
					                 SET
		    			                 t1.deleted = 1, t1.deleted_date = CURDATE(),
		    			                 t2.deleted = 1, t2.deleted_date = CURDATE()
									 WHERE t1.arch_hash = '$arch_hash'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            $this->db->end_transaction();

			$this->content['page_feedback'] = "Firm has been deleted";

			$this->content['action'] = 'close';
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('arch_design','sf_loadcontent','cf_loadcontent','showall','p=$p','order=$order','order_dir=$order_dir','active_search=".$this->active_search."')");
		}

	}

	function edit_arch() {
		global $stateNames, $states, $country_codes, $country_names;

		$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Create A New A&D Firm";

        if ( $this->ajax_vars['arch_hash'] ) {

            if ( ! $this->fetch_master_record($this->ajax_vars['arch_hash'], 1) ) {

                return $this->__trigger_error("A system error was encountered when trying to lookup A&D contact for database update. Please re-load A&D window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
            }

            $this->content['popup_controls']['popup_title'] = "Edit A&D Firm: " . stripslashes($this->current_arch['name']);
        }

		if ( $this->ajax_vars['onclose_action'] )
			$this->content['popup_controls']['onclose'] = $this->ajax_vars['onclose_action'];

		$tbl .=
		$this->form->form_tag().
		$this->form->hidden( array(
            "arch_hash"         =>  $this->arch_hash,
            "popup_id"          =>  $this->popup_id,
            "jscript_action"    =>  $this->ajax_vars['jscript_action'],
            "p"                 =>  $this->ajax_vars['p'],
            "order"             =>  $this->ajax_vars['order'],
            "order_dir"         =>  $this->ajax_vars['order_dir'],
            "active_search"     =>  $this->ajax_vars['active_search']
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<div style=\"padding:15px 0 0 15px;font-weight:bold;\"></div>
			<ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">" .
			( $this->p->ck(get_class($this), 'V', 'general') ? "
				<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\">General Info</a></li>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'contacts') ? "
				<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\">A&D Contacts</a></li>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'stats') ? "
				<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent3{$this->popup_id}\" onClick=\"expandcontent(this);\">A & D Stats</a></li>" : NULL
			) . "
			</ul>" .
            ( $this->p->ck(get_class($this), 'V', 'general') ? "
			<div id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"width:35%;padding-top:15px;text-align:right;background-color:#ffffff;\" id=\"err1_1{$this->popup_id}\">Firm Name: *</td>
						<td style=\"background-color:#ffffff;padding-top:15px;\">" .
    						$this->form->text_box(
        						"name=name",
        						"value=" . stripslashes($this->current_arch['name']),
        						"size=35",
        						"maxlength=255",
        						( $this->arch_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
            				) . "
            			</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#ffffff;\" id=\"err1_3{$this->popup_id}\">Street: *</td>
						<td style=\"background-color:#ffffff;\">".
                    		$this->form->text_area(
                        		"name=street",
                        		"value=" . stripslashes($this->current_arch['street']),
                        		"rows=3",
                        		"cols=35",
                        		( $this->arch_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
                            ) . "
                        </td>
					</tr>
                    <tr>
                        <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_4{$this->popup_id}\">City: *</td>
                        <td style=\"background-color:#ffffff;\">" .
                            $this->form->text_box(
                                "name=city",
                                "value=" . stripslashes($this->current_arch['city']),
                                "size=15",
                                "maxlength=255",
                                ( $this->arch_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
                            ) . "
                        </td>
                    </tr>
                    <tr>
                        <td  style=\"text-align:right;background-color:#ffffff;\" id=\"err1_5{$this->popup_id}\">State: *</td>
                        <td style=\"background-color:#ffffff;\">" .
                        ( $this->arch_hash && ! $this->p->ck(get_class($this), 'E', 'general') ?
                            ( $this->current_arch['country'] && $this->current_arch['country'] != 'US' && $this->current_arch['country'] != 'CA' ?
                                $this->current_arch['country'] : $stateNames[ array_search($this->current_arch['state'], $states) ]
                            ) :
                            "<input
                                type=\"hidden\"
                                name=\"stateSelect_default\"
                                id=\"stateSelect_default\"
                                value=\"{$this->current_arch['state']}\" >
                            <div>
                                <select id=\"stateSelect\" name=\"state\"></select>
                            </div>"
                        ) . "
                        </td>
                    </tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_6{$this->popup_id}\">Zip: *</td>
						<td style=\"background-color:#ffffff;\">" .
    						$this->form->text_box(
        						"name=zip",
        						"value={$this->current_arch['zip']}",
        						"size=7",
        						"maxlength=10",
        						( $this->arch_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
        					) . "
        				</td>
					</tr>
                    <tr>
                        <td  style=\"text-align:right;background-color:#ffffff;\" id=\"err1_6a{$this->popup_id}\">Country: *</td>
                        <td style=\"background-color:#ffffff;\">" .
                        ( $this->arch_hash && ! $this->p->ck(get_class($this), 'E', 'general') ?
                            $country_names[ array_search(
                                ( $this->current_arch['country'] ?
                                    $this->current_arch['country']
                                    :
                                    ( defined('MY_COMPANY_COUNTRY') ?
                                        MY_COMPANY_COUNTRY : NULL
                                    )
                                ), $country_codes) ] .
                            $this->form->hidden( array(
                                'country' =>
                                ( $this->current_arch['country'] ?
                                    $this->current_arch['country'] :
                                        ( defined('MY_COMPANY_COUNTRY') ?
                                            MY_COMPANY_COUNTRY : NULL
                                        )
                                    )
                            ) )
                            :
                            "<input
                                type=\"hidden\"
                                name=\"countrySelect_default\"
                                id=\"countrySelect_default\"
                                value=\"" .
                                ( $this->current_arch['country'] ?
                                    $this->current_arch['country'] :
                                    ( defined('MY_COMPANY_COUNTRY') ?
                                        MY_COMPANY_COUNTRY : 'US'
                                    )
                                ) . "\">
                            <div>
                                <select id=\"countrySelect\" name=\"country\" onchange=\"updateState(this.id);\"></select>
                            </div>"
                        ) . "
                        </td>
                    </tr>
					<tr>
						<td style=\"background-color:#ffffff;text-align:right;\" id=\"err1_7{$this->popup_id}\">Phone: *</td>
						<td style=\"background-color:#ffffff;\">" .
    						$this->form->text_box(
        						"name=phone",
        						"value={$this->current_arch['phone']}",
        						"size=18",
        						"maxlength=32",
        						( $this->arch_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
        					) . "
        				</td>
					</tr>
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;\" id=\"err1_8{$this->popup_id}\">Fax:</td>
						<td style=\"background-color:#ffffff;\">" .
    						$this->form->text_box(
        						"name=fax",
        						"value={$this->current_arch['fax']}",
        						"size=18",
        						"maxlength=16",
        						( $this->arch_hash && ! $this->p->ck(get_class($this), 'E', 'general') ? "readonly" : NULL )
        					) . "
        				</td>
					</tr>
				</table>
				<div style=\"text-align:left;padding:15px;\">" .

                ( ! $this->arch_hash || ( $this->lock && $this->arch_hash && $this->p->ck(get_class($this), 'E') ) ?
					$this->form->button(
    					"value=" . ( $this->arch_hash ? "Update Firm" : "Add Firm" ),
    					"onClick=submit_form(this.form,'arch_design','exec_post','refresh_form','actionBtn=" . ( $this->arch_hash ? "Update Firm" : "Add Firm" ) . "');"
					) : NULL
				) . "&nbsp;" .
				( $this->lock && $this->arch_hash && $this->p->ck(get_class($this), 'D') ?
					$this->form->button(
    					"value=Delete Firm",
    					"onClick=if(confirm('Are you sure you want to delete this A&D firm? This action CANNOT be undone!')) {submit_form(this.form,'arch_design','exec_post','refresh_form','actionBtn=Delete Firm');}"
					) : NULL
				) . $this->p->lock_stat(get_class($this), $this->popup_id) . "

				</div>
			</div>" : NULL
    		) .
    		( $this->p->ck(get_class($this), 'V', 'contacts') ? "
			<div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">
    			{$this->contact_info_form()}
			</div>" : NULL
			) .
			( $this->p->ck(get_class($this), 'V', 'stats') ? "
			<div id=\"tcontent3{$this->popup_id}\" class=\"tabcontent\">
				{$this->arch_design_stats_form()}
			</div>" : NULL
			) . "
		</div>" .
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')',10);";
        $this->content['jscript'][] = "setTimeout(function(){initCountry('countrySelect', 'stateSelect');}, 250);";
		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}


	function contact_info_form() {

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];

		if ( $this->ajax_vars['popup_id'] )
			$this->popup_id = $this->ajax_vars['popup_id'];

		if ( $this->ajax_vars['arch_hash'] && ! $this->arch_hash ) {

            if ( ! $this->fetch_master_record($this->ajax_vars['arch_hash']) ) {

                return $this->__trigger_error("A system error was encountered when trying to lookup A&D contact for database update. Please re-load A&D window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
            }
		}

		$action = $this->ajax_vars['action'];

        if ( $this->ajax_vars['contact_hash'] ) {

            if ( ! $this->fetch_contact_record($this->ajax_vars['contact_hash'], 1) ) {

                return $this->__trigger_error("A system error was encountered when trying to lookup A&D contact for database update. Please re-load A&D window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
            }
        }

		if ( ! $this->current_arch['arch_hash'] || $action == 'addnew' || ! $this->total_contacts || $this->ajax_vars['parent_win_open'] == 'add_from_proposal' ) {

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" style=\"width:35%;padding-top:15px;text-align:right;background-color:#ffffff;\" id=\"err3_1{$this->popup_id}\">Contact Name:</td>
					<td style=\"background-color:#ffffff;padding-top:15px;\">".$this->form->text_box("name=contact_name","value=".$this->current_contact['contact_name'],"size=35","maxlength=255")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_2{$this->popup_id}\">Title:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_title","value=".$this->current_contact['contact_title'],"size=20","maxlength=128")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_3{$this->popup_id}\">Phone:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_phone","value=".$this->current_contact['contact_phone1'],"size=18","maxlength=32")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_4{$this->popup_id}\">Phone 2:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_phone2","value=".$this->current_contact['contact_phone2'],"size=18","maxlength=32")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_5{$this->popup_id}\">Mobile:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_mobile","value=".$this->current_contact['contact_mobile'],"size=18","maxlength=32")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_6{$this->popup_id}\">Fax:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_fax","value=".$this->current_contact['contact_fax'],"size=18","maxlength=32")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err3_7{$this->popup_id}\">Email:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=contact_email","value=".$this->current_contact['contact_email'],"size=25","maxlength=128")."</td>
				</tr>".($this->current_arch['arch_hash'] ? "
				<tr>
					<td style=\"background-color:#ffffff;padding-right:40px;text-align:left;\" colspan=\"2\">
						<div style=\"float:right;\">
							".($this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'E','contacts') ? $this->form->hidden(array('contact_hash' => $this->current_contact['contact_hash']))."Update Contact" : (!$this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'A','contacts') ? "Add Contact" : NULL))."
							&nbsp;&nbsp;".(($this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'E','contacts')) || (!$this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'A','contacts')) ? $this->form->button("value=+","onClick=if ($F('contact_name')) {submit_form(this.form,'arch_design','exec_post','refresh_form','actionBtn=AddContactLine');}else alert('Please enter at least the name of your contact to continue.');") : NULL)."
							".($this->current_contact['contact_hash'] && $this->p->ck(get_class($this),'D','contacts') ? "&nbsp;&nbsp;Delete Contact&nbsp;&nbsp;".$this->form->button("value=X","onClick=if (confirm('Are you sure you want to delete this contact? This action CANNOT be undone!')){submit_form(this.form,'arch_design','exec_post','refresh_form','actionBtn=AddContactLine','delete=true');}") : NULL)."
						</div>".($this->current_arch['arch_hash'] && $this->total_contacts ? "
						<small><a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','cf_loadcontent','contact_info_form','otf=1','popup_id={$this->popup_id}','arch_hash=".$this->current_arch['arch_hash']."','p=$p','order=$order','order_dir=$order_dir');\" class=\"link_standard\"><- Back</a></small>" : NULL)."

					</td>
				</tr>" : NULL)."
			</table>";


		} elseif ( $this->current_arch['arch_hash'] && $this->total_contacts ) {

			$total = $this->total_contacts;
			$num_pages = ceil($this->total_contacts / SUB_PAGNATION_NUM);
			$p = ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ($p - 1);
			$end = $start_from + SUB_PAGNATION_NUM;
			if ($end > $this->total_contacts)
				$end = $this->total_contacts;

			$order_by_ops = array("contact_name"	=>	"arch_contacts.contact_name");

			$this->fetch_contacts( array(
				'start_from'	=>	$start_from,
				'order_by'		=>	$order_by_ops[ $order ],
				'order_dir'		=>	$order_dir
			) );

			$tbl = "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">
									<div style=\"float:right;font-weight:normal;padding-right:10px;\">".paginate_jscript($num_pages,$p,'sf_loadcontent','cf_loadcontent','arch_design','contact_info_form',$order,$order_dir,"'otf=1','arch_hash=".$this->current_arch['arch_hash']."','popup_id={$this->popup_id}'")."</div>
									Showing ".($start_from + 1)." - ".($start_from + SUB_PAGNATION_NUM > $this->total_contacts ? $this->total_contacts : $start_from + SUB_PAGNATION_NUM)." of ".$this->total_contacts." Contacts for ".$this->current_arch['name'].".
									<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">".($this->p->ck(get_class($this),'A','contacts') ? "
										[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','cf_loadcontent','contact_info_form','otf=1','popup_id={$this->popup_id}','action=addnew','arch_hash=".$this->current_arch['arch_hash']."');\" class=\"link_standard\">Add New Contact</a></small>]" : NULL)."
									</div>
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','cf_loadcontent','contact_info_form','otf=1','popup_id={$this->popup_id}','arch_hash=".$this->current_arch['arch_hash']."','p=$p','order=contact_name','order_dir=".($order == "contact_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
										Name</a>
									".($order == 'contact_name' ?
										"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
								</td>
								<td >Title</td>
								<td >Phone</td>
								<td >Email</td>
							</tr>";
							for ($i = 0; $i < ($end - $start_from); $i++) {
								$b++;
								$tbl .= "
								<tr ".($this->p->ck(get_class($this),'V','contacts') ? "onClick=\"agent.call('arch_design','sf_loadcontent','cf_loadcontent','contact_info_form','popup_id={$this->popup_id}','otf=1','action=addnew','arch_hash=".$this->current_arch['arch_hash']."','contact_hash=".$this->arch_contacts[$i]['contact_hash']."','p=$p','order=$order','order_dir=$order_dir');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">
									<td>".$this->arch_contacts[$i]['contact_name']."</td>
									<td>".$this->arch_contacts[$i]['contact_title']."</td>
									<td>".$this->arch_contacts[$i]['contact_phone1']."</td>
									<td>".$this->arch_contacts[$i]['contact_email']."</td>
								</tr>".($b < ($end - $start_from) ? "
								<tr>
									<td style=\"background-color:#cccccc;\" colspan=\"6\"></td>
								</tr>" : NULL);
							}


				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";
		}

		if ($this->ajax_vars['otf'])
			$this->content['html']["tcontent2{$this->popup_id}"] = $tbl;
		else
			return $tbl;
	}

	function arch_design_stats_form() {

		$tbl = "
		<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
			<tr>
				<td style=\"padding:0;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\">
								A & D Statistics for ".$this->current_arch['name']." as of ".date("G:i a")."
								<div style=\"margin:10px;width:600px;padding:0;\">";
									$current_qtr = get_quarter();
									list($qtr_start,$qtr_end) = get_quarter_dates($current_qtr);

									$result = $this->db->query("SELECT
																SUM(CASE
																	WHEN customer_invoice.type = 'I'
																		THEN customer_invoice.amount
																		ELSE 0
																	END) AS overall_invoiced ,
																SUM(line_items.sell * line_items.qty) AS overall_proposed ,
																SUM(CASE
																	WHEN customer_invoice.invoice_date BETWEEN ".date("Y-m-01")." AND CURDATE() AND customer_invoice.type = 'I'
																		THEN customer_invoice.amount
																		ELSE 0
																	END) AS invoiced_mtd ,
																SUM(CASE
																	WHEN proposals.creation_date BETWEEN ".strtotime(date("Y-m-01"))." AND ".strtotime(date("Y-m-d"))."
																		THEN line_items.sell * line_items.qty
																		ELSE 0
																	END) AS proposed_mtd ,
																SUM(CASE
																	WHEN customer_invoice.invoice_date BETWEEN $qtr_start AND CURDATE() AND customer_invoice.type = 'I'
																		THEN customer_invoice.amount
																		ELSE 0
																	END) AS invoiced_qtd ,
																SUM(CASE
																	WHEN proposals.creation_date BETWEEN ".strtotime($qtr_start)." AND ".strtotime(date("Y-m-d"))."
																		THEN line_items.sell * line_items.qty
																		ELSE 0
																	END) AS proposed_qtd ,
																SUM(CASE
																	WHEN customer_invoice.invoice_date BETWEEN ".date("Y-01-01")." AND CURDATE() AND customer_invoice.type = 'I'
																		THEN customer_invoice.amount
																		ELSE 0
																	END) AS invoiced_ytd ,
																SUM(CASE
																	WHEN proposals.creation_date BETWEEN ".strtotime(date("Y-01-01"))." AND ".strtotime(date("Y-m-d"))."
																		THEN line_items.sell * line_items.qty
																		ELSE 0
																	END) AS proposed_ytd
															   FROM `arch_design`
															   LEFT JOIN `proposals` ON proposals.arch_hash = arch_design.arch_hash
															   LEFT JOIN `customer_invoice` ON customer_invoice.proposal_hash = proposals.proposal_hash AND customer_invoice.deleted = 0
															   LEFT JOIN `line_items` ON line_items.proposal_hash = proposals.proposal_hash
															   WHERE proposals.arch_hash = '".$this->arch_hash."'");
									$row = $this->db->fetch_assoc($result);

									$result = $this->db->query("SELECT AVG(FORMAT((line_items.sell - line_items.cost) / line_items.sell * 100 , 2)) AS avg_margin
																FROM `line_items`
																LEFT JOIN proposals ON proposals.proposal_hash = line_items.proposal_hash
																WHERE proposals.arch_hash = '".$this->arch_hash."' AND !ISNULL(line_items.invoice_hash) AND line_items.invoice_hash != ''");
									$avg_margin = $this->db->result($result);

									$tbl .= "
									<fieldset style=\"margin-top:10px\">
										<legend>Resulted Sales &amp; Proposals</legend>
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\" >
											<tr>
												<td style=\"padding:10px 25px;\">
													Average GP Margin: ".round($avg_margin,2)."%
												</td>
											</tr>
											<tr>
												<td style=\"padding-left:25px;\" >
													Total Invoiced Sales:
													<div style=\"margin-left:45px;\">
														<table style=\"width:100%;\" >
															<tr>
																<td style=\"width:50%;\">
																	<div>MTD: $".number_format($row['invoiced_mtd'],2)."</div>
																	<div style=\"padding-top:5px;\">QTD: $".number_format($row['invoiced_qtd'],2)."</div>
																</td>
																<td style=\"width:50%;\">
																	<div>YTD: $".number_format($row['invoiced_ytd'],2)."</div>
																	<div style=\"padding-top:5px;\">Overall: $".number_format($row['overall_invoiced'],2)."</div>
																</td>
															</tr>
														</table>
													</div>
												</td>
											</tr>
											<tr>
												<td style=\"padding-left:25px;\" >
													Total Proposed Sales:
													<div style=\"margin-left:45px;\">
														<table style=\"width:100%;\" >
															<tr>
																<td style=\"width:50%;\">
																	<div>MTD: $".number_format($row['proposed_mtd'],2)."</div>
																	<div style=\"padding-top:5px;\">QTD: $".number_format($row['proposed_qtd'],2)."</div>
																</td>
																<td style=\"width:50%;\">
																	<div>YTD: $".number_format($row['proposed_ytd'],2)."</div>
																	<div style=\"padding-top:5px;\">Overall: $".number_format($row['overall_proposed'],2)."</div>
																</td>
															</tr>
														</table>
													</div>
												</td>
											</tr>
										</table>
									</fieldset>
								</div>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>";

		return $tbl;
	}

	function showall() {
		$this->unlock("_");
		$this->load_class_navigation();

		$p = $this->ajax_vars['p'];
		$order = $this->ajax_vars['order'];
		$order_dir = $this->ajax_vars['order_dir'];
		$this->active_search = $this->ajax_vars['active_search'];

		$this->content['popup_controls']['popup_id'] = ($this->ajax_vars['popup_id'] ? $this->ajax_vars['popup_id'] : 'popup_win_list');
		$this->popup_id = $this->content['popup_controls']['popup_id'];

		$this->arch_design($this->current_hash);

		$total_1 = $this->total;
		$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
		$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
		$start_from = MAIN_PAGNATION_NUM * ($p - 1);

		$end = $start_from + MAIN_PAGNATION_NUM;
		if ($end > $this->total)
			$end = $this->total;

		$order_by_ops = array("name"			=>	"arch_design.name",
							  "location"		=>	"arch_design.state , arch_design.city");

		$this->fetch_archs($start_from,$order_by_ops[($order ? $order : "name")],$order_dir);

		if ($this->total != $total_1) {
			$num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = MAIN_PAGNATION_NUM * ($p - 1);

			$end = $start_from + MAIN_PAGNATION_NUM;
			if ($end > $this->total)
				$end = $this->total;

		}

		$tbl .= ($this->active_search ? "
		<h3 style=\"color:#00477f;margin:0;\">Search Results</h3>
		<div style=\"padding-top:8px;padding-left:25px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','cf_loadcontent','showall');\">Show All</a>]</div>" : NULL)."
		".$this->form->hidden(array("p" => $p, "active_search" => $this->active_search))."
		<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
			<tr>
				<td style=\"padding:15;\">
					 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
						<tr>
							<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">".($this->total ? "
								<div style=\"float:right;font-weight:normal;padding-right:10px;\">".paginate_jscript($num_pages,$p,'sf_loadcontent','cf_loadcontent','arch_design','showall',$order,$order_dir,"'active_search=".$this->active_search."'","'popup_id={$this->popup_id}'")."</div>
								Showing ".($start_from + 1)." - ".($start_from + MAIN_PAGNATION_NUM > $this->total ? $this->total : $start_from + MAIN_PAGNATION_NUM)." of ".$this->total." A & D Firms." : NULL)."
								<div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">".($this->p->ck(get_class($this),'A') ? "
									<a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','show_popup_window','edit_arch','popup_id=main_popup','active_search=".$this->active_search."','p=$p','order=$order','order_dir=$order_dir');\"><img src=\"images/new.gif\" alt=\"Create a new A & D firm\" border=\"0\" /></a>
									&nbsp;" : NULL)."
									<a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','show_popup_window','search_archs','popup_id=main_popup','active_search=".$this->active_search."','p=$p','order=$order','order_dir=$order_dir');\"><img src=\"images/search.gif\" alt=\"Search A & D firms\" border=\"0\" /></a>
									&nbsp;
								</div>
							</td>
						</tr>
						<tr class=\"thead\" style=\"font-weight:bold;\">
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','cf_loadcontent','showall','p=$p','popup_id={$this->popup_id}','order=name','order_dir=".($order == "name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search=".$this->active_search."'" : NULL).");\" style=\"color:#ffffff;text-decoration:underline;\">
									Firm Name</a>
								".($order == 'name' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
							<td >
								<a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','cf_loadcontent','showall','p=$p','popup_id={$this->popup_id}','order=location','order_dir=".($order == "location" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."'".($this->active_search ? ",'active_search=".$this->active_search."'" : NULL).");\" style=\"color:#ffffff;text-decoration:underline;\">
									Location</a>
								".($order == 'location' ?
									"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
							</td>
						</tr>";
						for ($i = 0; $i < ($end - $start_from); $i++) {
							if ($this->p->ck(get_class($this),'V'))
								$onClick = "onClick=\"agent.call('arch_design','sf_loadcontent','show_popup_window','edit_arch','popup_id={$this->popup_id}','arch_hash=".$this->arch_info[$i]['arch_hash']."','p=$p','order=$order','order_dir=$order_dir','active_search=".$this->active_search."');\"";

							$b++;
							$tbl .= "
							<tr ".($this->p->ck(get_class($this),'V') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL).">
								<td $onClick>".$this->arch_info[$i]['name']."</td>
								<td>".$this->arch_info[$i]['city'].($this->arch_info[$i]['city'] ? ", " : NULL).$this->arch_info[$i]['state']."</td>
							</tr>".($b < ($end - $start_from) ? "
							<tr>
								<td style=\"background-color:#cccccc;\" colspan=\"6\"></td>
							</tr>" : NULL);
						}

						if (!$this->total)
							$tbl .= "
							<tr >
								<td>" .
    							( $this->active_search ?
									"<div style=\"padding-top:10px;font-weight:bold;\">Search returned empty result set</div>"
        							:
        							"You have no A&D firms to display. " .
        							( $this->p->ck(get_class($this),'A') ?
										"<a href=\"javascript:void(0);\" onClick=\"agent.call('arch_design','sf_loadcontent','show_popup_window','edit_arch','popup_id=main_popup');\">Create a new A&D firm now.</a>" : NULL
        							)
        						) . "
								</td>
							</tr>";

			$tbl .= "
					</table>
				</td>
			</tr>
		</table>";

		$this->content['html']['place_holder'] = $tbl;
	}

	function search_archs() {
		global $stateNames,$states;

		$date = date("U") - 3600;
		$result = $this->db->query("DELETE FROM `search`
							  		WHERE `timestamp` <= '$date'");

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = "Search For an A&D Firm";
		$this->content['focus'] = "name";

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array('popup_id' => $this->content['popup_controls']['popup_id']))."
		<div class=\"panel\">
			<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message\"></p>
			</div>
			<div style=\"padding:15px 0 0 15px;font-weight:bold;\">
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#cccccc;width:700px;\">
				<tr>
					<td class=\"smallfont\" style=\"padding:10px 5px 20px 5px;background-color:#ffffff;\">
						<table class=\"smallfont\">
							<tr>
								<td colspan=\"2\" ><strong>Search By Firm Name</strong></td>
							</tr>
							<tr>
								<td style=\"padding:10px 0 5px 40px;\" colspan=\"2\">
									If you know the design firm name or part of the name enter it below.
								</td>
							</tr>
							<tr>
								<td style=\"padding:10px;text-align:right;\" id=\"err0\">Firm Name:</td>
								<td>".$this->form->text_box("name=name","maxlength=255","size=25")."</td>
							</tr>
							<tr>
								<td colspan=\"2\" style=\"text-align:right;padding:15px;background-color:#ffffff;\">
									".$this->form->button("value=Search","id=primary","onClick=submit_form($('popup_id').form,'arch_design','exec_post','refresh_form','action=doit_search');")."
									&nbsp;
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
	}
}

?>