function findPos(obj,id) {
	var curleft = curtop = 0;
	id = id.toUpperCase();
	if (obj != null && obj.offsetParent) {
		curleft = obj.offsetLeft
		curtop = obj.offsetTop
		while (obj = obj.offsetParent) {
			curleft += obj.offsetLeft
			curtop += obj.offsetTop
		}
	}
	return (id == 'LEFT' ? curleft : curtop);
}
function checkAll() {
	var inputs = document.getElementsByTagName('input');
	var checked = document.getElementById('checked').value;
	if(checked == false){
		document.getElementById('checked').value = true;
	}else{
		document.getElementById('checked').value = "";
	}
	var checkboxes = [];
	for (var i = 0; i < inputs.length; i++) {
		if (inputs[i].type == 'checkbox') {
			if(inputs[i].className == "proposalCheckbox"){
				inputs[i].checked = checked;
			}
		}
	}
}

function mouse_pos(xy) {
	var posx = 0;
	var posy = 0;
	var e = window.event;
	if (e.pageX || e.pageY) 	{
		posx = e.pageX;
		posy = e.pageY;
	}
	else if (e.clientX || e.clientY) 	{
		posx = e.clientX + document.body.scrollLeft
			+ document.documentElement.scrollLeft;
		posy = e.clientY + document.body.scrollTop
			+ document.documentElement.scrollTop;
	}
	return(xy=='x'?posx:posy);
}

function clear_innerHTML() {
	for (var i = 0; i < arguments.length; i++)
		$(arguments[i]).innerHTML = '';

}
function clear_values() {
	for (var i = 0; i < arguments.length; i++)
		$(arguments[i]).value = '';
}
// Is the specified select-list behind the calendar?
function BehindCal(SelectList, CalLeftX, CalRightX, CalTopY, CalBottomY, ListTopY) {
   var ListLeftX = findPos(SelectList, 'LEFT');
   var ListRightX = ListLeftX + SelectList.offsetWidth;
   var ListBottomY = ListTopY + SelectList.offsetHeight;
   return (((ListTopY < CalBottomY) && (ListBottomY > CalTopY)) && ((ListLeftX < CalRightX) && (ListRightX > CalLeftX)));
}

// For IE, hides any select-lists that are behind the calendar
function FixSelectLists(CalDiv,Over,hiddenFieldName,num_elements) {
	num_elements = (num_elements ? num_elements : 3);
	if (navigator.appName == 'Microsoft Internet Explorer') {
		var CalLeftX = findPos(CalDiv,'left');
		var CalRightX = CalLeftX + CalDiv.offsetWidth;
		var CalTopY = findPos(CalDiv,'top');
		var CalBottomY = CalTopY + (CellHeight * 9);
		var FoundCalInput = false;
		formLoop :
		for (var j=0;j<document.forms.length;j++) {
			for (var i=0;i<document.forms[j].elements.length;i++) {
				if (typeof document.forms[j].elements[i].type == 'string') {
					if ((hiddenFieldName) && (document.forms[j].elements[i].type == 'hidden') && (document.forms[j].elements[i].name == hiddenFieldName)) {
						FoundCalInput = true;
						i += num_elements; // 3 elements between the 1st hidden field and the last year input field
					}
					if (!hiddenFieldName || FoundCalInput) {
						if (document.forms[j].elements[i].type.substr(0,6) == 'select') {
							//alert(document.forms[j].elements[i].name);
							ListTopY = findPos(document.forms[j].elements[i],'top');
							//alert('checking: '+document.forms[j].elements[i]);
							if (ListTopY < CalBottomY) {
								if (BehindCal(document.forms[j].elements[i], CalLeftX, CalRightX, CalTopY, CalBottomY, ListTopY)) {
									document.forms[j].elements[i].style.visibility = (Over) ? 'hidden' : 'visible';
								}
							}
							else break formLoop;
						}
					}
				}
			}
		}
	}
}
//This function selects an item from the key results and fills both the name and id (hidden)field
function selectItem() {
	for (var i = 0; i < arguments.length; i++) {
		var p_name = arguments[i].split('=');
		if ($(p_name[0]))
			$(p_name[0]).value = p_name[1];
	}
}

