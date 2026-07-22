<?php
// ============================================================
// 👥 User Management – Admin Panel (with safeDateFormat)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ---- Safe Date Formatter ----
if (!function_exists('safeDateFormat')) {
    function safeDateFormat($dateStr) {
        if (empty($dateStr) || strtotime($dateStr) === false) {
            return 'N/A';
        }
        return date('d M Y', strtotime($dateStr));
    }
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ---- Handle Actions ----
$message = '';
$message_type = '';

// Delete User
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $message = "User deleted successfully!";
        $message_type = "success";
    } else {
        $message = "You cannot delete your own account!";
        $message_type = "danger";
    }
}

// Block/Unblock
if (isset($_GET['toggle_block']) && is_numeric($_GET['toggle_block'])) {
    $id = (int)$_GET['toggle_block'];
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $new_status = ($user['status'] == 'blocked') ? 'active' : 'blocked';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        $message = "User " . ($new_status == 'blocked' ? 'blocked' : 'unblocked') . " successfully!";
        $message_type = "success";
    }
}

// Make/Remove Admin
if (isset($_GET['toggle_admin']) && is_numeric($_GET['toggle_admin'])) {
    $id = (int)$_GET['toggle_admin'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("SELECT is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if ($user) {
            $new_admin = ($user['is_super_admin'] == 1) ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET is_super_admin = ? WHERE id = ?");
            $stmt->execute([$new_admin, $id]);
            $message = "Admin status " . ($new_admin ? 'granted' : 'revoked') . " successfully!";
            $message_type = "success";
        }
    } else {
        $message = "You cannot change your own admin status!";
        $message_type = "danger";
    }
}

// ---- Update User via POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $id = (int)$_POST['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $registration_date = $_POST['registration_date'] ?: null;
    $activation_date = $_POST['activation_date'] ?: null;
    $package_id = $_POST['package_id'] ? (int)$_POST['package_id'] : null;
    $status = $_POST['status'];
    $new_password = trim($_POST['new_password']);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, 
                created_at = COALESCE(?, created_at),
                activation_date = COALESCE(?, activation_date),
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $phone, $registration_date, $activation_date, $status, $id]);

        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $id]);
        }

        if ($package_id) {
            $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$id]);
            $sub = $stmt->fetch();
            if ($sub) {
                $stmt = $pdo->prepare("UPDATE subscriptions SET package_id = ? WHERE id = ?");
                $stmt->execute([$package_id, $sub['id']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO subscriptions (user_id, package_id, amount, status, start_date, end_date, created_at)
                    VALUES (?, ?, 0, 'active', CURRENT_DATE, CURRENT_DATE + INTERVAL '30 days', NOW())
                ");
                $stmt->execute([$id, $package_id]);
            }
        }

        $pdo->commit();
        $message = "User updated successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error updating user: " . $e->getMessage();
        $message_type = "danger";
    }
}

// ---- Search ----
$search = trim($_GET['search'] ?? '');
$search_condition = "";
$search_params = [];
if (!empty($search)) {
    $search_condition = " WHERE u.name ILIKE ? OR u.email ILIKE ? OR u.phone ILIKE ?";
    $search_params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
}

// ---- Fetch users with referrer ----
$sql = "
    SELECT 
        u.*,
        p.name as package_name,
        s.status as sub_status,
        s.start_date as sub_start,
        s.end_date as sub_end,
        s.package_id as current_package_id,
        u.coins as user_coins,
        ref.name as referrer_name,
        ref.email as referrer_email
    FROM users u
    LEFT JOIN users ref ON u.referred_by = ref.id
    LEFT JOIN (
        SELECT DISTINCT ON (user_id) user_id, package_id, status, start_date, end_date
        FROM subscriptions
        WHERE status = 'active' OR status = 'paid'
        ORDER BY user_id, id DESC
    ) s ON u.id = s.user_id
    LEFT JOIN packages p ON s.package_id = p.id
    " . $search_condition . "
    ORDER BY u.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($search_params);
$users = $stmt->fetchAll();

// ---- Packages for dropdown ----
$packages = $pdo->query("SELECT id, name FROM packages ORDER BY id")->fetchAll();

include 'header.php';
?>

