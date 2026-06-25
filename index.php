<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$show_images = userHasActiveSubscription($pdo, $user_id);
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
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6fa;
            color: #1e293b;
        }
        .navbar-dark {
            background: linear-gradient(135deg, #0f172a, #1e293b) !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .navbar-brand {
            font-weight: 800;
            font-size: 1.6rem;
            letter-spacing: 1px;
        }
        .navbar-brand i {
            color: #fbbf24;
        }
        .search-box {
            background: #ffffff;
            padding: 25px 30px;
            border-radius: 30px;
            box-shadow: 0 15px 40px -10px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(10px);
            margin-bottom: 40px;
        }
        .search-box .form-control {
            border: none;
            background: #f1f5f9;
            border-radius: 20px;
            padding: 12px 20px;
            font-size: 0.95rem;
        }
        .search-box .btn-primary {
            border-radius: 30px;
            padding: 12px 30px;
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            border: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .search-box .btn-primary:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }
        .property-card {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            border: 1px solid rgba(255,255,255,0.2);
            height: 100%;
            backdrop-filter: blur(4px);
        }
        .property-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px -10px rgba(0,0,0,0.15);
            border-color: #fbbf24;
        }
        .property-card .card-img-top {
            height: 240px;
            object-fit: cover;
            background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
        }
        .property-card .card-body {
            padding: 24px 22px;
        }
        .property-card .bank-badge {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1e3a8a;
            background: #e0e7ff;
            padding: 4px 14px;
            border-radius: 30px;
            display: inline-block;
        }
        .property-card .auction-date {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }
        .property-card .property-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
            margin: 10px 0 6px;
        }
        .property-card .price {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0b1120;
        }
        .property-card .price span {
            font-size: 0.9rem;
            font-weight: 400;
            color: #64748b;
        }
        .property-card .city-badge {
            font-size: 0.85rem;
            color: #475569;
            margin-top: 6px;
        }
        .property-card .btn-auction {
            background: linear-gradient(135deg, #1e3a8a, #2563eb);
            border: none;
            color: white;
            font-weight: 700;
            padding: 12px;
            border-radius: 16px;
            width: 100%;
            transition: all 0.3s;
            margin-top: 16px;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        .property-card .btn-auction:hover {
            background: linear-gradient(135deg, #0f172a, #1e3a8a);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }
        .property-placeholder {
            height: 240px;
            background: linear-gradient(145deg, #f8fafc, #e2e8f0);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-bottom: 2px dashed #94a3b8;
            color: #64748b;
        }
        .property-placeholder i {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.6;
        }
        .property-placeholder .badge {
            font-size: 0.7rem;
            background: #fbbf24;
            color: #0f172a;
            padding: 4px 12px;
            border-radius: 30px;
        }
        @media (max-width: 576px) {
            .search-box { padding: 20px; }
            .property-card .card-img-top { height: 180px; }
        }
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
                <input type="text" name="city" class="form-control" placeholder="🔍 Search by City..." value="<?= htmlspecialchars($_GET['city'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Flat" <?= ($_GET['type']??'')=='Flat'?'selected':'' ?>>Flat</option>
                    <option value="Plot" <?= ($_GET['type']??'')=='Plot'?'selected':'' ?>>Plot</option>
                    <option value="Shop" <?= ($_GET['type']??'')=='Shop'?'selected':'' ?>>Shop</option>
                    <option value="Land" <?= ($_GET['type']??'')=='Land'?'selected':'' ?>>Land</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="number" name="max_price" class="form-control" placeholder="Max Price (₹)" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>
    </div>

    <h2 class="fw-bold mb-4" style="color: #0f172a;"><i class="fas fa-fire" style="color: #f97316;"></i> Live Auctions</h2>

    <div class="row">
        <?php
        $sql = "SELECT * FROM properties WHERE status = 'available'";
        $params = [];
        if(!empty($_GET['city'])) { $sql .= " AND city ILIKE ?"; $params[] = '%'.$_GET['city'].'%'; }
        if(!empty($_GET['type'])) { $sql .= " AND type = ?"; $params[] = $_GET['type']; }
        if(!empty($_GET['max_price'])) { $sql .= " AND price <= ?"; $params[] = $_GET['max_price']; }
        $sql .= " ORDER BY id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $properties = $stmt->fetchAll();

        if(count($properties) > 0):
            foreach($properties as $prop): ?>
            <div class="col-md-4 mb-4">
                <div class="property-card">
                    <?php if($show_images && !empty($prop['image_url'])): ?>
                        <img src="<?= htmlspecialchars($prop['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($prop['title']) ?>">
                    <?php else: ?>
                        <div class="property-placeholder">
                            <i class="fas fa-building"></i>
                            <span class="badge">🔒 Subscribe to see image</span>
                        </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="bank-badge">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                            <?php if(!empty($prop['auction_start_time'])): ?>
                                <span class="auction-date"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h5 class="property-title"><?= htmlspecialchars($prop['title']) ?></h5>
                        <div class="price">₹ <?= indianCurrencyFormat($prop['price']) ?> <span>Reserve</span></div>
                        <div class="city-badge"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <a href="property_detail.php?id=<?= $prop['id'] ?>" class="btn-auction"><i class="fas fa-eye"></i> View Details</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-secondary w-100 mt-3">Login to View</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-search" style="font-size: 3rem; opacity: 0.3;"></i>
                <p class="mt-3">No properties match your search. Try adjusting filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
