<?php 
require_once 'db.php'; 
include 'header.php'; 

if($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $stmt = $pdo->prepare("INSERT INTO properties (title, description, price, location, city, type, google_location, image_url, bank_name, sqft, possession_type, auction_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['title'], $_POST['description'], $_POST['price'], $_POST['location'], 
        $_POST['city'], $_POST['type'], $_POST['google_location'], $_POST['image_url'],
        $_POST['bank_name'], $_POST['sqft'], $_POST['possession_type'], $_POST['auction_date']
    ]);
    header("Location: properties.php?added=1#add-form");
    exit;
}
?>

<?php if(isset($_GET['added'])): ?>
    <div class="alert alert-success alert-dismissible fade show">✅ Property Added!</div>
<?php endif; ?>

<!-- Admin Add Form -->
<div class="card-premium" id="add-form" style="border-left: 4px solid #fbbf24;">
    <h5><i class="fas fa-plus-circle me-2" style="color: #fbbf24;"></i>Add Auction Property</h5>
    <form method="POST" class="mt-3">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label fw-semibold">Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Address</label><input type="text" name="location" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label fw-semibold">City</label><input type="text" name="city" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Price (₹)</label><input type="number" step="0.01" name="price" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Bank Name</label><input type="text" name="bank_name" class="form-control" placeholder="e.g. Bank of Baroda"></div>
            <div class="col-md-3"><label class="form-label fw-semibold">Sq. Ft.</label><input type="number" step="0.01" name="sqft" class="form-control" placeholder="e.g. 678"></div>
            <div class="col-md-4"><label class="form-label fw-semibold">Property Type</label>
                <select name="type" class="form-control">
                    <option value="Flat">Flat</option><option value="Plot">Plot</option>
                    <option value="Shop">Shop</option><option value="Dukan">Dukan</option>
                    <option value="Land">Land</option><option value="Row House">Row House</option><option value="Bungalow">Bungalow</option>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label fw-semibold">Possession Type</label>
                <select name="possession_type" class="form-control">
                    <option value="Physical">Physical Possession</option>
                    <option value="Symbolic">Symbolic Possession</option>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label fw-semibold">Auction Date</label>
                <input type="date" name="auction_date" class="form-control">
            </div>
            <div class="col-12"><label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6"><label class="form-label fw-semibold">Image URL</label>
                <input type="text" name="image_url" class="form-control" placeholder="https://...">
            </div>
            <div class="col-md-6"><label class="form-label fw-semibold">Google Map Link</label>
                <input type="text" name="google_location" class="form-control" placeholder="https://maps.google.com/...">
            </div>
            <div class="col-12"><button type="submit" name="add_property" class="btn btn-primary btn-lg w-100"><i class="fas fa-save me-2"></i>Add Property</button></div>
        </div>
    </form>
</div>

<!-- Property List -->
<div class="card-premium mt-4">
    <h5>📋 All Properties</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light"><tr><th>ID</th><th>Title</th><th>Bank</th><th>City</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php 
            $stmt = $pdo->query("SELECT * FROM properties ORDER BY id DESC");
            while($row = $stmt->fetch()) { ?>
                <tr><td><?= $row['id'] ?></td><td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['bank_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['city'] ?? '') ?></td>
                <td>₹<?= number_format($row['price'], 2) ?></td>
                <td><span class="badge bg-<?= ($row['status']=='available')?'success':'secondary' ?>"><?= $row['status'] ?></span></td>
                <td>
                    <a href="edit_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
                    <a href="delete_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">🗑️</a>
                </td></tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
