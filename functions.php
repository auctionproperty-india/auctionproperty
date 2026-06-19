function generateSocialCard($property) {
    // GD Check
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
        if (!$img) {
            error_log("Failed to create image resource");
            return $property['image_url'] ?? '';
        }

        // Colors
        $dark_blue = imagecolorallocate($img, 15, 23, 42);
        $light_blue = imagecolorallocate($img, 30, 58, 138);
        $white = imagecolorallocate($img, 255, 255, 255);
        $gold = imagecolorallocate($img, 251, 191, 36);
        $light_gray = imagecolorallocate($img, 200, 210, 220);
        $dark_bg = imagecolorallocate($img, 15, 23, 42);

        // Background
        imagefilledrectangle($img, 0, 0, $width, $height, $dark_blue);
        for ($i = 0; $i < $height; $i += 10) {
            $ratio = $i / $height;
            $r = 15 + (30 - 15) * $ratio;
            $g = 23 + (58 - 23) * $ratio;
            $b = 42 + (138 - 42) * $ratio;
            $col = imagecolorallocate($img, (int)$r, (int)$g, (int)$b);
            imagefilledrectangle($img, 0, $i, $width, $i+10, $col);
        }

        // Fallback (built-in font)
        $font_size = 5;
        $title = strtoupper($property['title'] ?? 'PROPERTY');
        $x = ($width - (strlen($title) * imagefontwidth($font_size))) / 2;
        imagestring($img, $font_size, $x, 180, $title, $gold);

        $sub = $property['locality'] ?? $property['city'] ?? '';
        $sx = ($width - (strlen($sub) * imagefontwidth($font_size))) / 2;
        imagestring($img, $font_size, $sx, 240, $sub, $light_gray);

        $detail_y = 400;
        $lines = [
            "BANK: " . ($property['bank_name'] ?? 'N/A'),
            "PRICE: ₹ " . indianCurrencyFormat($property['price'] ?? 0),
            "AREA: " . ($property['sqft'] ?? 0) . " Sq Ft",
            "CONTACT: " . ($property['contact_number'] ?? 'N/A')
        ];
        foreach ($lines as $i => $line) {
            $y = $detail_y + ($i * 40);
            $x = ($width - (strlen($line) * imagefontwidth($font_size))) / 2;
            imagestring($img, $font_size, $x, $y, $line, $white);
        }

        // Save
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $filename = 'social_' . time() . '_' . bin2hex(random_bytes(6)) . '.png';
        $path = $upload_dir . $filename;

        if (!imagepng($img, $path, 9)) {
            error_log("Failed to save image: " . $path);
            imagedestroy($img);
            return $property['image_url'] ?? '';
        }

        imagedestroy($img);
        return $path;

    } catch (Exception $e) {
        error_log("generateSocialCard error: " . $e->getMessage());
        return $property['image_url'] ?? '';
    }
}
