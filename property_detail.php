<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$property_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$property_id]);
$prop = $stmt->fetch();
if(!$prop) { die("Property not found!"); }

$has_subscription = userHasActiveSubscription($pdo, $user_id);
$show_image = $has_subscription;

include 'header.php'; 
$gradient = 'linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%)';
?>
<div class="container-fluid px-4 mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <a href="user_dashboard.php" class="btn btn-outline-secondary mb-4 shadow-sm rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>

            <div class="card border-0 shadow-xxl" style="border-radius: 28px; overflow: hidden; background: <?= $gradient ?>; color: #f0f4f8;">
                
                <!-- ===== DETAILS SECTION ===== (TOP) -->
                <div class="card-header p-4" style="background: rgba(0,0,0,0.25); border: none;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h2 class="fw-bold mb-1" style="color: #ffffff;"><i class="fas fa-gavel me-3" style="color: #ffd700;"></i><?= htmlspecialchars($prop['title']) ?></h2>
                            <span class="badge px-3 py-2 mt-2" style="background: #ffd700; color: #1a1a2e; font-weight:700;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank Auction') ?></span>
                        </div>
                        <?php if($has_subscription): ?>
                            <span class="badge bg-success px-3 py-2">✅ Subscribed</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark px-3 py-2">🔒 Unlocked</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- Details Grid -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.06); backdrop-filter:blur(4px);">
                                <small class="text-uppercase opacity-75">Borrower</small>
                                <h5 class="fw-bold" style="color:#fff;"><?= htmlspecialchars($prop['borrower_name'] ?? 'N/A') ?></h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.06); backdrop-filter:blur(4px);">
                                <small class="text-uppercase opacity-75">Property Type</small>
                                <h5 class="fw-bold" style="color:#fff;"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></h5>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.06); backdrop-filter:blur(4px); border-left:4px solid #ffd700;">
                                <i class="fas fa-home me-2" style="color:#ffd700;"></i>
                                <strong>Address:</strong> <?= htmlspecialchars($prop['location'] ?? 'Not Provided') ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(255,255,255,0.04);">
                                <small class="text-uppercase opacity-75"><i class="fas fa-map-pin"></i> City</small>
                                <h6 class="fw-bold" style="color:#fff;"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(255,255,255,0.04);">
                                <small class="text-uppercase opacity-75"><i class="fas fa-location-dot"></i> State</small>
                                <h6 class="fw-bold" style="color:#fff;"><?= htmlspecialchars($prop['state'] ?? 'N/A') ?></h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(255,255,255,0.04);">
                                <small class="text-uppercase opacity-75"><i class="fas fa-vector-square"></i> Area</small>
                                <h6 class="fw-bold" style="color:#fff;"><?= $prop['sqft'] ?? 0 ?> Sq Ft</h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.04);">
                                <small class="text-uppercase opacity-75">Auction Start</small>
                                <h6 class="fw-bold" style="color:#fff;"><?= htmlspecialchars($prop['auction_start_time'] ?? 'Not Set') ?></h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.04);">
                                <small class="text-uppercase opacity-75">Auction End</small>
                                <h6 class="fw-bold" style="color:#fff;"><?= htmlspecialchars($prop['auction_end_time'] ?? 'Not Set') ?></h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(255,215,0,0.10); border:1px solid rgba(255,215,0,0.2);">
                                <small class="text-uppercase opacity-75">Reserve Price</small>
                                <h4 class="fw-bold" style="color:#ffd700;">₹ <?= indianCurrencyFormat($prop['price']) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(100,200,255,0.08); border:1px solid rgba(100,200,255,0.15);">
                                <small class="text-uppercase opacity-75">EMD Amount</small>
                                <h4 class="fw-bold" style="color:#64c8ff;">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(52,211,153,0.08); border:1px solid rgba(52,211,153,0.15);">
                                <small class="text-uppercase opacity-75">Bid Increment</small>
                                <h4 class="fw-bold" style="color:#34d399;">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.04);">
                                <i class="fas fa-phone text-success me-2"></i> 
                                <strong>Contact:</strong>
                                <?php if(!empty($prop['contact_number'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $prop['contact_number']) ?>" target="_blank" style="text-decoration:none; font-weight:bold; color:#25D366;">
                                        <?= htmlspecialchars($prop['contact_number']) ?>
                                        <i class="fab fa-whatsapp ms-1"></i>
                                    </a>
                                <?php else: ?> N/A <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <?php if(!empty($prop['google_location'])): ?>
                                <a href="<?= $prop['google_location'] ?>" target="_blank" class="btn btn-outline-light w-100 rounded-4" style="border-color:rgba(255,255,255,0.15);">
                                    <i class="fas fa-map-marked-alt me-2"></i> View on Google Maps
                                </a>
                            <?php else: ?>
                                <span class="text-muted">No Map Link Available</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ===== "SUBSCRIBE TO UNLOCK" BANNER ===== (BOTTOM) -->
                    <?php if(!$has_subscription): ?>
                        <div class="mt-5 p-4 rounded-4 text-center" style="background:rgba(255,215,0,0.10); border:2px dashed rgba(255,215,0,0.25); backdrop-filter:blur(4px);">
                            <i class="fas fa-lock" style="font-size:2.5rem; color:#ffd700; opacity:0.7;"></i>
                            <h4 class="mt-2" style="color:#ffd700;">🔒 Subscribe to Unlock Full Details</h4>
                            <p class="opacity-75">Get access to all property images and complete information.</p>
                            <a href="user_packages.php" class="btn btn-lg mt-2" style="background:#ffd700; color:#1a1a2e; font-weight:700; border-radius:30px; padding:12px 40px;">
                                <i class="fas fa-rocket me-2"></i> Subscribe Now
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- ===== IMAGE SECTION ===== (Still at Bottom, but after the unlock banner) -->
                    <div class="mt-4">
                        <h5 class="text-warning"><i class="fas fa-image me-2"></i>Property Image</h5>
                        <?php if($has_subscription && !empty($prop['image_url'])): ?>
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background:rgba(255,255,255,0.04);">
                                <a href="<?= htmlspecialchars($prop['image_url']) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($prop['image_url']) ?>" class="img-fluid" style="width:100%; max-height:400px; object-fit:contain; cursor:pointer;">
                                </a>
                                <div class="text-center py-2" style="background:rgba(0,0,0,0.15);">
                                    <small class="opacity-75">Click image to open full size</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background:rgba(255,255,255,0.03); display:flex; align-items:center; justify-content:center; height:200px;">
                                <div class="text-center p-4">
                                    <i class="fas fa-image" style="font-size:60px; opacity:0.2;"></i>
                                    <p class="mt-2 opacity-50">No Image Available</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
    .shadow-xxl { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3) !important; }
    .rounded-4 { border-radius: 1.25rem !important; }
</style>
<?php include 'footer.php'; ?>
