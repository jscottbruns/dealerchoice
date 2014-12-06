<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class login extends AJAX_library {

	public $force_login;
	public $err;

	function login() {
		global $db;

		if ($_SESSION['use_db'] && DB_NAME != $_SESSION['use_db']) {
			$db->select_db($_SESSION['use_db']);
		}
		
		$this->db =& $db;
		$this->form = new form;
		$this->secret_hash_padding = 'A string that is used to pad out short strings for a certain type of encryption';

		if (isset($_SESSION['id_hash']) && strlen($_SESSION['id_hash']) == 32 && isset($_SESSION[session_name()])) {
			$result = $this->db->query("SELECT t1.id_hash , t1.reload_session , t1.time AS session_time
										FROM session t1
										WHERE t1.session_id = '".$_SESSION[session_name()]."'");

			$id_hash = $this->db->result($result,0,'id_hash');
			$session_time = $this->db->result($result,0,'session_time');
			if ($reload_session = $this->db->result($result,0,'reload_session')) {
				$this->reload_session = 1;
				$this->db->query("UPDATE `session`
								  SET `reload_session` = 0
								  WHERE `id_hash` = '$id_hash'");
			}
			if ($id_hash && $id_hash == $_SESSION['id_hash']) {
				if (!defined('CRON') && $_SESSION['user_name'] != 'root' && isset($_SESSION['VARIABLE']['USER_TIMEOUT']) && (time() - $session_time) / 60 > $_SESSION['VARIABLE']['USER_TIMEOUT']) {
					$_SESSION['force_login'] = $_SESSION['login_timeout'] = $this->force_login = $this->session_timeout = true;
					return false;
				}
				//Don't update the session time if it is just the poll
				if ($_POST['aa_sfunc_args'][0] != 'poll') {
					$this->db->query("UPDATE `session`
									  SET `time` = ".time()."
									  WHERE `id_hash` = '$id_hash'");
                    setcookie("session_time",time(),time() + (($_SESSION['VARIABLE']['USER_TIMEOUT'] ? $_SESSION['VARIABLE']['USER_TIMEOUT'] : 60) * 60),COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
				}
				unset($this->force_login,$_SESSION['force_login'],$this->force_login);
				return true;
			}
			if (!defined('CRON'))
    			$_SESSION['force_login'] = $this->force_login = true;

			return false;
		}
		if (!defined('CRON'))
    		$_SESSION['force_login'] = $this->force_login = true;

		return false;
	}

	function user_login($auto_login=false) {

		if (!$auto_login && (!$_POST['user_name'] || !$_POST['password']))
			return "Missing username or password : [Error 1]";
		else {
            if ($auto_login && $_POST['user_name'])
                unset($auto_login);

			if ($auto_login) {
                $user_name = $_COOKIE['user_name'];
                $password = $crypt_pwd = $_COOKIE['autologin'];
                $remember = $_COOKIE['remember_me'];
                $secure = $_COOKIE['https'];
                if ($_COOKIE['use_db'])
                    $use_db = $_COOKIE['use_db'];

			} else {
				$user_name = strtolower($_POST['user_name']);
				$password = $_POST['password'];
				$remember = $_POST['rememberme'];
				$crypt_pwd = md5($password);
				$ajax_login = $_POST['ajax_login'];
				$secure = $_POST['ssl'];
			}
			if ($use_db = $_POST['use_db']) {
				$_SESSION['use_db'] = $use_db;
				$this->db->select_db($_SESSION['use_db']);
			}

			$result = $this->db->query("SELECT t1.user_lock , t1.id_hash ,  t1.active , t1.start_date , t1.end_date , t1.ip_restriction , t3.group_lock
								  		FROM users t1
										LEFT JOIN user_subscribe t2 ON t2.id_hash = t1.id_hash
										LEFT JOIN user_groups t3 ON t3.group_hash = t2.group_hash
								  		WHERE t1.user_name = '$user_name' AND t1.password = '$crypt_pwd'
								  		GROUP BY t1.id_hash");
            if ($this->db->db_error)
                return "A database error was encountered when trying to validate your login information.<br /><br />Error Message: ".$this->db->db_errno." : ".$this->db->db_error;
			if (!$this->db->num_rows($result))
				return "We can't seem to find a user with the username and password you have provided. Please make sure you have entered your username correctly and entered your complete password. Remember, your password is cAsE sensitive";
			elseif ($this->db->num_rows($result) >= 1) {
				$i = 0;
				while ($row = $this->db->fetch_assoc($result)) {
					if ($i == 0) {
						$id_hash = $row['id_hash'];
						$user_lock = $row['user_lock'];
						$user_active = $row['active'];
						$start_date = $row['start_date'];
						if ($start_date == '0000-00-00')
							unset($start_date);

						$end_date = $row['end_date'];
						if ($end_date == '0000-00-00')
							unset($end_date);

						$ip_restriction = $row['ip_restriction'];
						if ($user_active == 0) {
							$inactive = true;
							break;
						}
						if ($user_lock == 1)
							break;
						if ($row['group_lock'] == 1) {
							$user_lock = 1;
							break;
						}
					} elseif ($row['group_lock'] == 1) {
						$user_lock = 1;
						break;
					}

					$i++;
				}
			}

			if ($user_lock && $user_name != 'root')
				return "We're sorry but this account has been disabled by the system administrator.";
			if ($inactive && $user_name != 'root')
				return "The user account you are try to access has be disabled.";
			if ($ip_restriction && $ip_restriction != $_SERVER['REMOTE_ADDR'])
				return "The IP address you are logging in from does not match the IP address your user account is restricted to. Please contact your system administrator.";
			if (($start_date && $end_date && (strtotime(date("Y-m-d")) < strtotime($start_date) || strtotime(date("Y-m-d")) > strtotime($end_date))) || ($start_date && !$end_date && strtotime(date("Y-m-d")) > strtotime($start_date)) || ($end_date && !$start_date && strtotime(date("Y-m-d")) > strtotime($end_date)))
				return "We're sorry but this account is date restricted and its access date has now expired.";

			$result = $this->db->query("SELECT `var_val`
										FROM `system_vars`
										WHERE `var_name` = 'USER_LOGIN'");
			if ($this->db->result($result) == 0 && $user_name != 'root')
				return "We're sorry but login has been disabled. Please check with your system administrator.";

			session_regenerate_id();
			$_SESSION[session_name()] = session_id();

			$this->user_set_tokens($id_hash,$remember);
			$this->load_system_vars();

			$result = $this->db->query("SELECT `group_hash`
										FROM `user_subscribe`
										WHERE `id_hash` = '".$_SESSION['id_hash']."'");
			while ($row = $this->db->fetch_assoc($result)) {
				if ($row['group_hash'])
					$_SESSION['group'][] = $row['group_hash'];
			}

			$_SESSION['override_groups'] = explode("|",fetch_sys_var('OVERRIDE_GROUPS'));
			if (@count(@array_intersect($_SESSION['override_groups'],$_SESSION['group'])))
				$_SESSION['is_mngr'] = 1;

			//Set the users appropriate timezone
			$tz = (defined('DEFAULT_TIMEZONE') ? DEFAULT_TIMEZONE : "US/Eastern");
			$_SESSION['TZ'] = $tz;
			putenv("TZ=".$tz);

			//Load the user permissions
			$this->load_user_permissions();

			//Update the users timestamp
			$this->db->query("UPDATE `users`
							  SET `timestamp` = ".time()."
							  WHERE `id_hash` = '$id_hash'");

			unset($_SESSION['login_timeout'],$_SESSION['force_login']);
			if ($ajax_login)
				return;
			else {
				if ($_POST['d']) {
					session_write_close();
					header("Location: ".base64_decode($_POST['d']));
					exit;
				} else {
					session_write_close();
					header("Location: core/index.php");
					exit;
				}
			}
		}
	}

	function load_user_permissions() {

		$this->permission = array();
		$result = $this->db->query("SELECT `group_hash`
									FROM `user_subscribe`
									WHERE `id_hash` = '".$_SESSION['id_hash']."'");
		while ($row = $this->db->fetch_assoc($result))
			$group[] = "'".$row['group_hash']."'";

		if ($_SESSION['user_name'] == 'root')
			// Don't give root S permissions which prevent locking of proposals.  Added for Trac #1221
			$result = $this->db->query("SELECT t1.*
										FROM permissions t1
										WHERE t1.obj_id > 0 AND t1.restriction = 0");
		else
			$result = $this->db->query("SELECT permissions. *
										FROM `permissions`
										LEFT JOIN user_permissions ON user_permissions.permission_hash = permissions.permission_hash
										LEFT JOIN users ON users.id_hash = user_permissions.id_hash
										LEFT JOIN user_subscribe ON user_subscribe.id_hash = users.id_hash
										WHERE user_permissions.id_hash = '".$_SESSION['id_hash']."'".(is_array($group) ? "
										OR user_permissions.group_hash
										IN (".implode(" , ",$group).")" : NULL)."
										GROUP BY permissions.permission_hash");
		while ($row = $this->db->fetch_assoc($result))
			$_SESSION['permission'][$row['class']][($row['content'] ? $row['content'] : 'default')][] = $row['permission'];

		return;
	}

	function doit() {
		$action = $_POST['action'];
		$btn = $_POST['btn'];
		$jscript_action = $_POST['jscript_action'];

		$this->popup_id = $_POST['popup_id'];
		$this->content['popup_controls']['popup_id'] = $this->popup_id;

		if ($action)
			return $this->$action();

		if ($btn == 'login') {
			if (!$_POST['user_name'] || !$_POST['password']) {
				$this->content['error'] = 1;
				$this->content['form_return']['feedback'] = "Please make sure you enter a username and password before continuing.";
				return;
			} else {
				$_POST['ajax_login'] = true;
				if ($feedback = $this->user_login()) {
					$this->content['error'] = 1;
					$this->content['form_return']['feedback'] = $feedback;
					return;
				}
				if ($_POST['previous_user'] != $_SESSION['id_hash']) {
					$this->content['action'] = 'close';
					$this->content['jscript'] = "window.location='".LINK_ROOT."/core/index.php'";
					return;
				}
				$invoked_class = $_POST['invoked_class'];
				$invoked_method = $_POST['invoked_method'];
				$cf_func = $_POST['cf_func'];
				if (!$cf_func)
					$cf_func = 'cf_loadcontent';

				$sf_func = $_POST['sf_func'];
				if (!$sf_func)
					$sf_func = 'sf_loadcontent';

				$orig_ajax_vars = unserialize(base64_decode($_POST['orig_ajax_vars']));
				if (count($orig_ajax_vars)) {
					while (list($var,$val) = each($orig_ajax_vars))
						$ajax_vars .= ",'".$var."=".$val."'";
				}

				//If previous user is not the same as the current user, redirect to home page...

				$this->content['html']['mynameis'] = $_SESSION['my_name'];
				$this->content['action'] = 'close';
				$this->content['page_feedback'] = "Welcome to DealerChoice ".$_SESSION['my_name']."!";
				$this->content['jscript'] = "agent.poll.start();";
				$this->content['jscript_action'] = ($jscript_action ? $jscript_action : "agent.call('$invoked_class','$sf_func','$cf_func','$invoked_method'".$ajax_vars.");");
			}
		}
	}

	function load_system_vars() {
		global $db;

		$_SESSION['DB_DEFINE'] = array();
		$result = $db->query("SELECT t1.*
							  FROM system_vars t1
							  WHERE	t1.define = 1 OR t1.define_db = 1");
		while ($row = $db->fetch_assoc($result)) {
			if ($row['define'] == 1)
                $_SESSION['VARIABLE'][$row['var_name']] = $row['var_val'];
            if ($row['define_db'] == 1)
                $_SESSION['DB_DEFINE'][] = $row['var_name'];
		}

		$result = $db->query("SELECT `fiscal_year`
							  FROM `period_dates`
							  WHERE '".date("Y-m-d")."' BETWEEN `period_start` AND `period_end`");
		$_SESSION['VARIABLE']['CURRENT_FISCAL_YEAR'] = $db->result($result);

		//This means we haven't defined our periods yet
		if (!$_SESSION['VARIABLE']['CURRENT_FISCAL_YEAR'])
			$_SESSION['INCOMPLETE_PERIOD_CONFIG'] = 1;

	}

	function load_system_page_vars() {
		global $db;

        $result = $db->query("SELECT var_name , var_val
                              FROM `system_vars`
                              WHERE `dynamic` = 1");
        while ($row = $db->fetch_assoc($result))
            $_SESSION['VARIABLE'][$row['var_name']] = $row['var_val'];

	}

	function user_set_tokens($id_hash,$remember=false) {
		if (!defined('CRON')) {
			$result = $this->db->query("SELECT `full_name` , `user_name` , `password` , `password_plain` , `unique_id` , `email`
								  		FROM `users`
								  		WHERE `id_hash` = '$id_hash'");
			$row = $this->db->fetch_assoc($result);
			$session_id = session_id();

			$result = $this->db->query("SELECT COUNT(*) AS Total
								  		FROM `session`
								  		WHERE `id_hash` = '$id_hash'");
			if ($this->db->result($result)) {
				$this->db->query("UPDATE `session`
								  SET `session_id` = '".$session_id."' , `time` = ".time()."
								  WHERE `id_hash` = '".$id_hash."'");
				$this->db->query("UPDATE `user_activity`
								  SET `logout` = ".time()."
								  WHERE `id_hash` = '".$id_hash."' AND `logout` = 0");
				$this->db->query("INSERT INTO `user_activity`
								  (`id_hash` , `date` , `login` , `ip_addr`)
								  VALUES('".$id_hash."' , '".date("Y-m-d")."' , ".time()." , '".$_SERVER['REMOTE_ADDR']."')");
			} else {
				$this->db->query("INSERT INTO `session`
								  (`session_id` , `id_hash` , `time`)
								  VALUES ('".$session_id."' , '".$id_hash."' , ".time().")");
				$this->db->query("INSERT INTO `user_activity`
								  (`id_hash` , `date` , `login` , `ip_addr`)
								  VALUES('".$id_hash."' , '".date("Y-m-d")."' , ".time()." , '".$_SERVER['REMOTE_ADDR']."')");
			}

			$_SESSION['my_name'] = stripslashes($row['full_name']);
			$_SESSION['id_hash'] = $id_hash;
			$_SESSION['user_name'] = $row['user_name'];
			$_SESSION['my_email'] = $row['email'];

			if ($row['unique_id'])
				$_SESSION['unique_id'] = $row['unique_id'];

			setcookie(session_name(),$_SESSION[session_name()],time() + 259200,COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
	        setcookie("user_name",$row['user_name'],time() + 259200,COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
	        setcookie("session_time",time(),time() + (($_SESSION['VARIABLE']['USER_TIMEOUT'] ? $_SESSION['VARIABLE']['USER_TIMEOUT'] : 60) * 60),COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
	        setcookie("remember_me",1,time() + 259200,COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
			if ($_SESSION['use_db'])
				setcookie("use_db",$_SESSION['use_db'],time() + 259200,COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
			if ($_SERVER['HTTPS'])
				setcookie("https",1,time() + 259200,COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
			if ($remember)
				setcookie("autologin",md5($row['password_plain']),time() + 259200,COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));

			if ($_SESSION['user_name'] == 'root' && !$_POST['root_hide_queries'])
				$_SESSION['VARIABLE']['PUN_SHOW_QUERIES'] = 1;

		} elseif (defined('CRON')) {
            $func_args = func_get_args();
			$cron_hash = $func_args[0];
			$cron_security_hash = '055deb81af5808f363f3700c563146e4.0b047e8942f0bc720450d840927bc763';
			if ($cron_hash == md5($cron_security_hash)) {
				list($cron_id_hash) = explode('.',$cron_security_hash);
	            $_SESSION['my_name'] = "DealerChoice Messenger Service";
	            $_SESSION['id_hash'] = $cron_id_hash;
	            $_SESSION['user_name'] = 'crond';
	            $_SESSION['my_email'] = 'message.service@dealer-choice.com';

	            $this->load_system_vars();
	            return true;
			} else
                die('-1');
		}
	}

	function user_isloggedin() {
		if (isset($_SESSION['id_hash']) && strlen($_SESSION['id_hash']) == 32 && isset($_SESSION[session_name()])) {
			$result = $this->db->query("SELECT t1.id_hash , t1.time AS session_time
										FROM session t1
										WHERE t1.session_id = '".$_SESSION[session_name()]."'");
			$id_hash = $this->db->result($result,0,'id_hash');
			$session_time = $this->db->result($result,0,'session_time');
			if ($id_hash && $id_hash == $_SESSION['id_hash']) {
				if ( $_SESSION['VARIABLE']['USER_TIMEOUT'] && ( ( time() - $session_time ) / 60 > $_SESSION['VARIABLE']['USER_TIMEOUT'] ) ) {
					$this->purge_session();
                    $this->err = "For security reasons, your session was closed after ".USER_TIMEOUT." minutes of inactivity. [Error DC102]";
					return false;
				}
				$this->db->query("UPDATE `session`
								  SET `time` = ".time()."
								  WHERE `id_hash` = '$id_hash'");
                setcookie("session_time",time(),time() + (($_SESSION['VARIABLE']['USER_TIMEOUT'] ? $_SESSION['VARIABLE']['USER_TIMEOUT'] : 60) * 60),COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
				return true;
			}
			$this->purge_session();
            $this->err = "We were unable to validate your session. Please login again. If you continue to experience problems logging in, please delete the browser cookies and restart your browser.";
			return false;
		}

		return false;
	}

	function getSessionTime() {
		$result = $this->db->query("SELECT `time`
							  		FROM `session`
							  		WHERE `id_hash` = '".$_SESSION['id_hash']."'");

		return $this->db->result($result);
	}

	function user_logout($sess_id=NULL) {
		$this->logout = true;

		if (!$sess_id) {
			$local = true;
			$sess_id = $_SESSION['id_hash'];
		}
		$this->db->query("DELETE FROM session
						  WHERE session.id_hash = '$sess_id'");
		if ($this->db->db_error) {
            if (defined('CRON'))
                return "-1\n(".$this->db->db_errno.") ".$this->db->db_error;

            return false;
		}
		$this->db->query("DELETE FROM table_lock
						  WHERE table_lock.user_hash = '$sess_id'");
		$this->db->query("UPDATE `chat`
						  SET `close_time` = ".time()." , `closed` = 1
						  WHERE `user_hash` = '$sess_id'");
		$this->db->query("UPDATE `user_activity`
						  SET `logout` = ".time()."
						  WHERE `id_hash` = '$sess_id' AND `logout` = 0");
        if (!defined('CRON')) {
	        // Delete the site cookies
	        $retain_cookies = array('user_name','https','use_db','remember_me');
	        while (list($cookie_name) = each($_COOKIE)) {
	            if (!in_array($cookie_name,$retain_cookies))
	                setcookie($cookie_name,'',time() - 3600,COOKIE_PATH,COOKIE_DOMAIN,($_SERVER['HTTPS'] ? 1 : 0));
	        }

	        if ($local) {
				unset($_SESSION);
				$_SESSION = array();
				session_destroy();
			}
        }
		return true;
	}

	function Decrypt($string) {
		global $secret_hash_padding;

		$result = '';
		for($i=1; $i<=strlen($string); $i++) {
			$char = substr($string, $i-1, 1);
			$keychar = substr($secret_hash_padding, ($i % strlen($secret_hash_padding))-1, 1);
			$char = chr(ord($char)-ord($keychar));
			$result .= $char;
		}
		return $result;
	}


	function login_prompt() {
		$invoked_class = ($_POST['invoked_class'] ? $_POST['invoked_class'] : $this->ajax_vars['invoked_class']);
		$invoked_method = ($_POST['invoked_method'] ? $_POST['invoked_method'] : $this->ajax_vars['invoked_method']);
		$cf_func = ($_POST['cf_func'] ? $_POST['cf_func'] : $this->ajax_vars['cf_func']);
		$sf_func = ($_POST['sf_func'] ? $_POST['sf_func'] : $this->ajax_vars['sf_func']);
		$orig_ajax_vars = ($_POST['orig_ajax_vars'] ? $_POST['orig_ajax_vars'] : $this->ajax_vars['orig_ajax_vars']);

		$this->popup_id = $this->content['popup_controls']['popup_id'] = $this->content['popup_controls']['popup_id'] = "login_window";
		$this->content['popup_controls']['popup_title'] = "Please Log-In To Continue";
		$this->content['focus'] = 'user_name';

		$tbl = $this->form->form_tag().
		$this->form->hidden(array("popup_id" => $this->popup_id,
								  "previous_user" => $_SESSION['id_hash'],
								  "invoked_class" => $invoked_class,
								  "invoked_method" => $invoked_method,
								  "orig_ajax_vars" => $orig_ajax_vars,
								  "l"	=>	1,
								  "sf_func" => $sf_func,
								  "cf_func" => $cf_func))."
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0;\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
				<tr>
					<td style=\"width:50%;background-color:#ffffff;vertical-align:top;padding:10px;\">
						".($_SESSION['login_timeout'] ? "
						For security reasons, you were logged out after ".USER_TIMEOUT." minutes of inactivity.<br /><br />Please log in again to continue." : ($_SESSION['id_hash'] ? "
						Your session was interrupted.<br /><br />This tends to happen if you have logged into another computer or the server cleans up old session files. Please log in again to continue." : "Welcome to DealerChoice! Please enter your username and password to login.
						"))."
					</td>
					<td style=\"width:50%;background-color:#ffffff;padding:10px 0 10px 25px;width:50%;\">
						<div style=\"padding-bottom:5px;\">User Name:</div>
						".$this->form->text_box("name=user_name","value=".$_POST['user_name'],"size=25")."
						<div style=\"padding-top:15px;padding-bottom:5px;\">Password:</div>
						".$this->form->password_box("name=password","size=25","onFocus=this.value=''")."
						<div style=\"padding-top:15px;margin-left:145px;\">
							".$this->form->button("value=Go",
                    							  "id=primary",
                    							  "onClick=submit_form(\$('l').form,'login','exec_post','refresh_form','btn=login');")."
						</div>
					</td>
				</tr>
			</table>
		</div>".
		$this->form->close_form();

		$this->content['popup_controls']["cmdTable"] = $tbl;
		$this->content['jscript'] = "agent.poll.stop();";
		return;
	}

	function purge_session() {
        if ($_SESSION['id_hash'])
            $this->db->query("DELETE FROM session
                              WHERE `id_hash` = '".$_SESSION['id_hash']."'");
	}

	function load_system_definitions() {
		while (list($key,$val) = each($_SESSION['VARIABLE'])) {
		    if ($val || is_numeric($val))
		        define($key,$val);
		}
	}

	function load_database_definitions() {
        if (is_array($_SESSION['DB_DEFINE']) && count($_SESSION['DB_DEFINE']) > 0) {
            for ($i = 0; $i < count($_SESSION['DB_DEFINE']); $i++) {
                if (defined($_SESSION['DB_DEFINE'][$i]))
                    $this->db->define($_SESSION['DB_DEFINE'][$i],constant($_SESSION['DB_DEFINE'][$i]));
            }
        }
	}
}

if (!defined('CRON'))
    $login_class = new login;
?>