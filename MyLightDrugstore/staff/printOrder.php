<link href="style.css" rel="stylesheet" type="text/css">
<?php
		echo "<script>alert('NOTE: Press Alt+F+V to Print Preview, Press Alt+F+P or Ctrl+P to direct Print and Press F5 to show HELP info.')</script>";
		include("conf.php");
		$qry = "SELECT *,tbltransactiondetails.fldDrugInfoID as drugInfoID, tbltransactiondetails.fldQuantity as orderQty,tbltransactiondetails.fldItemCost as orderItemCost,tbltransactiondetails.fldTotalPrice as orderTotalPrice FROM tbltransactiondetails INNER JOIN tbldruginfo ON tbltransactiondetails.fldDrugInfoID = tbldruginfo.fldDrugInfoID WHERE fldStatus = 'UNPAID'";
		$result = mysql_query($qry) or die (mysql_error());
		if(mysql_num_rows($result))
			{
				?>
					&nbsp;&nbsp;<?php echo date(M); echo " ".date(d); echo ",".date(Y); ?>
							<table id = "cartTable">
								<tr>
									<td colspan = "9" style = "text-align:center; font-size:15px; font-family:verdana; font-weight:bold; padding:20px;">
										My Light Drugstore<br />
										(<?php echo date(M); echo " ".date(d); echo ",".date(Y); ?>)
									</td>
								</tr>
								<tr>
									<td style = "width:320px; text-align:center;">
										Generic Name 
									</td>
									<td style = "width:320px; text-align:center;">
										Brand Name
									</td>
									<td style = "width:100px; text-align:center;">
										Item Cost
									</td>
									<td style = "width:100px; text-align:center;">
										Quantity
									</td>
									<td style = "width:100px; text-align:center;">
										Amount
									</td>
								</tr>
				<?php
				while($row = mysql_fetch_array($result))
					{
						$transaction = $row['drugInfoID'];
						$row['fldTransactionDetailsID'];
						$orderTotalPrice = $row['orderTotalPrice'];
						$grandTotal += $orderTotalPrice;
						?>
								<tr>
									<td>
										<?php echo $row['fldGenericName']; ?>
									</td>
									<td>
										<?php echo $row['fldBrandName']; ?>
									</td>
									<td style = "text-align:center;">
										<?php echo $row['orderItemCost']; ?>
									</td>
									<td style = "text-align:center;">
										<?php echo $row['orderQty']; ?>
									</td>
									<td style = "text-align:center;">
										<?php echo $orderTotalPrice; ?>
									</td>
								</tr>
						<?php
					}
			}
	?>
	<?php echo "<div id = 'clear'></div>"; ?>
	
							<tr>
								<td colspan = "5">
									<p style = 'float:right; color:#000000; font-weight:bold; margin-top:3px; margin-right:75px; font-size:15px;'>Grand Total:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <?php echo $grandTotal; ?></p>
								</td>
							</tr>
		</table>
		<div id = "signature"></div>