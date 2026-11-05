<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$to = $_GET['to'] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    die("Usage: test_email.php?to=your@email.com");
}

$ok = sendEmailViaBrevo(
    $to,
    "Test Email from Prime Property",
    "<h2>Test Email OK</h2><p>Ye email Brevo API se bheja gaya hai.</p>"
);

if ($ok) {
    echo "SENT OK - Brevo ne email accept kar liya. Ab inbox/spam check karo.";
} else {
    echo "FAILED - Brevo API ne email reject kiya. Render logs me 'Brevo API Error' dekho.";
}
