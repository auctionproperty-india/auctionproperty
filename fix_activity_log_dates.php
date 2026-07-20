<?php
// ============================================================
// 🔧 Fix NULL created_at in user_activity_log
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Activity Log Dates</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Fix Activity Log Dates</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Database Connected</div>";

    // Check how many NULL timestamps exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_activity_log WHERE created_at IS NULL");
    $null_count = $stmt->fetchColumn();
    echo "<div class='info'>ℹ️ Found $null_count rows with NULL created_at</div>";

    if ($null_count > 0) {
        // Update them to current timestamp
        $pdo->exec("UPDATE user_activity_log SET created_at = NOW() WHERE created_at IS NULL");
        echo "<div class='success'>✅ Updated $null_count rows with current timestamp</div>";
    } else {
        echo "<div class='success'>✅ No NULL timestamps found</div>";
    }

    // Set a default for future inserts (optional)
    try {
        $pdo->exec("ALTER TABLE user_activity_log ALTER COLUMN created_at SET DEFAULT NOW()");
        echo "<div class='success'>✅ Default set to NOW() for future rows</div>";
    } catch (PDOException $e) {
        echo "<div class='info'>ℹ️ Default already set or error: " . $e->getMessage() . "</div>";
    }

    // Verify sample
    $stmt = $pdo->query("SELECT id, user_id, activity_type, created_at FROM user_activity_log ORDER BY id DESC LIMIT 5");
    echo "<h2>📊 Last 5 Activity Logs</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>User</th><th>Activity</th><th>Time</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['id']}</td><td>{$row['user_id']}</td><td>{$row['activity_type']}</td><td>{$row['created_at']}</td></tr>";
    }
    echo "</table>";

    echo "<div class='success'>🎉 Fix applied! Admin activity log should now show correct dates.</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
