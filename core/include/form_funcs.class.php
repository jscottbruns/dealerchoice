<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
//Form Functions
class form {

	public $buffer = '';
	private $vars = array();

	function output() {
		$output = $this->buffer;

		$this->buffer = '';
		$this->vars = array();

		return $output;
	}

	function explode_args($args,$default=0) {

		for ($i = $default; $i < count($args); $i++) {
			unset($tmp_val);
			if (is_array($args[$i]))
                $this->explode_args($args[$i]);

			$arg_ex = explode("=",$args[$i]);
			if (count($arg_ex) > 2) {
				for ($j = 1; $j < count($arg_ex); $j++)
					$tmp_val[] = $arg_ex[$j];
			} else
				$tmp_val = $arg_ex[1];

            $this->vars[$arg_ex[0]] = (is_array($tmp_val) ? implode("=",$tmp_val) : $tmp_val);
		}
	}

	function form_tag($name='f') {
		$args = func_get_args();

		for ($i = 0; $i < count($args); $i++) {
			list($name,$val) = explode("=",$args[$i]);
			$this->vars[$name] = $val;
		}

		$this->buffer = "<form action=\"".$_SERVER['SCRIPT_NAME']."\" method=\"POST\" enctype=\"multipart/form-data\" name=\"".$name."\" id=\"form_tag\" onSubmit=\"return false\">";
		$this->buffer .= $this->hidden(array('current_id_hash' => $_SESSION['id_hash']));
		//while (list($name,$val) = each($this->vars))
			//$this->buffer .= $name.($val ? "=\"$val\"" : NULL)." ";

		//$this->buffer .= ">";
		return $this->output();
	}

	function close_form() {
		$this->buffer = "</form>";
		return $this->output();
	}

	//standard input text box
	function text_box() {
		$args = func_get_args();

		$this->explode_args($args);
		$this->buffer = "<input type=\"text\" class=\"txtSearch\" ";

		while (list($name,$val) = each($this->vars)) {
			if ($name != 'id')
				$this->buffer .= $name.($val !== NULL ? "=\"$val\"" : NULL)." ";

			if ($name == "name" && !$id)
				$id = " id=\"$val\"";
			if ($name == "id")
				$id = " id=\"$val\"";
		}
		$this->buffer .= $id;
		$this->buffer .= " />";
		return $this->output();
	}

	function text_area () {
		$args = func_get_args();

		$this->explode_args($args);
		$this->buffer = "<textarea ";

		while (list($name,$val) = each($this->vars)) {
			if ($name != 'id')
				$this->buffer .= ($name != "value" ? $name.($val ? "=\"$val\"" : NULL)." " : NULL);
			if ($name == "name" && !$id)
				$id = " id=\"$val\"";
			if ($name == "id")
				$id = " id=\"$val\"";
		}
		$this->buffer .= $id;
		$this->buffer .= " >".$this->vars['value']."</textarea>";

		return $this->output();
	}

	function checkbox() {
		$args = func_get_args();

		$this->explode_args($args);
		$this->buffer = "<input type=\"checkbox\" ";

		while (list($name,$val) = each($this->vars))  {
			if ($name != 'id')
				$this->buffer .= $name.($val ? "=\"$val\"" : NULL)." ";
			if ($name == "name" && !$id)
				$id = " id=\"$val\"";
			if ($name == "id")
				$id = " id=\"$val\"";
		}
		$this->buffer .= $id;
		$this->buffer .= " />";
		return $this->output();
	}

	//hidden input box, input needs to be a single array
	function hidden() {
		$args = func_get_args();

		while (list($name,$val) = each($args[0]))
			$this->buffer .= "<input type=\"hidden\" name=\"$name\" value=\"$val\" id=\"$name\" />";

		return $this->output();
	}

	function select($name,$value_array,$selected,$insideValue_array) {
		$args = func_get_args();
		if (count($args) > 4)
			$this->explode_args($args,4);

		$this->buffer = "<select name=\"$name\" class=\"txtSearch\" ";

		while (list($name,$val) = each($this->vars)) {
			if ( $name != 'blank' )
    			$this->buffer .= $name.($val ? "=\"$val\"" : NULL).($name == "name" ? " id=\"$val\"" : NULL)." ";
		}
		$this->buffer .= ">";
		if (!$this->vars['blank'])
			$this->buffer .= "<option></option>";

		if ($selected && !is_array($selected))
            $selected = array($selected);

		for ($i = 0; $i < count($value_array); $i++) {
			$this->buffer .= "<option value=\"".$insideValue_array[$i]."\"";
			if (is_array($selected) && (in_array($insideValue_array[$i],$selected) || in_array($value_array[$i],$selected)))
				$this->buffer .= "selected";
			elseif (!is_array($selected) && ($selected == $insideValue_array[$i] || $selected == $value_array[$i]))
				$this->buffer .= "selected";

			$this->buffer .= ">".$value_array[$i]."</option>\n";
		}
		$this->buffer .= "</select>";

		return $this->output();
	}

	function button() {
		$args = func_get_args();

		$this->explode_args($args);
		$this->buffer = "<input type=\"button\" ";

		while (list($name,$val) = each($this->vars)) {
			if ( $name == 'id' ) {
                $id = true;
			} elseif ( $name == 'name' ) {
                $btn_name = $val;
			}
			$this->buffer .= $name.($val ? "=\"$val\"" : NULL)." ";
		}

		$this->buffer .= ( $btn_name && ! $id ? "id=\"{$btn_name}\"" : NULL) . " />";

		return $this->output();
	}

