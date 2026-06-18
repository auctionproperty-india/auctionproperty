<?php 
require_once 'db.php'; 

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

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

// =============================================
// =============== ADMIN VIEW ==================
// =============================================
if($role == 'admin'): 
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_sold = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'sold'")->fetchColumn();
?>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card-premium d-flex align-items-center">
                <div class="stat-icon bg-soft-primary me-3"><i class="fas fa-building"></i></div>
                <div><h5 class="mb-0"><?= $total_props ?></h5><small class="text-muted">Total Properties</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-premium d-flex align-items-center">
                <div class="stat-icon bg-soft-success me-3"><i class="fas fa-users"></i></div>
                <div><h5 class="mb-0"><?= $total_users ?></h5><small class="text-muted">Total Users</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-premium d-flex align-items-center">
                <div class="stat-icon bg-soft-warning me-3"><i class="fas fa-check-circle"></i></div>
                <div><h5 class="mb-0"><?= $total_sold ?></h5><small class="text-muted">Sold Properties</small></div>
            </div>
        </div>
    </div>

    <?php if(isset($_SESSION['new_pass_display'])): ?>
        <div class="alert alert-success mt-4"><?= $_SESSION['new_pass_display']; unset($_SESSION['new_pass_display']); ?></div>
    <?php endif; ?>

    <div id="users-section" class="mt-4">
        <div class="card-premium">
            <h4><i class="fas fa-users-cog me-2"></i>Manage Users & Admins</h4>
            <div class="table-responsive">
                <table class="table table-hover mt-3">
                    <thead class="table-light"><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php 
                    $users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();
                    foreach($users as $u) { 
                        $is_self = ($u['id'] == $_SESSION['user_id']);
                    ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= $u['email'] ?></td>
                            <td><span class="badge bg-<?= ($u['role']=='admin')?'danger':'info' ?>"><?= $u['role'] ?></span></td>
                            <td><span class="badge bg-<?= ($u['status']=='active')?'success':'secondary' ?>"><?= $u['status'] ?></span></td>
                            <td>
                                <?php if(!$is_self): ?>
                                    <a href="?toggle_status=<?= $u['id'] ?>" class="btn btn-sm btn-<?= ($u['status']=='active')?'warning':'success' ?>">
                                        <?= ($u['status']=='active')?'Disable':'Enable' ?>
                                    </a>
                                    <a href="change_password.php?user_id=<?= $u['id'] ?>" class="btn btn-sm btn-warning">🔑 Change</a>
                                    <a href="?delete_user=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</a>
                                <?php else: ?>
                                    <span class="text-muted"><i class="fas fa-lock"></i> You</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php 
// =============================================
// =============== USER VIEW ===================
// =============================================
else: 
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    try {
        $purchases_stmt = $pdo->prepare("SELECT p.*, pr.title FROM purchases p JOIN properties pr ON p.property_id = pr.id WHERE p.user_id = ?");
        $purchases_stmt->execute([$user_id]);
        $purchases = $purchases_stmt->fetchAll();
        $purchase_count = count($purchases);
    } catch(Exception $e) {
        $purchases = [];
        $purchase_count = 0;
    }
?>

    <!-- User Welcome Banner -->
    <div class="user-welcome-banner">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2>🏡 Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2>
                <p>Checkout the latest properties available near you.</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-light text-success fw-bold">View All →</a>
            </div>
        </div>
    </div>

    <!-- User Stats -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card-premium d-flex align-items-center">
                <div class="stat-icon bg-soft-primary me-3"><i class="fas fa-shopping-bag"></i></div>
                <div><h5 class="mb-0"><?= $purchase_count ?></h5><small class="text-muted">My Purchases</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-premium d-flex align-items-center" style="border-left: 4px solid #10b981;">
                <div class="stat-icon bg-soft-success me-3"><i class="fas fa-gift"></i></div>
                <div><h5 class="mb-0">🎉 <span class="badge bg-success"><?= $user['referral_code'] ?></span></h5><small class="text-muted">Referral Code</small></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-premium d-flex align-items-center">
                <div class="stat-icon bg-soft-warning me-3"><i class="fas fa-building"></i></div>
                <div><h5 class="mb-0"><?= $total_props ?></h5><small class="text-muted">Total Properties</small></div>
            </div>
        </div>
    </div>

    <!-- Referral Link -->
    <div class="card-premium mt-3" style="border: 1px solid #10b981; background: #f0fdf4;">
        <h5 class="text-success"><i class="fas fa-link me-2"></i>Share & Earn</h5>
        <div class="input-group mb-2">
            <input type="text" class="form-control border-success" id="refLink" value="https://<?= $_SERVER['HTTP_HOST'] ?>/register.php?ref=<?= $user['referral_code'] ?>" readonly>
            <button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy Link</button>
        </div>
    </div>

    <!-- ===== 🆕 User को यहाँ Properties की लिस्ट दिखेगी ===== -->
    <div class="card-premium mt-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-fire me-2" style="color: #f97316;"></i>Latest Properties</h5>
            <a href="index.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="row mt-3">
            <?php
            // सिर्फ 6 Latest Available Properties लाएँ
            $stmt = $pdo->query("SELECT * FROM properties WHERE status = 'available' ORDER BY id DESC LIMIT 6");
            $properties = $stmt->fetchAll();
            if(count($properties) > 0) {
                foreach($properties as $prop) { ?>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="card h-100 shadow-sm border-0" style="border-radius: 16px; overflow: hidden;">
                            <img src="<?= htmlspecialchars($prop['image_url'] ?: 'https://via.placeholder.com/300x200?text=No+Image') ?>" 
                                 class="card-img-top" style="height: 150px; object-fit: cover;">
                            <div class="card-body p-3">
                                <h6 class="card-title fw-bold mb-1"><?= htmlspecialchars($prop['title']) ?></h6>
                                <p class="text-muted small mb-1"><i class="fas fa-map-pin me-1"></i><?= htmlspecialchars($prop['city'] ?? '') ?></p>
                                <p class="text-success fw-bold">₹ <?= number_format($prop['price'], 2) ?></p>
                            </div>
                        </div>
                    </div>
                <?php }
            } else { echo "<p class='text-muted'>No properties available right now. Check back later!</p>"; }
            ?>
        </div>
    </div>

    <!-- Purchases History -->
    <div class="card-premium mt-3">
        <h5><i class="fas fa-history me-2"></i>Recent Purchases</h5>
        <?php if($purchase_count > 0) { ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light"><tr><th>Property</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach($purchases as $p) { ?>
                        <tr>
                            <td><?= htmlspecialchars($p['title']) ?></td>
                            <td>₹<?= $p['amount'] ?></td>
                            <td><span class="badge bg-<?= ($p['status']=='completed')?'success':'warning' ?>"><?= $p['status'] ?></span></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted">🛒 You haven't purchased anything yet.</p>
        <?php } ?>
    </div>

    <script>
        function copyRef() {
            let inp = document.getElementById('refLink');
            inp.select(); 
            navigator.clipboard.writeText(inp.value).then(() => {
                alert('✅ Referral Link Copied!');
            }).catch(() => {
                document.execCommand('copy');
                alert('✅ Referral Link Copied!');
            });
        }
    </script>

<?php endif; ?>

<?php include 'footer.php'; ?>
