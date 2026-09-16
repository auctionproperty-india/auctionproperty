<?php
// ============================================================
// 📤 Bulk Upload Properties – Convert + Upload in ONE Page
// Tab 1: Convert Excel → CSV Download
// Tab 2: Upload CSV → Database
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'sub_admin');

// ============================================================
// 🔥 HELPER FUNCTIONS
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

function extractNumber($str) {
    if (empty($str)) return 0;
    $str = str_replace([',', '₹', ' '], '', $str);
    if (preg_match('/-?\d+(\.\d+)?/', $str, $m)) return (float)$m[0];
    return 0;
}

function isInvalidValue($str) {
    if (empty($str)) return true;
    $lower = strtolower(trim($str));
    return in_array($lower, ['#value!', 'club', 'club case', 'na', 'n/a', '-', 'null']);
}

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

function parseDate($dateStr) {
    if (empty($dateStr) || trim($dateStr) === '') return null;
    $dateStr = trim($dateStr);
    if (is_numeric($dateStr) && $dateStr > 25569 && $dateStr < 73000) {
        return date('Y-m-d', ($dateStr - 25569) * 86400);
    }
    $parts = explode(' ', $dateStr);
    $datePart = $parts[0];
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $datePart, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $datePart, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    $ts = strtotime($dateStr);
    if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);
    return null;
}

function parseDateTimeFlexible($str) {
    if (empty($str) || trim($str) === '') return null;
    $str = trim($str);
    if (in_array(strtolower($str), ['#value!', 'na', 'n/a', 'null', '-', 'club', 'club case'])) return null;
    $ts = strtotime($str);
    if ($ts !== false && $ts > 0) return date('Y-m-d H:i:s', $ts);
    return null;
}

// ============================================================
// 🔥 XLSX READER
// ============================================================
function colLetterToIndex($letters) {
    $letters = strtoupper($letters);
    $num = 0;
    for ($i = 0; $i < strlen($letters); $i++) $num = $num * 26 + (ord($letters[$i]) - 64);
    return $num - 1;
}

function readXlsxFile($filepath, $preferredSheet = 'MASTER') {
    if (class_exists('ZipArchive')) return readXlsxWithZip($filepath, $preferredSheet);
    else return readXlsxWithUnzip($filepath, $preferredSheet);
}

function readXlsxWithZip($filepath, $preferredSheet) {
    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) throw new Exception("Cannot open XLSX file");
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $xml = @simplexml_load_string($ssXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) $text = (string)$si->t;
                elseif (isset($si->r)) foreach ($si->r as $r) $text .= (string)$r->t;
                $sharedStrings[] = $text;
            }
        }
    }
    $sheetTarget = 'xl/worksheets/sheet1.xml';
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml !== false && $relsXml !== false) {
        $wbXml = @simplexml_load_string($workbookXml);
        $rels = @simplexml_load_string($relsXml);
        $rIdToTarget = [];
        if ($rels && isset($rels->Relationship)) {
            foreach ($rels->Relationship as $rel) $rIdToTarget[(string)$rel['Id']] = (string)$rel['Target'];
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
        if (!$found && $wbXml && isset($wbXml->sheets->sheet)) {
            $firstSheet = $wbXml->sheets->sheet[0];
            $rId = (string)$firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            if (isset($rIdToTarget[$rId])) {
                $target = $rIdToTarget[$rId];
                $sheetTarget = (strpos($target, 'xl/') === 0) ? $target : 'xl/' . ltrim($target, '/');
            }
        }
    }
    $sheetXml = $zip->getFromName($sheetTarget);
    if ($sheetXml === false) $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) throw new Exception("Cannot read sheet");
    return parseSheetXml($sheetXml, $sharedStrings);
}

