<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$package_id = $_GET['package_id'] ?? $_POST['package_id'] ?? 0;

if(!$package_id) { header("Location: dashboard.php"); exit; }

$pkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$pkg->execute([$package_id]);
$pkg = $pkg->fetch();
if(!$pkg) { die("Invalid package"); }

// Check if user already has active subscription for this package
$existing = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND package_id = ? AND status = 'active'");
$existing->execute([$user_id, $package_id]);
if($existing->rowCount() > 0) {
    header("Location: dashboard.php?msg=already_active");
    exit;
}

$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    $payment_method = $_POST['payment_method'] ?? 'bank';
    $utr = trim($_POST['utr'] ?? '');
    $slip_path = '';

    if($payment_method == 'bank') {
        if(empty($utr)) {
            $message = "<div class='alert alert-danger'>❌ Please enter UTR number.</div>";
        } elseif(!isset($_FILES['slip']) || $_FILES['slip']['error'] != 0) {
            $message = "<div class='alert alert-danger'>❌ Please upload a payment slip image.</div>";
        } else {
            $upload_dir = 'uploads/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['slip']['name'], PATHINFO_EXTENSION);
            $filename = 'slip_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            move_uploaded_file($_FILES['slip']['tmp_name'], $upload_dir . $filename);
            $slip_path = $upload_dir . $filename;
        }
    }

    if(empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, property_id, amount, payment_method, utr, slip_path, status) VALUES (?, ?, NULL, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $package_id, $pkg['price'], $payment_method, $utr, $slip_path]);
        header("Location: dashboard.php?msg=request_sent");
        exit;
    }
}

$display_price = $pkg['discount_price'] ?? null;
$regular_price = $pkg['price'];
$show_discount = $display_price && $display_price < $regular_price;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Buy Subscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#f4f7fc;}.container{max-width:600px;margin-top:80px;}.card{border-radius:20px;border:none;box-shadow:0 10px 30px rgba(0,0,0,0.05);}</style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-3">📦 Confirm Subscription</h3>
        <p><strong>Package:</strong> <?= htmlspecialchars($pkg['name']) ?></p>
        <p><strong>Duration:</strong> <?= $pkg['duration_months'] ?> Months</p>
        <p>
            <strong>Price:</strong><br>
            <?php if($show_discount): ?>
                <span style="text-decoration:line-through; color:#999;">₹ <?= indianCurrencyFormat($regular_price) ?></span>
                <span class="text-success fw-bold fs-4">₹ <?= indianCurrencyFormat($display_price) ?></span>
            <?php else: ?>
                <span class="fw-bold fs-4">₹ <?= indianCurrencyFormat($regular_price) ?></span>
            <?php endif; ?>
        </p>
        <hr>
        <?= $message ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="package_id" value="<?= $package_id ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Payment Method</label>
                <select name="payment_method" id="payment_method" class="form-control" onchange="toggleFields()">
                    <option value="bank">🏦 Bank Transfer (Upload Slip)</option>
                    <option value="online">💳 Online Payment (Coming Soon)</option>
                </select>
            </div>
            <div id="bank_fields">
                <div class="mb-3">
                    <label class="form-label fw-semibold">UTR Number *</label>
                    <input type="text" name="utr" class="form-control" placeholder="e.g. 123456789012" required>
                    <small class="text-muted">Your bank transaction reference number.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Payment Slip (Screenshot) *</label>
                    <input type="file" name="slip" class="form-control" accept="image/*" required>
                    <small class="text-muted">Upload screenshot of your bank payment.</small>
                </div>
            </div>
            <button type="submit" name="submit_payment" class="btn btn-primary w-100">Submit Request</button>
        </form>
        <a href="dashboard.php" class="btn btn-link mt-2 text-center">⬅ Cancel</a>
    </div>
</div>
<script>
    function toggleFields() {
        var method = document.getElementById('payment_method').value;
        var bankDiv = document.getElementById('bank_fields');
        if(method == 'bank') {
            bankDiv.style.display = 'block';
            bankDiv.querySelectorAll('input').forEach(el => el.required = true);
        } else {
            bankDiv.style.display = 'none';
            bankDiv.querySelectorAll('input').forEach(el => el.required = false);
        }
    }
    toggleFields();
</script>
</body>
</html>
