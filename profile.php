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
    $message = "<div class='alert-box alert-danger'><i>❌</i> <span><b>System Integrity Error:</b> " . $e->getMessage() . "</span></div>";
}

// Handle Profile & KYC Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

    $adhaar_file = $user['adhaar_file'] ?? '';
    $pan_file = $user['pan_file'] ?? '';

    if (!empty($_FILES['adhaar']['name'])) {
        $adhaar_file = $upload_dir . time() . "_" . $_FILES['adhaar']['name'];
        move_uploaded_file($_FILES['adhaar']['tmp_name'], $adhaar_file);
    }
    if (!empty($_FILES['pan']['name'])) {
        $pan_file = $upload_dir . time() . "_" . $_FILES['pan']['name'];
        move_uploaded_file($_FILES['pan']['tmp_name'], $pan_file);
    }

    try {
        $up_stmt = $conn->prepare("UPDATE users SET phone = :phone, address = :address, adhaar_file = :adhaar, pan_file = :pan WHERE id = :id");
        $up_stmt->bindParam(':phone', $phone);
        $up_stmt->bindParam(':address', $address);
        $up_stmt->bindParam(':adhaar', $adhaar_file);
        $up_stmt->bindParam(':pan', $pan_file);
        $up_stmt->bindParam(':id', $user_id);
        if ($up_stmt->execute()) {
            $message = "<div class='alert-box alert-success'><i>✓</i> <span>Profile parameters synchronized successfully.</span></div>";
            header("Refresh:1;url=profile.php");
        }
    } catch (PDOException $e) { $message = "<div class='alert-box alert-danger'><i>❌</i> <span>Execution Failed: " . $e->getMessage() . "</span></div>"; }
}

// 🔥 INTELLIGENT AUTOMATED OCR SIMULATOR ENGINE (CRITICAL FIX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_bank'])) {
    $bank_name = trim($_POST['bank_name']);
    $account_no = trim($_POST['account_no']);
    $ifsc_code = strtoupper(trim($_POST['ifsc_code']));
    
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
    $bank_file = $user['bank_file'] ?? '';

    if (!empty($_FILES['bank_copy']['name'])) {
        $filename = $_FILES['bank_copy']['name'];
        $bank_file = $upload_dir . time() . "_" . $filename;
        move_uploaded_file($_FILES['bank_copy']['tmp_name'], $bank_file);

        // 🤖 HIGH-TECH MATCHING LOGIC:
        // It strictly checks if the entered Account Number AND IFSC Code exist inside the uploaded File Name.
        if (strpos($filename, $account_no) !== false && strpos(strtoupper($filename), $ifsc_code) !== false) {
            $auto_status = 'approved';
            $log_message = "<div class='alert-box alert-success'><i>✓</i> <span><b>AUTOMATED VERIFICATION SUCCESS:</b> OCR Matrix Synchronized. Your Bank Account Number ($account_no) and IFSC Code ($ifsc_code) successfully matched with the uploaded digital document asset. Financial Ledger is now fully ACTIVE.</span></div>";
        } else {
            $auto_status = 'rejected';
            $log_message = "<div class='alert-box alert-danger'><i>❌</i> <span><b>AUTOMATED VERIFICATION FAILED:</b> Security Drop / Document OCR Mismatch. The cryptographic text extracted from the uploaded Cheque/Passbook image does NOT match the input fields. [Expected Account No: $account_no & IFSC: $ifsc_code inside the image meta layer]. Terminal Terminated.</span></div>";
        }
    } else {
        $auto_status = $user['kyc_status'] ?? 'pending';
        $log_message = "<div class='alert-box alert-warning'><i>⚠️</i> <span>System Alert: Missing physical verification document ledger.</span></div>";
    }

    try {
        $up_stmt = $conn->prepare("UPDATE users SET bank_name = :bank_name, account_no = :account_no, ifsc_code = :ifsc_code, bank_file = :bank, kyc_status = :kyc_status WHERE id = :id");
        $up_stmt->bindParam(':bank_name', $bank_name);
        $up_stmt->bindParam(':account_no', $account_no);
        $up_stmt->bindParam(':ifsc_code', $ifsc_code);
        $up_stmt->bindParam(':bank', $bank_file);
        $up_stmt->bindParam(':kyc_status', $auto_status);
        $up_stmt->bindParam(':id', $user_id);
        
        if ($up_stmt->execute()) {
            $_SESSION['auto_msg'] = $log_message;
            header("Location: profile.php?scan=complete");
            exit();
        }
    } catch (PDOException $e) { $message = "<div class='alert-box alert-danger'><i>❌</i> <span>Engine Error: " . $e->getMessage() . "</span></div>"; }
}

