function getXmlHttpRequestObject() {
    if (window.XMLHttpRequest) {
        return new XMLHttpRequest();
    } else if(window.ActiveXObject) {
        return new ActiveXObject("Microsoft.XMLHTTP");
    } else {
        alert("Browser does not support Ajax search suggest. Please upgrade your browser.");
    }
}
var searchReq = getXmlHttpRequestObject();

var vt = '';
var vt_val = '';
var ka = false; //KeyCall active
var vt_canc = '';
var q_input;

function key_call(class_n,parent_n,parent_v,start) {
	vtobj = new vtimer();
    if (start == 1) { 
    	vt_canc = true;
        window.clearTimeout(vt);
        vt_val = '';
        $('key_result_holder').value = 0;    
    }
    if (arguments.length > 4) {
        var val_str = ',';
        for (var i = 4; i < arguments.length; i++)
            val_str += '\''+arguments[i]+'\''+(i < arguments.length - 1 ? ',' : '');
    }    
    
    var vt_tmp = '';
    vt = window.setTimeout('key_call(\''+class_n+'\',\''+parent_n+'\',\''+parent_v+'\',0'+(val_str ? val_str : '')+')',500);
    q_input = $(parent_n); 
    var str = $F(parent_n);
    str = str.replace(/[^\040-\176\200-\377]/gi, "");
    str = str.replace(/'/g, '');
    str = str.replace(/`/g, '');
    
    if (str == '' && $('keyResults').getStyle('display') == 'block') {
        key_clear();
        $('keyResults').setStyle({display:'none'});
        return;
    }     

    if (vt_val != str) {        
        vt_val = str;
        ka = true;
        if (arguments.length > 4) {
            var val_str = '';
            for (var i = 4; i < arguments.length; i++)
                val_str += arguments[i]+'='+$F(arguments[i])+(i < arguments.length - 1 ? '&' : '');            
        }   
        if (searchReq != null) {   
        	if(parent_n == "item_no"){
        			parent_v = document.getElementById("item_vendor").value; 
        	}
			searchReq.open("GET",'include/ajax_keySearch.php?class=' + class_n + '&parent_name=' + parent_n + '&parent_id=' + parent_v + '&q=' + str + (val_str ? '&' + val_str : ''), true);
            searchReq.onreadystatechange = handleSearchSuggest; 
            searchReq.send(null);
        } else {
            if (str == '' || !str || str == false) {
                key_clear();
                $('keyResults').setStyle({display:'none'});
            } else 
                vt_val = vt_tmp;

        }
    }
}
function key_list(class_n,parent_n,parent_v,q) {
    vt_canc = true;
    window.clearTimeout(vt);
    vt_val = '';
    $('key_result_holder').value = 0;    
    
    q_input = $(parent_n); 	
    var str = escape(q);
    ka = true;
    
    if (arguments.length > 4) {
        var val_str = '';
        for (var i = 4; i < arguments.length; i++)
            val_str += arguments[i]+'='+$F(arguments[i])+(i < arguments.length - 1 ? '&' : '');            
    }       
    if (searchReq.readyState == 4 || searchReq.readyState == 0) {   
        searchReq.open("GET",'include/ajax_keySearch.php?class=' + class_n + '&parent_name=' + parent_n + '&parent_id=' + parent_v + '&q=' + str + (val_str ? '&' + val_str : ''), true);
        searchReq.onreadystatechange = handleSearchSuggest; 
        searchReq.send(null);
    } 
}
function handleSearchSuggest() {
    if (searchReq.readyState == 4 && searchReq.status == 200) {   
    	$('keyResults').innerHTML = '';
	    if (searchReq.responseXML!= null && searchReq.responseXML.hasChildNodes()) {
	        var xmlData = searchReq.responseXML.documentElement;
	        var header = xmlData.getElementsByTagName('keySearchHeader').item(0);
	        var styleEl = xmlData.getElementsByTagName('keySearchStyle').item(0);
	        var entry = xmlData.getElementsByTagName('keySearchEntry').item(0);

            if (entry.childNodes[0].childNodes[0] != undefined && (!entry || entry.childNodes.length == 0 || entry.childNodes[0].childNodes[0].nodeValue == '' || !entry.childNodes[0].childNodes[0].nodeValue)) {
                $('keyResults').setStyle({display:'none'});
                return;
            }   
            if ($('keyResults').getStyle('display') == 'none')
                $('keyResults').setStyle({display:'block'});            
                	
            if (ka == false) {
                key_clear();
                $('keyResults').setStyle({display:'none'});
            }     
                                   	        
	        if (header)
                $('keyResults').innerHTML += header.childNodes[0].nodeValue;

            optionList = '';
            for (var i = 0; i < entry.childNodes.length; i++){
            	if(entry.childNodes[0].childNodes[0] != undefined ){
            		entry.childNodes[i].childNodes[0].nodeValue = entry.childNodes[i].childNodes[0].nodeValue.replace('/[\x00-\x1F\x80-\xFF]/', entry.childNodes[i].childNodes[0].nodeValue);
                	optionList += entry.childNodes[i].childNodes[0].nodeValue;
            	}else{
            		entry.childNodes[i].textContent = entry.childNodes[i].textContent.replace('/[\x00-\x1F\x80-\xFF]/', entry.childNodes[i].textContent);
                	optionList += entry.childNodes[i].textContent;
            	}
            	
            }
                            
            $('keyResults').innerHTML += '<div ' + (styleEl ? styleEl.childNodes[0].nodeValue : '') + '>' + optionList + '</div>';
            
            reset_key_holder(0);            
            
            return;
	    } 
    }
}
function key_clear() {
    window.clearTimeout(vt);    
    ka = false;
    vt_val = '';
    $('key_result_holder').value = 0;    
    vt_canc = false;    
}
function key_clear2(desc,list) {
    window.clearTimeout(vt);    
    ka = false;
    vt_val = '';
    $('key_result_holder').value = 0;    
    vt_canc = false;
    document.getElementById("item_descr").value = desc;
    document.getElementById("list").value = list;
}
function reset_key_holder(i) {
    if (parseInt($F('key_result_holder')) > 0 && parseInt($F('key_result_holder')) != i) {
        if ($('key_result_'+$F('key_result_holder')))
            $('key_result_'+$F('key_result_holder')).className = 'suggest_link';
    }
    $('key_result_holder').value=(i?i:0);   
}
//Mouse over function
function suggestOver(div_value) {
    $(div_value).className = 'suggest_link_over';
}
//Mouse out function
function suggestOut(div_value) {
    $(div_value).className = 'suggest_link';
}
var keyPosTop = '';
var keyPosLeft = '';
vtobj = new vtimer();
function position_element(obj,px_top,px_left,class_name) {
	vtobj = new vtimer();
    vtobj.reset_keyResults(1);
    if (px_top)
        obj.setStyle({top : px_top+'px'});
    if (px_left) 
        obj.setStyle({left : px_left+'px'});   
        
    keyPosTop = px_top;
    keyPosLeft = px_left;  
}

function vtimer() {
    this.vtimer_id;
    this.keyResults_hide = function() {
    	if (vt_canc == true)
    	   return;
    	   
        $('keyResults').hide();
        $('keyResults').innerHTML = '';
        $('key_result_holder').value = 0;
    }
    this.reset_keyResults = function() {
        if (arguments[0] == 1) {
            this.keyResults_hide();
            return;
        } 
        this.vtimer_id=window.setTimeout('vtobj.keyResults_hide()',1000);   
    }
    this.hideKeyResults = function() {
        if ($('keyResults').getStyle('display') == 'block') 
            this.reset_keyResults(1);
        
    }
    this.cancel_timer = function() {
        if (this.vtimer_id)
            window.clearTimeout(this.vtimer_id);    
    }
}
