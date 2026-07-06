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

    // Fetch all pending earnings for this referrer
    $earnings = $pdo->prepare("SELECT id, amount FROM user_referral_earnings WHERE user_id = ? AND status = 'pending'");
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
        // Update this earning
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

    $_SESSION['msg'] = "✅ Total ₹" . indianCurrencyFormat($total_net) . " credited to wallet for " . count($earnings) . " referrals.";
    header("Location: admin_referrals.php?paid=1");
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
        COUNT(e.id) as total_count
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

// ---- Fetch dropdown data for manual add ----
$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
$packages = $pdo->query("SELECT id, name, referral_bonus FROM packages ORDER BY name")->fetchAll();

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
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('Pay all pending ₹<?= indianCurrencyFormat($group['total_amount']) ?> for <?= htmlspecialchars($group['referrer_name']) ?>?')">
                                                ✅ Confirm Pay All (Total: ₹<?= indianCurrencyFormat($group['total_amount']) ?>)
                                            </button>
                                        </div>
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

    <!-- ===== Paid Payouts (Individual History) ===== -->
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