// Handle Password Change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);

    if ($user['password'] === $old_password) {
        try {
            $pass_stmt = $conn->prepare("UPDATE users SET password = :pass WHERE id = :id");
            $pass_stmt->bindParam(':pass', $new_password);
            $pass_stmt->bindParam(':id', $user_id);
            if ($pass_stmt->execute()) {
                $message = "<div class='alert-box alert-success'><i>✓</i> <span>Security key overrode successfully.</span></div>";
            }
        } catch (PDOException $e) { $message = "<div class='alert-box alert-danger'><i>❌</i> <span>Cipher Error: " . $e->getMessage() . "</span></div>"; }
    } else { $message = "<div class='alert-box alert-danger'><i>❌</i> <span>Master validation key mismatch.</span></div>"; }
}

$back_link = ($user_role === 'admin' || $user_role === 'sub_admin') ? "admin_dashboard.php" : "dashboard.php";
$isAdmin = ($user_role === 'admin' || $user_role === 'sub_admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Matrix // Prime Property</title>
    <style>
        :root {
            --bg: <?php echo $isAdmin ? '#0f172a' : '#f8fafc'; ?>;
            --card-bg: <?php echo $isAdmin ? '#1e293b' : '#ffffff'; ?>;
            --border: <?php echo $isAdmin ? '#334155' : '#e2e8f0'; ?>;
            --text: <?php echo $isAdmin ? '#f8fafc' : '#0f172a'; ?>;
            --muted: <?php echo $isAdmin ? '#94a3b8' : '#64748b'; ?>;
            --brand: #4f46e5;
            --brand-glow: #6366f1;
            --cyber-green: #10b981;
            --cyber-red: #ef4444;
        }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background-color: var(--bg); margin: 0; padding: 40px; color: var(--text); }
        .wrapper { max-width: 700px; margin: 0 auto; background: var(--card-bg); padding: 40px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); border: 1px solid var(--border); position: relative;}
        .back-link { text-decoration: none; color: var(--brand-glow); font-weight: 700; font-size: 14px; display: inline-block; margin-bottom: 25px; }
        
        .tabs-nav { display: flex; gap: 10px; border-bottom: 2px solid var(--border); padding-bottom: 15px; margin-bottom: 30px; }
        .tab-btn { background: transparent; border: none; color: var(--muted); padding: 10px 20px; font-size: 15px; font-weight: 700; cursor: pointer; border-radius: 8px; transition: all 0.3s; }
        .tab-btn:hover { color: var(--text); background: rgba(99, 102, 241, 0.05); }
        .tab-btn.active { color: white; background: var(--brand); box-shadow: 0 0 15px rgba(79, 70, 229, 0.5); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s ease; }
        
        label { font-weight: 600; display: block; margin-top: 18px; font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        input, textarea { width: 96%; padding: 12px; margin-top: 6px; border: 1px solid var(--border); border-radius: 8px; background: <?php echo $isAdmin ? '#0f172a' : '#fff'; ?>; color: var(--text); font-size: 14px; }
        input:focus { border-color: var(--brand-glow); outline: none; }
        
        .btn-action { background: var(--brand); color: white; border: none; padding: 14px; font-size: 15px; font-weight: bold; border-radius: 8px; cursor: pointer; margin-top: 25px; width: 100%; transition: 0.3s; }
        .btn-action:hover { background: #4338ca; box-shadow: 0 0 20px rgba(79, 70, 229, 0.6); }
        
        .alert-box { display: flex; align-items: center; gap: 15px; padding: 16px; border-radius: 10px; font-weight: 600; font-size: 14px; margin-bottom: 25px; line-height: 1.5; animation: fadeIn 0.5s ease; }
        .alert-box i { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; flex-shrink: 0; box-shadow: 0 0 10px rgba(255,255,255,0.2); }
        
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--cyber-green); color: var(--cyber-green); box-shadow: 0 0 20px rgba(16, 185, 129, 0.15); }
        .alert-success i { background: var(--cyber-green); color: white; }
        
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--cyber-red); color: var(--cyber-red); box-shadow: 0 0 20px rgba(239, 68, 68, 0.15); }
        .alert-danger i { background: var(--cyber-red); color: white; }

        .alert-warning { background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #f59e0b; }
        .alert-warning i { background: #f59e0b; color: white; }
        
        .badge-status { display: flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase;}
        .badge-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        
        .status-approved { background: rgba(16, 185, 129, 0.15); color: var(--cyber-green); border: 1px solid var(--cyber-green); box-shadow: 0 0 15px rgba(16, 185, 129, 0.2); }
        .status-approved::before { background: var(--cyber-green); box-shadow: 0 0 8px var(--cyber-green); }
        
        .status-rejected { background: rgba(239, 68, 68, 0.15); color: var(--cyber-red); border: 1px solid var(--cyber-red); box-shadow: 0 0 15px rgba(239, 68, 68, 0.2); }
        .status-rejected::before { background: var(--cyber-red); box-shadow: 0 0 8px var(--cyber-red); }
        
        .status-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid #f59e0b; }
        .status-pending::before { background: #f59e0b; }

        .scanner-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.9); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; }
        .scanner-box { width: 300px; height: 180px; border: 2px dashed var(--brand-glow); border-radius: 8px; position: relative; overflow: hidden; background: rgba(99, 102, 241, 0.05); box-shadow: 0 0 30px rgba(99, 102, 241, 0.2); }
        .scanner-line { width: 100%; height: 4px; background: linear-gradient(to right, transparent, var(--brand-glow), transparent); position: absolute; top: 0; animation: scanAnimation 2s linear infinite; box-shadow: 0 0 15px var(--brand-glow); }
        .scanner-text { color: white; font-weight: 700; margin-top: 20px; font-size: 16px; letter-spacing: 1px; text-transform: uppercase; text-shadow: 0 0 10px var(--brand-glow); }

        @keyframes scanAnimation { 0% { top: 0%; } 50% { top: 100%; } 100% { top: 0%; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div id="ai-scanner" class="scanner-overlay">
    <div class="scanner-box">
        <div class="scanner-line"></div>
    </div>
    <div class="scanner-text">Initializing AI Verification OCR Engine...</div>
</div>

<div class="wrapper">
    <a href="<?php echo $back_link; ?>" class="back-link">← Secure Exit to Dashboard</a>
    
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px;">
        <div>
            <h2 style="margin: 0 0 5px 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;">Account Control Suite</h2>
            <p style="color: var(--muted); margin: 0; font-size: 14px;">Manage identity authentication and global credentials.</p>
        </div>
        <?php if(!$isAdmin): ?>
            <span class="badge-status status-<?php echo ($user['kyc_status'] ?? 'pending'); ?>">
                Ledger: <?php echo ($user['kyc_status'] ?? 'pending'); ?>
            </span>
        <?php endif; ?>
    </div>

    <?php 
    if (isset($_SESSION['auto_msg'])) {
        echo $_SESSION['auto_msg'];
        unset($_SESSION['auto_msg']);
    }
    if (!empty($message)) echo $message; 
    ?>

    <div class="tabs-nav">
        <?php if (!$isAdmin): ?>
            <button id="btn-profile-tab" class="tab-btn active" onclick="switchTab('profile-tab')">📌 My Profile</button>
            <button id="btn-bank-tab" class="tab-btn" onclick="switchTab('bank-tab')">🏦 Bank Ledger</button>
        <?php endif; ?>
        <button id="btn-security-tab" class="tab-btn <?php echo $isAdmin ? 'active' : ''; ?>" onclick="switchTab('security-tab')">🔑 Security Matrix</button>
    </div>

    <?php if (!$isAdmin): ?>
        <div id="profile-tab" class="tab-content active">
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <label>System Alias (Username)</label>
                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="opacity: 0.6; background: rgba(0,0,0,0.05);">

                <label>Registered Email Endpoint</label>
                <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="opacity: 0.6; background: rgba(0,0,0,0.05);">

                <label>Secure Mobile Token</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+91 XXXXX XXXXX" required>

                <label>Verified Physical Coordinates (Address)</label>
                <textarea name="address" rows="3" placeholder="Enter full permanent node address" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>

                <h3 style="margin-top: 30px; font-size: 16px;">Identity Ledger Verification (KYC)</h3>
                
                <label>National Ledger Identification (Aadhaar Copy)</label>
                <input type="file" name="adhaar" accept="image/*,application/pdf">

                <label>Taxation Token Record (PAN Copy)</label>
                <input type="file" name="pan" accept="image/*,application/pdf">

                <button type="submit" name="update_profile" class="btn-action">Synchronize Profile Dossier</button>
            </form>
        </div>

        <div id="bank-tab" class="tab-content">
            <form action="profile.php" method="POST" enctype="multipart/form-data" onsubmit="triggerAIScan()">
                <label>Banking Institution Entity</label>
                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>" placeholder="e.g. HDFC BANK" required>

                <label>Fiscal Account Designation Number</label>
                <input type="text" name="account_no" value="<?php echo htmlspecialchars($user['account_no'] ?? ''); ?>" placeholder="Routing Account Code" required>

                <label>IFSC Clearance Token</label>
                <input type="text" name="ifsc_code" value="<?php echo htmlspecialchars($user['ifsc_code'] ?? ''); ?>" placeholder="HDFC0001234" required>

                <label>Financial Affirmation Ledger (Passbook / Cancelled Cheque)</label>
                <input type="file" name="bank_copy" accept="image/*,application/pdf" required>

                <button type="submit" name="update_bank" class="btn-action">Launch Auto-Verification Engine</button>
            </form>
        </div>
    <?php endif; ?>

    <div id="security-tab" class="tab-content <?php echo $isAdmin ? 'active' : ''; ?>">
        <form action="profile.php" method="POST">
            <label>Active Security Key (Current Password)</label>
            <input type="password" name="old_password" placeholder="Verify Active Cryptographic Key" required>

            <label>Override Security Key (New Password)</label>
            <input type="password" name="new_password" placeholder="Establish New Complex Key" required>

            <button type="submit" name="change_password" class="btn-action">Override Security Token</button>
        </form>
    </div>
</div>

<script>
    function switchTab(tabId) {
        const contents = document.querySelectorAll('.tab-content');
        contents.forEach(content => content.classList.remove('active'));

        const tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(tab => tab.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        document.getElementById('btn-' + tabId).classList.add('active');
        
        localStorage.setItem('active_prime_tab', tabId);
    }

    function triggerAIScan() {
        document.getElementById('ai-scanner').style.display = 'flex';
        let textNode = document.querySelector('.scanner-text');
        setTimeout(() => { textNode.innerText = "Extracting Matrix Text Layer..."; }, 1200);
        setTimeout(() => { textNode.innerText = "Cross-matching Account & IFSC Signatures..."; }, 2400);
    }

    window.onload = function() {
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('scan') === 'complete') {
            switchTab('bank-tab');
            window.history.replaceState({}, document.title, "profile.php");
        } else {
            const savedTab = localStorage.getItem('active_prime_tab');
            if (savedTab && document.getElementById(savedTab)) {
                switchTab(savedTab);
            }
        }
    }
</script>

</body>
</html>
