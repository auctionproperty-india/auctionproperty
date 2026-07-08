<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}
if(!hasViewPermission('settings', $pdo)) {
    die("Permission denied.");
}

$message = '';
$settings_keys = ['default_contact', 'company_bank_name', 'company_account_number', 'company_ifsc', 'company_branch', 'tds_percent', 'admin_charge_percent', 'spin_min_coins', 'spin_max_coins'];

// Handle QR upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    if(!hasEditPermission('settings', $pdo)) {
        die("You don't have permission to edit settings.");
    }
    $admin_password = $_POST['admin_password'] ?? '';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
    if(!$admin || !password_verify($admin_password, $admin['password'])) {
        $message = "<div class='alert alert-danger'>❌ Incorrect admin password!</div>";
    } else {
        // Save text fields
        foreach($settings_keys as $key) {
            $val = trim($_POST[$key] ?? '');
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$val, $key]);
        }
        // Save QR code if uploaded
        if(isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == 0) {
            $upload_dir = 'uploads/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
            $filename = 'qr_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['qr_code']['tmp_name'], $upload_dir . $filename);
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'company_qr_code'")->execute([$upload_dir . $filename]);
        }
        $message = "<div class='alert alert-success'>✅ Settings updated!</div>";
    }
}

include 'header.php';
// Fetch current values
$settings = [];
foreach($settings_keys as $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $settings[$key] = $stmt->fetchColumn() ?: '';
}
$qr = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'company_qr_code'")->fetchColumn();
?>
<div class="card-premium">
    <h4><i class="fas fa-university me-2"></i>Company Bank Details & QR Code</h4>
    <?= $message ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="fw-bold">Bank Name</label>
                <input type="text" name="company_bank_name" class="form-control" value="<?= htmlspecialchars($settings['company_bank_name']) ?>">
            </div>
            <div class="col-md-6">
                <label class="fw-bold">Account Number</label>
                <input type="text" name="company_account_number" class="form-control" value="<?= htmlspecialchars($settings['company_account_number']) ?>">
            </div>
            <div class="col-md-4">
                <label class="fw-bold">IFSC Code</label>
                <input type="text" name="company_ifsc" class="form-control" value="<?= htmlspecialchars($settings['company_ifsc']) ?>">
            </div>
            <div class="col-md-4">
                <label class="fw-bold">Branch</label>
                <input type="text" name="company_branch" class="form-control" value="<?= htmlspecialchars($settings['company_branch']) ?>">
            </div>
            <div class="col-md-4">
                <label class="fw-bold">Default Contact Number</label>
                <input type="text" name="default_contact" class="form-control" value="<?= htmlspecialchars($settings['default_contact']) ?>">
            </div>
        </div>
        <hr>
        <h5><i class="fas fa-percent me-2"></i>Referral Payout Deductions</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="fw-bold">TDS %</label>
                <input type="number" step="0.01" name="tds_percent" class="form-control" value="<?= htmlspecialchars($settings['tds_percent']) ?>">
                <small class="text-muted">Default TDS percentage for referral payouts.</small>
            </div>
            <div class="col-md-6">
                <label class="fw-bold">Admin Charge %</label>
                <input type="number" step="0.01" name="admin_charge_percent" class="form-control" value="<?= htmlspecialchars($settings['admin_charge_percent']) ?>">
                <small class="text-muted">Default Admin Charge percentage for referral payouts.</small>
            </div>
        </div>
        <hr>
        <h5><i class="fas fa-coins me-2"></i>Daily Spin Coin Settings</h5>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="fw-bold">Min Coins per Spin</label>
                <input type="number" step="0.01" name="spin_min_coins" class="form-control" value="<?= htmlspecialchars($settings['spin_min_coins']) ?>">
                <small class="text-muted">Minimum coins user gets on each spin.</small>
            </div>
            <div class="col-md-6">
                <label class="fw-bold">Max Coins per Spin</label>
                <input type="number" step="0.01" name="spin_max_coins" class="form-control" value="<?= htmlspecialchars($settings['spin_max_coins']) ?>">
                <small class="text-muted">Maximum coins user gets on each spin.</small>
            </div>
            <div class="col-12">
                <small class="text-muted">Per slot total is capped at 22 coins. Adjust min/max to control average.</small>
            </div>
        </div>
        <hr>
        <div class="row g-3">
            <div class="col-12">
                <label class="fw-bold">UPI QR Code (for Payment Page)</label>
                <input type="file" name="qr_code" class="form-control" accept="image/*">
                <?php if($qr && file_exists($qr)): ?>
                    <div class="mt-3 text-center">
                        <p class="text-muted">Current QR Code:</p>
                        <img src="<?= $qr ?>" style="max-height:250px; border:1px solid #ddd; border-radius:12px; padding:10px; background:white;">
                    </div>
                <?php else: ?>
                    <div class="mt-3 text-center">
                        <p class="text-muted">No QR code uploaded yet.</p>
                    </div>
                <?php endif; ?>
                <small class="text-muted">Upload a QR code image (PNG, JPG). This will appear on the subscription payment page.</small>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <label class="fw-bold">Verify Admin Password *</label>
                <input type="password" name="admin_password" class="form-control" placeholder="Enter password to save" required>
            </div>
        </div>
        <button type="submit" name="update_settings" class="btn btn-primary mt-3">Save Settings</button>
    </form>
</div>
<?php include 'footer.php'; ?>
