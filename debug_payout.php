<?php
// ============================================================
// 🐞 DEBUG PAYOUT TOOL (Select → Preview → Generate)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Admin access required.");
}

$message = '';
$message_type = '';
$step = $_GET['step'] ?? 'select';

// ============================================================
// 🔥 ACTION: GENERATE PAYOUTS FOR SELECTED
// ============================================================
if (isset($_POST['generate_payouts_for_selected']) && !empty($_POST['selected_subs_to_process'])) {
    $selected_subs = array_map('intval', $_POST['selected_subs_to_process']);
    $batch_id = 'SELECTED_' . date('Ymd_His');
    $count = 0;
    
    try {
        $pdo->beginTransaction();
        foreach ($selected_subs as $sub_id) {
            $stmt = $pdo->prepare("SELECT user_id, amount, package_id FROM subscriptions WHERE id = ?");
            $stmt->execute([$sub_id]);
            $sub = $stmt->fetch();
            
            if ($sub && $sub['amount'] > 0) {
                if (function_exists('distributeIncome')) {
                    distributeIncome($pdo, $sub['user_id'], $sub['amount'], $sub['package_id'], $batch_id);
                    $count++;
                }
            }
        }
        $pdo->commit();
        $message = "✅ Successfully generated payouts for <b>$count</b> selected subscriptions!<br>Batch ID: <b>$batch_id</b><br>Check the <a href='admin_payout_manager.php' target='_blank'>Payout Manager</a> to verify.";
        $message_type = "success";
        $step = 'select'; // Reset to step 1
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error generating payouts: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// PREFETCH ALL DATA (Fast)
// ============================================================
$all_users = [];
$stmt = $pdo->query("SELECT id, name, referred_by, free_user_income_enabled FROM users");
while ($row = $stmt->fetch()) {
    $all_users[$row['id']] = $row;
}

$active_subs_by_user = [];
$stmt = $pdo->query("SELECT user_id, SUM(amount) as total FROM subscriptions WHERE status = 'active' AND end_date >= CURRENT_DATE GROUP BY user_id");
while ($row = $stmt->fetch()) {
    $active_subs_by_user[$row['user_id']] = (float)$row['total'];
}

$user_active_pkg = [];
$stmt = $pdo->query("
    SELECT DISTINCT ON (s.user_id) s.user_id, p.name as pkg_name, p.direct_income_percent, p.is_team_turnover_eligible
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

$team_slabs = $pdo->query("SELECT min_turnover, max_turnover, percentage FROM income_settings WHERE income_type = 'team_turnover' AND status = 1 ORDER BY min_turnover ASC")->fetchAll();

// Helpers
function getDirectPctForUser($user_id, &$all_users, &$user_active_pkg, $free_user_pct) {
    $is_free = !isset($user_active_pkg[$user_id]);
    if ($is_free) {
        $enabled = isset($all_users[$user_id]['free_user_income_enabled']) && $all_users[$user_id]['free_user_income_enabled'];
        return $enabled ? $free_user_pct : 0;
    } else {
        return (float)($user_active_pkg[$user_id]['direct_income_percent'] ?? 0);
    }
}

function getTeamPctForUser($user_id, $turnover, &$team_slabs, &$user_active_pkg) {
    if (!isset($user_active_pkg[$user_id])) return 0;
    if (empty($user_active_pkg[$user_id]['is_team_turnover_eligible'])) return 0;
    $matched_pct = 0;
    foreach ($team_slabs as $slab) {
        $min = (float)$slab['min_turnover'];
        $max = $slab['max_turnover'] !== null ? (float)$slab['max_turnover'] : PHP_FLOAT_MAX;
        if ($turnover >= $min && $turnover <= $max) {
            $matched_pct = (float)$slab['percentage'];
        }
    }
    return $matched_pct;
}

// ============================================================
// STEP 2: PROCESS SELECTED SUBSCRIPTIONS
// ============================================================
$receiver_statements = [];
$processed_sub_ids = [];

if ($step == 'view' && !empty($_POST['selected_subs'])) {
    $selected_subs = array_map('intval', $_POST['selected_subs']);
    $processed_sub_ids = $selected_subs;
    $placeholders = implode(',', array_fill(0, count($selected_subs), '?'));

    // Fetch volumes ONLY from selected subscriptions
    $selected_volumes_by_user = [];
    $stmt = $pdo->prepare("SELECT user_id, SUM(amount) as total FROM subscriptions WHERE id IN ($placeholders) GROUP BY user_id");
    $stmt->execute($selected_subs);
    while ($row = $stmt->fetch()) {
        $selected_volumes_by_user[$row['user_id']] = (float)$row['total'];
    }

    // Build children map
    $children_map = [];
    foreach ($all_users as $uid => $u) {
        $parent = $u['referred_by'];
        if ($parent && isset($all_users[$parent])) {
            $children_map[$parent][] = $uid;
        }
    }

    // Compute team turnover from SELECTED volumes only
    $team_turnover_cache = [];
    $computeTurnover = function($uid) use (&$computeTurnover, &$children_map, &$selected_volumes_by_user, &$team_turnover_cache) {
        if (isset($team_turnover_cache[$uid])) return $team_turnover_cache[$uid];
        $total = 0;
        if (isset($children_map[$uid])) {
            foreach ($children_map[$uid] as $child_id) {
                $total += isset($selected_volumes_by_user[$child_id]) ? $selected_volumes_by_user[$child_id] : 0;
                $total += $computeTurnover($child_id);
            }
        }
        $team_turnover_cache[$uid] = $total;
        return $total;
    };
    foreach ($all_users as $uid => $u) {
        $computeTurnover($uid);
    }

    // Fetch selected subscriptions details
    $stmt = $pdo->prepare("
        SELECT s.id as sub_id, s.user_id, s.amount, s.status, s.end_date,
               u.name as buyer_name, u.id as buyer_id
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id IN ($placeholders)
        ORDER BY s.id DESC
    ");
    $stmt->execute($selected_subs);
    $selected_sub_details = $stmt->fetchAll();

    // Walk up chain for each selected sub, record each receiver's earning
    foreach ($selected_sub_details as $sub) {
        $buyer_id = $sub['buyer_id'];
        $amount = (float)$sub['amount'];
        $buyer_name = $sub['buyer_name'];
        $sub_id = $sub['sub_id'];
        
        $current_user_id = $buyer_id;
        $last_direct_pct = 0;
        $last_team_pct = 0;
        $level = 1;
        $max_levels = 10;
        
        while ($level <= $max_levels) {
            $sponsor_id = $all_users[$current_user_id]['referred_by'] ?? null;
            if (!$sponsor_id || !isset($all_users[$sponsor_id])) break;
            
            $sponsor_name = $all_users[$sponsor_id]['name'] ?? 'Unknown';
            $sponsor_pkg_name = $user_active_pkg[$sponsor_id]['pkg_name'] ?? 'Free User';
            $sponsor_turnover = $team_turnover_cache[$sponsor_id] ?? 0;
            
            if (!isset($receiver_statements[$sponsor_id])) {
                $receiver_statements[$sponsor_id] = [
                    'name' => $sponsor_name,
                    'pkg' => $sponsor_pkg_name,
                    'turnover' => $sponsor_turnover,
                    'entries' => [],
                    'total' => 0
                ];
            }
            
            // DIRECT
            $direct_pct = getDirectPctForUser($sponsor_id, $all_users, $user_active_pkg, $free_user_pct);
            if ($direct_pct > $last_direct_pct) {
                $diff = $direct_pct - $last_direct_pct;
                $amt = ($amount * $diff) / 100;
                if ($amt > 0) {
                    $receiver_statements[$sponsor_id]['entries'][] = [
                        'sub_id' => $sub_id,
                        'buyer_id' => $buyer_id,
                        'buyer_name' => $buyer_name,
                        'buyer_amount' => $amount,
                        'level' => $level,
                        'type' => 'direct',
                        'pct' => $diff,
                        'amount' => $amt,
                    ];
                    $receiver_statements[$sponsor_id]['total'] += $amt;
                }
                $last_direct_pct = $direct_pct;
            }
            
            // TEAM TURNOVER
            $team_pct = getTeamPctForUser($sponsor_id, $sponsor_turnover, $team_slabs, $user_active_pkg);
            if ($team_pct > $last_team_pct) {
                $diff = $team_pct - $last_team_pct;
                $amt = ($amount * $diff) / 100;
                if ($amt > 0) {
                    $receiver_statements[$sponsor_id]['entries'][] = [
                        'sub_id' => $sub_id,
                        'buyer_id' => $buyer_id,
                        'buyer_name' => $buyer_name,
                        'buyer_amount' => $amount,
                        'level' => $level,
                        'type' => 'team_turnover',
                        'pct' => $diff,
                        'amount' => $amt,
                    ];
                    $receiver_statements[$sponsor_id]['total'] += $amt;
                }
                $last_team_pct = $team_pct;
            }
            
            $current_user_id = $sponsor_id;
            $level++;
            if ($last_direct_pct >= 100 && $last_team_pct >= 100) break;
        }
    }

    uasort($receiver_statements, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
}

// ============================================================
// STEP 1: Fetch all subscriptions for selection
// ============================================================
$all_subs = [];
if ($step == 'select') {
    $stmt = $pdo->query("
        SELECT s.id as sub_id, s.user_id, s.amount, s.status, s.end_date,
               u.name as buyer_name, u.id as buyer_id
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.status IN ('active', 'expired')
        ORDER BY s.id DESC
    ");
    $all_subs = $stmt->fetchAll();
}

include 'header.php';
?>

<style>
    .slip-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        overflow: hidden;
        margin-bottom: 25px;
    }
    .slip-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 20px 25px;
        text-align: center;
    }
    .slip-header h3 { margin: 0; font-weight: 800; letter-spacing: 1px; }
    .slip-header p { margin: 4px 0 0; opacity: 0.85; font-size: 0.85rem; }
    .slip-body { padding: 20px 25px; }
    .receiver-info {
        background: #f8fafc;
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 18px;
        border-left: 5px solid #2563eb;
    }
    .receiver-info table { width: 100%; font-size: 0.88rem; }
    .receiver-info td { padding: 3px 0; }
    .summary-pills { display: flex; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
    .summary-pill {
        flex: 1; min-width: 150px; padding: 12px; border-radius: 10px;
        text-align: center; border: 2px solid #e2e8f0;
    }
    .summary-pill .lbl { font-size: 0.7rem; text-transform: uppercase; color: #64748b; font-weight: 700; }
    .summary-pill .val { font-size: 1.3rem; font-weight: 800; margin-top: 4px; }
    .summary-pill.direct { background: #f0fdf4; border-color: #10b981; }
    .summary-pill.direct .val { color: #059669; }
    .summary-pill.team { background: #f5f3ff; border-color: #8b5cf6; }
    .summary-pill.team .val { color: #7c3aed; }
    .summary-pill.total { background: #eff6ff; border-color: #2563eb; }
    .summary-pill.total .val { color: #1d4ed8; }
    .entries-table { width: 100%; font-size: 0.82rem; }
    .entries-table th {
        background: #1e293b; color: #fff; padding: 10px 8px;
        font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.4px;
    }
    .entries-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .entries-table tr:hover { background: #f8fafc; }
    .badge-type-direct { background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .badge-type-team { background: #ede9fe; color: #5b21b6; padding: 3px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .badge-pkg { background: #2563eb; color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .badge-pkg-free { background: #94a3b8; color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    @media print { .no-print { display: none !important; } body { background: #fff !important; } .slip-card { box-shadow: none !important; page-break-inside: avoid; } }
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-danger"><i class="fas fa-bug me-2"></i> Step 1: Select Subscriptions</h3>
            <a href="admin_payout_manager.php" class="btn btn-outline-primary rounded-pill px-3">
                <i class="fas fa-wallet me-1"></i> Payout Manager
            </a>
        </div>
        
        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle me-1"></i> Select subscriptions. Team Turnover will be calculated <b>ONLY from selected subscriptions</b>, and statements will be grouped by <b>Income Receiver</b>. You can then click "Generate Payouts" to save them to the database.
        </div>
        
        <form method="POST" action="?step=view">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" style="font-size: 0.85rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 50px;">
                                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)" style="width:1.3rem;height:1.3rem;">
                                    </th>
                                    <th>Sub ID</th>
                                    <th>Buyer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_subs)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No subscriptions found.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($all_subs as $sub): 
                                    $sub_status = ($sub['status'] == 'active' && $sub['end_date'] >= date('Y-m-d')) ? 'Active' : 'Expired';
                                    $badge = ($sub_status == 'Active') ? 'success' : 'secondary';
                                ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" name="selected_subs[]" value="<?= $sub['sub_id'] ?>" class="sub-checkbox" style="width:1.3rem;height:1.3rem;">
                                        </td>
                                        <td><strong>#<?= $sub['sub_id'] ?></strong></td>
                                        <td>#<?= $sub['buyer_id'] ?> <?= htmlspecialchars($sub['buyer_name']) ?></td>
                                        <td>₹ <?= number_format($sub['amount'], 2) ?></td>
                                        <td><span class="badge bg-<?= $badge ?>"><?= $sub_status ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white text-end py-3">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5">
                        <i class="fas fa-eye me-2"></i> View Receiver Statements
                    </button>
                </div>
            </div>
        </form>
        
        <script>
            function toggleAll(source) {
                var checkboxes = document.getElementsByClassName('sub-checkbox');
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = source.checked;
                }
            }
        </script>
        
    <?php else: ?>
        <!-- ========================================== -->
        <!-- STEP 2: PER-RECEIVER STATEMENTS + GENERATE -->
        <!-- ========================================== -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h3 class="fw-bold text-success"><i class="fas fa-file-invoice-dollar me-2"></i> Income Statements (Per Receiver)</h3>
            <div>
                <a href="debug_payout.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <button onclick="window.print()" class="btn btn-outline-primary rounded-pill px-4 ms-2">
                    <i class="fas fa-print me-1"></i> Print All
                </button>
            </div>
        </div>
        
        <?php if (empty($receiver_statements)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> 
                No payouts were generated from the selected subscriptions. Please check your settings or select different subscriptions.
            </div>
        <?php else: ?>
            
            <!-- Summary Alert -->
            <div class="alert alert-success py-3">
                <h5 class="fw-bold mb-2"><i class="fas fa-check-circle me-2"></i> Total Payouts to be Generated:</h5>
                <p class="mb-0">
                    <?= count($receiver_statements) ?> Income Receivers | 
                    Total Amount: <b>₹ <?= number_format(array_sum(array_column($receiver_statements, 'total')), 2) ?></b>
                </p>
            </div>

            <?php $receiver_counter = 0; ?>
            <?php foreach ($receiver_statements as $receiver_id => $stmt_data): 
                $receiver_counter++;
                $sum_direct = 0;
                $sum_team = 0;
                foreach ($stmt_data['entries'] as $e) {
                    if ($e['type'] == 'direct') $sum_direct += $e['amount'];
                    else $sum_team += $e['amount'];
                }
            ?>
                <div class="slip-card">
                    <div class="slip-header">
                        <h3>PRIME PROPERTY INDIA</h3>
                        <p>Income Statement / Salary Slip</p>
                        <p style="background:rgba(255,255,255,0.2);display:inline-block;padding:3px 12px;border-radius:20px;margin-top:6px;font-size:0.75rem;">
                            Statement #<?= $receiver_counter ?> — Generated on <?= date('d M Y, h:i A') ?>
                        </p>
                    </div>
                    <div class="slip-body">
                        <div class="receiver-info">
                            <table>
                                <tr>
                                    <td style="width:50%;"><strong>Beneficiary:</strong> <?= htmlspecialchars($stmt_data['name']) ?></td>
                                    <td style="width:50%;"><strong>User ID:</strong> #<?= $receiver_id ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <strong>Package:</strong> 
                                        <?php if (!empty($stmt_data['pkg']) && $stmt_data['pkg'] != 'Free User'): ?>
                                            <span class="badge-pkg"><?= htmlspecialchars($stmt_data['pkg']) ?></span>
                                        <?php else: ?>
                                            <span class="badge-pkg-free">Free User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>Team Turnover (Selected):</strong> 
                                        ₹ <?= number_format($stmt_data['turnover'], 2) ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="summary-pills">
                            <div class="summary-pill direct">
                                <div class="lbl">Direct Income</div>
                                <div class="val">₹ <?= number_format($sum_direct, 2) ?></div>
                            </div>
                            <div class="summary-pill team">
                                <div class="lbl">Team Turnover Income</div>
                                <div class="val">₹ <?= number_format($sum_team, 2) ?></div>
                            </div>
                            <div class="summary-pill total">
                                <div class="lbl">Net Payable</div>
                                <div class="val">₹ <?= number_format($stmt_data['total'], 2) ?></div>
                            </div>
                        </div>
                        
                        <h6 class="fw-bold mb-2"><i class="fas fa-list me-1"></i> Income Breakdown (<?= count($stmt_data['entries']) ?> entries)</h6>
                        <div class="table-responsive">
                            <table class="entries-table">
                                <thead>
                                    <tr>
                                        <th>From Buyer</th>
                                        <th>Sub ID</th>
                                        <th>Level</th>
                                        <th>Type</th>
                                        <th>%</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stmt_data['entries'] as $e): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?= $e['buyer_id'] ?> <?= htmlspecialchars($e['buyer_name']) ?></strong>
                                                <div style="font-size:0.7rem;color:#64748b;">Purchased: ₹ <?= number_format($e['buyer_amount'], 2) ?></div>
                                            </td>
                                            <td>#<?= $e['sub_id'] ?></td>
                                            <td><span class="badge bg-warning text-dark">L<?= $e['level'] ?></span></td>
                                            <td>
                                                <?php if ($e['type'] == 'direct'): ?>
                                                    <span class="badge-type-direct">Direct Diff</span>
                                                <?php else: ?>
                                                    <span class="badge-type-team">Team Turnover</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?= $e['pct'] ?>%</strong></td>
                                            <td class="text-end fw-bold text-success">₹ <?= number_format($e['amount'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background:#f0fdf4;">
                                        <td colspan="5" class="text-end fw-bold">TOTAL:</td>
                                        <td class="text-end fw-bold text-success" style="font-size:1rem;">₹ <?= number_format($stmt_data['total'], 2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <div class="text-center mt-3 text-muted" style="font-size:0.75rem;">
                            <em>This is a computer-generated statement preview. Click "Generate Payouts" below to credit wallets.</em>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- 🔥 GENERATE BUTTON -->
            <form method="POST" action="?step=view" onsubmit="return confirm('⚠️ Are you sure you want to generate these payouts? This will credit the selected receivers\\' wallets permanently.');">
                <?php foreach ($processed_sub_ids as $sub_id): ?>
                    <input type="hidden" name="selected_subs_to_process[]" value="<?= $sub_id ?>">
                <?php endforeach; ?>
                <div class="text-center mt-4 mb-5">
                    <button type="submit" name="generate_payouts_for_selected" class="btn btn-success btn-lg rounded-pill px-5 shadow">
                        <i class="fas fa-check-circle me-2"></i> Generate Payouts for Selected Subscriptions
                    </button>
                </div>
            </form>
            
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
