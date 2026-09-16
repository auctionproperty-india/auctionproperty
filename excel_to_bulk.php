<?php
// ============================================================
// 🔄 Universal Converter – Excel (.xlsx) / CSV → Bulk Upload CSV
// Direct Excel Support (No PhpSpreadsheet needed)
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================================
// HELPER: Column Letter to Index (A=0, B=1, ..., AA=26)
// ============================================================
function colLetterToIndex($letters) {
    $letters = strtoupper($letters);
    $num = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $num = $num * 26 + (ord($letters[$i]) - 64);
    }
    return $num - 1;
}

// ============================================================
// HELPER: Read XLSX File (Manual XML Parsing)
// ============================================================
function readXlsx($filepath, $preferredSheet = 'MASTER') {
    if (!class_exists('ZipArchive')) {
        // Try shell unzip fallback
        return readXlsxWithUnzip($filepath, $preferredSheet);
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        throw new Exception("Cannot open XLSX file (ZipArchive failed)");
    }

    // ---- Step 1: Read Shared Strings ----
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $xml = @simplexml_load_string($ssXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) {
                    $text = (string)$si->t;
                } elseif (isset($si->r)) {
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // ---- Step 2: Find Sheet by Name ----
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    $sheetTarget = 'xl/worksheets/sheet1.xml'; // default

    if ($workbookXml !== false && $relsXml !== false) {
        $wbXml = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);

        $rIdToTarget = [];
        if ($rels && isset($rels->Relationship)) {
            foreach ($rels->Relationship as $rel) {
                $rIdToTarget[(string)$rel['Id']] = (string)$rel['Target'];
            }
        }

        $found = false;
        if ($wbXml && isset($wbXml->sheets->sheet)) {
            foreach ($wbXml->sheets->sheet as $sheet) {
                $name = (string)$sheet['name'];
                $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                if (strtoupper($name) === strtoupper($preferredSheet) && isset($rIdToTarget[$rId])) {
                    $target = $rIdToTarget[$rId];
                    $sheetTarget = (strpos($target, 'xl/') === 0) ? $target : 'xl/' . ltrim($target, '/');
                    $found = true;
                    break;
                }
            }
        }
        // If preferred sheet not found, use first
        if (!$found && $wbXml && isset($wbXml->sheets->sheet)) {
            $firstSheet = $wbXml->sheets->sheet[0];
            $rId = (string)$firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            if (isset($rIdToTarget[$rId])) {
                $target = $rIdToTarget[$rId];
                $sheetTarget = (strpos($target, 'xl/') === 0) ? $target : 'xl/' . ltrim($target, '/');
            }
        }
    }

    // ---- Step 3: Read Sheet XML ----
    $sheetXml = $zip->getFromName($sheetTarget);
    if ($sheetXml === false) {
        // Try sheet1.xml anyway
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    }
    $zip->close();

    if ($sheetXml === false) {
        throw new Exception("Cannot read sheet XML. Sheet tried: $sheetTarget");
    }

    // ---- Step 4: Parse Sheet ----
    $xml = @simplexml_load_string($sheetXml);
    if (!$xml || !isset($xml->sheetData->row)) {
        throw new Exception("Cannot parse sheet XML");
    }

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $maxCol = -1;

        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            $type = (string)$cell['t'];
            $value = isset($cell->v) ? (string)$cell->v : '';

            // Handle shared string
            if ($type === 's') {
                $value = $sharedStrings[(int)$value] ?? '';
            } elseif ($type === 'inlineStr') {
                if (isset($cell->is->t)) {
                    $value = (string)$cell->is->t;
                } elseif (isset($cell->is->r)) {
                    $value = '';
                    foreach ($cell->is->r as $r) {
                        $value .= (string)$r->t;
                    }
                }
            }

            // Get column index
            if (preg_match('/([A-Z]+)/', $ref, $m)) {
                $colIdx = colLetterToIndex($m[1]);
                $rowData[$colIdx] = $value;
                if ($colIdx > $maxCol) $maxCol = $colIdx;
            }
        }

        // Fill missing columns
        $fullRow = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $fullRow[$i] = $rowData[$i] ?? '';
        }
        $rows[] = $fullRow;
    }

    return $rows;
}

