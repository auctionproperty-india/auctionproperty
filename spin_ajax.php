<?php
// ============================================================
// spin_ajax.php – Complete with Admin Settings & Next Slot Time
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// ----- Helper: Get next slot start time -----
function getNextSlotStartTime() {
    $hour = (int)date('H');
    if ($hour < 8) {
        // next slot is 8 AM today
        return date('Y-m-d 08:00:00');
    } elseif ($hour < 14) {
        // next slot is 2 PM today
        return date('Y-m-d 14:00:00');
    } else {
        // next slot is 12 AM (midnight) tomorrow
        return date('Y-m-d 00:00:00', strtotime('+1 day'));
    }
}

// ----- Admin settings -----
$enabled = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_special_enabled'")->fetchColumn() ?: 1;
$gold_triggers = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_gold_triggers'")->fetchColumn() ?: '1,3,5';
$diamond_triggers = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'spin_diamond_triggers'")->fetchColumn() ?: '2,4';

$gold_numbers = array_filter(array_map('trim', explode(',', $gold_triggers)));
$diamond_numbers = array_filter(array_map('trim', explode(',', $diamond_triggers)));

// ----- Current slot -----
$slot = getCurrentSlot();
$today = date('Y-m-d');

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

if ($spins_used >= 5) {
    $next_time = getNextSlotStartTime();
    $formatted_time = date('h:i A', strtotime($next_time));
    echo json_encode([
        'success' => false,
        'message' => "All spins used for this slot. Next slot available at <strong>{$formatted_time}</strong>",
        'next_slot_time' => $next_time
    ]);
    exit;
}

$next_spin_number = $spins_used + 1;
$reward_type = 'property';
$targetSegment = null;

if ($enabled) {
    if (in_array($next_spin_number, $diamond_numbers)) {
        $reward_type = 'diamond';
        $targetSegment = 4;
    } elseif (in_array($next_spin_number, $gold_numbers)) {
        $reward_type = 'gold';
        $targetSegment = 0;
    }
}

if ($reward_type === 'property') {
    $available = [1,2,3,5,6,7];
    $targetSegment = $available[array_rand($available)];
}

// ----- Perform spin -----
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

$stmt = $pdo->prepare("UPDATE user_spins SET spins_used = ?, coins_earned = ?, last_spin_at = NOW() WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
$stmt->execute([$new_spins, $new_coins, $user_id, $today, $slot]);

if ($coin_amount > 0) {
    $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$coin_amount, $user_id]);
}

$is_reward = ($new_spins == 5);
if ($is_reward) {
    $pdo->prepare("UPDATE user_spins SET reward_given = TRUE WHERE user_id = ? AND slot_date = ? AND slot_number = ?")->execute([$user_id, $today, $slot]);
}

$response = [
    'success' => true,
    'spins_used' => $new_spins,
    'coins' => $coin_amount,
    'total_coins_earned' => $new_coins,
    'is_reward' => $is_reward,
    'reward_type' => $reward_type,
    'target_segment' => $targetSegment
];

if ($reward_type === 'property') {
    $prop = getRandomLowPriceProperty($pdo);
    if ($prop) {
        $response['show_property'] = true;
        $response['property'] = $prop;
    } else {
        $response['show_property'] = false;
    }
} else {
    $response['show_property'] = false;
}

// If spins are exhausted now, add next slot time
if ($new_spins >= 5) {
    $next_time = getNextSlotStartTime();
    $response['next_slot_time'] = $next_time;
    $response['message'] = "All spins used for this slot. Next slot available at " . date('h:i A', strtotime($next_time));
}

echo json_encode($response);
