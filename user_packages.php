<?php
// ============================================================
// 💎 User Packages – Premium Green/Blue World-Class Design
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
include 'header.php';

// ---- Messages ----
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
// 🔥 DYNAMIC FIELDS
// ============================================================// 🔥 Sort by display_order (Sequence) – Smallest first
$packages = $pdo->query("SELECT * FROM packages ORDER BY COALESCE(display_order, 999) ASC, id ASC")->fetchAll();
$allFields = $pdo->query("SELECT * FROM package_fields WHERE is_active = TRUE ORDER BY display_order ASC, id ASC")->fetchAll();

$fieldValues = [];
$stmt = $pdo->query("SELECT * FROM package_field_values WHERE is_visible = TRUE");
while ($row = $stmt->fetch()) {
    $fieldValues[$row['package_id']][$row['field_id']] = $row['field_value'];
}

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
        'car_registration' => 'fa-car',
        'car_visit' => 'fa-car-side',
        'free_parking' => 'fa-parking',
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

    body {
        font-family: 'Inter', sans-serif;
    }

    .premium-section {
        background: 
            radial-gradient(circle at 15% 15%, rgba(37,99,235,0.05) 0%, transparent 45%),
            radial-gradient(circle at 85% 85%, rgba(16,185,129,0.05) 0%, transparent 45%),
            linear-gradient(180deg, #f8fafc 0%, #ffffff 50%, #f0fdf4 100%);
        padding: 50px 0;
        min-height: 100vh;
    }

    /* ---- Header ---- */
    .page-header {
        text-align: center;
        margin-bottom: 50px;
    }
    .page-header .premium-title {
        font-size: 3rem;
        font-weight: 900;
        letter-spacing: -1.5px;
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 40%, #10b981 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 10px;
    }
    .page-header .premium-subtitle {
        color: #64748b;
        font-size: 1.1rem;
        font-weight: 500;
    }
    .page-header .premium-divider {
        width: 80px;
        height: 4px;
        background: linear-gradient(90deg, #2563eb, #10b981);
        margin: 18px auto 0;
        border-radius: 4px;
    }

    /* ---- Premium Card ---- */
    .premium-card {
        border-radius: 32px;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.06), 0 2px 8px rgba(0,0,0,0.03);
        transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
    }
    .premium-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 30px 70px rgba(0,0,0,0.14), 0 6px 20px rgba(37,99,235,0.08);
        border-color: #93c5fd;
    }
    .premium-card.recommended {
        border-color: #10b981;
        box-shadow: 0 10px 40px rgba(16,185,129,0.15), 0 2px 8px rgba(0,0,0,0.04);
    }
    .premium-card.active-plan {
        border: 2.5px solid #10b981;
        box-shadow: 0 15px 50px rgba(16,185,129,0.25);
    }

    /* ---- Recommended Badge ---- */
    .premium-recommended {
        position: absolute;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        padding: 7px 18px;
        border-radius: 30px;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        z-index: 10;
        box-shadow: 0 6px 20px rgba(16,185,129,0.45);
        animation: pulseGreen 2s infinite;
    }
    @keyframes pulseGreen {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.06); }
    }

    /* ---- Header Band (Green/Blue) ---- */
    .premium-header {
        padding: 35px 30px 30px;
        text-align: center;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .premium-header.blue {
        background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%);
    }
    .premium-header.green {
        background: linear-gradient(135deg, #064e3b 0%, #059669 50%, #10b981 100%);
    }
    .premium-header::before {
        content: '';
        position: absolute;
        top: -60%;
        right: -60%;
        width: 220%;
        height: 220%;
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 65%);
        pointer-events: none;
    }
    .premium-header::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 30px;
        background: linear-gradient(180deg, transparent, rgba(0,0,0,0.08));
        pointer-events: none;
    }

    .premium-header .plan-name {
        font-size: 2.4rem;
        font-weight: 900;
        letter-spacing: -1px;
        margin-bottom: 4px;
        text-shadow: 0 3px 15px rgba(0,0,0,0.2);
        position: relative;
    }
    .premium-header .plan-duration {
        font-size: 0.85rem;
        font-weight: 600;
        opacity: 0.95;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 18px;
        position: relative;
    }

    .price-wrapper {
        display: flex;
        align-items: baseline;
        justify-content: center;
        gap: 14px;
        flex-wrap: wrap;
        position: relative;
    }
    .price-old {
        font-size: 1.6rem;
        font-weight: 700;
        color: rgba(255,255,255,0.6);
        text-decoration: line-through;
        text-decoration-thickness: 2px;
    }
    .price-arrow {
        font-size: 1.6rem;
        font-weight: 300;
        color: rgba(255,255,255,0.8);
    }
    .price-new {
        font-size: 3rem;
        font-weight: 900;
        color: #ffffff;
        letter-spacing: -1.5px;
        text-shadow: 0 4px 20px rgba(0,0,0,0.25);
    }
    .save-pill {
        background: #ffffff;
        color: #059669;
        padding: 5px 16px;
        border-radius: 30px;
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        margin-top: 10px;
        display: inline-block;
    }
    .price-wrapper.no-discount .price-new {
        font-size: 3.2rem;
    }

    /* ---- Body ---- */
    .premium-body {
        padding: 28px 28px 20px;
        flex: 1;
        background: #ffffff;
        display: flex;
        flex-direction: column;
    }

    .features-grid-premium {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .feature-row-premium {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 14px;
        transition: all 0.25s ease;
        background: #f8fafc;
        border: 1.5px solid transparent;
    }
    .feature-row-premium:hover {
        background: #eff6ff;
        border-color: #bfdbfe;
        transform: translateX(4px);
    }
    .feature-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .feature-icon-box.blue {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1e40af;
    }
    .feature-icon-box.green {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
    }
    .feature-text-box {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1px;
    }
    .feature-text-box .lbl {
        font-size: 0.68rem;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    .feature-text-box .val {
        font-size: 0.95rem;
        font-weight: 800;
        color: #0f172a;
    }

    /* ---- Footer ---- */
    .premium-footer {
        padding: 22px 28px 26px;
        text-align: center;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border-top: 1.5px solid #eef2f6;
    }

    .btn-premium-buy {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        border: none;
        padding: 15px 30px;
        border-radius: 50px;
        font-weight: 800;
        width: 100%;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-size: 1rem;
        letter-spacing: 0.5px;
        display: block;
        text-decoration: none;
        box-shadow: 0 6px 25px rgba(37,99,235,0.35);
        position: relative;
        overflow: hidden;
    }
    .btn-premium-buy:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 35px rgba(37,99,235,0.5);
        color: #fff;
    }
    .btn-premium-buy.green {
        background: linear-gradient(135deg, #064e3b, #059669);
        box-shadow: 0 6px 25px rgba(16,185,129,0.35);
    }
    .btn-premium-buy.green:hover {
        box-shadow: 0 12px 35px rgba(16,185,129,0.5);
    }
    .btn-premium-buy:disabled {
        background: #cbd5e1 !important;
        color: #64748b !important;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .active-status-pill {
        display: inline-block;
        padding: 10px 24px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .active-status-pill.active {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
    }
    .active-status-pill.pending {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: #fff;
    }

    /* ---- Empty State ---- */
    .empty-pkg {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
        font-size: 0.9rem;
    }
    .empty-pkg i {
        font-size: 3rem;
        opacity: 0.3;
        display: block;
        margin-bottom: 12px;
    }

    /* ---- Responsive ---- */
    @media (max-width: 992px) {
        .page-header .premium-title { font-size: 2.2rem; }
        .premium-header .plan-name { font-size: 2rem; }
        .price-new { font-size: 2.4rem; }
        .price-old { font-size: 1.3rem; }
    }
    @media (max-width: 576px) {
        .page-header .premium-title { font-size: 1.8rem; }
        .premium-header { padding: 28px 22px 24px; }
        .premium-body { padding: 22px 20px 18px; }
        .premium-footer { padding: 18px 20px 22px; }
        .premium-header .plan-name { font-size: 1.7rem; }
        .price-new { font-size: 2rem; }
    }
</style>

<div class="premium-section">
    <div class="container">

        <!-- Header -->
        <div class="page-header">
            <h1 class="premium-title">Choose Your Plan</h1>
            <p class="premium-subtitle">Select the perfect plan for your real estate journey</p>
            <div class="premium-divider"></div>
        </div>

        <?php if ($has_pending): ?>
            <div class="alert alert-warning text-center mx-auto" style="max-width: 600px; background: #fffbeb; border-color: #f59e0b; color: #92400e; border-radius: 16px;">
                <i class="fas fa-clock"></i> You have a pending request. Please wait for admin approval.
            </div>
        <?php endif; ?>

        <!-- Packages Grid – 2 per row -->
        <div class="row g-4 justify-content-center">
            <?php 
            $count = count($packages);
            foreach ($packages as $index => $pkg):
                $is_active = ($is_subscribed && $sub_info['package_id'] == $pkg['id']);
                $discount_price = $pkg['discount_price'] ?? null;
                $regular_price = $pkg['price'] ?? 0;
                $show_discount = $discount_price && $discount_price < $regular_price;
                $is_recommended = ($index == 1 && $count > 2);

                // 🔥 Alternate Colors: Green / Blue
                $colorTheme = ($index % 2 == 0) ? 'blue' : 'green';

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
                <!-- 2 Packages per Row on Desktop -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="premium-card <?= $is_recommended ? 'recommended' : '' ?> <?= $is_active ? 'active-plan' : '' ?>">
                        
                        <?php if ($is_recommended): ?>
                            <span class="premium-recommended">⭐ Recommended</span>
                        <?php endif; ?>

                        <!-- HEADER BAND -->
                        <div class="premium-header <?= $colorTheme ?>">
                            <div class="plan-name"><?= htmlspecialchars($pkg['name']) ?></div>
                            <div class="plan-duration">
                                <?= htmlspecialchars($pkg['duration'] ?? 0) ?> Months Access
                            </div>
                            <div class="price-wrapper <?= $show_discount ? '' : 'no-discount' ?>">
                                <?php if ($show_discount): ?>
                                    <span class="price-old">₹<?= number_format($regular_price, 0) ?></span>
                                    <span class="price-arrow">→</span>
                                    <span class="price-new">₹<?= number_format($discount_price, 0) ?></span>
                                <?php else: ?>
                                    <span class="price-new">₹<?= number_format($regular_price, 0) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($show_discount): 
                                $saved = round((($regular_price - $discount_price)/$regular_price)*100);
                            ?>
                                <div class="save-pill">🔥 Save <?= $saved ?>%</div>
                            <?php endif; ?>
                        </div>

                        <!-- BODY - Dynamic Fields -->
                        <div class="premium-body">
                            <?php if (!empty($pkgFields)): ?>
                                <div class="features-grid-premium">
                                    <?php foreach ($pkgFields as $field): ?>
                                        <div class="feature-row-premium">
                                            <div class="feature-icon-box <?= $colorTheme ?>">
                                                <i class="fas <?= getFieldIcon($field['key']) ?>"></i>
                                            </div>
                                            <div class="feature-text-box">
                                                <span class="lbl"><?= htmlspecialchars($field['label']) ?></span>
                                                <span class="val"><?= htmlspecialchars($field['value']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-pkg">
                                    <i class="fas fa-box-open"></i>
                                    इस Package में Details उपलब्ध नहीं हैं
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- FOOTER -->
                        <div class="premium-footer">
                            <?php if ($is_active): ?>
                                <span class="active-status-pill active">✅ Active (<?= $days_left ?> days left)</span>
                                <button class="btn-premium-buy" disabled>Currently Active</button>
                            <?php elseif ($has_pending): ?>
                                <span class="active-status-pill pending">⏳ Pending Approval</span>
                                <button class="btn-premium-buy" disabled>Request Pending</button>
                            <?php else: ?>
                                <a href="buy_subscription.php?package_id=<?= $pkg['id'] ?>" class="btn-premium-buy <?= $colorTheme === 'green' ? 'green' : '' ?>">
                                    <i class="fas fa-arrow-right me-2"></i> Buy Now
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="text-center text-muted mt-4">
            <small><i class="fas fa-shield-alt me-1"></i> After payment, admin will activate your subscription within 24 hours.</small>
        </p>
    </div>
</div>

<?php include 'footer.php'; ?>
