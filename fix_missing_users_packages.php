<?php
// ============================================================
// 🔧 Fix Missing Users (ID > 87) + Packages
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Missing Data</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: auto; background: white; padding: 20px; border-radius: 10px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 Fix Missing Users & Packages</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Database Connected</div>";

    // ============================================================
    // 1. PACKAGES
    // ============================================================
    echo "<h2>📦 Checking Packages...</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) FROM packages");
    $pkg_count = $stmt->fetchColumn();
    if ($pkg_count == 0) {
        echo "<div class='info'>No packages found. Inserting...</div>";
        $pdo->exec("
            INSERT INTO packages (id, name, duration_months, price, discount_price, referral_bonus, max_properties) VALUES
            (1,'Silver',1,1500.00,1200.00,120.00,1),
            (2,'Gold',3,3500.00,3000.00,500.00,3),
            (3,'Platinum',6,6500.00,5500.00,1000.00,5),
            (4,'Diamond',12,11000.00,9500.00,1500.00,10)
            ON CONFLICT (id) DO NOTHING;
        ");
        echo "<div class='success'>✅ Packages inserted.</div>";
    } else {
        echo "<div class='success'>✅ Packages already exist ($pkg_count records).</div>";
    }

    // ============================================================
    // 2. USERS (ID > 87) - Jo missing hain
    // ============================================================
    echo "<h2>👤 Checking Users...</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    echo "<div class='info'>Current total users: $user_count</div>";

    // List of users we need to insert (IDs > 87)
    $sql = "
        INSERT INTO users (id, name, email, password, phone, referral_code, referred_by, role, status, permissions, is_super_admin, otp_code, otp_expiry, created_at, activation_date, manual_referral_updated, city, wallet_balance, bank_name, account_number, ifsc, branch, state, coins) VALUES
        (97,'Tarun sahu','jiyatarun90@gmail.com','\$2y\$10\$hwJ/Rdm/7TQOjEPhG10Dl.y3PncIEonfh91moVy5p81O3Wo9lE6Vy','6263797942','8C465E3A',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-04 14:06:27.669964',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (100,'Jai Chakravedi','jchakravedi@gmail.com','\$2y\$10\$coTMEbhZgGMUoY7NVR.deuj81b4.xuvyGZV2qU2goUTIdQSYDvHhW','9617544236','A929A195',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-04 15:28:51.074947',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (101,'Vicky Khare','vickykhare177@gmail.com','\$2y\$10\$Rc4/HGlK5FLhJsTbexqAJel.0mrq5IX86FGFBTqVC34QbwT4pVxoO','7489418887','7C6A8873',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 03:41:29.507094',NULL,0,'Mhow',0.00,NULL,NULL,NULL,NULL,NULL,106),
        (104,'Yashwant Ahirwar','yashwantahirwar162@gmail.com','\$2y\$10\$8DrKDWvo1lSv02E8LynKvOc8xNMzQcdy4pKy1r3sMak.dfCLnZtcu','9294879092','BDA42E04',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 05:45:50.36984',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (111,'Renuka Sharma','renukavinod0330@gmail.com','\$2y\$10\$6E25fbFRSNkgrqq1mJnTcOSVDhNdpyxt4ODDcTxirqmxpUEDPbija','9329625275','REF_305E5658',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 06:32:11.508427',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (112,'Gayatri','gayatri.bajpai765@gmail.com','\$2y\$10\$udNTiaHi/0E.Pc7NZcfeZORJ6bt66EofgqQwODWK1Q1vF6222amEK','9755878449','248DDD9B',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 07:08:48.397856',NULL,0,'Indy',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (115,'Ghanshyam Verma','gv341447@gmail.com','\$2y\$10\$cW/KrAYuVh.mGJ1T655VS.ilIxyEN96UGR4Rn92S0l/Nup831DCEC','9329188100','F2AE840F',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 08:17:17.409044',NULL,0,'INDORE',0.00,NULL,NULL,NULL,NULL,NULL,307),
        (118,'Ashok Kumar Namdev','mnamdev06@gmail.com','\$2y\$10\$GbDw2BUpkxbAVcgP6db9lu6Qw6ZgbcPUO8Qf3sY/leJACbZyNbGPi','9460751600','93BDCAF3',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 08:32:37.606662',NULL,0,'Idore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (121,'Badri Prasad Goyal','badriprasadgoyal@gmail.com','\$2y\$10\$nvFA6kZlcQyAJffXZRIje.sWXeO0bQttSX6CCfMihAYLbU4gp3ehe','9926429503','6C3C4B7B',14,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 11:04:23.669362',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,200),
        (122,'Ritik rane','raneritik1@gmail.com','\$2y\$10\$5IeDLIZOYk10RI.vJWjZwepwpCHOycT2Sf4y3ATMhFrbhbNAhR2UK','9691756963','0266B778',121,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 11:15:16.277037',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,'',112),
        (123,'Tokir','himmuraikwar15@gmail.com','\$2y\$10\$kn12IJ/cf3sWDjleSF1eHuulvFQ3sk.WBLVvvHJlSX4jP/mFNQrjy','6263796637','EA8E4F78',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 13:31:50.381177',NULL,0,'Amla',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (124,'Yogesh chouhan','kalabaic584@gmail.com','\$2y\$10\$YQJpJjYWCE29/EZj/FmNN.gKgJlnpuooSlv1DFnYIQuq6HTEEo.P6','7024386861','FD23C163',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 13:41:19.972366',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (125,'HARSH SINGH CHOUHAN','harshsingh01chouhan@gmail.com','\$2y\$10\$SiGjRKrZFPSgsHZh7Ehaw.ckKIjJzLLX/tBhWksYb.nPaq1BAdBqW','7909791010','6339BE41',14,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-05 14:00:30.398385',NULL,0,'INDORE',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (126,'Arun Rajput','thakurarun0322@gmail.com','\$2y\$10\$EQXF2aeV9PkYhrYbHJDFde6YD4Jq5uaJoiXegxmWx8lhgNHGj7WUC','6261054212','BB5F91A0',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-06 02:24:00.808933',NULL,0,'Shajapur',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (134,'Nitin Arya','nitinarya1974@gmail.com','\$2y\$10\$HzE53d46e7Pe1N9/l2oSqe.Qcayc9cNMWWj0EOXEwpHckKZFoK5Zq','6264949433','E9986433',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-06 10:25:19.446668',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (158,'Vishal','vishalpure036@gmail.com','\$2y\$10\$laub3Xk2OsJu35el0gsz1.J.4O6al498fRS.Yga45TFQQnxnQ0pKG','7828895179','A82CF951',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-06 11:10:53.944677',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (164,'Dinesh Anand','dineshanand123@gmail.com','\$2y\$10\$1MemBwEqzyTFhBv3hWrvj.fz73cqXkvwYADUEoglf7atYkxYUq2Le','9479733143','153321C5',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-06 16:14:15.864053',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (165,'AjarIshan Pathak','luckymascotji@gmail.com','\$2y\$10\$Rz2mkutkLh/7bXJYmhbHyO90308z68kxJW3tzfIUxU99ZMbnTQv/u','9407456663','31C0FD0C',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-07 17:25:00',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (168,'Shivam Dhakad','dhakadshivam094@gmail.com','\$2y\$10\$rhtka4mgHCr96UN1vOFRLev7n.0npj8P4waLo/vbIwDVuPhMWOGVW','9179777274','3DB3AD73',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-07 07:15:03.316087',NULL,0,'Bhopal',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (170,'Shivam Dhakad','dhakadshivam095@gmail.com','\$2y\$10\$/cqK6Y8r1mnQko8XlIevK.kQBiKscA.KKBOaxdQiYTpf.Xizw7HGy','9179777274','A4E4BF37',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-07 07:15:49.512415',NULL,0,'Bhopal',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (171,'ishant agrawal','ishant286@gmail.com','\$2y\$10\$2Rgt31YH84NHpWF1AMauoeT04XS88NKFqPkh004UAdP7cvo.4VNiO','9407168390','AF5147A3',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-07 07:20:33.6075',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,122),
        (172,'Gaurav Chauhan','chauhan.gaurav9754@gmail.com','\$2y\$10\$e66gSmkIxJfAPYS7Fnk3wuYaFmWTKWhohZ.0wF6FdidOjU6gKHENi','9754389322','5C91E542',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-07 08:53:39.311674',NULL,0,'INDORE',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (173,'Vijay rawat','veeraa1921@gmail.com','\$2y\$10\$N6u6JFfGjlfqjNpj.fdDtu/QIFqlfDGrJ4qUq15BXI1nOugJA3MYe','9893243806','5B36506F',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-08 06:21:38.606998',NULL,0,'Barwani',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (174,'VIJAY PRATAP','vpratap556@gmail.com','\$2y\$10\$eAfMWqql/JZWs9PfUxXWouKN1zEKanlMaBzcry/uganL3.gPu0Xni','9621538043','7CB9BF4D',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-08 06:42:39.106154',NULL,0,'Ghazipur District',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (178,'RANJEET PAWAR','RSP.7492@GMAIL.COM','\$2y\$10\$W0IzdVzUhTwt27O6YHh8OuvuQKz4e6hikYEJkDUSk05/oDFwNiFxW','7898221014','ED7A08AE',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-08 09:17:45.790741',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (181,'Manmeet singh bhatia','bhatiamonu958@gmail.com','\$2y\$10\$WKAOXJeKGzFZU/FPiV0gAe2pM.rw1E91v8p.i6SndutvKAMRJeT6u','9171970435','B0A3EB04',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-08 14:08:22.450916',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,'Madhya Pradesh',100),
        (184,'Balwant singh thakur','pratyushthakur555@gmail.com','\$2y\$10\$6VlHbWoNnBDdUPMcePiatO0o1ASuB3ySQbKsgyrUdhyC21QR5L166','9826042768','CA418D2D',115,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-08 15:23:28.957033',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (186,'Balwant singh thakur','anexalpbt06@gmail.com','\$2y\$10\$SkfsKMFEU01dBCVTjMdqPuiRZVZyxlPAj5TddDT2gCuafJieosoOa','9826042768','62042F9C',115,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-08 15:26:04.646459',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,122),
        (187,'bablu damar','babludamar1234@gmail.com','\$2y\$10\$kliqR1NZBrmgkN.I/oBTNOmn0CpC4pGDvVXfDRJpmdOYqqrM1H9CC','9644646653','458413EB',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-08 15:32:05.54346',NULL,0,'Dharad ratlam mp',0.00,NULL,NULL,NULL,NULL,NULL,106),
        (188,'Vikas Turkar','vikasturkar10@gmail.com','\$2y\$10\$SJwPTAgt5zAQdYkw8kbzW.0794bYYgNfQWBYrCidOzOaNQkrbzuni','9171241908','7DED0E29',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-09 06:41:46.54631',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (189,'Rishi Devda','rishidevda4545@gmail.com','\$2y\$10\$CpAx4gKm.U6.jKvidJXEe.e4Lt070eR2knXlsk.QdBzqmDrjV4X0G','9303684648','195AD123',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-09 08:34:17.955457',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (192,'Suhani rawat','suhanithakur2580@gmail.com','\$2y\$10\$6SIoCuAzKGlZNWQHP2/dCO8UVsuD.dXE7bHq/9fMAhNy0Vq41MBcq','8827013979','188FB177',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-09 15:29:00.756843',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (200,'Parmanand chouhan','himss13487@gmail.com','\$2y\$10\$T3MoWZEsKOsysx8GgLes9eXuRwlfR9Ilw3hGpTstLkHyo2PY1k8Y.','9926412026','9B882D8C',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-09 17:35:26.952623',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (203,'Nitin Solanki','nitinsolanki5885@gmail.com','\$2y\$10\$q6X0o.TJ/LdpDzZISNYKEudmkydqsT06uxhdw410BpqYaRSP0lMQm','8770955715','44656F5D',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-10 05:14:00.043112',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,205),
        (204,'Ashok Sisodiya','aashoksisodiya312@gmail.com','\$2y\$10\$2/4Q81bBjQSvUTFSZj7sZunqDX9l1T.rP7PyA3KzCfcRZr1kvdb.q','9343281241','DAD97C02',203,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-10 07:06:58.742358',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (206,'YASHVANT','yashwantgujrate@10gmail.com','\$2y\$10\$CqtCzy21Fkn2tv7GMEIbZuFXwtdabsUoV158bmpw3.Y.mSbWGzdgq','9617842256','BA51300A',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-10 11:08:11.062293',NULL,0,'SHAJAPUR',0.00,NULL,NULL,NULL,NULL,NULL,107)
        ON CONFLICT (id) DO NOTHING;
    ";

    $pdo->exec($sql);
    echo "<div class='success'>✅ Missing users (ID > 87) inserted.</div>";

    // Verify new count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $new_count = $stmt->fetchColumn();
    echo "<div class='info'>📊 Total users now: $new_count</div>";

    // ============================================================
    // 3. RESET SEQUENCES
    // ============================================================
    echo "<h2>🔄 Resetting sequences...</h2>";
    $tables = ['users', 'packages'];
    foreach ($tables as $table) {
        $seq = $pdo->query("SELECT pg_get_serial_sequence('$table', 'id')")->fetchColumn();
        if ($seq) {
            $max = $pdo->query("SELECT COALESCE(MAX(id), 0) FROM $table")->fetchColumn();
            $next = $max + 1;
            $pdo->exec("SELECT setval('$seq', $next, false)");
            echo "<div class='info'>✅ $seq set to $next</div>";
        }
    }

    echo "<div class='success'>🎉 All fixed! Now try:</div>";
    echo "<div class='info'>🔗 Admin panel: users should show $new_count</div>";
    echo "<div class='info'>🔗 Buy Subscription: packages should appear</div>";
    echo "<div class='info'>🔗 <a href='/' target='_blank'>Open Website</a></div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
