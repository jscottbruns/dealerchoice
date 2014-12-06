<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class AJAX_library extends errorHandler {

	public $ajax_vars = array();
	public $content = array();
	public $popup_id;

	private $err = array();
	private $jscript = array();

	protected function load_class_navigation() {
		$result = $this->db->query("SELECT *
							  		FROM `section_index`
							  		WHERE `class_name` = '".get_class($this)."'");

		$this->content['html']['content_title'] = $this->db->result($result,0,'content_title');
		$this->content['html']['content_nav'] = $this->db->result($result,0,'content_nav');

		if (get_class($this) != 'accounting' && !$this->p->ck(get_class($this),'L')) {
			$this->content['html']['place_holder'] = "
			<h3 style=\"color:#0A58AA;margin:0;\">Restricted Permission</h3>
			<table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
				<tr>
					<td style=\"padding:15;\">
						 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
							<tr>
								<td style=\"vertical-align:top;height:75px;padding:25px 0 0 50px;\">
									Your permission settings have recently been changed and you are no longer able to view this page.
									<br /><br />
									Please contact your system administrator. To prevent this message from being displayed, please <a href=\"index.php\" class=\"link_standard\">refresh</a> your page.
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>";
			$this->content['jscript'][] = "initjsDOMenu();";
			return true;
		}

		return false;
	}

	function error_template($msg,$stream=false) {
		if (!$stream && !$this->popup_id)
			$this->popup_id = $this->content['popup_controls']['popup_id'] = 'errwin_'.rand(5000,500000);

        $tbl = "
		<div class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\">
			<div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
				<h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
					<p id=\"feedback_message".$this->popup_id."\"></p>
			</div>
			<table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
				<tr>
					<td style=\"background-color:#ffffff;padding:10px 25px;\">
						<img src=\"images/alert.gif\" />&nbsp;
						<strong>Error!</strong>
						<div style=\"margin-top:10px;\">{$msg}</div>
					</td>
				</tr>
			</table>
		</div>";

	    if ($stream)
	    	return $tbl;
	    else {
            $this->content['popup_controls']['popup_title'] = "Error!";
            $this->content['popup_controls']["cmdTable"] = $tbl;

            return;
        }
	}

	function fetch_active_search($search_hash) {
		$result = $this->db->query("SELECT `query` , `total` , `search_str`
									FROM `search`
									WHERE `search_hash` = '".$search_hash."'");
		$row = $this->db->fetch_assoc($result);

		$this->total = $row['total'];
		$sql = base64_decode($row['query']);
		unset($this->page_pref['custom']);
		$this->detail_search = $row['detail_search'];
		$this->search_vars = $this->p->load_search_vars($row['search_str']);
	}

	function fetch_searches($class) {
		$result = $this->db->query("SELECT `search_hash` , `search_name`
									FROM `search`
									WHERE `user_hash` = '".$this->current_hash."' AND `saved` = 1 AND `search_class` = '".$class."'
									ORDER BY `search_name` ASC");
		while ($row = $this->db->fetch_assoc($result)) {
			$name[] = $row['search_name'];
			$hash[] = $row['search_hash'];
		}

		if ($name)
			return array($name,$hash);
	}

	function unlock($id=NULL) {
		if (!$id)
			$id = $this->ajax_vars['row_id'];

		if ($id == '*' || $id == "_") {
			global $db;
			if ($id == '*')
				$db->query("DELETE FROM `table_lock`
							WHERE `user_hash` = '".$_SESSION['id_hash']."'");
			else
				$db->query("DELETE FROM `table_lock`
							WHERE `user_hash` = '".$_SESSION['id_hash']."' AND `popup_id` = ''");
		} else
			$this->p->unlock($id);

		if ($this->ajax_vars['onclose_func'])
			$this->content['jscript_action'] = $this->ajax_vars['onclose_func'];
	}

	function notify($id=NULL) {
		if (!$id)
			$id = $this->ajax_vars['row_id'];

		$true = $this->p->notify($id);

		if ($true)
			$this->content['jscript_action'] = "alert('User has been notified.');";
		else
			$this->content['jscript_action'] = "alert('The user has exited. Please refresh this record.');";

		$this->content['action'] = 'continue';
	}

	function exec_post($post) {
		if ($_SESSION['force_login'] && get_class($this) != 'login') {
			$sf_func = $_POST['aa_sfunc'];
			$cf_func = $_POST['aa_cfunc'];
			$orig_ajax_vars = base64_encode(serialize($this->ajax_vars));
			$invoked_class = get_class($this);

			$this->content['jscript'] = "agent.call('login','sf_loadcontent','show_popup_window','login_prompt','popup_id=main_popup','invoked_class=$invoked_class','invoked_method=$method','orig_ajax_vars=$orig_ajax_vars','sf_func=$sf_func','cf_func=$cf_func');";
		} else
			$this->doit();

        $xml = new dC_AJAX_XML_Builder('text/xml',DEFAULT_CHARSET);
        $xml->array2xml($this->content);
        $xml->print_xml();
	}

	function load_class_vars($default,$args=NULL) {
		if (is_array($args) && count($args) > $default) {
			for ($i = $default; $i < count($args); $i++) {
				if (strstr($args[$i],"=") != strrchr($args[$i],"="))
					$this->ajax_vars[substr($args[$i],0,strpos($args[$i],"="))] = substr(strstr($args[$i],"="),1);
				else {
					list($arg1,$arg2) = explode("=",$args[$i]);
					$this->ajax_vars[$arg1] = $arg2;
				}
			}
		}
	}

	function sf_loadcontent($method) {
		$args = func_get_args();
		$this->load_class_vars(1,$args);
		if ($_SESSION['force_login'] && get_class($this) != 'login') {
			$sf_func = $_POST['aa_sfunc'];
			$cf_func = $_POST['aa_cfunc'];
			$orig_ajax_vars = base64_encode(serialize($this->ajax_vars));
			$invoked_class = get_class($this);

			$this->content['jscript'] = "agent.call('login','sf_loadcontent','show_popup_window','login_prompt','popup_id=main_popup','invoked_class=$invoked_class','invoked_method=$method','orig_ajax_vars=$orig_ajax_vars','sf_func=$sf_func','cf_func=$cf_func');";
		} elseif ($method)
			$this->$method();

        $xml = new dC_AJAX_XML_Builder('text/xml',DEFAULT_CHARSET);
        $xml->array2xml($this->content);
        $xml->print_xml();
	}


	function __trigger_error($error_msg, $errno, $file, $line, $flush=false, $popup=false, $submit_btn=NULL, $reset_error=false) {

		if ( $reset_error )
			$this->reset_error();

		if ( $error_msg ) {

			$file = find_root($file); # Shorten the file path

			$err_msg = "<!--E_LEVEL-->[" . strftime('%c') . "] [" . ( $_SESSION['user_name'] ? $_SESSION['user_name'] : "anonymous" ) . "@" . ( $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : "unknown" ) . "] [{$file}:{$line}] " . ( is_array($error_msg) ? $error_msg[0] : $error_msg ) . " ";
    		trigger_error($err_msg, $errno);

    		if ( $errno == E_DATABASE_ERROR ) {

    			if ( preg_match('/^(.*) - (.*)$/', $error_msg, $matches) )
                    $error_no = $matches[1];

                $error_msg = ( $error_no ? "$error_no - " : NULL ) . "A database error occurred when trying to process your request. The error has been logged and a notification has been sent to the DealerChoice support team. Please try your request again.";

                if ( $error_no == 'DB100' )
                    $error_msg = "$error_no - {$matches[2]}";
    		}

    		if ( $popup == 2 )
        		$error_msg = preg_replace('/^(.*)<!--.*-->$/', '$1', $error_msg);

    		if ( ! is_array($this->err) )
                $this->err = array();

    		if ( is_array($error_msg) )
                $this->err = array_merge($this->err, $error_msg);
    		else
                array_push($this->err, $error_msg);
		}

		if ( $flush ) {

			if ( $this->db->transaction === true )
				$this->db->end_transaction(1);

			if ( $flush == 2 ) # Return error message string for integration purposes if flush is 2
				return $err_msg;
			else
				$this->flush_error($popup);
		}
		if ( $submit_btn )
			$this->content['submit_btn'] = $submit_btn;

		return;
	}

	private function flush_error($popup=false) {

		$msg = implode("<br />", $this->err);

		if ( $popup == 1 ) {

			$this->error_template( implode("<br />", $this->err) );
			$this->content['popup_controls']['popup_id'] = $this->popup_id;
		} elseif ( $popup == 2 ) {

			if ( ! $this->content['action'] )
				$this->content['action'] = 'continue';

			$this->content['jscript'] = "alert('Error! " . addslashes( implode("\r\n", $this->err) ) . "');";
		} else {

			$this->content['error'] = 1;
			$this->content['form_return']['feedback'] = implode("<br />", $this->err);
		}

		if ( $this->jscript ) {

			if ( ! is_array($this->content['jscript']) ) {

				if ( $this->content['jscript'] )
    				$this->content['jscript'] = array($this->content['jscript']);
    			else
        			$this->content['jscript'] = array();
			}

			for ( $i = 0; $i < count($this->jscript); $i++ )
				array_push($this->content['jscript'], $this->jscript[$i]);
		}

		if ( $this->popup_id && ! $this->content['popup_controls']['popup_id'] )
            $this->content['popup_controls']['popup_id'] = $this->popup_id;

		return;
	}

	private function reset_error() {

		$this->err = array();
		return;
	}

	private function reset_jscript() {

		$this->jscript = array();
		return;
	}

	function jscript_action($jscript) {

		if ( ! is_array($this->jscript) )
    		$this->jscript = array();

		if ( $jscript )
			array_push($this->jscript, $jscript);
	}

	function purge_search() {
		$hash = $this->ajax_vars['s'];

		$r = $this->db->query("DELETE FROM `search`
							   WHERE search.search_hash = '$hash'");

		return;
	}

	function set_error($node) {
		if (!trim($node))
            return $node;

        if (!is_array($node))
            $node = array($node);

        for ($i = 0; $i < count($node); $i++){
            $script = "var d = document.getElementById('".$node[$i]."');
                                        d.className = d.className + ' error_class';
                                        ";
        }

        $this->content['jscript'][] = $script;


        return;
	}

	function image_window() {
        $image = $this->ajax_vars['image'];
        $descr = $this->ajax_vars['descr'];
        $title = $this->ajax_vars['title'];

        if (file_exists(SITE_ROOT.'core/'.$image)) {
            $image_attr = getimagesize(SITE_ROOT.'core/'.$image);
            $this->content['popup_controls']['popup_width'] = ($image_attr[0] + 50)."px";
            $this->content['popup_controls']['popup_height'] = ($image_attr[1] + 50)."px";
        } else
            $err = 1;

        $this->popup_id = $this->content['popup_controls']['poup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = ($title ? $title : "Image Window");

        $this->content['popup_controls']["cmdTable"] = "
        <div ".($err ? "class=\"panel\" id=\"main_table".$this->popup_id."\" style=\"margin-top:0\"" : "style=\"background-color:#ffffff;\"").">".($err ? "
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;margin-top:0;\">
                <tr>
                    <td style=\"background-color:#ffffff;padding:10px 25px;\">
                        <img src=\"images/alert.gif\" />&nbsp;
                        <strong>Error!</strong>
                        <div style=\"margin-top:10px;\">
                            The specified image does not exist.
                        </div>
                    </td>
                </tr>
            </table>" : "
            <div style=\"margin-top:10px;text-align:center;\">
                <table>
                    <tr>
                        <td><img src=\"".$image."\" /></td>
                    </tr>".($title ? "
                    <tr>
                        <td style=\"text-align:center;\">$title</td>
                    </tr>" : NULL)."
                </table>
            </div>")."
        </div>";

        return;

	}

	function set_error_msg($msg) {
        if ($msg)
            $this->err[] = $msg;
	}
}



?>