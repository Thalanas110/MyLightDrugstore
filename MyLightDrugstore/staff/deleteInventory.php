<?php
	include("conf.php");
	
	$qry = "DELETE FROM tbldruginfo WHERE fldDrugInfoID = '$_GET[druginfoID]'";
	$result = mysql_query($qry) or die (mysql_error());
	header("location:index.php");
	mysql_close($connection);
?>