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
	<h3>&raquo Update your quantity</h3>
	<br />
	<?php
		if (!$_POST['edit'])
			{
	?>
	<form name = "updateQuantityForm" action = "<?php echo $_SERVER[PHP_SELF]?>" method = "POST" onsubmit="return validate_updateQuantity_Form()">
	<input type = "hidden" name = "id" value = "<?php echo $fldDrugInfoID; ?>">
	<table id = "info">
		<tr>
			<td>
				Generic Name
			</td>
			<td>
				<i><?php echo $fldGenericName; ?></i>
			</td>
			<td>
				Brand Name
			</td>
			<td>
				<i><?php echo $fldBrandName; ?></i>
			</td>
		</tr>
		<tr>
			<td>
				Description
			</td>
			<td>
				<i><?php echo $fldDescription; ?></i>
			</td>
			<td>
				Suspension
			</td>
			<td>
				<i><?php echo $fldSuspension; ?></i>
			</td>
		</tr>
		<tr>
			<td>
				Dosage
			</td>
			<td>
				<i><?php echo $fldDosage; ?></i>
			</td>
			<td>
				Location
			</td>
			<td>
				<i><?php echo $fldLocation; ?></i>
			</td>
		</tr>
		<tr>
			<td>
				Price
			</td>
			<td>
				<i><?php echo $fldPrice; ?></i>
			</td>
			<td>
				Current Quantity
			</td>
			<td>
				<input type = "text" name = "currentQuantity" value = "<?php echo $fldQuantity; ?>" style = "border:0px; background-color:transparent;">
			</td>
		</tr>
		<tr>
			<td>
				
			</td>
			<td>
				
			</td>
			<td>
				Quantity
			</td>
			<td>
				<input type = "text" name = "newQuantity" style = "border-color:red;">
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
						$currentQuantity = addslashes(strip_tags(strtoupper($_POST['currentQuantity'])));
						$newQuantity = addslashes(strip_tags(strtoupper($_POST['newQuantity'])));
						$quantity = $currentQuantity +  $newQuantity;
						
							include("conf.php");
							$qry = "UPDATE tbldruginfo SET 
								fldQuantity = '$quantity' WHERE fldDrugInfoID = $id"; 
								 
							$result = mysql_query($qry) or die (mysql_error());
							
							echo "<p style = 'font-family:verdana; font-size:15px; margin-left:20px;'>Update Items Completed... <a href = 'purchaseOrderMaintenance.php'>View</a></p>";
							
							mysql_close($connection);
				}
	?>
</div>

<?php include("footer.php"); ?>
