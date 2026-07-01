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

// ---- Referral Earnings ----
$earnings = getReferralEarnings($pdo, $user_id, 'pending');
$paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
$total_pending = array_sum(array_column($earnings, 'amount'));
$total_paid = array_sum(array_column($paid_earnings, 'net_amount'));

// ---- Wallet ----
$wallet_balance = getUserWalletBalance($pdo, $user_id);

// ---- Show Images ----
$show_images = userHasActiveSubscription($pdo, $user_id);

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
$props = $stmt->fetchAll();

// ---- Render Best Deal Card (Modern Colorful) ----
function renderBestDealCard($prop, $show_images) {
    $gradients = [
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
        'linear-gradient(135deg, #fddb92 0%, #d1fdff 100%)',
    ];
    $gradient = $gradients[array_rand($gradients)];
    ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100" style="border-radius:24px; overflow:hidden; border:none; box-shadow:0 15px 40px -10px rgba(0,0,0,0.12); transition:all 0.4s; background: <?= $gradient ?>; color:#fff;">
            <?php if($show_images && !empty($prop['image_url'])): ?>
                <img src="<?= htmlspecialchars($prop['image_url']) ?>" style="height:200px; width:100%; object-fit:cover; border-bottom:3px solid rgba(255,255,255,0.2);" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:200px; background:rgba(255,255,255,0.1); display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(4px); border-bottom:3px solid rgba(255,255,255,0.2);">
                    <i class="fas fa-lock" style="font-size:2.5rem; opacity:0.8;"></i>
                    <span style="font-size:0.9rem; font-weight:600; margin-top:8px;">🔒 Subscribe to unlock</span>
                    <a href="user_packages.php" class="btn btn-sm btn-warning mt-2" style="border-radius:30px; font-weight:600; color:#1e293b;">Subscribe Now</a>
                </div>
            <?php endif; ?>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; background:rgba(255,255,255,0.2); padding:4px 14px; border-radius:30px;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                    <?php if(!empty($prop['auction_start_time'])): ?>
                        <span style="font-size:0.75rem; opacity:0.8;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mt-2" style="color:#fff;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:#fff;">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                <div style="font-size:0.85rem; opacity:0.8;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                <a href="property_detail.php?id=<?= $prop['id'] ?>" style="display:block; margin-top:16px; background:rgba(255,255,255,0.2); backdrop-filter:blur(4px); border:1px solid rgba(255,255,255,0.2); color:#fff; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
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
        background: rgba(251, 191, 36, 0.1);
        border-radius: 50%;
    }
    .user-welcome-banner h2 {
        font-weight: 800;
        letter-spacing: -0.5px;
    }
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
    .wallet-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -8px rgba(0,0,0,0.08);
    }
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
    .best-deals-section .card:hover {
        transform: translateY(-10px) !important;
        box-shadow: 0 30px 60px -15px rgba(0,0,0,0.2) !important;
    }
</style>

<!-- Welcome Banner -->
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

<!-- Wallet & Stats -->
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

<!-- Subscription Status -->
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

<!-- Best Deals (Modern Colorful) -->
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