// ============================================================
// HELPER: Fallback for XLSX (using shell unzip)
// ============================================================
function readXlsxWithUnzip($filepath, $preferredSheet = 'MASTER') {
    if (!function_exists('exec')) {
        throw new Exception("Neither ZipArchive nor exec() is available on this server.");
    }

    $tmpDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
    @mkdir($tmpDir, 0755, true);

    $cmd = "unzip -o " . escapeshellarg($filepath) . " -d " . escapeshellarg($tmpDir) . " 2>&1";
    exec($cmd, $output, $return);

    if ($return !== 0) {
        throw new Exception("unzip command failed: " . implode("\n", $output));
    }

    // Read shared strings
    $sharedStrings = [];
    $ssFile = $tmpDir . '/xl/sharedStrings.xml';
    if (file_exists($ssFile)) {
        $xml = @simplexml_load_string(file_get_contents($ssFile));
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) $text = (string)$si->t;
                elseif (isset($si->r)) foreach ($si->r as $r) $text .= (string)$r->t;
                $sharedStrings[] = $text;
            }
        }
    }

    // Find sheet
    $sheetFile = $tmpDir . '/xl/worksheets/sheet1.xml';

    $wbFile = $tmpDir . '/xl/workbook.xml';
    $relsFile = $tmpDir . '/xl/_rels/workbook.xml.rels';
    if (file_exists($wbFile) && file_exists($relsFile)) {
        $wbXml = @simplexml_load_string(file_get_contents($wbFile));
        $rels = @simplexml_load_string(file_get_contents($relsFile));
        $rIdToTarget = [];
        if ($rels && isset($rels->Relationship)) {
            foreach ($rels->Relationship as $rel) {
                $rIdToTarget[(string)$rel['Id']] = (string)$rel['Target'];
            }
        }
        if ($wbXml && isset($wbXml->sheets->sheet)) {
            foreach ($wbXml->sheets->sheet as $sheet) {
                $name = (string)$sheet['name'];
                $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                if (strtoupper($name) === strtoupper($preferredSheet) && isset($rIdToTarget[$rId])) {
                    $target = $rIdToTarget[$rId];
                    $sheetFile = $tmpDir . '/' . ltrim($target, '/');
                    if (strpos($target, 'xl/') !== 0) $sheetFile = $tmpDir . '/xl/' . ltrim($target, '/');
                    break;
                }
            }
        }
    }

    if (!file_exists($sheetFile)) {
        throw new Exception("Sheet file not found");
    }

    $sheetXml = file_get_contents($sheetFile);
    $xml = @simplexml_load_string($sheetXml);

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $maxCol = -1;
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            $type = (string)$cell['t'];
            $value = isset($cell->v) ? (string)$cell->v : '';
            if ($type === 's') {
                $value = $sharedStrings[(int)$value] ?? '';
            } elseif ($type === 'inlineStr') {
                if (isset($cell->is->t)) $value = (string)$cell->is->t;
            }
            if (preg_match('/([A-Z]+)/', $ref, $m)) {
                $colIdx = colLetterToIndex($m[1]);
                $rowData[$colIdx] = $value;
                if ($colIdx > $maxCol) $maxCol = $colIdx;
            }
        }
        $fullRow = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $fullRow[$i] = $rowData[$i] ?? '';
        }
        $rows[] = $fullRow;
    }

    // Cleanup
    exec("rm -rf " . escapeshellarg($tmpDir));

    return $rows;
}

// ============================================================
// HELPER: Read CSV / TSV
// ============================================================
function readCsv($filepath, $delimiter = null) {
    $handle = fopen($filepath, 'r');
    if (!$handle) throw new Exception("Cannot open file");

    if ($delimiter === null) {
        $firstLine = fgets($handle);
        rewind($handle);
        $tabCount = substr_count($firstLine, "\t");
        $commaCount = substr_count($firstLine, ',');
        $delimiter = ($tabCount > $commaCount) ? "\t" : ',';
    }

    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

// ============================================================
// HELPER: Clean UTF-8
// ============================================================
function cleanUTF8($str) {
    if ($str === null) return '';
    $str = (string)$str;
    if (!mb_check_encoding($str, 'UTF-8')) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $str);
        if ($converted !== false) $str = $converted;
        else $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    }
    $str = str_replace(
        ["\xE2\x80\x9C","\xE2\x80\x9D","\xE2\x80\x98","\xE2\x80\x99","\xE2\x80\x93","\xE2\x80\x94","\xE2\x80\xA6","\xC2\xA0"],
        ['"','"',"'","'",'-','-','...',' '],
        $str
    );
    return trim(mb_convert_encoding($str, 'UTF-8', 'UTF-8'));
}

