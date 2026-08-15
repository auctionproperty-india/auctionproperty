<?php
// ============================================================
// 📦 User Packages – Independence Day Special (Tricolor + Ashok Chakra)
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

// Tricolor theme for each package (Saffron, White, Green)
$color_schemes = [
    'Silver' => ['bg' => '#fff8f0', 'border' => '#ff9933', 'title' => '#cc7a00', 'accent' => '#ff9933', 'btn' => '#ff9933', 'btn_hover' => '#e68900', 'chakra_color' => 'rgba(255,153,51,0.08)'],
    'Gold'   => ['bg' => '#ffffff', 'border' => '#ffd700', 'title' => '#2d2d2d', 'accent' => '#fbbf24', 'btn' => '#fbbf24', 'btn_hover' => '#f59e0b', 'chakra_color' => 'rgba(251,191,36,0.10)'],
    'Platinum' => ['bg' => '#f0fff0', 'border' => '#138808', 'title' => '#0a5c0a', 'accent' => '#138808', 'btn' => '#138808', 'btn_hover' => '#0a5c0a', 'chakra_color' => 'rgba(19,136,8,0.08)'],
    'Diamond' => ['bg' => '#fff8f0', 'border' => '#ff9933', 'title' => '#0a5c0a', 'accent' => '#138808', 'btn' => '#ff9933', 'btn_hover' => '#e68900', 'chakra_color' => 'rgba(255,153,51,0.08)']
];
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

    .tricolor-section {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(180deg, #fdf6f0 0%, #f7faf7 50%, #fdf6f0 100%);
        padding: 40px 0;
        border-radius: 0;
        position: relative;
        min-height: 100vh;
    }
    .tricolor-strip {
        height: 4px;
        background: linear-gradient(90deg, #ff9933 0%, #ff9933 33%, #ffffff 33%, #ffffff 66%, #138808 66%, #138808 100%);
        margin-bottom: 30px;
        width: 80%;
        margin-left: auto;
        margin-right: auto;
        border-radius: 2px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .section-title {
        font-weight: 800;
        font-size: 2.6rem;
        color: #1e293b;
        letter-spacing: -0.5px;
    }
    .section-title .saffron { color: #ff9933; }
    .section-title .green { color: #138808; }
    .section-subtitle {
        color: #64748b;
        font-size: 1.1rem;
        font-weight: 400;
    }

    /* Card Styles */
    .pricing-card {
        background: #ffffff;
        border-radius: 28px;
        padding: 30px 20px 28px;
        transition: all 0.4s ease;
        height: 100%;
        position: relative;
        box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        border-top: 6px solid #e2e8f0;
        overflow: hidden;
    }
    .pricing-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.12);
    }
    /* Ashok Chakra Background */
    .pricing-card .chakra-bg {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 80%;
        height: auto;
        opacity: 0.08;
        z-index: 0;
        pointer-events: none;
    }
    .pricing-card .chakra-bg svg {
        width: 100%;
        height: auto;
        display: block;
    }
    .pricing-card > * {
        position: relative;
        z-index: 1;
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
        z-index: 2;
    }

    .pricing-card .package-name {
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin-bottom: 2px;
    }
    .pricing-card .package-duration {
        font-size: 0.9rem;
        font-weight: 500;
        color: #64748b;
        margin-bottom: 6px;
    }
    .pricing-card .price-box {
        display: flex;
        align-items: baseline;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
        margin: 4px 0 12px;
    }
    .pricing-card .price-box .regular-price {
        font-size: 1rem;
        color: #94a3b8;
        text-decoration: line-through;
        font-weight: 400;
    }
    .pricing-card .price-box .offer-price {
        font-size: 2.4rem;
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

    /* Features Grid – Centered */
    .pricing-card .features-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2px 16px;
        margin: 16px 0 22px;
        width: 100%;
        font-size: 0.9rem;
        flex: 1;
        justify-items: center;
        align-items: center;
    }
    .pricing-card .feature-item {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 5px 8px;
        border-bottom: 1px solid #f1f5f9;
        width: 100%;
        flex-wrap: wrap;
    }
    .pricing-card .feature-item .feature-icon {
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .pricing-card .feature-item .feature-label {
        font-weight: 600;
        color: #475569;
        font-size: 0.85rem;
    }
    .pricing-card .feature-item .feature-value {
        font-weight: 500;
        color: #1e293b;
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

    /* Package Specific Overrides – Tricolor */
    .pkg-silver .pricing-card { border-top-color: #ff9933; background: #fff8f0; }
    .pkg-silver .pricing-card .package-name { color: #cc7a00; }
    .pkg-silver .pricing-card .btn-buy { background: #ff9933; }
    .pkg-silver .pricing-card .btn-buy:hover { background: #e68900; }
    .pkg-silver .pricing-card .feature-icon { color: #ff9933; }
    .pkg-silver .badge-recommended { background: #ff9933; }

    .pkg-gold .pricing-card { border-top-color: #ffd700; background: #ffffff; }
    .pkg-gold .pricing-card .package-name { color: #2d2d2d; }
    .pkg-gold .pricing-card .btn-buy { background: #fbbf24; color: #1e293b; }
    .pkg-gold .pricing-card .btn-buy:hover { background: #f59e0b; color: #1e293b; }
    .pkg-gold .pricing-card .feature-icon { color: #f59e0b; }
    .pkg-gold .badge-recommended { background: #f59e0b; }

    .pkg-platinum .pricing-card { border-top-color: #138808; background: #f0fff0; }
    .pkg-platinum .pricing-card .package-name { color: #0a5c0a; }
    .pkg-platinum .pricing-card .btn-buy { background: #138808; }
    .pkg-platinum .pricing-card .btn-buy:hover { background: #0a5c0a; }
    .pkg-platinum .pricing-card .feature-icon { color: #138808; }
    .pkg-platinum .badge-recommended { background: #138808; }

    .pkg-diamond .pricing-card { border-top-color: #ff9933; background: #fff8f0; }
    .pkg-diamond .pricing-card .package-name { color: #0a5c0a; }
    .pkg-diamond .pricing-card .btn-buy { background: #ff9933; }
    .pkg-diamond .pricing-card .btn-buy:hover { background: #e68900; }
    .pkg-diamond .pricing-card .feature-icon { color: #138808; }
    .pkg-diamond .badge-recommended { background: #ff9933; }

    /* Responsive */
    @media (max-width: 768px) {
        .pricing-card .features-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }
        .section-title {
            font-size: 1.8rem;
        }
        .pricing-card .package-name {
            font-size: 1.6rem;
        }
        .pricing-card .price-box .offer-price {
            font-size: 2rem;
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
            <p class="section-subtitle">Celebrate Independence with the best property deals 🇮🇳</p>
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
                    <div class="pricing-card <?= $is_recommended ? 'recommended' : '' ?> <?= $is_active ? 'border border-success border-2' : '' ?>">
                        <?php if ($is_recommended): ?>
                            <span class="badge-recommended">⭐ Recommended</span>
                        <?php endif; ?>
                        
                        <!-- Ashok Chakra SVG Background -->
                        <div class="chakra-bg">
                            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="50" cy="50" r="45" fill="none" stroke="#1e293b" stroke-width="1.5" opacity="0.5"/>
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#1e293b" stroke-width="0.8" opacity="0.3"/>
                                <circle cx="50" cy="50" r="12" fill="none" stroke="#1e293b" stroke-width="1.2" opacity="0.6"/>
                                <!-- 24 spokes -->
                                <?php for ($i = 0; $i < 24; $i++): 
                                    $angle = $i * 15 - 7.5;
                                    $rad = deg2rad($angle);
                                    $x1 = 50 + 16 * cos($rad);
                                    $y1 = 50 + 16 * sin($rad);
                                    $x2 = 50 + 42 * cos($rad);
                                    $y2 = 50 + 42 * sin($rad);
                                ?>
                                    <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>" stroke="#1e293b" stroke-width="0.8" opacity="0.4"/>
                                <?php endfor; ?>
                                <!-- dots around chakra -->
                                <?php for ($i = 0; $i < 24; $i++): 
                                    $angle = $i * 15;
                                    $rad = deg2rad($angle);
                                    $x = 50 + 40 * cos($rad);
                                    $y = 50 + 40 * sin($rad);
                                ?>
                                    <circle cx="<?= $x ?>" cy="<?= $y ?>" r="1.2" fill="#1e293b" opacity="0.5"/>
                                <?php endfor; ?>
                            </svg>
                        </div>

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

                        <!-- Features Grid -->
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
