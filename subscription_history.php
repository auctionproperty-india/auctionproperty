<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

if(!hasViewPermission('subscriptions', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page.</div>");
}

include 'header.php';
$subs = $pdo->query("SELECT s.*, u.name as uname, p.name as pkg_name FROM subscriptions s JOIN users u ON s.user_id = u.id JOIN packages p ON s.package_id = p.id ORDER BY s.created_at DESC LIMIT 100")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-list-alt me-2"></i>Subscription History</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead><tr><th>User</th><th>Package</th><th>Amount</th><th>Status</th><th>Payment Method</th><th>UTR</th><th>Request Date</th><th>Activation/Reject Date</th></tr></thead>
            <tbody>
            <?php if(count($subs)>0) {
                foreach($subs as $s) {
                    $status_badge = $s['status']=='active' ? 'success' : ($s['status']=='pending' ? 'warning' : 'danger');
                    echo "<tr><td>".htmlspecialchars($s['uname'])."</td><td>".htmlspecialchars($s['pkg_name'])."</td><td>₹".$s['amount']."</td>
                          <td><span class='badge bg-$status_badge'>".$s['status']."</span></td>
                          <td>".$s['payment_method']."</td><td>".htmlspecialchars($s['utr']??'N/A')."</td>
                          <td>".date('d M Y', strtotime($s['created_at']))."</td>
                          <td>".($s['start_date'] ? date('d M Y', strtotime($s['start_date'])) : '—')."</td></tr>";
                }
            } else echo "<tr><td colspan='8' class='text-center'>No subscriptions found.</td></tr>";
            ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
