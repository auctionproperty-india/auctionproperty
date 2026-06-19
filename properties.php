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

// ---- FILTERS ----
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
    <div class="alert alert-success alert-dismissible fade show">✅ Property Added Successfully!</div>
<?php endif; ?>
<?php if(isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show">✅ Property Updated Successfully!</div>
<?php endif; ?>

<!-- ============================================= -->
<!-- ========== PROPERTY LIST ========== -->
<!-- ============================================= -->

<div class="card-premium">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Properties</h5>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#propertyModal">
            <i class="fas fa-plus-circle me-1"></i> Add New Property
        </button>
    </div>

    <!-- Filters -->
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

    <!-- Table -->
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
                                <a href="#" class="btn btn-sm btn-primary edit-btn" data-id="<?= $row['id'] ?>" data-bs-toggle="modal" data-bs-target="#propertyModal">✏️</a>
                                <a href="delete_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">🗑️</a>
                            </td>
                        </tr>
                    <?php }
                } else { ?>
                    <tr><td colspan="7" class="text-center text-muted">No properties found.</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================= -->
<!-- ========== MODAL (Popup Form) =============== -->
<!-- ============================================= -->

<div class="modal fade" id="propertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%);">
                <h5 class="modal-title text-white" id="modalTitle">
                    <i class="fas fa-plus-circle me-2"></i>Add New Property
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="propertyForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="edit_id" id="edit_id" value="0">
                    <input type="hidden" name="existing_image" id="existing_image" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Title *</label>
                            <input type="text" name="title" id="modal_title" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address *</label>
                            <input type="text" name="location" id="modal_location" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">City *</label>
                            <input type="text" name="city" id="modal_city" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">State</label>
                            <input type="text" name="state" id="modal_state" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Locality</label>
                            <input type="text" name="locality" id="modal_locality" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Reserve Price (₹) *</label>
                            <input type="number" step="0.01" name="price" id="modal_price" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Price per Sq Ft</label>
                            <input type="number" step="0.01" name="reserve_price_per_sqft" id="modal_reserve_price_per_sqft" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Bank Name</label>
                            <input type="text" name="bank_name" id="modal_bank_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Borrower Name</label>
                            <input type="text" name="borrower_name" id="modal_borrower_name" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Property Type</label>
                            <select name="type" id="modal_type" class="form-control">
                                <option value="Flat">Flat</option>
                                <option value="Plot">Plot</option>
                                <option value="Shop">Shop</option>
                                <option value="Land">Land</option>
                                <option value="Row House">Row House</option>
                                <option value="Bungalow">Bungalow</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Area (Sq Ft)</label>
                            <input type="number" step="0.01" name="sqft" id="modal_sqft" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Possession</label>
                            <select name="possession_type" id="modal_possession_type" class="form-control">
                                <option value="Physical">Physical</option>
                                <option value="Symbolic">Symbolic</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">EMD Amount (₹)</label>
                            <input type="number" step="0.01" name="emd_amount" id="modal_emd_amount" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Bid Increment (₹)</label>
                            <input type="number" step="0.01" name="bid_increment" id="modal_bid_increment" class="form-control">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Auction Start</label>
                            <input type="text" name="auction_start_time" id="modal_auction_start_time" class="form-control" placeholder="Wed, 24 Jun 2026 12:00 PM">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Auction End</label>
                            <input type="text" name="auction_end_time" id="modal_auction_end_time" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">EMD Deadline</label>
                            <input type="text" name="emd_deadline" id="modal_emd_deadline" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Auction Date (DD/MM/YYYY)</label>
                            <input type="text" name="auction_date" id="modal_auction_date" class="form-control" placeholder="e.g. 24/06/2026">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" name="contact_number" id="modal_contact_number" class="form-control" value="<?= htmlspecialchars($default_contact) ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Google Map Link (Auto)</label>
                            <input type="text" name="google_location" id="modal_google_location" class="form-control" readonly style="background:#f1f5f9;">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Upload Image</label>
                            <div id="current_image_preview"></div>
                            <input type="file" name="image_file" id="modal_image_file" class="form-control" accept="image/*">
                            <small id="image_help_text">Leave empty to auto-generate premium social card.</small>
                        </div>

                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_property" id="modal_submit_btn" class="btn btn-primary">Add Property</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to handle modal data and Google Autocomplete -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('propertyModal');
    
    // When modal is shown, check if we are editing
    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget; // Button that triggered the modal
        const isEdit = button && button.classList.contains('edit-btn');
        
        if (isEdit) {
            // Edit mode - fetch data via AJAX
            const id = button.getAttribute('data-id');
            fetch(`get_property.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        document.getElementById('edit_id').value = data.id;
                        document.getElementById('modal_title').value = data.title || '';
                        document.getElementById('modal_location').value = data.location || '';
                        document.getElementById('modal_city').value = data.city || '';
                        document.getElementById('modal_state').value = data.state || '';
                        document.getElementById('modal_locality').value = data.locality || '';
                        document.getElementById('modal_price').value = data.price || '';
                        document.getElementById('modal_reserve_price_per_sqft').value = data.reserve_price_per_sqft || '';
                        document.getElementById('modal_bank_name').value = data.bank_name || '';
                        document.getElementById('modal_borrower_name').value = data.borrower_name || '';
                        document.getElementById('modal_type').value = data.type || 'Flat';
                        document.getElementById('modal_sqft').value = data.sqft || '';
                        document.getElementById('modal_possession_type').value = data.possession_type || 'Physical';
                        document.getElementById('modal_emd_amount').value = data.emd_amount || '';
                        document.getElementById('modal_bid_increment').value = data.bid_increment || '';
                        document.getElementById('modal_auction_start_time').value = data.auction_start_time || '';
                        document.getElementById('modal_auction_end_time').value = data.auction_end_time || '';
                        document.getElementById('modal_emd_deadline').value = data.emd_deadline || '';
                        document.getElementById('modal_auction_date').value = data.auction_date || '';
                        document.getElementById('modal_contact_number').value = data.contact_number || '<?= htmlspecialchars($default_contact) ?>';
                        document.getElementById('modal_google_location').value = data.google_location || '';
                        document.getElementById('existing_image').value = data.image_url || '';
                        
                        // Show current image preview if exists
                        const preview = document.getElementById('current_image_preview');
                        if (data.image_url) {
                            preview.innerHTML = `<img src="${data.image_url}" style="max-height:120px; border-radius:10px; margin-bottom:10px;">`;
                        } else {
                            preview.innerHTML = '';
                        }
                        
                        document.getElementById('modal_submit_btn').name = 'update_property';
                        document.getElementById('modal_submit_btn').innerHTML = 'Update Property';
                        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Property #' + data.id;
                        document.getElementById('image_help_text').innerText = 'Leave empty to keep current image or auto-generate.';
                    }
                });
        } else {
            // Add mode - reset form
            document.getElementById('propertyForm').reset();
            document.getElementById('edit_id').value = '0';
            document.getElementById('existing_image').value = '';
            document.getElementById('current_image_preview').innerHTML = '';
            document.getElementById('modal_submit_btn').name = 'add_property';
            document.getElementById('modal_submit_btn').innerHTML = 'Add Property';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add New Property';
            document.getElementById('image_help_text').innerText = 'Leave empty to auto-generate premium social card.';
            document.getElementById('modal_contact_number').value = '<?= htmlspecialchars($default_contact) ?>';
        }
    });

    // Google Autocomplete
    const locationInput = document.getElementById('modal_location');
    if (locationInput) {
        const autocomplete = new google.maps.places.Autocomplete(locationInput);
        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            if (place.geometry) {
                const lat = place.geometry.location.lat();
                const lng = place.geometry.location.lng();
                document.getElementById('modal_google_location').value = 'https://www.google.com/maps?q=' + lat + ',' + lng;
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>
