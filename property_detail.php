<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$property_id = $_GET['id'] ?? 0;
$source = $_GET['source'] ?? 'auction';
$user_id = $_SESSION['user_id'];

if ($property_id) {
    logActivity($pdo, $user_id, 'property_view', 'Property ID: ' . $property_id . ', Source: ' . $source);
}

if($source == 'auction') {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$property_id]);
    $prop = $stmt->fetch();
    $is_customer = false;
} else {
    $stmt = $pdo->prepare("SELECT * FROM user_properties WHERE id = ? AND status = 'approved'");
    $stmt->execute([$property_id]);
    $prop = $stmt->fetch();
    $is_customer = true;
}
if(!$prop) { die("Property not found!"); }

if($source == 'auction') {
    $has_subscription = userHasActiveSubscription($pdo, $user_id);
} else {
    $has_subscription = true;
}

include 'header.php'; 

if(!$has_subscription && $source == 'auction') {
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg" style="border-radius: 30px; overflow: hidden;">
                    <div class="card-header text-white text-center p-4" style="background: linear-gradient(135deg, #1e293b, #3b82f6);">
                        <h3><i class="fas fa-lock me-2"></i>🔒 Access Restricted</h3>
                        <p class="mb-0 opacity-75">Subscribe to view full auction property details</p>
                    </div>
                    <div class="card-body p-4" style="background: #f8fafc;">
                        <div class="text-center mb-4">
                            <i class="fas fa-building" style="font-size: 4rem; color: #94a3b8;"></i>
                            <h4 class="mt-2"><?= htmlspecialchars($prop['title']) ?></h4>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #dcfce7; border-left: 5px solid #22c55e;">
                                    <small class="text-muted text-uppercase fw-bold">💰 Reserve Price</small>
                                    <h6 class="fw-bold mb-0 text-success">₹ <?= indianCurrencyFormat($prop['price']) ?></h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #e0e7ff; border-left: 5px solid #6366f1;">
                                    <small class="text-muted text-uppercase fw-bold">🏦 Bank Name</small>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #fef3c7; border-left: 5px solid #f59e0b;">
                                    <small class="text-muted text-uppercase fw-bold">📍 City</small>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #fce4ec; border-left: 5px solid #ef5350;">
                                    <small class="text-muted text-uppercase fw-bold">📅 Auction Date</small>
                                    <h6 class="fw-bold mb-0"><?= !empty($prop['auction_date']) ? date('d M Y', strtotime($prop['auction_date'])) : 'N/A' ?></h6>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <a href="user_packages.php" class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow">
                                <i class="fas fa-rocket me-2"></i> Subscribe Now
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-lg px-4 ms-2 rounded-pill">⬅ Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php 
    include 'footer.php'; 
    exit;
}

$gradient = 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)';
$image_url = ($source == 'auction') ? ($prop['image_url'] ?? '') : ($prop['image_url'] ?? '');
$show_images = ($source == 'auction') ? $has_subscription : true;
?>
<div class="container-fluid px-4 mt-4">
    <!-- आपका original full detail code यहाँ डालें – इस safe version में मैंने notification हटा दिया है -->
    <!-- कृपया अपनी पुरानी working `property_detail.php` का पूरा HTML/ PHP code यहाँ paste करें -->
    <!-- यह सिर्फ एक placeholder है, असली कोड आपके पास है -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card border-0 shadow-xxl" style="border-radius: 28px; overflow: hidden; background: <?= $gradient ?>; color:#fff;">
                <div class="card-header p-4" style="background: rgba(0,0,0,0.2); border: none;">
                    <h2 class="fw-bold"><?= htmlspecialchars($prop['title']) ?></h2>
                </div>
                <div class="card-body p-4">
                    <p>This is a safe fallback version. Please replace this with your original working code.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .shadow-xxl { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important; }
    .rounded-4 { border-radius: 1.25rem !important; }
</style>
<?php include 'footer.php'; ?>
