<?php
// ============================================================
// ⏰ DAILY PROPERTY UPDATE EMAIL (CRON JOB - DYNAMIC TIME)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

date_default_timezone_set('Asia/Kolkata');

$settings_query = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('daily_email_time', 'last_daily_email_date', 'email_on_daily_props')");
$settings = $settings_query->fetchAll(PDO::FETCH_KEY_PAIR);

$scheduled_time = $settings['daily_email_time'] ?? '06:30';
$last_sent_date = $settings['last_daily_email_date'] ?? '';
$is_enabled = ($settings['email_on_daily_props'] ?? '0') === '1';

if (!$is_enabled) {
    die("Email toggle is OFF. Exiting.\n");
}

$current_date = date('Y-m-d');
$current_time = date('H:i');

$scheduled_timestamp = strtotime($scheduled_time);
$scheduled_time_clean = date('H:i', $scheduled_timestamp);

if ($last_sent_date === $current_date) {
    die("Emails already sent today ({$current_date}). Exiting.\n");
}

if (strtotime($current_time) < strtotime($scheduled_time_clean)) {
    die("Current time ({$current_time}) is before scheduled time ({$scheduled_time_clean}). Exiting.\n");
}

$stmt = $pdo->prepare("
    SELECT id, title, city, state, price, sqft, auction_date, bank_name, type, image_url 
    FROM properties 
    WHERE created_at >= NOW() - INTERVAL '24 hours'
    ORDER BY city ASC, id DESC
");
$stmt->execute();
$new_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($new_properties)) {
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_daily_email_date'")->execute([$current_date]);
    die("No new properties added in the last 24 hours. Exiting.\n");
}

$grouped_properties = [];
foreach ($new_properties as $prop) {
    $city = ucwords(strtolower(trim($prop['city'])));
    $grouped_properties[$city][] = $prop;
}

$users_stmt = $pdo->query("SELECT name, email, city FROM users WHERE role = 'user' AND status = 'active'");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$property_list_html = "";
foreach ($grouped_properties as $city => $props) {
    $property_list_html .= "<h3 style='color:#2563eb; border-bottom:1px solid #eee; padding-bottom:5px; margin-top:20px;'>{$city}</h3>";
    foreach ($props as $prop) {
        $prop_link = "https://primepropertyindia.onrender.com/user_dashboard.php?view=all&property_id=" . $prop['id'];
        $property_list_html .= "
        <div style='padding: 10px 0; border-bottom: 1px solid #f1f5f9;'>
            <a href='{$prop_link}' style='color: #2563eb; text-decoration: none; font-weight: bold; font-size: 16px;'>{$prop['title']}</a><br>
            <span style='color: #10b981; font-weight: bold; font-size: 15px;'>₹ " . number_format($prop['price']) . "</span> | 
            <span style='color: #64748b; font-size: 13px;'>Bank: {$prop['bank_name']}</span><br>
            <a href='{$prop_link}' style='color: #007bff; text-decoration: underline; font-size: 13px;'>View Details</a>
        </div>";
    }
}

$sent_count = 0;
foreach ($users as $user) {
    $sent = sendSystemEmail('daily_props', $user['email'], [
        'name' => $user['name'],
        'date' => date('d M Y'),
        'property_list' => $property_list_html
    ]);
    if ($sent) $sent_count++;
}

$update_stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_daily_email_date'");
$update_stmt->execute([$current_date]);

echo "Process completed. Total emails sent: {$sent_count}\n";
?>
