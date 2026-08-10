<?php
// ============================================================
// ⚠️  एक बार चलाने वाली स्क्रिप्ट – बिना किसी रोल चेक के (सावधानी से उपयोग करें)
// ============================================================

session_start();
require_once __DIR__ . '/db.php';

$target_email = 'shankarmudra995@gmail.com';

// पहले यूजर ढूंढें
$stmt = $pdo->prepare("SELECT id, role, is_super_admin FROM users WHERE email = ?");
$stmt->execute([$target_email]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ यूजर नहीं मिला: $target_email");
}

// PostgreSQL के लिए boolean false
$update = $pdo->prepare("UPDATE users SET role = 'user', is_super_admin = false WHERE email = ?");
$update->execute([$target_email]);

echo "<h2>✅ हो गया!</h2>";
echo "<p><strong>$target_email</strong> अब User बन गया है।</p>";
echo "<p>अब वह My Team में अपनी Downline देख सकता है।</p>";
echo "<p style='color:red;'><strong>⚠️ इस फाइल को अभी डिलीट कर दें – सुरक्षा के लिए!</strong></p>";

// (वैकल्पिक) अपने आप डिलीट करने के लिए नीचे की लाइन को अनकमेंट करें
// unlink(__FILE__);
?>
