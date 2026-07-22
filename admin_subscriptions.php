<?php
// ============================================================
// 📋 Admin – Pending Subscriptions (Fixed)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}
if (!hasViewPermission('subscriptions', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page.</div>");
}

// ---- Approve with full edit ----
if (isset($_POST['activate_sub']) && isset($_POST['sub_id'])) {
    if (!hasEditPermission('subscriptions', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to approve subscriptions.</div>");
    }

    $sub_id = (int)$_POST['sub_id'];
    $amount = (float)$_POST['amount'];
    $package_id = (int)$_POST['package_id'];
    $start_date = $_POST['start_date'];

    if ($amount <= 0 || !$package_id || empty($start_date)) {
        die("All fields are required.");
    }

    // Fetch subscription data
    $sub_stmt = $pdo->prepare("SELECT s.*, p.duration_months, p.referral_bonus, u.referred_by, u.id as user_id 
                               FROM subscriptions s 
                               JOIN packages p ON s.package_id = p.id 
                               JOIN users u ON s.user_id = u.id 
                               WHERE s.id = ?");
    $sub_stmt->execute([$sub_id]);
    $data = $sub_stmt->fetch();

    if (!$data) {
        die("Subscription not found.");
    }

    // If already active, redirect
    if ($data['status'] == 'active') {
        header("Location: admin_subscriptions.php?msg=already_active");
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();
    try {
        // 1. Update subscription amount, package, start_date
        $pdo->prepare("UPDATE subscriptions SET amount = ?, package_id = ?, start_date = ? WHERE id = ?")
            ->execute([$amount, $package_id, $start_date, $sub_id]);

        // 2. Calculate end date based on new package
        $pkg_duration = $pdo->prepare("SELECT duration_months FROM packages WHERE id = ?");
        $pkg_duration->execute([$package_id]);
        $duration_months = (int)$pkg_duration->fetchColumn();
        $end_date = date('Y-m-d', strtotime("$start_date + $duration_months months"));
        $pdo->prepare("UPDATE subscriptions SET status = 'active', end_date = ? WHERE id = ?")
            ->execute([$end_date, $sub_id]);

        // 3. Cancel any other pending subscriptions for this user
        $pdo->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = ? AND id != ? AND status = 'pending'")
            ->execute([$data['user_id'], $sub_id]);

        // 4. Referral Bonus (if applicable)
        if ($data['referred_by'] && $data['referral_bonus'] > 0) {
            $check = $pdo->prepare("SELECT id FROM user_referral_earnings WHERE user_id = ? AND referred_user_id = ? AND package_id = ?");
            $check->execute([$data['referred_by'], $data['user_id'], $package_id]);
            if ($check->rowCount() == 0) {
                $pdo->prepare("INSERT INTO user_referral_earnings (user_id, referred_user_id, package_id, amount, status) 
                               VALUES (?, ?, ?, ?, 'pending')")
                    ->execute([$data['referred_by'], $data['user_id'], $package_id, $data['referral_bonus']]);
            }
        }

        // 5. Add Income Entry (if function exists)
        if (function_exists('addAccountEntry')) {
            $user_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
            $user_stmt->execute([$data['user_id']]);
            $user_info = $user_stmt->fetch();
            $pkg_name = $pdo->prepare("SELECT name FROM packages WHERE id = ?");
            $pkg_name->execute([$package_id]);
            $pkgname = $pkg_name->fetchColumn();
            $description = "Subscription payment from {$user_info['name']} ({$user_info['email']}) for package $pkgname";
            addAccountEntry($pdo, 'income', $amount, $description, 'Auction Subscription', $start_date);
        }

        $pdo->commit();
        header("Location: admin_subscriptions.php?msg=approved");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        // Log error and show user-friendly message
        error_log("Approval error: " . $e->getMessage());
        die("❌ Error approving subscription. Please try again or contact support. Error: " . $e->getMessage());
    }
}

// ---- Reject ----
if (isset($_GET['reject'])) {
    if (!hasEditPermission('subscriptions', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to reject subscriptions.</div>");
    }
    $sub_id = (int)$_GET['reject'];
    $pdo->prepare("UPDATE subscriptions SET status = 'rejected' WHERE id = ?")->execute([$sub_id]);
    header("Location: admin_subscriptions.php?msg=rejected");
    exit;
}

include 'header.php';

// ---- Fetch pending subscriptions ----
$pendings = $pdo->query("
    SELECT s.*, u.name as uname, p.title as ptitle, pk.name as pkg_name 
    FROM subscriptions s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN properties p ON s.property_id = p.id 
    JOIN packages pk ON s.package_id = pk.id 
    WHERE s.status = 'pending' 
    ORDER BY s.id DESC
")->fetchAll();

$packages = $pdo->query("SELECT * FROM packages ORDER BY name")->fetchAll();
?>

<div class="card-premium">
    <h4><i class="fas fa-clock me-2"></i>Pending Subscriptions</h4>

    <?php
    if (isset($_GET['msg'])) {
        $msg = $_GET['msg'];
        if ($msg == 'approved') echo "<div class='alert alert-success'>✅ Activated! Income added to accounting.</div>";
        elseif ($msg == 'rejected') echo "<div class='alert alert-warning'>⛔ Rejected!</div>";
        elseif ($msg == 'already_active') echo "<div class='alert alert-info'>ℹ️ This subscription was already active.</div>";
    }
    ?>

    <?php if (count($pendings) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>UTR</th>
                        <th>Slip</th>
                        <?php if (hasEditPermission('subscriptions', $pdo)): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendings as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['uname']) ?></td>
                            <td><?= htmlspecialchars($p['pkg_name']) ?></td>
                            <td>₹<?= $p['amount'] ?></td>
                            <td><?= htmlspecialchars($p['utr'] ?? 'N/A') ?></td>
                            <td>
                                <?php if (!empty($p['slip_path']) && file_exists($p['slip_path'])): ?>
                                    <a href="<?= $p['slip_path'] ?>" target="_blank" class="btn btn-sm btn-info">📷 View</a>
                                <?php else: ?>
                                    <span class="text-muted">No slip</span>
                                <?php endif; ?>
                            </td>
                            <?php if (hasEditPermission('subscriptions', $pdo)): ?>
                                <td>
                                    <form method="POST" style="min-width:400px;">
                                        <input type="hidden" name="sub_id" value="<?= $p['id'] ?>">
                                        <div class="row g-2">
                                            <div class="col-4">
                                                <label class="form-label small">Amount</label>
                                                <input type="number" step="0.01" name="amount" class="form-control form-control-sm" value="<?= $p['amount'] ?>" required>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small">Package</label>
                                                <select name="package_id" class="form-select form-select-sm" required>
                                                    <?php foreach ($packages as $pkg): ?>
                                                        <option value="<?= $pkg['id'] ?>" <?= ($pkg['id'] == $p['package_id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($pkg['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small">Start Date</label>
                                                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <button type="submit" name="activate_sub" class="btn btn-success btn-sm" onclick="return confirm('Activate this subscription with these details? Income will be added to accounting.')">✅ Approve</button>
                                            <a href="?reject=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this subscription?')">❌ Reject</a>
                                        </div>
                                    </form>
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
