<?php
require_once('include/common.php');
?>
<script type="text/javascript" src="include/prototype.js"></script>
<script type="text/javascript" src="include/common.js"></script>
<script type="text/javascript" src="include/ajax_client.js"></script>
<?php
require_once ('include/ajax_proxy.php');
set_time_limit(0);

if ($_POST['iframeUpload']) {
	$action = $_POST['action'];
	$class = $_POST['class'];
	$from = $_POST['from'];
	$popup_id = $_POST['popup_id'];
	$form_name = $_POST['form_name'];

	$upload_file = $_FILES['upload_file'];
	$upload_type = $_GET['upload_type'];
	$upload_file['name'] = str_replace("'","",$upload_file['name']);
	$upload_file['name'] = str_replace(" ","_",$upload_file['name']);
	$move = move_uploaded_file($upload_file['tmp_name'],UPLOAD_DIR.$upload_file['name']);

	if ($move == 1) {

		echo "
		<script>
		var par = window.parent.document;
		par.getElementById('file_name').value = '".UPLOAD_DIR.$upload_file['name']."';
		par.getElementById('file_type').value = '".$upload_file['type']."';
		par.getElementById('file_size').value = '".$upload_file['size']."';
		par.getElementById('file_error').value = '".$upload_file['error']."';

		window.parent.submit_form(window.parent.document.getElementById('proposal_hash').form,'{$class}','exec_post','refresh_form','action={$action}','from={$from}','popup_id={$popup_id}');
		</script>";

		exit();

	} else {

		echo "
		<script>
		console.log('error');
		window.parent.submit_form(window.parent.document.getElementById('proposal_hash').form,'{$class}','exec_post','refresh_form','action={$action}','error=1');
		</script>";

		exit();

	}
}
?>
<html><head>
<script language="javascript">
function upload(){
	var par = window.parent.document;
	var iframe = par.getElementById('iframe');
	iframe.style.display = 'none';

	var images_div = par.getElementById('progress_holder');
	var new_div = par.createElement('div');
	new_div.id = 'progress_div';
	var new_img = par.createElement('img');
	new_img.src = 'images/file_loading.gif';
	new_img.style.margin = '36px';
	new_div.appendChild(new_img);
	images_div.appendChild(new_div);
	setTimeout("document.iform.submit()",50);
    return;
}

</script>
<style>
#file {
	width: 350px;
}
input.button {font: 10px verdana, helvetica, tahoma, verdana, sans serif; font-weight: bold; border: solid 1px #666699;cursor:hand;padding:5px;}
input {font: 12px verdana, helvetica, tahoma, verdana, sans serif;border:1px solid #666699;}

</style>
<head><body topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0">
<form name="iform" action="upload.php" method="post" enctype="multipart/form-data">
<input id="file" type="file" name="upload_file" <?php if (!$_GET['onchange']) echo "onChange=\"upload()\""; ?> style="width:300px;" />
<?php

ini_set('upload_max_filesize', '32M');
ini_set('post_max_size', '32M');
ini_set('max_input_time', 300);  
ini_set('max_execution_time', 300); 

if ($_GET['onchange'])
	echo "
	<input type=\"button\" value=\"Upload\" onClick=\"if(document.iform.upload_file.value){upload();}else{return alert('You did not select a file!');}\" />";
?>
<input type="hidden" name="iframeUpload" value="1" />
<?php
while (list($key,$val) = each($_GET))
	echo "<input type=\"hidden\" name=\"".$key."\" value=\"".$val."\" />";
?>
</form>
</html>