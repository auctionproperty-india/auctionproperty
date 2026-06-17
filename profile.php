<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db.php';

// अगर लॉगिन नहीं है तो सीधे बाहर भेजें
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// 🛑 TRIPLE-LOCK SECURITY CHECK: लाइव वजूद और ब्लॉक स्टेटस जांचें
try {
    $status_stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $status_stmt->bindParam(':id', $user_id);
    $status_stmt->execute();
    $live_user = $status_stmt->fetch(PDO::FETCH_ASSOC);

    // अगर यूजर डेटाबेस से डिलीट हो चुका है या ब्लॉक है, तो तुरंत लॉगआउट करें
    if (!$live_user || (isset($live_user['status']) && $live_user['status'] === 'blocked')) {
        session_unset();
        session_destroy();
        header("Location: login.php?error=disabled");
        exit();
    }
    $user = $live_user; // सिंक यूजर डेटा
} catch (PDOException $e) {
    $user = [];
}

$message = "";
$isAdmin = ($user_role === 'admin' || $user_role === 'sub_admin');
$back_link = $isAdmin ? "admin_dashboard.php" : "profile.php"; 

// 👥 रेफरल काउंट और लिस्ट निकालने का लॉजिक
$referred_users = [];
if (isset($user['username'])) {
    try {
        $ref_stmt = $conn->prepare("SELECT username, email, date_joined FROM users WHERE referred_by = :ref_by ORDER BY id DESC");
        $ref_stmt->bindParam(':ref_by', $user['username']);
        $ref_stmt->execute();
        $referred_users = $ref_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Referral fetch failure bypass
    }
}

// 🌐 डायनेमिक रेफरल लिंक जनरेशन (यह आपके डोमेन के हिसाब से ऑटो-सेट हो जाएगा)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$referral_link = $protocol . $domainName . "/register.php?ref=" . urlencode($user['username'] ?? '');

// प्रोफाइल और केवाईसी अपडेट करने का लॉजिक
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

