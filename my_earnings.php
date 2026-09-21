<?php
// ============================================================
// 💰 My Earnings – Income History (Direct + Team Turnover)
// Salary Slip Format
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- Fetch User Info ----
$user_stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch();

if (!$user_info) {
    die("User not found.");
}

// ---- Fetch Totals ----
$total_direct = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM user_earnings WHERE user_id = ? AND income_type = 'direct'");
$total_direct->execute([$user_id]);
$sum_direct = $total_direct->fetchColumn();

$total_team = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM user_earnings WHERE user_id = ? AND income_type = 'team_turnover'");
$total_team->execute([$user_id]);
$sum_team = $total_team->fetchColumn();

$grand_total = $sum_direct + $sum_team;

// ---- Fetch Detailed History ----
$history_stmt = $pdo->prepare("
    SELECT e.*, u.name as from_user_name, u.email as from_user_email 
    FROM user_earnings e 
    JOIN users u ON e.from_user_id = u.id 
    WHERE e.user_id = ? 
    ORDER BY e.created_at DESC
");
$history_stmt->execute([$user_id]);
$history = $history_stmt->fetchAll();

include 'header.php';
?>

<style>
    .slip-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        overflow: hidden;
        max-width: 900px;
        margin: 0 auto;
    }
    .slip-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 25px 30px;
        text-align: center;
    }
    .slip-header h2 {
        margin: 0;
        font-weight: 800;
        letter-spacing: 1px;
    }
    .slip-header p {
        margin: 5px 0 0;
        opacity: 0.8;
        font-size: 0.9rem;
    }
    .slip-body {
        padding: 25px 30px;
    }
    .user-info-box {
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 25px;
        border-left: 5px solid #2563eb;
    }
    .user-info-box table {
        width: 100%;
        font-size: 0.9rem;
    }
    .user-info-box td {
        padding: 4px 0;
    }
    .summary-box {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    .summary-item {
        flex: 1;
        min-width: 180px;
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
    }
    .summary-item .label {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .summary-item .value {
        font-size: 1.5rem;
        font-weight: 800;
        margin-top: 5px;
    }
    .summary-item.direct { border-color: #10b981; background: #f0fdf4; }
    .summary-item.direct .value { color: #059669; }
    .summary-item.team { border-color: #8b5cf6; background: #f5f3ff; }
    .summary-item.team .value { color: #7c3aed; }
    .summary-item.total { border-color: #2563eb; background: #eff6ff; }
    .summary-item.total .value { color: #1d4ed8; }

    .table-custom th {
        background: #1e293b;
        color: #fff;
        font-size: 0.75rem;
        text-transform: uppercase;
        padding: 12px 10px;
        letter-spacing: 0.5px;
    }
    .table-custom td {
        padding: 12px 10px;
        vertical-align: middle;
        font-size: 0.85rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .table-custom tr:hover { background: #f8fafc; }
    
    .badge-income {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
    }
    .badge-income.direct { background: #dcfce7; color: #166534; }
    .badge-income.team { background: #ede9fe; color: #5b21b6; }

    @media print {
        body { background: #fff; }
        .sidebar, .header-top, .no-print { display: none !important; }
        .slip-card { box-shadow: none; border: 1px solid #ccc; }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h3 class="fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> My Income Statement</h3>
        <button onclick="window.print()" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-print me-2"></i> Print / Download
        </button>
    </div>

    <div class="slip-card">
        <!-- Header -->
        <div class="slip-header">
            <h2>PRIME PROPERTY INDIA</h2>
            <p>Income Statement / Salary Slip</p>
        </div>

        <div class="slip-body">
            <!-- User Info -->
            <div class="user-info-box">
                <table>
                    <tr>
                        <td style="width: 50%;"><strong>Name:</strong> <?= htmlspecialchars($user_info['name']) ?></td>
                        <td style="width: 50%;"><strong>User ID:</strong> #<?= $user_id ?></td>
                    </tr>
                    <tr>
                        <td><strong>Email:</strong> <?= htmlspecialchars($user_info['email']) ?></td>
                        <td><strong>Generated on:</strong> <?= date('d M Y, h:i A') ?></td>
                    </tr>
                </table>
            </div>

            <!-- Summary -->
            <div class="summary-box">
                <div class="summary-item direct">
                    <div class="label">Direct Income</div>
                    <div class="value">₹ <?= indianCurrencyFormat($sum_direct) ?></div>
                </div>
                <div class="summary-item team">
                    <div class="label">Team Turnover Income</div>
                    <div class="value">₹ <?= indianCurrencyFormat($sum_team) ?></div>
                </div>
                <div class="summary-item total">
                    <div class="label">Total Earnings</div>
                    <div class="value">₹ <?= indianCurrencyFormat($grand_total) ?></div>
                </div>
            </div>

            <!-- Detailed Table -->
            <h5 class="fw-bold mb-3"><i class="fas fa-list me-2"></i> Income Breakdown</h5>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>From User</th>
                            <th>Description</th>
                            <th style="text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No income history found yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <?php if ($row['income_type'] == 'direct'): ?>
                                        <span class="badge-income direct">Direct</span>
                                    <?php else: ?>
                                        <span class="badge-income team">Team Turnover</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($row['from_user_name'] ?? 'Unknown') ?></strong>
                                    <div style="font-size:0.7rem; color:#64748b;"><?= htmlspecialchars($row['from_user_email'] ?? '') ?></div>
                                </td>
                                <td style="font-size:0.8rem; color:#475569;"><?= htmlspecialchars($row['description'] ?? '') ?></td>
                                <td style="text-align:right; font-weight:700; color:#059669;">
                                    + ₹ <?= indianCurrencyFormat($row['amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="text-center mt-4 text-muted" style="font-size:0.75rem;">
                This is a computer-generated statement and does not require a signature.
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
