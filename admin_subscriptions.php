<?php
require_once 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }

// Activate (Approve)
if(isset($_GET['activate'])) {
    $sub_id = $_GET['activate'];
    $sub = $pdo->prepare("SELECT s.*, p.duration_months FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.id = ?");
    $sub->execute([$sub_id]);
    $data = $sub->fetch();
    if($data) {
        $end = date('Y-m-d', strtotime("+{$data['duration_months']} months"));
        $pdo->prepare("UPDATE subscriptions SET status = 'active', start_date = CURRENT_DATE, end_date = ? WHERE id = ?")->execute([$end, $sub_id]);
    }
    header("Location: admin_subscriptions.php?done=1");
    exit;
}

// Reject
if(isset($_GET['reject'])) {
    $sub_id = $_GET['reject'];
    $pdo->prepare("UPDATE subscriptions SET status = 'rejected' WHERE id = ?")->execute([$sub_id]);
    header("Location: admin_subscriptions.php?done=2");
    exit;
}

include 'header.php'; 
$pendings = $pdo->query("SELECT s.*, u.name as uname, p.title as ptitle, pk.name as pkg_name 
                        FROM subscriptions s 
                        JOIN users u ON s.user_id = u.id 
                        LEFT JOIN properties p ON s.property_id = p.id 
                        JOIN packages pk ON s.package_id = pk.id 
                        WHERE s.status = 'pending' ORDER BY s.id DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-clock me-2"></i>Pending Subscriptions</h4>
    <?php if(isset($_GET['done'])): 
        if($_GET['done'] == 1) echo "<div class='alert alert-success'>✅ Subscription Activated!</div>";
        if($_GET['done'] == 2) echo "<div class='alert alert-warning'>⛔ Subscription Rejected!</div>";
    endif; ?>
    <?php if(count($pendings) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>User</th><th>Package</th><th>Amount</th><th>UTR</th><th>Slip</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach($pendings as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['uname']) ?></td>
                        <td><?= htmlspecialchars($p['pkg_name']) ?></td>
                        <td>₹<?= $p['amount'] ?></td>
                        <td><?= htmlspecialchars($p['utr'] ?? 'N/A') ?></td>
                        <td>
                            <?php if(!empty($p['slip_path']) && file_exists($p['slip_path'])): ?>
                                <a href="<?= $p['slip_path'] ?>" target="_blank" class="btn btn-sm btn-info">📷 View</a>
                            <?php else: ?>
                                <span class="text-muted">No slip</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?activate=<?= $p['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Activate this subscription?')">✅ Approve</a>
                            <a href="?reject=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Reject this subscription?')">❌ Reject</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No pending requests.</p>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
