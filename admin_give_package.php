<?php
// ============================================================
// 🎁 Admin – Give Free Package to User (Without Payment & No Income)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}
if (!hasEditPermission('users', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to edit users.</div>");
}

$message = '';
$message_type = '';

// 1. Get User ID from URL
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    header("Location: users.php");
    exit;
}

// 2. Fetch the specific user
$user_stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    die("<div class='alert alert-danger m-5'>❌ User not found!</div>");
}

// ---- Handle Form Submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_package'])) {
    $package_id = (int)$_POST['package_id'];

    if ($package_id > 0) {
        try {
            $pdo->beginTransaction();

            // Get Package Duration
            $pkg_stmt = $pdo->prepare("SELECT name, duration_months FROM packages WHERE id = ?");
            $pkg_stmt->execute([$package_id]);
            $package = $pkg_stmt->fetch();
            
            $duration = $package['duration_months'] ?? 1;
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime("+$duration months"));

            // Cancel any existing active/pending subscriptions for this user
            $pdo->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = ? AND status IN ('active', 'pending')")
                ->execute([$user_id]);

            // 🔥 Insert New Subscription with Amount = 0
            // ⚠️ NOTE: We are NOT calling distributeIncome() here. 
            // This ensures that NO upline/sponsor gets any commission from this free assignment.
            $insert = $pdo->prepare("
                INSERT INTO subscriptions (user_id, package_id, amount, status, start_date, end_date, created_at) 
                VALUES (?, ?, 0.00, 'active', ?, ?, CURRENT_TIMESTAMP)
            ");
            $insert->execute([$user_id, $package_id, $start_date, $end_date]);

            // Update User's activation_date
            $pdo->prepare("UPDATE users SET activation_date = ? WHERE id = ?")
                ->execute([$start_date, $user_id]);

            $pdo->commit();
            $message = "✅ Package '{$package['name']}' successfully assigned to <b>{$user['name']}</b> for free! (No income distributed to uplines)";
            $message_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ Error: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = "❌ Please select a valid Package.";
        $message_type = "danger";
    }
}

// ---- Fetch Packages ----
$packages = $pdo->query("SELECT id, name, price, duration_months FROM packages ORDER BY display_order ASC")->fetchAll();

include 'header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-dark fw-bold"><i class="fas fa-gift me-2"></i> Give Free Package to User</h3>
        <a href="users.php" class="btn btn-outline-secondary rounded-pill px-4">⬅ Back to Users</a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-primary text-white rounded-top-4">
                    <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i> Assign Package Without Payment</h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-warning py-2" style="font-size:0.85rem;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <b>Important:</b> This is a free assignment. No upline/sponsor will receive any income from this transaction.
                    </div>

                    <form method="POST">
                        <input type="hidden" name="assign_package" value="1">
                        
                        <!-- 🔥 Display ONLY the selected user -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Selected User</label>
                            <input type="text" class="form-control bg-light" 
                                   value="#<?= $user['id'] ?> - <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)" 
                                   readonly>
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Select Package to Assign</label>
                            <select name="package_id" class="form-select" required>
                                <option value="">-- Choose Package --</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?= $pkg['id'] ?>">
                                        <?= htmlspecialchars($pkg['name']) ?> (₹<?= number_format($pkg['price']) ?>) - <?= $pkg['duration_months'] ?> Months
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success w-100 rounded-pill py-2">
                            <i class="fas fa-check-circle me-2"></i> Assign Package for Free
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
