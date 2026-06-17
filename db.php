<?php
// रेंडर PostgreSQL कनेक्शन स्ट्रिंग
$database_url = "postgresql://admin:JYJZAvIWxQymTwDzCN4lWZo3LdAOqNWM@dpg-d8ok6lflk1mc739ce1j0-a.oregon-postgres.render.com/auction_db_r1hx";

$dbopts = parse_url($database_url);

$host = $dbopts["host"];
$port = isset($dbopts["port"]) ? $dbopts["port"] : "5432"; 
$user = $dbopts["user"];
$pass = $dbopts["pass"];
$dbname = ltrim($dbopts["path"], '/');

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn = $pdo; 

    // 1. यूज़र्स, एडमिन और सब-एडमिन की कंबाइंड टेबल
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'user', 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // 2. प्रोडक्ट्स / प्रॉपर्टी टेबल (जो एडमिन या सब-एडमिन डालेंगे)
    $conn->exec("CREATE TABLE IF NOT EXISTS products (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        start_price NUMERIC(10, 2) NOT NULL,
        current_price NUMERIC(10, 2) NOT NULL,
        image_url VARCHAR(255),
        end_time TIMESTAMP NOT NULL,
        added_by INT REFERENCES users(id) ON DELETE SET NULL, 
        status VARCHAR(20) DEFAULT 'visible', 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // 3. बिड्स टेबल (बोली लगाने के लिए)
    $conn->exec("CREATE TABLE IF NOT EXISTS bids (
        id SERIAL PRIMARY KEY,
        product_id INT REFERENCES products(id) ON DELETE CASCADE,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        bid_amount NUMERIC(10, 2) NOT NULL,
        bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // 4. पासवर्ड रीसेट टेबल
    $conn->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id SERIAL PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        otp VARCHAR(6) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");

    // 🔥 जादुई लाइन: यह admin@test.com को डेटाबेस में एडमिन रोल पर सेट रखेगी
    $conn->exec("UPDATE users SET role = 'admin' WHERE email = 'admin@test.com';");

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
