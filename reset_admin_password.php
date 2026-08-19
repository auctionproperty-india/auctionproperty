<?php
require_once 'db.php';

$new_password = '121212';
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@admin.com'");
$stmt->execute([$hashed]);

if ($stmt->rowCount() > 0) {
    echo "✅ Password updated to: <strong>121212</strong><br>";
    echo "<a href='/login.php'>Go to Login</a>";
} else {
    echo "❌ Update failed.";
}
?>
