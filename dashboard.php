<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Admin Actions
if($role == 'admin') {
    // Toggle Status
    if(isset($_GET['toggle_status'])) {
        $id = $_GET['toggle_status'];
        $pdo->prepare("UPDATE users SET status = CASE WHEN status='active' THEN 'inactive' ELSE 'active' END WHERE id = ?")->execute([$id]);
        header("Location: dashboard.php");
        exit;
    }
    // Delete User
    if(isset($_GET['delete_user'])) {
        $id = $_GET['delete_user'];
        if($id != $_SESSION['user_id']) { 
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        }
        header("Location: dashboard.php");
        exit;
    }
    // Reset & Show Password (Feature 1)
    if(isset($_GET['reset_pass_show'])) {
        $id = $_GET['reset_pass_show'];
        $new_pass = bin2hex(random_bytes(4)); 
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $id]);
        $_SESSION['new_pass_display'] = "✅ New Password for User ID $id is: <strong>$new_pass</strong>";
        header("Location: dashboard.php");
        exit;
    }
    // Manual Set Password (Feature 1)
    if(isset($_POST['set_password']) && isset($_POST['user_id'])) {
        $uid = $_POST['user_id'];
        $new_pass = $_POST['new_password'];
        if(strlen($new_pass) < 4) { $_SESSION['new_pass_display'] = "❌ Password must be at least 4 characters."; }
        else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $uid]);
            $_SESSION['new_pass_display'] = "✅ Password for User ID $uid set to: <strong>$new_pass</strong>";
        }
        header("Location: dashboard.php");
        exit;
    }
    // Change Referrer (Feature 3)
    if(isset($_POST['change_referrer']) && isset($_POST['user_id']) && isset($_POST['new_referrer_id'])) {
        $uid = $_POST['user_id'];
        $new_ref = $_POST['new_referrer_id'];
        if($uid != $new_ref) {
            $pdo->prepare("UPDATE users SET referred_by = ?, manual_referral_updated = TRUE WHERE id = ?")->execute([$new_ref ?: null, $uid]);
            $_SESSION['new_pass_display'] = "✅ Referrer updated successfully!";
        } else {
            $_SESSION['new_pass_display'] = "❌ Cannot refer to self!";
        }
        header("Location: dashboard.php");
        exit;
    }
}

include 'header.php'; 
$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$show_images = userHasActiveSubscription($pdo, $user_id);

if($role == 'admin'): 
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_sold = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'sold'")->fetchColumn();
    $balance = getAccountBalance($pdo);
