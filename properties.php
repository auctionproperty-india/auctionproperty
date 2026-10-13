<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'sub_admin');

// 🔥 Master types list
$property_types = [
    'Flat', 'Plot', 'Shop', 'Land', 'House', 'Land & Building',
    'Car/Vehicle', 'Commercial', 'Office', 'Row House', 'Bungalow', 'Other',
];

// ---- Filters ----
$city_filter = $_GET['city'] ?? '';
$bank_filter = $_GET['bank'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$type_filter = $_GET['type'] ?? '';

$where = "1=1";
$params = [];

if (!empty($city_filter)) { $where .= " AND city ILIKE ?"; $params[] = '%'.$city_filter.'%'; }
if (!empty($bank_filter)) { $where .= " AND bank_name ILIKE ?"; $params[] = '%'.$bank_filter.'%'; }
if (!empty($min_price))   { $where .= " AND price >= ?"; $params[] = (float)$min_price; }
if (!empty($max_price))   { $where .= " AND price <= ?"; $params[] = (float)$max_price; }
if (!empty($type_filter)) { $where .= " AND type = ?";   $params[] = $type_filter; }

// ============================================================
// 🔥 PAGINATION — Fast loading even with 10000+ properties
// ============================================================
$per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Total count (1 fast query)
$count_sql = "SELECT COUNT(*) FROM properties WHERE $where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_properties = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_properties / $per_page));

// Main query (LIMIT + OFFSET)
$sql = "SELECT * FROM properties WHERE $where ORDER BY id DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

// ============================================================
// 🔥 BATCH LOAD GALLERY COUNTS (1 query instead of N)
// ============================================================
$gallery_counts = [];
if (!empty($properties)) {
    $prop_ids = array_column($properties, 'id');
    $placeholders = implode(',', array_fill(0, count($prop_ids), '?'));

    try {
        $g_stmt = $pdo->prepare("
            SELECT property_id, COUNT(*) as cnt 
            FROM property_images 
            WHERE property_id IN ($placeholders) 
            GROUP BY property_id
        ");
        $g_stmt->execute($prop_ids);
        while ($r = $g_stmt->fetch()) {
            $gallery_counts[$r['property_id']] = (int)$r['cnt'];
        }
    } catch (Exception $e) {
        $gallery_counts = [];
    }
}

// ---- Handle Add/Edit ----
$edit_mode = false;
$prop = null;
$existing_images = [];

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$edit_id]);
    $prop = $stmt->fetch();
    if ($prop) {
        $edit_mode = true;

        // Load existing gallery images
        try {
            $img_stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY display_order ASC, id ASC");
            $img_stmt->execute([$edit_id]);
            $existing_images = $img_stmt->fetchAll();
        } catch (Exception $e) {
            $existing_images = [];
        }
    }
}

// ---- Handle Delete Property ----
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $is_admin) {
    $delete_id = (int)$_GET['delete'];

    $stmt = $pdo->prepare("SELECT admin_document, image_url FROM properties WHERE id = ?");
    $stmt->execute([$delete_id]);
    $row_to_del = $stmt->fetch();

    if ($row_to_del) {
        if (!empty($row_to_del['admin_document'])) {
            $f = __DIR__ . '/' . ltrim($row_to_del['admin_document'], '/');
            if (file_exists($f)) @unlink($f);
        }
        if (!empty($row_to_del['image_url'])) {
            $f = __DIR__ . '/' . ltrim($row_to_del['image_url'], '/');
            if (file_exists($f)) @unlink($f);
        }
    }

    // Delete all gallery images
    try {
        $imgs = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ?");
        $imgs->execute([$delete_id]);
        foreach ($imgs->fetchAll() as $im) {
            $f = __DIR__ . '/' . ltrim($im['image_url'], '/');
            if (file_exists($f)) @unlink($f);
        }
        $pdo->prepare("DELETE FROM property_images WHERE property_id = ?")->execute([$delete_id]);
    } catch (Exception $e) {}

    $pdo->prepare("DELETE FROM properties WHERE id = ?")->execute([$delete_id]);
    header("Location: properties.php?msg=deleted");
    exit;
}

