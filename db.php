<?php
// ============================================================
// db.php – Database connection with Session Handler
// ============================================================

// ============================================================
// 1. Read Environment Variables
// ============================================================
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

// If individual vars are missing, fall back to DATABASE_URL
$database_url = getenv('DATABASE_URL');
if ($database_url && (!$host || $host === 'localhost')) {
    $parts = parse_url($database_url);
    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    $dbname = ltrim($parts['path'], '/');
    $user = $parts['user'];
    $password = $parts['pass'];
}

// ============================================================
// 2. Set Timezone
// ============================================================
date_default_timezone_set('Asia/Kolkata');

// ============================================================
// 3. Database Connection
// ============================================================
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}

// ============================================================
// 4. Sessions Table (if not exists)
// ============================================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data TEXT NOT NULL,
            access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    // Table might already exist, ignore error
}

// ============================================================
// 5. Session Handler (Database-based)
// ============================================================
require_once __DIR__ . '/session_handler.php';

$handler = new DatabaseSessionHandler($pdo);

if (session_status() == PHP_SESSION_NONE) {
    session_set_save_handler($handler, true);
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, // 30 days
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ============================================================
// 6. $pdo is now globally available
// ============================================================
?>
