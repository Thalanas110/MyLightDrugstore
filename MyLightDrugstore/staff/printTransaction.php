<link href="style.css" rel="stylesheet" type="text/css">
<?php		
			session_start();
			include('conf.php');
			$qry2 = "SELECT *,SUM(tbltransactiondetails.fldQuantity) AS transqty,DATE(fldTransaction),COUNT(fldBrandName) FROM tbltransactiondetails INNER JOIN tbldruginfo ON tbldruginfo.fldDrugInfoID = tbltransactiondetails.fldDrugInfoID WHERE DATE(fldTransaction) = '$_SESSION[dateTransaction]' GROUP BY fldBrandName";
			$result2 = mysql_query($qry2) or die (mysql_error());
			if(mysql_num_rows($result2))
				{
						?>
						<table border = "1" class = "listTransaction" align = "center">
								<tr>
									<td colspan = "9" style = "text-align:center; font-size:15px; font-family:verdana; font-weight:bold; padding:20px;">
										Transaction Detail's<br />
										<?php echo $_SESSION["dateTransaction"]; ?>
									</td>
								</tr>
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
							$_SESSION['dateOfTrnasaction'] = $row['DATE(fldTransaction)'];
							?>
									<tr>
										<td>
											<?php echo $row['fldTransactionDetailsID']; ?>
										</td>
										<td>
											<?php echo $row['DATE(fldTransaction)']; ?>
										</td>
										<td>
											<?php echo $row['fldBrandName']; ?>-<?php echo $row['fldDrugInfoID']; ?>
										</td>
										<td>
											<?php echo $transqty; ?>
										</td>
									</tr>
							<?php
				}
			}
	?>