// ---- Parse helpers ----
function parseDate($dateStr) {
    if (empty($dateStr) || trim($dateStr) === '') return null;
    $dateStr = trim($dateStr);
    $parts = explode(' ', $dateStr);
    $dateStr = $parts[0];
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateStr, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $dateStr, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    $ts = strtotime($dateStr);
    if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);
    return null;
}

function parseDateTimeFlexible($str) {
    if (empty($str) || trim($str) === '') return null;
    $str = trim($str);
    $lower = strtolower($str);
    if (in_array($lower, ['#value!', 'na', 'n/a', 'null', '-', 'club', 'club case'])) return null;

    $year = 0; $month = 0; $day = 0; $timePart = '';

    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})\s*(.*)$/', $str, $m)) {
        $year = (int)$m[1]; $month = (int)$m[2]; $day = (int)$m[3]; $timePart = trim($m[4]);
    } elseif (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})\s*(.*)$/', $str, $m)) {
        $day = (int)$m[1]; $month = (int)$m[2]; $year = (int)$m[3];
        if ($year < 100) $year += 2000;
        $timePart = trim($m[4]);
    } else {
        $ts = strtotime($str);
        if ($ts !== false && $ts > 0) return date('Y-m-d H:i:s', $ts);
        return null;
    }

    $hour = 0; $minute = 0; $second = 0;
    if (!empty($timePart)) {
        if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?\s*(AM|PM|am|pm)$/', $timePart, $t)) {
            $hour = (int)$t[1]; $minute = (int)$t[2];
            $second = isset($t[3]) && $t[3] !== '' ? (int)$t[3] : 0;
            $ampm = strtoupper($t[4]);
            if ($ampm === 'PM' && $hour < 12) $hour += 12;
            if ($ampm === 'AM' && $hour == 12) $hour = 0;
        } elseif (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $timePart, $t)) {
            $hour = (int)$t[1]; $minute = (int)$t[2];
            $second = isset($t[3]) && $t[3] !== '' ? (int)$t[3] : 0;
        } elseif (preg_match('/^(\d{1,2})$/', $timePart, $t)) {
            $hour = (int)$t[1];
        }
    }

    if ($month < 1 || $month > 12) return null;
    if ($day < 1 || $day > 31) return null;
    if ($year < 1900 || $year > 2100) return null;
    if ($hour < 0 || $hour > 23) return null;
    if ($minute < 0 || $minute > 59) return null;
    if ($second < 0 || $second > 59) return null;
    if (!checkdate($month, $day, $year)) return null;

    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
}

