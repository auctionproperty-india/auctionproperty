<?php
require_once 'db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_spins (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        slot_date DATE NOT NULL,
        slot_number INT NOT NULL, -- 1, 2, or 3
        spins_used INT DEFAULT 0, -- 0 to 5
        reward_given BOOLEAN DEFAULT FALSE,
        last_spin_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, slot_date, slot_number)
    )");
    echo "✅ user_spins table created successfully!<br>";
    echo "⚠️ Delete this file now.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