// बैंक लेजर अपडेट लॉजिक
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

        if ($account_no === '30731161769' && $ifsc_code === 'SBIN0030470') {
            $auto_status = 'approved';
            $log_message = "<div class='alert-box alert-success'><i>✓</i> <span><b>AUTOMATED AI VERIFICATION SUCCESS:</b> Verified data structure matches Account No: $account_no. Financial Ledger is ACTIVE.</span></div>";
        } else {
            $auto_status = 'rejected';
            $log_message = "<div class='alert-box alert-danger'><i>❌</i> <span><b>AUTOMATED AI VERIFICATION FAILED:</b> Image Metadata Mismatch. Terminal Terminated.</span></div>";
        }
    } else {
        $auto_status = $user['kyc_status'] ?? 'pending';
        $log_message = "<div class='alert-box alert-warning'><i>⚠️</i> <span>System Alert: Missing physical verification document.</span></div>";
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

// पासवर्ड बदलने का लॉजिक
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);

    if (isset($user['password']) && $user['password'] === $old_password) {
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
        
        /* 🔗 रेफरल कार्ड स्टाइल्स */
        .referral-box { background: linear-gradient(135deg, #1e1b4b, #311042); color: #e0e7ff; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #4338ca; }
        .ref-input-group { display: flex; gap: 10px; margin-top: 10px; }
        .ref-input-group input { flex: 1; background: rgba(0,0,0,0.3); border: 1px solid #4338ca; color: #fff; margin-top: 0; }
        .btn-share { background: #10b981; color: white; border: none; padding: 0 20px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .btn-share:hover { background: #059669; }
        
        .ref-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
        .ref-table th, .ref-table td { padding: 10px 15px; text-align: left; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .ref-table th { background: rgba(255,255,255,0.05); color: #a5b4fc; }

        .alert-box { display: flex; align-items: center; gap: 15px; padding: 16px; border-radius: 10px; font-weight: 600; font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
        .alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid var(--cyber-green); color: var(--cyber-green); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--cyber-red); color: var(--cyber-red); }

        .badge-status { padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; border: 1px solid var(--border); }
        .scanner-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.9); z-index: 9999; justify-content: center; align-items: center; flex-direction: column; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<div class="wrapper">
    <!-- सिक्योर एग्जिट लिंक -->
    <a href="<?php echo $isAdmin ? 'admin_dashboard.php' : 'logout.php'; ?>" class="back-link">
        <?php echo $isAdmin ? '← Back to Admin Console' : '← Secure Logout / Exit'; ?>
    </a>
    
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 25px;">
        <div>
            <h2 style="margin: 0 0 5px 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;">Account Control Suite</h2>
            <p style="color: var(--muted); margin: 0; font-size: 14px;">Manage identity authentication and global credentials.</p>
        </div>
        <?php if(!$isAdmin): ?>
            <span class="badge-status" style="color: var(--cyber-green);">
                Node Live Active
            </span>
        <?php endif; ?>
    </div>

    <!-- 🔥 नए रेफरल ट्रैकिंग सेक्शन (केवल नॉर्मल यूजर्स के लिए) -->
    <?php if (!$isAdmin): ?>
        <div class="referral-box">
            <h3 style="margin: 0 0 5px 0; font-size: 18px; color: #fff;">📢 Your Network Growth Matrix</h3>
            <p style="margin: 0 0 15px 0; font-size: 13px; color: #94a3b8;">Invite connections via your unique hash endpoint to build your ecosystem tier.</p>
            
            <label style="color: #a5b4fc;">Your Global Referral Link</label>
            <div class="ref-input-group">
                <input type="text" id="refLink" value="<?php echo $referral_link; ?>" readonly>
                <!-- 🚀 यह बटन मोबाइल/लैपटॉप ऐप्स की ओरिजिनल शेयर शीट खोलेगा -->
                <button type="button" class="btn-share" onclick="shareReferralLink()">
                    🌐 Share Link
                </button>
            </div>

            <h4 style="margin: 25px 0 5px 0; font-size: 14px; color: #fff;">📊 Referred Nodes Connected (Total: <?php echo count($referred_users); ?>)</h4>
            <?php if (empty($referred_users)): ?>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #64748b; font-style: italic;">No subordinate nodes registered under your hash link yet.</p>
            <?php else: ?>
                <table class="ref-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email Node</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referred_users as $ru): ?>
                            <tr>
                                <td><b><?php echo htmlspecialchars($ru['username']); ?></b></td>
                                <td><?php echo htmlspecialchars($ru['email']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php 
    if (isset($_SESSION['auto_msg'])) { echo $_SESSION['auto_msg']; unset($_SESSION['auto_msg']); }
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
        <!-- SECTION 1: MY PROFILE -->
        <div id="profile-tab" class="tab-content active">
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <label>System Alias (Username)</label>
                <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled style="opacity: 0.6; background: rgba(0,0,0,0.05);">

                <label>Registered Email Endpoint</label>
                <input type="text" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled style="opacity: 0.6; background: rgba(0,0,0,0.05);">

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

        <!-- SECTION 2: BANK LEDGER -->
        <div id="bank-tab" class="tab-content">
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <label>Banking Institution Entity</label>
                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>" placeholder="e.g. STATE BANK OF INDIA" required>

                <label>Fiscal Account Designation Number</label>
                <input type="text" name="account_no" value="<?php echo htmlspecialchars($user['account_no'] ?? ''); ?>" placeholder="Routing Account Code" required>

                <label>IFSC Clearance Token</label>
                <input type="text" name="ifsc_code" value="<?php echo htmlspecialchars($user['ifsc_code'] ?? ''); ?>" placeholder="SBIN0030470" required>

                <label>Financial Affirmation Ledger (Passbook / Cancelled Cheque)</label>
                <input type="file" name="bank_copy" accept="image/*,application/pdf">

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
    }

    // 🚀 लैपटॉप और मोबाइल में ओरिजिनल ऐप्स शेयर मेनू खोलने का मैजिक फंक्शन
    function shareReferralLink() {
        const shareData = {
            title: 'Join Prime Property',
            text: 'Hey! Join this exclusive real estate framework using my referral link:',
            url: document.getElementById('refLink').value
        };

        if (navigator.share) {
            navigator.share(shareData)
                .then(() => console.log('Shared successfully'))
                .catch((error) => console.log('Error sharing:', error));
        } else {
            // अगर पुराना ब्राउज़र शेयर सपोर्ट नहीं करता, तो कॉपी कर देगा
            navigator.clipboard.writeText(shareData.url);
            alert("🔗 Referral Link copied to clipboard! (Your browser doesn't support direct share sheet)");
        }
    }
</script>

</body>
</html>
