<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php';

$message = '';
$error = '';

// Submit new ticket
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $message_text = trim($_POST['message']);
    $screenshot = '';
    if(isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] == 0) {
        $upload_dir = 'uploads/support/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
        $filename = 'support_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['screenshot']['tmp_name'], $upload_dir . $filename);
        $screenshot = $upload_dir . $filename;
    }
    if(!empty($subject) && !empty($message_text)) {
        $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, screenshot, status) VALUES (?, ?, ?, ?, 'open')")->execute([$user_id, $subject, $message_text, $screenshot]);
        $message = "✅ Ticket submitted successfully! We will get back to you soon.";
    } else {
        $error = "❌ Subject and message are required.";
    }
}

// Fetch user's tickets
$tickets = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC");
$tickets->execute([$user_id]);
$tickets = $tickets->fetchAll();

// View specific ticket
$ticket_detail = null;
$replies = [];
if(isset($_GET['ticket_id'])) {
    $tid = (int)$_GET['ticket_id'];
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->execute([$tid, $user_id]);
    $ticket_detail = $stmt->fetch();
    if($ticket_detail) {
        $reply_stmt = $pdo->prepare("SELECT r.*, u.name as user_name, r.is_admin FROM ticket_replies r JOIN users u ON r.user_id = u.id WHERE r.ticket_id = ? ORDER BY r.created_at ASC");
        $reply_stmt->execute([$tid]);
        $replies = $reply_stmt->fetchAll();
    }
}
?>
<style>
    .ticket-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border-left: 4px solid #2563eb; }
    .ticket-card.closed { border-left-color: #6c757d; opacity:0.7; }
    .reply-item { padding: 10px; margin-bottom: 10px; border-radius: 12px; }
    .reply-admin { background: #e0e7ff; }
    .reply-user { background: #f1f5f9; }
</style>
<div class="container-fluid">
    <h4><i class="fas fa-headset me-2"></i>Support</h4>
    <?php if($message) echo "<div class='alert alert-success'>$message</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <?php if($ticket_detail): ?>
        <a href="support.php" class="btn btn-secondary mb-3">⬅ Back to My Tickets</a>
        <div class="card p-4">
            <h5>Ticket: <?= htmlspecialchars($ticket_detail['subject']) ?></h5>
            <p><strong>Status:</strong> <span class="badge bg-<?= ($ticket_detail['status']=='open')?'warning':($ticket_detail['status']=='in_progress'?'info':'secondary') ?>"><?= ucfirst($ticket_detail['status']) ?></span></p>
            <div class="border p-3 bg-light rounded-3 mb-3">
                <strong>Your Message:</strong><br>
                <?= nl2br(htmlspecialchars($ticket_detail['message'])) ?>
                <?php if($ticket_detail['screenshot']): ?>
                    <br><a href="<?= $ticket_detail['screenshot'] ?>" target="_blank">📷 View Screenshot</a>
                <?php endif; ?>
            </div>
            <hr>
            <h6>Conversation</h6>
            <?php foreach($replies as $r): ?>
                <div class="reply-item <?= $r['is_admin'] ? 'reply-admin' : 'reply-user' ?>">
                    <strong><?= $r['is_admin'] ? 'Admin' : 'You' ?></strong>
                    <small class="text-muted"><?= date('d M Y, h:i A', strtotime($r['created_at'])) ?></small>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($r['message'])) ?></p>
                </div>
            <?php endforeach; ?>
            <?php if(count($replies)==0) echo "<p class='text-muted'>No replies yet.</p>"; ?>
            <?php if($ticket_detail['status'] == 'closed'): ?>
                <div class="alert alert-secondary">This ticket is closed.</div>
            <?php endif; ?>
            <a href="support.php" class="btn btn-outline-primary mt-2">Back to List</a>
        </div>
    <?php else: ?>
        <!-- New Ticket Form -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <h5><i class="fas fa-plus-circle me-2"></i>Submit New Ticket</h5>
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Subject</label>
                    <input type="text" name="subject" class="form-control" placeholder="Brief subject of your issue" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Message</label>
                    <textarea name="message" class="form-control" rows="4" placeholder="Describe your problem in detail..." required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Screenshot (Optional)</label>
                    <input type="file" name="screenshot" class="form-control" accept=".jpg,.jpeg,.png,.gif">
                    <small class="text-muted">Upload a screenshot (max 5MB) to help us understand better.</small>
                </div>
                <button type="submit" name="submit_ticket" class="btn btn-primary">Submit Ticket</button>
            </form>
        </div>

        <!-- My Tickets List -->
        <div class="mt-4">
            <h5><i class="fas fa-list me-2"></i>My Tickets</h5>
            <?php if(count($tickets) > 0): ?>
                <?php foreach($tickets as $t): 
                    $status_class = $t['status'] == 'open' ? 'warning' : ($t['status'] == 'in_progress' ? 'info' : 'secondary');
                ?>
                    <div class="ticket-card <?= ($t['status'] == 'closed') ? 'closed' : '' ?>">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold"><?= htmlspecialchars($t['subject']) ?></span>
                            <span class="badge bg-<?= $status_class ?>"><?= ucfirst($t['status']) ?></span>
                        </div>
                        <p class="mb-1 small"><?= nl2br(htmlspecialchars(substr($t['message'],0,100))) ?>...</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small"><?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></span>
                            <a href="?ticket_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary">View & Replies</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">You haven't submitted any tickets yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
