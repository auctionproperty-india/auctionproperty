<?php
// 1. एरर दिखाने के लिए सेटिंग
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. डेटाबेस कनेक्शन फाइल को जोड़ना
include 'db.php';

// संदेश दिखाने के लिए वेरिएबल
$message = "";

// 3. जब यूज़र फॉर्म का बटन दबाकर सबमिट करे (POST रिक्वेस्ट)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($email) || empty($password)) {
        $message = "<p style='color: red;'>कृपया सभी फ़ील्ड्स को भरें।</p>";
    } else {
        try {
            // PostgreSQL के लिए सुरक्षित SQL Query
            $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
            $stmt = $conn->prepare($query);

            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);

            if ($stmt->execute()) {
                $message = "<p style='color: green;'>रजिस्ट्रेशन सफल रहा! 🎉 <a href='login.php' style='color: #007bff; font-weight: bold;'>यहाँ से लॉगिन करें</a></p>";
            } else {
                $message = "<p style='color: red;'>रजिस्ट्रेशन विफल रहा।</p>";
            }

        } catch (PDOException $e) {
            $message = "<p style='color: red;'>डेटाबेस एरर: " . $e->getMessage() . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>रजिस्ट्रेशन पेज</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; text-align: center; padding-top: 50px; }
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px #ccc; display: inline-block; width: 320px; text-align: left; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        label { font-weight: bold; color: #555; display: block; margin-top: 10px; }
        input { width: 93%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        button:hover { background-color: #218838; }
        .links-container { text-align: center; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
        .links-container a { color: #007bff; text-decoration: none; font-size: 14px; display: inline-block; margin: 5px 10px; }
        .links-container a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Create Account</h2>
    
    <?php echo $message; ?>

    <form action="register.php" method="POST">
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter Username" required>
        
        <label>Email Address</label>
        <input type="email" name="email" placeholder="Enter Email Address" required>
        
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter Password" required>
        
        <button type="submit">Register</button>
    </form>

    <div class="links-container">
        <a href="login.php">पहले से अकाउंट है? लॉगिन करें</a>
        <a href="forgot_password.php" style="color: #dc3545;">Password भूल गए?</a>
    </div>
</div>

</body>
</html>
