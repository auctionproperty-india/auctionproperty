<?php
// ============================================================
// ✅ Header – Top Nav with Hamburger + Sidebar (Index, Login, Register)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Current page name
$current_page = basename($_SERVER['PHP_SELF']);

// 🔥 Show top nav on index, login, register – hide on others
$hide_top_nav = !in_array($current_page, ['index.php', 'login.php', 'register.php']);

$is_logged_in = isset($_SESSION['user_id']);
$role = $is_logged_in ? ($_SESSION['role'] ?? 'user') : 'guest';

// Super admin check
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

// ---- Fetch navigation items ----
$nav_items = $pdo->query("SELECT * FROM navigation_items WHERE is_active = TRUE ORDER BY display_order")->fetchAll();

// ---- Fetch social links ----
$social_links = $pdo->query("SELECT * FROM social_links WHERE is_active = TRUE ORDER BY display_order")->fetchAll();

// ---- User info for top bar ----
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
        /* ====== Global ====== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7fc; overflow-x: hidden; }
        body { padding-top: 70px; }
        body.top-nav-hidden { padding-top: 0; }
        body.role-admin { background: #f8fafc; }
        body.role-user { background: #f0f5fa; }
        body.role-guest { background: #f8fafc; }
        body.role-sales { background: #f0f5fa; }
        /* Luxury login/register background (optional) */
        body.page-login, body.page-register {
            background: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80') no-repeat center center fixed;
            background-size: cover;
        }

        /* ====== Top Navigation – Dark Blue Gradient ====== */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            height: 70px;
        }
        body.top-nav-hidden .top-nav {
            display: none !important;
        }

        .top-nav .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .top-nav .nav-brand .brand-icon {
            color: #fbbf24;
            font-size: 1.8rem;
        }
        .top-nav .nav-brand .brand-text {
            color: #ffffff;
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
        }
        .top-nav .nav-brand .brand-text span {
            color: #fbbf24;
        }

        .top-nav .hamburger {
            background: none;
            border: none;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            padding: 0 8px;
            transition: transform 0.2s;
        }
        .top-nav .hamburger:hover {
            transform: scale(1.1);
        }

        .top-nav .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .top-nav .nav-right a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        .top-nav .nav-right a:hover {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }
        .top-nav .nav-right .btn-login {
            background: #fbbf24;
            color: #0f172a !important;
            font-weight: 600;
        }
        .top-nav .nav-right .btn-login:hover {
            background: #fcd34d;
        }
        .top-nav .nav-right .btn-register {
            border: 1px solid rgba(255,255,255,0.3);
        }
        .top-nav .nav-right .btn-register:hover {
            background: rgba(255,255,255,0.1);
        }
        .top-nav .nav-right .user-badge {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
        }
        .top-nav .nav-right .user-badge i {
            color: #fbbf24;
        }

        @media (max-width: 768px) {
            .top-nav .nav-brand .brand-text { font-size: 1.1rem; }
            .top-nav .nav-right a { font-size: 0.8rem; padding: 4px 10px; }
        }

        /* ====== Hamburger Sidebar (Off-canvas) ====== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1050;
        }
        .sidebar-overlay.show { display: block; }

        .hamburger-sidebar {
            position: fixed;
            top: 0;
            left: -320px;
            width: 320px;
            height: 100%;
            background: #ffffff;
            z-index: 1060;
            transition: left 0.3s ease-in-out;
            box-shadow: 2px 0 20px rgba(0,0,0,0.15);
            padding: 25px 20px;
            overflow-y: auto;
        }
        .hamburger-sidebar.open {
            left: 0;
        }
        .hamburger-sidebar .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .hamburger-sidebar .sidebar-header .close-btn {
            background: none;
            border: none;
            font-size: 1.8rem;
            color: #475569;
            cursor: pointer;
        }
        .hamburger-sidebar .sidebar-header .brand-small {
            font-weight: 700;
            font-size: 1.2rem;
            color: #1e293b;
        }
        .hamburger-sidebar .sidebar-header .brand-small i {
            color: #1e3a8a;
        }

        .hamburger-sidebar .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .hamburger-sidebar .nav-list li {
            margin: 4px 0;
        }
        .hamburger-sidebar .nav-list li a {
            display: block;
            padding: 12px 16px;
            color: #475569;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .hamburger-sidebar .nav-list li a:hover {
            background: #f1f5f9;
            color: #1e3a8a;
        }
        .hamburger-sidebar .nav-list li a i {
            width: 28px;
            color: #94a3b8;
            margin-right: 10px;
        }

        /* 🔥 Auth links for non-logged users */
        .hamburger-sidebar .auth-links {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
        .hamburger-sidebar .auth-links a {
            display: block;
            padding: 10px 16px;
            color: #1e3a8a;
            font-weight: 600;
            text-decoration: none;
            border-radius: 8px;
            background: #f1f5f9;
            margin-bottom: 8px;
            text-align: center;
        }
        .hamburger-sidebar .auth-links a:hover {
            background: #1e3a8a;
            color: #fff;
        }
        .hamburger-sidebar .auth-links .register-link {
            background: #eef2ff;
        }

        .hamburger-sidebar .social-section {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }
        .hamburger-sidebar .social-section h6 {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
        }
        .hamburger-sidebar .social-section .social-icons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .hamburger-sidebar .social-section .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #1e293b;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 1.2rem;
        }
        .hamburger-sidebar .social-section .social-icons a:hover {
            background: #1e3a8a;
            color: #fff;
            transform: translateY(-3px);
        }

        /* ====== Existing Sidebar (for logged-in users) ====== */
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
        body.role-sales .sidebar {
            background: #ffffff;
            color: #334155;
            border-right: 1px solid #e2e8f0;
        }

        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
                top: 0 !important;
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

        .sidebar-overlay-main {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 1040;
        }
        .sidebar-overlay-main.show { display: block; }

        /* ====== Main Content ====== */
        .main-content {
            padding: 30px 35px;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }
        body.role-admin .main-content {
            padding-top: 0 !important;
        }
        body.role-admin .main-content,
        body.role-user .main-content,
        body.role-sales .main-content {
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

        /* ====== Top Bar (User Info) ====== */
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
        body.role-sales .top-bar {
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
        body.role-sales .top-bar .user-info .name { color: #0f172a; }
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
            cursor: pointer;
            display: inline-block;
        }
        body.role-admin .hamburger-btn { color: #1e293b; }
        body.role-user .hamburger-btn { color: #1e293b; }
        body.role-sales .hamburger-btn { color: #1e293b; }
        @media (min-width: 992px) {
            .hamburger-btn {
                display: none !important;
            }
        }

        /* ====== Cards ====== */
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
        body.role-sales .card-premium {
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
        body.role-sales .stat-icon.bg-soft-primary { background: #dbeafe; color: #2563eb; }
        body.role-sales .stat-icon.bg-soft-success { background: #d1fae5; color: #059669; }
        body.role-sales .stat-icon.bg-soft-warning { background: #fef3c7; color: #d97706; }
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
        .badge-sales { background: #f59e0b; color: #000; }
    </style>
</head>
<body class="role-<?= $is_logged_in ? $role : 'guest' ?> <?= $hide_top_nav ? 'top-nav-hidden' : '' ?> <?= in_array($current_page, ['login.php', 'register.php']) ? 'page-login' : '' ?>">

<!-- ====== TOP NAV – on index, login, register ====== -->
<?php if (!$hide_top_nav): ?>
<nav class="top-nav">
    <div class="nav-brand">
        <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        <i class="fas fa-building brand-icon"></i>
        <span class="brand-text">Prime <span>Property</span></span>
    </div>
    <div class="nav-right">
        <?php if ($is_logged_in): ?>
            <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
            <a href="logout.php" style="color:#fca5a5;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php" class="btn-register"><i class="fas fa-user-plus"></i> Register</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ====== HAMBURGER SIDEBAR ====== -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="hamburger-sidebar" id="hamburgerSidebar">
    <div class="sidebar-header">
        <span class="brand-small"><i class="fas fa-building"></i> Prime Property</span>
        <button class="close-btn" id="closeSidebarBtn"><i class="fas fa-times"></i></button>
    </div>
    <ul class="nav-list">
        <?php foreach ($nav_items as $item): ?>
            <li>
                <a href="<?= htmlspecialchars($item['url']) ?>">
                    <?php if ($item['icon']): ?><i class="<?= htmlspecialchars($item['icon']) ?>"></i><?php endif; ?>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <!-- 🔥 Auth links for non-logged users -->
    <?php if (!$is_logged_in): ?>
    <div class="auth-links">
        <a href="login.php"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
        <a href="register.php" class="register-link"><i class="fas fa-user-plus me-2"></i>Create Account</a>
    </div>
    <?php endif; ?>
    <div class="social-section">
        <h6><i class="fas fa-share-alt me-2"></i>Follow Us</h6>
        <div class="social-icons">
            <?php foreach ($social_links as $social): ?>
                <a href="<?= htmlspecialchars($social['url']) ?>" target="_blank" title="<?= htmlspecialchars($social['platform']) ?>">
                    <i class="<?= htmlspecialchars($social['icon_class']) ?>"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ====== EXISTING SIDEBAR (for logged-in users) ====== -->
<?php if ($is_logged_in): ?>
<div class="sidebar-overlay-main" id="sidebarOverlayMain" onclick="toggleSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <div class="brand"><i class="fas fa-building"></i> <span>Prime Property India</span></div>

    <?php if ($role == 'admin'): ?>
        <!-- ADMIN SIDEBAR -->
        <a href="admin_dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <?php if (hasViewPermission('properties', $pdo)): ?>
            <a href="properties.php"><i class="fas fa-edit"></i> <span>Auction Properties</span></a>
        <?php endif; ?>
        <?php if ($is_super_admin): ?>
            <a href="users.php"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
            <a href="admin_team.php"><i class="fas fa-sitemap"></i> <span>View Team</span></a>
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
        <?php if (hasViewPermission('deductions', $pdo)): ?>
            <a href="admin_deductions.php"><i class="fas fa-percent"></i> <span>Deductions</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('activity_logs', $pdo)): ?>
            <a href="admin_activity_logs.php"><i class="fas fa-clock"></i> <span>Activity Logs</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('accounting', $pdo)): ?>
            <a href="admin_accounting.php"><i class="fas fa-wallet"></i> <span>Accounting</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('settings', $pdo)): ?>
            <a href="settings.php"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('kyc', $pdo)): ?>
            <a href="admin_kyc.php"><i class="fas fa-id-card"></i> <span>KYC Verification</span></a>
        <?php endif; ?>
        <?php if (hasViewPermission('support', $pdo)): ?>
            <a href="support_admin.php"><i class="fas fa-headset"></i> <span>Support Tickets</span></a>
        <?php endif; ?>
        <a href="admin_user_properties.php"><i class="fas fa-home"></i> <span>User Properties</span></a>
        <a href="properties.php?filter_city=Dholera Smart City"><i class="fas fa-city"></i> <span>Dholera Properties</span></a>
        <?php if ($is_super_admin): ?>
            <a href="admin_navigation.php"><i class="fas fa-bars"></i> <span>Navigation Manager</span></a>
        <?php endif; ?>
        <a href="admin_jobs.php"><i class="fas fa-briefcase"></i> <span>Jobs / Interviews</span></a>
        <a href="admin_social_links.php"><i class="fas fa-share-alt"></i> <span>Social Links</span></a>

    <?php elseif ($role == 'sales'): ?>
        <!-- SALES SIDEBAR -->
        <a href="sales_dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="sales_leads.php"><i class="fas fa-tasks"></i> <span>My Leads</span></a>
        <a href="sales_lead_upload.php"><i class="fas fa-upload"></i> <span>Upload Leads</span></a>
        <a href="sales_lead_add.php"><i class="fas fa-plus"></i> <span>Add Lead</span></a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
        <a href="change_password.php"><i class="fas fa-key"></i> <span>Change Password</span></a>
        <a href="support.php"><i class="fas fa-headset"></i> <span>Support</span></a>

    <?php else: ?>
        <!-- USER SIDEBAR -->
        <a href="user_dashboard.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="user_packages.php"><i class="fas fa-search-dollar"></i> <span>Buy Search Engine</span></a>
        <a href="user_team.php"><i class="fas fa-users"></i> <span>My Team</span></a>
        <a href="user_subscription_history.php"><i class="fas fa-history"></i> <span>Payment History</span></a>
        <a href="user_referrals.php"><i class="fas fa-link"></i> <span>Referrals</span></a>
        <a href="profile.php"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
        <a href="support.php"><i class="fas fa-headset"></i> <span>Support</span></a>
        <a href="user_properties.php"><i class="fas fa-home"></i> <span>My Properties</span></a>
        <a href="change_password.php"><i class="fas fa-key"></i> <span>Change Password</span></a>
        <a href="user_jobs.php"><i class="fas fa-briefcase"></i> <span>Jobs / Interviews</span></a>
    <?php endif; ?>

    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>
<?php endif; ?>

<!-- ====== MAIN CONTENT ====== -->
<div class="main-content">
    <!-- Top Bar (User Info) – for all logged-in pages -->
    <?php if ($is_logged_in): ?>
    <div class="top-bar">
        <div class="d-flex align-items-center gap-2">
            <button class="hamburger-btn" id="hamburgerBtnMain" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-info">
                <i class="fas fa-user-circle" style="font-size:32px; <?= ($role=='admin')?'color:#1e3a8a;':(($role=='sales')?'color:#f59e0b;':'color:#10b981;') ?>"></i>
                <div>
                    <div class="name"><?= htmlspecialchars($_SESSION['user_name']) ?>
                        <span class="badge-role badge <?= ($role=='admin')?'bg-primary':(($role=='sales')?'badge-sales':'bg-success') ?>"><?= strtoupper($role) ?></span>
                    </div>
                    <?php if ($role == 'user'): ?>
                        <div class="user-dates">
                            <span>📅 Reg: <?= $reg_date ?></span>
                            <span>✅ Act: 
                                <?php if ($expiry_date): ?>
                                    <?= $activation_date ?>
                                    <span class="countdown-timer" id="countdownDisplay" data-expiry="<?= $expiry_date ?>">
                                        <i class="fas fa-clock"></i> <?= $days_left ?> days left
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

        <?php if ($role == 'admin'): ?>
        <div class="notification-dropdown" style="position:relative; display:inline-block;">
            <button class="btn-notify" id="notifyToggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background:transparent; border:none; color:#1e293b; font-size:1.4rem; padding:4px 8px; position:relative; cursor:pointer;">
                <i class="fas fa-bell"></i>
                <?php if (isset($notification_count) && $notification_count > 0): ?>
                    <span class="badge-notify" style="position:absolute; top:-6px; right:-8px; background:#dc2626; color:#fff; border-radius:50%; padding:2px 6px; font-size:10px; font-weight:700; min-width:18px; text-align:center;"><?= $notification_count ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifyToggle" style="min-width:350px; max-height:400px; overflow-y:auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:0; margin-top:8px; box-shadow:0 20px 40px rgba(0,0,0,0.1);">
                <li class="dropdown-header" style="color:#475569; padding:10px 16px; font-weight:600; border-bottom:1px solid #e2e8f0; background:#f8fafc; border-radius:12px 12px 0 0;">🔔 Notifications</li>
                <?php if (isset($notifications) && count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <li><a class="dropdown-item" href="<?= $notif['link'] ?>" style="color:#1e293b; padding:10px 16px; border-bottom:1px solid #f1f5f9; white-space:normal; font-size:0.85rem;"><?= $notif['message'] ?></a></li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-center text-muted small" href="#">Mark all as read</a></li>
                <?php else: ?>
                    <li class="no-notification" style="color:#94a3b8; padding:20px; text-align:center;">✨ No new notifications</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php
// 🔥 Page content will be inserted here
?>

<!-- ====== SIDEBAR TOGGLE SCRIPTS ====== -->
<script>
// Toggle hamburger sidebar
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('hamburgerSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('closeSidebarBtn');

    function openSidebar() {
        if (sidebar) {
            sidebar.classList.add('open');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
    }

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});

// Toggle main sidebar (for logged-in users)
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlayMain');
    if (sidebar) {
        sidebar.classList.toggle('show');
        if (overlay) overlay.classList.toggle('show');
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('sidebarOverlayMain');
    if (overlay) {
        overlay.addEventListener('click', toggleSidebar);
    }
});

// Countdown timer
document.addEventListener('DOMContentLoaded', function() {
    const countdownEl = document.getElementById('countdownDisplay');
    if (countdownEl && countdownEl.dataset.expiry) {
        const expiry = new Date(countdownEl.dataset.expiry);
        function updateTimer() {
            const now = new Date();
            const diff = Math.max(0, Math.floor((expiry - now) / (1000 * 60 * 60 * 24)));
            if (diff > 0) {
                countdownEl.innerHTML = '<i class="fas fa-clock"></i> ' + diff + ' days left';
            } else {
                countdownEl.innerHTML = '<i class="fas fa-clock"></i> Expired';
            }
        }
        updateTimer();
        setInterval(updateTimer, 60000);
    }
});
</script>

</body>
</html>
