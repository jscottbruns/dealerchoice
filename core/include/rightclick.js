var right_menu_open='';
function showmenuie5(menu_id) {
	if(right_menu_open && $(menu_obj))
		$(menu_obj).style.visibility = "hidden";

	menu_obj = menu_id;
	if (!$(menu_obj))
		return;

	var rightedge = document.body.clientWidth-event.clientX;
	var bottomedge = document.body.clientHeight-event.clientY+(document.documentElement.scrollTop?document.documentElement.scrollTop:document.body.scrollTop);
	if (rightedge < $(menu_obj).style.offsetWidth)
		$(menu_obj).style.left = (document.body.scrollLeft + event.clientX - $(menu_obj).style.offsetWidth) + "px";
	else
		$(menu_obj).style.left = (document.body.scrollLeft + event.clientX) + "px";
	
	if (bottomedge < $(menu_obj).style.offsetHeight)
		$(menu_obj).style.top = ((document.documentElement.scrollTop?document.documentElement.scrollTop:document.body.scrollTop) + event.clientY - $(menu_obj).style.offsetHeight) + "px";
	else
		$(menu_obj).style.top = ((document.documentElement.scrollTop?document.documentElement.scrollTop:document.body.scrollTop) + event.clientY) + "px";
	
	$(menu_obj).style.visibility = "visible";
	right_menu_open=$(menu_obj);
	return false;
}
function hidemenuie5() {
	if(right_menu_open && $(menu_obj))
		$(menu_obj).style.visibility = "hidden";
		
	return;
}
