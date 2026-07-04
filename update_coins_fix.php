<?php
require_once 'db.php';

echo "<h2>🪙 Updating Coins for all existing users (PostgreSQL version)...</h2>";

try {
    // Step 1: Calculate referral counts per user
    $pdo->exec("
        WITH referral_counts AS (
            SELECT referred_by, COUNT(*) * 100 AS bonus
            FROM users
            WHERE referred_by IS NOT NULL
            GROUP BY referred_by
        )
        UPDATE users u
        SET coins = 100 + COALESCE((SELECT bonus FROM referral_counts WHERE referred_by = u.id), 0)
    ");
    echo "✅ All users updated with base 100 coins + referral bonuses.<br>";

    // Step 2: Count total coins
    $total = $pdo->query("SELECT SUM(coins) FROM users")->fetchColumn();
    echo "💰 Total coins in system: <strong>$total</strong><br>";

    // Step 3: Show top 10 coin holders
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
