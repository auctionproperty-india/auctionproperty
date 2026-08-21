<?php
// ============================================================
// sales_leads.php – List All Leads with Hierarchy
// ============================================================

require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sales')) {
    header("Location: login.php");
    exit;
}
include 'header.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Recursive function for subordinates
function getSubordinateIds($pdo, $team_id) {
    $ids = [$team_id];
    $children = $pdo->prepare("SELECT id FROM sales_team WHERE manager_id = ?");
    $children->execute([$team_id]);
    while ($child = $children->fetch()) {
        $ids = array_merge($ids, getSubordinateIds($pdo, $child['id']));
    }
    return $ids;
}

if ($role == 'admin') {
    // Admin sees all leads
    $leads = $pdo->query("SELECT l.*, u.name as assigned_name FROM leads l JOIN sales_team st ON l.assigned_to = st.id JOIN users u ON st.user_id = u.id ORDER BY l.created_at DESC")->fetchAll();
} else {
    // Sales person sees own and subordinates
    $stmt = $pdo->prepare("SELECT id FROM sales_team WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $team_id = $stmt->fetchColumn();
    if (!$team_id) {
        echo "<div class='alert alert-warning'>You are not assigned to any sales team.</div>";
        include 'footer.php';
        exit;
    }
    $sub_ids = getSubordinateIds($pdo, $team_id);
    $placeholders = implode(',', array_fill(0, count($sub_ids), '?'));
    $sql = "SELECT l.*, u.name as assigned_name FROM leads l JOIN sales_team st ON l.assigned_to = st.id JOIN users u ON st.user_id = u.id WHERE l.assigned_to IN ($placeholders) ORDER BY l.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($sub_ids);
    $leads = $stmt->fetchAll();
}
?>
<div class="container-fluid mt-4">
    <h1>📋 All Leads</h1>
    <div class="mb-3">
        <a href="sales_lead_upload.php" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Leads</a>
        <a href="sales_lead_add.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Manual Lead</a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead><tr><th>#</th><th>Name</th><th>Phone</th><th>City</th><th>Assigned To</th><th>Status</th><th>Source</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($leads as $idx => $l): ?>
                <tr>
                    <td><?= $idx+1 ?></td>
                    <td><?= htmlspecialchars($l['name']) ?></td>
                    <td><?= htmlspecialchars($l['phone']) ?></td>
                    <td><?= htmlspecialchars($l['city']) ?></td>
                    <td><?= htmlspecialchars($l['assigned_name']) ?></td>
                    <td><span class="badge bg-<?= $l['status']=='New'?'primary':($l['status']=='Contacted'?'warning':'success') ?>"><?= $l['status'] ?></span></td>
                    <td><?= htmlspecialchars($l['lead_source']) ?></td>
                    <td><?= date('d M Y', strtotime($l['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
