<?php
// ============================================================
// 🤝 Admin – Referral Payouts + MLM Payouts (Release System)
// Fixed: Shared Modal + Delete Old Pending
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
if(!hasViewPermission('referrals', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page.</div>");
}

// ---- Helper: get user bank details ----
function getUserBankDetails($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT bank_name, account_number, ifsc FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// ---- Global functions ----
function getGlobalDeductions($pdo) {
    $tds = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='tds_percent'")->fetchColumn();
    $admin = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='admin_charge_percent'")->fetchColumn();
    return ['tds' => (float)$tds ?: 2, 'admin' => (float)$admin ?: 5];
}

function calculateNet($amount, $tds_percent, $admin_charge_percent) {
    $tds = ($amount * $tds_percent) / 100;
    $admin_charge = ($amount * $admin_charge_percent) / 100;
    $net = $amount - $tds - $admin_charge;
    return ['tds' => $tds, 'admin_charge' => $admin_charge, 'net' => $net];
}

function activateSubscriptionForUser($pdo, $user_id, $package_id, $duration_months = 1) {
    $stmt = $pdo->prepare("SELECT id, end_date FROM subscriptions WHERE user_id = ? AND package_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
    $stmt->execute([$user_id, $package_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $new_end = date('Y-m-d', strtotime($existing['end_date'] . " + $duration_months months"));
        $pdo->prepare("UPDATE subscriptions SET end_date = ? WHERE id = ?")->execute([$new_end, $existing['id']]);
        return true;
    } else {
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime("+ $duration_months months"));
        $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, amount, payment_method, status, start_date, end_date) VALUES (?, ?, 0, 'referral_bonus', 'active', ?, ?)");
        return $stmt->execute([$user_id, $package_id, $start, $end]);
    }
}

// ============================================================
// 🔥 DELETE OLD PENDING MLM ENTRIES (Cleanup)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_old_pending'])) {
    if (!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ No permission.</div>");
    }
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->query("DELETE FROM user_earnings WHERE status = 'pending'");
        $deleted = $stmt->rowCount();
        $pdo->commit();
        $_SESSION['msg'] = "🗑️ Deleted <b>$deleted</b> old pending MLM entries.";
        header("Location: admin_referrals.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['msg'] = "❌ Error: " . $e->getMessage();
        header("Location: admin_referrals.php");
        exit;
    }
}

// ============================================================
// 🔥 RELEASE MLM PAYOUT (user_earnings pending → wallet)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_mlm'])) {
    if (!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ No permission.</div>");
    }

    $receiver_id = (int)$_POST['receiver_id'];
    $tds_percent = (float)$_POST['tds_percent'];
    $admin_charge_percent = (float)$_POST['admin_charge_percent'];
    $utr_no = trim($_POST['utr'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $ifsc = trim($_POST['ifsc'] ?? '');

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, amount FROM user_earnings WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$receiver_id]);
        $earnings = $stmt->fetchAll();

        if (empty($earnings)) throw new Exception("No pending MLM earnings for this user.");

        $total_gross = 0; $total_tds = 0; $total_admin = 0; $total_net = 0;

        foreach ($earnings as $e) {
            $calc = calculateNet($e['amount'], $tds_percent, $admin_charge_percent);
            $total_gross += $e['amount'];
            $total_tds += $calc['tds'];
            $total_admin += $calc['admin_charge'];
            $total_net += $calc['net'];

            $upd = $pdo->prepare("
                UPDATE user_earnings 
                SET status = 'paid', tds_deducted = ?, admin_charge_deducted = ?, 
                    net_amount = ?, paid_at = CURRENT_TIMESTAMP, utr_no = ?
                WHERE id = ?
            ");
            $upd->execute([$calc['tds'], $calc['admin_charge'], $calc['net'], $utr_no, $e['id']]);
        }

        if ($total_net > 0) {
            creditWallet($pdo, $receiver_id, $total_net, "MLM Payout Released (Gross ₹" . number_format($total_gross, 2) . ", Net ₹" . number_format($total_net, 2) . ")", null, null);
        }

        $uname_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $uname_stmt->execute([$receiver_id]);
        $uname = $uname_stmt->fetchColumn();
        addAccountEntry($pdo, 'expense', $total_net, "MLM Payout to $uname (ID: $receiver_id) - Gross ₹" . indianCurrencyFormat($total_gross) . " | UTR: $utr_no", 'MLM Payout');

        $pdo->commit();
        $_SESSION['msg'] = "✅ Released! Gross: ₹" . number_format($total_gross, 2) . " | TDS: ₹" . number_format($total_tds, 2) . " | Admin: ₹" . number_format($total_admin, 2) . " | <b>Net: ₹" . number_format($total_net, 2) . "</b>";
        header("Location: admin_referrals.php?mlm_paid=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['msg'] = "❌ Error: " . $e->getMessage();
        header("Location: admin_referrals.php");
        exit;
    }
}

// ============================================================
// EXISTING: Individual Referral Pay
// ============================================================
if(isset($_GET['pay']) && isset($_GET['id'])) {
    if(!hasEditPermission('referrals', $pdo)) die("No permission.");
    $id = $_GET['pay'];
    $tds_percent = (float)$_POST['tds_percent'];
    $admin_charge_percent = (float)$_POST['admin_charge_percent'];
    $bank_name = $_POST['bank_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
    $ifsc = $_POST['ifsc'] ?? '';
    $utr_no = trim($_POST['utr'] ?? '');
    $give_subscription = isset($_POST['give_subscription']) && $_POST['give_subscription'] == '1';
    $package_id = (int)($_POST['package_id'] ?? 0);
    $duration_months = (int)($_POST['duration_months'] ?? 1);

    $earn = $pdo->prepare("SELECT amount, user_id, package_id FROM user_referral_earnings WHERE id = ?");
    $earn->execute([$id]);
    $data = $earn->fetch();
    $amount = $data['amount'];
    $user_id = $data['user_id'];
    $pkg_id = $data['package_id'];
    if($amount) {
        $calc = calculateNet($amount, $tds_percent, $admin_charge_percent);
        $pdo->prepare("UPDATE user_referral_earnings SET status = 'paid', paid_at = CURRENT_TIMESTAMP, tds_deducted = ?, admin_charge_deducted = ?, net_amount = ?, bank_name = ?, account_number = ?, ifsc_code = ?, utr_no = ? WHERE id = ?")
            ->execute([$calc['tds'], $calc['admin_charge'], $calc['net'], $bank_name, $account_number, $ifsc, $utr_no, $id]);

        if ($calc['net'] > 0) creditWallet($pdo, $user_id, $calc['net'], "Referral bonus (net) for earning ID $id", $id);

        $user_name = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $user_name->execute([$user_id]);
        $uname = $user_name->fetchColumn();
        addAccountEntry($pdo, 'expense', $calc['net'], "Referral payout to $uname (ID: $user_id) - Net ₹" . indianCurrencyFormat($calc['net']) . " | UTR: $utr_no", 'Referral Payout');

        $pdo->prepare("UPDATE users SET wallet_balance = 0 WHERE id = ?")->execute([$user_id]);

        if ($give_subscription && $package_id > 0) {
            $final_pkg = $package_id ?: $pkg_id;
            activateSubscriptionForUser($pdo, $user_id, $final_pkg, $duration_months);
        }
        header("Location: admin_referrals.php?paid=1");
        exit;
    }
}

// ============================================================
// EXISTING: Pay All Pending for a Referrer
// ============================================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_all'])) {
    if(!hasEditPermission('referrals', $pdo)) die("No permission.");
    $referrer_id = (int)$_POST['referrer_id'];
    $tds_percent = (float)$_POST['tds_percent'];
    $admin_charge_percent = (float)$_POST['admin_charge_percent'];
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $ifsc = trim($_POST['ifsc']);
    $utr_no = trim($_POST['utr'] ?? '');
    $give_subscription_all = isset($_POST['give_subscription_all']) && $_POST['give_subscription_all'] == '1';
    $package_id_all = (int)($_POST['package_id_all'] ?? 0);
    $duration_all = (int)($_POST['duration_all'] ?? 1);

    $earnings = $pdo->prepare("SELECT id, amount FROM user_referral_earnings WHERE user_id = ? AND status = 'pending'");
    $earnings->execute([$referrer_id]);
    $earnings = $earnings->fetchAll();

    if(empty($earnings)) {
        $_SESSION['msg'] = "⚠️ No pending payouts.";
        header("Location: admin_referrals.php");
        exit;
    }

    $total_net = 0; $total_gross = 0; $total_tds = 0; $total_admin = 0;
    foreach($earnings as $earning) {
        $calc = calculateNet($earning['amount'], $tds_percent, $admin_charge_percent);
        $total_net += $calc['net']; $total_gross += $earning['amount']; $total_tds += $calc['tds']; $total_admin += $calc['admin_charge'];
        $stmt = $pdo->prepare("UPDATE user_referral_earnings SET status = 'paid', paid_at = CURRENT_TIMESTAMP, tds_deducted = ?, admin_charge_deducted = ?, net_amount = ?, bank_name = ?, account_number = ?, ifsc_code = ?, utr_no = ? WHERE id = ?");
        $stmt->execute([$calc['tds'], $calc['admin_charge'], $calc['net'], $bank_name, $account_number, $ifsc, $utr_no, $earning['id']]);
    }

    if($total_net > 0) creditWallet($pdo, $referrer_id, $total_net, "Referral bonus (net) multiple", 0);

    $user_name = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $user_name->execute([$referrer_id]);
    $uname = $user_name->fetchColumn();
    addAccountEntry($pdo, 'expense', $total_net, "Referral payout to $uname (ID: $referrer_id) - Net ₹" . indianCurrencyFormat($total_net) . " | UTR: $utr_no", 'Referral Payout');

    $pdo->prepare("UPDATE users SET wallet_balance = 0 WHERE id = ?")->execute([$referrer_id]);

    if ($give_subscription_all && $package_id_all > 0) {
        activateSubscriptionForUser($pdo, $referrer_id, $package_id_all, $duration_all);
    }

    $_SESSION['msg'] = "✅ Pay All done. Net ₹" . indianCurrencyFormat($total_net) . " credited.";
    header("Location: admin_referrals.php?paid=1");
    exit;
}

// ============================================================
// EXISTING: Manual Add Referral Payout
// ============================================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_manual_payout'])) {
    if(!hasEditPermission('referrals', $pdo)) die("No permission.");
    $referrer_id = (int)$_POST['referrer_id'];
    $referred_id = (int)$_POST['referred_id'];
    $package_id = (int)$_POST['package_id'];
    $amount = (float)$_POST['amount'];
    $activation_date = $_POST['activation_date'] ?? date('Y-m-d');

    if($referrer_id == $referred_id) {
        $_SESSION['msg'] = "❌ Same user.";
        header("Location: admin_referrals.php");
        exit;
    }
    $check = $pdo->prepare("SELECT id FROM user_referral_earnings WHERE user_id = ? AND referred_user_id = ? AND package_id = ?");
    $check->execute([$referrer_id, $referred_id, $package_id]);
    if($check->rowCount() > 0) {
        $_SESSION['msg'] = "⚠️ Already exists.";
        header("Location: admin_referrals.php");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO user_referral_earnings (user_id, referred_user_id, package_id, amount, status, referred_activation_date) VALUES (?, ?, ?, ?, 'pending', ?)");
    $stmt->execute([$referrer_id, $referred_id, $package_id, $amount, $activation_date]);
    $_SESSION['msg'] = "✅ Manual payout added.";
    header("Location: admin_referrals.php");
    exit;
}

include 'header.php';

$defaults = getGlobalDeductions($pdo);

// ---- Summary Stats ----
$summary = $pdo->query("SELECT COALESCE(SUM(tds_deducted), 0) as total_tds, COALESCE(SUM(admin_charge_deducted), 0) as total_admin, COALESCE(SUM(net_amount), 0) as total_net_paid FROM user_referral_earnings WHERE status = 'paid'")->fetch();
$total_tds = $summary['total_tds']; $total_admin = $summary['total_admin']; $total_net_paid = $summary['total_net_paid'];

// ---- Pending Referral Groups (Old) ----
$pendingGroups = $pdo->query("
    SELECT e.user_id as referrer_id, u.name as referrer_name, u.email as referrer_email, SUM(e.amount) as total_amount, COUNT(e.id) as total_count
    FROM user_referral_earnings e JOIN users u ON e.user_id = u.id
    WHERE e.status = 'pending' GROUP BY e.user_id, u.name, u.email ORDER BY u.name
")->fetchAll();

// 🔥 Pending MLM Payouts (from user_earnings)
$mlmPending = $pdo->query("
    SELECT e.user_id as receiver_id, u.name as receiver_name, u.email as receiver_email, SUM(e.amount) as total_amount, COUNT(e.id) as total_count
    FROM user_earnings e JOIN users u ON e.user_id = u.id
    WHERE e.status = 'pending'
    GROUP BY e.user_id, u.name, u.email
    ORDER BY total_amount DESC
")->fetchAll();

// 🔥 Pre-fetch all entries for each pending receiver (for modals)
$mlmEntries = [];
if (!empty($mlmPending)) {
    $receiver_ids = array_column($mlmPending, 'receiver_id');
    $placeholders = implode(',', array_fill(0, count($receiver_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT e.*, f.name as from_user_name 
        FROM user_earnings e 
        LEFT JOIN users f ON e.from_user_id = f.id 
        WHERE e.user_id IN ($placeholders) AND e.status = 'pending'
        ORDER BY e.id ASC
    ");
    $stmt->execute($receiver_ids);
    while ($row = $stmt->fetch()) {
        $mlmEntries[$row['user_id']][] = $row;
    }
}

// 🔥 Paid MLM History
$mlmPaid = $pdo->query("
    SELECT e.*, u.name as receiver_name
    FROM user_earnings e JOIN users u ON e.user_id = u.id
    WHERE e.status = 'paid'
    ORDER BY e.paid_at DESC
    LIMIT 50
")->fetchAll();

// Paid Referral Payouts (Old)
$paid = $pdo->query("SELECT e.*, u.name as referrer_name, r.name as referred_name, p.name as package_name 
                     FROM user_referral_earnings e 
                     JOIN users u ON e.user_id = u.id 
                     JOIN users r ON e.referred_user_id = r.id 
                     JOIN packages p ON e.package_id = p.id
                     WHERE e.status = 'paid' ORDER BY e.paid_at DESC")->fetchAll();

// Dropdown data
$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
$packages = $pdo->query("SELECT id, name, referral_bonus, duration_months FROM packages ORDER BY name")->fetchAll();

// Messages
if(isset($_SESSION['msg'])) { echo "<div class='alert alert-info'>" . $_SESSION['msg'] . "</div>"; unset($_SESSION['msg']); }
if(isset($_GET['paid'])) echo "<div class='alert alert-success'>✅ Referral Payout(s) completed!</div>";
if(isset($_GET['mlm_paid'])) echo "<div class='alert alert-success'>✅ MLM Payout released!</div>";
?>

<div class="card-premium">
    <h4><i class="fas fa-hand-holding-usd me-2"></i>Referral & MLM Payouts</h4>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card bg-success text-white p-3 rounded-4"><h6>Total Net Paid</h6><h3>₹ <?= indianCurrencyFormat($total_net_paid) ?></h3></div></div>
        <div class="col-md-3"><div class="card bg-warning text-dark p-3 rounded-4"><h6>Total TDS</h6><h3>₹ <?= indianCurrencyFormat($total_tds) ?></h3></div></div>
        <div class="col-md-3"><div class="card bg-info text-dark p-3 rounded-4"><h6>Total Admin Charge</h6><h3>₹ <?= indianCurrencyFormat($total_admin) ?></h3></div></div>
        <div class="col-md-3"><div class="card bg-secondary text-white p-3 rounded-4"><h6>Total Gross</h6><h3>₹ <?= indianCurrencyFormat($total_net_paid + $total_tds + $total_admin) ?></h3></div></div>
    </div>

    <p class="text-muted">Global TDS: <strong><?= $defaults['tds'] ?>%</strong> | Admin Charge: <strong><?= $defaults['admin'] ?>%</strong></p>

    <!-- ============================================================ -->
    <!-- SECTION A: MLM PENDING RELEASE -->
    <!-- ============================================================ -->
    <div class="card border-0 shadow-sm p-3 mb-4" style="background: #eef2ff; border-radius: 16px; border-left: 5px solid #2563eb !important;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0" style="color: #1e3a8a;"><i class="fas fa-layer-group me-2"></i>MLM Payouts (Pending Release)</h5>
            <div>
                <?php if (count($mlmPending) > 0): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ यह सभी PENDING MLM entries DELETE कर देगा (वापस नहीं होंगी)।\n\nक्या आप sure हैं?');">
                        <input type="hidden" name="delete_old_pending" value="1">
                        <button type="submit" class="btn btn-sm btn-danger rounded-pill">
                            <i class="fas fa-trash me-1"></i> Delete All Pending (<?= count($mlmPending) ?>)
                        </button>
                    </form>
                <?php endif; ?>
                <a href="admin_payout_preview.php" class="btn btn-sm btn-outline-primary rounded-pill">
                    <i class="fas fa-plus me-1"></i> Generate New
                </a>
            </div>
        </div>
        <p class="text-muted small mb-3">
            <b>View Details</b> से पूरी entries देखें, और <b>Release</b> दबाकर एक click में wallet credit करें।
        </p>

        <?php if (count($mlmPending) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle" style="font-size: 0.85rem; background: #fff;">
                    <thead class="table-dark">
                        <tr>
                            <th>Receiver</th>
                            <th class="text-end">Gross</th>
                            <th class="text-end">TDS (<?= $defaults['tds'] ?>%)</th>
                            <th class="text-end">Admin (<?= $defaults['admin'] ?>%)</th>
                            <th class="text-end">Net Payable</th>
                            <th class="text-center">Entries</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($mlmPending as $g): 
                        $gross = $g['total_amount'];
                        $calc = calculateNet($gross, $defaults['tds'], $defaults['admin']);
                        $bank = getUserBankDetails($pdo, $g['receiver_id']);
                        $entries = $mlmEntries[$g['receiver_id']] ?? [];
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($g['receiver_name']) ?></strong>
                                <div style="font-size:0.72rem;color:#64748b;">#<?= $g['receiver_id'] ?> — <?= htmlspecialchars($g['receiver_email']) ?></div>
                            </td>
                            <td class="text-end fw-bold">₹ <?= number_format($gross, 2) ?></td>
                            <td class="text-end text-danger">- ₹ <?= number_format($calc['tds'], 2) ?></td>
                            <td class="text-end text-danger">- ₹ <?= number_format($calc['admin_charge'], 2) ?></td>
                            <td class="text-end fw-bold text-success">₹ <?= number_format($calc['net'], 2) ?></td>
                            <td class="text-center"><span class="badge bg-primary"><?= $g['total_count'] ?></span></td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-info"
                                        onclick="openDetailsModal(
                                            <?= $g['receiver_id'] ?>,
                                            '<?= htmlspecialchars(addslashes($g['receiver_name'])) ?>',
                                            <?= $gross ?>,
                                            <?= $calc['tds'] ?>,
                                            <?= $calc['admin_charge'] ?>,
                                            <?= $calc['net'] ?>,
                                            '<?= htmlspecialchars(addslashes($bank['bank_name'] ?? '')) ?>',
                                            '<?= htmlspecialchars(addslashes($bank['account_number'] ?? '')) ?>',
                                            '<?= htmlspecialchars(addslashes($bank['ifsc'] ?? '')) ?>'
                                        )">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Release ₹<?= number_format($calc['net'], 2) ?> to <?= htmlspecialchars($g['receiver_name']) ?>?');">
                                    <input type="hidden" name="release_mlm" value="1">
                                    <input type="hidden" name="receiver_id" value="<?= $g['receiver_id'] ?>">
                                    <input type="hidden" name="tds_percent" value="<?= $defaults['tds'] ?>">
                                    <input type="hidden" name="admin_charge_percent" value="<?= $defaults['admin'] ?>">
                                    <input type="hidden" name="utr" value="AUTO-<?= date('YmdHis') . '-' . $g['receiver_id'] ?>">
                                    <input type="hidden" name="bank_name" value="<?= htmlspecialchars($bank['bank_name'] ?? '') ?>">
                                    <input type="hidden" name="account_number" value="<?= htmlspecialchars($bank['account_number'] ?? '') ?>">
                                    <input type="hidden" name="ifsc" value="<?= htmlspecialchars($bank['ifsc'] ?? '') ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-bolt me-1"></i> Release
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light mb-0 text-center">
                ✨ No pending MLM payouts. 
                <a href="admin_payout_preview.php" class="fw-bold">Generate new →</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================ -->
    <!-- SECTION B: OLD REFERRAL PENDING -->
    <!-- ============================================================ -->
    <h5 class="mt-4">Referral Payouts (Pending - Old System)</h5>
    <?php if(count($pendingGroups) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr>
                    <th>Referrer</th><th>Total Gross</th><th>TDS</th><th>Admin</th><th>Net</th><th>Count</th><th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach($pendingGroups as $group): 
                    $gross = $group['total_amount'];
                    $calc = calculateNet($gross, $defaults['tds'], $defaults['admin']);
                    $bank = getUserBankDetails($pdo, $group['referrer_id']);
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($group['referrer_name']) ?></strong><br><small><?= htmlspecialchars($group['referrer_email']) ?></small></td>
                        <td>₹<?= indianCurrencyFormat($gross) ?></td>
                        <td>₹<?= indianCurrencyFormat($calc['tds']) ?></td>
                        <td>₹<?= indianCurrencyFormat($calc['admin_charge']) ?></td>
                        <td><strong class="text-success">₹<?= indianCurrencyFormat($calc['net']) ?></strong></td>
                        <td><?= $group['total_count'] ?></td>
                        <td>
                            <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#payAllForm<?= $group['referrer_id'] ?>">
                                <i class="fas fa-credit-card"></i> Pay All
                            </button>
                            <div id="payAllForm<?= $group['referrer_id'] ?>" class="collapse mt-2">
                                <form method="POST" class="p-2 border rounded bg-light">
                                    <input type="hidden" name="referrer_id" value="<?= $group['referrer_id'] ?>">
                                    <input type="hidden" name="pay_all" value="1">
                                    <div class="row g-1">
                                        <div class="col-md-2"><input type="number" step="0.01" name="tds_percent" class="form-control form-control-sm" value="<?= $defaults['tds'] ?>" placeholder="TDS %" required></div>
                                        <div class="col-md-2"><input type="number" step="0.01" name="admin_charge_percent" class="form-control form-control-sm" value="<?= $defaults['admin'] ?>" placeholder="Admin %" required></div>
                                        <div class="col-md-2"><input type="text" name="bank_name" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['bank_name'] ?? '') ?>"></div>
                                        <div class="col-md-2"><input type="text" name="account_number" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['account_number'] ?? '') ?>"></div>
                                        <div class="col-md-2"><input type="text" name="ifsc" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['ifsc'] ?? '') ?>"></div>
                                        <div class="col-md-2"><input type="text" name="utr" class="form-control form-control-sm" placeholder="UTR" required></div>
                                    </div>
                                    <div class="mt-2"><button type="submit" class="btn btn-success btn-sm w-100">Confirm Pay All (Net: ₹<?= indianCurrencyFormat($calc['net']) ?>)</button></div>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: echo "<p class='text-muted'>No pending referral payouts.</p>"; endif; ?>

    <!-- ============================================================ -->
    <!-- SECTION C: MLM PAID HISTORY -->
    <!-- ============================================================ -->
    <h5 class="mt-4">MLM Payouts (Paid History)</h5>
    <?php if(count($mlmPaid) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered" style="font-size: 0.85rem;">
                <thead class="table-light"><tr>
                    <th>Receiver</th><th>From User</th><th>Type</th><th class="text-end">Gross</th><th class="text-end">TDS</th><th class="text-end">Admin</th><th class="text-end">Net</th><th>UTR</th><th>Paid On</th>
                </tr></thead>
                <tbody>
                <?php foreach($mlmPaid as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['receiver_name']) ?></strong> (#<?= $p['user_id'] ?>)</td>
                        <td>#<?= $p['from_user_id'] ?></td>
                        <td><?php if ($p['income_type'] == 'direct'): ?><span class="badge bg-success">Direct</span><?php else: ?><span class="badge bg-primary">Team</span><?php endif; ?></td>
                        <td class="text-end">₹ <?= number_format($p['amount'], 2) ?></td>
                        <td class="text-end">₹ <?= number_format($p['tds_deducted'] ?? 0, 2) ?></td>
                        <td class="text-end">₹ <?= number_format($p['admin_charge_deducted'] ?? 0, 2) ?></td>
                        <td class="text-end text-success fw-bold">₹ <?= number_format($p['net_amount'] ?? 0, 2) ?></td>
                        <td><?= htmlspecialchars($p['utr_no'] ?? '—') ?></td>
                        <td><?= $p['paid_at'] ? date('d M Y', strtotime($p['paid_at'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: echo "<p class='text-muted'>No MLM payouts released yet.</p>"; endif; ?>

    <!-- ============================================================ -->
    <!-- SECTION D: OLD PAID REFERRAL HISTORY -->
    <!-- ============================================================ -->
    <h5 class="mt-4">Referral Payouts (Paid History - Old)</h5>
    <?php if(count($paid) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered" style="font-size: 0.85rem;">
                <thead><tr>
                    <th>Referrer</th><th>Referred</th><th>Package</th><th>Gross</th><th>TDS</th><th>Admin</th><th>Net Paid</th><th>UTR</th><th>Paid On</th>
                </tr></thead>
                <tbody>
                <?php foreach($paid as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['referrer_name']) ?></td>
                        <td><?= htmlspecialchars($p['referred_name']) ?></td>
                        <td><?= htmlspecialchars($p['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($p['amount']) ?></td>
                        <td>₹<?= indianCurrencyFormat($p['tds_deducted']) ?></td>
                        <td>₹<?= indianCurrencyFormat($p['admin_charge_deducted']) ?></td>
                        <td><strong class="text-success">₹<?= indianCurrencyFormat($p['net_amount']) ?></strong></td>
                        <td><?= htmlspecialchars($p['utr_no'] ?? 'N/A') ?></td>
                        <td><?= date('d M Y', strtotime($p['paid_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: echo "<p class='text-muted'>No paid referral payouts yet.</p>"; endif; ?>

    <!-- ============================================================ -->
    <!-- SECTION E: MANUAL ADD -->
    <!-- ============================================================ -->
    <div class="card border-0 shadow-sm p-3 mt-5" style="background: #f8fafc; border-radius: 16px;">
        <h5><i class="fas fa-plus-circle me-2" style="color: #2563eb;"></i>Manual Add Referral Payout</h5>
        <form method="POST" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Referrer</label>
                <select name="referrer_id" class="form-select form-select-sm" required>
                    <option value="">Select</option>
                    <?php foreach($all_users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['email'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Referred User</label>
                <select name="referred_id" class="form-select form-select-sm" required>
                    <option value="">Select</option>
                    <?php foreach($all_users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['email'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Package</label>
                <select name="package_id" id="manual_package_id" class="form-select form-select-sm" required onchange="updateManualAmount()">
                    <option value="">Select</option>
                    <?php foreach($packages as $p): ?>
                        <option value="<?= $p['id'] ?>" data-bonus="<?= $p['referral_bonus'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Bonus Amount (₹)</label>
                <input type="number" step="0.01" name="amount" id="manual_amount" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Activation Date</label>
                <input type="date" name="activation_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" name="add_manual_payout" class="btn btn-primary btn-sm w-100">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================ -->
<!-- 🔥 SHARED MODAL (Outside Table) -->
<!-- ============================================================ -->
<div class="modal fade" id="sharedDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff;">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    Payout Details: <span id="modalReceiverName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Summary -->
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">Gross</small>
                            <div class="fw-bold fs-6" id="modalGross">₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">TDS (<span id="modalTdsPct"><?= $defaults['tds'] ?></span>%)</small>
                            <div class="fw-bold fs-6 text-danger" id="modalTds">- ₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">Admin (<span id="modalAdminPct"><?= $defaults['admin'] ?></span>%)</small>
                            <div class="fw-bold fs-6 text-danger" id="modalAdmin">- ₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center bg-success text-white">
                            <small>NET PAYABLE</small>
                            <div class="fw-bold fs-6" id="modalNet">₹ 0</div>
                        </div>
                    </div>
                </div>

                <!-- Entries Table (populated via JS) -->
                <h6 class="fw-bold mb-2">Income Breakdown (<span id="modalEntryCount">0</span> entries)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" style="font-size: 0.82rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>From User</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="modalEntriesBody"></tbody>
                        <tfoot>
                            <tr style="background:#fef3c7;">
                                <td colspan="3" class="text-end fw-bold">GROSS TOTAL:</td>
                                <td class="text-end fw-bold" id="modalGrossFoot">₹ 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Deduction Summary -->
                <div class="border rounded p-3" style="background: #f8fafc;">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Gross</span><span class="fw-bold" id="modalSumGross">₹ 0</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>TDS (<?= $defaults['tds'] ?>%)</span><span class="text-danger fw-bold" id="modalSumTds">- ₹ 0</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Admin Charge (<?= $defaults['admin'] ?>%)</span><span class="text-danger fw-bold" id="modalSumAdmin">- ₹ 0</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2 mt-2" style="border-top: 2px solid #1e293b; font-size: 1.15rem;">
                        <span class="fw-bold">NET PAYABLE</span>
                        <span class="fw-bold text-success" id="modalSumNet">₹ 0</span>
                    </div>
                </div>

                <div class="mt-3 small text-muted">
                    <strong>Bank:</strong> <span id="modalBank">N/A</span> | 
                    <strong>A/c:</strong> <span id="modalAcc">N/A</span> | 
                    <strong>IFSC:</strong> <span id="modalIfsc">N/A</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <form method="POST" id="modalReleaseForm" onsubmit="return confirm('Release this amount?');" style="display:inline;">
                    <input type="hidden" name="release_mlm" value="1">
                    <input type="hidden" name="receiver_id" id="modalReceiverId">
                    <input type="hidden" name="tds_percent" value="<?= $defaults['tds'] ?>">
                    <input type="hidden" name="admin_charge_percent" value="<?= $defaults['admin'] ?>">
                    <input type="hidden" name="utr" id="modalUtr">
                    <input type="hidden" name="bank_name" id="modalBankHidden">
                    <input type="hidden" name="account_number" id="modalAccHidden">
                    <input type="hidden" name="ifsc" id="modalIfscHidden">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-bolt me-1"></i> Release <span id="modalReleaseBtnAmount">₹ 0</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 🔥 MLM Entries Data for JS -->
<script>
    const MLM_ENTRIES = <?= json_encode($mlmEntries) ?>;
    const DEFAULT_TDS = <?= $defaults['tds'] ?>;
    const DEFAULT_ADMIN = <?= $defaults['admin'] ?>;

    function formatCurrency(n) {
        return '₹ ' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function openDetailsModal(receiverId, name, gross, tds, admin, net, bank, acc, ifsc) {
        // Header
        document.getElementById('modalReceiverName').textContent = name + ' (#' + receiverId + ')';

        // Summary
        document.getElementById('modalGross').textContent = formatCurrency(gross);
        document.getElementById('modalTds').textContent = '- ' + formatCurrency(tds);
        document.getElementById('modalAdmin').textContent = '- ' + formatCurrency(admin);
        document.getElementById('modalNet').textContent = formatCurrency(net);

        // Footer summary
        document.getElementById('modalSumGross').textContent = formatCurrency(gross);
        document.getElementById('modalSumTds').textContent = '- ' + formatCurrency(tds);
        document.getElementById('modalSumAdmin').textContent = '- ' + formatCurrency(admin);
        document.getElementById('modalSumNet').textContent = formatCurrency(net);
        document.getElementById('modalGrossFoot').textContent = formatCurrency(gross);
        document.getElementById('modalReleaseBtnAmount').textContent = formatCurrency(net);

        // Entries
        const entries = MLM_ENTRIES[receiverId] || [];
        const tbody = document.getElementById('modalEntriesBody');
        tbody.innerHTML = '';
        
        document.getElementById('modalEntryCount').textContent = entries.length;

        if (entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No entries found.</td></tr>';
        } else {
            entries.forEach(function(e) {
                const typeBadge = e.income_type === 'direct' 
                    ? '<span class="badge bg-success">Direct</span>' 
                    : '<span class="badge bg-primary">Team</span>';
                
                const row = '<tr>' +
                    '<td><strong>#' + e.from_user_id + ' ' + (e.from_user_name || 'N/A') + '</strong></td>' +
                    '<td>' + typeBadge + '</td>' +
                    '<td style="font-size:0.75rem;">' + (e.description || '') + '</td>' +
                    '<td class="text-end fw-bold text-success">' + formatCurrency(e.amount) + '</td>' +
                '</tr>';
                tbody.innerHTML += row;
            });
        }

        // Bank details
        document.getElementById('modalBank').textContent = bank || 'N/A';
        document.getElementById('modalAcc').textContent = acc || 'N/A';
        document.getElementById('modalIfsc').textContent = ifsc || 'N/A';

        // Hidden form fields
        document.getElementById('modalReceiverId').value = receiverId;
        document.getElementById('modalBankHidden').value = bank || '';
        document.getElementById('modalAccHidden').value = acc || '';
        document.getElementById('modalIfscHidden').value = ifsc || '';
        document.getElementById('modalUtr').value = 'AUTO-' + new Date().toISOString().slice(0,19).replace(/[^0-9]/g,'') + '-' + receiverId;

        // Show modal
        var modal = new bootstrap.Modal(document.getElementById('sharedDetailsModal'));
        modal.show();
    }
</script>

<?php include 'footer.php'; ?>