function toggle_display(d_area,type) {
	$(d_area).setStyle({display:type});
}
function fill_input() {
	for (var i = 0; i < arguments.length; i++) {
		var p_name = arguments[i].split('=');
		$(p_name[0]).value = $F(p_name[1]);
	}
}
function tier_action(el_name,el_value) {
	if (el_value.indexOf(',') != -1)
		el_value = el_value.replace(',','');

	el_value = parseInt(el_value);
}
function formatNumber(num) {

    if ( ! num )
        num = '';

	if ( /[^-.0-9]/.test( num.toString() ) )
        num = num.toString().replace(/[^-.0-9]/g,'');

    if ( isNaN(num) )
        num = '';

    if ( num )
        num = parseFloat(num);

    return num;
}
function formatCurrency(num,add) {
	if (!num)
		return '';
	num = num.toString().replace(/\$|\,/g,'');
	if(isNaN(num))
		num = "0";
	sign = (num == (num = Math.abs(num)));
	num = Math.floor(num*100+0.50000000001);
	cents = num%100;
	if (add) {
		add = parseFloat(add);
		cents = (cents+add)*100;
	}
	num = Math.floor(num/100).toString();

	if(cents<10)
		cents = "0" + cents;
	for (var i = 0; i < Math.floor((num.length-(1+i))/3); i++)
		num = num.substring(0,num.length-(4*i+3))+','+
	num.substring(num.length-(4*i+3));

	return( (((sign)?'':'-') + num + '.' + cents));
}
function chooseFile() {
	var iFrameObj = $('FileIframe');
	var fileChooser = iFrameObj.contentWindow.$('fileChooser');
	fileChooser.click();
}
function checkall(obj,bool) {
	var i=0, c;
	while (c = obj[i++]) {
		if (!c.disabled)
		c.checked = (bool ? bool : (c.checked ? false : true));
	}
}
imgout=new Image(9,9);
imgin=new Image(9,9);

imgout.src="images/collapse.gif";
imgin.src="images/expand.gif";

//this switches expand collapse icons
function filter(imagename,objectsrc){
	if (document.images){
		if (document.images[imagename])
			document.images[imagename].src=eval(objectsrc+".src");
	}
}

//show OR hide funtion depends on if element is shown or hidden
function shoh(id) {
	if (document.getElementById) { // DOM3 = IE5, NS6
		if ($(id).style.display == "none"){
			$(id).style.display = 'block';
			filter(("img"+id),'imgout');
			if ($('shoh_remember_'+id))
				$('shoh_remember_'+id).value = 1;

		} else {
			filter(("img"+id),'imgin');
			$(id).style.display = 'none';
			if ($('shoh_remember_'+id))
				$('shoh_remember_'+id).value = 0;
		}
	} else {
		if (document.layers) {
			if (document.id.display == "none"){
				document.id.display = 'block';
				filter(("img"+id),'imgin');
				if ($('shoh_remember_'+id))
					$('shoh_remember_'+id).value = 1;
			} else {
				filter(("img"+id),'imgout');
				document.id.display = 'none';
				if ($('shoh_remember_'+id))
					$('shoh_remember_'+id).value = 0;
			}
		} else {
			if (document.all.id.style.visibility == "none"){
				document.all.id.style.display = 'block';
				if ($('shoh_remember_'+id))
					$('shoh_remember_'+id).value = 1;
			} else {
				filter(("img"+id),'imgout');
				document.all.id.style.display = 'none';
				if ($('shoh_remember_'+id))
					$('shoh_remember_'+id).value = 0;
			}
		}
	}
}

