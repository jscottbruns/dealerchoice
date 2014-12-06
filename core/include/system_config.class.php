<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class system_config extends AJAX_library {

	public $user_info = array();
	public $current_user;
	public $user_hash;

	public $group_info = array();
	public $current_group;
	public $group_hash;

	public $total_products;
	public $product_info;

	public $total_commissions;
	public $commissions;
	public $commission_type = array("D" => "Generic Commission Rule",
									"C" => "Customer Commission Rule",
									"G" => "GSA Commission Rule",
	                                "T" => "Commission Team");
	public $overhead_type =   array("D" => "Generic Overhead Rule",
									"C" => "Customer Overhead Rule",
									"G" => "GSA Overhead Rule");
	public $total_overhead;
	public $currency;
	public $total_currency;
	public $home_currency;
    public $default_sym;

	function system_config() {
		global $db;

		$this->form = new form;
		$this->db =& $db;
		$this->validator = new Validator();
		$this->current_hash = $_SESSION['id_hash'];
		$this->p = new permissions($this->current_hash);
		$this->content['class_name'] = get_class($this);

		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `vendor_products`
									WHERE ISNULL(`vendor_hash`)");
		$this->total_products = $this->db->result($result);

		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `commission_tables`");
		$this->total_commissions = $this->db->result($result);

		$result = $this->db->query("SELECT COUNT(*) AS Total
									FROM `overhead_rules`");
		$this->total_overhead = $this->db->result($result);

		if (defined('MULTI_CURRENCY')) {
			$r = $this->db->query("SELECT *
			                       FROM currency
			                       WHERE `default` = 1");
			$this->home_currency = $this->db->fetch_assoc($r);

			$r = $this->db->query("SELECT var_val
			                       FROM system_vars
			                       WHERE `var_name` = 'CURRENCY_GAIN_LOSS_ACCT'");
			$this->home_currency['account_hash'] = $this->db->result($r, 0, 'var_val');
			if (valid_account($this->db, $this->home_currency['account_hash']) == false)
                unset($this->home_currency['account_hash']);
		}
        $this->default_sym = SYM;
	}

	function company_products() {
		$result = $this->db->query("SELECT `product_hash` , `product_name`
									FROM `vendor_products`
									WHERE ISNULL(`vendor_hash`)
									ORDER BY `product_name` ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			$product_hash[] = $row['product_hash'];
			$product_name[] = $row['product_name'];
		}
		return array($product_hash, $product_name);
	}

	function edit_system() {
		$this->unlock("_");
		$this->load_class_navigation();
		global $stateNames, $states;

		$tbl .= $this->form->form_tag()."
		<h3 style=\"margin-bottom:5px;color:#00477f;margin-top:0;\">
			System Settings
		</h3>
		<div style=\"padding:5px 0 5px 5px;\">
		<div id=\"main_table\">
			<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message\"></p>
			</div>
			<ul id=\"sys_maintab\" class=\"shadetabs\">".($this->p->ck(get_class($this), 'L', 'users') ? "
				<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"sys_tcontent1\" onClick=\"expandcontent(this);\" >Users &amp; Groups</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'company_settings') ? "
				<li ><a href=\"javascript:void(0);\" rel=\"sys_tcontent2\" onClick=\"expandcontent(this);\" >Company & System Settings</a></li>" : NULL).($this->p->ck(get_class($this), 'L', 'system_maintain') ? "
				<!--<li ><a href=\"javascript:void(0);\" rel=\"sys_tcontent3\" onClick=\"expandcontent(this);\">System & Database Maintenance</a></li>-->" : NULL)."
			</ul>".($this->p->ck(get_class($this), 'L', 'users') ? "
			<div id=\"sys_tcontent1\" class=\"tabcontent\">
				".$this->users()."
			</div>" : NULL).($this->p->ck(get_class($this), 'L', 'company_settings') ? "
			<div id=\"sys_tcontent2\" class=\"tabcontent\">
				".$this->form->form_tag().$this->company_settings().$this->form->close_form()."
			</div>" : NULL).($this->p->ck(get_class($this), 'L', 'system_maintain') ? "
			<!--<div id=\"sys_tcontent3\" class=\"tabcontent\">
				".$this->system_maintain()."
			</div>-->" : NULL)."
		</div>".
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'sys_maintab\')', 100);";
		$this->content['html']['place_holder'] = $tbl;

		return;

	}

	function fetch_users() {

		$this->user_info = array();

		$result = $this->db->query("SELECT
                                		t1.id_hash AS user_hash,
                                		t1.user_lock,
                                		t1.full_name,
                                		t1.user_name,
                                		t1.active
							  		FROM users t1
									WHERE t1.user_status < 20
							  		ORDER BY t1.full_name ASC");
		while ( $row = $this->db->fetch_assoc($result) )
			array_push($this->user_info, $row);

		__stripslashes($this->user_info);
	}

	function fetch_user_record($user_hash) {

		unset($this->user_hash);
		$this->current_user = array();

		$result = $this->db->query("SELECT *
									FROM users
									WHERE id_hash = '$user_hash'");
		if ( $row = $this->db->fetch_assoc($result) ) {

			$this->user_hash = $user_hash;
			$this->current_user = $row;
			$this->current_user['groups'] = array();

			$result_2 = $this->db->query("SELECT
		                            		id_hash,
		                            		group_hash
										  FROM user_subscribe
										  WHERE id_hash = '{$this->user_hash}'");
			while ( $row_2 = $this->db->fetch_assoc($result_2) )
				array_push($this->current_user['groups'], $row_2['group_hash']);

			$result_2 = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
										  FROM proposals t1
										  WHERE
    										  t1.last_change = '{$this->user_hash}'
    										  OR
    										  t1.sales_hash = '{$this->user_hash}'
    										  OR
    										  t1.designer_hash = '{$this->user_hash}'
    										  OR
    										  t1.sales_coord_hash = '{$this->user_hash}'
    										  OR
    										  t1.proj_mngr_hash = '{$this->user_hash}'");
			if ( $this->db->result($result_2, 0, 'Total') )
				$this->current_user['no_delete'] = 1;

			return true;
		}

		return false;
	}

	function fetch_groups() {

		$this->group_info = array();

		$result = $this->db->query("SELECT t1.*
									FROM user_groups t1
									ORDER BY t1.group_name ASC");
		while ( $row = $this->db->fetch_assoc($result) )
			array_push($this->group_info, $row);

	}

	function fetch_group_record($group_hash) {

        unset($this->group_hash);
        $this->current_group = array();

		$result = $this->db->query("SELECT
                                    	t1.*,
                                    	t3.id_hash
									FROM user_groups t1
									LEFT JOIN user_subscribe t2 ON t2.group_hash = t1.group_hash
									LEFT JOIN users t3 ON t3.id_hash = t2.id_hash
									WHERE t1.group_hash = '$group_hash'");
		if ( $this->db->num_rows($result) > 0)  {

			$i = 0;
			while ( $row = $this->db->fetch_assoc($result) ) {

				if ( $i == 0 ) {

					$this->group_hash = $row['group_hash'];
					$this->current_group = $row;
					$this->current_group['group_members'] = array();
				}

				if ( $row['id_hash'] )
					array_push($this->current_group['group_members'], $row['id_hash']);

				$i++;
			}

			return true;
		}

		return false;
	}

	function fetch_tax_tables($country, $state=NULL, $product=NULL) {
		global $states, $stateNames;

		if ($country != 'US' && $country != 'CA')
            return;

		if (is_array($product) && count($product)) {
			$product = array_unique($product);
			$product = array_values($product);
			array_walk($product, 'add_quotes', "'");
		}
		$result = $this->db->query("SELECT tax_table.* , accounts.obj_id AS valid_acct
									FROM `tax_table`
									LEFT JOIN `accounts` ON accounts.account_hash = tax_table.account_hash ".($product ? "
									LEFT JOIN `product_tax` ON product_tax.tax_hash = tax_table.tax_hash" : NULL)."
									WHERE tax_table.country = '$country' ".($state ? "
									   AND tax_table.state = '$state' " : NULL).($product ? " AND product_tax.product_hash IN (".implode(" , ", $product).") " : NULL).($product ? "
									GROUP BY tax_table.tax_hash" : NULL)."
									ORDER BY tax_table.state , tax_table.local ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			$row['state_name'] = $stateNames[array_search(strtoupper($row['state']), $states)];
			$total++;
			if ($row['local'])
				$this->tax_tables[$row['state']]['local'][] = $row;
			else
				$this->tax_tables[$row['state']]['state'] = $row;
		}
		$this->total_tax_rules = $total;
	}

	function doit_save_permissions() {

		$user_hash = $_POST['user_hash'];
		$group_hash = $_POST['group_hash'];

		$window = array(); # Load the permission classes
		$r = $this->db->query("SELECT class
							   FROM permissions
							   GROUP BY class");
		while ( $row = $this->db->fetch_assoc($r) )
			array_push($window, $row['class']);

		$perms = new permissions($user_hash . $group_hash, ( $group_hash ? "G" : NULL ) );
		$perms->fetch_user_permissions();

		if ( ! $this->db->start_transaction() )
			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

		foreach ( $window as $c ) {
			// Delete all permissions for this class immediately. This is to make
			// sure that all permissions can be removed from one class. With this statement after the
			// continue, this wasn't possible before. For ticket #144.
			$perms->prepare_permissions($c);
			
			if ( ! $_POST[ $c ] )
				continue;

			foreach ( $_POST[ $c ] as $p ) {

				if ( $p ) {

					list($perm, $content, $class) = explode("|", $p);

					if ( ! $perms->grant($perm, ( $class ? $class : $c ), $content) )
						return $this->__trigger_error("System error when attempting to grant user/group permissions. Please reload window and try again. <!-- Tried granting $perm" . ( $content ? "::$content" : NULL ) . " permission to class " . ( $class ? $class : $c ) . " -->", E_USER_ERROR, __FILE__, __LINE__, 1);
				}
			}
		}

		$this->db->end_transaction();

		$this->content['html']["permmissions_tbl_holder{$this->popup_id}"] = $this->permissions_form($window);
		$this->content['page_feedback'] = ( $group_hash ? "Group" : "User" ) . " permissions updated";
		$this->content['action'] = 'continue';

		return;
	}

	function doit_user() {

		$user_hash = $_POST['user_hash'];
		$method = $_POST['method'];

		if ( $method == 'select_permission' ) {

			$this->content['html']["permmissions_tbl_holder{$this->popup_id}"] = $this->permissions_form( $_POST['window'] );
			$this->content['action'] = 'continue';

			return;
		}

		if ( $method == 'rm' || ( $_POST['full_name'] && $_POST['user_name'] && $_POST['password'] && $_POST['email'] ) ) {

			if ( $method == 'rm' ) {

				if ( ! $this->db->start_transaction() )
    				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

				if ( ! $this->fetch_user_record($user_hash) )
    				return $this->__trigger_error("System error encountered when attempting to lookup user record. Please reload window and try again. <!-- Tried fetching user [ $user_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

				if ( ! $this->db->query("DELETE FROM users
        								 WHERE id_hash = '$user_hash'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				$result = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
											FROM work_order_items t1
											WHERE t1.resource_hash = '{$this->current_user['resource_hash']}'");
				if ( ! $this->db->result($result, 0, 'Total') ) {

					if ( ! $this->db->query("DELETE FROM resources
        									 WHERE resource_hash = '{$this->current_user['resource_hash']}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
				}

				$this->db->end_transaction();

				$this->content['action'] = 'close';
				$this->content['page_feedback'] = "User has been removed.";
				$this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'user_list');" );

				return;
			}

			$name = $_POST['full_name'];
			$user_name = $_POST['user_name'];
			$password = $_POST['password'];
			$login_date_start = $_POST['login_date_start'];
			$login_date_end = $_POST['login_date_end'];
			$ip_restriction = $_POST['ip_restriction'];
			$unique_id = $_POST['unique_id'];
			$commission_hash = $_POST['user_commission'];
			$phone = $_POST['phone'];
			$fax = $_POST['fax'];
			$lock = $_POST['lock'];
			$active = $_POST['active'];
			$email = $_POST['email'];
			$receive_email = $_POST['receive_email'];
			$group = $_POST['group'];
			for ( $i = 0; $i < count($group); $i++ ) {

				if ( $group[$i] )
					$groups[] = $group[$i];
			}

			$group = $groups;

			$resource['resource_name'] = ( ! $user_hash ? $_POST['full_name'] : $_POST['resource_name'] );
			$resource['vendor_hash'] = ( $_POST['resource_vendor_hash'] ? $_POST['resource_vendor_hash'] : ( defined('INTERNAL_VENDOR') ? INTERNAL_VENDOR : NULL ) );
			$resource['active'] = $_POST['resource_active'];
			$resource['cost_hour'] = preg_replace('/[^.0-9]/', "", $_POST['cost_hour']);
			$resource['cost_day'] = preg_replace('/[^.0-9]/', "", $_POST['cost_day']);
			$resource['cost_halfday'] = preg_replace('/[^.0-9]/', "", $_POST['cost_halfday']);
			$resource['sell_hour'] = preg_replace('/[^.0-9]/', "", $_POST['sell_hour']);
			$resource['sell_day'] = preg_replace('/[^.0-9]/', "", $_POST['sell_day']);
			$resource['sell_halfday'] = preg_replace('/[^.0-9]/', "", $_POST['sell_halfday']);
			$resource['descr'] = $_POST['descr'];

			if ( $login_date_start && $login_date_end && strtotime($login_date_end) <= strtotime($login_date_start) ) {

				$this->set_error("err1_3b{$this->popup_id}");
				return $this->__trigger_error("Please select a valid date range.", E_USER_ERROR, __FILE__, __LINE__, 1);
			}

			if ( $ip_restriction && ! $this->validator->is_ipaddress($ip_restriction) ) {

				$this->set_error("err1_3c{$this->popup_id}");
				return $this->__trigger_error("The IP address you entered is invalid!", E_USER_ERROR, __FILE__, __LINE__, 1);
			}

			if ( ! $resource['vendor_hash'] && $user_hash ) {

				$this->set_error("err2_5_1a{$this->popup_id}");
				return $this->__trigger_error("The vendor you entered to be applied for this user as a resource cannot be found. Please enter a valid vendor.", E_USER_ERROR, __FILE__, __LINE__, 1);
			}

			if ( $user_hash && ( $resource['cost_hour'] && $resource['cost_hour'] < 0 ) || ( $resource['cost_day']  && $resource['cost_day'] < 0 ) || ( $resource['cost_halfday']  && $resource['cost_halfday'] < 0 ) ) {

				if ( $resource['cost_hour'] && $resource['cost_hour'] < 0 ) $this->set_error("err2_5_2{$this->popup_id}");
				if ( $resource['cost_day'] && $resource['cost_day'] < 0 ) $this->set_error("err2_5_3{$this->popup_id}");
				if ( $resource['cost_halfday'] && $resource['cost_halfday'] < 0 ) $this->set_error("err2_5_4{$this->popup_id}");

				return $this->__trigger_error("Please check to make sure you indicated cost is a valid amount.", E_USER_ERROR, __FILE__, __LINE__, 1);
			}
			if ( $user_hash && ( $resource['sell_hour'] && $resource['sell_hour'] < 0 ) || ( $resource['sell_day'] && $resource['sell_day'] < 0 ) || ( $resource['sell_halfday'] && $resource['sell_halfday'] < 0 ) ) {

				if ($resource['sell_hour'] && $resource['sell_hour'] < 0) $this->set_error("err2_5_6{$this->popup_id}");
				if ($resource['sell_day'] && $resource['sell_day'] < 0) $this->set_error("err2_5_7{$this->popup_id}");
				if ($resource['sell_halfday'] && $resource['sell_halfday'] < 0) $this->set_error("err2_5_8{$this->popup_id}");

				return $this->__trigger_error("Please check to make sure you indicated sell is a valid amount.", E_USER_ERROR, __FILE__, __LINE__, 1);
			}

			if ( $user_hash ) {

				if ( ! $this->fetch_user_record($user_hash) )
    				return $this->__trigger_error("System error encountered when attempting to lookup user record. Please reload window and try again. <!-- Tried fetching user [ $user_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			} else {

				$result = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
											FROM users t1
											WHERE t1.user_name = '$user_name'");
				$user_exists = $this->db->result($result, 0, 'Total');
				$valid_user = global_classes::validateUsername($user_name);
				if ( ! $valid_user )
					$valid_user = 1;
				else
					unset($valid_user);

				/*
				Allow for the same unique id to be used for multiple users
				if ($unique_id) {
					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM `users`
												WHERE `unique_id` = '$unique_id'");
					$id_exists = $this->db->result($result);
				}
				*/
			}

			if ( $user_exists || $valid_user || $id_exists || ( $email && ! $this->validator->is_email($email) ) || ( $receive_email && ! $email ) ) {

				if ( $user_exists || $valid_user ) {

					$this->set_error("err1_2{$this->popup_id}");
					$this->set_error_msg( ( $valid_user ? "The username you entered is invalid. Usernames cannot have special charactors or spaces. Please enter a valid username." : "The username you entered already exists. Please enter a unique username.") );
				}

				if ( $id_exists ) {

					$this->set_error("err1_3a{$this->popup_id}");
					$this->set_error_msg("The unique ID you assigned to this user already exists. Please select an ID that has not previously been assigned.");
				}

				if ( $email && ! $this->validator->is_email($email) ) {

					$this->set_error("err1_4{$this->popup_id}");
					$this->set_error_msg("The email address you entered is invalid. Please enter a valid email.");
				}

				if ( $receive_email && ! $email ) {

					$this->set_error("err1_4{$this->popup_id}");
					$this->set_error_msg("In order to receive system emails and alerts, please enter an email address.");
				}

				return $this->__trigger_error(NULL, E_USER_ERROR, __FILE__, __LINE__, 1);
			}

			if ( ! $this->db->start_transaction() )
    			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			if ( $this->user_hash ) {

				$sql = array();

				( $name != stripslashes($this->current_user['full_name']) ? array_push($sql, "t1.full_name = '" . addslashes($name) . "'") : NULL );

				/*
                ** 8/16/2010 - Disable changing of usernames
				if ( $user_name != $this->current_user['user_name'] ) {

					array_push($sql, "t1.user_name = '$user_name'");
					$_SESSION['user_name'] = $user_name;
				}
                */

				if ( $password != $this->current_user['password_plain'] ) {

					array_push($sql, "t1.password = '" . md5($password) . "'");
					array_push($sql, "t1.password_plain = '$password'");
				}
				if ( $lock != $this->current_user['user_lock'] ) {

					array_push($sql, "t1.user_lock = '$lock'");
					if ( $lock )
						$this->db->query("DELETE FROM `session`
										  WHERE `id_hash` = '$user_hash'");
				}

				( $active != $this->current_user['active'] ? array_push($sql, "t1.active = '$active'") : NULL );
				( $unique_id != $this->current_user['unique_id'] ? array_push($sql, "t1.unique_id = '$unique_id'") : NULL );
				( $commission_hash != $this->current_user['commission_hash'] ? array_push($sql, "t1.commission_hash = '$commission_hash'") : NULL );
				( $email != $this->current_user['email'] ? array_push($sql, "t1.email = '$email'") : NULL );
                ( $phone != $this->current_user['phone'] ? array_push($sql, "t1.phone = '$phone'") : NULL );
                ( $fax != $this->current_user['fax'] ? array_push($sql, "t1.fax = '$fax'") : NULL );
				( $receive_email != $this->current_user['receive_email'] ? array_push($sql, "t1.receive_email = '$receive_email'") : NULL );
				( ! $login_date_start || $login_date_start != $this->current_user['login_date_start'] ? array_push($sql, "t1.start_date = '$login_date_start'") : NULL );
				( ! $login_date_end || $login_date_end != $this->current_user['login_date_end'] ? array_push($sql, "t1.end_date = '$login_date_end'") : NULL );
				( $ip_restriction != $this->current_user['ip_restriction'] ? array_push($sql, "t1.ip_restriction = '$ip_restriction'") : NULL );

				# The user has been removed from the following groups
				$group_rm = array_diff($this->current_user['groups'], $group);
				if ( ! count($group) && count($this->current_user['groups']))
					$group_rm = $this->current_user['groups'];

				if ( is_array($group_rm) ) {

					$update = 1;
					while ( list($key, $val) = each($group_rm) ) {

						if ( ! $this->db->query("DELETE FROM user_subscribe
        										 WHERE id_hash = '$user_hash' AND group_hash = '$val'")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
					}
				}

				# The user has been added to the following groups
				$group_add = array_diff($group, $this->current_user['groups']);
				if ( ! count($this->current_user['groups']) && count($group) )
					$group_add = $group;
				if ( is_array($group_add) ) {

					$update = 1;
					while ( list($key, $val) = each($group_add) ) {

						if ( ! $this->db->query("INSERT INTO user_subscribe
        										 VALUES
        										 (
	        										 '$user_hash',
	        										 '$val'
        										 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
					}
				}

				$resource = $this->db->prepare_query($resource, "resources", 'UPDATE');
				if ( ! $this->db->query("UPDATE resources t1
        								 SET
	        								 t1.timestamp = UNIX_TIMESTAMP(),
	        								 t1.last_change = '{$this->current_hash}',
	        								 " . implode(", ", $resource) . "
        								 WHERE t1.resource_hash = '{$this->current_user['resource_hash']}'")
                ) {

                }

                if ( $active != $this->current_user['active'] || $lock != $this->current_user['user_lock'] ) {

                    # The user is currently billable
                	if ( $this->current_user['active'] && ! $this->current_user['user_lock'] ) {

                        if ( ! $this->db->query("INSERT INTO user_action
                                                 VALUES
                                                 (
	                                                 NULL,
	                                                 '{$this->user_hash}',
	                                                 UNIX_TIMESTAMP(),
	                                                 0,
	                                                 '{$this->current_hash}'
                                                 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }

                	} elseif ( ! $this->current_user['active'] || $this->current_user['user_lock'] ) {

                        if ( $active && ! $lock ) {

	                        if ( ! $this->db->query("INSERT INTO user_action
        	                                         VALUES
        	                                         (
	        	                                         NULL,
	        	                                         '{$this->user_hash}',
	        	                                         UNIX_TIMESTAMP(),
	        	                                         1,
	        	                                         '{$this->current_hash}'
        	                                         )")
                            ) {

                            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                            }
                        }

                    }
                }

				if ( $sql ) {

					if ( ! $this->db->query("UPDATE users t1
											  SET
												  t1.timestamp = UNIX_TIMESTAMP(),
												  " . implode(", ", $sql) . "
											  WHERE t1.id_hash = '{$this->user_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }
				}

				$feedback = "User $user_name has been updated.";

			} else {

				$id_hash = rand_hash('users', 'id_hash');
				$resource_hash = rand_hash('resources', 'resource_hash');

				for ( $i = 0; $i < count($group); $i++ ) {

					if ( $group[$i] ) {

						if ( ! $this->db->query("INSERT INTO user_subscribe
        										 VALUES
        										 (
	        										 '$id_hash',
	        										 '{$group[$i]}'
        										 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
					}
				}

				if ( ! $this->db->query("INSERT INTO users
        								 VALUES
        								 (
	        								 NULL,
	        								 UNIX_TIMESTAMP(),
	        								 '$id_hash',
	        								 '$resource_hash',
	        								 '$commission_hash',
	        								 '$lock',
	        								 '$active',
	        								 '$user_name',
	        								 '10',
	        								 '" . md5($password) . "',
	        								 '$password',
	        								 '$unique_id',
	        								 '$salt',
	        								 '" . addslashes($name) . "',
	        								 '$email',
	        								 '$phone',
	        								 '$fax',
	        								 '$receive_email',
	        								 '',
	        								 '$login_date_start',
	        								 '$login_date_end',
	        								 '$ip_restriction'
        								 )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				if ( ! $this->db->query("INSERT INTO resources
        								 VALUES
        								 (
	        								 NULL,
	        								 UNIX_TIMESTAMP(),
	        								 '{$this->current_hash}',
	        								 '$resource_hash',
	        								 '{$resource['vendor_hash']}',
	        								 '{$resource['resource_name']}',
	        								 'L',
	        								 '{$resource['resource_active']}',
	        								 '{$resource['descr']}',
	        								 '{$resource['cost_hour']}',
	        								 0,
	        								 '{$resource['cost_day']}',
	        								 0,
	        								 '{$resource['cost_halfday']}',
	        								 0
        								 )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

                if ( ! $this->db->query("INSERT INTO user_action
                                         VALUES
                                         (
	                                         NULL,
	                                         '{$this->user_hash}',
	                                         UNIX_TIMESTAMP(),
	                                         " . ( $active && ! $lock ? "1" : "0" ) . ",
	                                         '{$this->current_hash}'
                                         )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				$feedback = "User $user_name has been created. You may configure user permissions below.";
				$this->content['jscript'][] = "agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_user', 'popup_id=line_item', 'user_hash=$id_hash')";
			}

			$this->db->end_transaction();

			$this->content['action'] = 'close';
			$this->content['page_feedback'] = $feedback;
			$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'user_list');";

			return;

		} else {

			if ( ! $_POST['full_name'] ) $this->set_error("err1_1{$this->popup_id}");
			if ( ! $_POST['user_name'] ) $this->set_error("err1_2{$this->popup_id}");
			if ( ! $_POST['password'] ) $this->set_error("err1_3{$this->popup_id}");
			if ( ! $_POST['email'] ) $this->set_error("err1_4{$this->popup_id}");

			return $this->__trigger_error("Please check that you have completed the requried fields.", E_USER_ERROR, __FILE__, __LINE__, 1);
		}
	}

	function doit_group() {

		$group_hash = $_POST['group_hash'];
		$method = $_POST['method'];

		if ( $method == 'rm' || $_POST['group_name'] ) {

			if ( $method == 'rm' ) {

				if ( ! $this->db->start_transaction() )
    				return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

				if ( ! $this->db->query("DELETE
                            				 t1.*,
                            				 t2.*,
                            				 t3.*
        								 FROM user_groups t1
        								 LEFT JOIN user_subscribe t2 ON t2.group_hash = t1.group_hash
        								 LEFT JOIN user_permissions t3 ON t3.group_hash = t2.group_hash
        								 WHERE t1.group_hash = '$group_hash'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

                $this->db->end_transaction();

				$this->content['action'] = 'close';
				$this->content['page_feedback'] = "Group has been removed and users have been unsubscribed.";
				$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'group_list');";

				return;
			}

			$group_name = $_POST['group_name'];
			$lock = $_POST['lock'];
			$submit_to = $_POST['submit_to'];
			$group_members = $_POST['group_members'];
			for ( $i = 0; $i < count($group_members); $i++ ) {

				if ( $group_members[$i] )
					$groups[] = $group_members[$i];
			}

			$group_members = $groups;

			if ( $group_hash ) {

				if ( ! $this->fetch_group_record($group_hash) )
    				return $this->__trigger_error("System error encountered when attempting to lookup group record. Please reload window and try again. <!-- Tried fetching group [ $group_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1);

			} else {

				$result = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
											FROM user_groups t1
											WHERE t1.group_name = '$group_name'");
				if ( $group_exists = $this->db->result($result, 0, 'Total') ) {

                    $this->set_error("err1_1{$this->popup_id}");
                    return $this->__trigger_error("The group you entered already exists. Please enter a unique group name.", E_USER_ERROR, __FILE__, __LINE__, 1);
				}
			}

			if ( ! $this->db->start_transaction() )
    			return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);

			if ( $this->group_hash ) {

				$sql = array();

				( $group_name != stripslashes($this->current_group['group_name']) ? array_push($sql, "t1.group_name = '" . addslashes($group_name) . "'") : NULL );

				if ( $lock != $this->current_group['group_lock'] ) {

					array_push($sql, "t1.group_lock = '$lock'");
					if ( $lock ) {

						if ( ! $this->db->query("DELETE t1.*
												 FROM session t1
												 LEFT JOIN users t2 ON t2.id_hash = t1.id_hash
												 LEFT JOIN user_subscribe t3 ON t3.id_hash = t2.id_hash
												 WHERE t3.group_hash = '{$this->group_hash}' AND t1.id_hash != '{$_SESSION['id_hash']}'")
						) {

							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
						}
					}
				}

				if ( $submit_to != $this->current_group['submit_to'] )
					array_push($sql, "t1.submit_to = '$submit_to'");

				# The group has lost users
				$group_rm = array_diff($this->current_group['group_members'], $group_members);
				if ( ! count($group_members) && count($this->current_group['group_members']) )
					$group_rm = $this->current_group['group_members'];

				if ( is_array($group_rm) ) {

					$update = 1;
					while ( list($key, $val) = each($group_rm) ) {

						if ( ! $this->db->query("DELETE FROM user_subscribe
        										 WHERE group_hash = '{$this->group_hash}' AND id_hash = '$val'")
						) {

							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
						}
					}
				}

				# The group has gained users
				$group_add = array_diff($group_members, $this->current_group['group_members']);
				if ( ! count($this->current_group['group_members']) && count($group_members) )
					$group_add = $group_members;

				if ( is_array($group_add) ) {

					$update = 1;
					while ( list($key, $val) = each($group_add) ) {

						if ( ! $this->db->query("INSERT INTO user_subscribe
        										 VALUES
        										 (
	        										 '$val',
	        										 '{$this->group_hash}'
        										 )")
						) {

							return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
						}
					}
				}

				if ( $sql ) {

					if ( ! $this->db->query("UPDATE user_groups t1
        									 SET " . implode(", ", $sql) . "
        									 WHERE t1.group_hash = '{$this->group_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                    }

					$feedback = "Group $group_name has been updated.";

				} elseif ( $update )
					$feedback = "Group $group_name has been updated.";
				else
					$feedback = "No changes have been made.";

			} else {

				$group_hash = rand_hash('user_groups', 'group_hash');

				for ( $i = 0; $i < count($group_members); $i++ ) {

					if ( $group_members[$i] ) {

						if ( ! $this->db->query("INSERT INTO user_subscribe
        										 VALUES
        										 (
	        										 '{$group_members[$i]}',
	        										 '$group_hash'
        										 )")
                        ) {

                        	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                        }
					}
				}

				if ( ! $this->db->query("INSERT INTO user_groups
        								 (
	        								 group_hash,
	        								 group_lock,
	        								 group_name,
	        								 submit_to
        								 )
        								 VALUES
        								 (
	        								 '$group_hash',
	        								 '$lock',
	        								 '$group_name',
	        								 '$submit_to'
        								 )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

				$feedback = "Group $group_name has been created.";
			}

			$this->db->end_transaction();

			$this->content['action'] = 'close';
			$this->content['page_feedback'] = $feedback;
			$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'group_list');";

			return;

		} else {

			if ( ! $_POST['group_name'] ) $this->set_error("err1_1{$this->popup_id}");
			return $this->__trigger_error("Please check that you have completed the requried fields.", E_USER_ERROR, __FILE__, __LINE__, 1);
		}
	}

	function doit() {

		$action = $_POST['action'];
		$btn = $_POST['btn'];
		$jscript_action = $_POST['jscript_action'];

		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		if ( $action )
			return $this->$action();
	}

	function edit_user() {

		if ( $user_hash = $this->ajax_vars['user_hash'] ) {

			if ( ! $this->fetch_user_record($user_hash) )
				return $this->__trigger_error("System error when attempting to fetch user record. Please reload window and try again. <!-- Tried fetching user [ $user_hash ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

		}

		$this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->content['popup_controls']['popup_title'] = ( $this->user_hash ? "Edit User : " . stripslashes($this->current_user['full_name']) : "Create New User" );

		$this->fetch_groups();
		$group_name = $group_hash = $commission_rule = $commission_hash = array();

		for ( $i = 0; $i < count($this->group_info); $i++ ) {

			array_push($group_name, $this->group_info[$i]['group_name']);
			array_push($group_hash, $this->group_info[$i]['group_hash']);
		}

		$this->fetch_commissions();
		array_push($commission_rule, "No Commission");
		array_push($commission_hash, '');

		for ( $i = 0; $i < count($this->commissions); $i++ ) {

			if ( $this->commissions[$i]['type'] == 'D' ) {

				array_push($commission_rule, $this->commissions[$i]['name']);
				array_push($commission_hash, $this->commissions[$i]['commission_hash']);
			}
		}

		$this->fetch_resource_record($this->current_user['resource_hash']);

		$tbl =
		$this->form->form_tag() .
		$this->form->hidden( array(
			"user_hash" => $this->user_hash,
			"popup_id"	=> $this->popup_id
		) ) . "
		<div class=\"panel\" id=\"main_table{$this->popup_id}\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<div style=\"padding:15px 0 0 15px;font-weight:bold;\"></div>
			<ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">
				<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\">User Info</a></li>" .
				( $this->user_hash ?
					"<li ><a href=\"javascript:void(0);\" rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\">Permissions</a></li>
					<li ><a href=\"javascript:void(0);\" rel=\"tcontent3{$this->popup_id}\" onClick=\"expandcontent(this);\">Resources</a></li>" : NULL
				) . "
			</ul>
			<div id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err1_1{$this->popup_id}\">Name: *</td>
						<td style=\"background-color:#ffffff;padding-top:10px;\">" .
							$this->form->text_box(
								"name=full_name",
								"value=" . stripslashes($this->current_user['full_name']),
								"size=25",
								"maxlength=255"
							) . "
							<div style=\"padding-top:5px;\">" .
							$this->form->checkbox(
								"name=active",
								"value=1",
								( $this->current_user['active'] || ! $this->user_hash ? "checked" : NULL ),
								"title=Only active users are able to be assigned within proposals"
							) . "&nbsp;Is this user active?
							</div>
							<div style=\"padding-top:5px;\">" .
							$this->form->checkbox(
								"name=lock",
								"value=1",
								( $this->current_user['user_lock'] ? "checked" : NULL ),
								"title=Locked users may not log into DealerChoice"
							) . "&nbsp;Place a lock on this user?
							</div>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err1_2{$this->popup_id}\">User Name: *</td>
						<td style=\"background-color:#ffffff;padding-top:10px;\">" .
						( $this->user_hash ?
                            $this->current_user['user_name'] .
                            $this->form->hidden( array(
                                'user_name' =>  $this->current_user['user_name']
                            ) ) :
    						$this->form->text_box(
        						"name=user_name",
        						"value={$this->current_user['user_name']}",
        						"size=15",
        						"maxlength=25"
        					)
        				) . "
        				</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_3{$this->popup_id}\">Password: *</td>
						<td style=\"background-color:#ffffff;\">" .
							$this->form->text_box(
								"name=password",
								"value=" . $this->current_user['password_plain'],
								"size=15",
								"maxlength=64"
							) . "
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_3b{$this->popup_id}\" title=\"By setting date restrictions, this user will only be allowed to access DealerChoice between the date range you set.\">Login Date Restriction:</td>
						<td style=\"background-color:#ffffff;\">
							<div style=\"margin-top:5px;\" id=\"login_date_start_holder{$this->popup_id}\"></div>
							<div style=\"margin-top:5px;\" id=\"login_date_end_holder{$this->popup_id}\"></div>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_3c{$this->popup_id}\" title=\"By setting IP restrictions, this user will only be allowed to access DealerChoice from the specified IP address.\">IP Address Restriction:</td>
						<td style=\"background-color:#ffffff;\">" .
							$this->form->text_box(
								"name=ip_restriction",
								"value={$this->current_user['ip_restriction']}",
								"maxlength=16"
							) . "
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" title=\"This is an optional field that will allow purchase order numbers to reflect the original sales rep.\" id=\"err1_3a{$this->popup_id}\">User ID: </td>
						<td style=\"background-color:#ffffff;\">" .
							$this->form->text_box(
								"name=unique_id",
								"value={$this->current_user['unique_id']}",
								"size=5",
								"maxlength=5"
							) . "
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err1_3a{$this->popup_id}\">Commission: </td>
						<td style=\"background-color:#ffffff;\">" .
							$this->form->select(
								"user_commission",
								$commission_rule,
								$this->current_user['commission_hash'],
								$commission_hash,
								"blank=1"
							) . "
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_4{$this->popup_id}\">Email: *</td>
						<td style=\"background-color:#ffffff;\">" .
							$this->form->text_box(
								"name=email",
								"value={$this->current_user['email']}",
								"size=25",
								"maxlength=255"
							) . "
							<div style=\"padding-top:5px;\">" .
							$this->form->checkbox(
								"name=receive_email",
								"value=1",
								( $this->current_user['receive_email'] || ! $this->user_hash ? "checked" : NULL ),
								"title=System alerts are sent to this user will be copied to the above email"
							) . "&nbsp;Receive messages &amp; alerts as email?
							</div>
						</td>
					</tr>
                    <tr>
                        <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_3c{$this->popup_id}\">Phone:</td>
                        <td style=\"background-color:#ffffff;\">" .
	                        $this->form->text_box(
		                        "name=phone",
		                        "value={$this->current_user['phone']}",
		                        "maxlength=26"
	                        ) . "
						</td>
                    </tr>
                    <tr>
                        <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_3c{$this->popup_id}\" >Fax:</td>
                        <td style=\"background-color:#ffffff;\">" .
	                        $this->form->text_box(
		                        "name=fax",
		                        "value={$this->current_user['fax']}",
		                        "maxlength=16"
							) . "
						</td>
                    </tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err1_5{$this->popup_id}\">Group: </td>
						<td style=\"background-color:#ffffff;\">" .
							$this->form->select(
								"group[]",
								$group_name,
								$this->current_user['groups'],
								$group_hash,
								"multiple",
								"size=5",
								"style=width:250px;",
								"blank=1"
							) . "
						</td>
					</tr>
				</table>
			</div>" .
			( $this->user_hash ?
				"<div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">" . $this->permissions_form() . "</div>
				<div id=\"tcontent3{$this->popup_id}\" class=\"tabcontent\">
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:15px;vertical-align:top;\" id=\"err2_5_1{$this->popup_id}\">Resource Name: *</td>
							<td style=\"background-color:#ffffff;padding-top:15px;\">" .
								$this->form->text_box(
									"name=resource_name",
									"value=" . stripslashes($this->current_resource['resource_name']),
									"size=35",
									"maxlength=128"
								) . "
								<div style=\"padding-top:5px;\">" .
									$this->form->checkbox(
										"name=resource_active",
										"value=1",
										( $this->current_resource['active'] ? "checked" : NULL )
									) . "&nbsp;Active?
								</div>
							</td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;padding-top:15px;vertical-align:top;\" id=\"err2_5_1a{$this->popup_id}\">Vendor:</td>
							<td style=\"background-color:#ffffff;padding-top:10px;\">" .
								$this->form->text_box(
									"name=resource_vendor",
									"value=" . stripslashes($this->current_resource['resource_vendor']),
									"autocomplete=off",
									"size=30",
									"TABINDEX=1",
									"onFocus=position_element($('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(\$F('resource_vendor_hash')==''){clear_values('resource_vendor');}key_call('proposals', 'resource_vendor', 'resource_vendor_hash', 1);",
									"onBlur=vtobj.reset_keyResults();key_clear();",
									"onKeyDown=clear_values('resource_vendor_hash');"
								) .
								$this->form->hidden( array(
									"resource_vendor_hash" => $this->current_resource['vendor_hash']
								) ) . "
							</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_5_2{$this->popup_id}\">Hourly Cost: </td>
							<td style=\"background-color:#ffffff;\">" .
								$this->form->text_box(
									"name=cost_hour",
									"value={$this->current_resource['cost_hour']}",
									"size=10",
									"onBlur=if(this.value){this.value=formatCurrency(this.value)}",
									"maxlength=128",
									"style=text-align:right;"
								) . "
								<span style=\"padding-left:25px;\">
									Sell: " .
									$this->form->text_box(
										"name=sell_hour",
										"value={$this->current_resource['sell_hour']}",
										"size=10",
										"onBlur=if(this.value){this.value=formatCurrency(this.value)}",
										"maxlength=128",
										"style=text-align:right;"
									) . "
								</span>
							</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_5_3{$this->popup_id}\">Daily Cost: </td>
							<td style=\"background-color:#ffffff;\">" .
								$this->form->text_box(
									"name=cost_day",
									"value={$this->current_resource['cost_day']}",
									"onBlur=if(this.value){this.value=formatCurrency(this.value)}",
									"size=10",
									"maxlength=128",
									"style=text-align:right;"
								) . "
								<span style=\"padding-left:25px;\">
									Sell: " .
									$this->form->text_box(
										"name=sell_day",
										"value={$this->current_resource['sell_day']}",
										"size=10",
										"onBlur=if(this.value){this.value=formatCurrency(this.value)}",
										"maxlength=128",
										"style=text-align:right;"
									) . "
								</span>
							</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_5_4{$this->popup_id}\">Half Day Cost: </td>
							<td style=\"background-color:#ffffff;\">" .
								$this->form->text_box(
									"name=cost_halfday",
									"value={$this->current_resource['cost_halfday']}",
									"onBlur=if(this.value){this.value=formatCurrency(this.value)}",
									"size=10",
									"maxlength=128",
									"style=text-align:right;"
								) . "
								<span style=\"padding-left:25px;\">
									Sell: " .
									$this->form->text_box(
										"name=sell_halfday",
										"value={$this->current_resource['sell_halfday']}",
										"size=10",
										"onBlur=if(this.value){this.value=formatCurrency(this.value)}",
										"maxlength=128",
										"style=text-align:right;"
									) . "
								</span>
							</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\">Description: </td>
							<td style=\"background-color:#ffffff;\">" .
								$this->form->text_area(
									"name=descr",
									"value=" . stripslashes($this->current_resource['descr']),
									"rows=4",
									"cols=50"
								) . "
							</td>
						</tr>
					</table>
				</div>" : NULL
			) . "
			<div style=\"padding:10px 15px;text-align:left;\">" .
				( ! $this->user_hash || ( $this->user_hash && $this->p->ck(get_class($this), 'E', 'users') ) ?
					$this->form->button(
						"value=Save User",
						"onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_user');"
					) : NULL
				) . "&nbsp;&nbsp;" .
				( $this->user_hash && ! $this->current_user['no_delete'] && $this->p->ck(get_class($this), 'D', 'users') ?
					$this->form->button(
						"value=Delete User",
						"onClick=if(confirm('Are you sure you want to delete this user? This action CANNOT be undone!')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_user', 'method=rm');}"
					) : NULL
				) . "
			</div>
		</div>" .
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('DateInput(\'login_date_start\', false, \'YYYY-MM-DD\', \'" . ( $this->current_user['start_date'] && $this->current_user['start_date'] != '0000-00-00' ? $this->current_user['start_date'] : NULL ) . "\', 1, \'login_date_start_holder{$this->popup_id}\', \'From:\')', 45)";
		$this->content["jscript"][] = "setTimeout('DateInput(\'login_date_end\', false, \'YYYY-MM-DD\', \'" . ( $this->current_user['end_date'] && $this->current_user['end_date'] != '0000-00-00' ? $this->current_user['end_date'] : NULL ) . "\', 1, \'login_date_end_holder{$this->popup_id}\', \'&nbsp;&nbsp;Thru:\')', 55)";

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')', 10);";
		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function permissions_form($local=NULL) {
		if ($_POST['user_hash'] && !$this->user_hash)
			$this->fetch_user_record($_POST['user_hash']);
		elseif ($_POST['group_hash'] && !$this->group_hash)
			$this->fetch_group_record($_POST['group_hash']);

		$perms = new permissions($this->user_hash.$this->group_hash, ($this->group_hash ? "G" : NULL), 1);
		$perms->fetch_user_permissions();

		$tbl = "
		<table cellspacing=\"0\" cellpadding=\"5\" style=\"width:700px;margin-top:0;border:1px solid #8c8c8c\" class=\"smallfont\">
			<tr>
				<td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;padding-top:10px;padding-left:15px;\">
					<fieldset>
						<legend style=\"font-weight:bold;\">".($this->user_hash ? "User" : "Group")." Permissions</legend>
						<div style=\"padding-top:5px;margin-left:15px;\">
							<table style=\"width:100%;\">
								<tr>
									<td style=\"padding-top:5px;\">
										<table style=\"width:100%;\">
											<tr>
												<td colspan=\"3\">
													<div style=\"font-style:italic;\">
    													Please check the desired permissions for " .
                                                		( $this->group_hash ?
                                                    		stripslashes($this->current_group['group_name']) : stripslashes($this->current_user['full_name'])
                                                    	) . " and click 'Save Permissions' below.
    												</div>
													<div style=\"margin:10px 15px;\">
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_arch_design');\" name=\"imgperm_holder_arch_design\" title=\"Click to view and edit A & D permissions\" />
                                                            <span onClick=\"shoh('perm_holder_arch_design');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand A & D permissions\">A & D</span>
                                                        </div>
                                                        <div id=\"perm_holder_arch_design\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "value=L", ($perms->ck('arch_design', 'L') ? "checked" : NULL))."&nbsp;View A & D List</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "onClick=permission_check_box(this);", "value=V", ($perms->ck('arch_design', 'V') ? "checked" : NULL))."&nbsp;View A & D Details</div>
                                                                    </td>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "onClick=permission_check_box(this);", "value=E", ($perms->ck('arch_design', 'E') ? "checked" : NULL))."&nbsp;Edit A & D Details</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "onClick=permission_check_box(this);", "value=A", ($perms->ck('arch_design', 'A') ? "checked" : NULL))."&nbsp;Create A & D Firms</div>
                                                                    </td>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "onClick=permission_check_box(this);", "value=D", ($perms->ck('arch_design', 'D') ? "checked" : NULL))."&nbsp;Delete A & D Firms</div>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td colspan=\"3\" style=\"padding-top:10px;\">
                                                                        <div style=\"padding-top:15px;\">
                                                                            <table style=\"width:100%;\" >
                                                                                <tr>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>General Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "value=V|general", ($perms->ck('arch_design', 'V', 'general') ? "checked" : NULL))."&nbsp;View</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "value=E|general", ($perms->ck('arch_design', 'E', 'general') ? "checked" : NULL))."&nbsp;Edit</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Contact Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "value=V|contacts", ($perms->ck('arch_design', 'V', 'contacts') ? "checked" : NULL))."&nbsp;View Contacts</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "value=E|contacts", ($perms->ck('arch_design', 'E', 'contacts') ? "checked" : NULL))."&nbsp;Edit Contacts</div>
                                                                                                    </td>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "value=A|contacts", ($perms->ck('arch_design', 'A', 'contacts') ? "checked" : NULL))."&nbsp;Create Contacts</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "value=D|contacts", ($perms->ck('arch_design', 'D', 'contacts') ? "checked" : NULL))."&nbsp;Delete Contacts</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>A & D Stats</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=arch_design[]", "value=V|stats", ($perms->ck('arch_design', 'V', 'stats') ? "checked" : NULL))."&nbsp;View Statistics</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_accounting');\" name=\"imgperm_holder_accounting\" title=\"Click to view and edit accounting permissions\" />
                                                            <span onClick=\"shoh('perm_holder_accounting');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand accounting permissions\">Accounting</span>
                                                        </div>
                                                        <div id=\"perm_holder_accounting\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>General Journal</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=L|journal", ($perms->ck('accounting', 'L', 'journal') ? "checked" : NULL))."&nbsp;Show General Journal</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=A|journal", ($perms->ck('accounting', 'A', 'journal') ? "checked" : NULL))."&nbsp;Create Journal Entries</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=V|config", ($perms->ck('accounting', 'V', 'config') ? "checked" : NULL))."&nbsp;View Business Cycle Settings</div>
                                                                                    </td>
                                                                                    <td style=\"vertical-align:top;padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=E|config", ($perms->ck('accounting', 'E', 'config') ? "checked" : NULL))."&nbsp;Edit Business Cycle Settings</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=E|period", ($perms->ck('accounting', 'E', 'period') ? "checked" : NULL))."&nbsp;Perform Period Closings</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=P|period", ($perms->ck('accounting', 'P', 'period') ? "checked" : NULL))."&nbsp;Post Transactions Into Closed Periods</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Chart of Accounts</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=L|accounts", ($perms->ck('accounting', 'L', 'accounts') ? "checked" : NULL))."&nbsp;View Chart of Accounts</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=V|accounts", ($perms->ck('accounting', 'V', 'accounts') ? "checked" : NULL))."&nbsp;View Account Details</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=A|accounts", ($perms->ck('accounting', 'A', 'accounts') ? "checked" : NULL))."&nbsp;Create New Accounts</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=E|accounts", ($perms->ck('accounting', 'E', 'accounts') ? "checked" : NULL))."&nbsp;Edit Accounts</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=D|accounts", ($perms->ck('accounting', 'D', 'accounts') ? "checked" : NULL))."&nbsp;Delete Accounts</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Check Register</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=V|check_reg", ($perms->ck('accounting', 'V', 'check_reg') ? "checked" : NULL))."&nbsp;View Check Register</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=A|check_reg", ($perms->ck('accounting', 'A', 'check_reg') ? "checked" : NULL))."&nbsp;Create New Check Entries</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=D|check_reg", ($perms->ck('accounting', 'D', 'check_reg') ? "checked" : NULL))."&nbsp;Void Checks</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=L|check_reg", ($perms->ck('accounting', 'L', 'check_reg') ? "checked" : NULL))."&nbsp;Reprint Checks</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=E|check_reg", ($perms->ck('accounting', 'E', 'check_reg') ? "checked" : NULL))."&nbsp;Clear Checks</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Account Register</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=V|account_reg", ($perms->ck('accounting', 'V', 'account_reg') ? "checked" : NULL))."&nbsp;View Account Register</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=A|account_reg", ($perms->ck('accounting', 'A', 'account_reg') ? "checked" : NULL))."&nbsp;Create New Entries</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=E|account_reg", ($perms->ck('accounting', 'E', 'account_reg') ? "checked" : NULL))."&nbsp;Edit Existing Entries</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Bank Reconciliation</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=V|bank_rec", ($perms->ck('accounting', 'V', 'bank_rec') ? "checked" : NULL))."&nbsp;View Bank Reconciliation</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=accounting[]", "value=E|bank_rec", ($perms->ck('accounting', 'E', 'bank_rec') ? "checked" : NULL))."&nbsp;Run Bank Reconciliation</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_customers');\" name=\"imgperm_holder_customers\" title=\"Click to view and edit customer permissions\" />
                                                            <span onClick=\"shoh('perm_holder_customers');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand customer permissions\">Customers</span>
                                                        </div>
                                                        <div id=\"perm_holder_customers\" style=\"padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"width:50%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=L", ($perms->ck('customers', 'L') ? "checked" : NULL))."&nbsp;View Customer List</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=V", "onClick=permission_check_box(this);", ($perms->ck('customers', 'V') ? "checked" : NULL))."&nbsp;View Customer Details</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=E", "onClick=permission_check_box(this);", ($perms->ck('customers', 'E') ? "checked" : NULL))."&nbsp;Edit Customer Details</div>
                                                                    </td>
                                                                    <td style=\"width:50%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=A", "onClick=permission_check_box(this);", ($perms->ck('customers', 'A') ? "checked" : NULL))."&nbsp;Create New Customers</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=D", "onClick=permission_check_box(this);", ($perms->ck('customers', 'D') ? "checked" : NULL))."&nbsp;Delete Customers</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=A|rcv_pmt", ($perms->ck('customers', 'A', 'rcv_pmt') ? "checked" : NULL))."&nbsp;Receive Customer Payments</div>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td colspan=\"2\" style=\"padding-top:10px;\">
                                                                        <div style=\"padding-top:15px;\">
                                                                            <table style=\"width:100%;\" >
                                                                                <tr>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>General Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=V|general", ($perms->ck('customers', 'V', 'general') ? "checked" : NULL))."&nbsp;View General Info</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=E|general", ($perms->ck('customers', 'E', 'general') ? "checked" : NULL))."&nbsp;Edit General Info</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Payment Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=V|payments", ($perms->ck('customers', 'V', 'payments') ? "checked" : NULL))."&nbsp;View Payment Info</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=E|payments", ($perms->ck('customers', 'E', 'payments') ? "checked" : NULL))."&nbsp;Edit Payment Info</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Contact Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=V|contacts", ($perms->ck('customers', 'V', 'contacts') ? "checked" : NULL))."&nbsp;View Contacts</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=E|contacts", ($perms->ck('customers', 'E', 'contacts') ? "checked" : NULL))."&nbsp;Edit Contacts</div>
                                                                                                    </td>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=A|contacts", ($perms->ck('customers', 'A', 'contacts') ? "checked" : NULL))."&nbsp;Create Contacts</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=D|contacts", ($perms->ck('customers', 'D', 'contacts') ? "checked" : NULL))."&nbsp;Delete Contacts</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Location Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=V|locations", ($perms->ck('customers', 'V', 'locations') ? "checked" : NULL))."&nbsp;View Locations</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=E|locations", ($perms->ck('customers', 'E', 'locations') ? "checked" : NULL))."&nbsp;Edit Locations</div>
                                                                                                    </td>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=A|locations", ($perms->ck('customers', 'A', 'locations') ? "checked" : NULL))."&nbsp;Create Locations</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=D|locations", ($perms->ck('customers', 'D', 'locations') ? "checked" : NULL))."&nbsp;Delete Locations</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>

                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Discounting Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=V|discounting", ($perms->ck('customers', 'V', 'discounting') ? "checked" : NULL))."&nbsp;View Discounts</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=E|discounting", ($perms->ck('customers', 'E', 'discounting') ? "checked" : NULL))."&nbsp;Edit Discounts</div>
                                                                                                    </td>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=A|discounting", ($perms->ck('customers', 'A', 'discounting') ? "checked" : NULL))."&nbsp;Create Discounts</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=D|discounting", ($perms->ck('customers', 'D', 'discounting') ? "checked" : NULL))."&nbsp;Delete Discounts</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;vertical-align:top;\">
                                                                                        <fieldset>
                                                                                            <legend>Customer Stats</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;vertical-align:top;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customers[]", "value=V|stats", ($perms->ck('customers', 'V', 'stats') ? "checked" : NULL))."&nbsp;View Customer Stats</div>
                                                                                                        <div style=\"padding-bottom:5px;\">&nbsp;</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_cust_credits');\" name=\"imgperm_holder_cust_credits\" title=\"Click to view and edit customer credit permissions\" />
                                                            <span onClick=\"shoh('perm_holder_cust_credits');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand customer credit permissions\">Customer Credits</span>
                                                        </div>
                                                        <div id=\"perm_holder_cust_credits\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"width:50%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_credits[]", "value=L", ($perms->ck('customer_credits', 'L') ? "checked" : NULL))."&nbsp;View Credits List</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_credits[]", "value=V", ($perms->ck('customer_credits', 'V') ? "checked" : NULL))."&nbsp;View Credits Details</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_credits[]", "value=E", ($perms->ck('customer_credits', 'E') ? "checked" : NULL))."&nbsp;Edit Customer Credits</div>

                                                                    </td>
                                                                    <td style=\"width:50%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_credits[]", "value=D", ($perms->ck('customer_credits', 'D') ? "checked" : NULL))."&nbsp;Delete Credits</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_credits[]", "value=A", ($perms->ck('customer_credits', 'A') ? "checked" : NULL))."&nbsp;Create New Credits</div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_customer_invoice');\" name=\"imgperm_holder_customer_invoice\" title=\"Click to view and edit customer invoice permissions\" />
                                                            <span onClick=\"shoh('perm_holder_customer_invoice');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand customer invoice permissions\">Customer Receivables</span>
                                                        </div>
                                                        <div id=\"perm_holder_customer_invoice\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=A", ($perms->ck('customer_invoice', 'A') ? "checked" : NULL))."&nbsp;Create Invoices</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=D", ($perms->ck('customer_invoice', 'D') ? "checked" : NULL))."&nbsp;Delete Invoices</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=V", ($perms->ck('customer_invoice', 'V') ? "checked" : NULL))."&nbsp;View Invoice Detail</div>
                                                                    </td>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=P", ($perms->ck('customer_invoice', 'P') ? "checked" : NULL))."&nbsp;Edit Print Preferences</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=A|line_items", ($perms->ck('customer_invoice', 'A', 'line_items') ? "checked" : NULL))."&nbsp;Add Line Items</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=D|line_items", ($perms->ck('customer_invoice', 'D', 'line_items') ? "checked" : NULL))."&nbsp;Delete Line Items</div>
                                                                    </td>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=E|line_items", ($perms->ck('customer_invoice', 'E', 'line_items') ? "checked" : NULL))."&nbsp;Edit Line Items</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=E", ($perms->ck('customer_invoice', 'E') ? "checked" : NULL))."&nbsp;Add/Edit Finance Charges</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=customer_invoice[]", "value=RD", ($perms->ck('customer_invoice', 'RD') ? "checked" : NULL))."&nbsp;Receive Customer Deposits</div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_pm');\" name=\"imgperm_holder_pm\" title=\"Click to view and edit project management permissions\" />
                                                            <span onClick=\"shoh('perm_holder_pm');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand project management permissions\">Project Management</span>
                                                        </div>
                                                        <div id=\"perm_holder_pm\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Work Orders</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=L|work_orders", ($perms->ck('pm_module', 'L', 'work_orders') ? "checked" : NULL))."&nbsp;View Work Order List</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=V|work_orders", ($perms->ck('pm_module', 'V', 'work_orders') ? "checked" : NULL))."&nbsp;View Work Order Detail</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=E|work_orders", ($perms->ck('pm_module', 'E', 'work_orders') ? "checked" : NULL))."&nbsp;Edit Work Orders</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=A|work_orders", ($perms->ck('pm_module', 'A', 'work_orders') ? "checked" : NULL))."&nbsp;Create Work Orders</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=D|work_orders", ($perms->ck('pm_module', 'D', 'work_orders') ? "checked" : NULL))."&nbsp;Delete Work Orders</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=VR|work_orders", ($perms->ck('pm_module', 'VR', 'work_orders') ? "checked" : NULL))."&nbsp;View Work Order Resources</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=AR|work_orders", ($perms->ck('pm_module', 'AR', 'work_orders') ? "checked" : NULL))."&nbsp;Add New Resources</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=VT|work_orders", ($perms->ck('pm_module', 'VT', 'work_orders') ? "checked" : NULL))."&nbsp;View True Costs</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Punchlist</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=V|punchlist", ($perms->ck('pm_module', 'V', 'punchlist') ? "checked" : NULL))."&nbsp;View Punch Item Detail</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=E|punchlist", ($perms->ck('pm_module', 'E', 'punchlist') ? "checked" : NULL))."&nbsp;Edit Punch Items</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=A|punchlist", ($perms->ck('pm_module', 'A', 'punchlist') ? "checked" : NULL))."&nbsp;Create Punch Items</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=D|punchlist", ($perms->ck('pm_module', 'D', 'punchlist') ? "checked" : NULL))."&nbsp;Delete Punch Items</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=CP|punchlist", ($perms->ck('pm_module', 'CP', 'punchlist') ? "checked" : NULL))."&nbsp;Create Punch Purchase Orders</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=CI|punchlist", ($perms->ck('pm_module', 'CI', 'punchlist') ? "checked" : NULL))."&nbsp;Create Punch Invoices</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Installation/Delivery Schedule</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=L|schedule", ($perms->ck('pm_module', 'L', 'schedule') ? "checked" : NULL))."&nbsp;View Schedule</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=E|schedule", ($perms->ck('pm_module', 'E', 'schedule') ? "checked" : NULL))."&nbsp;View Schedule Details</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=pm_module[]", "value=A|schedule", ($perms->ck('pm_module', 'A', 'schedule') ? "checked" : NULL))."&nbsp;Edit Completion Report</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_proposals');\" name=\"imgperm_holder_proposals\" title=\"Click to view and edit proposal permissions\" />
                                                            <span onClick=\"shoh('perm_holder_proposals');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand proposal permissions\">Proposals</span>
                                                        </div>
                                                        <div id=\"perm_holder_proposals\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L", "onClick=permission_check_box(this);", ($perms->ck('proposals', 'L') ? "checked" : NULL))."&nbsp;View Proposal List</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=V", "onClick=permission_check_box(this);", ($perms->ck('proposals', 'V') ? "checked" : NULL))."&nbsp;View Proposal Details</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=S", ($perms->ck('proposals', 'S') ? "checked" : NULL))."&nbsp;Restrict Proposal View to<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Sales Rep Ownership</div>
                                                                    </td>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=E", "onClick=permission_check_box(this);", ($perms->ck('proposals', 'E') ? "checked" : NULL))."&nbsp;Edit Proposal Details</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=A", "onClick=permission_check_box(this);", ($perms->ck('proposals', 'A') ? "checked" : NULL))."&nbsp;Create New Proposals</div>
                                                                    </td>
                                                                    <td style=\"width:33%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=D", "onClick=permission_check_box(this);", ($perms->ck('proposals', 'D') ? "checked" : NULL))."&nbsp;Delete Proposals</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=P", ($perms->ck('proposals', 'P') ? "checked" : NULL))."&nbsp;Edit Print Preferences</div>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td colspan=\"3\" style=\"padding-top:10px;\">
                                                                        <div style=\"padding-top:15px;\">
                                                                            <table style=\"width:100%;\" >
                                                                                <tr>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Project Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=V|project", ($perms->ck('proposals', 'V', 'project') ? "checked" : NULL))."&nbsp;View Project Info</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=E|project", ($perms->ck('proposals', 'E', 'project') ? "checked" : NULL))."&nbsp;Edit Project Info</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Design Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=V|design", ($perms->ck('proposals', 'V', 'design') ? "checked" : NULL))."&nbsp;View Design Info</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=E|design", ($perms->ck('proposals', 'E', 'design') ? "checked" : NULL))."&nbsp;Edit Design Info</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style=\"width:50%;vertical-align:top;\">
                                                                                        <fieldset >
                                                                                            <legend>Install Info</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=V|install", ($perms->ck('proposals', 'V', 'install') ? "checked" : NULL))."&nbsp;View Install Info</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=E|install", ($perms->ck('proposals', 'E', 'install') ? "checked" : NULL))."&nbsp;Edit Install Info</div>
                                                                                                        <div style=\"padding-bottom:5px;\">&nbsp;</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Item Details</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|item_details", ($perms->ck('proposals', 'L', 'item_details') ? "checked" : NULL))."&nbsp;View Item List</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=V|item_details", ($perms->ck('proposals', 'V', 'item_details') ? "checked" : NULL))."&nbsp;View Item Details</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=E|item_details", ($perms->ck('proposals', 'E', 'item_details') ? "checked" : NULL))."&nbsp;Edit Line Items</div>
                                                                                                    </td>
                                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=A|item_details", ($perms->ck('proposals', 'A', 'item_details') ? "checked" : NULL))."&nbsp;Create Line Items</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=D|item_details", ($perms->ck('proposals', 'D', 'item_details') ? "checked" : NULL))."&nbsp;Delete Line Items</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style=\"width:50%;\">
                                                                                        <fieldset>
                                                                                            <legend>Purchase Orders</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|purchase_order", ($perms->ck('proposals', 'L', 'purchase_order') ? "checked" : NULL))."&nbsp;View Purchase Order List</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;vertical-align:top;\">
                                                                                        <fieldset>
                                                                                            <legend>Customer Receivables</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|customer_invoice", ($perms->ck('proposals', 'L', 'customer_invoice') ? "checked" : NULL))."&nbsp;View Invoice List</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style=\"width:50%;vertical-align:top;\">
                                                                                        <fieldset>
                                                                                            <legend>Vendor Payables</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;vertical-align:top;\" nowrap>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|vendor_payables", ($perms->ck('proposals', 'L', 'vendor_payables') ? "checked" : NULL))."&nbsp;View Payables Tab</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|memo_costing", ($perms->ck('proposals', 'L', 'memo_costing') ? "checked" : NULL))."&nbsp;View Memo Costing</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|commissions_paid", ($perms->ck('proposals', 'L', 'commissions_paid') ? "checked" : NULL))."&nbsp;View Commissions Paid</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;vertical-align:top;\">
                                                                                        <fieldset>
                                                                                            <legend>File Vault</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|doc_vault", ($perms->ck('proposals', 'L', 'doc_vault') ? "checked" : NULL))."&nbsp;View Files</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=V|doc_vault", ($perms->ck('proposals', 'V', 'doc_vault') ? "checked" : NULL))."&nbsp;View File Details</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=E|doc_vault", ($perms->ck('proposals', 'E', 'doc_vault') ? "checked" : NULL))."&nbsp;Checkout Files</div>
                                                                                                    </td>
                                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=A|doc_vault", ($perms->ck('proposals', 'A', 'doc_vault') ? "checked" : NULL))."&nbsp;Create Files</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=D|doc_vault", ($perms->ck('proposals', 'D', 'doc_vault') ? "checked" : NULL))."&nbsp;Delete Files</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style=\"width:50%;vertical-align:top;\">
                                                                                        <fieldset>
                                                                                            <legend>Service & Punch</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;vertical-align:top;\" nowrap>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|pm_module", ($perms->ck('proposals', 'L', 'pm_module') ? "checked" : NULL))."&nbsp;View Service & Punch Tab</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|punch", ($perms->ck('proposals', 'L', 'punch') ? "checked" : NULL))."&nbsp;View Punchlist Tab</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=L|work_orders", ($perms->ck('proposals', 'L', 'work_orders') ? "checked" : NULL))."&nbsp;View Work Order Tab</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                    <td style=\"width:50%;vertical-align:top;\">
                                                                                        <fieldset>
                                                                                            <legend>Ledger</legend>
                                                                                            <table style=\"width:100%;\">
                                                                                                <tr>
                                                                                                    <td style=\"padding-top:5px;vertical-align:top;\" nowrap>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=V|ledger", ($perms->ck('proposals', 'V', 'ledger') ? "checked" : NULL))."&nbsp;View Ledger Tab</div>
                                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=proposals[]", "value=A|ledger", ($perms->ck('proposals', 'A', 'ledger') ? "checked" : NULL))."&nbsp;Create Journal Entries</div>
                                                                                                    </td>
                                                                                                </tr>
                                                                                            </table>
                                                                                        </fieldset>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_purchase_order');\" name=\"imgperm_holder_purchase_order\" title=\"Click to view and edit purchase order permissions\" />
                                                            <span onClick=\"shoh('perm_holder_purchase_order');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand purchase order permissions\">Purchase Orders</span>
                                                        </div>
                                                        <div id=\"perm_holder_purchase_order\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"width:50%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=purchase_order[]", "value=L", ($perms->ck('purchase_order', 'L') ? "checked" : NULL))."&nbsp;View Purchase Order List</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=purchase_order[]", "value=V", ($perms->ck('purchase_order', 'V') ? "checked" : NULL))."&nbsp;View Purchase Order Details</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=purchase_order[]", "value=E", ($perms->ck('purchase_order', 'E') ? "checked" : NULL))."&nbsp;Edit Purchase Orders</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=purchase_order[]", "value=P", ($perms->ck('purchase_order', 'P') ? "checked" : NULL))."&nbsp;Edit Print Preferences</div>
                                                                    </td>
                                                                    <td style=\"width:50%;vertical-align:top;padding-top:5px;\">
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=purchase_order[]", "value=U", ($perms->ck('purchase_order', 'U') ? "checked" : NULL))."&nbsp;Update Acknowledgment Info</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=purchase_order[]", "value=A", ($perms->ck('purchase_order', 'A') ? "checked" : NULL))."&nbsp;Create New Purchase Orders</div>
                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=purchase_order[]", "value=D", ($perms->ck('purchase_order', 'D') ? "checked" : NULL))."&nbsp;Delete Purchase Orders</div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_reports');\" name=\"imgperm_holder_reports\" title=\"Click to view and edit reporting permissions\" />
                                                            <span onClick=\"shoh('perm_holder_reports');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand reporting permissions\">Reports</span>
                                                        </div>
                                                        <div id=\"perm_holder_reports\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Customers &amp; Receivables Reports</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|ar", ($perms->ck('reports', 'V', 'ar') ? "checked" : NULL))."&nbsp;Accounts Receivable</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|cash_flow_exp", ($perms->ck('reports', 'V', 'ar_reconcile') ? "checked" : NULL))."&nbsp;Accounts Receivable Reconciliation</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|ar_reconcile", ($perms->ck('reports', 'V', 'cash_receipts') ? "checked" : NULL))."&nbsp;Cash Receipts</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|cash_receipts", ($perms->ck('reports', 'V', 'cash_flow_exp') ? "checked" : NULL))."&nbsp;Cash Flow Expectations</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|customer_balance", ($perms->ck('reports', 'V', 'customer_balance') ? "checked" : NULL))."&nbsp;Customer Balance</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|customer_statement", ($perms->ck('reports', 'V', 'customer_statement') ? "checked" : NULL))."&nbsp;Customer Statement</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Vendors &amp; Payables Reports</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|ap", ($perms->ck('reports', 'V', 'ap') ? "checked" : NULL))."&nbsp;Accounts Payable</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|cash_req", ($perms->ck('reports', 'V', 'cash_req') ? "checked" : NULL))."&nbsp;Cash Requirements</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|cash_disp", ($perms->ck('reports', 'V', 'cash_disp') ? "checked" : NULL))."&nbsp;Cash Disbursements</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|vendor_balance", ($perms->ck('reports', 'V', 'vendor_balance') ? "checked" : NULL))."&nbsp;Vendor Balance Summary</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|sales_tax", ($perms->ck('reports', 'V', 'sales_tax') ? "checked" : NULL))."&nbsp;Sales Tax Liability</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|po", ($perms->ck('reports', 'V', 'po') ? "checked" : NULL))."&nbsp;Purchase Order</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|vendor_discounting", ($perms->ck('reports', 'V', 'vendor_discounting') ? "checked" : NULL))."&nbsp;Vendor Discounting</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|wip_reconcile", ($perms->ck('reports', 'V', 'wip_reconcile') ? "checked" : NULL))."&nbsp;WIP Reconciliation</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|1099", ($perms->ck('reports', 'V', '1099') ? "checked" : NULL))."&nbsp;Vendor 1099</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Proposals &amp; Sales Reports</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|project_status", ($perms->ck('reports', 'V', 'project_status') ? "checked" : NULL))."&nbsp;Project Status Report</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|backlog", ($perms->ck('reports', 'V', 'backlog') ? "checked" : NULL))."&nbsp;Backlog</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|invoiced_sales", ($perms->ck('reports', 'V', 'invoiced_sales') ? "checked" : NULL))."&nbsp;Invoiced Sales Summary</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|bookings", ($perms->ck('reports', 'V', 'bookings') ? "checked" : NULL))."&nbsp;Bookings Summary</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|product_sales", ($perms->ck('reports', 'V', 'product_sales') ? "checked" : NULL))."&nbsp;Product Sales</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|job_costing", ($perms->ck('reports', 'V', 'job_costing') ? "checked" : NULL))."&nbsp;Job Costing</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|work_order", ($perms->ck('reports', 'V', 'work_order') ? "checked" : NULL))."&nbsp;Work Order Bookings</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|commissions", ($perms->ck('reports', 'V', 'commissions') ? "checked" : NULL))."&nbsp;Commissions</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Financial Reports</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|balance_sheet", ($perms->ck('reports', 'V', 'balance_sheet') ? "checked" : NULL))."&nbsp;Balance Sheet</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|income_stmtm", ($perms->ck('reports', 'V', 'income_stmtm') ? "checked" : NULL))."&nbsp;Income Statement</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|trial_balance", ($perms->ck('reports', 'V', 'trial_balance') ? "checked" : NULL))."&nbsp;Trial Balance</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|cash_flow", ($perms->ck('reports', 'V', 'cash_flow') ? "checked" : NULL))."&nbsp;Statement of Cash Flows</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|check_reconcile", ($perms->ck('reports', 'V', 'check_reconcile') ? "checked" : NULL))."&nbsp;Check Reconciliation</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=reports[]", "value=V|check_run", ($perms->ck('reports', 'V', 'check_run') ? "checked" : NULL))."&nbsp;Check Run</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                        <div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
                                                            <img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_config');\" name=\"imgperm_holder_config\" title=\"Click to view and edit system config permissions\" />
                                                            <span onClick=\"shoh('perm_holder_config');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand system config permissions\">System Configuration</span>
                                                        </div>
                                                        <div id=\"perm_holder_config\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
                                                            <table style=\"width:100%;\">
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Users &amp; Groups</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=system_config[]", "value=L|users", ($perms->ck('system_config', 'L', 'users') ? "checked" : NULL))."&nbsp;Show Users &amp; Groups</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=system_config[]", "value=V|users", ($perms->ck('system_config', 'V', 'users') ? "checked" : NULL))."&nbsp;View User/Group Details</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=system_config[]", "value=E|users", ($perms->ck('system_config', 'E', 'users') ? "checked" : NULL))."&nbsp;Edit Users &amp; Groups</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=system_config[]", "value=D|users", ($perms->ck('system_config', 'D', 'users') ? "checked" : NULL))."&nbsp;Delete Users &amp; Groups</div>
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=system_config[]", "value=A|users", ($perms->ck('system_config', 'A', 'users') ? "checked" : NULL))."&nbsp;Add Users &amp; Groups</div>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Company Settings</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=system_config[]", "value=L|company_settings", ($perms->ck('system_config', 'L', 'company_settings') ? "checked" : NULL))."&nbsp;Show Company &amp; System Settings</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>System Maintenance</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=system_config[]", "value=L|system_maintain", ($perms->ck('system_config', 'L', 'system_maintain') ? "checked" : NULL))."&nbsp;Show System Maintenance</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"padding-top:5px;\">
                                                                        <fieldset>
                                                                            <legend>Commission Tables &amp; Rules</legend>
                                                                            <table style=\"width:100%;\">
                                                                                <tr>
                                                                                    <td style=\"padding-top:5px;width:50%\">
                                                                                        <div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=system_config[]", "value=L|commission_tables", ($perms->ck('system_config', 'L', 'commission_tables') ? "checked" : NULL))."&nbsp;Show Commission Tables &amp; Rules</div>
                                                                                    </td>
                                                                                    <td style=\"padding-top:5px;width:50%;vertical-align:top;\">
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </fieldset>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
														<div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
															<img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_vendors');\" name=\"imgperm_holder_vendors\" title=\"Click to view and edit vendor permissions\" />
															<span onClick=\"shoh('perm_holder_vendors');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand vendor permissions\">Vendors</span>
														</div>
														<div id=\"perm_holder_vendors\" style=\"padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
															<table style=\"width:100%;\">
																<tr>
																	<td style=\"width:33%;vertical-align:top;padding-top:5px;\">
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=L", ($perms->ck('vendors', 'L') ? "checked" : NULL))."&nbsp;View Vendor List</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=V", "onClick=permission_check_box(this);", ($perms->ck('vendors', 'V') ? "checked" : NULL))."&nbsp;View Vendor Details</div>
																	</td>
																	<td style=\"width:33%;vertical-align:top;padding-top:5px;\">
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=E", "onClick=permission_check_box(this);", ($perms->ck('vendors', 'E') ? "checked" : NULL))."&nbsp;Edit Vendor Details</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=A", "onClick=permission_check_box(this);", ($perms->ck('vendors', 'A') ? "checked" : NULL))."&nbsp;Create New Vendors</div>
																	</td>
																	<td style=\"width:33%;vertical-align:top;padding-top:5px;\">
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=D", "onClick=permission_check_box(this);", ($perms->ck('vendors', 'D') ? "checked" : NULL))."&nbsp;Delete Vendors</div>
																		<!--<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=A|rcv_pmt", "onClick=permission_check_box(this);", ($perms->ck('vendors', 'A', 'rcv_pmt') ? "checked" : NULL))."&nbsp;Receive Direct Bill Payments</div>-->
																	</td>
																</tr>
																<tr>
																	<td colspan=\"3\" style=\"padding-top:10px;\">
																		<div style=\"padding-top:15px;\">
																			<table style=\"width:100%;\" >
																				<tr>
																					<td style=\"width:50%;\">
																						<fieldset>
																							<legend>General Info</legend>
																							<table style=\"width:100%;\">
																								<tr>
																									<td style=\"padding-top:5px;\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=V|general", ($perms->ck('vendors', 'V', 'general') ? "checked" : NULL))."&nbsp;View General Info</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=E|general", ($perms->ck('vendors', 'E', 'general') ? "checked" : NULL))."&nbsp;Edit General Info</div>
																									</td>
																								</tr>
																							</table>
																						</fieldset>
																					</td>
																					<td style=\"width:50%;\">
																						<fieldset>
																							<legend>Payment Info</legend>
																							<table style=\"width:100%;\">
																								<tr>
																									<td style=\"padding-top:5px;\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=V|payments", ($perms->ck('vendors', 'V', 'payments') ? "checked" : NULL))."&nbsp;View Payment Info</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=E|payments", ($perms->ck('vendors', 'E', 'payments') ? "checked" : NULL))."&nbsp;Edit Payment Info</div>
																									</td>
																								</tr>
																							</table>
																						</fieldset>
																					</td>
																				</tr>
																				<tr>
																					<td style=\"width:50%;\">
																						<fieldset>
																							<legend>Contact Info</legend>
																							<table style=\"width:100%;\">
																								<tr>
																									<td style=\"padding-top:5px;width:50%\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=V|contacts", ($perms->ck('vendors', 'V', 'contacts') ? "checked" : NULL))."&nbsp;View Contacts</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=E|contacts", ($perms->ck('vendors', 'E', 'contacts') ? "checked" : NULL))."&nbsp;Edit Contacts</div>
																									</td>
																									<td style=\"padding-top:5px;width:50%\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=A|contacts", ($perms->ck('vendors', 'A', 'contacts') ? "checked" : NULL))."&nbsp;Create Contacts</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=D|contacts", ($perms->ck('vendors', 'D', 'contacts') ? "checked" : NULL))."&nbsp;Delete Contacts</div>
																									</td>
																								</tr>
																							</table>
																						</fieldset>
																					</td>
																					<td style=\"width:50%;\">
																						<fieldset>
																							<legend>Location Info</legend>
																							<table style=\"width:100%;\">
																								<tr>
																									<td style=\"padding-top:5px;width:50%\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=V|locations", ($perms->ck('vendors', 'V', 'locations') ? "checked" : NULL))."&nbsp;View Locations</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=E|locations", ($perms->ck('vendors', 'E', 'locations') ? "checked" : NULL))."&nbsp;Edit Locations</div>
																									</td>
																									<td style=\"padding-top:5px;width:50%\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=A|locations", ($perms->ck('vendors', 'A', 'locations') ? "checked" : NULL))."&nbsp;Create Locations</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=D|locations", ($perms->ck('vendors', 'D', 'locations') ? "checked" : NULL))."&nbsp;Delete Locations</div>
																									</td>
																								</tr>
																							</table>
																						</fieldset>
																					</td>
																				</tr>
																				<tr>
																					<td style=\"width:50%;\">
																						<fieldset>
																							<legend>Discounting Info</legend>
																							<table style=\"width:100%;\">
																								<tr>
																									<td style=\"padding-top:5px;width:50%\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=V|discounting", ($perms->ck('vendors', 'V', 'discounting') ? "checked" : NULL))."&nbsp;View Discounts</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=E|discounting", ($perms->ck('vendors', 'E', 'discounting') ? "checked" : NULL))."&nbsp;Edit Discounts</div>
																									</td>
																									<td style=\"padding-top:5px;width:50%\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=A|discounting", ($perms->ck('vendors', 'A', 'discounting') ? "checked" : NULL))."&nbsp;Create Discounts</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=D|discounting", ($perms->ck('vendors', 'D', 'discounting') ? "checked" : NULL))."&nbsp;Delete Discounts</div>
																									</td>
																								</tr>
																							</table>
																						</fieldset>
																					</td>
																					<td style=\"width:50%;\">
																						<fieldset>
																							<legend>Products</legend>
																							<table style=\"width:100%;\">
																								<tr>
																									<td style=\"padding-top:5px;width:50%\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=V|products", ($perms->ck('vendors', 'V', 'products') ? "checked" : NULL))."&nbsp;View Products</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=E|products", ($perms->ck('vendors', 'E', 'products') ? "checked" : NULL))."&nbsp;Edit Products</div>
																									</td>
																									<td style=\"padding-top:5px;width:50%\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=A|products", ($perms->ck('vendors', 'A', 'products') ? "checked" : NULL))."&nbsp;Create Products</div>
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=D|products", ($perms->ck('vendors', 'D', 'products') ? "checked" : NULL))."&nbsp;Delete Products</div>
																									</td>
																								</tr>
																							</table>
																						</fieldset>
																					</td>
																				</tr>
																				<tr>
																					<td style=\"width:50%;vertical-align:top;\">
																						<fieldset>
																							<legend>Vendor Stats</legend>
																							<table style=\"width:100%;\">
																								<tr>
																									<td style=\"padding-top:5px;vertical-align:top;\">
																										<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=vendors[]", "value=V|stats", ($perms->ck('vendors', 'V', 'stats') ? "checked" : NULL))."&nbsp;View Vendor Stats</div>
																										<div style=\"padding-bottom:5px;\">&nbsp;</div>
																									</td>
																								</tr>
																							</table>
																						</fieldset>
																					</td>
																					<td style=\"width:50%;\"></td>
																				</tr>
																			</table>
																		</div>
																	</td>
																</tr>
															</table>
														</div>
														<div style=\"padding-bottom:4px;font-size:8pt;padding-top:5px;\" >
															<img src=\"images/expand.gif\" border=\"0\" style=\"cursor:hand;\" onClick=\"shoh('perm_holder_payables');\" name=\"imgperm_holder_payables\" title=\"Click to view and edit vendor payables permissions\" />
															<span onClick=\"shoh('perm_holder_payables');\" style=\"cursor:hand;padding-left:5px;\" title=\"Click to expand vendor payables permissions\">Vendor Payables</span>
														</div>
														<div id=\"perm_holder_payables\" style=\"display:none;padding-bottom:10px;display:none;border:1px solid #8c8c8c;background-color:#efefef;width:100%;\">
															<table style=\"width:100%;\">
																<tr>
																	<td style=\"width:33%;vertical-align:top;padding-top:5px;\">
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=L", ($perms->ck('payables', 'L') ? "checked" : NULL))."&nbsp;View Payables List</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=V", ($perms->ck('payables', 'V') ? "checked" : NULL))."&nbsp;View Payables Details</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=E", ($perms->ck('payables', 'E') ? "checked" : NULL))."&nbsp;Edit Vendor Payables</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=A", ($perms->ck('payables', 'A') ? "checked" : NULL))."&nbsp;Create New Payables</div>
																	</td>
																	<td style=\"width:33%;vertical-align:top;padding-top:5px;\">
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=D", ($perms->ck('payables', 'D') ? "checked" : NULL))."&nbsp;Delete Payables</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=P", ($perms->ck('payables', 'P') ? "checked" : NULL))."&nbsp;Print Checks</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=F", ($perms->ck('payables', 'F') ? "checked" : NULL))."&nbsp;Flag Payables for Payment</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=PB", ($perms->ck('payables', 'PB') ? "checked" : NULL))."&nbsp;Pay Bills</div>
																	</td>
																	<td style=\"width:33%;vertical-align:top;padding-top:5px;\">
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=V|memo_costing", ($perms->ck('payables', 'V', 'memo_costing') ? "checked" : NULL))."&nbsp;View Memo Costs</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=A|memo_costing", ($perms->ck('payables', 'A', 'memo_costing') ? "checked" : NULL))."&nbsp;Add Memo Costs</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=V|commissions_paid", ($perms->ck('payables', 'V', 'commissions_paid') ? "checked" : NULL))."&nbsp;View Commissions Paid</div>
																		<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=payables[]", "value=E|po_diff", ($perms->ck('payables', 'E', 'po_diff') ? "checked" : NULL))."&nbsp;Override PO/Payable Amt</div>
																	</td>
																</tr>
															</table>
														</div>
													</div>
												</td>
											</tr>
										</table>
										<div style=\"float:right;padding-right:45px;padding-top:15px;\" >" .
											$this->form->button(
												"value=Save Permissions",
												"onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_save_permissions');"
											) . "
										</div>
									</td>
								</tr>
							</table>
						</div>
					</fieldset>
				</td>
			</tr>
		</table>";

		if ($local)
			return $inner_tbl;

		return $tbl;
	}

	function edit_group() {

		if ($this->ajax_vars['group_hash']) {
			$valid = $this->fetch_group_record($this->ajax_vars['group_hash']);

			if ($valid === false)
				unset($this->ajax_vars['group_hash']);
		}

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Group : ".$this->current_group['group_name'] : "Create A New Group");

		$this->fetch_users();
		for ($i = 0; $i < count($this->user_info); $i++) {
			$user_name[] = stripslashes($this->user_info[$i]['full_name']);
			$user_hash[] = $this->user_info[$i]['user_hash'];
		}

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("group_hash" => $this->group_hash, "popup_id" => $this->popup_id))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<div style=\"padding:15px 0 0 15px;font-weight:bold;\"></div>
			<ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">
				<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\">Group Info</a></li>
				<li ><a href=\"javascript:void(0);\" rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\">Permissions</a></li>
			</ul>
			<div id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\">
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err1_1{$this->popup_id}\">Group Name: *</td>
						<td style=\"background-color:#ffffff;padding-top:10px;\">
							".$this->form->text_box("name=group_name", "value=".$this->current_group['group_name'], "size=25", "maxlength=128")."
							<div style=\"padding-top:5px;\">
							".$this->form->checkbox("name=lock", "value=1", ($this->current_group['group_lock'] ? "checked" : NULL), "title=Locking a group prevents members of that group from logging in")."&nbsp;Place a lock on this group?
							</div>
							<div style=\"padding-top:5px;\">
							".$this->form->checkbox("name=submit_to", "value=1", ($this->current_group['submit_to'] ? "checked" : NULL), "title=Enabling this feature allows proposals, invoices, etc, to be moved electronically from group to group")."&nbsp;Allow 'submit to' functionality?
							</div>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\" id=\"err1_2{$this->popup_id}\">Group Members: </td>
						<td style=\"background-color:#ffffff;padding-top:10px;\">
							".$this->form->select("group_members[]", $user_name, $this->current_group['group_members'], $user_hash, "multiple", "size=5", "style=width:250px;", "blank=1")."
						</td>
					</tr>
				</table>
			</div>
			<div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">".$this->permissions_form()."</div>
			<div style=\"padding:10px;text-align:right;padding-right:45px;\">".(!$valid || ($valid && $this->p->ck(get_class($this), 'E', 'users')) ?
				$this->form->button("value=Save Group", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_group');") : NULL)."
				&nbsp;&nbsp;".($this->group_hash && !$this->current_group['group_members'] && $this->p->ck(get_class($this), 'D', 'users') ?
				$this->form->button("value=Delete Group", "onClick=if(confirm('Are you sure you want to delete this group? This action CANNOT be undone!')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_group', 'method=rm');}") : NULL)."
			</div>
		</div>".
		$this->form->close_form();

		$this->content["jscript"][] = "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')', 10);";
		$this->content['popup_controls']["cmdTable"] = $tbl;

		return;
	}

	function users() {

		$tbl = "
		<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
			<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
				<p id=\"feedback_message\"></p>
		</div>
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\" >
			<tr>
				<td style=\"padding:0;font-weight:bold;\">
					<div style=\"background-color:#ffffff;height:100%;\">
						<div style=\"margin:20px;width:800px;padding:0;\">
							<fieldset>
								<legend>Add &amp; Edit Users <span id=\"sys_users_count\"></span></legend>
								<div style=\"padding-top:5px;margin-left:25px;font-weight:normal;\">" .
                        		( $this->p->ck(get_class($this), 'A', 'users') ?
									"[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_user', 'popup_id=main_popup');\" class=\"link_standard\">new user</a></small>]" : NULL
                        		) . "
								</div>
								<div id=\"loader_users\" style=\"padding:20px;display:block;\"><img src=\"images/ajax-loader-2.gif\" /></div>
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0;\">
									<tr>
										<td style=\"padding:10px 25px;\" id=\"sys_users\"></td>
									</tr>
								</table>
							</fieldset>
							<fieldset style=\"margin-top:10px;\">
								<legend>Add &amp; Edit Groups <span id=\"sys_groups_count\"></span></legend>
								<div style=\"padding-top:5px;margin-left:25px;font-weight:normal;\">" .
                                ( $this->p->ck(get_class($this), 'A', 'users') ?
									"[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_group', 'popup_id=main_popup');\" class=\"link_standard\">new group</a></small>]" : NULL
                                ) . "
								</div>
								<div id=\"loader_groups\" style=\"padding:20px;display:block;\"><img src=\"images/ajax-loader-2.gif\" /></div>
								<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">
									<tr>
										<td style=\"padding:10px 25px;\" id=\"sys_groups\"></td>
									</tr>
								</table>
							</fieldset>
						</div>
					</div>
				</td>
			</tr>
		</table>";

		if ( $this->ajax_vars['otf'] )
			$this->content['html']['tcontent1'] = $tbl;

		$this->content['jscript'][] = "window.setTimeout('agent.call(\'system_config\', \'sf_loadcontent\', \'cf_loadcontent\', \'user_list\')', 15);";
		$this->content['jscript'][] = "window.setTimeout('agent.call(\'system_config\', \'sf_loadcontent\', \'cf_loadcontent\', \'group_list\')', 25);";

		return $tbl;
	}

	function doit_update_system() {
		if ($this->popup_id == 'logo_win') {
			$from = $_POST['from'];
		    $file_name = $_POST['file_name'];
			$file_type = $_POST['file_type'];
			$file_size = $_POST['file_size'];
			$file_error = $_POST['file_error'];
			if ($from == 'logos') {
				if ($file_error || $file_type != "image/pjpeg") {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = ($file_error ? "There was an error while uploading your logo. Please try again." : "The image you uploaded is not a JPEG (.jpg) file. Please make sure you are uploading the correct file type.");
					$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?class=system_config&action=doit_update_system&current_hash=".$this->current_hash."&from=$from&popup_id={$this->popup_id}', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
					return;
				}
				if (copy($file_name, SITE_ROOT.'core/images/logos/'.basename($file_name))) {
					if ($logos = fetch_sys_var('company_logo')) {
						$logos = explode("|", $logos);

						@array_push($logos, LINK_ROOT.'/core/images/logos/'.basename($file_name));
					} else
						$logos = LINK_ROOT.'/core/images/logos/'.basename($file_name);

					update_sys_data('company_logo', (is_array($logos) ? implode("|", $logos) : $logos));
					$this->content['page_feedback'] = "Logo has been uploaded.";
					$this->content['action'] = "close";
					$logos = explode("|", fetch_sys_var('company_logo'));
					// Hide logos that are grayscale
					for ($i = 0; $i < count($logos); $i++) {
						if (!ereg("_gray", basename($logos[$i]))) {
							$tbl .=
							"<div style=\"padding:2px 3px 5px 3px\" title=\"".$logos[$i]."\">
								<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this logo?')){agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'manage_logos', 'rm=1', 'from=logos', 'id=".$i."');}\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></a>
								<a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_logos', 'from=logos', 'id=".$i."')\" class=\"link_standard\" title=\"View\">".(strlen(basename($logos[$i])) > 25 ? substr(basename($logos[$i]), 0, 23)."..." : basename($logos[$i]))."</a>
							</div>";
						}
					}
					$this->content['html']['logo_holder'] = $tbl;
					return;
				} else {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = "There was an error while uploading your logo. Please check to ensure the proper file permissions have been set for ".SITE_ROOT." and try again.";
					$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?class=system_config&action=doit_update_system&current_hash=".$this->current_hash."&from=$from&popup_id={$this->popup_id}', 'frameBorder=0', 'height=30px');toggle_display('iframe', 'block');";
					return;
				}
			} elseif ($from == 'docs') {
				if ($_POST['update_doc'] == 1) {
					$id = $_POST['id'];

					for ($i = 0; $i < count($_POST['auto_append']); $i++) {
						if ($_POST['auto_append'][$i])
							$doc_append[] = $_POST['auto_append'][$i];
					}

					$auto_append = fetch_sys_var('company_docs_auto_append');
					$auto_append = explode("|", $auto_append);
					$auto_append[$id] = @implode(":", $doc_append);

					update_sys_data('company_docs_auto_append', @implode("|", $auto_append));
					$this->content['page_feedback'] = "Document has been saved.";
					$this->content['action'] = "close";
					return;
				} else {
					if ($file_size == 0 || $file_error || ($file_type != "application/pdf" && $file_type != "application/msword")) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = ($file_error ? "There was an error while uploading your logo. Please try again." : "The image you uploaded is not a PDF or DOC file. Please make sure you are uploading the correct file type.");
						$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?class=system_config&action=doit_update_system&current_hash=".$this->current_hash."&from=$from&popup_id={$this->popup_id}&onchange=1', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
						return;
					}
					if (copy($file_name, SITE_ROOT.'core/images/docs/'.basename($file_name))) {
						for ($i = 0; $i < count($_POST['auto_append']); $i++) {
							if ($_POST['auto_append'][$i])
								$doc_append[] = $_POST['auto_append'][$i];
						}
						if ($docs = fetch_sys_var('company_docs')) {
							$docs = explode("|", $docs);
							@array_push($docs, LINK_ROOT.'/core/images/docs/'.basename($file_name));

							$auto_append = fetch_sys_var('company_docs_auto_append');
							$auto_append = explode("|", $auto_append);


							array_push($auto_append, @implode(":", $doc_append));
						} else {
							$docs = LINK_ROOT.'/core/images/docs/'.basename($file_name);
							$auto_append = @implode(":", $doc_append);
						}

						update_sys_data('company_docs', (is_array($docs) ? implode("|", $docs) : $docs));
						update_sys_data('company_docs_auto_append', (is_array($docs) ? implode("|", $auto_append) : $auto_append));

						$this->content['page_feedback'] = "Document has been uploaded.";
						$this->content['action'] = "close";
						$docs = explode("|", fetch_sys_var('company_docs'));
						for ($i = 0; $i < count($docs); $i++)
							$tbl .=
							"<div style=\"padding:2px 3px 5px 3px\" title=\"".$docs[$i]."\">
								<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this document?')){agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'manage_logos', 'rm=1', 'from=docs', 'id=".$i."');}\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></a>
								<a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_logos', 'from=docs', 'id=".$i."')\" class=\"link_standard\" title=\"View\">".basename($docs[$i])."</a>
							</div>";

						$this->content['html']['doc_holder'] = $tbl;
						return;
					} else {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = "There was an error while uploading your document. Please check to ensure the proper file permissions have been set for ".SITE_ROOT." and try again.";
						$this->content['jscript'] = "remove_element('progress_holder', 'progress_div');remove_element('iframe');create_element('iframe', 'iframe', 'src=upload.php?class=system_config&action=doit_update_system&current_hash=".$this->current_hash."&from=$from&popup_id={$this->popup_id}', 'frameBorder=0', 'height=50px');toggle_display('iframe', 'block');";
						return;
					}
				}
			}
		} else {
			$btn = $_POST['btn'];

			if ($btn == 'system') {
				$system_vars = $_POST['system_var'];

				$var['MAIN_PAGNATION_NUM'] = $system_vars['MAIN_PAGNATION_NUM'];
				$var['SUB_PAGNATION_NUM'] = $system_vars['SUB_PAGNATION_NUM'];
				$var['DATE_FORMAT'] = $system_vars['DATE_FORMAT'];
				$var['USER_TIMEOUT'] = $system_vars['USER_TIMEOUT'];
				$var['TIMESTAMP_FORMAT'] = $system_vars['TIMESTAMP_FORMAT'];
				$var['SPEC_ITEM_EDIT'] = $system_vars['SPEC_ITEM_EDIT'];
				$var['PO_DATE_ALERT'] = $system_vars['PO_DATE_ALERT'];
                $var['CUSTOMER_CREDIT_HOLD'] = $system_vars['CUSTOMER_CREDIT_HOLD'];
                $var['ACTIVATE_ITEM_LIBRARY'] = $system_vars['ACTIVATE_ITEM_LIBRARY'];
                $var['ACTIVATE_SALES_REP_ID'] = $system_vars['ACTIVATE_SALES_REP_ID'];
                $var['ACTIVATE_AR_JOURNAL'] = $system_vars['ACTIVATE_AR_JOURNAL'];
                $var['ACTIVATE_AP_JOURNAL'] = $system_vars['ACTIVATE_AP_JOURNAL'];
                $var['ACTIVATE_AP_DEPOSITS'] = $system_vars['ACTIVATE_AP_DEPOSITS'];
				$var['BILL_PAY_QUEUE'] = $system_vars['BILL_PAY_QUEUE'];
				$var['CUSTOMER_REFUND_PAY_DAYS'] = $system_vars['CUSTOMER_REFUND_PAY_DAYS'];
				$var['DEFAULT_TIMEZONE'] = $system_vars['DEFAULT_TIMEZONE'];
				$var['PROPOSAL_ARCHIVE_DAYS'] = $system_vars['PROPOSAL_ARCHIVE_DAYS'];
				$var['DEFAULT_PROPOSAL_VALID_DAYS'] = $system_vars['DEFAULT_PROPOSAL_VALID_DAYS'];
				$var['CUSTOMER_LOGIN'] = $system_vars['CUSTOMER_LOGIN'];
				$var['INVOICE_BACKDATE'] = $system_vars['INVOICE_BACKDATE'];
				$var['PO_INVOICE_EDIT'] = $system_vars['PO_INVOICE_EDIT'];
				$var['MAIL_QUEUE'] = $system_vars['MAIL_QUEUE'];
				$var['FORCE_SSL_REDIRECT'] = $system_vars['FORCE_SSL_REDIRECT'];
				$var['AP_LINE_RECEIVE'] = $system_vars['AP_LINE_RECEIVE'];
				$var['AUTO_UPDATE'] = $system_vars['AUTO_UPDATE'];
				$var['USER_LOGIN'] = $system_vars['USER_LOGIN'];
			 	$var['FRF_PRODUCT_HASH'] = $system_vars['FRF_PRODUCT_HASH'];
			 	$var['CBD_PRODUCT_HASH'] = $system_vars['CBD_PRODUCT_HASH'];
			 	$var['SOF_PRODUCT_HASH'] = $system_vars['SOF_PRODUCT_HASH'];
			 	$var['FSF_PRODUCT_HASH'] = $system_vars['FSF_PRODUCT_HASH'];
			 	$var['PAYABLE_POSTING_DATE'] = $system_vars['PAYABLE_POSTING_DATE'];
			 	/*
			 	$var['SMTP_GATEWAY'] = $system_vars['SMTP_GATEWAY'];
			 	if ($var['SMTP_GATEWAY'] == 1) {
			 	    $var['SMTP_HOST'] = $_POST['smtp_host'];
			 	    $var['SMTP_USERNAME'] = $_POST['smtp_username'];
			 	    $var['SMTP_PASSWORD'] = $_POST['smtp_password'];
			 	    if (!$var['SMTP_HOST'] || !$var['SMTP_USERNAME'] || !$var['SMTP_PASSWORD'])
                        return $this->__trigger_error("In order to use your own SMTP gateway, please make sure you have included a valid hostname, username and password", E_USER_NOTICE, __FILE__, __LINE__, 1);

			 	}
                */
				while (list($var_name, $var_val) = each($var))
					$this->db->query("UPDATE `system_vars`
									  SET `var_val` = '$var_val'
									  WHERE `var_name` = '$var_name'");

				$this->db->query("UPDATE `session`
								  SET `reload_session` = 1
								  WHERE `obj_id` > 0");

				$this->content['page_feedback'] = "Your system settings have been updated and are now active.";
				$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'company_settings');";
				$this->content['action'] = 'continue';
				return;
			} elseif ($btn == 'company') {
				$system_vars = $_POST['system_var'];

				$var['PROPOSAL_SEED'] = $system_vars['PROPOSAL_SEED'];
				$var['WORK_ORDER_SEED'] = $system_vars['WORK_ORDER_SEED'];
				$var['NEXT_WORK_ORDER_NUM'] = $system_vars['NEXT_WORK_ORDER_NUM'];
				$var['NEXT_PROPOSAL_NUM'] = $system_vars['NEXT_PROPOSAL_NUM'];
				$var['PO_SEED'] = $system_vars['PO_SEED'];
				$var['NEXT_PO_NUM'] = $system_vars['NEXT_PO_NUM'];
				$var['INVOICE_SEED'] = $system_vars['INVOICE_SEED'];
				$var['NEXT_INVOICE_NO'] = $system_vars['NEXT_INVOICE_NO'];
				$var['DEFAULT_CUST_PAY_TERMS'] = $system_vars['DEFAULT_CUST_PAY_TERMS'];
				$var['NEXT_CHECK_NO'] = $system_vars['NEXT_CHECK_NO'];
				$var['MARGIN_FLAG'] = $system_vars['MARGIN_FLAG'] * .01;
				$var['MARGIN_FLAG_AUTH'] = $system_vars['MARGIN_FLAG_AUTH'];
				$var['OVERHEAD_TYPE'] = $system_vars['OVERHEAD_TYPE'];
				$var['OVERHEAD_RATE'] = $system_vars['OVERHEAD_RATE'] * .01;
				if ($var['OVERHEAD_RATE'] > 1 || $var['OVERHEAD_RATE'] < 0) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = "The overhead rate you entered is invalid, please enter a percentage in the following format: 1.5% (not .015).";
					return;
				}
				$var['DEFAULT_CUST_DEPOSIT'] = $system_vars['DEFAULT_CUST_DEPOSIT'] * .01;
				$var['ACCOUNT_BALANCE_AMT'] = $system_vars['ACCOUNT_BALANCE_AMT'];
				$var['ACCOUNT_BALANCE_RULE'] = $system_vars['ACCOUNT_BALANCE_RULE'];
				$var['ACCOUNT_BALANCE_SCHED'] = $system_vars['ACCOUNT_BALANCE_SCHED'];

				$internal_vendor = $_POST['internal_vendor'];
				$internal_vendor_hash = $_POST['internal_vendor_hash'];
				if (!$internal_vendor_hash || !$internal_vendor) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = (!$internal_vendor ? "You must enter a vendor to be used as the default vendor for your internal resources. This vendor can be changed within the user's resource tab." : "We can't seem to find the vendor you selected. Please make sure you are selecting a valid vendor.");
					$this->content['form_return']['err']['err_internal_vendor'] = 1;
					return;
				}
				$var['INTERNAL_VENDOR'] = $internal_vendor_hash;
				if ($var['ACCOUNT_BALANCE_AMT']) {
					if (($var['ACCOUNT_BALANCE_RULE'] == 'P' && $var['ACCOUNT_BALANCE_AMT'] > 100) || $var['ACCOUNT_BALANCE_AMT'] < 1 || strspn($var['ACCOUNT_BALANCE_AMT'], "0123456789.") != strlen($var['ACCOUNT_BALANCE_AMT'])) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = "Please confirm that you entered valid rules for customer A/R enforcement. Percentages must be entered as whole numbers (i.e. 15 for 15% not .15).";
						return;
					}
				}
				$var['MULTI_CURRENCY'] = $system_vars['MULTI_CURRENCY'];
				$var['CURRENCY_GAIN_LOSS_ACCT'] = $system_vars['CURRENCY_GAIN_LOSS_ACCT'];
				$var['FINANCE_CHARGE_ACCOUNT'] = $system_vars['FINANCE_CHARGE_ACCOUNT'];
				$var['DEPOSIT_THRESHOLD'] = $system_vars['DEPOSIT_THRESHOLD'] * .01;
				$var['proposal_footer_message'] = $system_vars['proposal_footer_message'];
				$var['invoice_footer_message'] = $system_vars['invoice_footer_message'];
				$var['MY_COMPANY_NAME'] = $system_vars['MY_COMPANY_NAME'];
				$var['MY_COMPANY_ADDRESS'] = $system_vars['MY_COMPANY_ADDRESS'];
				$var['MY_COMPANY_ZIP'] = $system_vars['MY_COMPANY_ZIP'];
				$var['MY_COMPANY_COUNTRY'] = $system_vars['MY_COMPANY_COUNTRY'];
				$var['MY_COMPANY_PHONE'] = $system_vars['MY_COMPANY_PHONE'];
				$var['MY_COMPANY_FAX'] = $system_vars['MY_COMPANY_FAX'];
				$var['MY_COMPANY_URL'] = $system_vars['MY_COMPANY_URL'];
				$var['TAX_ID_NUMBER'] = $system_vars['TAX_ID_NUMBER'];
				$OVERRIDE_GROUPS = $system_vars['OVERRIDE_GROUPS'];
				for ($i = 0; $i < count($OVERRIDE_GROUPS); $i++)
					$var['OVERRIDE_GROUPS'][] = $OVERRIDE_GROUPS[$i];

				$var['OVERRIDE_GROUPS'] = @implode("|", $var['OVERRIDE_GROUPS']);

				if (strspn($var['NEXT_PROPOSAL_NUM'], "0123456789") != strlen($var['NEXT_PROPOSAL_NUM']) || strspn($var['NEXT_PO_NUM'], "0123456789") != strlen($var['NEXT_PO_NUM']) || strspn($var['NEXT_INVOICE_NO'], "0123456789") != strlen($var['NEXT_INVOICE_NO']) || strspn($var['NEXT_CHECK_NO'], "0123456789") != strlen($var['NEXT_CHECK_NO'])) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = "The following fields can contain only numbers, and one of them contains invalid charactors. Please check your information:<br /><br />Next proposal number<br />Next PO Number<br />Next Invoice Number<br />Next Check Number";
					return;
				}

			 	while (list($var_name, $var_val) = each($var))
					$this->db->query("UPDATE `system_vars`
									  SET `var_val` = '$var_val'
									  WHERE `var_name` = '$var_name'");

				$this->db->query("UPDATE `session`
								  SET `reload_session` = 1
								  WHERE `obj_id` > 0");

				$this->content['page_feedback'] = "Your system settings have been updated. ";
				$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'company_settings');";
				$this->content['action'] = 'continue';
				return;
			}
        }
	}

	function manage_logos() {
		$rm = $this->ajax_vars['rm'];
		$from = $this->ajax_vars['from'];

		if ($rm == 1) {
			$id = $this->ajax_vars['id'];
			if ($from == 'logos') {
				$logos = fetch_sys_var('company_logo');

				$logos = explode("|", $logos);
				@unlink(str_replace(LINK_ROOT, SITE_ROOT, $logos[$id]));
				unset($logos[$id]);
				$logos = array_values($logos);
				update_sys_data('company_logo', @implode("|", $logos));

				$logos = explode("|", fetch_sys_var('company_logo'));
				if (count($logos) && $logos[0]) {
					for ($i = 0; $i < count($logos); $i++)
						$tbl .=
						"<div style=\"padding:2px 3px 5px 3px\" title=\"".$logos[$i]."\">
							<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this logo?')){agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'manage_logos', 'from=logos', 'rm=1', 'id=".$i."');}\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></a>
							<a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_logos', 'from=logos', 'id=".$i."')\" class=\"link_standard\" title=\"View\">".(strlen(basename($logos[$i])) > 17 ? substr(basename($logos[$i]), 0, 17)."..." : basename($logos[$i]))."</a>
						</div>";
				} else
					$tbl .= "<div style=\"padding:2px 3px 5px 3px;font-style:italic;\">None</div>";
				$this->content['html']['logo_holder'] = $tbl;
			} else {
				$docs = explode("|", fetch_sys_var('company_docs'));
				$auto_append = explode("|", fetch_sys_var('company_docs_auto_append'));

				unset($docs[$id], $auto_append[$id]);

				$docs = array_values($docs);
				$auto_append = array_values($auto_append);

				update_sys_data('company_docs', @implode("|", $docs));
				update_sys_data('company_docs_auto_append', @implode("|", $auto_append));

				$docs = explode("|", fetch_sys_var('company_docs'));
				if (count($docs) && $docs[0]) {
					for ($i = 0; $i < count($docs); $i++)
						$tbl .=
						"<div style=\"padding:2px 3px 5px 3px\" title=\"".$docs[$i]."\">
							<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this document?')){agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'manage_logos', 'from=docs', 'rm=1', 'id=".$i."');}\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></a>
							<a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_logos', 'from=docs', 'id=".$i."')\" class=\"link_standard\" title=\"View\">".basename($docs[$i])."</a>
						</div>";
				} else
					$tbl .= "<div style=\"padding:2px 3px 5px 3px;font-style:italic;\">None</div>";
				$this->content['html']['doc_holder'] = $tbl;
			}
			return;
		} else {
			if ($this->ajax_vars['popup_id'])
				$this->popup_id = $this->ajax_vars['popup_id'];

			$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
			$this->content['popup_controls']['popup_title'] = "Company Logos & Documents";

			$tbl = "
			<div class=\"panel\" id=\"main_table{$this->popup_id}\">
				<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
					<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
						<p id=\"feedback_message{$this->popup_id}\"></p>
				</div>
				<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
					<tr>
						<td style=\"padding:0;\">
							 <div style=\"background-color:#ffffff;height:100%;padding-bottom:15px;\">
								<div style=\"margin:15px 35px;\">";
								if (is_numeric($this->ajax_vars['id'])) {
									if ($from == 'logos')
										$logo = explode("|", fetch_sys_var('company_logo'));
									else
										$doc = explode("|", fetch_sys_var('company_docs'));

									$tbl .= ($from == 'logos' ? "
									<div style=\"text-align:center;width:100%;\">
										<img src=\"".$logo[$this->ajax_vars['id']]."\" />
									</div>" : "
									<img src=\"images/file_save.gif\" border=\"0\" title=\"Click the link to download.\" />
									&nbsp;&nbsp;
									<a href=\"".$doc[$this->ajax_vars['id']]."\" title=\"Click to download and view the file.\" class=\"link_standard\" target=\"_blank\">".$doc[$this->ajax_vars['id']]."</a>");
									if ($from == 'docs') {
										$auto_append = fetch_sys_var('company_docs_auto_append');
										$auto_append = explode("|", $auto_append);
										$cur_auto_append = $auto_append[$this->ajax_vars['id']];
										$cur_auto_append_docs = explode(":", $cur_auto_append);

										$tbl .= $this->form->form_tag().$this->form->hidden(array("popup_id" => $this->popup_id))."
										<div style=\"margin-bottom:15px;margin-top:10px;margin-left:15px;\">
											Automatically append this document to outgoing:
											<div style=\"padding:10px;\">
												".$this->form->checkbox("name=auto_append[]", "value=proposals", (in_array('proposals', $cur_auto_append_docs) ? "checked" : NULL))."&nbsp;Proposals
												<br />
												".$this->form->checkbox("name=auto_append[]", "value=purchase_orders", (in_array('purchase_orders', $cur_auto_append_docs) ? "checked" : NULL))."&nbsp;Purchase Orders
												<br />
												".$this->form->checkbox("name=auto_append[]", "value=customer_invoices", (in_array('customer_invoices', $cur_auto_append_docs) ? "checked" : NULL))."&nbsp;Customer Invoices
												<br />
												".$this->form->checkbox("name=auto_append[]", "value=delivery_tickets", (in_array('delivery_tickets', $cur_auto_append_docs) ? "checked" : NULL))."&nbsp;Delivery Tickets
											</div>
											".$this->form->button("value=Save", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_update_system', 'from=docs', 'id=".$this->ajax_vars['id']."', 'update_doc=1')")."
										</div>".$this->form->close_form();
									}
								} else {
									$tbl .= $this->form->form_tag().$this->form->hidden(array('proposal_hash' => '', 'popup_id' => $this->popup_id))."
									".($from == 'logos' ?
										"Upload a new company logo. Logos must be in JPEG (.jpg) format." : "
										Upload a new company document. Documents must be in either PDF or DOC format.")."
									<div id=\"iframe\" style=\"margin-bottom:25px;padding-top:5px;\"><iframe src=\"upload.php?class=system_config&action=doit_update_system&current_hash=".$this->current_hash."&from=$from&popup_id=".$this->popup_id.($from == 'docs' ? "&onchange=1" : NULL)."\" frameborder=\"0\" style=\"height:".($from == 'docs' ? 4 : 2)."0px;\"></iframe></div>
									<div id=\"progress_holder\"></div>".
										$this->form->hidden(array("file_name" 		=>		'',
																  "file_type" 		=>		'',
																  "file_size" 		=>		'',
																  "file_error" 		=>		''
																  )
															);
									if ($from == 'docs')
										$tbl .= "
										<div style=\"margin-bottom:15px;\">
											Automatically append this document to outgoing:
											<div style=\"padding:10px;\">
												".$this->form->checkbox("name=auto_append[]", "value=proposals")."&nbsp;Proposals
												<br />
												".$this->form->checkbox("name=auto_append[]", "value=purchase_orders")."&nbsp;Purchase Orders
												<br />
												".$this->form->checkbox("name=auto_append[]", "value=customer_invoices")."&nbsp;Customer Invoices
												<br />
												".$this->form->checkbox("name=auto_append[]", "value=delivery_tickets")."&nbsp;Delivery Tickets
											</div>
										</div>";
								}
								$tbl .= "
								</div>
							</div>
						</td>
					</tr>
				</table>
			</div>";

			$this->content['popup_controls']["cmdTable"] = $tbl;
			return;
		}
	}

	/**
	 * Doit function for handling remit to addresses.
	 *
	 */
	function doit_remit() {

		$id = $_POST['id'];
		$from = $_POST['from'];
		$rm = ( $this->ajax_vars['rm'] ? $this->ajax_vars['rm'] : $_POST['rm'] );

		$remit_str = fetch_sys_var('REMIT_TO_ADDRESSES');
		if ($remit_str)
			$remit_arr = __unserialize( stripslashes($remit_str) );
		else
			$remit_arr = array();

		if ( $rm && is_numeric($id) ) {

			unset($remit_arr[$id]);
			$remit_arr = array_values($remit_arr);
			update_sys_data('REMIT_TO_ADDRESSES', addslashes( serialize($remit_arr) ));

			$this->content['action'] = 'close';
			$this->content['page_feedback'] = "Remittance address has been removed.";
			$this->content['html']['remit_to_holder'] = $this->manage_remit_addresses(1);

			return;
		}

		if ( ! $_POST['name'] || ! $_POST['street'] || ! $_POST['city'] || ! $_POST['state'] || ! $_POST['zip'] || ! $_POST['country'] ) {

			if ( ! $_POST['name'] ) $this->set_error("err1{$this->popup_id}");
			if ( ! $_POST['street'] ) $this->set_error("err2{$this->popup_id}");
			if ( ! $_POST['city'] ) $this->set_error("err4{$this->popup_id}");
			if ( ! $_POST['state'] ) $this->set_error("err5{$this->popup_id}");
			if ( ! $_POST['zip'] ) $this->set_error("err6{$this->popup_id}");
			if ( ! $_POST['country'] ) $this->set_error("err7{$this->popup_id}");

			return $this->__trigger_error("You left some required fields blank! Please check the indicated fields below and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);
		}

		# General info
		$remit['name'] = htmlspecialchars($_POST['name'], ENT_QUOTES);
		$remit['street'] = htmlspecialchars($_POST['street'], ENT_QUOTES);
		$remit['city'] = htmlspecialchars($_POST['city'], ENT_QUOTES);
		$remit['state'] = $_POST['state'];
		$remit['zip'] = $_POST['zip'];
		$remit['country'] = $_POST['country'];
		$remit['notes'] = htmlspecialchars($_POST['notes'], ENT_QUOTES);
		if ( is_numeric($id) )
			$remit_arr[$id] = $remit;
		else
			$remit_arr[] = $remit;

		update_sys_data('REMIT_TO_ADDRESSES', addslashes( serialize($remit_arr) ));
		$this->content['action'] = 'close';
		$this->content['page_feedback'] = "Remittance address has been " . ( is_numeric($id) ? "updated" : "added" );

		if ( $from == 'customer_invoice' ) {

			$invoice_total = $_POST['z_total'];
			$selected_remit = $_POST['selected_remit'];
			$invoice_popup = $_POST['parent_popup_id'];
            if ( $selected_remit + 1 > count($remit_arr) )
                $selected_remit = 0;

	        $remit_to_dd = create_drop_down_arrays($remit_arr, "name");

	        for ( $z = 0; $z < $invoice_total; $z++ ) {

	            for ( $i = 0; $i < count($remit_arr); $i++ ) {

	                $remit_to_addr[$i] = "{$remit_arr[$i]['name']}\n{$remit_arr[$i]['street']}\n{$remit_arr[$i]['city']}, {$remit_arr[$i]['state']} {$remit_arr[$i]['zip']}" .
	                ( $remit_arr[$i]['notes'] ?
    	                "\n{$remit_arr[$i]['notes']}" : NULL
	                );
	                $remit_to_tbl .= "
	                <div id=\"remit_to_address_holder{$i}_{$z}\" style=\"" . ( $i == $selected_val ? "display:block" : "display:none" ) . ";\">" .
    	                $this->form->text_area(
        	                "name=remit_to_address[$z][$i]",
        	                "id=remit_to_address_{$z}",
        	                "rows=4",
        	                "cols=25",
        	                "value={$remit_to_addr[$i]}"
    	                ) . "
    	            </div>";

	                $remit_to_toggle .= "toggle_display('remit_to_address_holder{$i}_{$z}', 'none');";
	            }

		        $this->content['html']["remit_to_holder_{$invoice_popup}_{$z}"] = $this->form->select(
                    "remit_to[$z]",
                    $remit_to_dd[0],
                    ( $selected_remit ? $selected_remit : 0 ),
                    $remit_to_dd[1],
                    "blank=1",
                    "onChange=if( \$('remit_to_address_holder' + this.options[this.selectedIndex].value+ '_{$z}')){{$remit_to_toggle}toggle_display('remit_to_address_holder' + this.options[this.selectedIndex].value + '_{$z}', 'block');}",
                    "style=width:175px;"
                );

                $this->content['html']["remit_address_holder_{$invoice_popup}_{$z}"] = $remit_to_tbl;
                $this->content['jscript'][] = "\$('remit_address_holder_{$invoice_popup}_{$z}').show();";
	        }

    	} else
    		$this->content['html']['remit_to_holder'] = $this->manage_remit_addresses(1);
	}
	/**
	 * Manage popup window that adds, edits, or removes remit to addresses.
	 *
	 */
	function manage_remit_addresses() {

		global $states, $stateNames, $country_codes, $country_names;

		if ( $local = func_get_arg(0) ) {

			$remit_str = fetch_sys_var('REMIT_TO_ADDRESSES');
			if ( $remit_str )
				$remit_arr = __unserialize( stripslashes($remit_str) );
			else
				$remit_arr = array();

			if ( count($remit_arr) && $remit_arr[0] ) {

				for ( $i = 0; $i < count($remit_arr); $i++ ) {

					$remit_to_title = stripslashes($remit_arr[$i]['street']) . "\n" . stripslashes($remit_arr[$i]['city']) . ", {$remit_arr[$i]['state']} {$remit_arr[$i]['zip']}";

					$tbl .=
					"<div style=\"padding:2px 3px 5px 3px\" title=\"$remit_to_title\" nowrap>
						<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this remit to address?')){agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'doit_remit', 'rm=1', 'id=$i');}\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></a>
						<a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_remit_addresses', 'id=$i', 'popup_id=remit_win')\" class=\"link_standard\" title=\"View\">".(strlen($remit_arr[$i]['name']) > 17 ? stripslashes(substr($remit_arr[$i]['name'], 0, 17))."..." : stripslashes($remit_arr[$i]['name']))."</a>
					</div>";
				}
			} else
				$tbl .= "<div style=\"padding:2px 3px 5px 3px;font-style:italic;\">No Remittance Addresses</div>";

			return $tbl;

		} else {

			$id = $this->ajax_vars['id'];
			$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
			$this->content['popup_controls']['popup_title'] = ( is_numeric($id) ? "Edit Remittance Address" : "Add Remittance Address" );

			$this->content['focus'] = 'name';

			if ( is_numeric($id) ) {

				$remit_str = fetch_sys_var('REMIT_TO_ADDRESSES');
				if ( $remit_str )
					$remit_arr = __unserialize( stripslashes($remit_str) );

				if ( ! isset($remit_arr[$id]) )
    				return $this->__trigger_error("The remittance address you selected cannot be found. Please reload window and try again. <!-- Tried fetching remittance address [ $id ] -->", E_USER_ERROR, __FILE__, __LINE__, 1, 2);
			}

			$tbl =
			$this->form->form_tag() .
			$this->form->hidden( array(
                "vendor_hash"       =>  $this->ajax_vars['vendor_hash'],
                "popup_id"          =>  $this->popup_id,
                "jscript_action"    =>  $this->ajax_vars['jscript_action'],
                "active_search"     =>  $this->ajax_vars['active_search'],
                "from"              =>  $this->ajax_vars['from'],
                "z_total"           =>  ( $this->ajax_vars['from'] ? $this->ajax_vars['z_total'] : NULL ),
                "selected_remit"    =>  ( $this->ajax_vars['from'] ? $this->ajax_vars['ztotal'] : NULL ),
                "parent_popup_id"   =>  ( $this->ajax_vars['from'] ? $this->ajax_vars['parent_popup_id'] : NULL )
			) ) . "
			<div class=\"panel\" id=\"main_table{$this->popup_id}\">
				<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
					<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
						<p id=\"feedback_message{$this->popup_id}\"></p>
				</div>
				<div>
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
						<tr>
							<td class=\"smallfont\" style=\"padding-top:15px;text-align:right;background-color:#ffffff;\" id=\"err1{$this->popup_id}\">Remittance Name: *</td>
							<td style=\"background-color:#ffffff;padding-top:15px;\">" .
    							$this->form->text_box(
        							"name=name",
        							"value=" . stripslashes($remit_arr[$id]['name']),
        							"size=35",
        							"maxlength=128"
    							) . "
    						</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#ffffff;\" id=\"err2{$this->popup_id}\">Street: *</td>
							<td style=\"background-color:#ffffff;\">" .
    							$this->form->text_area(
        							"name=street",
        							"value=" . ( $remit_arr[$id]['street1'] && ! $remit_arr[$id]['street'] ? stripslashes($remit_arr[$id]['street1']) : stripslashes($remit_arr[$id]['street']) ),
        							"rows=3",
        							"cols=35"
    							) . "
    						</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err4{$this->popup_id}\">City: *</td>
							<td style=\"background-color:#ffffff;\">" .
    							$this->form->text_box(
        							"name=city",
        							"value=" . stripslashes($remit_arr[$id]['city']),
        							"size=30",
        							"maxlength=128"
    							) . "
    						</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err5{$this->popup_id}\">State: *</td>
							<td style=\"background-color:#ffffff;\">
	                            <input
	                                type=\"hidden\"
	                                name=\"stateSelect_default\"
	                                id=\"stateSelect_default\"
	                                value=\"{$remit_arr[$id]['state']}\" >
	                            <div><select id=\"stateSelect\" name=\"state\"></select></div>
        					</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err6{$this->popup_id}\">Zip: *</td>
							<td style=\"background-color:#ffffff;\">" .
    							$this->form->text_box(
        							"name=zip",
        							"value={$remit_arr[$id]['zip']}",
        							"size=7",
        							"maxlength=10"
        						) . "
        					</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err7{$this->popup_id}\">Country: *</td>
							<td style=\"background-color:#ffffff;\">
	                            <input
	                                type=\"hidden\"
	                                name=\"countrySelect_default\"
	                                id=\"countrySelect_default\"
	                                value=\"" .
	                                ( $remit_arr[$id]['country'] ?
    	                                $remit_arr[$id]['country'] :
    	                                ( defined('MY_COMPANY_COUNTRY') ?
        	                                MY_COMPANY_COUNTRY : NULL
        	                            )
        	                        ) .
        	                    "\">
	                            <div><select id=\"countrySelect\" name=\"country\" onchange=\"updateState(this.id);\"></select></div>
        					</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#ffffff;\" id=\"err8{$this->popup_id}\">Notes: </td>
							<td style=\"background-color:#ffffff;\">" .
    							$this->form->text_area(
        							"name=notes",
        							"value=" . stripslashes($remit_arr[$id]['notes']),
        							"rows=4",
        							"cols=55"
        						) ."
        					</td>
						</tr>
					</table>
				</div>
				<div style=\"text-align:left;padding:15px;\" >" .
					$this->form->button(
    					"value=Save Address",
                        "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_remit', 'id=" . ( is_numeric($id) ? $id : NULL ) . "');"
					) . "&nbsp;&nbsp;" .
					( is_numeric($id) && $this->p->ck(get_class($this), 'L', 'company_settings') ?
                        $this->form->button(
                            "value=Delete Address",
                            "onClick=if( confirm('Are you sure you want to delete this remittance address?') ) { submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_remit', 'id=$id', 'rm=1'); }"
                        ) : NULL
                    )."
				</div>
			</div>";

            $this->content['jscript'][] = "setTimeout(function(){initCountry('countrySelect location_countrySelect', 'stateSelect location_stateSelect');}, 100);";
			$this->content['popup_controls']["cmdTable"] = $tbl;
		}

		return;
	}

	function fetch_override_groups() {
		$override_groups = fetch_sys_var('OVERRIDE_GROUPS');
		$override_groups = explode("|", $override_groups);

		return $override_groups;
	}

	function product_name($product_hash) {
		$result = $this->db->query("SELECT `product_name`
									FROM `vendor_products`
									WHERE `product_hash` = '$product_hash'");
		return $this->db->result($result);
	}

	function company_settings() {
		global $country_names, $country_codes;

		$time_zone_out = array(
    		"Newfoundland NST (UTC-3:30)",
    		"Atlantic AT (UTC-4:00)", //-4
			"Eastern ET (UTC-5:00)", //-5
    		"Central CT (UTC-6:00)", //-6
			"Mountain MT (UTC-7:00)", //-7
			"Pacific PT (UTC-8:00)", //-8
    		"Alaska AKT (UTC-9:00)", //-9
    		"Hawaii-Aleutian HT (UTC-10:00)" //-10
    	);

    	$time_zone_in = array(
	    	'-3:30',
	    	'-4:00',
	    	'-5:00',
	    	'-6:00',
	    	'-7:00',
	    	'-8:00',
	    	'-9:00',
	    	'-10:00'
    	);

		for ($i = 0; $i <= 200; $i++) {
			if ($i <= 100) {
				$gen_array[] = $i;
				if ($i == 0)
					$gen_array_pt[] = "Upon Receipt";
				else
					$gen_array_pt[] = $i;
			}
			$gen_array2[] = $i;
		}
		$date_formats = array(date('m-d-Y'),
							  date('m-d-y'),
							  date('m/d/Y'),
							  date('n/d/Y'),
							  date('m.d.Y'),
							  date('m.d.y'),
							  date('d-m-Y'),
							  date('d-m-y'),
							  date('d.m.Y'),
							  date('d.m.y'),
							  date('F jS, Y'),
							  date('l, F jS, Y'),
							  date('jS F Y'),
							  date('l, jS F Y'));
		$date_formats_in = array('m-d-Y',
							  	 'm-d-y',
							  	 'm/d/Y',
							  	 'n/d/Y',
							   	 'm.d.Y',
							  	 'm.d.y',
							  	 'd-m-Y',
							  	 'd-m-y',
							  	 'd.m.Y',
							  	 'd.m.y',
							  	 'F jS, Y',
							  	 'l, F jS, Y',
							  	 'jS F Y',
							  	 'l, jS F Y');

		$time_formats = array(date('g:i a'),
							  date('g:i:s a'),
							  date('h:i a'),
							  date('h:i:s A'),
							  date('H:i'),
							  date('H:i:s'));
		$time_formats_in = array('g:i a',
							  	 'g:i:s a',
							  	 'h:i a',
							  	 'h:i:s A',
							  	 'H:i',
							  	 'H:i:s');

		$this->fetch_groups();
		for ($i = 0; $i < count($this->group_info); $i++) {
			$group_name[] = $this->group_info[$i]['group_name'];
			$group_hash[] = $this->group_info[$i]['group_hash'];
		}
		$override_groups = fetch_sys_var('OVERRIDE_GROUPS');
		$override_groups = explode("|", $override_groups);

		if (defined('INTERNAL_VENDOR')) {
			$vendors = new vendors($this->current_hash);
			$vendors->fetch_master_record(INTERNAL_VENDOR);

			$internal_vendor_name = $vendors->current_vendor['vendor_name'];
		}
		$this->fetch_products(0, $this->total_products);
		for ($i = 0; $i < $this->total_products; $i++) {
			$prod_name[] = $this->product_info[$i]['product_name'];
			$prod_hash[] = $this->product_info[$i]['product_hash'];
		}

		$this->fetch_currencies();

	    $accounting = new accounting($this->current_hash);
	    $r = $this->db->query("SELECT `account_no` , `account_name` , `account_hash`
	                           FROM accounts
	                           WHERE account_type IN ('IN', 'EX', 'CG')");
	    while ($row = $this->db->fetch_assoc($r)) {
	        $acct_order[$row['account_hash']] = ($row['account_no'] ? $row['account_no'] : $row['account_name']);
	        $acct[$row['account_hash']] = ($row['account_no'] ? $row['account_no']." - " : NULL).$row['account_name'];
	    }

	    natsort($acct_order);
	    foreach ($acct_order as $h => $v)
            $G_L_acct[$h] = $acct[$h];

        unset($acct_order, $acct);

		$tbl .= "
		<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
			<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
				<p id=\"feedback_message\"></p>
		</div>
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;font-weight:bold;background-color:#efefef;padding:15px;\">
					<ul id=\"sys_maintab2\" class=\"shadetabs\">
						<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent2_1\" onClick=\"expandcontent(this);\" >System Settings</a></li>
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent2_3\" onClick=\"expandcontent(this);\" >Company Settings</a></li>
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent2_4\" onClick=\"expandcontent(this);\" >Products &amp; Services</a></li>
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent2_5\" onClick=\"expandcontent(this);\" >Resources</a></li>
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent2_6\" onClick=\"expandcontent(this);\" >Commissions &amp; Overhead</a></li>
					</ul>
					<div id=\"tcontent2_1\" class=\"tabcontent\">
						<table class=\"tborder\" cellspacing=\"1\" cellpadding=\"8\" style=\"width:100%;border:0;background-color:#777788;\">
							<tr>
								<td style=\"padding:5px 25px;background-color:#cccccc;vertical-align:top;\" colspan=\"2\">
									<div style=\"padding:15px;\">
										".$this->form->button("value=Update Settings", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_update_system', 'btn=system');")."
									</div>
								</td>
							</tr>
							<tr>
								<td style=\"padding:5px 25px;background-color:#ffffff;width:50%;vertical-align:top;\">
									<table style=\"width:100%;\" >
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Number of items to show in primary lists (i.e. proposals, customer, vendors):
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[MAIN_PAGNATION_NUM]", array_slice($gen_array, 5, 50), (defined('MAIN_PAGNATION_NUM') ? MAIN_PAGNATION_NUM : 25), array_slice($gen_array, 5, 50), "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Number of items to show in secondary lists (i.e. customer contacts, customer locations, discounts, etc):
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[SUB_PAGNATION_NUM]", array_slice($gen_array, 5, 50), (defined('SUB_PAGNATION_NUM') ? SUB_PAGNATION_NUM : 15), array_slice($gen_array, 5, 50), "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Allow editing of line items that originated from a specification application:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[SPEC_ITEM_EDIT]", array("Yes", "No"), (defined('SPEC_ITEM_EDIT') ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;vertical-align:top;\">
												Your company Logos:
												<div style=\"padding-top:5px;\">
													[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_logos', 'popup_id=logo_win', 'from=logos');\" class=\"link_standard\">upload</a></small>]
												</div>
                                                <div style=\"margin-top:10px;margin-left:25px;\">
	                                                <div style=\"padding-top:5px;height:50px;overflow:auto;background-color:#efefef;border:1px outset #cccccc\" id=\"logo_holder\">";
	                                                $logo = explode("|", fetch_sys_var('company_logo'));
	                                                if (count($logo) && $logo[0]) {
	                                                    for ($i = 0; $i < count($logo); $i++)
	                                                        $tbl .=
	                                                        "<div style=\"padding:2px 3px 5px 3px\" title=\"".$logo[$i]."\" nowrap>
	                                                            <a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this logo?')){agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'manage_logos', 'rm=1', 'from=logos', 'id=".$i."');}\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></a>
	                                                            <a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_logos', 'from=logos', 'id=".$i."', 'popup_id=logo_win')\" class=\"link_standard\" title=\"View\">".(strlen(basename($logo[$i])) > 17 ? substr(basename($logo[$i]), 0, 17)."..." : basename($logo[$i]))."</a>
	                                                        </div>";
	                                                } else
	                                                    $tbl .= "<div style=\"padding:2px 3px 5px 3px;font-style:italic;\">None</div>";
	                                                $tbl .= "
	                                                </div>
                                                </div>
											</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;vertical-align:top;\">
												Your Company Docs:
												<div style=\"padding-top:5px;\">
													[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_logos', 'popup_id=logo_win', 'from=docs');\" class=\"link_standard\">upload</a></small>]
												</div>
                                                <div style=\"margin-top:10px;margin-left:25px;\">
	                                                <div style=\"padding-top:5px;height:50px;overflow:auto;background-color:#efefef;border:1px outset #cccccc\" id=\"doc_holder\">";
	                                                $docs = explode("|", fetch_sys_var('company_docs'));
	                                                if (count($docs) && $docs[0]) {
	                                                    for ($i = 0; $i < count($docs); $i++)
	                                                        $tbl .=
	                                                        "<div style=\"padding:2px 3px 5px 3px\" title=\"".$docs[$i]."\" nowrap>
	                                                            <a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this document?')){agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'manage_logos', 'rm=1', 'from=docs', 'id=".$i."');}\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></a>
	                                                            <a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_logos', 'from=docs', 'id=".$i."', 'popup_id=logo_win')\" class=\"link_standard\" title=\"View\">".(strlen(basename($docs[$i])) > 17 ? substr(basename($docs[$i]), 0, 17)."..." : basename($docs[$i]))."</a>
	                                                        </div>";
	                                                } else
	                                                    $tbl .= "<div style=\"padding:2px 3px 5px 3px;font-style:italic;\">None</div>";
	                                                $tbl .= "
	                                                </div>
                                                </div>
											</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Default product for vendor freight charges:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[FRF_PRODUCT_HASH]", $prod_name, (defined('FRF_PRODUCT_HASH') ? $this->product_name(FRF_PRODUCT_HASH) : NULL), $prod_hash, (defined('FRF_PRODUCT_HASH') ? "blank=1" : NULL))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Default product for vendor small order fees:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[SOF_PRODUCT_HASH]", $prod_name, (defined('SOF_PRODUCT_HASH') ? $this->product_name(SOF_PRODUCT_HASH) : NULL), $prod_hash, (defined('SOF_PRODUCT_HASH') ? "blank=1" : NULL))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Default product for vendor fuel charges:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[FSF_PRODUCT_HASH]", $prod_name, (defined('FSF_PRODUCT_HASH') ? $this->product_name(FSF_PRODUCT_HASH) : NULL), $prod_hash, (defined('FSF_PRODUCT_HASH') ? "blank=1" : NULL))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Default product for CBD fees:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[CBD_PRODUCT_HASH]", $prod_name, (defined('CBD_PRODUCT_HASH') ? $this->product_name(CBD_PRODUCT_HASH) : NULL), $prod_hash, (defined('CBD_PRODUCT_HASH') ? "blank=1" : NULL))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Outgoing Mail/Fax queue:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[MAIL_QUEUE]", array("Enabled", "Disabled"), (defined('MAIL_QUEUE') && MAIL_QUEUE == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
    										</td>
										</tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Force Non-SSL Requests to SSL?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[FORCE_SSL_REDIRECT]", array("Yes", "No"), (defined('FORCE_SSL_REDIRECT') && FORCE_SSL_REDIRECT == 1 ? 1 : 0), array(1, 0), "blank=1")."

                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Require items to be received in order to map on A/P line item?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[AP_LINE_RECEIVE]", array("Yes", "No"), (defined('AP_LINE_RECEIVE') && AP_LINE_RECEIVE == 1 ? 1 : NULL), array(1, NULL), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
                                        ".(defined('HOSTING_TYPE') && HOSTING_TYPE == 'R' ? "
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Automatically install updates when they become available?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[AUTO_UPDATE]", array("Yes", "No"), (defined('AUTO_UPDATE') && AUTO_UPDATE == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>" : $this->form->hidden(array('system_var[AUTO_UPDATE]'=>(defined('AUTO_UPDATE') && AUTO_UPDATE == 1 ? 1 : 0))))."
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Proposal status options:
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'proposal_status_list', 'popup_id=proposal_status_win');\" class=\"link_standard\">edit list</a></small>]
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Default new customers to manual credit hold?
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[CUSTOMER_CREDIT_HOLD]", array("Yes", "No"), (defined('CUSTOMER_CREDIT_HOLD') && CUSTOMER_CREDIT_HOLD == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Activate Item Library?
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[ACTIVATE_ITEM_LIBRARY]", array("Yes", "No"), (defined('ACTIVATE_ITEM_LIBRARY') && ACTIVATE_ITEM_LIBRARY == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
										<tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Activate Sales Rep ID in invoice numbers?
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[ACTIVATE_SALES_REP_ID]", array("Yes", "No"), (defined('ACTIVATE_SALES_REP_ID') && ACTIVATE_SALES_REP_ID == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                         <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Display Journal entries on the AR Report?
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[ACTIVATE_AR_JOURNAL]", array("Yes", "No"), (defined('ACTIVATE_AR_JOURNAL') && ACTIVATE_AR_JOURNAL == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Display Journal entries on the AP Report?
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[ACTIVATE_AP_JOURNAL]", array("Yes", "No"), (defined('ACTIVATE_AP_JOURNAL') && ACTIVATE_AP_JOURNAL == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Display Vendor Deposits on the AP Report?
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[ACTIVATE_AP_DEPOSITS]", array("Yes", "No"), (defined('ACTIVATE_AP_DEPOSITS') && ACTIVATE_AP_DEPOSITS == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
									</table>
								</td>
								<td style=\"padding:5px 25px;background-color:#ffffff;width:50%;vertical-align:top;\">
									<table style=\"width:100%;\" >
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Number of days between the ship date and install date to display storage request notification warning:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[PO_DATE_ALERT]", array_slice($gen_array, 4, 60), (defined('PO_DATE_ALERT') ? PO_DATE_ALERT : 20), array_slice($gen_array, 4, 60), "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Number of days prior to a bill coming due to place it in the payment queue?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[BILL_PAY_QUEUE]", array_slice($gen_array, 4, 25), (defined('BILL_PAY_QUEUE') ? BILL_PAY_QUEUE : 15), array_slice($gen_array, 4, 25), "blank=1")."
                                                </div>
        									</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											When a customer refund is created, how many days until it should be placed in the payment queue?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[CUSTOMER_REFUND_PAY_DAYS]", array_slice($gen_array, 4, 25), (defined('CUSTOMER_REFUND_PAY_DAYS') ? CUSTOMER_REFUND_PAY_DAYS : 15), array_slice($gen_array, 4, 25), "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											In which timezone does your company reside?
                                                <div style=\"margin-top:10px;margin-left:25px;\">" .
                                                    $this->form->select(
                                                        "system_var[DEFAULT_TIMEZONE]",
                                                        $time_zone_out,
                                                        ( defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : $time_zone_out[5] ),
                                                        $time_zone_in,
                                                        "blank=1"
                                                    ) . "
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											How long (in minutes) should DealerChoice wait before logging out an inactive user?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[USER_TIMEOUT]", array_slice($gen_array2, 14, 120), (defined('USER_TIMEOUT') ? USER_TIMEOUT : 45), array_slice($gen_array2, 14, 120), "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											How to format date stamps:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[DATE_FORMAT]", $date_formats, (defined('DATE_FORMAT') ? DATE_FORMAT : $date_formats[0]), $date_formats_in, "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											How to format timestamps:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[TIMESTAMP_FORMAT]", $time_formats, (defined('TIMESTAMP_FORMAT') ? TIMESTAMP_FORMAT : $time_formats[0]), $time_formats_in, "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											How many days should a proposal wait before being archived?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[PROPOSAL_ARCHIVE_DAYS]", $gen_array2, (defined('PROPOSAL_ARCHIVE_DAYS') ? PROPOSAL_ARCHIVE_DAYS : 30), $gen_array2, "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											After a new proposal is created, how many days until it is no longer valid?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[DEFAULT_PROPOSAL_VALID_DAYS]", array_slice($gen_array, 29, 90), (defined('DEFAULT_PROPOSAL_VALID_DAYS') ? DEFAULT_PROPOSAL_VALID_DAYS : 30), array_slice($gen_array, 29, 90), "blank=1")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Allow users to modify customer invoice date upon creation:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[INVOICE_BACKDATE]", array("Yes", "No"), (defined('INVOICE_BACKDATE') && INVOICE_BACKDATE == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Once a purchase order has been invoiced, should it be locked to prevent changes?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[PO_INVOICE_EDIT]", array("Yes", "No"), (defined('PO_INVOICE_EDIT') && PO_INVOICE_EDIT == 1 ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Allow customer login:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[CUSTOMER_LOGIN]", array("Login Allowed", "Login Disabled"), (fetch_sys_var('CUSTOMER_LOGIN') ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
        									</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Allow Employee Login:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[USER_LOGIN]", array("Login Allowed", "Login Disabled"), (fetch_sys_var('USER_LOGIN') ? 1 : 0), array(1, 0), "blank=1")."
                                                </div>
    										</td>
										</tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Customer credit correction codes:
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'correction_code_list', 'popup_id=correction_code_win');\" class=\"link_standard\">edit list</a></small>]
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Field customization:
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('custom_fields', 'sf_loadcontent', 'show_popup_window', 'field_editor', 'popup_id=editor_popup');\" class=\"link_standard\">customize fields</a></small>]
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Posting date to use when entering new payables
                                                <div style=\"padding-top:5px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[PAYABLE_POSTING_DATE]", array("Receipt Date", "Invoice Date"), (defined('PAYABLE_POSTING_DATE') ? PAYABLE_POSTING_DATE : 'RECEIPT_DATE'), array("RECEIPT_DATE", "INVOICE_DATE"), "blank=1")."
                                                </div>
                                            </td>
                                        </tr>
									</table>
								</td>
							</tr>
							<!--
							<tr>
                                <td colspan=\"2\" style=\"padding:10px;background-color:#ffffff;\">
                                    <fieldset>
                                        <legend>SMTP Email Gateway Settings</legend>
                                        <table>
                                            <tr>
                                                <td colspan=\"3\" style=\"padding-top:15px;padding-left:15px;\">
                                                    Which SMTP email gateway should DealerChoice use when sending emails?
                                                    <div style=\"padding-top:5px;\">
                                                        ".$this->form->select("system_var[SMTP_GATEWAY]",
												                               array("Use DealerChoice SMTP Email Gateway", "Define Your Own SMTP Email Gateway"),
												                               (defined('SMTP_GATEWAY') && SMTP_GATEWAY == 1 ? 1 : 2),
												                               array(2, 1),
												                               "blank=1",
												                               "onChange=if(this.options[this.selectedIndex].value==2){\$('smtp_gateway').disabled=1;\$('smtp_username').disabled=1;\$('smtp_password').disabled=1}else{if(\$('smtp_gateway')){alert('y');\$('smtp_gateway').disabled=0;}\$('smtp_username').disabled=0;\$('smtp_password').disabled=0}")."
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style=\"padding-left:15px;padding-bottom:15px;padding-top:5px;\">
                                                    SMTP Host
                                                    <div style=\"padding-top:5px;\">
                                                        ".$this->form->text_box("name=smtp_host",
                                                                                "value=".(defined('USER_SMTP_HOST') ? USER_SMTP_HOST : NULL),
												                                (!defined('SMTP_GATEWAY') || SMTP_GATEWAY == 2 ? "disabled" : NULL))."
                                                    </div>
                                                </td>
                                                <td style=\"padding-left:15px;padding-bottom:15px;padding-top:5px;\">
                                                    SMTP Username
                                                    <div style=\"padding-top:5px;\">
                                                        ".$this->form->text_box("name=smtp_username",
												                                "id=smtp_username",
                                                                                "value=".(defined('USER_SMTP_USERNAME') ? USER_SMTP_USERNAME : NULL),
												                                (!defined('SMTP_GATEWAY') || SMTP_GATEWAY == 2 ? "disabled" : NULL))."
                                                    </div>
                                                </td>
                                                <td style=\"padding-left:15px;padding-bottom:15px;padding-top:5px;\">
                                                    SMTP Password
                                                    <div style=\"padding-top:5px;\">
                                                        ".$this->form->text_box("name=smtp_password",
                                                                                "value=".(defined('USER_SMTP_PASSWORD') ? USER_SMTP_PASSWORD : NULL),
												                                (!defined('SMTP_GATEWAY') || SMTP_GATEWAY == 2 ? "disabled" : NULL))."
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </fieldset>
                                </td>
							</tr>
							-->
						</table>
					</div>
					<div id=\"tcontent2_3\" class=\"tabcontent\">
						<table class=\"tborder\" cellspacing=\"1\" cellpadding=\"8\" style=\"width:100%;border:0;background-color:#777788;\">
							<tr>
								<td style=\"padding:5px 25px;background-color:#cccccc;vertical-align:top;\" colspan=\"2\">
									<div style=\"padding:15px;\">
										".$this->form->button("value=Update Settings", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_update_system', 'btn=company');")."
									</div>
								</td>
							</tr>
							<tr>
								<td style=\"padding:5px 25px;background-color:#ffffff;width:50%;vertical-align:top;\">
									<table style=\"width:100%;\" >
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											A seed number to precede proposal numbers:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[PROPOSAL_SEED]", "value=".(defined('PROPOSAL_SEED') ? PROPOSAL_SEED : ''))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Next proposal number to use:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[NEXT_PROPOSAL_NUM]", "value=".(defined('NEXT_PROPOSAL_NUM') ? NEXT_PROPOSAL_NUM : ''), "size=8")."
                                                </div>
        									</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											A seed number to precede PO numbers:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[PO_SEED]", "value=".(defined('PO_SEED') ? PO_SEED : ''))."
                                                </div>
        									</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Next PO number to use:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[NEXT_PO_NUM]", "value=".(defined('NEXT_PO_NUM') ? NEXT_PO_NUM : ''), "size=8")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											A seed number to precede work order numbers:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[WORK_ORDER_SEED]", "value=".(defined('WORK_ORDER_SEED') ? WORK_ORDER_SEED : ''))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Next Work Order number to use:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[NEXT_WORK_ORDER_NUM]", "value=".(defined('NEXT_WORK_ORDER_NUM') ? NEXT_WORK_ORDER_NUM : ''), "size=8")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											A seed number to precede invoice numbers:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[INVOICE_SEED]", "value=".(defined('INVOICE_SEED') ? INVOICE_SEED : ''))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Next invoice number to use:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[NEXT_INVOICE_NO]", "value=".(defined('NEXT_INVOICE_NO') ? NEXT_INVOICE_NO : ''), "size=8")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Default Customer Payment Terms:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[DEFAULT_CUST_PAY_TERMS]", array_slice($gen_array_pt, 0, 90), (defined('DEFAULT_CUST_PAY_TERMS') ? DEFAULT_CUST_PAY_TERMS : NULL), array_slice($gen_array, 0, 90))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;vertical-align:top;padding-top:5px;\">
												Minimum GP margin flag:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[MARGIN_FLAG]", array_slice($gen_array, 4, 45), (defined('MARGIN_FLAG') ? (float)MARGIN_FLAG * 100 : NULL), array_slice($gen_array, 4, 45))."
	                                                <span style=\"padding-left:15px;\">
	                                                    ".$this->form->checkbox("name=system_var[MARGIN_FLAG_AUTH]", "value=1", (defined('MARGIN_FLAG_AUTH') ? "checked" : NULL))."
	                                                    Require Authorization?
	                                                </span>
                                                </div>
											</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Apply a company wide overhead factor?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[OVERHEAD_TYPE]", array("No", "Yes, to the sell amount", "Yes, to the cost amount"), (defined('OVERHEAD_TYPE') ? OVERHEAD_TYPE : NULL), array("", "S", "C"), "blank=1")."
                                                    <div style=\"margin-top:10px;\">
                                                        Rate:&nbsp;
                                                        ".$this->form->text_box("name=system_var[OVERHEAD_RATE]", "value=".(defined('OVERHEAD_RATE') ? (float)OVERHEAD_RATE * 100 : NULL), "size=3", "style=text-align:right")." %
                                                    </div>
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Default deposit requirement for new customers:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[DEFAULT_CUST_DEPOSIT]", array_slice($gen_array, 5, 99), (defined('DEFAULT_CUST_DEPOSIT') ? (float)DEFAULT_CUST_DEPOSIT * 100 : NULL), array_slice($gen_array, 5, 99))." %
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											If a customer's A/R is over ".$this->form->text_box("name=system_var[ACCOUNT_BALANCE_AMT]",
    											                                                    "value=".(defined('ACCOUNT_BALANCE_AMT') ?
                        												                                ACCOUNT_BALANCE_AMT : NULL),
                        												                            "size=8",
                        												                            "style=text-align:right")."&nbsp;".
												$this->form->select("system_var[ACCOUNT_BALANCE_RULE]",
												                    array("%", "dollars"),
												                    (defined('ACCOUNT_BALANCE_RULE') ?
    												                    ACCOUNT_BALANCE_RULE : NULL),
    												                array('P', 'A'))."
                                            </td>
										</tr>
										<tr>
											<td style=\"text-align:right;padding-left:10px;vertical-align:top;padding-right:20px;\">
    											and more than ".$this->form->select("system_var[ACCOUNT_BALANCE_SCHED]",
    												                                $gen_array2, (defined('ACCOUNT_BALANCE_SCHED') ?
        												                                ACCOUNT_BALANCE_SCHED : NULL),
        												                            $gen_array2)." days outstanding,
                                                <div style=\"margin-top:5px;\">then stop outgoing proposals.</div>
                                            </td>
										</tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Enable multiple currencies?
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    <div style=\"float:right;display:".(defined('MULTI_CURRENCY') ? "block" : "none")."\" id=\"exchange_tbl\">
                                                        <img src=\"images/icon_arrow_small.gif\" />&nbsp;&nbsp;
                                                        <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'exchange_table', 'popup_id=exchange_win');\">Open Currency Table</a>
                                                    </div>
                                                    ".$this->form->checkbox("name=system_var[MULTI_CURRENCY]",
                                                                            "value=1",
                                                                            (defined('MULTI_CURRENCY') ? "checked" : NULL),
                                                                            "onClick=if(this.checked){\$('exchange_tbl').setStyle({'display':'block'});\$('exchange_acct').setStyle({'display':'block'})}else{\$('exchange_tbl').setStyle({'display':'none'});\$('exchange_acct').setStyle({'display':'none'})}")."
                                                    <div style=\"margin-top:10px;\">
	                                                    Home Currency:
	                                                    <span id=\"home_currency\" style=\"font-weight:bold;\">".($this->home_currency ?
	                                                        $this->home_currency['name'] : "Not Defined")."
	                                                    </span>
	                                                </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr id=\"exchange_acct\" style=\"display:".(defined('MULTI_CURRENCY') ? "block" : "none")."\">
                                            <td style=\"text-align:left;padding-left:35px;vertical-align:top;padding-top:10px;\">
                                                Gain/Loss on Currency Exchange - Default Account:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
	                                                ".$this->form->select("system_var[CURRENCY_GAIN_LOSS_ACCT]",
	                                                                      array_values($G_L_acct),
	                                                                      (defined('CURRENCY_GAIN_LOSS_ACCT') && isset($G_L_acct[CURRENCY_GAIN_LOSS_ACCT]) ? CURRENCY_GAIN_LOSS_ACCT : NULL),
	                                                                      array_keys($G_L_acct))."
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
                                                Account to be used when applying finance charges:
                                                <div style=\"margin-top:10px;margin-left:50px;\">
                                                    ".$this->form->select("system_var[FINANCE_CHARGE_ACCOUNT]",
                                                                          array_values($G_L_acct),
                                                                          (defined('FINANCE_CHARGE_ACCOUNT') && isset($G_L_acct[FINANCE_CHARGE_ACCOUNT]) ? FINANCE_CHARGE_ACCOUNT : NULL),
                                                                          array_keys($G_L_acct))."
                                                </div>
                                            </td>
                                        </tr>
									</table>
								</td>
								<td style=\"padding:5px 25px;background-color:#ffffff;width:50%;vertical-align:top;\">
									<table style=\"width:100%;\" >
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											If a customer falls short of the required deposit, what percent threshold would prevent PO's from being cut?
    											<div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[DEPOSIT_THRESHOLD]", array_slice($gen_array, 0, 15), (defined('DEPOSIT_THRESHOLD') ? (float)DEPOSIT_THRESHOLD * 100 : NULL), array_slice($gen_array, 0, 15), "blank=1")." %
    											</div>
    										</td>
										</tr>
										<tr>
											<td><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Footer message to be printed on all proposals:
    											<div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_area("name=system_var[proposal_footer_message]",
                                                                             "value=".fetch_sys_var('proposal_footer_message'),
                                                                             "rows=3",
                                                                             "cols=45")."
    											</div>
        									</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\" id=\"err_internal_vendor\">
    											Vendor to be assigned to internal resources:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=internal_vendor",
                                                                            "value=".$internal_vendor_name,
                                                                            "autocomplete=off",
                                                                            "size=30",
                                                                            "TABINDEX=1",
                                                                            "onFocus=position_element($('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if($F('internal_vendor_hash')==''){clear_values('internal_vendor');}key_call('proposals', 'internal_vendor', 'internal_vendor_hash', 1);",
                                                                            "onBlur=vtobj.reset_keyResults();key_clear();",
                                                                            "onKeyDown=clear_values('internal_vendor_hash');").
                                                    $this->form->hidden(array("internal_vendor_hash" => (defined('INTERNAL_VENDOR') ? INTERNAL_VENDOR : NULL)))."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Footer message to be printed on all invoices:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_area("name=system_var[invoice_footer_message]",
                                                                             "value=".fetch_sys_var('invoice_footer_message'),
                                                                             "rows=3",
                                                                             "cols=45")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Your company name:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[MY_COMPANY_NAME]", "value=".(defined('MY_COMPANY_NAME') ? MY_COMPANY_NAME : NULL), "maxlength=255", "size=55")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Your company address:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_area("name=system_var[MY_COMPANY_ADDRESS]",
                                                                             "value=".(defined('MY_COMPANY_ADDRESS') ? MY_COMPANY_ADDRESS : NULL),
                                                                             "rows=3",
                                                                             "cols=45")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Your company zip code:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[MY_COMPANY_ZIP]", "value=".(defined('MY_COMPANY_ZIP') ? MY_COMPANY_ZIP : NULL), "maxlength=255", "size=10")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Your company country:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[MY_COMPANY_COUNTRY]", $country_names, (defined('MY_COMPANY_COUNTRY') ? MY_COMPANY_COUNTRY : NULL), $country_codes)."
                                                </div>
    										</td>
										</tr>
                                        <tr>
                                            <td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:left;padding-left:10px;\">
                                                Your company's federal identification number:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[TAX_ID_NUMBER]", "value=".(defined('TAX_ID_NUMBER') ? TAX_ID_NUMBER : NULL))."
                                                </div>
                                            </td>
                                        </tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
												Your company remit to addresses:
												<div style=\"padding-top:5px;margin-left:25px;\">
													[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_remit_addresses', 'popup_id=new_remit_win');\" class=\"link_standard\">add new</a></small>]
												</div>
                                                <div style=\"margin-top:10px;margin-left:25px;\">
	                                                <div style=\"padding-top:5px;height:50px;overflow:auto;background-color:#efefef;border:1px outset #cccccc\" id=\"remit_to_holder\">";

	                                                    $remit_str = fetch_sys_var('REMIT_TO_ADDRESSES');
	                                                    if ($remit_str)
	                                                        $remit_arr = __unserialize( stripslashes($remit_str) );
	                                                    else
	                                                        $remit_arr = array();

	                                                    if ( count($remit_arr) && $remit_arr[0] ) {

	                                                        for ( $i = 0; $i < count($remit_arr); $i++ ) {

	                                                            $remit_to_title = stripslashes($remit_arr[$i]['street']) . "\n" . stripslashes($remit_arr[$i]['city']) . ", {$remit_arr[$i]['state']} {$remit_arr[$i]['zip']}";
	                                                            $tbl .=
	                                                            "<div style=\"padding:2px 3px 5px 3px\" title=\"$remit_to_title\" nowrap>
	                                                                <a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this remit to address?')){agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'doit_remit', 'rm=1', 'id=".$i."');}\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></a>
	                                                                <a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'manage_remit_addresses', 'id=".$i."', 'popup_id=remit_win')\" class=\"link_standard\" title=\"View\">".(strlen($remit_arr[$i]['name']) > 17 ? stripslashes(substr($remit_arr[$i]['name'], 0, 17))."..." : stripslashes($remit_arr[$i]['name']))."</a>
	                                                            </div>";
	                                                        }
	                                                    } else
	                                                        $tbl .= "<div style=\"padding:2px 3px 5px 3px;font-style:italic;\">No Remittance Addresses</div>";
	                                                $tbl .= "
	                                                </div>
                                                </div>
											</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Your company phone number:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[MY_COMPANY_PHONE]", "value=".(defined('MY_COMPANY_PHONE') ? MY_COMPANY_PHONE : NULL), "maxlength=255")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>										<tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Your company fax number:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[MY_COMPANY_FAX]", "value=".(defined('MY_COMPANY_FAX') ? MY_COMPANY_FAX : NULL), "maxlength=255")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;\">
    											Your company website:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->text_box("name=system_var[MY_COMPANY_URL]", "value=".(defined('MY_COMPANY_URL') ? MY_COMPANY_URL : NULL), "maxlength=255", "size=45")."
                                                </div>
    										</td>
										</tr>
										<tr>
											<td ><hr style=\"height:1px;color:#cccccc;width:90%;\"></td>
										</tr>
										<tr>
											<td style=\"text-align:left;padding-left:10px;vertical-align:top;\">
    											Overrides &amp; Authorizations may be made by the following groups:
                                                <div style=\"margin-top:10px;margin-left:25px;\">
                                                    ".$this->form->select("system_var[OVERRIDE_GROUPS][]", $group_name, $override_groups, $group_hash, "multiple", "size=5", "style=width:200px;", "blank=1")."
                                                </div>
    										</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td style=\"padding:5px 25px;background-color:#ffffff;width:50%;vertical-align:top;\" colspan=\"2\">
									<fieldset>
										<legend>Tax Tables</legend>
						                <div id=\"tax_feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin:10px 10px 0 25px;\">
						                    <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
						                        <p id=\"tax_feedback_holder_message\"></p>
						                </div>
										<div style=\"margin:10px 10px 0 25px;\">
                                            <strong>Country:</strong>
                                            <div style=\"margin:5px 5px 0 20px;\">
                                                ".$this->form->select("country",
                                                                       array("United States", "Canada"),
                                                                       (defined('MY_COMPANY_COUNTRY') ? MY_COMPANY_COUNTRY : 'US'),
                                                                       array('US', 'CA'),
                                                                       "blank=1",
                                                                       "onChange=if(this.options[this.selectedIndex].value=='US'){\$('us_tax').setStyle({'display':'block'});\$('ca_tax').setStyle({'display':'none'});\$('tax_controls').setStyle({'display':'block'});}else{\$('us_tax').setStyle({'display':'none'});\$('ca_tax').setStyle({'display':'block'});\$('tax_controls').setStyle({'display':'none'});}")."
                                            </div>
										</div>
										<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;border:0\">".($this->p->ck(get_class($this), 'L', 'company_settings') ? "
                                            <tr id=\"tax_controls\" style=\"display:".(defined('MY_COMPANY_COUNTRY') && MY_COMPANY_COUNTRY == 'US' ? "block" : "none")."\">
                                                <td>
                                                    <div style=\"margin-left:40px;\">
                                                        [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_tax_rule', 'popup_id=main_popup', 'country=' + \$F('country'));\" class=\"link_standard\">new tax rule</a></small>]
                                                    </div>
                                                </td>
                                            </tr>" : NULL)."
											<tr id=\"us_tax\" style=\"display:".(!defined('MY_COMPANY_COUNTRY') || (defined('MY_COMPANY_COUNTRY') && MY_COMPANY_COUNTRY == 'US') ? 'block' : 'none')."\">
												<td style=\"padding:10px 50px;\" id=\"tax_table\">".$this->tax_form(1, 'US')."</td>
											</tr>
                                            <tr id=\"ca_tax\" style=\"display:".(defined('MY_COMPANY_COUNTRY') && MY_COMPANY_COUNTRY == 'CA' ? 'block' : 'none')."\">
                                                <td style=\"padding:10px 50px;\" id=\"tax_table\">".$this->tax_form(1, 'CA')."</td>
                                            </tr>
										</table>
									</fieldset>
								</td>
							</tr>
						</table>
					</div>
					<div id=\"tcontent2_4\" class=\"tabcontent\">".$this->product_info_form(1)."</div>
					<div id=\"tcontent2_5\" class=\"tabcontent\">".$this->resource_info_form(1)."</div>
					<div id=\"tcontent2_6\" class=\"tabcontent\">".$this->commission_form(1)."</div>
				</td>
			</tr>
		</table>";

		if ($this->ajax_vars['otf'])
			$this->content['html']['sys_tcontent2'] = $this->form->form_tag().$tbl.$this->form->close_form();
		else {
			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'sys_maintab2\')', 200);";
			return $tbl;
		}
		return $tbl;
	}

	function fetch_resource_record($resource_hash) {
		$result = $this->db->query("SELECT resources.* , vendors.vendor_name as resource_vendor
									FROM `resources`
									LEFT JOIN `vendors` ON vendors.vendor_hash = resources.vendor_hash
									WHERE `resource_hash` = '$resource_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_resource = $row;
			$this->resource_hash = $resource_hash;

			/*
			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `line_items`
										WHERE `product_hash` = '".$this->product_hash."'");
			if ($this->db->result($result))
				$this->current_product['rm_lock'] = 1;
			*/
			return true;
		}

		return false;
	}

	function fetch_resources($type=NULL, $start, $order_by=NULL, $order_dir=NULL) {
		if (!$end)
			$end = SUB_PAGNATION_NUM;

		$this->resource_info = array();
		$result = $this->db->query("SELECT resources.resource_name , resources.resource_hash , resources.vendor_hash , resources.active , vendors.vendor_name as resource_vendor
									FROM `resources`
									LEFT JOIN `vendors` ON vendors.vendor_hash = resources.vendor_hash ".($type ? "
									WHERE `resource_type` = '$type' " : NULL).($order_by ? "
									ORDER BY $order_by ".($order_dir ? $order_dir : "ASC") : NULL)."
									LIMIT $start , $end");
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->resource_info, $row);


	}

	function doit_resource() {
		if ($resource_hash = $_POST['resource_hash']) {
			$valid = $this->fetch_resource_record($resource_hash);
			if ($valid)
				$edit_resource = true;

			$action_delete = $_POST['delete'];
		}

		if ($edit_resource && $action_delete) {
			$this->db->query("DELETE FROM `resources`
							  WHERE `resource_hash` = '$resource_hash'");

			$this->content['page_feedback'] = "Your resource has been deleted.";
			unset($this->ajax_vars, $this->current_product);

			$this->content['action'] = 'close';
			$this->content['html']['tcontent2_5'] = $this->resource_info_form(1);
			return;
		}

		if ($_POST['resource_name'] && $_POST['resource_vendor']) {
			if (!$_POST['resource_vendor_hash']) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "We can't seem to find the vendor you selected. Please make sure you are selecting a valid vendor.";
				$this->content['form_return']['err']['err2_5_1a'.$this->popup_id] = 1;
				return;
			}
			$resource['resource_name'] = $_POST['resource_name'];
			$resource['vendor_hash'] = $_POST['resource_vendor_hash'];
			$resource['active'] = $_POST['active'];
			$resource['cost_hour'] = ($_POST['cost_hour'] ? preg_replace('/[^.0-9]/', "", $_POST['cost_hour'])*1 : NULL);
			$resource['cost_day'] = ($_POST['cost_day'] ? preg_replace('/[^.0-9]/', "", $_POST['cost_day'])*1 : NULL);
			$resource['cost_halfday'] = ($_POST['cost_halfday'] ? preg_replace('/[^.0-9]/', "", $_POST['cost_halfday'])*1 : NULL);
			$resource['sell_hour'] = ($_POST['sell_hour'] ? preg_replace('/[^.0-9]/', "", $_POST['sell_hour'])*1 : NULL);
			$resource['sell_day'] = ($_POST['sell_day'] ? preg_replace('/[^.0-9]/', "", $_POST['sell_day'])*1 : NULL);
			$resource['sell_halfday'] = ($_POST['sell_halfday'] ? preg_replace('/[^.0-9]/', "", $_POST['sell_halfday'])*1 : NULL);
			$resource['descr'] = $_POST['descr'];

			if (($resource['cost_hour'] && $resource['cost_hour'] <= 0) || ($resource['cost_day']  && $resource['cost_day'] <= 0) || ($resource['cost_halfday']  && $resource['cost_halfday'] <= 0)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "Please check to make sure you indicated cost is a valid amount";
				if ($resource['cost_hour'] && $resource['cost_hour'] <= 0) $this->content['form_return']['err']['err2_5_2'.$this->popup_id] = 1;
				if ($resource['cost_day'] && $resource['cost_day'] <= 0) $this->content['form_return']['err']['err2_5_3'.$this->popup_id] = 1;
				if ($resource['cost_halfday'] && $resource['cost_halfday'] <= 0) $this->content['form_return']['err']['err2_5_4'.$this->popup_id] = 1;

				return;
			}
			if (($resource['sell_hour'] && $resource['sell_hour'] <= 0) || ($resource['sell_day'] && $resource['sell_day'] <= 0) || ($resource['sell_halfday'] && $resource['sell_halfday'] <= 0)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "Please check to make sure you indicated sell is a valid amount";
				if ($resource['sell_hour'] && $resource['sell_hour'] <= 0) $this->content['form_return']['err']['err2_5_6'.$this->popup_id] = 1;
				if ($resource['sell_day'] && $resource['sell_day'] <= 0) $this->content['form_return']['err']['err2_5_7'.$this->popup_id] = 1;
				if ($resource['sell_halfday'] && $resource['sell_halfday'] <= 0) $this->content['form_return']['err']['err2_5_8'.$this->popup_id] = 1;
				return;
			}

			if ($edit_resource) {
				$resource = $this->db->prepare_query($resource, "resources", 'UPDATE');
				$this->db->query("UPDATE `resources`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , ".implode(" , ", $resource)."
								  WHERE `resource_hash` = '$resource_hash'");
				$this->content['page_feedback'] = "Your resource has been updated.";
			} else {
				$resource_hash = md5(global_classes::get_rand_id(32, "global_classes"));
				while (global_classes::key_exists('resources', 'resource_hash', $resource_hash))
					$resource_hash = md5(global_classes::get_rand_id(32, "global_classes"));

				$resource = $this->db->prepare_query($resource, "resources", 'INSERT');
				$this->db->query("INSERT INTO `resources`
								  (`timestamp` , `last_change` , `resource_hash` , `resource_type` , ".implode(" , ", array_keys($resource)).")
								  VALUES (".time()." , '".$this->current_hash."' , '$resource_hash' , 'A' , ".implode(" , ", array_values($resource)).")");
				$this->content['page_feedback'] = "Your new resource has been added.";
			}
			unset($this->ajax_vars, $this->current_resource);

			$this->content['action'] = 'close';
			$this->content['html']['tcontent2_5'] = $this->resource_info_form(1);
			return;
		} else {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "You left some required fields blank. Please check the indicated fields and try again.";
			if (!$_POST['resource_name']) $this->content['form_return']['err']['err2_5_1'.$this->popup_id] = 1;
			if (!$_POST['resource_vendor']) $this->content['form_return']['err']['err2_5_1a'.$this->popup_id] = 1;
			return;
		}
	}

	function resource_info_form($local=NULL) {
		$action = $this->ajax_vars['action'];

		if ($this->ajax_vars['resource_hash']) {
			$this->fetch_resource_record($this->ajax_vars['resource_hash']);
			if ($valid === false)
				unset($this->ajax_vars['resource_hash']);
		}
		if ($action == 'addnew') {
			$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
			$this->content['popup_controls']['popup_title'] = ($valid ? "Edit A Resource" : "Create A New Resource");

			$tbl .= $this->form->form_tag().$this->form->hidden(array('popup_id' => $this->popup_id, 'resource_hash' => $this->resource_hash))."
			<div class=\"panel\" id=\"main_table{$this->popup_id}\">
				<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
					<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
						<p id=\"feedback_message{$this->popup_id}\"></p>
				</div>
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:15px;vertical-align:top;\" id=\"err2_5_1{$this->popup_id}\">Resource Name: *</td>
						<td style=\"background-color:#ffffff;padding-top:15px;\">
							".$this->form->text_box("name=resource_name", "value=".$this->current_resource['resource_name'], "size=35", "maxlength=128")."
							<div style=\"padding-top:5px;\">".$this->form->checkbox("name=active", "value=1", ($this->current_resource['active'] ? "checked" : NULL))."&nbsp;Active?</div>
						</td>
					</tr>
					<tr>
						<td style=\"text-align:right;background-color:#ffffff;padding-top:15px;vertical-align:top;\" id=\"err2_5_1a{$this->popup_id}\">Vendor:</td>
						<td style=\"background-color:#ffffff;padding-top:10px;\">
						".$this->form->text_box("name=resource_vendor",
												"value=".$this->current_resource['resource_vendor'],
												"autocomplete=off",
												"size=30",
												"onFocus=position_element($('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if($F('resource_vendor_hash')==''){clear_values('resource_vendor');}key_call('proposals', 'resource_vendor', 'resource_vendor_hash', 1);",
												"onBlur=vtobj.reset_keyResults();key_clear();",
												"onKeyDown=clear_values('resource_vendor_hash');").
						$this->form->hidden(array("resource_vendor_hash" => $this->current_resource['vendor_hash']))."
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_5_2{$this->popup_id}\">Hourly Cost: </td>
						<td style=\"background-color:#ffffff;\">
							".$this->form->text_box("name=cost_hour", "value=".$this->current_resource['cost_hour'], "size=10", "onBlur=if(this.value){this.value=formatCurrency(this.value)}", "maxlength=128", "style=text-align:right;")."
							<span style=\"padding-left:25px;\">
								Sell: ".$this->form->text_box("name=sell_hour", "value=".$this->current_resource['sell_hour'], "size=10", "onBlur=if(this.value){this.value=formatCurrency(this.value)}", "maxlength=128", "style=text-align:right;")."
							</span>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_5_3{$this->popup_id}\">Daily Cost: </td>
						<td style=\"background-color:#ffffff;\">
							".$this->form->text_box("name=cost_day", "value=".$this->current_resource['cost_day'], "onBlur=if(this.value){this.value=formatCurrency(this.value)}", "size=10", "maxlength=128", "style=text-align:right;")."
							<span style=\"padding-left:25px;\">
								Sell: ".$this->form->text_box("name=sell_day", "value=".$this->current_resource['sell_day'], "size=10", "onBlur=if(this.value){this.value=formatCurrency(this.value)}", "maxlength=128", "style=text-align:right;")."
							</span>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" id=\"err2_5_4{$this->popup_id}\">Half Day Cost: </td>
						<td style=\"background-color:#ffffff;\">
							".$this->form->text_box("name=cost_halfday", "value=".$this->current_resource['cost_halfday'], "onBlur=if(this.value){this.value=formatCurrency(this.value)}", "size=10", "maxlength=128", "style=text-align:right;")."
							<span style=\"padding-left:25px;\">
								Sell: ".$this->form->text_box("name=sell_halfday", "value=".$this->current_resource['sell_halfday'], "size=10", "onBlur=if(this.value){this.value=formatCurrency(this.value)}", "maxlength=128", "style=text-align:right;")."
							</span>
						</td>
					</tr>
				</table>
				<div style=\"padding:10px 15px;text-align:left;\">
					".$this->form->button("value=Save Resource", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_resource');")."
					&nbsp;&nbsp;".(!$this->current_resource['rm_lock'] ?
					$this->form->button("value=Delete Resource", "onClick=if(confirm('Are you sure you want to delete this resource? This action CANNOT be undone!')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_resource', 'delete=1');}") : NULL)."
				</div>
			</div>
			<div style=\"padding:10px 15px;text-align:left;\"></div>".$this->form->close_form();

			$this->content['popup_controls']["cmdTable"] = $tbl;
			return;
		} else {
			$p = $this->ajax_vars['p'];
			$order = $this->ajax_vars['order'];
			$order_dir = $this->ajax_vars['order_dir'];

			$rt = $this->db->query("SELECT COUNT(*) AS Total
									FROM `resources`
									WHERE `resource_type` = 'A'");
			$this->total_resources = $this->db->result($rt);
			$num_pages = ceil($this->total_resources / SUB_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ($p - 1);
			$end = $start_from + SUB_PAGNATION_NUM;
			if ($end > $this->total_resources)
				$end = $this->total_resources;

			$order_by_ops = array("resource_name"	=>	"resources.resource_name");

			$tbl = $this->fetch_resources('A', $start_from, $order_by_ops[($order ? $order : 'resource_name')], $order_dir);

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;margin-top:0;\" class=\"main_tab_table\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"3\">".($this->total_resources ? "
									<div style=\"float:right;font-weight:normal;padding-right:10px;\">".paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'system_config', 'resource_info_form', $order, $order_dir, "'otf=1'")."</div>
									Showing ".($start_from + 1)." - ".($start_from + SUB_PAGNATION_NUM > $this->total_resources ? $this->total_resources : $start_from + SUB_PAGNATION_NUM)." of ".$this->total_resources." Resources." : NULL)."
									<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">
										[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'resource_info_form', 'popup_id=main_popup', 'action=addnew');\" class=\"link_standard\">Add New Resource</a></small>]
									</div>
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'resource_info_form', 'otf=1', 'p=$p', 'order=resource_name', 'order_dir=".($order == "resource_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
										Resource Name</a>
									".($order == 'resource_name' ?
										"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
								</td>
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'resource_info_form', 'otf=1', 'p=$p', 'order=vendor_name', 'order_dir=".($order == "vendor_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
										Vendor</a>
									".($order == 'vendor_name' ?
										"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
								</td>
								<td>Active</td>
							</tr>";
							for ($i = 0; $i < ($end - $start_from); $i++) {
								$b++;
								$tbl .= "
								<tr onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'resource_info_form', 'popup_id=main_popup', 'action=addnew', 'resource_hash=".$this->resource_info[$i]['resource_hash']."');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\">
									<td>".$this->resource_info[$i]['resource_name']."</td>
									<td>".$this->resource_info[$i]['resource_vendor']."</td>
									<td style=\"padding-left:20px;\">".($this->resource_info[$i]['active'] ? 'Y' : 'N')."</td>
								</tr>".($b < ($end - $start_from) ? "
								<tr>
									<td style=\"background-color:#cccccc;\" colspan=\"3\"></td>
								</tr>" : NULL);
							}
							if (!$this->total_resources)
								$tbl .= "
								<tr>
									<td colspan=\"3\">You have no resources defined for your organization.</td>
								</tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";

			if ($local)
				return $tbl;
			else
				$this->content['html']['tcontent2_5'] = $tbl;
		}
	}

	function fetch_commission_record($commission_hash) {
		$result = $this->db->query("SELECT commission_tables.* , customers.customer_name
									FROM `commission_tables`
									LEFT JOIN `customers` ON customers.customer_hash = commission_tables.customer_hash
									WHERE `commission_hash` = '$commission_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_commission = $row;
			$this->commission_hash = $commission_hash;

			$rules = explode("|", $this->current_commission['rules']);
			unset($this->current_commission['rules']);
			for ($i = 0; $i < count($rules); $i++) {
				list($tier, $action, $rate) = explode(":", $rules[$i]);
				$this->current_commission['rules'][$tier] = array("tier_start"		=>	($i == 0 ? .01 : $previous_tier * .01),
														   		  "tier_end"		=>	$tier,
														   		  "tier_action"		=>	$action,
														   		  "tier_rate"		=>	$rate);
				$previous_tier = _round(($tier + .0001) * 100, 4);
			}
			if ($this->current_commission['type'] == 'T') {
				$this->current_commission['team'] = array();
                $r = $this->db->query("SELECT t1.* , t2.full_name AS team_member
                                       FROM commission_team_user t1
                                       LEFT JOIN users t2 ON t2.id_hash = t1.id_hash
                                       WHERE t1.commission_hash = '".$this->commission_hash."'");
                while ($row2 = $this->db->fetch_assoc($r))
                    $this->current_commission['team'][] = array('user_hash'     =>  $row2['id_hash'],
                                                                'team_member'   =>  stripslashes($row2['team_member']),
                                                                'rate'          =>  $row2['rate']);
			}

			return true;
		}

		return false;
	}

	function fetch_overhead_record($overhead_hash) {
		$result = $this->db->query("SELECT overhead_rules.* , customers.customer_name
									FROM `overhead_rules`
									LEFT JOIN `customers` ON customers.customer_hash = overhead_rules.customer_hash
									WHERE `overhead_hash` = '$overhead_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_overhead = $row;
			$this->overhead_hash = $overhead_hash;

			return true;
		}

		return false;
	}

	function fetch_commissions() {
		$this->commissions = array();

		$result = $this->db->query("SELECT commission_tables.* , customers.customer_name
									FROM `commission_tables`
									LEFT JOIN customers ON customers.customer_hash = commission_tables.customer_hash");

		while ($row = $this->db->fetch_assoc($result))
			array_push($this->commissions, $row);
	}

	function fetch_overhead() {
		$this->overhead = array();

		$result = $this->db->query("SELECT overhead_rules.* , customers.customer_name
									FROM `overhead_rules`
									LEFT JOIN customers ON customers.customer_hash = overhead_rules.customer_hash");

		while ($row = $this->db->fetch_assoc($result))
			array_push($this->overhead, $row);
	}

	function new_structure_line() {
		$tier = $_POST['tier'];
		$tier_action = $_POST['tier_action'];
		$tier_rate = $_POST['tier_rate'];
		$undo = $_POST['undo'];
		$commission_hash = $_POST['commission_hash'];
		$tier_complete = $_POST['tier_complete'];

		$action_val = array('1'	=>	'Commission at',
							'P'	=>	'Point for Point',
							'0'	=>	'No Commission');
		if (!is_array($tier)) {
			$tier = array($tier);
			$tier_action = array($tier_action);
			$tier_rate = array($tier_rate);
		}

		for ($i = 0; $i < count($tier); $i++) {
			if (is_numeric($undo) && $undo == $i)
				break;

			$tier[$i] *= 1;

			if ($tier_action[$i] == '1')
				$tier_rate[$i] *= 1;

			if ($i == count($tier) - 1 && ($tier[$i] <= $tier[$i-1] + .01 || $tier[$i] > 100)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "The commission value you entered (".$tier[$i].") is invalid. The value for this tier must be greater than ".($tier[$i-1] + .01)." and less than 100.";
				return;
			}
			if ($tier_action[$i] == '1' && ($tier_rate[$i] <= 0 || $tier_rate[$i] > 100)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "The commission rate you entered for this tier (".$tier_rate[$i].") is invalid. Please enter a valid commission rate (i.e. 10 for 10%).";
				return;
			}
			if (!is_numeric($undo) && !$tier_complete && strspn($tier[$i], ".0123456789") != strlen($tier[$i])) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "When entering commission tiers, please enter only whole numbers.";
				return;
			}
			$tbl .= $this->form->hidden(array('tier[]'			=>	$tier[$i],
											  'tier_action[]'	=>	$tier_action[$i],
											  'tier_rate[]'		=>	$tier_rate[$i]))."
			<div style=\"padding-bottom:10px;\">
				<span><a href=\"javascript:void(0);\" onClick=\"submit_form(\$('popup_id').form, 'system_config', 'exec_post', 'refresh_form', 'action=new_structure_line', 'undo=".$i."', 'commission_hash=$commission_hash')\"><img src=\"images/b_drop_small.gif\" title=\"Undo to this tier\" border=\"0\" /></a></span>
				From ".($commission_hash && $tier[$i-1] < 1 ? number_format(($tier[$i-1]* 100) + .01, 2) : number_format($tier[$i-1] + .01, 2))." %
				to ".($commission_hash && $tier[$i] < 1 ? ($tier[$i] * 100) : $tier[$i])." %
				then ".$action_val[$tier_action[$i]].($tier_action[$i] == '1' ?
					" ".($commission_hash && $tier_rate[$i] < 1 ? ($tier_rate[$i] * 100) : $tier_rate[$i])." %" : NULL)."
			</div>";
			$tier_val = number_format($tier[$i], 2);
		}
		$this->content['action'] = 'continue';
		if ($tier_val == 100) {
			$this->content['html']['tier_content_holder'.$this->popup_id] = $tbl.$this->form->hidden(array('tier_complete' => 1));
			$this->content['jscript'] = "toggle_display('commission_plus_btn{$this->popup_id}', 'none');toggle_display('commission_save_btn{$this->popup_id}', 'block');";
			return;
		} else {
			$this->content['html']['tier_content_holder'.$this->popup_id] = $tbl . "
			<div style=\"padding-bottom:10px;\">
				<span style=\"visibility:hidden\"><img src=\"images/b_drop_small.gif\" border=\"0\" /></span>
				From ".($tier_val < 10 ? "&nbsp;" : NULL).(($commission_hash && $tier_val < 1 ? ($tier_val * 100) : $tier_val) + .01)." %
				to ".$this->form->text_box("name=tier[]",
                        				   "value=",
                        				   "size=5",
                        				   "style=text-align:right;")." %
				then ".$this->form->select("tier_action[]",
                                			array("commission at", "point for point", "no commission"),
                                			(!$this->commission_hash ? 1 : $this->current_commission['a']),
                                			array(1, 'P', 0),
                                			"blank=1",
                                			"onChange=if(this.options[this.selectedIndex].value==1){\$('rate_holder').style.visibility='visible';}else{\$('rate_holder').style.visibility='hidden'}")."&nbsp;
				<span id=\"rate_holder\" style=\"visibility:visible;\">
				    ".$this->form->text_box("name=tier_rate[]",
                        				    "value=",
                        				    "size=5",
                                			"style=text-align:right;")."&nbsp;%
				</span>
			</div>";
			$this->content['jscript'] = "toggle_display('commission_plus_btn{$this->popup_id}', 'block');toggle_display('commission_save_btn{$this->popup_id}', 'none');";
		}
		return;
	}

	function doit_commission() {
		$commission_hash = $_POST['commission_hash'];
		$delete = $_POST['delete'];
		$method = $this->ajax_vars['method'];

		if ($delete) {
			$this->db->query("DELETE t1.* , t2.*
			                  FROM commission_tables t1
			                  LEFT JOIN commission_team_user t2 ON t2.commission_hash = t1.commission_hash
							  WHERE t1.commission_hash = '$commission_hash'");
			$this->db->query("UPDATE `users`
							  SET `commission_hash` = ''
							  WHERE `commission_hash` = ''");
			$this->content['page_feedback'] = "Commission rule has been deleted.";
			$this->content['action'] = 'close';
			$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'commission_form');";
			return;
		}

		if ($method == 'commission_team_members') {
			$proposal = $this->ajax_vars['proposal'];
			$w = 18;
			if ($proposal_hash = $this->ajax_vars['proposal_hash'])
                $w = 25;

            $rand_id = rand(5000, 500000);
            $tbl = $this->form->hidden(array('commission_rand[]'   =>  $rand_id))."\
            <div style=\"".($proposal ? "margin:5px;" : "margin-bottom:5px;")."\" id=\"comm_row_".$rand_id."\">\
                <span style=\"margin-right:10px;\">\
                    User:&nbsp;&nbsp;".
                    $this->form->text_box("name=commission_user_".$rand_id,
                                          "value=",
                                          "autocomplete=off",
                                          "size=".($proposal ? $w : "30"),
                                          "cid=com_user",
                                          "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}",
                                          "onKeyUp=if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}",
                                          "onBlur=key_clear();").$this->form->hidden(array('commission_hash_'.$rand_id =>  ''))."\
                </span>\
                <span>\
                    Rate: ".
                    $this->form->text_box("name=commission_user_rate_".$rand_id,
                                          "value=",
                                          "size=3",
                                          "style=text-align:right;",
                                          "cid=com_rate",
                                          "onFocus=calculate_balance(this, 'cid=com_rate');this.select();",
                                          "onChange=if(isNumeric(this.value)==false){this.value='';}")."\
                </span>\
		        <span style=\"margin-left:5px;\">\
		            <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"commission_user_rm('".$rand_id."');\"><img src=\"images/b_drop_small.gif\" border=\"0\" title=\"Remove\" /></a>\
		        </span>\
            </div>";

	        $tbl = str_replace("'", "\'", $tbl);

            $this->content['jscript'][] = "\$('commission_team_members".($proposal ? "_proposal" : NULL)."').insert({bottom:'".$tbl."'});";
	        $this->content['jscript'][] = "\$('commission_user_".$rand_id."').focus();";
	        return;
		}

		if ($_POST['name']) {
			$name = $_POST['name'];
			$type = $_POST['type'];
			$customer = $_POST['customer'];
			$customer_hash = $_POST['customer_hash'];
			$active = $_POST['active'];
			$effective_date = $_POST['effective_date'];
			$expiration_date = $_POST['expiration_date'];
			$edit_tiers = $_POST['edit_tiers'];
			$tier_complete = $_POST['tier_complete'];

			if ($type == 'C' && (!$customer || !$customer_hash)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = (!$customer ? "In order to create a customer commission rule, you must select a customer!" : "We can't seem to find the customer you selected. Please make sure you are selecting a valid customer from the search list.");
				$this->content['form_return']['err']['err2'.$this->popup_id] = 1;
				return;
			}

			if ($type == 'T') {
                $rand_id = $_POST['commission_rand'];
                $total_rate = 0;
                for ($i = 0; $i < count($rand_id); $i++) {
                	if ($_POST['commission_hash_'.$rand_id[$i]] && $_POST['commission_user_'.$rand_id[$i]] && bccomp($_POST['commission_user_rate_'.$rand_id[$i]], 0, 4) != 0) {
	                    $team_user[] = array('user_hash'    =>  $_POST['commission_hash_'.$rand_id[$i]],
	                                         'rate'         =>  $_POST['commission_user_rate_'.$rand_id[$i]]);
    	                $total_rate = bcadd($total_rate, $_POST['commission_user_rate_'.$rand_id[$i]], 4);

                	}
                }

                if (bccomp($total_rate, 100, 4) != 0)
                    return $this->__trigger_error("In order to create a new commission team, the total rates distributed across your team members must equal 100%", E_USER_NOTICE, __FILE__, __LINE__, 1);
			}

			if ($effective_date || $expiration_date) {
				if ($effective_date) {
					list($year, $month, $day) = explode("-", $effective_date);
					if (!checkdate($month, $day, $year)) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The effective date you entered is invalid.";
						$this->content['form_return']['err']['err2a'.$this->popup_id] = 1;
					}
				}
				if ($expiration_date) {
					list($year, $month, $day) = explode("-", $expiration_date);
					if (!checkdate($month, $day, $year)) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The expiration_date date you entered is invalid.";
						$this->content['form_return']['err']['err2b'.$this->popup_id] = 1;
					}
				}
				if ($expiration_date && $effective_date) {
					if (strtotime($expiration_date) < strtotime($effective_date)) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The expiration_date date you entered is invalid.";
						$this->content['form_return']['err']['err2b'.$this->popup_id] = 1;
					}
				}
			}

			if ($this->content['error'])
				return;

			$tier = $_POST['tier'];
			$tier_action = $_POST['tier_action'];
			$tier_rate = $_POST['tier_rate'];

			for ($i = 0; $i < count($tier); $i++) {
				if (!$tier_complete && strspn($tier[$i], ".0123456789") != strlen($tier[$i])) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = "When entering commission tiers, please enter only whole numbers.";
					return;
				}
				$rules[] = ($tier[$i] * .01).":".$tier_action[$i].":".($tier_rate[$i] * .01);
			}
			if ($commission_hash) {
				if ($type == 'C') {
					$result = $this->db->query("SELECT COUNT(*) AS Total
											    FROM `commission_tables`
												WHERE `type` = 'C' AND `customer_hash` = '$customer_hash'");
					if ($this->db->result($result) > 0) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = "A commission rule already exists under this customer. You may only have one commission rule per customer. Please try again.";
						return;
					}
				} elseif ($type == 'G') {
					$result = $this->db->query("SELECT COUNT(*) AS Total
											    FROM `commission_tables`
												WHERE `type` = 'G'");
					if ($this->db->result($result) > 0) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = "A GSA commission rule already exists. You may only have one GSA commission rule defined. Please try again.";
						return;
					}
				} elseif ($type == 'T') {
                    $this->db->query("DELETE FROM commission_team_user
                                      WHERE `commission_hash` = '$commission_hash'");

                    for ($i = 0; $i < count($team_user); $i++)
                        $this->db->query("INSERT INTO commission_team_user
                                          (`timestamp` , `commission_hash` , `id_hash` , `rate`)
                                          VALUE(".time()." , '$commission_hash' , '".$team_user[$i]['user_hash']."' , '".bcmul($team_user[$i]['rate'], .01, 6)."')");


				}

				$this->db->query("UPDATE `commission_tables`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `active` = '$active' , `name` = '$name' , `effective_date` = '".($effective_date ? $effective_date : "NULL")."' , `expiration_date` = '".($expiration_date ? $expiration_date : "NULL")."' , `rules` = '".@implode("|", $rules)."'
								  WHERE `commission_hash` = '$commission_hash'");
				$this->content['page_feedback'] = "Commission rule has been updated.";
			} else {
				$commission_hash = md5(global_classes::get_rand_id(32, "global_classes"));
				while (global_classes::key_exists('commission_tables', 'commission_hash', $commission_hash))
					$commission_hash = md5(global_classes::get_rand_id(32, "global_classes"));

				$this->db->query("INSERT INTO `commission_tables`
								  (`timestamp` , `last_change` , `commission_hash` , `type` , `customer_hash` , `name` , `active` , `effective_date` , `expiration_date` , `rules`)
								  VALUES(".time()." , '".$this->current_hash."' , '$commission_hash' , '$type' , '$customer_hash' , '$name' , '$active' , '".($effective_date ? $effective_date : "NULL")."' , '".($expiration_date ? $expiration_date : "NULL")."' , '".@implode("|", $rules)."')");
				$this->content['page_feedback'] = "Commission rule has been added.";

				if ($type == 'T') {
                    for ($i = 0; $i < count($team_user); $i++)
                        $this->db->query("INSERT INTO commission_team_user
                                          (`timestamp` , `commission_hash` , `id_hash` , `rate`)
                                          VALUE(".time()." , '$commission_hash' , '".$team_user[$i]['user_hash']."' , '".bcmul($team_user[$i]['rate'], .01, 6)."')");
				}
			}

			$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'commission_form');";
			$this->content['action'] = 'close';
			return;

		} else {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "Please ensure that you have completed the required (indicated) fields.";
			$this->content['form_return']['err']['err1'.$this->popup_id] = 1;
			return;
		}
	}

	function doit_overhead() {
		$overhead_hash = $_POST['overhead_hash'];
		$delete = $_POST['delete'];

		if ($delete) {
			$this->db->query("DELETE FROM `overhead_rules`
							  WHERE `overhead_hash` = '$overhead_hash'");
			$this->content['page_feedback'] = "Overhead rule has been deleted.";
			$this->content['action'] = 'close';
			$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'commission_form');";
			return;
		}

		if ($_POST['name']) {
			$name = $_POST['name'];
			$type = $_POST['type'];
			$customer = $_POST['customer'];
			$customer_hash = $_POST['customer_hash'];
			$active = $_POST['active'];
			$rate = $_POST['rate'];
			$apply_to = $_POST['apply_to'];
			$effective_date = $_POST['effective_date'];
			$expiration_date = $_POST['expiration_date'];

			if ($type == 'C' && (!$customer || !$customer_hash)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = (!$customer ? "In order to create a customer overhead rule, you must select a customer!" : "We can't seem to find the customer you selected. Please make sure you are selecting a valid customer from the search list.");
				$this->content['form_return']['err']['err2'.$this->popup_id] = 1;
				return;
			}

			if (strspn($rate, "0123456789.") != strlen($rate)) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "The overhead rate you entered is not valid. Please make sure you are entering a valid percentage for the overhead rate (i.e. 5.6)";
				$this->content['form_return']['err']['err2b'.$this->popup_id] = 1;
				return;
			}
			$rate *= .01;
			if ($effective_date || $expiration_date) {
				if ($effective_date) {
					list($year, $month, $day) = explode("-", $effective_date);
					if (!checkdate($month, $day, $year)) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The effective date you entered is invalid.";
						$this->content['form_return']['err']['err2a'.$this->popup_id] = 1;
					}
				}
				if ($expiration_date) {
					list($year, $month, $day) = explode("-", $expiration_date);
					if (!checkdate($month, $day, $year)) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The expiration_date date you entered is invalid.";
						$this->content['form_return']['err']['err2b'.$this->popup_id] = 1;
					}
				}
				if ($expiration_date && $effective_date) {
					if (strtotime($expiration_date) < strtotime($effective_date)) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'][] = "The expiration_date date you entered is invalid.";
						$this->content['form_return']['err']['err2b'.$this->popup_id] = 1;
					}
				}
			}

			if ($this->content['error'])
				return;

			if ($overhead_hash) {
				if ($type == 'C') {
					$result = $this->db->query("SELECT COUNT(*) AS Total
											    FROM `overhead_rules`
												WHERE `type` = 'C' AND `customer_hash` = '$customer_hash'");
					if ($this->db->result($result) > 0) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = "An overhead rule already exists under this customer. You may only have one overhead rule per customer. Please try again.";
						return;
					}
				} elseif ($type == 'G') {
					$result = $this->db->query("SELECT COUNT(*) AS Total
											    FROM `overhead_rules`
												WHERE `type` = 'G'");
					if ($this->db->result($result) > 0) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = "A GSA overhead rule already exists. You may only have one GSA overhead rule defined. Please try again.";
						return;
					}
				}

				$this->db->query("UPDATE `overhead_rules`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , `active` = '$active' , `name` = '$name' , `rate` = '$rate' , `apply_to` = '$apply_to' , `effective_date` = '".($effective_date ? $effective_date : "NULL")."' , `expiration_date` = '".($expiration_date ? $expiration_date : "NULL")."'
								  WHERE `overhead_hash` = '$overhead_hash'");
				$this->content['page_feedback'] = "Overhead rule has been updated.";
			} else {
				$overhead_hash = md5(global_classes::get_rand_id(32, "global_classes"));
				while (global_classes::key_exists('overhead_rules', 'overhead_hash', $overhead_hash))
					$overhead_hash = md5(global_classes::get_rand_id(32, "global_classes"));

				$this->db->query("INSERT INTO `overhead_rules`
								  (`timestamp` , `last_change` , `overhead_hash` , `type` , `customer_hash` , `name` , `active` , `rate` , `apply_to` , `effective_date` , `expiration_date`)
								  VALUES(".time()." , '".$this->current_hash."' , '$overhead_hash' , '$type' , '$customer_hash' , '$name' , '$active' , '$rate' , '$apply_to' , '".($effective_date ? $effective_date : "NULL")."' , '".($expiration_date ? $expiration_date : "NULL")."')");
				$this->content['page_feedback'] = "Overhead rule has been added.";
			}

			$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'commission_form');";
			$this->content['action'] = 'close';
			return;

		} else {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = "Please ensure that you have completed the required (indicated) fields.";
			$this->content['form_return']['err']['err1'.$this->popup_id] = 1;
			return;
		}
	}

	function commission_form($local=NULL) {
		$action = $this->ajax_vars['action'];
		$type = $this->ajax_vars['type'];

		$type_name = array("Generic Commission Rule", "Customer Commission Rule", "GSA Commission Rule", "Commission Team");
		$type_code = array("D", "C", "G", "T");

		$otype_name = array("Generic Overhead Rule", "Customer Overhead Rule", "GSA Overhead Rule");
		$otype_code = array("D", "C", "G");

		$action_out = array("commission at", "point for point", "no commission");
		$action_in = array(1, 'P', 0);

		if ($this->ajax_vars['commission_hash'] || $this->ajax_vars['overhead_hash']) {
			if ($this->ajax_vars['commission_hash']) {
				$valid = $this->fetch_commission_record($this->ajax_vars['commission_hash']);
				if ($valid === false)
					unset($this->ajax_vars['commission_hash']);
			} elseif ($this->ajax_vars['overhead_hash']) {
				$valid = $this->fetch_overhead_record($this->ajax_vars['overhead_hash']);
				if ($valid === false)
					unset($this->ajax_vars['overhead_hash']);
			}
		} else {
			$this->fetch_commissions();
			$this->fetch_overhead();
			for ($i = 0; $i < count($this->commissions); $i++) {
				if ($this->commissions[$i]['type'] == 'G') {
					unset($type_name[1], $type_code[1]);
					break;
				}
			}
			$type_name = array_values($type_name);
			$type_code = array_values($type_code);
		}

		if ($action == 'addnew') {
			$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
			if ($type == 'commission') {
				$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Commission Rules" : "Create A New Commission Rule");

				$tbl .= $this->form->form_tag().
				$this->form->hidden(array('popup_id'        => $this->popup_id,
                            			  'commission_hash' => $this->commission_hash,
                            			  'edit_tiers'      => ''))."
				<div class=\"panel\" id=\"main_table{$this->popup_id}\">
					".($valid ? "
					<h3 style=\"margin-bottom:5px;color:#00477f\">Edit Rule : ".$this->current_commission['name']."</h3>" : NULL)."
					<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
						<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
							<p id=\"feedback_message{$this->popup_id}\"></p>
					</div>
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:100%;margin-top:0;\" >
						<tr>
							<td  style=\"text-align:right;background-color:#ffffff;vertical-align:top;\">
								Rule Type: *
							</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\">
								".($this->commission_hash ?
									$this->commission_type[$this->current_commission['type']].$this->form->hidden(array('type' =>  $this->current_commission['type'])) :
									$this->form->select("type",
									                    $type_name,
									                    $this->current_commission['type'],
									                    $type_code,
									                    "onChange=if(this.options[this.selectedIndex].value=='C'){toggle_display('commission_customer_holder', 'block');}else{toggle_display('commission_customer_holder', 'none');}if(this.options[this.selectedIndex].value=='T'){toggle_display('commission_team_holder', 'block');}else{toggle_display('commission_team_holder', 'none');}", "blank=1"))."
								<div style=\"padding-top:5px;display:".($this->current_commission['type'] == 'C' ? "block" : "none").";margin-left:15px;\" id=\"commission_customer_holder\" >
									<span id=\"err2{$this->popup_id}\">Customer:</span>
									".($this->commission_hash ?
										$this->current_commission['customer_name'] :
										  $this->form->text_box("name=customer",
																"value=".($this->current_commission['type'] == 'C' ? $this->current_commission['customer'] : NULL),
																"autocomplete=off",
																"size=30",
																"onFocus=position_element($('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if($F('customer_hash')==''){clear_values('customer');}key_call('proposals', 'customer', 'customer_hash', 1);",
																"onBlur=vtobj.reset_keyResults();key_clear();",
																"onKeyDown=clear_values('customer_hash');").
									$this->form->hidden(array("customer_hash" => ($this->current_commission['type'] == 'C' ? $this->current_commission['customer_hash'] : NULL))))."
								</div>
							</td>
						</tr>
                        <tr>
                            <td  style=\"text-align:right;background-color:#ffffff;padding-top:10px;vertical-align:top;\" id=\"err1{$this->popup_id}\">Commission Name: *</td>
                            <td style=\"background-color:#ffffff;vertical-align:top;padding-top:10px\">
                                ".$this->form->text_box("name=name",
                                                        "value=".$this->current_commission['name'],
                                                        "maxlength=128",
                                                        "size=45")."
                                <div style=\"margin-top:5px;\">
                                    ".$this->form->checkbox("name=active", "value=1", ($this->current_commission['active'] ? "checked" : NULL), (!$this->commission_hash ? "checked" : NULL))."&nbsp;Active?&nbsp;
                                </div>
                            </td>
                        </tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:15px;\" id=\"err2a{$this->popup_id}\">Effective Date:</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\" id=\"commission_effective_date_holder\"></td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:15px;\" id=\"err2b{$this->popup_id}\">Expiration Date:</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\" id=\"commission_expiration_date_holder\"></td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:15px;\">GP Margin Structure: *</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\">
								<table style=\"width:100%;\" cellpadding=\"5\">
									<tr>
										<td style=\"background-color:#efefef;border:1px solid #00477f;\" nowrap>
											<div style=\"padding-bottom:5px;\" id=\"tier_content_holder{$this->popup_id}\">";
											if ($this->commission_hash) {
												$i = 0;
												$tbl .= $this->form->hidden(array('tier_complete' => 1));
												while (list($t, $tier_array) = each($this->current_commission['rules'])) {
													$tbl .= $this->form->hidden(array('tier[]'			=>	($t * 100),
																					  'tier_action[]'	=>	$tier_array['tier_action'],
																					  'tier_rate[]'		=>	($tier_array['tier_action'] == 1 ? $tier_array['tier_rate'] * 100 : $tier_array['tier_rate'])))."
													<div style=\"padding-bottom:10px;\">
														<span><a href=\"javascript:void(0);\" onClick=\"\$('edit_tiers').value=1;submit_form(\$('popup_id').form, 'system_config', 'exec_post', 'refresh_form', 'action=new_structure_line', 'undo=".$i."', 'commission_hash=".$this->commission_hash."')\"><img src=\"images/b_drop_small.gif\" title=\"Undo to this tier\" border=\"0\" /></a></span>
														From ".($tier_array['tier_start'] == .01 ? $tier_array['tier_start']:$tier_array['tier_start'] * 100)." %
														to ".($tier_array['tier_end'] * 100)." %
														then ".$action_out[array_search($tier_array['tier_action'], $action_in)].($tier_array['tier_action'] == '1' ?
															" ".($tier_array['tier_rate'] * 100)." %" : NULL)."
													</div>";
													$i++;
												}
											} else
												$tbl .= "
												From &nbsp;0.01 %
												to ".$this->form->text_box("name=tier[]",
                            											   "value=",
                            											   "size=5",
												                           "onBlur=if(this.value==100){toggle_display('commission_save_btn{$this->popup_id}', 'block');}",
                            											   "style=text-align:right;background-color:#ffffff;",
												                           "onChange=if(this.value&&isNumeric(this.value)==false){this.value=''}")." %
												then ".$this->form->select("tier_action[]",
												                            $action_out,
												                            (!$this->commission_hash ? 1 : $this->current_commission['a']),
												                            $action_in,
												                            "blank=1",
												                            "onChange=if(this.options[this.selectedIndex].value==1){\$('rate_holder_0').style.visibility='visible';}else{\$('rate_holder_0').style.visibility='hidden'}")."&nbsp;
												<span id=\"rate_holder_0\" style=\"visibility:visible;\">
    												".$this->form->text_box("name=tier_rate[]",
    												                        "value=",
    												                        "size=5",
												                            "style=text-align:right;",
												                            "onChange=if(this.value&&isNumeric(this.value)==false){this.value=''}")."&nbsp;%
        										</span>";

											$tbl .= "
											</div>
										</td>
									</tr>
									<tr>
                                        <td style=\"background-color:#ffffff;vertical-align:bottom;\">
                                            <div style=\"float:right;margin-right:23px;margin-top:5px;display:".($this->commission_hash ? "none" : "block")."\" id=\"commission_plus_btn{$this->popup_id}\">
                                                [<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"submit_form(\$('popup_id').form, 'system_config', 'exec_post', 'refresh_form', 'action=new_structure_line')\">Next</a>]
                                            </div>
                                        </td>
									</tr>
								</table>
							</td>
						</tr>
						<tr id=\"commission_team_holder\" style=\"display:".($this->current_commission['type'] == 'T' ? 'block' : 'none').";\">
                            <td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err3{$this->popup_id}\">
                                Team Members:
                                <div style=\"margin-top:5px;font-style:italic;font-size:10px;\">
                                    Enter commission percentage<br />for each member
                                </div>
                            </td>
                            <td style=\"background-color:#ffffff;\">
                                <div style=\"width:100%;overflow:auto;".($this->commission_hash && count($this->current_commission['team']) > 5 ? "height:75px;" : NULL)."\" id=\"commission_team_members\">";
									if ($this->commission_hash) {
										for ($i = 0; $i < count($this->current_commission['team']); $i++) {
											$rand_id = rand(500, 5000);

                                            $tbl .=
                                            $this->form->hidden(array('commission_rand[]'  =>  $rand_id))."
                                            <div style=\"margin-bottom:5px;\" id=\"comm_row_".$rand_id."\">
	                                            <span style=\"margin-right:10px;\">
	                                                User:&nbsp;&nbsp;".
	                                                $this->form->text_box("name=commission_user_".$rand_id,
	                                                                      "value=".$this->current_commission['team'][$i]['team_member'],
	                                                                      "autocomplete=off",
	                                                                      "size=30",
	                                                                      "cid=com_user",
	                                                                      "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}",
	                                                                      "onKeyUp=if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}",
	                                                                      "onBlur=key_clear();").$this->form->hidden(array('commission_hash_'.$rand_id =>  $this->current_commission['team'][$i]['user_hash']))."
	                                            </span>
	                                            <span>
	                                                Rate: ".
	                                                $this->form->text_box("name=commission_user_rate_".$rand_id,
	                                                                      "value=".trim_decimal(bcmul($this->current_commission['team'][$i]['rate'], 100, 6)),
	                                                                      "style=text-align:right;",
	                                                                      "cid=com_rate",
	                                                                      "size=3",
	                                                                      "onFocus=calculate_balance(this, 'cid=com_rate');this.select();",
	                                                                      "onChange=if(isNumeric(this.value)==false){this.value='';}")."
	                                            </span>
                                                <span style=\"margin-left:5px;\">
                                                    <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"commission_user_rm('".$rand_id."');\"><img src=\"images/b_drop_small.gif\" border=\"0\" title=\"Remove\" /></a>
                                                </span>
	                                        </div>";
										}
									} else {
                                        $rand_id = rand(5000, 500000);

                                        $tbl .= $this->form->hidden(array('commission_rand[]'  =>  $rand_id))."
	                                    <div style=\"margin-bottom:5px;\" id=\"comm_row_".$rand_id."\">
	                                        <span style=\"margin-right:10px;\">
	                                            User:&nbsp;&nbsp;".
	                                            $this->form->text_box("name=commission_user_".$rand_id,
		                                                              "value=",
		                                                              "autocomplete=off",
		                                                              "size=30",
	                                                                  "cid=com_user",
		                                                              "onFocus=position_element(\$('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}",
		                                                              "onKeyUp=if(ka==false && this.value){key_call('sys', 'commission_user_".$rand_id."', 'commission_hash_".$rand_id."', 1);}",
		                                                              "onBlur=key_clear();if(this.value && \$F('commission_hash_".$rand_id."') && \$F('commission_user_rate_".$rand_id."')){\$('first_comm_user_rm_".$rand_id."').show();}").
	                                            $this->form->hidden(array('commission_hash_'.$rand_id =>  ''))."
	                                        </span>
	                                        <span>
	                                            Rate: ".
								                $this->form->text_box("name=commission_user_rate_".$rand_id,
								                                      "value=",
								                                      "style=text-align:right;",
								                                      "cid=com_rate",
								                                      "size=3",
								                                      "onBlur=if(this.value && \$F('commission_hash_".$rand_id."')){\$('first_comm_user_rm_".$rand_id."').show();}",
								                                      "onFocus=calculate_balance(this, 'cid=com_rate');this.select();",
								                                      "onChange=if(isNumeric(this.value)==false){this.value='';}")."
	                                        </span>
                                            <span style=\"margin-left:5px;display:none;\" id=\"first_comm_user_rm_".$rand_id."\">
                                                <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"commission_user_rm('".$rand_id."');\"><img src=\"images/b_drop_small.gif\" border=\"0\" title=\"Remove\" /></a>
                                            </span>
	                                    </div>";
									}
									$tbl .= "
                                </div>
                                <div style=\"float:right;margin-right:28px;margin-bottom:5px;\">
                                    [<a href=\"javascript:void(0);\" onClick=\"commission_split();\" class=\"link_standard\">Next</a>]
                                </div>
                            </td>
						</td>
					</table>
					<div style=\"padding:10px 15px;text-align:left;display:".($this->commission_hash ? "block" : "none")."\" id=\"commission_save_btn{$this->popup_id}\">
						".$this->form->button("value=Save Commission", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_commission');")."
						&nbsp;&nbsp;".(!$this->current_product['rm_lock'] ?
						$this->form->button("value=Delete Commission", "onClick=if(confirm('Are you sure you want to delete this commission rule? This action CANNOT be undone!')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_commission', 'delete=1');}") : NULL)."
					</div>
				</div>
				<div style=\"padding:10px 15px;text-align:left;\"></div>".$this->form->close_form();
				$this->content['jscript'][] = "setTimeout('DateInput(\'effective_date\', ".($this->current_commission['effective_date'] && strtotime($this->current_commission['effective_date']) > strtotime(date("2006-01-01")) ? 'true' : 'false').", \'YYYY-MM-DD\', \'".($this->commission_hash && $this->current_commission['effective_date'] && strtotime($this->current_commission['effective_date']) > strtotime(date("2006-01-01")) ? $this->current_commission['effective_date'] : NULL)."\', 1, \'commission_effective_date_holder\')', 20);";
				$this->content['jscript'][] = "setTimeout('DateInput(\'expiration_date\', ".($this->current_commission['expiration_date'] && strtotime($this->current_commission['expiration_date']) > strtotime(date("2006-01-01")) ? 'true' : 'false').", \'YYYY-MM-DD\', \'".($this->commission_hash && $this->current_commission['expiration_date'] && strtotime($this->current_commission['expiration_date']) > strtotime(date("2006-01-01")) ? $this->current_commission['expiration_date'] : NULL)."\', 1, \'commission_expiration_date_holder\')', 41);";
			} elseif ($type == 'overhead') {
				$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Overhead Rules" : "Create A New Overhead Rule");

				$tbl .= $this->form->form_tag().$this->form->hidden(array('popup_id' => $this->popup_id, 'overhead_hash' => $this->overhead_hash))."
				<div class=\"panel\" id=\"main_table{$this->popup_id}\">
					".($valid ? "
					<h3 style=\"margin-bottom:5px;color:#00477f\">Edit Rule : ".$this->current_overhead['name']."</h3>" : NULL)."
					<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
						<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
							<p id=\"feedback_message{$this->popup_id}\"></p>
					</div>
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:10px;vertical-align:top;\" id=\"err1{$this->popup_id}\">Overhead Name: *</td>
							<td style=\"background-color:#ffffff;vertical-align:top;padding-top:10px\">".$this->form->text_box("name=name", "value=".$this->current_overhead['name'], "maxlength=128", "size=45")."</td>
						</tr>
						<tr>
							<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\">
								Rule Type: *
							</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\">
								".($this->overhead_hash ?
									$this->overhead_type[$this->current_overhead['type']] : $this->form->select("type", $otype_name, $this->current_overhead['type'], $otype_code, "onChange=if(this.options[this.selectedIndex].value=='C'){toggle_display('overhead_customer_holder', 'block');}else{toggle_display('overhead_customer_holder', 'none');}", "blank=1"))."
								<div style=\"padding-top:5px;display:".($this->current_overhead['type'] == 'C' ? "block" : "none").";margin-left:15px;\" id=\"overhead_customer_holder\" >
									<span id=\"err2{$this->popup_id}\">Customer:</span>
									".($this->commission_hash ?
										$this->current_overhead['customer_name'] :
										  $this->form->text_box("name=customer",
																"value=".($this->current_overhead['type'] == 'C' ? $this->current_overhead['customer_name'] : NULL),
																"autocomplete=off",
																"size=30",
																"onFocus=position_element($('keyResults'), findPos(this, 'top')+20, findPos(this, 'left'));if($F('customer_hash')==''){clear_values('customer');}key_call('proposals', 'customer', 'customer_hash', 1);",
																"onBlur=vtobj.reset_keyResults();key_clear();",
																"onKeyDown=clear_values('customer_hash');").
									$this->form->hidden(array("customer_hash" => ($this->current_overhead['type'] == 'C' ? $this->current_overhead['customer_hash'] : NULL))))."
								</div>
								<div style=\"padding-top:10px;margin-left:15px;\">".$this->form->checkbox("name=active", "value=1", ($this->current_overhead['active'] ? "checked" : NULL), (!$this->overhead_hash ? "checked" : NULL))."&nbsp;Active?&nbsp;</div>
							</td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2b{$this->popup_id}\">Rate:</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\">".$this->form->text_box("name=rate", "value=".($this->current_overhead['rate'] ? $this->current_overhead['rate'] * 100 : NULL), "size=3", "style=text-align:right;")."&nbsp;%</td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2c{$this->popup_id}\">Apply To:</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\">".$this->form->select("apply_to", array("Sell Amount", "Cost Amount"), $this->current_overhead['apply_to'], array('S', 'C'), "blank=1")."</td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2a{$this->popup_id}\">Effective Date:</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\" id=\"overhead_effective_date_holder\"></td>
						</tr>
						<tr>
							<td style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2b{$this->popup_id}\">Expiration Date:</td>
							<td style=\"background-color:#ffffff;vertical-align:top;\" id=\"overhead_expiration_date_holder\"></td>
						</tr>
					</table>
					<div style=\"padding:10px 15px;text-align:left;display:block\">
						".$this->form->button("value=Save Overhead", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_overhead');")."
						&nbsp;&nbsp;".
						$this->form->button("value=Delete Overhead", "onClick=if(confirm('Are you sure you want to delete this overhead rule? This action CANNOT be undone!')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_overhead', 'delete=1');}")."
					</div>
				</div>
				<div style=\"padding:10px 15px;text-align:left;\"></div>".$this->form->close_form();
				$this->content['jscript'][] = "setTimeout('DateInput(\'effective_date\', ".($this->current_overhead['effective_date'] && strtotime($this->current_overhead['effective_date']) > strtotime(date("2006-01-01")) ? 'true' : 'false').", \'YYYY-MM-DD\', \'".($this->overhead_hash && $this->current_overhead['effective_date'] && strtotime($this->current_overhead['effective_date']) > strtotime(date("2006-01-01")) ? $this->current_overhead['effective_date'] : NULL)."\', 1, \'overhead_effective_date_holder\')', 20);";
				$this->content['jscript'][] = "setTimeout('DateInput(\'expiration_date\', ".($this->current_overhead['expiration_date'] && strtotime($this->current_overhead['expiration_date']) > strtotime(date("2006-01-01")) ? 'true' : 'false').", \'YYYY-MM-DD\', \'".($this->overhead_hash && $this->current_overhead['expiration_date'] && strtotime($this->current_overhead['expiration_date']) > strtotime(date("2006-01-01")) ? $this->current_overhead['expiration_date'] : NULL)."\', 1, \'overhead_expiration_date_holder\')', 41);";
			}

			$this->content['popup_controls']["cmdTable"] = $tbl;
			return;
		} else {
			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
				<tr>
					<td style=\"padding:0;font-weight:bold;background-color:#efefef;padding:15px;\">
						<h5 style=\"margin-bottom:5px;color:#00477f;margin-top:0;\">
							Company Commission Rules
						</h5>
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td style=\"padding:0px;\">
									 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
										<tr>
											<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"3\">".($this->total_commissions ? "
												You have ".$this->total_commissions." Commission Rules." : NULL)."
												<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">
                                                    [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'commission_form', 'popup_id=main_popup', 'action=addnew', 'type=commission');\" class=\"link_standard\">Add New Commission Rule</a></small>]
												</div>
											</td>
										</tr>
										<tr class=\"thead\" style=\"font-weight:bold;\">
											<td style=\"width:30%;\">Rule</td>
											<td style=\"width:50%;\">Type</td>
											<td style=\"width:20%;\">Active</td>
										</tr>";
										for ($i = 0; $i < $this->total_commissions; $i++) {
											$b++;
											$tbl .= "
											<tr onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'commission_form', 'popup_id=main_popup', 'action=addnew', 'commission_hash=".$this->commissions[$i]['commission_hash']."', 'type=commission');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\">
												<td>".$this->commissions[$i]['name']."</td>
												<td>".$this->commission_type[$this->commissions[$i]['type']].($this->commissions[$i]['type'] == 'C' ?
													" (".(strlen($this->commissions[$i]['customer_name']) > 25 ?
														substr($this->commissions[$i]['customer_name'], 0, 23)."..." : $this->commissions[$i]['customer_name']).")" : NULL)."</td>
												<td style=\"padding-left:20px;\">".($this->commissions[$i]['active'] ? 'Y' : NULL)."</td>
											</tr>".($b < $this->total_commissions ? "
											<tr>
												<td style=\"background-color:#cccccc;\" colspan=\"3\"></td>
											</tr>" : NULL);
										}
										if (!$this->total_commissions)
											$tbl .= "
											<tr>
												<td colspan=\"3\">You have no commission rules defined for your organization.</td>
											</tr>";

							$tbl .= "
									</table>
								</td>
							</tr>
						</table>
						<h5 style=\"margin-bottom:5px;color:#00477f;margin-top:15px;\">
							Company Overhead Rules
						</h5>
						<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;margin-top:0;\" class=\"main_tab_table\">
							<tr>
								<td style=\"padding:0;\">
									 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
										<tr>
											<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"3\">".($this->total_overhead ? "
												You have ".$this->total_overhead." Overhead Rules." : NULL)."
												<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">
													[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'commission_form', 'popup_id=main_popup', 'action=addnew', 'type=overhead');\" class=\"link_standard\">Create New Overhead Rule</a></small>]
												</div>
											</td>
										</tr>
										<tr class=\"thead\" style=\"font-weight:bold;\">
											<td style=\"width:30%;\">Rule</td>
											<td style=\"width:50%;\">Type</td>
											<td style=\"width:20%;\">Active</td>
										</tr>";
										$b = 0;
										for ($i = 0; $i < $this->total_overhead; $i++) {
											$b++;
											$tbl .= "
											<tr onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'commission_form', 'popup_id=main_popup', 'action=addnew', 'overhead_hash=".$this->overhead[$i]['overhead_hash']."', 'type=overhead');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\">
												<td>".$this->overhead[$i]['name']."</td>
												<td>".$this->overhead_type[$this->overhead[$i]['type']].($this->overhead[$i]['type'] == 'C' ?
													" (".(strlen($this->overhead[$i]['customer_name']) > 25 ?
														substr($this->overhead[$i]['customer_name'], 0, 23)."..." : $this->overhead[$i]['customer_name']).")" : NULL)."</td>
												<td style=\"padding-left:20px;\">".($this->overhead[$i]['active'] ? 'Y' : NULL)."</td>
											</tr>".($b < $this->total_overhead ? "
											<tr>
												<td style=\"background-color:#cccccc;\" colspan=\"3\"></td>
											</tr>" : NULL);
										}
										if (!$this->total_overhead)
											$tbl .= "
											<tr>
												<td colspan=\"3\">You have no overhead rules defined for your organization.</td>
											</tr>";

							$tbl .= "
									</table>
								</td>
							</tr>
						</table>
						";

			if ($local)
				return $tbl;
			else
				$this->content['html']['tcontent2_6'] = $tbl;
		}
	}

	function fetch_product_record($product_hash) {
		$result = $this->db->query("SELECT *
									FROM `vendor_products`
									WHERE `product_hash` = '$product_hash'");
		if ($row = $this->db->fetch_assoc($result)) {
			$this->current_product = $row;
			$this->product_hash = $product_hash;

			if ($this->current_product['product_taxable']) {
				$r = $this->db->query("SELECT `tax_hash`
									   FROM `product_tax`
									   WHERE `product_hash` = '$product_hash'");
				while ($row2 = $this->db->fetch_assoc($r))
					$this->current_product['tax_hash'][] = $row2['tax_hash'];

			}
			$r = $this->db->query("SELECT accounts.account_no , accounts.account_name ,
								  (
								  	SELECT accounts.account_no
									FROM accounts
									WHERE accounts.account_hash = '".$this->current_product['product_income_account']."'
								  ) AS prod_inc_acct_no ,
								  (
								  	SELECT accounts.account_name
									FROM accounts
									WHERE accounts.account_hash = '".$this->current_product['product_income_account']."'
								  ) AS prod_inc_acct_name
								  FROM `vendor_products`
								  LEFT JOIN accounts ON accounts.account_hash = vendor_products.product_expense_account
								  WHERE vendor_products.product_hash = '".$this->product_hash."'");
			$this->current_product['income_acct_no'] = $this->db->result($r, 0, 'prod_inc_acct_no');
			$this->current_product['income_acct_name'] = $this->db->result($r, 0, 'prod_inc_acct_name');
			$this->current_product['expense_acct_no'] = $this->db->result($r, 0, 'account_no');
			$this->current_product['expense_acct_name'] = $this->db->result($r, 0, 'account_name');

			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `line_items`
										WHERE `product_hash` = '".$this->product_hash."' AND `invoice_hash` != ''");
			if ($this->db->result($result))
				$this->current_product['acct_lock'] = 1;

			$result = $this->db->query("SELECT COUNT(*) AS Total
										FROM `line_items`
										WHERE `product_hash` = '".$this->product_hash."'");
			if ($this->db->result($result))
				$this->current_product['rm_lock'] = 1;
			return true;
		}

		return false;
	}

	function fetch_products($start, $end=NULL, $order_by=NULL, $order_dir=NULL) {
		if (!$end)
			$end = SUB_PAGNATION_NUM;

		$this->product_info = array();
		$result = $this->db->query("SELECT `product_name` , `product_hash` , `product_active`
									FROM `vendor_products`
									WHERE ISNULL(`vendor_hash`)".($order_by ? "
									ORDER BY $order_by ".($order_dir ? $order_dir : "ASC") : NULL)."
									LIMIT $start , $end");
        writetest(MAIN_PAGNATION_NUM);
		while ($row = $this->db->fetch_assoc($result))
			array_push($this->product_info, $row);

	}

	function doit_product() {
		if ($product_hash = $_POST['product_hash']) {
			$valid = $this->fetch_product_record($product_hash);
			if ($valid)
				$edit_product = true;

			$action_delete = $_POST['delete'];
		}

		if ($edit_product && $action_delete) {
			$this->db->query("DELETE FROM `vendor_products`
							  WHERE `product_hash` = '$product_hash'");

			$this->content['page_feedback'] = "Your product has been deleted.";
			unset($this->ajax_vars, $this->current_product);

			$r = $this->db->query("SELECT COUNT(*) AS Total
								   FROM `vendor_products`
								   WHERE ISNULL(`vendor_hash`)");
			$this->total_products = $this->db->result($r);
			$this->content['action'] = 'close';
			$this->content['html']['tcontent2_4'] = $this->product_info_form(1);
			return;
		}

		if ($_POST['product_name'] && $_POST['product_income_account'] && $_POST['product_expense_account']) {
			$product['product_name'] = $_POST['product_name'];
			$product['product_active'] = $_POST['product_active'];
			$product['catalog_code'] = $_POST['catalog_code'];
			$product['separate_po'] = $_POST['separate_po'];
			$product['product_income_account'] = $_POST['product_income_account'];
			$product['product_expense_account'] = $_POST['product_expense_account'];
			$product['product_subtotal'] = $_POST['product_subtotal'];
			$product['subtotal_title'] = $_POST['subtotal_title'];
			if ($product['product_taxable'] = $_POST['product_taxable']) {
				$tax_states = $_POST['product_tax'];
				for ($i = 0; $i < count($tax_states); $i++) {
					if ($tax_states[$i])
						$tax_hash[] = $tax_states[$i];
				}
				if (!$tax_hash) {
					$this->content['error'] = 1;
					$this->content['form_return']['err']['err2_3_4'.$this->popup_id] = 1;
					$this->content['form_return']['feedback'][] = "In order to make a product taxable you must specify which states and jurisdictions collect tax for this product.";
					return;
				}
			}

			if ($this->content['error'])
				return;

			if ($edit_product) {
				if (is_array($tax_hash)) {
					for ($i = 0; $i < count($tax_hash); $i++) {
						if (!@in_array($tax_hash[$i], $this->current_product['tax_hash']))
							$this->db->query("INSERT INTO `product_tax`
											  (`product_hash` , `tax_hash`)
											  VALUES ('$product_hash' , '".$tax_hash[$i]."')");
					}
					if (is_array($this->current_product['tax_hash'])) {
						for ($i = 0; $i < count($this->current_product['tax_hash']); $i++) {
							if (!@in_array($this->current_product['tax_hash'][$i], $tax_hash))
								$this->db->query("DELETE FROM `product_tax`
												  WHERE `product_hash` = '$product_hash' AND `tax_hash` = '".$this->current_product['tax_hash'][$i]."'");
						}
					}
				} elseif (is_array($this->current_product['tax_hash']))
					$this->db->query("DELETE FROM `product_tax`
									  WHERE `product_hash` = '$product_hash'");
				if (is_array($this->current_product['tax_hash']) && !$product['product_taxable'])
					$this->db->query("DELETE FROM `product_tax`
									  WHERE `product_hash` = '$product_hash'");

				$product = $this->db->prepare_query($product, "vendor_products", 'UPDATE');
				$this->db->query("UPDATE `vendor_products`
								  SET `timestamp` = ".time()." , `last_change` = '".$this->current_hash."' , ".implode(" , ", $product)."
								  WHERE `product_hash` = '$product_hash'");
				$this->content['page_feedback'] = "Your product/service has been updated.";
			} else {
				$product_hash = md5(global_classes::get_rand_id(32, "global_classes"));
				while (global_classes::key_exists('vendor_products', 'product_hash', $product_hash))
					$product_hash = md5(global_classes::get_rand_id(32, "global_classes"));

				$product = $this->db->prepare_query($product, "vendor_products", 'INSERT');
				$this->db->query("INSERT INTO `vendor_products`
								  (`timestamp` , `last_change` , `product_hash` , ".implode(" , ", array_keys($product)).")
								  VALUES (".time()." , '".$this->current_hash."' , '$product_hash' , ".implode(" , ", array_values($product)).")");
				if ($tax_hash) {
					for ($i = 0; $i < count($tax_hash); $i++)
						$this->db->query("INSERT INTO `product_tax`
										  (`product_hash` , `tax_hash`)
										  VALUES ('$product_hash' , '".$tax_hash[$i]."')");
				}
				$this->content['page_feedback'] = "Your new product/service has been added.";
			}
			$r = $this->db->query("SELECT COUNT(*) AS Total
								   FROM `vendor_products`
								   WHERE ISNULL(`vendor_hash`)");
			$this->total_products = $this->db->result($r);

			unset($this->ajax_vars, $this->current_product);
			$this->content['action'] = 'close';
			$this->content['html']['tcontent2_4'] = $this->product_info_form(1);
			return;
		} else {
			$this->content['error'] = 1;
			$this->content['form_return']['feedback'][] = "You left some required fields blank. Please check the indicated fields and try again.";
			if (!$_POST['product_name']) $this->content['form_return']['err']['err2_3_1'.$this->popup_id] = 1;
			if (!$_POST['product_income_account']) $this->content['form_return']['err']['err2_3_2'.$this->popup_id] = 1;
			if (!$_POST['product_expense_account']) $this->content['form_return']['err']['err2_3_3'.$this->popup_id] = 1;
			return;
		}


	}

	function product_info_form($local=NULL) {
		$action = $this->ajax_vars['action'];

		if ($this->ajax_vars['product_hash']) {
			$valid = $this->fetch_product_record($this->ajax_vars['product_hash']);
			if ($valid === false)
				unset($this->ajax_vars['product_hash']);
		}

		$accounting = new accounting($this->current_hash);
		list($in_hash, $in_name) = $accounting->fetch_accounts_by_type('IN');
        list($ex_hash, $ex_name) = $accounting->fetch_accounts_by_type(array('EX', 'CG'));

        $this->fetch_tax_tables('US');

		if ($action == 'addnew') {
			$this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
			$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Product or Service" : "Create A New Product or Service");

			$tbl .= $this->form->form_tag().$this->form->hidden(array('popup_id' => $this->popup_id, 'product_hash' => $this->product_hash))."
			<div class=\"panel\" id=\"main_table{$this->popup_id}\">
				<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
					<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
						<p id=\"feedback_message{$this->popup_id}\"></p>
				</div>
				<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;padding-top:15px;vertical-align:top;width:275px;\" id=\"err2_3_1{$this->popup_id}\">Product/Service Name or Description: *</td>
						<td style=\"background-color:#ffffff;padding-top:15px;width:425px;\">
							".$this->form->text_box("name=product_name", "value=".$this->current_product['product_name'], "size=35", "maxlength=128")."
							<div style=\"padding-top:5px;\">".$this->form->checkbox("name=product_active", "value=1", (!$this->current_product || $this->current_product['product_active'] ? "checked" : NULL))."&nbsp;Active?</div>
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" >Catalog Code:</td>
						<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=catalog_code", "value=".$this->current_product['catalog_code'], "onKeyUp=this.value=this.value.toUpperCase()", "size=5", "maxlength=8")."</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" >Cut Separate Purchase Order?</td>
						<td style=\"background-color:#ffffff;\">".$this->form->checkbox("name=separate_po", "value=1", ($this->current_product['separate_po'] ? "checked" : NULL))."</td>
					</tr>
					<tr>
						<td colspan=\"2\" style=\"background-color:#efefef;color:#00477f;font-style:italic;padding-left:15px;\">
							Please assign the income account, expense account, and tax status to be used for this product:
						</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err2_3_2{$this->popup_id}\">Income Account: *</td>
						<td style=\"background-color:#ffffff;\">".$this->form->select("product_income_account", $in_name, $this->current_product['product_income_account'], $in_hash)."</td>
					</tr>
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:9px;\" id=\"err2_3_3{$this->popup_id}\">Expense Account: *</td>
						<td style=\"background-color:#ffffff;\">".$this->form->select("product_expense_account", $ex_name, ($this->current_product['product_expense_account'] ? $this->current_product['product_expense_account'] : (defined('DEFAULT_COGS_ACCT') ? DEFAULT_COGS_ACCT : NULL)), $ex_hash)."</td>
					</tr>";

				if ($this->total_tax_rules) {
					$tbl .= "
					<tr>
						<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;\" id=\"err2_3_4{$this->popup_id}\">Taxable?</td>
						<td style=\"background-color:#ffffff;\">
                            <div style=\"float:right;margin-right:60px;font-size:92%;margin-top:8px;display:".($this->current_product['product_taxable'] ? "block" : "none")."\" id=\"product_taxable_checkall{$this->popup_id}\">
                                [<a href=\"javascript:void(0);\" onClick=\"checkall(document.getElementsByName('product_tax[]'), this.checked);\" class=\"link_standard\">check all</a>]
                            </div>
							".$this->form->checkbox("name=product_taxable",
							                        "value=1",
					                                ($this->current_product['product_taxable'] ? "checked" : NULL),
					                                "onClick=if(this.checked){toggle_display('tax_holder{$this->popup_id}', 'block');toggle_display('product_taxable_checkall{$this->popup_id}', 'block');}else{toggle_display('tax_holder{$this->popup_id}', 'none');toggle_display('product_taxable_checkall{$this->popup_id}', 'none');}")."
							<div style=\"margin-top:5px;border-top:1px solid #8f8f8f;border-left:1px solid #8f8f8f;border-bottom:1px solid #8f8f8f;width:90%;".($this->total_tax_rules > 3 ? "height:100px;overflow-y:auto;" : NULL)."display:".($this->current_product['product_taxable'] ? "block" : "none")."\" id=\"tax_holder{$this->popup_id}\">
								<table style=\"width:".($sys->total_tax_rules > 3 ? "94" : "100")."%;\" cellpadding=\"4\" cellspacing=\"1\">";
								while (list($state, $info) = each($this->tax_tables)) {
									$tbl .= "
									<tr style=\"background-color:#efefef;\">
										<td style=\"border-bottom:1px solid #8f8f8f;border-right:1px solid #8f8f8f;\">
											".$this->form->checkbox("name=product_tax[]", "value=".$info['state']['tax_hash'], ($this->current_product['product_taxable'] && @in_array($info['state']['tax_hash'], $this->current_product['tax_hash']) ? "checked" : NULL)).
											"&nbsp;&nbsp;".
											$info['state']['state_name']."
										</td>
									</tr>";
									if ($info['local']) {
										for ($i = 0; $i < count($info['local']); $i++)
											$tbl .= "
											<tr style=\"background-color:#efefef;\">
												<td style=\"padding-left:35px;border-right:1px solid #8f8f8f;font-size:95%;border-bottom:1px solid #8f8f8f;\">
													".$this->form->checkbox("name=product_tax[]", "id=tax_".$info['state']['state_name'], "value=".$info['local'][$i]['tax_hash'], ($this->current_product['product_taxable'] && @in_array($info['local'][$i]['tax_hash'], $this->current_product['tax_hash']) ? "checked" : NULL)).
													"&nbsp;&nbsp;".
													$info['local'][$i]['local']."
												</td>
											</tr>";
									}
								}
							$tbl .= "
								</table>
							</div>
						</td>
					</tr>";
				}
				$tbl .= "
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" >Sub Total?</td>
					<td style=\"background-color:#ffffff;\">".$this->form->checkbox("name=product_subtotal", "value=1", ($this->current_product['product_subtotal'] ? "checked" : NULL))."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\" >Sub Total Title:</td>
					<td style=\"background-color:#ffffff;\">".$this->form->text_box("name=subtotal_title", "value=".$this->current_product['subtotal_title'], "maxlength=64", "size=64")."</td>
				</tr>";
				$tbl .= "
				</table>
				<div style=\"padding:10px 15px;text-align:left;\">
					".$this->form->button("value=Save Product", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_product');")."
					&nbsp;&nbsp;".(!$this->current_product['rm_lock'] ?
					$this->form->button("value=Delete Product", "onClick=if(confirm('Are you sure you want to delete this product? This action CANNOT be undone!')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_product', 'delete=1');}") : NULL)."
				</div>
			</div>
			<div style=\"padding:10px 15px;text-align:left;\"></div>".$this->form->close_form();
							if (is_array($tax)) {
								while (list($state, $info) = each($tax)) {
									for ($i = 0; $i < count($info); $i++) {
										if (!$info[$i]['local'])
											$tbl .= "<div style=\"padding-bottom:5px;\">".$this->form->checkbox("name=product_tax[]", "value=".$info[$i]['tax_hash'], ($this->current_product['product_taxable'] && @in_array($info[$i]['tax_hash'], $this->current_product['tax_hash']) ? "checked" : NULL))."&nbsp;".$state."</div>";
									}
								}
							}

			$this->content['popup_controls']["cmdTable"] = $tbl;
			return;
		} else {
			$p = $this->ajax_vars['p'];
			$order = $this->ajax_vars['order'];
			$order_dir = $this->ajax_vars['order_dir'];

			$total = $this->total_products;
			$num_pages = ceil($this->total_products / SUB_PAGNATION_NUM);
			$p = (!isset($p) || $p <= 1 || $p > $num_pages) ? 1 : $p;
			$start_from = SUB_PAGNATION_NUM * ($p - 1);
			$end = $start_from + SUB_PAGNATION_NUM;
			if ($end > $this->total_products)
				$end = $this->total_products;

			$order_by_ops = array("product_name"	=>	"vendor_products.product_name");

			$this->fetch_products($start_from, NULL, $order_by_ops[($order ? $order : 'product_name')], $order_dir);

			$tbl .= "
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;margin-top:0;\" class=\"main_tab_table\">
				<tr>
					<td style=\"padding:0;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\">
							<tr>
								<td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"6\">".($this->total_products ? "
									<div style=\"float:right;font-weight:normal;padding-right:10px;\">".paginate_jscript($num_pages, $p, 'sf_loadcontent', 'cf_loadcontent', 'system_config', 'product_info_form', $order, $order_dir, "'otf=1'")."</div>
									Showing ".($start_from + 1)." - ".($start_from + SUB_PAGNATION_NUM > $this->total_products ? $this->total_products : $start_from + SUB_PAGNATION_NUM)." of ".$this->total_products." Products/Services." : NULL)."
									<div style=\"padding-left:15px;padding-top:5px;font-weight:normal;\">
										[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'product_info_form', 'popup_id=main_popup', 'action=addnew');\" class=\"link_standard\">Add New Product/Service</a></small>]
									</div>
								</td>
							</tr>
							<tr class=\"thead\" style=\"font-weight:bold;\">
								<td >
									<a href=\"javascript:void(0);\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'product_info_form', 'otf=1', 'p=$p', 'order=product_name', 'order_dir=".($order == "product_name" && $order_dir == 'ASC' ? 'DESC' : 'ASC')."');\" style=\"color:#ffffff;text-decoration:underline;\">
										Product Name</a>
									".($order == 'product_name' ?
										"&nbsp;<img src=\"images/".($order_dir == 'ASC' ? "s_asc.png" : "s_desc.png")."\">" : NULL)."
								</td>
								<td>Active</td>
							</tr>";
							for ($i = 0; $i < ($end - $start_from); $i++) {
								$b++;
								$tbl .= "
								<tr onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'product_info_form', 'popup_id=main_popup', 'action=addnew', 'product_hash=".$this->product_info[$i]['product_hash']."');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\">
									<td>".$this->product_info[$i]['product_name']."</td>
									<td style=\"padding-left:20px;\">".($this->product_info[$i]['product_active'] ? 'Y' : NULL)."</td>
								</tr>".($b < ($end - $start_from) ? "
								<tr>
									<td style=\"background-color:#cccccc;\" colspan=\"2\"></td>
								</tr>" : NULL);
							}
							if (!$this->total_products)
								$tbl .= "
								<tr>
									<td colspan=\"2\">You have no products or services defined for your organization.</td>
								</tr>";

				$tbl .= "
						</table>
					</td>
				</tr>
			</table>";

			if ($local)
				return $tbl;
			else
				$this->content['html']['tcontent2_4'] = $tbl;
		}
	}


	function tax_form($local=NULL, $country=NULL) {
		if (!$local)
            $country = $this->ajax_vars['country'];

        if ($country == 'US') {
			$this->fetch_tax_tables($country);
			if ($this->total_tax_rules) {
				$tbl .= "
				<table>";
				while (list($state, $info) = each($this->tax_tables)) {
					$tbl .= "
					<tr>
						<td style=\"width:200px;font-weight:bold;\">
							<a href=\"javascript:void(0);\" style=\"color:#000000\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_tax_rule', 'popup_id=main_popup', 'tax_hash=".$info['state']['tax_hash']."');\">
								".$info['state']['state_name']."
							</a>".(!$info['state']['valid_acct'] ? "&nbsp;
							<img src=\"images/alert.gif\" title=\"This tax rule is missing a valid chart of account!\" />" : NULL)."
						</td>
						<td style=\"width:75px;font-weight:bold;\">".($info['state']['rate'] * 100)." %</td>
					</tr>";
					if ($info['local']) {
						$tbl .= "
						<tr>
							<td>
								<div style=\"background-color:#efefef;border:1px solid #cccccc;".(count($info['local']) > 5 ? "height:".(count($info['local']) > 12 ? "125" : (count($info['local']) * 16))."px;overflow:auto;" : NULL)."\">
									<table>";
									for ($i = 0; $i < count($info['local']); $i++)
										$tbl .= "
										<tr>
											<td style=\"width:200px;padding-left:25px;\">
												--
												<a href=\"javascript:void(0);\" style=\"color:#000000\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_tax_rule', 'popup_id=main_popup', 'tax_hash=".$info['local'][$i]['tax_hash']."');\">
													".$info['local'][$i]['local']."
												</a>".(!$info['local'][$i]['valid_acct'] ? "&nbsp;
												<img src=\"images/alert.gif\" title=\"This tax rule is missing a valid chart of account!\" />" : NULL)."
											</td>
											<td style=\"width:75px;\">".($info['local'][$i]['rate'] * 100)." %</td>
										</tr>";

						$tbl .= "
									</table>
								</div>
							</td>
						</tr>";
					}
				}
			$tbl .= "
				</table>";

			} else
				$tbl .= "
				<div style=\"font-style:italic;\">There are no tax rules created.<div>";

        } elseif ($country == 'CA') {
        	global $ca_states;

        	$accounting = new accounting($this->current_hash);
            list($li_hash, $li_name) = $accounting->fetch_accounts_by_type('CL');

	        $tbl .= "
            <table style=\"background-color:#8f8f8f;\" cellspacing=\"1\" cellpadding=\"5\">
                <tr>
                    <td style=\"width:225px;background-color:#efefef\">Province</td>
                    <td style=\"width:65px;background-color:#efefef;text-align:right;\">GST</td>
                    <td style=\"width:65px;background-color:#efefef;text-align:right;\">HST</td>
                    <td style=\"width:65px;background-color:#efefef;text-align:right;\">PST</td>
                    <td style=\"width:65px;background-color:#efefef;text-align:right;\">QST</td>
                    <td style=\"width:225px;background-color:#efefef;\">Sales Tax Payable Acct</td>
                </tr>";

            while (list($code, $name) = each($ca_states)) {
            	$tax_rules = array();
            	unset($account_hash);
	            $r = $this->db->query("SELECT t1.tax_hash , t1.local , t1.deleted
	                                   FROM tax_table t1
	                                   WHERE t1.country = '$country' AND t1.state = '$code'");
	            while ($row = $this->db->fetch_assoc($r)) {
	            	if (!$row['deleted']) {
	                    if ($this->fetch_tax_rule($row['tax_hash']) == true) {
	                        $tax_rules[$this->current_tax['local']] = $this->current_tax;

	                        if (!$tax_rules[$row['local']]['account'])
	                            $tax_rules[$row['local']]['account'] = array('account_no'      =>  $this->current_tax['account_no'],
					                                                         'account_name'    =>  $this->current_tax['account_name'],
					                                                         'acct_lock'       =>  $this->current_tax['acct_lock'],
					                                                         'active'          =>  $this->current_tax['active'],
	                                                                         'label'           =>  ($this->current_tax['account_no'] ? $this->current_tax['account_no']." : " : NULL).$this->current_tax['account_name'],
					                                                         'account_hash'    =>  $this->current_tax['account_hash']);
	                    }
	            	}
	            }

            	$tbl .= "
                <tr>
                    <td id=\"td_".$code."\" style=\"width:200px;font-weight:bold;background-color:#efefef\">".(strlen($name) > 21 ?
                        substr($name, 0, 20)."..." : $name)."
                    </td>
                    <td id=\"td_gst_".$code."\" onMouseOver=\"toggle_tax_display('".$code."', 'gst', 1);\$(this).setStyle({'cursor' : 'pointer'});\" onMouseOut=\"toggle_tax_display('".$code."', 'gst');\$(this).setStyle({cursor:'default'});\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_tax_rule_ca', 'popup_id=ca_popup_win', 'state=".$code."', 'local=gst');\" style=\"width:65px;background-color:#ffffff;text-align:right;\" >
                        <div id=\"".$code."_gst\" ".(!$tax_rules['gst']['active'] ? "style=\"color:#8f8f8f;font-style:italic;\"" : NULL).">".(bccomp(trim_decimal(bcmul($tax_rules['gst']['rate'], 100, 5)), 0, 5) ?
                            trim_decimal(bcmul($tax_rules['gst']['rate'], 100, 5))."%" : NULL)."
                        </div>
                    </td>
                    <td id=\"td_hst_".$code."\" onMouseOver=\"toggle_tax_display('".$code."', 'hst', 1);\$(this).setStyle({'cursor' : 'pointer'});\" onMouseOut=\"toggle_tax_display('".$code."', 'hst');\$(this).setStyle({cursor:'default'});\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_tax_rule_ca', 'popup_id=ca_popup_win', 'state=".$code."', 'local=hst');\" style=\"width:65px;background-color:#ffffff;text-align:right;\" >
                        <div id=\"".$code."_hst\" ".(!$tax_rules['hst']['active'] ? "style=\"color:#8f8f8f;font-style:italic;\"" : NULL).">".(bccomp(trim_decimal(bcmul($tax_rules['hst']['rate'], 100, 5)), 0, 5) ?
                            trim_decimal(bcmul($tax_rules['hst']['rate'], 100, 5))."%" : NULL)."
                        </div>
                    </td>
                    <td id=\"td_pst_".$code."\" onMouseOver=\"toggle_tax_display('".$code."', 'pst', 1);\$(this).setStyle({'cursor' : 'pointer'});\" onMouseOut=\"toggle_tax_display('".$code."', 'pst');\$(this).setStyle({cursor:'default'});\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_tax_rule_ca', 'popup_id=ca_popup_win', 'state=".$code."', 'local=pst');\" style=\"width:65px;background-color:#ffffff;text-align:right;\" >
                        <div id=\"".$code."_pst\" ".(!$tax_rules['pst']['active'] ? "style=\"color:#8f8f8f;font-style:italic;\"" : NULL).">".(bccomp(trim_decimal(bcmul($tax_rules['pst']['rate'], 100, 5)), 0, 5) ?
                            trim_decimal(bcmul($tax_rules['pst']['rate'], 100, 5))."%" : NULL)."
                        </div>
                    </td>
                    <td id=\"td_qst_".$code."\" onMouseOver=\"toggle_tax_display('".$code."', 'qst', 1);\$(this).setStyle({'cursor' : 'pointer'});\" onMouseOut=\"toggle_tax_display('".$code."', 'qst');\$(this).setStyle({cursor:'default'});\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_tax_rule_ca', 'popup_id=ca_popup_win', 'state=".$code."', 'local=qst');\" style=\"width:65px;background-color:#ffffff;text-align:right;\" >
                        <div id=\"".$code."_qst\" ".(!$tax_rules['qst']['active'] ? "style=\"color:#8f8f8f;font-style:italic;\"" : NULL).">".(bccomp(trim_decimal(bcmul($tax_rules['qst']['rate'], 100, 5)), 0, 5) ?
                            trim_decimal(bcmul($tax_rules['qst']['rate'], 100, 5))."%" : NULL)."
                        </div>
                    </td>
                    <td id=\"td_account_".$code."\" style=\"background-color:#ffffff;\">
	                    <div id=\"".$code."_account_hash\" style=\"display:block;color:#8f8f8f;font-style:italic;\"></div>
	                    <div id=\"".$code."_gst_account_hash\" style=\"display:none;\">".($tax_rules['gst']['active'] ?
                            ($tax_rules['gst']['account']['account_hash'] ?
	                            (strlen($tax_rules['gst']['account']['label']) > 30 ?
			                        substr($tax_rules['gst']['account']['label'], 0, 28)."..." : $tax_rules['gst']['account']['label']) : NULL) : "<span style=\"font-style:italic;color:#8f8f8f;\">Tax Rule Inactive</span>")."
                        </div>
                        <div id=\"".$code."_hst_account_hash\" style=\"display:none;\">".($tax_rules['gst']['active'] ?
                            ($tax_rules['hst']['account']['account_hash'] ?
			                    (strlen($tax_rules['hst']['account']['label']) > 30 ?
	                                substr($tax_rules['hst']['account']['label'], 0, 28)."..." : $tax_rules['hst']['account']['label']) : NULL) : "<span style=\"font-style:italic;color:#8f8f8f;\">Tax Rule Inactive</span>")."
                        </div>
                        <div id=\"".$code."_pst_account_hash\" style=\"display:none;\">".($tax_rules['pst']['active'] ?
                            ($tax_rules['pst']['account']['account_hash'] ?
	                            (strlen($tax_rules['pst']['account']['label']) > 30 ?
	                                substr($tax_rules['pst']['account']['label'], 0, 28)."..." : $tax_rules['pst']['account']['label']) : NULL) : "<span style=\"font-style:italic;color:#8f8f8f;\">Tax Rule Inactive</span>")."
                        </div>
                        <div id=\"".$code."_qst_account_hash\" style=\"display:none;\">".($tax_rules['qst']['active'] ?
                            ($tax_rules['qst']['account']['account_hash'] ?
	                            (strlen($tax_rules['qst']['account']['label']) > 30 ?
	                                substr($tax_rules['qst']['account']['label'], 0, 28)."..." : $tax_rules['qst']['account']['label']) : NULL) : "<span style=\"font-style:italic;color:#8f8f8f;\">Tax Rule Inactive</span>")."
                        </div>
                    </td>
                </tr>";
            }
            $tbl .= "
            </table>";
        }
        $this->content['jscript'][] = "
        var tax_blur_timeout = '';
        toggle_tax_display = function() {
            var code = arguments[0];
            var rule = arguments[1];
            var el_display = arguments[2];

            var obj = code + '_' + rule;

            if (!el_display) {
                \$('td_' + rule + '_' + code).setStyle({backgroundColor : '#FFFFFF'});
                \$(code + '_' + rule + '_account_hash').hide();
                \$(code + '_account_hash').show();
            } else {
                \$(code + '_' + rule + '_account_hash').show();
                \$(code + '_account_hash').hide();
                \$('td_' + rule + '_' + code).setStyle({backgroundColor : '#FFCC00'});
            }
        }";
		if ($local)
			return $tbl;
		else
			$this->content['html']['tax_table'] = $tbl;
	}

	function edit_tax_rule() {
        global $us_states;

        $stateNames = array_values($us_states);
        $states = array_keys($us_states);

		$state_list = $states;
		$stateNames_list = $stateNames;
		$valid = $this->fetch_tax_rule($this->ajax_vars['tax_hash']);

		$this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
		$this->popup_id = $this->content['popup_controls']['popup_id'];
		$this->content['popup_controls']['popup_title'] = ($valid ? "Edit Tax Rule : ".$this->current_tax['state_name'] : "Create A New Tax Rule");
		$accounting = new accounting($this->current_hash);
		list($li_hash, $li_name) = $accounting->fetch_accounts_by_type('CL');

		$result = $this->db->query("SELECT vendor_products.product_name , vendor_products.product_hash , vendors.vendor_hash , vendors.vendor_name
									FROM `vendor_products`
									LEFT JOIN `vendors` ON vendors.vendor_hash = vendor_products.vendor_hash
									ORDER BY vendors.vendor_name , vendor_products.product_name");
		while ($row = $this->db->fetch_assoc($result)) {
			$vendor[($row['vendor_hash'] ? $row['vendor_hash'] : "DEFAULT")] = ($row['vendor_name'] ? $row['vendor_name'] : MY_COMPANY_NAME);
			$products[($row['vendor_hash'] ? $row['vendor_hash'] : "DEFAULT")][] = array("name" =>	stripslashes($row['product_name']),
																						 "hash" =>	$row['product_hash']);
		}
		while (list($vendor_hash, $product_array) = each($products)) {
			$select .= "
			<optgroup label=\"".htmlentities(stripslashes($vendor[$vendor_hash]), ENT_QUOTES)."\">";
			for ($i = 0; $i < count($product_array); $i++) {
				$select .= "
				<option value=\"".$product_array[$i]['hash']."\" ".(@in_array($product_array[$i]['hash'], $this->current_tax['product']) ? "selected=\"selected\"" : NULL).">".stripslashes($product_array[$i]['name'])."</option>";
			}
			$select .= "
			</optgroup>";
		}
		$product_select = "
		<select name=\"product_taxable[]\" class=\"txtSearch\" size=\"6\" style=\"width:400px;\" multiple>
			".$select."
		</select>";

		$tbl .= $this->form->form_tag().
		$this->form->hidden(array("tax_hash"  =>  $this->tax_hash,
		                          "popup_id"  =>  $this->popup_id,
		                          "country"   =>  'US'))."
		<div class=\"panel\" id=\"main_table{$this->popup_id}\">
			<div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message{$this->popup_id}\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\">State: *</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">".($valid ?
						$this->current_tax['state_name'].$this->form->hidden(array("state" => $this->current_tax['state'])) : $this->form->select("state", $stateNames_list, $this->current_tax['state'], $state_list, "blank=1"))."
						<div style=\"padding-top:5px;\">
							".$this->form->checkbox("name=active", "value=1", (!$valid || $this->current_tax['active'] ? "checked" : NULL))."
							Active?
						</div>
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\">Local: </td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">
						".($valid ? ($this->current_tax['local'] ? $this->current_tax['local'] : "N/A") : $this->form->text_box(
								"name=local", 
								"value=".$this->current_tax['local'], 
								"maxlength=64", 
								"onkeyUp=if(this.value){toggle_display('inherit_holder', 'block');}else{toggle_display('inherit_holder', 'none');}"))."
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\">Rate: *</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">" .
						$this->form->text_box(
    						"name=rate",
    						"value=" . bcmul($this->current_tax['rate'], 100, 5),
    						"size=5",
    						"maxlength=5",
						    "onFocus=this.select();",
    						"style=text-align:right"
						) . "&nbsp;%
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;vertical-align:top;padding-top:10px;\">County/City Maximum Tax:</td>
					<td style=\"background-color:#ffffff;padding-top:10px;\">" .
						$this->form->text_box(
    						"name=maximum",
    						"value=" .$this->current_tax['maximum'],
    						"maxlength=8"
						)."
					</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;\">Sales Tax Payable Account: *</td>
					<td style=\"background-color:#ffffff;\">".$this->form->select("account_hash", $li_name, $this->current_tax['account_hash'], $li_hash, "blank=1")."</td>
				</tr>
				<tr>
					<td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#ffffff;\">
						Taxable Products &amp; Services:
						<div style=\"padding-top:10px;display:none\" id=\"inherit_holder\">
							".$this->form->checkbox("name=inherit", "value=1", "checked")."
							&nbsp;
							<small><i>Inherit product<br />rules from state?</i></small>
						</div>
					</td>
					<td style=\"background-color:#ffffff;\">".$product_select."</td>
				</tr>
			</table>
			<div style=\"padding:10px 15px;text-align:left;\">
				".($this->p->ck(get_class($this), 'E', 'users') ? $this->form->button("value=Save Tax Rule", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_tax');") : NULL)."
				&nbsp;&nbsp;
				".($this->p->ck(get_class($this), 'E', 'users') && $valid && !$this->current_tax['lock'] ? $this->form->button("value=Delete Tax Rule", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_tax', 'delete=1');") : NULL)."
			</div>
			</div>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
		return;
	}

	function fetch_tax_rule($tax_hash) {
		global $states, $stateNames;

		$result = $this->db->query("SELECT t1.*, t2.account_name, t2.account_no
									FROM tax_table t1
									LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
									WHERE t1.tax_hash = '$tax_hash'");

		if ( $row = $this->db->fetch_assoc($result) ) {

			$this->current_tax = $row;
			if ( ! $this->current_tax['account_hash'] )
				$this->current_tax['incomplete'] = 1;

			$this->tax_hash = $tax_hash;
			$this->current_tax['state_name'] = $stateNames[ array_search( strtoupper($this->current_tax['state']), $states) ];

			$this->current_tax['product'] = array();
			$result2 = $this->db->query("SELECT t1.product_hash
										 FROM product_tax t1
										 WHERE t1.tax_hash = '$tax_hash'");
			while ( $row2 = $this->db->fetch_assoc($result2) )
				array_push($this->current_tax['product'], $row2['product_hash']);

			$result2 = $this->db->query("SELECT t1.obj_id
										 FROM tax_collected t1
										 WHERE tax_hash = '{$this->tax_hash}'
										 LIMIT 1");
			if ( $this->db->result($result2, 0, 'obj_id') )
				$this->current_tax['lock'] = 1;

			$result2 = $this->db->query("SELECT t1.obj_id
										 FROM tax_collected t1
										 WHERE t1.tax_hash = '{$this->tax_hash}' AND t1.invoice_hash != ''
										 LIMIT 1");
			if ( $this->db->result($result2) )
				$this->current_tax['acct_lock'] = 1;

			return true;
		}

		return false;
	}

	function doit_tax() {
		global $stateNames, $states;

		$country = $_POST['country'];
		if ($country == 'CA') {
			$tax_hash = $_POST['tax_hash'];
			$account_hash = $_POST['account_hash'];
			$rate = preg_replace('/[^.0-9]/', "", trim($_POST['rate'])) * 1;
			$active = $_POST['active'];
			$state = $_POST['state'];
			$local = $_POST['local'];
			$maximum = $_POST['maximum'];
            $method = $_POST['method'];
            if ($rate)
                $rate = bcmul($rate, .01, 5);

			if ($tax_hash && $method == 'rm')
                $this->db->query("UPDATE tax_table
                                  SET `deleted` = 1
                                  WHERE tax_hash = '".$tax_hash."'");
			else {
	            if (bccomp($rate, 99, 5) == 1 || bccomp($rate, 0, 5) == -1)
	                return $this->__trigger_error("The tax rate you entered is invalid. Please enter a tax rate in this form: 5.5, which would indicate .055%", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if (!$account_hash)
                    return $this->__trigger_error('Please enter a valid sales tax liability account.', E_USER_ERROR, __FILE__, __LINE__, 1);

                if ($tax_hash)
	                $this->db->query("UPDATE tax_table
	                                  SET `rate` = '".$rate."' , `account_hash` = '".$account_hash."' , `active` = '".$active."' , `deleted` = 0
					                  WHERE `tax_hash` = '".$tax_hash."'");
                else {
                	$insert = true;
                    $tax_hash = md5(global_classes::get_rand_id(32, "global_classes"));
                    while (global_classes::key_exists('tax_table', 'tax_hash', $tax_hash))
                        $tax_hash = md5(global_classes::get_rand_id(32, "global_classes"));

                    $this->db->query("INSERT INTO tax_table
                                      (`tax_hash` , `active` , `country` , `state` , `local` , `rate` , `maximum`, `account_hash`)
                                      VALUES ('$tax_hash' , '$active' , '$country' , '$state' , '$local' , '$rate' , '$maximum' , '$account_hash')");
                }
            }
            $r = $this->db->query("SELECT t1.state , t1.local , t1.rate , t1.maximum, t1.active , t2.account_no , t2.account_name , t2.account_hash
                                   FROM tax_table t1
                                   LEFT JOIN accounts t2 ON t2.account_hash = t1.account_hash
                                   WHERE t1.tax_hash = '".$tax_hash."'");
            if ($tax_update = $this->db->fetch_assoc($r)) {
            	if ($method == 'rm') {
	                $this->content['html'][$tax_update['state']."_".$tax_update['local']] = '';
	                $this->content['html'][$tax_update['state']."_".$tax_update['local']."_account_hash"] = '';
            	} else {
		            $account_name = ($tax_update['account_no'] ? $tax_update['account_no']." : " : NULL).$tax_update['account_name'];
		            $this->content['html'][$tax_update['state']."_".$tax_update['local']] = trim_decimal(bcmul($tax_update['rate'], 100, 5))."%";
		            if ($tax_update['active'] == 1)
			            $this->content['html'][$tax_update['state']."_".$tax_update['local']."_account_hash"] = ($tax_update['account_hash'] ?
																						                            (strlen($account_name) > 30 ?
																						                                substr($account_name, 0, 28)."..." : $account_name) : NULL);
		            else
		                $this->content['html'][$tax_update['state']."_".$tax_update['local']."_account_hash"] = "<span style=\"font-style:italic;color:#8f8f8f;\">Tax Rule Inactive</span>";

		            $this->content['jscript'][] = "\$('".$tax_update['state']."_".$tax_update['local']."').setStyle({color : '".($tax_update['active'] ? "#000000" : "#8f8f8f")."', 'fontStyle' : '".($tax_update['active'] ? "normal" : "italic")."'})";
            	}
            }

            $this->content['action'] = 'close';
            $this->content['page_feedback'] = "Your tax rule has been updated.";
            return;
		} elseif ($country == 'US') {
			$tax_hash = $_POST['tax_hash'];
			$delete = $_POST['delete'];
			$state = $_POST['state'];
			$local = trim($_POST['local']);
			$rate = preg_replace('/[^.0-9]/', "", $_POST['rate'])*1;
			$maximum = $_POST['maximum'];
			$active = $_POST['active'];
			$product_taxable = $_POST['product_taxable'];
			if (!$tax_hash && $local)
				$inherit = $_POST['inherit'];

			$account_hash = $_POST['account_hash'];
			$state_name = $stateNames[array_search(strtoupper($state), $states)];

			if ($delete == 1) {
				$this->db->query("DELETE tax_table.* , product_tax.*
								  FROM `tax_table`
								  LEFT JOIN `product_tax` ON product_tax.tax_hash = tax_table.tax_hash
								  WHERE tax_table.tax_hash = '$tax_hash'");
				$feedback = "Your tax rule for $state_name has been deleted.";
			} else {
				if (bccomp($rate, 99, 5) == 1 || bccomp($rate, 0, 5) == -1)
					return $this->__trigger_error("The tax rate you entered is invalid. Please enter a tax rate in this form: 5.5, which would indicate .055%", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

				$rate = bcmul($rate, .01, 5);
				if ($tax_hash) {
					$this->fetch_tax_rule($tax_hash);
					$this->db->query("UPDATE `tax_table`
									  SET `active` = '$active' , `rate` = '$rate' , `maximum` = '$maximum' , `account_hash` = '$account_hash'
									  WHERE `tax_hash` = '".$this->tax_hash."'");

					if (!$product_taxable && $this->current_tax['product'])
						$this->db->query("DELETE FROM `product_tax`
										  WHERE `tax_hash` = '".$this->tax_hash."'");
					else {
						if (!is_array($product_taxable))
							$product_taxable = array();
						if (!is_array($this->current_tax['product']))
							$this->current_tax['product'] = array();

						$to_add = array_diff($product_taxable, $this->current_tax['product']);
						$to_rm = array_diff($this->current_tax['product'], $product_taxable);

						if (is_array($to_add)) {
							$to_add = array_values($to_add);
							for ($i = 0; $i < count($to_add); $i++) {
								$this->db->query("INSERT INTO `product_tax`
												  (`product_hash` , `tax_hash`)
												  VALUES ('".$to_add[$i]."' , '".$this->tax_hash."')");
								$this->db->query("UPDATE `vendor_products`
												  SET `product_taxable` = 1
												  WHERE `product_hash` = '".$to_add[$i]."'");
							}
						}
						if (is_array($to_rm)) {
							$to_rm = array_values($to_rm);
							for ($i = 0; $i < count($to_rm); $i++)
								$this->db->query("DELETE FROM `product_tax`
												  WHERE `tax_hash` = '".$this->tax_hash."' AND `product_hash` = '".$to_rm[$i]."'");
						}
					}

				} else {
					//If this is local rule, make sure a state rule already exists
					if ($local) {
						$result = $this->db->query("SELECT COUNT(*) AS Total
													FROM `tax_table`
													WHERE `state` = '$state' AND (ISNULL(`local`) || `local` = '')");
						if ($this->db->result($result) == 0) {
							$this->content['error'] = 1;
							$this->content['form_return']['feedback'] = "Before setting up a local tax rule, please make sure you have first created a tax rule for the state.";
							return;
						}
					}

					//Check for duplications
					$result = $this->db->query("SELECT COUNT(*) AS Total
												FROM `tax_table`
												WHERE `state` = '$state' AND ".($local ? "`local` = '".$local."'" : "(ISNULL(tax_table.local) OR tax_table.local = '')"));
					if ($this->db->result($result)) {
						$this->content['error'] = 1;
						$this->content['form_return']['feedback'] = "A tax rule already exists under the state ".($local ? "and local jurisdiction" : NULL)." you indicated. Please make sure you are creating a duplicate rule.";
						return;
					} else {
						$tax_hash = md5(global_classes::get_rand_id(32, "global_classes"));
						while (global_classes::key_exists('tax_table', 'tax_hash', $tax_hash))
							$tax_hash = md5(global_classes::get_rand_id(32, "global_classes"));

						$this->db->query("INSERT INTO `tax_table`
										  (`tax_hash` , `state` , `active` , `local` , `rate` , `maximum`, `account_hash`)
										  VALUES ('$tax_hash' , '$state' , '$active' , ".($local ? "'$local'" : "NULL")." , '$rate' , '$maximum', '$account_hash')");

						for ($i = 0; $i < count($product_taxable); $i++) {
							if ($product_taxable[$i])
								$this->db->query("INSERT INTO `product_tax`
												  (`product_hash` , `tax_hash`)
												  VALUES ('".$product_taxable[$i]."' , '$tax_hash')");
						}
						if ($inherit) {
							$result = $this->db->query("SELECT `tax_hash`
														FROM `tax_table`
														WHERE `state` = '$state' AND (ISNULL(tax_table.local) OR tax_table.local = '')");
							$state_tax_hash = $this->db->result($result);

							$result = $this->db->query("SELECT `product_hash`
														FROM `product_tax`
														WHERE `tax_hash` = '$state_tax_hash'");
							while ($row = $this->db->fetch_assoc($result)) {
								if (!@in_array($row['product_hash'], $product_taxable)) {
									$this->db->query("INSERT INTO `product_tax`
													  (`tax_hash` , `product_hash`)
													  VALUES ('$tax_hash' , '".$row['product_hash']."')");
									$this->db->query("UPDATE `vendor_products`
													  SET `product_taxable` = 1
													  WHERE `product_hash` = '".$row['product_hash']."'");
								}
							}
						}
					}
				}
				$feedback = "Tax rule has been saved";
			}
			$this->content['action'] = 'close';
			$this->content['page_feedback'] = $feedback;
			$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'tax_form', 'country=$country');");
		}

		return;
	}

	function fetch_var_descr($var_name) {
		$result = $this->db->query("SELECT `var_descr`
									FROM `system_vars`
									WHERE `var_name` = '$var_name'");
		return $this->db->result($result);
	}

	function system_maintain() {
		$tbl .= "
		<div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
			<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
				<p id=\"feedback_message\"></p>
		</div>
		<table cellspacing=\"1\" cellpadding=\"5\" class=\"main_tab_table\">
			<tr>
				<td style=\"padding:0;font-weight:bold;background-color:#efefef;padding:15px;\">
					<ul id=\"sys_maintab3\" class=\"shadetabs\">
						<li class=\"selected\" ><a href=\"javascript:void(0);\" rel=\"tcontent3_1\" onClick=\"expandcontent(this);\" >Fax &amp; Email Queue</a></li>
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent3_2\" onClick=\"expandcontent(this);\" >Database</a></li>
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent3_3\" onClick=\"expandcontent(this);\" >Backup &amp; Upgrade</a></li>
						<li ><a href=\"javascript:void(0);\" rel=\"tcontent3_4\" onClick=\"expandcontent(this);\" >Licensing</a></li>
					</ul>
					<div id=\"tcontent3_1\" class=\"tabcontent\"></div>
					<div id=\"tcontent3_2\" class=\"tabcontent\"></div>
					<div id=\"tcontent3_3\" class=\"tabcontent\"></div>
					<div id=\"tcontent3_4\" class=\"tabcontent\"></div>
				</td>
			</tr>
		</table>";

		if ($this->ajax_vars['otf'])
			$this->content['html']['sys_tcontent3'] = $this->form->form_tag().$tbl.$this->form->close_form();
		else {
			$this->content["jscript"][] = "setTimeout('initializetabcontent(\'sys_maintab3\')', 200);";
			return $tbl;
		}
		return $tbl;
	}

    function exchange_table() {
    	global $country_codes, $country_names;

        $this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "View & Edit Currency Exchange Tables";
        if ($this->ajax_vars['onclose_action'])
            $this->content['popup_controls']['onclose'] = $this->ajax_vars['onclose_action'];

        $this->content['focus'] = "name";

        $this->fetch_currencies();

        $action = $this->ajax_vars['action'];
        if ($action == 'edit' && $this->ajax_vars['currency_id']) {
            if ($this->fetch_currency($this->ajax_vars['currency_id']) == false)
                $this->__trigger_error("Unable to fetch valid currency for edit.", E_USER_ERROR, __FILE__, __LINE__, 1);
        }
        $head = $this->form->form_tag().
        $this->form->hidden(array("popup_id" => $this->popup_id))."
        <div class=\"panel\" id=\"main_table{$this->popup_id}\">
            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message{$this->popup_id}\"></p>
            </div>
            <div style=\"padding:15px 0 0 15px;font-weight:bold;\"></div>
            <div id=\"exchange_tbl_holder{$this->popup_id}\">";

        if (!$this->total_currency || $action == 'add' || $action == 'edit') {
        	if (!$this->current_currency) {
	        	for ($i = 0; $i < count($this->currency); $i++) {
	                if (in_array($this->currency[$i]['country'], $country_codes))
	                    unset($country_codes[array_search($this->currency[$i]['country'], $country_codes)], $country_names[array_search($this->currency[$i]['country'], $country_codes)]);
	        	}
        	}

        	$r = $this->db->query ("SELECT obj_id AS a
								    FROM customer_invoice
									WHERE !ISNULL(currency)
									UNION
									SELECT obj_id AS a
									FROM customer_payment
									WHERE !ISNULL(currency)
									UNION
									SELECT obj_id AS a
									FROM vendor_payables
									WHERE !ISNULL(currency)
									UNION
									SELECT obj_id AS a
									FROM vendor_payment
									WHERE !ISNULL(currency)
									LIMIT 1");
        	if ($this->db->result($r, 0, 'a'))
                $currency_lock = true;

        	$tbl = (!$currency_lock || ($currency_lock && $this->current_currency['default']) ? "
        	<div style=\"float:right;margin-right:120px;margin-bottom:0px;margin-top:25px;\">".($currency_lock && $this->current_currency['default'] ? "
        	    <img src=\"images/check.gif\" />".
        	    $this->form->hidden(array('default' => 1))."
        	    <span style=\"font-style:italic;\">This is my home currency</span>" :
                $this->form->checkbox("name=default",
                                      "value=1",
        	                          ($this->current_currency['default'] || !$this->total_currency ? "checked" : NULL))."&nbsp;
                Use as my home currency")."
        	</div>" : NULL)."
        	<h3 style=\"margin-bottom:5px;color:#00477f;margin-top:0;\">".($this->current_currency ?
                "Edit Currency : ".$this->current_currency['code'] : "Add Currency")."
        	</h3>".($this->total_currency ? "
        	<div style=\"margin-top:5px;margin-bottom:5px;margin-left:25px;\">
                <small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'exchange_table', 'otf=1', 'action=view', 'popup_id={$this->popup_id}');\"><-- Back</a></small>
        	</div>" : NULL)."
            <table cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"vertical-align:top;padding-top:15px;text-align:right;background-color:#ffffff;width:35%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;\" id=\"err_1{$this->popup_id}\">
                        Currency Name:
                    </td>
                    <td style=\"padding-top:15px;background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
                        ".$this->form->text_box("name=name", "value=".$this->current_currency['name'], "size=35", "maxlength=128")."
                        <div style=\"margin-top:5px;\">
                            ".$this->form->checkbox("name=active",
        	                                        "value=1",
        	                                        ($this->current_currency['active'] || !$this->current_currency ? "checked" : NULL))."&nbsp;
                            Active?
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;\" id=\"err_2{$this->popup_id}\">
                        Country:
                    </td>
                    <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">".
                        $this->form->select("country",
                                            $country_names,
                                            ($this->current_currency['country'] ? $this->current_currency['country'] : NULL),
                                            $country_codes,
                                            "blank=1")."
                    </td>
                </tr>
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;\" id=\"err_3{$this->popup_id}\">
                        Currency Code:
                    </td>
                    <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">".($this->current_currency['code'] ?
                        $this->form->hidden(array('code' => $this->current_currency['code'])) .
                        $this->current_currency['code'] : $this->form->text_box("name=code",
								                                                "value=".$this->current_currency['code'],
								                                                "size=3",
								                                                "maxlength=3",
								                                                ($this->current_currency ? "readonly" : "onKeyUp=if(this.value){this.value=this.value.toUpperCase();}")))."
                    </td>
                </tr>
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;\" id=\"err_4{$this->popup_id}\">
                        Currency Symbol:
                    </td>
                    <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
                        ".$this->form->text_box("name=symbol", "value=".$this->current_currency['symbol'], "size=6", "maxlength=12")."
                    </td>
                </tr>
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;\" id=\"err_5{$this->popup_id}\">
                        Printed Name: <small><i>(i.e. Dollar)</i></small>
                    </td>
                    <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
                        ".$this->form->text_box("name=printed", "value=".$this->current_currency['printed'], "size=35", "maxlength=128")."
                    </td>
                </tr>
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;width:25%;border-top:1px solid #AAC8C8;border-bottom:1px solid #AAC8C8;border-left:1px solid #AAC8C8;\" id=\"err_7{$this->popup_id}\">
                        Exchange Rate:
                    </td>
                    <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;border-bottom:1px solid #AAC8C8;\">
                        ".$this->form->text_box("name=rate",
                                                "value=".rtrim(trim($this->current_currency['rate'], '0'), '.'),
                                                "size=10",
                                                "maxlength=16",
                                                "style=text-align:right;",
                                                "onFocus=this.select();")."&nbsp;%
                    </td>
                </tr>
            </table>
            <div style=\"margin-top:10px;margin-left:25px;margin-bottom:10px;text-align:right;margin-right:125px;\">
                ".$this->form->button("value=Save",
                                      "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_currency', 'currency_id=".$this->current_currency['code']."');",
                                      "style=width:75px;")."
                &nbsp;&nbsp;".($this->currency_id ? "
                    ".$this->form->button("value=Delete", "onClick=if(confirm('Are you sure you want to remove this currency from the system configuration?')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_currency', 'rm=1', 'currency_id=".$this->current_currency['code']."');}", "style=width:75px;") : NULL)."

            </div>";
        } elseif ($this->total_currency && ($action == 'view' || !$action)) {
            $tbl .= "
            <h3 style=\"margin-bottom:5px;color:#00477f;margin-top:0;\">Currency Exchange Table</h3>
            <div style=\"margin-top:5px;margin-left:25px;margin-bottom:5px;\">
                [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'exchange_table', 'otf=1', 'action=add', 'popup_id={$this->popup_id}');\">add new currency</a></small>]
            </div>
            <table style=\"background-color:#8f8f8f;width:600px;\" cellspacing=\"0\" cellpadding=\"5\">
                <tr>
                    <td style=\"font-weight:bold;width:300px;background-color:#ffffff;border-bottom:1px #8f8f8f solid;border-top:1px #8f8f8f solid;border-left:1px #8f8f8f solid;\">Currency</td>
                    <td style=\"font-weight:bold;width:200px;background-color:#ffffff;text-align:right;border-bottom:1px #8f8f8f solid;border-top:1px #8f8f8f solid;\">Code</td>
                    <td style=\"font-weight:bold;width:150px;background-color:#ffffff;text-align:right;border-bottom:1px #8f8f8f solid;border-top:1px #8f8f8f solid;\">Rate</td>
                    <td style=\"font-weight:bold;width:50px;background-color:#ffffff;text-align:right;border-bottom:1px #8f8f8f solid;border-top:1px #8f8f8f solid;border-right:1px #8f8f8f solid;\">Active</td>
                </tr>";

            for ($i = 0; $i < count($this->currency); $i++) {
            	$tbl .= "
                <tr class=\"white_bg\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className='white_bg'\" ".($this->currency[$i]['default'] ? "style=\"font-weight:bold;\" title=\"This is my home currency\"" : NULL)." onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'exchange_table', 'currency_id=".$this->currency[$i]['code']."', 'otf=1', 'action=edit', 'popup_id={$this->popup_id}');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\">
                    <td style=\"width:300px;border-left:1px #8f8f8f solid;".($i < count($this->currency) ? "border-bottom:1px #8f8f8f solid;" : NULL)."\">
                        ".$this->currency[$i]['name']."
                    </td>
                    <td style=\"width:200px;text-align:right;".($i < count($this->currency) ? "border-bottom:1px #8f8f8f solid;" : NULL)."\">
                        ".$this->currency[$i]['code']."
                    </td>
                    <td style=\"width:150px;text-align:right;".($i < count($this->currency) ? "border-bottom:1px #8f8f8f solid;" : NULL)."\">
                        ".rtrim(trim($this->currency[$i]['rate'], '0'), '.')." %
                    </td>
                    <td style=\"width:50px;text-align:center;border-right:1px #8f8f8f solid;".($i < count($this->currency) ? "border-bottom:1px #8f8f8f solid;" : NULL)."\">
                        ".($this->currency[$i]['active'] ? "Y" : "&nbsp;")."
                    </td>
                </tr>";
            }
            $tbl .= "
            </table>";
        }
        $foot = "
            </div>
        </div>".
        $this->form->close_form();

        if ($this->ajax_vars['otf']) {
        	$this->content['html']["exchange_tbl_holder{$this->popup_id}"] = $tbl;
        } else
            $this->content['popup_controls']["cmdTable"] = $head.$tbl.$foot;
    }

    function fetch_currencies() {

    	$r = $this->db->query("SELECT currency.*
    	                       FROM currency
    	                       ORDER BY country");
    	while ($row = $this->db->fetch_assoc($r))
       		$this->currency[] = $row;

        $this->total_currency = count($this->currency);
    	return;
    }

    function doit_currency() {
    	$default = $_POST['default'];
    	$name = trim($_POST['name']);
    	$active = $_POST['active'];
    	$country = $_POST['country'];
    	$code = trim($_POST['code']);
    	$symbol = trim($_POST['symbol']);
    	$printed = trim($_POST['printed']);
    	$printed_plural = trim($_POST['printed_plural']);
    	$rate = trim($_POST['rate']);
    	$currency_id = $_POST['currency_id'];
    	$rm = $_POST['rm'];

    	if ($rm) {
    		if ($currency_id) {
                if ($this->fetch_currency($currency_id) === false)
                    return $this->__trigger_error("Unable to fetch currency rule.", E_USER_NOTICE, __FILE__, __LINE__, 1);

	            $this->db->query("DELETE FROM currency
	                              WHERE code = $currency_id");
                update_sys_data('FUNCTIONAL_CURRENCY', '');


                if ($this->current_currency['default'] == 1)
                    $this->content['html']['home_currency'] = "Not Defined";

    		} else {
                $this->content['error'] = 1;
                $this->content['form_return']['feedback'] = "Unable to fetch currency rule.";
                return;
            }

            $this->content['action'] = 'continue';
            $this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'exchange_table', 'popup_id=exchange_win', 'otf=1');";
            return;
    	}

    	if ($name && $country && $code && $symbol && $printed && $rate) {
    		if (strspn($rate, '0123456789.') != strlen($rate)) {
                $this->content['error'] = 1;
                $this->content['form_return']['feedback'] = "Please ensure that you have entered a valid exchange rate.";
                $this->content['form_return']['err']['err_7'.$this->popup_id] = 1;

                return;
    		}
    		if ($default)
                $rate = 1;

    		if ($currency_id) {
    			if ($this->fetch_currency($currency_id) === false)
                    return $this->__trigger_error("Unable to fetch currency rule.", E_USER_NOTICE, __FILE__, __LINE__, 1);

                ($name != $this->current_currency['name'] ? $sql[] = "`name` = '$name'" : NULL);
                ($active != $this->current_currency['active'] ? $sql[] = "`active` = '$active'" : NULL);
                ($country != $this->current_currency['country'] ? $sql[] = "`country` = '$country'" : NULL);
                ($code != $this->current_currency['code'] ? $sql[] = "`code` = '$code'" : NULL);
                ($symbol != $this->current_currency['symbol'] ? $sql[] = "`symbol` = '$symbol'" : NULL);
                ($printed != $this->current_currency['printed'] ? $sql[] = "`printed` = '$printed'" : NULL);
                ($printed_plural != $this->current_currency['printed_plural'] ? $sql[] = "`printed_plural` = '$printed_plural'" : NULL);
                ($rate != $this->current_currency['rate'] ? $sql[] = "`rate` = '$rate'" : NULL);

                if (is_array($sql))
                    $sql_p = implode(" , ", $sql);

                $this->db->query("UPDATE currency
                                  SET $sql_p
                                  WHERE code = '".$this->currency_id."'");
                $obj_id = $this->currency_id;

                $feedback = "Currency has been updated";
    		} else {
    			$r = $this->db->query("SELECT COUNT(*) AS Total
    			                       FROM currency
    			                       WHERE code = '$code'");
                if ($this->db->result($r) > 0)
                    return $this->__trigger_error("A currency already exists with the code you indicated. Please ensure you are not creating a duplication.", E_USER_NOTICE, __FILE__, __LINE__, 1);

        		$this->db->query("INSERT INTO currency
        		                  (`name` , `code` , `country` , `symbol` , `printed` , `printed_plural` , `rate`)
        		                  VALUES('$name' , '$code' , '$country' , '$symbol' , '$printed' , '$printed_plural' , '$rate')");

                $feedback = "Currency has been created";
    		}

    		if ($default) {
                if (!$this->home_currency) {
                    $this->db->query("UPDATE currency
                                      SET `default` = 1 , `rate` = 1.0
                                      WHERE code = '$code'");
                    update_sys_data('FUNCTIONAL_CURRENCY', $code);
                }
                elseif ($this->home_currency && $this->home_currency['code'] != $code) {
                    $this->db->query("UPDATE currency
                                      SET `default` = 1 , `rate` = 1.0
                                      WHERE `code` = '$code'");
                    $this->db->query("UPDATE currency
                                      SET `default` = 0
                                      WHERE `code` = '".$this->home_currency['code']."'");
                    update_sys_data('FUNCTIONAL_CURRENCY', $this->home_currency['code']);
                }
                $this->content['html']['home_currency'] = $name;
    		}

    		$this->content['page_feedback'] = $feedback;
    		$this->content['action'] = 'continue';
    		$this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'exchange_table', 'popup_id=exchange_win', 'otf=1');";

    		return;
    	} else {
            $this->content['error'] = 1;
            $this->content['form_return']['feedback'] = "Please make sure you have entered the required fields.";
    		if (!$name) $this->content['form_return']['err']['err_1'.$this->popup_id] = 1;
    		if (!$rate) $this->content['form_return']['err']['err_7'.$this->popup_id] = 1;
    		if (!$country) $this->content['form_return']['err']['err_2'.$this->popup_id] = 1;
    		if (!$code) $this->content['form_return']['err']['err_3'.$this->popup_id] = 1;
    		if (!$symbol) $this->content['form_return']['err']['err_4'.$this->popup_id] = 1;
    		if (!$printed) $this->content['form_return']['err']['err_5'.$this->popup_id] = 1;

    		return;
    	}

    }

    function fetch_currency($code) {
    	if (!$code)
    	    return false;

        $r = $this->db->query("SELECT currency.*
                               FROM currency
                               WHERE code = '$code'");
        if ($row = $this->db->fetch_assoc($r)) {
            $this->current_currency = $row;
            $this->currency_id = $code;
            $this->currency_rate = $row['rate'];

            return true;
        }

        return false;
    }

    function fetch_customer_currency($customer_hash) {

        $r = $this->db->query("SELECT currency.*
                               FROM currency
                               LEFT JOIN customers ON customers.currency = currency.code
                               WHERE customers.customer_hash = '$customer_hash'");
    	if ($row = $this->db->fetch_assoc($r))
            return $row;

    	return false;
    }

    function proposal_status_list() {

    	if ($_POST['exec'] == 1) {
    		$name = $_POST['status_name'];
    		$color = $_POST['status_color'];
    		$bold = $_POST['status_bold'];
    		$id = $_POST['id'];
    		$popup_id = $_POST['popup_id'];
    		$rm = $_POST['rm'];

            $this->content['action'] = 'continue';

            $proposal_status = fetch_sys_var('PROPOSAL_STATUS_LIST');
            $proposal_status = __unserialize(stripslashes($proposal_status));
            if ($rm == 1 && is_numeric($id)) {
            	unset($proposal_status[$id]);
            	$proposal_status = array_values($proposal_status);

            	update_sys_data('PROPOSAL_STATUS_LIST', addslashes(serialize($proposal_status)));

            	$this->content['page_feedback'] = "Proposal status list has been updated.";
            	$this->content['jscript'][] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'proposal_status_list', 'popup_id=$popup_id', 'otf=1');";
            	return;
            }

    		if (!is_array($proposal_status))
                $proposal_status = array();
    		else {
    			for ($i = 0; $i < count($proposal_status); $i++) {
    				if ($proposal_status[$i]['tag'] == $name && (!is_numeric($id) || (is_numeric($id) && $i != $id)))
    				    return $this->content['jscript'][] = "alert('You already have a status option under the same name. No duplicates!');";
    			}
    		}

    		if (is_numeric($id))
                $proposal_status[$id] = array('tag'    =>  $name,
		                                     'color'  =>  $color,
		                                     'bold'   =>  $bold);
    		else
                array_push($proposal_status, array('tag'    =>  $name,
		                                         'color'  =>  $color,
		                                         'bold'   =>  $bold));

            $order = array();
            $status = array();
            foreach ($proposal_status as $i => $tag) {
            	if (trim($tag['tag']))
                    $order[$i] = trim($tag['tag']);
            }

            natsort($order);
            foreach ($order as $key => $val)
            	$status[] = $proposal_status[$key];


            update_sys_data('PROPOSAL_STATUS_LIST', addslashes(serialize($status)));

            $this->content['page_feedback'] = "Proposal status list has been updated.";
            $this->content['jscript'][] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'proposal_status_list', 'popup_id=$popup_id', 'otf=1');";
            return;
    	}
        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Edit List : Proposal Status Options";
        $this->content['popup_controls']['popup_width'] = "450px;";
        $this->content['popup_controls']['popup_height'] = "350px;";
        $this->content['popup_controls']['popup_resize'] = 0;
        $this->content['focus'] = "name";

        $action = $this->ajax_vars['action'];
        $otf = $this->ajax_vars['otf'];

        if ($action == 'add') {
            if ($color_palate = fetch_sys_var('color_palate'))
                $color_palate = unserialize(base64_decode($color_palate));

            if ($proposal_status = fetch_sys_var('PROPOSAL_STATUS_LIST'))
                $proposal_status = __unserialize(stripslashes($proposal_status));

            if (is_numeric($this->ajax_vars['id']) && is_array($proposal_status))
                $current_stat = $proposal_status[$this->ajax_vars['id']];


            for ($i = 0; $i < count($color_palate); $i++)
                $s .= "<option style=\"background-color:".$color_palate[$i]."\" value=\"".$color_palate[$i]."\" ".($current_stat && $current_stat['color'] == $color_palate[$i] ? "selected" : NULL).">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>";


            $inner = "
            <div style=\"margin-bottom:5px;\">
                <div style=\"margin-bottom:10px;\">
                    <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'proposal_status_list', 'action=view', 'otf=1', 'popup_id={$this->popup_id}')\"><-- back</a>
                </div>
                <table>
                    <tr>
                        <td style=\"text-align:right;padding-bottom:5px;\">Status: </td>
                        <td style=\"padding-bottom:5px;\">".$this->form->text_box("name=status_name", "value=".$current_stat['tag'], "maxlength=32")."</td>
                    </tr>
                    <tr>
                        <td style=\"text-align:right;padding-bottom:5px;\">Color: </td>
                        <td style=\"padding-bottom:5px;\"><select name=\"status_color\" class=\"txtSearch\">".$s."</select></td>
                    </tr>
                    <tr>
                        <td style=\"text-align:right;padding-bottom:5px;\">Bold: </td>
                        <td style=\"padding-bottom:5px;\">".$this->form->select("status_bold", array("No", "Yes"), $current_stat['bold'], array(0, 1), "blank=1")."</td>
                    </tr>
                </table>
                <div style=\"float:right;margin-top:0;margin-right:25px;\">
                    ".$this->form->button("value=Save", "onClick=if(\$F('status_name')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=proposal_status_list', 'exec=1', 'id=".$this->ajax_vars['id']."')}else{alert('Please make sure you have included a name for the status option to be called.')}").($this->ajax_vars['id'] ?
                        "&nbsp;&nbsp;".$this->form->button("value=Remove", "onClick=if (confirm('Are you sure you want to remove this proposal status option? By doing so you may loose the ability to search and tag your proposals using this option.')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=proposal_status_list', 'exec=1', 'id=".$this->ajax_vars['id']."', 'rm=1')}") : NULL)."
                </div>
            </div>";


        } elseif (!$action || $action == 'view') {
            $inner = "
            <div style=\"margin-bottom:10px;\">
                [<a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'proposal_status_list', 'action=add', 'otf=1', 'popup_id={$this->popup_id}')\">add new</a>]
            </div>";

	        if ($proposal_status = fetch_sys_var('PROPOSAL_STATUS_LIST'))
	            $proposal_status = __unserialize(stripslashes($proposal_status));

            if (count($proposal_status) && $proposal_status[0]) {
	            for ($i = 0; $i < count($proposal_status); $i++) {
	            	if ($proposal_status[$i]['tag'])
		                $inner .= "
		                <div style=\"margin-bottom:5px;margin-left:15px;\">
		                    -
		                    <span style=\"color:".($proposal_status[$i]['color'] ? $proposal_status[$i]['color'] : "#000").";".($proposal_status[$i]['bold'] ? "font-weight:bold;" : NULL)."\">
		                        <a href=\"javascript:void(0);\" style=\"text-decoration:none;color:".($proposal_status[$i]['color'] ? $proposal_status[$i]['color'] : "#000")."\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'proposal_status_list', 'action=add', 'otf=1', 'popup_id={$this->popup_id}', 'id=".$i."')\">".$proposal_status[$i]['tag']."</a>
		                    </span>
		                </div>";
	            }
            } else
                $inner .= "<i>List is empty.</i>";
        }

        if (!$action) {
	        $tbl = $this->form->form_tag().$this->form->hidden(array('popup_id'    =>  $this->popup_id))."
	        <div class=\"panel\" id=\"main_table{$this->popup_id}\">
	            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
	                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
	                    <p id=\"feedback_message{$this->popup_id}\"></p>
	            </div>
	            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:390px;height:325px;\" class=\"smallfont\">
	                <tr>
	                    <td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;\">
	                        <div style=\"font-weight:bold;margin-bottom:5px;\">
	                            Define your proposal status options:
	                        </div>
	                        Each proposal can be assigned a particular status (i.e. On-Hold, Pending Design, etc) which is displayed within your proposal list.
	                        You may defined the status options available for each proposal here. The maximum length allowed for the option name is 32 characters.
	                        <br /><br />
	                        Keep in mind the following status options are already defined
	                        by the system and identified by the diamond color icon within your proposal list: booked, partially invoiced, fully invoiced, punchlist.
	                        <div style=\"margin-top:20px;margin-left:15px;\" id=\"list_tbl_hldr{$this->popup_id}\">";


	                    $foot .= "
                            </div>
	                    </td>
	                </tr>
	            </table>
	        </div>".
	        $this->form->close_form();

        }


        if ($otf)
            $this->content['html']["list_tbl_hldr".$this->popup_id] = $inner;
        else
            $this->content['popup_controls']["cmdTable"] = $tbl.$inner.$foot;

    }

    function correction_code_list() {
        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Edit List : Customer Correction Codes";
        $this->content['popup_controls']['popup_width'] = "650px;";
        $this->content['popup_controls']['popup_height'] = "300px;";
        $this->content['focus'] = "descr";

        $accounting = new accounting($this->current_hash);
        $total = $accounting->fetch_correction_codes();

        $action = $this->ajax_vars['action'];
        if ($from_credit = $this->ajax_vars['from_credit'])
            $this->content['popup_controls']['onclose'] = "if(\$('credit_hash')){submit_form(\$('credit_hash').form, 'customer_credits', 'exec_post', 'refresh_form', 'action=innerHTML_customer_credits', 'method=reload', 'popup_id=".$this->ajax_vars['credit_popup_id']."');}";

        $head = $this->form->form_tag().
        $this->form->hidden(array("popup_id" => $this->popup_id))."
        <div class=\"panel\" id=\"main_table{$this->popup_id}\">
            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message{$this->popup_id}\"></p>
            </div>
            <div id=\"code_tbl_holder{$this->popup_id}\">";

        if (!$total || $action == 'add' || $action == 'edit') {
            if ($this->ajax_vars['correction_hash']) {
                if ($accounting->fetch_correction_code($this->ajax_vars['correction_hash']) == false)
                    $this->__trigger_error("Unable to fetch valid correction code for edit.", E_USER_ERROR, __FILE__, __LINE__, 1);

            }

            $r = $this->db->query("SELECT `account_no` , `account_name` , `account_hash` , `account_type`
                                   FROM accounts
                                   WHERE account_type = 'IN' OR account_type = 'EX' OR account_type = 'CG'");
            while ($row = $this->db->fetch_assoc($r)) {
                $acct_order[$row['account_hash']] = ($row['account_no'] ? $row['account_no'] : $row['account_name']);
                $acct[$row['account_hash']] = ($row['account_no'] ? $row['account_no']." - " : NULL).$row['account_name'];
            }
            natsort($acct_order);
            foreach ($acct_order as $h => $v)
                $G_L_acct[$h] = $acct[$h];

            unset($acct_order, $acct);

            $tbl = "
            <h3 style=\"margin-bottom:5px;color:#00477f;margin-top:0;\">".($accounting->correction_hash ?
                "Edit Correction Code : ".$accounting->current_code['code'].$this->form->hidden(array('correction_hash' => $accounting->correction_hash)) : "Add Correction Code")."
            </h3>".($total ? "
            <div style=\"margin-top:5px;margin-bottom:5px;margin-left:25px;\">
                <small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'correction_code_list', 'otf=1', 'action=view', 'popup_id={$this->popup_id}');\"><-- Back</a></small>
            </div>" : NULL)."
            <table cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:590px;margin-top:0;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"vertical-align:top;padding-top:15px;text-align:right;background-color:#ffffff;width:35%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;\" id=\"err_1{$this->popup_id}\">
                        Description:
                    </td>
                    <td style=\"padding-top:15px;background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
                        ".$this->form->text_box("name=descr", "value=".$accounting->current_code['descr'], "size=35", "maxlength=128")."
                        <div style=\"margin-top:5px;\">
                            ".$this->form->checkbox("name=active",
                                                    "value=1",
                                                    ($accounting->current_code['active'] || !$accounting->current_code ? "checked" : NULL))."&nbsp;
                            Active?
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;\" id=\"err_2{$this->popup_id}\">
                        Correction Code:
                    </td>
                    <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
                        ".$this->form->text_box("name=code",
                                                "value=".$accounting->current_code['code'],
                                                "size=10",
                                                "maxlength=12",
                                                "onKeyUp=if(this.value){this.value=this.value.toUpperCase();}")."
                    </td>
                </tr>
                <tr>
                    <td class=\"smallfont\" style=\"text-align:right;background-color:#ffffff;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-bottom:1px solid #AAC8C8;padding-bottom:15px;vertical-align:top;padding-top:8px;\" id=\"err_3{$this->popup_id}\">
                        Income/Expense Account:
                    </td>
                    <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;border-bottom:1px solid #AAC8C8;padding-bottom:15px;vertical-align:top;padding-top:5px;\">
                        ".$this->form->select("account_hash",
                                               array_values($G_L_acct),
                                               $accounting->current_code['account_hash'],
                                               array_keys($G_L_acct))."


                    </td>
                </tr>
            </table>
            <div style=\"margin-top:10px;margin-left:25px;margin-bottom:10px;text-align:right;margin-right:125px;\">
                ".$this->form->button("value=Save", "onClick=submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_correction_code');", "style=width:75px;")."
                ".($accounting->correction_hash ? "
                    ".$this->form->button("value=Delete", "onClick=if(confirm('Are you sure you want to remove this correction code from the system configuration?')){submit_form(this.form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_correction_code', 'rm=1');}", "style=width:75px;") : NULL)."

            </div>";
        } elseif ($total && ($action == 'view' || !$action)) {
            $tbl .= "
            <h3 style=\"margin-bottom:5px;color:#00477f;margin-top:0;\">Correction Code Table</h3>
            <div style=\"margin-bottom:10px;\">
                Customer credit correction codes are used to issue a customer a credit memo. Each correction code can be used to identify the reason
                for the credit being issued, as well as the chart of account that is affected by the credit. You may create and edit your correction
                codes below.
            </div>
            <div style=\"margin-top:5px;margin-left:25px;margin-bottom:5px;\">
                [<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'correction_code_list', 'otf=1', 'action=add', 'popup_id={$this->popup_id}');\">add new code</a></small>]
            </div>
            <table style=\"background-color:#8f8f8f;width:590px;\" cellspacing=\"0\" cellpadding=\"5\">
                <tr>
                    <td style=\"font-weight:bold;width:190px;background-color:#ffffff;border-bottom:1px #8f8f8f solid;border-top:1px #8f8f8f solid;border-left:1px #8f8f8f solid;\">Description</td>
                    <td style=\"font-weight:bold;width:75px;background-color:#ffffff;text-align:right;border-bottom:1px #8f8f8f solid;border-top:1px #8f8f8f solid;\">Code</td>
                    <td style=\"font-weight:bold;width:265px;background-color:#ffffff;text-align:left;border-bottom:1px #8f8f8f solid;border-top:1px #8f8f8f solid;\">Account</td>
                    <td style=\"font-weight:bold;width:50px;background-color:#ffffff;text-align:right;border-bottom:1px #8f8f8f solid;border-top:1px #8f8f8f solid;border-right:1px #8f8f8f solid;\">Active</td>
                </tr>";

            foreach ($accounting->correction_codes as $code => $code_array) {
                $tbl .= "
                <tr class=\"white_bg\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className='white_bg'\" onClick=\"agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'correction_code_list', 'correction_hash=".$code_array['correction_hash']."', 'otf=1', 'action=edit', 'popup_id={$this->popup_id}');\">
                    <td style=\"border-left:1px #8f8f8f solid;".($i < $total ? "border-bottom:1px #8f8f8f solid;" : NULL)."\">
                        ".$code_array['descr']."
                    </td>
                    <td style=\"text-align:right;".($i < $total ? "border-bottom:1px #8f8f8f solid;" : NULL)."\">
                        ".$code_array['code']."
                    </td>
                    <td style=\"text-align:left;".($i < $total ? "border-bottom:1px #8f8f8f solid;" : NULL)."\">".($code_array['valid_account'] ? "
                        ".($code_array['account_no'] ?
                            $code_array['account_no']." : " : NULL).(strlen($code_array['account_name']) > 30 ?
                                substr($code_array['account_name'], 0, 28)."..." : $code_array['account_name']) : "<img src=\"images/alert.gif\" title=\"Invalid chart of account!\" />")."
                    </td>
                    <td style=\"text-align:right;border-right:1px #8f8f8f solid;padding-right:10px;".($i < $total ? "border-bottom:1px #8f8f8f solid;" : NULL)."\">
                        ".($code_array['active'] ? "Y" : "&nbsp;")."
                    </td>
                </tr>";
            }
            $tbl .= "
            </table>";
        }
        $foot = "
            </div>
        </div>".
        $this->form->close_form();

        if ($this->ajax_vars['otf']) {
            $this->content['html']["code_tbl_holder{$this->popup_id}"] = $tbl;
        } else
            $this->content['popup_controls']["cmdTable"] = $head.$tbl.$foot;

    }


    function doit_correction_code() {
    	$correction_hash = $_POST['correction_hash'];
    	$rm = $_POST['rm'];
        $code = $_POST['code'];
    	$descr = $_POST['descr'];
    	$active = $_POST['active'];
    	$account_hash = $_POST['account_hash'];
    	$popup_id = $_POST['popup_id'];

    	$accounting = new accounting($this->current_hash);
    	if ($correction_hash) {
	    	if ($accounting->fetch_correction_code($correction_hash) == false)
	            return $this->__trigger_error("Unable to fetch valid correction code for edit.", E_USER_NOTICE, __FILE__, __LINE__, 1);
    	}
        if ($rm) {
            $this->db->query("DELETE FROM correction_codes
                              WHERE correction_hash = '$correction_hash'");
            $this->content['page_feedback'] = "Correction code has been deleted";
            $this->content['action'] = 'continue';
            $this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'correction_code_list', 'popup_id=$popup_id', 'otf=1');";
            return;
        } else {
            if ($code && $descr && $account_hash) {
	        	if ($accounting->current_code) {
	        		($code != $accounting->current_code['code'] ? $sql[] = "`code` = '$code'" : NULL);
	        		($descr != $accounting->current_code['descr'] ? $sql[] = "`descr` = '$descr'" : NULL);
	        		($active != $accounting->current_code['active'] ? $sql[] = "`active` = '$active'" : NULL);
	        		($account_hash != $accounting->current_code['account_hash'] ? $sql[] = "`account_hash` = '$account_hash'" : NULL);

	        		if ($sql) {
		        		$this->db->query("UPDATE correction_codes
		        		                  SET ".implode(" , ", $sql)."
		        		                  WHERE correction_hash = '$correction_hash'");
                        if ($this->db->db_error)
                            return $this->__trigger_error($this->db->db_errno." : ".$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, 1);

		        		$feedback = "Correction code has been updated.";
	        		} else
                        $feedback = "No changes have been made.";

	        	} else {
	        	    $r = $this->db->query("SELECT COUNT(*) AS Total
	        	                           FROM correction_codes
	        	                           WHERE code = '$code'");
	                if ($this->db->result($r, 0, 'Total') > 0)
	                    return $this->__trigger_error("A correction code already exists under the code '".$code."'. Please choose a unique code and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1);

	                $correction_hash = md5(global_classes::get_rand_id(32, "global_classes"));
	                while (global_classes::key_exists('customer_payment', 'correction_hash', $correction_hash))
	                    $correction_hash = md5(global_classes::get_rand_id(32, "global_classes"));

	                $this->db->query("INSERT INTO correction_codes
	                                  (`timestamp` , `last_change` , `correction_hash` , `code` , `descr` , `active` , `account_hash`)
	                                  VALUES (".time()." , '".$this->current_hash."' , '$correction_hash' , '$code' , '".addslashes($descr)."' , '$active' , '$account_hash')");
                    if ($this->db->db_error)
                        return $this->__trigger_error($this->db->db_errno." : ".$this->db->db_error, E_USER_ERROR, __FILE__, __LINE__, 1);

                    $feedback = "Correction code has been added.";
	        	}

	        	$this->content['action'] = 'continue';
    	        $this->content['jscript_action'] = "agent.call('system_config', 'sf_loadcontent', 'cf_loadcontent', 'correction_code_list', 'popup_id=$popup_id', 'otf=1');";
            } else {
                if (!$code) $this->set_error('err_2'.$popup_id);
                if (!$descr) $this->set_error('err_1'.$popup_id);
                if (!$account_hash) $this->set_error('err_3'.$popup_id);
                return $this->__trigger_error("Please make sure you have completed the indicated fields below and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);
            }
        }
    }

    function fetch_currency_symbols() {

        $r = $this->db->query("SELECT currency.symbol , currency.code
                               FROM currency
                               ORDER BY country");
        while ($row = $this->db->fetch_assoc($r))
            $symbol[$row['code']] = $row['symbol'];

        return $symbol;
    }

    function silent_update() {
        $var_name = $this->ajax_vars['var_name'];
        $var_val = $this->ajax_vars['var_val'];

        $r = $this->db->query("SELECT `var_val` , `obj_id`
                               FROM system_vars
                               WHERE `var_name` = '$var_name'");
        $obj_id = $this->db->result($r, 0, 'obj_id');
        $stored_val = $this->db->result($r, 0, 'var_val');

        if ($obj_id && $var_val != $stored_val)
            $this->db->query("UPDATE system_vars
                              SET `var_val` = '$var_val'
                              WHERE `obj_id` = ".$obj_id);

    }

    function edit_tax_rule_ca() {
    	global $ca_states;

    	$ca_stateNames = array_values($ca_states);
    	$ca_stateCodes = array_keys($ca_states);

        $popup_id = $this->ajax_vars['popup_id'];
        $state = $this->ajax_vars['state'];
        $local = $this->ajax_vars['local'];

        $r = $this->db->query("SELECT t1.tax_hash , t1.deleted
                               FROM tax_table t1
                               WHERE t1.country = 'CA' AND t1.state = '$state' AND t1.local = '$local'");
        $tax_hash = $this->db->result($r, 0, 'tax_hash');
        $deleted = $this->db->result($r, 0, 'deleted');

        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Edit Tax Rule";
        $this->content['popup_controls']['popup_width'] = "425px;";
        $this->content['popup_controls']['popup_height'] = "250px;";
        $this->content['popup_controls']['popup_resize'] = 0;
        $this->content['focus'] = "rate";

        if (!$deleted && $tax_hash)
            $this->fetch_tax_rule($tax_hash);

        $accounting = new accounting($this->current_hash);
        list($li_hash, $li_name) = $accounting->fetch_accounts_by_type('CL');

        $tbl =
        $this->form->form_tag().
        $this->form->hidden(array('popup_id'    =>  $this->popup_id,
                                  'tax_hash'    =>  ($this->tax_hash ? $this->tax_hash : $tax_hash),
                                  'state'       =>  ($this->current_tax ? $this->current_tax['state'] : $state),
                                  'local'       =>  ($this->current_tax ? $this->current_tax['local'] : $local),
                                  'country'     =>  'CA'))."
        <div class=\"panel\" id=\"main_table{$this->popup_id}\">
            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message{$this->popup_id}\"></p>
            </div>
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:380px;height:200px;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;\">
                        <h5 style=\"margin-bottom:5px;color:#00477f;margin-top:0;\">".($this->current_tax ?
                            $this->current_tax['state_name']." : ".strtoupper($this->current_tax['local']) : $ca_stateNames[array_search($this->ajax_vars['state'], $ca_stateCodes)]." : ".strtoupper($this->ajax_vars['local']))."
                        </h5>
                        <div style=\"padding-top:10px;margin-left:15px;\">
                            <table cellpadding=\"4\" cellspacing=\"3\">
                                <tr>
                                    <td style=\"text-align:right;\" id=\"err1{$this->popup_id}\">Rate: </td>
                                    <td style=\"text-align:left;\">
                                        ".$this->form->text_box("name=rate",
                                                                "value=".($this->current_tax ? trim_decimal(bcmul($this->current_tax['rate'], 100, 5)) : NULL),
                                                                "style=text-align:right;",
                                                                "onFocus=this.select();",
                                                                "size=5")."&nbsp;%
                                </tr>
                                <tr>
                                    <td style=\"text-align:right;\" id=\"err2{$this->popup_id}\">Account: </td>
                                    <td style=\"text-align:left;\">
                                        ".$this->form->select("account_hash",
                                                              $li_name,
                                                              $this->current_tax['account_hash'],
                                                              $li_hash,
                                                              "blank=1",
                                                              "style=width:250px;")."
                                </tr>
                                <tr>
                                    <td style=\"text-align:right;\" id=\"err3{$this->popup_id}\">Active: </td>
                                    <td style=\"text-align:left;\">
                                        ".$this->form->checkbox("name=active",
                                                                "value=1",
                                                                (!$this->current_tax || $this->current_tax['active'] == 1 ? "checked" : NULL))."
                                </tr>
                            </table>
                            <div style=\"margin-top:15px;margin-left:15px;margin-bottom:5px;\">
                                ".$this->form->button("value=Save",
                                                      "onClick=submit_form(\$('tax_hash').form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_tax')").($this->current_tax ? "
	                                &nbsp;&nbsp;".
                                    $this->form->button("value=Delete",
	                                                    "onClick=if(confirm('Are you sure you want to remove this tax rule?')){submit_form(\$('tax_hash').form, 'system_config', 'exec_post', 'refresh_form', 'action=doit_tax', 'method=rm');}") : NULL)."
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>".
        $this->form->close_form();

        $this->content['popup_controls']['cmdTable'] = $tbl;
    }

    function user_list($local=NULL) {

    	if ( ! $local )
        	$this->fetch_users();

        $tbl = "
        <div style=\"width:700px;overflow-x:hidden;".( count($this->user_info) > 10 ? "height:225px;overflow-y:auto;" : NULL ) . "\">
            <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:683px;\">
                <tr>
                    <td style=\"background-color:#efefef;width:40%;font-weight:bold;\">Name</td>
                    <td style=\"background-color:#efefef;width:20%;font-weight:bold;\">Username</td>
                    <td style=\"background-color:#efefef;font-weight:bold;\">Lock</td>
                    <td style=\"background-color:#efefef;padding-right:5px;font-weight:bold;\" class=\"num_field\">Active</td>
                </tr>
                <tr>
                    <td colspan=\"4\" style=\"background-color:#cccccc;\"></td>
                </tr>";

        	$border = "border-bottom:1px solid #cccccc";
        	$total_active = 0;
        	$total_active_locked  = 0;
            for ( $i = 0; $i < count($this->user_info); $i++ ) {
            	
            	if($this->user_info[$i]['active'] && !$this->user_info[$i]['user_lock']){
            		$total_active += 1;
            	}else if($this->user_info[$i]['active'] && $this->user_info[$i]['user_lock']){
            		$total_active_locked += 1;
            	}

            	$b++;
            	if ( $b >= count($this->user_info) )
	            	unset($border);

                $tbl .= "
                <tr " . ( $this->p->ck(get_class($this), 'V', 'users') ? "onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_user', 'user_hash={$this->user_info[$i]['user_hash']}', 'popup_id=line_item');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
                    <td " . ( $border ? "style=\"$border\"" : NULL ) . ">" . stripslashes($this->user_info[$i]['full_name']) . "</td>
                    <td " . ( $border ? "style=\"$border\"" : NULL ) . ">{$this->user_info[$i]['user_name']}</td>
                    <td " . ( $border ? "style=\"$border\"" : NULL ) . ">" .
                    ( $this->user_info[$i]['user_lock'] ?
	                    "Y" : "&nbsp;"
                    ) . "
                    </td>
                    <td class=\"num_field\" style=\"padding-right:20px;$border\">" .
                    (
	                    $this->user_info[$i]['active'] ? "Y" : "&nbsp;"
	                ) . "
	                </td>
                </tr>";
            }

        $tbl .= "
            </table>
        </div>";

        if ( $local )
            return $tbl;

        $this->content['jscript'] = "\$('loader_users').hide();";
        $this->content['html']['sys_users_count'] = "( Total Users = " . count($this->user_info) . " Total Active Users = ". $total_active ." Total Active and Locked Users = ". $total_active_locked .")";
        $this->content['html']['sys_users'] = $tbl;
    }

    function group_list($local=NULL) {

    	if ( ! $local )
        	$this->fetch_groups();

        $tbl .= "
        <div style=\"width:700px;overflow-x:hidden;" . ( count($this->group_info) > 10 ? "height:225px;overflow-y:auto;" : NULL ) . "\">
            <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:683px;\">
                <tr>
                    <td style=\"background-color:#efefef;width:40%;font-weight:bold;\">Group Name</td>
                    <td style=\"background-color:#efefef;padding-right:5px;font-weight:bold;\" class=\"num_field\">Lock</td>
                </tr>
                <tr>
                    <td colspan=\"2\" style=\"background-color:#cccccc;\"></td>
                </tr>";

	        $border = "border-bottom:1px solid #cccccc";
            for ($i = 0; $i < count($this->group_info); $i++) {

            	$b++;
            	if ( $b >= count($this->group_info) )
	            	unset($border);

                $tbl .= "
                <tr " . ( $this->p->ck(get_class($this), 'V', 'users') ? "onClick=\"agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'edit_group', 'group_hash={$this->group_info[$i]['group_hash']}', 'popup_id=line_item');\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . " >
                    <td " . ( $border ? "style=\"$border;\"" : NULL ) . ">" . stripslashes($this->group_info[$i]['group_name']) . "</td>
                    <td class=\"num_field\" style=\"padding-right:20px;$border\">" . ( $this->group_info[$i]['group_lock'] ? "Y" : "&nbsp;" ) . "</td>
                </tr>";
            }

        $tbl .= "
            </table>
        </div>";

        if ( $local )
            return $tbl;

        $this->content['jscript'] = "\$('loader_groups').hide();";
        $this->content['html']['sys_groups_count'] = "(" . count($this->group_info) . ")";
        $this->content['html']['sys_groups'] = $tbl;
    }
}
?>