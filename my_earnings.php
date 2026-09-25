<?php
// ============================================================
// 💰 My Earnings – Professional Income Statement (Premium Look)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$user_stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch();
if (!$user_info) die("User not found.");

// ---- Date Filter ----
$range = $_GET['range'] ?? 'this_month';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

if ($range == 'this_week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
    $period_label = "This Week";
} elseif ($range == 'last_week') {
    $start_date = date('Y-m-d', strtotime('monday last week'));
    $end_date = date('Y-m-d', strtotime('sunday last week'));
    $period_label = "Last Week";
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
    $period_label = "Custom Period";
}

$start_dt = $start_date . ' 00:00:00';
$end_dt = $end_date . ' 23:59:59';

// ---- Fetch PAID earnings ----
$stmt = $pdo->prepare("
    SELECT e.*, u.name as from_user_name, u.email as from_user_email 
    FROM user_earnings e 
    LEFT JOIN users u ON e.from_user_id = u.id 
    WHERE e.user_id = ? 
      AND e.status = 'paid'
      AND e.paid_at >= ? 
      AND e.paid_at <= ? 
    ORDER BY e.paid_at DESC, e.id ASC
");
$stmt->execute([$user_id, $start_dt, $end_dt]);
$earnings = $stmt->fetchAll();

// Group by UTR + Date
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

// Compute deduction percentages
$tds_pct = $grand_gross > 0 ? round(($grand_tds / $grand_gross) * 100, 2) : 0;
$admin_pct = $grand_gross > 0 ? round(($grand_admin / $grand_gross) * 100, 2) : 0;

include 'header.php';
?>

<style>
    :root {
        --brand-dark: #0f172a;
        --brand-blue: #1e3a8a;
        --brand-blue-light: #2563eb;
        --brand-green: #059669;
        --brand-gold: #f59e0b;
        --brand-red: #dc2626;
        --brand-purple: #7c3aed;
    }

    body { background: #f0f4f8; }

    /* ================= STATEMENT WRAPPER ================= */
    .statement-wrapper {
        max-width: 1100px;
        margin: 0 auto;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 12px 45px rgba(15, 23, 42, 0.10), 0 2px 8px rgba(15, 23, 42, 0.04);
        overflow: hidden;
        margin-bottom: 40px;
    }

    /* ================= BRAND HEADER ================= */
    .statement-brand-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%);
        color: #fff;
        padding: 40px 45px 30px;
        position: relative;
        overflow: hidden;
    }
    .statement-brand-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 400px;
        height: 400px;
        background: radial-gradient(circle, rgba(251, 191, 36, 0.15), transparent 70%);
        border-radius: 50%;
    }
    .statement-brand-header::after {
        content: '';
        position: absolute;
        bottom: -60%;
        left: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(59, 130, 246, 0.2), transparent 70%);
        border-radius: 50%;
    }

    .brand-logo-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        z-index: 2;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 25px;
    }
    .brand-logo {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .brand-logo .logo-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        color: #0f172a;
        box-shadow: 0 8px 25px rgba(251, 191, 36, 0.35);
    }
    .brand-logo .brand-name {
        font-size: 1.6rem;
        font-weight: 900;
        letter-spacing: -0.5px;
        line-height: 1.1;
    }
    .brand-logo .brand-tagline {
        font-size: 0.75rem;
        opacity: 0.75;
        letter-spacing: 2px;
        text-transform: uppercase;
        font-weight: 600;
        margin-top: 2px;
    }

    .statement-period-badge {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.25);
        padding: 12px 24px;
        border-radius: 14px;
        text-align: right;
    }
    .statement-period-badge .label {
        font-size: 0.65rem;
        text-transform: uppercase;
        opacity: 0.75;
        letter-spacing: 1.5px;
        font-weight: 700;
    }
    .statement-period-badge .value {
        font-size: 1.05rem;
        font-weight: 800;
        margin-top: 2px;
    }

    /* ================= META ROW ================= */
    .statement-meta {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
        padding-top: 25px;
        border-top: 1px solid rgba(255, 255, 255, 0.15);
        position: relative;
        z-index: 2;
    }
    .statement-meta .meta-item {
        flex: 1;
        min-width: 200px;
    }
    .statement-meta .meta-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        opacity: 0.65;
        letter-spacing: 1.5px;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .statement-meta .meta-value {
        font-size: 1rem;
        font-weight: 700;
    }
    .statement-meta .meta-value small {
        font-weight: 500;
        opacity: 0.8;
        font-size: 0.85rem;
    }

    /* ================= SUMMARY GRID ================= */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        padding: 30px 45px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .summary-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        border: 2px solid #e2e8f0;
        transition: all 0.25s ease;
    }
    .summary-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
    }
    .summary-card .sc-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .summary-card .sc-content {
        flex: 1;
        min-width: 0;
    }
    .summary-card .sc-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }
    .summary-card .sc-value {
        font-size: 1.4rem;
        font-weight: 900;
        line-height: 1;
    }

    .summary-card.sc-gross { border-color: #fcd34d; background: linear-gradient(135deg, #fffbeb, #fef3c7); }
    .summary-card.sc-gross .sc-icon { background: #fef3c7; color: #b45309; }
    .summary-card.sc-gross .sc-value { color: #b45309; }

    .summary-card.sc-tds { border-color: #fca5a5; background: linear-gradient(135deg, #fef2f2, #fee2e2); }
    .summary-card.sc-tds .sc-icon { background: #fee2e2; color: #b91c1c; }
    .summary-card.sc-tds .sc-value { color: #b91c1c; }

    .summary-card.sc-admin { border-color: #c4b5fd; background: linear-gradient(135deg, #f5f3ff, #ede9fe); }
    .summary-card.sc-admin .sc-icon { background: #ede9fe; color: #6d28d9; }
    .summary-card.sc-admin .sc-value { color: #6d28d9; }

    .summary-card.sc-net { border-color: #6ee7b7; background: linear-gradient(135deg, #ecfdf5, #d1fae5); }
    .summary-card.sc-net .sc-icon { background: #d1fae5; color: #065f46; }
    .summary-card.sc-net .sc-value { color: #059669; }

    /* ================= BODY ================= */
    .statement-body {
        padding: 30px 45px 40px;
    }

    .section-heading {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
    }
    .section-heading .sh-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .section-heading h5 {
        margin: 0;
        font-weight: 800;
        color: #0f172a;
        font-size: 1.1rem;
    }
    .section-heading .sh-count {
        background: #eff6ff;
        color: #1d4ed8;
        padding: 3px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 800;
        margin-left: auto;
    }

    /* ================= BATCH SLIP ================= */
    .batch-slip {
        background: #fff;
        border: 2px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 24px;
        transition: all 0.25s ease;
    }
    .batch-slip:hover {
        border-color: #cbd5e1;
        box-shadow: 0 8px 25px rgba(15, 23, 42, 0.06);
    }

    .batch-slip-header {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        padding: 16px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        border-bottom: 2px solid #86efac;
    }
    .batch-info {
        display: flex;
        gap: 25px;
        flex-wrap: wrap;
        align-items: center;
    }
    .batch-info .info-item {
        display: flex;
        flex-direction: column;
    }
    .batch-info .info-item .lbl {
        font-size: 0.62rem;
        text-transform: uppercase;
        color: #166534;
        font-weight: 800;
        letter-spacing: 0.8px;
        margin-bottom: 2px;
    }
    .batch-info .info-item .val {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
    }
    .batch-info .info-item .utr-code {
        font-family: 'Courier New', monospace;
        background: #fff;
        padding: 2px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        border: 1px solid #86efac;
        color: #166534;
        font-weight: 700;
    }

    .batch-net-badge {
        background: linear-gradient(135deg, #059669, #10b981);
        color: #fff;
        padding: 10px 22px;
        border-radius: 12px;
        text-align: right;
        box-shadow: 0 6px 18px rgba(16, 185, 129, 0.3);
    }
    .batch-net-badge .lbl {
        font-size: 0.6rem;
        text-transform: uppercase;
        opacity: 0.85;
        font-weight: 800;
        letter-spacing: 1px;
    }
    .batch-net-badge .val {
        font-size: 1.25rem;
        font-weight: 900;
        line-height: 1;
        margin-top: 2px;
    }

    /* ================= TABLE ================= */
    .earnings-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.83rem;
    }
    .earnings-table thead th {
        background: #1e293b;
        color: #fff;
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 12px 10px;
        text-align: left;
        font-weight: 800;
        white-space: nowrap;
    }
    .earnings-table tbody td {
        padding: 12px 10px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #1e293b;
    }
    .earnings-table tbody tr:hover {
        background: #f8fafc;
    }
    .earnings-table tbody tr:last-child td {
        border-bottom: none;
    }
    .earnings-table tfoot td {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        padding: 14px 10px;
        font-weight: 900;
        font-size: 0.88rem;
        color: #166534;
        border-top: 2px solid #86efac;
    }

    .user-cell strong {
        font-weight: 800;
        color: #0f172a;
        display: block;
        font-size: 0.85rem;
    }
    .user-cell small {
        font-size: 0.7rem;
        color: #64748b;
    }

    .badge-type {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }
    .badge-type.direct { background: #dcfce7; color: #166534; }
    .badge-type.team { background: #ede9fe; color: #5b21b6; }

    .amt-gross { font-weight: 800; color: #0f172a; white-space: nowrap; }
    .amt-deduct { color: #dc2626; font-weight: 700; white-space: nowrap; }
    .amt-net { color: #059669; font-weight: 900; white-space: nowrap; font-size: 0.88rem; }

    /* ================= GRAND TOTAL ================= */
    .grand-total-box {
        margin-top: 30px;
        background: linear-gradient(135deg, #0f172a, #1e3a8a);
        border-radius: 16px;
        padding: 25px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        color: #fff;
        box-shadow: 0 12px 35px rgba(15, 23, 42, 0.25);
    }
    .grand-total-box .gt-left h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 0.5px;
        opacity: 0.85;
        text-transform: uppercase;
    }
    .grand-total-box .gt-left p {
        margin: 4px 0 0;
        font-size: 0.78rem;
        opacity: 0.7;
    }
    .grand-total-box .gt-right {
        display: flex;
        gap: 25px;
        flex-wrap: wrap;
    }
    .gt-item {
        text-align: right;
    }
    .gt-item .lbl {
        font-size: 0.65rem;
        text-transform: uppercase;
        opacity: 0.75;
        font-weight: 800;
        letter-spacing: 1px;
    }
    .gt-item .val {
        font-size: 1.05rem;
        font-weight: 800;
        margin-top: 2px;
    }
    .gt-item.net .val {
        font-size: 1.6rem;
        color: #6ee7b7;
        font-weight: 900;
    }

    /* ================= EMPTY STATE ================= */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    .empty-state i {
        font-size: 4rem;
        opacity: 0.3;
        margin-bottom: 15px;
    }
    .empty-state h5 {
        font-weight: 700;
        color: #64748b;
    }

    /* ================= FOOTER ================= */
    .statement-footer {
        background: #f8fafc;
        padding: 20px 45px;
        text-align: center;
        font-size: 0.75rem;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
    }

    /* ================= FILTER BAR ================= */
    .filter-bar {
        max-width: 1100px;
        margin: 0 auto 20px;
        background: #fff;
        border-radius: 14px;
        padding: 16px 20px;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
        border: 1px solid #e2e8f0;
    }
    .filter-bar .filter-left {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    .filter-bar .filter-left label {
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
    }
    .filter-bar select, .filter-bar input[type="date"] {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #1e293b;
        background: #fff;
        transition: all 0.2s;
    }
    .filter-bar select:focus, .filter-bar input[type="date"]:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .btn-print {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 800;
        font-size: 0.82rem;
        cursor: pointer;
        transition: all 0.25s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }
    .btn-print:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
    }

    /* ================= PRINT ================= */
    @media print {
        body { background: #fff !important; }
        .sidebar, .top-nav, .top-bar, .sidebar-overlay-main, .filter-bar, .btn-print,
        .impersonate-banner, footer, .no-print { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
        .statement-wrapper {
            box-shadow: none !important;
            border-radius: 0 !important;
            max-width: 100% !important;
            margin: 0 !important;
        }
        .batch-slip { page-break-inside: avoid; }
        .summary-card { box-shadow: none !important; }
    }

    @media (max-width: 768px) {
        .statement-brand-header { padding: 25px 20px; }
        .summary-grid { padding: 20px; gap: 12px; }
        .statement-body { padding: 20px; }
        .brand-logo .brand-name { font-size: 1.2rem; }
        .batch-net-badge .val { font-size: 1rem; }
        .grand-total-box { padding: 20px; }
        .gt-item .val { font-size: 0.95rem; }
        .gt-item.net .val { font-size: 1.3rem; }
        .earnings-table { font-size: 0.75rem; }
        .earnings-table thead th, .earnings-table tbody td { padding: 8px 6px; }
    }
</style>

<div class="container-fluid px-3">
    <!-- FILTER BAR -->
    <div class="filter-bar no-print">
        <form method="GET" class="filter-left" id="filterForm">
            <label><i class="fas fa-calendar-alt me-1"></i> Period:</label>
            <select name="range" id="rangeSelect">
                <option value="this_week" <?= $range == 'this_week' ? 'selected' : '' ?>>This Week</option>
                <option value="last_week" <?= $range == 'last_week' ? 'selected' : '' ?>>Last Week</option>
                <option value="this_month" <?= $range == 'this_month' ? 'selected' : '' ?>>This Month</option>
                <option value="last_month" <?= $range == 'last_month' ? 'selected' : '' ?>>Last Month</option>
                <option value="all_time" <?= $range == 'all_time' ? 'selected' : '' ?>>All Time</option>
                <option value="custom" <?= $range == 'custom' ? 'selected' : '' ?>>Custom Range</option>
            </select>
            <span class="custom-date-field <?= $range == 'custom' ? '' : 'd-none' ?>">
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
            </span>
            <span class="custom-date-field <?= $range == 'custom' ? '' : 'd-none' ?>">
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </span>
            <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 <?= $range == 'custom' ? '' : 'd-none' ?>" id="applyBtn">Apply</button>
        </form>
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-file-download"></i> Download / Print
        </button>
    </div>

    <!-- STATEMENT -->
    <div class="statement-wrapper">

        <!-- BRAND HEADER -->
        <div class="statement-brand-header">
            <div class="brand-logo-row">
                <div class="brand-logo">
                    <div class="logo-icon"><i class="fas fa-building"></i></div>
                    <div>
                        <div class="brand-name">PRIME PROPERTY INDIA</div>
                        <div class="brand-tagline">Income Statement</div>
                    </div>
                </div>
                <div class="statement-period-badge">
                    <div class="label">Statement Period</div>
                    <div class="value"><?= $period_label ?></div>
                </div>
            </div>

            <div class="statement-meta">
                <div class="meta-item">
                    <div class="meta-label">Beneficiary</div>
                    <div class="meta-value"><?= htmlspecialchars($user_info['name']) ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">User ID</div>
                    <div class="meta-value">#<?= $user_id ?></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Email</div>
                    <div class="meta-value"><small><?= htmlspecialchars($user_info['email']) ?></small></div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">Generated On</div>
                    <div class="meta-value"><small><?= date('d M Y, h:i A') ?></small></div>
                </div>
            </div>
        </div>

        <!-- SUMMARY GRID -->
        <div class="summary-grid">
            <div class="summary-card sc-gross">
                <div class="sc-icon"><i class="fas fa-coins"></i></div>
                <div class="sc-content">
                    <div class="sc-label">Gross Income</div>
                    <div class="sc-value">₹ <?= indianCurrencyFormat($grand_gross) ?></div>
                </div>
            </div>
            <div class="summary-card sc-tds">
                <div class="sc-icon"><i class="fas fa-percent"></i></div>
                <div class="sc-content">
                    <div class="sc-label">TDS Deducted (<?= $tds_pct ?>%)</div>
                    <div class="sc-value">- ₹ <?= indianCurrencyFormat($grand_tds) ?></div>
                </div>
            </div>
            <div class="summary-card sc-admin">
                <div class="sc-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="sc-content">
                    <div class="sc-label">Admin Charge (<?= $admin_pct ?>%)</div>
                    <div class="sc-value">- ₹ <?= indianCurrencyFormat($grand_admin) ?></div>
                </div>
            </div>
            <div class="summary-card sc-net">
                <div class="sc-icon"><i class="fas fa-wallet"></i></div>
                <div class="sc-content">
                    <div class="sc-label">Net Paid</div>
                    <div class="sc-value">₹ <?= indianCurrencyFormat($grand_net) ?></div>
                </div>
            </div>
        </div>

        <!-- BODY -->
        <div class="statement-body">

            <div class="section-heading">
                <div class="sh-icon"><i class="fas fa-receipt"></i></div>
                <h5>Payment History</h5>
                <span class="sh-count"><?= count($groups) ?> Payout<?= count($groups) != 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($groups)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>No Paid Earnings</h5>
                    <p>There are no paid earnings in this period.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($groups as $key => $g): ?>
                <div class="batch-slip">
                    <div class="batch-slip-header">
                        <div class="batch-info">
                            <div class="info-item">
                                <span class="lbl"><i class="fas fa-check-circle me-1"></i> Paid On</span>
                                <span class="val"><?= $g['paid_at'] ? date('d M Y, h:i A', strtotime($g['paid_at'])) : '—' ?></span>
                            </div>
                            <div class="info-item">
                                <span class="lbl"><i class="fas fa-hashtag me-1"></i> UTR</span>
                                <span class="val utr-code"><?= htmlspecialchars($g['utr']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="lbl"><i class="fas fa-list me-1"></i> Entries</span>
                                <span class="val"><?= count($g['entries']) ?></span>
                            </div>
                        </div>
                        <div class="batch-net-badge">
                            <div class="lbl">Net Paid</div>
                            <div class="val">₹ <?= indianCurrencyFormat($g['total_net']) ?></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="earnings-table">
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
                                        <td style="font-size:0.75rem; white-space:nowrap; color:#64748b;">
                                            <?= date('d M Y', strtotime($e['date'])) ?>
                                        </td>
                                        <td class="user-cell">
                                            <strong>#<?= $e['from_user_id'] ?> <?= htmlspecialchars($e['from_user_name'] ?? 'N/A') ?></strong>
                                            <small><?= htmlspecialchars($e['from_user_email'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <?php if ($e['income_type'] == 'direct'): ?>
                                                <span class="badge-type direct">Direct</span>
                                            <?php else: ?>
                                                <span class="badge-type team">Team</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.75rem; color:#475569; min-width:180px;">
                                            <?= htmlspecialchars($e['description'] ?? '') ?>
                                        </td>
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
                </div>
            <?php endforeach; ?>

            <!-- GRAND TOTAL -->
            <?php if (!empty($groups)): ?>
                <div class="grand-total-box">
                    <div class="gt-left">
                        <h4><i class="fas fa-chart-line me-2"></i>Period Summary</h4>
                        <p><?= $period_label ?> — <?= count($groups) ?> payouts, <?= count($earnings) ?> total entries</p>
                    </div>
                    <div class="gt-right">
                        <div class="gt-item">
                            <div class="lbl">Gross</div>
                            <div class="val">₹ <?= indianCurrencyFormat($grand_gross) ?></div>
                        </div>
                        <div class="gt-item">
                            <div class="lbl">TDS</div>
                            <div class="val" style="color:#fca5a5;">- ₹ <?= indianCurrencyFormat($grand_tds) ?></div>
                        </div>
                        <div class="gt-item">
                            <div class="lbl">Admin</div>
                            <div class="val" style="color:#c4b5fd;">- ₹ <?= indianCurrencyFormat($grand_admin) ?></div>
                        </div>
                        <div class="gt-item net">
                            <div class="lbl">Net Paid</div>
                            <div class="val">₹ <?= indianCurrencyFormat($grand_net) ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- FOOTER -->
        <div class="statement-footer">
            <i class="fas fa-shield-alt me-1"></i>
            This is a computer-generated statement and does not require a signature.
            <br>Generated on <?= date('d M Y, h:i A') ?> | Prime Property India
        </div>
    </div>
</div>

<script>
    document.getElementById('rangeSelect').addEventListener('change', function() {
        var customFields = document.querySelectorAll('.custom-date-field');
        var applyBtn = document.getElementById('applyBtn');
        if (this.value === 'custom') {
            customFields.forEach(function(field) { field.classList.remove('d-none'); });
            applyBtn.classList.remove('d-none');
        } else {
            customFields.forEach(function(field) { field.classList.add('d-none'); });
            applyBtn.classList.add('d-none');
            this.form.submit();
        }
    });
</script>

<?php include 'footer.php'; ?>
