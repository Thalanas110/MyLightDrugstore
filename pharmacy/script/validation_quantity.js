function validate_quantity_Form()
{
var quantityVar = document.forms["quantityForm"]["txtYourQuantity"].value;

if (quantityVar == "")
	  {
	  alert("Please enter your quantity/item code!");
	  return false;
	  //document.getElementById('error').innerHTML = "Required Field...";
	  //return false;
	  }
}

function validate_itemCode_Form()
{
var itemCodeVar = document.forms["itemCodeForm"]["itemCode"].value;

if (itemCodeVar == "")
	  {
	  alert("Please select the item code!");
	  return false;
	  //document.getElementById('error').innerHTML = "Required Field...";
	  //return false;
	  }
}