<?php
// ============================================================
// 💰 My Earnings – Income Statement (Weekly & Monthly Slip)
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

// ============================================================
// 🔥 DATE FILTER LOGIC
// ============================================================
$range = $_GET['range'] ?? 'this_week';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
$end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('sunday this week'));

if ($range == 'this_week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
    $period_label = "This Week (" . date('d M', strtotime($start_date)) . " - " . date('d M', strtotime($end_date)) . ")";
} elseif ($range == 'last_week') {
    $start_date = date('Y-m-d', strtotime('monday last week'));
    $end_date = date('Y-m-d', strtotime('sunday last week'));
    $period_label = "Last Week (" . date('d M', strtotime($start_date)) . " - " . date('d M', strtotime($end_date)) . ")";
} elseif ($range == 'this_month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
    $period_label = "This Month (" . date('M Y') . ")";
} elseif ($range == 'last_month') {
    $start_date = date('Y-m-01', strtotime('first day of last month'));
    $end_date = date('Y-m-t', strtotime('last day of last month'));
    $period_label = "Last Month (" . date('M Y', strtotime('first day of last month')) . ")";
} elseif ($range == 'all_time') {
    $start_date = '2000-01-01';
    $end_date = date('Y-m-d');
    $period_label = "All Time";
} elseif ($range == 'custom') {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $period_label = "Custom (" . date('d M Y', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date)) . ")";
}

// Add time to cover the whole day
$start_date_time = $start_date . ' 00:00:00';
$end_date_time = $end_date . ' 23:59:59';

// ============================================================
// 🔥 FETCH TOTALS (Filtered by Date)
// ============================================================
$total_direct = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM user_earnings WHERE user_id = ? AND income_type = 'direct' AND created_at >= ? AND created_at <= ?");
$total_direct->execute([$user_id, $start_date_time, $end_date_time]);
$sum_direct = $total_direct->fetchColumn();

$total_team = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM user_earnings WHERE user_id = ? AND income_type = 'team_turnover' AND created_at >= ? AND created_at <= ?");
$total_team->execute([$user_id, $start_date_time, $end_date_time]);
$sum_team = $total_team->fetchColumn();

$grand_total = $sum_direct + $sum_team;

// ============================================================
// 🔥 FETCH DETAILED HISTORY (Filtered by Date)
// ============================================================
$history_stmt = $pdo->prepare("
    SELECT e.*, u.name as from_user_name, u.email as from_user_email 
    FROM user_earnings e 
    JOIN users u ON e.from_user_id = u.id 
    WHERE e.user_id = ? AND e.created_at >= ? AND e.created_at <= ? 
    ORDER BY e.created_at DESC
");
$history_stmt->execute([$user_id, $start_date_time, $end_date_time]);
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
        max-width: 950px;
        margin: 0 auto;
    }
    .slip-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 25px 30px;
        text-align: center;
    }
    .slip-header h2 { margin: 0; font-weight: 800; letter-spacing: 1px; }
    .slip-header p { margin: 5px 0 0; opacity: 0.8; font-size: 0.9rem; }
    .slip-body { padding: 25px 30px; }
    
    .user-info-box {
        background: #f8fafc;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 25px;
        border-left: 5px solid #2563eb;
    }
    .user-info-box table { width: 100%; font-size: 0.9rem; }
    .user-info-box td { padding: 4px 0; }
    
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
    .summary-item .value { font-size: 1.5rem; font-weight: 800; margin-top: 5px; }
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

    /* 🔥 Print Styling */
    @media print {
        body { background: #fff !important; margin: 0; padding: 0; }
        .sidebar, .header-top, .navbar, footer, .no-print { display: none !important; }
        .slip-card { box-shadow: none !important; border: 1px solid #ccc !important; width: 100% !important; max-width: 100% !important; margin: 0 !important; }
        .container { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .summary-item { border: 1px solid #ccc !important; }
        .badge-income { border: 1px solid #ccc !important; }
    }
</style>

<div class="container mt-4 mb-5">
    <!-- Header & Filter -->
    <div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
        <h3 class="fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> My Income Statement</h3>
    </div>

    <!-- 🔥 Filter Form -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small">Select Period</label>
                    <select name="range" class="form-select" id="rangeSelect">
                        <option value="this_week" <?= $range == 'this_week' ? 'selected' : '' ?>>This Week</option>
                        <option value="last_week" <?= $range == 'last_week' ? 'selected' : '' ?>>Last Week (Payout)</option>
                        <option value="this_month" <?= $range == 'this_month' ? 'selected' : '' ?>>This Month</option>
                        <option value="last_month" <?= $range == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                        <option value="all_time" <?= $range == 'all_time' ? 'selected' : '' ?>>All Time</option>
                        <option value="custom" <?= $range == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="col-md-3 custom-date-field <?= $range == 'custom' ? '' : 'd-none' ?>">
                    <label class="form-label fw-bold small">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="col-md-3 custom-date-field <?= $range == 'custom' ? '' : 'd-none' ?>">
                    <label class="form-label fw-bold small">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                
                <div class="col-md-3 custom-date-field <?= $range == 'custom' ? '' : 'd-none' ?>">
                    <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                </div>

                <div class="col-md-3 ms-auto text-end">
                    <button type="button" onclick="window.print()" class="btn btn-outline-primary w-100">
                        <i class="fas fa-print me-2"></i> Print / Download Slip
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 🔥 Salary Slip -->
    <div class="slip-card">
        <div class="slip-header">
            <h2>PRIME PROPERTY INDIA</h2>
            <p>Income Statement / Salary Slip</p>
            <p style="font-size:0.8rem; margin-top:5px; background:rgba(255,255,255,0.2); display:inline-block; padding:2px 10px; border-radius:20px;">
                Period: <?= $period_label ?>
            </p>
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
                    <div class="label">Net Payable Amount</div>
                    <div class="value">₹ <?= indianCurrencyFormat($grand_total) ?></div>
                </div>
            </div>

            <!-- Detailed Table -->
            <h5 class="fw-bold mb-3"><i class="fas fa-list me-2"></i> Income Breakdown (<?= $period_label ?>)</h5>
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
                            <tr><td colspan="5" class="text-center text-muted py-4">No income found for this period.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($history as $row): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
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

<script>
    // Show/hide custom date fields based on selection
    document.getElementById('rangeSelect').addEventListener('change', function() {
        var customFields = document.querySelectorAll('.custom-date-field');
        if (this.value === 'custom') {
            customFields.forEach(function(field) {
                field.classList.remove('d-none');
            });
        } else {
            customFields.forEach(function(field) {
                field.classList.add('d-none');
            });
            this.form.submit(); // Auto-submit for predefined ranges
        }
    });
</script>

<?php include 'footer.php'; ?>
