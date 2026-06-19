<?php
if(session_status() == PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'db.php';
require_once 'functions.php';

$role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Prime Property</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>/* Your existing styles - same as before */</style>
</head>
<body class="role-<?= $role ?>">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <div class="brand"><i class="fas fa-building"></i> <span>Prime Property</span></div>
    <a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
    
    <?php if($role == 'admin'): ?>
        <?php if(hasPermission('properties', $pdo)): ?>
            <a href="properties.php"><i class="fas fa-edit"></i> <span>Manage Properties</span></a>
        <?php endif; ?>
        <?php if(hasPermission('users', $pdo)): ?>
            <a href="dashboard.php#users-section"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
            <a href="admin_permissions.php"><i class="fas fa-user-shield"></i> <span>Sub-Admins</span></a>
        <?php endif; ?>
        <?php if(hasPermission('packages', $pdo)): ?>
            <a href="admin_packages.php"><i class="fas fa-tags"></i> <span>Packages</span></a>
        <?php endif; ?>
        <?php if(hasPermission('subscriptions', $pdo)): ?>
            <a href="admin_subscriptions.php"><i class="fas fa-user-check"></i> <span>Subscriptions</span></a>
        <?php endif; ?>
        <?php if(hasPermission('settings', $pdo)): ?>
            <a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <?php endif; ?>
    <?php else: ?>
        <a href="index.php"><i class="fas fa-home"></i> <span>Explore Properties</span></a>
        <a href="#"><i class="fas fa-heart"></i> <span>My Favorites</span></a>
    <?php endif; ?>
    
    <a href="change_password.php"><i class="fas fa-key"></i> <span>Change Password</span></a>
    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>

<div class="main-content">
    <div class="top-bar">
        <div class="d-flex align-items-center gap-2">
            <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-info">
                <i class="fas fa-user-circle" style="font-size:32px; <?= ($role=='admin')?'color:#60a5fa;':'color:#10b981;' ?>"></i>
                <div>
                    <div class="name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                    <span class="badge-role badge <?= ($role=='admin')?'bg-danger':'bg-success' ?>"><?= strtoupper($role) ?></span>
                </div>
            </div>
        </div>
        <div class="hide-on-mobile">
            <i class="far fa-calendar-alt me-2"></i> <?= date('d M Y, h:i A') ?>
        </div>
    </div>
