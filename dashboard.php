<?php 
require_once 'db.php'; 
require_once 'functions.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Admin Actions
if($role == 'admin') {
    if(isset($_GET['toggle_status'])) {
        $id = $_GET['toggle_status'];
        $pdo->prepare("UPDATE users SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = ?")->execute([$id]);
        header("Location: dashboard.php");
        exit;
    }
    if(isset($_GET['delete_user'])) {
        $id = $_GET['delete_user'];
        if($id != $_SESSION['user_id']) { 
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        }
        header("Location: dashboard.php");
        exit;
    }
    if(isset($_GET['reset_pass'])) {
        $id = $_GET['reset_pass'];
        $new_pass = bin2hex(random_bytes(4)); 
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $id]);
        $_SESSION['new_pass_display'] = "✅ New Password: <strong>$new_pass</strong>";
        header("Location: dashboard.php");
        exit;
    }
}

include 'header.php'; 
$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();

if($role == 'admin'): 
    // --- Admin View (Keep as is, or update later) ---
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_sold = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'sold'")->fetchColumn();
?>
    <div class="row g-4">
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-building"></i></div><div><h5><?= $total_props ?></h5><small>Total Properties</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-users"></i></div><div><h5><?= $total_users ?></h5><small>Total Users</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-check-circle"></i></div><div><h5><?= $total_sold ?></h5><small>Sold</small></div></div></div>
    </div>
    <div id="users-section" class="mt-4">
        <div class="card-premium"><h4>👥 Manage Users</h4>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php 
                $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
                foreach($users as $u) { 
                    $is_self = ($u['id'] == $_SESSION['user_id']);
                    echo "<tr><td><a href='dashboard.php?view_user=".$u['id']."' target='_blank'>".htmlspecialchars($u['name'])."</a></td><td>".$u['email']."</td>";
                    echo "<td><span class='badge bg-".($u['role']=='admin'?'danger':'info')."'>".$u['role']."</span></td>";
                    echo "<td><span class='badge bg-".($u['status']=='active'?'success':'secondary')."'>".$u['status']."</span></td>";
                    if(!$is_self) {
                        echo "<td><a href='?toggle_status=".$u['id']."' class='btn btn-sm btn-warning'>Toggle</a> <a href='change_password.php?user_id=".$u['id']."' class='btn btn-sm btn-info'>Pass</a> <a href='?delete_user=".$u['id']."' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete?\")'>Del</a></td>";
                    } else { echo "<td>You</td>"; }
                    echo "</tr>";
                } ?>
                </tbody>
            </table></div>
        </div>
    </div>

