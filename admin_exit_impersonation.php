<?php
// ============================================================
// 🚪 Exit Impersonation – Return to Admin
// ============================================================

$imp_session_id = $_GET['imp_session'] ?? null;

// Destroy the impersonate session
if ($imp_session_id) {
    $imp_session_id = preg_replace('/[^a-f0-9]/', '', $imp_session_id);
    
    if (strlen($imp_session_id) >= 32) {
        ini_set('session.use_cookies', 0);
        ini_set('session.use_only_cookies', 0);
        session_name('IMPERSONATE');
        session_id($imp_session_id);
        session_start();
        
        // Delete from DB
        require_once __DIR__ . '/db.php';
        try {
            if (isset($pdo)) {
                $pdo->prepare("DELETE FROM sessions WHERE id = ?")->execute([$imp_session_id]);
            }
        } catch (Exception $e) {}
        
        $_SESSION = [];
        session_destroy();
    }
}

// Clear any stale IMPERSONATE cookie
if (isset($_COOKIE['IMPERSONATE'])) {
    setcookie('IMPERSONATE', '', time() - 3600, '/');
    unset($_COOKIE['IMPERSONATE']);
}

// Redirect to admin panel (admin cookie still intact)
header("Location: users.php");
exit;
?>