// ============================================================
// HELPER: Extract Number
// ============================================================
function extractNumber($str) {
    if (empty($str)) return 0;
    $str = str_replace([',', '₹', ' '], '', $str);
    if (preg_match('/-?\d+(\.\d+)?/', $str, $m)) return (float)$m[0];
    return 0;
}

// ============================================================
// HELPER: Normalize Type
// ============================================================
function normalizeType($type) {
    $type = trim($type);
    if (empty($type)) return 'Other';
    $map = [
        'flat' => 'Flat', 'plot' => 'Plot', 'shop' => 'Shop', 'land' => 'Land',
        'house' => 'House', 'independent house' => 'House', 'independenthouse' => 'House',
        'row house' => 'Row House', 'rowhouse' => 'Row House', 'bungalow' => 'Bungalow',
        'car' => 'Car/Vehicle', 'car/vehicle' => 'Car/Vehicle', 'vehicle' => 'Car/Vehicle',
        'commercial' => 'Commercial', 'commercial shop' => 'Commercial',
        'commercial building' => 'Commercial', 'commercial unit' => 'Commercial',
        'office' => 'Office', 'other' => 'Other', 'club case' => 'Other', 'club' => 'Other',
        'godown' => 'Commercial', 'independent slab building' => 'Commercial',
        'villa' => 'House', 'apartment' => 'Flat', 'highrise' => 'Flat',
    ];
    return $map[strtolower($type)] ?? 'Other';
}

// ============================================================
// HELPER: Parse Date (multiple formats)
// ============================================================
function parseAnyDate($value) {
    if (empty($value)) return null;
    $value = trim($value);
    if ($value === '' || strtolower($value) === 'na' || strtolower($value) === 'n/a') return null;

    // Excel serial date (number)
    if (is_numeric($value) && $value > 25569 && $value < 73000) {
        $unix = ($value - 25569) * 86400;
        return date('Y-m-d', $unix);
    }

    // Try strtotime
    $ts = strtotime($value);
    if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);

    return null;
}

// ============================================================
// HELPER: Detect header row & find column indexes
// ============================================================
function findHeaderRowAndColumns($rows) {
    $headerKeywords = [
        'title' => ['s no', 'sr no', 'serial', 'borrower name', 'property address', 'auction date'],
    ];

    // Try first 10 rows to find header
    foreach ($rows as $i => $row) {
        if ($i > 10) break;
        $joined = strtolower(implode(' ', $row));
        if (
            (strpos($joined, 's no') !== false || strpos($joined, 'sr') !== false || strpos($joined, 'serial') !== false) &&
            (strpos($joined, 'borrower') !== false || strpos($joined, 'auction') !== false || strpos($joined, 'property') !== false)
        ) {
            return $i;
        }
    }
    return 0;
}

