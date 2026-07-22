<?php
// ============================================================
// 🔧 Fix: Set activation_date for users with active subscriptions
// ============================================================

require_once __DIR__ . '/db.php';

try {
    $affected = 0;

    // Find all users with active subscription and start_date not null
    $stmt = $pdo->query("
        SELECT DISTINCT ON (user_id) user_id, start_date
        FROM subscriptions
        WHERE status = 'active' AND start_date IS NOT NULL
        ORDER BY user_id, id DESC
    ");
    $users_to_update = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update each user's activation_date
    foreach ($users_to_update as $row) {
        $update_stmt = $pdo->prepare("
            UPDATE users
            SET activation_date = ?
            WHERE id = ? AND activation_date IS NULL
        ");
        $update_stmt->execute([$row['start_date'], $row['user_id']]);
        $affected += $update_stmt->rowCount();
    }

    echo "✅ Updated $affected users' activation_date.<br>";
    echo "🎉 Done! You can now delete this file.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