function shohloop(id) {
	var i=0;
	while ($(id+'['+(++i)+']')) {
		if (document.getElementById) { // DOM3 = IE5, NS6
			if ($(id+'['+(i)+']').style.display == "none"){
				$(id+'['+(i)+']').style.display = 'block';
				filter(("img"+id),'imgin');
			} else {
				filter(("img"+id),'imgout');
				$(id+'['+(i)+']').style.display = 'none';
			}
		}
	}
	return;
}
function getCheckedItems(item_name) {
	var items = document.getElementsByName(item_name);
	var ary = new Array();
	for (var i = 0; i < items.length; i++) {
		if (items[i].checked)
			ary.push(item_name+'='+URLEncode(items[i].value));
	}
	return ary;
}
function getCheckedItemsAsString(item_name) {
	var items = document.getElementsByName(item_name);
	var ary = "line_item=";
	for (var i = 0; i < items.length; i++) {
		if (items[i].checked)
			if(items.length > 1){
				ary += URLEncode(items[i].value) + ",";
			}else{
				ary += URLEncode(items[i].value);
			}
	}
	return ary;
}
function show_wait_icon() {
	var wait_icon = $('ajax_progress');
	var top_pos = document.documentElement.scrollTop;
	if (!top_pos)
		top_pos = 0;

	wait_icon.setStyle({top : top_pos + 25 + 'px'});
	toggle_display('ajax_progress','block');

}
function show_page_feedback(feedback) {
    $('page_feedback_holder').setStyle({top : document.documentElement.scrollTop + 25 + 'px'});

	$('page_feedback_holder').innerHTML = feedback;
	toggle_display('page_feedback_holder','block');
	window.setTimeout("toggle_display('page_feedback_holder','none')",(feedback.length > 75 ? 8000 : 4000));
}
function toggle_submit(id,lock) {
	if ($(id))
		$(id).disabled = lock;
}
function remove_el(obj) {
	var p = obj.parentElement;
    var children = $(p).childElements();
    if ( children ) {
        var num_child = 0;
        children.each(function(item) {
            if ( item && $(item) ) {
                num_child++;
            }
        });
		if (num_child == 1) {
            $(p).style.display = 'none';
		}
    }
	p.removeChild(obj);
}
function remove_element(par_id,el_id) {
	var par = $(par_id);
	if (el_id)
		par.removeChild($(el_id));
	else
		par.removeChild(par.firstChild);
	return;
}
function create_element(par_id,type) {
	var iframe = document.createElement(type);

	if (arguments.length > 2) {
		for (var i = 2; i < arguments.length; i++) {
			var attr = arguments[i].split('=');
			if (attr.length > 2) {
				var attr_val = '';
				for (var j = 1; j < attr.length; j++)
					attr_val += attr[j]+(j < attr.length-1 ? '=' : '');
			} else
				attr_val = attr[1];


			if (attr_val)
				iframe.setAttribute(attr[0],attr_val);

		}
	}
	$(par_id).appendChild(iframe);

	return;
}
function get_value(id) {
	var el = $(id);
	if (el.type == 'radio') {
		var name = el.name;
		var el_array = document.getElementsByName(name);
		if (el_array.length > 1) {
			for (var i = 0; i < el_array.length; i++) {
				if (el_array[i].checked)
					var val = el_array[i].value;
			}
		} else
			var val = el.value;
	} else if (el.type == 'select-multiple') {
		var val_ar =  new Array();
		for (var j = 0; j < el.length; j++) {
			if (el[j].selected)
				val_ar.push(el[j].value);
		}
		var val = val_ar.join('|');
	} else
		var val = el.value

	return val;
}

function permission_check_box(obj) {
	var val = obj.value;
	var bool = obj.checked;
	var obj = document.getElementsByName(obj.name);

	var i=0, c;
	while (c = obj[i++]) {
		if (c.value.substring(0,1) == val)
			c.checked = bool;
	}
}
var screenW = 640, screenH = 480;
var collapse1 = '';

function startup() {
	if (parseInt(navigator.appVersion)>3) {
	 screenW = screen.width;
	 screenH = screen.height;
	}
	else if (navigator.appName == "Netscape"
		&& parseInt(navigator.appVersion)==3
		&& navigator.javaEnabled()
	   )
	{
	 var jToolkit = java.awt.Toolkit.getDefaultToolkit();
	 var jScreenSize = jToolkit.getScreenSize();
	 screenW = jScreenSize.width;
	 screenH = jScreenSize.height;
	}
	Event.observe(document.body,'keydown',function(event){keypressHandler(event)},true);
	css_browser_selector(navigator.userAgent);
}
function keypressHandler (event) {
	var key = event.which || event.keyCode;
	switch (key) {
		//case Event.KEY_RIGHT:
		//alert('moved right');
		//break;

		//case Event.KEY_LEFT:
		//alert('moved left');
		//break;
		//
		case Event.KEY_BACKSPACE:
		break;

		case Event.KEY_RETURN:
		if (ka == true && parseInt($F('key_result_holder')) >= 0) {
            var int_to_select = parseInt($F('key_result_holder'));
            if (int_to_select == 0)
                int_to_select = 1;

            if ($('key_result_'+int_to_select)) {
                var el_to_select = $('key_result_'+int_to_select);
                var evalStr= el_to_select.getAttribute('onclick').toString();
                if ( navigator.appName == 'Microsoft Internet Explorer' ) {

                    if ( parseIEAnon = evalStr.match(/^function (?:.*)\(\)\n{\n(.*)\n}$/) ) {
	                    evalStr = parseIEAnon[1];
	                    if (evalStr.match('selectItem')) {
	                    	var s = evalStr.substr(evalStr.indexOf("selectItem")+12);
	                    	if($(s.substr(0,s.indexOf('='))))
	                    	    $(s.substr(0,s.indexOf('='))).blur();

	                    }
	                    if ( evalStr ) {
	                        eval(evalStr);
	                    }
	                }
                }
            }
            $('keyResults').setStyle({display:'none'});
            return;
		}
		if ($('primary')) {
			var evalStr= $('primary').getAttribute('onclick').toString();
			if ( navigator.appName == 'Microsoft Internet Explorer' ) {

				if ( parseIEAnon = evalStr.match(/^function (?:.*)\(\)\n{\n(.*)\n}$/) ) {
				    evalStr = parseIEAnon[1];
				    if ( evalStr ) {
				        eval(evalStr);
				    }
				}
			}
		}
		break;

		case Event.KEY_DOWN:
		case Event.KEY_UP:
		var current_key_value = parseInt($F('key_result_holder'));
		if (key==Event.KEY_DOWN)
			var next_selected = current_key_value + 1;
		else
		    var next_selected = current_key_value - 1;

		if (current_key_value > 0) {
			var current_selected = 'key_result_'+$F('key_result_holder');
			if ($('key_result_'+next_selected))
				$(current_selected).className = 'suggest_link';
		}
		if ($('key_result_'+next_selected)) {
			$('key_result_'+next_selected).className = 'suggest_link_over';
            if (q_input) {
                vt_val = escape($('key_result_'+next_selected).readAttribute('innerval'));
                q_input.value = $('key_result_'+next_selected).readAttribute('innerval');
            }
			$('key_result_holder').value = next_selected;
		}
		break;

		case Event.KEY_TAB:
		if (ka == true) {
			var int_to_select = parseInt($F('key_result_holder'));
            if (int_to_select == 0)
                int_to_select = 1;

            if ( $('key_result_'+int_to_select) ) {
                var el_to_select = $('key_result_'+int_to_select);
                var evalStr= el_to_select.getAttribute('onclick').toString();
                if ( navigator.appName == 'Microsoft Internet Explorer' ) {
                    if ( parseIEAnon = evalStr.match(/^function (?:.*)\(\)\n{\n(.*)\n}$/) ) {
                    	evalStr = parseIEAnon[1];
                    	if ( evalStr ) {
                    		eval(evalStr);
                    	}
                    }
                }
            }
            $('keyResults').setStyle({display:'none'});
		}
		break;

	}
}

