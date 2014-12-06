<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
function config_decrypt($config_location) {
    if ($config_location) {
        $config = file($config_location);
        $ck = 0;

        $config_array = array();
        for ($i = 0; $i < count($config); $i++) {
            $config[$i] = trim($config[$i]);
            if (substr(trim($config[$i]),0,1) == '[' && substr(strrev(trim($config[$i])),0,1) == ']') {
                $section = substr($config[$i],1,strlen($config[$i])-2);
                $config_array[$section] = array();
            }
            if ($config[$i] && ereg("=",$config[$i])) {
                list($name,$var) = explode("=",$config[$i]);
                $var = trim($var);
                $name = trim($name);
                switch ($section) {
                    case 'database':
                        switch (trim($name)) {
                            case 'database.DatabaseType':
                            case 'DatabaseType':
                                $config_array[$section]['db_type'] = $var;
                            break;

                            case 'database.DatabaseHost':
                            case 'DatabaseHost':
                                $config_array[$section]['db_host'] = $var;
                            break;

                            case 'database.DefaultDatabase':
                            case 'DefaultDatabase':
                                $config_array[$section]['db_name'] = $var;
                            break;

                            case 'database.DatabaseList':
                            case 'DatabaseList':
                                $config_array[$section]['db_list'] = $var;
                            break;

                            case 'database.DatabaseUser':
                            case 'DatabaseUser':
                                $config_array[$section]['db_username'] = $var;
                            break;

                            case 'database.DatabasePass':
                            case 'DatabasePass':
                                $config_array[$section]['db_password'] = $var;
                            break;

                            case 'database.DatabasePort':
                            case 'DatabasePort':
                                $config_array[$section]['db_port'] = $var;
                            break;

                            case 'database.ConnectionCharset':
                            case 'ConnectionCharset':
                                $config_array[$section]['connection_charset'] = $var;
                            break;

                            case 'database.ConnectionCollation':
                            case 'ConnectionCollation':
                                $config_array[$section]['connection_collation'] = $var;
                            break;
                        }
                    break;

                    case 'cookie':
                        switch (trim($name)) {
                            case 'cookie.CookieName':
                            case 'CookieName':
                                $config_array[$section]['cookie_name'] = $var;
                            break;

                            case 'cookie.CookieDomain':
                            case 'CookieDomain':
                                $config_array[$section]['cookie_domain'] = $var;
                            break;

                            case 'cookie.CookiePath':
                            case 'CookiePath':
                                $config_array[$section]['cookie_path'] = $var;
                            break;

                            case 'cookie.CookieSecure':
                            case 'CookieSecure':
                                $config_array[$section]['cookie_secure'] = $var;
                            break;

                            case 'cookie.CookieSeed':
                            case 'CookieSeed':
                                $config_array[$section]['cookie_seed'] = $var;
                            break;
                        }
                    break;

                    case 'system':
                        switch (trim($name)) {
                            case 'system.InstallDir':
                            case 'InstallDir':
                                $config_array[$section]['install_dir'] = $var;
                            break;

                            case 'system.SiteDomain':
                            case 'SiteDomain':
                                $config_array[$section]['site_domain'] = $var;
                            break;

                            case 'system.SiteAlias':
                            case 'SiteAlias':
                                $config_array[$section]['site_alias'] = $var;
                            break;

                            case 'system.LicenseNo':
                            case 'LicenseNo':
                                $config_array[$section]['license_no'] = $var;
                            break;

                            case 'system.Licensee':
                            case 'Licensee':
                                $config_array[$section]['licensee'] = $var;
                            break;

                            case 'system.DefaultCharset':
                            case 'DefaultCharset':
                                $config_array[$section]['default_charset'] = $var;
                            break;
                        }
                    break;

                    case 'math':
                        switch (trim($name)) {
                            case 'math.DecimalPrecision':
                            case 'DecimalPrecision':
                                $config_array[$section]['DecimalPrecision'] = $var;
                            break;
                        }
                    break;
                }
            }
        }
        if (count($config_array) < 3 || count($config_array['system']) < 4 || count($config_array['database']) < 7 || count($config_array['cookie']) < 4)
            (defined('CRON') ? die('-1') : config_error('config1'));

        define('PUN',1);
        return $config_array;
    } else
        config_error('config2');
}

function defineConfigVars($config_array) {
    if (!is_array($config_array))
        return false;

    $define_sections = array('database','system','cookie');
    foreach ($config_array as $section => $value_array) {
        if (in_array($section,$define_sections)) {
            while (list($key,$val) = each($value_array)) {
                define(strtoupper($key),$val);
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
?>