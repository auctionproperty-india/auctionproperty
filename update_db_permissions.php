<?php
require_once 'db.php';
try {
    // Permissions column for sub-admin
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions TEXT DEFAULT '{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}'");
    // Super admin flag
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_super_admin BOOLEAN DEFAULT FALSE");
    // Make the main admin (admin@admin.com) the super admin
    $pdo->exec("UPDATE users SET is_super_admin = TRUE WHERE email = 'admin@admin.com'");
    
    echo "✅ Database updated successfully! <br>";
    echo "New columns: permissions, is_super_admin <br>";
    echo "<a href='admin_permissions.php' class='btn btn-primary mt-3'>Go to Manage Sub-Admins</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
