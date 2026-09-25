<?php
// ============================================================
// 💰 My Earnings – Income Statement (Matches Admin Paid Preview)
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
if (!$user_info) die("User not found.");

// ---- Date Filter ----
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

$start_dt = $start_date . ' 00:00:00';
$end_dt = $end_date . ' 23:59:59';

// ============================================================
// 🔥 FETCH EARNINGS – only PAID entries
// ============================================================
$stmt = $pdo->prepare("
    SELECT e.*, u.name as from_user_name, u.email as from_user_email 
    FROM user_earnings e 
    LEFT JOIN users u ON e.from_user_id = u.id 
    WHERE e.user_id = ? 
      AND e.status = 'paid'
      AND e.paid_at >= ? 
      AND e.paid_at <= ? 
    ORDER BY e.paid_at DESC
");
$stmt->execute([$user_id, $start_dt, $end_dt]);
$earnings = $stmt->fetchAll();

// Group by batch (UTR + date) so all entries from same payout are grouped
$groups = [];
foreach ($earnings as $e) {
    $utr = $e['utr_no'] ?? 'N/A';
    $date = $e['paid_at'] ? date('Y-m-d', strtotime($e['paid_at'])) : 'N/A';
    $key = $utr . '|' . $date;
    
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'utr' => $utr,
            'paid_at' => $e['paid_at'],
            'entries' => [],
            'total_gross' => 0,
            'total_tds' => 0,
            'total_admin' => 0,
            'total_net' => 0,
        ];
    }
    
    $gross = (float)$e['amount'];
    $tds = (float)($e['tds_deducted'] ?? 0);
    $admin = (float)($e['admin_charge_deducted'] ?? 0);
    $net = (float)($e['net_amount'] ?? ($gross - $tds - $admin));
    
    $groups[$key]['entries'][] = [
        'date' => $e['paid_at'],
        'income_type' => $e['income_type'],
        'from_user_id' => $e['from_user_id'],
        'from_user_name' => $e['from_user_name'],
        'from_user_email' => $e['from_user_email'],
        'description' => $e['description'],
        'gross' => $gross,
        'tds' => $tds,
        'admin' => $admin,
        'net' => $net,
    ];
    $groups[$key]['total_gross'] += $gross;
    $groups[$key]['total_tds'] += $tds;
    $groups[$key]['total_admin'] += $admin;
    $groups[$key]['total_net'] += $net;
}

// Grand totals
$grand_gross = 0; $grand_tds = 0; $grand_admin = 0; $grand_net = 0;
foreach ($groups as $g) {
    $grand_gross += $g['total_gross'];
    $grand_tds += $g['total_tds'];
    $grand_admin += $g['total_admin'];
    $grand_net += $g['total_net'];
}

include 'header.php';
?>

