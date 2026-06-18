<?php require_once 'db.php'; include 'header.php'; 
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $stmt = $pdo->prepare("INSERT INTO properties (title, description, price, location, city, type, google_location, image_url) VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([$_POST['title'], $_POST['description'], $_POST['price'], $_POST['location'], $_POST['city'], $_POST['type'], $_POST['google_location'], $_POST['image_url']]);
    echo "<div class='alert alert-success'>✅ Property Added!</div>";
}
?>
<div class="card p-4 shadow-sm mb-4">
    <h5>➕ Add New Property</h5>
    <form method="POST">
        <div class="row">
            <div class="col-md-4"><input name="title" placeholder="Title" class="form-control" required></div>
            <div class="col-md-4"><input name="location" placeholder="Address" class="form-control" required></div>
            <div class="col-md-4"><input name="city" placeholder="City (e.g. Mumbai)" class="form-control" required></div>
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
            <div class="col-md-6"><input name="google_location" placeholder="Google Map Link (URL)" class="form-control"></div>
            <div class="col-12 mt-2"><textarea name="description" placeholder="Description" class="form-control" rows="2"></textarea></div>
            <div class="col-12 mt-2"><input name="image_url" placeholder="Image URL" class="form-control"></div>
            <div class="col-12 mt-2"><button type="submit" name="add_property" class="btn btn-primary">Add Property</button></div>
        </div>
    </form>
</div>
<table class="table table-bordered table-hover bg-white shadow-sm">
    <thead><tr><th>ID</th><th>Title</th><th>City</th><th>Type</th><th>Price</th><th>Status</th></tr></thead>
    <tbody>
    <?php 
    $stmt = $pdo->query("SELECT * FROM properties ORDER BY id DESC");
    while($row = $stmt->fetch()) { ?>
        <tr><td><?= $row['id'] ?></td><td><?= htmlspecialchars($row['title']) ?></td><td><?= $row['city'] ?></td><td><?= $row['type'] ?></td><td>₹<?= $row['price'] ?></td><td><span class="badge bg-<?= ($row['status']=='available')?'success':'secondary' ?>"><?= $row['status'] ?></span></td></tr>
    <?php } ?>
    </tbody>
</table>
<?php include 'footer.php'; ?>
