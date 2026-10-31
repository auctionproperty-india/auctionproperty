<?php
// ============================================================
// ✅ Header – Top Nav + Sidebar + Collapsible Admin Sections
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

blockBadBots();
checkRateLimit(60, 60);

$current_page = basename($_SERVER['PHP_SELF']);
$hide_top_nav = !in_array($current_page, ['index.php', 'login.php', 'register.php']);

$is_logged_in = isset($_SESSION['user_id']);
$role = $is_logged_in ? ($_SESSION['role'] ?? 'user') : 'guest';

// SINGLE LOGIN LOGIC
if ($is_logged_in && $role != 'admin' && !isset($_SESSION['impersonate_admin_id'])) {
    $db_session = safeFetch($pdo, "SELECT active_session_id FROM users WHERE id = ?", [$_SESSION['user_id']]);
    if ($db_session && !empty($db_session['active_session_id'])) {
        if ($db_session['active_session_id'] !== session_id()) {
            session_destroy();
            header("Location: login.php?msg=logged_out_elsewhere");
            exit;
        }
    }
}

$display_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User';
if ($is_logged_in && ($display_name === 'User' || empty($display_name))) {
    $db_user = safeFetch($pdo, "SELECT name FROM users WHERE id = ?", [$_SESSION['user_id']]);
    if ($db_user && !empty($db_user['name'])) {
        $display_name = $db_user['name'];
        $_SESSION['name'] = $db_user['name'];
    }
}

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

// 🔥 NEW: SALES TARGET & REFERRAL LOGIC
$sales_referral_link = '';
$target_goal = 0;
$target_achieved = 0;
$target_percentage = 0;

if ($is_logged_in && $role == 'sales') {
    $sales_referral_link = getReferralLink($_SESSION['user_id']);

    try {
        $target_query = $pdo->prepare("SELECT sales_target FROM users WHERE id = ?");
        $target_query->execute([$_SESSION['user_id']]);
        $target_goal = $target_query->fetchColumn() ?: 10;

        $achieved_query = $pdo->prepare("SELECT COUNT(*) FROM sales_leads WHERE assigned_to = ? AND status = 'converted'");
        $achieved_query->execute([$_SESSION['user_id']]);
        $target_achieved = $achieved_query->fetchColumn();

        if($target_goal > 0) {
            $target_percentage = round(($target_achieved / $target_goal) * 100);
        }
    } catch(Exception $e) {}
}

// NOTIFICATIONS
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

$reg_date = '';
$expiry_date = null;
$days_left = 0;
$plan_name = 'No Plan';
$user_email = '';
$user_coins = 0;
$ba_status = 'none';

if ($is_logged_in && $role == 'user') {
    $user_id = $_SESSION['user_id'];
    $user_sidebar = safeFetch($pdo, "SELECT name, email, created_at as reg_date, coins, ba_status FROM users WHERE id = ?", [$user_id]);
    $reg_date = !empty($user_sidebar['reg_date']) ? date('d M Y', strtotime($user_sidebar['reg_date'])) : 'N/A';
    $user_email = $user_sidebar['email'] ?? '';
    $user_coins = $user_sidebar['coins'] ?? 0;
    $ba_status = $user_sidebar['ba_status'] ?? 'none';

    $sub_info = safeFetch($pdo, "SELECT s.start_date, s.end_date, p.name as plan_name, p.price as plan_price 
                                 FROM subscriptions s 
                                 LEFT JOIN packages p ON s.package_id = p.id 
                                 WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE
                                 ORDER BY s.end_date DESC LIMIT 1", [$user_id]);
    
    if ($sub_info) {
        $plan_name = $sub_info['plan_name'] ?? 'Active Plan';
        $expiry_date = $sub_info['end_date'];
        $days_left = (int) ((strtotime($expiry_date) - time()) / (60 * 60 * 24));
        $days_left = max(0, $days_left);
        if ((!empty($sub_info['plan_price']) && $sub_info['plan_price'] > 0) || (strtolower($plan_name) !== 'free' && $plan_name !== 'No Plan')) {
            $ba_status = 'active';
        }
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
        
        /* 🔥 TARGET PROGRESS BOX IN SIDEBAR */
        .target-box { background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; padding: 15px; margin: 10px 10px 20px 10px; }
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

<!-- HAMBURGER SIDEBAR -->
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
        <!-- ADMIN SIDEBAR -->
        <a href="admin_dashboard.php" class="<?= $current_page == 'admin_dashboard.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>
        <!-- ... (बाकी Admin links पहले जैसे ही रहेंगे, जगह बचाने के लिए यहाँ शामिल नहीं है, लेकिन आप इसे पूरा रखें) ... -->
        <!-- Admin Sidebar Section 1 to 7 here -->
    <?php elseif ($role == 'sales'): ?>
        <!-- SALES SIDEBAR -->
        <a href="sales_dashboard.php" class="<?= $current_page == 'sales_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
        <a href="sales_properties.php" class="<?= $current_page == 'sales_properties.php' ? 'active' : '' ?>"><i class="fas fa-building"></i> <span>All Properties</span></a>
        <a href="sales_leads.php" class="<?= $current_page == 'sales_leads.php' ? 'active' : '' ?>"><i class="fas fa-tasks"></i> <span>My Leads</span></a>
        <a href="sales_lead_upload.php" class="<?= $current_page == 'sales_lead_upload.php' ? 'active' : '' ?>"><i class="fas fa-upload"></i> <span>Upload Leads</span></a>
        <a href="sales_lead_add.php" class="<?= $current_page == 'sales_lead_add.php' ? 'active' : '' ?>"><i class="fas fa-plus"></i> <span>Add Lead</span></a>
        
        <!-- Registration Link & My Team -->
        <a href="<?= htmlspecialchars($sales_referral_link) ?>" target="_blank" title="Click to copy and share">
            <i class="fas fa-link text-primary"></i> <span>Registration Link</span>
        </a>
        <a href="my_team.php" class="<?= $current_page == 'my_team.php' ? 'active' : '' ?>">
            <i class="fas fa-user-friends text-info"></i> <span>My Team</span>
        </a>

        <!-- Sales Target Box -->
        <div class="target-box shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span style="font-size:12px; font-weight:800; color:#475569;"><i class="fas fa-bullseye text-danger"></i> Target</span>
                <span style="font-size:11px; font-weight:bold; color:<?= $target_percentage >= 100 ? '#10b981' : '#f59e0b' ?>;"><?= $target_percentage ?>%</span>
            </div>
            <div style="background:#e2e8f0; height:8px; border-radius:10px; overflow:hidden; margin-bottom:6px;">
                <div style="background:<?= $target_percentage >= 100 ? '#10b981' : 'linear-gradient(90deg, #3b82f6, #6366f1)' ?>; height:100%; width:<?= min(100, $target_percentage) ?>%;"></div>
            </div>
            <div style="font-size:11px; color:#64748b; font-weight:600;">
                <?= $target_achieved ?> / <?= $target_goal ?> Converted
            </div>
        </div>

        <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
        <a href="change_password.php" class="<?= $current_page == 'change_password.php' ? 'active' : '' ?>"><i class="fas fa-key"></i> <span>Change Password</span></a>
        <a href="support.php" class="<?= $current_page == 'support.php' ? 'active' : '' ?>"><i class="fas fa-headset"></i> <span>Support</span></a>

    <?php else: ?>
        <!-- USER SIDEBAR -->
        <!-- ... (बाकी User links पहले जैसे ही रहेंगे) ... -->
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
                    </div>
                </div>
            </div>
        </div>
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
