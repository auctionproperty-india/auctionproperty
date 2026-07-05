<?php
// ---- (All existing functions like indianCurrencyFormat, subscription, permissions, referrals, accounting, wallet, social card, email remain exactly as before) ----
// To save space, I'm only including the updated spin functions.
// But in the final file, you need to keep all your existing functions.
// For brevity, I'll show only the spin-related functions that changed, but I will provide the complete file in the final answer.

// ---- 🌀 DAILY SPIN SYSTEM (Updated) ----

function getCurrentSlot() {
    $hour = (int)date('H');
    if ($hour >= 0 && $hour < 8) return 1;
    if ($hour >= 8 && $hour < 14) return 2;
    return 3;
}

function getSlotTimeRange($slot) {
    switch($slot) {
        case 1: return '12 AM – 8 AM';
        case 2: return '8 AM – 2 PM';
        case 3: return '2 PM – 12 AM';
        default: return 'Unknown';
    }
}

function getUserSpinData($pdo, $user_id, $slot = null) {
    if ($slot === null) $slot = getCurrentSlot();
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$user_id, $today, $slot]);
    $data = $stmt->fetch();
    if (!$data) {
        // Create new record with default 0 spins and 0 coins
        $stmt = $pdo->prepare("INSERT INTO user_spins (user_id, slot_date, slot_number, spins_used, reward_given, coins_earned) VALUES (?, ?, ?, 0, FALSE, 0)");
        $stmt->execute([$user_id, $today, $slot]);
        return ['spins_used' => 0, 'reward_given' => false, 'can_spin' => true, 'coins_earned' => 0];
    }
    return [
        'spins_used' => $data['spins_used'],
        'reward_given' => (bool)$data['reward_given'],
        'can_spin' => ($data['spins_used'] < 5),
        'coins_earned' => (int)$data['coins_earned'],
        'id' => $data['id']
    ];
}

function performSpin($pdo, $user_id) {
    $today = date('Y-m-d');
    $slot = getCurrentSlot();
    $stmt = $pdo->prepare("SELECT spins_used, reward_given, coins_earned FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$user_id, $today, $slot]);
    $data = $stmt->fetch();
    
    if (!$data) {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO user_spins (user_id, slot_date, slot_number, spins_used, reward_given, coins_earned) VALUES (?, ?, ?, 0, FALSE, 0)");
        $stmt->execute([$user_id, $today, $slot]);
        $spins_used = 0;
        $coins_earned = 0;
        $reward_given = false;
    } else {
        $spins_used = $data['spins_used'];
        $coins_earned = $data['coins_earned'];
        $reward_given = $data['reward_given'];
    }

    if ($spins_used >= 5) {
        return ['success' => false, 'message' => 'You have already used all spins for this slot.'];
    }

    // Determine random coin (1-4)
    $coin_amount = rand(1, 4);
    // Cap total coins per slot at 20
    $new_coins = $coins_earned + $coin_amount;
    if ($new_coins > 20) {
        $coin_amount = 20 - $coins_earned; // give remaining to reach 20
        if ($coin_amount < 1) $coin_amount = 0; // already 20
    }

    // Increment spins
    $new_spins = $spins_used + 1;
    $new_coins_earned = $coins_earned + $coin_amount;

    // Update DB
    $stmt = $pdo->prepare("UPDATE user_spins SET spins_used = ?, coins_earned = ?, last_spin_at = CURRENT_TIMESTAMP WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$new_spins, $new_coins_earned, $user_id, $today, $slot]);

    // Update user's total coins
    if ($coin_amount > 0) {
        $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$coin_amount, $user_id]);
    }

    $is_reward = ($new_spins == 5);
    if ($is_reward) {
        // Mark reward as given
        $pdo->prepare("UPDATE user_spins SET reward_given = TRUE WHERE user_id = ? AND slot_date = ? AND slot_number = ?")->execute([$user_id, $today, $slot]);
    }

    return [
        'success' => true,
        'message' => $coin_amount > 0 ? "🎉 +$coin_amount coins!" : "You've reached the max 20 coins for this slot!",
        'coins' => $coin_amount,
        'spins_used' => $new_spins,
        'reward_given' => $is_reward,
        'is_reward' => $is_reward,
        'total_coins_earned' => $new_coins_earned,
        'remaining_spins' => 5 - $new_spins
    ];
}

function getSlotStatus($pdo, $user_id, $slot) {
    $today = date('Y-m-d');
    $data = getUserSpinData($pdo, $user_id, $slot);
    $spins = $data['spins_used'];
    $coins = $data['coins_earned'];
    $is_current = ($slot == getCurrentSlot());
    $is_future = (!$is_current && ($slot > getCurrentSlot()));
    $is_past = (!$is_current && ($slot < getCurrentSlot()));

    $status = [];
    $status['slot'] = $slot;
    $status['time_range'] = getSlotTimeRange($slot);
    $status['spins_used'] = $spins;
    $status['coins_earned'] = $coins;
    $status['is_current'] = $is_current;
    $status['is_past'] = $is_past;
    $status['is_future'] = $is_future;
    $status['can_spin'] = $data['can_spin'] && $is_current;

    // Determine display message
    if ($is_past) {
        if ($spins > 0) {
            $status['message'] = "✅ Spins: $spins/5 | Coins: $coins";
            $status['label'] = 'claimed';
        } else {
            $status['message'] = "❌ Missed Reward";
            $status['label'] = 'missed';
        }
    } elseif ($is_current) {
        if ($spins == 0) {
            $status['message'] = "⏳ You haven't spun yet!";
            $status['label'] = 'ready';
        } elseif ($spins < 5) {
            $status['message'] = "🔄 $spins/5 spins used | $coins coins earned";
            $status['label'] = 'progress';
        } else {
            $status['message'] = "✅ Completed! Total coins: $coins";
            $status['label'] = 'done';
        }
    } else {
        // future slot
        $status['message'] = "⏳ Upcoming Slot";
        $status['label'] = 'upcoming';
    }
    return $status;
}
?>
