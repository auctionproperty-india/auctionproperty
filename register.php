<?php
// डेटाबेस कनेक्शन फाइल को जोड़ना
include 'db.php';

// जब यूज़र फॉर्म सबमिट करे (POST रिक्वेस्ट)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. फॉर्म से डेटा लेना (PDO में mysqli_real_escape_string की कोई ज़रूरत नहीं होती)
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // 2. बेसिक वैलिडेशन (चेक करना कि फील्ड्स खाली तो नहीं हैं)
    if (empty($username) || empty($email) || empty($password)) {
        die("कृपया सभी फ़ील्ड्स को भरें।");
    }

    try {
        // 3. PostgreSQL के लिए सुरक्षित SQL Query (Placeholders के साथ)
        // ध्यान दें: अगर आपके डेटाबेस टेबल का नाम 'users' की जगह कुछ और है, तो उसे यहाँ बदल लें
        $query = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
        $stmt = $conn->prepare($query);

        // 4. डेटा को सुरक्षित तरीके से बाइंड करना (SQL Injection से सुरक्षा)
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);

        // 5. क्वेरी को चलाना (Execute)
        if ($stmt->execute()) {
            echo "<div style='color: green; text-align: center; margin-top: 20px;'>";
            echo "<h3>रजिस्ट्रेशन सफल रहा! 🎉</h3>";
            echo "<a href='index.php'>यहाँ से लॉगिन करें</a>";
            echo "</div>";
        } else {
            echo "रजिस्ट्रेशन विफल रहा। कृपया दोबारा प्रयास करें।";
        }

    } catch (PDOException $e) {
        // अगर डेटाबेस में कोई गड़बड़ आती है (जैसे टेबल न मिलना या डुप्लीकेट ईमेल)
        die("डेटाबेस एरर: " . $e->getMessage());
    }
}
?>
