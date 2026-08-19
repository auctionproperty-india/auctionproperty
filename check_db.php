<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<h1>🔍 Database Connection Check</h1>";
echo "<pre>";
echo "DB_HOST: $host\n";
echo "DB_PORT: $port\n";
echo "DB_NAME: $dbname\n";
echo "DB_USER: $user\n";
echo "DB_PASSWORD: " . ($password ? '****' : 'NOT SET') . "\n";
echo "</pre>";

try {
    // ✅ Correct DSN for PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2 style='color:green;'>✅ Connected to Supabase Successfully!</h2>";
    
    // Show tables
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>📊 Tables:</h3><ul>";
    foreach ($tables as $t) echo "<li>$t</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<h2 style='color:red;'>❌ Connection Failed: " . $e->getMessage() . "</h2>";
}
?>
