<?php
// ============================================================
// 📦 Daily Backup Script – Email: bliveindia2018@gmail.com
// ============================================================

// ---- Configuration ----
$BACKUP_EMAIL = 'bliveindia2018@gmail.com';
$PROJECT_DIR = __DIR__; // Current directory (project root)
$BACKUP_DIR = __DIR__ . '/temp_backup'; // Temporary backup folder

// Email subject with date
$date = date('Y-m-d');
$SUBJECT = "Daily Backup – {$date} – Prime Property India";

// ---- Security: Only allow local execution or cron ----
// If you want to run via URL, set a secret key and check it
// For cron, we can just allow all (but better to restrict via .htaccess)
// We'll add a simple key check – set a random key in your .env or here
$SECRET_KEY = 'YOUR_SUPER_SECRET_BACKUP_KEY'; // CHANGE THIS!

// If called via HTTP, require the key
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['key']) || $_GET['key'] !== $SECRET_KEY) {
        die('Unauthorized');
    }
}

// ---- Functions ----
function sendEmailWithAttachment($to, $subject, $body, $attachmentPath) {
    // Use PHP's mail() with MIME attachment
    $from = 'noreply@' . $_SERVER['SERVER_NAME'];

    // Boundaries
    $boundary = md5(time());

    // Headers
    $headers = "From: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    // Plain text body
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $body . "\r\n\r\n";

    // Attachment
    if (file_exists($attachmentPath)) {
        $fileContent = file_get_contents($attachmentPath);
        $fileContent = chunk_split(base64_encode($fileContent));
        $filename = basename($attachmentPath);

        $message .= "--$boundary\r\n";
        $message .= "Content-Type: application/octet-stream; name=\"$filename\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
        $message .= $fileContent . "\r\n\r\n";
    }

    $message .= "--$boundary--";

    // Send
    return mail($to, $subject, $message, $headers);
}

function logMessage($msg) {
    echo date('Y-m-d H:i:s') . " - " . $msg . "\n";
    // Also write to log file
    file_put_contents(__DIR__ . '/backup_log.txt', date('Y-m-d H:i:s') . " - " . $msg . "\n", FILE_APPEND);
}

// ---- Start Backup ----
logMessage("Backup started...");

// 1. Create temp directory if not exists
if (!is_dir($BACKUP_DIR)) {
    mkdir($BACKUP_DIR, 0755, true);
}

// 2. Generate archive name with date
$archiveName = "backup_" . date('Y-m-d_H-i-s') . ".tar.gz";
$archivePath = $BACKUP_DIR . '/' . $archiveName;

// 3. Create tar.gz of the entire project (excluding temp_backup and backup_* files)
logMessage("Creating tar archive...");

// Exclude temp_backup, backup_*.tar.gz, and log files
$exclude = "--exclude='{$BACKUP_DIR}' --exclude='*.tar.gz' --exclude='backup_log.txt'";
$command = "cd {$PROJECT_DIR} && tar -czf {$archivePath} . {$exclude} 2>&1";
exec($command, $output, $returnVar);

if ($returnVar !== 0) {
    logMessage("❌ Tar failed: " . implode("\n", $output));
    die("Tar failed");
}

logMessage("Tar created: {$archivePath}");

// 4. Export Database (PostgreSQL)
logMessage("Exporting database...");

// Load database configuration from db.php
// We need to get the DSN and credentials
// We'll include db.php to get $pdo, then extract connection info
include_once __DIR__ . '/db.php';

// If using Supabase, we need the connection string. We can get from $pdo.
// Attempt to get DB name, user, host, password from $pdo
// Since we might not have direct access, we can use pg_dump with env variables.

// Parse DSN to get dbname, user, host, port
$dsn = $pdo->getAttribute(PDO::ATTR_DSN);
// Example: pgsql:host=aws-0-ap-south-1.pooler.supabase.com;port=6543;dbname=postgres;user=postgres;password=...
// We'll extract using regex
$dbname = '';
$user = '';
$password = '';
$host = '';
$port = '5432';

if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) $dbname = $matches[1];
if (preg_match('/user=([^;]+)/', $dsn, $matches)) $user = $matches[1];
if (preg_match('/password=([^;]+)/', $dsn, $matches)) $password = $matches[1];
if (preg_match('/host=([^;]+)/', $dsn, $matches)) $host = $matches[1];
if (preg_match('/port=([^;]+)/', $dsn, $matches)) $port = $matches[1];

if (empty($dbname) || empty($user) || empty($host)) {
    logMessage("❌ Could not extract DB credentials from DSN. Please set manually.");
    // Fallback: you can set them manually here
    // $dbname = 'your_db';
    // $user = 'your_user';
    // $password = 'your_password';
    // $host = 'your_host';
    // $port = '5432';
}

$dbBackupFile = $BACKUP_DIR . '/db_dump.sql';
$pgDumpCommand = "PGPASSWORD='{$password}' pg_dump -h {$host} -p {$port} -U {$user} -d {$dbname} -F p > {$dbBackupFile} 2>&1";
exec($pgDumpCommand, $dumpOutput, $dumpReturn);

if ($dumpReturn !== 0) {
    logMessage("❌ pg_dump failed: " . implode("\n", $dumpOutput));
    // Still continue with file backup only
} else {
    logMessage("Database dump created: {$dbBackupFile}");
    // Add the SQL file to the archive
    $addDbCmd = "cd {$BACKUP_DIR} && tar -rf {$archivePath} db_dump.sql 2>&1";
    exec($addDbCmd);
    // Remove the SQL file after adding
    unlink($dbBackupFile);
}

logMessage("Backup archive finalized: {$archivePath}");

// 5. Email the backup
logMessage("Sending email to {$BACKUP_EMAIL}...");

$body = "Dear Admin,\n\n";
$body .= "Please find attached the daily backup for " . date('Y-m-d H:i:s') . ".\n\n";
$body .= "Backup includes:\n- Full project code\n- Database dump (if successful)\n\n";
$body .= "File: " . basename($archivePath) . "\n";
$body .= "Size: " . round(filesize($archivePath) / 1024 / 1024, 2) . " MB\n\n";
$body .= "Regards,\nBackup System\nPrime Property India";

$sent = sendEmailWithAttachment($BACKUP_EMAIL, $SUBJECT, $body, $archivePath);

if ($sent) {
    logMessage("✅ Email sent successfully.");
} else {
    logMessage("❌ Email sending failed.");
}

// 6. Cleanup: delete the archive and temp folder
unlink($archivePath);
rmdir($BACKUP_DIR); // should be empty

logMessage("Backup process completed.");

// ---- End ----
echo "Backup completed. Check backup_log.txt for details.\n";
?>
