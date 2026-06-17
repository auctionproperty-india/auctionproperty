<?php
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname = "auctionproperty_p917";
$user = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port = "5432";

try {
    // DSN string - SSL mode ko 'prefer' rakha hai taaki connection drop na ho
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=prefer";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $conn = new PDO($dsn, $user, $password, $options);
    
    // Agar yahan tak code aaya toh connection successful hai
    echo "<h1>Database Connection: SUCCESSFUL!</h1>";
    
} catch (PDOException $e) {
    // Error show karna
    echo "<h1>Connection Failed</h1>";
    echo "<p>Error Details: " . $e->getMessage() . "</p>";
}
?>
