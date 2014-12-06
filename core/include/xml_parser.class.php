<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
class xmlParser {

    private $xml;
	private $current_hash;
	private $import_from;
	private $proposal_hash;

	public $prj_data;
	public $prj_refs;

	function __construct($xmlFile, $import_from=NULL, $proposal_hash=NULL) {

        if ( ! file_exists($xmlFile) )
            throw new Exception("Error. Cannot stat '$xmlFile': The SIF/XML import file is does not exist or cannot be accessed. Please check file/directory permissions and try again");

        if(!$this->xml = file_get_contents($xmlFile))
            throw new Exception('Error reading input file.');

		$this->current_hash = $_SESSION['id_hash'];
		$this->import_from = $import_from;
		$this->proposal_hash = $proposal_hash;

		$this->xmlFile['name'] = $xmlFile;
		$this->xmlFile['ext'] = strrev(substr(strtolower(strrev($this->xmlFile['name'])),0,3));

		if ($this->import_from == "EXPRESSXML" && $this->xmlFile['ext'] != "xml")
			$this->error = "The file you have imported is not an ExpressXML file. Please make sure you are selecting the correct export type when exporting from Design Express and that the file extension, or last 3 letters of the file name, is 'xml'.";
		elseif ($this->import_from == "OFDAXML" && $this->xmlFile['ext'] != "xml")
			$this->error = "The file you have imported is not an OFDAXML file. Please make sure you are selecting the correct export type when exporting your file and that the file extension, or last 3 letters of the file name, is 'xml'.";
		elseif ($this->import_from == "SIF" && $this->xmlFile['ext'] != "sif")
			$this->error = "The file you have imported is not a valid SIF file. Please make sure you are selecting the correct export type (SIF) when exporting your file and that the file extension, or last 3 letters of the file name, is 'sif'.";
		elseif ($this->import_from == "PRJMTRX" && $this->xmlFile['ext'] != "sif")
			$this->error = "The file you have imported is not a valid ProjectMatrix SIF file. Please make sure you are selecting custom SIF as the export type, and that the file extension, or last 3 letters of the file name, is 'sif'.";
	}

