<?php
// ============================================================
// 📥 FINAL IMPORT - Complete Version with All Paths
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Final Import</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📥 Final Import - mysql_import.sql</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database Connected: $dbname</div>";

    // ============================================================
    // LOCATE SQL FILE - ALL POSSIBLE PATHS
    // ============================================================
    echo "<h2>🔍 Searching for mysql_import.sql...</h2>";
    
    // Saare possible paths check karein
    $possible_paths = [
        __DIR__ . '/mysql_import.sql',
        '/var/www/html/mysql_import.sql',
        getcwd() . '/mysql_import.sql',
        $_SERVER['DOCUMENT_ROOT'] . '/mysql_import.sql',
        '/app/mysql_import.sql',
        './mysql_import.sql',
        'mysql_import.sql',
        '/opt/render/project/src/mysql_import.sql',
        '/home/render/mysql_import.sql',
        '/data/mysql_import.sql',
        '/tmp/mysql_import.sql'
    ];
    
    $sql_file = null;
    
    // Pehle saare paths check karein
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $sql_file = $path;
            echo "<div class='success'>✅ Found at: $path</div>";
            break;
        }
    }
    
    // Agar nahi mila toh current directory mein .sql files search karein
    if (!$sql_file) {
        $files = glob("*.sql");
        if (!empty($files)) {
            $sql_file = $files[0];
            echo "<div class='success'>✅ Found SQL file: " . basename($sql_file) . " (in current directory)</div>";
        }
    }
    
    // Agar phir bhi nahi mila toh error dikhayein
    if (!$sql_file) {
        echo "<div class='error'>❌ mysql_import.sql NOT FOUND!</div>";
        echo "<div class='info'>📂 Current directory: " . __DIR__ . "</div>";
        echo "<div class='info'>📂 Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</div>";
        echo "<div class='info'>📂 getcwd(): " . getcwd() . "</div>";
        echo "<div class='info'>📄 Files found: " . implode(", ", glob("*")) . "</div>";
        die("Please upload mysql_import.sql file.");
    }
    
    // ============================================================
    // READ SQL FILE
    // ============================================================
    echo "<h2>📖 Reading mysql_import.sql...</h2>";
    
    $sql_content = file_get_contents($sql_file);
    $size = round(filesize($sql_file) / 1024 / 1024, 2);
    echo "<div class='info'>📄 File size: $size MB</div>";
    
    // ============================================================
    // CONVERT MySQL TO PostgreSQL
    // ============================================================
    echo "<h2>🔄 Converting MySQL to PostgreSQL...</h2>";
    
    // Remove MySQL-specific syntax
    $sql_content = str_replace('`', '"', $sql_content);
    $sql_content = str_replace('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;', '', $sql_content);
    $sql_content = str_replace('SET FOREIGN_KEY_CHECKS = 0;', '', $sql_content);
    $sql_content = str_replace('SET FOREIGN_KEY_CHECKS = 1;', '', $sql_content);
    
    // Convert data types
    $sql_content = preg_replace('/AUTO_INCREMENT/i', 'SERIAL', $sql_content);
    $sql_content = preg_replace('/INT PRIMARY KEY AUTO_INCREMENT/i', 'SERIAL PRIMARY KEY', $sql_content);
    $sql_content = preg_replace('/TINYINT\(1\)/i', 'BOOLEAN', $sql_content);
    $sql_content = preg_replace('/TINYINT/i', 'SMALLINT', $sql_content);
    
    // ============================================================
    // SPLIT AND EXECUTE
    // ============================================================
    echo "<h2>📝 Executing SQL statements...</h2>";
    
    $statements = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sql_content);
    
    $success = 0;
    $failed = 0;
    $total = count($statements);
    
    echo "<div class='info'>⏳ Total statements: $total</div>";
    
    $error_messages = [];
    
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        
        // Skip DROP TABLE IF EXISTS
        if (preg_match('/^DROP TABLE/i', $stmt)) {
            continue;
        }
        
        try {
            $pdo->exec($stmt);
            $success++;
        } catch (PDOException $e) {
            // Ignore "already exists" and "duplicate key" errors
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'duplicate key') === false) {
                $failed++;
                if ($failed <= 10) {
                    $error_messages[] = htmlspecialchars(substr($e->getMessage(), 0, 150));
                }
            }
        }
    }
    
    echo "<div class='success'>✅ Executed: $success successful, $failed failed</div>";
    
    if (!empty($error_messages)) {
        echo "<div class='info'>⚠️ First few errors:</div>";
        foreach ($error_messages as $msg) {
            echo "<div class='error'>❌ $msg</div>";
        }
    }

    // ============================================================
    // VERIFY DATA
    // ============================================================
    echo "<h2>📊 Database Summary</h2>";
    
    $tables = ['users', 'properties', 'packages', 'settings', 'subscriptions', 
               'wallet_transactions', 'user_spins', 'user_activity_log', 
               'kyc_documents', 'support_tickets', 'user_properties', 
               'user_referral_earnings', 'account_entries'];
    
    echo "<table>";
    echo "<tr><th>#</th><th>Table</th><th>Record Count</th><th>Status</th></tr>";
    
    $idx = 1;
    $total_records = 0;
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            $total_records += $count;
            $status = $count > 0 ? '✅' : '⚠️ Empty';
            echo "<tr><td>$idx</td><td>$table</td><td>$count</td><td>$status</td></tr>";
            $idx++;
        } catch (PDOException $e) {
            echo "<tr><td>$idx</td><td>$table</td><td>❌ Not Found</td><td>❌</td></tr>";
            $idx++;
        }
    }
    echo "</table>";
    
    echo "<div class='success'>✅ Total Records: $total_records</div>";

    echo "<hr>";
    echo "<div class='success'>✅ Import completed successfully!</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
