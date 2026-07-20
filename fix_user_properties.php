<?php
// ============================================================
// 🔧 Create user_properties Table
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Create User Properties Table</title>
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
    <h1>🔧 Create User Properties Table</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database Connected</div>";

    // ============================================================
    // 📋 CREATE user_properties TABLE
    // ============================================================
    
    $queries = [];
    
    // Main table
    $queries[] = "
        CREATE TABLE IF NOT EXISTS user_properties (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            property_id INTEGER NOT NULL,
            property_type VARCHAR(100),
            title VARCHAR(255) NOT NULL,
            description TEXT,
            price NUMERIC(12,2),
            location VARCHAR(255),
            bedrooms INTEGER DEFAULT 0,
            bathrooms INTEGER DEFAULT 0,
            area_sqft INTEGER DEFAULT 0,
            status VARCHAR(50) DEFAULT 'active',
            seller_name VARCHAR(255),
            seller_email VARCHAR(255),
            seller_phone VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            image_url TEXT,
            featured BOOLEAN DEFAULT FALSE,
            is_auction BOOLEAN DEFAULT FALSE,
            auction_date TIMESTAMP,
            auction_end_date TIMESTAMP,
            starting_bid NUMERIC(12,2),
            views INTEGER DEFAULT 0,
            likes INTEGER DEFAULT 0,
            city VARCHAR(100),
            state VARCHAR(100),
            zipcode VARCHAR(20),
            year_built INTEGER,
            property_status VARCHAR(50) DEFAULT 'active',
            agent_name VARCHAR(255),
            agent_phone VARCHAR(50),
            agent_email VARCHAR(255)
        )
    ";
    
    // Indexes for better performance
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_user_properties_user_id ON user_properties(user_id)";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_user_properties_property_id ON user_properties(property_id)";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_user_properties_status ON user_properties(status)";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_user_properties_type ON user_properties(property_type)";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_user_properties_auction ON user_properties(is_auction)";
    
    echo "<h2>📋 Executing Queries...</h2>";
    echo "<table>";
    echo "<tr><th>#</th><th>Operation</th><th>Status</th></tr>";
    
    $success_count = 0;
    foreach ($queries as $index => $query) {
        try {
            $pdo->exec($query);
            $status = "<span class='success'>✅ Success</span>";
            $success_count++;
        } catch (PDOException $e) {
            $status = "<span class='error'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
        }
        $short_query = substr($query, 0, 60) . (strlen($query) > 60 ? "..." : "");
        echo "<tr><td>" . ($index + 1) . "</td><td><code>" . htmlspecialchars($short_query) . "</code></td><td>$status</td></tr>";
    }
    echo "</table>";
    
    // ============================================================
    // 📊 SHOW ALL TABLES
    // ============================================================
    
    echo "<h2>📊 Existing Tables</h2>";
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<div class='error'>❌ No tables found!</div>";
    } else {
        echo "<ul>";
        foreach ($tables as $table) {
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $count_stmt->fetchColumn();
            echo "<li>✅ <strong>$table</strong> ($count rows)</li>";
        }
        echo "</ul>";
    }
    
    echo "<hr>";
    echo "<div class='success'>✅ Successfully created user_properties table with indexes!</div>";
    echo "<div class='info'>💡 Now try your website: <a href='/' target='_blank'>https://primepropertyindia.onrender.com</a></div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
