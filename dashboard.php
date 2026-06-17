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

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (isset($user['status']) && $user['status'] === 'blocked')) {
        session_unset();
        session_destroy();
        header("Location: login.php?error=disabled");
        exit();
    }
} catch (PDOException $e) {
    $user = [];
}

// 🔥 PRIME PROPERTY INDIA (ppi) सीक्रेट स्मार्ट लिंक जनरेशन
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$secret_hex = dechex($user['id'] ?? 0);
$referral_link = $protocol . $domainName . "/register.php?ref=ppi-" . $secret_hex;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Dashboard // Prime Property India</title>
    <style>
        body { font-family: 'Segoe UI', Roboto, sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; padding: 20px; }
        .dashboard-container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .header-bar h1 { margin: 0; font-size: 22px; color: #1e293b; }
        .nav-links a { text-decoration: none; color: #4f46e5; font-weight: 600; margin-left: 20px; font-size: 14px; }
        .welcome-card { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 25px; }
        
        /* 🌐 REFFERAL CARD DESIGN */
        .referral-dashboard-box {
            background: linear-gradient(135deg, #1e1b4b, #2e104d);
            color: #e0e7ff;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            border: 1px solid #4338ca;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            position: relative;
        }
        .ref-header { display: flex; align-items: center; gap: 15px; margin-bottom: 18px; }
        .ref-icon { font-size: 30px; }
        .ref-header h3 { margin: 0; font-size: 19px; color: #ffffff; font-weight: 700; }
        .ref-header p { margin: 4px 0 0 0; font-size: 13px; color: #94a3b8; }
        
        .ref-input-container { display: flex; flex-direction: column; gap: 12px; position: relative; }
        .ref-input-container input {
            width: 100%;
            padding: 14px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #4338ca;
            color: #ffffff;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            font-family: monospace;
        }
        .ref-btn-group { display: flex; gap: 10px; width: 100%; }
        .btn-ref-action {
            flex: 1;
            padding: 13px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.3s ease;
        }
        .btn-copy { background: #4f46e5; color: white; }
        .btn-copy:hover { background: #4338ca; box-shadow: 0 0 15px rgba(79, 70, 229, 0.4); }
        .btn-share { background: #10b981; color: white; }
        .btn-share:hover { background: #059669; box-shadow: 0 0 15px rgba(16, 185, 129, 0.4); }
        
        .copy-toast {
            display: none;
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: #10b981;
            color: white;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        
        @media (min-width: 600px) {
            .ref-input-container { flex-direction: row; align-items: center; }
            .ref-input-container input { flex: 1; }
            .ref-btn-group { width: auto; }
            .btn-ref-action { padding: 13px 22px; flex: none; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- टॉप हेडर बार -->
    <div class="header-bar">
        <h1>Prime Property India</h1>
        <div class="nav-links">
            <a href="dashboard.php" style="color: #0f172a;">🏠 Dashboard</a>
            <a href="profile.php">👤 My Profile (KYC)</a>
            <a href="logout.php" style="color: #ef4444;">🔒 Logout</a>
        </div>
    </div>

    <!-- स्वागत कार्ड -->
    <div class="welcome-card">
        <h2 style="margin: 0 0 5px 0;">Welcome back, <?php echo htmlspecialchars($user['username'] ?? 'User'); ?>!</h2>
        <p style="margin: 0; color: #64748b; font-size: 14px;">Your corporate account portal is active and secure.</p>
    </div>

    <!-- 🌐 PRIME PROPERTY INDIA (PPI) REFFERAL SYSTEM BLOCK -->
    <div class="referral-dashboard-box">
        <div class="ref-header">
            <span class="ref-icon">📢</span>
            <div>
                <h3>Prime Property India (PPI) Network Matrix</h3>
                <p>अपना सिक्योर कॉर्पोरेट नेटवर्क लिंक शेयर करें और डायरेक्ट डाउनलाइन कनेक्ट करें।</p>
            </div>
        </div>
        
        <div class="ref-body">
            <div class="ref-input-container">
                <input type="text" id="dashboardRefLink" value="<?php echo $referral_link; ?>" readonly>
                
                <div class="ref-btn-group">
                    <!-- 📋 कॉपी बटन -->
                    <button type="button" class="btn-ref-action btn-copy" onclick="copyDashboardLink()">
                        📋 Copy Link
                    </button>
                    <!-- 🚀 शेयर बटन -->
                    <button type="button" class="btn-ref-action btn-share" onclick="shareDashboardLink()">
                        🌐 Share Link
                    </button>
                </div>
            </div>
            <span id="copyToast" class="copy-toast">🔗 Link Copied to Clipboard!</span>
        </div>
    </div>

    <!-- डैशबोर्ड के बाकी फीचर्स या स्टेट्स यहाँ नीचे आ सकते हैं -->
</div>

<script>
    // 📋 कॉपी लिंक करने का स्मूथ फंक्शन
    function copyDashboardLink() {
        const copyText = document.getElementById("dashboardRefLink");
        navigator.clipboard.writeText(copyText.value).then(() => {
            const toast = document.getElementById("copyToast");
            toast.style.display = "block";
            // 2 सेकंड बाद टोस्ट गायब हो जाएगा
            setTimeout(() => { toast.style.display = "none"; }, 2000);
        }).catch(err => {
            // पुराने ब्राउज़र्स के लिए फॉलबैक
            copyText.select();
            document.execCommand("copy");
            alert("🔗 Link Copied to Clipboard!");
        });
    }

    // 🚀 नेटिव मोबाइल शेयर खोलने का फंक्शन
    function shareDashboardLink() {
        const shareData = {
            title: 'Prime Property India Network',
            text: 'Connect to Prime Property India corporate network routing gateway:',
            url: document.getElementById('dashboardRefLink').value
        };
        if (navigator.share) {
            navigator.share(shareData).catch((error) => console.log('Error sharing:', error));
        } else {
            copyDashboardLink();
        }
    }
</script>
</body>
</html>
