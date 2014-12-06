<!--
//
var popup_windows = new Object();
function Agent() {
	this.attempts = 0;
	this.url = 'include/ajax_proxy.php';
	this.style_timer='';
	this.in_progress=false;
	this.initialize = function(freq,sobj) {
		this.start_poll(freq,sobj);
		Ajax.Responders.register({
								 onCreate : function(request) {agent.in_progress=true;Ajax.activeRequestCount++;clearTimeout(this.style_timer);this.style_timer=window.setTimeout('show_wait_icon()',1500);request['timeoutId']=window.setTimeout(function(){if(agent.callInProgress(request.transport)){agent.abort_request(request);if(request.options['onFailure']){request.options['onFailure'](request.transport,request.json);}}},1000);},
								 onComplete : function(request) {agent.in_progress=false;Ajax.activeRequestCount--;clearTimeout(this.style_timer);$('ajax_progress').style.display = 'none';window.clearTimeout(request['timeoutId']);},
								 onFailure : function(request){return;}
		});
	}
	this.start_poll = function(freq,sobj) {
		if (!sobj)
			sobj = 'alerts';

		this.poll = new Ajax.PeriodicalUpdater('',
											   this.url,{
											   parameters : 'aa_ajax=1&aa_sobj='+sobj+'&aa_sfunc=sf_loadcontent&aa_sfunc_args[]=poll'+($('unique_id')?'&unique_id='+$F('unique_id'):''),
											   frequency : freq,
											   onSuccess : function(resp){cf_loadcontent(resp.responseXML);}
											   });
	}
	this.call = function() {
		var class_name = arguments[0];
		var sf_func = arguments[1];

		if (arguments.length > 3) {
			var aa_poststr = '';
			for (var i = 3; i < arguments.length; i++) {
				aa_poststr += '&aa_sfunc_args[]='+arguments[i];
				var split_it = arguments[i].split('=');
				aa_poststr += '&'+split_it[0]+'='+split_it[1];
			}
		}
		var client_func = arguments[2];

		this.current_call = new Array();
		this.current_call['class_name'] = class_name;
		this.current_call['sf_func'] = sf_func;
		this.current_call['client_func'] = client_func;
		this.current_call['func_args'] = aa_poststr;
		//alert(resp.responseText);
		v = new Ajax.Request(this.url, {
							 parameters : 'aa_ajax=1&aa_sobj='+class_name+'&aa_sfunc='+sf_func+aa_poststr,
							 onSuccess : function(resp){if(resp.status==200){Ajax.failedRequestCount=0;this.current_call='';}switch(client_func){case 'cf_loadcontent':cf_loadcontent(resp.responseXML);break;case 'show_popup_window':show_popup_window(resp.responseXML);break;case 'refresh_form':refresh_form(resp.responseXML);break;case 'nothing':nothing(resp.responseXML);break;}}
							 });
	}
	this.abort_request = function (xmlhttp) {
		return;
		if (confirm('The request has failed to respond. To cancel the current request, click \'Ok\', otherwise, click \'Cancel\' to keep waiting.')) {
			xmlhttp.transport.abort();
			window.clearTimeout(xmlhttp['timeoutId']);
		} else {
			xmlhttp['timeoutId']=window.setTimeout(function(){if(agent.callInProgress(xmlhttp.transport)){agent.abort_request(xmlhttp);if(xmlhttp.options['onFailure']){xmlhttp.options['onFailure'](xmlhttp.transport,xmlhttp.json);}}},10000);
		}
	}
	this.callInProgress = function(xmlhttp) {
		switch (xmlhttp.readyState) {
			case 1: case 2: case 3:
			return true;
			break;
			// Case 4 and 0

			default:
			return false;
			break;
		}
	}
	this.showFailureMessage = function() {
		return;
		alert('uh oh, it looks like the network is down. Try again shortly');
	}
}

var agent = new Agent();

function failit() {
	alert('Communication request has failed');
}

var d = document;
function URLEncode(plaintext)
{
	// The Javascript escape and unescape functions do not correspond
	// with what browsers actually do...
	var SAFECHARS = "0123456789" +					// Numeric
					"ABCDEFGHIJKLMNOPQRSTUVWXYZ" +	// Alphabetic
					"abcdefghijklmnopqrstuvwxyz" +
					"-_.!~*'()";					// RFC2396 Mark characters
	var HEX = "0123456789ABCDEF";

	var encoded = "";
	for (var i = 0; i < plaintext.length; i++ ) {
		var ch = plaintext.charAt(i);
	    if (ch == " ") {
		    encoded += "+";				// x-www-urlencoded, rather than %20
		} else if (SAFECHARS.indexOf(ch) != -1) {
		    encoded += ch;
		} else {
		    var charCode = ch.charCodeAt(0);
			if (charCode > 255) {
				encoded += "+";
			} else {
				encoded += "%";
				encoded += HEX.charAt((charCode >> 4) & 0xF);
				encoded += HEX.charAt(charCode & 0xF);
			}
		}
	} // for

	return encoded;
}

