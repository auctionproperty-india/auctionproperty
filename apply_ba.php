<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🛡️ SECURITY
blockBadBots();
checkRateLimit(30, 60);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT ba_status, name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['ba_status'] == 'active' || $user['ba_status'] == 'pending') {
    header("Location: user_dashboard.php");
    exit;
}

// 🔥 SIRF BA PACKAGES FETCH KARENGE
$packages = $pdo->query("SELECT * FROM packages WHERE is_ba_package = TRUE ORDER BY COALESCE(display_order, 999) ASC, id ASC")->fetchAll();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$selected_package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

// 🔥 FETCH DYNAMIC BANK DETAILS FOR PAYMENT PAGE (Settings Table)
$bank = [
    'name' => 'JANA SMAL FINANCE BANK', // Fallback Default
    'account' => '4553020001485256',
    'ifsc' => 'JSFB0004553',
    'branch' => 'Bhavarkua',
    'contact' => 'Not set',
    'qr_code' => '' 
];

try {
    $stmt_bank = $pdo->query("SELECT * FROM settings WHERE setting_key IN ('bank_name', 'account_number', 'ifsc_code', 'branch', 'contact', 'qr_code')");
    while ($row = $stmt_bank->fetch()) {
        if ($row['setting_key'] == 'bank_name') $bank['name'] = $row['setting_value'];
        if ($row['setting_key'] == 'account_number') $bank['account'] = $row['setting_value'];
        if ($row['setting_key'] == 'ifsc_code') $bank['ifsc'] = $row['setting_value'];
        if ($row['setting_key'] == 'branch') $bank['branch'] = $row['setting_value'];
        if ($row['setting_key'] == 'contact') $bank['contact'] = $row['setting_value'];
        if ($row['setting_key'] == 'qr_code') $bank['qr_code'] = $row['setting_value'];
    }
} catch (Exception $e) {
    // Settings table might not exist, using fallback
}

// Handle Payment Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    $package_id = (int)$_POST['package_id'];
    $amount_paid = (float)$_POST['amount'];
    
    $pkg_stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
    $pkg_stmt->execute([$package_id]);
    $selected_pkg = $pkg_stmt->fetch();

    $image_url = '';
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] == 0) {
        $upload_dir = 'uploads/slips/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
        $filename = 'ba_slip_' . $user_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $upload_dir . $filename)) {
            $image_url = $upload_dir . $filename;
        }
    }

    $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, amount, payment_screenshot, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())")->execute([$user_id, $package_id, $amount_paid, $image_url]);
    $pdo->prepare("UPDATE users SET ba_status = 'pending' WHERE id = ?")->execute([$user_id]);

    header("Location: user_dashboard.php?msg=ba_submitted");
    exit;
}

include 'header.php';
?>

