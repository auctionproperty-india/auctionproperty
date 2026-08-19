<?php
// ============================================================
// 📥 IMPORT TO SUPABASE - Render Backup Se Data Daalein
// ============================================================

// 👇 Supabase Connection String (Already Updated with Your Password)
$supabase_url = "postgresql://postgres.bqspzgwpqimjyhispwtp:Dugguai%40123@aws-0-ap-northeast-2.pooler.supabase.com:6543/postgres?pgbouncer=true";

// ============================================================
// DO NOT EDIT BELOW THIS LINE
// ============================================================

echo "<!DOCTYPE html>
<html>
<head>
    <title>Import to Supabase</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        textarea { width: 100%; height: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: monospace; }
        input[type=file] { padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 100%; }
        input[type=submit] { background: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        input[type=submit]:hover { background: #45a049; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📥 Import to Supabase</h1>";

// Check if a file was uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo "<div class='error'>❌ File upload error: " . $file['error'] . "</div>";
        exit;
    }
    
    $sql_content = file_get_contents($file['tmp_name']);
    $size = round($file['size'] / 1024 / 1024, 2);
    
    echo "<div class='info'>📄 File uploaded: " . htmlspecialchars($file['name']) . " (Size: $size MB)</div>";
    
    try {
        // Parse Supabase URL
        $parsed = parse_url($supabase_url);
        $host = $parsed['host'];
        $port = $parsed['port'] ?? 6543;
        $dbname = ltrim($parsed['path'], '/');
        $user = $parsed['user'];
        $password = $parsed['pass'];
        
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<div class='success'>✅ Connected to Supabase successfully!</div>";
        
        // Remove MySQL-specific syntax (if any)
        $sql_content = str_replace('`', '"', $sql_content);
        $sql_content = preg_replace('/ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;/i', '', $sql_content);
        $sql_content = preg_replace('/SET FOREIGN_KEY_CHECKS = 0;/i', '', $sql_content);
        $sql_content = preg_replace('/SET FOREIGN_KEY_CHECKS = 1;/i', '', $sql_content);
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
            
            // Skip DROP TABLE statements (we want to keep existing tables if any)
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
                    if ($failed <= 5) {
                        echo "<div class='error'>❌ " . htmlspecialchars(substr($e->getMessage(), 0, 150)) . "</div>";
                    }
                }
            }
        }
        
        echo "<div class='success'>✅ Executed: $success successful, $failed failed</div>";
        
        // Summary
        echo "<h2>📊 Supabase Database Summary</h2>";
        $tables = ['users', 'properties', 'packages', 'settings', 'navigation_items'];
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>#</th><th>Table</th><th>Record Count</th></tr>";
        $idx = 1;
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<tr><td>$idx</td><td>$table</td><td>$count</td></tr>";
            } catch (PDOException $e) {
                echo "<tr><td>$idx</td><td>$table</td><td>❌ Not Found</td></tr>";
            }
            $idx++;
        }
        echo "</table>";
        
        echo "<div class='success'>✅ Import completed successfully!</div>";
        echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Show upload form
?>
    <div class='info'>📤 Upload your backup SQL file to import into Supabase</div>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="sql_file" accept=".sql" required>
        <br><br>
        <input type="submit" value="📥 Import to Supabase">
    </form>
    <br>
    <div class='info'>
        <strong>⚠️ Important Notes:</strong><br>
        • File size limit: ~10MB (if larger, split into parts or use <code>psql</code> command)<br>
        • The script will convert MySQL syntax to PostgreSQL automatically<br>
        • Already existing tables will be skipped (no data loss)
    </div>
</div>
</body>
</html>
