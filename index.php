<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$show_images = userHasActiveSubscription($pdo, $user_id);

$search_city = $_GET['city'] ?? '';
$search_type = $_GET['type'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';
$tab = $_GET['tab'] ?? 'auction';

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

$today_sql = $base_sql . " AND auction_date = CURRENT_DATE ORDER BY id DESC";
$today_stmt = $pdo->prepare($today_sql);
$today_stmt->execute($params);
$today_props = $today_stmt->fetchAll();

$upcoming_sql = $base_sql . " AND (auction_date != CURRENT_DATE OR auction_date IS NULL) ORDER BY id DESC";
$upcoming_stmt = $pdo->prepare($upcoming_sql);
$upcoming_stmt->execute($params);
$upcoming_props = $upcoming_stmt->fetchAll();

// ---- Customer Properties ----
$customer_where = "status = 'approved'";
if(!empty($where_clause)) {
    $customer_where .= " AND " . $where_clause;
}
$customer_sql = "SELECT *, 'customer' as source FROM user_properties WHERE $customer_where ORDER BY created_at DESC";
$customer_stmt = $pdo->prepare($customer_sql);
$customer_stmt->execute($params);
$customer_props = $customer_stmt->fetchAll();

// ---- Render Property Card (without "Today" badge) ----
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
    ?>
    <div class="col-md-4 mb-4">
        <div class="property-card" style="position:relative; border-radius:24px; overflow:hidden; box-shadow:<?= $shadow ?>; height:100%; background: <?= $g['bg'] ?>; color:<?= $text_color ?>; transition:all 0.4s; border:1px solid <?= $border ?>;">
            <!-- No badge -->
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? ($prop['source']=='customer' ? 'Customer' : 'Bank')) ?></span>
                    <?php if($prop['source'] == 'auction' && !empty($prop['auction_start_time'])): ?>
                        <span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php elseif($prop['source'] == 'customer'): ?>
                        <span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;">📅 <?= date('d M Y', strtotime($prop['created_at'])) ?></span>
                    <?php endif; ?>
                </div>
                <h5 style="font-size:1.2rem; font-weight:700; margin:12px 0 6px; color:<?= $text_color ?>;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:<?= $text_color ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?> <span style="font-size:0.9rem; font-weight:400; opacity:0.7;">Reserve</span></div>
                <div style="font-size:0.85rem; opacity:0.8; margin-top:6px; color:<?= $text_color ?>;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
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
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Property – Auction & Customer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6fa; color: #1e293b; padding-top: 76px; }
        .navbar-dark { background: linear-gradient(135deg, #0f172a, #1e293b) !important; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: fixed; top:0; left:0; right:0; z-index:1030; }
        .navbar-brand { font-weight:800; font-size:1.6rem; letter-spacing:1px; }
        .navbar-brand i { color:#fbbf24; }
        .search-box { background:#ffffff; padding:25px 30px; border-radius:30px; box-shadow:0 15px 40px -10px rgba(0,0,0,0.08); border:1px solid rgba(255,255,255,0.3); backdrop-filter:blur(10px); margin-bottom:40px; }
        .search-box .form-control { border:none; background:#f1f5f9; border-radius:20px; padding:12px 20px; font-size:0.95rem; }
        .search-box .btn-primary { border-radius:30px; padding:12px 30px; background:linear-gradient(135deg, #1e3a8a, #2563eb); border:none; font-weight:600; transition:all 0.3s; }
        .search-box .btn-primary:hover { transform:scale(1.02); box-shadow:0 8px 25px rgba(37,99,235,0.3); }
        .section-title { font-weight:800; color:#0f172a; margin-bottom:20px; position:relative; }
        .section-title i { margin-right:10px; }
        .property-card:hover { transform:translateY(-10px); box-shadow:0 30px 60px -15px rgba(0,0,0,0.2) !important; }
        .nav-tabs .nav-link { font-weight:600; color:#475569; border: none; padding:12px 20px; }
        .nav-tabs .nav-link.active { background: transparent; border-bottom: 3px solid #2563eb; color: #2563eb; }
        .nav-tabs .nav-link:hover { border-bottom: 3px solid #94a3b8; }
        .no-auction-msg { background: #f8fafc; border-radius: 30px; padding: 30px; text-align: center; border: 2px dashed #e2e8f0; }
        .no-auction-msg i { font-size: 2.5rem; opacity:0.3; }
        @media (max-width:576px) { .search-box { padding:20px; } body { padding-top: 66px; } }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="fas fa-gavel"></i> Prime Property</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Auctions</a></li>
            </ul>
            <div class="ms-3">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2"><i class="fas fa-user"></i> Dashboard</a>
                    <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm me-2">Login</a>
                    <a href="register.php" class="btn btn-primary btn-sm">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <!-- Search Box -->
    <div class="search-box">
        <form method="GET" class="row g-3 align-items-center">
            <input type="hidden" name="tab" value="<?= $tab ?>">
            <div class="col-md-4">
                <input type="text" name="city" class="form-control" placeholder="🔍 Search by City..." value="<?= htmlspecialchars($search_city) ?>">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Flat" <?= ($search_type=='Flat')?'selected':'' ?>>Flat</option>
                    <option value="Plot" <?= ($search_type=='Plot')?'selected':'' ?>>Plot</option>
                    <option value="Shop" <?= ($search_type=='Shop')?'selected':'' ?>>Shop</option>
                    <option value="Land" <?= ($search_type=='Land')?'selected':'' ?>>Land</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" name="max_price" class="form-control" placeholder="Max Price (₹)" value="<?= htmlspecialchars($search_max_price) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= ($tab=='auction')?'active':'' ?>" href="?tab=auction&city=<?= urlencode($search_city) ?>&type=<?= urlencode($search_type) ?>&max_price=<?= urlencode($search_max_price) ?>">
                <i class="fas fa-gavel me-2"></i>Auction Properties
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= ($tab=='customer')?'active':'' ?>" href="?tab=customer&city=<?= urlencode($search_city) ?>&type=<?= urlencode($search_type) ?>&max_price=<?= urlencode($search_max_price) ?>">
                <i class="fas fa-home me-2"></i>Customer Properties
            </a>
        </li>
    </ul>

    <?php if($tab == 'auction'): ?>
        <!-- Auction Properties -->
        <div class="section-title">
            <i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions
            <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span>
        </div>
        <?php if(count($today_props) > 0): ?>
            <div class="row">
                <?php foreach($today_props as $prop): ?>
                    <?php renderPropertyCard($prop, $show_images, true); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-auction-msg">
                <i class="fas fa-calendar-day"></i>
                <p class="mt-2 fw-bold">📭 No auction today</p>
                <p class="text-muted">Check upcoming auctions below.</p>
            </div>
        <?php endif; ?>

        <hr class="my-5">

        <div class="section-title">
            <i class="fas fa-clock" style="color:#2563eb;"></i> Upcoming Auctions
            <span class="badge bg-primary rounded-pill ms-2"><?= count($upcoming_props) ?></span>
        </div>
        <?php if(count($upcoming_props) > 0): ?>
            <div class="row">
                <?php foreach($upcoming_props as $prop): ?>
                    <?php renderPropertyCard($prop, $show_images, false); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-auction-msg">
                <i class="fas fa-calendar-plus"></i>
                <p class="mt-2 fw-bold">📅 No upcoming auctions</p>
                <p class="text-muted">Check back later.</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Customer Properties -->
        <div class="section-title">
            <i class="fas fa-home" style="color:#10b981;"></i> Customer Properties
            <span class="badge bg-primary rounded-pill ms-2"><?= count($customer_props) ?></span>
        </div>
        <?php if(count($customer_props) > 0): ?>
            <div class="row">
                <?php foreach($customer_props as $prop): ?>
                    <?php renderPropertyCard($prop, $show_images, false); ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-auction-msg">
                <i class="fas fa-home"></i>
                <p class="mt-2 fw-bold">🏠 No customer properties yet</p>
                <p class="text-muted">Be the first to list your property!</p>
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
