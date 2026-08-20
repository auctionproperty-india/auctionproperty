<?php
// ============================================================
// 📦 Buy Subscription – Discount Price Pre-filled + Bank Details
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

// ============================================================
// 🔥 Settings से Bank Details और QR Code लें
// ============================================================
$bank_keys = ['company_bank_name', 'company_account_number', 'company_ifsc', 'company_branch', 'default_contact'];
$bank_data = [];
foreach ($bank_keys as $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $bank_data[$key] = $stmt->fetchColumn() ?: '';
}
$qr = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_qr_code'")->fetchColumn();

// ---- If form submitted ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : $display_price;
    $utr = trim($_POST['utr'] ?? '');
    $slip_url = null;

    // ============================================================
    // 🔥 NEW: Upload slip to Supabase Storage (Permanent)
    // ============================================================
    if (isset($_FILES['slip']) && $_FILES['slip']['error'] == UPLOAD_ERR_OK) {
        $slip_url = uploadToSupabase($_FILES['slip'], 'slip');
        if (!$slip_url) {
            // Fallback to local upload if Supabase fails
            $upload_dir = 'uploads/slips/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION);
            $filename = 'slip_' . time() . '_' . $user_id . '.' . $ext;
            move_uploaded_file($_FILES['slip']['tmp_name'], $upload_dir . $filename);
            $slip_url = $upload_dir . $filename;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (user_id, package_id, amount, payment_method, utr, slip_path, status, start_date, end_date, created_at)
        VALUES (?, ?, ?, 'bank', ?, ?, 'pending', NULL, NULL, NOW())
    ");
    $stmt->execute([$user_id, $package_id, $amount, $utr, $slip_url]);

    header("Location: user_packages.php?msg=request_sent");
    exit;
}

// ---- Show form ----
include 'header.php';
?>

<style>
.bank-detail-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 10px;
}
.bank-detail-box .label {
    font-weight: 600;
    color: #475569;
    font-size: 0.9em;
}
.bank-detail-box .value {
    font-weight: 500;
    color: #0f172a;
    font-size: 1em;
}
.qr-box {
    text-align: center;
    padding: 15px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.qr-box img {
    max-height: 200px;
    border-radius: 8px;
}
.payment-section {
    background: #f1f5f9;
    padding: 20px;
    border-radius: 12px;
    margin: 15px 0;
}
</style>

<div class="container-fluid">
    <div class="card-premium" style="max-width: 750px; margin: auto;">
        <h4><i class="fas fa-shopping-cart me-2"></i>Buy Package: <?= htmlspecialchars($package['name']) ?></h4>
        <p class="text-muted">Fill the details below to request subscription.</p>

        <?php if ($display_price < $package['price']): ?>
            <div class="alert alert-info">
                <i class="fas fa-tags"></i> You get a discount! Regular price: ₹<?= number_format($package['price'], 2) ?> → <strong>Pay only ₹<?= number_format($display_price, 2) ?></strong>
            </div>
        <?php endif; ?>

        <!-- 🔥 Bank Details + QR Code Display -->
        <div class="payment-section">
            <div class="row">
                <div class="col-md-7">
                    <h6 class="fw-bold"><i class="fas fa-university me-2"></i>Bank Details</h6>
                    <div class="bank-detail-box">
                        <div><span class="label">Bank Name:</span> <span class="value"><?= htmlspecialchars($bank_data['company_bank_name'] ?: 'Not set') ?></span></div>
                        <div><span class="label">Account Number:</span> <span class="value"><?= htmlspecialchars($bank_data['company_account_number'] ?: 'Not set') ?></span></div>
                        <div><span class="label">IFSC Code:</span> <span class="value"><?= htmlspecialchars($bank_data['company_ifsc'] ?: 'Not set') ?></span></div>
                        <div><span class="label">Branch:</span> <span class="value"><?= htmlspecialchars($bank_data['company_branch'] ?: 'Not set') ?></span></div>
                        <div><span class="label">Contact:</span> <span class="value"><?= htmlspecialchars($bank_data['default_contact'] ?: 'Not set') ?></span></div>
                    </div>
                </div>
                <div class="col-md-5">
                    <h6 class="fw-bold"><i class="fas fa-qrcode me-2"></i>QR Code</h6>
                    <div class="qr-box">
                        <?php if ($qr && file_exists($qr)): ?>
                            <img src="<?= htmlspecialchars($qr) ?>" alt="QR Code">
                            <p class="text-muted small mt-2">Scan to pay via UPI</p>
                        <?php else: ?>
                            <p class="text-muted">No QR code uploaded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <!-- ---- आपका Original Form – बिल्कुल वैसा ही ---- -->
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
                <small class="text-muted">Upload your payment screenshot (JPG, PNG, PDF) – stored permanently</small>
            </div>
            <button type="submit" class="btn btn-primary w-100">Submit Request</button>
            <a href="user_packages.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>
