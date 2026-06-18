<?php
require_once 'db.php';
try {
    // New Columns for Property
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS borrower_name VARCHAR(255) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS emd_amount DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS bid_increment DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS emd_deadline VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS auction_start_time VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS auction_end_time VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS locality VARCHAR(255) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS reserve_price_per_sqft DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS contact_number VARCHAR(20) DEFAULT '9238215516'");

    // Settings Table for Default Contact
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT NOT NULL
    )");
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('default_contact', '9238215516') ON CONFLICT (setting_key) DO NOTHING");

    echo "✅ सभी Auction Columns और Settings Table जुड़ गए! <br>";
    echo "✅ Default Contact: 9238215516 <br>";
    echo "<a href='properties.php' class='btn btn-primary mt-3'>Admin Panel जाएँ</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
