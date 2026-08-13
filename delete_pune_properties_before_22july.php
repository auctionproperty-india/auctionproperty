<?php
// ============================================================
// 🗑️ पुणे (Pune) शहर की 22 जुलाई 2026 से पहले की सभी Properties डिलीट करें
// ============================================================

session_start();
require_once __DIR__ . '/db.php';

// (वैकल्पिक) Admin Check – अगर आप चाहते हैं तो चेक रख सकते हैं
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("⛔ केवल Admin ही चला सकता है।");
}

// कन्फर्मेशन - POST method से
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<!DOCTYPE html>
    <html>
    <head><title>Confirm Delete</title></head>
    <body style='font-family:Arial; max-width:600px; margin:50px auto; padding:20px; background:#fef2f2; border-radius:12px; border:2px solid #ef4444;'>
        <h2 style='color:#dc2626;'>⚠️ चेतावनी: यह क्रिया अपरिवर्तनीय है</h2>
        <p>यह स्क्रिप्ट <strong>पुणे (Pune)</strong> शहर की <strong>22 जुलाई 2026 से पहले</strong> की <strong>सभी Properties</strong> को स्थायी रूप से डिलीट कर देगी।</p>
        <p>कृपया पुष्टि करें कि आप यह करना चाहते हैं।</p>
        <form method='POST'>
            <button type='submit' style='background:#dc2626; color:white; padding:12px 30px; border:none; border-radius:8px; cursor:pointer; font-weight:bold;'>
                ✅ हाँ, डिलीट करो
            </button>
            <a href='properties.php' style='margin-left:15px; color:#64748b;'>🚫 Cancel</a>
        </form>
        <p style='margin-top:20px;'><strong>Note:</strong> सुनिश्चित करें कि <code>properties</code> टेबल में <code>city</code> और <code>created_at</code> कॉलम मौजूद हैं।</p>
    </body>
    </html>";
    exit;
}

// --- यदि POST है, तो DELETE करें ---
$city = 'Pune';
$date_limit = '2026-07-22 23:59:59'; // 22 जुलाई 2026, रात 12 बजे तक

// पहले गिनती करें कि कितनी properties प्रभावित होंगी
$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE city = ? AND created_at <= ?");
$stmt->execute([$city, $date_limit]);
$count = $stmt->fetchColumn();

if ($count == 0) {
    die("ℹ️ पुणे शहर में 22 जुलाई 2026 से पहले कोई property नहीं मिली। कोई बदलाव नहीं किया गया।");
}

// DELETE
$stmt = $pdo->prepare("DELETE FROM properties WHERE city = ? AND created_at <= ?");
$stmt->execute([$city, $date_limit]);
$deleted = $stmt->rowCount();

// परिणाम
echo "<div style='font-family:Arial; max-width:700px; margin:50px auto; padding:30px; background:#f0fdf4; border-radius:16px; border:2px solid #22c55e;'>
    <h2 style='color:#166534;'>✅ डिलीट सफल!</h2>
    <p><strong>$deleted</strong> properties (पुणे, 22 जुलाई 2026 से पहले) को हटा दिया गया।</p>
    <p><a href='properties.php' style='color:#2563eb;'>🔙 Properties पर वापस जाएँ</a></p>
    <p style='color:red;'><strong>⚠️ इस फाइल को अभी डिलीट करें!</strong></p>
</div>";

// (वैकल्पिक) खुद को डिलीट करें
// unlink(__FILE__);
?>
