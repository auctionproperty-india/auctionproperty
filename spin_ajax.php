<?php
require_once 'db.php';
require_once 'functions.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $result = performSpin($pdo, $user_id);

    // Log activity only if function exists and spin succeeded
    if ($result['success'] && function_exists('logActivity')) {
        $details = "Slot: " . getCurrentSlot() . ", Spins: " . ($result['spins_used'] ?? 0) . ", Coins: " . ($result['coins'] ?? 0);
        logActivity($pdo, $user_id, 'spin', $details);
    }

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
