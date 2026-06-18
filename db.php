<?php
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname = "auctionproperty_p917";
$user = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port = "5432";

try {
    // DSN String with SSL mode
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    // Connection options
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $conn = new PDO($dsn, $user, $password, $options);
    
    // Success message for testing
    echo "<h1>DATABASE CONNECTION SUCCESSFUL</h1>";
    
} catch (PDOException $e) {
    // Detailed error output for troubleshooting
    die("<h1>DATABASE CONNECTION FAILED</h1><p>Error: " . $e->getMessage() . "</p>");
}
?>
