<?php
// ============================================================
// 📥 Import ALL Remaining Tables
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Import All Remaining Data</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
<div class='container'>
    <h1>📥 Import All Remaining Data</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Database Connected: $dbname</div>";

    // ============================================================
    // 1. SUBSCRIPTIONS
    // ============================================================
    echo "<h2>📋 Importing Subscriptions...</h2>";
    $sql = <<<SQL
    INSERT INTO subscriptions (id, user_id, package_id, property_id, amount, payment_method, utr, slip_path, status, start_date, end_date, created_at) VALUES
    (1,20,4,NULL,11000.00,'bank','893120643085','uploads/slip_1782835365_74f95b50321a.jpg','active','2026-06-30','2027-06-30','2026-06-30 16:02:45.459803'),
    (2,20,4,NULL,11000.00,'bank','893120643085','uploads/slip_1782835496_d32c97b79c92.jpg','rejected',NULL,NULL,'2026-06-30 16:04:56.882672'),
    (4,6,1,NULL,100.00,'bank','1','uploads/slip_1783221282_ce4433cb1285.jpg','rejected',NULL,NULL,'2026-07-05 03:14:42.851659'),
    (3,6,1,NULL,100.00,'bank','1','uploads/slip_1783221272_08d272a1f291.jpg','active','2026-07-05','2027-08-05','2026-07-05 03:14:32.780937'),
    (8,165,2,NULL,3000.00,'bank','12 cash','uploads/slip_1783360216_d516a3ffdd98.jpg','rejected',NULL,NULL,'2026-07-06 17:50:17.260501'),
    (7,165,2,NULL,3000.00,'bank','12 cash','uploads/slip_1783360210_48f2b04b1073.jpg','rejected',NULL,NULL,'2026-07-06 17:50:10.309282'),
    (5,165,2,NULL,3000.00,'bank','Cash payment','uploads/slip_1783359760_b8c3eb86a88f.jpg','rejected',NULL,NULL,'2026-07-06 17:42:40.743223'),
    (9,164,2,NULL,3500.00,'referral_bonus','','','active','2026-07-07','2026-10-07','2026-07-07 04:20:10.049352'),
    (6,165,2,NULL,3000.00,'bank','12 cash','uploads/slip_1783360196_09b611f9549c.jpg','active','2026-06-07','2027-06-06','2026-07-06 17:49:56.964933')
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM subscriptions");
    echo "<div class='success'>✅ Subscriptions: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 2. WALLET TRANSACTIONS
    // ============================================================
    echo "<h2>📋 Importing Wallet Transactions...</h2>";
    $sql = <<<SQL
    INSERT INTO wallet_transactions (id, user_id, amount, type, description, reference_id, created_at) VALUES
    (1,7,1720.50,'credit','Referral bonus (net) for multiple referrals (Paid via Admin Pay All)',0,'2026-07-07 03:30:40.203766'),
    (2,6,465.00,'credit','Referral bonus (net) for multiple referrals (Paid via Admin Pay All)',0,'2026-07-07 05:19:33.990135')
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM wallet_transactions");
    echo "<div class='success'>✅ Wallet Transactions: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 3. USER SPINS
    // ============================================================
    echo "<h2>📋 Importing User Spins...</h2>";
    $sql = <<<SQL
    INSERT INTO user_spins (id, user_id, slot_date, slot_number, spins_used, reward_given, last_spin_at, coins_earned) VALUES
    (40,73,'2026-07-06',1,0,0,'2026-07-06 04:01:40.029031',0),
    (41,73,'2026-07-06',2,0,0,'2026-07-06 04:01:41.080168',0),
    (42,73,'2026-07-06',3,0,0,'2026-07-06 04:01:42.121258',0),
    (44,6,'2026-07-06',2,0,0,'2026-07-06 04:39:37.890226',0),
    (1,6,'2026-07-05',2,5,1,'2026-07-05 12:04:24.034369',0),
    (2,6,'2026-07-05',1,0,0,'2026-07-05 12:29:09.917284',0),
    (3,6,'2026-07-05',3,0,0,'2026-07-05 12:29:11.486561',0),
    (4,7,'2026-07-05',1,0,0,'2026-07-05 12:32:42.411777',0),
    (89,27,'2026-07-07',1,0,0,'2026-07-07 04:01:31.927233',0),
    (90,27,'2026-07-07',2,0,0,'2026-07-07 04:01:32.968617',0),
    (39,7,'2026-07-06',3,5,1,'2026-07-06 02:42:59.676741',20),
    (91,27,'2026-07-07',3,0,0,'2026-07-07 04:01:34.009908',0),
    (5,7,'2026-07-05',2,5,1,'2026-07-05 12:34:14.008594',14),
    (7,122,'2026-07-05',1,0,0,'2026-07-05 12:48:45.61654',0),
    (9,122,'2026-07-05',3,0,0,'2026-07-05 12:48:47.708216',0),
    (43,6,'2026-07-06',1,5,1,'2026-07-06 04:39:36.846975',20),
    (46,100,'2026-07-06',1,0,0,'2026-07-06 05:10:29.841844',0),
    (47,100,'2026-07-06',2,0,0,'2026-07-06 05:10:30.882721',0),
    (48,100,'2026-07-06',3,0,0,'2026-07-06 05:10:31.922992',0),
    (8,122,'2026-07-05',2,5,1,'2026-07-05 12:52:24.391676',12)
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_spins");
    echo "<div class='success'>✅ User Spins: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 4. USER ACTIVITY LOG
    // ============================================================
    echo "<h2>📋 Importing User Activity Log...</h2>";
    $sql = <<<SQL
    INSERT INTO user_activity_log (id, user_id, activity_type, details, ip_address, created_at) VALUES
    (1,9,'login',NULL,'::1','2026-07-06 13:36:52.873473'),
    (2,18,'login',NULL,'::1','2026-07-06 14:43:22.246476'),
    (3,21,'property_view','Property ID: 79, Source: auction','::1','2026-07-06 14:52:02.248027'),
    (4,21,'property_view','Property ID: 144, Source: auction','::1','2026-07-06 14:58:45.383435'),
    (5,21,'property_view','Property ID: 144, Source: auction','::1','2026-07-06 14:58:50.600863'),
    (6,36,'property_view','Property ID: 28, Source: auction','::1','2026-07-06 15:09:27.23783'),
    (7,36,'spin','Slot: 3, Spins: 1, Coins: 0','::1','2026-07-06 15:16:51.287212'),
    (8,36,'property_view','Property ID: 79, Source: auction','::1','2026-07-06 15:17:14.825967'),
    (9,36,'spin','Slot: 3, Spins: 2, Coins: 0','::1','2026-07-06 15:17:40.314227'),
    (10,36,'spin','Slot: 3, Spins: 3, Coins: 0','::1','2026-07-06 15:17:52.37429')
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_activity_log");
    echo "<div class='success'>✅ User Activity Log: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 5. KYC DOCUMENTS
    // ============================================================
    echo "<h2>📋 Importing KYC Documents...</h2>";
    $sql = <<<SQL
    INSERT INTO kyc_documents (id, user_id, doc_type, file_path, status, uploaded_at) VALUES
    (1,6,'aadhar','uploads/kyc/kyc_6_1782990143_f843dcce.jpg','pending','2026-07-02 11:02:23.524755'),
    (2,6,'pan','uploads/kyc/kyc_6_1782990179_0afcf2a1.jpg','pending','2026-07-02 11:02:59.940697'),
    (3,18,'aadhar','uploads/kyc/kyc_18_1783007261.pdf','pending','2026-07-02 15:47:42.083803'),
    (4,18,'aadhar','uploads/kyc/kyc_18_1783084939.pdf','pending','2026-07-03 13:22:19.72217'),
    (5,18,'aadhar','uploads/kyc/kyc_18_1783084941.pdf','pending','2026-07-03 13:22:22.234004')
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM kyc_documents");
    echo "<div class='success'>✅ KYC Documents: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 6. SUPPORT TICKETS
    // ============================================================
    echo "<h2>📋 Importing Support Tickets...</h2>";
    $sql = <<<SQL
    INSERT INTO support_tickets (id, user_id, subject, message, screenshot, status, created_at) VALUES
    (1,6,'website not working','web','uploads/support/support_6_1782990545.jpeg','closed','2026-07-02 11:09:06.094221')
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM support_tickets");
    echo "<div class='success'>✅ Support Tickets: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 7. USER PROPERTIES (User submitted properties)
    // ============================================================
    echo "<h2>📋 Importing User Properties...</h2>";
    $sql = <<<SQL
    INSERT INTO user_properties (id, user_id, title, description, price, city, state, type, image_url, status, admin_remarks, created_at, updated_at, sqft, construction_sqft) VALUES
    (3,171,'1000 sqft plot for sale in Simrol near Indore IIT','In the Bansal Vihar colony in Simrol. 20*50=1000 Sqft plot for sale in the rate of 2500 rupee per sqft for more details call or WhatsApp on 9407168390',2500.00,'Indore','Madhya@Pradesh','Plot','','approved',NULL,'2026-07-07 07:24:43.942688','2026-07-07 07:24:43.942688',1000.00,0.00),
    (2,73,'Rental residential building','Free hold Title clear Mahalaxmi nagar near Bombay Hospital Indore',30000000.00,'Indore','M.P','House','uploads/user_properties/userprop_73_1783351543.jpg','approved',NULL,'2026-07-06 15:25:43.398111','2026-07-06 15:25:43.398111',1500.00,3660.00),
    (1,6,'property in tejaij nagar','plot area 400 sq feet 4 floor construction area 1630 sq feet contect - no. 8878190275',5500000.00,'indore','mp','House','uploads/user_properties/userprop_6_1783413945.jpeg','approved',NULL,'2026-07-03 11:08:38.520433','2026-07-07 08:45:46.146543',400.00,1630.00),
    (4,200,'Premium house for sale at Ramji vatika 1','750 sqrft house double floor with tower room and latbath attach total construction area 2025 sqrft',8000000.00,'Indore','Madhyapradesh','Row House','uploads/user_properties/userprop_200_1783619107.jpg','pending',NULL,'2026-07-09 17:45:07.607418','2026-07-10 04:35:33.038718',750.00,2025.00)
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_properties");
    echo "<div class='success'>✅ User Properties: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 8. USER REFERRAL EARNINGS
    // ============================================================
    echo "<h2>📋 Importing User Referral Earnings...</h2>";
    $sql = <<<SQL
    INSERT INTO user_referral_earnings (id, user_id, referred_user_id, package_id, amount, tds_deducted, admin_charge_deducted, net_amount, status, created_at, paid_at, bank_name, account_number, ifsc_code, remarks, referred_activation_date, utr_no) VALUES
    (1,7,20,4,1350.00,27.00,67.50,1255.50,'paid','2026-07-06 12:48:00.970116','2026-07-07 03:30:38.805144','State Bank of India','41292717518','SBIN OOO 1688',NULL,'2026-06-30','MB77263096610377047'),
    (2,7,164,2,500.00,10.00,25.00,465.00,'paid','2026-07-06 16:31:44.879257','2026-07-07 03:30:39.328867','State Bank of India','41292717518','SBIN OOO 1688',NULL,'2026-06-06','MB77263096610377047'),
    (3,6,165,2,500.00,10.00,25.00,465.00,'paid','2026-07-07 03:01:05.00066','2026-07-07 05:19:33.11262','sbi','30731161769','sbin0047034',NULL,NULL,'MB77265364110378277')
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_referral_earnings");
    echo "<div class='success'>✅ User Referral Earnings: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 9. ACCOUNT ENTRIES
    // ============================================================
    echo "<h2>📋 Importing Account Entries...</h2>";
    $sql = <<<SQL
    INSERT INTO account_entries (id, type, amount, description, category, entry_date, created_at) VALUES
    (3,'expense',500.00,'Airtel','wifi bill','2026-06-25','2026-06-25 11:41:32.164064'),
    (5,'income',8000.00,'Subscription payment from user Anil Gupta (ID: 20) for package Diamond','Auction Subscription','2026-06-30','2026-07-02 17:53:17.705173'),
    (7,'income',3000.00,'Subscription payment from AjarIshan Pathak (luckymascotji@gmail.com) for package Gold','Auction Subscription','2026-07-06','2026-07-07 03:01:06.221198'),
    (8,'income',3000.00,'Manual subscription activation for user Dinesh Anand (ID: 164) for package Gold','Auction Subscription','2026-07-07','2026-07-07 04:20:10.577862'),
    (9,'expense',465.00,'Referral payout to Shani Pathak (ID: 7) - Net ₹465 | UTR: MB77263096610377047 | Earning ID: 2','Referral Payout','2026-07-07','2026-07-07 05:00:09.684479'),
    (10,'expense',1255.50,'Referral payout to Shani Pathak (ID: 7) - Net ₹1,255 | UTR: MB77263096610377047 | Earning ID: 1','Referral Payout','2026-07-07','2026-07-07 05:00:10.212475'),
    (11,'expense',465.00,'Referral payout to Sachin Solanki (ID: 6) - Net ₹465 | UTR: MB77265364110378277 (Pay All)','Referral Payout','2026-07-07','2026-07-07 05:19:34.865765'),
    (6,'income',1.00,'Subscription payment from Sachin Solanki (bliveindia2018@gmail.com) for package 1 (edited amount)','Auction Subscription','2026-07-05','2026-07-05 03:16:14.493655')
    ON CONFLICT (id) DO NOTHING;
SQL;
    $pdo->exec($sql);
    $stmt = $pdo->query("SELECT COUNT(*) FROM account_entries");
    echo "<div class='success'>✅ Account Entries: " . $stmt->fetchColumn() . " records</div>";

    // ============================================================
    // 10. FINAL SUMMARY
    // ============================================================
    echo "<h2>📊 Final Database Summary</h2>";
    $tables = [
        'users', 'properties', 'packages', 'settings', 'subscriptions',
        'wallet_transactions', 'user_spins', 'user_activity_log',
        'kyc_documents', 'support_tickets', 'user_properties',
        'user_referral_earnings', 'account_entries'
    ];
    
    echo "<table>";
    echo "<tr><th>#</th><th>Table</th><th>Record Count</th></tr>";
    $idx = 1;
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<tr><td>$idx</td><td>$table</td><td>$count</td></tr>";
        } catch (PDOException $e) {
            echo "<tr><td>$idx</td><td>$table</td><td>❌ Not Found</td></tr>";
        }
        $idx++;
    }
    echo "</table>";

    echo "<hr>";
    echo "<div class='success'>🎉 All remaining data imported successfully!</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";
    echo "<div class='info'>⚠️ Delete this file after use for security.</div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
