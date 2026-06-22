<?php
// ============================================================
// यह फाइल सिर्फ Test Database (auctionproperty) को Fix करेगी
// - Missing Columns जोड़ेगी
// - Tables को Update करेगी
// ============================================================

require_once 'db.php';

echo "<h3>🔧 Fixing Test Database...</h3>";

try {
    // ---- Users Table ----
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS referred_by INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS manual_referral_updated BOOLEAN DEFAULT FALSE");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions TEXT DEFAULT '{}'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_super_admin BOOLEAN DEFAULT FALSE");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_code VARCHAR(10)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_expiry TIMESTAMP");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
    echo "✅ Users table fixed.<br>";

    // ---- Properties Table ----
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'Flat'");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS google_location TEXT DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS bank_name VARCHAR(255) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS sqft DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS possession_type VARCHAR(50) DEFAULT 'Physical'");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS auction_date DATE");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS borrower_name VARCHAR(255) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS emd_amount DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS bid_increment DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS emd_deadline VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS auction_start_time VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS auction_end_time VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS locality VARCHAR(255) DEFAULT ''");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS reserve_price_per_sqft DECIMAL(10,2) DEFAULT 0");
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS contact_number VARCHAR(20) DEFAULT ''");
    echo "✅ Properties table fixed.<br>";

    // ---- Packages Table ----
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS discount_price DECIMAL(10,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS referral_bonus DECIMAL(10,2) DEFAULT 0");
    echo "✅ Packages table fixed.<br>";

    // ---- Subscriptions Table ----
    $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS utr VARCHAR(100) DEFAULT ''");
    $pdo->exec("ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS slip_path VARCHAR(255) DEFAULT ''");
    echo "✅ Subscriptions table fixed.<br>";

    echo "<hr>";
    echo "<h3 style='color:green;'>✅ Test Database is now fully fixed!</h3>";
    echo "<p>Now go to <a href='properties.php'>Admin Panel → Properties</a> and try adding a property.</p>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
