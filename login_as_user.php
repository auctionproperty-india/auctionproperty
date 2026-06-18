<?php
require_once 'db.php';

// सिर्फ Admin ही use कर सकता है
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

$user_id = $_GET['id'] ?? 0;
if($user_id) {
    // User Data Fetch करें
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if($user) {
        // Original Admin ID Store करें (ताकि बाद में वापस आ सकें)
        $_SESSION['original_admin_id'] = $_SESSION['user_id'];
        $_SESSION['impersonating'] = $user_id;

        // User का Session Data Set करें
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        
        header("Location: dashboard.php");
        exit;
    }
}

header("Location: dashboard.php");
exit;
?>
