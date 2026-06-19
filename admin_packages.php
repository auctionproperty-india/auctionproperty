<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_prices'])) {
    foreach($_POST['price'] as $id => $price) {
        $discount = $_POST['discount'][$id] ?? null;
        $pdo->prepare("UPDATE packages SET price = ?, discount_price = ? WHERE id = ?")->execute([$price, $discount ?: null, $id]);
    }
    header("Location: admin_packages.php?updated=1");
    exit;
}

include 'header.php'; 
$packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-tags me-2"></i>Manage Package Prices & Discounts</h4>
    <?php if(isset($_GET['updated'])) echo "<div class='alert alert-success'>✅ Prices Updated!</div>"; ?>
    <form method="POST">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Package</th><th>Duration</th><th>Regular Price (₹)</th><th>Discount Price (₹)</th></tr></thead>
                <tbody>
                <?php foreach($packages as $pkg): ?>
                    <tr>
                        <td><?= htmlspecialchars($pkg['name']) ?></td>
                        <td><?= $pkg['duration_months'] ?> Months</td>
                        <td><input type="number" step="0.01" name="price[<?= $pkg['id'] ?>]" value="<?= $pkg['price'] ?>" class="form-control" style="width:150px;" required></td>
                        <td>
                            <input type="number" step="0.01" name="discount[<?= $pkg['id'] ?>]" value="<?= $pkg['discount_price'] ?>" class="form-control" style="width:150px;" placeholder="Leave empty for no discount">
                            <small class="text-muted">Discount price (if set) will be shown on user dashboard.</small>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" name="update_prices" class="btn btn-primary">Save Changes</button>
    </form>
</div>
<?php include 'footer.php'; ?>
