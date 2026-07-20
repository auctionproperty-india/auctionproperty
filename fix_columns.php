<?php
// ============================================================
// 🔧 Add Missing Columns to Properties Table
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Database Columns</title>
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
    <h1>🔧 Fix Missing Columns</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database Connected</div>";

    // ============================================================
    // 📋 COLUMNS TO ADD (Jitne bhi missing hain)
    // ============================================================
    
    $columns = [
        'auction_date' => 'TIMESTAMP',
        'auction_end_date' => 'TIMESTAMP',
        'starting_bid' => 'NUMERIC(12,2)',
        'reserve_price' => 'NUMERIC(12,2)',
        'is_auction' => 'BOOLEAN DEFAULT FALSE',
        'auction_status' => 'VARCHAR(50) DEFAULT \'upcoming\'',
        'views' => 'INTEGER DEFAULT 0',
        'likes' => 'INTEGER DEFAULT 0',
        'city' => 'VARCHAR(100)',
        'state' => 'VARCHAR(100)',
        'zipcode' => 'VARCHAR(20)',
        'year_built' => 'INTEGER',
        'lot_size' => 'VARCHAR(50)',
        'property_status' => 'VARCHAR(50) DEFAULT \'active\'',
        'featured_until' => 'TIMESTAMP',
        'video_url' => 'TEXT',
        'virtual_tour_url' => 'TEXT',
        'agent_name' => 'VARCHAR(255)',
        'agent_phone' => 'VARCHAR(50)',
        'agent_email' => 'VARCHAR(255)'
    ];

    echo "<h2>📋 Adding Missing Columns...</h2>";
    echo "<table>";
    echo "<tr><th>#</th><th>Column</th><th>Type</th><th>Status</th></tr>";
    
    $count = 0;
    $success_count = 0;
    
    foreach ($columns as $col_name => $col_type) {
        $count++;
        try {
            // Check if column exists
            $check_stmt = $pdo->prepare("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = 'properties' AND column_name = ?
            ");
            $check_stmt->execute([$col_name]);
            $exists = $check_stmt->fetchColumn();
            
            if ($exists) {
                $status = "<span class='info'>⏭️ Already exists</span>";
            } else {
                // Add column
                $alter_query = "ALTER TABLE properties ADD COLUMN IF NOT EXISTS $col_name $col_type";
                $pdo->exec($alter_query);
                $status = "<span class='success'>✅ Added</span>";
                $success_count++;
            }
        } catch (PDOException $e) {
            $status = "<span class='error'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
        }
        echo "<tr><td>$count</td><td><strong>$col_name</strong></td><td><code>$col_type</code></td><td>$status</td></tr>";
    }
    
    echo "</table>";
    
    // ============================================================
    // 📊 SHOW ALL COLUMNS
    // ============================================================
    
    echo "<h2>📊 Properties Table - All Columns</h2>";
    $stmt = $pdo->query("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'properties' 
        ORDER BY ordinal_position
    ");
    $columns_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>#</th><th>Column Name</th><th>Data Type</th></tr>";
    foreach ($columns_list as $index => $col) {
        echo "<tr><td>" . ($index + 1) . "</td><td><strong>" . htmlspecialchars($col['column_name']) . "</strong></td><td>" . htmlspecialchars($col['data_type']) . "</td></tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<div class='success'>✅ Added $success_count new columns</div>";
    echo "<div class='info'>💡 Now try your website: <a href='/' target='_blank'>https://primepropertyindia.onrender.com</a></div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
