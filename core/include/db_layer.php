<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
/***********************************************************************

  Copyright (C) 2002-2005  Rickard Andersson (rickard@punbb.org)

  This file is part of PunBB.

  PunBB is free software; you can redistribute it and/or modify it
  under the terms of the GNU General Public License as published
  by the Free Software Foundation; either version 2 of the License,
  or (at your option) any later version.

  PunBB is distributed in the hope that it will be useful, but
  WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston,
  MA  02111-1307  USA

************************************************************************/


// Make sure we have built in support for MySQL
if (!function_exists('mysql_connect'))
	exit('This PHP environment doesn\'t have MySQL support built in. Dealer-Choice needs MySQL database support in order to function properly.');

// Make sure no one attempts to run this script "directly"
if (!defined('PUN'))
	exit;

//
// Return current timestamp (with microseconds) as a float (used in dblayer)
//

function get_microtime() {
	list($usec, $sec) = explode(' ', microtime());
	return ((float)$usec + (float)$sec);
}


class DBLayer extends UserStack {

	var $prefix;
	var $link_id;
	var $query_result;
	public $db_error;
	public $db_errno;
	var $connection_id;
	var $version;
	var $current_user;

	// Character set stuff
	public $character_set_client;
	public $character_set_connection;
	public $character_set_database;
	public $character_set_results;
	public $character_set_server;
	public $character_set_system;

	var $saved_queries = array();
	var $num_queries = 0;

	var $transaction;
	var $transaction_queries;
	var $deadlock;

	public $session_vars = array();

