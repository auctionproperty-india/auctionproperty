<?php
// 🔥 TIMEZONE FIX
date_default_timezone_set('Asia/Kolkata');

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

// ============================================================
// 🔥 NEW: 1. FETCH LIVE USERS (Active in last 15 mins)
// ============================================================
$live_users_sql = "
    SELECT 
        l.user_id, 
        u.name, 
        u.email, 
        MAX(l.created_at) as last_seen, 
        l.ip_address,
        pk.name as paid_package
    FROM user_activity_log l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN (
        SELECT s.user_id, p.name, ROW_NUMBER() OVER(PARTITION BY s.user_id ORDER BY s.id DESC) as rn
        FROM subscriptions s 
        JOIN packages p ON s.package_id = p.id 
        WHERE s.status = 'active' AND s.end_date >= CURRENT_DATE AND p.price > 0
    ) pk ON pk.user_id = u.id AND pk.rn = 1
    WHERE l.created_at >= NOW() - INTERVAL '15 minutes'
    GROUP BY l.user_id, u.id, u.name, u.email, l.ip_address, pk.name
    ORDER BY last_seen DESC
";
$live_users_stmt = $pdo->query($live_users_sql);
$live_users = $live_users_stmt->fetchAll();

// ============================================================
// 🔥 NEW: 2. FETCH PAID USERS LOGIN STATS & TIME SPENT
// ============================================================
$paid_stats_sql = "
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        COUNT(l.id) as login_count,
        MIN(l.created_at) as first_login_today,
        MAX(l.created_at) as last_activity_today,
        pk.name as paid_package
    FROM user_activity_log l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN (
        SELECT s.user_id, p.name, ROW_NUMBER() OVER(PARTITION BY s.user_id ORDER BY s.id DESC) as rn
        FROM subscriptions s 
        JOIN packages p ON s.package_id = p.id 
        WHERE s.status = 'active' AND s.end_date >= CURRENT_DATE AND p.price > 0
    ) pk ON pk.user_id = u.id AND pk.rn = 1
    WHERE l.activity_type = 'login' 
    AND DATE(l.created_at) = CURRENT_DATE
    GROUP BY u.id, u.name, u.email, pk.name
    HAVING COUNT(l.id) > 0
    ORDER BY login_count DESC, last_activity_today DESC
";
$paid_stats_stmt = $pdo->query($paid_stats_sql);
$paid_stats = $paid_stats_stmt->fetchAll();

// ============================================================
// 🔥 EXISTING: 3. FETCH ACTIVITY LOGS
// ============================================================
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

