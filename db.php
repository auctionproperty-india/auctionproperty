<?php
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname = "auctionproperty_p917";
$user = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port = "5432";

try {
    // 'prefer' mode SSL ka best option hai jab connection drop ho raha ho
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=prefer";
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<h1>SUCCESS: Database Connected!</h1>";
} catch (PDOException $e) {
    die("<h1>Connection Failed:</h1><p>" . $e->getMessage() . "</p>");
}
?>
