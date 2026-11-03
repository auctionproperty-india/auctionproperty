<?php
// ============================================================
// 🏠 Admin/Sales - View Property Details (Popup Window)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🔥 FIX: Allow Admin AND Sales
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Access Denied. Please login as Admin or Sales.</h3>");
}

$prop_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$prop_id) {
    die("<h3 style='text-align:center; margin-top:50px;'>Invalid Property ID.</h3>");
}

// प्रॉपर्टी फेच करें
$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$prop_id]);
$prop = $stmt->fetch();

if (!$prop) {
    die("<h3 style='text-align:center; margin-top:50px;'>Property Not Found!</h3>");
}

// मुख्य इमेज का URL
$img_url = !empty($prop['image_url']) ? htmlspecialchars($prop['image_url']) : 'assets/img/no-image.png'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property #<?= $prop_id ?> Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fc; font-family: 'Inter', sans-serif; padding: 20px; }
        .prop-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; }
        .prop-img { width: 100%; height: 250px; object-fit: cover; }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 2px; }
        .detail-value { font-size: 1rem; color: #1e293b; font-weight: 600; margin-bottom: 12px; }
        .price-badge { background: #d1fae5; color: #065f46; padding: 5px 15px; border-radius: 20px; font-size: 1.1rem; font-weight: 800; display: inline-block; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="container" style="max-width: 800px;">
        
        <!-- हेडर -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-building me-2"></i> Property Details</h4>
            <button onclick="window.close()" class="btn btn-sm btn-danger rounded-pill px-3"><i class="fas fa-times"></i> Close</button>
        </div>

        <div class="card prop-card">
            <!-- इमेज -->
            <img src="<?= $img_url ?>" class="prop-img" alt="Property Image">
            
            <div class="card-body p-4">
                <!-- टाइटल और कीमत -->
                <h3 class="fw-bold mb-2"><?= htmlspecialchars($prop['title'] ?? 'No Title') ?></h3>
                <div class="price-badge"><i class="fas fa-tag me-1"></i> ₹ <?= number_format($prop['price'] ?? 0) ?></div>

                <!-- ग्रिड डिटेल्स -->
                <div class="row g-3 mt-2">
                    <div class="col-md-4 col-6">
                        <div class="detail-label">Property ID</div>
                        <div class="detail-value">#<?= $prop['id'] ?></div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="detail-label">Property Type</div>
                        <div class="detail-value"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="detail-label">Status</div>
                        <div class="detail-value"><span class="badge bg-success"><?= htmlspecialchars($prop['status'] ?? 'Available') ?></span></div>
                    </div>

                    <div class="col-md-4 col-6">
                        <div class="detail-label">City</div>
                        <div class="detail-value"><?= htmlspecialchars($prop['city'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="detail-label">Locality</div>
                        <div class="detail-value"><?= htmlspecialchars($prop['locality'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="detail-label">State</div>
                        <div class="detail-value"><?= htmlspecialchars($prop['state'] ?? 'N/A') ?></div>
                    </div>

                    <div class="col-md-4 col-6">
                        <div class="detail-label">Bank Name</div>
                        <div class="detail-value"><?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="detail-label">Area (Sq Ft)</div>
                        <div class="detail-value"><?= htmlspecialchars($prop['sqft'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="detail-label">Auction Date</div>
                        <div class="detail-value"><?= !empty($prop['auction_date']) ? date('d M Y', strtotime($prop['auction_date'])) : 'N/A' ?></div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- डिस्क्रिप्शन -->
                <div class="detail-label">Description</div>
                <p class="text-muted" style="font-size: 0.9rem;"><?= nl2br(htmlspecialchars($prop['description'] ?? 'No description available.')) ?></p>

                <!-- एडमिन एक्शन बटन -->
                <?php if ($_SESSION['role'] == 'admin'): ?>
                <div class="text-end mt-4 border-top pt-3">
                    <a href="properties.php?edit=<?= $prop['id'] ?>" target="_blank" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-edit me-1"></i> Edit This Property
                    </a>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
