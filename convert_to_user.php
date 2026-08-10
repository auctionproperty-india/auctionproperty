<?php
// ============================================================
// 🔄 एक क्लिक में Sub-Admin को User में बदलें (PostgreSQL संगत)
// ============================================================

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// केवल Admin ही चला सकता है
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("⛔ Access denied. Only Admin can run this script.");
}

$target_email = 'shankarmudra995@gmail.com';

// पहले यूजर को ढूंढें
$stmt = $pdo->prepare("SELECT id, role, is_super_admin FROM users WHERE email = ?");
$stmt->execute([$target_email]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ User with email '$target_email' not found.");
}

// अगर पहले से ही User है तो कोई बदलाव न करें
if ($user['role'] === 'user' && $user['is_super_admin'] === false) {
    die("ℹ️ This user is already a normal User. No changes made.");
}

// ✅ UPDATE क्वेरी – PostgreSQL के लिए false (boolean) का उपयोग
$update = $pdo->prepare("UPDATE users SET role = 'user', is_super_admin = false WHERE email = ?");
$update->execute([$target_email]);

// सफलता संदेश
echo "<!DOCTYPE html>
<html>
<head><title>Role Updated</title></head>
<body style='font-family: Arial, sans-serif; max-width: 700px; margin: 50px auto; padding: 30px; background: #f0fdf4; border-radius: 16px; border: 2px solid #22c55e;'>
    <h2 style='color: #166534;'>✅ Role Updated Successfully!</h2>
    <p><strong>$target_email</strong> is now a <strong>User</strong> (not Sub-Admin).</p>
    <p>Now they can log in and see their downline in <strong>My Team</strong> section.</p>
    <p>
        <a href='admin_team.php?id=" . $user['id'] . "' style='color: #2563eb;'>👥 View their Team</a> | 
        <a href='users.php' style='color: #2563eb;'>🔙 Back to Users</a>
    </p>
    <p style='margin-top: 20px; font-size: 0.9em; color: #666;'>
        <strong>Note:</strong> इस स्क्रिप्ट को एक बार चलाने के बाद सुरक्षा के लिए इसे डिलीट कर दें।
    </p>
</body>
</html>";

// अब हम चाहें तो इस फाइल को सेल्फ-डिलीट भी कर सकते हैं (वैकल्पिक)
// unlink(__FILE__); // इस line को हटाने के लिए कमेंट कर दिया है, चाहें तो खोल दें
?>
