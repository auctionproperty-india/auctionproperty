<?php
session_start();
require_once __DIR__ . '/db.php';

// 1. Settings से Bank Details और अन्य जानकारी लें
$stmt = $pdo->query("SELECT bank_details, admin_charge, scanner_image FROM settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// अगर settings नहीं हैं तो डिफॉल्ट
if (!$settings) {
    $settings = ['bank_details' => 'कृपया Bank Details अपडेट करें।', 'admin_charge' => '0', 'scanner_image' => ''];
}

// 2. Package ID (आपके सिस्टम के अनुसार) – मान लिया GET से आ रहा है
$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

// 3. यदि Slip Upload का फॉर्म सबमिट हुआ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $user_id = $_SESSION['user_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $package_id = $_POST['package_id'] ?? 0;
    
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
        }
    }
    
    // यहाँ आप payment_requests टेबल में insert करें
    $sql = "INSERT INTO payment_requests (user_id, package_id, amount, slip_image, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$user_id, $package_id, $amount, $slip_path]);
    
    if ($result) {
        $success = "✅ आपकी Payment Request सबमिट कर दी गई है। Admin जल्द ही approve करेंगे।";
    } else {
        $error = "❌ Payment Request सबमिट करने में त्रुटि: " . implode(" ", $stmt->errorInfo());
    }
}

// 4. अगर User Logged In नहीं है तो Login पर भेजें
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Instructions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f1f5f9; font-family: 'Segoe UI', system-ui, sans-serif; }
        .payment-container { max-width: 820px; margin: 40px auto; }
        .bank-card { background: white; border-radius: 20px; padding: 30px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); border: 1px solid #e9edf4; }
        .bank-detail-item { border-bottom: 1px solid #f0f2f5; padding: 14px 0; display: flex; align-items: flex-start; }
        .bank-detail-item:last-child { border-bottom: none; }
        .bank-icon { width: 40px; color: #2563eb; font-size: 1.5rem; margin-right: 16px; }
        .bank-label { font-weight: 600; color: #1e293b; min-width: 120px; }
        .bank-value { color: #334155; word-break: break-word; }
        .upload-box { border: 2px dashed #cbd5e1; border-radius: 16px; padding: 30px; text-align: center; background: #fafcff; transition: 0.3s; }
        .upload-box:hover { border-color: #2563eb; background: #f0f7ff; }
        .upload-box i { font-size: 3rem; color: #94a3b8; }
        .btn-primary-custom { background: #2563eb; border: none; padding: 12px 35px; font-weight: 600; border-radius: 50px; }
        .btn-primary-custom:hover { background: #1d4ed8; }
        .alert { border-radius: 12px; }
        .scanner-img { max-width: 100%; max-height: 200px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    </style>
</head>
<body>
<div class="payment-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-credit-card-2-front me-2"></i>Payment Instructions</h2>
        <a href="dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Bank Details Column -->
        <div class="col-lg-6">
            <div class="bank-card h-100">
                <h5 class="fw-bold mb-3"><i class="bi bi-bank me-2"></i>Bank Details</h5>
                <?php
                // मान लिया bank_details में HTML या plain text है – आप nl2br कर सकते हैं
                $bank_lines = explode("\n", $settings['bank_details']);
                foreach ($bank_lines as $line):
                    if (trim($line) == '') continue;
                    // स्मार्ट पार्सिंग – अगर लाइन में ':' है तो label और value अलग करें
                    if (strpos($line, ':') !== false) {
                        list($label, $value) = explode(':', $line, 2);
                        $label = trim($label);
                        $value = trim($value);
                    } else {
                        $label = 'Detail';
                        $value = trim($line);
                    }
                ?>
                <div class="bank-detail-item">
                    <div class="bank-icon"><i class="bi bi-dot"></i></div>
                    <div>
                        <div class="bank-label"><?= htmlspecialchars($label) ?></div>
                        <div class="bank-value"><?= nl2br(htmlspecialchars($value)) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (!empty($settings['scanner_image'])): ?>
                <div class="mt-3">
                    <label class="fw-bold mb-2">Scanner / QR Code:</label>
                    <div><img src="<?= htmlspecialchars($settings['scanner_image']) ?>" class="scanner-img"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Slip Upload Form Column -->
        <div class="col-lg-6">
            <div class="bank-card">
                <h5 class="fw-bold mb-3"><i class="bi bi-upload me-2"></i>Submit Payment Slip</h5>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="package_id" value="<?= $package_id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (₹)</label>
                        <input type="number" name="amount" class="form-control" placeholder="Enter amount you paid" required min="1">
                        <small class="text-muted">Admin Charge: ₹<?= $settings['admin_charge'] ?? '0' ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload Slip / Screenshot</label>
                        <div class="upload-box">
                            <i class="bi bi-image"></i>
                            <p class="mt-2 text-muted">Click to select image (JPG, PNG, PDF)</p>
                            <input type="file" name="slip_image" accept="image/*,application/pdf" class="form-control" required>
                        </div>
                    </div>

                    <button type="submit" name="submit_payment" class="btn btn-primary-custom w-100">
                        <i class="bi bi-check-circle me-2"></i>Submit Payment Request
                    </button>
                </form>
                <p class="text-muted small mt-3">* Admin द्वारा आपके पेमेंट की पुष्टि होने के बाद आपका पैकेज सक्रिय होगा।</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
