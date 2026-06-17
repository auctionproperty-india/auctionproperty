<?php
include 'db.php';
$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    if($stmt->execute([$_POST['username'], $_POST['email'], $_POST['password']])) {
        $msg = "Account ban gaya! Ab <a href='login.php'>Login karein</a>";
    } else {
        $msg = "Error: Email pehle se exist karti hai.";
    }
}
?>
<!DOCTYPE html>
<html>
<body style="background:#0f172a; color:white; font-family:sans-serif; text-align:center; padding-top:50px;">
    <form method="POST" style="display:inline-block; background:#1e293b; padding:30px; border-radius:10px;">
        <h2>Register</h2>
        <p><?php echo $msg; ?></p>
        <input type="text" name="username" placeholder="Username" required style="display:block; width:250px; padding:10px; margin:10px auto;">
        <input type="email" name="email" placeholder="Email" required style="display:block; width:250px; padding:10px; margin:10px auto;">
        <input type="password" name="password" placeholder="Password" required style="display:block; width:250px; padding:10px; margin:10px auto;">
        <button type="submit" style="background:#10b981; border:none; padding:10px 20px; color:white; cursor:pointer;">Register</button>
    </form>
</body>
</html>
