<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$show_images = userHasActiveSubscription($pdo, $user_id);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime Property</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f7fc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .property-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.03); transition: 0.3s; border: 1px solid #e9edf4; height: 100%; }
        .property-card:hover { transform: translateY(-8px); box-shadow: 0 20px 35px rgba(0,0,0,0.08); }
        .property-card img { height: 220px; object-fit: cover; width: 100%; cursor: pointer; }
        .property-card .card-body { padding: 20px; }
        .bank-badge { font-size: 12px; font-weight: 600; color: #1e3a8a; background: #e0e7ff; padding: 4px 12px; border-radius: 30px; }
        .price { font-size: 22px; font-weight: 800; color: #0b1120; }
        .btn-auction { background: #1e3a8a; border: none; color: white; font-weight: 700; padding: 10px; border-radius: 12px; width: 100%; transition: 0.3s; display: block; text-align: center; text-decoration: none; }
        .btn-auction:hover { background: #0b1d4a; color: #fff; }
        .search-box { background: white; padding: 20px; border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.03); border: 1px solid #e9edf4; margin-bottom: 30px; }
        .property-placeholder {
            background: linear-gradient(145deg, #f8fafc, #e2e8f0);
            border-radius: 20px 20px 0 0;
            padding: 30px 20px;
            text-align: center;
            height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-bottom: 2px dashed #94a3b8;
        }
        .property-placeholder i { font-size: 40px; color: #64748b; margin-bottom: 10px; }
        .property-placeholder h6 { font-weight: 700; color: #0f172a; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php"><i class="fas fa-gavel me-2"></i>Prime Property</a>
        <div class="ms-auto">
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php" class="btn btn-outline-light me-2">Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                <a href="register.php" class="btn btn-primary">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="search-box">
        <form method="GET" class="row g-3">
            <div class="col-md-4"><input type="text" name="city" placeholder="Search by City" class="form-control" value="<?= htmlspecialchars($_GET['city'] ?? '') ?>"></div>
            <div class="col-md-3"><select name="type" class="form-control"><option value="">All Types</option><option value="Flat" <?= ($_GET['type']??'')=='Flat'?'selected':'' ?>>Flat</option><option value="Plot" <?= ($_GET['type']??'')=='Plot'?'selected':'' ?>>Plot</option><option value="Shop" <?= ($_GET['type']??'')=='Shop'?'selected':'' ?>>Shop</option><option value="Land" <?= ($_GET['type']??'')=='Land'?'selected':'' ?>>Land</option></select></div>
            <div class="col-md-3"><input type="number" name="max_price" placeholder="Max Price" class="form-control" value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button></div>
        </form>
    </div>

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

        if(count($properties) > 0) {
            foreach($properties as $prop) { ?>
                <div class="col-md-4 mb-4">
                    <div class="property-card">
                        <?php if($show_images && !empty($prop['image_url'])): ?>
                            <a href="<?= htmlspecialchars($prop['image_url']) ?>" target="_blank">
                                <img src="<?= htmlspecialchars($prop['image_url']) ?>">
                            </a>
                        <?php else: ?>
                            <div class="property-placeholder">
                                <i class="fas fa-home"></i>
                                <h6><?= htmlspecialchars($prop['title']) ?></h6>
                                <p class="text-muted small"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></p>
                                <p class="fw-bold text-success">₹ <?= indianCurrencyFormat($prop['price']) ?></p>
                                <?php if(!$show_images): ?>
                                    <span class="badge bg-warning text-dark mt-2">🔒 Subscribe to View Image</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <span class="bank-badge">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                                <span class="text-muted small"><i class="far fa-calendar"></i> <?= date('d M Y', strtotime($prop['auction_date'] ?? 'now')) ?></span>
                            </div>
                            <h6 class="fw-bold mt-2"><?= htmlspecialchars($prop['title']) ?></h6>
                            <div class="price">₹ <?= indianCurrencyFormat($prop['price']) ?> <span class="fs-6 fw-normal text-muted">Reserve Price</span></div>
                            <p class="text-muted small mt-1"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></p>
                            
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <a href="property_detail.php?id=<?= $prop['id'] ?>" class="btn-auction"><i class="fas fa-eye"></i> View Details</a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-secondary w-100">Login to View</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php }
        } else { echo "<p class='text-center text-muted'>No properties match your search.</p>"; }
        ?>
    </div>
</div>
</body>
</html>
