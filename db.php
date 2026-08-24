<?php
// ============================================================
// db.php – Database Connection + Database Sessions (Stable)
// ============================================================

// ----- Read environment variables -----
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

// Fallback to DATABASE_URL if individual vars are missing
$database_url = getenv('DATABASE_URL');
if ($database_url && (!$host || $host === 'localhost')) {
    $parts = parse_url($database_url);
    $host = $parts['host'];
    $port = $parts['port'] ?? 5432;
    $dbname = ltrim($parts['path'], '/');
    $user = $parts['user'];
    $password = $parts['pass'];
}

date_default_timezone_set('Asia/Kolkata');

// ----- Connect to Supabase -----
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}

// ----- Create sessions table if not exists (for database sessions) -----
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data TEXT NOT NULL,
            access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    // ignore if table already exists
}

// ----- Database session handler -----
require_once __DIR__ . '/session_handler.php';

$handler = new DatabaseSessionHandler($pdo);

if (session_status() == PHP_SESSION_NONE) {
    session_set_save_handler($handler, true);
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// $pdo is now globally available
?>
