<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
if(!hasViewPermission('referrals', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page.</div>");
}

// ---- Helper function to activate subscription for a user ----
function activateSubscriptionForUser($pdo, $user_id, $package_id, $duration_months = 1) {
    // Check if already active subscription for this package?
    $stmt = $pdo->prepare("SELECT id, end_date FROM subscriptions WHERE user_id = ? AND package_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
    $stmt->execute([$user_id, $package_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        // Extend by duration
        $new_end = date('Y-m-d', strtotime($existing['end_date'] . " + $duration_months months"));
        $pdo->prepare("UPDATE subscriptions SET end_date = ? WHERE id = ?")->execute([$new_end, $existing['id']]);
        return true;
    } else {
        // Create new
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime("+ $duration_months months"));
        $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, amount, payment_method, status, start_date, end_date) VALUES (?, ?, 0, 'referral_bonus', 'active', ?, ?)");
        return $stmt->execute([$user_id, $package_id, $start, $end]);
    }
}

// ---- Mark as Paid (Individual) ----
if(isset($_GET['pay']) && isset($_GET['id'])) {
    if(!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to edit referrals.</div>");
    }
    $id = $_GET['pay'];
    $tds_percent = $_POST['tds_percent'] ?? 10;
    $admin_charge_percent = $_POST['admin_charge_percent'] ?? 5;
    $bank_name = $_POST['bank_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
    $ifsc = $_POST['ifsc'] ?? '';
    // Subscription activation options
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
        $calc = calculateReferralNet($amount, $tds_percent, $admin_charge_percent);
        $pdo->prepare("UPDATE user_referral_earnings SET 
                        status = 'paid', 
                        paid_at = CURRENT_TIMESTAMP,
                        tds_deducted = ?, 
                        admin_charge_deducted = ?, 
                        net_amount = ?,
                        bank_name = ?,
                        account_number = ?,
                        ifsc_code = ?
                    WHERE id = ?")
            ->execute([$calc['tds'], $calc['admin_charge'], $calc['net'], $bank_name, $account_number, $ifsc, $id]);
        
        creditWallet($pdo, $user_id, $calc['net'], "Referral bonus (net) for earning ID $id", $id);

        // If admin opted to give subscription
        if ($give_subscription && $package_id > 0) {
            $final_pkg = $package_id ?: $pkg_id; // use provided or fallback to earning's package
            activateSubscriptionForUser($pdo, $user_id, $final_pkg, $duration_months);
        }

        header("Location: admin_referrals.php?paid=1");
        exit;
    }
}

// ---- Pay All Pending for a Referrer ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_all'])) {
    if(!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to pay referrals.</div>");
    }
    $referrer_id = (int)$_POST['referrer_id'];
    $tds_percent = (float)$_POST['tds_percent'];
    $admin_charge_percent = (float)$_POST['admin_charge_percent'];
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $ifsc = trim($_POST['ifsc']);
    // Subscription options for all
    $give_subscription_all = isset($_POST['give_subscription_all']) && $_POST['give_subscription_all'] == '1';
    $package_id_all = (int)($_POST['package_id_all'] ?? 0);
    $duration_all = (int)($_POST['duration_all'] ?? 1);

    // Fetch all pending earnings for this referrer
    $earnings = $pdo->prepare("SELECT id, amount, package_id FROM user_referral_earnings WHERE user_id = ? AND status = 'pending'");
    $earnings->execute([$referrer_id]);
    $earnings = $earnings->fetchAll();

    if(empty($earnings)) {
        $_SESSION['msg'] = "⚠️ No pending payouts for this user.";
        header("Location: admin_referrals.php");
        exit;
    }

    $total_net = 0;
    foreach($earnings as $earning) {
        $calc = calculateReferralNet($earning['amount'], $tds_percent, $admin_charge_percent);
        $net = $calc['net'];
        $total_net += $net;
        $stmt = $pdo->prepare("UPDATE user_referral_earnings SET 
                                status = 'paid', 
                                paid_at = CURRENT_TIMESTAMP,
                                tds_deducted = ?, 
                                admin_charge_deducted = ?, 
                                net_amount = ?,
                                bank_name = ?,
                                account_number = ?,
                                ifsc_code = ?
                            WHERE id = ?");
        $stmt->execute([$calc['tds'], $calc['admin_charge'], $net, $bank_name, $account_number, $ifsc, $earning['id']]);
    }

    // Credit wallet with total net
    if($total_net > 0) {
        creditWallet($pdo, $referrer_id, $total_net, "Referral bonus (net) for multiple referrals (Paid via Admin Pay All)", 0);
    }

    // Give subscription to referrer if opted
    if ($give_subscription_all && $package_id_all > 0) {
        activateSubscriptionForUser($pdo, $referrer_id, $package_id_all, $duration_all);
    }

    $_SESSION['msg'] = "✅ Total ₹" . indianCurrencyFormat($total_net) . " credited to wallet for " . count($earnings) . " referrals.";
    if ($give_subscription_all) {
        $_SESSION['msg'] .= " Subscription activated for referrer.";
    }
    header("Location: admin_referrals.php?paid=1");
    exit;
}

// ---- Manual Add Referral Payout ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_manual_payout'])) {
    if(!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to add referrals.</div>");
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

// ---- Fetch pending groups ----
$pendingGroups = $pdo->query("
    SELECT 
        e.user_id as referrer_id,
        u.name as referrer_name,
        u.email as referrer_email,
        SUM(e.amount) as total_amount,
        COUNT(e.id) as total_count,
        GROUP_CONCAT(DISTINCT e.package_id) as package_ids
    FROM user_referral_earnings e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'pending'
    GROUP BY e.user_id, u.name, u.email
    ORDER BY u.name
")->fetchAll();

// ---- Fetch all paid (individual) for history ----
$paid = $pdo->query("SELECT e.*, u.name as referrer_name, r.name as referred_name, p.name as package_name 
                     FROM user_referral_earnings e
                     JOIN users u ON e.user_id = u.id
                     JOIN users r ON e.referred_user_id = r.id
                     JOIN packages p ON e.package_id = p.id
                     WHERE e.status = 'paid'
                     ORDER BY e.paid_at DESC")->fetchAll();

// ---- Fetch dropdown data ----
$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
$packages = $pdo->query("SELECT id, name, referral_bonus, duration_months FROM packages ORDER BY name")->fetchAll();

// ---- Show messages ----
if(isset($_SESSION['msg'])) {
    echo "<div class='alert alert-info'>" . $_SESSION['msg'] . "</div>";
    unset($_SESSION['msg']);
}
if(isset($_GET['paid'])) echo "<div class='alert alert-success'>✅ Payout(s) completed! Wallet credited.</div>";
?>
<div class="card-premium">
    <h4><i class="fas fa-hand-holding-usd me-2"></i>Referral Payouts</h4>

    <!-- ===== Manual Add Section ===== -->
    <div class="card border-0 shadow-sm p-3 mb-4" style="background: #f8fafc; border-radius: 16px;">
        <h5><i class="fas fa-plus-circle me-2" style="color: #2563eb;"></i>Manual Add Referral Payout</h5>
        <form method="POST" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Referrer (who gets paid)</label>
                <select name="referrer_id" class="form-select form-select-sm" required>
                    <option value="">Select Referrer</option>
                    <?php foreach($all_users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['email'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Referred User (who activated)</label>
                <select name="referred_id" class="form-select form-select-sm" required>
                    <option value="">Select User</option>
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
                <button type="submit" name="add_manual_payout" class="btn btn-primary btn-sm w-100">Add Payout</button>
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

    <!-- ===== Pending Payouts (Grouped by Referrer) ===== -->
    <h5 class="mt-4">Pending Payouts</h5>
    <?php if(count($pendingGroups) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr>
                    <th>Referrer</th>
                    <th>Total Pending (₹)</th>
                    <th>No. of Referrals</th>
                    <th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach($pendingGroups as $group): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($group['referrer_name']) ?></strong><br>
                            <small><?= htmlspecialchars($group['referrer_email']) ?></small>
                        </td>
                        <td><strong>₹<?= indianCurrencyFormat($group['total_amount']) ?></strong></td>
                        <td><?= $group['total_count'] ?></td>
                        <td>
                            <!-- Pay All Form -->
                            <button class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#payAllForm<?= $group['referrer_id'] ?>">
                                <i class="fas fa-credit-card"></i> Pay All
                            </button>
                            <div id="payAllForm<?= $group['referrer_id'] ?>" class="collapse mt-2">
                                <form method="POST" class="p-2 border rounded bg-light">
                                    <input type="hidden" name="referrer_id" value="<?= $group['referrer_id'] ?>">
                                    <input type="hidden" name="pay_all" value="1">
                                    <div class="row g-1">
                                        <div class="col-md-3">
                                            <label class="form-label small">TDS %</label>
                                            <input type="number" step="0.01" name="tds_percent" class="form-control form-control-sm" value="10" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Admin Charge %</label>
                                            <input type="number" step="0.01" name="admin_charge_percent" class="form-control form-control-sm" value="5" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Bank</label>
                                            <input type="text" name="bank_name" class="form-control form-control-sm" placeholder="Bank" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">A/c No.</label>
                                            <input type="text" name="account_number" class="form-control form-control-sm" placeholder="A/c No." required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">IFSC</label>
                                            <input type="text" name="ifsc" class="form-control form-control-sm" placeholder="IFSC" required>
                                        </div>
                                    </div>
                                    <!-- Subscription activation options for Pay All -->
                                    <div class="row g-1 mt-2">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="give_subscription_all" value="1" id="subAll<?= $group['referrer_id'] ?>">
                                                <label class="form-check-label small" for="subAll<?= $group['referrer_id'] ?>">
                                                    Give Subscription to Referrer
                                                </label>
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
                                            <label class="form-label small">Duration (Months)</label>
                                            <input type="number" name="duration_all" class="form-control form-control-sm" value="1" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Pay all pending ₹<?= indianCurrencyFormat($group['total_amount']) ?> for <?= htmlspecialchars($group['referrer_name']) ?>?')">
                                            ✅ Confirm Pay All (Total: ₹<?= indianCurrencyFormat($group['total_amount']) ?>)
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
    <?php else: echo "<p class='text-muted'>No pending payouts.</p>"; endif; ?>

    <!-- ===== Individual Pending Items (for single pay) ===== -->
    <h5 class="mt-4">Individual Pending Referrals</h5>
    <?php
    // Fetch individual pending items for single pay
    $individualPending = $pdo->query("SELECT e.*, u.name as referrer_name, r.name as referred_name, p.name as package_name 
                                     FROM user_referral_earnings e
                                     JOIN users u ON e.user_id = u.id
                                     JOIN users r ON e.referred_user_id = r.id
                                     JOIN packages p ON e.package_id = p.id
                                     WHERE e.status = 'pending'
                                     ORDER BY e.created_at DESC")->fetchAll();
    if(count($individualPending) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr>
                    <th>Referrer</th>
                    <th>Referred</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Activation Date</th>
                    <th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach($individualPending as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['referrer_name']) ?></td>
                        <td><?= htmlspecialchars($p['referred_name']) ?></td>
                        <td><?= htmlspecialchars($p['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($p['amount']) ?></td>
                        <td><?= $p['referred_activation_date'] ? date('d M Y', strtotime($p['referred_activation_date'])) : 'N/A' ?></td>
                        <td>
                            <form method="POST" action="?pay=1&id=<?= $p['id'] ?>" class="row g-1">
                                <div class="col-md-2"><input type="number" step="0.01" name="tds_percent" class="form-control form-control-sm" value="10" placeholder="TDS %"></div>
                                <div class="col-md-2"><input type="number" step="0.01" name="admin_charge_percent" class="form-control form-control-sm" value="5" placeholder="Admin %"></div>
                                <div class="col-md-2"><input type="text" name="bank_name" class="form-control form-control-sm" placeholder="Bank"></div>
                                <div class="col-md-2"><input type="text" name="account_number" class="form-control form-control-sm" placeholder="A/c No."></div>
                                <div class="col-md-2"><input type="text" name="ifsc" class="form-control form-control-sm" placeholder="IFSC"></div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#subOpts<?= $p['id'] ?>">Options</button>
                                </div>
                                <div class="col-12 collapse" id="subOpts<?= $p['id'] ?>">
                                    <div class="row g-1 mt-1">
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="give_subscription" value="1" id="subInd<?= $p['id'] ?>">
                                                <label class="form-check-label small" for="subInd<?= $p['id'] ?>">Give Subscription</label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small">Package</label>
                                            <select name="package_id" class="form-select form-select-sm">
                                                <option value="<?= $p['package_id'] ?>" selected><?= htmlspecialchars($p['package_name']) ?></option>
                                                <?php foreach($packages as $pk): ?>
                                                    <option value="<?= $pk['id'] ?>"><?= htmlspecialchars($pk['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small">Duration (Months)</label>
                                            <input type="number" name="duration_months" class="form-control form-control-sm" value="1" min="1">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-sm btn-success w-100" onclick="return confirm('Pay this referral?')">Mark Paid</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: echo "<p class='text-muted'>No individual pending items.</p>"; endif; ?>

    <!-- ===== Paid Payouts (History) ===== -->
    <h5 class="mt-4">Paid Payouts (History)</h5>
    <?php if(count($paid) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr>
                    <th>Referrer</th>
                    <th>Referred</th>
                    <th>Package</th>
                    <th>Gross</th>
                    <th>TDS</th>
                    <th>Admin Charge</th>
                    <th>Net Paid</th>
                    <th>Activation Date</th>
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
                        <td><strong>₹<?= indianCurrencyFormat($p['net_amount']) ?></strong></td>
                        <td><?= $p['referred_activation_date'] ? date('d M Y', strtotime($p['referred_activation_date'])) : 'N/A' ?></td>
                        <td><?= date('d M Y', strtotime($p['paid_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="download_referral_excel.php" class="btn btn-success mt-3"><i class="fas fa-file-excel"></i> Download Excel</a>
    <?php else: echo "<p class='text-muted'>No paid payouts yet.</p>"; endif; ?>
</div>
<?php include 'footer.php'; ?>
