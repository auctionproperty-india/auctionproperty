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

    // ---- UPDATE User Details (Name, Email, Phone) ----
    if(isset($_POST['update_user_details']) && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['new_pass_display'] = "❌ Invalid email format.";
            header("Location: admin_dashboard.php");
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$email, $uid]);
        if($check->rowCount() > 0) {
            $_SESSION['new_pass_display'] = "❌ Email already used by another user.";
            header("Location: admin_dashboard.php");
            exit;
        }

        $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?")->execute([$name, $email, $phone, $uid]);
        $_SESSION['new_pass_display'] = "✅ User details updated successfully!";
        header("Location: admin_dashboard.php");
        exit;
    }

    // ---- UPDATE Registration, Activation & End Dates ----
    if(isset($_POST['update_dates']) && isset($_POST['user_id'])) {
        $uid = (int)$_POST['user_id'];
        $new_reg_date = $_POST['reg_date'] ?? null;
        $new_act_date = $_POST['activation_date'] ?? null;
        $new_end_date = $_POST['end_date'] ?? null;

        if(!empty($new_reg_date)) {
            $reg_datetime = date('Y-m-d H:i:s', strtotime($new_reg_date));
            $pdo->prepare("UPDATE users SET created_at = ? WHERE id = ?")->execute([$reg_datetime, $uid]);
        }
        if(!empty($new_act_date)) {
            $act_datetime = date('Y-m-d', strtotime($new_act_date));
            $sub_id = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE ORDER BY id DESC LIMIT 1");
            $sub_id->execute([$uid]);
            $sid = $sub_id->fetchColumn();
            if($sid) {
                $pdo->prepare("UPDATE subscriptions SET start_date = ? WHERE id = ?")->execute([$act_datetime, $sid]);
            }
        }
        if(!empty($new_end_date)) {
            $end_datetime = date('Y-m-d', strtotime($new_end_date));
            $sub_id = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE ORDER BY id DESC LIMIT 1");
            $sub_id->execute([$uid]);
            $sid = $sub_id->fetchColumn();
            if($sid) {
                $pdo->prepare("UPDATE subscriptions SET end_date = ? WHERE id = ?")->execute([$end_datetime, $sid]);
            }
        }
        $_SESSION['new_pass_display'] = "✅ Registration, Activation & End dates updated!";
        header("Location: admin_dashboard.php");
        exit;
    }
}

