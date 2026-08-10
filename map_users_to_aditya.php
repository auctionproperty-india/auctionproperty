<?php
// ============================================================
// 🔄 सभी 24 यूज़रों को aditya@gmail.com से मैप करें
// ============================================================

session_start();
require_once __DIR__ . '/db.php';

// (वैकल्पिक) Admin चेक – अगर चाहें तो इस हिस्से को कमेंट कर दें
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("⛔ केवल Admin ही इस स्क्रिप्ट को चला सकता है।");
}

$new_user_email = 'aditya@gmail.com';

// 1. नए यूज़र की ID ढूँढें
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$new_user_email]);
$new_user = $stmt->fetch();

if (!$new_user) {
    die("❌ यूज़र '$new_user_email' नहीं मिला। कृपया पहले यह अकाउंट बनाएँ।");
}

$new_user_id = $new_user['id'];
echo "<h2>✅ नए User की ID: $new_user_id</h2>";

// 2. उन 24 यूज़रों की ID – जो पहले shankarmudra995 से जुड़े थे
$user_ids = [1, 2, 178, 9, 381, 384, 416, 4, 5, 10, 11, 12, 19, 22, 29, 13, 21, 6, 36, 52, 68, 34, 394, 293];

// 3. उन सभी 24 यूज़रों को aditya से जोड़ें (referred_by = new_user_id)
$placeholders = implode(',', array_fill(0, count($user_ids), '?'));
$sql = "UPDATE users SET referred_by = ? WHERE id IN ($placeholders)";

$params = array_merge([$new_user_id], $user_ids);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$affected = $stmt->rowCount();

// 4. परिणाम दिखाएँ
echo "<div style='font-family: Arial; max-width: 700px; margin: 50px auto; padding: 30px; background: #f0fdf4; border-radius: 16px; border: 2px solid #22c55e;'>";
echo "<h2 style='color: #166534;'>✅ सभी 24 Users को aditya@gmail.com से मैप कर दिया गया!</h2>";
echo "<p><strong>$affected</strong> यूज़र अब <strong>aditya@gmail.com</strong> (ID: $new_user_id) के अंडर हैं।</p>";
echo "<p>अब जब <strong>aditya@gmail.com</strong> लॉगिन करेगा, तो उसे 'My Team' में ये सभी 24 यूज़र दिखेंगे।</p>";
echo "<p><a href='users.php' style='color: #2563eb;'>🔙 Users लिस्ट पर जाएँ</a></p>";
echo "<p style='margin-top: 20px; font-size: 0.9em; color: #666;'><strong>⚠️ सुरक्षा:</strong> इस फाइल को अभी डिलीट कर दें।</p>";
echo "</div>";

// (वैकल्पिक) अपने आप डिलीट – चाहें तो नीचे की लाइन अनकमेंट करें
// unlink(__FILE__);
?>
