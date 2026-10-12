<?php
// ============================================================
// 🏠 Home Page – With Private Treaty + Date + Min/Max Price + Locality + Multi-Type
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/functions.php';

// ---- Safe Date Formatter ----
if (!function_exists('safeDateFormat')) {
    function safeDateFormat($dateStr) {
        if (empty($dateStr) || strtotime($dateStr) === false) {
            return 'N/A';
        }
        return date('d M Y', strtotime($dateStr));
    }
}

// ============================================================
// 🔥 DYNAMIC COLUMN CHECK
// ============================================================
if (!function_exists('tableHasColumn')) {
    function tableHasColumn($pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.columns 
                WHERE table_name = ? AND column_name = ?
            ");
            $stmt->execute([$table, $column]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

$properties_has_locality      = tableHasColumn($pdo, 'properties', 'locality');
$user_properties_has_locality = tableHasColumn($pdo, 'user_properties', 'locality');

$user_id = $_SESSION['user_id'] ?? null;
$show_images = userHasActiveSubscription($pdo, $user_id);

// ============================================================
// 🔥 All Property Types (Land & Building included)
// ============================================================
$all_types = [
    'Flat',
    'Plot',
    'Shop',
    'Land',
    'House',
    'Land & Building',   // 🔥 NEW
    'Car/Vehicle',
    'Commercial',
    'Office',
    'Row House',
    'Bungalow',
    'Other',
];

// 🔥 Search parameters
$search_city      = $_GET['city'] ?? '';
$search_locality  = $_GET['locality'] ?? '';
$search_types     = $_GET['type'] ?? [];        // 🔥 now array
$search_min_price = $_GET['min_price'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';
$search_date      = $_GET['date']  ?? '';
$tab              = $_GET['tab'] ?? 'auction';

// 🔥 Backward compat: अगर string आया तो array बनाओ
if (!is_array($search_types)) {
    $search_types = $search_types === '' ? [] : [$search_types];
}
// Empty values हटाओ
$search_types = array_values(array_filter($search_types, function($t) { return $t !== ''; }));

// ============================================================
// 🔥 TOTAL COUNTS
// ============================================================
$total_auction_stmt = $pdo->query("SELECT COUNT(*) as total FROM properties WHERE status = 'available'");
$total_auction = $total_auction_stmt->fetchColumn();

$total_customer_stmt = $pdo->query("SELECT COUNT(*) as total FROM user_properties");
$total_customer = $total_customer_stmt->fetchColumn();

// ============================================================
// SEARCH FILTERS
// ============================================================

// ---- Filters for `properties` table ----
$where_props = [];
$params_props = [];

if(!empty($search_city)) {
    $where_props[] = "city ILIKE ?";
    $params_props[] = '%'.$search_city.'%';
}

if(!empty($search_locality) && $properties_has_locality) {
    $where_props[] = "locality ILIKE ?";
    $params_props[] = '%'.$search_locality.'%';
}

// 🔥 MULTI-TYPE: IN clause
if(!empty($search_types)) {
    $placeholders = implode(',', array_fill(0, count($search_types), '?'));
    $where_props[] = "type IN ($placeholders)";
    foreach ($search_types as $t) $params_props[] = $t;
}

if(!empty($search_min_price)) {
    $where_props[] = "price >= ?";
    $params_props[] = (float)$search_min_price;
}

if(!empty($search_max_price)) {
    $where_props[] = "price <= ?";
    $params_props[] = (float)$search_max_price;
}

$where_clause_props = implode(" AND ", $where_props);

// ---- Filters for `user_properties` table ----
$where_cust = [];
$params_cust = [];

if(!empty($search_city)) {
    $where_cust[] = "city ILIKE ?";
    $params_cust[] = '%'.$search_city.'%';
}

if(!empty($search_locality) && $user_properties_has_locality) {
    $where_cust[] = "locality ILIKE ?";
    $params_cust[] = '%'.$search_locality.'%';
}

if(!empty($search_types)) {
    $placeholders = implode(',', array_fill(0, count($search_types), '?'));
    $where_cust[] = "type IN ($placeholders)";
    foreach ($search_types as $t) $params_cust[] = $t;
}

if(!empty($search_min_price)) {
    $where_cust[] = "price >= ?";
    $params_cust[] = (float)$search_min_price;
}

if(!empty($search_max_price)) {
    $where_cust[] = "price <= ?";
    $params_cust[] = (float)$search_max_price;
}

$where_clause_cust = implode(" AND ", $where_cust);

// ---- Auction Properties ----
$base_sql = "SELECT *, 'auction' as source FROM properties WHERE status = 'available'";
if(!empty($where_clause_props)) {
    $base_sql .= " AND " . $where_clause_props;
}

// ============================================================
// Split into Today / Upcoming / Past
// ============================================================
if(!empty($search_date)) {
    $date_sql = $base_sql . " AND auction_date = ? ORDER BY id DESC";
    $date_stmt = $pdo->prepare($date_sql);
    $date_stmt->execute(array_merge($params_props, [$search_date]));
    $today_props = $date_stmt->fetchAll();
    $upcoming_props = [];
    $past_props = [];
} else {
    $today_sql = $base_sql . " AND (auction_date = CURRENT_DATE OR auction_start_time = 'Private Treaty') ORDER BY id DESC";
    $today_stmt = $pdo->prepare($today_sql);
    $today_stmt->execute($params_props);
    $today_props = $today_stmt->fetchAll();

    $upcoming_sql = $base_sql . " AND auction_date > CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') ORDER BY id DESC";
    $upcoming_stmt = $pdo->prepare($upcoming_sql);
    $upcoming_stmt->execute($params_props);
    $upcoming_props = $upcoming_stmt->fetchAll();

    $past_sql = $base_sql . " AND auction_date < CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') ORDER BY id DESC";
    $past_stmt = $pdo->prepare($past_sql);
    $past_stmt->execute($params_props);
    $past_props = $past_stmt->fetchAll();
}

// ---- Customer Properties ----
$customer_where = "status = 'approved'";
if(!empty($where_clause_cust)) {
    $customer_where .= " AND " . $where_clause_cust;
}

$customer_params = $params_cust;
if(!empty($search_date)) {
    $customer_where .= " AND DATE(created_at) = ?";
    $customer_params[] = $search_date;
}

$customer_sql = "SELECT *, 'customer' as source FROM user_properties WHERE $customer_where ORDER BY created_at DESC";
$customer_stmt = $pdo->prepare($customer_sql);
$customer_stmt->execute($customer_params);
$customer_props = $customer_stmt->fetchAll();

// ============================================================
// 🔥 SMART FALLBACK
// ============================================================
$locality_fallback_used = false;

if(!empty($search_locality) && empty($customer_props) && empty($today_props) && empty($upcoming_props) && empty($past_props)) {
    $locality_fallback_used = true;

    // Properties retry
    $where_props_fb = array_filter($where_props, function($w) { return strpos($w, 'locality') === false; });
    $params_props_fb = [];
    if(!empty($search_city)) $params_props_fb[] = '%'.$search_city.'%';
    if(!empty($search_types)) foreach ($search_types as $t) $params_props_fb[] = $t;
    if(!empty($search_min_price)) $params_props_fb[] = (float)$search_min_price;
    if(!empty($search_max_price)) $params_props_fb[] = (float)$search_max_price;

    $fb_where = implode(" AND ", $where_props_fb);
    $fb_sql = "SELECT *, 'auction' as source FROM properties WHERE status = 'available'";
    if(!empty($fb_where)) $fb_sql .= " AND " . $fb_where;

    if(!empty($search_date)) {
        $fb_sql .= " AND auction_date = ? ORDER BY id DESC";
        $fb_stmt = $pdo->prepare($fb_sql);
        $fb_stmt->execute(array_merge($params_props_fb, [$search_date]));
        $today_props = $fb_stmt->fetchAll();
    } else {
        $fb_stmt = $pdo->prepare($fb_sql . " AND (auction_date = CURRENT_DATE OR auction_start_time = 'Private Treaty') ORDER BY id DESC");
        $fb_stmt->execute($params_props_fb);
        $today_props = $fb_stmt->fetchAll();

        $fb_stmt = $pdo->prepare($fb_sql . " AND auction_date > CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') ORDER BY id DESC");
        $fb_stmt->execute($params_props_fb);
        $upcoming_props = $fb_stmt->fetchAll();

        $fb_stmt = $pdo->prepare($fb_sql . " AND auction_date < CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') ORDER BY id DESC");
        $fb_stmt->execute($params_props_fb);
        $past_props = $fb_stmt->fetchAll();
    }

    // Customer retry
    $where_cust_fb = array_filter($where_cust, function($w) { return strpos($w, 'locality') === false; });
    $params_cust_fb = [];
    if(!empty($search_city)) $params_cust_fb[] = '%'.$search_city.'%';
    if(!empty($search_types)) foreach ($search_types as $t) $params_cust_fb[] = $t;
    if(!empty($search_min_price)) $params_cust_fb[] = (float)$search_min_price;
    if(!empty($search_max_price)) $params_cust_fb[] = (float)$search_max_price;

    $fb_cust_where = "status = 'approved'";
    if(!empty($where_cust_fb)) $fb_cust_where .= " AND " . implode(" AND ", $where_cust_fb);
    if(!empty($search_date)) {
        $fb_cust_where .= " AND DATE(created_at) = ?";
        $params_cust_fb[] = $search_date;
    }
    $fb_cust_stmt = $pdo->prepare("SELECT *, 'customer' as source FROM user_properties WHERE $fb_cust_where ORDER BY created_at DESC");
    $fb_cust_stmt->execute($params_cust_fb);
    $customer_props = $fb_cust_stmt->fetchAll();
}

// ---- Render Property Card ----
function renderPropertyCard($prop, $show_images, $is_today = false) {
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
    $shadow = ($g['text'] == 'white') ? '0 4px 20px rgba(0,0,0,0.3)' : '0 4px 20px rgba(0,0,0,0.05)';
    $border = ($g['text'] == 'white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.05)';
    $image_url = ($prop['source'] == 'auction') ? ($prop['image_url'] ?? '') : ($prop['image_url'] ?? '');
    
    $is_private_treaty = ($prop['source'] == 'auction' && isset($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty');
    ?>
    <div class="col-md-4 mb-4">
        <div class="property-card" style="position:relative; border-radius:24px; overflow:hidden; box-shadow:<?= $shadow ?>; height:100%; background: <?= $g['bg'] ?>; color:<?= $text_color ?>; transition:all 0.4s; border:1px solid <?= $border ?>;">
            <?php if($is_private_treaty): ?>
                <div style="position:absolute; top:15px; right:15px; z-index:10; background:#f59e0b; color:#000; padding:4px 14px; border-radius:30px; font-size:0.7rem; font-weight:700;">
                    🔑 Private Treaty
                </div>
            <?php endif; ?>
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? ($prop['source']=='customer' ? 'Customer' : 'Bank')) ?></span>
                    
                    <?php if($prop['source'] == 'auction'): ?>
                        <div style="text-align:right; line-height:1.3;">
                            <div style="font-size:0.6rem; opacity:0.6; color:<?= $text_color ?>; text-transform:uppercase; letter-spacing:0.3px;">Auction Date</div>
                            <div style="font-size:0.85rem; font-weight:700; color:<?= $text_color ?>;">
                                <?php 
                                if(!empty($prop['auction_start_time']) && $prop['auction_start_time'] == 'Private Treaty') {
                                    echo '🔑 Private Treaty';
                                } elseif(!empty($prop['auction_date'])) {
                                    echo date('d.m.Y', strtotime($prop['auction_date']));
                                } elseif(!empty($prop['auction_start_time'])) {
                                    echo date('d.m.Y', strtotime($prop['auction_start_time']));
                                } else {
                                    echo 'N/A';
                                }
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
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="property_detail.php?id=<?= $prop['id'] ?>&source=<?= $prop['source'] ?>" style="display:block; margin-top:16px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-light w-100 mt-3" style="border-radius:16px; font-weight:600; color:#1e293b;">Login to View</a>
                <?php endif; ?>
            </div>
            <?php if($show_images && !empty($image_url)): ?>
                <img src="<?= htmlspecialchars($image_url) ?>" style="height:200px; width:100%; object-fit:cover; border-top:3px solid <?= $border ?>;" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:150px; background:rgba(255,255,255,0.08); display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(4px); border-top:3px solid <?= $border ?>; padding:10px;">
                    <i class="fas fa-lock" style="font-size:1.8rem; opacity:0.7; color:<?= $text_color ?>;"></i>
                    <span style="font-size:0.8rem; font-weight:600; margin-top:4px; color:<?= $text_color ?>;">🔒 Subscribe to unlock</span>
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn btn-sm btn-light mt-2" style="border-radius:30px; font-weight:600; color:#1e293b;">Login</a>
                    <?php else: ?>
                        <a href="user_packages.php" class="btn btn-sm btn-warning mt-2" style="border-radius:30px; font-weight:600; color:#1e293b;">Subscribe Now</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Helper: build URL for tabs preserving all filters (including multi-type)
function buildTabUrl($tab, $city, $locality, $types, $min_price, $max_price, $date) {
    $params = [
        'tab' => $tab,
        'city' => $city,
        'locality' => $locality,
        'min_price' => $min_price,
        'max_price' => $max_price,
        'date' => $date,
    ];
    if (!empty($types) && is_array($types)) {
        $params['type'] = $types;
    }
    return '?' . http_build_query($params);
}
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

        /* 🔥 MULTI-TYPE SELECTOR */
        .type-selector-wrapper { position: relative; }
        .type-selector-btn {
            width: 100%;
            border: none;
            background: #f1f5f9;
            border-radius: 20px;
            padding: 12px 20px;
            font-size: 0.95rem;
            text-align: left;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #1e293b;
        }
        .type-selector-btn:hover { background: #e2e8f0; }
        .type-selector-btn .badge-selected {
            background: #2563eb;
            color: #fff;
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 30px;
            font-weight: 700;
            margin-left: 6px;
        }
        .type-panel {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            border: 1px solid #e2e8f0;
            padding: 10px;
            z-index: 100;
            max-height: 320px;
            overflow-y: auto;
            min-width: 260px;
        }
        .type-panel-header {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .type-panel-header button {
            background: none;
            border: none;
            color: #2563eb;
            font-size: 0.72rem;
            font-weight: 700;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }
        .type-panel-header button:hover { background: #eff6ff; }
        .type-checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 500;
            transition: background 0.15s;
            margin: 0;
        }
        .type-checkbox-item:hover { background: #f8fafc; }
        .type-checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #2563eb;
            margin: 0;
        }
        .type-checkbox-item.checked { background: #eff6ff; font-weight: 700; color: #1e40af; }

        .section-title { font-weight:800; color:#0f172a; margin-bottom:20px; position:relative; }
        .section-title i { margin-right:10px; }
        .property-card:hover { transform:translateY(-10px); box-shadow:0 30px 60px -15px rgba(0,0,0,0.2) !important; }
        .nav-tabs .nav-link { font-weight:600; color:#475569; border: none; padding:12px 20px; position:relative; }
        .nav-tabs .nav-link.active { background: transparent; border-bottom: 3px solid #2563eb; color: #2563eb; }
        .nav-tabs .nav-link:hover { border-bottom: 3px solid #94a3b8; }
        .nav-tabs .nav-link .badge-count { 
            background: #e2e8f0; color: #1e293b; font-size: 0.7rem; 
            padding: 2px 10px; border-radius: 30px; margin-left: 8px; font-weight: 700;
        }
        .nav-tabs .nav-link.active .badge-count { background: #2563eb; color: #ffffff; }
        .no-auction-msg { background: #f8fafc; border-radius: 30px; padding: 30px; text-align: center; border: 2px dashed #e2e8f0; }
        .no-auction-msg i { font-size: 2.5rem; opacity:0.3; }

        .fallback-notice {
            background: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b;
            border-radius: 12px; padding: 12px 18px; margin-bottom: 20px;
            font-size: 0.88rem; color: #78350f;
        }

        @media (max-width:576px) { .search-box { padding:20px; } }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- SEARCH BOX -->
        <div class="search-box">
            <form method="GET" class="row g-3 align-items-center" id="searchForm">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

                <!-- ROW 1: City | Locality | Type (multi) | Date -->
                <div class="col-md-3">
                    <input type="text" name="city" class="form-control"
                           placeholder="🔍 Search by City..."
                           value="<?= htmlspecialchars($search_city) ?>">
                </div>

                <div class="col-md-3">
                    <input type="text" name="locality" class="form-control"
                           placeholder="📍 Search by Locality / Area..."
                           value="<?= htmlspecialchars($search_locality) ?>">
                </div>

                <!-- 🔥 MULTI-SELECT TYPE -->
                <div class="col-md-3">
                    <div class="type-selector-wrapper">
                        <button type="button" class="type-selector-btn" onclick="toggleTypePanel(event)">
                            <span id="typeLabel">
                                <?php 
                                if (empty($search_types)) {
                                    echo 'All Types';
                                } elseif (count($search_types) === 1) {
                                    echo htmlspecialchars($search_types[0]);
                                } else {
                                    echo htmlspecialchars($search_types[0]) . ' +' . (count($search_types) - 1) . ' more';
                                }
                                ?>
                            </span>
                            <i class="fas fa-chevron-down" style="font-size:0.75rem;opacity:0.5;"></i>
                        </button>

                        <div class="type-panel" id="typePanel" style="display:none;">
                            <div class="type-panel-header">
                                <span>Select Property Type</span>
                                <button type="button" onclick="clearAllTypes(event)">Clear All</button>
                            </div>

                            <?php foreach ($all_types as $t): 
                                $is_checked = in_array($t, $search_types);
                            ?>
                                <label class="type-checkbox-item <?= $is_checked ? 'checked' : '' ?>" 
                                       onclick="event.stopPropagation();">
                                    <input type="checkbox"
                                           name="type[]"
                                           value="<?= htmlspecialchars($t) ?>"
                                           <?= $is_checked ? 'checked' : '' ?>
                                           onchange="onTypeChange(this)">
                                    <span><?= htmlspecialchars($t) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <input type="date" name="date" class="form-control"
                           value="<?= htmlspecialchars($search_date) ?>">
                </div>

                <!-- ROW 2: Min | Max | Search | Reset -->
                <div class="col-md-3">
                    <input type="number" name="min_price" class="form-control"
                           placeholder="💰 Min Price (₹)"
                           value="<?= htmlspecialchars($search_min_price) ?>"
                           min="0" step="1000">
                </div>

                <div class="col-md-3">
                    <input type="number" name="max_price" class="form-control"
                           placeholder="💰 Max Price (₹)"
                           value="<?= htmlspecialchars($search_max_price) ?>"
                           min="0" step="1000">
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Search Properties
                    </button>
                </div>

                <div class="col-md-2">
                    <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-reset w-100" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>

            <!-- Active Filters -->
            <?php if(!empty($search_city) || !empty($search_locality) || !empty($search_types) || !empty($search_min_price) || !empty($search_max_price) || !empty($search_date)): ?>
                <div class="mt-3 pt-3 border-top">
                    <span class="small text-muted me-2">Active Filters:</span>
                    <?php if(!empty($search_city)): ?>
                        <span class="badge bg-primary me-1">🏙️ <?= htmlspecialchars($search_city) ?></span>
                    <?php endif; ?>
                    <?php if(!empty($search_locality)): ?>
                        <span class="badge bg-info text-dark me-1">📍 <?= htmlspecialchars($search_locality) ?></span>
                    <?php endif; ?>
                    <?php if(!empty($search_types)): ?>
                        <?php foreach($search_types as $t): ?>
                            <span class="badge bg-success me-1">🏠 <?= htmlspecialchars($t) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if(!empty($search_min_price)): ?>
                        <span class="badge bg-warning text-dark me-1">Min ₹<?= indianCurrencyFormat($search_min_price) ?></span>
                    <?php endif; ?>
                    <?php if(!empty($search_max_price)): ?>
                        <span class="badge bg-warning text-dark me-1">Max ₹<?= indianCurrencyFormat($search_max_price) ?></span>
                    <?php endif; ?>
                    <?php if(!empty($search_date)): ?>
                        <span class="badge bg-danger me-1">📅 <?= htmlspecialchars($search_date) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if($locality_fallback_used): ?>
            <div class="fallback-notice">
                <i class="fas fa-info-circle me-2"></i>
                <strong>"<?= htmlspecialchars($search_locality) ?>"</strong> locality में कुछ नहीं मिला। 
                आस-पास की properties दिखा रहे हैं 
                <?php if(!empty($search_city)): ?>
                    (<strong><?= htmlspecialchars($search_city) ?></strong> में)
                <?php endif; ?>।
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= ($tab=='auction')?'active':'' ?>"
                   href="<?= buildTabUrl('auction', $search_city, $search_locality, $search_types, $search_min_price, $search_max_price, $search_date) ?>">
                    <i class="fas fa-gavel me-2"></i>Auction Properties
                    <span class="badge-count"><?= $total_auction ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($tab=='customer')?'active':'' ?>"
                   href="<?= buildTabUrl('customer', $search_city, $search_locality, $search_types, $search_min_price, $search_max_price, $search_date) ?>">
                    <i class="fas fa-home me-2"></i>Customer Properties
                    <span class="badge-count"><?= $total_customer ?></span>
                </a>
            </li>
        </ul>

        <?php if($tab == 'auction'): ?>
            <?php if(!empty($search_date)): ?>
                <div class="section-title">
                    <i class="fas fa-calendar-day" style="color:#2563eb;"></i> 
                    Properties for <?= htmlspecialchars($search_date) ?>
                    <span class="badge bg-primary rounded-pill ms-2"><?= count($today_props) ?></span>
                </div>
                <?php if(count($today_props) > 0): ?>
                    <div class="row"><?php foreach($today_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>
                <?php else: ?>
                    <div class="no-auction-msg"><i class="fas fa-calendar-times"></i><p class="mt-2 fw-bold">📭 No property on this date</p></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="section-title">
                    <i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions
                    <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span>
                    <?php
                    $pt_count = 0;
                    foreach($today_props as $p) {
                        if(isset($p['auction_start_time']) && $p['auction_start_time'] == 'Private Treaty') $pt_count++;
                    }
                    if($pt_count > 0): ?>
                        <span class="badge bg-warning text-dark rounded-pill ms-2">🔑 <?= $pt_count ?> Private Treaty</span>
                    <?php endif; ?>
                </div>
                <?php if(count($today_props) > 0): ?>
                    <div class="row"><?php foreach($today_props as $prop) renderPropertyCard($prop, $show_images, true); ?></div>
                <?php else: ?>
                    <div class="no-auction-msg"><i class="fas fa-calendar-day"></i><p class="mt-2 fw-bold">📭 No auction today</p></div>
                <?php endif; ?>

                <hr class="my-5">

                <div class="section-title">
                    <i class="fas fa-clock" style="color:#2563eb;"></i> Upcoming Auctions
                    <span class="badge bg-primary rounded-pill ms-2"><?= count($upcoming_props) ?></span>
                </div>
                <?php if(count($upcoming_props) > 0): ?>
                    <div class="row"><?php foreach($upcoming_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>
                <?php else: ?>
                    <div class="no-auction-msg"><i class="fas fa-calendar-plus"></i><p class="mt-2 fw-bold">📅 No upcoming auctions</p></div>
                <?php endif; ?>

                <hr class="my-5">

                <div class="section-title">
                    <i class="fas fa-history" style="color:#64748b;"></i> Past Auctions
                    <span class="badge bg-secondary rounded-pill ms-2"><?= count($past_props) ?></span>
                </div>
                <?php if(count($past_props) > 0): ?>
                    <div class="row"><?php foreach($past_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>
                <?php else: ?>
                    <div class="no-auction-msg"><i class="fas fa-calendar-check"></i><p class="mt-2 fw-bold">📭 No past auctions</p></div>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
            <div class="section-title">
                <i class="fas fa-home" style="color:#10b981;"></i> Customer Properties
                <span class="badge bg-primary rounded-pill ms-2"><?= count($customer_props) ?></span>
            </div>
            <?php if(count($customer_props) > 0): ?>
                <div class="row"><?php foreach($customer_props as $prop) renderPropertyCard($prop, $show_images, false); ?></div>
            <?php else: ?>
                <div class="no-auction-msg">
                    <i class="fas fa-home"></i>
                    <p class="mt-2 fw-bold">🏠 No customer properties found</p>
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] != 'admin'): ?>
                        <a href="add_user_property.php" class="btn btn-primary mt-2">Add Your Property</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- 🔥 MULTI-SELECT TYPE JS -->
    <script>
    function toggleTypePanel(e) {
        e.stopPropagation();
        const panel = document.getElementById('typePanel');
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
    }

    function onTypeChange(checkbox) {
        // Update parent label styling
        const label = checkbox.closest('.type-checkbox-item');
        if (checkbox.checked) {
            label.classList.add('checked');
        } else {
            label.classList.remove('checked');
        }
        updateTypeLabel();
    }

    function updateTypeLabel() {
        const checked = document.querySelectorAll('#typePanel input[type="checkbox"]:checked');
        const labelEl = document.getElementById('typeLabel');
        if (checked.length === 0) {
            labelEl.textContent = 'All Types';
        } else if (checked.length === 1) {
            labelEl.textContent = checked[0].value;
        } else {
            labelEl.textContent = checked[0].value + ' +' + (checked.length - 1) + ' more';
        }
    }

    function clearAllTypes(e) {
        e.stopPropagation();
        document.querySelectorAll('#typePanel input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
            cb.closest('.type-checkbox-item').classList.remove('checked');
        });
        updateTypeLabel();
    }

    // Close panel when clicking outside
    document.addEventListener('click', function(e) {
        const panel = document.getElementById('typePanel');
        const wrapper = document.querySelector('.type-selector-wrapper');
        if (panel && wrapper && !wrapper.contains(e.target)) {
            panel.style.display = 'none';
        }
    });

    // Prevent closing when clicking inside panel
    document.getElementById('typePanel').addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Initial label update
    updateTypeLabel();
    </script>
</body>
</html>
