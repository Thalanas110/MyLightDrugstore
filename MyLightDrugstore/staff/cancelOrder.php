<?php
	//heh//-----------select tbldruginfo INNER JOIN tbltransactiondetails---------->
	include("conf.php");
	$qry = "SELECT tbldruginfo.fldQuantity as currentInventory,tbltransactiondetails.fldQuantity as currentQuantity FROM tbldruginfo INNER JOIN tbltransactiondetails ON tbldruginfo.fldDrugInfoID = tbltransactiondetails.fldDrugInfoID WHERE tbldruginfo.fldDrugInfoID AND tbltransactiondetails.fldDrugInfoID = '$_GET[transaction]'";
	//$qry = "SELECT * FROM tbldruginfo INNER JOIN tbltransactiondetails ON tbldruginfo.fldDrugInfoID = tbltransactiondetails.fldDrugInfoID WHERE fldDrugInfoID = '17'";
	$result = mysql_query($qry) or die (mysql_error());
	if(mysql_num_rows($result))
		{
			while($row = mysql_fetch_array($result))
				{
					$currentInventory = $row['currentInventory'];
					$currentQuantity = $row['currentQuantity'];
					
				}
				
		}
	mysql_close($connection);
	//heh//----------update druginfo------->
	echo $returnQuantity = $currentInventory + $currentQuantity;

	include("conf.php");
	$qry = "UPDATE tbldruginfo SET fldQuantity = $returnQuantity WHERE fldDrugInfoID = '$_GET[transaction]'";
	$result = mysql_query($qry) or die (mysql_error());
	mysql_close($connection);

	//heh//----------delete transaction details------->
	include("conf.php");
	$qry = "DELETE FROM tbltransactiondetails WHERE fldTransactionDetailsID = '$_GET[transactionDetails]'";
	$result = mysql_query($qry) or die (mysql_error());
	mysql_close($connection);
	
	header("location: cart.php");
?>