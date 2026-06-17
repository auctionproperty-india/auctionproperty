<?php
session_start();
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub_admin')) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Executive Control Center - Prime Property</title>
    <style>
        :root {
            --bg-dark: #0f172a;
            --sidebar-bg: #1e293b;
            --accent-glow: #38bdf8;
            --text-primary: #f8fafc;
            --card-bg: #1e293b;
        }
        body { font-family: 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 0; background-color: var(--bg-dark); color: var(--text-primary); }
        .sidebar { width: 260px; background: var(--sidebar-bg); position: fixed; height: 100%; border-right: 1px solid #334155; padding-top: 20px; }
        .brand-box { text-align: center; padding: 20px 0; border-bottom: 1px solid #334155; margin-bottom: 20px; }
        .brand-title { font-size: 22px; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 1.5px; text-shadow: 0 0 15px var(--accent-glow); }
        .sidebar a { display: block; color: #94a3b8; padding: 14px 25px; text-decoration: none; font-weight: 600; transition: all 0.3s ease; }
        .sidebar a:hover, .sidebar a.active { background: #334155; color: var(--accent-glow); box-shadow: inset 4px 0 0 var(--accent-glow); }
        .main-content { margin-left: 280px; padding: 40px; }
        .header-panel { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 20px; }
        .welcome-text { font-size: 28px; font-weight: 700; background: linear-gradient(to right, #fff, var(--accent-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .badge-premium { background: rgba(56, 189, 248, 0.1); border: 1px solid var(--accent-glow); color: var(--accent-glow); padding: 5px 12px; border-radius: 20px; font-size: 12px; letter-spacing: 1px; box-shadow: 0 0 10px rgba(56, 189, 248, 0.3); }
        .panel-card { background: var(--card-bg); border: 1px solid #334155; padding: 30px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); margin-top: 30px; }
        .btn-logout { background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; transition: 0.3s; }
        .btn-logout:hover { box-shadow: 0 0 15px rgba(239, 68, 68, 0.5); }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand-box">
        <div class="brand-title">Prime Property</div>
    </div>
    <a href="#" class="active">📊 Control Dashboard</a>
    <a href="#">🏢 Manage Listings</a>
    <a href="#">👥 Verified Investors</a>
    <a href="profile.php">⚙️ Account Settings</a>
    <a href="login.php" style="color: #ef4444; margin-top: 100px;">🚪 Terminate Session</a>
</div>

<div class="main-content">
    <div class="header-panel">
        <div class="welcome-text">Console // <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        <div>
            <span class="badge-premium"><?php echo strtoupper($_SESSION['role']); ?> NODE</span>
        </div>
    </div>

    <div class="panel-card">
        <h2 style="margin-top: 0; color: #fff;">System Overview</h2>
        <p style="color: #94a3b8; line-height: 1.6;">Welcome to the command matrix of Prime Property. From this terminal, you can seamlessly broadcast premium real-estate listings and manage corporate auctions globally.</p>
    </div>
</div>

</body>
</html>
