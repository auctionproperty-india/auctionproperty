<?php
// ============================================================
// ✅ यह फाइल सिर्फ PROPERTIES DATA को Live से Test पर Copy करेगी
// बाकी सब (Users, Subscriptions, Wallet, etc.) वैसे ही रहेगा
// ============================================================

// ---- Test Database Connection (जहाँ Data डालना है) ----
// Test DB के Credentials Render Environment Variables से लिए जाएँगे
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

// ---- Live Database Connection (जहाँ से Data लेना है) ----
// ✅ आपके दिए गए Live Render PostgreSQL Credentials
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
// PROPERTIES TABLE COPY (ONLY)
// ============================================================
echo "<h4>📦 Copying Properties...</h4>";
try {
    // ✅ सिर्फ properties Table को ही Truncate करें (बाकी Tables untouched)
    $test_pdo->exec("TRUNCATE TABLE properties RESTART IDENTITY CASCADE");
    echo "✅ Test properties table cleared.<br>";
    
    $live_props = $live_pdo->query("SELECT * FROM properties")->fetchAll(PDO::FETCH_ASSOC);
    $count = 0;
    foreach($live_props as $row) {
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

echo "<hr>";
echo "<h3 style='color:green;'>✅ PROPERTIES DATA COPIED SUCCESSFULLY!</h3>";
echo "<p>Now check your Test URL: <a href='https://auctionproperty-1.onrender.com' target='_blank'>auctionproperty-1.onrender.com</a></p>";
echo "<p><strong>Note:</strong> Only the <code>properties</code> table has been replaced. All other data (Users, Subscriptions, Wallet, etc.) remains unchanged.</p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ IMPORTANT: Delete this file immediately after running!</p>";
?>
