<?php
require_once 'db.php';

$email = 'admin@admin.com';
$password = 'admin123';

$stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    echo "✅ User found!<br>";
    echo "ID: " . $user['id'] . "<br>";
    echo "Name: " . $user['name'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    
    if (password_verify($password, $user['password'])) {
        echo "✅✅✅ Password is CORRECT!<br>";
    } else {
        echo "❌ Password is WRONG!<br>";
    }
} else {
    echo "❌ User NOT found!<br>";
}
?>
