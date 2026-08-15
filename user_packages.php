<?php
// ============================================================
// 📦 User Packages – 2 per Row, Package Name Color Scheme
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
        echo "<div class='alert alert-success text-center'><i class='fas fa-check-circle'></i> ✅ Your subscription request has been sent. Admin will review it shortly.</div>";
    } elseif ($msg == 'already_pending') {
        echo "<div class='alert alert-warning text-center'><i class='fas fa-clock'></i> ⚠️ You already have a pending request.</div>";
    } elseif ($msg == 'already_active') {
        echo "<div class='alert alert-info text-center'><i class='fas fa-check-circle'></i> ℹ️ You already have an active subscription.</div>";
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

// Package color schemes (darker, more vibrant)
$color_schemes = [
    'Silver' => [
        'bg' => 'linear-gradient(145deg, #f0f0f0, #d1d5db)',
        'border' => '#9ca3af',
        'title' => '#374151',
        'accent' => '#6b7280',
        'btn' => '#4b5563',
        'btn_hover' => '#374151',
        'badge' => '#9ca3af'
    ],
    'Gold' => [
        'bg' => 'linear-gradient(145deg, #fef3c7, #fcd34d)',
        'border' => '#f59e0b',
        'title' => '#78350f',
        'accent' => '#d97706',
        'btn' => '#b45309',
        'btn_hover' => '#92400e',
        'badge' => '#d97706'
    ],
    'Platinum' => [
        'bg' => 'linear-gradient(145deg, #e2e8f0, #94a3b8)',
        'border' => '#64748b',
        'title' => '#1e293b',
        'accent' => '#475569',
        'btn' => '#334155',
        'btn_hover' => '#1e293b',
        'badge' => '#475569'
    ],
    'Diamond' => [
        'bg' => 'linear-gradient(145deg, #dbeafe, #60a5fa)',
        'border' => '#2563eb',
        'title' => '#1e3a8a',
        'accent' => '#1d4ed8',
        'btn' => '#1d4ed8',
        'btn_hover' => '#1e40af',
        'badge' => '#2563eb'
    ]
];
?>

<style>
    .pricing-card {
        border-radius: 24px;
        padding: 30px 20px 30px;
        transition: all 0.4s ease;
        border: 2px solid #e9edf4;
        height: 100%;
        position: relative;
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        background: #ffffff;
    }
    .pricing-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    }
    .pricing-card .badge-recommended {
        position: absolute;
        top: -12px;
        right: 20px;
        color: white;
        padding: 5px 18px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        background: #3b82f6;
    }
    .pricing-card .package-name {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 2px;
        letter-spacing: -0.5px;
    }
    .pricing-card .package-duration {
        font-size: 0.9rem;
        font-weight: 500;
        color: #4b5563;
        margin-bottom: 12px;
    }
    .pricing-card .price-box {
        margin: 12px 0 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .pricing-card .price-box .regular-price {
        font-size: 1.1rem;
        color: #9ca3af;
        text-decoration: line-through;
        font-weight: 500;
    }
    .pricing-card .price-box .offer-price {
        font-size: 2.4rem;
        font-weight: 800;
        color: #0f172a;
    }
    .pricing-card .price-box .offer-badge {
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 700;
        background: #dcfce7;
        color: #166534;
        display: inline-block;
    }
    .pricing-card .features-list {
        list-style: none;
        padding: 0;
        margin: 16px 0 20px;
        width: 100%;
        font-size: 0.9rem;
        flex: 1;
        text-align: left;
    }
    .pricing-card .features-list li {
        padding: 5px 0;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .pricing-card .features-list li:last-child {
        border-bottom: none;
    }
    .pricing-card .features-list .feature-icon {
        width: 26px;
        font-size: 1rem;
        text-align: center;
        flex-shrink: 0;
    }
    .pricing-card .features-list .feature-label {
        font-weight: 700;
        min-width: 110px;
        flex-shrink: 0;
    }
    .pricing-card .features-list .feature-value {
        font-weight: 500;
        color: #1e293b;
    }
    .pricing-card .btn-buy {
        border: none;
        padding: 12px 20px;
        border-radius: 40px;
        font-weight: 700;
        width: 100%;
        transition: 0.3s;
        font-size: 1rem;
        text-align: center;
        display: block;
        text-decoration: none;
        margin-top: auto;
        color: white;
        letter-spacing: 0.5px;
    }
    .pricing-card .btn-buy:hover {
        transform: scale(1.02);
        color: white;
    }
    .pricing-card .btn-buy:disabled {
        background: #94a3b8 !important;
        cursor: not-allowed;
        transform: none;
    }
    .pricing-card .badge-status {
        display: inline-block;
        padding: 6px 18px;
        border-radius: 30px;
        font-size: 0.8rem;
        font-weight: 700;
        margin-top: 8px;
    }
    .badge-status.active { background: #dcfce7; color: #166534; }
    .badge-status.pending { background: #fef3c7; color: #92400e; }
    .section-title {
        font-size: 2.4rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 6px;
    }
    .section-subtitle {
        color: #64748b;
        font-size: 1.1rem;
        margin-bottom: 30px;
    }

    /* Package Specific Overrides – Darker & More Distinct */
    .pkg-silver .pricing-card {
        background: linear-gradient(145deg, #f0f0f0, #d1d5db);
        border-color: #9ca3af;
    }
    .pkg-silver .pricing-card .package-name { color: #374151; }
    .pkg-silver .pricing-card .btn-buy { background: #4b5563; }
    .pkg-silver .pricing-card .btn-buy:hover { background: #374151; }
    .pkg-silver .badge-recommended { background: #6b7280; }

    .pkg-gold .pricing-card {
        background: linear-gradient(145deg, #fef3c7, #fcd34d);
        border-color: #f59e0b;
    }
    .pkg-gold .pricing-card .package-name { color: #78350f; }
    .pkg-gold .pricing-card .btn-buy { background: #b45309; }
    .pkg-gold .pricing-card .btn-buy:hover { background: #92400e; }
    .pkg-gold .badge-recommended { background: #d97706; }

    .pkg-platinum .pricing-card {
        background: linear-gradient(145deg, #e2e8f0, #94a3b8);
        border-color: #64748b;
    }
    .pkg-platinum .pricing-card .package-name { color: #1e293b; }
    .pkg-platinum .pricing-card .btn-buy { background: #334155; }
    .pkg-platinum .pricing-card .btn-buy:hover { background: #1e293b; }
    .pkg-platinum .badge-recommended { background: #475569; }

    .pkg-diamond .pricing-card {
        background: linear-gradient(145deg, #dbeafe, #60a5fa);
        border-color: #2563eb;
    }
    .pkg-diamond .pricing-card .package-name { color: #1e3a8a; }
    .pkg-diamond .pricing-card .btn-buy { background: #1d4ed8; }
    .pkg-diamond .pricing-card .btn-buy:hover { background: #1e40af; }
    .pkg-diamond .badge-recommended { background: #2563eb; }

    .pricing-card .feature-icon {
        color: #3b82f6;
    }
    .pkg-silver .pricing-card .feature-icon { color: #6b7280; }
    .pkg-gold .pricing-card .feature-icon { color: #d97706; }
    .pkg-platinum .pricing-card .feature-icon { color: #475569; }
    .pkg-diamond .pricing-card .feature-icon { color: #2563eb; }
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
            $col_size = 'col-lg-6 col-md-6';
            $is_recommended = ($index == 1 && $count > 2);

            // Package specific CSS class
            $name = $pkg['name'];
            $pkg_class = 'pkg-' . strtolower($name);
            if (!in_array($name, ['Silver','Gold','Platinum','Diamond'])) {
                $pkg_class = 'pkg-silver';
            }

            // Fields
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
                    
                    <div class="package-name"><?= htmlspecialchars($name) ?></div>
                    <div class="package-duration"><?= $pkg['duration_months'] ?> Months Access</div>

                    <div class="price-box">
                        <?php if ($show_discount): ?>
                            <span class="regular-price">₹<?= number_format($regular_price, 0) ?></span>
                            <span class="offer-price">₹<?= number_format($discount_price, 0) ?></span>
                            <span class="offer-badge">🔥 Save <?= round((($regular_price - $discount_price)/$regular_price)*100) ?>%</span>
                        <?php else: ?>
                            <span class="offer-price" style="font-size:2.4rem;">₹<?= number_format($regular_price, 0) ?></span>
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
