<?php
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

// ---- Permission Helpers (Safe) ----
function getUserPermissions($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT permissions, is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if(!$user) return [];

        if(!empty($user['is_super_admin']) && $user['is_super_admin']) {
            return ['properties'=>true, 'users'=>true, 'packages'=>true, 'subscriptions'=>true, 'settings'=>true];
        }

        $perms = [];
        if(!empty($user['permissions'])) {
            $perms = json_decode($user['permissions'], true);
            if(!is_array($perms)) $perms = [];
        }
        if(empty($perms)) {
            $perms = ['properties'=>true, 'users'=>true, 'packages'=>true, 'subscriptions'=>true, 'settings'=>true];
        }
        return $perms;
    } catch (Exception $e) {
        return ['properties'=>true, 'users'=>true, 'packages'=>true, 'subscriptions'=>true, 'settings'=>true];
    }
}

function hasPermission($permission, $pdo) {
    if(!isset($_SESSION['user_id'])) return false;
    $perms = getUserPermissions($_SESSION['user_id'], $pdo);
    return isset($perms[$permission]) && $perms[$permission] === true;
}

// ---- Social Image Generator (Full working code) ----
function generateSocialCard($property) {
    if (!extension_loaded('gd')) return $property['image_url'] ?? '';
    $font_path = __DIR__ . '/fonts/Inter.ttf';
    $font_exists = file_exists($font_path);
    try {
        $width = 1080; $height = 1080;
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
            $title = strtoupper($property['title'] ?? 'PROPERTY');
            $x = (int)(($width - (strlen($title) * imagefontwidth($f_size))) / 2);
            imagestring($img, $f_size, $x, 180, $title, $gold);
            $bank = $property['bank_name'] ?? 'BANK AUCTION';
            $bx = (int)(($width - (strlen($bank) * imagefontwidth($f_size))) / 2);
            imagestring($img, $f_size, $bx, 300, $bank, $white);
            $price = '₹ ' . indianCurrencyFormat($property['price'] ?? 0);
            $px = (int)(($width - (strlen($price) * imagefontwidth($f_size))) / 2);
            imagestring($img, $f_size, $px, 450, $price, $gold);
            return saveImage($img);
        }
        // Premium layout with TrueType
        $bank = strtoupper($property['bank_name'] ?? 'BANK AUCTION');
        $bank_size = 34;
        $bank_box = imagettfbbox($bank_size, 0, $font_path, $bank);
        $bank_w = ($bank_box[2] - $bank_box[0]) + 60;
        $bank_h = 70;
        $bank_x = (int)(($width - $bank_w) / 2);
        imagefilledrectangle($img, $bank_x, 120, $bank_x + $bank_w, 120 + $bank_h, $gold);
        $txt_x = $bank_x + 30;
        $txt_y = 120 + 48;
        imagettftext($img, $bank_size, 0, $txt_x, $txt_y, $dark_bg, $font_path, $bank);
        $title = strtoupper($property['title'] ?? 'PRIME PROPERTY');
        $title_size = 72;
        $title_box = imagettfbbox($title_size, 0, $font_path, $title);
        $title_width = $title_box[2] - $title_box[0];
        $x = (int)(($width - $title_width) / 2);
        imagettftext($img, $title_size, 0, $x, 280, $white, $font_path, $title);
        $city = strtoupper($property['city'] ?? '');
        if (!empty($city)) {
            $city_size = 38;
            $city_box = imagettfbbox($city_size, 0, $font_path, $city);
            $city_w = $city_box[2] - $city_box[0];
            $x = (int)(($width - $city_w) / 2);
            imagettftext($img, $city_size, 0, $x, 350, $light_gray, $font_path, $city);
        }
        $price_label = "RESERVE PRICE";
        $price_val = "₹ " . indianCurrencyFormat($property['price'] ?? 0);
        $label_size = 32;
        $label_box = imagettfbbox($label_size, 0, $font_path, $price_label);
        $label_w = $label_box[2] - $label_box[0];
        $x = (int)(($width - $label_w) / 2);
        imagettftext($img, $label_size, 0, $x, 480, $light_gray, $font_path, $price_label);
        $val_size = 72;
        $val_box = imagettfbbox($val_size, 0, $font_path, $price_val);
        $val_w = $val_box[2] - $val_box[0];
        $x = (int)(($width - $val_w) / 2);
        imagettftext($img, $val_size, 0, $x, 600, $gold, $font_path, $price_val);
        $per_sqft = "₹ " . indianCurrencyFormat($property['reserve_price_per_sqft'] ?? 0) . " PER SQ FT";
        $ps_size = 26;
        $ps_box = imagettfbbox($ps_size, 0, $font_path, $per_sqft);
        $ps_w = $ps_box[2] - $ps_box[0];
        $x = (int)(($width - $ps_w) / 2);
        imagettftext($img, $ps_size, 0, $x, 660, $white, $font_path, $per_sqft);
        $borrower = "BORROWER: " . ($property['borrower_name'] ?? 'N/A');
        $contact = "CONTACT: " . ($property['contact_number'] ?? 'N/A');
        $info_size = 26;
        imagettftext($img, $info_size, 0, 80, 780, $light_gray, $font_path, $borrower);
        imagettftext($img, $info_size, 0, 80, 830, $light_gray, $font_path, $contact);
        $emd = "EMD: ₹ " . indianCurrencyFormat($property['emd_amount'] ?? 0);
        $possession = "POSSESSION: " . ($property['possession_type'] ?? 'Physical');
        imagettftext($img, $info_size, 0, 680, 780, $light_gray, $font_path, $emd);
        imagettftext($img, $info_size, 0, 680, 830, $light_gray, $font_path, $possession);
        $brand = "🔹 PRIME PROPERTY";
        $brand_size = 28;
        imagettftext($img, $brand_size, 0, 80, 980, $gold, $font_path, $brand);
        $auction = "AUCTION: " . ($property['auction_start_time'] ?? 'N/A') . " - " . ($property['auction_end_time'] ?? 'N/A');
        $auction_size = 22;
        imagettftext($img, $auction_size, 0, 600, 980, $white, $font_path, $auction);
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
    imagepng($img, $path, 9);
    imagedestroy($img);
    return $path;
}
?>
