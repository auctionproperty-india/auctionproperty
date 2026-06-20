<?php
require_once 'db.php';
try {
    // Accounting Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS account_entries (
        id SERIAL PRIMARY KEY,
        type VARCHAR(20) NOT NULL CHECK (type IN ('income', 'expense')),
        amount DECIMAL(10,2) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(100) NOT NULL,
        entry_date DATE NOT NULL DEFAULT CURRENT_DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Manual Referral Update Flag
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS manual_referral_updated BOOLEAN DEFAULT FALSE");

    echo "✅ Database Updated to v5! <br>";
    echo "- Accounting table created.<br>";
    echo "- Referral manual update flag added.<br>";
    echo "<a href='admin_accounting.php' class='btn btn-primary mt-3'>Go to Accounting</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
