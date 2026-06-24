<?php
// ============================================================
// ✅ test_edit.php – यह फाइल get_property.php को Test करेगी
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Check if user is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("❌ You must be logged in as admin to run this test.");
}

// Get first property ID
$id = $pdo->query("SELECT id FROM properties ORDER BY id DESC LIMIT 1")->fetchColumn();
if(!$id) {
    die("❌ No properties found. Please add a property first.");
}

// Build URL to get_property.php
$url = "https://" . $_SERVER['HTTP_HOST'] . "/get_property.php?id=" . $id;

// Simulate AJAX call with file_get_contents
$response = @file_get_contents($url);

echo "<h3>🔍 Testing get_property.php</h3>";
echo "<p><strong>Request URL:</strong> " . htmlspecialchars($url) . "</p>";

if($response === false) {
    echo "<p style='color:red;'>❌ Failed to fetch data from get_property.php. Make sure the file exists and is readable.</p>";
    echo "<p>Check that <code>get_property.php</code> is in the same directory as <code>properties.php</code>.</p>";
} else {
    $data = json_decode($response, true);
    if(json_last_error() !== JSON_ERROR_NONE) {
        echo "<p style='color:red;'>❌ Invalid JSON response: " . htmlspecialchars($response) . "</p>";
    } else {
        echo "<p style='color:green;'>✅ get_property.php returned valid JSON!</p>";
        echo "<p><strong>Data:</strong></p>";
        echo "<pre>" . print_r($data, true) . "</pre>";
        echo "<p>Now try clicking Edit on a property in the Admin Panel – it should work!</p>";
    }
}
?>