<style>
    .user-table th { background: #f1f5f9; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.4px; color: #475569; }
    .user-table td { vertical-align: middle; }
    .user-table .actions .btn { padding: 2px 8px; font-size: 0.75rem; }
    .modal-content { background: #fff; color: #0f172a; }
    .modal-header { border-bottom: 1px solid #e2e8f0; }
    .modal-footer { border-top: 1px solid #e2e8f0; }
    .badge-status { padding: 4px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }
    .badge-status.active { background: #dcfce7; color: #166534; }
    .badge-status.inactive { background: #fee2e2; color: #991b1b; }
    .badge-status.blocked { background: #fef3c7; color: #92400e; }
    .badge-referrer { font-size: 0.75rem; background: #eef2ff; color: #1e3a8a; padding: 2px 10px; border-radius: 30px; }
    .search-box { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; }
    .search-box input { border-radius: 30px; padding: 8px 20px; border: 1px solid #e2e8f0; min-width: 250px; }
    .search-box input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-light"><i class="fas fa-users me-2"></i>User Management</h4>
        <span class="badge bg-primary">Total: <?= count($users) ?> users</span>
    </div>

    <!-- Search Bar -->
    <div class="search-box">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <input type="text" name="search" placeholder="🔍 Search by name, email, or phone..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($search)): ?>
                <a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card-premium">
        <div class="table-responsive">
            <table class="table user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Coins</th>
                        <th>Referred By</th>
                        <th>Reg. Date</th>
                        <th>Act. Date</th>
                        <th>Package</th>
                        <th>Expiry</th>
                        <th>Status</th>
                        <th>Admin</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="13" class="text-center text-muted py-4">No users found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><strong><?= htmlspecialchars($user['name']) ?></strong></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                        <td><span class="badge bg-warning text-dark"><?= number_format($user['user_coins'] ?? 0) ?></span></td>
                        <td>
                            <?php if ($user['referrer_name'] || $user['referrer_email']): ?>
                                <span class="badge-referrer">
                                    <?= htmlspecialchars($user['referrer_name'] ?? $user['referrer_email']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= safeDateFormat($user['created_at']) ?></td>
                        <td><?= safeDateFormat($user['activation_date']) ?></td>
                        <td>
                            <?php if ($user['package_name']): ?>
                                <span class="badge bg-primary"><?= htmlspecialchars($user['package_name']) ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Free</span>
                            <?php endif; ?>
                        </td>
                        <td><?= safeDateFormat($user['sub_end']) ?></td>
                        <td>
                            <?php
                            $status_class = 'inactive';
                            $status_label = 'Inactive';
                            if ($user['status'] == 'active') { $status_class = 'active'; $status_label = 'Active'; }
                            elseif ($user['status'] == 'blocked') { $status_class = 'blocked'; $status_label = 'Blocked'; }
                            ?>
                            <span class="badge-status <?= $status_class ?>"><?= $status_label ?></span>
                        </td>
                        <td>
                            <?= $user['is_super_admin'] ? '<span class="badge bg-danger">Admin</span>' : '<span class="badge bg-secondary">User</span>' ?>
                        </td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editModal_<?= $user['id'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>

                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?= $user['id'] ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href="?toggle_block=<?= $user['id'] ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-warning">
                                    <?= $user['status'] == 'blocked' ? '<i class="fas fa-unlock"></i>' : '<i class="fas fa-lock"></i>' ?>
                                </a>
                                <a href="?toggle_admin=<?= $user['id'] ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-info">
                                    <?= $user['is_super_admin'] ? '<i class="fas fa-user-minus"></i>' : '<i class="fas fa-user-plus"></i>' ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- EDIT MODAL -->
                    <div class="modal fade" id="editModal_<?= $user['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit User: <?= htmlspecialchars($user['name']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Phone</label>
                                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-control">
                                                    <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                    <option value="blocked" <?= $user['status'] == 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                                    <option value="inactive" <?= $user['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Registration Date</label>
                                                <input type="date" name="registration_date" class="form-control" value="<?= $user['created_at'] ? date('Y-m-d', strtotime($user['created_at'])) : '' ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Activation Date</label>
                                                <input type="date" name="activation_date" class="form-control" value="<?= $user['activation_date'] ? date('Y-m-d', strtotime($user['activation_date'])) : '' ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Package</label>
                                                <select name="package_id" class="form-control">
                                                    <option value="">Free</option>
                                                    <?php foreach ($packages as $pkg): ?>
                                                        <option value="<?= $pkg['id'] ?>" <?= ($user['current_package_id'] == $pkg['id']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($pkg['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Package Expiry Date</label>
                                                <input type="text" class="form-control" value="<?= safeDateFormat($user['sub_end']) ?>" readonly>
                                                <small class="text-muted">Expiry is auto-calculated; update package to change</small>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">New Password (leave blank to keep current)</label>
                                                <input type="text" name="new_password" class="form-control" placeholder="Enter new password">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
