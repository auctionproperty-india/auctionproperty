<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];

include 'header.php'; 

// ---- User Data ----
$user_stmt = $pdo->prepare("SELECT *, created_at as reg_date, city FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
$user_city = $user['city'] ?? '';

$show_images = userHasActiveSubscription($pdo, $user_id);

// ---- Subscription Data ----
$active_sub = $pdo->prepare("SELECT s.*, p.name as pkg_name, s.start_date, s.end_date, (s.end_date - CURRENT_DATE) as days_left FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE ORDER BY s.id DESC LIMIT 1");
$active_sub->execute([$user_id]);
$sub_info = $active_sub->fetch();
$is_subscribed = $sub_info ? true : false;

$reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
$activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
$expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
$days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

// ---- Referral Data ----
$referral_link = getReferralLink($user_id);
$earnings = getReferralEarnings($pdo, $user_id, 'pending');
$paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
$total_pending = array_sum(array_column($earnings, 'amount'));
$total_paid = array_sum(array_column($paid_earnings, 'net_amount'));
$team_members = getReferredUsers($pdo, $user_id);

$user_subs = $pdo->prepare("SELECT s.*, p.name as pkg_name FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
$user_subs->execute([$user_id]);
$user_subs = $user_subs->fetchAll();

// ---- 6 Lowest Price Properties from User's City ----
$sql = "SELECT * FROM properties WHERE status = 'available'";
$params = [];
if(!empty($user_city)) {
    $sql .= " AND city ILIKE ?";
    $params[] = '%'.$user_city.'%';
}
$sql .= " ORDER BY price ASC LIMIT 6";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$props = $stmt->fetchAll();
?>
<!-- Welcome Banner -->
<div class="user-welcome-banner">
    <div><h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
        <p>Showing best deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'your area' ?></p>
    </div>
    <div><a href="index.php" class="btn btn-light text-success fw-bold">Explore All →</a></div>
</div>

<!-- Subscription Status -->
<div class="card-premium mb-4" style="border-left: 5px solid <?= $is_subscribed ? '#10b981' : '#f59e0b' ?>;">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h5><i class="fas fa-user-clock me-2"></i>My Subscription</h5>
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="fw-bold">📅 Registered:</td><td><?= $reg_date_formatted ?></td></tr>
                <?php if($is_subscribed): ?>
                    <tr><td class="fw-bold">🚀 Activated:</td><td><?= $activation_date_formatted ?></td></tr>
                    <tr><td class="fw-bold">⏳ Expires:</td><td><?= $expiry_date_formatted ?></td></tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="col-md-6 text-md-end">
            <?php if($is_subscribed): ?>
                <span class="badge bg-success p-2 fs-6">✅ <?= htmlspecialchars($sub_info['pkg_name']) ?> Active</span>
                <div class="mt-2"><span class="badge bg-warning text-dark p-2 fs-5">⏳ <?= $days_left ?> Days Left</span></div>
            <?php else: ?>
                <span class="badge bg-secondary p-2 fs-6">🔴 No Active</span>
                <div class="mt-2"><a href="user_dashboard.php#packages" class="btn btn-sm btn-primary">Buy Plan</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Referral Link -->
<div class="card-premium mb-4" style="border:1px solid #10b981; background:#f0fdf4;">
    <h5><i class="fas fa-link me-2" style="color:#10b981;"></i>Your Referral Link</h5>
    <div class="input-group">
        <input type="text" class="form-control border-success" id="refLink" value="<?= $referral_link ?>" readonly>
        <button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
    </div>
    <div class="row mt-3">
        <div class="col-md-6">
            <span class="badge bg-warning text-dark">⏳ Pending: ₹ <?= indianCurrencyFormat($total_pending) ?></span>
            <span class="badge bg-success ms-2">✅ Paid: ₹ <?= indianCurrencyFormat($total_paid) ?></span>
        </div>
        <div class="col-md-6 text-md-end">
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#teamSection">👥 View My Team</button>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#subHistoryUser">📜 Subscription History</button>
        </div>
    </div>
    <div class="collapse mt-3" id="teamSection">
        <h6>My Team (Referred Users)</h6>
        <?php if(count($team_members) > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Name</th><th>Email</th><th>Registered On</th><th>Activation Date</th></tr></thead>
                    <tbody>
                    <?php foreach($team_members as $tm): ?>
                        <tr>
                            <td><?= htmlspecialchars($tm['name']) ?></td>
                            <td><?= htmlspecialchars($tm['email']) ?></td>
                            <td><?= date('d M Y', strtotime($tm['reg_date'])) ?></td>
                            <td><?= $tm['activation_date'] ? date('d M Y', strtotime($tm['activation_date'])) : '<span class="text-muted">Not Activated</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">You haven't referred anyone yet.</p>
        <?php endif; ?>
    </div>
    <div class="collapse mt-3" id="subHistoryUser">
        <h6>Your Subscription Requests</h6>
        <?php if(count($user_subs) > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Package</th><th>Amount</th><th>Status</th><th>Payment Method</th><th>Request Date</th><th>Activation/Reject Date</th></tr></thead>
                    <tbody>
                    <?php foreach($user_subs as $us): ?>
                        <tr>
                            <td><?= htmlspecialchars($us['pkg_name']) ?></td>
                            <td>₹<?= $us['amount'] ?></td>
                            <td><span class="badge bg-<?= $us['status']=='active'?'success':($us['status']=='pending'?'warning':'danger') ?>"><?= $us['status'] ?></span></td>
                            <td><?= $us['payment_method'] ?></td>
                            <td><?= date('d M Y', strtotime($us['created_at'])) ?></td>
                            <td><?= $us['start_date'] ? date('d M Y', strtotime($us['start_date'])) : ($us['status']=='rejected' ? 'Rejected' : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: echo "<p class='text-muted'>No subscription requests yet.</p>"; endif; ?>
    </div>
</div>

<!-- Buy Packages -->
<div id="packages" class="card-premium mb-4" style="border:2px solid #fbbf24; background:#fffbeb;">
    <h4><i class="fas fa-search-dollar me-2" style="color: #f59e0b;"></i>Buy Search Engine Access</h4>
    <p class="text-muted">Subscribe to view full details of all auction properties.</p>
    <div class="row">
        <?php
        $packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
        foreach($packages as $pkg) {
            $is_active = ($is_subscribed && $sub_info['package_id'] == $pkg['id']);
            $discount_price = $pkg['discount_price'] ?? null;
            $regular_price = $pkg['price'];
            $show_discount = $discount_price && $discount_price < $regular_price;
        ?>
            <div class="col-md-3 mb-3">
                <div class="card h-100 text-center shadow-sm" style="border-radius: 16px; <?= $is_active ? 'border: 2px solid #10b981; background: #f0fdf4;' : '' ?>">
                    <div class="card-body">
                        <h5 class="fw-bold"><?= htmlspecialchars($pkg['name']) ?></h5>
                        <div class="my-2">
                            <?php if($show_discount): ?>
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
                        <?php if($is_active): ?>
                            <div class="badge bg-success w-100 mt-2">✅ Active (<?= $days_left ?> days left)</div>
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

<!-- Best Deals -->
<div class="card-premium">
    <h5><i class="fas fa-fire me-2" style="color:#f97316;"></i>Best Deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'Your City' ?></h5>
    <div class="row">
        <?php if(count($props) > 0): ?>
            <?php foreach($props as $p): ?>
            <div class="col-md-4 mb-3">
                <div class="card h-100 shadow-sm border-0" style="border-radius:16px; overflow:hidden;">
                    <?php if($show_images && !empty($p['image_url'])): ?>
                        <a href="<?= htmlspecialchars($p['image_url']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($p['image_url']) ?>" style="height:150px; width:100%; object-fit:cover; cursor:pointer;">
                        </a>
                    <?php else: ?>
                        <div style="height:150px; background: linear-gradient(145deg, #f8fafc, #e2e8f0); display: flex; align-items: center; justify-content: center; border-radius: 16px 16px 0 0; flex-direction: column;">
                            <i class="fas fa-home" style="font-size: 30px; color: #94a3b8;"></i>
                            <span class="badge bg-warning mt-1" style="font-size: 11px;">🔒 Subscribe</span>
                        </div>
                    <?php endif; ?>
                    <div class="p-3">
                        <span class="badge bg-light text-dark">🏦 <?= htmlspecialchars($p['bank_name'] ?? 'Bank') ?></span>
                        <h6 class="fw-bold mt-1"><?= htmlspecialchars($p['title']) ?></h6>
                        <div class="fw-bold text-success">₹ <?= indianCurrencyFormat($p['price']) ?></div>
                        <a href="property_detail.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm w-100 mt-2">View Details</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">No properties available in your city yet.</p>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyRef() { let inp = document.getElementById('refLink'); inp.select(); navigator.clipboard.writeText(inp.value).then(() => alert('Referral Link Copied!')).catch(() => document.execCommand('copy')); }
</script>

<?php include 'footer.php'; ?>
