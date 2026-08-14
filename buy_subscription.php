<?php
// ============================================================
// 📦 बाय सब्सक्रिप्शन – अब Bank Details + Slip Upload वाला पेज
// ============================================================

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// सिर्फ User ही देख सकता है
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- Package ID प्राप्त करें ----
$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;
if ($package_id <= 0) {
    header("Location: user_packages.php?msg=invalid_package");
    exit;
}

// ---- Package की जानकारी लें ----
$stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$stmt->execute([$package_id]);
$package = $stmt->fetch();
if (!$package) {
    header("Location: user_packages.php?msg=package_not_found");
    exit;
}

// ---- Settings से Bank Details और Admin Charge लें ----
$stmt = $pdo->query("SELECT bank_details, admin_charge, scanner_image FROM settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    // अगर settings नहीं हैं तो डिफॉल्ट
    $settings = ['bank_details' => 'Bank Details अपडेट नहीं हैं। कृपया Admin से संपर्क करें।', 'admin_charge' => '0', 'scanner_image' => ''];
}

// ---- चेक करें कि User का पहले से कोई Active या Pending Subscription तो नहीं ----
$stmt = $pdo->prepare("SELECT id, status FROM subscriptions WHERE user_id = ? AND (status = 'active' OR status = 'pending')");
$stmt->execute([$user_id]);
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['status'] == 'active') {
        header("Location: user_packages.php?msg=already_active");
        exit;
    } elseif ($existing['status'] == 'pending') {
        header("Location: user_packages.php?msg=already_pending");
        exit;
    }
}

// ---- अगर फॉर्म सबमिट हुआ (Slip Upload) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $amount = $_POST['amount'] ?? 0;
    $amount = (float) $amount;
    if ($amount <= 0) {
        $error = "❌ कृपया सही राशि दर्ज करें।";
    } else {
        // File Upload
        $slip_path = '';
        if (isset($_FILES['slip_image']) && $_FILES['slip_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/uploads/slips/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['slip_image']['name'], PATHINFO_EXTENSION);
            $filename = 'slip_' . time() . '_' . $user_id . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['slip_image']['tmp_name'], $target)) {
                $slip_path = 'uploads/slips/' . $filename;
            } else {
                $error = "❌ Slip अपलोड करने में त्रुटि।";
            }
        } else {
            $error = "❌ कृपया Slip Image अपलोड करें।";
        }

        // अगर कोई error नहीं तो payment_requests में insert करें
        if (empty($error) && $slip_path) {
            // पहले Subscriptions में pending entry डालें (वैकल्पिक) या सिर्फ payment_requests में
            // हम दोनों करेंगे – payment_requests में भी और subscriptions में भी pending
            try {
                $pdo->beginTransaction();

                // 1. Subscriptions में pending entry
                $start_date = date('Y-m-d');
                $end_date = date('Y-m-d', strtotime("+{$package['duration_months']} months"));
                $sub_stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                $sub_stmt->execute([$user_id, $package_id, $start_date, $end_date]);
                $subscription_id = $pdo->lastInsertId();

                // 2. Payment Requests में entry
                $pay_stmt = $pdo->prepare("INSERT INTO payment_requests (user_id, package_id, amount, slip_image, status, subscription_id, created_at) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
                $pay_stmt->execute([$user_id, $package_id, $amount, $slip_path, $subscription_id]);

                $pdo->commit();
                $success = "✅ आपकी Payment Request सबमिट कर दी गई है। Admin जल्दी ही approve करेंगे।";
                // Success के बाद रीडायरेक्ट करें या मैसेज दिखाएँ – हम यहीं मैसेज दिखाते हैं
                // और आगे का HTML न दिखाएँ
                $show_success = true;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "❌ Payment Request सबमिट करने में त्रुटि: " . $e->getMessage();
            }
        }
    }
}

// ---- पेज शुरू ----
include 'header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-credit-card"></i> Buy Subscription – <?= htmlspecialchars($package['name']) ?></h2>
        <a href="user_packages.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Packages</a>
    </div>

    <?php if (isset($success) && $success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <p><a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a></p>
        <?php include 'footer.php'; exit; ?>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- बायाँ भाग – Package Details और Bank Details -->
        <div class="col-lg-6">
            <div class="card shadow-sm p-4">
                <h5 class="fw-bold">📦 Package Details</h5>
                <ul class="list-unstyled">
                    <li><strong>Package:</strong> <?= htmlspecialchars($package['name']) ?></li>
                    <li><strong>Duration:</strong> <?= $package['duration_months'] ?> Months</li>
                    <li><strong>Price:</strong> ₹ <?= indianCurrencyFormat($package['discount_price'] ?? $package['price']) ?></li>
                    <li><strong>Admin Charge:</strong> ₹ <?= htmlspecialchars($settings['admin_charge'] ?? '0') ?></li>
                </ul>
                <hr>
                <h5 class="fw-bold">🏦 Bank Details</h5>
                <div style="white-space: pre-line;"><?= nl2br(htmlspecialchars($settings['bank_details'])) ?></div>
                <?php if (!empty($settings['scanner_image'])): ?>
                    <div class="mt-3">
                        <label class="fw-bold">Scanner / QR Code:</label><br>
                        <img src="<?= htmlspecialchars($settings['scanner_image']) ?>" style="max-height:200px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.06);">
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- दायाँ भाग – Payment Form (Slip Upload) -->
        <div class="col-lg-6">
            <div class="card shadow-sm p-4">
                <h5 class="fw-bold"><i class="fas fa-upload"></i> Submit Payment Slip</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount Paid (₹)</label>
                        <input type="number" name="amount" class="form-control" placeholder="Enter the amount you paid" required min="1" step="0.01">
                        <small class="text-muted">Please enter the exact amount you transferred.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload Payment Slip / Screenshot</label>
                        <input type="file" name="slip_image" accept="image/*,application/pdf" class="form-control" required>
                        <small class="text-muted">Allowed: JPG, PNG, PDF (Max 5MB)</small>
                    </div>
                    <button type="submit" name="submit_payment" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane"></i> Submit Request
                    </button>
                </form>
                <p class="text-muted small mt-3">* Admin द्वारा पुष्टि होने पर आपकी सब्सक्रिप्शन सक्रिय हो जाएगी।</p>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
