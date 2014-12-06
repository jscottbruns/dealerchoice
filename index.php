<?php
require_once ('core/include/common.php');

# Check the browser settings and return an error message if necessary. For Trac #1425
if ( $browser->Name == 'Safari' || $browser->Name == 'Opera' )
	$feedback = "You are using a browser that is not yet 100% supported. All features may not function correctly. Please report any problems you find using this browser to Support@dc-sysllc.com. Otherwise, please use Microsoft Internet Explorer.";
elseif ( $browser->Name == 'MSIE' && preg_match('/^[1-6]\..*/', $browser->Version) )
	$feedback = "We detected that you are using an older version of Microsoft Internet Explorer. Please ugrade to version 7 to ensure everything in DealerChoice functions properly.";
if ( $feedback )
	$feedback = wordwrap($feedback, 85, "<br />\n");

if (is_array($_GET)) {
    reset($_GET);
    $err_msg = key($_GET);
}
if (!$_SERVER['HTTPS']) {
    header("Location: ".LINK_ROOT_SECURE.($err_msg ? "?".$err_msg : NULL));
    exit;
}

if (!$_COOKIE['remember_me'] && $login_class->user_isloggedin()) {
    $login_class->user_logout();
    session_start();
}

if ($_COOKIE['https'] && !$_SERVER['HTTPS'] && fetch_sys_var('FORCE_SSL_REDIRECT') == 0) {
	if ($_GET['ssl'] == 'no')
		setcookie('https',1,time()-3600);
	else {
		header("Location: ".LINK_ROOT_SECURE.($err_msg ? "?".$err_msg : NULL));
		exit;
	}
}

if ( ( $_COOKIE['remember_me'] && $_COOKIE['autologin'] ) || isset($_POST['login_button'] ) || isset($_POST['login_button_x']) ) {

	if ( is_object($login_class) ) {

		require_once(SITE_ROOT . 'core/include/login_funcs.class.php');
		$login_class = new login;
	}

	$feedback = $login_class->user_login( ( $_COOKIE['remember_me'] && $_COOKIE['autologin'] ? 1 : NULL ) );
}
if ($db->connection_id && defined('DB_LIST')) {
    $result = $db->query("SHOW DATABASES");
    while ($row = $db->fetch_assoc($result)) {
        if ($row['Database'] != "information_schema")
            $db_perm[] = $row['Database'];

    }
    $db_config = explode(",",DB_LIST);
    $db_list = array_values(array_intersect($db_perm,$db_config));
    if (count($db_list) > 1) {
        for ($i = 0; $i < count($db_list); $i++) {
            if (ereg("dealerchoice",$db_list[$i])) {
                $db_access = 1;
                $db_list_select .= "<option value='".$db_list[$i]."' ".($_POST['use_db'] == $db_list[$i] || $_COOKIE['use_db'] == $db_list[$i] ? "selected" : NULL).">".str_replace("dealerchoice_","",$db_list[$i])."</option>";
            }
        }
    } elseif (count($db_list) == 1 && ereg("dealerchoice",$db_list[0]))
        $db_access = 1;

    if (!$db_access)
        $db_priv_error = 'Unable to connect to database. Missing database host permissions.';
}

if ($err_msg) {
	$feedback = wordwrap(base64_decode($err_msg),85,"<br />\n");
}

