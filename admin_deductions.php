<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
if(!hasViewPermission('referrals', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page.</div>");
}

include 'header.php';

// Get overall totals
$totals = $pdo->query("SELECT 
    COALESCE(SUM(tds_deducted), 0) as total_tds,
    COALESCE(SUM(admin_charge_deducted), 0) as total_admin,
    COALESCE(SUM(amount), 0) as total_gross,
    COALESCE(SUM(net_amount), 0) as total_net,
    COUNT(*) as total_payouts
    FROM user_referral_earnings WHERE status = 'paid'")->fetch();

// Group by user
$user_totals = $pdo->query("SELECT 
    u.id as user_id,
    u.name as user_name,
    u.email as user_email,
    COUNT(e.id) as payout_count,
    SUM(e.amount) as total_gross,
    SUM(e.tds_deducted) as total_tds,
    SUM(e.admin_charge_deducted) as total_admin,
    SUM(e.net_amount) as total_net
    FROM user_referral_earnings e
    JOIN users u ON e.user_id = u.id
    WHERE e.status = 'paid'
    GROUP BY u.id, u.name, u.email
    ORDER BY u.name")->fetchAll();

// Detailed list
$details = $pdo->query("SELECT e.*, u.name as referrer_name, r.name as referred_name, p.name as package_name 
    FROM user_referral_earnings e
    JOIN users u ON e.user_id = u.id
    JOIN users r ON e.referred_user_id = r.id
    JOIN packages p ON e.package_id = p.id
    WHERE e.status = 'paid'
    ORDER BY e.paid_at DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-calculator me-2"></i>Deduction Summary</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-dark p-3 rounded-4">
                <h6>Total TDS Deducted</h6>
                <h3>₹ <?= indianCurrencyFormat($totals['total_tds']) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-dark p-3 rounded-4">
                <h6>Total Admin Charge</h6>
                <h3>₹ <?= indianCurrencyFormat($totals['total_admin']) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white p-3 rounded-4">
                <h6>Total Payouts</h6>
                <h3><?= $totals['total_payouts'] ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white p-3 rounded-4">
                <h6>Total Net Paid</h6>
                <h3>₹ <?= indianCurrencyFormat($totals['total_net']) ?></h3>
            </div>
        </div>
    </div>

    <h5 class="mt-4">Per User Deduction Summary</h5>
    <?php if(count($user_totals) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr>
                    <th>User</th>
                    <th>Payouts</th>
                    <th>Gross</th>
                    <th>TDS</th>
                    <th>Admin Charge</th>
                    <th>Net</th>
                </tr></thead>
                <tbody>
                <?php foreach($user_totals as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['user_name']) ?><br><small><?= htmlspecialchars($u['user_email']) ?></small></td>
                        <td><?= $u['payout_count'] ?></td>
                        <td>₹<?= indianCurrencyFormat($u['total_gross']) ?></td>
                        <td>₹<?= indianCurrencyFormat($u['total_tds']) ?></td>
                        <td>₹<?= indianCurrencyFormat($u['total_admin']) ?></td>
                        <td><strong>₹<?= indianCurrencyFormat($u['total_net']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No paid payouts yet.</p>
    <?php endif; ?>

    <h5 class="mt-4">Detailed Deduction History</h5>
    <?php if(count($details) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr>
                    <th>Date</th>
                    <th>Referrer</th>
                    <th>Referred</th>
                    <th>Package</th>
                    <th>Gross</th>
                    <th>TDS</th>
                    <th>Admin Charge</th>
                    <th>Net</th>
                    <th>UTR</th>
                </tr></thead>
                <tbody>
                <?php foreach($details as $d): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($d['paid_at'])) ?></td>
                        <td><?= htmlspecialchars($d['referrer_name']) ?></td>
                        <td><?= htmlspecialchars($d['referred_name']) ?></td>
                        <td><?= htmlspecialchars($d['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($d['amount']) ?></td>
                        <td>₹<?= indianCurrencyFormat($d['tds_deducted']) ?></td>
                        <td>₹<?= indianCurrencyFormat($d['admin_charge_deducted']) ?></td>
                        <td><strong>₹<?= indianCurrencyFormat($d['net_amount']) ?></strong></td>
                        <td><?= htmlspecialchars($d['utr_no'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No details yet.</p>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
