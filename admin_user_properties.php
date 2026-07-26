<?php
// ============================================================
// 🏠 Admin – User Properties (All Statuses)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// Fetch all user properties with user info
$stmt = $pdo->query("
    SELECT up.*, u.name as user_name, u.email as user_email 
    FROM user_properties up
    JOIN users u ON up.user_id = u.id
    ORDER BY up.created_at DESC
");
$properties = $stmt->fetchAll();
?>

<div class="container-fluid">
    <h4><i class="fas fa-home me-2"></i>User Properties (All)</h4>
    <p class="text-muted">Properties submitted by users. Approved ones appear on the customer properties section.</p>

    <div class="card-premium">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Title</th>
                        <th>Price</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($properties)): ?>
                        <tr><td colspan="8" class="text-center">No user properties found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($properties as $p): ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><?= htmlspecialchars($p['user_name']) ?><br><small><?= htmlspecialchars($p['user_email']) ?></small></td>
                            <td><?= htmlspecialchars($p['title']) ?></td>
                            <td>₹<?= number_format($p['price'], 2) ?></td>
                            <td><?= htmlspecialchars($p['city']) ?></td>
                            <td>
                                <span class="badge <?= $p['status'] == 'approved' ? 'bg-success' : ($p['status'] == 'pending' ? 'bg-warning' : 'bg-danger') ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($p['admin_remarks'] ?? '—') ?></td>
                            <td>
                                <a href="edit_user_property.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="delete_user_property.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this property?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <a href="admin_user_properties.php" class="btn btn-secondary btn-sm"><i class="fas fa-sync"></i> Refresh</a>
        <span class="text-muted ms-3">Total: <?= count($properties) ?> properties</span>
    </div>
</div>

<?php include 'footer.php'; ?>
