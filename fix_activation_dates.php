<?php
// ============================================================
// 🔧 Fix: Set activation_date for users with active subscriptions
// ============================================================

require_once __DIR__ . '/db.php';

try {
    // Update activation_date based on the latest active subscription
    $affected = $pdo->exec("
        UPDATE users 
        SET activation_date = s.start_date 
        FROM (
            SELECT DISTINCT ON (user_id) user_id, start_date 
            FROM subscriptions 
            WHERE status = 'active' AND start_date IS NOT NULL
            ORDER BY user_id, id DESC
        ) s 
        WHERE users.id = s.user_id AND (users.activation_date IS NULL OR users.activation_date = '')
    ");
    echo "✅ Updated $affected users' activation_date.<br>";
    echo "🎉 Done! You can now delete this file.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "ℹ️ If your PostgreSQL version doesn't support FROM in UPDATE, contact support for an alternative script.";
}
?>
