<?php 
require_once 'db.php'; 
include 'header.php'; 

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// ---- Admin Actions (Delete, Reset, Toggle) ----
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
        $_SESSION['new_pass_display'] = "✅ New Password for User ID $id is: <strong>$new_pass</strong>";
        header("Location: dashboard.php");
        exit;
    }
}

// Common Stats
$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();

if($role == 'admin'): 
    // ---- ADMIN VIEW ----
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
                                <a href="?reset_pass=<?= $u['id'] ?>" class="btn btn-sm btn-info" onclick="return confirm('Reset password?')">Reset Pass</a>
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

<?php else: 
    // ---- USER VIEW ----
    $user_data = $pdo->prepare("SELECT * FROM users WHERE id = ?")->execute([$user_id]);
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?")->execute([$user_id]);
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$user_id]);
    $user = $user->fetch();
    
    // Purchases (अगर Purchases टेबल है तो, वरना शांत रहें)
    try {
        $purchases = $pdo->prepare("SELECT p.*, pr.title FROM purchases p JOIN properties pr ON p.property_id = pr.id WHERE p.user_id = ?");
        $purchases->execute([$user_id]);
        $purchases = $purchases->fetchAll();
        $purchase_count = count($purchases);
    } catch(Exception $e) {
        $purchases = [];
        $purchase_count = 0;
    }
?>
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card-premium d-flex align-items-center">
                <div class="stat-icon bg-soft-primary me-3"><i class="fas fa-shopping-bag"></i></div>
                <div><h5 class="mb-0"><?= $purchase_count ?></h5><small class="text-muted">My Purchases</small></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card-premium d-flex align-items-center">
                <div class="stat-icon bg-soft-success me-3"><i class="fas fa-gift"></i></div>
                <div><h5 class="mb-0">🎉 Referral Code</h5><small class="text-muted"><?= $user['referral_code'] ?></small></div>
            </div>
        </div>
    </div>

    <div class="card-premium mt-4">
        <h5><i class="fas fa-link me-2"></i>Your Referral Link</h5>
        <div class="input-group mb-3">
            <input type="text" class="form-control" id="refLink" value="https://<?= $_SERVER['HTTP_HOST'] ?>/register.php?ref=<?= $user['referral_code'] ?>" readonly>
            <button class="btn btn-primary" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
        </div>
    </div>

    <div class="card-premium mt-3">
        <h5><i class="fas fa-history me-2"></i>Recent Purchases</h5>
        <?php if($purchase_count > 0) { ?>
            <ul class="list-group list-group-flush">
            <?php foreach($purchases as $p) { ?>
                <li class="list-group-item d-flex justify-content-between">
                    <?= htmlspecialchars($p['title']) ?>
                    <span class="badge bg-<?= ($p['status']=='completed')?'success':'warning' ?>">₹<?= $p['amount'] ?> (<?= $p['status'] ?>)</span>
                </li>
            <?php } ?>
            </ul>
        <?php } else { echo "<p class='text-muted'>No purchases yet.</p>"; } ?>
    </div>

    <script>
        function copyRef() {
            let inp = document.getElementById('refLink');
            inp.select(); document.execCommand('copy');
            alert('Referral Link Copied!');
        }
    </script>
<?php endif; ?>

<?php include 'footer.php'; ?>
