<?php
// ============================================================
// ✅ Header – Top Nav + Sidebar + Collapsible Admin Sections
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
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

// NOTIFICATIONS FETCH
$notification_count = 0;
$notifications = [];
if ($is_logged_in) {
    try {
        $notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = FALSE ORDER BY id DESC LIMIT 10");
        $notif_stmt->execute([$_SESSION['user_id']]);
        $notifications = $notif_stmt->fetchAll();
        $notification_count = count($notifications);
    } catch (Exception $e) {}
}

// EXCHANGE NOTIFICATION FETCH
$unread_exchange_count = 0;
if ($is_logged_in && $role == 'user') {
    try {
        $ns = $pdo->prepare("SELECT COUNT(*) FROM exchange_notifications WHERE owner_user_id=? AND is_read=FALSE");
        $ns->execute([$_SESSION['user_id']]);
        $unread_exchange_count = $ns->fetchColumn();
    } catch (Exception $e) {}
}

$nav_items = safeFetchAll($pdo, "SELECT * FROM navigation_items WHERE is_active = TRUE ORDER BY display_order");
$dynamic_pages = safeFetchAll($pdo, "SELECT slug, title, icon FROM dynamic_pages WHERE is_active = TRUE ORDER BY display_order ASC, id ASC");
$social_links = safeFetchAll($pdo, "SELECT * FROM social_links WHERE is_active = TRUE ORDER BY display_order");

// User info for top bar (Email, Plan, Reg Date, Coins)
$reg_date = '';
$expiry_date = null;
$days_left = 0;
$plan_name = 'No Plan';
$user_email = '';
$user_coins = 0;

