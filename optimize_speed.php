<?php
require_once 'db.php';

echo "<h2>🚀 Speed Optimization Check</h2>";

// 1. Check Indexes
echo "<h4>📊 Checking Indexes...</h4>";
$tables = ['properties', 'users', 'subscriptions'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT indexname FROM pg_indexes WHERE tablename = '$table'");
    $indexes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($indexes) > 0) {
        echo "✅ Table '$table' has " . count($indexes) . " indexes.<br>";
    } else {
        echo "❌ Table '$table' has NO indexes! Run add_indexes.php<br>";
    }
}

// 2. Check Image Generation Time
echo "<h4>🖼️ Testing Image Generation (Simulated)...</h4>";
$start = microtime(true);
// Create a dummy image to test speed
$img = imagecreatetruecolor(1920, 1080);
$white = imagecolorallocate($img, 255, 255, 255);
imagefilledrectangle($img, 0, 0, 1920, 1080, $white);
$path = 'uploads/test_speed.png';
imagepng($img, $path, 6);
imagedestroy($img);
$time = microtime(true) - $start;
echo "Image generation took: " . round($time, 2) . " seconds (Full HD)<br>";
if ($time > 2) echo "⚠️ Image generation is slow. Reduce resolution further if needed.<br>";

// 3. Check PHP Memory Limit
$memory = ini_get('memory_limit');
echo "<h4>💾 PHP Memory Limit: $memory</h4>";
if (intval($memory) < 256) echo "⚠️ Increase memory limit to 256M or higher.<br>";

// 4. Check Max Execution Time
$max_exec = ini_get('max_execution_time');
echo "<h4>⏱️ Max Execution Time: $max_exec seconds</h4>";
if ($max_exec < 60) echo "⚠️ Increase execution time to 60 seconds or more.<br>";

echo "<hr>";
echo "<p>✅ Diagnostic complete. If any warnings appear, fix them.</p>";
?>
