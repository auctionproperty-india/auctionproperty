<?php
require_once 'db.php';

$user_id = 6; // ← Shani का User ID यहाँ डालें (जैसे 6)

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name, wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        die("User not found!");
    }

    // Zero the wallet
    $pdo->prepare("UPDATE users SET wallet_balance = 0 WHERE id = ?")->execute([$user_id]);
    echo "✅ Wallet for {$user['name']} (ID: {$user['id']}) has been set to 0. Previous balance: ₹" . indianCurrencyFormat($user['wallet_balance']) . "<br>";
    echo "⚠️ Delete this file now.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
