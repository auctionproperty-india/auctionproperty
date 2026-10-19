<?php
// ============================================================
// 📋 Sales CRM – All Leads (with filters + WhatsApp + Bulk Email)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['sales', 'admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin');

// ---- Flash Messages ----
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ---- Filters ----
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$city_filter = trim($_GET['city'] ?? '');
$assigned_filter = trim($_GET['assigned'] ?? '');
$followup_filter = trim($_GET['followup'] ?? '');

$where = ["1=1"];
$params = [];

if (!$is_admin) {
    $where[] = "l.assigned_to = ?";
    $params[] = $user_id;
} elseif (!empty($assigned_filter)) {
    $where[] = "l.assigned_to = ?";
    $params[] = (int)$assigned_filter;
}

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

if ($followup_filter == 'today') {
    $where[] = "l.follow_up_date = CURRENT_DATE AND l.status NOT IN ('converted', 'closed', 'not_interested')";
} elseif ($followup_filter == 'overdue') {
    $where[] = "l.follow_up_date < CURRENT_DATE AND l.status NOT IN ('converted', 'closed', 'not_interested')";
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

// Sales users for filter (admin only)
$sales_users = [];
if ($is_admin) {
    $sales_users = $pdo->query("SELECT id, name FROM users WHERE role = 'sales' ORDER BY name")->fetchAll();
}

include 'header.php';
?>

<style>
    .filter-bar {
        background: #fff;
        border-radius: 16px;
        padding: 16px 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
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
        padding: 12px 12px; letter-spacing: 0.5px;
        text-align: left; font-weight: 700;
    }
    .leads-table td {
        padding: 12px;
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
    /* 🔥 Naya Status Color */
    .st-call_not_picked { background: #ffedd5; color: #c2410c; } 
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

    .btn-action {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        border: none;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s;
    }
    .btn-view { background: #eff6ff; color: #1e40af; }
    .btn-view:hover { background: #2563eb; color: #fff; }
    .btn-wa { background: #dcfce7; color: #166534; }
    .btn-wa:hover { background: #25d366; color: #fff; }

    .followup-badge {
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 20px;
        font-weight: 700;
    }
    .fb-overdue { background: #fee2e2; color: #b91c1c; }
    .fb-today { background: #fef3c7; color: #92400e; }
    .fb-upcoming { background: #e0f2fe; color: #075985; }

    .bulk-bar {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        border-radius: 14px;
        padding: 14px 20px;
        margin-top: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        box-shadow: 0 8px 20px rgba(37,99,235,0.25);
    }
    .bulk-bar .btn-email {
        background: #fbbf24;
        color: #0f172a;
        border: none;
        font-weight: 700;
        padding: 10px 22px;
        border-radius: 10px;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .bulk-bar .btn-email:hover { background: #fcd34d; transform: translateY(-2px); }

    .lead-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb; }
    #selectAll { width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="fw-bold mb-0"><i class="fas fa-list me-2 text-primary"></i> All Leads (<?= count($leads) ?>)</h3>
        <div class="d-flex gap-2">
            <a href="sales_lead_add.php" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-plus me-1"></i> Add Lead
            </a>
            <a href="sales_lead_upload.php" class="btn btn-success rounded-pill px-4">
                <i class="fas fa-file-upload me-1"></i> Bulk Upload
            </a>
        </div>
    </div>

    <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($flash_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
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
                    <option value="">All Status</option>
                    <option value="new" <?= $status_filter=='new'?'selected':'' ?>>New</option>
                    <option value="contacted" <?= $status_filter=='contacted'?'selected':'' ?>>Contacted</option>
                    <!-- 🔥 Naya Status Filter -->
                    <option value="call_not_picked" <?= $status_filter=='call_not_picked'?'selected':'' ?>>Call Not Picked</option>
                    
                    <option value="interested" <?= $status_filter=='interested'?'selected':'' ?>>Interested</option>
                    <option value="not_interested" <?= $status_filter=='not_interested'?'selected':'' ?>>Not Interested</option>
                    <option value="converted" <?= $status_filter=='converted'?'selected':'' ?>>Converted</option>
                    <option value="closed" <?= $status_filter=='closed'?'selected':'' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">City</label>
                <input type="text" name="city" class="form-control" placeholder="City..." value="<?= htmlspecialchars($city_filter) ?>">
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">Follow-up</label>
                <select name="followup" class="form-select">
                    <option value="">All</option>
                    <option value="today" <?= $followup_filter=='today'?'selected':'' ?>>Today</option>
                    <option value="overdue" <?= $followup_filter=='overdue'?'selected':'' ?>>Overdue</option>
                </select>
            </div>
            <?php if ($is_admin): ?>
            <div class="col-md-2">
                <label class="small fw-bold">Assigned To</label>
                <select name="assigned" class="form-select">
                    <option value="">All Sales</option>
                    <?php foreach ($sales_users as $su): ?>
                        <option value="<?= $su['id'] ?>" <?= $assigned_filter==$su['id']?'selected':'' ?>>
                            <?= htmlspecialchars($su['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
            </div>
        </form>
    </div>

    <form method="POST" action="send_bulk_email.php" id="bulkEmailForm">
        <div class="table-responsive">
            <table class="leads-table">
                <thead>
                    <tr>
                        <th style="width:36px;"><input type="checkbox" id="selectAll" title="Select All"></th>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Property</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Follow-up</th>
                        <?php if ($is_admin): ?><th>Assigned</th><?php endif; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr><td colspan="<?= $is_admin?11:10 ?>" class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2" style="opacity:0.3;"></i>
                            <div>No leads found</div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($leads as $l): 
                        // Follow-up badge
                        $fb_class = ''; $fb_text = '—';
                        if (!empty($l['follow_up_date'])) {
                            $fd = strtotime($l['follow_up_date']);
                            $td = strtotime(date('Y-m-d'));
                            if ($fd < $td) { $fb_class = 'fb-overdue'; $fb_text = '⚠️ ' . date('d M', $fd); }
                            elseif ($fd == $td) { $fb_class = 'fb-today'; $fb_text = '🔔 Today'; }
                            else { $fb_class = 'fb-upcoming'; $fb_text = date('d M', $fd); }
                        }
                        
                        // 🔥 WhatsApp Web Link
                        $clean_phone = preg_replace('/[^0-9]/', '', $l['phone']);
                        $clean_phone = ltrim($clean_phone, '0');
                        if (strlen($clean_phone) == 10) {
                            $clean_phone = '91' . $clean_phone;
                        }
                        $wa_link = "https://web.whatsapp.com/send?phone=" . $clean_phone;
                    ?>
                        <tr>
                            <td>
                                <input type="checkbox" 
                                       name="lead_ids[]" 
                                       value="<?= $l['id'] ?>" 
                                       class="lead-checkbox"
                                       data-email="<?= htmlspecialchars($l['email'] ?? '') ?>">
                            </td>
                            <td><strong>#<?= $l['id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($l['name']) ?></strong>
                                <?php if (!empty($l['email'])): ?>
                                    <div style="font-size:0.7rem;color:#64748b;"><?= htmlspecialchars($l['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($l['phone']) ?></td>
                            <td><?= htmlspecialchars($l['city'] ?? '—') ?></td>
                            <td style="font-size:0.8rem;"><?= htmlspecialchars($l['property_type'] ?? '—') ?></td>
                            <td><span class="status-pill st-<?= $l['status'] ?>"><?= ucfirst(str_replace('_', ' ', $l['status'])) ?></span></td>
                            <td><span class="priority-pill pr-<?= $l['priority'] ?>"><?= ucfirst($l['priority']) ?></span></td>
                            <td><span class="followup-badge <?= $fb_class ?>"><?= $fb_text ?></span></td>
                            <?php if ($is_admin): ?>
                                <td style="font-size:0.78rem;"><?= htmlspecialchars($l['assigned_name'] ?? '—') ?></td>
                            <?php endif; ?>
                            <td style="white-space:nowrap;">
                                <a href="sales_lead_view.php?id=<?= $l['id'] ?>" class="btn-action btn-view" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <!-- 🔥 WhatsApp Web Link -->
                                <a href="<?= $wa_link ?>" target="_blank" class="btn-action btn-wa" title="WhatsApp par bhejein">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Bulk Email Bar -->
        <div class="bulk-bar">
            <div>
                <i class="fas fa-envelope-open-text me-2"></i>
                <strong id="bulkCountLabel">0</strong> customers selected
            </div>
            <button type="button" class="btn-email" data-bs-toggle="modal" data-bs-target="#emailModal" id="openEmailModal">
                <i class="fas fa-paper-plane me-1"></i> Send Email to Selected
            </button>
        </div>

        <!-- Email Modal -->
        <div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-envelope me-2"></i> Send Bulk Email</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" required placeholder="Email subject...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="8" required placeholder="Type your message here..."></textarea>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> Personalization: <code>{name}</code> use karein — customer ka naam auto-replace hoga.
                            </small>
                        </div>
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle"></i> 
                            <strong id="selectedCount">0</strong> customers ko email jayega. 
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="sendEmailBtn">
                            <i class="fas fa-paper-plane"></i> Send Now
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Select All functionality
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.lead-checkbox').forEach(cb => cb.checked = this.checked);
    updateCount();
});

document.querySelectorAll('.lead-checkbox').forEach(cb => {
    cb.addEventListener('change', updateCount);
});

function updateCount() {
    const count = document.querySelectorAll('.lead-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('bulkCountLabel').textContent = count;
    
    const total = document.querySelectorAll('.lead-checkbox').length;
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = (count === total && total > 0);
}

document.getElementById('openEmailModal').addEventListener('click', function(e) {
    const count = document.querySelectorAll('.lead-checkbox:checked').length;
    if (count === 0) {
        e.preventDefault();
        e.stopPropagation();
        alert('⚠️ Kam se kam ek customer select karein!');
    }
});

document.getElementById('sendEmailBtn').addEventListener('click', function(e) {
    const count = document.querySelectorAll('.lead-checkbox:checked').length;
    if (count === 0) {
        e.preventDefault();
        alert('⚠️ Kam se kam ek customer select karein!');
        return;
    }
    if (!confirm(count + ' customers ko email bhejein?')) {
        e.preventDefault();
    }
});
</script>

<?php include 'footer.php'; ?>
