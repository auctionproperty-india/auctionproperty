<?php
// अपनी Aiven.io डेटाबेस की डिटेल्स यहाँ भरें
$host = "आपका_AIVEN_HOST_यहाँ_डालें"; 
$port = "आपका_AIVEN_PORT_यहाँ_डालें"; // ऐवन पर जो पोर्ट लिखा हो (जैसे 11494 या 3306)
$user = "avnadmin"; 
$password = "आपका_AIVEN_PASSWORD_यहाँ_डालें";
$dbname = "defaultdb"; // ऐवन पर डेटाबेस का नाम आमतौर पर defaultdb ही होता है

// डेटाबेस से कनेक्शन बनाना
$conn = mysqli_connect($host, $user, $password, $dbname, $port);

// अगर कनेक्शन फेल हो जाए तो स्क्रीन पर एरर दिखे
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
?>
