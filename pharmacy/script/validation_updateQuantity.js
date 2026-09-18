function validate_updateQuantity_Form()
{
var quantityVar = document.forms["updateQuantityForm"]["quantity"].value;

if (quantityVar == "")
	  {
	  alert("Data must be entered in required color red field");
	  return false;
	  //document.getElementById('error').innerHTML = "Required Field...";
	  //return false;
	  }
}