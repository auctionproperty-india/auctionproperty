<?php
// ============================================================
// 🔧 Migration: Add referral_incentive to packages & referral_transactions table
// ============================================================

require_once __DIR__ . '/db.php';

try {
    // ----- Add referral_incentive column to packages -----
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS referral_incentive VARCHAR(50) DEFAULT '0'");
    echo "✅ referral_incentive column added to packages.<br>";

    // ----- Create referral_transactions table -----
    $pdo->exec("CREATE TABLE IF NOT EXISTS referral_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referrer_id INT NOT NULL,
        referred_user_id INT NOT NULL,
        package_id INT NOT NULL,
        subscription_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending','credited') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        credited_at TIMESTAMP NULL,
        INDEX idx_referrer (referrer_id),
        INDEX idx_subscription (subscription_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✅ referral_transactions table created.<br>";

    echo "<p style='color:green;'>Migration successful! You can now use the referral system.</p>";
    echo "<p><a href='admin_packages.php'>Go to Admin Packages</a></p>";

} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}
?>
