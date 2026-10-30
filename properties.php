<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'sub_admin');

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

// PAGINATION
$per_page = 100;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE $where");
$count_stmt->execute($params);
$total_properties = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_properties / $per_page));

$sql = "SELECT * FROM properties WHERE $where ORDER BY id DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

// BATCH LOAD GALLERY COUNTS
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
    } catch (Exception $e) {}
}

// Handle Add/Edit
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
        try {
            $img_stmt = $pdo->prepare("SELECT * FROM property_images WHERE property_id = ? ORDER BY display_order ASC, id ASC");
            $img_stmt->execute([$edit_id]);
            $existing_images = $img_stmt->fetchAll();
        } catch (Exception $e) {}
    }
}

// ============================================================
// Handle Delete Property (admin only) — deletes files from Supabase too
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && $is_admin) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT admin_document, image_url FROM properties WHERE id = ?");
    $stmt->execute([$delete_id]);
    $row_to_del = $stmt->fetch();
    if ($row_to_del) {
        if (!empty($row_to_del['admin_document'])) {
            deleteCloudFileByUrl($row_to_del['admin_document']);
        }
        if (!empty($row_to_del['image_url'])) {
            deleteCloudFileByUrl($row_to_del['image_url']);
        }
    }
    try {
        $imgs = $pdo->prepare("SELECT image_url FROM property_images WHERE property_id = ?");
        $imgs->execute([$delete_id]);
        foreach ($imgs->fetchAll() as $im) {
            deleteCloudFileByUrl($im['image_url']);
        }
        $pdo->prepare("DELETE FROM property_images WHERE property_id = ?")->execute([$delete_id]);
    } catch (Exception $e) {}
    $pdo->prepare("DELETE FROM properties WHERE id = ?")->execute([$delete_id]);
    header("Location: properties.php?msg=deleted");
    exit;
}

