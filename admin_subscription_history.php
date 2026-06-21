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

$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT s.*, u.name as uname, p.title as ptitle, pk.name as pkg_name 
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        LEFT JOIN properties p ON s.property_id = p.id 
        JOIN packages pk ON s.package_id = pk.id 
        WHERE 1=1";
$params = [];
if(!empty($status_filter)) {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
}
if(!empty($search)) {
    $sql .= " AND (u.name ILIKE ? OR u.email ILIKE ? OR pk.name ILIKE ?)";
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
}
$sql .= " ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subs = $stmt->fetchAll();

include 'header.php'; 
?>
<div class="card-premium">
    <h4><i class="fas fa-history me-2"></i>Subscription History (All)</h4>
    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control" placeholder="🔍 Search user or package..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
            </form>
        </div>
        <div class="col-md-3">
            <form method="GET" class="d-flex gap-2">
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter=='pending'?'selected':'' ?>>Pending</option>
                    <option value="active" <?= $status_filter=='active'?'selected':'' ?>>Active</option>
                    <option value="rejected" <?= $status_filter=='rejected'?'selected':'' ?>>Rejected</option>
                </select>
                <?php if(!empty($search) || !empty($status_filter)): ?>
                    <a href="admin_subscription_history.php" class="btn btn-secondary btn-sm">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead><tr><th>User</th><th>Package</th><th>Amount</th><th>Status</th><th>Payment Method</th><th>UTR</th><th>Request Date</th><th>Activation/Reject Date</th></tr></thead>
            <tbody>
            <?php if(count($subs)>0): ?>
                <?php foreach($subs as $s): 
                    $badge = $s['status']=='active' ? 'success' : ($s['status']=='pending' ? 'warning' : 'danger');
                ?>
                    <tr>
                        <td><?= htmlspecialchars($s['uname']) ?></td>
                        <td><?= htmlspecialchars($s['pkg_name']) ?></td>
                        <td>₹<?= $s['amount'] ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= $s['status'] ?></span></td>
                        <td><?= $s['payment_method'] ?></td>
                        <td><?= htmlspecialchars($s['utr']??'N/A') ?></td>
                        <td><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                        <td><?= $s['start_date'] ? date('d M Y', strtotime($s['start_date'])) : ($s['status']=='rejected' ? 'Rejected' : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="text-center text-muted">No subscriptions found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
