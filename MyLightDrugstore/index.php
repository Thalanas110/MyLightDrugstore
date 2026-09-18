<?php 
	//session_start();
	include("header.php"); ?>

<div id = "wrapper">
	<div id = "bglogin"><img src = "images/bglogin.JPG"></div>
	 <?php
			if(isset($_POST['logIn']))
				{
					if($_POST['password'] == '')
						{
							$error = "Password don't match <img src = 'images/loading.gif' id = 'loading'>";
							echo "<META HTTP-EQUIV='Refresh' Content='2'; URL='index.php'>";
						}
					else
						{
							include("conf.php");
							$qry = "SELECT * FROM tbluseradmin";
							$result = mysql_query($qry) or die (mysql_error());
							if(mysql_num_rows($result))
								{
									while($row = mysql_fetch_array($result))
										{
											$adminUserid = $row['useradminID'];
											$adminPassword = $row['password'];
										}
								}

							$qry = "SELECT * FROM tbluserstaff";
							$result = mysql_query($qry) or die (mysql_error());
							if(mysql_num_rows($result))
								{
									while($row = mysql_fetch_array($result))
										{
											$staffUserid = $row['id'];
											$staffFullName = $row['fullname'];
											$staffPassword = $row['password'];
										}
								}	
															
							if($_POST['userName'] == "administrator" and $_POST['password'] == $adminPassword)
								{
									session_start();
									$_SESSION['adminUserid'] = $adminUserid;
									header("location:administrator/index.php");
								}
							elseif($_POST['userName'] == "staff" and $_POST['password'] == $staffPassword)
								{
									session_start();
									$_SESSION['staffFullName'] = $staffFullName;
									$_SESSION['staffUserid'] = $staffUserid;
									header("location:staff/index.php");
								}
							else{
									$error = "Password don't match <img src = 'images/loading.gif' id = 'loading'>";
									echo "<META HTTP-EQUIV='Refresh' Content='2'; URL='index.php'>";
								}
							
						}
				}
		?>
	<form action = "<?php echo $_SERVER[PHP_SELF]; ?>" method = "POST">
	<table id = "login">
		<tr>
			<td style = "text-align:right; font-family:verdana; font-size:15px;">
				User Account:
			</td>
			<td>
				<select name = "userName" style = "width:200px;">
					<option value = "staff" selected>Staff</option>
					<option value = "administrator">Administrator</option>
				</select>
			</td>
		</tr>
		<tr>
			<td style = "text-align:right; font-family:verdana; font-size:15px;">
				Password:
			</td>
			<td>
				<input type = "password" name = "password"  style = "width:195px;">
			</td>
		</tr>
		<tr>
			<td colspan = "2">
				<table id = "errorTable">
					<tr>
						<td style = "width:200px;">
							<div id = "errorLogin"><?php echo $error; ?></div>
						</td>
						<td>
							<input type = "submit" name = "logIn" value = "Sign In" style = "float:right; width:100px;">
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	</form>
</div>

<?php include("footer.php"); ?>
