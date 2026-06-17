<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

$message = "";

// URL से सीक्रेट रेफरल कोड कैप्चर करना
$ref_code = "";
if (isset($_GET['ref'])) {
    $ref_code = trim($_GET['ref']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // हिडन फ़ील्ड से सीक्रेट कोड उठाना
    $secret_ref = !empty($_POST['hidden_ref']) ? trim($_POST['hidden_ref']) : null;
    $final_referred_by = null;

    if (!empty($username) && !empty($email) && !empty($password)) {
        try {
            // ईमेल डुप्लीकेट चेक
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $message = "<div class='alert alert-danger'>Email already registered under another node core.</div>";
            } else {
                // अगर कोई सीक्रेट कोड आया है, तो असली यूजरनेम ढूंढना जिसके नीचे जोड़ना है
                if (!empty($secret_ref)) {
                    // कोड से 'REF' हटाकर ID निकालना (जैसे REF15 -> ID 15)
                    $mapped_id = str_replace('REF', '', $secret_ref);
                    if (is_numeric($mapped_id)) {
                        $find_stmt = $conn->prepare("SELECT username FROM users WHERE id = :id");
                        $find_stmt->bindParam(':id', $mapped_id);
                        $find_stmt->execute();
                        $referred_user = $find_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($referred_user) {
                            $final_referred_by = $referred_user['username'];
                        }
                    }
                }

                // नया यूजर इन्सर्ट करना
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status, referred_by) VALUES (:username, :email, :password, 'user', 'active', :referred_by)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':referred_by', $final_referred_by);

                if ($stmt->execute()) {
                    $message = "<div class='alert alert-success'>Registration successful! Gateway verified. Redirecting to login...</div>";
                    header("Refresh:2;url=login.php");
                }
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Database Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>All fields are required.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Register Terminal</title>
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
    <h2>Create Server Node</h2>
    <p>Establish corporate identity parameters directly with the primary ledger.</p>

    <?php echo $message; ?>

    <form action="register.php" method="POST">
        <input type="hidden" name="hidden_ref" value="<?php echo htmlspecialchars($ref_code); ?>">

        <label>Username</label>
        <input type="text" name="username" placeholder="Choose unique alias" required>

        <label>Email Endpoint</label>
        <input type="email" name="email" placeholder="name@domain.com" required>

        <label>Security Key (Password)</label>
        <input type="password" name="password" placeholder="••••••••" required>

        <button type="submit" class="btn-submit">Initialize Framework Access</button>
    </form>
    
    <div style="text-align: center; margin-top: 20px; font-size: 13px;">
        <a href="login.php" style="color: #6366f1; text-decoration: none;">Return to Login Core</a>
    </div>
</div>

</body>
</html>
