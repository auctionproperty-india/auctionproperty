<?php
// ============================================================
// 🕵️ Admin – Login as User (Safe Impersonation)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Must be logged in as admin (in main session)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("❌ Admin access required. Please login as admin first.");
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    die("❌ Invalid User ID.");
}

// Fetch the user to impersonate
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ User not found.");
}

// Save the current admin's identity
$admin_backup = [
    'user_id' => $_SESSION['user_id'],
    'role'    => $_SESSION['role'],
    'name'    => $_SESSION['name'] ?? 'Admin',
    'email'   => $_SESSION['email'] ?? '',
];

// Close the admin session
session_write_close();

// Clear any old IMPERSONATE session
session_name('IMPERSONATE');
session_start();
$_SESSION = [];
session_destroy();

if (isset($_COOKIE['IMPERSONATE'])) {
    setcookie('IMPERSONATE', '', time() - 3600, '/');
}

// Start fresh IMPERSONATE session
session_name('IMPERSONATE');
session_start();

// Set user session data
$_SESSION['user_id']  = $user['id'];
$_SESSION['role']     = $user['role'];
$_SESSION['name']     = $user['name'];
$_SESSION['email']    = $user['email'];
$_SESSION['phone']    = $user['phone'] ?? '';

// Store admin info for banner and exit button
$_SESSION['impersonate_admin'] = $admin_backup;

// Redirect to user dashboard with imp=1 flag
header("Location: user_dashboard.php?imp=1");
exit;
?>
