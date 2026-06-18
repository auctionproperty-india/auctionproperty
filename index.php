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
        <h2 class="text-center mb-4">Available Properties</h2>
        <div class="row">
            <?php
            try {
                $stmt = $pdo->query("SELECT * FROM properties WHERE status = 'available' ORDER BY id DESC");
                $properties = $stmt->fetchAll();
                if(count($properties) > 0) {
                    foreach($properties as $prop) { ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100 shadow">
                                <img src="<?= htmlspecialchars($prop['image_url']) ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                                <div class="card-body">
                                    <h5><?= htmlspecialchars($prop['title']) ?></h5>
                                    <p>₹ <?= number_format($prop['price'], 2) ?></p>
                                    <p><small><?= htmlspecialchars($prop['location']) ?></small></p>
                                    <?php if(isset($_SESSION['user_id'])): ?>
                                        <button class="btn btn-success w-100" disabled>Buy (Coming Soon)</button>
                                    <?php else: ?>
                                        <a href="login.php" class="btn btn-secondary w-100">Login to Buy</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php }
                } else { echo "<p class='text-center text-muted'>📭 No properties added yet.</p>"; }
            } catch(Exception $e) { echo "<p class='text-danger'>Error: ".$e->getMessage()."</p>"; }
            ?>
        </div>
    </div>
</body>
</html>
