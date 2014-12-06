<?php
include('include/common.php');

$force_redirect['redirect_delay'] = 2;
$force_redirect['destination'] = ($_SERVER['HTTPS'] ? LINK_ROOT_SECURE : LINK_ROOT);
$login_class->user_logout();

include('include/header.php');

echo "
<table class=\"tborder\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
	<tr>
		<td class=\"tcat\" style=\"padding:7px;\"><div id=\"content_title\">DealerChoice :: Logout</div></td>
		<td class=\"tcat\" style=\"vertical-align:bottom;padding:0;text-align:left;\" nowrap>
			<div id=\"content_nav\"></div>
		</td>
	</tr>
	<tr>
		<td class=\"panelsurround\" colspan=\"2\" >
			<div class=\"panel\" >
				<div style=\"padding:10px;text-align:center;\" class=\"fieldset\">
					<table cellspacing=\"1\" cellpadding=\"5\" style=\"background-color:#8c8c8c;width:90%;\" >
						<tr>
							<td style=\"background-color:#ffffff;padding:30px 0;width:100%;text-align:center;\">
								<h3 style=\"color:#00477f;font-weight:bold; \">
									Signing Out of the DealerChoice Network.
								</h3>
								<img src=\"images/animated_timer_bar.gif\" />
							</td>
						</tr>
					</table>
				</div>		
			</div>
		</td>
	</tr>
</table>";

include('include/footer.php');
?>