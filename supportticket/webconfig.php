<?php

$username="root";
$password="";
$server='localhost';
$database='test';
$conn=mysqli_connect($server,$username,$password,$database);
if($conn){
    echo"Connected";
}
else{
    die("ERROR");
}
?>
