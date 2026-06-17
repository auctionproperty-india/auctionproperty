<?php
// Render PostgreSQL Database Connection Suite (Full Overwrite)
$host = "dpg-cuv66rtu4l7c739m13sg-a.singapore-postgres.render.com";
$port = "5432";
$dbname = "auctionproperty_p917"; 
$user = "auctionproperty_p917_user"; 

// ⚠️ यहाँ अपना वो असली पासवर्ड डालें जो आपके रेंडर डैशबोर्ड पर पोस्टग्रेस सेटिंग्स में दिख रहा है
$password = "YOUR_DATABASE_PASSWORD_HERE"; 

try {
    // 🌍 पोस्टग्रेस के लिए स्टैंडर्ड DSN स्ट्रिंग
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    // 🔒 ड्राइवर लेवल पर SSL मोड को 'require' करना ताकि रेंडर कनेक्शन न काटे
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // 🔥 यह लाइन रेंडर के सर्वर को चुप कराने के लिए सबसे ज़रूरी है
        PDO::PGSQL_ATTR_SSL_MODE     => 'require' 
    ];
    
    // ⚡ कनेक्शन इनिशियलाइज़ेशन
    $conn = new PDO($dsn, $user, $password, $options);
    
    // कनेक्शन एकदम परफेक्ट होने पर स्क्रीन पर कुछ नहीं दिखेगा, सीधे काम होगा
} catch (PDOException $e) {
    // अगर फिर भी कोई गड़बड़ हो, तो यह साफ़ एरर टर्मिनल पर दिखाएगा
    die("Database Matrix Connection Terminated: " . $e->getMessage());
}
?>
