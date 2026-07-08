<?php
require_once 'db.php';

try {
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES 
        ('spin_min_coins', '3'),
        ('spin_max_coins', '7')
        ON CONFLICT (setting_key) DO NOTHING");
    echo "✅ Spin coin settings added!<br>";
    echo "⚠️ Delete this file now.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
