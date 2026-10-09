<?php
// ============================================================
// 📧 DAILY DATABASE BACKUP SCRIPT (Supabase -> Email)
// ============================================================

// 1. SECURITY TOKEN (Isko change karein aur yaad rakhein)
define('BACKUP_SECRET_TOKEN', 'MySuperSecretBackupToken2026');

// 2. EMAIL CONFIGURATION (Apne SMTP details yahan bharein)
define('SMTP_HOST', 'smtp.gmail.com'); // Ya smtp-relay.brevo.com
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password'); // Gmail ka App Password use karein
define('EMAIL_TO', 'santoshdhakse829@gmail.com'); // Jis email par backup chahiye
define('EMAIL_FROM', 'your_email@gmail.com');

// 3. SECURITY CHECK (Bina token ke koi is URL ko access na kar sake)
if (!isset($_GET['token']) || $_GET['token'] !== BACKUP_SECRET_TOKEN) {
    http_response_code(403);
    die("Access Denied. Invalid Token.");
}

require_once __DIR__ . '/db.php'; // Aapki database connection file

// 4. CREATE BACKUP FILE
$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$date = date('Y-m-d_H-i-s');
$sql_file = $backup_dir . "backup_{$date}.sql";
$zip_file = $backup_dir . "backup_{$date}.zip";

// 5. FETCH ALL TABLES FROM SUPABASE (PostgreSQL)
try {
    $tables = [];
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['table_name'];
    }

    $sql_dump = "-- Prime Property India Database Backup\n";
    $sql_dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";

    foreach ($tables as $table) {
        // Get Create Table Statement (Optional but good)
        $sql_dump .= "\n-- Table structure for `$table`\n";
        
        // Get Data
        $sql_dump .= "INSERT INTO `$table` VALUES\n";
        $data_stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            $row_count = 0;
            foreach ($rows as $row) {
                $values = array_map(function($val) use ($pdo) {
                    if ($val === null) return "NULL";
                    return $pdo->quote($val);
                }, array_values($row));
                
                $sql_dump .= "(" . implode(", ", $values) . ")";
                $row_count++;
                if ($row_count < count($rows)) {
                    $sql_dump .= ",\n";
                } else {
                    $sql_dump .= ";\n";
                }
            }
        } else {
            $sql_dump .= "-- No data\n";
        }
    }

    // Write SQL to file
    file_put_contents($sql_file, $sql_dump);

    // 6. ZIP THE FILE
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $zip->addFile($sql_file, basename($sql_file));
        $zip->close();
    } else {
        die("Failed to create ZIP file.");
    }

    // Delete the raw SQL file after zipping
    unlink($sql_file);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// 7. SEND EMAIL WITH ATTACHMENT (Native SMTP)
function sendEmailWithAttachment($to, $from, $subject, $body, $file_path, $file_name) {
    $boundary = md5(time());
    
    $headers = "From: $from\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $body . "\r\n";
    $message .= "--$boundary\r\n";

    $file_content = chunk_split(base64_encode(file_get_contents($file_path)));
    $message .= "Content-Type: application/zip; name=\"$file_name\"\r\n";
    $message .= "Content-Disposition: attachment; filename=\"$file_name\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= $file_content . "\r\n";
    $message .= "--$boundary--";

    // Native SMTP Connection
    $smtp_conn = fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
    if (!$smtp_conn) return false;

    $response = fgets($smtp_conn, 515);
    fputs($smtp_conn, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    $response = fgets($smtp_conn, 515);
    
    fputs($smtp_conn, "STARTTLS\r\n");
    $response = fgets($smtp_conn, 515);
    stream_socket_enable_crypto($smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    
    fputs($smtp_conn, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    $response = fgets($smtp_conn, 515);
    
    fputs($smtp_conn, "AUTH LOGIN\r\n");
    $response = fgets($smtp_conn, 515);
    fputs($smtp_conn, base64_encode(SMTP_USER) . "\r\n");
    $response = fgets($smtp_conn, 515);
    fputs($smtp_conn, base64_encode(SMTP_PASS) . "\r\n");
    $response = fgets($smtp_conn, 515);
    
    fputs($smtp_conn, "MAIL FROM: <$from>\r\n");
    $response = fgets($smtp_conn, 515);
    fputs($smtp_conn, "RCPT TO: <$to>\r\n");
    $response = fgets($smtp_conn, 515);
    fputs($smtp_conn, "DATA\r\n");
    $response = fgets($smtp_conn, 515);
    
    fputs($smtp_conn, $headers . "\r\n" . $message . "\r\n.\r\n");
    $response = fgets($smtp_conn, 515);
    fputs($smtp_conn, "QUIT\r\n");
    fclose($smtp_conn);
    
    return true;
}

$subject = "Daily Backup - " . date('d M Y');
$body = "Hello Admin,\n\nPlease find attached the daily database backup for Prime Property India.\n\nDate: " . date('d M Y, h:i A') . "\n\nRegards,\nSystem";
$file_name = basename($zip_file);

if (sendEmailWithAttachment(EMAIL_TO, EMAIL_FROM, $subject, $body, $zip_file, $file_name)) {
    echo "✅ Backup successfully created and emailed!";
} else {
    echo "❌ Backup created, but email failed to send.";
}

// 8. CLEANUP OLD BACKUPS (Optional - Keep only last 3 days)
$files = glob($backup_dir . '*.zip');
if (count($files) > 3) {
    usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
    $delete_count = count($files) - 3;
    for ($i = 0; $i < $delete_count; $i++) {
        unlink($files[$i]);
    }
}
?>
