<?php
require_once 'db.php';
try {
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS bank_name VARCHAR(255) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS sqft DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS possession_type VARCHAR(50) DEFAULT 'Physical'");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS auction_date DATE");
    echo "✅ Auction Columns added successfully!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