<!-- 🔥 SEARCHABLE DROPDOWN (Select2) CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .nav-tabs .nav-link { font-weight: 600; color: #475569; border: none; padding: 12px 20px; }
    .nav-tabs .nav-link.active { background: transparent; border-bottom: 3px solid #2563eb; color: #2563eb; }
    .live-dot { width: 10px; height: 10px; background-color: #10b981; border-radius: 50%; display: inline-block; animation: pulse 1.5s infinite; }
    @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
    
    .select2-container--bootstrap-5 .select2-selection--single { height: 38px !important; border-radius: 8px; border-color: #cbd5e1; }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { line-height: 36px !important; }
</style>

<div class="card-premium">
    <h4 class="mb-4"><i class="fas fa-shield-alt me-2 text-primary"></i>Security & Activity Dashboard</h4>

    <!-- TABS -->
    <ul class="nav nav-tabs mb-4" id="activityTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="live-tab" data-bs-toggle="tab" data-bs-target="#live" type="button" role="tab">
                <i class="fas fa-circle text-success me-1" style="font-size: 0.6rem;"></i> Live Users <span class="badge bg-success rounded-pill ms-1"><?= count($live_users) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="paid-tab" data-bs-toggle="tab" data-bs-target="#paid" type="button" role="tab">
                <i class="fas fa-crown text-warning me-1"></i> Paid Users Stats
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                <i class="fas fa-history me-1"></i> Full Activity Logs
            </button>
        </li>
    </ul>

    <div class="tab-content" id="activityTabsContent">
        
        <!-- TAB 1: LIVE USERS -->
        <div class="tab-pane fade show active" id="live" role="tabpanel">
            <div class="alert alert-info border-0 shadow-sm mb-3">
                <i class="fas fa-info-circle me-2"></i> ये यूज़र पिछले 15 मिनट में एक्टिव रहे हैं। अगर कोई संदिग्ध यूज़र लगातार लाइव दिख रहा है, तो उसका IP चेक करें।
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Status</th>
                            <th>User</th>
                            <th>Package</th>
                            <th>Last Seen</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($live_users) > 0): foreach($live_users as $lu): 
                            // 🔥 TIMEZONE FIX: Added . ' UTC'
                            $last_seen_ist = date('h:i A', strtotime($lu['last_seen'] . ' UTC'));
                        ?>
                            <tr>
                                <td><span class="live-dot"></span> Live</td>
                                <td>
                                    <strong><?= htmlspecialchars($lu['name']) ?></strong><br>
                                    <small><?= $lu['email'] ?></small>
                                </td>
                                <td>
                                    <?php if($lu['paid_package']): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-crown"></i> <?= htmlspecialchars($lu['paid_package']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Free User</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $last_seen_ist ?></td>
                                <td><code><?= htmlspecialchars($lu['ip_address']) ?></code></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No users are currently online.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: PAID USERS LOGIN STATS -->
        <div class="tab-pane fade" id="paid" role="tabpanel">
            <div class="alert alert-warning border-0 shadow-sm mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i> यहाँ सिर्फ <strong>पेड यूज़र्स</strong> की लॉगिन काउंट और आज का एक्टिव टाइम दिख रहा है।
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Paid User</th>
                            <th>Package Name</th>
                            <th>Today's Logins</th>
                            <th>First Login Today</th>
                            <th>Last Activity</th>
                            <th>Est. Time Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($paid_stats) > 0): foreach($paid_stats as $ps): 
                            // 🔥 TIMEZONE FIX: Added . ' UTC' to both
                            $first = strtotime($ps['first_login_today'] . ' UTC');
                            $last = strtotime($ps['last_activity_today'] . ' UTC');
                            
                            $diff_mins = round(abs($last - $first) / 60);
                            $time_spent = ($diff_mins > 60) ? floor($diff_mins/60)." hrs ".($diff_mins%60)." mins" : $diff_mins." mins";
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($ps['name']) ?></strong><br>
                                    <small><?= $ps['email'] ?></small>
                                </td>
                                <td>
                                    <?php if($ps['paid_package']): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($ps['paid_package']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Expired/Free</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-primary fs-6"><?= $ps['login_count'] ?> times</span></td>
                                <td><?= date('h:i A', $first) ?></td>
                                <td><?= date('h:i A', $last) ?></td>
                                <td><strong class="text-danger"><?= $time_spent ?></strong></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No login activity by paid users today.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: EXISTING FULL LOGS -->
        <div class="tab-pane fade" id="logs" role="tabpanel">
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
                    <!-- SEARCHABLE DROPDOWN -->
                    <select name="filter_user" id="userSearchSelect" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= ($filter_user==$u['id'])?'selected':'' ?>>
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                            </option>
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
                    <thead class="table-dark">
                        <tr>
                            <th>User</th>
                            <th>Activity</th>
                            <th>Details</th>
                            <th>IP</th>
                            <th>Time (IST)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($logs)>0): foreach($logs as $log): 
                        $type_label = [
                            'login' => '🔑 Login',
                            'spin' => '🔄 Spin',
                            'property_view' => '🏠 Property View',
                            'copy_data' => '📋 Copy Data'
                        ][$log['activity_type']] ?? $log['activity_type'];
                        
                        // 🔥 TIMEZONE FIX: Added . ' UTC'
                        $log_time_ist = date('d M Y, h:i A', strtotime($log['created_at'] . ' UTC'));
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($log['user_name']) ?><br><small><?= $log['user_email'] ?></small></td>
                            <td><?= $type_label ?></td>
                            <td><?= nl2br(htmlspecialchars($log['details'] ?? '')) ?></td>
                            <td><code><?= $log['ip_address'] ?></code></td>
                            <td><?= $log_time_ist ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center">No logs found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($total_pages > 1): ?>
                <nav><ul class="pagination justify-content-center mt-3">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <li class="page-item <?= $i==$page?'active':'' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&filter_type=<?= urlencode($filter_type) ?>&filter_user=<?= urlencode($filter_user) ?>#logs"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul></nav>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- SEARCHABLE DROPDOWN JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('#userSearchSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search User by Name or Email...',
            allowClear: true,
            width: '100%'
        });
    });
</script>

<?php include 'footer.php'; ?>
