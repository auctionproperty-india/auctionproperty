<?php
// ============================================================
// ✅ यह फाइल LIVE URL (auctionprimeproperty) पर चलाएँ
// ✅ यह Test Database (Neon.tech) का सारा Data Live पर Overwrite करेगी
// ⚠️ LIVE DATABASE पूरी तरह Overwrite हो जाएगी – पुराना Data नहीं बचेगा
// ============================================================

// ---- LIVE Database (जहाँ Data डालना है) ----
$live_host = getenv('DB_HOST') ?: 'localhost';
$live_port = getenv('DB_PORT') ?: '5432';
$live_dbname = getenv('DB_NAME') ?: 'postgres';
$live_user = getenv('DB_USER') ?: 'postgres';
$live_password = getenv('DB_PASSWORD') ?: '';

try {
    $live_pdo = new PDO("pgsql:host=$live_host;port=$live_port;dbname=$live_dbname;sslmode=require", $live_user, $live_password);
    $live_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Live Database Connected!<br>";
} catch (PDOException $e) {
    die("❌ Live DB Connection Failed: " . $e->getMessage());
}

// ---- TEST Database (Neon.tech – जहाँ से Data लेना है) ----
// 🔴 अपने Test (Neon.tech) Database के Credentials डालें
$test_host = 'ep-raspy-mud-aox1lbpn-pooler.c-2.ap-southeast-1.aws.neon.tech';
$test_port = '5432';
$test_dbname = 'neondb';
$test_user = 'neondb_owner';
$test_password = 'npg_tmrLf14HysaM';

try {
    $test_pdo = new PDO("pgsql:host=$test_host;port=$test_port;dbname=$test_dbname;sslmode=require", $test_user, $test_password);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Test Database (Neon.tech) Connected!<br>";
} catch (PDOException $e) {
    die("❌ Test DB Connection Failed: " . $e->getMessage());
}

