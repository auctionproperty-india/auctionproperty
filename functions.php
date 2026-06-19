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

        if (!$font_exists) {
            // Fallback simple image
            $f_size = 5;
            $title = strtoupper($property['title'] ?? 'PROPERTY');
            $x = (int)(($width - (strlen($title) * imagefontwidth($f_size))) / 2);
            imagestring($img, $f_size, $x, 180, $title, $gold);
            return saveImage($img);
        }

        // 1. Title (Biggest)
        $title = strtoupper($property['title'] ?? 'PRIME PROPERTY');
        $title_size = 74;
        $title_box = imagettfbbox($title_size, 0, $font_path, $title);
        $title_width = $title_box[2] - $title_box[0];
        $x = (int)(($width - $title_width) / 2);
        imagettftext($img, $title_size, 0, $x, 220, $gold, $font_path, $title);

        // 2. City (Large)
        $city = strtoupper($property['city'] ?? 'PREMIUM LOCATION');
        $city_size = 42;
        $city_box = imagettfbbox($city_size, 0, $font_path, $city);
        $city_w = $city_box[2] - $city_box[0];
        $x = (int)(($width - $city_w) / 2);
        imagettftext($img, $city_size, 0, $x, 310, $white, $font_path, $city);

        // 3. Bank Name Badge
        $bank = $property['bank_name'] ?? 'BANK AUCTION';
        $bank_size = 30;
        $bank_box = imagettfbbox($bank_size, 0, $font_path, $bank);
        $bank_w = ($bank_box[2] - $bank_box[0]) + 60;
        $bank_h = 60;
        $bank_x = (int)(($width - $bank_w) / 2);
        imagefilledrectangle($img, $bank_x, 370, $bank_x + $bank_w, 370 + $bank_h, $gold);
        $txt_x = $bank_x + 30;
        $txt_y = 370 + 42;
        imagettftext($img, $bank_size, 0, $txt_x, $txt_y, $dark_bg, $font_path, $bank);

        // 4. Reserve Price (Big)
        $price_label = "RESERVE PRICE";
        $price_val = "₹ " . indianCurrencyFormat($property['price'] ?? 0);
        $label_size = 34;
        $val_size = 64;

        $label_box = imagettfbbox($label_size, 0, $font_path, $price_label);
        $label_w = $label_box[2] - $label_box[0];
        $x = (int)(($width - $label_w) / 2);
        imagettftext($img, $label_size, 0, $x, 540, $light_gray, $font_path, $price_label);

        $val_box = imagettfbbox($val_size, 0, $font_path, $price_val);
        $val_w = $val_box[2] - $val_box[0];
        $x = (int)(($width - $val_w) / 2);
        imagettftext($img, $val_size, 0, $x, 640, $gold, $font_path, $price_val);

        // 5. Footer Brand
        imagettftext($img, 30, 0, 100, 980, $light_gray, $font_path, "🔹 PRIME PROPERTY");

        return saveImage($img);

    } catch (Exception $e) {
        error_log("generateSocialCard error: " . $e->getMessage());
        return $property['image_url'] ?? '';
    }
}
