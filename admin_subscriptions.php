<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
if(!hasViewPermission('subscriptions', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page.</div>");
}

// ---- Approve (Activate) with amount edit ----
if(isset($_POST['activate_sub']) && isset($_POST['sub_id']) && isset($_POST['amount'])) {
    if(!hasEditPermission('subscriptions', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to approve subscriptions.</div>");
    }
    $sub_id = (int)$_POST['sub_id'];
    $amount = (float)$_POST['amount'];
    if($amount <= 0) {
        die("Invalid amount");
    }

    // Fetch subscription data
    $sub = $pdo->prepare("SELECT s.*, p.duration_months, p.referral_bonus, u.referred_by, u.id as user_id FROM subscriptions s 
                          JOIN packages p ON s.package_id = p.id 
                          JOIN users u ON s.user_id = u.id 
                          WHERE s.id = ?");
    $sub->execute([$sub_id]);
    $data = $sub->fetch();
    if($data) {
        // Update the subscription amount to the edited amount
        $pdo->prepare("UPDATE subscriptions SET amount = ? WHERE id = ?")->execute([$amount, $sub_id]);

        $end = date('Y-m-d', strtotime("+{$data['duration_months']} months"));
        $pdo->prepare("UPDATE subscriptions SET status = 'active', start_date = CURRENT_DATE, end_date = ? WHERE id = ?")->execute([$end, $sub_id]);

        // ---- Referral Bonus ----
        if($data['referred_by'] && $data['referral_bonus'] > 0) {
            $check = $pdo->prepare("SELECT id FROM user_referral_earnings WHERE user_id = ? AND referred_user_id = ? AND package_id = ?");
            $check->execute([$data['referred_by'], $data['user_id'], $data['package_id']]);
            if($check->rowCount() == 0) {
                $pdo->prepare("INSERT INTO user_referral_earnings (user_id, referred_user_id, package_id, amount, status) VALUES (?, ?, ?, ?, 'pending')")
                    ->execute([$data['referred_by'], $data['user_id'], $data['package_id'], $data['referral_bonus']]);
            }
        }

        // ---- Add Income to Accounting with the edited amount ----
        addAccountEntry($pdo, 'income', $amount, 'Subscription payment from user ID '.$data['user_id'].' for package '.$data['package_id'].' (edited amount)', 'Subscription');

        header("Location: admin_subscriptions.php?done=1");
        exit;
    } else {
        die("Subscription not found");
    }
}

// ---- Reject ----
if(isset($_GET['reject'])) {
    if(!hasEditPermission('subscriptions', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to reject subscriptions.</div>");
    }
    $sub_id = $_GET['reject'];
    $pdo->prepare("UPDATE subscriptions SET status = 'rejected' WHERE id = ?")->execute([$sub_id]);
    header("Location: admin_subscriptions.php?done=2");
    exit;
}

include 'header.php'; 
$pendings = $pdo->query("SELECT s.*, u.name as uname, p.title as ptitle, pk.name as pkg_name 
                        FROM subscriptions s 
                        JOIN users u ON s.user_id = u.id 
                        LEFT JOIN properties p ON s.property_id = p.id 
                        JOIN packages pk ON s.package_id = pk.id 
                        WHERE s.status = 'pending' ORDER BY s.id DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-clock me-2"></i>Pending Subscriptions</h4>
    <?php if(isset($_GET['done'])): 
        if($_GET['done'] == 1) echo "<div class='alert alert-success'>✅ Activated with edited amount! Income added to accounting.</div>";
        if($_GET['done'] == 2) echo "<div class='alert alert-warning'>⛔ Rejected!</div>";
    endif; ?>
    <?php if(count($pendings) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>User</th><th>Package</th><th>Amount</th><th>UTR</th><th>Slip</th>
                <?php if(hasEditPermission('subscriptions', $pdo)): ?><th>Actions</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach($pendings as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['uname']) ?></td>
                        <td><?= htmlspecialchars($p['pkg_name']) ?></td>
                        <td>₹<?= $p['amount'] ?></td>
                        <td><?= htmlspecialchars($p['utr'] ?? 'N/A') ?></td>
                        <td>
                            <?php if(!empty($p['slip_path']) && file_exists($p['slip_path'])): ?>
                                <a href="<?= $p['slip_path'] ?>" target="_blank" class="btn btn-sm btn-info">📷 View</a>
                            <?php else: ?>
                                <span class="text-muted">No slip</span>
                            <?php endif; ?>
                        </td>
                        <?php if(hasEditPermission('subscriptions', $pdo)): ?>
                        <td>
                            <!-- Approve Form with Amount Edit -->
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="sub_id" value="<?= $p['id'] ?>">
                                <div class="input-group input-group-sm" style="min-width:200px;">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" name="amount" class="form-control" value="<?= $p['amount'] ?>" required>
                                    <button type="submit" name="activate_sub" class="btn btn-success" onclick="return confirm('Activate this subscription with this amount? Income will be added to accounting.')">✅ Approve</button>
                                </div>
                            </form>
                            <a href="?reject=<?= $p['id'] ?>" class="btn btn-sm btn-danger mt-1" onclick="return confirm('Reject this subscription?')">❌ Reject</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No pending requests.</p>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
