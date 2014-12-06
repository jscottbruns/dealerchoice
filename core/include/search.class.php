<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed

abstract class search
{

	protected $class;
	protected $hash;
	protected $total;

	function fetch_search($search_hash, $class=NULL) {

		$r = $this->db->query("SELECT
	                        	   t1.search_class,
	                        	   t1.query,
	                        	   t1.total,
	                        	   t1.search_str
                        	   FROM search t1
                        	   WHERE t1.search_hash = '$search_hash' " .
                        	   ( $class ?
                            	   "AND t1.search_class = '$class'" : NULL
                        	   ) );

        if ( $row = $this->db->fetch_assoc($r) ) {

        	$this->hash = $search_hash;
        	$this->class = $row['search_class'];
        	$this->total = $row['total'];
        	$this->query = $row['query'];
        	$this->search_str = $row['search_str'];

        	return true;
        }

        return false;
	}

	function get_total() { return $this->total; }
}