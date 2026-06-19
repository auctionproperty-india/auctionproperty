<?php
$font_dir = 'fonts/';
if (!is_dir($font_dir)) mkdir($font_dir, 0777, true);

// ✅ नया और सही URL (Inter Regular Font – Google Fonts से)
$font_url = 'https://raw.githubusercontent.com/google/fonts/main/ofl/inter/Inter%5Bwght%5D.ttf';
$font_path = $font_dir . 'Inter.ttf';

$font_data = file_get_contents($font_url);
if ($font_data !== false) {
    file_put_contents($font_path, $font_data);
    echo "✅ Font downloaded successfully!<br>";
    echo "Path: " . $font_path . "<br>";
    echo "File size: " . filesize($font_path) . " bytes";
} else {
    // ⚠️ अगर पहला URL काम न करे तो दूसरा URL आज़माएँ
    $font_url2 = 'https://raw.githubusercontent.com/rsms/inter/main/fonts/Inter-Regular.ttf';
    $font_data2 = file_get_contents($font_url2);
    if ($font_data2 !== false) {
        file_put_contents($font_path, $font_data2);
        echo "✅ Font downloaded successfully (from backup URL)!<br>";
        echo "Path: " . $font_path . "<br>";
        echo "File size: " . filesize($font_path) . " bytes";
    } else {
        echo "❌ Failed to download font from both URLs.<br>";
        echo "Please manually download Inter font from: <a href='https://fonts.google.com/specimen/Inter' target='_blank'>Google Fonts</a><br>";
        echo "And place the .ttf file in the 'fonts/' folder as 'Inter.ttf'";
    }
}
?>
