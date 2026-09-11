<?php
// ============================================================
// 📥 Download CSV Template for Bulk Property Upload
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="property_bulk_upload_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel to properly display Hindi/special chars
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header Row – must match exactly
fputcsv($output, [
    'title',
    'location',
    'city',
    'state',
    'locality',
    'type',
    'bank_name',
    'borrower_name',
    'price',
    'reserve_price_per_sqft',
    'sqft',
    'possession_type',
    'emd_amount',
    'bid_increment',
    'emd_deadline',
    'auction_start_time',
    'auction_end_time',
    'auction_date',
    'inspection_date',
    'contact_number',
    'status',
    'description'
]);

// Sample Data Row 1
fputcsv($output, [
    'Shop in Govind Nagar, Mathura',
    'A Shop Measuring Area 15.53 Sq.mtrs Shop No. 105 First Floor, Mohalla Govind Nagar',
    'Mathura',
    'Uttar Pradesh',
    'Govind Nagar',
    'Shop',
    'SMFG India Home Finance',
    'Sameer Khan',
    '880000',
    '0',
    '167.17',
    'Physical',
    '88000',
    '0',
    '19/08/2026 12:00 PM',
    '20/08/2026 11:00 AM',
    '20/08/2026 01:00 PM',
    '20/08/2026',
    '18/08/2026',
    '8878190275',
    'available',
    'Property description goes here'
]);

// Sample Data Row 2
fputcsv($output, [
    'Flat in Subhash Chowk, Bhopal',
    'Subhash Chowk, Bhopal',
    'Bhopal',
    'MP',
    'Subhash Chowk',
    'Flat',
    'Punjab National Bank',
    'Ramesh Kumar',
    '584000',
    '500',
    '1200',
    'Symbolic',
    '58400',
    '25000',
    '01/09/2026 05:00 PM',
    '02/09/2026 11:00 AM',
    '02/09/2026 02:00 PM',
    '02/09/2026',
    '30/08/2026',
    '9876543210',
    'available',
    'Well maintained flat in central location'
]);

fclose($output);
exit;
