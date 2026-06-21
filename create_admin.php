<?php
// ============================================================
// ✅ यह फाइल TEST URL (auctionproperty-1) पर चलाएँ
// ✅ यह admin@admin.com बना देगी (अगर नहीं है) या पासवर्ड Reset कर देगी
// ============================================================

require_once 'db.php';

$email = 'admin@admin.com';
$password = 'Admin@123';
$name = 'Admin User';
$phone = '9999999999';
$ref_code = strtoupper(substr(md5(uniqid()), 0, 8));

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if($existing) {
        // Update password
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_super_admin = TRUE, role = 'admin' WHERE email = ?");
        $stmt->execute([$hashed, $email]);
        echo "✅ Admin user <strong>$email</strong> updated with password <strong>$password</strong> and Super Admin rights!<br>";
    } else {
        // Create new admin
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO users (name, email, password, phone, referral_code, role, is_super_admin, status) 
                VALUES (?, ?, ?, ?, ?, 'admin', TRUE, 'active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $email, $hashed, $phone, $ref_code]);
        echo "✅ Admin user <strong>$email</strong> created with password <strong>$password</strong> and Super Admin rights!<br>";
    }

    echo "Now try logging in at: <a href='login.php'>login.php</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
