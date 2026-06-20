<?php
require_once 'db.php';
try {
    // 1. Packages Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id SERIAL PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        duration_months INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        discount_price DECIMAL(10,2) DEFAULT NULL
    )");
    
    // 2. Properties Table (सभी नए कॉलम्स के साथ)
    $pdo->exec("CREATE TABLE IF NOT EXISTS properties (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        location VARCHAR(255),
        city VARCHAR(100),
        state VARCHAR(100),
        type VARCHAR(50),
        google_location TEXT,
        image_url TEXT,
        bank_name VARCHAR(255),
        sqft DECIMAL(10,2),
        possession_type VARCHAR(50),
        auction_date DATE,
        borrower_name VARCHAR(255),
        emd_amount DECIMAL(10,2),
        bid_increment DECIMAL(10,2),
        emd_deadline VARCHAR(100),
        auction_start_time VARCHAR(100),
        auction_end_time VARCHAR(100),
        locality VARCHAR(255),
        reserve_price_per_sqft DECIMAL(10,2),
        contact_number VARCHAR(20),
        status VARCHAR(20) DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Users Table (Permissions & Super Admin के साथ)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password TEXT NOT NULL,
        phone VARCHAR(15),
        referral_code VARCHAR(20) UNIQUE NOT NULL,
        referred_by INT,
        role VARCHAR(20) DEFAULT 'user',
        status VARCHAR(20) DEFAULT 'active',
        permissions TEXT DEFAULT '{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}',
        is_super_admin BOOLEAN DEFAULT FALSE,
        otp_code VARCHAR(10),
        otp_expiry TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 4. Subscriptions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        package_id INT REFERENCES packages(id),
        property_id INT REFERENCES properties(id) ON DELETE SET NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(20) DEFAULT 'bank',
        utr VARCHAR(100) DEFAULT '',
        slip_path VARCHAR(255) DEFAULT '',
        status VARCHAR(20) DEFAULT 'pending',
        start_date DATE,
        end_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Settings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT NOT NULL
    )");
    
    // 6. Insert Default Packages (अगर खाली हैं)
    $count = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
    if($count == 0) {
        $pdo->exec("INSERT INTO packages (name, duration_months, price) VALUES 
            ('Silver', 1, 1500.00),
            ('Gold', 3, 3500.00),
            ('Platinum', 6, 6500.00),
            ('Diamond', 12, 11000.00)");
    }

    // 7. Insert Default Settings
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('default_contact', '9238215516') ON CONFLICT (setting_key) DO NOTHING");

    echo "✅ Database Setup Complete! All tables created successfully on Neon.tech.<br>";
    echo "✅ Default Packages and Settings inserted.<br>";
    echo "Now run <strong>setup_admin.php</strong> to create your admin account.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
