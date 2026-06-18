<?php
require_once 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }

// Activate Subscription
if(isset($_GET['activate'])) {
    $sub_id = $_GET['activate'];
    $sub = $pdo->prepare("SELECT s.*, p.duration_months FROM subscriptions s JOIN packages p ON s.package_id = p.id WHERE s.id = ?");
    $sub->execute([$sub_id]);
    $data = $sub->fetch();
    if($data) {
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime("+{$data['duration_months']} months"));
        $pdo->prepare("UPDATE subscriptions SET status = 'active', start_date = ?, end_date = ? WHERE id = ?")->execute([$start, $end, $sub_id]);
    }
    header("Location: admin_subscriptions.php?done=1");
    exit;
}

include 'header.php'; 
$pending = $pdo->query("SELECT s.*, u.name as uname, p.title as ptitle FROM subscriptions s 
                        JOIN users u ON s.user_id = u.id 
                        JOIN properties p ON s.property_id = p.id 
                        WHERE s.status = 'pending' ORDER BY s.id DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-clock me-2"></i>Pending Subscriptions (Activate)</h4>
    <?php if(isset($_GET['done'])) echo "<div class='alert alert-success'>✅ Subscription Activated!</div>"; ?>
    <table class="table table-bordered">
        <thead><tr><th>User</th><th>Property</th><th>Amount</th><th>Method</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(count($pending) > 0) {
            foreach($pending as $p) { ?>
                <tr>
                    <td><?= htmlspecialchars($p['uname']) ?></td>
                    <td><?= htmlspecialchars($p['ptitle']) ?></td>
                    <td>₹<?= $p['amount'] ?></td>
                    <td><?= $p['payment_method'] ?></td>
                    <td>
                        <a href="?activate=<?= $p['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Activate this user?')">✅ Activate</a>
                        <?php if($p['screenshot_path']): ?>
                            <a href="<?= $p['screenshot_path'] ?>" target="_blank" class="btn btn-sm btn-info">View Screenshot</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php }
        } else { echo "<tr><td colspan='5'>No pending requests.</td></tr>"; } ?>
        </tbody>
    </table>
</div>
<?php include 'footer.php'; ?>
