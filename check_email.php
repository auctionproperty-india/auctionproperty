<?php
// ============================================================
// ✅ यह File Register Form से आए Email को Check करेगी
// ============================================================

require_once 'db.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    echo "<h3>🔍 Checking Email: <strong>$email</strong></h3>";
    
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email ILIKE ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if($user) {
        echo "❌ User FOUND in database: <br>";
        echo "ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}<br>";
    } else {
        echo "✅ User NOT FOUND. You can register with this email.<br>";
    }
} else {
    // Show a test form
    ?>
    <h3>🔍 Test Email Checker</h3>
    <p>Enter the email you are trying to register with:</p>
    <form method="POST">
        <input type="email" name="email" placeholder="Enter email" style="width:300px; padding:10px;">
        <button type="submit" style="padding:10px 20px;">Check</button>
    </form>
    <?php
}
?>
