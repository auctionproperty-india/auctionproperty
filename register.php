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
    
    // फॉर्म से डेटा लेना
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // बेसिक वैलिडेशन
    if (empty($username) || empty($email) || empty($password)) {
        $message = "<p style='color: red;'>कृपया सभी फ़ील्ड्स को भरें।</p>";
    } else {
        try {
            // PostgreSQL के लिए सुरक्षित SQL Query
            $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
            $stmt = $conn->prepare($query);

            // डेटा को सुरक्षित तरीके से बाइंड करना
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);

            // क्वेरी को चलाना
            if ($stmt->execute()) {
                $message = "<p style='color: green;'>रजिस्ट्रेशन सफल रहा! 🎉 <a href='index.php'>यहाँ से लॉगिन करें</a></p>";
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
        .form-container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px #ccc; display: inline-block; width: 300px; }
        input { width: 90%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 95%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #218838; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Create Account</h2>
    
    <?php echo $message; ?>

    <form action="register.php" method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Register</button>
    </form>
</div>

</body>
</html>
