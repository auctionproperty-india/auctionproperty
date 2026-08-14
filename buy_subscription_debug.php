<?php
// ============================================================
// 🔍 DEBUG BUY SUBSCRIPTION – Session और Conditions जाँचें
// ============================================================

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ---- सबसे पहले Session डंप करें ----
echo "<pre style='background:#1e293b; color:#e2e8f0; padding:20px; border-radius:12px;'>";
echo "🔍 SESSION DUMP:\n";
print_r($_SESSION);
echo "</pre>";

// ---- फिर Conditions चेक करें ----
$user_id = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$user_id) {
    die("❌ Session में user_id नहीं है – आप लॉगिन नहीं हैं।");
}
if ($role == 'admin') {
    die("❌ आप Admin हैं – यह पेज केवल Users के लिए है।");
}

$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
echo "📦 Package ID: $package_id<br>";

// Package check
$pkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$pkg->execute([$package_id]);
$package = $pkg->fetch();
if (!$package) {
    die("❌ Package not found.");
}
echo "✅ Package found: " . $package['name'] . "<br>";

// Pending check
$pending = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'pending'");
$pending->execute([$user_id]);
if ($pending->rowCount() > 0) {
    die("⚠️ You have pending subscription – would redirect to user_packages.php?msg=already_pending");
}

// Active check
$active = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
$active->execute([$user_id]);
if ($active->rowCount() > 0) {
    die("⚠️ You have active subscription – would redirect to user_packages.php?msg=already_active");
}

echo "✅ All checks passed – आप आगे बढ़ सकते हैं।";
echo "<br><a href='buy_subscription.php?package_id=$package_id'>Refresh (now without debug)</a>";
exit;
?>
