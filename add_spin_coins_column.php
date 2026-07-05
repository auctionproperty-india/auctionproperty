<?php
require_once 'db.php';

try {
    $pdo->exec("ALTER TABLE user_spins ADD COLUMN IF NOT EXISTS coins_earned INT DEFAULT 0");
    echo "✅ Column 'coins_earned' added successfully!<br>";
    echo "⚠️ Delete this file now.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
