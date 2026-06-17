<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$message = "";

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<p class='msg-err'>System Error: " . $e->getMessage() . "</p>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile']) && $user_role !== 'admin' && $user_role !== 'sub_admin') {
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $bank_name = trim($_POST['bank_name']);
    $account_no = trim($_POST['account_no']);
    $ifsc_code = trim($_POST['ifsc_code']);

    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

    $adhaar_file = $user['adhaar_file'] ?? '';
    $pan_file = $user['pan_file'] ?? '';
    $bank_file = $user['bank_file'] ?? '';

    if (!empty($_FILES['adhaar']['name'])) {
        $adhaar_file = $upload_dir . time() . "_" . $_FILES['adhaar']['name'];
        move_uploaded_file($_FILES['adhaar']['tmp_name'], $adhaar_file);
    }
    if (!empty($_FILES['pan']['name'])) {
        $pan_file = $upload_dir . time() . "_" . $_FILES['pan']['name'];
        move_uploaded_file($_FILES['pan']['tmp_name'], $pan_file);
    }
    if (!empty($_FILES['bank_copy']['name'])) {
        $bank_file = $upload_dir . time() . "_" . $_FILES['bank_copy']['name'];
        move_uploaded_file($_FILES['bank_copy']['tmp_name'], $bank_file);
    }

    try {
        $up_query = "UPDATE users SET phone = :phone, address = :address, bank_name = :bank_name, 
                     account_no = :account_no, ifsc_code = :ifsc_code, adhaar_file = :adhaar, 
                     pan_file = :pan, bank_file = :bank, kyc_status = 'pending' WHERE id = :id";
        
        $up_stmt = $conn->prepare($up_query);
        $up_stmt->bindParam(':phone', $phone);
        $up_stmt->bindParam(':address', $address);
        $up_stmt->bindParam(':bank_name', $bank_name);
        $up_stmt->bindParam(':account_no', $account_no);
        $up_stmt->bindParam(':ifsc_code', $ifsc_code);
        $up_stmt->bindParam(':adhaar', $adhaar_file);
        $up_stmt->bindParam(':pan', $pan_file);
        $up_stmt->bindParam(':bank', $bank_file);
        $up_stmt->bindParam(':id', $user_id);
        
        if ($up_stmt->execute()) {
            $message = "<p class='msg-ok'>Profile parameters updated successfully. Certification pending.</p>";
            header("Refresh:2");
        }
    } catch (PDOException $e) {
        $message = "<p class='msg-err'>Update Interrupted: " . $e->getMessage() . "</p>";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);

    if ($user['password'] === $old_password) {
        try {
            $pass_stmt = $conn->prepare("UPDATE users SET password = :pass WHERE id = :id");
            $pass_stmt->bindParam(':pass', $new_password);
            $pass_stmt->bindParam(':id', $user_id);
            if ($pass_stmt->execute()) {
                $message = "<p class='msg-ok'>Security token / password modified successfully.</p>";
            }
        } catch (PDOException $e) {
            $message = "<p class='msg-err'>Security Update Error: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p class='msg-err'>Invalid master password provided.</p>";
    }
}

$back_link = ($user_role === 'admin' || $user_role === 'sub_admin') ? "admin_dashboard.php" : "dashboard.php";
$isAdmin = ($user_role === 'admin' || $user_role === 'sub_admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Profile // Prime Property</title>
    <style>
        body { font-family: 'Segoe UI', Roboto, sans-serif; background-color: <?php echo $isAdmin ? '#0f172a' : '#f8fafc'; ?>; margin: 0; padding: 40px; color: <?php echo $isAdmin ? '#f8fafc' : '#1e293b'; ?>; }
        .wrapper { max-width: 650px; margin: 0 auto; background: <?php echo $isAdmin ? '#1e293b' : '#ffffff'; ?>; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border: 1px solid <?php echo $isAdmin ? '#334155' : '#e2e8f0'; ?>; }
        h2, h3 { font-weight: 700; border-bottom: 1px solid <?php echo $isAdmin ? '#334155' : '#e2e8f0'; ?>; padding-bottom: 10px; margin-top: 30px; }
        label { font-weight: 600; display: block; margin-top: 15px; font-size: 14px; color: <?php echo $isAdmin ? '#94a3b8' : '#475569'; ?>; }
        input, textarea { width: 96%; padding: 12px; margin-top: 6px; border: 1px solid <?php echo $isAdmin ? '#334155' : '#cbd5e1'; ?>; border-radius: 6px; background: <?php echo $isAdmin ? '#0f172a' : '#fff'; ?>; color: <?php echo $isAdmin ? '#fff' : '#000'; ?>; font-size: 14px; }
        input:focus { border-color: #6366f1; outline: none; }
        .btn-action { background: #4f46e5; color: white; border: none; padding: 14px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; margin-top: 20px; width: 100%; transition: 0.3s; }
        .btn-action:hover { background: #4338ca; box-shadow: 0 0 15px rgba(79, 70, 229, 0.4); }
        .msg-ok { background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #22c55e; padding: 12px; border-radius: 6px; font-weight: 600; }
        .msg-err { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 12px; border-radius: 6px; font-weight: 600; }
        .back-link { text-decoration: none; color: #6366f1; font-weight: bold; font-size: 14px; }
    </style>
</head>
<body>

<div class="wrapper">
    <a href="<?php echo $back_link; ?>" class="back-link">← Return to Dashboard</a>
    
    <?php if ($isAdmin): ?>
        <h2 style="text-shadow: 0 0 10px #38bdf8;">⚙️ Corporate Identity Setup</h2>
        <?php echo $message; ?>
        <form action="profile.php" method="POST">
            <h3>Update Core Authentication Token</h3>
            <label>Current Security Key</label>
            <input type="password" name="old_password" placeholder="Verify Active Password" required>
            <label>New Elite Password</label>
            <input type="password" name="new_password" placeholder="Establish New Password" required>
            <button type="submit" name="change_password" class="btn-action">Commit Security Changes</button>
        </form>
    <?php else: ?>
        <h2>👤 Account Settings & Verification</h2>
        <?php echo $message; ?>
        <form action="profile.php" method="POST" enctype="multipart/form-data">
            <h3>Merchant & Financial Dossier</h3>
            <label>System Alias</label>
            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="background:#f1f5f9; color:#64748b;">
            <label>Corporate Communications Email</label>
            <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:#f1f5f9; color:#64748b;">
            <label>Contact Number</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
            <label>Physical Address</label>
            <textarea name="address" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
            <label>Banking Institution</label>
            <input type="text" name="bank_name" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>" required>
            <label>Account Number</label>
            <input type="text" name="account_no" value="<?php echo htmlspecialchars($user['account_no'] ?? ''); ?>" required>
            <label>IFSC Clearing Code</label>
            <input type="text" name="ifsc_code" value="<?php echo htmlspecialchars($user['ifsc_code'] ?? ''); ?>" required>
            <h3>📁 Verification Documentation (KYC)</h3>
            <label>National ID (Aadhaar Ledger)</label>
            <input type="file" name="adhaar" accept="image/*,application/pdf">
            <label>Taxation Token (PAN Record)</label>
            <input type="file" name="pan" accept="image/*,application/pdf">
            <label>Financial Affirmation (Passbook Copy)</label>
            <input type="file" name="bank_copy" accept="image/*,application/pdf">
            <button type="submit" name="update_profile" class="btn-action">Deploy KYC Information</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
