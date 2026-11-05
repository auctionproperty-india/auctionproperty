<?php
// ============================================================
// 🚪 LOGOUT SCRIPT
// ============================================================
session_start();

// सारे सेशन वेरिएबल्स खाली करें
$_SESSION = array();

// सेशन कुकी को भी डिलीट करें
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// अब सेशन को पूरी तरह डिस्ट्रॉय करें
session_destroy();

// 🔥 यूजर को login.php की जगह index.php पर भेजें
header("Location: index.php");
exit;
?>
