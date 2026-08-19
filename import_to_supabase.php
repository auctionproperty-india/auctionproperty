<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<h1>📥 Import to Supabase</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("❌ Upload error");
    }
    $sql = file_get_contents($file['tmp_name']);
    $size = round(filesize($file['tmp_name']) / 1024 / 1024, 2);
    echo "<p>File: " . $file['name'] . " ($size MB)</p>";
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p style='color:green;'>✅ Connected to Supabase!</p>";
        
        // ============================================================
        // 1. MySQL → PostgreSQL conversions
        // ============================================================
        $sql = str_replace('`', '"', $sql);
        $sql = str_replace('\\', '', $sql);
        $sql = preg_replace('/ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;/i', '', $sql);
        $sql = preg_replace('/SET FOREIGN_KEY_CHECKS = 0;/i', '', $sql);
        $sql = preg_replace('/SET FOREIGN_KEY_CHECKS = 1;/i', '', $sql);
        $sql = preg_replace('/AUTO_INCREMENT/i', 'SERIAL', $sql);
        $sql = preg_replace('/INT PRIMARY KEY AUTO_INCREMENT/i', 'SERIAL PRIMARY KEY', $sql);
        $sql = preg_replace('/TINYINT\(1\)/i', 'BOOLEAN', $sql);
        $sql = preg_replace("/\r\n/", "\n", $sql);
        $sql = str_replace("''", 'NULL', $sql);
        
        // ============================================================
        // 2. 🔥 FIX: Remove NOT NULL constraint from sessions.data
        //    (Any spacing/case variation)
        // ============================================================
        $sql = preg_replace('/data\s+TEXT\s+NOT\s+NULL/i', 'data TEXT', $sql);
        $sql = preg_replace('/"data"\s+TEXT\s+NOT\s+NULL/i', '"data" TEXT', $sql);
        $sql = preg_replace('/data\s+text\s+not\s+null/i', 'data TEXT', $sql);
        
        // Also, if sessions table already exists, drop it first
        $sql = "DROP TABLE IF EXISTS sessions CASCADE;\n" . $sql;
        
        // ============================================================
        // 3. Drop other tables (clean slate)
        // ============================================================
        $drop_tables = ['users', 'properties', 'packages', 'settings', 'navigation_items'];
        foreach ($drop_tables as $t) {
            try { $pdo->exec("DROP TABLE IF EXISTS $t CASCADE"); } catch (Exception $e) {}
        }
        
        // ============================================================
        // 4. Execute as a single transaction
        // ============================================================
        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $pdo->commit();
            echo "<p style='color:green;'>✅ Import completed successfully!</p>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<p style='color:red;'>❌ Import failed: " . $e->getMessage() . "</p>";
        }
        
        // ============================================================
        // 5. Summary
        // ============================================================
        $tables = ['users','properties','packages','settings','navigation_items'];
        echo "<h2>📊 Database Summary</h2><table border='1'><tr><th>Table</th><th>Rows</th></tr>";
        foreach ($tables as $t) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
                echo "<tr><td>$t</td><td>$count</td></tr>";
            } catch (Exception $e) {
                echo "<tr><td>$t</td><td>❌</td></tr>";
            }
        }
        echo "</table>";
        echo "<p><a href='/'>🔗 Open Website</a></p>";
        
    } catch (PDOException $e) {
        echo "<p style='color:red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
    }
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="sql_file" accept=".sql" required>
    <br><br>
    <input type="submit" value="Import to Supabase">
</form>
