<?php
require_once 'db.php';
require_once 'functions.php';
if(isset($_SESSION['user_id'])) header("Location: dashboard.php");

$error = '';
$referral_code = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$readonly = !empty($referral_code) ? 'readonly' : '';
$success_msg = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $ref_code = generateReferralCode();
    $ref_by = null;

    // Phone validation: exactly 10 digits
    if(!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "❌ Phone number must be exactly 10 digits!";
    } else {
        $input_ref = trim($_POST['referral_code'] ?? '');
        if(!empty($input_ref)) {
            $ref_by = getReferrerIdByCode($pdo, $input_ref);
        }

        try {
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, city, referral_code, referred_by, role, status, coins) 
                                   VALUES (?,?,?,?,?,?,?, 'user', 'active', 0)");
            $stmt->execute([$name, $email, $password, $phone, $city, $ref_code, $ref_by]);
            $new_user_id = $pdo->lastInsertId();

            // Coin reward logic
            $coin_amount = 100;

            // 1. Give coins to the new user
            $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$coin_amount, $new_user_id]);

            // 2. Give coins to the referrer (if any)
            if($ref_by) {
                $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$coin_amount, $ref_by]);
                // Optionally log coin transactions? Not required now.
            }

            $success_msg = "✅ Registration successful! You and your referrer (if any) each received $coin_amount coins!";
            // Redirect after a short delay or directly to login with message
            header("Location: login.php?msg=" . urlencode($success_msg));
            exit;
        } catch(PDOException $e) {
            if(str_contains($e->getMessage(), 'email')) $error = "❌ Email already exists!";
            else $error = "❌ Error: ".$e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Register</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script>
function validateForm() {
    var phone = document.getElementById('phone').value;
    var phoneRegex = /^[0-9]{10}$/;
    if(!phoneRegex.test(phone)) {
        alert('Phone number must be exactly 10 digits!');
        return false;
    }
    return true;
}
</script>
</head>
<body>
<div class="container mt-5" style="max-width:500px;">
    <h2>Register</h2>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <?php if(isset($_GET['msg'])) echo "<div class='alert alert-success'>".htmlspecialchars($_GET['msg'])."</div>"; ?>
    <form method="POST" onsubmit="return validateForm()">
        <input type="text" name="name" placeholder="Full Name" class="form-control mb-2" required>
        <input type="email" name="email" placeholder="Email" class="form-control mb-2" required>
        <input type="text" name="city" placeholder="Your City (e.g. Indore)" class="form-control mb-2" required>
        <input type="password" name="password" placeholder="Password" class="form-control mb-2" required minlength="4">
        <input type="text" name="phone" id="phone" placeholder="Phone (10 digits)" class="form-control mb-2" required maxlength="10" pattern="[0-9]{10}" title="Must be exactly 10 digits">
        <div class="input-group mb-2">
            <span class="input-group-text bg-light"><i class="fas fa-link"></i></span>
            <input type="text" name="referral_code" placeholder="Referral Code" class="form-control" value="<?= htmlspecialchars($referral_code) ?>" <?= $readonly ?>>
            <?php if($readonly): ?>
                <span class="input-group-text bg-warning text-dark"><i class="fas fa-lock"></i></span>
            <?php endif; ?>
        </div>
        <?php if($readonly): ?>
            <small class="text-muted">🔒 Referral code is locked (auto-filled from your invitation link).</small>
        <?php endif; ?>
        <button class="btn btn-primary w-100">Register</button>
        <p class="mt-2">Already have account? <a href="login.php">Login</a></p>
    </form>
</div>
</body>
</html>
