<?php
// ============================================================
// 📦 Buy Subscription – Discount Price Pre-filled
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

// ---- Determine the price to display ----
// Use discount price if available and less than regular price
$display_price = $package['price'];
if (!empty($package['discount_price']) && $package['discount_price'] > 0 && $package['discount_price'] < $package['price']) {
    $display_price = $package['discount_price'];
}

// ---- Check if user already has a pending subscription ----
$pending_check = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'pending'");
$pending_check->execute([$user_id]);
if ($pending_check->rowCount() > 0) {
    header("Location: user_packages.php?msg=already_pending");
    exit;
}

// ---- Check if user already has an active subscription ----
$active_check = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
$active_check->execute([$user_id]);
if ($active_check->rowCount() > 0) {
    header("Location: user_packages.php?msg=already_active");
    exit;
}

// ---- If form submitted ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : $display_price;
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

    // Redirect with success
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

        <?php if ($display_price < $package['price']): ?>
            <div class="alert alert-info">
                <i class="fas fa-tags"></i> You get a discount! Regular price: ₹<?= number_format($package['price'], 2) ?> → <strong>Pay only ₹<?= number_format($display_price, 2) ?></strong>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Amount (₹)</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= number_format($display_price, 2) ?>" required>
                <small class="text-muted">You can edit the amount if you have any special offer.</small>
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
