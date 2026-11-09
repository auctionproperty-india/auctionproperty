<?php
// ============================================================
// 📹 User - My Video Meetings (Includes Public Meetings)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// 🔥 PUBLIC मीटिंग्स और अपनी मीटिंग्स दोनों निकालें
$stmt = $pdo->prepare("
    SELECT m.*, u.name as host_name 
    FROM video_meetings m 
    JOIN users u ON m.host_id = u.id 
    WHERE m.user_id = ? OR m.is_public = TRUE 
    ORDER BY m.id DESC
");
$stmt->execute([$user_id]);
$meetings = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <h3 class="fw-bold mb-4"><i class="fas fa-video text-primary"></i> My Video Meetings</h3>

    <div class="row">
        <?php if(count($meetings) > 0): ?>
            <?php foreach($meetings as $m): ?>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm" style="border-radius:16px;">
                        <div class="card-body p-4 text-center">
                            <div class="mb-3">
                                <i class="fas fa-video fa-3x <?= $m['is_public'] ? 'text-success' : 'text-primary' ?> opacity-50"></i>
                                <?php if($m['is_public']): ?>
                                    <span class="badge bg-success position-absolute top-0 end-0 m-3">Public</span>
                                <?php endif; ?>
                            </div>
                            <h5 class="fw-bold"><?= htmlspecialchars($m['title']) ?></h5>
                            <p class="text-muted small mb-1">Host: <?= htmlspecialchars($m['host_name']) ?></p>
                            <?php if($m['scheduled_at']): ?>
                                <?php 
                                // Timezone change to IST
                                $utc_date = new DateTime($m['scheduled_at'], new DateTimeZone('UTC'));
                                $utc_date->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                ?>
                                <p class="text-muted small mb-3"><i class="far fa-clock"></i> <?= $utc_date->format('d M Y, h:i A') ?></p>
                            <?php endif; ?>
                            
                            <a href="video_room.php?code=<?= htmlspecialchars($m['meeting_code']) ?>" class="btn btn-primary w-100 fw-bold">
                                <i class="fas fa-sign-in-alt"></i> Join Meeting
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <h5 class="text-muted">No meetings scheduled yet.</h5>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
