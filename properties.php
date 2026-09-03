<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'sub_admin');

// ---- Get filters ----
$city_filter = $_GET['city'] ?? '';
$bank_filter = $_GET['bank'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';

$where = "1=1";
$params = [];

if (!empty($city_filter)) {
    $where .= " AND city ILIKE ?";
    $params[] = '%' . $city_filter . '%';
}
if (!empty($bank_filter)) {
    $where .= " AND bank_name ILIKE ?";
    $params[] = '%' . $bank_filter . '%';
}
if (!empty($min_price)) {
    $where .= " AND price >= ?";
    $params[] = (float)$min_price;
}
if (!empty($max_price)) {
    $where .= " AND price <= ?";
    $params[] = (float)$max_price;
}

// ---- Get properties ----
$sql = "SELECT * FROM properties WHERE $where ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

// ---- Handle Add/Edit ----
$edit_mode = false;
$prop = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$edit_id]);
    $prop = $stmt->fetch();
    if ($prop) {
        $edit_mode = true;
    }
}

// ---- Handle Delete ----
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $is_admin) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ?");
    $stmt->execute([$delete_id]);
    header("Location: properties.php?msg=deleted");
    exit;
}

// ---- Function to safely parse date ----
function parseDate($dateStr) {
    if (empty($dateStr)) return null;
    // Remove any extra spaces and time portion (keep only date part)
    $dateStr = trim($dateStr);
    // If contains space, take only first part (assuming date part is before space)
    $parts = explode(' ', $dateStr);
    $dateStr = $parts[0]; // take only the first token
    
    // Try to parse with strtotime (supports many formats)
    $timestamp = strtotime($dateStr);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    // Fallback: try DD/MM/YYYY or DD-MM-YYYY
    if (strpos($dateStr, '/') !== false) {
        $d = explode('/', $dateStr);
        if (count($d) === 3) {
            // Assume DD/MM/YYYY
            return $d[2] . '-' . $d[1] . '-' . $d[0];
        }
    } elseif (strpos($dateStr, '-') !== false) {
        $d = explode('-', $dateStr);
        if (count($d) === 3) {
            // Could be DD-MM-YYYY or YYYY-MM-DD
            // Check if first part is 4-digit year
            if (strlen($d[0]) === 4) {
                return $d[0] . '-' . $d[1] . '-' . $d[2];
            } else {
                return $d[2] . '-' . $d[1] . '-' . $d[0];
            }
        }
    }
    // If all fails, return null
    return null;
}

// ---- Handle Form Submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $location = trim($_POST['location'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $sqft = (float)($_POST['sqft'] ?? 0);
    $possession_type = trim($_POST['possession_type'] ?? '');
    $borrower_name = trim($_POST['borrower_name'] ?? '');
    $emd_amount = (float)($_POST['emd_amount'] ?? 0);
    $bid_increment = (float)($_POST['bid_increment'] ?? 0);
    $emd_deadline = trim($_POST['emd_deadline'] ?? '');
    $auction_start_time = trim($_POST['auction_start_time'] ?? '');
    $auction_end_time = trim($_POST['auction_end_time'] ?? '');
    $locality = trim($_POST['locality'] ?? '');
    $reserve_price_per_sqft = (float)($_POST['reserve_price_per_sqft'] ?? 0);
    $contact_number = trim($_POST['contact_number'] ?? '');
    $status = trim($_POST['status'] ?? 'available');
    $auction_date = trim($_POST['auction_date'] ?? '');
    $inspection_date = trim($_POST['inspection_date'] ?? '');

    // Parse dates safely
    $auction_date = parseDate($auction_date);
    $inspection_date = parseDate($inspection_date);

    $action = $_POST['action'];

    if ($action === 'add') {
        $stmt = $pdo->prepare("
            INSERT INTO properties (
                title, description, price, location, city, state, type, bank_name, 
                sqft, possession_type, borrower_name, emd_amount, bid_increment, 
                emd_deadline, auction_start_time, auction_end_time, locality, 
                reserve_price_per_sqft, contact_number, status, auction_date, 
                inspection_date, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $title, $description, $price, $location, $city, $state, $type, $bank_name,
            $sqft, $possession_type, $borrower_name, $emd_amount, $bid_increment,
            $emd_deadline, $auction_start_time, $auction_end_time, $locality,
            $reserve_price_per_sqft, $contact_number, $status, $auction_date,
            $inspection_date
        ]);
        header("Location: properties.php?msg=added");
        exit;
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("
            UPDATE properties SET 
                title = ?, description = ?, price = ?, location = ?, city = ?, state = ?, 
                type = ?, bank_name = ?, sqft = ?, possession_type = ?, borrower_name = ?, 
                emd_amount = ?, bid_increment = ?, emd_deadline = ?, auction_start_time = ?, 
                auction_end_time = ?, locality = ?, reserve_price_per_sqft = ?, 
                contact_number = ?, status = ?, auction_date = ?, inspection_date = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $description, $price, $location, $city, $state, $type, $bank_name,
            $sqft, $possession_type, $borrower_name, $emd_amount, $bid_increment,
            $emd_deadline, $auction_start_time, $auction_end_time, $locality,
            $reserve_price_per_sqft, $contact_number, $status, $auction_date,
            $inspection_date, $id
        ]);
        header("Location: properties.php?msg=updated");
        exit;
    }
}

