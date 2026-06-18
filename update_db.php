<?php
require_once 'db.php';
try {
    // 1. Users Table में Role और Status कॉलम जोड़ें
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'");
    
    // 2. Properties Table बनाएँ (अगर नहीं है)
    $pdo->exec("CREATE TABLE IF NOT EXISTS properties (
        id SERIAL PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        location VARCHAR(255),
        image_url TEXT,
        status VARCHAR(20) DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 3. आपको Admin बनाएँ (आपका ईमेल पहले से सेट है)
    $pdo->exec("UPDATE users SET role = 'admin', status = 'active' WHERE email = 'bliveindia2018@gmail.com'");
    
    echo "✅ Database पूरी तरह सेट हो गया! <br>";
    echo "✅ आप (bliveindia2018@gmail.com) अब Admin हैं। <br>";
    echo "<a href='dashboard.php' class='btn btn-primary mt-3'>Dashboard पर जाएँ</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
