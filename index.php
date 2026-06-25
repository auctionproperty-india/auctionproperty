<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$show_images = userHasActiveSubscription($pdo, $user_id);

// Today's date in format "d M Y" (e.g., "25 Jun 2026")
$today_str = date('d M Y');

// Search parameters
$search_city = $_GET['city'] ?? '';
$search_type = $_GET['type'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';

// Build common WHERE clause for available properties
$where = ["status = 'available'"];
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
$base_sql = "SELECT * FROM properties WHERE $where_clause";

// ---- 1. Today's Auctions ----
$today_sql = $base_sql . " AND auction_start_time ILIKE ? ORDER BY id DESC";
$today_params = array_merge($params, ['%'.$today_str.'%']);
$today_stmt = $pdo->prepare($today_sql);
$today_stmt->execute($today_params);
$today_props = $today_stmt->fetchAll();

// ---- 2. Upcoming Auctions (excluding today's) ----
$upcoming_sql = $base_sql . " AND (auction_start_time NOT ILIKE ? OR auction_start_time IS NULL) ORDER BY id DESC";
$upcoming_params = array_merge($params, ['%'.$today_str.'%']);
$upcoming_stmt = $pdo->prepare($upcoming_sql);
$upcoming_stmt->execute($upcoming_params);
$upcoming_props = $upcoming_stmt->fetchAll();

// ---- Helper to render property cards ----
function renderPropertyCard($prop, $show_images, $is_today = false) {
    $badge_html = '';
    if($is_today) {
        $badge_html = '<span class="badge bg-danger text-white px-3 py-2" style="border-radius:30px; font-size:0.7rem; position:absolute; top:12px; right:12px; z-index:10; box-shadow:0 4px 12px rgba(220,38,38,0.3);"><i class="fas fa-fire"></i> Today\'s Auction</span>';
    }
    ?>
    <div class="col-md-4 mb-4">
        <div class="property-card" style="position:relative; background:#ffffff; border-radius:24px; overflow:hidden; box-shadow:0 10px 30px -5px rgba(0,0,0,0.04); transition:all 0.4s; border:1px solid rgba(255,255,255,0.2); height:100%;">
            <?= $badge_html ?>
            <?php if($show_images && !empty($prop['image_url'])): ?>
                <img src="<?= htmlspecialchars($prop['image_url']) ?>" class="card-img-top" style="height:220px; object-fit:cover; background:linear-gradient(145deg, #f1f5f9, #e2e8f0);" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:220px; background:linear-gradient(145deg, #f8fafc, #e2e8f0); display:flex; flex-direction:column; align-items:center; justify-content:center; color:#94a3b8;">
                    <i class="fas fa-building" style="font-size:3rem;"></i>
                    <span class="badge bg-warning mt-2 text-dark" style="font-size:0.7rem;">🔒 Subscribe to see image</span>
                </div>
            <?php endif; ?>
            <div class="card-body" style="padding:22px 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#1e3a8a; background:#e0e7ff; padding:4px 14px; border-radius:30px;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                    <?php if(!empty($prop['auction_start_time'])): ?>
                        <span style="font-size:0.75rem; color:#64748b; font-weight:500;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php endif; ?>
                </div>
                <h5 style="font-size:1.2rem; font-weight:700; color:#0f172a; margin:12px 0 6px;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:#0b1120;">₹ <?= indianCurrencyFormat($prop['price']) ?> <span style="font-size:0.9rem; font-weight:400; color:#64748b;">Reserve</span></div>
                <div style="font-size:0.85rem; color:#475569; margin-top:6px;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="property_detail.php?id=<?= $prop['id'] ?>" style="background:linear-gradient(135deg, #1e3a8a, #2563eb); border:none; color:white; font-weight:700; padding:12px; border-radius:16px; width:100%; transition:all 0.3s; margin-top:16px; text-decoration:none; display:block; text-align:center;">View Details</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary w-100 mt-3" style="border-radius:16px;">Login to View</a>
                <?php endif; ?>
            </div>
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
    <title>Prime Property – Luxury Auction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6fa; color: #1e293b; }
        .navbar-dark { background: linear-gradient(135deg, #0f172a, #1e293b) !important; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .navbar-brand { font-weight:800; font-size:1.6rem; letter-spacing:1px; }
        .navbar-brand i { color:#fbbf24; }
        .search-box { background:#ffffff; padding:25px 30px; border-radius:30px; box-shadow:0 15px 40px -10px rgba(0,0,0,0.08); border:1px solid rgba(255,255,255,0.3); backdrop-filter:blur(10px); margin-bottom:40px; }
        .search-box .form-control { border:none; background:#f1f5f9; border-radius:20px; padding:12px 20px; font-size:0.95rem; }
        .search-box .btn-primary { border-radius:30px; padding:12px 30px; background:linear-gradient(135deg, #1e3a8a, #2563eb); border:none; font-weight:600; transition:all 0.3s; }
        .search-box .btn-primary:hover { transform:scale(1.02); box-shadow:0 8px 25px rgba(37,99,235,0.3); }
        .section-title { font-weight:800; color:#0f172a; margin-bottom:20px; position:relative; }
        .section-title i { margin-right:10px; }
        .property-card { transition:all 0.4s cubic-bezier(0.25,0.46,0.45,0.94); }
        .property-card:hover { transform:translateY(-10px); box-shadow:0 25px 50px -10px rgba(0,0,0,0.15); border-color:#fbbf24; }
        @media (max-width:576px) { .search-box { padding:20px; } .property-card img { height:180px; } }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
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

    <!-- ===== TODAY'S AUCTIONS ===== -->
    <?php if(count($today_props) > 0): ?>
        <div class="section-title">
            <i class="fas fa-bolt" style="color:#dc2626;"></i> Today's Auctions <span class="badge bg-danger rounded-pill ms-2"><?= count($today_props) ?></span>
        </div>
        <div class="row">
            <?php foreach($today_props as $prop): ?>
                <?php renderPropertyCard($prop, $show_images, true); ?>
            <?php endforeach; ?>
        </div>
        <hr class="my-5">
    <?php else: ?>
        <div class="alert alert-light text-center py-4" style="border-radius:30px; background:#f8fafc;">
            <i class="fas fa-calendar-day" style="font-size:2rem; opacity:0.3;"></i>
            <p class="mt-2">No auctions scheduled for today. Check upcoming auctions below.</p>
        </div>
    <?php endif; ?>

    <!-- ===== UPCOMING AUCTIONS ===== -->
    <div class="section-title">
        <i class="fas fa-clock" style="color:#2563eb;"></i> Upcoming Auctions
        <?php if(count($upcoming_props) > 0): ?>
            <span class="badge bg-primary rounded-pill ms-2"><?= count($upcoming_props) ?></span>
        <?php endif; ?>
    </div>
    <div class="row">
        <?php if(count($upcoming_props) > 0): ?>
            <?php foreach($upcoming_props as $prop): ?>
                <?php renderPropertyCard($prop, $show_images, false); ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-calendar-plus" style="font-size:3rem; opacity:0.2;"></i>
                <p class="mt-3">No upcoming auctions at the moment. Check back later.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
