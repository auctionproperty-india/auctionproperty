<?php
require_once 'db.php';

try {
    $pdo->exec("ALTER TABLE user_referral_earnings ADD COLUMN IF NOT EXISTS utr_no VARCHAR(100) DEFAULT NULL");
    echo "✅ Column 'utr_no' added successfully!<br>";
    echo "⚠️ Delete this file now.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
