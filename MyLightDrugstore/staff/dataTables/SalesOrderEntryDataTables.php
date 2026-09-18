<SCRIPT LANGUAGE="JavaScript">
<!-- Begin
function sendValue (s){
var selvalue = s.value;
window.opener.document.getElementById('itemCode').value = selvalue;
window.close();
}
//  End -->
</script>


<table cellpadding="0" cellspacing="0" border="0" style = "margin-top:25px;" class="display" id="example">
	<thead>
		<tr>
			<th>Code</th>
			<th>Generic Name</th>
			<th>Brand Name</th>
			<th>Description</th>
			<th>Location</th>
			<th>Quantity</th>
			<th>Price</th>
		</tr>
	</thead>
	<tbody>
		<?php
			session_start();
			include("conf.php");
			$qry = "SELECT * FROM tbldruginfo";
			$result = mysql_query($qry) or die (mysql_error());
			if(mysql_num_rows($result))
				{
					while($row = mysql_fetch_array($result))
						{
							$description = $row[fldDescription];
							$suspension = $row[fldSuspension];
							$location = $row[fldLocation];
							$dosage = $row[fldDosage];
							?>	
							
							<tr class = "tooltip">
							<form name="selectform">
								<td style = "width:10px;">
									<input type = "hidden" name="itemCode" value="<?php echo $row[fldDrugInfoID]; ?>">
									<input type=button style = "background:transparent; border:0; cursor:hand; width:50px;" value="<?php echo $row[fldDrugInfoID]; ?>" onClick="sendValue(this.form.itemCode)&sendValueg(this.form.genericN);">
								</td>
								<td style = "color:transparent;">
									<?php echo $row[fldGenericName]; ?><br />
									<input type=button style = "background:transparent; border:0; cursor:hand;" value="<?php echo $row[fldGenericName]; ?>" onClick="sendValue(this.form.itemCode)&sendValueg(this.form.genericN);">
								</td>
								<td style = "color:transparent;">
									<?php echo $row[fldBrandName]; ?><?php echo $row[fldDosage]; ?><?php echo $row[fldSuspension]; ?><br />
									<input type=button style = "background:transparent; border:0; cursor:hand;" value="(<?php echo $row[fldBrandName]; ?>)/(<?php echo $row[fldDosage]; ?>)/(<?php echo $row[fldSuspension]; ?>)" onClick="sendValue(this.form.itemCode)&sendValueg(this.form.genericN);">
								</td>
								<td style = "color:transparent;">
									<?php echo $row[fldDescription]; ?><br />
									<input type=button style = "background:transparent; border:0; cursor:hand;" value="<?php echo $row[fldDescription]; ?>" onClick="sendValue(this.form.itemCode)&sendValueg(this.form.genericN);">
								</td>
								<td style = "color:transparent;">
									<?php echo $row[fldLocation]; ?><br />
									<input type=button style = "background:transparent; border:0; cursor:hand;" value="<?php echo $row[fldLocation]; ?>" onClick="sendValue(this.form.itemCode)&sendValueg(this.form.genericN);">
								</td>
								<td style = "color:transparent;">
									<?php echo $row[fldQuantity]; ?><br />
									<input type=button style = "background:transparent; border:0; cursor:hand;" value="<?php echo $row[fldQuantity]; ?>" onClick="sendValue(this.form.itemCode)&sendValueg(this.form.genericN);">
								</td>
								<td style = "color:transparent;">
									<?php echo $row[fldPrice]; ?><br />
									<input type=button style = "background:transparent; border:0; cursor:hand;" value="<?php echo $row[fldPrice]; ?>" onClick="sendValue(this.form.itemCode)&sendValueg(this.form.genericN);">
								</td>
								</form> 
							</tr>
							
							<?php
						}
				}
		?>
	</tbody>
	<tfoot>
		<tr>
			<th>Code</th>
			<th>Generic Name</th>
			<th>Brand Name</th>
			<th>Description</th>
			<th>Location</th>
			<th>Quantity</th>
			<th>Price</th>
		</tr>
	</tfoot>
</table>
