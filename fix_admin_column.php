<?php
// ============================================================
// 🔧 Fix is_super_admin column to BOOLEAN
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Admin Column</title>
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
    <h1>🔧 Fix is_super_admin Column</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Database Connected</div>";

    // Step 1: Drop default if exists
    try {
        $pdo->exec("ALTER TABLE users ALTER COLUMN is_super_admin DROP DEFAULT");
        echo "<div class='info'>✅ Dropped default from is_super_admin</div>";
    } catch (PDOException $e) {
        echo "<div class='info'>ℹ️ No default found or already dropped</div>";
    }

    // Step 2: Change column type to BOOLEAN
    $pdo->exec("ALTER TABLE users ALTER COLUMN is_super_admin TYPE BOOLEAN USING is_super_admin::BOOLEAN");
    echo "<div class='success'>✅ Column 'is_super_admin' changed to BOOLEAN</div>";

    // Step 3: Set default FALSE
    try {
        $pdo->exec("ALTER TABLE users ALTER COLUMN is_super_admin SET DEFAULT FALSE");
        echo "<div class='success'>✅ Default set to FALSE</div>";
    } catch (PDOException $e) {
        echo "<div class='info'>ℹ️ Could not set default: " . $e->getMessage() . "</div>";
    }

    // Also fix any other boolean-like columns if needed
    // manual_referral_updated might also be boolean
    try {
        $pdo->exec("ALTER TABLE users ALTER COLUMN manual_referral_updated DROP DEFAULT");
        $pdo->exec("ALTER TABLE users ALTER COLUMN manual_referral_updated TYPE BOOLEAN USING manual_referral_updated::BOOLEAN");
        $pdo->exec("ALTER TABLE users ALTER COLUMN manual_referral_updated SET DEFAULT FALSE");
        echo "<div class='success'>✅ Also fixed 'manual_referral_updated' column</div>";
    } catch (PDOException $e) {
        echo "<div class='info'>ℹ️ manual_referral_updated not changed: " . $e->getMessage() . "</div>";
    }

    // Verify
    $stmt = $pdo->query("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = 'users' AND column_name IN ('is_super_admin', 'manual_referral_updated')");
    echo "<h2>📊 Column Info</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Column</th><th>Type</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['column_name']}</td><td>{$row['data_type']}</td><td>{$row['column_default']}</td></tr>";
    }
    echo "</table>";

    echo "<div class='success'>🎉 Fix applied! Now sub-admin page should work.</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
