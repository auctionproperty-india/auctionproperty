<?php
// Database Credentials
$host     = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname   = "auctionproperty_p917";
$user     = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port     = "5432";

try {
    // SSL error ko bypass karne ke liye hum direct DSN use karenge
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    // Connection Options
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // SSL ke liye kuch servers ko options ki zarurat hoti hai
        PDO::PGSQL_ATTR_SSL_MODE => 'prefer' 
    ];

    $conn = new PDO($dsn, $user, $password, $options);
    
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
