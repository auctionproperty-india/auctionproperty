<?php
// ============================================================
// 📤 Bulk Upload Properties – Excel (.xlsx) + CSV दोनों Support
// EMD Deadline = Auction Date − 1 Day (हमेशा)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'sub_admin');

// ============================================================
// HELPERS
// ============================================================
function cleanUTF8($str) {
    if ($str === null) return '';
    $str = (string)$str;
    if (!mb_check_encoding($str, 'UTF-8')) {
        $c = @iconv('Windows-1252', 'UTF-8//IGNORE', $str);
        $str = ($c !== false) ? $c : mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    }
    $rep = [
        "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
        "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
        "\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-',
        "\xE2\x80\xA6" => '...', "\xC2\xA0" => ' ',
    ];
    $str = str_replace(array_keys($rep), array_values($rep), $str);
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
    $dateStr = $parts[0];
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateStr, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $dateStr, $m)) {
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

function detectDelimiter($filepath) {
    $handle = fopen($filepath, 'r');
    $firstLine = fgets($handle);
    fclose($handle);
    if ($firstLine === false) return ',';
    return (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) ? "\t" : ',';
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
    if ($zip->open($filepath) !== true) throw new Exception("Cannot open XLSX");
    $ss = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $xml = @simplexml_load_string($ssXml);
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) $text = (string)$si->t;
                elseif (isset($si->r)) foreach ($si->r as $r) $text .= (string)$r->t;
                $ss[] = $text;
            }
        }
    }
    $sheetTarget = 'xl/worksheets/sheet1.xml';
    $wbXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbXml !== false && $relsXml !== false) {
        $wb = @simplexml_load_string($wbXml);
        $rels = @simplexml_load_string($relsXml);
        $rIdToTarget = [];
        if ($rels && isset($rels->Relationship)) foreach ($rels->Relationship as $rel) $rIdToTarget[(string)$rel['Id']] = (string)$rel['Target'];
        $found = false;
        if ($wb && isset($wb->sheets->sheet)) {
            foreach ($wb->sheets->sheet as $sheet) {
                $name = (string)$sheet['name'];
                $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                if (strtoupper($name) === strtoupper($preferredSheet) && isset($rIdToTarget[$rId])) {
                    $t = $rIdToTarget[$rId];
                    $sheetTarget = (strpos($t, 'xl/') === 0) ? $t : 'xl/' . ltrim($t, '/');
                    $found = true;
                    break;
                }
            }
        }
        if (!$found && $wb && isset($wb->sheets->sheet)) {
            $first = $wb->sheets->sheet[0];
            $rId = (string)$first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
            if (isset($rIdToTarget[$rId])) {
                $t = $rIdToTarget[$rId];
                $sheetTarget = (strpos($t, 'xl/') === 0) ? $t : 'xl/' . ltrim($t, '/');
            }
        }
    }
    $sheetXml = $zip->getFromName($sheetTarget);
    if ($sheetXml === false) $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) throw new Exception("Cannot read sheet");
    return parseSheetXml($sheetXml, $ss);
}

