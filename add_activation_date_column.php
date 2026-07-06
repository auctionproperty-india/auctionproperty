<?php
require_once 'db.php';

try {
    $pdo->exec("ALTER TABLE user_referral_earnings ADD COLUMN IF NOT EXISTS referred_activation_date DATE DEFAULT NULL");
    echo "✅ Column 'referred_activation_date' added successfully!<br>";
    echo "⚠️ Delete this file now.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