<?php else: 
    // =============================================
    // =============== USER VIEW (SHINING GLOSSY) ===================
    // =============================================
    $user_stmt = $pdo->prepare("SELECT *, created_at as reg_date FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    $active_sub = $pdo->prepare("SELECT s.*, p.name as pkg_name, 
                                s.start_date, s.end_date, 
                                (s.end_date - CURRENT_DATE) as days_left 
                                FROM subscriptions s 
                                JOIN packages p ON s.package_id = p.id 
                                WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE 
                                ORDER BY s.id DESC LIMIT 1");
    $active_sub->execute([$user_id]);
    $sub_info = $active_sub->fetch();
    $is_subscribed = $sub_info ? true : false;

    $reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
    $activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
    $expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
    $days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

    try { $purchases = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE user_id = ?"); $purchases->execute([$user_id]); $purchase_count = $purchases->fetchColumn(); } catch(Exception $e) { $purchase_count = 0; }
?>

    <!-- 🌟 SHINING HERO BANNER -->
    <div class="position-relative p-5 mb-4 rounded-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #2563eb 100%); box-shadow: 0 20px 40px -10px rgba(37, 99, 235, 0.3); overflow: hidden;">
        <div class="position-absolute top-0 end-0 opacity-10" style="font-size: 200px; right: 20px; top: -20px;"><i class="fas fa-building"></i></div>
        <div class="position-relative" style="z-index: 2;">
            <h1 class="display-4 fw-bold">🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h1>
            <p class="fs-5 opacity-75">Find your dream property and unlock exclusive auction details.</p>
            <a href="index.php" class="btn btn-light btn-lg shadow-lg mt-2 text-primary fw-bold px-5">
                Explore All Properties <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>

    <!-- 🪞 GLOSSY STATUS CARD -->
    <div class="card border-0 shadow-lg p-4 mb-4 rounded-4" style="background: rgba(255,255,255,0.8); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.3);">
        <div class="row g-4 align-items-center">
            <div class="col-md-7">
                <h5 class="fw-bold"><i class="fas fa-crown text-warning me-2"></i> My Subscription Status</h5>
                <div class="row g-2 mt-2">
                    <div class="col-sm-4"><span class="text-muted">📅 Registered:</span><br><strong><?= $reg_date_formatted ?></strong></div>
                    <div class="col-sm-4"><span class="text-muted">🚀 Activated:</span><br><strong><?= $activation_date_formatted ?></strong></div>
                    <div class="col-sm-4"><span class="text-muted">⏳ Expires:</span><br><strong><?= $expiry_date_formatted ?></strong></div>
                </div>
            </div>
            <div class="col-md-5 text-md-end">
                <?php if($is_subscribed): ?>
                    <span class="badge bg-success p-3 fs-6 rounded-pill"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($sub_info['pkg_name']) ?> Active</span>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark p-3 fs-5 rounded-pill">
                            ⏳ <?= $days_left ?> Days Left
                        </span>
                        <?php if($days_left <= 7 && $days_left > 0): ?>
                            <span class="badge bg-danger ms-2 p-2">⚠️ Expiring Soon!</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <span class="badge bg-secondary p-3 fs-6 rounded-pill">🔴 No Active Plan</span>
                    <div class="mt-2 text-muted">Buy a plan to unlock full details.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 💎 BUY SEARCH ENGINE (GLOSSY CARDS) -->
    <div id="packages" class="card border-0 shadow-lg p-4 mb-4 rounded-4" style="background: #ffffff;">
        <h3 class="fw-bold mb-3"><i class="fas fa-rocket text-primary me-2"></i> Buy Search Engine Access</h3>
        <p class="text-muted">Choose your premium plan and unlock every property detail.</p>
        <div class="row g-4">
            <?php
            $packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
            foreach($packages as $pkg) {
                $is_active = ($is_subscribed && $sub_info['package_id'] == $pkg['id']);
                $card_border = $is_active ? 'border-2 border-success' : 'border-0';
                $bg_style = $is_active ? 'background: #f0fdf4;' : 'background: #f8fafc;';
            ?>
                <div class="col-md-3">
                    <div class="card h-100 text-center shadow-sm rounded-4 <?= $card_border ?>" style="<?= $bg_style ?> transition: all 0.3s ease; hover:shadow-xl;">
                        <div class="card-body p-4">
                            <h4 class="fw-bold"><?= htmlspecialchars($pkg['name']) ?></h4>
                            <h2 class="text-success fw-bold">₹ <?= indianCurrencyFormat($pkg['price']) ?></h2>
                            <span class="badge bg-dark"><?= $pkg['duration_months'] ?> Months</span>
                            <?php if($is_active): ?>
                                <div class="mt-3"><span class="badge bg-success w-100 p-2">✅ Active</span></div>
                            <?php else: ?>
                                <form method="POST" action="buy_subscription.php" class="mt-3">
                                    <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
                                    <button type="submit" class="btn btn-primary w-100 rounded-pill shadow-sm">Buy Now</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
        <small class="text-muted mt-3 d-block">* Admin will activate your subscription after payment confirmation.</small>
    </div>

    <!-- 🔍 Search Bar (Sleek) -->
    <div class="card border-0 shadow-sm p-3 mb-4 rounded-4" style="background: #ffffff;">
        <form method="GET" class="row g-2">
            <div class="col-md-4"><input type="text" name="city" placeholder="City" class="form-control border-0 bg-light" value="<?= htmlspecialchars($_GET['city'] ?? '') ?>"></div>
            <div class="col-md-3">
                <select name="type" class="form-select border-0 bg-light">
                    <option value="">All Types</option>
                    <option value="Flat" <?= ($_GET['type']??'')=='Flat'?'selected':'' ?>>Flat</option>
                    <option value="Plot" <?= ($_GET['type']??'')=='Plot'?'selected':'' ?>>Plot</option>
                    <option value="Shop" <?= ($_GET['type']??'')=='Shop'?'selected':'' ?>>Shop</option>
                </select>
            </div>
            <div class="col-md-3"><input type="number" name="max_price" placeholder="Max Price" class="form-control border-0 bg-light" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100 rounded-pill"><i class="fas fa-search"></i> Search</button></div>
        </form>
    </div>

    <!-- 🏠 Property Cards Grid -->
    <div class="row g-4">
        <?php
        $sql = "SELECT * FROM properties WHERE status = 'available'";
        $params = [];
        if(!empty($_GET['city'])) { $sql .= " AND city ILIKE ?"; $params[] = '%'.$_GET['city'].'%'; }
        if(!empty($_GET['type'])) { $sql .= " AND type = ?"; $params[] = $_GET['type']; }
        if(!empty($_GET['max_price'])) { $sql .= " AND price <= ?"; $params[] = $_GET['max_price']; }
        $sql .= " ORDER BY id DESC LIMIT 6";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $props = $stmt->fetchAll();
        if(count($props) > 0) {
            foreach($props as $p) { ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden hover-lift">
                        <img src="<?= htmlspecialchars($p['image_url'] ?: 'https://via.placeholder.com/300x200') ?>" style="height:180px; width:100%; object-fit:cover;">
                        <div class="card-body p-3">
                            <span class="badge bg-light text-dark mb-1">🏦 <?= htmlspecialchars($p['bank_name'] ?? 'Bank') ?></span>
                            <h6 class="fw-bold"><?= htmlspecialchars($p['title']) ?></h6>
                            <div class="fw-bold text-success">₹ <?= indianCurrencyFormat($p['price']) ?></div>
                            <a href="property_detail.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-sm w-100 mt-2 rounded-pill">View Details</a>
                        </div>
                    </div>
                </div>
            <?php }
        } else { echo "<p class='text-muted'>No properties match your search.</p>"; }
        ?>
    </div>

    <style>
        .hover-lift { transition: all 0.2s ease; }
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.1) !important; }
    </style>

<?php endif; ?>
<?php include 'footer.php'; ?>
