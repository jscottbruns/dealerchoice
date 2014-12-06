<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class pdfXtra {
	
	function fill_void($str,$length,$textSize=12) {
		$length = floor($length / $this->getTextWidth($textSize,$str));
		for ($i = 0; $i < $length; $i++)
			$fill[] = $str;
			
		return implode('',$fill);
	}











}
?>