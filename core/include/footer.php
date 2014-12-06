<?php
// DealerChoice V2.9.1 - Buildtime 2014-01-22 22:24:28 Rev87
// Please add a comment if file is manually changed
if ( ! defined('PUN') )
	exit;

?>
		</td>
	</tr>
	<tr>
		<td>
			<table class="footer_tbl">
				<tr>
					<td></td>
					<td>
						<table class="footer_left" style="width:100%;text-align:center " >
							<tr>
								<td style="text-align:left;padding-left:10px;" rowspan="2"></td>
								<td style="text-align:center;">&nbsp;</td>
								<td style="text-align:right;padding-right:15px;" rowspan="2"></td>
							</tr>
							<tr>
								<td style="font-size:9pt;text-align:center">
								    <div id="jscript_err" style="display:none;"></div>
									<br />
									DealerChoice, LLC : Engineering Efficiency
									<br />
									<a href="http://www.dc-sysllc.com">www.dc-sysllc.com</a>

									<?php
									echo "
									<div style=\"padding-top:5px;font-size:7pt;\">".(defined('LICENSEE') && defined('LICENSE_NO') ?
    									"Licensed To: ".LICENSEE." (".LICENSE_NO.")" : "Unlicensed").(defined('CURRENT_VERSION') ?
        									"<div style=\"margin-top:3px;\">Version " . CURRENT_VERSION . "</div>" : NULL)."

        							</div>";
									if ($_SESSION['id_hash'] && $_SESSION['use_db'])
										echo "<div style=\"padding-top:5px;font-size:7pt;\">Database: ".$_SESSION['use_db']."</div>";

                                    if (defined('DEBUG'))
										echo "<div id=\"debug_info_holder\"></div>";
									?>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>
<?php
ob_end_flush();

// Close the db connection (and free up any result data)
$db->close();

// Spit out the page
exit();
?>