function isNumeric(s) {
	var validChars = '0123456789.';
	var isNumber = true;
	var Char;

	for (var i = 0; i < s.length && isNumber == true; i++) {
	    Char = s.charAt(i);
	    if (validChars.indexOf(Char) == -1)
	        isNumber = false;
	}

	return isNumber;
}

function calculate_balance(obj,id) {
    var r = $$('input['+id+']');
    var j = 0;
    var diff = 0;
    var val = 0;

    for (var i = 0; i < r.length; i++) {
        if ($F(r[i])) {
            if (j + parseFloat($F(r[i])) > 100) {
                diff = 100 - j;
                $(r[i]).value = (diff != 0 ? diff : '');
                j = 100;
            } else {
            	if ($(r[i]) != obj)
                    j += parseFloat($F(r[i]));
            }
        }
    }
    val = (100 - j);
    $(obj).value = (val != 0 ? val : '');
}

function commission_split() {
    var proposal = arguments[0];
    var proposal_hash = arguments[1];
    var t_r = $$('input[cid=com_rate]');
    for (var i = 0; i < t_r.length; i++) {
        var val = parseFloat($(t_r[i]).value);
        if (!val || val == 0) {
            alert('Please enter a commission rate before adding additional commission users.');
            return false;
        }
    }
    agent.call('system_config','sf_loadcontent','cf_loadcontent','doit_commission','method=commission_team_members','proposal='+(proposal?proposal:0),'proposal_hash='+(proposal_hash?proposal_hash:0));
}

function commission_user_rm(rand_id) {
    var comm_row = 'comm_row_' + rand_id;
    var comm_hidden = $$('#commission_team_members_proposal input');
    for (var i = 0; i < comm_hidden.length; i++) {
        if (comm_hidden[i].type == 'hidden' && comm_hidden[i].value == rand_id)
            comm_hidden[i].value = 0;
    }
    $(comm_row).remove();
}

