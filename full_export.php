<?php
// ============================================================
// 📥 Full Database Export – PostgreSQL → MySQL
// (Structure + Data)
// ============================================================

require_once __DIR__ . '/db.php'; // PostgreSQL connection

// Increase memory limit for large exports
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Output as SQL file
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="full_backup_' . date('Y-m-d_H-i-s') . '.sql"');

// Start SQL
$sql = "-- ===================================================\n";
$sql .= "-- Full Database Export – PostgreSQL → MySQL\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- ===================================================\n\n";

// Set foreign key checks off
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

// Get all tables in public schema (PostgreSQL)
$stmt = $pdo->query("
    SELECT table_name 
    FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_type = 'BASE TABLE'
    ORDER BY table_name
");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($tables)) {
    die("No tables found.");
}

$sql .= "-- Tables found: " . implode(', ', $tables) . "\n\n";

// Helper function to map PostgreSQL data types to MySQL
function mapType($pgType) {
    $type = strtolower($pgType);
    if (strpos($type, 'int') !== false || $type === 'serial') return 'INT';
    if (strpos($type, 'bigint') !== false) return 'BIGINT';
    if (strpos($type, 'smallint') !== false) return 'SMALLINT';
    if (strpos($type, 'decimal') !== false || strpos($type, 'numeric') !== false) return 'DECIMAL(10,2)';
    if (strpos($type, 'varchar') !== false) return 'VARCHAR(255)';
    if (strpos($type, 'text') !== false) return 'TEXT';
    if (strpos($type, 'timestamp') !== false || strpos($type, 'datetime') !== false) return 'DATETIME';
    if ($type === 'date') return 'DATE';
    if ($type === 'time') return 'TIME';
    if ($type === 'boolean') return 'TINYINT(1)';
    if ($type === 'json') return 'JSON';
    return 'VARCHAR(255)'; // fallback
}

// Generate CREATE TABLE for each table
foreach ($tables as $table) {
    // Fetch columns
    $cols = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default, character_maximum_length
        FROM information_schema.columns
        WHERE table_name = '$table' AND table_schema = 'public'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);

    $col_defs = [];
    $primary_key = null;
    foreach ($cols as $col) {
        $name = $col['column_name'];
        $type = mapType($col['data_type']);
        $null = ($col['is_nullable'] === 'YES') ? 'NULL' : 'NOT NULL';
        $default = $col['column_default'];

        // Skip default for SERIAL (auto_increment)
        if ($name === 'id' && strpos($default, 'nextval') !== false) {
            $type = 'INT AUTO_INCREMENT';
            $null = 'NOT NULL';
            $primary_key = 'id';
            $col_defs[] = "$name $type $null";
            continue;
        }
        // Handle boolean default (true/false) -> 1/0
        if ($col['data_type'] === 'boolean' && $default !== null) {
            if ($default === 'true') $default = '1';
            elseif ($default === 'false') $default = '0';
        }
        // Handle PostgreSQL current_timestamp
        if ($default && strpos($default, 'current_timestamp') !== false) {
            $default = 'CURRENT_TIMESTAMP';
        }
        // Handle current_date (PostgreSQL) -> MySQL does not support DEFAULT CURRENT_DATE, so we remove default
        if ($default && strpos($default, 'current_date') !== false) {
            $default = null; // remove default, set NULL
        }
        // Escape default string
        if ($default !== null) {
            $default = "'" . addslashes($default) . "'";
        }

        $col_defs[] = "$name $type $null" . ($default ? " DEFAULT $default" : "");
    }

    // Add PRIMARY KEY if id exists
    if ($primary_key) {
        $col_defs[] = "PRIMARY KEY ($primary_key)";
    }

    $create_sql = "CREATE TABLE IF NOT EXISTS $table (\n  " . implode(",\n  ", $col_defs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
    $sql .= $create_sql;
}

// ---- INSERT DATA ----
$sql .= "-- ===================================================\n";
$sql .= "-- Data Insertion\n";
$sql .= "-- ===================================================\n\n";

foreach ($tables as $table) {
    // Get column names excluding 'id' if it's auto_increment
    $cols = $pdo->query("
        SELECT column_name, data_type
        FROM information_schema.columns
        WHERE table_name = '$table' AND table_schema = 'public'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);

    $col_names = [];
    $col_types = [];
    $skip_id = false;
    foreach ($cols as $col) {
        if ($col['column_name'] === 'id') {
            // Check if it's serial (auto increment) – skip for INSERT
            $default = $pdo->query("SELECT column_default FROM information_schema.columns WHERE table_name = '$table' AND column_name = 'id'")->fetchColumn();
            if (strpos($default, 'nextval') !== false) {
                $skip_id = true;
                continue;
            }
        }
        $col_names[] = $col['column_name'];
        $col_types[$col['column_name']] = $col['data_type'];
    }

    // Fetch data
    $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) continue;

    $sql .= "-- Data for $table\n";
    foreach ($rows as $row) {
        $values = [];
        foreach ($col_names as $col) {
            $val = $row[$col];
            if ($val === null) {
                $values[] = 'NULL';
                continue;
            }
            // Boolean conversion
            if ($col_types[$col] === 'boolean') {
                $values[] = ($val === 't' || $val === 'true' || $val === true) ? '1' : '0';
                continue;
            }
            // Numeric
            if (is_numeric($val) && $col_types[$col] !== 'text' && $col_types[$col] !== 'varchar') {
                $values[] = $val;
            } else {
                // Escape string
                $values[] = $pdo->quote($val);
            }
        }
        $insert = "INSERT INTO $table (" . implode(', ', $col_names) . ") VALUES (" . implode(', ', $values) . ");\n";
        $sql .= $insert;
    }
    $sql .= "\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

echo $sql;
exit;
?>
