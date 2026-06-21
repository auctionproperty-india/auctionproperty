<?php
// ============================================================
// ✅ यह फाइल सिर्फ TEST ENVIRONMENT (auctionproperty-1) पर चलाएँ
// ============================================================

// ---- Test Database Connection (जहाँ Data डालना है) ----
$test_host = getenv('DB_HOST') ?: 'localhost';
$test_port = getenv('DB_PORT') ?: '5432';
$test_dbname = getenv('DB_NAME') ?: 'postgres';
$test_user = getenv('DB_USER') ?: 'postgres';
$test_password = getenv('DB_PASSWORD') ?: '';

try {
    $test_pdo = new PDO("pgsql:host=$test_host;port=$test_port;dbname=$test_dbname;sslmode=require", $test_user, $test_password);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Test DB Connection Failed: " . $e->getMessage());
}

// ---- Live Database Connection (जहाँ से Data लेना है) ----
// ⚠️ यहाँ Live Database के Credentials डालें (Render वाले)
$live_host = 'dpg-xxxxxxxxx.oregon-postgres.render.com'; // 🔴 अपना Live Hostname डालें
$live_port = '5432';
$live_dbname = 'auction_db'; // या जो भी हो
$live_user = 'auction_db_user'; // 🔴 अपना Live User डालें
$live_password = 'xxxxxxxxxxxxx'; // 🔴 अपना Live Password डालें

try {
    $live_pdo = new PDO("pgsql:host=$live_host;port=$live_port;dbname=$live_dbname;sslmode=require", $live_user, $live_password);
    $live_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Live Database Connected!<br>";
} catch (PDOException $e) {
    die("❌ Live DB Connection Failed: " . $e->getMessage());
}

