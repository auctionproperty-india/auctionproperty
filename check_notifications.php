<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetchColumn();
    echo json_encode(['count' => $count]);
} else {
    echo json_encode(['count' => 0]);
}
?>
