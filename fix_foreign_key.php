<?php
require_once 'db.php';
try {
    // पुरानी constraint हटाएँ
    $pdo->exec("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_property_id_fkey");
    // नई constraint डालें (ON DELETE SET NULL के साथ)
    $pdo->exec("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_property_id_fkey FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL");
    echo "✅ Foreign key constraint updated! Now property_id can be NULL.";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
