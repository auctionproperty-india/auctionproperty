<?php
// 1. एरर दिखाने के लिए सेटिंग
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. डेटाबेस कनेक्शन फाइल को जोड़ना
include 'db.php';

// 3. जब यूज़र फॉर्म सबमिट करे (POST रिक्वेस्ट)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // फॉर्म से डेटा लेना
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // बेसिक वैलिडेशन
    if (empty($username) || empty($email) || empty($password)) {
        die("कृपया सभी फ़ील्ड्स को भरें।");
    }

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
            echo "<div style='color: green; text-align: center; margin-top: 20px;'>";
            echo "<h3>रजिस्ट्रेशन सफल रहा! 🎉</h3>";
            echo "<a href='index.php'>यहाँ से लॉगिन करें</a>";
            echo "</div>";
        } else {
            echo "रजिस्ट्रेशन विफल रहा।";
        }

    } catch (PDOException $e) {
        die("डेटाबेस एरर: " . $e->getMessage());
    }
}
?>
