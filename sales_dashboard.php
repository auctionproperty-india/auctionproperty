<?php
// ============================================================
// 📊 Sales CRM – Dashboard
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['sales', 'admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin');

// ---- Dashboard Stats ----
$scope_sql = $is_admin ? "" : " AND assigned_to = " . (int)$user_id;

$total_leads = $pdo->query("SELECT COUNT(*) FROM sales_leads WHERE 1=1 $scope_sql")->fetchColumn();

$today_followups = $pdo->query("
    SELECT COUNT(*) FROM sales_leads 
    WHERE follow_up_date = CURRENT_DATE 
    AND status NOT IN ('converted', 'closed', 'not_interested')
    $scope_sql
")->fetchColumn();

$overdue_followups = $pdo->query("
    SELECT COUNT(*) FROM sales_leads 
    WHERE follow_up_date < CURRENT_DATE 
    AND status NOT IN ('converted', 'closed', 'not_interested')
    $scope_sql
")->fetchColumn();

$converted = $pdo->query("SELECT COUNT(*) FROM sales_leads WHERE status = 'converted' $scope_sql")->fetchColumn();

$status_breakdown = $pdo->query("
    SELECT status, COUNT(*) as cnt 
    FROM sales_leads 
    WHERE 1=1 $scope_sql 
    GROUP BY status
")->fetchAll();

$status_data = [];
foreach ($status_breakdown as $row) {
    $status_data[$row['status']] = $row['cnt'];
}

$recent_leads = $pdo->query("
    SELECT l.*, u.name as assigned_name 
    FROM sales_leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    WHERE 1=1 $scope_sql 
    ORDER BY l.id DESC LIMIT 8
")->fetchAll();

include 'header.php';
?>

<style>
    .sales-stat-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        transition: all 0.25s;
        height: 100%;
    }
    .sales-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    }
    .sales-stat-card .icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        margin-bottom: 12px;
    }
    .sales-stat-card .label {
        font-size: 0.72rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.8px;
        margin-bottom: 4px;
    }
    .sales-stat-card .value {
        font-size: 1.8rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }
    .sc-blue .icon { background: #dbeafe; color: #1e40af; }
    .sc-orange .icon { background: #fef3c7; color: #b45309; }
    .sc-red .icon { background: #fee2e2; color: #b91c1c; }
    .sc-green .icon { background: #d1fae5; color: #065f46; }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .qa-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-radius: 14px;
        background: #fff;
        border: 2px solid #e2e8f0;
        text-decoration: none;
        color: #0f172a;
        font-weight: 700;
        transition: all 0.25s;
    }
    .qa-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        text-decoration: none;
        color: #1e3a8a;
    }
    .qa-btn .qa-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .qa-btn.primary { border-color: #2563eb; background: #eff6ff; }
    .qa-btn.primary .qa-icon { background: #2563eb; color: #fff; }
    .qa-btn.success { border-color: #10b981; background: #f0fdf4; }
    .qa-btn.success .qa-icon { background: #10b981; color: #fff; }

    .status-pill {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: capitalize;
    }
    .st-new { background: #dbeafe; color: #1e40af; }
    .st-contacted { background: #fef3c7; color: #92400e; }
    .st-interested { background: #ddd6fe; color: #5b21b6; }
    .st-not_interested { background: #fee2e2; color: #991b1b; }
    .st-converted { background: #d1fae5; color: #065f46; }
    .st-closed { background: #e2e8f0; color: #475569; }

    .section-title {
        font-weight: 800;
        color: #0f172a;
        font-size: 1rem;
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .section-title i { color: #2563eb; }

    .leads-table {
        width: 100%;
        font-size: 0.85rem;
        border-collapse: collapse;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
    }
    .leads-table th {
        background: #1e293b; color: #fff;
        font-size: 0.68rem; text-transform: uppercase;
        padding: 11px 12px; letter-spacing: 0.5px;
        text-align: left; font-weight: 700;
    }
    .leads-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .leads-table tr:hover td { background: #f8fafc; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0"><i class="fas fa-chart-line me-2 text-primary"></i> Sales Dashboard</h3>
            <p class="text-muted mb-0 small">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Sales') ?></p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="sales_lead_add.php" class="qa-btn primary">
            <div class="qa-icon"><i class="fas fa-plus"></i></div>
            <div>
                <div>Add New Lead</div>
                <small style="font-weight:500; opacity:0.7;">Create a lead manually</small>
            </div>
        </a>
        <a href="sales_lead_upload.php" class="qa-btn success">
            <div class="qa-icon"><i class="fas fa-file-upload"></i></div>
            <div>
                <div>Bulk Upload</div>
                <small style="font-weight:500; opacity:0.7;">Upload CSV of leads</small>
            </div>
        </a>
        <a href="sales_leads.php" class="qa-btn">
            <div class="qa-icon" style="background:#f1f5f9; color:#1e3a8a;"><i class="fas fa-list"></i></div>
            <div>
                <div>All Leads</div>
                <small style="font-weight:500; opacity:0.7;"><?= $total_leads ?> total</small>
            </div>
        </a>
        <a href="sales_leads.php?followup=today" class="qa-btn">
            <div class="qa-icon" style="background:#fef3c7; color:#b45309;"><i class="fas fa-bell"></i></div>
            <div>
                <div>Today's Follow-ups</div>
                <small style="font-weight:500; opacity:0.7;"><?= $today_followups ?> pending</small>
            </div>
        </a>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="sales-stat-card sc-blue">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="label">Total Leads</div>
                <div class="value"><?= number_format($total_leads) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sales-stat-card sc-orange">
                <div class="icon"><i class="fas fa-calendar-day"></i></div>
                <div class="label">Today's Follow-ups</div>
                <div class="value"><?= number_format($today_followups) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sales-stat-card sc-red">
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="label">Overdue</div>
                <div class="value"><?= number_format($overdue_followups) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="sales-stat-card sc-green">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="label">Converted</div>
                <div class="value"><?= number_format($converted) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Status Breakdown -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="section-title"><i class="fas fa-chart-pie"></i> Lead Status</div>
                <?php 
                $all_statuses = ['new', 'contacted', 'interested', 'not_interested', 'converted', 'closed'];
                foreach ($all_statuses as $st): 
                    $cnt = $status_data[$st] ?? 0;
                    $pct = $total_leads > 0 ? round(($cnt / $total_leads) * 100) : 0;
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="status-pill st-<?= $st ?>"><?= ucfirst(str_replace('_', ' ', $st)) ?></span>
                            <strong><?= $cnt ?></strong>
                        </div>
                        <div style="background:#f1f5f9; height:6px; border-radius:10px; overflow:hidden;">
                            <div style="background:linear-gradient(90deg, #2563eb, #3b82f6); height:100%; width:<?= $pct ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Leads -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
                <div class="section-title"><i class="fas fa-clock"></i> Recent Leads</div>
                <div class="table-responsive">
                    <table class="leads-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>City</th>
                                <th>Status</th>
                                <th>Assigned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_leads)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No leads yet. Add one!</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recent_leads as $l): ?>
                                <tr style="cursor:pointer;" onclick="window.location='sales_lead_view.php?id=<?= $l['id'] ?>'">
                                    <td><strong><?= htmlspecialchars($l['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($l['phone']) ?></td>
                                    <td><?= htmlspecialchars($l['city'] ?? '—') ?></td>
                                    <td><span class="status-pill st-<?= $l['status'] ?>"><?= ucfirst(str_replace('_', ' ', $l['status'])) ?></span></td>
                                    <td style="font-size:0.78rem;"><?= htmlspecialchars($l['assigned_name'] ?? 'Unassigned') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
