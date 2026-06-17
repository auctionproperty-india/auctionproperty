<?php
// Database Configuration
$host     = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname   = "auctionproperty_p917";
$user     = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port     = "5432";

try {
    // Connection string with SSL prefer mode to prevent unexpected closure
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=prefer";
    
    // Establishing the connection
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10 // Timeout set kiya hai taaki request hang na ho
    ]);
    
} catch (PDOException $e) {
    // Agar koi dikkat aati hai toh error dikh jayega
    die("Database Connection Error: " . $e->getMessage());
}
?>
