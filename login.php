<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(isset($_SESSION['user_id'])) {
    if($_SESSION['role'] == 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit;
}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_super_admin, status, wallet_balance FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if($user && password_verify($password, $user['password'])){
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        if(!empty($user['is_super_admin']) && $user['is_super_admin']) {
            $_SESSION['is_super_admin'] = true;
        } else {
            $_SESSION['is_super_admin'] = false;
        }
        // ✅ Log Login Activity
        logActivity($pdo, $user['id'], 'login');
        if($user['role'] == 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: user_dashboard.php");
        }
        exit;
    } else {
        $error = "❌ Invalid Email/Password or Account Disabled!";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width:400px;">
    <div class="card p-4 shadow-lg border-0 rounded-4">
        <h3 class="text-center mb-3">🔐 Login</h3>
        <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <?php if(isset($_GET['msg'])) echo "<div class='alert alert-success'>✅ Registration successful! Please login.</div>"; ?>
        <form method="POST">
            <div class="mb-3"><input type="email" name="email" placeholder="Email" class="form-control" required></div>
            <div class="mb-3"><input type="password" name="password" placeholder="Password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
            <p class="mt-3 text-center">New user? <a href="register.php">Register here</a></p>
        </form>
    </div>
</div>
</body>
</html>
