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

<div class="card-premium mt-4">
    <h4><i class="fas fa-users me-2"></i>My Team (Referred Users)</h4>
    <?php if(count($team_members) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Registered On</th><th>Activation Date</th></tr></thead>
                <tbody>
                <?php foreach($team_members as $tm): ?>
                    <tr>
                        <td><?= htmlspecialchars($tm['name']) ?></td>
                        <td><?= htmlspecialchars($tm['email']) ?></td>
                        <td><?= htmlspecialchars($tm['phone'] ?? 'N/A') ?></td>
                        <td><?= date('d M Y', strtotime($tm['reg_date'])) ?></td>
                        <td><?= $tm['activation_date'] ? date('d M Y', strtotime($tm['activation_date'])) : '<span class="text-muted">Not Activated</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">You haven't referred anyone yet. Share your referral link!</p>
    <?php endif; ?>
</div>

<div class="card-premium mt-4">
    <h4><i class="fas fa-history me-2"></i>Your Subscription Requests</h4>
    <?php if(count($user_subs) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr><th>Package</th><th>Amount</th><th>Status</th><th>Payment Method</th><th>UTR</th><th>Request Date</th><th>Activation/Reject Date</th></tr></thead>
                <tbody>
                <?php foreach($user_subs as $us): ?>
                    <tr>
                        <td><?= htmlspecialchars($us['pkg_name']) ?></td>
                        <td>₹<?= $us['amount'] ?></td>
                        <td><span class="badge bg-<?= $us['status']=='active'?'success':($us['status']=='pending'?'warning':'danger') ?>"><?= $us['status'] ?></span></td>
                        <td><?= $us['payment_method'] ?></td>
                        <td><?= htmlspecialchars($us['utr'] ?? 'N/A') ?></td>
                        <td><?= date('d M Y', strtotime($us['created_at'])) ?></td>
                        <td><?= $us['start_date'] ? date('d M Y', strtotime($us['start_date'])) : ($us['status']=='rejected' ? 'Rejected' : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No subscription requests yet.</p>
    <?php endif; ?>
</div>

<script>
    function copyRef() { 
        let inp = document.getElementById('refLink'); 
        inp.select(); 
        navigator.clipboard.writeText(inp.value).then(() => alert('Referral Link Copied!')).catch(() => document.execCommand('copy')); 
    }
</script>

<?php include 'footer.php'; ?>
