<?php
// After fetching user_id and property
// Log property view
if (isset($user_id) && isset($property_id)) {
    $source = $_GET['source'] ?? 'auction';
    logActivity($pdo, $user_id, 'property_view', 'Property ID: ' . $property_id . ', Source: ' . $source);
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$property_id = $_GET['id'] ?? 0;
$source = $_GET['source'] ?? 'auction';
$user_id = $_SESSION['user_id'];

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
    // ... (same as before, keep the restricted view) ...
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

// ----- SUBSCRIBED or CUSTOMER: Show ALL Details -----
$gradient = 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)';
$image_url = ($source == 'auction') ? ($prop['image_url'] ?? '') : ($prop['image_url'] ?? '');

// ---- Similar Properties (only for auction, based on city and price) ----
$similar_props = [];
if($source == 'auction') {
    $city = $prop['city'] ?? '';
    $price = (float)$prop['price'];
    $min_price = $price * 0.7; // 30% less
    $max_price = $price * 1.3; // 30% more
    $sql = "SELECT id, title, price, city, image_url, bank_name, auction_date 
            FROM properties 
            WHERE status = 'available' 
            AND id != ? 
            AND (city ILIKE ? OR city = ?) 
            AND price BETWEEN ? AND ? 
            ORDER BY id DESC 
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$property_id, '%'.$city.'%', $city, $min_price, $max_price]);
    $similar_props = $stmt->fetchAll();
}
?>
<div class="container-fluid px-4 mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <a href="javascript:history.back()" class="btn btn-outline-secondary mb-4 shadow-sm rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>

            <div class="card border-0 shadow-xxl" style="border-radius: 28px; overflow: hidden; background: <?= $gradient ?>; color:#fff;">
                <!-- ... (same header and body as before) ... -->
                <div class="card-header p-4" style="background: rgba(0,0,0,0.2); border: none;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h2 class="fw-bold mb-1"><i class="fas fa-gavel me-3" style="color: #fbbf24;"></i><?= htmlspecialchars($prop['title']) ?></h2>
                            <span class="badge bg-warning text-dark px-3 py-2 mt-2">🏦 <?= htmlspecialchars($prop['bank_name'] ?? ($source=='customer' ? 'Customer Property' : 'Bank Auction')) ?></span>
                        </div>
                        <span class="badge bg-success px-3 py-2"><?= ($source=='auction' ? '✅ Subscribed' : '🏠 Customer Listed') ?></span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- All Details (same as before) -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.08);">
                                <small class="text-uppercase opacity-75">Borrower</small>
                                <h5 class="fw-bold"><?= htmlspecialchars($prop['borrower_name'] ?? ($source=='customer' ? 'Customer Listed' : 'N/A')) ?></h5>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.08);">
                                <small class="text-uppercase opacity-75">Property Type</small>
                                <h5 class="fw-bold"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></h5>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.08); border-left:4px solid #fbbf24;">
                                <i class="fas fa-home me-2" style="color:#fbbf24;"></i>
                                <strong>Address:</strong> <?= htmlspecialchars($prop['location'] ?? ($prop['city'] ?? 'Not Provided')) ?>
                            </div>
                        </div>

                        <!-- Area and Construction Area (for customer) -->
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(255,255,255,0.08);">
                                <small class="text-uppercase opacity-75"><i class="fas fa-map-pin"></i> City</small>
                                <h6 class="fw-bold"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></h6>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(255,255,255,0.08);">
                                <small class="text-uppercase opacity-75"><i class="fas fa-location-dot"></i> State</small>
                                <h6 class="fw-bold"><?= htmlspecialchars($prop['state'] ?? 'N/A') ?></h6>
                            </div>
                        </div>

                        <?php if($source == 'customer'): ?>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 text-center" style="background:rgba(255,255,255,0.08);">
                                    <small class="text-uppercase opacity-75"><i class="fas fa-vector-square"></i> Area</small>
                                    <h6 class="fw-bold"><?= $prop['sqft'] ?? 'N/A' ?> Sq Ft</h6>
                                </div>
                            </div>
                            <?php if(!empty($prop['construction_sqft']) && $prop['construction_sqft'] > 0): ?>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 text-center" style="background:rgba(255,255,255,0.08);">
                                    <small class="text-uppercase opacity-75"><i class="fas fa-building"></i> Construction Area</small>
                                    <h6 class="fw-bold"><?= $prop['construction_sqft'] ?> Sq Ft</h6>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="col-md-4">
                                <div class="p-3 rounded-4 text-center" style="background:rgba(255,255,255,0.08);">
                                    <small class="text-uppercase opacity-75"><i class="fas fa-vector-square"></i> Area</small>
                                    <h6 class="fw-bold"><?= $prop['sqft'] ?? 'N/A' ?> Sq Ft</h6>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Price -->
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(251,191,36,0.15); border:1px solid rgba(251,191,36,0.3);">
                                <small class="text-uppercase opacity-75">Reserve Price</small>
                                <h4 class="fw-bold" style="color:#fbbf24;">₹ <?= indianCurrencyFormat($prop['price']) ?></h4>
                            </div>
                        </div>
                        <?php if($source == 'auction'): ?>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(99,102,241,0.15); border:1px solid rgba(99,102,241,0.3);">
                                <small class="text-uppercase opacity-75">EMD Amount</small>
                                <h4 class="fw-bold" style="color:#818cf8;">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-4 text-center" style="background:rgba(52,211,153,0.15); border:1px solid rgba(52,211,153,0.3);">
                                <small class="text-uppercase opacity-75">Bid Increment</small>
                                <h4 class="fw-bold" style="color:#34d399;">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></h4>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Contact -->
                        <div class="col-md-6">
                            <div class="p-3 rounded-4" style="background:rgba(255,255,255,0.08);">
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
                                <a href="<?= $prop['google_location'] ?>" target="_blank" class="btn btn-outline-light w-100 rounded-4">
                                    <i class="fas fa-map-marked-alt me-2"></i> View on Google Maps
                                </a>
                            <?php else: ?>
                                <span class="text-muted">No Map Link Available</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Image Section -->
                    <div class="mt-5">
                        <h5 class="text-warning"><i class="fas fa-image me-2"></i>Property Image</h5>
                        <?php if(!empty($image_url)): ?>
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background:rgba(255,255,255,0.05);">
                                <a href="<?= htmlspecialchars($image_url) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($image_url) ?>" class="img-fluid" style="width:100%; max-height:400px; object-fit:contain; cursor:pointer;">
                                </a>
                                <div class="text-center py-2" style="background:rgba(0,0,0,0.2);">
                                    <small class="opacity-75">Click image to open full size</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; height:200px;">
                                <div class="text-center p-4">
                                    <i class="fas fa-image" style="font-size:60px; opacity:0.3;"></i>
                                    <p class="mt-2 opacity-75">No Image Available</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ===== SIMILAR PROPERTIES SECTION (only for auction) ===== -->
                    <?php if($source == 'auction' && count($similar_props) > 0): ?>
                    <div class="mt-5">
                        <h5 class="text-warning"><i class="fas fa-list-ul me-2"></i>Similar Properties</h5>
                        <div class="row g-3">
                            <?php foreach($similar_props as $sim): ?>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm rounded-4 overflow-hidden" style="background:rgba(255,255,255,0.05); color:#fff;">
                                    <?php if($show_images && !empty($sim['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($sim['image_url']) ?>" style="height:150px; object-fit:cover;" alt="<?= htmlspecialchars($sim['title']) ?>">
                                    <?php else: ?>
                                        <div style="height:150px; background:rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center;">
                                            <i class="fas fa-home fa-2x opacity-50"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body p-3">
                                        <h6 class="fw-bold"><?= htmlspecialchars($sim['title']) ?></h6>
                                        <div class="text-muted small">🏦 <?= htmlspecialchars($sim['bank_name'] ?? 'Bank') ?></div>
                                        <div class="fw-bold text-warning">₹ <?= indianCurrencyFormat($sim['price']) ?></div>
                                        <div class="small opacity-75"><?= htmlspecialchars($sim['city']) ?></div>
                                        <a href="property_detail.php?id=<?= $sim['id'] ?>&source=auction" class="btn btn-sm btn-outline-light mt-2 w-100">View</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <!-- ===== END SIMILAR PROPERTIES ===== -->

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
