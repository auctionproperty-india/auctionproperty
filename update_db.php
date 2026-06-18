<?php
require_once 'db.php';
try {
    // Role कॉलम जोड़ो (अगर नहीं है तो)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) DEFAULT 'user'");
    
    // पहले यूजर को Admin बना दो (अपने ईमेल से बदलो)
    $pdo->exec("UPDATE users SET role = 'admin' WHERE email = 'आपका_ईमेल_यहाँ_डालें'");
    
    echo "✅ Database अपडेट हो गया! अब आप Admin हैं।";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
