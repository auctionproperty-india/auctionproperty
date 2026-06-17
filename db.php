<?php
// Render ke Dashboard se ye values utha raha hai
$host     = getenv('DB_HOST');
$dbname   = getenv('DB_NAME');
$user     = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$port     = "5432"; // Fix port

try {
    // Connection string ka sahi format
    $dsn = "pgsql:host=" . $host . ";port=" . $port . ";dbname=" . $dbname;
    
    // PDO Connection
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // Agar error aaya toh seedha pata chal jayega
    die("Database Connection Error: " . $e->getMessage());
}
?>
