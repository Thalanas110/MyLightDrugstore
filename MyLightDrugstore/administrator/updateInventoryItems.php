<?php include("header.php"); ?>

<div id="content">
	<br />
	<h3>&raquo Inventory &raquo Update Inventory</h3>
	<table border = "0" height = "300px" width = "900px">
		<tr>
			<td align = "center">
				<div class = "dateTime" id = "dateTime">
				<form name="Tick">
				<input type = "text" name = "dateTime" size="15" value = "<?php echo date( 'D, d M Y' ); ?>" style = "border:0px solid;">/
				<input type="text" size="10" name="Clock" style = "border:0px solid;">
				</form>
				</div>
				<script language="JavaScript" src="script/countdownTimer.js"></script>
				<?php
					include("dataTables/updateDataTables.php");
				?>
			</td>
		</tr>
	</table>
</div>

<?php include("footer.php"); ?>
