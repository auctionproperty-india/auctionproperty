<?php
require_once __DIR__ . '/db.php';

$dirs = ['uploads/', 'uploads/slips/', 'uploads/kyc/', 'uploads/resumes/'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
        echo "✅ Created directory: $dir<br>";
    } else {
        echo "ℹ️ Directory already exists: $dir<br>";
    }
}
echo "🎉 Done!";
?>
