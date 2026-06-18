<?php
require_once 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }
if(isset($_GET['activate'])) {
    $sub_id = $_GET['activate'];
    $sub = $pdo->prepare("SELECT s.*, p.duration_months FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.id = ?");
    $sub->execute([$sub_id]);
    $data = $sub->fetch();
    if($data) {
        $end = date('Y-m-d', strtotime("+{$data['duration_months']} months"));
        $pdo->prepare("UPDATE subscriptions SET status = 'active', start_date = CURDATE(), end_date = ? WHERE id = ?")->execute([$end, $sub_id]);
    }
    header("Location: admin_subscriptions.php?done=1");
    exit;
}
include 'header.php'; 
$pendings = $pdo->query("SELECT s.*, u.name as uname, p.title as ptitle FROM subscriptions s 
                        JOIN users u ON s.user_id = u.id 
                        LEFT JOIN properties p ON s.property_id = p.id 
                        WHERE s.status = 'pending' ORDER BY s.id DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-clock me-2"></i>Pending Approvals</h4>
    <?php if(isset($_GET['done'])) echo "<div class='alert alert-success'>✅ Activated!</div>"; ?>
    <table class="table table-bordered">
        <thead><tr><th>User</th><th>Package</th><th>Property (if any)</th><th>Amount</th><th>Method</th><th>Action</th></tr></thead>
        <tbody>
        <?php if(count($pendings)>0) { foreach($pendings as $p) { ?>
            <tr><td><?= htmlspecialchars($p['uname']) ?></td>
            <td><?= $p['package_id'] ?></td>
            <td><?= $p['ptitle'] ? htmlspecialchars($p['ptitle']) : 'All Properties' ?></td>
            <td>₹<?= $p['amount'] ?></td>
            <td><?= $p['payment_method'] ?></td>
            <td><a href="?activate=<?= $p['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Activate?')">✅ Activate</a>
            <?php if($p['screenshot_path']) echo "<a href='".$p['screenshot_path']."' target='_blank' class='btn btn-sm btn-info'>📷</a>"; ?></td></tr>
        <?php } } else { echo "<tr><td colspan='6'>No pending.</td></tr>"; } ?>
        </tbody>
    </table>
</div>
<?php include 'footer.php'; ?>
