<?php
// ============================================================
// 📥 BACKUP SCRIPT - Render Database Se Backup Lein
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Backup Render Database</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow: auto; max-height: 400px; }
        .btn { background: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; margin: 10px 0; }
        .btn:hover { background: #45a049; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📥 Render Database Backup</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database Connected: $dbname</div>";

    // ============================================================
    // GET ALL TABLES
    // ============================================================
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "<div class='error'>❌ No tables found in database!</div>";
        exit;
    }

    echo "<div class='info'>📊 Found " . count($tables) . " tables</div>";

    // ============================================================
    // GENERATE SQL DUMP
    // ============================================================
    $sql_dump = "-- ============================================================\n";
    $sql_dump .= "-- Backup from Render Database\n";
    $sql_dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Database: $dbname\n";
    $sql_dump .= "-- ============================================================\n\n";

    // Disable triggers for clean import
    $sql_dump .= "SET session_replication_role = 'replica';\n\n";

    foreach ($tables as $table) {
        echo "<div class='info'>⏳ Processing: $table</div>";
        
        // Get table structure
        $stmt = $pdo->query("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns 
            WHERE table_name = '$table' 
            ORDER BY ordinal_position
        ");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build CREATE TABLE
        $sql_dump .= "-- Table: $table\n";
        $sql_dump .= "DROP TABLE IF EXISTS $table CASCADE;\n";
        $sql_dump .= "CREATE TABLE $table (\n";
        
        $col_defs = [];
        foreach ($columns as $col) {
            $def = "    " . $col['column_name'] . " " . $col['data_type'];
            if ($col['is_nullable'] === 'NO' && $col['column_default'] === null) {
                $def .= " NOT NULL";
            }
            if ($col['column_default'] !== null && strpos($col['column_default'], 'nextval') === false) {
                $def .= " DEFAULT " . $col['column_default'];
            }
            $col_defs[] = $def;
        }
        
        // Add primary key if exists
        $stmt = $pdo->query("
            SELECT column_name 
            FROM information_schema.key_column_usage 
            WHERE table_name = '$table' 
            AND constraint_name LIKE '%pkey%'
        ");
        $pk = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pk) {
            $col_defs[] = "    PRIMARY KEY (" . $pk['column_name'] . ")";
        }
        
        $sql_dump .= implode(",\n", $col_defs);
        $sql_dump .= "\n);\n\n";
        
        // Get data
        $stmt = $pdo->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $sql_dump .= "-- Data for: $table\n";
            $sql_dump .= "INSERT INTO $table VALUES\n";
            
            $value_rows = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($val)) {
                        $values[] = $val;
                    } else {
                        // Escape single quotes
                        $val = str_replace("'", "''", $val);
                        $values[] = "'" . $val . "'";
                    }
                }
                $value_rows[] = "(" . implode(", ", $values) . ")";
            }
            $sql_dump .= implode(",\n", $value_rows);
            $sql_dump .= ";\n\n";
        }
    }

    // Enable triggers again
    $sql_dump .= "SET session_replication_role = 'origin';\n";

    // ============================================================
    // SAVE OR DOWNLOAD
    // ============================================================
    $filename = 'render_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Save file on server
    file_put_contents($filename, $sql_dump);
    
    $size = round(filesize($filename) / 1024 / 1024, 2);
    
    echo "<div class='success'>✅ Backup created successfully!</div>";
    echo "<div class='info'>📄 File: $filename (Size: $size MB)</div>";
    echo "<div class='info'>📊 Total tables: " . count($tables) . "</div>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='$filename' download class='btn'>📥 Download Backup File</a>";
    echo "</div>";
    
    // Show first few lines
    echo "<div class='info'>📝 Preview (first 500 characters):</div>";
    echo "<pre>" . htmlspecialchars(substr($sql_dump, 0, 500)) . "...</pre>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
