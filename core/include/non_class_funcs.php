<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
function __unserialize($sObject) {

    $__ret = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $sObject );

    return unserialize($__ret);

}

function server_info($n) {
    switch ($n) {
    	case 'memory_limit':
        $val = ini_get('memory_limit');
        $split = str_split($val);
        $count = count($split);
        for ($i = 0; $i < $count; $i++) {
            if (strspn($split[$i],'0123456789') == 1)
                continue;
            else {
                $byte = $split[$i];
                unset($split[$i]);
                break 1;
            }
        }
        if ($byte) {
        	$byte = 'G';
        	$split = implode('',array_values($split));
            switch ($byte) {
            	case 'G':
                $val = pow(1024,3) * $split;
           		break;

            	case 'M':
            	$val = pow(1024,2) * $split;
            	break;

            	case 'K':
            	$val = $split * 1024;
            	break;
            }
        }
   		break;
    }

    return $val;
}

function __stripslashes(&$array) {
	while (list($a,$b) = each($array))
		$b = stripslashes($array);
}

function display_saved_queries() {

	global $db;

	$query_time_total = 0.0;
	$saved_queries = $db->get_saved_queries();

    $str = "
	<div style=\"text-align:center;\">
    	<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;\">
    		<tr>
				<td style=\"width:75px;font-weight:bold;background-color:#ececec;text-align:right;\">Times</th>
				<td style=\"width:650px;font-weight:bold;background-color:#ececec;text-align:left;\">Query</th>
			</tr>";

	while ( list(, $cur_query) = @each($saved_queries) ) {

    	$query_time_total += $cur_query[1];

		$str .=
			"<tr>
				<td style=\"background-color:#ececec;text-align:right;vertical-align:top;" .
        		( $cur_query[1] > 1 ?
            		"color:#ff0000;" : NULL
        		) .
        		"\">" .
        		( $cur_query[1] !== false ?
            		$cur_query[1] : "<div style=\"font-weight:bold;color:#ff0000;\">Failed</div>"
            	) . "
            	</td>
				<td style=\"background-color:#ececec;text-align:left;\">" . nl2br( pun_htmlspecialchars( preg_replace('/(\n(?:\s*)?\n)/m', "\n", $cur_query[0]) ) ) . "</td>
			</tr>";

	}

    $str .= "
        	<tr>
				<td colspan=\"2\" style=\"background-color:#ececec;font-weight:bold;text-align:left;\">Total query time: $query_time_total s</td>
			</tr>
		</table>
	</div>";

	return $str;
}

function pun_htmlspecialchars($str)
{
	$str = preg_replace('/&(?!#[0-9]+;)/s', '&amp;', $str);
	$str = str_replace(array('<', '>', '"'), array('&lt;', '&gt;', '&quot;'), $str);

	return $str;
}

function wrap_array(&$item1,$key,$quote) {
	$item1 = $quote.$item1.$quote;
}

function error($debug,$message=NULL) {
	global $main_config;

	write_error($debug,"\nThe error() function was called.".($message ? "\n\nThe error message provided to the user was: $message" : NULL));

	// Empty output buffer and stop buffering
	$temp_msg = trim(ob_get_contents());
	$temp_msg = explode("<!--OBSPLIT-->",$temp_msg);
	$temp_msg = $temp_msg[0];
	@ob_end_clean();

	// "Restart" output buffering if we are using ob_gzhandler (since the gzip header is already sent)
	if (!empty($main_config['o_gzip']) && extension_loaded('zlib') && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false))
		ob_start('ob_gzhandler');

