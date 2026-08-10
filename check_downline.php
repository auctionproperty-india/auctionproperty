<?php
// ============================================================
// 🔍 My Team की समस्या को डायग्नोस करें
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/db.php';

$target_email = 'shankarmudra995@gmail.com';

// 1. इस यूजर का ID पता करें
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$target_email]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ यूजर नहीं मिला");
}

$user_id = $user['id'];
echo "<h2>🔍 Target User ID: $user_id</h2>";

// 2. डेटाबेस में 'referrer_id' या 'sponsor_id' कॉलम ढूंढें
$stmt = $pdo->query("SELECT column_name FROM information_schema.columns 
                     WHERE table_name='users' 
                     AND (column_name LIKE '%refer%' OR column_name LIKE '%sponsor%' OR column_name LIKE '%parent%')");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($cols)) {
    echo "<p style='color:red;'>⚠️ 'users' टेबल में 'referrer_id' या 'sponsor_id' नाम का कोई कॉलम नहीं मिला। कृपया अपनी टेबल स्ट्रक्चर देखें।</p>";
    // फिर भी try करते हैं common नामों से
    $possible_cols = ['referrer_id', 'sponsor_id', 'parent_id', 'ref_id'];
    $found_col = null;
    foreach ($possible_cols as $col) {
        try {
            $stmt = $pdo->query("SELECT $col FROM users LIMIT 1");
            $found_col = $col;
            break;
        } catch (Exception $e) {}
    }
    if ($found_col) {
        echo "<p>✅ मुझे कॉलम मिला: <strong>$found_col</strong></p>";
        $col_name = $found_col;
    } else {
        die("❌ कोई भी रेफरल कॉलम नहीं मिला।");
    }
} else {
    $col_name = $cols[0];
    echo "<p>✅ कॉलम मिला: <strong>$col_name</strong></p>";
}

// 3. अब देखते हैं कि इस User ID से कितने यूजर्स जुड़े हैं
$sql = "SELECT id, name, email, role FROM users WHERE $col_name = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$downlines = $stmt->fetchAll();

echo "<h3>📊 इस User की Downline में कुल यूजर्स: " . count($downlines) . "</h3>";

if (count($downlines) == 0) {
    echo "<div style='background:#fef3c7; padding:15px; border-left:5px solid #f59e0b;'>
            <strong>⚠️ समस्या:</strong> इस User ID ($user_id) से कोई भी यूजर लिंक नहीं है।<br>
            <strong>मतलब:</strong> '24 logo vala script' ने शायद गलत ID डाल दी थी।<br>
            अब हमें उन 24 यूजर्स को <strong>मैन्युअली इस User ID से जोड़ना</strong> होगा।
          </div>";
    
    // पता करें कि वे 24 यूजर्स कौन हैं (मान लिया कि वे user_role वाले हैं और पहले से किसी और के पास नहीं हैं)
    echo "<h3>🔧 सुझावित फिक्स:</h3>
          <p>यदि आपको उन 24 यूजर्स की ID / Email की लिस्ट पता है, तो नीचे दी गई SQL चलाएँ:</p>
          <pre style='background:#1e293b; color:#e2e8f0; padding:15px; border-radius:8px;'>
UPDATE users SET $col_name = $user_id WHERE id IN (24 यूजर्स की ID कॉमा से लिखें);
          </pre>
          <p>या, अगर आपको उनकी ID नहीं पता, तो मुझे बताएँ कि उन्हें कैसे पहचाना जाए (जैसे कि सभी नए रजिस्टर्ड यूजर्स), मैं उन्हें ढूंढने की स्क्रिप्ट दे दूँगा।</p>";
} else {
    echo "<div style='background:#dcfce7; padding:15px; border-left:5px solid #22c55e;'>
            <strong>✅ डेटा सही है!</strong> इन $col_name = $user_id वाले यूजर्स की लिस्ट:<br>
            <ul>";
    foreach ($downlines as $d) {
        echo "<li>ID: {$d['id']}, Name: {$d['name']}, Email: {$d['email']}, Role: {$d['role']}</li>";
    }
    echo "</ul>
          <p><strong>समस्या:</strong> अगर डेटा होने के बावजूद 'My Team' पेज पर स्पिनर घूम रहा है, तो <strong>my_team.php</strong> में कोई PHP Error आ रहा है।<br>
          कृपया <strong>F12 → Console</strong> टैब खोलें और वहाँ कोई रेड एरर दिख रहा है तो मुझे बताएँ।<br>
          साथ ही, अपने सर्वर की <strong>error_log</strong> भी चेक करें।</p>
          </div>";
}

// 4. (वैकल्पिक) अगर my_team.php में कोई DB एरर है तो उसे यहाँ टेस्ट करें
echo "<h3>🧪 my_team.php की क्वेरी टेस्ट करें:</h3>";
// मान लिया कि my_team.php में कुछ ऐसी क्वेरी है
try {
    // सामान्यतः MLM सिस्टम में binary या multi-level होता है, लेकिन बेसिक क्वेरी यही होती है
    $test_sql = "SELECT COUNT(*) FROM users WHERE $col_name = ?";
    $stmt = $pdo->prepare($test_sql);
    $stmt->execute([$user_id]);
    $count = $stmt->fetchColumn();
    echo "✅ सीधी क्वेरी चल रही है, Count: $count";
} catch (Exception $e) {
    echo "❌ क्वेरी में एरर: " . $e->getMessage();
}

echo "<p style='margin-top:30px;'><strong>⚠️ सुरक्षा:</strong> इस फाइल को चलाने के बाद <strong>डिलीट</strong> कर दें।</p>";
?>
