<link href="style.css" rel="stylesheet" type="text/css">
<?php
		include("conf.php");
		$qry = "SELECT *,tbltransactiondetails.fldDrugInfoID as drugInfoID, tbltransactiondetails.fldQuantity as orderQty,tbltransactiondetails.fldItemCost as orderItemCost,tbltransactiondetails.fldTotalPrice as orderTotalPrice FROM tbltransactiondetails INNER JOIN tbldruginfo ON tbltransactiondetails.fldDrugInfoID = tbldruginfo.fldDrugInfoID WHERE fldStatus = 'UNPAID'";
		$result = mysql_query($qry) or die (mysql_error());
		if(mysql_num_rows($result))
			{
				?>
							<table id = "cartTable">
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
									<td style = "width:50px; text-align:center;">
										Action
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
									<td style = "text-align:center;">
										<a href = "cancelOrder.php?transactionDetails=<?php echo $row['fldTransactionDetailsID']; ?>&transaction=<?php echo $transaction; ?>" title = "Cancel" onclick = "return confirm ('Are you sure do you want to cancel this item?');"><img src = "images/delete.png" height = "12px" width = "12px"></a>
									</td>
								</tr>
						<?php
					}
					?>
					<div id = "headerUpdateOrder">
						&nbsp;
						<a href = 'updateOrder.php' id = 'linkPrintOrder' title = 'If your order has been done, you may now click this link.' onclick = "return confirm ('Are you sure do you want to process?');">UPDATE ORDER</a>
						<font style = "color:#FFFFFF; size:15px; font-weight:bold;"> | </font>
						<script type="text/javascript">
						// Popup window code for customer information ----- heh
						function newPopup(url) {
							popupWindow = window.open(
								url,'popUpWindow','height=500,width=900,left=230,top=100,resizable=yes,scrollbars=yes,toolbar=no,menubar=yes,location=no,directories=no,status=yes')
						}
						</script>
						<a href = 'JavaScript:newPopup("printOrder.php");' id = 'linkPrintOrder' title = 'If you want to print this order, you may now click this link.'>PRINT ORDER</a>
						<?php
						echo "<p style = 'float:right; color:#FFFFFF; font-weight:bold; margin-top:3px; margin-right:75px; font-size:15px;'>Grand Total:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $grandTotal . "</p>";
						?>
					</div>
					<?php
			}
	?>
	<?php echo "<div id = 'clear'></div>"; ?>
		</table>