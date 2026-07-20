<?php
// ============================================================
// 📥 DIRECT IMPORT - Complete File with Full Data Import
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Direct Import</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .bar { background: #4CAF50; height: 20px; border-radius: 5px; transition: width 0.5s; }
        .progress { background: #e9ecef; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📥 Direct Import - Complete Data</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database Connected: $dbname</div>";

    // ============================================================
    // DROP ALL TABLES
    // ============================================================
    echo "<h2>🗑️ Dropping existing tables...</h2>";
    
    $tables = [
        'user_referral_earnings', 'user_properties', 'support_tickets', 
        'kyc_documents', 'user_activity_log', 'user_spins', 
        'wallet_transactions', 'subscriptions', 'account_entries',
        'packages', 'settings', 'users', 'properties'
    ];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS $table CASCADE");
            echo "<div class='info'>🗑️ Dropped: $table</div>";
        } catch (PDOException $e) {
            // Ignore
        }
    }

    // ============================================================
    // CREATE TABLES
    // ============================================================
    echo "<h2>📋 Creating tables...</h2>";

    // Users Table
    $pdo->exec("
        CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            name TEXT,
            email TEXT,
            password TEXT,
            phone TEXT,
            referral_code TEXT,
            referred_by INT,
            role TEXT,
            status TEXT,
            permissions TEXT,
            is_super_admin INT DEFAULT 0,
            otp_code TEXT,
            otp_expiry TIMESTAMP,
            created_at TIMESTAMP,
            activation_date DATE,
            manual_referral_updated INT DEFAULT 0,
            city TEXT,
            wallet_balance DECIMAL(15,2) DEFAULT 0,
            bank_name TEXT,
            account_number TEXT,
            ifsc TEXT,
            branch TEXT,
            state TEXT,
            coins INT DEFAULT 0
        )
    ");
    echo "<div class='success'>✅ Created: users</div>";

    // Properties Table
    $pdo->exec("
        CREATE TABLE properties (
            id SERIAL PRIMARY KEY,
            title TEXT,
            description TEXT,
            price DECIMAL(15,2),
            location TEXT,
            city TEXT,
            state TEXT,
            type TEXT,
            google_location TEXT,
            image_url TEXT,
            bank_name TEXT,
            sqft DECIMAL(15,2),
            possession_type TEXT,
            inspection_date DATE,
            borrower_name TEXT,
            emd_amount DECIMAL(15,2),
            bid_increment DECIMAL(15,2),
            emd_deadline TEXT,
            auction_start_time TEXT,
            auction_end_time TEXT,
            locality TEXT,
            reserve_price_per_sqft DECIMAL(15,2),
            contact_number TEXT,
            status TEXT,
            created_at TIMESTAMP,
            auction_date DATE
        )
    ");
    echo "<div class='success'>✅ Created: properties</div>";

    // Packages Table
    $pdo->exec("
        CREATE TABLE packages (
            id SERIAL PRIMARY KEY,
            name TEXT,
            duration_months INT,
            price DECIMAL(15,2),
            discount_price DECIMAL(15,2),
            referral_bonus DECIMAL(15,2),
            max_properties INT
        )
    ");
    echo "<div class='success'>✅ Created: packages</div>";

    // Settings Table
    $pdo->exec("
        CREATE TABLE settings (
            id SERIAL PRIMARY KEY,
            setting_key TEXT,
            setting_value TEXT
        )
    ");
    echo "<div class='success'>✅ Created: settings</div>";

    // Subscriptions Table
    $pdo->exec("
        CREATE TABLE subscriptions (
            id SERIAL PRIMARY KEY,
            user_id INT,
            package_id INT,
            property_id INT,
            amount DECIMAL(15,2),
            payment_method TEXT,
            utr TEXT,
            slip_path TEXT,
            status TEXT,
            start_date DATE,
            end_date DATE,
            created_at TIMESTAMP
        )
    ");
    echo "<div class='success'>✅ Created: subscriptions</div>";

    // Wallet Transactions Table
    $pdo->exec("
        CREATE TABLE wallet_transactions (
            id SERIAL PRIMARY KEY,
            user_id INT,
            amount DECIMAL(15,2),
            type TEXT,
            description TEXT,
            reference_id INT,
            created_at TIMESTAMP
        )
    ");
    echo "<div class='success'>✅ Created: wallet_transactions</div>";

    // User Spins Table
    $pdo->exec("
        CREATE TABLE user_spins (
            id SERIAL PRIMARY KEY,
            user_id INT,
            slot_date DATE,
            slot_number INT,
            spins_used INT DEFAULT 0,
            reward_given INT DEFAULT 0,
            last_spin_at TIMESTAMP,
            coins_earned INT DEFAULT 0
        )
    ");
    echo "<div class='success'>✅ Created: user_spins</div>";

    // User Activity Log Table
    $pdo->exec("
        CREATE TABLE user_activity_log (
            id SERIAL PRIMARY KEY,
            user_id INT,
            activity_type TEXT,
            details TEXT,
            ip_address TEXT,
            created_at TIMESTAMP
        )
    ");
    echo "<div class='success'>✅ Created: user_activity_log</div>";

    // KYC Documents Table
    $pdo->exec("
        CREATE TABLE kyc_documents (
            id SERIAL PRIMARY KEY,
            user_id INT,
            doc_type TEXT,
            file_path TEXT,
            status TEXT,
            uploaded_at TIMESTAMP
        )
    ");
    echo "<div class='success'>✅ Created: kyc_documents</div>";

    // Support Tickets Table
    $pdo->exec("
        CREATE TABLE support_tickets (
            id SERIAL PRIMARY KEY,
            user_id INT,
            subject TEXT,
            message TEXT,
            screenshot TEXT,
            status TEXT,
            created_at TIMESTAMP
        )
    ");
    echo "<div class='success'>✅ Created: support_tickets</div>";

    // User Properties Table
    $pdo->exec("
        CREATE TABLE user_properties (
            id SERIAL PRIMARY KEY,
            user_id INT,
            title TEXT,
            description TEXT,
            price DECIMAL(15,2),
            city TEXT,
            state TEXT,
            type TEXT,
            image_url TEXT,
            status TEXT,
            admin_remarks TEXT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP,
            sqft DECIMAL(15,2),
            construction_sqft DECIMAL(15,2)
        )
    ");
    echo "<div class='success'>✅ Created: user_properties</div>";

    // User Referral Earnings Table
    $pdo->exec("
        CREATE TABLE user_referral_earnings (
            id SERIAL PRIMARY KEY,
            user_id INT,
            referred_user_id INT,
            package_id INT,
            amount DECIMAL(15,2),
            tds_deducted DECIMAL(15,2),
            admin_charge_deducted DECIMAL(15,2),
            net_amount DECIMAL(15,2),
            status TEXT,
            created_at TIMESTAMP,
            paid_at TIMESTAMP,
            bank_name TEXT,
            account_number TEXT,
            ifsc_code TEXT,
            remarks TEXT,
            referred_activation_date DATE,
            utr_no TEXT
        )
    ");
    echo "<div class='success'>✅ Created: user_referral_earnings</div>";

    // Account Entries Table
    $pdo->exec("
        CREATE TABLE account_entries (
            id SERIAL PRIMARY KEY,
            type TEXT,
            amount DECIMAL(15,2),
            description TEXT,
            category TEXT,
            entry_date DATE,
            created_at TIMESTAMP
        )
    ");
    echo "<div class='success'>✅ Created: account_entries</div>";

    // ============================================================
    // INSERT DATA FROM SQL FILE
    // ============================================================
    echo "<h2>📝 Inserting data...</h2>";

    $sql_file = '/var/www/html/mysql_import.sql';
    
    echo "<div class='info'>📂 Checking file: $sql_file</div>";
    
    if (!file_exists($sql_file)) {
        // Try alternative paths
        $alt_paths = [
            __DIR__ . '/mysql_import.sql',
            getcwd() . '/mysql_import.sql',
            './mysql_import.sql',
            'mysql_import.sql'
        ];
        
        $found = false;
        foreach ($alt_paths as $path) {
            if (file_exists($path)) {
                $sql_file = $path;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            die("<div class='error'>❌ SQL file not found!<br>Please upload mysql_import.sql file.</div>");
        }
    }
    
    $size = round(filesize($sql_file) / 1024 / 1024, 2);
    echo "<div class='success'>✅ File found! Size: $size MB</div>";

    // Read file
    echo "<div class='info'>📖 Reading file...</div>";
    $sql_content = file_get_contents($sql_file);
    
    // Convert MySQL to PostgreSQL
    $sql_content = str_replace('`', '"', $sql_content);
    $sql_content = str_replace('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;', '', $sql_content);
    $sql_content = str_replace('SET FOREIGN_KEY_CHECKS = 0;', '', $sql_content);
    $sql_content = str_replace('SET FOREIGN_KEY_CHECKS = 1;', '', $sql_content);
    $sql_content = preg_replace('/AUTO_INCREMENT/i', 'SERIAL', $sql_content);
    $sql_content = preg_replace('/INT PRIMARY KEY AUTO_INCREMENT/i', 'SERIAL PRIMARY KEY', $sql_content);
    $sql_content = preg_replace('/TINYINT\(1\)/i', 'BOOLEAN', $sql_content);
    $sql_content = preg_replace('/TINYINT/i', 'SMALLINT', $sql_content);

    // Split statements
    $statements = preg_split("/;(?=(?:[^']*'[^']*')*[^']*$)/", $sql_content);
    
    $success = 0;
    $failed = 0;
    $total = count($statements);
    
    echo "<div class='info'>⏳ Total statements: $total</div>";
    echo "<div class='progress'><div class='bar' style='width: 0%;'></div></div>";
    
    $counter = 0;
    
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        
        // Skip DROP TABLE statements
        if (preg_match('/^DROP TABLE/i', $stmt)) {
            continue;
        }
        
        $counter++;
        
        try {
            $pdo->exec($stmt);
            $success++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'duplicate key') === false) {
                $failed++;
                if ($failed <= 5) {
                    echo "<div class='error'>❌ " . htmlspecialchars(substr($e->getMessage(), 0, 100)) . "</div>";
                }
            }
        }
        
        // Update progress every 10 statements
        if ($counter % 10 == 0) {
            $progress = round(($counter / $total) * 100);
            echo "<script>document.querySelector('.bar').style.width = '$progress%';</script>";
            ob_flush();
            flush();
        }
    }
    
    echo "<div class='success'>✅ Executed: $success successful, $failed failed</div>";

    // ============================================================
    // SUMMARY
    // ============================================================
    echo "<h2>📊 Database Summary</h2>";
    
    $tables = ['users', 'properties', 'packages', 'settings', 'subscriptions', 
               'wallet_transactions', 'user_spins', 'user_activity_log', 
               'kyc_documents', 'support_tickets', 'user_properties', 
               'user_referral_earnings', 'account_entries'];
    
    echo "<table>";
    echo "<tr><th>#</th><th>Table</th><th>Record Count</th><th>Status</th></tr>";
    
    $idx = 1;
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            $status = $count > 0 ? '✅' : '⚠️ Empty';
            echo "<tr><td>$idx</td><td>$table</td><td>$count</td><td>$status</td></tr>";
        } catch (PDOException $e) {
            echo "<tr><td>$idx</td><td>$table</td><td>❌ Not Found</td><td>❌</td></tr>";
        }
        $idx++;
    }
    echo "</table>";

    echo "<div class='success'>✅ Import completed successfully!</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
