<?php
require_once 'db.php';
try {
    // 1. सबसे पहले कॉलम बनाएँ (अगर नहीं हैं तो)
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'Flat'");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS google_location TEXT DEFAULT ''");
    
    // 2. अब पुरानी Properties को अपडेट करें (ताकि वो खाली न रहें)
    $pdo->exec("UPDATE properties SET city = 'Unknown' WHERE city IS NULL OR city = ''");
    $pdo->exec("UPDATE properties SET type = 'Flat' WHERE type IS NULL OR type = ''");

    // 3. Packages Table (भविष्य के लिए)
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id SERIAL PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        duration_months INT NOT NULL,
        price DECIMAL(10,2) NOT NULL
    )");
    $count = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
    if($count == 0) {
        $pdo->exec("INSERT INTO packages (name, duration_months, price) VALUES 
            ('1 Month', 1, 99.00),
            ('3 Months', 3, 199.00),
            ('6 Months', 6, 299.00),
            ('1 Year', 12, 499.00)");
    }

    echo "✅ Database पूरी तरह सेट हो गया! <br>";
    echo "✅ अब `properties.php` में Error नहीं आएगा। <br>";
    echo "<a href='properties.php' class='btn btn-primary mt-3'>Admin Panel पर जाएँ</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
