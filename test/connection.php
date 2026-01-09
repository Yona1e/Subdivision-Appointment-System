<?php
$server = "localhost";
$username = "root";
$password = "";
$database = "appointment-system";
$connection = mysqli_connect("$server", "$username", "$password");
$select_db =mysqli_connect($conneecction, $database);

if(!$select_db){
    echo("Connection terminated");
}
?>

