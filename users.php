<?php
// ============================================================
// 👥 User Management – Admin Panel (With View Team Button)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ---- Helper: Safe Date Format ----
if (!function_exists('safeDateFormat')) {
    function safeDateFormat($dateStr) {
        if (empty($dateStr) || strtotime($dateStr) === false) {
            return 'N/A';
        }
        return date('d M Y', strtotime($dateStr));
    }
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

include 'header.php';
?>

<style>
    .user-table th { background: #f1f5f9; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.4px; color: #475569; }
    .user-table td { vertical-align: middle; }
    .user-table .actions .btn { padding: 2px 8px; font-size: 0.75rem; }
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
                            <!-- Edit Button -->
                            <a href="admin_edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i>
                            </a>

                            <!-- ✅ View Team Button -->
                            <a href="admin_team.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-info" title="View Team">
                                <i class="fas fa-sitemap"></i>
                            </a>

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
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
