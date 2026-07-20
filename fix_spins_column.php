<?php
// ============================================================
// 🔧 Fix user_spins column: reward_given to BOOLEAN
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Spin Column</title>
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
    <h1>🔧 Fix user_spins Column</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Database Connected</div>";

    // Alter column type to BOOLEAN
    $pdo->exec("ALTER TABLE user_spins ALTER COLUMN reward_given TYPE BOOLEAN USING reward_given::BOOLEAN");
    echo "<div class='success'>✅ Column 'reward_given' changed to BOOLEAN</div>";

    // Check
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'user_spins' AND column_name = 'reward_given'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<div class='info'>📊 Column info: " . print_r($col, true) . "</div>";

    echo "<div class='success'>🎉 Fix applied! Now try opening your dashboard.</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
