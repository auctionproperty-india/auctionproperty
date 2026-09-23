<?php
// ============================================================
// 🚪 Logout – Properly destroys BOTH admin and impersonate sessions
// ============================================================

// 1. First, destroy the IMPERSONATE session if URL has imp_session
if (isset($_GET['imp_session']) && !empty($_GET['imp_session'])) {
    $imp_id = preg_replace('/[^a-f0-9]/', '', $_GET['imp_session']);
    if (strlen($imp_id) >= 32) {
        ini_set('session.use_cookies', 0);
        ini_set('session.use_only_cookies', 0);
        session_name('IMPERSONATE');
        session_id($imp_id);
        session_start();
        
        // Delete from DB
        try {
            global $pdo;
            if (isset($pdo)) {
                $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$imp_id]);
            }
        } catch (Exception $e) {}
        
        $_SESSION = [];
        session_destroy();
    }
}

// 2. Now load db.php to get the CORRECT session (PRIMEPROP_SESS)
require_once __DIR__ . '/db.php';

// 3. Clear all session data
$_SESSION = [];

// 4. Destroy the PRIMEPROP_SESS session from DB
$session_id = session_id();
if ($session_id) {
    try {
        $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$session_id]);
    } catch (Exception $e) {}
}

// 5. Destroy PHP session
session_destroy();

// 6. Clear ALL cookies (both admin and impersonate)
$cookies_to_clear = ['PRIMEPROP_SESS', 'IMPERSONATE', 'PHPSESSID', 'PRIMEPROP'];
foreach ($cookies_to_clear as $cookie_name) {
    setcookie($cookie_name, '', time() - 3600, '/');
    setcookie($cookie_name, '', time() - 3600, '/', '', false, true);
    unset($_COOKIE[$cookie_name]);
}

// 7. Redirect to login
header("Location: login.php");
exit;
?>
