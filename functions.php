<?php
// ---- Currency ----
function indianCurrencyFormat($number) {
    if ($number === null || $number === '') return '0';
    $number = (float) $number;
    $num = (string) floor($number);
    $len = strlen($num);
    if ($len <= 3) return $num;
    $last = substr($num, -3);
    $rest = substr($num, 0, $len - 3);
    $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return $rest . ',' . $last;
}

// ---- Subscription ----
function hasActiveSubscription($pdo, $user_id, $property_id = null) {
    if($property_id) {
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND property_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
        $stmt->execute([$user_id, $property_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
        $stmt->execute([$user_id]);
    }
    return $stmt->rowCount() > 0;
}

function userHasActiveSubscription($pdo, $user_id) {
    if(!$user_id) return false;
    $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->rowCount() > 0;
}

// ---- Permission Helpers ----
function getUserPermissions($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT permissions, is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if(!$user) return [];

        if(!empty($user['is_super_admin']) && $user['is_super_admin']) {
            $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
            $full = [];
            foreach($modules as $m) $full[$m] = ['view' => true, 'edit' => true];
            return $full;
        }

        if(empty($user['permissions'])) {
            $default = [];
            $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
            foreach($modules as $m) $default[$m] = ['view' => false, 'edit' => false];
            return $default;
        }

        $perms = json_decode($user['permissions'], true);
        if(!is_array($perms)) $perms = [];

        $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
        $new_perms = [];
        foreach($modules as $mod) {
            if(isset($perms[$mod])) {
                if(is_array($perms[$mod])) {
                    $new_perms[$mod] = [
                        'view' => isset($perms[$mod]['view']) ? (bool)$perms[$mod]['view'] : false,
                        'edit' => isset($perms[$mod]['edit']) ? (bool)$perms[$mod]['edit'] : false
                    ];
                } else {
                    $val = (bool)$perms[$mod];
                    $new_perms[$mod] = ['view' => $val, 'edit' => $val];
                }
            } else {
                $new_perms[$mod] = ['view' => false, 'edit' => false];
            }
        }
        return $new_perms;
    } catch (Exception $e) {
        return [];
    }
}

function hasViewPermission($permission, $pdo) {
    if(!isset($_SESSION['user_id'])) return false;
    $perms = getUserPermissions($_SESSION['user_id'], $pdo);
    return isset($perms[$permission]['view']) && $perms[$permission]['view'] === true;
}

function hasEditPermission($permission, $pdo) {
    if(!isset($_SESSION['user_id'])) return false;
    $perms = getUserPermissions($_SESSION['user_id'], $pdo);
    return isset($perms[$permission]['edit']) && $perms[$permission]['edit'] === true;
}

// ---- Referral System ----
function generateReferralCode() { return strtoupper(substr(md5(uniqid()), 0, 8)); }
function getReferrerIdByCode($pdo, $code) {
    if(empty($code)) return null;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
    $stmt->execute([$code]);
    $user = $stmt->fetch();
    return $user ? $user['id'] : null;
}
function getReferralLink($user_id) {
    $pdo = $GLOBALS['pdo'];
    $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $code = $stmt->fetchColumn();
    if(!$code) { $new_code = generateReferralCode(); $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$new_code, $user_id]); $code = $new_code; }
    return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $code;
}
function getReferralEarnings($pdo, $user_id, $status = null) {
    $sql = "SELECT e.*, u.name as referred_name, p.name as package_name FROM user_referral_earnings e JOIN users u ON e.referred_user_id = u.id JOIN packages p ON e.package_id = p.id WHERE e.user_id = ?";
    if($status) $sql .= " AND e.status = ?";
    $sql .= " ORDER BY e.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if($status) $stmt->execute([$user_id, $status]);
    else $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
function getReferredUsers($pdo, $user_id) {
    $sql = "SELECT u.id, u.name, u.email, u.created_at as reg_date, (SELECT s.start_date FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active' ORDER BY s.id LIMIT 1) as activation_date FROM users u WHERE u.referred_by = ? ORDER BY u.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
function getReferrerName($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT u.name FROM users u JOIN users r ON u.id = r.referred_by WHERE r.id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 'N/A';
}
function calculateReferralNet($amount, $tds_percent, $admin_charge_percent) {
    $tds = ($amount * $tds_percent) / 100; $admin_charge = ($amount * $admin_charge_percent) / 100; $net = $amount - $tds - $admin_charge;
    return ['tds' => $tds, 'admin_charge' => $admin_charge, 'net' => $net];
}
function changeReferrer($pdo, $user_id, $new_referrer_id) {
    if($user_id == $new_referrer_id) return false;
    $pdo->prepare("UPDATE users SET referred_by = ?, manual_referral_updated = TRUE WHERE id = ?")->execute([$new_referrer_id, $user_id]);
    return true;
}

// ---- Accounting ----
function addAccountEntry($pdo, $type, $amount, $description, $category, $entry_date = null) {
    if($entry_date === null) $entry_date = date('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO account_entries (type, amount, description, category, entry_date) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$type, $amount, $description, $category, $entry_date]);
}
function getAccountBalance($pdo) {
    $income = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'income'")->fetchColumn();
    $expense = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'expense'")->fetchColumn();
    return ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
}
function getAccountEntries($pdo, $limit = 100) {
    $stmt = $pdo->query("SELECT * FROM account_entries ORDER BY entry_date DESC, id DESC LIMIT $limit");
    return $stmt->fetchAll();
}

// ---- Wallet ----
function getUserWalletBalance($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return (float) $stmt->fetchColumn();
}
function creditWallet($pdo, $user_id, $amount, $description, $reference_id = null) {
    if($amount <= 0) return false;
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'credit', ?, ?)");
    return $stmt->execute([$user_id, $amount, $description, $reference_id]);
}
function debitWallet($pdo, $user_id, $amount, $description, $reference_id = null) {
    if($amount <= 0) return false;
    $balance = getUserWalletBalance($pdo, $user_id);
    if($balance < $amount) return false;
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'debit', ?, ?)");
    return $stmt->execute([$user_id, $amount, $description, $reference_id]);
}

// ===== 🔥 4K Social Image Generator =====
function generateSocialCard($property) {
    if (!extension_loaded('gd')) return $property['image_url'] ?? '';
    $font_path = __DIR__ . '/fonts/Inter.ttf';
    $font_exists = file_exists($font_path);

    try {
        // 4K Resolution: 3840 x 2160 (Ultra HD)
        $width = 1920;
        $height = 1080;
        $img = imagecreatetruecolor($width, $height);
        if (!$img) return $property['image_url'] ?? '';

        $dark_blue = imagecolorallocate($img, 15, 23, 42);
        imagefilledrectangle($img, 0, 0, $width, $height, $dark_blue);
        for ($i = 0; $i < $height; $i += 10) {
            $ratio = $i / $height;
            $r = (int)(15 + (30 - 15) * $ratio);
            $g = (int)(23 + (58 - 23) * $ratio);
            $b = (int)(42 + (138 - 42) * $ratio);
            $col = imagecolorallocate($img, $r, $g, $b);
            imagefilledrectangle($img, 0, $i, $width, $i + 10, $col);
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $gold = imagecolorallocate($img, 251, 191, 36);
        $light_gray = imagecolorallocate($img, 200, 210, 220);
        $dark_bg = imagecolorallocate($img, 15, 23, 42);

        if (!$font_exists) {
            $f_size = 5;
            $lines = [
                strtoupper($property['title'] ?? 'PROPERTY'),
                "BANK: " . ($property['bank_name'] ?? 'N/A'),
                "PRICE: ₹ " . indianCurrencyFormat($property['price'] ?? 0),
                "CITY: " . ($property['city'] ?? ''),
                "CONTACT: " . ($property['contact_number'] ?? 'N/A')
            ];
            $y = 200;
            foreach ($lines as $line) {
                $x = (int)(($width - (strlen($line) * imagefontwidth($f_size))) / 2);
                imagestring($img, $f_size, $x, $y, $line, $white);
                $y += 100;
            }
            return saveImage($img);
        }

        $font_regular = $font_path;
        $title = strtoupper($property['title'] ?? 'PRIME PROPERTY');
        $title_size = 120;
        $title_box = imagettfbbox($title_size, 0, $font_regular, $title);
        $title_width = $title_box[2] - $title_box[0];
        $x = (int)(($width - $title_width) / 2);
        imagettftext($img, $title_size, 0, $x, 250, $gold, $font_regular, $title);

        $bank = strtoupper($property['bank_name'] ?? 'BANK AUCTION');
        $bank_size = 70;
        $bank_box = imagettfbbox($bank_size, 0, $font_regular, $bank);
        $bank_w = ($bank_box[2] - $bank_box[0]) + 120;
        $bank_h = 100;
        $bank_x = (int)(($width - $bank_w) / 2);
        imagefilledrectangle($img, $bank_x, 320, $bank_x + $bank_w, 320 + $bank_h, $gold);
        $txt_x = $bank_x + 60;
        $txt_y = 320 + 80;
        imagettftext($img, $bank_size, 0, $txt_x, $txt_y, $dark_bg, $font_regular, $bank);

        $type = $property['type'] ?? 'N/A';
        $possession = $property['possession_type'] ?? 'N/A';
        $info_line = "TYPE: $type   |   POSSESSION: $possession";
        $info_size = 50;
        $info_box = imagettfbbox($info_size, 0, $font_regular, $info_line);
        $info_w = $info_box[2] - $info_box[0];
        $x = (int)(($width - $info_w) / 2);
        imagettftext($img, $info_size, 0, $x, 520, $white, $font_regular, $info_line);

        $city = $property['city'] ?? '';
        $locality = $property['locality'] ?? '';
        $state = $property['state'] ?? '';
        $loc_str = "$city, $locality, $state";
        $loc_size = 44;
        $loc_box = imagettfbbox($loc_size, 0, $font_regular, $loc_str);
        $loc_w = $loc_box[2] - $loc_box[0];
        $x = (int)(($width - $loc_w) / 2);
        imagettftext($img, $loc_size, 0, $x, 620, $light_gray, $font_regular, $loc_str);

        $address = $property['location'] ?? '';
        $addr_size = 40;
        $addr_box = imagettfbbox($addr_size, 0, $font_regular, $address);
        $addr_w = $addr_box[2] - $addr_box[0];
        if ($addr_w > $width - 200) {
            $address = substr($address, 0, 80) . '...';
            $addr_box = imagettfbbox($addr_size, 0, $font_regular, $address);
            $addr_w = $addr_box[2] - $addr_box[0];
        }
        $x = (int)(($width - $addr_w) / 2);
        imagettftext($img, $addr_size, 0, $x, 720, $white, $font_regular, $address);

        $price = "RESERVE PRICE: ₹ " . indianCurrencyFormat($property['price'] ?? 0);
        $emd = "EMD: ₹ " . indianCurrencyFormat($property['emd_amount'] ?? 0);
        $bid = "BID INCREMENT: ₹ " . indianCurrencyFormat($property['bid_increment'] ?? 0);
        $area = "AREA: " . ($property['sqft'] ?? 0) . " Sq Ft";
        $items = [$price, $emd, $bid, $area];
        $cols = 4;
        $box_w = 700;
        $box_h = 150;
        $gap = 40;
        $start_x = (int)(($width - ($box_w * $cols + $gap * ($cols - 1))) / 2);
        $box_y = 800;
        foreach ($items as $i => $text) {
            $x_pos = $start_x + ($i * ($box_w + $gap));
            $box_color = imagecolorallocate($img, 30, 50, 80);
            imagefilledrectangle($img, $x_pos, $box_y, $x_pos + $box_w, $box_y + $box_h, $box_color);
            imagerectangle($img, $x_pos, $box_y, $x_pos + $box_w, $box_y + $box_h, $gold);
            $parts = explode(':', $text);
            $label = $parts[0] . ':';
            $value = isset($parts[1]) ? trim($parts[1]) : '';
            $label_size = 32;
            $value_size = 44;
            $label_box = imagettfbbox($label_size, 0, $font_regular, $label);
            $label_w = $label_box[2] - $label_box[0];
            $lx = (int)($x_pos + ($box_w - $label_w) / 2);
            imagettftext($img, $label_size, 0, $lx, $box_y + 50, $light_gray, $font_regular, $label);
            $value_box = imagettfbbox($value_size, 0, $font_regular, $value);
            $value_w = $value_box[2] - $value_box[0];
            $vx = (int)($x_pos + ($box_w - $value_w) / 2);
            imagettftext($img, $value_size, 0, $vx, $box_y + 120, $white, $font_regular, $value);
        }

        $start = $property['auction_start_time'] ?? 'N/A';
        $end = $property['auction_end_time'] ?? 'N/A';
        $deadline = $property['emd_deadline'] ?? 'N/A';
        $auction_date = $property['auction_date'] ?? '';
        if (!empty($auction_date)) {
            $auction_date = date('d M Y', strtotime($auction_date));
        }
        $date_line = "START: $start   |   END: $end   |   EMD DEADLINE: $deadline   |   AUCTION DATE: $auction_date";
        $date_size = 40;
        $date_box = imagettfbbox($date_size, 0, $font_regular, $date_line);
        $date_w = $date_box[2] - $date_box[0];
        if ($date_w > $width - 100) {
            $date_line = "START: $start   |   END: $end";
            $date_box = imagettfbbox($date_size, 0, $font_regular, $date_line);
            $date_w = $date_box[2] - $date_box[0];
        }
        $x = (int)(($width - $date_w) / 2);
        imagettftext($img, $date_size, 0, $x, 1050, $white, $font_regular, $date_line);

        $borrower = "BORROWER: " . ($property['borrower_name'] ?? 'N/A');
        $contact = "CONTACT: " . ($property['contact_number'] ?? 'N/A');
        $info_size2 = 44;
        $borrower_box = imagettfbbox($info_size2, 0, $font_regular, $borrower);
        $borrower_w = $borrower_box[2] - $borrower_box[0];
        $x = (int)(($width - $borrower_w) / 2);
        imagettftext($img, $info_size2, 0, $x, 1200, $gold, $font_regular, $borrower);
        $contact_box = imagettfbbox($info_size2, 0, $font_regular, $contact);
        $contact_w = $contact_box[2] - $contact_box[0];
        $x = (int)(($width - $contact_w) / 2);
        imagettftext($img, $info_size2, 0, $x, 1280, $white, $font_regular, $contact);

        $brand = "🔹 PRIME PROPERTY";
        $brand_size = 50;
        $brand_box = imagettfbbox($brand_size, 0, $font_regular, $brand);
        $brand_w = $brand_box[2] - $brand_box[0];
        $x = (int)(($width - $brand_w) / 2);
        imagettftext($img, $brand_size, 0, $x, 1900, $gold, $font_regular, $brand);

        return saveImage($img);

    } catch (Exception $e) {
        return $property['image_url'] ?? '';
    }
}

function saveImage($img) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = 'social_' . time() . '_' . bin2hex(random_bytes(6)) . '.png';
    $path = $upload_dir . $filename;
    imagepng($img, $path, 0);
    imagedestroy($img);
    return $path;
}

// ---- Email Functions with Conditional PHPMailer ----
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/phpmailer/PHPMailer.php')) {
    require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
    require_once __DIR__ . '/vendor/phpmailer/Exception.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmailSMTP($to, $subject, $body, $from_email = null, $from_name = null) {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        if (!$from_email) $from_email = getenv('SMTP_FROM_EMAIL') ?: 'noreply@yourdomain.com';
        if (!$from_name) $from_name = getenv('SMTP_FROM_NAME') ?: 'Prime Property';
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.sendgrid.net';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('SMTP_USERNAME');
            $mail->Password   = getenv('SMTP_PASSWORD');
            $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
            $mail->Port       = getenv('SMTP_PORT') ?: 587;
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("SMTP send failed: " . $mail->ErrorInfo);
            return sendMailFallback($to, $subject, $body, $from_email, $from_name);
        }
    } else {
        return sendMailFallback($to, $subject, $body, $from_email, $from_name);
    }
}