function readXlsxWithUnzip($filepath, $preferredSheet) {
    if (!function_exists('exec')) throw new Exception("ZipArchive और exec() unavailable");
    $tmp = sys_get_temp_dir() . '/xlsx_' . uniqid();
    @mkdir($tmp, 0755, true);
    exec("unzip -o " . escapeshellarg($filepath) . " -d " . escapeshellarg($tmp) . " 2>&1", $out, $ret);
    if ($ret !== 0) throw new Exception("unzip failed");
    $ss = [];
    $ssFile = $tmp . '/xl/sharedStrings.xml';
    if (file_exists($ssFile)) {
        $xml = @simplexml_load_string(file_get_contents($ssFile));
        if ($xml && isset($xml->si)) {
            foreach ($xml->si as $si) {
                $text = '';
                if (isset($si->t)) $text = (string)$si->t;
                elseif (isset($si->r)) foreach ($si->r as $r) $text .= (string)$r->t;
                $ss[] = $text;
            }
        }
    }
    $sheetFile = $tmp . '/xl/worksheets/sheet1.xml';
    $wbFile = $tmp . '/xl/workbook.xml';
    $relsFile = $tmp . '/xl/_rels/workbook.xml.rels';
    if (file_exists($wbFile) && file_exists($relsFile)) {
        $wb = @simplexml_load_string(file_get_contents($wbFile));
        $rels = @simplexml_load_string(file_get_contents($relsFile));
        $rIdToTarget = [];
        if ($rels && isset($rels->Relationship)) foreach ($rels->Relationship as $rel) $rIdToTarget[(string)$rel['Id']] = (string)$rel['Target'];
        if ($wb && isset($wb->sheets->sheet)) {
            foreach ($wb->sheets->sheet as $sheet) {
                $name = (string)$sheet['name'];
                $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                if (strtoupper($name) === strtoupper($preferredSheet) && isset($rIdToTarget[$rId])) {
                    $t = $rIdToTarget[$rId];
                    $sheetFile = (strpos($t, 'xl/') === 0) ? $tmp . '/' . $t : $tmp . '/xl/' . ltrim($t, '/');
                    break;
                }
            }
        }
    }
    if (!file_exists($sheetFile)) throw new Exception("Sheet not found");
    $sheetXml = file_get_contents($sheetFile);
    exec("rm -rf " . escapeshellarg($tmp));
    return parseSheetXml($sheetXml, $ss);
}

function parseSheetXml($sheetXml, $ss) {
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
            if ($type === 's') $value = $ss[(int)$value] ?? '';
            elseif ($type === 'inlineStr') {
                if (isset($cell->is->t)) $value = (string)$cell->is->t;
                elseif (isset($cell->is->r)) { $value = ''; foreach ($cell->is->r as $r) $value .= (string)$r->t; }
            }
            if (preg_match('/([A-Z]+)/', $ref, $m)) {
                $ci = colLetterToIndex($m[1]);
                $rowData[$ci] = $value;
                if ($ci > $maxCol) $maxCol = $ci;
            }
        }
        $full = [];
        for ($i = 0; $i <= $maxCol; $i++) $full[$i] = $rowData[$i] ?? '';
        $rows[] = $full;
    }
    return $rows;
}

// ============================================================
// 🔥 CSV ROWS READER
// ============================================================
function readCsvRows($filepath) {
    $handle = fopen($filepath, 'r');
    if (!$handle) throw new Exception("Cannot open file");
    $delim = detectDelimiter($filepath);
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delim)) !== false) $rows[] = $row;
    fclose($handle);
    return $rows;
}

// ============================================================
// 🔥 DETECT: Is this a "MASTER" PNB-style file, or a Bulk-format CSV?
// ============================================================
function isMasterFormat($rows) {
    // Look for known MASTER headers in first 10 rows
    foreach ($rows as $i => $row) {
        if ($i > 10) break;
        $joined = strtolower(implode(' ', $row));
        if ((strpos($joined, 's no') !== false || strpos($joined, 'sr') !== false) &&
            (strpos($joined, 'borrower') !== false || strpos($joined, 'auction') !== false)) {
            return true;
        }
    }
    return false;
}

