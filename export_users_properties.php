<?php
// ============================================================
// ✅ Export Users & Properties as CSV from Render (PostgreSQL)
// ============================================================

require_once 'db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("❌ You must be logged in as admin.");
}

// Set headers to download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="data_export.csv"');

$output = fopen('php://output', 'w');

// Write header row
fputcsv($output, ['table', 'id', 'name', 'email', 'password', 'phone', 'referral_code', 'referred_by', 'role', 'status', 'city', 'state', 'wallet_balance', 'coins', 'bank_name', 'account_number', 'ifsc', 'branch', 'created_at']);

// ---- Export Users ----
$users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $user) {
    $row = array_merge(['users'], array_values($user));
    fputcsv($output, $row);
}

// ---- Export Properties ----
$props = $pdo->query("SELECT * FROM properties")->fetchAll(PDO::FETCH_ASSOC);
foreach ($props as $prop) {
    // Convert property to a flat array with consistent columns
    // We'll list all columns we want
    $row = [
        'properties',
        $prop['id'],
        $prop['title'],
        $prop['description'],
        $prop['price'],
        $prop['location'],
        $prop['city'],
        $prop['state'],
        $prop['type'],
        $prop['bank_name'],
        $prop['sqft'],
        $prop['possession_type'],
        $prop['auction_date'],
        $prop['status'],
        $prop['created_at'],
        // add other columns as needed, but keep it simple
    ];
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