function sendMailFallback($to, $subject, $body, $from_email = null, $from_name = null) {
    if (!$from_email) $from_email = 'noreply@' . $_SERVER['HTTP_HOST'];
    if (!$from_name) $from_name = 'Prime Property';
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $from_name <$from_email>" . "\r\n";
    return mail($to, $subject, $body, $headers);
}

function sendNewPropertyNotification($pdo, $property_id, $source = 'auction') {
    if ($source == 'auction') {
        $stmt = $pdo->prepare("SELECT title, price, city, id FROM properties WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT title, price, city, id FROM user_properties WHERE id = ?");
    }
    $stmt->execute([$property_id]);
    $prop = $stmt->fetch();
    if (!$prop) return false;
    $users = $pdo->query("SELECT email FROM users WHERE status = 'active' AND email IS NOT NULL AND email != ''")->fetchAll();
    if (empty($users)) return false;
    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $detail_url = $base_url . '/property_detail.php?id=' . $property_id . '&source=' . $source;
    $subject = "🏠 New Property Added: " . $prop['title'];
    $message = "<html><body style='font-family: Arial, sans-serif;'>";
    $message .= "<h2>New Property Alert!</h2>";
    $message .= "<p>A new property has been added to our platform.</p>";
    $message .= "<p><strong>Title:</strong> " . htmlspecialchars($prop['title']) . "</p>";
    $message .= "<p><strong>Price:</strong> ₹ " . indianCurrencyFormat($prop['price']) . "</p>";
    $message .= "<p><strong>City:</strong> " . htmlspecialchars($prop['city']) . "</p>";
    $message .= "<p><a href='$detail_url' style='display:inline-block; background:#2563eb; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>View Property</a></p>";
    $message .= "<p style='margin-top:20px; font-size:0.8rem; color:#666;'>You are receiving this email because you are registered on our platform.</p>";
    $message .= "</body></html>";
    foreach ($users as $user) {
        sendEmailSMTP($user['email'], $subject, $message);
    }
    return true;
}

