<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

$user_subs = $pdo->prepare("SELECT s.*, p.name as pkg_name FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.user_id = ? ORDER BY s.created_at DESC");
$user_subs->execute([$user_id]);
$user_subs = $user_subs->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-history me-2"></i>Your Subscription Requests</h4>
    <p class="text-muted">All your subscription requests and their status.</p>
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
<?php include 'footer.php'; ?>
