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

// ---- Admin Actions ----
if(isset($_GET['toggle_status'])) {
    if(!$is_super_admin) { die("Access Denied"); }
    $id = $_GET['toggle_status'];
    $pdo->prepare("UPDATE users SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = ?")->execute([$id]);
    header("Location: admin_dashboard.php");
    exit;
}
if(isset($_GET['delete_user'])) {
    if(!$is_super_admin) { die("Access Denied"); }
    $id = $_GET['delete_user'];
    if($id != $_SESSION['user_id']) { 
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    }
    header("Location: admin_dashboard.php");
    exit;
}
if(isset($_GET['reset_pass_show'])) {
    if(!$is_super_admin) { die("Access Denied"); }
    $id = $_GET['reset_pass_show'];
    $new_pass = bin2hex(random_bytes(4)); 
    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $id]);
    $_SESSION['new_pass_display'] = "✅ New Password for User ID $id is: <strong>$new_pass</strong>";
    header("Location: admin_dashboard.php");
    exit;
}
if(isset($_POST['set_password']) && isset($_POST['user_id'])) {
    if(!$is_super_admin) { die("Access Denied"); }
    $uid = $_POST['user_id'];
    $new_pass = $_POST['new_password'];
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
if(isset($_POST['change_referrer']) && isset($_POST['user_id']) && isset($_POST['new_referrer_id'])) {
    if(!$is_super_admin) { die("Access Denied"); }
    $uid = $_POST['user_id'];
    $new_ref = $_POST['new_referrer_id'];
    if($uid != $new_ref) {
        $pdo->prepare("UPDATE users SET referred_by = ?, manual_referral_updated = TRUE WHERE id = ?")->execute([$new_ref ?: null, $uid]);
        $_SESSION['new_pass_display'] = "✅ Referrer updated successfully!";
    } else {
        $_SESSION['new_pass_display'] = "❌ Cannot refer to self!";
    }
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
                        echo "<tr>
                            <td><a href='admin_dashboard.php?view_user=".$u['id']."' target='_blank'>".htmlspecialchars($u['name'])."</a></td>
                            <td>".$u['email']."</td>
                            <td>".htmlspecialchars($u['phone'] ?? 'N/A')."</td>
                            <td>".($u['referrer_name'] ? htmlspecialchars($u['referrer_name']) : '<span class="text-muted">Direct</span>')."</td>
                            <td><span class='badge bg-".($u['role']=='admin'?'danger':'info')."'>".$u['role']."</span></td>
                            <td><span class='badge bg-".($u['status']=='active'?'success':'secondary')."'>".$u['status']."</span></td>";
                        
                        if(!$is_self && $is_super_admin) {
                            echo "<td>
                                <a href='?toggle_status=".$u['id']."' class='btn btn-sm btn-warning'>Toggle</a>
                                <button class='btn btn-sm btn-info' onclick=\"document.getElementById('pass_user_id').value='".$u['id']."'; document.getElementById('passModal').style.display='block';\">Pass</button>
                                <a href='?reset_pass_show=".$u['id']."' class='btn btn-sm btn-secondary' onclick='return confirm(\"Reset password and show new one?\")'>Show/Reset</a>
                                <button class='btn btn-sm btn-primary' onclick=\"openRefModal('".$u['id']."', '".addslashes($current_referrer)."')\">Ref</button>
                                <a href='?delete_user=".$u['id']."' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete?\")'>Del</a>
                            </td>";
                        } else if($is_self) { echo "<td><span class='text-muted'>You</span></td>"; } 
                        else { echo "<td><span class='text-muted'>View Only (Sub-Admin)</span></td>"; }
                        echo "</tr>";
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

<!-- Modals -->
<div id="passModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:30px; border-radius:20px; max-width:400px; width:90%;">
        <h5>Set New Password</h5>
        <form method="POST">
            <input type="hidden" name="user_id" id="pass_user_id" value="">
            <div class="mb-3"><label>New Password</label><input type="text" name="new_password" class="form-control" required minlength="4"></div>
            <button type="submit" name="set_password" class="btn btn-primary">Save Password</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('passModal').style.display='none';">Close</button>
        </form>
    </div>
</div>

<div id="refModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:30px; border-radius:20px; max-width:500px; width:95%; max-height:90vh; overflow-y:auto;">
        <h5>🔄 Change Referrer</h5>
        <hr>
        <p id="currentReferrerDisplay"><strong>Current Referrer:</strong> <span id="currentRefName" class="fw-bold text-primary">Loading...</span></p>
        <form method="POST">
            <input type="hidden" name="user_id" id="ref_user_id" value="">
            <div class="mb-3">
                <label class="fw-bold">Search & Select New Referrer</label>
                <input type="text" id="refSearchInput" class="form-control mb-2" placeholder="🔍 Type name or email to filter..." onkeyup="filterRefOptions()">
                <select name="new_referrer_id" id="refSelect" class="form-control" size="6" required>
                    <option value="">None (Direct)</option>
                    <?php 
                    $all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
                    foreach($all_users as $usr) {
                        echo "<option value='".$usr['id']."'>".htmlspecialchars($usr['name'])." (".htmlspecialchars($usr['email']).")</option>";
                    }
                    ?>
                </select>
                <small class="text-muted">Select a user to be the new referrer.</small>
            </div>
            <button type="submit" name="change_referrer" class="btn btn-primary w-100">Update Referrer</button>
            <button type="button" class="btn btn-secondary w-100 mt-2" onclick="document.getElementById('refModal').style.display='none';">Cancel</button>
        </form>
    </div>
</div>

<script>
    function openRefModal(userId, currentReferrerName) {
        document.getElementById('ref_user_id').value = userId;
        document.getElementById('currentRefName').textContent = currentReferrerName || 'Direct';
        document.getElementById('refSearchInput').value = '';
        document.getElementById('refModal').style.display = 'flex';
        filterRefOptions();
    }
    function filterRefOptions() {
        var input = document.getElementById('refSearchInput');
        var filter = input.value.toUpperCase();
        var select = document.getElementById('refSelect');
        var options = select.options;
        for (var i = 0; i < options.length; i++) {
            var txt = options[i].textContent || options[i].innerText;
            if (txt.toUpperCase().indexOf(filter) > -1) {
                options[i].style.display = "";
            } else {
                options[i].style.display = "none";
            }
        }
    }
</script>
<?php endif; ?>
<!-- ====== END SUPER ADMIN ONLY ====== -->

<?php include 'footer.php'; ?>
