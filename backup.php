<?php
// ============================================================
// 📦 DAILY BACKUP – Email पर भेजने वाली सरल Script
// ============================================================

// ============ ⚙️ CONFIGURATION (यहाँ बदलाव करें) ============
$BACKUP_EMAIL = 'bliveindia2018@gmail.com';       // किस Email पर भेजना है
$SECRET_KEY   = 'backup123456';                    // सुरक्षा के लिए Key (कोई भी रखें)
// ============================================================

// ---- Security Check (सिर्फ Key वाले को अनुमति) ----
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
        die('❌ Unauthorized – सही Key डालें');
    }
}

// ---- Log File ----
$logFile = __DIR__ . '/backup_log.txt';
function writeLog($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " – " . $msg . "\n", FILE_APPEND);
}

writeLog("===== Backup Started =====");

// ---- Check if ZipArchive is available ----
if (!class_exists('ZipArchive')) {
    writeLog("❌ ZipArchive not available on this server");
    die("❌ ZipArchive Extension ज़रूरी है");
}

// ---- Create ZIP file ----
$zipFileName = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
$zipFilePath = __DIR__ . '/' . $zipFileName;

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    writeLog("❌ Cannot create ZIP file");
    die("❌ ZIP file नहीं बन सकी");
}

// ---- Add all files from project folder (skip certain files/folders) ----
$skipFolders = ['temp_backup', 'node_modules', '.git'];
$skipFiles   = ['backup_log.txt'];

$projectDir = __DIR__;
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$fileCount = 0;
foreach ($files as $file) {
    if (!$file->isFile()) continue;

    $filePath = $file->getRealPath();
    $relativePath = str_replace($projectDir . DIRECTORY_SEPARATOR, '', $filePath);
    $relativePath = str_replace('\\', '/', $relativePath);

    // Skip unwanted files/folders
    $skip = false;
    foreach ($skipFolders as $folder) {
        if (strpos($relativePath, $folder . '/') === 0) { $skip = true; break; }
    }
    foreach ($skipFiles as $f) {
        if ($relativePath === $f) { $skip = true; break; }
    }
    // Skip the backup zip itself
    if (strpos($relativePath, 'backup_') === 0 && substr($relativePath, -4) === '.zip') $skip = true;
    
    if ($skip) continue;

    $zip->addFile($filePath, $relativePath);
    $fileCount++;
}

$zip->close();
writeLog("✅ ZIP created: $zipFileName ($fileCount files)");

// ---- Send Email with Attachment ----
$to      = $BACKUP_EMAIL;
$subject = 'Daily Backup – ' . date('d M Y') . ' – Prime Property India';
$from    = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$boundary = md5(uniqid(time()));

$headers  = "From: $from\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$body  = "--$boundary\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$body .= "<h2>📦 Daily Backup</h2>";
$body .= "<p><b>Date:</b> " . date('d M Y H:i:s') . "</p>";
$body .= "<p><b>Files:</b> $fileCount</p>";
$body .= "<p><b>Size:</b> " . round(filesize($zipFilePath) / 1024 / 1024, 2) . " MB</p>";
$body .= "<p>Backup file attached below.</p>\r\n\r\n";

// Attach ZIP
if (file_exists($zipFilePath)) {
    $fileData = chunk_split(base64_encode(file_get_contents($zipFilePath)));
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: application/zip; name=\"$zipFileName\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"$zipFileName\"\r\n\r\n";
    $body .= $fileData . "\r\n\r\n";
}
$body .= "--$boundary--";

// Send Mail
if (mail($to, $subject, $body, $headers)) {
    writeLog("✅ Email sent to $to");
    echo "✅ Backup भेज दिया गया – $to\n";
} else {
    writeLog("❌ Email sending failed");
    echo "❌ Email नहीं भेजा जा सका\n";
}

// ---- Delete ZIP after sending (स्थान बचाने के लिए) ----
if (file_exists($zipFilePath)) {
    unlink($zipFilePath);
    writeLog("🗑️ ZIP file deleted");
}

writeLog("===== Backup Finished =====\n");
echo "✅ Backup process पूरा हुआ।\n";