// ============================================================
// 🔥 PROCESS MASTER (PNB Excel/CSV) → Insert into DB
// ============================================================
function processMaster($rows, $pdo) {
    $headerIdx = 0;
    foreach ($rows as $i => $row) {
        if ($i > 10) break;
        $j = strtolower(implode(' ', $row));
        if ((strpos($j, 's no') !== false || strpos($j, 'sr') !== false) &&
            (strpos($j, 'borrower') !== false || strpos($j, 'auction') !== false)) {
            $headerIdx = $i; break;
        }
    }
    $headerRow = $rows[$headerIdx] ?? [];
    $colMap = [];
    foreach ($headerRow as $idx => $col) {
        $c = strtolower(trim($col));
        if (strpos($c, 'borrower') !== false && !isset($colMap['borrower'])) $colMap['borrower'] = $idx;
        elseif ((strpos($c, 'reserved price') !== false || strpos($c, 'reserve price') !== false) && !isset($colMap['price'])) $colMap['price'] = $idx;
        elseif (strpos($c, 'auction date') !== false && !isset($colMap['auction_date'])) $colMap['auction_date'] = $idx;
        elseif ((strpos($c, 'property address') !== false || strpos($c, 'full address') !== false) && !isset($colMap['address'])) $colMap['address'] = $idx;
        elseif (strpos($c, 'location') !== false && !isset($colMap['location'])) $colMap['location'] = $idx;
        elseif (strpos($c, 'state') !== false && !isset($colMap['state'])) $colMap['state'] = $idx;
        elseif (strpos($c, 'possession') !== false && !isset($colMap['possession'])) $colMap['possession'] = $idx;
        elseif ((strpos($c, 'property type') !== false || strpos($c, 'nature of') !== false) && !isset($colMap['type'])) $colMap['type'] = $idx;
        elseif ((strpos($c, 'super area') !== false || strpos($c, 'covered area') !== false) && !isset($colMap['area'])) $colMap['area'] = $idx;
        elseif ((strpos($c, 'location map') !== false || $c === 'map') && !isset($colMap['map'])) $colMap['map'] = $idx;
    }
    $defaults = ['borrower'=>6,'price'=>7,'auction_date'=>8,'address'=>9,'location'=>10,'state'=>11,'possession'=>12,'type'=>13,'area'=>14,'map'=>26];
    foreach ($defaults as $k => $v) if (!isset($colMap[$k])) $colMap[$k] = $v;

    $success = 0; $skip = 0; $fail = 0;
    $skipRows = []; $failRows = [];

    $insertSQL = "INSERT INTO properties (
        title, description, price, location, city, state, type, bank_name,
        sqft, possession_type, borrower_name, emd_amount, bid_increment,
        emd_deadline, auction_start_time, auction_end_time, locality,
        reserve_price_per_sqft, contact_number, status, auction_date,
        inspection_date, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    for ($i = $headerIdx + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        if (empty(array_filter($row))) continue;
        $row = array_map('cleanUTF8', $row);

        $get = function($k) use ($row, $colMap) {
            return isset($colMap[$k]) && isset($row[$colMap[$k]]) ? $row[$colMap[$k]] : '';
        };

        $borrower = $get('borrower');
        $priceRaw = $get('price');
        $dateRaw = $get('auction_date');
        $address = $get('address');
        $location = $get('location');
        $state = $get('state');
        $possession = $get('possession');
        $type = $get('type');
        $areaRaw = $get('area');
        $mapLink = $get('map');

        $tL = strtolower($type);
        $pL = strtolower($priceRaw);
        if ($tL === 'club case' || $tL === 'club' || $pL === 'club' || $pL === 'club case' || empty($priceRaw) || !is_numeric(str_replace([',', ' '], '', $priceRaw))) {
            $skip++;
            $skipRows[] = "Row " . ($i + 1) . ": Skipped (Club/Invalid)";
            continue;
        }

        $price = extractNumber($priceRaw);
        if ($price <= 0) { $skip++; continue; }

        $auctionDate = parseDate($dateRaw);
        if (!$auctionDate) { $skip++; $skipRows[] = "Row " . ($i + 1) . ": Invalid Date"; continue; }

        $normalizedType = normalizeType($type);
        $sqft = extractNumber($areaRaw);
        $title = $normalizedType . ' in ' . $location;
        if (!in_array($possession, ['Physical', 'Symbolic'])) $possession = 'Physical';
        $emd = round($price * 0.1, 2);

        // 🔥 EMD DEADLINE = Auction Date − 1 Day (5:00 PM)
        $emdDeadline = $auctionDate . ' 17:00:00';

        $desc = (!empty($mapLink) && strtolower($mapLink) !== 'na') ? 'Location: ' . $mapLink : '';

        try {
            $stmt = $pdo->prepare($insertSQL);
            $stmt->execute([
                $title, $desc, $price, $address, $location, $state, $normalizedType, 'PNB Housing',
                $sqft, $possession, $borrower, $emd, 0, $emdDeadline, null, null, '',
                0, '', 'available', $auctionDate, null
            ]);
            $success++;
        } catch (PDOException $e) {
            $fail++;
            $failRows[] = "Row " . ($i + 1) . ": " . cleanUTF8($e->getMessage());
        }
    }

    return ['success'=>$success, 'skip'=>$skip, 'fail'=>$fail, 'skip_rows'=>$skipRows, 'fail_rows'=>$failRows];
}