function getElementsByClassName(oElm, strTagName, strClassName){
    var arrElements = (strTagName == "*" && oElm.all)? oElm.all : oElm.getElementsByTagName(strTagName);
    var arrReturnElements = new Array();
    strClassName = strClassName.replace(/\-/g, "\\-");
    var oRegExp = new RegExp("(^|\\s)" + strClassName + "(\\s|$)");
    var oElement;
    for(var i=0; i<arrElements.length; i++){
        oElement = arrElements[i];
        if(oRegExp.test(oElement.className)){
            arrReturnElements.push(oElement);
        }
    }
    return (arrReturnElements)
}

function submit_form(fobj,class_name,sf_func,cf_func) {
	var str = '';
	if (arguments.length > 4) {
		for(var i = 4; i < arguments.length; i++) {
			if (arguments[i] == 'final=1')
				var a = 1;
			if (typeof(arguments[i]) == 'object') {
				for (var j = 0; j < arguments[i].length; j++) {
					var split_it = arguments[i][j].split('=');
					str += '&'+split_it[0]+'='+split_it[1];
				}
			} else {
				var split_it = arguments[i].split('=');
				str += '&'+split_it[0]+'='+split_it[1];
			}
		}
	}
	//if (a)
		//document.write(Form.serialize(fobj).replace(/&/g,"<br />"));

	agent.call(class_name,sf_func,cf_func,Form.serialize(fobj),str);
}
function reset_error_nodes(xmlData,div_class,div_id) {
	if ($(div_id)) {
		var a = $(div_id).getElementsByClassName(div_class);
		if (a) {
			for (var index = 0; index < a.length; ++index) {
				if (!xmlData.getElementsByTagName(a[index].id).item(0))
					$(a[index]).removeClassName(div_class);
			}
		}
	}
}
function refresh_form(xml) {

	if ( xml.hasChildNodes() ) {

    	var popup_id = '';

		var xmlData = xml.documentElement;
		var error = xmlData.getElementsByTagName('error').item(0);
		var form_return = xmlData.getElementsByTagName('form_return').item(0);
		var popup_data = xmlData.getElementsByTagName('popup_controls').item(0);
		var submit_btn = xmlData.getElementsByTagName('submit_btn').item(0);

		if ( popup_data && popup_data.getElementsByTagName('popup_id').item(0).childNodes[0] )
			var popup_id = popup_data.getElementsByTagName('popup_id').item(0).childNodes[0].nodeValue;

		if ( form_return ) {

			if ( form_return.getElementsByTagName('feedback') )
                var feedback_msg = form_return.getElementsByTagName('feedback').item(0);
            if ( form_return.getElementsByTagName('err') )
    			var error_nodes = form_return.getElementsByTagName('err').item(0);

			// If we have multiple values under feedback msg, parse them out by line breaks
			if ( feedback_msg && feedback_msg.childNodes[0].childNodes.length >= 0 ) {

                var feedback_txt;

                if ( feedback_msg.childNodes[0].childNodes.length > 0 ) {

					feedback_txt = 'The following errors were reported: <br /><br />';

					for ( var i = 0; i < feedback_msg.childNodes.length; i++ ) {

						if ( feedback_msg.childNodes[i].childNodes[0] )
							feedback_txt += feedback_msg.childNodes[i].childNodes[0].nodeValue + '<br />';
					}
				} else
    				feedback_txt = feedback_msg.childNodes[0].nodeValue;

			}
		}

		// The submitted form contains errors
		if ( error ) {

			reset_error_nodes(xmlData, 'error_class', 'main_table' + popup_id);
			reset_error_nodes(xmlData, 'error_class_text', 'main_table' + popup_id);

            if ( feedback_txt ) {

				$('feedback_holder' + popup_id).style.display = 'block';
				$('feedback_message' + popup_id).innerHTML = feedback_txt;
            }

			if ( error_nodes ) {

				for (var i = 0; i < error_nodes.childNodes.length; i++) {
					if ($(error_nodes.childNodes[i].nodeName).nodeType == 3 || $(error_nodes.childNodes[i].nodeName).type == 'text' || $(error_nodes.childNodes[i].nodeName).type == 'select-one')
                        $(error_nodes.childNodes[i].nodeName).addClassName('error_class_text');
					else
						$(error_nodes.childNodes[i].nodeName).addClassName('error_class');
				}
			}
			if (xmlData.getElementsByTagName('jscript').item(0)) {
				for (var i = 0; i < xmlData.getElementsByTagName('jscript').item(0).childNodes.length; i++) {
					if (window.execScript) {
						if (xmlData.getElementsByTagName('jscript').item(0).childNodes[i].nodeValue != null)
							window.execScript(xmlData.getElementsByTagName('jscript').item(0).childNodes[i].nodeValue);
						else if (xmlData.getElementsByTagName('jscript').item(0).childNodes[i].childNodes[0].nodeValue != null)
							window.execScript(xmlData.getElementsByTagName('jscript').item(0).childNodes[i].childNodes[0].nodeValue);
					} else {
						if (xmlData.getElementsByTagName('jscript').item(0).childNodes[i].nodeValue != null)
							window.setTimeout(xmlData.getElementsByTagName('jscript').item(0).childNodes[i].nodeValue,0);
						else if (xmlData.getElementsByTagName('jscript').item(0).childNodes[i].childNodes[0].nodeValue != null)
							window.setTimeout(xmlData.getElementsByTagName('jscript').item(0).childNodes[i].childNodes[0].nodeValue,0);
					}
				}
			}
            if (xmlData.getElementsByTagName('html').item(0))
                write_content(xmlData.getElementsByTagName('html').item(0),'html');

			if ( submit_btn )
				toggle_submit( submit_btn.childNodes[0].nodeValue );

		} else {

            var action;
			if ( action = xmlData.getElementsByTagName('action').item(0) ) {

				if ( action.childNodes.item(0).nodeValue == 'close') {

					if ( popup_id )
    					popup_windows[ popup_id].hide();

					cf_loadcontent(xml);

				} else if ( action.childNodes.item(0).nodeValue == 'continue' )
					cf_loadcontent(xml);
			}

            if ( xmlData.getElementsByTagName('page_feedback').item(0) )
                show_page_feedback( xmlData.getElementsByTagName('page_feedback').item(0).childNodes[0].nodeValue );

			if ( $('feedback_holder' + popup_id) && $('main_table' + popup_id) ) {

				$('feedback_holder' + popup_id).style.display = 'none';
				reset_error_nodes(xmlData, 'error_class', 'main_table' + popup_id);
				reset_error_nodes(xmlData, 'error_class_text', 'main_table' + popup_id);
			}

			if ( xmlData.getElementsByTagName('jscript_action').item(0) ) {

				if ( window.execScript )
					window.execScript( xmlData.getElementsByTagName('jscript_action').item(0).childNodes[0].nodeValue );
				else
					window.setTimeout( xmlData.getElementsByTagName('jscript_action').item(0).childNodes[0].nodeValue, 0);
			}
			if ( submit_btn )
				toggle_submit( submit_btn.childNodes[0].nodeValue );
		}
	}
}

