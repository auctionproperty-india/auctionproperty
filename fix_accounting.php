<?php
require_once 'db.php';
require_once 'functions.php';

// Only allow admin to run
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Access Denied");
}

echo "<h2>🔧 Fixing Accounting Entries...</h2>";

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
        $amount = (float)$sub['amount']; // This is the actual amount stored (user entered or admin edited)
        $description = "Subscription payment from user {$sub['user_name']} (ID: {$sub['user_id']}) for package {$sub['pkg_name']}";
        addAccountEntry($pdo, 'income', $amount, $description, 'Subscription', $sub['start_date']);
        $count++;
    }

    echo "✅ Added $count new accounting entries with correct amounts.<br>";
    echo "<hr><h3 style='color:green;'>✅ All subscription accounting entries are now fixed!</h3>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Please delete this file (fix_accounting.php) immediately.</p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Error: " . $e->getMessage() . "</h3>";
}
?>
