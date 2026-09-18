<?php
	session_start(); 
	include("header.php"); 
?>

<div id="content">
	<br />
	
	<form name = "itemCodeForm" action = "<?php echo $_SERVER[PHP_SELF]?>" method = "POST" onsubmit="return validate_itemCode_Form()">
	<table id = "salesTable">
	<tr>
		<td>
			<font size = "2px;" face = "verdana">Item Code:</font>
		</td>
		<td>
			<input type = "text" id = "itemCode" name = "itemCode">
			<input type = "submit" name = "viewOrder" value = "View">
			<input type = "hidden" name = "txtItemCode" value = "<?php echo $_POST['itemCode']; ?>">
		</td>
		<td>
			<script type="text/javascript">
			// Popup window code for customer information ----- heh
			function newPopup(url) {
				popupWindow = window.open(
					url,'popUpWindow','height=500,width=1000,left=140,top=100,resizable=yes,scrollbars=yes,toolbar=no,menubar=yes,location=no,directories=no,status=yes')
			}
			</script>
			<a href="JavaScript:newPopup('popUpSalesOrderEntry.php');" title = "Search Item Code"><img src = "images/search.png" style = "width:20px; height:20px;"></a>
		</td>
	</tr>
	</table>
	<hr />
	<br />
	<font face = "verdana">List of item Code Information:</font>
	<br /><br />	
	<div id = "listOfItemCodeInformation">
		<?php
		$itemCode = $_POST['itemCode'];
		if($_POST['viewOrder'])
			{
			
			include('conf.php');
			$qry2 = "SELECT * FROM tbldruginfo WHERE fldDrugInfoID = $itemCode";
			$result2 = mysql_query($qry2) or die (mysql_error());
			if(mysql_num_rows($result2))
				{
					while($row = mysql_fetch_assoc($result2))
						{
							$_SESSION['price'] = $row['fldPrice'];
							$_SESSION['itemCode'] = $itemCode;
							$_SESSION['quantity'] = $row['fldQuantity'];
							?>
								<table border = "0" class = "listInformation">
									<tr>
										<td>
											Generic Name
										</td>
										<td>
											<input type = "text" name = "genericName" value = "<?php echo $row['fldGenericName']; ?>" readonly>
										</td>
										<td>
											Brand Name
										</td>
										<td>
											<input type = "text" name = "brandName" value = "<?php echo $row['fldBrandName']; ?>" readonly>
										</td>
										<td>
											Description
										</td>
										<td>
											<input type = "text" name = "description" value = "<?php echo $row['fldDescription']; ?>" readonly>
										</td>
									</tr>
									<tr>
										<td>
											Suspension
										</td>
										<td>
											<input type = "text" name = "suspension" value = "<?php echo $row['fldSuspension']; ?>" readonly>
										</td>
										<td>
											Dosage
										</td>
										<td>
											<input type = "text" name = "dosage" value = "<?php echo $row['fldDosage']; ?>" readonly>
										</td>
										<td>
											Price
										</td>
										<td>
											<input type = "text" name = "price" value = "<?php echo $row['fldPrice']; ?>" readonly>
										</td>
									</tr>
									<tr>
										<td>
											Remaining Quantity
										</td>
										<td>
											<input type = "text" name = "quantity" value = "<?php echo $row['fldQuantity']; ?>" readonly>
										</td>
										<td>
											Location
										</td>
										<td>
											<input type = "text" name = "location" value = "<?php echo $row['fldLocation']; ?>" readonly>
										</td>
										<td colspan = "2">
											&nbsp;
										</td>
									</tr>
								</table>
								<a href = "salesOrderEntry.php" style = "float:right; margin-top:-10px;">(x)close</a>
							<?php
							
				}
			}
		}
	?>
	</div>
	<div id = "clear"></div>
	</form>
	<br />
	<?php
		if(!$_POST['addOrder'])
			{
	?>
	<form name = "quantityForm" action = "<?php echo $_SERVER[PHP_SELF]?>" method = "POST" onsubmit="return validate_quantity_Form()">
	<table id = "salesTable">
		<tr>
			<td>
				<font face = "verdana">Enter your order quantity:</font>
			</td>
			<td>
				<input type = "text" name = "txtYourQuantity" style = "border:1px solid red;">
			</td>
			<td>
				<input type = "submit" name = "addOrder" value = "Add Order">
			</td>
		</tr>
	</table>
	
	<?php
			}
			else
			{
						$priceVar = addslashes(strip_tags(strtoupper($_SESSION['price'])));
						//$transactionVar = addslashes(strip_tags(strtoupper($_SESSION['transaction'])));
						$itemCodeVar = addslashes(strip_tags(strtoupper($_SESSION['itemCode'])));
						$yourQuantityVar = addslashes(strip_tags(strtoupper($_POST['txtYourQuantity'])));
						$realQuantity = addslashes(strip_tags(strtoupper($_SESSION['quantity'])));
						$totalPrice = $priceVar * $yourQuantityVar;	
						
						if($realQuantity < $yourQuantityVar)
							{	
								echo "<script>alert('Sorry your stocks will go to negative(-), make sure your proposed quantity is LESS THAN OR EQUAL TO CURRENT QUANTITY!')</script>";
							}
						else 
							{
								if($yourQuantityVar <= 0)
									{
										echo "<script>alert('WARNING - The quantity in the specified inventory are no stocks , Sorry!')</script>";
									}
								else if($realQuantity >= $yourQuantityVar)
									{
										$updateQuantity = $realQuantity - $yourQuantityVar;
										
										include("conf.php");
										$qry3 = "INSERT INTO tbltransactiondetails (
											fldDrugInfoID,fldQuantity,fldItemCost,fldTotalPrice,fldStatus
										) VALUES (
											$itemCodeVar,'$_POST[txtYourQuantity]',$priceVar,$totalPrice,'UNPAID')";
										$result3 = mysql_query($qry3) or die (mysql_error());

										mysql_close($connection);
										
										//-----------transaction
										/*include("conf.php");
										$qry5 = "INSERT INTO tbltransaction (
											fldTransactionID
										) VALUES (
											$transactionVar)";
										$result5 = mysql_query($qry5) or die (mysql_error());
										mysql_close($connection); */
										
										//-----------update druginfo
										include("conf.php");
										$qry7 = "UPDATE tbldruginfo SET fldQuantity = $updateQuantity WHERE fldDrugInfoID = $itemCodeVar";
										$result7 = mysql_query($qry7) or die (mysql_error());
										mysql_close($connection);
										
										echo "<meta http-equiv='refresh' content='3;url=salesOrderEntry.php'><h4 style = 'text-align:center; color:#000000; font-weight:normal; font-family:verdana; margin-left:0px;'>Customer Order Added Sucessfull<br />";
										//<a href = 'salesOrderEntry.php' style = 'margin-left:0px; color:#FF0000'>Click here if it does not redirect in 5 second</a></h4>";
										//session_destroy();
								}
								
						
							}
			}
	?>
	</form>
	<br />
	<iframe src = "cart.php" name = "frameFocus" height = "350px" width = "100%"></iframe>
</div>
<?php include("footer.php"); ?>
