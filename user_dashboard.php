<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

$user_stmt = $pdo->prepare("SELECT id, name, email, phone, city, referral_code, referred_by, role, status, created_at as reg_date, wallet_balance FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
$user_city = $user['city'] ?? '';

$active_sub = $pdo->prepare("SELECT s.*, p.name as pkg_name, s.start_date, s.end_date, (s.end_date - CURRENT_DATE) as days_left FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE ORDER BY s.id DESC LIMIT 1");
$active_sub->execute([$user_id]);
$sub_info = $active_sub->fetch();
$is_subscribed = $sub_info ? true : false;

$reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
$activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
$expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
$days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

$earnings = getReferralEarnings($pdo, $user_id, 'pending');
$paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
$total_pending = array_sum(array_column($earnings, 'amount'));
$total_paid = array_sum(array_column($paid_earnings, 'net_amount'));

$wallet_balance = getUserWalletBalance($pdo, $user_id);
$show_images = userHasActiveSubscription($pdo, $user_id);

$sql = "SELECT * FROM properties WHERE status = 'available'";
$params = [];
if(!empty($user_city)) {
    $sql .= " AND city ILIKE ?";
    $params[] = '%'.$user_city.'%';
}
$sql .= " ORDER BY price ASC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$props = $stmt->fetchAll();

function renderBestDealCard($prop, $show_images) {
    $gradients = [
        ['bg' => 'linear-gradient(135deg, #0f0c29, #302b63, #24243e)', 'text' => '#ffffff', 'accent' => '#ffd700'],
        ['bg' => 'linear-gradient(135deg, #1a1a2e, #16213e, #0f3460)', 'text' => '#ffffff', 'accent' => '#e94560'],
        ['bg' => 'linear-gradient(135deg, #1e3c72, #2a5298)', 'text' => '#ffffff', 'accent' => '#f9ca24'],
        ['bg' => 'linear-gradient(135deg, #0b1a2e, #1b3a4b, #2c5a6e)', 'text' => '#f0f4f8', 'accent' => '#48dbfb'],
        ['bg' => 'linear-gradient(135deg, #1c1c1c, #2d2d2d, #3d3d3d)', 'text' => '#f5f5f5', 'accent' => '#ff6b6b'],
        ['bg' => 'linear-gradient(135deg, #0d0d0d, #1a1a2e, #16213e)', 'text' => '#e0e0e0', 'accent' => '#f093fb'],
    ];
    $g = $gradients[array_rand($gradients)];
    ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100" style="border-radius:28px; overflow:hidden; border:none; box-shadow:0 20px 40px -12px rgba(0,0,0,0.2); transition:all 0.4s; background: <?= $g['bg'] ?>; color: <?= $g['text'] ?>;">
            <?php if($show_images && !empty($prop['image_url'])): ?>
                <img src="<?= htmlspecialchars($prop['image_url']) ?>" style="height:200px; width:100%; object-fit:cover; border-bottom:2px solid rgba(255,255,255,0.06);" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:200px; background:rgba(255,255,255,0.04); display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(6px); border-bottom:2px solid rgba(255,255,255,0.05);">
                    <i class="fas fa-lock" style="font-size:2.5rem; color:<?= $g['accent'] ?>; opacity:0.6;"></i>
                    <span style="font-size:0.9rem; font-weight:600; margin-top:8px; color:<?= $g['text'] ?>; opacity:0.7;">🔒 Subscribe to unlock</span>
                    <a href="user_packages.php" class="btn btn-sm mt-2" style="border-radius:30px; font-weight:600; background:<?= $g['accent'] ?>; border:none; color:#1a1a2e;">Subscribe</a>
                </div>
            <?php endif; ?>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.65rem; font-weight:700; text-transform:uppercase; background:rgba(255,255,255,0.06); padding:4px 14px; border-radius:30px; color:<?= $g['accent'] ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                    <?php if(!empty($prop['auction_start_time'])): ?>
                        <span style="font-size:0.75rem; opacity:0.5;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mt-2" style="color:<?= $g['text'] ?>; line-height:1.3;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.8rem; font-weight:800; color:<?= $g['accent'] ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                <div style="font-size:0.9rem; opacity:0.6;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                <a href="property_detail.php?id=<?= $prop['id'] ?>" style="display:block; margin-top:18px; background:rgba(255,255,255,0.06); backdrop-filter:blur(4px); border:1px solid rgba(255,255,255,0.08); color:<?= $g['text'] ?>; font-weight:600; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
            </div>
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
        background: rgba(251, 191, 36, 0.08);
        border-radius: 50%;
    }
    .user-welcome-banner h2 { font-weight: 800; letter-spacing: -0.5px; }
    .wallet-card {
        background: white;
        border-radius: 24px;
        padding: 20px 24px;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04);
        border: 1px solid rgba(255,255,255,0.3);
        backdrop-filter: blur(4px);
        transition: 0.3s;
        text-align: center;
    }
    .wallet-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -8px rgba(0,0,0,0.08); }
    .wallet-card .icon { font-size: 2rem; margin-bottom: 6px; display: block; }
    .wallet-card h5 { font-weight: 700; color: #0f172a; }
    .wallet-card .amount { font-size: 1.8rem; font-weight: 800; color: #2563eb; }
    .subscription-status {
        background: white;
        border-radius: 20px;
        padding: 20px 24px;
        border-left: 6px solid <?= $is_subscribed ? '#10b981' : '#f59e0b' ?>;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04);
    }
    .best-deals-section .card:hover { transform: translateY(-10px) !important; box-shadow: 0 30px 60px -15px rgba(0,0,0,0.25) !important; }
</style>

<div class="user-welcome-banner">
    <div class="row align-items-center">
        <div class="col-md-7">
            <h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
            <p class="opacity-75">Discover the most affordable properties in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'your city' ?></p>
        </div>
        <div class="col-md-5 text-md-end">
            <a href="user_packages.php" class="btn btn-light btn-lg rounded-pill px-5 fw-bold text-primary shadow-sm">
                <i class="fas fa-rocket"></i> Subscribe Now
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="wallet-card">
            <span class="icon">💰</span>
            <h5>Wallet Balance</h5>
            <div class="amount">₹ <?= indianCurrencyFormat($wallet_balance) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wallet-card">
            <span class="icon">⏳</span>
            <h5>Pending</h5>
            <div class="amount text-warning">₹ <?= indianCurrencyFormat($total_pending) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wallet-card">
            <span class="icon">✅</span>
            <h5>Paid</h5>
            <div class="amount text-success">₹ <?= indianCurrencyFormat($total_paid) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="wallet-card">
            <span class="icon">📅</span>
            <h5>Registered</h5>
            <div class="amount" style="font-size:1.2rem;"><?= $reg_date_formatted ?></div>
        </div>
    </div>
</div>

<div class="subscription-status mb-4">
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

<div class="best-deals-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fas fa-fire me-2" style="color:#f97316;"></i> Best Deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'Your City' ?></h4>
        <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">View All <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row">
        <?php if(count($props) > 0): ?>
            <?php foreach($props as $p): ?>
                <?php renderBestDealCard($p, $show_images); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-4">
                <i class="fas fa-search" style="font-size: 2rem; opacity:0.3;"></i>
                <p class="mt-2">No properties available in your city. Explore all properties.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
