<?php 
	session_start();
	include("header.php"); 
?>

<div id="content" style = "height:300px;">
	<div id = "welcomeAdmin">
	WELCOME ADMINISTRATOR<br>
	(My Light Drugstore)
	<!--<table border = "0" height = "300px" width = "900px">
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
					include("dataTables/dataTables.php");
				?>
			</td>
		</tr>
	</table>-->
	</div>
</div>

<?php include("footer.php"); ?>