<style>
    /* Premium Card Styles */
    .premium-card { border-radius: 22px; overflow: hidden; box-shadow: 0 6px 25px rgba(0,0,0,0.06); transition: all 0.3s; height: 100%; display: flex; flex-direction: column; background: #ffffff; border: 2px solid #e2e8f0; cursor: pointer; }
    .premium-card:hover { transform: translateY(-5px); box-shadow: 0 20px 50px rgba(0,0,0,0.12); border-color: #93c5fd; }
    .premium-card.selected { border: 3px solid #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.2); transform: translateY(-5px); }
    
    .premium-header { padding: 25px 18px; text-align: center; color: #fff; background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); }
    .premium-body { padding: 16px 14px; flex: 1; }
    .price-new { font-size: 1.9rem; font-weight: 900; color: #ffffff; }
    
    /* Payment Page Styles (Matching buy_subscription.php) */
    .bank-card { border-radius: 16px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    .bg-soft-info { background-color: #e0f2fe; color: #0284c7; }
</style>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2"><i class="fas fa-handshake text-primary"></i> Business Associate (BA) Program</h2>
                <p class="text-muted">Select a training package to get your Referral Link and Team Access.</p>
            </div>

            <?php if ($step == 1): ?>
                <form action="apply_ba.php" method="GET">
                    <input type="hidden" name="step" value="2">
                    <input type="hidden" name="package_id" id="selected_package_id" required>

                    <div class="row g-3 mb-4 justify-content-center">
                        <?php if(count($packages) > 0): ?>
                            <?php foreach ($packages as $pkg): 
                                $pkg_name = strtolower($pkg['name']);
                                $icon = 'fa-medal';
                                if (strpos($pkg_name, 'silver') !== false) $icon = 'fa-cog';
                                elseif (strpos($pkg_name, 'gold') !== false) $icon = 'fa-crown';
                                elseif (strpos($pkg_name, 'platinum') !== false) $icon = 'fa-gem';
                                elseif (strpos($pkg_name, 'diamond') !== false) $icon = 'fa-diamond';
                            ?>
                            <div class="col-md-6 col-lg-4 col-xl-3">
                                <!-- 🔥 Onclick JS to select package -->
                                <div class="premium-card text-center" onclick="selectPackage(<?= $pkg['id'] ?>, this)">
                                    <div class="premium-header">
                                        <i class="fas <?= $icon ?> fa-3x mb-3 text-warning"></i>
                                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($pkg['name']) ?></h4>
                                        <div class="price-new mt-2">₹ <?= number_format($pkg['price'], 0) ?></div>
                                    </div>
                                    <div class="premium-body text-start">
                                        <div class="d-flex gap-2 mb-2 align-items-center"><i class="fas fa-check-circle text-success"></i> <span class="small fw-bold">Corporate Training</span></div>
                                        <div class="d-flex gap-2 mb-2 align-items-center"><i class="fas fa-check-circle text-success"></i> <span class="small fw-bold">Referral Link</span></div>
                                        <div class="d-flex gap-2 mb-2 align-items-center"><i class="fas fa-check-circle text-success"></i> <span class="small fw-bold">Team Access</span></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center text-muted">No BA packages available right now.</div>
                        <?php endif; ?>
                    </div>

                    <?php if(count($packages) > 0): ?>
                    <div class="bg-light p-3 rounded-3 mb-4 mx-auto" style="max-width: 800px; border: 1px solid #e2e8f0;">
                        <label class="d-flex align-items-start gap-3 cursor-pointer">
                            <input type="checkbox" class="form-check-input custom-checkbox mt-1" style="width:20px; height:20px;" required>
                            <span class="small text-muted">
                                I agree to the <strong>Terms & Conditions</strong>. I understand that the package amount is non-refundable and my BA account will be activated only after admin verifies the payment slip.
                            </span>
                        </label>
                    </div>

                    <div class="text-center">
                        <button type="submit" class="btn btn-warning btn-lg fw-bold rounded-pill px-5 py-3 shadow" onclick="return checkPackageSelected()">
                            Proceed to Payment <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </form>

                <script>
                    function selectPackage(id, card) {
                        // Remove selected class from all
                        document.querySelectorAll('.premium-card').forEach(el => el.classList.remove('selected'));
                        // Add selected class to clicked card
                        card.classList.add('selected');
                        // Set hidden input value
                        document.getElementById('selected_package_id').value = id;
                    }

                    function checkPackageSelected() {
                        const selectedId = document.getElementById('selected_package_id').value;
                        if (!selectedId) { 
                            alert("Please select a package to continue."); 
                            return false; 
                        }
                        return true;
                    }
                </script>

            <?php elseif ($step == 2 && $selected_package_id > 0): 
                $pkg_stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
                $pkg_stmt->execute([$selected_package_id]);
                $selected_pkg = $pkg_stmt->fetch();
                $pay_amount = ($selected_pkg['discount_price'] > 0) ? $selected_pkg['discount_price'] : $selected_pkg['price'];
            ?>
                
                <!-- 🔥 STEP 2: PAYMENT PAGE (Exact look like buy_subscription.php) -->
                <h3 class="mb-2"><i class="fas fa-shopping-cart"></i> Buy Package: <?= htmlspecialchars($selected_pkg['name']) ?></h3>
                <p class="text-muted mb-4">Fill the details below to request subscription.</p>

                <?php if($selected_pkg['discount_price'] > 0): ?>
                <div class="alert alert-info d-flex align-items-center gap-2" style="border-radius: 12px;">
                    <i class="fas fa-tag fs-5"></i> 
                    <span>You get a discount! Regular price: <del>₹<?= number_format($selected_pkg['price'], 2) ?></del> → <strong>Pay only ₹<?= number_format($pay_amount, 2) ?></strong></span>
                </div>
                <?php endif; ?>

                <div class="card bank-card mb-4">
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <h5 class="fw-bold mb-3"><i class="fas fa-university"></i> Bank Details</h5>
                                <div class="bg-light p-3 rounded" style="border: 1px solid #e2e8f0;">
                                    <p class="mb-1"><strong>Bank Name:</strong> <?= htmlspecialchars($bank['name']) ?></p>
                                    <p class="mb-1"><strong>Account Number:</strong> <?= htmlspecialchars($bank['account']) ?></p>
                                    <p class="mb-1"><strong>IFSC Code:</strong> <?= htmlspecialchars($bank['ifsc']) ?></p>
                                    <p class="mb-1"><strong>Branch:</strong> <?= htmlspecialchars($bank['branch']) ?></p>
                                    <p class="mb-0"><strong>Contact:</strong> <?= htmlspecialchars($bank['contact']) ?></p>
                                </div>
                            </div>
                            <div class="col-md-6 text-center">
                                <h5 class="fw-bold mb-3"><i class="fas fa-qrcode"></i> QR Code</h5>
                                <div class="bg-light p-3 rounded h-100 d-flex align-items-center justify-content-center" style="border: 1px solid #e2e8f0;">
                                    <?php if(!empty($bank['qr_code'])): ?>
                                        <img src="<?= htmlspecialchars($bank['qr_code']) ?>" alt="QR Code" style="max-width: 150px; border-radius: 8px;">
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No QR code uploaded yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="package_id" value="<?= $selected_package_id ?>">
                    
                    <div class="card bank-card mb-4">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label class="fw-bold mb-1">Amount (₹)</label>
                                <input type="number" name="amount" class="form-control form-control-lg" value="<?= $pay_amount ?>" required>
                                <small class="text-muted">You can edit the amount if you have any special offer.</small>
                            </div>

                            <div class="mb-4">
                                <label class="fw-bold mb-1">Upload Payment Screenshot / Slip <span class="text-danger">*</span></label>
                                <input type="file" name="payment_slip" class="form-control form-control-lg" accept="image/*, .pdf" required>
                                <small class="text-muted">Upload a clear screenshot of your transaction.</small>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="apply_ba.php?step=1" class="btn btn-outline-secondary btn-lg rounded-pill w-50">Back</a>
                                <button type="submit" name="submit_payment" class="btn btn-success btn-lg rounded-pill w-50 fw-bold">
                                    Submit Request <i class="fas fa-check ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
