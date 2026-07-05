<?php
require_once 'db.php';

// अपनी Email डालें – जहाँ Test Email भेजनी है
$to = 'your-email@example.com'; // ← अपनी Email डालें
$subject = "Test Email from Prime Property";
$message = "This is a test email to check if mail() function works.";
$headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
$headers .= "Content-type:text/plain;charset=UTF-8";

if(mail($to, $subject, $message, $headers)) {
    echo "✅ Test email sent to $to. Check your inbox/spam.";
} else {
    echo "❌ Failed to send test email. Check server logs.";
}

// Show mail configuration
echo "<hr><h4>PHP mail() Configuration:</h4>";
echo "<pre>";
print_r(ini_get_all('mail'));
echo "</pre>";
?>
