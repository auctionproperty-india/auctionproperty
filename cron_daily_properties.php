<?php
// ============================================================
// ⏰ DAILY PROPERTY UPDATE EMAIL (CRON JOB)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

date_default_timezone_set('Asia/Kolkata');

echo "Starting daily email process...\n";

// 1. Fetch properties added in last 24 hours
$stmt = $pdo->prepare("
    SELECT id, title, city, state, price, sqft, auction_date, bank_name, type, image_url 
    FROM properties 
    WHERE created_at >= NOW() - INTERVAL '24 hours'
    ORDER BY city ASC, id DESC
");
$stmt->execute();
$new_properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($new_properties)) {
    die("No new properties added in the last 24 hours. Exiting.\n");
}

// 2. Group properties by City
$grouped_properties = [];
foreach ($new_properties as $prop) {
    $city = ucwords(strtolower(trim($prop['city'])));
    $grouped_properties[$city][] = $prop;
}

// 3. Fetch all active users
$users_stmt = $pdo->query("SELECT name, email, city FROM users WHERE role = 'user' AND status = 'active'");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$sent_count = 0;

// 4. Build the property list HTML once (This will replace the {property_list} placeholder)
$property_list_html = "";
foreach ($grouped_properties as $city => $props) {
    $property_list_html .= "<h3 style='color:#2563eb; border-bottom:1px solid #eee; padding-bottom:5px; margin-top:20px;'>{$city}</h3>";
    
    foreach ($props as $prop) {
        $price = number_format($prop['price']);
        $sqft = $prop['sqft'] ? $prop['sqft'] . ' Sq Ft' : 'N/A';
        $auction_date = !empty($prop['auction_date']) ? date('d M Y', strtotime($prop['auction_date'])) : 'N/A';
        
        // Link to user dashboard
        $prop_link = "https://primepropertyindia.onrender.com/user_dashboard.php?view=all&property_id=" . $prop['id'];
        
        $property_list_html .= "
        <div style='padding: 10px 0; border-bottom: 1px solid #f1f5f9;'>
            <a href='{$prop_link}' style='color: #2563eb; text-decoration: none; font-weight: bold; font-size: 16px;'>{$prop['title']}</a><br>
            <span style='color: #10b981; font-weight: bold; font-size: 15px;'>₹ {$price}</span> | 
            <span style='color: #64748b; font-size: 13px;'>Bank: {$prop['bank_name']}</span><br>
            <span style='color: #64748b; font-size: 12px;'>Auction Date: {$auction_date} | Area: {$sqft}</span><br>
            <a href='{$prop_link}' style='color: #007bff; text-decoration: underline; font-size: 13px; margin-top: 5px; display: inline-block;'>View More Details...</a>
        </div>
        ";
    }
}

// 5. Loop through users and send email using our Central Function
foreach ($users as $user) {
    
    // Pass data to the central function (Template is fetched from DB)
    $sent = sendSystemEmail('daily_props', $user['email'], [
        'name' => $user['name'],
        'date' => date('d M Y'),
        'property_list' => $property_list_html
    ]);
    
    if ($sent) {
        $sent_count++;
        echo "Email sent to: {$user['email']}\n";
    } else {
        echo "Failed to send to: {$user['email']} (Check if email_on_daily_props is ON in Admin Settings)\n";
    }
}

echo "Process completed. Total emails sent: {$sent_count}\n";
