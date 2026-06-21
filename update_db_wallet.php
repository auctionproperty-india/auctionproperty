<?php
require_once 'db.php';
try {
    // 1. Users table में wallet_balance column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(10,2) DEFAULT 0");

    // 2. Wallet Transactions Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS wallet_transactions (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        amount DECIMAL(10,2) NOT NULL,
        type VARCHAR(20) NOT NULL CHECK (type IN ('credit', 'debit')),
        description TEXT NOT NULL,
        reference_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. Existing users को wallet balance 0 सेट करें (जो NULL हैं)
    $pdo->exec("UPDATE users SET wallet_balance = 0 WHERE wallet_balance IS NULL");

    echo "✅ Wallet system updated successfully! <br>";
    echo "- wallet_balance column added to users.<br>";
    echo "- wallet_transactions table created.<br>";
    echo "<a href='user_dashboard.php' class='btn btn-primary mt-3'>Go to Dashboard</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
