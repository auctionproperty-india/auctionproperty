<?php
// ============================================================
// 👤 User Dashboard – Search Bar + City Properties + Spin Modal
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- Get user city for default filter ----
$user_stmt = $pdo->prepare("SELECT city FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_city = $user_stmt->fetchColumn() ?? '';

// ---- Search Parameters (Default city = user's city) ----
$search_city      = $_GET['city'] ?? $user_city; 
$search_locality  = $_GET['locality'] ?? '';
$search_types     = $_GET['type'] ?? [];
$search_min_price = $_GET['min_price'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';
$search_date      = $_GET['date']  ?? '';

// Fix array for types
if (!is_array($search_types)) {
    $search_types = $search_types === '' ? [] : [$search_types];
}
$search_types = array_values(array_filter($search_types, function($t) { return $t !== ''; }));

// ---- Build Query ----
$where = "status = 'available'";
$params = [];

if(!empty($search_city)) {
    $where .= " AND city ILIKE ?";
    $params[] = '%' . $search_city . '%';
}
if(!empty($search_locality)) {
    $where .= " AND locality ILIKE ?";
    $params[] = '%' . $search_locality . '%';
}
if(!empty($search_types)) {
    $ph = implode(',', array_fill(0, count($search_types), '?'));
    $where .= " AND type IN ($ph)";
    foreach ($search_types as $t) $params[] = $t;
}
if(!empty($search_min_price)) {
    $where .= " AND price >= ?";
    $params[] = (float)$search_min_price;
}
if(!empty($search_max_price)) {
    $where .= " AND price <= ?";
    $params[] = (float)$search_max_price;
}

// Fetch properties
$sql = "SELECT * FROM properties WHERE $where ORDER BY id DESC LIMIT 12";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$display_props = $stmt->fetchAll();

// ---- Fetch active notification for popup ----
$notif_stmt = $pdo->query("SELECT * FROM admin_notifications WHERE is_active = true ORDER BY updated_at DESC LIMIT 1");
$notification = $notif_stmt->fetch();

$all_types = ['Flat','Plot','Shop','Land','House','Land & Building','Car/Vehicle','Commercial','Office','Row House','Bungalow','Other'];

// ---- Render property card ----
function renderDashboardCard($prop, $show_images = false) {
    $gradients = [
        ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #1e3a5f 0%, #3b82f6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #064e3b 0%, #10b981 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #4c1d95 0%, #8b5cf6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #b91c1c 0%, #ef4444 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #78350f 0%, #f59e0b 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #172554 0%, #6366f1 100%)', 'text' => 'white'],
    ];
    $g = $gradients[array_rand($gradients)];
    $text_color = '#ffffff';
    $shadow = '0 15px 40px -10px rgba(0,0,0,0.3)';
    $border = 'rgba(255,255,255,0.15)';
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
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; background:rgba(255,255,255,0.15); padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                    <?php if(!empty($prop['auction_start_time']) && $prop['auction_start_time'] != 'Private Treaty'): ?>
                        <span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mt-2" style="color:<?= $text_color ?>;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:<?= $text_color ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                <div style="font-size:0.85rem; opacity:0.8; color:<?= $text_color ?>;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                <a href="property_detail.php?id=<?= $prop['id'] ?>&source=auction" style="display:block; margin-top:16px; background:rgba(255,255,255,0.15); backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
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
    /* Search Bar CSS */
    .search-box { background:#ffffff; padding:20px 25px; border-radius:20px; box-shadow:0 10px 30px -10px rgba(0,0,0,0.08); border:1px solid #e2e8f0; margin-bottom:30px; }
    .search-box .form-control { border:none; background:#f1f5f9; border-radius:16px; padding:12px 20px; font-size:0.95rem; }
    .search-box .form-control:focus { background:#e2e8f0; box-shadow:0 0 0 3px rgba(37,99,235,0.15); }
    .search-box .btn-primary { border-radius:20px; padding:12px 30px; background:linear-gradient(135deg, #1e3a8a, #2563eb); border:none; font-weight:600; }
    .search-box .btn-reset { border-radius:20px; padding:12px 20px; background:#f1f5f9; color:#475569; border:none; font-weight:600; }
    
    /* Multi-Select Type */
    .type-selector-wrapper { position: relative; }
    .type-selector-btn { width: 100%; border: none; background: #f1f5f9; border-radius: 16px; padding: 12px 20px; font-size: 0.95rem; text-align: left; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #1e293b; }
    .type-panel { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #fff; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; padding: 10px; z-index: 100; max-height: 300px; overflow-y: auto; min-width: 260px; }
    .type-panel-header { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.75rem; color: #64748b; font-weight: 700; display: flex; justify-content: space-between; }
    .type-checkbox-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; cursor: pointer; font-size: 0.88rem; }
    .type-checkbox-item.checked { background: #eff6ff; font-weight: 700; color: #1e40af; }

    .section-title { font-weight: 800; color: #0f172a; margin-bottom: 20px; font-size: 1.2rem; }
    .no-auction-msg { background: #f8fafc; border-radius: 20px; padding: 30px; text-align: center; border: 2px dashed #e2e8f0; }
    
    /* Modals CSS */
    .premium-modal .modal-content { background: linear-gradient(145deg, #0b1120, #1a2332); border: 2px solid rgba(255, 215, 0, 0.3); border-radius: 32px; color: #fff; }
    .premium-modal .modal-title { font-size: 1.5rem; font-weight: 800; color: #fbbf24; }
    .premium-modal .btn-gold { background: linear-gradient(135deg, #fbbf24, #f59e0b); border: none; color: #0f172a; font-weight: 700; padding: 10px 30px; border-radius: 50px; }
</style>

<div class="container-fluid mt-2">
    
    <!-- SEARCH BOX -->
    <div class="search-box">
        <form method="GET" class="row g-3 align-items-center" id="searchForm">
            <div class="col-md-3">
                <input type="text" name="city" class="form-control" placeholder="🔍 Search by City..." value="<?= htmlspecialchars($search_city) ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="locality" class="form-control" placeholder="📍 Search by Locality..." value="<?= htmlspecialchars($search_locality) ?>">
            </div>
            <div class="col-md-3">
                <div class="type-selector-wrapper">
                    <button type="button" class="type-selector-btn" onclick="toggleTypePanel(event)">
                        <span id="typeLabel"><?= empty($search_types) ? 'All Types' : (count($search_types) === 1 ? htmlspecialchars($search_types[0]) : htmlspecialchars($search_types[0]) . ' +' . (count($search_types) - 1) . ' more') ?></span>
                        <i class="fas fa-chevron-down" style="font-size:0.75rem;opacity:0.5;"></i>
                    </button>
                    <div class="type-panel" id="typePanel" style="display:none;">
                        <div class="type-panel-header">
                            <span>Select Property Type</span>
                            <button type="button" onclick="clearAllTypes(event)" style="background:none;border:none;color:#2563eb;font-weight:bold;cursor:pointer;">Clear All</button>
                        </div>
                        <?php foreach ($all_types as $t): $is_checked = in_array($t, $search_types); ?>
                            <label class="type-checkbox-item <?= $is_checked ? 'checked' : '' ?>" onclick="event.stopPropagation();">
                                <input type="checkbox" name="type[]" value="<?= htmlspecialchars($t) ?>" <?= $is_checked ? 'checked' : '' ?> onchange="onTypeChange(this)">
                                <span><?= htmlspecialchars($t) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($search_date) ?>">
            </div>
            <div class="col-md-3">
                <input type="number" name="min_price" class="form-control" placeholder="💰 Min Price" value="<?= htmlspecialchars($search_min_price) ?>">
            </div>
            <div class="col-md-3">
                <input type="number" name="max_price" class="form-control" placeholder="💰 Max Price" value="<?= htmlspecialchars($search_max_price) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Search</button>
            </div>
            <div class="col-md-2">
                <a href="user_dashboard.php" class="btn btn-reset w-100"><i class="fas fa-redo"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Properties Display -->
    <div class="section-title">
        <i class="fas fa-building" style="color:#2563eb;"></i> Properties <?= !empty($search_city) ? 'in ' . htmlspecialchars($search_city) : '' ?>
        <span class="badge bg-primary rounded-pill ms-2"><?= count($display_props) ?></span>
    </div>
    
    <?php if (count($display_props) > 0): ?>
        <div class="row">
            <?php foreach ($display_props as $prop): ?>
                <?php renderDashboardCard($prop, false); ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-auction-msg py-5">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h5 class="fw-bold">No properties found</h5>
            <p class="text-muted">Try adjusting your search filters or search in a different city.</p>
            <a href="user_dashboard.php" class="btn btn-outline-primary mt-2">Clear Filters</a>
        </div>
    <?php endif; ?>

</div>

<!-- ====== 🔥 SPIN WHEEL MODAL ====== -->
<div class="modal fade" id="spinWheelModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 24px; background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; border: 2px solid #fbbf24;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-dharmachakra fa-spin"></i> Daily Spin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                
                <!-- Visual Wheel -->
                <div class="spin-wheel-container">
                    <div class="spin-pointer"></div>
                    <div class="spin-wheel" id="visualSpinWheel"></div>
                </div>

                <div class="mb-3">
                    <span class="badge bg-light text-dark fs-6">Your Coins: <strong id="modalCoinBalance"><?= number_format($user_coins) ?></strong></span>
                </div>
                
                <button type="button" class="btn btn-warning btn-lg w-100 fw-bold rounded-pill" id="spinBtn" onclick="startSpinAnimation()">
                    <i class="fas fa-sync-alt"></i> SPIN NOW
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Property Result Modal -->
<div class="modal fade" id="propertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 24px; overflow: hidden; background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                <h5 class="modal-title"><i class="fas fa-home me-2" style="color: #fbbf24;"></i> You Won!</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="propertyModalContent"></div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
                <a href="#" id="viewPropertyLink" class="btn btn-primary w-100" target="_blank">View Details</a>
            </div>
        </div>
    </div>
</div>

<!-- Admin Notification Popup -->
<?php if ($notification): ?>
<div class="modal fade premium-modal" id="notificationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="fas fa-crown me-2"></i><?= htmlspecialchars($notification['title']) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <?php if (!empty($notification['image_url'])): ?>
                    <img src="<?= htmlspecialchars($notification['image_url']) ?>" alt="Notification" style="max-width:100%; border-radius:16px; margin-bottom:15px;">
                <?php endif; ?>
                <p style="font-weight: 400; font-size:1.1rem;"><?= nl2br(htmlspecialchars($notification['message'] ?? '')) ?></p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-gold" data-bs-dismiss="modal"><i class="fas fa-check-circle me-2"></i>Got it!</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('notificationModal');
    if (el) new bootstrap.Modal(el).show();
});
</script>
<?php endif; ?>

<!-- ====== SPIN WHEEL JS LOGIC ====== -->
<script>
let isSpinning = false;
let currentRotation = 0;

function startSpinAnimation() {
    if (isSpinning) return;
    isSpinning = true;
    
    const btn = document.getElementById('spinBtn');
    const wheel = document.getElementById('visualSpinWheel');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Spinning...';

    // Random rotation (5 to 10 full spins + random angle)
    const spins = Math.floor(Math.random() * 5) + 5; 
    const extraAngle = Math.floor(Math.random() * 360);
    currentRotation += (spins * 360) + extraAngle;
    
    // Apply CSS rotation
    wheel.style.transform = `rotate(${currentRotation}deg)`;

    // Fetch result from backend after animation completes (4 seconds)
    setTimeout(() => {
        fetch('spin_ajax.php')
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> SPIN NOW';
                isSpinning = false;

                if (data.success) {
                    if (data.coins > 0) {
                        animateCoins(data.coins);
                    }
                    
                    if (data.show_property && data.property) {
                        showPropertyWin(data.property);
                    } else {
                        alert(data.message || 'You won coins!');
                    }
                } else {
                    alert('Error: ' + (data.message || 'Try again later.'));
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-sync-alt"></i> SPIN NOW';
                isSpinning = false;
                alert('Connection error. Try again.');
            });
    }, 4200); // Wait for wheel animation to finish
}

// Coin counting animation
function animateCoins(coinsToAdd) {
    const el = document.getElementById('headerCoinBalance');
    const modalEl = document.getElementById('modalCoinBalance');
    if (!el) return;
    
    let start = parseInt(el.textContent.replace(/,/g, '')) || 0;
    let end = start + coinsToAdd;
    let duration = 1000;
    let startTime = null;

    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        let progress = Math.min((timestamp - startTime) / duration, 1);
        let current = Math.floor(progress * (end - start) + start);
        
        el.textContent = current.toLocaleString();
        if (modalEl) modalEl.textContent = current.toLocaleString();
        
        if (progress < 1) window.requestAnimationFrame(step);
    }
    window.requestAnimationFrame(step);
}

// Show Property Modal
function showPropertyWin(prop) {
    const icon = (prop.type && prop.type.toLowerCase().includes('car')) ? '🚗' : '🏠';
    const imageHtml = prop.image_url ? `<img src="${prop.image_url}" style="width:100%; max-height:150px; object-fit:cover; border-radius:12px; margin-bottom:12px;">` : '';
    
    document.getElementById('propertyModalContent').innerHTML = `
        ${imageHtml}
        <h5 class="fw-bold">${icon} ${prop.title}</h5>
        <p class="text-warning fw-bold fs-4">₹ ${parseInt(prop.price).toLocaleString('en-IN')}</p>
        <p><i class="fas fa-map-pin"></i> ${prop.city || 'N/A'}</p>
    `;
    document.getElementById('viewPropertyLink').href = `property_detail.php?id=${prop.id}&source=auction`;
    
    // Hide Spin Modal, Show Property Modal
    const spinModalEl = document.getElementById('spinWheelModal');
    const spinModal = bootstrap.Modal.getInstance(spinModalEl);
    if (spinModal) spinModal.hide();

    new bootstrap.Modal(document.getElementById('propertyModal')).show();
}

// Multi-type selector toggle logic
function toggleTypePanel(e) {
    e.stopPropagation();
    const panel = document.getElementById('typePanel');
    panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
}
function onTypeChange(cb) {
    const label = cb.closest('.type-checkbox-item');
    if (cb.checked) label.classList.add('checked');
    else label.classList.remove('checked');
    updateTypeLabel();
}
function updateTypeLabel() {
    const checked = document.querySelectorAll('#typePanel input[type="checkbox"]:checked');
    const labelEl = document.getElementById('typeLabel');
    if (checked.length === 0) labelEl.textContent = 'All Types';
    else if (checked.length === 1) labelEl.textContent = checked[0].value;
    else labelEl.textContent = checked[0].value + ' +' + (checked.length - 1) + ' more';
}
function clearAllTypes(e) {
    e.stopPropagation();
    document.querySelectorAll('#typePanel input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
        cb.closest('.type-checkbox-item').classList.remove('checked');
    });
    updateTypeLabel();
}
document.addEventListener('click', function(e) {
    const panel = document.getElementById('typePanel');
    const wrapper = document.querySelector('.type-selector-wrapper');
    if (panel && wrapper && !wrapper.contains(e.target)) panel.style.display = 'none';
});
</script>

<?php include 'footer.php'; ?>
