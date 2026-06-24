<?php
// ============================================================
// ✅ Debug File – Server पर Files की List दिखाएगी
// ============================================================

echo "<h3>📂 Files in Current Directory</h3>";

$files = scandir(__DIR__);
echo "<ul>";
foreach($files as $file) {
    if($file !== '.' && $file !== '..') {
        echo "<li>" . htmlspecialchars($file) . "</li>";
    }
}
echo "</ul>";

echo "<hr>";

// Check if get_property.php exists
if(file_exists(__DIR__ . '/get_property.php')) {
    echo "✅ <strong>get_property.php</strong> exists in this directory.<br>";
    echo "File size: " . filesize(__DIR__ . '/get_property.php') . " bytes.<br>";
} else {
    echo "❌ <strong>get_property.php</strong> NOT found in this directory.<br>";
    echo "Make sure the file name is exactly <code>get_property.php</code> (case-sensitive).<br>";
}

echo "<hr>";

// Check if properties.php exists
if(file_exists(__DIR__ . '/properties.php')) {
    echo "✅ <strong>properties.php</strong> exists in this directory.<br>";
} else {
    echo "❌ <strong>properties.php</strong> NOT found in this directory.<br>";
}
?>