function readXlsxWithUnzip($filepath, $preferredSheet) {
    if (!function_exists('exec')) throw new Exception("ZipArchive और exec() दोनों unavailable हैं");
    $tmpDir = sys_get_temp_dir() . '/xlsx_' . uniqid();
    @mkdir($tmpDir, 0755, true);
    exec("unzip -o " . escapeshellarg($filepath) . " -d " . escapeshellarg($tmpDir) . " 2>&1", $out, $ret);
    if ($ret !== 0) throw new Exception("unzip failed");
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
    $sheetFile = $tmpDir . '/xl/worksheets/sheet1.xml';
    $wbFile = $tmpDir . '/xl/workbook.xml';
    $relsFile = $tmpDir . '/xl/_rels/workbook.xml.rels';
    if (file_exists($wbFile) && file_exists($relsFile)) {
        $wbXml = @simplexml_load_string(file_get_contents($wbFile));
        $rels = @simplexml_load_string(file_get_contents($relsFile));
        $rIdToTarget = [];
        if ($rels && isset($rels->Relationship)) {
            foreach ($rels->Relationship as $rel) $rIdToTarget[(string)$rel['Id']] = (string)$rel['Target'];
        }
        if ($wbXml && isset($wbXml->sheets->sheet)) {
            foreach ($wbXml->sheets->sheet as $sheet) {
                $name = (string)$sheet['name'];
                $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                if (strtoupper($name) === strtoupper($preferredSheet) && isset($rIdToTarget[$rId])) {
                    $target = $rIdToTarget[$rId];
                    $sheetFile = (strpos($target, 'xl/') === 0) ? $tmpDir . '/' . $target : $tmpDir . '/xl/' . ltrim($target, '/');
                    break;
                }
            }
        }
    }
    if (!file_exists($sheetFile)) throw new Exception("Sheet file not found");
    $sheetXml = file_get_contents($sheetFile);
    exec("rm -rf " . escapeshellarg($tmpDir));
    return parseSheetXml($sheetXml, $sharedStrings);
}

function parseSheetXml($sheetXml, $sharedStrings) {
    $xml = @simplexml_load_string($sheetXml);
    if (!$xml || !isset($xml->sheetData->row)) throw new Exception("Cannot parse sheet");
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $maxCol = -1;
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];
            $type = (string)$cell['t'];
            $value = isset($cell->v) ? (string)$cell->v : '';
            if ($type === 's') $value = $sharedStrings[(int)$value] ?? '';
            elseif ($type === 'inlineStr') {
                if (isset($cell->is->t)) $value = (string)$cell->is->t;
                elseif (isset($cell->is->r)) {
                    $value = '';
                    foreach ($cell->is->r as $r) $value .= (string)$r->t;
                }
            }
            if (preg_match('/([A-Z]+)/', $ref, $m)) {
                $colIdx = colLetterToIndex($m[1]);
                $rowData[$colIdx] = $value;
                if ($colIdx > $maxCol) $maxCol = $colIdx;
            }
        }
        $fullRow = [];
        for ($i = 0; $i <= $maxCol; $i++) $fullRow[$i] = $rowData[$i] ?? '';
        $rows[] = $fullRow;
    }
    return $rows;
}

function readCsvFile($filepath) {
    $handle = fopen($filepath, 'r');
    if (!$handle) throw new Exception("Cannot open file");
    $firstLine = fgets($handle);
    rewind($handle);
    $tabCount = substr_count($firstLine, "\t");
    $commaCount = substr_count($firstLine, ',');
    $delimiter = ($tabCount > $commaCount) ? "\t" : ',';
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) $rows[] = $row;
    fclose($handle);
    return $rows;
}

