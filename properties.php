<?php 
require_once 'db.php'; 
include 'header.php'; 

// सिर्फ Admin को ही यहाँ आने दें
if($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $stmt = $pdo->prepare("INSERT INTO properties (title, description, price, location, city, type, google_location, image_url) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$_POST['title'], $_POST['description'], $_POST['price'], $_POST['location'], $_POST['city'], $_POST['type'], $_POST['google_location'], $_POST['image_url']]);
    echo "<div class='alert alert-success'>✅ Property Added!</div>";
}
?>
<div class="card-premium mb-4">
    <h5>➕ Add New Property</h5>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-4"><input name="title" placeholder="Title" class="form-control" required></div>
            <div class="col-md-4"><input name="location" placeholder="Address" class="form-control" required></div>
            <div class="col-md-4"><input name="city" placeholder="City" class="form-control" required></div>
            <div class="col-md-3"><input name="price" placeholder="Price" class="form-control" required></div>
            <div class="col-md-3">
                <select name="type" class="form-control" required>
                    <option value="Flat">Flat</option>
                    <option value="Plot">Plot</option>
                    <option value="Shop">Shop</option>
                    <option value="Dukan">Dukan</option>
                    <option value="Land">Land</option>
                    <option value="Row House">Row House</option>
                    <option value="Bungalow">Bungalow</option>
                </select>
            </div>
            <div class="col-md-6"><input name="google_location" placeholder="Google Map Link" class="form-control"></div>
            <div class="col-12"><textarea name="description" placeholder="Description" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><input name="image_url" placeholder="Image URL" class="form-control"></div>
            <div class="col-12"><button type="submit" name="add_property" class="btn btn-primary">Add Property</button></div>
        </div>
    </form>
</div>

<div class="card-premium">
    <h5>📋 All Properties</h5>
    <!-- Mobile के लिए Scrollable Table -->
    <div class="table-responsive">
        <table class="table table-hover mt-2">
            <thead class="table-light"><tr><th>ID</th><th>Title</th><th>City</th><th>Type</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php 
            $stmt = $pdo->query("SELECT * FROM properties ORDER BY id DESC");
            while($row = $stmt->fetch()) { ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['city'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['type'] ?? '') ?></td>
                    <td>₹<?= $row['price'] ?></td>
                    <td><span class="badge bg-<?= ($row['status']=='available')?'success':'secondary' ?>"><?= $row['status'] ?></span></td>
                    <td>
                        <a href="edit_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
                        <a href="delete_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">🗑️</a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
