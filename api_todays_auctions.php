<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'functions.php';

// आज की तारीख
$today = date('Y-m-d');

// डेटाबेस से सिर्फ आज की प्रॉपर्टी चुनें
$query = "SELECT id, title, location, bank_name, icon, auction_date 
          FROM properties 
          WHERE auction_date = '$today' 
          ORDER BY id ASC";

$result = mysqli_query($conn, $query);
$properties = [];

while ($row = mysqli_fetch_assoc($result)) {
    // अगर icon नहीं है तो डिफ़ॉल्ट इमोजी दें
    $row['icon'] = $row['icon'] ?? '🏷️';
    $properties[] = $row;
}

echo json_encode($properties);
?>
