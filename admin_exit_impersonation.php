<?php
// ============================================================
// 🚪 Exit Impersonation – Return to Admin
// ============================================================

// Start the IMPERSONATE session
session_name('IMPERSONATE');
session_start();

// Destroy it
$_SESSION = [];
session_destroy();

// Clear IMPERSONATE cookie
if (isset($_COOKIE['IMPERSONATE'])) {
    setcookie('IMPERSONATE', '', time() - 3600, '/');
}

// Redirect to users.php (admin session is still active in PRIMEPROP_SESS cookie)
header("Location: users.php");
exit;
?>
