<?php
// ============================================================
// 🚨 Emergency – Clear ALL Cookies AND Database Sessions
// ============================================================

require_once __DIR__ . '/db.php';

// 1. Clear ALL browser cookies
$cookies_to_clear = ['PRIMEPROP_SESS', 'IMPERSONATE', 'PHPSESSID', 'PRIMEPROP'];
foreach ($cookies_to_clear as $cookie_name) {
    if (isset($_COOKIE[$cookie_name])) {
        setcookie($cookie_name, '', time() - 3600, '/');
        setcookie($cookie_name, '', time() - 3600, '/', '', false, true);
        unset($_COOKIE[$cookie_name]);
    }
}

// 2. Clear ALL sessions from database
try {
    $pdo->exec("DELETE FROM sessions");
} catch (Exception $e) {
    // ignore
}

// 3. Destroy current PHP session
$_SESSION = [];
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Full Reset Done</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fc; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Inter', sans-serif; }
        .box { background: #fff; border-radius: 20px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
        .box h2 { color: #10b981; font-weight: 800; }
    </style>
</head>
<body>
    <div class="box">
        <h2>✅ Full Reset Complete!</h2>
        <p>All cookies and database sessions cleared. Please login fresh.</p>
        <a href="login.php" class="btn btn-primary rounded-pill px-5 mt-3" style="background:#1e3a8a;">
            Go to Login
        </a>
    </div>
</body>
</html>
