<?php
// ============================================================
// 👥 User Management – Admin Panel (Multi-Select Package Filter)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

if (!function_exists('safeDateFormat')) {
    function safeDateFormat($dateStr) {
        if (empty($dateStr) || strtotime($dateStr) === false) {
            return 'N/A';
        }
        return date('d M Y', strtotime($dateStr));
    }
}

function cleanDisplayValue($value, $fallback = 'N/A') {
    if (empty($value)) return $fallback;
    $value = trim((string)$value);
    if (
        strpos($value, 'Deprecated') !== false ||
        strpos($value, 'htmlspecialchars') !== false ||
        strpos($value, 'Fatal error') !== false ||
        strpos($value, 'Warning') !== false ||
        strpos($value, '<br') !== false ||
        strpos($value, '<b>') !== false ||
        strpos($value, 'admin_edit_user') !== false ||
        (strpos($value, '.php') !== false && strpos($value, 'line') !== false)
    ) {
        return $fallback;
    }
    return $value;
}

function getUserRoleLabel($user) {
    $role = strtolower($user['role'] ?? 'user');
    $isSuper = !empty($user['is_super_admin']);

    if ($role === 'admin' || $role === 'sub_admin') {
        if ($isSuper && $role === 'admin') {
            return ['label' => 'Super Admin', 'class' => 'badge-role-super'];
        }
        return ['label' => 'Sub Admin', 'class' => 'badge-role-subadmin'];
    }
    if ($role === 'sales') {
        return ['label' => 'Sales', 'class' => 'badge-role-sales'];
    }
    return ['label' => 'User', 'class' => 'badge-role-user'];
}

$message = '';
$message_type = '';

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

