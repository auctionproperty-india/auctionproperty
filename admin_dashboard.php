<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

// सुरक्षा जांच: केवल एडमिन या सब-एडमिन ही यहाँ आ सकते हैं
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub_admin')) {
    header("Location: login.php");
    exit();
}

$message = "";

// 🤖 SELF-HEALING DATABASE AUTO-PATCH (यह अपने आप कॉलम जोड़ देगा अगर नहीं होगा तो)
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
        $message = "<div class='alert alert-success'>⚙️ [System Sync]: Database integrity patch applied. 'status' column added successfully!</div>";
    }
} catch (PDOException $e) {
    try {
        $conn->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
    } catch (Exception $ex) {}
}

// 🔥 1. यूजर को डेटाबेस से हमेशा के लिए गायब (DELETE) करने का लॉजिक
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $target_id = $_GET['id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $target_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>🗑️ User Node permanently purged from database ledger. They can now re-register fresh.</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Error deleting user node: " . $e->getMessage() . "</div>";
    }
}

// 2. यूजर का पासवर्ड बदलने का लॉजिक
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_user_password'])) {
    $target_user_id = $_POST['target_user_id'];
    $new_password = trim($_POST['new_password']);
    
    if (!empty($new_password)) {
        try {
            $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->bindParam(':password', $new_password);
            $stmt->bindParam(':id', $target_user_id);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>User password updated successfully!</div>";
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Error updating password: " . $e->getMessage() . "</div>";
        }
    }
}

// 3. यूजर को Enable / Disable (Block/Unblock) करने का लॉजिक
if (isset($_GET['action']) && ($_GET['action'] === 'block' || $_GET['action'] === 'unblock') && isset($_GET['id'])) {
    $target_id = $_GET['id'];
    $action = $_GET['action'];
    $new_status = ($action === 'block') ? 'blocked' : 'active';
    
    try {
        $stmt = $conn->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $new_status);
        $stmt->bindParam(':id', $target_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>User status updated to " . strtoupper($new_status) . " successfully!</div>";
        }
    } catch (PDOException $e) {
        $message = "<div class='alert alert-danger'>Error changing status: " . $e->getMessage() . "</div>";
    }
}

