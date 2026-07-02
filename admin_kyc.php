<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

include 'header.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['doc_id'])) {
    $doc_id = (int)$_POST['doc_id'];
    $action = $_POST['action'];
    $reason = trim($_POST['reason'] ?? '');
    if($action == 'approve') {
        $pdo->prepare("UPDATE kyc_documents SET status = 'approved' WHERE id = ?")->execute([$doc_id]);
        $msg = "✅ KYC Approved!";
    } else {
        $pdo->prepare("UPDATE kyc_documents SET status = 'rejected', reason = ? WHERE id = ?")->execute([$reason, $doc_id]);
        $msg = "❌ KYC Rejected with reason.";
    }
    header("Location: admin_kyc.php?msg=".urlencode($msg));
    exit;
}

$kyc_list = $pdo->query("SELECT k.*, u.name as user_name, u.email as user_email FROM kyc_documents k JOIN users u ON k.user_id = u.id ORDER BY k.uploaded_at DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-id-card me-2"></i>KYC Documents</h4>
    <?php if(isset($_GET['msg'])) echo "<div class='alert alert-info'>".htmlspecialchars($_GET['msg'])."</div>"; ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead><tr><th>User</th><th>Type</th><th>File</th><th>Status</th><th>Reason</th><th>Uploaded</th><th>Action</th></tr></thead>
            <tbody>
            <?php if(count($kyc_list)>0): foreach($kyc_list as $k): ?>
                <tr>
                    <td><?= htmlspecialchars($k['user_name']) ?><br><small><?= $k['user_email'] ?></small></td>
                    <td><?= ucfirst($k['doc_type']) ?></td>
                    <td><a href="<?= $k['file_path'] ?>" target="_blank">View</a></td>
                    <td><span class="badge bg-<?= ($k['status']=='approved')?'success':($k['status']=='pending'?'warning':'danger') ?>"><?= ucfirst($k['status']) ?></span></td>
                    <td><?= htmlspecialchars($k['reason'] ?? '') ?></td>
                    <td><?= date('d M Y', strtotime($k['uploaded_at'])) ?></td>
                    <td>
                        <?php if($k['status'] == 'pending'): ?>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="doc_id" value="<?= $k['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Approve this document?')">✅ Approve</button>
                            </form>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-docid="<?= $k['id'] ?>">❌ Reject</button>
                        <?php else: ?>
                            <span class="text-muted">Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; else: echo "<tr><td colspan='7' class='text-center'>No KYC documents.</td></tr>"; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5>Reject KYC</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="doc_id" id="reject_doc_id" value="">
                <input type="hidden" name="action" value="reject">
                <div class="mb-3">
                    <label>Reason for Rejection</label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Enter reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.querySelectorAll('[data-bs-target="#rejectModal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('reject_doc_id').value = this.dataset.docid;
        });
    });
</script>
<?php include 'footer.php'; ?>
