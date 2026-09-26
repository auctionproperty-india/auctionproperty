<?php
// ============================================================
// 👁️ Sales CRM – View / Edit Lead (with Notes & Follow-ups)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['sales', 'admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin');
$lead_id = (int)($_GET['id'] ?? 0);

if ($lead_id <= 0) {
    header("Location: sales_leads.php");
    exit;
}

$message = '';
$message_type = '';

// Fetch lead
$stmt = $pdo->prepare("SELECT l.*, u.name as assigned_name FROM sales_leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE l.id = ?");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();

if (!$lead) {
    die("<div class='alert alert-danger m-4'>Lead not found.</div>");
}

// Permission check
if (!$is_admin && $lead['assigned_to'] != $user_id) {
    die("<div class='alert alert-danger m-4'>You don't have permission to view this lead.</div>");
}

// ---- Handle Update Lead ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lead'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $property_type = trim($_POST['property_type'] ?? '');
    $budget_min = !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : null;
    $budget_max = !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : null;
    $status = $_POST['status'] ?? 'new';
    $priority = $_POST['priority'] ?? 'medium';
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $old_status = $lead['status'];

    try {
        $pdo->prepare("
            UPDATE sales_leads 
            SET name=?, email=?, phone=?, city=?, property_type=?, 
                budget_min=?, budget_max=?, status=?, priority=?, 
                follow_up_date=?, updated_at=CURRENT_TIMESTAMP
            WHERE id=?
        ")->execute([$name, $email, $phone, $city, $property_type, 
                     $budget_min, $budget_max, $status, $priority, $follow_up_date, $lead_id]);

        // Log status change
        if ($old_status != $status) {
            $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note, old_status, new_status) VALUES (?, ?, 'status_change', ?, ?, ?)")
                ->execute([$lead_id, $user_id, "Status changed from $old_status to $status", $old_status, $status]);
        }

        $message = "✅ Lead updated successfully!";
        $message_type = "success";
        
        // Refresh
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch();
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ---- Handle Add Note ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note = trim($_POST['note'] ?? '');
    if (!empty($note)) {
        $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note) VALUES (?, ?, 'note', ?)")
            ->execute([$lead_id, $user_id, $note]);
        $message = "✅ Note added!";
        $message_type = "success";
    }
}

// ---- Handle Add Follow-up ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_followup'])) {
    $fdate = $_POST['followup_date'] ?? '';
    $fremark = trim($_POST['followup_remarks'] ?? '');
    $ftype = $_POST['followup_type'] ?? 'call';
    if (!empty($fdate)) {
        $pdo->prepare("INSERT INTO sales_followups (lead_id, user_id, followup_date, followup_type, remarks) VALUES (?, ?, ?, ?, ?)")
            ->execute([$lead_id, $user_id, $fdate, $ftype, $fremark]);
        // Also update lead's follow_up_date
        $pdo->prepare("UPDATE sales_leads SET follow_up_date = ? WHERE id = ?")->execute([$fdate, $lead_id]);
        $message = "✅ Follow-up scheduled!";
        $message_type = "success";
    }
}

// ---- Handle Delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lead']) && $is_admin) {
    $pdo->prepare("DELETE FROM sales_leads WHERE id = ?")->execute([$lead_id]);
    header("Location: sales_leads.php");
    exit;
}

// Fetch notes
$notes = $pdo->prepare("SELECT n.*, u.name as user_name FROM sales_lead_notes n LEFT JOIN users u ON n.user_id = u.id WHERE n.lead_id = ? ORDER BY n.id DESC");
$notes->execute([$lead_id]);
$notes = $notes->fetchAll();

// Fetch follow-ups
$followups = $pdo->prepare("SELECT f.*, u.name as user_name FROM sales_followups f LEFT JOIN users u ON f.user_id = u.id WHERE f.lead_id = ? ORDER BY f.followup_date DESC");
$followups->execute([$lead_id]);
$followups = $followups->fetchAll();

include 'header.php';
?>

