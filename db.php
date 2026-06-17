<?php
// Render / Supabase PostgreSQL Connection
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$user = "prime_db_user";
$password = "fW6X8gBfOatV8vA8HlFpIn6kWeO2N5nI";
$dbname = "prime_db_a91h";
$port = "5432";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $conn = new PDO($dsn, $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 🔥 PostgreSQL COMPATIBLE AUTO-PATCH
    // यह चेक करेगा कि referred_by कॉलम है या नहीं, नहीं होगा तो तुरंत बना देगा
    $checkQuery = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='referred_by'");
    if ($checkQuery->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN referred_by VARCHAR(100) DEFAULT NULL");
    }
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}
?>
