<?php
$font_dir = 'fonts/';
if (!is_dir($font_dir)) mkdir($font_dir, 0777, true);

$font_url = 'https://github.com/google/fonts/raw/main/ofl/inter/Inter%5Bwght%5D.ttf';
$font_path = $font_dir . 'Inter.ttf';

$font_data = file_get_contents($font_url);
if ($font_data !== false) {
    file_put_contents($font_path, $font_data);
    echo "✅ Font downloaded successfully!<br>";
    echo "Path: " . $font_path . "<br>";
    echo "File size: " . filesize($font_path) . " bytes";
} else {
    echo "❌ Failed to download font. Please check internet connection or use a different font URL.";
}
?>