	public function xml2ary() {

		if ($this->import_from == 'SIF' || $this->import_from == 'PRJMTRX')
			$this->sif2ary();
		else {
			$parser = xml_parser_create();
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING,0);
			xml_parse_into_struct($parser,$this->xml,$vals,$index);
			xml_parser_free($parser);

			$mnary = array();
			$ary =& $mnary;
			foreach ($vals as $r) {
				$t = $r['tag'];
				if ($r['type'] == 'open') {
					if (isset($ary[$t])) {
						if (isset($ary[$t][0]))
							$ary[$t][] = array();
						else
							$ary[$t] = array($ary[$t],array());
							$cv =& $ary[$t][count($ary[$t]) - 1];
					} else
						$cv =& $ary[$t];
					if (isset($r['attributes'])) {
						foreach ($r['attributes'] as $k => $v)
							$cv['_a'][$k] = $v;
					}
					$cv['_c'] = array();
					$cv['_c']['_p'] =& $ary;
					$ary =& $cv['_c'];
				} elseif ($r['type'] == 'complete') {
					if (isset($ary[$t])) {
						if (isset($ary[$t][0]))
							$ary[$t][] = array();
						else
							$ary[$t] = array($ary[$t], array());

						$cv =& $ary[$t][count($ary[$t]) - 1];
					} else
						$cv =& $ary[$t];

					if (isset($r['attributes'])) {
						foreach ($r['attributes'] as $k => $v)
							$cv['_a'][$k] = $v;
					}
					$cv['_v'] = (isset($r['value']) ? $r['value'] : '');
				} elseif ($r['type'] == 'close')
					$ary =& $ary['_p'];
			}
			$this->_del_p($mnary);

			switch($this->import_from) {
				case 'EXPRESSXML':
				$this->prj_data =& $mnary['PROJECT'];
				break;

				case 'OFDAXML':
					// OFDA schema 3.0 uses Envelope as the root element and OFDA 4.0 uses ofda:Envelope. Account for both variations.
					if ($mnary['Envelope']) {
						$this->prj_data =& $mnary['Envelope'];
					} else {
						$this->prj_data =& $mnary['ofda:Envelope'];
					}
				break;
			}
			if (!is_array($this->prj_data))
				$this->error = "The file you selected appears to be invalid or empty. Please make sure the file is not corrupt and of a valid type.";
		}
	}

	function implode_it($a) {
		@array_shift($a);
		if (count($a) > 1)
			$b = @implode("=",$a);
		else
			$b = $a[0];

		return trim($b);
	}

	public function sif2ary() {
		$this->project = array();

		$tmp = file($this->xmlFile['name']);
        $valid_ends = array('ISL','OSL','IT','TP','GR');

		$this->project['SETUP']['ORIGINATING_FORMAT'] = 'SIF';
		$this->project['SETUP']['IMPORT_DATE'] = time();
		$this->project['SETUP']['FILE_NAME'] = basename($this->xmlFile['name']);

		for ( $i = 0; $i < count($tmp); $i++ ) {

			$var = explode("=", $tmp[$i]);
			$var[1] = trim($var[1]);

            switch ( $var[0] ) {

                case 'PRJ':
                $this->project['SETUP']['NAME'] = $this->implode_it($var);
                break;

                case 'ST':
                case 'VR': # Project Matrix

                if ( $this->import_from == 'PRJMTRX' && $var[0] == 'ST' )
                    continue;

                $this->project['SETUP']['ORIGINATING_SOFTWARE'] = $this->implode_it($var);
                break;

                case 'SF':
                $this->project['SETUP']['PROJECT_FILE_LOCATION'] = $this->implode_it($var);
                break;

                case 'SV':
                $this->project['SETUP']['ORIGINATING_VERSION'] = $this->implode_it($var);
                break;

                case 'TK': # ProjectMatrix Unknown

                break;

                case 'TYP': # Order type

                break;

                case 'GR': # GR=TYPES Start of panel type group

                break;

                case 'TP': # Begin panel type

                $this->open_panel = ( is_numeric($this->open_panel) ? $this->open_panel : 0 );
                $this->project['PANEL_TYPE'][$this->open_panel] = array();
                unset($this->open_item);

                break;

                case 'TPN': # Panel name

                $this->project['PANEL_TYPE'][$this->open_panel]['NAME'] = $this->implode_it($var);
                break;

                case 'TPD': # Panel description

                $this->project['PANEL_TYPE'][$this->open_panel]['DESCR'] = $this->implode_it($var);
                break;

                case 'TMC': # Panel catalog code

                $this->project['PANEL_TYPE'][$this->open_panel]['CATALOG_CODE'] = $var[1];;
                break;

                case 'TME': # Panel catalog effective date

                $this->project['PANEL_TYPE'][$this->open_panel]['CATALOG_EFFECTIVE_DATE'] = $var[1];;
                break;

                case 'TMV': # Panel catalog version

                $this->project['PANEL_TYPE'][$this->open_panel]['CATALOG_VERSION'] = $var[1];;
                break;

                case 'TFR': # Frame ID

                $this->project['PANEL_TYPE'][$this->open_panel]['FRAME_ID'] = $var[1];;
                break;

                case 'THT': # Panel frame height

                $this->project['PANEL_TYPE'][$this->open_panel]['HEIGHT'] = $var[1];;
                break;

                case 'THT': # Panel frame total height

                $this->project['PANEL_TYPE'][$this->open_panel]['TOTAL_HEIGHT'] = $var[1];;
                break;

                case 'IT': # Start of panel item

                $this->open_item = ( is_numeric($this->open_item) ? $this->open_item : 0 );
                unset($this->open_option);

                break;

                case 'ITP': # Panel item number

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['PN'] = $var[1];;
                break;

                case 'ITD': # Panel item descr

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['DESCR'] = $var[1];;
                break;

                case 'IOR': # Panel item order

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['ORDER'] = $var[1];;
                break;

                case 'ISD': # Panel item side

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['SIDE'] = $var[1];;
                break;

                case 'IHT': # Panel item height

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['HEIGHT'] = $var[1];;
                break;

                case 'IFR': # Panel item required

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['REQUIRED'] = $var[1];;
                break;

                case 'IFS': # Panel item single

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['SINGLE'] = $var[1];;
                break;

                case 'IMG': # Panel item main group

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['MAIN_GROUP'] = $var[1];;
                break;

                case 'ICG': # Panel item current group

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['CURR_GROUP'] = $var[1];;
                break;

                case 'IXX': # Panel item special

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['SPECIAL'] = $this->implode_it($var);
                break;

                case 'ISL': # Begin of panel item option

                $this->open_option = ( is_numeric($this->open_option) ? $this->open_option : 0 );
                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['FINISH'][$this->open_option]['LEVEL'] = $var[1];;
                break;

                case 'ION': # Option name

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['FINISH'][$this->open_option]['NAME'] = $var[1];;
                break;

                case 'IOD': # Option description

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['FINISH'][$this->open_option]['DESCR'] = $var[1];;
                break;

                case 'IOG': # Option group description

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['FINISH'][$this->open_option]['GROUP_DESCR'] = $var[1];;
                break;

                case 'IOP': # Option price

                $this->project['PANEL_TYPE'][$this->open_panel]['ELEMENT'][$this->open_item]['FINISH'][$this->open_option]['PRICE'] = $var[1];;
                break;

                case 'PN': # Begin part number

                unset($this->open_item_option);
                if ( is_numeric($this->open_item) ) {

                    if ( ! array_key_exists('COMPLETE', $this->project['ITEM'][$this->open_item]) )
                        $this->project['ITEM'][$this->open_item]['COMPLETE'] = 1;

                    $this->open_item++;
                } else
                    $this->open_item = 0;

                $this->project['ITEM'][$this->open_item]['PN'] = $var[1];;
                break;

                case 'CPL': # Item complete

                $this->project['ITEM'][$this->open_item]['COMPLETE'] = $var[1];;
                break;

                case 'XX': # Item special

                $this->project['ITEM'][$this->open_item]['SPECIAL'] = $this->implode_it($var);
                break;

                case 'PD': # Item descr
                case 'PE':
                case 'ED':

                $this->project['ITEM'][$this->open_item]['DESCR'] = $this->implode_it($var);
                break;

                case 'QT': # Item qty

                $this->project['ITEM'][$this->open_item]['QTY'] = preg_replace('/[^-.0-9]/', '', $var[1]);
                break;

                case 'NT': # Qty per package

                $this->project['ITEM'][$this->open_item]['PACKAGING'] = $var[1];;
                break;

                case 'MC': # Catalog code

                $this->project['ITEM'][$this->open_item]['CATALOG_CODE'] = $var[1];;
                break;

                case 'ME': # Catalog effective date

                $this->project['ITEM'][$this->open_item]['CATALOG_EFFECTIVE_DATE'] = $var[1];;
                break;

                case 'MV': # Catalog version

                $this->project['ITEM'][$this->open_item]['CATALOG_VERSION'] = $var[1];;
                break;

                case 'VC': # Vendor code

                $this->project['ITEM'][$this->open_item]['VENDOR_CODE'] = $var[1];;
                break;

                case 'VD': # Vendor descr

                if ( $this->open_item && is_numeric($this->open_item) )
                    $this->project['ITEM'][$this->open_item]['VENDOR_DESCR'] = $var[1];;

                break;

                case 'PZ': # Price zone or currency
                case 'CUR':

                $this->project['ITEM'][$this->open_item]['PRICE_ZONE'] = $var[1];;

                break;

                case 'IM': # ProjectMatrix Image File

                $this->project['ITEM'][$this->open_item]['IMAGE_FILE'] = $this->implode_it($var);
                break;

                case 'PC': # ProjectMatrix Part Class

                $this->project['ITEM'][$this->open_item]['PART_CLASS'] = $this->implode_it($var);
                break;

                case 'MD': # ProjectMatrix Model Description

                $this->project['ITEM'][$this->open_item]['MODEL_DESCR'] = $this->implode_it($var);
                break;

                case 'CG': # TODO: ProjectMatrix Unknown **

                break;


                case 'CC': # Category code
                case 'GC': # ProjectMatrix **

                $this->project['ITEM'][$this->open_item]['PRODUCT_CODE'] = $var[1];;
                break;

                case 'CP': # Category description

                $this->project['ITEM'][$this->open_item]['PRODUCT_DESCR'] = $var[1];;
                break;

                case 'CT': # Category type

                $this->project['ITEM'][$this->open_item]['PRODUCT_SECTOR'] = $var[1];;
                break;

                case 'TYPE': # Panel type ID (Teknion only)

                if ( $var[1] )
                    $this->project['ITEM'][$this->open_item]['PANEL_TYPE_ID'] = $var[1];;

                break;

                case 'PL': # Product list price

                $this->project['ITEM'][$this->open_item]['PRICE']['LIST'] = preg_replace('/[^-.0-9]/', "", $var[1]);
                break;

                case 'PB': # Product cost
                case 'BP':

                $this->project['ITEM'][$this->open_item]['PRICE']['BUY'] = preg_replace('/[^-.0-9]/', "", $var[1]);

                break;

                case 'PS': # Sell price
                case 'SP':

                $this->project['ITEM'][$this->open_item]['PRICE']['SELL'] = preg_replace('/[^.0-9]/', "", $var[1]);
                break;

                case 'SF%': # ProjectMatrix List discount factor

                $this->project['ITEM'][$this->open_item]['PRICE']['LIST_DISCOUNT_FACTOR'] = preg_replace('/[^.0-9]/', "", $var[1]);

                break;

                case 'P%': # Buy discount
                case 'B%':

                unset( $buy_disc );
                if ( ! is_array($this->project['ITEM'][$this->open_item]['PRICE']['BUY_DISCOUNT']) )
                    $this->project['ITEM'][$this->open_item]['PRICE']['BUY_DISCOUNT'] = array();

                if ( preg_match('/\|/', $var[1]) ) {

                    $buy_disc = explode("|", $var[1]);
                    for ( $z = 0; $z < count($buy_disc); $z++ ) {

                        if ( trim( $buy_disc[$z] ) ) {

                            array_push( $this->project['ITEM'][$this->open_item]['PRICE']['BUY_DISCOUNT'], array(
                                'DISCOUNT'  =>  preg_replace('/[^.0-9]/', "", $buy_disc[$z])
                            ) );
                        }
                    }

                } else {

                    array_push( $this->project['ITEM'][$this->open_item]['PRICE']['BUY_DISCOUNT'], array(
                        'DISCOUNT'  =>  preg_replace('/[^.0-9]/', "", $var[1])
                    ) );
                }

                break;

                case 'S-': # Discount off list
                case 'S%':

                unset($list_disc);
                if ( ! is_array($this->project['ITEM'][$this->open_item]['PRICE']['LIST_DISCOUNT']) )
                    $this->project['ITEM'][$this->open_item]['PRICE']['LIST_DISCOUNT'] = array();

                if ( preg_match('/\|/', $var[1]) ) {

                    $disc_array = preg_split('/\|/', $var[1]);
                    $base = 100;
                    for ( $z = 0; $z < count($disc_array); $z++ ) {

                        if ( trim( $disc_array[$z] ) )
                            $base = bcsub( $base, bcmul($base, bcmul( preg_replace('/[^.0-9]/', "", $disc_array[$z]), .01, 6), 6), 6);
                    }

                    $list_disc = _round( bcsub(100, $base, 6), 4);

                } else
                    $list_disc = preg_replace('/[^.0-9]/', "", $var[1]);


                $this->project['ITEM'][$this->open_item]['PRICE']['LIST_DISCOUNT'] = $list_disc;

                break;

                case 'BF%': # Buy discount factor

                $elem = count( $this->project['ITEM'][$this->open_item]['PRICE']['BUY_DISCOUNT'] );
                if ( $elem && isset( $this->project['ITEM'][$this->open_item]['PRICE']['BUY_DISCOUNT'][ --$elem ] ) )
                    $this->project['ITEM'][$this->open_item]['PRICE']['BUY_DISCOUNT'][ $elem ]['FACTOR'] = preg_replace('/[^.0-9]/', "", $var[1]);

                break;

                case 'FG': # ProjectMatrix Discount margin

                break;

                case 'U1': # Item Tag
                case 'U2':
                case 'U3':
                case 'L1':
                case 'L2':
                case 'L3':
                case 'T1':
                case 'T2':
                case 'T3':
                case 'BLD':
                case 'FLR':
                case 'DEP':
                case 'PER':
                case 'TG': # Trac 1441

                if ( ! is_array($this->project['ITEM'][$this->open_item]['TAG']) )
                    $this->project['ITEM'][$this->open_item]['TAG'] = array();

                array_push($this->project['ITEM'][$this->open_item]['TAG'], array(
                    'TYPE'  =>  'Tag',
                    'VALUE' =>  $var[1]
                ) );

                if ( $var[0] == 'TG' )
                    $this->project['ITEM'][$this->open_item]['DECAL'] = $var[1];;

                break;

                case 'HT': # Item Height

                $this->project['ITEM'][$this->open_item]['DIMENSIONS']['HEIGHT'] = $var[1];;
                break;

                case 'WD': # Item width

                $this->project['ITEM'][$this->open_item]['DIMENSIONS']['WIDTH'] = $var[1];;
                break;

                case 'DP': # Item depth

                $this->project['ITEM'][$this->open_item]['DIMENSIONS']['DEPTH'] = $var[1];;
                break;

                case 'WT': # Item weight

                $this->project['ITEM'][$this->open_item]['DIMENSIONS']['WEIGHT'] = $var[1];;
                break;

                case 'VO': # Item volume

                $this->project['ITEM'][$this->open_item]['DIMENSIONS']['VOLUME'] = $var[1];;
                break;

                case 'NL':
                case 'NE':

                if ( ! is_array($this->project['ITEM'][$this->open_item]['NOTES']) )
                    $this->project['ITEM'][$this->open_item]['NOTES'] = array();

                array_push($this->project['ITEM'][$this->open_item]['NOTES'], array(
                'TYPE'  =>  ( $var[0] == 'NL' ? 'Sif' : 'Report' ),
                'VALUE' =>  $var[1]
                ) );
                $tmp_i = count($this->project['ITEM'][$this->open_item]['NOTES']);
                $this->project['ITEM'][$this->open_item]['NOTES'][$tmp_i]['TYPE'] = 'Sif';
                $this->project['ITEM'][$this->open_item]['NOTES'][$tmp_i]['VALUE'] = $var[1];;
                break;

                case 'OSL': # Option start

                $this->open_item_option = ( is_numeric($this->open_item_option) ? $this->open_item_option : 0 );
                $this->project['ITEM'][$this->open_item]['FINISH'][$this->open_item_option]['LEVEL'] = $var[1];;
                break;

                case 'ON': # Option code

                if ( ( ! is_numeric($this->open_item_option) || ! $this->project['ITEM'][$this->open_item]['FINISH'][$this->open_item_option]['LEVEL']) && ! is_numeric($this->project['ITEM'][$this->open_item]['FINISH'][$this->open_item_option]['LEVEL']) ) {

                    if ( is_numeric($this->open_item_option) )
                        $this->open_item_option++;
                    else
                        $this->open_item_option = 0;

                }

                $this->project['ITEM'][$this->open_item]['FINISH'][$this->open_item_option]['NAME'] = $var[1];;
                if ( $this->project['ITEM'][$this->open_item]['FINISH'][$this->open_item_option]['GROUP_DESCR'] == 'Panel Type' )
                    $this->project['ITEM'][$this->open_item]['PANEL_TYPE_DESCR'] = $var[1];;

                break;

                case 'OG': # Option group description

                $this->project['ITEM'][$this->open_item]['FINISH'][$this->open_item_option]['GROUP_DESCR'] = $var[1];;
                break;

                case 'OD': # Option description

                $this->project['ITEM'][$this->open_item]['FINISH'][$this->open_item_option]['DESCR'] = $var[1];;
                break;

                case 'OP': # Option upcharge price

                $this->project['ITEM'][$this->open_item]['FINISH'][$this->open_item_option]['PRICE'] = $var[1];;
                break;

                case 'END':

                if ( $var[1] == 'ISL' )
                    $this->open_option++;
                if ( $var[1] == 'OSL' )
                    $this->open_item_option++;
                if ( $var[1] == 'IT' )
                    $this->open_item++;
                if ( $var[1] == 'TP' )
                    $this->open_panel++;
                if ( $var[1] == 'GR' )
                    unset($this->open_panel, $this->open_option, $this->open_item);

                break;
            }

			if ( is_numeric($this->open_item) && ! isset($this->project['ITEM'][$this->open_item]['COMPLETE']) && isset($this->project['ITEM'][$this->open_item]['PN']) )
				$this->project['ITEM'][$this->open_item]['COMPLETE'] = 1;
		}

		if (!is_array($this->project))
			$this->error = "The file you selected appears to be invalid or empty. Please make sure the file is not corrupt and of a valid type.";

		if ( ! $this->project['SETUP']['ORIGINATING_SOFTWARE'] )
    		$this->project['SETUP']['ORIGINATING_SOFTWARE'] = 'SIF';
	}

	public function load_refs() {
		if ($this->import_from == 'SIF' || $this->import_from == 'PRJMTRX')
			return;

		$project =& $this->prj_data['_c'];
		$this->project = array();

		if (is_array($project)) {
			if ($this->import_from == 'EXPRESSXML') {
				$this->project['SETUP'] = array();
				$this->project['SETUP']['IMPORT_DATE'] = time();
				$this->project['SETUP']['FILE_NAME'] = basename($this->xmlFile['name']);
				if ($this->prj_data['_a']['name'])
					$this->project['SETUP']['NAME'] = $this->prj_data['_a']['name'];
				if ($this->prj_data['_a']['description'])
					$this->project['SETUP']['DESCR'] = $this->prj_data['_a']['name'];

				$this->project['SETUP']['ORIGINATING_SOFTWARE'] = "Design Express";
				$this->project['SETUP']['ORIGINATING_FORMAT'] = 'EXPRESSXML';
				if ($this->prj_data['_a']['version'])
					$this->project['SETUP']['ORIGINATING_VERSION'] = $this->prj_data['_a']['version'];

				//Load catalogs, vendors and products first
				foreach ($project as $key=>$val) {
					switch ($key) {
						case 'SETUP':
						$data =& $val['_a'];
						if ($data['currency'])
							$this->project['SETUP']['CURRENCY'] = $data['currency'];
						if (is_numeric($data['packaging']))
							$this->packaging = $data['packaging'];
						break;

						case 'CATALOGS':
						$data =& $val['_c'];
						if (is_array($data)) {
							$tmp_i = 0;
							foreach ($data as $data_arr) {
								if (!$data_arr[0])
									$data_arr = array($data_arr);

								while (list($i,$vals) = each($data_arr)) {
									$this->project['CATALOG'][$tmp_i]['UNIQUE_ID'] = $data_arr[$i]['_a']['id'];
									$this->project['CATALOG'][$tmp_i]['CODE'] = $data_arr[$i]['_a']['code'];
									$this->project['CATALOG'][$tmp_i]['EFFECTIVE_DATE'] = $data_arr[$i]['_a']['eff_date'];
									$this->project['CATALOG'][$tmp_i]['VERSION'] = $data_arr[$i]['_a']['version'];

									$this->data_ref['CATALOG']['UNIQUE_ID'][$data_arr[$i]['_a']['id']] = $tmp_i;
									$tmp_i++;
								}
							}
						}

						case 'PRODUCT_LINES':
						$data =& $val['_c'];
						if (is_array($data)) {
							$tmp_i = 0;
							foreach ($data as $data_arr) {
								if (!$data_arr[0])
									$data_arr = array($data_arr);

								while (list($i,$vals) = each($data_arr)) {
									$this->project['PRODUCT'][$tmp_i]['UNIQUE_ID'] = $data_arr[$i]['_a']['id'];
									$this->project['PRODUCT'][$tmp_i]['CODE'] = $data_arr[$i]['_a']['code'];
									$this->project['PRODUCT'][$tmp_i]['DESCR'] = $data_arr[$i]['_a']['desc'];
									$this->project['PRODUCT'][$tmp_i]['SECTOR'] = $data_arr[$i]['_a']['sector'];

									$this->data_ref['PRODUCT']['UNIQUE_ID'][$data_arr[$i]['_a']['id']] = $tmp_i;
									$tmp_i++;
								}
							}
						}

						break;

						case 'VENDORS':
						$data =& $val['_c'];
						if (is_array($data)) {
							$tmp_i = 0;
							foreach ($data as $data_arr) {
								if (!$data_arr[0])
									$data_arr = array($data_arr);

								while (list($i,$vals) = each($data_arr)) {
									$this->project['VENDOR'][$tmp_i]['UNIQUE_ID'] = $data_arr[$i]['_a']['id'];
									$this->project['VENDOR'][$tmp_i]['CODE'] = $data_arr[$i]['_a']['code'];
									$this->project['VENDOR'][$tmp_i]['DESCR'] = $data_arr[$i]['_a']['desc'];

									$this->data_ref['VENDOR']['UNIQUE_ID'][$data_arr[$i]['_a']['id']] = $tmp_i;
									$tmp_i++;
								}
							}
						}
						break;
					}
				}

				foreach ($project as $key=>$val) {
					switch ($key) {
						case 'PANEL_TYPES':
						$data =& $val['_c'];
						if (is_array($data)) {
							$tmp_i = 0;
							foreach ($data as $data_arr) {
								if (!$data_arr[0])
									$data_arr = array($data_arr);

								while (list($i,$vals) = each($data_arr)) {
									$this->project['PANEL_TYPE'][$tmp_i]['NAME'] = $data_arr[$i]['_a']['name'];
									$this->project['PANEL_TYPE'][$tmp_i]['DESCR'] = $data_arr[$i]['_a']['desc'];
									$this->project['PANEL_TYPE'][$tmp_i]['CATALOG_CODE'] = $this->project['CATALOG'][$this->data_ref['CATALOG']['UNIQUE_ID'][$data_arr[$i]['_a']['catalog_id']]]['CODE'];
									$this->project['PANEL_TYPE'][$tmp_i]['CATALOG_VERSION'] = $this->project['CATALOG'][$this->data_ref['CATALOG']['UNIQUE_ID'][$data_arr[$i]['_a']['catalog_id']]]['VERSION'];
									$this->project['PANEL_TYPE'][$tmp_i]['CATALOG_EFFECTIVE_DATE'] = $this->project['CATALOG'][$this->data_ref['CATALOG']['UNIQUE_ID'][$data_arr[$i]['_a']['catalog_id']]]['EFFECTIVE_DATE'];
									$this->project['PANEL_TYPE'][$tmp_i]['FRAME_ID'] = $data_arr[$i]['_a']['frame_id'];
									$this->project['PANEL_TYPE'][$tmp_i]['HEIGHT'] = $data_arr[$i]['_a']['height'];
									$this->project['PANEL_TYPE'][$tmp_i]['WIDTH'] = $data_arr[$i]['_a']['width'];
									$this->project['PANEL_TYPE'][$tmp_i]['TOTAL_HEIGHT'] = $data_arr[$i]['_a']['total_height'];
									$element =& $data_arr[$i]['_c']['TYPE_ELEMENT'];
									if (!$element[0])
										$element = array($element);

									for ($tmp_el_i = 0; $tmp_el_i < count($element); $tmp_el_i++) {
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['PN'] = $element[$tmp_el_i]['_a']['pn'];
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['DESCR'] = $element[$tmp_el_i]['_a']['desc'];
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['ORDER'] = $element[$tmp_el_i]['_a']['order'];
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['SIDE'] = $element[$tmp_el_i]['_a']['side'];
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['HEIGHT'] = $element[$tmp_el_i]['_a']['height'];
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['REQUIRED'] = $element[$tmp_el_i]['_a']['required'];
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['SINGLE'] = $element[$tmp_el_i]['_a']['single'];
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['MAIN_GROUP'] = $element[$tmp_el_i]['_a']['main_group'];
										$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['CURR_GROUP'] = $element[$tmp_el_i]['_a']['curr_group'];
										$finish =& $element[$tmp_el_i]['_c']['FINISHES']['_c']['FINISH'];
										if ($finish) {
											if (!$finish[0])
												$finish = array($finish);

											for ($tmp_fi_i = 0; $tmp_fi_i < count($finish); $tmp_fi_i++) {
												$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['FINISH'][$tmp_fi_i]['LEVEL'] = $finish[$tmp_fi_i]['_a']['level'];
												$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['FINISH'][$tmp_fi_i]['GROUP_DESCR'] = $finish[$tmp_fi_i]['_a']['group_desc'];
												$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['FINISH'][$tmp_fi_i]['NAME'] = $finish[$tmp_fi_i]['_a']['name'];
												$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['FINISH'][$tmp_fi_i]['DESCR'] = $finish[$tmp_fi_i]['_a']['desc'];
												$this->project['PANEL_TYPE'][$tmp_i]['ELEMENT'][$tmp_el_i]['FINISH'][$tmp_fi_i]['PRICE'] = $finish[$tmp_fi_i]['_a']['price'];
											}
										}
									}
									$tmp_i++;
								}
							}
						}
						break;

						case 'SPEC_GROUP':
						if (!$val[0])
							$val = array($val);

						for ($m = 0; $m < count($val); $m++) {
							$attr =& $val[$m]['_a'];
							$data =& $val[$m]['_c'];
							if (is_array($data)) {
								$spec_group_name = $attr['name'];
								$spec_group_qty = $attr['qty'];

								$item =& $data['ITEM'];
								if (!$item[0])
									$item = array($item);
								for ($tmp_i = 0; $tmp_i < count($item); $tmp_i++) {
									$this->project['ITEM'][$tmp_i]['SPEC_GROUP'] = $spec_group_name;
									$this->project['ITEM'][$tmp_i]['SPEC_GROUP_QTY'] = $spec_group_qty;
									$this->project['ITEM'][$tmp_i]['PN'] = $item[$tmp_i]['_a']['pn'];
									$this->project['ITEM'][$tmp_i]['ITEM_HASH'] = $item[$tmp_i]['_a']['item_hash'];
									$this->project['ITEM'][$tmp_i]['PACKAGING'] = $this->packaging;
									$this->project['ITEM'][$tmp_i]['DESCR'] = $item[$tmp_i]['_a']['desc'];
									$this->project['ITEM'][$tmp_i]['QTY'] = $item[$tmp_i]['_a']['qty'];
									$this->project['ITEM'][$tmp_i]['COMPLETE'] = $item[$tmp_i]['_a']['complete'];
									$this->project['ITEM'][$tmp_i]['CATALOG_CODE'] = $this->project['CATALOG'][$this->data_ref['CATALOG']['UNIQUE_ID'][$item[$tmp_i]['_a']['catalog_id']]]['CODE'];
									$this->project['ITEM'][$tmp_i]['CATALOG_EFFECTIVE_DATE'] = $this->project['CATALOG'][$this->data_ref['CATALOG']['UNIQUE_ID'][$item[$tmp_i]['_a']['catalog_id']]]['EFFECTIVE_DATE'];
									$this->project['ITEM'][$tmp_i]['CATALOG_VERSION'] = $this->project['CATALOG'][$this->data_ref['CATALOG']['UNIQUE_ID'][$item[$tmp_i]['_a']['catalog_id']]]['VERSION'];

									$this->project['ITEM'][$tmp_i]['PRJ'] = $item[$tmp_i]['_a']['prj'];
									$this->project['ITEM'][$tmp_i]['SPECIAL'] = $this->project['ITEM'][$tmp_i]['PRJ'];
									if ($item[$tmp_i]['_a']['prj_desc'])
										$this->project['ITEM'][$tmp_i]['SPECIAL'] = $item[$tmp_i]['_a']['prj_desc'];

									$this->project['ITEM'][$tmp_i]['ASSEMBLY'] = $item[$tmp_i]['_a']['assembly'];
									if ($item[$tmp_i]['_a']['panel_type']) {
										$this->project['ITEM'][$tmp_i]['PANEL_TYPE_ID'] = $item[$tmp_i]['_a']['panel_type'];
										$this->project['ITEM'][$tmp_i]['PANEL_TYPE_DESCR'] = $item[$tmp_i]['_a']['panel_type_desc'];
									}
									$this->project['ITEM'][$tmp_i]['PRODUCT_CODE'] = $this->project['PRODUCT'][$this->data_ref['PRODUCT']['UNIQUE_ID'][$item[$tmp_i]['_a']['prod_line_id']]]['CODE'];
									$this->project['ITEM'][$tmp_i]['PRODUCT_DESCR'] = $this->project['PRODUCT'][$this->data_ref['PRODUCT']['UNIQUE_ID'][$item[$tmp_i]['_a']['prod_line_id']]]['DESCR'];
									$this->project['ITEM'][$tmp_i]['PRODUCT_SECTOR'] = $this->project['PRODUCT'][$this->data_ref['PRODUCT']['UNIQUE_ID'][$item[$tmp_i]['_a']['prod_line_id']]]['SECTOR'];

									$this->project['ITEM'][$tmp_i]['VENDOR_CODE'] = $this->project['VENDOR'][$this->data_ref['VENDOR']['UNIQUE_ID'][$item[$tmp_i]['_a']['vendor_id']]]['CODE'];
									$this->project['ITEM'][$tmp_i]['VENDOR_DESCR'] = $this->project['VENDOR'][$this->data_ref['VENDOR']['UNIQUE_ID'][$item[$tmp_i]['_a']['vendor_id']]]['DESCR'];

									$this->project['ITEM'][$tmp_i]['ROUNDING'] = $item[$tmp_i]['_a']['rounding'];

									$price =& $item[$tmp_i]['_c']['PRICE']['_a'];
									$this->project['ITEM'][$tmp_i]['PRICE']['LIST'] = $price['list'];
									$this->project['ITEM'][$tmp_i]['PRICE']['BUY'] = $price['buy'];
									$this->project['ITEM'][$tmp_i]['PRICE']['SELL'] = $price['sell'];
									$this->project['ITEM'][$tmp_i]['PRICE']['CURRENCY'] = $price['currency'];

									$tag =& $item[$tmp_i]['_c']['IDENTIFICATION']['_a'];
									if ($tag['tag_1'])
										$this->project['ITEM'][$tmp_i]['TAG'][] = array('TYPE' => 'Tag', 'VALUE' => $tag['tag_1']);
									if ($tag['tag_2'])
										$this->project['ITEM'][$tmp_i]['TAG'][] = array('TYPE' => 'Tag', 'VALUE' => $tag['tag_2']);
									if ($tag['tag_3'])
										$this->project['ITEM'][$tmp_i]['TAG'][] = array('TYPE' => 'Tag', 'VALUE' => $tag['tag_3']);

									$dimensions =& $item[$tmp_i]['_c']['DIMENSIONS']['_a'];
									$this->project['ITEM'][$tmp_i]['DIMENSIONS']['HEIGHT'] = $dimensions['height'];
									$this->project['ITEM'][$tmp_i]['DIMENSIONS']['WIDTH'] = $dimensions['width'];
									$this->project['ITEM'][$tmp_i]['DIMENSIONS']['DEPTH'] = $dimensions['depth'];
									$this->project['ITEM'][$tmp_i]['DIMENSIONS']['WEIGHT'] = $dimensions['weight'];
									$this->project['ITEM'][$tmp_i]['DIMENSIONS']['VOLUME'] = $dimensions['volume'];
									$this->project['ITEM'][$tmp_i]['DIMENSIONS']['NUM_PKG'] = $dimensions['num_pkg'];
									$this->project['ITEM'][$tmp_i]['DIMENSIONS']['INST_TIME'] = $dimensions['inst_time'];
									$this->project['ITEM'][$tmp_i]['DIMENSIONS']['CAD_OFFSET'] = $dimensions['cad_offset'];

									$notes =& $item[$tmp_i]['_c']['ITEM_NOTES']['_c']['ITEM_NOTE'];
									if (!$notes[0])
										$notes = array($notes);

									for ($tmp_note_i = 0; $tmp_note_i < count($notes); $tmp_note_i++) {
										if ($notes[$tmp_note_i]['_a']['type'] == 'report' && $this->project['ITEM'][$tmp_i]['SPECIAL'] != 1)
											$this->project['ITEM'][$tmp_i]['SPECIAL'] = $notes[$tmp_note_i]['_v'];

										$this->project['ITEM'][$tmp_i]['NOTES'][$tmp_note_i]['TYPE'] = $notes[$tmp_note_i]['_a']['type'];
										$this->project['ITEM'][$tmp_i]['NOTES'][$tmp_note_i]['VALUE'] = $notes[$tmp_note_i]['_v'];
									}

									$finish =& $item[$tmp_i]['_c']['FINISHES']['_c']['FINISH'];
									if ($finish[0]) {
										for ($tmp_fi_i = 0; $tmp_fi_i < count($finish); $tmp_fi_i++) {
											$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['LEVEL'] = $finish[$tmp_fi_i]['_a']['level'];
											$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['GROUP_DESCR'] = $finish[$tmp_fi_i]['_a']['group_desc'];
											$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['NAME'] = $finish[$tmp_fi_i]['_a']['name'];
											$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['DESCR'] = $finish[$tmp_fi_i]['_a']['desc'];
											$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['PRICE'] = $finish[$tmp_fi_i]['_a']['price'];
											if ($finish[$tmp_fi_i]['_a']['panel'])
												$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['PANEL'] = $finish[$tmp_fi_i]['_a']['panel'];
										}
									} else {
										$tmp_fi_i = 0;
										$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['LEVEL'] = $finish['_a']['level'];
										$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['GROUP_DESCR'] = $finish['_a']['group_desc'];
										$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['NAME'] = $finish['_a']['name'];
										$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['DESCR'] = $finish['_a']['desc'];
										$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['PRICE'] = $finish['_a']['price'];
										if ($finish['_a']['panel'])
											$this->project['ITEM'][$tmp_i]['FINISH'][$tmp_fi_i]['PANEL'] = $finish['_a']['panel'];
									}
								}
							}
						}
						break;
					}
				}
			} elseif ($this->import_from == 'OFDAXML') {
				if ($project['ofda:PurchaseOrder'])
					$prefix = "ofda:";

				$po_data =& $project[$prefix.'PurchaseOrder']['_c'];
				$this->project['SETUP'] = array();
				$this->project['SETUP']['ORIGINATING_FORMAT'] = 'OFDAXML';
				$this->project['SETUP']['IMPORT_DATE'] = time();
				$this->project['SETUP']['NAME'] = $po_data[$prefix.'Project']['_c'][$prefix.'Title']['_v'];
				$this->project['SETUP']['PROJECT_FILE_LOCATION'] = $po_data[$prefix.'Project']['_c'][$prefix.'SpecifierFile']['_c'][$prefix.'Name']['_v'];
				$this->project['SETUP']['ORIGINATING_VERSION'] = $po_data[$prefix.'Project']['_c'][$prefix.'SpecifierFile']['_c'][$prefix.'SpecifierSoftware']['_v'];
				$this->project['SETUP']['FILE_NAME'] = basename($this->xmlFile['name']);
				$contractCode = $po_data[$prefix.'Header']['_c'][$prefix.'Contract']['_c'][$prefix.'Code']['_v'];

				//Catalog, Vendor and Product first
				foreach ($project as $key=>$val) {
					switch ($key) {
						case $prefix.'Header':
						$data =& $val['_c'];
						if ($data[$prefix.'Currency'])
							$this->project['SETUP']['CURRENCY'] = $data[$prefix.'Currency']['_v'];
						if ($data[$prefix.'Language'])
							$this->project['SETUP']['LANGUAGE'] = $data[$prefix.'Language']['_v'];

						//Vendor details
						if ($data[$prefix.'Vendor']['_c'][$prefix.'Code']) {
							$vendor = $data[$prefix.'Vendor']['_c'][$prefix.'Code'];
							if (!$vendor[0])
								$vendor = array($vendor);

							for ($i = 0; $i < count($vendor); $i++) {
								$this->project['VENDOR'][$i]['CODE'] = $vendor[$i]['_v'];
								$this->project['VENDOR'][$i]['UNIQUE_ID'] = $vendor[$i]['_v'];
								$this->project['VENDOR'][$i]['DESCR'] = $data[$prefix.'Vendor']['_c'][$prefix.'Name'][$i]['_v'];

								$this->data_ref['VENDOR']['UNIQUE_ID'][$vendor[$i]['_v']] = $i;
							}
						}
						//Catalog details
						if ($data[$prefix.'Catalog']['_c'][$prefix.'Code']) {
							$catalog = $data[$prefix.'Catalog']['_c'][$prefix.'Code'];
							if (!$catalog[0])
								$catalog = array($catalog);

							for ($i = 0; $i < count($catalog); $i++) {
								$this->project['CATALOG'][$i]['CODE'] = $catalog[$i]['_v'];
								$this->project['CATALOG'][$i]['UNIQUE_ID'] = $catalog[$i]['_v'];
								$this->project['CATALOG'][$i]['VERSION'] = $data[$prefix.'Catalog']['_c'][$prefix.'Version'][$i]['_v'];

								$this->data_ref['CATALOG']['UNIQUE_ID'][$catalog[$i]['_v']] = $i;
							}
						}
						break;
					}
				}
				foreach ($project as $key => $val) {
					switch ($key) {
						case $prefix.'PurchaseOrder':
						//This may cause a problem: add ['_c'] after line 703
						$item =& $po_data[$prefix.'OrderLineItem'];
						if (!$item[0])
							$item = array($item);

						for ($i = 0; $i < count($item); $i++) {
							$detail =& $item[$i]['_c'];

							if ($detail[$prefix.'SpecGroup']) {

							}

							$spec_detail =& $detail[$prefix.'SpecItem']['_c'];

							// If SpecItem element does not exist, grab the description from the SpecMisc element if it exists.  Added for Trac #848
							if ($spec_detail) {
								$this->project['ITEM'][$i]['PN'] = $spec_detail[$prefix.'Number']['_v'];
								$this->project['ITEM'][$i]['DESCR'] = $spec_detail[$prefix.'Description']['_v'];
								$this->project['ITEM'][$i]['CATALOG_CODE'] = $spec_detail[$prefix.'Catalog']['_c'][$prefix.'Code']['_v'];
								$this->project['ITEM'][$i]['PACKAGING'] = $spec_detail[$prefix.'PackageCount']['_v'];
							} else {
								$this->project['ITEM'][$i]['DESCR'] = $detail[$prefix.'SpecMisc']['_c'][$prefix.'Description']['_v'];
							}

							if ($this->project['SETUP']['CURRENCY'])
                                $this->project['ITEM'][$i]['PRICE_ZONE'] = $this->project['SETUP']['CURRENCY'];

							$this->project['ITEM'][$i]['CONTRACT_CODE'] = $contractCode;
							$this->project['ITEM'][$i]['QTY'] = $detail[$prefix.'Quantity']['_v'];
							$this->project['ITEM'][$i]['COMPLETE'] = ($detail[$prefix.'Status']['_v'] == 'Incomplete' ? 0 : 1);
							$this->project['ITEM'][$i]['CATALOG_VERSION'] = $this->project['CATALOG'][$this->data_ref['CATALOG']['CODE'][$spec_detail[$prefix.'Catalog']['_c'][$prefix.'Code']['_v']]]['VERSION'];
							$this->project['ITEM'][$i]['VENDOR_CODE'] = $detail[$prefix.'VendorRef']['_a']['EnterpriseRef'];

							$price =& $detail[$prefix.'Price']['_c'];
							$this->project['ITEM'][$i]['PRICE']['LIST'] = $price[$prefix.'PublishedPrice']['_v'];
							$this->project['ITEM'][$i]['PRICE']['BUY'] = $price[$prefix.'OrderDealerPrice']['_v'];
							$this->project['ITEM'][$i]['PRICE']['SELL'] = $price[$prefix.'EndCustomerPrice']['_v'];

							if ( ! is_array($this->project['ITEM'][$i]['PRICE']['BUY_DISCOUNT']) )
                                $this->project['ITEM'][$i]['PRICE']['BUY_DISCOUNT'] = array();

                            array_push( $this->project['ITEM'][$i]['PRICE']['BUY_DISCOUNT'], array(
                                'DISCOUNT'  =>  $price["{$prefix}OrderDealerDiscount"]['_v']
                            ) );

							$this->project['ITEM'][$i]['PRICE']['LIST_DISCOUNT'] = $price[$prefix.'EndCustomerDiscount']['_v'];
							$this->project['ITEM'][$i]['PRICE']['DISCOUNT_CATEGORY'] = $price[$prefix.'DiscountCategory']['_v'];

							$tag =& $detail[$prefix.'Tag'];
							if (!$tag[0])
								$tag = array($tag);

							for ($j = 0; $j < count($tag); $j++) {
								if (trim($tag[$j]['_c'][$prefix.'Value']['_v']))
									$this->project['ITEM'][$i]['TAG'][] = array('TYPE' => ($tag[$j]['_c'][$prefix.'Type']['_v'] == 'Generic' ? 'Tag' : $tag[$j]['_c'][$prefix.'Type']['_v']), 'VALUE' => $tag[$j]['_c'][$prefix.'Value']['_v']);
							}

							$finish =& $spec_detail[$prefix.'Option'];
							if (!$finish[0])
								$finish = array($finish);

							// Use the recursive function to parse all finishes.  Added for trac #847
							$this->finish_i = 0;
							for ($j = 0; $j < count($finish); $j++) {
								$this->parse_finishes($i,$finish[$j],$this->finish_i,$prefix);
							}

						}
						break;

						case $prefix.'Vendor':
						$data =& $val['_c'];
						$data =& $val['_c'];
						if (!$data[0])
							$data = array($data);

						for ($i = 0; $i < count($data); $i++) {
							$this->project['VENDOR'][$i]['CODE'] = $data[$i]['_c']['Code']['_v'];
							$this->project['VENDOR'][$i]['NAME'] = $data[$i]['_c']['Name']['_v'];
							$this->project['VENDOR'][$i]['VERSION'] = $data[$i]['_c']['Version']['_v'];

							$this->data_ref['VENDOR']['CODE'][$data[$i]['_c']['Code']['_v']] = $i;
						}
						break;

						case $prefix.'Catalog':
						$data =& $val['_c'];
						if (!$data[0])
							$data = array($data);

						for ($i = 0; $i < count($data); $i++) {
							$this->project['CATALOG'][$i]['CODE'] = $data[$i]['_c']['Code']['_v'];
							$this->project['CATALOG'][$i]['NAME'] = $data[$i]['_c']['Name']['_v'];
							$this->project['CATALOG'][$i]['VERSION'] = $data[$i]['_c']['Version']['_v'];

							$this->data_ref['CATALOG']['CODE'][$data[$i]['_c']['Code']['_v']] = $i;
						}
						break;
					}
				}
			}
		}
	}


	private function _del_p(&$ary) {
		foreach ($ary as $k => $v) {
			if ($k === '_p')
				unset($ary[$k]);
			elseif (is_array($ary[$k]))
				$this->_del_p($ary[$k]);
		}
	}

	/*preclude return with: <?xml version=\"1.0\" encoding=\"UTF-8\"?>\n*/
	public function ary2xml($cary, $d=0, $forcetag='') {
		$res = array();
		foreach ($cary as $tag => $r) {
			if (isset($r[0]))
				$res[] = $this->ary2xml($r,$d,$tag);
			else {
				if ($forcetag)
					$tag = $forcetag;
				$sp = str_repeat("\t",$d);
				$res[] = "$sp<$tag";
				if (isset($r['_a'])) {
					foreach ($r['_a'] as $at => $av)
						$res[] = " $at=\"$av\"";
				}
				$res[] = ">" . (isset($r['_c']) ? "\n" : '');
				if (isset($r['_c']))
					$res[] = $this->ary2xml($r['_c'],$d+1);
				elseif (isset($r['_v']))
					$res[] = $r['_v'];
				$res[] = (isset($r['_c']) ? $sp : '')."</$tag>\n";
			}
		}
		return implode('',$res);
	}

	public function ins2ary(&$ary, $element, $pos) {
		$ar1 = array_slice($ary,0,$pos);
		$ar1[] = $element;
		$ary = array_merge($ar1,array_slice($ary,$pos));
	}

	public function load_header() {
		$project = $this->xml->xpath('//PROJECT');
		for ($i = 0; $i < count($project); $i++) {
			$project_info = (array)$project[$i];
			foreach ($project_info as $key=>$val) {
				switch ($key) {
					case 'SPEC_GROUP':
					$group_data = (array)$val;

					//If we have multiple groups:
					if ($group_data[0]) {
						for ($j = 0; $j < count($group_data); $j++) {
							$groups = (array)$group_data[$j];

							if (!$groups[0])
								$this->prj_data['groups'][] = $groups;

						}
					} else
						$this->prj_data['groups'][] = $group_data;

					break;

					default:
					if (count($val)) {
						foreach ($val as $val_key=>$val_val) {
							$data = current((array)$val_val);
							if (is_array($data) && array_key_exists('id',$data))
								$this->prj_data['ref'][strtolower($key)][$data['id']] = $data;
							else
								$this->prj_data['head'][strtolower($key)] = (array)$val;
						}
					} else
						$this->prj_data['head'][strtolower($key)] = current((array)$val);

					break;
				}
			}
		}
	}

	/**
	 * Use recursion to correctly parse all the options and finishes for an line item in the OFDA XML.
	 * Added for trac #847.
	 *
	 * @param int $i
	 * @param array $finish
	 * @param int $finish_i
	 * @param string $prefix
	 */
	public function parse_finishes($i, $finish, $finish_i,$prefix) {
		$this->project['ITEM'][$i]['FINISH'][$finish_i]['LEVEL'] = $finish['_a']['Level'];
		$this->project['ITEM'][$i]['FINISH'][$finish_i]['SEQUENCE'] = $finish['_a']['Sequence'];
		$this->project['ITEM'][$i]['FINISH'][$finish_i]['NAME'] = $finish['_c'][$prefix.'Code']['_v'];
		$this->project['ITEM'][$i]['FINISH'][$finish_i]['GROUP_CODE'] = $finish['_c'][$prefix.'GroupCode']['_v'];
		$this->project['ITEM'][$i]['FINISH'][$finish_i]['GROUP_DESCR'] = $finish['_c'][$prefix.'GroupDescription']['_v'];
		$this->project['ITEM'][$i]['FINISH'][$finish_i]['DESCR'] = $finish['_c'][$prefix.'Description']['_v'];
		$this->finish_i++;
		if ($finish['_c'][$prefix.'Option']['_a']) {
			$this->parse_finishes($i,$finish['_c'][$prefix.'Option'],$this->finish_i,$prefix);
		} else
			return ;
	}


}
?>