// ===== 🔄 DAILY SPIN SYSTEM (Updated with coin settings) =====
function getCurrentSlot() {
    $hour = (int)date('H');
    if ($hour >= 0 && $hour < 8) return 1;
    if ($hour >= 8 && $hour < 14) return 2;
    return 3;
}

function getSlotTimeRange($slot) {
    switch($slot) {
        case 1: return '12 AM – 8 AM';
        case 2: return '8 AM – 2 PM';
        case 3: return '2 PM – 12 AM';
        default: return 'Unknown';
    }
}

function getUserSpinData($pdo, $user_id, $slot = null) {
    if ($slot === null) $slot = getCurrentSlot();
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("/* force new plan */ SELECT * FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$user_id, $today, $slot]);
    $data = $stmt->fetch();
    if (!$data) {
        $stmt = $pdo->prepare("INSERT INTO user_spins (user_id, slot_date, slot_number, spins_used, reward_given, coins_earned) VALUES (?, ?, ?, 0, FALSE, 0)");
        $stmt->execute([$user_id, $today, $slot]);
        return ['spins_used' => 0, 'reward_given' => false, 'can_spin' => true, 'coins_earned' => 0];
    }
    return [
        'spins_used' => $data['spins_used'],
        'reward_given' => (bool)$data['reward_given'],
        'can_spin' => ($data['spins_used'] < 5),
        'coins_earned' => (int)$data['coins_earned'],
        'id' => $data['id']
    ];
}

function getRandomLowPriceProperty($pdo, $exclude_ids = [], $type = null) {
    $sql = "SELECT id, title, price, city, image_url, bank_name, type FROM properties WHERE status = 'available'";
    if ($type) {
        if ($type == 'car') {
            $sql .= " AND (type ILIKE '%Car%' OR type ILIKE '%Vehicle%')";
        } else {
            $sql .= " AND type NOT ILIKE '%Car%' AND type NOT ILIKE '%Vehicle%'";
        }
    }
    if (!empty($exclude_ids)) {
        $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
        $sql .= " AND id NOT IN ($placeholders)";
    }
    $sql .= " ORDER BY price ASC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    if (!empty($exclude_ids)) {
        $stmt->execute($exclude_ids);
    } else {
        $stmt->execute();
    }
    $props = $stmt->fetchAll();
    if (empty($props)) {
        // fallback: get any without type filter
        $sql = "SELECT id, title, price, city, image_url, bank_name, type FROM properties WHERE status = 'available'";
        if (!empty($exclude_ids)) {
            $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
            $sql .= " AND id NOT IN ($placeholders)";
        }
        $sql .= " ORDER BY price ASC LIMIT 10";
        $stmt = $pdo->prepare($sql);
        if (!empty($exclude_ids)) $stmt->execute($exclude_ids);
        else $stmt->execute();
        $props = $stmt->fetchAll();
        if (empty($props)) return null;
    }
    $available = array_filter($props, function($p) use ($exclude_ids) {
        return !in_array($p['id'], $exclude_ids);
    });
    if (empty($available)) {
        $available = $props;
    }
    return $available[array_rand($available)];
}

