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
    // =============== USER VIEW ===================
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
    <div class="user-welcome-banner">
        <div><h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2><p>Find your dream property today.</p></div>
        <div><a href="index.php" class="btn btn-light text-success fw-bold">Explore All →</a></div>
    </div>

    <div class="card-premium mb-4" style="border-left: 5px solid <?= $is_subscribed ? '#10b981' : '#f59e0b' ?>; background: <?= $is_subscribed ? '#f0fdf4' : '#fffbeb' ?>;">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5><i class="fas fa-user-clock me-2"></i>My Subscription Status</h5>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="fw-bold">📅 Registered On:</td><td><?= $reg_date_formatted ?></td></tr>
                    <?php if($is_subscribed): ?>
                        <tr><td class="fw-bold">🚀 Activated On:</td><td><?= $activation_date_formatted ?></td></tr>
                        <tr><td class="fw-bold">⏳ Expires On:</td><td><?= $expiry_date_formatted ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <?php if($is_subscribed): ?>
                    <span class="badge bg-success p-2 fs-6 w-100 w-md-auto"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($sub_info['pkg_name']) ?> Active</span>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark p-2 fs-5 w-100 w-md-auto">
                            ⏳ <?= $days_left ?> Days Remaining
                        </span>
                        <?php if($days_left <= 7 && $days_left > 0): ?>
                            <span class="badge bg-danger ms-2">⚠️ Expiring Soon!</span>
                        <?php elseif($days_left <= 0): ?>
                            <span class="badge bg-danger ms-2">❌ Expired!</span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <span class="badge bg-secondary p-2 fs-6 w-100 w-md-auto">🔴 No Active Subscription</span>
                    <div class="mt-2 text-muted">Buy a plan to unlock full details.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== BUY SEARCH ENGINE ===== -->
    <div id="packages" class="card-premium mb-4" style="border: 2px solid #fbbf24; background: #fffbeb;">
        <h4><i class="fas fa-search-dollar me-2" style="color: #f59e0b;"></i>Buy Search Engine Access</h4>
        <p class="text-muted">Subscribe to view full details of all auction properties. Choose your plan:</p>
        <div class="row">
            <?php
            $packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
            foreach($packages as $pkg) {
                $is_active = ($is_subscribed && $sub_info['package_id'] == $pkg['id']);
                // ✅ Check discount
                $discount_price = $pkg['discount_price'] ?? null;
                $regular_price = $pkg['price'];
                $show_discount = $discount_price && $discount_price < $regular_price;
            ?>
                <div class="col-md-3 mb-3">
                    <div class="card h-100 text-center shadow-sm" style="border-radius: 16px; <?= $is_active ? 'border: 2px solid #10b981; background: #f0fdf4;' : '' ?>">
                        <div class="card-body">
                            <h5 class="fw-bold"><?= htmlspecialchars($pkg['name']) ?></h5>
                            <div>
                                <?php if($show_discount): ?>
                                    <span style="text-decoration:line-through; color:#999; font-size:14px;">₹ <?= indianCurrencyFormat($regular_price) ?></span>
                                    <h4 class="text-success fw-bold">₹ <?= indianCurrencyFormat($discount_price) ?></h4>
                                    <span class="badge bg-danger">🔥 Offer</span>
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

    <!-- Search Bar -->
    <div class="card-premium mb-3">
        <form method="GET" class="row g-3">
            <div class="col-md-4"><input type="text" name="city" placeholder="City" class="form-control" value="<?= htmlspecialchars($_GET['city'] ?? '') ?>"></div>
            <div class="col-md-3">
                <select name="type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Flat" <?= ($_GET['type']??'')=='Flat'?'selected':'' ?>>Flat</option>
                    <option value="Plot" <?= ($_GET['type']??'')=='Plot'?'selected':'' ?>>Plot</option>
                    <option value="Shop" <?= ($_GET['type']??'')=='Shop'?'selected':'' ?>>Shop</option>
                </select>
            </div>
            <div class="col-md-3"><input type="number" name="max_price" placeholder="Max Price" class="form-control" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button></div>
        </form>
    </div>

    <!-- Property Cards -->
    <div class="row">
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
                <div class="col-md-4 mb-3">
                    <div class="card h-100 shadow-sm border-0" style="border-radius:16px; overflow:hidden;">
                        <a href="<?= htmlspecialchars($p['image_url'] ?: 'https://via.placeholder.com/300x200') ?>" target="_blank">
                            <img src="<?= htmlspecialchars($p['image_url'] ?: 'https://via.placeholder.com/300x200') ?>" style="height:150px; width:100%; object-fit:cover; cursor:pointer;">
                        </a>
                        <div class="p-3">
                            <span class="badge bg-light text-dark">🏦 <?= htmlspecialchars($p['bank_name'] ?? 'Bank') ?></span>
                            <h6 class="fw-bold mt-1"><?= htmlspecialchars($p['title']) ?></h6>
                            <div class="fw-bold text-success">₹ <?= indianCurrencyFormat($p['price']) ?></div>
                            <a href="property_detail.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm w-100 mt-2">View Details</a>
                        </div>
                    </div>
                </div>
            <?php }
        } else { echo "<p class='text-muted'>No properties match your search.</p>"; }
        ?>
    </div>

<?php endif; ?>
<?php include 'footer.php'; ?>
