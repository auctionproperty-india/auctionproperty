<?php
require_once 'db.php';
try {
    // 1. पहले role कॉलम जोड़ें (अगर नहीं है तो)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'");
    
    // 2. आपके ईमेल वाले यूजर को Admin बनाएँ
    $pdo->exec("UPDATE users SET role = 'admin' WHERE email = 'bliveindia2018@gmail.com'");
    
    echo "✅ Database अपडेट हो गया! <br>";
    echo "✅ आप (bliveindia2018@gmail.com) अब Admin हैं। <br>";
    echo "<a href='dashboard.php'>Dashboard पर जाएँ</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
