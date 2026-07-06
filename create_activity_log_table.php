<?php
require_once 'db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_log (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        activity_type VARCHAR(50) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ user_activity_log table created!<br>";
    echo "⚠️ Delete this file now.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
