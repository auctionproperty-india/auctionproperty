<?php
// रेंडर पर जो MySQL डेटाबेस बनाया है, उसके क्रेडेंशियल्स यहाँ आएँगे
$host     = 'आपका_डेटाबेस_होस्ट_यानी_EXTERNAL_URL'; 
$db_name  = 'आपका_डेटाबेस_नाम';
$user     = 'आपका_डेटाबेस_यूजरनेम';
$password = 'आपका_डेटाबेस_पासवर्ड';
$port     = '3306'; // आमतौर पर 3306 होता है

$conn = mysqli_connect($host, $user, $password, $db_name, $port);

// कनेक्शन चेक करने के लिए
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
?>
