<?php
require_once 'db.php';
require_once 'functions.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$result = performSpin($pdo, $user_id);
echo json_encode($result);
?>
