<?php
require_once __DIR__ . '/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$city = trim($_POST['city'] ?? '');

if (empty($city)) {
    echo json_encode(['success' => false, 'message' => 'City name is required.']);
    exit;
}

try {
    // चेक करें कि पहले से फेवरेट है या नहीं
    $stmt = $pdo->prepare("SELECT id FROM user_favorite_cities WHERE user_id = ? AND city = ?");
    $stmt->execute([$user_id, $city]);
    
    if ($stmt->fetch()) {
        // अगर पहले से है, तो रिमूव कर दें (Toggle फीचर)
        $pdo->prepare("DELETE FROM user_favorite_cities WHERE user_id = ? AND city = ?")->execute([$user_id, $city]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => "$city removed from favorites."]);
    } else {
        // नहीं है तो ऐड कर दें
        $pdo->prepare("INSERT INTO user_favorite_cities (user_id, city) VALUES (?, ?)")->execute([$user_id, $city]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => "$city added to favorites!"]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>
