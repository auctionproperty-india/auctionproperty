<?php
// ============================================================
// 🚪 Exit Impersonation – Return to Admin
// ============================================================

$imp_session_id = $_GET['imp_session'] ?? null;

if ($imp_session_id) {
    ini_set('session.use_cookies', 0);
    ini_set('session.use_only_cookies', 0);
    session_name('IMPERSONATE');
    session_id(preg_replace('/[^a-f0-9]/', '', $imp_session_id));
    session_start();
    $_SESSION = [];
    session_destroy();
}

// Redirect back to admin panel (admin cookie is still intact)
header("Location: users.php");
exit;
?>
