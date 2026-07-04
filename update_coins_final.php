<?php
require_once 'db.php';

echo "<h2>🪙 Adding coins column and updating all users...</h2>";

try {
    // ---- Step 1: Check if column exists, add if not ----
    $check = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='users' AND column_name='coins'");
    if($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN coins INT DEFAULT 0");
        echo "✅ Column 'coins' added.<br>";
    } else {
        echo "✅ Column 'coins' already exists.<br>";
    }

    // ---- Step 2: Update coins for all users ----
    // PostgreSQL compatible update using CTE
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

    // ---- Step 3: Total coins ----
    $total = $pdo->query("SELECT SUM(coins) FROM users")->fetchColumn();
    echo "💰 Total coins in system: <strong>$total</strong><br>";

    // ---- Step 4: Top 10 holders ----
    $users = $pdo->query("SELECT id, name, email, coins FROM users ORDER BY coins DESC LIMIT 10")->fetchAll();
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
