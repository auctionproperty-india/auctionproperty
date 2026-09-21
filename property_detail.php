<?php
// ============================================================
// 📄 Property Detail – Full Detail for Paid / Basic for Free
// (Updated: High-Tech Blur Effect for Free Users)
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
// 1️⃣ FREE USER VIEW (No Subscription) – High-Tech Blur Lock
// ============================================================
if (!$has_subscription && $source == 'auction') {
    ?>
    <style>
        /* 🔥 High-Tech Blur Effect Styles */
        .blurred-content {
            filter: blur(6px);
            user-select: none;
            pointer-events: none;
            opacity: 0.7;
        }
        .lock-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(180deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.9) 100%);
            z-index: 10;
            text-align: center;
            border-radius: 16px;
            padding: 20px;
        }
        .lock-icon {
            font-size: 3rem;
            color: #f59e0b;
            margin-bottom: 15px;
            animation: pulseLock 2s infinite;
        }
        @keyframes pulseLock {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .premium-btn {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            color: #fff;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 700;
            box-shadow: 0 8px 25px rgba(37,99,235,0.4);
            transition: all 0.3s;
        }
        .premium-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(37,99,235,0.6);
            color: #fff;
        }
    </style>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                
                <!-- Back Button -->
                <a href="javascript:history.back()" class="btn btn-outline-secondary rounded-pill mb-3">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>

                <div class="card border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
                    <!-- Header -->
                    <div class="card-header text-white text-center p-4" style="background: linear-gradient(135deg, #1e293b, #3b82f6);">
                        <h3><i class="fas fa-lock me-2"></i> Premium Property Details</h3>
                        <p class="mb-0 opacity-75">Unlock full details by subscribing to a package</p>
                    </div>

                    <div class="card-body p-4" style="background: #f8fafc;">
                        <div class="text-center mb-4">
                            <h4 class="fw-bold"><?= htmlspecialchars($prop['title']) ?></h4>
                            <span class="badge bg-primary px-3 py-2 rounded-pill">Bank Auction</span>
                        </div>

                        <!-- Visible Basic Info -->
                        <div class="row g-3 mb-4">
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
                                        }
                                        echo $date_display;
                                        ?>
                                    </h6>
                                </div>
                            </div>
                        </div>

                        <!-- 🔥 Locked / Blurred Section -->
                        <div class="position-relative">
                            <!-- Overlay -->
                            <div class="lock-overlay">
                                <i class="fas fa-crown lock-icon"></i>
                                <h4 class="fw-bold text-dark mb-2">Unlock Full Property Details</h4>
                                <p class="text-muted mb-4 small">Get access to Area (Sq Ft), EMD, Contact Info, Address & more.</p>
                                <a href="user_packages.php" class="btn premium-btn">
                                    <i class="fas fa-rocket me-2"></i> View Packages to Buy
                                </a>
                            </div>
                            
                            <!-- Blurred Grid -->
                            <div class="row g-3 blurred-content">
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4 shadow-sm text-center" style="background: #f3e8ff; border-left: 5px solid #a855f7;">
                                        <small class="text-muted text-uppercase fw-bold">EMD Amount</small>
                                        <h6 class="fw-bold mb-0">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></h6>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4 shadow-sm text-center" style="background: #e0f2fe; border-left: 5px solid #0ea5e9;">
                                        <small class="text-muted text-uppercase fw-bold">Bid Increment</small>
                                        <h6 class="fw-bold mb-0">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></h6>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4 shadow-sm text-center" style="background: #fefce8; border-left: 5px solid #eab308;">
                                        <small class="text-muted text-uppercase fw-bold">Area (Sq Ft)</small>
                                        <h6 class="fw-bold mb-0"><?= number_format($prop['sqft'] ?? 0, 2) ?></h6>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-4 shadow-sm text-center" style="background: #ecfdf5; border-left: 5px solid #10b981;">
                                        <small class="text-muted text-uppercase fw-bold">Contact</small>
                                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($prop['contact_number'] ?? 'N/A') ?></h6>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="p-3 rounded-4 shadow-sm" style="background: #f1f5f9; border-left: 5px solid #64748b;">
                                        <small class="text-muted text-uppercase fw-bold">Address / Location</small>
                                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($prop['address'] ?? $prop['location'] ?? 'Address details hidden') ?></h6>
                                    </div>
                                </div>
                            </div>
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
// 2️⃣ PAID USER VIEW – SUPER BOLD + STYLISH (Unchanged)
// ============================================================

