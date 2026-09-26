<?php
// ============================================================
// 📊 Admin – Sales CRM Dashboard (Full Overview)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// ---- Overall Stats ----
$total_leads = $pdo->query("SELECT COUNT(*) FROM sales_leads")->fetchColumn();
$converted = $pdo->query("SELECT COUNT(*) FROM sales_leads WHERE status='converted'")->fetchColumn();
$today_followups = $pdo->query("SELECT COUNT(*) FROM sales_leads WHERE follow_up_date=CURRENT_DATE AND status NOT IN ('converted','closed','not_interested')")->fetchColumn();
$overdue = $pdo->query("SELECT COUNT(*) FROM sales_leads WHERE follow_up_date<CURRENT_DATE AND status NOT IN ('converted','closed','not_interested')")->fetchColumn();
$unassigned = $pdo->query("SELECT COUNT(*) FROM sales_leads WHERE assigned_to IS NULL")->fetchColumn();

// ---- Status breakdown ----
$status_breakdown = $pdo->query("SELECT status, COUNT(*) as cnt FROM sales_leads GROUP BY status")->fetchAll();
$status_data = [];
foreach ($status_breakdown as $row) $status_data[$row['status']] = $row['cnt'];

// ---- Sales Team Performance ----
$sales_users = $pdo->query("
    SELECT u.id, u.name, u.email, u.sales_target,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id) as total_leads,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id AND status='converted') as converted_leads,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id AND status NOT IN ('converted','closed','not_interested')) as active_leads,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id AND follow_up_date=CURRENT_DATE AND status NOT IN ('converted','closed','not_interested')) as today_fup,
           (SELECT COUNT(*) FROM sales_leads WHERE assigned_to = u.id AND follow_up_date<CURRENT_DATE AND status NOT IN ('converted','closed','not_interested')) as overdue
    FROM users u 
    WHERE u.role = 'sales' AND COALESCE(u.sales_active, TRUE) = TRUE
    ORDER BY converted_leads DESC, total_leads DESC
")->fetchAll();

