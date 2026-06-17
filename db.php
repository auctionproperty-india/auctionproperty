<?php
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname = "auctionproperty_p917";
$user = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port = "5432";

try {
    // SSL Connection parameter update kiya hai
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    // Kuch servers par options ki zarurat hoti hai
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $conn = new PDO($dsn, $user, $password, $options);
    echo "<h1>SUCCESS: Database Connected Securely!</h1>";
} catch (PDOException $e) {
    die("<h1>Connection Failed</h1><p>" . $e->getMessage() . "</p>");
}
?>
