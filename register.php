<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

$message = "";

// ⚙️ ऑटो-पैच: चेक करें कि यूजर्स टेबल में referred_by कॉलम है या नहीं, नहीं तो जोड़ देगा
try {
    $checkRefColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'referred_by'");
    if ($checkRefColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN referred_by VARCHAR(100) DEFAULT NULL");
    }
} catch (PDOException $e) {}

// 🔗 यूआरएल से रेफरल कोड (यूजरनेम) ऑटो-कैप्चर करना
$ref_code = "";
if (isset($_GET['ref'])) {
    $ref_code = trim($_GET['ref']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $referred_by = !empty($_POST['referred_by']) ? trim($_POST['referred_by']) : null;

    if (!empty($username) && !empty($email) && !empty($password)) {
        try {
            // चेक करें कि ईमेल पहले से तो नहीं है
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $message = "<div class='alert alert-danger'>Email already registered under another node core.</div>";
            } else {
                // नया यूजर रजिस्टर करना (referred_by के साथ)
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, referred_by) VALUES (:username, :email, :password, 'user', 'active', :referred_by)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':referred_by', $referred_by);

                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success'>Registration successful! Redirecting to login terminal...</div>";
                    header("Refresh:2;url=login.php");
                }
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Database Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>All registration fields are required.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Terminal // Prime Property</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #f8fafc; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .register-box { background: #1e293b; border: 1px solid #334155; padding: 40px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        h2 { margin: 0 0 5px 0; font-size: 24px; color: #6366f1; text-align: center; }
        p { margin: 0 0 25px 0; color: #94a3b8; font-size: 14px; text-align: center; }
        label { display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: bold; margin-top: 15px; }
        input { width: 94%; padding: 12px; margin-top: 5px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; font-size: 14px; }
        input:focus { border-color: #6366f1; outline: none; }
        .btn-submit { background: #4f46e5; color: white; width: 100%; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 25px; font-size: 15px; }
        .btn-submit:hover { background: #4338ca; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; font-weight: 600; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }
    </style>
</head>
<body>

<div class="register-box">
    <h2>Create New Node</h2>
    <p>Establish your credentials in the framework ecosystem</p>

    <?php echo $message; ?>

    <form action="register.php" method="POST">
        <label>Username</label>
        <input type="text" name="username" placeholder="Choose unique identity" required>

        <label>Email Endpoint</label>
        <input type="email" name="email" placeholder="name@domain.com" required>

        <label>Security Key (Password)</label>
        <input type="password" name="password" placeholder="••••••••" required>

        <!-- 🔗 रेफरल इनपुट: लिंक से आने पर यह ऑटोमैटिकली नाम भर देगा -->
        <label>Referral Node Partner (Optional)</label>
        <input type="text" name="referred_by" value="<?php echo htmlspecialchars($ref_code); ?>" placeholder="Partner Username">

        <button type="submit" class="btn-submit">Initialize Account</button>
    </form>
    
    <div style="text-align: center; margin-top: 20px; font-size: 13px;">
        <a href="login.php" style="color: #6366f1; text-decoration: none;">Return to Login Core</a>
    </div>
</div>

</body>
</html>
