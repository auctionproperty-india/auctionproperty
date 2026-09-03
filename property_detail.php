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
// 2️⃣ PAID USER VIEW (Active Subscription) – WHITE + BLUE THEME
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
    /* ===== WHITE + BLUE THEME ===== */
    .wb-card {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e8edf4;
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .wb-card-header {
        background: #ffffff;
        border-bottom: 1px solid #e8edf4;
        padding: 20px 28px;
    }
    .wb-card-body {
        padding: 24px 28px 28px;
        background: #ffffff;
    }
    .wb-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.3px;
        margin: 0;
    }
    .wb-title i {
        color: #2563eb;
        margin-right: 8px;
    }
    .wb-badge {
        background: #f1f5f9;
        color: #1e293b;
        padding: 4px 14px;
        border-radius: 50px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
        letter-spacing: 0.3px;
    }
    .wb-badge i {
        margin-right: 4px;
        color: #2563eb;
    }
    .wb-badge-subscribed {
        background: #dbeafe;
        color: #1e40af;
    }
    .wb-price-tag {
        background: linear-gradient(135deg, #1e40af, #2563eb);
        color: #ffffff;
        border-radius: 14px;
        padding: 12px 20px;
        text-align: center;
        min-width: 140px;
        box-shadow: 0 4px 15px rgba(37,99,235,0.25);
    }
    .wb-price-tag .label {
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.8;
        font-weight: 600;
    }
    .wb-price-tag .amount {
        font-size: 1.6rem;
        font-weight: 800;
        line-height: 1.2;
    }
    .wb-address-box {
        background: #f0f5ff;
        border-radius: 12px;
        padding: 14px 18px;
        border-left: 4px solid #2563eb;
        font-size: 0.9rem;
        color: #1e293b;
        margin-bottom: 20px;
    }
    .wb-address-box i {
        color: #2563eb;
        margin-right: 6px;
    }
    .wb-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .wb-grid-item {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px 14px;
        border: 1px solid #e8edf4;
        transition: all 0.15s ease;
    }
    .wb-grid-item:hover {
        background: #f0f5ff;
        border-color: #b8cbe8;
    }
    .wb-grid-item .label {
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #64748b;
        font-weight: 600;
        display: block;
        margin-bottom: 2px;
    }
    .wb-grid-item .value {
        font-size: 0.95rem;
        font-weight: 600;
        color: #0f172a;
        word-break: break-word;
    }
    .wb-grid-item .value a {
        color: #2563eb;
        text-decoration: none;
        font-weight: 600;
    }
    .wb-grid-item .value a:hover {
        text-decoration: underline;
    }
    .wb-section-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: #0f172a;
        margin: 24px 0 14px 0;
        padding-bottom: 8px;
        border-bottom: 2px solid #e8edf4;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .wb-section-title i {
        color: #2563eb;
        font-size: 1rem;
    }
    .wb-image-box {
        border-radius: 14px;
        overflow: hidden;
        background: #f8fafc;
        border: 1px solid #e8edf4;
        margin-top: 12px;
    }
    .wb-image-box img {
        width: 100%;
        max-height: 400px;
        object-fit: contain;
        display: block;
        background: #ffffff;
    }
    .wb-image-box .caption {
        background: #f8fafc;
        text-align: center;
        padding: 6px;
        font-size: 0.7rem;
        color: #94a3b8;
    }
    .wb-similar-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e8edf4;
        overflow: hidden;
        transition: all 0.2s;
        height: 100%;
    }
    .wb-similar-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        border-color: #2563eb;
    }
    .wb-similar-card .body {
        padding: 14px;
    }
    .wb-similar-card .body h6 {
        font-size: 0.9rem;
        font-weight: 700;
        color: #0f172a;
    }
    .wb-similar-card .body .price {
        font-weight: 700;
        color: #2563eb;
        font-size: 0.95rem;
    }
    .btn-wb {
        background: #2563eb;
        color: #fff;
        border: none;
        padding: 6px 18px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-wb:hover {
        background: #1d4ed8;
        box-shadow: 0 4px 15px rgba(37,99,235,0.25);
        transform: translateY(-1px);
        color: #fff;
    }
    .btn-wb-outline {
        background: transparent;
        color: #2563eb;
        border: 1px solid #d1d9e6;
        padding: 6px 18px;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-wb-outline:hover {
        background: #f0f5ff;
        border-color: #2563eb;
        color: #2563eb;
    }
    @media (max-width: 768px) {
        .wb-title { font-size: 1.3rem; }
        .wb-price-tag .amount { font-size: 1.3rem; }
        .wb-grid { grid-template-columns: 1fr 1fr; }
        .wb-card-header { flex-direction: column; align-items: stretch; }
        .wb-price-tag { margin-top: 10px; }
    }
    @media (max-width: 576px) {
        .wb-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Back Button -->
            <a href="javascript:history.back()" class="btn btn-wb-outline mb-3">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>

            <!-- Main Card -->
            <div class="wb-card">

                <!-- Header -->
                <div class="wb-card-header d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h1 class="wb-title"><i class="fas fa-gavel"></i><?= htmlspecialchars($prop['title']) ?></h1>
                        <div class="mt-1">
                            <span class="wb-badge"><i class="fas fa-university"></i> <?= htmlspecialchars($prop['bank_name'] ?? ($source=='customer' ? 'Customer Property' : 'Bank Auction')) ?></span>
                            <?php if ($source == 'auction'): ?>
                                <span class="wb-badge wb-badge-subscribed ms-1"><i class="fas fa-check-circle"></i> Subscribed</span>
                            <?php else: ?>
                                <span class="wb-badge ms-1" style="background:#d1fae5; color:#065f46;"><i class="fas fa-home"></i> Customer Listed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="wb-price-tag">
                        <div class="label">Reserve Price</div>
                        <div class="amount">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                    </div>
                </div>

                <!-- Body -->
                <div class="wb-card-body">

                    <!-- Address -->
                    <?php $address = $prop['address'] ?? $prop['location'] ?? ''; ?>
                    <?php if (!empty($address)): ?>
                    <div class="wb-address-box">
                        <i class="fas fa-map-pin"></i> 
                        <strong>Address / Location:</strong> <?= nl2br(htmlspecialchars($address)) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Main Details Grid -->
                    <div class="wb-grid">
                        <div class="wb-grid-item">
                            <span class="label">Borrower Name</span>
                            <span class="value"><?= htmlspecialchars($prop['borrower_name'] ?? ($source=='customer' ? 'Customer Listed' : 'N/A')) ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Property Type</span>
                            <span class="value"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Possession</span>
                            <span class="value"><?= getPossessionValue($prop) ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Locality</span>
                            <span class="value"><?= htmlspecialchars($prop['locality'] ?? 'N/A') ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">City</span>
                            <span class="value"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">State</span>
                            <span class="value"><?= htmlspecialchars($prop['state'] ?? 'N/A') ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Area (Sq Ft)</span>
                            <span class="value"><?= number_format($prop['sqft'] ?? 0, 2) ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Price per Sq Ft</span>
                            <span class="value">₹ <?= number_format($prop['price_per_sqft'] ?? 0, 2) ?></span>
                        </div>
                    </div>

                    <!-- Financial & Auction Section -->
                    <div class="wb-section-title"><i class="fas fa-coins"></i> Financial & Auction Details</div>
                    <div class="wb-grid">
                        <div class="wb-grid-item">
                            <span class="label">EMD Amount</span>
                            <span class="value">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Bid Increment</span>
                            <span class="value">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">EMD Deadline</span>
                            <span class="value"><?= empty($prop['emd_deadline']) ? 'N/A' : date('d M Y h:i A', strtotime($prop['emd_deadline'])) ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Auction Start</span>
                            <span class="value"><?= empty($prop['auction_start_time']) ? 'N/A' : date('d M Y h:i A', strtotime($prop['auction_start_time'])) ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Auction End</span>
                            <span class="value"><?= empty($prop['auction_end_time']) ? 'N/A' : date('d M Y h:i A', strtotime($prop['auction_end_time'])) ?></span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Inspection Date</span>
                            <span class="value"><?= empty($prop['inspection_date']) ? 'N/A' : date('d M Y', strtotime($prop['inspection_date'])) ?></span>
                        </div>
                        <div class="wb-grid-item" style="grid-column: span 2;">
                            <span class="label">Auction Date</span>
                            <span class="value">
                                <?php 
                                if (!empty($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty') {
                                    echo '🔑 Private Treaty';
                                } elseif (!empty($prop['auction_date'])) {
                                    echo date('d M Y', strtotime($prop['auction_date']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="wb-grid-item">
                            <span class="label">Contact Number</span>
                            <span class="value">
                                <?php if(!empty($prop['contact_number'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $prop['contact_number']) ?>" target="_blank">
                                        <?= htmlspecialchars($prop['contact_number']) ?> <i class="fab fa-whatsapp" style="color:#25D366;"></i>
                                    </a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if(!empty($prop['google_location'])): ?>
                        <div class="wb-grid-item" style="grid-column: span 2;">
                            <span class="label">Map Location</span>
                            <span class="value">
                                <a href="<?= $prop['google_location'] ?>" target="_blank" class="btn btn-wb btn-sm">
                                    <i class="fas fa-map-marked-alt me-1"></i> View on Map
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if(!empty($prop['description'])): ?>
                    <div class="wb-section-title"><i class="fas fa-align-left"></i> Description</div>
                    <div style="background:#f8fafc; border-radius:12px; padding:14px 18px; color:#334155; font-size:0.9rem; line-height:1.7; border:1px solid #e8edf4;">
                        <?= nl2br(htmlspecialchars($prop['description'])) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Image -->
                    <div class="wb-section-title"><i class="fas fa-image"></i> Property Image</div>
                    <div class="wb-image-box">
                        <?php if(!empty($image_url)): ?>
                            <a href="<?= htmlspecialchars($image_url) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($image_url) ?>" alt="Property Image">
                            </a>
                            <div class="caption"><i class="fas fa-expand me-1"></i> Click to view full size</div>
                        <?php else: ?>
                            <div style="height:180px; display:flex; align-items:center; justify-content:center; background:#fafcfd; color:#94a3b8;">
                                <i class="fas fa-image fa-2x opacity-25"></i>
                                <span class="ms-2" style="font-size:0.9rem;">No Image Available</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Similar Properties -->
                    <?php if($source == 'auction' && count($similar_props) > 0): ?>
                    <div class="wb-section-title"><i class="fas fa-list-ul"></i> Similar Properties</div>
                    <div class="row g-3">
                        <?php foreach($similar_props as $sim): ?>
                        <div class="col-md-4">
                            <div class="wb-similar-card">
                                <?php if(!empty($sim['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($sim['image_url']) ?>" style="height:130px; width:100%; object-fit:cover;" alt="<?= htmlspecialchars($sim['title']) ?>">
                                <?php else: ?>
                                    <div style="height:130px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                        <i class="fas fa-home fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="body">
                                    <h6><?= htmlspecialchars($sim['title']) ?></h6>
                                    <div style="font-size:0.75rem; color:#64748b;">🏦 <?= htmlspecialchars($sim['bank_name'] ?? 'Bank') ?></div>
                                    <div class="price">₹ <?= indianCurrencyFormat($sim['price']) ?></div>
                                    <div style="font-size:0.75rem; color:#94a3b8;"><?= htmlspecialchars($sim['city']) ?></div>
                                    <a href="property_detail.php?id=<?= $sim['id'] ?>&source=auction" class="btn btn-wb w-100 mt-2">View</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                </div> <!-- card-body -->
            </div> <!-- card -->
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
