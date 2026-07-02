<?php
// ============================================================
// ✅ Header – सभी Pages का Common Header
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$role = $_SESSION['role'] ?? 'user';

// Super Admin Check
$is_super_admin = false;
if(isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if($row && $row['is_super_admin']) {
        $is_super_admin = true;
        $_SESSION['is_super_admin'] = true;
    } else {
        $_SESSION['is_super_admin'] = false;
    }
}

// ---- User Data for Sidebar Stats ----
$user_sidebar = null;
$wallet_balance = 0;
$total_pending = 0;
$total_paid = 0;
$reg_date = '';
$activation_date = 'Not Active';

if($role == 'user') {
    $user_id = $_SESSION['user_id'];
    // User data
    $stmt = $pdo->prepare("SELECT name, email, created_at as reg_date FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_sidebar = $stmt->fetch();
    $reg_date = !empty($user_sidebar['reg_date']) ? date('d M Y', strtotime($user_sidebar['reg_date'])) : 'N/A';

    // Wallet balance
    $wallet_balance = getUserWalletBalance($pdo, $user_id);

    // Referral earnings
    $earnings = getReferralEarnings($pdo, $user_id, 'pending');
    $paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
    $total_pending = array_sum(array_column($earnings, 'amount'));
    $total_paid = array_sum(array_column($paid_earnings, 'net_amount'));

    // Activation date (from active subscription)
    $sub = $pdo->prepare("SELECT start_date FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE ORDER BY id DESC LIMIT 1");
    $sub->execute([$user_id]);
    $sub_info = $sub->fetch();
    $activation_date = $sub_info ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
}
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7fc; overflow-x: hidden; transition: background 0.3s; }
        body.role-admin { background: #0f172a; }
        body.role-user { background: #f0f5fa; }
        .sidebar { height: 100vh; width: 280px; position: fixed; top:0; left:0; padding: 30px 15px; box-shadow: 4px 0 25px rgba(0,0,0,0.15); z-index: 1050; transition: transform 0.3s ease-in-out, background 0.3s; overflow-y: auto; }
        body.role-admin .sidebar { background: linear-gradient(180deg, #0b1120 0%, #1a2332 100%); color: #94a3b8; }
        body.role-user .sidebar { background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); color: #334155; border-right: 1px solid #e2e8f0; }
        @media (max-width: 991px) { .sidebar { transform: translateX(-100%); } .sidebar.show { transform: translateX(0); } }
        @media (min-width: 992px) { .sidebar { transform: translateX(0) !important; } }
        .sidebar .brand { font-size: 24px; font-weight: 800; text-align: center; padding-bottom: 25px; border-bottom: 1px solid #2a3a52; margin-bottom: 25px; letter-spacing: 1px; }
        body.role-admin .sidebar .brand { color: #ffffff; }
        body.role-admin .sidebar .brand i { color: #fbbf24; }
        body.role-user .sidebar .brand { color: #0f172a; }
        body.role-user .sidebar .brand i { color: #10b981; }
        .sidebar a { display: flex; align-items: center; padding: 12px 20px; margin: 4px 0; text-decoration: none; border-radius: 12px; font-weight: 500; font-size: 15px; transition: all 0.3s ease; border-left: 3px solid transparent; }
        body.role-admin .sidebar a { color: #94a3b8; }
        body.role-user .sidebar a { color: #475569; }
        .sidebar a i { width: 28px; font-size: 18px; transition: all 0.3s; }
        body.role-admin .sidebar a i { color: #64748b; }
        body.role-user .sidebar a i { color: #94a3b8; }
        .sidebar a:hover { background: #1e2a41; color: #ffffff; }
        .sidebar a:hover i { color: #fbbf24; }
        body.role-user .sidebar a:hover { background: #e2e8f0; color: #0f172a; }
        body.role-user .sidebar a:hover i { color: #10b981; }
        .sidebar a.active { background: #1e2a41; color: #ffffff; border-left-color: #fbbf24; }
        body.role-user .sidebar a.active { background: #10b981; color: #ffffff; border-left-color: #059669; }
        .sidebar a.active i { color: #fbbf24; }
        body.role-user .sidebar a.active i { color: #ffffff; }
        .sidebar .logout-link { margin-top: 30px; border-top: 1px solid #2a3a52; padding-top: 20px; color: #ef4444; }
        body.role-user .sidebar .logout-link { border-top-color: #e2e8f0; }
        .sidebar .logout-link i { color: #ef4444; }
        .main-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; transition: margin-left 0.3s; }
        @media (max-width: 991px) { .main-content { margin-left: 0; padding: 15px; } }
        .top-bar { padding: 15px 20px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 10px; transition: all 0.3s; }
        body.role-admin .top-bar { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; }
        body.role-user .top-bar { background: #ffffff; border: 1px solid rgba(0,0,0,0.02); box-shadow: 0 4px 15px rgba(0,0,0,0.03); color: #0f172a; }
        .top-bar .user-info { display: flex; align-items: center; gap: 12px; }
        .top-bar .user-info .name { font-weight: 700; font-size: 16px; }
        body.role-admin .top-bar .user-info .name { color: #f8fafc; }
        body.role-user .top-bar .user-info .name { color: #0f172a; }
        .top-bar .badge-role { padding: 4px 14px; border-radius: 30px; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
        .hamburger-btn { background: transparent; border: none; font-size: 28px; padding: 5px 10px; display: none; cursor: pointer; }
        body.role-admin .hamburger-btn { color: #e2e8f0; }
        body.role-user .hamburger-btn { color: #1e293b; }
        @media (max-width: 991px) { .hamburger-btn { display: block; } }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1040; }
        .sidebar-overlay.show { display: block; }
        .card-premium { border-radius: 20px; border: none; padding: 20px 24px; margin-bottom: 20px; transition: transform 0.2s, box-shadow 0.2s; }
        body.role-admin .card-premium { background: #1e293b; color: #e2e8f0; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
        body.role-user .card-premium { background: #ffffff; color: #0f172a; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); }
        .card-premium:hover { transform: translateY(-2px); box-shadow: 0 20px 30px -10px rgba(0,0,0,0.1); }
        .stat-icon { width: 50px; height: 50px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        body.role-admin .stat-icon.bg-soft-primary { background: #1e3a5f; color: #60a5fa; }
        body.role-admin .stat-icon.bg-soft-success { background: #14532d; color: #34d399; }
        body.role-admin .stat-icon.bg-soft-warning { background: #713f12; color: #fbbf24; }
        body.role-user .stat-icon.bg-soft-primary { background: #dbeafe; color: #2563eb; }
        body.role-user .stat-icon.bg-soft-success { background: #d1fae5; color: #059669; }
        body.role-user .stat-icon.bg-soft-warning { background: #fef3c7; color: #d97706; }
        body.role-admin .table { color: #e2e8f0; }
        body.role-admin .table-light { background: #334155; color: #f8fafc; }
        body.role-admin .table-hover tbody tr:hover { background: #2d3748; }
        body.role-user .table-light { background: #f1f5f9; }
        body.role-user .table-hover tbody tr:hover { background: #f8fafc; }
        .btn { border-radius: 10px; font-weight: 600; padding: 8px 16px; font-size: 14px; }
        .btn-primary { background: #2563eb; border: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .user-welcome-banner { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 24px; padding: 30px; color: white; margin-bottom: 25px; box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3); }
        .user-welcome-banner h2 { font-weight: 800; }
        .user-welcome-banner p { opacity: 0.9; }
        @media (max-width: 576px) { .top-bar .user-info .name { font-size: 14px; } .card-premium { padding: 15px; } .stat-icon { width: 40px; height: 40px; font-size: 18px; } .hide-on-mobile { display: none; } .user-welcome-banner { padding: 20px; } }

        /* Sidebar stats styling */
        .sidebar-stats {
            background: rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid rgba(255,255,255,0.08);
        }
        body.role-user .sidebar-stats {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        .sidebar-stats .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-size: 0.85rem;
        }
        .sidebar-stats .stat-item:last-child { border-bottom: none; }
        .sidebar-stats .stat-item .label { opacity: 0.7; }
        .sidebar-stats .stat-item .value { font-weight: 700; }
        .sidebar-user-name {
            font-weight: 700;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 5px;
        }
        .sidebar-user-email {
            font-size: 0.75rem;
            opacity: 0.7;
            text-align: center;
            margin-bottom: 10px;
        }
        .sidebar-date-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            padding: 4px 0;
            opacity: 0.8;
        }
    </style>
</head>
<body class="role-<?= $role ?>">

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <div class="brand"><i class="fas fa-building"></i> <span>Prime Property</span></div>
    
    <?php if($role == 'admin'): ?>
        <a href="admin_dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <?php if(hasViewPermission('properties', $pdo)): ?>
            <a href="properties.php"><i class="fas fa-edit"></i> <span>Manage Properties</span></a>
        <?php endif; ?>
        <?php if($is_super_admin): ?>
            <a href="admin_dashboard.php#users-section"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
            <a href="admin_permissions.php"><i class="fas fa-user-shield"></i> <span>Sub-Admins</span></a>
        <?php endif; ?>
        <?php if(hasViewPermission('packages', $pdo)): ?>
            <a href="admin_packages.php"><i class="fas fa-tags"></i> <span>Packages</span></a>
        <?php endif; ?>
        <?php if(hasViewPermission('subscriptions', $pdo)): ?>
            <a href="admin_subscriptions.php"><i class="fas fa-user-check"></i> <span>Pending Subscriptions</span></a>
            <a href="admin_subscription_history.php"><i class="fas fa-history"></i> <span>Subscription History</span></a>
        <?php endif; ?>
        <?php if(hasViewPermission('referrals', $pdo)): ?>
            <a href="admin_referrals.php"><i class="fas fa-hand-holding-usd"></i> <span>Referral Payouts</span></a>
        <?php endif; ?>
        <?php if(hasViewPermission('accounting', $pdo)): ?>
            <a href="admin_accounting.php"><i class="fas fa-wallet"></i> <span>Accounting</span></a>
        <?php endif; ?>
        <?php if(hasViewPermission('settings', $pdo)): ?>
            <a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- User Sidebar -->
        <a href="user_dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="user_packages.php"><i class="fas fa-search-dollar"></i> <span>Buy Search Engine</span></a>
        <a href="user_team.php"><i class="fas fa-users"></i> <span>My Team</span></a>
        <a href="user_subscription_history.php"><i class="fas fa-history"></i> <span>Payment History</span></a>
        <a href="user_referrals.php"><i class="fas fa-link"></i> <span>Referrals</span></a>
        <a href="change_password.php"><i class="fas fa-key"></i> <span>Change Password</span></a>

        <!-- ====== Sidebar Stats for User ====== -->
        <div class="sidebar-stats">
            <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
            <div class="sidebar-user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
            <div class="sidebar-date-row">
                <span>📅 Reg: <?= $reg_date ?></span>
                <span>✅ Act: <?= $activation_date ?></span>
            </div>
            <hr style="margin: 8px 0; opacity:0.2;">
            <div class="stat-item"><span class="label">💰 Wallet</span><span class="value">₹ <?= indianCurrencyFormat($wallet_balance) ?></span></div>
            <div class="stat-item"><span class="label">⏳ Pending</span><span class="value">₹ <?= indianCurrencyFormat($total_pending) ?></span></div>
            <div class="stat-item"><span class="label">✅ Paid</span><span class="value">₹ <?= indianCurrencyFormat($total_paid) ?></span></div>
        </div>
        <!-- ====== End Sidebar Stats ====== -->
    <?php endif; ?>
    
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
