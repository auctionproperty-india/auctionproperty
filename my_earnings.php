<?php
// ============================================================
// 💰 My Earnings – Clean Readable Typography
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
    $period_label = "This Month";
} elseif ($range == 'last_month') {
    $start_date = date('Y-m-01', strtotime('first day of last month'));
    $end_date = date('Y-m-t', strtotime('last day of last month'));
    $period_label = "Last Month";
} elseif ($range == 'all_time') {
    $start_date = '2000-01-01';
    $end_date = date('Y-m-d');
    $period_label = "All Time";
} elseif ($range == 'custom') {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    $period_label = "Custom";
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
    
    preg_match('/Level\s*(\d+)/i', $e['description'] ?? '', $m);
    $level = isset($m[1]) ? (int)$m[1] : 0;
    
    $groups[$key]['entries'][] = [
        'date' => $e['paid_at'],
        'income_type' => $e['income_type'],
        'level' => $level,
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

// Build grouped summary
foreach ($groups as $key => &$g) {
    $summary_groups = [];
    foreach ($g['entries'] as $e) {
        $combo_key = $e['income_type'] . '_' . $e['level'];
        if (!isset($summary_groups[$combo_key])) {
            $summary_groups[$combo_key] = [
                'type' => $e['income_type'],
                'level' => $e['level'],
                'count' => 0,
                'total' => 0,
            ];
        }
        $summary_groups[$combo_key]['count']++;
        $summary_groups[$combo_key]['total'] += $e['gross'];
    }
    
    usort($summary_groups, function($a, $b) {
        if ($a['type'] !== $b['type']) return strcmp($a['type'], $b['type']);
        return $a['level'] - $b['level'];
    });
    
    $labels = range('A', 'Z');
    $idx = 0;
    foreach ($summary_groups as &$sg) {
        $sg['label'] = $labels[$idx];
        $idx++;
    }
    unset($sg);
    
    $g['summary_groups'] = $summary_groups;
}
unset($g);

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
    :root {
        --brand-dark: #0f172a;
        --brand-blue: #1e3a8a;
        --brand-blue-light: #2563eb;
        --brand-green: #059669;
        --brand-gold: #f59e0b;
        --brand-red: #dc2626;
        --brand-purple: #7c3aed;
    }

    body { background: #f0f4f8; font-family: 'Inter', sans-serif; }

    @page {
        size: A4;
        margin: 12mm 10mm;
    }

    .statement-wrapper {
        max-width: 210mm;
        margin: 0 auto;
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 12px 45px rgba(15, 23, 42, 0.10);
        overflow: hidden;
        margin-bottom: 30px;
    }

    /* ============ BRAND HEADER ============ */
    .statement-brand-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #2563eb 100%);
        color: #fff;
        padding: 24px 32px 20px;
    }
    .brand-logo-row {
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 15px; margin-bottom: 18px;
    }
    .brand-logo { display: flex; align-items: center; gap: 14px; }
    .brand-logo .logo-icon {
        width: 48px; height: 48px;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 22px; color: #0f172a;
        box-shadow: 0 6px 18px rgba(251, 191, 36, 0.35);
    }
    .brand-logo .brand-name {
        font-size: 1.15rem; font-weight: 700;
        letter-spacing: 0.5px; line-height: 1.2;
    }
    .brand-logo .brand-tagline {
        font-size: 0.7rem; opacity: 0.7; letter-spacing: 2px;
        text-transform: uppercase; font-weight: 500; margin-top: 3px;
    }
    .statement-period-badge {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.22);
        padding: 9px 20px; border-radius: 10px; text-align: right;
    }
    .statement-period-badge .label {
        font-size: 0.6rem; text-transform: uppercase; opacity: 0.7;
        letter-spacing: 1.2px; font-weight: 600;
    }
    .statement-period-badge .value {
        font-size: 0.92rem; font-weight: 700; margin-top: 3px;
    }

    .statement-meta {
        display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;
        padding-top: 16px; border-top: 1px solid rgba(255, 255, 255, 0.12);
    }
    .statement-meta .meta-item { flex: 1; min-width: 140px; }
    .statement-meta .meta-label {
        font-size: 0.6rem; text-transform: uppercase; opacity: 0.6;
        letter-spacing: 1.2px; font-weight: 600; margin-bottom: 4px;
    }
    .statement-meta .meta-value {
        font-size: 0.9rem; font-weight: 600;
    }
    .statement-meta .meta-value small {
        font-weight: 400; opacity: 0.75;
        font-size: 0.8rem;
    }

    /* ============ GROSS SUMMARY ============ */
    .gross-summary {
        display: flex;
        padding: 20px 32px;
        background: linear-gradient(135deg, #fffbeb, #fef3c7);
        border-bottom: 1px solid #fde68a;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    .gross-summary .gs-icon {
        width: 56px; height: 56px;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        border-radius: 15px;
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; color: #fff;
        box-shadow: 0 8px 20px rgba(251, 191, 36, 0.4);
        flex-shrink: 0;
    }
    .gross-summary .gs-info { flex: 1; }
    .gross-summary .gs-info .label {
        font-size: 0.7rem; text-transform: uppercase;
        color: #92400e; font-weight: 600; letter-spacing: 1px;
        margin-bottom: 4px;
    }
    .gross-summary .gs-info .value {
        font-size: 1.8rem; font-weight: 700;
        color: #b45309; line-height: 1.1;
    }
    .gross-summary .gs-info .sub {
        font-size: 0.75rem; color: #78350f;
        margin-top: 5px; font-weight: 500;
    }
    .gross-summary .gs-right { text-align: right; }
    .gross-summary .gs-right .lbl {
        font-size: 0.65rem; text-transform: uppercase;
        color: #92400e; font-weight: 600; letter-spacing: 0.8px;
    }
    .gross-summary .gs-right .val {
        font-size: 1.1rem; font-weight: 700;
        color: #78350f; margin-top: 3px;
    }

    /* ============ BODY ============ */
    .statement-body { padding: 22px 32px 26px; }

    .section-heading {
        display: flex; align-items: center; gap: 12px;
        margin-bottom: 16px; padding-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
        flex-wrap: wrap;
    }
    .section-heading .sh-icon {
        width: 34px; height: 34px;
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff; border-radius: 9px;
        display: flex; align-items: center; justify-content: center; font-size: 15px;
    }
    .section-heading h5 {
        margin: 0; font-weight: 700; color: #0f172a;
        font-size: 1rem;
    }
    .section-heading .sh-count {
        background: #eff6ff; color: #1d4ed8;
        padding: 3px 11px; border-radius: 20px;
        font-size: 0.7rem; font-weight: 600;
    }

    /* Toggle */
    .view-toggle {
        margin-left: auto;
        display: inline-flex;
        background: #f1f5f9;
        border-radius: 24px;
        padding: 3px;
        gap: 3px;
    }
    .view-toggle button {
        background: transparent;
        border: none;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .view-toggle button.active {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        box-shadow: 0 3px 8px rgba(37, 99, 235, 0.3);
    }

    /* ============ BATCH SLIP ============ */
    .batch-slip {
        background: #fff;
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 20px;
        page-break-inside: avoid;
    }

    .batch-slip-header {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        padding: 14px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        border-bottom: 1.5px solid #e2e8f0;
    }
    .batch-info { display: flex; gap: 24px; flex-wrap: wrap; align-items: center; }
    .batch-info .info-item { display: flex; flex-direction: column; }
    .batch-info .info-item .lbl {
        font-size: 0.6rem; text-transform: uppercase; color: #94a3b8;
        font-weight: 600; letter-spacing: 1px; margin-bottom: 3px;
    }
    .batch-info .info-item .val {
        font-size: 0.82rem; font-weight: 600; color: #0f172a;
    }
    .batch-info .info-item .utr-code {
        font-family: 'Courier New', monospace;
        background: #eff6ff; padding: 3px 9px;
        border-radius: 5px; font-size: 0.72rem;
        border: 1px solid #bfdbfe;
        color: #1e40af; font-weight: 600;
    }
    .batch-gross-badge {
        background: linear-gradient(135deg, #f59e0b, #fbbf24);
        color: #fff;
        padding: 9px 18px;
        border-radius: 10px;
        text-align: right;
        box-shadow: 0 5px 14px rgba(245, 158, 11, 0.3);
    }
    .batch-gross-badge .lbl {
        font-size: 0.6rem; text-transform: uppercase; opacity: 0.95;
        font-weight: 600; letter-spacing: 1px;
    }
    .batch-gross-badge .val {
        font-size: 1.05rem; font-weight: 700;
        line-height: 1.1; margin-top: 3px;
    }

    /* ============ TABLE ============ */
    .earnings-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
    .earnings-table thead th {
        background: #1e293b; color: #fff;
        font-size: 0.65rem; text-transform: uppercase;
        letter-spacing: 0.8px; padding: 11px 12px;
        text-align: left; font-weight: 600; white-space: nowrap;
    }
    .earnings-table tbody td {
        padding: 11px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #1e293b;
        font-weight: 400;
        font-size: 0.82rem;
    }
    .earnings-table tbody tr:hover { background: #f8fafc; }
    .earnings-table tbody tr:last-child td { border-bottom: none; }
    .earnings-table tfoot td {
        background: #fef3c7;
        padding: 12px;
        font-weight: 700;
        font-size: 0.85rem;
        color: #78350f;
        border-top: 1.5px solid #fcd34d;
    }

    .user-cell strong {
        font-weight: 600; color: #0f172a;
        display: block; font-size: 0.82rem;
    }
    .user-cell small {
        font-size: 0.7rem; color: #64748b;
        font-weight: 400;
    }

    .badge-type {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .badge-type.direct { background: #dcfce7; color: #166534; }
    .badge-type.team { background: #ede9fe; color: #5b21b6; }

    .group-label {
        display: inline-block;
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.72rem;
        min-width: 42px;
        text-align: center;
    }

    .level-text {
        font-weight: 500;
        color: #475569;
        font-size: 0.82rem;
    }

    .sales-text {
        font-weight: 600;
        color: #0f172a;
        font-size: 0.85rem;
    }
    .sales-text small {
        color: #94a3b8;
        font-weight: 400;
        font-size: 0.72rem;
        margin-left: 3px;
    }

    .amt-gross-cell {
        font-weight: 700;
        color: #b45309;
        white-space: nowrap;
        font-size: 0.88rem;
    }

    /* ============ DEDUCTION BREAKDOWN ============ */
    .deduction-breakdown {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border-top: 1.5px solid #e2e8f0;
        padding: 18px 26px;
    }
    .deduction-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 0.88rem;
        border-bottom: 1px dashed #cbd5e1;
    }
    .deduction-row:last-child { border-bottom: none; }
    .deduction-row .label {
        color: #475569;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .deduction-row .label i {
        width: 20px;
        text-align: center;
        color: #94a3b8;
        font-size: 0.85rem;
    }
    .deduction-row .value {
        font-weight: 600;
        color: #0f172a;
    }
    .deduction-row.deduct .value { color: #dc2626; }
    .deduction-row.deduct .label i { color: #dc2626; }
    .deduction-row.net-row {
        border-top: 2px solid #1e293b;
        border-bottom: none;
        margin-top: 6px;
        padding-top: 14px;
    }
    .deduction-row.net-row .label {
        color: #0f172a;
        font-weight: 700;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .deduction-row.net-row .label i {
        color: #059669;
        font-size: 0.95rem;
    }
    .deduction-row.net-row .value {
        color: #059669;
        font-weight: 700;
        font-size: 1.3rem;
    }

    /* ============ GRAND TOTAL ============ */
    .grand-total-box {
        margin-top: 24px;
        background: linear-gradient(135deg, #0f172a, #1e3a8a);
        border-radius: 14px;
        padding: 20px 26px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 18px;
        color: #fff;
        page-break-inside: avoid;
    }
    .grand-total-box .gt-left h4 {
        margin: 0; font-size: 0.9rem; font-weight: 600;
        letter-spacing: 1px; opacity: 0.9; text-transform: uppercase;
    }
    .grand-total-box .gt-left p {
        margin: 4px 0 0; font-size: 0.75rem;
        opacity: 0.7; font-weight: 400;
    }
    .grand-total-box .gt-right { display: flex; gap: 24px; flex-wrap: wrap; }
    .gt-item { text-align: right; }
    .gt-item .lbl {
        font-size: 0.62rem; text-transform: uppercase;
        opacity: 0.7; font-weight: 600; letter-spacing: 1px;
    }
    .gt-item .val {
        font-size: 0.95rem; font-weight: 600;
        margin-top: 3px;
    }
    .gt-item.net .val {
        font-size: 1.35rem;
        color: #6ee7b7;
        font-weight: 700;
    }

    /* ============ EMPTY ============ */
    .empty-state { text-align: center; padding: 50px 20px; color: #94a3b8; }
    .empty-state i { font-size: 3rem; opacity: 0.3; margin-bottom: 12px; }
    .empty-state h5 { font-weight: 600; color: #64748b; font-size: 1rem; }

    /* ============ FOOTER ============ */
    .statement-footer {
        background: #f8fafc;
        padding: 16px 32px;
        text-align: center;
        font-size: 0.72rem;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        font-weight: 400;
    }

    /* ============ FILTER BAR ============ */
    .filter-bar {
        max-width: 210mm; margin: 0 auto 16px;
        background: #fff; border-radius: 12px;
        padding: 14px 20px;
        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05);
        display: flex; justify-content: space-between;
        align-items: center; gap: 12px; flex-wrap: wrap;
        border: 1px solid #e2e8f0;
    }
    .filter-bar .filter-left { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .filter-bar .filter-left label {
        font-size: 0.72rem; font-weight: 600;
        text-transform: uppercase; color: #64748b;
        letter-spacing: 0.8px;
    }
    .filter-bar select, .filter-bar input[type="date"] {
        border: 2px solid #e2e8f0; border-radius: 8px;
        padding: 7px 14px; font-size: 0.82rem;
        font-weight: 500; color: #1e293b; background: #fff;
    }
    .filter-bar select:focus, .filter-bar input[type="date"]:focus {
        outline: none; border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
    .btn-print {
        background: linear-gradient(135deg, #1e3a8a, #2563eb);
        color: #fff; border: none;
        padding: 9px 22px; border-radius: 9px;
        font-weight: 600; font-size: 0.82rem;
        cursor: pointer; transition: all 0.25s;
        display: inline-flex; align-items: center; gap: 7px;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }
    .btn-print:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4); }

    /* ============ PRINT ============ */
    @media print {
        body { background: #fff !important; font-size: 10pt; }
        .sidebar, .top-nav, .top-bar, .sidebar-overlay-main, .filter-bar, .btn-print,
        .impersonate-banner, footer, .no-print, .view-toggle { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 0 !important; }
        .statement-wrapper {
            box-shadow: none !important;
            border-radius: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .statement-brand-header { padding: 15px 20px !important; border-radius: 0 !important; }
        .gross-summary { padding: 12px 20px !important; }
        .statement-body { padding: 15px 20px !important; }
        .batch-slip { page-break-inside: avoid; margin-bottom: 12px !important; }
        .deduction-breakdown { page-break-inside: avoid; }
        .grand-total-box { page-break-inside: avoid; margin-top: 15px !important; }
        .statement-footer { page-break-inside: avoid; }
    }

    /* Hide/Show Views */
    .view-detailed { display: none; }
    .view-summary  { display: block; }
    body.show-detailed .view-detailed { display: block; }
    body.show-detailed .view-summary  { display: none; }

    /* Summary view: hide batch header */
    .batch-slip-header { display: none; }
    body.show-detailed .batch-slip-header { display: flex; }

    @media (max-width: 768px) {
        .statement-brand-header { padding: 18px 20px; }
        .gross-summary { padding: 15px 20px; }
        .gross-summary .gs-info .value { font-size: 1.5rem; }
        .statement-body { padding: 15px 20px; }
        .earnings-table { font-size: 0.75rem; }
        .earnings-table thead th, .earnings-table tbody td { padding: 8px 8px; }
        .view-toggle { margin-left: 0; }
    }
</style>

<div class="container-fluid px-3">
    <!-- FILTER BAR -->
    <div class="filter-bar no-print">
        <form method="GET" class="filter-left" id="filterForm">
            <label><i class="fas fa-calendar-alt me-1"></i> Period</label>
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
            <i class="fas fa-file-download"></i> Print / Download A4
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

        <!-- GROSS SUMMARY -->
        <div class="gross-summary">
            <div class="gs-icon"><i class="fas fa-coins"></i></div>
            <div class="gs-info">
                <div class="label">Total Gross Income</div>
                <div class="value">₹ <?= indianCurrencyFormat($grand_gross) ?></div>
                <div class="sub">
                    <?= count($groups) ?> Payout<?= count($groups) != 1 ? 's' : '' ?> &nbsp;·&nbsp; <?= count($earnings) ?> Total Entr<?= count($earnings) != 1 ? 'ies' : 'y' ?>
                </div>
            </div>
            <div class="gs-right">
                <div class="lbl">Net Payable</div>
                <div class="val">₹ <?= indianCurrencyFormat($grand_net) ?></div>
            </div>
        </div>

        <!-- BODY -->
        <div class="statement-body">

            <div class="section-heading">
                <div class="sh-icon"><i class="fas fa-receipt"></i></div>
                <h5>Earnings Breakdown</h5>
                <span class="sh-count"><?= count($groups) ?> Payout<?= count($groups) != 1 ? 's' : '' ?></span>

                <?php if (!empty($groups)): ?>
                <div class="view-toggle no-print">
                    <button type="button" id="btnSummary" class="active" onclick="setView('summary')">
                        <i class="fas fa-layer-group"></i> Summary
                    </button>
                    <button type="button" id="btnDetailed" onclick="setView('detailed')">
                        <i class="fas fa-list"></i> Detailed
                    </button>
                </div>
                <?php endif; ?>
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

                    <!-- Batch Header (only in Detailed view) -->
                    <div class="batch-slip-header">
                        <div class="batch-info">
                            <div class="info-item">
                                <span class="lbl">Paid On</span>
                                <span class="val"><?= $g['paid_at'] ? date('d M Y, h:i A', strtotime($g['paid_at'])) : '—' ?></span>
                            </div>
                            <div class="info-item">
                                <span class="lbl">Reference</span>
                                <span class="val utr-code"><?= htmlspecialchars($g['utr']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="lbl">Entries</span>
                                <span class="val"><?= count($g['entries']) ?></span>
                            </div>
                        </div>
                        <div class="batch-gross-badge">
                            <div class="lbl">Gross Amount</div>
                            <div class="val">₹ <?= indianCurrencyFormat($g['total_gross']) ?></div>
                        </div>
                    </div>

                    <!-- ============ SUMMARY VIEW ============ -->
                    <div class="view-summary">
                        <div class="table-responsive">
                            <table class="earnings-table">
                                <thead>
                                    <tr>
                                        <th style="width:110px;">Group</th>
                                        <th>Income Type</th>
                                        <th class="text-center" style="width:110px;">Level</th>
                                        <th class="text-center" style="width:120px;">Sales</th>
                                        <th class="text-end" style="width:150px;">Gross Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($g['summary_groups'])): ?>
                                        <tr><td colspan="5" class="text-center text-muted">No entries</td></tr>
                                    <?php else: foreach ($g['summary_groups'] as $sg): ?>
                                        <tr>
                                            <td><span class="group-label">Group <?= $sg['label'] ?></span></td>
                                            <td>
                                                <?php if ($sg['type'] == 'direct'): ?>
                                                    <span class="badge-type direct">Direct Income</span>
                                                <?php else: ?>
                                                    <span class="badge-type team">Team Turnover</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($sg['level'] > 0): ?>
                                                    <span class="level-text">Level <?= $sg['level'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="sales-text"><?= $sg['count'] ?></span>
                                                <small>Sales</small>
                                            </td>
                                            <td class="text-end amt-gross-cell">₹ <?= indianCurrencyFormat($sg['total']) ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end">GROSS TOTAL</td>
                                        <td class="text-end">₹ <?= indianCurrencyFormat($g['total_gross']) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- ============ DETAILED VIEW ============ -->
                    <div class="view-detailed">
                        <div class="table-responsive">
                            <table class="earnings-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>From User</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="text-end">Gross Amount</th>
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
                                            <td class="text-end amt-gross-cell">₹ <?= indianCurrencyFormat($e['gross']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-end">GROSS TOTAL</td>
                                        <td class="text-end">₹ <?= indianCurrencyFormat($g['total_gross']) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- DEDUCTION BREAKDOWN -->
                    <div class="deduction-breakdown">
                        <div class="deduction-row">
                            <span class="label"><i class="fas fa-coins"></i> Gross Amount</span>
                            <span class="value">₹ <?= indianCurrencyFormat($g['total_gross']) ?></span>
                        </div>
                        <div class="deduction-row deduct">
                            <span class="label"><i class="fas fa-percent"></i> TDS Deducted</span>
                            <span class="value">- ₹ <?= indianCurrencyFormat($g['total_tds']) ?></span>
                        </div>
                        <div class="deduction-row deduct">
                            <span class="label"><i class="fas fa-hand-holding-usd"></i> Admin Charge</span>
                            <span class="value">- ₹ <?= indianCurrencyFormat($g['total_admin']) ?></span>
                        </div>
                        <div class="deduction-row net-row">
                            <span class="label"><i class="fas fa-wallet"></i> Net Payable</span>
                            <span class="value">₹ <?= indianCurrencyFormat($g['total_net']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- GRAND TOTAL -->
            <?php if (!empty($groups)): ?>
                <div class="grand-total-box">
                    <div class="gt-left">
                        <h4><i class="fas fa-chart-line me-2"></i>Period Summary</h4>
                        <p><?= $period_label ?> &nbsp;·&nbsp; <?= count($groups) ?> Payout<?= count($groups) != 1 ? 's' : '' ?></p>
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
            <br>Generated on <?= date('d M Y, h:i A') ?> · Prime Property India
        </div>
    </div>
</div>

<script>
    function setView(mode) {
        if (mode === 'detailed') {
            document.body.classList.add('show-detailed');
            document.getElementById('btnDetailed').classList.add('active');
            document.getElementById('btnSummary').classList.remove('active');
            try { localStorage.setItem('earnings_view', 'detailed'); } catch(e) {}
        } else {
            document.body.classList.remove('show-detailed');
            document.getElementById('btnSummary').classList.add('active');
            document.getElementById('btnDetailed').classList.remove('active');
            try { localStorage.setItem('earnings_view', 'summary'); } catch(e) {}
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        try {
            var savedView = localStorage.getItem('earnings_view') || 'summary';
            setView(savedView);
        } catch(e) {}
    });

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
