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

// Submit ticket
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
?>
<style>
    .ticket-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border-left: 4px solid #2563eb;
    }
    .ticket-card.closed { border-left-color: #6c757d; }
    .ticket-card .ticket-subject { font-weight: 700; }
    .ticket-card .ticket-meta { font-size: 0.8rem; color: #64748b; }
</style>

<div class="container-fluid">
    <h4 class="mb-4"><i class="fas fa-headset me-2"></i>Support</h4>
    <?php if($message) echo "<div class='alert alert-success'>$message</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

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

    <div class="mt-4">
        <h5><i class="fas fa-list me-2"></i>My Tickets</h5>
        <?php if(count($tickets) > 0): ?>
            <?php foreach($tickets as $t): 
                $status_class = $t['status'] == 'open' ? 'warning' : ($t['status'] == 'in_progress' ? 'info' : 'secondary');
            ?>
                <div class="ticket-card <?= ($t['status'] == 'closed') ? 'closed' : '' ?>">
                    <div class="d-flex justify-content-between">
                        <span class="ticket-subject"><?= htmlspecialchars($t['subject']) ?></span>
                        <span class="badge bg-<?= $status_class ?>"><?= ucfirst($t['status']) ?></span>
                    </div>
                    <p class="mb-1"><?= nl2br(htmlspecialchars($t['message'])) ?></p>
                    <?php if($t['screenshot']): ?>
                        <a href="<?= $t['screenshot'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">📷 View Screenshot</a>
                    <?php endif; ?>
                    <div class="ticket-meta mt-2">Submitted: <?= date('d M Y, h:i A', strtotime($t['created_at'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted">You haven't submitted any tickets yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
