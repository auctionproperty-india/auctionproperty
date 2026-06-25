<?php
// ============================================================
// 🔍 test_edit_debug.php – Direct AJAX Test
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("❌ You must be logged in as admin.");
}

// सबसे पहली Property का ID लें
$id = $pdo->query("SELECT id FROM properties ORDER BY id DESC LIMIT 1")->fetchColumn();
if(!$id) {
    die("❌ No properties found.");
}

// Direct API Call
$url = "https://" . $_SERVER['HTTP_HOST'] . "/get_property.php?id=" . $id;

echo "<h3>🔍 Testing get_property.php</h3>";
echo "<p><strong>Request URL:</strong> " . htmlspecialchars($url) . "</p>";

// CURL से Request भेजें
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p><strong>HTTP Status Code:</strong> " . $http_code . "</p>";

if($http_code == 200) {
    // सिर्फ Body Extract करें
    $body = substr($response, strpos($response, "\r\n\r\n") + 4);
    $data = json_decode($body, true);
    
    if(json_last_error() === JSON_ERROR_NONE) {
        echo "<p style='color:green;'>✅ get_property.php returned valid JSON!</p>";
        echo "<p><strong>Data:</strong></p>";
        echo "<pre>" . print_r($data, true) . "</pre>";
        echo "<p>Now go to Admin Panel and click Edit – it should work.</p>";
    } else {
        echo "<p style='color:red;'>❌ Invalid JSON: " . htmlspecialchars($body) . "</p>";
    }
} else {
    echo "<p style='color:red;'>❌ HTTP Error " . $http_code . ". Check file permissions and path.</p>";
}
?>
