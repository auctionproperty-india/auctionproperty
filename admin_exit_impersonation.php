<?php
// ============================================================
// 🚪 Emergency Exit – Clear ALL Sessions & Cookies
// Use this if you are stuck in Impersonation Mode
// ============================================================

// Clear ALL possible session names
$session_names = ['PHPSESSID', 'IMPERSONATE', 'PRIMEPROP_SESS', 'PRIMEPROP'];

foreach ($session_names as $sname) {
    // Start and destroy each session
    session_name($sname);
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION = [];
    session_destroy();
    
    // Clear cookie
    if (isset($_COOKIE[$sname])) {
        setcookie($sname, '', time() - 3600, '/');
    }
}

// Redirect to login
header("Location: login.php?msg=session_cleared");
exit;
?>
