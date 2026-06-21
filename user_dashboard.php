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
$user_stmt = $pdo->prepare("SELECT *, created_at as reg_date FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// ---- Subscription Status ----
$active_sub = $pdo->prepare("SELECT s.*, p.name as pkg_name, s.start_date, s.end_date, (s.end_date - CURRENT_DATE) as days_left FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE ORDER BY s.id DESC LIMIT 1");
$active_sub->execute([$user_id]);
$sub_info = $active_sub->fetch();
$is_subscribed = $sub_info ? true : false;

$reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
$activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
$expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
$days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

// ---- Wallet Balance ----
$wallet = getUserWalletBalance($pdo, $user_id);

// ---- Referral Link ----
$referral_link = getReferralLink($user_id);
?>
<!-- Welcome Banner -->
<div class="user-welcome-banner">
    <div>
        <h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
        <p>Your referral earnings at a glance</p>
    </div>
    <div>
        <a href="user_packages.php" class="btn btn-light text-success fw-bold">Buy Subscription →</a>
    </div>
</div>

<!-- Wallet Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
            <h6 class="text-muted">⏳ Pending</h6>
            <h2 class="fw-bold text-dark">₹ <?= indianCurrencyFormat($wallet['pending']) ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0);">
            <h6 class="text-muted">✅ Paid</h6>
            <h2 class="fw-bold text-success">₹ <?= indianCurrencyFormat($wallet['paid']) ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
            <h6 class="text-muted">💰 Available Balance</h6>
            <h2 class="fw-bold text-primary">₹ <?= indianCurrencyFormat($wallet['available']) ?></h2>
        </div>
    </div>
</div>

<!-- Subscription Status (Compact) -->
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
        <span class="badge bg-warning text-dark">⏳ Pending: ₹ <?= indianCurrencyFormat($wallet['pending']) ?></span>
        <span class="badge bg-success ms-2">✅ Paid: ₹ <?= indianCurrencyFormat($wallet['paid']) ?></span>
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
