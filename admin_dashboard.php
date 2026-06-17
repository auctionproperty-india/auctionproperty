<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub_admin')) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .sidebar { width: 220px; background: #343a40; color: white; position: fixed; height: 100%; padding-top: 20px; }
        .sidebar h3 { text-align: center; color: #ffc107; }
        .sidebar a { display: block; color: #ddd; padding: 12px; text-decoration: none; padding-left: 20px; }
        .sidebar a:hover { background: #495057; color: white; }
        .main-content { margin-left: 240px; padding: 30px; }
        .header { background: white; padding: 15px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .card { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px; }
        .badge { background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>Admin Panel</h3>
    <a href="#">📊 मुख्य डैशबोर्ड</a>
    <a href="#">🏢 प्रॉपर्टी/प्रोडक्ट जोड़ें</a>
    <a href="#">👥 यूज़र्स लिस्ट</a>
    <a href="login.php" style="color: #ff6b6b; margin-top: 50px;">Logout</a>
</div>

<div class="main-content">
    <div class="header">
        <h2>प्रणाम, <?php echo htmlspecialchars($_SESSION['username']); ?>! <span class="badge"><?php echo strtoupper($_SESSION['role']); ?></span></h2>
        <a href="login.php" style="color: red; text-decoration: none; font-weight: bold;">लॉगआउट</a>
    </div>
    <div class="card">
        <h3>🚀 एडमिन कंट्रोल रूम</h3>
        <p>यह आपका एडमिन पैनल है। यहाँ से आप जो भी एंट्री भरेंगे, वह यूज़र को सीधे दिखाई देगी।</p>
    </div>
</div>

</body>
</html>
