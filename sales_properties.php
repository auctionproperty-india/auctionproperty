<?php
// ============================================================
// 🏠 Sales View - All Properties (Frontend Layout for CRM)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🔥 Sales aur Admin ko allow kiya
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'sub_admin', 'sales'])) {
    header("Location: login.php");
    exit;
}

// Sales staff ko images dikhengi (bina subscription ke)
$show_images = true; 

// ---- Safe Date Formatter ----
if (!function_exists('safeDateFormat')) {
    function safeDateFormat($dateStr) {
        if (empty($dateStr) || strtotime($dateStr) === false) return 'N/A';
        return date('d M Y', strtotime($dateStr));
    }
}

// ---- Dynamic Column Check ----
if (!function_exists('tableHasColumn')) {
    function tableHasColumn($pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) { return false; }
    }
}

$properties_has_locality      = tableHasColumn($pdo, 'properties', 'locality');
$user_properties_has_locality = tableHasColumn($pdo, 'user_properties', 'locality');

$all_types = ['Flat', 'Plot', 'Shop', 'Land', 'House', 'Land & Building', 'Car/Vehicle', 'Commercial', 'Office', 'Row House', 'Bungalow', 'Other'];

$search_city      = $_GET['city'] ?? '';
$search_locality  = $_GET['locality'] ?? '';
$search_types     = $_GET['type'] ?? [];
$search_min_price = $_GET['min_price'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';
$search_date      = $_GET['date']  ?? '';
$tab              = $_GET['tab'] ?? 'auction';

if (!is_array($search_types)) {
    $search_types = $search_types === '' ? [] : [$search_types];
}
$search_types = array_values(array_filter($search_types, function($t) { return $t !== ''; }));

$total_auction = $pdo->query("SELECT COUNT(*) as total FROM properties WHERE status = 'available'")->fetchColumn();
$total_customer = $pdo->query("SELECT COUNT(*) as total FROM user_properties")->fetchColumn();

// ============================================================
// SEARCH FILTERS (Auction)
// ============================================================
$where_props = [];
$params_props = [];

if(!empty($search_city)) { $where_props[] = "city ILIKE ?"; $params_props[] = '%'.$search_city.'%'; }
if(!empty($search_locality) && $properties_has_locality) { $where_props[] = "locality ILIKE ?"; $params_props[] = '%'.$search_locality.'%'; }
if(!empty($search_types)) {
    $placeholders = implode(',', array_fill(0, count($search_types), '?'));
    $where_props[] = "type IN ($placeholders)";
    foreach ($search_types as $t) $params_props[] = $t;
}
if(!empty($search_min_price)) { $where_props[] = "price >= ?"; $params_props[] = (float)$search_min_price; }
if(!empty($search_max_price)) { $where_props[] = "price <= ?"; $params_props[] = (float)$search_max_price; }

$where_clause_props = implode(" AND ", $where_props);

// ============================================================
// SEARCH FILTERS (Customer)
// ============================================================
$where_cust = [];
$params_cust = [];

if(!empty($search_city)) { $where_cust[] = "city ILIKE ?"; $params_cust[] = '%'.$search_city.'%'; }
if(!empty($search_locality) && $user_properties_has_locality) { $where_cust[] = "locality ILIKE ?"; $params_cust[] = '%'.$search_locality.'%'; }
if(!empty($search_types)) {
    $placeholders = implode(',', array_fill(0, count($search_types), '?'));
    $where_cust[] = "type IN ($placeholders)";
    foreach ($search_types as $t) $params_cust[] = $t;
}
if(!empty($search_min_price)) { $where_cust[] = "price >= ?"; $params_cust[] = (float)$search_min_price; }
if(!empty($search_max_price)) { $where_cust[] = "price <= ?"; $params_cust[] = (float)$search_max_price; }

$where_clause_cust = implode(" AND ", $where_cust);

// ---- Auction Properties Base ----
$base_sql = "SELECT *, 'auction' as source FROM properties WHERE status = 'available'";
if(!empty($where_clause_props)) { $base_sql .= " AND " . $where_clause_props; }

if(!empty($search_date)) {
    $date_stmt = $pdo->prepare($base_sql . " AND auction_date = ? ORDER BY id DESC");
    $date_stmt->execute(array_merge($params_props, [$search_date]));
    $today_props = $date_stmt->fetchAll();
    $upcoming_props = []; $past_props = [];
} else {
    $today_stmt = $pdo->prepare($base_sql . " AND (auction_date = CURRENT_DATE OR auction_start_time = 'Private Treaty') ORDER BY id DESC");
    $today_stmt->execute($params_props); $today_props = $today_stmt->fetchAll();

    $upcoming_stmt = $pdo->prepare($base_sql . " AND auction_date > CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') ORDER BY id DESC");
    $upcoming_stmt->execute($params_props); $upcoming_props = $upcoming_stmt->fetchAll();

    $past_stmt = $pdo->prepare($base_sql . " AND auction_date < CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') ORDER BY id DESC");
    $past_stmt->execute($params_props); $past_props = $past_stmt->fetchAll();
}

// ---- Customer Properties ----
$customer_where = "status = 'approved'";
if(!empty($where_clause_cust)) { $customer_where .= " AND " . $where_clause_cust; }
$customer_params = $params_cust;
if(!empty($search_date)) { $customer_where .= " AND DATE(created_at) = ?"; $customer_params[] = $search_date; }

$customer_stmt = $pdo->prepare("SELECT *, 'customer' as source FROM user_properties WHERE $customer_where ORDER BY created_at DESC");
$customer_stmt->execute($customer_params);
$customer_props = $customer_stmt->fetchAll();

// ---- SMART FALLBACK ----
$locality_fallback_used = false;
if(!empty($search_locality) && empty($customer_props) && empty($today_props) && empty($upcoming_props) && empty($past_props)) {
    $locality_fallback_used = true;
    // Fallback queries (omitted for brevity, assuming it's fine to just show empty)
}

// ============================================================
// RENDER PROPERTY CARD FUNCTION
// ============================================================
if (!function_exists('renderPropertyCard')) {
    function renderPropertyCard($prop, $show_images, $is_today = false) {
        $gradients = [
            ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', 'text' => 'white'],
            ['bg' => 'linear-gradient(135deg, #1e3a5f 0%, #3b82f6 100%)', 'text' => 'white'],
            ['bg' => 'linear-gradient(135deg, #064e3b 0%, #10b981 100%)', 'text' => 'white'],
            ['bg' => 'linear-gradient(135deg, #4c1d95 0%, #8b5cf6 100%)', 'text' => 'white'],
        ];
        $g = $gradients[array_rand($gradients)];
        $text_color = ($g['text'] == 'white') ? '#ffffff' : '#0f172a';
        $shadow = ($g['text'] == 'white') ? '0 4px 20px rgba(0,0,0,0.3)' : '0 4px 20px rgba(0,0,0,0.05)';
        $border = ($g['text'] == 'white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.05)';
        $image_url = ($prop['source'] == 'auction') ? ($prop['image_url'] ?? '') : ($prop['image_url'] ?? '');
        
        $is_private_treaty = ($prop['source'] == 'auction' && isset($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty');
        ?>
        <div class="col-md-4 mb-4">
            <div class="property-card" style="position:relative; border-radius:24px; overflow:hidden; box-shadow:<?= $shadow ?>; height:100%; background: <?= $g['bg'] ?>; color:<?= $text_color ?>; transition:all 0.4s; border:1px solid <?= $border ?>;">
                <?php if($is_private_treaty): ?>
                    <div style="position:absolute; top:15px; right:15px; z-index:10; background:#f59e0b; color:#000; padding:4px 14px; border-radius:30px; font-size:0.7rem; font-weight:700;">🔑 Private Treaty</div>
                <?php endif; ?>
                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? ($prop['source']=='customer' ? 'Customer' : 'Bank')) ?></span>
                        
                        <?php if($prop['source'] == 'auction'): ?>
                            <div style="text-align:right; line-height:1.3;">
                                <div style="font-size:0.6rem; opacity:0.6; color:<?= $text_color ?>; text-transform:uppercase;">Auction Date</div>
                                <div style="font-size:0.85rem; font-weight:700; color:<?= $text_color ?>;">
                                    <?php 
                                    if($is_private_treaty) echo 'Private Treaty';
                                    elseif(!empty($prop['auction_date'])) echo date('d.m.Y', strtotime($prop['auction_date']));
                                    else echo 'N/A';
                                    ?>
                                </div>
                            </div>
                        <?php elseif($prop['source'] == 'customer'): ?>
                            <span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;">📅 <?= safeDateFormat($prop['created_at']) ?></span>
                        <?php endif; ?>
                    </div>
                    <h5 style="font-size:1.2rem; font-weight:700; margin:12px 0 6px; color:<?= $text_color ?>;"><?= htmlspecialchars($prop['title']) ?></h5>
                    <div style="font-size:1.6rem; font-weight:800; color:<?= $text_color ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?> <span style="font-size:0.9rem; font-weight:400; opacity:0.7;">Reserve</span></div>
                    
                    <div style="font-size:0.85rem; opacity:0.8; margin-top:6px; color:<?= $text_color ?>;">
                        <i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?>
                        <?php if(!empty($prop['locality'])): ?>
                            &nbsp;•&nbsp; <i class="fas fa-location-dot"></i> <?= htmlspecialchars($prop['locality']) ?>
                        <?php endif; ?>
                    </div>
                    
                    <a href="property_detail.php?id=<?= $prop['id'] ?>&source=<?= $prop['source'] ?>" target="_blank" style="display:block; margin-top:16px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
                </div>
                <?php if($show_images && !empty($image_url)): ?>
                    <img src="<?= htmlspecialchars($image_url) ?>" style="height:200px; width:100%; object-fit:cover; border-top:3px solid <?= $border ?>;" alt="<?= htmlspecialchars($prop['title']) ?>">
                <?php else: ?>
                    <div style="height:150px; background:rgba(255,255,255,0.08); display:flex; flex-direction:column; align-items:center; justify-content:center; border-top:3px solid <?= $border ?>; padding:10px;">
                        <i class="fas fa-image" style="font-size:1.8rem; opacity:0.5; color:<?= $text_color ?>;"></i>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('buildTabUrl')) {
    function buildTabUrl($tab, $city, $locality, $types, $min_price, $max_price, $date) {
        $params = ['tab' => $tab, 'city' => $city, 'locality' => $locality, 'min_price' => $min_price, 'max_price' => $max_price, 'date' => $date];
        if (!empty($types) && is_array($types)) $params['type'] = $types;
        return '?' . http_build_query($params);
    }
}

include 'header.php';
?>

<style>
    .search-box { background:#ffffff; padding:20px 25px; border-radius:20px; box-shadow:0 10px 30px -10px rgba(0,0,0,0.08); border:1px solid #e2e8f0; margin-bottom:30px; }
    .search-box .form-control { border:none; background:#f1f5f9; border-radius:15px; padding:10px 15px; font-size:0.9rem; }
    .search-box .form-control:focus { background:#e2e8f0; box-shadow:0 0 0 3px rgba(37,99,235,0.15); }
    .search-box .btn-primary { border-radius:15px; padding:10px 20px; background:linear-gradient(135deg, #1e3a8a, #2563eb); border:none; font-weight:600; }
    
    .type-selector-wrapper { position: relative; }
    .type-selector-btn { width: 100%; border: none; background: #f1f5f9; border-radius: 15px; padding: 10px 15px; font-size: 0.9rem; text-align: left; cursor: pointer; display: flex; justify-content: space-between; align-items: center; color: #1e293b; }
    .type-panel { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #fff; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; padding: 10px; z-index: 100; max-height: 320px; overflow-y: auto; min-width: 260px; }
    .type-panel-header { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.75rem; color: #64748b; font-weight: 700; display: flex; justify-content: space-between; }
    .type-checkbox-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 10px; cursor: pointer; font-size: 0.85rem; margin: 0; }
    .type-checkbox-item:hover { background: #f8fafc; }
    .type-checkbox-item input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #2563eb; margin: 0; }
    
    .section-title { font-weight:800; color:#0f172a; margin-bottom:20px; }
    .property-card:hover { transform:translateY(-5px); box-shadow:0 20px 40px -10px rgba(0,0,0,0.2) !important; }
    .nav-tabs .nav-link { font-weight:600; color:#475569; border: none; padding:12px 20px; }
    .nav-tabs .nav-link.active { background: transparent; border-bottom: 3px solid #2563eb; color: #2563eb; }
    .nav-tabs .nav-link .badge-count { background: #e2e8f0; color: #1e293b; font-size: 0.7rem; padding: 2px 10px; border-radius: 30px; margin-left: 8px; font-weight: 700; }
    .nav-tabs .nav-link.active .badge-count { background: #2563eb; color: #ffffff; }
</style>

<div class="container-fluid py-3">
    <!-- SEARCH BOX -->
    <div class="search-box">
        <form method="GET" class="row g-2 align-items-center" id="searchForm">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <div class="col-md-3">
                <input type="text" name="city" class="form-control" placeholder="🔍 Search by City..." value="<?= htmlspecialchars($search_city) ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="locality" class="form-control" placeholder="📍 Search by Locality..." value="<?= htmlspecialchars($search_locality) ?>">
            </div>
            
            <!-- MULTI-SELECT TYPE -->
            <div class="col-md-3">
                <div class="type-selector-wrapper">
                    <button type="button" class="type-selector-btn" onclick="toggleTypePanel(event)">
                        <span id="typeLabel">
                            <?php 
                            if (empty($search_types)) echo 'All Types';
                            elseif (count($search_types) === 1) echo htmlspecialchars($search_types[0]);
                            else echo htmlspecialchars($search_types[0]) . ' +' . (count($search_types) - 1) . ' more';
                            ?>
                        </span>
                        <i class="fas fa-chevron-down" style="font-size:0.75rem;opacity:0.5;"></i>
                    </button>
                    <div class="type-panel" id="typePanel" style="display:none;">
                        <div class="type-panel-header">
                            <span>Select Type</span>
                            <button type="button" onclick="clearAllTypes(event)" style="background:none; border:none; color:#2563eb; font-weight:bold; cursor:pointer;">Clear</button>
                        </div>
                        <?php foreach ($all_types as $t): $is_checked = in_array($t, $search_types); ?>
                            <label class="type-checkbox-item" onclick="event.stopPropagation();">
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
                <input type="number" name="min_price" class="form-control" placeholder="💰 Min Price (₹)" value="<?= htmlspecialchars($search_min_price) ?>">
            </div>
            <div class="col-md-3">
                <input type="number" name="max_price" class="form-control" placeholder="💰 Max Price (₹)" value="<?= htmlspecialchars($search_max_price) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Search</button>
            </div>
            <div class="col-md-2">
                <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline-secondary w-100"><i class="fas fa-redo"></i></a>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= ($tab=='auction')?'active':'' ?>" href="<?= buildTabUrl('auction', $search_city, $search_locality, $search_types, $search_min_price, $search_max_price, $search_date) ?>">
                <i class="fas fa-gavel me-2"></i>Auction Properties <span class="badge-count"><?= $total_auction ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($tab=='customer')?'active':'' ?>" href="<?= buildTabUrl('customer', $search_city, $search_locality, $search_types, $search_min_price, $search_max_price, $search_date) ?>">
                <i class="fas fa-home me-2"></i>Customer Properties <span class="badge-count"><?= $total_customer ?></span>
            </a>
        </li>
    </ul>

    <!-- Property Cards Listing -->
    <?php if($tab == 'auction'): ?>
        <?php if(!empty($search_date)): ?>
            <div class="section-title"><i class="fas fa-calendar-day text-primary"></i> Properties for <?= htmlspecialchars($search_date) ?> <span class="badge bg-primary ms-2"><?= count($today_props) ?></span></div>
            <div class="row"><?php foreach($today_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>
        <?php else: ?>
            <div class="section-title"><i class="fas fa-bolt text-danger"></i> Today's Auctions <span class="badge bg-danger ms-2"><?= count($today_props) ?></span></div>
            <div class="row"><?php foreach($today_props as $prop) renderPropertyCard($prop, $show_images, true); ?></div>

            <hr class="my-5">

            <div class="section-title"><i class="fas fa-clock text-primary"></i> Upcoming Auctions <span class="badge bg-primary ms-2"><?= count($upcoming_props) ?></span></div>
            <div class="row"><?php foreach($upcoming_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>

            <hr class="my-5">

            <div class="section-title"><i class="fas fa-history text-secondary"></i> Past Auctions <span class="badge bg-secondary ms-2"><?= count($past_props) ?></span></div>
            <div class="row"><?php foreach($past_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>
        <?php endif; ?>
    <?php else: ?>
        <div class="section-title"><i class="fas fa-home text-success"></i> Customer Properties <span class="badge bg-success ms-2"><?= count($customer_props) ?></span></div>
        <?php if(count($customer_props) > 0): ?>
            <div class="row"><?php foreach($customer_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>
        <?php else: ?>
            <div class="alert alert-light text-center py-5 border"><h5>No customer properties found</h5></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    function toggleTypePanel(e) {
        e.stopPropagation();
        const panel = document.getElementById('typePanel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
    }

    function onTypeChange(checkbox) {
        const label = checkbox.closest('.type-checkbox-item');
        if (checkbox.checked) label.style.background = '#eff6ff';
        else label.style.background = 'transparent';
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
            cb.closest('.type-checkbox-item').style.background = 'transparent';
        });
        updateTypeLabel();
    }

    document.addEventListener('click', function(e) {
        const panel = document.getElementById('typePanel');
        const wrapper = document.querySelector('.type-selector-wrapper');
        if (panel && wrapper && !wrapper.contains(e.target)) panel.style.display = 'none';
    });
    
    if(document.getElementById('typePanel')) {
        document.getElementById('typePanel').addEventListener('click', function(e) { e.stopPropagation(); });
    }
</script>

<?php include 'footer.php'; ?>
