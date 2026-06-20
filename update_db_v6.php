<?php
require_once 'db.php';
try {
    // 1. Users table में city column
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT ''");

    // 2. Settings में Bank Details और QR Code के लिए entries
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES 
        ('company_bank_name', ''),
        ('company_account_number', ''),
        ('company_ifsc', ''),
        ('company_branch', ''),
        ('company_qr_code', '')
    ON CONFLICT (setting_key) DO NOTHING");

    echo "✅ Database Updated to v6! <br>";
    echo "- Users table: city column added.<br>";
    echo "- Settings: bank details & QR code keys added.<br>";
    echo "<a href='settings.php' class='btn btn-primary mt-3'>Go to Settings</a>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
