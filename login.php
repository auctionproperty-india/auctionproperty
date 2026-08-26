<?php
require_once __DIR__ . '/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_super_admin'] = !empty($user['is_super_admin']) ? true : false;

            if ($user['role'] == 'admin' || $user['role'] == 'sub_admin') {
                header("Location: admin_dashboard.php");
            } elseif ($user['role'] == 'sales') {
                header("Location: sales_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

include 'header.php';
?>
<style>
    .login-container {
        min-height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .login-card {
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(8px);
        border-radius: 30px;
        padding: 40px 35px;
        max-width: 450px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.3);
    }
    .login-card h2 {
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 8px;
    }
    .login-card p.sub {
        color: #64748b;
        margin-bottom: 25px;
        font-size: 0.95rem;
    }
    .login-card .form-label {
        font-weight: 600;
        color: #1e293b;
    }
    .login-card .form-control {
        border-radius: 12px;
        padding: 12px 16px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .login-card .form-control:focus {
        border-color: #1e3a8a;
        box-shadow: 0 0 0 3px rgba(30,58,138,0.1);
    }
    .login-card .btn-primary {
        background: #1e3a8a;
        border: none;
        padding: 12px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .login-card .btn-primary:hover {
        background: #2563eb;
        transform: translateY(-2px);
    }
    .login-card .register-link {
        color: #1e3a8a;
        font-weight: 600;
        text-decoration: none;
    }
    .login-card .register-link:hover {
        text-decoration: underline;
    }
    .login-card .error-msg {
        background: #fef2f2;
        border-left: 4px solid #dc2626;
        padding: 12px;
        border-radius: 8px;
        color: #991b1b;
        font-size: 0.9rem;
    }
    @media (max-width: 576px) {
        .login-card { padding: 30px 20px; }
    }
</style>
<div class="login-container">
    <div class="login-card">
        <h2>Welcome Back</h2>
        <p class="sub">Sign in to your account</p>

        <?php if ($error): ?>
            <div class="error-msg mb-3"><?= htmlspecialchars($error) ?></div>
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
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>

        <p class="text-center mt-4">
            Don't have an account? <a href="register.php" class="register-link">Create one</a>
        </p>
    </div>
</div>
<?php include 'footer.php'; ?>
