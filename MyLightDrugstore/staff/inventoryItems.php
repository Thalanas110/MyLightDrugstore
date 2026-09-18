<?php 
	include("header.php"); 
?>

<div id="content">
	<br />
	<h3>&raquo Inventory &raquo Inventory Items</h3>
	<br />
	<?php
		if (!$_POST['add'])
			{
	?>
	<form name = "inventoryItemsForm" action = "<?php echo $_SERVER[PHP_SELF]?>" method = "POST" onsubmit="return validate_inventoryItems_Form()">
	<table id = "info">
		<tr>
			<td>
				Generic Name
			</td>
			<td>
				<input type = "text" name = "genericName" style = "border-color:red;">
			</td>
			<td>
				Brand Name
			</td>
			<td>
				<input type = "text" name = "brandName" style = "border-color:red;">
			</td>
		</tr>
		<tr>
			<td>
				Description
			</td>
			<td>
				<input type = "text" name = "description" style = "border-color:red;">
			</td>
			<td>
				Suspension
			</td>
			<td>
				<input type = "text" name = "suspension" style = "border-color:red;">
			</td>
		</tr>
		<tr>
			<td>
				Dosage
			</td>
			<td>
				<input type = "text" name = "dosage" style = "border-color:red;">
			</td>
			<td>
				Location
			</td>
			<td>
				<input type = "text" name = "location" style = "border-color:red;">
			</td>
		</tr>
		<tr>
			<td>
				Price
			</td>
			<td>
				<input type = "text" name = "price" style = "border-color:red;">
			</td>
			<td>
				Quantity
			</td>
			<td>
				<input type = "text" name = "quantity" style = "border-color:red;">
			</td>
		</tr>
		<tr>
			<td colspan = "4">
				<br />
				<input type = "submit" name = "add" value = "Begin Processing..." style = " margin-left:320px; width:170px;">
			</td>
		</tr>
	</table>
	</form>
	<?php
			}
			else
				{	
					$genericName = addslashes(strip_tags(strtoupper($_POST['genericName'])));
					$description = addslashes(strip_tags(strtoupper($_POST['description'])));
					$suspension = addslashes(strip_tags(strtoupper($_POST['suspension'])));
					$dosage = addslashes(strip_tags(strtoupper($_POST['dosage'])));
					$price = addslashes(strip_tags(strtoupper($_POST['price'])));
					$quantity = addslashes(strip_tags(strtoupper($_POST['quantity'])));
					$location = addslashes(strip_tags(strtoupper($_POST['location'])));
					$brandName = addslashes(strip_tags(strtoupper($_POST['brandName'])));
				
							include("conf.php");
							$qry = "INSERT INTO tbldruginfo( 
								fldGenericName,
								fldBrandName,
								fldDescription,
								fldSuspension,
								fldDosage,
								fldPrice,
								fldQuantity,
								fldLocation
							) VALUES (
								'$genericName',
								'$brandName',
								'$description',
								'$suspension',
								'$dosage',
								'$price',
								'$quantity',
								'$location')
								 "; 
								 
							$result = mysql_query($qry) or die (mysql_error());
							
							echo "<script>alert('Process Completed');</script>";
							
							mysql_close($connection);
				}
	?>
</div>

<?php include("footer.php"); ?>
