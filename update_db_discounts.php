<?php
require_once 'db.php';
try {
    // Packages table में discount_price जोड़ें
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS discount_price DECIMAL(10,2) DEFAULT NULL");
    
    // Subscriptions table में UTR और slip_path जोड़ें
    $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS utr VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS slip_path VARCHAR(255) DEFAULT ''");
    
    echo "✅ Database updated successfully! <br>";
    echo "New columns added: discount_price (packages), utr, slip_path (subscriptions). <br>";
    echo "<a href='admin_packages.php' class='btn btn-primary mt-3'>Go to Manage Packages</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
