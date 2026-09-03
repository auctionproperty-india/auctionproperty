<?php
// ============================================================
// 🎰 Spin AJAX – One Spin, Coins + Upcoming Property (City-wise)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- Get user's city ----
$stmt = $pdo->prepare("SELECT city FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_city = $stmt->fetchColumn() ?: '';

// ---- Check spin eligibility ----
$spin_data = getSpinData($pdo, $user_id);
if (!$spin_data || $spin_data['spins_used'] >= 5) {
    echo json_encode(['success' => false, 'message' => 'You have used all spins today']);
    exit;
}

// ---- Perform spin ----
// Randomly decide reward: 70% coins, 30% property (or adjust)
$is_reward = (rand(1, 100) <= 70); // 70% coins, 30% property

$coins_earned = 0;
$property = null;
$show_property = false;

if ($is_reward) {
    // Coin reward: random between 1 and 10 (or based on your logic)
    $coins_earned = rand(1, 10);
    // Update user coins
    $stmt = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
    $stmt->execute([$coins_earned, $user_id]);
} else {
    // Property reward: find an upcoming property
    // First try user's city
    $sql = "SELECT * FROM properties 
            WHERE status = 'available' 
              AND auction_date > CURRENT_DATE 
              AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty')
              AND city ILIKE ? 
            ORDER BY price ASC 
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['%' . $user_city . '%']);
    $property = $stmt->fetch();

    // If not found, get any upcoming with lowest price
    if (!$property) {
        $sql = "SELECT * FROM properties 
                WHERE status = 'available' 
                  AND auction_date > CURRENT_DATE 
                  AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty')
                ORDER BY price ASC 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $property = $stmt->fetch();
    }

    if ($property) {
        $show_property = true;
        $coins_earned = 0; // no coins for property spin
        // Optionally give some coins too? We'll give 0 for property.
        // We can also give a small coin bonus, but keep it simple.
    } else {
        // If no property found, fallback to coin reward
        $coins_earned = rand(1, 5);
        $stmt = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
        $stmt->execute([$coins_earned, $user_id]);
        $is_reward = true;
        $show_property = false;
    }
}

// ---- Update spin usage ----
updateSpinUsage($pdo, $user_id);

// ---- Get updated spin data ----
$spin_data = getSpinData($pdo, $user_id);

// ---- Response ----
$response = [
    'success' => true,
    'is_reward' => $is_reward && !$show_property,
    'coins' => $coins_earned,
    'spins_used' => $spin_data['spins_used'],
    'total_coins_earned' => $spin_data['total_coins_earned'] ?? 0,
    'show_property' => $show_property,
];

if ($show_property && $property) {
    $response['property'] = [
        'id' => $property['id'],
        'title' => $property['title'],
        'price' => $property['price'],
        'bank_name' => $property['bank_name'] ?? 'Bank',
        'city' => $property['city'] ?? '',
        'type' => $property['type'] ?? '',
        'image_url' => $property['image_url'] ?? '',
        'auction_date' => $property['auction_date'] ?? '',
    ];
    $response['message'] = '🏠 You won a property!';
} else {
    $response['message'] = '🎉 You earned ' . $coins_earned . ' coins!';
}

echo json_encode($response);
