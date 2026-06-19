<?php
$font_dir = 'fonts/';
if (!is_dir($font_dir)) mkdir($font_dir, 0777, true);

// ✅ नया और सही URL (Google Fonts से सीधा डाउनलोड)
$font_url = 'https://raw.githubusercontent.com/rsms/inter/main/fonts/Inter-Regular.ttf';
$font_path = $font_dir . 'Inter.ttf';

$font_data = file_get_contents($font_url);
if ($font_data !== false && strlen($font_data) > 10000) {
    file_put_contents($font_path, $font_data);
    echo "✅ Font downloaded successfully!<br>";
    echo "Path: " . $font_path . "<br>";
    echo "File size: " . filesize($font_path) . " bytes";
} else {
    echo "❌ Failed to download font automatically.<br>";
    echo "Please manually download Inter font from: <a href='https://fonts.google.com/specimen/Inter' target='_blank'>Google Fonts</a><br>";
    echo "And place the .ttf file in the 'fonts/' folder as 'Inter.ttf'";
}
?>
