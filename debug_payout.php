<?php
// ============================================================
// 🐞 DEBUG & SELECTIVE PAYOUT TOOL
// Shows Active + Expired Subscriptions, Calculates Differential Direct & Team Turnover
// Allows Admin to selectively generate payouts via Checkboxes
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
        <h3 class="fw-bold text-danger"><i class="fas fa-bug me-2"></i> Payout Debugger & Selective Generator</h3>
        <a href="admin_payout_backfill.php" class="btn btn-outline-primary rounded-pill px-4">Go to Backfill Tool</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info py-2 small">
        <i class="fas fa-info-circle me-1"></i> This tool shows <b>Active</b> and <b>Expired</b> subscriptions. Use the checkboxes to selectively generate payouts. The Team Turnover is calculated using <b>Differential Logic</b> (bottom-up).
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
                                <th>Sponsor Package</th>
                                <th>Direct (Level Diff)</th>
                                <th>Team Turnover</th>
                                <th>Total Expected</th>
                                <th>Reason (if 0)</th>
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
                                echo "<tr><td colspan='11' class='text-center text-danger py-4'>No active or expired subscriptions found.</td></tr>";
                            }

                            foreach ($subs as $sub):
                                $sub_id = $sub['sub_id'];
                                $buyer_id = $sub['buyer_id'];
                                $amount = $sub['amount'];
                                $buyer_name = $sub['buyer_name'];
                                $sponsor_id = $sub['referred_by'];
                                $sub_status = ($sub['status'] == 'active' && $sub['end_date'] >= date('Y-m-d')) ? 'Active' : 'Expired';
                                $status_color = ($sub_status == 'Active') ? 'success' : 'secondary';
                                
                                $sponsor_name = 'N/A';
                                $sponsor_pkg = 'N/A';
                                $direct_amt = 0;
                                $team_amt = 0;
                                $total_amt = 0;
                                $reason = '';
                                $can_pay = false;
                                
                                if (empty($sponsor_id)) {
                                    $reason = "Buyer has no sponsor (referred_by is NULL)";
                                } else {
                                    // Get Sponsor Info
                                    $sp_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                                    $sp_stmt->execute([$sponsor_id]);
                                    $sponsor_name = $sp_stmt->fetchColumn() ?: 'Unknown';
                                    
                                    // Check if Sponsor is Free
                                    $is_free = !userHasActiveSubscription($pdo, $sponsor_id);
                                    
                                    if ($is_free) {
                                        $sponsor_pkg = 'Free User';
                                        $user_check = $pdo->prepare("SELECT free_user_income_enabled FROM users WHERE id = ?");
                                        $user_check->execute([$sponsor_id]);
                                        $is_enabled = $user_check->fetchColumn();
                                        
                                        if ($is_enabled) {
                                            $free_setting = $pdo->query("SELECT percentage FROM income_settings WHERE income_type = 'free_user_direct' AND status = 1 LIMIT 1")->fetch();
                                            $direct_pct = $free_setting ? (float)$free_setting['percentage'] : 0;
                                            if ($direct_pct > 0) {
                                                $direct_amt = ($amount * $direct_pct) / 100;
                                            } else {
                                                $reason = "Free user income enabled but global % is 0%";
                                            }
                                        } else {
                                            $reason = "Free User, Admin has Disabled income for them.";
                                        }
                                    } else {
                                        // Paid User - Get Package
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
                                            $sponsor_pkg = $pkg['name'];
                                            $direct_pct = (float)$pkg['direct_income_percent'];
                                            if ($direct_pct > 0) {
                                                $direct_amt = ($amount * $direct_pct) / 100;
                                            } else {
                                                $reason = "Sponsor's package ({$sponsor_pkg}) has Direct Income set to 0% in Admin.";
                                            }
                                        } else {
                                            $reason = "Sponsor has no active package but is not marked as free?";
                                        }
                                    }
                                    
                                    // ==========================================
                                    // 🔥 Calculate Team Turnover Income (Differential)
                                    // ==========================================
                                    // Check if Sponsor's package is eligible for Team Turnover
                                    $sponsor_pkg_stmt = $pdo->prepare("
                                        SELECT p.is_team_turnover_eligible
                                        FROM subscriptions s
                                        JOIN packages p ON s.package_id = p.id
                                        WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE
                                        ORDER BY s.id DESC LIMIT 1
                                    ");
                                    $sponsor_pkg_stmt->execute([$sponsor_id]);
                                    $is_team_eligible = $sponsor_pkg_stmt->fetchColumn() ?? false;
                                    
                                    if ($is_team_eligible) {
                                        $total_turnover = getTeamTurnover($pdo, $sponsor_id);
                                        $slabStmt = $pdo->prepare("SELECT * FROM income_settings WHERE income_type = 'team_turnover' AND status = 1 AND min_turnover <= ? AND (max_turnover IS NULL OR max_turnover >= ?) ORDER BY min_turnover DESC LIMIT 1");
                                        $slabStmt->execute([$total_turnover, $total_turnover]);
                                        $slab = $slabStmt->fetch();
                                        if ($slab) {
                                            $team_pct = (float)$slab['percentage'];
                                            $team_amt = ($amount * $team_pct) / 100;
                                        } else {
                                            if (empty($reason)) $reason = "No matching Team Turnover slab found.";
                                        }
                                    } else {
                                        if (empty($reason)) $reason = "Sponsor's package is not eligible for Team Turnover.";
                                    }
                                }
                                
                                $total_amt = $direct_amt + $team_amt;
                                if ($total_amt > 0) {
                                    $can_pay = true;
                                    $reason = "✅ Should be working!";
                                }
                                if (empty($reason)) {
                                    $reason = "No income calculated.";
                                }
                            ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if ($can_pay): ?>
                                            <input type="checkbox" name="selected_subs[]" value="<?= $sub_id ?>" class="sub-checkbox" style="width:1.2rem; height:1.2rem;">
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-danger" title="Not eligible for payout"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>#<?= $sub_id ?></strong></td>
                                    <td>#<?= $buyer_id ?> <?= htmlspecialchars($buyer_name) ?></td>
                                    <td>₹ <?= number_format($amount, 2) ?></td>
                                    <td><span class="badge bg-<?= $status_color ?>"><?= $sub_status ?></span></td>
                                    <td>#<?= $sponsor_id ?> <?= htmlspecialchars($sponsor_name) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($sponsor_pkg) ?></span></td>
                                    <td class="text-success fw-bold">
                                        <?= $direct_amt > 0 ? '₹ ' . number_format($direct_amt, 2) : '₹ 0.00' ?>
                                    </td>
                                    <td class="text-primary fw-bold">
                                        <?= $team_amt > 0 ? '₹ ' . number_format($team_amt, 2) : '₹ 0.00' ?>
                                    </td>
                                    <td class="text-dark fw-bold fs-6">
                                        ₹ <?= number_format($total_amt, 2) ?>
                                    </td>
                                    <td class="text-danger small" style="max-width: 200px;"><?= $reason ?></td>
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
