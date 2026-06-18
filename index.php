<?php 
require_once 'db.php'; 
require_once 'functions.php'; // Format function include करें
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
        body { background: #f4f7fc; }
        .property-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.03); transition: 0.3s; border: 1px solid #e9edf4; height: 100%; }
        .property-card:hover { transform: translateY(-8px); box-shadow: 0 20px 35px rgba(0,0,0,0.08); }
        .property-card img { height: 220px; object-fit: cover; width: 100%; }
        .property-card .card-body { padding: 20px; }
        .bank-badge { font-size: 12px; font-weight: 600; color: #1e3a8a; background: #e0e7ff; padding: 4px 12px; border-radius: 30px; }
        .price { font-size: 22px; font-weight: 800; color: #0b1120; }
        .btn-auction { background: #1e3a8a; border: none; color: white; font-weight: 700; padding: 10px; border-radius: 12px; width: 100%; transition: 0.3s; display: block; text-align: center; text-decoration: none; }
        .btn-auction:hover { background: #0b1d4a; color: #fff; }
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
    <div class="row">
        <?php
        $sql = "SELECT * FROM properties WHERE status = 'available' ORDER BY id DESC";
        $stmt = $pdo->query($sql);
        $properties = $stmt->fetchAll();
        if(count($properties) > 0) {
            foreach($properties as $prop) { ?>
                <div class="col-md-4 mb-4">
                    <div class="property-card">
                        <img src="<?= htmlspecialchars($prop['image_url'] ?: 'https://via.placeholder.com/600x400?text=Property') ?>" alt="Property">
                        <div class="card-body">
                            <!-- Bank & Date -->
                            <div class="d-flex justify-content-between">
                                <span class="bank-badge">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                                <span class="text-muted small"><i class="far fa-calendar"></i> <?= date('d M Y', strtotime($prop['auction_date'] ?? 'now')) ?></span>
                            </div>
                            <!-- Title (Only) -->
                            <h6 class="fw-bold mt-2"><?= htmlspecialchars($prop['title']) ?></h6>
                            <!-- Price in Indian Format -->
                            <div class="price mt-2">₹ <?= indianCurrencyFormat($prop['price']) ?> <span class="fs-6 fw-normal text-muted">Starting Bid</span></div>
                            <!-- Location / City (Optional, small text) -->
                            <p class="text-muted small mt-1"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></p>
                            
                            <!-- Button -->
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <a href="property_detail.php?id=<?= $prop['id'] ?>" class="btn-auction"><i class="fas fa-eye"></i> View Details</a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-secondary w-100">Login to View</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php }
        } else { echo "<p class='text-center text-muted'>No properties available.</p>"; }
        ?>
    </div>
</div>
</body>
</html>
