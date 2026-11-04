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

// 3. Fetch all users
$users_stmt = $pdo->query("SELECT name, email, city FROM users WHERE role = 'user' AND status = 'active'");
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$sent_count = 0;

// 4. Loop through users and send email
foreach ($users as $user) {
    $user_city = ucwords(strtolower(trim($user['city'])));
    
    $email_body = "
    <div style='font-family: Arial, sans-serif; background: #f4f7fc; padding: 20px;'>
        <div style='max-width: 650px; margin: 0 auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
            
            <div style='background: #007bff; padding: 20px; text-align: center;'>
                <h2 style='color: #fff; margin: 0;'>FindAuction by Prime Property</h2>
            </div>
            
            <div style='padding: 20px;'>
                <h3 style='color: #0f172a; margin-top: 0;'>Hello {$user['name']},</h3>
                <p style='color: #475569; font-size: 15px;'>Here are the latest auctions recently added. Check them out before they are gone!</p>
    ";

    // Loop through grouped properties
    foreach ($grouped_properties as $city => $props) {
        $email_body .= "<h4 style='color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-top: 25px;'>{$city} ({$user_city})</h4>";
        
        foreach ($props as $prop) {
            $price = number_format($prop['price']);
            $sqft = $prop['sqft'] ? $prop['sqft'] . ' Sq Ft' : 'N/A';
            $auction_date = !empty($prop['auction_date']) ? date('d M Y', strtotime($prop['auction_date'])) : 'N/A';
            
            // 🔥 LINK TO PROPERTY
            $prop_link = "https://primepropertyindia.onrender.com/user_dashboard.php?view=all&property_id=" . $prop['id'];

            $email_body .= "
                <div style='padding: 15px 0; border-bottom: 1px solid #f1f5f9;'>
                    <h3 style='margin: 0 0 8px 0;'>
                        <a href='{$prop_link}' style='color: #2563eb; text-decoration: none;'>{$prop['title']}</a>
                    </h3>
                    <p style='margin: 0 0 5px 0; color: #475569; font-size: 14px;'>
                        <strong>Bank:</strong> {$prop['bank_name']}
                    </p>
                    <p style='margin: 0 0 5px 0; color: #10b981; font-weight: bold; font-size: 16px;'>
                        ₹ {$price}
                    </p>
                    <p style='margin: 0 0 10px 0; color: #64748b; font-size: 13px;'>
                        Auction Date: {$auction_date} | Area: {$sqft}
                    </p>
                    <a href='{$prop_link}' style='color: #007bff; text-decoration: underline; font-size: 14px;'>View More Details...</a>
                </div>
            ";
        }
    }

    $email_body .= "
            </div>
            <div style='background: #f8fafc; padding: 15px; text-align: center; color: #94a3b8; font-size: 12px;'>
                &copy; " . date('Y') . " Prime Property India. All rights reserved.
            </div>
        </div>
    </div>
    ";

    $subject = "New Auction Properties Added - " . date('d M Y');
    
    // 🔥 CHANGED: sendEmailSMTP se sendEmailViaBrevo kiya gaya hai
    if (sendEmailViaBrevo($user['email'], $subject, $email_body)) {
        $sent_count++;
        echo "Email sent to: {$user['email']}\n";
    } else {
        echo "Failed to send to: {$user['email']}\n";
    }
}

echo "Process completed. Total emails sent: {$sent_count}\n";
