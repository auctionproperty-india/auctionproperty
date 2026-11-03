<?php
// ============================================================
// 🏠 Admin/Sales - Premium Property Poster & WhatsApp
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'sales'])) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Access Denied. Please login.</h3>");
}

$is_admin = ($_SESSION['role'] == 'admin');
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

// यूजर फेच करें
$user_info = null;
if ($user_id) {
    $u_stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $u_stmt->execute([$user_id]);
    $user_info = $u_stmt->fetch();
}

// मुख्य इमेज (CORS safe)
$img_url = !empty($prop['image_url']) ? htmlspecialchars($prop['image_url']) : 'https://via.placeholder.com/1080x600/1e293b/fbbf24?text=Prime+Property+India';

// 🔥 WhatsApp मैसेज तैयार करें (Professional Greeting + Area)
$wa_phone = '';
$wa_message = '';
$email_link = '';

if ($user_info && !empty($user_info['phone'])) {
    $clean_phone = preg_replace('/[^0-9]/', '', $user_info['phone']);
    if (strlen($clean_phone) == 10) $clean_phone = '91' . $clean_phone;
    $wa_phone = $clean_phone;
    
    $client_name = !empty($user_info['name']) ? $user_info['name'] : 'Valuable Client';
    $sqft_display = !empty($prop['sqft']) ? $prop['sqft'] . ' Sq Ft' : 'N/A';
    
    // 🔥 Luxury & Professional Format
    $wa_message = "👑 *PRIME PROPERTY INDIA* 👑\n";
    $wa_message .= "_Your Trusted Auction Partner_\n\n";
    $wa_message .= "Namaste *" . $client_name . "* 👋,\n\n";
    $wa_message .= "We noticed you recently showed interest in this premium property on our website. We are here to assist you in securing your dream investment!\n\n";
    $wa_message .= "🏢 *Property:* " . $prop['title'] . "\n";
    $wa_message .= "📍 *Location:* " . $prop['city'] . ", " . $prop['state'] . "\n";
    $wa_message .= "📏 *Area:* " . $sqft_display . "\n"; // 🔥 Added Area
    $wa_message .= "💰 *Reserve Price:* ₹" . number_format($prop['price']) . "\n";
    $wa_message .= "🏦 *Bank:* " . $prop['bank_name'] . "\n";
    $wa_message .= "📅 *Auction Date:* " . (!empty($prop['auction_date']) ? date('d M Y', strtotime($prop['auction_date'])) : 'TBA') . "\n\n";
    $wa_message .= "Let's take the next step. If you would like to know more details or schedule a site visit, simply reply to this message or give us a call.\n\n";
    $wa_message .= "Have a great day! ✨\n";
    $wa_message .= "📞 *Prime Property Team*";
}

