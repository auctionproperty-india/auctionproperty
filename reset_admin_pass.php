<?php
// ============================================================
// ✅ यह फाइल TEST URL (auctionproperty-1) पर चलाएँ
// ✅ यह admin@admin.com का पासवर्ड "Admin@123" में Reset करेगी
// ============================================================

require_once 'db.php';

$email = 'admin@admin.com';
$new_password = 'Admin@123';
$hashed = password_hash($new_password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashed, $email]);

    if($stmt->rowCount() > 0) {
        echo "✅ Password for <strong>$email</strong> has been reset to <strong>$new_password</strong>.<br>";
        echo "Now try logging in at: <a href='login.php'>login.php</a>";
    } else {
        echo "❌ User with email '$email' not found!";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
