<?php
// ============================================================
// 📦 Admin: Manage Packages (with Features) – PostgreSQL
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'header.php';

// ----- Handle Add/Edit/Delete -----
$message = '';

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM packages WHERE id = ?")->execute([$id]);
    $message = "<div class='alert alert-success'>✅ Package deleted.</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $discount_price = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
    $duration_months = (int)$_POST['duration_months'];
    $features = trim($_POST['features']);

    if ($id > 0) {
        // Update
        $stmt = $pdo->prepare("UPDATE packages SET name = ?, price = ?, discount_price = ?, duration_months = ?, features = ? WHERE id = ?");
        $stmt->execute([$name, $price, $discount_price, $duration_months, $features, $id]);
        $message = "<div class='alert alert-success'>✅ Package updated.</div>";
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO packages (name, price, discount_price, duration_months, features) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $discount_price, $duration_months, $features]);
        $message = "<div class='alert alert-success'>✅ Package added.</div>";
    }
}

// ----- Fetch all packages -----
$packages = $pdo->query("SELECT * FROM packages ORDER BY duration_months")->fetchAll();
?>

<div class="card-premium">
    <h4><i class="fas fa-boxes me-2"></i>Manage Packages</h4>
    <?= $message ?>

    <!-- Add/Edit Form -->
    <div class="bg-light p-3 mb-4 rounded">
        <h5 id="form-title">Add New Package</h5>
        <form method="POST">
            <input type="hidden" name="id" id="package-id" value="0">
            <div class="row g-3">
                <div class="col-md-4">
                    <label>Package Name</label>
                    <input type="text" name="name" id="pkg-name" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label>Price (₹)</label>
                    <input type="number" step="0.01" name="price" id="pkg-price" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label>Discount Price</label>
                    <input type="number" step="0.01" name="discount_price" id="pkg-discount" class="form-control">
                </div>
                <div class="col-md-2">
                    <label>Duration (months)</label>
                    <input type="number" name="duration_months" id="pkg-duration" class="form-control" required>
                </div>
                <div class="col-md-12">
                    <label>Features / Benefits (each on new line)</label>
                    <textarea name="features" id="pkg-features" rows="5" class="form-control" placeholder="e.g. Validity: 1 month&#10;Property Search: All India&#10;Support: 24x7"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Save Package</button>
                    <button type="reset" class="btn btn-secondary" onclick="resetForm()">Cancel</button>
                </div>
            </div>
        </form>
    </div>

    <!-- List of Packages -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Price</th>
                <th>Discount</th>
                <th>Duration</th>
                <th>Features</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($packages as $pkg): ?>
                <tr>
                    <td><?= $pkg['id'] ?></td>
                    <td><?= htmlspecialchars($pkg['name']) ?></td>
                    <td>₹<?= number_format($pkg['price'], 2) ?></td>
                    <td><?= $pkg['discount_price'] ? '₹'.number_format($pkg['discount_price'], 2) : '-' ?></td>
                    <td><?= $pkg['duration_months'] ?> mo</td>
                    <td><small><?= nl2br(htmlspecialchars($pkg['features'])) ?></small></td>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="editPackage(<?= htmlspecialchars(json_encode($pkg)) ?>)">Edit</button>
                        <a href="?delete=<?= $pkg['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this package?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function editPackage(data) {
    document.getElementById('package-id').value = data.id;
    document.getElementById('pkg-name').value = data.name;
    document.getElementById('pkg-price').value = data.price;
    document.getElementById('pkg-discount').value = data.discount_price || '';
    document.getElementById('pkg-duration').value = data.duration_months;
    document.getElementById('pkg-features').value = data.features || '';
    document.getElementById('form-title').innerText = 'Edit Package';
}

function resetForm() {
    document.getElementById('package-id').value = 0;
    document.getElementById('pkg-name').value = '';
    document.getElementById('pkg-price').value = '';
    document.getElementById('pkg-discount').value = '';
    document.getElementById('pkg-duration').value = '';
    document.getElementById('pkg-features').value = '';
    document.getElementById('form-title').innerText = 'Add New Package';
}
</script>

<?php include 'footer.php'; ?>
