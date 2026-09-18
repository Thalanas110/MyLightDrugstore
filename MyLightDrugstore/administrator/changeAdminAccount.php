<style>
#changePassword{
	border:0px solid;
}
#jig1{
	border:0px solid;
	width:150px;
	font-family:verdana;
	font-size:12px;
}
#jig2{
	border:0px solid;
	width:auto;
	font-family:verdana;
	font-size:11px;
}
#messageSuccessful{
	font-family:verdana;
	font-size:13px;
}
</style>
<font face = "verdana" size = "4px;">Change Password:</font>
<hr />
<?php
	session_start();
	include("conf.php");
	$qry = "SELECT * FROM tbluseradmin";
	$result = mysql_query($qry) or die (mysql_error());
	if(mysql_num_rows($result))
		{
			while($row = mysql_fetch_array($result))
				{
					$useradminID = $row['useradminID'];
					$password = $row['password'];
				}
		}
	//mysql_close($connection);
	
	$changePassword = $_POST['changePassword'];
	$oldPassword = $_POST['oldPassword'];
	$oldPassword = $_POST['oldPassword'];
	$newPassword = $_POST['newPassword'];
	$retypePassword = $_POST['retypePassword'];
	
	if(isset($changePassword))
		{
			if($oldPassword == '')
				{
					$error_old = "<img src = 'images/failed.png' style = 'width:15px; height:15px;'> Required!";
				}
			else
				{
					if($newPassword == '')
						{
							$error_new = "<img src = 'images/failed.png' style = 'width:15px; height:15px;'> Required!";
						}
					else
						{
							if($oldPassword != $password)
								{
									$error_notmatch = "<img src = 'images/failed.png' style = 'width:15px; height:15px;'> Failed!";
								}
							else
								{
									if($newPassword != $retypePassword)
										{
											$error_confirm = "<img src = 'images/failed.png' style = 'width:15px; height:15px;'> Failed!";
										}
									else
										{
											if($newPassword == $password)
												{
													$error_same = "<img src = 'images/failed.png' style = 'width:15px; height:15px;'> Failed!, New password Same to Current Password!";
												}
											else
												{
													include("conf.php");
													$qry = "UPDATE tbluseradmin SET password = '$newPassword' WHERE useradminID = $useradminID";
													$result = mysql_query($qry) or die (mysql_error());
													$message = "<img src = 'images/success.png' style = 'width:20px; height:20px;'>Changed Successful!";
													echo "<META HTTP-EQUIV='Refresh' Content='2'; URL='changeAdminAccount.php'>";
												}
										}
								}
						}
				}
		}
	
?>
<br/>
<form action = "<?php echo $_SERVER[PHP_SELF] ?>" method = "POST">
<table id = "changePassword">
	<tr>
		<td id = "jig1">
			Old Password:
		</td>
		<td id = "jig2">
			<input type = "password" name = "oldPassword" value = "<?php echo $oldPassword; ?>">
		</td>
		<td id = "jig2">
			<?php echo $error_notmatch; ?><?php echo $error_old; ?>
		</td>
	</tr>
	<tr>
		<td id = "jig1">
			New Password:
		</td>
		<td id = "jig2">
			<input type = "password" name = "newPassword" value = "<?php echo $newPassword; ?>">
		</td>
		<td id = "jig2">
			<?php echo $error_new; ?>
		</td>
	</tr>
	<tr>
		<td id = "jig1">
			Retype-New Password:
		</td>
		<td id = "jig2">
			<input type = "password" name = "retypePassword" value = "<?php echo $retypePassword; ?>">
		</td>
		<td id = "jig2">
			<?php echo $error_confirm; ?>
		</td>
	</tr>
	<tr>
		<td colspan = "2">
			<input type = "submit" name = "changePassword" value = "Changed Password" style = "float:right;">
		</td>
	</tr>
</table>
</form>

<div id = "messageSuccessful">
<?php
	echo $error_same;
	echo $message;
?>
</div>