<?php
require_once 'db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_code VARCHAR(10)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_expiry TIMESTAMP");
    echo "✅ OTP कॉलम जुड़ गए! <a href='change_password.php'>अब Change Password पर जाएँ</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
