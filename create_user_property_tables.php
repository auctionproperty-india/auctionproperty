<?php
require_once 'db.php';

echo "<h2>🔧 Creating User Property Tables...</h2>";

try {
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS max_properties INT DEFAULT 1");
    $pdo->exec("UPDATE packages SET max_properties = 1 WHERE name ILIKE '%Silver%'");
    $pdo->exec("UPDATE packages SET max_properties = 3 WHERE name ILIKE '%Gold%'");
    $pdo->exec("UPDATE packages SET max_properties = 5 WHERE name ILIKE '%Platinum%'");
    $pdo->exec("UPDATE packages SET max_properties = 10 WHERE name ILIKE '%Diamond%'");
    echo "✅ Packages updated with max_properties.<br>";

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_properties (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(15,2) NOT NULL,
        city VARCHAR(100),
        state VARCHAR(100),
        type VARCHAR(50),
        image_url TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        admin_remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ user_properties table created.<br>";

    echo "<hr><h3 style='color:green;'>✅ All done!</h3>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file now.</p>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
