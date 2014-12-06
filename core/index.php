<?php
//Load page specific includes and libraries
require_once ('include/common.php');

if ( ! $login_class->user_isloggedin() ) {

	header("Location: ../login.php".($login_class->err ? "?".base64_encode($login_class->err) : NULL));
	exit;
}

// Select correct db to use if different that system constant
if (DB_NAME != $_SESSION['use_db']) {
	$db->select_db($_SESSION['use_db']);
}
$a = new AJAX_library;
$a->unlock('*');
unset($a);
require_once ('include/header.php');
require_once ('include/ajax_proxy.php');
echo "
<script>
Event.observe(window, 'load', function() {agent.initialize(45);".($_GET['r'])."})
Event.observe(document, 'click', function() {hidemenuie5();vtobj.hideKeyResults();})
</script>
<div id=\"dhtmlwindowholder\"><span style=\"display:none;\">.</span></div>
<div id=\"parent_key_holder\"><div id=\"keyResults\" onMouseDown=\"setTimeout('vtobj.cancel_timer()',100);\"></div></div>
".$form->text_box("name=key_result_holder","value=","style=display:none;","id=key_result_holder")."
<table class=\"tborder\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
	<tr>
		<td class=\"tcat\" style=\"padding:7px;\"><div id=\"content_title\">Welcome ".$_SESSION['my_name']."!</div></td>
		<td class=\"tcat\" style=\"vertical-align:bottom;padding:0;text-align:left;\" nowrap>
			<div id=\"content_nav\"></div>
		</td>
	</tr>
	<tr>
		<td class=\"panelsurround\" colspan=\"2\" >
			<div class=\"panel\" >
				<div style=\"padding:10px;\" class=\"fieldset\" id=\"place_holder\"></div>
			</div>
		</td>
	</tr>
</table>
<script>document.oncontextmenu=function(){return;}</script>
";
include('include/footer.php');
//
?>