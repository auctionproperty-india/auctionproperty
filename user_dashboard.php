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
    .wallet-card .icon {
        font-size: 2rem;
        margin-bottom: 6px;
        display: block;
    }
    .wallet-card h5 {
        font-weight: 700;
        color: #0f172a;
    }
    .wallet-card .amount {
        font-size: 1.8rem;
        font-weight: 800;
        color: #2563eb;
    }
    .best-deals-section .card {
        border-radius: 24px;
        border: none;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04);
        transition: 0.3s;
        height: 100%;
        overflow: hidden;
    }
    .best-deals-section .card:hover {
        transform: translateY(-6px);
        box-shadow: 0 25px 45px -10px rgba(0,0,0,0.1);
    }
    .best-deals-section .card img {
        height: 180px;
        object-fit: cover;
        background: #f1f5f9;
    }
    .best-deals-section .card .badge-bank {
        background: #e0e7ff;
        color: #1e3a8a;
        font-weight: 600;
        font-size: 0.7rem;
        padding: 4px 12px;
        border-radius: 30px;
    }
    .best-deals-section .card .price {
        font-size: 1.3rem;
        font-weight: 700;
        color: #0f172a;
    }
    .best-deals-section .card .auction-date {
        font-size: 0.75rem;
        color: #64748b;
    }
    .best-deals-section .card .btn-detail {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        border: none;
        color: white;
        font-weight: 600;
        border-radius: 30px;
        padding: 8px 16px;
        width: 100%;
        transition: 0.3s;
    }
    .best-deals-section .card .btn-detail:hover {
        transform: scale(0.98);
        box-shadow: 0 8px 20px rgba(37,99,235,0.3);
    }
    .subscription-status {
        background: white;
        border-radius: 20px;
        padding: 20px 24px;
        border-left: 6px solid <?= $is_subscribed ? '#10b981' : '#f59e0b' ?>;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04);
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

<!-- Best Deals -->
<div class="best-deals-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fas fa-fire me-2" style="color:#f97316;"></i> Best Deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'Your City' ?></h4>
        <a href="index.php" class="btn btn-outline-primary btn-sm rounded-pill px-4">View All <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row">
        <?php if(count($props) > 0): ?>
            <?php foreach($props as $p): ?>
            <div class="col-md-4 mb-4">
                <div class="card">
                    <?php if($show_images && !empty($p['image_url'])): ?>
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['title']) ?>">
                    <?php else: ?>
                        <div style="height:180px; background:linear-gradient(145deg,#f8fafc,#e2e8f0); display:flex; align-items:center; justify-content:center; flex-direction:column; color:#94a3b8;">
                            <i class="fas fa-home" style="font-size:3rem;"></i>
                            <span class="badge bg-warning mt-2 text-dark">🔒 Subscribe</span>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span class="badge-bank">🏦 <?= htmlspecialchars($p['bank_name'] ?? 'Bank') ?></span>
                            <?php if(!empty($p['auction_start_time'])): ?>
                                <span class="auction-date"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($p['auction_start_time']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h6 class="fw-bold mt-2"><?= htmlspecialchars($p['title']) ?></h6>
                        <div class="price">₹ <?= indianCurrencyFormat($p['price']) ?></div>
                        <div class="text-muted small"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($p['city'] ?? '') ?></div>
                        <a href="property_detail.php?id=<?= $p['id'] ?>" class="btn-detail mt-2 d-block text-center">View Details</a>
                    </div>
                </div>
            </div>
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
