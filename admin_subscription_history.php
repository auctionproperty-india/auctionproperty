<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// ---- Search filter ----
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT s.*, u.name as user_name 
        FROM subscriptions s 
        JOIN users u ON s.user_id = u.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.name ILIKE ? OR u.email ILIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if (!empty($status_filter)) {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();
?>
<div class="container-fluid mt-4">
    <h1>📋 Subscription History (All)</h1>

    <!-- Search & Filter -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search user or email" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-control">
                <option value="">All Status</option>
                <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Search</button>
        </div>
        <div class="col-md-2">
            <a href="admin_subscription_history.php" class="btn btn-secondary w-100">Reset</a>
        </div>
    </form>

    <?php if (count($subscriptions) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>User</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment Method</th>
                        <th>UTR</th>
                        <th>Activation Date</th>
                        <th>Expiry Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['user_name']) ?></td>
                            <td><?= htmlspecialchars($row['package_name'] ?? 'N/A') ?></td>
                            <td>₹ <?= number_format($row['amount'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= $row['status'] == 'active' ? 'success' : ($row['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['payment_method'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['utr'] ?? 'N/A') ?></td>
                            <td><?= !empty($row['start_date']) ? date('d M Y', strtotime($row['start_date'])) : 'N/A' ?></td>
                            <td><?= !empty($row['end_date']) ? date('d M Y', strtotime($row['end_date'])) : 'N/A' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No subscriptions found.</div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
