<?php 
require_once 'db.php'; 
require_once 'functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

$default_contact = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='default_contact'")->fetchColumn();
if(!$default_contact) $default_contact = '9238215516';

// ---- EDIT MODE ----
$edit_id = $_GET['edit'] ?? 0;
$edit_data = null;
if($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
    if(!$edit_data) { $edit_id = 0; $edit_data = null; }
}

// ---- FILTERS (Admin) ----
$filter_city = $_GET['filter_city'] ?? '';
$filter_bank = $_GET['filter_bank'] ?? '';
$filter_price_min = $_GET['filter_price_min'] ?? '';
$filter_price_max = $_GET['filter_price_max'] ?? '';

$where = [];
$params = [];

if(!empty($filter_city)) {
    $where[] = "city ILIKE ?";
    $params[] = '%'.$filter_city.'%';
}
if(!empty($filter_bank)) {
    $where[] = "bank_name ILIKE ?";
    $params[] = '%'.$filter_bank.'%';
}
if(!empty($filter_price_min)) {
    $where[] = "price >= ?";
    $params[] = (float)$filter_price_min;
}
if(!empty($filter_price_max)) {
    $where[] = "price <= ?";
    $params[] = (float)$filter_price_max;
}

$sql = "SELECT * FROM properties";
if(count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ---- ADD / UPDATE LOGIC ----
if($_SERVER['REQUEST_METHOD'] == 'POST') {

    function safeNumeric($val) {
        if ($val === '' || $val === null) return 0;
        return (float) $val;
    }

    function safeString($val) {
        return trim($val ?? '');
    }

    // UPDATE
    if(isset($_POST['update_property']) && $edit_id) {
        $image_path = $_POST['existing_image'] ?? '';
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
            title=?, description='', price=?, location=?, city=?, state=?, type=?, google_location=?, image_url=?, 
            bank_name=?, sqft=?, possession_type=?, auction_date=?, 
            borrower_name=?, emd_amount=?, bid_increment=?, emd_deadline=?, 
            auction_start_time=?, auction_end_time=?, locality=?, reserve_price_per_sqft=?, contact_number=? 
            WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            safeString($_POST['title'] ?? ''),
            safeNumeric($_POST['price'] ?? 0),
            safeString($_POST['location'] ?? ''),
            safeString($_POST['city'] ?? ''),
            safeString($_POST['state'] ?? ''),
            safeString($_POST['type'] ?? 'Flat'),
            safeString($_POST['google_location'] ?? ''),
            $image_path,
            safeString($_POST['bank_name'] ?? ''),
            safeNumeric($_POST['sqft'] ?? 0),
            safeString($_POST['possession_type'] ?? 'Physical'),
            $auction_date_db,
            safeString($_POST['borrower_name'] ?? ''),
            safeNumeric($_POST['emd_amount'] ?? 0),
            safeNumeric($_POST['bid_increment'] ?? 0),
            safeString($_POST['emd_deadline'] ?? ''),
            safeString($_POST['auction_start_time'] ?? ''),
            safeString($_POST['auction_end_time'] ?? ''),
            safeString($_POST['locality'] ?? ''),
            safeNumeric($_POST['reserve_price_per_sqft'] ?? 0),
            safeString($_POST['contact_number'] ?? $default_contact),
            $edit_id
        ]);

        if (!$use_uploaded_image) {
            $updated_prop = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
            $updated_prop->execute([$edit_id]);
            $prop_data = $updated_prop->fetch();
            $generated_path = generateSocialCard($prop_data);
            if ($generated_path) {
                $pdo->prepare("UPDATE properties SET image_url = ? WHERE id = ?")->execute([$generated_path, $edit_id]);
            }
        }

        header("Location: properties.php?updated=1");
        exit;
    }

    // ADD
    if(isset($_POST['add_property'])) {
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
            title, description, price, location, city, state, type, google_location, image_url, 
            bank_name, sqft, possession_type, auction_date, 
            borrower_name, emd_amount, bid_increment, emd_deadline, 
            auction_start_time, auction_end_time, locality, reserve_price_per_sqft, contact_number
        ) VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            safeString($_POST['title'] ?? ''),
            safeNumeric($_POST['price'] ?? 0),
            safeString($_POST['location'] ?? ''),
            safeString($_POST['city'] ?? ''),
            safeString($_POST['state'] ?? ''),
            safeString($_POST['type'] ?? 'Flat'),
            safeString($_POST['google_location'] ?? ''),
            $image_path,
            safeString($_POST['bank_name'] ?? ''),
            safeNumeric($_POST['sqft'] ?? 0),
            safeString($_POST['possession_type'] ?? 'Physical'),
            $auction_date_db,
            safeString($_POST['borrower_name'] ?? ''),
            safeNumeric($_POST['emd_amount'] ?? 0),
            safeNumeric($_POST['bid_increment'] ?? 0),
            safeString($_POST['emd_deadline'] ?? ''),
            safeString($_POST['auction_start_time'] ?? ''),
            safeString($_POST['auction_end_time'] ?? ''),
            safeString($_POST['locality'] ?? ''),
            safeNumeric($_POST['reserve_price_per_sqft'] ?? 0),
            safeString($_POST['contact_number'] ?? $default_contact)
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

        header("Location: properties.php?added=1");
        exit;
    }
}

