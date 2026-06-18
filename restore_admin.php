<?php
require_once 'db.php';

// चेक करें कि क्या impersonation चल रहा है
if(isset($_SESSION['impersonating']) && isset($_SESSION['original_admin_id'])) {
    $admin_id = $_SESSION['original_admin_id'];
    
    // Admin Data Reload करें
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    if($admin) {
        // Session Restore करें
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['user_name'] = $admin['name'];
        $_SESSION['role'] = $admin['role'];
        
        // Impersonation Variables हटाएँ
        unset($_SESSION['impersonating']);
        unset($_SESSION['original_admin_id']);
        
        header("Location: dashboard.php");
        exit;
    }
}

// अगर कोई impersonation नहीं है, तो Dashboard पर जाएँ
header("Location: dashboard.php");
exit;
?>
