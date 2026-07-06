<?php
require_once 'db.php';
require_once 'functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, type, amount, category, description, entry_date FROM account_entries WHERE id = ?");
$stmt->execute([$id]);
$entry = $stmt->fetch();

if(!$entry) {
    http_response_code(404);
    echo json_encode(['error' => 'Entry not found']);
    exit;
}

header('Content-Type: application/json');
echo json_encode($entry);
exit;
?>
