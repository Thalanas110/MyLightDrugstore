<link href="style.css" rel="stylesheet" type="text/css">
<div id = "accountsWrapper">
	<?php
		if(isset($_POST['add']))
			{
				if($_POST['fullname'] == '' || $_POST['password'] == '')
					{
						$error = "<img src = 'images/failed.png' id = 'loading' style = 'width:15px; height:15px;'> Failed!";
						echo "<META HTTP-EQUIV='Refresh' Content='2'; URL='userAccounts.php'>";
					}
				else
					{
						include("conf.php");
						$qry = "INSERT INTO tbluserstaff(fullname,password) VALUES ('$_POST[fullname]','$_POST[password]')";
						$result = mysql_query($qry) or die (mysql_error());
						$message = "<img src = 'images/success.png' id = 'loading' style = 'width:15px; height:15px;'> Successful!";
						echo "<META HTTP-EQUIV='Refresh' Content='2'; URL='userAccounts.php'>";
					}
			}
		?>
	<form action = "<?php echo $_SERVER[PHP_SELF]?>" method = "POST">
	<font face = "verdana" size = "4px">CREATE USER ACCOUNT:</font>
	<br /><br />
	<table id = "accountsTable">
		<tr>
			<td>
				FULL NAME:
			</td>
			<td>
				<input type = "text" name = "fullname" style = "width:200px;">
			</td>
		</tr>
		<tr>
			<td>
				PASSWORD:
			</td>
			<td>
				<input type = "password" name = "password" style = "width:200px;">
			</td>
		</tr>
		<tr>	
			<td colspan = "2">
				<table id = "messageTable">
					<tr>
						<td style = "width:190px; text-align:center;">
							<div id = "accountStyle"><?php echo $message; ?></div>
							<div id = "accountStyle"><?php echo $error; ?></div>
						</td>
						<td>
							<input type = "submit" name = "add" value = "Create Account" style = "float:right;">
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	</form>
	
	<div id = "accountList">
		<?php
			include("conf.php");
			$qry = "SELECT * FROM tbluserstaff";
			$result = mysql_query($qry) or die (mysql_error());
			if(mysql_num_rows($result))
				{
					?>
				<table id = "listTable">
					<tr>
						<th>
							Action
						</th>
						<th>
							Fullname
						</th>
						<th>
							Password
						</th>
						<th>
							Created Date
						</th>
					</tr>
					<?php
					while($row = mysql_fetch_array($result))
						{
							?>
						<tr>
							<td style = "text-align:center">
								<a href = "deleteUserAccount.php?userid=<?php echo $row['id']; ?>"><img src = "images/delete.png" style = "width:20px; height:15px; cursor:hand;" title = "Delete User Account"></a>
							</td>
							<td style = "text-align:center;">
								<?php echo $row['fullname']; ?>
							</td>
							<td style = "text-align:center;">
								<?php echo $row['password']; ?>
							</td>
							<td style = "text-align:center;">
								<?php echo $row['datecreated']; ?>
							</td>
						</tr>											
							<?php
						}
				}
				?>
			
		</table>
	</div>
</div>