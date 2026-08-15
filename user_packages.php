<?php
// ============================================================
// 📦 User Packages – Tricolor Theme + Starry Background
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

// Tricolor theme packages
// Silver = Saffron (#ff9933), Gold = White (#ffffff), Platinum = Green (#138808), Diamond = Saffron+Green
$color_schemes = [
    'Silver' => ['bg' => '#fff8f0', 'border' => '#ff9933', 'title' => '#cc7a00', 'accent' => '#ff9933', 'btn' => '#ff9933', 'btn_hover' => '#e68900'],
    'Gold' => ['bg' => '#ffffff', 'border' => '#ffd700', 'title' => '#2d2d2d', 'accent' => '#ffd700', 'btn' => '#fbbf24', 'btn_hover' => '#f59e0b'],
    'Platinum' => ['bg' => '#f0fff0', 'border' => '#138808', 'title' => '#0a5c0a', 'accent' => '#138808', 'btn' => '#138808', 'btn_hover' => '#0a5c0a'],
    'Diamond' => ['bg' => '#fff8f0', 'border' => '#ff9933', 'title' => '#0a5c0a', 'accent' => '#138808', 'btn' => '#ff9933', 'btn_hover' => '#e68900']
];
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

    /* Starry Background */
    .tricolor-section {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(180deg, #fdf6f0 0%, #f0f7f0 50%, #fdf6f0 100%);
        padding: 40px 0;
        border-radius: 0;
        position: relative;
        overflow: hidden;
        min-height: 100vh;
    }
    /* Star effect */
    .tricolor-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: 
            radial-gradient(2px 2px at 20px 30px, #ff9933, transparent),
            radial-gradient(2px 2px at 40px 70px, #138808, transparent),
            radial-gradient(2px 2px at 50px 160px, #ff9933, transparent),
            radial-gradient(2px 2px at 90px 40px, #138808, transparent),
            radial-gradient(2px 2px at 130px 80px, #ff9933, transparent),
            radial-gradient(2px 2px at 160px 30px, #138808, transparent),
            radial-gradient(3px 3px at 200px 120px, #ff9933, transparent),
            radial-gradient(2px 2px at 250px 50px, #138808, transparent),
            radial-gradient(2px 2px at 300px 180px, #ff9933, transparent),
            radial-gradient(2px 2px at 350px 90px, #138808, transparent),
            radial-gradient(3px 3px at 400px 60px, #ff9933, transparent),
            radial-gradient(2px 2px at 450px 140px, #138808, transparent),
            radial-gradient(2px 2px at 500px 30px, #ff9933, transparent),
            radial-gradient(2px 2px at 550px 100px, #138808, transparent),
            radial-gradient(2px 2px at 600px 170px, #ff9933, transparent);
        background-size: 600px 200px;
        opacity: 0.2;
        pointer-events: none;
        z-index: 0;
    }
    /* Saffron, White, Green stripes at top */
    .tricolor-section .tricolor-strip {
        position: relative;
        z-index: 1;
        height: 4px;
        background: linear-gradient(90deg, #ff9933 0%, #ff9933 33%, #ffffff 33%, #ffffff 66%, #138808 66%, #138808 100%);
        margin-bottom: 25px;
        border-radius: 2px;
        width: 80%;
        margin-left: auto;
        margin-right: auto;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .tricolor-section .section-title {
        font-weight: 800;
        font-size: 2.6rem;
        color: #1e293b;
        letter-spacing: -0.5px;
        position: relative;
        z-index: 1;
    }
    .tricolor-section .section-title .saffron { color: #ff9933; }
    .tricolor-section .section-title .green { color: #138808; }
    .tricolor-section .section-subtitle {
        color: #64748b;
        font-size: 1.1rem;
        font-weight: 400;
        position: relative;
        z-index: 1;
    }

    .pricing-card {
        background: #ffffff;
        border-radius: 24px;
        padding: 28px 24px 28px;
        transition: all 0.4s ease;
        height: 100%;
        position: relative;
        box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        display: flex;
        flex-direction: column;
        border-top: 5px solid #e2e8f0;
        position: relative;
        z-index: 1;
    }
    .pricing-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.12);
    }
    .pricing-card .badge-recommended {
        position: absolute;
        top: -12px;
        right: 20px;
        color: white;
        padding: 4px 18px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: #ff9933;
        box-shadow: 0 2px 10px rgba(255,153,51,0.3);
    }
    .pricing-card .pkg-top {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
        margin-bottom: 12px;
    }
    .pricing-card .package-name {
        font-size: 1.8rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin-bottom: 2px;
    }
    .pricing-card .package-duration {
        font-size: 0.85rem;
        font-weight: 500;
        color: #64748b;
        margin-bottom: 6px;
    }
    .pricing-card .price-box {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 2px;
    }
    .pricing-card .price-box .regular-price {
        font-size: 1rem;
        color: #94a3b8;
        text-decoration: line-through;
        font-weight: 400;
    }
    .pricing-card .price-box .offer-price {
        font-size: 2.2rem;
        font-weight: 800;
        color: #0f172a;
    }
    .pricing-card .price-box .save-badge {
        padding: 3px 12px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 700;
        background: #dcfce7;
        color: #166534;
        display: inline-block;
    }

    /* Features Grid with Icons */
    .pricing-card .features-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2px 12px;
        margin: 16px 0 20px;
        width: 100%;
        font-size: 0.9rem;
        flex: 1;
    }
    .pricing-card .feature-item {
        display: flex;
        align-items: center;
        padding: 5px 0;
        border-bottom: 1px solid #f1f5f9;
        gap: 6px;
        min-height: 34px;
    }
    .pricing-card .feature-item .feature-icon {
        width: 22px;
        font-size: 0.9rem;
        text-align: center;
        flex-shrink: 0;
        color: #ff9933;
    }
    .pricing-card .feature-item .feature-label {
        font-weight: 600;
        color: #475569;
        font-size: 0.85rem;
        white-space: nowrap;
    }
    .pricing-card .feature-item .feature-value {
        font-weight: 500;
        color: #1e293b;
        margin-left: auto;
        text-align: right;
        word-break: break-word;
        font-size: 0.85rem;
    }

    .pricing-card .btn-buy {
        border: none;
        padding: 12px 20px;
        border-radius: 50px;
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
        border: none;
    }
    .pricing-card .btn-buy:hover {
        transform: scale(1.02);
        color: white;
    }
    .pricing-card .btn-buy:disabled {
        background: #94a3b8 !important;
        cursor: not-allowed;
        transform: none;
        color: #f1f5f9;
    }
    .pricing-card .badge-status {
        display: inline-block;
        padding: 6px 18px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 700;
        margin-top: 8px;
        align-self: center;
    }
    .badge-status.active { background: #dcfce7; color: #166534; }
    .badge-status.pending { background: #fef3c7; color: #92400e; }

    /* Package Specific Colors */
    .pkg-silver .pricing-card { border-top-color: #ff9933; }
    .pkg-silver .pricing-card .package-name { color: #cc7a00; }
    .pkg-silver .pricing-card .btn-buy { background: #ff9933; }
    .pkg-silver .pricing-card .btn-buy:hover { background: #e68900; }
    .pkg-silver .pricing-card .feature-icon { color: #ff9933; }
    .pkg-silver .badge-recommended { background: #ff9933; }

    .pkg-gold .pricing-card { border-top-color: #ffd700; background: #fefefe; }
    .pkg-gold .pricing-card .package-name { color: #2d2d2d; }
    .pkg-gold .pricing-card .btn-buy { background: #fbbf24; color: #1e293b; }
    .pkg-gold .pricing-card .btn-buy:hover { background: #f59e0b; color: #1e293b; }
    .pkg-gold .pricing-card .feature-icon { color: #f59e0b; }
    .pkg-gold .badge-recommended { background: #f59e0b; }

    .pkg-platinum .pricing-card { border-top-color: #138808; }
    .pkg-platinum .pricing-card .package-name { color: #0a5c0a; }
    .pkg-platinum .pricing-card .btn-buy { background: #138808; }
    .pkg-platinum .pricing-card .btn-buy:hover { background: #0a5c0a; }
    .pkg-platinum .pricing-card .feature-icon { color: #138808; }
    .pkg-platinum .badge-recommended { background: #138808; }

    .pkg-diamond .pricing-card { border-top-color: #ff9933; background: #fffefc; }
    .pkg-diamond .pricing-card .package-name { color: #0a5c0a; }
    .pkg-diamond .pricing-card .btn-buy { background: #ff9933; }
    .pkg-diamond .pricing-card .btn-buy:hover { background: #e68900; }
    .pkg-diamond .pricing-card .feature-icon { color: #138808; }
    .pkg-diamond .badge-recommended { background: #ff9933; }

    @media (max-width: 768px) {
        .pricing-card .features-grid {
            grid-template-columns: 1fr;
        }
        .pricing-card .feature-item .feature-value {
            text-align: left;
            margin-left: 0;
        }
        .tricolor-section .section-title {
            font-size: 1.8rem;
        }
    }
</style>

<div class="tricolor-section">
    <div class="container-fluid py-4">
        <div class="tricolor-strip"></div>
        <div class="text-center mb-5">
            <h2 class="section-title">
                <span class="saffron">✦</span> Choose Your <span class="saffron">Plan</span> <span class="green">✦</span>
            </h2>
            <p class="section-subtitle">Select the best package to unlock all auction properties</p>
        </div>

        <?php if ($has_pending): ?>
            <div class="alert alert-warning text-center" style="background: #fffbeb; border-color: #f59e0b; color: #92400e;">
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
                    <div class="pricing-card <?= $is_recommended ? 'recommended' : '' ?> <?= $is_active ? 'border border-success border-2' : '' ?>">
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
                                    <?php 
                                    $saved = round((($regular_price - $discount_price)/$regular_price)*100);
                                    ?>
                                    <span class="save-badge">🔥 Save <?= $saved ?>%</span>
                                <?php else: ?>
                                    <span class="offer-price" style="font-size:2.2rem;">₹<?= number_format($regular_price, 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Features Grid with Icons -->
                        <div class="features-grid">
                            <?php foreach ($fields as $field): 
                                $value = trim($pkg[$field['key']] ?? '');
                                if ($value !== '' && $value !== null):
                            ?>
                                <div class="feature-item">
                                    <span class="feature-icon"><i class="fas <?= $field['icon'] ?>"></i></span>
                                    <span class="feature-label"><?= $field['label'] ?></span>
                                    <span class="feature-value"><?= htmlspecialchars($value) ?></span>
                                </div>
                            <?php endif; endforeach; ?>
                        </div>

                        <?php if ($is_active): ?>
                            <span class="badge-status active">✅ Active (<?= $days_left ?> days left)</span>
                            <button class="btn-buy" disabled>Currently Active</button>
                        <?php elseif ($has_pending): ?>
                            <span class="badge-status pending">⏳ Pending Approval</span>
                            <button class="btn-buy" disabled>Request Pending</button>
                        <?php else: ?>
                            <a href="buy_subscription.php?package_id=<?= $pkg['id'] ?>" class="btn-buy">
                                <i class="fas fa-arrow-right me-2"></i> Buy Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="text-center text-muted mt-4"><small>* After payment, admin will activate your subscription within 24 hours.</small></p>
    </div>
</div>

<?php include 'footer.php'; ?>