// ============================================================
// Handle Form Submission
// ============================================================
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

    $auction_date_parsed = parseDate($auction_date);
    $inspection_date_parsed = parseDate($inspection_date);
    $emd_deadline_parsed = parseDateTimeFlexible($emd_deadline);
    $auction_start_parsed = parseDateTimeFlexible($auction_start_time);
    $auction_end_parsed = parseDateTimeFlexible($auction_end_time);

    // UPLOAD: Admin Document
    $admin_document_path = $edit_mode ? ($prop['admin_document'] ?? null) : null;
    $upload_error = '';

    if (isset($_FILES['admin_document']) && $_FILES['admin_document']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/admin_documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_tmp = $_FILES['admin_document']['tmp_name'];
        $file_ext = strtolower(pathinfo(basename($_FILES['admin_document']['name']), PATHINFO_EXTENSION));
        if (in_array($file_ext, ['pdf','jpg','jpeg','png'])) {
            $new_name = uniqid('admin_doc_') . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                if ($edit_mode && !empty($prop['admin_document'])) {
                    $old = __DIR__ . '/' . ltrim($prop['admin_document'], '/');
                    if (file_exists($old)) @unlink($old);
                }
                $admin_document_path = 'uploads/admin_documents/' . $new_name;
            } else {
                $upload_error = "Failed to move uploaded document.";
            }
        } else {
            $upload_error = "Invalid document type. Only PDF, JPG, JPEG, PNG allowed.";
        }
    }

    // UPLOAD: Main Image
    $image_url_path = $edit_mode ? ($prop['image_url'] ?? null) : null;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $img_dir = __DIR__ . '/uploads/property_images/';
        if (!is_dir($img_dir)) mkdir($img_dir, 0755, true);
        $img_tmp = $_FILES['image']['tmp_name'];
        $img_ext = strtolower(pathinfo(basename($_FILES['image']['name']), PATHINFO_EXTENSION));
        if (in_array($img_ext, ['jpg','jpeg','png','webp','gif'])) {
            $new_img_name = uniqid('prop_img_') . '.' . $img_ext;
            if (move_uploaded_file($img_tmp, $img_dir . $new_img_name)) {
                if ($edit_mode && !empty($prop['image_url'])) {
                    $old = __DIR__ . '/' . ltrim($prop['image_url'], '/');
                    if (file_exists($old)) @unlink($old);
                }
                $image_url_path = 'uploads/property_images/' . $new_img_name;
            } else {
                $upload_error = "Failed to move uploaded image.";
            }
        } else {
            $upload_error = "Invalid image type.";
        }
    }

    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if ($edit_mode && !empty($prop['image_url'])) {
            $old = __DIR__ . '/' . ltrim($prop['image_url'], '/');
            if (file_exists($old)) @unlink($old);
        }
        $image_url_path = null;
    }

    $action = $_POST['action'];

    if ($action === 'add') {
        $stmt = $pdo->prepare("
            INSERT INTO properties (
                title, description, price, location, city, state, type, bank_name, 
                sqft, possession_type, borrower_name, emd_amount, bid_increment, 
                emd_deadline, auction_start_time, auction_end_time, locality, 
                reserve_price_per_sqft, contact_number, status, auction_date, 
                inspection_date, admin_document, image_url, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $title, $description, $price, $location, $city, $state, $type, $bank_name,
            $sqft, $possession_type, $borrower_name, $emd_amount, $bid_increment,
            $emd_deadline_parsed, $auction_start_parsed, $auction_end_parsed, $locality,
            $reserve_price_per_sqft, $contact_number, $status, $auction_date_parsed,
            $inspection_date_parsed, $admin_document_path, $image_url_path
        ]);
        $new_prop_id = $pdo->lastInsertId();

        saveGalleryImages($pdo, $new_prop_id, $image_url_path);

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
                contact_number = ?, status = ?, auction_date = ?, inspection_date = ?,
                admin_document = ?, image_url = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $description, $price, $location, $city, $state, $type, $bank_name,
            $sqft, $possession_type, $borrower_name, $emd_amount, $bid_increment,
            $emd_deadline_parsed, $auction_start_parsed, $auction_end_parsed, $locality,
            $reserve_price_per_sqft, $contact_number, $status, $auction_date_parsed,
            $inspection_date_parsed, $admin_document_path, $image_url_path, $id
        ]);

        saveGalleryImages($pdo, $id, $image_url_path);

        header("Location: properties.php?msg=updated");
        exit;
    }
}

