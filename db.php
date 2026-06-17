<?php
// रेंडर PostgreSQL कनेक्शन स्ट्रिंग
$database_url = "postgresql://admin:JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM@dpg-d8ok6lflk1mc739ce1j0-a.oregon-postgres.render.com/auction_db_r1hx";

$dbopts = parse_url($database_url);

$host = $dbopts["host"];
// अगर यूआरएल में पोर्ट नहीं है, तो अपने आप डिफ़ॉल्ट 5432 पोर्ट ले लेगा, एरर नहीं आएगी
$port = isset($dbopts["port"]) ? $dbopts["port"] : "5432"; 
$user = $dbopts["user"];
$pass = $dbopts["pass"];
$dbname = ltrim($dbopts["path"], '/');

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn = $pdo; // आपके पुराने कोड के सपोर्ट के लिए
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
