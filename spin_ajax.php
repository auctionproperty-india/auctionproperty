<?php
// ============================================================
// spin_ajax.php – Complete with Admin Settings Support
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ----- Admin settings -----
$enabled = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_special_enabled'")->fetchColumn() ?: 1;
$gold_triggers = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_gold_triggers'")->fetchColumn() ?: '1,3,5';
$diamond_triggers = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_diamond_triggers'")->fetchColumn() ?: '2,4';

$gold_numbers = array_filter(array_map('trim', explode(',', $gold_triggers)));
$diamond_numbers = array_filter(array_map('trim', explode(',', $diamond_triggers)));

// ----- Current slot -----
$slot = getCurrentSlot();
$today = date('Y-m-d');

$stmt = $pdo->prepare("SELECT spins_used FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
$stmt->execute([$user_id, $today, $slot]);
$row = $stmt->fetch();
$spins_used = $row ? (int)$row['spins_used'] : 0;

if ($spins_used >= 5) {
    echo json_encode(['success' => false, 'message' => 'All spins used for this slot']);
    exit;
}

$next_spin_number = $spins_used + 1;

// Determine reward type and target segment
$reward_type = 'property';
$targetSegment = null;

if ($enabled) {
    if (in_array($next_spin_number, $diamond_numbers)) {
        $reward_type = 'diamond';
        $targetSegment = 4; // DIAMOND index
    } elseif (in_array($next_spin_number, $gold_numbers)) {
        $reward_type = 'gold';
        $targetSegment = 0; // GOLD index
    }
}

if ($reward_type === 'property') {
    // Avoid Gold (0) and Diamond (4)
    $available = [1,2,3,5,6,7];
    $targetSegment = $available[array_rand($available)];
}

// ----- Perform spin with custom target segment -----
function doSpin($pdo, $user_id, $slot, $targetSegment, $reward_type) {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT spins_used, coins_earned FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$user_id, $today, $slot]);
    $data = $stmt->fetch();

    if (!$data) {
        // Insert if not exists
        $stmt = $pdo->prepare("INSERT INTO user_spins (user_id, slot_date, slot_number, spins_used, coins_earned) VALUES (?, ?, ?, 0, 0)");
        $stmt->execute([$user_id, $today, $slot]);
        $spins_used = 0;
        $coins_earned = 0;
    } else {
        $spins_used = (int)$data['spins_used'];
        $coins_earned = (int)$data['coins_earned'];
    }

    // Coin reward (if not special, we still give coins)
    $coin_settings = getSpinCoinSettings($pdo);
    $min = $coin_settings['min'];
    $max = $coin_settings['max'];
    $cap_per_slot = 22;

    $coin_amount = rand((int)$min, (int)$max);
    if ($coins_earned + $coin_amount > $cap_per_slot) {
        $coin_amount = max(0, $cap_per_slot - $coins_earned);
    }

    $new_spins = $spins_used + 1;
    $new_coins = $coins_earned + $coin_amount;

    // Update user_spins
    $stmt = $pdo->prepare("UPDATE user_spins SET spins_used = ?, coins_earned = ?, last_spin_at = NOW() WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$new_spins, $new_coins, $user_id, $today, $slot]);

    // Credit total coins
    if ($coin_amount > 0) {
        $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$coin_amount, $user_id]);
    }

    $is_reward = ($new_spins == 5);
    if ($is_reward) {
        $pdo->prepare("UPDATE user_spins SET reward_given = TRUE WHERE user_id = ? AND slot_date = ? AND slot_number = ?")->execute([$user_id, $today, $slot]);
    }

    // Prepare response
    $response = [
        'success' => true,
        'spins_used' => $new_spins,
        'coins' => $coin_amount,
        'total_coins_earned' => $new_coins,
        'is_reward' => $is_reward,
        'reward_type' => $reward_type,
        'target_segment' => $targetSegment
    ];

    // If property, fetch a random property (but we already have target segment)
    // For property, we still need to show a property in modal if not special
    if ($reward_type === 'property') {
        // Get a random low-price property (upcoming auction)
        $prop = getRandomLowPriceProperty($pdo);
        if ($prop) {
            $response['show_property'] = true;
            $response['property'] = $prop;
        } else {
            $response['show_property'] = false;
        }
    } else {
        // Gold or Diamond – no property modal
        $response['show_property'] = false;
    }

    return $response;
}

// Execute spin
$result = doSpin($pdo, $user_id, $slot, $targetSegment, $reward_type);

// Also include the target segment in the response for the frontend to rotate correctly
$result['target_segment'] = $targetSegment;

echo json_encode($result);
