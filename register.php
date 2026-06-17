<?php
require_once 'db.php';
?>
<!DOCTYPE html>
<html>
<body>
    <h2>Create Account</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Full Name" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Register</button>
    </form>
    <br><a href="index.php">Back to Home</a>
</body>
</html>
