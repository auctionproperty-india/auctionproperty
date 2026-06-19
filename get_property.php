<?php
require_once 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

$id = $_GET['id'] ?? 0;
if(!$id) {
    http_response_code(400);
    die(json_encode(['error' => 'Property ID required']));
}

$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$id]);
$property = $stmt->fetch();

if(!$property) {
    http_response_code(404);
    die(json_encode(['error' => 'Property not found']));
}

header('Content-Type: application/json');
echo json_encode($property);
?>
