<?php
if(session_status() == PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>PropertyDeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f7fc;
            overflow-x: hidden;
        }

        /* ---- SIDEBAR (Desktop & Mobile) ---- */
        .sidebar {
            height: 100vh; width: 280px; position: fixed; top:0; left:0;
            background: linear-gradient(180deg, #0b1120 0%, #1a2332 100%);
            color: #a3b1cc;
            padding: 30px 15px;
            box-shadow: 4px 0 25px rgba(0,0,0,0.15);
            z-index: 1050;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
        }
        /* Mobile में Sidebar छिपा रहेगा */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
        }
        @media (min-width: 992px) {
            .sidebar {
                transform: translateX(0) !important;
            }
        }

        .sidebar .brand {
            font-size: 24px; font-weight: 700; color: #fff;
            text-align: center; letter-spacing: 1px;
            padding-bottom: 25px; border-bottom: 1px solid #2a3a52;
            margin-bottom: 25px;
        }
        .sidebar .brand i { color: #fbbf24; margin-right: 10px; }
        .sidebar a {
            display: flex; align-items: center;
            padding: 12px 20px; margin: 4px 0;
            color: #94a3b8; text-decoration: none;
            border-radius: 12px; font-weight: 500; font-size: 15px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        .sidebar a i { width: 28px; font-size: 18px; color: #64748b; transition: all 0.3s; }
        .sidebar a:hover { background: #1e2a41; color: #ffffff; }
        .sidebar a:hover i { color: #fbbf24; }
        .sidebar a.active {
            background: #1e2a41; color: #ffffff;
            border-left-color: #fbbf24;
        }
        .sidebar a.active i { color: #fbbf24; }
        .sidebar .logout-link {
            margin-top: 30px; border-top: 1px solid #2a3a52; padding-top: 20px;
            color: #ef4444;
        }
        .sidebar .logout-link i { color: #ef4444; }

        /* ---- MAIN CONTENT ---- */
        .main-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }
        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
                padding: 15px 15px 30px 15px;
            }
        }

        /* ---- TOP BAR ---- */
        .top-bar {
            background: #ffffff;
            padding: 15px 20px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.02);
            flex-wrap: wrap;
            gap: 10px;
        }
        .top-bar .user-info {
            display: flex; align-items: center; gap: 12px;
        }
        .top-bar .user-info .name {
            font-weight: 600; color: #0f172a; font-size: 16px;
        }
        .top-bar .badge-role {
            padding: 4px 12px; border-radius: 30px; font-size: 10px; font-weight: 700;
            letter-spacing: 0.5px; text-transform: uppercase;
        }
        .hamburger-btn {
            background: transparent; border: none; font-size: 28px;
            color: #1e293b; padding: 5px 10px;
            display: none; cursor: pointer;
        }
        @media (max-width: 991px) {
            .hamburger-btn { display: block; }
        }

        /* ---- Overlay (Mobile) ---- */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 1040;
        }
        .sidebar-overlay.show { display: block; }

        /* ---- Cards & Tables Responsive ---- */
        .card-premium {
            background: #ffffff;
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            padding: 20px 24px;
            margin-bottom: 20px;
        }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 15px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; flex-shrink: 0;
        }
        .bg-soft-primary { background: #e0f2fe; color: #0284c7; }
        .bg-soft-success { background: #dcfce7; color: #16a34a; }
        .bg-soft-warning { background: #fef3c7; color: #d97706; }
        .table-responsive-custom {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .btn { border-radius: 10px; font-weight: 600; padding: 8px 16px; font-size: 14px; }
        .btn-primary { background: #2563eb; border: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* Mobile Extra Tweaks */
        @media (max-width: 576px) {
            .top-bar .user-info .name { font-size: 14px; }
            .card-premium { padding: 15px; }
            .stat-icon { width: 40px; height: 40px; font-size: 18px; }
            .hide-on-mobile { display: none; }
        }
    </style>
</head>
<body>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<div class="sidebar" id="mainSidebar">
    <div class="brand"><i class="fas fa-building"></i> <span>PropertyDeal</span></div>
    
    <a href="dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
    
    <?php if($role == 'admin'): ?>
        <a href="properties.php"><i class="fas fa-edit"></i> <span>Manage Properties</span></a>
        <a href="dashboard.php#users-section"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
    <?php else: ?>
        <a href="my_purchases.php"><i class="fas fa-shopping-bag"></i> <span>My Purchases</span></a>
        <a href="my_referrals.php"><i class="fas fa-link"></i> <span>Referrals</span></a>
    <?php endif; ?>
    
    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="top-bar">
        <div class="d-flex align-items-center gap-2">
            <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-info">
                <i class="fas fa-user-circle" style="font-size:32px; color:#2563eb;"></i>
                <div>
                    <div class="name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                    <span class="badge-role badge <?= ($role=='admin')?'bg-danger':'bg-info' ?>"><?= strtoupper($role) ?></span>
                </div>
            </div>
        </div>
        <div class="hide-on-mobile">
            <i class="far fa-calendar-alt me-2"></i> <?= date('d M Y, h:i A') ?>
        </div>
    </div>
