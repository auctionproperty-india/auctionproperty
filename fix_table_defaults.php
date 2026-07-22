<?php
// ============================================================
// 🔧 Fix: Set default for created_at in users table
// ============================================================

require_once __DIR__ . '/db.php';

try {
    // Check if default already exists (optional, but we'll just set it)
    $pdo->exec("ALTER TABLE users ALTER COLUMN created_at SET DEFAULT NOW()");
    echo "✅ Default value for 'created_at' set to NOW() successfully.<br>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "ℹ️ If the column already has a default, this error can be ignored.";
}

// Also, for any existing rows where created_at is NULL, set to current time
$pdo->exec("UPDATE users SET created_at = NOW() WHERE created_at IS NULL");
echo "✅ Updated " . $pdo->rowCount() . " rows with NULL created_at to current time.<br>";

echo "🎉 Done! You can now delete this file.";
?>
