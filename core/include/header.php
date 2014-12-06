<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
<?php
if ( $browser->Name == 'MSIE' && preg_match('/^[^1-7]\..*/', $browser->Version) ) # Trac #1651 - Automatic compatibility mode for MSIE compatibility mode for version >= 8.x
	print "<meta http-equiv=\"X-UA-Compatible\" content=\"IE=7\" />";

?>
<link rel="SHORTCUT ICON" href="images/favicon.ico"/>
<title>DealerChoice : Engineering Efficiency</title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo (defined('DEFAULT_CHARSET') ? DEFAULT_CHARSET : 'iso-8859-1'); ?>">
<link rel="stylesheet" href="include/main.css">
<link rel="stylesheet" href="include/inside_menu.css">
<link rel="stylesheet" href="include/tabcontent.css">
<link rel="stylesheet" href="include/dhtmlwindow.css" type="text/css" />
<link rel="stylesheet" type="text/css" href="include/office_xp.css" />

<script type="text/javascript" src="include/jsdomenu.js"></script>
<script type="text/javascript" src="include/jsdomenubar.js"></script>
<?php
if ($force_redirect)
	echo "<meta http-equiv=\"refresh\" content=\"".$force_redirect['redirect_delay'].";URL=".$force_redirect['destination']."\" />";

require_once('include/ddconfig.inc.php');
?>
<script type="text/javascript" src="include/dhtmlwindow.js"></script>
<script type="text/javascript" src="include/tabcontent.js"></script>
<script type="text/javascript" src="include/calendarDateInput.js"></script>
<script type="text/javascript" src="include/prototype.js"></script>

<script type="text/javascript" src="include/ajax_keySearch.js<?php echo "?t=".time(); ?>"></script>
<script type="text/javascript" src="include/common.js"></script>
<script type="text/javascript" src="include/ajax_client.js"></script>
<script type="text/javascript" src="include/rightclick.js"></script>
<script type="text/javascript" src="include/country_state.js"></script>
<!--OBSTYLE-->
</head>
<body onload="initjsDOMenu();startup();">
<div class="ajax_placeholder" id="ajax_progress" style="display:none;">
	<img src="images/ajax-loader.gif" />
	&nbsp;
	Loading...
</div>
<table style="text-align:left;width:100%;">
	<tr>
		<td >
			<div align=center id=ygma>
				<table style="width:100%;" cellpadding=0 cellspacing=0 >
					<tr >
						<td id=ygmalinks class=ygmabk colspan="2" style="background-color:#00477f;border-bottom:1px solid #00477f;">
							<table width="100%">
								<tr>
									<td align="left" style="font-size:11px; " ></td>
									<td style="text-align:right;font-size:-2;color:#ffffff;">&nbsp;</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td valign="top" >
							<div style="padding:8px 12px 3px 3px;margin:0;">
								<a href="<?php echo LINK_ROOT_SECURE.($login_class->user_isloggedin() ? "/core/" : NULL) ?>index.php">
									<img src="images/Dealer_Choice_logo_web.gif" border="0" align="left" style="padding:10px 15px 10px 0; ">
								</a>
								<div style="font-family:verdana,geneva,sans-serif;font-size:12px;padding-top:8px; ">
									<?php echo ($login_class->user_isloggedin() ? "Welcome, <strong>".$_SESSION['user_name']."</strong>" : "Welcome!") ?>
									<br />
									<?php echo ($login_class->user_isloggedin() ? "[<a href=\"logout.php\">Logout</a>]" : NULL);?>
								</div>
								<div class="page_feedback" id="page_feedback_holder" ></div>
							</div>
						</td>
						<td style="padding-top:8px;text-align:right;padding-right:25px;vertical-align:top;">
						<?php
							if ($login_class->user_isloggedin())
							echo "
							<div id=\"status_tablediv\">
								<table class=\"status_tbl\">
									<tr>
										<td class=\"status_btn\" onClick=\"agent.call('alerts','sf_loadcontent','show_popup_window','message_win','popup_id=alert_win');\" nowrap>
											Messages <span id=\"status_alert_count\"></span>
										</td>
										<td style=\"vertical-align:bottom;float:right;color:#16387c;font-size:15pt;padding-right:15px;text-align:right;\" nowrap>
											Hello ".(strlen($_SESSION['my_name']) > 20 ? substr($_SESSION['my_name'],0,strpos($_SESSION['my_name']," ")) : $_SESSION['my_name'])."!
										</td>
									</tr>
									<tr>
										<td colspan=\"3\"></td>
									</tr>
								</table>
							</div>";
						?>
						</td>
					</tr>
					<tr>
						<td align="left" valign="bottom"></td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
	<tr>
		<td>
		<!--OBSPLIT-->
