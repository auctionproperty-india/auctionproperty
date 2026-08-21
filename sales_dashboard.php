<?php
// ============================================================
// sales_dashboard.php – Sales Team Dashboard (Role: sales)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sales') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get sales team record
$stmt = $pdo->prepare("SELECT id, role, manager_id FROM sales_team WHERE user_id = ?");
$stmt->execute([$user_id]);
$sales_user = $stmt->fetch();
if (!$sales_user) {
    die("Sales team record not found.");
}
$team_id = $sales_user['id'];
$role = $sales_user['role'];

// Recursive function to get subordinate team IDs (including self)
function getSubordinateIds($pdo, $team_id) {
    $ids = [$team_id];
    $children = $pdo->prepare("SELECT id FROM sales_team WHERE manager_id = ?");
    $children->execute([$team_id]);
    while ($child = $children->fetch()) {
        $ids = array_merge($ids, getSubordinateIds($pdo, $child['id']));
    }
    return $ids;
}
$sub_ids = getSubordinateIds($pdo, $team_id);

// Get leads assigned to this team
$placeholders = implode(',', array_fill(0, count($sub_ids), '?'));
$sql = "SELECT l.*, u.name as assigned_name FROM leads l 
        JOIN sales_team st ON l.assigned_to = st.id 
        JOIN users u ON st.user_id = u.id 
        WHERE l.assigned_to IN ($placeholders) 
        ORDER BY l.created_at DESC LIMIT 20";
$stmt = $pdo->prepare($sql);
$stmt->execute($sub_ids);
$leads = $stmt->fetchAll();

// Stats
$total_leads = count($leads);
$new_leads = 0;
foreach ($leads as $l) if ($l['status'] == 'New') $new_leads++;

// Team members under this user
$stmt = $pdo->prepare("SELECT st.*, u.name, u.email FROM sales_team st JOIN users u ON st.user_id = u.id WHERE st.manager_id = ?");
$stmt->execute([$team_id]);
$team_members = $stmt->fetchAll();

include 'header.php';
?>
<div class="container-fluid mt-4">
    <h1>📊 Sales Dashboard</h1>
    <div class="row">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Leads</h5>
                    <h2><?= $total_leads ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success mb-3">
                <div class="card-body">
                    <h5 class="card-title">New Leads</h5>
                    <h2><?= $new_leads ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info mb-3">
                <div class="card-body">
                    <h5 class="card-title">Your Role</h5>
                    <h2><?= $role ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-3">
                <div class="card-body">
                    <h5 class="card-title">Team Size</h5>
                    <h2><?= count($team_members) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <a href="sales_lead_upload.php" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Leads</a>
        <a href="sales_lead_add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Lead</a>
        <a href="sales_leads.php" class="btn btn-info"><i class="fas fa-list"></i> View All Leads</a>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Recent Leads</h5>
        </div>
        <div class="card-body">
            <?php if (count($leads) > 0): ?>
                <table class="table table-striped">
                    <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>City</th><th>Assigned To</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($leads as $idx => $l): ?>
                        <tr>
                            <td><?= $idx+1 ?></td>
                            <td><?= htmlspecialchars($l['name']) ?></td>
                            <td><?= htmlspecialchars($l['phone']) ?></td>
                            <td><?= htmlspecialchars($l['city']) ?></td>
                            <td><?= htmlspecialchars($l['assigned_name']) ?></td>
                            <td><span class="badge bg-<?= $l['status']=='New'?'primary':($l['status']=='Contacted'?'warning':'success') ?>"><?= $l['status'] ?></span></td>
                            <td><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No leads yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($team_members) > 0): ?>
    <div class="card mt-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Your Team</h5>
        </div>
        <div class="card-body">
            <table class="table table-sm">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
                <tbody>
                    <?php foreach ($team_members as $tm): ?>
                    <tr>
                        <td><?= htmlspecialchars($tm['name']) ?></td>
                        <td><?= htmlspecialchars($tm['email']) ?></td>
                        <td><?= $tm['role'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
