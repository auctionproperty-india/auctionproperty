<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];

// ---- Super Admin Check ----
$is_super_admin = false;
$stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
if($user_data && $user_data['is_super_admin']) {
    $is_super_admin = true;
    $_SESSION['is_super_admin'] = true;
} else {
    $_SESSION['is_super_admin'] = false;
}

// ---- Handle POST actions from modal ----
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(!$is_super_admin) die("Access Denied");

    // Set Password
    if(isset($_POST['set_password']) && isset($_POST['user_id']) && isset($_POST['new_password'])) {
        $uid = (int)$_POST['user_id'];
        $new_pass = trim($_POST['new_password']);
        if(strlen($new_pass) < 4) {
            $_SESSION['new_pass_display'] = "❌ Password must be at least 4 characters.";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $uid]);
            $_SESSION['new_pass_display'] = "✅ Password for User ID $uid set to: <strong>$new_pass</strong>";
        }
        header("Location: admin_dashboard.php");
        exit;
    }

    // Change Referrer
    if(isset($_POST['change_referrer']) && isset($_POST['user_id']) && isset($_POST['new_referrer_id'])) {
        $uid = (int)$_POST['user_id'];
        $new_ref = (int)$_POST['new_referrer_id'];
        if($uid == $new_ref) {
            $_SESSION['new_pass_display'] = "❌ Cannot refer to self!";
        } else {
            $pdo->prepare("UPDATE users SET referred_by = ?, manual_referral_updated = TRUE WHERE id = ?")->execute([$new_ref ?: null, $uid]);
            $_SESSION['new_pass_display'] = "✅ Referrer updated successfully!";
        }
        header("Location: admin_dashboard.php");
        exit;
    }

    // Delete User (POST)
    if(isset($_POST['delete_user']) && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        if($uid != $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $_SESSION['new_pass_display'] = "✅ User deleted successfully!";
        } else {
            $_SESSION['new_pass_display'] = "❌ Cannot delete yourself!";
        }
        header("Location: admin_dashboard.php");
        exit;
    }
}

// ---- Handle GET actions (toggle status, reset password show) ----
if(isset($_GET['toggle_status'])) {
    if(!$is_super_admin) die("Access Denied");
    $id = (int)$_GET['toggle_status'];
    $pdo->prepare("UPDATE users SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = ?")->execute([$id]);
    header("Location: admin_dashboard.php");
    exit;
}
if(isset($_GET['reset_pass_show'])) {
    if(!$is_super_admin) die("Access Denied");
    $id = (int)$_GET['reset_pass_show'];
    $new_pass = bin2hex(random_bytes(4)); 
    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $id]);
    $_SESSION['new_pass_display'] = "✅ New Password for User ID $id is: <strong>$new_pass</strong>";
    header("Location: admin_dashboard.php");
    exit;
}

include 'header.php'; 

$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_sold = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'sold'")->fetchColumn();
$balance = getAccountBalance($pdo);
$user_search = $_GET['user_search'] ?? '';
?>
<!-- Stats -->
<div class="row g-4">
    <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-building"></i></div><div><h5><?= $total_props ?></h5><small>Properties</small></div></div></div>
    <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-users"></i></div><div><h5><?= $total_users ?></h5><small>Total Users</small></div></div></div>
    <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-check-circle"></i></div><div><h5><?= $total_sold ?></h5><small>Sold</small></div></div></div>
    <div class="col-md-3"><div class="card-premium d-flex align-items-center" style="border-left:4px solid #2563eb;"><div class="stat-icon bg-soft-info me-3"><i class="fas fa-wallet"></i></div><div><h5>₹ <?= indianCurrencyFormat($balance['balance']) ?></h5><small>Available Balance</small></div></div></div>
</div>

<?php if(isset($_SESSION['new_pass_display'])): ?>
    <div class="alert alert-success mt-3"><?= $_SESSION['new_pass_display']; unset($_SESSION['new_pass_display']); ?></div>
<?php endif; ?>

