<?php require_once 'db.php'; include 'header.php'; 
$id = $_GET['id'] ?? 0;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("UPDATE properties SET title=?, description=?, price=?, location=?, city=?, type=?, google_location=?, image_url=? WHERE id=?");
    $stmt->execute([$_POST['title'], $_POST['description'], $_POST['price'], $_POST['location'], $_POST['city'], $_POST['type'], $_POST['google_location'], $_POST['image_url'], $id]);
    echo "<div class='alert alert-success'>✅ Updated! <a href='properties.php'>Back</a></div>";
}

$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$id]);
$prop = $stmt->fetch();
if(!$prop) die("Not found");
?>
<div class="card p-4 shadow-sm">
    <h4>✏️ Edit Property</h4>
    <form method="POST">
        <input name="title" value="<?= htmlspecialchars($prop['title']) ?>" class="form-control mb-2" required>
        <input name="location" value="<?= htmlspecialchars($prop['location']) ?>" class="form-control mb-2" required>
        <input name="city" value="<?= htmlspecialchars($prop['city'] ?? '') ?>" class="form-control mb-2" required>
        <input name="price" value="<?= $prop['price'] ?>" class="form-control mb-2" required>
        <select name="type" class="form-control mb-2">
            <option value="Flat" <?= ($prop['type']=='Flat')?'selected':'' ?>>Flat</option>
            <option value="Plot" <?= ($prop['type']=='Plot')?'selected':'' ?>>Plot</option>
            <option value="Shop" <?= ($prop['type']=='Shop')?'selected':'' ?>>Shop</option>
            <option value="Dukan" <?= ($prop['type']=='Dukan')?'selected':'' ?>>Dukan</option>
            <option value="Land" <?= ($prop['type']=='Land')?'selected':'' ?>>Land</option>
            <option value="Row House" <?= ($prop['type']=='Row House')?'selected':'' ?>>Row House</option>
            <option value="Bungalow" <?= ($prop['type']=='Bungalow')?'selected':'' ?>>Bungalow</option>
        </select>
        <input name="google_location" value="<?= htmlspecialchars($prop['google_location'] ?? '') ?>" class="form-control mb-2">
        <textarea name="description" class="form-control mb-2" rows="2"><?= htmlspecialchars($prop['description']) ?></textarea>
        <input name="image_url" value="<?= htmlspecialchars($prop['image_url']) ?>" class="form-control mb-2">
        <button type="submit" class="btn btn-success">Update</button>
        <a href="properties.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php include 'footer.php'; ?>
