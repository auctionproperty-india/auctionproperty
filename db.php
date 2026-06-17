<?php
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$port = "5432";
$dbname = "auctionproperty_p917"; 
$user = "auctionproperty_p917_user"; 
$password = getenv('DB_PASSWORD'); // Render pe ye variable set rakhna

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::PGSQL_ATTR_SSL_MODE => 'require'
    ]);

    // AUTO-TABLE SETUP (Agar table nahi hai, to ye khud bana dega)
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'user'
    )";
    $conn->exec($sql);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
