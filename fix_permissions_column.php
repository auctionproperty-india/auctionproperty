<?php
require_once 'db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions TEXT DEFAULT '{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_super_admin BOOLEAN DEFAULT FALSE");
    $pdo->exec("UPDATE users SET is_super_admin = TRUE WHERE email = 'admin@admin.com'");
    // अगर किसी यूजर के पास permissions खाली है तो डिफॉल्ट सेट करें
    $pdo->exec("UPDATE users SET permissions = '{\"properties\":true,\"users\":true,\"packages\":true,\"subscriptions\":true,\"settings\":true}' WHERE permissions IS NULL OR permissions = ''");
    echo "✅ Database columns verified and fixed! <br>";
    echo "✅ Super Admin (admin@admin.com) is set.<br>";
    echo "<a href='dashboard.php' class='btn btn-primary mt-3'>Go to Dashboard</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
