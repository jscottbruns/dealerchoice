<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class errorHandler {

	private static $level = array(
        1       =>  'E_ERROR  ',
        2       =>  'E_WARNING',
        4       =>  'E_PARSE  ',
        8       =>  'E_NOTICE ',
        16      =>  'CORE_ERRO',
        32      =>  'CORE_WARN',
        64      =>  'COMP_ERRO',
        128     =>  'COMP_WARN',
        256     =>  'USER_ERRO',
        512     =>  'DATAB_ERR',
        1024    =>  'USER_NOTI',
        2048    =>  'STRCT_WAR',
        4096    =>  'RVRBL_ERR',
        8192    =>  'DEPRECATE',
        16384   =>  'USER_DEPR',
        30719   =>  'E_ALL    '
	);

	static function do_error() {

		$errno = func_get_arg(0);
		$errstr = func_get_arg(1);
		$file = func_get_arg(2);
		$line = func_get_arg(3);

		if ( ! defined('ERROR_FILE') )
    		return false;

        if ( ! file_exists( dirname(ERROR_FILE) ) ) { # Check the existence of err/

	        if ( ! mkdir( dirname(ERROR_FILE), 0777, true) )
		        return false;
        }

        if ( ! file_exists( ERROR_FILE ) ) {

            if ( ! touch(ERROR_FILE) )
            	return false;

        } elseif ( ! is_writable( ERROR_FILE ) ) {

            if ( ! chmod ( ERROR_FILE, 0777) )
                return false;
        }

        if ( $fh = fopen(ERROR_FILE, 'a') ) {

        	if ( ! preg_match('/^<!--E_LEVEL-->/', $errstr) ) {

                $file = find_root($file);
                $errstr = "<!--E_LEVEL-->[" . strftime('%c') . "] [" . ( $_SESSION['user_name'] ? $_SESSION['user_name'] : "anonymous" ) . "@" . ( $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : "unknown" ) . "] [{$file}:{$line}] $errstr";
        	}

            $errstr = preg_replace('/^(<!--E_LEVEL-->)(.*)$/m', self::$level[ $errno ] . ' $2', $errstr);

            fwrite($fh, "$errstr \r\n");
            fclose($fh);
        }

        return true;
	}
}
?>