// ---- City-wise Leads ----
$city_breakdown = $pdo->query("
    SELECT city, COUNT(*) as cnt 
    FROM sales_leads 
    WHERE city IS NOT NULL AND city != '' 
    GROUP BY city 
    ORDER BY cnt DESC 
    LIMIT 8
")->fetchAll();

// ---- Recent Activity (Notes) ----
$recent_activity = $pdo->query("
    SELECT n.*, u.name as user_name, l.name as lead_name, l.id as lead_id
    FROM sales_lead_notes n 
    LEFT JOIN users u ON n.user_id = u.id 
    LEFT JOIN sales_leads l ON n.lead_id = l.id 
    ORDER BY n.id DESC 
    LIMIT 10
")->fetchAll();

// ---- Today's Followups List ----
$today_list = $pdo->query("
    SELECT l.*, u.name as assigned_name 
    FROM sales_leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    WHERE l.follow_up_date = CURRENT_DATE 
    AND l.status NOT IN ('converted','closed','not_interested')
    ORDER BY l.priority DESC, l.id DESC
")->fetchAll();

// ---- Overdue List ----
$overdue_list = $pdo->query("
    SELECT l.*, u.name as assigned_name 
    FROM sales_leads l 
    LEFT JOIN users u ON l.assigned_to = u.id 
    WHERE l.follow_up_date < CURRENT_DATE 
    AND l.status NOT IN ('converted','closed','not_interested')
    ORDER BY l.follow_up_date ASC
    LIMIT 10
")->fetchAll();

include 'header.php';
?>

<style>
    .stat-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        transition: all 0.25s;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    }
    .stat-card .icon {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; margin-bottom: 12px;
    }
    .stat-card .label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        letter-spacing: 0.6px;
    }
    .stat-card .value {
        font-size: 1.8rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
        margin-top: 4px;
    }
    .sc-blue .icon { background: #dbeafe; color: #1e40af; }
    .sc-green .icon { background: #d1fae5; color: #065f46; }
    .sc-orange .icon { background: #fef3c7; color: #b45309; }
    .sc-red .icon { background: #fee2e2; color: #b91c1c; }
    .sc-purple .icon { background: #ddd6fe; color: #5b21b6; }

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
        font-size: 18px; flex-shrink: 0;
    }
    .qa-btn.primary { border-color: #2563eb; background: #eff6ff; }
    .qa-btn.primary .qa-icon { background: #2563eb; color: #fff; }
    .qa-btn.success { border-color: #10b981; background: #f0fdf4; }
    .qa-btn.success .qa-icon { background: #10b981; color: #fff; }
    .qa-btn.warning { border-color: #f59e0b; background: #fffbeb; }
    .qa-btn.warning .qa-icon { background: #f59e0b; color: #fff; }

    .panel-card {
        background: #fff;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }
    .panel-card h6 {
        font-weight: 800;
        font-size: 0.95rem;
        margin-bottom: 16px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f1f5f9;
        display: flex; align-items: center; gap: 8px;
    }
    .panel-card h6 i { color: #2563eb; }

    .perf-table {
        width: 100%;
        font-size: 0.82rem;
        border-collapse: collapse;
    }
    .perf-table th {
        background: #f8fafc;
        color: #475569;
        font-size: 0.65rem;
        text-transform: uppercase;
        padding: 9px 8px;
        letter-spacing: 0.5px;
        font-weight: 700;
        text-align: left;
        border-bottom: 2px solid #e2e8f0;
    }
    .perf-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .perf-table tr:hover td { background: #f8fafc; }

    .progress-bar-custom {
        height: 6px;
        background: #f1f5f9;
        border-radius: 10px;
        overflow: hidden;
        margin-top: 2px;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #2563eb, #3b82f6);
        border-radius: 10px;
    }

    .status-pill {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: capitalize;
        white-space: nowrap;
    }
    .st-new { background: #dbeafe; color: #1e40af; }
    .st-contacted { background: #fef3c7; color: #92400e; }
    .st-interested { background: #ddd6fe; color: #5b21b6; }
    .st-not_interested { background: #fee2e2; color: #991b1b; }
    .st-converted { background: #d1fae5; color: #065f46; }
    .st-closed { background: #e2e8f0; color: #475569; }

    .activity-item {
        display: flex;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .activity-item:last-child { border-bottom: none; }
    .activity-icon {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; flex-shrink: 0;
        background: #eff6ff; color: #2563eb;
    }
    .activity-icon.status_change { background: #fef3c7; color: #b45309; }
    .activity-icon.initial { background: #d1fae5; color: #059669; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-0"><i class="fas fa-chart-line me-2 text-primary"></i> Sales CRM Dashboard</h3>
            <p class="text-muted small mb-0">Complete overview of sales team & leads</p>
        </div>
        <a href="admin_sales_users.php" class="btn btn-outline-primary rounded-pill px-4">
            <i class="fas fa-user-tie me-1"></i> Manage Sales Users
        </a>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="sales_lead_add.php" class="qa-btn primary">
            <div class="qa-icon"><i class="fas fa-plus"></i></div>
            <div>
                <div>Add Lead</div>
                <small style="font-weight:500; opacity:0.7;">Create manually</small>
            </div>
        </a>
        <a href="sales_lead_upload.php" class="qa-btn success">
            <div class="qa-icon"><i class="fas fa-file-upload"></i></div>
            <div>
                <div>Bulk Upload</div>
                <small style="font-weight:500; opacity:0.7;">CSV import</small>
            </div>
        </a>
        <a href="admin_sales_leads.php" class="qa-btn">
            <div class="qa-icon" style="background:#f1f5f9; color:#1e3a8a;"><i class="fas fa-list"></i></div>
            <div>
                <div>All Leads</div>
                <small style="font-weight:500; opacity:0.7;"><?= $total_leads ?> total</small>
            </div>
        </a>
        <a href="admin_sales_leads.php?followup=today" class="qa-btn warning">
            <div class="qa-icon"><i class="fas fa-bell"></i></div>
            <div>
                <div>Today's Follow-ups</div>
                <small style="font-weight:500; opacity:0.7;"><?= $today_followups ?> pending</small>
            </div>
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card sc-blue">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="label">Total Leads</div>
                <div class="value"><?= number_format($total_leads) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card sc-green">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="label">Converted</div>
                <div class="value"><?= number_format($converted) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card sc-orange">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="label">Today's Follow-ups</div>
                <div class="value"><?= number_format($today_followups) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card sc-red">
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="label">Overdue</div>
                <div class="value"><?= number_format($overdue) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Sales Team Performance -->
        <div class="col-lg-8">
            <div class="panel-card">
                <h6><i class="fas fa-trophy"></i> Sales Team Performance</h6>
                <?php if (empty($sales_users)): ?>
                    <p class="text-muted small text-center py-3">No sales users yet. <a href="admin_sales_users.php">Create one →</a></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Sales User</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Converted</th>
                                    <th class="text-center">Active</th>
                                    <th class="text-center">Today</th>
                                    <th class="text-center">Overdue</th>
                                    <th>Conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales_users as $s): 
                                    $rate = $s['total_leads'] > 0 ? round(($s['converted_leads'] / $s['total_leads']) * 100) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <a href="admin_sales_leads.php?assigned=<?= $s['id'] ?>" style="font-weight:700; color:#0f172a; text-decoration:none;">
                                                <?= htmlspecialchars($s['name']) ?>
                                            </a>
                                            <div style="font-size:0.7rem; color:#64748b;"><?= htmlspecialchars($s['email']) ?></div>
                                        </td>
                                        <td class="text-center fw-bold"><?= $s['total_leads'] ?></td>
                                        <td class="text-center"><span class="badge bg-success"><?= $s['converted_leads'] ?></span></td>
                                        <td class="text-center"><?= $s['active_leads'] ?></td>
                                        <td class="text-center"><?php if($s['today_fup'] > 0): ?><span class="badge bg-warning text-dark"><?= $s['today_fup'] ?></span><?php else: ?>0<?php endif; ?></td>
                                        <td class="text-center"><?php if($s['overdue'] > 0): ?><span class="badge bg-danger"><?= $s['overdue'] ?></span><?php else: ?>0<?php endif; ?></td>
                                        <td style="min-width:100px;">
                                            <div style="font-size:0.72rem; font-weight:700; color:#059669;"><?= $rate ?>%</div>
                                            <div class="progress-bar-custom">
                                                <div class="progress-fill" style="width:<?= $rate ?>%;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Today's Follow-ups -->
            <div class="panel-card">
                <h6><i class="fas fa-bell"></i> Today's Follow-ups (<?= count($today_list) ?>)</h6>
                <?php if (empty($today_list)): ?>
                    <p class="text-muted small text-center py-2">No follow-ups scheduled for today.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="perf-table">
                            <thead>
                                <tr>
                                    <th>Lead</th>
                                    <th>Phone</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_list as $l): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($l['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($l['phone']) ?></td>
                                        <td style="font-size:0.78rem;"><?= htmlspecialchars($l['assigned_name'] ?? 'Unassigned') ?></td>
                                        <td><span class="status-pill st-<?= $l['status'] ?>"><?= ucfirst(str_replace('_',' ',$l['status'])) ?></span></td>
                                        <td>
                                            <a href="sales_lead_view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Status Breakdown -->
            <div class="panel-card">
                <h6><i class="fas fa-chart-pie"></i> Lead Status</h6>
                <?php 
                $all_statuses = ['new', 'contacted', 'interested', 'not_interested', 'converted', 'closed'];
                foreach ($all_statuses as $st): 
                    $cnt = $status_data[$st] ?? 0;
                    $pct = $total_leads > 0 ? round(($cnt / $total_leads) * 100) : 0;
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="status-pill st-<?= $st ?>"><?= ucfirst(str_replace('_',' ',$st)) ?></span>
                            <strong style="font-size:0.85rem;"><?= $cnt ?></strong>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- City Breakdown -->
            <?php if (!empty($city_breakdown)): ?>
            <div class="panel-card">
                <h6><i class="fas fa-map-marker-alt"></i> Top Cities</h6>
                <?php 
                $max_city = max(array_column($city_breakdown, 'cnt'));
                foreach ($city_breakdown as $c): 
                    $pct = $max_city > 0 ? round(($c['cnt'] / $max_city) * 100) : 0;
                ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:0.82rem; font-weight:600;"><?= htmlspecialchars($c['city']) ?></span>
                            <strong style="font-size:0.82rem;"><?= $c['cnt'] ?></strong>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width:<?= $pct ?>%; background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="panel-card">
                <h6><i class="fas fa-history"></i> Recent Activity</h6>
                <div style="max-height:300px; overflow-y:auto;">
                    <?php if (empty($recent_activity)): ?>
                        <p class="text-muted small text-center py-2">No activity yet.</p>
                    <?php else: foreach ($recent_activity as $a): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?= $a['note_type'] ?>">
                                <i class="fas <?= $a['note_type']=='status_change' ? 'fa-exchange-alt' : ($a['note_type']=='initial' ? 'fa-plus' : 'fa-comment') ?>"></i>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-size:0.8rem; font-weight:600; color:#0f172a;">
                                    <?= htmlspecialchars($a['lead_name'] ?? 'Lead') ?>
                                </div>
                                <div style="font-size:0.75rem; color:#475569; word-break:break-word;">
                                    <?= htmlspecialchars(mb_substr($a['note'] ?? '', 0, 80)) ?><?= strlen($a['note'] ?? '') > 80 ? '...' : '' ?>
                                </div>
                                <div style="font-size:0.68rem; color:#94a3b8; margin-top:2px;">
                                    <?= htmlspecialchars($a['user_name'] ?? 'System') ?> · <?= date('d M, h:i A', strtotime($a['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
