<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }
if(!hasPermission('referrals', $pdo)) { die("Permission denied."); }

// Mark as Paid
if(isset($_GET['pay']) && isset($_GET['id'])) {
    $id = $_GET['pay'];
    $tds_percent = $_POST['tds_percent'] ?? 10;
    $admin_charge_percent = $_POST['admin_charge_percent'] ?? 5;
    $bank_name = $_POST['bank_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
    $ifsc = $_POST['ifsc'] ?? '';
    
    // Get earning amount
    $earn = $pdo->prepare("SELECT amount FROM user_referral_earnings WHERE id = ?");
    $earn->execute([$id]);
    $amount = $earn->fetchColumn();
    if($amount) {
        $calc = calculateReferralNet($amount, $tds_percent, $admin_charge_percent);
        $pdo->prepare("UPDATE user_referral_earnings SET 
                        status = 'paid', 
                        paid_at = CURRENT_TIMESTAMP,
                        tds_deducted = ?, 
                        admin_charge_deducted = ?, 
                        net_amount = ?,
                        bank_name = ?,
                        account_number = ?,
                        ifsc_code = ?
                    WHERE id = ?")
            ->execute([$calc['tds'], $calc['admin_charge'], $calc['net'], $bank_name, $account_number, $ifsc, $id]);
        header("Location: admin_referrals.php?paid=1");
        exit;
    }
}

include 'header.php';

$pending = $pdo->query("SELECT e.*, u.name as referrer_name, r.name as referred_name, p.name as package_name 
                        FROM user_referral_earnings e
                        JOIN users u ON e.user_id = u.id
                        JOIN users r ON e.referred_user_id = r.id
                        JOIN packages p ON e.package_id = p.id
                        WHERE e.status = 'pending'
                        ORDER BY e.created_at DESC")->fetchAll();

$paid = $pdo->query("SELECT e.*, u.name as referrer_name, r.name as referred_name, p.name as package_name 
                     FROM user_referral_earnings e
                     JOIN users u ON e.user_id = u.id
                     JOIN users r ON e.referred_user_id = r.id
                     JOIN packages p ON e.package_id = p.id
                     WHERE e.status = 'paid'
                     ORDER BY e.paid_at DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-hand-holding-usd me-2"></i>Referral Payouts</h4>
    <?php if(isset($_GET['paid'])) echo "<div class='alert alert-success'>✅ Payout Marked as Paid!</div>"; ?>
    
    <h5 class="mt-4">Pending Payouts</h5>
    <?php if(count($pending) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Referrer</th><th>Referred User</th><th>Package</th><th>Amount (₹)</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach($pending as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['referrer_name']) ?></td>
                        <td><?= htmlspecialchars($p['referred_name']) ?></td>
                        <td><?= htmlspecialchars($p['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($p['amount']) ?></td>
                        <td>
                            <form method="POST" action="?pay=1&id=<?= $p['id'] ?>" class="row g-2">
                                <div class="col-md-3"><input type="number" step="0.01" name="tds_percent" class="form-control form-control-sm" value="10" placeholder="TDS %"></div>
                                <div class="col-md-3"><input type="number" step="0.01" name="admin_charge_percent" class="form-control form-control-sm" value="5" placeholder="Admin %"></div>
                                <div class="col-md-2"><input type="text" name="bank_name" class="form-control form-control-sm" placeholder="Bank"></div>
                                <div class="col-md-2"><input type="text" name="account_number" class="form-control form-control-sm" placeholder="A/c No."></div>
                                <div class="col-md-2"><input type="text" name="ifsc" class="form-control form-control-sm" placeholder="IFSC"></div>
                                <div class="col-md-12"><button type="submit" class="btn btn-sm btn-success w-100">Mark Paid</button></div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: echo "<p class='text-muted'>No pending payouts.</p>"; endif; ?>

    <h5 class="mt-4">Paid Payouts</h5>
    <?php if(count($paid) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Referrer</th><th>Referred</th><th>Package</th><th>Gross</th><th>TDS</th><th>Admin Charge</th><th>Net Paid</th><th>Bank</th><th>A/c No.</th><th>IFSC</th><th>Paid On</th></tr></thead>
                <tbody>
                <?php foreach($paid as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['referrer_name']) ?></td>
                        <td><?= htmlspecialchars($p['referred_name']) ?></td>
                        <td><?= htmlspecialchars($p['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($p['amount']) ?></td>
                        <td>₹<?= indianCurrencyFormat($p['tds_deducted']) ?></td>
                        <td>₹<?= indianCurrencyFormat($p['admin_charge_deducted']) ?></td>
                        <td><strong>₹<?= indianCurrencyFormat($p['net_amount']) ?></strong></td>
                        <td><?= htmlspecialchars($p['bank_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['account_number'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p['ifsc_code'] ?? '') ?></td>
                        <td><?= date('d M Y', strtotime($p['paid_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Download Excel Button -->
        <a href="download_referral_excel.php" class="btn btn-success mt-3"><i class="fas fa-file-excel"></i> Download Excel</a>
    <?php else: echo "<p class='text-muted'>No paid payouts yet.</p>"; endif; ?>
</div>
<?php include 'footer.php'; ?>
