<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class xls {
	
	var $buffer;
	var $xls_row;
	
	function xlsBOF($title) { 
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0"); 
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");;
		header("Content-Disposition: attachment; filename=".$title.".xls");
		header("Content-Transfer-Encoding: binary ");

		$this->buffer .= pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);  

		$this->xls_row = 0;
	} 
	
	function xlsMoveRow($row_no=NULL) {
		if ($row_no)
			$this->xls_row = $row_no;
		else
			$this->xls_row++;
	}
	
	function xlsWriteNumber($Col, $Value) { 
		$this->buffer .= pack("sssss", 0x203, 14, $this->xls_row, $Col, 0x0) . pack("d", $Value);
	} 
	
	function xlsWriteLabel($Col, $Value ) { 
		$L = strlen($Value); 
		$this->buffer .= pack("ssssss", 0x204, 8 + $L, $this->xls_row, $Col, 0x0, $L) . $Value; 
	}
	
	function xlsWriteBoldLabel($Col, $Value ) { 
		$L = strlen($Value); 
		$this->buffer .= pack("ssssss", 0x204, 8 + $L, $this->xls_row, $Col, 0x0, $L) . $Value; 
	} 
	
	function xlsStream() {
		$this->buffer .= pack("ss", 0x0A, 0x00); 

		echo $this->buffer;
		exit();
	}
}
?>