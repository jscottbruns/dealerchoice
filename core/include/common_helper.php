<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
define('E_DATABASE_ERROR',512);
$from_common = 1;

function ini_decrypt() {

	if ( ! defined('DOCUMENT_ROOT') ) {

		print "Error - Premature call to ini file parse. If problem persists delete all temporary internet files, close browser and repeat login process.";
    	exit;
	}

	$ini_file = DOCUMENT_ROOT . '/core/include/config/dealerchoice.ini';

	if ( ! file_exists($ini_file) ) {

		if ( function_exists('config_error') )
    		config_error('config2');
    	else
    		print "Error - Cannot stat system settings file $ini_file. Please check filesystem for valid ini file or contact your system administrator.";

		exit;
	}

    $ini_array = parse_ini_file($ini_file, 1);

    foreach ( $ini_array as $key => $val ) {

        foreach ( $val as $sect_key => $sect_val ) {

            $el_key = preg_replace("/^$key\.(.*)$/", "$1", $sect_key);
            $ini_array[ $key ][ strtolower($el_key) ] = $sect_val;
            unset($ini_array[ $key ][ $sect_key ]);
        }
    }

    if ( ! $ini_array || ! is_array($ini_array) ) {

    	if ( function_exists('config_error') )
            config_error('config2');
        else
            print "Error - Cannot parse ini settings file. Please check filesystem for valid ini file or contact your system administrator.";

    	exit;
    }

    #if (count($ini_array) < 3 || count($ini_array['system']) < 4 || count($ini_array['database']) < 7 || count($ini_array['cookie']) < 4)
    #    ( defined('CRON') ? die('-1') : config_error('config1') );

    define('PUN',1);

    return $ini_array;
}

function defineConfigVars($config_array) {

    if ( ! is_array($config_array) )
        return false;

    $define_sections = array(
        'database'  =>  array(
            'databasetype'          =>  'db_type',
            'databasehost'          =>  'db_host',
            'defaultdatabase'       =>  'db_name',
            'databaselist'          =>  'db_list',
            'databaseuser'          =>  'db_username',
            'databasepass'          =>  'db_password',
            'databaseport'          =>  'db_port',
            'connectioncharset'     =>  'connection_charset',
            'connectioncollation'   =>  'connection_collation'
        ),
        'system'    =>  array(
            'installdir'            =>  'install_dir',
            'sitedomain'            =>  'site_domain',
            'sitealias'             =>  'site_alias',
            'licenseno'             =>  'license_no',
            'defaultcharset'        =>  'default_charset',
            'templatedir'           =>  'template_dir'
        ),
        'cookie'    =>  array(
            'cookiename'            =>  'cookie_name',
            'cookiedomain'          =>  'cookie_domain',
            'cookiepath'            =>  'cookie_path',
            'cookiesecure'          =>  'cookie_secure',
            'cookieseed'            =>  'cookie_seed'
        )
    );

    foreach ( $config_array as $section => $value_array ) {

        if ( isset($define_sections[$section]) ) {

            while ( list($key, $val) = each($value_array) ) {

                if ( isset($define_sections[$section][$key]) )
                    define( strtoupper($define_sections[$section][$key]), $val);
                else
                    define( strtoupper($key), $val);
            }
        }
    }

    return true;
}

function config_error($err) {

    if (defined('CRON'))
        exit('-1');

    header("Location: /config_error.php?".base64_encode($err));
    exit;
}

function find_root($file) {

    if ( $file && defined('SITE_ROOT') ) {

        $path = preg_split('/\\\|\//', $file);

        if ( $doc_root = preg_split('/\\\|\//', SITE_ROOT) ) {

            $delim = array_pop($doc_root);
            if ( ! $delim ) # Since the site_root has a trailing slash, last elem will be empty
                $delim = array_pop($doc_root);
        }

        $doc_match = preg_grep("/^$delim$/", $path);
        $file = implode( array_slice($path, ( key($doc_match) + 1 ) ), '/');
    }

    return $file;
}

function root_path($full_path) {

    $full_path = dirname($full_path);
    $path_split = preg_split('/\/|\\\/', $full_path);
    array_shift($path_split);

    if ( in_array( $path_split[ count($path_split) - 1 ], array('core', 'include') ) )
        $full_path = root_path($full_path);

    return preg_replace('/^(.*)([\/|\\\])$/', '$1', $full_path);
}

function start_timer() {

    list($usec, $sec) = explode(' ', microtime());
    $pun_start = ( (float)$usec + (float)$sec );

    return $pun_start;
}

function errorlog_setup($errfile) {

	if ( ! $errfile || ! file_exists($errfile) ) {

	    if ( $errfile && ! file_exists( dirname($errfile) ) )
	        mkdir( dirname($errfile), 0777, true);

	    if ( ! $errfile || ( $errfile && ! touch($errfile) ) )
	        $errfile = realpath( SITE_ROOT . 'err/dealerchoice.err' );
	}

    define('ERROR_FILE', $errfile);
}

