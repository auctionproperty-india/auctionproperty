<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        try {
            // चेक करें कि यह ईमेल डेटाबेस में है या नहीं
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // 6 अंकों का रैंडम OTP जनरेट करें
                $otp = rand(100000, 999990);

                // पहले से अगर कोई पुराना OTP इस ईमेल का है तो उसे डिलीट करें
                $del_query = "DELETE FROM password_resets WHERE email = :email";
                $del_stmt = $conn->prepare($del_query);
                $del_stmt->bindParam(':email', $email);
                $del_stmt->execute();

                // नया OTP डेटाबेस में सेव करें
                $ins_query = "INSERT INTO password_resets (email, otp) VALUES (:email, :otp)";
                $ins_stmt = $conn->prepare($ins_query);
                $ins_stmt->bindParam(':email', $email);
                $ins_stmt->bindParam(':otp', $otp);
                
                if ($ins_stmt->execute()) {
                    // 🚨 भाई ध्यान दें: असली ईमेल भेजने के लिए यहाँ SMTP कोड लगेगा।
                    // अभी टेस्टिंग के लिए हम स्क्रीन पर ही OTP दिखा देते हैं ताकि काम न रुके!
                    $message = "<div style='color: green; font-weight: bold; background: #e2f0d9; padding: 10px; border-radius: 4px; margin-bottom: 15px;'>
                        OTP जनरेट हो गया है! (टेस्टिंग के लिए आपका OTP है: $otp)<br>
                        <a href='verify_otp.php?email=" . urlencode($email) . "' style='color: #007bff;'>यहाँ क्लिक करके OTP वेरीफाई करें</a>
                    </div>";
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
    <p style="color: #666; font-size: 14px;">अपना रजिस्टर्ड ईमेल डालें, हम पासवर्ड रीसेट करने के लिए OTP जनरेट करेंगे।</p>
    
    <?php echo $message; ?>

    <form action="forgot_password.php" method="POST">
        <input type="email" name="email" placeholder="Enter Registered Email" required>
        <button type="submit">Send OTP</button>
    </form>

    <div class="links-container">
        <a href="login.php">वापस लॉगिन पर जाएं</a>
    </div>
</div>

</body>
</html>
