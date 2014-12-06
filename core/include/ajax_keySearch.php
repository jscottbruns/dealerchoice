<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
defined('DOCUMENT_ROOT') || define('DOCUMENT_ROOT', realpath( dirname(__FILE__) . '/../..') );

set_include_path(
    realpath(APPLICATION_PATH . '/core/include') .
    PATH_SEPARATOR .
    get_include_path()
);

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
session_start();

if ( ! $_SESSION['id_hash'] )
    exit;

require_once 'common_helper.php';

if ( ! defined('DOCUMENT_ROOT') || ( defined('DOCUMENT_ROOT') && ! DOCUMENT_ROOT ) )
    config_error('config6');

$config_result = ini_decrypt($config_location);

define('SITE_ROOT', DOCUMENT_ROOT . '/');

bcscale( ( isset($config_result['math']['decimalprecision']) ? $config_result['math']['decimalprecision'] : 2 ) );

if ( $_SESSION['user_name'] == 'root' )
    define('DEBUG', 1);

if ( defined('DEBUG') ) {

    list($usec, $sec) = explode(' ', microtime());
    $pun_start = ( (float)$usec + (float)$sec );
}

if ( ! $config_result['system']['maxexecutiontime'] || preg_match('/[^0-9]/', $config_result['system']['maxexecutiontime']) )
    $config_result['system']['maxexecutiontime'] = 300;

ini_set('max_execution_time', $config_result['system']['maxexecutiontime']);

errorlog_setup($config_result['system']['errorlogfile']);

require_once 'error_handler.class.php';
set_error_handler(
    array(
        'errorHandler',
        'do_error'
    ),
    E_ALL & ~E_NOTICE
);

setlocale(LC_MONETARY, 'en_US');

// Strip slashes from GET/POST/COOKIE (if magic_quotes_gpc is enabled)
if (get_magic_quotes_gpc())
{
    function stripslashes_array($array)
    {
        return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
    }

    $_GET = stripslashes_array($_GET);
    $_POST = stripslashes_array($_POST);
    $_COOKIE = stripslashes_array($_COOKIE);
    $_REQUEST = stripslashes_array($_REQUEST);
}

set_magic_quotes_runtime(0);
mt_srand((double)microtime()*1000000);

if ( empty($config_result['cookie']['cookie_name']) )
    $config_result['cookie']['cookie_name'] = 'dealerchoice_cookie';

require_once 'db_layer.php';
require_once 'non_class_funcs.php';
require_once 'xml.class.php';

if ( $_SESSION['use_db'] && $_SESSION['use_db'] != $config_result['database']['db_name'] )
    $config_result['database']['db_name'] = $_SESSION['use_db'];

if ( ! defineConfigVars($config_result) )
    die("Fatal Startup Error : Unable to define configuration settings.");

unset($config_result);

$db = new DBLayer(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, CONNECTION_CHARSET);

// Select correct db to use if different that system constant
if (DB_NAME != $_SESSION['use_db']) {
	$db->select_db($_SESSION['use_db']);
}

$_SERVER['HTTP_ACCEPT_ENCODING'] = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';

if ( extension_loaded('zlib') && ( strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false ) ) {

    $_SESSION['O_GZIP'] = true;
    ob_start('ob_gzhandler');
} else
    ob_start();