?>
    <!-- Stats -->
    <div class="row g-4">
        <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-building"></i></div><div><h5><?= $total_props ?></h5><small>Properties</small></div></div></div>
        <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-users"></i></div><div><h5><?= $total_users ?></h5><small>Users</small></div></div></div>
        <div class="col-md-3"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-check-circle"></i></div><div><h5><?= $total_sold ?></h5><small>Sold</small></div></div></div>
        <div class="col-md-3"><div class="card-premium d-flex align-items-center" style="border-left:4px solid #2563eb;"><div class="stat-icon bg-soft-info me-3"><i class="fas fa-wallet"></i></div><div><h5>₹ <?= indianCurrencyFormat($balance['balance']) ?></h5><small>Balance</small></div></div></div>
    </div>

    <?php if(isset($_SESSION['new_pass_display'])): ?>
        <div class="alert alert-success mt-3"><?= $_SESSION['new_pass_display']; unset($_SESSION['new_pass_display']); ?></div>
    <?php endif; ?>

    <?php if(hasViewPermission('users', $pdo)): ?>
    <div id="users-section" class="mt-4">
        <div class="card-premium"><h4>👥 Manage Users</h4>
            <div class="table-responsive"><table class="table table-hover">
                <thead><tr><th>Name</th><th>Email</th><th>Referred By</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php 
                $users = $pdo->query("SELECT u.*, r.name as referrer_name FROM users u LEFT JOIN users r ON u.referred_by = r.id ORDER BY u.id DESC")->fetchAll();
                foreach($users as $u) { 
                    $is_self = ($u['id'] == $_SESSION['user_id']);
                    echo "<tr><td><a href='dashboard.php?view_user=".$u['id']."' target='_blank'>".htmlspecialchars($u['name'])."</a></td><td>".$u['email']."</td>";
                    echo "<td>".($u['referrer_name'] ? htmlspecialchars($u['referrer_name']) : '<span class="text-muted">Direct</span>')."</td>";
                    echo "<td><span class='badge bg-".($u['role']=='admin'?'danger':'info')."'>".$u['role']."</span></td>";
                    echo "<td><span class='badge bg-".($u['status']=='active'?'success':'secondary')."'>".$u['status']."</span></td>";
                    echo "<td>";
                    if(!$is_self && hasEditPermission('users', $pdo)) {
                        // Toggle
                        echo "<a href='?toggle_status=".$u['id']."' class='btn btn-sm btn-warning'>Toggle</a> ";
                        // Change Password (Modal or direct)
                        echo "<button class='btn btn-sm btn-info' onclick=\"document.getElementById('pass_user_id').value='".$u['id']."'; document.getElementById('passModal').style.display='block';\">Pass</button> ";
                        // Show/Reset Password
                        echo "<a href='?reset_pass_show=".$u['id']."' class='btn btn-sm btn-secondary' onclick='return confirm(\"Reset password and show new one?\")'>Show/Reset</a> ";
                        // Delete
                        echo "<a href='?delete_user=".$u['id']."' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete?\")'>Del</a> ";
                        // Change Referrer (Feature 3)
                        echo "<button class='btn btn-sm btn-primary' onclick=\"document.getElementById('ref_user_id').value='".$u['id']."'; document.getElementById('refModal').style.display='block';\">Ref</button>";
                    } else if($is_self) { echo "You"; } else { echo "View Only"; }
                    echo "</td></tr>";
                } ?>
                </tbody>
            </table></div>
        </div>
    </div>

    <!-- Modal: Change Password (Feature 1) -->
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

    <!-- Modal: Change Referrer (Feature 3) -->
    <div id="refModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; padding:30px; border-radius:20px; max-width:400px; width:90%;">
            <h5>Change Referrer</h5>
            <form method="POST">
                <input type="hidden" name="user_id" id="ref_user_id" value="">
                <div class="mb-3">
                    <label>New Referrer (User ID)</label>
                    <select name="new_referrer_id" class="form-control">
                        <option value="">None (Direct)</option>
                        <?php 
                        $all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
                        foreach($all_users as $usr) {
                            echo "<option value='".$usr['id']."'>".htmlspecialchars($usr['name'])." (ID:".$usr['id'].")</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="change_referrer" class="btn btn-primary">Update Referrer</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('refModal').style.display='none';">Close</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Subscription History (Feature 4) -->
    <div class="mt-4 card-premium">
        <h4>📋 Subscription History</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr><th>User</th><th>Package</th><th>Amount</th><th>Status</th><th>Payment Method</th><th>UTR</th><th>Request Date</th><th>Activation/Reject Date</th></tr></thead>
                <tbody>
                <?php 
                $subs = $pdo->query("SELECT s.*, u.name as uname, p.name as pkg_name FROM subscriptions s JOIN users u ON s.user_id = u.id JOIN packages p ON s.package_id = p.id ORDER BY s.created_at DESC LIMIT 50")->fetchAll();
                if(count($subs)>0) {
                    foreach($subs as $s) {
                        $status_badge = $s['status']=='active' ? 'success' : ($s['status']=='pending' ? 'warning' : 'danger');
                        echo "<tr><td>".htmlspecialchars($s['uname'])."</td><td>".htmlspecialchars($s['pkg_name'])."</td><td>₹".$s['amount']."</td>
                              <td><span class='badge bg-$status_badge'>".$s['status']."</span></td>
                              <td>".$s['payment_method']."</td><td>".htmlspecialchars($s['utr']??'N/A')."</td>
                              <td>".date('d M Y', strtotime($s['created_at']))."</td>
                              <td>".($s['start_date'] ? date('d M Y', strtotime($s['start_date'])) : '—')."</td></tr>";
                    }
                } else echo "<tr><td colspan='8' class='text-center'>No subscriptions found.</td></tr>";
                ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: 
    // ---- USER VIEW ----
    $user_stmt = $pdo->prepare("SELECT *, created_at as reg_date FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    $active_sub = $pdo->prepare("SELECT s.*, p.name as pkg_name, s.start_date, s.end_date, (s.end_date - CURRENT_DATE) as days_left FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE ORDER BY s.id DESC LIMIT 1");
    $active_sub->execute([$user_id]);
    $sub_info = $active_sub->fetch();
    $is_subscribed = $sub_info ? true : false;

    $reg_date_formatted = !empty($user['reg_date']) ? date('d M Y', strtotime($user['reg_date'])) : 'N/A';
    $activation_date_formatted = ($is_subscribed && !empty($sub_info['start_date'])) ? date('d M Y', strtotime($sub_info['start_date'])) : 'Not Active';
    $expiry_date_formatted = ($is_subscribed && !empty($sub_info['end_date'])) ? date('d M Y', strtotime($sub_info['end_date'])) : 'N/A';
    $days_left = $is_subscribed ? (int)$sub_info['days_left'] : 0;

    $referral_link = getReferralLink($user_id);
    $earnings = getReferralEarnings($pdo, $user_id, 'pending');
    $paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
    $total_pending = array_sum(array_column($earnings, 'amount'));
    $total_paid = array_sum(array_column($paid_earnings, 'net_amount'));
    $team_members = getReferredUsers($pdo, $user_id);

    // User Subscription History (Feature 4)
    $user_subs = $pdo->prepare("SELECT s.*, p.name as pkg_name FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
    $user_subs->execute([$user_id]);
    $user_subs = $user_subs->fetchAll();
?>
    <div class="user-welcome-banner">
        <div><h2>🏡 Welcome, <?= htmlspecialchars($user['name']) ?>!</h2><p>Find your dream property today.</p></div>
        <div><a href="index.php" class="btn btn-light text-success fw-bold">Explore All →</a></div>
    </div>

    <!-- Subscription Status -->
    <div class="card-premium mb-4" style="border-left: 5px solid <?= $is_subscribed ? '#10b981' : '#f59e0b' ?>;">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5><i class="fas fa-user-clock me-2"></i>My Subscription Status</h5>
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
                    <span class="badge bg-secondary p-2 fs-6">🔴 No Active Subscription</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Referral & Team -->
    <div class="card-premium mb-4" style="border:1px solid #10b981; background:#f0fdf4;">
        <h5><i class="fas fa-link me-2" style="color:#10b981;"></i>Your Referral Link</h5>
        <div class="input-group">
            <input type="text" class="form-control border-success" id="refLink" value="<?= $referral_link ?>" readonly>
            <button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
        </div>
        <div class="row mt-3">
            <div class="col-md-6">
                <span class="badge bg-warning text-dark">⏳ Pending: ₹ <?= indianCurrencyFormat($total_pending) ?></span>
                <span class="badge bg-success ms-2">✅ Paid: ₹ <?= indianCurrencyFormat($total_paid) ?></span>
            </div>
            <div class="col-md-6 text-md-end">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#teamSection">👥 View My Team</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#subHistoryUser">📜 Subscription History</button>
            </div>
        </div>
        
        <!-- Team Section (Feature 2 & 3) -->
        <div class="collapse mt-3" id="teamSection">
            <h6>My Team (Referred Users)</h6>
            <?php if(count($team_members) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Name</th><th>Email</th><th>Registered On</th><th>Activation Date</th></tr></thead>
                        <tbody>
                        <?php foreach($team_members as $tm): ?>
                            <tr>
                                <td><?= htmlspecialchars($tm['name']) ?></td>
                                <td><?= htmlspecialchars($tm['email']) ?></td>
                                <td><?= date('d M Y', strtotime($tm['reg_date'])) ?></td>
                                <td><?= $tm['activation_date'] ? date('d M Y', strtotime($tm['activation_date'])) : '<span class="text-muted">Not Activated</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">You haven't referred anyone yet.</p>
            <?php endif; ?>
        </div>

        <!-- Subscription History (Feature 4) -->
        <div class="collapse mt-3" id="subHistoryUser">
            <h6>Your Subscription Requests</h6>
            <?php if(count($user_subs) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Package</th><th>Amount</th><th>Status</th><th>Payment Method</th><th>Request Date</th><th>Activation/Reject Date</th></tr></thead>
                        <tbody>
                        <?php foreach($user_subs as $us): ?>
                            <tr>
                                <td><?= htmlspecialchars($us['pkg_name']) ?></td>
                                <td>₹<?= $us['amount'] ?></td>
                                <td><span class="badge bg-<?= $us['status']=='active'?'success':($us['status']=='pending'?'warning':'danger') ?>"><?= $us['status'] ?></span></td>
                                <td><?= $us['payment_method'] ?></td>
                                <td><?= date('d M Y', strtotime($us['created_at'])) ?></td>
                                <td><?= $us['start_date'] ? date('d M Y', strtotime($us['start_date'])) : ($us['status']=='rejected' ? 'Rejected' : '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: echo "<p class='text-muted'>No subscription requests yet.</p>"; endif; ?>
        </div>
    </div>

    <!-- Packages Section (Keep your existing) -->
    <div id="packages" class="card-premium mb-4" style="border:2px solid #fbbf24; background:#fffbeb;">
        <!-- Copy your existing packages code here or leave it as is -->
        <h4><i class="fas fa-search-dollar me-2" style="color: #f59e0b;"></i>Buy Search Engine Access</h4>
        <p class="text-muted">Subscribe to view full details of all auction properties.</p>
        <!-- ... your package cards ... -->
    </div>

    <script>
        function copyRef() { let inp = document.getElementById('refLink'); inp.select(); navigator.clipboard.writeText(inp.value).then(() => alert('Copied!')).catch(() => document.execCommand('copy')); }
    </script>

<?php endif; ?>
<?php include 'footer.php'; ?>
