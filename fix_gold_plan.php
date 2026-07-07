<?php
require_once 'db.php';
require_once 'functions.php';

// User email
$email = 'dineshanand123@gmail.com';

// Package name we want to assign
$package_name = 'Gold'; // यदि 'Gold' नहीं है तो बदलें

echo "<h2>🔧 Fixing Plan for $email to $package_name</h2>";

try {
    // ---- Step 1: Get user ID ----
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        die("❌ User not found with email: $email");
    }
    $user_id = $user['id'];
    echo "✅ User found: {$user['name']} (ID: $user_id)<br>";

    // ---- Step 2: Get Gold package details ----
    $stmt = $pdo->prepare("SELECT id, name, price, duration_months FROM packages WHERE name ILIKE ?");
    $stmt->execute([$package_name]);
    $pkg = $stmt->fetch();
    if (!$pkg) {
        die("❌ Package '$package_name' not found. Please check the name.");
    }
    echo "✅ Package found: {$pkg['name']} (ID: {$pkg['id']}, Price: ₹{$pkg['price']}, Duration: {$pkg['duration_months']} months)<br>";

    // ---- Step 3: Check if user already has an active subscription for this package ----
    $stmt = $pdo->prepare("SELECT id, end_date FROM subscriptions WHERE user_id = ? AND package_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
    $stmt->execute([$user_id, $pkg['id']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // If already active, just extend by duration
        $new_end = date('Y-m-d', strtotime($existing['end_date'] . " + {$pkg['duration_months']} months"));
        $pdo->prepare("UPDATE subscriptions SET end_date = ? WHERE id = ?")->execute([$new_end, $existing['id']]);
        $sub_id = $existing['id'];
        echo "✅ Existing active subscription extended to $new_end.<br>";
    } else {
        // Insert new subscription
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime("+ {$pkg['duration_months']} months"));
        $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, amount, payment_method, status, start_date, end_date) VALUES (?, ?, ?, 'referral_bonus', 'active', ?, ?)");
        $stmt->execute([$user_id, $pkg['id'], $pkg['price'], $start, $end]);
        $sub_id = $pdo->lastInsertId();
        echo "✅ New Gold subscription created (ID: $sub_id) from $start to $end.<br>";
    }

    // ---- Step 4: Add Accounting Entry (Income) ----
    $description = "Manual subscription activation for user {$user['name']} (ID: $user_id) for package {$pkg['name']}";
    // Use the package price as amount
    $amount = $pkg['price'];
    $entry_date = date('Y-m-d');
    
    // Check if entry already exists to avoid duplicate? We'll add anyway.
    $added = addAccountEntry($pdo, 'income', $amount, $description, 'Auction Subscription', $entry_date);
    if ($added) {
        echo "✅ Accounting entry added: ₹" . indianCurrencyFormat($amount) . " as income.<br>";
    } else {
        echo "⚠️ Failed to add accounting entry. You may need to add manually.<br>";
    }

    echo "<hr><p style='color:green; font-weight:bold;'>✅ User $email is now on Gold plan, and accounting entry added.</p>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Delete this file now.</p>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
