<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }
if(!hasPermission('packages', $pdo)) { die("Permission denied."); }

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_prices'])) {
    $password_attempt = $_POST['admin_password'] ?? '';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if(!$admin || !password_verify($password_attempt, $admin['password'])) {
        $error = "❌ Incorrect Admin Password!";
    } else {
        foreach($_POST['price'] as $id => $price) {
            $discount = $_POST['discount'][$id] ?? null;
            $bonus = $_POST['referral_bonus'][$id] ?? 0;
            $pdo->prepare("UPDATE packages SET price = ?, discount_price = ?, referral_bonus = ? WHERE id = ?")->execute([$price, $discount ?: null, $bonus, $id]);
        }
        header("Location: admin_packages.php?updated=1");
        exit;
    }
}

include 'header.php'; 
$packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
?>
<div class="card-premium">
    <h4><i class="fas fa-tags me-2"></i>Manage Packages (Price, Discount, Referral Bonus)</h4>
    <?php if(isset($_GET['updated'])) echo "<div class='alert alert-success'>✅ Updated!</div>"; ?>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="POST">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Package</th><th>Duration</th><th>Price (₹)</th><th>Discount (₹)</th><th>Referral Bonus (₹)</th></tr></thead>
                <tbody>
                <?php foreach($packages as $pkg): ?>
                    <tr>
                        <td><?= htmlspecialchars($pkg['name']) ?></td>
                        <td><?= $pkg['duration_months'] ?> Months</td>
                        <td><input type="number" step="0.01" name="price[<?= $pkg['id'] ?>]" value="<?= $pkg['price'] ?>" class="form-control" style="width:150px;" required></td>
                        <td><input type="number" step="0.01" name="discount[<?= $pkg['id'] ?>]" value="<?= $pkg['discount_price'] ?>" class="form-control" style="width:150px;" placeholder="Empty"></td>
                        <td><input type="number" step="0.01" name="referral_bonus[<?= $pkg['id'] ?>]" value="<?= $pkg['referral_bonus'] ?>" class="form-control" style="width:150px;" required></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <label class="fw-bold">Verify Admin Password *</label>
                <input type="password" name="admin_password" class="form-control" placeholder="Enter password to save" required>
            </div>
        </div>
        <button type="submit" name="update_prices" class="btn btn-primary mt-3">Save Changes</button>
    </form>
</div>
<?php include 'footer.php'; ?>
