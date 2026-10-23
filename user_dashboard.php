<?php
// ============================================================
// 👤 User Dashboard – Clean Layout (Spin in Header)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- Get user city for best deals ----
$user_stmt = $pdo->prepare("SELECT city FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_city = $user_stmt->fetchColumn() ?? '';

// ---- Today's Auctions (including Private Treaty) ----
$city_sql = "SELECT * FROM properties WHERE status = 'available' AND city ILIKE ? AND auction_date = CURRENT_DATE ORDER BY id DESC";
$city_stmt = $pdo->prepare($city_sql);
$city_stmt->execute(['%' . $user_city . '%']);
$city_props = $city_stmt->fetchAll();

$pt_sql = "SELECT * FROM properties WHERE status = 'available' AND auction_start_time = 'Private Treaty' ORDER BY id DESC";
$pt_stmt = $pdo->prepare($pt_sql);
$pt_stmt->execute();
$pt_props = $pt_stmt->fetchAll();

$today_props = array_merge($city_props, $pt_props);

// ---- Best Deals ----
$best_sql = "SELECT * FROM properties WHERE status = 'available'";
$best_params = [];
if (!empty($user_city)) {
    $best_sql .= " AND city ILIKE ?";
    $best_params[] = '%' . $user_city . '%';
}
$best_sql .= " ORDER BY price ASC LIMIT 8";
$best_stmt = $pdo->prepare($best_sql);
$best_stmt->execute($best_params);
$best_props = $best_stmt->fetchAll();

// ---- Fetch active notification for popup ----
$notif_stmt = $pdo->query("SELECT * FROM admin_notifications WHERE is_active = true ORDER BY updated_at DESC LIMIT 1");
$notification = $notif_stmt->fetch();

// ---- Render property card ----
function renderDashboardCard($prop, $show_images = false, $is_today = false) {
    $gradients = [
        ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #1e3a5f 0%, #3b82f6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #064e3b 0%, #10b981 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #4c1d95 0%, #8b5cf6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #b91c1c 0%, #ef4444 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #78350f 0%, #f59e0b 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #172554 0%, #6366f1 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%)', 'text' => 'dark'],
    ];
    $g = $gradients[array_rand($gradients)];
    $text_color = ($g['text'] == 'white') ? '#ffffff' : '#0f172a';
    $shadow = ($g['text'] == 'white') ? '0 15px 40px -10px rgba(0,0,0,0.3)' : '0 15px 40px -10px rgba(0,0,0,0.1)';
    $border = ($g['text'] == 'white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.05)';
    $image_url = $prop['image_url'] ?? '';
    $is_private_treaty = isset($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty';
    ?>
    <div class="col-md-6 col-xl-4 mb-4">
        <div class="card h-100" style="border-radius:20px; overflow:hidden; border:none; box-shadow:<?= $shadow ?>; transition:all 0.4s; background: <?= $g['bg'] ?>; color:<?= $text_color ?>; position:relative;">
            <?php if($is_private_treaty): ?>
                <div style="position:absolute; top:15px; right:15px; z-index:10; background:#f59e0b; color:#000; padding:4px 14px; border-radius:30px; font-size:0.7rem; font-weight:700;">
                    🔑 Private Treaty
                </div>
            <?php endif; ?>
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                    <?php if(!empty($prop['auction_start_time']) && $prop['auction_start_time'] != 'Private Treaty'): ?>
                        <span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mt-2" style="color:<?= $text_color ?>;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:<?= $text_color ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                <div style="font-size:0.85rem; opacity:0.8; color:<?= $text_color ?>;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                <a href="property_detail.php?id=<?= $prop['id'] ?>&source=auction" style="display:block; margin-top:16px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
            </div>
            <?php if($show_images && !empty($image_url)): ?>
                <img src="<?= htmlspecialchars($image_url) ?>" style="height:180px; width:100%; object-fit:cover; border-top:3px solid <?= $border ?>;" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:150px; background:rgba(255,255,255,0.08); display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(4px); border-top:3px solid <?= $border ?>; padding:10px;">
                    <i class="fas fa-lock" style="font-size:1.8rem; opacity:0.7; color:<?= $text_color ?>;"></i>
                    <span style="font-size:0.8rem; font-weight:600; margin-top:4px; color:<?= $text_color ?>;">🔒 Subscribe to unlock</span>
                    <a href="user_packages.php" class="btn btn-sm btn-primary mt-2" style="border-radius:30px; font-weight:600; color:#ffffff; background:#2563eb; border:none;">Subscribe Now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

include 'header.php';
?>

<style>
    .section-title { font-weight: 800; color: #0f172a; margin-bottom: 20px; font-size: 1.2rem; }
    .section-title i { margin-right: 10px; }
    .no-auction-msg { background: #f8fafc; border-radius: 20px; padding: 30px; text-align: center; border: 2px dashed #e2e8f0; }
    .no-auction-msg i { font-size: 2.5rem; opacity:0.3; }

    /* Modals CSS */
    .premium-modal .modal-content { background: linear-gradient(145deg, #0b1120, #1a2332); border: 2px solid rgba(255, 215, 0, 0.3); box-shadow: 0 30px 80px rgba(0,0,0,0.8), 0 0 40px rgba(255, 215, 0, 0.1); border-radius: 32px; color: #fff; position: relative; overflow: hidden; }
    .premium-modal .modal-content::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle at 30% 50%, rgba(255,215,0,0.08) 0%, transparent 60%); pointer-events: none; animation: shimmer 6s infinite linear; }
    @keyframes shimmer { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .premium-modal .modal-header { border-bottom: 1px solid rgba(255,215,0,0.2); padding: 24px 30px 16px 30px; }
    .premium-modal .modal-title { font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, #fbbf24, #f59e0b, #fbbf24); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; animation: goldShine 3s ease-in-out infinite; letter-spacing: 1px; text-shadow: 0 0 30px rgba(251,191,36,0.3); }
    @keyframes goldShine { 0% { background-position: 0% center; } 50% { background-position: 200% center; } 100% { background-position: 0% center; } }
    .premium-modal .modal-body { padding: 30px 30px 20px 30px; font-size: 1.15rem; line-height: 1.8; color: #e2e8f0; text-shadow: 0 2px 10px rgba(0,0,0,0.5); position: relative; z-index: 1; }
    .premium-modal .modal-body img { border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.6); border: 1px solid rgba(255,215,0,0.15); margin-bottom: 20px; width: 100%; max-height: 280px; object-fit: contain; }
    .premium-modal .modal-footer { border-top: 1px solid rgba(255,215,0,0.15); padding: 16px 30px 24px 30px; justify-content: center; }
    .premium-modal .btn-gold { background: linear-gradient(135deg, #fbbf24, #f59e0b); border: none; color: #0f172a; font-weight: 700; padding: 12px 40px; border-radius: 50px; font-size: 1.1rem; letter-spacing: 0.5px; box-shadow: 0 8px 25px rgba(251,191,36,0.4); transition: all 0.3s ease; }
    .premium-modal .btn-gold:hover { transform: scale(1.05); box-shadow: 0 12px 40px rgba(251,191,36,0.6); color: #0f172a; }
    .premium-modal .btn-close-white { filter: invert(1) brightness(2); opacity: 0.7; }
    .premium-modal .btn-close-white:hover { opacity: 1; }
</style>

<div class="container-fluid mt-2">
    
    <!-- Today's Auctions -->
    <?php 
    $pt_count = 0;
    foreach($today_props as $p) {
        if(isset($p['auction_start_time']) && $p['auction_start_time'] == 'Private Treaty') $pt_count++;
    }
    ?>
    <div class="section-title mt-1">
        <i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions
        <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span>
        <?php if($pt_count > 0): ?>
            <span class="badge bg-warning text-dark rounded-pill ms-2">🔑 <?= $pt_count ?> Private Treaty</span>
        <?php endif; ?>
    </div>
    
    <?php if (count($today_props) > 0): ?>
        <div class="row">
            <?php foreach ($today_props as $prop): ?>
                <?php renderDashboardCard($prop, false, true); ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-auction-msg">
            <i class="fas fa-calendar-day"></i>
            <p class="mt-2 fw-bold">📭 No auction today</p>
        </div>
    <?php endif; ?>

    <hr class="my-4">

    <!-- Best Deals in City -->
    <div class="section-title">
        <i class="fas fa-fire" style="color:#f97316;"></i> Best Deals in <?= !empty($user_city) ? htmlspecialchars($user_city) : 'Your City' ?>
        <span class="badge bg-primary rounded-pill ms-2"><?= count($best_props) ?></span>
    </div>
    
    <?php if (count($best_props) > 0): ?>
        <div class="row">
            <?php foreach ($best_props as $prop): ?>
                <?php renderDashboardCard($prop, false, false); ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center text-muted py-4 bg-white rounded-4 shadow-sm border">
            <i class="fas fa-search" style="font-size:2rem; opacity:0.3;"></i>
            <p class="mt-2">No properties available in your city. Explore all properties.</p>
            <a href="index.php" class="btn btn-outline-primary btn-sm">View All Properties</a>
        </div>
    <?php endif; ?>

</div>

<!-- Property Modal for Spin -->
<div class="modal fade" id="propertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 24px; overflow: hidden; background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                <h5 class="modal-title"><i class="fas fa-home me-2" style="color: #fbbf24;"></i>🏠 Low Price Property</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="propertyModalContent"></div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-undo-alt me-2"></i>Close</button>
                <a href="#" id="viewPropertyLink" class="btn btn-primary" target="_blank">View Details</a>
            </div>
        </div>
    </div>
</div>

<!-- 🔥 ADMIN NOTIFICATION POPUP -->
<?php if ($notification): ?>
<div class="modal fade premium-modal" id="notificationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-crown me-2" style="color: #fbbf24;"></i><?= htmlspecialchars($notification['title']) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <?php if (!empty($notification['image_url'])): ?>
                    <img src="<?= htmlspecialchars($notification['image_url']) ?>" alt="Notification Image">
                <?php endif; ?>
                <p style="font-weight: 400;"><?= nl2br(htmlspecialchars($notification['message'] ?? '')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gold" data-bs-dismiss="modal"><i class="fas fa-check-circle me-2"></i>Got it!</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('notificationModal');
    if (modalElement) {
        const myModal = new bootstrap.Modal(modalElement, { backdrop: 'static', keyboard: false });
        myModal.show();
    }
});
</script>
<?php endif; ?>

<!-- ====== SPIN SCRIPT ====== -->
<script>
function triggerHeaderSpin() {
    const spinBtn = document.getElementById('miniSpinBtn');
    const spinIcon = spinBtn.querySelector('i');
    if (spinBtn.disabled) return;

    spinBtn.disabled = true;
    spinIcon.classList.add('fa-spin');

    fetch('spin_ajax.php')
        .then(response => {
            if (!response.ok) throw new Error('HTTP error ' + response.status);
            return response.json();
        })
        .then(data => {
            spinIcon.classList.remove('fa-spin');
            
            if (data.success) {
                // Animate Coin Balance
                if (data.coins > 0) {
                    animateCoinBalance(data.coins);
                    showToast(`🪙 +${data.coins} coins won!`, '#10b981');
                }

                // Show property modal
                if (data.show_property && data.property) {
                    const p = data.property;
                    const isCar = (p.type && (p.type.toLowerCase().includes('car') || p.type.toLowerCase().includes('vehicle')));
                    const icon = isCar ? '🚗' : '🏠';
                    const imageHtml = p.image_url ? `<img src="${p.image_url}" style="width:100%; max-height:200px; object-fit:cover; border-radius:12px; margin-bottom:12px;" alt="${p.title}">` : `<div style="height:150px; background:#1e293b; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8;"><i class="fas fa-image fa-2x"></i></div>`;
                    
                    const modalContent = document.getElementById('propertyModalContent');
                    if (modalContent) {
                        modalContent.innerHTML = `
                            ${imageHtml}
                            <h5 class="fw-bold">${icon} ${p.title}</h5>
                            <p class="text-muted">🏦 ${p.bank_name || 'Bank'}</p>
                            <p class="text-warning fw-bold">₹ ${parseInt(p.price).toLocaleString('en-IN')}</p>
                            <p><i class="fas fa-map-pin"></i> ${p.city || 'N/A'}</p>
                            <p><small class="text-muted">Type: ${p.type || 'N/A'}</small></p>
                            <div class="mt-2 p-2 bg-success bg-opacity-25 rounded-3">
                                <i class="fas fa-coins text-warning"></i> You earned <strong>${data.coins}</strong> coins!
                            </div>
                        `;
                        const viewLink = document.getElementById('viewPropertyLink');
                        if (viewLink) viewLink.href = `property_detail.php?id=${p.id}&source=auction`;
                        
                        const propertyModal = new bootstrap.Modal(document.getElementById('propertyModal'));
                        propertyModal.show();
                        
                        propertyModal._element.addEventListener('hidden.bs.modal', function () { 
                            spinBtn.disabled = false; 
                        });
                    }
                } else {
                    // Just a coin reward
                    spinBtn.disabled = false;
                }
            } else {
                showToast(`❌ ${data.message || 'Something went wrong'}`, '#dc2626');
                spinBtn.disabled = false;
            }
        })
        .catch(error => {
            spinIcon.classList.remove('fa-spin');
            showToast('❌ Error spinning. Try again.', '#dc2626');
            spinBtn.disabled = false;
        });
}

// 🪙 Coin Counting Animation
function animateCoinBalance(coinsToAdd) {
    const el = document.getElementById('headerCoinBalance');
    if (!el) return;
    
    let start = parseInt(el.textContent.replace(/,/g, '')) || 0;
    let end = start + coinsToAdd;
    let duration = 1000; // 1 second
    let startTime = null;

    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        let progress = Math.min((timestamp - startTime) / duration, 1);
        let current = Math.floor(progress * (end - start) + start);
        el.textContent = current.toLocaleString();
        if (progress < 1) {
            window.requestAnimationFrame(step);
        } else {
            el.textContent = end.toLocaleString();
        }
    }
    window.requestAnimationFrame(step);
}

// Small Toast Notification
function showToast(msg, bgColor) {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed; bottom:20px; right:20px; background:${bgColor}; color:white; padding:16px 24px; border-radius:12px; font-weight:bold; box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:9999; animation: slideIn 0.5s ease;`;
    toast.innerHTML = msg;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}
</script>
<style>
@keyframes slideIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<?php include 'footer.php'; ?>
