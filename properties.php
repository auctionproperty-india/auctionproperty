<?php 
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}

if(!hasViewPermission('properties', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page.</div>");
}

$default_contact = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='default_contact'")->fetchColumn();
if(!$default_contact) $default_contact = '9238215516';

// FILTERS
$filter_city = $_GET['filter_city'] ?? '';
$filter_bank = $_GET['filter_bank'] ?? '';
$filter_price_min = $_GET['filter_price_min'] ?? '';
$filter_price_max = $_GET['filter_price_max'] ?? '';

$where = [];
$params = [];
if(!empty($filter_city)) { $where[] = "city ILIKE ?"; $params[] = '%'.$filter_city.'%'; }
if(!empty($filter_bank)) { $where[] = "bank_name ILIKE ?"; $params[] = '%'.$filter_bank.'%'; }
if(!empty($filter_price_min)) { $where[] = "price >= ?"; $params[] = (float)$filter_price_min; }
if(!empty($filter_price_max)) { $where[] = "price <= ?"; $params[] = (float)$filter_price_max; }

$where_clause = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// PAGINATION
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) FROM properties $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql = "SELECT id, title, bank_name, city, price, status FROM properties $where_clause ORDER BY id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ---- ADD / UPDATE LOGIC ----
function safeNumeric($val) { if ($val === '' || $val === null) return 0; return (float) $val; }
function safeString($val) { return trim($val ?? ''); }

