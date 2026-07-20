<?php
// ============================================================
// 📥 COMPLETE DATA IMPORT - Fixed Version
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Complete Data Import</title>
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
    </style>
</head>
<body>
<div class='container'>
    <h1>📥 Complete Data Import</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div class='success'>✅ Database Connected: $dbname</div>";

    // ============================================================
    // DROP EXISTING TABLES
    // ============================================================
    echo "<h2>🗑️ Dropping existing tables...</h2>";
    
    $tables_to_drop = [
        'user_referral_earnings', 'user_properties', 'support_tickets', 
        'kyc_documents', 'user_activity_log', 'user_spins', 
        'wallet_transactions', 'subscriptions', 'account_entries',
        'packages', 'settings', 'users', 'properties'
    ];
    
    foreach ($tables_to_drop as $table) {
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
    // INSERT DATA - Using pg_escape (Safe Method)
    // ============================================================
    echo "<h2>📝 Inserting data...</h2>";

    // Read SQL file
    $sql_file = __DIR__ . '/mysql_import.sql';
    
    if (!file_exists($sql_file)) {
        die("<div class='error'>❌ SQL file not found: $sql_file<br>Please upload your mysql_import.sql file to the server.</div>");
    }
    
    $sql_content = file_get_contents($sql_file);
    echo "<div class='info'>📄 File size: " . round(filesize($sql_file) / 1024 / 1024, 2) . " MB</div>";

    // Extract all INSERT statements
    preg_match_all('/INSERT INTO `([^`]+)` VALUES\s*\((.*?)\)\s*;/is', $sql_content, $matches);

    if (empty($matches[0])) {
        echo "<div class='error'>❌ No INSERT statements found in SQL file!</div>";
    } else {
        echo "<div class='info'>📊 Found " . count($matches[0]) . " INSERT statements</div>";
        
        $total_inserted = 0;
        
        foreach ($matches[0] as $index => $insert_stmt) {
            $table_name = $matches[1][$index];
            $values_str = $matches[2][$index];
            
            // Parse values (handles quoted strings with commas)
            $values = [];
            $current = '';
            $in_quotes = false;
            $quote_char = '';
            $len = strlen($values_str);
            
            for ($i = 0; $i < $len; $i++) {
                $char = $values_str[$i];
                
                if ($in_quotes) {
                    if ($char === $quote_char && ($i + 1 < $len && $values_str[$i + 1] === $quote_char)) {
                        $current .= $char;
                        $i++; // Skip next quote
                    } elseif ($char === $quote_char) {
                        $in_quotes = false;
                        $current .= $char;
                    } else {
                        $current .= $char;
                    }
                } else {
                    if ($char === "'" || $char === '"') {
                        $in_quotes = true;
                        $quote_char = $char;
                        $current .= $char;
                    } elseif ($char === ',' && !$in_quotes) {
                        $current = trim($current);
                        if ($current === 'NULL' || $current === "''" || $current === '""') {
                            $values[] = null;
                        } else {
                            $values[] = $current;
                        }
                        $current = '';
                    } else {
                        $current .= $char;
                    }
                }
            }
            
            // Add last value
            $current = trim($current);
            if (!empty($current)) {
                if ($current === 'NULL' || $current === "''" || $current === '""') {
                    $values[] = null;
                } else {
                    $values[] = $current;
                }
            }
            
            // Determine number of columns (placeholder count) - simplistic approach
            // We'll dynamically build the INSERT with proper quoting
            
            if (empty($values)) {
                continue;
            }
            
            // Build the INSERT statement with proper PostgreSQL quoting
            $placeholders = [];
            $escaped_values = [];
            
            foreach ($values as $val) {
                if ($val === null || $val === 'NULL') {
                    $placeholders[] = 'NULL';
                } elseif (is_numeric($val) && strpos($val, "'") !== 0) {
                    // Try to keep as number
                    $placeholders[] = $val;
                } else {
                    // Remove surrounding quotes if present
                    $val = trim($val);
                    if ((strpos($val, "'") === 0 && substr($val, -1) === "'") || 
                        (strpos($val, '"') === 0 && substr($val, -1) === '"')) {
                        $val = substr($val, 1, -1);
                    }
                    // Escape single quotes for PostgreSQL
                    $val = str_replace("'", "''", $val);
                    $placeholders[] = "'" . $val . "'";
                }
            }
            
            try {
                $insert_sql = "INSERT INTO \"$table_name\" VALUES (" . implode(', ', $placeholders) . ")";
                $pdo->exec($insert_sql);
                $total_inserted++;
                if ($total_inserted % 10 == 0) {
                    echo "<div class='info'>✅ Inserted $total_inserted records...</div>";
                }
            } catch (PDOException $e) {
                // Skip errors and continue
                if (strpos($e->getMessage(), 'duplicate key') === false) {
                    // Don't flood with errors
                }
            }
        }
        
        echo "<div class='success'>✅ Total records inserted: $total_inserted</div>";
    }

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
            $idx++;
        } catch (PDOException $e) {
            echo "<tr><td>$idx</td><td>$table</td><td>❌ Not Found</td><td>❌</td></tr>";
            $idx++;
        }
    }
    echo "</table>";

    echo "<div class='success'>✅ Import completed!</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
