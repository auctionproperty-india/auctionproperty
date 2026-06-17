<?php
// Render PostgreSQL Database Connection Core (No Manual Password Needed)
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$port = "5432";
$dbname = "auctionproperty_p917"; 
$user = "auctionproperty_p917_user"; 

// 🔥 यह रेंडर के एनवायरनमेंट से अपने आप पासवर्ड खींच लेगा
$password = getenv('DB_PASSWORD') ?: "YOUR_FALLBACK_PASSWORD"; 

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::PGSQL_ATTR_SSL_MODE     => 'require' // रेंडर कनेक्शन एरर फिक्स
    ];
    
    $conn = new PDO($dsn, $user, $password, $options);
    
} catch (PDOException $e) {
    die("Database Matrix Connection Terminated: " . $e->getMessage());
}
?>
