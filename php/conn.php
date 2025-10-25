<?php 
$conn = mysqli_connect("localhost","root","","inventory_system_db");

if($conn==false){
	die("Error: " . mysqli_connect_error());
    echo "failed";
}

?>