include 'header.php'; 
?>

<!-- Success Messages -->
<?php if(isset($_GET['added'])): ?>
    <div class="alert alert-success">✅ Property Added Successfully!</div>
<?php endif; ?>
<?php if(isset($_GET['updated'])): ?>
    <div class="alert alert-success">✅ Property Updated Successfully!</div>
<?php endif; ?>

<!-- ============================================= -->
<!-- ========== PROPERTY LIST (MAIN VIEW) ========== -->
<!-- ============================================= -->

<div class="card-premium">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Properties</h5>
        <a href="?add=1" class="btn btn-primary btn-sm">
            <i class="fas fa-plus-circle me-1"></i> Add New Property
        </a>
    </div>

    <!-- Search Filters (3 separate fields) -->
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="text" name="filter_city" class="form-control" placeholder="🏙️ City" value="<?= htmlspecialchars($filter_city) ?>">
        </div>
        <div class="col-md-3">
            <input type="text" name="filter_bank" class="form-control" placeholder="🏦 Bank Name" value="<?= htmlspecialchars($filter_bank) ?>">
        </div>
        <div class="col-md-2">
            <input type="number" name="filter_price_min" class="form-control" placeholder="Min Price" value="<?= htmlspecialchars($filter_price_min) ?>">
        </div>
        <div class="col-md-2">
            <input type="number" name="filter_price_max" class="form-control" placeholder="Max Price" value="<?= htmlspecialchars($filter_price_max) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
        </div>
    </form>

    <!-- Properties Table -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr><th>ID</th><th>Title</th><th>Bank</th><th>City</th><th>Price</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php 
                if(count($rows) > 0) {
                    foreach($rows as $row) { ?>
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
                    <?php }
                } else { ?>
                    <tr><td colspan="7" class="text-center text-muted">No properties match your filters.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================= -->
<!-- ========== ADD / EDIT FORM (BELOW LIST) ===== -->
<!-- ============================================= -->

<?php if(isset($_GET['add']) || $edit_id): ?>
<div class="card-premium mt-4" id="add-form" style="border-left: 4px solid #fbbf24;">
    <h5>
        <i class="fas fa-<?= $edit_id ? 'edit' : 'plus-circle' ?> me-2" style="color: #fbbf24;"></i>
        <?= $edit_id ? 'Edit Property #'.$edit_id : 'Add New Property' ?>
        <a href="properties.php" class="btn btn-sm btn-outline-secondary float-end">✕ Close</a>
    </h5>
    <form method="POST" class="mt-3" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Title *</label>
                <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Address *</label>
                <input type="text" name="location" id="location_input" class="form-control" required value="<?= htmlspecialchars($edit_data['location'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">City *</label>
                <input type="text" name="city" class="form-control" required value="<?= htmlspecialchars($edit_data['city'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">State</label>
                <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($edit_data['state'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Locality</label>
                <input type="text" name="locality" class="form-control" value="<?= htmlspecialchars($edit_data['locality'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Reserve Price (₹) *</label>
                <input type="number" step="0.01" name="price" class="form-control" required value="<?= htmlspecialchars($edit_data['price'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Price per Sq Ft</label>
                <input type="number" step="0.01" name="reserve_price_per_sqft" class="form-control" value="<?= htmlspecialchars($edit_data['reserve_price_per_sqft'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Bank Name</label>
                <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($edit_data['bank_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Borrower Name</label>
                <input type="text" name="borrower_name" class="form-control" value="<?= htmlspecialchars($edit_data['borrower_name'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Property Type</label>
                <select name="type" class="form-control">
                    <option value="Flat" <?= ($edit_data['type']??'')=='Flat'?'selected':'' ?>>Flat</option>
                    <option value="Plot" <?= ($edit_data['type']??'')=='Plot'?'selected':'' ?>>Plot</option>
                    <option value="Shop" <?= ($edit_data['type']??'')=='Shop'?'selected':'' ?>>Shop</option>
                    <option value="Land" <?= ($edit_data['type']??'')=='Land'?'selected':'' ?>>Land</option>
                    <option value="Row House" <?= ($edit_data['type']??'')=='Row House'?'selected':'' ?>>Row House</option>
                    <option value="Bungalow" <?= ($edit_data['type']??'')=='Bungalow'?'selected':'' ?>>Bungalow</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-semibold">Area (Sq Ft)</label>
                <input type="number" step="0.01" name="sqft" class="form-control" value="<?= htmlspecialchars($edit_data['sqft'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Possession</label>
                <select name="possession_type" class="form-control">
                    <option value="Physical" <?= ($edit_data['possession_type']??'')=='Physical'?'selected':'' ?>>Physical</option>
                    <option value="Symbolic" <?= ($edit_data['possession_type']??'')=='Symbolic'?'selected':'' ?>>Symbolic</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">EMD Amount (₹)</label>
                <input type="number" step="0.01" name="emd_amount" class="form-control" value="<?= htmlspecialchars($edit_data['emd_amount'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Bid Increment (₹)</label>
                <input type="number" step="0.01" name="bid_increment" class="form-control" value="<?= htmlspecialchars($edit_data['bid_increment'] ?? '') ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-semibold">Auction Start</label>
                <input type="text" name="auction_start_time" class="form-control" placeholder="Wed, 24 Jun 2026 12:00 PM" value="<?= htmlspecialchars($edit_data['auction_start_time'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Auction End</label>
                <input type="text" name="auction_end_time" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM" value="<?= htmlspecialchars($edit_data['auction_end_time'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">EMD Deadline</label>
                <input type="text" name="emd_deadline" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM" value="<?= htmlspecialchars($edit_data['emd_deadline'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label fw-semibold">Auction Date (DD/MM/YYYY)</label>
                <input type="text" name="auction_date" class="form-control" placeholder="e.g. 24/06/2026" value="<?= htmlspecialchars($edit_data['auction_date'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($edit_data['contact_number'] ?? $default_contact) ?>" required>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold">Google Map Link (Auto)</label>
                <input type="text" name="google_location" id="google_location" class="form-control" readonly style="background:#f1f5f9;" value="<?= htmlspecialchars($edit_data['google_location'] ?? '') ?>">
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold">Upload Image</label>
                <?php if($edit_id && !empty($edit_data['image_url']) && file_exists($edit_data['image_url'])): ?>
                    <div class="mb-2"><img src="<?= $edit_data['image_url'] ?>" style="max-height:120px; border-radius:10px;"></div>
                    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($edit_data['image_url']) ?>">
                <?php endif; ?>
                <input type="file" name="image_file" class="form-control" accept="image/*">
                <small><?= $edit_id ? 'Leave empty to keep/auto-generate.' : 'Leave empty to auto-generate premium social card.' ?></small>
            </div>

            <div class="col-12">
                <?php if($edit_id): ?>
                    <input type="hidden" name="property_id" value="<?= $edit_id ?>">
                    <button type="submit" name="update_property" class="btn btn-success btn-lg w-100">Update Property</button>
                <?php else: ?>
                    <button type="submit" name="add_property" class="btn btn-primary btn-lg w-100">Add Property</button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Google Maps Autocomplete -->
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
