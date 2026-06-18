<?php
require_once 'db.php';

$email = 'admin@admin.com';
$password = 'Admin@123'; // आप चाहें तो इसे बदल सकते हैं
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$ref_code = strtoupper(substr(md5(uniqid()), 0, 8));

try {
    // चेक करें कि यह ईमेल पहले से है या नहीं
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if($user) {
        // अगर है तो सिर्फ Admin बनाएँ
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = ?")->execute([$email]);
        echo "✅ 'admin@admin.com' को ADMIN बना दिया गया है। <br>";
    } else {
        // नहीं है तो नया अकाउंट बनाएँ
        $sql = "INSERT INTO users (name, email, password, phone, referral_code, role, status) VALUES (?, ?, ?, ?, ?, 'admin', 'active')";
        $pdo->prepare($sql)->execute(['Admin User', $email, $hashed_password, '9999999999', $ref_code]);
        echo "✅ 'admin@admin.com' का नया ADMIN अकाउंट बना दिया गया है। <br>";
    }

    echo "📧 Email: <strong>admin@admin.com</strong> <br>";
    echo "🔑 Password: <strong>Admin@123</strong> <br><br>";
    echo "<a href='login.php' class='btn btn-primary'>Login करें</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
