<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Investor Terminal - Prime Property</title>
    <style>
        :root {
            --primary-brand: #4f46e5;
            --brand-glow: #6366f1;
            --bg-light: #f8fafc;
            --card-white: #ffffff;
        }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background-color: var(--bg-light); margin: 0; padding: 0; color: #1e293b; }
        .navbar { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .nav-brand { font-size: 24px; font-weight: 800; letter-spacing: 1px; text-shadow: 0 0 10px var(--brand-glow); text-transform: uppercase; }
        .nav-actions { display: flex; align-items: center; }
        .btn-profile { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; margin-right: 15px; transition: all 0.3s ease; }
        .btn-profile:hover { background: white; color: #1e1b4b; box-shadow: 0 0 15px rgba(255,255,255,0.4); }
        .btn-logout { background: #ef4444; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .btn-logout:hover { box-shadow: 0 0 15px rgba(239, 68, 68, 0.4); }
        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .welcome-hero { background: linear-gradient(to right, #eff6ff, #dbeafe); border-left: 5px solid var(--primary-brand); padding: 25px; border-radius: 8px; margin-bottom: 30px; }
        .listing-grid-empty { border: 2px dashed #cbd5e1; padding: 60px; text-align: center; color: #64748b; border-radius: 12px; background: #fff; font-size: 16px; font-style: italic; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="nav-brand">Prime Property</div>
    <div class="nav-actions">
        <span style="margin-right: 20px; color: #cbd5e1; font-weight: 500;">👤 Active User: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="profile.php" class="btn-profile">My Profile</a>
        <a href="login.php" class="btn-logout">Sign Out</a>
    </div>
</div>

<div class="container">
    <div class="welcome-hero">
        <h3 style="margin: 0; color: #1e3a8a; font-size: 20px;">Authentication Successful</h3>
        <p style="margin: 5px 0 0 0; color: #1e40af; font-size: 14px;">Welcome to your private asset deployment portal.</p>
    </div>

    <h2 style="font-weight: 800; color: #0f172a; margin-bottom: 20px;">Live Premium Acquisitions</h2>
    
    <div class="listing-grid-empty">
        No active assets deployed by the administrator at this moment. Real-time entries will update automatically.
    </div>
</div>

</body>
</html>
