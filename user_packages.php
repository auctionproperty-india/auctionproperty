<?php
// ============================================================
// 📦 User Packages – Luxury 4K Design
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
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;600;700&display=swap');

    .luxury-section {
        font-family: 'Inter', sans-serif;
        background: #0b1120; /* Deep dark background for the page */
        padding: 40px 0;
        border-radius: 0;
    }
    .luxury-section .section-title {
        font-family: 'Playfair Display', serif;
        font-size: 2.8rem;
        color: #f8fafc;
        letter-spacing: 1px;
    }
    .luxury-section .section-subtitle {
        color: #94a3b8;
        font-size: 1.15rem;
        font-weight: 300;
        letter-spacing: 0.5px;
    }

    .pricing-card {
        background: linear-gradient(145deg, #1e293b, #0f172a);
        border: 1px solid #334155;
        border-radius: 28px;
        padding: 30px 25px 30px;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        height: 100%;
        position: relative;
        box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        display: flex;
        flex-direction: column;
        color: #f1f5f9;
    }
    .pricing-card:hover {
        transform: translateY(-12px);
        border-color: #d4af37;
        box-shadow: 0 30px 60px rgba(212, 175, 55, 0.15);
    }
    .pricing-card .badge-recommended {
        position: absolute;
        top: -14px;
        right: 20px;
        background: linear-gradient(135deg, #d4af37, #f7d794);
        color: #0f172a;
        padding: 6px 24px;
        border-radius: 40px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 15px rgba(212,175,55,0.4);
    }
    .pricing-card .pkg-top {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
        margin-bottom: 16px;
        border-bottom: 1px solid #334155;
        padding-bottom: 16px;
    }
    .pricing-card .package-name {
        font-family: 'Playfair Display', serif;
        font-size: 2rem;
        font-weight: 700;
        color: #f8fafc;
        letter-spacing: 0.5px;
        margin-bottom: 2px;
    }
    .pricing-card .package-duration {
        font-size: 0.9rem;
        font-weight: 400;
        color: #94a3b8;
        margin-bottom: 8px;
    }
    .pricing-card .price-box {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 4px;
    }
    .pricing-card .price-box .regular-price {
        font-size: 1.1rem;
        color: #64748b;
        text-decoration: line-through;
        font-weight: 400;
    }
    .pricing-card .price-box .offer-price {
        font-size: 2.4rem;
        font-weight: 800;
        color: #fbbf24;
        letter-spacing: -0.5px;
    }
    .pricing-card .price-box .save-badge {
        background: linear-gradient(135deg, #d4af37, #f7d794);
        color: #0f172a;
        padding: 4px 14px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 10px rgba(212,175,55,0.3);
        margin-left: 6px;
    }

    /* Features – Two Column Luxury Style */
    .pricing-card .features-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4px 16px;
        margin: 18px 0 22px;
        width: 100%;
        font-size: 0.95rem;
        flex: 1;
    }
    .pricing-card .feature-item {
        display: flex;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        gap: 6px;
    }
    .pricing-card .feature-item .feature-icon {
        color: #d4af37;
        width: 20px;
        font-size: 0.9rem;
        text-align: center;
        flex-shrink: 0;
    }
    .pricing-card .feature-item .feature-label {
        font-weight: 600;
        color: #cbd5e1;
        white-space: nowrap;
    }
    .pricing-card .feature-item .feature-value {
        font-weight: 500;
        color: #f1f5f9;
        margin-left: auto;
        text-align: right;
        word-break: break-word;
    }

    .pricing-card .btn-buy {
        background: linear-gradient(135deg, #d4af37, #b8860b);
        border: none;
        padding: 14px 20px;
        border-radius: 50px;
        font-weight: 700;
        width: 100%;
        transition: 0.3s;
        font-size: 1.05rem;
        text-align: center;
        display: block;
        text-decoration: none;
        margin-top: auto;
        color: #0f172a;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        box-shadow: 0 4px 20px rgba(212,175,55,0.25);
    }
    .pricing-card .btn-buy:hover {
        background: linear-gradient(135deg, #f7d794, #d4af37);
        transform: scale(1.02);
        color: #0f172a;
        box-shadow: 0 6px 30px rgba(212,175,55,0.4);
    }
    .pricing-card .btn-buy:disabled {
        background: #334155 !important;
        box-shadow: none;
        cursor: not-allowed;
        transform: none;
        color: #94a3b8;
    }
    .pricing-card .badge-status {
        display: inline-block;
        padding: 8px 20px;
        border-radius: 40px;
        font-size: 0.8rem;
        font-weight: 700;
        margin-top: 8px;
        align-self: center;
        background: #1e293b;
        color: #f1f5f9;
        border: 1px solid #334155;
    }
    .badge-status.active {
        background: #064e3b;
        color: #6ee7b7;
        border-color: #10b981;
    }
    .badge-status.pending {
        background: #78350f;
        color: #fcd34d;
        border-color: #f59e0b;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .pricing-card .features-grid {
            grid-template-columns: 1fr;
        }
        .pricing-card .feature-item .feature-value {
            text-align: left;
            margin-left: 0;
        }
        .luxury-section .section-title {
            font-size: 2rem;
        }
    }
</style>

<div class="luxury-section">
    <div class="container-fluid py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">✨ Choose Your Plan</h2>
            <p class="section-subtitle">Select the finest package to unlock exclusive auction properties</p>
        </div>

        <?php if ($has_pending): ?>
            <div class="alert alert-warning text-center" style="background: #1e293b; border-color: #d4af37; color: #f8fafc;">
                <i class="fas fa-clock"></i> You have a pending request. Please wait for admin approval.
            </div>
        <?php endif; ?>

        <div class="row g-5 justify-content-center">
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
                // Define fields with labels and icons
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
                <div class="<?= $col_size ?> mb-4">
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
                                    <?php 
                                    $saved = round((($regular_price - $discount_price)/$regular_price)*100);
                                    ?>
                                    <span class="save-badge">🔥 Save <?= $saved ?>%</span>
                                <?php else: ?>
                                    <span class="offer-price" style="font-size:2.4rem;">₹<?= number_format($regular_price, 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Two Column Features Grid -->
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

        <p class="text-center text-muted mt-5" style="color: #94a3b8 !important;"><small>* After payment, admin will activate your subscription within 24 hours.</small></p>
    </div>
</div>

<?php include 'footer.php'; ?>
