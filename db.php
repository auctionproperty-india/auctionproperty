<?php
// ============================================================
// db.php – Database connection for Render + Supabase
// ============================================================

// Read environment variables
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

// Set timezone (optional)
date_default_timezone_set('Asia/Kolkata');

try {
    // Build DSN
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Optional: create sessions table if not exists (if your app needs it)
    // But your backup already creates it – so we skip.

} catch (PDOException $e) {
    // Log error and stop execution (or show friendly message)
    die("❌ Database Connection Failed: " . $e->getMessage());
}

// Now $pdo is defined globally – all files that include db.php can use it.
?>
