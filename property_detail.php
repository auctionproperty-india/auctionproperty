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

// ---- IF NOT SUBSCRIBED ----
if(!$has_subscription) {
    include 'header.php'; 
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-lg" style="border-radius: 30px; overflow: hidden;">
                    <div class="card-header text-white text-center p-4" style="background: linear-gradient(135deg, #1e293b, #3b82f6);">
                        <h3><i class="fas fa-lock me-2"></i>🔒 Access Restricted</h3>
                        <p class="mb-0 opacity-75">Subscribe to view full property details including images and complete information.</p>
                    </div>
                    <div class="card-body p-4" style="background: #f8fafc;">
                        <div class="text-center mb-4">
                            <i class="fas fa-building" style="font-size: 4rem; color: #94a3b8;"></i>
                            <h4 class="mt-2"><?= htmlspecialchars($prop['title']) ?></h4>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #fef3c7; border-left: 5px solid #f59e0b;">
                                    <small class="text-muted text-uppercase fw-bold">📍 City</small>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></h6>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #dcfce7; border-left: 5px solid #22c55e;">
                                    <small class="text-muted text-uppercase fw-bold">💰 Reserve Price</small>
                                    <h6 class="fw-bold mb-0 text-success">₹ <?= indianCurrencyFormat($prop['price']) ?></h6>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #e0e7ff; border-left: 5px solid #6366f1;">
                                    <small class="text-muted text-uppercase fw-bold">📅 Auction Date</small>
                                    <h6 class="fw-bold mb-0"><?= !empty($prop['auction_date']) ? date('d M Y', strtotime($prop['auction_date'])) : 'N/A' ?></h6>
                                </div>
                            </div>
                        </div>
                        <div class="text-center mt-4">
                            <a href="user_packages.php" class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow">
                                <i class="fas fa-rocket me-2"></i> Buy Subscription Now
                            </a>
                            <a href="user_dashboard.php" class="btn btn-outline-secondary btn-lg px-4 ms-2 rounded-pill">⬅ Go Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; exit;
}

