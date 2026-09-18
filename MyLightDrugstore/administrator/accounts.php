<?php include("header.php"); ?>

	<script type="text/javascript">
	// Popup window code for customer information ----- heh
	function newPopup(url) {
		popupWindow = window.open(
			url,'popUpWindow','height=500,width=900,left=230,top=100,resizable=yes,scrollbars=yes,toolbar=no,menubar=yes,location=no,directories=no,status=yes')
	}
	
	// Popup window code for customer information ----- heh
	function newPopupAdmin(url) {
		popupWindow = window.open(
			url,'popUpWindow','height=300,width=430,left=230,top=100,resizable=yes,scrollbars=yes,toolbar=no,menubar=yes,location=no,directories=no,status=yes')
	}
	</script>
<form action = "<?php echo $_SERVER[PHP_SELF]?>" method = "POST">
<div id="content">
	<br />
	<h3>&raquo User Accounts</h3>
	<h2><a href = "JavaScript:newPopup('userAccounts.php');">User Accounts</a></h2>
	<h2><a href = "JavaScript:newPopupAdmin('changeAdminAccount.php');">Administrator Accounts</a></h2>
	<br/>
</div>
</form>

<?php include("footer.php"); ?>
