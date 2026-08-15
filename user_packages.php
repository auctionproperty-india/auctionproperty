<?php
// ============================================================
// 📦 User Packages – Tricolor Bands + Ashok Chakra in White Band
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

    /* ---- Card Layout with 3 Bands ---- */
    .pricing-card {
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(0,0,0,0.06);
        transition: all 0.4s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        background: #ffffff;
        border: 2px solid #e2e8f0;
    }
    .pricing-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.12);
    }

    /* ---- Top Band (Saffron) ---- */
    .card-top {
        background: #ff9933;
        padding: 20px 16px 14px;
        text-align: center;
        color: white;
        min-height: 120px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        z-index: 2;
    }
    .card-top .package-name {
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        color: #fff;
        margin-bottom: 2px;
    }
    .card-top .package-duration {
        font-size: 0.9rem;
        font-weight: 500;
        opacity: 0.9;
        margin-bottom: 6px;
    }
    /* ===== PRICE BOX – UPDATED ===== */
    .card-top .price-box {
        display: flex;
        align-items: baseline;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px 12px;
        margin-top: 2px;
    }
    .card-top .price-box .regular-price {
        font-size: 2.8rem;      /* बड़ा */
        font-weight: 800;        /* Bold */
        color: #ffffff;          /* सफेद पर गहरा नहीं, इसलिए सफेद ही रखा */
        text-decoration: none;   /* Strike-through नहीं */
        letter-spacing: -0.5px;
    }
    .card-top .price-box .offer-price {
        font-size: 1.6rem;       /* छोटा */
        font-weight: 600;
        color: rgba(255,255,255,0.8);
        text-decoration: line-through;
    }
    .card-top .price-box .save-badge {
        padding: 3px 12px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 700;
        background: #ffffff;
        color: #ff9933;
        display: inline-block;
    }

    /* ---- Middle Band (White) – Contains Ashok Chakra ---- */
    .card-middle {
        background: #ffffff;
        padding: 16px 16px 12px;
        flex: 1;
        display: flex;
        flex-direction: column;
        position: relative;
        z-index: 1;
        overflow: hidden;
        min-height: 180px;
    }
    /* Ashok Chakra inside white band */
    .card-middle .chakra-bg {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 80%;
        height: auto;
        opacity: 0.12;
        z-index: 0;
        pointer-events: none;
        transition: opacity 0.3s ease;
    }
    .pricing-card:hover .card-middle .chakra-bg {
        opacity: 0.18;
    }
    .card-middle .chakra-bg svg {
        width: 100%;
        height: auto;
        display: block;
    }
    .card-middle .chakra-bg svg circle,
    .card-middle .chakra-bg svg line {
        stroke-width: 1.8 !important;
    }
    .card-middle .chakra-bg svg .chakra-circle-outer {
        stroke: #1e293b;
        stroke-width: 2.5;
        opacity: 0.4;
    }
    .card-middle .chakra-bg svg .chakra-circle-inner {
        stroke: #1e293b;
        stroke-width: 2;
        opacity: 0.3;
    }
    .card-middle .chakra-bg svg .chakra-spoke {
        stroke: #1e293b;
        stroke-width: 1.5;
        opacity: 0.4;
    }
    .card-middle .chakra-bg svg .chakra-dot {
        fill: #1e293b;
        opacity: 0.5;
    }

    /* Features box – positioned above chakra */
    .card-middle .features-box {
        position: relative;
        z-index: 1;
        width: 100%;
        border-radius: 16px;
        padding: 12px 12px;
        border: 2px solid #e2e8f0;
        background: rgba(255,255,255,0.85);
        backdrop-filter: blur(2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        transition: all 0.3s ease;
    }
    .pricing-card:hover .card-middle .features-box {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .card-middle .features-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        width: 100%;
        font-size: 0.9rem;
    }
    .card-middle .feature-item {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 6px 4px;
        border-bottom: 1px solid #f1f5f9;
        flex-wrap: wrap;
    }
    .card-middle .feature-item:nth-last-child(1),
    .card-middle .feature-item:nth-last-child(2) {
        border-bottom: none;
    }
    .card-middle .feature-item .feature-icon {
        font-size: 0.85rem;
        flex-shrink: 0;
        width: 20px;
        text-align: center;
        color: #ff9933; /* default */
    }
    .card-middle .feature-item .feature-label {
        font-weight: 600;
        color: #475569;
        font-size: 0.8rem;
        white-space: nowrap;
    }
    .card-middle .feature-item .feature-value {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.85rem;
        background: rgba(0,0,0,0.03);
        padding: 1px 10px;
        border-radius: 20px;
    }

    /* ---- Bottom Band (Green) ---- */
    .card-bottom {
        background: #138808;
        padding: 14px 16px 18px;
        text-align: center;
        min-height: 70px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        position: relative;
        z-index: 2;
    }
    .card-bottom .btn-buy {
        background: #ffffff;
        color: #138808;
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
        letter-spacing: 0.3px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .card-bottom .btn-buy:hover {
        background: #f0fdf0;
        transform: scale(1.02);
        color: #0a5c0a;
    }
    .card-bottom .btn-buy:disabled {
        background: #94a3b8 !important;
        cursor: not-allowed;
        transform: none;
        color: #f1f5f9;
    }
    .card-bottom .badge-status {
        display: inline-block;
        padding: 6px 18px;
        border-radius: 30px;
        font-size: 0.8rem;
        font-weight: 700;
        background: #ffffff;
        color: #0a5c0a;
        border: 1px solid #0a5c0a;
    }
    .badge-status.active { background: #ffffff; color: #166534; border-color: #166534; }
    .badge-status.pending { background: #ffffff; color: #92400e; border-color: #f59e0b; }

    /* Recommended badge – positioned absolutely on card */
    .badge-recommended {
        position: absolute;
        top: 12px;
        right: 16px;
        background: #ffffff;
        color: #ff9933;
        padding: 4px 16px;
        border-radius: 30px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        z-index: 10;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        border: 1px solid #ff9933;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .card-middle .features-grid {
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .card-middle .feature-item {
            padding: 5px 4px;
            font-size: 0.8rem;
        }
        .card-middle .feature-item .feature-label {
            font-size: 0.75rem;
            white-space: normal;
        }
        .card-middle .feature-item .feature-value {
            font-size: 0.75rem;
            padding: 1px 8px;
        }
        .section-title {
            font-size: 1.8rem;
        }
        .card-top .package-name {
            font-size: 1.6rem;
        }
        .card-top .price-box .regular-price {
            font-size: 2.2rem;
        }
        .card-top .price-box .offer-price {
            font-size: 1.4rem;
        }
    }
    @media (max-width: 576px) {
        .card-middle .features-grid {
            grid-template-columns: 1fr;
        }
        .card-middle .feature-item {
            justify-content: center;
        }
        .card-middle .feature-item:nth-last-child(1),
        .card-middle .feature-item:nth-last-child(2) {
            border-bottom: 1px solid #f1f5f9;
        }
        .card-middle .feature-item:last-child {
            border-bottom: none;
        }
    }

    /* Package specific icon colors */
    .pkg-silver .card-middle .feature-icon { color: #ff9933; }
    .pkg-gold .card-middle .feature-icon { color: #f59e0b; }
    .pkg-platinum .card-middle .feature-icon { color: #138808; }
    .pkg-diamond .card-middle .feature-icon { color: #ff9933; }
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

                        <!-- ===== TOP BAND: SAFFRON ===== -->
                        <div class="card-top">
                            <div class="package-name"><?= htmlspecialchars($name) ?></div>
                            <div class="package-duration"><?= $pkg['duration_months'] ?> Months Access</div>
                            <div class="price-box">
                                <?php if ($show_discount): ?>
                                    <!-- Original Price – बड़ा, Bold -->
                                    <span class="regular-price">₹<?= number_format($regular_price, 0) ?></span>
                                    <!-- Discount Price – छोटा, Strike-through -->
                                    <span class="offer-price">₹<?= number_format($discount_price, 0) ?></span>
                                    <?php 
                                    $saved = round((($regular_price - $discount_price)/$regular_price)*100);
                                    ?>
                                    <span class="save-badge">🔥 Save <?= $saved ?>%</span>
                                <?php else: ?>
                                    <!-- अगर कोई discount नहीं, तो सिर्फ original price बड़ा दिखाएँ -->
                                    <span class="regular-price">₹<?= number_format($regular_price, 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ===== MIDDLE BAND: WHITE (with Ashok Chakra) ===== -->
                        <div class="card-middle">
                            <!-- Ashok Chakra Background -->
                            <div class="chakra-bg">
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="50" cy="50" r="44" fill="none" class="chakra-circle-outer"/>
                                    <circle cx="50" cy="50" r="40" fill="none" stroke="#1e293b" stroke-width="0.8" opacity="0.3"/>
                                    <circle cx="50" cy="50" r="14" fill="none" class="chakra-circle-inner"/>
                                    <?php for ($i = 0; $i < 24; $i++): 
                                        $angle = $i * 15 - 7.5;
                                        $rad = deg2rad($angle);
                                        $x1 = 50 + 18 * cos($rad);
                                        $y1 = 50 + 18 * sin($rad);
                                        $x2 = 50 + 42 * cos($rad);
                                        $y2 = 50 + 42 * sin($rad);
                                    ?>
                                        <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>" class="chakra-spoke"/>
                                    <?php endfor; ?>
                                    <?php for ($i = 0; $i < 24; $i++): 
                                        $angle = $i * 15;
                                        $rad = deg2rad($angle);
                                        $x = 50 + 38 * cos($rad);
                                        $y = 50 + 38 * sin($rad);
                                    ?>
                                        <circle cx="<?= $x ?>" cy="<?= $y ?>" r="1.5" class="chakra-dot"/>
                                    <?php endfor; ?>
                                </svg>
                            </div>

                            <div class="features-box">
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
                            </div>
                        </div>

                        <!-- ===== BOTTOM BAND: GREEN ===== -->
                        <div class="card-bottom">
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
                </div>
            <?php endforeach; ?>
        </div>

        <p class="text-center text-muted mt-4"><small>* After payment, admin will activate your subscription within 24 hours.</small></p>
    </div>
</div>

<?php include 'footer.php'; ?>
