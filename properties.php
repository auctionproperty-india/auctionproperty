<?php 
require_once 'db.php'; 
require_once 'functions.php'; 

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

$default_contact = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='default_contact'")->fetchColumn();
if(!$default_contact) $default_contact = '9238215516';

// ---- ADD PROPERTY ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_property'])) {
    $image_path = '';
    $use_uploaded_image = false;
    if(isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $upload_dir = 'uploads/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $filename);
        $image_path = $upload_dir . $filename;
        $use_uploaded_image = true;
    }

    $auction_date_db = null;
    if(!empty($_POST['auction_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['auction_date']);
        if($date_obj) $auction_date_db = $date_obj->format('Y-m-d');
    }

    $sql = "INSERT INTO properties (
        title, description, price, location, city, type, google_location, image_url, 
        bank_name, sqft, possession_type, auction_date, 
        borrower_name, emd_amount, bid_increment, emd_deadline, 
        auction_start_time, auction_end_time, locality, reserve_price_per_sqft, contact_number
    ) VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['title'], $_POST['price'], $_POST['location'], 
        $_POST['city'], $_POST['type'], $_POST['google_location'], $image_path,
        $_POST['bank_name'], $_POST['sqft'], $_POST['possession_type'], $auction_date_db,
        $_POST['borrower_name'], $_POST['emd_amount'], $_POST['bid_increment'], $_POST['emd_deadline'],
        $_POST['auction_start_time'], $_POST['auction_end_time'], $_POST['locality'], 
        $_POST['reserve_price_per_sqft'], $_POST['contact_number']
    ]);
    
    $new_id = $pdo->lastInsertId();

    if (!$use_uploaded_image) {
        $new_prop = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
        $new_prop->execute([$new_id]);
        $prop_data = $new_prop->fetch();
        $generated_path = generateSocialCard($prop_data);
        if ($generated_path) {
            $pdo->prepare("UPDATE properties SET image_url = ? WHERE id = ?")->execute([$generated_path, $new_id]);
        }
    }

    header("Location: properties.php?added=1#add-form");
    exit;
}

// ---- EDIT PROPERTY ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_property'])) {
    $id = $_POST['property_id'];
    $image_path = $_POST['existing_image'];
    $use_uploaded_image = false;

    if(isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $upload_dir = 'uploads/';
        if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['image_file']['tmp_name'], $upload_dir . $filename);
        $image_path = $upload_dir . $filename;
        $use_uploaded_image = true;
    }

    $auction_date_db = null;
    if(!empty($_POST['auction_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['auction_date']);
        if($date_obj) $auction_date_db = $date_obj->format('Y-m-d');
    }

    $sql = "UPDATE properties SET 
        title=?, description='', price=?, location=?, city=?, type=?, google_location=?, image_url=?, 
        bank_name=?, sqft=?, possession_type=?, auction_date=?, 
        borrower_name=?, emd_amount=?, bid_increment=?, emd_deadline=?, 
        auction_start_time=?, auction_end_time=?, locality=?, reserve_price_per_sqft=?, contact_number=? 
        WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['title'], $_POST['price'], $_POST['location'], 
        $_POST['city'], $_POST['type'], $_POST['google_location'], $image_path,
        $_POST['bank_name'], $_POST['sqft'], $_POST['possession_type'], $auction_date_db,
        $_POST['borrower_name'], $_POST['emd_amount'], $_POST['bid_increment'], $_POST['emd_deadline'],
        $_POST['auction_start_time'], $_POST['auction_end_time'], $_POST['locality'], 
        $_POST['reserve_price_per_sqft'], $_POST['contact_number'], $id
    ]);

    if (!$use_uploaded_image) {
        $updated_prop = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
        $updated_prop->execute([$id]);
        $prop_data = $updated_prop->fetch();
        $generated_path = generateSocialCard($prop_data);
        if ($generated_path) {
            $pdo->prepare("UPDATE properties SET image_url = ? WHERE id = ?")->execute([$generated_path, $id]);
        }
    }

    header("Location: properties.php?updated=1");
    exit;
}

