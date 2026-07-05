<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? 0;
if(!$id) { header("Location: user_properties.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM user_properties WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$prop = $stmt->fetch();
if(!$prop) { header("Location: user_properties.php"); exit; }

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_property'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $type = trim($_POST['type']);
    $sqft = (float)($_POST['sqft'] ?? 0);
    $construction_sqft = (float)($_POST['construction_sqft'] ?? 0);
    $image_url = $prop['image_url'];

    if(empty($title) || $price <= 0) {
        $error = "❌ Title and Price are required.";
    } else {
        // Handle new image upload
        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $upload_dir = 'uploads/user_properties/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'userprop_' . $user_id . '_' . time() . '.' . $ext;
            $target_path = $upload_dir . $filename;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                // Delete old image if exists
                if($prop['image_url'] && file_exists($prop['image_url'])) {
                    unlink($prop['image_url']);
                }
                $image_url = $target_path;
            } else {
                $error = "❌ Failed to upload new image.";
            }
        }

        if(empty($error)) {
            $stmt = $pdo->prepare("UPDATE user_properties SET 
                title=?, description=?, price=?, city=?, state=?, type=?, sqft=?, construction_sqft=?, image_url=?, updated_at=CURRENT_TIMESTAMP 
                WHERE id=? AND user_id=?");
            $stmt->execute([$title, $description, $price, $city, $state, $type, $sqft, $construction_sqft, $image_url, $id, $user_id]);
            header("Location: user_properties.php?msg=updated");
            exit;
        }
    }
}

include 'header.php'; 
?>
<div class="container-fluid">
    <h4><i class="fas fa-edit me-2"></i>Edit Property</h4>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <div class="card p-4">
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($prop['title']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?= $prop['price'] ?>" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($prop['description']) ?></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($prop['city']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($prop['state']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="Flat" <?= ($prop['type']=='Flat')?'selected':'' ?>>Flat</option>
                        <option value="Plot" <?= ($prop['type']=='Plot')?'selected':'' ?>>Plot</option>
                        <option value="Shop" <?= ($prop['type']=='Shop')?'selected':'' ?>>Shop</option>
                        <option value="Land" <?= ($prop['type']=='Land')?'selected':'' ?>>Land</option>
                        <option value="House" <?= ($prop['type']=='House')?'selected':'' ?>>House</option>
                        <option value="Row House" <?= ($prop['type']=='Row House')?'selected':'' ?>>Row House</option>
                        <option value="Bungalow" <?= ($prop['type']=='Bungalow')?'selected':'' ?>>Bungalow</option>
                        <option value="Other" <?= ($prop['type']=='Other')?'selected':'' ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Area (Sq Ft)</label>
                    <input type="number" step="0.01" name="sqft" class="form-control" value="<?= $prop['sqft'] ?? '' ?>" placeholder="e.g. 1200">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Construction Area (Sq Ft)</label>
                    <input type="number" step="0.01" name="construction_sqft" class="form-control" value="<?= $prop['construction_sqft'] ?? '' ?>" placeholder="e.g. 800">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Current Image</label><br>
                    <?php if($prop['image_url'] && file_exists($prop['image_url'])): ?>
                        <img src="<?= $prop['image_url'] ?>" style="max-height:150px; border-radius:10px; margin-bottom:10px;">
                    <?php else: ?>
                        <p class="text-muted">No image</p>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small>Leave empty to keep current image.</small>
                </div>
            </div>
            <button type="submit" name="update_property" class="btn btn-primary mt-3">Update Property</button>
            <a href="user_properties.php" class="btn btn-secondary mt-3">Cancel</a>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
