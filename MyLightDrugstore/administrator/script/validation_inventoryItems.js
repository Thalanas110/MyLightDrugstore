function validate_inventoryItems_Form()
{
var genericNameVar = document.forms["inventoryItemsForm"]["genericName"].value;
var brandNameVar = document.forms["inventoryItemsForm"]["brandName"].value;
var descriptionVar = document.forms["inventoryItemsForm"]["description"].value;
var suspensionVar = document.forms["inventoryItemsForm"]["suspension"].value;
var dosageVar = document.forms["inventoryItemsForm"]["dosage"].value;
var priceVar = document.forms["inventoryItemsForm"]["price"].value;
var quantityVar = document.forms["inventoryItemsForm"]["quantity"].value;
var locationVar = document.forms["inventoryItemsForm"]["location"].value;

if (genericNameVar == "" || brandNameVar == "" || descriptionVar == "" || suspensionVar == "" || dosageVar == "" || PriceVar == "" || quantityVar == "" || locationVar == "")
	  {
	  alert("Data must be entered in required color red field");
	  return false;
	  //document.getElementById('error').innerHTML = "Required Field...";
	  //return false;
	  }
}