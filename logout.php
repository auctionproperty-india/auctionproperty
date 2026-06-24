<?php
// ============================================================
// ✅ Logout – Session Destroy + Cookies Clear
// ============================================================

// Session Start करें (अगर पहले से नहीं है)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// सारे Session Variables खाली करें
$_SESSION = array();

// अगर Session Cookie है, तो उसे Delete करें
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Session Destroy करें
session_destroy();

// Browser Cache Clear करने के लिए Headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// Login Page पर Redirect करें
header("Location: login.php");
exit;
?>
