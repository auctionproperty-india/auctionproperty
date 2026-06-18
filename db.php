<?php
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname = "auctionproperty_p917";
$user = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port = "5432";

try {
    // SSL Required mode
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<h1>Database Connected Successfully!</h1>";
} catch (PDOException $e) {
    die("<h1>Connection Failed:</h1><p>" . $e->getMessage() . "</p>");
}
?>
