<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
/*///////////////////////////////
File: include/common.php
Desr: This file contains all the core
includes needed through the site. This
file should be the fist included file on
all primary files
//////////////////////////////////*/

defined('DOCUMENT_ROOT') || define('DOCUMENT_ROOT', realpath( dirname(__FILE__) . '/../..') );

set_include_path(
	realpath(DOCUMENT_ROOT . '/core/include') .
	PATH_SEPARATOR .
	get_include_path()
);

require_once 'common_helper.php';

if ( ! defined('DOCUMENT_ROOT') || ( defined('DOCUMENT_ROOT') && ! DOCUMENT_ROOT ) )
    config_error('config6');

$config_result = ini_decrypt($config_location);

# session_set_cookie_params(0,'/',$config_result['cookie']['cookie_domain']);
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
session_start();

define('LINK_ROOT', "http://{$config_result['system']['sitedomain']}");
define('LINK_ROOT_SECURE', "https://{$config_result['system']['sitedomain']}");
define('URL_HOST', substr( $config_result['system']['sitedomain'], 0, strlen($config_result['system']['sitedomain']) ) );
define('URL_ALIAS', substr( $config_result['system']['sitealias'], 0, strlen($config_result['system']['sitealias']) ) );
define('SITE_ROOT', DOCUMENT_ROOT . '/');
define('UPLOAD_DIR', SITE_ROOT . 'core/tmp/');

require_once 'config_private.php';

while ( list($key, $val) = each($config_private) )
	define($key, $val);

unset($config_private);

if ( $_SESSION['user_name'] == 'root' )
	define('DEBUG', 1);

if ( defined('DEBUG') )
    $pun_start = start_timer();

if ( ! $config_result['system']['maxexecutiontime'] || preg_match('/[^0-9]/', $config_result['system']['maxexecutiontime']) )
    $config_result['system']['maxexecutiontime'] = 300;

ini_set('max_execution_time', $config_result['system']['maxexecutiontime']);

errorlog_setup();

require_once 'error_handler.class.php';
set_error_handler(
    array(
        'errorHandler',
        'do_error'
    ),
    E_ALL & ~E_NOTICE
);

if ( ! $config_result['system']['currencyLocale'] )
    $config_result['system']['currencyLocale'] = 'en_US';

setlocale(LC_MONETARY, $config_result['system']['currencyLocale']);

# Strip slashes from GET/POST/COOKIE (if magic_quotes_gpc is enabled)
if ( get_magic_quotes_gpc() ) {

	function stripslashes_array($array) {
		return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
	}

	$_GET = stripslashes_array($_GET);
	$_POST = stripslashes_array($_POST);
	$_COOKIE = stripslashes_array($_COOKIE);
	$_REQUEST = stripslashes_array($_REQUEST);
}

set_magic_quotes_runtime(0);
mt_srand( (double)microtime() * 1000000 );

if ( empty($config_result['cookie']['cookie_name']) )
	$config_result['cookie']['cookie_name'] = 'dealerchoice_cookie';

require_once 'db_layer.php';

if ( isset($_SESSION['use_db']) && $_SESSION['use_db'] != $config_result['database']['db_name'] )
	$config_result['database']['db_name'] = $_SESSION['use_db'];

if ( ! $config_result['system']['templatedir'] || ! file_exists($config_result['system']['templatedir']) )
    $config_result['system']['templatedir'] = realpath(DOCUMENT_ROOT . '/core/images/e_profile');

if ( ! preg_match('/\/$/', $config_result['system']['templatedir']) )
    $config_result['system']['templatedir'] .= '/';

if ( ! defineConfigVars($config_result) )
    config_error('config5');

$db = new DBLayer(
    DB_HOST,
    DB_USERNAME,
    DB_PASSWORD,
    DB_NAME,
    ( defined('CONNECTION_CHARSET') ?
        CONNECTION_CHARSET : ''
    ),
    ( defined('CONNECTION_COLLATION') ?
        CONNECTION_COLLATION : ''
    )
) or ( config_error('config7') );

require_once 'globals.class.php';
require_once 'non_class_funcs.php';
require_once 'validator.class.php';
require_once 'form_funcs.class.php';
require_once 'xml.class.php';
require_once 'ajax_library.class.php';
require_once 'login_funcs.class.php';
require_once 'search.class.php';
require_once 'permissions.class.php';
require_once 'alerts.class.php';
require_once 'override.class.php';

if ( ! defined('CRON') ) { # Load session vars into constant definitions

    if ( isset($login_class->reload_session) )
        $login_class->load_system_vars();

    $login_class->load_system_page_vars();

    if ( $_SESSION['VARIABLE']['MULTI_CURRENCY'] && ! $_SESSION['VARIABLE']['FUNCTIONAL_CURRENCY'] )
        unset($_SESSION['VARIABLE']['MULTI_CURRENCY'], $_SESSION['DB_DEFINE']['MULTI_CURRENCY']);

    $login_class->load_system_definitions();
    $login_class->load_database_definitions();
}

if ( ! defined('DEFAULT_CHARSET') )
    define('DEFAULT_CHARSET', 'iso-8859-1');

if ( ! $config_result['math']['decimalprecision'] )
    $config_result['math']['decimalprecision'] = 2;

bcscale($config_result['math']['decimalprecision']);

list($country_names, $country_codes) = countrylist_setup($tmp_country_names, $tmp_country_codes);
unset($tmp_country_names, $tmp_country_codes);

if ( ! defined('DEFAULT_TIMEZONE') )
    define('DEFAULT_TIMEZONE', "-5:00");

date_default_timezone_set($timezone_map[DEFAULT_TIMEZONE]);

$db->set_timezone(DEFAULT_TIMEZONE);

// Load the global functions libraries
if (!defined('CRON'))
    require_once 'browser.php';

require_once 'math.class.php';
require_once 'xml_parser.class.php';

//Load the various class files
require_once 'custom_fields.class.php';
require_once 'customers.class.php';
require_once 'vendors.class.php';
require_once 'proposals.class.php';
require_once 'line_items.class.php';
require_once 'arch_design.class.php';
require_once 'discounting.class.php';
require_once 'accounting.class.php';
require_once 'purchase_order.class.php';
require_once 'reports.class.php';
require_once 'customer_invoice.class.php';
require_once 'customer_credits.class.php';
require_once 'payables.class.php';
require_once 'doc_vault.class.php';
require_once 'system_config.class.php';
require_once 'mail.class.php';
require_once 'support.class.php';
require_once 'chat.class.php';
require_once 'pm_module.class.php';
require_once 'templates.class.php';

// Assign the default currency symbol
if (function_exists('default_currency_symbol'))
    define('SYM',default_currency_symbol());

$db->define('default_currency_symbol',(defined('SYM') ? SYM : '$'));

$_SERVER['HTTP_ACCEPT_ENCODING'] = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';

// Should we use gzip output compression?
if (!defined('CRON')) {
	if (extension_loaded('zlib') && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false || strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') !== false)) {
		$_SESSION['O_GZIP'] = true;
		ob_start('ob_gzhandler');
	} else
		ob_start();

}

unset($config_result);
?>
