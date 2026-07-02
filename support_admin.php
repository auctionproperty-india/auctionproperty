<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

include 'header.php';

// Update status
if(isset($_GET['update_status']) && isset($_GET['ticket_id']) && isset($_GET['status'])) {
    $ticket_id = (int)$_GET['ticket_id'];
    $new_status = $_GET['status'];
    $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$new_status, $ticket_id]);
    header("Location: support_admin.php?updated=1");
    exit;
}

$tickets = $pdo->query("SELECT t.*, u.name as user_name, u.email as user_email FROM support_tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC")->fetchAll();
?>
<div class="container-fluid">
    <h4><i class="fas fa-headset me-2"></i>Support Tickets (Admin)</h4>
    <?php if(isset($_GET['updated'])) echo "<div class='alert alert-success'>✅ Status updated!</div>"; ?>
    <div class="card-premium">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>User</th><th>Subject</th><th>Message</th><th>Screenshot</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if(count($tickets) > 0): ?>
                    <?php foreach($tickets as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['user_name']) ?><br><small><?= $t['user_email'] ?></small></td>
                            <td><strong><?= htmlspecialchars($t['subject']) ?></strong></td>
                            <td><?= nl2br(htmlspecialchars($t['message'])) ?></td>
                            <td>
                                <?php if($t['screenshot']): ?>
                                    <a href="<?= $t['screenshot'] ?>" target="_blank" class="btn btn-sm btn-info">View</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?= ($t['status']=='open')?'warning':($t['status']=='in_progress'?'info':'secondary') ?>"><?= ucfirst($t['status']) ?></span></td>
                            <td>
                                <a href="?update_status=1&ticket_id=<?= $t['id'] ?>&status=in_progress" class="btn btn-sm btn-primary">In Progress</a>
                                <a href="?update_status=1&ticket_id=<?= $t['id'] ?>&status=closed" class="btn btn-sm btn-success">Close</a>
                                <a href="?update_status=1&ticket_id=<?= $t['id'] ?>&status=open" class="btn btn-sm btn-warning">Re-open</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center">No tickets yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
