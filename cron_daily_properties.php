<?php
// ============================================================
// ⏰ DAILY PROPERTY UPDATE EMAIL (CITY, STATE & FAVORITES WISE)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

date_default_timezone_set('Asia/Kolkata');

$force = isset($_GET['force']) && $_GET['force'] === '1';
$test_to = $_GET['to'] ?? null;

$settings_query = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('daily_email_time', 'last_daily_email_date', 'email_on_daily_props')");
$settings = $settings_query->fetchAll(PDO::FETCH_KEY_PAIR);

$scheduled_time = $settings['daily_email_time'] ?? '06:30';
$last_sent_date = $settings['last_daily_email_date'] ?? '';
$is_enabled = ($settings['email_on_daily_props'] ?? '0') === '1';

if (!$is_enabled && !$force) die("Email toggle is OFF. Exiting.\n");

$current_date = date('Y-m-d');
$current_time = date('H:i');
$scheduled_time_clean = date('H:i', strtotime($scheduled_time));

if (!$force) {
    if ($last_sent_date === $current_date) die("Emails already sent today ({$current_date}). Exiting.\n");
    if (strtotime($current_time) < strtotime($scheduled_time_clean)) die("Current time ({$current_time}) is before scheduled time ({$scheduled_time_clean}). Exiting.\n");
}

// 1. पिछले 24 घंटे की नई प्रॉपर्टीज निकालें
$stmt = $pdo->prepare("SELECT id, title, city, state, price, bank_name, type, image_url FROM properties WHERE created_at >= NOW() - INTERVAL '24 hours' ORDER BY state ASC, city ASC, id DESC");
$stmt->execute();
$new_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($new_properties)) {
    if (!$force) $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_daily_email_date'")->execute([$current_date]);
    die("No new properties added in the last 24 hours. Exiting.\n");
}

// 2. सभी एक्टिव यूजर्स और उनकी फेवरेट सिटीज निकालें
$users_stmt = $pdo->query("SELECT id, name, email, city, state FROM users WHERE role = 'user' AND status = 'active'");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$fav_stmt = $pdo->query("SELECT user_id, city FROM user_favorite_cities");
$favorites = [];
while ($row = $fav_stmt->fetch(PDO::FETCH_ASSOC)) {
    $favorites[$row['user_id']][] = strtolower(trim($row['city']));
}

$sent_count = 0;
$brevo_limit_safe = 250; // Limit safe from 300

// 3. हर यूजर के लिए ईमेल प्रोसेस करें
foreach ($users as $user) {
    if ($test_to && strtolower($user['email']) !== strtolower($test_to)) continue;

    $u_state = strtolower(trim($user['state'] ?? ''));
    $u_city = strtolower(trim($user['city'] ?? ''));
    $u_favs = $favorites[$user['id']] ?? [];

    $matched_props = [];

    // 4. चेक करें कि कौन सी नई प्रॉपर्टी इस यूजर के लिए रेलेवेंट है
    foreach ($new_properties as $prop) {
        $p_state = strtolower(trim($prop['state'] ?? ''));
        $p_city = strtolower(trim($prop['city'] ?? ''));

        $is_match = false;

        // कंडीशन A: प्रॉपर्टी यूजर की सिटी में है
        if (!empty($u_city) && $p_city === $u_city) $is_match = true;
        
        // कंडीशन B: प्रॉपर्टी यूजर के स्टेट में है
        if (!empty($u_state) && $p_state === $u_state) $is_match = true;
        
        // कंडीशन C: प्रॉपर्टी यूजर की फेवरेट सिटी में है
        if (in_array($p_city, $u_favs)) $is_match = true;

        if ($is_match || $test_to) {
            $matched_props[] = $prop;
        }
    }

    // अगर कोई भी प्रॉपर्टी मैच नहीं हुई, तो इस यूजर को ईमेल मत भेजो
    if (empty($matched_props) && !$test_to) continue;

    // 5. ईमेल का HTML बनाएं
    $html = "<h3 style='color:#2563eb;'>New Properties for You!</h3>";
    $html .= "<p>Hello <strong>{$user['name']}</strong>, here are the latest properties added in your area and favorite cities:</p>";

    $grouped = [];
    foreach ($matched_props as $p) {
        $c = ucwords(strtolower(trim($p['city'])));
        $grouped[$c][] = $p;
    }

    foreach ($grouped as $city => $props) {
        $is_fav = in_array(strtolower($city), $u_favs) ? " ⭐ (Favorite)" : "";
        $is_my_city = (strtolower($city) === $u_city) ? " (Your City)" : "";
        
        $html .= "<h4 style='color:#475569; margin-top:15px; border-bottom:1px solid #eee; padding-bottom:5px;'>📍 {$city}{$is_my_city}{$is_fav}</h4>";
        
        foreach ($props as $prop) {
            $link = "https://primepropertyindia.onrender.com/user_dashboard.php?view=all&property_id=" . $prop['id'];
            $html .= "
            <div style='padding: 10px 0; border-bottom: 1px solid #f1f5f9;'>
                <a href='{$link}' style='color: #2563eb; text-decoration: none; font-weight: bold; font-size: 16px;'>{$prop['title']}</a><br>
                <span style='color: #10b981; font-weight: bold; font-size: 15px;'>₹ " . number_format($prop['price']) . "</span> | 
                <span style='color: #64748b; font-size: 13px;'>Bank: {$prop['bank_name']}</span><br>
                <a href='{$link}' style='color: #007bff; text-decoration: underline; font-size: 13px;'>View Details</a>
            </div>";
        }
    }

    // 6. ईमेल भेजें
    $sent = sendSystemEmail('daily_props', $user['email'], [
        'name' => $user['name'],
        'date' => date('d M Y'),
        'property_list' => $html
    ]);
    
    if ($sent) $sent_count++;

    if (!$test_to && $sent_count >= $brevo_limit_safe) break; // 250 limit
}

if (!$test_to) {
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_daily_email_date'")->execute([$current_date]);
    echo "Process completed. Total emails sent: {$sent_count}\n";
} else {
    echo ($sent_count > 0) ? "TEST EMAIL SENT OK.\n" : "TEST EMAIL FAILED.\n";
}
?>
