<html>
<head>
<title>Search Item Code</title>
<!-- (START SCRIPT FOR DATA GRID -----heh ) -->
<style type="text/css" title="currentStyle">
	@import "dataTables/css/demo_page.css";
	@import "dataTables/css/demo_table.css";
	
	.tooltip span {display:none; padding:5px 5px; margin-left:8px; margin-top:23px; width:300px;}
	.tooltip:hover span{display:inline; position:absolute; border:1px solid #cccccc; background:#78b6e4; color:#000000;}
</style>

<script type="text/javascript" language="javascript" src="dataTables/js/jquery.js"></script>
<script type="text/javascript" language="javascript" src="dataTables/js/jquery.dataTables.js"></script>
<script type="text/javascript" charset="utf-8">
	$(document).ready(function() {
		var oTable = $('#example').dataTable();
		var oSettings = oTable.fnSettings();
		
	} );
</script>
<!-- (END SCRIPT FOR DATA GRID -----heh ) -->
</head>
<body bgcolor = "#c0c0c0">
<?php
	include("dataTables/SalesOrderEntryDataTables.php");
?>
</body>
</html>