include 'header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>🏠 All Properties (<?= count($properties) ?>)</h1>
        <a href="?add=1" class="btn btn-primary btn-lg rounded-pill shadow <?= isset($_GET['add']) ? 'disabled' : '' ?>">
            <i class="fas fa-plus-circle me-2"></i> Add New Property
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php if ($_GET['msg'] === 'added'): ?>✅ Property added successfully!
            <?php elseif ($_GET['msg'] === 'updated'): ?>✅ Property updated successfully!
            <?php elseif ($_GET['msg'] === 'deleted'): ?>✅ Property deleted successfully!
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ============================================================
    ADD/EDIT FORM
    ============================================================ -->
    <?php if (isset($_GET['add']) || $edit_mode): ?>
    <div class="card shadow-lg rounded-4 mb-5 border-0">
        <div class="card-header bg-primary text-white p-3 rounded-top-4">
            <h5 class="mb-0"><i class="fas fa-<?= $edit_mode ? 'edit' : 'plus' ?> me-2"></i> <?= $edit_mode ? 'Edit Property' : 'Add New Property' ?></h5>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <input type="hidden" name="action" value="<?= $edit_mode ? 'edit' : 'add' ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id" value="<?= $prop['id'] ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Title *</label>
                        <input type="text" name="title" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($prop['title']) : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Address / Location *</label>
                        <input type="text" name="location" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($prop['location']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reserve Price (₹) *</label>
                        <input type="number" name="price" class="form-control" required step="0.01" value="<?= $edit_mode ? $prop['price'] : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Price per Sq Ft</label>
                        <input type="number" name="reserve_price_per_sqft" class="form-control" step="0.01" value="<?= $edit_mode ? $prop['reserve_price_per_sqft'] : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Area (Sq Ft)</label>
                        <input type="number" name="sqft" class="form-control" value="<?= $edit_mode ? $prop['sqft'] : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Borrower Name</label>
                        <input type="text" name="borrower_name" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['borrower_name']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['bank_name']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Property Type</label>
                        <!-- ====== 🔥 UPDATED: Full Property Type List ====== -->
                        <select name="type" class="form-control">
                            <option value="Flat" <?= $edit_mode && $prop['type'] == 'Flat' ? 'selected' : '' ?>>Flat</option>
                            <option value="Plot" <?= $edit_mode && $prop['type'] == 'Plot' ? 'selected' : '' ?>>Plot</option>
                            <option value="Shop" <?= $edit_mode && $prop['type'] == 'Shop' ? 'selected' : '' ?>>Shop</option>
                            <option value="Land" <?= $edit_mode && $prop['type'] == 'Land' ? 'selected' : '' ?>>Land</option>
                            <option value="House" <?= $edit_mode && $prop['type'] == 'House' ? 'selected' : '' ?>>House</option>
                            <option value="Car/Vehicle" <?= $edit_mode && ($prop['type'] == 'Car/Vehicle' || $prop['type'] == 'Car') ? 'selected' : '' ?>>Car / Vehicle</option>
                            <option value="Commercial" <?= $edit_mode && $prop['type'] == 'Commercial' ? 'selected' : '' ?>>Commercial</option>
                            <option value="Office" <?= $edit_mode && $prop['type'] == 'Office' ? 'selected' : '' ?>>Office</option>
                            <option value="Row House" <?= $edit_mode && $prop['type'] == 'Row House' ? 'selected' : '' ?>>Row House</option>
                            <option value="Bungalow" <?= $edit_mode && $prop['type'] == 'Bungalow' ? 'selected' : '' ?>>Bungalow</option>
                            <option value="Other" <?= $edit_mode && $prop['type'] == 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Possession</label>
                        <select name="possession_type" class="form-control">
                            <option value="Physical" <?= $edit_mode && $prop['possession_type'] == 'Physical' ? 'selected' : '' ?>>Physical</option>
                            <option value="Symbolic" <?= $edit_mode && $prop['possession_type'] == 'Symbolic' ? 'selected' : '' ?>>Symbolic</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Locality</label>
                        <input type="text" name="locality" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['locality']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">City *</label>
                        <input type="text" name="city" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($prop['city']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">State</label>
                        <input type="text" name="state" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['state']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">EMD Amount (₹)</label>
                        <input type="number" name="emd_amount" class="form-control" step="0.01" value="<?= $edit_mode ? $prop['emd_amount'] : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">BID Increment (₹)</label>
                        <input type="number" name="bid_increment" class="form-control" step="0.01" value="<?= $edit_mode ? $prop['bid_increment'] : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">EMD Deadline</label>
                        <input type="text" name="emd_deadline" class="form-control" step="0.01" value="<?= $edit_mode ? htmlspecialchars($prop['emd_deadline']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Auction Start</label>
                        <input type="text" name="auction_start_time" class="form-control" step="0.01" value="<?= $edit_mode ? htmlspecialchars($prop['auction_start_time']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Auction End</label>
                        <input type="text" name="auction_end_time" class="form-control" step="0.01" value="<?= $edit_mode ? htmlspecialchars($prop['auction_end_time']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Inspection Date (DD/MM/YYYY)</label>
                        <input type="text" name="inspection_date" class="form-control" step="0.01" value="<?= $edit_mode && $prop['inspection_date'] ? date('d/m/Y', strtotime($prop['inspection_date'])) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Auction Date (DD/MM/YYYY) *</label>
                        <input type="text" name="auction_date" class="form-control" step="0.01" required value="<?= $edit_mode && $prop['auction_date'] ? date('d/m/Y', strtotime($prop['auction_date'])) : '' ?>">
                        <small class="text-muted">Enter only date (e.g., 24/08/2026). Time will be ignored.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['contact_number']) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-control">
                            <option value="available" <?= $edit_mode && $prop['status'] == 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="sold" <?= $edit_mode && $prop['status'] == 'sold' ? 'selected' : '' ?>>Sold</option>
                            <option value="pending" <?= $edit_mode && $prop['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= $edit_mode ? htmlspecialchars($prop['description']) : '' ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill shadow">
                            <i class="fas fa-save me-2"></i> <?= $edit_mode ? 'Update Property' : 'Add Property' ?>
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="properties.php" class="btn btn-outline-secondary w-100 mt-2 rounded-pill">Cancel Edit</a>
                        <?php else: ?>
                            <a href="properties.php" class="btn btn-outline-secondary w-100 mt-2 rounded-pill">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================
    SEARCH BAR (FILTERS)
    ============================================================ -->
    <div class="card shadow-sm rounded-4 mb-4 border-0">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">City</label>
                    <input type="text" name="city" class="form-control" placeholder="Search by City..." value="<?= htmlspecialchars($city_filter) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Bank Name</label>
                    <input type="text" name="bank" class="form-control" placeholder="Search by Bank..." value="<?= htmlspecialchars($bank_filter) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Min Price</label>
                    <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?= htmlspecialchars($min_price) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Max Price</label>
                    <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?= htmlspecialchars($max_price) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">
                        <i class="fas fa-search me-2"></i> Filter
                    </button>
                    <a href="properties.php" class="btn btn-outline-secondary w-100 mt-1 rounded-pill">
                        <i class="fas fa-undo me-2"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
    PROPERTY LIST
    ============================================================ -->
    <?php if (count($properties) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Bank</th>
                        <th>City</th>
                        <th>Price</th>
                        <th>Auction Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $row): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['bank_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['city'] ?? 'N/A') ?></td>
                            <td>₹ <?= number_format($row['price'], 2) ?></td>
                            <td>
                                <?php if (!empty($row['auction_date'])): ?>
                                    <?= date('d M Y', strtotime($row['auction_date'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?= $row['status'] == 'available' ? 'success' : ($row['status'] == 'sold' ? 'danger' : 'warning') ?>"><?= $row['status'] ?></span></td>
                            <td>
                                <a href="properties.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                <?php if ($is_admin): ?>
                                    <a href="properties.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center py-4">
            <i class="fas fa-inbox fa-3x d-block mb-3 opacity-50"></i>
            <h5>No properties found</h5>
            <p class="text-muted">Try adjusting your filters or add a new property.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
