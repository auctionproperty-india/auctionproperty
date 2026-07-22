<?php
// ============================================================
// 📦 Buy Subscription – Prevent Duplicate
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

// ---- Check if package exists ----
$pkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$pkg->execute([$package_id]);
$package = $pkg->fetch();
if (!$package) {
    die("Invalid package selected.");
}

// ---- Check if user already has a pending subscription for any package ----
$pending_check = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'pending'");
$pending_check->execute([$user_id]);
if ($pending_check->rowCount() > 0) {
    // User already has a pending request – redirect with message
    header("Location: user_packages.php?msg=already_pending");
    exit;
}

// ---- Also check if user already has an active subscription ----
$active_check = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
$active_check->execute([$user_id]);
if ($active_check->rowCount() > 0) {
    header("Location: user_packages.php?msg=already_active");
    exit;
}

// ---- If form submitted ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : $package['price'];
    $utr = trim($_POST['utr'] ?? '');
    $slip_path = '';

    // Handle slip upload
    if (isset($_FILES['slip']) && $_FILES['slip']['error'] == 0) {
        $upload_dir = 'uploads/slips/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION);
        $filename = 'slip_' . time() . '_' . $user_id . '.' . $ext;
        move_uploaded_file($_FILES['slip']['tmp_name'], $upload_dir . $filename);
        $slip_path = $upload_dir . $filename;
    }

    // Insert subscription – status = pending
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (user_id, package_id, amount, payment_method, utr, slip_path, status, start_date, end_date, created_at)
        VALUES (?, ?, ?, 'bank', ?, ?, 'pending', NULL, NULL, NOW())
    ");
    $stmt->execute([$user_id, $package_id, $amount, $utr, $slip_path]);

    // ✅ Redirect to user_packages with success message (prevent duplicate on refresh)
    header("Location: user_packages.php?msg=request_sent");
    exit;
}

// ---- Show form ----
include 'header.php';
?>

<div class="container-fluid">
    <div class="card-premium" style="max-width: 600px; margin: auto;">
        <h4><i class="fas fa-shopping-cart me-2"></i>Buy Package: <?= htmlspecialchars($package['name']) ?></h4>
        <p class="text-muted">Fill the details below to request subscription.</p>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Amount (₹)</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= $package['price'] ?>" required>
                <small class="text-muted">You can edit amount if you have offer.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">UTR / Transaction ID</label>
                <input type="text" name="utr" class="form-control" placeholder="Enter UTR or Payment reference">
            </div>
            <div class="mb-3">
                <label class="form-label">Payment Slip (optional)</label>
                <input type="file" name="slip" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
            </div>
            <button type="submit" class="btn btn-primary w-100">Submit Request</button>
            <a href="user_packages.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
