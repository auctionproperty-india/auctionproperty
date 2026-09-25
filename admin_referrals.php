<?php
// ============================================================
// 🤝 Admin – Referral Payouts + MLM Payouts (Release System)
// v3: Individual Delete + Paid History Details + Cleaned Old Section
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

// ============================================================
// 🔥 INDIVIDUAL DELETE – Delete all pending entries for a specific user
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_pending'])) {
    if (!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ No permission.</div>");
    }
    
    $delete_user_id = (int)$_POST['delete_user_id'];
    try {
        // Get user name
        $uname_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $uname_stmt->execute([$delete_user_id]);
        $uname = $uname_stmt->fetchColumn();
        
        $stmt = $pdo->prepare("DELETE FROM user_earnings WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$delete_user_id]);
        $deleted = $stmt->rowCount();
        
        $_SESSION['msg'] = "🗑️ Deleted <b>$deleted</b> pending entries for <b>" . htmlspecialchars($uname) . "</b> (#$delete_user_id).";
        header("Location: admin_referrals.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['msg'] = "❌ Error: " . $e->getMessage();
        header("Location: admin_referrals.php");
        exit;
    }
}

// ============================================================
// 🔥 DELETE ALL PENDING
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_pending'])) {
    if (!hasEditPermission('referrals', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ No permission.</div>");
    }
    
    try {
        $stmt = $pdo->query("DELETE FROM user_earnings WHERE status = 'pending'");
        $deleted = $stmt->rowCount();
        $_SESSION['msg'] = "🗑️ Deleted <b>$deleted</b> pending MLM entries (all users).";
        header("Location: admin_referrals.php");
        exit;
    } catch (Exception $e) {
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

include 'header.php';

$defaults = getGlobalDeductions($pdo);

// ---- Summary Stats ----
$summary = $pdo->query("SELECT COALESCE(SUM(tds_deducted), 0) as total_tds, COALESCE(SUM(admin_charge_deducted), 0) as total_admin, COALESCE(SUM(net_amount), 0) as total_net_paid FROM user_earnings WHERE status = 'paid'")->fetch();
$total_tds = $summary['total_tds']; 
$total_admin = $summary['total_admin']; 
$total_net_paid = $summary['total_net_paid'];

// ---- Pending MLM Payouts (Grouped by Receiver) ----
$mlmPending = $pdo->query("
    SELECT e.user_id as receiver_id, u.name as receiver_name, u.email as receiver_email, 
           SUM(e.amount) as total_amount, COUNT(e.id) as total_count
    FROM user_earnings e 
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'pending'
    GROUP BY e.user_id, u.name, u.email
    ORDER BY total_amount DESC
")->fetchAll();

// Pre-fetch entries for each pending receiver
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

// ---- Paid MLM History (Grouped by user_id + UTR) ----
$mlmPaidGroups = $pdo->query("
    SELECT e.user_id as receiver_id, u.name as receiver_name,
           COALESCE(e.utr_no, 'N/A') as utr_no,
           DATE(e.paid_at) as paid_date,
           MIN(e.paid_at) as paid_at,
           SUM(e.amount) as total_gross,
           SUM(COALESCE(e.tds_deducted, 0)) as total_tds,
           SUM(COALESCE(e.admin_charge_deducted, 0)) as total_admin,
           SUM(COALESCE(e.net_amount, 0)) as total_net,
           COUNT(*) as entry_count
    FROM user_earnings e 
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'paid'
    GROUP BY e.user_id, u.name, e.utr_no, DATE(e.paid_at)
    ORDER BY MIN(e.paid_at) DESC
    LIMIT 100
")->fetchAll();

// Pre-fetch paid entries for each group (for modal)
$mlmPaidEntries = [];
if (!empty($mlmPaidGroups)) {
    foreach ($mlmPaidGroups as $idx => $g) {
        $stmt = $pdo->prepare("
            SELECT e.*, f.name as from_user_name 
            FROM user_earnings e 
            LEFT JOIN users f ON e.from_user_id = f.id 
            WHERE e.user_id = ? 
              AND e.status = 'paid'
              AND COALESCE(e.utr_no, 'N/A') = ?
              AND DATE(e.paid_at) = ?
            ORDER BY e.id ASC
        ");
        $stmt->execute([$g['receiver_id'], $g['utr_no'], $g['paid_date']]);
        $mlmPaidEntries[$idx] = $stmt->fetchAll();
    }
}

// ---- Dropdown data ----
$all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
$packages = $pdo->query("SELECT id, name, referral_bonus, duration_months FROM packages ORDER BY name")->fetchAll();

// Messages
if(isset($_SESSION['msg'])) { echo "<div class='alert alert-info'>" . $_SESSION['msg'] . "</div>"; unset($_SESSION['msg']); }
if(isset($_GET['mlm_paid'])) echo "<div class='alert alert-success'>✅ MLM Payout released!</div>";
?>

<div class="card-premium">
    <h4><i class="fas fa-hand-holding-usd me-2"></i>MLM Payout Management</h4>

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
                <h6>Total TDS</h6>
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
    <!-- SECTION A: MLM PENDING RELEASE -->
    <!-- ============================================================ -->
    <div class="card border-0 shadow-sm p-3 mb-4" style="background: #eef2ff; border-radius: 16px; border-left: 5px solid #2563eb !important;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="mb-0" style="color: #1e3a8a;">
                <i class="fas fa-layer-group me-2"></i>Pending Release (<?= count($mlmPending) ?> receivers)
            </h5>
            <div>
                <?php if (count($mlmPending) > 0): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ यह सभी PENDING MLM entries DELETE कर देगा (all users)।\n\nक्या आप sure हैं?');">
                        <input type="hidden" name="delete_all_pending" value="1">
                        <button type="submit" class="btn btn-sm btn-danger rounded-pill">
                            <i class="fas fa-trash me-1"></i> Delete All Pending
                        </button>
                    </form>
                <?php endif; ?>
                <a href="admin_payout_preview.php" class="btn btn-sm btn-outline-primary rounded-pill">
                    <i class="fas fa-plus me-1"></i> Generate New
                </a>
            </div>
        </div>
        <p class="text-muted small mb-3">
            हर receiver के आगे <b>View Details</b> (पूरी entries देखें), <b>Release</b> (wallet credit करें), और <b>Delete</b> (इस user की सभी pending entries हटाएं)।
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
                                        onclick="openPendingModal(
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
                                <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ DELETE all <?= $g['total_count'] ?> pending entries for <?= htmlspecialchars($g['receiver_name']) ?>?\n\nThis cannot be undone.');">
                                    <input type="hidden" name="delete_user_pending" value="1">
                                    <input type="hidden" name="delete_user_id" value="<?= $g['receiver_id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete this user's pending entries">
                                        <i class="fas fa-trash"></i>
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
    <!-- SECTION B: MLM PAID HISTORY (with View Details) -->
    <!-- ============================================================ -->
    <h5 class="mt-4"><i class="fas fa-history me-2"></i>Paid History (<?= count($mlmPaidGroups) ?> batches)</h5>
    <?php if(count($mlmPaidGroups) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle" style="font-size: 0.85rem;">
                <thead class="table-dark">
                    <tr>
                        <th>Receiver</th>
                        <th>UTR</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">TDS</th>
                        <th class="text-end">Admin</th>
                        <th class="text-end">Net Paid</th>
                        <th class="text-center">Entries</th>
                        <th>Paid On</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($mlmPaidGroups as $idx => $p): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($p['receiver_name']) ?></strong>
                            <div style="font-size:0.72rem;color:#64748b;">#<?= $p['receiver_id'] ?></div>
                        </td>
                        <td><code style="font-size:0.75rem;"><?= htmlspecialchars($p['utr_no']) ?></code></td>
                        <td class="text-end">₹ <?= number_format($p['total_gross'], 2) ?></td>
                        <td class="text-end text-danger">₹ <?= number_format($p['total_tds'], 2) ?></td>
                        <td class="text-end text-danger">₹ <?= number_format($p['total_admin'], 2) ?></td>
                        <td class="text-end text-success fw-bold">₹ <?= number_format($p['total_net'], 2) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= $p['entry_count'] ?></span></td>
                        <td><?= $p['paid_at'] ? date('d M Y, h:i A', strtotime($p['paid_at'])) : '—' ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-info"
                                    onclick="openPaidModal(
                                        <?= $idx ?>,
                                        '<?= htmlspecialchars(addslashes($p['receiver_name'])) ?>',
                                        <?= $p['receiver_id'] ?>,
                                        '<?= htmlspecialchars(addslashes($p['utr_no'])) ?>',
                                        <?= $p['total_gross'] ?>,
                                        <?= $p['total_tds'] ?>,
                                        <?= $p['total_admin'] ?>,
                                        <?= $p['total_net'] ?>,
                                        '<?= date('d M Y, h:i A', strtotime($p['paid_at'])) ?>'
                                    )">
                                <i class="fas fa-eye me-1"></i> View Details
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-light text-center">No paid MLM payouts yet.</div>
    <?php endif; ?>

</div>

<!-- ============================================================ -->
<!-- MODAL 1: PENDING DETAILS -->
<!-- ============================================================ -->
<div class="modal fade" id="pendingDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff;">
                <h5 class="modal-title">
                    <i class="fas fa-file-invoice-dollar me-2"></i>
                    Pending Payout: <span id="pModalReceiverName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">Gross</small>
                            <div class="fw-bold fs-6" id="pModalGross">₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">TDS (<?= $defaults['tds'] ?>%)</small>
                            <div class="fw-bold fs-6 text-danger" id="pModalTds">- ₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">Admin (<?= $defaults['admin'] ?>%)</small>
                            <div class="fw-bold fs-6 text-danger" id="pModalAdmin">- ₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center bg-success text-white">
                            <small>NET PAYABLE</small>
                            <div class="fw-bold fs-6" id="pModalNet">₹ 0</div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-2">Pending Entries (<span id="pModalEntryCount">0</span>)</h6>
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
                        <tbody id="pModalEntriesBody"></tbody>
                        <tfoot>
                            <tr style="background:#fef3c7;">
                                <td colspan="3" class="text-end fw-bold">GROSS TOTAL:</td>
                                <td class="text-end fw-bold" id="pModalGrossFoot">₹ 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="border rounded p-3" style="background: #f8fafc;">
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Gross</span><span class="fw-bold" id="pModalSumGross">₹ 0</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>TDS (<?= $defaults['tds'] ?>%)</span><span class="text-danger fw-bold" id="pModalSumTds">- ₹ 0</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 border-bottom">
                        <span>Admin Charge (<?= $defaults['admin'] ?>%)</span><span class="text-danger fw-bold" id="pModalSumAdmin">- ₹ 0</span>
                    </div>
                    <div class="d-flex justify-content-between pt-2 mt-2" style="border-top: 2px solid #1e293b; font-size: 1.15rem;">
                        <span class="fw-bold">NET PAYABLE</span>
                        <span class="fw-bold text-success" id="pModalSumNet">₹ 0</span>
                    </div>
                </div>

                <div class="mt-3 small text-muted">
                    <strong>Bank:</strong> <span id="pModalBank">N/A</span> | 
                    <strong>A/c:</strong> <span id="pModalAcc">N/A</span> | 
                    <strong>IFSC:</strong> <span id="pModalIfsc">N/A</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <form method="POST" id="pModalReleaseForm" onsubmit="return confirm('Release this amount?');" style="display:inline;">
                    <input type="hidden" name="release_mlm" value="1">
                    <input type="hidden" name="receiver_id" id="pModalReceiverId">
                    <input type="hidden" name="tds_percent" value="<?= $defaults['tds'] ?>">
                    <input type="hidden" name="admin_charge_percent" value="<?= $defaults['admin'] ?>">
                    <input type="hidden" name="utr" id="pModalUtr">
                    <input type="hidden" name="bank_name" id="pModalBankHidden">
                    <input type="hidden" name="account_number" id="pModalAccHidden">
                    <input type="hidden" name="ifsc" id="pModalIfscHidden">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-bolt me-1"></i> Release <span id="pModalReleaseBtnAmount">₹ 0</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL 2: PAID DETAILS -->
<!-- ============================================================ -->
<div class="modal fade" id="paidDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #065f46, #10b981); color: #fff;">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>
                    Paid Statement: <span id="paidModalReceiverName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success py-2 small mb-3">
                    <strong>UTR:</strong> <code id="paidModalUtr"></code> | 
                    <strong>Paid On:</strong> <span id="paidModalPaidOn"></span>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">Gross</small>
                            <div class="fw-bold fs-6" id="paidModalGross">₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">TDS</small>
                            <div class="fw-bold fs-6 text-danger" id="paidModalTds">- ₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center">
                            <small class="text-muted">Admin</small>
                            <div class="fw-bold fs-6 text-danger" id="paidModalAdmin">- ₹ 0</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-2 border rounded text-center bg-success text-white">
                            <small>NET PAID</small>
                            <div class="fw-bold fs-6" id="paidModalNet">₹ 0</div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold mb-2">Paid Entries (<span id="paidModalEntryCount">0</span>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" style="font-size: 0.82rem;">
                        <thead class="table-dark">
                            <tr>
                                <th>From User</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">TDS</th>
                                <th class="text-end">Admin</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody id="paidModalEntriesBody"></tbody>
                        <tfoot>
                            <tr style="background:#f0fdf4;">
                                <td colspan="3" class="text-end fw-bold">TOTALS:</td>
                                <td class="text-end fw-bold" id="paidModalGrossFoot">₹ 0</td>
                                <td class="text-end fw-bold text-danger" id="paidModalTdsFoot">₹ 0</td>
                                <td class="text-end fw-bold text-danger" id="paidModalAdminFoot">₹ 0</td>
                                <td class="text-end fw-bold text-success" id="paidModalNetFoot">₹ 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================ -->
<script>
    const MLM_ENTRIES = <?= json_encode($mlmEntries) ?>;
    const MLM_PAID_ENTRIES = <?= json_encode($mlmPaidEntries) ?>;

    function formatCurrency(n) {
        return '₹ ' + parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ========== PENDING MODAL ==========
    function openPendingModal(receiverId, name, gross, tds, admin, net, bank, acc, ifsc) {
        document.getElementById('pModalReceiverName').textContent = name + ' (#' + receiverId + ')';
        document.getElementById('pModalGross').textContent = formatCurrency(gross);
        document.getElementById('pModalTds').textContent = '- ' + formatCurrency(tds);
        document.getElementById('pModalAdmin').textContent = '- ' + formatCurrency(admin);
        document.getElementById('pModalNet').textContent = formatCurrency(net);

        document.getElementById('pModalSumGross').textContent = formatCurrency(gross);
        document.getElementById('pModalSumTds').textContent = '- ' + formatCurrency(tds);
        document.getElementById('pModalSumAdmin').textContent = '- ' + formatCurrency(admin);
        document.getElementById('pModalSumNet').textContent = formatCurrency(net);
        document.getElementById('pModalGrossFoot').textContent = formatCurrency(gross);
        document.getElementById('pModalReleaseBtnAmount').textContent = formatCurrency(net);

        const entries = MLM_ENTRIES[receiverId] || [];
        const tbody = document.getElementById('pModalEntriesBody');
        tbody.innerHTML = '';
        document.getElementById('pModalEntryCount').textContent = entries.length;

        if (entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No entries.</td></tr>';
        } else {
            entries.forEach(function(e) {
                const typeBadge = e.income_type === 'direct' 
                    ? '<span class="badge bg-success">Direct</span>' 
                    : '<span class="badge bg-primary">Team</span>';
                
                tbody.innerHTML += '<tr>' +
                    '<td><strong>#' + e.from_user_id + ' ' + (e.from_user_name || 'N/A') + '</strong></td>' +
                    '<td>' + typeBadge + '</td>' +
                    '<td style="font-size:0.75rem;">' + (e.description || '') + '</td>' +
                    '<td class="text-end fw-bold text-success">' + formatCurrency(e.amount) + '</td>' +
                '</tr>';
            });
        }

        document.getElementById('pModalBank').textContent = bank || 'N/A';
        document.getElementById('pModalAcc').textContent = acc || 'N/A';
        document.getElementById('pModalIfsc').textContent = ifsc || 'N/A';

        document.getElementById('pModalReceiverId').value = receiverId;
        document.getElementById('pModalBankHidden').value = bank || '';
        document.getElementById('pModalAccHidden').value = acc || '';
        document.getElementById('pModalIfscHidden').value = ifsc || '';
        document.getElementById('pModalUtr').value = 'AUTO-' + new Date().toISOString().slice(0,19).replace(/[^0-9]/g,'') + '-' + receiverId;

        new bootstrap.Modal(document.getElementById('pendingDetailsModal')).show();
    }

    // ========== PAID MODAL ==========
    function openPaidModal(idx, name, receiverId, utr, gross, tds, admin, net, paidOn) {
        document.getElementById('paidModalReceiverName').textContent = name + ' (#' + receiverId + ')';
        document.getElementById('paidModalUtr').textContent = utr;
        document.getElementById('paidModalPaidOn').textContent = paidOn;
        document.getElementById('paidModalGross').textContent = formatCurrency(gross);
        document.getElementById('paidModalTds').textContent = '- ' + formatCurrency(tds);
        document.getElementById('paidModalAdmin').textContent = '- ' + formatCurrency(admin);
        document.getElementById('paidModalNet').textContent = formatCurrency(net);

        const entries = MLM_PAID_ENTRIES[idx] || [];
        const tbody = document.getElementById('paidModalEntriesBody');
        tbody.innerHTML = '';
        document.getElementById('paidModalEntryCount').textContent = entries.length;

        let totalGross = 0, totalTds = 0, totalAdmin = 0, totalNet = 0;

        if (entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No entries.</td></tr>';
        } else {
            entries.forEach(function(e) {
                const typeBadge = e.income_type === 'direct' 
                    ? '<span class="badge bg-success">Direct</span>' 
                    : '<span class="badge bg-primary">Team</span>';
                
                totalGross += parseFloat(e.amount);
                totalTds += parseFloat(e.tds_deducted || 0);
                totalAdmin += parseFloat(e.admin_charge_deducted || 0);
                totalNet += parseFloat(e.net_amount || 0);

                tbody.innerHTML += '<tr>' +
                    '<td><strong>#' + e.from_user_id + ' ' + (e.from_user_name || 'N/A') + '</strong></td>' +
                    '<td>' + typeBadge + '</td>' +
                    '<td style="font-size:0.75rem;">' + (e.description || '') + '</td>' +
                    '<td class="text-end">' + formatCurrency(e.amount) + '</td>' +
                    '<td class="text-end text-danger">- ' + formatCurrency(e.tds_deducted || 0) + '</td>' +
                    '<td class="text-end text-danger">- ' + formatCurrency(e.admin_charge_deducted || 0) + '</td>' +
                    '<td class="text-end fw-bold text-success">' + formatCurrency(e.net_amount || 0) + '</td>' +
                '</tr>';
            });
        }

        document.getElementById('paidModalGrossFoot').textContent = formatCurrency(totalGross);
        document.getElementById('paidModalTdsFoot').textContent = '- ' + formatCurrency(totalTds);
        document.getElementById('paidModalAdminFoot').textContent = '- ' + formatCurrency(totalAdmin);
        document.getElementById('paidModalNetFoot').textContent = formatCurrency(totalNet);

        new bootstrap.Modal(document.getElementById('paidDetailsModal')).show();
    }
</script>

<?php include 'footer.php'; ?>
