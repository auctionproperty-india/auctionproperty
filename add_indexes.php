<?php
require_once 'db.php';
try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_properties_city ON properties(city)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_properties_bank ON properties(bank_name)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_properties_price ON properties(price)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_properties_status ON properties(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_subscriptions_user ON subscriptions(user_id)");
    echo "✅ All indexes created!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
