<?php
$font_dir = 'fonts/';
if (!is_dir($font_dir)) mkdir($font_dir, 0777, true);

$font_url = 'https://github.com/google/fonts/raw/main/ofl/inter/Inter%5Bwght%5D.ttf';
$font_path = $font_dir . 'Inter.ttf';

file_put_contents($font_path, file_get_contents($font_url));

if (file_exists($font_path)) {
    echo "✅ Font downloaded successfully! <br>";
    echo "Path: " . $font_path;
} else {
    echo "❌ Failed to download font. Please check your internet connection.";
}
?>
