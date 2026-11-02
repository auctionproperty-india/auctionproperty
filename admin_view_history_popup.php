<?php
// ============================================================
// 🏠 Admin - View History Popup (With User Details)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// चेक करें कि एडमिन लॉगिन है या नहीं
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Access Denied. Please login as Admin.</h3>");
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$prop_id = isset($_GET['prop_id']) ? (int)$_GET['prop_id'] : 0;

if (!$user_id || !$prop_id) {
    die("<h3 style='text-align:center; margin-top:50px;'>Invalid Request.</h3>");
}

// 🔥 FETCH USER DETAILS (Name, Email, Phone)
$user_stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch();

$user_name = $user_info['name'] ?? 'Unknown User';
$user_email = $user_info['email'] ?? 'No Email';
$user_phone = $user_info['phone'] ?? 'No Phone';

// उस यूजर द्वारा उस प्रॉपर्टी को देखने की पूरी हिस्ट्री निकालें
$stmt = $pdo->prepare("
    SELECT created_at, ip_address
    FROM user_activity_log 
    WHERE user_id = ? AND activity_type = 'property_view' AND details LIKE ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id, '%Property ID: ' . $prop_id . '%']);
$history = $stmt->fetchAll();

// टाइमज़ोन सेट करें
date_default_timezone_set('Asia/Kolkata');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View History - Property #<?= $prop_id ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fc; font-family: 'Inter', sans-serif; padding: 20px; }
        .history-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; overflow: hidden; }
        .history-header { background: #1e293b; color: #fff; padding: 15px 20px; display: flex; justify-content: space-between; align-items: flex-start; }
        .user-details-box { background: rgba(255,255,255,0.1); border-radius: 8px; padding: 10px 15px; margin-top: 10px; border-left: 3px solid #3b82f6; }
        .timeline { list-style: none; padding: 20px; margin: 0; position: relative; }
        .timeline::before { content: ''; position: absolute; top: 20px; bottom: 20px; left: 32px; width: 2px; background: #e2e8f0; }
        .timeline-item { position: relative; padding-left: 40px; margin-bottom: 20px; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-icon { position: absolute; left: 0; top: 0; width: 26px; height: 26px; border-radius: 50%; background: #dbeafe; color: #1e40af; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; border: 2px solid #fff; box-shadow: 0 0 0 2px #e2e8f0; }
        .timeline-content { background: #f8fafc; padding: 10px 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .time-text { font-weight: 700; color: #0f172a; font-size: 1rem; margin: 0; }
        .date-text { color: #64748b; font-size: 0.85rem; margin: 2px 0 0 0; }
        .ip-text { font-size: 0.75rem; color: #94a3b8; margin-top: 5px; }
    </style>
</head>
<body>

    <div class="container" style="max-width: 550px;">
        <div class="history-card">
            <!-- हेडर (अब इसमें ईमेल और मोबाइल भी है) -->
            <div class="history-header">
                <div class="w-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i> View History</h5>
                        <button onclick="window.close()" class="btn btn-sm btn-danger rounded-circle" title="Close Window"><i class="fas fa-times"></i></button>
                    </div>
                    
                    <!-- User Info Box -->
                    <div class="user-details-box mt-3">
                        <div class="fw-bold text-white mb-1" style="font-size: 1.1rem;">
                            <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($user_name) ?> <span class="text-white-50" style="font-size:0.85rem;">(ID: <?= $user_id ?>)</span>
                        </div>
                        <div class="small text-light">
                            <div><i class="fas fa-envelope me-2"></i> <?= htmlspecialchars($user_email) ?></div>
                            <div><i class="fas fa-phone me-2"></i> <?= htmlspecialchars($user_phone) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark"><i class="fas fa-building text-primary me-1"></i> Property ID: #<?= $prop_id ?></span>
                <a href="admin_view_property_popup.php?id=<?= $prop_id ?>" target="_blank" class="btn btn-sm btn-outline-primary py-1" style="font-size: 0.8rem;">
                    <i class="fas fa-external-link-alt"></i> View Property
                </a>
            </div>

            <!-- टाइमलाइन हिस्ट्री -->
            <ul class="timeline">
                <?php if(count($history) > 0): foreach($history as $index => $row): 
                    // Date & Time फॉर्मेट करें (DB में UTC है, IST में बदलें)
                    $timestamp = strtotime($row['created_at'] . ' UTC');
                    $date_ist = date('d M Y', $timestamp);
                    $time_ist = date('h:i:s A', $timestamp);
                    
                    // पहली बार देखा (सबसे हाल का) उसे हाईलाइट करें
                    $is_latest = ($index === 0);
                ?>
                    <li class="timeline-item">
                        <div class="timeline-icon" style="<?= $is_latest ? 'background:#d1fae5; color:#065f46;' : '' ?>">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="timeline-content" style="<?= $is_latest ? 'border-color:#10b981; background:#f0fdf4;' : '' ?>">
                            <p class="time-text"><?= $time_ist ?> <?= $is_latest ? '<span class="badge bg-success ms-1" style="font-size:0.6rem;">Latest</span>' : '' ?></p>
                            <p class="date-text"><i class="fas fa-calendar-alt me-1"></i> <?= $date_ist ?></p>
                            <p class="ip-text"><i class="fas fa-network-wired me-1"></i> IP: <?= htmlspecialchars($row['ip_address']) ?></p>
                        </div>
                    </li>
                <?php endforeach; else: ?>
                    <li class="text-center text-muted py-4">No history found.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
