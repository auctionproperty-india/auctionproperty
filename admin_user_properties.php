<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
if(!hasViewPermission('properties', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ Permission denied.</div>");
}

include 'header.php';

// Handle approve/reject
if(isset($_GET['approve']) || isset($_GET['reject'])) {
    if(!hasEditPermission('properties', $pdo)) die("Permission denied.");
    $id = (int)($_GET['approve'] ?? $_GET['reject']);
    $status = isset($_GET['approve']) ? 'approved' : 'rejected';
    $remarks = $_POST['remarks'] ?? '';
    if($status == 'rejected' && empty($remarks)) {
        // if reject, we can pass remarks via GET or use form
        // We'll use a simple form for reject
        // Better to use a modal but for simplicity, we'll use GET with remarks
    }
    // For simplicity, we'll use a separate form for reject later. For now just approve/reject with no remarks in GET.
    // We'll create a form for reject with remarks.
    if($status == 'approved') {
        $pdo->prepare("UPDATE user_properties SET status = 'approved', admin_remarks = NULL WHERE id = ?")->execute([$id]);
        $msg = "✅ Property Approved!";
    } else {
        // For reject, we need remarks, so we'll use POST
        // We'll handle via POST below
    }
    // Redirect
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_submit'])) {
    $id = (int)$_POST['id'];
    $remarks = trim($_POST['remarks']);
    $pdo->prepare("UPDATE user_properties SET status = 'rejected', admin_remarks = ? WHERE id = ?")->execute([$remarks, $id]);
    header("Location: admin_user_properties.php?msg=rejected");
    exit;
}

$props = $pdo->query("SELECT up.*, u.name as user_name, u.email as user_email FROM user_properties up JOIN users u ON up.user_id = u.id ORDER BY up.created_at DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-building me-2"></i>User Properties (Customer)</h4>
    <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead><tr><th>User</th><th>Title</th><th>Price</th><th>City</th><th>Status</th><th>Image</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(count($props)>0): foreach($props as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['user_name']) ?><br><small><?= $p['user_email'] ?></small></td>
                    <td><?= htmlspecialchars($p['title']) ?></td>
                    <td>₹<?= indianCurrencyFormat($p['price']) ?></td>
                    <td><?= htmlspecialchars($p['city']) ?></td>
                    <td><span class="badge bg-<?= ($p['status']=='approved')?'success':($p['status']=='pending'?'warning':'danger') ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td>
                        <?php if($p['image_url'] && file_exists($p['image_url'])): ?>
                            <a href="<?= $p['image_url'] ?>" target="_blank">View</a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($p['status'] == 'pending'): ?>
                            <?php if(hasEditPermission('properties', $pdo)): ?>
                                <a href="?approve=<?= $p['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Approve?')">✅ Approve</a>
                                <!-- Reject with Remarks -->
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?= $p['id'] ?>">❌ Reject</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: echo "<tr><td colspan='7' class='text-center'>No user properties.</td></tr>"; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5>Reject Property</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" id="reject_id" value="">
                <div class="mb-3">
                    <label>Reason for Rejection</label>
                    <textarea name="remarks" class="form-control" rows="3" required placeholder="Enter reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="reject_submit" class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.querySelectorAll('[data-bs-target="#rejectModal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('reject_id').value = this.dataset.id;
        });
    });
</script>
<?php include 'footer.php'; ?>
