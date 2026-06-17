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
$message = "";

// 1. डेटाबेस से वर्तमान यूज़र की पूरी जानकारी निकालना
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<p style='color:red;'>डेटा लोड एरर: " . $e->getMessage() . "</p>";
}

// 2. प्रोफाइल और KYC सबमिट होने पर प्रोसेस करना
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $bank_name = trim($_POST['bank_name']);
    $account_no = trim($_POST['account_no']);
    $ifsc_code = trim($_POST['ifsc_code']);

    // फाइल अपलोड करने का इंतजाम (रेंडर के लोकल फोल्डर 'uploads' में सेवे होगा)
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $adhaar_file = $user['adhaar_file'];
    $pan_file = $user['pan_file'];
    $bank_file = $user['bank_file'];

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
            $message = "<p style='color:green; font-weight:bold;'>प्रोफाइल और KYC डिटेल्स सफलतापूर्वक अपडेट हो गईं! ⏳ वेरिफिकेशन पेंडिंग है।</p>";
            // डेटा रिफ्रेश करें
            header("Refresh:2");
        }
    } catch (PDOException $e) {
        $message = "<p style='color:red;'>अपडेट एरर: " . $e->getMessage() . "</p>";
    }
}

// 3. पासवर्ड बदलने का लॉजिक
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);

    if ($user['password'] === $old_password) {
        try {
            $pass_stmt = $conn->prepare("UPDATE users SET password = :pass WHERE id = :id");
            $pass_stmt->bindParam(':pass', $new_password);
            $pass_stmt->bindParam(':id', $user_id);
            if ($pass_stmt->execute()) {
                $message = "<p style='color:green; font-weight:bold;'>पासवर्ड सफलतापूर्वक बदल गया है! 🔑</p>";
            }
        } catch (PDOException $e) {
            $message = "<p style='color:red;'>पासवर्ड एरर: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p style='color:red;'>पुराना पासवर्ड गलत है!</p>";
    }
}

// वापस जाने का लिंक तय करना (एडमिन या यूज़र के हिसाब से)
$back_link = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'sub_admin') ? "admin_dashboard.php" : "dashboard.php";
?>

<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>My Profile & KYC</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f1f3f5; margin: 0; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h2, h3 { color: #333; border-bottom: 2px solid #dee2e6; padding-bottom: 8px; }
        label { font-weight: bold; display: block; margin-top: 12px; color: #555; }
        input, text-area, textarea { width: 96%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .btn-submit { background: #28a745; color: white; border: none; padding: 12px 20px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 15px; width: 100%; }
        .btn-pass { background: #dc3545; color: white; border: none; padding: 12px 20px; font-size: 16px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 15px; width: 100%; }
        .badge { display: inline-block; padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 12px; color: white; }
        .pending { background: #ffc107; color: #000; }
        .approved { background: #28a745; }
        .back-btn { text-decoration: none; color: #007bff; font-weight: bold; display: inline-block; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="container">
    <a href="<?php echo $back_link; ?>" class="back-btn">← वापस डैशबोर्ड पर जाएं</a>
    
    <h2>👤 प्रोफाइल और KYC मैनेजमेंट</h2>
    <p>आपका रोल: <b><?php echo strtoupper($user['role']); ?></b> | KYC स्टेटस: 
        <span class="badge <?php echo $user['kyc_status']; ?>"><?php echo strtoupper($user['kyc_status']); ?></span>
    </p>

    <?php echo $message; ?>

    <form action="profile.php" method="POST" enctype="multipart/form-data">
        <h3>🏠 पर्सनल और बैंक डिटेल्स</h3>
        
        <label>यूज़रनेम:</label>
        <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="background:#eee;">

        <label>ईमेल एड्रेस:</label>
        <input type="text" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:#eee;">

        <label>मोबाइल नंबर:</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Enter Mobile Number" required>

        <label>पूरा पता (Address):</label>
        <textarea name="address" rows="3" style="width: 96%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px;" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>

        <label>बैंक का नाम (Bank Name):</label>
        <input type="text" name="bank_name" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>" placeholder="e.g. SBI, HDFC" required>

        <label>अकाउंट नंबर (Account Number):</label>
        <input type="text" name="account_no" value="<?php echo htmlspecialchars($user['account_no'] ?? ''); ?>" placeholder="Enter Account Number" required>

        <label>IFSC कोड:</label>
        <input type="text" name="ifsc_code" value="<?php echo htmlspecialchars($user['ifsc_code'] ?? ''); ?>" placeholder="Enter IFSC Code" required>

        <h3>📁 डॉक्यूमेंट अपलोड (KYC Documents)</h3>
        
        <label>आधार कार्ड (Aadhaar Card Photo):</label>
        <input type="file" name="adhaar" accept="image/*,application/pdf">
        <?php if(!empty($user['adhaar_file'])): ?><p style="font-size:12px; color:green;">✓ आधार अपलोड है</p><?php endif; ?>

        <label>पैन कार्ड (PAN Card Photo):</label>
        <input type="file" name="pan" accept="image/*,application/pdf">
        <?php if(!empty($user['pan_file'])): ?><p style="font-size:12px; color:green;">✓ पैन कार्ड अपलोड है</p><?php endif; ?>

        <label>बैंक पासबुक / कैंसिल्ड चेक (Bank Passbook Copy):</label>
        <input type="file" name="bank_copy" accept="image/*,application/pdf">
        <?php if(!empty($user['bank_file'])): ?><p style="font-size:12px; color:green;">✓ बैंक प्रूफ अपलोड है</p><?php endif; ?>

        <button type="submit" name="update_profile" class="btn-submit">Save KYC & Profile Details</button>
    </form>

    <br><br>

    <form action="profile.php" method="POST">
        <h3>🔑 पासवर्ड बदलें (Change Password)</h3>
        
        <label>पुराना पासवर्ड (Current Password):</label>
        <input type="password" name="old_password" placeholder="Enter Current Password" required>

        <label>नया पासवर्ड (New Password):</label>
        <input type="password" name="new_password" placeholder="Enter New Password" required>

        <button type="submit" name="change_password" class="btn-pass">Update Password</button>
    </form>
</div>

</body>
</html>
