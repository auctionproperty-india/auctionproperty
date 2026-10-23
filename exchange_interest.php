<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$prop_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, user_id, title FROM user_properties WHERE id = ? AND listing_type='exchange' AND status='approved'");
$stmt->execute([$prop_id]);
$prop = $stmt->fetch();

if($prop && $prop['user_id'] != $user_id) {
    $chk = $pdo->prepare("SELECT id FROM exchange_notifications WHERE property_id=? AND interested_user_id=? AND created_at > NOW() - INTERVAL '1 day'");
    $chk->execute([$prop_id, $user_id]);
    
    if(!$chk->fetch()) {
        $ins = $pdo->prepare("INSERT INTO exchange_notifications (property_id, owner_user_id, interested_user_id) VALUES (?,?,?)");
        $ins->execute([$prop_id, $prop['user_id'], $user_id]);
    }
}
header("Location: my_notifications.php?sent=1");
exit;
