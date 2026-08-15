<?php
// ============================================================
// 📦 User Packages – Premium Look (Left Top, Centered Features)
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

// Premium color schemes – high-end gradients
$color_schemes = [
    'Silver' => [
        'bg' => 'linear-gradient(145deg, #f7f7f7, #e0e0e0)',
        'border' => '#c0c0c0',
        'title' => '#2d2d2d',
        'accent' => '#6c757d',
        'btn' => '#6c757d',
        'btn_hover' => '#495057',
        'badge' => '#adb5bd'
    ],
    'Gold' => [
        'bg' => 'linear-gradient(145deg, #fff9e6, #f7d794)',
        'border' => '#d4af37',
        'title' => '#7a5d00',
        'accent' => '#b8860b',
        'btn' => '#b8860b',
        'btn_hover' => '#8b6508',
        'badge' => '#d4af37'
    ],
    'Platinum' => [
        'bg' => 'linear-gradient(145deg, #f0f2f5, #c8d0d9)',
        'border' => '#8a9ba8',
        'title' => '#2c3e50',
        'accent' => '#5d6d7e',
        'btn' => '#5d6d7e',
        'btn_hover' => '#34495e',
        'badge' => '#8a9ba8'
    ],
    'Diamond' => [
        'bg' => 'linear-gradient(145deg, #e6f0ff, #87bfff)',
        'border' => '#3b82f6',
        'title' => '#1a365d',
        'accent' => '#2563eb',
        'btn' => '#2563eb',
        'btn_hover' => '#1e3a8a',
        'badge' => '#3b82f6'
    ]
];
?>

<style>
    .pricing-card {
        border-radius: 28px;
        padding: 30px 25px 30px;
        transition: all 0.4s ease;
        border: 2px solid #e9edf4;
        height: 100%;
        position: relative;
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        display: flex;
        flex-direction: column;
        background: #ffffff;
    }
    .pricing-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 50px rgba(0,0,0,0.12);
    }
    .pricing-card .badge-recommended {
        position: absolute;
        top: -14px;
        right: 20px;
        color: white;
        padding: 4px 20px;
        border-radius: 40px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #3b82f6;
    }
    .pricing-card .pkg-top {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
        margin-bottom: 16px;
    }
    .pricing-card .package-name {
        font-size: 1.8rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin-bottom: 2px;
    }
    .pricing-card .package-duration {
        font-size: 0.9rem;
        font-weight: 500;
        color: #4b5563;
        margin-bottom: 8px;
    }
    .pricing-card .price-box {
        display: flex;
        align-items: baseline;
        gap: 10px;
        flex-wrap: wrap;
    }
    .pricing-card .price-box .regular-price {
        font-size: 1.1rem;
        color: #9ca3af;
        text-decoration: line-through;
        font-weight: 500;
    }
    .pricing-card .price-box .offer-price {
        font-size: 2.2rem;
        font-weight: 800;
        color: #0f172a;
    }
    .pricing-card .price-box .offer-badge {
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.7rem;
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
        font-size: 0.95rem;
        flex: 1;
        text-align: center; /* Center the entire list */
    }
    .pricing-card .features-list li {
        padding: 6px 0;
        border-bottom: 1px solid rgba(0,0,0,0.04);
        display: flex;
        align-items: center;
        justify-content: center; /* Center each item content */
        gap: 8px;
        flex-wrap: wrap;
    }
    .pricing-card .features-list li:last-child {
        border-bottom: none;
    }
    .pricing-card .features-list .feature-icon {
        font-size: 1rem;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
    }
    .pricing-card .features-list .feature-label {
        font-weight: 700;
        color: #1e293b;
    }
    .pricing-card .features-list .feature-value {
        font-weight: 500;
        color: #334155;
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
        letter-spacing: 0.3px;
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
        align-self: center;
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

    /* Package Specific Overrides – Premium Colors */
    .pkg-silver .pricing-card {
        background: linear-gradient(145deg, #f7f7f7, #e0e0e0);
        border-color: #c0c0c0;
    }
    .pkg-silver .pricing-card .package-name { color: #2d2d2d; }
    .pkg-silver .pricing-card .btn-buy { background: #6c757d; }
    .pkg-silver .pricing-card .btn-buy:hover { background: #495057; }
    .pkg-silver .badge-recommended { background: #6c757d; }
    .pkg-silver .pricing-card .feature-icon { color: #6c757d; }

    .pkg-gold .pricing-card {
        background: linear-gradient(145deg, #fff9e6, #f7d794);
        border-color: #d4af37;
    }
    .pkg-gold .pricing-card .package-name { color: #7a5d00; }
    .pkg-gold .pricing-card .btn-buy { background: #b8860b; }
    .pkg-gold .pricing-card .btn-buy:hover { background: #8b6508; }
    .pkg-gold .badge-recommended { background: #d4af37; }
    .pkg-gold .pricing-card .feature-icon { color: #b8860b; }

    .pkg-platinum .pricing-card {
        background: linear-gradient(145deg, #f0f2f5, #c8d0d9);
        border-color: #8a9ba8;
    }
    .pkg-platinum .pricing-card .package-name { color: #2c3e50; }
    .pkg-platinum .pricing-card .btn-buy { background: #5d6d7e; }
    .pkg-platinum .pricing-card .btn-buy:hover { background: #34495e; }
    .pkg-platinum .badge-recommended { background: #8a9ba8; }
    .pkg-platinum .pricing-card .feature-icon { color: #5d6d7e; }

    .pkg-diamond .pricing-card {
        background: linear-gradient(145deg, #e6f0ff, #87bfff);
        border-color: #3b82f6;
    }
    .pkg-diamond .pricing-card .package-name { color: #1a365d; }
    .pkg-diamond .pricing-card .btn-buy { background: #2563eb; }
    .pkg-diamond .pricing-card .btn-buy:hover { background: #1e3a8a; }
    .pkg-diamond .badge-recommended { background: #3b82f6; }
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

            $name = $pkg['name'];
            $pkg_class = 'pkg-' . strtolower($name);
            if (!in_array($name, ['Silver','Gold','Platinum','Diamond'])) {
                $pkg_class = 'pkg-silver';
            }

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
                    
                    <div class="pkg-top">
                        <div class="package-name"><?= htmlspecialchars($name) ?></div>
                        <div class="package-duration"><?= $pkg['duration_months'] ?> Months Access</div>

                        <div class="price-box">
                            <?php if ($show_discount): ?>
                                <span class="regular-price">₹<?= number_format($regular_price, 0) ?></span>
                                <span class="offer-price">₹<?= number_format($discount_price, 0) ?></span>
                                <span class="offer-badge">🔥 Save <?= round((($regular_price - $discount_price)/$regular_price)*100) ?>%</span>
                            <?php else: ?>
                                <span class="offer-price" style="font-size:2.2rem;">₹<?= number_format($regular_price, 0) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Centered Features -->
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
