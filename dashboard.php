<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

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
    // New: Reset Password and Show Temp Password
    if(isset($_GET['reset_pass_show'])) {
        $id = $_GET['reset_pass_show'];
        $new_pass = bin2hex(random_bytes(4)); 
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $id]);
        $_SESSION['new_pass_display'] = "✅ New Password for User ID $id is: <strong>$new_pass</strong>";
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
?>
    <div class="row g-4">
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-building"></i></div><div><h5><?= $total_props ?></h5><small>Total Properties</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-users"></i></div><div><h5><?= $total_users ?></h5><small>Total Users</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-check-circle"></i></div><div><h5><?= $total_sold ?></h5><small>Sold</small></div></div></div>
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
                    if(!$is_self && hasEditPermission('users', $pdo)) {
                        echo "<td>
                            <a href='?toggle_status=".$u['id']."' class='btn btn-sm btn-warning'>Toggle</a>
                            <a href='change_password.php?user_id=".$u['id']."' class='btn btn-sm btn-info'>Pass</a>
                            <a href='?reset_pass_show=".$u['id']."' class='btn btn-sm btn-secondary' onclick='return confirm(\"Reset password and show new one?\")'>Show/Reset</a>
                            <a href='?delete_user=".$u['id']."' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete?\")'>Del</a>
                        </td>";
                    } else if($is_self) { echo "<td>You</td>"; } else { echo "<td>View Only</td>"; }
                    echo "</tr>";
                } ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <?php endif; ?>

<?php else: 
    // ---- USER VIEW ----
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

    // Referral Data
    $referral_link = getReferralLink($user_id);
    $earnings = getReferralEarnings($pdo, $user_id, 'pending');
    $paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
    $total_pending = array_sum(array_column($earnings, 'amount'));
    $total_paid = array_sum(array_column($paid_earnings, 'net_amount'));
    
    // Team Data (Referred Users)
    $team_members = getReferredUsers($pdo, $user_id);
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
                        <span class="badge bg-warning text-dark p-2 fs-5 w-100 w-md-auto">⏳ <?= $days_left ?> Days Remaining</span>
                    </div>
                <?php else: ?>
                    <span class="badge bg-secondary p-2 fs-6 w-100 w-md-auto">🔴 No Active Subscription</span>
                    <div class="mt-2 text-muted">Buy a plan to unlock full details.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== REFERRAL LINK & TEAM ===== -->
    <div class="card-premium mb-4" style="border: 1px solid #10b981; background: #f0fdf4;">
        <h5><i class="fas fa-link me-2" style="color: #10b981;"></i>Your Referral Link</h5>
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
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#referralHistory">📜 History</button>
            </div>
        </div>
        
        <!-- Team Section -->
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

        <!-- History Section -->
        <div class="collapse mt-3" id="referralHistory">
            <h6>Earning History</h6>
            <?php if(count($earnings) > 0 || count($paid_earnings) > 0): ?>
                <table class="table table-sm table-bordered">
                    <thead><tr><th>Referred User</th><th>Package</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach(array_merge($earnings, $paid_earnings) as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['referred_name']) ?></td>
                            <td><?= htmlspecialchars($e['package_name']) ?></td>
                            <td>₹<?= indianCurrencyFormat($e['amount']) ?></td>
                            <td><span class="badge bg-<?= $e['status']=='paid' ? 'success' : 'warning' ?>"><?= $e['status'] ?></span></td>
                            <td><?= date('d M Y', strtotime($e['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No earnings yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== BUY SEARCH ENGINE ===== -->
    <div id="packages" class="card-premium mb-4" style="border: 2px solid #fbbf24; background: #fffbeb;">
        <!-- ... Your existing packages code ... -->
        <!-- (Copy from your current dashboard.php, keeping it unchanged) -->
    </div>

    <script>
        function copyRef() {
            let inp = document.getElementById('refLink');
            inp.select(); navigator.clipboard.writeText(inp.value).then(() => alert('Referral Link Copied!')).catch(() => document.execCommand('copy'));
        }
    </script>

<?php endif; ?>
<?php include 'footer.php'; ?>