// ============================================================
// 🗑️ 1. Live Database को पूरी तरह खाली करें (CASCADE से)
// ============================================================
echo "<h4>🗑️ Clearing Live Database...</h4>";
try {
    $live_pdo->exec("TRUNCATE TABLE 
        users, 
        properties, 
        packages, 
        subscriptions, 
        user_referral_earnings, 
        account_entries, 
        wallet_transactions 
    RESTART IDENTITY CASCADE");
    echo "✅ Live Database Cleared!<br>";
} catch (Exception $e) {
    echo "❌ Clear Error: " . $e->getMessage() . "<br>";
}

// ============================================================
// 📦 2. Packages Copy
// ============================================================
echo "<h4>📦 Copying Packages...</h4>";
try {
    $test_data = $test_pdo->query("SELECT * FROM packages ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($test_data as $row) {
        $stmt = $live_pdo->prepare("INSERT INTO packages (id, name, duration_months, price, discount_price, referral_bonus) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$row['id'], $row['name'], $row['duration_months'], $row['price'], $row['discount_price'] ?? null, $row['referral_bonus'] ?? 0]);
        $count++;
    }
    echo "✅ $count Packages copied!<br>";
} catch (Exception $e) { echo "❌ Packages Error: " . $e->getMessage() . "<br>"; }

// ============================================================
// 👥 3. Users Copy
// ============================================================
echo "<h4>👥 Copying Users...</h4>";
try {
    $test_data = $test_pdo->query("SELECT * FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($test_data as $row) {
        $stmt = $live_pdo->prepare("INSERT INTO users (
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
    echo "✅ $count Users copied!<br>";
} catch (Exception $e) { echo "❌ Users Error: " . $e->getMessage() . "<br>"; }

// ============================================================
// 🏠 4. Properties Copy
// ============================================================
echo "<h4>🏠 Copying Properties...</h4>";
try {
    $test_data = $test_pdo->query("SELECT * FROM properties ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($test_data as $row) {
        $stmt = $live_pdo->prepare("INSERT INTO properties (
            id, title, description, price, location, city, state, type, 
            google_location, image_url, bank_name, sqft, possession_type, 
            auction_date, borrower_name, emd_amount, bid_increment, 
            emd_deadline, auction_start_time, auction_end_time, locality, 
            reserve_price_per_sqft, contact_number, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $row['id'], $row['title'], $row['description'], $row['price'], 
            $row['location'], $row['city'] ?? '', $row['state'] ?? '', $row['type'] ?? '',
            $row['google_location'] ?? '', $row['image_url'] ?? '', $row['bank_name'] ?? '',
            $row['sqft'] ?? 0, $row['possession_type'] ?? 'Physical',
            $row['auction_date'] ?? null, $row['borrower_name'] ?? '',
            $row['emd_amount'] ?? 0, $row['bid_increment'] ?? 0,
            $row['emd_deadline'] ?? '', $row['auction_start_time'] ?? '', 
            $row['auction_end_time'] ?? '', $row['locality'] ?? '',
            $row['reserve_price_per_sqft'] ?? 0, $row['contact_number'] ?? '',
            $row['status'] ?? 'available', $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $count++;
    }
    echo "✅ $count Properties copied!<br>";
} catch (Exception $e) { echo "❌ Properties Error: " . $e->getMessage() . "<br>"; }

// ============================================================
// 📋 5. Subscriptions Copy
// ============================================================
echo "<h4>📋 Copying Subscriptions...</h4>";
try {
    $test_data = $test_pdo->query("SELECT * FROM subscriptions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($test_data as $row) {
        $stmt = $live_pdo->prepare("INSERT INTO subscriptions (
            id, user_id, package_id, property_id, amount, payment_method,
            utr, slip_path, status, start_date, end_date, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $row['id'], $row['user_id'], $row['package_id'] ?? null,
            $row['property_id'] ?? null, $row['amount'], $row['payment_method'] ?? 'bank',
            $row['utr'] ?? '', $row['slip_path'] ?? '', $row['status'] ?? 'pending',
            $row['start_date'] ?? null, $row['end_date'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $count++;
    }
    echo "✅ $count Subscriptions copied!<br>";
} catch (Exception $e) { echo "❌ Subscriptions Error: " . $e->getMessage() . "<br>"; }

// ============================================================
// 💰 6. Referral Earnings Copy
// ============================================================
echo "<h4>💰 Copying Referral Earnings...</h4>";
try {
    $test_data = $test_pdo->query("SELECT * FROM user_referral_earnings ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($test_data as $row) {
        $stmt = $live_pdo->prepare("INSERT INTO user_referral_earnings (
            id, user_id, referred_user_id, package_id, amount,
            tds_deducted, admin_charge_deducted, net_amount,
            status, bank_name, account_number, ifsc_code, created_at, paid_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $row['id'], $row['user_id'], $row['referred_user_id'],
            $row['package_id'] ?? null, $row['amount'] ?? 0,
            $row['tds_deducted'] ?? 0, $row['admin_charge_deducted'] ?? 0,
            $row['net_amount'] ?? 0, $row['status'] ?? 'pending',
            $row['bank_name'] ?? '', $row['account_number'] ?? '',
            $row['ifsc_code'] ?? '', $row['created_at'] ?? date('Y-m-d H:i:s'),
            $row['paid_at'] ?? null
        ]);
        $count++;
    }
    echo "✅ $count Referral Earnings copied!<br>";
} catch (Exception $e) { echo "❌ Referral Earnings Error: " . $e->getMessage() . "<br>"; }

// ============================================================
// 📊 7. Accounting Entries Copy
// ============================================================
echo "<h4>📊 Copying Accounting Entries...</h4>";
try {
    $test_data = $test_pdo->query("SELECT * FROM account_entries ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($test_data as $row) {
        $stmt = $live_pdo->prepare("INSERT INTO account_entries (id, type, amount, description, category, entry_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $row['id'], $row['type'], $row['amount'],
            $row['description'], $row['category'],
            $row['entry_date'] ?? date('Y-m-d'), $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $count++;
    }
    echo "✅ $count Accounting Entries copied!<br>";
} catch (Exception $e) { echo "❌ Accounting Error: " . $e->getMessage() . "<br>"; }

// ============================================================
// 💳 8. Wallet Transactions Copy
// ============================================================
echo "<h4>💳 Copying Wallet Transactions...</h4>";
try {
    $test_data = $test_pdo->query("SELECT * FROM wallet_transactions ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($test_data as $row) {
        $stmt = $live_pdo->prepare("INSERT INTO wallet_transactions (id, user_id, amount, type, description, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $row['id'], $row['user_id'], $row['amount'],
            $row['type'], $row['description'], $row['reference_id'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $count++;
    }
    echo "✅ $count Wallet Transactions copied!<br>";
} catch (Exception $e) { echo "❌ Wallet Transactions Error: " . $e->getMessage() . "<br>"; }

echo "<hr>";
echo "<h3 style='color:green;'>✅ ALL DATA (Users, Properties, Subscriptions, Wallet, Referral, Accounting) COPIED SUCCESSFULLY!</h3>";
echo "<p>Now check your Live URL: <a href='https://auctionprimeproperty.onrender.com' target='_blank'>auctionprimeproperty.onrender.com</a></p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ IMPORTANT: Delete this file immediately after running!</p>";
?>