	function DBLayer($db_host, $db_username, $db_password, $db_name, $charset='', $collation='') {

		$this->prefix = NULL;

		if ( $this->link_id = mysql_connect($db_host, $db_username, $db_password, 1) ) {

			$r = $this->query("SELECT
                    		   CONNECTION_ID() AS cid,
                    		   VERSION() AS v");
			$this->connection_id = $this->result($r, 0, 'cid');
			$this->version = $this->result($r, 0, 'v');
			$this->current_user = $_SESSION['id_hash'];
			
			$this->set_charset('utf8',$this->link_id);

	        if ( isset($charset) && ! empty($charset) )
                $this->set_charset($charset, $collation);

			if ( $this->select_db($db_name) )
				return $this->link_id;

            return false;

		} else {

			$this->db_error = 'Unable to connect to MySQL server: ' . mysql_error();
			$this->db_errno = mysql_errno();

			return false;
		}
	}

	function select_db($db_name) {
		if (!$this->link_id) {
            $this->db_error = "Connection to database [".$db_name."] has failed. ".mysql_error();
            $this->db_errno = mysql_errno();
            return false;
		}
		if (@mysql_select_db($db_name, $this->link_id))
			return true;
	    else {
	    	$this->db_error = mysql_error();
	    	$this->db_errno = mysql_errno($this->link_id);
			return false;
	    }
	}


	function start_transaction() {
        if (!$this->link_id) {
            if (!$this->db_error) {
	            $this->db_error = "Unable to start transaction. ".mysql_error();
	            $this->db_errno = mysql_errno();
            }
            return false;
        }
		if ( $this->start_process() ) {

			if ( $this->query("BEGIN") ) {

				$this->transaction = true;
				$this->transaction_queries = array();

				return true;

			} else {
				$this->db_error = "Unable to start transaction. " . mysql_error($this->link_id);
				$this->db_errno = mysql_errno();
				$this->end_process($this->connection_id);

				return false;
			}
		} else {
			$this->db_error = "An active database process is currently running. Please wait for the current process to finish before starting a new process.";
            $this->db_errno = "DB100";
			return false;
		}
	}


	function end_transaction($rollback=false) {
		$this->transaction = false;
		$this->transaction_queries = array();

		$this->end_process($this->connection_id);

		if ($rollback)
		    $this->query("ROLLBACK");
		else
		    $this->query("COMMIT");

		return;
	}

	function query($sql, $unbuffered = false) {

        if ( ! $this->link_id ) {

        	if ( ! $this->db_error ) {

                $this->db_error = "Connection to the database ".(defined('DB_NAME') ? "[".DB_NAME."] " : NULL)."has failed. ".mysql_error();
                $this->db_errno = mysql_errno();
        	}

            return false;
        }

		if ( defined('PUN_SHOW_QUERIES') )
			$q_start = get_microtime();

		unset($this->db_errno, $this->db_error);

		if ( $this->transaction == true && !$this->deadlock )
			$this->transaction_queries[] = $sql;

		if ( $unbuffered )
			$this->query_result = @mysql_unbuffered_query($sql, $this->link_id);
		else
			$this->query_result = @mysql_query($sql, $this->link_id);

		if ( defined('PUN_SHOW_QUERIES') )
			$this->saved_queries[] = array($sql, ($this->query_result ? sprintf('%.5f', get_microtime() - $q_start) : false));

		if ( $this->query_result ) {

			++$this->num_queries;

			return $this->query_result;

		} else {

			$this->db_errno = mysql_errno($this->link_id);
			$this->db_error = mysql_error($this->link_id);

			if ( defined('SITE_ROOT') ) {

				if ( $this->db_errno != 1213 && $this->db_errno != 1205 ) {

                    $q = explode("\n", $sql);
                    $count = count($q);
                    for ( $i = 0; $i < $count; $i++ )
                        $q_str .= trim($q[$i]) . ( $i < $count - 1 ? "\n" : NULL );
				}

	            $fh = fopen(SITE_ROOT . 'err/db_query.log', 'a');
    	        fwrite($fh,"[" . strftime('%c') . "] [" . ( $_SESSION['user_name'] ? $_SESSION['user_name'] : "anonymous" ) . "@" . ( $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : "unknown" ) . "] {$this->db_errno} {$this->db_error} \n----------START QUERY OUTPUT----------\n{$q_str}\n----------END QUERY OUTPUT----------\n");
    	        fclose($fh);

	            unset($q, $q_str);
			}

			# ER_LOCK_DEADLOCK
			if ( ( $this->db_errno == 1213 || $this->db_errno == 1205 ) && ! $this->deadlock ) {

				$this->deadlock = true;
				$deadlock_res = $this->resolve_deadlock( ( $this->db_errno == 1205 ? $sql : NULL) );

				if ( $deadlock_res == true ) {

					++$this->num_queries;
					unset($this->deadlock);

					return $deadlock_res;
				}
			}

			return false;
		}
	}


	function result($query_id = 0, $row = 0, $col = NULL) {
		return ($query_id) ? @mysql_result($query_id, $row, $col) : false;
	}


	function fetch_assoc($query_id = 0) {
		return ($query_id) ? @mysql_fetch_assoc($query_id) : false;
	}

	function fetch_array($query_id = 0) {
		return ($query_id) ? @mysql_fetch_array($query_id) : false;
	}

	function fetch_row($query_id = 0) {
		return ($query_id) ? @mysql_fetch_row($query_id) : false;
	}


	function num_rows($query_id = 0) {
		return ($query_id) ? @mysql_num_rows($query_id) : false;
	}


	function affected_rows() {
		return ($this->link_id) ? @mysql_affected_rows($this->link_id) : false;
	}


	function insert_id() {
		return ($this->link_id) ? @mysql_insert_id($this->link_id) : false;
	}


	function get_num_queries() {
		return $this->num_queries;
	}


	function get_saved_queries() {
		return $this->saved_queries;
	}


	function free_result($query_id = false) {
		return ($query_id) ? @mysql_free_result($query_id) : false;
	}


	function escape($str) {
		if (function_exists('mysql_real_escape_string'))
			return mysql_real_escape_string($str, $this->link_id);
		else
			return mysql_escape_string($str);
	}


	function error() {
		$result['error_sql'] = @current(@end($this->saved_queries));
		$result['error_no'] = @mysql_errno($this->link_id);
		$result['error_msg'] = @mysql_error($this->link_id);

		return $result;
	}


	function close() {
		if ($this->transaction === true)
			$this->end_transaction(1);

		if ($this->link_id) {
			if (is_resource($this->query_result))
				@mysql_free_result($this->query_result);

			return @mysql_close($this->link_id);
		}
		else
			return false;
	}


	function prepare_query($value_array,$table,$q_type,$drop_array=NULL,$add_array=NULL) {
		$keys = array_keys($value_array);
		$values = array_values($value_array);

		$result = $this->query("SHOW COLUMNS FROM `".$table."`");
		while ($row = $this->fetch_assoc($result))
			$field[] = $row['Field'];

		$drop_fields = array_diff($keys,$field);

		if (is_array($drop_fields)) {
			while (list($key) = each($drop_fields))
				unset($keys[$key],$values[$key]);

			$keys = array_values($keys);
			$values = array_values($values);
		}

		//Remove empty items
		if ($q_type == 'INSERT') {
			while (list($key,$val) = each($values)) {
				if (!$val)
					unset($keys[$key],$values[$key]);
			}
			$keys = @array_values($keys);
			$values = @array_values($values);
		}

		@array_walk($keys,'wrap_array',"`");
		@array_walk($values,'wrap_array',"'");

		if (strtoupper($q_type) == 'INSERT')
			return @array_combine($keys,$values);
		else {
			for ($i = 0; $i < count($keys); $i++)
				$sql[] = $keys[$i]." = ".$values[$i];

			return $sql;
		}
	}

	function server_info() {
		return mysql_get_server_info($this->link_id);
	}

	function resolve_deadlock($timeout_query=NULL) {
		$max_attempts = 50;
		$current = 0;

		while ($current++ < $max_attempts) {
			$res = $this->deadlock_query_loop($timeout_query);

			if (!$res && ($this->db_errno == 1213 || $this->db_errno == 1205))
				continue;
			else {
				if ($res)
					return $res;
				else
					return false;
			}
		}

		return false;
	}

	function deadlock_query_loop($timeout_query=NULL) {
		if ($timeout_query)
			$q = array($timeout_query);
		else
			$q =& $this->transaction_queries;

		for ($i = 0; $i < count($q); $i++) {
			$res = $this->query($q[$i]);

			return $res;
		}

		return true;
	}

    function set_charset($charset,$collation='') {

    	if ($charset)
        	$this->query("SET NAMES {$charset}");
        if ($collation)
            $this->query("SET CHARACTER SET {$collation}");

        $r = $this->query("SHOW VARIABLES LIKE 'character_set_%'");
        while ($row = $this->fetch_assoc($r)) {
            if (property_exists(get_class($this),$row['Variable_name']))
                $this->{$row['Variable_name']} = $row['Value'];
        }
        $r = $this->query("SHOW VARIABLES LIKE 'collation%'");
        while ($row = $this->fetch_assoc($r)) {
            if (property_exists(get_class($this),$row['Variable_name']))
                $this->{$row['Variable_name']} = $row['Value'];
        }
    }

    function set_timezone($zone) {
        $this->query("SET time_zone = '{$zone}'");
        return true;
    }

    function define($var,$val) {
        if ($this->query("SET @{$var} = '{$val}'"))
            return true;

        return false;
    }

    function seek($result, $row) {

    	if ( ! mysql_data_seek($result, $row) ) {

            $this->db_error = "MySQL data seek error to row $row " . mysql_error();
            $this->db_errno = mysql_errno();

    		return false;
    	}

    	return true;
    }
}

class UserStack {

	function start_process() {

		$r = $this->query ("SELECT COUNT(*) AS Total
							FROM user_stack
							WHERE `id_hash` = '".$this->current_user."'");
		if ($this->result($r) == 0) {

			$this->query("INSERT INTO `user_stack`
                          (`timestamp` , `id_hash` , `session_id` , `connection_id`)
    					  VALUES(".time()." , '".$this->current_user."' , '".session_id()."' , '".$this->connection_id."')");
			return $this->insert_id();
		}

		return false;
	}

	function end_process($connection_id) {

		$this->query("DELETE
             		  FROM user_stack
					  WHERE user_stack.connection_id = '{$this->connection_id}'");
		return;
	}
}
?>