function getSpinCoinSettings($pdo) {
    $min = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='spin_min_coins'")->fetchColumn();
    $max = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='spin_max_coins'")->fetchColumn();
    return [
        'min' => (float)$min ?: 3,
        'max' => (float)$max ?: 7
    ];
}

function performSpin($pdo, $user_id) {
    $today = date('Y-m-d');
    $slot = getCurrentSlot();
    $stmt = $pdo->prepare("/* force new plan */ SELECT spins_used, reward_given, coins_earned FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$user_id, $today, $slot]);
    $data = $stmt->fetch();
    if (!$data) {
        $stmt = $pdo->prepare("INSERT INTO user_spins (user_id, slot_date, slot_number, spins_used, reward_given, coins_earned) VALUES (?, ?, ?, 0, FALSE, 0)");
        $stmt->execute([$user_id, $today, $slot]);
        $spins_used = 0;
        $coins_earned = 0;
        $reward_given = false;
    } else {
        $spins_used = $data['spins_used'];
        $coins_earned = $data['coins_earned'];
        $reward_given = $data['reward_given'];
    }
    if ($spins_used >= 5) {
        return ['success' => false, 'message' => 'You have already used all spins for this slot.'];
    }

    // Get coin settings
    $coin_settings = getSpinCoinSettings($pdo);
    $min_coin = $coin_settings['min'];
    $max_coin = $coin_settings['max'];
    $cap_per_slot = 22; // fixed cap

    // Determine random coin (between min and max)
    $coin_amount = rand((int)$min_coin, (int)$max_coin);
    // Ensure total doesn't exceed cap
    if ($coins_earned + $coin_amount > $cap_per_slot) {
        $coin_amount = $cap_per_slot - $coins_earned;
        if ($coin_amount < 1) $coin_amount = 0;
    }

    $new_spins = $spins_used + 1;
    $new_coins_earned = $coins_earned + $coin_amount;

    // Update spins_used, coins_earned
    $stmt = $pdo->prepare("UPDATE user_spins SET spins_used = ?, coins_earned = ?, last_spin_at = CURRENT_TIMESTAMP WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$new_spins, $new_coins_earned, $user_id, $today, $slot]);

    // Credit user's total coins
    if ($coin_amount > 0) {
        $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$coin_amount, $user_id]);
    }

    // Check if 5th spin (reward milestone)
    $is_reward = ($new_spins == 5);
    if ($is_reward) {
        $pdo->prepare("UPDATE user_spins SET reward_given = TRUE WHERE user_id = ? AND slot_date = ? AND slot_number = ?")->execute([$user_id, $today, $slot]);
    }

    // For spins 1-4, show a property (car/property); for 5th, we can also show property (optional)
    $show_property = true; // always show property
    if ($show_property) {
        // Manage session for shown properties
        if (!isset($_SESSION['shown_properties'])) {
            $_SESSION['shown_properties'] = [];
        }
        $exclude = $_SESSION['shown_properties'];
        // Alternate between car and property
        $type = ($new_spins % 2 == 1) ? 'car' : 'property';
        $prop = getRandomLowPriceProperty($pdo, $exclude, $type);
        if (!$prop) {
            $prop = getRandomLowPriceProperty($pdo, $exclude);
        }
        if ($prop) {
            $_SESSION['shown_properties'][] = $prop['id'];
            if (count($_SESSION['shown_properties']) > 10) array_shift($_SESSION['shown_properties']);
            $response = [
                'success' => true,
                'message' => ($type == 'car') ? "🚗 +$coin_amount coins! Check out this car!" : "🏠 +$coin_amount coins! Check out this property!",
                'spins_used' => $new_spins,
                'show_property' => true,
                'property' => $prop,
                'coins' => $coin_amount,
                'is_reward' => $is_reward,
                'total_coins_earned' => $new_coins_earned,
                'type' => $type
            ];
        } else {
            $response = [
                'success' => true,
                'message' => "+$coin_amount coins! No property available.",
                'spins_used' => $new_spins,
                'show_property' => false,
                'coins' => $coin_amount,
                'is_reward' => $is_reward,
                'total_coins_earned' => $new_coins_earned
            ];
        }
    } else {
        // No property (if we ever want to skip)
        $response = [
            'success' => true,
            'message' => "+$coin_amount coins!",
            'spins_used' => $new_spins,
            'show_property' => false,
            'coins' => $coin_amount,
            'is_reward' => $is_reward,
            'total_coins_earned' => $new_coins_earned
        ];
    }

    return $response;
}

