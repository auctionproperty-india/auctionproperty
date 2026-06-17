<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #e9ecef; margin: 0; padding: 0; }
        .navbar { background: #007bff; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; font-weight: bold; }
        .container { max-width: 1000px; margin: 30px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0px 4px 10px rgba(0,0,0,0.05); }
        .welcome-box { background: #e7f1ff; padding: 15px; border-radius: 5px; color: #0c5460; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Prime Property Portal</h2>
    <div> 
        <a href="profile.php" style="background: #28a745; padding: 8px 15px; border-radius: 4px; margin-right: 10px; text-decoration: none;">👤 Profile & KYC</a>
        <span style="margin-right: 20px;">👤 <?php echo htmlspecialchars($_SESSION['username']); ?> (यूज़र)</span>
        <a href="login.php" style="background: #dc3545; padding: 8px 15px; border-radius: 4px;">Log Out</a>
    </div>
</div>

<div class="container">
    <div class="welcome-box">
        <strong>लॉगिन सफल रहा!</strong> आप यूज़र डैशबोर्ड पर हैं।
    </div>
    <h3>🏢 लाइव प्रॉपर्टीज / आइटम्स</h3>
    <div style="border: 2px dashed #ccc; padding: 40px; text-align: center; color: #999; margin-top: 20px; border-radius: 6px;">
        अभी एडमिन ने कोई आइटम नहीं डाला है। एडमिन पैनल से एंट्री होते ही यहाँ डेटा दिखने लगेगा!
    </div>
</div>

</body>
</html>
