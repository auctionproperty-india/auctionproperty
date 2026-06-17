<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (!empty($email) && !empty($password)) {
        try {
            // डेटाबेस से यूज़र ढूंढना
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // पासवर्ड मैच करना
            if ($user && $user['password'] === $password) {
                // सेशन में यूज़र का डेटा सेव करना
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // रोल के हिसाब से मैसेज दिखाना (एडमिन/यूज़र)
                if ($user['role'] == 'admin' || $user['role'] == 'sub_admin') {
                    $message = "<p style='color: green; font-weight: bold;'>लॉगिन सफल! आप एडमिन हैं। 🎉</p>";
                } else {
                    $message = "<p style='color: green; font-weight: bold;'>लॉगिन सफल! आप यूज़र हैं। 🎉</p>";
                }
            } else {
                $message = "<p style='color: red;'>गलत ईमेल या पासवर्ड।</p>";
            }
        } catch (PDOException $e) {
            $message = "<p style='color: red;'>डेटाबेस एरर: " . $e->getMessage() . "</p>";
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
    <title>लॉगिन पेज</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; text-align: center; padding-top: 50px; }
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px #ccc; display: inline-block; width: 320px; text-align: left; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        label { font-weight: bold; color: #555; display: block; margin-top: 10px; }
        input { width: 93%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 12px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #0056b3; }
        .links-container { text-align: center; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
        .links-container a { color: #007bff; text-decoration: none; font-size: 14px; display: inline-block; margin: 5px 10px; }
        .links-container a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Login Account</h2>
    
    <?php echo $message; ?>

    <form action="login.php" method="POST">
        <label>Email Address</label>
        <input type="email" name="email" placeholder="Enter Email Address" required>
        
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter Password" required>
        
        <button type="submit">Login</button>
    </form>

    <div class="links-container">
        <a href="register.php">नया अकाउंट बनाएं</a>
        <a href="forgot_password.php" style="color: #dc3545;">Password भूल गए?</a>
    </div>
</div>

</body>
</html>
