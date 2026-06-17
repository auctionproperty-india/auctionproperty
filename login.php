<?php
session_start();
include 'db.php';
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $user = $stmt->fetch();
    if($user && $_POST['password'] === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: dashboard.php");
    } else {
        $error = "Galat email ya password!";
    }
}
?>
<!DOCTYPE html>
<html>
<body style="background:#0f172a; color:white; font-family:sans-serif; text-align:center; padding-top:50px;">
    <form method="POST" style="display:inline-block; background:#1e293b; padding:30px; border-radius:10px;">
        <h2>Login</h2>
        <p style="color:red;"><?php echo $error; ?></p>
        <input type="email" name="email" placeholder="Email" required style="display:block; width:250px; padding:10px; margin:10px auto;">
        <input type="password" name="password" placeholder="Password" required style="display:block; width:250px; padding:10px; margin:10px auto;">
        <button type="submit" style="background:#4f46e5; border:none; padding:10px 20px; color:white; cursor:pointer;">Login</button>
    </form>
</body>
</html>
