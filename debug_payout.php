<?php
// ============================================================
// 🐞 DEBUG & SELECTIVE PAYOUT TOOL (Full Chain View)
// Shows Active + Expired Subscriptions, Calculates Differential Direct & Team Turnover
// Displays the ENTIRE upline chain so you can see exactly who gets paid what.
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Admin access required.");
}

$message = '';
$message_type = '';

// ============================================================
// 🔥 HANDLE SELECTIVE PAYOUT GENERATION
// ============================================================
if (isset($_POST['generate_selected']) && !empty($_POST['selected_subs'])) {
    $selected_subs = $_POST['selected_subs'];
    $batch_id = 'MANUAL_' . date('Ymd_His');
    $count = 0;
    
    try {
        $pdo->beginTransaction();
        foreach ($selected_subs as $sub_id) {
            $sub_id = (int)$sub_id;
            // Fetch subscription details
            $stmt = $pdo->prepare("SELECT user_id, amount, package_id FROM subscriptions WHERE id = ?");
            $stmt->execute([$sub_id]);
            $sub = $stmt->fetch();
            
            if ($sub && $sub['amount'] > 0) {
                // Call the distribution function
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
        $message = "❌ Error generating payouts: " . $e->getMessage();
        $message_type = "danger";
    }
}

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold text-danger"><i class="fas fa-bug me-2"></i> Payout Debugger (Full Chain View)</h3>
        <a href="admin_payout_backfill.php" class="btn btn-outline-primary rounded-pill px-4">Go to Backfill Tool</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info py-2 small">
        <i class="fas fa-info-circle me-1"></i> This tool now shows the <b>ENTIRE UPLINE CHAIN</b> for each subscription. Use the checkboxes to selectively generate payouts.
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
                                <th>Sponsor (Upline)</th>
                                <th>Payout Chain Breakdown (Direct + Team Turnover)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch ALL subscriptions (Active + Expired)
                            $stmt = $pdo->query("
                                SELECT s.id as sub_id, s.user_id, s.amount, s.package_id, s.status, s.end_date, 
                                       u.name as buyer_name, u.referred_by, u.id as buyer_id
                                FROM subscriptions s 
                                JOIN users u ON s.user_id = u.id 
                                WHERE s.status IN ('active', 'expired')
                                ORDER BY s.id DESC
                            ");
                            $subs = $stmt->fetchAll();
                            
                            if (count($subs) == 0) {
                                echo "<tr><td colspan='7' class='text-center text-danger py-4'>No active or expired subscriptions found.</td></tr>";
                            }

                            foreach ($subs as $sub):
                                $sub_id = $sub['sub_id'];
                                $buyer_id = $sub['buyer_id'];
                                $amount = $sub['amount'];
                                $buyer_name = $sub['buyer_name'];
                                $sub_status = ($sub['status'] == 'active' && $sub['end_date'] >= date('Y-m-d')) ? 'Active' : 'Expired';
                                $status_color = ($sub_status == 'Active') ? 'success' : 'secondary';
                                
                                // Build the chain dynamically
                                $chain_html = '';
                                $current_user_id = $buyer_id;
                                $last_direct_pct = 0;
                                $last_team_pct = 0;
                                $level = 1;
                                $max_levels = 5; // Limit to 5 levels for display
                                $has_any_payout = false;
                                $total_payout = 0;
                                
                                while ($level <= $max_levels) {
                                    $stmt_chain = $pdo->prepare("SELECT referred_by FROM users WHERE id = ?");
                                    $stmt_chain->execute([$current_user_id]);
                                    $sponsor_id = $stmt_chain->fetchColumn();
                                    if (!$sponsor_id) break;
                                    
                                    // Get Sponsor Name & Package
                                    $sp_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                                    $sp_stmt->execute([$sponsor_id]);
                                    $sponsor_name = $sp_stmt->fetchColumn() ?: 'Unknown';
                                    
                                    // Check if Sponsor is Free
                                    $is_free = !userHasActiveSubscription($pdo, $sponsor_id);
                                    $sponsor_pkg_name = '';
                                    
                                    if ($is_free) {
                                        $sponsor_pkg_name = 'Free User';
                                    } else {
                                        $pkg_stmt = $pdo->prepare("
                                            SELECT p.name, p.direct_income_percent 
                                            FROM subscriptions s
                                            JOIN packages p ON s.package_id = p.id
                                            WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE
                                            ORDER BY s.id DESC LIMIT 1
                                        ");
                                        $pkg_stmt->execute([$sponsor_id]);
                                        $pkg = $pkg_stmt->fetch();
                                        if ($pkg) {
                                            $sponsor_pkg_name = $pkg['name'];
                                        } else {
                                            $sponsor_pkg_name = 'N/A';
                                        }
                                    }
                                    
                                    // Calculate Direct Income Diff
                                    $direct_pct = getUserIncomePercentage($pdo, $sponsor_id);
                                    $direct_amt = 0;
                                    $direct_diff = 0;
                                    if ($direct_pct > $last_direct_pct) {
                                        $direct_diff = $direct_pct - $last_direct_pct;
                                        $direct_amt = ($amount * $direct_diff) / 100;
                                        $last_direct_pct = $direct_pct;
                                    }
                                    
                                    // Calculate Team Turnover Diff
                                    $team_pct = getTeamTurnoverPercentage($pdo, $sponsor_id);
                                    $team_amt = 0;
                                    $team_diff = 0;
                                    if ($team_pct > $last_team_pct) {
                                        $team_diff = $team_pct - $last_team_pct;
                                        $team_amt = ($amount * $team_diff) / 100;
                                        $last_team_pct = $team_pct;
                                    }
                                    
                                    $level_total = $direct_amt + $team_amt;
                                    if ($level_total > 0) {
                                        $has_any_payout = true;
                                        $total_payout += $level_total;
                                        
                                        $chain_html .= "<div class='mb-1 border-bottom pb-1'>";
                                        $chain_html .= "<strong>Level $level: #$sponsor_id $sponsor_name</strong> ($sponsor_pkg_name)<br>";
                                        if ($direct_amt > 0) $chain_html .= "<span class='text-success'>Direct: ₹" . number_format($direct_amt, 2) . " ($direct_diff%)</span><br>";
                                        if ($team_amt > 0) $chain_html .= "<span class='text-primary'>Team: ₹" . number_format($team_amt, 2) . " ($team_diff%)</span><br>";
                                        $chain_html .= "<strong>Total: ₹" . number_format($level_total, 2) . "</strong>";
                                        $chain_html .= "</div>";
                                    } else {
                                        $chain_html .= "<div class='mb-1 text-muted small'>Level $level: #$sponsor_id $sponsor_name ($sponsor_pkg_name) - No Payout</div>";
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
                                            <i class="fas fa-times-circle text-danger" title="Not eligible for payout"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>#<?= $sub_id ?></strong></td>
                                    <td>#<?= $buyer_id ?> <?= htmlspecialchars($buyer_name) ?></td>
                                    <td>₹ <?= number_format($amount, 2) ?></td>
                                    <td><span class="badge bg-<?= $status_color ?>"><?= $sub_status ?></span></td>
                                    <td>#<?= $sub['referred_by'] ?> <?= htmlspecialchars($sub['referred_by'] ? 'Sponsor' : 'N/A') ?></td>
                                    <td style="min-width: 300px;">
                                        <?= $chain_html ?>
                                        <div class="mt-1 fw-bold text-dark">Total Chain Payout: ₹ <?= number_format($total_payout, 2) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-end py-3">
                <button type="submit" name="generate_selected" class="btn btn-success btn-lg rounded-pill px-5" onclick="return confirm('Generate payouts for selected subscriptions? This will credit wallets.');">
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
