<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🛡️ SECURITY: Block Bad Bots & Rate Limiting
blockBadBots();
checkRateLimit(30, 60);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $pdo->prepare("SELECT ba_status, name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Agar pehle se active ya pending hai to wapas dashboard bhej do
if ($user['ba_status'] == 'active' || $user['ba_status'] == 'pending') {
    header("Location: user_dashboard.php");
    exit;
}

// 🔥 FETCH REAL PACKAGES FROM DATABASE (Same as Search Engine)
// Agar aapke packages table me status column hai (jaise status = 'active'), to yahan filter laga sakte hain.
$packages = $pdo->query("SELECT * FROM packages ORDER BY id ASC")->fetchAll();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$selected_package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

// Handle Form Submission (Payment Slip)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    $package_id = (int)$_POST['package_id'];
    
    // Get package price
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

    // Insert into subscriptions (Pending)
    $pdo->prepare("INSERT INTO subscriptions (user_id, package_id, amount, payment_screenshot, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())")->execute([$user_id, $package_id, $amount, $image_url]);

    // Update user status to pending
    $pdo->prepare("UPDATE users SET ba_status = 'pending' WHERE id = ?")->execute([$user_id]);

    header("Location: user_dashboard.php?msg=ba_submitted");
    exit;
}

include 'header.php';
?>

