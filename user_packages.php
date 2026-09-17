<?php
// ============================================================
// 💎 User Packages – World-Class Tricolor Design + Dynamic Fields
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

// ============================================================
// 🔥 DYNAMIC FIELDS SUPPORT
// ============================================================

// Fetch all packages
$packages = $pdo->query("SELECT * FROM packages ORDER BY id ASC")->fetchAll();

// Fetch all active fields (Master List)
$allFields = $pdo->query("SELECT * FROM package_fields WHERE is_active = TRUE ORDER BY display_order ASC, id ASC")->fetchAll();

// Fetch all field values (only visible per package)
$fieldValues = [];
$stmt = $pdo->query("SELECT * FROM package_field_values WHERE is_visible = TRUE");
while ($row = $stmt->fetch()) {
    $fieldValues[$row['package_id']][$row['field_id']] = $row['field_value'];
}

// Icon Map (Field Type के अनुसार या Field Key के अनुसार)
function getFieldIcon($fieldKey) {
    $icons = [
        'validity' => 'fa-clock',
        'property_search' => 'fa-search',
        'company_support' => 'fa-headset',
        'sales_team_support' => 'fa-users',
        'self_refer_incentive' => 'fa-coins',
        'team_refer_incentive' => 'fa-handshake',
        'property_sale_incentive' => 'fa-percent',
        'team_sale_incentive' => 'fa-percent',
        'free_property_visit' => 'fa-building',
        'property_registration' => 'fa-file-signature',
        'free_parking' => 'fa-car',
        'furnished' => 'fa-couch',
        'floor_number' => 'fa-layer-group',
        'carpet_area' => 'fa-ruler-combined',
        'possession' => 'fa-key',
    ];
    return $icons[$fieldKey] ?? 'fa-check-circle';
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

    .tricolor-section {
        font-family: 'Inter', sans-serif;
        background: 
            radial-gradient(circle at 10% 20%, rgba(255,153,51,0.06) 0%, transparent 40%),
            radial-gradient(circle at 90% 80%, rgba(19,136,8,0.06) 0%, transparent 40%),
            linear-gradient(180deg, #fdfbf7 0%, #ffffff 50%, #f7faf7 100%);
        padding: 40px 0;
        min-height: 100vh;
        position: relative;
    }
    .tricolor-strip {
        height: 5px;
        background: linear-gradient(90deg, 
            #ff9933 0%, #ff9933 33%, 
            #ffffff 33%, #ffffff 66%, 
            #138808 66%, #138808 100%);
        margin-bottom: 30px;
        width: 70%;
        margin-left: auto;
        margin-right: auto;
        border-radius: 3px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    }
    .section-title {
        font-weight: 900;
        font-size: 3rem;
        letter-spacing: -1px;
        background: linear-gradient(135deg, #ff9933 0%, #1e293b 50%, #138808 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .section-subtitle {
        color: #64748b;
        font-size: 1.05rem;
        font-weight: 500;
        margin-top: 4px;
    }

    /* ---- Pricing Card ---- */
    .pricing-card {
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 4px 25px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.04);
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
    }
    .pricing-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 30px 60px rgba(0,0,0,0.14), 0 4px 12px rgba(0,0,0,0.06);
    }
    .pricing-card.recommended {
        border-color: #fbbf24;
        box-shadow: 0 8px 35px rgba(251,191,36,0.2), 0 2px 8px rgba(0,0,0,0.04);
    }
    .pricing-card.active-plan {
        border: 2.5px solid #10b981;
        box-shadow: 0 8px 35px rgba(16,185,129,0.2);
    }

    /* ---- Recommended Badge ---- */
    .badge-recommended {
        position: absolute;
        top: 14px;
        right: 14px;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #fff;
        padding: 5px 16px;
        border-radius: 30px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        z-index: 10;
        box-shadow: 0 4px 15px rgba(251,191,36,0.5);
        animation: pulseBadge 2s infinite;
    }
    @keyframes pulseBadge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    /* ---- Top Band: Saffron ---- */
    .card-top {
        background: linear-gradient(135deg, #ff9933 0%, #f97316 100%);
        padding: 26px 20px 18px;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
    }
    .card-top::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        pointer-events: none;
    }
    .card-top .package-name {
        font-size: 2.2rem;
        font-weight: 900;
        letter-spacing: -0.8px;
        color: #fff;
        margin-bottom: 2px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        position: relative;
    }
    .card-top .package-duration {
        font-size: 0.88rem;
        font-weight: 500;
        opacity: 0.95;
        margin-bottom: 10px;
        position: relative;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .card-top .price-box {
        display: flex;
        align-items: baseline;
        justify-content: center;
        flex-wrap: wrap;
        gap: 6px 10px;
        position: relative;
    }
    .card-top .price-box .regular-price {
        font-size: 2.6rem;
        font-weight: 800;
        color: rgba(255,255,255,0.65);
        text-decoration: line-through;
        text-decoration-thickness: 3px;
        letter-spacing: -0.8px;
    }
    .card-top .price-box .offer-price {
        font-size: 2rem;
        font-weight: 800;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .card-top .price-box .offer-price .arrow {
        font-size: 1.6rem;
        font-weight: 300;
        opacity: 0.9;
    }
    .card-top .price-box .save-badge {
        padding: 4px 14px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 800;
        background: #ffffff;
        color: #f97316;
        display: inline-block;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* ---- Middle Band: White with Ashok Chakra ---- */
    .card-middle {
        background: #ffffff;
        padding: 18px 18px 16px;
        flex: 1;
        position: relative;
        overflow: hidden;
        min-height: 200px;
        display: flex;
        flex-direction: column;
    }
    .chakra-bg {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 75%;
        max-width: 240px;
        opacity: 0.10;
        z-index: 0;
        pointer-events: none;
        transition: opacity 0.5s ease;
    }
    .pricing-card:hover .chakra-bg {
        opacity: 0.16;
    }
    .chakra-bg svg {
        width: 100%;
        height: auto;
        display: block;
        animation: rotateChakra 60s linear infinite;
    }
    @keyframes rotateChakra {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .chakra-bg svg circle,
    .chakra-bg svg line {
        stroke: #1e293b;
    }
    .chakra-bg svg .chakra-circle-outer {
        stroke-width: 2.5;
        opacity: 0.5;
    }
    .chakra-bg svg .chakra-circle-inner {
        stroke-width: 2;
        opacity: 0.4;
    }
    .chakra-bg svg .chakra-spoke {
        stroke-width: 1.5;
        opacity: 0.5;
    }
    .chakra-bg svg .chakra-dot {
        fill: #1e293b;
        opacity: 0.6;
        stroke: none;
    }

    /* ---- Features Box ---- */
    .features-box {
        position: relative;
        z-index: 1;
        width: 100%;
        border-radius: 18px;
        padding: 14px 14px;
        border: 1.5px solid #eef2f6;
        background: rgba(255,255,255,0.92);
        backdrop-filter: blur(6px);
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        transition: all 0.3s ease;
    }
    .pricing-card:hover .features-box {
        border-color: #cbd5e1;
        box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    }
    .features-grid {
        display: flex;
        flex-direction: column;
        gap: 2px;
        width: 100%;
    }
    .feature-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 6px;
        border-bottom: 1px solid #f8fafc;
        transition: all 0.2s;
        border-radius: 8px;
    }
    .feature-item:hover {
        background: #f8fafc;
    }
    .feature-item:last-child {
        border-bottom: none;
    }
    .feature-item .feature-icon {
        font-size: 0.9rem;
        flex-shrink: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: linear-gradient(135deg, #fff7ed, #ffedd5);
        color: #f97316;
    }
    .feature-item .feature-label {
        font-weight: 600;
        color: #64748b;
        font-size: 0.78rem;
        flex: 1;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .feature-item .feature-value {
        font-weight: 800;
        color: #0f172a;
        font-size: 0.85rem;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        padding: 3px 12px;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
    }

    /* ---- Bottom Band: Green ---- */
    .card-bottom {
        background: linear-gradient(135deg, #138808 0%, #0a5c0a 100%);
        padding: 18px 20px;
        text-align: center;
        position: relative;
        z-index: 2;
        overflow: hidden;
    }
    .card-bottom::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        pointer-events: none;
    }
    .btn-buy {
        background: #ffffff;
        color: #138808;
        border: none;
        padding: 13px 24px;
        border-radius: 50px;
        font-weight: 800;
        width: 100%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 1rem;
        text-align: center;
        display: block;
        text-decoration: none;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        position: relative;
        z-index: 1;
    }
    .btn-buy:hover {
        background: #f0fdf0;
        transform: translateY(-2px);
        color: #0a5c0a;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }
    .btn-buy:disabled {
        background: rgba(255,255,255,0.4);
        color: rgba(255,255,255,0.9);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .badge-status {
        display: inline-block;
        padding: 7px 22px;
        border-radius: 50px;
        font-size: 0.82rem;
        font-weight: 800;
        background: #ffffff;
        color: #0a5c0a;
        margin-bottom: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-status.active { color: #166534; }
    .badge-status.pending { color: #92400e; }

    /* ---- Package Specific Icon Colors ---- */
    .pkg-silver .feature-icon { background: linear-gradient(135deg, #f8fafc, #e2e8f0) !important; color: #64748b !important; }
    .pkg-gold .feature-icon { background: linear-gradient(135deg, #fff7ed, #fed7aa) !important; color: #d97706 !important; }
    .pkg-platinum .feature-icon { background: linear-gradient(135deg, #f0fdf4, #bbf7d0) !important; color: #16a34a !important; }
    .pkg-diamond .feature-icon { background: linear-gradient(135deg, #eff6ff, #bfdbfe) !important; color: #2563eb !important; }

    /* ---- Responsive ---- */
    @media (max-width: 768px) {
        .section-title { font-size: 2rem; }
        .card-top .package-name { font-size: 1.7rem; }
        .card-top .price-box .regular-price { font-size: 2rem; }
        .card-top .price-box .offer-price { font-size: 1.6rem; }
        .feature-item .feature-label { font-size: 0.72rem; }
        .feature-item .feature-value { font-size: 0.78rem; padding: 2px 10px; }
    }
</style>

<div class="tricolor-section">
    <div class="container-fluid py-4">
        <div class="tricolor-strip"></div>

        <!-- Header -->
        <div class="text-center mb-5">
            <h2 class="section-title">✦ Choose Your Plan ✦</h2>
            <p class="section-subtitle">Celebrate Independence with the best property deals 🇮🇳</p>
        </div>

        <?php if ($has_pending): ?>
            <div class="alert alert-warning text-center mx-auto" style="max-width: 600px; background: #fffbeb; border-color: #f59e0b; color: #92400e; border-radius: 16px;">
                <i class="fas fa-clock"></i> You have a pending request. Please wait for admin approval.
            </div>
        <?php endif; ?>

        <div class="row g-4 justify-content-center">
            <?php 
            $count = count($packages);
            foreach ($packages as $index => $pkg):
                $is_active = ($is_subscribed && $sub_info['package_id'] == $pkg['id']);
                $discount_price = $pkg['discount_price'] ?? null;
                $regular_price = $pkg['price'] ?? 0;
                $show_discount = $discount_price && $discount_price < $regular_price;
                $col_size = ($count <= 2) ? 'col-lg-5 col-md-6' : 'col-lg-4 col-md-6';
                $is_recommended = ($index == 1 && $count > 2);

                $name = $pkg['name'];
                $pkg_class = 'pkg-' . strtolower(preg_replace('/[^a-z]/i', '', $name));
                if (!in_array(strtolower($name), ['silver','gold','platinum','diamond'])) {
                    $pkg_class = 'pkg-silver';
                }

                // 🔥 Fetch Dynamic Fields for THIS Package
                $pkgFields = [];
                if (isset($fieldValues[$pkg['id']])) {
                    foreach ($allFields as $f) {
                        if (isset($fieldValues[$pkg['id']][$f['id']])) {
                            $value = trim($fieldValues[$pkg['id']][$f['id']] ?? '');
                            if ($value !== '' && $value !== null) {
                                $pkgFields[] = [
                                    'label' => $f['field_label'],
                                    'key' => $f['field_key'],
                                    'value' => $value,
                                ];
                            }
                        }
                    }
                }
            ?>
                <div class="<?= $col_size ?> mb-4 <?= $pkg_class ?>">
                    <div class="pricing-card <?= $is_recommended ? 'recommended' : '' ?> <?= $is_active ? 'active-plan' : '' ?>">
                        
                        <?php if ($is_recommended): ?>
                            <span class="badge-recommended">⭐ Recommended</span>
                        <?php endif; ?>

                        <!-- ===== TOP BAND: SAFFRON ===== -->
                        <div class="card-top">
                            <div class="package-name"><?= htmlspecialchars($name) ?></div>
                            <div class="package-duration">
                                <?= htmlspecialchars($pkg['duration'] ?? 0) ?> Months Access
                            </div>
                            <div class="price-box">
                                <?php if ($show_discount): ?>
                                    <span class="regular-price">₹<?= number_format($regular_price, 0) ?></span>
                                    <span class="offer-price">
                                        <span class="arrow">→</span> ₹<?= number_format($discount_price, 0) ?>
                                    </span>
                                    <?php 
                                    $saved = round((($regular_price - $discount_price)/$regular_price)*100);
                                    ?>
                                    <span class="save-badge">🔥 Save <?= $saved ?>%</span>
                                <?php else: ?>
                                    <span class="regular-price" style="text-decoration:none; color:#fff;">₹<?= number_format($regular_price, 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- ===== MIDDLE BAND: WHITE (Ashok Chakra) ===== -->
                        <div class="card-middle">
                            <div class="chakra-bg">
                                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="50" cy="50" r="44" fill="none" class="chakra-circle-outer"/>
                                    <circle cx="50" cy="50" r="40" fill="none" class="chakra-circle-inner"/>
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
                                <?php if (!empty($pkgFields)): ?>
                                    <div class="features-grid">
                                        <?php foreach ($pkgFields as $field): ?>
                                            <div class="feature-item">
                                                <span class="feature-icon">
                                                    <i class="fas <?= getFieldIcon($field['key']) ?>"></i>
                                                </span>
                                                <span class="feature-label"><?= htmlspecialchars($field['label']) ?></span>
                                                <span class="feature-value"><?= htmlspecialchars($field['value']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4" style="font-size: 0.85rem;">
                                        <i class="fas fa-box-open fa-2x mb-2 d-block opacity-50"></i>
                                        इस Package में Details उपलब्ध नहीं हैं
                                    </div>
                                <?php endif; ?>
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

        <p class="text-center text-muted mt-4">
            <small>* After payment, admin will activate your subscription within 24 hours.</small>
        </p>
    </div>
</div>

<?php include 'footer.php'; ?>
