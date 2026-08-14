<?php
// ============================================================
// 🔍 DEBUG VERSION – Buy Subscription
// ============================================================

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "<pre style='background:#1e293b; color:#e2e8f0; padding:20px; border-radius:12px; font-size:14px;'>";
echo "🚀 DEBUGGING START\n\n";

// 1. Session Check
if (!isset($_SESSION['user_id'])) {
    echo "❌ Session user_id NOT SET → would redirect to login.php\n";
} else {
    echo "✅ Session user_id: " . $_SESSION['user_id'] . "\n";
    echo "✅ Session role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
}

// 2. Role Check
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    echo "❌ Role is admin → would redirect to login.php\n";
} else {
    echo "✅ Role is not admin.\n";
}

// 3. Package ID
$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
echo "📦 Package ID: $package_id\n";

if ($package_id == 0) {
    echo "❌ Package ID is 0 → would die with 'Invalid package selected.'\n";
} else {
    // Check if package exists
    $pkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
    $pkg->execute([$package_id]);
    $package = $pkg->fetch();
    if (!$package) {
        echo "❌ Package not found in database → would die\n";
    } else {
        echo "✅ Package found: " . $package['name'] . "\n";
    }
}

// 4. Pending subscription check
if (isset($_SESSION['user_id'])) {
    $pending_check = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'pending'");
    $pending_check->execute([$_SESSION['user_id']]);
    $pending_count = $pending_check->rowCount();
    if ($pending_count > 0) {
        echo "⚠️ You have $pending_count pending subscription(s) → would redirect to user_packages.php?msg=already_pending\n";
    } else {
        echo "✅ No pending subscriptions.\n";
    }

    // 5. Active subscription check
    $active_check = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
    $active_check->execute([$_SESSION['user_id']]);
    $active_count = $active_check->rowCount();
    if ($active_count > 0) {
        echo "⚠️ You have $active_count active subscription(s) → would redirect to user_packages.php?msg=already_active\n";
    } else {
        echo "✅ No active subscriptions.\n";
    }
}

echo "\n🔚 DEBUGGING END\n";
echo "</pre>";
exit;
?>
