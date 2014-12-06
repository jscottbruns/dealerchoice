<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class customer_credits extends AJAX_library {

    public $total;
    public $current_hash;
    public $proposal_hash;
    public $customer_hash;
    public $invoice_hash;
    public $credit_hash;
    public $credit_item_hash;
    public $credit_expense_hash;

    public $credit_info = array();
    public $current_credit = array();
    public $current_expense_line = array();
    public $current_credit_item = array();

    /**
     * Class constructor
     *
     * @param  Place    $where  Where something interesting takes place
     * @param  integer  $repeat How many times something interesting should happen
     * @throws Some_Exception_Class If something interesting cannot happen
     * @return Status
     */

    function customer_credits() {
        global $db;

        $this->form = new form;
        $this->db =& $db;

        if ( $param = func_get_arg(0) ) {

        	if ( is_array($param) ) {

        		$this->proposal_hash = $param['proposal_hash'];
        		$this->customer_hash = $param['customer_hash'];
        		$this->invoice_hash = $param['invoice_hash'];

        	} else
        		$this->current_hash = $param;

        } elseif ( $_POST['aa_sobj'] == __CLASS__ ) {

        	foreach ( $_POST as $_key => $_val ) {

        		if ( property_exists( __CLASS__, $_key ) )
	        		$this->$_key = $_val;
        	}
        }

        if ( ! $this->current_hash )
            $this->current_hash = $_SESSION['id_hash'];

        $this->p = new permissions($this->current_hash);
        $this->content['class_name'] = get_class($this);

        if ( $this->page_pref = $this->p->page_pref( get_class($this) ) ) {

            if ( $this->page_pref['sort'] != '*' ) {

                switch ( $this->page_pref['sort'] ) {

                    case 1:

                    $sql_p[] = "customer_credit.balance != 0";
                    break;

                    case 2:

                    $sql_p[] = "customer_credit.balance = 0";
                    break;
                }
            }

            if ( ! $this->active_search )
                $this->page_pref['custom'] = 1;
        }

        if ( $sql_p && ! $_POST['active_search'] ) {

            $sql = implode(" AND ", $sql_p);
            $this->page_pref['str'] = $sql;

            $this->get_total( array(
                'sql'   =>  $sql
            ) );

        } else {

            if ( $_POST['active_search'] ) {

                if ( $this->p->fetch_search( $_POST['active_search'], get_class($this) ) )
                    $this->total = $this->p->get_total();

            } else
                $this->get_total();
        }

        return;
    }

    function fetch_customer_credits($start, $order_by=NULL, $order_dir=NULL) {

        $this->credit_info = array();
        $_total = 0;
        $end = MAIN_PAGNATION_NUM;

        if ( $this->active_search ) {

            $result = $this->db->query("SELECT
	                                        t1.query,
	                                        t1.total,
	                                        t1.search_str
                                        FROM search t1
                                        WHERE t1.search_hash = '{$this->active_search}'");

            if ( $row = $this->db->fetch_assoc($result) ) {

	            $this->total = $row['total'];
	            $sql = base64_decode($row['query']);
	            unset($this->page_pref['custom']);

	            $this->detail_search = $row['detail_search'];
	            $this->search_vars = $this->p->load_search_vars($row['search_str']);
            }
        } elseif ( $this->page_pref['str'] )
            $sql = $this->page_pref['str'];

        if ( $order_by || $sql ) {

            $pattern = array(
                '/\bcustomer_credit\.(.*)\b/U',
                '/\bcustomer_invoice\.(.*)\b/U',
                '/\bproposals\.(.*)\b/U',
                '/\bcustomers\.(.*)\b/U'
            );
            $replace = array(
                't1.$1',
                't2.$1',
                't3.$1',
                't4.$1'
            );

            if ( $order_by )
                $order_by = preg_replace($pattern, $replace, $order_by);

            if ( $sql )
                $sql = preg_replace($pattern, $replace, $sql);
        }


        $result = $this->db->query("SELECT
                                        t1.*,
                                        t4.customer_name,
                                        t2.invoice_no,
                                        t2.amount AS invoice_amount,
                                        t2.proposal_hash
                                    FROM customer_credit t1
                                    LEFT JOIN customer_invoice t2 ON t2.invoice_hash = t1.invoice_hash
                                    LEFT JOIN proposals t3 ON t3.proposal_hash = t2.proposal_hash
                                    LEFT JOIN customers t4 ON t4.customer_hash = t1.customer_hash
                                    WHERE " .
                                    ( $this->customer_hash ?
                                        "t1.customer_hash = '{$this->customer_hash}' AND " : NULL
                                    ) .
                                    ( $this->proposal_hash ?
                                        "t1.proposal_hash = '{$this->proposal_hash}' AND " : NULL
                                    ) .
                                    ( $sql ?
                                        "$sql AND " : NULL
                                    ) . "t1.deleted = 0 " .
                                    ( $order_by ?
                                        "ORDER BY $order_by " .
                                        ( $order_dir ?
                                            $order_dir : " ASC "
                                        ) : "ORDER BY t1.credit_no ASC"
                                    ) . "
                                    LIMIT $start, $end");
        while ( $row = $this->db->fetch_assoc($result) ) {

        	$_total++;
            array_push($this->credit_info, $row);
        }

        return $_total;
    }

    function fetch_credit_record($credit_hash, $edit=false) {

    	unset($this->credit_hash, $this->invoice_hash, $this->proposal_hash);
    	$this->current_credit = array();

        $result = $this->db->query("SELECT
                                        t1.*,
                                        t3.invoice_no,
                                        t4.customer_name,
                                        t5.proposal_no,
                                        t5.proposal_hash,
                                        COUNT( t2.expense_hash ) AS total_expenses,
                                        COUNT( t2.item_hash ) AS total_items
                                    FROM customer_credit t1
                                    LEFT JOIN customer_credit_expenses t2 ON t2.credit_hash = t1.credit_hash
                                    LEFT JOIN customer_invoice t3 ON t3.invoice_hash = t1.invoice_hash
                                    LEFT JOIN customers t4 ON t4.customer_hash = t1.customer_hash
                                    LEFT JOIN proposals t5 ON t5.proposal_hash = t1.proposal_hash
                                    WHERE t1.credit_hash = '$credit_hash' AND t1.deleted = 0
                                    GROUP BY t1.credit_hash");
        if ( $row = $this->db->fetch_assoc($result) ) {

            $this->current_credit = $row;
            $this->credit_hash = $credit_hash;
            if ( $row['proposal_hash'] )
                $this->proposal_hash = $row['proposal_hash'];
            if ( $row['invoice_hash'] )
                $this->invoice_hash = $row['invoice_hash'];

            if ( $edit )
                $this->lock = $this->content['row_lock'] = $this->p->lock("customer_credit", $this->credit_hash, $this->popup_id);

            $this->current_credit['expense_items'] = $this->current_credit['credit_item'] = array();

            if ( $this->current_credit['total_expenses'] )
                $this->current_credit['expense_items'] = $this->fetch_credit_expenses($this->credit_hash);

            if ( $this->current_credit['total_items'] )
                $this->current_credit['credit_item'] = $this->fetch_credit_items($this->credit_hash);

            return true;
        }

        return false;
    }

    function fetch_credit_expenses($credit_hash) {

    	$expense_item = array();
        $result = $this->db->query("SELECT expense_hash
                                    FROM customer_credit_expenses t1
                                    WHERE t1.credit_hash = '$credit_hash'
                                    ORDER BY t1.obj_id ASC");
        while ( $row = $this->db->fetch_assoc($result) )
            $expense_item[] = $row['expense_hash'];

        return $expense_item;
    }

    function fetch_expense_line($expense_hash, $credit_hash) {

        $result = $this->db->query("SELECT
                                        t1.credit_hash,
                                        t1.expense_hash,
	                                    t1.amount,
	                                    t1.tax,
	                                    t1.memo,
	                                    t1.correction_code,
                                        t2.code,
                                        t2.descr,
                                        t3.account_name,
                                        t3.account_no,
                                        t3.account_hash,
	                                    t4.account_hash AS expense_account_hash,
	                                    t4.account_name AS expense_account_name,
	                                    t4.account_no AS expense_account_no
                                    FROM customer_credit_expenses t1
                                    LEFT JOIN correction_codes t2 ON t2.correction_hash = t1.correction_code
                                    LEFT JOIN accounts t3 ON t3.account_hash = t2.account_hash
                                    LEFT JOIN accounts t4 ON t4.account_hash = t1.account_hash
                                    WHERE t1.credit_hash = '$credit_hash' AND t1.expense_hash = '$expense_hash'");
        if ( $row = $this->db->fetch_assoc($result) ) {

            if ( $row['correction_code'] == '--AUTO--' ) {

                $row['account_hash'] = $row['expense_account_hash'];
                $row['account_name'] = $row['expense_account_name'];
                $row['account_no'] = $row['expense_account_no'];
            }

            $row['account_name'] = ( trim($row['account_no']) ? trim($row['account_no']) . " : " . stripslashes($row['account_name']) : stripslashes($row['account_name']) );

            $this->current_expense_line = $row;
            $this->credit_expense_hash = $expense_hash;

            return true;
        }

        return false;
    }

    function doit() {

        $action = $_POST['action'];

        if ( $_POST['popup_id'] )
            $this->popup_id = $this->content['popup_controls']['popup_id'] = $_POST['popup_id'];

        if ( $_POST['active_search'] )
            $this->active_search = $_POST['active_search'];

        if ( $action )
            return $this->$action();
    }


    function edit_credit() {

        $rand_err_new = rand(500,5000000);

        $this->content['popup_controls']['popup_id'] = $this->popup_id = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Issue New Credit";

        if ( $credit_hash = $this->ajax_vars['credit_hash'] ) {

            if ( ! $this->fetch_credit_record($this->ajax_vars['credit_hash'], 1) )
                $this->__trigger_error("System error encountered during credit lookup. Unable to fetch customer credit for edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

            $this->content['popup_controls']['popup_title'] = "Edit Customer Credit";
        }

        if ( $proposal_hash = $this->ajax_vars['proposal_hash'] ) {

        	$r = $this->db->query("SELECT t1.customer_hash, t1.proposal_no, t2.customer_name
        	                       FROM proposals t1
        	                       LEFT JOIN customers t2 ON t2.customer_hash = t1.customer_hash
        	                       WHERE t1.proposal_hash = '{$this->ajax_vars['proposal_hash']}'");
            if ( $row = $this->db->fetch_assoc($r) ) {

            	$credit_proposal = array(
	            	'proposal_no'      =>  $row['proposal_no'],
	            	'proposal_hash'    =>  $row['proposal_hash']
            	);
            	$credit_customer = array(
	            	'customer_name'    =>  $row['customer_name'],
	            	'customer_hash'    =>  $row['customer_hash']
            	);
            }
        }

        if ( ! $this->credit_hash )
            $this->content['focus'] = 'credit_customer';

        $tab = 1;

        $tbl =
        $this->form->form_tag() .
        $this->form->hidden( array(
            "credit_hash"   =>  $this->credit_hash,
            "popup_id"      =>  $this->popup_id,
            "p"             =>  $this->ajax_vars['p'],
            "order"         =>  $this->ajax_vars['order'],
            "order_dir"     =>  $this->ajax_vars['order_dir'],
            "changed"       =>  '',
            "from_proposal" =>  ( $credit_proposal ? 1 : NULL )
        ) ) . "
        <div class=\"panel\" id=\"main_table{$this->popup_id}\" style=\"margin-top:0;\">
            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message{$this->popup_id}\"></p>
            </div>
            <table cellspacing=\"1\" cellpadding=\"5\" class=\"popup_tab_table\">
                <tr>
                    <td style=\"padding:0;\">
                         <div style=\"background-color:#ffffff;height:100%;\">
                            <div style=\"margin:15px 35px;\">
                                <div style=\"float:right;margin-top:" . ( $this->credit_hash ? "25px" : "10px" ) . ";margin-right:10px;" . ( ! $this->credit_hash ? "padding-bottom:10px;" : NULL ) . "\">" .
                                ( $this->p->active_lock ?
                                    "<div style=\"text-align:right;padding-right:15px;\">" . $this->p->lock_stat(get_class($this), $this->popup_id) . "</div>" :
                                    ( ( $this->credit_hash && $this->lock && ! $this->current_credit['deleted'] && $this->p->ck(get_class($this), 'E') ) || ! $this->credit_hash ?
	                                    $this->form->button(
    	                                    "value=Save &amp; Close",
                                            "id=credit_btn",
                                            ( $this->credit_hash ?
                                                "onClick=if( \$F('changed') ) { if( confirm('You have changed this credit. Are you sure you want to save the new changes?') ) { this.disabled=1;submit_form(\$('credit_hash').form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit'); } } else { submit_form(\$('credit_hash').form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit'); }"
                                                :
                                                "onClick=this.disabled=1;submit_form(\$('credit_hash').form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit');"
                                            )
	                                    ) : NULL
	                                )
	                            ) . "
                                </div>
                                <h3 style=\"margin-bottom:5px;color:#00477f;\">" .
	                            ( $this->credit_hash ?
                                    "View/Edit Customer Credit" : "Create New Customer Credit"
	                            ) . "
                                </h3>" .
	                            ( $this->credit_hash ?
	                                "<div style=\"padding:5px 0 5px 5px;\">" .
    	                            ( $this->lock && $this->credit_hash && ! $active_payments && ! bccomp($this->current_credit['amount'], $this->current_credit['balance'], 2) && ! $this->current_credit['deleted'] && $this->p->ck(get_class($this), 'D') ?
	                                    "<a href=\"javascript:void(0);\" onClick=\"if(confirm('Are you sure you want to delete this credit? This action CAN NOT be undone!')){submit_form(\$('credit_hash').form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit','method=rm');}\" class=\"link_standard\"><img src=\"images/kill.gif\" title=\"Delete this credit\" border=\"0\" /></a>" : NULL
    	                            ) . "
	                                </div>
	                                <ul id=\"maintab{$this->popup_id}\" class=\"shadetabs\">
	                                    <li class=\"selected\"><a href=\"javascript:void(0);\" id=\"cntrl_tcontent1{$this->popup_id}\" rel=\"tcontent1{$this->popup_id}\" onClick=\"expandcontent(this);\" >Credit Details</a></li>
	                                    <li class=\"selected\"><a href=\"javascript:void(0);\" id=\"cntrl_tcontent2{$this->popup_id}\" rel=\"tcontent2{$this->popup_id}\" onClick=\"expandcontent(this);\" >Credit Applied</a></li>
	                                </ul>" : NULL
                                ) . "
                                <div " . ( $this->credit_hash ? "id=\"tcontent1{$this->popup_id}\" class=\"tabcontent\"" : NULL ) . ">
                                    <table cellpadding=\"5\" cellspacing=\"1\" style=\"background-color:#cccccc;width:95%;\">
                                        <tr>
                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;padding-top:10px;width:25%;\" id=\"err1{$this->popup_id}\">Customer:</td>
                                            <td style=\"background-color:#ffffff;padding-top:10px;\">" .
                                                ( $credit_customer ?
                                                    $credit_customer['customer_name'] .
                                                    $this->form->hidden( array(
                                                        'customer_hash'     =>  $credit_customer['customer_hash'],
                                                        'credit_customer'   =>  $credit_customer['customer_hash']
                                                    ) ) :
		                                            ( bccomp($this->current_credit['amount'], $this->current_credit['balance'], 2) ?
		                                                $this->form->hidden( array(
		                                                    "customer_hash" => $this->current_credit['customer_hash']
		                                                ) ) . stripslashes($this->current_credit['customer_name'])
		                                                :
		                                                $this->form->text_box(
		                                                    "name=credit_customer",
		                                                    "value=" . stripslashes($this->current_credit['customer_name']),
		                                                    "autocomplete=off",
		                                                    "size=30",
		                                                    "onChange=selectItem('changed=1');",
		                                                    "onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('customer_credits','credit_customer','customer_hash',1);}",
		                                                    "onKeyUp=if(ka==false && this.value){key_call('customer_credits','credit_customer','customer_hash',1);}",
		                                                    "onBlur=key_clear();if(!\$F('customer_hash')){\$('proposal_no').disabled=1;\$('proposal_no').value='Select customer first...'}",
		                                                    "onKeyDown=clear_values('customer_hash');",
		                                                    "tabindex=" . $tab++
		                                                ) .
		                                                $this->form->hidden( array(
		                                                    "customer_hash" => $this->current_credit['customer_hash']
		                                                ) )
		                                            )
		                                        ) . "
                                                <span id=\"customer_note_holder\"></span>
                                            </td>
                                        </tr>
                                        <tr id=\"invoice_holder{$this->popup_id}\">
                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err5{$this->popup_id}\">Proposal No:</td>
                                            <td style=\"background-color:#ffffff;\">
                                                <div id=\"vendor_po_holder\">" .
                                                    ( $credit_proposal ?
                                                        $credit_proposal['proposal_no'] .
                                                        $this->form->hidden( array(
                                                            'proposal_hash' =>  $credit_proposal['proposal_hash'],
                                                            'proposal_no'   =>  $credit_proposal['proposal_hash']
                                                        ) ) :
														$this->form->text_box(
														    "name=proposal_no",
														    "value=".
														    ( ! $this->credit_hash ?
														        "Select customer first..." : $this->current_credit['proposal_no']
														    ),
														    "onChange=selectItem('changed=1');",
														    ( ! $this->credit_hash ?
														        "disabled title=Select customer first..." : NULL
														    ),
														    "onFocus=if(!\$F('customer_hash')){\$('proposal_no').disabled=1;\$('proposal_no').value='Select customer first...'}else{position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('customer_credits','proposal_no','proposal_hash',1,'customer_hash');}}",
														    "onKeyUp=if(ka==false && this.value && \$F('customer_hash')){key_call('customer_credits','proposal_no','proposal_hash',1,'customer_hash');}",
														    "onBlur=key_clear();",
														    "size=20",
														    "onKeyDown=clear_values('proposal_hash');",
														    "tabindex=" . $tab++
														) .
														$this->form->hidden( array(
	    													"proposal_hash" => $this->current_credit['proposal_hash']
														) ) . "
														<span style=\"padding-left:5px;font-style:italic;font-size:90%;\">Optional</span>"
													) . "
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err2{$this->popup_id}\" nowrap>Amount:</td>
                                            <td style=\"background-color:#ffffff;\">" .
                                                $this->form->text_box(
                                                    "name=credit_amount",
                                                    "onChange=if( \$F('credit_hash') ){selectItem('changed=1');}if( this.value ){ if( \$('amount_{$rand_err_new}') ){\$('amount_{$rand_err_new}').value=formatCurrency( this.value );} }",
                                                    "value=" . number_format($this->current_credit['amount'], 2),
                                                    "size=10",
                                                    "onFocus=if(this.value){this.select();}",
                                                    "style=text-align:right;",
                                                    "onBlur=if( this.value ){this.value=formatCurrency(this.value);}",
                                                    "tabindex=" . $tab++
                                                ) . "
                                            </td>
                                        </tr>" .
                                        ( $this->credit_hash ? "
	                                        <tr>
	                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" nowrap>Open Balance</td>
	                                            <td style=\"background-color:#ffffff;\">" .
                                                ( bccomp($this->current_credit['balance'], 0, 2) == -1 ?
	                                                "(\$" . number_format( bcmul($this->current_credit['balance'], -1, 2), 2) . ")" : "\$" . number_format($this->current_credit['balance'], 2)
                                                ) . "
	                                            </td>
	                                        </tr>" : NULL
                                        ) . "
                                        <tr>
                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;\" id=\"err3{$this->popup_id}\">Reference:</td>
                                            <td style=\"background-color:#ffffff;\">" .
                                                $this->form->text_box(
                                                    "name=credit_no",
                                                    "value={$this->current_credit['credit_no']}",
                                                    ( $this->credit_hash ? "onChange=selectItem('changed=1');" : NULL ),
                                                    "maxlength=16",
                                                    "size=15",
                                                    "tabindex=" . $tab++
                                                ) . "
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style=\"text-align:right;background-color:#efefef;font-weight:bold;\" id=\"err4{$this->popup_id}\" nowrap>Date:</td>
                                            <td style=\"background-color:#ffffff;\" id=\"credit_date_holder{$this->popup_id}\">" .
                                            ( $this->credit_hash ?
                                                date(DATE_FORMAT, strtotime($this->current_credit['credit_date'])) : NULL
                                            ) . "
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style=\"background-color:#efefef;text-align:right;font-weight:bold;vertical-align:top;\" >Notes:</td>
                                            <td style=\"background-color:#ffffff;\">" .
                                                $this->form->text_area(
                                                    "name=notes",
                                                    "value=" . stripslashes($this->current_credit['notes']),
                                                    "rows=3",
                                                    "cols=40",
                                                    ( $this->credit_hash ? "onChange=selectItem('changed=1');" : NULL ),
                                                    "tabindex=" . $tab++
                                                ) . "
                                            </td>
                                        </tr>";

                                        $accounting = new accounting($this->current_hash);
                                        $accounting->fetch_correction_codes();

                                        $last_index = $tab;
                                        $dist_total = 0;

                                        for ( $i = 0; $i < $this->current_credit['total_expenses']; $i++ ) {

                                        	if ( ! $this->fetch_expense_line($this->current_credit['expense_items'][$i], $this->credit_hash) )
                                            	return $this->__trigger_error("System error encountered when attempting to lookup credit distribution items. Unable to fetch credit from database. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

                                            $rand_err = rand(500, 50000);
                                            $code_in = $code_out = array();

	                                        foreach ( $accounting->correction_codes as $code => $code_array ) {

	                                            if ( $code_array['active'] || ( ! $code_array['active'] && $this->current_expense_line['correction_code'] == $code_array['correction_hash'] ) ) {

	                                                $code_in[] = $code_array['correction_hash'];
	                                                $code_out[] = "{$code} - " . ( strlen($code_array['descr']) > 30 ? substr($code_array['descr'], 0, 27) . "..." : $code_array['descr'] );
	                                            }
	                                        }

	                                        $dist_total = bcadd($dist_total, $this->current_expense_line['amount'], 2);

                                            $credit_dist_table .= "
                                            <tr>
                                                <td>
                                                    <div id=\"code_select_{$rand_err}\">" .
	                                                    $this->form->select(
    	                                                    "correction_code{$rand_err}",
	                                                        ( $this->p->ck('system_config', 'L', 'company_settings') ? array_merge($code_out, array("&nbsp;&nbsp;--&nbsp;&nbsp;Create/Edit Correction Codes") ) : $code_out ),
	                                                        $this->current_expense_line['correction_code'],
	                                                        ( $this->p->ck('system_config', 'L', 'company_settings') ? array_merge($code_in, array('*') ) : $code_in ),
	                                                        "style=width:225px;",
	                                                        "onChange=if(this.options[this.selectedIndex].value=='*'){agent.call('system_config','sf_loadcontent','show_popup_window','correction_code_list','popup_id=correction_code_win','from_credit=1','credit_popup_id={$this->popup_id}');this.value='" . ( $this->current_expense_line['correction_code'] ? $this->current_expense_line['correction_code'] : NULL ) . "';}else if( this.options[ this.selectedIndex ].value == '' ){ \$('amount_{$rand_err}').value='';\$('memo_{$rand_err}').value='';} tally();",
	                                                        "tabindex=" . $tab++
	                                                    ) . "
                                                    </div>" .
                                                    $this->form->hidden( array(
                                                        "error_node[]"             =>  $rand_err,
                                                        "expense_id_{$rand_err}"   =>  $this->current_expense_line['expense_hash'],
                                                        "orig_value_{$rand_err}"   =>  $this->current_expense_line['correction_code']
                                                    ) ) . "
                                                </td>
                                                <td>" .
                                                    $this->form->text_box(
                                                        "name=amount_{$rand_err}",
                                                        "onChange=selectItem('changed=1');",
                                                        ( $this->credit_hash && ! $this->p->ck(get_class($this), 'E') ? "readonly" : NULL ),
                                                        "value=" . ( $this->current_expense_line['amount'] ? number_format($this->current_expense_line['amount'], 2) : NULL ),
                                                        "size=14",
                                                        "style=text-align:right",
                                                        "onFocus=if( this.value ){ select(); } else { calc_amounts('$rand_err') }",
                                                        "onBlur=if(this.value){this.value=formatCurrency(this.value);}",
                                                        "onChange=tally()",
                                                        "cid=dist_amt",
                                                        "tabindex=" . $tab++
                                                    ) . "
                                                </td>
                                                <td>" .
                                                    $this->form->text_box(
                                                        "name=memo_{$rand_err}",
                                                        "onChange=selectItem('changed=1');",
                                                        ( $this->credit_hash && !$this->p->ck(get_class($this), 'E') ? "readonly" : NULL ),
                                                        "value=" . stripslashes($this->current_expense_line['memo']),
                                                        "maxlength=255",
                                                        "size=30",
                                                        "tabindex=" . $tab++
                                                    ) . "
                                                </td>
                                            </tr>";
                                        }

                                        if ( ! $this->credit_hash ) {

                                            $code_in = $code_out = array();
                                            foreach ( $accounting->correction_codes as $code => $code_array ) {

                                                if ( $code_array['active'] || ( ! $code_array['active'] && $expenses[$i]['correction_code'] == $code_array['correction_hash'] ) ) {

                                                    $code_in[] = $code_array['correction_hash'];
                                                    $code_out[] = "$code - " . ( strlen($code_array['descr']) > 30 ? substr($code_array['descr'], 0, 27) . "..." : $code_array['descr'] );
                                                }
                                            }

                                            for ( $i = 0; $i < 3; $i++ ) {

                                                $credit_dist_table .= "
                                                <tr>
                                                    <td>" .
                                                    ( $code_out || ( ! $code_out && $this->p->ck('system_config', 'L', 'company_settings')) ?
                                                        "<div id=\"code_select_{$rand_err_new}\">" .
	                                                        $this->form->select(
    	                                                        "correction_code{$rand_err_new}",
	                                                            ( is_array($code_out) ? ( $this->p->ck('system_config', 'L', 'company_settings') ? array_merge($code_out, array("&nbsp;&nbsp;--&nbsp;&nbsp;Create/Edit Correction Codes") ) : $code_out ) : array("&nbsp;&nbsp;--&nbsp;&nbsp;Create/Edit Correction Codes") ),
	                                                            $expenses[$i]['correction_code'],
	                                                            ( is_array($code_out) ? ( $this->p->ck('system_config', 'L', 'company_settings') ? array_merge($code_in, array('*') ) : $code_in ) : array('*') ),
	                                                            "onChange=if( this.options[ this.selectedIndex ].value == '*' ){agent.call('system_config','sf_loadcontent','show_popup_window','correction_code_list','popup_id=correction_code_win','from_credit=1','credit_popup_id={$this->popup_id}');this.value='';}else if( this.options[ this.selectedIndex ].value == '' ){ \$('amount_{$rand_err_new}').value='';\$('memo_{$rand_err_new}').value='';} tally();",
	                                                            "style=width:225px;",
	                                                            "tabindex=" . $tab++
	                                                        ) . "
                                                        </div>" .
                                                        $this->form->hidden( array(
                                                            'error_node[]'    =>  $rand_err_new
                                                        ) )  : "<img src=\"images/alert.gif\" title=\"Undefined correction code list! Make sure you've defined your customer credit correction codes before creating new credits! System Config -> Company &amp; System Settings -> System Settings -> Customer credit correction codes\" />"
                                                    ) . "
                                                    </td>
                                                    <td>" .
                                                        $this->form->text_box(
                                                            "name=amount_{$rand_err_new}",
                                                            "value=" . ( $this->ajax_vars['remaining_amt'] ? number_format($this->ajax_vars['remaining_amt'], 2) : NULL ),
                                                            "size=14",
                                                            "style=text-align:right",
                                                            "onFocus=if( this.value ){ select(); } else { calc_amounts('$rand_err_new') }",
                                                            "onChange=tally()",
                                                            "cid=dist_amt",
                                                            "onBlur=if(this.value){this.value=formatCurrency(this.value);}",
                                                            "tabindex=" . $tab++
                                                        ) . "
                                                    </td>
                                                    <td>" .
                                                        $this->form->text_box(
                                                            "name=memo_{$rand_err_new}",
                                                            "value=",
                                                            "maxlength=255",
                                                            "size=30",
                                                            "tabindex=" . $tab++
                                                        ) . "
                                                    </td>
                                                </tr>";

                                                $rand_err_new = rand(500, 50000);
                                            }
                                        }

	                                    $tbl .= "
                                        <tr>
                                            <td colspan=\"2\" style=\"background-color:#ffffff;width:100%\">
                                                <div style=\"padding:5px 0;font-weight:bold;\">
                                                    Distribution Total: <span id=\"credit_amount_holder\">" .
            	                                    ( $this->credit_hash ?
	            	                                    "\$" . number_format($dist_total, 2) : NULL
            	                                    ) . "</span>
            	                                </div>
                                                <div id=\"expense_holder{$this->popup_id}\">";

                                                    $inner_tbl = "
                                                    <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" style=\"width:97%;\" id=\"credit_dist_holder{$this->popup_id}\">
                                                        <tr class=\"thead\" style=\"font-size:9pt;\">
                                                            <td class=\"smallfont\" style=\"width:200px;vertical-align:bottom;font-weight:bold;\">Correction Code</td>
                                                            <td class=\"smallfont\" style=\"width:90px;vertical-align:bottom;font-weight:bold;text-align:right;\">Amount</td>
                                                            <td class=\"smallfont\" style=\"vertical-align:bottom;font-weight:bold;\">Memo</td>
                                                        </tr>" .
                                                        $credit_dist_table;

                                                    $inner_tbl .= "
                                                    </table>" .
                                                    ( ( $this->lock && $this->credit_hash && $this->p->ck(get_class($this), 'E')) || ! $this->credit_hash ?
                                                        "<div style=\"float:right;padding:10px 25px;\">
                                                            <a href=\"javascript:void(0);\" class=\"link_standard\" onClick=\"selectItem('changed=1');submit_form(\$('credit_hash').form,'customer_credits','exec_post','refresh_form','action=doit_add_line');\">[<small>add more lines</small>]</a>
                                                        </div>" : NULL
                                                    );

                                                $tbl .=
                                                ( ! $local ?
                                                    $inner_tbl : NULL
                                                ) . "
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>";

                            if ( $this->credit_hash ) {

                            	$applied = $this->fetch_credit_applied($this->credit_hash);
                                $total = count($applied);

                            	$tbl .= "
                                <div id=\"tcontent2{$this->popup_id}\" class=\"tabcontent\">
                                    <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:600px;margin-top:0;\" class=\"smallfont\">
                                        <tr>
                                            <td style=\"padding:0;\">
                                                 <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:100%;\" >
                                                    <tr>
                                                        <td style=\"font-weight:bold;vertical-align:middle;background-color:#efefef;\" colspan=\"7\" class=\"smallfont\">" .
	                                                        ( ! $applied ?
	                                                            "This credit has not been applied" : "This credit has been applied on $total payment(s)."
	                                                        ) . "
                                                            <div style=\"margin-left:25px;padding-top:5px;font-weight:normal;font-style:italic;\">" .
                                                            ( bccomp($this->current_credit['balance'], 0, 2) == -1 ?
                                                                "Credit has been applied in full." : "Credit has an available balance of \$" . number_format($this->current_credit['balance'], 2)
                                                            ) . "
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr class=\"thead\" style=\"font-weight:bold;\">
                                                        <td style=\"font-size:8pt;\">Date Applied</td>
                                                        <td style=\"font-size:8pt;\">Invoice No.</td>
                                                        <td style=\"font-size:8pt;\">Comments</td>
                                                        <td style=\"font-size:8pt;\" class=\"num_field\">Amount</td>
                                                    </tr>";

                                                for ( $i = 0; $i < $total; $i++ ) {

                                                    $tbl .= "
                                                    <tr >
                                                        <td style=\"font-size:8pt;border-bottom:1px solid #cccccc;\">" . date(DATE_FORMAT, strtotime($applied[$i]['receipt_date'])) . "</td>
                                                        <td style=\"font-size:8pt;border-bottom:1px solid #cccccc;\"><a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice','sf_loadcontent','show_popup_window','edit_invoice','invoice_hash={$applied[$i]['invoice_hash']}','popup_id=invoice_window');\" class=\"link_standard\" title=\"View this invoice\">{$applied[$i]['invoice_no']}</a></td>
                                                        <td style=\"font-size:8pt;border-bottom:1px solid #cccccc;\">" .
                                                        ( $applied[$i]['comments'] ?
	                                                        ( strlen($applied[$i]['comments']) > 45 ?
	                                                            substr( stripslashes($applied[$i]['comments']), 0, 42) . "..." : stripslashes($applied[$i]['comments'])
	                                                        ) : "&nbsp;"
	                                                    ) . "
                                                        </td>
                                                        <td style=\"font-size:8pt;border-bottom:1px solid #cccccc;\" class=\"num_field\">" .
                                                        ( bccomp($applied[$i]['receipt_amount'], 0, 2) == -1 ?
                                                            "(\$" . number_format( bcmul($applied[$i]['receipt_amount'], -1, 2), 2) . ")" : "\$" . number_format($applied[$i]['receipt_amount'], 2)
                                                        ) .  "
                                                        </td>
                                                    </tr>";

                                                    $total_applied = bcadd($total_applied, $applied[$i]['receipt_amount'], 2);
                                                }

                                                if ( ! $total )
                                                    $tbl .= "
                                                        <tr>
                                                            <td colspan=\"4\" style=\"border-bottom:1px solid #cccccc;\" class=\"smallfont\">This credit has not been applied.</td>
                                                        </tr>";

                                                $tbl .= "
                                                    <tr>
                                                        <td colspan=\"4\" style=\"background-color:#efefef;text-align:right;\">
                                                            <table>
                                                                <tr>
                                                                    <td style=\"text-align:right;font-weight:bold;padding-bottom:3px;\" >Total Credit Amount:</td>
                                                                    <td style=\"font-weight:bold;text-align:left;padding-bottom:3px;\" >\$" . number_format($this->current_credit['amount'], 2) . "</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"text-align:right;font-weight:bold;padding-bottom:3px;\" >Total Applied:</td>
                                                                    <td style=\"font-weight:bold;text-align:left;padding-bottom:3px;\" >\$" . number_format($total_applied, 2) . "</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style=\"text-align:right;font-weight:bold;\" >Credit Balance: </td>
                                                                    <td style=\"font-weight:bold;text-align:left;\" >" .
                                                                    ( bccomp($this->current_credit['balance'], 0, 2) == -1 ?
                                                                        "(\$" . number_format( bcmul($this->current_credit['balance'], -1, 2), 2) . ")" : "\$" . number_format($this->current_credit['balance'], 2)
                                                                    ) . "
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </td>
                                                    <tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </div>";
                            }

                            $tbl .= "
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>" .
        $this->form->hidden( array(
            'last_index'    =>  $last_index
        ) ) .
        $this->form->close_form();

        $this->content['jscript'][] = "
        tally = function() {

            var item = $$('input[cid=dist_amt]');
            var t_total = 0;

            for ( var i = 0; i < item.length; i++ ) {

                if ( \$F( item[i] ) ) {

                    var val = parseFloat( \$F( item[i] ).toString().replace(/[^-.0-9]/g, '') );
                    t_total += val;
                }
            }

            \$('credit_amount_holder').innerHTML = '\$' + formatCurrency( t_total );

        }
        calc_amounts = function() {

            var field = arguments[0];
            var total = parseFloat( \$F('credit_amount').toString().replace(/[^-.0-9]/g, '') );
            var item = $$('input[cid=dist_amt]');

            var t_total = 0;

            if ( \$('amount_' + field) && \$F('amount_' + field) ) {
                return;
            }

            for ( var i = 0; i < item.length; i++ ) {

                if ( match = /^amount_([0-9]*)$/.exec( $(item[i]).name ) ) {

	                if ( \$F( item[i] ) && match[1] != field ) {

	                    t_total += parseFloat( \$F( item[i] ).toString().replace(/[^-.0-9]/g, '') );
	                }
                }
            }

            if ( \$('amount_' + field) && total - t_total ) {

                \$('amount_' + field).value = formatCurrency( total - t_total );
            }

        }";

        if ( $this->credit_hash )
            $this->content['jscript'][] = "setTimeout('initializetabcontent(\'maintab{$this->popup_id}\')',10);";
        else
            $this->content['jscript'][] = "setTimeout('DateInput(\'credit_date\',\'true\',\'YYYY-MM-DD\',\'" . date("Y-m-d") . "\',1,\'credit_date_holder{$this->popup_id}\')', 45);";

	    $this->content['popup_controls']["cmdTable"] = $tbl;

        return;
    }

    function doit_add_line() {

        $credit_hash = $_POST['credit_hash'];
        $error_node = $_POST['error_node'];
        $credit_amount = preg_replace('/[^-.0-9]/', "", $_POST['credit_amount']);
        $popup_id = $_POST['popup_id'];
        $last_index = (int)$_POST['last_index'];

        $total = 0;
        for ($i = 0; $i < count($error_node); $i++) {

            $amount = preg_replace('/[^-.0-9]/', "", $_POST["amount_{$error_node[$i]}"]);
            $total = bcadd($total, $amount, 2);

            $last_index += 3;
        }

        $accounting = new accounting($this->current_hash);
        $accounting->fetch_correction_codes();
        foreach ($accounting->correction_codes as $code => $code_array) {
            $code_in[] = $code_array['correction_hash'];
            $code_out[] = $code." - ".(strlen($code_array['descr']) > 30 ? substr($code_array['descr'],0,27)."..." : $code_array['descr']);
        }

        for ($i = 0; $i < 3; $i++) {

            $rand_err = rand(500, 50000);
            $credit_dist_table .= "
            <tr>
                <td>" .
                    $this->form->select(
                        "correction_code{$rand_err}",
                        $code_out,
                        $expenses[$i]['correction_code'],
                        $code_in,
                        "style=width:225px;",
                        "onChange=if(this.options[this.selectedIndex].value=='*'){agent.call('system_config','sf_loadcontent','show_popup_window','correction_code_list','popup_id=correction_code_win','from_credit=1','credit_popup_id={$this->popup_id}');this.value='" . ( $this->current_expense_line['correction_code'] ? $this->current_expense_line['correction_code'] : NULL ) . "';}else if( this.options[ this.selectedIndex ].value == '' ){ \$('amount_{$rand_err}').value='';\$('memo_{$rand_err}').value='';} tally();",
                        "tabindex=" . $last_index++
                    ) .
                    $this->form->hidden( array(
                        'error_node[]'    =>  $rand_err
                    ) ) . "
                </td>
                <td>" .
                    $this->form->text_box(
                        "name=amount_{$rand_err}",
                        "value=" . ( $i == 0 && bccomp($total, 0, 2) && bccomp( bcsub($credit_amount, $total, 2), 0, 2) ? number_format( bcsub($credit_amount, $total, 2), 2) : NULL ),
                        "size=14",
                        "style=text-align:right",
                        "onFocus=if( this.value ){ select(); } else { calc_amounts('$rand_err') }",
                        "onBlur=if(this.value){this.value=formatCurrency(this.value);}",
                        "onChange=tally()",
                        "cid=dist_amt",
                        "tabindex=" . $last_index++
                    ) . "
                </td>
                <td>" .
                    $this->form->text_box(
                        "name=memo_{$rand_err}",
                        "value=",
                        "maxlength=255",
                        "size=30",
                        "tabindex=" . $last_index++
                    ) . "
                </td>
            </tr>";

            if ( ! $code_out )
                break;
        }

        $this->ajax_vars['credit_hash'] = $credit_hash;

        $tempfile = SITE_ROOT . "core/tmp/temp_{$rand}.tmp";
        $fh = fopen($tempfile, 'w+');
        fwrite($fh, $credit_dist_table);
        fclose($fh);

        $temp_str = file($tempfile);
        $str = '';

        for ( $i = 0; $i < count($temp_str); $i++ )
            $str .= trim( str_replace("'", "\'", $temp_str[$i]) );

        $this->content['jscript'][] = "\$('credit_dist_holder{$popup_id}').insert({bottom: '{$str}'});";
        $this->content['action'] = 'continue';
        return;
    }

    function doit_customer_credit() {

        $p = $_POST['p'];
        $order = $_POST['order'];
        $order_dir = $_POST['order_dir'];

    	if ( $arg = func_get_arg(0) ) {

    		$credit_hash = $arg['credit_hash'];
    		$method = $arg['method'];
    		$submit_btn = $arg['submit_btn'];

    		$inherit = true;

    	} else {

    		$credit_hash = $_POST['credit_hash'];
    		$method = $_POST['method'];
    		$submit_btn = 'credit_btn';
    		$jscript_action = $_POST['jscript_action'];
    	}

        if ( $credit_hash ) {

            if ( ! $this->fetch_credit_record($credit_hash) )
                return $this->__trigger_error("A system error was encountered when trying to lookup customer credit for update. Please re-load the window you are working in and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
        }

        if ( $method == 'rm' && $this->credit_hash ) {

        	define('DEBUG', 1);
        	$invoice_popup = $_POST['invoice_popup'];
        	$submit_btn = $_POST['submit_btn'];
        	$caller = $_POST['caller'];

        	
            if ( $this->current_credit['deleted'] )
                return $this->__trigger_error("Credit has already been flaged as deleted. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            $accounting = new accounting($this->current_hash);

            if ( $accounting->period_ck( $this->current_credit['credit_date'] ) ) # Period check
                return $this->__trigger_error($accounting->closed_err, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

            $audit_id = $accounting->audit_id();

            if ( ! $inherit ) { # Trac #1119 Avoid transaction when called from other method

	            if ( ! $this->db->start_transaction() )
    	            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            }

            $accounting->start_transaction( array(
                'proposal_hash'     =>  $this->proposal_hash,
                'ar_credit_hash'    =>  $this->credit_hash,
                'ar_invoice_hash'   =>  $this->invoice_hash,
                'customer_hash'     =>  $this->current_credit['customer_hash']
            ) );

            if ( ! $accounting->exec_trans($audit_id, $this->current_credit['credit_date'], DEFAULT_AR_ACCT, $this->current_credit['amount'], 'AD', "Customer Credit Delete: {$this->current_credit['credit_no']}") )
                return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}",E_USER_ERROR,__FILE__,__LINE__,1);

            $total = $total_tax = 0;
            if ( $this->invoice_hash ) {

            	$lines = new line_items($this->proposal_hash, $this->current_credit['punch']);
            	$master_tax = array();

            	for ( $i = 0; $i < count($this->current_credit['credit_item']); $i++ ) {

            		if ( ! $this->fetch_credit_item($this->credit_hash, $this->current_credit['credit_item'][$i]) )
                        return $this->__trigger_error("System error encountered when attempting to lookup line item for credit reversal. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                    unset($tax_array);
                    $tax_array = $lines->fetch_item_tax($this->credit_item_hash, 1);

                    $item_tax = $_item_tax = 0;
                    if ( $tax_array ) {

	                    foreach ( $tax_array as $tax_hash => $tax_item ) {

	                    	if ( ! $tax_item['tax'] )
    	                    	continue;
							if($this->current_credit_item['tax'] != 0){
								if ( bccomp($this->current_credit_item['amount'], $this->current_credit_item['total_sell'], 2) ) # If we're crediting a percentage of the total item
		                        $tax_item['tax'] = bcmul($this->current_credit_item['amount'], $tax_item['rate'], 4);

		                   		$master_tax[ $tax_hash ] = bcadd($master_tax[ $tax_hash ], $tax_item['tax'], 4);
							}
	                    }
                    }

                    if ( ! $accounting->fetch_account_record($this->current_credit_item['account_hash']) )
                        return $this->__trigger_error("A system error was encountered during chart of sales tax chart of account lookup. Please confirm that correction codes have been properly configured.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                    $accounting->setTransIndex( array(
                        'item_hash' =>  $this->credit_item_hash
                    ) );
                    if ( $accounting->current_account['account_action'] == 'CR' )
                        $this->current_credit_item['amount'] = bcmul($this->current_credit_item['amount'], -1, 2);

                    if ( ! $accounting->exec_trans($audit_id, $this->current_credit['credit_date'], $accounting->account_hash, bcmul($this->current_credit_item['amount'], -1, 2), 'AD', $this->current_credit_item['memo']) )
                        return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            	}

            	if ( $master_tax ) {

            		$sys = new system_config;

            		foreach ( $master_tax as $tax_hash => $tax_amount ) {

            			if ( ! $sys->fetch_tax_rule($tax_hash) )
                			return $this->__trigger_error("System error encountered when attempting to open sales tax rule for credit update. Unable to fetch tax rule from database. Please check with your system administrator and confirm that tax tables have been configured correctly.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

		                if ( ! $accounting->fetch_account_record($sys->current_tax['account_hash']) )
		                    return $this->__trigger_error("System error encountered when attempting to open sales tax chart of account. Please check with your system administrator and confirm that tax tables have been configured correctly.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

		                if ( $accounting->current_account['account_action'] == 'CR' )
		                    $tax_amount = bcmul($tax_amount, -1, 4);

		                if ( ! $accounting->exec_trans($audit_id, $this->current_credit['credit_date'], $accounting->account_hash, bcmul($tax_amount, -1, 4), 'AD', $sys->current_tax['state_name'] . ( $sys->current_tax['local'] ? ", " . ( $lines->tax_country == 'CA' ? strtoupper($sys->current_tax['local']) : $sys->current_tax['local'] ) : NULL ) . " (" . rtrim( trim( bcmul($sys->current_tax['rate'], 100, 5), '0'), '.') . "%) Tax") )
		                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            		}
            	}

            } else {

	            $credit_total = 0;
	            for ( $i = 0; $i < count($this->current_credit['expense_items']); $i++ ) {

	            	if ( ! $this->fetch_expense_line($this->current_credit['expense_items'][$i], $this->credit_hash) )
	                	return $this->__trigger_error("System error encountered during expense line lookup. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                if ( ! $accounting->fetch_account_record($this->current_expense_line['account_hash']) )
	                    return $this->__trigger_error("The chart of account associated with correction code {$this->current_expense_line['code']} was not found. Please review customer credit and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	                if ( $accounting->current_account['account_action'] == 'CR' )
	                    $this->current_expense_line['amount'] = bcmul($this->current_expense_line['amount'], -1, 2);

	                if ( ! $accounting->exec_trans($audit_id, $this->current_credit['credit_date'], $accounting->account_hash, bcmul($this->current_expense_line['amount'], -1, 2), 'AD', $this->current_expense_line['memo']) )
	                    return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	            }
            }

            if ( ! $this->db->query("UPDATE customer_credit
		                             SET
    		                             timestamp = UNIX_TIMESTAMP(),
    		                             last_change = '{$this->current_hash}',
    		                             deleted = 1,
    		                             deleted_date = CURDATE()
		                             WHERE credit_hash = '{$this->credit_hash}'")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            }
            
            if ( $this->invoice_hash ) {

	            $customer_invoice = new customer_invoice( array( # Reverse receipt and update receivable balance
	                'user_hash'     =>  $this->current_hash,
	                'proposal_hash' =>  $this->proposal_hash,
	                'invoice_hash'  =>  $this->invoice_hash
	            ) );

	            if ( ! $customer_invoice->fetch_invoice_record($this->current_credit['invoice_hash']) )
	                return $this->__trigger_error("System error encountered when attempting to fetch invoice record for edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	            $applied = $this->fetch_credit_applied_for_delete($this->credit_hash);
	            
	            for ( $i = 0; $i < count($applied); $i++ ) {

	            	if ( $applied[$i]['credit_applied'] == $this->credit_hash ) {

	                    $payment_hash = $applied[$i]['payment_hash'];
	                    break;
	            	}
	            }

	            if ( ! $customer_invoice->fetch_payment_record($payment_hash) )
	                return $this->__trigger_error("System error encountered when attempting to identify payment record from current credit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	            
	            $balance = bcadd($customer_invoice->current_invoice['balance'], $customer_invoice->current_payment['receipt_amount'], 2);
	            if ( $customer_invoice->current_payment['currency'] && $customer_invoice->current_payment['exchange_rate'] && bccomp($customer_invoice->current_payment['currency_adjustment'], 0, 2) )
	                $balance = bcadd($balance, $customer_invoice->current_payment['currency_adjustment'], 2);

	            if ( ! $this->db->query("UPDATE customer_payment t1
	                                     LEFT JOIN customer_invoice t2 ON t2.invoice_hash = t1.invoice_hash
	                                     SET
	                                         t1.timestamp = UNIX_TIMESTAMP(),
	                                         t1.last_change = '{$this->current_hash}',
	                                         t1.deleted = 1,
	                                         t1.deleted_date = CURDATE(),
	                                         t2.paid_in_full = 0,
	                                         t2.balance = '$balance'
	                                     WHERE t1.invoice_hash = '{$customer_invoice->invoice_hash}' AND t1.payment_hash = '{$customer_invoice->payment_hash}'")
	            ) {

	            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	            }

	            if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_payment['receipt_date'], DEFAULT_AR_ACCT, $this->current_payment['receipt_amount'], 'AD', "Credit Applied Delete ({$customer_invoice->current_payment['receipt_date']})") )
	                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	            if ( ! $accounting->exec_trans($audit_id, $customer_invoice->current_payment['receipt_date'], DEFAULT_AR_ACCT, bcmul($this->current_payment['receipt_amount'], -1, 2), 'AD', "Credit Applied Delete ({$customer_invoice->current_payment['receipt_date']})") )
	                return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            }

            if ( ! $accounting->end_transaction() ) {

				if ( $inherit )
                    return false;

				return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            }

            if ( $inherit )
            	return true;

            $this->db->end_transaction();

            $this->content['action'] = 'close';
            $this->content['page_feedback'] = 'Customer credit has been removed';

            if ( $caller == 'customer_invoice' || $caller == 'proposals' ) {

            	$this->content['jscript'] = array(
                	"window.setTimeout('agent.call(\'customer_invoice\', \'sf_loadcontent\', \'cf_loadcontent\', \'proposal_invoices\', \'proposal_hash={$this->proposal_hash}\', \'otf=1\');', 750)",
                	"window.setTimeout('agent.call(\'proposals\', \'sf_loadcontent\', \'cf_loadcontent\', \'ledger\', \'proposal_hash={$this->proposal_hash}\', \'otf=1\');', 1000)"
            	);

            	if ( $caller == 'customer_invoice' && $invoice_popup ) {

                	array_push(
                    	$this->content['jscript'],
                    	"window.setTimeout('agent.call(\'customer_invoice\', \'sf_loadcontent\', \'show_popup_window\', \'edit_invoice\', \'invoice_hash={$this->invoice_hash}\', \'popup_id=$invoice_popup\');', 250)"
                    );
            	}


            	$this->content['jscript_action'] = "agent.call('customer_credits', 'sf_loadcontent', 'cf_loadcontent', 'proposal_credits', 'proposal_hash={$this->proposal_hash}', 'otf=1')";
            	return;
            }

            $this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall','p=$p','order=$order','order_dir=$order_dir')" );
            return;
        }

        if ( $method == 'invoice' ) { # Issue credit from receivable

        	$invoice_hash = $_POST['invoice_hash'];
        	$invoice_popup = $_POST['invoice_popup'];
        	$item = $_POST['item'];
        	$code = $_POST['code'];
        	$credit_no = $_POST['credit_no'];
        	$credit_date = $_POST['credit_date'];
        	$comment = $_POST['comment'];
        	$submit_btn = 'invoice_credit_btn';
            $apply_invoice = $_POST['apply_invoice'];
            $item_tax_actual = $_POST['item_tax_actual'];
            $item_sell = $_POST['item_sell'];
            $tax = $_POST['tax'];

            $accounting = new accounting($this->current_hash);

            if ( $accounting->period_ck( $credit_date ) ) # Period check
                return $this->__trigger_error($accounting->closed_err, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

            if ( ! $this->db->start_transaction() )
                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            $audit_id = $accounting->audit_id();

            if ( ! $this->credit_hash )
                $credit_hash = rand_hash('customer_credit', 'credit_hash');

	        $customer_invoice = new customer_invoice($this->current_hash);
	        if ( ! $customer_invoice->fetch_invoice_record($invoice_hash) )
	            return $this->__trigger_error("System error during invoice lookup. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

	        $lines = new line_items($customer_invoice->proposal_hash, $customer_invoice->current_invoice['punch']);

            $accounting->start_transaction( array(
                'customer_hash'     =>  $customer_invoice->current_invoice['customer_hash'],
                'ar_credit_hash'    =>  $credit_hash,
                'proposal_hash'     =>  $customer_invoice->proposal_hash,
                'ar_invoice_hash'   =>  $customer_invoice->invoice_hash
            ) );

            if ( ! $this->credit_hash ) {

	            $r = $this->db->query("SELECT COUNT(*) AS Total
	                                   FROM customer_credit
	                                   WHERE credit_no = '$credit_no' AND deleted = 0");
	            if ( $this->db->result($r, 0, 'Total') )
	                return $this->__trigger_error("A customer credit already exists under the reference number you entered. Please enter a unique reference number.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

	            if ( strtotime($credit_date) < strtotime($customer_invoice->current_invoice['invoice_date']) ) {

	            	$this->set_error("err_dt1{$this->popup_id}");
	                return $this->__trigger_error("Credit must be dated on or after invoice date ({$customer_invoice->current_invoice['invoice_date']})", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
	            }
            }

            if ( ! $item )
                return $this->__trigger_error("You must select at least 1 line item to be credited.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

        	$total = $total_tax = 0;
        	$account_exec = $master_tax = array();

        	for ( $i = 0; $i < count($item); $i++ ) {

        		unset($this->credit_item_hash, $this->current_credit_item, $lines->item_hash, $lines->current_line);

        		if ( $this->credit_hash && in_array($item[$i], $this->current_credit['credit_item']) ) {

                    if ( ! $this->fetch_credit_item($this->credit_hash, $item[$i]) )
                        return $this->__trigger_error("System error encountered when attempting to lookup credit item. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                    $item_hash = $this->credit_item_hash;

        		} else {

	        		if ( ! $lines->fetch_line_item_record($item[$i]) )
	            		return $this->__trigger_error("System error encountered when attempting to fetch line item for item credit. Unable to lookup item for database edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

	            	$item_hash = $lines->item_hash;
        		}

                $item_code = $code[ $item_hash ];
                $credit_amount = preg_replace('/[^.0-9]/', "", $_POST["item_credit_{$item_hash}"]);

                if ( ! $credit_amount )
                    continue;

        		$tax_array = $lines->fetch_item_tax($item_hash, 1);

                $item_tax = $_item_tax = 0;
                foreach( $tax_array as $tax_hash => $tax_item ) {

                    if ( bccomp($credit_amount, $item_sell[ $item_hash ], 2) ) # If we're crediting a percentage of the total item
                        $tax_item['tax'] = bcmul($credit_amount, $tax_item['rate'], 4);

                    if ( ! $this->credit_hash || ( $this->credit_hash && ! in_array($item_hash, $this->current_credit['credit_item']) ) ) {

                    	$master_tax[ $tax_hash ] = bcadd($master_tax[ $tax_hash ], $tax_item['tax'], 4);
                    } else {

                    	$master_tax[ $tax_hash ] = bcadd($master_tax[ $tax_hash ], bcmul( bcsub($credit_amount, $this->current_credit_item['amount'], 2), $tax_item['rate'], 4), 4);
                    }

                    $_item_tax = bcadd($_item_tax, $tax_item['tax'], 4);
                }

                $total = bcadd($total, $credit_amount, 4);
                if ( bccomp($_item_tax, 0, 4) == 1 ) {

                    $total = bcadd($total, $_item_tax, 4);
                    $total_tax = bcadd($total_tax, $_item_tax, 4);
                    $item_tax = $_item_tax;
                }

                if ( $item_code == '--AUTO--' ) {

                    $correction_hash = '--AUTO--';
                    if ( $this->credit_hash )
                        $correction_acct = $this->current_credit_item['income_account_hash'];
                    else
                        $correction_acct = $lines->current_line['income_account_hash'];

                } else {

	                if ( ! $accounting->fetch_correction_code($item_code) ) {

	                    $this->set_error("err1_{$item_hash}");
	                    return $this->__trigger_error("System error encountered during correction code lookup. Please confirm that correction codes have been properly configured.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	                }

	                if ( ! $accounting->current_code['valid_account'] ) {

	                    $this->set_error("err1_{$item_hash}");
	                    return $this->__trigger_error("Correction code indicated below is not properly configured. Please check configuration for valid chart of account.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	                }

	                $correction_hash = $accounting->correction_hash;
	                $correction_acct = $accounting->current_code['account_hash'];
                }

                if ( $this->credit_hash && $this->credit_item_hash ) {

                	if ( $item_code != $this->current_credit_item['correction_code'] || bccomp($credit_amount, $this->current_credit_item['amount'], 2) ) {

	                    if ( $item_code != $this->current_credit_item['correction_code'] ) {

	                        array_push( $account_exec, array(
	                            'account_hash'    =>    $this->current_credit_item['account_hash'],
	                            'amount'          =>    bcmul($this->current_credit_item['amount'], -1, 2),
	                            'item_hash'       =>    $this->credit_item_hash,
	                            'memo'            =>    ( strlen($this->current_credit_item['item_descr']) > 64 ? substr($this->current_credit_item['item_descr'], 0, 64) : $this->current_credit_item['item_descr'] )
	                        ) );
	                        array_push( $account_exec, array(
	                            'account_hash'    =>  $correction_acct,
	                            'amount'          =>  $credit_amount,
	                            'item_hash'       =>  $this->credit_item_hash,
                                'memo'            =>  ( strlen($this->current_credit_item['item_descr']) > 64 ? substr($this->current_credit_item['item_descr'], 0, 64) : $this->current_credit_item['item_descr'] )
	                        ) );

	                    } elseif ( bccomp($credit_amount, $this->current_credit_item['amount'], 2) ) {

	                        array_push( $account_exec, array(
	                            'account_hash'    =>  $this->current_credit_item['account_hash'],
	                            'amount'          =>  bcsub($credit_amount, $this->current_credit_item['amount'], 2),
	                            'item_hash'       =>  $this->credit_item_hash,
	                            'memo'            =>  ( strlen($this->current_credit_item['item_descr']) > 64 ? substr($this->current_credit_item['item_descr'], 0, 64) : $this->current_credit_item['item_descr'] )
	                        ) );
	                    }

	                    if ( ! $this->db->query("UPDATE customer_credit_expenses t1
	                                             SET " .
		                                             ( $item_code != $this->current_credit_item['correction_code'] ?
	    	                                             "t1.correction_code = '$correction_hash',
	    	                                              t1.account_hash = '$correction_acct'" .
	    	                                              ( bccomp($credit_amount, $this->current_credit_item['amount'], 2) ?
    	    	                                              ", " : NULL
	    	                                              ) : NULL
	    	                                         ) .
	    	                                         ( bccomp($credit_amount, $this->current_credit_item['amount'], 2) ?
	    	                                             "t1.amount = '$credit_amount',
	    	                                              t1.tax = '$item_tax' " : NULL
	    	                                         ) . "
	                                             WHERE t1.credit_hash = '{$this->credit_hash}' AND t1.expense_hash = '{$this->credit_expense_hash}'")
	                    ) {

	                        return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	                    }
                	}

                } else {

	                $expense_hash = rand_hash('customer_credit_expenses', 'expense_hash'); # Trac #1119 - Added item hash and tax column for item mapping
	                if ( ! $this->db->query("INSERT INTO customer_credit_expenses
	                                         VALUES
	                                         (
	                                             NULL,
	                                             '$expense_hash',
	                                             '$credit_hash',
	                                             '$correction_hash',
	                                             '$correction_acct',
	                                             '{$item_hash}',
	                                             '$credit_amount',
	                                             '$item_tax',
	                                             '" . addslashes($lines->current_line['item_descr']) . "'
	                                         )")
	                ) {

	                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	                }

	                array_push( $account_exec, array(
	                    'account_hash'    =>  $correction_acct,
	                    'amount'          =>  $credit_amount,
	                    'item_hash'       =>  $item_hash,
	                    'memo'            =>  ( strlen($lines->current_line['item_no']) > 64 ? substr($lines->current_line['item_no'], 0, 64) : $lines->current_line['item_no'] )
	                ) );
                }
        	}

        	if ( $this->credit_hash ) { # If editing credit, check for dropped items

        		unset($apply_invoice);

	        	if ( ! $item )
	            	$item = array();

	        	$removed_items = array_diff($this->current_credit['credit_item'], $item);

	        	foreach ( $removed_items as $item_hash ) {

                    if ( ! $this->fetch_credit_item($this->credit_hash, $item_hash) )
                        return $this->__trigger_error("System error encountered when attempting to lookup credit item. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                    if ( ! $this->db->query("DELETE FROM customer_credit_expenses
                                             WHERE credit_hash = '{$this->credit_hash}' AND expense_hash = '{$this->credit_expense_hash}'")
                    ) {

                    	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

                    array_push( $account_exec, array(
	                    'account_hash'  =>  $this->current_credit_item['account_hash'],
	                    'amount'        =>  bcmul($this->current_credit_item['amount'], -1, 2),
	                    'item_hash'     =>  $this->credit_item_hash,
                        'memo'          =>  "Item Removed . " . ( strlen($this->current_credit_item['item_descr']) > 64 ? substr($this->current_credit_item['item_descr'], 0, 64) : $this->current_credit_item['item_descr'] )
                    ) );

	                $tax_array = $lines->fetch_item_tax($this->credit_item_hash, 1);

	                $item_tax = $_item_tax = 0;
	                foreach( $tax_array as $tax_hash => $tax_item ) {

	                    if ( bccomp($this->current_credit_item['amount'], $this->current_credit_item['total_sell'], 2) ) # Adjust tax to percentage of total sell
	                        $tax_item['tax'] = bcmul($this->current_credit_item['amount'], $tax_item['rate'], 4);

                        $master_tax[ $tax_hash ] = bcadd($master_tax[ $tax_hash ], bcmul($tax_item['tax'], -1, 4), 4);
	                }
	        	}
        	}

            if ( $error )
                return $this->__trigger_error("Please check that a credit amount has been entered for the items indicated below.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

            if ( ! $total || bccomp($total, 0, 2) <= 0 )
                return $this->__trigger_error("Total credit must be greater than 0.00.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

            if($tax > 0){
            	$total = $total - $total_tax;
            	$total_tax = $tax;
            	$total = $total + $total_tax;
            }
            
            if ( $this->credit_hash ) {

            	$adj = bcsub($total, $this->current_credit['amount'], 4);

                if ( ! $this->db->query("UPDATE customer_credit t1
                                         SET
	                                         t1.timestamp = UNIX_TIMESTAMP(),
	                                         t1.last_change = '{$this->current_hash}',
	                                         t1.amount = '$total',
	                                         t1.balance = 0,
	                                         t1.tax = '$total_tax',
	                                         t1.notes = '" . addslashes($comment) . "'
                                         WHERE t1.credit_hash = '{$this->credit_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                $credit_date = $this->current_credit['credit_date'];
                $trans_type = 'AD';

                if ( ! $accounting->exec_trans($audit_id, $credit_date, DEFAULT_AR_ACCT, bcmul($adj, -1, 4), $trans_type, "Customer Credit Adjustments: $credit_no") )
                    return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            } else {

	            if ( ! $this->db->query("INSERT INTO customer_credit
	                                     VALUES
	                                     (
	                                         NULL,
	                                         UNIX_TIMESTAMP(),
	                                         '{$this->current_hash}',
	                                         '$credit_hash',
	                                         '{$customer_invoice->current_invoice['customer_hash']}',
	                                         '{$customer_invoice->proposal_hash}',
	                                         '{$customer_invoice->invoice_hash}',
	                                         0,
	                                         NULL,
	                                         '$credit_no',
	                                         '$credit_date',
	                                         '$total',
	                                         '$total_tax',
	                                         '$total',
	                                         '" . addslashes($comment) . "'
	                                     )")
	            ) {

	            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	            }

	            $trans_type = 'CC';

	            if ( ! $accounting->exec_trans($audit_id, $credit_date, DEFAULT_AR_ACCT, bcmul($total, -1, 4), $trans_type, "Customer Credit Issued: $credit_no") )
	                return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            }

            $sys = new system_config;

            foreach ( $master_tax as $tax_hash => $tax_amount ) {

            	if ( ! $sys->fetch_tax_rule($tax_hash) )
                	return $this->__trigger_error("System error encountered when attempting to open sales tax rule for credit update. Unable to fetch tax rule from database. Please check with your system administrator and confirm that tax tables have been configured correctly.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! $accounting->fetch_account_record($sys->current_tax['account_hash']) )
                    return $this->__trigger_error("System error encountered when attempting to open sales tax chart of account. Please check with your system administrator and confirm that tax tables have been configured correctly.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( $accounting->current_account['account_action'] == 'CR' )
                    $tax_amount = bcmul($tax_amount, -1, 4);

                if ( ! $accounting->exec_trans($audit_id, $credit_date, $accounting->account_hash, $tax_amount, $trans_type, $sys->current_tax['state_name'] . ( $sys->current_tax['local'] ? ", " . ( $lines->tax_country == 'CA' ? strtoupper($sys->current_tax['local']) : $sys->current_tax['local'] ) : NULL ) . " (" . rtrim( trim( bcmul($sys->current_tax['rate'], 100, 5), '0'), '.') . "%) Tax") )
                    return $this->__trigger_error("{$accounting->err['errno']} - {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            }

            for ( $i = 0; $i < count($account_exec); $i++ ) {

            	if ( ! bccomp($account_exec[$i]['amount'], 0, 4) )
                    continue;

            	if ( ! $accounting->fetch_account_record($account_exec[$i]['account_hash']) )
                    return $this->__trigger_error("A system error was encountered during chart of account lookup. Please confirm that correction codes have been properly configured.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            	$accounting->setTransIndex( array(
                	'item_hash'    =>  $account_exec[$i]['item_hash']
            	) );
                if ( $accounting->current_account['account_action'] == 'CR' )
                    $account_exec[$i]['amount'] = bcmul($account_exec[$i]['amount'], -1, 4);

                if ( ! $accounting->exec_trans($audit_id, $credit_date, $accounting->account_hash, $account_exec[$i]['amount'], $trans_type, $account_exec[$i]['memo']) )
                    return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            }

            $payment_amount = $total;

            if ( $this->credit_hash && bccomp($total, $this->current_credit['amount'], 2) ) {

            	$applied = $this->fetch_credit_applied($this->credit_hash); # Update the applied credit and receivable balance

            	if ( ! $applied || count($applied) > 1 )
                	return $this->__trigger_error("System error encountered when attempting to update balance on applied credit. Could not lookup applied credit for receivable update. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! $customer_invoice->fetch_invoice_record($applied[0]['invoice_hash']) )
                    return $this->__trigger_error("System error encountered when attempting to lookup invoice associated with applied credit cash receipt. Could not lookup invoice receipt. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! $customer_invoice->fetch_payment_record($applied[0]['payment_hash']) )
                    return $this->__trigger_error("System error encountered when attempting to lookup invoice receipt associated with applied credit. Could not lookup invoice receipt. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                $applied_adj = bcsub($payment_amount, $customer_invoice->current_payment['receipt_amount'], 4);
                $inv_balance = bcsub($customer_invoice->current_invoice['balance'], $applied_adj, 4);

                if ( bccomp($inv_balance, 0, 2) == -1 )
                    return $this->__trigger_error("Changes applied to this credit will result in a negative balance on the invoice associated with the applied credit. Please adjust the total credit issued to avoid a negative invoice balance.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! $this->db->query("UPDATE customer_payment t1
                                         LEFT JOIN customer_invoice t2 ON t2.invoice_hash = t1.invoice_hash
                                         SET
	                                         t1.timestamp = UNIX_TIMESTAMP(),
	                                         t1.last_change = '{$this->current_hash}',
	                                         t1.receipt_amount = '$payment_amount',
	                                         t2.balance = '$inv_balance'
                                         WHERE t1.payment_hash = '{$customer_invoice->payment_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( ! $this->db->query("UPDATE customer_credit_applied t1
                                         SET t1.amount = '$payment_amount'
                                         WHERE t1.payment_hash = '{$customer_invoice->payment_hash}' AND credit_hash = '{$this->credit_hash}'")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                $accounting->setTransIndex( array(
                    'ar_payment_hash'   =>  $customer_invoice->payment_hash
                ) );

                if ( ! $accounting->exec_trans($audit_id, $credit_date, DEFAULT_AR_ACCT, $applied_adj, 'AD', "Adjustment to Credit Applied: $credit_no") )
                    return $this->__trigger_error("An error was encountered while attempting to apply the new credit to your invoice. Your credit could not be created. Please reload window and try again.", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                if ( ! $accounting->exec_trans($audit_id, $credit_date, DEFAULT_AR_ACCT, bcmul($applied_adj, -1, 4), 'AD', "Adjustment to Credit Applied: $credit_no") )
                    return $this->__trigger_error("An error was encountered while attempting to apply the new credit to your invoice. Your credit could not be created. Please reload window and try again.", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                $apply_invoice = 1;

            } elseif ( ! $this->credit_hash && $apply_invoice ) {

                $payment_hash = rand_hash('customer_payment', 'payment_hash');
                $accounting->setTransIndex( array(
                    'ar_payment_hash'   =>  $payment_hash
                ) );

                $invoice_balance = bcsub($customer_invoice->current_invoice['balance'], $payment_amount, 4);

                if ( bccomp($invoice_balance, 0, 2) == -1 ) {

                    $invoice_balance = 0;
                    $payment_amount = $customer_invoice->current_invoice['balance'];
                }

                $remaining_amt = bcsub($total, $payment_amount, 4);

                if ( ! $this->db->query("INSERT INTO customer_payment
                                         (
                                             timestamp,
                                             last_change,
                                             payment_hash,
                                             invoice_hash,
                                             receipt_amount,
                                             applied_from,
                                             applied_type,
                                             receipt_date,
                                             check_no,
                                             comments
                                         )
                                         VALUES
                                         (
                                             UNIX_TIMESTAMP(),
                                             '{$this->current_hash}',
                                             '$payment_hash',
                                             '{$customer_invoice->invoice_hash}',
                                             '$payment_amount', " .
                                             ( $credit_hash ?
                                                 "'$credit_hash'" : "NULL"
                                             ) . ", " .
                                             ( $credit_hash ?
                                                 "'C'" : "NULL"
                                             ) . ",
                                             '$credit_date',
                                             '$credit_no',
                                             '" . addslashes($comment) . "'
                                         )")
                ) {

                	return $this->__trigger_error("An error was encountered while attempting to apply the new credit to your invoice. Your credit could not be created. Please reload window and try again.", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( ! $this->db->query("UPDATE customer_invoice t1
                                         SET
                                             t1.timestamp = UNIX_TIMESTAMP(),
                                             t1.last_change = '{$this->current_hash}',
                                             t1.balance = '$invoice_balance'
                                         WHERE t1.invoice_hash = '{$customer_invoice->invoice_hash}'")
                ) {

                	return $this->__trigger_error("An error was encountered while attempting to apply the new credit to your invoice. Your credit could not be created. Please reload window and try again.", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( ! $this->db->query("UPDATE customer_credit t1
                                         SET
                                             t1.timestamp = UNIX_TIMESTAMP(),
                                             t1.last_change = '{$this->current_hash}',
                                             t1.balance = '$remaining_amt'
                                         WHERE t1.credit_hash = '{$credit_hash}'")
                ) {

                	return $this->__trigger_error("An error was encountered while attempting to apply the new credit to your invoice. Your credit could not be created. Please reload window and try again.", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( ! $this->db->query("INSERT INTO customer_credit_applied
                                         VALUES
                                         (
                                             NULL,
                                             '$payment_hash',
                                             '$credit_hash',
                                             '$payment_amount',
                                             0,
                                             0
                                         )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                if ( ! $accounting->exec_trans($audit_id, $credit_date, DEFAULT_AR_ACCT, $payment_amount, 'CC', "Credit applied: $credit_no") )
                    return $this->__trigger_error("An error was encountered while attempting to apply the new credit to your invoice. Your credit could not be created. Please reload window and try again.", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                if ( ! $accounting->exec_trans($audit_id, $credit_date, DEFAULT_AR_ACCT, bcmul($payment_amount, -1, 4), 'CC', "Credit applied: $credit_no") )
                    return $this->__trigger_error("An error was encountered while attempting to apply the new credit to your invoice. Your credit could not be created. Please reload window and try again.", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                $feedback = "Your customer credit has been successfully created and applied to the current invoice";
            }

            if ( ! $accounting->end_transaction() )
                return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            $this->db->end_transaction();

        	$this->content['page_feedback'] = ( $this->credit_hash ? "Credit has been updated" : "Credit has been issued" );
        	$this->content['jscript'][] = "window.setTimeout('agent.call(\'proposals\',\'sf_loadcontent\',\'cf_loadcontent\',\'ledger\',\'proposal_hash={$this->proposal_hash}\',\'otf=1\');', 1000)";

        	if ( $apply_invoice ) {

            	$this->content['jscript'][] = "agent.call('customer_invoice','sf_loadcontent','show_popup_window','edit_invoice','invoice_hash=$invoice_hash','popup_id=$invoice_popup');";
                if ( $caller == 'customer_invoice' ) {

                    array_push(
                        $this->content['jscript'],
                        "window.setTimeout('agent.call(\'customer_invoice\', \'sf_loadcontent\', \'cf_loadcontent\', \'proposal_invoices\', \'proposal_hash={$this->proposal_hash}\');', 250)"
                    );
                }
        	}

            $this->content['jscript_action'] = "agent.call('customer_credits', 'sf_loadcontent', 'cf_loadcontent', 'proposal_credits', 'proposal_credits', 'otf=1', 'proposal_hash={$this->proposal_hash}');";
        	$this->content['action'] = 'close';

        	return;
        }

        # Issue credits from customer credits window
        $credit_customer = $_POST['credit_customer'];
        $customer_hash = $_POST['customer_hash'];
        $credit_amount = preg_replace('/[^.0-9]/', "", $_POST['credit_amount']);
        $credit_no = addslashes($_POST['credit_no']);
        $proposal_no = $_POST['proposal_no'];
        $proposal_hash = $_POST['proposal_hash'];
        $credit_date = $_POST['credit_date'];
        $comment = $_POST['notes'];
        $error_node = $_POST['error_node'];
        $from_proposal = $_POST['from_proposal'];
        $submit_btn = 'credit_btn';

        if ( $this->credit_hash )
            $credit_date = $this->current_credit['credit_date'];

        $e = false;
        if ( ! $credit_customer ) {

        	$this->set_error("err1{$this->popup_id}");
        	$e = true;
        }
        if ( ! $credit_amount ) {

        	$this->set_error("err2{$this->popup_id}");
        	$e = true;
        }
        if ( ! $credit_no ) {

            $this->set_error("err3{$this->popup_id}");
            $e = true;
        }
        if ( ! $credit_date ) {

            $this->set_error("err4{$this->popup_id}");
            $e = true;
        }

        if ( $e )
        	return $this->__trigger_error("Required fields are missing! Please check that you have completed the required fields indicated below.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

        if ( ! $customer_hash || ( $proposal_no && ! $proposal_hash ) ) {

            if ( ! $customer_hash ) $this->set_error("err1{$this->popup_id}");
            if ( $proposal_no && ! $proposal_hash ) $this->set_error("err5{$this->popup_id}");
            return $this->__trigger_error("The value you entered below is not value. Please make sure that when searching a key result field you select the matching result displayed in the drop down box.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
        }

        if ( $credit_no && ! $_POST['credit_dup_confirm'] && ( ! $this->credit_hash || ( $this->credit_hash && $this->current_credit['credit_no'] != trim($credit_no) ) ) ) {

            $r = $this->db->query("SELECT COUNT(*) AS Total
                                   FROM customer_credit t1
                                   WHERE t1.credit_no = '$credit_no' AND t1.deleted = 0");
            if ( $this->db->result($r, 0, 'Total') ) {

                return $this->__trigger_error(
                    "A customer credit numbered '$credit_no' already exists under the selected customer. Are you sure you want to issue new credit under the same reference number? <br /><br />" .
                    $this->form->button(
                        "value=Proceed Anyway",
                        "onClick=\$('$submit_btn').disabled=1;submit_form(this.form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit','credit_dup_confirm=1');"
                    ),
                    E_USER_NOTICE,
                    __FILE__,
                    __LINE__,
                    1,
                    false,
                    $submit_btn
                );
            }
        }

        if ( ! $this->db->start_transaction() )
            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

        if ( ! $this->credit_hash ) {

        	$trans_type = 'CC';
            $credit_hash = rand_hash('customer_credit', 'credit_hash');
        } else
            $trans_type = 'AD';

        $accounting = new accounting($this->current_hash);
        $accounting->start_transaction( array(
	        'customer_hash'     =>  $customer_hash,
	        'ar_credit_hash'    =>  ( $this->credit_hash ? $this->credit_hash : $credit_hash ),
	        'proposal_hash'     =>  $proposal_hash
        ) );

        if ( $accounting->period_ck($credit_date) )
            return $this->__trigger_error($accounting->closed_err, E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);

        $audit_id = $accounting->audit_id();

        $account_exec = array();
        $total = 0;
        for ( $i = 0; $i < count($error_node); $i++ ) {

        	$expense_id = $_POST["expense_id_{$error_node[$i]}"];
            $amount = preg_replace('/[^-.0-9]/', "", $_POST["amount_{$error_node[$i]}"]);
            $memo = $_POST["memo_{$error_node[$i]}"];
            $code = $_POST["correction_code{$error_node[$i]}"];
            if ( $code == '*' )
                unset($code);

            if ( ( $code && ! bccomp($amount, 0, 2) ) || ( bccomp($amount, 0, 2) && ! $code ) ) {

                $this->set_error("correction_code{$error_node[$i]}");
                return $this->__trigger_error( ( bccomp($amount, 0, 2) && ! $code ? "Please enter a valid correction code for the distribution item indicated below." : "Please enter a dollar amount for the distribution item indicated below." ), E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
            }

            if ( $code && bccomp($amount, 0, 2) ) {

                $total = bcadd($total, $amount, 2);
                $expense_dist_num++;

                if ( $this->credit_hash && $expense_id ) {

                    if ( ! $this->fetch_expense_line($expense_id, $credit_hash) )
                        return $this->__trigger_error("System error encountered when trying to lookup credit distribution line. Please reload credit window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                    if ( $code != $this->current_expense_line['correction_code'] || bccomp($amount, $this->current_expense_line['amount'], 2) || $memo != $this->current_expense_line['memo'] ) {

                    	if ( $code != $this->current_expense_line['correction_code'] ) {

		                    if ( ! $accounting->fetch_correction_code($code) ) {

		                        $this->set_error("correction_code{$error_node[$i]}");
		                        return $this->__trigger_error("The correction code you selected below cannot be retrieved from the database. Please make sure you have selected a valid correction code and that it has been configured correctly.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
		                    }

		                    if ( ! $accounting->current_code['valid_account'] ) {

		                        $this->set_error("correction_code{$error_node[$i]}");
		                        return $this->__trigger_error("The correction code you selected below has not been properly configured and is missing a valid chart of account association. Please make sure that the chart of account has not been recently deleted and that the correction code has been properly configured.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
		                    }

                            array_push( $account_exec, array(
                                'account_hash'    =>    $this->current_expense_line['account_hash'],
                                'amount'          =>    bcmul($this->current_expense_line['amount'], -1, 2),
                                'memo'            =>    $this->current_expense_line['memo']
                            ) );
                            array_push( $account_exec, array(
                                'account_hash'    =>  $accounting->current_code['account_hash'],
                                'amount'          =>  $amount,
                                'memo'            =>  $memo
                            ) );

                    	} elseif ( bccomp($amount, $this->current_expense_line['amount'], 2) ) {

                    		array_push( $account_exec, array(
	                    		'account_hash'    =>  $this->current_expense_line['account_hash'],
	                    		'amount'          =>  bcsub($amount, $this->current_expense_line['amount'], 2),
	                    		'memo'            =>  $memo
                    		) );
                    	}

                        if ( ! $this->db->query("UPDATE customer_credit_expenses t1
                                                 SET " .
                                                     ( $code != $this->current_expense_line['correction_code'] ?
                                                         "t1.correction_code = '{$accounting->correction_hash}',
                                                          t1.account_hash = '{$accounting->current_code['account_hash']}'" .
                                                          ( bccomp($amount, $this->current_expense_line['amount'], 2) || $memo != $this->current_expense_line['memo'] ?
                                                              ", " : NULL
                                                          ) : NULL
                                                     ) .
                                                     ( bccomp($amount, $this->current_expense_line['amount'], 2) ?
                                                         "t1.amount = '$credit_amount'" .
                                                         ( $memo != $this->current_expense_line['memo'] ?
                                                             ", " : NULL
                                                         ) : NULL
                                                     ) .
                                                     ( $memo != $this->current_expense_line['memo'] ?
                                                         "t1.memo = '" . addslashes($memo) . "'" : NULL
                                                     ) . "
                                                 WHERE t1.credit_hash = '{$this->credit_hash}' AND t1.expense_hash = '{$this->credit_expense_hash}'")
                        ) {

                            return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                        }

                    }

                } else {

                    $expense_hash = rand_hash('customer_credit_expenses', 'expense_hash');

                    if ( ! $accounting->fetch_correction_code($code) ) {

                        $this->set_error("correction_code{$error_node[$i]}");
                        return $this->__trigger_error("The correction code you selected below cannot be retrieved from the database. Please make sure you have selected a valid correction code and that it has been configured correctly.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

                    if ( ! $accounting->current_code['valid_account'] ) {

                        $this->set_error("correction_code{$error_node[$i]}");
                        return $this->__trigger_error("The correction code you selected below has not been properly configured and is missing a valid chart of account association. Please make sure that the chart of account has not been recently deleted and that the correction code has been properly configured.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

                    if ( ! $this->db->query("INSERT INTO customer_credit_expenses
                                             VALUES
                                             (
	                                             NULL,
	                                             '$expense_hash',
	                                             '" . ( $this->credit_hash ? $this->credit_hash : $credit_hash ) . "',
	                                             '{$accounting->correction_hash}',
	                                             '{$accounting->current_code['account_hash']}',
	                                             NULL,
	                                             '$amount',
	                                             0,
	                                             '" . addslashes($memo) . "'
                                             )")
                    ) {

                        return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                    }

                    array_push($account_exec, array(
                        'account_hash'      =>  $accounting->current_code['account_hash'],
                        'amount'            =>  $amount,
                        'memo'              =>  $memo
                    ) );
                }

            } elseif ( $expense_id ) {

                if ( ! $this->fetch_expense_line($expense_id, $this->credit_hash) )
                    return $this->__trigger_error("A system error was encountered when trying to lookup credit distribution line. Please reload credit window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

                if ( ! $this->db->query("DELETE FROM customer_credit_expenses
                                         WHERE credit_hash = '{$this->credit_hash}' AND expense_hash = '{$this->credit_expense_hash}'")
                ) {

                    return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
                }

                array_push( $account_exec, array(
                    'account_hash'      =>  $this->current_expense_line['account_hash'],
                    'amount'            =>  bcmul($this->current_expense_line['amount'], -1, 2),
                    'memo'              =>  $this->current_expense_line['memo']
                ) );
            }
        }

        if ( bccomp($total, $credit_amount, 2) ) {

            $this->set_error("err2{$this->popup_id}");
            return $this->__trigger_error("Total credit amount [" . number_format($credit_amount, 2) . "] does not match the total credit distributions [" . number_format($total, 2) . "] entered below. Please check your entries and try again.", E_USER_NOTICE, __FILE__, __LINE__, 1, false, $submit_btn);
        }

        if ( $this->credit_hash ) {

        	$feedback = "No changes have been made";
        	$sql = array();

            ( $customer_hash != $this->current_credit['customer_hash'] ? array_push($sql, "t1.customer_hash = '$customer_hash'") : NULL );
            ( $proposal_hash != $this->current_credit['proposal_hash'] ? array_push($sql, "t1.proposal_hash = '$proposal_hash'") : NULL );
            ( $credit_no != $this->current_credit['credit_no'] ? array_push($sql, "t1.credit_no = '$credit_no'") : NULL );
            ( strlen( base64_encode($comment) ) != strlen( base64_encode( stripslashes($this->current_credit['notes']) ) ) ? array_push($sql, "t1.notes = '" . addslashes($comment) . "'") : NULL );

            if ( bccomp($credit_amount, $this->current_credit['amount'], 2) ) {

                array_push($sql, "t1.amount = '$credit_amount'");

                $adj = bcsub($credit_amount, $this->current_credit['amount'], 2);
                $balance = bcadd($this->current_credit['balance'], $adj, 2);

                if ( bccomp($balance, 0, 2) == -1 )
                    return $this->__trigger_error("Your changes will result in a negative credit balance, which is not permitted. Please check your changes and try again.", E_USER_NOITICE, __FILE__, __LINE__, 1, false, $submit_btn);

                array_push($sql, "t1.balance = '$balance'");

                if ( ! $accounting->exec_trans($audit_id, $this->current_credit['credit_date'], DEFAULT_AR_ACCT, bcmul($adj, -1, 2), $trans_type, "Customer Credit Adjustment: $credit_no" ) )
                    return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            }

            if ( $sql ) {

	            if ( ! $this->db->query("UPDATE customer_credit t1
	                                     SET
		                                     t1.timestamp = UNIX_TIMESTAMP(),
		                                     t1.last_change = '{$this->current_hash}',
		                                     " . implode(", ", $sql) . "
	                                     WHERE t1.credit_hash = '{$this->credit_hash}'")
	            ) {

	                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
	            }

	            $feedback = "Credit has been updated";
            }

        } else {

        	$feedback = "Credit $credit_no has been issued";

            if ( ! $this->db->query("INSERT INTO customer_credit
                                     VALUES
                                     (
	                                     NULL,
	                                     UNIX_TIMESTAMP(),
	                                     '{$this->current_hash}',
	                                     '$credit_hash',
	                                     '$customer_hash'," .
	                                     ( $proposal_hash ? "'$proposal_hash'" : "NULL" ) . ",
	                                     NULL,
	                                     0,
	                                     NULL,
	                                     '$credit_no',
	                                     '$credit_date',
	                                     '$credit_amount',
	                                     0,
	                                     '$credit_amount',
	                                     '" . addslashes($comment) . "'
                                     )")
            ) {

                return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
            }

            if ( ! $accounting->exec_trans($audit_id, $credit_date, DEFAULT_AR_ACCT, bcmul($credit_amount, -1, 2), $trans_type, "Customer Credit Issued: $credit_no" ) )
                return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
        }

        for ( $i = 0; $i < count($account_exec); $i++ ) {

        	if ( $i == 0 )
            	$feedback = "Credit has been updated";

            if ( ! $accounting->fetch_account_record($account_exec[$i]['account_hash']) )
                return $this->__trigger_error("System error encountered when attempting to lookup chart of account for credit distribution item. Unable to fetch account from database. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

            if ( $accounting->current_account['account_action'] == 'CR' )
                $account_exec[$i]['amount'] = bcmul($account_exec[$i]['amount'], -1, 2);

            if ( ! $accounting->exec_trans($audit_id, $credit_date, $accounting->account_hash, $account_exec[$i]['amount'], $trans_type, $account_exec[$i]['memo']) )
                return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);
        }

        if ( ! $accounting->end_transaction() )
            return $this->__trigger_error("{$accounting->err['errno']} {$accounting->err['err']}", E_USER_ERROR, __FILE__, __LINE__, 1, false, $submit_btn);

        $this->db->end_transaction();

        $this->content['action'] = 'close';
        $this->content['page_feedback'] = ( $feedback ? $feedback : "Credit Updated" );
        if ( $from_proposal )
            $this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('customer_credits', 'sf_loadcontent', 'cf_loadcontent', 'proposal_credits', 'proposal_hash=$proposal_hash', 'p=$p', 'order=$order', 'order_dir=$order_dir')" );
        else
            $this->content['jscript_action'] = ( $jscript_action ? $jscript_action : "agent.call('customer_credits', 'sf_loadcontent', 'cf_loadcontent', 'showall', 'p=$p', 'order=$order', 'order_dir=$order_dir')" );

        return;
    }

    function showall() {

        $this->unlock("_");
        $this->load_class_navigation();

        $p = $this->ajax_vars['p'];
        $order = $this->ajax_vars['order'];
        $order_dir = $this->ajax_vars['order_dir'];
        $this->active_search = $this->ajax_vars['active_search'];

        $total_before = $this->total;
        $num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
        $p = ( ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p );
        $start_from = MAIN_PAGNATION_NUM * ($p - 1);

        $end = $start_from + MAIN_PAGNATION_NUM;
        if ( $end > $this->total )
            $end = $this->total;

        $order_by_ops = array(
            "timestamp"          =>  "customer_credit.timestamp",
            "obj_id"             =>  "customer_credit.obj_id",
            "customer"           =>  "customers.customer_name",
            "invoice_no"         =>  "customer_invoice.invoice_no",
            "credit_no"          =>  "customer_credit.credit_no",
            "credit_date"        =>  "customer_credit.credit_date"
        );

        $_total = $this->fetch_customer_credits(
            $start_from,
            $order_by_ops[ ( $order ? $order : "obj_id" ) ],
            $order_dir
        );

        if ( $this->active_search )
            $this->page_pref =& $this->search_vars;

        if ( $total_before != $this->total ) {

            $num_pages = ceil($this->total / MAIN_PAGNATION_NUM);
            $p = ( ( ! isset($p) || $p <= 1 || $p > $num_pages ) ? 1 : $p );
            $start_from = MAIN_PAGNATION_NUM * ($p - 1);

            $end = $start_from + MAIN_PAGNATION_NUM;
            if ( $end > $this->total )
                $end = $this->total;
        }

        $sort_menu = "
        <div id=\"sort_menu\" class=\"search_suggest\" >
            <div class=\"function_menu_item\" style=\"font-weight:bold;background-color:#365082;color:#ffffff;margin-top:0;\">
                <div style=\"float:right;padding-right:1px;\">
                    <a href=\"javascript:void(0);\" onClick=\"toggle_display('sort_menu','none');\"><img src=\"images/close.gif\" border=\"0\"></a>
                </div>
                Sort Options
            </div>
            <div class=\"function_menu_item\" style=\"padding-left:10px;\">
                <small><strong>Show:</strong></small>
                <div style=\"margin-left:10px;padding:5px 0;\">
                    <div style=\"padding-bottom:3px;\">".$this->form->radio("name=sort","value=*","id=show",(!$this->page_pref['sort'] || $this->page_pref['sort'] == '*' ? "checked" : NULL))." All Credits</div>
                    <div style=\"padding-bottom:3px;\">".$this->form->radio("name=sort","value=1","id=show",($this->page_pref['sort'] == 1 ? "checked" : NULL))." Credits with Open Balances</div>
                    <div style=\"padding-bottom:3px;\">".$this->form->radio("name=sort","value=2","id=show",($this->page_pref['sort'] == 2 ? "checked" : NULL))." Fully Applied Credits</div>
                </div>
            </div>
            <div class=\"function_menu_item\">
                <div style=\"float:right;padding-right:5px;\">".$this->form->button("value=Go","onClick=submit_form(this.form,'customer_credits','exec_post','refresh_form','action=doit_search');")."</div>
                ".$this->form->checkbox("name=page_pref","value=1",($this->page_pref['custom'] ? "checked" : NULL))."&nbsp;&nbsp;<small><i>Remember Preferences</i></small>
            </div>
        </div>";

        $tbl =
        $this->form->form_tag().
        $this->form->hidden( array(
            'p'               => $p,
            'order'           => $order,
            'order_dir'       => $order_dir,
            'active_search'   => $this->active_search
        ) ) .
        ( $this->active_search ?
	        "<h3 style=\"color:#00477f;margin:0;padding-left:10px;\">Search Results</h3>
	        <div style=\"padding-top:8px;padding-bottom:10px;padding-left:30px;font-weight:bold;\">[<a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall');\">Show All</a>]</div>" : NULL
        ) . "
        <table cellpadding=\"0\" cellspacing=\"3\" border=\"0\" width=\"100%\">
            <tr>
                <td style=\"padding-left:10px;padding-top:0\">
                     <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"6\" style=\"width:90%;\">
                        <tr>
                            <td style=\"font-weight:bold;vertical-align:bottom;\" colspan=\"6\">
                                <div style=\"float:right;font-weight:normal;padding-right:10px;text-align:right;\">" .
	                                ( $this->total ?
	                                    paginate_jscript(
	                                        $num_pages,
	                                        $p,
	                                        'sf_loadcontent',
	                                        'cf_loadcontent',
	                                        'customer_credits',
	                                        'showall',
	                                        $order,
	                                        $order_dir,
	                                        "'active_search={$this->active_search}'"
	                                    ) : NULL
	                                ) . "
                                    <div style=\"padding-top:5px;\">
                                        <a href=\"javascript:void(0);\" onMouseOver=\"position_element(\$('sort_menu'),findPos(this,'top')+15,findPos(this,'left')-100);\" onClick=\"toggle_display('sort_menu','block');\"><img src=\"images/arrow_down2.gif\" id=\"sort_arrow_down\" border=\"0\" title=\"Change sorting options\" /></a>
                                        &nbsp;
                                        <span style=\"cursor:hand;\" onMouseOver=\"position_element(\$('sort_menu'),findPos(\$('sort_arrow_down'),'top')+15,findPos(\$('sort_arrow_down'),'left')-100);\" onClick=\"toggle_display('sort_menu','block');\" title=\"Change sorting options\"><small>Sort Options</small></span>
                                        $sort_menu
                                    </div>
                                </div>" .
                                ( $this->total ?
                                    "Showing " . ( $start_from + 1 ) . " - " .
                                    ( $start_from + MAIN_PAGNATION_NUM > $this->total ?
                                        $this->total : $start_from + MAIN_PAGNATION_NUM
                                    ) . " of {$this->total} Customer Credits." : NULL
                                ) . "
                                <div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
	                                ( $this->p->ck(get_class($this), 'A') ?
	                                    "<a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','show_popup_window','edit_credit','popup_id=cc_new_win','p=$p','order=$order','order_dir=$order_dir');\"><img src=\"images/new_invoice.gif\" title=\"Create new customer credit\" border=\"0\" /></a>&nbsp;&nbsp;" : NULL
	                                ) . "
                                    <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','show_popup_window','search_credits','popup_id=cc_search_win','active_search={$this->active_search}','p=$p','order=$order','order_dir=$order_dir');\"><img src=\"images/search.gif\" title=\"Search customer credits\" border=\"0\" /></a>
                                    &nbsp;&nbsp;
                                </div>
                            </td>
                        </tr>
                        <tr class=\"thead\" style=\"font-weight:bold;\">
                            <td style=\"width:200px;vertical-align:bottom;\">
                                <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall','p=$p','order=customer','order_dir=" . ( $order == "customer" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "','active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">Customer</a>" .
                                ( $order == 'customer' ?
                                    "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                ) . "
                            </td>
                            <td style=\"width:110px;vertical-align:bottom;\">
                                <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall','p=$p','order=invoice_no','order_dir=" . ( $order == "invoice_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "','active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">Invoice No.</a>" .
                                ( $order == 'invoice_no' ?
                                    "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                ) . "
                            </td>
                            <td style=\"width:110px;vertical-align:bottom;\">
                                <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall','p=$p','order=credit_no','order_dir=" . ( $order == "credit_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "','active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">Credit No.</a>" .
                                ( $order == 'credit_no' ?
                                    "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                ) . "
                            </td>
                            <td style=\"width:110px;vertical-align:bottom;\">
                                <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall','p=$p','order=credit_date','order_dir=" . ( $order == "credit_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "','active_search={$this->active_search}');\" style=\"color:#ffffff;text-decoration:underline;\">Credit Date</a>" .
                                ( $order == 'credit_date' ?
                                    "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                                ) . "
                            </td>
                            <td style=\"vertical-align:bottom;\" class=\"num_field\">Amount</td>
                            <td style=\"vertical-align:bottom;\" class=\"num_field\">Balance</td>
                        </tr>";

                        $border = "border-bottom:1px solid #cccccc;";
                        for ( $i = 0; $i < $_total; $i++ ) {

                        	if ( $i >= $_total - 1 )
                            	unset($border);

                            if ( $this->p->ck(get_class($this), 'V') ) {

                            	if ( $this->credit_info[$i]['invoice_hash'] )
                                	$onClick = "onClick=\"agent.call('customer_credits','sf_loadcontent','show_popup_window','invoice_credits','credit_hash={$this->credit_info[$i]['credit_hash']}','proposal_hash={$this->credit_info[$i]['proposal_hash']}','invoice_hash={$this->credit_info[$i]['invoice_hash']}','popup_id=credit_win')\"";
                                else
                                    $onClick = "onClick=\"agent.call('customer_credits','sf_loadcontent','show_popup_window','edit_credit','credit_hash={$this->credit_info[$i]['credit_hash']}','popup_id=credit_detail','new=true','active_search={$this->active_search}','p=$p','order=$order','order_dir=$order_dir');\"";
                            }

                            $tbl .= "
                            <tr " . ( $this->p->ck(get_class($this), 'V') ? "onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
                                <td style=\"{$border}vertical-align:bottom;\" style=\"text-align:left;\" $onClick nowrap>" . stripslashes($this->credit_info[$i]['customer_name']) . "</td>
                                <td style=\"{$border}vertical-align:bottom;\" style=\"text-align:left;\" " . ( ! $this->credit_info[$i]['invoice_no'] ? $onClick : NULL ) . ">" .
                                ( $this->credit_info[$i]['invoice_no'] ?
                                    ( $this->p->ck('customer_invoice', 'V') ?
                                        "<a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice','sf_loadcontent','show_popup_window','edit_invoice','invoice_hash={$this->credit_info[$i]['invoice_hash']}','popup_id=invoice_win');\" title=\"View this invoice\" class=\"link_standard\">" : NULL
                                    ) . $this->credit_info[$i]['invoice_no'] .
                                    ( $this->p->ck('customer_invoice', 'V') ?
                                        "</a>" : NULL
                                    ) : "&nbsp;"
                                ) . "
                                </td>
                                <td style=\"{$border}vertical-align:bottom;\" style=\"text-align:left;\" $onClick>{$this->credit_info[$i]['credit_no']}</td>
                                <td style=\"{$border}vertical-align:bottom;\" $onClick>" . date(DATE_FORMAT, strtotime($this->credit_info[$i]['credit_date']) ) . "</td>
                                <td style=\"{$border}vertical-align:bottom;\" class=\"num_field\" $onClick>\$" . number_format($this->credit_info[$i]['amount'], 2) . "</td>
                                <td style=\"{$border}vertical-align:bottom;\" class=\"num_field\" $onClick>" .
                                ( bccomp($this->credit_info[$i]['balance'], 0, 2) == -1 ?
                                    "(\$" . number_format( bcmul($this->credit_info[$i]['balance'], -1, 2), 2) . ")" : "\$" . number_format($this->credit_info[$i]['balance'], 2)
                                ) . "
                                </td>
                            </tr>";
                        }

                        if ( ! $this->total )
                            $tbl .= "
                            <tr >
                                <td colspan=\"6\">There are no credits to display.</td>
                            </tr>";

            $tbl .= "
                    </table>
                </td>
            </tr>
        </table>" .
        $this->form->close_form();

        $this->content['jscript'] = $jscript;
        $this->content['html']['place_holder'] = $tbl;
    }

    function innerHTML_customer_credits() {

    	if ( $method = $this->ajax_vars['method'] ) {

    		$credit_hash = $this->ajax_vars['credit_hash'];
    	} else {

    		$method = $_POST['method'];
    		$credit_hash = $_POST['credit_hash'];
    	}

    	if ( $method == 'reload' ) {

            $popup_id = $_POST['popup_id'];
            $error_node = $_POST['error_node'];
            $last_index = (int)$_POST['last_index'];

            $accounting = new accounting($this->current_hash);
            $accounting->fetch_correction_codes();

            for ( $i = 0; $i < count($error_node); $i++ ) {

            	$code_in = $code_out = array();
	            foreach ( $accounting->correction_codes as $code => $code_array ) {

	                if ( $code_array['active'] || ( ! $code_array['active'] && $credit_hash && $_POST["orig_value_{$error_node[$i]}"] == $code_array['correction_hash'] ) ) {

	                    $code_in[] = $code_array['correction_hash'];
	                    $code_out[] = "$code - " . ( strlen($code_array['descr']) > 30 ? substr($code_array['descr'], 0, 27) . "..." : $code_array['descr'] );
	                }
	            }

            	$this->content['html']["code_select_{$error_node[$i]}"] = $this->form->select(
                	"correction_code{$error_node[$i]}",
			        ( is_array($code_out) ? ( $this->p->ck('system_config', 'L', 'company_settings') ? array_merge($code_out, array("&nbsp;&nbsp;--&nbsp;&nbsp;Create/Edit Correction Codes")) : $code_out ) : array("&nbsp;&nbsp;--&nbsp;&nbsp;Create/Edit Correction Codes") ),
			        $_POST["orig_value_{$error_node[$i]}"],
			        ( is_array($code_out) ? ( $this->p->ck('system_config', 'L', 'company_settings') ? array_merge($code_in, array('*')) : $code_in ) : array('*') ),
			        "onChange=if( this.options[ this.selectedIndex ].value == '*' ) { agent.call('system_config', 'sf_loadcontent', 'show_popup_window', 'correction_code_list', 'popup_id=correction_code_win', 'from_credit=1', 'credit_popup_id=$popup_id'); this.value='" . ( $credit_hash && $_POST["orig_value_{$error_node[$i]}"] ? $_POST["orig_value_{$error_node[$i]}"] : NULL ) . "'; }else if ( this.options[ this.selectedIndex ].value == '' ) { \$('amount_{$error_node[$i]}').value='';\$('memo_{$error_node[$i]}').value='';} tally();",
			        "style=width:225px;",
			        "tabindex=$last_index"
			    );

			    $last_index += 3;
            }
    	}

        $this->content['action'] = 'continue';
        return;
    }

    function doit_search() {

        $sort = $_POST['sort'];
        $save = $_POST['page_pref'];
        $detail_search = $_POST['detail_search'];

        $str = "sort=$sort";

        if ( $save ) {

            $r = $this->db->query("SELECT obj_id
                                   FROM page_prefs
                                   WHERE id_hash = '{$this->current_hash}' AND class = '" . get_class($this) . "'");
            if ( $obj_id = $this->db->result($r) ) {

                if ( ! $this->db->query("UPDATE page_prefs
		                                 SET pref_str = '$str'
		                                 WHERE obj_id = $obj_id")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }

            } else {

                if ( ! $this->db->query("INSERT INTO page_prefs
		                                 VALUES
		                                 (
			                                 NULL,
			                                 '{$this->current_hash}',
			                                 '" . get_class($this) . "',
			                                 '',
			                                 '$str'
		                                 )")
                ) {

                	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
                }
            }

        } else {

            unset($this->page_pref['custom']);

            $sql_p = array();

            if ( $sort != '*' ) {

                switch ( $sort ) {

                    case 1:
                    array_push($sql_p, "customer_credit.balance != 0");
                    break;

                    case 2:
                    array_push($sql_p, "customer_credit.balance = 0");
                    break;
                }
            }

            if ( $detail_search ) {

                $sql_p = array();

                $credit_no = $_POST['credit_no'];
                $invoice_no = $_POST['invoice_no'];
                $customer_filter = $_POST['customer_filter'];
                if ( ! is_array($customer_filter) && $customer_filter )
                    $customer_filter = array($customer_filter);

                array_walk($customer_filter, 'add_quotes',"'");

                $applied = $_POST['applied'];
                $credit_date = $_POST['credit_date'];

                $save = $_POST['save'];
                $search_name = addslashes($_POST['search_name']);
                if ( ! $search_name )
                    unset($save);

                if ( $credit_no )
                    array_push($sql_p, "customer_credit.credit_no LIKE '%{$credit_no}%'");
                if ( $invoice_no )
                    array_push($sql_p, "customer_invoice.invoice_no LIKE '%{$invoice_no}%'");
                if ( $applied ) {

                	if ( $applied == 1 )
	                    array_push($sql_p, "customer_credit.balance = 0");
                    elseif ( $applied == 2 )
                        array_push($sql_p, " ( customer_credit.balance > 0 AND customer_credit.balance < customer_credit.amount ) ");
                    elseif ( $applied == 3 )
                        array_push($sql_p, "customer_credit.balance = customer_credit.amount");
                }

                if ( is_array($customer_filter) )
                    array_push($sql_p, "customer_credit.customer_hash IN (" . implode(", ", $customer_filter) . ")");
                if ( $credit_date )
                    array_push($sql_p, "customer_credit.credit_date >= '$credit_date'");

                array_walk($customer_filter, 'strip_quotes');
                $str .= "|credit_no=$credit_no|invoice_no=$invoice_no|applied=$applied|credit_date=$credit_date|customer_filter=" . implode("&", $customer_filter);
            }

            if ( $sql_p )
                $sql = implode(" AND ", $sql_p);

            $r = $this->db->query("SELECT COUNT(*) AS Total
                                   FROM customer_credit
                                   LEFT JOIN customer_invoice ON customer_invoice.invoice_hash = customer_credit.invoice_hash AND customer_credit.deleted = 0 " .
                                   ( $sql ?
                                       "WHERE $sql ": NULL
                                   ) );
            $total = $this->db->result($r, 0, 'Total');

            $search_hash = rand_hash('search', 'search_hash');
            if ( ! $this->db->query("INSERT INTO search
                                     VALUES
                                     (
	                                     NULL,
	                                     UNIX_TIMESTAMP(),
	                                     '$search_hash',
	                                     '$save',
	                                     '$search_name',
	                                     '" . get_class($this) . "',
	                                     '{$this->current_hash}',
	                                     '$detail_search',
	                                     '" . base64_encode($sql) . "',
	                                     '$total',
	                                     '$str'
                                     )")
            ) {

            	return $this->__trigger_error("{$this->db->db_errno} - {$this->db->db_error}", E_DATABASE_ERROR, __FILE__, __LINE__, 1);
            }

            $this->active_search = $search_hash;
        }

        fclose($fh);

        $c_action = $_POST['c_action'];
        $this->content['action'] = ($c_action ? $c_action : 'continue');
        $this->content['jscript_action'] = "agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall','active_search=".$this->active_search."');";
        return;
    }

    function search_credits() {
        $date = date("U") - 3600;
        $result = $this->db->query("DELETE FROM search
                                    WHERE timestamp <= '$date'");

        $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Search Customer Credits";

        $this->content['focus'] = "credit_no";

        list($search_name,$search_hash) = $this->fetch_searches('customer_credits');

        if ( $this->ajax_vars['active_search'] ) {

            $result = $this->db->query("SELECT search_str
                                        FROM search
                                        WHERE search_hash = '{$this->ajax_vars['active_search']}'");
            if ( $row = $this->db->fetch_assoc($result) ) {

                $search_vars = permissions::load_search_vars( $row['search_str'] );
            }
        }

        $tbl =
        $this->form->form_tag() .
        $this->form->hidden( array(
            'popup_id'        => $this->content['popup_controls']['popup_id'],
            'detail_search'   => 1,
            'c_action'        => 'close'
        ) ) . "
        <div class=\"panel\" id=\"main_table\">
            <div id=\"feedback_holder\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                <p id=\"feedback_message\"></p>
            </div>
            <div style=\"padding:15px 0 0 15px;font-weight:bold;\"></div>
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:700px;margin-top:0;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"padding:20px 45px;background-color:#ffffff;\">
                        <div style=\"font-weight:bold;padding-bottom:10px;\">
                            Filter your search criteria below:
                        </div>
                        <table >
                            <tr>
                                <td style=\"padding-left:15px;\">
                                    <fieldset>
                                        <legend>Credit Number</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->text_box("name=credit_no",
                                                                    "size=25",
                                                                    "maxlength=255",
                                                                    ($search_vars ? "onFocus=this.select()" : NULL),
                                                                    "value=".$search_vars['credit_no'])."
                                        </div>
                                    </fieldset>
                                </td>
                                <td style=\"padding-left:15px;\">
                                    <fieldset>
                                        <legend>Invoice Number</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->text_box("name=invoice_no",
                                                                    "value=".$search_vars['invoice_no'],
                                                                    "size=35",
                                                                    ($search_vars ? "onFocus=this.select()" : NULL),
                                                                    "maxlength=255")."
                                        </div>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;vertical-align:middle;\">
                                    <fieldset>
                                        <legend>Credit Date</legend>
                                        <div style=\"padding:5px 5px 0 5px;\" id=\"credit_date{$this->popup_id}\"></div>
                                    </fieldset>
                                </td>";
                                $this->content['jscript'][] = "setTimeout('DateInput(\'credit_date\',false,\'YYYY-MM-DD\',\'".($search_vars['credit_date'] ? $search_vars['credit_date'] : NULL)."\',1,\'credit_date{$this->popup_id}\')',350);";
                                $tbl .= "
                                <td style=\"padding-left:15px;vertical-align:top;\" rowspan=\"2\">
                                    <fieldset>
                                        <legend>Search By Customer</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->text_box("name=customer",
                                                                    "value=",
                                                                    "autocomplete=off",
                                                                    "size=37",
                                                                    "onFocus=position_element(\$('keyResults'),findPos(this,'top')+20,findPos(this,'left'));if(ka==false && this.value){key_call('reports','customer','customer_hash',1);}",
                                                                    "onKeyUp=if(ka==false && this.value){key_call('reports','customer','customer_hash',1);}",
                                                                    "onBlur=key_clear();"
                                                                    )."


                                            <div style=\"margin-top:15px;width:250px;height:85px;overflow:auto;display:none;background-color:#efefef;border:1px outset #cccccc;\" id=\"customer_filter_holder\">";
                                            if (is_array($search_vars['customer_filter']) || $search_vars['customer_filter']) {
                                                if (!is_array($search_vars['customer_filter']))
                                                    $search_vars['customer_filter'] = array($search_vars['customer_filter']);
                                                for ($i = 0; $i < count($search_vars['customer_filter']); $i++)
                                                    $tbl .= "<div id=\"customer_".$search_vars['customer_filter'][$i]."\" style=\"padding:5px 2px 0 5px;\">[<small><a href=\"javascript:void(0);\" onClick=\"remove_el(this.parentElement.parentElement)\" class=\"link_standard\" title=\"Remove from filter\">x</a></small>]&nbsp;".addslashes((strlen(reports::customer_name($search_vars['customer_filter'][$i])) > 25 ? substr(reports::customer_name($search_vars['customer_filter'][$i]),0,22)."..." : reports::customer_name($search_vars['customer_filter'][$i])))."<input type=\"hidden\" id=\"customer_filter\" name=\"customer_filter[]\" value=\"".$search_vars['customer_filter'][$i]."\" /></div>";
                                            }
                                            $tbl .= "
                                            </div>
                                        </div>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <td style=\"padding-left:15px;vertical-align:middle;\">
                                    <fieldset>
                                        <legend>Credit Applied/Unapplied</legend>
                                        <div style=\"padding:5px;padding-bottom:5px\">
                                            ".$this->form->select("applied",
                                                                   array("Fully Applied","Partially Applied","Not Applied"),
                                                                   $search_vars['applied'],
                                                                   array(1,2,3))."
                                        </div>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <div style=\"float:right;text-align:right;padding-top:15px;margin-right:25px;\">
                ".$this->form->button("value=Search",
                                      "id=primary",
                                      "onClick=submit_form(\$('popup_id').form,'customer_credits','exec_post','refresh_form','action=doit_search');")."
            </div>
            <div style=\"margin:15px;\">
                <table>
                    <tr>
                        <td>
                            ".$this->form->checkbox("name=save","value=1","onClick=if(this.checked){toggle_display('save_search_box','block')}else{toggle_display('save_search_box','none')}")."&nbsp;
                            Save Search?
                        </td>
                        <td>" .
                        ( $search_hash ?
                            "<div style=\"margin-left:15px;\">" .
                                $this->form->select(
                                    "saved_searches",
                                    $search_name,
                                    '',
                                    $search_hash,
                                    "style=width:200px",
                                    "onChange=if(this.options[this.selectedIndex].value != ''){toggle_display('search_go_btn','block');}else{toggle_display('search_go_btn','none');}"
                                ) . "&nbsp;" .
                                $this->form->button(
                                    "value=Go",
                                    "onClick=agent.call('customer_credits','sf_loadcontent','cf_loadcontent','showall','active_search='+\$F('saved_searches'));window.setTimeout('popup_windows[\'{$this->content['popup_controls']['popup_id']}\'].hide()',1000);"
                                ) . "
                                &nbsp;" .
                                $this->form->button(
                                    "value=&nbsp;X&nbsp;",
                                    "title=Delete this saved search","onClick=if(\$('saved_searches') && \$('saved_searches').options[\$('saved_searches').selectedIndex].value){if(confirm('Do you really want to delete your saved search?')){agent.call('customer_credits','sf_loadcontent','cf_loadcontent','purge_search','s='+\$('saved_searches').options[\$('saved_searches').selectedIndex].value);\$('saved_searches').remove(\$('saved_searches').selectedIndex);}}"
                                ) . "
                            </div>" : NULL
                        ) . "
                        </td>
                    </tr>
                    <tr>
                        <td colspan=\"2\">
                            <div style=\"padding-top:5px;display:none;margin-left:5px;\" id=\"save_search_box\">
                                Name Your Saved Search:
                                <div style=\"margin-top:5px;\">
                                    ".$this->form->text_box("name=search_name",
                                                            "value=",
                                                            "size=35",
                                                            "style=background-color:#ffffff",
                                                            "maxlenth=64")."
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>".
        $this->form->close_form();

        $this->content['popup_controls']["cmdTable"] = $tbl;
    }

    function invoice_credits() {
    	
    	$hasMaximum = false;
    	$result = $this->db->query("SELECT
    			t1.*,
    			t3.maximum AS tax_maximum
    			FROM tax_collected t1
    			LEFT JOIN line_items t2 ON t2.item_hash = t1.item_hash
    			LEFT JOIN tax_table t3 ON t3.tax_hash = t1.tax_hash
    			WHERE t1.invoice_hash = '{$this->invoice_hash}' AND t2.punch = " . ( $this->punch ? 1 : 0 ) . " AND t2.active = 1");
    			while ( $row = $this->db->fetch_assoc($result) ) {
    			if($row['tax_maximum'] > 0){
    			$hasMaximum = true;
    			break;
    	}
    	}

        $invoice_hash = $this->ajax_vars['invoice_hash'];
        $invoice_popup = $this->ajax_vars['invoice_popup'];

        $customer_invoice = new customer_invoice($this->current_hash);
        if ( ! $customer_invoice->fetch_invoice_record($invoice_hash) )
            return $this->__trigger_error("System error encountered during receivable invoice lookup. Unable to fetch customer invoice for database edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Issue Credits on Invoice {$customer_invoice->current_invoice['invoice_no']}";

        $lines = new line_items($customer_invoice->proposal_hash, $customer_invoice->current_invoice['punch']);

        if ( $credit_hash = $this->ajax_vars['credit_hash'] ) {

        	if ( ! $this->fetch_credit_record($credit_hash) )
            	return $this->__trigger_error("System error encountered during credit lookup. Unable to fetch customer credit for database edit. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

            $this->content['popup_controls']['popup_title'] = "View/Edit Invoice Credit {$this->current_credit['credit_no']}";
        }

        $this->content['popup_controls']['popup_width'] = "525px;";
        $this->content['popup_controls']['popup_height'] = "675px;";
        if ( $this->credit_hash && count($this->current_credit['credit_item']) > 3 )
            $this->content['popup_controls']['popup_height'] = "875px;";

        $code_out = $code_in = array();

		$accounting = new accounting($this->current_hash);
		$accounting->fetch_correction_codes();
		foreach ( $accounting->correction_codes as $code => $code_array ) {

			if ( $code_array['active'] ) {

				$code_in[] = $code_array['correction_hash'];
				$code_out[] = "$code - " . ( strlen($code_array['descr']) > 30 ? substr( stripslashes($code_array['descr']), 0, 27) . "..." : stripslashes($code_array['descr']) );
			}
		}
		
		if($hasMaximum){
			$this->content['jscript'][] = "
			tally = function() {
			
			var item = \$('table_content').getElementsBySelector('[id2=\"ice\"]');
			var total_credit = 0;
			var total_tax = document.getElementById(\"taxEntered\").value;
			var total_amount = 0;
			var sell = 0;
			var percent = 0;
			
			for ( var i = 0; i < item.length; i++ ) {
			if ( item[i].checked ) {
			var val = \$F(item[i]);
			num = parseFloat(\$F('item_credit_'+\$F(item[i])).toString().replace(/[^-.0-9]/g,''));
			if( isNaN(num) )
			num = 0;
			
			num2 = 0;
			if( isNaN(num2) )
			num2 = 0;
			
			sell = parseFloat( \$F('item_sell[' + \$F(item[i]) + ']').toString().replace(/[^-.0-9]/g,'') );
			if( isNaN(sell) )
			sell = 0;
			
			
			\$('item_tax_actual[' + \$F(item[i]) + ']').value = parseInt(total_tax);
			total_credit += num;
			
			}
			}
			
			total_amount = total_credit + parseInt(total_tax);
			if (total_credit != 0)
			\$('total_credit{$this->popup_id}').innerHTML = '$' + formatCurrency(total_credit);
			else
			\$('total_credit{$this->popup_id}').innerHTML = '$0.00';
			
			if (total_amount != 0)
			\$('total_amount{$this->popup_id}').innerHTML = '$' + formatCurrency(total_amount);
			else
			\$('total_amount{$this->popup_id}').innerHTML = '$0.00';
			
			
			}";
			
			$this->content['jscript'][] = "
			tallyTotal = function() {
			
			var item = \$('table_content').getElementsBySelector('[id2=\"ice\"]');
			var total_credit = 0;
			var total_tax = document.getElementById(\"taxEntered\").value;
			var total_amount = 0;
			var sell = 0;
			var percent = 0;
			
			for ( var i = 0; i < item.length; i++ ) {
			if ( item[i].checked ) {
			var val = \$F(item[i]);
			num = parseFloat(\$F('item_credit_'+\$F(item[i])).toString().replace(/[^-.0-9]/g,''));
			if( isNaN(num) )
			num = 0;
			
			num2 = 0;
			if( isNaN(num2) )
			num2 = 0;
			
			sell = parseFloat( \$F('item_sell[' + \$F(item[i]) + ']').toString().replace(/[^-.0-9]/g,'') );
			if( isNaN(sell) )
			sell = 0;
			
			
			\$('item_tax_actual[' + \$F(item[i]) + ']').value = parseInt(total_tax);
			total_credit += num;
			
			}
			}
			
			total_amount = total_credit + parseInt(total_tax);
			if (total_credit != 0)
			\$('total_credit{$this->popup_id}').innerHTML = '$' + formatCurrency(total_credit);
			else
			\$('total_credit{$this->popup_id}').innerHTML = '$0.00';
			
			if (total_amount != 0)
			\$('total_amount{$this->popup_id}').innerHTML = '$' + formatCurrency(total_amount);
			else
			\$('total_amount{$this->popup_id}').innerHTML = '$0.00';
			
			
			}";
		}else{
			$this->content['jscript'][] = "
			tally = function() {
	
	            var item = \$('table_content').getElementsBySelector('[id2=\"ice\"]');
	            var total_credit = 0;
	            var total_tax = 0;
	            var total_amount = 0;
	            var sell = 0;
	            var percent = 0;
	
	            for ( var i = 0; i < item.length; i++ ) {
	                if ( item[i].checked ) {
	                    var val = \$F(item[i]);
	                    num = parseFloat(\$F('item_credit_'+\$F(item[i])).toString().replace(/[^-.0-9]/g,''));
			            if( isNaN(num) )
			                num = 0;
	
			            num2 = parseFloat( \$F('item_tax[' + \$F(item[i]) + ']').toString().replace(/[^-.0-9]/g,'') );
			            if( isNaN(num2) )
			                num2 = 0;
	
						sell = parseFloat( \$F('item_sell[' + \$F(item[i]) + ']').toString().replace(/[^-.0-9]/g,'') );
			            if( isNaN(sell) )
			                sell = 0;
						if ( sell != num ) {
			            	percent = ( num / sell );
			            	num2 = ( num2 * percent );
			            	num2 = Math.round(num2 * 100) / 100;
			            }
	
			            \$('item_tax_actual[' + \$F(item[i]) + ']').value = num2;
			            total_credit += num;
	                    total_tax += num2;
	
	                }
	            }
	
	            total_amount = total_credit + total_tax;
	            if (total_credit != 0)
	                \$('total_credit{$this->popup_id}').innerHTML = '$' + formatCurrency(total_credit);
				else
	            	\$('total_credit{$this->popup_id}').innerHTML = '$0.00';
	
	          
	            if (total_tax != 0)
	                \$('total_tax{$this->popup_id}').innerHTML = '$' + formatCurrency(total_tax);
				else
	            	\$('total_tax{$this->popup_id}').innerHTML = '$0.00';
	
	            if (total_amount != 0)
	                \$('total_amount{$this->popup_id}').innerHTML = '$' + formatCurrency(total_amount);
				else
	            	\$('total_amount{$this->popup_id}').innerHTML = '$0.00';
	
	
	        }";
    	}

        if ( ! $this->credit_hash ) {

        	# Increment the credit num if previous credits exist under this inv
	        $r = $this->db->query("SELECT COUNT( t1.obj_id ) AS Total
	                               FROM customer_credit t1
	                               WHERE t1.invoice_hash = '{$customer_invoice->invoice_hash}' AND t1.deleted = 0");
	        if ( $prev_cre = $this->db->result($r, 0, 'Total') )
                $prev_cre = "-$prev_cre";
        }

        if ( $this->credit_hash ) {

        	# Make sure only 1 credit applied exists, otherwise disable save btn
        	$r = $this->db->query("SELECT COUNT(*) AS Total
        	                       FROM customer_credit_applied t1
        	                       WHERE t1.credit_hash = '{$this->credit_hash}' AND t1.void = 0");
            $total_applied = $this->db->result($r, 0, 'Total');
        }

        $tbl =
        $this->form->form_tag() .
        $this->form->hidden( array(
            'popup_id'        =>   $this->popup_id,
            'invoice_hash'    =>   $customer_invoice->invoice_hash,
            'proposal_hash'   =>   $customer_invoice->proposal_hash,
            'credit_hash'     =>   $this->credit_hash,
            'invoice_popup'   =>   $invoice_popup,
            'apply_invoice'   =>   1
        ) ) . "
        <div class=\"panel\" id=\"main_table{$this->popup_id}\">
            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                 <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message{$this->popup_id}\"></p>
            </div>
            <table cellspacing=\"0\" cellpadding=\"5\" style=\"border:1px solid #8c8c8c;width:490px;height:525px;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;\">
                        <h5 style=\"margin-bottom:5px;color:#00477f;\">
                            Customer Invoice : <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_invoice', 'sf_loadcontent', 'show_popup_window', 'edit_invoice', 'invoice_hash={$customer_invoice->invoice_hash}', 'popup_id=invoice_window');\" style=\"color:#00477f;text-decoration:underline;\" title=\"View this invoice\">{$customer_invoice->current_invoice['invoice_no']}</a>
                        </h5>
                        <div style=\"margin-top:15px;margin-left:15px;\">
                            <table cellpadding=\"2\">
                                <tr>
                                    <td style=\"text-align:right;font-weight:bold\">Customer: </td>
                                    <td>" . stripslashes($customer_invoice->current_invoice['customer_name']) . "</td>
                                </tr>
                                <tr>
                                    <td style=\"text-align:right;font-weight:bold\">Proposal: </td>
                                    <td><a href=\"javascript:void(0);\" onClick=\"agent.call('proposals', 'sf_loadcontent', 'show_popup_window', 'edit_proposal', 'proposal_hash={$customer_invoice->proposal_hash}', 'popup_id=proposal_win', 'from_report=1');\" style=\"color:#000;text-decoration:underline;\" title=\"View this proposal\">{$this->current_credit['proposal_no']}</a></td>
                                </tr>
                                <tr>
                                    <td style=\"text-align:right;font-weight:bold\">Invoice Date: </td>
                                    <td>" . date(DATE_FORMAT, strtotime($customer_invoice->current_invoice['invoice_date'])) . "</td>
                                </tr>
                                <tr>
                                    <td style=\"text-align:right;font-weight:bold\">Invoice Total: </td>
                                    <td id=\"amount_holder\">" .
		                            ( bccomp($customer_invoice->current_invoice['amount'], 0, 2) == -1 ?
		                                "(\$" . number_format( bcmul($customer_invoice->current_invoice['amount'], -1, 2), 2) . ")" : "\$" . number_format($customer_invoice->current_invoice['amount'], 2)
		                            ) . "
                                    </td>
                                </tr>
                                <tr>
                                    <td style=\"text-align:right;font-weight:bold\">Invoice Balance: </td>
                                    <td id=\"balance_holder\">" .
                                    ( bccomp($customer_invoice->current_invoice['balance'], 0, 2) == -1 ?
                                        "(\$" . number_format( bcmul($customer_invoice->current_invoice['balance'], -1, 2), 2) . ")" : "\$" . number_format($customer_invoice->current_invoice['balance'], 2)
                                    ) . "
                                    </td>
                                </tr>
                            </table>
                            <div style=\"margin-top:15px;\">
                                <div style=\"margin-bottom:4px;\">
                                    Use this tool to issue credits on specific line items on your invoice.
                                    By selecting a line item, you may indicate a credit amount for each item.
                                    <br /><br />
                                    When finished, click the 'Issue Credits' button below.
                                </div>
                                <div style=\"padding-top:0;" . ( count($customer_invoice->current_invoice['line_items']) > 9 ? "height:300px;overflow-y:auto;" : NULL ) . "overflow-x:hidden;\" id=\"table_content\">
	                                <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8f8f8f;width:450px;\">";

	                                for ($i = 0; $i < count($customer_invoice->current_invoice['line_items']); $i++) {

	                                	if ( ! $lines->fetch_line_item_record($customer_invoice->current_invoice['line_items'][$i]) )
                                            return $this->__trigger_error("A system error was encountered during line item lookup. Please reload credits window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

	                                	if ( bccomp($lines->current_line['ext_sell'], 0, 2) == 1 ) {

	                                		$income_acct = $accounting->fetch_account_name($lines->current_line['income_account_hash']);

	                                		unset($this->credit_item_hash, $this->current_credit_item);

	                                		if ( $this->credit_hash && in_array($lines->item_hash, $this->current_credit['credit_item']) ) {

	                                			if ( ! $this->fetch_credit_item($this->credit_hash, $lines->item_hash) )
    	                                			return $this->__trigger_error("An error was encountered when attempting to lookup credit applied on one or more invoice line items. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1, 2);

	                                		}

		                                	list($item_tax) = $lines->fetch_invoice_tax($customer_invoice->invoice_hash, $lines->item_hash);

		                                	$ext_sell = $lines->current_line['ext_sell'];

		                                	$r = $this->db->query("SELECT SUM( t1.amount ) AS item_total
		                                						   FROM customer_credit_expenses t1
		                                						   LEFT JOIN customer_credit t2 ON t2.credit_hash = t1.credit_hash
		                                						   WHERE t1.item_hash = '{$lines->item_hash}' AND t2.deleted = 0");
											if ( $item_total = $this->db->result($r, 0, 'item_total') ) {

												$ext_sell = bcsub($ext_sell, $item_total, 2);
												$item_tax = bcmul($item_tax, bcdiv($ext_sell, $lines->current_line['ext_sell'], 4), 4);
											}

		                                    $tbl .= "
		                                    <tr style=\"background-color:#ffffff;\">
		                                        <td style=\"width:20px;background-color:#efefef;\">" .
		                                            $this->form->checkbox(
    		                                            "name=item[]",
                                                        "value={$lines->item_hash}",
		                                                "id2=ice",
		                                                ( $this->credit_item_hash ? "checked" : NULL ),
		                                                "onClick=toggle_display('i_{$lines->item_hash}', (this.checked ? 'block' : 'none'));tally()"
		                                            ) .
													$this->form->hidden( array(
													    "item_sell[{$lines->item_hash}]"           =>  $ext_sell,
	    												"item_tax[{$lines->item_hash}]"            =>  $item_tax,
	    												"item_tax_actual[{$lines->item_hash}]"     =>  0
													) ) . "
		                                        </td>
		                                        <td style=\"width:300px;\">" .
												( strlen("{$lines->current_line['item_product']} : {$lines->current_line['item_descr']}") > 50 ?
	                                                substr("{$lines->current_line['item_product']} : {$lines->current_line['item_descr']}", 0, 48) . "..." : "{$lines->current_line['item_product']} : {$lines->current_line['item_descr']}"
	                                            ) . "
	                                            </td>
	                                            <td style=\"width:130px;text-align:right;\">\$" . number_format($ext_sell, 2) . "</td>
		                                    </tr>
		                                    <tr id=\"i_{$lines->item_hash}\" " . ( $this->credit_item_hash ? "" : "style=\"display:none" ) . ";background-color:#efefef;\">
	                                            <td colspan=\"3\">
	                                                <table style=\"width:100%;\" cellspacing=\"0\" cellpadding=\"0\">
	                                                    <tr>
	                                                        <td style=\"text-align:right;\">
                                                                <span id=\"err1_{$lines->item_hash}\">Code: </span>" .
	                                                            $this->form->select(
	                                                                "code[{$lines->item_hash}]",
				                                                    array_merge($code_out, array("-- AUTO -- [$income_acct]")),
				                                                    ( $this->credit_item_hash ? $this->current_credit_item['correction_code'] : NULL ),
				                                                    array_merge($code_in, array("--AUTO--")),
				                                                    "blank=1",
				                                                    "style=width:275px;"
				                                                ) . "
	                                                        </td>
	                                                        <td style=\"text-align:right;\">
	                                                            <span id=\"err2_{$lines->item_hash}\">Amt: </span>" .
	                                                            $this->form->text_box(
                                                                    "name=item_credit_{$lines->item_hash}",
                                                                    "value=" . ( $this->credit_item_hash ? number_format($this->current_credit_item['amount'], 2) : number_format($ext_sell, 2) ),
                                                                    "size=9",
                                                                    "onFocus=this.select()",
                                                                    "onBlur=if(this.value < 0){this.value='0.00';}if(this.value){if(this.value > {$ext_sell}){this.value='" . number_format($ext_sell, 2) . "'}else{this.value=formatCurrency(this.value)}}tally()",
                                                                    "style=text-align:right;background-color:#ffffff;"
	                                                            ) . "
	                                                        </td>
	                                                    </tr>
	                                                </table>
	                                            </td>
		                                    </tr>";
	                                	}
	                                }
	                                
	                                if($hasMaximum){
	                                	$tbl .= "
	                                	</table>
	                                	</div>
	                                	<div style=\"text-align:right;margin-top:15px;margin-bottom:15px;margin-right:25px;\">
	                                	<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8f8f8f\">
	                                	<tr>
	                                	<td style=\"text-align:right;vertical-align:top;background-color:#efefef;width:115px;padding-top:10px;\">Credit Amount: </td>
	                                	<td style=\"background-color:#ffffff;width:100px;text-align:left;\">
	                                	<div>
	                                	<table cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#ffffff\">
	                                	<tr>
	                                	<td>
	                                	<div style=\"text-align:right;\" id=\"total_credit{$this->popup_id}\">" .
	                                	( $this->credit_hash ?
	                                			"\$" . number_format( bcsub($this->current_credit['amount'], $this->current_credit['tax'], 2), 2) : "\$0.00"
	                                	) . "
	                                	</div>
	                                	
	                                	</td>
	                                	<td>
	                                	<div>Credit</div>
	                                	</td>
	                                	</tr>
	                                	<tr>
	                                	<td>
	                                	<div style=\"text-align:right;\" id=\"total_tax{$this->popup_id}\">" .
	                                	$this->form->text_box(
	                                			"name=tax",
	                                			"value=" . number_format($this->current_credit['tax'],2),
	                                			"style=text-align:right;",
	                                			"id=taxEntered",
	                                			"onBlur=if(this.value){this.value=formatCurrency(this.value)}tallyTotal()",
	                                			"size=8"
	                                			) . "
	                                			</div>
	                                	</td>
	                                	<td>
	                                	<div>Tax</div>
	                                	</td>
	                                	</tr>
	                                	<tr>
	                                	<td style=\"border-top:1px solid #000000;padding-top:5px;\">
	                                	<div style=\"text-align:right;\" id=\"total_amount{$this->popup_id}\">" .
	                                	( $this->credit_hash ?
	                                			"\$" . number_format($this->current_credit['amount'], 2) : "\$0.00"
	                                	) . "
	                                	</div>
	                                	</td>
	                                	<td style=\"border-top:1px solid #000000;padding-top:5px;\">
	                                	<div>Total</div>
	                                	</td>
	                                	</tr>
	                                	</table>
	                                	</div>
	                                	</td>
	                                	</tr>" .
	                                	( $this->credit_hash ?
	                                			"<tr>
	                                			<td style=\"text-align:right;background-color:#efefef;width:115px;\">Open Balance: </td>
	                                			<td style=\"background-color:#ffffff;width:100px;text-align:left;\">" .
	                                			( bccomp($this->current_credit['balance'], 0, 2) == -1 ?
	                                					"(\$" . number_format( bcmul($this->current_credit['balance'], -1, 2), 2) . ")" : "\$" . number_format($this->current_credit['balance'], 2)
	                                			) . "
	                                			</td>
	                                			</tr>" : NULL
	                                	) . "
	                                	<tr>
	                                	<td style=\"text-align:right;background-color:#efefef;width:115px;\">Reference No: </td>
	                                	<td style=\"background-color:#ffffff;width:100px;text-align:left;\">
	                                	<div id=\"ref_no{$this->popup_id}\">" .
	                                	$this->form->text_box(
	                                			"name=credit_no",
	                                			"value=" . ( $this->credit_hash ? $this->current_credit['credit_no'] : "CR-{$customer_invoice->current_invoice['invoice_no']}$prev_cre" ),
	                                			"size=24",
	                                			( $this->credit_hash ? "readonly title=Credit reference number cannot be modified once credit has been issued" : NULL )
	                                			) . "
	                                			</div>
	                                			</td>
	                                			</tr>
	                                			<tr>
	                                			<td style=\"text-align:right;background-color:#efefef;width:115px;\" id=\"err_dt1{$this->popup_id}\">Credit Date: </td>
	                                			<td style=\"background-color:#ffffff;width:100px;text-align:left;\">" .
	                                			( $this->credit_hash ?
	                                					$this->form->hidden( array(
	                                					'credit_date'   =>  $this->current_credit['credit_date']
	                                			) ) .
	                                			date(DATE_FORMAT, strtotime($this->current_credit['credit_date'])) : "<div id=\"date{$this->popup_id}\"></div>"
	                                			) . "
	                                			</td>
	                                			</tr>
	                                			<tr>
	                                			<td style=\"text-align:right;background-color:#efefef;width:115px;vertical-align:top;\">Comments: </td>
	                                			<td style=\"background-color:#ffffff;width:100px;text-align:left;\">
	                                			<div id=\"comment{$this->popup_id}\">" .
	                                			$this->form->text_area(
	                                					"name=comment",
	                                					"value=" . ( $this->credit_hash ? stripslashes($this->current_credit['notes']) : NULL ),
	                                							"rows=3",
	                                							"cols=25"
	                                					) . "
	                                							</div>
	                                							</td>
	                                							</tr>
	                                							</table>
	                                							</div>
	                                							<div style=\"text-align:right;margin-top:25px;margin-right:25px;margin-bottom:10px;\">" .
	                                							$this->form->button(
	                                							"value=Save Credit",
	                                							"id=invoice_credit_btn",
	                                							( $total_applied > 1 ? "disabled title=Credit has been applied to multiple receivables, changes not permitted" : NULL ),
	                                									"onClick=if(confirm('" . ( $this->credit_hash ? "Are you sure you want to make changes to this credit? Changes to the credit amount will affect any credits applied from the credit." : "Are you sure you want to issue customer credits towards this invoice? Any credit issued from this invoice will automatically be applied to the invoice." ) . "')){submit_form(this.form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit','method=invoice');this.disabled=1;}"
	                                							) . "&nbsp;&nbsp;" .
	                                									( $this->credit_hash ?
	                                									$this->form->button(
	                                									"value=Delete Credit",
	                                									"id=invoice_credit_del",
	                                									"onClick=if(confirm('Are you sure you want to delete this credit? Any credits which may have been applied to a receivable will be removed. This action CANNOT be undone!')){submit_form(this.form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit','method=rm','caller=customer_invoice','submit_btn=invoice_credit_del');this.disabled=1;}"
	                                	) : NULL
	                                	) . "
	                                	</div>
	                                	</div>
	                                	</div>
	                                	</td>
	                                	</tr>
	                                	</table>
	                                	</div>".
	                                	$this->form->close_form();
	                                }else{
		                                $tbl .= "
		                                </table>
		                            </div>
		                            <div style=\"text-align:right;margin-top:15px;margin-bottom:15px;margin-right:25px;\">
	                                    <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8f8f8f\">
	                                        <tr>
	                                            <td style=\"text-align:right;vertical-align:top;background-color:#efefef;width:115px;padding-top:10px;\">Credit Amount: </td>
	                                            <td style=\"background-color:#ffffff;width:100px;text-align:left;\">
	                                            	<div>
	                                            		<table cellspacing=\"0\" cellpadding=\"5\" style=\"background-color:#ffffff\">
	                                                		<tr>
	                                                			<td>
	                                                				<div style=\"text-align:right;\" id=\"total_credit{$this->popup_id}\">" .
	                                                				( $this->credit_hash ?
		                                                				"\$" . number_format( bcsub($this->current_credit['amount'], $this->current_credit['tax'], 2), 2) : "\$0.00"
	                                                				) . "
	                                                				</div>
	
			                                                	</td>
			                                                	<td>
			                                                		<div>Credit</div>
			                                                	</td>
			                                                </tr>
			                                                <tr>
			                                                	<td>
			                                                		<div style=\"text-align:right;\" id=\"total_tax{$this->popup_id}\">" .
	                                                                ( $this->credit_hash ?
		                                                                "\$" . number_format($this->current_credit['tax'], 2) : "\$0.00"
	                                                                ) . "
			                                                		</div>
			                                                	</td>
			                                                	<td>
			                                                		<div>Tax</div>
			                                                	</td>
			                                                </tr>
			                                                <tr>
			                                                	<td style=\"border-top:1px solid #000000;padding-top:5px;\">
			                                                		<div style=\"text-align:right;\" id=\"total_amount{$this->popup_id}\">" .
			                                                		( $this->credit_hash ?
	    		                                                		"\$" . number_format($this->current_credit['amount'], 2) : "\$0.00"
			                                                		) . "
			                                                		</div>
			                                                	</td>
			                                                	<td style=\"border-top:1px solid #000000;padding-top:5px;\">
			                                                		<div>Total</div>
			                                                	</td>
	                                                		</tr>
	                                                	</table>
	                                                </div>
	                                            </td>
	                                        </tr>" .
	                                        ( $this->credit_hash ?
	                                            "<tr>
		                                            <td style=\"text-align:right;background-color:#efefef;width:115px;\">Open Balance: </td>
		                                            <td style=\"background-color:#ffffff;width:100px;text-align:left;\">" .
	                                                ( bccomp($this->current_credit['balance'], 0, 2) == -1 ?
	                                                    "(\$" . number_format( bcmul($this->current_credit['balance'], -1, 2), 2) . ")" : "\$" . number_format($this->current_credit['balance'], 2)
	                                                ) . "
	                                                </td>
	                                            </tr>" : NULL
	                                        ) . "
	                                        <tr>
	                                            <td style=\"text-align:right;background-color:#efefef;width:115px;\">Reference No: </td>
	                                            <td style=\"background-color:#ffffff;width:100px;text-align:left;\">
	                                                <div id=\"ref_no{$this->popup_id}\">" .
	                                                    $this->form->text_box(
	                                                        "name=credit_no",
		                                                    "value=" . ( $this->credit_hash ? $this->current_credit['credit_no'] : "CR-{$customer_invoice->current_invoice['invoice_no']}$prev_cre" ),
		                                                    "size=24",
	                                                        ( $this->credit_hash ? "readonly title=Credit reference number cannot be modified once credit has been issued" : NULL )
	                                                    ) . "
	                                                </div>
	                                            </td>
	                                        </tr>
	                                        <tr>
	                                            <td style=\"text-align:right;background-color:#efefef;width:115px;\" id=\"err_dt1{$this->popup_id}\">Credit Date: </td>
	                                            <td style=\"background-color:#ffffff;width:100px;text-align:left;\">" .
	                                            ( $this->credit_hash ?
	                                                $this->form->hidden( array(
	                                                    'credit_date'   =>  $this->current_credit['credit_date']
	                                                ) ) .
	                                                date(DATE_FORMAT, strtotime($this->current_credit['credit_date'])) : "<div id=\"date{$this->popup_id}\"></div>"
	                                            ) . "
	                                            </td>
	                                        </tr>
	                                        <tr>
	                                            <td style=\"text-align:right;background-color:#efefef;width:115px;vertical-align:top;\">Comments: </td>
	                                            <td style=\"background-color:#ffffff;width:100px;text-align:left;\">
	                                                <div id=\"comment{$this->popup_id}\">" .
	                                                    $this->form->text_area(
		                                                    "name=comment",
	                                                        "value=" . ( $this->credit_hash ? stripslashes($this->current_credit['notes']) : NULL ),
	                                                        "rows=3",
		                                                    "cols=25"
	                                                    ) . "
	                                                </div>
	                                            </td>
	                                        </tr>
	                                    </table>
		                            </div>
	                                <div style=\"text-align:right;margin-top:25px;margin-right:25px;margin-bottom:10px;\">" .
	                                    $this->form->button(
	                                        "value=Save Credit",
	                                        "id=invoice_credit_btn",
	                                        ( $total_applied > 1 ? "disabled title=Credit has been applied to multiple receivables, changes not permitted" : NULL ),
	                                        "onClick=if(confirm('" . ( $this->credit_hash ? "Are you sure you want to make changes to this credit? Changes to the credit amount will affect any credits applied from the credit." : "Are you sure you want to issue customer credits towards this invoice? Any credit issued from this invoice will automatically be applied to the invoice." ) . "')){submit_form(this.form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit','method=invoice');this.disabled=1;}"
	                                    ) . "&nbsp;&nbsp;" .
	                                    ( $this->credit_hash ?
		                                    $this->form->button(
	                                            "value=Delete Credit",
		                                        "id=invoice_credit_del",
		                                        "onClick=if(confirm('Are you sure you want to delete this credit? Any credits which may have been applied to a receivable will be removed. This action CANNOT be undone!')){submit_form(this.form,'customer_credits','exec_post','refresh_form','action=doit_customer_credit','method=rm','caller=customer_invoice','submit_btn=invoice_credit_del');this.disabled=1;}"
		                                    ) : NULL
		                                ) . "
	                                </div>
	                            </div>
	                        </div>
	                    </td>
	                </tr>
	            </table>
	        </div>".
	        $this->form->close_form();
    	}

        if ( ! $this->credit_hash )
            $this->content['jscript'][] = "setTimeout('DateInput(\'credit_date\',true,\'YYYY-MM-DD\',\'" . date("Y-m-d") . "\',1,\'date{$this->popup_id}\')',19);";

        $this->content['popup_controls']["cmdTable"] = $tbl.$inner.$foot;
    }

    function fetch_credit_applied($credit_hash, $deleted=NULL) {

    	$applied = array();
        $r = $this->db->query("SELECT
                                   t1.payment_hash,
                                   t1.credit_hash,
                                   t1.amount AS receipt_amount,
                                   t2.payment_hash,
                                   t2.receipt_date,
                                   t2.applied_from AS credit_applied,
                                   t2.applied_type,
                                   t2.comments,
                                   t3.invoice_hash,
                                   t3.invoice_no,
                                   t3.customer_hash
                               FROM customer_credit_applied t1
                               LEFT JOIN customer_payment t2 ON t2.payment_hash = t1.payment_hash
                               LEFT JOIN customer_invoice t3 ON t3.invoice_hash = t2.invoice_hash
                               WHERE t1.credit_hash = '$credit_hash' " .
                               ( ! $deleted ?
                                   "AND t1.void = 0" : NULL
                               ) );
        while ( $row = $this->db->fetch_assoc($r) )
            $applied[] = $row;


    	return $applied;
    }
    
    /**
     * Used to get customer credit applied when deleting a credit.
     * 
     * This method is here to avoid any cascading errors by changing the query
     * and affecting other uses of this method.
     * 
     * @param string $credit_hash
     * 
     * @return array $applied
     */
    function fetch_credit_applied_for_delete($credit_hash) {

        $applied = array();
        $r = $this->db->query("SELECT
                                   t1.credit_hash,
                                   t1.amount AS receipt_amount,
                                   t2.payment_hash,
                                   t2.receipt_date,
                                   t2.applied_from AS credit_applied,
                                   t2.applied_type,
                                   t2.comments,
                                   t3.invoice_hash,
                                   t3.invoice_no,
                                   t3.customer_hash
                               FROM customer_credit_applied t1
                               LEFT JOIN customer_payment t2 ON t2.payment_hash = t1.invoice_hash 
                               LEFT JOIN customer_invoice t3 ON t3.invoice_hash = t2.invoice_hash 
                               WHERE t1.credit_hash = '$credit_hash' ");
        while ( $row = $this->db->fetch_assoc($r) )
            $applied[] = $row;


        return $applied;
    }
    
    function item_list() {
        $args = func_get_args();
        if ($args) {
            $credit_hash = $args[0];
            $invoice_hash = $args[1];
            $popup_id = $args[3];
        } else {
            $credit_hash = $this->ajax_vars['credit_hash'];
            $invoice_hash = $this->ajax_vars['invoice_hash'];
            $popup_id = $this->ajax_vars['popup_id'];
        }

        if (!$this->credit_hash) {
            if ($credit_hash && $this->fetch_credit_record($credit_hash) && count($this->current_credit['credit_item']))
                $credit_item =& $this->current_credit['credit_item'];
        } else
            $credit_item =& $this->current_credit['credit_item'];

		$accounting = new accounting($this->current_hash);
		$accounting->fetch_correction_codes();

		for ($i = 0; $i < count($expenses); $i++) {
		    $rand_err = rand(500,5000000);
		    $code_in = $code_out = array();
		    foreach ($accounting->correction_codes as $code => $code_array) {
		        if ($code_array['active'] || (!$code_array['active'] && $expenses[$i]['correction_code'] == $code_array['correction_hash'])) {
		            $code_in[] = $code_array['correction_hash'];
		            $code_out[] = $code." - ".(strlen($code_array['descr']) > 30 ? substr($code_array['descr'],0,27)."..." : $code_array['descr']);
		        }
		    }
		}

        $customer_invoice = new customer_invoice();
        if ($customer_invoice->fetch_invoice_record($invoice_hash)) {
            $total_items = count($this->current_invoice['line_items']);
            $lines = new line_items($customer_invoice->proposal_hash,$customer_invoice->current_invoice['punch']);

            for ($i = 0; $i < count($customer_invoice->current_invoice['line_items']); $i++) {
                $lines->fetch_line_item_record($customer_invoice->current_invoice['line_items'][$i]);
                $item_exists = true;

			    $item_tbl .= "
			    <tr style=\"background-color:#ffffff;\">
			        <td style=\"width:22px;background-color:#efefef;vertical-align:bottom;\">
			            ".$this->form->checkbox("name=item[]",
			                                    "value=".$customer_invoice->current_invoice['line_items'][$i],
			                                    "id2=ice",
			                                    "onClick=toggle_display('i_".$customer_invoice->current_invoice['line_items'][$i]."',(this.checked ? 'block' : 'none'));tally()").
			            $this->form->hidden(array("item_tax[".$lines->current_line['item_hash']."]" => $lines->fetch_invoice_tax($customer_invoice->invoice_hash,$lines->current_line['item_hash']),"item_tax_actual[".$lines->current_line['item_hash']."]" => 0))."
			        </td>
			        <td style=\"300px;border-right:1px solid #cccccc;vertical-align:bottom;\">".(strlen($lines->current_line['item_product']." : ".$lines->current_line['item_descr']) > 50 ?
			            substr($lines->current_line['item_product']." : ".$lines->current_line['item_descr'],0,48)."..." : $lines->current_line['item_product']." : ".$lines->current_line['item_descr'])."
			        </td>
			        <td style=\"text-align:right;vertical-align:bottom;\">".($lines->current_line['ext_sell'] < 0 ?
			            "($".number_format($lines->current_line['ext_sell'] * -1,2).")" : "$".number_format($lines->current_line['ext_sell'],2)).
			            $this->form->hidden(array("item_sell[".$lines->current_line['item_hash']."]" => $lines->current_line['ext_sell']))."
			        </td>
                    <td style=\"text-align:right;vertical-align:bottom;\">".
                        $this->form->text_box("name=item_credit[{}]",
                                               "value=",
                                               "style=width:70px;")."
                    </td>
                    <td>

                    </td>
			    </tr>";
			}

            if (!$item_exists)
                $item_tbl .= "
                <tr>
                    <td style=\"background-color:#ffffff;border-top:1px solid #cccccc;\" colspan=\"5\">No available line items.</td>
                </tr>";

        } else
            $item_tbl = "
            <tr>
                <td style=\"background-color:#ffffff;\" colspan=\"5\">Unable to fetch invoice record.</td>
            </tr>";

        $tbl = "
        <div style=\"width:600px;".(count($customer_invoice->current_invoice['line_items']) > 4 ? "height:150px;overflow-y:auto;" : NULL)."overflow-x:hidden;\">
            <table style=\"width:600px;background-color:#8f8f8f;\" cellspacing=\"0\" cellpadding=\"3\">
                <tr>
                    <td style=\"background-color:#efefef;width:22px;border-bottom:1px solid #cccccc;border-right:1px solid #cccccc;text-align:center;\">
                        ".$this->form->checkbox("name=",
                                                "onClick=checkall(document.getElementsByName('ap_invoice_item[]'),this.checked);tally_totals();".($this->credit_hash ? "\$('changed').value=1;" : NULL))."
                    </td>
                    <td style=\"background-color:#efefef;border-bottom:1px solid #cccccc;border-right:1px solid #cccccc;200px\">Item</td>
                    <td style=\"background-color:#efefef;border-bottom:1px solid #cccccc;text-align:right;width:90px;\">Ext Sell</td>
                    <td style=\"background-color:#efefef;border-bottom:1px solid #cccccc;text-align:right;width:90px;\">Credit</td>
                    <td style=\"background-color:#efefef;border-bottom:1px solid #cccccc;text-align:right;width:168px;\">Code</td>
                </tr>
                ". $item_tbl ."
            </table>
        </div>";

        if ($args)
            return $tbl;

        $this->content['html']['cc_item_list_holder'] = $tbl;

    }

    /**
     * customer_credits::proposal_credits
     * Table display showing customer credits associated to this proposal
     *
     * @param  integer  $local  If output needed as return value set to 1
     * @return string   $tbl    Only returns this value if $local is set
     */

    function proposal_credits($local=NULL) {

        $order = $this->ajax_vars['order'];
        $order_dir = $this->ajax_vars['order_dir'];

        if ( $proposal_hash = $this->ajax_vars['proposal_hash'] ) {

            $proposals = new proposals($this->current_hash);
            if ( ! $proposals->fetch_master_record($proposal_hash) )
                return $this->__trigger_error("System error encountered when trying to lookup proposal for invoice display. Please reload window and try again.", E_USER_ERROR, __FILE__, __LINE__, 1);

            $this->get_total( array(
                'proposal_hash' =>  $proposal_hash
            ) );
        }

        $order_by_ops = array(
            "credit_no"    =>  "customer_credit.credit_no",
            "credit_date"  =>  "customer_credit.credit_date"
        );

        if ( $this->total ) {

	        $this->fetch_customer_credits(
	            0,
	            $order_by_ops[ ( $order ? $order : $order_by_ops['invoice_no'] ) ],
	            $order_dir
	        );
        }

        $tbl .= "
        <table class=\"tborder\" cellspacing=\"0\" cellpadding=\"8\" style=\"width:100%;\" >
            <tr>
                <td style=\"font-weight:bold;vertical-align:middle;\" colspan=\"4\">" .
                    ( $this->total ? "
                        Showing 1 - {$this->total} of {$this->total} proposal credits" : NULL
                    ) . "
                    <div style=\"padding-left:5px;padding-top:8px;font-weight:normal;\">" .
                    ( $this->p->ck(get_class($this), 'A') ? "
                        <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits', 'sf_loadcontent', 'show_popup_window', 'credit_menu', 'proposal_hash={$this->proposal_hash}', 'popup_id=credit_menu', 'proposal_no={$proposals->current_proposal['proposal_no']}');\"><img src=\"images/new_customer_invoice.gif\" title=\"Issue Credit\" border=\"0\" /></a>&nbsp;" : "&nbsp;"
                    ) . "
                    </div>
                </td>
            </tr>
            <tr class=\"thead\" style=\"font-weight:bold;\">
                <td style=\"width:35%;vertical-align:bottom;\">
                    <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','cf_loadcontent','proposal_credits','otf=1','proposal_hash={$this->proposal_hash}','order=credit_no','order_dir=" . ( $order == "credit_no" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "');\" style=\"color:#ffffff;text-decoration:underline;\">Credit No</a>" .
                    ( $order == 'credit_no' ?
                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                    ) . "
                </td>
                <td style=\"width:25%;vertical-align:bottom;\" nowrap>
                    <a href=\"javascript:void(0);\" onClick=\"agent.call('customer_credits','sf_loadcontent','cf_loadcontent','proposal_credits','otf=1','proposal_hash={$this->proposal_hash}','order=credit_date','order_dir=" . ( $order == "credit_date" && $order_dir == 'ASC' ? 'DESC' : 'ASC' ) . "');\" style=\"color:#ffffff;text-decoration:underline;\">Credit Date</a>" .
                    ( $order == 'credit_date' ?
                        "&nbsp;<img src=\"images/" . ( $order_dir == 'ASC' ? "s_asc.png" : "s_desc.png" ) . "\">" : NULL
                    ) . "
                </td>
                <td style=\"width:20%;vertical-align:bottom;padding-right:10px;\" class=\"num_field\">Amount</td>
                <td style=\"width:20%;vertical-align:bottom;padding-right:10px;\" class=\"num_field\">Balance</td>
            </tr>";

            for ( $i = 0; $i < $this->total; $i++ ) {

            	if ( $this->credit_info[$i]['invoice_hash'] )
                	$onClick = "agent.call('customer_credits','sf_loadcontent','show_popup_window','invoice_credits','proposal_hash={$this->proposal_hash}','invoice_hash={$this->credit_info[$i]['invoice_hash']}','credit_hash={$this->credit_info[$i]['credit_hash']}','popup_id=credit_win');";
            	else
            	    $onClick = "agent.call('customer_credits','sf_loadcontent','show_popup_window','edit_credit','credit_hash={$this->credit_info[$i]['credit_hash']}','proposal_hash={$this->proposal_hash}','popup_id=prop_credit_win');";

                $tbl .= "
                <tr " . ( $this->p->ck(get_class($this), 'V') ? "onClick=\"$onClick\" onMouseOver=\"this.className='item_row_over'\" onMouseOut=\"this.className=''\"" : NULL ) . ">
                    <td style=\"vertical-align:bottom;text-align:left;" . ( $i < $this->total - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "\" class=\"smallfont\">{$this->credit_info[$i]['credit_no']}</td>
                    <td style=\"vertical-align:bottom;" . ( $i < $this->total - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "\" class=\"smallfont\">" . date(DATE_FORMAT, strtotime($this->credit_info[$i]['credit_date']) ) . "</td>
                    <td style=\"vertical-align:bottom;text-align:right;" . ( $i < $this->total - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "\" class=\"smallfont\">\$" . number_format($this->credit_info[$i]['amount'], 2) . "</td>
                    <td style=\"vertical-align:bottom;text-align:right;" . ( $i < $this->total - 1 ? "border-bottom:1px solid #cccccc;" : NULL ) . "\" class=\"smallfont\">\$" . number_format($this->credit_info[$i]['balance'], 2) . "</td>
                </tr>";
            }

            if ( ! $this->total )
                $tbl .= "
                <tr >
                    <td class=\"smallfont\" colspan=\"4\">You have no customer credits to display under this proposal. </td>
                </tr>";

            $tbl .= "
        </table>";

        if ( $local )
            return $tbl;

        $this->content['html']['tcontent6_2'] = $tbl;
        return;
    }

    /**
     * Query the customer_credit_invoice table and count total invoices according to parameters
     *
     * @param  array    $param  Associative array with index list identical to __construct
     */

    function get_total() {

        if ( $param = func_get_arg(0) ) {

            $this->proposal_hash = $param['proposal_hash'];
            $this->invoice_hash = $param['invoice_hash'];
            $this->customer_hash = $param['customer_hash'];
            $sql = $param['sql'];
        }

        $result = $this->db->query("SELECT COUNT(*) AS Total
                                    FROM customer_credit
                                    WHERE " .
                                    ( $this->customer_hash ? "
                                        customer_hash = '{$this->customer_hash}' AND " : NULL
                                    ) .
                                    ( $this->proposal_hash ? "
                                        proposal_hash = '{$this->proposal_hash}' AND " : NULL
                                    ) .
                                    ( $this->invoice_hash ? "
                                        invoice_hash = '{$this->invoice_hash}' AND " : NULL
                                    ) .
                                    ( $sql ?
                                        "$sql AND " : NULL
                                    ) . "customer_credit.deleted = 0");

        $this->total = $this->db->result($result, 0, 'Total');
    }

    /**
     * Description
     *
     * @param  Place    $where  Where something interesting takes place
     * @param  integer  $repeat How many times something interesting should happen
     * @throws Some_Exception_Class If something interesting cannot happen
     * @return Status
     */

    function fetch_credit_item($credit_hash, $item_hash) {

        $this->current_credit_item = array();
        $r = $this->db->query("SELECT
                                    t1.expense_hash,
                                    t1.credit_hash,
                                    t1.correction_code,
                                    t1.item_hash,
                                    t1.amount,
                                    t1.tax,
                                    t1.memo,
                                    t2.code,
                                    t2.descr,
                                    t3.account_hash,
                                    t3.account_name,
                                    t3.account_no,
                                    t4.account_hash AS expense_account_hash,
                                    t4.account_name AS expense_account_name,
                                    t4.account_no AS expense_account_no,
                                    t5.item_no,
                                    t5.item_descr,
                                    t5.income_account_hash,
                                   ROUND( t5.sell * t5.qty, 2) AS total_sell
                               FROM customer_credit_expenses t1
                               LEFT JOIN correction_codes t2 ON t2.correction_hash = t1.correction_code
                               LEFT JOIN accounts t3 ON t3.account_hash = t2.account_hash
                               LEFT JOIN accounts t4 ON t4.account_hash = t1.account_hash
                               LEFT JOIN line_items t5 ON t5.item_hash = t1.item_hash
                               WHERE t1.credit_hash = '$credit_hash' AND t1.item_hash = '$item_hash'
                               GROUP BY t1.item_hash");

        if ( $row = $this->db->fetch_assoc($r) ) {

            if ( ! $row['account_hash'] ) {

                $row['account_hash'] = $row['expense_account_hash'];
                $row['account_name'] = $row['expense_account_name'];
                $row['account_no'] = $row['expense_account_no'];
            }

            $row['account_name'] = ( trim($row['account_no']) ? trim($row['account_no']) . " : " . stripslashes($row['account_name']) : stripslashes($row['account_name']) );

        	$this->current_credit_item = $row;
        	$this->credit_item_hash = $item_hash;
        	$this->credit_expense_hash = $row['expense_hash'];

        	return true;
        }

        return false;
    }

    /**
     * Description
     *
     * @param  Place    $where  Where something interesting takes place
     * @param  integer  $repeat How many times something interesting should happen
     * @throws Some_Exception_Class If something interesting cannot happen
     * @return Status
     */

    function fetch_credit_items($credit_hash) {

    	$credit_item = array();
        $result = $this->db->query("SELECT t1.item_hash
                                    FROM customer_credit_expenses t1
                                    WHERE t1.credit_hash = '$credit_hash' AND ! ISNULL( t1.item_hash )
                                    ORDER BY t1.obj_id ASC");
        while ( $row = $this->db->fetch_assoc($result) )
            $credit_item[] = $row['item_hash'];

        return $credit_item;
    }

    /**
     * Description
     *
     * @param  Place    $where  Where something interesting takes place
     * @param  integer  $repeat How many times something interesting should happen
     * @throws Some_Exception_Class If something interesting cannot happen
     * @return Status
     */

    function credit_menu() {

        $this->popup_id = $this->content['popup_controls']['popup_id'] = $this->ajax_vars['popup_id'];
        $this->content['popup_controls']['popup_title'] = "Issue Proposal Credit";

        $this->content['popup_controls']['popup_width'] = "425px;";
        $this->content['popup_controls']['popup_height'] = "325px;";
        $proposal_hash = $this->ajax_vars['proposal_hash'];

        $inv_list = "
        <table cellpadding=\"4\" cellspacing=\"0\" style=\"width:185px;\">
        <tr>
            <td colspan=\"2\" style=\"padding-bottom:5px;\"><i>Select Invoice Below</i></td>
        </tr>
        <tr>
        <td style=\"border-bottom:1px solid #cccccc;\">Invoice</td>
        <td style=\"padding-left:15px;border-bottom:1px solid #cccccc;\">Date</td>
        </tr>";

        $num = 0;
        $r = $this->db->query("SELECT
                                   t1.invoice_no,
                                   t1.invoice_date,
                                   t1.invoice_hash
                               FROM customer_invoice t1
                               WHERE t1.proposal_hash = '$proposal_hash' AND t1.deleted = 0");
        while ( $row = $this->db->fetch_assoc( $r ) ) {

        	$num++;
        	$inv_list .= "
        	<tr>
        	<td ><a href=\"javascript:void(0);\" title=\"Issue credit against this invoice\" style=\"color:#000\" onClick=\"window.setTimeout('popup_windows[\'{$this->popup_id}\'].hide()', 750);agent.call('customer_credits', 'sf_loadcontent', 'show_popup_window', 'invoice_credits', 'proposal_hash=$proposal_hash', 'invoice_hash={$row['invoice_hash']}', 'popup_id=credit_win');\">{$row['invoice_no']}</a></td>
        	<td style=\"padding-left:15px;\">" . date(DATE_FORMAT, strtotime($row['invoice_date']) ) . "</td>
        	";
        }

        $inv_list .= "
        </table>";

        $this->content['popup_controls']["cmdTable"] = "
        <div class=\"panel\" id=\"main_table{$this->popup_id}\">
            <div id=\"feedback_holder{$this->popup_id}\" style=\"background-color:#ffffff;border:1px solid #cccccc;font-weight:bold;padding:5px;display:none;margin-bottom:5px;\">
                 <h3 class=\"error_msg\" style=\"margin-top:0;\">Error!</h3>
                    <p id=\"feedback_message{$this->popup_id}\"></p>
            </div>
            <table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:390px;height:305px;\" class=\"smallfont\">
                <tr>
                    <td class=\"smallfont\" style=\"background-color:#ffffff;vertical-align:top;\">
                        <h5 style=\"margin-bottom:5px;color:#00477f;\">
                            Proposal {$this->ajax_vars['proposal_no']}
                        </h5>
                        <fieldset style=\"margin-top:25px;\">
                            <legend>What type of credit would you like to issue?</legend>
                            <div style=\"padding:10px 5px 5px 10px;\">" .
                                $this->form->radio(
	                                "name=credit_op",
                                    "id=op_g",
	                                "value=G",
	                                "onClick=if(this.checked){window.setTimeout('popup_windows[\'{$this->popup_id}\'].hide()', 750);\$('inv_list').hide();agent.call('customer_credits', 'sf_loadcontent', 'show_popup_window', 'edit_credit', 'proposal_hash=$proposal_hash', 'popup_id=cc_new_win');}",
                                    "style=border:none;"
                                ) . "
                                A blanket credit against this proposal
                                <div style=\"margin:5px 5px 10px 25px;font-style:italic;\">
                                    Can be issued against a proposal, but is not specific to any one invoice or item.
                                </div>" .
                                $this->form->radio(
                                    "name=credit_op",
                                    "id=op_i",
                                    "value=I",
                                    "onClick=if(this.checked){\$('inv_list').show();}",
                                    "style=border:none;",
                                    ( ! $num ? "disabled title=No invoices have been issued against this proposal" : NULL )
                                ) . "
                                Credit against a specific invoice
                                <div style=\"margin:5px 5px 10px 25px;font-style:italic;\">
                                    Is issued against invoiced line items and includes sales tax where applicable
                                </div>
                                <div id=\"inv_list\" style=\"display:none;margin-left:45px;margin-top:5px;\">
                                    $inv_list
                                </div>
                            </div>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>";
    }
}

?>