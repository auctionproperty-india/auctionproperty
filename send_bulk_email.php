<?php
// ============================================================
// 📧 Send Bulk Email to Selected Leads
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['sales', 'admin'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: sales_leads.php");
    exit;
}

$lead_ids = $_POST['lead_ids'] ?? [];
$subject  = trim($_POST['subject'] ?? '');
$message  = trim($_POST['message'] ?? '');

if (empty($lead_ids) || $subject === '' || $message === '') {
    $_SESSION['flash_error'] = "Sabhi fields bharein aur kam se kam ek customer select karein.";
    header("Location: sales_leads.php");
    exit;
}

// Sanitize IDs
$lead_ids = array_map('intval', $lead_ids);

// Placeholder-safe query
$placeholders = implode(',', array_fill(0, count($lead_ids), '?'));
$stmt = $pdo->prepare("SELECT id, name, email FROM sales_leads 
                       WHERE id IN ($placeholders) 
                         AND email IS NOT NULL 
                         AND email != ''");
$stmt->execute($lead_ids);
$leads = $stmt->fetchAll();

if (empty($leads)) {
    $_SESSION['flash_error'] = "Selected leads mein kisi ka bhi valid email nahi hai.";
    header("Location: sales_leads.php");
    exit;
}

$sent   = 0;
$failed = 0;
$no_email_skipped = count($lead_ids) - count($leads);

// ============================================================
// EMAIL HEADERS
// ============================================================
// ⚠️ Apna domain email yahan daalein
$from_email = "no-reply@primepropertyindia.com";
$from_name  = "Prime Property India";

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: {$from_name} <{$from_email}>\r\n";
$headers .= "Reply-To: {$from_email}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// ============================================================
// SEND LOOP
// ============================================================
foreach ($leads as $lead) {
    // Personalization: {name} replace karein
    $personal_msg = str_replace(
        '{name}', 
        htmlspecialchars($lead['name']), 
        $message
    );
    
    // HTML body wrap
    $body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .wrapper { max-width: 600px; margin: 0 auto; padding: 20px; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="wrapper">
        ' . $personal_msg . '
        <div class="footer">
            <p>Prime Property India</p>
            <p>Yeh email aapko isliye mila kyunki aapne humse sampark kiya tha.</p>
        </div>
    </div>
</body>
</html>';
    
    // Send
    if (@mail($lead['email'], $subject, $body, $headers)) {
        $sent++;
    } else {
        $failed++;
    }
    
    // Throttle: 1 second delay between emails (spam protection)
    usleep(200000); // 0.2 second
}

// ============================================================
// FLASH MESSAGE
// ============================================================
$msg = "✅ $sent emails bheje gaye.";
if ($failed > 0)          $msg .= " ❌ $failed fail hue.";
if ($no_email_skipped > 0) $msg .= " ⚠️ $no_email_skipped leads ke pass email nahi tha (skipped).";

$_SESSION['flash_success'] = $msg;

header("Location: sales_leads.php");
exit;
