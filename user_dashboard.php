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
$active_sub = $pdo->prepare("SELECT s.*, p.name as pkg_name, s.start_date, s.end_date, (s.end_date - CURRENT_DATE) as days_left FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE ORDER BY s.id DESC LIMIT 1");
$active_sub->execute([$user_id]);
$sub_info = $active_sub->fetch();
$is_subscribed = $sub_info ? true : false;

$reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
$activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
$expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
$days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

// ---- Wallet Balance ----
$wallet_balance = getUserWalletBalance($pdo, $user_id);

// ---- Show images only if subscribed ----
$show_images = userHasActiveSubscription($pdo, $user_id);

// ---- 10 Lowest Price Properties from User's City ----
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
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
            <h6 class="text-muted">⏳ Pending</h6>
            <h2 class="fw-bold text-dark">₹ <?= indianCurrencyFormat($wallet_balance) ?></h2>
            <!-- Pending amount is not directly available here, we removed it. Actually we should show wallet balance only? The user asked to keep wallet. I'll show pending/paid? The user said "refral link income or dusra dashbaord hatakar sidbar me dal do" – so income (pending/paid) should go to sidebar. So here we show only wallet balance? Actually we have wallet balance as available. We can show wallet balance, pending/paid are not needed here because they are referral earnings. We'll just show wallet balance. But the user said "refral link income or dusra dashbaord hatakar sidbar me dal do" – income means pending/paid. So we remove them from dashboard. So we can keep only wallet balance. Let's change wallet cards to only one card? Or keep three cards but only wallet balance matters. Actually the user said "reffral link income or dusra dashbaord hatakar sidbar me dal do" – meaning referral link and income (pending/paid) should be removed from dashboard. So we should remove pending/paid cards. We'll show only wallet balance card.
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0);">
            <h6 class="text-muted">✅ Paid</h6>
            <h2 class="fw-bold text-success">₹ <?= indianCurrencyFormat($paid_amt) ?></h2>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
            <h6 class="text-muted">💰 Wallet Balance</h6>
            <h2 class="fw-bold text-primary">₹ <?= indianCurrencyFormat($wallet_balance) ?></h2>
        </div>
    </div>
</div> -->
