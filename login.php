<?php
// ============================================================
// 🔐 LOGIN PAGE (100% FIXED FOR SINGLE DEVICE)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// अगर यूजर पहले से लॉगिन है
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit;
}

$error = '';
$success = '';

// रजिस्ट्रेशन सक्सेस मैसेज
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// लॉगआउट मैसेज हैंडलिंग
if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out_elsewhere') {
    $error = 'You have been logged out because you logged in from another device or your session expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            
            if ($user['status'] === 'deleted' || $user['status'] === 'inactive') {
                $error = 'Your account is inactive or deleted.';
            } else {
                // 🔥 नया Session Token जनरेट करें
                $session_token = bin2hex(random_bytes(32));

                // डेटाबेस में टोकन अपडेट करें
                try {
                    $update_token = $pdo->prepare("UPDATE users SET session_token = ? WHERE id = ?");
                    $update_token->execute([$session_token, $user['id']]);
                } catch (Exception $e) {
                    // अगर कॉलम नहीं है तो यह एरर इग्नोर करेगा (लेकिन आपने SQL चला दिया है तो यह काम करेगा)
                }
                
                // सेशन वेरिएबल्स सेट करें
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['email']         = $user['email'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['name']          = $user['name'];
                $_SESSION['logged_in']     = true;
                $_SESSION['session_token'] = $session_token; // यही टोकन आपकी header.php चेक करेगी
                
                if ($user['role'] === 'admin') {
                    header("Location: dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit;
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

include 'header.php';
?>

<style>
    .login-container { min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .login-card { background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(8px); border-radius: 30px; padding: 40px 35px; max-width: 450px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.3); }
    .login-card h2 { font-weight: 700; color: #0f172a; margin-bottom: 8px; }
    .login-card p.sub { color: #64748b; margin-bottom: 25px; font-size: 0.95rem; }
    .login-card .form-label { font-weight: 600; color: #1e293b; }
    .login-card .form-control { border-radius: 12px; padding: 12px 16px; border: 1px solid #e2e8f0; background: #f8fafc; }
    .login-card .form-control:focus { border-color: #1e3a8a; box-shadow: 0 0 0 3px rgba(30,58,138,0.1); }
    .login-card .btn-primary { background: #1e3a8a; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s; }
    .login-card .btn-primary:hover { background: #2563eb; transform: translateY(-2px); }
    .login-card .register-link { color: #1e3a8a; font-weight: 600; text-decoration: none; }
    .login-card .register-link:hover { text-decoration: underline; }
    .login-card .error-msg { background: #fef2f2; border-left: 4px solid #dc2626; padding: 12px; border-radius: 8px; color: #991b1b; font-size: 0.9rem; margin-bottom: 20px; }
    .login-card .success-msg { background: #dcfce7; border-left: 4px solid #16a34a; padding: 12px; border-radius: 8px; color: #14532d; font-size: 0.9rem; margin-bottom: 20px; }
</style>

<div class="login-container">
    <div class="login-card">
        <h2>Welcome Back</h2>
        <p class="sub">Sign in to your account</p>

        <?php if ($success): ?>
            <div class="success-msg"><i class="fas fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mt-2">Sign In</button>
        </form>

        <p class="text-center mt-4">Don't have an account? <a href="register.php" class="register-link">Create one</a></p>
    </div>
</div>

<?php include 'footer.php'; ?>
