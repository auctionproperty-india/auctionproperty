<?php
// ============================================================
// ✅ यह फाइल ajaykatija143indian@gmail.com को System से Delete करेगी
// ✅ Case-Insensitive Search (ILIKE) का उपयोग करेगी
// ============================================================

require_once 'db.php';

$email = 'ajaykatija143indian@gmail.com';

try {
    // Case-Insensitive Search (ILIKE)
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email ILIKE ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $user_id = $user['id'];
        
        // Delete all related data
        $pdo->prepare("DELETE FROM wallet_transactions WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM user_referral_earnings WHERE user_id = ? OR referred_user_id = ?")->execute([$user_id, $user_id]);
        $pdo->prepare("DELETE FROM subscriptions WHERE user_id = ?")->execute([$user_id]);
        
        // Finally delete the user
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        
        echo "✅ User <strong>{$email}</strong> (ID: {$user_id}) has been DELETED successfully!<br>";
        echo "📝 Now you can register again with the same email.<br>";
        echo "<a href='register.php' class='btn btn-primary'>Go to Register</a>";
    } else {
        echo "❌ User <strong>{$email}</strong> not found (Case-Insensitive Search).<br>";
        echo "🔍 Here are all users in the system:<br>";
        $all = $pdo->query("SELECT id, name, email FROM users ORDER BY id")->fetchAll();
        if (count($all) > 0) {
            echo "<ul>";
            foreach ($all as $u) {
                echo "<li><strong>ID:</strong> {$u['id']} | <strong>Name:</strong> {$u['name']} | <strong>Email:</strong> {$u['email']}</li>";
            }
            echo "</ul>";
            echo "If you see your email above with a different case, copy the exact ID and email and contact support.<br>";
        } else {
            echo "No users found at all!<br>";
        }
        echo "<a href='register.php' class='btn btn-primary'>Go to Register</a>";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
