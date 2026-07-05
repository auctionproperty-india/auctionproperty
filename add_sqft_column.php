<?php
require_once 'db.php';

echo "<h2>🔧 Adding 'sqft' column to user_properties table...</h2>";

try {
    $pdo->exec("ALTER TABLE user_properties ADD COLUMN IF NOT EXISTS sqft DECIMAL(10,2) DEFAULT NULL");
    echo "✅ Column 'sqft' added successfully.<br>";
    echo "<hr><p style='color:green; font-weight:bold;'>Now you can add/edit customer properties with area.</p>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file now.</p>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
