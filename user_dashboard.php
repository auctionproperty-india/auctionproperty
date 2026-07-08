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

// ---- Referral Earnings (Gross for Pending & Paid) ----
$pending_earnings = getReferralEarnings($pdo, $user_id, 'pending');
$paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');

// ✅ Pending = Gross (amount)
$total_pending = array_sum(array_column($pending_earnings, 'amount'));

// ✅ Paid = Gross (amount) – not net
$total_paid = array_sum(array_column($paid_earnings, 'amount'));

// ---- Wallet Balance ----
$wallet_balance = getUserWalletBalance($pdo, $user_id);

// ---- Show Images ----
$show_images = userHasActiveSubscription($pdo, $user_id);

// ---- Today's Auctions ----
$today_sql = "SELECT * FROM properties WHERE status = 'available' AND auction_date = CURRENT_DATE ORDER BY id DESC";
$today_stmt = $pdo->prepare($today_sql);
$today_stmt->execute();
$today_props = $today_stmt->fetchAll();

// ---- Best Deals ----
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

// ---- Get slot statuses ----
$slot_statuses = [];
for ($slot = 1; $slot <= 3; $slot++) {
    $slot_statuses[$slot] = getSlotStatus($pdo, $user_id, $slot);
}
$current_slot = getCurrentSlot();
$current_slot_data = getUserSpinData($pdo, $user_id, $current_slot);
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
    .spin-wheel { transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99); }
    .spin-wheel.pulse { animation: spinPulse 1s infinite; }
    @keyframes spinPulse {
        0% { box-shadow: 0 0 30px rgba(251,191,36,0.3); }
        50% { box-shadow: 0 0 60px rgba(251,191,36,0.6); }
        100% { box-shadow: 0 0 30px rgba(251,191,36,0.3); }
    }
    @keyframes slideIn {
        from { transform: translateX(100px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .confetti-container { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9998; overflow: hidden; }
    .confetti { position: absolute; width: 10px; height: 10px; background: #fbbf24; animation: confettiFall 2s linear; }
    @keyframes confettiFall {
        0% { transform: translateY(-20px) rotate(0deg); opacity: 1; }
        100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
    }
    .slot-card { background: rgba(255,255,255,0.05); border-radius: 16px; padding: 15px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(4px); margin-bottom: 12px; }
    .slot-card .slot-time { font-weight: 600; font-size: 1.1rem; }
    .slot-card .slot-status { font-size: 0.9rem; margin-top: 4px; }
    .slot-card .slot-status .badge { font-weight: 600; }
    .slot-card.missed { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); }
    .slot-card.claimed { background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); }
    .slot-card.current { background: rgba(37, 99, 235, 0.15); border-color: rgba(37, 99, 235, 0.3); }
    .slot-card.upcoming { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.05); }
    .modal-content { border: none; }
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
    <h4><i class="fas fa-gift me-2" style="color: #fbbf24;"></i>Daily Spin</h4>
    <div class="row g-3 mb-4">
        <?php foreach ($slot_statuses as $slot => $status): 
            $card_class = '';
            $badge_color = '';
            if ($status['is_past']) {
                if ($status['spins_used'] > 0) {
                    $card_class = 'claimed';
                    $badge_color = 'bg-success';
                } else {
                    $card_class = 'missed';
                    $badge_color = 'bg-danger';
                }
            } elseif ($status['is_current']) {
                $card_class = 'current';
                $badge_color = 'bg-primary';
            } else {
                $card_class = 'upcoming';
                $badge_color = 'bg-secondary';
            }
        ?>
        <div class="col-md-4">
            <div class="slot-card <?= $card_class ?>">
                <div class="slot-time"><?= $status['time_range'] ?></div>
                <div class="slot-status">
                    <span class="badge <?= $badge_color ?>"><?= $status['label'] ?></span>
                    <span class="ms-2"><?= $status['message'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($current_slot_data['can_spin']): ?>
    <div class="row align-items-center mt-3">
        <div class="col-md-6">
            <p class="mb-1">Current Slot: <strong><?= getSlotTimeRange($current_slot) ?></strong></p>
            <p class="mb-1">Spins Used: <span id="spinCount"><?= $current_slot_data['spins_used'] ?></span>/5</p>
            <p class="mb-1">Coins Earned this slot: <span id="slotCoins"><?= $current_slot_data['coins_earned'] ?></span>/22</p>
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
            <button id="spinBtn" class="btn btn-warning mt-3 px-4 fw-bold" <?= ($current_slot_data['can_spin']) ? '' : 'disabled' ?>>
                <i class="fas fa-sync-alt"></i> Spin!
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary text-center mt-3">
        <?php if ($current_slot_data['spins_used'] >= 5): ?>
            You have completed this slot! Total coins earned: <?= $current_slot_data['coins_earned'] ?>/22
        <?php else: ?>
            No spins available for this slot.
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ===== PROPERTY MODAL ===== -->
<div class="modal fade" id="propertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 24px; overflow: hidden; background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                <h5 class="modal-title"><i class="fas fa-home me-2" style="color: #fbbf24;"></i>🏠 Low Price Property</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="propertyModalContent">
                    <!-- Dynamic content -->
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-undo-alt me-2"></i>Back to Spin</button>
                <a href="#" id="viewPropertyLink" class="btn btn-primary" target="_blank">View Details</a>
            </div>
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
    const slotCoins = document.getElementById('slotCoins');
    const propertyModal = new bootstrap.Modal(document.getElementById('propertyModal'));
    const propertyModalContent = document.getElementById('propertyModalContent');
    const viewPropertyLink = document.getElementById('viewPropertyLink');

    if (!spinBtn) {
        console.log('Spin button not found');
        return;
    }
    
    console.log('Spin system initialized');

    const segments = [0, 72, 144, 216, 288];
    let currentRotation = 0;
    let isSpinning = false;

    spinBtn.addEventListener('click', function() {
        if (isSpinning) return;
        isSpinning = true;
        this.disabled = true;
        spinMessage.innerHTML = '🔄 Spinning...';
        
        // Random rotation
        const randomSegment = segments[Math.floor(Math.random() * segments.length)];
        const extraSpin = Math.floor(Math.random() * 360);
        const totalRotation = 360 * 5 + randomSegment + extraSpin;
        currentRotation += totalRotation;
        
        wheel.style.transition = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
        wheel.style.transform = `rotate(${currentRotation}deg)`;
        wheel.classList.add('pulse');
        
        console.log('Spinning...');

        fetch('spin_ajax.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Spin response:', data);
                wheel.classList.remove('pulse');
                isSpinning = false;
                
                if (data.success) {
                    spinCount.textContent = data.spins_used || 0;
                    slotCoins.textContent = data.total_coins_earned || 0;
                    
                    if (data.is_reward) {
                        spinMessage.innerHTML = `🎉 +${data.coins} coins!`;
                        showCoinAnimation(data.coins);
                        launchStarShower(); // 🌟 New Star Shower Effect
                        // Update coins in banner
                        const coinSpan = document.querySelector('.banner-stats strong:last-child');
                        if (coinSpan) {
                            let current = parseInt(coinSpan.textContent);
                            if (!isNaN(current)) {
                                coinSpan.textContent = current + data.coins;
                            }
                        }
                        if (data.spins_used >= 5) {
                            spinBtn.disabled = true;
                            spinBtn.innerHTML = '<i class="fas fa-check"></i> Done';
                        } else {
                            spinBtn.disabled = false;
                        }
                    } else if (data.show_property && data.property) {
                        // Show property modal
                        const p = data.property;
                        const isCar = (p.type && (p.type.toLowerCase().includes('car') || p.type.toLowerCase().includes('vehicle')));
                        const icon = isCar ? '🚗' : '🏠';
                        const imageHtml = p.image_url ? `<img src="${p.image_url}" style="width:100%; max-height:200px; object-fit:cover; border-radius:12px; margin-bottom:12px;" alt="${p.title}">` : `<div style="height:150px; background:#1e293b; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8;"><i class="fas fa-image fa-2x"></i></div>`;
                        propertyModalContent.innerHTML = `
                            ${imageHtml}
                            <h5 class="fw-bold">${icon} ${p.title}</h5>
                            <p class="text-muted">🏦 ${p.bank_name || 'Bank'}</p>
                            <p class="text-warning fw-bold">₹ ${parseInt(p.price).toLocaleString('en-IN')}</p>
                            <p><i class="fas fa-map-pin"></i> ${p.city || 'N/A'}</p>
                            <p><small class="text-muted">Type: ${p.type || 'N/A'}</small></p>
                            <div class="mt-2 p-2 bg-success bg-opacity-25 rounded-3">
                                <i class="fas fa-coins text-warning"></i> You earned <strong>${data.coins}</strong> coins!
                            </div>
                        `;
                        viewPropertyLink.href = `property_detail.php?id=${p.id}&source=auction`;
                        propertyModal.show();
                        spinMessage.innerHTML = data.message || '🏠 Check out this property!';
                        // Re-enable spin after modal closes
                        propertyModal._element.addEventListener('hidden.bs.modal', function () {
                            spinBtn.disabled = false;
                        });
                    } else {
                        spinMessage.innerHTML = data.message || 'Spin done!';
                        spinBtn.disabled = false;
                    }
                } else {
                    spinMessage.innerHTML = `❌ ${data.message || 'Something went wrong'}`;
                    spinBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Spin error:', error);
                wheel.classList.remove('pulse');
                spinMessage.innerHTML = '❌ Error spinning. Please try again.';
                spinBtn.disabled = false;
                isSpinning = false;
            });
    });

    function showCoinAnimation(coins) {
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

    // 🌟 ===== WORLD CLASS STAR SHOWER EFFECT =====
    function launchStarShower() {
        const container = document.createElement('div');
        container.className = 'star-shower-container';
        document.body.appendChild(container);

        const count = 130;
        const colors = ['#fbbf24', '#f59e0b', '#fcd34d', '#fde68a', '#fef3c7', '#ffffff', '#ffd700', '#ffb700', '#ffaa00', '#ffcc66'];

        for (let i = 0; i < count; i++) {
            const star = document.createElement('div');
            star.className = 'star-shower';
            const size = 8 + Math.random() * 22;
            const left = Math.random() * 100;
            const duration = 1.5 + Math.random() * 2.8;
            const delay = Math.random() * 1.6;
            const rotation = Math.random() * 360;
            const color = colors[Math.floor(Math.random() * colors.length)];
            const starChar = Math.random() > 0.4 ? '★' : '✦';
            
            star.style.cssText = `
                position: absolute;
                top: -20px;
                left: ${left}%;
                font-size: ${size}px;
                color: ${color};
                opacity: 0;
                transform: rotate(${rotation}deg);
                animation: starFall ${duration}s ease-in ${delay}s forwards;
                text-shadow: 0 0 10px ${color}, 0 0 20px ${color};
                pointer-events: none;
            `;
            star.textContent = starChar;
            container.appendChild(star);
        }

        const maxDuration = 3.0 + 1.6;
        setTimeout(() => {
            if (container.parentNode) {
                container.parentNode.removeChild(container);
            }
        }, maxDuration * 1000 + 500);
    }

    // Add star shower CSS
    const style = document.createElement('style');
    style.textContent = `
        .star-shower-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
            overflow: hidden;
        }
        @keyframes starFall {
            0% {
                opacity: 0;
                transform: translateY(-20px) rotate(0deg) scale(0.5);
            }
            10% {
                opacity: 1;
                transform: translateY(10vh) rotate(36deg) scale(1);
            }
            90% {
                opacity: 1;
                transform: translateY(90vh) rotate(360deg) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(100vh) rotate(400deg) scale(0.3);
            }
        }
        .star-shower {
            filter: drop-shadow(0 0 8px rgba(251, 191, 36, 0.8));
        }
        @keyframes slideIn {
            from { transform: translateX(100px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php include 'footer.php'; ?>
