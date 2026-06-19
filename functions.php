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

function generateSocialCard($property) {
    if (!extension_loaded('gd')) {
        error_log("GD extension missing");
        return $property['image_url'] ?? '';
    }

    $font_path = __DIR__ . '/fonts/Inter.ttf';
    $font_exists = file_exists($font_path);

    try {
        $width = 1080;
        $height = 1080;
        $img = imagecreatetruecolor($width, $height);
        if (!$img) return $property['image_url'] ?? '';

        // Background Gradient
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

        // अगर Font नहीं है तो Fallback
        if (!$font_exists) {
            $f_size = 5;
            $title = strtoupper($property['title'] ?? 'PROPERTY');
            $x = (int)(($width - (strlen($title) * imagefontwidth($f_size))) / 2);
            imagestring($img, $f_size, $x, 180, $title, $gold);
            return saveImage($img);
        }

        // ---------- Premium Layout with TrueType Font (BIG TEXT) ----------
        // 1. Main Title (Biggest)
        $title = strtoupper($property['title'] ?? 'PRIME PROPERTY');
        $title_size = 80;
        $title_box = imagettfbbox($title_size, 0, $font_path, $title);
        $title_width = $title_box[2] - $title_box[0];
        $x = (int)(($width - $title_width) / 2);
        imagettftext($img, $title_size, 0, $x, 200, $gold, $font_path, $title);

        // 2. Subtitle (Locality / City) - Big
        $sub = strtoupper($property['locality'] ?? $property['city'] ?? 'PREMIUM LOCATION');
        $sub_size = 48;
        $sub_box = imagettfbbox($sub_size, 0, $font_path, $sub);
        $sub_w = $sub_box[2] - $sub_box[0];
        $x = (int)(($width - $sub_w) / 2);
        imagettftext($img, $sub_size, 0, $x, 280, $white, $font_path, $sub);

        // 3. Property Type Badge - Big
        $type = $property['type'] ?? 'PROPERTY';
        $type_str = strtoupper($type);
        $badge_y = 340;
        $badge_size = 40;
        $badge_box = imagettfbbox($badge_size, 0, $font_path, $type_str);
        $badge_w = ($badge_box[2] - $badge_box[0]) + 80;
        $badge_h = 80;
        $badge_x = (int)(($width - $badge_w) / 2);
        imagefilledrectangle($img, $badge_x, $badge_y - 50, $badge_x + $badge_w, $badge_y + 40, $gold);
        $txt_x = $badge_x + 40;
        $txt_y = $badge_y + 10;
        imagettftext($img, $badge_size, 0, $txt_x, $txt_y, $dark_bg, $font_path, $type_str);

        // 4. Four Detail Boxes - Big Text
        $box_y = 460;
        $box_width = 240;
        $box_height = 130;
        $gap = 30;
        $start_x = (int)(($width - ($box_width * 4 + $gap * 3)) / 2);
        $details = [
            ['label' => 'AREA', 'value' => ($property['sqft'] ?? 0) . ' Sq Ft'],
            ['label' => 'CITY', 'value' => $property['city'] ?? 'N/A'],
            ['label' => 'RESERVE PRICE', 'value' => '₹ ' . indianCurrencyFormat($property['price'] ?? 0)],
            ['label' => 'PER SQ FT', 'value' => '₹ ' . indianCurrencyFormat($property['reserve_price_per_sqft'] ?? 0)],
        ];

        foreach ($details as $index => $item) {
            $x_pos = $start_x + ($index * ($box_width + $gap));
            $box_color = imagecolorallocate($img, 30, 50, 80);
            imagefilledrectangle($img, $x_pos, $box_y, $x_pos + $box_width, $box_y + $box_height, $box_color);
            imagerectangle($img, $x_pos, $box_y, $x_pos + $box_width, $box_y + $box_height, $gold);

            // Label - Big
            $label_size = 24;
            $label_box = imagettfbbox($label_size, 0, $font_path, $item['label']);
            $label_w = $label_box[2] - $label_box[0];
            $lx = (int)($x_pos + ($box_width - $label_w) / 2);
            imagettftext($img, $label_size, 0, $lx, $box_y + 35, $light_gray, $font_path, $item['label']);

            // Value - Even Bigger
            $val_size = 36;
            $val_box = imagettfbbox($val_size, 0, $font_path, $item['value']);
            $val_w = $val_box[2] - $val_box[0];
            $vx = (int)($x_pos + ($box_width - $val_w) / 2);
            imagettftext($img, $val_size, 0, $vx, $box_y + 100, $white, $font_path, $item['value']);
        }

        // 5. Bottom Details (Bank, Borrower, Auction) - Big
        $bottom_y = 700;
        $line_height = 60;
        $left_col_x = 80;
        $right_col_x = 580;
        $text_size = 30;

        // Bank
        imagettftext($img, $text_size, 0, $left_col_x, $bottom_y, $gold, $font_path, "🏦 BANK");
        imagettftext($img, $text_size, 0, $left_col_x + 180, $bottom_y, $white, $font_path, $property['bank_name'] ?? 'N/A');

        // Borrower
        imagettftext($img, $text_size, 0, $left_col_x, $bottom_y + $line_height, $gold, $font_path, "👤 BORROWER");
        imagettftext($img, $text_size, 0, $left_col_x + 180, $bottom_y + $line_height, $white, $font_path, $property['borrower_name'] ?? 'N/A');

        // Auction Start
        imagettftext($img, $text_size, 0, $right_col_x, $bottom_y, $gold, $font_path, "📅 START");
        imagettftext($img, $text_size, 0, $right_col_x + 160, $bottom_y, $white, $font_path, $property['auction_start_time'] ?? 'N/A');

        // Auction End
        imagettftext($img, $text_size, 0, $right_col_x, $bottom_y + $line_height, $gold, $font_path, "⏳ END");
        imagettftext($img, $text_size, 0, $right_col_x + 160, $bottom_y + $line_height, $white, $font_path, $property['auction_end_time'] ?? 'N/A');

        // 6. Footer Contact & Brand - Big
        $contact = "📞 CONTACT: " . ($property['contact_number'] ?? 'N/A');
        $brand = "🔹 PRIME PROPERTY";
        $foot_size = 32;
        imagettftext($img, $foot_size, 0, 80, 1000, $gold, $font_path, $contact);
        imagettftext($img, $foot_size, 0, 700, 1000, $light_gray, $font_path, $brand);

        return saveImage($img);

    } catch (Exception $e) {
        error_log("generateSocialCard error: " . $e->getMessage());
        return $property['image_url'] ?? '';
    }
}

// Helper function to save image
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
