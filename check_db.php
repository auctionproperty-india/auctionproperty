<?php
// ============================================================
// 🔍 Check Database Connection
// ============================================================

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$database_url = getenv('DATABASE_URL');

echo "<h1>🔍 Database Connection Check</h1>";

echo "<h2>Environment Variables:</h2>";
echo "<pre>";
echo "DB_HOST: " . ($host ?: 'NOT SET') . "\n";
echo "DB_PORT: " . ($port ?: 'NOT SET') . "\n";
echo "DB_NAME: " . ($dbname ?: 'NOT SET') . "\n";
echo "DB_USER: " . ($user ?: 'NOT SET') . "\n";
echo "DB_PASSWORD: " . ($password ?: 'NOT SET') . "\n";
echo "DATABASE_URL: " . ($database_url ?: 'NOT SET') . "\n";
echo "</pre>";

if ($database_url) {
    echo "<h2>✅ DATABASE_URL is set!</h2>";
    
    try {
        $pdo = new PDO($database_url);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<h2 style='color:green;'>✅ Connected to Supabase Successfully!</h2>";
    } catch (PDOException $e) {
        echo "<h2 style='color:red;'>❌ Connection Failed: " . $e->getMessage() . "</h2>";
    }
} else {
    echo "<h2 style='color:red;'>❌ DATABASE_URL is NOT set!</h2>";
}
?>
