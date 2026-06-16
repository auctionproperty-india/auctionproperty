<?php
// यह एक फ्री टेस्ट MySQL डेटाबेस है ताकि आपका प्रोजेक्ट तुरंत लाइव हो सके
$host     = 'sql12.freesqldatabase.com'; 
$db_name  = 'sql12781452';
$user     = 'sql12781452';
$password = 'mR8fX8bL2e';
$port     = '3306'; 

$conn = mysqli_connect($host, $user, $password, $db_name, $port);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
?>
