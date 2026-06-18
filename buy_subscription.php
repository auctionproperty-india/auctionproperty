<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$package_id = $_POST['package_id'] ?? 0;

if(!$package_id) { header("Location: dashboard.php"); exit; }

$pkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$pkg->execute([$package_id]);
$pkg = $pkg->fetch();
if(!$pkg) { die("Invalid package"); }

// Insert pending subscription with NULL property_id
$stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, property_id, amount, payment_method, status) VALUES (?, ?, NULL, ?, 'bank', 'pending')");
$stmt->execute([$user_id, $package_id, $pkg['price']]);

header("Location: dashboard.php?msg=request_sent");
exit;
?>