$session_id = session_id();
$r = $db->query("SELECT id_hash
                 FROM session
                 WHERE session_id = '$session_id'");
$_SESSION['id_hash'] = $db->result($r,0,'id_hash');

while ( list($var, $val) = each($_SESSION['VARIABLE']) )
    define($var, $val);

unset($var, $val);

function str_split_account_no($q) {
    $q_array = str_split($q);
    $q_account_no = '';

    for ( $i = 0; $i < count($q_array); $i++ ) {

        if ( strspn($q_array[$i], "0123456789. ") == 1 )
            $q_account_no .= $q_array[$i];
        else
            break 1;
    }

    if ( trim($q_account_no) != '' )
        return trim($q_account_no);

    return false;
}

/**
 * fixAscii
 *
 * Replace ascii chars with utf8. Note there are ascii characters that don't
 * correctly map and will be replaced by spaces.
 *
 * @author      Robin Cafolla
 * @date        2013-03-22
 * @Copyright   (c) 2013 Robin Cafolla
 * @licence     MIT (x11) http://opensource.org/licenses/MIT
 */
function fixAscii($string) {
    $map = Array(
        '33' => '!', '34' => '"', '35' => '#', '36' => '$', '37' => '%', '38' => '&', '39' => "'", '40' => '(', '41' => ')', '42' => '*',
        '43' => '+', '44' => ',', '45' => '-', '46' => '.', '47' => '/', '48' => '0', '49' => '1', '50' => '2', '51' => '3', '52' => '4',
        '53' => '5', '54' => '6', '55' => '7', '56' => '8', '57' => '9', '58' => ':', '59' => ';', '60' => '<', '61' => '=', '62' => '>',
        '63' => '?', '64' => '@', '65' => 'A', '66' => 'B', '67' => 'C', '68' => 'D', '69' => 'E', '70' => 'F', '71' => 'G', '72' => 'H',
        '73' => 'I', '74' => 'J', '75' => 'K', '76' => 'L', '77' => 'M', '78' => 'N', '79' => 'O', '80' => 'P', '81' => 'Q', '82' => 'R',
        '83' => 'S', '84' => 'T', '85' => 'U', '86' => 'V', '87' => 'W', '88' => 'X', '89' => 'Y', '90' => 'Z', '91' => '[', '92' => '\\',
        '93' => ']', '94' => '^', '95' => '_', '96' => '`', '97' => 'a', '98' => 'b', '99' => 'c', '100'=> 'd', '101'=> 'e', '102'=> 'f',
        '103'=> 'g', '104'=> 'h', '105'=> 'i', '106'=> 'j', '107'=> 'k', '108'=> 'l', '109'=> 'm', '110'=> 'n', '111'=> 'o', '112'=> 'p',
        '113'=> 'q', '114'=> 'r', '115'=> 's', '116'=> 't', '117'=> 'u', '118'=> 'v', '119'=> 'w', '120'=> 'x', '121'=> 'y', '122'=> 'z',
        '123'=> '{', '124'=> '|', '125'=> '}', '126'=> '~', '127'=> ' ', '128'=> '&#8364;', '129'=> ' ', '130'=> ',', '131'=> ' ', '132'=> '"',
        '133'=> '.', '134'=> ' ', '135'=> ' ', '136'=> '^', '137'=> ' ', '138'=> ' ', '139'=> '<', '140'=> ' ', '141'=> ' ', '142'=> ' ',
        '143'=> ' ', '144'=> ' ', '145'=> "'", '146'=> "'", '147'=> '"', '148'=> '"', '149'=> '.', '150'=> '-', '151'=> '-', '152'=> '~',
        '153'=> ' ', '154'=> ' ', '155'=> '>', '156'=> ' ', '157'=> ' ', '158'=> ' ', '159'=> ' ', '160'=> ' ', '161'=> '¡', '162'=> '¢',
        '163'=> '£', '164'=> '¤', '165'=> '¥', '166'=> '¦', '167'=> '§', '168'=> '¨', '169'=> '©', '170'=> 'ª', '171'=> '«', '172'=> '¬',
        '173'=> '­', '174'=> '®', '175'=> '¯', '176'=> '°', '177'=> '±', '178'=> '²', '179'=> '³', '180'=> '´', '181'=> 'µ', '182'=> '¶',
        '183'=> '·', '184'=> '¸', '185'=> '¹', '186'=> 'º', '187'=> '»', '188'=> '¼', '189'=> '½', '190'=> '¾', '191'=> '¿', '192'=> 'À',
        '193'=> 'Á', '194'=> 'Â', '195'=> 'Ã', '196'=> 'Ä', '197'=> 'Å', '198'=> 'Æ', '199'=> 'Ç', '200'=> 'È', '201'=> 'É', '202'=> 'Ê',
        '203'=> 'Ë', '204'=> 'Ì', '205'=> 'Í', '206'=> 'Î', '207'=> 'Ï', '208'=> 'Ð', '209'=> 'Ñ', '210'=> 'Ò', '211'=> 'Ó', '212'=> 'Ô',
        '213'=> 'Õ', '214'=> 'Ö', '215'=> '×', '216'=> 'Ø', '217'=> 'Ù', '218'=> 'Ú', '219'=> 'Û', '220'=> 'Ü', '221'=> 'Ý', '222'=> 'Þ',
        '223'=> 'ß', '224'=> 'à', '225'=> 'á', '226'=> 'â', '227'=> 'ã', '228'=> 'ä', '229'=> 'å', '230'=> 'æ', '231'=> 'ç', '232'=> 'è',
        '233'=> 'é', '234'=> 'ê', '235'=> 'ë', '236'=> 'ì', '237'=> 'í', '238'=> 'î', '239'=> 'ï', '240'=> 'ð', '241'=> 'ñ', '242'=> 'ò',
        '243'=> 'ó', '244'=> 'ô', '245'=> 'õ', '246'=> 'ö', '247'=> '÷', '248'=> 'ø', '249'=> 'ù', '250'=> 'ú', '251'=> 'û', '252'=> 'ü',
        '253'=> 'ý', '254'=> 'þ', '255'=> 'ÿ'
    );

    $search = Array();
    $replace = Array();

    foreach ($map as $s => $r) {
        $search[] = chr((int)$s);
        $replace[] = $r;
    }

    return str_replace($search, $replace, $string);
}

if ( isset($_GET['q']) && $_GET['q'] != '' ) {

    $q = addslashes( trim($_GET['q']) );
    $class = $_GET['class'];

    switch ($class) {

    	case 'proposals':
	        $display_area = $_GET['display_area'];
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];
	        $invoice_type = $_GET['invoice_type'];
	        $install_proposal_hash = $_GET['install_proposal_hash'];

            if ( preg_match('/^(item_ship_to)\-(.*)$/', $parent_name, $matches) )  {

	            $parent_name = $matches[1];
	            $rand_id = $matches[2];
            } if ( preg_match('/^(final_vendor)\-(.*)$/', $parent_name) ) {

                $parent_name = $matches[1];
                $rand_id = $matches[2];
            } elseif ( preg_match('/invoice_to_vendor_/', $parent_name) ) {

	            $parent_name_replace = $parent_name;
	            $parent_name = 'invoice_to_vendor';
	        }

    		switch ($parent_name) {

	            case 'customer':
	            case 'doc_print_customer':
	            case 'discount_customer':

	            $result = $db->query("SELECT customer_hash as ID, customer_name as NAME, street as STREET, city as CITY, state as STATE, zip as ZIP
                                      FROM customers
                                      WHERE customer_name LIKE '{$q}%' AND active = 1 AND deleted = 0
                                      ORDER BY customer_name");
	            $title = "Customer List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'propose_to':

                $result = $db->query("SELECT t1.customer_hash AS PARENT_ID, t1.customer_name AS PARENT_NAME, t1.street AS PARENT_STREET,
                                      t1.city AS PARENT_CITY, t1.state AS PARENT_STATE, t2.location_hash AS ID, t2.location_name AS NAME,
                                      t2.location_street AS STREET, t2.location_city AS CITY, t2.location_state AS STATE
                                      FROM customers t1
                                      LEFT JOIN locations t2 ON t2.entry_hash = t1.customer_hash AND t2.deleted = 0
                                      WHERE
                                      (
                                          t1.customer_name LIKE '{$q}%' AND t1.active = 1 AND t1.deleted = 0
                                      )
                                      OR
                                      (
                                          t2.location_name LIKE '{$q}%' AND t2.entry_type = 'c'
                                      )
                                      ORDER BY t1.customer_name, t2.location_name ASC");
	            $title = "Customer List & Locations:";
	            $style = "style=\"height:125px;overflow:auto;\"";
	            $multi = true;

	            while ($row = $db->fetch_assoc($result))
	                $db_results['customers|'.$row['PARENT_ID']][] = array('PARENT_NAME'       => $row['PARENT_NAME'],
	                                                                      'PARENT_ID'         => $row['PARENT_ID'],
	                                                                      'PARENT_STREET'     => $row['PARENT_STREET'],
	                                                                      'PARENT_CITY'       => $row['PARENT_CITY'],
	                                                                      'PARENT_STATE'      => $row['PARENT_STATE'],
	                                                                      'ID'                => ( $row['ID'] ? "customers|{$row['PARENT_ID']}|{$row['ID']}" : NULL ),
	                                                                      'NAME'              => $row['NAME'],
	                                                                      'STREET'            => $row['STREET'],
	                                                                      'CITY'              => $row['CITY'],
	                                                                      'STATE'             => $row['STATE']);
	            break;

                case 'paying_customer';
	            case 'payee':

                $result = $db->query("SELECT
                                          t1.customer_hash as ID,
                                          t1.customer_name as NAME,
                                          NULL AS IS_VENDOR
                                      FROM customers t1
                                      WHERE t1.customer_name LIKE '{$q}%' AND t1.deleted = 0
                                      UNION
                                      SELECT
                                          t1.vendor_hash as ID,
                                          t1.vendor_name as NAME,
                                          t1.obj_id AS IS_VENDOR
                                      FROM vendors t1
                                      RIGHT JOIN customer_invoice t2 ON t2.customer_hash = t1.vendor_hash AND t2.invoice_to = 'V' AND t2.balance > 0 AND t2.deleted = 0
                                      WHERE t1.vendor_name LIKE '{$q}%' AND t1.deleted = 0
                                      ORDER BY NAME ASC");
	            $title = "Customer/Vendor List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'direct_vendor':
	            case 'item_vendor':
	            case 'final_vendor':
	            case 'comment_vendor':
	            case 'func_vendor':
	            case 'discount_vendor':
	            case 'po_vendor':
	            case 'punch_vendor':
	            case 'invoice_to_vendor':
	            case 'resource_vendor':
	            case 'internal_vendor':

	            if ( $invoice_type == 'R' )
                    $result = $db->query("SELECT customer_hash as ID, customer_name as NAME
                                          FROM customers
                                          WHERE customer_name LIKE '{$q}%' AND active = 1 AND deleted = 0
                                          ORDER BY customer_name ASC");
                else
                    $result = $db->query("SELECT vendor_hash as ID, vendor_name as NAME
                                          FROM vendors
                                          WHERE vendor_name LIKE '{$q}%' AND active = 1 AND deleted = 0
                                          ORDER BY vendor_name ASC");

	            $title = "Vendor List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            if ( $rand_id )
	                $parent_name .= "-{$rand_id}";

	            if ( $parent_name_replace )
	                $parent_name = $parent_name_replace;

	            break;
	            
	            case 'item_no':
	            
            		$result = $db->query("SELECT id as ID, 
            							item_number as NAME,
            							item_desc as DESCR,
            							list_price as STREET
            		FROM item_library
            		WHERE item_number LIKE '{$q}%' AND vendor_hash = '{$parent_id}'
            		ORDER BY item_number ASC");
            
            		$title = "Item Number List:";
            		$style = "style=\"height:125px;overflow:auto;\"";
	            		
	            
	            break;

	            case 'arch':

	            $result = $db->query("SELECT arch_hash as ID, name as NAME
                                      FROM arch_design
                                      WHERE name LIKE '{$q}%' AND deleted = 0
                                      ORDER BY name ASC");
	            $title = "A & D List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'sales_rep':
	            case 'sales_rep_2':
	            case 'proj_mngr':
	            case 'designer':
	            case 'sales_coord':

	            $result = $db->query("SELECT id_hash as ID, full_name as NAME
                                      FROM users
                                      WHERE full_name LIKE '{$q}%' AND active = 1 AND user_name != 'root'
                                      ORDER BY full_name");
	            $title = "Employee List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'import_proposal_no':

                $result = $db->query("SELECT t1.proposal_hash as ID, t1.proposal_no as NAME, t1.proposal_descr as DESCR, t2.customer_name as CUSTOMER
                                      FROM proposals t1
                                      LEFT JOIN customers t2 ON t2.customer_hash = t1.customer_hash
                                      WHERE t1.proposal_no LIKE '%{$q}%'
                                      ORDER BY t1.proposal_no
                                      LIMIT 0, 50");
	            $title = "Proposal List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'ship_to':
	            case 'po_ship_to':
	            case 'doc_print_ship_to':
	            case 'install_addr':
	            case 'billing_addr':
	            case 'item_ship_to':
	            case 'func_ship_to':
	            case 'install_proposal':

	            $multi = true;
	            $title = "Locations List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

                $result = $db->query("SELECT
                                          t1.customer_hash AS PARENT_ID,
                                          t1.customer_name AS PARENT_NAME,
                                          t1.street AS PARENT_STREET,
                                          t1.city AS PARENT_CITY,
                                          t1.state AS PARENT_STATE,
                                          t2.location_hash AS ID,
                                          t2.location_name AS NAME,
                                          t2.location_street AS STREET,
                                          t2.location_city AS CITY,
                                          t2.location_state AS STATE
                                      FROM customers t1
                                      LEFT JOIN locations t2 ON t2.entry_hash = t1.customer_hash AND t2.entry_type = 'c' AND t2.deleted = 0
                                      WHERE
                                      (
                                          t1.customer_name LIKE '{$q}%' AND active = 1 AND t1.deleted = 0
                                      )
                                      OR
                                      (
                                          t2.location_name LIKE '" . ( strlen($q) >= 2 ? "%" : NULL ) . "{$q}%' AND t2.entry_type = 'c'
                                      )
                                      ORDER BY t1.customer_name, t2.location_name ASC");
	            while ($row = $db->fetch_assoc($result))
	                $db_results["customers|{$row['PARENT_ID']}"][] = array('PARENT_NAME'     => $row['PARENT_NAME'],
	                                                                      'PARENT_ID'       => $row['PARENT_ID'],
	                                                                      'PARENT_STREET'   => $row['PARENT_STREET'],
	                                                                      'PARENT_CITY'     => $row['PARENT_CITY'],
	                                                                      'PARENT_STATE'    => $row['PARENT_STATE'],
	                                                                      'ID'              => ( $row['ID'] ? "customers|{$row['PARENT_ID']}|{$row['ID']}" : NULL ),
	                                                                      'NAME'            => preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $row['NAME']),
	                                                                      'STREET'          => $row['STREET'],
	                                                                      'CITY'            => $row['CITY'],
	                                                                      'STATE'           => $row['STATE']);
	            //Now search against the vendors table
                $result = $db->query("SELECT
	                                      t1.vendor_hash AS PARENT_ID, t1.vendor_name AS PARENT_NAME, t1.street AS PARENT_STREET,
	                                      t1.city AS PARENT_CITY, t1.state AS PARENT_STATE, t2.location_hash AS ID, t2.location_name AS NAME,
	                                      t2.location_street AS STREET, t2.location_city AS CITY, t2.location_state AS STATE
                                      FROM vendors t1
                                      LEFT JOIN locations t2 ON t2.entry_hash = t1.vendor_hash AND t2.entry_type = 'v' AND t2.deleted = 0
                                      WHERE
                                      (
                                          t1.vendor_name LIKE '{$q}%' AND t1.active = 1 AND t1.deleted = 0
                                      )
                                      OR
                                      (
                                          t2.location_name LIKE '" . ( strlen($q) >= 2 ? "%" : NULL ) . "{$q}%' AND t2.entry_type = 'v'
                                      )
                                      ORDER BY t1.vendor_name, t2.location_name ASC");
	            if ( $rand_id )
	                $parent_name .= "-{$rand_id}";

	            while ( $row = $db->fetch_assoc($result) )
	                $db_results["vendors|{$row['PARENT_ID']}"][] = array('PARENT_NAME'       => $row['PARENT_NAME'],
	                                                                     'PARENT_ID'         => $row['PARENT_ID'],
	                                                                     'PARENT_STREET'     => $row['PARENT_STREET'],
	                                                                     'PARENT_CITY'       => $row['PARENT_CITY'],
	                                                                     'PARENT_STATE'      => $row['PARENT_STATE'],
	                                                                     'ID'                => ( $row['ID'] ? "vendors|{$row['PARENT_ID']}|{$row['ID']}" : NULL ),
	                                                                     'NAME'              => preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $row['NAME']),
	                                                                     'STREET'            => $row['STREET'],
	                                                                     'CITY'              => $row['CITY'],
	                                                                     'STATE'             => $row['STATE']);

	            break;


    		}

    		$q_results = array();
    		if ( $multi ) {
    			
    			asort($db_results);

	            if ( ! $db_results )
	                $q_results[] = "<div style=\"padding:2px 5px;font-style:italic\">No results...</div>";
	            else {

	            	$k = 0;
	                while ( list($key, $val) = each($db_results) ) {

	                    $k++;
	                    $q_results[] =  "
                        <div id=\"key_result_$k\"
	                         onmouseover=\"javascript:suggestOver(this);reset_key_holder('$k');\"
	                         onmouseout=\"javascript:suggestOut(this);\"
	                         onclick=\"key_clear();" .
	                         ( $parent_name == 'install_proposal' ?
    	                         "agent.call('proposals','sf_loadcontent','cf_loadcontent','finalize_show_tax','proposal_hash=$install_proposal_hash','install_hash=$key');" : NULL
	                         ) . "selectItem('{$parent_name}=" . htmlentities( addslashes($val[0]['PARENT_NAME']), ENT_QUOTES) . "','{$parent_id}={$key}');agent.call('proposals','sf_loadcontent','cf_loadcontent','innerHTML_customer','qtype=$parent_name','id=$key','propose_to_hash=" . ( $parent_name == 'propose_to' ? "$key'" : "' + \$F('propose_to_hash')" ) . ");\"
	                         style=\"padding:2px 5px;\"
	                         innerval=\"" . htmlentities($val[0]['PARENT_NAME'], ENT_QUOTES) . "\"
	                         title=\"" . htmlentities( stripslashes($val[0]['PARENT_STREET']), ENT_QUOTES) . "\n" . htmlentities( stripslashes($val[0]['PARENT_CITY']), ENT_QUOTES) . ",&nbsp;" . htmlentities($val[0]['PARENT_STATE'], ENT_QUOTES) .
                        "\">" .
                        ( strlen($val[0]['PARENT_NAME']) > 35 ?
                            htmlentities( substr($val[0]['PARENT_NAME'], 0, 32), ENT_QUOTES) . "..." : htmlentities($val[0]['PARENT_NAME'], ENT_QUOTES)
                        ) . "
	                    </div>";

	                    if ( count($val) > 0 && $val[0]['ID'] ) {

	                        for ( $i = 0; $i < count($val); $i++ ) {

	                            $k++;
	                            $q_results[] =  "
	                            <li>
	                                <div id=\"key_result_$k\"
	                                     onmouseover=\"javascript:suggestOver(this);reset_key_holder('$k');\"
	                                     onmouseout=\"javascript:suggestOut(this);\"
	                                     onclick=\"key_clear();" .
	                                     ( $parent_name == 'install_proposal' ?
    	                                     "agent.call('proposals','sf_loadcontent','cf_loadcontent','finalize_show_tax','proposal_hash=$install_proposal_hash','install_hash=$key');" : NULL
	                                     ) . "selectItem('{$parent_name}=" . htmlentities( addslashes($val[0]['PARENT_NAME']), ENT_QUOTES) . " : " . htmlentities( addslashes($val[$i]['NAME']), ENT_QUOTES) . "', '{$parent_id}={$val[$i]['ID']}');agent.call('proposals','sf_loadcontent','cf_loadcontent','innerHTML_customer','qtype=$parent_name','id={$val[$i]['ID']}','propose_to_hash=" . ( $parent_name == 'propose_to' ? "{$val[$i]['ID']}'" : "' + \$F('propose_to_hash')") . ");\"
	                                     style=\"padding:2px 5px;margin-left:25px;\"
	                                     innerval=\"" . htmlentities($val[$i]['NAME'], ENT_QUOTES) . "\"
	                                     title=\"" . stripslashes($val[$i]['STREET']) . "\n" . stripslashes($val[$i]['CITY']) . ",&nbsp;{$val[$i]['STATE']}\">" .
	                                     ( strlen($val[$i]['NAME']) > 35 ?
    	                                     htmlentities( substr($val[$i]['NAME'], 0, 32), ENT_QUOTES) . "..." : htmlentities($val[$i]['NAME'], ENT_QUOTES) ) . "
	                                </div>
	                            </li>";
	                        }
	                    }
	                }
	            }

    		} else {

    			$i = 0;
	            while ( $row = $db->fetch_assoc($result) ) {

	            	$row['NAME'] = stripslashes($row['NAME']);
	            	$row['STREET'] = stripslashes($row['STREET']);
	            	$row['CITY'] = stripslashes($row['CITY']);
	            	$desc = $row['DESCR'];
	            	$list = $row['STREET'];

	                $i++;
	                unset($name);
	                if ( $row['CUSTOMER'] ) {

	                    $name = "{$row['CUSTOMER']} - {$row['DESCR']}";
	                    if ( strlen($name) > 50 )
	                        $name = substr($name, 0, 45) . "...";
	                }
					if($parent_name == "item_no"){
                        //If more issues arise use fixAscii
                        $desc = preg_replace( "/\r|\n/", "", $desc );
                        $q_results[] =  "
						<div id=\"key_result_$i\" " .
						( strlen($row['NAME']) > 35 || $row['STREET'] ?
								"title=\"" . htmlentities($row['NAME'], ENT_QUOTES) .
								( $row['STREET'] ?
										"\n" . stripslashes($row['STREET']) .
										( $row['CITY'] ?
												"\n" . stripslashes($row['CITY']) : NULL
										) .
										( $row['STATE'] ?
												", {$row['STATE']}" : NULL
												) .
												( $row['ZIP'] ?
														"\n{$row['ZIP']}" : NULL
														) : NULL
														) . "\"" : NULL
														) . "
														onmouseover=\"javascript:suggestOver(this);reset_key_holder('$i');\"
														onmouseout=\"javascript:suggestOut(this);\"
														onClick=\"key_clear2('".str_replace("\"","&quot;",$desc)."','$list');\$('$parent_name').value='" . htmlentities( addslashes($row['NAME']), ENT_QUOTES) . "';selectItem('{$parent_id}={$row['ID']}');" .
														( strpos($parent_name, 'invoice_to_vendor') !== false ?
																"agent.call('customer_invoice','sf_loadcontent','cf_loadcontent','innerHTML_update_billing_info','vendor_hash={$row['ID']}');" : NULL
																) .
																( $parent_name == 'paying_customer' ?
																		"submit_form(\$('popup_id').form,'customers','exec_post','refresh_form','action=doit_receive_payment','is_vendor=" . ( $row['IS_VENDOR'] ? 1 : NULL ) . "');" :
																		( $parent_name == 'customer' || $parent_name == 'arch' || $parent_name == 'direct_vendor' || $parent_name == 'propose_to' ?
																				"agent.call('proposals','sf_loadcontent','cf_loadcontent','innerHTML_customer','qtype=$parent_name','id={$row['ID']}'" . ( $parent_name == 'customer' ? ",'propose_to_hash=' + \$F('propose_to_hash')" : NULL ) . ");" :
																				( $parent_name == 'item_vendor' || $parent_name == 'func_vendor' ?
																						"agent.call('proposals','sf_loadcontent','cf_loadcontent','innerHTML_customer','qtype=$parent_name','id={$row['ID']}'" .
																						( $parent_name != 'func_vendor' ?
																								",'jscript_action=submit_form($(\'$parent_name\').form,\'proposals\',\'exec_post\',\'refresh_form\',\'action=fill_discounts\',\'item_vendor_hash={$row['ID']}\');'" : ",'func_vendor=1'"
																								) . ");" : NULL
																								)
																								)
																								) . "\"
																								style=\"padding:2px 5px\"
																								innerval=\"" . htmlentities($row['NAME'], ENT_QUOTES) . "\">" .
																								( $name ? "
																										<div style=\"float:right;\">$name</div>" :
																										( $parent_name == 'paying_customer' && $row['IS_VENDOR'] ?
																												"<div style=\"float:right;\">(Vendor)</div>" : NULL
																										)
																								) .
																								( strlen($row['NAME'].$row['STREET'].$row['DESCR']) > 50 ?
																										 htmlentities( addslashes($row['NAME']), ENT_QUOTES) . "  -  $" . htmlentities( addslashes($row['STREET']), ENT_QUOTES) . "  -  " .htmlentities( substr($row['DESCR'], 0, 40), ENT_QUOTES) . "..." : htmlentities( addslashes($row['NAME']), ENT_QUOTES) . "  -  $" . htmlentities( addslashes($row['STREET']), ENT_QUOTES) . "  -  " . htmlentities( addslashes($row['DESCR']), ENT_QUOTES)
																								) . "
																								</div>";
					}else{
						$q_results[] =  "
						<div id=\"key_result_$i\" " .
						( strlen($row['NAME']) > 35 || $row['STREET'] ?
								"title=\"" . htmlentities($row['NAME'], ENT_QUOTES) .
								( $row['STREET'] ?
										"\n" . stripslashes($row['STREET']) .
										( $row['CITY'] ?
												"\n" . stripslashes($row['CITY']) : NULL
										) .
										( $row['STATE'] ?
												", {$row['STATE']}" : NULL
												) .
												( $row['ZIP'] ?
														"\n{$row['ZIP']}" : NULL
														) : NULL
														) . "\"" : NULL
														) . "
														onmouseover=\"javascript:suggestOver(this);reset_key_holder('$i');\"
														onmouseout=\"javascript:suggestOut(this);\"
														onClick=\"key_clear();\$('$parent_name').value='" . htmlentities( addslashes($row['NAME']), ENT_QUOTES) . "';selectItem('{$parent_id}={$row['ID']}');" .
														( strpos($parent_name, 'invoice_to_vendor') !== false ?
																"agent.call('customer_invoice','sf_loadcontent','cf_loadcontent','innerHTML_update_billing_info','vendor_hash={$row['ID']}');" : NULL
																) .
																( $parent_name == 'paying_customer' ?
																		"submit_form(\$('popup_id').form,'customers','exec_post','refresh_form','action=doit_receive_payment','is_vendor=" . ( $row['IS_VENDOR'] ? 1 : NULL ) . "');" :
																		( $parent_name == 'customer' || $parent_name == 'arch' || $parent_name == 'direct_vendor' || $parent_name == 'propose_to' ?
																				"agent.call('proposals','sf_loadcontent','cf_loadcontent','innerHTML_customer','qtype=$parent_name','id={$row['ID']}'" . ( $parent_name == 'customer' ? ",'propose_to_hash=' + \$F('propose_to_hash')" : NULL ) . ");" :
																				( $parent_name == 'item_vendor' || $parent_name == 'func_vendor' ?
																						"agent.call('proposals','sf_loadcontent','cf_loadcontent','innerHTML_customer','qtype=$parent_name','id={$row['ID']}'" .
																						( $parent_name != 'func_vendor' ?
																								",'jscript_action=submit_form($(\'$parent_name\').form,\'proposals\',\'exec_post\',\'refresh_form\',\'action=fill_discounts\',\'item_vendor_hash={$row['ID']}\');'" : ",'func_vendor=1'"
																								) . ");" : NULL
																								)
																								)
																								) . "\"
																								style=\"padding:2px 5px\"
																								innerval=\"" . htmlentities($row['NAME'], ENT_QUOTES) . "\">" .
																								( $name ? "
																										<div style=\"float:right;\">$name</div>" :
																										( $parent_name == 'paying_customer' && $row['IS_VENDOR'] ?
																												"<div style=\"float:right;\">(Vendor)</div>" : NULL
																										)
																								) .
																								( strlen($row['NAME']) > 35 ?
																										htmlentities( substr($row['NAME'], 0, 32), ENT_QUOTES) . "..." : htmlentities($row['NAME'], ENT_QUOTES)
																								) . "
																								</div>";
					}
	                
	            }
	            if ( ! $db->num_rows($result) )
	                $q_results[] = "<div style=\"padding:2px 5px;font-style:italic\">No results...</div>";
    		}

    	break;

        case 'customers':

	        $display_area = $_GET['display_area'];
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];

            switch ( $parent_name ) {

	            case 'customer_name':

	            $result = $db->query("SELECT customer_name as NAME, customer_hash as ID
                                      FROM customers
                                      WHERE customer_name LIKE '{$q}%' AND deleted = 0
                                      ORDER BY customer_name");
	            $title = "Checking for Duplicates...";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;
	        }

            $i = 0;
            $q_results = array();

	        while ( $row = $db->fetch_assoc($result) ) {

	        	$row['NAME'] = stripslashes($row['NAME']);

	            $i++;
	            $q_results[] =  "
	            <div id=\"key_result_$i\"
                    onmouseover=\"javascript:suggestOver(this);reset_key_holder('$i');\"
                    onmouseout=\"javascript:suggestOut(this);\"
                    style=\"padding:2px 5px;\"
                    innerval=\"" . htmlentities($row['NAME'], ENT_QUOTES) . "\"
                    onClick=\"key_clear();\$('$parent_name').value='" . htmlentities( addslashes($row['NAME']), ENT_QUOTES) . "';\"" .
                    ( strlen($row['NAME']) > 30 ?
                        "title=\"" . htmlentities( stripslashes($row['NAME']), ENT_QUOTES) . "\"" : NULL
                    ) . " style=\"padding:2px;\">" .
                    ( strlen($row['NAME']) > 30 ?
                        htmlentities( substr( stripslashes($row['NAME']), 0, 27), ENT_QUOTES) . "..." : htmlentities( stripslashes($row['NAME']), ENT_QUOTES)
                    ) . "
	            </div>";
	        }

        break;

        # Trac #1508
		case 'vendors':

			$display_area = $_GET['display_area'];
			$parent_name = $_GET['parent_name'];
			$parent_id = $_GET['parent_id'];

			switch ($parent_name) {

			    case 'vendor_name':
	            $result = $db->query("SELECT vendor_name as NAME, vendor_hash as ID
                                      FROM vendors
                                      WHERE vendor_name LIKE '{$q}%' AND deleted = 0
                                      ORDER BY vendor_name");
	            $title = "Checking for Duplicates...";
	            $style = "style=\"height:125px;overflow:auto;\"";

                break;
			}

			$i = 0;
			$q_results = array();

			while ( $row = $db->fetch_assoc($result) ) {

				$row['NAME'] = stripslashes($row['NAME']);

			    $i++;
                $q_results[] =  "
                <div id=\"key_result_$i\"
                    onmouseover=\"javascript:suggestOver(this);reset_key_holder('$i');\"
                    onmouseout=\"javascript:suggestOut(this);\"
                    style=\"padding:2px 5px;\"
                    innerval=\"" . htmlentities($row['NAME'], ENT_QUOTES) . "\"
                    onClick=\"key_clear();\$('$parent_name').value='" . htmlentities( addslashes($row['NAME']), ENT_QUOTES) . "';\"" .
                    ( strlen($row['NAME']) > 30 ?
                        "title=\"" . htmlentities( stripslashes($row['NAME']), ENT_QUOTES) . "\"" : NULL
                    ) . " style=\"padding:2px;\">" .
                    ( strlen($row['NAME']) > 30 ?
                        htmlentities( substr( stripslashes($row['NAME']), 0, 27), ENT_QUOTES) . "..." : htmlentities( stripslashes($row['NAME']), ENT_QUOTES)
                    ) . "
                </div>";
			}

		break;

        case 'payables':

	        $display_area = $_GET['display_area'];
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];
	        $po_vendor = $_GET['po_vendor_hash'];
	        $current_invoice_hash = $_GET['invoice_hash'];
	        $type = $_GET['type'];

	        if ( $q == '*' )
	            unset($q);

	        if ( preg_match('/^(.*)\[(.*)\]$/', $parent_name, $matches) ) {

	            $hash = $matches[2];
	            $q_name = $matches[1];
	        } else
	            $q_name = $parent_name;

	        if ( preg_match('/account_name/', $parent_name) )
	            $q_name = "account_name";
	        elseif ( preg_match('/proposal_no_/', $parent_name) )
	            $q_name = "proposal_no";

	        switch ( $q_name ) {

	            case 'account_name':
	            case 'interest_earned_account':
	            case 'service_charge_account':
	            case 'debit_account':
	            case 'credit_account':
	            case 'reconcile_acct':

	            if ( $q )
	                $q_account_no = str_split_account_no($q);

                $result = $db->query("SELECT t1.account_hash as ID, t1.account_name as NAME, t1.account_no AS ACCT_NO, t2.type as TYPE
                                      FROM accounts t1
                                      LEFT JOIN account_types t2 ON t2.type_code = t1.account_type
                                      WHERE " .
                                      ( $q ? "
                                          (
                                              t1.account_name LIKE '{$q}%' OR account_no LIKE '" . ( $q_account_no ? $q_account_no : $q ) . "%'
                                          )
                                          AND t1.active = 1 AND t1.sys_lock = 0" : "t1.active = 1 AND t1.sys_lock = 0"
                                      ) . "
                                      ORDER BY t1.account_no, t1.account_name");
	            $title = "Chart of Accounts:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'po_no':

	            if ( ! $po_vendor )
	                return;

	            if ( $type == 'R' ) {

	                $result = $db->query("SELECT
							                  t1.invoice_hash as ID,
							                  t1.invoice_no as NAME,
							                  t1.amount as AMT
	                                      FROM customer_invoice t1
	                                      WHERE t1.customer_hash = '$po_vendor' AND t1.invoice_no LIKE '{$q}%' AND t1.type = 'I' AND t1.deleted = 0
	                                      ORDER BY t1.invoice_date, t1.invoice_no");

	            } else {

	                $result = $db->query("SELECT
							              t1.po_hash AS ID,
							              t1.po_no AS NAME,
							              t1.currency,
							              t1.exchange_rate,
                                          t1.order_amount AS AMT,
                                          t1.proposal_hash AS PROPOSAL_HASH,
                                          (
                                              SUM(
                                                  CASE
                                                  WHEN t2.type = 'I' " .
                                                  ( $current_invoice_hash ?
                                                      "AND t2.invoice_hash != '{$current_invoice_hash}'" : NULL
                                                  ) . " THEN
	                                                  t2.amount ELSE
                                                      0
                                                  END
                                              ) +
                                              CASE
                                              WHEN t3.reconciled != 0 AND ! ISNULL( t3.reconciled ) THEN
                                                  t3.reconciled ELSE
                                                  0
                                              END
                                            ) AS total_paid_so_far,
                                            SUM(
                                                CASE
                                                WHEN t2.type = 'D' THEN
                                                    t2.amount ELSE
                                                    0
                                                END
                                            ) AS total_deposit_paid
                                            FROM purchase_order t1
                                            LEFT JOIN vendor_payables t2 ON t2.po_hash = t1.po_hash AND t2.deleted = 0
                                            LEFT JOIN (
                                                SELECT
	                                                t3.po_hash,
	                                                SUM( t3.amount ) AS reconciled
                                                FROM purchase_order_reconcile t3
                                                GROUP BY t3.po_hash
                                            ) AS t3 ON t3.po_hash = t1.po_hash
                                            WHERE t1.vendor_hash = '$po_vendor' AND t1.po_no LIKE '%{$q}%' AND t1.deleted = 0
                                            GROUP BY t1.po_hash
                                            LIMIT 0, 50");
	            }

	            $style = "style=\"height:125px;overflow:auto;\"";
				$title = "Open Purchase Orders: ";

                # WIP Account
                $r2 = $db->query("SELECT var_val
                                  FROM system_vars
                                  WHERE var_name = 'DEFAULT_WIP_ACCT'");
                $wip_account = $db->result($r2, 0, 'var_val');

                # Customer deposits acct
                $r2 = $db->query("SELECT var_val
                                  FROM system_vars
                                  WHERE var_name = 'DEFAULT_CUST_DEPOSIT_ACCT'");
                $cust_dep_account = $db->result($r2, 0, 'var_val');
	            if ( $type == 'I' && $wip_account ) {

	            	$accounts = array();
	                $r2 = $db->query("SELECT account_hash, account_name
	                                  FROM accounts
	                                  WHERE account_hash = '$wip_account'");
	                $accounts['account_name'] = $db->result($r2, 0, 'account_name');
	                $accounts['account_hash'] = $db->result($r2, 0, 'account_hash');
	                if ( $accounts['account_hash'] && $accounts['account_name'] )
    	                $fill_acct = true;

	            } elseif ( $type == 'D' && $wip_account ) {

	                $r2 = $db->query("SELECT deposit_percent
	                                  FROM vendors
	                                  WHERE vendor_hash = '$po_vendor'");
	                $deposit_percent = $db->query($r2, 0, 'deposit_percent');

                    $r2 = $db->query("SELECT account_hash, account_name
                                      FROM accounts
                                      WHERE account_hash = '$wip_account'");
                    $accounts['account_name'] = $db->result($r2, 0, 'account_name');
                    $accounts['account_hash'] = $db->result($r2, 0, 'account_hash');
                    if ( $accounts['account_hash'] && $accounts['account_name'] )
                        $fill_acct = true;

	            } elseif ( $type == 'R' && $cust_dep_account ) {

	            	$title = "Customer Invoices: ";
                    $r2 = $db->query("SELECT account_hash, account_name
                                      FROM accounts
                                      WHERE account_hash = '$cust_dep_account'");
                    $accounts['account_name'] = $db->result($r2, 0, 'account_name');
                    $accounts['account_hash'] = $db->result($r2, 0, 'account_hash');
                    if ( $accounts['account_hash'] && $accounts['account_name'] )
                        $fill_acct = true;
	            }

	            break;

	            case 'po_vendor':
	            case 'journal_check_payee':

	            $title = "Vendor List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            if ( $type == 'R' ) {

	            	$title = "Customer List: ";
	                $result = $db->query("SELECT customer_hash as ID, customer_name as NAME, deleted AS DELETED
                                          FROM customers
                                          WHERE customer_name LIKE '{$q}%'
                                          ORDER BY customer_name");
	            } else {

	                $result = $db->query("SELECT vendor_hash as ID, vendor_name as NAME, deleted AS DELETED
                                          FROM vendors
                                          WHERE vendor_name LIKE '{$q}%'
                                          ORDER BY vendor_name");
	            }

	            break;

	            case 'payment_account':
	            case 'DEFAULT_AR_ACCT':
	            case 'DEFAULT_WIP_ACCT':
	            case 'DEFAULT_CASH_ACCT':
	            case 'DEFAULT_VENDOR_DEPOSIT_ACCT':

                if ( $q )
                    $q_account_no = str_split_account_no($q);

	            $result = $db->query("SELECT account_hash as ID, account_no AS ACCT_NO, account_name as NAME, balance as BALANCE
                                      FROM accounts
                                      WHERE
                                      (
                                          account_name LIKE '{$q}%' OR account_no LIKE '" .
                                          ( $q_account_no ?
                                              $q_account_no : $q
                                          ) . "%'
                                      )
                                      AND account_type = 'CA' AND active = 1 " .
                                      ( $parent_name == 'payment_account' ?
                                          "AND checking = 1" : NULL
                                      ) . "
                                      ORDER BY account_no, account_name");
	            $title = "Account List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'DEFAULT_AP_ACCT':
	            case 'DEFAULT_CUST_DEPOSIT_ACCT':
	            case 'SALES_TAX_PAYABLE':
                if ( $q )
                    $q_account_no = str_split_account_no($q);

	            $result = $db->query("SELECT account_hash as ID, account_no AS ACCT_NO, account_name as NAME, balance as BALANCE
                                      FROM accounts
                                      WHERE
                                      (
                                          account_name LIKE '{$q}%' OR account_no LIKE '" .
                                          ( $q_account_no ?
                                              $q_account_no : $q
                                          ) . "%'
                                      )
                                      AND account_type = 'CL' AND active = 1
                                      ORDER BY account_no, account_name");
	            $title = "Account List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'DEFAULT_COGS_ACCT':
	            case 'DEFAULT_DISC_TAKEN_ACCT':
	            case 'SMALL_ORDER_FEE_COGS_ACCT':
	            case 'FREIGHT_COGS_ACCT':
	            case 'FUEL_SURCHARGE_COGS_ACCT':
	            case 'CBD_COGS_ACCT':
                if ( $q )
                    $q_account_no = str_split_account_no($q);

	            $result = $db->query("SELECT account_hash as ID, account_no AS ACCT_NO, account_name as NAME, balance as BALANCE
                                      FROM accounts
                                      WHERE
                                      (
                                          account_name LIKE '{$q}%' OR account_no LIKE '" .
                                          ( $q_account_no ?
                                              $q_account_no : $q
                                          ) . "%'
                                      )
                                      AND
                                      (
                                          account_type = 'EX' OR account_type = 'CG'
                                      )
                                      AND accounts.active = 1
                                      ORDER BY account_no, account_name");
	            $title = "Account List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'SMALL_ORDER_FEE_INCOME_ACCT':
	            case 'FREIGHT_INCOME_ACCT':
	            case 'FUEL_SURCHARGE_INCOME_ACCT':
	            case 'CBD_INCOME_ACCT':

                if ( $q )
                    $q_account_no = str_split_account_no($q);

	            $result = $db->query("SELECT account_hash as ID, account_no AS ACCT_NO, account_name as NAME, balance as BALANCE
                                      FROM accounts
                                      WHERE
                                      (
                                          account_name LIKE '{$q}%' OR account_no LIKE '" .
                                          ( $q_account_no ?
                                              $q_account_no : $q
                                          ) . "%'
                                      )
                                      AND account_type = 'IN' AND active = 1
                                      ORDER BY account_no, account_name");
	            $title = "Account List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'DEFAULT_PROFIT_ACCT':

                if ( $q )
                    $q_account_no = str_split_account_no($q);

	            $result = $db->query("SELECT account_hash as ID, account_no AS ACCT_NO, account_name as NAME, balance as BALANCE
                                      FROM accounts
                                      WHERE
                                      (
                                          account_name LIKE '{$q}%' OR account_no LIKE '" .
                                          ( $q_account_no ?
                                              $q_account_no : $q
                                          ) . "%'
                                      )
                                      AND account_type = 'EQ' AND active = 1
                                      ORDER BY account_no, account_name");
	            $title = "Account List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'wip_clearing_acct':

                if ( $q )
                    $q_account_no = str_split_account_no($q);

	            $result = $db->query("SELECT t1.account_hash as ID, t1.account_no AS ACCT_NO, t1.account_name as NAME, t1.balance as BALANCE, t2.type as TYPE
                                      FROM accounts t1
                                      LEFT JOIN account_types t2 ON t2.type_code = t1.account_type
                                      WHERE
                                      (
                                          t1.account_name LIKE '{$q}%' OR t1.account_no LIKE '" .
                                          ( $q_account_no ?
                                              $q_account_no : $q
                                          ) . "%'
                                      )
                                      AND t1.active = 1 AND t1.account_type IN ('IN','EX','CG')
                                      ORDER BY t1.account_no, t1.account_name");
	            $title = "Account List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'proposal_no':
	            case 'journal_proposal_no':

	            $result = $db->query("SELECT proposal_hash as ID, proposal_no as NAME, proposal_descr as DESCR
                                      FROM proposals
                                      WHERE proposal_no LIKE '%{$q}%'
                                      ORDER BY proposal_no
                                      LIMIT 0, 50");
	            $title = "Proposal List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;
	        }

            $i = 0;
            $q_results = array();

	        while ( $row = $db->fetch_assoc($result) ) {

	        	$row['NAME'] = stripslashes($row['NAME']);
	        	$row['DESCR'] = stripslashes($row['DESCR']);

	        	if ( isset($tmp_onClick) )
                    unset($tmp_onClick);
	        	if ( $q_name == 'po_no' && $row['currency'] && $row['exchange_rate'] ) {
                    $row['AMT'] = currency_exchange($row['AMT'], $row['exchange_rate']);
                    $tmp_onClick = "setCurrency('{$row['currency']}');";
	        	}

	            unset($empty);
	            if ( $type == 'D' && bccomp($deposit_percent, 0, 4) == 1 )
	                $deposit_amt = bcmul($row['AMT'], $deposit_percent, 2);
	            elseif ( $type == 'I' && $q_name == 'po_no' && ! bccomp(0, bcadd($row['AMT'], $row['total_paid_so_far'], 2), 2) )
	                $empty = true;

	            $q_results_str = '';
	            if ( ! $empty ) {

	                $i++;
	                $q_results_str .= "
	                <div id=\"key_result_$i\"
	                     style=\"padding:2px 5px;\"
	                     onmouseover=\"javascript:suggestOver(this);reset_key_holder('$i');\"
	                     onmouseout=\"javascript:suggestOut(this);\"";

	                     switch ( $parent_name ) {

	                        case 'po_no':
	                        $q_results_str .= "
	                        innerval=\"" . htmlentities($row['NAME'], ENT_QUOTES) . "\"
	                        onclick=\"key_clear();selectItem('{$parent_name}=" . htmlentities( addslashes($row['NAME']), ENT_QUOTES) . "','{$parent_id}={$row['ID']}');" .
	                        ( $type != 'R' ?
    	                        "view_po_win('{$row['PROPOSAL_HASH']}','{$row['ID']}');\$('total_amount_holder').innerHTML='\$" . number_format( ( $type == 'I' ? bcsub($row['AMT'], $row['total_paid_so_far'], 2) : ( $deposit_amt ? $deposit_amt : $row['AMT'] ) ), 2) . "';\$('amount_' + \$F('first_rand_err')).value='" . number_format( ( $type == 'I' ? bcsub($row['AMT'], $row['total_paid_so_far'], 2) : ( $deposit_amt ? $deposit_amt : $row['AMT'] ) ), 2) . "';" .
    	                        ( $fill_acct ?
        	                        "\$('account_name_' + \$F('first_rand_err')).value='{$accounts['account_name']}';\$('account_hash_' + \$F('first_rand_err')).value='{$accounts['account_hash']}';" : NULL
    	                        ) . "selectItem('invoice_amount=" . number_format( ( $type == 'I' ? bcsub($row['AMT'], $row['total_paid_so_far'], 2) : ( $deposit_amt ? $deposit_amt : $row['AMT'] ) ), 2) . "');" : NULL
    	                    ) .
    	                    ( $type == 'I' ?
        	                    "submit_form(\$('popup_id').form,'payables','exec_post','refresh_form','action=check_deposits','po_hash={$row['ID']}','popup_id='+\$F('popup_id'));" : NULL
    	                    ) .
    	                    ( $tmp_onClick ?
        	                    $tmp_onClick : NULL
        	                ) . "\" style=\"padding:2px 3px;\" >
	                        <div style=\"float:right;padding-right:5px;\">" .
        	                ( $type == 'I' ?
                                ( bccomp( bcsub($row['AMT'], $row['total_paid_so_far'], 2), 0) == -1 ?
    	                            "(\$" . number_format( bcmul( bcsub($row['AMT'], $row['total_paid_so_far'], 2), -1, 2), 2) . ")" : '$' . number_format( bcsub($row['AMT'], $row['total_paid_so_far'], 2), 2)
                                ) : '$' . number_format($row['AMT'], 2)
                            ) .
                            ( $q_name == 'po_no' && $row['currency'] && $row['exchange_rate'] ?
                                "&nbsp;{$row['currency']}" : NULL
                            ) . "
	                        </div>" . htmlentities($row['NAME'], ENT_QUOTES);
	                        break;

	                        default:
			                if ( $row['NAME'] && $row['ACCT_NO'] )
			                    $name = ( $row['ACCT_NO'] ? $row['ACCT_NO']." : " : NULL ) . $row['NAME'];
			                else
			                    $name = $row['NAME'];

	                        $q_results_str .= "
	                        innerval=\"" . htmlentities($name, ENT_QUOTES) . "\"
	                        onclick=\"key_clear();selectItem('{$parent_name}=" . htmlentities( addslashes($name), ENT_QUOTES) . "','{$parent_id}={$row['ID']}');" .
	                        ( $parent_name == 'payment_account' ?
    	                        "submit_form(\$('payment_account').form,'payables','exec_post','refresh_form','action=amount_tracker','payment_account_hash={$row['ID']}');" :
    	                        ( $parent_name == 'po_no' && $type != 'R' ?
        	                        ( $fill_acct ?
            	                        "\$('amount_' + \$F('first_rand_err')).value='" . number_format( ( $deposit_amt ? $deposit_amt : $row['AMT']), 2) . "';\$('account_name_' + \$F('first_rand_err')).value='{$accounts['account_name']}';\$('account_hash_' + \$F('first_rand_err')).value='{$accounts['account_hash']}';" : NULL
            	                    ) . "selectItem('invoice_amount=" . number_format( ( $deposit_amt ? $deposit_amt : $row['AMT'] ), 2) . "');" : NULL
            	                )
            	            ) .
            	            ( $parent_name == 'po_vendor' && $type != 'R' ?
                	            "\$('po_no').disabled=0;\$('po_no').title='';\$('view_po_holder').innerHTML='';submit_form(\$('popup_id').form,'payables','exec_post','refresh_form','action=vendor_due_date','vendor_hash={$row['ID']}','popup_id='+\$F('popup_id'));" : NULL
            	            ) . "\" style=\"padding:2px 3px;" . ( $row['DELETED'] ? "color:#8f8f8f;font-style:italic;" : NULL ) . "\" " . ( $row['DELETED'] ? "title=\"This vendor has been deleted.\"" : NULL ) . ">" .
	                        ( $row['TYPE'] || $row['BALANCE'] || $row['DESCR'] ?
	                            "<div style=\"float:right;padding-right:5px;\">" .
	                            ( $row['DESCR'] ?
    	                            htmlentities($row['DESCR'], ENT_QUOTES) :
                                    ( $row['TYPE'] ?
                                        $row['TYPE'] :
                                        ( bccomp($row['BALANCE'], 0, 2) == 1 ?
                                            "\$" . number_format($row['BALANCE'], 2) : "<span style=\"color:#ff0000;\">(\$" . number_format( bcmul($row['BALANCE'], -1, 2), 2) . ")</span>"
                                        )
                                    )
                	            ) . "</div>" : NULL
                	        ) . "
	                        " . htmlentities($name, ENT_QUOTES) .
                	        ( $row['AMT'] ? "
    	                        &nbsp;<i>\$" . number_format($row['AMT'], 2) . "</i>" : NULL
                	        );

	                        break;
	                     }

	                $q_results_str .= "
	                </div>";

	                $q_results[] = $q_results_str;
	            }
	        }

        break;

        case 'accounting':

	        $display_area = $_GET['display_area'];
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];

            if ( $q == '*' )
                unset($q);

            if ( preg_match('/^(.*)\[(.*)\]$/', $parent_name, $matches) ) {

                $hash = $matches[2];
                $q_name = $matches[1];
            } else
                $q_name = $parent_name;

	        switch ( $q_name ) {

	            case 'account_name':

                if ( $q )
                    $q_account_no = str_split_account_no($q);

	            $result = $db->query("SELECT
                        	              t1.account_hash as ID,
                        	              CASE
                        	              WHEN ( LENGTH(t1.account_name) + LENGTH(t1.account_no) ) > 33 THEN
	                        	              CONCAT( SUBSTR(t1.account_name, 1, 30), '...') ELSE
	                        	              t1.account_name
                        	              END AS NAME,
                        	              t1.account_no AS ACCT_NO,
                        	              t2.type as TYPE
                                      FROM accounts t1
                                      LEFT JOIN account_types t2 ON t2.type_code = t1.account_type
                                      WHERE " .
                        	          ( $q ?
                                          "(
                                	          t1.account_name LIKE '{$q}%' OR account_no LIKE '" .
                                	          ( $q_account_no ?
                                    	          $q_account_no : $q
                                    	      ) . "%'
                                    	  )
                                    	  AND t1.active = 1" : "t1.active = 1"
                                      ) . "
                                      AND t1.account_hash != '" . DEFAULT_PROFIT_ACCT . "'
                                      ORDER BY t1.account_no, t1.account_name");
	            $title = "Chart of Accounts:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'journal_check_payee':

	            $result = $db->query("SELECT vendor_hash as ID, vendor_name as NAME
                                      FROM vendors
                                      WHERE vendor_name LIKE '{$q}%' AND active = 1
                                      ORDER BY vendor_name");
	            $title = "Vendor List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'name':

	            $result = $db->query("SELECT
								          vendor_hash AS ID,
								          vendor_name AS NAME,
								          'Vendor: ' AS tablelabel,
								          'vendor' AS tablename
                                      FROM vendors
                                      WHERE vendor_name LIKE '{$q}%' AND active = 1
                                      UNION
                                      SELECT
	                                      customer_hash AS ID,
	                                      customer_name AS NAME,
	                                      'Customer: ' AS tablelabel,
	                                      'customer' AS tablename
                                      FROM customers
                                      WHERE customer_name LIKE '{$q}%' AND active = 1
                                      ORDER BY NAME ASC");
	            $title = "Customer/Vendor List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;
	        }

            $i = 0;
            $q_results = array();

	        while ( $row = $db->fetch_assoc($result) ) {

	        	$row['NAME'] = stripslashes($row['NAME']);

	        	if ( $q_name == 'account_name' && $row['ACCT_NO'] )
                    $name = ( $row['ACCT_NO'] ? "{$row['ACCT_NO']} : " : NULL ) . $row['NAME'];
                else
                    $name = $row['NAME'];

	            $i++;
                $q_results[] = "
                <div id=\"key_result_$i\"
                    style=\"padding:2px 5px;\"
                    onmouseover=\"javascript:suggestOver(this);reset_key_holder('$i');\"
                    onmouseout=\"javascript:suggestOut(this);\"
                    innerval=\"" . htmlentities($name, ENT_QUOTES) . "\"
                    onclick=\"key_clear();selectItem('{$parent_name}=" . htmlentities( addslashes($name), ENT_QUOTES) . "','{$parent_id}=" . ( $q_name == 'name' ? "{$row['tablename']}_" : NULL ) . "{$row['ID']}');" .
                    ( $parent_name == 'journal_check_payee' ?
                        "agent.call('accounting','sf_loadcontent','cf_loadcontent','vendor_remit_to','vendor_hash={$row['ID']}','popup_id='+\$F('popup_id'));" : NULL
                    ) . "\" style=\"padding:2px 3px;\" >" .
                    ( $row['TYPE'] || $row['BALANCE'] ?
                        "<div style=\"float:right;padding-right:5px;\">" .
                        ( $row['TYPE'] ?
                            $row['TYPE'] :
                            ( bccomp($row['BALANCE'], 0, 2) == 1 ?
                                "\$" . number_format($row['BALANCE'], 2) : "<span style=\"color:#ff0000;\">(\$" . number_format( bcmul($row['BALANCE'], -1, 2) ,2) . ")</span>"
                            )
                        ) . "</div>" : NULL
                    ) .
                    ( $q_name == 'name' ?
	                    "<i>{$row['tablelabel']}</i>" : NULL
                    ) .
                    htmlentities($name, ENT_QUOTES) .
                    ( $row['AMT'] ? "
                        &nbsp;<i>\$" . number_format($row['AMT'], 2) . "</i>" : NULL
                    ) . "
                </div>";
	        }

        break;

        case 'reports':

	        $display_area = $_GET['display_area'];
	        $q = trim($_GET['q']);
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];

            switch ( $parent_name ) {

	            case 'customer':
	            $result = $db->query("SELECT customer_hash as ID, customer_name as NAME, city as CITY, state as STATE, zip as ZIP, deleted AS DELETED
                                      FROM customers
                                      WHERE customer_name LIKE '{$q}%'
                                      ORDER BY customer_name
                                      LIMIT 0, 50");
	            $title = "Customer List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'proposal':
	            $result = $db->query("SELECT proposal_hash as ID, proposal_no as NAME, proposal_descr as DESCR
                                      FROM proposals
                                      WHERE proposal_no LIKE '%{$q}%'
                                      ORDER BY proposal_no
                                      LIMIT 0, 50");
	            $title = "Proposal List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'vendor':
	            $result = $db->query("SELECT vendor_hash as ID, vendor_name as NAME, city as CITY, state as STATE, zip as ZIP, deleted AS DELETED
                                      FROM vendors
                                      WHERE vendor_name LIKE '{$q}%'
                                      ORDER BY vendor_name
                                      LIMIT 0, 50");
	            $title = "Vendor List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'customer_vendor':

                $result = $db->query("SELECT
                                          t1.customer_hash as ID,
                                          t1.customer_name as NAME,
                                          t1.city AS CITY,
                                          t1.state AS STATE,
                                          t1.zip AS ZIP,
                                          t1.deleted AS DELETED,
                                          NULL AS IS_VENDOR
                                      FROM customers t1
                                      WHERE t1.customer_name LIKE '{$q}%'
                                      UNION
                                      SELECT
                                          t1.vendor_hash as ID,
                                          t1.vendor_name as NAME,
                                          t1.city AS CITY,
                                          t1.state AS STATE,
                                          t1.zip AS ZIP,
                                          t1.deleted AS DELETED,
                                          t1.obj_id AS IS_VENDOR
                                      FROM vendors t1
                                      WHERE t1.vendor_name LIKE '{$q}%'
                                      ORDER BY NAME ASC");
                $title = "Customer/Vendor List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	            case 'check_no':

				$result = $db->query("SELECT check_no as ID, check_no as NAME
									  FROM check_register
									  WHERE check_no LIKE '$q%'
									  GROUP BY ID
									  ORDER BY NAME");
	            $title = "Check List:";
	            $style = "style=\"height:125px;overflow:auto;\"";

	            break;

	        }

            $q_results = array();
            $i = 0;

            while ( $row = $db->fetch_assoc($result) ) {

            	$row['NAME'] = stripslashes($row['NAME']);
            	if ( $row['DESCR'] )
                	$row['DESCR'] = stripslashes($row['DESCR']);

	            $i++;
	            if ( $parent_name == 'customer' )
	                $innerHTML .= "
	                <div style=\"padding:5px;\" id=\"customer_{$row['ID']}\">
	                    &nbsp;" . htmlspecialchars($row['NAME'], ENT_QUOTES) . "
	                </div>";
	            elseif ( $parent_name == 'vendor' )
	                $innerHTML .= "
	                <div style=\"padding:5px;\" id=\"vendor_{$row['ID']}\">
	                    &nbsp;" . htmlspecialchars($row['NAME'], ENT_QUOTES) . "
	                </div>";

	            $q_results[] = "
	            <div id=\"key_result_{$i}\" " .
                    ( strlen($row['NAME']) > 35 || $row['DELETED'] ?
                        "title=\"" .
                        ( $row['DELETED'] ?
                            "This entry has been deleted. \n" : NULL
                        ) . htmlentities($row['NAME'], ENT_QUOTES) .
                        ( $row['STREET'] ?
                            "\n" . htmlentities($row['STREET'], ENT_QUOTES) .
                            ( $row['CITY'] ?
                                "\n" . htmlentities($row['CITY'], ENT_QUOTES) : NULL
                            ) .
                            ( $row['STATE'] ?
                                ", " . htmlentities($row['STATE'], ENT_QUOTES) : NULL
                            ) .
                            ( $row['ZIP'] ?
                                "\n{$row['ZIP']}" : NULL
                            ) : NULL
                        ) . "\"" : NULL
                    ) . "
                    onmouseover=\"javascript:suggestOver(this);reset_key_holder('$i');\"
                    onmouseout=\"javascript:suggestOut(this);\"
                    innerval=\"" . htmlentities($row['NAME'], ENT_QUOTES) . "\"
                    onClick=\"key_clear();" .
                    ( $parent_name == 'customer' || $parent_name == 'vendor' || $parent_name == 'proposal' || $parent_name == 'customer_vendor' || $parent_name == 'check_no' ?
                        "clear_values('$parent_name');window.setTimeout('\$(\'$parent_name\').focus();',100);\$('{$parent_name}_filter_holder').innerHTML += '<div id=\'{$parent_name}_{$row['ID']}\' style=\'padding:5px 2px 0 5px;\'>[<small><a href=\'javascript:void(0);\' onClick=\'remove_el(this.parentElement.parentElement)\' class=\'link_standard\' title=\'Remove from filter\'>x</a></small>]&nbsp;".
                        ( strlen($row['NAME']) > 25 ?
                            htmlentities( addslashes( substr($row['NAME'], 0, 22) ), ENT_QUOTES) . "..." : htmlentities( addslashes($row['NAME']), ENT_QUOTES)
                        ) . "<input type=\'hidden\' id=\'{$parent_name}_filter\' name=\'{$parent_name}_filter[]\' value=\'{$row['ID']}\' /></div>';toggle_display('{$parent_name}_filter_holder','block');" : NULL
                    ) . "\"
                    style=\"padding:2px 5px;" . ( $row['DELETED'] ? "color:#757575;font-style:italic;" : NULL )."\">" .
                    ( $parent_name == 'customer_vendor' && $row['IS_VENDOR'] ?
                        "<div style=\"float:right;\">(Vendor)</div>" : NULL
                    ) .
                    ( $row['DESCR'] ? "
                        <div style=\"float:right;\">" . htmlentities( ( strlen($row['DESCR']) > 45 ? substr($row['DESCR'],0,45) : $row['DESCR'] ), ENT_QUOTES) . "</div>" : NULL
                    ) .
                    ( strlen($row['NAME']) > 35 ?
                        htmlentities( substr($row['NAME'], 0, 32), ENT_QUOTES) . "..." : htmlentities($row['NAME'], ENT_QUOTES)
                    ) . "
	            </div>";
	        }

        break;

        # TODO: Continue syntax formatting from here

        case 'alerts':
	        $display_area = $_GET['display_area'];
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];

            switch ($parent_name) {
	            case 'contact_email':
	            $result = $db->query("SELECT users.user_name as ID , users.full_name as NAME
	                                        FROM users
	                                        WHERE users.full_name LIKE '{$q}%' OR users.user_name LIKE '{$q}%' AND user_name != 'root'
	                                        ORDER BY NAME ASC");
	            $title = "Contact List:";
	            $style = "style=\"height:125px;overflow:auto;\"";
	            break;
	        }
            $q_results = array();
            $i = 0;
            while ($row = $db->fetch_assoc($result)) {
            	$row['NAME'] = stripslashes($row['NAME']);

	            $i++;
	            $q_results[] = "
	            <div id=\"key_result_".$i."\"
                     onmouseover=\"javascript:suggestOver(this);reset_key_holder('".$i."');\"
                     onmouseout=\"javascript:suggestOut(this);\"
                     innerval=\"".htmlentities($row['NAME'],ENT_QUOTES)."\"
	                 onClick=\"key_clear();\$('to').value += '".htmlentities(addslashes($row['NAME']),ENT_QUOTES)." &lt;".$row['ID']."&gt; \\n';window.setTimeout('toggle_display(\'email_search_menu\',\'none\');clear_values(\'contact_email\');',100);\"
	                 style=\"padding:2px 5px\">
	                 ".htmlentities($row['NAME'],ENT_QUOTES) . "&nbsp;&lt;".$row['ID']."&gt;
	            </div>";
	        }

        break;

        case 'customer_credits':

	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];
	        $customer_hash = $_GET['customer_hash'];

	        if (ereg("\[",$parent_name)) {
	            $hash = substr(strchr($parent_name,"["),1,32);
	            $q_name = substr($parent_name,0,strpos($parent_name,"["));
	        } else
	            $q_name = $parent_name;

	        switch ($q_name) {
	            case 'proposal_no':
                $result = $db->query("SELECT t1.proposal_hash as ID, t1.proposal_no as NAME, t1.proposal_descr as DESCR
                                      FROM proposals t1
                                      WHERE t1.proposal_no LIKE '%{$q}%' AND t1.customer_hash = '$customer_hash'
                                      ORDER BY t1.proposal_no
                                      LIMIT 0, 50");
	            $title = "Proposal List:";
	            $style = "style=\"height:125px;overflow:auto;\"";
	            break;

	            case 'credit_customer':
	            $result = $db->query("SELECT t1.customer_name as NAME, t1.customer_hash as ID
                                      FROM customers t1
                                      WHERE ( t1.customer_name LIKE '{$q}.%' OR t1.customer_name LIKE '{$q}%' )
                                          AND t1.deleted = 0
                                      ORDER BY t1.customer_name");
	            $title = "Customer List";
	            $style = "style=\"height:125px;overflow:auto;\"";
	            break;

	        }

	        $q_results = array();
	        $i = 0;
            while ($row = $db->fetch_assoc($result)) {

            	$row['NAME'] = stripslashes($row['NAME']);

	            $i++;
	            $q_results[] = "
	            <div id=\"key_result_{$i}\" " . ( strlen($row['NAME']) > 35 ? "title=\"" . htmlentities($row['NAME'], ENT_QUOTES) . "\"" : NULL ) . "
                    onmouseover=\"javascript:suggestOver(this);reset_key_holder('{$i}');\"
                    onmouseout=\"javascript:suggestOut(this);\"
                    innerval=\"" . htmlentities($row['NAME'], ENT_QUOTES) . "\"
	                onClick=\"key_clear();\$('{$parent_name}').value='" . htmlentities( addslashes($row['NAME']), ENT_QUOTES) . "';selectItem('{$parent_id}={$row['ID']}');" .
	                ( $parent_name == 'credit_customer' ?
    	                "\$('proposal_no').disabled=0;\$('proposal_no').title='';\$('proposal_no').value='';\$('proposal_no').focus()" : NULL
	                ) .
	                ( $parent_name == 'proposal_no' ?
    	                "\$('credit_amount').focus();" : NULL
	                ) . "\"
	                style=\"padding:2px 5px\">" .
	                ( $parent_name == 'proposal_no' ? "
	                    <div style=\"float:right;padding-right:5px;\">" .
                        ( strlen( $row['DESCR'] ) > 35 ?
                            htmlentities( substr($row['DESCR'], 0, 42), ENT_QUOTES) . '...' : htmlentities($row['DESCR'], ENT_QUOTES)
                        ) . "
	                    </div>" : NULL
	                ) . htmlentities($row['NAME'], ENT_QUOTES) . "
	            </div>";
	        }


        break;

        case 'mail':
	        $display_area = $_GET['display_area'];
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];
	        $win_popup_id = $_GET['popup_id'];

            switch ($parent_name) {
	            case 'proposal_no':
	            case 'work_order_proposal_no':
	            $result = $db->query("SELECT proposals.proposal_hash as ID, proposals.proposal_no as NAME ,
	                                       proposals.proposal_descr as DESCR, customers.customer_name as CUSTOMER
	                                        FROM proposals
	                                        LEFT JOIN customers ON customers.customer_hash = proposals.customer_hash
	                                        WHERE proposals.proposal_no LIKE '%{$q}%'
	                                        ORDER BY proposals.proposal_no");
	            $title = "Proposal List:";
	            $style = "style=\"height:125px;overflow:auto;\"";
	            break;

	            case 'contact_email':
	            $result = $db->query("SELECT customer_contacts.contact_email as ID, customer_contacts.contact_name as NAME
	                                        FROM customer_contacts
	                                        WHERE customer_contacts.contact_name LIKE '{$q}%' AND customer_contacts.contact_email != ''
	                                        UNION
	                                        SELECT vendor_contacts.contact_email as ID, vendor_contacts.contact_name as NAME
	                                        FROM vendor_contacts
	                                        WHERE vendor_contacts.contact_name LIKE '{$q}%' AND vendor_contacts.contact_email != ''
	                                        ORDER BY NAME ASC");
	            $title = "Contact List:";
	            $style = "style=\"height:125px;overflow:auto;\"";
	            break;

	            case 'contact_fax':
	            $result = $db->query("SELECT customer_contacts.contact_fax as ID, customer_contacts.contact_name as NAME
	                                        FROM customer_contacts
	                                        WHERE customer_contacts.contact_name LIKE '{$q}%' AND customer_contacts.contact_fax != ''
	                                        UNION
	                                        SELECT vendor_contacts.contact_fax as ID, vendor_contacts.contact_name as NAME
	                                        FROM vendor_contacts
	                                        WHERE vendor_contacts.contact_name LIKE '{$q}%' AND vendor_contacts.contact_fax != ''
	                                        UNION
	                                        SELECT vendors.fax as ID, vendors.vendor_name as NAME
	                                        FROM vendors
	                                        WHERE vendors.vendor_name LIKE '{$q}%' AND vendors.fax != ''
	                                        ORDER BY NAME ASC");
	            $title = "Contact List:";
	            $style = "style=\"height:125px;overflow:auto;\"";
	            break;
	        }

	        $q_results = array();
	        $i = 0;
            while ($row = $db->fetch_assoc($result)) {
            	$row['NAME'] = stripslashes($row['NAME']);
            	$row['CUSTOMER'] = stripslashes($row['CUSTOMER']);
            	$row['DESCR'] = stripslashes($row['DESCR']);
            	$row['CUSTOMER'] = stripslashes($row['CUSTOMER']);
	            $i++;

	            $name = $row['CUSTOMER']." - ".$row['DESCR'];

	            $q_results[] = "
	            <div id=\"key_result_".$i."\"".($name ? "
	                 title=\"".htmlentities($name,ENT_QUOTES)."\"" : NULL)."
                     onmouseover=\"javascript:suggestOver(this);reset_key_holder('".$i."');\"
                     onmouseout=\"javascript:suggestOut(this);\"
                     innerval=\"".htmlentities($row['NAME'],ENT_QUOTES)."\"
	                 onClick=\"key_clear();".($parent_name == 'contact_email' || $parent_name == 'contact_fax' ?
	                    ($parent_name == 'contact_fax' ?
	                        "\$('fax').value = '".$row['ID']."';\$('to').value = '".htmlentities(addslashes($row['NAME']),ENT_QUOTES)."';" : "\$('recipient_list').value += '".htmlentities(addslashes($row['NAME']),ENT_QUOTES)." &lt;".$row['ID']."&gt; \\n';")."clear_values('".$parent_name."');" : "selectItem('".$parent_name."=".htmlentities(addslashes($row['NAME']),ENT_QUOTES)."');").($parent_name == 'contact_fax' || $parent_name == 'contact_email' ? "setTimeout('toggle_display(\'".($parent_name == 'contact_fax' ? "fax" : "email")."_search_menu\',\'none\');',50);" : NULL).($parent_name == 'work_order_proposal_no' ? "window.setTimeout('popup_windows[\'".$win_popup_id."\'].hide()',500);agent.call('pm_module','sf_loadcontent','show_popup_window','edit_work_order','popup_id=line_item','proposal_hash=".$row['ID']."');" : ($parent_name != 'contact_email' && $parent_name != 'contact_fax' ? "agent.call('mail','sf_loadcontent','show_popup_window','agent','popup_id=line_item','proposal_hash=".$row['ID']."');" : NULL))."\"
	                 style=\"padding:2px 5px\">".($parent_name == 'contact_email' || $parent_name == 'contact_fax' ?
	                    htmlentities($row['NAME'],ENT_QUOTES) . "&nbsp;".($parent_name == 'contact_email' ? "&lt;" : "(").$row['ID'].($parent_name == 'contact_email' ? "&gt;" : ")") : "
	                    <div style=\"float:right;\">
	                        ".htmlentities((strlen($name) > 45 ? substr($name,0,42)."..." : $name),ENT_QUOTES)."
	                    </div>
	                    ".htmlentities($row['NAME'],ENT_QUOTES))."
	            </div>";
	        }

        break;

        case 'pm_module':
	        $display_area = $_GET['display_area'];
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];

            switch ($parent_name) {
	            case 'item_resource':
	            $result = $db->query("SELECT resources.resource_hash as ID, resources.resource_name as NAME
	                                        FROM resources
	                                        WHERE resource_name LIKE '%{$q}%' AND active = 1
	                                        ORDER BY resource_name");
	            $title = "Resource List:";
	            $style = "style=\"height:125px;overflow:auto;\"";
	            break;

	        }
	        $q_results = array();
	        $i = 0;
	        while ($row = $db->fetch_assoc($result)) {
	            $i++;
	            $q_results[] = "
	            <div id=\"key_result_".$i."\" ".(strlen($row['NAME']) > 35 ? "title=\"".htmlentities($row['NAME'],ENT_QUOTES)."\"" : NULL)."
                     onmouseover=\"javascript:suggestOver(this);reset_key_holder('".$i."');\"
                     onmouseout=\"javascript:suggestOut(this);\"
                     innerval=\"".htmlentities($row['NAME'],ENT_QUOTES)."\"
	                 onClick=\"selectItem('".$parent_name."=".htmlentities(addslashes($row['NAME']),ENT_QUOTES)."','".$parent_id."=".$row['ID']."');setTimeout('if(\$F(\'item_resource_hash\')){submit_form(\$(\'order_hash\').form,\'pm_module\',\'exec_post\',\'refresh_form\',\'action=fill_resource_data\');}',100);\"
	                 style=\"padding:2px 5px\">
	                 ".(strlen($row['NAME']) > 35 ?
        	            htmlentities(substr($row['NAME'],0,32),ENT_QUOTES)."..." : htmlentities($row['NAME'],ENT_QUOTES))."
	            </div>";
	        }

        break;

        case 'sys':
            $display_area = $_GET['display_area'];
            $parent_name = $_GET['parent_name'];
            $parent_id = $_GET['parent_id'];

            if (ereg("commission_user_",$parent_name))
                $q_name = 'commission_user';
            else
                $q_name = $parent_name;

            switch ($q_name) {
                case 'commission_user':
                $result = $db->query("SELECT users.id_hash as ID, users.full_name as NAME
                                      FROM users
                                      WHERE full_name LIKE '{$q}%' AND active = 1 AND user_name != 'root'
                                      ORDER BY full_name");
                $title = "Employee List:";
                $style = "style=\"height:125px;overflow:auto;\"";
                break;

            }
            $q_results = array();
            $i = 0;
            while ($row = $db->fetch_assoc($result)) {
            	$row['NAME'] = stripslashes($row['NAME']);

                $i++;
                $q_results[] = "
                <div id=\"key_result_".$i."\" ".(strlen($row['NAME']) > 35 ? "title=\"".htmlentities($row['NAME'],ENT_QUOTES)."\"" : NULL)."
                     onmouseover=\"javascript:suggestOver(this);reset_key_holder('".$i."');\"
                     onmouseout=\"javascript:suggestOut(this);\"
                     innerval=\"".htmlentities($row['NAME'],ENT_QUOTES)."\"
                     onClick=\"selectItem('".$parent_name."=".htmlentities(addslashes($row['NAME']),ENT_QUOTES)."','".$parent_id."=".$row['ID']."');\"
                     style=\"padding:2px 5px\">".htmlentities($row['NAME'],ENT_QUOTES)."
                </div>";
            }

        break;

		// Enable customer/vendor search for Trac #1286
        case 'customer_vendor':
	        $parent_name = $_GET['parent_name'];
	        $parent_id = $_GET['parent_id'];

	        if (ereg("\[",$parent_name)) {
	            $hash = substr(strchr($parent_name,"["),1,32);
	            $q_name = substr($parent_name,0,strpos($parent_name,"["));
	        } else
	            $q_name = $parent_name;

	        switch ($q_name) {
	           case 'customer':
	            $result = $db->query("SELECT customers.customer_name as NAME, customers.customer_hash as ID
                                        FROM customers
                                        WHERE customer_name LIKE '{$q}%' AND deleted = 0
                                        ORDER BY customer_name");
	            $title = "Customer List";
	            $style = "style=\"height:125px;overflow:auto;\"";
	           break;

	           case 'vendor':
	            $result = $db->query("SELECT vendors.vendor_name as NAME, vendors.vendor_hash as ID
                                        FROM vendors
                                        WHERE vendor_name LIKE '{$q}%' and deleted = 0
                                        ORDER BY vendor_name");
	            $title = "Vendor List";
	            $style = "style=\"height:125px;overflow:auto;\"";
	           break;
	        }

	        $q_results = array();
	        $i = 0;
            while ($row = $db->fetch_assoc($result)) {
            	$row['NAME'] = stripslashes($row['NAME']);

	            $i++;
	            $q_results[] = "
	            <div id=\"key_result_".$i."\" ".(strlen($row['NAME']) > 35 ? "title=\"".htmlentities($row['NAME'],ENT_QUOTES)."\"" : NULL)."
                    onmouseover=\"javascript:suggestOver(this);reset_key_holder('".$i."');\"
                    onmouseout=\"javascript:suggestOut(this);\"
                    innerval=\"".htmlentities($row['NAME'],ENT_QUOTES)."\"
	                onClick=\"key_clear();\$('".$parent_name."').value='".htmlentities(addslashes($row['NAME']),ENT_QUOTES)."';selectItem('".$parent_id."=".$row['ID']."');\"
	                style=\"padding:2px 5px\">".($parent_name == 'invoice_no' ? "
	                    <div style=\"float:right;padding-right:5px;\">".($row['AMT'] < 0 ?
	                        "($".number_format($row['AMT'] * -1,2).")" : "$".number_format($row['AMT'],2))."
	                    </div>" : NULL).(strlen($row['NAME']) > 35 ?
	                        htmlentities(substr($row['NAME'],0,32),ENT_QUOTES)."..." : htmlentities($row['NAME'],ENT_QUOTES))."
	            </div>";
	        }


        break;
    }

    $xml = new dC_AJAX_XML_Builder('text/xml',DEFAULT_CHARSET);
    $xml->add_group('root');

    if ($title || $headerHTML)
        $xml->add_tag('keySearchHeader',
                      ($headerHTML ?
                          $headerHTML : "<div class=\"function_menu_item\" style=\"font-weight:bold;border-bottom:1px solid #8c8c8c\" >
							                <div style=\"float:right;padding-right:1px;\">
							                    <a href=\"javascript:void(0);\" onClick=\"toggle_display('keyResults','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
							                </div>
							                $title
							            </div>"),'','',true);

    if ($style)
        $xml->add_tag('keySearchStyle',$style,'','',true);

    $xml->add_group('keySearchEntry');
    for ($i = 0; $i < count($q_results); $i++)
        $xml->add_tag('entry',$q_results[$i],'','',true);

    $xml->close_group();
    $xml->close_group();


    $xml->print_xml();
    exit;
}



?>