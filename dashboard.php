<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Admin Actions remain same
if($role == 'admin') {
    // ... (keep your existing admin actions) ...
    // I'm keeping them short here, but you should keep your full admin logic.
}

include 'header.php'; 
$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$show_images = userHasActiveSubscription($pdo, $user_id);

if($role == 'admin'): 
    // Admin view unchanged (keep your existing admin dashboard)
    // ...
    // (I'll skip for brevity, but you should copy your existing admin dashboard from previous code)
else: 
    // ---- USER VIEW ----
    $user_stmt = $pdo->prepare("SELECT *, created_at as reg_date, city FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    $user_city = $user['city'] ?? '';

    // Subscription status
    $active_sub = $pdo->prepare("SELECT s.*, p.name as pkg_name, s.start_date, s.end_date, (s.end_date - CURRENT_DATE) as days_left FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE ORDER BY s.id DESC LIMIT 1");
    $active_sub->execute([$user_id]);
    $sub_info = $active_sub->fetch();
    $is_subscribed = $sub_info ? true : false;

    $reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
    $activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
    $expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
    $days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

    // ---- Fetch 6 Lowest Price Properties from User's City ----
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
    <div class="user-welcome-banner">
        <div><h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2>
            <p>Showing the most affordable properties in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'your area' ?></p>
        </div>
        <div><a href="index.php" class="btn btn-light text-success fw-bold">Explore All →</a></div>
    </div>

    <!-- Subscription Status (Quick Summary) -->
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
                    <div class="mt-2"><a href="dashboard.php#packages" class="btn btn-sm btn-primary">Buy Plan</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 6 Properties (Lowest Price) -->
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
                <p class="text-muted">No properties available in your city yet. Explore all properties.</p>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>
<?php include 'footer.php'; ?>
