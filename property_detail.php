<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$property_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$property_id]);
$prop = $stmt->fetch();
if(!$prop) { die("Property not found!"); }

$active_sub = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
$active_sub->execute([$user_id]);
$has_access = $active_sub->rowCount() > 0;

if(!$has_access) {
    header("Location: dashboard.php?msg=subscribe_first");
    exit;
}

include 'header.php'; 
?>
<div class="container-fluid px-4 mt-4">
    <div class="row">
        <div class="col-12">
            <a href="dashboard.php" class="btn btn-outline-secondary mb-4 shadow-sm">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>

            <!-- Main Card -->
            <div class="card border-0 shadow-xxl" style="border-radius: 28px; overflow: hidden; background: #ffffff;">
                
                <!-- Header -->
                <div class="card-header p-0" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border: none;">
                    <div class="p-4 text-white">
                        <h1 class="display-5 fw-bold mb-1"><i class="fas fa-gavel me-3" style="color: #fbbf24;"></i><?= htmlspecialchars($prop['title']) ?></h1>
                        <span class="badge bg-warning text-dark px-3 py-2 mt-2 fs-6">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank Auction') ?></span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="row g-0">
                        <!-- LEFT: Details -->
                        <div class="col-lg-7 p-4">
                            
                            <!-- ⭐ Price & EMD - Stylish Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center shadow-sm" style="background: linear-gradient(145deg, #fef3c7, #fde68a); border: 1px solid #f59e0b;">
                                        <small class="text-uppercase fw-bold text-dark">Reserve Price</small>
                                        <h3 class="fw-bold text-dark">₹ <?= indianCurrencyFormat($prop['price']) ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center shadow-sm" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe); border: 1px solid #6366f1;">
                                        <small class="text-uppercase fw-bold">EMD Amount</small>
                                        <h3 class="fw-bold">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4 text-center shadow-sm" style="background: linear-gradient(145deg, #d1fae5, #a7f3d0); border: 1px solid #10b981;">
                                        <small class="text-uppercase fw-bold">Bid Increment</small>
                                        <h3 class="fw-bold">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></h3>
                                    </div>
                                </div>
                            </div>

                            <!-- ⏰ Auction Schedule - Stylish -->
                            <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
                                <div class="card-header bg-dark text-white fw-bold"><i class="fas fa-clock me-2"></i> Auction Schedule</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="p-3 rounded-3" style="background: #f8fafc;">
                                                <span class="badge bg-primary mb-2">START</span>
                                                <p class="fw-bold mb-0"><?= htmlspecialchars($prop['auction_start_time'] ?? 'Not Set') ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 rounded-3" style="background: #f8fafc;">
                                                <span class="badge bg-danger mb-2">END</span>
                                                <p class="fw-bold mb-0"><?= htmlspecialchars($prop['auction_end_time'] ?? 'Not Set') ?></p>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="p-3 rounded-3" style="background: #f1f5f9;">
                                                <span class="badge bg-warning text-dark mb-2">⏳ EMD DEADLINE</span>
                                                <p class="fw-bold mb-0"><?= htmlspecialchars($prop['emd_deadline'] ?? 'N/A') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 📍 Location & Area - Stylish Grid -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4" style="background: #f0f9ff; border: 1px solid #bae6fd;">
                                        <i class="fas fa-map-pin text-blue-500 me-2"></i>
                                        <small class="text-muted d-block">City</small>
                                        <strong><?= htmlspecialchars($prop['city']) ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                        <i class="fas fa-location-dot text-green-500 me-2"></i>
                                        <small class="text-muted d-block">Locality</small>
                                        <strong><?= htmlspecialchars($prop['locality'] ?? 'N/A') ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 rounded-4" style="background: #fefce8; border: 1px solid #fde68a;">
                                        <i class="fas fa-vector-square text-yellow-500 me-2"></i>
                                        <small class="text-muted d-block">Area</small>
                                        <strong><?= $prop['sqft'] ?? 0 ?> Sq Ft</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="mb-4 p-3 rounded-4" style="background: #f1f5f9; border-left: 4px solid #2563eb;">
                                <i class="fas fa-home me-2" style="color: #2563eb;"></i>
                                <strong>Address:</strong> <?= htmlspecialchars($prop['location']) ?>
                            </div>

                            <!-- Bank & Borrower -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4" style="background: #fff7ed; border: 1px solid #fdba74;">
                                        <i class="fas fa-university text-orange-500 me-2"></i>
                                        <small class="text-muted d-block">Bank</small>
                                        <strong><?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4" style="background: #f3e8ff; border: 1px solid #d8b4fe;">
                                        <i class="fas fa-user text-purple-500 me-2"></i>
                                        <small class="text-muted d-block">Borrower</small>
                                        <strong><?= htmlspecialchars($prop['borrower_name'] ?? 'N/A') ?></strong>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact & Map -->
                            <div class="row g-3 mt-3">
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                                        <i class="fas fa-phone text-success me-2"></i>
                                        <strong>Contact:</strong> <?= htmlspecialchars($prop['contact_number'] ?? 'N/A') ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <?php if(!empty($prop['google_location'])): ?>
                                        <a href="<?= $prop['google_location'] ?>" target="_blank" class="btn btn-outline-primary w-100 rounded-4">
                                            <i class="fas fa-map-marked-alt me-2"></i> View on Google Maps
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">No Map Link Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Possession -->
                            <div class="mt-3 p-3 rounded-4" style="background: #ecfdf5; border: 1px solid #6ee7b7;">
                                <i class="fas fa-hand text-emerald-500 me-2"></i>
                                <strong>Possession:</strong> <?= htmlspecialchars($prop['possession_type'] ?? 'Physical') ?>
                            </div>
                        </div>

                        <!-- RIGHT: Image -->
                        <div class="col-lg-5 p-4" style="background: #f8fafc;">
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                                <a href="<?= htmlspecialchars($prop['image_url'] ?: 'https://via.placeholder.com/600x400?text=Property') ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($prop['image_url'] ?: 'https://via.placeholder.com/600x400?text=Property') ?>" class="img-fluid" style="height: 320px; width: 100%; object-fit: cover; cursor: pointer;">
                                </a>
                                <div class="text-center py-2 bg-light"><small class="text-muted">🖱️ Click image to open full size</small></div>
                            </div>
                            
                            <!-- Quick Summary -->
                            <div class="mt-4 p-3 rounded-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white;">
                                <h6 class="text-warning"><i class="fas fa-shield-alt me-2"></i>Quick Summary</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Bank: <?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Area: <?= $prop['sqft'] ?? 0 ?> Sq Ft</li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Possession: <?= htmlspecialchars($prop['possession_type'] ?? 'Physical') ?></li>
                                    <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>EMD: ₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .shadow-xxl { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important; }
    .rounded-4 { border-radius: 1.25rem !important; }
    .text-blue-500 { color: #3b82f6; }
    .text-green-500 { color: #22c55e; }
    .text-yellow-500 { color: #eab308; }
    .text-orange-500 { color: #f97316; }
    .text-purple-500 { color: #a855f7; }
    .text-emerald-500 { color: #10b981; }
</style>

<?php include 'footer.php'; ?>