function focus_to(el) {

	if ($(el)) {
        $(el).activate();
    }
}

function unlock_row(cn,id,onclose_func) {
	agent.call(cn,'sf_loadcontent','nothing','unlock','row_id='+id,'onclose_func='+onclose_func);
	return;
}

//A more concise version of refresh_form, but without all the functionality
function nothing(xml) {
	if (xml.hasChildNodes()) {
		var xmlData = xml.documentElement;
		if (xmlData.getElementsByTagName('jscript_action').item(0)) {
			if (window.execScript)
				window.execScript(xmlData.getElementsByTagName('jscript_action').item(0).nodeValue);
			else
				window.setTimeout(xmlData.getElementsByTagName('jscript_action').item(0).nodeValue,0);
		}
	}
	return;
}

function cf_loadcontent(xml) {
	var jscript_xml = '';
	if (xml.hasChildNodes()) {

		var xmlData = xml.documentElement;
		for (var i = 0; i < xmlData.childNodes.length; i++) {

			switch (xmlData.childNodes[i].nodeName) {

				case 'html':
				write_content(xmlData.childNodes[i],'html');
				break;

				case 'html_append':
				write_content(xmlData.childNodes[i],'html_append');
				break;

				case 'value':
				write_content(xmlData.childNodes[i],'value');
				break;

				case 'value_append':
				write_content(xmlData.childNodes[i],'value_append');
				break;

				case 'jscript':
				write_content(xmlData.childNodes[i],'jscript');
				break;

				case 'focus':
                if (xmlData.childNodes[i].firstChild)
                    var focus_to_field = xmlData.childNodes[i].firstChild.nodeValue;

                break;
			}
		}

        if ( focus_to_field )
    		focus_to(focus_to_field);
	}
}