// ----- SUBSCRIBED USER: Colorful Layout with Detail Box UP and Summary Box DOWN -----
include 'header.php'; 
?>
<div class="container-fluid px-4 mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Back Button -->
            <a href="user_dashboard.php" class="btn btn-outline-secondary mb-4 shadow-sm rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>

            <!-- Main Card with Colorful Background -->
            <div class="card border-0 shadow-xxl" style="border-radius: 28px; overflow: hidden; background: linear-gradient(145deg, #ffffff, #f0f4ff);">
                
                <!-- Header with Gradient -->
                <div class="card-header p-4" style="background: linear-gradient(135deg, #1e293b 0%, #3b82f6 100%); border: none;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h2 class="text-white fw-bold mb-1"><i class="fas fa-gavel me-3" style="color: #fbbf24;"></i><?= htmlspecialchars($prop['title']) ?></h2>
                            <span class="badge bg-warning text-dark px-3 py-2 mt-2">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank Auction') ?></span>
                        </div>
                        <div class="text-white">
                            <span class="badge bg-success px-3 py-2">✅ Subscribed</span>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    
                    <!-- ========================================================== -->
                    <!-- ✅ DETAIL BOX - UP (Colorful Background)                    -->
                    <!-- ========================================================== -->
                    <div class="row g-4">
                        
                        <!-- Image Column -->
                        <div class="col-lg-4">
                            <?php if($show_image && !empty($prop['image_url'])): ?>
                                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                                    <a href="<?= htmlspecialchars($prop['image_url']) ?>" target="_blank">
                                        <img src="<?= htmlspecialchars($prop['image_url']) ?>" class="img-fluid" style="height: 260px; width: 100%; object-fit: cover; cursor: pointer;">
                                    </a>
                                    <div class="text-center py-1 bg-light"><small class="text-muted">Click image to open full size</small></div>
                                </div>
                            <?php else: ?>
                                <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background: #f1f5f9; display: flex; align-items: center; justify-content: center; height: 260px;">
                                    <div class="text-center p-4">
                                        <i class="fas fa-image" style="font-size: 60px; color: #94a3b8;"></i>
                                        <p class="text-muted mt-2">No Image Available</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Details Column -->
                        <div class="col-lg-8">
                            <!-- Borrower & Type -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe);">
                                        <small class="text-uppercase fw-bold" style="color: #1e3a8a;">Borrower</small>
                                        <h5 class="fw-bold text-dark"><?= htmlspecialchars($prop['borrower_name'] ?? 'N/A') ?></h5>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4" style="background: linear-gradient(145deg, #d1fae5, #a7f3d0);">
                                        <small class="text-uppercase fw-bold" style="color: #065f46;">Property Type</small>
                                        <h5 class="fw-bold text-dark"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></h5>
                                    </div>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="mb-3 p-3 rounded-4" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border-left: 4px solid #f59e0b;">
                                <i class="fas fa-home me-2" style="color: #b45309;"></i>
                                <strong>Address:</strong> <?= htmlspecialchars($prop['location'] ?? 'Not Provided') ?>
                            </div>

                            <!-- Location Details -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #fce7f3, #fbcfe8);">
                                        <small class="text-uppercase fw-bold" style="color: #9d174d;">City</small>
                                        <h6 class="fw-bold text-dark"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe);">
                                        <small class="text-uppercase fw-bold" style="color: #1e3a8a;">State</small>
                                        <h6 class="fw-bold text-dark"><?= htmlspecialchars($prop['state'] ?? 'N/A') ?></h6>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #d1fae5, #a7f3d0);">
                                        <small class="text-uppercase fw-bold" style="color: #065f46;">Area</small>
                                        <h6 class="fw-bold text-dark"><?= $prop['sqft'] ?? 0 ?> Sq Ft</h6>
                                    </div>
                                </div>
                            </div>

                            <!-- Auction Schedule -->
                            <div class="card border-0 shadow-sm mb-3 rounded-4 overflow-hidden" style="background: linear-gradient(145deg, #fef2f2, #fecaca);">
                                <div class="card-header" style="background: #dc2626; color: white;"><i class="fas fa-clock me-2"></i> Auction Schedule</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div><strong>Start:</strong> <?= htmlspecialchars($prop['auction_start_time'] ?? 'Not Set') ?></div>
                                            <div class="mt-2"><strong>End:</strong> <?= htmlspecialchars($prop['auction_end_time'] ?? 'Not Set') ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div><strong>EMD Deadline:</strong> <?= htmlspecialchars($prop['emd_deadline'] ?? 'N/A') ?></div>
                                            <div class="mt-2"><strong>Possession:</strong> <?= htmlspecialchars($prop['possession_type'] ?? 'Physical') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Price, EMD, Bid -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #fef3c7, #fde68a);">
                                        <small class="text-uppercase fw-bold text-dark">Reserve Price</small>
                                        <h4 class="fw-bold text-dark">₹ <?= indianCurrencyFormat($prop['price']) ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe);">
                                        <small class="text-uppercase fw-bold">EMD Amount</small>
                                        <h4 class="fw-bold text-dark">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #d1fae5, #a7f3d0);">
                                        <small class="text-uppercase fw-bold">Bid Increment</small>
                                        <h4 class="fw-bold text-dark">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></h4>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact & Map -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4" style="background: linear-gradient(145deg, #d1fae5, #a7f3d0); border: 1px solid #bbf7d0;">
                                        <i class="fas fa-phone text-success me-2"></i> 
                                        <strong>Contact:</strong>
                                        <?php if(!empty($prop['contact_number'])): ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $prop['contact_number']) ?>" target="_blank" style="text-decoration:none; font-weight:bold; color:#25D366;">
                                                <?= htmlspecialchars($prop['contact_number']) ?>
                                                <i class="fab fa-whatsapp ms-1"></i>
                                            </a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <?php if(!empty($prop['google_location'])): ?>
                                        <a href="<?= $prop['google_location'] ?>" target="_blank" class="btn btn-outline-primary w-100 rounded-4" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border: none;">
                                            <i class="fas fa-map-marked-alt me-2"></i> View on Google Maps
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No Map Link Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ========================================================== -->
                    <!-- ✅ QUICK SUMMARY BOX - DOWN (Colorful Gradient)            -->
                    <!-- ========================================================== -->
                    <div class="mt-4 p-4 rounded-4" style="background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #1e293b 100%); color: white; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="text-warning"><i class="fas fa-shield-alt me-2"></i>Quick Summary</h6>
                                <ul class="list-unstyled small mb-0">
                                    <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Bank: <?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></li>
                                    <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>City: <?= htmlspecialchars($prop['city'] ?? 'N/A') ?></li>
                                    <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Area: <?= $prop['sqft'] ?? 0 ?> Sq Ft</li>
                                    <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Possession: <?= htmlspecialchars($prop['possession_type'] ?? 'Physical') ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <span class="badge bg-warning text-dark px-3 py-2 me-1">💰 <?= indianCurrencyFormat($prop['price']) ?></span>
                                <span class="badge bg-info px-3 py-2">📅 <?= !empty($prop['auction_date']) ? date('d M Y', strtotime($prop['auction_date'])) : 'N/A' ?></span>
                            </div>
                        </div>
                    </div>
                    <!-- ========================================================== -->

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
