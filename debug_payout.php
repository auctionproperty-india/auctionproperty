<?php
// ============================================================
// 🐞 DEBUG: Why is Payout Not Generating?
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Admin access required.");
}

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <h3 class="fw-bold text-danger"><i class="fas fa-bug me-2"></i> Payout Debugger</h3>
    <p class="text-muted">This tool shows exactly why payouts are not being generated for active subscriptions.</p>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Active Subscriptions Analysis</h5>
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Buyer</th>
                        <th>Amount</th>
                        <th>Sponsor (Upline)</th>
                        <th>Sponsor Package</th>
                        <th>Sponsor Income %</th>
                        <th>Expected Payout</th>
                        <th>Reason (if 0)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("
                        SELECT s.user_id, s.amount, u.name as buyer_name, u.referred_by, u.id as buyer_id
                        FROM subscriptions s 
                        JOIN users u ON s.user_id = u.id 
                        WHERE s.status = 'active' AND s.end_date >= CURRENT_DATE 
                        ORDER BY s.id DESC
                    ");
                    $subs = $stmt->fetchAll();
                    
                    if (count($subs) == 0) {
                        echo "<tr><td colspan='7' class='text-center text-danger'>No active subscriptions found in database!</td></tr>";
                    }

                    foreach ($subs as $sub):
                        $buyer_id = $sub['buyer_id'];
                        $amount = $sub['amount'];
                        $buyer_name = $sub['buyer_name'];
                        $sponsor_id = $sub['referred_by'];
                        
                        $sponsor_name = 'N/A';
                        $sponsor_pkg = 'N/A';
                        $sponsor_pct = 0;
                        $reason = '';
                        
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
                                    $sponsor_pct = $free_setting ? (float)$free_setting['percentage'] : 0;
                                    if ($sponsor_pct == 0) $reason = "Free user income is enabled but global percentage is 0%";
                                } else {
                                    $reason = "Sponsor is a Free User and Admin has Disabled income for them.";
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
                                    $sponsor_pct = (float)$pkg['direct_income_percent'];
                                    if ($sponsor_pct == 0) $reason = "Sponsor's package ({$sponsor_pkg}) has Direct Income set to 0% in Admin settings.";
                                } else {
                                    $reason = "Sponsor has no active package but is not marked as free?";
                                }
                            }
                        }
                        
                        $expected = ($amount * $sponsor_pct) / 100;
                    ?>
                        <tr>
                            <td>#<?= $buyer_id ?> <?= htmlspecialchars($buyer_name) ?></td>
                            <td>₹ <?= number_format($amount, 2) ?></td>
                            <td>#<?= $sponsor_id ?> <?= htmlspecialchars($sponsor_name) ?></td>
                            <td><?= htmlspecialchars($sponsor_pkg) ?></td>
                            <td><span class="badge bg-info"><?= $sponsor_pct ?>%</span></td>
                            <td class="fw-bold <?= $expected > 0 ? 'text-success' : 'text-danger' ?>">
                                ₹ <?= number_format($expected, 2) ?>
                            </td>
                            <td class="text-danger small"><?= $reason ?: '✅ Should be working!' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
