<?php
require_once 'db.php';

echo "<h2>🔧 Fixing Accounting Entries (One-Time Script)</h2>";
echo "<p style='color:red; font-weight:bold;'>⚠️ This script will delete all existing 'Subscription' entries and recreate them based on active subscriptions.</p>";
echo "<p>Make sure you have a backup before proceeding.</p>";

// Simple confirmation to prevent accidental run
if(!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    echo "<p>To proceed, click: <a href='?confirm=yes' class='btn btn-danger'>Yes, I want to fix accounting</a></p>";
    exit;
}

try {
    // Step 1: Delete all existing accounting entries with category 'Subscription'
    $deleted = $pdo->exec("DELETE FROM account_entries WHERE category = 'Subscription'");
    echo "✅ Deleted $deleted old subscription entries.<br>";

    // Step 2: Fetch all active subscriptions (status = 'active')
    $subs = $pdo->query("SELECT s.*, u.name as user_name, p.name as pkg_name 
                         FROM subscriptions s 
                         JOIN users u ON s.user_id = u.id 
                         JOIN packages p ON s.package_id = p.id 
                         WHERE s.status = 'active'")->fetchAll();

    $count = 0;
    foreach ($subs as $sub) {
        $amount = (float)$sub['amount'];
        $description = "Subscription payment from user {$sub['user_name']} (ID: {$sub['user_id']}) for package {$sub['pkg_name']}";
        // Insert directly without using function (to avoid dependency)
        $stmt = $pdo->prepare("INSERT INTO account_entries (type, amount, description, category, entry_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['income', $amount, $description, 'Subscription', $sub['start_date']]);
        $count++;
    }

    echo "✅ Added $count new accounting entries with correct amounts.<br>";
    echo "<hr><h3 style='color:green;'>✅ All subscription accounting entries are now fixed!</h3>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Please delete this file (fix_accounting.php) immediately after running.</p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>
