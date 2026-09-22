<?php
// ============================================================
// 🐞 FAST DEBUG & SELECTIVE PAYOUT TOOL (With Reason)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Admin access required.");
}

$message = '';
$message_type = '';

if (isset($_POST['generate_selected']) && !empty($_POST['selected_subs'])) {
    $selected_subs = $_POST['selected_subs'];
    $batch_id = 'MANUAL_' . date('Ymd_His');
    $count = 0;
    
    try {
        $pdo->beginTransaction();
        foreach ($selected_subs as $sub_id) {
            $sub_id = (int)$sub_id;
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
        $message = "✅ Successfully generated payouts for <b>$count</b> selected subscriptions.<br>Batch ID: <b>$batch_id</b>";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// 🔥 PREFETCH ALL DATA
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

$children_map = [];
foreach ($all_users as $uid => $u) {
    $parent = $u['referred_by'];
    if ($parent && isset($all_users[$parent])) {
        $children_map[$parent][] = $uid;
    }
}

$team_turnover_cache = [];
function computeTeamTurnover($uid, &$children_map, &$active_subs_by_user, &$cache) {
    if (isset($cache[$uid])) return $cache[$uid];
    $total = 0;
    if (isset($children_map[$uid])) {
        foreach ($children_map[$uid] as $child_id) {
            $total += isset($active_subs_by_user[$child_id]) ? $active_subs_by_user[$child_id] : 0;
            $total += computeTeamTurnover($child_id, $children_map, $active_subs_by_user, $cache);
        }
    }
    $cache[$uid] = $total;
    return $total;
}

foreach ($all_users as $uid => $u) {
    computeTeamTurnover($uid, $children_map, $active_subs_by_user, $team_turnover_cache);
}

function getDirectPctInMemory($user_id, &$all_users, &$user_active_pkg, $free_user_pct) {
    $is_free = !isset($user_active_pkg[$user_id]);
    if ($is_free) {
        $enabled = isset($all_users[$user_id]['free_user_income_enabled']) && $all_users[$user_id]['free_user_income_enabled'];
        if ($enabled) return $free_user_pct;
        return 0;
    } else {
        return (float)($user_active_pkg[$user_id]['direct_income_percent'] ?? 0);
    }
}

function getTeamTurnoverPctInMemory($user_id, &$team_turnover_cache, &$team_slabs, &$user_active_pkg) {
    if (!isset($user_active_pkg[$user_id])) return 0; // Free user, not eligible for Team Turnover
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
}

$stmt = $pdo->query("
    SELECT s.id as sub_id, s.user_id, s.amount, s.package_id, s.status, s.end_date, 
           u.name as buyer_name, u.referred_by, u.id as buyer_id
    FROM subscriptions s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.status IN ('active', 'expired')
    ORDER BY s.id DESC
");
$subs = $stmt->fetchAll();

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-danger"><i class="fas fa-bug me-2"></i> Payout Debugger (With Reasons)</h3>
        <a href="admin_payout_backfill.php" class="btn btn-outline-primary rounded-pill px-4">Go to Backfill Tool</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info py-2 small">
        <i class="fas fa-info-circle me-1"></i> Check the "Reason" column below to see exactly why a payout is 0 for a specific user.
    </div>

    <form method="POST">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle" style="font-size: 0.85rem;">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                                <th>Sub ID</th>
                                <th>Buyer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th style="min-width: 400px;">Payout Chain & Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($subs) == 0): ?>
                                <tr><td colspan="6" class="text-center text-danger py-4">No active or expired subscriptions found.</td></tr>
                            <?php endif; ?>

                            <?php foreach ($subs as $sub):
                                $sub_id = $sub['sub_id'];
                                $buyer_id = $sub['buyer_id'];
                                $amount = (float)$sub['amount'];
                                $buyer_name = $sub['buyer_name'];
                                $sub_status = ($sub['status'] == 'active' && $sub['end_date'] >= date('Y-m-d')) ? 'Active' : 'Expired';
                                $status_color = ($sub_status == 'Active') ? 'success' : 'secondary';
                                
                                $chain_html = '';
                                $current_user_id = $buyer_id;
                                $last_direct_pct = 0;
                                $last_team_pct = 0;
                                $level = 1;
                                $max_levels = 5;
                                $has_any_payout = false;
                                $total_payout = 0;
                                
                                while ($level <= $max_levels) {
                                    $sponsor_id = $all_users[$current_user_id]['referred_by'] ?? null;
                                    if (!$sponsor_id) break;
                                    
                                    $sponsor_name = $all_users[$sponsor_id]['name'] ?? 'Unknown';
                                    $sponsor_pkg_name = $user_active_pkg[$sponsor_id]['pkg_name'] ?? 'Free User';
                                    
                                    // Direct Income Check
                                    $direct_pct = getDirectPctInMemory($sponsor_id, $all_users, $user_active_pkg, $free_user_pct);
                                    $direct_amt = 0;
                                    $direct_diff = 0;
                                    $direct_reason = '';
                                    
                                    if ($direct_pct > $last_direct_pct) {
                                        $direct_diff = $direct_pct - $last_direct_pct;
                                        $direct_amt = ($amount * $direct_diff) / 100;
                                        $last_direct_pct = $direct_pct;
                                    } else {
                                        if ($direct_pct == 0) {
                                            if (!isset($user_active_pkg[$sponsor_id])) {
                                                $enabled = $all_users[$sponsor_id]['free_user_income_enabled'] ?? false;
                                                if (!$enabled) $direct_reason = "Free User (Income Disabled)";
                                                else $direct_reason = "Free User (0% Global)";
                                            } else {
                                                $direct_reason = "Package % is 0";
                                            }
                                        }
                                    }
                                    
                                    // Team Turnover Check
                                    $team_pct = getTeamTurnoverPctInMemory($sponsor_id, $team_turnover_cache, $team_slabs, $user_active_pkg);
                                    $team_amt = 0;
                                    $team_diff = 0;
                                    $team_reason = '';
                                    
                                    if ($team_pct > $last_team_pct) {
                                        $team_diff = $team_pct - $last_team_pct;
                                        $team_amt = ($amount * $team_diff) / 100;
                                        $last_team_pct = $team_pct;
                                    } else {
                                        if ($team_pct == 0) {
                                            if (!isset($user_active_pkg[$sponsor_id])) {
                                                $team_reason = "No Active Package";
                                            } elseif (empty($user_active_pkg[$sponsor_id]['is_team_turnover_eligible'])) {
                                                $team_reason = "Package Not Eligible";
                                            } else {
                                                $team_reason = "No Slab Matched";
                                            }
                                        }
                                    }
                                    
                                    $level_total = $direct_amt + $team_amt;
                                    
                                    if ($level_total > 0) {
                                        $has_any_payout = true;
                                        $total_payout += $level_total;
                                        $chain_html .= "<div class='mb-1 border-bottom pb-1'>";
                                        $chain_html .= "<strong>L$level: #$sponsor_id $sponsor_name</strong> <span class='badge bg-info text-dark'>$sponsor_pkg_name</span><br>";
                                        if ($direct_amt > 0) $chain_html .= "<span class='text-success small'>Direct: ₹" . number_format($direct_amt, 2) . " ($direct_diff%)</span> ";
                                        if ($team_amt > 0) $chain_html .= "<span class='text-primary small'>Team: ₹" . number_format($team_amt, 2) . " ($team_diff%)</span>";
                                        $chain_html .= "</div>";
                                    } else {
                                        $reason_text = '';
                                        if (!empty($direct_reason)) $reason_text .= "Direct: $direct_reason. ";
                                        if (!empty($team_reason)) $reason_text .= "Team: $team_reason.";
                                        if (empty($reason_text)) $reason_text = "No gap difference.";
                                        $chain_html .= "<div class='mb-1 text-muted small'>L$level: #$sponsor_id $sponsor_name ($sponsor_pkg_name) - <span class='text-danger'>$reason_text</span></div>";
                                    }
                                    
                                    $current_user_id = $sponsor_id;
                                    $level++;
                                }
                                
                                if (!$has_any_payout) {
                                    $chain_html = "<span class='text-danger'>No payouts calculated for this chain.</span>";
                                }
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if ($has_any_payout): ?>
                                            <input type="checkbox" name="selected_subs[]" value="<?= $sub_id ?>" class="sub-checkbox" style="width:1.2rem; height:1.2rem;">
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-danger" title="Not eligible"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>#<?= $sub_id ?></strong></td>
                                    <td>#<?= $buyer_id ?> <?= htmlspecialchars($buyer_name) ?></td>
                                    <td>₹ <?= number_format($amount, 2) ?></td>
                                    <td><span class="badge bg-<?= $status_color ?>"><?= $sub_status ?></span></td>
                                    <td>
                                        <?= $chain_html ?>
                                        <div class="mt-1 fw-bold text-dark border-top pt-1">Total: ₹ <?= number_format($total_payout, 2) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end py-3">
                <button type="submit" name="generate_selected" class="btn btn-success btn-lg rounded-pill px-5" onclick="return confirm('Generate payouts for selected subscriptions?');">
                    <i class="fas fa-check-circle me-2"></i> Generate Selected Payouts
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    function toggleAll(source) {
        checkboxes = document.getElementsByClassName('sub-checkbox');
        for(var i=0, n=checkboxes.length;i<n;i++) {
            checkboxes[i].checked = source.checked;
        }
    }
</script>

<?php include 'footer.php'; ?>
