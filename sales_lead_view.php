<?php
// ============================================================
// 👁️ Lead View & Edit with Activity Timeline
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🔥 TIMEZONE FIX (India ke time ke liye)
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['sales', 'admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin');
$lead_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$lead_id) {
    header("Location: sales_leads.php");
    exit;
}

// ---- Handle Update Lead Form ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lead'])) {
    // Purani details fetch karein timeline ke liye
    $old_stmt = $pdo->prepare("SELECT status, priority FROM sales_leads WHERE id = ?");
    $old_stmt->execute([$lead_id]);
    $old_data = $old_stmt->fetch();

    // Update Query
    $stmt = $pdo->prepare("
        UPDATE sales_leads 
        SET status = ?, priority = ?, follow_up_date = ?, alternate_number = ? 
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['status'], 
        $_POST['priority'], 
        !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null,
        $_POST['alternate_number'] ?? null,
        $lead_id
    ]);

    // Timeline me change save karein
    $notes = [];
    if ($old_data['status'] != $_POST['status']) {
        $notes[] = "Status changed from '{$old_data['status']}' to '{$_POST['status']}'";
    }
    if ($old_data['priority'] != $_POST['priority']) {
        $notes[] = "Priority changed from '{$old_data['priority']}' to '{$_POST['priority']}'";
    }

    if (!empty($notes)) {
        $log_stmt = $pdo->prepare("INSERT INTO sales_lead_history (lead_id, user_id, action_type, notes) VALUES (?, ?, 'updated', ?)");
        $log_stmt->execute([$lead_id, $user_id, implode(" | ", $notes)]);
    }

    header("Location: sales_lead_view.php?id=$lead_id&msg=updated");
    exit;
}

// ---- Handle Add Note ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note = trim($_POST['note_text']);
    if (!empty($note)) {
        $log_stmt = $pdo->prepare("INSERT INTO sales_lead_history (lead_id, user_id, action_type, notes) VALUES (?, ?, 'note', ?)");
        $log_stmt->execute([$lead_id, $user_id, $note]);
        header("Location: sales_lead_view.php?id=$lead_id&msg=note_added");
        exit;
    }
}

// ---- Fetch Lead Details ----
$stmt = $pdo->prepare("SELECT l.*, u.name as assigned_name FROM sales_leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE l.id = ?");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();

if (!$lead) {
    echo "<div class='alert alert-danger m-4'>Lead not found!</div>";
    include 'footer.php';
    exit;
}

