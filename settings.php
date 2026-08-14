<?php
session_start();
require_once __DIR__ . '/db.php';

// 1. Save Settings (Edit Mode ON)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings']) && isset($_SESSION['edit_allowed']) && $_SESSION['edit_allowed'] === true) {
    
    $tds = $_POST['tds'] ?? '0';
    $bank_details = $_POST['bank_details'] ?? '';
    $admin_charge = $_POST['admin_charge'] ?? '0';
    $scanner_image = $_POST['existing_scanner'] ?? '';
    
    // File Upload
    if (isset($_FILES['scanner_image']) && $_FILES['scanner_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['scanner_image']['name'], PATHINFO_EXTENSION);
        $filename = 'scanner_' . time() . '.' . $ext;
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['scanner_image']['tmp_name'], $target)) {
            $scanner_image = 'uploads/' . $filename;
        }
    }
    
    $sql = "UPDATE settings SET tds = ?, bank_details = ?, admin_charge = ?, scanner_image = ? WHERE id = 1";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$tds, $bank_details, $admin_charge, $scanner_image]);
    
    if ($result) {
        $success = "✅ Settings सफलतापूर्वक Save हो गईं!";
        unset($_SESSION['edit_allowed']);
    } else {
        $error = "❌ Save करने में त्रुटि: " . implode(" ", $stmt->errorInfo());
    }
}

// 2. Password Verify
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_password'])) {
    $entered_password = $_POST['password'] ?? '';
    if (password_verify($entered_password, password_hash('admin123', PASSWORD_DEFAULT))) {
        $_SESSION['edit_allowed'] = true;
    } else {
        $error = "❌ गलत Password!";
    }
}

// 3. Load Settings
$stmt = $pdo->query("SELECT tds, bank_details, admin_charge, scanner_image FROM settings WHERE id = 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    $pdo->exec("INSERT INTO settings (id, tds, bank_details, admin_charge, scanner_image) VALUES (1, '0', '', '0', '')");
    $settings = ['tds'=>'0', 'bank_details'=>'', 'admin_charge'=>'0', 'scanner_image'=>''];
}

$edit_mode = isset($_SESSION['edit_allowed']) && $_SESSION['edit_allowed'] === true;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .container { max-width: 800px; margin: 40px auto; }
        .readonly { background: #f1f5f9; color: #475569; }
        .password-box { background: #fff; border: 1px dashed #94a3b8; padding: 25px; border-radius: 12px; }
        .preview-img { max-height: 150px; border: 1px solid #ddd; padding: 5px; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>⚙️ Settings</h1>
        <a href="admin_dashboard.php" class="btn btn-secondary">🔙 Back to Dashboard</a>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!$edit_mode): ?>
        <div class="password-box mb-4">
            <h5>🔒 Edit Settings</h5>
            <p class="text-muted">Settings को Edit करने के लिए Admin Password दर्ज करें:</p>
            <form method="POST" class="row g-3">
                <div class="col-auto">
                    <input type="password" name="password" placeholder="Admin Password" required class="form-control">
                </div>
                <div class="col-auto">
                    <button type="submit" name="verify_password" class="btn btn-primary">🔓 Unlock</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label"><strong>TDS (%)</strong></label>
            <input type="text" name="tds" value="<?= htmlspecialchars($settings['tds']) ?>" class="form-control <?= $edit_mode ? '' : 'readonly' ?>" <?= $edit_mode ? '' : 'disabled' ?>>
        </div>
        <div class="mb-3">
            <label class="form-label"><strong>Bank Details</strong></label>
            <textarea name="bank_details" rows="4" class="form-control <?= $edit_mode ? '' : 'readonly' ?>" <?= $edit_mode ? '' : 'disabled' ?>><?= htmlspecialchars($settings['bank_details']) ?></textarea>
            <small class="text-muted">यह Bank Details User को Payment के समय दिखाई जाएगी।</small>
        </div>
        <div class="mb-3">
            <label class="form-label"><strong>Admin Charge (₹)</strong></label>
            <input type="text" name="admin_charge" value="<?= htmlspecialchars($settings['admin_charge']) ?>" class="form-control <?= $edit_mode ? '' : 'readonly' ?>" <?= $edit_mode ? '' : 'disabled' ?>>
        </div>
        <div class="mb-3">
            <label class="form-label"><strong>Scanner Image</strong></label>
            <?php if (!empty($settings['scanner_image'])): ?>
                <div><img src="<?= htmlspecialchars($settings['scanner_image']) ?>" class="preview-img"></div>
            <?php endif; ?>
            <input type="file" name="scanner_image" accept="image/*" class="form-control" <?= $edit_mode ? '' : 'disabled' ?>>
            <input type="hidden" name="existing_scanner" value="<?= htmlspecialchars($settings['scanner_image']) ?>">
        </div>
        
        <?php if ($edit_mode): ?>
            <button type="submit" name="save_settings" class="btn btn-success">💾 Save Settings</button>
            <a href="settings.php" class="btn btn-warning">↩️ Cancel</a>
        <?php else: ?>
            <p class="text-muted fst-italic">Settings को Edit करने के लिए ऊपर Unlock करें।</p>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