if (isset($_GET['toggle_free_income']) && is_numeric($_GET['toggle_free_income'])) {
    $id = (int)$_GET['toggle_free_income'];
    $stmt = $pdo->prepare("SELECT free_user_income_enabled FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
        $new_status = ($user['free_user_income_enabled']) ? 0 : 1; 
        $stmt = $pdo->prepare("UPDATE users SET free_user_income_enabled = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        $message = "Free User Income " . ($new_status ? 'Enabled' : 'Disabled') . " successfully!";
        $message_type = "success";
    }
}

// ---- Filters ----
$search = trim($_GET['search'] ?? '');
$referral_filter = trim($_GET['referral_filter'] ?? 'all');

// 🔥 Multi-select package filter
$package_filters = $_GET['package_filter'] ?? [];
if (!is_array($package_filters)) {
    $package_filters = empty($package_filters) ? [] : [$package_filters];
}
// Sanitize values
$clean_pkg_filters = [];
foreach ($package_filters as $pf) {
    $pf = trim((string)$pf);
    if ($pf === '') continue;
    if (is_numeric($pf)) {
        $clean_pkg_filters[] = (string)(int)$pf;
    } elseif (in_array($pf, ['free', 'none'])) {
        $clean_pkg_filters[] = $pf;
    }
}
$package_filters = array_values(array_unique($clean_pkg_filters));

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

// 🔥 Multi-package filter (OR logic)
if (!empty($package_filters)) {
    $pkg_or_conditions = [];
    $free_selected = in_array('free', $package_filters);
    $none_selected = in_array('none', $package_filters);
    $pkg_ids_selected = array_filter($package_filters, 'is_numeric');

    if ($free_selected) {
        $pkg_or_conditions[] = "s.user_id IS NULL"; // no active subscription => free user
    }
    if ($none_selected) {
        $pkg_or_conditions[] = "s.user_id IS NULL";
    }
    if (!empty($pkg_ids_selected)) {
        $placeholders = implode(',', array_fill(0, count($pkg_ids_selected), '?'));
        $pkg_or_conditions[] = "s.package_id IN ($placeholders)";
        foreach ($pkg_ids_selected as $pid) {
            $search_params[] = (int)$pid;
        }
    }
    if (!empty($pkg_or_conditions)) {
        $search_condition .= " AND (" . implode(' OR ', $pkg_or_conditions) . ")";
    }
}

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

// Fetch all packages for filter dropdown
$all_packages = $pdo->query("SELECT id, name FROM packages ORDER BY name ASC")->fetchAll();

// 🔥 Build preserve QS for toggle/delete links
$preserve_params = [];
if (!empty($search)) $preserve_params[] = 'search=' . urlencode($search);
if ($referral_filter != 'all') $preserve_params[] = 'referral_filter=' . urlencode($referral_filter);
foreach ($package_filters as $pf) {
    $preserve_params[] = 'package_filter[]=' . urlencode($pf);
}
$preserve_qs = !empty($preserve_params) ? implode('&', $preserve_params) : '';
$preserve_qs_amp = $preserve_qs ? '&' . $preserve_qs : '';

include 'header.php';
?>

<style>
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
    .user-table tbody tr:hover { background: #f8faff; }
    .user-table .actions { white-space: nowrap; }
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
    .badge-role-super { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; padding: 3px 10px; border-radius: 30px; font-size: 0.68rem; font-weight: 700; display: inline-block; white-space: nowrap; }
    .badge-role-subadmin { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; padding: 3px 10px; border-radius: 30px; font-size: 0.68rem; font-weight: 700; display: inline-block; white-space: nowrap; }
    .badge-role-sales { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; padding: 3px 10px; border-radius: 30px; font-size: 0.68rem; font-weight: 700; display: inline-block; white-space: nowrap; }
    .badge-role-user { background: #64748b; color: #fff; padding: 3px 10px; border-radius: 30px; font-size: 0.68rem; font-weight: 700; display: inline-block; white-space: nowrap; }
    .badge-package { background: #2563eb; color: #fff; padding: 3px 10px; border-radius: 30px; font-size: 0.68rem; font-weight: 700; display: inline-block; }
    .badge-package-free { background: #94a3b8; color: #fff; padding: 3px 10px; border-radius: 30px; font-size: 0.68rem; font-weight: 700; display: inline-block; }
    .badge-coins { background: #fbbf24; color: #0f172a; padding: 3px 10px; border-radius: 30px; font-size: 0.7rem; font-weight: 700; display: inline-block; }
    .date-stack { font-size: 0.75rem; line-height: 1.4; }
    .date-stack .lbl { color: #94a3b8; font-size: 0.65rem; font-weight: 600; text-transform: uppercase; }
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
        min-width: 200px;
        font-size: 0.85rem;
    }
    .search-box input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .search-box select {
        border-radius: 30px;
        padding: 8px 14px;
        border: 1px solid #e2e8f0;
        background: #fff;
        font-size: 0.85rem;
        min-width: 160px;
    }
    .search-box select:focus { outline: none; border-color: #2563eb; }
    .filter-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-right: 4px;
    }
    .toggle-badge {
        cursor: pointer;
        padding: 4px 12px;
        border-radius: 30px;
        font-size: 0.65rem;
        font-weight: 700;
        display: inline-block;
        transition: all 0.2s;
    }
    .toggle-badge.on { background: #10b981; color: #fff; box-shadow: 0 2px 5px rgba(16,185,129,0.4); }
    .toggle-badge.off { background: #e2e8f0; color: #475569; border: 1px solid #cbd5e1; }

    /* 🔥 Multi-Select Dropdown */
    .multi-select-wrap { position: relative; display: inline-block; }
    .multi-select-btn {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 30px;
        padding: 8px 16px;
        font-size: 0.85rem;
        font-weight: 500;
        color: #1e293b;
        cursor: pointer;
        min-width: 200px;
        text-align: left;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        transition: all 0.2s;
    }
    .multi-select-btn:hover { border-color: #2563eb; }
    .multi-select-btn .ms-label { flex: 1; }
    .multi-select-btn .ms-badge {
        background: #2563eb;
        color: #fff;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        min-width: 20px;
        text-align: center;
    }
    .multi-select-btn i.fa-chevron-down {
        color: #94a3b8;
        font-size: 0.7rem;
        transition: transform 0.2s;
    }
    .multi-select-wrap.open .multi-select-btn i.fa-chevron-down { transform: rotate(180deg); }

    .multi-select-panel {
        display: none;
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        min-width: 260px;
        max-height: 340px;
        overflow-y: auto;
        z-index: 500;
        padding: 6px 0;
    }
    .multi-select-wrap.open .multi-select-panel { display: block; }

    .multi-select-panel .ms-actions {
        display: flex;
        justify-content: space-between;
        padding: 6px 14px 8px;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 4px;
    }
    .multi-select-panel .ms-actions button {
        background: none;
        border: none;
        color: #2563eb;
        font-size: 0.75rem;
        font-weight: 700;
        cursor: pointer;
        padding: 2px 4px;
    }
    .multi-select-panel .ms-actions button:hover { text-decoration: underline; }

    .multi-select-panel label {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 14px;
        cursor: pointer;
        font-size: 0.85rem;
        color: #334155;
        margin: 0;
        transition: background 0.15s;
    }
    .multi-select-panel label:hover { background: #f1f5f9; }
    .multi-select-panel input[type="checkbox"] {
        width: 1.1rem;
        height: 1.1rem;
        accent-color: #2563eb;
        cursor: pointer;
        margin: 0;
    }
    .multi-select-panel .ms-sep {
        height: 1px;
        background: #e2e8f0;
        margin: 4px 0;
    }

    @media (max-width: 768px) {
        .user-table { font-size: 0.75rem; }
        .user-table th { font-size: 0.62rem; padding: 8px 5px; }
        .user-table td { padding: 8px 5px; }
        .search-box input[type="text"],
        .search-box select { min-width: 130px; font-size: 0.78rem; }
        .multi-select-btn { min-width: 160px; font-size: 0.78rem; }
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="text-light mb-0"><i class="fas fa-users me-2"></i>User Management</h4>
        <span class="badge bg-primary" style="font-size: 0.85rem; padding: 8px 16px;">Total: <?= count($users) ?> users</span>
    </div>

    <!-- 🔥 SEARCH + FILTERS -->
    <div class="search-box">
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center w-100" id="filterForm">
            <input type="text" name="search" placeholder="🔍 Search name, email, phone..." value="<?= htmlspecialchars($search ?? '') ?>">

            <span class="filter-label"><i class="fas fa-user-tag"></i> Referrer:</span>
            <select name="referral_filter">
                <option value="all" <?= ($referral_filter == 'all') ? 'selected' : '' ?>>All Users</option>
                <option value="with_referrer" <?= ($referral_filter == 'with_referrer') ? 'selected' : '' ?>>With Referrer</option>
                <option value="without_referrer" <?= ($referral_filter == 'without_referrer') ? 'selected' : '' ?>>⚠️ Without Referrer</option>
            </select>

            <span class="filter-label"><i class="fas fa-box"></i> Packages:</span>

            <!-- 🔥 Multi-Select Package Dropdown -->
            <div class="multi-select-wrap" id="pkgWrap">
                <button type="button" class="multi-select-btn" onclick="togglePkgDropdown(event)">
                    <span class="ms-label" id="pkgBtnLabel">
                        <?php 
                        if (empty($package_filters)) {
                            echo 'All Packages';
                        } elseif (count($package_filters) == 1) {
                            $single = $package_filters[0];
                            if ($single == 'free') echo '🆓 Free Users Only';
                            elseif ($single == 'none') echo '❌ No Subscription';
                            else {
                                foreach ($all_packages as $pkg) {
                                    if ($pkg['id'] == $single) { echo '📦 ' . htmlspecialchars($pkg['name']); break; }
                                }
                            }
                        } else {
                            echo 'Packages Selected';
                        }
                        ?>
                    </span>
                    <span class="ms-badge" id="pkgBtnBadge" style="<?= empty($package_filters) ? 'display:none;' : '' ?>"><?= count($package_filters) ?></span>
                    <i class="fas fa-chevron-down"></i>
                </button>

                <div class="multi-select-panel" id="pkgPanel">
                    <div class="ms-actions">
                        <button type="button" onclick="pkgSelectAll()">✓ Select All</button>
                        <button type="button" onclick="pkgClearAll()">✗ Clear All</button>
                    </div>

                    <label>
                        <input type="checkbox" name="package_filter[]" value="free"
                            <?= in_array('free', $package_filters) ? 'checked' : '' ?>
                            onchange="updatePkgLabel()">
                        🆓 Free Users Only
                    </label>
                    <label>
                        <input type="checkbox" name="package_filter[]" value="none"
                            <?= in_array('none', $package_filters) ? 'checked' : '' ?>
                            onchange="updatePkgLabel()">
                        ❌ No Subscription
                    </label>

                    <div class="ms-sep"></div>

                    <?php foreach ($all_packages as $pkg): ?>
                        <label>
                            <input type="checkbox" name="package_filter[]" value="<?= (int)$pkg['id'] ?>"
                                <?= in_array((string)$pkg['id'], $package_filters, true) ? 'checked' : '' ?>
                                onchange="updatePkgLabel()">
                            📦 <?= htmlspecialchars($pkg['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3"><i class="fas fa-search"></i> Apply</button>
            <?php if (!empty($search) || $referral_filter != 'all' || !empty($package_filters)): ?>
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
                        <th>Free Income</th>
                        <th>Status</th> 
                        <th>Role</th>
                        <th style="text-align: right;">Actions</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="11" class="text-center text-muted py-4">No users found.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><strong>#<?= htmlspecialchars($user['id'] ?? '') ?></strong></td>

                        <td>
                            <div style="font-weight: 700; color: #0f172a;">
                                <?= htmlspecialchars(cleanDisplayValue($user['name'] ?? '', 'Unknown')) ?>
                            </div>
                            <div style="font-size: 0.72rem; color: #64748b;">
                                <?= htmlspecialchars(cleanDisplayValue($user['email'] ?? '', 'N/A')) ?>
                            </div>
                        </td>

                        <td>
                            <div style="font-size: 0.78rem;">
                                <?= htmlspecialchars(cleanDisplayValue($user['phone'] ?? '', 'N/A')) ?>
                            </div>
                        </td>

                        <td>
                            <span class="badge-coins">🪙 <?= number_format($user['user_coins'] ?? 0) ?></span>
                        </td>

                        <td>
                            <?php if (!empty($user['referrer_name']) || !empty($user['referrer_email'])): ?>
                                <span class="badge-referrer" title="<?= htmlspecialchars(cleanDisplayValue($user['referrer_name'] ?? '', $user['referrer_email'] ?? '')) ?>">
                                    👤 <?= htmlspecialchars(cleanDisplayValue($user['referrer_name'] ?? '', $user['referrer_email'] ?? '')) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="date-stack">
                                <div><span class="lbl">Reg:</span> <?= safeDateFormat($user['created_at'] ?? '') ?></div>
                                <div><span class="lbl">Act:</span> <?= safeDateFormat($user['activation_date'] ?? '') ?></div>
                            </div>
                        </td>

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

                        <td>
                            <?php if (empty($user['package_name'])): ?>
                                <a href="?toggle_free_income=<?= $user['id'] ?><?= $preserve_qs_amp ?>" class="text-decoration-none">
                                    <?php if (!empty($user['free_user_income_enabled'])): ?>
                                        <span class="toggle-badge on">ON</span>
                                    <?php else: ?>
                                        <span class="toggle-badge off">OFF</span>
                                    <?php endif; ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <a href="?toggle_block=<?= $user['id'] ?><?= $preserve_qs_amp ?>" 
                               class="btn btn-sm <?= ($user['status'] == 'blocked') ? 'btn-danger' : 'btn-success' ?>"
                               title="<?= ($user['status'] == 'blocked') ? 'Click to Unblock' : 'Click to Block' ?>">
                                <?= ($user['status'] == 'blocked') ? 'Blocked' : 'Active' ?>
                            </a>
                        </td>

                        <td>
                            <?php $roleInfo = getUserRoleLabel($user); ?>
                            <span class="<?= $roleInfo['class'] ?>"><?= $roleInfo['label'] ?></span>
                        </td>

                        <td class="actions" style="text-align: right;">
                            <a href="admin_edit_user.php?id=<?= htmlspecialchars($user['id'] ?? '') ?>" class="btn btn-sm btn-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>

                            <a href="admin_give_package.php?user_id=<?= htmlspecialchars($user['id'] ?? '') ?>" class="btn btn-sm btn-success" title="Give Free Package">
                                <i class="fas fa-gift"></i>
                            </a>

                            <a href="javascript:void(0)" onclick="loginAsUser(<?= (int)$user['id'] ?>)" class="btn btn-sm btn-warning" title="Login as User">
                                <i class="fas fa-user-secret"></i>
                            </a>

                            <a href="admin_team.php?id=<?= htmlspecialchars($user['id'] ?? '') ?>" class="btn btn-sm btn-info" title="View Team">
                                <i class="fas fa-sitemap"></i>
                            </a>
                            
                            <?php if (($user['id'] ?? 0) != $_SESSION['user_id']): ?>
                                <a href="?delete=<?= htmlspecialchars($user['id'] ?? '') ?><?= $preserve_qs_amp ?>"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Delete this user?')" title="Delete">
                                    <i class="fas fa-trash"></i>
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

<script>
    // 🔥 Multi-select dropdown toggle
    function togglePkgDropdown(e) {
        if (e) e.stopPropagation();
        document.getElementById('pkgWrap').classList.toggle('open');
    }

    // Close on outside click
    document.addEventListener('click', function(e) {
        var wrap = document.getElementById('pkgWrap');
        if (wrap && !wrap.contains(e.target)) {
            wrap.classList.remove('open');
        }
    });

    // Update label when checkboxes change
    function updatePkgLabel() {
        var checkboxes = document.querySelectorAll('#pkgPanel input[name="package_filter[]"]:checked');
        var count = checkboxes.length;
        var labelEl = document.getElementById('pkgBtnLabel');
        var badgeEl = document.getElementById('pkgBtnBadge');

        if (count === 0) {
            labelEl.textContent = 'All Packages';
            badgeEl.style.display = 'none';
        } else if (count === 1) {
            var parentLabel = checkboxes[0].parentElement;
            var text = parentLabel.textContent.trim();
            labelEl.textContent = text;
            badgeEl.style.display = 'none';
        } else {
            labelEl.textContent = 'Packages Selected';
            badgeEl.textContent = count;
            badgeEl.style.display = 'inline-block';
        }
    }

    function pkgSelectAll() {
        document.querySelectorAll('#pkgPanel input[name="package_filter[]"]').forEach(function(cb) {
            cb.checked = true;
        });
        updatePkgLabel();
    }

    function pkgClearAll() {
        document.querySelectorAll('#pkgPanel input[name="package_filter[]"]').forEach(function(cb) {
            cb.checked = false;
        });
        updatePkgLabel();
    }

    function loginAsUser(userId) {
        if (!userId || userId <= 0) return;
        window.open(
            'admin_login_as_user.php?user_id=' + userId,
            'ImpersonateUser_' + userId,
            'width=1300,height=850,scrollbars=yes,resizable=yes'
        );
    }
</script>

<?php include 'footer.php'; ?>
