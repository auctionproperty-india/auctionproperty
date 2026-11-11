<?php
// ============================================================
// 📹 User - My Video Meetings (With Time & Day Filter)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🔥 भारतीय समय (IST) सेट करें
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$user_stmt = $pdo->prepare("SELECT ba_status FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_ba = $user_stmt->fetchColumn() ?: 'none';

$sql = "SELECT m.*, u.name as host_name 
        FROM video_meetings m 
        JOIN users u ON m.host_id = u.id 
        WHERE (m.is_public = TRUE OR m.user_id = ?) 
        AND (m.target_audience = 'all' OR (m.target_audience = 'ba_only' AND ? = 'active'))
        ORDER BY m.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id, $user_ba]);
$meetings = $stmt->fetchAll();

// आज का दिन (0 = Sunday, 1 = Monday...)
$today_day = date('w'); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Video Meetings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <?php include 'header.php'; ?>
    
    <div class="container mt-4 mb-5">
        <h3 class="fw-bold mb-4"><i class="fas fa-video text-primary"></i> My Video Meetings</h3>

        <div class="row">
            <?php 
            $visible_meetings = 0;
            if(count($meetings) > 0): 
                foreach($meetings as $m):
                    // 🔥 चेक करें कि मीटिंग आज दिखनी चाहिए या नहीं
                    $show_meeting = true;
                    if (!empty($m['meeting_days'])) {
                        $allowed_days = explode(',', $m['meeting_days']);
                        if (!in_array($today_day, $allowed_days)) {
                            $show_meeting = false; // अगर आज का दिन लिस्ट में नहीं है, तो मत दिखाओ
                        }
                    }

                    if ($show_meeting):
                        $visible_meetings++;
                        // 🔥 Time को UTC से IST में बदलें
                        $display_time = 'Any Time';
                        if($m['scheduled_at']) {
                            $dt = new DateTime($m['scheduled_at'], new DateTimeZone('UTC'));
                            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                            $display_time = $dt->format('d M Y, h:i A');
                        }
            ?>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm" style="border-radius:16px;">
                        <div class="card-body p-4 text-center">
                            <div class="mb-3 position-relative">
                                <i class="fas fa-video fa-3x <?= $m['is_public'] ? 'text-success' : 'text-primary' ?> opacity-50"></i>
                                <?php if($m['target_audience'] == 'ba_only'): ?>
                                    <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-1">Exclusive BA</span>
                                <?php endif; ?>
                            </div>
                            <h5 class="fw-bold"><?= htmlspecialchars($m['title']) ?></h5>
                            <p class="text-muted small mb-1">Host: <?= htmlspecialchars($m['host_name']) ?></p>
                            
                            <p class="text-muted small mb-3"><i class="far fa-clock"></i> <?= $display_time ?></p>
                            
                            <a href="video_room.php?code=<?= htmlspecialchars($m['meeting_code']) ?>" class="btn btn-primary w-100 fw-bold">
                                <i class="fas fa-sign-in-alt"></i> Join Meeting
                            </a>
                        </div>
                    </div>
                </div>
            <?php 
                    endif;
                endforeach; 
            endif;
            
            if ($visible_meetings == 0): ?>
                <div class="col-12 text-center py-5">
                    <h5 class="text-muted">No meetings scheduled for today.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
