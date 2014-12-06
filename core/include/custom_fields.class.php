<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class custom_fields extends AJAX_library {

	public $class;
	public $class_fields = array();
	public $input_types = array('text_box'         =>  "Text Input Box",
	                            'select'           =>  "Drop Down Select Box",
	                            'select_multiple'  =>  "Multiple Select Box",
	                            'checkbox'         =>  "Checkbox",
	                            'textarea'         =>  "Scrolling Comment Box");

	function custom_fields($class) {
        global $db;

		$this->db =& $db;
		$this->form = new form;

		$this->current_hash = $_SESSION['id_hash'];
		$this->class = $class;
	}

	function fetch_class_fields() {

		$location = func_get_arg(0);
		$sector = func_get_arg(1);
		$group_by_sector = func_get_arg(2);

		$exists = 0;

		$r = $this->db->query("SELECT t1.*
		                       FROM custom_fields t1
		                       WHERE t1.class = '{$this->class}' " .
		                       ( ! $this->edit_class ?
    		                       " AND t1.active = 1" : NULL
		                       ) . "
		                       ORDER BY t1.sector, t1.position ASC");
        while ( $row = $this->db->fetch_assoc($r) ) {

        	$exists++;
        	if ( $group_by_sector )
                $this->class_fields[ $row['location'] ][ $row['sector'] ][] = $row;
        	else
                $this->class_fields[ $row['location'] ][] = $row;
        }

        return $exists;
	}

	function build_input($field,$selected_value=NULL,$extra=NULL) {

		switch ($field['field_type']) {
			case 'text_box':
			$html = $this->form->text_box("name=custom[".$field['obj_id']."]",
                              			  "value=".($selected_value ?
                                              $selected_value : stripslashes($field['default_value'])),
                                          ($extra ? $extra : NULL),
                                          ($field['max_length'] ? "maxlength=".$field['max_length'] : NULL),
                                          ($field['field_width'] || $field['field_height'] ? "style=".($field['field_width'] ? "width:".$field['field_width']."px;" : NULL).($field['field_height'] ? "height:".$field['field_height']."px;" : NULL) : NULL));
			break;

			case 'select':
			$prepopulated_array = @explode("|",$field['prepopulated']);

			$html = $this->form->select("custom[".$field['obj_id']."]",
                              			 $prepopulated_array,
                              			 ($selected_value && in_array($selected_value,$prepopulated_array) ?
                                  			 $selected_value : $field['default_value']),
                                         $prepopulated_array,
                                         ($field['required'] ? "blank=1" : NULL),
                                         ($extra ? $extra : NULL),
                                         ($field['field_width'] || $field['field_height'] ? "style=".($field['field_width'] ? "width:".$field['field_width']."px;" : NULL).($field['field_height'] ? "height:".$field['field_height']."px;" : NULL) : NULL));
			break;

            case 'select_multiple':
            $prepopulated_array = @explode("|",$field['prepopulated']);
            $default_value = @explode("|",$field['default_value']);
            if ($selected_value)
                $selected_value = @explode("|",$selected_value);

            $html = $this->form->select("custom[".$field['obj_id']."][]",
                                         $prepopulated_array,
                                         ($selected_value ?
                                             $selected_value : $default_value),
                                         $prepopulated_array,
                                         "blank=1",
                                         "multiple",
                                         ($extra ? $extra : NULL),
                                         ($field['field_width'] || $field['field_height'] ? "style=".($field['field_width'] ? "width:".$field['field_width']."px;" : NULL).($field['field_height'] ? "height:".$field['field_height']."px;" : NULL) : NULL));
            break;

			case 'textarea':
            $html = $this->form->text_area("name=custom[".$field['obj_id']."]",
                                           "value=".($selected_value ?
                                              $selected_value : stripslashes($field['default_value'])),
                                           ($extra ? $extra : NULL),
                                           ($field['field_width'] || $field['field_height'] ? "style=".($field['field_width'] ? "width:".$field['field_width']."px;" : NULL).($field['field_height'] ? "height:".$field['field_height']."px;" : NULL) : NULL));
			break;

			case 'radio':


			break;

			case 'checkbox':
            $html = $this->form->checkbox("name=custom[".$field['obj_id']."]",
                                           "value=".stripslashes($field['default_value']),
                                           ($selected_value && $selected_value == $field['default_value'] ? "checked" : NULL),
                                           ($extra ? $extra : NULL));
			break;

		}

		return $html;
	}

	function fetch_custom_field($obj_id) {
        $r = $this->db->query("SELECT *
                               FROM `custom_fields`
                               WHERE `obj_id` = $obj_id");
        if ($row = $this->db->fetch_assoc($r)) {
        	$this->field_id = $obj_id;
            $this->current_field = $row;
            return true;
    	}
	}

	function field_editor() {
        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Custom Field Editor";
        $this->content['popup_controls']['popup_width'] = "750px;";
        $this->content['popup_controls']['popup_height'] = "550px;";
        $this->edit_class = 1;

        if ($action = $this->ajax_vars['action'])
            $this->content['jscript'][] = "toggle_display('feedback_holder".$this->popup_id."','none');";


        $sub_section['proposals'] = array('project'    =>  'Project Info',
                                          'design'     =>  'Design',
                                          'install'    =>  'Install Info');
        $sub_section['customers'] = array('general'    =>  'General Info',
                                          'payment'    =>  'Payment Info',
                                          'contact'    =>  'Contacts',
                                          'location'   =>  'Locations');
        $sub_section['vendors'] = array('general'      =>  'General Info',
                                        'payment'      =>  'Payment Info',
                                        'contact'      =>  'Contacts',
                                        'location'     =>  'Locations',
                                        'product'      =>  'Products');
        if ($content = $this->ajax_vars['content'])
            $this->set_class($content);

        if ($action == 'load_nav') {
            $nav = "
            <div style=\"width:120px;margin-top:15px;\">";
            foreach ($sub_section[$content] as $key => $var)
                $nav .= "
                <div style=\"margin-bottom:5px;border-top:1px solid #8f8f8f;width:120px;border-bottom:1px solid #8f8f8f;background-color:#efefef;padding:3px;text-align:left;\">
                    <div style=\"float:right;padding-right:4px;margin-top:2px;\"><img src=\"images/arrow_right_12x11_tile_box.gif\" /></div>
                    <a id=\"nav_item_".$key."\" href=\"javascript:void(0);\" class=\"link_standard\" style=\"text-decoration:none;\" onClick=\"agent.call('custom_fields','sf_loadcontent','cf_loadcontent','field_editor','popup_id=".$this->popup_id."','content=$content','action=load_content','sub=$key');\">".$var."</a>
                </div>";

            $nav .= "
            </div>";

            $title = "
            <div >
                <div style=\"font-weight:bold;\">
                    Custom Field Editor : ".strtoupper(substr($content,0,1)).substr($content,1)."
                    <div style=\"margin-top:15px;font-weight:normal;\">
                        <div style=\"margin-top:55px;\"><-- Select subsection to begin</div>
                    </div>
                </div>
            </div>";

            $this->content['html']['editor_content_holder'.$this->popup_id] = '';
            $this->content['html']['editor_navigation_holder'.$this->popup_id] = $nav;
            $this->content['html']['editor_title_holder'.$this->popup_id] = $title;
            return;
        }

        if ($sub = $this->ajax_vars['sub'])
            $this->fetch_class_fields($sub);

        if ($action == 'load_content') {
            $title = "
            <div >
                <div style=\"font-weight:bold;\">
                    Custom Field Editor : ".strtoupper(substr($content,0,1)).substr($content,1)."
                </div>
            </div>";

            $class_fields =& $this->class_fields[$sub];
            if (count($class_fields) > 0) {
            	$field_list = "
            	<table cellpadding=\"5\" cellspacing=\"0\" style=\"width:550px;\">
                    <tr>
                        <td style=\"width:250px;font-weight:bold;border-bottom:1px solid #8f8f8f;border-left:1px solid #8f8f8f;background-color:#efefef;border-top:1px solid #8f8f8f;\">Field Name</td>
                        <td style=\"width:175px;font-weight:bold;border-bottom:1px solid #8f8f8f;background-color:#efefef;border-top:1px solid #8f8f8f;\">Type</td>".($content == 'proposals' ? "
                        <td style=\"font-weight:bold;text-align:right;border-bottom:1px solid #8f8f8f;background-color:#efefef;border-top:1px solid #8f8f8f;\">Sector</td>" : NULL)."
                        <td style=\"font-weight:bold;text-align:right;border-bottom:1px solid #8f8f8f;background-color:#efefef;border-top:1px solid #8f8f8f;\">Position</td>
                        <td style=\"font-weight:bold;text-align:right;border-bottom:1px solid #8f8f8f;background-color:#efefef;border-top:1px solid #8f8f8f;\">Required</td>
                        <td style=\"font-weight:bold;text-align:right;border-bottom:1px solid #8f8f8f;background-color:#efefef;border-top:1px solid #8f8f8f;border-right:1px solid #8f8f8f;\">Active</td>
                    </tr>";

            	for ($i = 0; $i < count($class_fields); $i++) {
            		$field_list .= "
                    <tr onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className='white_bg'\" onClick=\"agent.call('custom_fields','sf_loadcontent','cf_loadcontent','field_editor','popup_id=".$this->popup_id."','content=$content','sub=$sub','action=edit_field','field_id=".$class_fields[$i]['obj_id']."');\">
                        <td style=\"border-bottom:1px solid #8f8f8f;border-left:1px solid #8f8f8f;\">".$class_fields[$i]['field_name']."</td>
                        <td style=\"border-bottom:1px solid #8f8f8f;\" nowrap>".$this->input_types[$class_fields[$i]['field_type']]."</td>".($content == 'proposals' ? "
                        <td style=\"text-align:right;border-bottom:1px solid #8f8f8f;padding-right:10px;\">".$class_fields[$i]['sector']."</td>" : NULL)."
                        <td style=\"text-align:right;border-bottom:1px solid #8f8f8f;padding-right:10px;\">".$class_fields[$i]['position']."</td>
                        <td style=\"text-align:right;border-bottom:1px solid #8f8f8f;padding-left:10px;padding-right:10px;\">".($class_fields[$i]['required'] ?
                            "Y" : "N")."
                    	</td>
                        <td style=\"text-align:right;border-bottom:1px solid #8f8f8f;border-right:1px solid #8f8f8f;padding-left:10px;padding-right:10px;\">".($class_fields[$i]['active'] ?
                            "Y" : "N")."
                        </td>
                    </tr>";
            	}
            	$field_list .= "
            	</table>";
            } else
                $field_list = "There have been no custom fields created under this section.";

            $content_tbl = "
            <div style=\"margin-top:20px;\" id=\"content_controls".$this->popup_id."\">
                <div style=\"margin-left:15px;margin-top:-10px;\">[<small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('custom_fields','sf_loadcontent','cf_loadcontent','field_editor','popup_id=".$this->popup_id."','content=$content','sub=$sub','action=edit_field');\">add new field</a></small>]</div>
                <div style=\"margin-top:15px;\">
                    $field_list
                </div>
            </div>";

            $this->content['html']['editor_title_holder'.$this->popup_id] = $title;
            $this->content['html']['editor_content_holder'.$this->popup_id] = $content_tbl;
            foreach ($sub_section[$content] as $key => $var)
                $this->content['jscript'][] = "\$('nav_item_".$key."').setStyle({fontWeight : '".($key == $sub ? "bold" : "normal")."'});";

            return;
        }

        if ($action == 'edit_field') {
            $this->content['focus'] = 'field_name';

            $title = "
            <div >
                <div style=\"font-weight:bold;\">
                    Custom Field Editor : ".strtoupper(substr($content,0,1)).substr($content,1)."
                </div>
            </div>";

            if ($field_id = $this->ajax_vars['field_id'])
                $this->fetch_custom_field($field_id);

	        $r = $this->db->query("SELECT `position`
	                               FROM `custom_fields`
	                               WHERE `class` = '$content' AND `location` = '$sub' AND `sector` = '".($this->field_id && $this->current_field['sector'] ? $this->current_field['sector'] : 1)."'
	                               ORDER BY `position` DESC
	                               LIMIT 1");
	        if ($last_pos = $this->db->result($r,0,'position')) {
                if (!$this->field_id)
    	        	$last_pos++;
	        } else
                $last_pos = 1;


            if (ereg("\|",$this->current_field['prepopulated']))
                $prepop = explode("|",$this->current_field['prepopulated']);

            $r = 'form_'.rand(500,5000);

	        for ($i = 0; $i <= 25; $i++)
                $gen_array[] = $i;

            if (!$last_pos)
                $last_pos = 1;

            if ($content == 'proposals') {
            	if ($this->current_field['position'] && $this->current_field['position'] > $last_pos)
                    $last_pos = $this->current_field['position'];

	            switch ($sub) {
	            	case 'project':
	                $sector_ops = array_slice($gen_array,1,7);
	                $position_ops = array_slice($gen_array,1,$last_pos);
	            	break;

	            	case 'design':
	            	$sector_ops = array_slice($gen_array,1,3);
	                $position_ops = array_slice($gen_array,1,$last_pos);
	            	break;

	            	case 'install':
	            	$sector_array = $gen_array;
	            	unset($sector_array[4]);
	            	$sector_ops = array_slice($sector_array,1,10);
	                $position_ops = array_slice($gen_array,1,$last_pos);
	            	break;
	            }
            } elseif ($content == 'customers' || $content == 'vendors') {
                switch ($sub) {
                    case 'general':
                    $position_ops = array_slice($gen_array,1,$last_pos);
                    break;

                    case 'payment':
                    $position_ops = array_slice($gen_array,1,$last_pos);
                    break;

                    case 'contact':
                    $position_ops = array_slice($gen_array,1,$last_pos);
                    break;

                    case 'location':
                    $position_ops = array_slice($gen_array,1,$last_pos);
                    break;

                    case 'product':
                    $position_ops = array_slice($gen_array,1,$last_pos);
                    break;
                }
	        }

            $content_tbl = "
            <div style=\"margin-top:20px;\" id=\"content_controls".$this->popup_id."\">
                <div style=\"margin-left:15px;margin-top:-10px;\"><small><a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"agent.call('custom_fields','sf_loadcontent','cf_loadcontent','field_editor','popup_id=".$this->popup_id."','content=$content','sub=$sub','action=load_content');\"><--back</a></small></div>
                <div style=\"margin-top:15px;\">
		            <table cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:550px;margin-top:0;\" class=\"smallfont\">
	                    <tr>
	                        <td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_1".$this->popup_id."\">
	                            Field Name:
	                        </td>
	                        <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
	                            ".$this->form->text_box("name=field_name",
                        	                            "value=".$this->current_field['field_name'],
                        	                            "size=35",
                        	                            "maxlength=64")."
	                            <div style=\"margin-top:5px;\">
	                                ".$this->form->checkbox("name=active",
	                                                        "value=1",
	                                                        ($this->current_field['active'] || !$this->current_field ? "checked" : NULL))."&nbsp;
	                                Active?
	                            </div>
	                        </td>
	                    </tr>
	                    <tr>
	                        <td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_2".$this->popup_id."\">
	                            Field Type:
	                        </td>
	                        <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;vertical-align:top;\">
	                            <div style=\"float:right;\">
	                                <div style=\"position:absolute;top:152px;left:500px;border:1px solid #8f8f8f;padding:5px;background-color:#efefef;display:".(!$this->current_field || $this->current_field['field_type'] == 'text_box' ? "block" : "none").";\" id=\"sample_text_box\">
    	                                <i>Sample</i>:&nbsp;&nbsp;
    	                                ".$this->form->text_box("value=Example",
                                                                ($this->current_field['field_width'] || $this->current_field['field_height'] ? "style=".($this->current_field['field_width'] ? "width:".$this->current_field['field_width']."px;" : NULL).($this->current_field['field_height'] ? "height:".$this->current_field['field_height']."px;" : NULL) : NULL),
                            	                                "id=input_text_box")."
    	                            </div>
	                                <div style=\"position:absolute;top:152px;left:500px;border:1px solid #8f8f8f;padding:5px;background-color:#efefef;display:".($this->current_field['field_type'] == 'select' ? "block" : "none").";\" id=\"sample_select\">
    	                                <i>Sample</i>:&nbsp;&nbsp;
    	                                ".$this->form->select("Sample",
	                                                          array("Example 1","Example 2","Example 3","Example 4","Example 5","Example 6","Example 7"),
	                                                          '',
	                                                          array("Example 1","Example 2","Example 3","Example 4","Example 5","Example 6","Example 7"),
	                                                          "blank=1",
	                                                          ($this->current_field['field_width'] || $this->current_field['field_height'] ? "style=".($this->current_field['field_width'] ? "width:".$this->current_field['field_width']."px;" : NULL).($this->current_field['field_height'] ? "height:".$this->current_field['field_height']."px;" : NULL) : NULL),
	                                                          "id=input_select")."
                                    </div>
	                                <div style=\"position:absolute;top:152px;left:500px;border:1px solid #8f8f8f;padding:5px;background-color:#efefef;display:".($this->current_field['field_type'] == 'select_multiple' ? "block" : "none").";\" id=\"sample_select_multiple\">
    	                                <i>Sample</i>:&nbsp;&nbsp;
    	                                ".$this->form->select("Sample",
	                                                          array("Example 1","Example 2","Example 3","Example 4","Example 5","Example 6","Example 7"),
	                                                          '',
	                                                          array("Example 1","Example 2","Example 3","Example 4","Example 5","Example 6","Example 7"),
	                                                          "blank=1",
	                                                          "multiple",
	                                                          ($this->current_field['field_width'] || $this->current_field['field_height'] ? "style=".($this->current_field['field_width'] ? "width:".$this->current_field['field_width']."px;" : NULL).($this->current_field['field_height'] ? "height:".$this->current_field['field_height']."px;" : NULL) : NULL),
	                                                          "id=input_select_multiple")."
                                    </div>
	                                <div style=\"position:absolute;top:152px;left:500px;border:1px solid #8f8f8f;padding:5px;background-color:#efefef;display:".($this->current_field['field_type'] == 'checkbox' ? "block" : "none").";\" id=\"sample_checkbox\">
    	                                <i>Sample</i>:&nbsp;&nbsp;
    	                                ".$this->form->checkbox("value=1",
                            	                                "id=input_checkbox")."
                                    </div>
	                                <div style=\"position:absolute;top:152px;left:500px;border:1px solid #8f8f8f;padding:5px;background-color:#efefef;display:".($this->current_field['field_type'] == 'textarea' ? "block" : "none").";\" id=\"sample_textarea\">
    	                                <i>Sample</i>:&nbsp;&nbsp;
    	                                ".$this->form->text_area("value=Example",
                            	                                 (!$this->current_field['field_width'] ? "cols=20" : NULL),
                            	                                 (!$this->current_field['field_height'] ? "row=3" : NULL),
	                                                             ($this->current_field['field_width'] || $this->current_field['field_height'] ? "style=".($this->current_field['field_width'] ? "width:".$this->current_field['field_width']."px;" : NULL).($this->current_field['field_height'] ? "height:".$this->current_field['field_height']."px;" : NULL) : NULL),
                            	                                 "id=input_textarea")."
                                    </div>
	                            </div>
	                            ".$this->form->select("type",
	                                                  array_values($this->input_types),
	                                                  $this->current_field['field_type'],
	                                                  array_keys($this->input_types),
	                                                  "blank=1",
	                                                  "onChange=toggle_samples(this.options[this.selectedIndex].value);activate_fields(this.options[this.selectedIndex].value);format_fields();")."
	                        </td>
	                    </tr>
	                    <tr id=\"tr_field_length".$this->popup_id."\" style=\"display:".($this->current_field && $this->current_field['field_type'] != 'text_box' ? 'none' : 'block')."\">
	                        <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_3".$this->popup_id."\">
	                            Max Length:
	                        </td>
	                        <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
	                            ".$this->form->text_box("name=field_length",
                        	                            "value=".($this->current_field['max_length'] ? $this->current_field['max_length'] : NULL),
                        	                            "maxlength=3",
                        	                            "size=3")."
                                <span style=\"margin-left:10px;font-style:italic;\">leave blank for default</span>
	                        </td>
	                    </tr>
	                    <tr id=\"tr_field_required".$this->popup_id."\" style=\"display:".($this->current_field['field_type'] == 'checkbox' ? 'none' : 'block')."\">
	                        <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_4".$this->popup_id."\">
	                            Required:
	                        </td>
	                        <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
	                            ".$this->form->checkbox("name=field_required","value=1",($this->current_field['required'] ? "checked" : NULL))."
	                        </td>
	                    </tr>
                        <tr id=\"tr_field_width".$this->popup_id."\" style=\"display:".($this->current_field['field_type'] == 'checkbox' ? 'none' : 'block')."\">
                            <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_4a".$this->popup_id."\">
                                Field Width:
                            </td>
                            <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
                                ".$this->form->text_box("name=field_width",
                                                        "value=".$this->current_field['field_width'],
	                                                    "style=width:25px;",
	                                                    "maxlength=3",
	                                                    "onKeyUp=window.setTimeout('format_fields()',800);")."
                                <span style=\"margin-left:10px;font-style:italic;\">leave blank for default</span>
                            </td>
                        </tr>
                        <tr id=\"tr_field_height".$this->popup_id."\" style=\"display:".($this->current_field['field_type'] == 'checkbox' ? 'none' : 'block')."\">
                            <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_4a".$this->popup_id."\">
                                Field Height:
                            </td>
                            <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
                                ".$this->form->text_box("name=field_height",
                                                        "value=".$this->current_field['field_height'],
	                                                    "style=width:25px;",
	                                                    "maxlength=3",
	                                                    "onKeyUp=window.setTimeout('format_fields()',800);")."
                                <span style=\"margin-left:10px;font-style:italic;\">leave blank for default</span>
                            </td>
                        </tr>
                        <tr id=\"tr_default_value".$this->popup_id."\" style=\"display:block;\">
                            <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_6".$this->popup_id."\">
                                Default Value:
                            </td>
                            <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
                                <div id=\"default_std".$this->popup_id."\" style=\"display:".(!$this->field_id || $this->current_field['field_type'] != 'checkbox' ? "block" : "none")."\">
                                    ".$this->form->text_box("name=default_value",
                                                            "value=".($this->current_field['field_type'] != 'checkbox' && $this->current_field['default_value'] ? $this->current_field['default_value'] : NULL),
                                                            "size=35")."
                                </div>
                                <div id=\"default_ck".$this->popup_id."\" style=\"display:".($this->current_field['field_type'] == 'checkbox' ? "block" : "none")."\">
                                    ".$this->form->checkbox("name=default_value_ck",
	                                                        "value=1",
                                                            ($this->current_field['default_value'] ? "checked" : NULL))."
                                </div>
                            </td>
                        </tr>
	                    <tr id=\"tr_prepop".$this->popup_id."\" style=\"display:".($this->current_field['field_type'] == 'select' || $this->current_field['field_type'] == 'select_multiple' ? "block" : "none")."\">
	                        <td class=\"smallfont\" style=\"vertical-align:top;text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_5".$this->popup_id."\">
	                            Pre Populated:
	                        </td>
	                        <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">
    	                        ".$this->form->text_area("name=prepopulated",
                        	                            "value=".(is_array($prepop) ? implode("\n",$prepop) : $this->current_field['prepopulated']),
	                                                    "rows=3",
	                                                    "cols=35")."
                                <div style=\"margin-top:5px;font-style:italic;\">
                                   For select and select-multiple, separate values on new lines.
                                </div>
	                        </td>
	                    </tr>".($content == 'proposals' ? "
	                    <tr>
	                        <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_5".$this->popup_id."\">
	                            Sector:
	                        </td>
	                        <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\">".
                                $this->form->select("sector",
                                                     $sector_ops,
                                                     $this->current_field['sector'],
                                                     $sector_ops,
                                                     "blank=1",
                                                     "onChange=agent.call('custom_fields','sf_loadcontent','cf_loadcontent','reload_position','content=$content','sub=$sub','sector='+this.options[this.selectedIndex].value,'popup_id=".$this->popup_id."','field_id=".$field_id."');")."

                                <span style=\"margin-left:10px;\">[<small><a href=\"javascript:void(0);\" onClick=\"agent.call('custom_fields','sf_loadcontent','show_popup_window','image_window','image=".urlencode('images/sectormap_'.$content.'_'.$sub.'.gif')."','descr=','title=Custom Field Sector Layout Map','popup_id=sector_map_".$sub."');\" class=\"link_standard\">sector layout map</a></small>]</span>
	                        </td>
	                    </tr>" : NULL)."
	                    <tr>
	                        <td class=\"smallfont\" style=\"text-align:right;background-color:#efefef;width:25%;border-top:1px solid #AAC8C8;border-bottom:1px solid #AAC8C8;border-left:1px solid #AAC8C8;border-right:1px solid #AAC8C8;\" id=\"err_5".$this->popup_id."\">
	                            Position:
	                        </td>
	                        <td style=\"background-color:#ffffff;border-top:1px solid #AAC8C8;border-right:1px solid #AAC8C8;border-bottom:1px solid #AAC8C8;\" id=\"position_holder".$this->popup_id."\">".
                                $this->form->select("position",
                                                     $position_ops,
                                                     ($this->current_field['position'] ? $this->current_field['position'] : $last_pos),
                                                     $position_ops,
                                                     "blank=1")."
                                <span style=\"margin-left:10px;font-style:italic;\">Custom fields are added below existing fields.</span>
	                        </td>
	                    </tr>
	                </table>
                </div>
                <div style=\"margin-top:10px;\">
                    ".$this->form->button("value=Save","onClick=submit_form(this.form,'custom_fields','exec_post','refresh_form','button=save','content=$content','subsection=$sub','field_id=".$this->field_id."');").($this->field_id ?
                        "&nbsp;&nbsp;".
                        $this->form->button("value=Remove",
                                            "onClick=if(confirm('Are you sure you want to remove this custom field? This action will cause any information that was saved within this custom field to be lost. This action can not be undone!')){submit_form(this.form,'custom_fields','exec_post','refresh_form','button=rm','content=$content','subsection=$sub','field_id=".$this->field_id."')}") : NULL)."
                    <!--
                    &nbsp;&nbsp;".$this->form->button("value=Preview","onClick=submit_form(this.form,'custom_fields','exec_post','refresh_form','button=save','content=$content','subsection=$sub','field_id=".$this->field_id."','preview=1');")."
                    -->
                </div>
            </div>";

            $this->content['jscript'][] = "
            toggle_samples = function() {
                var sel = 'sample_'+arguments[0];
                var s = new Array('sample_text_box','sample_select','sample_select_multiple','sample_checkbox','sample_textarea');

                for (var i = 0; i < s.length; i++)
                    toggle_display(s[i],sel==s[i]?'block':'none');

            }
            activate_fields = function() {
                switch (arguments[0]) {
                    case 'text_box':
                    toggle_display('tr_prepop".$this->popup_id."','block');
                    toggle_display('tr_field_length".$this->popup_id."','block');
                    toggle_display('tr_prepop".$this->popup_id."','none');
                    toggle_display('tr_field_required".$this->popup_id."','block');
                    toggle_display('tr_field_width".$this->popup_id."','block');
                    toggle_display('tr_field_height".$this->popup_id."','block');
                    toggle_display('default_std".$this->popup_id."','block');
                    toggle_display('default_ck".$this->popup_id."','none');
                    break;

                    case 'textarea':
                    toggle_display('tr_prepop".$this->popup_id."','block');
                    toggle_display('tr_field_length".$this->popup_id."','none');
                    toggle_display('tr_prepop".$this->popup_id."','none');
                    toggle_display('tr_field_required".$this->popup_id."','block');
                    toggle_display('tr_field_width".$this->popup_id."','block');
                    toggle_display('tr_field_height".$this->popup_id."','block');
                    toggle_display('default_std".$this->popup_id."','block');
                    toggle_display('default_ck".$this->popup_id."','none');
                    break;

                    case 'select':
                    toggle_display('tr_prepop".$this->popup_id."','block');
                    toggle_display('tr_field_length".$this->popup_id."','none');
                    toggle_display('tr_prepop".$this->popup_id."','block');
                    toggle_display('tr_field_required".$this->popup_id."','block');
                    toggle_display('tr_field_width".$this->popup_id."','block');
                    toggle_display('tr_field_height".$this->popup_id."','block');
                    toggle_display('default_std".$this->popup_id."','block');
                    toggle_display('default_ck".$this->popup_id."','none');
                    break;

                    case 'select_multiple':
                    toggle_display('tr_prepop".$this->popup_id."','block');
                    toggle_display('tr_field_length".$this->popup_id."','none');
                    toggle_display('tr_prepop".$this->popup_id."','block');
                    toggle_display('tr_field_required".$this->popup_id."','block');
                    toggle_display('tr_field_width".$this->popup_id."','block');
                    toggle_display('tr_field_height".$this->popup_id."','block');
                    toggle_display('default_std".$this->popup_id."','block');
                    toggle_display('default_ck".$this->popup_id."','none');
                    break;

                    case 'checkbox':
                    toggle_display('tr_prepop".$this->popup_id."','none');
                    toggle_display('tr_field_length".$this->popup_id."','none');
                    toggle_display('tr_prepop".$this->popup_id."','none');
                    toggle_display('tr_field_required".$this->popup_id."','none');
                    toggle_display('tr_field_width".$this->popup_id."','none');
                    toggle_display('tr_field_height".$this->popup_id."','none');
                    toggle_display('default_std".$this->popup_id."','none');
                    toggle_display('default_ck".$this->popup_id."','block');
                    break;
                }
            }
            format_fields = function() {
                var h = \$F('field_height');
                var w = \$F('field_width');
                var t = \$F('type');
                if (t == 'checkbox')
                    h = w = '';

                \$('input_' + t).setStyle({
                    'width' : w ? w+'px' : 'auto',
                    'height' : h ? h+'px' : 'auto'});

            }";

            $this->content['html']['editor_title_holder'.$this->popup_id] = $title;
            $this->content['html']['editor_content_holder'.$this->popup_id] = $content_tbl;
            return;
        }


        $tbl = $this->form->form_tag().
        $this->form->hidden(array('popup_id' => $this->popup_id))."
        <div class=\"panel\" id=\"main_table".$this->popup_id."\">
            <div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message".$this->popup_id."\"></p>
            </div>
            <table style=\"height:100%;width:700px;\" cellpadding=\"0\" cellspacing=\"5\">
                <tr>
                    <td style=\"width:120px;border:1px solid #8f8f8f;background-color:#ffffff;vertical-align:top;\">
                        <div style=\"font-weight:bold;padding:5px;\">
                            Section:
                            <div style=\"margin-top:5px;\">
                                ".$this->form->select("section",
                                                       array("Proposals","Customers","Vendors"),
                                                       '',
                                                       array("proposals","customers","vendors"),
                                                       "onChange=agent.call('custom_fields','sf_loadcontent','cf_loadcontent','field_editor','popup_id=".$this->popup_id."','action=load_nav','content='+this.options[this.selectedIndex].value);")."
                            </div>
                        </div>
                        <div id=\"editor_navigation_holder".$this->popup_id."\"></div>
                    </td>
                    <td style=\"width:580px;border:1px solid #8f8f8f;background-color:#ffffff;vertical-align:top;\">
                        <div style=\"padding:5px;\">
                            <div id=\"editor_title_holder".$this->popup_id."\"></div>
                            <div id=\"editor_content_holder".$this->popup_id."\"></div>
                            &nbsp;
                        </div>
                    </td>
                </tr>
            </table>
        </div>".$this->form->close_form();

        $this->content['popup_controls']["cmdTable"] = $tbl;
	}

	function set_class($class) {
        if (!$class)
            return;

        $this->class = $class;
        return;
	}

	function doit() {
        $btn = $_POST['button'];
        $jscript_action = $_POST['jscript_action'];

        $this->popup_id = $_POST['popup_id'];
        $content = $_POST['content'];
        $subsection = $_POST['subsection'];
        $preview = $_POST['preview'];

        if ($btn == 'save') {
        	$field_name = trim($_POST['field_name']);
        	if (strspn($field_name,"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ- 1234567890") != strlen($field_name)) {
                $this->set_error('err_1'.$this->popup_id);
        	    return $this->__trigger_error("Invalid field name! Field name must contain only valid alpha numeric characters. Spaces and hyphens are allowed.",E_USER_NOTICE,__FILE__,__LINE__,1);
        	}
        	$active = $_POST['active'];
        	$field_type = $_POST['type'];
        	$field_length = preg_replace('/[^.0-9]/', "",$_POST['field_length']);
        	$field_required = $_POST['field_required'];
        	$field_width = $_POST['field_width'];
        	$field_height = $_POST['field_height'];
        	$default_value = trim($field_type == 'checkbox' ? $_POST['default_value_ck'] : $_POST['default_value']);
        	$prepopulated = $_POST['prepopulated'];
        	$sector = ($_POST['sector'] ? $_POST['sector'] : 1);
        	$position = $_POST['position'];

        	switch ($subsection) {
        		case 'contact':
                if ($content == 'vendors')
                    $table = "vendor_contacts";
                elseif ($content == 'customers')
                    $table = "customer_contacts";
        		break;

        		case 'location';
        		$table = "locations";
        		break;

        		case 'product':
        		$table = "vendor_products";
        		break;

        		default:
        		$table = $content;
        		break;
        	}
        	$col_type = 'VARCHAR';

        	if (!$field_name) {
                $this->set_error('err_1'.$this->popup_id);
                $error = 1;
        	}

        	if (($content == 'proposals' && (($subsection == 'design' && $sector == 2) || ($subsection == 'install' && $sector == 7))) && $field_type != 'checkbox')
                return $this->__trigger_error("Only custom fields of type 'checkbox' are permitted within the chosen sector of the proposal $subsection window.",E_USER_NOTICE,__FILE__,__LINE__,1);

        	if ($field_type == 'select' || $field_type == 'select_multiple') {
                if (!trim($prepopulated)) {
                    $this->set_error('err_5'.$this->popup_id);
                    return $this->__trigger_error("In order to create a field of type drop down select or select multiple, you must include a list of prepopulated values for users to select from.",E_USER_NOTICE,__FILE__,__LINE__,1);
                } else {
                    $tmp = explode("\n",trim($prepopulated));
                    if (count($tmp) < 2) {
                        $this->set_error('err_5'.$this->popup_id);
                        return $this->__trigger_error("In order to create a field of type drop down select or select multiple, the list of prepopulated values must be at least 2 items in length.",E_USER_NOTICE,__FILE__,__LINE__,1);
                    }
                    for ($i = 0; $i < count($tmp); $i++)
                        $l[] = strlen(trim($tmp[$i]));

                    sort($l);
                    $col_length = array_pop($l);
                }

                unset($tmp);
                if ($default_value) {
                    $tmp = explode("\n",trim($prepopulated));
                    $valid = false;
                    for ($i = 0; $i < count($tmp); $i++) {
                        if (trim($tmp[$i]) == trim($default_value)) {
                            $valid = true;
                            break;
                        }
                    }
                    if ($valid == false) {
	                    $this->set_error('err_6'.$this->popup_id);
	                    return $this->__trigger_error("In order to use a default value when creating a field of type drop down select or select multiple, the default value must be found in the list of prepopulated values.",E_USER_NOTICE,__FILE__,__LINE__,1);
                    }
                    unset($tmp);
                }
        	}
        	if ($error)
                return $this->__trigger_error("Please confirm the required fields indicated below have been completed.",E_USER_NOTICE,__FILE__,__LINE__,1);
        	if ($field_type == 'text_box' && $default_value && $field_length && $field_length < strlen($default_value)) {
                $this->set_error('err_3'.$this->popup_id);
                $this->set_error('err_6'.$this->popup_id);

                return $this->__trigger_error("The maximum length you indicated is less than the default value! Please make sure the max length allowed is at least the length of your default value.",E_USER_NOTICE,__FILE__,__LINE__,1);
        	}


            if ($field_type == 'select' || $field_type == 'select_multiple') {
                $prepopulated_array = explode("\n",trim($prepopulated));
                for ($i = 0; $i < count($prepopulated_array); $i++) {
                    if (trim($prepopulated_array[$i]))
                         $prepopulated_tmp[] = trim($prepopulated_array[$i]);
                }
                $prepopulated = implode("|",$prepopulated_tmp);
                if ($field_type == 'select_multiple') {
                    $col_type = 'TEXT';
                    unset($col_length);
                }
            } elseif ($field_type == 'checkbox') {
                $col_type = 'TINYINT';
                $col_length = 1;
                unset($field_width,$field_height);
            } elseif ($field_type == 'textarea')
            	$col_type = 'TEXT';
            else {
            	if (trim($field_length))
                    $col_length = $field_length;
                elseif ($default_value)
                    $col_length = strlen($default_value);
                else
                    $col_length = 255;
            }

            if ($field_id = $_POST['field_id'])
                $this->fetch_custom_field($field_id);
            else {
                $r = $this->db->query("SELECT `obj_id`
                                       FROM custom_fields
                                       WHERE `field_name` = '$field_name'");
                if ($this->db->result($r,0,'obj_id'))
                    return $this->__trigger_error("The field name you are creating already exists. Please check and make sure you are not creating a duplicate custom field.",E_USER_NOTICE,__FILE__,__LINE__,1);
            }

            if (!$this->field_id || ($this->field_id && ($this->current_field['position'] != $position || $this->current_field['sector'] != $sector))) {
                $r = $this->db->query("SELECT `obj_id` , `position`
                                       FROM custom_fields
                                       WHERE ".($this->field_id ? "
                                           `obj_id` != ".$this->field_id." AND " : NULL)."
                                       `class`= '$content' AND `location` = '$subsection' AND `sector` = '$sector'
                                       ORDER BY `position` ASC");
                while ($row = $this->db->fetch_assoc($r)) {
                    $existing[] = $row['position'];
                    $id[] = $row['obj_id'];
                }

                if ($existing) {
                	$position_tmp = $this->current_field['position'];
                	if (!$this->field_id || $this->current_field['sector'] != $sector)
                	    $this->current_field['position'] = 9999;

                    for ($i = 0; $i < count($existing); $i++) {
                        if ($position < $this->current_field['position'] || $position > $this->current_field['position']) {
                            if (($existing[$i] < $this->current_field['position'] && $existing[$i] >= $position) || ($existing[$i] > $this->current_field['position'] && $existing[$i] <= $position))
                                $position_update[] = "UPDATE custom_fields
	                                                  SET custom_fields.position = (custom_fields.position ".($existing[$i] < $this->current_field['position'] && $existing[$i] >= $position ?
	                                                      "+" : "-")." 1)
	                                                  WHERE custom_fields.obj_id  = ".$id[$i];
                        }
                    }
                    $this->current_field['position'] = $position_tmp;
                }
            }
            if ($this->field_id && $content == 'proposals' && $this->current_field['sector'] != $sector)
	            $position_update[] = "UPDATE custom_fields
		                              SET custom_fields.position = (custom_fields.position - 1)
		                              WHERE `class` = '".$this->current_field['class']."' AND `location` = '".$this->current_field['location']."'
		                                  AND `sector` = '".$this->current_field['sector']."' AND `position` > '".$this->current_field['position']."'";

            if ($this->field_id) {
            	$col_name = $this->current_field['col_name'];
            	$old_col = $new_col = $col_name;
            	if ($field_name != $this->current_field['field_name']) {
            		if (strtolower($field_name) != strtolower($this->current_field['field_name'])) {
	                    $r = $this->db->query("SELECT `obj_id`
	                                           FROM custom_fields
	                                           WHERE `field_name` = '$field_name'");
	                    if ($this->db->result($r,0,'obj_id'))
	                        return $this->__trigger_error("The field name you have entered already exists. Please check and make sure you are not creating a duplicate custom field.",E_USER_NOTICE,__FILE__,__LINE__,1);

	                    $old_col = $this->current_field['col_name'];
		                $new_col = 'custom_'.strtolower(str_replace(' ','_',$field_name));

                        $sql[] = "`col_name` = '$new_col'";
                        $sql_rev[] = "`col_name` = '".$this->current_field['col_name']."'";
            		}
	                $sql[] = "`field_name` = '$field_name'";

	                $sql_rev[] = "`field_name` = '".$this->current_field['field_name']."'";
            	}
            	if ($this->current_field['max_length'] == 0)
                    unset($this->current_field['max_length']);
                if ($this->current_field['required'] == 0)
                    unset($this->current_field['required']);

                ($active != $this->current_field['active'] ? $sql[] = "`active` = '$active'" : NULL);
                ($active != $this->current_field['active'] ? $sql_rev[] = "`active` = '".$this->current_field['active']."'" : NULL);

                ($field_type != $this->current_field['field_type'] ? $sql[] = "`field_type` = '$field_type'" : NULL);
                ($field_type != $this->current_field['field_type'] ? $sql_rev[] = "`field_type` = '".$this->current_field['field_type']."'" : NULL);

                ($field_length != $this->current_field['max_length'] ? $sql[] = "`max_length` = '$field_length'" : NULL);
                ($field_length != $this->current_field['max_length'] ? $sql_rev[] = "`max_length` = '".$this->current_field['max_length']."'" : NULL);

                ($col_length != $this->current_field['col_length'] ? $sql[] = "`col_length` = '$col_length'" : NULL);
                ($col_length != $this->current_field['col_length'] ? $sql_rev[] = "`col_length` = '".$this->current_field['col_length']."'" : NULL);

                ($field_required != $this->current_field['required'] ? $sql[] = "`required` = '$field_required'" : NULL);
                ($field_required != $this->current_field['required'] ? $sql_rev[] = "`required` = '".$this->current_field['required']."'" : NULL);

                ($field_width != $this->current_field['field_width'] ? $sql[] = "`field_width` = '$field_width'" : NULL);
                ($field_width != $this->current_field['field_width'] ? $sql_rev[] = "`field_width` = '".$this->current_field['field_width']."'" : NULL);

                ($field_height != $this->current_field['field_height'] ? $sql[] = "`field_height` = '$field_height'" : NULL);
                ($field_height != $this->current_field['field_height'] ? $sql_rev[] = "`field_height` = '".$this->current_field['field_height']."'" : NULL);

                ($default_value != $this->current_field['default_value'] ? $sql[] = "`default_value` = '$default_value'" : NULL);
                ($default_value != $this->current_field['default_value'] ? $sql_rev[] = "`default_value` = '".$this->current_field['default_value']."'" : NULL);

            	if ($prepopulated != $this->current_field['prepopulated'] || ($field_type != 'select' && $field_type != 'select_multiple')) {
                    $sql[] = "`prepopulated` = ".($field_type != 'select' && $field_type != 'select_multiple' ?
                        "NULL" : "'$prepopulated'");
                    $sql_rev[] = "`prepopulated` = '".$this->current_field['prepopulated']."'";
            	}
                ($sector != $this->current_field['sector'] ? $sql[] = "`sector` = '$sector'" : NULL);
                ($sector != $this->current_field['sector'] ? $sql_rev[] = "`sector` = '".$this->current_field['sector']."'" : NULL);

                ($position != $this->current_field['position'] ? $sql[] = "`position` = '$position'" : NULL);
                ($position != $this->current_field['position'] ? $sql_rev[] = "`position` = '".$this->current_field['position']."'" : NULL);

            	if ($field_type != 'select' && $field_type != 'select_multiple') {
            		if ($field_type == 'checkbox')
                        $col_length = 1;
                    elseif (!$col_length && $field_type != 'textarea')
                        $col_length = ($field_length ? $field_length : 255);
            	}

            	$orig_default_value = $default_value;
                if (!$default_value && !$field_required)
                    $default_value = "NULL";

            	if ($sql) {
                    $this->db->query("UPDATE custom_fields
                                      SET ".implode(" , ",$sql)."
                                      WHERE obj_id = ".$this->field_id);
                    if ($this->db->db_error)
                        return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error,E_DATABASE_ERROR,__FILE__,__LINE__,1);

                    $update = true;
            	}
                if (strtolower($field_name) != strtolower($this->current_field['field_name']) || $orig_default_value != $this->current_field['default_value'] || $col_length != $this->current_field['col_length']) {
                    $update = true;
                    $this->db->query("ALTER TABLE $table
                                      CHANGE COLUMN `".$old_col."` `".$new_col."` $col_type ".($col_length ? "( ".$col_length." )" : NULL)." ".($col_type != 'TEXT' ?
                                          ($field_required ?
                                              "NOT NULL" : "NULL")." DEFAULT ".($default_value ?
                                                  ($default_value == 'NULL' ?
                                                      $default_value : "'".$default_value."'") : (!$default_value && !$field_required ?
                                                      "NULL" : "''")) : NULL)." COMMENT '".$field_name."'");
                    if ($this->db->db_error) {
                    	$db_err = $this->db->db_errno.' - '.$this->db->db_error;
                    	$this->revert($sql_rev);
                        return $this->__trigger_error($db_err,E_DATABASE_ERROR,__FILE__,__LINE__,1);
                    }
                }
            	if ($update)
                    $this->content['page_feedback'] = "Custom field has been saved.";
            	else
            	    $this->content['page_feedback'] = "No changes have been made.";

                if ($position_update) {
                    for ($i = 0; $i < count($position_update); $i++)
                        $this->db->query($position_update[$i]);
                }

            	$this->content['action'] = 'continue';
            	$this->content['jscript_action'] = "agent.call('custom_fields','sf_loadcontent','cf_loadcontent','field_editor','content=$content','sub=$subsection','popup_id=".$this->popup_id."','action=load_content');";
            	return;
            } else {
            	$col_name = 'custom_'.strtolower(str_replace(' ','_',$field_name));

            	$r = $this->db->query("SHOW COLUMNS FROM ".$table);
            	while ($row = $this->db->fetch_assoc($r)) {
            		if (substr($row['Field'],0,7) == 'custom_')
                        $table_cols[] = $row['Field'];
            	}
            	$this->content['action'] = 'continue';
            	$this->content['page_feedback'] = "Your custom field has been created.";

            	if (in_array($table,$table_cols)) {
            		$this->set_error('err_1'.$this->popup_id);
                    return $this->__trigger_error("A field already exists under the name specified below. Please make sure the field you are creating has not previously been created.",E_USER_NOTICE,__FILE__,__LINE__,1);
            	}

            	if (!$default_value && !$field_required)
                    $default_value = "NULL";

                $this->db->query("INSERT INTO `custom_fields`
                                  (`timestamp` , `created_by` , `active` , `field_name` , `col_name` , `col_length` , `field_type` ,
                                  `max_length` , `default_value` , `required` , `field_width` , `field_height` , `prepopulated` ,
                                  `class` , `table` , `location` , `sector` , `position`)
                                  VALUES (".time()." , '".$this->current_hash."' , '$active' , '$field_name' , '$col_name' , '$col_length' ,
                                  '$field_type' , '$field_length' , ".($default_value == 'NULL' ? $default_value : "'$default_value'")." , '$field_required' , '$field_width' , '$field_height' ,
                                  '$prepopulated' , '$content' , '$table' , '$subsection' , '$sector' , '$position')");
                if ($this->db->db_error)
                    return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error,E_DATABASE_ERROR,__FILE__,__LINE__,1);
                else
                    $insert_id = $this->db->insert_id();

                if ($default_value && $default_value != 'NULL')
                    $default_value = "'".$default_value."'";

                $this->db->query("ALTER TABLE $table
                                  ADD COLUMN `".$col_name."` $col_type ".($col_type != 'TEXT' ?
                                      "( ".$col_length." )" : NULL)." ".($field_required ?
                                      "NOT NULL" : "NULL").($col_type != 'TEXT' ?
                                          " DEFAULT ".($default_value ?
                                              $default_value : "''") : NULL)." COMMENT '".$field_name."'");
                if ($this->db->db_error) {
                	$db_err = $this->db->db_errno.' - '.$this->db->db_error;
                	$this->db->query("DELETE FROM custom_fields
                	                  WHERE `obj_id` = ".$insert_id);
                    return $this->__trigger_error($db_err,E_DATABASE_ERROR,__FILE__,__LINE__,1);
                }
	            if ($position_update) {
	                for ($i = 0; $i < count($position_update); $i++)
	                    $this->db->query($position_update[$i]);
	            }

                $this->content['page_feedback'] = "Your custom field has been created.";
                $this->content['action'] = 'continue';
                $this->content['jscript_action'] = "agent.call('custom_fields','sf_loadcontent','cf_loadcontent','field_editor','content=$content','sub=$subsection','popup_id=".$this->popup_id."','action=load_content');";
                return;
            }

        } elseif ($btn == 'rm') {
        	$field_id = $_POST['field_id'];
        	$this->fetch_custom_field($field_id);

            $this->db->query("DELETE FROM custom_fields
                              WHERE `obj_id` = ".$this->field_id);
            if ($this->db->db_error)
                return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error,E_DATABASE_ERROR,__FILE__,__LINE__,1);

        	//First update the table and remove the column
        	$this->db->query("ALTER TABLE ".$this->current_field['table']."
        	                  DROP COLUMN `".$this->current_field['col_name']."`");
        	if ($this->db->db_error)
                return $this->__trigger_error($this->db->db_errno.' - '.$this->db->db_error,E_DATABASE_ERROR,__FILE__,__LINE__,1);

            //Now reorder the position of the remainder of the fields
            $this->db->query("UPDATE custom_fields
                              SET custom_fields.position = (custom_fields.position - 1)
                              WHERE `class` = '".$this->current_field['class']."' AND `location` = '".$this->current_field['location']."'
                                  AND `sector` = '".$this->current_field['sector']."' AND `position` > '".$this->current_field['position']."'");

            $this->content['action'] = 'continue';
            $this->content['page_feedback'] = "Custom field has been removed";
            $this->content['jscript_action'] = "agent.call('custom_fields','sf_loadcontent','cf_loadcontent','field_editor','content=$content','sub=$subsection','popup_id=".$this->popup_id."','action=load_content');";

            return;
        }




	}

	function revert($sql) {
        if ($sql)
            $this->db->query("UPDATE custom_fields
                              SET ".implode(" , ",$sql)."
                              WHERE obj_id = ".$this->field_id);
	}

	function reload_position() {
        $content = $this->ajax_vars['content'];
        $sub = $this->ajax_vars['sub'];
        $sector = $this->ajax_vars['sector'];
        $popup_id = $this->ajax_vars['popup_id'];
        $field_id = $this->ajax_vars['field_id'];

        $r = $this->db->query("SELECT `position` , `obj_id`
                               FROM `custom_fields`
                               WHERE `class` = '$content' AND `location` = '$sub' AND `sector` = '$sector'
                               ORDER BY `position` DESC
                               LIMIT 1");
        $last_pos = $this->db->result($r,0,'position');
        if ($this->db->result($r,0,'obj_id') != $field_id) {
            if (!$field_id)
                $last_pos++;
        } elseif ($field_id) {
        	$r = $this->db->query("SELECT `position`
        	                       FROM `custom_fields`
        	                       WHERE `obj_id` = $field_id AND `class` = '$content' AND `location` = '$sub' AND `sector` = '$sector'");
            $current_position = $this->db->result($r,0,'position');
        }
        for ($i = 0; $i <= 25; $i++)
            $gen_array[] = $i;

        if (!$last_pos)
            $last_pos = 1;

        $position_ops = array_slice($gen_array,1,$last_pos);

        $select = $this->form->select("position",
			                          $position_ops,
			                          ($current_position ? $current_position : $last_pos),
			                          $position_ops,
			                          "blank=1");

        $this->content['html']['position_holder'.$popup_id] = $select;
	}
}
?>