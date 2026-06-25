<?php
// ============================================================
// ✅ Database Connection + Session Handler (कोई Conflict नहीं)
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';
$app_env = getenv('APP_ENV') ?: 'production';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    if ($app_env === 'dev') {
        $pdo->exec("CREATE SCHEMA IF NOT EXISTS dev");
        $pdo->exec("SET search_path TO dev, public");
    }
    
    // ---- ✅ Sessions Table ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(128) NOT NULL PRIMARY KEY,
        data TEXT NOT NULL,
        access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    
    // ---- ✅ Session Handler Register ----
    require_once __DIR__ . '/session_handler.php';
    $handler = new DatabaseSessionHandler($pdo);
    
    // ---- ✅ Session Settings – सिर्फ तभी Set करें जब Session Active न हो ----
    if (session_status() == PHP_SESSION_NONE) {
        // Session Handler और Cookie Params Set करें
        session_set_save_handler($handler, true);
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30, // 30 Days
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        // Session Start
        session_start();
    } else {
        // अगर Session पहले से Active है – तो कुछ न करें (क्योंकि Handler Set नहीं कर सकते)
        // लेकिन हम यहाँ Handler को Register करना चाहते हैं, लेकिन यह Active Session के साथ काम नहीं करेगा
        // इसलिए हम Session को फिर से Start करने से रोकेंगे और Error से बचेंगे
        // ध्यान दें: यदि Session Active है तो Save Handler पहले से ही Set होना चाहिए
        // इसलिए हम यहाँ कोई कार्रवाई नहीं करेंगे
    }
    
} catch (PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}
?>
