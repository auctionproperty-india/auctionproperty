<?php
// ============================================================
// 📄 Property Detail – Full Detail for Paid / Basic for Free
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$property_id = $_GET['id'] ?? 0;
$source      = $_GET['source'] ?? 'auction';
$user_id     = $_SESSION['user_id'];

// Log view
if ($property_id) {
    logActivity($pdo, $user_id, 'property_view', 'Property ID: ' . $property_id . ', Source: ' . $source);
}

// Fetch property
if ($source == 'auction') {
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

// Subscription check
if ($source == 'auction') {
    $has_subscription = userHasActiveSubscription($pdo, $user_id);
} else {
    $has_subscription = true; // customer properties are always visible
}

include 'header.php';

// ============================================================
// 1️⃣ FREE USER VIEW (No Subscription) – Only Basic Details
// ============================================================
if (!$has_subscription && $source == 'auction') {
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

                        <!-- Basic Details Grid (Free User) -->
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
                                    <small class="text-muted text-uppercase fw-bold">Auction Date</small>
                                    <h6 class="fw-bold mb-0">
                                        <?php 
                                        $date_display = 'N/A';
                                        if (!empty($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty') {
                                            $date_display = '🔑 Private Treaty';
                                        } elseif (!empty($prop['auction_date'])) {
                                            $date_display = date('d.m.Y', strtotime($prop['auction_date']));
                                        } elseif (!empty($prop['auction_start_time'])) {
                                            $date_display = date('d.m.Y', strtotime($prop['auction_start_time']));
                                        }
                                        echo $date_display;
                                        ?>
                                    </h6>
                                </div>
                            </div>
                            <!-- EMD Amount -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #f3e8ff; border-left: 5px solid #a855f7;">
                                    <small class="text-muted text-uppercase fw-bold">EMD Amount</small>
                                    <h6 class="fw-bold mb-0">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></h6>
                                </div>
                            </div>
                            <!-- Bid Increment -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #e0f2fe; border-left: 5px solid #0ea5e9;">
                                    <small class="text-muted text-uppercase fw-bold">Bid Increment</small>
                                    <h6 class="fw-bold mb-0">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></h6>
                                </div>
                            </div>
                            <!-- Area -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #fefce8; border-left: 5px solid #eab308;">
                                    <small class="text-muted text-uppercase fw-bold">Area (Sq Ft)</small>
                                    <h6 class="fw-bold mb-0"><?= number_format($prop['sqft'] ?? 0, 2) ?></h6>
                                </div>
                            </div>
                            <!-- Contact -->
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 shadow-sm text-center" style="background: #ecfdf5; border-left: 5px solid #10b981;">
                                    <small class="text-muted text-uppercase fw-bold">Contact</small>
                                    <h6 class="fw-bold mb-0"><?= htmlspecialchars($prop['contact_number'] ?? 'N/A') ?></h6>
                                </div>
                            </div>
                        </div>

                        <!-- Image (if any) -->
                        <?php if (!empty($prop['image_url'])): ?>
                            <div class="mt-4 text-center">
                                <img src="<?= htmlspecialchars($prop['image_url']) ?>" class="img-fluid rounded shadow" style="max-height:250px;" alt="Property Image">
                            </div>
                        <?php endif; ?>

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

// ============================================================
// 2️⃣ PAID USER VIEW (Active Subscription) – LIGHT LUXURY LOOK
// ============================================================

$image_url  = $prop['image_url'] ?? '';
$show_images = true;

// ---- Helper to get possession value ----
function getPossessionValue($prop) {
    $possession = '';
    if (isset($prop['possession']) && !empty($prop['possession'])) {
        $possession = $prop['possession'];
    } elseif (isset($prop['possession_type']) && !empty($prop['possession_type'])) {
        $possession = $prop['possession_type'];
    } elseif (isset($prop['possession_status']) && !empty($prop['possession_status'])) {
        $possession = $prop['possession_status'];
    }
    return !empty($possession) ? htmlspecialchars($possession) : 'N/A';
}

// ---- Similar Properties (only for auction) ----
$similar_props = [];
if ($source == 'auction') {
    $city = $prop['city'] ?? '';
    $price = (float)$prop['price'];
    $min_price = $price * 0.7;
    $max_price = $price * 1.3;
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

<style>
    /* Light Luxury Theme */
    .luxury-card {
        background: #ffffff;
        border-radius: 28px;
        border: none;
        box-shadow: 0 15px 50px rgba(0,0,0,0.06), 0 5px 20px rgba(0,0,0,0.02);
        overflow: hidden;
    }
    .luxury-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #eef2f6;
        padding: 28px 30px;
    }
    .luxury-card .card-body {
        padding: 30px;
    }
    .luxury-title {
        font-size: 2rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.5px;
    }
    .luxury-badge {
        background: #f1f5f9;
        color: #1e293b;
        padding: 6px 18px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 0.9rem;
        display: inline-block;
    }
    .luxury-badge i {
        color: #2563eb;
        margin-right: 6px;
    }
    .luxury-subtitle {
        color: #64748b;
        font-size: 1rem;
        margin-top: 4px;
    }
    .detail-grid-luxury {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
        margin-top: 20px;
    }
    .detail-item-luxury {
        background: #f8fafc;
        border-radius: 16px;
        padding: 16px 18px;
        border: 1px solid #eef2f6;
        transition: all 0.2s ease;
    }
    .detail-item-luxury:hover {
        background: #f1f5f9;
        border-color: #d1d9e6;
    }
    .detail-item-luxury .label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #94a3b8;
        font-weight: 600;
    }
    .detail-item-luxury .value {
        font-size: 1.1rem;
        font-weight: 600;
        color: #0f172a;
        margin-top: 4px;
    }
    .detail-item-luxury .value a {
        color: #2563eb;
        text-decoration: none;
    }
    .detail-item-luxury .value a:hover {
        text-decoration: underline;
    }
    .price-tag {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #0f172a;
        border-radius: 16px;
        padding: 16px 20px;
        text-align: center;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(251,191,36,0.25);
    }
    .price-tag .amount {
        font-size: 2rem;
        font-weight: 800;
    }
    .price-tag .label {
        font-size: 0.75rem;
        text-transform: uppercase;
        opacity: 0.7;
        font-weight: 600;
    }
    .section-title-luxury {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        margin: 30px 0 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #eef2f6;
    }
    .section-title-luxury i {
        color: #2563eb;
        margin-right: 8px;
    }
    .similar-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #eef2f6;
        transition: all 0.2s;
        overflow: hidden;
    }
    .similar-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    }
    .btn-luxury-primary {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 10px 28px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-luxury-primary:hover {
        background: #1d4ed8;
        box-shadow: 0 8px 25px rgba(37,99,235,0.25);
        transform: translateY(-2px);
    }
    .btn-luxury-outline {
        background: transparent;
        color: #2563eb;
        border: 1px solid #d1d9e6;
        padding: 10px 28px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }
    .btn-luxury-outline:hover {
        background: #f1f5f9;
        border-color: #2563eb;
    }
    .property-image-container {
        border-radius: 20px;
        overflow: hidden;
        background: #f8fafc;
        box-shadow: 0 4px 15px rgba(0,0,0,0.04);
        margin-top: 20px;
    }
    .property-image-container img {
        width: 100%;
        max-height: 450px;
        object-fit: contain;
        background: #ffffff;
    }
    @media (max-width: 768px) {
        .luxury-title { font-size: 1.5rem; }
        .detail-grid-luxury { grid-template-columns: 1fr 1fr; }
        .price-tag .amount { font-size: 1.5rem; }
    }
    @media (max-width: 576px) {
        .detail-grid-luxury { grid-template-columns: 1fr; }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Back Button -->
            <a href="javascript:history.back()" class="btn btn-luxury-outline mb-4">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>

            <!-- Main Card -->
            <div class="luxury-card">

                <!-- Header -->
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h1 class="luxury-title"><i class="fas fa-gavel me-2" style="color: #2563eb;"></i><?= htmlspecialchars($prop['title']) ?></h1>
                        <div class="luxury-subtitle">
                            <span class="luxury-badge"><i class="fas fa-university"></i> <?= htmlspecialchars($prop['bank_name'] ?? ($source=='customer' ? 'Customer Property' : 'Bank Auction')) ?></span>
                            <?php if ($source == 'auction'): ?>
                                <span class="luxury-badge ms-2" style="background: #dbeafe; color: #1e40af;"><i class="fas fa-check-circle"></i> Subscribed</span>
                            <?php else: ?>
                                <span class="luxury-badge ms-2" style="background: #d1fae5; color: #065f46;"><i class="fas fa-home"></i> Customer Listed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <div class="price-tag">
                            <div class="label">Reserve Price</div>
                            <div class="amount">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="card-body">

                    <!-- Address -->
                    <div class="p-3 rounded-4" style="background: #f8fafc; border-left: 4px solid #2563eb;">
                        <i class="fas fa-map-pin me-2" style="color: #2563eb;"></i>
                        <strong>Address / Location:</strong>
                        <?= nl2br(htmlspecialchars($prop['address'] ?? $prop['location'] ?? 'N/A')) ?>
                    </div>

                    <!-- Detail Grid -->
                    <div class="detail-grid-luxury">
                        <div class="detail-item-luxury">
                            <div class="label">Borrower Name</div>
                            <div class="value"><?= htmlspecialchars($prop['borrower_name'] ?? ($source=='customer' ? 'Customer Listed' : 'N/A')) ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Property Type</div>
                            <div class="value"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Possession</div>
                            <div class="value"><?= getPossessionValue($prop) ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Locality</div>
                            <div class="value"><?= htmlspecialchars($prop['locality'] ?? 'N/A') ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">City</div>
                            <div class="value"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">State</div>
                            <div class="value"><?= htmlspecialchars($prop['state'] ?? 'N/A') ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Area (Sq Ft)</div>
                            <div class="value"><?= number_format($prop['sqft'] ?? 0, 2) ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Price per Sq Ft</div>
                            <div class="value">₹ <?= number_format($prop['price_per_sqft'] ?? 0, 2) ?></div>
                        </div>
                    </div>

                    <!-- Financial & Auction Details -->
                    <div class="section-title-luxury"><i class="fas fa-coins"></i> Financial & Auction Details</div>
                    <div class="detail-grid-luxury">
                        <div class="detail-item-luxury">
                            <div class="label">EMD Amount</div>
                            <div class="value">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Bid Increment</div>
                            <div class="value">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">EMD Deadline</div>
                            <div class="value"><?= empty($prop['emd_deadline']) ? 'N/A' : date('d M Y h:i A', strtotime($prop['emd_deadline'])) ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Auction Start</div>
                            <div class="value"><?= empty($prop['auction_start_time']) ? 'N/A' : date('d M Y h:i A', strtotime($prop['auction_start_time'])) ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Auction End</div>
                            <div class="value"><?= empty($prop['auction_end_time']) ? 'N/A' : date('d M Y h:i A', strtotime($prop['auction_end_time'])) ?></div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Inspection Date</div>
                            <div class="value"><?= empty($prop['inspection_date']) ? 'N/A' : date('d M Y', strtotime($prop['inspection_date'])) ?></div>
                        </div>
                        <div class="detail-item-luxury" style="grid-column: span 2;">
                            <div class="label">Auction Date</div>
                            <div class="value">
                                <?php 
                                if (!empty($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty') {
                                    echo '🔑 Private Treaty';
                                } elseif (!empty($prop['auction_date'])) {
                                    echo date('d M Y', strtotime($prop['auction_date']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="detail-item-luxury">
                            <div class="label">Contact Number</div>
                            <div class="value">
                                <?php if(!empty($prop['contact_number'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $prop['contact_number']) ?>" target="_blank">
                                        <?= htmlspecialchars($prop['contact_number']) ?> <i class="fab fa-whatsapp ms-1" style="color: #25D366;"></i>
                                    </a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if(!empty($prop['google_location'])): ?>
                        <div class="detail-item-luxury" style="grid-column: span 2;">
                            <div class="label">Map Location</div>
                            <div class="value">
                                <a href="<?= $prop['google_location'] ?>" target="_blank" class="btn btn-sm btn-luxury-primary">
                                    <i class="fas fa-map-marked-alt me-1"></i> View on Google Maps
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if(!empty($prop['description'])): ?>
                    <div class="section-title-luxury"><i class="fas fa-align-left"></i> Description</div>
                    <div class="p-3 rounded-4" style="background: #f8fafc; color: #334155; line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($prop['description'])) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Image -->
                    <div class="section-title-luxury"><i class="fas fa-image"></i> Property Image</div>
                    <div class="property-image-container">
                        <?php if(!empty($image_url)): ?>
                            <a href="<?= htmlspecialchars($image_url) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($image_url) ?>" alt="Property Image">
                            </a>
                            <div class="text-center py-2" style="background: #f8fafc; font-size: 0.85rem; color: #94a3b8;">
                                <i class="fas fa-expand me-1"></i> Click to view full size
                            </div>
                        <?php else: ?>
                            <div style="height:200px; display:flex; align-items:center; justify-content:center; background: #f8fafc; color: #94a3b8;">
                                <i class="fas fa-image fa-3x opacity-25"></i>
                                <span class="ms-3">No Image Available</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Similar Properties -->
                    <?php if($source == 'auction' && count($similar_props) > 0): ?>
                    <div class="section-title-luxury"><i class="fas fa-list-ul"></i> Similar Properties</div>
                    <div class="row g-3">
                        <?php foreach($similar_props as $sim): ?>
                        <div class="col-md-4">
                            <div class="similar-card">
                                <?php if(!empty($sim['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($sim['image_url']) ?>" style="height:150px; width:100%; object-fit:cover;" alt="<?= htmlspecialchars($sim['title']) ?>">
                                <?php else: ?>
                                    <div style="height:150px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                        <i class="fas fa-home fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="p-3">
                                    <h6 class="fw-bold"><?= htmlspecialchars($sim['title']) ?></h6>
                                    <div class="text-muted small">🏦 <?= htmlspecialchars($sim['bank_name'] ?? 'Bank') ?></div>
                                    <div class="fw-bold text-primary">₹ <?= indianCurrencyFormat($sim['price']) ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($sim['city']) ?></div>
                                    <a href="property_detail.php?id=<?= $sim['id'] ?>&source=auction" class="btn btn-sm btn-luxury-primary w-100 mt-2">View</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                </div> <!-- card-body -->
            </div> <!-- luxury-card -->
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
