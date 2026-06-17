<?php
include 'db.php';
$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
    if($stmt->execute([$username, $email, $password])){
        $msg = "Account created! <a href='login.php'>Login here</a>";
    } else {
        $msg = "Error creating account.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Register // Prime Property India</title></head>
<body style="background:#0f172a; color:white; font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh;">
    <form method="POST" style="background:#1e293b; padding:30px; border-radius:10px; width:300px;">
        <h2 style="text-align:center; color:#10b981;">Register Node</h2>
        <p><?php echo $msg; ?></p>
        <input type="text" name="username" placeholder="Username" required style="width:100%; padding:10px; margin:10px 0;">
        <input type="email" name="email" placeholder="Email" required style="width:100%; padding:10px; margin:10px 0;">
        <input type="password" name="password" placeholder="Password" required style="width:100%; padding:10px; margin:10px 0;">
        <button type="submit" style="width:100%; padding:10px; background:#10b981; border:none; color:white; cursor:pointer;">Create Account</button>
    </form>
</body>
</html>
