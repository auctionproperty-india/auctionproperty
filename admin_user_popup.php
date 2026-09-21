<?php
// ============================================================
// 👤 Admin – User Details Popup Window
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("<div class='alert alert-danger m-5'>❌ Access Denied. Please login as admin.</div>");
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    die("<div class='alert alert-danger m-5'>❌ Invalid User ID.</div>");
}

// ---- Fetch User Info ----
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    die("<div class='alert alert-danger m-5'>❌ User not found.</div>");
}

// ---- Fetch Referrer Info ----
$referrer_name = 'N/A';
$referrer_email = '';
if (!empty($user['referred_by'])) {
    $ref_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $ref_stmt->execute([$user['referred_by']]);
    $ref = $ref_stmt->fetch();
    if ($ref) {
        $referrer_name = $ref['name'];
        $referrer_email = $ref['email'];
    }
}

// ---- Fetch Active Subscription ----
$sub_stmt = $pdo->prepare("
    SELECT s.*, p.name as pkg_name, p.direct_income_percent, p.is_team_turnover_eligible 
    FROM subscriptions s 
    JOIN packages p ON s.package_id = p.id 
    WHERE s.user_id = ? AND s.status = 'active' AND s.end_date >= CURRENT_DATE 
    ORDER BY s.id DESC LIMIT 1
");
$sub_stmt->execute([$user_id]);
$active_sub = $sub_stmt->fetch();

// ---- Fetch Earnings Summary ----
$earn_stmt = $pdo->prepare("SELECT income_type, COALESCE(SUM(amount), 0) as total FROM user_earnings WHERE user_id = ? GROUP BY income_type");
$earn_stmt->execute([$user_id]);
$earnings = ['direct' => 0, 'team_turnover' => 0];
while ($row = $earn_stmt->fetch()) {
    $earnings[$row['income_type']] = $row['total'];
}

// ---- Fetch Team Count ----
$team_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$team_stmt->execute([$user_id]);
$team_count = $team_stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - <?= htmlspecialchars($user['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f6fa; font-family: 'Inter', sans-serif; padding: 20px; }
        .popup-card { max-width: 700px; margin: 0 auto; border-radius: 16px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .popup-header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; border-radius: 16px 16px 0 0; padding: 20px 25px; }
        .popup-header h4 { margin: 0; font-weight: 700; }
        .popup-body { padding: 25px; }
        .info-row { display: flex; border-bottom: 1px solid #f1f5f9; padding: 10px 0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 40%; font-weight: 600; color: #64748b; font-size: 0.9rem; }
        .info-value { width: 60%; font-weight: 700; color: #0f172a; font-size: 0.9rem; }
        .badge-pkg { background: #2563eb; color: #fff; padding: 4px 12px; border-radius: 30px; font-size: 0.75rem; }
        .badge-free { background: #94a3b8; color: #fff; padding: 4px 12px; border-radius: 30px; font-size: 0.75rem; }
    </style>
</head>
<body>

<div class="card popup-card">
    <div class="popup-header d-flex justify-content-between align-items-center">
        <h4><i class="fas fa-user-circle me-2"></i> User Details</h4>
        <button onclick="window.close()" class="btn btn-sm btn-light rounded-pill px-3">
            <i class="fas fa-times me-1"></i> Close
        </button>
    </div>
    <div class="popup-body">
        
        <!-- User Info -->
        <div class="info-row">
            <div class="info-label">User ID</div>
            <div class="info-value">#<?= $user['id'] ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Name</div>
            <div class="info-value"><?= htmlspecialchars($user['name']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email</div>
            <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Phone</div>
            <div class="info-value"><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Status</div>
            <div class="info-value">
                <span class="badge <?= $user['status'] == 'active' ? 'bg-success' : 'bg-danger' ?>">
                    <?= ucfirst($user['status'] ?? 'inactive') ?>
                </span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Referrer (Upline)</div>
            <div class="info-value">
                <?php if ($referrer_name != 'N/A'): ?>
                    👤 <?= htmlspecialchars($referrer_name) ?>
                    <div style="font-size:0.75rem; color:#64748b; font-weight:400;"><?= htmlspecialchars($referrer_email) ?></div>
                <?php else: ?>
                    <span class="text-muted">Direct / No Referrer</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Coins</div>
            <div class="info-value">🪙 <?= number_format($user['coins'] ?? 0) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Wallet Balance</div>
            <div class="info-value">₹ <?= indianCurrencyFormat($user['wallet_balance'] ?? 0) ?></div>
        </div>

        <hr class="my-4">

        <!-- Package Info -->
        <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-box me-2"></i> Current Package</h6>
        <?php if ($active_sub): ?>
            <div class="info-row">
                <div class="info-label">Package Name</div>
                <div class="info-value"><span class="badge-pkg"><?= htmlspecialchars($active_sub['pkg_name']) ?></span></div>
            </div>
            <div class="info-row">
                <div class="info-label">Direct Income %</div>
                <div class="info-value"><?= htmlspecialchars($active_sub['direct_income_percent']) ?>%</div>
            </div>
            <div class="info-row">
                <div class="info-label">Team Turnover Eligible?</div>
                <div class="info-value">
                    <?= $active_sub['is_team_turnover_eligible'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Start Date</div>
                <div class="info-value"><?= date('d M Y', strtotime($active_sub['start_date'])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">End Date</div>
                <div class="info-value"><?= date('d M Y', strtotime($active_sub['end_date'])) ?></div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning py-2 mb-0"><i class="fas fa-info-circle me-1"></i> No active paid package. (Free User)</div>
        <?php endif; ?>

        <hr class="my-4">

        <!-- Earnings Summary -->
        <h6 class="fw-bold mb-3 text-success"><i class="fas fa-coins me-2"></i> Earnings Summary</h6>
        <div class="row g-3">
            <div class="col-4">
                <div class="p-2 border rounded text-center">
                    <div style="font-size:0.7rem; color:#64748b; font-weight:700;">DIRECT</div>
                    <div class="fw-bold text-success">₹ <?= indianCurrencyFormat($earnings['direct']) ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 border rounded text-center">
                    <div style="font-size:0.7rem; color:#64748b; font-weight:700;">TEAM</div>
                    <div class="fw-bold text-primary">₹ <?= indianCurrencyFormat($earnings['team_turnover']) ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 border rounded text-center">
                    <div style="font-size:0.7rem; color:#64748b; font-weight:700;">TEAM SIZE</div>
                    <div class="fw-bold text-dark"><?= $team_count ?> Users</div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button onclick="window.close()" class="btn btn-secondary rounded-pill px-4">Close Window</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
