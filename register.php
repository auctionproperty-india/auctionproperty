<?php
// ============================================================
// 📝 Register – All Fields Required + 100 Coins + Auto-Login
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$error = '';
$success = '';

// ---- Check if referral code exists in URL ----
$ref_code = isset($_GET['ref']) ? trim($_GET['ref']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $ref_code_post = trim($_POST['ref_code'] ?? '');

    // ---- Validation ----
    $errors = [];

    // 1. All fields required
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email)) $errors[] = "Email is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($confirm)) $errors[] = "Please confirm your password.";

    // 2. Password match
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    // 3. Password length
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    // 4. Mobile number must be exactly 10 digits (starting with 6-9)
    if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
        $errors[] = "Phone number must be a valid 10-digit Indian mobile number (starting with 6,7,8,9).";
    }

    // 5. Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // 6. Check duplicate email
    if (empty($errors)) {
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->rowCount() > 0) {
            $errors[] = "This email is already registered. Please use a different email or <a href='login.php'>login</a>.";
        }
    }

    // ---- If no errors, proceed ----
    if (empty($errors)) {
        // Generate referral code
        $referral_code = strtoupper(substr(md5(uniqid()), 0, 8));
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $referred_by = null;

        // Check referral code from URL or form
        $ref_to_use = !empty($ref_code_post) ? $ref_code_post : $ref_code;
        if (!empty($ref_to_use)) {
            $ref_stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $ref_stmt->execute([$ref_to_use]);
            $ref_user = $ref_stmt->fetch();
            if ($ref_user) $referred_by = $ref_user['id'];
        }

        // ✅ Insert with 100 coins
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, referral_code, referred_by, role, status, created_at, coins, city)
            VALUES (?, ?, ?, ?, ?, ?, 'user', 'active', NOW(), 100, ?)
        ");
        $stmt->execute([$name, $email, $phone, $hashed, $referral_code, $referred_by, $city]);

        // ✅ AUTO-LOGIN
        $_SESSION['user_id'] = $pdo->lastInsertId();
        $_SESSION['user_name'] = $name;
        $_SESSION['role'] = 'user';

        // ✅ Set success message in session for dashboard
        $_SESSION['registration_success'] = "✅ Welcome! You have received 100 bonus coins.";

        // ✅ Redirect to user dashboard
        header("Location: user_dashboard.php");
        exit;
    } else {
        $error = implode("<br>", $errors);
    }
}

include 'header.php'; 
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card-premium">
                <h4><i class="fas fa-user-plus me-2"></i>Register</h4>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="tel" name="phone" class="form-control" required maxlength="10" pattern="[6-9][0-9]{9}">
                        <small class="text-muted">Enter 10-digit Indian mobile number (starting with 6,7,8,9).</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">City *</label>
                        <input type="text" name="city" class="form-control" required placeholder="Enter your city (e.g. Indore, Mumbai, Delhi)">
                        <small class="text-muted">Properties from your city will appear on your dashboard.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <?php if (!empty($ref_code)): ?>
                        <input type="hidden" name="ref_code" value="<?= htmlspecialchars($ref_code) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary w-100">Register</button>
                    <p class="mt-3 text-center">Already have an account? <a href="login.php">Login</a></p>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
