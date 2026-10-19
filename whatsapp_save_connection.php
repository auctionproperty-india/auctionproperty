<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$code = $_POST['code'] ?? '';
$phone_id = $_POST['phone_number_id'] ?? '';
$waba_id = $_POST['waba_id'] ?? '';

if (empty($code) || empty($phone_id) || empty($waba_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']); exit;
}

// 🔥 Meta App Details (App Settings > Basic se lein)
$app_id = 'YOUR_META_APP_ID';           // Apna App ID daalein
$app_secret = 'YOUR_META_APP_SECRET';   // Apna App Secret daalein

// 1. Code ko Access Token me exchange karein
$url = "https://graph.facebook.com/v23.0/oauth/access_token?client_id={$app_id}&client_secret={$app_secret}&code={$code}";
$response = file_get_contents($url);
$tokenData = json_decode($response, true);

if (isset($tokenData['access_token'])) {
    $access_token = $tokenData['access_token'];
    
    // 2. Database me save karein (Supabase)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO whatsapp_settings (user_id, waba_id, phone_number_id, access_token, is_coexistence) 
            VALUES (?, ?, ?, ?, 1)
            ON CONFLICT (user_id) DO UPDATE 
            SET waba_id = EXCLUDED.waba_id, phone_number_id = EXCLUDED.phone_number_id, access_token = EXCLUDED.access_token
        ");
        $stmt->execute([$_SESSION['user_id'], $waba_id, $phone_id, $access_token]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Token exchange failed: ' . $response]);
}
?>
