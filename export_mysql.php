<?php
// ============================================================
// ✅ Export PostgreSQL Data as MySQL Compatible SQL
// ============================================================

// ✅ Security: Only allow if you're logged in as admin
// (यदि आप admin नहीं हैं, तो यह Script न चले)
require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("❌ You must be logged in as admin to export data.");
}

// Set memory limit and execution time for large databases
ini_set('memory_limit', '512M');
set_time_limit(300);

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="mysql_import.sql"');

// Function to convert PostgreSQL value to MySQL compatible string
function mysql_escape($str) {
    return str_replace(["'", "\\"], ["''", "\\\\"], $str);
}

// Function to format value for MySQL INSERT
function formatValue($val) {
    if ($val === null) return 'NULL';
    if (is_numeric($val)) return $val;
    if (is_bool($val)) return $val ? '1' : '0';
    // For strings, escape and wrap in quotes
    return "'" . mysql_escape($val) . "'";
}

// Get all tables
$tables = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")->fetchAll(PDO::FETCH_COLUMN);

// Start output
echo "-- MySQL Compatible Export from PostgreSQL\n";
echo "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// For each table
foreach ($tables as $table) {
    // Skip session table (if any)
    if ($table == 'sessions') continue;

    // Get column names and types
    $columns = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = '$table'")->fetchAll();
    $col_names = array_column($columns, 'column_name');
    $col_list = implode('`, `', $col_names);

    // Fetch all rows
    $rows = $pdo->query("SELECT * FROM \"$table\"")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) continue;

    // Drop existing table (if any) - but we want to replace, so DROP + CREATE
    echo "DROP TABLE IF EXISTS `$table`;\n";

    // Create table structure – we'll get CREATE TABLE from PostgreSQL and convert
    // But simpler: we'll generate CREATE TABLE based on columns.
    // However, for safety, we can fetch the actual CREATE statement using pg_get_tabledef (not available in PDO)
    // Instead, we'll generate a basic CREATE TABLE with appropriate types.
    // For simplicity, we'll use TEXT for all columns, but better to guess types.
    $create_sql = "CREATE TABLE `$table` (\n";
    foreach ($columns as $col) {
        $name = $col['column_name'];
        $type = $col['data_type'];
        // Map PostgreSQL types to MySQL
        $mysql_type = 'TEXT';
        if (stripos($type, 'int') !== false) $mysql_type = 'INT';
        elseif (stripos($type, 'decimal') !== false || stripos($type, 'numeric') !== false) $mysql_type = 'DECIMAL(15,2)';
        elseif (stripos($type, 'timestamp') !== false) $mysql_type = 'TIMESTAMP';
        elseif (stripos($type, 'date') !== false) $mysql_type = 'DATE';
        elseif (stripos($type, 'bool') !== false) $mysql_type = 'TINYINT(1)';
        elseif (stripos($type, 'text') !== false) $mysql_type = 'TEXT';
        elseif (stripos($type, 'varchar') !== false) $mysql_type = 'VARCHAR(255)';
        // Add primary key for 'id' column
        $pk = ($name == 'id') ? ' PRIMARY KEY AUTO_INCREMENT' : '';
        $create_sql .= "    `$name` $mysql_type$pk,\n";
    }
    // Remove trailing comma
    $create_sql = rtrim($create_sql, ",\n") . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
    echo $create_sql;

    // Insert data
    $placeholders = implode(',', array_fill(0, count($col_names), '?'));
    $insert_prefix = "INSERT INTO `$table` (`$col_list`) VALUES\n";
    $first = true;
    foreach ($rows as $row) {
        $values = [];
        foreach ($col_names as $col) {
            $values[] = formatValue($row[$col]);
        }
        $line = "(" . implode(',', $values) . ")";
        if ($first) {
            echo $insert_prefix . $line . "\n";
            $first = false;
        } else {
            echo ",\n" . $line;
        }
    }
    echo ";\n\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
?>
