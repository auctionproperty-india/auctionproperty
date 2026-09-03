<?php
// ============================================================
// spin_ajax.php – Simple Spin (No Gold/Diamond, Only Coins/Property)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login']);
    exit;
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// ---- Get user city ----
$stmt = $pdo->prepare("SELECT city FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_city = $stmt->fetchColumn() ?: '';

// ---- Get current slot (based on time) ----
$hour = (int)date('H');
if ($hour < 8) $slot = 1;      // 12 AM – 8 AM
elseif ($hour < 14) $slot = 2; // 8 AM – 2 PM
else $slot = 3;                // 2 PM – 12 AM

// ---- Get or create spin record ----
$stmt = $pdo->prepare("SELECT spins_used, coins_earned FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
$stmt->execute([$user_id, $today, $slot]);
$data = $stmt->fetch();

if (!$data) {
    $stmt = $pdo->prepare("INSERT INTO user_spins (user_id, slot_date, slot_number, spins_used, coins_earned) VALUES (?, ?, ?, 0, 0)");
    $stmt->execute([$user_id, $today, $slot]);
    $spins_used = 0;
    $coins_earned = 0;
} else {
    $spins_used = (int)$data['spins_used'];
    $coins_earned = (int)$data['coins_earned'];
}

// ---- Check limit (max 5 spins per slot) ----
if ($spins_used >= 5) {
    echo json_encode([
        'success' => false,
        'message' => 'You have used all 5 spins for this slot.'
    ]);
    exit;
}

// ---- Random outcome: 70% chance coins, 30% chance property ----
$is_coins = (rand(1, 100) <= 70);

if ($is_coins) {
    // Coin amount: random 1–10
    $coin_amount = rand(1, 10);
    // Update user coins
    $stmt = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
    $stmt->execute([$coin_amount, $user_id]);
    
    $new_spins = $spins_used + 1;
    $new_coins = $coins_earned + $coin_amount;
    
    // Update spin record
    $stmt = $pdo->prepare("UPDATE user_spins SET spins_used = ?, coins_earned = ? WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$new_spins, $new_coins, $user_id, $today, $slot]);
    
    $response = [
        'success' => true,
        'spins_used' => $new_spins,
        'coins' => $coin_amount,
        'total_coins_earned' => $new_coins,
        'show_property' => false,
        'message' => '🎉 You earned ' . $coin_amount . ' coins!'
    ];
    
} else {
    // ---- Property reward ----
    // First: try user's city, upcoming, lowest price
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
        // Update spin record (no coins)
        $new_spins = $spins_used + 1;
        $new_coins = $coins_earned;
        $stmt = $pdo->prepare("UPDATE user_spins SET spins_used = ? WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
        $stmt->execute([$new_spins, $user_id, $today, $slot]);
        
        $response = [
            'success' => true,
            'spins_used' => $new_spins,
            'coins' => 0,
            'total_coins_earned' => $new_coins,
            'show_property' => true,
            'property' => [
                'id' => $property['id'],
                'title' => $property['title'],
                'price' => $property['price'],
                'bank_name' => $property['bank_name'] ?? 'Bank',
                'city' => $property['city'] ?? '',
                'type' => $property['type'] ?? '',
                'image_url' => $property['image_url'] ?? '',
                'auction_date' => $property['auction_date'] ?? '',
            ],
            'message' => '🏠 Check out this property!'
        ];
    } else {
        // No property available, fallback to coins
        $coin_amount = rand(1, 5);
        $stmt = $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
        $stmt->execute([$coin_amount, $user_id]);
        
        $new_spins = $spins_used + 1;
        $new_coins = $coins_earned + $coin_amount;
        $stmt = $pdo->prepare("UPDATE user_spins SET spins_used = ?, coins_earned = ? WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
        $stmt->execute([$new_spins, $new_coins, $user_id, $today, $slot]);
        
        $response = [
            'success' => true,
            'spins_used' => $new_spins,
            'coins' => $coin_amount,
            'total_coins_earned' => $new_coins,
            'show_property' => false,
            'message' => '🎉 You earned ' . $coin_amount . ' coins!'
        ];
    }
}

echo json_encode($response);
