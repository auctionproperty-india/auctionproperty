<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }
if(!hasPermission('subscriptions', $pdo)) { die("You do not have permission to access this page."); }
// ... बाकी पुराना कोड वैसा ही ...
?>
