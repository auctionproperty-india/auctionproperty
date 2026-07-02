<?php
require_once 'db.php';

echo "<h2>🔧 Creating Tables & Columns...</h2>";

try {
    // ---- Users Table में नए कॉलम ----
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS bank_name VARCHAR(100)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS account_number VARCHAR(50)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS ifsc VARCHAR(20)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS branch VARCHAR(100)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(50)");
    echo "✅ Users table columns added/verified.<br>";

    // ---- KYC Documents Table ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS kyc_documents (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        doc_type VARCHAR(50) NOT NULL,
        file_path TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ kyc_documents table created.<br>";

    // ---- Support Tickets Table ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id SERIAL PRIMARY KEY,
        user_id INT REFERENCES users(id) ON DELETE CASCADE,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        screenshot TEXT,
        status VARCHAR(20) DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "✅ support_tickets table created.<br>";

    echo "<hr><h3 style='color:green;'>✅ All tables and columns have been successfully added!</h3>";
    echo "<p style='color:red; font-weight:bold;'>⚠️ Please delete this file (create_tables.php) immediately after running.</p>";

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<p>If you see errors, check that your database is accessible and the 'users' table exists.</p>";
}
?>
