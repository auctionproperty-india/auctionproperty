<?php
// ============================================================
// 💰 Admin – Generate Payouts (Select Subscriptions → Preview → Generate)
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
// 🔥 ACTION: GENERATE PAYOUTS FOR SELECTED SUBSCRIPTIONS
// ============================================================
if (isset($_POST['generate_payouts']) && !empty($_POST['selected_subs'])) {
    $selected_subs = array_map('intval', $_POST['selected_subs']);
    $batch_id = 'PAYOUT_' . date('Ymd_His');
    $count = 0;
    $total_amount = 0;
    
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
        $message = "✅ Successfully generated payouts for <b>$count</b> subscriptions!<br>Batch ID: <b>$batch_id</b><br>Check <a href='admin_payout_manager.php' target='_blank'>Payout Manager</a> to verify.";
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

// Helper functions
function getDirectPct($user_id, &$all_users, &$user_active_pkg, $free_user_pct) {
    $is_free = !isset($user_active_pkg[$user_id]);
    if ($is_free) {
        $enabled = isset($all_users[$user_id]['free_user_income_enabled']) && $all_users[$user_id]['free_user_income_enabled'];
        return $enabled ? $free_user_pct : 0;
    } else {
        return (float)($user_active_pkg[$user_id]['direct_income_percent'] ?? 0);
    }
}

function getTeamPct($user_id, $turnover, &$team_slabs, &$user_active_pkg) {
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
// STEP 2: BUILD PREVIEW FOR SELECTED SUBSCRIPTIONS
// ============================================================
$receiver_statements = [];
$processed_sub_ids = [];

if ($step == 'preview' && !empty($_POST['selected_subs'])) {
    $selected_subs = array_map('intval', $_POST['selected_subs']);
    $processed_sub_ids = $selected_subs;
    $placeholders = implode(',', array_fill(0, count($selected_subs), '?'));

    // Fetch volumes ONLY from selected subscriptions (for accurate team turnover)
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

    // Compute team turnover
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
    $selected_sub_details = $stmt->fetchAll();

    // Walk up chain for each selected sub
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
            $direct_pct = getDirectPct($sponsor_id, $all_users, $user_active_pkg, $free_user_pct);
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
            $team_pct = getTeamPct($sponsor_id, $sponsor_turnover, $team_slabs, $user_active_pkg);
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
// STEP 1: Fetch all ACTIVE subscriptions
// ============================================================
$active_subs = [];
if ($step == 'select') {
    $stmt = $pdo->query("
        SELECT s.id as sub_id, s.user_id, s.amount, s.status, s.end_date,
               u.name as buyer_name, u.id as buyer_id,
               p.name as pkg_name, p.direct_income_percent
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN packages p ON s.package_id = p.id
        WHERE s.status = 'active' AND s.end_date >= CURRENT_DATE
        ORDER BY s.id DESC
    ");
    $active_subs = $stmt->fetchAll();
}

include 'header.php';
?>

<style>
    .sub-card {
        background: #fff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        overflow: hidden;
        margin-bottom: 15px;
    }
    .sub-card-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 12px 20px;
        font-weight: 700;
    }
    .sub-card-body { padding: 18px 20px; }
    .pkg-badge {
        background: #2563eb; color: #fff; padding: 4px 12px;
        border-radius: 20px; font-weight: 700; font-size: 0.78rem;
    }
    .pkg-badge-free {
        background: #94a3b8; color: #fff; padding: 4px 12px;
        border-radius: 20px; font-weight: 700; font-size: 0.78rem;
    }
    .entries-table { width: 100%; font-size: 0.82rem; }
    .entries-table th {
        background: #1e293b; color: #fff; padding: 10px 8px;
        font-size: 0.7rem; text-transform: uppercase;
    }
    .entries-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; }
    .badge-direct { background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .badge-team { background: #ede9fe; color: #5b21b6; padding: 3px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .table-subs { font-size: 0.85rem; }
    .table-subs th { background: #1e293b; color: #fff; padding: 12px 8px; font-size: 0.72rem; text-transform: uppercase; }
    .table-subs td { padding: 12px 8px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
    .table-subs tr:hover { background: #f8fafc; }
</style>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h3 class="fw-bold text-primary"><i class="fas fa-hand-holding-usd me-2"></i> Generate Payouts</h3>
        <div>
            <a href="admin_payout_manager.php" class="btn btn-outline-primary rounded-pill px-3">
                <i class="fas fa-wallet me-1"></i> Payout Manager
            </a>
            <a href="debug_payout.php" class="btn btn-outline-info rounded-pill px-3">
                <i class="fas fa-bug me-1"></i> Debug Tool
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($step == 'select'): ?>
        <!-- ========================================== -->
        <!-- STEP 1: SELECT ACTIVE SUBSCRIPTIONS        -->
        <!-- ========================================== -->
        
        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle me-1"></i> 
            यहाँ सभी <b>Active Subscriptions</b> दिख रहे हैं, उनके वर्तमान पैकेज के नाम के साथ। जिन-जिन सब्सक्रिप्शन्स का पेआउट जनरेट करना है, उन्हें <b>चेकबॉक्स से सेलेक्ट करें</b> और नीचे <b>"Preview & Generate"</b> दबाएं।
        </div>

        <form method="POST" action="?step=preview">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-dark text-white rounded-top-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-list-check me-2"></i> Active Subscriptions (<?= count($active_subs) ?>)</h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-light" onclick="toggleAllCheckbox(true)">
                            <i class="fas fa-check-square me-1"></i> Select All
                        </button>
                        <button type="button" class="btn btn-sm btn-light" onclick="toggleAllCheckbox(false)">
                            <i class="fas fa-square me-1"></i> Deselect All
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($active_subs)): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            कोई Active Subscription नहीं मिला। कृपया पहले <a href="admin_extend_subscriptions.php">Extend Subscriptions</a> पेज से सब्सक्रिप्शन्स को Active करें।
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-subs">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">
                                            <input type="checkbox" id="selectAll" onclick="toggleAllCheckbox(this.checked)" style="width:1.3rem;height:1.3rem;">
                                        </th>
                                        <th>Sub ID</th>
                                        <th>Buyer</th>
                                        <th>Package</th>
                                        <th class="text-end">Amount</th>
                                        <th>Start → End Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_subs as $sub): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_subs[]" value="<?= $sub['sub_id'] ?>" class="sub-checkbox" style="width:1.3rem;height:1.3rem;">
                                            </td>
                                            <td><strong>#<?= $sub['sub_id'] ?></strong></td>
                                            <td>
                                                <strong><?= htmlspecialchars($sub['buyer_name']) ?></strong>
                                                <div style="font-size:0.72rem;color:#64748b;">#<?= $sub['buyer_id'] ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($sub['pkg_name'])): ?>
                                                    <span class="pkg-badge"><?= htmlspecialchars($sub['pkg_name']) ?></span>
                                                    <div style="font-size:0.7rem;color:#64748b;margin-top:3px;">Direct: <?= $sub['direct_income_percent'] ?>%</div>
                                                <?php else: ?>
                                                    <span class="pkg-badge-free">Unknown</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-bold">₹ <?= number_format($sub['amount'], 2) ?></td>
                                            <td style="font-size:0.75rem;">
                                                <?= date('d M Y', strtotime($sub['end_date'])) ?><br>
                                                <small class="text-muted">Expires</small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($active_subs)): ?>
                    <div class="card-footer bg-white text-end py-3">
                        <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5">
                            <i class="fas fa-eye me-2"></i> Preview & Generate Payouts
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <script>
            function toggleAllCheckbox(state) {
                var checkboxes = document.getElementsByClassName('sub-checkbox');
                var selectAll = document.getElementById('selectAll');
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = state;
                }
                if (selectAll) selectAll.checked = state;
            }
        </script>

    <?php else: ?>
        <!-- ========================================== -->
        <!-- STEP 2: PREVIEW + GENERATE                 -->
        <!-- ========================================== -->
        
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h3 class="fw-bold text-success"><i class="fas fa-check-circle me-2"></i> Payout Preview</h3>
            <a href="admin_generate_payout.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="fas fa-arrow-left me-1"></i> Back to Selection
            </a>
        </div>

        <?php if (empty($receiver_statements)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> 
                No payouts would be generated from the selected subscriptions. Please check settings.
            </div>
        <?php else: ?>

            <!-- Summary -->
            <div class="alert alert-success py-3">
                <div class="row">
                    <div class="col-md-4">
                        <small>Subscriptions Selected:</small>
                        <div class="fw-bold fs-4"><?= count($processed_sub_ids) ?></div>
                    </div>
                    <div class="col-md-4">
                        <small>Income Receivers:</small>
                        <div class="fw-bold fs-4"><?= count($receiver_statements) ?></div>
                    </div>
                    <div class="col-md-4">
                        <small>Total Payout:</small>
                        <div class="fw-bold fs-4 text-success">₹ <?= number_format(array_sum(array_column($receiver_statements, 'total')), 2) ?></div>
                    </div>
                </div>
            </div>

            <!-- Receiver Breakdown -->
            <?php $rc = 0; foreach ($receiver_statements as $rid => $sd): 
                $rc++;
                $sum_d = 0; $sum_t = 0;
                foreach ($sd['entries'] as $e) {
                    if ($e['type'] == 'direct') $sum_d += $e['amount']; else $sum_t += $e['amount'];
                }
            ?>
                <div class="sub-card">
                    <div class="sub-card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-user-circle me-2"></i> Receiver #<?= $rc ?>: <?= htmlspecialchars($sd['name']) ?> (#<?= $rid ?>)</span>
                        <span class="badge bg-white text-dark"><?= htmlspecialchars($sd['pkg']) ?></span>
                    </div>
                    <div class="sub-card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="p-2 text-center border rounded">
                                    <small class="text-muted">Team Turnover</small>
                                    <div class="fw-bold">₹ <?= number_format($sd['turnover'], 2) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 text-center border rounded bg-success text-white">
                                    <small>Direct Income</small>
                                    <div class="fw-bold">₹ <?= number_format($sum_d, 2) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 text-center border rounded bg-primary text-white">
                                    <small>Team Turnover</small>
                                    <div class="fw-bold">₹ <?= number_format($sum_t, 2) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-2 text-center border rounded bg-dark text-white">
                                    <small>Net Payable</small>
                                    <div class="fw-bold">₹ <?= number_format($sd['total'], 2) ?></div>
                                </div>
                            </div>
                        </div>
                        
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
                                <?php foreach ($sd['entries'] as $e): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= $e['buyer_id'] ?> <?= htmlspecialchars($e['buyer_name']) ?></strong>
                                            <div style="font-size:0.7rem;color:#64748b;">Bought: ₹ <?= number_format($e['buyer_amount'], 2) ?></div>
                                        </td>
                                        <td>#<?= $e['sub_id'] ?></td>
                                        <td><span class="badge bg-warning text-dark">L<?= $e['level'] ?></span></td>
                                        <td>
                                            <?php if ($e['type'] == 'direct'): ?>
                                                <span class="badge-direct">Direct</span>
                                            <?php else: ?>
                                                <span class="badge-team">Team</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $e['pct'] ?>%</td>
                                        <td class="text-end fw-bold text-success">₹ <?= number_format($e['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Generate Button -->
            <form method="POST" action="?step=preview" onsubmit="return confirm('⚠️ Are you sure? This will PERMANENTLY credit these amounts to the receivers\\' wallets.');">
                <?php foreach ($processed_sub_ids as $sid): ?>
                    <input type="hidden" name="selected_subs[]" value="<?= $sid ?>">
                <?php endforeach; ?>
                
                <div class="text-center mt-4 mb-5">
                    <button type="submit" name="generate_payouts" class="btn btn-success btn-lg rounded-pill px-5 shadow">
                        <i class="fas fa-check-circle me-2"></i> Confirm & Generate Payouts Now
                    </button>
                </div>
            </form>

        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