<style>
    .ba-card { background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; }
    .ba-header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; padding: 30px; text-align: center; }
    .package-card { border: 2px solid #e2e8f0; border-radius: 16px; padding: 20px; cursor: pointer; transition: all 0.3s; height: 100%; position: relative; background: #fff; }
    .package-card:hover { border-color: #2563eb; transform: translateY(-5px); box-shadow: 0 10px 20px rgba(37,99,235,0.1); }
    .package-card.selected { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37,99,235,0.2); }
    .package-input { position: absolute; opacity: 0; }
    .custom-checkbox { width: 20px; height: 20px; cursor: pointer; }
    .bank-box { background: #f8fafc; border: 1px dashed #94a3b8; border-radius: 12px; padding: 20px; text-align: center; }
    
    /* Icon Colors */
    .icon-silver { color: #64748b; }
    .icon-gold { color: #f59e0b; }
    .icon-platinum { color: #0ea5e9; }
    .icon-diamond { color: #3b82f6; }
    .icon-default { color: #8b5cf6; }
</style>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="ba-card">
                <div class="ba-header">
                    <h2 class="fw-bold mb-2"><i class="fas fa-handshake"></i> Apply for Business Associate (BA)</h2>
                    <p class="mb-0 opacity-75">Join our team, get trained, and start earning by referring users.</p>
                </div>

                <div class="p-4 p-md-5">

                    <!-- 🔥 TRAINING & BENEFITS SECTION -->
                    <div class="text-center mb-5">
                        <i class="fas fa-users fa-4x text-primary mb-3 opacity-50"></i>
                        <h4 class="fw-bold">Why Become a BA?</h4>
                        <p class="text-muted mb-4">We provide complete corporate training to help you build your team and generate income.</p>
                        <div class="d-flex justify-content-center gap-4 flex-wrap text-start">
                            <div><i class="fas fa-check-circle text-success me-2"></i> Direct & Team Commissions</div>
                            <div><i class="fas fa-check-circle text-success me-2"></i> Personal Referral Link</div>
                            <div><i class="fas fa-check-circle text-success me-2"></i> Real-time Income Tracking</div>
                            <div><i class="fas fa-check-circle text-success me-2"></i> Corporate Guidance</div>
                        </div>
                    </div>

                    <hr class="mb-5">

                    <?php if ($step == 1): ?>
                        <!-- 🔥 STEP 1: SELECT PACKAGE & T&C -->
                        <form action="apply_ba.php?step=2" method="GET">
                            <h5 class="fw-bold mb-4"><i class="fas fa-box"></i> Select Training Package</h5>
                            
                            <div class="row g-3 mb-4">
                                <?php if(count($packages) > 0): ?>
                                    <?php foreach ($packages as $pkg): 
                                        // Dynamic Icon based on Package Name
                                        $pkg_name = strtolower($pkg['name']);
                                        $icon_class = 'icon-default';
                                        $icon_fa = 'fa-medal';
                                        
                                        if (strpos($pkg_name, 'silver') !== false) { $icon_class = 'icon-silver'; $icon_fa = 'fa-cog'; }
                                        elseif (strpos($pkg_name, 'gold') !== false) { $icon_class = 'icon-gold'; $icon_fa = 'fa-crown'; }
                                        elseif (strpos($pkg_name, 'platinum') !== false) { $icon_class = 'icon-platinum'; $icon_fa = 'fa-gem'; }
                                        elseif (strpos($pkg_name, 'diamond') !== false) { $icon_class = 'icon-diamond'; $icon_fa = 'fa-diamond'; }
                                        elseif (strpos($pkg_name, 'visit') !== false || strpos($pkg_name, 'combo') !== false) { $icon_class = 'icon-platinum'; $icon_fa = 'fa-star'; }
                                    ?>
                                    <div class="col-md-6 col-lg-4 col-xl-3">
                                        <label class="w-100 m-0 h-100">
                                            <input type="radio" name="package_id" value="<?= $pkg['id'] ?>" class="package-input" required onchange="document.getElementById('pkg_id').value = this.value; document.querySelectorAll('.package-card').forEach(el => el.classList.remove('selected')); this.closest('.package-card').classList.add('selected');">
                                            <div class="package-card text-center d-flex flex-column justify-content-between">
                                                <div>
                                                    <i class="fas <?= $icon_fa ?> fa-3x <?= $icon_class ?> mb-3"></i>
                                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($pkg['name']) ?></h6>
                                                    <h5 class="fw-bold text-dark mt-2">₹ <?= indianCurrencyFormat($pkg['price']) ?></h5>
                                                </div>
                                                <p class="text-muted small mb-0 mt-3">Get Referral Link<br>+ Team Access</p>
                                            </div>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="alert alert-warning">No packages found. Please contact admin.</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- 🔥 T&C SECTION -->
                            <div class="bg-light p-3 rounded-3 mb-4 border">
                                <label class="d-flex align-items-start gap-3 cursor-pointer">
                                    <input type="checkbox" class="form-check-input custom-checkbox mt-1" required>
                                    <span class="small text-muted">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Privacy Policy</a>. I understand that the package amount is non-refundable and my BA account will be activated only after admin verifies the payment slip.
                                    </span>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold rounded-pill py-3" onclick="return checkPackageSelected()">
                                Proceed to Payment <i class="fas fa-arrow-right ms-2"></i>
                            </button>
                        </form>

                        <!-- Terms Modal -->
                        <div class="modal fade" id="termsModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content" style="border-radius: 20px;">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title">Terms & Conditions</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <h6 class="fw-bold">1. Training & Support</h6>
                                        <p class="small text-muted">We provide complete corporate training to help you grow your team. Misuse of the platform will lead to immediate termination.</p>
                                        <h6 class="fw-bold mt-3">2. Income & Payouts</h6>
                                        <p class="small text-muted">Commissions are calculated based on our MLM structure. Minimum payout limits apply.</p>
                                        <h6 class="fw-bold mt-3">3. Payment Verification</h6>
                                        <p class="small text-muted">Once you upload the payment slip, the admin will verify it within 24 hours. Your referral link will be activated only after verification.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            function checkPackageSelected() {
                                const selected = document.querySelector('input[name="package_id"]:checked');
                                if (!selected) {
                                    alert("Please select a package to continue.");
                                    return false;
                                }
                                return true;
                            }
                        </script>

                    <?php elseif ($step == 2 && $selected_package_id > 0): 
                        // Get selected package details for step 2
                        $pkg_stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
                        $pkg_stmt->execute([$selected_package_id]);
                        $selected_pkg = $pkg_stmt->fetch();
                    ?>
                        <!-- 🔥 STEP 2: PAYMENT GATEWAY -->
                        <div class="text-center mb-4">
                            <h4 class="fw-bold">Complete Your Payment</h4>
                            <p class="text-muted">Pay <strong class="text-dark">₹ <?= indianCurrencyFormat($selected_pkg['price'] ?? 0) ?></strong> for <strong><?= htmlspecialchars($selected_pkg['name'] ?? 'Package') ?></strong></p>
                        </div>

                        <div class="row justify-content-center mb-4">
                            <div class="col-md-8">
                                <div class="bank-box">
                                    <h6 class="fw-bold mb-3">Scan QR Code to Pay</h6>
                                    <!-- Replace this image src with your actual Google Pay/PhonePe QR Code -->
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

                    <?php else: ?>
                        <div class="alert alert-danger text-center">
                            Invalid Step. <a href="apply_ba.php">Click here to restart</a>.
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