var lineItemValidator = Class.create({

	initialize: function() {
 		if (arguments.length < 2)
 			return;

        var fieldList = arguments[0];
        var eventList = arguments[1];
		if (typeof fieldList == 'undefined')
			return;

        this.popup_id = $F('popup_id') || null;
        this.fieldList = [];
        for (var i = 0; i < fieldList.length; i++) {

            if ( $(fieldList[i]) && $(fieldList[i]).type != 'hidden' ) {

				eventObserve = eventList[i].split('|');
				for (var j = 0; j < eventObserve.length; j++) {

					$(fieldList[i]).observe(eventObserve[j], this.validateItem.bind(this));
					this.fieldList.push(fieldList[i] + '|' + eventObserve[j]);
				}
			}
        }
	},
	stopListening: function() {

        var fieldList = arguments[0];
        var eventList = arguments[1];
        if ( typeof fieldList == 'undefined' )
            return;

		for ( var i = 0; i < fieldList.length; i++ ) {
            if ($(fieldList[i])) {
				eventObserve = eventList[i].split('|');
				for (var j = 0; j < eventObserve.length; j++) {
					$(fieldList[i]).stopObserving(eventObserve[j]);
				}
			}
		};
	},
	validateItem: function(event) {

        var profit, __sell, discount1, discount2, discount3, discount4, discount5;
		var qty = formatNumber( $F('qty') );
		var list = this.convertFlt( $F('list') );

		if ( discount1 = formatNumber( $F('discount1') ) )
    		discount1 *= .01;
		if ( discount2 = formatNumber( $F('discount2') ) )
    		discount2 *= .01;
		if ( discount3 = formatNumber( $F('discount3') ) )
    		discount3 *= .01;
		if ( discount4 = formatNumber( $F('discount4') ) )
    		discount4 *= .01;
		if ( discount5 = formatNumber( $F('discount5') ) )
    		discount5 *= .01;

		var cost = this.convertFlt( $F('cost') );
		var gp_margin = formatNumber( $F('gp_margin') );
		var list_discount = formatNumber( $F('list_discount') );
		var sell = __sell = this.convertFlt( $F('sell') );
		var from_edit = $F('from_edit');
		var booked = $F('booked');
		var popup_id = $F('popup_id');
		var MARGIN_FLAG = this.convertFlt( $F('MARGIN_FLAG') );

        var evel = $( Event.element(event) );
		var evtp = event.type;

		if ( list ) {

			if ( evel.name != 'cost' && discount1 != null && discount1 != 0) {

				cost = this.round(list - (list * discount1), 4);
				if ( discount2 != null && discount2 != 0) {
					cost = this.round(cost - (cost * discount2), 4);
					if ( discount3 != null && discount3 != 0) {
						cost = this.round(cost - (cost * discount3), 4);
						if ( discount4 != null && discount4 != 0) {
							cost = this.round(cost - (cost * discount4), 4);
							if ( discount5 != null && discount5 != 0) {
								cost = this.round(cost - (cost * discount5), 4);
							}
						}
					}
				}

				cost = this.round(cost, 2);
			}
		}

		if ( cost != null && gp_margin ) {

            if ( evel.name != 'cost' ) {

    			if ( gp_margin >= 100 )
					gp_margin = 0;

				sell = this.GPMargin(cost, null, gp_margin);

				if ( $('sell').type != 'hidden' ) {

					//if ( qty )
						//$('extended_sell' + popup_id).innerHTML = this.round(sell * qty, 2);
					if ( evel.name != 'sell' )
						$('sell').value = formatCurrency( this.round(sell, 2) );
				}
			}

			//$('gp_margin' + popup_id).innerHTML =
			//( MARGIN_FLAG > 0 && gp_margin * .01 < MARGIN_FLAG ?
				//"<span style=\"color:red;\">" : ''
            //) + gp_margin + ' %' +
			//( MARGIN_FLAG > 0 && gp_margin * .01 < MARGIN_FLAG ?
				//"</span>" : ''
            //);

		} else {

            if ( evel.name != 'sell' ) {

	            if ( cost && gp_margin === 0 )
	                sell = cost;

	            if ( list != null && list_discount && evel.name != 'sell' && $('sell').type != 'hidden' )
	    			sell = this.list_discount(list, 0, list_discount);

	    		$('sell').value = formatCurrency(sell);
            }
		}

        if ( cost != null ) {

            if ( evel.name == 'cost' ) {

                cost = $F('cost');
				if ( $F('cost') && event.type == 'change' ) {

					if (discount1) {
						$('discount1').value = '';
					}
					if (discount2) {
						$('discount2').value = '';
					}
					if (discount3) {
						$('discount3').value = '';
					}
					if (discount4) {
						$('discount4').value = '';
					}
					if (discount5) {
						$('discount5').value = '';
					}

					$('gp_margin').value = '';
				}
			} else {

                if ( cost ) {
                    $('cost').value = formatCurrency(this.round(cost, 2));
                }
			}

            //if ( qty != null ) {
                //$('extended_cost' + popup_id).innerHTML = '$' + formatCurrency(this.round(qty * cost, 2));
            //}

            if ( sell != null ) {

				gp_margin = this.round(this.GPMargin(cost, sell) * 100, 4);
                if ( gp_margin > 0 && ! list_discount && evel.name != 'cost' && evel.name != 'sell' && evel.name != 'gp_margin' && $('sell').type != 'hidden' ) {
                    $('gp_margin').value = gp_margin;
                }

                //$('gp_margin' + popup_id).innerHTML =
                //( MARGIN_FLAG > 0 && ( gp_margin * .01 ) < MARGIN_FLAG ?
					//"<span style=\"color:red;\">" +
					//( gp_margin < 0 ?
					    //"(" +
					    //( gp_margin < -100 ?
					        //"100" : gp_margin * -1
					    //) + " %)" : gp_margin + " %"
					//) +
                    //"</span>" : gp_margin + " %"
                //);

                if ( qty ) {

                    profit = this.round(( sell * qty ) - ( cost * qty ), 2);
                    //$('extended_sell' + popup_id).innerHTML = '$' + formatCurrency(this.round(sell * qty, 2));
                    //if ( profit != 0 ) {

	                    //$('profit_dollars' + popup_id).innerHTML =
	                    //( profit < 0 ?
	                        //"<span style=\"color:red;\">($" + formatCurrency(this.round(profit * -1, 2)) + ")" : '$' + formatCurrency(this.round(profit, 2))
	                    //);
	                //}
                }
            } //else {

                //if ( gp_margin == null ) {

	               // $('extended_sell' + popup_id).innerHTML = '';
	               // $('profit_dollars' + popup_id).innerHTML = '';
	               // $('gp_margin' + popup_id).innerHTML = '';
	            //}
            //}
        }

        if ( evel.name == 'sell' ) {

            //$('gp_margin').value = '';
            sell = __sell;

            if ( cost )
                gp_margin = this.round(this.GPMargin(cost, sell) * 100, 4);

            if ( qty )
                profit = this.round(( sell * qty ) - ( cost * qty ), 2);
        }

        if ( cost && qty )
            $('extended_cost' + popup_id).innerHTML = '$' + formatCurrency( this.round(cost * qty, 2) );
        else
            $('extended_cost' + popup_id).innerHTML = '';

        if ( sell && qty )
            $('extended_sell' + popup_id).innerHTML = '$' + formatCurrency( this.round(sell * qty, 2) )
        else
            $('extended_sell' + popup_id).innerHTML = '';

        if ( gp_margin ) {
            $('gp_margin' + popup_id).innerHTML =
            ( MARGIN_FLAG > 0 && ( gp_margin * .01 ) < MARGIN_FLAG ?
                "<span style=\"color:red;\">" +
                ( gp_margin < 0 ?
                    "(" +
                    ( gp_margin < -100 ?
                        "100" : gp_margin * -1
                    ) + " %)" : gp_margin + " %"
                ) +
                "</span>" : gp_margin + " %"
            );
        } else
            $('gp_margin' + popup_id).innerHTML = '';

        if ( profit ) {
            $('profit_dollars' + popup_id).innerHTML =
            ( profit < 0 ?
                "<span style=\"color:red;\">($" + formatCurrency(this.round(profit * -1, 2)) + ")" : '$' + formatCurrency(this.round(profit, 2))
            );
        } else
            $('profit_dollars' + popup_id).innerHTML = '';

        $('submit_btn').disabled = 0;
	},
	convertFlt: function(flt) {
		if ( flt ) {
			flt = flt.replace(/[^-.0-9]/g, '');
			return parseFloat( flt );
		}
		return null;
	},
	round: function(num, prec) {
        if ( ! prec ) {
            prec = 2;
        }
		var pow = Math.pow(10, parseInt(prec));
		return Math.round(num * pow) / pow;
	},
	GPMargin: function(cost, sell, margin) {
		if ( margin ) {

			var sell = ( cost / ( 100 - margin ) ) * 100;
			return this.round(sell, 4);
		} else {
			if ( sell > 0 ) {
				return this.round(( sell - cost ) / sell, 4);
			}
            if ( ! cost && ! sell ) {

                return 0;
            }
		}

        return -1;
	},
	list_discount: function(list, sell, disc) {
        if ( disc ) {

            sell = list - ( list * ( disc * .01 ) );
            return this.round(sell, 4);
        } else {

            disc = ( ( sell / list ) * 100 ) - 100;
            return this.round(disc * -1, 4);
        }
	}
});

