<?php
// रेंडर PostgreSQL कनेक्शन स्ट्रिंग
$database_url = "postgresql://admin:JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM@dpg-d8ok6lflk1mc739ce1j0-a.oregon-postgres.render.com/auction_db_r1hx";

$dbopts = parse_url($database_url);

$host = $dbopts["host"];
$port = isset($dbopts["port"]) ? $dbopts["port"] : "5432"; 
$user = $dbopts["user"];
$pass = $dbopts["pass"];
$dbname = ltrim($dbopts["path"], '/');

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn = $pdo; 

    // 🔥 जादूई कोड: अगर 'users' टेबल नहीं है, तो यह अपने आप बना देगा
    $table_query = "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";
    $conn->exec($table_query);

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
