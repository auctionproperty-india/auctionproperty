<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 🔗 db.php को लोड करना ताकि SSL कनेक्शन हर जगह काम करे
include 'db.php';

$message = "";

// अगर यूजर पहले से लॉगइन है, तो उसे सीधा डैशबोर्ड पर भेजें
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!empty($email) && !empty($password)) {
        try {
            // 🔍 डेटाबेस में यूजर को ढूंढना (db.php का $conn ऑब्जेक्ट इस्तेमाल हो रहा है)
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // स्टेटस चेक करना (ब्लॉक तो नहीं है)
                if (isset($user['status']) && $user['status'] === 'blocked') {
                    $message = "<div class='alert alert-danger'>❌ Your node account has been suspended. Contact support.</div>";
                } else {
                    // 🔑 पासवर्ड मैच करना (अगर आपने प्लेन टेक्स्ट रखा है तो डायरेक्ट मैच, अगर हैश किया है तो password_verify लगेगा)
                    // अभी आपके पुराने सिस्टम के अनुसार यह प्लेन टेक्स्ट पासवर्ड चेक कर रहा है
                    if ($password === $user['password']) {
                        // सेशन्स सेट करना
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'] ?? 'user';

                        $message = "<div class='alert alert-success'>✓ Access Granted! Syncing terminal...</div>";
                        
                        // 📊 एडमिन है तो एडमिन डैशबोर्ड, यूजर है तो नॉर्मल डैशबोर्ड
                        if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'sub_admin') {
                            header("Refresh:1;url=admin_dashboard.php");
                        } else {
                            header("Refresh:1;url=dashboard.php");
                        }
                        exit();
                    } else {
                        $message = "<div class='alert alert-danger'>❌ Encryption Key Mismatch (Wrong Password).</div>";
                    }
                }
            } else {
                $message = "<div class='alert alert-danger'>❌ Email endpoint not found in active core.</div>";
            }
        } catch (PDOException $e) {
            // रेंडर पोस्टग्रेस के किसी भी अचानक एरर को पकड़ने के लिए
            $message = "<div class='alert alert-danger'>Matrix Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>⚠️ Please enter both credentials.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Property India // Secure Login Terminal</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #f8fafc; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #1e293b; border: 1px solid #334155; padding: 40px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        h2 { margin: 0 0 5px 0; font-size: 24px; color: #4f46e5; text-align: center; font-weight: 800; letter-spacing: -0.5px; }
        p { margin: 0 0 25px 0; color: #94a3b8; font-size: 14px; text-align: center; }
        label { display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: bold; margin-top: 15px; letter-spacing: 0.5px; }
        input { width: 94%; padding: 12px; margin-top: 5px; background: #0f172a; border: 1px solid #334155; color: white; border-radius: 6px; font-size: 14px; }
        input:focus { border-color: #6366f1; outline: none; }
        .btn-submit { background: #4f46e5; color: white; width: 100%; padding: 12px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 25px; font-size: 15px; transition: background 0.3s; }
        .btn-submit:hover { background: #4338ca; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; font-weight: 600; text-align: center; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Prime Property India</h2>
    <p>Secure Terminal Portal // Core Gateway</p>

    <?php if(!empty($message)) echo $message; ?>

    <form action="login.php" method="POST">
        <label>Corporate Email Endpoint</label>
        <input type="email" name="email" placeholder="name@domain.com" required>

        <label>Security Access Key (Password)</label>
        <input type="password" name="password" placeholder="••••••••" required>

        <button type="submit" class="btn-submit">Authenticate Account Access</button>
    </form>
    
    <div style="text-align: center; margin-top: 25px; font-size: 13px; color: #94a3b8;">
        New Node? <a href="register.php" style="color: #6366f1; text-decoration: none; font-weight: 600;">Create Free Account</a>
    </div>
</div>

</body>
</html>
