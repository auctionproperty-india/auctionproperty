<?php
if(session_status() == PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar {
            height: 100vh; width: 250px; position: fixed; top:0; left:0;
            background: #1e2a3a; color: #b0c4de;
            padding-top: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        }
        .sidebar h4 { color: #fff; text-align: center; border-bottom: 1px solid #2c3e50; padding-bottom: 15px; }
        .sidebar a {
            display: block; padding: 12px 20px; color: #b0c4de;
            text-decoration: none; border-left: 3px solid transparent;
            font-size: 15px;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #111b26; color: #fff; border-left-color: #3498db;
        }
        .sidebar a i { width: 25px; }
        .main-content { margin-left: 250px; padding: 20px; }
        .top-bar {
            background: #fff; padding: 15px 25px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 25px;
        }
        .badge-role { font-size: 12px; padding: 4px 10px; }
    </style>
</head>
<body>
<div class="sidebar">
    <h4>🏠 PropertyDeal</h4>
    <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="properties.php"><i class="fas fa-building"></i> Properties</a>
    <?php if($_SESSION['role'] == 'admin'): ?>
    <a href="users.php"><i class="fas fa-users-cog"></i> Users & Admins</a>
    <?php endif; ?>
    <hr style="border-color:#2c3e50;">
    <a href="logout.php" style="color:#e74c3c;"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>
<div class="main-content">
    <div class="top-bar">
        <span><strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> 
            <span class="badge bg-<?= ($_SESSION['role']=='admin')?'danger':'info' ?> badge-role"><?= strtoupper($_SESSION['role']) ?></span>
        </span>
        <span><?= date('d M Y, h:i A') ?></span>
    </div>
