<?php
require_once 'db.php';

// ---- 🚀 ऑटो-सेटअप (OTP कॉलम अपने आप बन जाएंगे) ----
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_code VARCHAR(10)");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS otp_expiry TIMESTAMP");
} catch (Exception $e) {
    // पहले से हैं तो ignore करें
}

// ---- यूज़र चेक ----
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$target_user_id = $_GET['user_id'] ?? $user_id;

$is_admin_mode = ($role == 'admin' && $target_user_id != $user_id);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$target_user = $stmt->fetch();
if(!$target_user) { die("User not found!"); }

$message = '';
$otp_sent = false;
$otp_display = ''; // अगर Mail fail हो तो OTP यहाँ दिखाएँ

// Email Send Function (Mailhog / Sendmail)
function send_mail($to, $subject, $body) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: PropertyDeal <noreply@propertydeal.com>" . "\r\n";
    return mail($to, $subject, $body, $headers);
}

// ---- ADMIN DIRECT CHANGE ----
if($is_admin_mode && $_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_change_pass'])) {
    $new_pass = $_POST['new_password'];
    if(strlen($new_pass) < 6) { $message = "❌ Password must be at least 6 characters."; 
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $target_user_id]);
        send_mail($target_user['email'], "Password Changed by Admin", "New password: <strong>$new_pass</strong>");
        $message = "✅ Password changed for <strong>{$target_user['email']}</strong>!";
    }
}

// ---- USER SELF CHANGE ----
if(!$is_admin_mode) {
    // Step A: Send OTP
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_otp'])) {
        $otp = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $pdo->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?")->execute([$otp, $expiry, $user_id]);
        
        $body = "Your OTP is: <h2>$otp</h2><p>Valid for 10 minutes.</p>";
        $mail_sent = send_mail($target_user['email'], "Your OTP for Password Change", $body);
        
        if($mail_sent) {
            $otp_sent = true;
            $message = "✅ OTP sent to your email!";
        } else {
            // ***** मोस्ट इम्पोर्टेंट: अगर Mail नहीं जाता तो OTP यहाँ दिखा दो *****
            $otp_sent = true;
            $otp_display = $otp;
            $message = "⚠️ Mail not configured, but your OTP is: <strong style='font-size:24px; color:#2563eb;'>$otp</strong> (Copy this)";
        }
    }

    // Step B: Verify OTP
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp_change'])) {
        $otp_input = $_POST['otp_code'];
        $new_pass = $_POST['new_password'];
        $check = $pdo->prepare("SELECT * FROM users WHERE id = ? AND otp_code = ? AND otp_expiry > NOW()");
        $check->execute([$user_id, $otp_input]);
        if($check->rowCount() > 0) {
            if(strlen($new_pass) < 6) { $message = "❌ Password must be 6+ chars.";
            } else {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password = ?, otp_code = NULL, otp_expiry = NULL WHERE id = ?")->execute([$hashed, $user_id]);
                send_mail($target_user['email'], "Password Changed", "Your password has been changed.");
                $message = "✅ Password changed! <a href='dashboard.php'>Go to Dashboard</a>";
            }
        } else {
            $message = "❌ Invalid or Expired OTP!";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f7fc; font-family: 'Inter', sans-serif; }
        .container { max-width: 500px; margin-top: 80px; }
        .card { border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .btn-primary { background: #2563eb; border: none; }
        .btn-primary:hover { background: #1d4ed8; }
        .otp-display { font-size: 28px; font-weight: 700; color: #2563eb; letter-spacing: 4px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4 shadow-sm">
        <h3 class="mb-3">🔑 Change Password</h3>
        <?php if($message) echo "<div class='alert alert-info'>$message</div>"; ?>

        <?php if($is_admin_mode): ?>
            <form method="POST">
                <p><strong>User:</strong> <?= htmlspecialchars($target_user['email']) ?></p>
                <div class="mb-3">
                    <label>New Password</label>
                    <input type="text" name="new_password" class="form-control" required>
                </div>
                <button type="submit" name="admin_change_pass" class="btn btn-primary w-100">Update Password</button>
                <a href="dashboard.php" class="btn btn-link mt-2 d-block text-center">⬅ Back</a>
            </form>
        <?php else: ?>
            <?php if(!$otp_sent): ?>
                <form method="POST">
                    <p>OTP will be sent to <strong><?= htmlspecialchars($target_user['email']) ?></strong></p>
                    <button type="submit" name="send_otp" class="btn btn-primary w-100">📧 Send OTP</button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-3">
                        <label>Enter OTP (6 digits)</label>
                        <input type="text" name="otp_code" class="form-control" required maxlength="6" placeholder="e.g. 123456">
                    </div>
                    <div class="mb-3">
                        <label>New Password</label>
                        <input type="text" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <button type="submit" name="verify_otp_change" class="btn btn-success w-100">✅ Verify & Change</button>
                </form>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-link mt-2 d-block text-center">⬅ Back</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