// UPDATE
if(isset($_POST['update_property']) && isset($_POST['property_id'])) {
    if(!hasEditPermission('properties', $pdo)) die("Permission denied.");
    $id = $_POST['property_id'];
    
    $inspection_date_db = null;
    if(!empty($_POST['inspection_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['inspection_date']);
        if($date_obj) $inspection_date_db = $date_obj->format('Y-m-d');
    }

    $auction_date_db = null;
    if(!empty($_POST['auction_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['auction_date']);
        if($date_obj) $auction_date_db = $date_obj->format('Y-m-d');
    }

    $sql = "UPDATE properties SET 
        title=?, description='', price=?, location=?, city=?, state=?, type=?, 
        bank_name=?, sqft=?, possession_type=?, inspection_date=?, 
        borrower_name=?, emd_amount=?, bid_increment=?, emd_deadline=?, 
        auction_start_time=?, auction_end_time=?, locality=?, reserve_price_per_sqft=?, 
        contact_number=?, auction_date=? 
        WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        safeString($_POST['title'] ?? ''),
        safeNumeric($_POST['price'] ?? 0),
        safeString($_POST['location'] ?? ''),
        safeString($_POST['city'] ?? ''),
        safeString($_POST['state'] ?? ''),
        safeString($_POST['type'] ?? 'Flat'),
        safeString($_POST['bank_name'] ?? ''),
        safeNumeric($_POST['sqft'] ?? 0),
        safeString($_POST['possession_type'] ?? 'Physical'),
        $inspection_date_db,
        safeString($_POST['borrower_name'] ?? ''),
        safeNumeric($_POST['emd_amount'] ?? 0),
        safeNumeric($_POST['bid_increment'] ?? 0),
        safeString($_POST['emd_deadline'] ?? ''),
        safeString($_POST['auction_start_time'] ?? ''),
        safeString($_POST['auction_end_time'] ?? ''),
        safeString($_POST['locality'] ?? ''),
        safeNumeric($_POST['reserve_price_per_sqft'] ?? 0),
        safeString($_POST['contact_number'] ?? $default_contact),
        $auction_date_db,
        $id
    ]);
    header("Location: properties.php?updated=1");
    exit;
}

// ADD
if(isset($_POST['add_property'])) {
    if(!hasEditPermission('properties', $pdo)) die("Permission denied.");
    
    $inspection_date_db = null;
    if(!empty($_POST['inspection_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['inspection_date']);
        if($date_obj) $inspection_date_db = $date_obj->format('Y-m-d');
    }

    $auction_date_db = null;
    if(!empty($_POST['auction_date'])) {
        $date_obj = DateTime::createFromFormat('d/m/Y', $_POST['auction_date']);
        if($date_obj) $auction_date_db = $date_obj->format('Y-m-d');
    }

    $sql = "INSERT INTO properties (
        title, description, price, location, city, state, type, 
        bank_name, sqft, possession_type, inspection_date, 
        borrower_name, emd_amount, bid_increment, emd_deadline, 
        auction_start_time, auction_end_time, locality, reserve_price_per_sqft, 
        contact_number, auction_date, status
    ) VALUES (?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        safeString($_POST['title'] ?? ''),
        safeNumeric($_POST['price'] ?? 0),
        safeString($_POST['location'] ?? ''),
        safeString($_POST['city'] ?? ''),
        safeString($_POST['state'] ?? ''),
        safeString($_POST['type'] ?? 'Flat'),
        safeString($_POST['bank_name'] ?? ''),
        safeNumeric($_POST['sqft'] ?? 0),
        safeString($_POST['possession_type'] ?? 'Physical'),
        $inspection_date_db,
        safeString($_POST['borrower_name'] ?? ''),
        safeNumeric($_POST['emd_amount'] ?? 0),
        safeNumeric($_POST['bid_increment'] ?? 0),
        safeString($_POST['emd_deadline'] ?? ''),
        safeString($_POST['auction_start_time'] ?? ''),
        safeString($_POST['auction_end_time'] ?? ''),
        safeString($_POST['locality'] ?? ''),
        safeNumeric($_POST['reserve_price_per_sqft'] ?? 0),
        safeString($_POST['contact_number'] ?? $default_contact),
        $auction_date_db
    ]);
    
    $new_id = $pdo->lastInsertId();
    // Send email notification to all users (if function exists)
    if(function_exists('sendNewPropertyNotification')) {
        sendNewPropertyNotification($pdo, $new_id, 'auction');
    }
    
    header("Location: properties.php?added=1");
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

<div class="container-fluid px-3">
    <!-- Filter Form + Add Button (Top Row) -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Properties (<?= $total_rows ?>)</h5>
        <?php if(hasEditPermission('properties', $pdo)): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#propertyModal" onclick="openAddModal()">
                <i class="fas fa-plus-circle me-1"></i> Add New Property
            </button>
        <?php else: ?>
            <span class="text-muted">(View Only Mode)</span>
        <?php endif; ?>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-2 rounded-3 shadow-sm mb-3 border">
        <form method="GET" class="row g-1 align-items-center">
            <div class="col-md-3">
                <input type="text" name="filter_city" class="form-control form-control-sm" placeholder="🏙️ City" value="<?= htmlspecialchars($filter_city) ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="filter_bank" class="form-control form-control-sm" placeholder="🏦 Bank Name" value="<?= htmlspecialchars($filter_bank) ?>">
            </div>
            <div class="col-md-2">
                <input type="number" name="filter_price_min" class="form-control form-control-sm" placeholder="Min Price" value="<?= htmlspecialchars($filter_price_min) ?>">
            </div>
            <div class="col-md-2">
                <input type="number" name="filter_price_max" class="form-control form-control-sm" placeholder="Max Price" value="<?= htmlspecialchars($filter_price_max) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter"></i> Filter</button>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-3 p-2 shadow-sm border">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr><th>ID</th><th>Title</th><th>Bank</th><th>City</th><th>Price</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if(count($rows) > 0): ?>
                        <?php foreach($rows as $row): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['bank_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($row['city'] ?? '') ?></td>
                                <td><?= indianCurrencyFormat($row['price']) ?></td>
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
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No properties found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if($total_pages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination justify-content-center pagination-sm">
                <?php if($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>">« Prev</a></li>
                <?php endif; ?>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page', ARRAY_FILTER_USE_KEY)) ?>">Next »</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
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

                    <?php include 'property_form.php'; ?>

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
        document.getElementById('property_id').value = '';
        document.getElementById('existing_image').value = '';
        document.getElementById('submitBtn').name = 'add_property';
        document.getElementById('submitBtn').innerHTML = 'Add Property';
        document.getElementById('currentImagePreview').style.display = 'none';
        document.getElementById('imageHelpText').textContent = 'Leave empty to auto-generate premium social card.';
        
        document.getElementById('edit_title').value = '';
        document.getElementById('edit_price').value = '';
        document.getElementById('edit_reserve_price_per_sqft').value = '';
        document.getElementById('edit_borrower_name').value = '';
        document.getElementById('edit_bank_name').value = '';
        document.getElementById('edit_location').value = '';
        document.getElementById('edit_locality').value = '';
        document.getElementById('edit_city').value = '';
        document.getElementById('edit_state').value = '';
        document.getElementById('edit_emd_amount').value = '';
        document.getElementById('edit_bid_increment').value = '';
        document.getElementById('edit_sqft').value = '';
        document.getElementById('edit_emd_deadline').value = '';
        document.getElementById('edit_auction_start_time').value = '';
        document.getElementById('edit_auction_end_time').value = '';
        document.getElementById('edit_inspection_date').value = '';
        document.getElementById('edit_contact_number').value = '';
        document.getElementById('edit_google_location').value = '';
        document.getElementById('edit_description').value = '';
        document.getElementById('edit_auction_date').value = '';
        
        document.getElementById('edit_type').value = 'Flat';
        document.getElementById('edit_possession_type').value = 'Physical';
    }

    function openEditModal(id) {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Property #' + id;
        document.getElementById('submitBtn').name = 'update_property';
        document.getElementById('submitBtn').innerHTML = 'Update Property';
        document.getElementById('imageHelpText').textContent = 'Leave empty to keep current image or auto-generate.';

        fetch('get_property.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                document.getElementById('property_id').value = data.id || '';
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
                document.getElementById('edit_inspection_date').value = data.inspection_date || '';
                document.getElementById('edit_contact_number').value = data.contact_number || '';
                document.getElementById('edit_google_location').value = data.google_location || '';
                document.getElementById('existing_image').value = data.image_url || '';
                document.getElementById('edit_description').value = data.description || '';
                document.getElementById('edit_auction_date').value = data.auction_date || '';

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
                console.error(error);
            });
    }
</script>

<?php include 'footer.php'; ?>
