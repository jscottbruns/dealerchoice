#!/usr/bin/perl
use Spreadsheet::WriteExcel;
use DBI;
use Config::Abstract::Ini;
use Getopt::Long;
use HTTP::Date;
use strict;

my ($report_hash,$tmp_file,$dbh,$verbose,$format,$ini,$workbook);
$format = 'excel';
$verbose = 0;
our (%args,%ini_database) = ();
my @proposal_filter;

GetOptions ("report_hash|r=s"    =>  \$report_hash,
            "tmp_file|t=s"       =>  \$tmp_file,
            "format|f:s"         =>  \$format,
            "verbose|v"          =>  \$verbose)or Help();
init();

sub init()
{
	my $settingsfile = 'include/config/dealerchoice.ini';
	eval { $ini = new Config::Abstract::Ini($settingsfile) };
	if ($@) {
        print "Unable to read system configuration file.";
        exit 1;
	}
	eval { %ini_database = $ini->get_entry('database') };
	if ($@) {
        print "Unable to read database configuration.";
	}

	init_db();
	exec_xls();
}

sub init_db
{
    $dbh = DBI->connect("DBI:mysql:database=".$ini_database{'DefaultDatabase'}.";host=".$ini_database{'DatabaseHost'},$ini_database{'DatabaseUser'},$ini_database{'DatabasePass'},{PrintError => 0, RaiseError => 1}) or die $DBI::errstr;

    my $sth = $dbh->prepare("SELECT str AS queryopts
                             FROM `reports`
                             WHERE `report_hash` = '$report_hash'");
    eval { $sth->execute() };
    if ($@) {
        print "Query Error: $DBI::err - $DBI::errstr";
        exit 1;
    }
    my $r = $sth->fetchrow_arrayref();
    (my $str) = @$r;
    if (my @argstr = split(/\|/,$str)) {
	    foreach (@argstr) {
	        my ($var,$val) = split('=',$_);
	        $args{$var} = $val;
	    }
    } else {
        print "Invalid report hash. Can't find report in database.";
        exit 1;
    }
}

sub exec_xls
{
    my $workbook = Spreadsheet::WriteExcel->new($tmp_file);
    my $sheet1 = $workbook->add_worksheet();

    my $rightalign = $workbook->add_format();
    $rightalign->set_align('right');

    my $bold = $workbook->add_format();
    $bold->set_bold();

    $sheet1->set_column(0, 0, 15); # Proposal number col format
    $sheet1->set_column(1, 1, 25); # Proposal descr col format
    $sheet1->set_column(2, 2, 15); # Customer col format
    $sheet1->set_column(3, 3, 17); # PO number
    $sheet1->set_column(4, 4, 12); # Last entry
    $sheet1->set_column(5, 12, 12, $rightalign); # Amount fields
    $sheet1->activate();

    my %cols = ( 0  => {"colname"              =>  "proposal_no",
    	                "label"                =>  "Proposal No"},
	             1  => {"colname"              =>  "proposal_descr",
	             	    "label"                =>  "Proposal Descr"},
                 2  => {"colname"              =>  "customer_name",
                        "label"                =>  "Customer"},
                 3  => {"colname"              =>  "po_no",
                        "label"                =>  "PO No"},
                 4  => {"colname"              =>  "last_entry",
                        "label"                =>  "Last Entry"},
                 5  => {"colname"              =>  "order_amount",
                        "label"                =>  "PO Amount",
                        "type"                 =>  "int"},
                 6  => {"colname"              =>  "total_cost",
                        "label"                =>  "Total Cost",
                        "type"                 =>  "int"},
                 7  => {"colname"              =>  "total_sell",
                        "label"                =>  "Total Sell",
                        "type"                 =>  "int"},
                 8  => {"colname"              =>  "total_profit",
                        "label"                =>  "Total Profit",
                        "type"                 =>  "formula",
                        "formula"              =>  "subtract",
                        "formulacols"          =>  "H|G"},
                 9  => {"colname"              =>  "ap_wip_total",
                        "label"                =>  "WIP Debits",
                        "type"                 =>  "int"},
                 10  => {"colname"              =>  "ar_wip_total",
                        "label"                =>  "WIP Credits",
                        "type"                 =>  "int"},
                 11  => {"colname"              =>  "reconciled",
                        "label"                =>  "Reconciled",
                        "type"                 =>  "int"},
                 12 => {"colname"              =>  "wip_net",
                        "label"                =>  "Balance",
                        "type"                 =>  "formula",
                        "formula"              =>  "subtract2",
                        "formulacols"          =>  "J|K|L",
                        "format"               =>  $bold});
    my %bindvalues;
    my %attr = (
        PrintError  =>  0,
        RaiseError  =>  1
    );


    my $rowcount = 0;
	for my $col ( sort {$a <=> $b} keys %cols) {
		$sheet1->write($rowcount,$col,$cols{$col}->{"label"});
	}
	if ($args{'proposal_filter'}) {
        @proposal_filter = split("&",$args{'proposal_filter'});
	}
    $dbh->do("CALL wip_report(
        '$args{default_wip_account}', " .
        ( $args{'sort_from_date'} ?
            "'$args{sort_from_date}'" : "NULL"
        ) . ", " .
        ( $args{'sort_to_date'} ?
            "'$args{sort_to_date}'" : "NULL"
        ) . ", " .
        ( $args{'wip_balance'} ?
            "$args{wip_balance}" : "0"
        ) . "
    )") or die $dbh->errstr;

    my $sth = $dbh->prepare("SELECT t1.po_hash , t1.proposal_hash , t1.proposal_no , t1.ap_invoice_hash , t1.ar_invoice_hash ,
							 SUM(t1.ap_total) AS ap_wip_total , SUM(t1.ar_total) AS ar_wip_total , SUM(t1.reconciled_total) AS reconciled
							 FROM _tmp_reports_data_wip t1".($args{'proposal_filter'} ? "
							 WHERE t1.proposal_hash IN IN (".join(" , ",@proposal_filter).")" : "")."
							 GROUP BY t1.po_hash , t1.proposal_hash ".($args{'wip_balance'} == 1 || $args{'wip_balance'} == 2 ? "
							     HAVING (IFNULL(ap_wip_total,0) - IFNULL(ar_wip_total,0) - IFNULL(reconciled,0))".($args{'wip_balance'} == 1 ? " != 0 " : " = 0") : "")."
							 ORDER BY t1.proposal_no , t1.po_hash ASC");
    eval { $sth->execute() };
    if ($@) {
        print "Query Error: $DBI::err - $DBI::errstr";
        exit 1;
    }

    $sth->bind_columns( \( @bindvalues{ @{$sth->{NAME_lc} } } ));
    while (my $row = $sth->fetch()) {
    	$rowcount++;

        delete($bindvalues{'last_entry'});
	    ( $bindvalues{'order_amount'},$bindvalues{'po_no'},$bindvalues{'total_cost'},$bindvalues{'total_sell'},
	      $bindvalues{'proposal_descr'},$bindvalues{'customer_name'}) =
	      $dbh->selectrow_array("SELECT t1.order_amount , t1.po_no , SUM(t2.cost * t2.qty) AS total_cost ,
	                             SUM(t2.sell * t2.qty) AS total_sell , t3.proposal_descr , t4.customer_name
                                 FROM purchase_order t1
                                 LEFT JOIN line_items t2 ON t2.po_hash = t1.po_hash
                                 LEFT JOIN proposals t3 ON t3.proposal_hash = t1.proposal_hash
	                             LEFT JOIN customers t4 ON t4.customer_hash = t3.customer_hash
	                             WHERE t1.po_hash = '".$bindvalues{'po_hash'}."'
	                             GROUP BY t1.po_hash");
        if ($bindvalues{'po_hash'}) {
            my $sth_2 = $dbh->prepare("SELECT DISTINCT t1.invoice_date
                                       FROM vendor_payables AS t1
                                       LEFT JOIN vendor_payable_expenses t2 ON t2.invoice_hash = t1.invoice_hash
                                       WHERE t1.po_hash = '".$bindvalues{'po_hash'}."' AND t2.account_hash = '".$args{'default_wip_account'}."' AND t1.deleted = 0
                                       UNION ALL
                                       SELECT DISTINCT t1.invoice_date
                                       FROM customer_invoice AS t1
                                       LEFT JOIN line_items t2 ON t2.invoice_hash = t1.invoice_hash
                                       WHERE t2.po_hash = '".$bindvalues{'po_hash'}."' AND t2.wip_account_hash = '".$args{'default_wip_account'}."' AND t2.direct_bill_amt != 'C' AND t1.deleted = 0");
            if ($sth_2->execute) {
                my @ref = $sth_2->fetchall_arrayref;
            	foreach $row (@ref) {
            		if (!$bindvalues{'last_entry'} || ($bindvalues{'last_entry'} && str2time($row->[$_][0]) > str2time($bindvalues{'last_entry'}))) {
                        $bindvalues{'last_entry'} = $row->[$_][0];
            		}
            	}
            }
            $sth_2->finish;
        }


	    for my $col ( sort {$a <=> $b} keys %cols) {
            if ($cols{$col}->{ 'type' } eq 'int') {
                if ($bindvalues{$cols{$col}->{ 'colname' }}) {
                    $sheet1->write($rowcount,$col,$bindvalues{$cols{$col}->{ 'colname' }});
                }
            } elsif ($cols{$col}->{ 'type' } eq 'formula') {
                if ($cols{$col}->{ 'formula' } == 'subtract') {
                    my @subcols = split(/\|/,$cols{$col}->{ 'formulacols' });
                    my $formrow = ($rowcount + 1);
                    my @formstr = ();
                    foreach (@subcols) {
                    	push(@formstr,$_.$formrow);
                    }
                    $sheet1->write_formula($rowcount,$col,"=".join(" - ",@formstr),$cols{$col}->{ 'format' });
                }
            } else {
                if ($bindvalues{$cols{$col}->{ 'colname' }}) {
                    $sheet1->write($rowcount,$col,$bindvalues{$cols{$col}->{ 'colname' }});
                }
            }
	    }
    }

    $sth->finish;

    my $final_row = ++$rowcount + 2;
    $sheet1->write_formula($final_row,5,"SUM(F2:F$rowcount)",$bold);
    $sheet1->write_formula($final_row,6,"SUM(G2:G$rowcount)",$bold);
    $sheet1->write_formula($final_row,7,"SUM(H2:H$rowcount)",$bold);
    $sheet1->write_formula($final_row,8,"SUM(I2:I$rowcount)",$bold);
    $sheet1->write_formula($final_row,9,"SUM(J2:J$rowcount)",$bold);
    $sheet1->write_formula($final_row,10,"SUM(K2:K$rowcount)",$bold);
    $sheet1->write_formula($final_row,11,"SUM(L2:L$rowcount)",$bold);
    $sheet1->write_formula($final_row,12,"SUM(M2:M$rowcount)",$bold);

    #my ($sec,$min,$hour,$mday,$mon,$year,$wday,$yday,$isdst) = localtime(time);
    #$newfile = "WIPDETAIL_".$year.$mon.$mday.".xls";

    #`mv $tmp_file $newfile`;

    #print $newfile;
    exit 1;
}

sub Help {
    print <<__HELP__;
DealerChoice DataDump

Export report data into Excel Spreadsheet format

    $0 --report_hash REPORT_HASH --tmp_file OUTPUT_FILE [--format [excel|csv]] [--

__HELP__

    # Don't display standard PerlSvc help text
    $verbose = 0;
}