<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

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

// Handle Payment Form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    $package_id = (int)$_POST['package_id'];
    
    $pkg_stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
    $pkg_stmt->execute([$package_id]);
    $selected_pkg = $pkg_stmt->fetch();
    $amount = $selected_pkg ? $selected_pkg['price'] : 0;

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

    $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, amount, payment_screenshot, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())")->execute([$user_id, $package_id, $amount, $image_url]);
    $pdo->prepare("UPDATE users SET ba_status = 'pending' WHERE id = ?")->execute([$user_id]);

    header("Location: user_dashboard.php?msg=ba_submitted");
    exit;
}

include 'header.php';
?>

<style>
    /* BA Premium Card Styles (Matching User Packages) */
    .premium-card { border-radius: 22px; overflow: hidden; box-shadow: 0 6px 25px rgba(0,0,0,0.06); transition: all 0.4s; height: 100%; display: flex; flex-direction: column; background: #ffffff; border: 1.5px solid #e2e8f0; }
    .premium-card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,0.12); border-color: #93c5fd; }
    .premium-header { padding: 22px 18px 18px; text-align: center; color: #fff; background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); }
    .premium-body { padding: 16px 14px 12px; flex: 1; }
    .price-new { font-size: 1.9rem; font-weight: 900; color: #ffffff; }
    .bank-box { background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 12px; padding: 20px; text-align: center; }
    .package-input { display: none; }
    .premium-card.selected { border: 3px solid #2563eb; box-shadow: 0 0 0 4px rgba(37,99,235,0.2); transform: translateY(-5px); }
</style>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-11">
            
            <div class="text-center mb-5">
                <h2 class="fw-bold mb-2"><i class="fas fa-handshake text-primary"></i> Business Associate (BA) Program</h2>
                <p class="text-muted">Select a training package to get your Referral Link and Team Access.</p>
            </div>

            <?php if ($step == 1): ?>
                <form action="apply_ba.php?step=2" method="GET">
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
                                <label class="w-100 m-0 h-100 cursor-pointer">
                                    <input type="radio" name="package_id" value="<?= $pkg['id'] ?>" class="package-input" required onchange="document.querySelectorAll('.premium-card').forEach(el => el.classList.remove('selected')); this.closest('.premium-card').classList.add('selected');">
                                    <div class="premium-card text-center">
                                        <div class="premium-header">
                                            <i class="fas <?= $icon ?> fa-3x mb-3 text-warning"></i>
                                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($pkg['name']) ?></h4>
                                            <div class="price-new mt-2">₹ <?= number_format($pkg['price'], 0) ?></div>
                                        </div>
                                        <div class="premium-body text-start">
                                            <div class="d-flex gap-2 mb-2 align-items-center">
                                                <i class="fas fa-check-circle text-success"></i> <span class="small fw-bold">Corporate Training</span>
                                            </div>
                                            <div class="d-flex gap-2 mb-2 align-items-center">
                                                <i class="fas fa-check-circle text-success"></i> <span class="small fw-bold">Referral Link</span>
                                            </div>
                                            <div class="d-flex gap-2 mb-2 align-items-center">
                                                <i class="fas fa-check-circle text-success"></i> <span class="small fw-bold">Team Access</span>
                                            </div>
                                        </div>
                                    </div>
                                </label>
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
                    function checkPackageSelected() {
                        const selected = document.querySelector('input[name="package_id"]:checked');
                        if (!selected) { alert("Please select a package to continue."); return false; }
                        return true;
                    }
                </script>

            <?php elseif ($step == 2 && $selected_package_id > 0): 
                $pkg_stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
                $pkg_stmt->execute([$selected_package_id]);
                $selected_pkg = $pkg_stmt->fetch();
            ?>
                <div class="text-center mb-4">
                    <h4 class="fw-bold">Complete Your Payment</h4>
                    <p class="text-muted">Pay <strong class="text-dark fs-5">₹ <?= indianCurrencyFormat($selected_pkg['price'] ?? 0) ?></strong> for <strong><?= htmlspecialchars($selected_pkg['name'] ?? 'Package') ?></strong></p>
                </div>

                <div class="row justify-content-center mb-4">
                    <div class="col-md-8">
                        <div class="bank-box">
                            <h6 class="fw-bold mb-3">Scan QR Code to Pay</h6>
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=upi://pay?pa=yourupi@okaxis&pn=PrimeProperty" alt="QR Code" class="mb-3 rounded shadow-sm border p-2 bg-white" style="width: 150px; height: 150px;">
                            
                            <h6 class="fw-bold mb-2">OR Transfer to Bank Account</h6>
                            <p class="mb-1 text-dark"><strong>Bank Name:</strong> State Bank of India</p>
                            <p class="mb-1 text-dark"><strong>Account Name:</strong> Prime Property India</p>
                            <p class="mb-1 text-dark"><strong>Account No:</strong> 1234567890123</p>
                            <p class="mb-0 text-dark"><strong>IFSC Code:</strong> SBIN0001234</p>
                        </div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" class="col-md-8 mx-auto">
                    <input type="hidden" name="package_id" value="<?= $selected_package_id ?>">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Upload Payment Screenshot / Slip <span class="text-danger">*</span></label>
                        <input type="file" name="payment_slip" class="form-control form-control-lg" accept="image/*, .pdf" required>
                        <small class="text-muted">Upload a clear screenshot of your transaction.</small>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="apply_ba.php?step=1" class="btn btn-outline-secondary btn-lg rounded-pill w-50">Back</a>
                        <button type="submit" name="submit_payment" class="btn btn-success btn-lg rounded-pill w-50 fw-bold">
                            Submit Slip <i class="fas fa-check ms-2"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
