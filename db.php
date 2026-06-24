<?php
// ============================================================
// ✅ Database Connection + Session Handler Register
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
    
    // ---- ✅ Sessions Table Create (अगर नहीं है) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(128) NOT NULL PRIMARY KEY,
        data TEXT NOT NULL,
        access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    
    // ---- ✅ Database Session Handler Register ----
    require_once __DIR__ . '/session_handler.php';
    $handler = new DatabaseSessionHandler($pdo);
    session_set_save_handler($handler, true);
    
    // ---- ✅ Session Cookie Parameters (30 Days Lifetime) ----
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, // 30 Days
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // ---- ✅ Session Start ----
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
} catch (PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}
?>