function validateItem() {

    var profit, __sell, discount1, discount2, discount3, discount4, discount5;
	var qty = formatNumber( $F('qty') );
	var list = this.convertFlt( $F('list') );

	if ( discount1 = formatNumber( $F('discount1') ) )
		discount1 *= .01;
	if ( discount2 = formatNumber( $F('discount2') ) )
		discount2 *= .01;
	if ( discount3 = formatNumber( $F('discount3') ) )
		discount3 *= .01;
	if ( discount4 = formatNumber( $F('discount4') ) )
		discount4 *= .01;
	if ( discount5 = formatNumber( $F('discount5') ) )
		discount5 *= .01;

	var cost = this.convertFlt( $F('cost') );
	var gp_margin = formatNumber( $F('gp_margin') );
	var list_discount = formatNumber( $F('list_discount') );
	var sell = __sell = this.convertFlt( $F('sell') );
	var from_edit = $F('from_edit');
	var booked = $F('booked');
	var popup_id = $F('popup_id');
	var MARGIN_FLAG = this.convertFlt( $F('MARGIN_FLAG') );

	if ( list ) {
		if (discount1 != null) {

			cost = this.round(list - (list * discount1), 4);
			if ( discount2 != null) {
				cost = this.round(cost - (cost * discount2), 4);
				if ( discount3 != null) {
					cost = this.round(cost - (cost * discount3), 4);
					if ( discount4 != null) {
						cost = this.round(cost - (cost * discount4), 4);
						if ( discount5 != null) {
							cost = this.round(cost - (cost * discount5), 4);
						}
					}
				}
			}

			cost = this.round(cost, 2);
		}
	}

	if ( cost != null && gp_margin ) {


			if ( gp_margin >= 100 )
				gp_margin = 0;

			sell = this.GPMargin(cost, null, gp_margin);

			if ( $('sell').type != 'hidden' ) {

				//if ( qty )
					//$('extended_sell' + popup_id).innerHTML = this.round(sell * qty, 2);
					$('sell').value = formatCurrency( this.round(sell, 2) );
			}

		//$('gp_margin' + popup_id).innerHTML =
		//( MARGIN_FLAG > 0 && gp_margin * .01 < MARGIN_FLAG ?
			//"<span style=\"color:red;\">" : ''
        //) + gp_margin + ' %' +
		//( MARGIN_FLAG > 0 && gp_margin * .01 < MARGIN_FLAG ?
			//"</span>" : ''
        //);

	} else {


            if ( cost && gp_margin === 0 )
                sell = cost;

            if ( list != null && list_discount && $('sell').type != 'hidden' )
    			sell = this.list_discount(list, 0, list_discount);

    		$('sell').value = formatCurrency(sell);
	}

    if ( cost != null ) {

            if ( cost ) {
                $('cost').value = formatCurrency(this.round(cost, 2));
            }

        //if ( qty != null ) {
            //$('extended_cost' + popup_id).innerHTML = '$' + formatCurrency(this.round(qty * cost, 2));
        //}

        if ( sell != null ) {

			gp_margin = this.round(this.GPMargin(cost, sell) * 100, 4);
            if ( gp_margin > 0 && ! list_discount && $('sell').type != 'hidden' ) {
                $('gp_margin').value = gp_margin;
            }

            //$('gp_margin' + popup_id).innerHTML =
            //( MARGIN_FLAG > 0 && ( gp_margin * .01 ) < MARGIN_FLAG ?
				//"<span style=\"color:red;\">" +
				//( gp_margin < 0 ?
				    //"(" +
				    //( gp_margin < -100 ?
				        //"100" : gp_margin * -1
				    //) + " %)" : gp_margin + " %"
				//) +
                //"</span>" : gp_margin + " %"
            //);

            if ( qty ) {

                profit = this.round(( sell * qty ) - ( cost * qty ), 2);
                //$('extended_sell' + popup_id).innerHTML = '$' + formatCurrency(this.round(sell * qty, 2));
                //if ( profit != 0 ) {

                    //$('profit_dollars' + popup_id).innerHTML =
                    //( profit < 0 ?
                        //"<span style=\"color:red;\">($" + formatCurrency(this.round(profit * -1, 2)) + ")" : '$' + formatCurrency(this.round(profit, 2))
                    //);
                //}
            }
        } //else {

            //if ( gp_margin == null ) {

               // $('extended_sell' + popup_id).innerHTML = '';
               // $('profit_dollars' + popup_id).innerHTML = '';
               // $('gp_margin' + popup_id).innerHTML = '';
            //}
        //}
    }

    if ( cost && qty )
        $('extended_cost' + popup_id).innerHTML = '$' + formatCurrency( this.round(cost * qty, 2) );
    else
        $('extended_cost' + popup_id).innerHTML = '';

    if ( sell && qty )
        $('extended_sell' + popup_id).innerHTML = '$' + formatCurrency( this.round(sell * qty, 2) )
    else
        $('extended_sell' + popup_id).innerHTML = '';

    if ( gp_margin ) {
        $('gp_margin' + popup_id).innerHTML =
        ( MARGIN_FLAG > 0 && ( gp_margin * .01 ) < MARGIN_FLAG ?
            "<span style=\"color:red;\">" +
            ( gp_margin < 0 ?
                "(" +
                ( gp_margin < -100 ?
                    "100" : gp_margin * -1
                ) + " %)" : gp_margin + " %"
            ) +
            "</span>" : gp_margin + " %"
        );
    } else
        $('gp_margin' + popup_id).innerHTML = '';

    if ( profit ) {
        $('profit_dollars' + popup_id).innerHTML =
        ( profit < 0 ?
            "<span style=\"color:red;\">($" + formatCurrency(this.round(profit * -1, 2)) + ")" : '$' + formatCurrency(this.round(profit, 2))
        );
    } else
        $('profit_dollars' + popup_id).innerHTML = '';

    $('submit_btn').disabled = 0;
}

