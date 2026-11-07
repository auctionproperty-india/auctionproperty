<?php
// ============================================================
// 📋 Admin – Pending Subscriptions (Full Updated with Reason)
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

    $sub_stmt = $pdo->prepare("
        SELECT s.*, p.duration_months, p.referral_bonus, p.is_ba_package, u.referred_by, u.id as user_id 
        FROM subscriptions s 
        JOIN packages p ON s.package_id = p.id 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = ?
    ");
    $sub_stmt->execute([$sub_id]);
    $data = $sub_stmt->fetch();

    if (!$data) die("Subscription not found.");
    if ($data['status'] == 'active') {
        header("Location: admin_subscriptions.php?msg=already_active");
        exit;
    }

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

        // 4. Update user's activation_date & BA Status (If it's a BA package)
        $pdo->prepare("UPDATE users SET activation_date = ? WHERE id = ?")
            ->execute([$start_date, $data['user_id']]);
        
        if ($data['is_ba_package']) {
            $pdo->prepare("UPDATE users SET ba_status = 'active' WHERE id = ?")->execute([$data['user_id']]);
        }

        // 5. Referral Bonus
        if ($data['referred_by'] && $data['referral_bonus'] > 0) {
            $check = $pdo->prepare("SELECT id FROM user_referral_earnings WHERE user_id = ? AND referred_user_id = ? AND package_id = ?");
            $check->execute([$data['referred_by'], $data['user_id'], $package_id]);
            if ($check->rowCount() == 0) {
                $pdo->prepare("INSERT INTO user_referral_earnings (user_id, referred_user_id, package_id, amount, status) VALUES (?, ?, ?, ?, 'pending')")
                    ->execute([$data['referred_by'], $data['user_id'], $package_id, $data['referral_bonus']]);
            }
        }

        // 6. Add Income Entry
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

        // 7. DISTRIBUTE MLM INCOME
        if (function_exists('distributeIncome')) {
            distributeIncome($pdo, $data['user_id'], $amount, $package_id);
        }

        $pdo->commit();
        header("Location: admin_subscriptions.php?msg=approved");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("❌ Error: " . $e->getMessage());
    }
}

// ---- Reject with Reason ----
if (isset($_GET['reject'])) {
    if (!hasEditPermission('subscriptions', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ No permission.</div>");
    }
    $sub_id = (int)$_GET['reject'];
    $reason = $_GET['reason'] ?? '';
    
    // Get user ID and check if BA package
    $check_stmt = $pdo->prepare("SELECT s.user_id, p.is_ba_package FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.id = ?");
    $check_stmt->execute([$sub_id]);
    $sub_data = $check_stmt->fetch();

    // Update subscription
    $pdo->prepare("UPDATE subscriptions SET status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$reason, $sub_id]);
    
    // 🔥 Reset BA Status if it was a BA package
    if ($sub_data && $sub_data['is_ba_package']) {
        $pdo->prepare("UPDATE users SET ba_status = 'none' WHERE id = ?")->execute([$sub_data['user_id']]);
    }
    
    header("Location: admin_subscriptions.php?msg=rejected");
    exit;
}

include 'header.php';

$pendings = $pdo->query("
    SELECT s.*, u.name as uname, u.id as user_id, ref.name as referrer_name, ref.id as referrer_id, p.title as ptitle, pk.name as pkg_name 
    FROM subscriptions s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN users ref ON u.referred_by = ref.id
    LEFT JOIN properties p ON s.property_id = p.id 
    JOIN packages pk ON s.package_id = pk.id 
    WHERE s.status = 'pending' 
    ORDER BY s.id DESC
")->fetchAll();

$packages = $pdo->query("SELECT * FROM packages ORDER BY name")->fetchAll();
?>

<div class="card-premium">
    <h4><i class="fas fa-clock me-2"></i>Pending Subscriptions</h4>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'approved'): ?><div class='alert alert-success'>✅ Activated! Income distributed.</div><?php endif; ?>
        <?php if ($_GET['msg'] == 'rejected'): ?><div class='alert alert-warning'>⛔ Rejected & BA status reset!</div><?php endif; ?>
    <?php endif; ?>

    <?php if (count($pendings) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Referrer</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>UTR</th>
                        <th>Slip</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendings as $p): ?>
                        <tr>
                            <td><a href="javascript:void(0)" onclick="openUserPopup(<?= $p['user_id'] ?>)" class="fw-bold text-primary"><?= htmlspecialchars($p['uname']) ?></a></td>
                            <td><?= !empty($p['referrer_name']) ? htmlspecialchars($p['referrer_name']) : '—' ?></td>
                            <td><?= htmlspecialchars($p['pkg_name']) ?></td>
                            <td>₹<?= $p['amount'] ?></td>
                            <td><?= htmlspecialchars($p['utr'] ?? 'N/A') ?></td>
                            <td>
                                <?php 
                                if (!empty($p['payment_screenshot'])) {
                                    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
                                    echo '<a href="'.$base_url.'/'.$p['payment_screenshot'].'" target="_blank" class="btn btn-sm btn-info">📷 View</a>';
                                } else {
                                    echo '<span class="text-danger">No slip</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <form method="POST" style="min-width:350px;">
                                    <input type="hidden" name="sub_id" value="<?= $p['id'] ?>">
                                    <div class="row g-2">
                                        <div class="col-6"><input type="number" name="amount" class="form-control form-control-sm" value="<?= $p['amount'] ?>" required></div>
                                        <div class="col-6"><input type="date" name="start_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
                                        <div class="col-12">
                                            <select name="package_id" class="form-select form-select-sm" required>
                                                <?php foreach ($packages as $pkg): ?>
                                                    <option value="<?= $pkg['id'] ?>" <?= ($pkg['id'] == $p['package_id']) ? 'selected' : '' ?>><?= htmlspecialchars($pkg['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <button type="submit" name="activate_sub" class="btn btn-success btn-sm">✅ Approve</button>
                                        <button type="button" onclick="rejectWithReason(<?= $p['id'] ?>)" class="btn btn-sm btn-danger">❌ Reject</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No pending requests.</p>
    <?php endif; ?>
</div>

<script>
function openUserPopup(userId) {
    if (!userId || userId == 0) return;
    window.open('admin_user_popup.php?id=' + userId, 'UserDetailsPopup', 'width=800,height=700,scrollbars=yes,resizable=yes');
}
function rejectWithReason(id) {
    let reason = prompt("Enter rejection reason (Optional but recommended):");
    if (reason !== null) { // If user didn't click Cancel
        window.location.href = "?reject=" + id + "&reason=" + encodeURIComponent(reason);
    }
}
</script>

<?php include 'footer.php'; ?>
