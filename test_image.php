<?php
require_once 'db.php';
require_once 'functions.php';

// PHP Errors को Screen पर दिखाएँ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// GD Extension Check
if (!extension_loaded('gd')) {
    die("❌ GD Extension not loaded! Image generation will not work.");
}
echo "✅ GD Extension is loaded.<br>";

// Check uploads folder
$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    echo "📁 'uploads/' folder created.<br>";
}
if (!is_writable($upload_dir)) {
    die("❌ 'uploads/' folder is not writable. Please set permissions to 0777.");
}
echo "✅ 'uploads/' folder is writable.<br>";

// Get first property
$stmt = $pdo->query("SELECT * FROM properties ORDER BY id DESC LIMIT 1");
$prop = $stmt->fetch();

if(!$prop) {
    die("❌ No property found. Please add a property first.");
}
echo "✅ Property found: " . htmlspecialchars($prop['title']) . "<br>";

// Try to generate image
try {
    $image_path = generateSocialCard($prop);
    if($image_path && file_exists($image_path)) {
        echo "✅ Image Generated Successfully!<br>";
        echo "Path: " . $image_path . "<br>";
        echo "<img src='" . $image_path . "' style='max-width:600px; border:1px solid #ddd; border-radius:10px;'>";
    } else {
        echo "❌ Failed to generate image. Returned path: " . ($image_path ?: 'empty');
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage();
}
?>