if (!$temp_msg) {

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Error</title>
<style type="text/css">
<!--
BODY {MARGIN: 10% 20% auto 20%; font: 10px Verdana, Arial, Helvetica, sans-serif}
#errorbox {BORDER: 1px solid #B84623;WIDTH:400px;}
H2 {MARGIN: 0; COLOR: #FFFFFF; BACKGROUND-COLOR: #B84623; FONT-SIZE: 1.1em; PADDING: 5px 4px}
#errorbox DIV {PADDING: 6px 5px; BACKGROUND-COLOR: #F1F1F1}
-->
</style>
</head>
<body>
<?php
} elseif ($temp_msg)
$temp_msg = str_replace("<title>DealerChoice","<title>Error :: DealerChoice", $temp_msg);
echo str_replace("<!--OBSTYLE--><style type=\"text/css\">","
<!--OBSTYLE--><style type=\"text/css\">
<!--
BODY {MARGIN: 10% 20% auto 20%; font: 10px Verdana, Arial, Helvetica, sans-serif}
-->
",$temp_msg);
echo genericTable("Error",NULL,true)."
<div style=\"padding:10px\" class=\"fieldset\">
	<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:90%;\" >
		<tr>
			<td class=\"smallfont\" style=\"padding:20px;background-color:#ffffff;text-align:center;\">
				<div style=\"width:700px;\">
					<h2 style=\"color:#0A58AA;margin-top:0\">Sorry, this page caused an error.</h2>".($message ?
					$message : "
					Please check the URL in your browser's address bar to make sure you have spelled the
					page correctly. If you found this page by clicking a link, the link may be invalid or
					the page may be temporarily unvailable.")."
					<br /><br />
					Please click <a href=\"index.php\">here</a> to go to your SelectionSheet.com home page or click
					<a href=\"javascript:history.back();\">here</a> to return to the previous page.
				</div>
			</td>
		</tr>
	</table>
</div>";

	if (defined('PUN_DEBUG'))
	{
		echo '<div><strong>File:</strong> '.$file.'<br />'."\n\t\t".'<strong>Line:</strong> '.$line.'<br /><br />'."\n\t\t"."\n";

		if ($db_error)
		{
			echo "\t\t".'<br /><br /><strong>Database reported:</strong> '.pun_htmlspecialchars($db_error['error_msg']).(($db_error['error_no']) ? ' (Errno: '.$db_error['error_no'].')' : '')."\n";

			if ($db_error['error_sql'] != '')
				echo "\t\t".'<br /><br /><strong>Failed query:</strong> '.pun_htmlspecialchars($db_error['error_sql'])."\n";
		}
		echo '</div>';
	}
if ($temp_msg) {
echo closeGenericTable();
?>
		</td>
	</tr>
	<tr>
		<td>
			<table class="footer_tbl">
				<tr>
					<td></td>
					<td>
						<table class="footer_left">
							<tr>
								<td align="center">
								<br>
									<a href="about.php">About Our Company</a>
									&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp;
									<a href="products.php">Our Scheduling System</a>
									&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp;
									<a href="register.php">Register Free!</a>
									&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp;
									<a href="contact.php">Contact Us</a>
								</td>
							</tr>
							<tr>
								<td align="center">
									<br />
									By accessing this site, you accept the terms of our Acceptable Use Policy and Visitor Agreement and Privacy Policy.
								</td>
								<td></td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<?php
}
?>
</body>
</html>
<?php

	// If a database connection was established (before this error) we close it
	if ($db_error)
		$GLOBALS['db']->close();

	exit;
}

function paginate($num_pages, $cur_page, $link_to)
{
	$pages = array();
	$link_to_all = false;

	// If $cur_page == -1, we link to all pages (used in viewforum.php)
	if ($cur_page == -1)
	{
		$cur_page = 1;
		$link_to_all = true;
	}

	if ($num_pages <= 1)
		$pages = array('<strong>1</strong>');
	else
	{
		if ($cur_page > 3)
		{
			$pages[] = '<a href="'.$link_to.'&amp;p=1">1</a>';

			if ($cur_page != 4)
				$pages[] = '&hellip;';
		}

		// Don't ask me how the following works. It just does, OK? :-)
		for ($current = $cur_page - 2, $stop = $cur_page + 3; $current < $stop; ++$current)
		{
			if ($current < 1 || $current > $num_pages)
				continue;
			else if ($current != $cur_page || $link_to_all)
				$pages[] = '<a href="'.$link_to.'&amp;p='.$current.'">'.$current.'</a>';
			else
				$pages[] = '<strong>'.$current.'</strong>';
		}

		if ($cur_page <= ($num_pages-3))
		{
			if ($cur_page != ($num_pages-3))
				$pages[] = '&hellip;';

			$pages[] = '<a href="'.$link_to.'&amp;p='.$num_pages.'">'.$num_pages.'</a>';
		}
	}

	$str = "
	<table class=\"tborder\" cellSpacing=\"1\" cellPadding=\"3\">
		<tbody>
		<tr>
			<td class=\"vbmenu_control\" style=\"font-weight:normal;text-align:left;\">Page $cur_page of $num_pages</td>
			<td class=\"alt1_nav\">&nbsp;&nbsp;".implode('&nbsp;&nbsp;', $pages)."&nbsp;&nbsp;</td>".($cur_page < $num_pages ? "
			<td class=\"alt1_nav\"><a href=\"".$link_to."&amp;p=".($cur_page + 1)."\"><strong>&gt;</strong></a></td>" : NULL)."
		</tr>
		</tbody>
	</table>";

	return $str;
}

function paginate_jscript($num_pages, $cur_page, $s_function, $c_function, $s_obj=NULL, $s_method=NULL, $order=NULL, $order_dir=NULL, $args=NULL) {
	$pages = array();
	$link_to_all = false;

	if ($cur_page == -1)
	{
		$cur_page = 1;
		$link_to_all = true;
	}

	if ($num_pages <= 1)
		$pages = array('<strong>1</strong>');
	else
	{
		if ($cur_page > 3)
		{
			$pages[] = "<a href=\"javascript:void(0);\" onClick=\"agent.call('$s_obj','$s_function','$c_function','$s_method','p=1','order=$order','order_dir=$order_dir'".($args ? ",".$args : NULL).");\">1</a>";

			if ($cur_page != 4)
				$pages[] = '&hellip;';
		}

		// Don't ask me how the following works. It just does, OK? :-)
		for ($current = $cur_page - 2, $stop = $cur_page + 3; $current < $stop; ++$current)
		{
			if ($current < 1 || $current > $num_pages)
				continue;
			else if ($current != $cur_page || $link_to_all)
				$pages[] = "<a href=\"javascript:void(0);\" onClick=\"agent.call('$s_obj','$s_function','$c_function','$s_method','p=$current','order=$order','order_dir=$order_dir'".($args ? ",".$args : NULL).")\">$current</a>";
			else
				$pages[] = '<strong>'.$current.'</strong>';
		}

		if ($cur_page <= ($num_pages-3))
		{
			if ($cur_page != ($num_pages-3))
				$pages[] = '&hellip;';

			$pages[] = "<a href=\"javascript:void(0);\" onClick=\"agent.call('$s_obj','$s_function','$c_function','$s_method','p=$num_pages','order=$order','order_dir=$order_dir'".($args ? ",".$args : NULL).")\">$num_pages</a>";
		}
	}

	$str = "
	<table class=\"tborder\" cellSpacing=\"1\" cellPadding=\"3\">
		<tbody>
		<tr>
			<td class=\"vbmenu_control\" style=\"font-weight:normal;text-align:left;\">Page $cur_page of $num_pages</td>
			<td class=\"alt1_nav\">&nbsp;&nbsp;".implode('&nbsp;&nbsp;', $pages)."&nbsp;&nbsp;</td>".($cur_page > 1 && $num_pages > 1 ? "
			<td class=\"alt1_nav\"><a href=\"javascript:void(0);\" onClick=\"agent.call('$s_obj','$s_function','$c_function','$s_method','p=".($cur_page - 1)."','order=$order','order_dir=$order_dir'".($args ? ",".$args : NULL).");\"><strong>&lt;</strong></a></td>" : NULL).($cur_page < $num_pages ? "
			<td class=\"alt1_nav\"><a href=\"javascript:void(0);\" onClick=\"agent.call('$s_obj','$s_function','$c_function','$s_method','p=".($cur_page + 1)."','order=$order','order_dir=$order_dir'".($args ? ",".$args : NULL).");\"><strong>&gt;</strong></a></td>" : NULL)."
		</tr>
		</tbody>
	</table>";

	return $str;
}


function query_str($var=NULL) {
	$qs = explode("&",$_SERVER['QUERY_STRING']);
	$qs = array_unique($qs);
	if ($var) {
		$var .= (!ereg("=",$var) ? "=" : NULL);
		$remove_str = preg_grep("/^$var/",$qs);

		while (list($key) = each($remove_str))
			unset($qs[$key]);
	}

	while (list($key) = each($qs)) {
		if ($qs[$key] == "" || ereg("feedback=",$qs[$key]))
			unset($qs[$key]);
	}

	return implode("&",array_values($qs))."&amp;";
}

function id_hash_to_name($hash_array) {
	global $db;
	if (!is_array($hash_array)) {
		$non_array = true;
		$hash_array = array($hash_array);
	}
	for ($i = 0; $i < count($hash_array); $i++) {
		$result = $db->query("SELECT `first_name` , `last_name`
							  FROM `user_login`
							  WHERE `id_hash` = '".$hash_array[$i]."'");
		$name[] = $db->result($result,"first_name")." ".$db->result($result,0,"last_name");
	}

	return ($non_array ? $name[0] : $name);
}

function unique_multi_array($array) {
	global $target;
	if (!is_array($target))
		$target = array();

	foreach ($array as $key => $val) {
		if (is_array($val))
			unique_multi_array($val);
		else {
			if (!in_array($val,$target))
				$target[] = $val;
		}
	}
	return $target;
}

function format_fax_email($hash,$message,$recp_array,$type='fax') {
	global $db;

	$result = $db->query("SELECT `first_name` , `last_name` , `builder` , `address` , `phone` , `fax` , `email`
						  FROM `user_login`
						  WHERE `id_hash` = '$hash'");
	$row = $db->fetch_assoc($result);
	$name = $row['first_name']." ".$row['last_name'];
	$builder = $row['builder'];
	list($addr1,$addr2,$city,$state,$zip) = explode("+",$row['address']);
	list($phone) = explode("+",$row['phone']);
	$fax = $row['fax'];
	$email = $row['email'];

	$fp = fopen(FAX_DOCS."default/selectionsheet_".($type == 'email' ? "email_" : NULL)."coverpage.htm","r");
	while (!feof($fp))
		$data .= fread($fp,1024);

	$data = str_replace("<!--COMPANY-->",$builder,$data);
	$data = str_replace("<!--ADDR1-->",$addr1,$data);
	$data = str_replace("<!--ADDR2-->",$addr2,$data);
	$data = str_replace("<!--CITYSTZIP-->","$city $state, $zip",$data);
	$data = str_replace("<!--PHONE-->",($phone ? "phone: ".$phone : NULL),$data);
	$data = str_replace("<!--FAX-->",($fax ? "fax: ".$fax : NULL),$data);
	$data = str_replace("<!--DATE-->",date("D, M d, Y g:i a"),$data);
	if ($type == 'fax')
		$data = str_replace("<!--RECPFAX-->",$recp_array['fax'],$data);
	elseif ($type == 'email')
		$data = str_replace("<!--RECPEMAIL-->",$recp_array['email'],$data);

	$data = str_replace("<!--RECP-->",$recp_array['sendTo'],$data);
	$data = str_replace("<!--FROM-->","$name, $builder",$data);
	$data = str_replace("<!--BODY-->",$message,$data);

	return $data;
}

function clean_phone($phone)
{
  $p = strtolower($phone);
  for ($i=0;$i<strlen($p);$i++)
  {
    $a = ord(substr($p, $i, 1));
    // If ( Not Numeric ) or ( Not 'x' )
    if ((($a >= 48) && ($a <= 57)) || ($a == 120)) $r .= substr($p, $i, 1);
  }
  return $r;
}

function format_phone($phone)
{
  $phone = clean_phone($phone);
  $ret = "";
  $ext = "";
  $i = strpos($phone,'x');
  if (!($i === false))
  {
    // Contains extension
    $ext = "x".substr($phone,$i);
    $phone = substr($phone,0,$i);
  }
  // Phones with no extension
  switch(strlen($phone))
  {
    case 7:
      $ret = substr($phone, 0, 3)."-".substr($phone, 3);
      break;
    case 8:
      $ret = substr($phone, 0, 4)."-".substr($phone, 4);
      break;
    case 10:
      $ret = "(".substr($phone, 0, 3).") ".substr($phone, 3, 3)."-".substr($phone, 6, 4);
      break;
    default:
      $ret = $phone;
  }
  return $ret.$ext;
}
function in_multi_array($array,$value,$key=NULL) {
	if (!is_array($array))
		return false;

	reset($array);
	for ($i = 0; $i < count($array); $i++) {
		if ($key) {
			$tmp_array = current($array);
			if (array_key_exists($key,$tmp_array) && $tmp_array[$key] == $value)
				return key($array);

		}
		elseif (in_array($value,current($array)))
			return key($array);

		next($array);
	}

	return false;
}
function fsize_unit_convert($bytes)
{
	$units = array('B', 'KB', 'MB', 'GB');
	$converted = $bytes . ' ' . $units[0];
	for ($i = 0; $i < count($units); $i++)
	{
	if (($bytes/pow(1024, $i)) >= 1)
	{$converted = round($bytes/pow(1024, $i), 2) . ' ' . $units[$i];}
	}
	return $converted;
}
/** Serialized Array of big names, thousand, million, etc
 * @package NumberToText */
define("N2T_BIG", serialize(array('thousand', 'million', 'billion', 'trillion', 'quadrillion', 'quintillion', 'sextillion', 'septillion', 'octillion', 'nonillion', 'decillion', 'undecillion', 'duodecillion', 'tredecillion', 'quattuordecillion', 'quindecillion', 'sexdecillion', 'septendecillion', 'octodecillion', 'novemdecillion', 'vigintillion', 'unvigintillion', 'duovigintillion', 'trevigintillion', 'quattuorvigintillion', 'quinvigintillion', 'sexvigintillion', 'septenvigintillion', 'octovigintillion', 'novemvigintillion', 'trigintillion', 'untrigintillion', 'duotrigintillion', 'tretrigintillion', 'quattuortrigintillion', 'quintrigintillion', 'sextrigintillion', 'septentrigintillion', 'octotrigintillion', 'novemtrigintillion')));
/** Serialized Array of medium names, twenty, thirty, etc
 * @package NumberToText */
define("N2T_MEDIUM", serialize(array(2=>'twenty', 3=>'thirty', 4=>'forty', 5=>'fifty', 6=>'sixty', 7=>'seventy', 8=>'eighty', 9=>'ninety')));
/** Serialized Array of small names, zero, one, etc.. up to eighteen, nineteen
 * @package NumberToText */
define("N2T_SMALL", serialize(array('zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen')));
/** Word for "dollars"
 * @package NumberToText */
define("N2T_DOLLARS", "dollars");
/** Word for one "dollar"
 * @package NumberToText */
define("N2T_DOLLARS_ONE", "dollar");
/** Word for "cents"
 * @package NumberToText */
define("N2T_CENTS", "cents");
/** Word for one "cent"
 * @package NumberToText */
define("N2T_CENTS_ONE", "cent");
/** Word for "and"
 * @package NumberToText */
define("N2T_AND", "and");
/** Word for "negative"
 * @package NumberToText */
define("N2T_NEGATIVE", "negative");

/** Number to text converter. Converts a number into a textual description, such as
 * "one hundred thousand and twenty-five".
 *
 * Now supports _any_ size number, and negative numbers. To pass numbers > 2 ^32, you must
 * pass them as a string, as PHP only has 32-bit integers.
 *
 * @author Greg MacLelan
 * @version 1.1
 * @param int  $number      The number to convert
 * @param bool $currency    True to convert as a dollar amount
 * @param bool $capatalize  True to capatalize every word (except "and")
 * @param bool $and         True to use "and"  (ie. "one hundred AND six")
 * @return The textual description of the number, as a string.
 * @package NumberToText
 */
/** Changelog:
 * 2007-01-11: Fixed bug with invalid array references, trim() output
 */
function NumberToText($number,$currency=false,$capatalize=false,$and=true,$currency_string=false) {
    $big = unserialize(N2T_BIG);
    $small = unserialize(N2T_SMALL);
    if (!$currency_string)
        $currency_string = N2T_DOLLARS;


    // get rid of leading 0's
    /*
    while ($number{0} == 0) {
        $number = substr($number,1);
    }
    */

    $text = "";

    //$negative = ($number < 0); // check for negative
    //$number = abs($number); // make sure we have a +ve number
    if (substr($number, 0, 1) == "-") {
        $negative = true;
        $number = substr($number,1); // abs()
    } else {
        $negative = false;
    }

    // get the integer and decimal parts
    //$int_o = $int = floor($number); // store into two vars
    if ($pos = strpos($number,".")) {
    	list($d,$c) = explode(".",$number);
    	if (strlen($c) < 2)
    	    $number = number_format($number,2,'.','');

        $int_o = $int = substr($number,0,$pos);
        $decimal_o = $decimal = substr($number,$pos + 1);

    } else {
        $int_o = $int = $number;
        $decimal_o = $decimal = 0;
    }
    // $int_o and $decimal_o are for "original value"

    // conversion for integer part:

    $section = 0; // $section controls "thousand" "million" etc
    do {
        // keep breaking down into 3 digits ($convert) and the rest
        //$convert = $int % 1000;
        //$int = floor($int / 1000);

        if ($section > count($big) - 1) {
            // ran out of names for numbers this big, call recursively
            $text = NumberToText($int, false, false, $and)." ".$big[$section-1]." ".$text;
            $int = 0;
        } else {
            // we can handle it
            $convert = substr($int, -3); // grab the last 3 digits
            $int = substr($int, 0, -1 * strlen($convert));

            if ($convert > 0) {
                // we have something here, put it in
                $text = trim(n2t_convertthree($convert, $and, ($int > 0)).(isset($big[$section-1]) ? ' '.$big[$section-1].' ' : '').$text);
            }
        }

        $section++;
    } while ($int > 0);

    // conversion for decimal part:

    if ($currency && floor($number)) {
        // add " dollars"
        $text .= " ".($int_o == 1 ? $currency_string : $currency_string)." ";
    }

    if ($decimal && $currency) {
        // if we have any cents, add those
        if ($int_o > 0) {
            $text .= " ".N2T_AND." ";
        }
        $cents = substr($decimal,0,2); // (0.)2342 -> 23
        if (substr($cents,0,1) == '.')
            $cents = number_format($cents * 100);
        elseif (substr($cents,0,1) == 0 && strlen($cents) == 2)
            $cents = substr($cents,1,1);

        $decimal = substr($decimal,2); // (0.)2345.. -> 45..

        $text .= $cents."/100";//n2t_convertthree($cents, false, true); // explicitly show "and" if there was an $int
    }

    if ($decimal) {
        // any remaining decimals (whether or not $currency is set)
        $text .= " point";
        for ($i = 0; $i < strlen($decimal); $i++) {
            // go through one number at a time
            $text .= " ".$small[$decimal{$i}];
        }
    }

   // if ($decimal_o && $currency) {
        // add " cents" (if we're doing currency and had decimals)
        //$text .= $decimal_o == 1 ? N2T_CENTS_ONE : N2T_CENTS);
   // }

    // check for negative
    if ($negative) {
        $text = N2T_NEGATIVE." ".$text;
    }

    // capatalize words
    if ($capatalize) {
        // easier to capatalize all words then un-capatalize "and"
        $text = str_replace(ucwords(N2T_AND), N2T_AND, ucwords($text));
    }

    return trim($text);
}

/** This is a utility function of n2t. It converts a 3-digit number
 * into a textual description. Normally this is not called by itself.
 *
 * @param  int  $number     The 3-digit number to convert (0 - 999)
 * @param  bool $and        True to put the "and" in the string
 * @param  bool $preceding  True if there are preceding members, puts an
 *                          explicit and in (ie 1001 => one thousand AND one)
 * @return The textual description of the number, as a string
 * @package NumberToText
 */
function n2t_convertthree($number, $and, $preceding) {
    $small = unserialize(N2T_SMALL);
    $medium = unserialize(N2T_MEDIUM);

    $text = "";

    if ($hundreds = floor($number / 100)) {
        // we have 100's place
        $text .= $small[$hundreds]." hundred ";
    }
    $tens = $number % 100;
    if ($tens) {
        // we still have values
        if ($and && ($hundreds || $preceding)) {
            $text .= " ".N2T_AND." ";
        }

        if ($tens < 20) {
            $text .= $small[$tens];
        } else {
            $text .= $medium[floor($tens/10)];
            if ($ones = $tens % 10) {
                $text .= "-".$small[$ones];
            }
        }
    }

    return $text;
}

function update_sys_data($var_name,$var_val) {
	global $db;

	$db->query("UPDATE `system_vars`
			    SET `var_val` = '$var_val'
			    WHERE `var_name` = '$var_name'");
    if (isset($_SESSION[$var_name]))
    	$_SESSION[$var_name] = $var_val;

	return;
}

function fetch_sys_var($var_name) {
	global $db;

	$result = $db->query("SELECT system_vars.var_val
						  FROM `system_vars`
						  WHERE system_vars.var_name = '$var_name'");
	return $db->result($result);
}

function get_quarter($date=NULL) {
	if (!$date)
		$date = date("Y-m-d");

	$month = date("n",strtotime($date));
	$qtr = round($month / 12,2);
	$qtr *= 100;

	if ($qtr > 0 && $qtr <= 25)
		return 1;
	elseif ($qtr > 25 && $qtr <= 50)
		return 2;
	elseif ($qtr > 50 && $qtr <= 75)
		return 3;
	elseif ($qtr > 75)
		return 4;
}

function get_quarter_dates($qtr,$year=NULL) {
	if (!$year)
		$year = date("Y");

	switch($qtr) {
		case 1:
		$start = $year."-01-01";
		$end = $year."-03-".accounting::getLastDayOfMonth(3,$year);
		break;

		case 2:
		$start = $year."-04-01";
		$end = $year."-06-".accounting::getLastDayOfMonth(6,$year);
		break;

		case 3:
		$start = $year."-07-01";
		$end = $year."-09-".accounting::getLastDayOfMonth(9,$year);
		break;

		case 4:
		$start = $year."-10-01";
		$end = $year."-12-".accounting::getLastDayOfMonth(12,$year);
		break;
	}

	return array($start,$end);
}

function get_period_info($date) {
	global $db;

	$result = $db->query("SELECT *
						  FROM `period_dates`
						  WHERE `period_start` <= '$date' AND `period_end` >= '$date'");

	return $db->fetch_assoc($result);
}

function get_previous_period($date) {
	global $db;

	$result = $db->query("SELECT `period_start`
						  FROM `period_dates`
						  WHERE `period_start` <= '$date' AND `period_end` >= '$date'");

	if ($period_start = $db->result($result)) {
		$previous_period_date = date("Y-m-d",strtotime($period_start) - 172800);
		$result = $db->query("SELECT `fiscal_year` , `period` , `period_start` , `period_end`
							  FROM `period_dates`
							  WHERE `period_start` <= '$previous_period_date' AND `period_end` >= '$previous_period_date'");

		if ($previous_period_data = $db->fetch_assoc($result))
			return $previous_period_data;
	}
}

function get_next_period($date) {
	global $db;

	$result = $db->query("SELECT `period_end`
						  FROM `period_dates`
						  WHERE `period_start` <= '$date' AND `period_end` >= '$date'");

	if ($period_end = $db->result($result)) {
		$next_period_date = date("Y-m-d",strtotime($period_end) + 172800);
		$result = $db->query("SELECT `fiscal_year` , `period` , `period_start` , `period_end`
							  FROM `period_dates`
							  WHERE `period_start` <= '$next_period_date' AND `period_end` >= '$next_period_date'");

		if ($next_period_data = $db->fetch_assoc($result))
			return $next_period_data;
	}
}

function add_quotes(&$item1,$key,$prefix) {
    $item1 = $prefix.$item1.$prefix;
}

function strip_quotes(&$item1,$key) {
	if (substr($item1,0,1) == "'" && substr(strrev($item1),0,1) == "'") {
		$item1_tmp = strrev(substr(strrev($item1),1));
		$item1 = substr($item1_tmp,1);
	}
}

function _round($number,$precision=4) {
    list($int,$dec) = explode(".",$number);
    if (strlen($dec) <= $precision)
        return $number;

    if (strlen($dec) > 8)
        $dec = substr($dec,0,8);

    $half = substr($dec,$precision);
    $int_half = substr($half,0,1);
    $factor = bccomp($int_half,5,1);

    $to_add = '.';
    for ($i = 1; $i < $precision; $i++)
        $to_add .= '0';

    $to_add .= '1';
    $to_add = (float)$to_add;

    $number = $int.".".substr($dec,0,$precision);
    $number = (float)$number;

    if ($factor >= 0) {
    	if (bccomp($number,0,$precision) == -1)
            $to_add = bcmul($to_add,-1,$precision);

        return bcadd($number,$to_add,$precision);
    } else
        return $number;
}

function fetch_user_data($content,$user_hash=NULL) {
	global $db;

	if (!$user_hash)
		$user_hash = $_SESSION['id_hash'];

	$result = $db->query("SELECT `value`
						  FROM `user_data`
						  WHERE `id_hash` = '$user_hash' AND `key` = '$content'");
	$row = $db->fetch_assoc($result);
	return $row['value'];
}

function store_user_data($content,$data,$user_hash=NULL) {
	global $db;

	if (!$user_hash)
		$user_hash = $_SESSION['id_hash'];

	$result = $db->query("SELECT `obj_id`
						  FROM `user_data`
						  WHERE `id_hash` = '$user_hash' AND `key` = '$content'");
	if ($id = $db->result($result))
		$db->query("UPDATE `user_data`
					SET `value` = '$data'
					WHERE `id_hash` = '$user_hash' AND `key` = '$content'");
	else
		$db->query("INSERT INTO `user_data`
					(`timestamp` , `id_hash` , `key` , `value`)
					VALUES (".time()." , '$user_hash' , '$content' , '$data')");
	return;
}

function group_name($group_hash) {
	global $db;

	$result = $db->query("SELECT `group_name`
						  FROM `user_groups`
						  WHERE `group_hash` = '$group_hash'");
	return $db->result($result);
}

function user_name($id_hash) {
	global $db;

	$result = $db->query("SELECT `full_name`
						  FROM `users`
						  WHERE `id_hash` = '$id_hash'");
	return $db->result($result);
}

function field_exists($db_table,$db_field,$val) {
	global $db;

	$result = $db->query("SELECT COUNT(*) AS Total
						  FROM `".$db_table."`
						  WHERE `".$db_field."` = '".$val."'");
	return $db->result($result);
}

function create_drop_down_arrays($arr,$display_key) {
	$pd_keys_arr = array();
	$pd_values_arr = array();

	foreach($arr as $key => $val) {
	  $pd_keys_arr[] = $key;
	  $pd_values_arr[] = stripslashes($val[$display_key]);
	}

	$ret = array($pd_values_arr,$pd_keys_arr);
	return $ret;
}

function base_version($version=NULL) {
	if (!$version)
		$current_version = fetch_sys_var('CURRENT_VERSION');
	else
		$current_version = $version;

	$c = explode(".",$current_version);

	$v = $c[0].".".($c[1] ? $c[1] : '0');
	$v = (float)$v;

	return $v;
}


/**
 * Converts a string to an array limiting the length of each element of the array
 * to the length parameter. It leaves all the words complete and pushes any words
 * that would extend past the length parameter to the next element in the array.
 * Originally added for the Allsteel eorder template.  Copied over to non-class-funcs by Steve
 * on 9-2-08 because of issue with redeclaring the same function in kimball and national eorders.
 *
 * @param string $str
 * @param int $length
 * @return array
 */
function explode_comments($long_str, $length = 40) {
	// Convert comments to an array
	while (strlen($long_str) > 0) {
		$tmp = substr($long_str, 0,$length);
		if (strlen($long_str) > $length) {
			if ($long_str[$length] == " ") {
				$str_arr[] = $tmp;
				$next_pos = $length + 1;
			} else {
				$last_space = strripos($tmp, " ");
				$tmp = substr($tmp, 0, $last_space);
				$str_arr[] = $tmp;
				$next_pos = $last_space + 1;
			}
			$long_str = trim(substr($long_str,$next_pos));
		} else {
			$str_arr[] = trim($long_str);
			unset($long_str);
		}
	}
	return $str_arr;
}

function trim_decimal($num) {
    return (float)rtrim(trim($num, '0'), '.');
}

function float_length($num) {
	$num = trim_decimal($num);
	$num = strlen(substr(strrev($num),0,strpos(strrev($num),'.')));
	return ($num == 0 || $num < 2 ? 2 : $num);
}

function currency_exchange($amount,$currency,$rev=NULL,$precision=2) {
	if (!$amount || bccomp($amount,0,4) == 0)
        return 0;
    if (!$currency || bccomp($currency,0,float_length($currency)) == 0)
        return $amount;

    if ($rev)
        return _round(bcdiv($amount,bcdiv(1,$currency,6),6),$precision);

    return _round(bcdiv($amount,$currency,6),$precision);
}

function htmlspecialchars_uni($text, $entities = true)
{
    return str_replace(
        // replace special html characters
        array('<', '>', '"'),
        array('&lt;', '&gt;', '&quot;'),
        preg_replace(
            // translates all non-unicode entities
            '/&(?!' . ($entities ? '#[0-9]+|shy' : '(#[0-9]+|[a-z]+)') . ';)/si',
            '&amp;',
            $text
        )
    );
}

function posting_date() {
    if (defined('DEFAULT_POSTING_DATE'))
        return DEFAULT_POSTING_DATE;

    return 1;
}

# Check the validity of supplied account_hash against chart of accounts
function valid_account(&$db,$account_hash) {
    if (!$account_hash)
        return false;

    $r = $db->query("SELECT t1.obj_id AS valid
                     FROM accounts t1
                     WHERE t1.account_hash = '".$account_hash."' AND t1.active = 1");
    if ($db->result($r,0,'valid') > 0)
        return true;

    return false;
}

function default_currency_symbol() {
    if (defined('MULTI_CURRENCY')) {
    	global $db;

        $r = $db->query("SELECT t1.symbol
                         FROM currency t1
                         WHERE t1.default = 1");
        if ($row = $db->fetch_assoc($r))
            return $row['symbol'];

    }

    return '$';
}

function writetest($str,$file='core/tmp/test.txt',$mode='w+') {
	if (!$file)
        $file = 'core/tmp/test.txt';

    $fh = fopen(SITE_ROOT.$file,$mode);
    fwrite($fh,$str);
    fclose($fh);

    return;
}

function currency_symbol($currency) {
    global $db;

    $r = $db->query("SELECT currency_symbol('{$currency}') AS symbol");
    return $db->result($r,0,'symbol');
}

function currency_phrase($currency=NULL) {
    global $db;

    $r = $db->query("SELECT currency_phrase(".(isset($currency) ? "'{$currency}'" : "NULL").") AS printed");
    return $db->result($r,0,'printed');

}

function rand_hash($table, $col) {

    $hash = md5( global_classes::get_rand_id(32, "global_classes") );
    while ( global_classes::key_exists($table, $col, $hash) )
        $hash = md5( global_classes::get_rand_id(32, "global_classes") );

    return $hash;
}

function upload_form(&$form) {

	$param = func_get_arg(1);

	return "
	<div style=\"padding-top:5px;font-weight:bold;\" id=\"err2{$param['popup_id']}\">" .
	( $param['label'] ?
		$param['label'] : "File:"
	) . "
	</div>
	<div id=\"iframe\"><iframe src=\"{$param['url']}\" frameborder=\"0\" style=\"" . ( $param['style'] ? $param['style'] : "height:50px;" ) . "\"></iframe></div>
	<div id=\"progress_holder\"></div>" .
	$form->hidden( array(
    	"file_name"     =>   $param['file_name'],
		"file_type"     =>   $param['file_type'],
		"file_size" 	=>   $param['file_size'],
		"file_error" 	=>   $param['file_error']
	) );
}













?>