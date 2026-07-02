<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

// ---- User Data ----
$user_stmt = $pdo->prepare("SELECT id, name, email, phone, city, referral_code, referred_by, role, status, created_at as reg_date, wallet_balance FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
$user_city = $user['city'] ?? '';

// ---- Subscription ----
$active_sub = $pdo->prepare("SELECT s.*, p.name as pkg_name, s.start_date, s.end_date, (s.end_date - CURRENT_DATE) as days_left FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE ORDER BY s.id DESC LIMIT 1");
$active_sub->execute([$user_id]);
$sub_info = $active_sub->fetch();
$is_subscribed = $sub_info ? true : false;

$reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
$activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
$expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
$days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

// ---- Show Images ----
$show_images = userHasActiveSubscription($pdo, $user_id);

// ---- Today's Auctions ----
$today_str = date('d M Y');
$today_sql = "SELECT * FROM properties WHERE status = 'available' AND auction_start_time ILIKE ? ORDER BY id DESC";
$today_stmt = $pdo->prepare($today_sql);
$today_stmt->execute(['%'.$today_str.'%']);
$today_props = $today_stmt->fetchAll();

// ---- Best Deals (10 Lowest Price) ----
$sql = "SELECT * FROM properties WHERE status = 'available'";
$params = [];
if(!empty($user_city)) {
    $sql .= " AND city ILIKE ?";
    $params[] = '%'.$user_city.'%';
}
$sql .= " ORDER BY price ASC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$best_props = $stmt->fetchAll();

// ---- Render Card (same as home page style) ----
function renderDashboardCard($prop, $show_images, $is_today = false) {
    $badge_html = '';
    if($is_today) {
        $badge_html = '<span class="badge bg-danger text-white px-3 py-2" style="border-radius:30px; font-size:0.7rem; position:absolute; top:12px; right:12px; z-index:10; box-shadow:0 4px 12px rgba(220,38,38,0.4);"><i class="fas fa-fire"></i> Today</span>';
    }
    $gradients = [
        ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #1e3a5f 0%, #3b82f6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #064e3b 0%, #10b981 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #4c1d95 0%, #8b5cf6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #b91c1c 0%, #ef4444 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #78350f 0%, #f59e0b 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #172554 0%, #6366f1 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%)', 'text' => 'dark'],
    ];
    $g = $gradients[array_rand($gradients)];
    $text_color = ($g['text'] == 'white') ? '#ffffff' : '#0f172a';
    $shadow = ($g['text'] == 'white') ? '0 15px 40px -10px rgba(0,0,0,0.3)' : '0 15px 40px -10px rgba(0,0,0,0.1)';
    $border = ($g['text'] == 'white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.05)';
    ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100" style="border-radius:24px; overflow:hidden; border:none; box-shadow:<?= $shadow ?>; transition:all 0.4s; background: <?= $g['bg'] ?>; color:<?= $text_color ?>;">
            <?= $badge_html ?>
            <!-- Details TOP -->
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                    <?php if(!empty($prop['auction_start_time'])): ?>
                        <span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mt-2" style="color:<?= $text_color ?>;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:<?= $text_color ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                <div style="font-size:0.85rem; opacity:0.8; color:<?= $text_color ?>;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                <a href="property_detail.php?id=<?= $prop['id'] ?>" style="display:block; margin-top:16px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
            </div>
            <!-- Image / Subscribe BOTTOM (small) -->
            <?php if($show_images && !empty($prop['image_url'])): ?>
                <img src="<?= htmlspecialchars($prop['image_url']) ?>" style="height:200px; width:100%; object-fit:cover; border-top:3px solid <?= $border ?>;" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:150px; background:rgba(255,255,255,0.08); display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(4px); border-top:3px solid <?= $border ?>; padding:10px;">
                    <i class="fas fa-lock" style="font-size:1.8rem; opacity:0.7; color:<?= $text_color ?>;"></i>
                    <span style="font-size:0.8rem; font-weight:600; margin-top:4px; color:<?= $text_color ?>;">🔒 Subscribe to unlock</span>
                    <a href="user_packages.php" class="btn btn-sm btn-warning mt-2" style="border-radius:30px; font-weight:600; color:#1e293b;">Subscribe Now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
<style>
    .user-welcome-banner {
        background: linear-gradient(135deg, #0f172a, #1e293b);
        border-radius: 30px;
        padding: 35px 40px;
        color: white;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px -10px rgba(15,23,42,0.3);
    }
    .user-welcome-banner::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: rgba(251, 191, 36, 0.1);
        border-radius: 50%;
    }
    .user-welcome-banner h2 { font-weight: 800; letter-spacing: -0.5px; }
    .subscription-status {
        background: white;
        border-radius: 20px;
        padding: 20px 24px;
        border-left: 6px solid <?= $is_subscribed ? '#10b981' : '#f59e0b' ?>;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04);
        margin-bottom: 30px;
    }
    .section-title { font-weight:800; color:#0f172a; margin-bottom:20px; position:relative; }
    .section-title i { margin-right:10px; }
    .card:hover { transform: translateY(-10px) !important; box-shadow: 0 30px 60px -15px rgba(0,0,0,0.2) !important; }
    @media (max-width:576px) { .user-welcome-banner { padding: 20px; } }
</style>

<!-- Welcome Banner -->
<div class="user-welcome-banner">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
            <p class="opacity-75">Discover the most affordable properties in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'your city' ?></p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="user_packages.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold text-primary shadow-sm">
                <i class="fas fa-rocket"></i> Subscribe
            </a>
        </div>
    </div>
</div>

<!-- Subscription Status -->
<div class="subscription-status">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h6><i class="fas fa-user-clock me-2"></i>Subscription Status</h6>
            <?php if($is_subscribed): ?>
                <span class="badge bg-success p-2 fs-6">✅ <?= htmlspecialchars($sub_info['pkg_name']) ?> Active</span>
                <span class="badge bg-warning text-dark ms-2 p-2">⏳ <?= $days_left ?> Days Left</span>
            <?php else: ?>
                <span class="badge bg-secondary p-2 fs-6">🔴 No Active Plan</span>
                <a href="user_packages.php" class="btn btn-sm btn-primary ms-2">Buy Plan</a>
            <?php endif; ?>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if($is_subscribed): ?>
                <small>Activated: <?= $activation_date_formatted ?></small>
                <span class="ms-2">|</span>
                <small>Expires: <?= $expiry_date_formatted ?></small>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===== TODAY'S AUCTIONS ===== -->
<?php if(count($today_props) > 0): ?>
    <div class="section-title">
        <i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span>
    </div>
    <div class="row">
        <?php foreach($today_props as $prop): ?>
            <?php renderDashboardCard($prop, $show_images, true); ?>
        <?php endforeach; ?>
    </div>
    <hr class="my-5">
<?php else: ?>
    <div class="alert alert-light text-center py-4" style="border-radius:30px; background:#f8fafc;">
        <i class="fas fa-calendar-day" style="font-size:2rem; opacity:0.3;"></i>
        <p class="mt-2">No auctions scheduled for today. Check best deals below.</p>
    </div>
<?php endif; ?>

<!-- ===== BEST DEALS (10 Lowest Price) ===== -->
<div class="section-title">
    <i class="fas fa-fire" style="color:#f97316;"></i> Best Deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'Your City' ?>
    <span class="badge bg-primary rounded-pill ms-2"><?= count($best_props) ?></span>
</div>
<div class="row">
    <?php if(count($best_props) > 0): ?>
        <?php foreach($best_props as $prop): ?>
            <?php renderDashboardCard($prop, $show_images, false); ?>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12 text-center text-muted py-4">
            <i class="fas fa-search" style="font-size:2rem; opacity:0.3;"></i>
            <p class="mt-2">No properties available in your city. Explore all properties.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
