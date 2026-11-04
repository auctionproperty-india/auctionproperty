<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🔥 आपकी ईमेल आईडी यहाँ डाल दी गई है
$test_email = "hr.primepropertyindia@gmail.com"; 

$subject = "Test Email from Prime Property";
$body = "
<div style='font-family: Arial, sans-serif; padding: 20px; text-align: center;'>
    <h1 style='color: #10b981;'>✅ Success!</h1>
    <p style='font-size: 16px;'>Agar yeh email aapko mila hai, to iska matlab aapka SMTP setup perfectly kaam kar raha hai.</p>
    <p style='color: #64748b;'>Ab aap apni website par naye users ko automatic welcome email bhej sakte hain.</p>
</div>
";

// ईमेल भेजने की कोशिश करें
if (sendEmailSMTP($test_email, $subject, $body)) {
    echo "<div style='text-align: center; margin-top: 50px; font-family: sans-serif;'>";
    echo "<h2 style='color:green;'>✅ Test Email Sent Successfully!</h2>";
    echo "<p>Please check your inbox (hr.primepropertyindia@gmail.com).</p>";
    echo "</div>";
} else {
    echo "<div style='text-align: center; margin-top: 50px; font-family: sans-serif;'>";
    echo "<h2 style='color:red;'>❌ Failed to send email.</h2>";
    echo "<p>Please check your Render Environment Variables and Logs.</p>";
    echo "</div>";
}
?>
