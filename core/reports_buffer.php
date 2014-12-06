<?php

if ( $_GET['streamfile'] ) {

	$loaded = urldecode($_GET['streamfile']);

    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"" . basename($loaded) . "\";");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: " . filesize($loaded) );

    set_time_limit(0);

    if ( ! readfile($loaded) ) {

    	#trigger_error("Cannot stat file {$_GET['streamfile']}", E_USER_ERROR);
    	print "Cannot stat file {$_GET['streamfile']}. Stream error.";
    	exit;
    }

    exit;
}

require_once('include/common.php');

$report_hash = $_GET['h'];
$referer = parse_url($_SERVER['HTTP_REFERER']);

if ( $referer['host'] != SITE_DOMAIN ) {

    header('HTTP/1.1 403 Forbidden');
    exit;
}

if ( $_SESSION['O_GZIP'] )
    header("Content-Encoding: gzip");
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
<link rel="SHORTCUT ICON" href="images/favicon.ico"/>
<title>DealerChoice : View Report</title>
<meta http-equiv="Content-Type" content="text/html; charset=<?php echo (defined('DEFAULT_CHARSET_HEADER') ? DEFAULT_CHARSET_HEADER : 'iso-8859-1'); ?>">
<link rel="stylesheet" href="include/main.css">
<link rel="stylesheet" type="text/css" href="include/office_xp.css" />

<script type="text/javascript" src="include/prototype.js"></script>
<script type="text/javascript" src="include/common.js"></script>
<script>
Element.extend(function $(element) {
  if (arguments.length > 1) {
    for (var i = 0, elements = [], length = arguments.length; i < length; i++)
      elements.push($(arguments[i]));
    return elements;
  }
  if (Object.isString(element)) {
    if (document.getElementById(element)) {
        element = document.getElementById(element);
    } else {
        element = window.parent.$(element);
        }
  }
  return Element.extend(element);
});
function $C(object) { return object.prototype; }
var agent = Class.create();
Object.extend(agent,window.parent.agent);

function resize_iframe() {
    if (!$('reports_window')) {
        return false;
    }

    $('reports_window').setStyle({'height' : $('reports_data_table').getHeight()+'px'});
    var parentwidth = $('reports_data_table').getWidth();
    var iframewidth = $('reports_window').getWidth();
    if (iframewidth < parentwidth) {
        parentwidth += 100;
        $('reports_window').setStyle({'width' : parentwidth + 'px'});
    }
}
document.observe('dom:loaded',function(){resize_iframe();});
</script>
</head>
<body style="background-color:transparent">
<div style="margin:0;padding:0;">
<?php

if ( ! $login_class->user_isloggedin() ) {

    print $login_class->error_template($login_class->err);

    ob_end_flush();
    $db->close();

    exit;
}

$output = $_GET['output'];
$loaded = $_GET['loaded'];

$reports = new reports($_SESSION['id_hash']);
$reports->report_hash = $report_hash;
if ( ! $reports->fetch_report_settings($report_hash) ) {

    print $login_class->error_template("Could not fetch report.");

    ob_end_flush();
    $db->close();

    exit;
}

if ( $output == 'xls' ) {

    $_GET['h'] = $report_hash;
    require_once('xls_print.php');

    echo "
    <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:1090px;margin-top:0;\"  id=\"reports_data_table\">
        <tr>
            <td style=\"width:100%;background-color:#ffffff;padding:10px;\" colspan=\"9\">
                <h3 style=\"margin-bottom:5px;color:#00477f;margin-top:-5px;\">
                    {$reports->current_report['report_name']}" .
                    ( $reports->p->ck('reports', 'V', 'ap') ?
	                    "<span style=\"font-size:10pt;padding-left:15px;font-weight:normal;\">
	                        [<small><a href=\"javascript:void(0);\" onClick=\"agent.call('reports','sf_loadcontent','show_popup_window','wip_report','popup_id=report_filter','report_hash={$reports->report_hash}');\" class=\"link_standard\">Update Report Settings</a></small>]
	                    </span>" : NULL
                    ) . "
                    <div style=\"margin-left:15px;margin-top:10px;margin-bottom:10px;font-size:10pt;\">{$reports->report_title}</div>
                </h3>
                <div style=\"margin-top:20px;margin-left:45px;\">
                    <a href=\"reports_buffer.php?streamfile=" . urlencode($tmpfname) . "\" target=\"_blank\"><img src=\"images/excel.gif\" alt=\"Export\" border=\"0\" /></a>&nbsp;
                    <a href=\"reports_buffer.php?streamfile=" . urlencode($tmpfname) . "\" target=\"_blank\" class=\"link_standard\">Download Report</a>
                </div>
            </td>
        </tr>
    </table>";

} else {

	$method = explode('::', $reports->current_report['method']);
    $reports->{$method[1]}(1);
}