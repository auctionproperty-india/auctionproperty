<?php
// ============================================================
// 👤 User Dashboard – No Registration/Activation Dates
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
include 'header.php';

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

// ---- Today's Auctions (for dashboard) ----
$today_sql = "SELECT * FROM properties WHERE status = 'available' AND auction_date = CURRENT_DATE ORDER BY id DESC LIMIT 6";
$today_stmt = $pdo->prepare($today_sql);
$today_stmt->execute();
$today_props = $today_stmt->fetchAll();

// ---- Best Deals ----
$user_stmt = $pdo->prepare("SELECT city FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_city = $user_stmt->fetchColumn() ?? '';

$sql = "SELECT * FROM properties WHERE status = 'available'";
$params = [];
if (!empty($user_city)) {
    $sql .= " AND city ILIKE ?";
    $params[] = '%' . $user_city . '%';
}
$sql .= " ORDER BY price ASC LIMIT 6";
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
    $show_images = $show_images ?? false;
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
    .section-title { font-weight: 800; color: #0f172a; margin-bottom: 20px; }
    .section-title i { margin-right: 10px; }
    .no-auction-msg { background: #f8fafc; border-radius: 30px; padding: 30px; text-align: center; border: 2px dashed #e2e8f0; }
    .no-auction-msg i { font-size: 2.5rem; opacity:0.3; }
    @media (max-width:576px) {
        .dashboard-stats { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .welcome-banner { padding: 20px; }
        .stat-card .stat-number { font-size: 1.3rem; }
    }
</style>

<div class="container-fluid">
    <!-- Welcome Banner – No Reg/Act Dates -->
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

    <!-- Stats Cards -->
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

    <!-- Today's Auctions -->
    <?php if (count($today_props) > 0): ?>
        <div class="section-title">
            <i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions
            <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span>
        </div>
        <div class="row">
            <?php foreach ($today_props as $prop): ?>
                <?php renderDashboardCard($prop, false, true); ?>
            <?php endforeach; ?>
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

<?php include 'footer.php'; ?>
