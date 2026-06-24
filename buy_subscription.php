<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$package_id = $_GET['package_id'] ?? $_POST['package_id'] ?? 0;
if(!$package_id) { header("Location: user_dashboard.php"); exit; }

$pkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
$pkg->execute([$package_id]);
$pkg = $pkg->fetch();
if(!$pkg) { die("Invalid package"); }

$existing = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND package_id = ? AND status = 'active'");
$existing->execute([$user_id, $package_id]);
if($existing->rowCount() > 0) {
    header("Location: user_dashboard.php?msg=already_active");
    exit;
}

// ---- Fetch Company Bank Details ----
$bank_name = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='company_bank_name'")->fetchColumn();
$account = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='company_account_number'")->fetchColumn();
$ifsc = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='company_ifsc'")->fetchColumn();
$branch = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='company_branch'")->fetchColumn();
$qr = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='company_qr_code'")->fetchColumn();

// ---- User Wallet Balance ----
$wallet_balance = getUserWalletBalance($pdo, $user_id);

$message = '';
$payment_success = false;

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    $payment_method = $_POST['payment_method'] ?? 'bank';
    $utr = trim($_POST['utr'] ?? '');
    $slip_path = '';

    // ---- WALLET PAYMENT ----
    if($payment_method == 'wallet') {
        if($wallet_balance < $pkg['price']) {
            $message = "<div class='alert alert-danger'>❌ Insufficient wallet balance. Your balance: ₹" . indianCurrencyFormat($wallet_balance) . "</div>";
        } else {
            $deducted = debitWallet($pdo, $user_id, $pkg['price'], "Subscription to " . $pkg['name'], $package_id);
            if($deducted) {
                $end_date = date('Y-m-d', strtotime("+{$pkg['duration_months']} months"));
                $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, property_id, amount, payment_method, utr, slip_path, status, start_date, end_date) VALUES (?, ?, NULL, ?, 'wallet', '', '', 'active', CURRENT_DATE, ?)");
                $stmt->execute([$user_id, $package_id, $pkg['price'], $end_date]);
                addAccountEntry($pdo, 'income', $pkg['price'], "Wallet payment for subscription from user ID $user_id", 'Subscription');
                header("Location: user_dashboard.php?msg=wallet_paid");
                exit;
            } else {
                $message = "<div class='alert alert-danger'>❌ Wallet deduction failed.</div>";
            }
        }
    } else {
        // ---- BANK/UPI PAYMENT ----
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
            header("Location: user_dashboard.php?msg=request_sent");
            exit;
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body{background:#f4f7fc;}
        .container{max-width:750px;margin-top:60px;}
        .card{border-radius:24px;border:none;box-shadow:0 10px 30px rgba(0,0,0,0.05);}
        .qr-box {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 2px dashed #d1d5db;
        }
        .qr-box img {
            max-height: 220px;
            border-radius: 12px;
            background: white;
            padding: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .upi-apps {
            margin-top: 12px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .upi-apps span {
            background: white;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }
        .wallet-box{background:#f0fdf4;border:2px solid #10b981;border-radius:12px;padding:15px;}
    </style>
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

        <!-- Wallet Balance Display -->
        <div class="wallet-box mb-3 d-flex justify-content-between align-items-center">
            <span><i class="fas fa-wallet"></i> Your Wallet Balance:</span>
            <span class="fw-bold fs-5 text-success">₹ <?= indianCurrencyFormat($wallet_balance) ?></span>
        </div>

        <hr>

        <!-- ===== QR CODE & BANK DETAILS ===== -->
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <h6><i class="fas fa-qrcode me-2" style="color:#2563eb;"></i>Scan & Pay (UPI)</h6>
                <div class="qr-box">
                    <?php if($qr && file_exists($qr)): ?>
                        <img src="<?= $qr ?>" alt="UPI QR Code">
                        <div class="upi-apps">
                            <span><i class="fas fa-google-pay"></i> GPay</span>
                            <span><i class="fas fa-mobile-alt"></i> PhonePe</span>
                            <span><i class="fas fa-amazon"></i> Amazon Pay</span>
                            <span>BHIM</span>
                            <span>Paytm</span>
                        </div>
                        <small class="text-muted d-block mt-2">Scan with any UPI app to pay</small>
                    <?php else: ?>
                        <p class="text-muted">QR Code not set. Please contact admin.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <?php if($bank_name && $account && $ifsc): ?>
                <h6><i class="fas fa-university me-2" style="color:#2563eb;"></i>Bank Transfer Details</h6>
                <div class="p-3" style="background:#f8fafc; border-radius:12px;">
                    <p class="small mb-1"><strong>Bank:</strong> <?= htmlspecialchars($bank_name) ?></p>
                    <p class="small mb-1"><strong>A/c No.:</strong> <?= htmlspecialchars($account) ?></p>
                    <p class="small mb-1"><strong>IFSC:</strong> <?= htmlspecialchars($ifsc) ?></p>
                    <p class="small"><strong>Branch:</strong> <?= htmlspecialchars($branch) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?= $message ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="package_id" value="<?= $package_id ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Payment Method</label>
                <select name="payment_method" id="payment_method" class="form-control" onchange="toggleFields()">
                    <option value="bank">🏦 Bank Transfer (Upload Slip)</option>
                    <option value="wallet">💰 Pay from Wallet (Instant)</option>
                    <option value="online">💳 Online Payment (Coming Soon)</option>
                </select>
            </div>
            <div id="bank_fields">
                <div class="mb-3">
                    <label class="form-label fw-semibold">UTR Number *</label>
                    <input type="text" name="utr" class="form-control" placeholder="e.g. 123456789012">
                    <small class="text-muted">Your bank transaction reference number.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Payment Slip (Screenshot) *</label>
                    <input type="file" name="slip" class="form-control" accept="image/*">
                    <small class="text-muted">Upload screenshot of your bank payment.</small>
                </div>
            </div>
            <button type="submit" name="submit_payment" class="btn btn-primary w-100">Submit Request</button>
        </form>
        <a href="user_dashboard.php" class="btn btn-link mt-2 text-center">⬅ Cancel</a>
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
