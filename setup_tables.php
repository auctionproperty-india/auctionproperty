<?php
// ============================================================
// 🛠️ एक बार चलाएँ – PostgreSQL पर सभी ज़रूरी टेबल बनाएँ
// ============================================================

require_once __DIR__ . '/db.php';

echo "<div style='font-family: Arial; max-width:700px; margin:50px auto; padding:20px; background:#f8fafc; border-radius:12px;'>";
echo "<h2>🔧 डेटाबेस टेबल सेटअप</h2>";

try {
    // ----- 1. settings टेबल (अगर नहीं है तो बनाएँ) -----
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY DEFAULT 1,
        tds VARCHAR(10) DEFAULT '0',
        bank_details TEXT,
        admin_charge VARCHAR(20) DEFAULT '0',
        scanner_image VARCHAR(255)
    )");
    echo "<p>✅ settings टेबल (यदि नहीं थी तो) बना दी गई।</p>";

    // कॉलम जोड़ें – अगर पहले से हैं तो ignore
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS tds VARCHAR(10) DEFAULT '0'");
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS bank_details TEXT");
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS admin_charge VARCHAR(20) DEFAULT '0'");
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS scanner_image VARCHAR(255)");
    echo "<p>✅ settings टेबल में सभी आवश्यक कॉलम मौजूद हैं (या जोड़ दिए गए)।</p>";

    // सुनिश्चित करें कि एक पंक्ति (id=1) हो
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (id, tds, bank_details, admin_charge, scanner_image) 
                    VALUES (1, '0', '', '0', '')");
        echo "<p>✅ settings में डिफॉल्ट पंक्ति डाल दी गई (id=1)।</p>";
    }

    // ----- 2. payment_requests टेबल (अगर नहीं है तो बनाएँ) -----
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_requests (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        package_id INTEGER NOT NULL,
        amount VARCHAR(20),
        slip_image VARCHAR(255),
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>✅ payment_requests टेबल (यदि नहीं थी तो) बना दी गई।</p>";

    // ----- सफलता संदेश -----
    echo "<div style='background:#dcfce7; padding:15px; border-left:5px solid #22c55e; margin-top:20px;'>";
    echo "<strong style='color:#166534;'>✅ सभी टेबल और कॉलम सफलतापूर्वक बना दिए गए हैं!</strong><br>";
    echo "अब आप <strong>Settings</strong> में Bank Details डाल सकते हैं और <strong>User Packages</strong> से 'Buy Now' करके Payment Page देख सकते हैं।";
    echo "</div>";

    echo "<p style='margin-top:20px;'>";
    echo "<a href='settings.php' style='color:#2563eb;'>⚙️ Settings पर जाएँ</a> | ";
    echo "<a href='user_packages.php' style='color:#2563eb;'>📦 Packages पर जाएँ</a> | ";
    echo "<a href='payment_info.php?package_id=1' style='color:#2563eb;'>💳 Payment Page (Demo)</a>";
    echo "</p>";

    echo "<p style='color:red; font-weight:bold;'>⚠️ सुरक्षा कारणों से इस फाइल (<strong>setup_tables.php</strong>) को अभी डिलीट कर दें!</p>";

} catch (PDOException $e) {
    echo "<div style='background:#fee2e2; padding:15px; border-left:5px solid #dc2626; margin-top:10px;'>";
    echo "<strong style='color:#dc2626;'>❌ त्रुटि:</strong> " . $e->getMessage();
    echo "</div>";
}
echo "</div>";
?>
