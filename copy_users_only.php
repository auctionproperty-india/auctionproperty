<?php
// ============================================================
// ✅ यह फाइल TEST URL (auctionproperty-1) पर चलाएँ
// ✅ यह Live Database (Render) से सिर्फ `users` Table को Test (Neon.tech) पर Copy करेगी
// ⚠️ TEST DATABASE की `users` Table Overwrite हो जाएगी – बाकी Tables untouched
// ============================================================

// ---- TEST Database (Neon.tech – जहाँ Data डालना है) ----
$test_host = getenv('DB_HOST') ?: 'localhost';
$test_port = getenv('DB_PORT') ?: '5432';
$test_dbname = getenv('DB_NAME') ?: 'postgres';
$test_user = getenv('DB_USER') ?: 'postgres';
$test_password = getenv('DB_PASSWORD') ?: '';

try {
    $test_pdo = new PDO("pgsql:host=$test_host;port=$test_port;dbname=$test_dbname;sslmode=require", $test_user, $test_password);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Test Database (Neon.tech) Connected!<br>";
} catch (PDOException $e) {
    die("❌ Test DB Connection Failed: " . $e->getMessage());
}

// ---- LIVE Database (Render – जहाँ से Data लेना है) ----
$live_host = 'dpg-d8ok6lflk1mc739ce1j0-a';
$live_port = '5432';
$live_dbname = 'auction_db_r1hx';
$live_user = 'admin';
$live_password = 'JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM';

try {
    $live_pdo = new PDO("pgsql:host=$live_host;port=$live_port;dbname=$live_dbname;sslmode=require", $live_user, $live_password);
    $live_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Live Database (Render) Connected!<br>";
} catch (PDOException $e) {
    die("❌ Live DB Connection Failed: " . $e->getMessage());
}

// ============================================================
// 🗑️ 1. Test Database की `users` Table खाली करें (बाकी Tables untouched)
// ============================================================
echo "<h4>🗑️ Clearing Test users table...</h4>";
try {
    $test_pdo->exec("TRUNCATE TABLE users RESTART IDENTITY CASCADE");
    echo "✅ Test users table cleared!<br>";
} catch (Exception $e) {
    echo "❌ Clear Error: " . $e->getMessage() . "<br>";
}

// ============================================================
// 👥 2. Users Copy (Only from Live to Test)
// ============================================================
echo "<h4>👥 Copying Users...</h4>";
try {
    $live_data = $live_pdo->query("SELECT * FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($live_data as $row) {
        $stmt = $test_pdo->prepare("INSERT INTO users (
            id, name, email, password, phone, city, referral_code, 
            referred_by, role, status, permissions, is_super_admin, 
            otp_code, otp_expiry, wallet_balance, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $row['id'], $row['name'], $row['email'], $row['password'],
            $row['phone'] ?? '', $row['city'] ?? '', $row['referral_code'] ?? '',
            $row['referred_by'] ?? null, $row['role'] ?? 'user', 
            $row['status'] ?? 'active', $row['permissions'] ?? '{}',
            $row['is_super_admin'] ?? false,
            $row['otp_code'] ?? null, $row['otp_expiry'] ?? null,
            $row['wallet_balance'] ?? 0, $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $count++;
    }
    echo "✅ $count Users copied successfully!<br>";
} catch (Exception $e) {
    echo "❌ Users Copy Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3 style='color:green;'>✅ USERS DATA COPIED FROM LIVE TO TEST SUCCESSFULLY!</h3>";
echo "<p>Now check your Test URL: <a href='https://auctionproperty-1.onrender.com' target='_blank'>auctionproperty-1.onrender.com</a></p>";
echo "<p><strong>Note:</strong> Only the <code>users</code> table has been replaced. All other data (Properties, Subscriptions, Wallet, etc.) remains unchanged.</p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ IMPORTANT: Delete this file immediately after running!</p>";
?>
