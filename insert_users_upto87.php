<?php
// ============================================================
// 📥 Insert Users (ID 1 to 87) - No Shell Required
// ============================================================

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres';
$password = getenv('DB_PASSWORD') ?: '';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Insert Users</title>
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
    <h1>📥 Insert Users (ID 1–87)</h1>";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='success'>✅ Database Connected: $dbname</div>";

    // ---------- Create users table if not exists ----------
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
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
    echo "<div class='success'>✅ Users table ready</div>";

    // ---------- Insert users (ID 1 to 87) ----------
    // We'll use a single INSERT with multiple rows
    $sql = "
        INSERT INTO users (id, name, email, password, phone, referral_code, referred_by, role, status, permissions, is_super_admin, otp_code, otp_expiry, created_at, activation_date, manual_referral_updated, city, wallet_balance, bank_name, account_number, ifsc, branch, state, coins) VALUES
        (1,'Devchand Mansare','mansaredevchand003@gmail.com','\$2y\$10\$GavTBoy0DX4kBAKZOy1wi.4d7NEUQ5hosf3W1RcJ4V9GYPXU88.Z2','9826092036','0B4ADAB3',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-23 06:44:00.864592',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (2,'Ram Jagtap','rjagtapg@gmail.com','\$2y\$10\$VOcZemmcE/G83oQ0S6zRiuVhxwRVg1lpwV/PN7dvFOUN0kOozSob.','9755215755','123E6B4B',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-23 06:51:35.061004',NULL,0,'indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (3,'asd','shankarmudra995@gmail.com','\$2y\$10\$PSwobIQDhjVLnSRI3F1iC.1BSEP/6RnGeYl87dcZ4QsGnrscTutzy','12','EDAE3E7A',NULL,'admin','active','{\"properties\":true,\"users\":false,\"packages\":false,\"subscriptions\":false,\"settings\":false}',0,NULL,NULL,'2026-06-18 10:39:08.711785',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (4,'Chayan Thakre','chayanthakre@gmail.com','\$2y\$10\$mZhLDtHUT2bjyLBt9yJYCujrWMKRov.DkgijEKKGY9C.GR2XWNt/a','8827555968','92E0B853',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-23 14:02:44.134903',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (5,'Admin User','admin@admin.com','\$2y\$10\$MQ4LnjH.aZpMyyqlVwAGbOJwErtzbifoKlJbg6z3z4hHn3QfpimHm','9999999999','41450692',NULL,'admin','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',1,NULL,NULL,'2026-06-18 12:07:58.946044',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (6,'Sachin Solanki','bliveindia2018@gmail.com','\$2y\$10\$3iI4qg6nVhYb1aWPBGbl1ehLgD8iPhcZTGztaZAhK98ohJRpDuXQW','8878190275','894F8BD5',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,'117199','2026-06-25 11:25:38','2026-06-18 13:01:00',NULL,0,'Indore',0.00,'sbi','30731161769','sbin0047034','iet','mp',1243),
        (7,'Shani Pathak','skshanipathak123@gmail.com','\$2y\$10\$DtKwf0UxnHnzI.8z8IVFu.gLCyYfj3IU6I.6q0GpoWoBIXGtAOVVu','6306136374','E469731D',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-24 05:09:28.773755',NULL,0,'Indore',0.00,'State Bank of India','41292717518','SBIN OOO 1688','Harraiya basti','Madhya Pradesh',3154),
        (9,'Sub admin','sub@admin.com','\$2y\$10\$7nBVusyqbyjCGpCD9Wib9.fZ8w6uS1770W3SoEyk4BIN4xmDPwq5q','','DB49D2AA',NULL,'admin','active','{\"properties\":{\"view\":true,\"edit\":true},\"users\":{\"view\":true,\"edit\":false},\"packages\":{\"view\":true,\"edit\":false},\"subscriptions\":{\"view\":true,\"edit\":false},\"settings\":{\"view\":false,\"edit\":false},\"referrals\":{\"view\":false,\"edit\":false},\"accounting\":{\"view\":false,\"edit\":false}}',0,NULL,NULL,'2026-06-19 13:36:56.30079',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (10,'Premlal Jagati','premlal01@gmail.com','\$2y\$10\$t8jQ96U98xSkJpukGafsHuLJ219Uy/HGIiLkojPszazuldBeLxkJG','09926070135','CA358811',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-20 04:44:08.834949',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (11,'Aadhar Patil','aadhar23patil@gmail.com','\$2y\$10\$M/Yuq.32Nf6zNpwjZ9nLUuytuiydJeNECVlsRMPVjEzmU8NQQ89ee','9039063442','1B05EA2C',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-20 04:55:49.739773',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (12,'Deepak karma','deepakkrma03@gmail.com','\$2y\$10\$jGYAi7CYIFs.2c8N8byp1OsOiaN9nQ8lIW5MVinaUHH4l.5qE0ZFG','7389327845','5D76B874',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-20 05:24:22.588089',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (13,'Mohan pawar','pawarmohan690@gmail.com','\$2y\$10\$uWSSsOWBjjSQJT7KXvUJAubdPWZmDRHgCGiKFyr61xx7zpKgi715W','9098687847','EC93715B',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-20 06:46:27.325168',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,110),
        (14,'Santosh Dhakse','santoshdhakse829@gmail.com','\$2y\$10\$rE9L.Yo6MSP/4lE78SW6oOv0Dwf3NSPfrSsDaLjDDS3ZUKyspBH3G','9893384365','AD8D3F73',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-20 10:32:25.13852',NULL,1,'',0.00,NULL,NULL,NULL,NULL,NULL,325),
        (15,'Mittarpal Singh','mittarpal1548@gmail.com','\$2y\$10\$KGUFhZ1CbLAclFd1NA2lleFBBcmVbnLO0IOudLwwzcwX2ImoY2Fm.','9878024439','3AE40F26',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-20 17:22:14.759787',NULL,1,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (16,'Sourabh Chavda','nidhichawda01@gmail.com','\$2y\$10\$0/RbxGfKM.dcfhAvnBxxr.MzJ3U9F0JZD0tPJYshvFo3Lp.jaROue','7879135200','10CAFB8C',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-20 18:53:24.156514',NULL,1,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (17,'Satyam kori','satyamkori2001@gmail.com','\$2y\$10\$Ejwy/p1ydJm48lJlkvlaUuu3kJekbb0QvJ/KzryRVfxUmaQhVLkXq','7869751863','8EFE0F88',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-21 07:54:10.066786',NULL,1,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (18,'Ajay Katija','ajjukingtigerboltehai@gmail.com','\$2y\$10\$TrXBSmJXYPYOFnHiCCM27eCm4I/vHtuyrt3XpQ994MfAmwsE7hmCC','7223000223','6BEE9E24',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,'390544','2026-07-12 17:57:49','2026-06-24 05:35:47.880479',NULL,1,'Indore',0.00,'State Bank Of India','35395375662','SBIN0030043','Badnawar','Madhya Pradesh',2223),
        (19,'Raviraj Chouhan','raviraj.chouhan24@gmail.com','\$2y\$10\$iD/EATIA3vTcseIPQN/G.eKX/vYftW4kDh4BRbjNeNkufpGDYeIIm','9754922021','BD8E8111',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-24 09:18:52.704298',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (20,'Anil Gupta','anilskyway1@gmail.com','\$2y\$10\$31kdu9QQ0qTgKoa8x4jGAuqlMiXgy7CoxeZSzke044yyO3mh8xLL.','8899210498','8F5DDF36',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-30 12:39:19.79649',NULL,1,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (21,'Shivkishor kumre','shivkishorkumre819@gmail.com','\$2y\$10\$uS/6Jqgr1PCHmnJfP3pW6./C8QhMpn/WDEeWMN4ANxGuCVe7YZbyu','8103023916','552030B9',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-06-30 17:22:54.091022',NULL,0,'Chhindwara',0.00,NULL,NULL,NULL,NULL,NULL,130),
        (22,'Santosh Choudhary','santoshji11221@gmail.com','\$2y\$10\$/xy/mifez359FQW96.Lr6eYun.80dEO9.EUdz1IPre/9AJLSGNnXS','9300011221','B7099744',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-01 09:20:33.860008',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (23,'Madan Mohan Dubey','madanmohandubey448@gmail.com','\$2y\$10\$81EKTBoJdF6jAm6yjGkU2.sZq434BexrmZu0y2/izpJhq8OKYqT3C','9424338518','3C7F3F9F',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-02 08:00:34.69163',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,200),
        (26,'Kirti Nayak','kirti7354@gmail.com','\$2y\$10\$nIh2KDzW/oJJl0iqp5N47.5SkkJmNE3zIqhSI9AZyXO3ds1BeeeTW','9243805776','90FD8DC9',23,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-02 08:04:58.797144',NULL,1,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (27,'Rakesh bhilware','rakeshkumarazad3@gmail.com','\$2y\$10\$rZHeGvEUcxBLP6fo.76uf.mkqp5iwQw1pD1gJAxI8VomuphqgePv6','9340515178','DCCA9ADE',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-02 08:18:24.271656',NULL,1,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (28,'Shiva Katiza','shivakatija1@gmail.com','\$2y\$10\$FGAQs8VwlBltBpjvaYDoTu/17UtpD3PLB2p4LFdroW5Y4rCvEMHEm','9770267410','32FE7A4C',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-02 09:11:25.192902',NULL,0,'Badnawar',0.00,NULL,NULL,NULL,NULL,NULL,120),
        (29,'Lokpal singh Parihar','lokpal.singh.parihar1976@gmail.com','\$2y\$10\$LPzS0S1iyfG4x1N7qi9OO.KQAaftmouHkSgnWWZEEdLeszoucbYgG','9009093515','2B579023',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-02 16:38:20.887035',NULL,0,'Indore',0.00,'Bank of Baroda','72950100008515','BARB0DBSANV','Sanwer Road','Madhyapradesh',106),
        (30,'Rohit badgotiya','rohitbadgotiya@gmail.com','\$2y\$10\$bSXcS3DYEANi93MePN3sNOMjH18bESivSAVXBwGEMCU0XNVp5FO9a','6266189535','521DA1B6',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 06:24:45.363755',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (32,'Basantilal Makwana','basntmakwana55@Gmail.com','\$2y\$10\$..PhrDj4Z2FdvyOJKD0R6OXZt7Fh1zXaWY2vtYrEv3eDcwcRwIzuy','7770959716','9F22746B',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 07:09:29.261073',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,120),
        (34,'Aadesh Chouhan','Chouhanaadesh90@gmail.com','\$2y\$10\$iqu0CJjgxL6KxdsOYz5ZbezYsbjqQG5YV/0DMPEhYfiYdF2dGu2D6','9993823339','F2A3256A',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 08:05:23.241845',NULL,0,'Saujon',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (36,'Aadesh Chouhan','croshni648@gmail.com','\$2y\$10\$zwJIow9f8nRaNOaypl/fUusINwIbh17xAkDR7yajvSd0SuOhDwQDy','9993823339','B28CE904',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 08:06:08.738044',NULL,0,'Saujon',0.00,NULL,NULL,NULL,NULL,NULL,120),
        (37,'Vijay Chouhan','vc91181@gmail.com','\$2y\$10\$FCJgDANcjU2enU1fIfNJyuaPfdZQVhgH67MVHHF2sf1Qhkz/f2.H6','9424032331','3B4E96D4',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 10:38:23.291813',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,250),
        (38,'Satyam','devil127v@gmail.com','\$2y\$10\$zFNFA5S3EF717oU.Vzomtu4C7z2wjbdpHrpiktgOZYhyS9Hnz.nHu','9575430494','F358CF5E',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 11:31:10.896316',NULL,0,'Indore , Gwalior',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (41,'Satyam','devil127c@gmail.com','\$2y\$10\$GZFcVUk81FJK1quF26zBCOKyYGrTxHl3oeUyrgENWM67WImo/2XVS','9575430494','DB71B97D',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 11:33:14.877799',NULL,0,'Indore , Gwalior',0.00,NULL,NULL,NULL,NULL,NULL,164),
        (42,'Ankit sen','ankitsen49596@gmail.com','\$2y\$10\$Ja8ICZweHHR3mk3vRXQHA.qeSS40A5SeliET9j3jKDGT0nPwAT3zO','7999724074','06F145E0',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 13:43:10.67067',NULL,0,'Bhopal',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (49,'Mayank Nigam','nigamji690@gmail.com','\$2y\$10\$/drCLkkZvdkgsLgT1ot4Eu4AWiByMS9DibVNScZoDrSh.x.0xsz3.','7985984690','62CB2B8A',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 14:12:33.771311',NULL,0,'Uttar Pradesh',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (51,'Kamlesh Chouhan','kamleshchouhan7771@gmail.com','\$2y\$10\$4MwkL7EUp/HBo7vmu6TsUe7dXEZUaFRXIkyVAG5otbkBZG4OT7uuW','8815677306','A1CC82E4',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 14:35:32.774873',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (52,'Vikas chouhan','radhegrty@gmail.com','\$2y\$10\$CULZWvo3zZvU15Q6f0T7W.fUVQ/FJqIv6lZZRSc2F1hTvK/2xgIWe','7416503208','6DE8AFBF',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 14:38:03.547348',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (53,'Aryan','aryansrivastava604@gmail.com','\$2y\$10\$6HS9qRtvn/5oL69kw5zZfuaHxodpXCC0wY2VhPXvRvWGIKwsblqfi','7985984690','1853CEC1',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 14:41:26.067782',NULL,0,'Uttar Pradesh',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (54,'Ram Shing','ramshing251990@gmail.com','\$2y\$10\$7G0xoFMyjIrBbGlLhWo7guOUOisbmT4E1ZDhwK0tvdJEBUlKX39hy','7426038423','D779A165',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 14:41:46.968545',NULL,0,'Ajmer',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (55,'Santosh chauhan','schauhanschauhan35@gmail.com','\$2y\$10\$vks9mVejy3WLNfCj3Vnfj.zLELmZ7PQa1W6eT9cLBzQD6n37rIhN.','9670215605','AC8C24AE',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 14:48:13.675735',NULL,0,'Basti',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (68,'Yash Sonwan','yashsonwan143@gmail.com','\$2y\$10\$5wrlseb3Kq9HZfep46MfIehPv12/yXf3ip0zyAro8GTub6TegWcWG','9179139994','77B232A0',NULL,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 15:04:23.751403',NULL,0,'Ujjain',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (69,'marquis manish yadav','manishyadav869071@gmail.com','\$2y\$10\$qB47Hqc8pUeWhPK5XfrZz.pz/uiO8gdxJoSEFVkyDNQGZhZRjHQHG','8005535080','7CA87657',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 15:10:49.769151',NULL,0,'baswada',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (70,'Shantilal Katija','katijashantilal2@gmail.com','\$2y\$10\$oAOGz2APTZy21.vBQvG4pe5MECoFuSd0qItCvE67UKyjNWdqDnSMa','9340108530','B4CCC2CA',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 15:15:39.578373',NULL,0,'Ratlam',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (71,'Chetan rawat','Chetanrawat662@gmail.com','\$2y\$10\$WxzdkgOub0OfGwhuZuEg6.dMfjJbGhQyqQcRMtlxkBlS89udtHSD6','8815674205','D8D0AB2B',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 15:21:07.972168',NULL,0,'Alirajpur',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (72,'Ram rajak','ramchandrasikarwar754@gmail.com','\$2y\$10\$BzQ4UOZ5giUjUYzAU/TkUuRWFA696.iUQmgzzehJkI7a2PN2tt5X.','9202612018','96D9396D',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-03 15:47:46.666927',NULL,0,'Gwalior',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (73,'KIRTI MANWANI','vickymanwani614@gmail.com','\$2y\$10\$mJUcQ1B5xjm0rEJnqG/prexuX1YRA3t3eb5toVFUFP4EFK1Z5fzg.','6350595703','81A7B0A1',6,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-04 06:31:31.144446',NULL,1,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (75,'Dilip katija','aadikatija@gmail.com','\$2y\$10\$h5fNoDVN4aGnJJEUtl/FHuzK0qSNrToTyGNuvUD1sk10xqiNCzA.i','9303582135','D7ADD42A',18,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-04 07:01:03.869374',NULL,0,'Badnawar',0.00,NULL,NULL,NULL,NULL,NULL,100),
        (87,'Manoj kurawle','indore07@gmail.com','\$2y\$10\$XvJhMcr5PZooktCq24.skuup9L/HFakZmY7B38oRPXkgv9Ix1c0t2','7771998320','85218CEE',7,'user','active','{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',0,NULL,NULL,'2026-07-04 09:43:48.569951',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100)
        ON CONFLICT (id) DO NOTHING;
    ";

    $pdo->exec($sql);
    echo "<div class='success'>✅ Users (ID 1–87) inserted successfully!</div>";

    // ---------- Verify ----------
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "<div class='info'>📊 Total users in database: $count</div>";

    echo "<hr>";
    echo "<div class='success'>🎉 Done! You can now <a href='/' target='_blank'>open your website</a> and login with admin@admin.com / admin123</div>";
    echo "<div class='info'>⚠️ Delete this file after use for security.</div>";

} catch (PDOException $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
</div>
</body>
</html>
