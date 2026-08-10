<?php
// ============================================================
// ⚠️ FORCE MAP: 24 Users को aditya@gmail.com से जोड़ें (बिना Admin Check)
// ⚠️ इस फाइल को चलाने के तुरंत बाद DELETE कर दें!
// ============================================================

session_start();
require_once __DIR__ . '/db.php';

// --- सुरक्षा: पहले POST से कन्फर्मेशन लें ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<!DOCTYPE html>
    <html>
    <head><title>Confirm Map</title></head>
    <body style='font-family: Arial; max-width:600px; margin:50px auto; padding:20px; background:#f8fafc; border-radius:12px; border:1px solid #e2e8f0;'>
        <h2 style='color:#d97706;'>⚠️ क्या आप सुनिश्चित हैं?</h2>
        <p>यह स्क्रिप्ट <strong>सभी 24 Users</strong> (जिनकी ID ऊपर लिस्ट है) को <strong>aditya@gmail.com</strong> के अंडर मैप कर देगी।</p>
        <p><strong>ध्यान दें:</strong> यह एक irreversible (उलटा न होने वाला) कदम है – हालाँकि आप चाहें तो मैन्युअली बदल सकते हैं।</p>
        <form method='POST'>
            <button type='submit' style='background:#2563eb; color:white; padding:12px 30px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; font-size:16px;'>
                ✅ हाँ, मैप करो
            </button>
            <a href='users.php' style='margin-left:15px; color:#64748b;'>🚫 Cancel</a>
        </form>
    </body>
    </html>";
    exit;
}

// --- यदि POST है, तो मैपिंग करें ---
$new_user_email = 'aditya@gmail.com';

// 1. नए यूज़र की ID ढूँढें
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$new_user_email]);
$new_user = $stmt->fetch();

if (!$new_user) {
    die("❌ यूज़र '$new_user_email' नहीं मिला। कृपया पहले यह अकाउंट बनाएँ (User Role में)।");
}

$new_user_id = $new_user['id'];

// 2. 24 यूज़रों की ID – आपकी डायग्नोस्टिक स्क्रिप्ट से ली गई
$user_ids = [1, 2, 178, 9, 381, 384, 416, 4, 5, 10, 11, 12, 19, 22, 29, 13, 21, 6, 36, 52, 68, 34, 394, 293];

// 3. UPDATE चलाएँ
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
echo "<p>अब <strong>aditya@gmail.com</strong> लॉगिन करके 'My Team' में सभी 24 देख सकता है।</p>";
echo "<p><a href='users.php' style='color: #2563eb;'>🔙 Users लिस्ट पर जाएँ</a></p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ इस फाइल (<strong>force_map_users.php</strong>) को तुरंत DELETE कर दें!</p>";
echo "</div>";

// (वैकल्पिक) अपने आप डिलीट – चाहें तो अनकमेंट करें
// unlink(__FILE__);
?>
