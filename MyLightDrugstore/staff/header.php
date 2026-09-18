<?php
	session_start();
	if($_SESSION['staffUserid'] == false)
		{
			header("location:../index.php");
		}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Pharmacy Inventory System v1.0</title>
<link href="style.css" rel="stylesheet" type="text/css">
<script type="text/javascript" src="script/validation_inventoryItems.js"></script> <!--(SCRIPT->VALIDATION INVENTORY ITEMS ----- heh )-->
<script type="text/javascript" src="script/validation_updateQuantity.js"></script> <!--(SCRIPT->VALIDATION UPDATE QUANTITY ----- heh )-->
<script type="text/javascript" src="script/validation_quantity.js"></script> <!--(SCRIPT->VALIDATION UPDATE QUANTITY ----- heh )-->
<!-- (START SCRIPT FOR DATA GRID -----heh ) -->
<style type="text/css" title="currentStyle">
	@import "dataTables/css/demo_page.css";
	@import "dataTables/css/demo_table.css";
</style>
<script type="text/javascript" language="javascript" src="dataTables/js/jquery.js"></script>
<script type="text/javascript" language="javascript" src="dataTables/js/jquery.dataTables.js"></script>
<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {
		var oTable = $('#example').dataTable();
		var oSettings = oTable.fnSettings();
		window.scrollTo(0,0);
	} );
</script>
<!-- (END SCRIPT FOR DATA GRID -----heh ) -->
</head>
<body OnLoad="document.formFocus.txtfocus.focus();">
<div id="header">
	<div id="navigation">
    	<?php
    		include("navigation.php");
    	?>
    </div>
</div>