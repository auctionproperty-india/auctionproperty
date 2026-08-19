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
    $sql_content = file_get_contents($file['tmp_name']);
    $size = round(filesize($file['tmp_name']) / 1024 / 1024, 2);
    echo "<p>File: " . $file['name'] . " ($size MB)</p>";
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<p style='color:green;'>✅ Connected to Supabase!</p>";
        
        // Convert MySQL to PostgreSQL (if needed)
        $sql_content = str_replace('`', '"', $sql_content);
        $sql_content = preg_replace('/ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;/i', '', $sql_content);
        $sql_content = preg_replace('/SET FOREIGN_KEY_CHECKS = 0;/i', '', $sql_content);
        $sql_content = preg_replace('/SET FOREIGN_KEY_CHECKS = 1;/i', '', $sql_content);
        $sql_content = preg_replace('/AUTO_INCREMENT/i', 'SERIAL', $sql_content);
        $sql_content = preg_replace('/INT PRIMARY KEY AUTO_INCREMENT/i', 'SERIAL PRIMARY KEY', $sql_content);
        $sql_content = preg_replace('/TINYINT\(1\)/i', 'BOOLEAN', $sql_content);
        
        $statements = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sql_content);
        $success = $failed = 0;
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
                    if ($failed <= 5) echo "<p style='color:red;'>❌ " . substr($e->getMessage(), 0, 150) . "</p>";
                }
            }
        }
        echo "<p style='color:green;'>✅ Imported: $success successful, $failed failed</p>";
        
        // Summary
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