function countrylist_setup($_names, $_codes) {

	if ( ! defined('MY_COMPANY_COUNTRY') || ( defined('MY_COMPANY_COUNTRY') && MY_COMPANY_COUNTRY == 'US' ) ) {

	    $country_names = array(
	        "UNITED STATES",
	        "CANADA",
	        "MEXICO"
	    );
	    $country_codes = array(
	        "US",
	        "CA",
	        "MX"
	    );
	} elseif ( defined('MY_COMPANY_COUNTRY') && MY_COMPANY_COUNTRY == 'CA' ) {

	    $country_names = array(
	        "CANADA",
	        "UNITED STATES",
	        "MEXICO"
	    );
	    $country_codes = array(
	        "CA",
	        "US",
	        "MX"
	    );
	}

	$country_names = array_merge($country_names, $_names);
	$country_codes = array_merge($country_codes, $_codes);

	return array(
        $country_names,
        $country_codes
    );
}


/**
 * @author   "Sebastián Grignoli" <grignoli@framework2.com.ar>
 * @package  forceUTF8
 * @version  1.1
 * @link     http://www.framework2.com.ar/dzone/forceUTF8-es/
 * @example  http://www.framework2.com.ar/dzone/forceUTF8-es/
 */

function forceUTF8($text){
        /**
         * Function forceUTF8
         *
         * This function leaves UTF8 characters alone, while converting almost all non-UTF8 to UTF8.
         *
         * It may fail to convert characters to unicode if they fall into one of these scenarios:
         *
         * 1) when any of these characters:   ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖ×ØÙÚÛÜÝÞß
         *    are followed by any of these:  ("group B")
         *                                    ¡¢£¤¥¦§¨©ª«¬­®¯°±²³´µ¶•¸¹º»¼½¾¿
         * For example:   %ABREPRESENT%C9%BB. «REPRESENTÉ»
         * The "«" (%AB) character will be converted, but the "É" followed by "»" (%C9%BB)
         * is also a valid unicode character, and will be left unchanged.
         *
         * 2) when any of these: àáâãäåæçèéêëìíîï  are followed by TWO chars from group B,
         * 3) when any of these: ðñòó  are followed by THREE chars from group B.
         *
         * @name forceUTF8
         * @param string $text  Any string.
         * @return string  The same string, UTF8 encoded
         *
         */

        if(is_array($text))
        {
                foreach($text as $k => $v)
                {
                        $text[$k] = forceUTF8($v);
                }
                return $text;
        }

        $max = strlen($text);
        $buf = "";
        for($i = 0; $i < $max; $i++){
                $c1 = $text{$i};
                if($c1>="\xc0"){ //Should be converted to UTF8, if it's not UTF8 already
                        $c2 = $i+1 >= $max? "\x00" : $text{$i+1};
                        $c3 = $i+2 >= $max? "\x00" : $text{$i+2};
                        $c4 = $i+3 >= $max? "\x00" : $text{$i+3};
                        if($c1 >= "\xc0" & $c1 <= "\xdf"){ //looks like 2 bytes UTF8
                                if($c2 >= "\x80" && $c2 <= "\xbf"){ //yeah, almost sure it's UTF8 already
                                        $buf .= $c1 . $c2;
                                        $i++;
                                } else { //not valid UTF8.  Convert it.
                                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                                        $cc2 = ($c1 & "\x3f") | "\x80";
                                        $buf .= $cc1 . $cc2;
                                }
                        } elseif($c1 >= "\xe0" & $c1 <= "\xef"){ //looks like 3 bytes UTF8
                                if($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf"){ //yeah, almost sure it's UTF8 already
                                        $buf .= $c1 . $c2 . $c3;
                                        $i = $i + 2;
                                } else { //not valid UTF8.  Convert it.
                                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                                        $cc2 = ($c1 & "\x3f") | "\x80";
                                        $buf .= $cc1 . $cc2;
                                }
                        } elseif($c1 >= "\xf0" & $c1 <= "\xf7"){ //looks like 4 bytes UTF8
                                if($c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf" && $c4 >= "\x80" && $c4 <= "\xbf"){ //yeah, almost sure it's UTF8 already
                                        $buf .= $c1 . $c2 . $c3;
                                        $i = $i + 2;
                                } else { //not valid UTF8.  Convert it.
                                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                                        $cc2 = ($c1 & "\x3f") | "\x80";
                                        $buf .= $cc1 . $cc2;
                                }
                        } else { //doesn't look like UTF8, but should be converted
                                $cc1 = (chr(ord($c1) / 64) | "\xc0");
                                $cc2 = (($c1 & "\x3f") | "\x80");
                                $buf .= $cc1 . $cc2;
                        }
                } elseif(($c1 & "\xc0") == "\x80"){ // needs conversion
                        $cc1 = (chr(ord($c1) / 64) | "\xc0");
                        $cc2 = (($c1 & "\x3f") | "\x80");
                        $buf .= $cc1 . $cc2;
                } else { // it doesn't need convesion
                        $buf .= $c1;
                }
        }
        return $buf;
}

?>
