<?php
// ============================================================
// 🐞 DEBUG PAYOUT TOOL (2-Step: Select → View Breakdown & Statement)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Admin access required.");
}

$step = $_GET['step'] ?? 'select'; // 'select' or 'view'

// ============================================================
// 🔥 PREFETCH ALL DATA (Fast)
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

// Build children map & compute team turnover
$children_map = [];
foreach ($all_users as $uid => $u) {
    $parent = $u['referred_by'];
    if ($parent && isset($all_users[$parent])) {
        $children_map[$parent][] = $uid;
    }
}

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

// Helper closures
$getDirectPct = function($user_id) use (&$all_users, &$user_active_pkg, $free_user_pct) {
    $is_free = !isset($user_active_pkg[$user_id]);
    if ($is_free) {
        $enabled = isset($all_users[$user_id]['free_user_income_enabled']) && $all_users[$user_id]['free_user_income_enabled'];
        return $enabled ? $free_user_pct : 0;
    } else {
        return (float)($user_active_pkg[$user_id]['direct_income_percent'] ?? 0);
    }
};

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
// 🔥 STEP 2: PROCESS SELECTED SUBSCRIPTIONS (Build breakdown)
// ============================================================
$selected_data = [];

if ($step == 'view' && !empty($_POST['selected_subs'])) {
    $selected_subs = $_POST['selected_subs'];
    
    foreach ($selected_subs as $sub_id) {
        $sub_id = (int)$sub_id;
        $stmt = $pdo->prepare("
            SELECT s.id as sub_id, s.user_id, s.amount, s.package_id, s.status, s.end_date,
                   u.name as buyer_name, u.id as buyer_id
            FROM subscriptions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$sub_id]);
        $sub = $stmt->fetch();
        if (!$sub) continue;
        
        $buyer_id = $sub['buyer_id'];
        $amount = (float)$sub['amount'];
        $buyer_name = $sub['buyer_name'];
        $sub_status = ($sub['status'] == 'active' && $sub['end_date'] >= date('Y-m-d')) ? 'Active' : 'Expired';
        
        $chain = [];
        $current_user_id = $buyer_id;
        $last_direct_pct = 0;
        $last_team_pct = 0;
        $level = 1;
        $max_levels = 10;
        $grand_total = 0;
        
        while ($level <= $max_levels) {
            $sponsor_id = $all_users[$current_user_id]['referred_by'] ?? null;
            if (!$sponsor_id || !isset($all_users[$sponsor_id])) break;
            
            $sponsor_name = $all_users[$sponsor_id]['name'] ?? 'Unknown';
            $sponsor_pkg_name = $user_active_pkg[$sponsor_id]['pkg_name'] ?? 'Free User';
            
            $direct_pct = $getDirectPct($sponsor_id);
            $direct_diff = 0;
            $direct_amt = 0;
            $direct_reason = '';
            
            if ($direct_pct > $last_direct_pct) {
                $direct_diff = $direct_pct - $last_direct_pct;
                $direct_amt = ($amount * $direct_diff) / 100;
                $last_direct_pct = $direct_pct;
            } else {
                if ($direct_pct == 0) {
                    if (!isset($user_active_pkg[$sponsor_id])) {
                        $enabled = $all_users[$sponsor_id]['free_user_income_enabled'] ?? false;
                        $direct_reason = $enabled ? "Free User (0% Global)" : "Free User (Income Disabled)";
                    } else {
                        $direct_reason = "Package % is 0";
                    }
                } else {
                    $direct_reason = "No higher gap than previous level";
                }
            }
            
            $team_pct = $getTeamPct($sponsor_id);
            $team_diff = 0;
            $team_amt = 0;
            $team_reason = '';
            $sponsor_turnover = $team_turnover_cache[$sponsor_id] ?? 0;
            
            if ($team_pct > $last_team_pct) {
                $team_diff = $team_pct - $last_team_pct;
                $team_amt = ($amount * $team_diff) / 100;
                $last_team_pct = $team_pct;
            } else {
                if ($team_pct == 0) {
                    if (!isset($user_active_pkg[$sponsor_id])) {
                        $team_reason = "No Active Package (Not eligible)";
                    } elseif (empty($user_active_pkg[$sponsor_id]['is_team_turnover_eligible'])) {
                        $team_reason = "Package Not Team-Eligible";
                    } else {
                        $team_reason = "No Slab Matched (Turnover: ₹" . number_format($sponsor_turnover, 2) . ")";
                    }
                } else {
                    $team_reason = "No higher gap than previous level";
                }
            }
            
            $level_total = $direct_amt + $team_amt;
            $grand_total += $level_total;
            
            $chain[] = [
                'level' => $level,
                'sponsor_id' => $sponsor_id,
                'sponsor_name' => $sponsor_name,
                'sponsor_pkg' => $sponsor_pkg_name,
                'sponsor_turnover' => $sponsor_turnover,
                'direct_pct' => $direct_pct,
                'direct_diff' => $direct_diff,
                'direct_amt' => $direct_amt,
                'direct_reason' => $direct_reason,
                'team_pct' => $team_pct,
                'team_diff' => $team_diff,
                'team_amt' => $team_amt,
                'team_reason' => $team_reason,
                'level_total' => $level_total,
            ];
            
            $current_user_id = $sponsor_id;
            $level++;
            if ($last_direct_pct >= 100 && $last_team_pct >= 100) break;
        }
        
        $selected_data[] = [
            'sub_id' => $sub_id,
            'buyer_id' => $buyer_id,
            'buyer_name' => $buyer_name,
            'amount' => $amount,
            'sub_status' => $sub_status,
            'chain' => $chain,
            'grand_total' => $grand_total,
        ];
    }
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
    .chain-box {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px;
        border: 1px solid #e2e8f0;
    }
    .level-item {
        padding: 8px 10px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.82rem;
    }
    .level-item:last-child { border-bottom: none; }
    .stmt-slip {
        background: #fff;
        border: 2px solid #1e3a8a;
        border-radius: 16px;
        overflow: hidden;
        margin-top: 20px;
    }
    .stmt-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 18px;
        text-align: center;
    }
    .stmt-header h4 { margin: 0; font-weight: 800; letter-spacing: 1px; }
    .stmt-body { padding: 18px; }
    .stmt-table { width: 100%; font-size: 0.85rem; }
    .stmt-table th {
        background: #1e293b;
        color: #fff;
        padding: 10px;
        font-size: 0.75rem;
        text-transform: uppercase;
    }
    .stmt-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
    .stmt-summary {
        background: #f0fdf4;
        border-radius: 10px;
        padding: 14px;
        display: flex;
        justify-content: space-between;
        font-weight: 800;
        font-size: 1.1rem;
        color: #166534;
    }
    @media print {
        .no-print { display: none !important; }
        body { background: #fff !important; }
    }
</style>

<div class="container mt-4 mb-5">
    <?php if ($step == 'select'): ?>
        <!-- ========================================== -->
        <!-- STEP 1: SELECT SUBSCRIPTIONS               -->
        <!-- ========================================== -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-danger"><i class="fas fa-bug me-2"></i> Step 1: Select Subscriptions to Debug</h3>
        </div>
        
        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle me-1"></i> Select the subscriptions you want to debug. Then click <b>"View Breakdown"</b> to see full details with statement preview.
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
                        <i class="fas fa-eye me-2"></i> View Breakdown
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
        <!-- STEP 2: VIEW BREAKDOWN + STATEMENT PREVIEW -->
        <!-- ========================================== -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h3 class="fw-bold text-success"><i class="fas fa-check-circle me-2"></i> Payout Breakdown & Statement Preview</h3>
            <div>
                <a href="debug_payout.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-arrow-left me-1"></i> Back to Selection
                </a>
                <button onclick="window.print()" class="btn btn-outline-primary rounded-pill px-4 ms-2">
                    <i class="fas fa-print me-1"></i> Print All
                </button>
            </div>
        </div>
        
        <?php if (empty($selected_data)): ?>
            <div class="alert alert-warning">No subscriptions were selected. Please go back and select at least one.</div>
        <?php endif; ?>
        
        <?php foreach ($selected_data as $data): ?>
            
            <!-- Buyer Info Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white rounded-top-4">
                    <h5 class="mb-0">
                        <i class="fas fa-user me-2"></i> 
                        Subscription #<?= $data['sub_id'] ?> — Buyer: <?= htmlspecialchars($data['buyer_name']) ?> (#<?= $data['buyer_id'] ?>)
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Summary Badges -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <div class="p-2 text-center border rounded">
                                <small class="text-muted">Amount</small>
                                <div class="fw-bold fs-5">₹ <?= number_format($data['amount'], 2) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 text-center border rounded">
                                <small class="text-muted">Status</small>
                                <div class="fw-bold fs-5">
                                    <span class="badge bg-<?= $data['sub_status'] == 'Active' ? 'success' : 'secondary' ?>"><?= $data['sub_status'] ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 text-center border rounded">
                                <small class="text-muted">Chain Levels</small>
                                <div class="fw-bold fs-5"><?= count($data['chain']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 text-center border rounded bg-success text-white">
                                <small>Total Chain Payout</small>
                                <div class="fw-bold fs-5">₹ <?= number_format($data['grand_total'], 2) ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chain Breakdown -->
                    <h6 class="fw-bold mb-2"><i class="fas fa-sitemap me-1"></i> Upline Chain Breakdown</h6>
                    <div class="chain-box mb-4">
                        <?php if (empty($data['chain'])): ?>
                            <div class="text-muted p-3">No upline chain found.</div>
                        <?php else: ?>
                            <?php foreach ($data['chain'] as $item): ?>
                                <div class="level-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>L<?= $item['level'] ?>: #<?= $item['sponsor_id'] ?> <?= htmlspecialchars($item['sponsor_name']) ?></strong>
                                            <span class="badge bg-info text-dark ms-1"><?= htmlspecialchars($item['sponsor_pkg']) ?></span>
                                            <?php if ($item['sponsor_turnover'] > 0): ?>
                                                <small class="text-muted d-block">Team Turnover: ₹ <?= number_format($item['sponsor_turnover'], 2) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($item['direct_amt'] > 0): ?>
                                                <div class="text-success small">Direct: ₹<?= number_format($item['direct_amt'], 2) ?> (<?= $item['direct_diff'] ?>%)</div>
                                            <?php endif; ?>
                                            <?php if ($item['team_amt'] > 0): ?>
                                                <div class="text-primary small">Team: ₹<?= number_format($item['team_amt'], 2) ?> (<?= $item['team_diff'] ?>%)</div>
                                            <?php endif; ?>
                                            <div class="fw-bold mt-1">Total: ₹<?= number_format($item['level_total'], 2) ?></div>
                                        </div>
                                    </div>
                                    <?php if (!empty($item['direct_reason']) || !empty($item['team_reason'])): ?>
                                        <div class="text-danger small mt-1">
                                            <?php if (!empty($item['direct_reason'])): ?>
                                                <i class="fas fa-info-circle"></i> Direct: <?= htmlspecialchars($item['direct_reason']) ?>
                                            <?php endif; ?>
                                            <?php if (!empty($item['team_reason'])): ?>
                                                <br><i class="fas fa-info-circle"></i> Team: <?= htmlspecialchars($item['team_reason']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Statement Preview (Salary Slip Style) -->
                    <h6 class="fw-bold mb-2"><i class="fas fa-file-invoice-dollar me-1"></i> Statement Preview (Demo)</h6>
                    <div class="stmt-slip">
                        <div class="stmt-header">
                            <h4>PRIME PROPERTY INDIA</h4>
                            <p class="mb-0 small">Income Statement / Salary Slip</p>
                        </div>
                        <div class="stmt-body">
                            <table class="table table-sm mb-3">
                                <tr>
                                    <td><strong>Buyer:</strong> <?= htmlspecialchars($data['buyer_name']) ?> (#<?= $data['buyer_id'] ?>)</td>
                                    <td><strong>Subscription ID:</strong> #<?= $data['sub_id'] ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Purchase Amount:</strong> ₹ <?= number_format($data['amount'], 2) ?></td>
                                    <td><strong>Status:</strong> <?= $data['sub_status'] ?></td>
                                </tr>
                            </table>
                            
                            <table class="stmt-table">
                                <thead>
                                    <tr>
                                        <th>Level</th>
                                        <th>Beneficiary</th>
                                        <th>Package</th>
                                        <th>Income Type</th>
                                        <th>%</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($data['chain'])): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-3">No income generated.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($data['chain'] as $item): ?>
                                        <?php if ($item['direct_amt'] > 0): ?>
                                            <tr>
                                                <td>L<?= $item['level'] ?></td>
                                                <td>#<?= $item['sponsor_id'] ?> <?= htmlspecialchars($item['sponsor_name']) ?></td>
                                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($item['sponsor_pkg']) ?></span></td>
                                                <td><span class="text-success">Direct Diff</span></td>
                                                <td><?= $item['direct_diff'] ?>%</td>
                                                <td class="text-end fw-bold">₹ <?= number_format($item['direct_amt'], 2) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($item['team_amt'] > 0): ?>
                                            <tr>
                                                <td>L<?= $item['level'] ?></td>
                                                <td>#<?= $item['sponsor_id'] ?> <?= htmlspecialchars($item['sponsor_name']) ?></td>
                                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($item['sponsor_pkg']) ?></span></td>
                                                <td><span class="text-primary">Team Turnover Diff</span></td>
                                                <td><?= $item['team_diff'] ?>%</td>
                                                <td class="text-end fw-bold">₹ <?= number_format($item['team_amt'], 2) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="stmt-summary mt-3">
                                <span>NET TOTAL CHAIN PAYOUT:</span>
                                <span>₹ <?= number_format($data['grand_total'], 2) ?></span>
                            </div>
                            
                            <div class="text-center mt-3 text-muted small">
                                <em>This is a demo statement preview (not yet credited).</em>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        <?php endforeach; ?>
        
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
