<?php
// ============================================================
// ✅ get_property.php – सबसे सरल और पक्का Version
// ============================================================

// ✅ Explicit Session Start (db.php से पहले)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ✅ Session Check – अगर User Admin नहीं है तो Error Return करें
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'You must be logged in as admin.']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Property ID required']);
    exit;
}

// ✅ Property Data Fetch – Explicit Columns
$sql = "SELECT 
            id, title, description, price, location, city, state, type, 
            google_location, image_url, bank_name, sqft, possession_type, 
            inspection_date, borrower_name, emd_amount, bid_increment, 
            emd_deadline, auction_start_time, auction_end_time, locality, 
            reserve_price_per_sqft, contact_number, status, created_at 
        FROM properties 
        WHERE id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$property = $stmt->fetch();

if(!$property) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Property not found']);
    exit;
}

// ✅ inspection_date को DD/MM/YYYY में Convert करें
if(!empty($property['inspection_date'])) {
    $date_obj = DateTime::createFromFormat('Y-m-d', $property['inspection_date']);
    if($date_obj) {
        $property['inspection_date'] = $date_obj->format('d/m/Y');
    }
}

header('Content-Type: application/json');
echo json_encode($property);
exit;
?>
