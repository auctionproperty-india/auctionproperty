<?php
// ============================================================
// ✅ यह फाइल TEST URL (auctionproperty-1) पर चलाएँ
// ✅ Live से सारे Users को Test में Copy करेगी (Admin सहित)
// ✅ Truncate से पहले Backup नहीं है – ध्यान से चलाएँ
// ============================================================

// ---- Error Reporting On ----
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---- TEST Database (जहाँ Data डालना है) ----
$test_host = getenv('DB_HOST') ?: 'localhost';
$test_port = getenv('DB_PORT') ?: '5432';
$test_dbname = getenv('DB_NAME') ?: 'postgres';
$test_user = getenv('DB_USER') ?: 'postgres';
$test_password = getenv('DB_PASSWORD') ?: '';

try {
    $test_pdo = new PDO("pgsql:host=$test_host;port=$test_port;dbname=$test_dbname;sslmode=require", $test_user, $test_password);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Test Database Connected!<br>";
} catch (PDOException $e) {
    die("❌ Test DB Connection Failed: " . $e->getMessage());
}

// ---- LIVE Database (जहाँ से Data लेना है) ----
$live_host = 'dpg-d8ok6lflk1mc739ce1j0-a';
$live_port = '5432';
$live_dbname = 'auction_db_r1hx';
$live_user = 'admin';
$live_password = 'JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM';

try {
    $live_pdo = new PDO("pgsql:host=$live_host;port=$live_port;dbname=$live_dbname;sslmode=require", $live_user, $live_password);
    $live_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Live Database Connected!<br>";
} catch (PDOException $e) {
    die("❌ Live DB Connection Failed: " . $e->getMessage());
}

// ============================================================
// 1. Test की users Table Truncate करें
// ============================================================
echo "<h4>🗑️ Clearing Test users table...</h4>";
try {
    $test_pdo->exec("TRUNCATE TABLE users RESTART IDENTITY CASCADE");
    echo "✅ Test users table cleared.<br>";
} catch (Exception $e) {
    echo "❌ Truncate Error: " . $e->getMessage() . "<br>";
    die();
}

// ============================================================
// 2. Live से Users Fetch करें
// ============================================================
echo "<h4>👥 Fetching users from Live...</h4>";
try {
    $live_stmt = $live_pdo->query("SELECT * FROM users");
    $live_users = $live_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($live_users);
    echo "Found $total users in Live database.<br>";
} catch (Exception $e) {
    die("❌ Fetch Error: " . $e->getMessage());
}

// ============================================================
// 3. Insert into Test
// ============================================================
echo "<h4>📥 Inserting users into Test...</h4>";
$success_count = 0;
$error_count = 0;

foreach ($live_users as $row) {
    try {
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
        $success_count++;
    } catch (PDOException $e) {
        $error_count++;
        echo "❌ Failed to insert user ID {$row['id']}: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";
echo "<h3 style='color:green;'>✅ $success_count users inserted successfully!</h3>";
if ($error_count > 0) {
    echo "<p style='color:orange;'>⚠️ $error_count users failed. Check errors above.</p>";
}

// ============================================================
// 4. Verify Count
// ============================================================
$test_count = $test_pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
echo "<p>Total users in Test now: <strong>$test_count</strong></p>";

echo "<p>Now try login at: <a href='login.php'>login.php</a></p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ DELETE THIS FILE AFTER RUNNING!</p>";
?>
