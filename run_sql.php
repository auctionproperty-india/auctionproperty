<?php
// ============================================================
// 📥 DATABASE QUERY RUNNER - MySQL to PostgreSQL Converter
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Query Runner</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        textarea { width: 100%; height: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: monospace; }
        input[type=submit] { background: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        input[type=submit]:hover { background: #45a049; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📥 Database Query Runner</h1>";

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sql'])) {
    $sql_content = $_POST['sql'];
    
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<div class='success'>✅ Database Connected: $dbname</div>";
        
        // Convert MySQL to PostgreSQL
        $sql_content = str_replace('`', '"', $sql_content);
        $sql_content = str_replace('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;', '', $sql_content);
        $sql_content = str_replace('SET FOREIGN_KEY_CHECKS = 0;', '', $sql_content);
        $sql_content = str_replace('SET FOREIGN_KEY_CHECKS = 1;', '', $sql_content);
        $sql_content = preg_replace('/AUTO_INCREMENT/i', 'SERIAL', $sql_content);
        $sql_content = preg_replace('/INT PRIMARY KEY AUTO_INCREMENT/i', 'SERIAL PRIMARY KEY', $sql_content);
        $sql_content = preg_replace('/TINYINT\(1\)/i', 'BOOLEAN', $sql_content);
        $sql_content = preg_replace('/TINYINT/i', 'SMALLINT', $sql_content);

        // Split statements
        $statements = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sql_content);
        
        $success = 0;
        $failed = 0;
        $total = count($statements);
        
        echo "<div class='info'>⏳ Total statements: $total</div>";
        
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) continue;
            
            if (preg_match('/^DROP TABLE/i', $stmt)) continue;
            
            try {
                $pdo->exec($stmt);
                $success++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'duplicate key') === false) {
                    $failed++;
                    if ($failed <= 5) {
                        echo "<div class='error'>❌ " . htmlspecialchars(substr($e->getMessage(), 0, 100)) . "</div>";
                    }
                }
            }
        }
        
        echo "<div class='success'>✅ Executed: $success successful, $failed failed</div>";

        // Summary
        echo "<h2>📊 Database Summary</h2>";
        $tables = ['users', 'properties', 'packages', 'settings', 'subscriptions', 'wallet_transactions', 'user_spins', 'user_activity_log', 'kyc_documents', 'support_tickets', 'user_properties', 'user_referral_earnings', 'account_entries'];
        
        echo "<table>";
        echo "<tr><th>#</th><th>Table</th><th>Record Count</th></tr>";
        $idx = 1;
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<tr><td>$idx</td><td>$table</td><td>$count</td></tr>";
            } catch (PDOException $e) {
                echo "<tr><td>$idx</td><td>$table</td><td>❌</td></tr>";
            }
            $idx++;
        }
        echo "</table>";

    } catch (PDOException $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Show form
?>
    <div class='info'>📝 Paste your SQL code below and click Run</div>
    <form method="POST">
        <textarea name="sql" placeholder="Paste your SQL code here..."><?php echo isset($_POST['sql']) ? htmlspecialchars($_POST['sql']) : ''; ?></textarea>
        <br><br>
        <input type="submit" value="▶️ Run SQL">
    </form>
</div>
</body>
</html>