if ($user_info && !empty($user_info['email'])) {
    $subject = "Exclusive Opportunity: " . $prop['title'];
    $body = "Namaste " . (!empty($user_info['name']) ? $user_info['name'] : 'Valuable Client') . ",\n\nI noticed you viewed this premium property. Let me know if you need more details.\n\nProperty: " . $prop['title'] . "\nLocation: " . $prop['city'] . "\nPrice: ₹" . number_format($prop['price']) . "\n\nRegards,\nPrime Property Team";
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;800;900&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    
    <style>
        body { background: #f4f7fc; font-family: 'Poppins', sans-serif; padding: 20px; }
        .prop-card { background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: none; }
        .prop-img { width: 100%; height: 350px; object-fit: cover; }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 2px; }
        .detail-value { font-size: 1rem; color: #1e293b; font-weight: 600; margin-bottom: 12px; }
        .price-badge { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 8px 20px; border-radius: 30px; font-size: 1.4rem; font-weight: 800; display: inline-block; margin-bottom: 15px; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
        .no-print { display: block; }
    </style>
</head>
<body>

    <div class="container" style="max-width: 900px;">
        
        <!-- हेडर -->
        <div class="d-flex justify-content-between align-items-center mb-3 no-print">
            <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-building me-2"></i> Property Details</h4>
            <button onclick="window.close()" class="btn btn-sm btn-danger rounded-pill px-3"><i class="fas fa-times"></i> Close</button>
        </div>

        <!-- 🔥 ACTION BUTTONS -->
        <?php if($user_info): ?>
        <div class="card border-0 shadow-sm mb-3 p-3 no-print bg-light">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-user-circle text-primary"></i> Client: <?= htmlspecialchars($user_info['name']) ?></h6>
                    <small class="text-muted"><?= htmlspecialchars($user_info['phone']) ?> | <?= htmlspecialchars($user_info['email']) ?></small>
                </div>
                <div class="d-flex gap-2">
                    <?php if($wa_phone): ?>
                        <!-- 🔥 Hidden Data for JS -->
                        <div id="wa-data" data-phone="<?= $wa_phone ?>" data-msg="<?= htmlspecialchars($wa_message) ?>" style="display:none;"></div>
                        <button onclick="openWhatsApp()" class="btn btn-success btn-sm rounded-pill px-3">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </button>
                    <?php endif; ?>
                    <?php if($email_link): ?>
                        <a href="<?= $email_link ?>" class="btn btn-primary btn-sm rounded-pill px-3">
                            <i class="fas fa-envelope"></i> Email
                        </a>
                    <?php endif; ?>
                    <button onclick="downloadPoster()" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold">
                        <i class="fas fa-camera"></i> Download Banner
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 🔥 PROPERTY CARD (Visible on Screen) -->
        <div class="card prop-card">
            <img src="<?= $img_url ?>" class="prop-img" alt="Property Image" crossorigin="anonymous">
            
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h3 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($prop['title'] ?? 'No Title') ?></h3>
                        <p class="text-muted mb-2"><i class="fas fa-map-marker-alt text-danger"></i> <?= htmlspecialchars($prop['city'] ?? '') ?>, <?= htmlspecialchars($prop['state'] ?? '') ?></p>
                    </div>
                    <div class="price-badge"><i class="fas fa-tag me-1"></i> ₹ <?= number_format($prop['price'] ?? 0) ?></div>
                </div>

                <hr>

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
                        <div class="detail-label">State</div>
                        <div class="detail-value"><?= htmlspecialchars($prop['state'] ?? 'N/A') ?></div>
                    </div>
                    <div class="col-md-4 col-6">
                        <div class="detail-label">Bank Name</div>
                        <div class="detail-value"><?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></div>
                    </div>
                    <!-- 🔥 Area Added here in on-screen view too -->
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
                <?php if($is_admin): ?>
                    <div class="detail-label">Description (Admin Only)</div>
                    <p class="text-muted" style="font-size: 0.9rem;"><?= nl2br(htmlspecialchars($prop['description'] ?? 'No description available.')) ?></p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ============================================================ -->
    <!-- 🔥 HIDDEN PREMIUM POSTER TEMPLATE (For Download) -->
    <!-- ============================================================ -->
    <div id="poster-template" style="position: absolute; left: -9999px; top: 0; width: 1080px; height: 1350px; background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); font-family: 'Poppins', sans-serif; padding: 40px; box-sizing: border-box; display: flex; flex-direction: column; color: #fff;">
        
        <!-- Top Border Design -->
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 10px; background: linear-gradient(90deg, #fbbf24, #f59e0b, #fbbf24);"></div>

        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
            <div style="text-align: left;">
                <h1 style="margin: 0; font-family: 'Montserrat', sans-serif; font-size: 48px; font-weight: 900; color: #fbbf24; letter-spacing: 1px; line-height: 1;">PRIME PROPERTY</h1>
                <h2 style="margin: 5px 0 0 0; font-size: 24px; font-weight: 700; color: #fff; letter-spacing: 2px;">INDIA</h2>
                <p style="margin: 5px 0 0 0; font-size: 14px; color: #94a3b8; text-transform: uppercase;">Your Trusted Auction Partner</p>
            </div>
            <div style="background: #fbbf24; color: #0f172a; padding: 10px 25px; border-radius: 50px; font-family: 'Montserrat', sans-serif; font-size: 28px; font-weight: 900; text-transform: uppercase; box-shadow: 0 4px 15px rgba(251,191,36,0.4);">
                Bank Auction
            </div>
        </div>

        <!-- Property Image -->
        <div style="width: 100%; height: 450px; border-radius: 20px; overflow: hidden; border: 4px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.3); margin-bottom: 30px; background: #1e293b;">
            <img src="<?= $img_url ?>" style="width: 100%; height: 100%; object-fit: cover;" crossorigin="anonymous">
        </div>

        <!-- Title & Location -->
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="margin: 0; font-family: 'Montserrat', sans-serif; font-size: 50px; font-weight: 800; color: #fff; text-transform: uppercase; text-shadow: 0 2px 10px rgba(0,0,0,0.5);">
                <?= htmlspecialchars($prop['title'] ?? 'Property') ?>
            </h2>
            <p style="margin: 10px 0 0 0; font-size: 28px; color: #cbd5e1; font-weight: 500;">
                <i class="fas fa-map-marker-alt" style="color: #fbbf24; margin-right: 10px;"></i> 
                <?= htmlspecialchars($prop['city'] ?? '') ?>, <?= htmlspecialchars($prop['state'] ?? '') ?>
            </p>
        </div>

        <!-- Price Banner -->
        <div style="background: linear-gradient(90deg, #10b981, #059669); border-radius: 20px; padding: 20px; text-align: center; margin-bottom: 40px; box-shadow: 0 10px 30px rgba(16,185,129,0.3); border: 2px solid #34d399;">
            <p style="margin: 0; font-size: 24px; font-weight: 600; text-transform: uppercase; letter-spacing: 2px; color: #ecfdf5;">Reserve Price</p>
            <h2 style="margin: 5px 0 0 0; font-family: 'Montserrat', sans-serif; font-size: 64px; font-weight: 900; color: #fff;">₹ <?= number_format($prop['price'] ?? 0) ?></h2>
        </div>

        <!-- Details Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px;">
            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 15px;">
                <div style="background: #fbbf24; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #0f172a;"><i class="fas fa-university"></i></div>
                <div>
                    <p style="margin: 0; font-size: 16px; color: #94a3b8; text-transform: uppercase;">Bank Name</p>
                    <h4 style="margin: 5px 0 0 0; font-size: 22px; color: #fff; font-weight: 700;"><?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></h4>
                </div>
            </div>
            
            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 15px;">
                <div style="background: #fbbf24; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #0f172a;"><i class="fas fa-building"></i></div>
                <div>
                    <p style="margin: 0; font-size: 16px; color: #94a3b8; text-transform: uppercase;">Property Type</p>
                    <h4 style="margin: 5px 0 0 0; font-size: 22px; color: #fff; font-weight: 700;"><?= htmlspecialchars($prop['type'] ?? 'N/A') ?></h4>
                </div>
            </div>

            <!-- 🔥 Area Box Added to Grid -->
            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 15px;">
                <div style="background: #fbbf24; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #0f172a;"><i class="fas fa-ruler-combined"></i></div>
                <div>
                    <p style="margin: 0; font-size: 16px; color: #94a3b8; text-transform: uppercase;">Area</p>
                    <h4 style="margin: 5px 0 0 0; font-size: 22px; color: #fff; font-weight: 700;"><?= htmlspecialchars($prop['sqft'] ?? 'N/A') ?> Sq Ft</h4>
                </div>
            </div>

            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 15px;">
                <div style="background: #fbbf24; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #0f172a;"><i class="fas fa-calendar-alt"></i></div>
                <div>
                    <p style="margin: 0; font-size: 16px; color: #94a3b8; text-transform: uppercase;">Auction Date</p>
                    <h4 style="margin: 5px 0 0 0; font-size: 22px; color: #fff; font-weight: 700;"><?= !empty($prop['auction_date']) ? date('d M Y', strtotime($prop['auction_date'])) : 'TBA' ?></h4>
                </div>
            </div>

            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 15px;">
                <div style="background: #fbbf24; width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #0f172a;"><i class="fas fa-hashtag"></i></div>
                <div>
                    <p style="margin: 0; font-size: 16px; color: #94a3b8; text-transform: uppercase;">Property ID</p>
                    <h4 style="margin: 5px 0 0 0; font-size: 22px; color: #fff; font-weight: 700;">#<?= $prop['id'] ?></h4>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="margin-top: auto; text-align: center; border-top: 2px solid rgba(255,255,255,0.1); padding-top: 20px;">
            <p style="margin: 0; font-size: 24px; color: #fbbf24; font-weight: 700;">Prime Property India</p>
            <p style="margin: 5px 0 0 0; font-size: 18px; color: #94a3b8;">www.primepropertyindia.onrender.com</p>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- JAVASCRIPT -->
    <!-- ============================================================ -->
    <script>
    // 🔥 Smart WhatsApp Opener (Mobile vs Desktop)
    function openWhatsApp() {
        const dataDiv = document.getElementById('wa-data');
        if (!dataDiv) return;

        const phone = dataDiv.getAttribute('data-phone');
        const rawMsg = dataDiv.getAttribute('data-msg');
        const encodedText = encodeURIComponent(rawMsg);
        
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        if (isMobile) {
            window.location.href = `whatsapp://send?phone=${phone}&text=${encodedText}`;
        } else {
            window.open(`https://web.whatsapp.com/send?phone=${phone}&text=${encodedText}`, '_blank');
        }
    }

    // 🔥 Poster Download Script (Targets the Hidden Luxury Template)
    function downloadPoster() {
        const poster = document.getElementById('poster-template');
        
        // Temporarily make it visible but behind everything to allow canvas to render it properly
        poster.style.left = '0';
        poster.style.top = '0';
        poster.style.zIndex = '-1';

        html2canvas(poster, {
            scale: 1, // 1080x1350 is already high res. Scale 1 is fine.
            useCORS: true,
            backgroundColor: null,
            logging: false
        }).then(canvas => {
            let link = document.createElement('a');
            link.download = 'PrimeProperty_<?= $prop_id ?>_Banner.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            
            // Hide it back
            poster.style.left = '-9999px';
        }).catch(err => {
            alert("Banner download failed. Please try again.");
            poster.style.left = '-9999px';
        });
    }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
