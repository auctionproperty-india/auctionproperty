<?php
require_once 'db.php';
session_start();
?>
<!DOCTYPE html>
<html>
<body>
    <h2>Sign In</h2>
    <form method="POST">
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Login</button>
    </form>
    <br><a href="index.php">Back to Home</a>
</body>
</html>
