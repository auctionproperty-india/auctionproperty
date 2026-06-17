<?php
// Database Credentials
$host     = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname   = "auctionproperty_p917";
$user     = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port     = "5432";

try {
    // SSL connection stable karne ke liye 'no-verify' mode best hai
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=no-verify";
    
    // PDO Connection
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 15
    ]);
    
} catch (PDOException $e) {
    // Agar error aaya toh seedha dikhega
    die("Database Connection Error: " . $e->getMessage());
}
?>