<style>
    .slip-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        overflow: hidden;
        margin-bottom: 25px;
    }
    .slip-header {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 25px 30px;
        text-align: center;
    }
    .slip-header h2 { margin: 0; font-weight: 800; letter-spacing: 1px; }
    .slip-header p { margin: 5px 0 0; opacity: 0.85; font-size: 0.9rem; }
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
        gap: 12px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    .summary-item {
        flex: 1;
        min-width: 140px;
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
    }
    .summary-item .label {
        font-size: 0.68rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.4px;
    }
    .summary-item .value { font-size: 1.4rem; font-weight: 800; margin-top: 4px; }
    .summary-item.gross { border-color: #f59e0b; background: #fef3c7; }
    .summary-item.gross .value { color: #b45309; }
    .summary-item.tds { border-color: #ef4444; background: #fef2f2; }
    .summary-item.tds .value { color: #b91c1c; }
    .summary-item.admin { border-color: #8b5cf6; background: #f5f3ff; }
    .summary-item.admin .value { color: #6d28d9; }
    .summary-item.net { border-color: #10b981; background: #f0fdf4; }
    .summary-item.net .value { color: #059669; }

    .batch-header {
        background: #f1f5f9;
        padding: 12px 18px;
        border-radius: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        margin-bottom: 12px;
        flex-wrap: wrap;
        gap: 8px;
        border-left: 5px solid #10b981;
    }
    .batch-header .info { font-size: 0.8rem; color: #475569; }
    .batch-header .utr { font-family: monospace; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
    .batch-header .net { font-weight: 800; color: #059669; font-size: 1rem; }

    .table-custom {
        width: 100%;
        font-size: 0.82rem;
        border-collapse: collapse;
    }
    .table-custom th {
        background: #1e293b;
        color: #fff;
        font-size: 0.7rem;
        text-transform: uppercase;
        padding: 10px 8px;
        letter-spacing: 0.4px;
        text-align: left;
    }
    .table-custom td {
        padding: 10px 8px;
        vertical-align: middle;
        font-size: 0.82rem;
        border-bottom: 1px solid #f1f5f9;
    }
    .table-custom tr:hover { background: #f8fafc; }
    .table-custom tfoot td {
        background: #f0fdf4;
        font-weight: 800;
        font-size: 0.9rem;
        color: #166534;
        border-top: 2px solid #10b981;
    }
    .badge-income {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
    }
    .badge-income.direct { background: #dcfce7; color: #166534; }
    .badge-income.team { background: #ede9fe; color: #5b21b6; }
    .badge-level { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 6px; font-size: 0.68rem; font-weight: 700; }

    .amt-gross { font-weight: 700; color: #0f172a; }
    .amt-deduct { color: #dc2626; font-weight: 600; }
    .amt-net { color: #059669; font-weight: 800; }

    @media print {
        body { background: #fff !important; margin: 0; padding: 0; }
        .sidebar, .header-top, .navbar, footer, .no-print, .top-bar { display: none !important; }
        .slip-card { box-shadow: none !important; border: 1px solid #ccc !important; width: 100% !important; max-width: 100% !important; margin: 0 !important; }
        .container { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
        .summary-item { border: 1px solid #ccc !important; }
        .batch-header { border: 1px solid #ccc !important; }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
        <h3 class="fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> My Income Statement</h3>
    </div>

    <!-- Filter Form -->
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

    <!-- Salary Slip -->
    <div class="slip-card">
        <div class="slip-header">
            <h2>PRIME PROPERTY INDIA</h2>
            <p>Income Statement / Salary Slip</p>
            <p style="font-size:0.8rem; margin-top:5px; background:rgba(255,255,255,0.2); display:inline-block; padding:3px 12px; border-radius:20px;">
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

            <!-- Summary (Gross → TDS → Admin → Net) -->
            <div class="summary-box">
                <div class="summary-item gross">
                    <div class="label">Gross Income</div>
                    <div class="value">₹ <?= indianCurrencyFormat($grand_gross) ?></div>
                </div>
                <div class="summary-item tds">
                    <div class="label">TDS Deducted</div>
                    <div class="value">- ₹ <?= indianCurrencyFormat($grand_tds) ?></div>
                </div>
                <div class="summary-item admin">
                    <div class="label">Admin Charge</div>
                    <div class="value">- ₹ <?= indianCurrencyFormat($grand_admin) ?></div>
                </div>
                <div class="summary-item net">
                    <div class="label">Net Paid</div>
                    <div class="value">₹ <?= indianCurrencyFormat($grand_net) ?></div>
                </div>
            </div>

            <!-- Payment Batches -->
            <h5 class="fw-bold mb-3"><i class="fas fa-list me-2"></i> Payment History (<?= count($groups) ?> payouts)</h5>

            <?php if (empty($groups)): ?>
                <div class="alert alert-light text-center py-4">
                    <i class="fas fa-inbox fa-2x text-muted mb-2" style="opacity:0.4;"></i>
                    <p class="text-muted mb-0">No paid earnings in this period.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($groups as $key => $g): ?>
                <div class="batch-header">
                    <div class="info">
                        <strong><i class="fas fa-check-circle text-success me-1"></i> Paid on:</strong> 
                        <?= $g['paid_at'] ? date('d M Y, h:i A', strtotime($g['paid_at'])) : '—' ?>
                        &nbsp;|&nbsp; 
                        <strong>UTR:</strong> <span class="utr"><?= htmlspecialchars($g['utr']) ?></span>
                    </div>
                    <div class="net">Net: ₹ <?= indianCurrencyFormat($g['total_net']) ?></div>
                </div>

                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>From User</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">TDS</th>
                                <th class="text-end">Admin</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($g['entries'] as $e): ?>
                                <tr>
                                    <td style="font-size:0.75rem; white-space:nowrap;"><?= date('d M Y', strtotime($e['date'])) ?></td>
                                    <td>
                                        <strong>#<?= $e['from_user_id'] ?> <?= htmlspecialchars($e['from_user_name'] ?? 'N/A') ?></strong>
                                        <div style="font-size:0.7rem; color:#64748b;"><?= htmlspecialchars($e['from_user_email'] ?? '') ?></div>
                                    </td>
                                    <td>
                                        <?php if ($e['income_type'] == 'direct'): ?>
                                            <span class="badge-income direct">Direct</span>
                                        <?php else: ?>
                                            <span class="badge-income team">Team Turnover</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:0.75rem; color:#475569;"><?= htmlspecialchars($e['description'] ?? '') ?></td>
                                    <td class="text-end amt-gross">₹ <?= indianCurrencyFormat($e['gross']) ?></td>
                                    <td class="text-end amt-deduct">- ₹ <?= indianCurrencyFormat($e['tds']) ?></td>
                                    <td class="text-end amt-deduct">- ₹ <?= indianCurrencyFormat($e['admin']) ?></td>
                                    <td class="text-end amt-net">₹ <?= indianCurrencyFormat($e['net']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" class="text-end">BATCH TOTAL:</td>
                                <td class="text-end">₹ <?= indianCurrencyFormat($g['total_gross']) ?></td>
                                <td class="text-end">- ₹ <?= indianCurrencyFormat($g['total_tds']) ?></td>
                                <td class="text-end">- ₹ <?= indianCurrencyFormat($g['total_admin']) ?></td>
                                <td class="text-end">₹ <?= indianCurrencyFormat($g['total_net']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endforeach; ?>

            <div class="text-center mt-4 text-muted" style="font-size:0.75rem;">
                This is a computer-generated statement and does not require a signature.
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('rangeSelect').addEventListener('change', function() {
        var customFields = document.querySelectorAll('.custom-date-field');
        if (this.value === 'custom') {
            customFields.forEach(function(field) { field.classList.remove('d-none'); });
        } else {
            customFields.forEach(function(field) { field.classList.add('d-none'); });
            this.form.submit();
        }
    });
</script>

<?php include 'footer.php'; ?>