if ($is_logged_in && $role == 'user') {
    $user_id = $_SESSION['user_id'];
    $user_sidebar = safeFetch($pdo, "SELECT name, email, created_at as reg_date, coins FROM users WHERE id = ?", [$user_id]);
    $reg_date = !empty($user_sidebar['reg_date']) ? date('d M Y', strtotime($user_sidebar['reg_date'])) : 'N/A';
    $user_email = $user_sidebar['email'] ?? '';
    $user_coins = $user_sidebar['coins'] ?? 0;

    // Fetch Active or Last Plan
    $sub_info = safeFetch($pdo, "SELECT s.start_date, s.end_date, p.name as plan_name 
                                 FROM subscriptions s 
                                 LEFT JOIN packages p ON s.package_id = p.id 
                                 WHERE s.user_id = ? 
                                 ORDER BY s.end_date DESC LIMIT 1", [$user_id]);
    
    if ($sub_info) {
        $plan_name = $sub_info['plan_name'] ?? 'Active Plan';
        $expiry_date = $sub_info['end_date'];
        $days_left = (int) ((strtotime($expiry_date) - time()) / (60 * 60 * 24));
        $days_left = max(0, $days_left);
    }
}

$is_impersonating = isset($_SESSION['impersonate_admin_id']);
$current_imp_session = $is_impersonating ? session_id() : '';
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
        body { font-family: 'Inter', sans-serif; background: #f4f7fc; overflow-x: hidden; user-select: none; padding-top: 70px; }
        input, textarea, select, [contenteditable="true"] { user-select: text !important; }
        body.top-nav-hidden { padding-top: 0; }
        body.role-admin { background: #f8fafc; }
        body.role-user { background: #f0f5fa; }
        body.role-guest { background: #f8fafc; }
        body.role-sales { background: #f0f5fa; }
        body.page-login, body.page-register { background: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80') no-repeat center center fixed; background-size: cover; }

        /* ====== Impersonation Banner ====== */
        .impersonate-banner { position: fixed; top: 0; left: 0; right: 0; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; padding: 12px 20px; text-align: center; z-index: 99999; box-shadow: 0 4px 20px rgba(0,0,0,0.3); font-size: 0.9rem; font-weight: 500; display: flex; justify-content: center; align-items: center; gap: 14px; flex-wrap: wrap; }
        .impersonate-banner b { color: #fff; }
        .impersonate-banner .btn-exit { background: rgba(255,255,255,0.25); border: 1px solid rgba(255,255,255,0.4); color: #fff; padding: 4px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: all 0.2s; }
        .impersonate-banner .btn-exit:hover { background: #fff; color: #d97706; }
        body.impersonate-mode { padding-top: 120px !important; }
        body.impersonate-mode .sidebar { top: 50px !important; }
        body.impersonate-mode .top-nav { top: 50px !important; }
        body.impersonate-mode .hamburger-sidebar { top: 50px !important; }

        /* ====== Top Navigation – Dark Blue Gradient ====== */
        .top-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 20px rgba(0,0,0,0.3); height: 70px; }
        body.top-nav-hidden .top-nav { display: none !important; }

        .top-nav .nav-brand { display: flex; align-items: center; gap: 12px; }
        .top-nav .nav-brand .brand-icon { color: #fbbf24; font-size: 1.8rem; }
        .top-nav .nav-brand .brand-text { color: #ffffff; font-weight: 700; font-size: 1.4rem; letter-spacing: -0.5px; }
        .top-nav .nav-brand .brand-text span { color: #fbbf24; }
        .top-nav .hamburger { background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer; padding: 0 8px; transition: transform 0.2s; }
        .top-nav .hamburger:hover { transform: scale(1.1); }
        .top-nav .nav-right { display: flex; align-items: center; gap: 10px; }
        .top-nav .nav-right a { color: rgba(255,255,255,0.85); text-decoration: none; padding: 6px 14px; border-radius: 20px; font-weight: 500; font-size: 0.9rem; transition: all 0.3s; }
        .top-nav .nav-right a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .top-nav .nav-right .btn-login { background: #fbbf24; color: #0f172a !important; font-weight: 600; }
        .top-nav .nav-right .btn-login:hover { background: #fcd34d; }
        .top-nav .nav-right .btn-register { border: 1px solid rgba(255,255,255,0.3); }
        .top-nav .nav-right .btn-register:hover { background: rgba(255,255,255,0.1); }
        .top-nav .nav-right .user-badge { color: rgba(255,255,255,0.9); font-size: 0.9rem; }
        .top-nav .nav-right .user-badge i { color: #fbbf24; }

        @media (max-width: 768px) {
            .top-nav .nav-brand .brand-text { font-size: 1.1rem; }
            .top-nav .nav-right a { font-size: 0.8rem; padding: 4px 10px; }
        }

        /* ====== Hamburger Sidebar ====== */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050; }
        .sidebar-overlay.show { display: block; }
        .hamburger-sidebar { position: fixed; top: 0; left: -320px; width: 320px; height: 100%; background: #ffffff; z-index: 1060; transition: left 0.3s ease-in-out; box-shadow: 2px 0 20px rgba(0,0,0,0.15); padding: 25px 20px; overflow-y: auto; }
        .hamburger-sidebar.open { left: 0; }
        .hamburger-sidebar .sidebar-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; }
        .hamburger-sidebar .sidebar-header .close-btn { background: none; border: none; font-size: 1.8rem; color: #475569; cursor: pointer; }
        .hamburger-sidebar .sidebar-header .brand-small { font-weight: 700; font-size: 1.2rem; color: #1e293b; }
        .hamburger-sidebar .sidebar-header .brand-small i { color: #1e3a8a; }
        .hamburger-sidebar .nav-list { list-style: none; padding: 0; margin: 0; }
        .hamburger-sidebar .nav-list li { margin: 4px 0; }
        .hamburger-sidebar .nav-list li a { display: block; padding: 12px 16px; color: #475569; text-decoration: none; border-radius: 10px; font-weight: 500; transition: all 0.2s; }
        .hamburger-sidebar .nav-list li a:hover { background: #f1f5f9; color: #1e3a8a; }
        .hamburger-sidebar .nav-list li a i { width: 28px; color: #94a3b8; margin-right: 10px; }

        /* ====== Sidebar ====== */
        .sidebar { height: 100vh; width: 280px; position: fixed; top: 0; left: 0; padding: 30px 15px; box-shadow: 2px 0 12px rgba(0,0,0,0.06); z-index: 1050; transition: transform 0.3s ease-in-out; overflow-y: auto; background: #ffffff; border-right: 1px solid #e2e8f0; color: #334155; }
        body:not(.top-nav-hidden) .sidebar { top: 70px; }
        body.top-nav-hidden .sidebar { top: 0; }
        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); top: 0 !important; }
            .sidebar.show { transform: translateX(0); }
        }
        @media (min-width: 992px) {
            .sidebar { transform: translateX(0) !important; }
        }
        .sidebar .brand { font-size: 24px; font-weight: 800; text-align: center; padding-bottom: 25px; border-bottom: 1px solid #e2e8f0; margin-bottom: 25px; letter-spacing: 1px; color: #1e293b; }
        .sidebar .brand i { color: #1e3a8a; }
        .sidebar a { display: flex; align-items: center; padding: 12px 20px; margin: 4px 0; text-decoration: none; border-radius: 12px; font-weight: 500; font-size: 15px; transition: all 0.3s ease; border-left: 3px solid transparent; color: #475569; }
        .sidebar a i { width: 28px; font-size: 18px; transition: all 0.3s; color: #94a3b8; }
        .sidebar a:hover { background: #f1f5f9; color: #1e3a8a; }
        .sidebar a:hover i { color: #1e3a8a; }
        .sidebar a.active { background: #eef2ff; color: #1e3a8a; border-left-color: #1e3a8a; }
        .sidebar a.active i { color: #1e3a8a; }
        .sidebar .logout-link { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px; color: #dc2626 !important; }
        .sidebar .logout-link i { color: #dc2626 !important; }
        .sidebar .logout-link:hover { background: #fef2f2 !important; color: #b91c1c !important; }
        .sidebar-overlay-main { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1040; }
        .sidebar-overlay-main.show { display: block; }

        .sidebar .nav-section { margin: 6px 0; }
        .sidebar .nav-section-title { display: flex; align-items: center; padding: 12px 20px; color: #334155; font-weight: 600; font-size: 14.5px; cursor: pointer; border-radius: 12px; transition: all 0.25s ease; user-select: none; border-left: 3px solid transparent; }
        .sidebar .nav-section-title:hover { background: #f1f5f9; color: #1e3a8a; }
        .sidebar .nav-section-title.active { background: #eef2ff; color: #1e3a8a; }
        .sidebar .nav-section-title .section-arrow { margin-left: auto; font-size: 11px; color: #94a3b8; transition: transform 0.3s ease; }
        .sidebar .nav-section.open .nav-section-title .section-arrow { transform: rotate(90deg); color: #1e3a8a; }
        .sidebar .nav-section-title i.section-icon { width: 28px; font-size: 17px; color: #64748b; margin-right: 0; }
        .sidebar .nav-section.open .nav-section-title i.section-icon { color: #1e3a8a; }
        .sidebar .nav-section-items { max-height: 0; overflow: hidden; transition: max-height 0.35s ease; padding-left: 8px; }
        .sidebar .nav-section.open .nav-section-items { max-height: 600px; }
        .sidebar .nav-section-items a { padding: 9px 16px 9px 32px; font-size: 13.5px; font-weight: 500; margin: 2px 0; border-left: 2px solid transparent; border-radius: 10px; }
        .sidebar .nav-section-items a i { width: 22px; font-size: 14px; }
        .sidebar .nav-section-items a:hover { background: #f1f5f9; border-left-color: #cbd5e1; }
        .sidebar .nav-section-items a.active { background: #eef2ff; color: #1e3a8a; border-left-color: #1e3a8a; }

        /* ====== Main Content ====== */
        .main-content { padding: 30px 35px; min-height: 100vh; transition: margin-left 0.3s; }
        body.role-admin .main-content { padding-top: 0 !important; }
        body.role-admin .main-content, body.role-user .main-content, body.role-sales .main-content { margin-left: 280px; }
        body.role-guest .main-content { margin-left: 0 !important; }
        @media (max-width: 991px) {
            .main-content { margin-left: 0 !important; padding: 15px; }
        }

        /* ====== Top Bar ====== */
        .top-bar { padding: 10px 15px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.03); color: #0f172a; min-height: 70px; }
        .top-bar .user-info { display: flex; align-items: center; gap: 12px; }
        .top-bar .user-info .name { font-weight: 700; font-size: 16px; color: #0f172a; }
        .top-bar .badge-role { padding: 4px 14px; border-radius: 30px; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
        .top-bar .user-dates { font-size: 12px; color: #64748b; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 3px; }
        .top-bar .countdown-timer { font-weight: 700 !important; color: #dc3545 !important; background: rgba(220, 53, 69, 0.1); padding: 2px 10px; border-radius: 20px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; }
        .hamburger-btn { background: transparent; border: none; font-size: 24px; padding: 5px 10px; cursor: pointer; display: inline-block; color: #1e293b; }
        @media (min-width: 992px) { .hamburger-btn { display: none !important; } }
        
        /* Compact Spin & Coin Area */
        .header-spin-area {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 5px 15px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-spin-area .coin-display {
            font-weight: 800;
            font-size: 16px;
            color: #d97706;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .header-spin-area .mini-spin-btn {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border: none;
            color: #0f172a;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(251, 191, 36, 0.4);
            transition: 0.3s;
        }
        .header-spin-area .mini-spin-btn:hover { transform: scale(1.1); }
        .header-spin-area .mini-spin-btn:disabled { opacity: 0.5; transform: none; cursor: not-allowed; }

        .btn { border-radius: 10px; font-weight: 600; padding: 8px 16px; font-size: 14px; }
        .btn-primary { background: #1e3a8a; border: none; }
        .btn-primary:hover { background: #1e40af; }
        .badge-sales { background: #f59e0b; color: #000; }
    </style>
</head>
<body class="role-<?= $is_logged_in ? $role : 'guest' ?> <?= $hide_top_nav ? 'top-nav-hidden' : '' ?> <?= $is_impersonating ? 'impersonate-mode' : '' ?> <?= in_array($current_page, ['login.php', 'register.php']) ? 'page-login' : '' ?>"
      oncontextmenu="var tag = event.target.tagName; if(tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') return false;"
      onkeydown="var tag = event.target.tagName; var isInput = (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || event.target.isContentEditable); if (isInput) return true; if (event.key === 'F12') return false; if (event.ctrlKey && (event.key === 'u' || event.key === 's' || event.key === 'p')) return false; if (event.ctrlKey && event.shiftKey && (event.key === 'I' || event.key === 'J' || event.key === 'C')) return false;">

<?php if ($is_impersonating): ?>
<div class="impersonate-banner">
    <span><i class="fas fa-user-secret"></i> You are logged in as <b><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></b> (User #<?= $_SESSION['user_id'] ?>) <span style="opacity:0.85;">(Original Admin: <?= htmlspecialchars($_SESSION['impersonate_admin_name'] ?? 'Admin') ?>)</span></span>
    <a href="admin_exit_impersonation.php?imp_session=<?= htmlspecialchars($current_imp_session) ?>" class="btn-exit"><i class="fas fa-sign-out-alt"></i> Return to Admin</a>
</div>
<?php endif; ?>

<!-- TOP NAV -->
<?php if (!$hide_top_nav): ?>
<nav class="top-nav">
    <div class="nav-brand">
        <button class="hamburger" id="hamburgerBtn" aria-label="Menu"><i class="fas fa-ellipsis-v"></i></button>
        <i class="fas fa-building brand-icon"></i>
        <span class="brand-text">Prime <span>Property</span></span>
    </div>
    <div class="nav-right">
        <?php if ($is_logged_in): ?>
            <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User') ?></span>
            <a href="logout.php" style="color:#fca5a5;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php" class="btn-register"><i class="fas fa-user-plus"></i> Register</a>
        <?php endif; ?>
    </div>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="hamburger-sidebar" id="hamburgerSidebar">
    <div class="sidebar-header">
        <span class="brand-small"><i class="fas fa-building"></i> Prime Property</span>
        <button class="close-btn" id="closeSidebarBtn"><i class="fas fa-times"></i></button>
    </div>
    <ul class="nav-list">
        <?php if (!empty($dynamic_pages)): foreach ($dynamic_pages as $page): ?>
            <li><a href="page.php?slug=<?= urlencode($page['slug']) ?>"><i class="fas <?= htmlspecialchars($page['icon']) ?>"></i> <?= htmlspecialchars($page['title']) ?></a></li>
        <?php endforeach; else: ?>
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<!-- SIDEBAR -->
<?php if ($is_logged_in): ?>
<div class="sidebar-overlay-main" id="sidebarOverlayMain" onclick="toggleSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <div class="brand"><i class="fas fa-building"></i> <span>Prime Property</span></div>

    <?php if ($role == 'admin'): ?>
        <a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <!-- (Baki Admin Menu yahan rahega) -->
    <?php elseif ($role == 'sales'): ?>
        <a href="sales_dashboard.php" class="<?= $current_page == 'sales_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <!-- (Baki Sales Menu yahan rahega) -->
    <?php else: ?>
        <a href="user_dashboard.php" class="<?= $current_page == 'user_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="user_packages.php" class="<?= $current_page == 'user_packages.php' ? 'active' : '' ?>"><i class="fas fa-search-dollar"></i> <span>Buy Search Engine</span></a>
        <a href="user_team.php" class="<?= $current_page == 'user_team.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> <span>My Team</span></a>
        <a href="my_earnings.php" class="<?= $current_page == 'my_earnings.php' ? 'active' : '' ?>"><i class="fas fa-file-invoice-dollar"></i><span>My Earnings</span></a>
        <a href="user_wallet_history.php" class="<?= $current_page == 'user_wallet_history.php' ? 'active' : '' ?>"><i class="fas fa-receipt"></i> <span>Payment History</span></a>
        <a href="user_subscription_history.php" class="<?= $current_page == 'user_subscription_history.php' ? 'active' : '' ?>"><i class="fas fa-history"></i> <span>Subscription History</span></a>
        <a href="user_referrals.php" class="<?= $current_page == 'user_referrals.php' ? 'active' : '' ?>"><i class="fas fa-link"></i> <span>Referrals</span></a>
        <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
        <a href="support.php" class="<?= $current_page == 'support.php' ? 'active' : '' ?>"><i class="fas fa-headset"></i> <span>Support</span></a>
        <a href="user_properties.php" class="<?= $current_page == 'user_properties.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> <span>My Properties</span></a>
    <?php endif; ?>

    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>
<?php endif; ?>

<!-- MAIN CONTENT -->
<div class="main-content">
    <?php if ($is_logged_in): ?>
    <div class="top-bar">
        <!-- Left Side: User Info -->
        <div class="d-flex align-items-center gap-2">
            <button class="hamburger-btn" id="hamburgerBtnMain" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div class="user-info">
                <i class="fas fa-user-circle" style="font-size:28px; <?= ($role=='admin')?'color:#1e3a8a;':(($role=='sales')?'color:#f59e0b;':'color:#10b981;') ?>"></i>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="name" style="font-size:15px;"><?= htmlspecialchars($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User') ?></span>
                        <span class="badge-role badge <?= ($role=='admin')?'bg-primary':(($role=='sales')?'badge-sales':'bg-success') ?>"><?= strtoupper($role) ?></span>
                        <?php if($role == 'user' && !empty($user_email)): ?>
                            <span class="text-muted" style="font-size:12px;"><i class="fas fa-envelope"></i> <?= htmlspecialchars($user_email) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($role == 'user'): ?>
                        <div class="user-dates">
                            <span><i class="fas fa-calendar-alt text-primary"></i> Reg: <?= $reg_date ?></span>
                            <span class="text-muted">|</span>
                            <?php if ($expiry_date): ?>
                                <span><i class="fas fa-crown text-warning"></i> Plan: <strong><?= htmlspecialchars($plan_name) ?></strong> (<?= date('d M Y', strtotime($expiry_date)) ?>)</span>
                                <?php if ($days_left > 0): ?>
                                    <span class="countdown-timer"><i class="fas fa-clock"></i> <?= $days_left ?> days left</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary" style="font-size:10px;">Expired</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Center: Mini Spin Wheel & Coins (Only on Dashboard) -->
        <?php if ($current_page == 'user_dashboard.php' && $role == 'user'): ?>
        <div class="header-spin-area">
            <div class="coin-display" title="Total Coins">
                <span style="font-size:1.2rem;">🪙</span>
                <span id="headerCoinBalance"><?= number_format($user_coins) ?></span>
            </div>
            <div style="width:1px; height:20px; background:#cbd5e1;"></div>
            <button class="mini-spin-btn" id="miniSpinBtn" onclick="triggerHeaderSpin()" title="Spin Now">
                <i class="fas fa-sync-alt" style="font-size:14px;"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Right Side: Notification Bell -->
        <?php if ($role == 'user'): ?>
            <a href="my_notifications.php" class="btn btn-light position-relative me-2" style="border-radius:50%; width:36px; height:36px; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-bell" style="color:#1e293b; font-size:1.1rem;"></i>
                <?php if($unread_exchange_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;"><?= $unread_exchange_count ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php // 🔥 Page content will be inserted here ?>

<!-- SIDEBAR TOGGLE SCRIPTS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('hamburgerSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('closeSidebarBtn');

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', () => { if(sidebar) { sidebar.classList.add('open'); overlay.classList.add('show'); document.body.style.overflow = 'hidden'; }});
    if (closeBtn) closeBtn.addEventListener('click', () => { if(sidebar) { sidebar.classList.remove('open'); overlay.classList.remove('show'); document.body.style.overflow = ''; }});
    if (overlay) overlay.addEventListener('click', () => { if(sidebar) { sidebar.classList.remove('open'); overlay.classList.remove('show'); document.body.style.overflow = ''; }});
});

function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlayMain');
    if (sidebar) { sidebar.classList.toggle('show'); if (overlay) overlay.classList.toggle('show'); }
}
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('sidebarOverlayMain');
    if (overlay) { overlay.addEventListener('click', toggleSidebar); }
});

function toggleSection(el) {
    var section = el.closest('.nav-section');
    if (!section) return;
    section.classList.toggle('open');
}

<?php if ($is_logged_in && ($role == 'admin' || $role == 'sales')): ?>
setInterval(function() {
    fetch('check_notifications.php')
    .then(response => response.json())
    .then(data => {
        const badge = document.querySelector('.badge-notify');
        const bell = document.getElementById('notifyToggle');
        if (data.count > 0) {
            if (badge) { badge.innerText = data.count; } 
            else if (bell) { bell.insertAdjacentHTML('beforeend', `<span class="badge-notify" style="position:absolute; top:-6px; right:-8px; background:#dc2626; color:#fff; border-radius:50%; padding:2px 6px; font-size:10px; font-weight:700; min-width:18px; text-align:center;">${data.count}</span>`); }
        } else if (badge) { badge.remove(); }
    }).catch(err => console.log('Notification fetch error'));
}, 15000);
<?php endif; ?>
</script>
</body>
</html>
