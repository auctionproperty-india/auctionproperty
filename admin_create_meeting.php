<?php
// ============================================================
// 📹 Admin - Create Video Meeting (With Target Audience & Delete)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    header("Location: login.php");
    exit;
}

$host_id = $_SESSION['user_id'];
$success = '';

// 🔥 Delete Meeting Logic
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM video_meetings WHERE id = ?")->execute([$del_id]);
    header("Location: admin_create_meeting.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_meeting'])) {
    $title = trim($_POST['title']);
    $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $scheduled_at = $_POST['scheduled_at'];
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $target_audience = $_POST['target_audience'] ?? 'all'; // 🔥 NEW
    
    $host_ids_arr = $_POST['host_ids'] ?? [];
    $host_ids_str = implode(',', array_map('intval', $host_ids_arr));

    $meeting_code = 'PP-' . strtoupper(bin2hex(random_bytes(4)));
    
    if (!empty($title)) {
        if ($is_public) $user_id = null;

        $stmt = $pdo->prepare("INSERT INTO video_meetings (host_id, user_id, title, meeting_code, scheduled_at, is_public, host_ids, target_audience) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$host_id, $user_id, $title, $meeting_code, $scheduled_at, $is_public, $host_ids_str, $target_audience]);
        $success = "Meeting created successfully!";
    }
}

$users = $pdo->query("SELECT id, name, email FROM users WHERE role = 'user' ORDER BY name ASC")->fetchAll();
$hosts = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin', 'sales') ORDER BY name ASC")->fetchAll();

// Recent Meetings
$recent_meetings = $pdo->query("
    SELECT m.*, u.name as user_name 
    FROM video_meetings m 
    LEFT JOIN users u ON m.user_id = u.id 
    ORDER BY m.id DESC LIMIT 10
")->fetchAll();

include 'header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <h3 class="fw-bold mb-4"><i class="fas fa-video text-primary"></i> Create Video Meeting</h3>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-danger fw-bold">Meeting Deleted Successfully!</div>
    <?php endif; ?>
    <?php if($success): ?><div class="alert alert-info fw-bold"><?= $success ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="fw-bold small">Meeting Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>

                        <!-- 🔥 Target Audience -->
                        <div class="mb-3 p-3 bg-light rounded border">
                            <label class="fw-bold small text-primary mb-2 d-block">Target Audience</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="target_audience" id="aud_all" value="all" checked>
                                <label class="form-check-label" for="aud_all">All Users</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="target_audience" id="aud_ba" value="ba_only">
                                <label class="form-check-label" for="aud_ba">Only Active BA Users</label>
                            </div>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded border">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_public" id="is_public" value="1" onchange="toggleUserSelect()">
                                <label class="form-check-label fw-bold" for="is_public">Public Meeting (Direct Join)</label>
                            </div>
                        </div>

                        <div class="mb-3" id="userSelectDiv">
                            <label class="fw-bold small">Invite Specific User (Optional)</label>
                            <select name="user_id" class="form-select">
                                <option value="">Choose User...</option>
                                <?php foreach($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="fw-bold small">Assign Co-Hosts</label>
                            <select name="host_ids[]" class="form-select" multiple size="3">
                                <?php foreach($hosts as $h): ?>
                                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?> (<?= strtoupper($h['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="fw-bold small">Scheduled Date & Time</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control">
                        </div>
                        <button type="submit" name="create_meeting" class="btn btn-primary w-100 fw-bold"><i class="fas fa-plus"></i> Generate Link</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-header bg-white fw-bold pt-3 pb-3">Recent Meetings</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Link</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_meetings as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['title']) ?></td>
                                    <td>
                                        <?= $m['target_audience'] == 'ba_only' ? '<span class="badge bg-warning text-dark">BA Only</span>' : '<span class="badge bg-info">All Users</span>' ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($m['meeting_code']) ?></code></td>
                                    <td>
                                        <a href="video_room.php?code=<?= $m['meeting_code'] ?>" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-video"></i> Join</a>
                                        <a href="?delete_id=<?= $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this meeting?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleUserSelect() {
    const isPublic = document.getElementById('is_public').checked;
    document.getElementById('userSelectDiv').style.display = isPublic ? 'none' : 'block';
}
</script>
<?php include 'footer.php'; ?>
