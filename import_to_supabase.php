<?php
// ============================================================
// 📥 IMPORT TO SUPABASE - Render Backup Se Data Daalein
// ============================================================

// 👇 YAHAN APNI SUPABASE CONNECTION STRING PASTE KAREIN
$supabase_url = "postgresql://postgres.[PROJECT-REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:6543/postgres?pgbouncer=true";

echo "<!DOCTYPE html>
<html>
<head>
    <title>Import to Supabase</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        textarea { width: 100%; height: 200px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: monospace; }
        input[type=file] { padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        input[type=submit] { background: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        input[type=submit]:hover { background: #45a049; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📥 Import to Supabase</h1>";

// Check if backup file uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo "<div class='error'>❌ File upload error: " . $file['error'] . "</div>";
        exit;
    }
    
    $sql_content = file_get_contents($file['tmp_name']);
    $size = round($file['size'] / 1024 / 1024, 2);
    
    echo "<div class='info'>📄 File uploaded: " . $file['name'] . " (Size: $size MB)</div>";
    
    try {
        // Parse Supabase URL
        $parsed = parse_url($supabase_url);
        $host = $parsed['host'];
        $port = $parsed['port'] ?? 5432;
        $dbname = ltrim($parsed['path'], '/');
        $user = $parsed['user'];
        $password = $parsed['pass'];
        
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<div class='success'>✅ Connected to Supabase!</div>";
        
        // Split and execute
        $statements = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sql_content);
        
        $success = 0;
        $failed = 0;
        $total = count($statements);
        
        echo "<div class='info'>⏳ Total statements: $total</div>";
        
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) continue;
            
            try {
                $pdo->exec($stmt);
                $success++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false) {
                    $failed++;
                }
            }
        }
        
        echo "<div class='success'>✅ Executed: $success successful, $failed failed</div>";
        
        // Summary
        echo "<h2>📊 Supabase Database Summary</h2>";
        $tables = ['users', 'properties', 'packages', 'settings'];
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>#</th><th>Table</th><th>Count</th></tr>";
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
    <div class='info'>📤 Upload your backup SQL file to import into Supabase</div>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="sql_file" accept=".sql" required>
        <br><br>
        <input type="submit" value="📥 Import to Supabase">
    </form>
    <br>
    <div class='info'>
        <strong>⚠️ Important:</strong><br>
        1. Pehle <strong>backup_render.php</strong> run karein aur backup download karein<br>
        2. Phir yahan us file ko upload karein<br>
        3. Supabase connection string update karein (script ke top par)
    </div>
</div>
</body>
</html>