// ============================================================
// 🔥 CONVERT MASTER FILE TO BULK FORMAT
// ============================================================
function convertToBulk($rows, $bankName = 'PNB Housing') {
    if (empty($rows)) return [null, 0, 0, []];

    $headerIdx = 0;
    foreach ($rows as $i => $row) {
        if ($i > 10) break;
        $joined = strtolower(implode(' ', $row));
        if ((strpos($joined, 's no') !== false || strpos($joined, 'sr') !== false) &&
            (strpos($joined, 'borrower') !== false || strpos($joined, 'auction') !== false)) {
            $headerIdx = $i;
            break;
        }
    }

    $headerRow = $rows[$headerIdx] ?? [];
    $colMap = [];
    foreach ($headerRow as $idx => $col) {
        $colLower = strtolower(trim($col));
        if (strpos($colLower, 'borrower') !== false && !isset($colMap['borrower'])) $colMap['borrower'] = $idx;
        elseif ((strpos($colLower, 'reserved price') !== false || strpos($colLower, 'reserve price') !== false) && !isset($colMap['price'])) $colMap['price'] = $idx;
        elseif (strpos($colLower, 'auction date') !== false && !isset($colMap['auction_date'])) $colMap['auction_date'] = $idx;
        elseif ((strpos($colLower, 'property address') !== false || strpos($colLower, 'full address') !== false) && !isset($colMap['address'])) $colMap['address'] = $idx;
        elseif (strpos($colLower, 'location') !== false && !isset($colMap['location'])) $colMap['location'] = $idx;
        elseif (strpos($colLower, 'state') !== false && !isset($colMap['state'])) $colMap['state'] = $idx;
        elseif (strpos($colLower, 'possession') !== false && !isset($colMap['possession'])) $colMap['possession'] = $idx;
        elseif ((strpos($colLower, 'property type') !== false || strpos($colLower, 'nature of') !== false) && !isset($colMap['type'])) $colMap['type'] = $idx;
        elseif ((strpos($colLower, 'super area') !== false || strpos($colLower, 'covered area') !== false) && !isset($colMap['area'])) $colMap['area'] = $idx;
        elseif ((strpos($colLower, 'location map') !== false || $colLower === 'map') && !isset($colMap['map'])) $colMap['map'] = $idx;
    }

    $defaults = ['borrower'=>6,'price'=>7,'auction_date'=>8,'address'=>9,'location'=>10,'state'=>11,'possession'=>12,'type'=>13,'area'=>14,'map'=>26];
    foreach ($defaults as $k => $v) if (!isset($colMap[$k])) $colMap[$k] = $v;

    $out = "\xEF\xBB\xBF";
    $out .= implode(',', [
        'title', 'location', 'city', 'state', 'locality', 'type', 'bank_name',
        'borrower_name', 'price', 'reserve_price_per_sqft', 'sqft', 'possession_type',
        'emd_amount', 'bid_increment', 'emd_deadline', 'auction_start_time',
        'auction_end_time', 'auction_date', 'inspection_date', 'contact_number',
        'status', 'description'
    ]) . "\n";

    $validCount = 0;
    $skipCount = 0;
    $skipRows = [];

    for ($i = $headerIdx + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row))) continue;
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
        $areaRaw = $getVal('area');
        $mapLink = $getVal('map');

        $typeLower = strtolower($type);
        $priceLower = strtolower($priceRaw);
        if ($typeLower === 'club case' || $typeLower === 'club' || $priceLower === 'club' || $priceLower === 'club case' || empty($priceRaw) || !is_numeric(str_replace([',', ' '], '', $priceRaw))) {
            $skipCount++;
            $skipRows[] = "Row " . ($i + 1) . ": Skipped (Club/Invalid)";
            continue;
        }

        $price = extractNumber($priceRaw);
        if ($price <= 0) { $skipCount++; $skipRows[] = "Row " . ($i + 1) . ": Invalid Price"; continue; }

        $auctionDate = parseDate($dateRaw);
        if (!$auctionDate) { $skipCount++; $skipRows[] = "Row " . ($i + 1) . ": Invalid Date"; continue; }

        $normalizedType = normalizeType($type);
        $sqft = extractNumber($areaRaw);
        $title = $normalizedType . ' in ' . $location;
        if (!in_array($possession, ['Physical', 'Symbolic'])) $possession = 'Physical';
        $emd = round($price * 0.1, 2);
        $emdDeadline = date('d/m/Y 05:00 PM', strtotime($auctionDate . ' -1 day'));

        $description = '';
        if (!empty($mapLink) && strtolower($mapLink) !== 'na') $description = 'Location: ' . $mapLink;

        $csvRow = [
            $title, $address, $location, $state, '', $normalizedType, $bankName,
            $borrower, $price, '', $sqft, $possession, $emd, '', $emdDeadline,
            '', '', date('d/m/Y', strtotime($auctionDate)), '', '', 'available', $description
        ];

        $escaped = [];
        foreach ($csvRow as $val) {
            $val = (string)$val;
            if (strpos($val, ',') !== false || strpos($val, '"') !== false || strpos($val, "\n") !== false) {
                $escaped[] = '"' . str_replace('"', '""', $val) . '"';
            } else {
                $escaped[] = $val;
            }
        }
        $out .= implode(',', $escaped) . "\n";
        $validCount++;
    }

    return [$out, $validCount, $skipCount, $skipRows];
}

