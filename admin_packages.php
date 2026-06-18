<?php
require_once 'db.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_prices'])) {
    foreach($_POST['price'] as $id => $price) {
        $pdo->prepare("UPDATE packages SET price = ? WHERE id = ?")->execute([$price, $id]);
    }
    header("Location: admin_packages.php?updated=1");
    exit;
}
include 'header.php'; 
?>
<div class="card-premium">
    <h4><i class="fas fa-edit me-2"></i>Manage Package Prices</h4>
    <?php if(isset($_GET['updated'])) echo "<div class='alert alert-success'>✅ Prices Updated!</div>"; ?>
    <form method="POST">
        <table class="table table-bordered">
            <thead><tr><th>Package</th><th>Duration</th><th>Price (₹)</th></tr></thead>
            <tbody>
            <?php 
            $packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
            foreach($packages as $pkg): ?>
                <tr>
                    <td><?= htmlspecialchars($pkg['name']) ?></td>
                    <td><?= $pkg['duration_months'] ?> Months</td>
                    <td><input type="number" step="0.01" name="price[<?= $pkg['id'] ?>]" value="<?= $pkg['price'] ?>" class="form-control" style="width:150px;"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" name="update_prices" class="btn btn-primary">Update Prices</button>
    </form>
</div>
<?php include 'footer.php'; ?>