include 'header.php'; 
?>

<?php if(isset($_GET['added'])): ?>
    <div class="alert alert-success">✅ Property Added Successfully!</div>
<?php endif; ?>
<?php if(isset($_GET['updated'])): ?>
    <div class="alert alert-success">✅ Property Updated Successfully!</div>
<?php endif; ?>

<!-- ===== ADD / EDIT FORM ===== -->
<div class="card-premium" id="add-form" style="border-left: 4px solid #fbbf24;">
    <h5><i class="fas fa-<?= isset($_GET['edit']) ? 'edit' : 'plus-circle' ?> me-2" style="color: #fbbf24;"></i>
        <?= isset($_GET['edit']) ? 'Edit' : 'Add' ?> Auction Property
    </h5>
    <form method="POST" enctype="multipart/form-data" class="mt-3">
        <?php 
        $edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
        $prop = null;
        if($edit_id) {
            $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
            $stmt->execute([$edit_id]);
            $prop = $stmt->fetch();
            if(!$prop) { echo "<div class='alert alert-danger'>Property not found!</div>"; $edit_id = 0; }
        }
        if($edit_id && $prop):
            // Edit Mode - show hidden input
        ?>
            <input type="hidden" name="property_id" value="<?= $edit_id ?>">
            <input type="hidden" name="existing_image" value="<?= htmlspecialchars($prop['image_url'] ?? '') ?>">
            <?php $display_date = !empty($prop['auction_date']) ? date('d/m/Y', strtotime($prop['auction_date'])) : ''; ?>
        <?php endif; ?>

        <div class="row g-3">
            <!-- Basic Fields -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Title *</label>
                <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($prop['title'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Address *</label>
                <input type="text" name="location" id="location_input" class="form-control" placeholder="e.g. Sapna Sangeeta, Indore" required value="<?= htmlspecialchars($prop['location'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">City *</label>
                <input type="text" name="city" class="form-control" required value="<?= htmlspecialchars($prop['city'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Locality</label>
                <input type="text" name="locality" class="form-control" placeholder="e.g. Sapna Sangeeta" value="<?= htmlspecialchars($prop['locality'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Reserve Price (₹) *</label>
                <input type="number" step="0.01" name="price" class="form-control" required value="<?= $prop['price'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Price per Sq Ft</label>
                <input type="number" step="0.01" name="reserve_price_per_sqft" class="form-control" value="<?= $prop['reserve_price_per_sqft'] ?? '' ?>">
            </div>

            <!-- Bank & Borrower -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Bank Name</label>
                <input type="text" name="bank_name" class="form-control" placeholder="e.g. Union Bank" value="<?= htmlspecialchars($prop['bank_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Borrower Name</label>
                <input type="text" name="borrower_name" class="form-control" placeholder="e.g. Alka Agarwal" value="<?= htmlspecialchars($prop['borrower_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Property Type</label>
                <select name="type" class="form-control">
                    <option value="Flat" <?= ($prop['type']??'')=='Flat'?'selected':'' ?>>Flat</option>
                    <option value="Plot" <?= ($prop['type']??'')=='Plot'?'selected':'' ?>>Plot</option>
                    <option value="Shop" <?= ($prop['type']??'')=='Shop'?'selected':'' ?>>Shop</option>
                    <option value="Land" <?= ($prop['type']??'')=='Land'?'selected':'' ?>>Land</option>
                    <option value="Row House" <?= ($prop['type']??'')=='Row House'?'selected':'' ?>>Row House</option>
                    <option value="Bungalow" <?= ($prop['type']??'')=='Bungalow'?'selected':'' ?>>Bungalow</option>
                </select>
            </div>

            <!-- Area, Possession, EMD, Bid -->
            <div class="col-md-3">
                <label class="form-label fw-semibold">Area (Sq Ft)</label>
                <input type="number" step="0.01" name="sqft" class="form-control" value="<?= $prop['sqft'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Possession</label>
                <select name="possession_type" class="form-control">
                    <option value="Physical" <?= ($prop['possession_type']??'')=='Physical'?'selected':'' ?>>Physical</option>
                    <option value="Symbolic" <?= ($prop['possession_type']??'')=='Symbolic'?'selected':'' ?>>Symbolic</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">EMD Amount (₹)</label>
                <input type="number" step="0.01" name="emd_amount" class="form-control" value="<?= $prop['emd_amount'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Bid Increment (₹)</label>
                <input type="number" step="0.01" name="bid_increment" class="form-control" value="<?= $prop['bid_increment'] ?? '' ?>">
            </div>

            <!-- Auction Timings -->
            <div class="col-md-4">
                <label class="form-label fw-semibold">Auction Start</label>
                <input type="text" name="auction_start_time" class="form-control" placeholder="Wed, 24 Jun 2026 12:00 PM" value="<?= htmlspecialchars($prop['auction_start_time'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Auction End</label>
                <input type="text" name="auction_end_time" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM" value="<?= htmlspecialchars($prop['auction_end_time'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">EMD Deadline</label>
                <input type="text" name="emd_deadline" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM" value="<?= htmlspecialchars($prop['emd_deadline'] ?? '') ?>">
            </div>

            <!-- Date & Contact -->
            <div class="col-md-6">
                <label class="form-label fw-semibold">Auction Date (DD/MM/YYYY)</label>
                <input type="text" name="auction_date" class="form-control" placeholder="e.g. 24/06/2026" value="<?= htmlspecialchars($display_date ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($prop['contact_number'] ?? $default_contact) ?>" required>
            </div>

            <!-- Google Map Link (Auto-filled) -->
            <div class="col-12">
                <label class="form-label fw-semibold">Google Map Link (Auto-generated)</label>
                <input type="text" name="google_location" id="google_location" class="form-control" readonly style="background:#f1f5f9;" value="<?= htmlspecialchars($prop['google_location'] ?? '') ?>">
            </div>

            <!-- Image Upload -->
            <div class="col-12">
                <label class="form-label fw-semibold">Upload Property Image</label>
                <?php if(!empty($prop['image_url']) && file_exists($prop['image_url'])): ?>
                    <div class="mb-2">
                        <img src="<?= $prop['image_url'] ?>" style="max-height:120px; border-radius:10px;">
                    </div>
                <?php endif; ?>
                <input type="file" name="image_file" class="form-control" accept="image/*">
                <small class="text-muted"><?= $edit_id ? 'Leave empty to keep existing image (Auto-generate new).' : 'Optional: If no image uploaded, system will auto-generate a social card.' ?></small>
            </div>

            <!-- Submit Button -->
            <div class="col-12">
                <button type="submit" name="<?= $edit_id ? 'update_property' : 'add_property' ?>" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-save me-2"></i><?= $edit_id ? 'Update Property' : 'Add Property' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ===== PROPERTY LIST TABLE ===== -->
<div class="card-premium mt-4">
    <h5>📋 All Properties</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>ID</th><th>Title</th><th>Bank</th><th>City</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php 
            $stmt = $pdo->query("SELECT * FROM properties ORDER BY id DESC");
            while($row = $stmt->fetch()) { ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= htmlspecialchars($row['bank_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['city'] ?? '') ?></td>
                    <td>₹<?= number_format($row['price'], 2) ?></td>
                    <td><span class="badge bg-<?= ($row['status']=='available')?'success':'secondary' ?>"><?= $row['status'] ?></span></td>
                    <td>
                        <a href="properties.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
                        <a href="delete_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">🗑️</a>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Google Maps Autocomplete (Optional) -->
<script>
    function initAutocomplete() {
        var input = document.getElementById('location_input');
        if (input) {
            var autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.addListener('place_changed', function() {
                var place = autocomplete.getPlace();
                if (place.geometry) {
                    var lat = place.geometry.location.lat();
                    var lng = place.geometry.location.lng();
                    document.getElementById('google_location').value = 'https://www.google.com/maps?q=' + lat + ',' + lng;
                }
            });
        }
    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&libraries=places&callback=initAutocomplete" async defer></script>

<?php include 'footer.php'; ?>
