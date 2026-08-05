<?php
// ============================================================
// 📝 Register – Prevent Duplicate Email
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
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $ref_code_post = trim($_POST['ref_code'] ?? '');

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "❌ All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "❌ Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "❌ Password must be at least 6 characters.";
    } else {
        // ✅ CHECK DUPLICATE EMAIL (Most Important)
        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        if ($check_stmt->rowCount() > 0) {
            $error = "❌ This email is already registered. Please use a different email or <a href='login.php'>login</a>.";
        } else {
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

            // Insert new user
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password, referral_code, referred_by, role, status, created_at, coins)
                VALUES (?, ?, ?, ?, ?, ?, 'user', 'active', NOW(), 0)
            ");
            $stmt->execute([$name, $email, $phone, $hashed, $referral_code, $referred_by]);

            $success = "✅ Registration successful! You can now <a href='login.php'>login</a>.";
            // Optionally, automatically log in or redirect
            // header("Location: login.php?msg=registered");
            // exit;
        }
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
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
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
