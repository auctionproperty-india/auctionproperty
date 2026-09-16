<?php
// ============================================================
// 💾 Backup ALL Properties → CSV Download
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

// ---- Fetch ALL Properties ----
$stmt = $pdo->query("SELECT * FROM properties ORDER BY id ASC");
$properties = $stmt->fetchAll();

// ---- Output as CSV ----
$filename = 'properties_FULL_backup_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM (Excel compatibility)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Full Header Row
fputcsv($output, [
    'id', 'title', 'location', 'city', 'state', 'locality', 'type', 'bank_name',
    'borrower_name', 'price', 'reserve_price_per_sqft', 'sqft', 'possession_type',
    'emd_amount', 'bid_increment', 'emd_deadline', 'auction_start_time',
    'auction_end_time', 'auction_date', 'inspection_date', 'contact_number',
    'status', 'description', 'created_at'
]);

foreach ($properties as $p) {
    fputcsv($output, [
        $p['id'] ?? '',
        $p['title'] ?? '',
        $p['location'] ?? '',
        $p['city'] ?? '',
        $p['state'] ?? '',
        $p['locality'] ?? '',
        $p['type'] ?? '',
        $p['bank_name'] ?? '',
        $p['borrower_name'] ?? '',
        $p['price'] ?? '',
        $p['reserve_price_per_sqft'] ?? '',
        $p['sqft'] ?? '',
        $p['possession_type'] ?? '',
        $p['emd_amount'] ?? '',
        $p['bid_increment'] ?? '',
        $p['emd_deadline'] ?? '',
        $p['auction_start_time'] ?? '',
        $p['auction_end_time'] ?? '',
        $p['auction_date'] ?? '',
        $p['inspection_date'] ?? '',
        $p['contact_number'] ?? '',
        $p['status'] ?? '',
        $p['description'] ?? '',
        $p['created_at'] ?? '',
    ]);
}

fclose($output);
exit;
