<?php
// ============================================================
// 💳 User Payment History
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🔥 भारतीय समय (IST) सेट करें
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all subscriptions/payments for this user
$stmt = $pdo->prepare("
    SELECT s.*, p.name as package_name, p.is_ba_package 
    FROM subscriptions s 
    JOIN packages p ON s.package_id = p.id 
    WHERE s.user_id = ? 
    ORDER BY s.id DESC
");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll();

include 'header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold m-0"><i class="fas fa-history text-primary"></i> My Payment History</h3>
        <a href="user_dashboard.php" class="btn btn-outline-secondary fw-bold" style="border-radius:20px;"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Date & Time (IST)</th>
                            <th>Package Name</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Rejection Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($history) > 0): ?>
                            <?php foreach($history as $h): ?>
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <!-- 🔥 यहाँ टाइम फॉर्मेट हो रहा है -->
                                    <?= date('d M Y, h:i A', strtotime($h['created_at'])) ?>
                                </td>
                                <td><?= htmlspecialchars($h['package_name']) ?> <?= $h['is_ba_package'] ? '<span class="badge bg-primary ms-1">BA</span>' : '' ?></td>
                                <td class="fw-bold">₹<?= number_format($h['amount'], 2) ?></td>
                                <td>
                                    <?php if($h['status'] == 'active'): ?>
                                        <span class="badge bg-success px-3 py-2">Approved</span>
                                    <?php elseif($h['status'] == 'rejected'): ?>
                                        <span class="badge bg-danger px-3 py-2">Rejected</span>
                                    <?php elseif($h['status'] == 'pending'): ?>
                                        <span class="badge bg-warning text-dark px-3 py-2">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary px-3 py-2"><?= ucfirst($h['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-danger fw-bold">
                                    <?= $h['status'] == 'rejected' ? htmlspecialchars($h['rejection_reason'] ?: 'Not specified') : '-' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted fw-bold">No payment history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
