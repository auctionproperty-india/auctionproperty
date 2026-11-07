<?php
// ============================================================
// ✅ REGISTER – With State, Referral, City, Mobile Validation
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $_SESSION['referral_code'] = trim($_GET['ref']);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $ref_code = trim($_POST['referral_code'] ?? '');
    if (empty($ref_code) && isset($_SESSION['referral_code'])) {
        $ref_code = $_SESSION['referral_code'];
    }

    $phone_clean = preg_replace('/[^0-9]/', '', $phone);

    if (empty($name) || empty($email) || empty($password) || empty($city) || empty($state) || empty($phone)) {
        $error = 'All fields are required (Name, Email, Phone, City, State, Password).';
    }
    elseif (!preg_match('/^[6-9][0-9]{9}$/', $phone_clean)) {
        $error = '❌ Invalid Mobile Number! 10 digit का सही Mobile Number डालें (6, 7, 8, 9 से शुरू होना चाहिए)।';
    }
    elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $pdo->prepare("SELECT id, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone_clean]);
        $phone_exists = $stmt->fetch();

        if ($existing_user && $existing_user['status'] !== 'deleted') {
            $error = 'Email already registered.';
        } elseif ($phone_exists) {
            $error = '❌ This Mobile Number is already registered!';
        } else {
            $ref_by = null;
            if (!empty($ref_code)) {
                $ref_by = getReferrerIdByCode($pdo, $ref_code);
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $new_code = generateReferralCode();

            if ($existing_user) {
                $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, city=?, state=?, password=?, referral_code=?, referred_by=?, status='active', created_at=NOW() WHERE id=?");
                $stmt->execute([$name, $phone_clean, $city, $state, $hashed, $new_code, $ref_by, $existing_user['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, city, state, password, referral_code, referred_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([$name, $email, $phone_clean, $city, $state, $hashed, $new_code, $ref_by]);
            }

            unset($_SESSION['referral_code']);

            // Welcome Email
            sendSystemEmail('registration', $email, [
                'name' => $name,
                'email' => $email,
                'password' => $password
            ]);

            // Downline Email
            if ($ref_by) {
                $sponsor_stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $sponsor_stmt->execute([$ref_by]);
                $sponsor_data = $sponsor_stmt->fetch();
                if ($sponsor_data) {
                    sendSystemEmail('downline_reg', $sponsor_data['email'], [
                        'sponsor_name' => $sponsor_data['name'],
                        'downline_name' => $name,
                        'downline_email' => $email
                    ]);
                }
            }

            $_SESSION['flash_success'] = 'Account created successfully! Please check your email for login details.';
            header("Location: login.php");
            exit;
        }
    }
}

include 'header.php';
?>

<style>
    .register-container { min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .register-card { background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(8px); border-radius: 30px; padding: 40px 35px; max-width: 500px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.3); }
    .register-card h2 { font-weight: 700; color: #0f172a; margin-bottom: 8px; }
    .register-card p.sub { color: #64748b; margin-bottom: 25px; font-size: 0.95rem; }
    .register-card .form-label { font-weight: 600; color: #1e293b; }
    .register-card .form-control { border-radius: 12px; padding: 12px 16px; border: 1px solid #e2e8f0; background: #f8fafc; }
    .register-card .form-control:focus { border-color: #1e3a8a; box-shadow: 0 0 0 3px rgba(30,58,138,0.1); }
    .register-card .btn-primary { background: #1e3a8a; border: none; padding: 12px; border-radius: 12px; font-weight: 600; transition: all 0.3s; }
    .register-card .btn-primary:hover { background: #2563eb; transform: translateY(-2px); }
    .register-card .login-link { color: #1e3a8a; font-weight: 600; text-decoration: none; }
    .register-card .login-link:hover { text-decoration: underline; }
    .register-card .error-msg { background: #fef2f2; border-left: 4px solid #dc2626; padding: 12px; border-radius: 8px; color: #991b1b; font-size: 0.9rem; }
    .register-card .success-msg { background: #dcfce7; border-left: 4px solid #16a34a; padding: 12px; border-radius: 8px; color: #14532d; }
    .referral-info { background: #f0f5ff; border-radius: 10px; padding: 10px 14px; font-size: 0.9rem; color: #1e3a8a; border: 1px solid #dbeafe; margin-top: 5px; display: <?= (isset($_SESSION['referral_code']) && !empty($_SESSION['referral_code'])) ? 'block' : 'none' ?>; }
    .phone-input-wrapper { position: relative; }
    .phone-input-wrapper .country-prefix { position: absolute; top: 50%; left: 16px; transform: translateY(-50%); color: #64748b; font-weight: 600; pointer-events: none; }
    .phone-input-wrapper input { padding-left: 55px !important; letter-spacing: 2px; font-weight: 600; }
    .phone-input-wrapper input.valid-phone { border-color: #16a34a; background: #f0fdf4; }
    .phone-input-wrapper input.invalid-phone { border-color: #dc2626; background: #fef2f2; }
    .phone-status { font-size: 0.75rem; margin-top: 4px; font-weight: 600; display: none; }
    .phone-status.valid { color: #16a34a; display: block; }
    .phone-status.invalid { color: #dc2626; display: block; }
    @media (max-width: 576px) { .register-card { padding: 30px 20px; } }
</style>

<div class="register-container">
    <div class="register-card">
        <h2>Create Account</h2>
        <p class="sub">Join Prime Property today</p>

        <?php if ($error): ?>
            <div class="error-msg mb-3"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="mb-3"><label class="form-label">Full Name <span style="color:#dc2626;">*</span></label><input type="text" name="name" class="form-control" required minlength="3"></div>
            <div class="mb-3"><label class="form-label">Email Address <span style="color:#dc2626;">*</span></label><input type="email" name="email" class="form-control" required></div>
            <div class="mb-3">
                <label class="form-label">Mobile Number <span style="color:#dc2626;">*</span></label>
                <div class="phone-input-wrapper">
                    <span class="country-prefix">+91</span>
                    <input type="tel" name="phone" id="phoneInput" class="form-control" required maxlength="10" minlength="10" inputmode="numeric" autocomplete="off" pattern="[6-9][0-9]{9}">
                </div>
                <div class="phone-status" id="phoneStatus"></div>
            </div>
            <div class="mb-3"><label class="form-label">City <span style="color:#dc2626;">*</span></label><input type="text" name="city" class="form-control" required minlength="2" placeholder="e.g. Indore"></div>
            
            <!-- 🔥 NAYA: State Input -->
            <div class="mb-3"><label class="form-label">State <span style="color:#dc2626;">*</span></label><input type="text" name="state" class="form-control" required minlength="2" placeholder="e.g. Madhya Pradesh"></div>
            
            <div class="mb-3"><label class="form-label">Password <span style="color:#dc2626;">*</span></label><input type="password" name="password" class="form-control" required minlength="6"></div>
            <div class="mb-3"><label class="form-label">Confirm Password <span style="color:#dc2626;">*</span></label><input type="password" name="confirm_password" class="form-control" required minlength="6"></div>

            <input type="hidden" name="referral_code" value="<?= isset($_SESSION['referral_code']) ? htmlspecialchars($_SESSION['referral_code']) : '' ?>">

            <div class="referral-info" id="referralInfo">
                <i class="fas fa-gift"></i> <strong>🎉 Referral Code Applied:</strong>
                <span style="font-weight:700; color:#1e40af;"><?= isset($_SESSION['referral_code']) ? htmlspecialchars($_SESSION['referral_code']) : '' ?></span><br>
                <small style="color:#64748b;">You will get a special bonus on registration!</small>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="submitBtn">Create Account</button>
        </form>

        <p class="text-center mt-4">Already have an account? <a href="login.php" class="login-link">Sign in</a></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phoneInput');
    const phoneStatus = document.getElementById('phoneStatus');
    const form = document.getElementById('registerForm');

    phoneInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length > 10) this.value = this.value.slice(0, 10);
        validatePhone(this.value);
    });

    phoneInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        const digits = pastedText.replace(/[^0-9]/g, '').slice(0, 10);
        this.value = digits;
        validatePhone(digits);
    });

    phoneInput.addEventListener('keypress', function(e) {
        if (!/[0-9]/.test(e.key)) { e.preventDefault(); return; }
        if (this.value.length === 0 && !/[6-9]/.test(e.key)) {
            e.preventDefault();
            phoneStatus.className = 'phone-status invalid';
            phoneStatus.innerHTML = '❌ पहला अंक 6, 7, 8, या 9 होना चाहिए';
            setTimeout(() => { if (phoneInput.value.length === 0) phoneStatus.className = 'phone-status'; }, 2000);
            return;
        }
    });

    function validatePhone(value) {
        if (value.length === 0) {
            phoneStatus.className = 'phone-status'; phoneStatus.innerHTML = '';
            phoneInput.classList.remove('valid-phone', 'invalid-phone'); return;
        }
        if (value.length < 10) {
            phoneStatus.className = 'phone-status invalid';
            phoneStatus.innerHTML = `⏳ ${10 - value.length} और अंक डालें`;
            phoneInput.classList.remove('valid-phone'); phoneInput.classList.add('invalid-phone'); return;
        }
        if (value.length === 10) {
            if (/^[6-9][0-9]{9}$/.test(value)) {
                phoneStatus.className = 'phone-status valid';
                phoneStatus.innerHTML = '✅ सही Mobile Number';
                phoneInput.classList.remove('invalid-phone'); phoneInput.classList.add('valid-phone');
            } else {
                phoneStatus.className = 'phone-status invalid';
                phoneStatus.innerHTML = '❌ पहला अंक 6, 7, 8, या 9 होना चाहिए';
                phoneInput.classList.remove('valid-phone'); phoneInput.classList.add('invalid-phone');
            }
        }
    }

    form.addEventListener('submit', function(e) {
        if (!/^[6-9][0-9]{9}$/.test(phoneInput.value)) {
            e.preventDefault();
            phoneStatus.className = 'phone-status invalid';
            phoneStatus.innerHTML = '❌ सही 10 digit Mobile Number डालें (6/7/8/9 से शुरू)';
            phoneInput.focus(); return false;
        }
    });
});
</script>

<?php include 'footer.php'; ?>
