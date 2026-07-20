-- ============================================================
-- PostgreSQL Compatible Import
-- Complete Data from mysql_import.sql (Converted)
-- ============================================================

-- Drop all existing tables
DROP TABLE IF EXISTS user_referral_earnings CASCADE;
DROP TABLE IF EXISTS user_properties CASCADE;
DROP TABLE IF EXISTS support_tickets CASCADE;
DROP TABLE IF EXISTS kyc_documents CASCADE;
DROP TABLE IF EXISTS user_activity_log CASCADE;
DROP TABLE IF EXISTS user_spins CASCADE;
DROP TABLE IF EXISTS wallet_transactions CASCADE;
DROP TABLE IF EXISTS subscriptions CASCADE;
DROP TABLE IF EXISTS account_entries CASCADE;
DROP TABLE IF EXISTS packages CASCADE;
DROP TABLE IF EXISTS settings CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS properties CASCADE;

-- ============================================================
-- CREATE TABLES
-- ============================================================

-- Users Table
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
);

-- Properties Table
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
);

-- Packages Table
CREATE TABLE packages (
    id SERIAL PRIMARY KEY,
    name TEXT,
    duration_months INT,
    price DECIMAL(15,2),
    discount_price DECIMAL(15,2),
    referral_bonus DECIMAL(15,2),
    max_properties INT
);

-- Settings Table
CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    setting_key TEXT,
    setting_value TEXT
);

-- Subscriptions Table
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
);

-- Wallet Transactions Table
CREATE TABLE wallet_transactions (
    id SERIAL PRIMARY KEY,
    user_id INT,
    amount DECIMAL(15,2),
    type TEXT,
    description TEXT,
    reference_id INT,
    created_at TIMESTAMP
);

-- User Spins Table
CREATE TABLE user_spins (
    id SERIAL PRIMARY KEY,
    user_id INT,
    slot_date DATE,
    slot_number INT,
    spins_used INT DEFAULT 0,
    reward_given INT DEFAULT 0,
    last_spin_at TIMESTAMP,
    coins_earned INT DEFAULT 0
);

-- User Activity Log Table
CREATE TABLE user_activity_log (
    id SERIAL PRIMARY KEY,
    user_id INT,
    activity_type TEXT,
    details TEXT,
    ip_address TEXT,
    created_at TIMESTAMP
);

-- KYC Documents Table
CREATE TABLE kyc_documents (
    id SERIAL PRIMARY KEY,
    user_id INT,
    doc_type TEXT,
    file_path TEXT,
    status TEXT,
    uploaded_at TIMESTAMP
);

-- Support Tickets Table
CREATE TABLE support_tickets (
    id SERIAL PRIMARY KEY,
    user_id INT,
    subject TEXT,
    message TEXT,
    screenshot TEXT,
    status TEXT,
    created_at TIMESTAMP
);

-- User Properties Table
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
);

-- User Referral Earnings Table
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
);

-- Account Entries Table
CREATE TABLE account_entries (
    id SERIAL PRIMARY KEY,
    type TEXT,
    amount DECIMAL(15,2),
    description TEXT,
    category TEXT,
    entry_date DATE,
    created_at TIMESTAMP
);

-- ============================================================
-- INSERT DATA
-- ============================================================

-- Packages
INSERT INTO packages (id, name, duration_months, price, discount_price, referral_bonus, max_properties) VALUES
(1,'Silver',1,1500.00,1200.00,120.00,1),
(2,'Gold',3,3500.00,3000.00,500.00,3),
(3,'Platinum',6,6500.00,5500.00,1000.00,5),
(4,'Diamond',12,11000.00,9500.00,1500.00,10);

-- Settings
INSERT INTO settings (id, setting_key, setting_value) VALUES
(1,'default_contact','9238215516'),
(2,'company_bank_name','JANA SMAL FINANCE BANK'),
(3,'company_account_number','4553020001485256'),
(4,'company_ifsc','JSFB0004553'),
(5,'company_branch','Bhavarkua'),
(7,'tds_percent','2'),
(8,'admin_charge_percent','5'),
(9,'spin_min_coins','3'),
(10,'spin_max_coins','7'),
(6,'company_qr_code','uploads/qr_1783482437.jpeg');

