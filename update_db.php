<?php
require_once 'db.php';
try {
    // Properties में नए कॉलम जोड़ें
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'Flat'");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS google_location TEXT DEFAULT ''");
    
    // Packages Table (Payment के लिए)
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id SERIAL PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        duration_months INT NOT NULL,
        price DECIMAL(10,2) NOT NULL
    )");
    
    // डिफॉल्ट पैकेज (अगर खाली है)
    $count = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
    if($count == 0) {
        $pdo->exec("INSERT INTO packages (name, duration_months, price) VALUES 
            ('1 Month', 1, 99.00),
            ('3 Months', 3, 199.00),
            ('6 Months', 6, 299.00),
            ('1 Year', 12, 499.00)");
    }

    echo "✅ Database अपडेट हो गया! अब Admin से Type और City डाल सकते हैं।";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
