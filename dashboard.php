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
    // --- Admin View (unchanged) ---
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
    // --- USER VIEW ---
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$user_id]);
    $user = $user->fetch();

    // Check active subscription
    $has_active_sub = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
    $has_active_sub->execute([$user_id]);
    $is_subscribed = $has_active_sub->rowCount() > 0;

    try { $purchases = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE user_id = ?"); $purchases->execute([$user_id]); $purchase_count = $purchases->fetchColumn(); } catch(Exception $e) { $purchase_count = 0; }
?>
    <div class="user-welcome-banner">
        <div><h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2><p>Find your dream property today.</p></div>
        <div><a href="index.php" class="btn btn-light text-success fw-bold">Explore All →</a></div>
    </div>

    <!-- ===== BUY SEARCH ENGINE SECTION ===== -->
    <div class="card-premium mb-4" style="border: 2px solid #fbbf24; background: #fffbeb;">
        <h4><i class="fas fa-search-dollar me-2" style="color: #f59e0b;"></i>Buy Search Engine Access</h4>
        <p class="text-muted">Subscribe to view full details of all auction properties. Choose your plan:</p>
        <div class="row">
            <?php
            $packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
            foreach($packages as $pkg) {
                $already = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND package_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
                $already->execute([$user_id, $pkg['id']]);
                $is_active = $already->rowCount() > 0;
            ?>
                <div class="col-md-3 mb-3">
                    <div class="card h-100 text-center shadow-sm" style="border-radius: 16px; <?= $is_active ? 'border: 2px solid #10b981;' : '' ?>">
                        <div class="card-body">
                            <h5 class="fw-bold"><?= htmlspecialchars($pkg['name']) ?></h5>
                            <h4 class="text-success">₹ <?= indianCurrencyFormat($pkg['price']) ?></h4>
                            <small><?= $pkg['duration_months'] ?> Months</small>
                            <?php if($is_active): ?>
                                <div class="badge bg-success w-100 mt-2">✅ Active</div>
                            <?php else: ?>
                                <form method="POST" action="buy_subscription.php" class="mt-2">
                                    <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
                                    <button type="submit" class="btn btn-primary w-100 btn-sm">Buy Now</button>
                                </form>
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
                        <img src="<?= htmlspecialchars($p['image_url'] ?: 'https://via.placeholder.com/300x200') ?>" style="height:150px; width:100%; object-fit:cover;">
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