// ============================================================
// 🔥 PROCESS BULK-FORMAT CSV → Insert into DB
// ============================================================
function processBulkCsv($filepath, $pdo) {
    $delim = detectDelimiter($filepath);
    $handle = fopen($filepath, 'r');
    if (!$handle) throw new Exception("Cannot read file");

    $header = fgetcsv($handle, 0, $delim);
    if ($header && isset($header[0])) $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
    $header = array_map('trim', $header);
    $headerLower = array_map('strtolower', $header);

    $required = ['title', 'city', 'price', 'auction_date'];
    foreach ($required as $col) {
        if (!in_array($col, $headerLower)) {
            fclose($handle);
            throw new Exception("Missing required column: $col");
        }
    }

    $colMap = [];
    foreach ($headerLower as $i => $col) $colMap[$col] = $i;

    $success = 0; $skip = 0; $fail = 0;
    $skipRows = []; $failRows = [];
    $rowNum = 1;

    $insertSQL = "INSERT INTO properties (
        title, description, price, location, city, state, type, bank_name,
        sqft, possession_type, borrower_name, emd_amount, bid_increment,
        emd_deadline, auction_start_time, auction_end_time, locality,
        reserve_price_per_sqft, contact_number, status, auction_date,
        inspection_date, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    while (($row = fgetcsv($handle, 0, $delim)) !== false) {
        $rowNum++;
        if (count(array_filter($row)) === 0) continue;
        $row = array_map('cleanUTF8', $row);

        $getVal = function($col) use ($row, $colMap) {
            return isset($colMap[$col]) && isset($row[$colMap[$col]]) ? $row[$colMap[$col]] : '';
        };

        $title = $getVal('title');
        $priceRaw = $getVal('price');
        $city = $getVal('city');
        $auction_date_raw = $getVal('auction_date');

        if (isInvalidValue($priceRaw)) {
            $skip++;
            $skipRows[] = "Row $rowNum: Skipped (invalid price)";
            continue;
        }

        $price = extractNumber($priceRaw);
        if (empty($title) || empty($city) || $price <= 0 || empty($auction_date_raw)) {
            $fail++;
            $failRows[] = "Row $rowNum: Missing required fields";
            continue;
        }

        $auction_date = parseDate($auction_date_raw);
        if (!$auction_date) {
            $fail++;
            $failRows[] = "Row $rowNum: Invalid date";
            continue;
        }

        // 🔥 EMD DEADLINE = Auction Date − 1 Day (5:00 PM)
        $emd_deadline_parsed = date('Y-m-d 17:00:00', strtotime($auction_date . ' -1 day'));

        $auction_start_parsed = parseDateTimeFlexible($getVal('auction_start_time'));
        $auction_end_parsed = parseDateTimeFlexible($getVal('auction_end_time'));
        $inspection_date = parseDate($getVal('inspection_date'));

        $type = normalizeType($getVal('type'));
        $possession_type = in_array(strtolower($getVal('possession_type')), ['physical', 'symbolic'])
                            ? ucfirst(strtolower($getVal('possession_type'))) : 'Physical';
        $statusRaw = $getVal('status');
        $status = in_array(strtolower($statusRaw), ['available', 'sold', 'pending'])
                    ? strtolower($statusRaw) : 'available';

        try {
            $stmt = $pdo->prepare($insertSQL);
            $stmt->execute([
                $title, $getVal('description'), $price, $getVal('location'), $city, $getVal('state'),
                $type, $getVal('bank_name'), extractNumber($getVal('sqft')), $possession_type,
                $getVal('borrower_name'), extractNumber($getVal('emd_amount')), extractNumber($getVal('bid_increment')),
                $emd_deadline_parsed, $auction_start_parsed, $auction_end_parsed, $getVal('locality'),
                extractNumber($getVal('reserve_price_per_sqft')), $getVal('contact_number'),
                $status, $auction_date, $inspection_date
            ]);
            $success++;
        } catch (PDOException $e) {
            $fail++;
            $failRows[] = "Row $rowNum: " . cleanUTF8($e->getMessage());
        }
    }
    fclose($handle);

    return ['success'=>$success, 'skip'=>$skip, 'fail'=>$fail, 'skip_rows'=>$skipRows, 'fail_rows'=>$failRows];
}

