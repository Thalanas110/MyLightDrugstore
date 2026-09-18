<link href="style.css" rel="stylesheet" type="text/css">
<?php
		include("conf.php");
		$qry = "SELECT * FROM tbldruginfo";
		$result = mysql_query($qry) or die (mysql_error());
		if(mysql_num_rows($result))
			{
				?>
							<table id = "cartTable">
								<tr>
									<td colspan = "9" style = "text-align:center; font-size:15px; font-family:verdana; font-weight:bold; padding:20px;">
										INVENTORY STATUS - REPORT <br />
										As of: (<?php echo date(M); echo " ".date(d); echo ",".date(Y); ?>)
									</td>
								</tr>
								<tr>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										No.
									</td>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										Generic Name
									</td>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										Brand Name
									</td>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										Description
									</td>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										Suspension
									</td>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										Dosage
									</td>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										Price
									</td>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										Quantity
									</td>
									<td style = "text-align:center; font-family:verdana; font-weight:bold;">
										Location
									</td>
								</tr>
				<?php
				while($row = mysql_fetch_array($result))
					{
							$count += 1;
						?>
								<tr>
									<td>
										<?php echo $count; ?>
									</td>
									<td>
										<?php echo $row['fldGenericName']; ?>
									</td>
									<td>
										<?php echo $row['fldBrandName']; ?>
									</td>
									<td>
										<?php echo $row['fldDescription']; ?>
									</td>
									<td>
										<?php echo $row['fldSuspension']; ?>
									</td>
									<td>
										<?php echo $row['fldDosage']; ?>
									</td>
									<td>
										<?php echo $row['fldPrice']; ?>
									</td>
									<td>
										<?php echo $row['fldQuantity']; ?>
									</td>
									<td>
										<?php echo $row['fldLocation']; ?>
									</td>
								</tr>
						<?php
					}
			}
	?>
		</table>