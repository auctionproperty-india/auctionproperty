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

// ---- Subscription Status ----
$active_sub = $pdo->prepare("SELECT s.id, s.user_id, s.package_id, s.property_id, s.amount, s.payment_method, s.utr, s.slip_path, s.status, s.start_date, s.end_date, s.created_at, p.name as pkg_name, (s.end_date - CURRENT_DATE) as days_left 
                            FROM subscriptions s 
                            JOIN packages p ON s.package_id = p.id 
                            WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE 
                            ORDER BY s.id DESC LIMIT 1");
$active_sub->execute([$user_id]);
$sub_info = $active_sub->fetch();
$is_subscribed = $sub_info ? true : false;

$reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
$activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
$expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
$days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

// ---- Referral & Earnings ----
$referral_link = getReferralLink($user_id);
$earnings = getReferralEarnings($pdo, $user_id, 'pending');
$paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
$total_pending = array_sum(array_column($earnings, 'amount'));
$total_paid = array_sum(array_column($paid_earnings, 'net_amount'));
$team_members = getReferredUsers($pdo, $user_id);

$user_subs = $pdo->prepare("SELECT s.*, p.name as pkg_name FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
$user_subs->execute([$user_id]);
$user_subs = $user_subs->fetchAll();

// ---- Wallet Balance ----
$wallet_balance = getUserWalletBalance($pdo, $user_id);

// ---- Show images only if subscribed ----
$show_images = userHasActiveSubscription($pdo, $user_id);

// ---- 10 Lowest Price Properties (Explicit Columns) ----
$sql = "SELECT id, title, description, price, location, city, state, type, google_location, image_url, 
               bank_name, sqft, possession_type, inspection_date, borrower_name, emd_amount, bid_increment, 
               emd_deadline, auction_start_time, auction_end_time, locality, reserve_price_per_sqft, 
               contact_number, status, created_at 
        FROM properties 
        WHERE status = 'available'";
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
<!-- Welcome Banner -->
<div class="user-welcome-banner">
    <div>
        <h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
        <p>Showing best deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'your area' ?></p>
    </div>
    <div>
        <a href="user_packages.php" class="btn btn-light text-success fw-bold">Buy Subscription →</a>
    </div>
</div>

<!-- Wallet Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
            <h6 class="text-muted">💰 Wallet Balance</h6>
            <h2 class="fw-bold text-primary">₹ <?= indianCurrencyFormat($wallet_balance) ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
            <h6 class="text-muted">⏳ Pending</h6>
            <h2 class="fw-bold text-dark">₹ <?= indianCurrencyFormat($total_pending) ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0);">
            <h6 class="text-muted">✅ Paid</h6>
            <h2 class="fw-bold text-success">₹ <?= indianCurrencyFormat($total_paid) ?></h2>
        </div>
    </div>
</div>

<!-- Subscription Status -->
<div class="card-premium mb-4" style="border-left: 5px solid <?= $is_subscribed ? '#10b981' : '#f59e0b' ?>;">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h6><i class="fas fa-user-clock me-2"></i>Subscription Status</h6>
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="fw-bold">📅 Registered:</td><td><?= $reg_date_formatted ?></td></tr>
                <?php if($is_subscribed): ?>
                    <tr><td class="fw-bold">🚀 Activated:</td><td><?= $activation_date_formatted ?></td></tr>
                    <tr><td class="fw-bold">⏳ Expires:</td><td><?= $expiry_date_formatted ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if($is_subscribed): ?>
                <span class="badge bg-success p-2 fs-6">✅ <?= htmlspecialchars($sub_info['pkg_name']) ?> Active</span>
                <div class="mt-2"><span class="badge bg-warning text-dark p-2 fs-5">⏳ <?= $days_left ?> Days Left</span></div>
            <?php else: ?>
                <span class="badge bg-secondary p-2 fs-6">🔴 No Active Plan</span>
                <div class="mt-2"><a href="user_packages.php" class="btn btn-sm btn-primary">Buy Plan</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Referral Link -->
<div class="card-premium" style="border:1px solid #10b981; background:#f0fdf4;">
    <h5><i class="fas fa-link me-2" style="color:#10b981;"></i>Your Referral Link</h5>
    <div class="input-group">
        <input type="text" class="form-control border-success" id="refLink" value="<?= $referral_link ?>" readonly>
        <button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
    </div>
    <div class="mt-2">
        <span class="badge bg-warning text-dark">⏳ Pending: ₹ <?= indianCurrencyFormat($total_pending) ?></span>
        <span class="badge bg-success ms-2">✅ Paid: ₹ <?= indianCurrencyFormat($total_paid) ?></span>
    </div>
</div>

<!-- ===== BEST DEALS ===== -->
<div class="card-premium">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-fire me-2" style="color:#f97316;"></i>Best Deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'Your City' ?></h5>
        <a href="index.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-arrow-right me-1"></i> View All Properties
        </a>
    </div>
    <div class="row">
        <?php if(count($props) > 0): ?>
            <?php foreach($props as $p): ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm border-0" style="border-radius:16px; overflow:hidden;">
                    <?php if($show_images && !empty($p['image_url'])): ?>
                        <a href="<?= htmlspecialchars($p['image_url']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($p['image_url']) ?>" style="height:150px; width:100%; object-fit:cover; cursor:pointer;">
                        </a>
                    <?php else: ?>
                        <div style="height:150px; background: linear-gradient(145deg, #f8fafc, #e2e8f0); display: flex; align-items: center; justify-content: center; border-radius: 16px 16px 0 0; flex-direction: column;">
                            <i class="fas fa-home" style="font-size: 30px; color: #94a3b8;"></i>
                            <span class="badge bg-warning mt-1" style="font-size: 11px;">🔒 Subscribe</span>
                        </div>
                    <?php endif; ?>
                    <div class="p-3">
                        <span class="badge bg-light text-dark">🏦 <?= htmlspecialchars($p['bank_name'] ?? 'Bank') ?></span>
                        <h6 class="fw-bold mt-1"><?= htmlspecialchars($p['title']) ?></h6>
                        <div class="fw-bold text-success">₹ <?= indianCurrencyFormat($p['price']) ?></div>
                        <?php if(!empty($p['auction_start_time'])): ?>
                            <div class="text-muted small"><i class="far fa-calendar-alt me-1"></i> Auction: <?= htmlspecialchars($p['auction_start_time']) ?></div>
                        <?php endif; ?>
                        <a href="property_detail.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm w-100 mt-2">View Details</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No properties available in your city yet. <a href="index.php">Explore all properties</a></p>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyRef() { 
        let inp = document.getElementById('refLink'); 
        inp.select(); 
        navigator.clipboard.writeText(inp.value).then(() => alert('Referral Link Copied!')).catch(() => document.execCommand('copy')); 
    }
</script>

<?php include 'footer.php'; ?>
