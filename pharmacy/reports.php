<?php include("header.php"); ?>

	<script type="text/javascript">
	// Popup window code for customer information ----- heh
	function newPopup(url) {
		popupWindow = window.open(
			url,'popUpWindow','height=500,width=900,left=230,top=100,resizable=yes,scrollbars=yes,toolbar=no,menubar=yes,location=no,directories=no,status=yes')
	}
	</script>
	
<div id="content">
	<br />
	<h3>&raquo Reports</h3>
	<h2><a href = "JavaScript:newPopup('inventoryStatus.php');">Inventory Status</a></h2>
	<h2><a href = "transactionDetails.php">Transaction Details</a></h2>
	<br/>
</div>

<?php include("footer.php"); ?>
