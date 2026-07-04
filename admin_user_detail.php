<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

$user_id = $_GET['id'] ?? 0;
if(!$user_id) { die("User ID required"); }

// Fetch user with subscription info
$stmt = $pdo->prepare("SELECT 
                        u.*,
                        s.id as sub_id,
                        s.package_id,
                        s.amount as sub_amount,
                        s.payment_method,
                        s.utr,
                        s.status as sub_status,
                        s.start_date,
                        s.end_date,
                        s.created_at as sub_created_at,
                        p.name as package_name,
                        p.duration_months
                      FROM users u
                      LEFT JOIN (
                          SELECT * FROM subscriptions WHERE status = 'active' AND end_date >= CURRENT_DATE ORDER BY id DESC LIMIT 1
                      ) s ON u.id = s.user_id
                      LEFT JOIN packages p ON s.package_id = p.id
                      WHERE u.id = ?");
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
                <tr><th>Coins</th><td><strong><?= (int)($user['coins'] ?? 0) ?></strong></td></tr>
                <tr><th>Registered (Free Signup)</th><td><strong><?= date('d M Y, h:i A', strtotime($user['created_at'])) ?></strong></td></tr>
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

    <!-- Subscription Details -->
    <div class="mt-4">
        <h5><i class="fas fa-crown me-2"></i>Subscription / Plan</h5>
        <?php if($user['package_name']): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <tr><th>Package</th><td><span class="badge bg-success"><?= htmlspecialchars($user['package_name']) ?></span></td></tr>
                    <tr><th>Duration</th><td><?= $user['duration_months'] ?> Months</td></tr>
                    <tr><th>Amount Paid</th><td>₹ <?= indianCurrencyFormat($user['sub_amount']) ?></td></tr>
                    <tr><th>Payment Method</th><td><?= htmlspecialchars($user['payment_method'] ?? 'N/A') ?></td></tr>
                    <tr><th>UTR</th><td><?= htmlspecialchars($user['utr'] ?? 'N/A') ?></td></tr>
                    <tr><th>Status</th><td><span class="badge bg-success">Active</span></td></tr>
                    <tr><th>Purchase Date (Request)</th><td><?= date('d M Y, h:i A', strtotime($user['sub_created_at'])) ?></td></tr>
                    <tr><th>Activation Date</th><td><?= date('d M Y, h:i A', strtotime($user['start_date'])) ?></td></tr>
                    <tr><th>Expiry Date</th><td><?= date('d M Y, h:i A', strtotime($user['end_date'])) ?></td></tr>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-secondary">
                <i class="fas fa-info-circle"></i> This user is on <strong>Free Plan</strong>. 
                Registered on <?= date('d M Y, h:i A', strtotime($user['created_at'])) ?>.
            </div>
        <?php endif; ?>
    </div>

    <a href="admin_dashboard.php" class="btn btn-secondary mt-3">⬅ Back to Dashboard</a>
</div>
<?php include 'footer.php'; ?>
