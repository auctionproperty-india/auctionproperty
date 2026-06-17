<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

$message = "";
$email = isset($_GET['email']) ? $_GET['email'] : '';

// सुरक्षा के लिए: अगर कोई बिना ईमेल के डायरेक्ट इस पेज पर आए, तो वापस भेज दो
if (empty($email)) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (!empty($email) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $message = "<p style='color: red;'>दोनों पासवर्ड मैच नहीं हो रहे हैं।</p>";
        } else {
            try {
                // डेटाबेस में नया पासवर्ड अपडेट करना
                $up_query = "UPDATE users SET password = :password WHERE email = :email";
                $up_stmt = $conn->prepare($up_query);
                $up_stmt->bindParam(':password', $new_password);
                $up_stmt->bindParam(':email', $email);
                
                if ($up_stmt->execute()) {
                    $message = "<p style='color: green; font-weight: bold;'>पासवर्ड सफलतापूर्वक बदल गया है! 🎉 <br><br><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;'>यहाँ से लॉगिन करें</a></p>";
                } else {
                    $message = "<p style='color: red;'>पासवर्ड अपडेट करने में विफल।</p>";
                }
            } catch (PDOException $e) {
                $message = "<p style='color: red;'>एरर: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        $message = "<p style='color: red;'>कृपया सभी फ़ील्ड्स भरें।</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Create New Password</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; text-align: center; padding-top: 50px; }
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px #ccc; display: inline-block; width: 320px; text-align: left; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        label { font-weight: bold; color: #555; display: block; margin-top: 15px; }
        input { width: 93%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #218838; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Create New Password</h2>
    
    <?php echo $message; ?>

    <!-- अगर पासवर्ड बदल गया तो फॉर्म छुपा देंगे -->
    <?php if(strpos($message, 'सफलतापूर्वक') === false): ?>
    <form action="verify_otp.php?email=<?php echo urlencode($email); ?>" method="POST">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
        
        <label>आपका वेरीफाइड ईमेल:</label>
        <p style="font-weight: bold; color: green; margin: 5px 0; font-size: 16px;"><?php echo htmlspecialchars($email); ?></p>

        <label>नया पासवर्ड सेट करें</label>
        <input type="password" name="new_password" placeholder="Enter New Password" required>
        
        <label>पासवर्ड दोबारा डालें</label>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        
        <button type="submit">Save & Update Password</button>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