<style>
    .lead-container {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .lead-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 24px 30px;
    }
    .lead-header h3 { margin: 0; font-weight: 800; font-size: 1.4rem; }
    .lead-header .meta { font-size: 0.85rem; opacity: 0.85; margin-top: 6px; }

    .lead-body { padding: 30px; }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }
    .info-box {
        background: #f8fafc;
        border-radius: 12px;
        padding: 14px 16px;
        border-left: 4px solid #2563eb;
    }
    .info-box .lbl {
        font-size: 0.65rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.6px;
        margin-bottom: 4px;
    }
    .info-box .val {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
        word-break: break-word;
    }

    .status-select {
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        padding: 8px 14px;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .notes-timeline {
        max-height: 350px;
        overflow-y: auto;
    }
    .note-item {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 10px;
        border-left: 3px solid #2563eb;
    }
    .note-item.status-change { border-left-color: #f59e0b; background: #fffbeb; }
    .note-item.initial { border-left-color: #10b981; background: #f0fdf4; }
    .note-item .note-meta {
        font-size: 0.72rem;
        color: #64748b;
        margin-bottom: 4px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 6px;
    }
    .note-item .note-text {
        font-size: 0.85rem;
        color: #0f172a;
        font-weight: 500;
    }

    .followup-item {
        background: #fff;
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 10px;
    }
    .followup-item.upcoming { border-left: 4px solid #2563eb; }
    .followup-item.overdue { border-left: 4px solid #dc2626; background: #fef2f2; }
    .followup-item.done { border-left: 4px solid #10b981; background: #f0fdf4; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <a href="sales_leads.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-1"></i> Back to Leads
        </a>
        <div class="d-flex gap-2">
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $lead['phone']) ?>" target="_blank" class="btn btn-success rounded-pill px-3">
                <i class="fab fa-whatsapp me-1"></i> WhatsApp
            </a>
            <a href="tel:<?= $lead['phone'] ?>" class="btn btn-primary rounded-pill px-3">
                <i class="fas fa-phone me-1"></i> Call
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= $message ?></div>
    <?php endif; ?>

    <div class="lead-container">
        <!-- Header -->
        <div class="lead-header">
            <h3><?= htmlspecialchars($lead['name']) ?> <small style="opacity:0.6;font-size:0.9rem;">#<?= $lead['id'] ?></small></h3>
            <div class="meta">
                <i class="fas fa-phone me-1"></i> <?= htmlspecialchars($lead['phone']) ?>
                <?php if ($lead['email']): ?> &nbsp;|&nbsp; <i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($lead['email']) ?><?php endif; ?>
                <?php if ($lead['city']): ?> &nbsp;|&nbsp; <i class="fas fa-map-pin me-1"></i> <?= htmlspecialchars($lead['city']) ?><?php endif; ?>
            </div>
        </div>

        <div class="lead-body">
            <div class="row g-4">
                <!-- LEFT: Edit Form -->
                <div class="col-lg-7">
                    <h6 class="fw-bold mb-3"><i class="fas fa-edit me-2 text-primary"></i> Lead Information</h6>
                    <form method="POST">
                        <input type="hidden" name="update_lead" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small fw-bold">Name</label>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($lead['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($lead['phone']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($lead['email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">City</label>
                                <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($lead['city'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Property Type</label>
                                <input type="text" name="property_type" class="form-control" value="<?= htmlspecialchars($lead['property_type'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Budget Min (₹)</label>
                                <input type="number" name="budget_min" class="form-control" value="<?= htmlspecialchars($lead['budget_min'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Budget Max (₹)</label>
                                <input type="number" name="budget_max" class="form-control" value="<?= htmlspecialchars($lead['budget_max'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Status</label>
                                <select name="status" class="form-select status-select">
                                    <?php foreach(['new','contacted','interested','not_interested','converted','closed'] as $st): ?>
                                        <option value="<?= $st ?>" <?= $lead['status']==$st?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Priority</label>
                                <select name="priority" class="form-select">
                                    <?php foreach(['low','medium','high'] as $pr): ?>
                                        <option value="<?= $pr ?>" <?= $lead['priority']==$pr?'selected':'' ?>><?= ucfirst($pr) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Follow-up Date</label>
                                <input type="date" name="follow_up_date" class="form-control" value="<?= htmlspecialchars($lead['follow_up_date'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary rounded-pill px-4">
                                <i class="fas fa-save me-1"></i> Update Lead
                            </button>
                            <?php if ($is_admin): ?>
                                <button type="submit" name="delete_lead" value="1" class="btn btn-outline-danger rounded-pill px-4" 
                                        onclick="return confirm('⚠️ Delete this lead permanently?');">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Follow-ups -->
                    <h6 class="fw-bold mt-4 mb-3"><i class="fas fa-bell me-2 text-warning"></i> Follow-ups</h6>
                    
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="add_followup" value="1">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <input type="date" name="followup_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-3">
                                <select name="followup_type" class="form-select">
                                    <option value="call">Call</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">Email</option>
                                    <option value="visit">Visit</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="followup_remarks" class="form-control" placeholder="Remarks...">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-warning w-100 rounded-pill"><i class="fas fa-plus"></i></button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($followups)): ?>
                        <p class="text-muted small">No follow-ups scheduled.</p>
                    <?php else: foreach ($followups as $f): 
                        $cls = 'upcoming';
                        if ($f['status'] == 'done') $cls = 'done';
                        elseif (strtotime($f['followup_date']) < strtotime(date('Y-m-d'))) $cls = 'overdue';
                    ?>
                        <div class="followup-item <?= $cls ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong style="font-size:0.85rem;">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('d M Y', strtotime($f['followup_date'])) ?>
                                    </strong>
                                    <span class="badge bg-info text-dark ms-2" style="font-size:0.65rem;"><?= ucfirst($f['followup_type']) ?></span>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($f['user_name'] ?? '') ?></small>
                            </div>
                            <?php if (!empty($f['remarks'])): ?>
                                <div class="mt-1 small text-muted"><?= htmlspecialchars($f['remarks']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- RIGHT: Notes Timeline -->
                <div class="col-lg-5">
                    <h6 class="fw-bold mb-3"><i class="fas fa-comment-dots me-2 text-success"></i> Notes & Activity</h6>
                    
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="add_note" value="1">
                        <textarea name="note" class="form-control mb-2" rows="3" placeholder="Add a note..." required></textarea>
                        <button type="submit" class="btn btn-success btn-sm rounded-pill px-4">
                            <i class="fas fa-plus me-1"></i> Add Note
                        </button>
                    </form>

                    <div class="notes-timeline">
                        <?php if (empty($notes)): ?>
                            <p class="text-muted small">No notes yet.</p>
                        <?php else: foreach ($notes as $n): ?>
                            <div class="note-item <?= $n['note_type'] ?>">
                                <div class="note-meta">
                                    <span><i class="fas fa-user me-1"></i><?= htmlspecialchars($n['user_name'] ?? 'Unknown') ?></span>
                                    <span><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></span>
                                </div>
                                <div class="note-text"><?= nl2br(htmlspecialchars($n['note'])) ?></div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
