<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];

// ✅ Unlimited Properties – No limit check
// The following limit check has been removed to allow unlimited properties.
// You can now add as many properties as you want.

$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $type = trim($_POST['type']);
    $sqft = (float)($_POST['sqft'] ?? 0);
    $construction_sqft = (float)($_POST['construction_sqft'] ?? 0);
    $image_url = '';

    if(empty($title) || $price <= 0) {
        $error = "❌ Title and Price are required.";
    } else {
        // Handle image upload
        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $upload_dir = 'uploads/user_properties/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = 'userprop_' . $user_id . '_' . time() . '.' . $ext;
            $target_path = $upload_dir . $filename;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_url = $target_path;
            } else {
                $error = "❌ Failed to upload image. Please check directory permissions.";
            }
        }

        if(empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO user_properties (user_id, title, description, price, city, state, type, sqft, construction_sqft, image_url, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $title, $description, $price, $city, $state, $type, $sqft, $construction_sqft, $image_url]);
            header("Location: user_properties.php?msg=added");
            exit;
        }
    }
}

include 'header.php'; 
?>
<div class="container-fluid">
    <h4><i class="fas fa-plus-circle me-2"></i>Add Your Property</h4>
    <?php if($error) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <div class="card p-4">
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" step="0.01" name="price" class="form-control" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="Flat">Flat</option>
                        <option value="Plot">Plot</option>
                        <option value="Shop">Shop</option>
                        <option value="Land">Land</option>
                        <option value="House">House</option>
                        <option value="Row House">Row House</option>
                        <option value="Bungalow">Bungalow</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Area (Sq Ft)</label>
                    <input type="number" step="0.01" name="sqft" class="form-control" placeholder="e.g. 1200">
                </div>
                <!-- Construction Area -->
                <div class="col-md-3">
                    <label class="form-label">Construction Area (Sq Ft)</label>
                    <input type="number" step="0.01" name="construction_sqft" class="form-control" placeholder="e.g. 800">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Upload Image (1 photo)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small class="text-muted">Max file size: 5MB. Supported: JPG, PNG, GIF.</small>
                </div>
            </div>
            <button type="submit" name="add_property" class="btn btn-primary mt-3">Submit Property</button>
            <a href="user_properties.php" class="btn btn-secondary mt-3">Cancel</a>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>