// ============================================================
// 🔥 HANDLE: Convert Tab (Download CSV)
// ============================================================
$convertReport = null;
$convertError = '';
$csvData = '';
$csvFileName = 'bulk_upload_ready.csv';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert') {
    try {
        $file = $_FILES['source_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception("File upload error");

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'xlsx') $rows = readXlsxFile($file['tmp_name'], 'MASTER');
        elseif ($ext === 'xls') throw new Exception("पुराना .xls Support नहीं है। Excel → Save As → .xlsx करें।");
        elseif (in_array($ext, ['csv', 'txt', 'tsv'])) $rows = readCsvFile($file['tmp_name']);
        else throw new Exception("Unsupported: .$ext");

        if (empty($rows)) throw new Exception("File में Data नहीं मिला");

        list($csvData, $validCount, $skipCount, $skipRows) = convertToBulk($rows, 'PNB Housing');
        if ($validCount === 0) throw new Exception("कोई Valid Property नहीं मिली");

        $convertReport = ['valid' => $validCount, 'skip' => $skipCount, 'skip_rows' => $skipRows];
        $csvFileName = 'bulk_upload_ready_' . date('Y-m-d_H-i-s') . '.csv';

    } catch (Exception $e) {
        $convertError = $e->getMessage();
    }
}

// ---- Direct Download ----
if (isset($_POST['download']) && $_POST['download'] === '1' && !empty($_POST['csv_content'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($_POST['filename'] ?? 'bulk_upload.csv') . '"');
    header('Pragma: no-cache');
    echo $_POST['csv_content'];
    exit;
}

// ============================================================
// 🔥 HANDLE: Upload Tab (Insert to DB)
// ============================================================
$uploadReport = null;
$uploadError = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    try {
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception("CSV upload error");

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) throw new Exception("Cannot read file");

        // Detect delimiter
        $firstLine = fgets($handle);
        rewind($handle);
        $tabCount = substr_count($firstLine, "\t");
        $commaCount = substr_count($firstLine, ',');
        $delimiter = ($tabCount > $commaCount) ? "\t" : ',';

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header && isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        $header = array_map('trim', $header);
        $headerLower = array_map('strtolower', $header);

        $required = ['title', 'city', 'price', 'auction_date'];
        foreach ($required as $col) {
            if (!in_array($col, $headerLower)) throw new Exception("Missing required column: $col");
        }

        $colMap = [];
        foreach ($headerLower as $i => $col) $colMap[$col] = $i;

        $successCount = 0;
        $skipCount = 0;
        $failCount = 0;
        $skipRows = [];
        $failRows = [];
        $rowNum = 1;

        $insertSQL = "INSERT INTO properties (
            title, description, price, location, city, state, type, bank_name,
            sqft, possession_type, borrower_name, emd_amount, bid_increment,
            emd_deadline, auction_start_time, auction_end_time, locality,
            reserve_price_per_sqft, contact_number, status, auction_date,
            inspection_date, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNum++;
            if (count(array_filter($row)) === 0) continue;
            $row = array_map('cleanUTF8', $row);

            $getVal = function($col) use ($row, $colMap) {
                return isset($colMap[$col]) && isset($row[$colMap[$col]]) ? $row[$colMap[$col]] : '';
            };

            $title = $getVal('title');
            $location = $getVal('location');
            $city = $getVal('city');
            $state = $getVal('state');
            $locality = $getVal('locality');
            $type = $getVal('type');
            $bank_name = $getVal('bank_name');
            $borrower_name = $getVal('borrower_name');
            $priceRaw = $getVal('price');
            $pricePerSqftRaw = $getVal('reserve_price_per_sqft');
            $sqftRaw = $getVal('sqft');
            $possession_type = $getVal('possession_type');
            $emdRaw = $getVal('emd_amount');
            $bidRaw = $getVal('bid_increment');
            $emd_deadline = $getVal('emd_deadline');
            $auction_start_time = $getVal('auction_start_time');
            $auction_end_time = $getVal('auction_end_time');
            $auction_date_raw = $getVal('auction_date');
            $inspection_date_raw = $getVal('inspection_date');
            $contact_number = $getVal('contact_number');
            $statusRaw = $getVal('status');
            $description = $getVal('description');

            if (isInvalidValue($priceRaw)) {
                $skipCount++;
                $skipRows[] = "Row $rowNum: Skipped (Club/Invalid)";
                continue;
            }

            $price = extractNumber($priceRaw);
            $pricePerSqft = extractNumber($pricePerSqftRaw);
            $sqft = extractNumber($sqftRaw);
            $emd_amount = extractNumber($emdRaw);
            $bid_increment = extractNumber($bidRaw);

            if (empty($title) || empty($city) || $price <= 0 || empty($auction_date_raw)) {
                $failCount++;
                $failRows[] = "Row $rowNum: Missing required";
                continue;
            }

            $auction_date = parseDate($auction_date_raw);
            if (!$auction_date) {
                $failCount++;
                $failRows[] = "Row $rowNum: Invalid auction_date";
                continue;
            }

            $type = normalizeType($type);
            $possession_type = in_array(strtolower($possession_type), ['physical', 'symbolic'])
                                ? ucfirst(strtolower($possession_type)) : 'Physical';
            $status = in_array(strtolower($statusRaw), ['available', 'sold', 'pending'])
                        ? strtolower($statusRaw) : 'available';

            $inspection_date = parseDate($inspection_date_raw);
            $auction_start_parsed = parseDateTimeFlexible($auction_start_time);
            $auction_end_parsed = parseDateTimeFlexible($auction_end_time);

            // EMD Deadline Auto (Auction Date − 1 Day, 5 PM)
            $emd_deadline_parsed = parseDateTimeFlexible($emd_deadline);
            if (empty($emd_deadline_parsed) && !empty($auction_date)) {
                $emd_deadline_parsed = date('Y-m-d 17:00:00', strtotime($auction_date . ' -1 day'));
            }

            try {
                $stmt = $pdo->prepare($insertSQL);
                $stmt->execute([
                    $title, $description, $price, $location, $city, $state, $type, $bank_name,
                    $sqft, $possession_type, $borrower_name, $emd_amount, $bid_increment,
                    $emd_deadline_parsed, $auction_start_parsed, $auction_end_parsed, $locality,
                    $pricePerSqft, $contact_number, $status, $auction_date, $inspection_date
                ]);
                $successCount++;
            } catch (PDOException $e) {
                $failCount++;
                $failRows[] = "Row $rowNum: " . cleanUTF8($e->getMessage());
            }
        }
        fclose($handle);

        $uploadReport = [
            'success' => $successCount,
            'skip' => $skipCount,
            'fail' => $failCount,
            'skip_rows' => $skipRows,
            'fail_rows' => $failRows,
        ];

    } catch (Exception $e) {
        $uploadError = $e->getMessage();
    }
}

