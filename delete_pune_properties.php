<?php
// ============================================================
// 🗑️ Pune की 22 July तक की सभी Properties को DELETE करें
// ⚠️ बिना Admin Check – POST कन्फर्मेशन के साथ
// ⚠️ इस फाइल को चलाने के तुरंत बाद DELETE कर दें!
// ============================================================

session_start();
require_once __DIR__ . '/db.php';

// --- पहले POST से कन्फर्मेशन लें ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<!DOCTYPE html>
    <html>
    <head><title>Confirm Delete</title></head>
    <body style='font-family: Arial; max-width:600px; margin:50px auto; padding:20px; background:#fef2f2; border-radius:12px; border:2px solid #dc2626;'>
        <h2 style='color:#dc2626;'>⚠️ चेतावनी: स्थायी विलोपन (Permanent Deletion)</h2>
        <p>यह स्क्रिप्ट <strong>Pune</strong> शहर की <strong>22 July 2026</strong> तक की <strong>सभी Properties</strong> को डिलीट कर देगी।</p>
        <p style='color:red; font-weight:bold;'>यह क्रिया उलटी नहीं की जा सकती (Irreversible)!</p>
        <p>कृपया सुनिश्चित करें कि आप सही डेटा डिलीट कर रहे हैं।</p>
        <form method='POST'>
            <button type='submit' style='background:#dc2626; color:white; padding:12px 30px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; font-size:16px;'>
                🗑️ हाँ, सभी Pune Properties डिलीट करें
            </button>
            <a href='properties.php' style='margin-left:15px; color:#64748b;'>🚫 Cancel</a>
        </form>
    </body>
    </html>";
    exit;
}

// --- POST है, तो डिलीट करें ---
// मान लिया कि आपकी properties टेबल में 'city' और 'created_at' कॉलम हैं
// अगर कॉलम का नाम अलग है (जैसे 'location', 'date_added'), तो नीचे बदल दें

$sql = "DELETE FROM properties WHERE city = 'Pune' AND DATE(created_at) <= '2026-07-22'";
$stmt = $pdo->prepare($sql);
$stmt->execute();

$deleted = $stmt->rowCount();

// परिणाम दिखाएँ
echo "<div style='font-family: Arial; max-width: 700px; margin: 50px auto; padding: 30px; background: #fef2f2; border-radius: 16px; border: 2px solid #dc2626;'>";
echo "<h2 style='color: #dc2626;'>✅ सभी Pune Properties डिलीट कर दी गईं!</h2>";
echo "<p><strong>$deleted</strong> Properties (Pune, 22 July 2026 तक) सफलतापूर्वक हटा दी गईं।</p>";
echo "<p><a href='properties.php' style='color: #2563eb;'>🔙 Properties पर जाएँ</a></p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ इस फाइल (<strong>delete_pune_properties.php</strong>) को तुरंत DELETE कर दें!</p>";
echo "</div>";

// (वैकल्पिक) अपने आप डिलीट – चाहें तो अनकमेंट करें
// unlink(__FILE__);
?>
