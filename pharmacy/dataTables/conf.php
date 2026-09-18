<?php
	$localhost = "localhost";
	$username = "root";
	$password = "";
	$db = "dbmedicine";

	$connection = mysql_connect($localhost,$username,$password) or die ("Cannot connect to server");
	mysql_select_db($db) or die ("Cannot connect to database");
	
?>