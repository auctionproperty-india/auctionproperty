<?php
// ============================================================
// 🏠 Home Page – Today / Upcoming / Past Auctions + Customer Properties
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

$user_id = $_SESSION['user_id'] ?? null;
$show_images = userHasActiveSubscription($pdo, $user_id);

$search_city = $_GET['city'] ?? '';
$search_type = $_GET['type'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';
$search_date  = $_GET['date']  ?? '';
$tab = $_GET['tab'] ?? 'auction';

// ============================================================
// 🔥 TOTAL COUNTS (पूरे पोर्टल की कुल संख्या – बिना फ़िल्टर)
// ============================================================
$total_auction_stmt = $pdo->query("SELECT COUNT(*) as total FROM properties WHERE status = 'available'");
$total_auction = $total_auction_stmt->fetchColumn();

$total_customer_stmt = $pdo->query("SELECT COUNT(*) as total FROM user_properties");
$total_customer = $total_customer_stmt->fetchColumn();

// ============================================================
// SEARCH FILTERS (City, Type, Max Price, Date)
// ============================================================
$where = [];
$params = [];
if(!empty($search_city)) {
    $where[] = "city ILIKE ?";
    $params[] = '%'.$search_city.'%';
}
if(!empty($search_type)) {
    $where[] = "type = ?";
    $params[] = $search_type;
}
if(!empty($search_max_price)) {
    $where[] = "price <= ?";
    $params[] = (float)$search_max_price;
}
$where_clause = implode(" AND ", $where);

// ---- Auction Properties ----
$base_sql = "SELECT *, 'auction' as source FROM properties WHERE status = 'available'";
if(!empty($where_clause)) {
    $base_sql .= " AND " . $where_clause;
}

// -------- Date Search (specific date) overrides everything ----------
if(!empty($search_date)) {
    $base_sql .= " AND auction_date = ?";
    $params[] = $search_date;
    $stmt = $pdo->prepare($base_sql . " ORDER BY id DESC");
    $stmt->execute($params);
    $today_props = $stmt->fetchAll();
    $upcoming_props = [];
    $past_props = [];
} else {
    // -------- No Date Search: Show Today, Upcoming, Past ----------
    // 1. Today's Auctions (including Private Treaty)
    $today_sql = $base_sql . " AND (auction_date = CURRENT_DATE OR auction_start_time = 'Private Treaty') ORDER BY id DESC";
    $today_stmt = $pdo->prepare($today_sql);
    $today_stmt->execute($params);
    $today_props = $today_stmt->fetchAll();

    // 2. Upcoming Auctions (future dates, exclude Private Treaty)
    $upcoming_sql = $base_sql . " AND auction_date > CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') ORDER BY id DESC";
    $upcoming_stmt = $pdo->prepare($upcoming_sql);
    $upcoming_stmt->execute($params);
    $upcoming_props = $upcoming_stmt->fetchAll();

    // 3. Past Auctions (expired dates, exclude Private Treaty)
    $past_sql = $base_sql . " AND auction_date < CURRENT_DATE AND (auction_start_time IS NULL OR auction_start_time != 'Private Treaty') ORDER BY id DESC";
    $past_stmt = $pdo->prepare($past_sql);
    $past_stmt->execute($params);
    $past_props = $past_stmt->fetchAll();
}

// ---- Customer Properties ----
$customer_where = "status = 'approved'";
if(!empty($where_clause)) {
    $customer_where .= " AND " . $where_clause;
}
$customer_params = $params; // city, type, max_price
if(!empty($search_date)) {
    $customer_where .= " AND DATE(created_at) = ?";
    $customer_params[] = $search_date;
}
$customer_sql = "SELECT *, 'customer' as source FROM user_properties WHERE $customer_where ORDER BY created_at DESC";
$customer_stmt = $pdo->prepare($customer_sql);
$customer_stmt->execute($customer_params);
$customer_props = $customer_stmt->fetchAll();

// ---- Render Property Card (no change) ----
function renderPropertyCard($prop, $show_images, $is_today = false) {
    // ... (same as before) ...
    // (we'll keep the existing render function unchanged, just copy it)
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
        /* ... existing styles ... */
        /* add a new style for past auctions if needed */
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Search Box -->
        <div class="search-box">
            <form method="GET" class="row g-3 align-items-center">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                <div class="col-md-3">
                    <input type="text" name="city" class="form-control" placeholder="🔍 Search by City..." value="<?= htmlspecialchars($search_city) ?>">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Flat" <?= ($search_type=='Flat')?'selected':'' ?>>Flat</option>
                        <option value="Plot" <?= ($search_type=='Plot')?'selected':'' ?>>Plot</option>
                        <option value="Shop" <?= ($search_type=='Shop')?'selected':'' ?>>Shop</option>
                        <option value="Land" <?= ($search_type=='Land')?'selected':'' ?>>Land</option>
                        <!-- 🔥 Full property types as per your requirement -->
                        <option value="House" <?= ($search_type=='House')?'selected':'' ?>>House</option>
                        <option value="Car/Vehicle" <?= ($search_type=='Car/Vehicle')?'selected':'' ?>>Car / Vehicle</option>
                        <option value="Commercial" <?= ($search_type=='Commercial')?'selected':'' ?>>Commercial</option>
                        <option value="Office" <?= ($search_type=='Office')?'selected':'' ?>>Office</option>
                        <option value="Row House" <?= ($search_type=='Row House')?'selected':'' ?>>Row House</option>
                        <option value="Bungalow" <?= ($search_type=='Bungalow')?'selected':'' ?>>Bungalow</option>
                        <option value="Other" <?= ($search_type=='Other')?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="max_price" class="form-control" placeholder="Max Price (₹)" value="<?= htmlspecialchars($search_max_price) ?>">
                </div>
                <div class="col-md-3">
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($search_date) ?>" placeholder="Select Date">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
                </div>
            </form>
        </div>

        <!-- Tabs with Total Counts -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?= ($tab=='auction')?'active':'' ?>" href="?tab=auction&city=<?= urlencode($search_city) ?>&type=<?= urlencode($search_type) ?>&max_price=<?= urlencode($search_max_price) ?>&date=<?= urlencode($search_date) ?>">
                    <i class="fas fa-gavel me-2"></i>Auction Properties
                    <span class="badge-count"><?= $total_auction ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($tab=='customer')?'active':'' ?>" href="?tab=customer&city=<?= urlencode($search_city) ?>&type=<?= urlencode($search_type) ?>&max_price=<?= urlencode($search_max_price) ?>&date=<?= urlencode($search_date) ?>">
                    <i class="fas fa-home me-2"></i>Customer Properties
                    <span class="badge-count"><?= $total_customer ?></span>
                </a>
            </li>
        </ul>

        <?php if($tab == 'auction'): ?>
            <!-- ==================== AUCTION TAB ==================== -->
            <?php if(!empty($search_date)): ?>
                <!-- Date search mode: show only that date's properties -->
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
                <!-- ====== TODAY'S AUCTIONS ====== -->
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

                <!-- ====== UPCOMING AUCTIONS ====== -->
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

                <!-- ====== PAST AUCTIONS ====== -->
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
            <!-- ==================== CUSTOMER TAB ==================== -->
            <div class="section-title">
                <i class="fas fa-home" style="color:#10b981;"></i> Customer Properties
                <span class="badge bg-primary rounded-pill ms-2"><?= count($customer_props) ?></span>
                <?php if(!empty($search_date)): ?>
                    <span class="badge bg-info rounded-pill ms-2">📅 <?= htmlspecialchars($search_date) ?></span>
                <?php endif; ?>
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
</body>
</html>
