<?php
require_once 'db.php';
try {
    // 1. Packages Table (अगर पुरानी है तो उसे ठीक करें)
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id SERIAL PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        duration_months INT NOT NULL,
        price DECIMAL(10,2) NOT NULL
    )");
    
    // डिफॉल्ट पैकेज डालें (अगर खाली है)
    $count = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
    if($count == 0) {
        $pdo->exec("INSERT INTO packages (name, duration_months, price) VALUES 
            ('1 Month Plan', 1, 99.00),
            ('3 Months Plan', 3, 199.00),
            ('6 Months Plan', 6, 299.00),
            ('1 Year Plan', 12, 499.00)");
    }

    // 2. Subscriptions Table (User की Purchases के लिए)
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        package_id INT REFERENCES packages(id),
        property_id INT REFERENCES properties(id) ON DELETE CASCADE,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(20) DEFAULT 'bank', -- online or bank
        screenshot_path VARCHAR(255) DEFAULT '',
        status VARCHAR(20) DEFAULT 'pending', -- pending, active, expired
        start_date DATE,
        end_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "✅ Database अपडेट हो गया! <br>";
    echo "✅ Packages और Subscriptions टेबल बन गई हैं। <br>";
    echo "<a href='admin_packages.php' class='btn btn-primary mt-3'>Packages Manage करें</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