// ============================================================
// HELPER: Smart Column Mapping (by header names)
// ============================================================
function detectColumnMap($headerRow) {
    $map = [];
    foreach ($headerRow as $idx => $col) {
        $colLower = strtolower(trim($col));

        if (strpos($colLower, 'borrower') !== false) $map['borrower'] = $idx;
        elseif (strpos($colLower, 'reserved price') !== false || strpos($colLower, 'reserve price') !== false) $map['price'] = $idx;
        elseif (strpos($colLower, 'auction date') !== false) $map['auction_date'] = $idx;
        elseif (strpos($colLower, 'property address') !== false || strpos($colLower, 'address') !== false) $map['address'] = $idx;
        elseif (strpos($colLower, 'location') !== false && !isset($map['location'])) $map['location'] = $idx;
        elseif (strpos($colLower, 'state') !== false) $map['state'] = $idx;
        elseif (strpos($colLower, 'possession') !== false) $map['possession'] = $idx;
        elseif (strpos($colLower, 'property type') !== false || strpos($colLower, 'nature of') !== false) $map['type'] = $idx;
        elseif (strpos($colLower, 'super area') !== false || strpos($colLower, 'covered area') !== false) $map['super_area'] = $idx;
        elseif (strpos($colLower, 'carpet') !== false) $map['carpet'] = $idx;
        elseif (strpos($colLower, 'location map') !== false || strpos($colLower, 'map') !== false) $map['map'] = $idx;
        elseif (strpos($colLower, 'lan number') !== false) $map['lan'] = $idx;
        elseif (strpos($colLower, 'branch') !== false) $map['branch'] = $idx;
    }
    return $map;
}

// ============================================================
// HELPER: Convert rows to Bulk Upload Format
// ============================================================
function convertToBulkFormat($rows, $sheetName = 'PNB Housing') {
    if (empty($rows)) return ['', 0, 0];

    // Find header row
    $headerIdx = findHeaderRowAndColumns($rows);
    $headerRow = $rows[$headerIdx] ?? [];

    // Detect column map from header
    $colMap = detectColumnMap($headerRow);

    // Fallback to positional mapping (PNB format)
    if (!isset($colMap['borrower'])) $colMap['borrower'] = 6;
    if (!isset($colMap['price'])) $colMap['price'] = 7;
    if (!isset($colMap['auction_date'])) $colMap['auction_date'] = 8;
    if (!isset($colMap['address'])) $colMap['address'] = 9;
    if (!isset($colMap['location'])) $colMap['location'] = 10;
    if (!isset($colMap['state'])) $colMap['state'] = 11;
    if (!isset($colMap['possession'])) $colMap['possession'] = 12;
    if (!isset($colMap['type'])) $colMap['type'] = 13;
    if (!isset($colMap['super_area'])) $colMap['super_area'] = 14;
    if (!isset($colMap['map'])) $colMap['map'] = 26;

    // Build CSV
    $out = '';
    $out .= "\xEF\xBB\xBF"; // UTF-8 BOM

    // Header
    $out .= implode(',', [
        'title', 'location', 'city', 'state', 'locality', 'type', 'bank_name',
        'borrower_name', 'price', 'reserve_price_per_sqft', 'sqft', 'possession_type',
        'emd_amount', 'bid_increment', 'emd_deadline', 'auction_start_time',
        'auction_end_time', 'auction_date', 'inspection_date', 'contact_number',
        'status', 'description'
    ]) . "\n";

    $validCount = 0;
    $skipCount = 0;

    // Loop from row AFTER header
    for ($i = $headerIdx + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row))) continue;

        // Clean all
        $row = array_map('cleanUTF8', $row);

        $getVal = function($key) use ($row, $colMap) {
            return isset($colMap[$key]) && isset($row[$colMap[$key]]) ? $row[$colMap[$key]] : '';
        };

        $borrower = $getVal('borrower');
        $priceRaw = $getVal('price');
        $dateRaw = $getVal('auction_date');
        $address = $getVal('address');
        $location = $getVal('location');
        $state = $getVal('state');
        $possession = $getVal('possession');
        $type = $getVal('type');
        $areaRaw = $getVal('super_area');
        $mapLink = $getVal('map');

        // Skip Club Cases
        $typeLower = strtolower($type);
        $priceLower = strtolower($priceRaw);
        if (
            $typeLower === 'club case' || $typeLower === 'club' ||
            $priceLower === 'club' || $priceLower === 'club case' ||
            empty($priceRaw) || !is_numeric(str_replace([',', ' '], '', $priceRaw))
        ) {
            $skipCount++;
            continue;
        }

        $price = extractNumber($priceRaw);
        if ($price <= 0) { $skipCount++; continue; }

        $auctionDate = parseAnyDate($dateRaw);
        if (!$auctionDate) { $skipCount++; continue; }

        $normalizedType = normalizeType($type);
        $sqft = extractNumber($areaRaw);
        $title = $normalizedType . ' in ' . $location;

        // Possession
        if (!in_array($possession, ['Physical', 'Symbolic'])) $possession = 'Physical';

        // EMD = 10% of price
        $emd = round($price * 0.1, 2);

        // EMD Deadline = Auction Date - 1 day (5 PM)
        $emdDeadline = date('d/m/Y 05:00 PM', strtotime($auctionDate . ' -1 day'));

        // Auction date format
        $auctionDateFormatted = date('d/m/Y', strtotime($auctionDate));

        // Description
        $description = '';
        if (!empty($mapLink) && strtolower($mapLink) !== 'na') {
            $description = 'Location: ' . $mapLink;
        }

        // Build CSV row
        $csvRow = [
            $title,
            $address,
            $location,
            $state,
            '',                          // locality
            $normalizedType,
            $sheetName,
            $borrower,
            $price,
            '',                          // reserve_price_per_sqft
            $sqft,
            $possession,
            $emd,
            '',                          // bid_increment
            $emdDeadline,
            '',                          // auction_start_time
            '',                          // auction_end_time
            $auctionDateFormatted,
            '',                          // inspection_date
            '',                          // contact_number
            'available',
            $description
        ];

        // Proper CSV escape
        $escaped = [];
        foreach ($csvRow as $val) {
            if (strpos($val, ',') !== false || strpos($val, '"') !== false || strpos($val, "\n") !== false) {
                $escaped[] = '"' . str_replace('"', '""', $val) . '"';
            } else {
                $escaped[] = $val;
            }
        }
        $out .= implode(',', $escaped) . "\n";
        $validCount++;
    }

    return [$out, $validCount, $skipCount];
}

