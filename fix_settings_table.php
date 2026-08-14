<?php
// ============================================================
// 🔧 Settings टेबल में आवश्यक कॉलम जोड़ें – बस एक बार चलाएँ
// ============================================================

require_once __DIR__ . '/db.php';

// कॉलम जोड़ें – अगर पहले से हैं तो कोई प्रभाव नहीं
try {
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS tds VARCHAR(10) DEFAULT '0'");
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS bank_details TEXT");
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS admin_charge VARCHAR(20) DEFAULT '0'");
    $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS scanner_image VARCHAR(255)");
    
    // सुनिश्चित करें कि कम से कम एक पंक्ति हो (id=1)
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (id, tds, bank_details, admin_charge, scanner_image) 
                    VALUES (1, '0', '', '0', '')");
    }
    
    echo "<div style='font-family:Arial; max-width:600px; margin:50px auto; padding:20px; background:#dcfce7; border:2px solid #22c55e; border-radius:12px;'>
            <h2 style='color:#166534;'>✅ Settings Table सफलतापूर्वक अपडेट हो गई!</h2>
            <p>अब आप <strong>settings.php</strong> खोलकर सेटिंग्स देख/बदल सकते हैं।</p>
            <p><a href='settings.php' style='color:#2563eb;'>➡️ settings.php पर जाएँ</a></p>
            <p style='color:red; font-weight:bold;'>⚠️ इस फाइल (<strong>fix_settings_table.php</strong>) को अभी DELETE कर दें!</p>
          </div>";
} catch (PDOException $e) {
    echo "<div style='font-family:Arial; max-width:600px; margin:50px auto; padding:20px; background:#fee2e2; border:2px solid #dc2626; border-radius:12px;'>
            <h2 style='color:#dc2626;'>❌ त्रुटि: " . $e->getMessage() . "</h2>
            <p>कृपया अपनी डेटाबेस संरचना जाँचें या मुझे बताएँ।</p>
          </div>";
}
?>
