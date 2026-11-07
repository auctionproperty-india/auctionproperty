<?php
// ============================================================
// 🏠 Home Page – With Sub-Filters (Today, Upcoming) & Search Logic
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

blockBadBots();
checkRateLimit(30, 60);

if (!function_exists('safeDateFormat')) {
    function safeDateFormat($dateStr) {
        if (empty($dateStr) || strtotime($dateStr) === false) return 'N/A';
        return date('d M Y', strtotime($dateStr));
    }
}

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

$user_id = $_SESSION['user_id'] ?? null;
$show_images = userHasActiveSubscription($pdo, $user_id);

$all_types = ['Flat', 'Plot', 'Shop', 'Land', 'House', 'Land & Building', 'Car/Vehicle', 'Commercial', 'Office', 'Row House', 'Bungalow', 'Other'];

$search_city      = $_GET['city'] ?? '';
$search_locality  = $_GET['locality'] ?? '';
$search_types     = $_GET['type'] ?? [];
$search_min_price = $_GET['min_price'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';
$search_date      = $_GET['date']  ?? '';
$tab              = $_GET['tab'] ?? 'auction';
$time_filter      = $_GET['time_filter'] ?? 'today'; // Default today

// 🔥 Search Active check (Sirf tab true hoga jab user ne form submit kiya ho)
$is_search_active = (isset($_GET['city']) || isset($_GET['locality']) || isset($_GET['date']) || isset($_GET['type']) || isset($_GET['min_price']) || isset($_GET['max_price']));

if (!is_array($search_types)) {
    $search_types = $search_types === '' ? [] : [$search_types];
}
$search_types = array_values(array_filter($search_types, function($t) { return $t !== ''; }));

// 🔥 PAGINATION SETUP
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_auction = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available'")->fetchColumn();
$total_customer = $pdo->query("SELECT COUNT(*) FROM user_properties WHERE listing_type = 'sale' OR listing_type IS NULL")->fetchColumn();
$total_exchange = $pdo->query("SELECT COUNT(*) FROM user_properties WHERE status='approved' AND listing_type='exchange'")->fetchColumn();

// ---- Filters for `properties` table ----
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

// ---- Filters for `user_properties` table ----
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

// Fetch Properties Data
$auction_props = []; $customer_props = []; $exchange_props = []; $total_pages = 1;
$total_today_display = 0; $total_upcoming_display = 0;

if ($tab == 'auction') {
    $base_where = "status = 'available' $where_clause_props";
    
    // 🔥 FIX: अब सर्च करने पर भी Today/Upcoming का फिल्टर हटेगा नहीं
    if(!empty($search_date)) {
        $time_where = " AND auction_date = '" . $search_date . "'";
    } else {
        if ($time_filter == 'upcoming') {
            $time_where = " AND auction_date > CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty')";
        } else {
            $time_filter = 'today'; 
            $time_where = " AND (auction_date = CURRENT_DATE OR auction_start_time = 'Private Treaty')";
        }
    }

    // Counts for Pills
    $c_today = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE $base_where AND (auction_date = CURRENT_DATE OR auction_start_time = 'Private Treaty')");
    $c_today->execute($params_props);
    $total_today_display = $c_today->fetchColumn();

    $c_upcoming = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE $base_where AND auction_date > CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty')");
    $c_upcoming->execute($params_props);
    $total_upcoming_display = $c_upcoming->fetchColumn();

    // Main Pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE $base_where $time_where");
    $count_stmt->execute($params_props);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // Fetch Data
    $stmt = $pdo->prepare("SELECT *, 'auction' as source FROM properties WHERE $base_where $time_where ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params_props);
    $auction_props = $stmt->fetchAll();

} elseif ($tab == 'customer') {
    $customer_where = "status = 'approved' AND (listing_type = 'sale' OR listing_type IS NULL)";
    if(!empty($where_clause_cust)) $customer_where .= " AND " . $where_clause_cust;
    if(!empty($search_date)) $customer_where .= " AND DATE(created_at) = ?";

    $count_params = $params_cust; if(!empty($search_date)) $count_params[] = $search_date;
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_properties WHERE $customer_where");
    $count_stmt->execute($count_params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    $stmt = $pdo->prepare("SELECT *, 'customer' as source FROM user_properties WHERE $customer_where ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($count_params);
    $customer_props = $stmt->fetchAll();

} elseif ($tab == 'exchange') {
    $exchange_where = "status = 'approved' AND listing_type = 'exchange'";
    $ex_params = [];
    if(!empty($search_city)) { $exchange_where .= " AND (city ILIKE ? OR want_city ILIKE ?)"; $ex_params[] = '%'.$search_city.'%'; $ex_params[] = '%'.$search_city.'%'; }
    if(!empty($search_types)) { $ph = implode(',', array_fill(0, count($search_types), '?')); $exchange_where .= " AND (type IN ($ph) OR want_type IN ($ph))"; foreach ($search_types as $t) $ex_params[] = $t; foreach ($search_types as $t) $ex_params[] = $t; }
    if(!empty($search_min_price)) { $exchange_where .= " AND price >= ?"; $ex_params[] = (float)$search_min_price; }
    if(!empty($search_max_price)) { $exchange_where .= " AND price <= ?"; $ex_params[] = (float)$search_max_price; }
    if(!empty($search_date)) { $exchange_where .= " AND DATE(created_at) = ?"; $ex_params[] = $search_date; }

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_properties WHERE $exchange_where");
    $count_stmt->execute($ex_params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    $ex_stmt = $pdo->prepare("SELECT *, 'exchange' as source FROM user_properties WHERE $exchange_where ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $ex_stmt->execute($ex_params);
    $exchange_props = $ex_stmt->fetchAll();
}

function renderPropertyCard($prop, $show_images, $is_today = false) {
    global $pdo, $user_id;
    $gradients = [
        ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', 'text' => 'white'], ['bg' => 'linear-gradient(135deg, #1e3a5f 0%, #3b82f6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #064e3b 0%, #10b981 100%)', 'text' => 'white'], ['bg' => 'linear-gradient(135deg, #4c1d95 0%, #8b5cf6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #b91c1c 0%, #ef4444 100%)', 'text' => 'white'], ['bg' => 'linear-gradient(135deg, #78350f 0%, #f59e0b 100%)', 'text' => 'white'],
    ];
    $g = $gradients[array_rand($gradients)];
    $text_color = ($g['text'] == 'white') ? '#ffffff' : '#0f172a';
    $shadow = ($g['text'] == 'white') ? '0 4px 20px rgba(0,0,0,0.3)' : '0 4px 20px rgba(0,0,0,0.05)';
    $border = ($g['text'] == 'white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.05)';
    $image_url = ($prop['source'] == 'auction') ? ($prop['image_url'] ?? '') : ($prop['image_url'] ?? '');
    $has_city_access = canUserViewProperty($pdo, $user_id, $prop['city'] ?? '');
    $is_private_treaty = ($prop['source'] == 'auction' && isset($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty');
    ?>
    <div class="col-md-4 mb-4">
        <div class="property-card" style="position:relative; border-radius:24px; overflow:hidden; box-shadow:<?= $shadow ?>; height:100%; background: <?= $g['bg'] ?>; color:<?= $text_color ?>; transition:all 0.4s; border:1px solid <?= $border ?>;">
            <?php if($is_private_treaty): ?><div style="position:absolute; top:15px; right:15px; z-index:10; background:#f59e0b; color:#000; padding:4px 14px; border-radius:30px; font-size:0.7rem; font-weight:700;">🔑 Private Treaty</div><?php endif; ?>
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? ($prop['source']=='customer' ? 'Customer' : 'Bank')) ?></span>
                    <?php if($prop['source'] == 'auction'): ?>
                        <div style="text-align:right; line-height:1.3;">
                            <div style="font-size:0.6rem; opacity:0.6; color:<?= $text_color ?>; text-transform:uppercase;">Auction Date</div>
                            <div style="font-size:0.85rem; font-weight:700; color:<?= $text_color ?>;"><?= (!empty($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty') ? '🔑 Private Treaty' : (!empty($prop['auction_date']) ? date('d.m.Y', strtotime($prop['auction_date'])) : 'N/A') ?></div>
                        </div>
                    <?php elseif($prop['source'] == 'customer'): ?><span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;">📅 <?= safeDateFormat($prop['created_at']) ?></span><?php endif; ?>
                </div>
                <h5 style="font-size:1.2rem; font-weight:700; margin:12px 0 6px; color:<?= $text_color ?>;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:<?= $text_color ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?> <span style="font-size:0.9rem; font-weight:400; opacity:0.7;">Reserve</span></div>
                <div style="font-size:0.85rem; opacity:0.8; margin-top:6px; color:<?= $text_color ?>;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?><?php if(!empty($prop['locality'])): ?>&nbsp;•&nbsp; <i class="fas fa-location-dot"></i> <?= htmlspecialchars($prop['locality']) ?><?php endif; ?></div>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="property_detail.php?id=<?= $prop['id'] ?>&source=<?= $prop['source'] ?>" style="display:block; margin-top:16px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
                <?php else: ?><a href="login.php" class="btn btn-light w-100 mt-3" style="border-radius:16px; font-weight:600; color:#1e293b;">Login to View</a><?php endif; ?>
            </div>
            <?php if($show_images && $has_city_access && !empty($image_url)): ?>
                <img src="<?= htmlspecialchars($image_url) ?>" style="height:200px; width:100%; object-fit:cover; border-top:3px solid <?= $border ?>;" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:150px; background:rgba(255,255,255,0.08); display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(4px); border-top:3px solid <?= $border ?>; padding:10px;">
                    <i class="fas fa-lock" style="font-size:1.8rem; opacity:0.7; color:<?= $text_color ?>;"></i><span style="font-size:0.8rem; font-weight:600; margin-top:4px; color:<?= $text_color ?>;">🔒 Subscribe to unlock</span>
                    <?php if(!isset($_SESSION['user_id'])): ?><a href="login.php" class="btn btn-sm btn-light mt-2" style="border-radius:30px; font-weight:600; color:#1e293b;">Login</a>
                    <?php else: ?><a href="user_packages.php" class="btn btn-sm btn-warning mt-2" style="border-radius:30px; font-weight:600; color:#1e293b;">Subscribe Now</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function renderIndexPagination($total_pages, $current_page, $params) {
    if ($total_pages <= 1) return '';
    $html = '<nav aria-label="Page navigation" class="mt-5"><ul class="pagination justify-content-center flex-wrap">';
    $prev_page = max(1, $current_page - 1); $prev_link = '?' . http_build_query(array_merge($params, ['page' => $prev_page]));
    $html .= '<li class="page-item ' . ($current_page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . $prev_link . '">Previous</a></li>';
    $start = max(1, $current_page - 2); $end = min($total_pages, $current_page + 2);
    if ($start > 1) { $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($params, ['page' => 1])) . '">1</a></li>'; if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
    for ($i = $start; $i <= $end; $i++) { $html .= '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '"><a class="page-link" href="?' . http_build_query(array_merge($params, ['page' => $i])) . '">' . $i . '</a></li>'; }
    if ($end < $total_pages) { if ($end < $total_pages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>'; $html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($params, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>'; }
    $next_page = min($total_pages, $current_page + 1); $next_link = '?' . http_build_query(array_merge($params, ['page' => $next_page]));
    $html .= '<li class="page-item ' . ($current_page >= $total_pages ? 'disabled' : '') . '"><a class="page-link" href="' . $next_link . '">Next</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

$pagination_params = [
    'tab' => $tab, 'city' => $search_city, 'locality' => $search_locality, 'min_price' => $search_min_price, 'max_price' => $search_max_price, 'date' => $search_date
];
if (!empty($search_types)) $pagination_params['type'] = $search_types;
if ($tab == 'auction') $pagination_params['time_filter'] = $time_filter;
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Property India – Auction & Customer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6fa; color: #1e293b; }
        .search-box { background:#ffffff; padding:25px 30px; border-radius:30px; box-shadow:0 15px 40px -10px rgba(0,0,0,0.08); border:1px solid rgba(255,255,255,0.3); backdrop-filter:blur(10px); margin-bottom:40px; }
        .search-box .form-control { border:none; background:#f1f5f9; border-radius:20px; padding:12px 20px; font-size:0.95rem; }
        .search-box .form-control:focus { background:#e2e8f0; box-shadow:0 0 0 3px rgba(37,99,235,0.15); }
        .search-box .btn-primary { border-radius:30px; padding:12px 30px; background:linear-gradient(135deg, #1e3a8a, #2563eb); border:none; font-weight:600; transition:all 0.3s; }
        .search-box .btn-primary:hover { transform:scale(1.02); box-shadow:0 8px 25px rgba(37,99,235,0.3); }
        .search-box .btn-reset { border-radius:30px; padding:12px 20px; background:#f1f5f9; color:#475569; border:none; font-weight:600; }
        .search-box .btn-reset:hover { background:#e2e8f0; color:#1e293b; }
        .type-selector-wrapper { position: relative; }
        .type-selector-btn { width: 100%; border: none; background: #f1f5f9; border-radius: 20px; padding: 12px 20px; font-size: 0.95rem; text-align: left; cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; color: #1e293b; }
        .type-selector-btn:hover { background: #e2e8f0; }
        .type-panel { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #fff; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; padding: 10px; z-index: 100; max-height: 320px; overflow-y: auto; min-width: 260px; }
        .type-panel-header { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
        .type-panel-header button { background: none; border: none; color: #2563eb; font-size: 0.72rem; font-weight: 700; cursor: pointer; padding: 4px 8px; border-radius: 6px; }
        .type-panel-header button:hover { background: #eff6ff; }
        .type-checkbox-item { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; cursor: pointer; font-size: 0.88rem; font-weight: 500; transition: background 0.15s; margin: 0; }
        .type-checkbox-item:hover { background: #f8fafc; }
        .type-checkbox-item input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb; margin: 0; }
        .type-checkbox-item.checked { background: #eff6ff; font-weight: 700; color: #1e40af; }
        .section-title { font-weight:800; color:#0f172a; margin-bottom:20px; position:relative; }
        .section-title i { margin-right:10px; }
        .property-card:hover { transform:translateY(-10px); box-shadow:0 30px 60px -15px rgba(0,0,0,0.2) !important; }
        .nav-tabs .nav-link { font-weight:600; color:#475569; border: none; padding:12px 20px; position:relative; }
        .nav-tabs .nav-link.active { background: transparent; border-bottom: 3px solid #2563eb; color: #2563eb; }
        .nav-tabs .nav-link:hover { border-bottom: 3px solid #94a3b8; }
        .nav-tabs .nav-link .badge-count { background: #e2e8f0; color: #1e293b; font-size: 0.7rem; padding: 2px 10px; border-radius: 30px; margin-left: 8px; font-weight: 700; }
        .nav-tabs .nav-link.active .badge-count { background: #2563eb; color: #ffffff; }
        .no-auction-msg { background: #f8fafc; border-radius: 30px; padding: 30px; text-align: center; border: 2px dashed #e2e8f0; }
        .no-auction-msg i { font-size: 2.5rem; opacity:0.3; }
        .fallback-notice { background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 12px; padding: 12px 18px; margin-bottom: 20px; font-size: 0.88rem; color: #78350f; }
        @media (max-width:576px) { .search-box { padding:20px; } }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="search-box">
            <form method="GET" class="row g-3 align-items-center" id="searchForm">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="col-md-3"><input type="text" name="city" class="form-control" placeholder="🔍 Search by City..." value="<?= htmlspecialchars($search_city) ?>"></div>
                <div class="col-md-3"><input type="text" name="locality" class="form-control" placeholder="📍 Search by Locality / Area..." value="<?= htmlspecialchars($search_locality) ?>"></div>
                <div class="col-md-3">
                    <div class="type-selector-wrapper">
                        <button type="button" class="type-selector-btn" onclick="toggleTypePanel(event)">
                            <span id="typeLabel"><?php if (empty($search_types)) echo 'All Types'; elseif (count($search_types) === 1) echo htmlspecialchars($search_types[0]); else echo htmlspecialchars($search_types[0]) . ' +' . (count($search_types) - 1) . ' more'; ?></span>
                            <i class="fas fa-chevron-down" style="font-size:0.75rem;opacity:0.5;"></i>
                        </button>
                        <div class="type-panel" id="typePanel" style="display:none;">
                            <div class="type-panel-header"><span>Select Property Type</span><button type="button" onclick="clearAllTypes(event)">Clear All</button></div>
                            <?php foreach ($all_types as $t): $is_checked = in_array($t, $search_types); ?>
                                <label class="type-checkbox-item <?= $is_checked ? 'checked' : '' ?>" onclick="event.stopPropagation();">
                                    <input type="checkbox" name="type[]" value="<?= htmlspecialchars($t) ?>" <?= $is_checked ? 'checked' : '' ?> onchange="onTypeChange(this)"><span><?= htmlspecialchars($t) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3"><input type="date" name="date" class="form-control" value="<?= htmlspecialchars($search_date) ?>"></div>
                <div class="col-md-3"><input type="number" name="min_price" class="form-control" placeholder="💰 Min Price (₹)" value="<?= htmlspecialchars($search_min_price) ?>" min="0" step="1000"></div>
                <div class="col-md-3"><input type="number" name="max_price" class="form-control" placeholder="💰 Max Price (₹)" value="<?= htmlspecialchars($search_max_price) ?>" min="0" step="1000"></div>
                <div class="col-md-4"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Search Properties</button></div>
                <div class="col-md-2"><a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-reset w-100" title="Reset Filters"><i class="fas fa-redo"></i></a></div>
            </form>
            <?php if(!empty($search_city) || !empty($search_locality) || !empty($search_types) || !empty($search_min_price) || !empty($search_max_price) || !empty($search_date)): ?>
                <div class="mt-3 pt-3 border-top">
                    <span class="small text-muted me-2">Active Filters:</span>
                    <?php if(!empty($search_city)): ?><span class="badge bg-primary me-1">🏙️ <?= htmlspecialchars($search_city) ?></span><?php endif; ?>
                    <?php if(!empty($search_locality)): ?><span class="badge bg-info text-dark me-1">📍 <?= htmlspecialchars($search_locality) ?></span><?php endif; ?>
                    <?php if(!empty($search_types)): foreach($search_types as $t): ?><span class="badge bg-success me-1">🏠 <?= htmlspecialchars($t) ?></span><?php endforeach; endif; ?>
                    <?php if(!empty($search_min_price)): ?><span class="badge bg-warning text-dark me-1">Min ₹<?= indianCurrencyFormat($search_min_price) ?></span><?php endif; ?>
                    <?php if(!empty($search_max_price)): ?><span class="badge bg-warning text-dark me-1">Max ₹<?= indianCurrencyFormat($search_max_price) ?></span><?php endif; ?>
                    <?php if(!empty($search_date)): ?><span class="badge bg-danger me-1">📅 <?= htmlspecialchars($search_date) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item"><a class="nav-link <?= ($tab=='auction')?'active':'' ?>" href="?<?= http_build_query(array_merge($pagination_params, ['tab' => 'auction', 'page' => 1])) ?>"><i class="fas fa-gavel me-2"></i>Auction Properties <span class="badge-count"><?= $total_auction ?></span></a></li>
            <li class="nav-item"><a class="nav-link <?= ($tab=='customer')?'active':'' ?>" href="?<?= http_build_query(array_merge($pagination_params, ['tab' => 'customer', 'page' => 1])) ?>"><i class="fas fa-home me-2"></i>Customer Properties <span class="badge-count"><?= $total_customer ?></span></a></li>
            <li class="nav-item"><a class="nav-link <?= ($tab=='exchange')?'active':'' ?>" href="?<?= http_build_query(array_merge($pagination_params, ['tab' => 'exchange', 'page' => 1])) ?>"><i class="fas fa-exchange-alt me-2"></i>Exchange (All India) <span class="badge-count"><?= $total_exchange ?></span></a></li>
        </ul>

        <?php if($tab == 'auction'): ?>
            
            <!-- 🔥 Sub-Filters (Today, Upcoming) - अब हमेशा दिखेंगे -->
            <div class="d-flex gap-2 mb-4 flex-wrap">
                <a href="?<?= http_build_query(array_merge($pagination_params, ['time_filter'=>'today', 'page'=>1])) ?>" class="btn <?= $time_filter=='today' ? 'btn-danger' : 'btn-outline-danger' ?> fw-bold rounded-pill px-4 shadow-sm">
                    <i class="fas fa-bolt me-1"></i> Today's (<?= $total_today_display ?>)
                </a>
                <a href="?<?= http_build_query(array_merge($pagination_params, ['time_filter'=>'upcoming', 'page'=>1])) ?>" class="btn <?= $time_filter=='upcoming' ? 'btn-primary' : 'btn-outline-primary' ?> fw-bold rounded-pill px-4 shadow-sm">
                    <i class="fas fa-clock me-1"></i> Upcoming (<?= $total_upcoming_display ?>)
                </a>
            </div>

            <!-- Grid -->
            <?php if(count($auction_props) > 0): ?>
                <div class="row">
                    <?php foreach($auction_props as $prop): ?>
                        <?= renderPropertyCard($prop, $show_images, $time_filter == 'today'); ?>
                    <?php endforeach; ?>
                </div>
                <?= renderIndexPagination($total_pages, $page, $pagination_params) ?>
            <?php else: ?>
                <div class="no-auction-msg"><i class="fas fa-calendar-times"></i><p class="mt-2 fw-bold">📭 No properties found</p></div>
            <?php endif; ?>

        <?php elseif($tab == 'customer'): ?>
            <div class="section-title"><i class="fas fa-home" style="color:#10b981;"></i> Customer Properties <span class="badge bg-primary rounded-pill ms-2"><?= count($customer_props) ?></span></div>
            <?php if(count($customer_props) > 0): ?>
                <div class="row"><?php foreach($customer_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>
                <?= renderIndexPagination($total_pages, $page, $pagination_params) ?>
            <?php else: ?>
                <div class="no-auction-msg"><i class="fas fa-home"></i><p class="mt-2 fw-bold">🏠 No customer properties found</p><?php if(isset($_SESSION['user_id']) && $_SESSION['role'] != 'admin'): ?><a href="add_user_property.php" class="btn btn-primary mt-2">Add Your Property</a><?php endif; ?></div>
            <?php endif; ?>

        <?php elseif($tab == 'exchange'): ?>
            <div class="section-title"><i class="fas fa-exchange-alt" style="color:#8b5cf6;"></i> Exchange Properties (All India) <span class="badge bg-primary rounded-pill ms-2"><?= count($exchange_props) ?></span></div>
            <?php if(count($exchange_props) > 0): ?>
                <div class="row">
                    <?php foreach($exchange_props as $prop): ?>
                        <div class="col-md-4 mb-4">
                            <div class="exchange-card" style="border-radius:20px;background:linear-gradient(135deg,#4c1d95 0%,#7c3aed 100%);color:#fff;padding:22px;height:100%;box-shadow:0 10px 30px rgba(76,29,149,0.3);position:relative;overflow:hidden;">
                                <div style="position:absolute;top:14px;right:14px;background:#fbbf24;color:#000;padding:3px 12px;border-radius:30px;font-size:0.7rem;font-weight:700;">🔄 EXCHANGE</div>
                                <div style="font-size:0.7rem;text-transform:uppercase;opacity:0.7;letter-spacing:0.5px;"><i class="fas fa-user"></i> By <?= htmlspecialchars($prop['city'] ?: 'User') ?></div>
                                <h5 style="font-weight:700;margin:10px 0 4px;"><?= htmlspecialchars($prop['title']) ?></h5>
                                <div style="background:rgba(255,255,255,0.12);border-radius:12px;padding:12px;margin-top:12px;">
                                    <div style="font-size:0.7rem;text-transform:uppercase;opacity:0.75;font-weight:700;">⬆️ दे रहा है</div>
                                    <div style="font-weight:600;margin-top:4px;"><?= htmlspecialchars($prop['type']) ?> — ₹<?= indianCurrencyFormat($prop['price']) ?></div>
                                    <div style="font-size:0.85rem;opacity:0.85;">📍 <?= htmlspecialchars($prop['city']) ?><?= $prop['state'] ? ', '.htmlspecialchars($prop['state']) : '' ?></div>
                                </div>
                                <div style="background:rgba(255,255,255,0.12);border-radius:12px;padding:12px;margin-top:8px;">
                                    <div style="font-size:0.7rem;text-transform:uppercase;opacity:0.75;font-weight:700;">⬇️ चाहता है</div>
                                    <div style="font-weight:600;margin-top:4px;"><?= htmlspecialchars($prop['want_type'] ?: 'Any') ?> <?= $prop['want_budget'] ? '— ₹'.indianCurrencyFormat($prop['want_budget']) : '' ?></div>
                                    <div style="font-size:0.85rem;opacity:0.85;">📍 <?= htmlspecialchars($prop['want_city'] ?: 'All India') ?><?= $prop['want_state'] ? ', '.htmlspecialchars($prop['want_state']) : '' ?></div>
                                </div>
                                <?php if(isset($_SESSION['user_id'])): ?><a href="exchange_interest.php?id=<?= $prop['id'] ?>" class="btn w-100 mt-3" style="background:#fbbf24;color:#000;font-weight:700;border-radius:14px;" onclick="return confirm('Owner को notification भेजें? वो आपसे contact करेंगे।')">🤝 I'm Interested</a>
                                <?php else: ?><a href="login.php" class="btn w-100 mt-3" style="background:#fbbf24;color:#000;font-weight:700;border-radius:14px;">Login to Contact</a><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?= renderIndexPagination($total_pages, $page, $pagination_params) ?>
            <?php else: ?>
                <div class="no-auction-msg"><i class="fas fa-exchange-alt"></i><p class="mt-2 fw-bold">🔄 No exchange properties found</p></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleTypePanel(e) { e.stopPropagation(); const panel = document.getElementById('typePanel'); panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none'; }
    function onTypeChange(checkbox) { const label = checkbox.closest('.type-checkbox-item'); if (checkbox.checked) label.classList.add('checked'); else label.classList.remove('checked'); updateTypeLabel(); }
    function updateTypeLabel() { const checked = document.querySelectorAll('#typePanel input[type="checkbox"]:checked'); const labelEl = document.getElementById('typeLabel'); if (checked.length === 0) labelEl.textContent = 'All Types'; else if (checked.length === 1) labelEl.textContent = checked[0].value; else labelEl.textContent = checked[0].value + ' +' + (checked.length - 1) + ' more'; }
    function clearAllTypes(e) { e.stopPropagation(); document.querySelectorAll('#typePanel input[type="checkbox"]').forEach(cb => { cb.checked = false; cb.closest('.type-checkbox-item').classList.remove('checked'); }); updateTypeLabel(); }
    document.addEventListener('click', function(e) { const panel = document.getElementById('typePanel'); const wrapper = document.querySelector('.type-selector-wrapper'); if (panel && wrapper && !wrapper.contains(e.target)) panel.style.display = 'none'; });
    document.getElementById('typePanel').addEventListener('click', function(e) { e.stopPropagation(); });
    updateTypeLabel();
    </script>
</body>
</html>
