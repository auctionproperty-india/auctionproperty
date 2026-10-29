<?php
// ============================================================
// ✅ Header – Top Nav + Sidebar + Collapsible Admin Sections
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🛡️ SECURITY: Block Bad Bots & Rate Limiting
blockBadBots();
checkRateLimit(60, 60); // 60 requests per 60 seconds

$current_page = basename($_SERVER['PHP_SELF']);
$hide_top_nav = !in_array($current_page, ['index.php', 'login.php', 'register.php']);

$is_logged_in = isset($_SESSION['user_id']);
$role = $is_logged_in ? ($_SESSION['role'] ?? 'user') : 'guest';

// 🔥 User Name Fix
$display_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User';
if ($is_logged_in && ($display_name === 'User' || empty($display_name))) {
    $db_user = safeFetch($pdo, "SELECT name FROM users WHERE id = ?", [$_SESSION['user_id']]);
    if ($db_user && !empty($db_user['name'])) {
        $display_name = $db_user['name'];
        $_SESSION['name'] = $db_user['name'];
    }
}

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
if ($is_logged_in && ($role == 'admin' || $role == 'sales')) {
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

// User info for top bar
$reg_date = '';
$expiry_date = null;
$days_left = 0;
$plan_name = 'No Plan';
$user_email = '';
$user_coins = 0;
$ba_status = 'none'; // 🔥 New BA Status Variable

if ($is_logged_in && $role == 'user') {
    $user_id = $_SESSION['user_id'];
    // Added ba_status to the SELECT query
    $user_sidebar = safeFetch($pdo, "SELECT name, email, created_at as reg_date, coins, ba_status FROM users WHERE id = ?", [$user_id]);
    $reg_date = !empty($user_sidebar['reg_date']) ? date('d M Y', strtotime($user_sidebar['reg_date'])) : 'N/A';
    $user_email = $user_sidebar['email'] ?? '';
    $user_coins = $user_sidebar['coins'] ?? 0;
    $ba_status = $user_sidebar['ba_status'] ?? 'none';

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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7fc; overflow-x: hidden; user-select: none; padding-top: 70px; }
        input, textarea, select, [contenteditable="true"] { user-select: text !important; }
        body.top-nav-hidden { padding-top: 0; }
        
        .impersonate-banner { position: fixed; top: 0; left: 0; right: 0; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; padding: 12px 20px; text-align: center; z-index: 99999; font-size: 0.9rem; font-weight: 500; display: flex; justify-content: center; align-items: center; gap: 14px; }
        .impersonate-banner .btn-exit { background: rgba(255,255,255,0.25); border: 1px solid rgba(255,255,255,0.4); color: #fff; padding: 4px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-decoration: none; }
        body.impersonate-mode { padding-top: 120px !important; }
        body.impersonate-mode .sidebar { top: 50px !important; }
        body.impersonate-mode .top-nav { top: 50px !important; }

        .top-nav { position: fixed; top: 0; left: 0; right: 0; z-index: 1030; background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 20px rgba(0,0,0,0.3); height: 70px; }
        body.top-nav-hidden .top-nav { display: none !important; }
        .top-nav .nav-brand { display: flex; align-items: center; gap: 12px; }
        .top-nav .nav-brand .brand-icon { color: #fbbf24; font-size: 1.8rem; }
        .top-nav .nav-brand .brand-text { color: #ffffff; font-weight: 700; font-size: 1.4rem; }
        .top-nav .nav-brand .brand-text span { color: #fbbf24; }
        .top-nav .hamburger { background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer; padding: 0 8px; }
        .top-nav .nav-right { display: flex; align-items: center; gap: 10px; }
        .top-nav .nav-right a { color: rgba(255,255,255,0.85); text-decoration: none; padding: 6px 14px; border-radius: 20px; font-weight: 500; font-size: 0.9rem; transition: all 0.3s; }
        .top-nav .nav-right a:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .top-nav .nav-right .btn-login { background: #fbbf24; color: #0f172a !important; font-weight: 600; }
        .top-nav .nav-right .user-badge { color: rgba(255,255,255,0.9); font-size: 0.9rem; }
        .top-nav .nav-right .user-badge i { color: #fbbf24; }

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
        .sidebar .brand { font-size: 24px; font-weight: 800; text-align: center; padding-bottom: 25px; border-bottom: 1px solid #e2e8f0; margin-bottom: 25px; color: #1e293b; }
        .sidebar .brand i { color: #1e3a8a; }
        .sidebar a { display: flex; align-items: center; padding: 12px 20px; margin: 4px 0; text-decoration: none; border-radius: 12px; font-weight: 500; font-size: 15px; transition: all 0.3s ease; border-left: 3px solid transparent; color: #475569; }
        .sidebar a i { width: 28px; font-size: 18px; transition: all 0.3s; color: #94a3b8; }
        .sidebar a:hover { background: #f1f5f9; color: #1e3a8a; }
        .sidebar a:hover i { color: #1e3a8a; }
        .sidebar a.active { background: #eef2ff; color: #1e3a8a; border-left-color: #1e3a8a; }
        .sidebar a.active i { color: #1e3a8a; }
        .sidebar .logout-link { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px; color: #dc2626 !important; }
        .sidebar .logout-link i { color: #dc2626 !important; }
        .sidebar-overlay-main { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 1040; }
        .sidebar-overlay-main.show { display: block; }
        
        /* Collapsible Sidebar Sections */
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

        .main-content { padding: 20px 25px; min-height: 100vh; transition: margin-left 0.3s; }
        body.role-admin .main-content, body.role-user .main-content, body.role-sales .main-content { margin-left: 280px; }
        body.role-guest .main-content { margin-left: 0 !important; }
        @media (max-width: 991px) { .main-content { margin-left: 0 !important; padding: 15px; } }

        .top-bar { padding: 10px 15px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.03); color: #0f172a; min-height: 65px; }
        .top-bar .user-info { display: flex; align-items: center; gap: 12px; }
        .top-bar .user-info .name { font-weight: 700; font-size: 15px; color: #0f172a; }
        .top-bar .badge-role { padding: 4px 14px; border-radius: 30px; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
        .top-bar .user-dates { font-size: 12px; color: #64748b; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 3px; }
        .top-bar .countdown-timer { font-weight: 700 !important; color: #dc3545 !important; background: rgba(220, 53, 69, 0.1); padding: 2px 10px; border-radius: 20px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px; }
        .hamburger-btn { background: transparent; border: none; font-size: 24px; padding: 5px 10px; cursor: pointer; color: #1e293b; }
        @media (min-width: 992px) { .hamburger-btn { display: none !important; } }
        
        .header-spin-area { background: #f8fafc; border: 1px solid #e2e8f0; padding: 5px 15px; border-radius: 30px; display: flex; align-items: center; gap: 15px; }
        .header-spin-area .coin-display { font-weight: 800; font-size: 16px; color: #d97706; display: flex; align-items: center; gap: 5px; }
        .header-spin-area .mini-spin-btn { background: linear-gradient(135deg, #fbbf24, #f59e0b); border: none; color: #0f172a; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(251, 191, 36, 0.4); transition: 0.3s; }
        .header-spin-area .mini-spin-btn:hover { transform: scale(1.1); }

        .btn { border-radius: 10px; font-weight: 600; padding: 8px 16px; font-size: 14px; }
        .badge-sales { background: #f59e0b; color: #000; }
        
        .spin-wheel-container { width: 250px; height: 250px; position: relative; margin: 0 auto 20px auto; }
        .spin-wheel { width: 100%; height: 100%; border-radius: 50%; border: 8px solid #fbbf24; box-shadow: 0 0 20px rgba(251, 191, 36, 0.5); background: conic-gradient(#ef4444 0deg 72deg, #3b82f6 72deg 144deg, #10b981 144deg 216deg, #f59e0b 216deg 288deg, #8b5cf6 288deg 360deg); transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99); position: relative; }
        .spin-wheel::after { content: ''; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; background: #fff; border-radius: 50%; border: 4px solid #fbbf24; }
        .spin-pointer { position: absolute; top: -15px; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 15px solid transparent; border-right: 15px solid transparent; border-top: 25px solid #1e293b; z-index: 10; }
    </style>
</head>
<body class="role-<?= $is_logged_in ? $role : 'guest' ?> <?= $hide_top_nav ? 'top-nav-hidden' : '' ?> <?= $is_impersonating ? 'impersonate-mode' : '' ?> <?= in_array($current_page, ['login.php', 'register.php']) ? 'page-login' : '' ?>">

<?php if ($is_impersonating): ?>
<div class="impersonate-banner">
    <span><i class="fas fa-user-secret"></i> Logged in as <b><?= htmlspecialchars($display_name) ?></b></span>
    <a href="admin_exit_impersonation.php?imp_session=<?= htmlspecialchars($current_imp_session) ?>" class="btn-exit"><i class="fas fa-sign-out-alt"></i> Return</a>
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
            <span class="user-badge"><i class="fas fa-user-circle"></i> <?= htmlspecialchars($display_name) ?></span>
            <a href="logout.php" style="color:#fca5a5;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php" class="btn-register"><i class="fas fa-user-plus"></i> Register</a>
        <?php endif; ?>
    </div>
</nav>

<!-- 🔥 HAMBURGER SIDEBAR -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="hamburger-sidebar" id="hamburgerSidebar">
    <div class="sidebar-header">
        <span class="brand-small"><i class="fas fa-building"></i> Prime Property</span>
        <button class="close-btn" id="closeSidebarBtn"><i class="fas fa-times"></i></button>
    </div>
    <ul class="nav-list">
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="index.php?tab=auction"><i class="fas fa-gavel"></i> Auctions</a></li>
        <li><a href="index.php?tab=customer"><i class="fas fa-building"></i> Properties</a></li>
        <li><a href="index.php?tab=exchange"><i class="fas fa-exchange-alt"></i> Exchange</a></li>
        
        <?php if (!empty($dynamic_pages)): ?>
            <li style="border-top:1px solid #eee; margin-top:10px; padding-top:10px;"><span style="font-size:12px; color:#888; padding-left:15px;">More Pages</span></li>
            <?php foreach ($dynamic_pages as $page): ?>
                <li><a href="page.php?slug=<?= urlencode($page['slug']) ?>"><i class="fas <?= htmlspecialchars($page['icon']) ?>"></i> <?= htmlspecialchars($page['title']) ?></a></li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<!-- EXISTING SIDEBAR (Logged in users) -->
<?php if ($is_logged_in): ?>
<div class="sidebar-overlay-main" id="sidebarOverlayMain" onclick="toggleSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <div class="brand"><i class="fas fa-building"></i> <span>Prime Property</span></div>
    
    <?php if ($role == 'admin'): ?>
        <!-- ============================================================ -->
        <!-- ADMIN SIDEBAR -->
        <!-- ============================================================ -->
        <a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>

        <!-- 🔷 1. PROPERTIES -->
        <div class="nav-section <?= in_array($current_page, ['properties.php','bulk_upload_properties.php','admin_user_properties.php']) ? 'open' : '' ?>">
            <div class="nav-section-title <?= in_array($current_page, ['properties.php','bulk_upload_properties.php','admin_user_properties.php']) ? 'active' : '' ?>" onclick="toggleSection(this)">
                <i class="fas fa-building section-icon"></i>
                <span>Properties</span>
                <i class="fas fa-chevron-right section-arrow"></i>
            </div>
            <div class="nav-section-items">
                <?php if (hasViewPermission('properties', $pdo)): ?>
                    <a href="properties.php" class="<?= $current_page == 'properties.php' ? 'active' : '' ?>"><i class="fas fa-edit"></i> <span>Auction Properties</span></a>
                    <a href="bulk_upload_properties.php" class="<?= $current_page == 'bulk_upload_properties.php' ? 'active' : '' ?>"><i class="fas fa-file-upload"></i> <span>Bulk Upload</span></a>
                <?php endif; ?>
                <a href="admin_user_properties.php" class="<?= $current_page == 'admin_user_properties.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> <span>User Properties</span></a>
                <a href="properties.php?filter_city=Dholera Smart City"><i class="fas fa-city"></i> <span>Dholera Properties</span></a>
            </div>
        </div>

        <!-- 🔷 2. USERS & TEAM -->
        <?php if ($is_super_admin): ?>
        <div class="nav-section <?= in_array($current_page, ['users.php','admin_team.php','admin_permissions.php']) ? 'open' : '' ?>">
            <div class="nav-section-title <?= in_array($current_page, ['users.php','admin_team.php','admin_permissions.php']) ? 'active' : '' ?>" onclick="toggleSection(this)">
                <i class="fas fa-users section-icon"></i><span>Users & Team</span><i class="fas fa-chevron-right section-arrow"></i>
            </div>
            <div class="nav-section-items">
                <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>"><i class="fas fa-users-cog"></i> <span>Manage Users</span></a>
                <a href="admin_team.php" class="<?= $current_page == 'admin_team.php' ? 'active' : '' ?>"><i class="fas fa-sitemap"></i> <span>View Team</span></a>
                <a href="admin_permissions.php" class="<?= $current_page == 'admin_permissions.php' ? 'active' : '' ?>"><i class="fas fa-user-shield"></i> <span>Sub-Admins</span></a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 🔷 3. PACKAGES & SUBSCRIPTIONS -->
        <?php if (hasViewPermission('packages', $pdo) || hasViewPermission('subscriptions', $pdo)): ?>
        <div class="nav-section <?= in_array($current_page, ['admin_packages.php','admin_subscriptions.php','admin_subscription_history.php','admin_give_package.php','admin_extend_subscriptions.php']) ? 'open' : '' ?>">
            <div class="nav-section-title <?= in_array($current_page, ['admin_packages.php','admin_subscriptions.php','admin_subscription_history.php','admin_give_package.php','admin_extend_subscriptions.php']) ? 'active' : '' ?>" onclick="toggleSection(this)">
                <i class="fas fa-tags section-icon"></i><span>Packages</span><i class="fas fa-chevron-right section-arrow"></i>
            </div>
            <div class="nav-section-items">
                <?php if (hasViewPermission('packages', $pdo)): ?>
                    <a href="admin_packages.php" class="<?= $current_page == 'admin_packages.php' ? 'active' : '' ?>"><i class="fas fa-tags"></i> <span>Manage Packages</span></a>
                <?php endif; ?>
                <?php if (hasViewPermission('subscriptions', $pdo)): ?>
                    <a href="admin_subscriptions.php" class="<?= $current_page == 'admin_subscriptions.php' ? 'active' : '' ?>"><i class="fas fa-user-check"></i> <span>Pending Requests</span></a>
                    <a href="admin_subscription_history.php" class="<?= $current_page == 'admin_subscription_history.php' ? 'active' : '' ?>"><i class="fas fa-history"></i> <span>Subscription History</span></a>
                <?php endif; ?>
                <a href="admin_give_package.php" class="<?= $current_page == 'admin_give_package.php' ? 'active' : '' ?>"><i class="fas fa-gift"></i> <span>Give Free Package</span></a>
                <a href="admin_extend_subscriptions.php" class="<?= $current_page == 'admin_extend_subscriptions.php' ? 'active' : '' ?>"><i class="fas fa-calendar-plus"></i> <span>Extend Subscriptions</span></a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 🔷 4. INCOME & PAYOUTS -->
        <?php if (hasViewPermission('referrals', $pdo) || hasViewPermission('accounting', $pdo)): ?>
        <div class="nav-section <?= in_array($current_page, ['admin_referrals.php','admin_payout_preview.php','admin_payout_manager.php','admin_fix_subscription_amounts.php','admin_accounting.php','admin_deductions.php','admin_wallet_payout.php']) ? 'open' : '' ?>">
            <div class="nav-section-title <?= in_array($current_page, ['admin_referrals.php','admin_payout_preview.php','admin_payout_manager.php','admin_fix_subscription_amounts.php','admin_accounting.php','admin_deductions.php','admin_wallet_payout.php']) ? 'active' : '' ?>" onclick="toggleSection(this)">
                <i class="fas fa-hand-holding-usd section-icon"></i><span>Income & Payouts</span><i class="fas fa-chevron-right section-arrow"></i>
            </div>
            <div class="nav-section-items">
                <a href="admin_payout_preview.php" class="<?= $current_page == 'admin_payout_preview.php' ? 'active' : '' ?>"><i class="fas fa-file-invoice-dollar"></i> <span>Payout Preview</span></a>
                <a href="admin_referrals.php" class="<?= $current_page == 'admin_referrals.php' ? 'active' : '' ?>"><i class="fas fa-hand-holding-usd"></i> <span>Release Payouts</span></a>
                <a href="admin_payout_manager.php" class="<?= $current_page == 'admin_payout_manager.php' ? 'active' : '' ?>"><i class="fas fa-wallet"></i> <span>Payout Manager</span></a>
                <a href="admin_wallet_payout.php" class="<?= $current_page == 'admin_wallet_payout.php' ? 'active' : '' ?>"><i class="fas fa-money-bill-wave"></i> <span>Wallet Payout</span></a>
                <a href="admin_fix_subscription_amounts.php" class="<?= $current_page == 'admin_fix_subscription_amounts.php' ? 'active' : '' ?>"><i class="fas fa-tools"></i> <span>Fix Amounts</span></a>
                <?php if (hasViewPermission('accounting', $pdo)): ?>
                    <a href="admin_accounting.php" class="<?= $current_page == 'admin_accounting.php' ? 'active' : '' ?>"><i class="fas fa-wallet"></i> <span>Accounting</span></a>
                <?php endif; ?>
                <?php if (hasViewPermission('deductions', $pdo)): ?>
                    <a href="admin_deductions.php" class="<?= $current_page == 'admin_deductions.php' ? 'active' : '' ?>"><i class="fas fa-percent"></i> <span>Deductions</span></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 🔷 5. SALES CRM -->
        <div class="nav-section <?= in_array($current_page, ['admin_sales_crm.php','admin_sales_leads.php','admin_sales_users.php']) ? 'open' : '' ?>">
            <div class="nav-section-title <?= in_array($current_page, ['admin_sales_crm.php','admin_sales_leads.php','admin_sales_users.php']) ? 'active' : '' ?>" onclick="toggleSection(this)">
                <i class="fas fa-chart-line section-icon"></i><span>Sales CRM</span><i class="fas fa-chevron-right section-arrow"></i>
            </div>
            <div class="nav-section-items">
                <a href="admin_sales_crm.php" class="<?= $current_page == 'admin_sales_crm.php' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> <span>CRM Dashboard</span></a>
                <a href="admin_sales_leads.php" class="<?= $current_page == 'admin_sales_leads.php' ? 'active' : '' ?>"><i class="fas fa-list"></i> <span>All Leads</span></a>
                <a href="admin_sales_users.php" class="<?= $current_page == 'admin_sales_users.php' ? 'active' : '' ?>"><i class="fas fa-user-tie"></i> <span>Sales Users</span></a>
            </div>
        </div>

        <!-- 🔷 6. CONTENT & PAGES -->
        <div class="nav-section <?= in_array($current_page, ['admin_pages.php','admin_navigation.php','admin_jobs.php','admin_social_links.php','admin_notification.php','admin_activity_logs.php','admin_kyc.php','support_admin.php']) ? 'open' : '' ?>">
            <div class="nav-section-title <?= in_array($current_page, ['admin_pages.php','admin_navigation.php','admin_jobs.php','admin_social_links.php','admin_notification.php','admin_activity_logs.php','admin_kyc.php','support_admin.php']) ? 'active' : '' ?>" onclick="toggleSection(this)">
                <i class="fas fa-file-alt section-icon"></i><span>Content & Pages</span><i class="fas fa-chevron-right section-arrow"></i>
            </div>
            <div class="nav-section-items">
                <a href="admin_pages.php" class="<?= $current_page == 'admin_pages.php' ? 'active' : '' ?>"><i class="fas fa-file-alt"></i> <span>Manage Pages</span></a>
                <?php if ($is_super_admin): ?>
                    <a href="admin_navigation.php" class="<?= $current_page == 'admin_navigation.php' ? 'active' : '' ?>"><i class="fas fa-bars"></i> <span>Navigation Manager</span></a>
                <?php endif; ?>
                <a href="admin_jobs.php" class="<?= $current_page == 'admin_jobs.php' ? 'active' : '' ?>"><i class="fas fa-briefcase"></i> <span>Jobs / Interviews</span></a>
                <a href="admin_social_links.php" class="<?= $current_page == 'admin_social_links.php' ? 'active' : '' ?>"><i class="fas fa-share-alt"></i> <span>Social Links</span></a>
                <a href="admin_notification.php" class="<?= $current_page == 'admin_notification.php' ? 'active' : '' ?>"><i class="fas fa-bullhorn"></i> <span>Popup Notification</span><?php if (isset($notif_count) && $notif_count > 0): ?><span class="badge bg-danger ms-2"><?= $notif_count ?></span><?php endif; ?></a>
                <?php if (hasViewPermission('activity_logs', $pdo)): ?>
                    <a href="admin_activity_logs.php" class="<?= $current_page == 'admin_activity_logs.php' ? 'active' : '' ?>"><i class="fas fa-clock"></i> <span>Activity Logs</span></a>
                <?php endif; ?>
                <?php if (hasViewPermission('kyc', $pdo)): ?>
                    <a href="admin_kyc.php" class="<?= $current_page == 'admin_kyc.php' ? 'active' : '' ?>"><i class="fas fa-id-card"></i> <span>KYC Verification</span></a>
                <?php endif; ?>
                <?php if (hasViewPermission('support', $pdo)): ?>
                    <a href="support_admin.php" class="<?= $current_page == 'support_admin.php' ? 'active' : '' ?>"><i class="fas fa-headset"></i> <span>Support Tickets</span></a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 🔷 7. SETTINGS -->
        <?php if (hasViewPermission('settings', $pdo)): ?>
        <div class="nav-section <?= in_array($current_page, ['settings.php','admin_spin_settings.php']) ? 'open' : '' ?>">
            <div class="nav-section-title <?= in_array($current_page, ['settings.php','admin_spin_settings.php']) ? 'active' : '' ?>" onclick="toggleSection(this)">
                <i class="fas fa-cog section-icon"></i><span>Settings</span><i class="fas fa-chevron-right section-arrow"></i>
            </div>
            <div class="nav-section-items">
                <a href="settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog"></i> <span>General Settings</span></a>
                <a href="admin_spin_settings.php" class="<?= $current_page == 'admin_spin_settings.php' ? 'active' : '' ?>"><i class="fas fa-dharmachakra"></i> <span>Spin Settings</span></a>
            </div>
        </div>
        <?php endif; ?>

    <?php elseif ($role == 'sales'): ?>
        <!-- SALES SIDEBAR -->
        <a href="sales_dashboard.php" class="<?= $current_page == 'sales_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="sales_properties.php" class="<?= $current_page == 'sales_properties.php' ? 'active' : '' ?>"><i class="fas fa-building"></i> <span>All Properties</span></a>
        <a href="sales_leads.php" class="<?= $current_page == 'sales_leads.php' ? 'active' : '' ?>"><i class="fas fa-tasks"></i> <span>My Leads</span></a>
        <a href="sales_lead_upload.php" class="<?= $current_page == 'sales_lead_upload.php' ? 'active' : '' ?>"><i class="fas fa-upload"></i> <span>Upload Leads</span></a>
        <a href="sales_lead_add.php" class="<?= $current_page == 'sales_lead_add.php' ? 'active' : '' ?>"><i class="fas fa-plus"></i> <span>Add Lead</span></a>
        <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
        <a href="change_password.php" class="<?= $current_page == 'change_password.php' ? 'active' : '' ?>"><i class="fas fa-key"></i> <span>Change Password</span></a>
        <a href="support.php" class="<?= $current_page == 'support.php' ? 'active' : '' ?>"><i class="fas fa-headset"></i> <span>Support</span></a>

    <?php else: ?>
        <!-- ============================================================ -->
        <!-- 🔥 USER SIDEBAR (BA Logic Added) -->
        <!-- ============================================================ -->
        <a href="user_dashboard.php" class="<?= ($current_page == 'user_dashboard.php' && !isset($_GET['view'])) ? 'active' : '' ?>"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        
        <div class="nav-section <?= ($current_page == 'user_dashboard.php' && isset($_GET['view'])) ? 'open' : '' ?>">
            <div class="nav-section-title <?= ($current_page == 'user_dashboard.php' && isset($_GET['view'])) ? 'active' : '' ?>" onclick="toggleSection(this)">
                <i class="fas fa-building section-icon"></i><span>All Properties</span><i class="fas fa-chevron-right section-arrow"></i>
            </div>
            <div class="nav-section-items">
                <a href="user_dashboard.php?view=all&tab=auction" class="<?= (isset($_GET['tab']) && $_GET['tab'] == 'auction') ? 'active' : '' ?>"><i class="fas fa-gavel"></i> <span>Auction Properties</span></a>
                <a href="user_dashboard.php?view=all&tab=customer" class="<?= (isset($_GET['tab']) && $_GET['tab'] == 'customer') ? 'active' : '' ?>"><i class="fas fa-home"></i> <span>Customer Properties</span></a>
                <a href="user_dashboard.php?view=all&tab=exchange" class="<?= (isset($_GET['tab']) && $_GET['tab'] == 'exchange') ? 'active' : '' ?>"><i class="fas fa-exchange-alt"></i> <span>Exchange Properties</span></a>
            </div>
        </div>

        <a href="user_packages.php" class="<?= $current_page == 'user_packages.php' ? 'active' : '' ?>"><i class="fas fa-search-dollar"></i> <span>Buy Search Engine</span></a>
        
        <!-- 🔥 BA LOGIC START -->
        <?php if ($ba_status == 'active'): ?>
            <a href="user_team.php" class="<?= $current_page == 'user_team.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> <span>My Team</span></a>
            <a href="my_earnings.php" class="<?= $current_page == 'my_earnings.php' ? 'active' : '' ?>"><i class="fas fa-file-invoice-dollar"></i> <span>My Earnings</span></a>
            <a href="user_referrals.php" class="<?= $current_page == 'user_referrals.php' ? 'active' : '' ?>"><i class="fas fa-link"></i> <span>Referrals</span></a>
        <?php elseif ($ba_status == 'pending'): ?>
            <a href="javascript:void(0);" class="text-warning" style="cursor: default;"><i class="fas fa-clock"></i> <span>BA Approval Pending</span></a>
        <?php else: ?>
            <a href="apply_ba.php" class="<?= $current_page == 'apply_ba.php' ? 'active' : '' ?>"><i class="fas fa-handshake"></i> <span>Apply for BA</span></a>
        <?php endif; ?>
        <!-- 🔥 BA LOGIC END -->

        <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
        <a href="support.php" class="<?= $current_page == 'support.php' ? 'active' : '' ?>"><i class="fas fa-headset"></i> <span>Support</span></a>
        <a href="user_properties.php" class="<?= $current_page == 'user_properties.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> <span>My Properties</span></a>
        <a href="change_password.php" class="<?= $current_page == 'change_password.php' ? 'active' : '' ?>"><i class="fas fa-key"></i> <span>Change Password</span></a>
        <a href="user_jobs.php" class="<?= $current_page == 'user_jobs.php' ? 'active' : '' ?>"><i class="fas fa-briefcase"></i> <span>Jobs / Interviews</span></a>
    <?php endif; ?>

    <?php if ($is_impersonating): ?>
        <a href="admin_exit_impersonation.php?imp_session=<?= htmlspecialchars($current_imp_session) ?>" class="logout-link" style="color:#d97706 !important;"><i class="fas fa-sign-out-alt" style="color:#d97706 !important;"></i> <span>Exit Impersonation</span></a>
    <?php else: ?>
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- MAIN CONTENT -->
<div class="main-content">
    <?php if ($is_logged_in): ?>
    <div class="top-bar">
        <div class="d-flex align-items-center gap-2">
            <button class="hamburger-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div class="user-info">
                <i class="fas fa-user-circle" style="font-size:28px; <?= ($role=='admin')?'color:#1e3a8a;':(($role=='sales')?'color:#f59e0b;':'color:#10b981;') ?>"></i>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="name"><?= htmlspecialchars($display_name) ?></span>
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
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($current_page == 'user_dashboard.php' && $role == 'user'): ?>
        <div class="header-spin-area">
            <div class="coin-display" title="Total Coins">
                <span style="font-size:1.2rem;">🪙</span>
                <span id="headerCoinBalance"><?= number_format($user_coins) ?></span>
            </div>
            <div style="width:1px; height:20px; background:#cbd5e1;"></div>
            <button class="mini-spin-btn" data-bs-toggle="modal" data-bs-target="#spinWheelModal" title="Spin Now">
                <i class="fas fa-sync-alt" style="font-size:14px;"></i>
            </button>
        </div>
        <?php endif; ?>

        <?php if ($role == 'user'): ?>
            <a href="my_notifications.php" class="btn btn-light position-relative me-2" style="border-radius:50%; width:36px; height:36px; display:flex; align-items: center; justify-content: center;">
                <i class="fas fa-bell" style="color:#1e293b; font-size:1.1rem;"></i>
                <?php if($unread_exchange_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;"><?= $unread_exchange_count ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php // Page content is inserted here ?>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlayMain');
    if (sidebar) { sidebar.classList.toggle('show'); if (overlay) overlay.classList.toggle('show'); }
}
function toggleSection(el) {
    var section = el.closest('.nav-section');
    if (!section) return;
    section.classList.toggle('open');
}
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('hamburgerSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('closeSidebarBtn');
    if (hamburgerBtn) hamburgerBtn.addEventListener('click', () => { if(sidebar) { sidebar.classList.add('open'); overlay.classList.add('show'); document.body.style.overflow = 'hidden'; }});
    if (closeBtn) closeBtn.addEventListener('click', () => { if(sidebar) { sidebar.classList.remove('open'); overlay.classList.remove('show'); document.body.style.overflow = ''; }});
    if (overlay) overlay.addEventListener('click', () => { if(sidebar) { sidebar.classList.remove('open'); overlay.classList.remove('show'); document.body.style.overflow = ''; }});
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
