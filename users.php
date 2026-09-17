<?php
// ============================================================
// 👥 User Management – Admin Panel (With Referral Filter)
// Compact Table + NULL Safe
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

// ---- Search & Filter ----
$search = trim($_GET['search'] ?? '');
$referral_filter = trim($_GET['referral_filter'] ?? 'all');

$search_condition = "";
$search_params = [];

if (!empty($search)) {
    $search_condition .= " AND (u.name ILIKE ? OR u.email ILIKE ? OR u.phone ILIKE ?)";
    $search_params[] = '%' . $search . '%';
    $search_params[] = '%' . $search . '%';
    $search_params[] = '%' . $search . '%';
}

if ($referral_filter == 'with_referrer') {
    $search_condition .= " AND u.referred_by IS NOT NULL";
} elseif ($referral_filter == 'without_referrer') {
    $search_condition .= " AND u.referred_by IS NULL";
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
    WHERE 1=1
    " . $search_condition . "
    ORDER BY u.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($search_params);
$users = $stmt->fetchAll();

include 'header.php';
?>

<style>
    /* ===== Compact User Table ===== */
    .user-table-wrap {
        background: #fff;
        border-radius: 16px;
        padding: 16px 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid #e8edf4;
    }
    .user-table {
        width: 100%;
        font-size: 0.82rem;
        margin-bottom: 0;
        white-space: nowrap;
        border-collapse: separate;
        border-spacing: 0;
    }
    .user-table th {
        background: #f1f5f9;
        font-weight: 700;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #475569;
        padding: 10px 8px;
        border-bottom: 2px solid #e2e8f0;
        text-align: left;
        vertical-align: middle;
    }
    .user-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #1e293b;
    }
    .user-table tbody tr:hover {
        background: #f8faff;
    }
    .user-table .actions {
        white-space: nowrap;
    }
    .user-table .actions .btn {
        padding: 3px 7px;
        font-size: 0.7rem;
        border-radius: 6px;
        margin-right: 2px;
    }

    .badge-status {
        padding: 3px 10px;
        border-radius: 30px;
        font-size: 0.68rem;
        font-weight: 700;
        display: inline-block;
    }
    .badge-status.active { background: #dcfce7; color: #166534; }
    .badge-status.inactive { background: #fee2e2; color: #991b1b; }
    .badge-status.blocked { background: #fef3c7; color: #92400e; }

    .badge-referrer {
        font-size: 0.7rem;
        background: #eef2ff;
        color: #1e3a8a;
        padding: 2px 8px;
        border-radius: 30px;
        display: inline-block;
        max-width: 100px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }

    .badge-role-admin { background: #dc2626; color: #fff; }
    .badge-role-user { background: #64748b; color: #fff; }
    .badge-package { background: #2563eb; color: #fff; padding: 3px 10px; border-radius: 30px; font-size: 0.68rem; font-weight: 700; display: inline-block; }
    .badge-package-free { background: #94a3b8; color: #fff; padding: 3px 10px; border-radius: 30px; font-size: 0.68rem; font-weight: 700; display: inline-block; }
    .badge-coins { background: #fbbf24; color: #0f172a; padding: 3px 10px; border-radius: 30px; font-size: 0.7rem; font-weight: 700; display: inline-block; }

    .date-stack { font-size: 0.75rem; line-height: 1.4; }
    .date-stack .lbl { color: #94a3b8; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }

    /* Search box */
    .search-box {
        background: #fff;
        padding: 14px 18px;
        border-radius: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid #e8edf4;
        margin-bottom: 16px;
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .search-box input[type="text"] {
        border-radius: 30px;
        padding: 8px 18px;
        border: 1px solid #e2e8f0;
        min-width: 220px;
        font-size: 0.85rem;
    }
    .search-box input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .search-box select {
        border-radius: 30px;
        padding: 8px 14px;
        border: 1px solid #e2e8f0;
        background: #fff;
        font-size: 0.85rem;
    }
    .search-box select:focus { outline: none; border-color: #2563eb; }

    @media (max-width: 768px) {
        .user-table { font-size: 0.75rem; }
        .user-table th { font-size: 0.62rem; padding: 8px 5px; }
        .user-table td { padding: 8px 5px; }
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="text-light mb-0"><i class="fas fa-users me-2"></i>User Management</h4>
        <span class="badge bg-primary" style="font-size: 0.85rem; padding: 8px 16px;">Total: <?= count($users) ?> users</span>
    </div>

    <!-- Search + Filter Bar -->
    <div class="search-box">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center w-100">
            <input type="text" name="search" placeholder="🔍 Search name, email, phone..." value="<?= htmlspecialchars($search ?? '') ?>">

            <select name="referral_filter">
                <option value="all" <?= ($referral_filter == 'all') ? 'selected' : '' ?>>All Users</option>
                <option value="with_referrer" <?= ($referral_filter == 'with_referrer') ? 'selected' : '' ?>>With Referrer</option>
                <option value="without_referrer" <?= ($referral_filter == 'without_referrer') ? 'selected' : '' ?>>⚠️ Without Referrer</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($search) || $referral_filter != 'all'): ?>
                <a href="users.php" class="btn btn-secondary btn-sm rounded-pill px-3"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type ?? 'info') ?> alert-dismissible fade show">
            <?= htmlspecialchars($message ?? '') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="user-table-wrap">
        <div class="table-responsive">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Coins</th>
                        <th>Referrer</th>
                        <th>Dates</th>
                        <th>Package</th>
                        <th>Status</th>
                        <th>Role</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-4">No users found.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($users as $user): ?>
                    <tr>
                        <!-- ID -->
                        <td><strong>#<?= htmlspecialchars($user['id'] ?? '') ?></strong></td>

                        <!-- Name + Email -->
                        <td>
                            <div style="font-weight: 700; color: #0f172a;">
                                <?= htmlspecialchars($user['name'] ?? 'Unknown') ?>
                            </div>
                            <div style="font-size: 0.72rem; color: #64748b;">
                                <?= htmlspecialchars($user['email'] ?? 'N/A') ?>
                            </div>
                        </td>

                        <!-- Phone -->
                        <td>
                            <div style="font-size: 0.78rem;">
                                <?= htmlspecialchars($user['phone'] ?? 'N/A') ?>
                            </div>
                        </td>

                        <!-- Coins -->
                        <td>
                            <span class="badge-coins">🪙 <?= number_format($user['user_coins'] ?? 0) ?></span>
                        </td>

                        <!-- Referrer -->
                        <td>
                            <?php if (!empty($user['referrer_name']) || !empty($user['referrer_email'])): ?>
                                <span class="badge-referrer" title="<?= htmlspecialchars($user['referrer_name'] ?? $user['referrer_email'] ?? '') ?>">
                                    👤 <?= htmlspecialchars($user['referrer_name'] ?? $user['referrer_email'] ?? '') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <!-- Reg Date + Act Date (Combined) -->
                        <td>
                            <div class="date-stack">
                                <div><span class="lbl">Reg:</span> <?= safeDateFormat($user['created_at'] ?? '') ?></div>
                                <div><span class="lbl">Act:</span> <?= safeDateFormat($user['activation_date'] ?? '') ?></div>
                            </div>
                        </td>

                        <!-- Package + Expiry (Combined) -->
                        <td>
                            <?php if (!empty($user['package_name'])): ?>
                                <div><span class="badge-package"><?= htmlspecialchars($user['package_name']) ?></span></div>
                                <div style="font-size: 0.7rem; color: #64748b; margin-top: 2px;">
                                    Exp: <?= safeDateFormat($user['sub_end'] ?? '') ?>
                                </div>
                            <?php else: ?>
                                <span class="badge-package-free">Free</span>
                            <?php endif; ?>
                        </td>

                        <!-- Status -->
                        <td>
                            <?php
                            $status = $user['status'] ?? 'inactive';
                            $status_class = 'inactive';
                            $status_label = 'Inactive';
                            if ($status == 'active') { $status_class = 'active'; $status_label = 'Active'; }
                            elseif ($status == 'blocked') { $status_class = 'blocked'; $status_label = 'Blocked'; }
                            ?>
                            <span class="badge-status <?= $status_class ?>"><?= $status_label ?></span>
                        </td>

                        <!-- Role -->
                        <td>
                            <?php if (!empty($user['is_super_admin'])): ?>
                                <span class="badge badge-role-admin">Admin</span>
                            <?php else: ?>
                                <span class="badge badge-role-user">User</span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="actions" style="text-align: right;">
                            <a href="admin_edit_user.php?id=<?= htmlspecialchars($user['id'] ?? '') ?>" class="btn btn-sm btn-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="admin_team.php?id=<?= htmlspecialchars($user['id'] ?? '') ?>" class="btn btn-sm btn-info" title="View Team">
                                <i class="fas fa-sitemap"></i>
                            </a>
                            <?php if (($user['id'] ?? 0) != $_SESSION['user_id']): ?>
                                <a href="?delete=<?= htmlspecialchars($user['id'] ?? '') ?>&search=<?= urlencode($search) ?>&referral_filter=<?= urlencode($referral_filter) ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this user?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href="?toggle_block=<?= htmlspecialchars($user['id'] ?? '') ?>&search=<?= urlencode($search) ?>&referral_filter=<?= urlencode($referral_filter) ?>"
                                   class="btn btn-sm btn-warning"
                                   title="<?= ($user['status'] == 'blocked') ? 'Unblock' : 'Block' ?>">
                                    <?= ($user['status'] == 'blocked') ? '<i class="fas fa-unlock"></i>' : '<i class="fas fa-lock"></i>' ?>
                                </a>
                                <a href="?toggle_admin=<?= htmlspecialchars($user['id'] ?? '') ?>&search=<?= urlencode($search) ?>&referral_filter=<?= urlencode($referral_filter) ?>"
                                   class="btn btn-sm btn-info"
                                   title="<?= (!empty($user['is_super_admin'])) ? 'Remove Admin' : 'Make Admin' ?>">
                                    <?= (!empty($user['is_super_admin'])) ? '<i class="fas fa-user-minus"></i>' : '<i class="fas fa-user-plus"></i>' ?>
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
