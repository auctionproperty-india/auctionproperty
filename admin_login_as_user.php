<?php
// ============================================================
// 🕵️ Admin – Login as User (Impersonation)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Admin access required. Please login as admin first.");
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['name'] ?? 'Admin';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    die("Invalid User ID.");
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

// Close the current admin session (PHPSESSID)
session_write_close();

// Start a new session with a different name (IMPERSONATE)
session_name('IMPERSONATE');
session_start();

// Set user session variables (same as normal login)
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['name'] = $user['name'];
$_SESSION['email'] = $user['email'];
$_SESSION['phone'] = $user['phone'];
// Add any other session variables your app uses (e.g., referral_code, etc.)

// Store admin info for the banner
$_SESSION['impersonate_admin_id'] = $admin_id;
$_SESSION['impersonate_admin_name'] = $admin_name;

// Redirect to user dashboard
header("Location: dashboard.php");
exit;
?>