function convertFlt(flt) {
	if ( flt ) {
		flt = flt.replace(/[^-.0-9]/g, '');
		return parseFloat( flt );
	}
	return null;
}

function round(num, prec) {
    if ( ! prec ) {
        prec = 2;
    }
	var pow = Math.pow(10, parseInt(prec));
	return Math.round(num * pow) / pow;
}

function GPMargin(cost, sell, margin) {
	if ( margin ) {

		var sell = ( cost / ( 100 - margin ) ) * 100;
		return this.round(sell, 4);
	} else {
		if ( sell > 0 ) {
			return this.round(( sell - cost ) / sell, 4);
		}
        if ( ! cost && ! sell ) {

            return 0;
        }
	}

    return -1;
}

function list_discount(list, sell, disc) {
    if ( disc ) {

        sell = list - ( list * ( disc * .01 ) );
        return this.round(sell, 4);
    } else {

        disc = ( ( sell / list ) * 100 ) - 100;
        return this.round(disc * -1, 4);
    }
}

function editLineItem(){

    var invoice_lock = arguments[0];
	if (invoice_lock) {

        alert('System settings have been set to prevent invoiced line items from being edited.');
		return;
	}

	var all_input = document.getElementsByTagName('input');
	var all_select = document.getElementsByTagName('select');
	var all_textarea = document.getElementsByTagName('textarea');
	  
	if ( document.getElementById('item_no').disabled ) {
		
		for(i=0;i<all_input.length;i++){
			all_input[i].disabled=false;
		}
		
		for(i=0;i<all_select.length;i++){
			all_select[i].disabled=false;
		}
		
		for(i=0;i<all_textarea.length;i++){
			all_textarea[i].disabled=false;
		}
		
        $('edit_booked_line_holder').setStyle( {
            'background' : 'url(images/edit_item_gradient.gif) repeat-y',
			'color'      : '#ff0000'
        } );

		var item_validator = new lineItemValidator(
    		[
    			'qty',
				'list',
				'discount1',
				'discount2',
				'discount3',
				'discount4',
				'discount5',
				'cost',
				'gp_margin',
				'list_discount',
				'sell'
			],
			[
    			'keyup',
				'keyup',
				'keyup',
				'keyup',
				'keyup',
				'keyup',
				'keyup',
				'change|keyup',
				'blur|keyup',
				'keyup',
				'keyup'
			]
		);
	}
	else {

		for(i=0;i<all_input.length;i++){
			all_input[i].disabled=true;
		}
		
		for(i=0;i<all_select.length;i++){
			all_select[i].disabled=true;
		}
		
		for(i=0;i<all_textarea.length;i++){
			all_textarea[i].disabled=true;
		}
		
		$('edit_booked_line_holder').setStyle( {
            'background'        : '',
			'backgroundColor'   : '#eaeaea',
			'color'             : '#000000'
        } );

		lineItemValidator.prototype.stopListening(
    		[
    			'qty',
				'list',
				'discount1',
				'discount2',
				'discount3',
				'discount4',
				'discount5',
				'cost',
				'gp_margin',
				'list_discount',
				'sell'
			],
			[
                'keyup',
				'keyup',
				'keyup',
				'keyup',
				'keyup',
				'keyup',
				'keyup',
				'change|keyup',
				'blur|keyup',
				'keyup',
				'keyup'
			]
		);
	}
}

