<?php
if (is_array($_GET)) {
    reset($_GET);
    $err_code = key($_GET);

    switch (base64_decode($err_code)) {
    	case 'config1':
    	$msg = "The main configuration file is corrupt and not readable. Please have the system administrator visit our website <a href=\"http://www.dc-sysllc.com/\">www.dc-sysllc.com</a> to restore your config file and correct the problem. [err1]";
    	break;

    	case 'config2':
    	$msg = "The main configuration file doesn't exist or is corrupt. Please make sure the file 'dealerchoice.ini' is intact and is readable by the primary system user of ".$_SERVER['SERVER_SOFTWARE'].", and is located in the core/include/config directory. [err2]";
    	break;

    	case 'config3':
    	$msg = "Unable to locate and read the main system configuration file. Please check that the configuration file has been saved and is located under your DealerChoice directory -> core/include/config/dealerchoice.ini [err3]";
    	break;

    	case 'config4':
    	$msg = "The DealerChoice directory root specified in the system configuration file [".SITE_ROOT."] is not a valid directory. Please make sure the DealerChoice directory root hasn't changed and that your configuration file has not been modified. [err4]";
    	break;

    	case 'config5':
    	$msg = "Unable to define system configuration variables and settings. Please restart your browser and try again. [err5]";
    	break;

    	default:
        $msg = "The system has encountered a critical error and cannot proceed. Please restart your browser and try again. [err6]";
    	break;
    }
}
if (!$_GET || !$msg) {
    header("Location index.php");
    exit;
}
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link rel="SHORTCUT ICON" href="core/images/favicon.ico"/>
<title>DealerChoice :: System Error</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<style>
html {font-size: 125%}
body,td {font-family:arial, verdana, helvetica, tahoma;font-size: 78%; }
a:link , a:active , a:visited{color:#000000}

.error_msg{color:#e60000;font-size:10pt;font-weight:bold;}
</style>
</head>
<body bgcolor="#efefef" topmargin="0">
<center>
<table style="width:700px;height:100%;background-color:#ffffff;" cellpadding="0" cellspacing="0" align="center">
    <tr>
        <td style="text-align:center;vertical-align:top;border-right:1px solid #8f8f8f;border-left:1px solid #8f8f8f">
            <center>
            <div style="margin-bottom:100px;margin-top:100px;">
                <img src="dealerchoice_logo_541.gif" alt="DealerChoice Logo" />
            </div>
            <div style="width:450px;padding:15px 20px;border:1px solid #8f8f8f;background-color:#efefef;text-align:left;color:#00477f">
                <div style="margin-bottom:15px;font-weight:bold;">
                    <img src="core/images/alert.gif" />&nbsp;&nbsp;System Configuration Error!
                </div>
                <div style="margin-left:10px;font-size:8pt;">
                    <?php echo $msg; ?>
                    <div style="margin-top:15px;">
                        <a href="index.php" class="link_standard">Reload DealerChoice Login Page</a>
                    </div>
                </div>
            </div>
            </center>
        </td>
    </tr>
</table>
</center>
</body>
</html>