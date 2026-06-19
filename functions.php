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

// ✅ नया Function: Social Media Style Image Generate करेगा
function generateSocialCard($property) {
    $font_path = __DIR__ . '/fonts/Inter.ttf';
    if (!file_exists($font_path)) {
        // अगर Font नहीं है तो Error Log करें और पुरानी Image लौटा दें
        error_log("Font not found at: " . $font_path);
        return $property['image_url'] ?? '';
    }

    // Image Dimensions (Instagram Post Size 1080x1080)
    $width = 1080;
    $height = 1080;
    $img = imagecreatetruecolor($width, $height);

    // 1. Background Gradient (Dark Premium Theme)
    $gradient = imagecreatetruecolor($width, $height);
    $dark_blue = imagecolorallocate($gradient, 15, 23, 42);
    $light_blue = imagecolorallocate($gradient, 30, 58, 138);
    imagefilledrectangle($gradient, 0, 0, $width, $height, $dark_blue);
    // Top to Bottom Gradient effect (simple horizontal blocks)
    for ($i = 0; $i < $height; $i++) {
        $ratio = $i / $height;
        $r = 15 + ($light_blue - $dark_blue) * $ratio; // simplified, better to use imagecopymerge or custom loop
    }
    // Better: Use imagefilledrectangle with gradient color blending
    for ($i = 0; $i < $height; $i += 10) {
        $ratio = $i / $height;
        $r = 15 + (30 - 15) * $ratio;
        $g = 23 + (58 - 23) * $ratio;
        $b = 42 + (138 - 42) * $ratio;
        $col = imagecolorallocate($gradient, (int)$r, (int)$g, (int)$b);
        imagefilledrectangle($gradient, 0, $i, $width, $i+10, $col);
    }
    imagecopy($img, $gradient, 0, 0, 0, 0, $width, $height);
    imagedestroy($gradient);

    // Colors
    $white = imagecolorallocate($img, 255, 255, 255);
    $gold = imagecolorallocate($img, 251, 191, 36);
    $light_gray = imagecolorallocate($img, 200, 210, 220);
    $dark_bg = imagecolorallocate($img, 15, 23, 42);
    $text_dark = imagecolorallocate($img, 30, 41, 59);

    // 2. Title - "9 PITHAMPUR" (Property Title)
    $title = strtoupper($property['title'] ?? 'PRIME PROPERTY');
    $title_size = 60;
    $title_box = imagettfbbox($title_size, 0, $font_path, $title);
    $title_width = $title_box[2] - $title_box[0];
    $x = ($width - $title_width) / 2;
    imagettftext($img, $title_size, 0, $x, 180, $gold, $font_path, $title);

    // 3. Subtitle - "NEAR HOTEL MANNAL" (Location)
    $subtitle = $property['locality'] ?? $property['city'] ?? 'PREMIUM LOCATION';
    $sub_size = 30;
    $sub_box = imagettfbbox($sub_size, 0, $font_path, $subtitle);
    $sub_w = $sub_box[2] - $sub_box[0];
    imagettftext($img, $sub_size, 0, ($width - $sub_w)/2, 240, $light_gray, $font_path, $subtitle);

    // 4. Property Type Badge - "FLAT / SAI SATYAM RESIDENCE"
    $type = $property['type'] ?? 'PROPERTY';
    $type_str = strtoupper($type) . ' / ' . (strlen($property['title']) > 15 ? substr($property['title'], 0, 15) : $property['title']);
    $badge_y = 320;
    // Draw Badge Background
    $badge_padding = 30;
    $badge_box = imagettfbbox(28, 0, $font_path, $type_str);
    $badge_w = ($badge_box[2] - $badge_box[0]) + ($badge_padding * 2);
    $badge_h = 60;
    $badge_x = ($width - $badge_w) / 2;
    imagefilledrectangle($img, $badge_x, $badge_y - 40, $badge_x + $badge_w, $badge_y + 30, $gold);
    imagettftext($img, 28, 0, $badge_x + $badge_padding, $badge_y + 10, $dark_bg, $font_path, $type_str);

    // 5. Details Boxes (Area, Sqft, Price, Per Sqft)
    $box_y = 430;
    $box_width = 220;
    $box_height = 100;
    $gap = 30;
    $start_x = ($width - ($box_width * 4 + $gap * 3)) / 2;

    $details = [
        ['label' => 'AREA', 'value' => $property['city'] ?? 'INDORE'],
        ['label' => 'SQ.FT.', 'value' => ($property['sqft'] ?? 0) . ' Sq Ft'],
        ['label' => 'RESERVED PRICE', 'value' => '₹ ' . indianCurrencyFormat($property['price'] ?? 0)],
        ['label' => 'PRICE (PER SQ FT)', 'value' => '₹ ' . indianCurrencyFormat($property['reserve_price_per_sqft'] ?? 0)],
    ];

    foreach ($details as $index => $item) {
        $x_pos = $start_x + ($index * ($box_width + $gap));
        // Box Background (Light with opacity)
        $box_color = imagecolorallocate($img, 30, 50, 80);
        imagefilledrectangle($img, $x_pos, $box_y, $x_pos + $box_width, $box_y + $box_height, $box_color);
        imagerectangle($img, $x_pos, $box_y, $x_pos + $box_width, $box_y + $box_height, $gold);
        
        // Label
        $label_size = 14;
        $label_box = imagettfbbox($label_size, 0, $font_path, $item['label']);
        $label_w = $label_box[2] - $label_box[0];
        imagettftext($img, $label_size, 0, $x_pos + ($box_width - $label_w)/2, $box_y + 30, $light_gray, $font_path, $item['label']);
        
        // Value
        $val_size = 18;
        $val_box = imagettfbbox($val_size, 0, $font_path, $item['value']);
        $val_w = $val_box[2] - $val_box[0];
        imagettftext($img, $val_size, 0, $x_pos + ($box_width - $val_w)/2, $box_y + 75, $white, $font_path, $item['value']);
    }

    // 6. Bottom Details (Bank, Borrower, Auction Dates)
    $bottom_y = 680;
    $line_height = 40;
    $left_col_x = 100;
    $right_col_x = 600;
    $bottom_text_size = 22;

    // Bank
    $bank_label = "🏦 BANK";
    $bank_val = $property['bank_name'] ?? 'N/A';
    imagettftext($img, $bottom_text_size, 0, $left_col_x, $bottom_y, $gold, $font_path, $bank_label);
    imagettftext($img, $bottom_text_size, 0, $left_col_x + 80, $bottom_y, $white, $font_path, $bank_val);

    // Borrower
    $borrower_label = "👤 BORROWER";
    $borrower_val = $property['borrower_name'] ?? 'N/A';
    imagettftext($img, $bottom_text_size, 0, $left_col_x, $bottom_y + $line_height, $gold, $font_path, $borrower_label);
    imagettftext($img, $bottom_text_size, 0, $left_col_x + 80, $bottom_y + $line_height, $white, $font_path, $borrower_val);

    // Auction Start
    $start_label = "📅 AUCTION START";
    $start_val = $property['auction_start_time'] ?? 'N/A';
    imagettftext($img, $bottom_text_size, 0, $right_col_x, $bottom_y, $gold, $font_path, $start_label);
    imagettftext($img, $bottom_text_size, 0, $right_col_x + 80, $bottom_y, $white, $font_path, $start_val);

    // Auction End
    $end_label = "⏳ AUCTION END";
    $end_val = $property['auction_end_time'] ?? 'N/A';
    imagettftext($img, $bottom_text_size, 0, $right_col_x, $bottom_y + $line_height, $gold, $font_path, $end_label);
    imagettftext($img, $bottom_text_size, 0, $right_col_x + 80, $bottom_y + $line_height, $white, $font_path, $end_val);

    // 7. Footer - Contact & Brand
    $contact = "📞 CONTACT: " . ($property['contact_number'] ?? 'N/A');
    $brand = "🔹 PRIME PROPERTY";
    imagettftext($img, 24, 0, 100, 980, $gold, $font_path, $contact);
    imagettftext($img, 24, 0, 700, 980, $light_gray, $font_path, $brand);

    // 8. Save Image
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = 'social_' . time() . '_' . bin2hex(random_bytes(6)) . '.png';
    $path = $upload_dir . $filename;
    imagepng($img, $path, 9);
    imagedestroy($img);

    return $path;
}
?>