<!-- ====== MANAGE USERS - ONLY SUPER ADMIN ====== -->
<?php if($is_super_admin): ?>
<div id="users-section" class="mt-4">
    <div class="card-premium">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h4 class="mb-0">👥 Manage Users</h4>
            <form method="GET" class="d-flex flex-wrap gap-2">
                <input type="text" name="user_search" class="form-control form-control-sm" style="min-width:200px;" placeholder="🔍 Search by name or email..." value="<?= htmlspecialchars($user_search) ?>">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> Search</button>
                <?php if(!empty($user_search)): ?>
                    <a href="admin_dashboard.php" class="btn btn-sm btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Referred By</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php 
                $sql_users = "SELECT u.id, u.name, u.email, u.phone, u.referred_by, u.role, u.status, r.name as referrer_name FROM users u LEFT JOIN users r ON u.referred_by = r.id";
                if(!empty($user_search)) {
                    $sql_users .= " WHERE u.name ILIKE ? OR u.email ILIKE ?";
                    $stmt = $pdo->prepare($sql_users . " ORDER BY u.id DESC");
                    $stmt->execute(['%'.$user_search.'%', '%'.$user_search.'%']);
                } else {
                    $stmt = $pdo->query($sql_users . " ORDER BY u.id DESC");
                }
                $users = $stmt->fetchAll();

                if(count($users) > 0) {
                    foreach($users as $u) { 
                        $is_self = ($u['id'] == $_SESSION['user_id']);
                        $current_referrer = $u['referrer_name'] ?? 'Direct';
                        $status_badge = ($u['status'] == 'active') ? 'success' : 'secondary';
                        $status_text = ucfirst($u['status']);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= $u['email'] ?></td>
                        <td><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></td>
                        <td><?= ($u['referrer_name'] ? htmlspecialchars($u['referrer_name']) : '<span class="text-muted">Direct</span>') ?></td>
                        <td><span class='badge bg-<?= ($u['role']=='admin'?'danger':'info') ?>'><?= $u['role'] ?></span></td>
                        <td>
                            <?php if(!$is_self && $is_super_admin): ?>
                                <!-- Toggle Switch for Status -->
                                <a href="?toggle_status=<?= $u['id'] ?>" class="status-toggle" title="Toggle Status">
                                    <span class="badge bg-<?= $status_badge ?> p-2" style="cursor:pointer; min-width:60px; display:inline-block; text-align:center;">
                                        <?= $status_text ?>
                                        <i class="fas fa-sync-alt ms-1"></i>
                                    </span>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-<?= $status_badge ?>"><?= $status_text ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!$is_self && $is_super_admin): ?>
                                <!-- Single Settings Button -->
                                <button class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#userSettingsModal"
                                        data-userid="<?= $u['id'] ?>"
                                        data-username="<?= htmlspecialchars($u['name']) ?>"
                                        data-useremail="<?= htmlspecialchars($u['email']) ?>"
                                        data-userstatus="<?= $u['status'] ?>"
                                        data-currentreferrer="<?= htmlspecialchars($current_referrer) ?>"
                                        onclick="populateModal(this)">
                                    <i class="fas fa-cog"></i> Settings
                                </button>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php 
                    }
                } else {
                    echo "<tr><td colspan='7' class='text-center text-muted'>No users found.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ====== USER SETTINGS MODAL ====== -->
<div class="modal fade" id="userSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #1e293b, #334155); color: white; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title"><i class="fas fa-user-cog me-2"></i>User Settings</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Hidden user ID -->
                <input type="hidden" id="modal_user_id" value="">

                <div class="row mb-4">
                    <div class="col-md-6">
                        <strong>Name:</strong> <span id="modal_user_name"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Email:</strong> <span id="modal_user_email"></span>
                    </div>
                </div>

                <div class="card mb-4 p-3 border-0 shadow-sm">
                    <h6><i class="fas fa-power-off me-2 text-warning"></i>Change Status</h6>
                    <p>
                        Current Status: <span id="modal_user_status_badge" class="badge"></span>
                    </p>
                    <a href="#" id="modal_toggle_link" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-sync-alt"></i> Toggle Status
                    </a>
                </div>

                <div class="card mb-4 p-3 border-0 shadow-sm">
                    <h6><i class="fas fa-key me-2 text-primary"></i>Set / Reset Password</h6>
                    <form method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="user_id" id="modal_password_user_id" value="">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <input type="text" name="new_password" class="form-control" placeholder="Enter new password (min 4 chars)" required>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="set_password" class="btn btn-primary w-100">Set Password</button>
                            </div>
                        </div>
                    </form>
                    <div class="mt-2">
                        <a href="#" id="modal_reset_link" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-sync-alt"></i> Generate Random Password & Show
                        </a>
                    </div>
                </div>

                <div class="card mb-4 p-3 border-0 shadow-sm">
                    <h6><i class="fas fa-link me-2 text-success"></i>Change Referrer</h6>
                    <p><strong>Current Referrer:</strong> <span id="modal_current_referrer"></span></p>
                    <form method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="user_id" id="modal_referrer_user_id" value="">
                        <div class="row g-2">
                            <div class="col-md-8">
                                <select name="new_referrer_id" class="form-control" required>
                                    <option value="">None (Direct)</option>
                                    <?php 
                                    $all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
                                    foreach($all_users as $usr) {
                                        echo "<option value='".$usr['id']."'>".htmlspecialchars($usr['name'])." (".htmlspecialchars($usr['email']).")</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" name="change_referrer" class="btn btn-success w-100">Update</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card p-3 border-0 shadow-sm" style="border-left: 4px solid #dc3545 !important;">
                    <h6 class="text-danger"><i class="fas fa-trash-alt me-2"></i>Delete User</h6>
                    <form method="POST" action="admin_dashboard.php" onsubmit="return confirm('Are you sure you want to delete this user? This action is irreversible!')">
                        <input type="hidden" name="user_id" id="modal_delete_user_id" value="">
                        <button type="submit" name="delete_user" class="btn btn-danger">🗑️ Delete User</button>
                    </form>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Populate modal with user data when Settings button clicked
    function populateModal(btn) {
        var userId = btn.getAttribute('data-userid');
        var userName = btn.getAttribute('data-username');
        var userEmail = btn.getAttribute('data-useremail');
        var userStatus = btn.getAttribute('data-userstatus');
        var currentReferrer = btn.getAttribute('data-currentreferrer') || 'Direct';

        document.getElementById('modal_user_id').value = userId;
        document.getElementById('modal_user_name').innerText = userName;
        document.getElementById('modal_user_email').innerText = userEmail;

        // Status badge
        var badge = document.getElementById('modal_user_status_badge');
        var statusClass = (userStatus === 'active') ? 'bg-success' : 'bg-secondary';
        badge.className = 'badge ' + statusClass;
        badge.innerText = userStatus.charAt(0).toUpperCase() + userStatus.slice(1);

        // Toggle link
        var toggleLink = document.getElementById('modal_toggle_link');
        toggleLink.href = '?toggle_status=' + userId;

        // Set password form
        document.getElementById('modal_password_user_id').value = userId;

        // Reset link
        document.getElementById('modal_reset_link').href = '?reset_pass_show=' + userId;

        // Change referrer form
        document.getElementById('modal_referrer_user_id').value = userId;
        document.getElementById('modal_current_referrer').innerText = currentReferrer;

        // Delete form
        document.getElementById('modal_delete_user_id').value = userId;
    }

    // On modal close, clear any messages? (optional)
    document.getElementById('userSettingsModal').addEventListener('hidden.bs.modal', function () {
        // Optionally reset forms
    });
</script>

<!-- Extra style for toggle badge -->
<style>
    .status-toggle .badge {
        transition: all 0.2s ease;
    }
    .status-toggle .badge:hover {
        transform: scale(1.05);
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
</style>

<?php endif; ?>
<!-- ====== END SUPER ADMIN ONLY ====== -->

<?php include 'footer.php'; ?>