// ============================================================
// HANDLE UPLOAD
// ============================================================
$report = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error (Code: {$file['error']})";
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        try {
            if ($ext === 'xlsx') {
                // Excel File
                $rows = readXlsxFile($file['tmp_name'], 'MASTER');
                $report = processMaster($rows, $pdo);
                $report['type'] = 'Excel (MASTER)';

            } elseif ($ext === 'xls') {
                $errors[] = "पुराना .xls Support नहीं है। Excel में File खोलें → Save As → .xlsx करें।";

            } elseif (in_array($ext, ['csv', 'txt', 'tsv'])) {
                // CSV File – Check if it's MASTER format or Bulk format
                $rows = readCsvRows($file['tmp_name']);

                if (isMasterFormat($rows)) {
                    $report = processMaster($rows, $pdo);
                    $report['type'] = 'CSV (MASTER)';
                } else {
                    $report = processBulkCsv($file['tmp_name'], $pdo);
                    $report['type'] = 'CSV (Bulk Format)';
                }
            } else {
                $errors[] = "Unsupported file type: .$ext (केवल .xlsx, .csv, .tsv)";
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

include 'header.php';
?>

<style>
    .upload-card { background: #fff; border-radius: 24px; padding: 32px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); border: 1px solid #e8edf4; }
    .upload-area { border: 3px dashed #b8cbe8; border-radius: 20px; padding: 40px 20px; text-align: center; background: #f8faff; transition: all 0.3s; cursor: pointer; }
    .upload-area:hover { border-color: #2563eb; background: #eff6ff; }
    .upload-area i { font-size: 3.5rem; color: #2563eb; margin-bottom: 12px; }
    .btn-upload { background: linear-gradient(135deg, #1e40af, #2563eb); color: #fff; border: none; padding: 14px 40px; border-radius: 50px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 6px 20px rgba(37,99,235,0.25); transition: all 0.3s; }
    .btn-upload:hover { transform: translateY(-2px); color: #fff; }
    .btn-download { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #0f172a; border: none; padding: 12px 32px; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; box-shadow: 0 6px 20px rgba(251,191,36,0.25); transition: all 0.3s; }
    .btn-download:hover { transform: translateY(-2px); color: #0f172a; }
    .info-box { background: #eff6ff; border-left: 5px solid #2563eb; border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; }
    .report-card { border-radius: 16px; padding: 22px; margin-bottom: 20px; }
    .report-success { background: #ecfdf5; border-left: 5px solid #10b981; }
    .report-warning { background: #fffbeb; border-left: 5px solid #f59e0b; }
    .report-error { background: #fef2f2; border-left: 5px solid #dc2626; }
    .report-card h5 { font-weight: 700; margin-bottom: 8px; }
</style>

<div class="container-fluid mt-4">
    <h1 class="mb-4"><i class="fas fa-file-excel text-success me-2"></i> Bulk Upload Properties</h1>

    <?php if ($report): ?>
        <div class="report-card report-success">
            <h5>✅ Upload Completed! <small class="text-muted">(Type: <?= $report['type'] ?>)</small></h5>
            <p class="mb-1"><strong>Successfully Added:</strong> <span class="text-success fw-bold"><?= $report['success'] ?></span></p>
            <p class="mb-1"><strong>Skipped (Club/Invalid):</strong> <span class="text-warning fw-bold"><?= $report['skip'] ?></span></p>
            <p class="mb-0"><strong>Failed:</strong> <span class="text-danger fw-bold"><?= $report['fail'] ?></span></p>
        </div>

        <?php if (!empty($report['skip_rows'])): ?>
            <div class="report-card report-warning">
                <h5>⚠️ Skipped Rows (पहली 30)</h5>
                <ul class="mb-0" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach (array_slice($report['skip_rows'], 0, 30) as $s): ?>
                        <li><?= htmlspecialchars($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($report['fail_rows'])): ?>
            <div class="report-card report-error">
                <h5>❌ Failed Rows (पहली 30)</h5>
                <ul class="mb-0" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach (array_slice($report['fail_rows'], 0, 30) as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <a href="properties.php" class="btn btn-primary mb-4"><i class="fas fa-list me-2"></i> View All Properties</a>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?><div><?= $e ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        <h6><i class="fas fa-shield-alt me-2"></i> 🔥 Features</h6>
        <ul class="mb-0" style="font-size: 0.9rem;">
            <li><strong>Direct .xlsx Upload</strong> – Excel File सीधे डालें</li>
            <li><strong>CSV भी Support</strong> – Bulk Format या MASTER Format दोनों</li>
            <li><strong style="color: #dc2626;">EMD Deadline = Auction Date − 1 Day (5:00 PM) हमेशा Auto-Set</strong></li>
            <li>Club Case Rows Auto-Skip होंगी</li>
            <li>UTF-8 Safe – Smart Quotes Auto-Fix</li>
        </ul>
    </div>

    <div class="text-center mb-4">
        <a href="convert_excel.php" class="btn-download">
            <i class="fas fa-magic me-2"></i> Excel → Bulk CSV Converter
        </a>
        <a href="download_template.php" class="btn-download ms-2">
            <i class="fas fa-download me-2"></i> Download Sample CSV
        </a>
    </div>

    <div class="upload-card">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" id="uploadArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <h4>Drag & Drop Excel / CSV File Here</h4>
                <p>या क्लिक करके फ़ाइल चुनें</p>
                <input type="file" name="csv_file" id="csvFile" accept=".xlsx,.xls,.csv,.txt,.tsv" style="display:none;" required>
                <div id="fileName" class="mt-3 fw-bold text-success"></div>
            </div>
            <div class="text-center mt-4">
                <button type="submit" class="btn-upload">
                    <i class="fas fa-upload me-2"></i> Upload Properties
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const csvFile = document.getElementById('csvFile');
    const fileName = document.getElementById('fileName');

    uploadArea.addEventListener('click', function() { csvFile.click(); });
    uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); uploadArea.style.borderColor = '#10b981'; uploadArea.style.background = '#ecfdf5'; });
    uploadArea.addEventListener('dragleave', function(e) { e.preventDefault(); uploadArea.style.borderColor = '#b8cbe8'; uploadArea.style.background = '#f8faff'; });
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.style.borderColor = '#b8cbe8';
        uploadArea.style.background = '#f8faff';
        if (e.dataTransfer.files.length > 0) {
            csvFile.files = e.dataTransfer.files;
            fileName.innerHTML = '<i class="fas fa-check-circle"></i> ' + e.dataTransfer.files[0].name;
        }
    });
    csvFile.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileName.innerHTML = '<i class="fas fa-check-circle"></i> ' + this.files[0].name;
        }
    });
});
</script>

<?php include 'footer.php'; ?>
