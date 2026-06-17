<?php
// RENDER POSTGRES CONNECTION
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$port = "5432";
$dbname = "auctionproperty_p917"; 
$user = "auctionproperty_p917_user"; 
// याद रखें: रेंडर के Environment Variables में DB_PASSWORD की Key बना दी है
$password = getenv('DB_PASSWORD'); 

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::PGSQL_ATTR_SSL_MODE     => 'require'
    ];
    $conn = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
?>
