<?php
require_once 'db.php';
require_once 'functions.php';

// पहली Available Property लें
$stmt = $pdo->query("SELECT * FROM properties ORDER BY id DESC LIMIT 1");
$prop = $stmt->fetch();

if(!$prop) {
    die("❌ No property found. Please add a property first.");
}

// Image Generate करें
$image_path = generateSocialCard($prop);

if($image_path) {
    echo "✅ Image Generated Successfully!<br>";
    echo "Path: " . $image_path . "<br>";
    echo "<img src='" . $image_path . "' style='max-width:600px; border:1px solid #ddd; border-radius:10px;'>";
} else {
    echo "❌ Failed to generate image.";
}
?>
