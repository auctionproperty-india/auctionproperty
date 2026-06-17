<?php
session_start();
include 'db.php';

if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit(); }

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && ($password === $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid Email or Password!";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Sign In // Prime Property India</title></head>
<body style="background:#0f172a; color:white; font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh;">
    <form method="POST" style="background:#1e293b; padding:30px; border-radius:10px; width:300px;">
        <h2 style="text-align:center; color:#4f46e5;">Sign In</h2>
        <p style="color:red;"><?php echo $error; ?></p>
        <input type="email" name="email" placeholder="Email" required style="width:100%; padding:10px; margin:10px 0;">
        <input type="password" name="password" placeholder="Password" required style="width:100%; padding:10px; margin:10px 0;">
        <button type="submit" style="width:100%; padding:10px; background:#4f46e5; border:none; color:white; cursor:pointer;">Login</button>
    </form>
</body>
</html>
