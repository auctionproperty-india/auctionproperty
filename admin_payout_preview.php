<?php
// ============================================================
// 💰 Admin – Payout Preview (Per-Receiver Format like My Earnings)
// Step 1: Select buyers → Step 2: Preview per-receiver → Confirm
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$message = '';
$message_type = '';
$step = $_GET['step'] ?? 'select';

// ============================================================
// 🔥 ACTION: CONFIRM & GENERATE PAYOUTS
// ============================================================
if (isset($_POST['confirm_generate']) && !empty($_POST['selected_subs'])) {
    $selected_subs = array_map('intval', $_POST['selected_subs']);
    $batch_id = 'PREVIEW_' . date('Ymd_His');
    $count = 0;

    try {
        $pdo->beginTransaction();
        foreach ($selected_subs as $sub_id) {
            $stmt = $pdo->prepare("SELECT user_id, amount, package_id FROM subscriptions WHERE id = ?");
            $stmt->execute([$sub_id]);
            $sub = $stmt->fetch();
            if ($sub && $sub['amount'] > 0) {
                distributeIncome($pdo, $sub['user_id'], $sub['amount'], $sub['package_id'], $batch_id);
                $count++;
            }
        }
        $pdo->commit();
        $message = "✅ Successfully generated payouts for <b>$count</b> subscriptions!<br>Batch ID: <b>$batch_id</b>";
        $message_type = "success";
        $step = 'select';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// PREFETCH ALL DATA
// ============================================================
$all_users = [];
$stmt = $pdo->query("SELECT id, name, referred_by, free_user_income_enabled FROM users");
while ($row = $stmt->fetch()) {
    $all_users[$row['id']] = $row;
}

$active_subs_by_user = [];
$stmt = $pdo->query("
    SELECT user_id, SUM(amount) as total 
    FROM subscriptions 
    WHERE status = 'active' AND end_date >= CURRENT_DATE 
    GROUP BY user_id
");
while ($row = $stmt->fetch()) {
    $active_subs_by_user[$row['user_id']] = (float)$row['total'];
}

$user_active_pkg = [];
$stmt = $pdo->query("
    SELECT DISTINCT ON (s.user_id) s.user_id, p.name as pkg_name, 
           p.direct_income_percent, p.is_team_turnover_eligible
    FROM subscriptions s
    JOIN packages p ON s.package_id = p.id
    WHERE s.status = 'active' AND s.end_date >= CURRENT_DATE
    ORDER BY s.user_id, s.id DESC
");
while ($row = $stmt->fetch()) {
    $user_active_pkg[$row['user_id']] = $row;
}

$free_user_pct = 0;
$free_setting = $pdo->query("SELECT percentage FROM income_settings WHERE income_type = 'free_user_direct' AND status = 1 LIMIT 1")->fetch();
if ($free_setting) $free_user_pct = (float)$free_setting['percentage'];

$team_slabs = $pdo->query("
    SELECT min_turnover, max_turnover, percentage 
    FROM income_settings 
    WHERE income_type = 'team_turnover' AND status = 1 
    ORDER BY min_turnover ASC
")->fetchAll();

// Children map
$children_map = [];
foreach ($all_users as $uid => $u) {
    $parent = $u['referred_by'];
    if ($parent && isset($all_users[$parent])) {
        $children_map[$parent][] = $uid;
    }
}

// Team turnover cache
$team_turnover_cache = [];
$computeTurnover = function($uid) use (&$computeTurnover, &$children_map, &$active_subs_by_user, &$team_turnover_cache) {
    if (isset($team_turnover_cache[$uid])) return $team_turnover_cache[$uid];
    $total = 0;
    if (isset($children_map[$uid])) {
        foreach ($children_map[$uid] as $child_id) {
            $total += isset($active_subs_by_user[$child_id]) ? $active_subs_by_user[$child_id] : 0;
            $total += $computeTurnover($child_id);
        }
    }
    $team_turnover_cache[$uid] = $total;
    return $total;
};
foreach ($all_users as $uid => $u) {
    $computeTurnover($uid);
}

// Direct %
$getDirectPct = function($user_id) use (&$all_users, &$user_active_pkg, $free_user_pct) {
    if (isset($user_active_pkg[$user_id])) {
        return (float)($user_active_pkg[$user_id]['direct_income_percent'] ?? 0);
    } else {
        $enabled = !empty($all_users[$user_id]['free_user_income_enabled']);
        return $enabled ? $free_user_pct : 0;
    }
};

// Team %
$getTeamPct = function($user_id) use (&$team_turnover_cache, &$team_slabs, &$user_active_pkg) {
    if (!isset($user_active_pkg[$user_id])) return 0;
    if (empty($user_active_pkg[$user_id]['is_team_turnover_eligible'])) return 0;
    $turnover = $team_turnover_cache[$user_id] ?? 0;
    $matched_pct = 0;
    foreach ($team_slabs as $slab) {
        $min = (float)$slab['min_turnover'];
        $max = $slab['max_turnover'] !== null ? (float)$slab['max_turnover'] : PHP_FLOAT_MAX;
        if ($turnover >= $min && $turnover <= $max) {
            $matched_pct = (float)$slab['percentage'];
        }
    }
    return $matched_pct;
};

// ============================================================
// STEP 2: BUILD PREVIEW – GROUPED BY RECEIVER
// ============================================================
$receiver_data = [];   // [user_id => [entries, total_direct, total_team, total]]
$processed_sub_ids = [];
$grand_total = 0;

if ($step == 'preview' && !empty($_POST['selected_subs'])) {
    $selected_subs = array_map('intval', $_POST['selected_subs']);
    $processed_sub_ids = $selected_subs;
    $placeholders = implode(',', array_fill(0, count($selected_subs), '?'));

    $stmt = $pdo->prepare("
        SELECT s.id as sub_id, s.user_id, s.amount, s.status, s.end_date, s.package_id,
               u.name as buyer_name, u.id as buyer_id,
               p.name as pkg_name
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN packages p ON s.package_id = p.id
        WHERE s.id IN ($placeholders)
        ORDER BY s.id DESC
    ");
    $stmt->execute($selected_subs);
    $subs = $stmt->fetchAll();

    foreach ($subs as $sub) {
        $buyer_id = $sub['buyer_id'];
        $amount = (float)$sub['amount'];
        $buyer_name = $sub['buyer_name'];

        $current_user_id = $buyer_id;
        $last_direct_pct = 0;
        $last_team_pct = 0;
        $level = 1;
        $max_levels = 10;

        while ($level <= $max_levels) {
            $sponsor_id = $all_users[$current_user_id]['referred_by'] ?? null;
            if (!$sponsor_id || !isset($all_users[$sponsor_id])) break;

            $sponsor_name = $all_users[$sponsor_id]['name'] ?? 'Unknown';
            $sponsor_pkg = $user_active_pkg[$sponsor_id]['pkg_name'] ?? 'Free User';
            $sponsor_turnover = $team_turnover_cache[$sponsor_id] ?? 0;

            // Direct diff
            $direct_pct = $getDirectPct($sponsor_id);
            $direct_diff = 0;
            $direct_amt = 0;
            if ($direct_pct > $last_direct_pct) {
                $direct_diff = $direct_pct - $last_direct_pct;
                $direct_amt = ($amount * $direct_diff) / 100;
                $last_direct_pct = $direct_pct;
            }

            // Team diff
            $team_pct = $getTeamPct($sponsor_id);
            $team_diff = 0;
            $team_amt = 0;
            if ($team_pct > $last_team_pct) {
                $team_diff = $team_pct - $last_team_pct;
                $team_amt = ($amount * $team_diff) / 100;
                $last_team_pct = $team_pct;
            }

            $level_total = $direct_amt + $team_amt;

            // Initialize receiver
            if (!isset($receiver_data[$sponsor_id])) {
                $receiver_data[$sponsor_id] = [
                    'user_id' => $sponsor_id,
                    'name' => $sponsor_name,
                    'package' => $sponsor_pkg,
                    'turnover' => $sponsor_turnover,
                    'entries' => [],
                    'total_direct' => 0,
                    'total_team' => 0,
                    'total' => 0,
                ];
            }

            // Add entry only if something was earned
            if ($level_total > 0) {
                if ($direct_amt > 0) {
                    $receiver_data[$sponsor_id]['entries'][] = [
                        'buyer_id' => $buyer_id,
                        'buyer_name' => $buyer_name,
                        'buyer_amount' => $amount,
                        'sub_id' => $sub['sub_id'],
                        'level' => $level,
                        'type' => 'Direct',
                        'pct' => $direct_diff,
                        'amount' => $direct_amt,
                    ];
                    $receiver_data[$sponsor_id]['total_direct'] += $direct_amt;
                    $receiver_data[$sponsor_id]['total'] += $direct_amt;
                }
                if ($team_amt > 0) {
                    $receiver_data[$sponsor_id]['entries'][] = [
                        'buyer_id' => $buyer_id,
                        'buyer_name' => $buyer_name,
                        'buyer_amount' => $amount,
                        'sub_id' => $sub['sub_id'],
                        'level' => $level,
                        'type' => 'Team Turnover',
                        'pct' => $team_diff,
                        'amount' => $team_amt,
                    ];
                    $receiver_data[$sponsor_id]['total_team'] += $team_amt;
                    $receiver_data[$sponsor_id]['total'] += $team_amt;
                }
            }

            $current_user_id = $sponsor_id;
            $level++;
            if ($last_direct_pct >= 100 && $last_team_pct >= 100) break;
        }
    }

    // Sort by total descending
    uasort($receiver_data, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    // Grand total
    foreach ($receiver_data as $rd) {
        $grand_total += $rd['total'];
    }
}

// ============================================================
// STEP 1: Fetch all subscriptions
// ============================================================
$all_subs = [];
if ($step == 'select') {
    $stmt = $pdo->query("
        SELECT s.id as sub_id, s.user_id, s.amount, s.status, s.end_date,
               u.name as buyer_name, u.id as buyer_id,
               p.name as pkg_name
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN packages p ON s.package_id = p.id
        WHERE s.status IN ('active', 'expired') AND s.amount > 0
        ORDER BY s.id DESC
    ");
    $all_subs = $stmt->fetchAll();
}

include 'header.php';
?>

<style>
    .stmt-slip {
        background: #fff;
        border-radius: 16px;
        border: 2px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 20px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    }
    .stmt-slip-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 16px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .stmt-slip-header h5 {
        margin: 0;
        font-weight: 800;
        font-size: 1.05rem;
    }
    .stmt-slip-header .pkg-badge {
        background: rgba(255,255,255,0.25);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .stmt-slip-body {
        padding: 18px 22px;
    }

    /* Summary pills */
    .summary-pills {
        display: flex;
        gap: 12px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }
    .summary-pill {
        flex: 1;
        min-width: 130px;
        padding: 12px;
        border-radius: 12px;
        text-align: center;
        border: 2px solid #e2e8f0;
    }
    .summary-pill .lbl {
        font-size: 0.68rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.4px;
    }
    .summary-pill .val {
        font-size: 1.15rem;
        font-weight: 800;
        margin-top: 3px;
    }
    .summary-pill.direct { background: #f0fdf4; border-color: #10b981; }
    .summary-pill.direct .val { color: #059669; }
    .summary-pill.team { background: #f5f3ff; border-color: #8b5cf6; }
    .summary-pill.team .val { color: #7c3aed; }
    .summary-pill.total { background: #eff6ff; border-color: #2563eb; }
    .summary-pill.total .val { color: #1d4ed8; }

    /* Entries table */
    .entries-table {
        width: 100%;
        font-size: 0.82rem;
        border-collapse: collapse;
    }
    .entries-table th {
        background: #1e293b;
        color: #fff;
        padding: 10px 8px;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        text-align: left;
    }
    .entries-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .entries-table tr:hover { background: #f8fafc; }
    .entries-table tfoot td {
        background: #f0fdf4;
        font-weight: 800;
        font-size: 0.9rem;
        color: #166534;
    }

    .badge-level { background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; }
    .badge-direct { background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .badge-team { background: #ede9fe; color: #5b21b6; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }

    /* Confirm bar */
    .confirm-bar {
        background: linear-gradient(135deg, #065f46, #10b981);
        padding: 18px 22px;
        border-radius: 16px;
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        box-shadow: 0 6px 25px rgba(16,185,129,0.3);
        margin-top: 20px;
    }
    .confirm-bar h4 { margin: 0; font-weight: 800; font-size: 1.3rem; }
    .confirm-bar p { margin: 4px 0 0; font-size: 0.85rem; opacity: 0.9; }
</style>

<div class="container mt-4 mb-5">
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($step == 'select'): ?>
        <!-- ========================================== -->
        <!-- STEP 1: SELECT SUBSCRIPTIONS               -->
        <!-- ========================================== -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold text-dark"><i class="fas fa-list-check me-2 text-primary"></i> Payout Preview – Step 1</h3>
            <div>
                <a href="admin_fix_subscription_amounts.php" class="btn btn-outline-warning rounded-pill px-3">
                    <i class="fas fa-tools me-1"></i> Fix Amounts
                </a>
                <a href="admin_payout_manager.php" class="btn btn-outline-primary rounded-pill px-3">
                    <i class="fas fa-wallet me-1"></i> Payout Manager
                </a>
            </div>
        </div>

        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle me-1"></i>
            Select subscriptions to preview payouts. Preview will be shown <b>per-receiver</b> (just like the user sees in My Earnings).
        </div>

        <form method="POST" action="?step=preview">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-dark text-white rounded-top-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-list me-2"></i> Subscriptions (<?= count($all_subs) ?>)</h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-light" onclick="toggleAll(true)">Select All</button>
                        <button type="button" class="btn btn-sm btn-light" onclick="toggleAll(false)">Deselect All</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;"></th>
                                    <th>Sub ID</th>
                                    <th>Buyer</th>
                                    <th>Package</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_subs)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No subscriptions found.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($all_subs as $sub): 
                                    $is_active = ($sub['status'] == 'active' && $sub['end_date'] >= date('Y-m-d'));
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" name="selected_subs[]" value="<?= $sub['sub_id'] ?>" class="sub-checkbox" style="width:1.2rem;height:1.2rem;">
                                        </td>
                                        <td><strong>#<?= $sub['sub_id'] ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($sub['buyer_name']) ?></strong>
                                            <div style="font-size: 0.7rem; color: #64748b;">#<?= $sub['buyer_id'] ?></div>
                                        </td>
                                        <td>
                                            <?php if ($sub['pkg_name']): ?>
                                                <span class="badge bg-primary"><?= htmlspecialchars($sub['pkg_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold">₹ <?= number_format($sub['amount'], 2) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $is_active ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $is_active ? 'Active' : 'Expired' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-end py-3">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5">
                        <i class="fas fa-eye me-2"></i> View Payout Preview
                    </button>
                </div>
            </div>
        </form>

    <?php else: ?>
        <!-- ========================================== -->
        <!-- STEP 2: PREVIEW (PER-RECEIVER)             -->
        <!-- ========================================== -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold text-success"><i class="fas fa-file-invoice-dollar me-2"></i> Payout Preview – Per Receiver</h3>
            <a href="admin_payout_preview.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Selection
            </a>
        </div>

        <?php if (empty($receiver_data)): ?>
            <div class="alert alert-warning">No payouts calculated from selected subscriptions.</div>
        <?php else: ?>

        <!-- Overall Summary -->
        <div class="alert alert-success py-3">
            <h5 class="fw-bold mb-2"><i class="fas fa-info-circle me-1"></i> Overall Summary</h5>
            <div class="row">
                <div class="col-md-4">
                    <small>Total Receivers:</small>
                    <div class="fw-bold fs-5"><?= count($receiver_data) ?></div>
                </div>
                <div class="col-md-4">
                    <small>Buyers Selected:</small>
                    <div class="fw-bold fs-5"><?= count($processed_sub_ids) ?></div>
                </div>
                <div class="col-md-4">
                    <small>Grand Total Payout:</small>
                    <div class="fw-bold fs-5 text-success">₹ <?= number_format($grand_total, 2) ?></div>
                </div>
            </div>
        </div>

        <!-- Per-Receiver Cards -->
        <?php $rc = 0; foreach ($receiver_data as $rid => $rd): $rc++; ?>
            <div class="stmt-slip">
                <div class="stmt-slip-header">
                    <h5>
                        <i class="fas fa-user-circle me-2"></i>
                        Receiver #<?= $rc ?>: <?= htmlspecialchars($rd['name']) ?> (#<?= $rid ?>)
                    </h5>
                    <span class="pkg-badge"><?= htmlspecialchars($rd['package']) ?></span>
                </div>
                <div class="stmt-slip-body">

                    <!-- Summary -->
                    <div class="summary-pills">
                        <div class="summary-pill direct">
                            <div class="lbl">Direct Income</div>
                            <div class="val">₹ <?= number_format($rd['total_direct'], 2) ?></div>
                        </div>
                        <div class="summary-pill team">
                            <div class="lbl">Team Turnover</div>
                            <div class="val">₹ <?= number_format($rd['total_team'], 2) ?></div>
                        </div>
                        <div class="summary-pill total">
                            <div class="lbl">Net Payable</div>
                            <div class="val">₹ <?= number_format($rd['total'], 2) ?></div>
                        </div>
                    </div>

                    <!-- Entries Table -->
                    <h6 class="fw-bold mb-2"><i class="fas fa-list me-1"></i> Income Breakdown (<?= count($rd['entries']) ?> entries)</h6>
                    <div class="table-responsive">
                        <table class="entries-table">
                            <thead>
                                <tr>
                                    <th>From Buyer</th>
                                    <th>Sub ID</th>
                                    <th>Level</th>
                                    <th>Income Type</th>
                                    <th>%</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rd['entries'] as $e): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= $e['buyer_id'] ?> <?= htmlspecialchars($e['buyer_name']) ?></strong>
                                            <div style="font-size: 0.7rem; color: #64748b;">Paid: ₹ <?= number_format($e['buyer_amount'], 2) ?></div>
                                        </td>
                                        <td>#<?= $e['sub_id'] ?></td>
                                        <td><span class="badge-level">L<?= $e['level'] ?></span></td>
                                        <td>
                                            <?php if ($e['type'] == 'Direct'): ?>
                                                <span class="badge-direct">Direct</span>
                                            <?php else: ?>
                                                <span class="badge-team">Team Turnover</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= number_format($e['pct'], 2) ?>%</strong></td>
                                        <td class="text-end fw-bold text-success">₹ <?= number_format($e['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" class="text-end">TOTAL PAYABLE:</td>
                                    <td class="text-end">₹ <?= number_format($rd['total'], 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Confirm Bar -->
        <form method="POST" action="?step=preview" onsubmit="return confirm('⚠️ Are you sure? This will PERMANENTLY credit ₹<?= number_format($grand_total, 2) ?> to selected receivers\' wallets.\n\nClick OK to confirm.');">
            <?php foreach ($processed_sub_ids as $sid): ?>
                <input type="hidden" name="selected_subs[]" value="<?= $sid ?>">
            <?php endforeach; ?>
            
            <div class="confirm-bar">
                <div>
                    <h4>₹ <?= number_format($grand_total, 2) ?> — Total Payout</h4>
                    <p>यह राशि <?= count($receiver_data) ?> receivers के wallets में जाएगी।</p>
                </div>
                <div>
                    <button type="submit" name="confirm_generate" class="btn btn-light btn-lg rounded-pill px-5 fw-bold text-success">
                        <i class="fas fa-check-circle me-2"></i> Yes, Confirm & Generate Payouts
                    </button>
                </div>
            </div>
        </form>

        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    function toggleAll(state) {
        document.querySelectorAll('.sub-checkbox').forEach(function(cb) {
            cb.checked = state;
        });
    }
</script>

<?php include 'footer.php'; ?>
