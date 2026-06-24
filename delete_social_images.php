<?php
require_once 'db.php';

echo "<h2>🗑️ Deleting All Social Images...</h2>";

// 1. Get all properties with image_url starting with 'uploads/social_'
$stmt = $pdo->query("SELECT id, image_url FROM properties WHERE image_url LIKE 'uploads/social_%'");
$properties = $stmt->fetchAll();
$count = 0;
$deleted_files = 0;

foreach ($properties as $prop) {
    $image_path = $prop['image_url'];
    if (file_exists($image_path)) {
        unlink($image_path); // Delete file
        $deleted_files++;
    }
    // Set image_url to NULL in database
    $pdo->prepare("UPDATE properties SET image_url = NULL WHERE id = ?")->execute([$prop['id']]);
    $count++;
}

echo "✅ $count property records updated (image_url set to NULL).<br>";
echo "✅ $deleted_files image files deleted from server.<br>";

// Also delete any orphaned social images that might not be in DB
$files = glob('uploads/social_*.png');
$orphaned = 0;
foreach ($files as $file) {
    // Check if any property still references this file (optional, but we can just delete all)
    unlink($file);
    $orphaned++;
}
echo "✅ $orphaned orphaned image files deleted.<br>";

echo "<hr>";
echo "<p style='color:green; font-weight:bold;'>All social images have been deleted. Now run your website – it should load faster.</p>";
echo "<p style='color:red; font-weight:bold;'>⚠️ DELETE THIS FILE IMMEDIATELY AFTER RUNNING!</p>";
?>
