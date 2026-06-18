<?php
require_once 'db.php';
try {
    // पुरानी टेबल हटाओ
    $pdo->exec("DROP TABLE IF EXISTS users CASCADE");
    
    // नई टेबल बनाओ
    $pdo->exec("CREATE TABLE users (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password TEXT NOT NULL,
        phone VARCHAR(15),
        referral_code VARCHAR(20) UNIQUE NOT NULL,
        is_admin BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "✅ 'users' टेबल सफलतापूर्वक बन गई!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
