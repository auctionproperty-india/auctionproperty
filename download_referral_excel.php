<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { die("Unauthorized"); }

// Fetch all paid referrals with bank details
$data = $pdo->query("SELECT u.name as referrer, e.referred_user_id, r.name as referred, p.name as package, 
                     e.amount, e.tds_deducted, e.admin_charge_deducted, e.net_amount, 
                     e.bank_name, e.account_number, e.ifsc_code, e.paid_at
                     FROM user_referral_earnings e
                     JOIN users u ON e.user_id = u.id
                     JOIN users r ON e.referred_user_id = r.id
                     JOIN packages p ON e.package_id = p.id
                     WHERE e.status = 'paid'
                     ORDER BY e.paid_at DESC")->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=referral_payouts.csv');
$output = fopen('php://output', 'w');
fputcsv($output, ['Referrer Name', 'Referred User', 'Package', 'Gross Amount', 'TDS Deducted', 'Admin Charge', 'Net Amount', 'Bank Name', 'Account Number', 'IFSC Code', 'Paid On']);
foreach($data as $row) {
    fputcsv($output, [
        $row['referrer'],
        $row['referred'],
        $row['package'],
        $row['amount'],
        $row['tds_deducted'],
        $row['admin_charge_deducted'],
        $row['net_amount'],
        $row['bank_name'],
        $row['account_number'],
        $row['ifsc_code'],
        date('d M Y', strtotime($row['paid_at']))
    ]);
}
fclose($output);
exit;
?>
