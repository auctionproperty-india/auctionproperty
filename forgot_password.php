<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

// PHPMailer की फाइलों को शामिल करना
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        try {
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $otp = rand(100000, 999999);

                // पुराना OTP डिलीट करना
                $del_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = :email");
                $del_stmt->bindParam(':email', $email);
                $del_stmt->execute();

                // नया OTP सेव करना
                $ins_stmt = $conn->prepare("INSERT INTO password_resets (email, otp) VALUES (:email, :otp)");
                $ins_stmt->bindParam(':email', $email);
                $ins_stmt->bindParam(':otp', $otp);
                
                if ($ins_stmt->execute()) {
                    
                    // 🔥 यहाँ से असली ईमेल जाएगा
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        
                        // 🚨 अपनी ईमेल डिटेल्स यहाँ डालें
                        $mail->Username   = 'YOUR_GMAIL_EMAIL@gmail.com'; // आपका जीमेल
                        $mail->Password   = 'YOUR_GMAIL_APP_PASSWORD';   // आपका 16 अक्षरों का ऐप पासवर्ड
                        
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom('YOUR_GMAIL_EMAIL@gmail.com', 'Auction Site');
                        $mail->addAddress($email); // यूज़र का ईमेल

                        $mail->isHTML(true);
                        $mail->Subject = 'Password Reset OTP';
                        $mail->Body    = "आपका पासवर्ड रीसेट करने का OTP है: <b>$otp</b>. यह OTP किसी के साथ शेयर न करें।";

                        $mail->send();
                        
                        // ईमेल जाने के बाद सीधे OTP वाले पेज पर भेजें
                        header("Location: verify_otp.php?email=" . urlencode($email));
                        exit();

                    } catch (Exception $e) {
                        $message = "<p style='color: red;'>ईमेल भेजने में गड़बड़ हुई: {$mail->ErrorInfo}</p>";
                    }
                }
            } else {
                $message = "<p style='color: red;'>यह ईमेल हमारे रिकॉर्ड में नहीं है।</p>";
            }
        } catch (PDOException $e) {
            $message = "<p style='color: red;'>एरर: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p style='color: red;'>कृपया अपना ईमेल दर्ज करें।</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; text-align: center; padding-top: 50px; }
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px #ccc; display: inline-block; width: 320px; text-align: left; }
        h2 { text-align: center; color: #333; }
        input { width: 93%; padding: 10px; margin: 15px 0; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 12px; background-color: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #c82333; }
        .links-container { text-align: center; margin-top: 20px; }
        .links-container a { color: #007bff; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
<div class="form-container">
    <h2>Forgot Password</h2>
    <p style="color: #666; font-size: 14px;">अपना रजिस्टर्ड ईमेल डालें, हम पासवर्ड रीसेट करने के लिए आपके ईमेल पर OTP भेजेंगे।</p>
    <?php echo $message; ?>
    <form action="forgot_password.php" method="POST">
        <input type="email" name="email" placeholder="Enter Registered Email" required>
        <button type="submit">Send OTP to Email</button>
    </form>
    <div class="links-container">
        <a href="login.php">वापस लॉगिन पर जाएं</a>
    </div>
</div>
</body>
</html>
