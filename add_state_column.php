<?php
require_once 'db.php';
try {
    $pdo->exec("ALTER TABLE properties ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT ''");
    echo "✅ 'state' column added successfully!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
