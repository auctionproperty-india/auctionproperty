<?php
// ============================================================
// db.php – Database connection + File-based Sessions
// ============================================================

// ============================================================
// 1. Read Environment Variables
// ============================================================
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

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
// 4. Session Handling – File-based with /tmp
// ============================================================
// Ensure session save path is writable
ini_set('session.save_path', '/tmp');
ini_set('session.gc_maxlifetime', 86400); // 24 hours

if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ============================================================
// 5. $pdo is globally available
// ============================================================
?>
