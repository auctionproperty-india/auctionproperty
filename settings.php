<?php
session_start();
require_once __DIR__ . '/db.php';

// 1. यदि 'Save' किया गया है और Session में 'edit_allowed' है तो UPDATE करें
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings']) && isset($_SESSION['edit_allowed']) && $_SESSION['edit_allowed'] === true) {
    
    $tds = $_POST['tds'] ?? '';
    $bank_details = $_POST['bank_details'] ?? '';
    $admin_charge = $_POST['admin_charge'] ?? '';
    
    // File Upload Handle
    $scanner_image = null;
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
    
    // यदि कोई नई Image नहीं आई तो पुरानी Image retain करें
    if ($scanner_image === null) {
        // पुरानी Image लें
        $stmt = $pdo->query("SELECT scanner_image FROM settings LIMIT 1");
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        $scanner_image = $old['scanner_image'] ?? '';
    }
    
    // UPDATE Query
    $sql = "UPDATE settings 
            SET tds = ?, bank_details = ?, admin_charge = ?, scanner_image = ? 
            WHERE id = 1"; // मान लिया कि settings की id = 1 है
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$tds, $bank_details, $admin_charge, $scanner_image]);
    
    if ($result) {
        $success = "✅ Settings सफलतापूर्वक Save हो गईं!";
        // Edit permission खत्म करें (ताकि दोबारा Edit के लिए Password माँगे)
        unset($_SESSION['edit_allowed']);
    } else {
        $error = "❌ Save करने में त्रुटि: " . implode(" ", $stmt->errorInfo());
    }
}

// 2. यदि 'Edit' बटन दबाया (Password Verify)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_password'])) {
    $entered_password = $_POST['password'] ?? '';
    // यहाँ अपना Admin Password (Hash) डालें – मान लिया password = 'admin123'
    // बेहतर होगा कि आप किसी Admin User से verify करें
    if (password_verify($entered_password, password_hash('admin123', PASSWORD_DEFAULT))) {
        $_SESSION['edit_allowed'] = true;
        $edit_mode = true;
    } else {
        $error = "❌ गलत Password!";
    }
}

// 3. Current Settings Load करें
$stmt = $pdo->query("SELECT tds, bank_details, admin_charge, scanner_image FROM settings LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    // अगर पहली बार Settings नहीं हैं तो डिफॉल्ट डालें
    $pdo->exec("INSERT INTO settings (tds, bank_details, admin_charge, scanner_image) VALUES ('0', '', '0', '')");
    $settings = ['tds'=>'0', 'bank_details'=>'', 'admin_charge'=>'0', 'scanner_image'=>''];
}

$edit_mode = isset($_SESSION['edit_allowed']) && $_SESSION['edit_allowed'] === true;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Settings</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 30px auto; padding: 20px; }
        .readonly { background: #f1f5f9; color: #475569; }
        input[type="text"], textarea { width: 100%; padding: 8px; margin: 5px 0 15px; border: 1px solid #cbd5e1; border-radius: 6px; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; }
        .btn-primary { background: #2563eb; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-success { background: #22c55e; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .field-group { margin-bottom: 20px; }
        .msg { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #dcfce7; border: 1px solid #22c55e; }
        .error { background: #fee2e2; border: 1px solid #dc2626; }
        .hidden { display: none; }
        .password-box { background: #f8fafc; border: 1px dashed #94a3b8; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>⚙️ Settings</h1>

    <?php if (isset($success)): ?>
        <div class="msg success"><?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="msg error"><?= $error ?></div>
    <?php endif; ?>

    <!-- Password Verification Box -->
    <?php if (!$edit_mode): ?>
        <div class="password-box">
            <h3>🔒 Edit Settings</h3>
            <p>Settings को Edit करने के लिए कृपया Password दर्ज करें:</p>
            <form method="POST">
                <input type="password" name="password" placeholder="Enter Admin Password" required style="width: 300px; padding: 8px; border:1px solid #94a3b8; border-radius:6px;">
                <button type="submit" name="verify_password" class="btn btn-primary">🔓 Unlock Edit</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Settings Form -->
    <form method="POST" enctype="multipart/form-data">
        <div class="field-group">
            <label><strong>TDS (%)</strong></label>
            <input type="text" name="tds" value="<?= htmlspecialchars($settings['tds']) ?>" <?= $edit_mode ? '' : 'disabled class="readonly"' ?>>
        </div>
        <div class="field-group">
            <label><strong>Bank Details</strong></label>
            <textarea name="bank_details" rows="3" <?= $edit_mode ? '' : 'disabled class="readonly"' ?>><?= htmlspecialchars($settings['bank_details']) ?></textarea>
        </div>
        <div class="field-group">
            <label><strong>Admin Charge (₹)</strong></label>
            <input type="text" name="admin_charge" value="<?= htmlspecialchars($settings['admin_charge']) ?>" <?= $edit_mode ? '' : 'disabled class="readonly"' ?>>
        </div>
        <div class="field-group">
            <label><strong>Scanner Image</strong></label>
            <?php if (!empty($settings['scanner_image'])): ?>
                <div><img src="<?= htmlspecialchars($settings['scanner_image']) ?>" style="max-height:150px; border:1px solid #ccc; padding:5px;"></div>
            <?php endif; ?>
            <input type="file" name="scanner_image" accept="image/*" <?= $edit_mode ? '' : 'disabled' ?>>
            <?php if (!$edit_mode): ?>
                <p style="color:#64748b; font-size:0.9em;">(Edit mode में Enable होगा)</p>
            <?php endif; ?>
        </div>
        
        <?php if ($edit_mode): ?>
            <button type="submit" name="save_settings" class="btn btn-success">💾 Save Settings</button>
            <a href="settings.php" class="btn btn-warning">↩️ Cancel (Exit Edit)</a>
        <?php else: ?>
            <p style="color:#94a3b8; font-style:italic;">Settings को Edit करने के लिए ऊपर Unlock करें।</p>
        <?php endif; ?>
    </form>

    <?php if ($edit_mode): ?>
        <script>
            // Edit mode में Cancel पर Edit permission हटाने के लिए
            // हम सिर्फ URL में ?exit=1 डालकर session destroy कर सकते हैं
        </script>
    <?php endif; ?>
</body>
</html>
