<?php
// ============================================================
// ✅ Header – Top Nav only on Home Page + Proper Spacing
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// करंट पेज का फ़ाइल नाम
$current_page = basename($_SERVER['PHP_SELF']);

// ✅ Top nav केवल home page (index.php) पर दिखे – बाकी सब जगह छिपा
$hide_top_nav = ($current_page != 'index.php');

// क्या यूजर लॉगिन है?
$is_logged_in = isset($_SESSION['user_id']);
$role = $is_logged_in ? ($_SESSION['role'] ?? 'user') : 'guest';

// सुपर एडमिन चेक
$is_super_admin = false;
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if ($row && $row['is_super_admin']) {
        $is_super_admin = true;
        $_SESSION['is_super_admin'] = true;
    } else {
        $_SESSION['is_super_admin'] = false;
    }
}

// ---- डेटाबेस से नेविगेशन आइटम लोड करें ----
$nav_items = $pdo->query("SELECT * FROM navigation_items WHERE is_active = TRUE ORDER BY display_order")->fetchAll();

// ---- लॉगिन यूजर के लिए टॉप बार डेटा ----
$reg_date = '';
$activation_date = 'Not Active';
$expiry_date = null;
$days_left = 0;

if ($is_logged_in && $role == 'user') {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT name, email, created_at as reg_date FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_sidebar = $stmt->fetch();
    $reg_date = !empty($user_sidebar['reg_date']) ? date('d M Y', strtotime($user_sidebar['reg_date'])) : 'N/A';

    $sub = $pdo->prepare("SELECT start_date, end_date FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE ORDER BY id DESC LIMIT 1");
    $sub->execute([$user_id]);
    $sub_info = $sub->fetch();
    if ($sub_info) {
        $activation_date = date('d M Y', strtotime($sub_info['start_date']));
        $expiry_date = $sub_info['end_date'];
        $days_left = (int) ((strtotime($expiry_date) - time()) / (60 * 60 * 24));
        $days_left = max(0, $days_left);
    } else {
        $activation_date = 'Not Active';
        $expiry_date = null;
        $days_left = 0;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Prime Property India</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ====== ग्लोबल ====== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7fc; overflow-x: hidden; }
        /* ✅ Body padding – top nav के लिए (70px), लेकिन जब top nav छिपा हो तो 0 */
        body { padding-top: 70px; }
        body.top-nav-hidden { padding-top: 0; }
        body.role-admin { background: #f8fafc; }
        body.role-user { background: #f0f5fa; }
        body.role-guest { background: #f8fafc; }

        /* ====== स्टिकी नेविगेशन – केवल home page पर ====== */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background: #1e293b;
            padding: 8px 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px 15px;
            border-bottom: 1px solid #334155;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        /* ✅ जब top nav छिपा हो तो उसे display none */
        body.top-nav-hidden .top-nav {
            display: none !important;
        }
        .top-nav a {
            color: #94a3b8;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .top-nav a:hover,
        .top-nav a.active-nav {
            color: #f8fafc;
            background: #2d3748;
        }
        .top-nav a i { color: #fbbf24; }
        .top-nav .nav-brand {
            color: #f8fafc;
            font-weight: 700;
            font-size: 18px;
            margin-right: 15px;
        }
        .top-nav .nav-brand i { color: #fbbf24; }
        .top-nav .nav-right {
            margin-left: auto;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .top-nav .nav-right a {
            padding: 6px 14px;
            border-radius: 20px;
        }
        .top-nav .nav-right .btn-login {
            background: #2563eb;
            color: #fff !important;
        }
        .top-nav .nav-right .btn-login:hover { background: #1d4ed8; color: #fff !important; }
        .top-nav .nav-right .btn-register {
            border: 1px solid #2563eb;
            color: #60a5fa !important;
        }
        .top-nav .nav-right .btn-register:hover {
            background: #2563eb;
            color: #fff !important;
        }
        .top-nav .nav-right .user-badge {
            color: #94a3b8;
            font-size: 13px;
        }
        .top-nav .nav-right .user-badge i { color: #fbbf24; }

        @media (max-width: 768px) {
            .top-nav .nav-brand { font-size: 16px; }
            .top-nav a { font-size: 13px; padding: 6px 10px; }
            .top-nav .nav-right .btn-login,
            .top-nav .nav-right .btn-register { padding: 4px 12px; font-size: 12px; }
        }

        /* ====== साइडबार – Admin White/Blue Theme ====== */
        .sidebar {
            height: 100vh;
            width: 280px;
            position: fixed;
            top: 0;
            left: 0;
            padding: 30px 15px;
            box-shadow: 2px 0 12px rgba(0,0,0,0.06);
            z-index: 1050;
            transition: transform 0.3s ease-in-out, background 0.3s;
            overflow-y: auto;
        }
        /* ✅ जब top nav दिख रहा हो तो sidebar top: 70px, otherwise top: 0 */
        body:not(.top-nav-hidden) .sidebar {
            top: 70px;
        }
        body.top-nav-hidden .sidebar {
            top: 0;
        }
        body.role-admin .sidebar {
            background: #ffffff;
            color: #1e293b;
            border-right: 1px solid #e2e8f0;
        }
        body.role-user .sidebar {
            background: #ffffff;
            color: #334155;
            border-right: 1px solid #e2e8f0;
        }
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); top: 0 !important; }
            .sidebar.show { transform: translateX(0); }
        }
        @media (min-width: 992px) { .sidebar { transform: translateX(0) !important; } }

        .sidebar .brand {
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            padding-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 25px;
            letter-spacing: 1px;
            color: #1e293b;
        }
        .sidebar .brand i { color: #1e3a8a; }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            margin: 4px 0;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            color: #475569;
        }
        .sidebar a i {
            width: 28px;
            font-size: 18px;
            transition: all 0.3s;
            color: #94a3b8;
        }
        .sidebar a:hover {
            background: #f1f5f9;
            color: #1e3a8a;
        }
        .sidebar a:hover i { color: #1e3a8a; }
        .sidebar a.active {
            background: #eef2ff;
            color: #1e3a8a;
            border-left-color: #1e3a8a;
        }
        .sidebar a.active i { color: #1e3a8a; }

        .sidebar .logout-link {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            color: #dc2626 !important;
        }
        .sidebar .logout-link i { color: #dc2626 !important; }
        .sidebar .logout-link:hover {
            background: #fef2f2 !important;
            color: #b91c1c !important;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 1040;
        }
        .sidebar-overlay.show { display: block; }

        /* ====== मुख्य कंटेंट ====== */
        .main-content {
            padding: 30px 35px;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }
        body.role-admin .main-content {
            padding-top: 0 !important;
        }
        body.role-admin .main-content,
        body.role-user .main-content {
            margin-left: 280px;
        }
        body.role-guest .main-content {
            margin-left: 0 !important;
        }
        @media (max-width: 991px) {
            .main-content {
                margin-left: 0 !important;
                padding: 15px;
            }
        }

        /* ====== टॉप बार (यूजर इन्फो) ====== */
        .top-bar {
            padding: 15px 20px;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 10px;
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.02);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            color: #0f172a;
        }
        body.role-admin .top-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 10px;
        }
        .top-bar .user-info { display: flex; align-items: center; gap: 12px; }
        .top-bar .user-info .name { font-weight: 700; font-size: 16px; }
        body.role-admin .top-bar .user-info .name { color: #0f172a; }
        body.role-user .top-bar .user-info .name { color: #0f172a; }
        .top-bar .badge-role {
            padding: 4px 14px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .top-bar .user-dates {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 2px;
            color: inherit;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .top-bar .countdown-timer {
            font-weight: 700 !important;
            color: #dc3545 !important;
            background: rgba(220, 53, 69, 0.1);
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .hamburger-btn {
            background: transparent;
            border: none;
            font-size: 28px;
            padding: 5px 10px;
            display: none;
            cursor: pointer;
        }
        body.role-admin .hamburger-btn { color: #1e293b; }
        body.role-user .hamburger-btn { color: #1e293b; }
        @media (max-width: 991px) { .hamburger-btn { display: block; } }

        /* ====== नोटिफिकेशन ====== */
        .notification-dropdown { position: relative; display: inline-block; }
        .notification-dropdown .dropdown-menu {
            min-width: 350px;
            max-height: 400px;
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0;
            margin-top: 8px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .notification-dropdown .dropdown-item {
            color: #1e293b;
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
            white-space: normal;
            font-size: 0.85rem;
        }
        .notification-dropdown .dropdown-item:hover { background: #f8fafc; color: #0f172a; }
        .notification-dropdown .dropdown-item:last-child { border-bottom: none; }
        .notification-dropdown .dropdown-header {
            color: #475569;
            padding: 10px 16px;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 12px 12px 0 0;
        }
        .notification-dropdown .badge-notify {
            position: absolute;
            top: -6px;
            right: -8px;
            background: #dc2626;
            color: #fff;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
        }
        .notification-dropdown .btn-notify {
            background: transparent;
            border: none;
            color: #1e293b;
            font-size: 1.4rem;
            padding: 4px 8px;
            position: relative;
            cursor: pointer;
        }
        .notification-dropdown .btn-notify:hover { color: #1e3a8a; }
        .no-notification { color: #94a3b8; padding: 20px; text-align: center; }
        @media (max-width: 576px) {
            .notification-dropdown .dropdown-menu { min-width: 280px; right: -10px; }
        }

        /* ====== कार्ड / स्टेट्स ====== */
        .card-premium {
            border-radius: 20px;
            border: none;
            padding: 20px 24px;
            margin-bottom: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        body.role-admin .card-premium {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        body.role-user .card-premium {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }
        .card-premium:hover { transform: translateY(-2px); box-shadow: 0 20px 30px -10px rgba(0,0,0,0.08); }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        body.role-admin .stat-icon.bg-soft-primary { background: #eef2ff; color: #1e3a8a; }
        body.role-admin .stat-icon.bg-soft-success { background: #dcfce7; color: #166534; }
        body.role-admin .stat-icon.bg-soft-warning { background: #fef3c7; color: #92400e; }
        body.role-user .stat-icon.bg-soft-primary { background: #dbeafe; color: #2563eb; }
        body.role-user .stat-icon.bg-soft-success { background: #d1fae5; color: #059669; }
        body.role-user .stat-icon.bg-soft-warning { background: #fef3c7; color: #d97706; }
        .btn { border-radius: 10px; font-weight: 600; padding: 8px 16px; font-size: 14px; }
        .btn-primary { background: #1e3a8a; border: none; }
        .btn-primary:hover { background: #1e40af; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        .user-welcome-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            border-radius: 24px;
            padding: 30px;
            color: white;
            margin-bottom: 25px;
            box-shadow: 0 10px 25px -5px rgba(37,99,235,0.3);
        }
        .user-welcome-banner h2 { font-weight: 800; }
        .user-welcome-banner p { opacity: 0.9; }
        @media (max-width: 576px) {
            .top-bar .user-info .name { font-size: 14px; }
            .card-premium { padding: 15px; }
            .stat-icon { width: 40px; height: 40px; font-size: 18px; }
        }
    </style>
</head>
<body class="role-<?= $is_logged_in ? $role : 'guest' ?> <?= $hide_top_nav ? 'top-nav-hidden' : '' ?>">

<!-- ====== स्टिकी नेविगेशन – केवल Home Page पर दिखे ====== -->
<?php if (!$hide_top_nav): ?>
<nav class="top-nav">
    <span class="nav-brand"><i class="fas fa-building"></i> Prime Property India</span>
    <?php foreach ($nav_items as $item): ?>
        <?php
            $is_active = false;
            $current_uri = $_SERVER['REQUEST_URI'];
            if ($item['url'] == '/' && $current_uri == '/') {
                $is_active = true;
            } elseif ($item['url'] != '/' && strpos($current_uri, $item['url']) !== false) {
                $is_active = true;
            }
        ?>
        <a href="<?= htmlspecialchars($item['url']) ?>" class="<?= $is_active ? 'active-nav' : '' ?>">
            <?php if ($item['icon']): ?><i class="<?= htmlspecialchars($item['icon']) ?>"></i><?php endif; ?>
            <?= htmlspecialchars($item['label']) ?>
        </a>
    <?php endforeach; ?>
    <div class="nav-right">
        <?php if ($is_logged_in): ?>
            <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
            <a href="logout.php" style="color:#ef4444;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php" class="btn-register"><i class="fas fa-user-plus"></i> Register</a>
        <?php endif; ?>
    </div>
</nav>
<?php endif; ?>

<!-- ====== साइडबार – केवल लॉगिन यूजर के लिए ====== -->
<?php if ($is_logged_in): ?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <div class="brand"><i class="fas fa-building"></i> <span>Prime Property India</span></div>
    
    <?php if ($role == 'admin'): ?>
        <a href="admin_dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <?php if (hasViewPermission('properties', $pdo)): ?>
            <a href="properties.php"><i class="fas fa-edit"></i> <span>Manage Properties</span></a>
        <?php endif; ?>
        <?php if ($is_super_admin): ?>
            <a href="admin_dashboard.php#users-section"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
            <a href="admin_permissions.php"><i class="fas fa-user-shield"></i> <span>Sub-Admins</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('packages', $pdo)): ?>
            <a href="admin_packages.php"><i class="fas fa-tags"></i> <span>Packages</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('subscriptions', $pdo)): ?>
            <a href="admin_subscriptions.php"><i class="fas fa-user-check"></i> <span>Pending Subscriptions</span></a>
            <a href="admin_subscription_history.php"><i class="fas fa-history"></i> <span>Subscription History</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('referrals', $pdo)): ?>
            <a href="admin_referrals.php"><i class="fas fa-hand-holding-usd"></i> <span>Referral Payouts</span></a>
        <?php endif; ?>
        <a href="admin_deductions.php"><i class="fas fa-percent"></i> <span>Deductions</span></a>
        <a href="admin_activity_logs.php"><i class="fas fa-clock"></i> <span>Activity Logs</span></a>
        <?php if (hasViewPermission('accounting', $pdo)): ?>
            <a href="admin_accounting.php"><i class="fas fa-wallet"></i> <span>Accounting</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('settings', $pdo)): ?>
            <a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <?php endif; ?>
        <a href="admin_kyc.php"><i class="fas fa-id-card"></i> <span>KYC Verification</span></a>
        <a href="support_admin.php"><i class="fas fa-headset"></i> <span>Support Tickets</span></a>
        <a href="admin_user_properties.php"><i class="fas fa-home"></i> <span>User Properties</span></a>
        <a href="properties.php?filter_city=Dholera Smart City"><i class="fas fa-city"></i> <span>Dholera Properties</span></a>
        <a href="admin_navigation.php"><i class="fas fa-bars"></i> <span>Navigation Manager</span></a>
    <?php else: ?>
        <a href="user_dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="user_packages.php"><i class="fas fa-search-dollar"></i> <span>Buy Search Engine</span></a>
        <a href="user_team.php"><i class="fas fa-users"></i> <span>My Team</span></a>
        <a href="user_subscription_history.php"><i class="fas fa-history"></i> <span>Payment History</span></a>
        <a href="user_referrals.php"><i class="fas fa-link"></i> <span>Referrals</span></a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
        <a href="support.php"><i class="fas fa-headset"></i> <span>Support</span></a>
        <a href="user_properties.php"><i class="fas fa-home"></i> <span>My Properties</span></a>
        <a href="change_password.php"><i class="fas fa-key"></i> <span>Change Password</span></a>
    <?php endif; ?>
    
    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>
<?php endif; ?>

<div class="main-content">
    <!-- ====== टॉप बार (यूजर इन्फो) – सभी लॉगिन पेजों पर ====== -->
    <?php if ($is_logged_in): ?>
    <div class="top-bar">
        <div class="d-flex align-items-center gap-2">
            <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-info">
                <i class="fas fa-user-circle" style="font-size:32px; <?= ($role=='admin')?'color:#1e3a8a;':'color:#10b981;' ?>"></i>
                <div>
                    <div class="name"><?= htmlspecialchars($_SESSION['user_name']) ?>
                        <span class="badge-role badge <?= ($role=='admin')?'bg-primary':'bg-success' ?>"><?= strtoupper($role) ?></span>
                    </div>
                    <?php if ($role == 'user'): ?>
                        <div class="user-dates">
                            <span>📅 Reg: <?= $reg_date ?></span>
                            <span>✅ Act: 
                                <?php if ($expiry_date): ?>
                                    <?= $activation_date ?>
                                    <span class="countdown-timer" id="countdownDisplay" data-expiry="<?= $expiry_date ?>">
                                        <i class="fas fa-clock"></i> Loading...
                                    </span>
                                <?php else: ?>
                                    Not Active
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- नोटिफिकेशन बेल (केवल एडमिन) -->
        <?php if ($role == 'admin'): ?>
        <div class="notification-dropdown">
            <button class="btn-notify" id="notifyToggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <?php if (isset($notification_count) && $notification_count > 0): ?>
                    <span class="badge-notify"><?= $notification_count ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifyToggle">
                <li class="dropdown-header">🔔 Notifications</li>
                <?php if (isset($notifications) && count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <li><a class="dropdown-item" href="<?= $notif['link'] ?>"><?= $notif['message'] ?></a></li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center text-muted small" href="#">Mark all as read</a></li>
                <?php else: ?>
                    <li class="no-notification">✨ No new notifications</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php
// बाकी पेज का कंटेंट यहाँ आएगा – footer.php में main-content और body बंद करें
?>