// ---- Handle GET actions ----
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
$total_coins = $pdo->query("SELECT SUM(coins) FROM users")->fetchColumn();
$user_search = $_GET['user_search'] ?? '';
?>
<!-- Stats -->
<div class="row g-4">
    <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-building"></i></div><div><h5><?= $total_props ?></h5><small>Properties</small></div></div></div>
    <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-users"></i></div><div><h5><?= $total_users ?></h5><small>Total Users</small></div></div></div>
    <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-check-circle"></i></div><div><h5><?= $total_sold ?></h5><small>Sold</small></div></div></div>
    <div class="col-md-3"><div class="card-premium d-flex align-items-center" style="border-left:4px solid #2563eb;"><div class="stat-icon bg-soft-info me-3"><i class="fas fa-wallet"></i></div><div><h5>₹ <?= indianCurrencyFormat($balance['balance']) ?></h5><small>Available Balance</small></div></div></div>
    <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-coins"></i></div><div><h5><?= (int)$total_coins ?></h5><small>Total Coins</small></div></div></div>
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
                <thead><tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Referred By</th>
                    <th>Role</th>
                    <th>Plan</th>
                    <th>Coins</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr></thead>
                <tbody>
                <?php 
                // Modified query to sort active subscribers first (package_name NOT NULL)
                $sql_users = "SELECT 
                                u.id, u.name, u.email, u.phone, u.referred_by, u.role, u.status, u.coins,
                                u.created_at as reg_date,
                                r.name as referrer_name,
                                p.name as package_name,
                                s.start_date as sub_start,
                                s.end_date as sub_end,
                                CASE WHEN p.name IS NOT NULL THEN 1 ELSE 0 END as is_active_sub
                              FROM users u 
                              LEFT JOIN users r ON u.referred_by = r.id
                              LEFT JOIN (
                                  SELECT DISTINCT ON (user_id) user_id, package_id, start_date, end_date
                                  FROM subscriptions
                                  WHERE status = 'active' AND end_date >= CURRENT_DATE
                                  ORDER BY user_id, id DESC
                              ) s ON u.id = s.user_id
                              LEFT JOIN packages p ON s.package_id = p.id";
                
                if(!empty($user_search)) {
                    $sql_users .= " WHERE u.name ILIKE ? OR u.email ILIKE ?";
                    $stmt = $pdo->prepare($sql_users . " ORDER BY is_active_sub DESC, u.id DESC");
                    $stmt->execute(['%'.$user_search.'%', '%'.$user_search.'%']);
                } else {
                    $stmt = $pdo->query($sql_users . " ORDER BY is_active_sub DESC, u.id DESC");
                }
                $users = $stmt->fetchAll();

                if(count($users) > 0) {
                    foreach($users as $u) { 
                        $is_self = ($u['id'] == $_SESSION['user_id']);
                        $current_referrer = $u['referrer_name'] ?? 'Direct';
                        $status_badge = ($u['status'] == 'active') ? 'success' : 'secondary';
                        $status_text = ucfirst($u['status']);
                        $plan_display = ($u['package_name']) ? htmlspecialchars($u['package_name']) : 'Free';
                        // Dates for modal (datetime-local format)
                        $reg_date = date('Y-m-d\TH:i', strtotime($u['reg_date']));
                        $act_date = !empty($u['sub_start']) ? date('Y-m-d\TH:i', strtotime($u['sub_start'])) : '';
                        $end_date = !empty($u['sub_end']) ? date('Y-m-d\TH:i', strtotime($u['sub_end'])) : '';
                        // Green tick if active subscription
                        $active_badge = $u['is_active_sub'] ? ' ✅' : '';
                ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($u['name']) ?>
                            <?php if($u['is_active_sub']): ?>
                                <span class="badge bg-success" title="Active Subscriber"><i class="fas fa-check-circle"></i></span>
                            <?php endif; ?>
                        </td>
                        <td><?= $u['email'] ?></td>
                        <td><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></td>
                        <td><?= ($u['referrer_name'] ? htmlspecialchars($u['referrer_name']) : '<span class="text-muted">Direct</span>') ?></td>
                        <td><span class='badge bg-<?= ($u['role']=='admin'?'danger':'info') ?>'><?= $u['role'] ?></span></td>
                        <td>
                            <?php if($u['package_name']): ?>
                                <span class="badge bg-success"><?= $plan_display ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Free</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= (int)($u['coins'] ?? 0) ?></strong></td>
                        <td>
                            <?php if(!$is_self && $is_super_admin): ?>
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
                                <button class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#userSettingsModal"
                                        data-userid="<?= $u['id'] ?>"
                                        data-username="<?= htmlspecialchars($u['name']) ?>"
                                        data-useremail="<?= htmlspecialchars($u['email']) ?>"
                                        data-userphone="<?= htmlspecialchars($u['phone'] ?? '') ?>"
                                        data-userstatus="<?= $u['status'] ?>"
                                        data-currentreferrer="<?= htmlspecialchars($current_referrer) ?>"
                                        data-regdate="<?= $reg_date ?>"
                                        data-actdate="<?= $act_date ?>"
                                        data-enddate="<?= $end_date ?>"
                                        onclick="populateModal(this)">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <a href="admin_user_detail.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-info" title="View Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php 
                    }
                } else {
                    echo "<tr><td colspan='9' class='text-center text-muted'>No users found.</td></tr>";
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
                <input type="hidden" id="modal_user_id" value="">

                <div class="row mb-4">
                    <div class="col-md-6">
                        <strong>Name:</strong> <span id="modal_user_name"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Email:</strong> <span id="modal_user_email"></span>
                    </div>
                </div>

                <!-- ===== EDIT USER DETAILS (Name, Email, Phone) ===== -->
                <div class="card mb-4 p-3 border-0 shadow-sm" style="border-left:4px solid #3b82f6;">
                    <h6><i class="fas fa-user-edit me-2 text-primary"></i>Edit User Details</h6>
                    <form method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="user_id" id="modal_details_user_id" value="">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Name</label>
                                <input type="text" name="name" id="modal_edit_name" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Email</label>
                                <input type="email" name="email" id="modal_edit_email" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Phone</label>
                                <input type="text" name="phone" id="modal_edit_phone" class="form-control form-control-sm">
                            </div>
                        </div>
                        <button type="submit" name="update_user_details" class="btn btn-sm btn-primary mt-2">Save Details</button>
                    </form>
                </div>

                <!-- ===== EDIT DATES ===== -->
                <div class="card mb-4 p-3 border-0 shadow-sm" style="border-left:4px solid #8b5cf6;">
                    <h6><i class="fas fa-calendar-alt me-2 text-primary"></i>Edit Registration & Subscription Dates</h6>
                    <form method="POST" action="admin_dashboard.php">
                        <input type="hidden" name="user_id" id="modal_dates_user_id" value="">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small">Registration Date</label>
                                <input type="datetime-local" name="reg_date" id="modal_reg_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Activation Date</label>
                                <input type="datetime-local" name="activation_date" id="modal_act_date" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">End Date</label>
                                <input type="datetime-local" name="end_date" id="modal_end_date" class="form-control form-control-sm">
                            </div>
                        </div>
                        <button type="submit" name="update_dates" class="btn btn-sm btn-primary mt-2">Update Dates</button>
                    </form>
                </div>

                <!-- ===== CHANGE STATUS ===== -->
                <div class="card mb-4 p-3 border-0 shadow-sm">
                    <h6><i class="fas fa-power-off me-2 text-warning"></i>Change Status</h6>
                    <p>
                        Current Status: <span id="modal_user_status_badge" class="badge"></span>
                    </p>
                    <a href="#" id="modal_toggle_link" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-sync-alt"></i> Toggle Status
                    </a>
                </div>

                <!-- ===== SET PASSWORD ===== -->
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

                <!-- ===== CHANGE REFERRER ===== -->
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

                <!-- ===== DELETE USER ===== -->
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
    function populateModal(btn) {
        var userId = btn.getAttribute('data-userid');
        var userName = btn.getAttribute('data-username');
        var userEmail = btn.getAttribute('data-useremail');
        var userPhone = btn.getAttribute('data-userphone') || '';
        var userStatus = btn.getAttribute('data-userstatus');
        var currentReferrer = btn.getAttribute('data-currentreferrer') || 'Direct';
        var regDate = btn.getAttribute('data-regdate') || '';
        var actDate = btn.getAttribute('data-actdate') || '';
        var endDate = btn.getAttribute('data-enddate') || '';

        document.getElementById('modal_user_id').value = userId;
        document.getElementById('modal_user_name').innerText = userName;
        document.getElementById('modal_user_email').innerText = userEmail;

        // Populate edit details form
        document.getElementById('modal_details_user_id').value = userId;
        document.getElementById('modal_edit_name').value = userName;
        document.getElementById('modal_edit_email').value = userEmail;
        document.getElementById('modal_edit_phone').value = userPhone;

        // Dates
        document.getElementById('modal_dates_user_id').value = userId;
        document.getElementById('modal_reg_date').value = regDate;
        document.getElementById('modal_act_date').value = actDate;
        document.getElementById('modal_end_date').value = endDate;

        var badge = document.getElementById('modal_user_status_badge');
        var statusClass = (userStatus === 'active') ? 'bg-success' : 'bg-secondary';
        badge.className = 'badge ' + statusClass;
        badge.innerText = userStatus.charAt(0).toUpperCase() + userStatus.slice(1);

        document.getElementById('modal_toggle_link').href = '?toggle_status=' + userId;
        document.getElementById('modal_password_user_id').value = userId;
        document.getElementById('modal_reset_link').href = '?reset_pass_show=' + userId;
        document.getElementById('modal_referrer_user_id').value = userId;
        document.getElementById('modal_current_referrer').innerText = currentReferrer;
        document.getElementById('modal_delete_user_id').value = userId;
    }

    document.getElementById('userSettingsModal').addEventListener('hidden.bs.modal', function () {});
</script>

<style>
    .status-toggle .badge { transition: all 0.2s ease; }
    .status-toggle .badge:hover { transform: scale(1.05); box-shadow: 0 0 10px rgba(0,0,0,0.1); }
</style>

<?php endif; ?>
<!-- ====== END SUPER ADMIN ONLY ====== -->

<?php include 'footer.php'; ?>
