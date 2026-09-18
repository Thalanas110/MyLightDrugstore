<?php
	include("conf.php");
	$qry = "DELETE FROM tbluserstaff WHERE id = '$_GET[userid]'";
	$result = mysql_query($qry) or die (mysql_error());
	mysql_close($connection);
	
	header("location:userAccounts.php");
	
?>