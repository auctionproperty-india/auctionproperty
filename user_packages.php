<?php
// ============================================================
// 📦 User Packages – 2 per Row, Color Scheme by Package Name
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
include 'header.php';

// ---- Show messages ----
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    if ($msg == 'request_sent') {
        echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> ✅ Your subscription request has been sent. Admin will review it shortly.</div>";
    } elseif ($msg == 'already_pending') {
        echo "<div class='alert alert-warning'><i class='fas fa-clock'></i> ⚠️ You already have a pending request.</div>";
    } elseif ($msg == 'already_active') {
        echo "<div class='alert alert-info'><i class='fas fa-check-circle'></i> ℹ️ You already have an active subscription.</div>";
    }
}

// ---- Active subscription ----
$active_sub = $pdo->prepare("
    SELECT s.*, p.name as pkg_name, s.start_date, s.end_date, (s.end_date - CURRENT_DATE) as days_left 
    FROM subscriptions s 
    JOIN packages p ON s.package_id = p.id 
    WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE 
    ORDER BY s.id DESC LIMIT 1
");
$active_sub->execute([$user_id]);
$sub_info = $active_sub->fetch();
$is_subscribed = $sub_info ? true : false;
$days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

// ---- Pending check ----
$pending_check = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'pending'");
$pending_check->execute([$user_id]);
$has_pending = $pending_check->rowCount() > 0;

$packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();

// Package color schemes
$color_schemes = [
    'Silver' => [
        'border' => '#c0c0c0',
        'bg' => '#f9f9f9',
        'accent' => '#a8a8a8',
        'gradient' => 'linear-gradient(135deg, #f0f0f0, #e0e0e0)',
        'badge' => '#6b7280'
    ],
    'Gold' => [
        'border' => '#fbbf24',
        'bg' => '#fffbeb',
        'accent' => '#f59e0b',
        'gradient' => 'linear-gradient(135deg, #fef3c7, #fde68a)',
        'badge' => '#d97706'
    ],
    'Platinum' => [
        'border' => '#94a3b8',
        'bg' => '#f1f5f9',
        'accent' => '#64748b',
        'gradient' => 'linear-gradient(135deg, #e2e8f0, #cbd5e1)',
        'badge' => '#475569'
    ],
    'Diamond' => [
        'border' => '#3b82f6',
        'bg' => '#eff6ff',
        'accent' => '#2563eb',
        'gradient' => 'linear-gradient(135deg, #dbeafe, #93c5fd)',
        'badge' => '#1d4ed8'
    ]
];
?>

<style>
    .pricing-card {
        border-radius: 20px;
        padding: 25px 20px 30px;
        background: #ffffff;
        transition: all 0.3s ease;
        border: 2px solid #e9edf4;
        height: 100%;
        position: relative;
        box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        display: flex;
        flex-direction: column;
    }
    .pricing-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    }
    .pricing-card .badge-recommended {
        position: absolute;
        top: -12px;
        right: 20px;
        color: white;
        padding: 4px 16px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }
    .pricing-card .package-name {
        font-size: 1.6rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .pricing-card .package-duration {
        font-size: 0.85rem;
        color: #64748b;
        margin-bottom: 12px;
    }
    .pricing-card .price-box {
        margin: 12px 0 16px;
    }
    .pricing-card .price-box .regular-price {
        font-size: 1.1rem;
        color: #94a3b8;
        text-decoration: line-through;
        font-weight: 500;
    }
    .pricing-card .price-box .offer-price {
        font-size: 2.2rem;
        font-weight: 800;
        color: #0f172a;
        margin-left: 8px;
    }
    .pricing-card .price-box .offer-badge {
        padding: 2px 10px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-left: 8px;
        display: inline-block;
        background: #dcfce7;
        color: #166534;
    }
    .pricing-card .features-list {
        list-style: none;
        padding: 0;
        margin: 16px 0 20px;
        text-align: left;
        font-size: 0.9rem;
        flex: 1;
    }
    .pricing-card .features-list li {
        padding: 4px 0;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pricing-card .features-list li:last-child {
        border-bottom: none;
    }
    .pricing-card .features-list .feature-icon {
        width: 24px;
        color: #3b82f6;
        font-size: 0.9rem;
        text-align: center;
    }
    .pricing-card .features-list .feature-label {
        font-weight: 600;
        color: #1e293b;
        min-width: 110px;
    }
    .pricing-card .features-list .feature-value {
        color: #475569;
    }
    .pricing-card .btn-buy {
        background: #0f172a;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 40px;
        font-weight: 600;
        width: 100%;
        transition: 0.3s;
        font-size: 0.95rem;
        text-align: center;
        display: block;
        text-decoration: none;
        margin-top: auto;
    }
    .pricing-card .btn-buy:hover {
        transform: scale(1.02);
        color: white;
    }
    .pricing-card .btn-buy:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
    }
    .pricing-card .badge-status {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-top: 8px;
    }
    .badge-status.active { background: #dcfce7; color: #166534; }
    .badge-status.pending { background: #fef3c7; color: #92400e; }
    .section-title {
        font-size: 2rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }
    .section-subtitle {
        color: #64748b;
        font-size: 1.05rem;
        margin-bottom: 30px;
    }
    /* Package specific overrides */
    .pkg-silver .pricing-card { border-color: #c0c0c0; background: #fafafa; }
    .pkg-silver .pricing-card .package-name { color: #4b5563; }
    .pkg-silver .badge-recommended { background: #6b7280; }

    .pkg-gold .pricing-card { border-color: #fbbf24; background: #fffbeb; }
    .pkg-gold .pricing-card .package-name { color: #92400e; }
    .pkg-gold .badge-recommended { background: #d97706; }

    .pkg-platinum .pricing-card { border-color: #94a3b8; background: #f1f5f9; }
    .pkg-platinum .pricing-card .package-name { color: #334155; }
    .pkg-platinum .badge-recommended { background: #475569; }

    .pkg-diamond .pricing-card { border-color: #3b82f6; background: #eff6ff; }
    .pkg-diamond .pricing-card .package-name { color: #1e3a8a; }
    .pkg-diamond .badge-recommended { background: #2563eb; }
</style>

<div class="container-fluid py-4">
    <div class="text-center mb-4">
        <h2 class="section-title">🚀 Choose Your Plan</h2>
        <p class="section-subtitle">Select the best package to unlock all auction properties</p>
    </div>

    <?php if ($has_pending): ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-clock"></i> You have a pending request. Please wait for admin approval.
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <?php 
        $count = count($packages);
        foreach ($packages as $index => $pkg):
            $is_active = ($is_subscribed && $sub_info['package_id'] == $pkg['id']);
            $discount_price = $pkg['discount_price'] ?? null;
            $regular_price = $pkg['price'];
            $show_discount = $discount_price && $discount_price < $regular_price;
            $col_size = 'col-lg-6 col-md-6'; // 2 per row
            $is_recommended = ($index == 1 && $count > 2);

            // Package specific CSS class
            $pkg_class = 'pkg-' . strtolower($pkg['name']);
            // fallback
            if (!in_array($pkg['name'], ['Silver','Gold','Platinum','Diamond'])) {
                $pkg_class = 'pkg-silver';
            }

            // Fields with labels, icons, and keys
            $fields = [
                ['label' => 'Validity', 'icon' => 'fa-clock', 'key' => 'validity'],
                ['label' => 'Property Search', 'icon' => 'fa-search', 'key' => 'property_search'],
                ['label' => 'Company Support', 'icon' => 'fa-headset', 'key' => 'company_support'],
                ['label' => 'Sales Team Support', 'icon' => 'fa-users', 'key' => 'sales_team_support'],
                ['label' => 'Self Refer Incentive', 'icon' => 'fa-coins', 'key' => 'self_refer_incentive'],
                ['label' => 'Team Refer Incentive', 'icon' => 'fa-handshake', 'key' => 'team_refer_incentive'],
                ['label' => 'Property Sale Incentive', 'icon' => 'fa-percent', 'key' => 'property_sale_incentive'],
                ['label' => 'Team Sale Incentive', 'icon' => 'fa-percent', 'key' => 'team_sale_incentive'],
                ['label' => 'Free Property Visit', 'icon' => 'fa-building', 'key' => 'free_property_visit']
            ];
        ?>
            <div class="<?= $col_size ?> mb-4 <?= $pkg_class ?>">
                <div class="pricing-card <?= $is_recommended ? 'recommended' : '' ?> <?= $is_active ? 'border border-success' : '' ?>">
                    <?php if ($is_recommended): ?>
                        <span class="badge-recommended">⭐ Recommended</span>
                    <?php endif; ?>
                    
                    <div class="package-name"><?= htmlspecialchars($pkg['name']) ?></div>
                    <div class="package-duration"><?= $pkg['duration_months'] ?> Months Access</div>

                    <div class="price-box">
                        <?php if ($show_discount): ?>
                            <span class="regular-price">₹<?= number_format($regular_price, 0) ?></span>
                            <span class="offer-price">₹<?= number_format($discount_price, 0) ?></span>
                            <span class="offer-badge">🔥 Save <?= round((($regular_price - $discount_price)/$regular_price)*100) ?>%</span>
                        <?php else: ?>
                            <span class="offer-price" style="font-size:2rem;">₹<?= number_format($regular_price, 0) ?></span>
                        <?php endif; ?>
                    </div>

                    <ul class="features-list">
                        <?php foreach ($fields as $field): 
                            $value = trim($pkg[$field['key']] ?? '');
                            if ($value !== '' && $value !== null):
                        ?>
                            <li>
                                <span class="feature-icon"><i class="fas <?= $field['icon'] ?>"></i></span>
                                <span class="feature-label"><?= $field['label'] ?>:</span>
                                <span class="feature-value"><?= htmlspecialchars($value) ?></span>
                            </li>
                        <?php endif; endforeach; ?>
                    </ul>

                    <?php if ($is_active): ?>
                        <span class="badge-status active">✅ Active (<?= $days_left ?> days left)</span>
                        <button class="btn-buy" disabled>Currently Active</button>
                    <?php elseif ($has_pending): ?>
                        <span class="badge-status pending">⏳ Pending Approval</span>
                        <button class="btn-buy" disabled>Request Pending</button>
                    <?php else: ?>
                        <a href="buy_subscription.php?package_id=<?= $pkg['id'] ?>" class="btn-buy">
                            <i class="fas fa-arrow-right me-1"></i> Buy Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-center text-muted mt-4"><small>* After payment, admin will activate your subscription within 24 hours.</small></p>
</div>

<?php include 'footer.php'; ?>
