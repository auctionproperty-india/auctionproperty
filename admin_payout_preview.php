<?php
// ============================================================
// 💰 Admin – Payout Preview & Generate (2-Step Confirmation)
// Step 1: Select buyers → View full upline breakdown
// Step 2: Confirm → Generate payouts & credit wallets
// Uses CURRENT package % and Team Turnover slabs on every run
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
    $total_paid = 0;

    try {
        $pdo->beginTransaction();
        foreach ($selected_subs as $sub_id) {
            $stmt = $pdo->prepare("SELECT user_id, amount, package_id FROM subscriptions WHERE id = ?");
            $stmt->execute([$sub_id]);
            $sub = $stmt->fetch();
            if ($sub && $sub['amount'] > 0) {
                // Use existing distributeIncome (which does the same differential logic)
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
// PREFETCH ALL DATA (FAST)
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

// Build children map
$children_map = [];
foreach ($all_users as $uid => $u) {
    $parent = $u['referred_by'];
    if ($parent && isset($all_users[$parent])) {
        $children_map[$parent][] = $uid;
    }
}

// Helper: compute team turnover (bottom-up)
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

// Helper: get direct %
$getDirectPct = function($user_id) use (&$all_users, &$user_active_pkg, $free_user_pct) {
    if (isset($user_active_pkg[$user_id])) {
        return (float)($user_active_pkg[$user_id]['direct_income_percent'] ?? 0);
    } else {
        $enabled = !empty($all_users[$user_id]['free_user_income_enabled']);
        return $enabled ? $free_user_pct : 0;
    }
};

// Helper: get team turnover %
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
// STEP 2: BUILD PREVIEW
// ============================================================
$preview_data = [];
$processed_sub_ids = [];

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

        // Walk up the upline chain
        $chain = [];
        $current_user_id = $buyer_id;
        $last_direct_pct = 0;
        $last_team_pct = 0;
        $level = 1;
        $max_levels = 10;
        $chain_total = 0;

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
            $direct_note = '';
            if ($direct_pct > $last_direct_pct) {
                $direct_diff = $direct_pct - $last_direct_pct;
                $direct_amt = ($amount * $direct_diff) / 100;
                $last_direct_pct = $direct_pct;
            } else {
                $direct_note = $direct_pct == 0 ? 'No package / not enabled' : 'Already covered by downline';
            }

            // Team turnover diff
            $team_pct = $getTeamPct($sponsor_id);
            $team_diff = 0;
            $team_amt = 0;
            $team_note = '';
            if ($team_pct > $last_team_pct) {
                $team_diff = $team_pct - $last_team_pct;
                $team_amt = ($amount * $team_diff) / 100;
                $last_team_pct = $team_pct;
            } else {
                if ($team_pct == 0) {
                    if (!isset($user_active_pkg[$sponsor_id])) {
                        $team_note = 'No active package';
                    } elseif (empty($user_active_pkg[$sponsor_id]['is_team_turnover_eligible'])) {
                        $team_note = 'Package not eligible for team turnover';
                    } else {
                        $team_note = 'No slab matched (Turnover: ₹' . number_format($sponsor_turnover, 0) . ')';
                    }
                } else {
                    $team_note = 'Already covered by downline';
                }
            }

            $level_total = $direct_amt + $team_amt;
            $chain_total += $level_total;

            $chain[] = [
                'level' => $level,
                'sponsor_id' => $sponsor_id,
                'sponsor_name' => $sponsor_name,
                'sponsor_pkg' => $sponsor_pkg,
                'sponsor_turnover' => $sponsor_turnover,
                'direct_pct' => $direct_pct,
                'direct_diff' => $direct_diff,
                'direct_amt' => $direct_amt,
                'direct_note' => $direct_note,
                'team_pct' => $team_pct,
                'team_diff' => $team_diff,
                'team_amt' => $team_amt,
                'team_note' => $team_note,
                'level_total' => $level_total,
            ];

            $current_user_id = $sponsor_id;
            $level++;
            if ($last_direct_pct >= 100 && $last_team_pct >= 100) break;
        }

        $preview_data[] = [
            'sub_id' => $sub['sub_id'],
            'buyer_id' => $buyer_id,
            'buyer_name' => $buyer_name,
            'buyer_pkg' => $sub['pkg_name'],
            'amount' => $amount,
            'status' => ($sub['status'] == 'active' && $sub['end_date'] >= date('Y-m-d')) ? 'Active' : 'Expired',
            'chain' => $chain,
            'chain_total' => $chain_total,
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
    .preview-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        overflow: hidden;
        margin-bottom: 20px;
    }
    .preview-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 16px 22px;
    }
    .preview-header h5 {
        margin: 0;
        font-weight: 700;
        font-size: 1rem;
    }
    .preview-body { padding: 18px 22px; }
    .summary-row {
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    .summary-box {
        flex: 1;
        min-width: 120px;
        padding: 10px;
        border-radius: 10px;
        text-align: center;
        border: 2px solid #e2e8f0;
    }
    .summary-box .lbl {
        font-size: 0.65rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
    }
    .summary-box .val {
        font-size: 1.1rem;
        font-weight: 800;
        margin-top: 3px;
    }
    .summary-box.blue { background: #eff6ff; border-color: #2563eb; }
    .summary-box.blue .val { color: #1d4ed8; }
    .summary-box.green { background: #f0fdf4; border-color: #10b981; }
    .summary-box.green .val { color: #059669; }
    .summary-box.purple { background: #f5f3ff; border-color: #8b5cf6; }
    .summary-box.purple .val { color: #7c3aed; }
    .summary-box.total { background: #fef3c7; border-color: #f59e0b; }
    .summary-box.total .val { color: #b45309; }

    .chain-table {
        width: 100%;
        font-size: 0.82rem;
        border-collapse: collapse;
    }
    .chain-table th {
        background: #1e293b;
        color: #fff;
        padding: 10px 8px;
        font-size: 0.7rem;
        text-transform: uppercase;
        text-align: left;
    }
    .chain-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .chain-table tr:hover { background: #f8fafc; }
    .badge-level { background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; }
    .badge-pkg { background: #2563eb; color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .badge-pkg-free { background: #94a3b8; color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
    .amt-direct { color: #059669; font-weight: 700; }
    .amt-team { color: #7c3aed; font-weight: 700; }
    .amt-total { color: #0f172a; font-weight: 800; font-size: 0.9rem; }
    .note-text { color: #94a3b8; font-size: 0.72rem; font-style: italic; }

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
    .confirm-bar .total-info h4 { margin: 0; font-weight: 800; font-size: 1.3rem; }
    .confirm-bar .total-info p { margin: 4px 0 0; font-size: 0.85rem; opacity: 0.9; }

    .select-all-bar {
        background: #f8fafc;
        padding: 10px 16px;
        border-radius: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        border: 1px solid #e2e8f0;
    }
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
            <a href="admin_payout_manager.php" class="btn btn-outline-primary rounded-pill px-3">
                <i class="fas fa-wallet me-1"></i> Payout Manager
            </a>
        </div>

        <div class="alert alert-info py-2 small">
            <i class="fas fa-info-circle me-1"></i>
            Select subscriptions to preview payouts. Team Turnover is calculated from <b>ALL active subscriptions</b> in the system. Current package % and Team Turnover slabs are used.
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
                                                <span class="badge-pkg"><?= htmlspecialchars($sub['pkg_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge-pkg-free">—</span>
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
        <!-- STEP 2: PREVIEW & CONFIRM                 -->
        <!-- ========================================== -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="fw-bold text-success"><i class="fas fa-check-circle me-2"></i> Payout Preview – Step 2</h3>
            <a href="admin_payout_preview.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i> Back to Selection
            </a>
        </div>

        <?php if (empty($preview_data)): ?>
            <div class="alert alert-warning">No subscriptions selected. Please go back.</div>
        <?php else:
            $grand_total = 0;
            foreach ($preview_data as $pd) $grand_total += $pd['chain_total'];
        ?>

        <!-- Overall Summary -->
        <div class="alert alert-success py-3">
            <h5 class="fw-bold mb-2"><i class="fas fa-info-circle me-1"></i> Preview Summary</h5>
            <div class="row">
                <div class="col-md-4">
                    <small>Buyers Selected:</small>
                    <div class="fw-bold fs-5"><?= count($preview_data) ?></div>
                </div>
                <div class="col-md-4">
                    <small>Total Upline Payments:</small>
                    <div class="fw-bold fs-5">
                        <?php 
                        $total_entries = 0;
                        foreach ($preview_data as $pd) {
                            foreach ($pd['chain'] as $c) {
                                if ($c['level_total'] > 0) $total_entries++;
                            }
                        }
                        echo $total_entries;
                        ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <small>Grand Total Payout:</small>
                    <div class="fw-bold fs-5 text-success">₹ <?= number_format($grand_total, 2) ?></div>
                </div>
            </div>
        </div>

        <!-- Buyer-wise breakdown -->
        <?php $idx = 0; foreach ($preview_data as $pd): $idx++; ?>
            <div class="preview-card">
                <div class="preview-header">
                    <h5>
                        <i class="fas fa-user me-2"></i> 
                        Buyer #<?= $idx ?>: <?= htmlspecialchars($pd['buyer_name']) ?> (#<?= $pd['buyer_id'] ?>)
                        <?php if ($pd['buyer_pkg']): ?>
                            — <span style="background: rgba(255,255,255,0.25); padding: 2px 10px; border-radius: 20px; font-size: 0.75rem;"><?= htmlspecialchars($pd['buyer_pkg']) ?></span>
                        <?php endif; ?>
                    </h5>
                    <div style="margin-top: 6px; font-size: 0.82rem; opacity: 0.9;">
                        Subscription #<?= $pd['sub_id'] ?> | Amount: ₹ <?= number_format($pd['amount'], 2) ?> | Status: <?= $pd['status'] ?>
                    </div>
                </div>
                <div class="preview-body">

                    <!-- Summary -->
                    <?php
                    $sum_direct = 0;
                    $sum_team = 0;
                    foreach ($pd['chain'] as $c) {
                        $sum_direct += $c['direct_amt'];
                        $sum_team += $c['team_amt'];
                    }
                    ?>
                    <div class="summary-row">
                        <div class="summary-box blue">
                            <div class="lbl">Buyer Amount</div>
                            <div class="val">₹ <?= number_format($pd['amount'], 2) ?></div>
                        </div>
                        <div class="summary-box green">
                            <div class="lbl">Direct Payout</div>
                            <div class="val">₹ <?= number_format($sum_direct, 2) ?></div>
                        </div>
                        <div class="summary-box purple">
                            <div class="lbl">Team Turnover</div>
                            <div class="val">₹ <?= number_format($sum_team, 2) ?></div>
                        </div>
                        <div class="summary-box total">
                            <div class="lbl">Total Payout</div>
                            <div class="val">₹ <?= number_format($pd['chain_total'], 2) ?></div>
                        </div>
                    </div>

                    <!-- Upline Chain -->
                    <h6 class="fw-bold mb-2"><i class="fas fa-sitemap me-1"></i> Upline Chain Breakdown</h6>
                    <div class="table-responsive">
                        <table class="chain-table">
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Beneficiary</th>
                                    <th>Package</th>
                                    <th>Team Turnover</th>
                                    <th class="text-end">Direct Diff</th>
                                    <th class="text-end">Team Diff</th>
                                    <th class="text-end">Level Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pd['chain'])): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-3">No upline chain found.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($pd['chain'] as $c): ?>
                                    <tr>
                                        <td><span class="badge-level">L<?= $c['level'] ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($c['sponsor_name']) ?></strong>
                                            <div style="font-size: 0.7rem; color: #64748b;">#<?= $c['sponsor_id'] ?></div>
                                        </td>
                                        <td>
                                            <?php if ($c['sponsor_pkg'] == 'Free User'): ?>
                                                <span class="badge-pkg-free">Free User</span>
                                            <?php else: ?>
                                                <span class="badge-pkg"><?= htmlspecialchars($c['sponsor_pkg']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>₹ <?= number_format($c['sponsor_turnover'], 0) ?></td>
                                        <td class="text-end">
                                            <?php if ($c['direct_amt'] > 0): ?>
                                                <span class="amt-direct">₹ <?= number_format($c['direct_amt'], 2) ?></span>
                                                <div style="font-size: 0.7rem; color: #64748b;"><?= $c['direct_diff'] ?>%</div>
                                            <?php else: ?>
                                                <span class="note-text">—</span>
                                                <?php if ($c['direct_note']): ?>
                                                    <div class="note-text"><?= htmlspecialchars($c['direct_note']) ?></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($c['team_amt'] > 0): ?>
                                                <span class="amt-team">₹ <?= number_format($c['team_amt'], 2) ?></span>
                                                <div style="font-size: 0.7rem; color: #64748b;"><?= $c['team_diff'] ?>%</div>
                                            <?php else: ?>
                                                <span class="note-text">—</span>
                                                <?php if ($c['team_note']): ?>
                                                    <div class="note-text"><?= htmlspecialchars($c['team_note']) ?></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end amt-total">₹ <?= number_format($c['level_total'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f0fdf4;">
                                    <td colspan="6" class="text-end fw-bold">Chain Total:</td>
                                    <td class="text-end fw-bold text-success">₹ <?= number_format($pd['chain_total'], 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Confirm Bar -->
        <form method="POST" action="?step=preview" onsubmit="return confirm('⚠️ Are you sure? This will PERMANENTLY credit ₹<?= number_format($grand_total, 2) ?> to the selected uplines\' wallets.\n\nClick OK to confirm.');">
            <?php foreach ($processed_sub_ids as $sid): ?>
                <input type="hidden" name="selected_subs[]" value="<?= $sid ?>">
            <?php endforeach; ?>
            
            <div class="confirm-bar">
                <div class="total-info">
                    <h4>₹ <?= number_format($grand_total, 2) ?> — Total Payout</h4>
                    <p>यह पूरी राशि confirm करने पर uplines के wallets में जाएगी।</p>
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
