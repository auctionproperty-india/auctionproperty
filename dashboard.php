<?php 
require_once 'db.php'; 

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// ---- 🚀 View User Mode (Admin किसी User का Dashboard देख रहा है) ----
$view_user_id = null;
if($role == 'admin' && isset($_GET['view_user'])) {
    $view_user_id = (int)$_GET['view_user'];
    // चेक करें कि यह User मौजूद है
    $check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $check->execute([$view_user_id]);
    if($check->fetch()) {
        $user_id = $view_user_id; // अब User का ID सेट कर दो
        $role = 'user'; // View को User Mode में बदल दो (ताकि Admin Dashboard न दिखे)
    }
}

// ---- Admin Actions (Toggle, Delete, Reset) ----
if($_SESSION['role'] == 'admin' && !isset($_GET['view_user'])) {
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

// ---- Include Header ----
include 'header.php'; 

$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();

// =============================================
// =============== ADMIN VIEW ==================
// =============================================
if($_SESSION['role'] == 'admin' && !isset($_GET['view_user'])): 
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_sold = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'sold'")->fetchColumn();
?>
    <div class="row g-4">
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-building"></i></div><div><h5 class="mb-0"><?= $total_props ?></h5><small>Total Properties</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-users"></i></div><div><h5 class="mb-0"><?= $total_users ?></h5><small>Total Users</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-check-circle"></i></div><div><h5 class="mb-0"><?= $total_sold ?></h5><small>Sold</small></div></div></div>
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
                            <td>
                                <?php if(!$is_self): ?>
                                    <a href="dashboard.php?view_user=<?= $u['id'] ?>" target="_blank" style="color: #60a5fa; font-weight:600; text-decoration:underline;">
                                        <?= htmlspecialchars($u['name']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($u['name']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= $u['email'] ?></td>
                            <td><span class="badge bg-<?= ($u['role']=='admin')?'danger':'info' ?>"><?= $u['role'] ?></span></td>
                            <td><span class="badge bg-<?= ($u['status']=='active')?'success':'secondary' ?>"><?= $u['status'] ?></span></td>
                            <td>
                                <?php if(!$is_self): ?>
                                    <a href="?toggle_status=<?= $u['id'] ?>" class="btn btn-sm btn-<?= ($u['status']=='active')?'warning':'success' ?>"><?= ($u['status']=='active')?'Disable':'Enable' ?></a>
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
    // User Data
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();
    
    // Purchases
    try {
        $purchases_stmt = $pdo->prepare("SELECT p.*, pr.title FROM purchases p JOIN properties pr ON p.property_id = pr.id WHERE p.user_id = ?");
        $purchases_stmt->execute([$user_id]);
        $purchases = $purchases_stmt->fetchAll();
        $purchase_count = count($purchases);
    } catch(Exception $e) {
        $purchases = [];
        $purchase_count = 0;
    }

    // अगर Admin किसी User को देख रहा है तो ऊपर एक बैनर दिखाएँ
    if(isset($_GET['view_user']) && $_SESSION['role'] == 'admin'): ?>
        <div class="alert alert-info mb-3">
            <i class="fas fa-eye me-2"></i> You are viewing <strong><?= htmlspecialchars($user['name']) ?></strong>'s Dashboard. 
            <a href="dashboard.php" class="btn btn-sm btn-primary ms-2">⬅ Back to Admin Panel</a>
        </div>
    <?php endif; ?>

    <!-- User Welcome -->
    <div class="user-welcome-banner">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div><h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2><p>Find your dream property today.</p></div>
            <div><a href="index.php" class="btn btn-light text-success fw-bold">Explore More →</a></div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-shopping-bag"></i></div><div><h5><?= $purchase_count ?></h5><small>Purchases</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center" style="border-left:4px solid #10b981;"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-gift"></i></div><div><h5><span class="badge bg-success"><?= $user['referral_code'] ?></span></h5><small>Referral Code</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-building"></i></div><div><h5><?= $total_props ?></h5><small>Total Properties</small></div></div></div>
    </div>

    <!-- Referral Link -->
    <div class="card-premium mt-3" style="border:1px solid #10b981; background:#f0fdf4;">
        <h5 class="text-success"><i class="fas fa-link me-2"></i>Share & Earn</h5>
        <div class="input-group"><input type="text" class="form-control border-success" id="refLink" value="https://<?= $_SERVER['HTTP_HOST'] ?>/register.php?ref=<?= $user['referral_code'] ?>" readonly><button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button></div>
    </div>

    <!-- Live Auctions -->
    <div class="card-premium mt-3">
        <div class="d-flex justify-content-between"><h5><i class="fas fa-fire me-2" style="color:#f97316;"></i>Live Auctions for You</h5><a href="index.php" class="btn btn-sm btn-outline-primary">View All</a></div>
        <div class="row mt-3">
            <?php
            $stmt = $pdo->query("SELECT * FROM properties WHERE status = 'available' ORDER BY id DESC LIMIT 6");
            $props = $stmt->fetchAll();
            if(count($props) > 0) {
                foreach($props as $p) { ?>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="card" style="border-radius: 16px; overflow: hidden; border: 1px solid #e9edf4; height: 100%;">
                            <img src="<?= htmlspecialchars($p['image_url'] ?: 'https://via.placeholder.com/300x200?text=Property') ?>" style="height: 150px; width:100%; object-fit: cover;">
                            <div class="p-3">
                                <span class="badge bg-light text-dark">🏦 <?= htmlspecialchars($p['bank_name'] ?? 'Bank') ?></span>
                                <h6 class="fw-bold mt-1"><?= htmlspecialchars($p['title']) ?></h6>
                                <p class="text-muted small"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($p['city']) ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-success">₹ <?= number_format($p['price'], 2) ?></span>
                                    <span class="badge bg-secondary"><?= $p['sqft'] ?? 0 ?> Sq Ft</span>
                                </div>
                                <a href="#" class="btn btn-primary btn-sm w-100 mt-2">View Auction</a>
                            </div>
                        </div>
                    </div>
                <?php }
            } else { echo "<p class='text-muted'>No live auctions right now.</p>"; }
            ?>
        </div>
    </div>

    <script>
        function copyRef() {
            let inp = document.getElementById('refLink');
            inp.select(); navigator.clipboard.writeText(inp.value).then(() => alert('Copied!')).catch(() => document.execCommand('copy'));
        }
    </script>

<?php endif; ?>

<?php include 'footer.php'; ?>
