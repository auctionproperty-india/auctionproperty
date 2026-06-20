<?php
require_once 'db.php';
try {
    // 1. Packages में referral_bonus कॉलम जोड़ें
    $pdo->exec("ALTER TABLE packages ADD COLUMN IF NOT EXISTS referral_bonus DECIMAL(10,2) DEFAULT 0");
    
    // 2. Referral Earnings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_referral_earnings (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        referred_user_id INT REFERENCES users(id) ON DELETE CASCADE,
        package_id INT REFERENCES packages(id),
        amount DECIMAL(10,2) NOT NULL,
        tds_deducted DECIMAL(10,2) DEFAULT 0,
        admin_charge_deducted DECIMAL(10,2) DEFAULT 0,
        net_amount DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending', -- pending, paid
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_at TIMESTAMP,
        bank_name VARCHAR(100),
        account_number VARCHAR(50),
        ifsc_code VARCHAR(20),
        remarks TEXT
    )");
    
    // 3. Existing users को Referral Code सेट करें (अगर नहीं है)
    $users = $pdo->query("SELECT id FROM users WHERE referral_code IS NULL OR referral_code = ''")->fetchAll();
    foreach($users as $u) {
        $code = strtoupper(substr(md5(uniqid()), 0, 8));
        $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$code, $u['id']]);
    }
    
    echo "✅ Referral System Database Updated! <br>";
    echo "✅ New columns: referral_bonus in packages, user_referral_earnings table. <br>";
    echo "✅ Default referral codes generated for existing users. <br>";
    echo "<a href='admin_packages.php' class='btn btn-primary'>Go to Packages (Set Referral Bonus)</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
