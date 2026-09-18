<table cellpadding="0" cellspacing="0" border="0" style = "margin-top:25px;" class="display" id="example">
	<thead>
		<tr>
			<th style = "width:80px;">Item Code</th>
			<th>Generic Name</th>
			<th>Brand Name</th>
			<th>Quantity</th>
			<th>Price</th>
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
								<td><?php echo $row[fldDrugInfoID]; ?>
								<td><?php echo $row[fldGenericName]; ?></td>
								<td><?php echo $row[fldBrandName]; ?>
								<span><?php echo '<b>DESCRIPTION</b>: '.$description . '<br><b>SUSPENSION</b>: '.$suspension . '<br><b>LOCATION</b>: '.$location . '<br><b>DOSAGE</b>: '.$dosage; ?></span>
								</td>
								<td><?php echo $row[fldQuantity]; ?></td>
								<td><?php echo $row[fldPrice]; ?></td>
							</tr>
							<?php
						}
				}
		?>
	</tbody>
	<tfoot>
		<tr>
			<th>Item Code</th>
			<th>Generic Name</th>
			<th>Brand Name</th>
			<th>Quantity</th>
			<th>Price</th>
		</tr>
	</tfoot>
</table>