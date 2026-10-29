<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Check if already applied
$check = $pdo->prepare("SELECT ba_status FROM users WHERE id = ?");
$check->execute([$user_id]);
$status = $check->fetchColumn();

if ($status == 'active' || $status == 'pending') {
    header("Location: user_dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update status to pending
    $stmt = $pdo->prepare("UPDATE users SET ba_status = 'pending' WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        $message = "<div class='alert alert-success'>✅ Your application has been submitted! Please wait for admin approval.</div>";
        // Redirect after 2 seconds
        header("refresh:2;url=user_dashboard.php");
    }
}

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-lg" style="border-radius: 20px;">
                <div class="card-header bg-primary text-white text-center p-4" style="border-radius: 20px 20px 0 0;">
                    <h4 class="mb-0 fw-bold"><i class="fas fa-handshake me-2"></i> Apply for Business Associate (BA)</h4>
                    <p class="mb-0 opacity-75">Start earning by referring users to our platform.</p>
                </div>
                <div class="card-body p-4 text-center">
                    <?= $message ?>
                    <div class="mb-4">
                        <i class="fas fa-users fa-5x text-primary mb-3 opacity-50"></i>
                        <h5 class="fw-bold">Benefits of becoming a BA</h5>
                        <ul class="list-unstyled text-start d-inline-block mt-2 text-muted">
                            <li><i class="fas fa-check-circle text-success me-2"></i> Earn Direct & Team Commissions</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Get your personal Referral Link</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Track your Team & Income from Dashboard</li>
                        </ul>
                    </div>
                    
                    <form method="POST">
                        <button type="submit" class="btn btn-warning btn-lg fw-bold px-5" style="border-radius: 50px;">
                            Submit Application <i class="fas fa-paper-plane ms-2"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
