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
$user_stmt = $pdo->prepare("SELECT id, name, email, phone, city, referral_code, referred_by, role, status, created_at as reg_date, wallet_balance, coins FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
$user_city = $user['city'] ?? '';
$coins_balance = (int)($user['coins'] ?? 0);

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

// ---- Today's Auctions (using auction_date) ----
$today_sql = "SELECT * FROM properties WHERE status = 'available' AND auction_date = CURRENT_DATE ORDER BY id DESC";
$today_stmt = $pdo->prepare($today_sql);
$today_stmt->execute();
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

// ---- Render Dashboard Card ----
function renderDashboardCard($prop, $show_images, $is_today = false) {
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
                <a href="property_detail.php?id=<?= $prop['id'] ?>&source=auction" style="display:block; margin-top:16px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
            </div>
            <?php if($show_images && !empty($prop['image_url'])): ?>
                <img src="<?= htmlspecialchars($prop['image_url']) ?>" style="height:200px; width:100%; object-fit:cover; border-top:3px solid <?= $border ?>;" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:150px; background:rgba(255,255,255,0.08); display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(4px); border-top:3px solid <?= $border ?>; padding:10px;">
                    <i class="fas fa-lock" style="font-size:1.8rem; opacity:0.7; color:<?= $text_color ?>;"></i>
                    <span style="font-size:0.8rem; font-weight:600; margin-top:4px; color:<?= $text_color ?>;">🔒 Subscribe to unlock</span>
                    <a href="user_packages.php" class="btn btn-sm btn-primary mt-2" style="border-radius:30px; font-weight:600; color:#ffffff; background:#2563eb; border:none;">Subscribe Now</a>
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
        padding: 30px 35px;
        color: #ffffff !important;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px -10px rgba(15,23,42,0.3);
    }
    .user-welcome-banner * { color: #ffffff !important; }
    .user-welcome-banner h2 { font-weight: 800; letter-spacing: -0.5px; }
    .user-welcome-banner .opacity-75 { color: rgba(255,255,255,0.75) !important; }
    .user-welcome-banner .banner-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
    .user-welcome-banner .banner-actions .btn { border-radius: 30px; font-weight: 600; padding: 8px 20px; font-size: 0.9rem; border: none; color: #ffffff !important; }
    .user-welcome-banner .banner-actions .btn-light { background: #ffffff !important; color: #0f172a !important; }
    .user-welcome-banner .banner-actions .btn-primary { background: #2563eb !important; color: #ffffff !important; }
    .user-welcome-banner .banner-actions .btn-primary:hover { background: #1d4ed8 !important; }
    .user-welcome-banner .banner-actions .btn-light:hover { background: #e2e8f0 !important; color: #0f172a !important; }
    .user-welcome-banner .banner-stats { display: flex; gap: 15px; justify-content: flex-end; flex-wrap: wrap; font-size: 0.9rem; color: #ffffff !important; }
    .user-welcome-banner .banner-stats div { color: #ffffff !important; }
    .user-welcome-banner .banner-stats .opacity-75 { color: rgba(255,255,255,0.75) !important; }
    .user-welcome-banner .banner-stats strong { color: #ffffff !important; }
    .subscription-status-inline { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.15); color: #ffffff !important; }
    .subscription-status-inline .label { opacity: 0.7; font-size: 0.85rem; color: rgba(255,255,255,0.7) !important; }
    .subscription-status-inline .status-badge { font-weight: 600; color: #ffffff !important; }
    .subscription-status-inline .status-badge .icon { font-size: 1.2rem; margin-right: 4px; }
    .subscription-status-inline .status-badge .badge { color: #0f172a !important; background-color: #10b981 !important; }
    .subscription-status-inline .status-badge .badge.bg-warning { background-color: #10b981 !important; }
    .subscription-status-inline .status-badge .ms-2 { color: rgba(255,255,255,0.7) !important; }
    .subscription-status-inline .btn-primary { background: #2563eb !important; color: #ffffff !important; border: none; }
    .subscription-status-inline .btn-primary:hover { background: #1d4ed8 !important; }
    .section-title { font-weight: 800; color: #0f172a; margin-bottom: 20px; position: relative; }
    .section-title i { margin-right: 10px; }
    .card:hover { transform: translateY(-10px) !important; box-shadow: 0 30px 60px -15px rgba(0,0,0,0.2) !important; }
    .no-auction-msg { background: #f8fafc; border-radius: 30px; padding: 30px; text-align: center; border: 2px dashed #e2e8f0; }
    .no-auction-msg i { font-size: 2.5rem; opacity:0.3; }
    /* Spinner Styles */
    .spin-wheel {
        transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
    }
    .spin-wheel.pulse {
        animation: spinPulse 1s infinite;
    }
    @keyframes spinPulse {
        0% { box-shadow: 0 0 30px rgba(251,191,36,0.3); }
        50% { box-shadow: 0 0 60px rgba(251,191,36,0.6); }
        100% { box-shadow: 0 0 30px rgba(251,191,36,0.3); }
    }
    @keyframes slideIn {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @media (max-width:576px) {
        .user-welcome-banner { padding: 20px; }
        .user-welcome-banner .banner-stats { justify-content: flex-start; }
    }
</style>

<!-- ===== WELCOME BANNER ===== -->
<div class="user-welcome-banner">
    <div class="row align-items-center">
        <div class="col-md-7">
            <h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
            <p class="opacity-75" style="margin-bottom:4px;">Discover the most affordable properties in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'your city' ?></p>
            <div class="banner-actions">
                <a href="index.php" class="btn btn-light"><i class="fas fa-th-list me-2"></i>View All Properties</a>
                <a href="user_properties.php" class="btn btn-light"><i class="fas fa-list me-2"></i>My Properties</a>
                <a href="user_packages.php" class="btn btn-primary"><i class="fas fa-rocket me-2"></i>Subscribe Now</a>
            </div>
        </div>
        <div class="col-md-5 text-md-end">
            <div class="banner-stats">
                <div><span class="opacity-75">💰 Wallet</span><br><strong>₹ <?= indianCurrencyFormat($wallet_balance) ?></strong></div>
                <div><span class="opacity-75">⏳ Pending</span><br><strong>₹ <?= indianCurrencyFormat($total_pending) ?></strong></div>
                <div><span class="opacity-75">✅ Paid</span><br><strong>₹ <?= indianCurrencyFormat($total_paid) ?></strong></div>
                <div><span class="opacity-75">🪙 Coins</span><br><strong><?= $coins_balance ?></strong></div>
            </div>
        </div>
    </div>

    <div class="subscription-status-inline">
        <span class="label">📋 Subscription Status:</span>
        <?php if($is_subscribed): ?>
            <span class="status-badge text-success">
                <span class="icon">✅</span> Active
                <span class="badge bg-success ms-2">⏳ <?= $days_left ?> Days Left</span>
                <span class="ms-2" style="font-size:0.8rem; opacity:0.7;">Activated: <?= $activation_date_formatted ?></span>
            </span>
        <?php else: ?>
            <span class="status-badge text-danger">
                <span class="icon">❌</span> Not Active
                <a href="user_packages.php" class="btn btn-primary btn-sm ms-2">Buy Plan</a>
                <span class="ms-2" style="font-size:0.8rem; opacity:0.7;">Activated: Not Active</span>
            </span>
        <?php endif; ?>
    </div>
</div>

<!-- ===== DAILY SPIN SYSTEM ===== -->
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, #1e293b, #334155); color: #fff;">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h4><i class="fas fa-gift me-2" style="color: #fbbf24;"></i>Daily Spin</h4>
            <p class="opacity-75 small">Spin up to 5 times per slot. Every 5 spins = 5-20 coins!</p>
            <div class="d-flex gap-3 flex-wrap">
                <span class="badge bg-primary">Slot: <?= getSlotTimeRange(getCurrentSlot()) ?></span>
                <span class="badge bg-info">Spins Used: <span id="spinCount"><?= getUserSpinData($pdo, $user_id)['spins_used'] ?></span>/5</span>
                <?php if(getUserSpinData($pdo, $user_id)['reward_given']): ?>
                    <span class="badge bg-success">✅ Reward Claimed!</span>
                <?php endif; ?>
            </div>
            <div id="spinMessage" class="mt-2 small"></div>
        </div>
        <div class="col-md-6 text-center">
            <div class="spinner-wrapper" style="position:relative; display:inline-block;">
                <div id="spinWheel" class="spin-wheel" style="width:120px; height:120px; border-radius:50%; background: conic-gradient(
                    #fbbf24 0deg 72deg, 
                    #ef4444 72deg 144deg, 
                    #10b981 144deg 216deg, 
                    #3b82f6 216deg 288deg, 
                    #8b5cf6 288deg 360deg
                ); border:4px solid #fff; box-shadow:0 0 30px rgba(251,191,36,0.3); margin:0 auto;">
                </div>
                <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; width:30px; height:30px; border-radius:50%; border:3px solid #fbbf24;"></div>
                <div style="position:absolute; top:-10px; left:50%; transform:translateX(-50%); width:0; height:0; border-left:12px solid transparent; border-right:12px solid transparent; border-top:20px solid #fbbf24; filter:drop-shadow(0 0 10px rgba(251,191,36,0.5));"></div>
            </div>
            <button id="spinBtn" class="btn btn-warning mt-3 px-4 fw-bold" <?= (getUserSpinData($pdo, $user_id)['can_spin']) ? '' : 'disabled' ?>>
                <i class="fas fa-sync-alt"></i> Spin!
            </button>
        </div>
    </div>
</div>

<!-- ===== TODAY'S AUCTIONS ===== -->
<?php if(count($today_props) > 0): ?>
    <div class="section-title">
        <i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions
        <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span>
    </div>
    <div class="row">
        <?php foreach($today_props as $prop): ?>
            <?php renderDashboardCard($prop, $show_images, true); ?>
        <?php endforeach; ?>
    </div>
    <hr class="my-5">
<?php else: ?>
    <div class="no-auction-msg">
        <i class="fas fa-calendar-day"></i>
        <p class="mt-2 fw-bold">📭 No auction today</p>
        <p class="text-muted">Check best deals below.</p>
    </div>
    <hr class="my-4">
<?php endif; ?>

<!-- ===== BEST DEALS ===== -->
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const spinBtn = document.getElementById('spinBtn');
    const wheel = document.getElementById('spinWheel');
    const spinCount = document.getElementById('spinCount');
    const spinMessage = document.getElementById('spinMessage');

    // Predefined segments (5 segments)
    const segments = [0, 72, 144, 216, 288];
    let currentRotation = 0;

    spinBtn.addEventListener('click', function() {
        this.disabled = true;
        spinMessage.innerHTML = '🔄 Spinning...';
        
        // Random rotation (at least 5 full rotations + random segment)
        const randomSegment = segments[Math.floor(Math.random() * segments.length)];
        const extraSpin = Math.floor(Math.random() * 360); // extra randomness
        const totalRotation = 360 * 5 + randomSegment + extraSpin;
        currentRotation += totalRotation;
        
        wheel.style.transform = `rotate(${currentRotation}deg)`;
        wheel.classList.add('pulse');
        
        // AJAX call to spin
        fetch('spin_ajax.php')
            .then(response => response.json())
            .then(data => {
                wheel.classList.remove('pulse');
                if (data.success) {
                    spinCount.textContent = data.spins_used;
                    if (data.is_reward) {
                        spinMessage.innerHTML = `🎉 <strong>${data.message}</strong>`;
                        // Show coin animation
                        showCoinAnimation(data.coins);
                    } else {
                        spinMessage.innerHTML = `🔄 ${data.message}`;
                    }
                    if (data.spins_used >= 5) {
                        spinBtn.disabled = true;
                        spinBtn.innerHTML = '<i class="fas fa-check"></i> Done';
                    } else {
                        spinBtn.disabled = false;
                    }
                } else {
                    spinMessage.innerHTML = `❌ ${data.message}`;
                    spinBtn.disabled = false;
                }
            })
            .catch(error => {
                spinMessage.innerHTML = '❌ Error spinning. Please try again.';
                spinBtn.disabled = false;
                console.error('Spin error:', error);
            });
    });

    function showCoinAnimation(coins) {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed; bottom:20px; right:20px; background:#10b981; color:white; padding:16px 24px; border-radius:12px; font-weight:bold; box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:9999; animation: slideIn 0.5s ease;';
        toast.innerHTML = `🪙 +${coins} coins!`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s';
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }
});
</script>

<?php include 'footer.php'; ?>
