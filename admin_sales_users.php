<?php
// ============================================================
// 👥 Admin – Sales Users Management
// Create, Edit, Deactivate Sales Users + Performance Stats
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}
if (!hasEditPermission('users', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ No permission.</div>");
}

$message = '';
$message_type = '';

// ---- Create New Sales User ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_sales'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $target = (float)($_POST['sales_target'] ?? 0);

    if (empty($name) || empty($email) || empty($password)) {
        $message = "❌ Name, Email, Password required!";
        $message_type = "danger";
    } elseif (strlen($password) < 6) {
        $message = "❌ Password minimum 6 characters!";
        $message_type = "danger";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $message = "❌ Email already exists!";
            $message_type = "danger";
        } else {
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $ref_code = strtoupper(substr(md5(uniqid()), 0, 8));
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, password, role, status, referral_code, sales_target, activation_date) 
                    VALUES (?, ?, ?, ?, 'sales', 'active', ?, ?, CURRENT_DATE)
                ");
                $stmt->execute([$name, $email, $phone, $hashed, $ref_code, $target]);
                $message = "✅ Sales user '<b>" . htmlspecialchars($name) . "</b>' created successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "❌ Error: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }
}

// ---- Update Sales User ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sales'])) {
    $uid = (int)$_POST['user_id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $target = (float)($_POST['sales_target'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    $new_password = trim($_POST['new_password'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, sales_target=?, status=? WHERE id=? AND role='sales'");
        $stmt->execute([$name, $email, $phone, $target, $status, $uid]);

        if (!empty($new_password) && strlen($new_password) >= 6) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $uid]);
        }

        $message = "✅ Sales user updated!";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ---- Toggle Active ----
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    try {
        $pdo->prepare("UPDATE users SET sales_active = NOT COALESCE(sales_active, TRUE) WHERE id=? AND role='sales'")->execute([$uid]);
        $message = "✅ Status changed!";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "❌ " . $e->getMessage();
        $message_type = "danger";
    }
}

// ---- Fetch Sales Users with Stats ----
$sales_users = $pdo->query("
    SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at, 
           u.sales_target, COALESCE(u.sales_active, TRUE) as is_active,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id) as total_leads,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id AND status = 'converted') as converted_leads,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id AND status NOT IN ('converted','closed','not_interested') AND follow_up_date = CURRENT_DATE) as today_followups,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id AND status NOT IN ('converted','closed','not_interested') AND follow_up_date < CURRENT_DATE) as overdue_followups
    FROM users u 
    WHERE u.role = 'sales' 
    ORDER BY u.id DESC
")->fetchAll();

include 'header.php';
?>