include 'header.php';
?>

<style>
    .card-conv { background: #fff; border-radius: 24px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); border: 1px solid #e8edf4; margin-bottom: 24px; }
    .upload-area { border: 3px dashed #b8cbe8; border-radius: 20px; padding: 35px 20px; text-align: center; background: #f8faff; cursor: pointer; transition: all 0.3s; }
    .upload-area:hover { border-color: #2563eb; background: #eff6ff; }
    .upload-area i { font-size: 3rem; color: #2563eb; margin-bottom: 10px; }
    .btn-primary-custom { background: linear-gradient(135deg, #1e40af, #2563eb); color: #fff; border: none; padding: 14px 40px; border-radius: 50px; font-weight: 700; box-shadow: 0 6px 20px rgba(37,99,235,0.25); }
    .btn-primary-custom:hover { transform: translateY(-2px); color: #fff; }
    .btn-success-custom { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; padding: 14px 40px; border-radius: 50px; font-weight: 700; box-shadow: 0 6px 20px rgba(16,185,129,0.25); }
    .btn-success-custom:hover { transform: translateY(-2px); color: #fff; }
    .info-box { background: #eff6ff; border-left: 5px solid #2563eb; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; }
    .report-card { border-radius: 16px; padding: 20px; margin-bottom: 16px; }
    .report-success { background: #ecfdf5; border-left: 5px solid #10b981; }
    .report-warning { background: #fffbeb; border-left: 5px solid #f59e0b; }
    .report-error { background: #fef2f2; border-left: 5px solid #dc2626; }
    .step-badge { display: inline-block; background: #1e3a8a; color: #fff; padding: 6px 16px; border-radius: 30px; font-weight: 700; font-size: 0.85rem; margin-bottom: 10px; }
    .nav-pills-custom .nav-link { color: #475569; font-weight: 700; border-radius: 50px; padding: 12px 30px; }
    .nav-pills-custom .nav-link.active { background: linear-gradient(135deg, #1e40af, #2563eb); color: #fff; }
</style>

<div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-file-upload text-primary me-2"></i> Bulk Upload Properties</h1>

    <!-- Tabs -->
    <ul class="nav nav-pills nav-pills-custom mb-4">
        <li class="nav-item">
            <a class="nav-link <?= (!isset($_POST['action']) || $_POST['action'] === 'convert') ? 'active' : '' ?>" href="?tab=convert">
                <i class="fas fa-magic me-2"></i> Step 1: Convert Excel → CSV
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= (isset($_POST['action']) && $_POST['action'] === 'upload') ? 'active' : '' ?>" href="?tab=upload">
                <i class="fas fa-upload me-2"></i> Step 2: Upload CSV → Database
            </a>
        </li>
    </ul>

    <?php $activeTab = $_GET['tab'] ?? ((isset($_POST['action']) && $_POST['action'] === 'upload') ? 'upload' : 'convert'); ?>

    <?php if ($activeTab === 'convert'): ?>
        <!-- ==================== TAB 1: CONVERT ==================== -->
        <div class="info-box">
            <h6 class="fw-bold mb-2"><i class="fas fa-info-circle me-2"></i> Step 1: Excel को CSV में Convert करें</h6>
            <ul class="mb-0" style="font-size: 0.9rem;">
                <li>Excel File (.xlsx) या CSV Upload करें</li>
                <li>"Convert & Download CSV" बटन दबाएँ</li>
                <li>CSV Excel में खोलकर <strong>Verify करें</strong> – अगर कोई Column खाली है तो भरें</li>
                <li>फिर <strong>Step 2 Tab</strong> में जाकर CSV Upload करें</li>
            </ul>
        </div>

        <?php if ($convertError): ?>
            <div class="alert alert-danger"><strong>❌ Error:</strong> <?= htmlspecialchars($convertError) ?></div>
        <?php endif; ?>

        <?php if ($convertReport): ?>
            <div class="report-card report-success">
                <h5>✅ Conversion Successful!</h5>
                <p class="mb-1"><strong>Valid Properties:</strong> <span class="text-success fw-bold"><?= $convertReport['valid'] ?></span></p>
                <p class="mb-0"><strong>Skipped:</strong> <span class="text-warning fw-bold"><?= $convertReport['skip'] ?></span></p>
            </div>

            <?php if (!empty($convertReport['skip_rows'])): ?>
                <div class="report-card report-warning">
                    <h6>⚠️ Skipped Rows (पहली 15)</h6>
                    <ul class="mb-0" style="max-height: 200px; overflow-y: auto; font-size: 0.85rem;">
                        <?php foreach (array_slice($convertReport['skip_rows'], 0, 15) as $s): ?>
                            <li><?= htmlspecialchars($s) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card-conv text-center">
                <h5 class="fw-bold mb-3"><i class="fas fa-download me-2"></i> CSV Download करें</h5>
                <form method="POST">
                    <input type="hidden" name="download" value="1">
                    <input type="hidden" name="filename" value="<?= htmlspecialchars($csvFileName) ?>">
                    <input type="hidden" name="csv_content" value="<?= htmlspecialchars($csvData) ?>">
                    <button type="submit" class="btn-success-custom">
                        <i class="fas fa-download me-2"></i> Download Bulk Upload CSV
                    </button>
                </form>
                <p class="text-muted mt-3 mb-0" style="font-size: 0.85rem;">
                    CSV Download होने के बाद Excel में खोलें, Verify करें, फिर <strong>Step 2 Tab</strong> में Upload करें।
                </p>
            </div>

        <?php else: ?>
            <div class="card-conv">
                <form method="POST" enctype="multipart/form-data" id="convForm">
                    <input type="hidden" name="action" value="convert">
                    <label for="srcFile" class="upload-area d-block">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h5>Excel / CSV File चुनें</h5>
                        <p class="mb-0 text-muted">.xlsx / .csv / .tsv Supported</p>
                        <input type="file" name="source_file" id="srcFile" accept=".xlsx,.csv,.tsv,.txt" style="display:none;" required>
                        <div id="fileName" class="mt-3 fw-bold text-success"></div>
                    </label>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn-primary-custom">
                            <i class="fas fa-magic me-2"></i> Convert & Show Result
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ==================== TAB 2: UPLOAD ==================== -->
        <div class="info-box">
            <h6 class="fw-bold mb-2"><i class="fas fa-info-circle me-2"></i> Step 2: Verified CSV Upload करें</h6>
            <ul class="mb-0" style="font-size: 0.9rem;">
                <li>Step 1 से Download की हुई CSV File चुनें</li>
                <li>अगर Excel में Edit किया है तो <strong>Save as CSV UTF-8</strong> करें</li>
                <li>"Upload to Database" बटन दबाएँ</li>
            </ul>
        </div>

        <?php if ($uploadError): ?>
            <div class="alert alert-danger"><strong>❌ Error:</strong> <?= htmlspecialchars($uploadError) ?></div>
        <?php endif; ?>

        <?php if ($uploadReport): ?>
            <div class="report-card report-success">
                <h5>✅ Upload Completed!</h5>
                <p class="mb-1"><strong>Successfully Added:</strong> <span class="text-success fw-bold"><?= $uploadReport['success'] ?></span></p>
                <p class="mb-1"><strong>Skipped (Club/Invalid):</strong> <span class="text-warning fw-bold"><?= $uploadReport['skip'] ?></span></p>
                <p class="mb-0"><strong>Failed:</strong> <span class="text-danger fw-bold"><?= $uploadReport['fail'] ?></span></p>
            </div>

            <?php if (!empty($uploadReport['fail_rows'])): ?>
                <div class="report-card report-error">
                    <h6>❌ Failed Rows</h6>
                    <ul class="mb-0" style="max-height: 250px; overflow-y: auto; font-size: 0.85rem;">
                        <?php foreach (array_slice($uploadReport['fail_rows'], 0, 20) as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <a href="properties.php" class="btn btn-primary mb-3"><i class="fas fa-list me-2"></i> View All Properties</a>
            <a href="?tab=convert" class="btn btn-secondary mb-3"><i class="fas fa-redo me-2"></i> Convert Another File</a>
        <?php else: ?>
            <div class="card-conv">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload">
                    <label for="csvFile" class="upload-area d-block">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h5>Verified CSV File चुनें</h5>
                        <p class="mb-0 text-muted">.csv / .tsv Supported</p>
                        <input type="file" name="csv_file" id="csvFile" accept=".csv,.tsv,.txt" style="display:none;" required>
                        <div id="fileName2" class="mt-3 fw-bold text-success"></div>
                    </label>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn-primary-custom">
                            <i class="fas fa-upload me-2"></i> Upload to Database
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function setupUpload(areaSelector, inputId, fileNameId) {
        const area = document.querySelector(areaSelector);
        const input = document.getElementById(inputId);
        const fname = document.getElementById(fileNameId);
        if (!area || !input) return;

        area.addEventListener('click', function() { input.click(); });
        area.addEventListener('dragover', function(e) { e.preventDefault(); area.style.borderColor = '#10b981'; area.style.background = '#ecfdf5'; });
        area.addEventListener('dragleave', function(e) { e.preventDefault(); area.style.borderColor = '#b8cbe8'; area.style.background = '#f8faff'; });
        area.addEventListener('drop', function(e) {
            e.preventDefault();
            area.style.borderColor = '#b8cbe8';
            area.style.background = '#f8faff';
            if (e.dataTransfer.files.length > 0) {
                input.files = e.dataTransfer.files;
                if (fname) fname.innerHTML = '<i class="fas fa-check-circle"></i> ' + e.dataTransfer.files[0].name;
            }
        });
        input.addEventListener('change', function() {
            if (this.files.length > 0 && fname) fname.innerHTML = '<i class="fas fa-check-circle"></i> ' + this.files[0].name;
        });
    }

    setupUpload('#convForm .upload-area', 'srcFile', 'fileName');
    setupUpload('#uploadForm .upload-area', 'csvFile', 'fileName2');
});
</script>

<?php include 'footer.php'; ?>
