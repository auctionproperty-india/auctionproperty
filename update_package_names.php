<?php
require_once 'db.php';
try {
    // पैकेज के नाम अपडेट करें
    $pdo->exec("UPDATE packages SET name = 'Silver' WHERE duration_months = 1");
    $pdo->exec("UPDATE packages SET name = 'Gold' WHERE duration_months = 3");
    $pdo->exec("UPDATE packages SET name = 'Platinum' WHERE duration_months = 6");
    $pdo->exec("UPDATE packages SET name = 'Diamond' WHERE duration_months = 12");
    // अगर कोई पैकेज missing हो तो डालें
    $count = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
    if($count < 4) {
        $pdo->exec("INSERT INTO packages (name, duration_months, price) VALUES 
            ('Silver', 1, 99.00),
            ('Gold', 3, 199.00),
            ('Platinum', 6, 299.00),
            ('Diamond', 12, 499.00) ON CONFLICT (name) DO NOTHING");
    }
    echo "✅ Package names updated! Now Silver, Gold, Platinum, Diamond.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
