<?php
// ============================================================
// 🔧 Fix: Set default for created_at in users table
// ============================================================

require_once __DIR__ . '/db.php';

try {
    // Set default value for created_at
    $pdo->exec("ALTER TABLE users ALTER COLUMN created_at SET DEFAULT NOW()");
    echo "✅ Default value for 'created_at' set to NOW() successfully.<br>";
} catch (PDOException $e) {
    // Ignore if already set
    echo "ℹ️ " . $e->getMessage() . " (if it says column already exists, it's fine)<br>";
}

// Update existing rows with NULL created_at
$affected = $pdo->exec("UPDATE users SET created_at = NOW() WHERE created_at IS NULL");
echo "✅ Updated $affected rows with NULL created_at to current time.<br>";

echo "🎉 Done! You can now delete this file.";
?>
