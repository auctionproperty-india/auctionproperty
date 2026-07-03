<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? 0;
if($id) {
    $pdo->prepare("DELETE FROM user_properties WHERE id = ? AND user_id = ?")->execute([$id, $user_id]);
}
header("Location: user_properties.php");
exit;
?>