// ============================================================
// 1. Properties Table Copy
// ============================================================
echo "<h4>📦 Copying Properties...</h4>";
try {
    // पहले Test DB में पुरानी Properties हटाएँ (सिर्फ इसी Table की)
    $test_pdo->exec("TRUNCATE TABLE properties RESTART IDENTITY CASCADE");
    
    // Live से Data Fetch करें
    $live_props = $live_pdo->query("SELECT * FROM properties")->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach($live_props as $row) {
        // Explicit Columns (PostgreSQL के cached plan issue से बचने के लिए)
        $sql = "INSERT INTO properties (
            id, title, description, price, location, city, state, type, 
            google_location, image_url, bank_name, sqft, possession_type, 
            auction_date, borrower_name, emd_amount, bid_increment, 
            emd_deadline, auction_start_time, auction_end_time, locality, 
            reserve_price_per_sqft, contact_number, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $test_pdo->prepare($sql);
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
    echo "✅ $count Properties copied successfully!<br>";
} catch (Exception $e) {
    echo "❌ Properties Copy Error: " . $e->getMessage() . "<br>";
}

// ============================================================
// 2. Users Table Copy (जरूरी नहीं, लेकिन अगर चाहें तो)
// ============================================================
echo "<h4>👥 Copying Users...</h4>";
try {
    // पहले Test DB में पुराने Users हटाएँ (सिर्फ इसी Table की)
    $test_pdo->exec("TRUNCATE TABLE users RESTART IDENTITY CASCADE");
    
    $live_users = $live_pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($live_users as $row) {
        $sql = "INSERT INTO users (
            id, name, email, password, phone, city, referral_code, 
            referred_by, role, status, permissions, is_super_admin, 
            otp_code, otp_expiry, wallet_balance, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $test_pdo->prepare($sql);
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

// ============================================================
// 3. Subscriptions Table Copy (अगर चाहें)
// ============================================================
echo "<h4>📋 Copying Subscriptions...</h4>";
try {
    $test_pdo->exec("TRUNCATE TABLE subscriptions RESTART IDENTITY CASCADE");
    
    $live_subs = $live_pdo->query("SELECT * FROM subscriptions")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($live_subs as $row) {
        $sql = "INSERT INTO subscriptions (
            id, user_id, package_id, property_id, amount, payment_method,
            utr, slip_path, status, start_date, end_date, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $test_pdo->prepare($sql);
        $stmt->execute([
            $row['id'], $row['user_id'], $row['package_id'] ?? null,
            $row['property_id'] ?? null, $row['amount'], $row['payment_method'] ?? 'bank',
            $row['utr'] ?? '', $row['slip_path'] ?? '', $row['status'] ?? 'pending',
            $row['start_date'] ?? null, $row['end_date'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $count++;
    }
    echo "✅ $count Subscriptions copied successfully!<br>";
} catch (Exception $e) {
    echo "❌ Subscriptions Copy Error: " . $e->getMessage() . "<br>";
}

// ============================================================
// 4. Packages Table Copy (अगर चाहें)
// ============================================================
echo "<h4>📦 Copying Packages...</h4>";
try {
    $test_pdo->exec("TRUNCATE TABLE packages RESTART IDENTITY CASCADE");
    
    $live_pkgs = $live_pdo->query("SELECT * FROM packages")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($live_pkgs as $row) {
        $sql = "INSERT INTO packages (id, name, duration_months, price, discount_price, referral_bonus) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $test_pdo->prepare($sql);
        $stmt->execute([
            $row['id'], $row['name'], $row['duration_months'],
            $row['price'], $row['discount_price'] ?? null, $row['referral_bonus'] ?? 0
        ]);
        $count++;
    }
    echo "✅ $count Packages copied successfully!<br>";
} catch (Exception $e) {
    echo "❌ Packages Copy Error: " . $e->getMessage() . "<br>";
}

// ============================================================
// 5. Referral Earnings Table Copy
// ============================================================
echo "<h4>💰 Copying Referral Earnings...</h4>";
try {
    $test_pdo->exec("TRUNCATE TABLE user_referral_earnings RESTART IDENTITY CASCADE");
    
    $live_refs = $live_pdo->query("SELECT * FROM user_referral_earnings")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($live_refs as $row) {
        $sql = "INSERT INTO user_referral_earnings (
            id, user_id, referred_user_id, package_id, amount,
            tds_deducted, admin_charge_deducted, net_amount,
            status, bank_name, account_number, ifsc_code, created_at, paid_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $test_pdo->prepare($sql);
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
    echo "✅ $count Referral Earnings copied successfully!<br>";
} catch (Exception $e) {
    echo "❌ Referral Earnings Copy Error: " . $e->getMessage() . "<br>";
}

// ============================================================
// 6. Accounting Entries Copy
// ============================================================
echo "<h4>📊 Copying Accounting Entries...</h4>";
try {
    $test_pdo->exec("TRUNCATE TABLE account_entries RESTART IDENTITY CASCADE");
    
    $live_acc = $live_pdo->query("SELECT * FROM account_entries")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($live_acc as $row) {
        $sql = "INSERT INTO account_entries (id, type, amount, description, category, entry_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $test_pdo->prepare($sql);
        $stmt->execute([
            $row['id'], $row['type'], $row['amount'],
            $row['description'], $row['category'],
            $row['entry_date'] ?? date('Y-m-d'), $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $count++;
    }
    echo "✅ $count Accounting Entries copied successfully!<br>";
} catch (Exception $e) {
    echo "❌ Accounting Entries Copy Error: " . $e->getMessage() . "<br>";
}

// ============================================================
// 7. Wallet Transactions Copy
// ============================================================
echo "<h4>💳 Copying Wallet Transactions...</h4>";
try {
    $test_pdo->exec("TRUNCATE TABLE wallet_transactions RESTART IDENTITY CASCADE");
    
    $live_wallet = $live_pdo->query("SELECT * FROM wallet_transactions")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($live_wallet as $row) {
        $sql = "INSERT INTO wallet_transactions (id, user_id, amount, type, description, reference_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $test_pdo->prepare($sql);
        $stmt->execute([
            $row['id'], $row['user_id'], $row['amount'],
            $row['type'], $row['description'], $row['reference_id'] ?? null,
            $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        $count++;
    }
    echo "✅ $count Wallet Transactions copied successfully!<br>";
} catch (Exception $e) {
    echo "❌ Wallet Transactions Copy Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3 style='color:green;'>✅ ALL DATA COPIED SUCCESSFULLY!</h3>";
echo "<p>Now check your Test URL: <a href='https://auctionproperty-1.onrender.com' target='_blank'>auctionproperty-1.onrender.com</a></p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ IMPORTANT: Delete this file immediately after running!</p>";
?>
