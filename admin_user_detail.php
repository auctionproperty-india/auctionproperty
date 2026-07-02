<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

$user_id = $_GET['id'] ?? 0;
if(!$user_id) { die("User ID required"); }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if(!$user) { die("User not found"); }

include 'header.php'; 
?>
<div class="card-premium">
    <h4><i class="fas fa-user me-2"></i>User Profile: <?= htmlspecialchars($user['name']) ?></h4>
    <div class="row">
        <div class="col-md-6">
            <h5>Personal Details</h5>
            <table class="table table-bordered">
                <tr><th>Name</th><td><?= htmlspecialchars($user['name']) ?></td></tr>
                <tr><th>Email</th><td><?= $user['email'] ?></td></tr>
                <tr><th>Phone</th><td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td></tr>
                <tr><th>City</th><td><?= htmlspecialchars($user['city'] ?? 'N/A') ?></td></tr>
                <tr><th>State</th><td><?= htmlspecialchars($user['state'] ?? 'N/A') ?></td></tr>
                <tr><th>Registered</th><td><?= date('d M Y', strtotime($user['created_at'])) ?></td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <h5>Bank Details</h5>
            <table class="table table-bordered">
                <tr><th>Bank Name</th><td><?= htmlspecialchars($user['bank_name'] ?? 'N/A') ?></td></tr>
                <tr><th>Account Number</th><td><?= htmlspecialchars($user['account_number'] ?? 'N/A') ?></td></tr>
                <tr><th>IFSC</th><td><?= htmlspecialchars($user['ifsc'] ?? 'N/A') ?></td></tr>
                <tr><th>Branch</th><td><?= htmlspecialchars($user['branch'] ?? 'N/A') ?></td></tr>
            </table>
        </div>
    </div>
    <a href="admin_dashboard.php" class="btn btn-secondary">⬅ Back</a>
</div>
<?php include 'footer.php'; ?>