// सभी सामान्य यूजर्स की लिस्ट निकालना (एडमिन को छोड़कर)
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role != 'admin' ORDER BY id DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'>Fetch Error: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Command Core</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; padding: 30px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h2 { font-size: 28px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 5px; }
        .header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #334155; padding-bottom: 20px; }
        .btn-logout { background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-profile { background: #4f46e5; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; margin-right: 10px; }
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; font-size: 14px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }

        .user-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 25px; margin-bottom: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .user-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; padding-bottom: 15px; margin-bottom: 15px; }
        .user-title { font-size: 18px; font-weight: 700; color: #6366f1; }
        
        .grid-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .detail-item { font-size: 13px; color: #94a3b8; }
        .detail-item strong { color: #f8fafc; display: block; margin-bottom: 2px; font-size: 14px; }
        
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .badge-active { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
        .badge-blocked { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        .badge-kyc { background: rgba(245, 158, 11, 0.2); color: #f59e0b; border: 1px solid #f59e0b; }

        .action-zone { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; background: #0f172a; padding: 15px; border-radius: 8px; border: 1px solid #334155; gap: 15px; }
        .password-form { display: flex; gap: 10px; align-items: center; }
        .password-form input { background: #1e293b; border: 1px solid #334155; color: white; padding: 8px 12px; border-radius: 6px; font-size: 13px; }
        .password-form button { background: #4f46e5; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .password-form button:hover { background: #4338ca; }

        .btn-status { padding: 8px 16px; border-radius: 6px; font-weight: bold; text-decoration: none; font-size: 13px; transition: 0.2s; display: inline-block; }
        .btn-block { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid #ef4444; }
        .btn-block:hover { background: #ef4444; color: white; }
        .btn-unblock { background: rgba(16, 185, 129, 0.2); color: #10b981; border: 1px solid #10b981; }
        .btn-unblock:hover { background: #10b981; color: white; }
        
        /* 🗑️ नया डिलीट बटन स्टाइल */
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); margin-left: 8px; }
        .btn-delete:hover { background: #b91c1c; color: white; border-color: #b91c1c; box-shadow: 0 0 15px rgba(185, 28, 28, 0.4); }
        
        .doc-link { color: #38bdf8; text-decoration: none; font-weight: 600; }
        .doc-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <div>
            <h2>Admin Command Center</h2>
            <p style="margin: 0; color: #94a3b8; font-size: 14px;">Monitor all nodes, adjust credentials, and manage system access permissions.</p>
        </div>
        <div>
            <a href="profile.php" class="btn-profile">⚙️ My Admin Profile</a>
            <a href="logout.php" class="btn-logout">Secure Terminate</a>
        </div>
    </div>

    <?php echo $message; ?>

    <h3 style="margin-bottom: 20px; font-size: 20px; border-left: 4px solid #4f46e5; padding-left: 10px;">Registered User Base</h3>

    <?php if (empty($users)): ?>
        <p style="color: #94a3b8;">No registered users found in the framework ecosystem.</p>
    <?php else: ?>
        <?php foreach ($users as $u): ?>
            <div class="user-card">
                <div class="user-header">
                    <div class="user-title">
                        <?php echo htmlspecialchars($u['username']); ?> 
                        <span style="font-size: 13px; color: #64748b; font-weight: normal; margin-left: 10px;">(ID: <?php echo $u['id']; ?>)</span>
                    </div>
                    <div>
                        <span class="badge badge-<?php echo ((isset($u['status']) && $u['status'] === 'blocked') ? 'blocked' : 'active'); ?>">
                            System Access: <?php echo $u['status'] ?? 'active'; ?>
                        </span>
                        <span class="badge badge-kyc" style="margin-left: 5px;">
                            KYC: <?php echo $u['kyc_status'] ?? 'pending'; ?>
                        </span>
                    </div>
                </div>

                <div class="grid-details">
                    <div class="detail-item">
                        <strong>Email Endpoint</strong>
                        <?php echo htmlspecialchars($u['email']); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Mobile Token</strong>
                        <?php echo htmlspecialchars($u['phone'] ?? 'Not Linked'); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Physical Node (Address)</strong>
                        <?php echo htmlspecialchars($u['address'] ?? 'Not Provided'); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Identity Assets (Docs)</strong>
                        Aadhaar: <?php echo !empty($u['adhaar_file']) ? "<a class='doc-link' href='".htmlspecialchars($u['adhaar_file'])."' target='_blank'>View</a>" : "Missing"; ?><br>
                        PAN: <?php echo !empty($u['pan_file']) ? "<a class='doc-link' href='".htmlspecialchars($u['pan_file'])."' target='_blank'>View</a>" : "Missing"; ?>
                    </div>
                </div>

                <div class="grid-details" style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: 8px; border: 1px dashed #334155;">
                    <div class="detail-item">
                        <strong>Bank Entity Name</strong>
                        <?php echo htmlspecialchars($u['bank_name'] ?? 'Not Linked'); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Fiscal Account Code</strong>
                        <?php echo htmlspecialchars($u['account_no'] ?? 'Not Linked'); ?>
                    </div>
                    <div class="detail-item">
                        <strong>IFSC Signature Key</strong>
                        <?php echo htmlspecialchars($u['ifsc_code'] ?? 'Not Linked'); ?>
                    </div>
                    <div class="detail-item">
                        <strong>Cheque/Passbook Asset</strong>
                        <?php echo !empty($u['bank_file']) ? "<a class='doc-link' href='".htmlspecialchars($u['bank_file'])."' target='_blank'>View Document</a>" : "No File Up"; ?>
                    </div>
                </div>

                <div style="margin-top: 20px;"></div>

                <div class="action-zone">
                    <form action="admin_dashboard.php" method="POST" class="password-form">
                        <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                        <input type="text" name="new_password" placeholder="Enter New Plaintext Password" required>
                        <button type="submit" name="change_user_password">Override Password</button>
                    </form>

                    <div style="display: flex; align-items: center;">
                        <?php if (isset($u['status']) && $u['status'] === 'blocked'): ?>
                            <a href="admin_dashboard.php?action=unblock&id=<?php echo $u['id']; ?>" class="btn-status btn-unblock">✓ Enable User</a>
                        <?php else: ?>
                            <a href="admin_dashboard.php?action=block&id=<?php echo $u['id']; ?>" class="btn-status btn-block">❌ Disable User</a>
                        <?php endif; ?>

                        <a href="admin_dashboard.php?action=delete&id=<?php echo $u['id']; ?>" 
                           class="btn-status btn-delete" 
                           onclick="return confirm('⚠️ WARNING: Are you absolutely sure you want to PERMANENTLY DELETE this user from the database? This action cannot be undone and they will have to register fresh.');">
                           🗑️ Delete Account
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
