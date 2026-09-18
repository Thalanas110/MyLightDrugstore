
	<body id="dt_example">
		<div id="container">
<table cellpadding="0" cellspacing="0" border="0" style = "margin-top:25px;" class="display" id="example">
	<thead>
		<tr>
			<th>Generic Name</th>
			<th>Brand Name</th>
			<th>Quantity</th>
			<th>Price</th>
			<th>Actions</th>
		</tr>
	</thead>
	<tbody>
		<?php
			//$connection = mysql_connect("Localhost","root","") or die ("Cannot connect to server");
			//mysql_select_db("dbmedicine") or die ("Cannot connect to database");
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
								<td><?php echo $row[fldGenericName]; ?></td>
								<td><?php echo $row[fldBrandName]; ?></td>
								<td><?php echo $row[fldQuantity]; ?>
									<span><?php echo '<b>DESCRIPTION</b>: '.$description . '<br><b>SUSPENSION</b>: '.$suspension . '<br><b>LOCATION</b>: '.$location . '<br><b>DOSAGE</b>: '.$dosage; ?></span>
								</td>
								<td><?php echo $row[fldPrice]; ?></td>
								<td style = "text-align:center;">
								<a href = "updateQuantity.php?druginfoID=<?php echo $row[fldDrugInfoID]; ?>" title = "Update your item quantity" onclick = "return confirm ('Are you sure do you want to update our quantity?');">
										<img src = "dataTables/images/po.jpg" style = "height:17px; width:17px;">
									</a>
								</td>
							</tr>
							<?php
						}
				}
		?>
	</tbody>
	<tfoot>
		<tr>
			<th>Generic Name</th>
			<th>Brand Name</th>
			<th>Quantity</th>
			<th>Price</th>
			<th>Actions</th>
		</tr>
	</tfoot>
</table>