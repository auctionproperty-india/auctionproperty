<?php
// ============================================================
// 📥 Excel (MASTER Sheet) → Bulk Upload CSV Converter
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['master_csv'])) {
    $file = $_FILES['master_csv']['tmp_name'];
    $handle = fopen($file, 'r');
    
    // Detect Delimiter (Tab or Comma)
    $firstLine = fgets($handle);
    rewind($handle);
    $delimiter = (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) ? "\t" : ',';
    
    // Read Header
    $header = fgetcsv($handle, 0, $delimiter);
    
    // Output CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bulk_upload_ready.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $out = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Bulk Upload Headers
    fputcsv($out, [
        'title', 'location', 'city', 'state', 'locality', 'type', 'bank_name',
        'borrower_name', 'price', 'reserve_price_per_sqft', 'sqft', 'possession_type',
        'emd_amount', 'bid_increment', 'emd_deadline', 'auction_start_time',
        'auction_end_time', 'auction_date', 'inspection_date', 'contact_number',
        'status', 'description'
    ]);
    
    $validCount = 0;
    $skipCount = 0;
    
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        // Skip empty rows
        if (count(array_filter($row)) === 0) continue;
        
        // Column indexes (0-based from Excel MASTER sheet):
        // A=0 S NO, B=1 BRANCH, C=2 REGION, D=3 ZONE, E=4 PRIME/ROSHNI
        // F=5 LAN NUMBER, G=6 BORROWER NAME, H=7 RESERVED PRICE, I=8 AUCTION DATE
        // J=9 PROPERTY ADDRESS, K=10 LOCATION, L=11 STATE, M=12 POSSESSION
        // N=13 Property Type, O=14 SUPER AREA, P=15 carpet area, Q=16 AGE,
        // R=17 CONFIG, S=18 Parking, T=19 Pictures, U=20 STATUS, V=21 Ownership
        // W=22 Lift, X=23 Floors, Y=24 Floor, Z=25 Furnished, AA=26 Location Map
        
        $serial     = trim($row[0] ?? '');
        $borrower   = trim($row[6] ?? '');
        $priceRaw   = trim($row[7] ?? '');
        $dateRaw    = trim($row[8] ?? '');
        $address    = trim($row[9] ?? '');
        $location   = trim($row[10] ?? '');
        $state      = trim($row[11] ?? '');
        $possession = trim($row[12] ?? 'Physical');
        $type       = trim($row[13] ?? '');
        $areaRaw    = trim($row[14] ?? '');
        $mapLink    = trim($row[26] ?? '');
        
        // ---- SKIP INVALID (Club cases) ----
        $typeLower = strtolower($type);
        $priceLower = strtolower($priceRaw);
        
        if (
            $typeLower === 'club case' || 
            $typeLower === 'club' ||
            $priceLower === 'club' || 
            $priceLower === 'club case' ||
            empty($priceRaw) || 
            !is_numeric(str_replace([',', ' '], '', $priceRaw))
        ) {
            $skipCount++;
            continue;
        }
        
        // ---- Normalize Property Type ----
        $typeMap = [
            'flat' => 'Flat',
            'plot' => 'Plot',
            'shop' => 'Shop',
            'land' => 'Land',
            'house' => 'House',
            'independent house' => 'House',
            'independenthouse' => 'House',
            'row house' => 'Row House',
            'rowhouse' => 'Row House',
            'bungalow' => 'Bungalow',
            'car' => 'Car/Vehicle',
            'car/vehicle' => 'Car/Vehicle',
            'vehicle' => 'Car/Vehicle',
            'commercial shop' => 'Commercial',
            'commercial building' => 'Commercial',
            'commercial unit' => 'Commercial',
            'commercial' => 'Commercial',
            'office' => 'Office',
            'godown' => 'Commercial',
            'independent slab building' => 'Commercial',
        ];
        $normalizedType = $typeMap[$typeLower] ?? 'Other';
        
        // ---- Extract Sqft (Number) ----
        $sqft = 0;
        if (!empty($areaRaw)) {
            $cleanArea = str_replace([',', 'Sq.ft', 'sqft', 'Sq.Ft', 'sq ft', 'SFT', 'Sft'], '', $areaRaw);
            if (preg_match('/(\d+(?:\.\d+)?)/', $cleanArea, $m)) {
                $sqft = (float)$m[1];
            }
        }
        
        // ---- Parse Auction Date ----
        $auctionDate = '';
        if (!empty($dateRaw)) {
            $ts = strtotime($dateRaw);
            if ($ts !== false) {
                $auctionDate = date('d/m/Y', $ts);
            }
        }
        
        // ---- Possession ----
        if (!in_array($possession, ['Physical', 'Symbolic'])) {
            $possession = 'Physical';
        }
        
        // ---- Price ----
        $price = (float)str_replace([',', ' '], '', $priceRaw);
        
        // ---- EMD = 10% of Price ----
        $emd = round($price * 0.1, 2);
        
        // ---- Title ----
        $title = $normalizedType . ' in ' . $location;
        
        // ---- Description ----
        $description = '';
        if (!empty($mapLink) && strtolower($mapLink) !== 'na') {
            $description = 'Location: ' . $mapLink;
        }
        
        // ---- Write Row ----
        fputcsv($out, [
            $title,              // title
            $address,            // location (full address)
            $location,           // city
            $state,              // state
            '',                  // locality (empty)
            $normalizedType,     // type
            'PNB Housing',       // bank_name
            $borrower,           // borrower_name
            $price,              // price
            '',                  // reserve_price_per_sqft
            $sqft,               // sqft
            $possession,         // possession_type
            $emd,                // emd_amount
            '',                  // bid_increment
            '',                  // emd_deadline
            '',                  // auction_start_time
            '',                  // auction_end_time
            $auctionDate,        // auction_date
            '',                  // inspection_date
            '',                  // contact_number
            'available',         // status
            $description         // description
        ]);
        
        $validCount++;
    }
    
    fclose($handle);
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Excel → Bulk Upload Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f0f4f8, #e2e8f0); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .converter-box { max-width: 650px; margin: 60px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); }
        .converter-box h2 { color: #1e3a8a; font-weight: 800; }
        .info-box { background: #eff6ff; padding: 20px; border-radius: 12px; margin: 25px 0; border-left: 5px solid #2563eb; font-size: 14px; }
        .info-box ol { margin: 0; padding-left: 20px; }
        .info-box li { margin-bottom: 6px; }
        .upload-area { border: 3px dashed #93c5fd; border-radius: 16px; padding: 30px; text-align: center; background: #f8faff; cursor: pointer; transition: all 0.3s; }
        .upload-area:hover { border-color: #2563eb; background: #eff6ff; }
        .upload-area i { font-size: 3rem; color: #2563eb; margin-bottom: 10px; }
        .btn-convert { background: linear-gradient(135deg, #1e40af, #2563eb); color: white; padding: 14px 40px; border: none; border-radius: 50px; font-size: 16px; font-weight: 700; width: 100%; margin-top: 20px; transition: all 0.3s; }
        .btn-convert:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(37,99,235,0.4); }
        .fileName { font-weight: 700; color: #10b981; margin-top: 15px; }
    </style>
</head>
<body>
<div class="container">
    <div class="converter-box">
        <h2><i class="fas fa-file-excel text-success me-2"></i> Excel → Bulk Upload Converter</h2>
        <p class="text-muted">PNB Housing Excel File को Bulk Upload Format में Convert करें</p>
        
        <div class="info-box">
            <strong>📋 Steps:</strong>
            <ol>
                <li>अपनी Excel File खोलें (जैसे <code>400+++PNB HOUSING SEP OCT 2026 AUCTION FILE.xlsx</code>)</li>
                <li><strong>MASTER Sheet</strong> पर Right-Click करें → <strong>Move or Copy</strong></li>
                <li>या: सिर्फ MASTER Sheet को नई Excel में Copy करें</li>
                <li>फिर <strong>File → Save As → CSV UTF-8 (Comma delimited) (*.csv)</strong></li>
                <li>वह CSV File यहाँ Upload करें ↓</li>
                <li>Converted File Download हो जाएगी – उसे सीधे Bulk Upload में डाल दें!</li>
            </ol>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <label for="csvInput" class="upload-area d-block">
                <i class="fas fa-cloud-upload-alt"></i>
                <div class="fw-bold">Click here to Select CSV File</div>
                <div class="text-muted small">MASTER Sheet की CSV File चुनें</div>
                <input type="file" name="master_csv" id="csvInput" accept=".csv" style="display:none;" required>
                <div class="fileName" id="fileName"></div>
            </label>
            <button type="submit" class="btn-convert">
                <i class="fas fa-magic me-2"></i> Convert & Download Bulk Upload CSV
            </button>
        </form>
        
        <div class="alert alert-warning mt-4 mb-0" style="font-size: 13px;">
            <strong>⚠️ नोट:</strong> "Club case" वाली सभी Rows अपने आप Skip हो जाएँगी।<br>
            केवल Actual Properties ही CSV में आएँगी।
        </div>
    </div>
</div>

<script>
document.getElementById('csvInput').addEventListener('change', function() {
    if (this.files.length > 0) {
        document.getElementById('fileName').innerHTML = '✅ Selected: ' + this.files[0].name;
    }
});
document.querySelector('.upload-area').addEventListener('click', function(e) {
    if (e.target.tagName !== 'INPUT') {
        document.getElementById('csvInput').click();
    }
});
</script>
</body>
</html>