// Parse helpers
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

    // ============================================================
    // 📄 ADMIN DOCUMENT UPLOAD (Cloud) + REMOVE
    // ============================================================
    $admin_document_path = $edit_mode ? ($prop['admin_document'] ?? null) : null;
    $upload_error = '';

    if (isset($_FILES['admin_document']) && $_FILES['admin_document']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo(basename($_FILES['admin_document']['name']), PATHINFO_EXTENSION));
        if (in_array($file_ext, ['pdf','jpg','jpeg','png'])) {
            $uploaded = uploadFileToCloud($_FILES['admin_document'], 'admin_documents');
            if ($uploaded) {
                $admin_document_path = $uploaded;
            } else {
                $upload_error = "Failed to upload document to cloud. Check SUPABASE_SERVICE_KEY on Render.";
            }
        } else {
            $upload_error = "Invalid document type.";
        }
    }

    // Explicit remove admin document (only when user checks the checkbox)
    if (isset($_POST['remove_admin_document']) && $_POST['remove_admin_document'] == '1') {
        if ($edit_mode && !empty($prop['admin_document'])) {
            deleteCloudFileByUrl($prop['admin_document']);
        }
        $admin_document_path = null;
    }

    // ============================================================
    // 🖼️ MAIN IMAGE UPLOAD (Cloud) + REMOVE
    // ============================================================
    $image_url_path = $edit_mode ? ($prop['image_url'] ?? null) : null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $img_ext = strtolower(pathinfo(basename($_FILES['image']['name']), PATHINFO_EXTENSION));
        if (in_array($img_ext, ['jpg','jpeg','png','webp','gif'])) {
            $uploaded = uploadFileToCloud($_FILES['image'], 'property_images');
            if ($uploaded) {
                $image_url_path = $uploaded;
            } else {
                $upload_error = $upload_error ?: "Failed to upload image to cloud.";
            }
        }
    }

    // Explicit remove image
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        if ($edit_mode && !empty($prop['image_url'])) {
            deleteCloudFileByUrl($prop['image_url']);
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
// Save Gallery Images (Cloud)
// ============================================================
function saveGalleryImages($pdo, $property_id, $main_image = null) {
    if (empty($_FILES['images']['name'][0])) return;
    $maxOrder = 0;
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(display_order), 0) FROM property_images WHERE property_id = ?");
        $stmt->execute([$property_id]);
        $maxOrder = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}
    $first_uploaded = null;
    foreach ($_FILES['images']['name'] as $i => $name) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $ext = strtolower(pathinfo(basename($name), PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;

        $single = [
            'name'     => $_FILES['images']['name'][$i],
            'type'     => $_FILES['images']['type'][$i],
            'tmp_name' => $_FILES['images']['tmp_name'][$i],
            'error'    => $_FILES['images']['error'][$i],
            'size'     => $_FILES['images']['size'][$i],
        ];
        $url = uploadFileToCloud($single, 'property_images');
        if ($url) {
            $pdo->prepare("INSERT INTO property_images (property_id, image_url, display_order, created_at) VALUES (?, ?, ?, NOW())")
                ->execute([$property_id, $url, ++$maxOrder]);
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

<!-- ============================================================ -->
<!-- 🔥 ALL STYLES                                                 -->
<!-- ============================================================ -->
<style>
    .compact-table-wrapper {
        background: #fff;
        border-radius: 14px;
        overflow-x: auto;
        overflow-y: visible;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
        position: relative;
    }
    .compact-table {
        width: 100%;
        min-width: 1200px;
        margin: 0;
        font-size: 0.8rem;
        border-collapse: separate;
        border-spacing: 0;
    }
    .compact-table thead th {
        background: #1e293b;
        color: #fff;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 9px 8px;
        text-align: left;
        white-space: nowrap;
        border: none;
        position: sticky;
        top: 0;
        z-index: 3;
    }
    .compact-table tbody td {
        padding: 6px 8px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        line-height: 1.3;
        background: #fff;
        color: #1e293b;
    }
    .compact-table tbody tr:hover td { background: #f8faff; }
    .compact-table tbody tr:last-child td { border-bottom: none; }
    .compact-table .col-id { width: 55px; font-weight: 700; color: #1e40af; font-size: 0.75rem; }
    .compact-table .col-img { width: 55px; }
    .compact-table .col-img img {
        width: 40px; height: 40px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        display: block;
        cursor: pointer;
        transition: transform 0.15s;
    }
    .compact-table .col-img img:hover {
        transform: scale(2);
        z-index: 100;
        position: relative;
        box-shadow: 0 8px 25px rgba(0,0,0,0.25);
    }
    .compact-table .col-img .no-img {
        width: 40px; height: 40px;
        background: #f1f5f9;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
        font-size: 0.6rem;
        font-weight: 700;
    }
    .compact-table .col-title { max-width: 200px; font-weight: 600; color: #0f172a; }
    .compact-table .ellipsis {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .compact-table .col-type .badge,
    .compact-table .col-status .badge {
        font-size: 0.62rem;
        padding: 3px 8px;
        border-radius: 20px;
        font-weight: 700;
    }
    .compact-table .col-bank { max-width: 120px; font-size: 0.75rem; color: #475569; }
    .compact-table .col-city { font-size: 0.78rem; color: #334155; font-weight: 600; white-space: nowrap; }
    .compact-table .col-locality { font-size: 0.72rem; color: #64748b; max-width: 110px; }
    .compact-table .col-price { font-weight: 700; color: #059669; font-size: 0.82rem; white-space: nowrap; }
    .compact-table .col-date { font-size: 0.72rem; color: #64748b; white-space: nowrap; }
    .compact-table .col-doc { width: 55px; text-align: center; }
    .compact-table .col-actions {
        width: 85px;
        text-align: center;
        white-space: nowrap;
        position: sticky;
        right: 0;
        background: #fff !important;
        box-shadow: -4px 0 8px -2px rgba(0,0,0,0.06);
        z-index: 2;
    }
    .compact-table thead .col-actions {
        background: #1e293b !important;
        z-index: 4;
    }
    .compact-table tbody tr:hover .col-actions {
        background: #f8faff !important;
    }
    .btn-xs {
        width: 26px; height: 26px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 0.7rem;
        border: none;
        transition: all 0.15s;
        vertical-align: middle;
    }
    .btn-xs.btn-primary { background: #2563eb; color: #fff; }
    .btn-xs.btn-primary:hover { background: #1d4ed8; transform: scale(1.1); }
    .btn-xs.btn-danger { background: #dc2626; color: #fff; }
    .btn-xs.btn-danger:hover { background: #b91c1c; transform: scale(1.1); }
    .btn-xs.btn-info { background: #0891b2; color: #fff; text-decoration: none; }
    .btn-xs.btn-info:hover { background: #0e7490; transform: scale(1.1); color: #fff; }

    .page-header-compact {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-wrap: wrap;
        gap: 10px;
    }
    .page-header-compact h1 {
        font-size: 1.3rem;
        font-weight: 800;
        margin: 0;
        color: #0f172a;
    }
    .page-header-compact .count-badge {
        background: #2563eb;
        color: #fff;
        font-size: 0.72rem;
        padding: 3px 10px;
        border-radius: 20px;
        font-weight: 700;
        margin-left: 6px;
    }
    .filter-bar-compact {
        background: #fff;
        border-radius: 12px;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        margin-bottom: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .filter-bar-compact .form-control {
        font-size: 0.8rem;
        padding: 6px 12px;
        height: 34px;
        border-radius: 8px;
    }
    .filter-bar-compact .btn {
        font-size: 0.8rem;
        padding: 6px 14px;
        height: 34px;
        border-radius: 8px;
    }
    .compact-pagination {
        margin-top: 14px;
        display: flex;
        justify-content: center;
        gap: 4px;
        flex-wrap: wrap;
    }
    .compact-pagination a, .compact-pagination span {
        padding: 5px 11px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s;
    }
    .compact-pagination a:hover {
        background: #eff6ff;
        border-color: #2563eb;
        color: #2563eb;
    }
    .compact-pagination .active {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
    }
    .compact-pagination .disabled { opacity: 0.4; pointer-events: none; }
    .compact-pagination .dots { border: none; background: transparent; }

    /* ========== CUSTOM FILE UPLOAD UI ========== */
    .custom-upload-box {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 18px;
        text-align: center;
        background: #f8fafc;
        transition: all 0.2s;
        cursor: pointer;
    }
    .custom-upload-box:hover {
        border-color: #2563eb;
        background: #eff6ff;
    }
    .custom-upload-box.danger:hover {
        border-color: #dc2626;
        background: #fef2f2;
    }
    .custom-upload-box input[type="file"] { display: none; }
    .custom-upload-box .upload-icon {
        font-size: 1.8rem;
        color: #94a3b8;
        margin-bottom: 6px;
    }
    .custom-upload-box.danger .upload-icon { color: #dc2626; }
    .custom-upload-box .upload-text {
        font-size: 0.85rem;
        color: #475569;
        font-weight: 600;
    }
    .custom-upload-box .upload-hint {
        font-size: 0.72rem;
        color: #94a3b8;
        margin-top: 4px;
    }

    /* Preview grid */
    .preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 8px;
        margin-top: 10px;
    }
    .preview-item {
        position: relative;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid #e2e8f0;
        background: #fff;
        aspect-ratio: 1;
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }
    .preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .preview-item .file-name {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(15,23,42,0.75);
        color: #fff;
        font-size: 0.6rem;
        padding: 3px 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        backdrop-filter: blur(4px);
    }
    .preview-item .remove-btn {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #dc2626;
        color: #fff;
        border: 2px solid #fff;
        font-size: 12px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-weight: bold;
        box-shadow: 0 2px 6px rgba(220,38,38,0.4);
        transition: all 0.15s;
    }
    .preview-item .remove-btn:hover {
        background: #b91c1c;
        transform: scale(1.15);
    }
    .preview-item.single .remove-btn {
        width: 26px;
        height: 26px;
        font-size: 14px;
    }
    .preview-label {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    /* 🔥 Document preview card */
    .doc-preview-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: 12px;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        margin-top: 10px;
        transition: all 0.2s;
    }
    .doc-preview-card .doc-icon {
        width: 42px;
        height: 42px;
        background: #dc2626;
        color: #fff;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .doc-preview-card .doc-info {
        flex: 1;
        min-width: 0;
    }
    .doc-preview-card .doc-name {
        font-weight: 700;
        font-size: 0.82rem;
        color: #78350f;
        word-break: break-all;
        line-height: 1.2;
    }
    .doc-preview-card .doc-meta {
        font-size: 0.7rem;
        color: #92400e;
        margin-top: 2px;
    }
    .doc-preview-card .doc-remove {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #dc2626;
        color: #fff;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        transition: all 0.15s;
        flex-shrink: 0;
    }
    .doc-preview-card .doc-remove:hover {
        background: #b91c1c;
        transform: scale(1.1);
    }
    .doc-preview-card.marked-remove {
        background: #fee2e2;
        border-color: #f87171;
        opacity: 0.7;
    }
    .doc-preview-card.marked-remove .doc-name,
    .doc-preview-card.marked-remove .doc-meta {
        text-decoration: line-through;
        color: #991b1b;
    }
</style>

<div class="container-fluid mt-3">

    <div class="page-header-compact">
        <h1>
            🏠 All Properties 
            <span class="count-badge"><?= number_format($total_properties) ?></span>
            <?php if ($total_pages > 1): ?>
                <small class="text-muted ms-2" style="font-size:0.75rem; font-weight:500;">
                    Page <?= $page ?> / <?= $total_pages ?>
                </small>
            <?php endif; ?>
        </h1>
        <a href="?add=1" class="btn btn-primary btn-sm rounded-pill px-3 <?= isset($_GET['add']) ? 'disabled' : '' ?>">
            <i class="fas fa-plus-circle me-1"></i> Add New Property
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show py-2 px-3" role="alert" style="font-size:0.85rem;">
            <?php if ($_GET['msg'] === 'added'): ?>✅ Property added!
            <?php elseif ($_GET['msg'] === 'updated'): ?>✅ Property updated!
            <?php elseif ($_GET['msg'] === 'deleted'): ?>✅ Property deleted!
            <?php elseif ($_GET['msg'] === 'image_deleted'): ?>✅ Image deleted!
            <?php endif; ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($upload_error)): ?>
        <div class="alert alert-warning alert-dismissible fade show py-2 px-3" style="font-size:0.85rem;">
            ⚠️ <?= htmlspecialchars($upload_error) ?>
            <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- ADD / EDIT FORM                                              -->
    <!-- ============================================================ -->
    <?php if (isset($_GET['add']) || $edit_mode): ?>
    <div class="card shadow-lg rounded-4 mb-4 border-0">
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

                    <!-- ============================================================ -->
                    <!-- MAIN / COVER IMAGE                                           -->
                    <!-- ============================================================ -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-primary">
                            <i class="fas fa-image me-1"></i> Main / Cover Image
                        </label>

                        <div class="custom-upload-box" onclick="document.getElementById('mainImageInput').click();">
                            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                            <div class="upload-text">Click to select image</div>
                            <div class="upload-hint">JPG, PNG, WEBP, GIF • Max 5MB • Saved to Cloud</div>
                            <input type="file" name="image" id="mainImageInput" accept="image/*"
                                   onchange="handleMainImageSelect(this)">
                        </div>

                        <div class="preview-grid" id="mainImagePreview"></div>

                        <?php if ($edit_mode && !empty($prop['image_url'])): ?>
                            <div class="mt-3 p-3 rounded-3" style="background:#f0fdf4; border:1px solid #86efac;">
                                <span class="preview-label">
                                    <i class="fas fa-server me-1"></i> Current on Server
                                </span>
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= htmlspecialchars($prop['image_url']) ?>"
                                         alt="Current"
                                         style="width:70px; height:70px; object-fit:cover; border-radius:10px; border:2px solid #fff; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                                    <div>
                                        <div class="form-check">
                                            <input type="checkbox" name="remove_image" value="1"
                                                   class="form-check-input" id="remove_image"
                                                   onchange="toggleServerMainRemoval(this)">
                                            <label class="form-check-label small text-danger fw-bold" for="remove_image">
                                                <i class="fas fa-trash me-1"></i> Remove This Cover
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ============================================================ -->
                    <!-- GALLERY IMAGES                                               -->
                    <!-- ============================================================ -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-success">
                            <i class="fas fa-images me-1"></i> Gallery Images (Multiple)
                        </label>

                        <div class="custom-upload-box" onclick="document.getElementById('galleryInput').click();">
                            <div class="upload-icon"><i class="fas fa-images"></i></div>
                            <div class="upload-text">Click to select multiple images</div>
                            <div class="upload-hint">एक साथ कई images select करें • Cloud</div>
                            <input type="file" name="images[]" id="galleryInput" accept="image/*" multiple
                                   onchange="handleGallerySelect(this)">
                        </div>

                        <div class="preview-grid" id="galleryPreview"></div>

                        <?php if ($edit_mode && !empty($existing_images)): ?>
                            <div class="mt-3 p-3 rounded-3" style="background:#eff6ff; border:1px solid #93c5fd;">
                                <span class="preview-label">
                                    <i class="fas fa-server me-1"></i> Current Gallery on Server (<?= count($existing_images) ?>)
                                </span>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($existing_images as $img): ?>
                                        <div style="position:relative;">
                                            <img src="<?= htmlspecialchars($img['image_url']) ?>"
                                                 alt="Gallery"
                                                 style="width:65px; height:65px; object-fit:cover; border-radius:8px; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
                                            <a href="delete_property_image.php?id=<?= $img['id'] ?>&prop=<?= $prop['id'] ?>"
                                               onclick="return confirm('Delete this image from cloud?')"
                                               style="position:absolute; top:-6px; right:-6px; width:22px; height:22px; border-radius:50%; background:#dc2626; color:#fff; font-size:11px; text-align:center; line-height:22px; text-decoration:none; font-weight:bold; box-shadow:0 2px 6px rgba(220,38,38,0.4);"
                                               title="Delete from server">
                                                ×
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ============================================================ -->
                    <!-- 🔥 ADMIN DOCUMENT                                            -->
                    <!-- ============================================================ -->
                    <div class="col-12">
                        <label class="form-label fw-bold text-danger">
                            <i class="fas fa-file-pdf me-1"></i> Admin Document (PDF/JPG/PNG - Only for Admins)
                        </label>

                        <div class="custom-upload-box danger" onclick="document.getElementById('adminDocInput').click();">
                            <div class="upload-icon"><i class="fas fa-file-upload"></i></div>
                            <div class="upload-text">Click to select document</div>
                            <div class="upload-hint">PDF, JPG, JPEG, PNG • Max 10MB • Saved to Cloud</div>
                            <input type="file" name="admin_document" id="adminDocInput" 
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   onchange="handleAdminDocSelect(this)">
                        </div>

                        <div id="adminDocPreview"></div>

                        <?php if ($edit_mode && !empty($prop['admin_document'])): 
                            $doc_exists = cloudFileExists($prop['admin_document']);
                            $doc_ext = strtolower(pathinfo($prop['admin_document'], PATHINFO_EXTENSION));
                            $doc_icon = ($doc_ext === 'pdf') ? 'fa-file-pdf' : 'fa-file-image';
                            $doc_name = basename(parse_url($prop['admin_document'], PHP_URL_PATH));
                        ?>
                            <div class="doc-preview-card" id="serverDocCard">
                                <div class="doc-icon">
                                    <i class="fas <?= $doc_icon ?>"></i>
                                </div>
                                <div class="doc-info">
                                    <div class="doc-name">
                                        <?= htmlspecialchars($doc_name) ?>
                                    </div>
                                    <div class="doc-meta">
                                        <i class="fas fa-cloud me-1"></i> On Cloud
                                        <?php if ($doc_exists): ?>
                                            • <a href="<?= htmlspecialchars($prop['admin_document']) ?>" target="_blank" style="color:#2563eb; font-weight:600;">View</a>
                                        <?php else: ?>
                                            • <span style="color:#dc2626;">File Missing</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <input type="checkbox" name="remove_admin_document" value="1"
                                       id="remove_admin_document" style="display:none;">
                                <button type="button" class="doc-remove" 
                                        onclick="toggleAdminDocRemove()" 
                                        id="adminDocRemoveBtn"
                                        title="Remove this document">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-2" style="font-size:0.7rem;">
                                <i class="fas fa-info-circle me-1"></i>
                                Trash icon दबाने पर submit के बाद document cloud से हट जाएगा
                            </small>
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

    <!-- ============================================================ -->
    <!-- FILTER BAR                                                    -->
    <!-- ============================================================ -->
    <div class="filter-bar-compact">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto" style="min-width:140px;">
                <input type="text" name="city" class="form-control form-control-sm" placeholder="🔍 City" value="<?= htmlspecialchars($city_filter) ?>">
            </div>
            <div class="col-auto" style="min-width:140px;">
                <input type="text" name="bank" class="form-control form-control-sm" placeholder="🏦 Bank" value="<?= htmlspecialchars($bank_filter) ?>">
            </div>
            <div class="col-auto" style="min-width:130px;">
                <select name="type" class="form-control form-control-sm">
                    <option value="">All Types</option>
                    <?php foreach ($property_types as $pt): ?>
                        <option value="<?= htmlspecialchars($pt) ?>" <?= ($type_filter == $pt) ? 'selected' : '' ?>><?= htmlspecialchars($pt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto" style="min-width:110px;">
                <input type="number" name="min_price" class="form-control form-control-sm" placeholder="Min ₹" value="<?= htmlspecialchars($min_price) ?>">
            </div>
            <div class="col-auto" style="min-width:110px;">
                <input type="number" name="max_price" class="form-control form-control-sm" placeholder="Max ₹" value="<?= htmlspecialchars($max_price) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
            </div>
            <div class="col-auto">
                <a href="properties.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- COMPACT TABLE                                                 -->
    <!-- ============================================================ -->
    <?php if (count($properties) > 0): ?>
        <div class="compact-table-wrapper">
            <table class="compact-table">
                <thead>
                    <tr>
                        <th class="col-id">ID</th>
                        <th class="col-img">Img</th>
                        <th class="col-title">Title</th>
                        <th class="col-type">Type</th>
                        <th class="col-bank">Bank</th>
                        <th class="col-city">City</th>
                        <th class="col-locality">Locality</th>
                        <th class="col-price">Price</th>
                        <th class="col-date">Auction</th>
                        <th class="col-status">Status</th>
                        <th class="col-doc">Doc</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $row):
                        $gallery_count = $gallery_counts[$row['id']] ?? 0;
                        $has_main_img = !empty($row['image_url']);
                        $has_doc = !empty($row['admin_document']);
                    ?>
                        <tr>
                            <td class="col-id"><?= htmlspecialchars($row['id'] ?? '') ?></td>
                            <td class="col-img">
                                <?php if ($has_main_img): ?>
                                    <img src="<?= htmlspecialchars($row['image_url']) ?>"
                                         alt="img"
                                         loading="lazy"
                                         onclick="window.open(this.src, '_blank')">
                                <?php else: ?>
                                    <div class="no-img">NO</div>
                                <?php endif; ?>
                                <?php if ($gallery_count > 0): ?>
                                    <div style="font-size:0.6rem; color:#2563eb; margin-top:1px; font-weight:700; text-align:center;">+<?= $gallery_count ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="col-title">
                                <span class="ellipsis" title="<?= htmlspecialchars($row['title'] ?? '') ?>">
                                    <?= htmlspecialchars($row['title'] ?? '') ?>
                                </span>
                            </td>
                            <td class="col-type">
                                <?php if (!empty($row['type'])): ?>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($row['type']) ?></span>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </td>
                            <td class="col-bank">
                                <span class="ellipsis" title="<?= htmlspecialchars($row['bank_name'] ?? '') ?>">
                                    <?= htmlspecialchars($row['bank_name'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="col-city"><?= htmlspecialchars($row['city'] ?? '—') ?></td>
                            <td class="col-locality">
                                <span class="ellipsis" title="<?= htmlspecialchars($row['locality'] ?? '') ?>">
                                    <?= htmlspecialchars($row['locality'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="col-price">₹<?= number_format($row['price'] ?? 0, 0) ?></td>
                            <td class="col-date">
                                <?php if (!empty($row['auction_date'])): ?>
                                    <?= date('d/m/y', strtotime($row['auction_date'])) ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td class="col-status">
                                <?php 
                                $statusVal = $row['status'] ?? 'available';
                                $badgeClass = ($statusVal == 'available') ? 'success' : (($statusVal == 'sold') ? 'danger' : 'warning');
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($statusVal) ?></span>
                            </td>
                            <td class="col-doc">
                                <?php if ($has_doc): ?>
                                    <a href="<?= htmlspecialchars($row['admin_document']) ?>" target="_blank" 
                                       class="btn-xs btn-info" title="View Admin Document">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                <?php else: ?>
                                    <span style="font-size:0.65rem; color:#94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-actions">
                                <a href="properties.php?edit=<?= $row['id'] ?>" class="btn-xs btn-primary" title="Edit Property">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($is_admin): ?>
                                    <a href="properties.php?delete=<?= $row['id'] ?>" class="btn-xs btn-danger" 
                                       onclick="return confirm('Delete this property?\n\nAll images and documents will be deleted from cloud too!')" 
                                       title="Delete Property">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
            <div class="compact-pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 3);
                $end = min($total_pages, $page + 3);

                if ($start > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                    <?php if ($start > 2): ?><span class="dots">...</span><?php endif; ?>
                <?php endif;

                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor;

                if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?><span class="dots">...</span><?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-info text-center py-5" style="border-radius:14px;">
            <i class="fas fa-inbox fa-3x d-block mb-3 opacity-50"></i>
            <h5 class="mb-1">No properties found</h5>
            <small class="text-muted">Try adjusting your filters</small>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- 🔥 JAVASCRIPT                                                -->
<!-- ============================================================ -->
<script>
// ============================================================
// MAIN IMAGE — Select + Preview + Remove
// ============================================================
function handleMainImageSelect(input) {
    const preview = document.getElementById('mainImagePreview');
    preview.innerHTML = '';

    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('❌ Image is too large. Max 5MB allowed.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            const item = document.createElement('div');
            item.className = 'preview-item single';
            item.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <div class="file-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                <button type="button" class="remove-btn" onclick="removeMainImage(event)" title="Remove this image">×</button>
            `;
            preview.appendChild(item);
        };
        reader.readAsDataURL(file);
    }
}

function removeMainImage(event) {
    event.stopPropagation();
    const input = document.getElementById('mainImageInput');
    input.value = '';
    document.getElementById('mainImagePreview').innerHTML = '';
}

// ============================================================
// GALLERY — Multiple Select + Preview + Individual Remove
// ============================================================
let galleryFiles = [];

function handleGallerySelect(input) {
    if (!input.files) return;

    const existingNames = galleryFiles.map(f => f.name + '_' + f.size);
    for (let i = 0; i < input.files.length; i++) {
        const f = input.files[i];
        if (f.size > 5 * 1024 * 1024) {
            alert(`❌ "${f.name}" is too large (Max 5MB)`);
            continue;
        }
        const key = f.name + '_' + f.size;
        if (!existingNames.includes(key)) {
            galleryFiles.push(f);
            existingNames.push(key);
        }
    }
    renderGalleryPreview();
    syncGalleryInput();
}

function renderGalleryPreview() {
    const preview = document.getElementById('galleryPreview');
    preview.innerHTML = '';

    if (galleryFiles.length > 0) {
        const label = document.createElement('div');
        label.className = 'preview-label';
        label.style.gridColumn = '1 / -1';
        label.innerHTML = `<i class="fas fa-eye me-1"></i> New selection (${galleryFiles.length}) — not uploaded yet`;
        preview.appendChild(label);
    }

    galleryFiles.forEach((file, idx) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const item = document.createElement('div');
            item.className = 'preview-item';
            item.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <div class="file-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                <button type="button" class="remove-btn" onclick="removeGalleryImage(event, ${idx})" title="Remove this image">×</button>
            `;
            preview.appendChild(item);
        };
        reader.readAsDataURL(file);
    });
}

function removeGalleryImage(event, idx) {
    event.stopPropagation();
    galleryFiles.splice(idx, 1);
    renderGalleryPreview();
    syncGalleryInput();
}

function syncGalleryInput() {
    const input = document.getElementById('galleryInput');
    const dt = new DataTransfer();
    galleryFiles.forEach(f => dt.items.add(f));
    input.files = dt.files;
}

// ============================================================
// 🔥 ADMIN DOCUMENT — Select + Preview + Remove
// ============================================================
function handleAdminDocSelect(input) {
    const preview = document.getElementById('adminDocPreview');
    preview.innerHTML = '';

    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 10 * 1024 * 1024) {
            alert('❌ Document is too large. Max 10MB allowed.');
            input.value = '';
            return;
        }
        const ext = file.name.split('.').pop().toLowerCase();
        const icon = (ext === 'pdf') ? 'fa-file-pdf' : 'fa-file-image';

        const card = document.createElement('div');
        card.className = 'doc-preview-card';
        card.id = 'newDocCard';
        card.innerHTML = `
            <div class="doc-icon"><i class="fas ${icon}"></i></div>
            <div class="doc-info">
                <div class="doc-name" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</div>
                <div class="doc-meta">
                    <i class="fas fa-cloud-upload-alt me-1"></i> New selection • 
                    ${(file.size / 1024).toFixed(0)} KB
                </div>
            </div>
            <button type="button" class="doc-remove" onclick="removeNewAdminDoc(event)" title="Remove this document">
                <i class="fas fa-trash"></i>
            </button>
        `;
        preview.appendChild(card);
    }
}

function removeNewAdminDoc(event) {
    event.stopPropagation();
    const input = document.getElementById('adminDocInput');
    input.value = '';
    document.getElementById('adminDocPreview').innerHTML = '';
}

// 🔥 Toggle remove state for EXISTING server document
function toggleAdminDocRemove() {
    const checkbox = document.getElementById('remove_admin_document');
    const card = document.getElementById('serverDocCard');
    const btn = document.getElementById('adminDocRemoveBtn');

    if (checkbox.checked) {
        checkbox.checked = false;
        card.classList.remove('marked-remove');
        btn.innerHTML = '<i class="fas fa-trash"></i>';
        btn.title = 'Remove this document';
        btn.style.background = '#dc2626';
    } else {
        if (!confirm('⚠️ क्या आप इस admin document को cloud से हटाना चाहते हैं?\n\nSubmit करते ही यह cloud storage से हट जाएगा।')) {
            return;
        }
        checkbox.checked = true;
        card.classList.add('marked-remove');
        btn.innerHTML = '<i class="fas fa-undo"></i>';
        btn.title = 'Undo remove';
        btn.style.background = '#059669';
    }
}

// ============================================================
// SERVER IMAGE REMOVAL — Visual feedback
// ============================================================
function toggleServerMainRemoval(checkbox) {
    const container = checkbox.closest('.p-3');
    const img = container.querySelector('img');
    if (checkbox.checked) {
        img.style.opacity = '0.3';
        img.style.filter = 'grayscale(1)';
        container.style.background = '#fef2f2';
        container.style.borderColor = '#fecaca';
    } else {
        img.style.opacity = '1';
        img.style.filter = 'none';
        container.style.background = '#f0fdf4';
        container.style.borderColor = '#86efac';
    }
}

// ============================================================
// HELPERS
// ============================================================
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[enctype="multipart/form-data"]');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            syncGalleryInput();
        });
    });
});
</script>

<?php include 'footer.php'; ?>
