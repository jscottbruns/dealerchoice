<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class math {

	function gp_margin() {

        if ( $arg = func_get_arg(0) ) {

            $cost = $arg['cost'];
            $sell = $arg['sell'];
            $margin = $arg['margin'];
        }

		if ( $margin ) {

			$sell = bcmul( bcdiv($cost, bcsub(100, $margin, 4), 6), 100, 4);
			return _round($sell, 4);

		} elseif ( bccomp($sell, 0, 2) == 1 )
			return _round( bcdiv( bcsub($sell, $cost, 4), $sell, 6), 4);

		elseif ( ! bccomp($cost, 0, 2) && ! bccomp($sell, 0, 2) )
			return 0;

		return -1;
	}

	function list_discount() {

		if ( $arg = func_get_arg(0) ) {

			$list = $arg['list'];
			$sell = $arg['sell'];
			$disc = $arg['disc'];
		}

		if ( $disc )
			$return = (float)bcsub($list, bcmul($list, bcmul($disc, .01, 6), 4), 4);
		else
    		$return = (float)bcmul( bcsub( bcmul( bcdiv($sell, $list, 6), 100, 4), 100, 4), -1, 4);

    	return _round($return, 2);
	}

	function format_num($num, $fix='$', $per=NULL) {

		if ( bccomp($num, 0, 2) == -1 ) {

			$num = bcmul($num, -1, 2);
			return "
			<span style=\"color:#ff0000;\">(" .
			( ! $per ?
    			$fix : NULL
    		)
    		. number_format($num, 2) . ")" .
    		( $per ?
        		$fix : NULL
        	) . "
        	</span>";
		} else
			return
			( ! $per ?
    			$fix : NULL
    		)
    		. number_format($num, 2) .
    		( $per ?
        		$fix : NULL
        	);
	}



}
?>