<?php
// ============================================================
// ✅ यह फाइल ajaykatija143indian@gmail.com को System से Delete करेगी
// ⚠️ User का सारा Data (Subscriptions, Referrals, Wallet) भी Delete हो जाएगा
// ============================================================

require_once 'db.php';

$email = 'ajaykatija143indian@gmail.com';

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $user_id = $user['id'];
        
        // Delete all related data first (CASCADE will handle it, but we'll do it explicitly for safety)
        $pdo->prepare("DELETE FROM wallet_transactions WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM user_referral_earnings WHERE user_id = ? OR referred_user_id = ?")->execute([$user_id, $user_id]);
        $pdo->prepare("DELETE FROM subscriptions WHERE user_id = ?")->execute([$user_id]);
        
        // Finally delete the user
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        
        echo "✅ User <strong>{$email}</strong> (ID: {$user_id}) has been deleted successfully!<br>";
        echo "📝 Now you can register again with the same email.<br>";
        echo "<a href='register.php' class='btn btn-primary'>Go to Register</a>";
    } else {
        echo "❌ User <strong>{$email}</strong> not found in system.<br>";
        echo "<a href='register.php' class='btn btn-primary'>Go to Register</a>";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
