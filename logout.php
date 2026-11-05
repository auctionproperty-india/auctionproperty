<?php
// ============================================================
// 🚪 LOGOUT SCRIPT (Force Clean)
// ============================================================
session_start();

// 1. सेशन वेरिएबल्स खाली करें
$_SESSION = array();

// 2. सेशन कुकी को डिलीट करें
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. सेशन डिस्ट्रॉय करें
session_destroy();

// 4. यूजर को पब्लिक होम पेज (index.php) पर भेजें
header("Location: index.php");
exit;
?>
