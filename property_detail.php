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

            <div class="card border-0 shadow-xxl" style="border-radius: 28px; overflow: hidden; background: #ffffff;">
                <div class="card-header p-0" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border: none;">
                    <div class="p-4 text-white">
                        <h1 class="display-6 fw-bold mb-1"><i class="fas fa-gavel me-3" style="color: #fbbf24;"></i><?= htmlspecialchars($prop['title']) ?></h1>
                        <span class="badge bg-warning text-dark px-3 py-2 mt-2">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank Auction') ?></span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="row g-0">
                        <div class="col-lg-8 p-4">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6"><div class="bg-light p-3 rounded-4 h-100"><small class="text-muted text-uppercase fw-bold">Borrower</small><h5 class="fw-bold"><?= htmlspecialchars($prop['borrower_name'] ?? 'N/A') ?></h5></div></div>
                                <div class="col-md-6"><div class="bg-light p-3 rounded-4 h-100"><small class="text-muted text-uppercase fw-bold">Property Type</small><h5 class="fw-bold"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></h5></div></div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4"><div class="bg-gradient-to-br p-3 rounded-4" style="background: #f8fafc;"><small class="text-muted"><i class="fas fa-map-pin me-1"></i> City</small><h6 class="fw-bold"><?= htmlspecialchars($prop['city']) ?></h6></div></div>
                                <div class="col-md-4"><div class="bg-gradient-to-br p-3 rounded-4" style="background: #f8fafc;"><small class="text-muted"><i class="fas fa-location-dot me-1"></i> Locality</small><h6 class="fw-bold"><?= htmlspecialchars($prop['locality'] ?? 'N/A') ?></h6></div></div>
                                <div class="col-md-4"><div class="bg-gradient-to-br p-3 rounded-4" style="background: #f8fafc;"><small class="text-muted"><i class="fas fa-vector-square me-1"></i> Area</small><h6 class="fw-bold"><?= $prop['sqft'] ?? 0 ?> Sq Ft</h6></div></div>
                            </div>

                            <div class="mb-4 p-3 rounded-4" style="background: #f1f5f9; border-left: 4px solid #2563eb;">
                                <i class="fas fa-home me-2" style="color: #2563eb;"></i>
                                <strong>Address:</strong> <?= htmlspecialchars($prop['location']) ?>
                            </div>

                            <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
                                <div class="card-header bg-dark text-white"><i class="fas fa-clock me-2"></i> Auction Schedule</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6"><div class="border-bottom pb-2"><strong>Start:</strong> <?= htmlspecialchars($prop['auction_start_time'] ?? 'Not Set') ?></div><div class="mt-2"><strong>End:</strong> <?= htmlspecialchars($prop['auction_end_time'] ?? 'Not Set') ?></div></div>
                                        <div class="col-md-6"><div><strong>EMD Deadline:</strong> <?= htmlspecialchars($prop['emd_deadline'] ?? 'N/A') ?></div><div class="mt-2"><strong>Possession:</strong> <?= htmlspecialchars($prop['possession_type'] ?? 'Physical') ?></div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4"><div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #fef3c7, #fde68a);"><small class="text-uppercase fw-bold text-dark">Reserve Price</small><h4 class="fw-bold text-dark">₹ <?= indianCurrencyFormat($prop['price']) ?></h4></div></div>
                                <div class="col-md-4"><div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #e0e7ff, #c7d2fe);"><small class="text-uppercase fw-bold">EMD Amount</small><h4 class="fw-bold">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></h4></div></div>
                                <div class="col-md-4"><div class="p-3 rounded-4 text-center" style="background: linear-gradient(145deg, #d1fae5, #a7f3d0);"><small class="text-uppercase fw-bold">Bid Increment</small><h4 class="fw-bold">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></h4></div></div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6"><div class="p-3 rounded-4" style="background: #f0fdf4; border: 1px solid #bbf7d0;"><i class="fas fa-phone text-success me-2"></i> <strong>Contact:</strong> <?= htmlspecialchars($prop['contact_number'] ?? 'N/A') ?></div></div>
                                <div class="col-md-6"><?php if(!empty($prop['google_location'])): ?><a href="<?= $prop['google_location'] ?>" target="_blank" class="btn btn-outline-primary w-100 rounded-4"><i class="fas fa-map-marked-alt me-2"></i> View on Google Maps</a><?php else: ?><span class="text-muted">No Map Link Available</span><?php endif; ?></div>
                            </div>
                        </div>

                        <div class="col-lg-4 p-4" style="background: #f8fafc;">
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                                <a href="<?= htmlspecialchars($prop['image_url'] ?: 'https://via.placeholder.com/600x400?text=Property') ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($prop['image_url'] ?: 'https://via.placeholder.com/600x400?text=Property') ?>" class="img-fluid" style="height: 280px; width: 100%; object-fit: cover; cursor: pointer;">
                                </a>
                                <div class="text-center py-1 bg-light"><small class="text-muted">Click image to open full size</small></div>
                            </div>
                            
                            <div class="mt-4 p-3 rounded-4" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white;">
                                <h6 class="text-warning"><i class="fas fa-shield-alt me-2"></i>Quick Summary</h6>
                                <ul class="list-unstyled small">
                                    <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Bank: <?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></li>
                                    <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Area: <?= $prop['sqft'] ?? 0 ?> Sq Ft</li>
                                    <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>Possession: <?= htmlspecialchars($prop['possession_type'] ?? 'Physical') ?></li>
                                    <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i>EMD: ₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></li>
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
    .bg-gradient-to-br { background: linear-gradient(145deg, #ffffff, #f1f5f9); }
    .rounded-4 { border-radius: 1.25rem !important; }
</style>
<?php include 'footer.php'; ?>
