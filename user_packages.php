<?php
// ============================================================
// 📦 User Packages – with all Incentives & Features
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
        echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> ✅ Your subscription request has been sent. Admin will review it shortly.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } elseif ($msg == 'already_pending') {
        echo "<div class='alert alert-warning'><i class='fas fa-clock'></i> ⚠️ You already have a pending request.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } elseif ($msg == 'already_active') {
        echo "<div class='alert alert-info'><i class='fas fa-check-circle'></i> ℹ️ You already have an active subscription.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
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
?>

<div id="packages" class="card-premium" style="border:2px solid #fbbf24; background:#fffbeb;">
    <h4><i class="fas fa-search-dollar me-2" style="color: #f59e0b;"></i>Buy Search Engine Access</h4>
    <p class="text-muted">Subscribe to view full details of all auction properties.</p>

    <?php if ($has_pending): ?>
        <div class="alert alert-warning">
            <i class="fas fa-clock"></i> You have a pending request. Please wait for admin approval.
        </div>
    <?php endif; ?>

    <div class="row">
        <?php
        $packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
        foreach ($packages as $pkg) {
            $is_active = ($is_subscribed && $sub_info['package_id'] == $pkg['id']);
            $discount_price = $pkg['discount_price'] ?? null;
            $regular_price = $pkg['price'];
            $show_discount = $discount_price && $discount_price < $regular_price;
        ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm" style="border-radius: 16px; <?= $is_active ? 'border: 2px solid #10b981; background: #f0fdf4;' : '' ?>">
                    <div class="card-body">
                        <h5 class="fw-bold"><?= htmlspecialchars($pkg['name']) ?></h5>
                        <div class="my-2">
                            <?php if ($show_discount): ?>
                                <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
                                    <span style="font-size: 22px; font-weight: 700; color: #dc3545; text-decoration: line-through; background: #fee2e2; padding: 0 12px; border-radius: 8px;">
                                        ₹ <?= indianCurrencyFormat($regular_price) ?>
                                    </span>
                                    <span style="font-size: 18px; font-weight: 800; color: #10b981; background: #d1fae5; padding: 2px 10px; border-radius: 8px;">
                                        🔥 Offer
                                    </span>
                                    <span style="font-size: 32px; font-weight: 800; color: #0f172a;">
                                        ₹ <?= indianCurrencyFormat($discount_price) ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <h4 class="text-success">₹ <?= indianCurrencyFormat($regular_price) ?></h4>
                            <?php endif; ?>
                        </div>
                        <small><?= $pkg['duration_months'] ?> Months</small>

                        <!-- 🔥 All Features & Incentives -->
                        <div class="text-start mt-3" style="font-size: 0.9rem;">
                            <ul class="list-unstyled">
                                <?php if (!empty($pkg['validity'])): ?>
                                    <li><i class="fas fa-check-circle text-success me-1"></i> <strong>Validity:</strong> <?= htmlspecialchars($pkg['validity']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($pkg['property_search'])): ?>
                                    <li><i class="fas fa-check-circle text-success me-1"></i> <strong>Property Search:</strong> <?= htmlspecialchars($pkg['property_search']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($pkg['company_support'])): ?>
                                    <li><i class="fas fa-check-circle text-success me-1"></i> <strong>Company Support:</strong> <?= htmlspecialchars($pkg['company_support']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($pkg['sales_team_support'])): ?>
                                    <li><i class="fas fa-check-circle text-success me-1"></i> <strong>Sales Team Support:</strong> <?= htmlspecialchars($pkg['sales_team_support']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($pkg['self_refer_incentive'])): ?>
                                    <li><i class="fas fa-coins text-warning me-1"></i> <strong>Self Refer Incentive:</strong> <?= htmlspecialchars($pkg['self_refer_incentive']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($pkg['team_refer_incentive'])): ?>
                                    <li><i class="fas fa-users text-info me-1"></i> <strong>Team Refer Incentive:</strong> <?= htmlspecialchars($pkg['team_refer_incentive']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($pkg['property_sale_incentive'])): ?>
                                    <li><i class="fas fa-percent text-primary me-1"></i> <strong>Property Sale Incentive:</strong> <?= htmlspecialchars($pkg['property_sale_incentive']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($pkg['team_sale_incentive'])): ?>
                                    <li><i class="fas fa-percent text-secondary me-1"></i> <strong>Team Sale Incentive:</strong> <?= htmlspecialchars($pkg['team_sale_incentive']) ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <?php if ($is_active): ?>
                            <div class="badge bg-success w-100 mt-2">✅ Active (<?= $days_left ?> days left)</div>
                        <?php elseif ($has_pending): ?>
                            <div class="badge bg-warning text-dark w-100 mt-2">⏳ Pending Request</div>
                        <?php else: ?>
                            <a href="buy_subscription.php?package_id=<?= $pkg['id'] ?>" class="btn btn-primary w-100 btn-sm mt-2">Buy Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
    <small class="text-muted">* After payment, admin will activate your subscription.</small>
</div>

<?php include 'footer.php'; ?>
