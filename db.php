<?php
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$dbname = "auctionproperty_p917";
$user = "auctionproperty_p917_user";
$password = "JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM";
$port = "5432";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $conn = new PDO($dsn, $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Connection successful
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
