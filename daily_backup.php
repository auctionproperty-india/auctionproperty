<?php
// ============================================================
// 📧 DAILY DATABASE BACKUP SCRIPT (Supabase -> Resend Email API)
// ============================================================

// 1. SECURITY TOKEN
define('BACKUP_SECRET_TOKEN', 'MySuperSecretBackupToken2026');

// 2. RESEND API CONFIGURATION
// https://resend.com par free account banayein, API key lein, aur yahan daalein
define('RESEND_API_KEY', 're_xxxxxxxxxxxxxxxxxxxxxxxx'); // 🔥 Yahan apni Resend API Key daalein
define('EMAIL_TO', 'bliveindia2018@gmail.com'); // Backup is email par aayega
define('EMAIL_FROM', 'onboarding@resend.dev'); // Resend ka default sender (testing ke liye)

// 3. SECURITY CHECK
if (!isset($_GET['token']) || $_GET['token'] !== BACKUP_SECRET_TOKEN) {
    http_response_code(403);
    die("Access Denied. Invalid Token.");
}

// 4. DATABASE CONNECTION
require_once __DIR__ . '/db.php';

// 5. CREATE BACKUP DIRECTORY
$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$date = date('Y-m-d_H-i-s');
$sql_file = $backup_dir . "backup_{$date}.sql";
$zip_file = $backup_dir . "backup_{$date}.zip";

// 6. FETCH ALL TABLES FROM SUPABASE AND GENERATE SQL DUMP
try {
    $tables = [];
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tables[] = $row['table_name'];
    }

    $sql_dump = "-- Prime Property India Database Backup\n";
    $sql_dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Database: Supabase PostgreSQL\n\n";

    foreach ($tables as $table) {
        $sql_dump .= "\n-- --------------------------------------------------------\n";
        $sql_dump .= "-- Table structure and data for `$table`\n";
        $sql_dump .= "-- --------------------------------------------------------\n";
        
        $data_stmt = $pdo->query("SELECT * FROM \"$table\"");
        $rows = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            $columns = array_keys($rows[0]);
            $col_list = implode(", ", array_map(function($c) { return "\"$c\""; }, $columns));
            
            $sql_dump .= "INSERT INTO \"$table\" ($col_list) VALUES\n";
            
            $row_count = 0;
            foreach ($rows as $row) {
                $values = array_map(function($val) use ($pdo) {
                    if ($val === null) return "NULL";
                    if ($val === true) return "TRUE";
                    if ($val === false) return "FALSE";
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
            $sql_dump .= "-- No data in this table\n";
        }
    }

    file_put_contents($sql_file, $sql_dump);

    // 7. ZIP THE FILE
    if (!class_exists('ZipArchive')) {
        die("Error: ZipArchive extension is not enabled on this server.");
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $zip->addFile($sql_file, basename($sql_file));
        $zip->close();
    } else {
        die("Failed to create ZIP file.");
    }

    unlink($sql_file);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// 8. SEND EMAIL VIA RESEND API (HTTP - Port 443)
function sendEmailWithResend($api_key, $to, $from, $subject, $body, $file_path, $file_name) {
    $file_content = base64_encode(file_get_contents($file_path));
    
    $post_data = [
        'from' => $from,
        'to' => [$to],
        'subject' => $subject,
        'html' => nl2br($body),
        'attachments' => [
            [
                'filename' => $file_name,
                'content' => $file_content
            ]
        ]
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($http_code >= 200 && $http_code < 300);
}

$subject = "Daily Backup - " . date('d M Y');
$body = "Hello Admin,\n\nPlease find attached the daily database backup for Prime Property India.\n\nDate: " . date('d M Y, h:i A') . "\n\nRegards,\nSystem";
$file_name = basename($zip_file);

if (sendEmailWithResend(RESEND_API_KEY, EMAIL_TO, EMAIL_FROM, $subject, $body, $zip_file, $file_name)) {
    echo "✅ Backup successfully created and emailed!";
} else {
    echo "❌ Backup created, but email failed to send. Please check your Resend API Key.";
}

// 9. CLEANUP OLD BACKUPS (Keep only last 3 days)
$files = glob($backup_dir . '*.zip');
if (count($files) > 3) {
    usort($files, function($a, $b) { return filemtime($a) - filemtime($b); });
    $delete_count = count($files) - 3;
    for ($i = 0; $i < $delete_count; $i++) {
        unlink($files[$i]);
    }
}
?>
