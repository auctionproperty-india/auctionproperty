<?php
// ============================================================
// 📋 Admin – All Leads with Transfer & Bulk Actions
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// ============================================================
// 🔥 BULK TRANSFER HANDLER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_transfer'])) {
    $lead_ids = $_POST['lead_ids'] ?? [];
    $new_assigned = (int)($_POST['new_assigned'] ?? 0);
    $transfer_reason = trim($_POST['reason'] ?? '');

    if (empty($lead_ids)) {
        $message = "❌ No leads selected!";
        $message_type = "danger";
    } else {
        try {
            $pdo->beginTransaction();
            $count = 0;
            foreach ($lead_ids as $lid) {
                $lid = (int)$lid;
                // Get old assigned
                $old_stmt = $pdo->prepare("SELECT assigned_to, name FROM sales_leads WHERE id = ?");
                $old_stmt->execute([$lid]);
                $old_data = $old_stmt->fetch();

                // Transfer
                $pdo->prepare("UPDATE sales_leads SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$new_assigned ?: null, $lid]);

                // Transfer history
                $pdo->prepare("INSERT INTO sales_lead_transfers (lead_id, from_user_id, to_user_id, transferred_by, reason) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$lid, $old_data['assigned_to'] ?? null, $new_assigned, $user_id, $transfer_reason]);

                // Add note
                $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note) VALUES (?, ?, 'transfer', ?)")
                    ->execute([$lid, $user_id, "Transferred to User ID: $new_assigned | Reason: $transfer_reason"]);

                $count++;
            }
            $pdo->commit();
            $message = "✅ Successfully transferred <b>$count</b> leads!";
            $message_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// ============================================================
// 🔥 SINGLE TRANSFER HANDLER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_transfer'])) {
    $lead_id = (int)$_POST['lead_id'];
    $new_assigned = (int)$_POST['new_assigned'];
    $transfer_reason = trim($_POST['reason'] ?? 'Quick reassign');

    try {
        $old_stmt = $pdo->prepare("SELECT assigned_to FROM sales_leads WHERE id = ?");
        $old_stmt->execute([$lead_id]);
        $old_assigned = $old_stmt->fetchColumn();

        $pdo->prepare("UPDATE sales_leads SET assigned_to = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$new_assigned ?: null, $lead_id]);

        $pdo->prepare("INSERT INTO sales_lead_transfers (lead_id, from_user_id, to_user_id, transferred_by, reason) VALUES (?, ?, ?, ?, ?)")
            ->execute([$lead_id, $old_assigned, $new_assigned, $user_id, $transfer_reason]);

        $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note) VALUES (?, ?, 'transfer', ?)")
            ->execute([$lead_id, $user_id, "Reassigned to User ID: $new_assigned | $transfer_reason"]);

        $message = "✅ Lead #$lead_id transferred!";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "❌ " . $e->getMessage();
        $message_type = "danger";
    }
}

// ============================================================
// 🔥 Filters
// ============================================================
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$city_filter = trim($_GET['city'] ?? '');
$assigned_filter = trim($_GET['assigned'] ?? '');
$followup_filter = trim($_GET['followup'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(l.name ILIKE ? OR l.phone ILIKE ? OR l.email ILIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    $where[] = "l.status = ?";
    $params[] = $status_filter;
}

if (!empty($city_filter)) {
    $where[] = "l.city ILIKE ?";
    $params[] = '%' . $city_filter . '%';
}

if ($assigned_filter === 'unassigned') {
    $where[] = "l.assigned_to IS NULL";
} elseif (!empty($assigned_filter) && is_numeric($assigned_filter)) {
    $where[] = "l.assigned_to = ?";
    $params[] = (int)$assigned_filter;
}

if ($followup_filter == 'today') {
    $where[] = "l.follow_up_date = CURRENT_DATE AND l.status NOT IN ('converted','closed','not_interested')";
} elseif ($followup_filter == 'overdue') {
    $where[] = "l.follow_up_date < CURRENT_DATE AND l.status NOT IN ('converted','closed','not_interested')";
}

$where_clause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT l.*, u.name as assigned_name 
    FROM sales_leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    WHERE $where_clause
    ORDER BY l.id DESC
");
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Fetch sales users for transfer dropdown
$sales_users = $pdo->query("SELECT id, name FROM users WHERE role='sales' AND COALESCE(sales_active, TRUE) = TRUE ORDER BY name")->fetchAll();

include 'header.php';
?>

<style>
    .filter-bar {
        background: #fff;
        border-radius: 16px;
        padding: 16px 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        margin-bottom: 16px;
    }
    .filter-bar .form-control, .filter-bar .form-select {
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        font-size: 0.85rem;
        font-weight: 500;
    }
    .filter-bar .form-control:focus, .filter-bar .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }

    .bulk-bar {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        border-radius: 14px;
        padding: 14px 20px;
        margin-bottom: 16px;
        display: none;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }
    .bulk-bar.active { display: flex; }
    .bulk-bar select {
        border-radius: 8px;
        border: none;
        padding: 6px 12px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .bulk-bar input[type=text] {
        border-radius: 8px;
        border: none;
        padding: 6px 12px;
        font-size: 0.85rem;
        min-width: 200px;
    }

    .leads-table {
        width: 100%;
        font-size: 0.85rem;
        border-collapse: collapse;
        background: #fff;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
    }
    .leads-table th {
        background: #1e293b; color: #fff;
        font-size: 0.68rem; text-transform: uppercase;
        padding: 12px 10px; letter-spacing: 0.5px;
        text-align: left; font-weight: 700;
    }
    .leads-table td {
        padding: 11px 10px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .leads-table tr:hover td { background: #f8fafc; }

    .status-pill {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: capitalize;
        white-space: nowrap;
    }
    .st-new { background: #dbeafe; color: #1e40af; }
    .st-contacted { background: #fef3c7; color: #92400e; }
    .st-interested { background: #ddd6fe; color: #5b21b6; }
    .st-not_interested { background: #fee2e2; color: #991b1b; }
    .st-converted { background: #d1fae5; color: #065f46; }
    .st-closed { background: #e2e8f0; color: #475569; }

    .priority-pill {
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
    }
    .pr-high { background: #fee2e2; color: #b91c1c; }
    .pr-medium { background: #fef3c7; color: #92400e; }
    .pr-low { background: #e0f2fe; color: #075985; }

    .assignee-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #eff6ff;
        color: #1e40af;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .assignee-badge.unassigned {
        background: #fef2f2;
        color: #991b1b;
    }

    .transfer-select {
        border-radius: 8px;
        border: 1.5px solid #e2e8f0;
        padding: 4px 8px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #fff;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0"><i class="fas fa-list me-2 text-primary"></i> All Leads (<?= count($leads) ?>)</h3>
            <p class="text-muted small mb-0">Admin control – View, Transfer, Manage all leads</p>
        </div>
        <div class="d-flex gap-2">
            <a href="sales_lead_add.php" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-plus me-1"></i> Add Lead
            </a>
            <a href="sales_lead_upload.php" class="btn btn-success rounded-pill px-4">
                <i class="fas fa-file-upload me-1"></i> Bulk Upload
            </a>
            <a href="admin_sales_crm.php" class="btn btn-outline-primary rounded-pill px-4">
                <i class="fas fa-chart-line me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= $message ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, phone, email..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach(['new','contacted','interested','not_interested','converted','closed'] as $st): ?>
                        <option value="<?= $st ?>" <?= $status_filter==$st?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">City</label>
                <input type="text" name="city" class="form-control" placeholder="City..." value="<?= htmlspecialchars($city_filter) ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Assigned To</label>
                <select name="assigned" class="form-select">
                    <option value="">All Sales</option>
                    <option value="unassigned" <?= $assigned_filter=='unassigned'?'selected':'' ?>>⚠️ Unassigned</option>
                    <?php foreach ($sales_users as $su): ?>
                        <option value="<?= $su['id'] ?>" <?= $assigned_filter==$su['id']?'selected':'' ?>>
                            <?= htmlspecialchars($su['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Follow-up</label>
                <select name="followup" class="form-select">
                    <option value="">All</option>
                    <option value="today" <?= $followup_filter=='today'?'selected':'' ?>>Today</option>
                    <option value="overdue" <?= $followup_filter=='overdue'?'selected':'' ?>>Overdue</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>

    <!-- Bulk Transfer Bar -->
    <form method="POST" id="bulkForm">
        <div class="bulk-bar" id="bulkBar">
            <div style="font-weight:800;">
                <i class="fas fa-check-square me-1"></i> <span id="selectedCount">0</span> leads selected
            </div>
            <div>
                <label class="small fw-bold me-1">Transfer to:</label>
                <select name="new_assigned" required>
                    <option value="">-- Select Sales User --</option>
                    <option value="0">🚫 Unassign</option>
                    <?php foreach ($sales_users as $su): ?>
                        <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <input type="text" name="reason" placeholder="Reason (optional)">
            </div>
            <button type="submit" name="bulk_transfer" value="1" class="btn btn-light btn-sm rounded-pill px-4 fw-bold" 
                    onclick="return confirm('Transfer all selected leads?');">
                <i class="fas fa-exchange-alt me-1"></i> Transfer Selected
            </button>
            <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="clearSelection()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Leads Table -->
        <div class="table-responsive">
            <table class="leads-table">
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="selectAll" onclick="toggleAll(this)" style="width:1.2rem;height:1.2rem;cursor:pointer;"></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned To</th>
                        <th>Quick Transfer</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="10" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2" style="opacity:0.3;"></i>
                            <div>No leads found</div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($leads as $l): ?>
                        <tr>
                            <td><input type="checkbox" name="lead_ids[]" value="<?= $l['id'] ?>" class="lead-cb" onchange="updateBulkBar()" style="width:1.2rem;height:1.2rem;cursor:pointer;"></td>
                            <td><strong>#<?= $l['id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($l['name']) ?></strong>
                                <?php if (!empty($l['email'])): ?>
                                    <div style="font-size:0.7rem;color:#64748b;"><?= htmlspecialchars($l['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($l['phone']) ?></td>
                            <td><?= htmlspecialchars($l['city'] ?? '—') ?></td>
                            <td><span class="status-pill st-<?= $l['status'] ?>"><?= ucfirst(str_replace('_',' ',$l['status'])) ?></span></td>
                            <td><span class="priority-pill pr-<?= $l['priority'] ?>"><?= ucfirst($l['priority']) ?></span></td>
                            <td>
                                <?php if (!empty($l['assigned_name'])): ?>
                                    <span class="assignee-badge"><i class="fas fa-user"></i> <?= htmlspecialchars($l['assigned_name']) ?></span>
                                <?php else: ?>
                                    <span class="assignee-badge unassigned"><i class="fas fa-exclamation-triangle"></i> Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:flex; gap:4px; align-items:center;">
                                    <input type="hidden" name="single_transfer" value="1">
                                    <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
                                    <input type="hidden" name="reason" value="Quick transfer from list">
                                    <select name="new_assigned" class="transfer-select" onchange="this.form.submit()">
                                        <option value="">Transfer...</option>
                                        <option value="0">🚫 Unassign</option>
                                        <?php foreach ($sales_users as $su): ?>
                                            <?php if ($su['id'] != $l['assigned_to']): ?>
                                                <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['name']) ?></option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <a href="sales_lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<script>
function toggleAll(master) {
    document.querySelectorAll('.lead-cb').forEach(cb => cb.checked = master.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const selected = document.querySelectorAll('.lead-cb:checked').length;
    document.getElementById('selectedCount').textContent = selected;
    document.getElementById('bulkBar').classList.toggle('active', selected > 0);
    document.getElementById('selectAll').checked = 
        (selected > 0 && selected === document.querySelectorAll('.lead-cb').length);
}

function clearSelection() {
    document.querySelectorAll('.lead-cb').forEach(cb => cb.checked = false);
    document.getElementById('selectAll').checked = false;
    updateBulkBar();
}
</script>

<?php include 'footer.php'; ?>
