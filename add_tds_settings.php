<?php
require_once 'db.php';

try {
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('tds_percent', '10'), ('admin_charge_percent', '5') ON CONFLICT (setting_key) DO NOTHING");
    echo "✅ TDS and Admin Charge settings added!<br>";
    echo "⚠️ Delete this file now.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
