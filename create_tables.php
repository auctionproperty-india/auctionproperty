<?php
// ============================================================
// 🔧 Database Tables Creator - Render par run karein
// ============================================================

// Database connection (environment variables se)
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Tables Creator</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #45a049; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Database Tables Creator</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database Connected Successfully!</div>";
    echo "<div class='info'>📊 Database: $dbname</div>";
    
    // ============================================================
    // 📋 CREATE TABLES
    // ============================================================
    
    $queries = [];
    
    // 1. Properties Table
    $queries[] = "
        CREATE TABLE IF NOT EXISTS properties (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            price NUMERIC(12,2),
            location VARCHAR(255),
            property_type VARCHAR(100),
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
            featured BOOLEAN DEFAULT FALSE
        )
    ";
    
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_properties_status ON properties(status)";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_properties_type ON properties(property_type)";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_properties_price ON properties(price)";
    
    // 2. Users Table (agar hai toh)
    $queries[] = "
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            role VARCHAR(50) DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)";
    
    // 3. Contacts/Enquiries Table
    $queries[] = "
        CREATE TABLE IF NOT EXISTS enquiries (
            id SERIAL PRIMARY KEY,
            property_id INTEGER REFERENCES properties(id) ON DELETE CASCADE,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50),
            message TEXT,
            status VARCHAR(50) DEFAULT 'new',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_enquiries_property_id ON enquiries(property_id)";
    
    // 4. Categories Table (agar hai toh)
    $queries[] = "
        CREATE TABLE IF NOT EXISTS categories (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    // 5. Settings Table
    $queries[] = "
        CREATE TABLE IF NOT EXISTS settings (
            id SERIAL PRIMARY KEY,
            key VARCHAR(100) UNIQUE NOT NULL,
            value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $queries[] = "CREATE INDEX IF NOT EXISTS idx_settings_key ON settings(key)";
    
    // ============================================================
    // 🚀 EXECUTE QUERIES
    // ============================================================
    
    echo "<h2>📋 Creating Tables...</h2>";
    echo "<table>";
    echo "<tr><th>#</th><th>Query</th><th>Status</th></tr>";
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($queries as $index => $query) {
        try {
            $pdo->exec($query);
            $status = "<span class='success'>✅ Success</span>";
            $success_count++;
        } catch (PDOException $e) {
            $status = "<span class='error'>❌ " . htmlspecialchars($e->getMessage()) . "</span>";
            $error_count++;
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
            // Count rows in each table
            $count_stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $count_stmt->fetchColumn();
            echo "<li>✅ <strong>$table</strong> ($count rows)</li>";
        }
        echo "</ul>";
    }
    
    echo "<hr>";
    echo "<div class='success'>✅ Total: $success_count successful, $error_count failed</div>";
    echo "<div class='info'>💡 Now try opening your website: <a href='/' target='_blank'>https://primepropertyindia.onrender.com</a></div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database Connection Failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

</div>
</body>
</html>
