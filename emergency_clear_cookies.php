<?php
// ============================================================
// 🚨 Emergency – Clear ALL Session Cookies (Stuck Impersonation Fix)
// ============================================================

// Set expire time to past for all possible session cookies
$cookies_to_clear = ['PRIMEPROP_SESS', 'IMPERSONATE', 'PHPSESSID', 'PRIMEPROP'];

foreach ($cookies_to_clear as $cookie_name) {
    if (isset($_COOKIE[$cookie_name])) {
        setcookie($cookie_name, '', time() - 3600, '/');
        setcookie($cookie_name, '', time() - 3600, '/', '', false, true);
        unset($_COOKIE[$cookie_name]);
    }
}

// Also destroy any active PHP session
session_start();
$_SESSION = [];
session_destroy();
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Show success message with link to login
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cookies Cleared</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f4f7fc; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            font-family: 'Inter', sans-serif; 
        }
        .box { 
            background: #fff; 
            border-radius: 20px; 
            padding: 40px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.1); 
            text-align: center; 
            max-width: 500px; 
        }
        .box h2 { color: #10b981; font-weight: 800; }
        .box p { color: #64748b; }
    </style>
</head>
<body>
    <div class="box">
        <h2>✅ All Cookies Cleared!</h2>
        <p>Your browser's session cookies have been completely removed. You can now login fresh.</p>
        <a href="login.php" class="btn btn-primary rounded-pill px-5 mt-3" style="background:#1e3a8a;">
            <i class="fas fa-sign-in-alt"></i> Go to Login
        </a>
        <p class="mt-3 small text-muted">You will be logged out. Please login as Admin again.</p>
    </div>
</body>
</html>
