<?php
// ============================================================
// 🔑 Change Password – OTP Display on Screen (No Email)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$otp_generated = false;
$otp = '';

// ---- Step 1: Generate OTP and Display ----
if (isset($_POST['generate_otp'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate old password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!password_verify($old_password, $user['password'])) {
        $error = "❌ Current password is incorrect.";
    } elseif (strlen($new_password) < 6) {
        $error = "❌ New password must be at least 6 characters.";
    } elseif ($new_password !== $confirm_password) {
        $error = "❌ New passwords do not match.";
    } else {
        // Generate OTP (6 digits)
        $otp = rand(100000, 999999);
        $_SESSION['change_pass_otp'] = $otp;
        $_SESSION['change_pass_new'] = $new_password; // store temporarily
        $_SESSION['change_pass_otp_time'] = time();
        $otp_generated = true;
        $message = "✅ OTP generated successfully! Please enter it below.";
    }
}

// ---- Step 2: Verify OTP and Change Password ----
if (isset($_POST['verify_otp'])) {
    $user_otp = $_POST['otp'] ?? '';
    $stored_otp = $_SESSION['change_pass_otp'] ?? null;
    $new_pass = $_SESSION['change_pass_new'] ?? null;
    $otp_time = $_SESSION['change_pass_otp_time'] ?? 0;

    // Check OTP expiry (5 minutes)
    if (time() - $otp_time > 300) {
        $error = "❌ OTP has expired. Please generate a new one.";
        unset($_SESSION['change_pass_otp'], $_SESSION['change_pass_new'], $_SESSION['change_pass_otp_time']);
    } elseif ($user_otp != $stored_otp) {
        $error = "❌ Invalid OTP. Please try again.";
    } else {
        // OTP verified – update password
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        $message = "✅ Password changed successfully!";
        unset($_SESSION['change_pass_otp'], $_SESSION['change_pass_new'], $_SESSION['change_pass_otp_time']);
        // Optionally redirect after a few seconds
        // header("Refresh: 2; url=dashboard.php");
    }
}

include 'header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card-premium">
                <h4><i class="fas fa-key me-2"></i>Change Password</h4>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <!-- Step 1: Old + New Password -->
                <?php if (!$otp_generated && !isset($_POST['verify_otp'])): ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="generate_otp" class="btn btn-primary">Generate OTP</button>
                </form>
                <?php endif; ?>

                <!-- Step 2: Enter OTP (displayed on screen) -->
                <?php if ($otp_generated || isset($_POST['generate_otp']) && !$error): ?>
                    <!-- Show OTP on screen -->
                    <div class="alert alert-info text-center mt-3" style="font-size: 1.5rem; font-weight: bold; background: #f0f9ff; border: 2px dashed #0284c7;">
                        🔑 Your OTP: <span style="color: #dc2626; letter-spacing: 4px;"><?= $otp ?></span>
                        <br><small class="text-muted" style="font-size: 0.8rem;">(This OTP is valid for 5 minutes)</small>
                    </div>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Enter OTP *</label>
                            <input type="text" name="otp" class="form-control" placeholder="Enter 6-digit OTP" required maxlength="6">
                        </div>
                        <button type="submit" name="verify_otp" class="btn btn-success">Verify & Change Password</button>
                        <a href="change_password.php" class="btn btn-secondary">Cancel</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
