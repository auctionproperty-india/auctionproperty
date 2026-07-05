<?php
require_once 'db.php';

echo "<h2>🔧 Adding 'construction_sqft' column to user_properties table...</h2>";

try {
    $pdo->exec("ALTER TABLE user_properties ADD COLUMN IF NOT EXISTS construction_sqft DECIMAL(10,2) DEFAULT NULL");
    echo "✅ Column 'construction_sqft' added successfully.<br>";
    echo "<hr><p style='color:green; font-weight:bold;'>Now you can add/edit properties with construction area.</p>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file now.</p>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