function css_browser_selector(u){var ua = u.toLowerCase(),is=function(t){return ua.indexOf(t)>-1;},g='gecko',w='webkit',s='safari',o='opera',h=document.getElementsByTagName('html')[0],b=[(!(/opera|webtv/i.test(ua))&&/msie\s(\d)/.test(ua))?('ie ie'+RegExp.$1):is('firefox/2')?g+' ff2':is('firefox/3.5')?g+' ff3 ff3_5':is('firefox/3')?g+' ff3':is('gecko/')?g:is('opera')?o+(/version\/(\d+)/.test(ua)?' '+o+RegExp.$1:(/opera(\s|\/)(\d+)/.test(ua)?' '+o+RegExp.$2:'')):is('konqueror')?'konqueror':is('chrome')?w+' chrome':is('iron')?w+' iron':is('applewebkit/')?w+' '+s+(/version\/(\d+)/.test(ua)?' '+s+RegExp.$1:''):is('mozilla/')?g:'',is('j2me')?'mobile':is('iphone')?'iphone':is('ipod')?'ipod':is('mac')?'mac':is('darwin')?'mac':is('webtv')?'webtv':is('win')?'win':is('freebsd')?'freebsd':(is('x11')||is('linux'))?'linux':'','js']; c = b.join(' '); h.className += ' '+c; return c;};









