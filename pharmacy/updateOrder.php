<?php
	include("conf.php");
	$qry = "UPDATE tbltransactiondetails SET fldStatus = 'PAID' WHERE fldStatus = 'UNPAID'";
	$result = mysql_query($qry) or die (mysql_error());
	mysql_close($connection);
	
	echo "<meta http-equiv='refresh' content='3;url=cart.php'><h4 style = 'text-align:center; color:#000000; font-weight:normal; font-family:verdana; margin-left:0px;'>Customer Order Processed!<br /><a href = 'salesOrderEntry.php' style = 'margin-left:0px; color:#FF0000'>Click here if it does not redirect in 5 second</a></h4>";

?>