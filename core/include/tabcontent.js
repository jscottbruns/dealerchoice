//** Tab Content script- © Dynamic Drive DHTML code library (http://www.dynamicdrive.com)
//** Last updated: Nov 8th, 06

var enabletabpersistence=1 //enable tab persistence via session only cookies, so selected tab is remembered?

////NO NEED TO EDIT BELOW////////////////////////
var tabcontentIDs=new Object()

function expandcontent(linkobj){
	var ulid = $(linkobj).parentNode.parentNode.id //id of UL element
	var par_ul = $(linkobj).parentNode.parentNode;

	var ullist = $(par_ul).getElementsByTagName("li");
	for (var i = 0; i < ullist.length; i++) {
		$(ullist[i]).removeClassName('selected');
		var child_div = $(ullist[i]).childElements();
		for (var j = 0; j < child_div.length; j++)
			$($(child_div[j]).readAttribute('rel')).setStyle({display:'none'});
	}
	$(linkobj).parentNode.addClassName('selected');  //highlight currently clicked on tab	
	$($(linkobj).readAttribute('rel')).setStyle({display:'block'}); //expand corresponding tab content
	saveselectedtabcontentid(ulid, $(linkobj).readAttribute('rel'))
}


function expandtab(tabcontentid, tabnumber){ //interface for selecting a tab (plus expand corresponding content)
var thetab=document.getElementById(tabcontentid).getElementsByTagName("a")[tabnumber]
if (thetab.getAttribute("rel"))
expandcontent(thetab)
}

function savetabcontentids(ulid, relattribute){// save ids of tab content divs
if (typeof tabcontentIDs[ulid]=="undefined") //if this array doesn't exist yet
tabcontentIDs[ulid]=new Array()
tabcontentIDs[ulid][tabcontentIDs[ulid].length]=relattribute
}

function saveselectedtabcontentid(ulid, selectedtabid){ //set id of clicked on tab as selected tab id & enter into cookie
if (enabletabpersistence==1) //if persistence feature turned on
setCookie(ulid, selectedtabid)
}

function getullistlinkbyId(ulid, tabcontentid){ //returns a tab link based on the ID of the associated tab content
var ullist=document.getElementById(ulid).getElementsByTagName("li")
for (var i=0; i<ullist.length; i++){
if (ullist[i].getElementsByTagName("a")[0].getAttribute("rel")==tabcontentid){
return ullist[i].getElementsByTagName("a")[0]
break
}
}
}

function initializetabcontent(){
	for (var i=0; i<arguments.length; i++){ //loop through passed UL ids
		if (enabletabpersistence==0 && getCookie(arguments[i])!="") //clean up cookie if persist=off
			setCookie(arguments[i], "")
		var clickedontab=getCookie(arguments[i]) //retrieve ID of last clicked on tab from cookie, if any
		var ulobj=document.getElementById(arguments[i])
		if (ulobj) {
			var ulist=ulobj.getElementsByTagName("li") //array containing the LI elements within UL
		}
		if (!ulist || ulist.length == 0)
			return;
		for (var x=0; x<ulist.length; x++){ //loop through each LI element
			var ulistlink=ulist[x].getElementsByTagName("a")[0]
			if (ulistlink.getAttribute("rel")){
				savetabcontentids(arguments[i], ulistlink.getAttribute("rel")) //save id of each tab content as loop runs
				ulistlink.click=function(){
					expandcontent(this)
					return false
				}
				if (ulist[x].className=="selected" && clickedontab=="")
					expandcontent(ulistlink) //auto load currenly selected tab content
			}
		} //end inner for loop
		if (clickedontab!=""){ //if a tab has been previously clicked on per the cookie value
			var culistlink=getullistlinkbyId(arguments[i], clickedontab)
			if (typeof culistlink!="undefined") //if match found between tabcontent id and rel attribute value
				expandcontent(culistlink) //auto load currenly selected tab content
			else //else if no match found between tabcontent id and rel attribute value (cookie mis-association)
				expandcontent(ulist[0].getElementsByTagName("a")[0]) //just auto load first tab instead
		}
	} //end outer for loop
}


function getCookie(Name){ 
var re=new RegExp(Name+"=[^;]+", "i"); //construct RE to search for target name/value pair
if (document.cookie.match(re)) //if cookie found
return document.cookie.match(re)[0].split("=")[1] //return its value
return ""
}

function setCookie(name, value){
document.cookie = name+"="+value //cookie value is domain wide (path=/)
}