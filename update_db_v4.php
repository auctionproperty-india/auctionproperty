<?php
require_once 'db.php';
try {
    // 1. New column: activation_date in users (to track when they first got active subscription)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS activation_date DATE");

    // 2. Update permissions column to new format (Read/Write structure)
    // Fetch all admin users
    $admins = $pdo->query("SELECT id, permissions FROM users WHERE role = 'admin'")->fetchAll();
    foreach($admins as $admin) {
        $perms = json_decode($admin['permissions'], true);
        if (is_array($perms) && !isset($perms['properties']['view'])) {
            // Convert old structure { "properties": true } to new { "properties": {"view": true, "edit": true} }
            $new_perms = [];
            $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals'];
            foreach ($modules as $mod) {
                $val = $perms[$mod] ?? false;
                $new_perms[$mod] = ['view' => (bool)$val, 'edit' => (bool)$val];
            }
            $pdo->prepare("UPDATE users SET permissions = ? WHERE id = ?")->execute([json_encode($new_perms), $admin['id']]);
        }
    }

    // 3. Set default permissions for any existing admin who might have null
    $pdo->exec("UPDATE users SET permissions = '{\"properties\":{\"view\":true,\"edit\":true},\"users\":{\"view\":true,\"edit\":true},\"packages\":{\"view\":true,\"edit\":true},\"subscriptions\":{\"view\":true,\"edit\":true},\"settings\":{\"view\":true,\"edit\":true},\"referrals\":{\"view\":true,\"edit\":true}}' WHERE role = 'admin' AND (permissions IS NULL OR permissions = '' OR permissions::text = '{}')");

    echo "✅ Database Updated to v4! <br>";
    echo "- Permissions structure upgraded to Read/Write levels.<br>";
    echo "- Activation date column added.<br>";
    echo "<a href='admin_permissions.php' class='btn btn-primary'>Go to Manage Sub-Admins (New UI)</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
