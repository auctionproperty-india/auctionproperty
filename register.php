<?php
// ============================================================
// ✅ REGISTER – With Referral Code Capture from URL
// ============================================================

session_start(); // 🔥 MUST BE FIRST

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ====== 🔥 FIX: Capture Referral Code from URL ======
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $_SESSION['referral_code'] = trim($_GET['ref']);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    // ====== 🔥 FIX: Get referral code from POST or Session ======
    $ref_code = trim($_POST['referral_code'] ?? '');
    if (empty($ref_code) && isset($_SESSION['referral_code'])) {
        $ref_code = $_SESSION['referral_code'];
    }

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            // Check referral code
            $ref_by = null;
            if (!empty($ref_code)) {
                $ref_by = getReferrerIdByCode($pdo, $ref_code);
            }
            // Hash password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $new_code = generateReferralCode();

            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, referral_code, referred_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())");
            $stmt->execute([$name, $email, $phone, $hashed, $new_code, $ref_by]);

            // ====== 🔥 FIX: Clear session referral code after successful registration ======
            unset($_SESSION['referral_code']);

            $success = 'Account created! You can now login.';
            // Optionally auto-login
            // header("Location: login.php?registered=1");
        }
    }
}

include 'header.php';
?>
<style>
    .register-container {
        min-height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .register-card {
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(8px);
        border-radius: 30px;
        padding: 40px 35px;
        max-width: 500px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.3);
    }
    .register-card h2 {
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }
    .register-card p.sub {
        color: #64748b;
        margin-bottom: 25px;
        font-size: 0.95rem;
    }
    .register-card .form-label {
        font-weight: 600;
        color: #1e293b;
    }
    .register-card .form-control {
        border-radius: 12px;
        padding: 12px 16px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .register-card .form-control:focus {
        border-color: #1e3a8a;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    .register-card .btn-primary {
        background: #1e3a8a;
        border: none;
        padding: 12px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .register-card .btn-primary:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }
    .register-card .login-link {
        color: #1e3a8a;
        font-weight: 600;
        text-decoration: none;
    }
    .register-card .login-link:hover {
        text-decoration: underline;
    }
    .register-card .error-msg {
        background: #fef2f2;
        border-left: 4px solid #dc2626;
        padding: 12px;
        border-radius: 8px;
        color: #991b1b;
        font-size: 0.9rem;
    }
    .register-card .success-msg {
        background: #dcfce7;
        border-left: 4px solid #16a34a;
        padding: 12px;
        border-radius: 8px;
        color: #14532d;
    }
    .referral-info {
        background: #f0f5ff;
        border-radius: 10px;
        padding: 10px 14px;
        font-size: 0.9rem;
        color: #1e3a8a;
        border: 1px solid #dbeafe;
        margin-top: 5px;
        display: <?= (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) ? 'block' : 'none' ?>;
    }
    .referral-info i {
        margin-right: 6px;
    }
    @media (max-width: 576px) {
        .register-card { padding: 30px 20px; }
    }
</style>
<div class="register-container">
    <div class="register-card">
        <h2>Create Account</h2>
        <p class="sub">Join Prime Property today</p>

        <?php if ($error): ?>
            <div class="error-msg mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-msg mb-3"><?= htmlspecialchars($success) ?> <a href="login.php" class="login-link">Login now</a></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="Your name" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone (optional)</label>
                <input type="text" name="phone" class="form-control" placeholder="9876543210">
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
            </div>
            
            <!-- ====== 🔥 FIX: Hidden field to carry referral code ====== -->
            <input type="hidden" name="referral_code" value="<?= isset($_SESSION['referral_code']) ? htmlspecialchars($_SESSION['referral_code']) : '' ?>">

            <!-- ====== 🔥 FIX: Show referral info if code is present ====== -->
            <div class="referral-info" id="referralInfo">
                <i class="fas fa-gift"></i> 
                <strong>🎉 Referral Code Applied:</strong> 
                <span style="font-weight:700; color:#1e40af;">
                    <?= isset($_SESSION['referral_code']) ? htmlspecialchars($_SESSION['referral_code']) : '' ?>
                </span>
                <br>
                <small style="color:#64748b;">You will get a special bonus on registration!</small>
            </div>

            <button type="submit" class="btn btn-primary w-100">Create Account</button>
        </form>

        <p class="text-center mt-4">
            Already have an account? <a href="login.php" class="login-link">Sign in</a>
        </p>
    </div>
</div>

<?php include 'footer.php'; ?>
