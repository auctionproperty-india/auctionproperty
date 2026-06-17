<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        try {
            // डेटाबेस से यूज़र को निकालना
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // पासवर्ड मैच करना (बिना हैश वाले सिंपल पासवर्ड के लिए)
            if ($user && $password === $user['password']) {
                
                // 🛑 CRITICAL CHECK: अगर एडमिन ने यूज़र को ब्लॉक (Disable) किया है
                if (isset($user['status']) && $user['status'] === 'blocked') {
                    $error = "❌ Your account access has been disabled by the administrator core.";
                } else {
                    // अगर एक्टिव है तो लॉगिन होने दें
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['username'] = $user['username'];

                    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'sub_admin') {
                        header("Location: admin_dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();
                }
            } else {
                $error = "❌ Invalid email or cryptographic password matrix.";
            }
        } catch (PDOException $e) {
            $error = "❌ System Matrix Error: " . $e->getMessage();
        }
    } else {
        $error = "❌ Please populate all required authentication parameters.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login // Prime Property</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #0f172a; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; height: 100vh; color: #f8fafc; }
        .login-box { background: #1e293b; padding: 40px; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); border: 1px solid #334155; width: 100%; max-width: 400px; }
        h2 { margin: 0 0 10px 0; font-size: 26px; font-weight: 800; text-align: center; color: #6366f1; }
        p { text-align: center; color: #94a3b8; font-size: 14px; margin-bottom: 30px; }
        label { font-weight: 600; display: block; margin-top: 15px; font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        input { width: 93%; padding: 12px; margin-top: 6px; border: 1px solid #334155; border-radius: 8px; background: #0f172a; color: white; font-size: 14px; }
        input:focus { border-color: #6366f1; outline: none; }
        .btn-submit { background: #4f46e5; color: white; border: none; padding: 14px; font-size: 15px; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 25px; width: 100%; transition: 0.3s; }
        .btn-submit:hover { background: #4338ca; box-shadow: 0 0 20px rgba(79, 70, 229, 0.5); }
        .error-box { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 12px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 20px; text-align: center; line-height: 1.4; }
        .footer-link { text-align: center; margin-top: 20px; font-size: 13px; color: #64748b; }
        .footer-link a { color: #6366f1; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Secure Gateway</h2>
    <p>Enter your authorization tokens to session initialize.</p>

    <?php if (!empty($error)): ?>
        <div class="error-box"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <label>Identity Email Node</label>
        <input type="email" name="email" placeholder="name@domain.com" required>

        <label>Security Access Key</label>
        <input type="password" name="password" placeholder="••••••••" required>

        <button type="submit" name="login" class="btn-submit">Initialize Core Session</button>
    </form>
    
    <div class="footer-link">
        Don't have an asset token? <a href="register.php">Register Node</a>
    </div>
</div>

</body>
</html>
