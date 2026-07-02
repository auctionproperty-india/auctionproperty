<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

include 'header.php';

$ticket_id = $_GET['id'] ?? 0;

if($ticket_id) {
    // Fetch ticket with user info
    $stmt = $pdo->prepare("SELECT t.*, u.name as user_name, u.email as user_email FROM support_tickets t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    if(!$ticket) { die("Ticket not found"); }

    // Handle reply
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_message'])) {
        $msg = trim($_POST['reply_message']);
        if(!empty($msg)) {
            $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, TRUE)")->execute([$ticket_id, $_SESSION['user_id'], $msg]);
            if($ticket['status'] != 'closed') {
                $pdo->prepare("UPDATE support_tickets SET status = 'in_progress' WHERE id = ?")->execute([$ticket_id]);
            }
            header("Location: support_admin.php?id=".$ticket_id);
            exit;
        }
    }

    // Close ticket
    if(isset($_GET['close'])) {
        $pdo->prepare("UPDATE support_tickets SET status = 'closed' WHERE id = ?")->execute([$ticket_id]);
        header("Location: support_admin.php?id=".$ticket_id."&closed=1");
        exit;
    }
    if(isset($_GET['reopen'])) {
        $pdo->prepare("UPDATE support_tickets SET status = 'open' WHERE id = ?")->execute([$ticket_id]);
        header("Location: support_admin.php?id=".$ticket_id);
        exit;
    }

    // Fetch replies
    $replies = $pdo->prepare("SELECT r.*, u.name as user_name, r.is_admin FROM ticket_replies r JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
    $replies->execute([$ticket_id]);
    $replies = $replies->fetchAll();
    ?>
    <div class="container-fluid">
        <a href="support_admin.php" class="btn btn-secondary mb-3">⬅ Back to All Tickets</a>
        <?php if(isset($_GET['closed'])) echo "<div class='alert alert-success'>✅ Ticket closed.</div>"; ?>
        <div class="card p-4">
            <h4>Ticket #<?= $ticket_id ?> – <?= htmlspecialchars($ticket['subject']) ?></h4>
            <p><strong>From:</strong> <?= htmlspecialchars($ticket['user_name']) ?> (<?= $ticket['user_email'] ?>)</p>
            <p><strong>Status:</strong> <span class="badge bg-<?= ($ticket['status']=='open')?'warning':($ticket['status']=='in_progress'?'info':'secondary') ?>"><?= ucfirst($ticket['status']) ?></span></p>
            <div class="border p-3 bg-light rounded-3 mb-3">
                <strong>Initial Message:</strong><br>
                <?= nl2br(htmlspecialchars($ticket['message'])) ?>
                <?php if($ticket['screenshot']): ?>
                    <br><a href="<?= $ticket['screenshot'] ?>" target="_blank">📷 View Screenshot</a>
                <?php endif; ?>
            </div>
            <hr>
            <div class="mb-3">
                <h5>Conversation</h5>
                <?php foreach($replies as $r): ?>
                    <div class="p-2 mb-2 rounded-3" style="background: <?= $r['is_admin'] ? '#e0e7ff' : '#f1f5f9' ?>;">
                        <strong><?= $r['is_admin'] ? 'Admin' : htmlspecialchars($r['user_name']) ?></strong>
                        <small class="text-muted"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></small>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($r['message'])) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if(count($replies)==0) echo "<p class='text-muted'>No replies yet.</p>"; ?>
            </div>
            <?php if($ticket['status'] != 'closed'): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>Reply</label>
                        <textarea name="reply_message" class="form-control" rows="3" required placeholder="Type your reply..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                    <a href="?id=<?= $ticket_id ?>&close=1" class="btn btn-success" onclick="return confirm('Close this ticket?')">✅ Done / Close</a>
                </form>
            <?php else: ?>
                <div class="alert alert-secondary">This ticket is closed. <a href="?id=<?= $ticket_id ?>&reopen=1" onclick="return confirm('Reopen this ticket?')" class="btn btn-sm btn-warning">Reopen</a></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    include 'footer.php';
    exit;
}

// ---- List all tickets ----
$tickets = $pdo->query("SELECT t.*, u.name as user_name, u.email as user_email FROM support_tickets t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-headset me-2"></i>Support Tickets</h4>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead><tr><th>User</th><th>Subject</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            <?php if(count($tickets)>0): foreach($tickets as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['user_name']) ?><br><small><?= $t['user_email'] ?></small></td>
                    <td><?= htmlspecialchars($t['subject']) ?></td>
                    <td><span class="badge bg-<?= ($t['status']=='open')?'warning':($t['status']=='in_progress'?'info':'secondary') ?>"><?= ucfirst($t['status']) ?></span></td>
                    <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                    <td><a href="?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">View & Reply</a></td>
                </tr>
            <?php endforeach; else: echo "<tr><td colspan='5' class='text-center'>No tickets.</td></tr>"; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
