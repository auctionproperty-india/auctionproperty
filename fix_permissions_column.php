<?php
require_once 'db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions TEXT DEFAULT '{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_super_admin BOOLEAN DEFAULT FALSE");
    $pdo->exec("UPDATE users SET is_super_admin = TRUE WHERE email = 'admin@admin.com'");
    echo "✅ Done!";
} catch (Exception $e) { echo "❌ " . $e->getMessage(); }
?>