function show_popup_window(xml) {
	if (xml.hasChildNodes()) {
		cf_loadcontent(xml);
		var xmlData = xml.documentElement;
		var popup_scrolling = 1;
		var popup_resize = 1;
		for (var i = 0; i < xmlData.childNodes.length; i++) {
			switch (xmlData.childNodes[i].nodeName) {
				case 'class_name':
				if (xmlData.childNodes[i].firstChild)
					var class_name = xmlData.childNodes[i].firstChild.nodeValue;

				break;

				case 'row_lock':
				if (xmlData.childNodes[i].firstChild)
					var row_lock_id = xmlData.childNodes[i].firstChild.nodeValue;

				break;

				case 'focus':
				if (xmlData.childNodes[i].firstChild)
					var focus_to_field = xmlData.childNodes[i].firstChild.nodeValue;

				break;

				case 'popup_controls':
				for (var j = 0; j < xmlData.childNodes[i].childNodes.length; j++) {
					switch (xmlData.childNodes[i].childNodes[j].nodeName) {
						case 'popup_id':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var popup_id = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
						case 'popup_title':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var popup_title = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
						case 'onclose':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var onclose_func = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
						case 'onload':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var onload_func = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
						case 'popup_width':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var popup_width = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
						case 'popup_height':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var popup_height = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
						case 'popup_resize':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var popup_resize = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
						case 'popup_scrolling':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var popup_scrolling = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
						case 'cmdTable':
						if (xmlData.childNodes[i].childNodes[j].childNodes[0])
							var cmdTable = xmlData.childNodes[i].childNodes[j].childNodes[0].nodeValue;

						break;
					}
				}
				break;
			}
		}
		if (!popup_id)
			popup_id = 'client_popup';

        if (!cmdTable)
            return false;

		if (onload_func) {
			var onload_arg = function(){eval(onload_func)};
		}

		popup_windows[popup_id] = dhtmlwindow.open(popup_id,'inline',cmdTable,popup_title,'width='+(popup_width?popup_width:'750px')+(popup_height?',height='+popup_height:'')+',left=200px,top=150px,resize='+popup_resize+',scrolling='+popup_scrolling,null,onload_arg);

		if (xmlData.getElementsByTagName('error').item(0))
			do_error(popup_id,xmlData.getElementsByTagName('form_return').item(0));

		if ((class_name && row_lock_id) || onclose_func)
			popup_windows[popup_id].onclose = function(){if(class_name && row_lock_id){unlock_row(class_name,row_lock_id,onclose_func)}if(onclose_func){eval(onclose_func)}}
		else {
			if (onclose_func)
				popup_windows[popup_id].onclose = function(){eval(onclose_func)}

		}
		if (focus_to_field)
			focus_to(focus_to_field);
	}
}

function write_content(xml_content,type) {
	for (var i = 0; i < xml_content.childNodes.length; i++) {
		if (type == 'html' && $(xml_content.childNodes[i].nodeName)) {
			if (!xml_content.childNodes[i].childNodes[0])
				$(xml_content.childNodes[i].nodeName).innerHTML='';
			else
				$(xml_content.childNodes[i].nodeName).innerHTML=xml_content.childNodes[i].childNodes[0].nodeValue;
		} else if (type == 'html_append' && $(xml_content.childNodes[i].nodeName) && xml_content.childNodes[i].childNodes[0])
			$(xml_content.childNodes[i].nodeName).innerHTML+=xml_content.childNodes[i].childNodes[0].nodeValue;
		else if (type == 'value' && $(xml_content.childNodes[i].nodeName)) {
			if (!xml_content.childNodes[i].childNodes[0])
				$(xml_content.childNodes[i].nodeName).value='';
			else
				$(xml_content.childNodes[i].nodeName).value=xml_content.childNodes[i].childNodes[0].nodeValue;
		} else if (type == 'value_append' && $(xml_content.childNodes[i].nodeName) && xml_content.childNodes[i].childNodes[0])
			$(xml_content.childNodes[i].nodeName).value+=xml_content.childNodes[i].childNodes[0].nodeValue;
		else if (type == 'jscript') {
			if (xml_content.childNodes[i].nodeValue != null) {
				//alert(xml_content.childNodes[i].nodeValue);
				eval(xml_content.childNodes[i].nodeValue);
			} else {
				//alert('2: '+xml_content.childNodes[i].childNodes[0].nodeValue);
				eval(xml_content.childNodes[i].childNodes[0].nodeValue);
			}
		}
	}
}

function do_error(popup_id,form_return) {
	var feedback_msg = form_return.getElementsByTagName('feedback').item(0);

	$('feedback_holder'+popup_id).style.display = 'block';
	$('feedback_message'+popup_id).innerHTML = feedback_msg.childNodes[0].nodeValue;

	return;
}


