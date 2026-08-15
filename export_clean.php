<?php
// ============================================================
// 📥 Clean Database Export – PostgreSQL → MySQL
// (No Warnings, No HTML)
// ============================================================

// Turn off error reporting to avoid warnings in output
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/db.php'; // PostgreSQL connection

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="clean_backup_' . date('Y-m-d_H-i-s') . '.sql"');

$sql = "-- ===================================================\n";
$sql .= "-- Clean Database Export – PostgreSQL → MySQL\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- ===================================================\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

// Get all tables
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
    return 'VARCHAR(255)';
}

foreach ($tables as $table) {
    $cols = $pdo->query("
        SELECT column_name, data_type, is_nullable, column_default
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

        if ($name === 'id' && $default && strpos($default, 'nextval') !== false) {
            $type = 'INT AUTO_INCREMENT';
            $null = 'NOT NULL';
            $primary_key = 'id';
            $col_defs[] = "$name $type $null";
            continue;
        }

        // Convert boolean defaults
        if ($col['data_type'] === 'boolean' && $default !== null) {
            if ($default === 'true') $default = '1';
            elseif ($default === 'false') $default = '0';
        }

        // Handle PostgreSQL defaults
        if ($default && strpos($default, 'current_timestamp') !== false) {
            $default = 'CURRENT_TIMESTAMP';
        }
        if ($default && strpos($default, 'current_date') !== false) {
            $default = null;
        }

        if ($default !== null) {
            $default = "'" . addslashes($default) . "'";
        }

        $col_defs[] = "$name $type $null" . ($default ? " DEFAULT $default" : "");
    }

    if ($primary_key) {
        $col_defs[] = "PRIMARY KEY ($primary_key)";
    }

    $create_sql = "CREATE TABLE IF NOT EXISTS $table (\n  " . implode(",\n  ", $col_defs) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
    $sql .= $create_sql;
}

$sql .= "-- ===================================================\n";
$sql .= "-- Data Insertion\n";
$sql .= "-- ===================================================\n\n";

foreach ($tables as $table) {
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
            $default = $pdo->query("SELECT column_default FROM information_schema.columns WHERE table_name = '$table' AND column_name = 'id'")->fetchColumn();
            if ($default && strpos($default, 'nextval') !== false) {
                $skip_id = true;
                continue;
            }
        }
        $col_names[] = $col['column_name'];
        $col_types[$col['column_name']] = $col['data_type'];
    }

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
            if ($col_types[$col] === 'boolean') {
                $values[] = ($val === 't' || $val === 'true' || $val === true) ? '1' : '0';
                continue;
            }
            if (is_numeric($val) && $col_types[$col] !== 'text' && $col_types[$col] !== 'varchar') {
                $values[] = $val;
            } else {
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
