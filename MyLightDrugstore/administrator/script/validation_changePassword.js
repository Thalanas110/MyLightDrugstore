function validate_changePassword_Form()
{
	var oldPassword = document.forms["changePasswordForm"]["oldPassword"].value;
	var newPassword = document.forms["changePasswordForm"]["newPassword"].value;
	var retypePassword = document.forms["changePasswordForm"]["retypePassword"].value;

if (oldPassword == "" || newPassword == "" || retypePassword == "")
	  {
	  alert("Required All Fields!");
	  return false;
	  }
}