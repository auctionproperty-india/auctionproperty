<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
if(!hasViewPermission('users', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ Permission denied.</div>");
}

include 'header.php';

$filter_type = $_GET['filter_type'] ?? '';
$filter_user = $_GET['filter_user'] ?? '';
$limit = 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
if (!empty($filter_type)) {
    $where[] = "activity_type = ?";
    $params[] = $filter_type;
}
if (!empty($filter_user)) {
    $where[] = "user_id = ?";
    $params[] = (int)$filter_user;
}
$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$count_sql = "SELECT COUNT(*) FROM user_activity_log $where_sql";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();
$total_pages = ceil($total / $limit);

$sql = "SELECT l.*, u.name as user_name, u.email as user_email 
        FROM user_activity_log l 
        JOIN users u ON l.user_id = u.id 
        $where_sql 
        ORDER BY l.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-history me-2"></i>User Activity Logs</h4>
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="filter_type" class="form-select">
                <option value="">All Activities</option>
                <option value="login" <?= $filter_type=='login'?'selected':'' ?>>Login</option>
                <option value="spin" <?= $filter_type=='spin'?'selected':'' ?>>Spin</option>
                <option value="property_view" <?= $filter_type=='property_view'?'selected':'' ?>>Property View</option>
                <option value="copy_data" <?= $filter_type=='copy_data'?'selected':'' ?>>Copy Data</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="filter_user" class="form-select">
                <option value="">All Users</option>
                <?php foreach($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= ($filter_user==$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['name']) ?> (<?= $u['email'] ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i> Filter</button>
        </div>
        <div class="col-md-2">
            <a href="admin_activity_logs.php" class="btn btn-secondary btn-sm w-100">Clear</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead><tr>
                <th>User</th>
                <th>Activity</th>
                <th>Details</th>
                <th>IP</th>
                <th>Time</th>
            </tr></thead>
            <tbody>
            <?php if(count($logs)>0): foreach($logs as $log): 
                $type_label = [
                    'login' => '🔑 Login',
                    'spin' => '🔄 Spin',
                    'property_view' => '🏠 Property View',
                    'copy_data' => '📋 Copy Data'
                ][$log['activity_type']] ?? $log['activity_type'];
            ?>
                <tr>
                    <td><?= htmlspecialchars($log['user_name']) ?><br><small><?= $log['user_email'] ?></small></td>
                    <td><?= $type_label ?></td>
                    <td><?= nl2br(htmlspecialchars($log['details'] ?? '')) ?></td>
                    <td><?= $log['ip_address'] ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center">No logs found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($total_pages > 1): ?>
        <nav><ul class="pagination justify-content-center">
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <li class="page-item <?= $i==$page?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&filter_type=<?= urlencode($filter_type) ?>&filter_user=<?= urlencode($filter_user) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
