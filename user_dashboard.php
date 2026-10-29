<?php
// ============================================================
// 👤 User Dashboard – Grouped Auctions + View All Logic
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🛡️ SECURITY: Block Bad Bots & Rate Limiting
blockBadBots();
checkRateLimit(30, 60); // 30 requests per 60 seconds

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- 🔥 Get user city & BA Status ----
$user_stmt = $pdo->prepare("SELECT city, ba_status FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch();
$user_city = $user_data['city'] ?? '';
$ba_status = $user_data['ba_status'] ?? 'none';

// ---- Views and Tabs ----
$view = $_GET['view'] ?? 'dashboard'; // 'dashboard' or 'all'
$tab  = $_GET['tab'] ?? 'auction';

// ---- Search Parameters ----
$search_city      = $_GET['city'] ?? ($view == 'dashboard' ? $user_city : ''); 
$search_locality  = $_GET['locality'] ?? '';
$search_types     = $_GET['type'] ?? [];
$search_min_price = $_GET['min_price'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';
$search_date      = $_GET['date']  ?? '';

if (!is_array($search_types)) {
    $search_types = $search_types === '' ? [] : [$search_types];
}
$search_types = array_values(array_filter($search_types, function($t) { return $t !== ''; }));

// ============================================================
// 🔥 COUNTERS & TOTALS
// ============================================================
$count_city_str = '%' . $user_city . '%';

// 1. User City Counts (For Dashboard Header)
$total_city = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE status = 'available' AND city ILIKE ?");
$total_city->execute([$count_city_str]);
$count_total_city = $total_city->fetchColumn();

$total_today = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE status = 'available' AND city ILIKE ? AND (auction_date = CURRENT_DATE OR auction_start_time = 'Private Treaty')");
$total_today->execute([$count_city_str]);
$count_today_city = $total_today->fetchColumn();

$total_past = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE status = 'available' AND city ILIKE ? AND auction_date < CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty')");
$total_past->execute([$count_city_str]);
$count_past_city = $total_past->fetchColumn();

// 2. Global Counts (For Tabs)
$global_auction = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available'")->fetchColumn();
$global_customer = $pdo->query("SELECT COUNT(*) FROM user_properties WHERE status = 'approved' AND (listing_type = 'sale' OR listing_type IS NULL)")->fetchColumn();
$global_exchange = $pdo->query("SELECT COUNT(*) FROM user_properties WHERE status = 'approved' AND listing_type = 'exchange'")->fetchColumn();

// ---- Build Common Where Clause for Search ----
$common_where = "";
$params = [];

if(!empty($search_city)) { $common_where .= " AND city ILIKE ?"; $params[] = '%' . $search_city . '%'; }
if(!empty($search_locality)) { $common_where .= " AND locality ILIKE ?"; $params[] = '%' . $search_locality . '%'; }
if(!empty($search_types)) {
    $ph = implode(',', array_fill(0, count($search_types), '?'));
    $common_where .= " AND type IN ($ph)";
    foreach ($search_types as $t) $params[] = $t;
}
if(!empty($search_min_price)) { $common_where .= " AND price >= ?"; $params[] = (float)$search_min_price; }
if(!empty($search_max_price)) { $common_where .= " AND price <= ?"; $params[] = (float)$search_max_price; }

// ---- Fetch Properties based on Tab ----
$today_props = [];
$upcoming_props = [];
$past_props = [];
$all_props = [];

if ($tab == 'auction') {
    // 🔥 AUCTION TAB: Always fetch grouped (Today, Upcoming, Past)
    
    // TODAY'S AUCTIONS
    $stmt = $pdo->prepare("SELECT *, 'auction' as source FROM properties WHERE status = 'available' AND (auction_date = CURRENT_DATE OR auction_start_time = 'Private Treaty') $common_where ORDER BY id DESC");
    $stmt->execute($params);
    $today_props = $stmt->fetchAll();

    // UPCOMING AUCTIONS
    $stmt = $pdo->prepare("SELECT *, 'auction' as source FROM properties WHERE status = 'available' AND auction_date > CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') $common_where ORDER BY price ASC");
    $stmt->execute($params);
    $upcoming_props = $stmt->fetchAll();

    // PAST AUCTIONS
    $stmt = $pdo->prepare("SELECT *, 'auction' as source FROM properties WHERE status = 'available' AND auction_date < CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') $common_where ORDER BY id DESC");
    $stmt->execute($params);
    $past_props = $stmt->fetchAll();

} elseif ($tab == 'customer') {
    // CUSTOMER TAB
    $sql = "SELECT *, 'customer' as source FROM user_properties WHERE status = 'approved' AND (listing_type = 'sale' OR listing_type IS NULL) $common_where ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_props = $stmt->fetchAll();

} elseif ($tab == 'exchange') {
    // EXCHANGE TAB
    $sql = "SELECT *, 'exchange' as source FROM user_properties WHERE status = 'approved' AND listing_type = 'exchange' $common_where ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_props = $stmt->fetchAll();
}

// Fetch notification
$notif_stmt = $pdo->query("SELECT * FROM admin_notifications WHERE is_active = true ORDER BY updated_at DESC LIMIT 1");
$notification = $notif_stmt->fetch();

$all_types = ['Flat','Plot','Shop','Land','House','Land & Building','Car/Vehicle','Commercial','Office','Row House','Bungalow','Other'];

// ---- Render property card ----
function renderDashboardCard($prop, $tab = 'auction') {
    $gradients = [
        ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)'],
        ['bg' => 'linear-gradient(135deg, #1e3a5f 0%, #3b82f6 100%)'],
        ['bg' => 'linear-gradient(135deg, #064e3b 0%, #10b981 100%)'],
        ['bg' => 'linear-gradient(135deg, #4c1d95 0%, #8b5cf6 100%)'],
        ['bg' => 'linear-gradient(135deg, #b91c1c 0%, #ef4444 100%)'],
        ['bg' => 'linear-gradient(135deg, #78350f 0%, #f59e0b 100%)'],
    ];
    $g = $gradients[array_rand($gradients)];
    $text_color = '#ffffff';
    $border = 'rgba(255,255,255,0.15)';
    $is_private_treaty = (isset($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty');
    ?>
    <div class="col-md-6 col-xl-4 mb-4">
        <div class="card h-100" style="border-radius:20px; overflow:hidden; border:none; box-shadow:0 10px 25px rgba(0,0,0,0.15); background: <?= $g['bg'] ?>; color:<?= $text_color ?>; position:relative;">
            
            <?php if($is_private_treaty): ?>
                <div style="position:absolute; top:0; right:0; z-index:10; background:#fbbf24; color:#000; padding:6px 16px; border-radius:0 0 0 16px; font-size:0.7rem; font-weight:800; box-shadow:-2px 2px 10px rgba(0,0,0,0.2);">🔑 Private Treaty</div>
            <?php endif; ?>
            
            <div class="card-body p-4 d-flex flex-column">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
                    <div style="flex: 1; min-width: 0;">
                        <span class="d-inline-block text-truncate w-100" style="font-size:0.7rem; font-weight:700; text-transform:uppercase; background:rgba(255,255,255,0.15); padding:6px 12px; border-radius:8px; color:<?= $text_color ?>;">
                            🏦 <?= htmlspecialchars($prop['bank_name'] ?? ($tab == 'auction' ? 'Bank' : 'User')) ?>
                        </span>
                    </div>
                    <?php if($tab == 'auction' && !empty($prop['auction_date']) && $prop['auction_start_time'] != 'Private Treaty'): ?>
                        <div style="flex-shrink: 0; text-align: center; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 4px 10px; min-width: 75px;">
                            <div style="font-size:0.55rem; text-transform:uppercase; opacity:0.8; font-weight:700;">Auction Date</div>
                            <div style="font-size:0.85rem; font-weight:800; margin-top:1px;"><i class="far fa-calendar-alt"></i> <?= date('d M Y', strtotime($prop['auction_date'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Title -->
                <h5 class="fw-bold mb-3" style="font-size:1.15rem; line-height:1.35; min-height:2.7em; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;" title="<?= htmlspecialchars($prop['title']) ?>">
                    <?= htmlspecialchars($prop['title']) ?>
                </h5>

                <div class="mt-auto">
                    <!-- Price -->
                    <div class="mb-2">
                        <div style="font-size:0.65rem; text-transform:uppercase; opacity:0.75; letter-spacing:1px; font-weight:700;">Reserve Price</div>
                        <div style="font-size:1.7rem; font-weight:900; letter-spacing:-0.5px;">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                    </div>
                    
                    <!-- City -->
                    <div class="mb-4 d-flex align-items-center gap-2" style="font-size:1.05rem; font-weight:700; opacity:0.95;">
                        <i class="fas fa-map-marker-alt" style="color:#fbbf24; font-size:1.1rem;"></i> <?= htmlspecialchars($prop['city'] ?? '') ?>
                    </div>
                    
                    <!-- Button -->
                    <div>
                        <?php if($tab == 'exchange'): ?>
                            <a href="exchange_interest.php?id=<?= $prop['id'] ?>" class="btn w-100" style="background:#fbbf24; color:#000; font-weight:800; border-radius:12px; padding:10px;" onclick="return confirm('Owner को notification भेजें?')">🤝 I'm Interested</a>
                        <?php else: ?>
                            <a href="property_detail.php?id=<?= $prop['id'] ?>&source=<?= $prop['source'] ?>" style="display:block; background:rgba(255,255,255,0.15); backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:11px; border-radius:12px; text-align:center; text-decoration:none; transition:all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">View Details →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

include 'header.php';
?>

<style>
    .search-box { background:#ffffff; padding:20px 25px; border-radius:20px; box-shadow:0 10px 30px -10px rgba(0,0,0,0.08); border:1px solid #e2e8f0; margin-bottom:20px; }
    .search-box .form-control { border:none; background:#f1f5f9; border-radius:16px; padding:12px 20px; font-size:0.95rem; }
    .search-box .btn-primary { border-radius:20px; padding:12px 30px; background:linear-gradient(135deg, #1e3a8a, #2563eb); border:none; font-weight:600; }
    .search-box .btn-reset { border-radius:20px; padding:12px 20px; background:#f1f5f9; color:#475569; border:none; font-weight:600; }
    
    .type-selector-wrapper { position: relative; }
    .type-selector-btn { width: 100%; border: none; background: #f1f5f9; border-radius: 16px; padding: 12px 20px; font-size: 0.95rem; text-align: left; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #1e293b; }
    .type-panel { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #fff; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; padding: 10px; z-index: 100; max-height: 300px; overflow-y: auto; min-width: 260px; }
    .type-panel-header { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.75rem; color: #64748b; font-weight: 700; display: flex; justify-content: space-between; }
    .type-checkbox-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; cursor: pointer; font-size: 0.88rem; }
    .type-checkbox-item.checked { background: #eff6ff; font-weight: 700; color: #1e40af; }

    .nav-tabs .nav-link { font-weight:600; color:#475569; border: none; padding:12px 20px; }
    .nav-tabs .nav-link.active { background: transparent; border-bottom: 3px solid #2563eb; color: #2563eb; }
    .nav-tabs .nav-link .badge-count { background: #e2e8f0; color: #1e293b; font-size: 0.7rem; padding: 2px 10px; border-radius: 30px; margin-left: 8px; font-weight: 700; }
    .nav-tabs .nav-link.active .badge-count { background: #2563eb; color: #ffffff; }
    
    .section-title { font-weight: 800; color: #0f172a; margin-bottom: 20px; font-size: 1.2rem; }
    .section-title i { margin-right: 10px; }

    .premium-modal .modal-content { background: linear-gradient(145deg, #0b1120, #1a2332); border: 2px solid rgba(255, 215, 0, 0.3); border-radius: 32px; color: #fff; }
    .premium-modal .modal-title { font-size: 1.5rem; font-weight: 800; color: #fbbf24; }
    .premium-modal .btn-gold { background: linear-gradient(135deg, #fbbf24, #f59e0b); border: none; color: #0f172a; font-weight: 700; padding: 10px 30px; border-radius: 50px; }
</style>

<div class="container-fluid mt-2">
    
    <!-- 🔥 BA Apply Banner (Only for normal users) -->
    <?php if ($ba_status == 'none'): ?>
    <div class="alert alert-warning border-0 shadow-sm mb-4 d-flex justify-content-between align-items-center flex-wrap" style="border-radius: 16px; background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);">
        <div>
            <h5 class="fw-bold text-dark mb-1"><i class="fas fa-handshake text-warning"></i> Become a Business Associate (BA)</h5>
            <p class="text-muted mb-0" style="font-size:0.95rem;">Join our BA program to earn commissions, refer users, and grow your team.</p>
        </div>
        <a href="apply_ba.php" class="btn btn-warning fw-bold px-4 py-2 mt-2 mt-md-0" style="border-radius: 20px;">Apply Now <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <?php elseif ($ba_status == 'pending'): ?>
    <div class="alert alert-info border-0 shadow-sm mb-4" style="border-radius: 16px;">
        <i class="fas fa-clock"></i> <strong>BA Application Pending:</strong> Your application to become a Business Associate is currently under review. We will notify you once approved.
    </div>
    <?php endif; ?>
    
    <!-- SEARCH BOX (Show only in All View) -->
    <?php if ($view == 'all'): ?>
        <div class="search-box">
            <form method="GET" class="row g-3 align-items-center">
                <input type="hidden" name="view" value="all">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
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
                    <a href="user_dashboard.php?view=all&tab=<?= $tab ?>" class="btn btn-reset w-100"><i class="fas fa-redo"></i> Reset</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- TABS (Always Visible) -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= ($tab=='auction')?'active':'' ?>" href="?view=<?= $view ?>&tab=auction&city=<?= urlencode($search_city) ?>">
                <i class="fas fa-gavel me-2"></i>Auction
                <span class="badge-count"><?= $global_auction ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($tab=='customer')?'active':'' ?>" href="?view=<?= $view ?>&tab=customer&city=<?= urlencode($search_city) ?>">
                <i class="fas fa-home me-2"></i>Customer
                <span class="badge-count"><?= $global_customer ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($tab=='exchange')?'active':'' ?>" href="?view=<?= $view ?>&tab=exchange&city=<?= urlencode($search_city) ?>">
                <i class="fas fa-exchange-alt me-2"></i>Exchange
                <span class="badge-count"><?= $global_exchange ?></span>
            </a>
        </li>
    </ul>

    <!-- 🔥 DASHBOARD VIEW (Counters, if not in 'all' view) -->
    <?php if ($view == 'dashboard'): ?>
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px; background: #fff;">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center py-3">
                <div class="d-flex gap-2 align-items-center">
                    <i class="fas fa-city text-primary fs-4"></i>
                    <h5 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($user_city ?: 'Your City') ?></h5>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-2 mt-md-0">
                    <span class="badge bg-primary fs-6 rounded-pill px-3 py-2">Total: <?= $count_total_city ?></span>
                    <span class="badge bg-danger fs-6 rounded-pill px-3 py-2">Today: <?= $count_today_city ?></span>
                    <span class="badge bg-secondary fs-6 rounded-pill px-3 py-2">Past: <?= $count_past_city ?></span>
                    <a href="user_dashboard.php?view=all&tab=auction" class="btn btn-sm btn-outline-primary fw-bold ms-2" style="border-radius:20px;">More Properties <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- CONTENT AREA -->
    <?php if ($tab == 'auction'): ?>
        
        <?php if ($view == 'all'): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0"><i class="fas fa-building text-primary"></i> All Auction Properties</h5>
                <a href="user_dashboard.php" class="btn btn-outline-secondary fw-bold" style="border-radius:20px;">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        <?php endif; ?>

        <!-- 1. TODAY'S AUCTIONS -->
        <div class="section-title mt-4"><i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span></div>
        <?php if(count($today_props) > 0): ?>
            <div class="row"><?php foreach($today_props as $prop) renderDashboardCard($prop, 'auction'); ?></div>
        <?php else: ?>
            <div class="alert alert-light border text-center py-4 mb-4" style="border-radius:20px;"><p class="mb-0 text-muted fw-bold">📭 No auction today</p></div>
        <?php endif; ?>

        <hr class="my-5">

        <!-- 2. UPCOMING AUCTIONS -->
        <div class="section-title"><i class="fas fa-clock" style="color:#2563eb;"></i> Upcoming Auctions <span class="badge bg-primary rounded-pill ms-2"><?= count($upcoming_props) ?></span></div>
        <?php if(count($upcoming_props) > 0): ?>
            <div class="row"><?php foreach($upcoming_props as $prop) renderDashboardCard($prop, 'auction'); ?></div>
        <?php else: ?>
            <div class="alert alert-light border text-center py-4 mb-4" style="border-radius:20px;"><p class="mb-0 text-muted fw-bold">📅 No upcoming auctions</p></div>
        <?php endif; ?>

        <hr class="my-5">

        <!-- 3. PAST AUCTIONS -->
        <div class="section-title"><i class="fas fa-history" style="color:#64748b;"></i> Past Auctions <span class="badge bg-secondary rounded-pill ms-2"><?= count($past_props) ?></span></div>
        <?php if(count($past_props) > 0): ?>
            <div class="row"><?php foreach($past_props as $prop) renderDashboardCard($prop, 'auction'); ?></div>
        <?php else: ?>
            <div class="alert alert-light border text-center py-4" style="border-radius:20px;"><p class="mb-0 text-muted fw-bold">📭 No past auctions</p></div>
        <?php endif; ?>

    <?php else: ?>
        
        <!-- CUSTOMER / EXCHANGE GRID -->
        <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
            <h5 class="fw-bold m-0">
                <i class="fas fa-building text-primary"></i> All <?= ucfirst($tab) ?> Properties
                <span class="badge bg-primary rounded-pill ms-2"><?= count($all_props) ?></span>
            </h5>
            <?php if ($view == 'all'): ?>
                <a href="user_dashboard.php" class="btn btn-outline-secondary fw-bold" style="border-radius:20px;">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (count($all_props) > 0): ?>
            <div class="row">
                <?php foreach ($all_props as $prop): ?>
                    <?php renderDashboardCard($prop, $tab); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light border text-center py-5" style="border-radius:20px;">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="fw-bold">No properties found</h5>
                <p class="text-muted">Try adjusting your search filters or search in a different city.</p>
            </div>
        <?php endif; ?>

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
                <div class="spin-wheel-container">
                    <div class="spin-pointer"></div>
                    <div class="spin-wheel" id="visualSpinWheel"></div>
                </div>

                <div class="mb-3">
                    <span class="badge bg-light text-dark fs-6">Your Coins: <strong id="modalCoinBalance"><?= number_format($user_coins ?? 0) ?></strong></span>
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

    const spins = Math.floor(Math.random() * 5) + 5; 
    const extraAngle = Math.floor(Math.random() * 360);
    currentRotation += (spins * 360) + extraAngle;
    
    wheel.style.transform = `rotate(${currentRotation}deg)`;

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
                    
                    const spinModalEl = document.getElementById('spinWheelModal');
                    const spinModal = bootstrap.Modal.getInstance(spinModalEl);
                    
                    if (data.show_property && data.property) {
                        if (spinModal) spinModal.hide();
                        spinModalEl.addEventListener('hidden.bs.modal', function () {
                            showPropertyWin(data.property);
                        }, { once: true });
                    } else {
                        setTimeout(() => {
                            if (spinModal) spinModal.hide();
                            showToast(`🪙 +${data.coins} Coins Won!`, '#10b981');
                        }, 1000);
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
    }, 4200); 
}

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
    
    new bootstrap.Modal(document.getElementById('propertyModal')).show();
}

function showToast(msg, bgColor) {
    const toast = document.createElement('div');
    toast.style.cssText = `position:fixed; bottom:20px; right:20px; background:${bgColor}; color:white; padding:16px 24px; border-radius:12px; font-weight:bold; box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:9999;`;
    toast.innerHTML = msg;
    document.body.appendChild(toast);
    setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 500); }, 3000);
}

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