// ============================================================
// HANDLE UPLOAD
// ============================================================
$error = '';
$success = false;
$totalRows = 0;
$skipCount = 0;
$downloadData = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['source_file'])) {
    try {
        $file = $_FILES['source_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("File upload error: " . $file['error']);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Read file based on type
        if ($ext === 'xlsx') {
            $rows = readXlsx($file['tmp_name'], 'MASTER');
        } elseif ($ext === 'xls') {
            throw new Exception("Old .xls format not supported. Please save as .xlsx in Excel.");
        } elseif (in_array($ext, ['csv', 'txt', 'tsv'])) {
            $rows = readCsv($file['tmp_name']);
        } else {
            throw new Exception("Unsupported file type: .$ext");
        }

        if (empty($rows)) throw new Exception("No data found in the file");

        // Convert
        list($csvData, $totalRows, $skipCount) = convertToBulkFormat($rows, 'PNB Housing');

        if ($totalRows === 0) {
            throw new Exception("No valid properties found (all may be Club Cases or missing required fields)");
        }

        $success = true;
        $downloadData = $csvData;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <title>Universal Excel → Bulk Upload Converter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #f0f4f8, #e2e8f0); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .converter-box { max-width: 750px; margin: 40px auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); }
        .converter-box h2 { color: #1e3a8a; font-weight: 800; }
        .info-box { background: #eff6ff; padding: 20px; border-radius: 12px; margin: 25px 0; border-left: 5px solid #2563eb; font-size: 14px; }
        .info-box ol { margin: 0; padding-left: 20px; }
        .info-box li { margin-bottom: 6px; }
        .upload-area { border: 3px dashed #93c5fd; border-radius: 16px; padding: 40px 20px; text-align: center; background: #f8faff; cursor: pointer; transition: all 0.3s; }
        .upload-area:hover { border-color: #2563eb; background: #eff6ff; }
        .upload-area i { font-size: 3.5rem; color: #2563eb; margin-bottom: 12px; }
        .btn-convert { background: linear-gradient(135deg, #1e40af, #2563eb); color: white; padding: 14px 40px; border: none; border-radius: 50px; font-size: 16px; font-weight: 700; width: 100%; margin-top: 20px; transition: all 0.3s; }
        .btn-convert:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(37,99,235,0.4); }
        .btn-convert:disabled { opacity: 0.5; cursor: not-allowed; }
        .fileName { font-weight: 700; color: #10b981; margin-top: 15px; }
        .result-card { background: #ecfdf5; border-left: 5px solid #10b981; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .error-card { background: #fef2f2; border-left: 5px solid #dc2626; padding: 20px; border-radius: 12px; margin-bottom: 20px; color: #991b1b; }
        .btn-download { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 14px 40px; border: none; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; margin-top: 15px; }
        .btn-download:hover { color: white; transform: translateY(-2px); }
        .badge-support { background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-right: 5px; }
    </style>
</head>
<body>
<div class="container">
    <div class="converter-box">
        <h2><i class="fas fa-magic text-primary me-2"></i> Universal Excel → Bulk Upload Converter</h2>
        <p class="text-muted">किसी भी Excel / CSV File को सीधे Bulk Upload Format में Convert करें</p>

        <div class="mb-3">
            <span class="badge-support">✅ .xlsx (Excel 2007+)</span>
            <span class="badge-support">✅ .csv</span>
            <span class="badge-support">✅ .tsv</span>
        </div>

        <?php if ($error): ?>
            <div class="error-card">
                <strong>❌ Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="result-card">
                <h5 class="fw-bold text-success mb-2">✅ Conversion Successful!</h5>
                <p class="mb-1"><strong>Valid Properties:</strong> <span class="text-success fw-bold"><?= $totalRows ?></span></p>
                <p class="mb-0"><strong>Skipped (Club/Invalid):</strong> <span class="text-warning fw-bold"><?= $skipCount ?></span></p>
            </div>

            <form method="POST" id="downloadForm">
                <input type="hidden" name="download_data" id="downloadData">
                <button type="button" onclick="downloadCsv()" class="btn-download">
                    <i class="fas fa-download me-2"></i> Download Bulk Upload CSV
                </button>
            </form>

            <a href="?" class="btn btn-secondary mt-3 rounded-pill">Convert Another File</a>

            <script>
            function downloadCsv() {
                const csvData = <?= json_encode($downloadData) ?>;
                const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'bulk_upload_ready_' + new Date().toISOString().slice(0,10) + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
            </script>

        <?php else: ?>

            <div class="info-box">
                <strong>📋 Steps:</strong>
                <ol>
                    <li>अपनी Excel File चुनें (जैसे <code>400+++PNB HOUSING.xlsx</code>)</li>
                    <li><strong>"Convert & Download"</strong> बटन दबाएँ</li>
                    <li>File में से <strong>MASTER Sheet</strong> अपने आप पढ़ी जाएगी</li>
                    <li>Club Cases और Invalid Rows Skip हो जाएँगी</li>
                    <li>Bulk Upload CSV Download हो जाएगी – सीधे Upload करें!</li>
                </ol>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <label for="srcInput" class="upload-area d-block">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <div class="fw-bold">Click here to Select File</div>
                    <div class="text-muted small">.xlsx / .csv / .tsv supported</div>
                    <input type="file" name="source_file" id="srcInput" accept=".xlsx,.csv,.tsv,.txt" style="display:none;" required>
                    <div class="fileName" id="fileName"></div>
                </label>
                <button type="submit" class="btn-convert">
                    <i class="fas fa-magic me-2"></i> Convert & Download Bulk Upload CSV
                </button>
            </form>

        <?php endif; ?>

        <div class="alert alert-warning mt-4 mb-0" style="font-size: 13px;">
            <strong>⚠️ Features:</strong><br>
            • Direct .xlsx पढ़ता है – CSV में Convert करने की ज़रूरत नहीं<br>
            • MASTER Sheet Auto-Detect करता है<br>
            • "Club case" वाली Rows Auto-Skip<br>
            • EMD Deadline = Auction Date − 1 Day (Auto)<br>
            • Type Auto-Normalize (FLAT → Flat, etc.)
        </div>
    </div>
</div>

<script>
document.getElementById('srcInput')?.addEventListener('change', function() {
    if (this.files.length > 0) {
        document.getElementById('fileName').innerHTML = '✅ Selected: ' + this.files[0].name;
    }
});
document.querySelector('.upload-area')?.addEventListener('click', function(e) {
    if (e.target.tagName !== 'INPUT') {
        document.getElementById('srcInput').click();
    }
});
</script>
</body>
</html>
