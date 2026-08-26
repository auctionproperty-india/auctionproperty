<?php
// ============================================================
// ✅ Header – Top Nav with Hamburger + Sidebar (Index only)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Current page name
$current_page = basename($_SERVER['PHP_SELF']);
$hide_top_nav = ($current_page != 'index.php'); // only show on index

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

        /* ====== Sidebar (old – kept for reference) ====== */
        .sidebar { /* ... existing sidebar styles remain ... */ }
        .main-content { padding: 30px 35px; min-height: 100vh; transition: margin-left 0.3s; }
        body.role-admin .main-content { padding-top: 0 !important; }
        body.role-admin .main-content, body.role-user .main-content, body.role-sales .main-content { margin-left: 280px; }
        body.role-guest .main-content { margin-left: 0 !important; }
        @media (max-width: 991px) { .main-content { margin-left: 0 !important; padding: 15px; } }

        /* other styles remain same... */
    </style>
</head>
<body class="role-<?= $is_logged_in ? $role : 'guest' ?> <?= $hide_top_nav ? 'top-nav-hidden' : '' ?>">

<!-- ====== TOP NAV – only on index ====== -->
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
        <!-- Add any extra static links if needed -->
    </ul>
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

<!-- ====== Existing Sidebar (for logged-in users) ====== -->
<?php if ($is_logged_in): ?>
<div class="sidebar-overlay" id="sidebarOverlayMain" onclick="toggleSidebar()"></div>
<div class="sidebar" id="mainSidebar">
    <!-- ... same as before ... -->
</div>
<?php endif; ?>

<div class="main-content">
    <!-- ====== Top Bar (User Info) – same as before ====== -->
    <?php if ($is_logged_in): ?>
    <div class="top-bar">
        <!-- ... keep existing top-bar ... -->
    </div>
    <?php endif; ?>

<?php
// Everything else (content) goes here
?>

<script>
// Toggle hamburger sidebar
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('hamburgerSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('closeSidebarBtn');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', openSidebar);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
});
</script>

</body>
</html>
