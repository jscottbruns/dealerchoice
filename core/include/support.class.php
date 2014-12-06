<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class support extends AJAX_library {
	
	
    function support() {
        global $db;
    
        $this->form = new form;
        $this->db =& $db;
    
        $this->current_hash = $_SESSION['id_hash'];                
    }
    
    function support_form() {

        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "DealerChoice Support";
        $this->content['popup_controls']['popup_width'] = "625px;";
        $this->content['popup_controls']['popup_height'] = "475px;";
        $this->content['popup_controls']['popup_resize'] = 0;        

        $category = array('Proposals',
                          'Customers',
                          'Vendors',
                          'Receivables',
                          'Payables',
                          'Purchase Orders',
                          'Accounting',
                          'Reports');
        
        $tbl = $this->form->form_tag().
        $this->form->hidden(array('popup_id' =>  $this->popup_id,
                                  'clear_field' =>  1))."
        <div class=\"panel\" id=\"main_table".$this->popup_id."\">
            <div id=\"feedback_holder".$this->popup_id."\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                 <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message".$this->popup_id."\"></p>
            </div>
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:590px;height:450px;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;\">
                        <h5 style=\"margin-bottom:5px;color:#00477f;\">
                            DealerChoice Support : New Support Ticket                        
                        </h5>
                        <div style=\"margin-top:15px;margin-left:15px;\">
                            Use this form to open a support inquiry with DealerChoice support. Once submitted, you will receive an email confirmation with your assigned
                            ticket number. Please retain that email, as your ticket number is necessary for any follow up inquiries. 
                            <div style=\"margin-top:15px;\">
                                <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8f8f8f;width:475px;\">
                                    <tr>
                                        <td style=\"text-align:right;width:30%;background-color:#efefef;\">From: </td>
                                        <td style=\"background-color:#ffffff;width:70%\">
                                            ".stripslashes($_SESSION['my_name']).
                                            $this->form->hidden(array('full_name' => stripslashes($_SESSION['my_name'])))."
                                        </td>                                                                  
                                    </tr>
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\">Reply To Email: *</td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->text_box("name=reply_to",
                                                                    "value=".$_SESSION['my_email'],
                                                                    "size=35")."
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\">Category: *</td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->select("name=category",
                                                                  $category,
                                                                  '',
                                                                  $category,
                                                                  "onChange=agent.call('support','sf_loadcontent','cf_loadcontent','sub_category','cat='+this.options[this.selectedIndex].value);")."
                                        </td>
                                    </tr>               
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\">Sub Category: *</td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->select("name=sub_category",
                                                                  array('Select Category First...'),
                                                                  'Select Category First...',
                                                                  array('Select Category First...'),
                                                                  'disabled')."
                                        </td>
                                    </tr> 
                                    <tr>
                                        <td style=\"text-align:right;background-color:#efefef;\">Subject: *</td>
                                        <td style=\"background-color:#ffffff;\">
                                            ".$this->form->text_box("name=subject",
                                                                    "size=35",
                                                                    "maxlength=255")."
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style=\"background-color:#ffffff;\" colspan=\"2\">
                                            ".$this->form->text_area("name=detail",
                                                                     "value=Please provide as much detail as possible.\n\nIf applicable, please include details as to what you were doing at the time of the issue, any error messages received or otherwise relevant feedback, and what the expected result was.",
                                                                     "rows=7",
                                                                     "cols=85",
                                                                     "onFocus=if(\$F('clear_field')==1){this.value='';\$('clear_field').value='';}")."
                                        </td>
                                    </tr>                                                                                                                                                                                                                                                                                                                    
                                </table>
                                <div style=\"margin-top:10px;\">
                                    ".$this->form->button("value=Submit","onClick=")."
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table> 
        </div>".               
        $this->form->close_form();  

        $this->content['popup_controls']["cmdTable"] = $tbl;                        
    }
	
	
}


?>