<?php require_once 'db.php'; ?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏠 PropertyDeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">🏠 PropertyDeal</a>
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
        <!-- Search Form -->
        <div class="card p-4 shadow-sm mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="city" placeholder="City (e.g. Delhi)" class="form-control" value="<?= $_GET['city'] ?? '' ?>">
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="Flat" <?= ($_GET['type']??'')=='Flat'?'selected':'' ?>>Flat</option>
                        <option value="Plot" <?= ($_GET['type']??'')=='Plot'?'selected':'' ?>>Plot</option>
                        <option value="Shop" <?= ($_GET['type']??'')=='Shop'?'selected':'' ?>>Shop</option>
                        <option value="Dukan" <?= ($_GET['type']??'')=='Dukan'?'selected':'' ?>>Dukan</option>
                        <option value="Land" <?= ($_GET['type']??'')=='Land'?'selected':'' ?>>Land</option>
                        <option value="Row House" <?= ($_GET['type']??'')=='Row House'?'selected':'' ?>>Row House</option>
                        <option value="Bungalow" <?= ($_GET['type']??'')=='Bungalow'?'selected':'' ?>>Bungalow</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="min_price" placeholder="Min Price" class="form-control" value="<?= $_GET['min_price'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <input type="number" name="max_price" placeholder="Max Price" class="form-control" value="<?= $_GET['max_price'] ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">🔍 Search</button>
                </div>
            </form>
        </div>

        <h2 class="text-center mb-4">Available Properties</h2>
        <div class="row">
            <?php
            // Query बनाएँ
            $sql = "SELECT * FROM properties WHERE status = 'available'";
            $params = [];
            if(!empty($_GET['city'])) {
                $sql .= " AND city ILIKE ?";
                $params[] = '%'.$_GET['city'].'%';
            }
            if(!empty($_GET['type'])) {
                $sql .= " AND type = ?";
                $params[] = $_GET['type'];
            }
            if(!empty($_GET['min_price'])) {
                $sql .= " AND price >= ?";
                $params[] = $_GET['min_price'];
            }
            if(!empty($_GET['max_price'])) {
                $sql .= " AND price <= ?";
                $params[] = $_GET['max_price'];
            }
            $sql .= " ORDER BY id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $properties = $stmt->fetchAll();

            if(count($properties) > 0) {
                foreach($properties as $prop) { ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 shadow">
                            <img src="<?= htmlspecialchars($prop['image_url']) ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                            <div class="card-body">
                                <h5><?= htmlspecialchars($prop['title']) ?></h5>
                                <p><strong>📍 <?= htmlspecialchars($prop['city']) ?></strong> (<?= $prop['type'] ?>)</p>
                                <p>₹ <?= number_format($prop['price'], 2) ?></p>
                                <p><small><?= htmlspecialchars($prop['location']) ?></small></p>
                                <?php if(isset($_SESSION['user_id'])): ?>
                                    <!-- आगे Step 2 में इसे Detail पेज पर भेजेंगे -->
                                    <a href="property_detail.php?id=<?= $prop['id'] ?>" class="btn btn-info w-100">View Details</a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-secondary w-100">Login to View</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php }
            } else { echo "<p class='text-center text-muted'>📭 No properties match your search.</p>"; }
            ?>
        </div>
    </div>
</body>
</html>