$image_url  = $prop['image_url'] ?? '';
$show_images = true;

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

function getAuctionDateDisplay($dateStr, $showTime = true) {
    if (empty($dateStr) || $dateStr == '0000-00-00' || $dateStr == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    if (trim($dateStr) === 'Private Treaty') {
        return '🔑 Private Treaty';
    }
    $timestamp = strtotime($dateStr);
    if ($timestamp === false || $timestamp <= 0) {
        return 'N/A';
    }
    return $showTime ? date('d M Y h:i A', $timestamp) : date('d M Y', $timestamp);
}

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
    /* ===== SUPER BOLD + STYLISH THEME ===== */
    .sb-card {
        background: #ffffff;
        border-radius: 24px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08), 0 2px 15px rgba(0,0,0,0.03);
        overflow: hidden;
        transition: all 0.3s ease;
    }
    .sb-card:hover {
        box-shadow: 0 20px 60px rgba(0,0,0,0.10);
    }
    .sb-card-header {
        background: linear-gradient(135deg, #f8faff, #ffffff);
        border-bottom: 2px solid #e8edf4;
        padding: 24px 32px;
    }
    .sb-card-body {
        padding: 28px 32px 32px;
        background: #ffffff;
    }
    .sb-title {
        font-size: 1.8rem;
        font-weight: 800;
        color: #0a0f1a;
        letter-spacing: -0.5px;
        margin: 0;
    }
    .sb-title i {
        color: #2563eb;
        margin-right: 10px;
    }
    .sb-badge {
        background: #f1f5f9;
        color: #1e293b;
        padding: 6px 18px;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-block;
        letter-spacing: 0.4px;
        border: 1px solid #e8edf4;
    }
    .sb-badge i {
        margin-right: 6px;
        color: #2563eb;
    }
    .sb-badge-subscribed {
        background: #dbeafe;
        color: #1e40af;
        border-color: #93b5e8;
    }
    .sb-price-tag {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #0a0f1a;
        border-radius: 16px;
        padding: 14px 24px;
        text-align: center;
        min-width: 160px;
        box-shadow: 0 6px 25px rgba(251,191,36,0.35);
        border: 2px solid rgba(255,255,255,0.3);
    }
    .sb-price-tag .label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        opacity: 0.7;
        font-weight: 700;
    }
    .sb-price-tag .amount {
        font-size: 1.8rem;
        font-weight: 900;
        line-height: 1.2;
    }
    .sb-address-box {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border-radius: 14px;
        padding: 16px 20px;
        border-left: 6px solid #2563eb;
        font-size: 1rem;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 24px;
        border: 1px solid #bfdbfe;
    }
    .sb-address-box i {
        color: #2563eb;
        margin-right: 8px;
        font-size: 1.1rem;
    }
    .sb-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }
    .sb-grid-item {
        background: #f8fafc;
        border-radius: 14px;
        padding: 14px 16px;
        border: 2px solid #e8edf4;
        transition: all 0.2s ease;
    }
    .sb-grid-item:hover {
        background: #eff6ff;
        border-color: #93b5e8;
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(37,99,235,0.08);
    }
    .sb-grid-item .label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #64748b;
        font-weight: 700;
        display: block;
        margin-bottom: 4px;
    }
    .sb-grid-item .value {
        font-size: 1.05rem;
        font-weight: 800;
        color: #0a0f1a;
        word-break: break-word;
    }
    .sb-grid-item .value a {
        color: #2563eb;
        text-decoration: none;
        font-weight: 700;
    }
    .sb-grid-item .value a:hover {
        text-decoration: underline;
        color: #1d4ed8;
    }
    .sb-section-title {
        font-size: 1.1rem;
        font-weight: 800;
        color: #0a0f1a;
        margin: 28px 0 16px 0;
        padding-bottom: 10px;
        border-bottom: 3px solid #2563eb;
        display: flex;
        align-items: center;
        gap: 10px;
        letter-spacing: -0.3px;
    }
    .sb-section-title i {
        color: #2563eb;
        font-size: 1.2rem;
        background: #eff6ff;
        padding: 8px;
        border-radius: 10px;
    }
    .sb-image-box {
        border-radius: 16px;
        overflow: hidden;
        background: #f8fafc;
        border: 2px solid #e8edf4;
        margin-top: 14px;
    }
    .sb-image-box img {
        width: 100%;
        max-height: 450px;
        object-fit: contain;
        display: block;
        background: #ffffff;
    }
    .sb-image-box .caption {
        background: #f8fafc;
        text-align: center;
        padding: 8px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        border-top: 1px solid #e8edf4;
    }
    .sb-similar-card {
        background: #ffffff;
        border-radius: 16px;
        border: 2px solid #e8edf4;
        overflow: hidden;
        transition: all 0.25s ease;
        height: 100%;
    }
    .sb-similar-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.08);
        border-color: #2563eb;
    }
    .sb-similar-card .body {
        padding: 16px;
    }
    .sb-similar-card .body h6 {
        font-size: 0.95rem;
        font-weight: 800;
        color: #0a0f1a;
    }
    .sb-similar-card .body .price {
        font-weight: 800;
        color: #2563eb;
        font-size: 1rem;
    }
    .btn-sb {
        background: linear-gradient(135deg, #1e40af, #2563eb);
        color: #fff;
        border: none;
        padding: 8px 22px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.25s ease;
        box-shadow: 0 4px 15px rgba(37,99,235,0.2);
    }
    .btn-sb:hover {
        background: linear-gradient(135deg, #1d4ed8, #3b82f6);
        box-shadow: 0 8px 25px rgba(37,99,235,0.35);
        transform: translateY(-2px);
        color: #fff;
    }
    .btn-sb-outline {
        background: transparent;
        color: #2563eb;
        border: 2px solid #d1d9e6;
        padding: 8px 22px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 700;
        transition: all 0.25s ease;
    }
    .btn-sb-outline:hover {
        background: #eff6ff;
        border-color: #2563eb;
        color: #2563eb;
        transform: translateY(-2px);
    }
    .sb-description-box {
        background: #f8fafc;
        border-radius: 14px;
        padding: 16px 20px;
        color: #0f172a;
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.8;
        border: 2px solid #e8edf4;
    }
    @media (max-width: 768px) {
        .sb-title { font-size: 1.4rem; }
        .sb-price-tag .amount { font-size: 1.4rem; }
        .sb-price-tag { min-width: 120px; padding: 10px 16px; }
        .sb-grid { grid-template-columns: 1fr 1fr; }
        .sb-card-header { flex-direction: column; align-items: stretch; padding: 18px 20px; }
        .sb-price-tag { margin-top: 12px; }
        .sb-card-body { padding: 18px 18px 22px; }
    }
    @media (max-width: 576px) {
        .sb-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <!-- Back Button -->
            <a href="javascript:history.back()" class="btn btn-sb-outline mb-3">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>

            <!-- Main Card -->
            <div class="sb-card">

                <!-- Header -->
                <div class="sb-card-header d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h1 class="sb-title"><i class="fas fa-gavel"></i><?= htmlspecialchars($prop['title']) ?></h1>
                        <div class="mt-2">
                            <span class="sb-badge"><i class="fas fa-university"></i> <?= htmlspecialchars($prop['bank_name'] ?? ($source=='customer' ? 'Customer Property' : 'Bank Auction')) ?></span>
                            <?php if ($source == 'auction'): ?>
                                <span class="sb-badge sb-badge-subscribed ms-1"><i class="fas fa-check-circle"></i> Subscribed</span>
                            <?php else: ?>
                                <span class="sb-badge ms-1" style="background:#d1fae5; color:#065f46; border-color:#6ee7b7;"><i class="fas fa-home"></i> Customer Listed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="sb-price-tag">
                        <div class="label">Reserve Price</div>
                        <div class="amount">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                    </div>
                </div>

                <!-- Body -->
                <div class="sb-card-body">

                    <!-- Address -->
                    <?php $address = $prop['address'] ?? $prop['location'] ?? ''; ?>
                    <?php if (!empty($address)): ?>
                    <div class="sb-address-box">
                        <i class="fas fa-map-pin"></i> 
                        <strong>Address / Location:</strong> <?= nl2br(htmlspecialchars($address)) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Main Details Grid -->
                    <div class="sb-grid">
                        <div class="sb-grid-item">
                            <span class="label">Borrower Name</span>
                            <span class="value"><?= htmlspecialchars($prop['borrower_name'] ?? ($source=='customer' ? 'Customer Listed' : 'N/A')) ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Property Type</span>
                            <span class="value"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Possession</span>
                            <span class="value"><?= getPossessionValue($prop) ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Locality</span>
                            <span class="value"><?= htmlspecialchars($prop['locality'] ?? 'N/A') ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">City</span>
                            <span class="value"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">State</span>
                            <span class="value"><?= htmlspecialchars($prop['state'] ?? 'N/A') ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Area (Sq Ft)</span>
                            <span class="value"><?= number_format($prop['sqft'] ?? 0, 2) ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Price per Sq Ft</span>
                            <span class="value">₹ <?= number_format($prop['price_per_sqft'] ?? 0, 2) ?></span>
                        </div>
                    </div>

                    <!-- Financial & Auction Section -->
                    <div class="sb-section-title"><i class="fas fa-coins"></i> Financial & Auction Details</div>
                    <div class="sb-grid">
                        <div class="sb-grid-item">
                            <span class="label">EMD Amount</span>
                            <span class="value">₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Bid Increment</span>
                            <span class="value">₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">EMD Deadline</span>
                            <span class="value"><?= getAuctionDateDisplay($prop['emd_deadline']) ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Auction Start</span>
                            <span class="value"><?= getAuctionDateDisplay($prop['auction_start_time']) ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Auction End</span>
                            <span class="value"><?= getAuctionDateDisplay($prop['auction_end_time']) ?></span>
                        </div>
                        <div class="sb-grid-item">
                            <span class="label">Inspection Date</span>
                            <span class="value"><?= getAuctionDateDisplay($prop['inspection_date'], false) ?></span>
                        </div>
                        <div class="sb-grid-item" style="grid-column: span 2;">
                            <span class="label">Auction Date</span>
                            <span class="value">
                                <?= getAuctionDateDisplay($prop['auction_date'] ?? $prop['auction_start_time'], false) ?>
                            </span>
                        </div>
                        <div class="sb-grid-item">
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
                        <div class="sb-grid-item" style="grid-column: span 2;">
                            <span class="label">Map Location</span>
                            <span class="value">
                                <a href="<?= $prop['google_location'] ?>" target="_blank" class="btn btn-sb btn-sm">
                                    <i class="fas fa-map-marked-alt me-1"></i> View on Map
                                </a>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if(!empty($prop['description'])): ?>
                    <div class="sb-section-title"><i class="fas fa-align-left"></i> Description</div>
                    <div class="sb-description-box">
                        <?= nl2br(htmlspecialchars($prop['description'])) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Image -->
                    <div class="sb-section-title"><i class="fas fa-image"></i> Property Image</div>
                    <div class="sb-image-box">
                        <?php if(!empty($image_url)): ?>
                            <a href="<?= htmlspecialchars($image_url) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($image_url) ?>" alt="Property Image">
                            </a>
                            <div class="caption"><i class="fas fa-expand me-1"></i> Click to view full size</div>
                        <?php else: ?>
                            <div style="height:200px; display:flex; align-items:center; justify-content:center; background:#fafcfd; color:#94a3b8; font-weight:700;">
                                <i class="fas fa-image fa-2x opacity-25 me-2"></i>
                                <span style="font-size:1rem;">No Image Available</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Similar Properties -->
                    <?php if($source == 'auction' && count($similar_props) > 0): ?>
                    <div class="sb-section-title"><i class="fas fa-list-ul"></i> Similar Properties</div>
                    <div class="row g-3">
                        <?php foreach($similar_props as $sim): ?>
                        <div class="col-md-4">
                            <div class="sb-similar-card">
                                <?php if(!empty($sim['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($sim['image_url']) ?>" style="height:140px; width:100%; object-fit:cover;" alt="<?= htmlspecialchars($sim['title']) ?>">
                                <?php else: ?>
                                    <div style="height:140px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                        <i class="fas fa-home fa-2x"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="body">
                                    <h6><?= htmlspecialchars($sim['title']) ?></h6>
                                    <div style="font-size:0.8rem; font-weight:600; color:#64748b;">🏦 <?= htmlspecialchars($sim['bank_name'] ?? 'Bank') ?></div>
                                    <div class="price">₹ <?= indianCurrencyFormat($sim['price']) ?></div>
                                    <div style="font-size:0.8rem; font-weight:600; color:#94a3b8;"><?= htmlspecialchars($sim['city']) ?></div>
                                    <a href="property_detail.php?id=<?= $sim['id'] ?>&source=auction" class="btn btn-sb w-100 mt-2">View</a>
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