// ---- Fetch History / Activity ----
$hist_stmt = $pdo->prepare("
    SELECT h.*, u.name as user_name 
    FROM sales_lead_history h 
    LEFT JOIN users u ON h.user_id = u.id 
    WHERE h.lead_id = ? 
    ORDER BY h.created_at DESC
");
$hist_stmt->execute([$lead_id]);
$history = $hist_stmt->fetchAll();

include 'header.php';
?>

<style>
    .info-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.03); padding: 24px; }
    .notes-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.03); padding: 24px; height: 100%; }
    
    /* Timeline CSS */
    .timeline { position: relative; padding-left: 20px; margin-top: 20px; }
    .timeline::before { content: ''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
    .timeline-item { position: relative; margin-bottom: 20px; }
    .timeline-icon {
        position: absolute; left: -21px; top: 2px; width: 16px; height: 16px;
        border-radius: 50%; background: #10b981; border: 3px solid #fff; box-shadow: 0 0 0 2px #10b981;
    }
    .timeline-content { background: #f0fdf4; padding: 12px 16px; border-radius: 12px; border: 1px solid #bbf7d0; }
    
    .form-label { font-weight: 700; font-size: 0.85rem; color: #475569; margin-bottom: 4px; }
    .form-control, .form-select { border-radius: 10px; border: 1.5px solid #e2e8f0; font-size: 0.9rem; padding: 8px 12px; }
</style>

<div class="container-fluid py-4">
    <!-- Flash Messages -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> 
            <?php 
                if($_GET['msg'] == 'updated') echo "Lead updated successfully!";
                if($_GET['msg'] == 'note_added') echo "Note added to timeline!";
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Lead #<?= $lead['id'] ?> - <?= htmlspecialchars($lead['name']) ?></h4>
        <a href="sales_leads.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-arrow-left me-1"></i> Back to Leads</a>
    </div>

    <div class="row g-4">
        <!-- LEFT SIDE: Edit Form -->
        <div class="col-md-7">
            <div class="info-card">
                <h5 class="fw-bold text-primary mb-4 border-bottom pb-2"><i class="fas fa-edit me-2"></i> Lead Information</h5>
                <form method="POST">
                    <input type="hidden" name="update_lead" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Primary Phone</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($lead['phone']) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alternate Number</label>
                            <input type="text" name="alternate_number" class="form-control" value="<?= htmlspecialchars($lead['alternate_number'] ?? '') ?>" placeholder="Party ka dusra number">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($lead['city'] ?? '') ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Property Type</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($lead['property_type'] ?? '') ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="new" <?= $lead['status'] == 'new' ? 'selected' : '' ?>>New</option>
                                <option value="contacted" <?= $lead['status'] == 'contacted' ? 'selected' : '' ?>>Contacted</option>
                                <!-- 🔥 CALL NOT PICKED OPTION -->
                                <option value="call_not_picked" <?= $lead['status'] == 'call_not_picked' ? 'selected' : '' ?>>Call Not Picked</option>
                                
                                <option value="interested" <?= $lead['status'] == 'interested' ? 'selected' : '' ?>>Interested</option>
                                <option value="not_interested" <?= $lead['status'] == 'not_interested' ? 'selected' : '' ?>>Not interested</option>
                                <option value="converted" <?= $lead['status'] == 'converted' ? 'selected' : '' ?>>Converted</option>
                                <option value="closed" <?= $lead['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                <option value="low" <?= $lead['priority'] == 'low' ? 'selected' : '' ?>>Low</option>
                                <option value="medium" <?= $lead['priority'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="high" <?= $lead['priority'] == 'high' ? 'selected' : '' ?>>High</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Follow-up Date</label>
                            <input type="date" name="follow_up_date" class="form-control" value="<?= htmlspecialchars($lead['follow_up_date'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4 rounded-pill px-4"><i class="fas fa-save me-1"></i> Update Lead</button>
                </form>
            </div>
        </div>

        <!-- RIGHT SIDE: Notes & Timeline -->
        <div class="col-md-5">
            <div class="notes-card">
                <h5 class="fw-bold text-success mb-3"><i class="fas fa-comments me-2"></i> Notes & Activity</h5>
                
                <!-- Add Note Form -->
                <form method="POST" class="mb-4">
                    <input type="hidden" name="add_note" value="1">
                    <textarea name="note_text" class="form-control mb-2" rows="3" placeholder="Add a note... (e.g. call back after 2 days)" required></textarea>
                    <button type="submit" class="btn btn-success w-100 rounded-pill"><i class="fas fa-plus me-1"></i> Add Note</button>
                </form>

                <!-- Timeline -->
                <div style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($history)): ?>
                        <p class="text-muted text-center py-3"><small>No activity recorded yet.</small></p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($history as $h): 
                                // 🔥 UTC to IST Time Fix
                                $dt = new DateTime($h['created_at'], new DateTimeZone('UTC'));
                                $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                $formatted_time = $dt->format('d M Y, h:i A');
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon" style="background: <?= $h['action_type'] == 'updated' ? '#3b82f6' : '#10b981' ?>; box-shadow: 0 0 0 2px <?= $h['action_type'] == 'updated' ? '#3b82f6' : '#10b981' ?>;"></div>
                                    <div class="timeline-content" style="background: <?= $h['action_type'] == 'updated' ? '#eff6ff' : '#f0fdf4' ?>; border-color: <?= $h['action_type'] == 'updated' ? '#bfdbfe' : '#bbf7d0' ?>;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong style="font-size: 0.8rem; color: #1e293b;"><i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($h['user_name'] ?? 'System') ?></strong>
                                            <small class="text-muted" style="font-size: 0.7rem;"><i class="far fa-clock me-1"></i> <?= $formatted_time ?></small>
                                        </div>
                                        <p class="mb-0 text-dark" style="font-size: 0.85rem;"><?= htmlspecialchars($h['notes']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