<style>
    .sales-user-card {
        background: #fff;
        border-radius: 18px;
        padding: 20px;
        box-shadow: 0 6px 25px rgba(0,0,0,0.06);
        border: 1px solid #e2e8f0;
        transition: all 0.25s;
        height: 100%;
    }
    .sales-user-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.10);
        border-color: #93b5e8;
    }
    .su-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
        padding-bottom: 14px;
        border-bottom: 1px solid #f1f5f9;
    }
    .su-avatar {
        width: 48px; height: 48px;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px; font-weight: 800; color: #fff;
    }
    .su-name { font-weight: 800; color: #0f172a; font-size: 1rem; }
    .su-email { font-size: 0.75rem; color: #64748b; }

    .su-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 12px;
    }
    .su-stat {
        background: #f8fafc;
        border-radius: 10px;
        padding: 8px 12px;
        text-align: center;
    }
    .su-stat .lbl {
        font-size: 0.6rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .su-stat .val {
        font-size: 1rem;
        font-weight: 800;
        margin-top: 2px;
        color: #0f172a;
    }
    .su-stat.total .val { color: #2563eb; }
    .su-stat.conv .val { color: #059669; }
    .su-stat.today .val { color: #d97706; }
    .su-stat.over .val { color: #dc2626; }

    .badge-status {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
    }
    .bs-active { background: #dcfce7; color: #166534; }
    .bs-inactive { background: #fee2e2; color: #991b1b; }
    .bs-blocked { background: #fef3c7; color: #92400e; }

    .btn-xs {
        padding: 4px 10px;
        font-size: 0.72rem;
        border-radius: 8px;
        font-weight: 600;
    }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0"><i class="fas fa-user-tie me-2 text-primary"></i> Sales Users Management</h3>
            <p class="text-muted small mb-0">Create & Manage Sales Team</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4" onclick="document.getElementById('createForm').classList.toggle('d-none');">
            <i class="fas fa-plus me-1"></i> New Sales User
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= $message ?></div>
    <?php endif; ?>

    <!-- Create Form -->
    <div id="createForm" class="card border-0 shadow-sm rounded-4 p-4 mb-4 d-none">
        <h5 class="fw-bold mb-3"><i class="fas fa-user-plus text-success me-2"></i> Create New Sales User</h5>
        <form method="POST">
            <input type="hidden" name="create_sales" value="1">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="small fw-bold">Full Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Password *</label>
                    <input type="text" name="password" class="form-control" required minlength="6">
                </div>
                <div class="col-md-2">
                    <label class="small fw-bold">Target (₹)</label>
                    <input type="number" name="sales_target" class="form-control" value="0">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success rounded-pill px-4">
                    <i class="fas fa-save me-1"></i> Create Sales User
                </button>
                <button type="button" class="btn btn-secondary rounded-pill px-4 ms-2" onclick="document.getElementById('createForm').classList.add('d-none');">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Sales Users List -->
    <h5 class="fw-bold mb-3"><i class="fas fa-users me-2 text-primary"></i> All Sales Users (<?= count($sales_users) ?>)</h5>
    <div class="row g-3">
        <?php if (empty($sales_users)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center py-4">
                    <i class="fas fa-info-circle me-1"></i> No sales users yet. Click "New Sales User" to create one.
                </div>
            </div>
        <?php endif; ?>
        <?php foreach ($sales_users as $s): 
            $initials = strtoupper(substr($s['name'], 0, 2));
            $conversion_rate = $s['total_leads'] > 0 ? round(($s['converted_leads'] / $s['total_leads']) * 100) : 0;
        ?>
            <div class="col-lg-3 col-md-6">
                <div class="sales-user-card">
                    <div class="su-header">
                        <div class="su-avatar"><?= $initials ?></div>
                        <div style="flex:1; min-width:0;">
                            <div class="su-name"><?= htmlspecialchars($s['name']) ?></div>
                            <div class="su-email" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?= htmlspecialchars($s['email']) ?>
                            </div>
                        </div>
                        <?php if ($s['is_active'] && $s['status'] == 'active'): ?>
                            <span class="badge-status bs-active">Active</span>
                        <?php elseif ($s['status'] == 'blocked'): ?>
                            <span class="badge-status bs-blocked">Blocked</span>
                        <?php else: ?>
                            <span class="badge-status bs-inactive">Inactive</span>
                        <?php endif; ?>
                    </div>

                    <div class="su-stats">
                        <div class="su-stat total">
                            <div class="lbl">Total Leads</div>
                            <div class="val"><?= $s['total_leads'] ?></div>
                        </div>
                        <div class="su-stat conv">
                            <div class="lbl">Converted</div>
                            <div class="val"><?= $s['converted_leads'] ?> (<?= $conversion_rate ?>%)</div>
                        </div>
                        <div class="su-stat today">
                            <div class="lbl">Today F/U</div>
                            <div class="val"><?= $s['today_followups'] ?></div>
                        </div>
                        <div class="su-stat over">
                            <div class="lbl">Overdue</div>
                            <div class="val"><?= $s['overdue_followups'] ?></div>
                        </div>
                    </div>

                    <div class="d-flex gap-1 flex-wrap">
                        <a href="admin_sales_leads.php?assigned=<?= $s['id'] ?>" class="btn btn-outline-primary btn-xs">
                            <i class="fas fa-list"></i> Leads
                        </a>
                        <a href="admin_sales_crm.php?user=<?= $s['id'] ?>" class="btn btn-outline-info btn-xs">
                            <i class="fas fa-chart-line"></i> Stats
                        </a>
                        <button class="btn btn-outline-warning btn-xs" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['id'] ?>">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <a href="?toggle=<?= $s['id'] ?>" class="btn btn-xs <?= $s['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                           onclick="return confirm('<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?> this sales user?');">
                            <i class="fas fa-<?= $s['is_active'] ? 'pause' : 'play' ?>"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">Edit: <?= htmlspecialchars($s['name']) ?></h5>
                            <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="update_sales" value="1">
                            <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                            <div class="modal-body">
                                <div class="mb-2">
                                    <label class="small fw-bold">Name</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($s['name']) ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="small fw-bold">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($s['email']) ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="small fw-bold">Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($s['phone'] ?? '') ?>">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="small fw-bold">Target (₹)</label>
                                        <input type="number" name="sales_target" class="form-control" value="<?= $s['sales_target'] ?? 0 ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="small fw-bold">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?= $s['status']=='active'?'selected':'' ?>>Active</option>
                                            <option value="inactive" <?= $s['status']=='inactive'?'selected':'' ?>>Inactive</option>
                                            <option value="blocked" <?= $s['status']=='blocked'?'selected':'' ?>>Blocked</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="small fw-bold">New Password (leave blank to keep current)</label>
                                    <input type="text" name="new_password" class="form-control" placeholder="Min 6 characters">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