$tabindex = 1;
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link rel="SHORTCUT ICON" href="core/images/favicon.ico"/>
<title>DealerChoice :: Login</title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo (defined('DEFAULT_CHARSET_HEADER') ? DEFAULT_CHARSET_HEADER : 'iso-8859-1'); ?>" />
<style>
html {font-size: 125%}
/* body styles contain settings for color, background and font across the page */
body,td {font-family:arial, verdana, helvetica, tahoma;font-size: 78%; }
#holding_table{height:100%;width:801px;text-align:center}
.nav_out{background-color:#666666;cursor:hand;color:#ffffff;border-bottom:1px solid #262626;}
.nav_out a:link,a:active,a:visited{color:#ffffff;text-decoration:none}

.nav_in{background-color:#cccccc;cursor:hand;color:#333333;border-bottom:0;text-decoration:underline;}
.nav_in a:visited{color:#333333;}
.nav_in a:active{color:#333333}
.nav_in a:link{color:#333333}
.nav_in a:hover{color:#333333}
.link_standard , .link_standard a , .link_standard a:link , .link_standard a:active , .link_standard a:visited{color:#000000}
.textbox{background-color:#cccccc;}
.button {
	font-family:Verdana, Arial, Helvetica, sans-serif;
	font-size:11px;
	font-weight:800;
	color:#4D4D4D;
	background-color:#ffffff;
}

.error_msg{color:#e60000;font-size:10pt;font-weight:bold;}
</style>
<script>
function checkit() {
    var c = document.getElementById('rememberme');
    c.checked = (c.checked ? false : true);
}
function login() {
    if (document.getElementById('login_holder')) {
		var login_hldr = document.getElementById('login_holder');

		login_hldr.innerHTML = "\
			<table style='text-align:center;'>\
			    <tr>\
			        <td style='text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:70%;'>Username: </td>\
			        <td style='text-align:left'><input type='text' name='user_name' id='user_name' tabindex='<?php echo$tabindex++?>' value='<?php echo ($_POST['user_name'] ? $_POST['user_name'] : $_COOKIE['user_name']); ?>' style='width:175px' /></td>\
			    </tr>\
			    <tr>\
			        <td style='text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:70%;'>Password: </td>\
			        <td style='text-align:left;'><input type='password' name='password' tabindex='<?php echo$tabindex++?>' id='password' tabindex='2' style='width:175px' /></td>\
			    </tr>\
			    <?php
			    if ($db_list_select) { ?>
			    <tr>\
			        <td style='text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:70%;'>Database: </td>\
			        <td style='text-align:left;'><select name='use_db' style='width:185px;' tabindex='<?php echo$tabindex++?>'><?php echo $db_list_select?></select></td>\
			    </tr>\
			    <?php
			    } ?>
			    <tr>\
			        <td style='text-align:right;padding-top:5px;'><input type='checkbox' tabindex='<?php echo$tabindex++?>' name='rememberme' id='rememberme' tabindex='3' value='1' <?php echo ($_COOKIE['remember_me'] == 1 ? 'checked' : NULL); ?>/></td>\
			        <td style='text-align:left;font-family:Arial,Helvetica,sans-serif;font-size:70%;padding-top:5px;'><a href='javascript:void(0);' style='text-decoration:none;color:#000000;' onClick='checkit();'>Remember me on this computer.</a></td>\
			    </tr>\
			    <tr>\
			        <td ></td>\
			        <td style='text-align:left;padding-top:20px;'><input type='image' name='login_button' tabindex='<?php echo$tabindex++?>' src='core/images/login.gif' /></td>\
			    </tr>\
			</table>\
	    ";
		document.getElementById('<?php echo ($_POST['user_name'] || $_COOKIE['user_name'] ? "password" : "user_name"); ?>').focus();
	}
}

</script>
</head>
<body bgcolor="#efefef" topmargin="0" onLoad="login()">
<table style="width:700px;height:100%;background-color:#ffffff;" cellpadding="0" cellspacing="0" align="center">
	<tr>
		<td style="text-align:center;vertical-align:top;border-right:1px solid #8f8f8f;border-left:1px solid #8f8f8f">
        	<div style="margin-bottom:95px;margin-top:100px;"><img src="dealerchoice_logo_541.gif" alt="DealerChoice Logo" /></div>
            <table align="center">
                <tr>
                    <td style="text-align:center;">
						<form action="index.php" method="post" name=f>
<?php

						if ($_GET['root_hide_queries'])
							echo "<input type=\"hidden\" name=\"root_hide_queries\" value=\"1\" />";

						if ( $_SERVER['HTTP_HOST'] != URL_HOST && $_SERVER['HTTP_HOST'] != URL_ALIAS ) {

							echo
							"The site you are logging into ({$_SERVER['HTTP_HOST']}) is different than the URL host in the configuration file (".URL_HOST.").
							<br /><br />
							<a href=\"".LINK_ROOT."\" style=\"color:blue;\">Follow this link to log into the proper URL.</a>
							<br /><br />";

						} else {

							if ($feedback)
								echo "
								<div style=\"color:#ff0000;padding-bottom:10px;\">$feedback</div>";

							if ( ! $db->connection_id || $db_priv_error ) {

							   echo "
							   <div style=\"font-size:9pt;width:350px;padding:35px 25px 25px 25px;border:1px solid #8f8f8f;background-color:#efefef;\">
							       <img src=\"core/images/alert.gif\" />&nbsp;".
                                    ( $db_priv_error ?
                                        $db_priv_error : NULL
                                    ) .
                                    ( $db->db_error ?
                                        $db->db_error : NULL
                                    ) . "
							   </div>";
							} elseif ($browser_err) {

                                echo "
                                <div style=\"font-size:9pt;width:350px;padding:35px 25px 25px 25px;border:1px solid #8f8f8f;background-color:#efefef;\">
							        <img src=\"core/images/alert.gif\" />
							        &nbsp;".$browser_err."
							    </div>";
							} else {

		                        echo "
		                        <div style=\"margin-bottom:5px;text-align:center;font-size:9pt;color:#000000;font-weight:bold;\">
		                            Site URL: " . ( defined('URL_HOST') ? URL_HOST : "Undefined") . "
		                        </div>
								<div style=\"width:350px;padding:35px 25px 20px 25px;border:1px solid #8f8f8f;background-color:#efefef;\">
								    <div style=\"text-align:left;margin-top:-30px;margin-left:-20px;font-size:7pt;color:#000000;font-weight:bold;\">".(fetch_sys_var('FORCE_SSL_REDIRECT') == 0 ? "
								        <img src=\"core/images/ssl".($_SERVER['HTTPS'] ? "_off" : NULL).".gif\" />&nbsp;
								        <a href=\"".($_SERVER['HTTPS'] ? LINK_ROOT."?ssl=no" : LINK_ROOT_SECURE)."\" style=\"color:#000000;text-decoration:underline;\">".($_SERVER['HTTPS'] ? "Standard" : "Secure")." Login</a>" : "&nbsp;")."
								    </div>
								    <div id=\"login_holder\" style=\"margin-top:25px;\"></div>
								</div>";
							}

							echo "
							<div style=\"color:#000000;font-size:8pt;padding-top:5px;\">" .
							( defined('LICENSEE') && defined('LICENSE_NO') ?
		    					"Licensed To: " . LICENSEE . " (" . LICENSE_NO . ")" : "Unlicensed"
    						) .
    						( defined('CURRENT_VERSION') ?
                                "<div style=\"margin-top:3px;\">Version " . CURRENT_VERSION . "</div>" : NULL
    						) . "
		        			</div>";

?>
							<noscript>
		                    <b>Your browser does not have javascript enabled. Please check your browser settings and enable javascript to continue.</b>
		                    </noscript>

<?php
						}
?>
    					</form>
                    </td>
                </tr>
            </table>
		</td>
	</tr>
</table>
</body>
</html>