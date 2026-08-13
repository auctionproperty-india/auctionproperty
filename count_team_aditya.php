<?php
// ============================================================
// 📊 aditya@gmail.com की Team Count और Direct Joining Count
// ============================================================

// Error reporting (ताकि कोई भी error दिखे)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/db.php';

$target_email = 'aditya@gmail.com';

// 1. User की ID प्राप्त करें
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$target_email]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ User '$target_email' नहीं मिला।");
}

$user_id = $user['id'];
echo "<h2>👤 User: $target_email (ID: $user_id)</h2>";

// 2. कुल Downline (जिनका referred_by = user_id)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$stmt->execute([$user_id]);
$total_downline = $stmt->fetchColumn();

echo "<p><strong>📊 कुल Downline (referred_by = $user_id):</strong> $total_downline</p>";

// 3. Direct joining count (जिनका कोई referrer नहीं या NULL है – मान लिया कि Direct वे हैं जिनका referred_by NULL है)
// लेकिन अगर आपके सिस्टम में Direct का अलग से कोई फ्लैग है तो बताएँ, यहाँ हम मान रहे हैं कि जिनका referred_by IS NULL वे Direct हैं।
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by IS NULL");
$stmt->execute();
$direct_count = $stmt->fetchColumn();

echo "<p><strong>🌟 Direct Joining (referred_by IS NULL):</strong> $direct_count</p>";

// 4. (वैकल्पिक) aditya के Direct referrals (जिन्होंने सीधे aditya को refer किया) – अगर आपके system में ऐसा है तो
// अक्सर Direct वे होते हैं जिनका referred_by = user_id और वे level 1 पर हैं – तो वही तो ऊपर count कर लिए, इसलिए अलग से कोई ज़रूरत नहीं।

// 5. अगर आपको उन सभी 24 की लिस्ट चाहिए (जो पहले मैप हुए थे) तो नीचे दिखा सकते हैं:
$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE referred_by = ? LIMIT 50");
$stmt->execute([$user_id]);
$users = $stmt->fetchAll();

if ($users) {
    echo "<h3>📋 इन Users की लिस्ट (जो aditya के अंडर हैं):</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>{$u['id']}</td><td>{$u['name']}</td><td>{$u['email']}</td><td>{$u['role']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ aditya के अंडर कोई User नहीं है (जबकि हमें 24 होने चाहिए – शायद referred_by अभी अपडेट नहीं हुआ?)</p>";
}

echo "<p><a href='users.php'>🔙 Back to Users</a></p>";
?>