	function radio () {
		$args = func_get_args();

		$this->explode_args($args);
		$this->buffer = "<input type=\"radio\" ";

		while (list($name,$val) = each($this->vars)) {
            if ($name != 'id')
                $this->buffer .= $name.($val !== NULL ? "=\"$val\"" : NULL)." ";
            if ($name == "name" && !$id)
                $id = " id=\"$val\"";
            if ($name == "id")
                $id = " id=\"$val\"";
		}
        $this->buffer .= $id;
		$this->buffer .= " />";

		return $this->output();
	}

	//Password Box
	function password_box() {
		$args = func_get_args();

		for ($i = 0; $i < count($args); $i++) {
			list($name,$val) = explode("=",$args[$i]);
			$this->vars[$name] = $val;
		}

		$this->buffer = "<input type=\"password\" ";

		while (list($name,$val) = each($this->vars))
			$this->buffer .= $name.($val ? "=\"$val\"" : NULL).($name == "name" ? " id=\"$val\"" : NULL)." ";

		$this->buffer .= " />";
		return $this->output();
	}

	function submit() {
		$args = func_get_args();

		for ($i = 0; $i < count($args); $i++) {
			list($name,$val) = explode("=",$args[$i]);
			$this->vars[$name] = $val;
		}

		$this->buffer = "<input type=\"submit\" ";

		while (list($name,$val) = each($this->vars))
			$this->buffer .= $name.($val ? "=\"$val\"" : NULL).($name == "name" ? " id=\"$val\"" : NULL)." ";

		$this->buffer .= " />";
		return $this->output();
	}

    function select_disabled() {

    	$arg = func_get_arg(0);

        $this->buffer = "
        <select name=\"{$arg['name']}\" class=\"txtSearch\" id=\"" . ( $arg['id'] ? $arg['id'] : $arg['name'] ) . "\" disabled>";

        if ( $arg['message'] )
            $this->buffer .= "<option>{$arg['message']}</option>";

        $this->buffer .= "
        </select>";

        return $this->output();
    }

	/*


	//Function to print multiple select box
	function selectGeneric($size, $name, $matchArray, $valueArray, $selected=NULL)
	{
		$str = "";
		if (is_array($size)) {
			$width = $size[0];
			$height = $size[1];
		}
		if (!is_array($matchArray)) settype($matchArray, "array"); //we have to force this because on the first pass,this is set to string and will STB.

		$str .= "<select multiple name='".$name."[]' style=\"width:".(is_array($size) ? $width : $size).";height:".(is_array($size) ? $height : "100").";\">\n";
		for ($x = 0; $x < count($matchArray); $x++)
		{
			$str .= "\t<option value='".$matchArray[$x]."'";
			if (is_array($selected) && inList($matchArray[$x], $selected)) $str .= " SELECTED";
			$str .= ">".$valueArray[$x]."\n";
		}
		$str .= "</select>";
		return $str;
	}

	function inList($needle, $haystack)
	{
		while (list($k, $v) = each($haystack)) if ($needle == $v) return true;
		return false;
	}




	function hiddenOutput () {
		foreach ($nameValue as $key => $value) {
			$str .= "<input type=\"hidden\" name=\"$key\" value=\"$value\">\n";
		}
		return $str;
	}




	function radioArray ($name,$elementTitleArray,$valueArray,$selectedValue=NULL,$break) {
		if (!$name || !$valueArray || !$elementTitleArray) {
			exit('Required Fields -> $name, $valueArray');
		}
		if (!is_array($valueArray) || !is_array($elementTitleArray)) {
			exit('Non-Array Element -> $valueArray');
		}

		for ($i = 0; $i < count($valueArray); $i++) {
			$form .= "<small>".$elementTitleArray[$i]."</small><input type=\"radio\" name=\"$name\" value=\"".$valueArray[$i]."\" ";
			if ($valueArray[$i] == $selectedValue) {
				$form .= "checked";
			}
			$form .= ">&nbsp;&nbsp;";
			if ($break && $i == $break) {
				$form .= "<br>";
			}
		}

		return $form;
	}

	function genericTable($header,$link=NULL,$align=false) {
		if (!$link) $link = $_SERVER['PHP_SELF'];

		$tbl = "
			<table class=\"tborder\" width=\"100%\" cellpadding=\"2\" cellspacing=\"0\">
				<tr>
					<td class=\"tcat\" colspan=\"2\" style=\"padding:7;\"><a href=\"$link\">$header</a></td>
				</tr>
				<tr>
					<td class=\"panelsurround\">
						<div class=\"panel\" ".($align == true ? "align=\"center\"" : NULL).">
							";

		return $tbl;
	}

	function closeGenericTable() {
	$tbl = "
					</div>
				</td>
			</tr>
		</table>";

		return $tbl;
	}

	function help($id,$extra=NULL) {
		return "&nbsp;<a href=\"javascript:openWin('".(!ereg("/core",$_SERVER['SCRIPT_NAME']) ? "/core/" : "" )."help.php?id=$id','300','300');\" style=\"text-decoration:none;\"><img src=\"images/helpicon.gif\" border=\"0\">".($extra ? "&nbsp;$extra" : NULL)."</a>";
	}
	*/
}
$form = new form;
?>