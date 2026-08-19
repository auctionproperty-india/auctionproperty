<?php
// ============================================================
// 👤 User Dashboard – Complete with Spin, Stats, Cards
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- Get user stats ----
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM user_properties WHERE user_id = ? AND status = 'approved') as total_properties,
        (SELECT COUNT(*) FROM user_properties WHERE user_id = ? AND status = 'pending') as pending_properties,
        (SELECT COUNT(*) FROM user_referral_earnings WHERE user_id = ?) as total_referrals,
        (SELECT COALESCE(SUM(net_amount), 0) FROM user_referral_earnings WHERE user_id = ? AND status = 'paid') as total_earnings,
        (SELECT COALESCE(SUM(net_amount), 0) FROM user_referral_earnings WHERE user_id = ? AND status = 'pending') as pending_earnings,
        coins,
        wallet_balance
    FROM users 
    WHERE id = ?
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$stats = $stmt->fetch();

// ---- Get user city for best deals ----
$user_stmt = $pdo->prepare("SELECT city FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_city = $user_stmt->fetchColumn() ?? '';

// ---- Get active subscription ----
$sub_stmt = $pdo->prepare("
    SELECT p.name as package_name, s.start_date, s.end_date
    FROM subscriptions s
    LEFT JOIN packages p ON s.package_id = p.id
    WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE
    ORDER BY s.id DESC LIMIT 1
");
$sub_stmt->execute([$user_id]);
$subscription = $sub_stmt->fetch();

// ---- Get slot statuses for spin ----
$slot_statuses = [];
for ($slot = 1; $slot <= 3; $slot++) {
    $slot_statuses[$slot] = getSlotStatus($pdo, $user_id, $slot);
}
$current_slot = getCurrentSlot();
$current_slot_data = getUserSpinData($pdo, $user_id, $current_slot);

// ============================================================
// 🔥 FIX: Today's Auctions = User City + Private Treaty (Any City)
// ============================================================

// Query 1: Properties in user's city with today's date (if city exists)
if (empty($user_city)) {
    // No city set → show all today's auctions (any city)
    $city_sql = "SELECT * FROM properties WHERE status = 'available' AND auction_date = CURRENT_DATE ORDER BY id DESC";
    $city_stmt = $pdo->prepare($city_sql);
    $city_stmt->execute();
    $city_props = $city_stmt->fetchAll();
} else {
    // City set → show only user city today's auctions
    $city_sql = "SELECT * FROM properties WHERE status = 'available' AND city ILIKE ? AND auction_date = CURRENT_DATE ORDER BY id DESC";
    $city_stmt = $pdo->prepare($city_sql);
    $city_stmt->execute(['%' . $user_city . '%']);
    $city_props = $city_stmt->fetchAll();
}

// Query 2: Private Treaty properties (any city)
$pt_sql = "SELECT * FROM properties WHERE status = 'available' AND auction_start_time = 'Private Treaty' ORDER BY id DESC";
$pt_stmt = $pdo->prepare($pt_sql);
$pt_stmt->execute();
$pt_props = $pt_stmt->fetchAll();

// Merge both arrays
$today_props = array_merge($city_props, $pt_props);

// ---- Best Deals (based on city) ----
$best_sql = "SELECT * FROM properties WHERE status = 'available'";
$best_params = [];
if (!empty($user_city)) {
    $best_sql .= " AND city ILIKE ?";
    $best_params[] = '%' . $user_city . '%';
}
$best_sql .= " ORDER BY price ASC LIMIT 6";
$best_stmt = $pdo->prepare($best_sql);
$best_stmt->execute($best_params);
$best_props = $best_stmt->fetchAll();

// ---- Function to render property card (used in dashboard) ----
function renderDashboardCard($prop, $show_images = false, $is_today = false) {
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
    $image_url = $prop['image_url'] ?? '';
    
    // Check if Private Treaty
    $is_private_treaty = isset($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty';
    ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100" style="border-radius:24px; overflow:hidden; border:none; box-shadow:<?= $shadow ?>; transition:all 0.4s; background: <?= $g['bg'] ?>; color:<?= $text_color ?>; position:relative;">
            <?php if($is_private_treaty): ?>
                <div style="position:absolute; top:15px; right:15px; z-index:10; background:#f59e0b; color:#000; padding:4px 14px; border-radius:30px; font-size:0.7rem; font-weight:700;">
                    🔑 Private Treaty
                </div>
            <?php endif; ?>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                    <?php if(!empty($prop['auction_start_time']) && $prop['auction_start_time'] != 'Private Treaty'): ?>
                        <span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mt-2" style="color:<?= $text_color ?>;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:<?= $text_color ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                <div style="font-size:0.85rem; opacity:0.8; color:<?= $text_color ?>;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                <a href="property_detail.php?id=<?= $prop['id'] ?>&source=auction" style="display:block; margin-top:16px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
            </div>
            <?php if($show_images && !empty($image_url)): ?>
                <img src="<?= htmlspecialchars($image_url) ?>" style="height:200px; width:100%; object-fit:cover; border-top:3px solid <?= $border ?>;" alt="<?= htmlspecialchars($prop['title']) ?>">
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

include 'header.php';
?>

<style>
    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 18px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 18px 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        text-align: center;
        transition: all 0.25s ease;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    }
    .stat-card .stat-number {
        font-size: 1.8rem;
        font-weight: 800;
        color: #0f172a;
    }
    .stat-card .stat-label {
        font-size: 0.75rem;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .stat-card .stat-icon {
        font-size: 1.8rem;
        opacity: 0.6;
        margin-bottom: 4px;
    }
    .welcome-banner {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
        border-radius: 20px;
        padding: 30px;
        color: white;
        margin-bottom: 25px;
        box-shadow: 0 10px 25px -5px rgba(37,99,235,0.3);
    }
    .welcome-banner h2 { font-weight: 700; }
    .welcome-banner p { opacity: 0.8; }

    /* Spin System Styles */
    .spin-card {
        background: linear-gradient(135deg, #1e293b, #334155);
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 30px;
        color: #fff;
    }
    .spin-card h4 { color: #fbbf24; }
    .slot-card {
        background: rgba(255,255,255,0.05);
        border-radius: 16px;
        padding: 15px;
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(4px);
        margin-bottom: 12px;
    }
    .slot-card .slot-time { font-weight: 600; font-size: 1.1rem; }
    .slot-card .slot-status { font-size: 0.9rem; margin-top: 4px; }
    .slot-card .slot-status .badge { font-weight: 600; }
    .slot-card.missed { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); }
    .slot-card.claimed { background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); }
    .slot-card.current { background: rgba(37, 99, 235, 0.15); border-color: rgba(37, 99, 235, 0.3); }
    .slot-card.upcoming { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.05); }
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
    .star-shower {
        position: absolute;
        top: -20px;
        pointer-events: none;
        animation: starFall var(--duration) ease-in var(--delay) forwards;
        text-shadow: 0 0 10px var(--color), 0 0 20px var(--color);
    }
    @keyframes starFall {
        0% { opacity: 0; transform: translateY(-20px) rotate(0deg) scale(0.5); }
        10% { opacity: 1; transform: translateY(10vh) rotate(36deg) scale(1); }
        90% { opacity: 1; transform: translateY(90vh) rotate(360deg) scale(1); }
        100% { opacity: 0; transform: translateY(100vh) rotate(400deg) scale(0.3); }
    }
    .section-title { font-weight: 800; color: #0f172a; margin-bottom: 20px; }
    .section-title i { margin-right: 10px; }
    .no-auction-msg { background: #f8fafc; border-radius: 30px; padding: 30px; text-align: center; border: 2px dashed #e2e8f0; }
    .no-auction-msg i { font-size: 2.5rem; opacity:0.3; }
</style>

<div class="container-fluid">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>🏡 Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
                <p>Discover the most affordable properties in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'your city' ?></p>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <a href="index.php" class="btn btn-light btn-sm"><i class="fas fa-th-list"></i> View All Properties</a>
                    <a href="user_properties.php" class="btn btn-light btn-sm"><i class="fas fa-list"></i> My Properties</a>
                    <a href="user_packages.php" class="btn btn-warning btn-sm"><i class="fas fa-rocket"></i> Subscribe Now</a>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if ($subscription): ?>
                    <span class="badge bg-success fs-6">✅ Active Plan: <?= htmlspecialchars($subscription['package_name']) ?></span>
                    <br>
                    <span class="badge bg-info fs-6 mt-1">⏳ <?= date('d M Y', strtotime($subscription['end_date'])) ?></span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark fs-6">❌ No Active Subscription</span>
                    <br>
                    <a href="user_packages.php" class="btn btn-light btn-sm mt-2">Buy Plan</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Cards (Includes Coin Balance) -->
    <div class="dashboard-stats">
        <div class="stat-card">
            <div class="stat-icon">🏠</div>
            <div class="stat-number"><?= number_format($stats['total_properties'] ?? 0) ?></div>
            <div class="stat-label">My Properties</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-number"><?= number_format($stats['pending_properties'] ?? 0) ?></div>
            <div class="stat-label">Pending Properties</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-number"><?= number_format($stats['total_referrals'] ?? 0) ?></div>
            <div class="stat-label">Referrals</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-number">₹ <?= number_format($stats['total_earnings'] ?? 0, 2) ?></div>
            <div class="stat-label">Total Earnings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-number">₹ <?= number_format($stats['pending_earnings'] ?? 0, 2) ?></div>
            <div class="stat-label">Pending Earnings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🪙</div>
            <div class="stat-number"><?= number_format($stats['coins'] ?? 0) ?></div>
            <div class="stat-label">My Coins</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💳</div>
            <div class="stat-number">₹ <?= number_format($stats['wallet_balance'] ?? 0, 2) ?></div>
            <div class="stat-label">Wallet Balance</div>
        </div>
    </div>

    <!-- ====== DAILY SPIN SYSTEM ====== -->
    <div class="spin-card">
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

    <!-- ============================================================
    🔥 TODAY'S AUCTIONS – User City + Private Treaty (All, No Limit)
    ============================================================ -->
    <?php 
    // Count Private Treaty properties in today's auctions
    $pt_count = 0;
    foreach($today_props as $p) {
        if(isset($p['auction_start_time']) && $p['auction_start_time'] == 'Private Treaty') $pt_count++;
    }
    ?>
    <div class="section-title">
        <i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions
        <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span>
        <?php if($pt_count > 0): ?>
            <span class="badge bg-warning text-dark rounded-pill ms-2">🔑 <?= $pt_count ?> Private Treaty</span>
        <?php endif; ?>
    </div>
    <?php if (count($today_props) > 0): ?>
        <div class="row">
            <?php foreach ($today_props as $prop): ?>
                <?php renderDashboardCard($prop, false, true); ?>
            <?php endforeach; ?>
        </div>
        <hr class="my-4">
    <?php else: ?>
        <div class="no-auction-msg">
            <i class="fas fa-calendar-day"></i>
            <p class="mt-2 fw-bold">📭 No auction today</p>
        </div>
        <hr class="my-4">
    <?php endif; ?>

    <!-- Best Deals -->
    <div class="section-title">
        <i class="fas fa-fire" style="color:#f97316;"></i> Best Deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'Your City' ?>
        <span class="badge bg-primary rounded-pill ms-2"><?= count($best_props) ?></span>
    </div>
    <div class="row">
        <?php if (count($best_props) > 0): ?>
            <?php foreach ($best_props as $prop): ?>
                <?php renderDashboardCard($prop, false, false); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-4">
                <i class="fas fa-search" style="font-size:2rem; opacity:0.3;"></i>
                <p class="mt-2">No properties available in your city. Explore all properties.</p>
                <a href="index.php" class="btn btn-outline-primary btn-sm">View All Properties</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Property Modal for Spin -->
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

<!-- ====== SPIN JAVASCRIPT ====== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const spinBtn = document.getElementById('spinBtn');
    const wheel = document.getElementById('spinWheel');
    const spinCount = document.getElementById('spinCount');
    const spinMessage = document.getElementById('spinMessage');
    const slotCoins = document.getElementById('slotCoins');

    if (!spinBtn) {
        console.log('Spin button not found');
        return;
    }

    console.log('Spin system initialized');

    let currentRotation = 0;
    let isSpinning = false;

    spinBtn.addEventListener('click', function() {
        if (isSpinning) return;
        isSpinning = true;
        this.disabled = true;
        spinMessage.innerHTML = '🔄 Spinning...';

        const randomSegment = Math.floor(Math.random() * 5) * 72;
        const extraSpin = Math.floor(Math.random() * 360);
        const totalRotation = 360 * 5 + randomSegment + extraSpin;
        currentRotation += totalRotation;

        wheel.style.transition = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
        wheel.style.transform = `rotate(${currentRotation}deg)`;
        wheel.classList.add('pulse');

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
                        launchStarShower();

                        // Update coin display in stats card
                        const coinSpan = document.querySelector('.stat-card .stat-number');
                        if (coinSpan) {
                            let current = parseInt(coinSpan.textContent.replace(/,/g, ''));
                            if (!isNaN(current)) {
                                coinSpan.textContent = (current + data.coins).toLocaleString();
                            }
                        }
                        if (data.spins_used >= 5) {
                            spinBtn.disabled = true;
                            spinBtn.innerHTML = '<i class="fas fa-check"></i> Done';
                        } else {
                            spinBtn.disabled = false;
                        }
                    } else if (data.show_property && data.property) {
                        const p = data.property;
                        const isCar = (p.type && (p.type.toLowerCase().includes('car') || p.type.toLowerCase().includes('vehicle')));
                        const icon = isCar ? '🚗' : '🏠';
                        const imageHtml = p.image_url ? `<img src="${p.image_url}" style="width:100%; max-height:200px; object-fit:cover; border-radius:12px; margin-bottom:12px;" alt="${p.title}">` : `<div style="height:150px; background:#1e293b; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8;"><i class="fas fa-image fa-2x"></i></div>`;
                        const modalContent = document.getElementById('propertyModalContent');
                        if (modalContent) {
                            modalContent.innerHTML = `
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
                            const viewLink = document.getElementById('viewPropertyLink');
                            if (viewLink) {
                                viewLink.href = `property_detail.php?id=${p.id}&source=auction`;
                            }
                            const propertyModal = new bootstrap.Modal(document.getElementById('propertyModal'));
                            propertyModal.show();
                            spinMessage.innerHTML = data.message || '🏠 Check out this property!';
                            propertyModal._element.addEventListener('hidden.bs.modal', function () {
                                spinBtn.disabled = false;
                            });
                        } else {
                            spinMessage.innerHTML = data.message || 'Spin done!';
                            spinBtn.disabled = false;
                        }
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
                --duration: ${duration}s;
                --delay: ${delay}s;
                --color: ${color};
                font-size: ${size}px;
                color: ${color};
                left: ${left}%;
                transform: rotate(${rotation}deg);
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
});
</script>

<?php include 'footer.php'; ?>
