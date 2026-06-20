<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

// ✅ Permission Check – 'properties' module के लिए View Permission
if(!hasViewPermission('properties', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page. Contact Super Admin.</div>");
}

$default_contact = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='default_contact'")->fetchColumn();
if(!$default_contact) $default_contact = '9238215516';

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
    if(isset($_POST['update_property']) && isset($_POST['property_id'])) {
        // Check Edit Permission
        if(!hasEditPermission('properties', $pdo)) {
            die("<div class='alert alert-danger'>❌ You don't have permission to edit properties.</div>");
        }
        $id = $_POST['property_id'];
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
            $id
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

    // ADD
    if(isset($_POST['add_property'])) {
        // Check Edit Permission
        if(!hasEditPermission('properties', $pdo)) {
            die("<div class='alert alert-danger'>❌ You don't have permission to add properties.</div>");
        }
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

<?php if(isset($_GET['added'])): ?>
    <div class="alert alert-success">✅ Property Added Successfully!</div>
<?php endif; ?>
<?php if(isset($_GET['updated'])): ?>
    <div class="alert alert-success">✅ Property Updated Successfully!</div>
<?php endif; ?>

<div class="card-premium">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5><i class="fas fa-list me-2"></i>All Properties</h5>
        <?php if(hasEditPermission('properties', $pdo)): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#propertyModal" onclick="openAddModal()">
                <i class="fas fa-plus-circle me-1"></i> Add New Property
            </button>
        <?php else: ?>
            <span class="text-muted">(View Only Mode)</span>
        <?php endif; ?>
    </div>

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
                                <?php if(hasEditPermission('properties', $pdo)): ?>
                                    <button class="btn btn-sm btn-primary" onclick="openEditModal(<?= $row['id'] ?>)">✏️</button>
                                    <a href="delete_property.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">🗑️</a>
                                <?php else: ?>
                                    <span class="text-muted">View Only</span>
                                <?php endif; ?>
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

<!-- ===== MODAL ===== -->
<div class="modal fade" id="propertyModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #1e293b, #334155); color: white; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus-circle me-2"></i>Add New Property</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="propertyForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="property_id" id="property_id" value="">
                    <input type="hidden" name="existing_image" id="existing_image" value="">

                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold">Title *</label><input type="text" name="title" id="edit_title" class="form-control" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Address *</label><input type="text" name="location" id="edit_location" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">City *</label><input type="text" name="city" id="edit_city" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">State</label><input type="text" name="state" id="edit_state" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Locality</label><input type="text" name="locality" id="edit_locality" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Reserve Price (₹) *</label><input type="number" step="0.01" name="price" id="edit_price" class="form-control" required></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Price per Sq Ft</label><input type="number" step="0.01" name="reserve_price_per_sqft" id="edit_reserve_price_per_sqft" class="form-control"></div>

                        <div class="col-md-4"><label class="form-label fw-semibold">Bank Name</label><input type="text" name="bank_name" id="edit_bank_name" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Borrower Name</label><input type="text" name="borrower_name" id="edit_borrower_name" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Property Type</label>
                            <select name="type" id="edit_type" class="form-control">
                                <option value="Flat">Flat</option><option value="Plot">Plot</option>
                                <option value="Shop">Shop</option><option value="Land">Land</option>
                                <option value="Row House">Row House</option><option value="Bungalow">Bungalow</option>
                            </select>
                        </div>

                        <div class="col-md-3"><label class="form-label fw-semibold">Area (Sq Ft)</label><input type="number" step="0.01" name="sqft" id="edit_sqft" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Possession</label>
                            <select name="possession_type" id="edit_possession_type" class="form-control">
                                <option value="Physical">Physical</option><option value="Symbolic">Symbolic</option>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label fw-semibold">EMD Amount (₹)</label><input type="number" step="0.01" name="emd_amount" id="edit_emd_amount" class="form-control"></div>
                        <div class="col-md-3"><label class="form-label fw-semibold">Bid Increment (₹)</label><input type="number" step="0.01" name="bid_increment" id="edit_bid_increment" class="form-control"></div>

                        <div class="col-md-4"><label class="form-label fw-semibold">Auction Start</label><input type="text" name="auction_start_time" id="edit_auction_start_time" class="form-control" placeholder="Wed, 24 Jun 2026 12:00 PM"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">Auction End</label><input type="text" name="auction_end_time" id="edit_auction_end_time" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM"></div>
                        <div class="col-md-4"><label class="form-label fw-semibold">EMD Deadline</label><input type="text" name="emd_deadline" id="edit_emd_deadline" class="form-control" placeholder="Wed, 24 Jun 2026 05:00 PM"></div>

                        <div class="col-md-6"><label class="form-label fw-semibold">Auction Date (DD/MM/YYYY)</label><input type="text" name="auction_date" id="edit_auction_date" class="form-control" placeholder="e.g. 24/06/2026"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Contact Number</label><input type="text" name="contact_number" id="edit_contact_number" class="form-control" value="<?= $default_contact ?>" required></div>

                        <div class="col-12"><label class="form-label fw-semibold">Google Map Link</label><input type="text" name="google_location" id="edit_google_location" class="form-control" placeholder="https://maps.google.com/..."></div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Upload Image</label>
                            <div id="currentImagePreview" style="display:none; margin-bottom:10px;">
                                <img id="currentImage" src="" style="max-height:120px; border-radius:10px; border:1px solid #ddd;">
                            </div>
                            <input type="file" name="image_file" id="edit_image_file" class="form-control" accept="image/*">
                            <small id="imageHelpText">Leave empty to auto-generate premium social card.</small>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="add_property" id="submitBtn" class="btn btn-primary btn-lg w-100">Add Property</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add New Property';
        document.getElementById('propertyForm').reset();
        document.getElementById('property_id').value = '';
        document.getElementById('existing_image').value = '';
        document.getElementById('submitBtn').name = 'add_property';
        document.getElementById('submitBtn').innerHTML = 'Add Property';
        document.getElementById('currentImagePreview').style.display = 'none';
        document.getElementById('imageHelpText').textContent = 'Leave empty to auto-generate premium social card.';
        document.getElementById('edit_google_location').value = '';
        document.getElementById('edit_location').value = '';
    }

    function openEditModal(id) {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Property #' + id;
        document.getElementById('submitBtn').name = 'update_property';
        document.getElementById('submitBtn').innerHTML = 'Update Property';
        document.getElementById('imageHelpText').textContent = 'Leave empty to keep current image or auto-generate.';

        fetch('get_property.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('property_id').value = data.id;
                document.getElementById('edit_title').value = data.title || '';
                document.getElementById('edit_location').value = data.location || '';
                document.getElementById('edit_city').value = data.city || '';
                document.getElementById('edit_state').value = data.state || '';
                document.getElementById('edit_locality').value = data.locality || '';
                document.getElementById('edit_price').value = data.price || '';
                document.getElementById('edit_reserve_price_per_sqft').value = data.reserve_price_per_sqft || '';
                document.getElementById('edit_bank_name').value = data.bank_name || '';
                document.getElementById('edit_borrower_name').value = data.borrower_name || '';
                document.getElementById('edit_type').value = data.type || 'Flat';
                document.getElementById('edit_sqft').value = data.sqft || '';
                document.getElementById('edit_possession_type').value = data.possession_type || 'Physical';
                document.getElementById('edit_emd_amount').value = data.emd_amount || '';
                document.getElementById('edit_bid_increment').value = data.bid_increment || '';
                document.getElementById('edit_auction_start_time').value = data.auction_start_time || '';
                document.getElementById('edit_auction_end_time').value = data.auction_end_time || '';
                document.getElementById('edit_emd_deadline').value = data.emd_deadline || '';
                document.getElementById('edit_auction_date').value = data.auction_date || '';
                document.getElementById('edit_contact_number').value = data.contact_number || '';
                document.getElementById('edit_google_location').value = data.google_location || '';
                document.getElementById('existing_image').value = data.image_url || '';

                if (data.image_url) {
                    document.getElementById('currentImage').src = data.image_url;
                    document.getElementById('currentImagePreview').style.display = 'block';
                } else {
                    document.getElementById('currentImagePreview').style.display = 'none';
                }

                var modal = new bootstrap.Modal(document.getElementById('propertyModal'));
                modal.show();
            })
            .catch(error => {
                alert('Error loading property data: ' + error);
            });
    }
</script>

<?php include 'footer.php'; ?>