-- Users
INSERT INTO users (id, name, email, password, phone, referral_code, referred_by, role, status, permissions, is_super_admin, otp_code, otp_expiry, created_at, activation_date, manual_referral_updated, city, wallet_balance, bank_name, account_number, ifsc, branch, state, coins) VALUES
(188,'Vikas Turkar','vikasturkar10@gmail.com','$2y$10$SJwPTAgt5zAQdYkw8kbzW.0794bYYgNfQWBYrCidOzOaNQkrbzuni','9171241908','7DED0E29',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-09 06:41:46.54631',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(174,'VIJAY PRATAP','vpratap556@gmail.com','$2y$10$eAfMWqql/JZWs9PfUxXWouKN1zEKanlMaBzcry/uganL3.gPu0Xni','9621538043','7CB9BF4D',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-08 06:42:39.106154',NULL,0,'Ghazipur District',0.00,NULL,NULL,NULL,NULL,NULL,100),
(178,'RANJEET PAWAR','RSP.7492@GMAIL.COM','$2y$10$W0IzdVzUhTwt27O6YHh8OuvuQKz4e6hikYEJkDUSk05/oDFwNiFxW','7898221014','ED7A08AE',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-08 09:17:45.790741',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(165,'AjarIshan Pathak','luckymascotji@gmail.com','$2y$10$Rz2mkutkLh/7bXJYmhbHyO90308z68kxJW3tzfIUxU99ZMbnTQv/u','9407456663','31C0FD0C',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-07 17:25:00',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(122,'Ritik rane','raneritik1@gmail.com','$2y$10$5IeDLIZOYk10RI.vJWjZwepwpCHOycT2Sf4y3ATMhFrbhbNAhR2UK','9691756963','0266B778',121,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 11:15:16.277037',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,'',112),
(123,'Tokir','himmuraikwar15@gmail.com','$2y$10$kn12IJ/cf3sWDjleSF1eHuulvFQ3sk.WBLVvvHJlSX4jP/mFNQrjy','6263796637','EA8E4F78',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 13:31:50.381177',NULL,0,'Amla',0.00,NULL,NULL,NULL,NULL,NULL,100),
(124,'Yogesh chouhan','kalabaic584@gmail.com','$2y$10$YQJpJjYWCE29/EZj/FmNN.gKgJlnpuooSlv1DFnYIQuq6HTEEo.P6','7024386861','FD23C163',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 13:41:19.972366',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(126,'Arun Rajput','thakurarun0322@gmail.com','$2y$10$EQXF2aeV9PkYhrYbHJDFde6YD4Jq5uaJoiXegxmWx8lhgNHGj7WUC','6261054212','BB5F91A0',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-06 02:24:00.808933',NULL,0,'Shajapur',0.00,NULL,NULL,NULL,NULL,NULL,100),
(184,'Balwant singh thakur','pratyushthakur555@gmail.com','$2y$10$6VlHbWoNnBDdUPMcePiatO0o1ASuB3ySQbKsgyrUdhyC21QR5L166','9826042768','CA418D2D',115,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-08 15:23:28.957033',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(189,'Rishi Devda','rishidevda4545@gmail.com','$2y$10$CpAx4gKm.U6.jKvidJXEe.e4Lt070eR2knXlsk.QdBzqmDrjV4X0G','9303684648','195AD123',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-09 08:34:17.955457',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(181,'Manmeet singh bhatia','bhatiamonu958@gmail.com','$2y$10$WKAOXJeKGzFZU/FPiV0gAe2pM.rw1E91v8p.i6SndutvKAMRJeT6u','9171970435','B0A3EB04',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-08 14:08:22.450916',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,'Madhya Pradesh',100),
(115,'Ghanshyam Verma','gv341447@gmail.com','$2y$10$cW/KrAYuVh.mGJ1T655VS.ilIxyEN96UGR4Rn92S0l/Nup831DCEC','9329188100','F2AE840F',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 08:17:17.409044',NULL,0,'INDORE',0.00,NULL,NULL,NULL,NULL,NULL,307),
(186,'Balwant singh thakur','anexalpbt06@gmail.com','$2y$10$SkfsKMFEU01dBCVTjMdqPuiRZVZyxlPAj5TddDT2gCuafJieosoOa','9826042768','62042F9C',115,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-08 15:26:04.646459',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,122),
(187,'bablu damar','babludamar1234@gmail.com','$2y$10$kliqR1NZBrmgkN.I/oBTNOmn0CpC4pGDvVXfDRJpmdOYqqrM1H9CC','9644646653','458413EB',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-08 15:32:05.54346',NULL,0,'Dharad ratlam mp',0.00,NULL,NULL,NULL,NULL,NULL,106),
(200,'Parmanand chouhan','himss13487@gmail.com','$2y$10$T3MoWZEsKOsysx8GgLes9eXuRwlfR9Ilw3hGpTstLkHyo2PY1k8Y.','9926412026','9B882D8C',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-09 17:35:26.952623',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(204,'Ashok Sisodiya','aashoksisodiya312@gmail.com','$2y$10$2/4Q81bBjQSvUTFSZj7sZunqDX9l1T.rP7PyA3KzCfcRZr1kvdb.q','9343281241','DAD97C02',203,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-10 07:06:58.742358',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(203,'Nitin Solanki','nitinsolanki5885@gmail.com','$2y$10$q6X0o.TJ/LdpDzZISNYKEudmkydqsT06uxhdw410BpqYaRSP0lMQm','8770955715','44656F5D',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-10 05:14:00.043112',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,205),
(206,'YASHVANT','yashwantgujrate@10gmail.com','$2y$10$CqtCzy21Fkn2tv7GMEIbZuFXwtdabsUoV158bmpw3.Y.mSbWGzdgq','9617842256','BA51300A',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-10 11:08:11.062293',NULL,0,'SHAJAPUR',0.00,NULL,NULL,NULL,NULL,NULL,107),
(192,'Suhani rawat','suhanithakur2580@gmail.com','$2y$10$6SIoCuAzKGlZNWQHP2/dCO8UVsuD.dXE7bHq/9fMAhNy0Vq41MBcq','8827013979','188FB177',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-09 15:29:00.756843',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(26,'Kirti Nayak','kirti7354@gmail.com','$2y$10$nIh2KDzW/oJJl0iqp5N47.5SkkJmNE3zIqhSI9AZyXO3ds1BeeeTW','9243805776','90FD8DC9',23,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-02 08:04:58.797144',NULL,1,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(118,'Ashok Kumar Namdev','mnamdev06@gmail.com','$2y$10$GbDw2BUpkxbAVcgP6db9lu6Qw6ZgbcPUO8Qf3sY/leJACbZyNbGPi','9460751600','93BDCAF3',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 08:32:37.606662',NULL,0,'Idore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(27,'Rakesh bhilware','rakeshkumarazad3@gmail.com','$2y$10$rZHeGvEUcxBLP6fo.76uf.mkqp5iwQw1pD1gJAxI8VomuphqgePv6','9340515178','DCCA9ADE',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-02 08:18:24.271656',NULL,1,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(30,'Rohit badgotiya','rohitbadgotiya@gmail.com','$2y$10$bSXcS3DYEANi93MePN3sNOMjH18bESivSAVXBwGEMCU0XNVp5FO9a','6266189535','521DA1B6',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 06:24:45.363755',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(20,'Anil Gupta','anilskyway1@gmail.com','$2y$10$31kdu9QQ0qTgKoa8x4jGAuqlMiXgy7CoxeZSzke044yyO3mh8xLL.','8899210498','8F5DDF36',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-30 12:39:19.79649',NULL,1,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(42,'Ankit sen','ankitsen49596@gmail.com','$2y$10$Ja8ICZweHHR3mk3vRXQHA.qeSS40A5SeliET9j3jKDGT0nPwAT3zO','7999724074','06F145E0',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 13:43:10.67067',NULL,0,'Bhopal',0.00,NULL,NULL,NULL,NULL,NULL,100),
(32,'Basantilal Makwana','basntmakwana55@Gmail.com','$2y$10$..PhrDj4Z2FdvyOJKD0R6OXZt7Fh1zXaWY2vtYrEv3eDcwcRwIzuy','7770959716','9F22746B',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 07:09:29.261073',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,120),
(34,'Aadesh Chouhan','Chouhanaadesh90@gmail.com','$2y$10$iqu0CJjgxL6KxdsOYz5ZbezYsbjqQG5YV/0DMPEhYfiYdF2dGu2D6','9993823339','F2A3256A',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 08:05:23.241845',NULL,0,'Saujon',0.00,NULL,NULL,NULL,NULL,NULL,100),
(121,'Badri Prasad Goyal','badriprasadgoyal@gmail.com','$2y$10$nvFA6kZlcQyAJffXZRIje.sWXeO0bQttSX6CCfMihAYLbU4gp3ehe','9926429503','6C3C4B7B',14,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 11:04:23.669362',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,200),
(125,'HARSH SINGH CHOUHAN','harshsingh01chouhan@gmail.com','$2y$10$SiGjRKrZFPSgsHZh7Ehaw.ckKIjJzLLX/tBhWksYb.nPaq1BAdBqW','7909791010','6339BE41',14,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 14:00:30.398385',NULL,0,'INDORE',0.00,NULL,NULL,NULL,NULL,NULL,100),
(29,'Lokpal singh Parihar','lokpal.singh.parihar1976@gmail.com','$2y$10$LPzS0S1iyfG4x1N7qi9OO.KQAaftmouHkSgnWWZEEdLeszoucbYgG','9009093515','2B579023',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-02 16:38:20.887035',NULL,0,'Indore',0.00,'Bank of Baroda','72950100008515','BARB0DBSANV','Sanwer Road','Madhyapradesh',106),
(49,'Mayank Nigam','nigamji690@gmail.com','$2y$10$/drCLkkZvdkgsLgT1ot4Eu4AWiByMS9DibVNScZoDrSh.x.0xsz3.','7985984690','62CB2B8A',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 14:12:33.771311',NULL,0,'Uttar Pradesh',0.00,NULL,NULL,NULL,NULL,NULL,100),
(28,'Shiva Katiza','shivakatija1@gmail.com','$2y$10$FGAQs8VwlBltBpjvaYDoTu/17UtpD3PLB2p4LFdroW5Y4rCvEMHEm','9770267410','32FE7A4C',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-02 09:11:25.192902',NULL,0,'Badnawar',0.00,NULL,NULL,NULL,NULL,NULL,120),
(18,'Ajay Katija','ajjukingtigerboltehai@gmail.com','$2y$10$TrXBSmJXYPYOFnHiCCM27eCm4I/vHtuyrt3XpQ994MfAmwsE7hmCC','7223000223','6BEE9E24',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,'390544','2026-07-12 17:57:49','2026-06-24 05:35:47.880479',NULL,1,'Indore',0.00,'State Bank Of India','35395375662','SBIN0030043','Badnawar','Madhya Pradesh',2223),
(134,'Nitin Arya','nitinarya1974@gmail.com','$2y$10$HzE53d46e7Pe1N9/l2oSqe.Qcayc9cNMWWj0EOXEwpHckKZFoK5Zq','6264949433','E9986433',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-06 10:25:19.446668',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(36,'Aadesh Chouhan','croshni648@gmail.com','$2y$10$zwJIow9f8nRaNOaypl/fUusINwIbh17xAkDR7yajvSd0SuOhDwQDy','9993823339','B28CE904',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 08:06:08.738044',NULL,0,'Saujon',0.00,NULL,NULL,NULL,NULL,NULL,120),
(21,'Shivkishor kumre','shivkishorkumre819@gmail.com','$2y$10$uS/6Jqgr1PCHmnJfP3pW6./C8QhMpn/WDEeWMN4ANxGuCVe7YZbyu','8103023916','552030B9',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-30 17:22:54.091022',NULL,0,'Chhindwara',0.00,NULL,NULL,NULL,NULL,NULL,130),
(12,'Deepak karma','deepakkrma03@gmail.com','$2y$10$jGYAi7CYIFs.2c8N8byp1OsOiaN9nQ8lIW5MVinaUHH4l.5qE0ZFG','7389327845','5D76B874',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-20 05:24:22.588089',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(5,'Admin User','admin@admin.com','$2y$10$MQ4LnjH.aZpMyyqlVwAGbOJwErtzbifoKlJbg6z3z4hHn3QfpimHm','9999999999','41450692',NULL,'admin','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',1,NULL,NULL,'2026-06-18 12:07:58.946044',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(3,'asd','shankarmudra995@gmail.com','$2y$10$PSwobIQDhjVLnSRI3F1iC.1BSEP/6RnGeYl87dcZ4QsGnrscTutzy','12','EDAE3E7A',NULL,'admin','active','{"properties":true,"users":false,"packages":false,"subscriptions":false,"settings":false}',0,NULL,NULL,'2026-06-18 10:39:08.711785',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(10,'Premlal Jagati','premlal01@gmail.com','$2y$10$t8jQ96U98xSkJpukGafsHuLJ219Uy/HGIiLkojPszazuldBeLxkJG','09926070135','CA358811',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-20 04:44:08.834949',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(13,'Mohan pawar','pawarmohan690@gmail.com','$2y$10$uWSSsOWBjjSQJT7KXvUJAubdPWZmDRHgCGiKFyr61xx7zpKgi715W','9098687847','EC93715B',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-20 06:46:27.325168',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,110),
(11,'Aadhar Patil','aadhar23patil@gmail.com','$2y$10$M/Yuq.32Nf6zNpwjZ9nLUuytuiydJeNECVlsRMPVjEzmU8NQQ89ee','9039063442','1B05EA2C',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-20 04:55:49.739773',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(9,'Sub admin','sub@admin.com','$2y$10$7nBVusyqbyjCGpCD9Wib9.fZ8w6uS1770W3SoEyk4BIN4xmDPwq5q','','DB49D2AA',NULL,'admin','active','{"properties":{"view":true,"edit":true},"users":{"view":true,"edit":false},"packages":{"view":true,"edit":false},"subscriptions":{"view":true,"edit":false},"settings":{"view":false,"edit":false},"referrals":{"view":false,"edit":false},"accounting":{"view":false,"edit":false}}',0,NULL,NULL,'2026-06-19 13:36:56.30079',NULL,0,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(17,'Satyam kori','satyamkori2001@gmail.com','$2y$10$Ejwy/p1ydJm48lJlkvlaUuu3kJekbb0QvJ/KzryRVfxUmaQhVLkXq','7869751863','8EFE0F88',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-21 07:54:10.066786',NULL,1,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(16,'Sourabh Chavda','nidhichawda01@gmail.com','$2y$10$0/RbxGfKM.dcfhAvnBxxr.MzJ3U9F0JZD0tPJYshvFo3Lp.jaROue','7879135200','10CAFB8C',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-20 18:53:24.156514',NULL,1,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(15,'Mittarpal Singh','mittarpal1548@gmail.com','$2y$10$KGUFhZ1CbLAclFd1NA2lleFBBcmVbnLO0IOudLwwzcwX2ImoY2Fm.','9878024439','3AE40F26',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-20 17:22:14.759787',NULL,1,'',0.00,NULL,NULL,NULL,NULL,NULL,100),
(1,'Devchand Mansare','mansaredevchand003@gmail.com','$2y$10$GavTBoy0DX4kBAKZOy1wi.4d7NEUQ5hosf3W1RcJ4V9GYPXU88.Z2','9826092036','0B4ADAB3',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-23 06:44:00.864592',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(2,'Ram Jagtap','rjagtapg@gmail.com','$2y$10$VOcZemmcE/G83oQ0S6zRiuVhxwRVg1lpwV/PN7dvFOUN0kOozSob.','9755215755','123E6B4B',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-23 06:51:35.061004',NULL,0,'indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(4,'Chayan Thakre','chayanthakre@gmail.com','$2y$10$mZhLDtHUT2bjyLBt9yJYCujrWMKRov.DkgijEKKGY9C.GR2XWNt/a','8827555968','92E0B853',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-23 14:02:44.134903',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(19,'Raviraj Chouhan','raviraj.chouhan24@gmail.com','$2y$10$iD/EATIA3vTcseIPQN/G.eKX/vYftW4kDh4BRbjNeNkufpGDYeIIm','9754922021','BD8E8111',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-24 09:18:52.704298',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(22,'Santosh Choudhary','santoshji11221@gmail.com','$2y$10$/xy/mifez359FQW96.Lr6eYun.80dEO9.EUdz1IPre/9AJLSGNnXS','9300011221','B7099744',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-01 09:20:33.860008',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(23,'Madan Mohan Dubey','madanmohandubey448@gmail.com','$2y$10$81EKTBoJdF6jAm6yjGkU2.sZq434BexrmZu0y2/izpJhq8OKYqT3C','9424338518','3C7F3F9F',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-02 08:00:34.69163',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,200),
(6,'Sachin Solanki','bliveindia2018@gmail.com','$2y$10$3iI4qg6nVhYb1aWPBGbl1ehLgD8iPhcZTGztaZAhK98ohJRpDuXQW','08878190275','894F8BD5',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,'117199','2026-06-25 11:25:38','2026-06-18 13:01:00',NULL,0,'Indore',0.00,'sbi','30731161769','sbin0047034','iet','mp',1243),
(14,'Santosh Dhakse','santoshdhakse829@gmail.com','$2y$10$rE9L.Yo6MSP/4lE78SW6oOv0Dwf3NSPfrSsDaLjDDS3ZUKyspBH3G','9893384365','AD8D3F73',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-20 10:32:25.13852',NULL,1,'',0.00,NULL,NULL,NULL,NULL,NULL,325),
(51,'Kamlesh Chouhan','kamleshchouhan7771@gmail.com','$2y$10$4MwkL7EUp/HBo7vmu6TsUe7dXEZUaFRXIkyVAG5otbkBZG4OT7uuW','8815677306','A1CC82E4',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 14:35:32.774873',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(38,'Satyam','devil127v@gmail.com','$2y$10$zFNFA5S3EF717oU.Vzomtu4C7z2wjbdpHrpiktgOZYhyS9Hnz.nHu','9575430494','F358CF5E',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 11:31:10.896316',NULL,0,'Indore , Gwalior',0.00,NULL,NULL,NULL,NULL,NULL,100),
(52,'Vikas chouhan','radhegrty@gmail.com','$2y$10$CULZWvo3zZvU15Q6f0T7W.fUVQ/FJqIv6lZZRSc2F1hTvK/2xgIWe','7416503208','6DE8AFBF',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 14:38:03.547348',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(53,'Aryan','aryansrivastava604@gmail.com','$2y$10$6HS9qRtvn/5oL69kw5zZfuaHxodpXCC0wY2VhPXvRvWGIKwsblqfi','7985984690','1853CEC1',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 14:41:26.067782',NULL,0,'Uttar Pradesh',0.00,NULL,NULL,NULL,NULL,NULL,100),
(54,'Ram Shing','ramshing251990@gmail.com','$2y$10$7G0xoFMyjIrBbGlLhWo7guOUOisbmT4E1ZDhwK0tvdJEBUlKX39hy','7426038423','D779A165',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 14:41:46.968545',NULL,0,'Ajmer',0.00,NULL,NULL,NULL,NULL,NULL,100),
(55,'Santosh chauhan','schauhanschauhan35@gmail.com','$2y$10$vks9mVejy3WLNfCj3Vnfj.zLELmZ7PQa1W6eT9cLBzQD6n37rIhN.','9670215605','AC8C24AE',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 14:48:13.675735',NULL,0,'Basti',0.00,NULL,NULL,NULL,NULL,NULL,100),
(68,'Yash Sonwan','yashsonwan143@gmail.com','$2y$10$5wrlseb3Kq9HZfep46MfIehPv12/yXf3ip0zyAro8GTub6TegWcWG','9179139994','77B232A0',NULL,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 15:04:23.751403',NULL,0,'Ujjain',0.00,NULL,NULL,NULL,NULL,NULL,100),
(69,'marquis manish yadav','manishyadav869071@gmail.com','$2y$10$qB47Hqc8pUeWhPK5XfrZz.pz/uiO8gdxJoSEFVkyDNQGZhZRjHQHG','8005535080','7CA87657',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 15:10:49.769151',NULL,0,'baswada',0.00,NULL,NULL,NULL,NULL,NULL,100),
(70,'Shantilal Katija','katijashantilal2@gmail.com','$2y$10$oAOGz2APTZy21.vBQvG4pe5MECoFuSd0qItCvE67UKyjNWdqDnSMa','9340108530','B4CCC2CA',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 15:15:39.578373',NULL,0,'Ratlam',0.00,NULL,NULL,NULL,NULL,NULL,100),
(71,'Chetan rawat','Chetanrawat662@gmail.com','$2y$10$WxzdkgOub0OfGwhuZuEg6.dMfjJbGhQyqQcRMtlxkBlS89udtHSD6','8815674205','D8D0AB2B',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 15:21:07.972168',NULL,0,'Alirajpur',0.00,NULL,NULL,NULL,NULL,NULL,100),
(72,'Ram rajak','ramchandrasikarwar754@gmail.com','$2y$10$BzQ4UOZ5giUjUYzAU/TkUuRWFA696.iUQmgzzehJkI7a2PN2tt5X.','9202612018','96D9396D',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 15:47:46.666927',NULL,0,'Gwalior',0.00,NULL,NULL,NULL,NULL,NULL,100),
(75,'Dilip katija','aadikatija@gmail.com','$2y$10$h5fNoDVN4aGnJJEUtl/FHuzK0qSNrToTyGNuvUD1sk10xqiNCzA.i','9303582135','D7ADD42A',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-04 07:01:03.869374',NULL,0,'Badnawar',0.00,NULL,NULL,NULL,NULL,NULL,100),
(87,'Manoj kurawle','indore07@gmail.com','$2y$10$XvJhMcr5PZooktCq24.skuup9L/HFakZmY7B38oRPXkgv9Ix1c0t2','7771998320','85218CEE',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-04 09:43:48.569951',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(97,'Tarun sahu','jiyatarun90@gmail.com','$2y$10$hwJ/Rdm/7TQOjEPhG10Dl.y3PncIEonfh91moVy5p81O3Wo9lE6Vy','6263797942','8C465E3A',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-04 14:06:27.669964',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(41,'Satyam','devil127c@gmail.com','$2y$10$GZFcVUk81FJK1quF26zBCOKyYGrTxHl3oeUyrgENWM67WImo/2XVS','9575430494','DB71B97D',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 11:33:14.877799',NULL,0,'Indore , Gwalior',0.00,NULL,NULL,NULL,NULL,NULL,164),
(7,'Shani Pathak','skshanipathak123@gmail.com','$2y$10$DtKwf0UxnHnzI.8z8IVFu.gLCyYfj3IU6I.6q0GpoWoBIXGtAOVVu','6306136374','E469731D',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-06-24 05:09:28.773755',NULL,0,'Indore',0.00,'State Bank of India','41292717518','SBIN OOO 1688','Harraiya basti','Madhya Pradesh',3154),
(100,'Jai Chakravedi','jchakravedi@gmail.com','$2y$10$coTMEbhZgGMUoY7NVR.deuj81b4.xuvyGZV2qU2goUTIdQSYDvHhW','9617544236','A929A195',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-04 15:28:51.074947',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(73,'KIRTI MANWANI','vickymanwani614@gmail.com','$2y$10$mJUcQ1B5xjm0rEJnqG/prexuX1YRA3t3eb5toVFUFP4EFK1Z5fzg.','6350595703','81A7B0A1',6,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-04 06:31:31.144446',NULL,1,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(37,'Vijay Chouhan','vc91181@gmail.com','$2y$10$FCJgDANcjU2enU1fIfNJyuaPfdZQVhgH67MVHHF2sf1Qhkz/f2.H6','9424032331','3B4E96D4',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-03 10:38:23.291813',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,250),
(101,'Vicky Khare','vickykhare177@gmail.com','$2y$10$Rc4/HGlK5FLhJsTbexqAJel.0mrq5IX86FGFBTqVC34QbwT4pVxoO','7489418887','7C6A8873',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 03:41:29.507094',NULL,0,'Mhow',0.00,NULL,NULL,NULL,NULL,NULL,106),
(104,'Yashwant Ahirwar','yashwantahirwar162@gmail.com','$2y$10$8DrKDWvo1lSv02E8LynKvOc8xNMzQcdy4pKy1r3sMak.dfCLnZtcu','9294879092','BDA42E04',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 05:45:50.36984',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(111,'Renuka Sharma','renukavinod0330@gmail.com','$2y$10$6E25fbFRSNkgrqq1mJnTcOSVDhNdpyxt4ODDcTxirqmxpUEDPbija','9329625275','REF_305E5658',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 06:32:11.508427',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(112,'Gayatri','gayatri.bajpai765@gmail.com','$2y$10$udNTiaHi/0E.Pc7NZcfeZORJ6bt66EofgqQwODWK1Q1vF6222amEK','9755878449','248DDD9B',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-05 07:08:48.397856',NULL,0,'Indy',0.00,NULL,NULL,NULL,NULL,NULL,100),
(158,'Vishal','vishalpure036@gmail.com','$2y$10$laub3Xk2OsJu35el0gsz1.J.4O6al498fRS.Yga45TFQQnxnQ0pKG','7828895179','A82CF951',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-06 11:10:53.944677',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(171,'ishant agrawal','ishant286@gmail.com','$2y$10$2Rgt31YH84NHpWF1AMauoeT04XS88NKFqPkh004UAdP7cvo.4VNiO','9407168390','AF5147A3',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-07 07:20:33.6075',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,122),
(164,'Dinesh Anand','dineshanand123@gmail.com','$2y$10$1MemBwEqzyTFhBv3hWrvj.fz73cqXkvwYADUEoglf7atYkxYUq2Le','9479733143','153321C5',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-06 16:14:15.864053',NULL,0,'Indore',0.00,NULL,NULL,NULL,NULL,NULL,100),
(168,'Shivam Dhakad','dhakadshivam094@gmail.com','$2y$10$rhtka4mgHCr96UN1vOFRLev7n.0npj8P4waLo/vbIwDVuPhMWOGVW','9179777274','3DB3AD73',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-07 07:15:03.316087',NULL,0,'Bhopal',0.00,NULL,NULL,NULL,NULL,NULL,100),
(170,'Shivam Dhakad','dhakadshivam095@gmail.com','$2y$10$/cqK6Y8r1mnQko8XlIevK.kQBiKscA.KKBOaxdQiYTpf.Xizw7HGy','9179777274','A4E4BF37',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-07 07:15:49.512415',NULL,0,'Bhopal',0.00,NULL,NULL,NULL,NULL,NULL,100),
(172,'Gaurav Chauhan','chauhan.gaurav9754@gmail.com','$2y$10$e66gSmkIxJfAPYS7Fnk3wuYaFmWTKWhohZ.0wF6FdidOjU6gKHENi','9754389322','5C91E542',18,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-07 08:53:39.311674',NULL,0,'INDORE',0.00,NULL,NULL,NULL,NULL,NULL,100),
(173,'Vijay rawat','veeraa1921@gmail.com','$2y$10$N6u6JFfGjlfqjNpj.fdDtu/QIFqlfDGrJ4qUq15BXI1nOugJA3MYe','9893243806','5B36506F',7,'user','active','{"properties":true,"users":true,"packages":true,"subscriptions":true,"settings":true}',0,NULL,NULL,'2026-07-08 06:21:38.606998',NULL,0,'Barwani',0.00,NULL,NULL,NULL,NULL,NULL,100);

-- Account Entries
INSERT INTO account_entries (id, type, amount, description, category, entry_date, created_at) VALUES
(3,'expense',500.00,'Airtel','wifi bill','2026-06-25','2026-06-25 11:41:32.164064'),
(5,'income',8000.00,'Subscription payment from user Anil Gupta (ID: 20) for package Diamond','Auction Subscription','2026-06-30','2026-07-02 17:53:17.705173'),
(7,'income',3000.00,'Subscription payment from AjarIshan Pathak (luckymascotji@gmail.com) for package Gold','Auction Subscription','2026-07-06','2026-07-07 03:01:06.221198'),
(8,'income',3000.00,'Manual subscription activation for user Dinesh Anand (ID: 164) for package Gold','Auction Subscription','2026-07-07','2026-07-07 04:20:10.577862'),
(9,'expense',465.00,'Referral payout to Shani Pathak (ID: 7) - Net ₹465 | UTR: MB77263096610377047 | Earning ID: 2','Referral Payout','2026-07-07','2026-07-07 05:00:09.684479'),
(10,'expense',1255.50,'Referral payout to Shani Pathak (ID: 7) - Net ₹1,255 | UTR: MB77263096610377047 | Earning ID: 1','Referral Payout','2026-07-07','2026-07-07 05:00:10.212475'),
(11,'expense',465.00,'Referral payout to Sachin Solanki (ID: 6) - Net ₹465 | UTR: MB77265364110378277 (Pay All)','Referral Payout','2026-07-07','2026-07-07 05:19:34.865765'),
(6,'income',1.00,'Subscription payment from Sachin Solanki (bliveindia2018@gmail.com) for package 1 (edited amount)','Auction Subscription','2026-07-05','2026-07-05 03:16:14.493655');

-- User Referral Earnings
INSERT INTO user_referral_earnings (id, user_id, referred_user_id, package_id, amount, tds_deducted, admin_charge_deducted, net_amount, status, created_at, paid_at, bank_name, account_number, ifsc_code, remarks, referred_activation_date, utr_no) VALUES
(1,7,20,4,1350.00,27.00,67.50,1255.50,'paid','2026-07-06 12:48:00.970116','2026-07-07 03:30:38.805144','State Bank of India','41292717518','SBIN OOO 1688',NULL,'2026-06-30','MB77263096610377047'),
(2,7,164,2,500.00,10.00,25.00,465.00,'paid','2026-07-06 16:31:44.879257','2026-07-07 03:30:39.328867','State Bank of India','41292717518','SBIN OOO 1688',NULL,'2026-06-06','MB77263096610377047'),
(3,6,165,2,500.00,10.00,25.00,465.00,'paid','2026-07-07 03:01:05.00066','2026-07-07 05:19:33.11262','sbi','30731161769','sbin0047034',NULL,NULL,'MB77265364110378277');

-- User Properties
INSERT INTO user_properties (id, user_id, title, description, price, city, state, type, image_url, status, admin_remarks, created_at, updated_at, sqft, construction_sqft) VALUES
(3,171,'1000 sqft plot for sale in Simrol near Indore IIT','In the Bansal Vihar colony in Simrol. 20*50=1000 Sqft plot for sale in the rate of 2500 rupee per sqft for more details call or WhatsApp on 9407168390',2500.00,'Indore','Madhya@Pradesh','Plot','','approved',NULL,'2026-07-07 07:24:43.942688','2026-07-07 07:24:43.942688',1000.00,0.00),
(2,73,'Rental residential building','Free hold Title clear Mahalaxmi nagar near Bombay Hospital Indore',30000000.00,'Indore','M.P','House','uploads/user_properties/userprop_73_1783351543.jpg','approved',NULL,'2026-07-06 15:25:43.398111','2026-07-06 15:25:43.398111',1500.00,3660.00),
(1,6,'property in tejaij nagar','plot area 400 sq feet 4 floor construction area 1630 sq feet contect - no. 8878190275',5500000.00,'indore','mp','House','uploads/user_properties/userprop_6_1783413945.jpeg','approved',NULL,'2026-07-03 11:08:38.520433','2026-07-07 08:45:46.146543',400.00,1630.00),
(4,200,'Premium house for sale at Ramji vatika 1','750 sqrft house double floor with tower room and latbath attach total construction area 2025 sqrft',8000000.00,'Indore','Madhyapradesh','Row House','uploads/user_properties/userprop_200_1783619107.jpg','pending',NULL,'2026-07-09 17:45:07.607418','2026-07-10 04:35:33.038718',750.00,2025.00);

-- Properties (Sample)
INSERT INTO properties (id, title, description, price, location, city, state, type, google_location, image_url, bank_name, sqft, possession_type, inspection_date, borrower_name, emd_amount, bid_increment, emd_deadline, auction_start_time, auction_end_time, locality, reserve_price_per_sqft, contact_number, status, created_at, auction_date) VALUES
(17,'Plot in Barwaha, Khargone','',1674400.00,'Plot No 52, Ward No 06, Nagar Palika- Barwaha, Mahaveer Ward, Nanda Marg South Side, Teh Barwaha Dist Khargone, M.P','Khargone','mp','Plot',NULL,NULL,'Aavas Financiers',990.00,'Physical',NULL,'',167440.00,0.00,'Wed, 22 Jul 2026 12:00 AM','Thu, 23 Jul 2026 11:00 AM','Thu, 23 Jul 2026 01:00 PM','Barwaha',0.00,'9238215516','available','2026-06-19 16:25:22.778819','2026-07-23'),
(16,'Flat in Pigdamber, Indore','',1541000.00,'flat no. 309, 3rd floor multi-storeyed building "Eden Garden" Block -A situated at village Pigdamber, Tehsil Mhow Dist. Indore MP','indore','mp','Flat','https://maps.app.goo.gl/1bSEcLg78U991ACd7?g_st=ac',NULL,'Bank of Baroda',678.00,'Physical',NULL,'',154100.00,10000.00,'Mon, 22 Jun 2026 06:00 PM','Mon, 22 Jun 2026 02:00 PM','Mon, 22 Jun 2026 06:00 PM','Rau',2272.00,'9238215516','available','2026-06-19 13:15:04.129808','2026-06-22');

-- ============================================================
-- VERIFICATION
-- ============================================================
SELECT '✅ Import completed successfully!' as status;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as total_properties FROM properties;
SELECT COUNT(*) as total_packages FROM packages;