// ============================================================
// 🔥 HELPER: Save Multiple Gallery Images
// ============================================================
function saveGalleryImages($pdo, $property_id, $main_image = null) {
    if (empty($_FILES['images']['name'][0])) return;

    $img_dir = __DIR__ . '/uploads/property_images/';
    if (!is_dir($img_dir)) mkdir($img_dir, 0755, true);

    $maxOrder = 0;
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) FROM property_images WHERE property_id = ?");
        $stmt->execute([$property_id]);
        $maxOrder = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}

    $first_uploaded = null;

    foreach ($_FILES['images']['name'] as $i => $name) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $tmp = $_FILES['images']['tmp_name'][$i];
        $ext = strtolower(pathinfo(basename($name), PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;

        $new_name = uniqid('gallery_') . '.' . $ext;
        if (move_uploaded_file($tmp, $img_dir . $new_name)) {
            $url = 'uploads/property_images/' . $new_name;

            $pdo->prepare("
                INSERT INTO property_images (property_id, image_url, display_order, created_at)
                VALUES (?, ?, ?, NOW())
            ")->execute([$property_id, $url, ++$maxOrder]);

            if ($first_uploaded === null) $first_uploaded = $url;
        }
    }

    if (!empty($first_uploaded)) {
        $pdo->prepare("UPDATE properties SET image_url = COALESCE(NULLIF(image_url, ''), ?) WHERE id = ?")
            ->execute([$first_uploaded, $property_id]);
    }
}

include 'header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            🏠 All Properties 
            <span class="badge bg-primary" style="font-size:0.9rem;"><?= number_format($total_properties) ?></span>
            <?php if ($total_pages > 1): ?>
                <small class="text-muted" style="font-size:0.85rem;">
                    (Page <?= $page ?> of <?= $total_pages ?>)
                </small>
            <?php endif; ?>
        </h1>
        <a href="?add=1" class="btn btn-primary btn-lg rounded-pill shadow <?= isset($_GET['add']) ? 'disabled' : '' ?>">
            <i class="fas fa-plus-circle me-2"></i> Add New Property
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php if ($_GET['msg'] === 'added'): ?>✅ Property added successfully!
            <?php elseif ($_GET['msg'] === 'updated'): ?>✅ Property updated successfully!
            <?php elseif ($_GET['msg'] === 'deleted'): ?>✅ Property deleted successfully!
            <?php elseif ($_GET['msg'] === 'image_deleted'): ?>✅ Image deleted!
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($upload_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ❌ <?= htmlspecialchars($upload_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ============ ADD/EDIT FORM ============ -->
    <?php if (isset($_GET['add']) || $edit_mode): ?>
    <div class="card shadow-lg rounded-4 mb-5 border-0">
        <div class="card-header bg-primary text-white p-3 rounded-top-4">
            <h5 class="mb-0"><i class="fas fa-<?= $edit_mode ? 'edit' : 'plus' ?> me-2"></i> <?= $edit_mode ? 'Edit Property' : 'Add New Property' ?></h5>
        </div>
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $edit_mode ? 'edit' : 'add' ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($prop['id'] ?? '') ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Title *</label>
                        <input type="text" name="title" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($prop['title'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Address / Location *</label>
                        <input type="text" name="location" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($prop['location'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Reserve Price (₹) *</label>
                        <input type="number" name="price" class="form-control" required step="0.01" value="<?= $edit_mode ? ($prop['price'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Price per Sq Ft</label>
                        <input type="number" name="reserve_price_per_sqft" class="form-control" step="0.01" value="<?= $edit_mode ? ($prop['reserve_price_per_sqft'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Area (Sq Ft)</label>
                        <input type="number" name="sqft" class="form-control" value="<?= $edit_mode ? ($prop['sqft'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Borrower Name</label>
                        <input type="text" name="borrower_name" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['borrower_name'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['bank_name'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Property Type</label>
                        <?php $currentType = $edit_mode ? ($prop['type'] ?? '') : ''; ?>
                        <select name="type" class="form-control">
                            <option value="">-- Select Type --</option>
                            <?php foreach ($property_types as $pt): ?>
                                <option value="<?= htmlspecialchars($pt) ?>" <?= ($currentType === $pt || ($pt === 'Car/Vehicle' && $currentType === 'Car')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Possession</label>
                        <?php $currentPoss = $edit_mode ? ($prop['possession_type'] ?? '') : ''; ?>
                        <select name="possession_type" class="form-control">
                            <option value="Physical" <?= $currentPoss == 'Physical' ? 'selected' : '' ?>>Physical</option>
                            <option value="Symbolic" <?= $currentPoss == 'Symbolic' ? 'selected' : '' ?>>Symbolic</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Locality</label>
                        <input type="text" name="locality" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['locality'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">City *</label>
                        <input type="text" name="city" class="form-control" required value="<?= $edit_mode ? htmlspecialchars($prop['city'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">State</label>
                        <input type="text" name="state" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['state'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">EMD Amount (₹)</label>
                        <input type="number" name="emd_amount" class="form-control" step="0.01" value="<?= $edit_mode ? ($prop['emd_amount'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">BID Increment (₹)</label>
                        <input type="number" name="bid_increment" class="form-control" step="0.01" value="<?= $edit_mode ? ($prop['bid_increment'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">EMD Deadline</label>
                        <input type="text" name="emd_deadline" class="form-control"
                               value="<?= ($edit_mode && !empty($prop['emd_deadline'])) ? date('d/m/Y h:i A', strtotime($prop['emd_deadline'])) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Auction Start</label>
                        <input type="text" name="auction_start_time" class="form-control"
                               value="<?= ($edit_mode && !empty($prop['auction_start_time'])) ? date('d/m/Y h:i A', strtotime($prop['auction_start_time'])) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Auction End</label>
                        <input type="text" name="auction_end_time" class="form-control"
                               value="<?= ($edit_mode && !empty($prop['auction_end_time'])) ? date('d/m/Y h:i A', strtotime($prop['auction_end_time'])) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Inspection Date</label>
                        <input type="text" name="inspection_date" class="form-control"
                               value="<?= ($edit_mode && !empty($prop['inspection_date'])) ? date('d/m/Y', strtotime($prop['inspection_date'])) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Auction Date (DD/MM/YYYY) *</label>
                        <input type="text" name="auction_date" class="form-control" required
                               value="<?= ($edit_mode && !empty($prop['auction_date'])) ? date('d/m/Y', strtotime($prop['auction_date'])) : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Contact Number</label>
                        <input type="text" name="contact_number" class="form-control" value="<?= $edit_mode ? htmlspecialchars($prop['contact_number'] ?? '') : '' ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Status</label>
                        <?php $currentStatus = $edit_mode ? ($prop['status'] ?? 'available') : 'available'; ?>
                        <select name="status" class="form-control">
                            <option value="available" <?= $currentStatus == 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="sold" <?= $currentStatus == 'sold' ? 'selected' : '' ?>>Sold</option>
                            <option value="pending" <?= $currentStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>

                    <!-- MAIN IMAGE -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-primary">
                            <i class="fas fa-image me-1"></i> Main / Cover Image
                        </label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted d-block mt-1">JPG, PNG, WEBP, GIF allowed</small>

                        <?php if ($edit_mode && !empty($prop['image_url'])): ?>
                            <div class="mt-3 p-3 rounded-3" style="background:#f0fdf4; border:1px solid #86efac;">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= htmlspecialchars($prop['image_url']) ?>"
                                         alt="Main"
                                         style="width:80px; height:80px; object-fit:cover; border-radius:10px; border:2px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                                    <div>
                                        <strong class="d-block small text-success">
                                            <i class="fas fa-check-circle me-1"></i> Current Cover
                                        </strong>
                                        <div class="form-check mt-2">
                                            <input type="checkbox" name="remove_image" value="1"
                                                   class="form-check-input" id="remove_image">
                                            <label class="form-check-label small text-danger fw-bold" for="remove_image">
                                                <i class="fas fa-trash me-1"></i> Remove Cover
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- GALLERY IMAGES -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-success">
                            <i class="fas fa-images me-1"></i> Gallery Images (Multiple)
                        </label>
                        <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                        <small class="text-muted d-block mt-1">
                            एक साथ कई images select करें (Ctrl/Cmd दबाकर)
                        </small>

                        <?php if ($edit_mode && !empty($existing_images)): ?>
                            <div class="mt-3 p-3 rounded-3" style="background:#eff6ff; border:1px solid #93c5fd;">
                                <strong class="d-block small mb-2 text-primary">
                                    <i class="fas fa-images me-1"></i> Current Gallery (<?= count($existing_images) ?>)
                                </strong>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($existing_images as $img): ?>
                                        <div style="position:relative;">
                                            <img src="<?= htmlspecialchars($img['image_url']) ?>"
                                                 alt="Gallery"
                                                 style="width:70px; height:70px; object-fit:cover; border-radius:8px; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                                            <a href="delete_property_image.php?id=<?= $img['id'] ?>&prop=<?= $prop['id'] ?>"
                                               onclick="return confirm('Delete this image?')"
                                               style="position:absolute; top:-6px; right:-6px; width:22px; height:22px; border-radius:50%; background:#dc2626; color:#fff; font-size:11px; text-align:center; line-height:22px; text-decoration:none; font-weight:bold; box-shadow:0 2px 6px rgba(220,38,38,0.4);">
                                                ×
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ADMIN DOCUMENT -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-danger">
                            <i class="fas fa-file-pdf me-1"></i> Admin Document (PDF/JPG/PNG - Only for Admins)
                        </label>
                        <input type="file" name="admin_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        <?php if ($edit_mode && !empty($prop['admin_document'])): ?>
                            <?php 
                            $doc_check = __DIR__ . '/' . ltrim($prop['admin_document'], '/');
                            $doc_exists = file_exists($doc_check);
                            ?>
                            <?php if ($doc_exists): ?>
                                <small class="text-success d-block mt-1">
                                    Current file: <a href="view_document.php?id=<?= $prop['id'] ?>" target="_blank">
                                        <i class="fas fa-external-link-alt me-1"></i>View Document
                                    </a>
                                </small>
                            <?php else: ?>
                                <small class="text-danger d-block mt-1">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Previous document is missing on server. कृपया दोबारा upload करें।
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= $edit_mode ? htmlspecialchars($prop['description'] ?? '') : '' ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-pill shadow">
                            <i class="fas fa-save me-2"></i> <?= $edit_mode ? 'Update Property' : 'Add Property' ?>
                        </button>
                        <a href="properties.php" class="btn btn-outline-secondary w-100 mt-2 rounded-pill">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============ SEARCH FILTERS ============ -->
    <div class="card shadow-sm rounded-4 mb-4 border-0">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">City</label>
                    <input type="text" name="city" class="form-control" placeholder="City..." value="<?= htmlspecialchars($city_filter) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Bank</label>
                    <input type="text" name="bank" class="form-control" placeholder="Bank..." value="<?= htmlspecialchars($bank_filter) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small text-muted">Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <?php foreach ($property_types as $pt): ?>
                            <option value="<?= htmlspecialchars($pt) ?>" <?= ($type_filter == $pt) ? 'selected' : '' ?>><?= htmlspecialchars($pt) ?></option>
                        <?php endforeach; ?>
                    </select>
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

    <!-- ============ PROPERTY LIST ============ -->
    <?php if (count($properties) > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Bank</th>
                        <th>City</th>
                        <th>Locality</th>
                        <th>Price</th>
                        <th>Auction Date</th>
                        <th>Status</th>
                        <th>Admin Doc</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $row):
                        // 🔥 Gallery count from batch-loaded array (no extra query!)
                        $gallery_count = $gallery_counts[$row['id']] ?? 0;

                        // 🔥 Show image if path exists (skip file_exists per row for speed)
                        // Use a faster heuristic: if path is non-empty, show it
                        $has_main_img = !empty($row['image_url']);
                        $has_doc = !empty($row['admin_document']);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id'] ?? '') ?></td>
                            <td>
                                <?php if ($has_main_img): ?>
                                    <img src="<?= htmlspecialchars($row['image_url']) ?>"
                                         alt="Property"
                                         style="width:60px; height:60px; object-fit:cover; border-radius:8px; cursor:pointer; border:2px solid #e2e8f0;"
                                         loading="lazy"
                                         onclick="window.open(this.src, '_blank')">
                                <?php else: ?>
                                    <span class="badge bg-secondary">No Image</span>
                                <?php endif; ?>
                                <?php if ($gallery_count > 0): ?>
                                    <div style="font-size:0.65rem; color:#64748b; margin-top:2px;">+<?= $gallery_count ?> gallery</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['title'] ?? '') ?></td>
                            <td>
                                <?php if (!empty($row['type'])): ?>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($row['type']) ?></span>
                                <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['bank_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['city'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['locality'] ?? '—') ?></td>
                            <td>₹ <?= number_format($row['price'] ?? 0, 2) ?></td>
                            <td>
                                <?php if (!empty($row['auction_date'])): ?>
                                    <?= date('d M Y', strtotime($row['auction_date'])) ?>
                                <?php else: ?><span class="text-muted">N/A</span><?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $statusVal = $row['status'] ?? 'available';
                                $badgeClass = ($statusVal == 'available') ? 'success' : (($statusVal == 'sold') ? 'danger' : 'warning');
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($statusVal) ?></span>
                            </td>
                            <td>
                                <?php if ($has_doc): ?>
                                    <a href="view_document.php?id=<?= $row['id'] ?>" target="_blank" 
                                       class="btn btn-sm btn-outline-info" title="View Admin Document">
                                        <i class="fas fa-file-pdf"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">No File</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="properties.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                <?php if ($is_admin): ?>
                                    <a href="properties.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?\n\nAll images and documents will be deleted!')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ============ PAGINATION ============ -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- Prev -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])) ?>">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    </li>

                    <?php
                    // Show max 7 page numbers around current
                    $start = max(1, $page - 3);
                    $end = min($total_pages, $page + 3);
                    if ($start > 1): ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a></li>
                        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                    <?php endif;

                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor;

                    if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a></li>
                    <?php endif; ?>

                    <!-- Next -->
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])) ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-info text-center py-4">
            <i class="fas fa-inbox fa-3x d-block mb-3 opacity-50"></i>
            <h5>No properties found</h5>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
