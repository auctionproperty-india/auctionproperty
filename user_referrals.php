<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

// ---- Referral Data ----
$referral_link = getReferralLink($user_id);
$earnings = getReferralEarnings($pdo, $user_id, 'pending');
$paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
$total_pending = array_sum(array_column($earnings, 'amount'));
$total_paid = array_sum(array_column($paid_earnings, 'net_amount'));
$team_members = getReferredUsers($pdo, $user_id);

$user_subs = $pdo->prepare("SELECT s.*, p.name as pkg_name FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
$user_subs->execute([$user_id]);
$user_subs = $user_subs->fetchAll();
?>
<div class="card-premium" style="border:1px solid #10b981; background:#f0fdf4;">
    <h5><i class="fas fa-link me-2" style="color:#10b981;"></i>Your Referral Link</h5>
    <div class="input-group">
        <input type="text" class="form-control border-success" id="refLink" value="<?= $referral_link ?>" readonly>
        <button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
    </div>
    <div class="mt-2">
        <span class="badge bg-warning text-dark">⏳ Pending: ₹ <?= indianCurrencyFormat($total_pending) ?></span>
        <span class="badge bg-success ms-2">✅ Paid: ₹ <?= indianCurrencyFormat($total_paid) ?></span>
    </div>
</div>

<!-- ===== Pending Earnings ===== -->
<div class="card-premium mt-4">
    <h5><i class="fas fa-clock me-2"></i>Pending Earnings</h5>
    <?php if(count($earnings) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr><th>Referred User</th><th>Package</th><th>Gross Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach($earnings as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['referred_name']) ?></td>
                        <td><?= htmlspecialchars($e['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($e['amount']) ?></td>
                        <td><span class="badge bg-warning">Pending</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No pending earnings.</p>
    <?php endif; ?>
</div>

<!-- ===== Paid Earnings (with Breakdown) ===== -->
<div class="card-premium mt-4">
    <h5><i class="fas fa-history me-2"></i>Paid Earnings History</h5>
    <?php if(count($paid_earnings) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr>
                    <th>Referred User</th>
                    <th>Package</th>
                    <th>Gross</th>
                    <th>TDS Deducted</th>
                    <th>Admin Charge</th>
                    <th>Net Paid</th>
                    <th>Paid On</th>
                </tr></thead>
                <tbody>
                <?php foreach($paid_earnings as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['referred_name']) ?></td>
                        <td><?= htmlspecialchars($e['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($e['amount']) ?></td>
                        <td>₹<?= indianCurrencyFormat($e['tds_deducted']) ?></td>
                        <td>₹<?= indianCurrencyFormat($e['admin_charge_deducted']) ?></td>
                        <td><strong class="text-success">₹<?= indianCurrencyFormat($e['net_amount']) ?></strong></td>
                        <td><?= date('d M Y', strtotime($e['paid_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No paid earnings yet.</p>
    <?php endif; ?>
</div>

<!-- ===== Subscription History (unchanged) ===== -->
<div class="card-premium mt-4">
    <h4><i class="fas fa-history me-2"></i>Your Subscription Requests</h4>
    <!-- ... keep your existing code ... -->
</div>

<script>
    function copyRef() { 
        let inp = document.getElementById('refLink'); 
        inp.select(); 
        navigator.clipboard.writeText(inp.value).then(() => alert('Referral Link Copied!')).catch(() => document.execCommand('copy')); 
    }
</script>

<?php include 'footer.php'; ?>
