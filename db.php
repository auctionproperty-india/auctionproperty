<?php
// रेंडर PostgreSQL कनेक्शन PDO के जरिए
$dbopts = parse_url("postgresql://admin:JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM@dpg-d8ok6lflk1mc739ce1j0-a.oregon-postgres.render.com/auction_db_r1hx");

$host = $dbopts["host"];
$port = $dbopts["port"];
$user = $dbopts["user"];
$pass = $dbopts["pass"];
$dbname = ltrim($dbopts["path"], '/');

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn = $pdo; 
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
