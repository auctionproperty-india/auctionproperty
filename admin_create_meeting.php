<?php
// ============================================================
// 📹 Admin - Create Video Meeting (With Days, Watchers & Time Fix)
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
    $target_audience = $_POST['target_audience'] ?? 'all';
    
    // 🔥 New Fields
    $meeting_days_arr = $_POST['meeting_days'] ?? [];
    $meeting_days = implode(',', array_map('intval', $meeting_days_arr));
    $min_watchers = (int)($_POST['min_watchers'] ?? 10);
    $max_watchers = (int)($_POST['max_watchers'] ?? 100);
    
    $host_ids_arr = $_POST['host_ids'] ?? [];
    $host_ids_str = implode(',', array_map('intval', $host_ids_arr));

    $meeting_code = 'PP-' . strtoupper(bin2hex(random_bytes(4)));
    
    if (!empty($title)) {
        if ($is_public) $user_id = null;

        $stmt = $pdo->prepare("INSERT INTO video_meetings (host_id, user_id, title, meeting_code, scheduled_at, is_public, host_ids, target_audience, meeting_days, min_watchers, max_watchers) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$host_id, $user_id, $title, $meeting_code, $scheduled_at, $is_public, $host_ids_str, $target_audience, $meeting_days, $min_watchers, $max_watchers]);
        $success = "Meeting created successfully!";
    }
}

$users = $pdo->query("SELECT id, name, email FROM users WHERE role = 'user' ORDER BY name ASC")->fetchAll();
$hosts = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('admin', 'sales') ORDER BY name ASC")->fetchAll();
$recent_meetings = $pdo->query("SELECT m.*, u.name as user_name FROM video_meetings m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.id DESC LIMIT 10")->fetchAll();

include 'header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <h3 class="fw-bold mb-4"><i class="fas fa-video text-primary"></i> Create Video Meeting</h3>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="alert alert-danger fw-bold">Meeting Deleted Successfully!</div>
    <?php endif; ?>
    <?php if($success): ?><div class="alert alert-info fw-bold"><?= $success ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-6">
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

                        <!-- 🔥 Weekly Days Setup -->
                        <div class="mb-3 p-3 bg-light rounded border">
                            <label class="fw-bold small text-primary mb-2 d-block">Repeat on Days (Optional)</label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="meeting_days[]" value="0" id="d0"><label class="form-check-label" for="d0">Sun</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="meeting_days[]" value="1" id="d1"><label class="form-check-label" for="d1">Mon</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="meeting_days[]" value="2" id="d2"><label class="form-check-label" for="d2">Tue</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="meeting_days[]" value="3" id="d3"><label class="form-check-label" for="d3">Wed</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="meeting_days[]" value="4" id="d4"><label class="form-check-label" for="d4">Thu</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="meeting_days[]" value="5" id="d5"><label class="form-check-label" for="d5">Fri</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="meeting_days[]" value="6" id="d6"><label class="form-check-label" for="d6">Sat</label></div>
                            </div>
                        </div>

                        <!-- 🔥 Min/Max Watchers -->
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="fw-bold small">Min Fake Watchers</label>
                                <input type="number" name="min_watchers" class="form-control" value="10" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="fw-bold small">Max Fake Watchers</label>
                                <input type="number" name="max_watchers" class="form-control" value="100" required>
                            </div>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded border">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_public" id="is_public" value="1" onchange="toggleUserSelect()">
                                <label class="form-check-label fw-bold" for="is_public">Public Meeting (Direct Join)</label>
                            </div>
                        </div>

                        <div class="mb-3" id="userSelectDiv" style="display:none;">
                            <label class="fw-bold small">Invite Specific User</label>
                            <select name="user_id" class="form-select">
                                <option value="">Choose User...</option>
                                <?php foreach($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
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
                            <label class="fw-bold small">Scheduled Date & Time (IST)</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control">
                        </div>
                        <button type="submit" name="create_meeting" class="btn btn-primary w-100 fw-bold"><i class="fas fa-plus"></i> Generate Link</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-header bg-white fw-bold pt-3 pb-3">Recent Meetings</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_meetings as $m): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($m['title']) ?><br>
                                        <small class="text-muted">Watchers: <?= $m['min_watchers'] ?>-<?= $m['max_watchers'] ?></small>
                                    </td>
                                    <td><?= $m['target_audience'] == 'ba_only' ? '<span class="badge bg-warning text-dark">BA Only</span>' : '<span class="badge bg-info">All</span>' ?></td>
                                    <td>
                                        <?php 
                                        if($m['scheduled_at']) {
                                            $dt = new DateTime($m['scheduled_at'], new DateTimeZone('UTC'));
                                            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                            echo $dt->format('d M, h:i A');
                                        } else { echo 'N/A'; }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="video_room.php?code=<?= $m['meeting_code'] ?>" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-video"></i></a>
                                        <a href="?delete_id=<?= $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
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
    document.getElementById('userSelectDiv').style.display = document.getElementById('is_public').checked ? 'none' : 'block';
}
</script>
<?php include 'footer.php'; ?>
