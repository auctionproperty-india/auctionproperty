<?php
require_once 'db.php';

try {
    // सभी Users जिनके पास Paid Referrals हैं – उनका Wallet 0 करें
    $stmt = $pdo->query("SELECT DISTINCT user_id FROM user_referral_earnings WHERE status = 'paid'");
    $users = $stmt->fetchAll();

    if (empty($users)) {
        echo "No users with paid earnings found.";
        exit;
    }

    $count = 0;
    foreach ($users as $u) {
        $uid = $u['user_id'];
        $update = $pdo->prepare("UPDATE users SET wallet_balance = 0 WHERE id = ?");
        $update->execute([$uid]);
        $count++;
    }

    echo "✅ Wallet balance set to 0 for <strong>$count</strong> users who have paid earnings.<br>";
    echo "⚠️ <strong>Delete this file now.</strong>";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
