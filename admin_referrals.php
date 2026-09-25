<?php
// ============================================================
// 🤝 Admin – Referral Payouts + MLM Payouts (Release System)
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
    return ['tds' => (float)$tds ?: 10, 'admin' => (float)$admin ?: 5];
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
// 🔥 NEW: RELEASE MLM PAYOUT (user_earnings pending → wallet)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_mlm'])) {
    if (!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission.</div>");
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

        // Fetch all pending MLM earnings for this user
        $stmt = $pdo->prepare("SELECT id, amount FROM user_earnings WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$receiver_id]);
        $earnings = $stmt->fetchAll();

        if (empty($earnings)) {
            throw new Exception("No pending MLM earnings for this user.");
        }

        $total_gross = 0;
        $total_tds = 0;
        $total_admin = 0;
        $total_net = 0;

        foreach ($earnings as $e) {
            $calc = calculateNet($e['amount'], $tds_percent, $admin_charge_percent);
            $total_gross += $e['amount'];
            $total_tds += $calc['tds'];
            $total_admin += $calc['admin_charge'];
            $total_net += $calc['net'];

            // Update earning to 'paid'
            $upd = $pdo->prepare("
                UPDATE user_earnings 
                SET status = 'paid', 
                    tds_deducted = ?, 
                    admin_charge_deducted = ?, 
                    net_amount = ?, 
                    paid_at = CURRENT_TIMESTAMP,
                    utr_no = ?
                WHERE id = ?
            ");
            $upd->execute([$calc['tds'], $calc['admin_charge'], $calc['net'], $utr_no, $e['id']]);
        }

        // Credit net amount to wallet
        if ($total_net > 0) {
            creditWallet($pdo, $receiver_id, $total_net, "MLM Payout Released (Gross ₹" . number_format($total_gross, 2) . ", Net ₹" . number_format($total_net, 2) . ")", null, null);
        }

        // Accounting expense entry
        $uname_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $uname_stmt->execute([$receiver_id]);
        $uname = $uname_stmt->fetchColumn();
        $description = "MLM Payout to $uname (ID: $receiver_id) - Gross ₹" . indianCurrencyFormat($total_gross) . " | UTR: $utr_no";
        addAccountEntry($pdo, 'expense', $total_net, $description, 'MLM Payout');

        $pdo->commit();

        $_SESSION['msg'] = "✅ MLM Payout released!<br>Gross: ₹" . number_format($total_gross, 2) . " | TDS: ₹" . number_format($total_tds, 2) . " | Admin: ₹" . number_format($total_admin, 2) . " | <b>Net Credited to Wallet: ₹" . number_format($total_net, 2) . "</b>";
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
    if(!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission.</div>");
    }
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
        $pdo->prepare("UPDATE user_referral_earnings SET 
                        status = 'paid', 
                        paid_at = CURRENT_TIMESTAMP,
                        tds_deducted = ?, 
                        admin_charge_deducted = ?, 
                        net_amount = ?,
                        bank_name = ?,
                        account_number = ?,
                        ifsc_code = ?,
                        utr_no = ?
                    WHERE id = ?")
            ->execute([$calc['tds'], $calc['admin_charge'], $calc['net'], $bank_name, $account_number, $ifsc, $utr_no, $id]);

        if ($calc['net'] > 0) {
            creditWallet($pdo, $user_id, $calc['net'], "Referral bonus (net) for earning ID $id", $id);
        }

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
    if(!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission.</div>");
    }
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
        $_SESSION['msg'] = "⚠️ No pending payouts for this user.";
        header("Location: admin_referrals.php");
        exit;
    }

    $total_net = 0;
    $total_gross = 0;
    $total_tds = 0;
    $total_admin = 0;
    foreach($earnings as $earning) {
        $calc = calculateNet($earning['amount'], $tds_percent, $admin_charge_percent);
        $total_net += $calc['net'];
        $total_gross += $earning['amount'];
        $total_tds += $calc['tds'];
        $total_admin += $calc['admin_charge'];
        $stmt = $pdo->prepare("UPDATE user_referral_earnings SET 
                                status = 'paid', 
                                paid_at = CURRENT_TIMESTAMP,
                                tds_deducted = ?, 
                                admin_charge_deducted = ?, 
                                net_amount = ?,
                                bank_name = ?,
                                account_number = ?,
                                ifsc_code = ?,
                                utr_no = ?
                            WHERE id = ?");
        $stmt->execute([$calc['tds'], $calc['admin_charge'], $calc['net'], $bank_name, $account_number, $ifsc, $utr_no, $earning['id']]);
    }

    if($total_net > 0) {
        creditWallet($pdo, $referrer_id, $total_net, "Referral bonus (net) for multiple referrals (Paid via Admin Pay All)", 0);
    }

    $user_name = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $user_name->execute([$referrer_id]);
    $uname = $user_name->fetchColumn();
    $description = "Referral payout to $uname (ID: $referrer_id) - Net ₹" . indianCurrencyFormat($total_net) . " | UTR: $utr_no (Pay All)";
    addAccountEntry($pdo, 'expense', $total_net, $description, 'Referral Payout');

    $pdo->prepare("UPDATE users SET wallet_balance = 0 WHERE id = ?")->execute([$referrer_id]);

    if ($give_subscription_all && $package_id_all > 0) {
        activateSubscriptionForUser($pdo, $referrer_id, $package_id_all, $duration_all);
    }

    $_SESSION['msg'] = "✅ Total Gross: ₹" . indianCurrencyFormat($total_gross) . ", Deductions: TDS ₹" . indianCurrencyFormat($total_tds) . ", Admin ₹" . indianCurrencyFormat($total_admin) . ", Net ₹" . indianCurrencyFormat($total_net) . " credited. Wallet balance set to 0. UTR: $utr_no";
    if ($give_subscription_all) $_SESSION['msg'] .= " Subscription activated.";
    header("Location: admin_referrals.php?paid=1");
    exit;
}

// ============================================================
// EXISTING: Manual Add Referral Payout
// ============================================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_manual_payout'])) {
    if(!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission.</div>");
    }
    $referrer_id = (int)$_POST['referrer_id'];
    $referred_id = (int)$_POST['referred_id'];
    $package_id = (int)$_POST['package_id'];
    $amount = (float)$_POST['amount'];
    $activation_date = $_POST['activation_date'] ?? date('Y-m-d');

    if($referrer_id == $referred_id) {
        $_SESSION['msg'] = "❌ Referrer and referred user cannot be same.";
        header("Location: admin_referrals.php");
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM user_referral_earnings WHERE user_id = ? AND referred_user_id = ? AND package_id = ?");
    $check->execute([$referrer_id, $referred_id, $package_id]);
    if($check->rowCount() > 0) {
        $_SESSION['msg'] = "⚠️ This referral already exists.";
        header("Location: admin_referrals.php");
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO user_referral_earnings 
                           (user_id, referred_user_id, package_id, amount, status, referred_activation_date) 
                           VALUES (?, ?, ?, ?, 'pending', ?)");
    $stmt->execute([$referrer_id, $referred_id, $package_id, $amount, $activation_date]);

    $_SESSION['msg'] = "✅ Manual payout added successfully! It will appear in pending list.";
    header("Location: admin_referrals.php");
    exit;
}

include 'header.php';

$defaults = getGlobalDeductions($pdo);

// ---- Summary Statistics (old referral) ----
$summary = $pdo->query("SELECT 
                           COALESCE(SUM(tds_deducted), 0) as total_tds,
                           COALESCE(SUM(admin_charge_deducted), 0) as total_admin,
                           COALESCE(SUM(net_amount), 0) as total_net_paid
                       FROM user_referral_earnings WHERE status = 'paid'")->fetch();

$total_tds = $summary['total_tds'];
$total_admin = $summary['total_admin'];
$total_net_paid = $summary['total_net_paid'];

// ---- Pending Referral Groups (Old System) ----
$pendingGroups = $pdo->query("
    SELECT 
        e.user_id as referrer_id,
        u.name as referrer_name,
        u.email as referrer_email,
        SUM(e.amount) as total_amount,
        COUNT(e.id) as total_count
    FROM user_referral_earnings e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'pending'
    GROUP BY e.user_id, u.name, u.email
    ORDER BY u.name
")->fetchAll();

// 🔥 NEW: Pending MLM Payouts (from user_earnings)
$mlmPending = $pdo->query("
    SELECT 
        e.user_id as receiver_id,
        u.name as receiver_name,
        u.email as receiver_email,
        SUM(e.amount) as total_amount,
        COUNT(e.id) as total_count
    FROM user_earnings e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'pending'
    GROUP BY e.user_id, u.name, u.email
    ORDER BY total_amount DESC
")->fetchAll();

// 🔥 NEW: Paid MLM Payouts History
$mlmPaid = $pdo->query("
    SELECT e.*, u.name as receiver_name
    FROM user_earnings e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'paid'
    ORDER BY e.paid_at DESC
    LIMIT 50
")->fetchAll();

// ---- Paid Referral Payouts (Old) ----
$paid = $pdo->query("SELECT e.*, u.name as referrer_name, r.name as referred_name, p.name as package_name 
                     FROM user_referral_earnings e
                     JOIN users u ON e.user_id = u.id
                     JOIN users r ON e.referred_user_id = r.id
                     JOIN packages p ON e.package_id = p.id
                     WHERE e.status = 'paid'
                     ORDER BY e.paid_at DESC")->fetchAll();

// ---- Dropdown data ----
$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
$packages = $pdo->query("SELECT id, name, referral_bonus, duration_months FROM packages ORDER BY name")->fetchAll();

// ---- Messages ----
if(isset($_SESSION['msg'])) {
    echo "<div class='alert alert-info'>" . $_SESSION['msg'] . "</div>";
    unset($_SESSION['msg']);
}
if(isset($_GET['paid'])) echo "<div class='alert alert-success'>✅ Referral Payout(s) completed! Expense entry added to accounting.</div>";
if(isset($_GET['mlm_paid'])) echo "<div class='alert alert-success'>✅ MLM Payout released to wallet!</div>";
?>

<div class="card-premium">
    <h4><i class="fas fa-hand-holding-usd me-2"></i>Referral & MLM Payouts</h4>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white p-3 rounded-4">
                <h6>Total Net Paid</h6>
                <h3>₹ <?= indianCurrencyFormat($total_net_paid) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark p-3 rounded-4">
                <h6>Total TDS Deducted</h6>
                <h3>₹ <?= indianCurrencyFormat($total_tds) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-dark p-3 rounded-4">
                <h6>Total Admin Charge</h6>
                <h3>₹ <?= indianCurrencyFormat($total_admin) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white p-3 rounded-4">
                <h6>Total Gross</h6>
                <h3>₹ <?= indianCurrencyFormat($total_net_paid + $total_tds + $total_admin) ?></h3>
            </div>
        </div>
    </div>

    <p class="text-muted">Global TDS: <strong><?= $defaults['tds'] ?>%</strong> | Admin Charge: <strong><?= $defaults['admin'] ?>%</strong> (Edit in Settings)</p>

    <!-- ============================================================ -->
    <!-- 🔥 SECTION A: MLM PAYOUTS (PENDING RELEASE) -->
    <!-- ============================================================ -->
    <div class="card border-0 shadow-sm p-3 mb-4" style="background: #eef2ff; border-radius: 16px; border-left: 5px solid #2563eb !important;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0" style="color: #1e3a8a;"><i class="fas fa-layer-group me-2"></i>MLM Payouts (Pending Release)</h5>
            <a href="admin_payout_preview.php" class="btn btn-sm btn-outline-primary rounded-pill">
                <i class="fas fa-plus me-1"></i> Generate New Payouts
            </a>
        </div>
        <p class="text-muted small mb-3">
            ये payouts <b>Payout Preview</b> से generate हुए हैं। TDS और Admin Charge लगाकर <b>Pay Now</b> दबाएं — तभी user के wallet में net amount जाएगा।
        </p>

        <?php if (count($mlmPending) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle" style="font-size: 0.85rem; background: #fff;">
                    <thead class="table-dark">
                        <tr>
                            <th>Receiver</th>
                            <th class="text-end">Total Gross</th>
                            <th class="text-end">TDS (<?= $defaults['tds'] ?>%)</th>
                            <th class="text-end">Admin (<?= $defaults['admin'] ?>%)</th>
                            <th class="text-end">Net Payable</th>
                            <th class="text-center">Entries</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($mlmPending as $g): 
                        $gross = $g['total_amount'];
                        $calc = calculateNet($gross, $defaults['tds'], $defaults['admin']);
                        $bank = getUserBankDetails($pdo, $g['receiver_id']);
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
                            <td>
                                <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#mlmPay<?= $g['receiver_id'] ?>">
                                    <i class="fas fa-credit-card me-1"></i> Pay Now
                                </button>
                                <div class="collapse mt-2" id="mlmPay<?= $g['receiver_id'] ?>">
                                    <form method="POST" class="p-2 border rounded bg-white" onsubmit="return confirm('Release ₹<?= number_format($calc['net'], 2) ?> to <?= htmlspecialchars($g['receiver_name']) ?>?');">
                                        <input type="hidden" name="release_mlm" value="1">
                                        <input type="hidden" name="receiver_id" value="<?= $g['receiver_id'] ?>">
                                        <div class="row g-1">
                                            <div class="col-md-2">
                                                <label class="form-label small">TDS %</label>
                                                <input type="number" step="0.01" name="tds_percent" class="form-control form-control-sm" value="<?= $defaults['tds'] ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Admin %</label>
                                                <input type="number" step="0.01" name="admin_charge_percent" class="form-control form-control-sm" value="<?= $defaults['admin'] ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">Bank</label>
                                                <input type="text" name="bank_name" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['bank_name'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">A/c No.</label>
                                                <input type="text" name="account_number" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['account_number'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">IFSC</label>
                                                <input type="text" name="ifsc" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['ifsc'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label small">UTR</label>
                                                <input type="text" name="utr" class="form-control form-control-sm" placeholder="UTR" required>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <button type="submit" class="btn btn-success btn-sm w-100">
                                                ✅ Confirm & Release ₹<?= number_format($calc['net'], 2) ?> to Wallet
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-light mb-0 text-center">
                ✨ No pending MLM payouts. All released. 
                <a href="admin_payout_preview.php" class="fw-bold">Generate new →</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================================ -->
    <!-- SECTION B: OLD REFERRAL PENDING -->
    <!-- ============================================================ -->
    <h5 class="mt-4">Referral Payouts (Pending)</h5>
    <?php if(count($pendingGroups) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr>
                    <th>Referrer</th>
                    <th>Total Gross</th>
                    <th>TDS</th>
                    <th>Admin Charge</th>
                    <th>Net Payable</th>
                    <th>Count</th>
                    <th>Action</th>
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
                                        <div class="col-md-2">
                                            <label class="form-label small">TDS %</label>
                                            <input type="number" step="0.01" name="tds_percent" class="form-control form-control-sm" value="<?= $defaults['tds'] ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Admin %</label>
                                            <input type="number" step="0.01" name="admin_charge_percent" class="form-control form-control-sm" value="<?= $defaults['admin'] ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Bank</label>
                                            <input type="text" name="bank_name" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['bank_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">A/c No.</label>
                                            <input type="text" name="account_number" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['account_number'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">IFSC</label>
                                            <input type="text" name="ifsc" class="form-control form-control-sm" value="<?= htmlspecialchars($bank['ifsc'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">UTR</label>
                                            <input type="text" name="utr" class="form-control form-control-sm" placeholder="UTR Number" required>
                                        </div>
                                    </div>
                                    <div class="row g-1 mt-2">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="give_subscription_all" value="1" id="subAll<?= $group['referrer_id'] ?>">
                                                <label class="form-check-label small" for="subAll<?= $group['referrer_id'] ?>">Give Subscription</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Package</label>
                                            <select name="package_id_all" class="form-select form-select-sm">
                                                <option value="">Select</option>
                                                <?php foreach($packages as $p): ?>
                                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Duration</label>
                                            <input type="number" name="duration_all" class="form-control form-control-sm" value="1" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Pay all pending ₹<?= indianCurrencyFormat($gross) ?> for <?= htmlspecialchars($group['referrer_name']) ?>?')">
                                            ✅ Confirm Pay All (Net: ₹<?= indianCurrencyFormat($calc['net']) ?>)
                                        </button>
                                    </div>
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
                    <th>Receiver</th>
                    <th>From User</th>
                    <th>Type</th>
                    <th class="text-end">Gross</th>
                    <th class="text-end">TDS</th>
                    <th class="text-end">Admin</th>
                    <th class="text-end">Net Paid</th>
                    <th>UTR</th>
                    <th>Paid On</th>
                </tr></thead>
                <tbody>
                <?php foreach($mlmPaid as $p): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($p['receiver_name']) ?></strong> (#<?= $p['user_id'] ?>)</td>
                        <td>#<?= $p['from_user_id'] ?></td>
                        <td>
                            <?php if ($p['income_type'] == 'direct'): ?>
                                <span class="badge bg-success">Direct</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Team</span>
                            <?php endif; ?>
                        </td>
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
    <h5 class="mt-4">Referral Payouts (Paid History)</h5>
    <?php if(count($paid) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered" style="font-size: 0.85rem;">
                <thead><tr>
                    <th>Referrer</th>
                    <th>Referred</th>
                    <th>Package</th>
                    <th>Gross</th>
                    <th>TDS</th>
                    <th>Admin Charge</th>
                    <th>Net Paid</th>
                    <th>UTR</th>
                    <th>Paid On</th>
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
        <a href="download_referral_excel.php" class="btn btn-success mt-3"><i class="fas fa-file-excel"></i> Download Excel</a>
    <?php else: echo "<p class='text-muted'>No paid referral payouts yet.</p>"; endif; ?>

    <!-- ============================================================ -->
    <!-- SECTION E: MANUAL ADD REFERRAL PAYOUT -->
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
        <script>
            function updateManualAmount() {
                const sel = document.getElementById('manual_package_id');
                const bonus = sel.options[sel.selectedIndex]?.getAttribute('data-bonus') || 0;
                document.getElementById('manual_amount').value = bonus;
            }
        </script>
    </div>
</div>

<?php include 'footer.php'; ?>
