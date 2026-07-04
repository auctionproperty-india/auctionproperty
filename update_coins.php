<?php
require_once 'db.php';

echo "<h2>🪙 Updating Coins for all existing users...</h2>";

try {
    // Step 1: Give 100 base coins to every user, plus 100 per referral they have
    $sql = "UPDATE users u 
            SET coins = 100 + (SELECT COUNT(*) * 100 FROM users WHERE referred_by = u.id)";
    $pdo->exec($sql);
    echo "✅ All users updated with base 100 coins + referral bonuses.<br>";

    // Step 2: Count total coins
    $total = $pdo->query("SELECT SUM(coins) FROM users")->fetchColumn();
    echo "💰 Total coins in system: <strong>$total</strong><br>";

    // Step 3: Show a few users for verification
    $users = $pdo->query("SELECT id, name, email, coins, referral_code FROM users ORDER BY coins DESC LIMIT 10")->fetchAll();
    echo "<h4>Top 10 Coin Holders:</h4>";
    echo "<table border='1' cellpadding='8'><tr><th>Name</th><th>Email</th><th>Coins</th></tr>";
    foreach($users as $u) {
        echo "<tr><td>{$u['name']}</td><td>{$u['email']}</td><td>{$u['coins']}</td></tr>";
    }
    echo "</table>";

    echo "<hr><p style='color:red; font-weight:bold;'>⚠️ Delete this file immediately after running.</p>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
