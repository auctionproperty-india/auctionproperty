<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

// Explicit columns in query
$sql = "SELECT u.id, u.name, u.email, u.created_at as reg_date, 
        (SELECT s.start_date FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active' ORDER BY s.id LIMIT 1) as activation_date 
        FROM users u WHERE u.referred_by = ? ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$team_members = $stmt->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-users me-2"></i>My Team (Referred Users)</h4>
    <p class="text-muted">Users who joined using your referral link.</p>
    <?php if(count($team_members) > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead><tr><th>Name</th><th>Email</th><th>Registered On</th><th>Activation Date</th></tr></thead>
                <tbody>
                <?php foreach($team_members as $tm): ?>
                    <tr>
                        <td><?= htmlspecialchars($tm['name']) ?></td>
                        <td><?= htmlspecialchars($tm['email']) ?></td>
                        <td><?= date('d M Y', strtotime($tm['reg_date'])) ?></td>
                        <td><?= $tm['activation_date'] ? date('d M Y', strtotime($tm['activation_date'])) : '<span class="text-muted">Not Activated</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">You haven't referred anyone yet. Share your referral link!</p>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
