<?php
// ============================================================
// 🔍 FIND SQL FILE - Show exact location
// ============================================================

echo "<h1>🔍 SQL File Finder</h1>";

$paths = [
    __DIR__ . '/mysql_import.sql',
    '/var/www/html/mysql_import.sql',
    getcwd() . '/mysql_import.sql',
    $_SERVER['DOCUMENT_ROOT'] . '/mysql_import.sql',
    '/app/mysql_import.sql',
    '/home/render/mysql_import.sql',
    '/opt/render/project/src/mysql_import.sql',
    '/data/mysql_import.sql',
    '/tmp/mysql_import.sql',
    './mysql_import.sql',
    'mysql_import.sql'
];

echo "<h2>📂 Checking all possible paths:</h2>";
echo "<pre>";

$found = false;
foreach ($paths as $path) {
    if (file_exists($path)) {
        echo "✅ FOUND: $path\n";
        echo "   Size: " . round(filesize($path) / 1024 / 1024, 2) . " MB\n";
        $found = true;
    } else {
        echo "❌ NOT FOUND: $path\n";
    }
}

if (!$found) {
    echo "\n⚠️ Searching entire filesystem...\n";
    $output = shell_exec("find / -name '*.sql' 2>/dev/null");
    echo $output;
}

echo "\n📂 Current directory: " . __DIR__ . "\n";
echo "📂 Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "📂 getcwd(): " . getcwd() . "\n";

echo "\n📄 Files in current directory:\n";
foreach (glob("*") as $file) {
    echo "  - $file\n";
}

echo "</pre>";
?>
