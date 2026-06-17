<?php
$host     = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname   = "auctionproperty_p917";
$user     = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port     = "5432";

try {
    // Basic connection string - koi extra SSL option nahi
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
