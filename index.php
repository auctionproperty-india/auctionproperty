<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏠 FindAuction - Bank Properties</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .hero-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            padding: 60px 0; border-radius: 0 0 40px 40px; color: white;
            margin-bottom: 30px; box-shadow: 0 10px 30px rgba(30, 58, 138, 0.2);
        }
        .hero-section h1 { font-weight: 800; }
        .search-box { background: white; padding: 15px; border-radius: 60px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .search-box .form-control { border: none; padding: 12px 20px; border-radius: 40px; background: #f1f5f9; }
        .search-box .btn-primary { border-radius: 40px; padding: 10px 30px; background: #1e3a8a; border: none; font-weight: 600; }
        .property-card {
            background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            transition: transform 0.3s ease, box-shadow 0.3s ease; height: 100%; border: 1px solid #e9edf4;
        }
        .property-card:hover { transform: translateY(-8px); box-shadow: 0 20px 35px rgba(0,0,0,0.08); }
        .property-card img { height: 220px; object-fit: cover; width: 100%; }
        .property-card .card-body { padding: 20px; }
        .property-card .bank-badge { font-size: 12px; font-weight: 600; color: #1e3a8a; background: #e0e7ff; padding: 4px 12px; border-radius: 30px; }
        .property-card .price { font-size: 22px; font-weight: 800; color: #0b1120; }
        .property-card .price span { font-size: 14px; font-weight: 400; color: #64748b; }
        .property-card .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; font-size: 14px; color: #475569; margin-top: 10px; }
        .property-card .btn-auction {
            background: #1e3a8a; border: none; color: white; font-weight: 700; padding: 10px; border-radius: 12px;
            width: 100%; margin-top: 15px; transition: 0.3s;
        }
        .property-card .btn-auction:hover { background: #0b1d4a; }
        .sidebar-card { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 5px 15px rgba(0,0,0,0.02); border: 1px solid #e9edf4; margin-bottom: 25px; }
        .sidebar-card h6 { font-weight: 700; color: #0b1120; border-bottom: 2px solid #e9edf4; padding-bottom: 12px; margin-bottom: 15px; }
        .social-icons a { color: #475569; font-size: 18px; margin-right: 15px; }
        .social-icons a:hover { color: #1e3a8a; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-gavel me-2"></i>FindAuction</a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item"><a class="nav-link" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Search</a></li>
                <li class="nav-item"><a class="nav-link" href="#">Premium</a></li>
                <li class="nav-item"><a class="nav-link" href="#">FAQ</a></li>
            </ul>
            <div>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="btn btn-outline-light me-2"><i class="fas fa-user"></i> Dashboard</a>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <h1 class="text-center">🔍 Find Bank Auction Properties</h1>
        <p class="text-center opacity-75">Search thousands of residential & commercial properties</p>
        <div class="row justify-content-center mt-4">
            <div class="col-md-8">
                <form method="GET" class="search-box d-flex gap-2 flex-wrap">
                    <input type="text" name="city" class="form-control flex-grow-1" placeholder="Search by City (e.g. Indore)" value="<?= htmlspecialchars($_GET['city'] ?? '') ?>">
                    <select name="type" class="form-control w-auto" style="flex: 1; min-width: 120px;">
                        <option value="">All Types</option>
                        <option value="Flat" <?= ($_GET['type']??'')=='Flat'?'selected':'' ?>>Flat</option>
                        <option value="Plot" <?= ($_GET['type']??'')=='Plot'?'selected':'' ?>>Plot</option>
                        <option value="Shop" <?= ($_GET['type']??'')=='Shop'?'selected':'' ?>>Shop</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container">
    <div class="row">
        <!-- Left Side: Properties -->
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold"><?= htmlspecialchars($_GET['city'] ?? 'All') ?> Properties</h5>
                <div>
                    <a href="?sort=popular" class="btn btn-sm btn-outline-secondary">Popular</a>
                    <a href="?sort=low" class="btn btn-sm btn-outline-secondary">Price: Low</a>
                    <a href="?sort=high" class="btn btn-sm btn-outline-secondary">Price: High</a>
                </div>
            </div>
            <div class="row">
                <?php
                $sql = "SELECT * FROM properties WHERE status = 'available'";
                $params = [];
                if(!empty($_GET['city'])) { $sql .= " AND city ILIKE ?"; $params[] = '%'.$_GET['city'].'%'; }
                if(!empty($_GET['type'])) { $sql .= " AND type = ?"; $params[] = $_GET['type']; }
                
                // Sorting
                if(isset($_GET['sort'])) {
                    if($_GET['sort'] == 'low') $sql .= " ORDER BY price ASC";
                    else if($_GET['sort'] == 'high') $sql .= " ORDER BY price DESC";
                    else $sql .= " ORDER BY id DESC";
                } else { $sql .= " ORDER BY id DESC"; }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $properties = $stmt->fetchAll();

                if(count($properties) > 0) {
                    foreach($properties as $prop) { ?>
                        <div class="col-md-6 mb-4">
                            <div class="property-card">
                                <img src="<?= htmlspecialchars($prop['image_url'] ?: 'https://via.placeholder.com/600x400?text=Bank+Auction') ?>" alt="Property">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <span class="bank-badge">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank Auction') ?></span>
                                        <span class="text-muted small"><i class="far fa-calendar"></i> <?= $prop['auction_date'] ?? date('d M Y') ?></span>
                                    </div>
                                    <h6 class="fw-bold mt-2"><?= htmlspecialchars($prop['title']) ?></h6>
                                    <p class="text-muted small"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['location']) ?>, <?= htmlspecialchars($prop['city']) ?></p>
                                    <div class="details-grid">
                                        <span><i class="fas fa-vector-square"></i> <?= $prop['sqft'] ?? 0 ?> Sq Ft</span>
                                        <span><i class="fas fa-hand"></i> <?= htmlspecialchars($prop['possession_type'] ?? 'Physical') ?></span>
                                    </div>
                                    <div class="price mt-2">₹ <?= number_format($prop['price'], 2) ?> <span>Starting Bid</span></div>
                                    <?php if(isset($_SESSION['user_id'])): ?>
                                        <a href="#" class="btn-auction text-center d-block"><i class="fas fa-gavel"></i> VIEW AUCTION</a>
                                    <?php else: ?>
                                        <a href="login.php" class="btn btn-secondary w-100 mt-3">Login to Bid</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php }
                } else { echo "<p class='text-muted'>No properties match your search.</p>"; }
                ?>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="col-lg-4">
            <!-- Daily Alert -->
            <div class="sidebar-card">
                <h6><i class="fas fa-bell text-primary"></i> Daily Alert</h6>
                <div class="input-group">
                    <input type="email" class="form-control" placeholder="Your Email">
                    <button class="btn btn-primary" type="button">Subscribe</button>
                </div>
            </div>

            <!-- Share -->
            <div class="sidebar-card">
                <h6><i class="fas fa-share-alt text-primary"></i> Share</h6>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fas fa-envelope"></i></a>
                </div>
            </div>

            <!-- Top Cities -->
            <div class="sidebar-card">
                <h6><i class="fas fa-city text-primary"></i> Top Cities</h6>
                <?php
                $cities = $pdo->query("SELECT DISTINCT city, COUNT(*) as total FROM properties WHERE city != '' GROUP BY city ORDER BY total DESC LIMIT 5")->fetchAll();
                if(count($cities) > 0) {
                    echo "<ul class='list-unstyled'>";
                    foreach($cities as $c) {
                        echo "<li class='py-1'><a href='?city=".urlencode($c['city'])."' class='text-decoration-none text-dark'><i class='fas fa-chevron-right text-primary' style='font-size:10px;'></i> ".htmlspecialchars($c['city'])." (".$c['total'].")</a></li>";
                    }
                    echo "</ul>";
                } else { echo "<p class='text-muted'>No cities found.</p>"; }
                ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
