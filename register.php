<?php
require_once 'db.php';
require_once 'functions.php';
if(isset($_SESSION['user_id'])) header("Location: dashboard.php");

$error = '';
$referral_code = isset($_GET['ref']) ? trim($_GET['ref']) : '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $ref_code = generateReferralCode();
    $ref_by = null;
    
    // अगर Referral Code मिला तो Referrer ID फेच करें
    $input_ref = trim($_POST['referral_code'] ?? '');
    if(!empty($input_ref)) {
        $ref_by = getReferrerIdByCode($pdo, $input_ref);
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, referral_code, referred_by, role, status) VALUES (?,?,?,?,?,?, 'user', 'active')");
        $stmt->execute([$name, $email, $password, $phone, $ref_code, $ref_by]);
        header("Location: login.php?msg=Registered");
        exit;
    } catch(PDOException $e) {
        if(str_contains($e->getMessage(), 'email')) $error = "❌ Email already exists!";
        else $error = "❌ Error: ".$e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Register</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body>
<div class="container mt-5" style="max-width:500px;">
    <h2>Register</h2>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
        <input type="text" name="name" placeholder="Full Name" class="form-control mb-2" required>
        <input type="email" name="email" placeholder="Email" class="form-control mb-2" required>
        <input type="password" name="password" placeholder="Password" class="form-control mb-2" required>
        <input type="text" name="phone" placeholder="Phone" class="form-control mb-2">
        <input type="text" name="referral_code" placeholder="Referral Code (optional)" class="form-control mb-2" value="<?= htmlspecialchars($referral_code) ?>">
        <button class="btn btn-primary w-100">Register</button>
        <p class="mt-2">Already have account? <a href="login.php">Login</a></p>
    </form>
</div>
</body>
</html>
