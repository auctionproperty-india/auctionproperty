<?php
// ============================================================
// 📹 Admin - Create Video Meeting
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    header("Location: login.php");
    exit;
}

$host_id = $_SESSION['user_id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_meeting'])) {
    $title = trim($_POST['title']);
    $user_id = (int)$_POST['user_id'];
    $scheduled_at = $_POST['scheduled_at'];
    
    // यूनिक मीटिंग कोड जनरेट करें (जैसे: PP-A1B2C3D4)
    $meeting_code = 'PP-' . strtoupper(bin2hex(random_bytes(4)));
    
    if (!empty($title) && $user_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO video_meetings (host_id, user_id, title, meeting_code, scheduled_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$host_id, $user_id, $title, $meeting_code, $scheduled_at]);
        $success = "Meeting created successfully!";
    } else {
        $success = "<span class='text-danger'>Please fill all details.</span>";
    }
}

// सभी यूजर्स की लिस्ट निकालें
$users = $pdo->query("SELECT id, name, email FROM users WHERE role = 'user' ORDER BY name ASC")->fetchAll();

// हाल ही में बनाई गई मीटिंग्स
$recent_meetings = $pdo->query("
    SELECT m.*, u.name as user_name 
    FROM video_meetings m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY m.id DESC LIMIT 10
")->fetchAll();

include 'header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <h3 class="fw-bold mb-4"><i class="fas fa-video text-primary"></i> Create Video Meeting</h3>

    <?php if($success): ?>
        <div class="alert alert-info fw-bold"><?= $success ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="fw-bold small">Meeting Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Property Discussion" required>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold small">Select User</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Choose User...</option>
                                <?php foreach($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold small">Scheduled Date & Time (Optional)</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control">
                        </div>
                        <button type="submit" name="create_meeting" class="btn btn-primary w-100 fw-bold"><i class="fas fa-plus"></i> Generate Meeting Link</button>
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
                                    <th>User</th>
                                    <th>Link Code</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recent_meetings as $m): ?>
                                <tr>
                                    <td><?= htmlspecialchars($m['title']) ?></td>
                                    <td><?= htmlspecialchars($m['user_name']) ?></td>
                                    <td><code><?= htmlspecialchars($m['meeting_code']) ?></code></td>
                                    <td>
                                        <a href="video_room.php?code=<?= $m['meeting_code'] ?>" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-video"></i> Join</a>
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

<?php include 'footer.php'; ?>
