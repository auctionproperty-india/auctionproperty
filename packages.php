<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$property_id = $_GET['property_id'] ?? 0;

// अगर पहले से Active है तो Detail Page पर भेज दें
$check = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND property_id = ? AND status = 'active' AND end_date >= CURDATE()");
$check->execute([$user_id, $property_id]);
if($check->rowCount() > 0) {
    header("Location: property_detail.php?id=" . $property_id);
    exit;
}

// Fetch Packages
$packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();

$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buy_package'])) {
    $package_id = $_POST['package_id'];
    $payment_method = $_POST['payment_method'];
    
    // Package Details
    $pkg = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
    $pkg->execute([$package_id]);
    $pkg = $pkg->fetch();
    
    $screenshot_path = '';
    if($payment_method == 'bank' && isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] == 0) {
        $upload_dir = 'uploads/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
        $filename = 'payment_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        move_uploaded_file($_FILES['screenshot']['tmp_name'], $upload_dir . $filename);
        $screenshot_path = $upload_dir . $filename;
    }

    // Insert Subscription (Pending)
    $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, property_id, amount, payment_method, screenshot_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$user_id, $package_id, $property_id, $pkg['price'], $payment_method, $screenshot_path]);
    
    $message = "<div class='alert alert-success'>✅ Request Submitted! Admin will activate it soon.</div>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Buy Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-3">🔓 Unlock Full Property Details</h2>
    <?= $message ?>
    <div class="row">
        <?php foreach($packages as $pkg): ?>
            <div class="col-md-3 mb-3">
                <div class="card p-3 shadow-sm text-center h-100">
                    <h5><?= htmlspecialchars($pkg['name']) ?></h5>
                    <h4 class="text-success">₹ <?= indianCurrencyFormat($pkg['price']) ?></h4>
                    <form method="POST" enctype="multipart/form-data" class="mt-2">
                        <input type="hidden" name="package_id" value="<?= $pkg['id'] ?>">
                        <div class="mb-2">
                            <select name="payment_method" class="form-control form-control-sm" required>
                                <option value="online">💳 Pay Online (Coming Soon)</option>
                                <option value="bank">🏦 Bank Transfer (Upload Screenshot)</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <input type="file" name="screenshot" class="form-control form-control-sm" accept="image/*">
                            <small class="text-muted">Upload if Bank Transfer</small>
                        </div>
                        <button type="submit" name="buy_package" class="btn btn-primary w-100">Buy Now</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <a href="index.php" class="btn btn-secondary">⬅ Back to Properties</a>
</div>
</body>
</html>
