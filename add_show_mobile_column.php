<?php
require_once __DIR__ . '/db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS show_mobile BOOLEAN DEFAULT FALSE");
    echo "✅ Column 'show_mobile' added successfully!";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
