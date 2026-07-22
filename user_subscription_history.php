<?php
// ============================================================
// 📋 User Subscription History – Safe Date Formatting
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
include 'header.php';

// ---- Safe Date Formatter (if not defined in functions.php) ----
if (!function_exists('safeDateFormat')) {
    function safeDateFormat($dateStr) {
        if (empty($dateStr) || strtotime($dateStr) === false) {
            return 'N/A';
        }
        return date('d M Y', strtotime($dateStr));
    }
}

// ---- Fetch user's subscription history ----
$stmt = $pdo->prepare("
    SELECT 
        s.*,
        p.name as package_name,
        s.start_date,
        s.end_date,
        s.created_at as request_date,
        s.updated_at as action_date
    FROM subscriptions s
    LEFT JOIN packages p ON s.package_id = p.id
    WHERE s.user_id = ?
    ORDER BY s.id DESC
");
$stmt->execute([$user_id]);
$subscriptions = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="card-premium">
        <h4><i class="fas fa-history me-2"></i>Your Subscription Requests</h4>
        <p class="text-muted">All your subscription requests and their status.</p>

        <?php if (empty($subscriptions)): ?>
            <div class="alert alert-info">You have not made any subscription request yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment Method</th>
                            <th>UTR</th>
                            <th>Request Date</th>
                            <th>Activation/Reject Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $sub): ?>
                            <tr>
                                <td><?= htmlspecialchars($sub['package_name']) ?></td>
                                <td>₹<?= number_format($sub['amount'], 2) ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    if ($sub['status'] == 'active') $statusClass = 'badge bg-success';
                                    elseif ($sub['status'] == 'pending') $statusClass = 'badge bg-warning text-dark';
                                    elseif ($sub['status'] == 'rejected') $statusClass = 'badge bg-danger';
                                    else $statusClass = 'badge bg-secondary';
                                    ?>
                                    <span class="<?= $statusClass ?>"><?= ucfirst($sub['status']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($sub['payment_method'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($sub['utr'] ?? 'N/A') ?></td>
                                <td><?= safeDateFormat($sub['request_date']) ?></td>
                                <td>
                                    <?php
                                    $actionDate = null;
                                    if ($sub['status'] == 'active') {
                                        $actionDate = $sub['start_date'];
                                    } elseif ($sub['status'] == 'rejected') {
                                        $actionDate = $sub['action_date'];
                                    } else {
                                        $actionDate = $sub['updated_at'] ?? null;
                                    }
                                    echo safeDateFormat($actionDate);
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
