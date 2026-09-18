<?php include("header.php"); ?>

<div id="content">
	<br />
	<h3>&raquo Reports &raquo Transaction Details</h3>
	<br/>
		<form action = "<?php echo $_SERVER[PHP_SELF]?>" method = "POST">
		<table border = "0">
			<tr>
				<td>
					<font size = "2px;" face = "verdana">Date Processed:</font>
				</td>
				<td>
					<?php
						include('conf.php');
						$result = mysql_query("SELECT fldTransaction,DATE(fldTransaction),COUNT(fldTransaction) FROM tbltransactiondetails GROUP BY DATE(fldTransaction)")or die(mysql_error());	
						//$non_repeat = array_unique($result);			
					?>
						<select name = "cboTransaction" style = "border-color:red; height:25px; width:185px; text-transform: uppercase;">
							<option selected></option>
							<?php
								while($row = mysql_fetch_array($result)){
								
							?>
								<option style = "font-family:verdana;" value = "<?php echo $row['DATE(fldTransaction)']; ?>"><?php echo $row['DATE(fldTransaction)']; ?></option>
					<?php
								}
						?>
								
							</select>
					<?php
						mysql_close($connection);
					?>
					<input type = "submit" name = "viewTransaction" value = "View">
					<input type = "hidden" name = "txtTransaction" value = "<?php echo $_POST['cboTransaction']; ?>">
				</td>
			</tr>
	</table>
	<hr />
	<br />
	<font face = "verdana">List of Transaction's:</font>
	<br /><br />	
	<div id = "listOfTransaction">
		<?php
		$_SESSION['dateTransaction'] = $_POST['txtTransaction'];
		if($_POST['viewTransaction'])
			{
			
			include('conf.php');
			$qry2 = "SELECT *,SUM(tbltransactiondetails.fldQuantity) AS transqty,DATE(fldTransaction),COUNT(fldBrandName) FROM tbltransactiondetails INNER JOIN tbldruginfo ON tbldruginfo.fldDrugInfoID = tbltransactiondetails.fldDrugInfoID WHERE DATE(fldTransaction) = '$_POST[cboTransaction]' GROUP BY fldBrandName";
			$result2 = mysql_query($qry2) or die (mysql_error());
			if(mysql_num_rows($result2))
				{
						?>			
						<div id = "headerUpdateOrder">
						<script type="text/javascript">
						// Popup window code for customer information ----- heh
						function newPopup(url) {
							popupWindow = window.open(
								url,'popUpWindow','height=500,width=900,left=230,top=100,resizable=yes,scrollbars=yes,toolbar=no,menubar=yes,location=no,directories=no,status=yes')
						}
						</script>
						&nbsp;
						<a href = 'JavaScript:newPopup("printTransaction.php");' id = 'linkPrintOrder' title = 'If you want to print this order, you may now click this link.'>PRINT TRANSACTION</a>
						</div>
						<br />
						<table border = "0" class = "listTransaction">
									<tr>
										<td>
											ID
										</td>
										<td>
											Transaction Date
										</td>
										<td>
											Brand Name
										</td>
										<td>
											Quantity
										</td>
									</tr>
						<?php
					while($row = mysql_fetch_assoc($result2))
						{
							$transqty = $row['transqty'];
							$_SESSION["dateTransaction"] = $row['DATE(fldTransaction)'];
							?>
							
									<tr>
										<td>
											<?php echo $row['fldTransactionDetailsID']; ?>
										</td>
										<td>
											<?php echo $row['DATE(fldTransaction)']; ?>
										</td>
										<td>
											<?php echo $row['fldBrandName']; ?>
										</td>
										<td>
											<?php echo $transqty; ?>
										</td>
									</tr>	
							<?php
				}
			}
		}
	?>
									</table>
									
									
	</div>
	</form>
	<div id = "clear"></div>
</div>

<?php include("footer.php"); ?>