function getSlotStatus($pdo, $user_id, $slot) {
    $data = getUserSpinData($pdo, $user_id, $slot);
    $spins = $data['spins_used'];
    $coins = $data['coins_earned'];
    $is_current = ($slot == getCurrentSlot());
    $is_past = (!$is_current && ($slot < getCurrentSlot()));
    $is_future = (!$is_current && ($slot > getCurrentSlot()));
    $status = [];
    $status['slot'] = $slot;
    $status['time_range'] = getSlotTimeRange($slot);
    $status['spins_used'] = $spins;
    $status['coins_earned'] = $coins;
    $status['is_current'] = $is_current;
    $status['is_past'] = $is_past;
    $status['is_future'] = $is_future;
    $status['can_spin'] = $data['can_spin'] && $is_current;
    if ($is_past) {
        if ($spins > 0) {
            $status['message'] = "✅ Spins: $spins/5 | Coins: $coins";
            $status['label'] = 'claimed';
        } else {
            $status['message'] = "❌ Missed Reward";
            $status['label'] = 'missed';
        }
    } elseif ($is_current) {
        if ($spins == 0) {
            $status['message'] = "⏳ You haven't spun yet!";
            $status['label'] = 'ready';
        } elseif ($spins < 5) {
            $status['message'] = "🔄 $spins/5 spins used | $coins coins earned";
            $status['label'] = 'progress';
        } else {
            $status['message'] = "✅ Completed! Total coins: $coins";
            $status['label'] = 'done';
        }
    } else {
        $status['message'] = "⏳ Upcoming Slot";
        $status['label'] = 'upcoming';
    }
    return $status;
}

// ---- Activity Log ----
function logActivity($pdo, $user_id, $activity_type, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, activity_type, details, ip_address) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$user_id, $activity_type, $details, $ip]);
}
?>
