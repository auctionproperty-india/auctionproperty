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

// ---- Get all three slot statuses ----
$slot_statuses = [];
for ($slot = 1; $slot <= 3; $slot++) {
    $slot_statuses[$slot] = getSlotStatus($pdo, $user_id, $slot);
}
$current_slot = getCurrentSlot();
$current_slot_data = getUserSpinData($pdo, $user_id, $current_slot);

// ---- Render Dashboard Card (unchanged) ----
function renderDashboardCard($prop, $show_images, $is_today = false) {
    // ... same as before ...
    // I'll skip for brevity, keep your existing function.
}
?>
<style>
    /* same styles as before, plus new for slots */
    .slot-card {
        background: rgba(255,255,255,0.05);
        border-radius: 16px;
        padding: 15px;
        border: 1px solid rgba(255,255,255,0.1);
        backdrop-filter: blur(4px);
        margin-bottom: 12px;
    }
    .slot-card .slot-time {
        font-weight: 600;
        font-size: 1.1rem;
    }
    .slot-card .slot-status {
        font-size: 0.9rem;
        margin-top: 4px;
    }
    .slot-card .slot-status .badge {
        font-weight: 600;
    }
    .slot-card.missed { background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); }
    .slot-card.claimed { background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); }
    .slot-card.current { background: rgba(37, 99, 235, 0.15); border-color: rgba(37, 99, 235, 0.3); }
    .slot-card.upcoming { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.05); }
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
    .confetti-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 9998;
        overflow: hidden;
    }
    .confetti {
        position: absolute;
        width: 10px;
        height: 10px;
        background: #fbbf24;
        animation: confettiFall 2s linear;
    }
    @keyframes confettiFall {
        0% { transform: translateY(-20px) rotate(0deg); opacity: 1; }
        100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
    }
</style>

<!-- ===== WELCOME BANNER (unchanged) ===== -->
<div class="user-welcome-banner">
    <!-- keep your existing welcome banner code -->
</div>

<!-- ===== DAILY SPIN SYSTEM ===== -->
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: linear-gradient(135deg, #1e293b, #334155); color: #fff;">
    <h4><i class="fas fa-gift me-2" style="color: #fbbf24;"></i>Daily Spin</h4>
    
    <!-- Slot Status Cards (all three) -->
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

    <!-- Current Slot Spinner -->
    <?php if ($current_slot_data['can_spin']): ?>
    <div class="row align-items-center mt-3">
        <div class="col-md-6">
            <p class="mb-1">Current Slot: <strong><?= getSlotTimeRange($current_slot) ?></strong></p>
            <p class="mb-1">Spins Used: <span id="spinCount"><?= $current_slot_data['spins_used'] ?></span>/5</p>
            <p class="mb-1">Coins Earned this slot: <span id="slotCoins"><?= $current_slot_data['coins_earned'] ?></span>/20</p>
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
            You have completed this slot! Total coins earned: <?= $current_slot_data['coins_earned'] ?>/20
        <?php else: ?>
            No spins available for this slot.
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ===== TODAY'S AUCTIONS & BEST DEALS (unchanged) ===== -->
<!-- ... keep your existing code ... -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const spinBtn = document.getElementById('spinBtn');
    const wheel = document.getElementById('spinWheel');
    const spinCount = document.getElementById('spinCount');
    const spinMessage = document.getElementById('spinMessage');
    const slotCoins = document.getElementById('slotCoins');

    if (!spinBtn) return;

    const segments = [0, 72, 144, 216, 288];
    let currentRotation = 0;

    spinBtn.addEventListener('click', function() {
        this.disabled = true;
        spinMessage.innerHTML = '🔄 Spinning...';
        
        const randomSegment = segments[Math.floor(Math.random() * segments.length)];
        const extraSpin = Math.floor(Math.random() * 360);
        const totalRotation = 360 * 5 + randomSegment + extraSpin;
        currentRotation += totalRotation;
        
        wheel.style.transform = `rotate(${currentRotation}deg)`;
        wheel.classList.add('pulse');
        
        fetch('spin_ajax.php')
            .then(response => response.json())
            .then(data => {
                wheel.classList.remove('pulse');
                if (data.success) {
                    spinCount.textContent = data.spins_used;
                    slotCoins.textContent = data.total_coins_earned;
                    if (data.coins > 0) {
                        spinMessage.innerHTML = `🎉 +${data.coins} coins!`;
                        showCoinAnimation(data.coins);
                    } else {
                        spinMessage.innerHTML = `⚠️ You've reached the max 20 coins for this slot!`;
                    }
                    if (data.is_reward) {
                        // Confetti effect on 5th spin
                        launchConfetti();
                        spinMessage.innerHTML = `🎊 Congratulations! You completed the slot! Total coins: ${data.total_coins_earned}`;
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

    function launchConfetti() {
        const container = document.createElement('div');
        container.className = 'confetti-container';
        document.body.appendChild(container);
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.top = Math.random() * 100 + '%';
            confetti.style.background = ['#fbbf24', '#ef4444', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'][Math.floor(Math.random()*6)];
            confetti.style.width = (Math.random() * 10 + 5) + 'px';
            confetti.style.height = (Math.random() * 10 + 5) + 'px';
            confetti.style.animationDuration = (Math.random() * 2 + 1) + 's';
            container.appendChild(confetti);
        }
        setTimeout(() => container.remove(), 3000);
    }
});
</script>

<?php include 'footer.php'; ?>
