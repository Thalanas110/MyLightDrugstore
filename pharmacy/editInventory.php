<?php 
	include("header.php"); 
?>
<?php
	include("conf.php");
	$qry = "SELECT * FROM tbldruginfo WHERE fldDrugInfoID = '$_GET[druginfoID]'";
	$result = mysql_query($qry) or die (mysql_error());
	if(mysql_num_rows($result) > 0)
		{
			while($row = mysql_fetch_object($result))
				{
					$fldDrugInfoID = $row->fldDrugInfoID;
					$fldGenericName = $row->fldGenericName;
					$fldBrandName = $row->fldBrandName;
					$fldDescription = $row->fldDescription;
					$fldSuspension = $row->fldSuspension;
					$fldDosage = $row->fldDosage;
					$fldPrice = $row->fldPrice;
					$fldQuantity = $row->fldQuantity;
					$fldLocation = $row->fldLocation;
				}
		}
?>
<div id="content">
	<br />
	<h3>&raquo Inventory &raquo Edit Items</h3>
	<br />
	<?php
		if (!$_POST['edit'])
			{
	?>
	<form name = "inventoryItemsForm" action = "<?php echo $_SERVER[PHP_SELF]?>" method = "POST" onsubmit="return validate_inventoryItems_Form()">
	<input type = "hidden" name = "id" value = "<?php echo $fldDrugInfoID; ?>">
	<table id = "info">
		<tr>
			<td>
				Generic Name
			</td>
			<td>
				<input type = "text" name = "genericName" value = "<?php echo $fldGenericName; ?>" style = "border-color:red;">
			</td>
			<td>
				Brand Name
			</td>
			<td>
				<input type = "text" name = "brandName" value = "<?php echo $fldBrandName; ?>" style = "border-color:red;">
			</td>
		</tr>
		<tr>
			<td>
				Description
			</td>
			<td>
				<input type = "text" name = "description" value = "<?php echo $fldDescription; ?>" style = "border-color:red;">
			</td>
			<td>
				Suspension
			</td>
			<td>
				<input type = "text" name = "suspension" value = "<?php echo $fldSuspension; ?>" style = "border-color:red;">
			</td>
		</tr>
		<tr>
			<td>
				Dosage
			</td>
			<td>
				<input type = "text" name = "dosage" value = "<?php echo $fldDosage; ?>" style = "border-color:red;">
			</td>
			<td>
				Location
			</td>
			<td>
				<input type = "text" name = "location" value = "<?php echo $fldLocation; ?>" style = "border-color:red;">
			</td>
		</tr>
		<tr>
			<td>
				Price
			</td>
			<td>
				<input type = "text" name = "price" value = "<?php echo $fldPrice; ?>" style = "border-color:red;">
			</td>
			<td>
				Quantity
			</td>
			<td>
				<input type = "text" name = "quantity" value = "<?php echo $fldQuantity; ?>" style = "border-color:red;">
			</td>
		</tr>
		<tr>
			<td colspan = "4">
				<br />
				<input type = "submit" name = "edit" value = "Begin Processing..." style = " margin-left:320px; width:170px;">
			</td>
		</tr>
	</table>
	</form>
	<?php
			}
			else
				{	
						$id = $_POST['id'];
						$genericName = addslashes(strip_tags(strtoupper($_POST['genericName'])));
						$brandName = addslashes(strip_tags(strtoupper($_POST['brandName'])));
						$description = addslashes(strip_tags(strtoupper($_POST['description'])));
						$suspension = addslashes(strip_tags(strtoupper($_POST['suspension'])));
						$dosage = addslashes(strip_tags(strtoupper($_POST['dosage'])));
						$price = addslashes(strip_tags(strtoupper($_POST['price'])));
						$quantity = addslashes(strip_tags(strtoupper($_POST['quantity'])));
						$location = addslashes(strip_tags(strtoupper($_POST['location'])));
						
							include("conf.php");
							$qry = "UPDATE tbldruginfo SET 
								fldGenericName = '$genericName',
								fldBrandName = '$brandName',
								fldDescription = '$description',
								fldSuspension = '$suspension',
								fldDosage = '$dosage',
								fldPrice = '$price',
								fldQuantity = '$quantity',
								fldLocation = '$location' WHERE fldDrugInfoID = $id"; 
								 
							$result = mysql_query($qry) or die (mysql_error());
							
							echo "<p style = 'font-family:verdana; font-size:15px; margin-left:20px;'>Update Items Completed... <a href = 'updateInventoryItems.php'>View</a></p>";
							
							mysql_close($connection);
				}
	?>
</div>

<?php include("footer.php"); ?>
