<?php
// ============================================================
// ✅ Check Database Tables
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Check Database</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📊 Database Check</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database Connected</div>";

    // Get all tables
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>📋 Tables Found:</h2>";
    
    if (empty($tables)) {
        echo "<div class='error'>❌ No tables found in database!</div>";
    } else {
        echo "<table>";
        echo "<tr><th>#</th><th>Table Name</th><th>Row Count</th><th>Status</th></tr>";
        foreach ($tables as $index => $table) {
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $count_stmt->fetchColumn();
            
            $status = ($count > 0) ? "✅ Has Data" : "⚠️ Empty";
            $color = ($count > 0) ? "style='color: green;'" : "style='color: orange;'";
            
            echo "<tr>
                <td>" . ($index + 1) . "</td>
                <td><strong>$table</strong></td>
                <td>$count rows</td>
                <td $color>$status</td>
            </tr>";
        }
        echo "</table>";
    }
    
    // Special check for properties and users
    echo "<h2>🔍 Specific Checks:</h2>";
    
    // Check properties table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM properties");
        $prop_count = $stmt->fetchColumn();
        echo "<div class='info'>🏠 Properties: <strong>$prop_count</strong> records found</div>";
        
        if ($prop_count > 0) {
            // Show sample data
            $sample = $pdo->query("SELECT id, title, price, location FROM properties LIMIT 3");
            echo "<table>";
            echo "<tr><th>ID</th><th>Title</th><th>Price</th><th>Location</th></tr>";
            while ($row = $sample->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>
                    <td>" . $row['id'] . "</td>
                    <td>" . htmlspecialchars($row['title']) . "</td>
                    <td>" . $row['price'] . "</td>
                    <td>" . htmlspecialchars($row['location']) . "</td>
                </tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ Properties table is EMPTY! No data found.</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Properties table error: " . $e->getMessage() . "</div>";
    }
    
    // Check users table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $user_count = $stmt->fetchColumn();
        echo "<div class='info'>👤 Users: <strong>$user_count</strong> records found</div>";
        
        if ($user_count == 0) {
            echo "<div class='error'>❌ Users table is EMPTY! No users found.</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='error'>❌ Users table error: " . $e->getMessage() . "</div>";
    }
    
    echo "<hr>";
    echo "<div class='info'>💡 <strong>What to do next:</strong><br>
        - If tables are empty, you need to import data from your old database<br>
        - Or manually add sample data for testing<br>
        - Check login: user credentials must exist in users table</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
