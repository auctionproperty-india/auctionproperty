<?php
// Render PostgreSQL के Variables (जो हम Render Dashboard पर डालेंगे)
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

try {
    // PDO का उपयोग कर PostgreSQL से कनेक्ट करें
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Session Start (आगे Login के लिए)
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // अगर यहाँ तक पहुँचे तो कनेक्शन सफल है
    // (हम इसे Index पेज पर चेक करेंगे)
} catch (PDOException $e) {
    // अगर डेटाबेस नहीं जुड़ा तो यहाँ Error दिखेगा और सर्वर रुक जाएगा
    die("❌ Database Connection Failed: " . $e->getMessage());
}
?>
