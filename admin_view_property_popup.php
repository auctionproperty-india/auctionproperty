<?php
// ============================================================
// 🏠 Admin/Sales - View Property Details (With WhatsApp & Poster)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Access Denied. Please login.</h3>");
}

$prop_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

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

// यूजर फेच करें (अगर user_id पास हुआ है)
$user_info = null;
if ($user_id) {
    $u_stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $u_stmt->execute([$user_id]);
    $user_info = $u_stmt->fetch();
}

// मुख्य इमेज का URL
$img_url = !empty($prop['image_url']) ? htmlspecialchars($prop['image_url']) : 'https://via.placeholder.com/800x400?text=No+Image';

// 🔥 WhatsApp मैसेज तैयार करें
$wa_link = '';
$email_link = '';
if ($user_info && !empty($user_info['phone'])) {
    $clean_phone = preg_replace('/[^0-9]/', '', $user_info['phone']);
    if (strlen($clean_phone) == 10) $clean_phone = '91' . $clean_phone;
    
    $wa_message = "Hello " . $user_info['name'] . ",\n\n";
    $wa_message .= "मैंने देखा कि आपने हमारी वेबसाइट पर यह प्रॉपर्टी देखी थी:\n\n";
    $wa_message .= "🏠 *" . $prop['title'] . "*\n";
    $wa_message .= "📍 Location: " . $prop['city'] . ", " . $prop['locality'] . "\n";
    $wa_message .= "💰 Price: ₹" . number_format($prop['price']) . "\n";
    $wa_message .= "🏦 Bank: " . $prop['bank_name'] . "\n\n";
    $wa_message .= "क्या आप अभी भी इसमें इंटरेस्टेड हैं? अगर हाँ, तो मैं आपको और डिटेल्स भेज सकता हूँ या आपकी कॉल का इंतज़ार कर रहा हूँ।\n\n";
    $wa_message .= "Regards,\nPrime Property Team";
    
    $wa_link = "https://wa.me/" . $clean_phone . "?text=" . urlencode($wa_message);
}

if ($user_info && !empty($user_info['email'])) {
    $subject = "Regarding Property: " . $prop['title'];
    $body = "Hello " . $user_info['name'] . ",\n\nI noticed you viewed this property on our website. Let me know if you need more details.\n\nProperty: " . $prop['title'] . "\nPrice: ₹" . number_format($prop['price']) . "\n\nRegards,\nPrime Property Team";
    $email_link = "mailto:" . $user_info['email'] . "?subject=" . urlencode($subject) . "&body=" . urlencode($body);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property #<?= $prop_id ?> Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <style>
        body { background: #f4f7fc; font-family: 'Inter', sans-serif; padding: 20px; }
        .prop-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; }
        .prop-img { width: 100%; height: 300px; object-fit: cover; }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 2px; }
        .detail-value { font-size: 1rem; color: #1e293b; font-weight: 600; margin-bottom: 12px; }
        .price-badge { background: #d1fae5; color: #065f46; padding: 5px 15px; border-radius: 20px; font-size: 1.2rem; font-weight: 800; display: inline-block; margin-bottom: 15px; }
        
        /* HIDE BUTTONS IN POSTER */
        .no-print { display: block; }
        .watermark { text-align: center; font-size: 0.8rem; color: #94a3b8; margin-top: 15px; }
    </style>
</head>
<body>

    <div class="container" style="max-width: 800px;">
        
        <!-- हेडर -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-building me-2"></i> Property Details</h4>
            <button onclick="window.close()" class="btn btn-sm btn-danger rounded-pill px-3"><i class="fas fa-times"></i> Close</button>
        </div>

        <!-- 🔥 ACTION BUTTONS (Only if User Info Exists) -->
        <?php if($user_info): ?>
        <div class="card border-0 shadow-sm mb-3 p-3 no-print bg-light">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-user-circle text-primary"></i> Client: <?= htmlspecialchars($user_info['name']) ?></h6>
                    <small class="text-muted"><?= htmlspecialchars($user_info['phone']) ?> | <?= htmlspecialchars($user_info['email']) ?></small>
                </div>
                <div class="d-flex gap-2">
                    <?php if($wa_link): ?>
                        <a href="<?= $wa_link ?>" target="_blank" class="btn btn-success btn-sm rounded-pill px-3">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                    <?php endif; ?>
                    <?php if($email_link): ?>
                        <a href="<?= $email_link ?>" class="btn btn-primary btn-sm rounded-pill px-3">
                            <i class="fas fa-envelope"></i> Email
                        </a>
                    <?php endif; ?>
                    <button onclick="downloadPoster()" class="btn btn-warning btn-sm rounded-pill px-3">
                        <i class="fas fa-camera"></i> Download Poster
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 🔥 PROPERTY CARD (This will be downloaded as image) -->
        <div class="card prop-card" id="poster-area">
            <img src="<?= $img_url ?>" class="prop-img" alt="Property Image" crossorigin="anonymous">
            
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($prop['title'] ?? 'No Title') ?></h3>
                        <p class="text-muted mb-2"><i class="fas fa-map-marker-alt text-danger"></i> <?= htmlspecialchars($prop['location'] ?? $prop['city'] ?? '') ?></p>
                    </div>
                    <div class="price-badge"><i class="fas fa-tag me-1"></i> ₹ <?= number_format($prop['price'] ?? 0) ?></div>
                </div>

                <hr>

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

                <!-- Watermark (Shows in downloaded image) -->
                <div class="watermark mt-4 pt-3 border-top">
                    <b>Prime Property India</b> | www.primepropertyindia.onrender.com
                </div>
            </div>
        </div>

    </div>

    <!-- 🔥 POSTER DOWNLOAD SCRIPT -->
    <script>
    function downloadPoster() {
        const posterArea = document.getElementById('poster-area');
        const btns = document.querySelector('.no-print');
        
        // Hide buttons temporarily for screenshot
        btns.style.display = 'none';

        html2canvas(posterArea, {
            scale: 2, // High resolution
            useCORS: true, // For external images
            backgroundColor: "#ffffff"
        }).then(canvas => {
            let link = document.createElement('a');
            link.download = 'Property_<?= $prop_id ?>_PrimeProperty.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            
            // Show buttons again
            btns.style.display = 'block';
        }).catch(err => {
            alert("Poster download failed. " + err);
            btns.style.display = 'block';
        });
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
