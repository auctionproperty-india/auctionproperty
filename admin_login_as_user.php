<?php
// ============================================================
// 🕵️ Admin – Login as User (URL-based Session)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Must be logged in as admin (from main PRIMEPROP_SESS cookie)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("❌ Admin access required. Please login as admin first.");
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    die("❌ Invalid User ID.");
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ User not found.");
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'] ?? 'Admin';

// Close the admin session (don't destroy it)
session_write_close();

// Create NEW impersonate session
ini_set('session.use_cookies', 0);
ini_set('session.use_only_cookies', 0);
ini_set('session.use_trans_sid', 0);

session_name('IMPERSONATE');
$new_session_id = bin2hex(random_bytes(24)); // 48 hex chars
session_id($new_session_id);
session_start();

$_SESSION['user_id']  = $user['id'];
$_SESSION['role']     = $user['role'];
$_SESSION['name']     = $user['name'];
$_SESSION['email']    = $user['email'];
$_SESSION['phone']    = $user['phone'] ?? '';
$_SESSION['impersonate_admin_id'] = $admin_id;
$_SESSION['impersonate_admin_name'] = $admin_name;

session_write_close();

// Redirect with session ID in URL
header("Location: user_dashboard.php?imp_session=" . $new_session_id);
exit;
?>
