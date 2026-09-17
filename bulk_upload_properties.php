<?php
// ============================================================
// 📤 Bulk Upload Properties – 2 Tabs (Convert + Upload)
// Pure PHP XLSX Reader + Auto Start/End Date + Retry on Error
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
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $dateStr, $m)) {
        // 24/9/2026 – पहला नंबर > 12 तो Day है
        $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
        if ($d > 12 && $mo <= 12) {
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        // dd/mm/yyyy default
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $dateStr, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    $ts = strtotime($dateStr);
    if ($ts !== false && $ts > 0) return date('Y-m-d', $ts);
    return null;
}

// 🔥 ROBUST DateTime Parser
function parseDateTimeRobust($str) {
    if (empty($str) || trim($str) === '') return null;
    $str = trim($str);
    if (in_array(strtolower($str), ['#value!', 'na', 'n/a', 'null', '-', 'club', 'club case'])) return null;

    // Try DD/MM/YYYY HH:MM or DD/MM/YYYY
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})(?:\s+(.+))?$/', $str, $m)) {
        $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
        if ($y < 100) $y += 2000;

        // 24/9/2026 → d=24, mo=9 → Day first
        if ($d > 12 && $mo <= 12) {
            // OK day first
        } elseif ($mo > 12 && $d <= 12) {
            // Month first
            $tmp = $d; $d = $mo; $mo = $tmp;
        }
        if (!checkdate($mo, $d, $y)) return null;

        $h = 0; $i = 0; $s = 0;
        if (!empty($m[4])) {
            $timePart = trim($m[4]);
            // HH:MM AM/PM
            if (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?\s*(AM|PM|am|pm)$/', $timePart, $t)) {
                $h = (int)$t[1]; $i = (int)$t[2];
                $s = isset($t[3]) && $t[3] !== '' ? (int)$t[3] : 0;
                $ampm = strtoupper($t[4]);
                if ($ampm === 'PM' && $h < 12) $h += 12;
                if ($ampm === 'AM' && $h == 12) $h = 0;
            }
            // HH:MM
            elseif (preg_match('/^(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?/', $timePart, $t)) {
                $h = (int)$t[1]; $i = (int)$t[2];
                $s = isset($t[3]) && $t[3] !== '' ? (int)$t[3] : 0;
            }
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $i, $s);
    }

    // ISO format
    if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})(?:[\sT](\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/', $str, $m)) {
        $y = (int)$m[1]; $mo = (int)$m[2]; $d = (int)$m[3];
        $h = isset($m[4]) ? (int)$m[4] : 0;
        $i = isset($m[5]) ? (int)$m[5] : 0;
        $s = isset($m[6]) ? (int)$m[6] : 0;
        if (!checkdate($mo, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $mo, $d, $h, $i, $s);
    }

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
// PURE PHP ZIP READER
// ============================================================
function readZipEntry($zipFile, $entryName) {
    $data = @file_get_contents($zipFile);
    if ($data === false) return false;
    $len = strlen($data);
    $eocdPos = -1;
    $searchStart = max(0, $len - 65558);
    for ($i = $len - 22; $i >= $searchStart; $i--) {
        if (substr($data, $i, 4) === "PK\x05\x06") { $eocdPos = $i; break; }
    }
    if ($eocdPos === -1) return false;
    $cdCount = unpack('v', substr($data, $eocdPos + 10, 2))[1];
    $cdOffset = unpack('V', substr($data, $eocdPos + 16, 4))[1];
    if ($cdOffset >= $len) return false;
    $pos = $cdOffset;
    for ($i = 0; $i < $cdCount; $i++) {
        if ($pos + 46 > $len) return false;
        if (substr($data, $pos, 4) !== "PK\x01\x02") return false;
        $compressionMethod = unpack('v', substr($data, $pos + 10, 2))[1];
        $compressedSize = unpack('V', substr($data, $pos + 20, 4))[1];
        $fileNameLen = unpack('v', substr($data, $pos + 28, 2))[1];
        $extraLen = unpack('v', substr($data, $pos + 30, 2))[1];
        $commentLen = unpack('v', substr($data, $pos + 32, 2))[1];
        $localHeaderOffset = unpack('V', substr($data, $pos + 42, 4))[1];
        $fileName = substr($data, $pos + 46, $fileNameLen);
        if ($fileName === $entryName) {
            if ($localHeaderOffset + 30 > $len) return false;
            if (substr($data, $localHeaderOffset, 4) !== "PK\x03\x04") return false;
            $localFileNameLen = unpack('v', substr($data, $localHeaderOffset + 26, 2))[1];
            $localExtraLen = unpack('v', substr($data, $localHeaderOffset + 28, 2))[1];
            $dataStart = $localHeaderOffset + 30 + $localFileNameLen + $localExtraLen;
            if ($dataStart + $compressedSize > $len) return false;
            $compressedData = substr($data, $dataStart, $compressedSize);
            if ($compressionMethod === 0) return $compressedData;
            if ($compressionMethod === 8) {
                $out = @gzinflate($compressedData);
                if ($out === false) $out = @gzinflate(substr($compressedData, 2));
                return $out;
            }
            return false;
        }
        $pos += 46 + $fileNameLen + $extraLen + $commentLen;
    }
    return false;
}

function colLetterToIndex($letters) {
    $letters = strtoupper($letters);
    $num = 0;
    for ($i = 0; $i < strlen($letters); $i++) $num = $num * 26 + (ord($letters[$i]) - 64);
    return $num - 1;
}

function readXlsxFile($filepath, $preferredSheet = 'MASTER') {
    $ssXml = readZipEntry($filepath, 'xl/sharedStrings.xml');
    $ss = [];
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
    $wbXml = readZipEntry($filepath, 'xl/workbook.xml');
    $relsXml = readZipEntry($filepath, 'xl/_rels/workbook.xml.rels');
    if ($wbXml !== false && $relsXml !== false) {
        $wb = @simplexml_load_string($wbXml);
        $rels = @simplexml_load_string($relsXml);
        $rIdToTarget = [];
        if ($rels && isset($rels->Relationship)) {
            foreach ($rels->Relationship as $rel) $rIdToTarget[(string)$rel['Id']] = (string)$rel['Target'];
        }
        $found = false;
        if ($wb && isset($wb->sheets->sheet)) {
            foreach ($wb->sheets->sheet as $sheet) {
                $name = (string)$sheet['name'];
                $rId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
                if (strtoupper($name) === strtoupper($preferredSheet) && isset($rIdToTarget[$rId])) {
                    $t = $rIdToTarget[$rId];
                    $sheetTarget = (strpos($t, 'xl/') === 0) ? $t : 'xl/' . ltrim($t, '/');
                    $found = true; break;
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
    $sheetXml = readZipEntry($filepath, $sheetTarget);
    if ($sheetXml === false) $sheetXml = readZipEntry($filepath, 'xl/worksheets/sheet1.xml');
    if ($sheetXml === false) throw new Exception("Cannot read sheet");
    return parseSheetXml($sheetXml, $ss);
}

function parseSheetXml($sheetXml, $ss) {
    $xml = @simplexml_load_string($sheetXml);
    if (!$xml || !isset($xml->sheetData->row)) throw new Exception("Cannot parse sheet");
    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = []; $maxCol = -1;
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r']; $type = (string)$cell['t'];
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

function readCsvRows($filepath) {
    $handle = fopen($filepath, 'r');
    if (!$handle) throw new Exception("Cannot open file");
    $delim = detectDelimiter($filepath);
    $rows = [];
    while (($row = fgetcsv($handle, 0, $delim)) !== false) $rows[] = $row;
    fclose($handle);
    return $rows;
}

function isMasterFormat($rows) {
    foreach ($rows as $i => $row) {
        if ($i > 10) break;
        $j = strtolower(implode(' ', $row));
        if ((strpos($j, 's no') !== false || strpos($j, 'sr') !== false) &&
            (strpos($j, 'borrower') !== false || strpos($j, 'auction') !== false)) {
            return true;
        }
    }
    return false;
}

// ============================================================
// CONVERT MASTER → BULK CSV
// ============================================================
function convertMasterToBulkCsv($rows, $bankName = 'PNB Housing') {
    if (empty($rows)) return [null, 0, 0, []];

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

    $out = "\xEF\xBB\xBF";
    $out .= implode(',', [
        'title', 'location', 'city', 'state', 'locality', 'type', 'bank_name',
        'borrower_name', 'price', 'reserve_price_per_sqft', 'sqft', 'possession_type',
        'emd_amount', 'bid_increment', 'emd_deadline', 'auction_start_time',
        'auction_end_time', 'auction_date', 'inspection_date', 'contact_number',
        'status', 'description'
    ]) . "\n";

    $validCount = 0; $skipCount = 0; $skipRows = [];

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
            $skipCount++;
            $skipRows[] = "Row " . ($i + 1) . ": Skipped (Club/Invalid)";
            continue;
        }

        $price = extractNumber($priceRaw);
        if ($price <= 0) { $skipCount++; continue; }

        $auctionDate = parseDate($dateRaw);
        if (!$auctionDate) { $skipCount++; $skipRows[] = "Row " . ($i + 1) . ": Invalid Date"; continue; }

        $normalizedType = normalizeType($type);
        $sqft = extractNumber($areaRaw);
        $title = $normalizedType . ' in ' . $location;
        if (!in_array($possession, ['Physical', 'Symbolic'])) $possession = 'Physical';
        $emd = round($price * 0.1, 2);

        // 🔥 EMD DEADLINE = Auction Date − 1 Day
        $emdDeadline = date('d/m/Y 05:00 PM', strtotime($auctionDate . ' -1 day'));
        $auctionDateFormatted = date('d/m/Y', strtotime($auctionDate));

        // 🔥 Auction Start = Same as Auction Date, 11 AM
        // 🔥 Auction End = Same as Auction Date, 02 PM
        $auctionStartFormatted = date('d/m/Y 11:00 AM', strtotime($auctionDate));
        $auctionEndFormatted = date('d/m/Y 02:00 PM', strtotime($auctionDate));

        $desc = (!empty($mapLink) && strtolower($mapLink) !== 'na') ? 'Location: ' . $mapLink : '';

        $csvRow = [
            $title, $address, $location, $state, '', $normalizedType, $bankName,
            $borrower, $price, '', $sqft, $possession, $emd, '', $emdDeadline,
            $auctionStartFormatted, $auctionEndFormatted, $auctionDateFormatted, '', '', 'available', $desc
        ];

        $escaped = [];
        foreach ($csvRow as $v) {
            $v = (string)$v;
            $escaped[] = (strpos($v, ',') !== false || strpos($v, '"') !== false || strpos($v, "\n") !== false)
                ? '"' . str_replace('"', '""', $v) . '"' : $v;
        }
        $out .= implode(',', $escaped) . "\n";
        $validCount++;
    }

    return [$out, $validCount, $skipCount, $skipRows];
}

// ============================================================
// PROCESS BULK CSV → INSERT INTO DB (with Retry)
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
        if (!in_array($col, $headerLower)) { fclose($handle); throw new Exception("Missing required column: $col"); }
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

        // 🔥 EMD = Auction Date − 1 Day (5 PM)
        $emd_deadline_parsed = date('Y-m-d 17:00:00', strtotime($auction_date . ' -1 day'));

        // 🔥 Auction Start / End – Parse or Auto-Set from Auction Date
        $auction_start_parsed = parseDateTimeRobust($getVal('auction_start_time'));
        $auction_end_parsed = parseDateTimeRobust($getVal('auction_end_time'));

        // Fallback: If NULL, use Auction Date with default times
        if (empty($auction_start_parsed)) {
            $auction_start_parsed = $auction_date . ' 11:00:00';
        }
        if (empty($auction_end_parsed)) {
            $auction_end_parsed = $auction_date . ' 14:00:00';
        }

        $inspection_date = parseDate($getVal('inspection_date'));
        $type = normalizeType($getVal('type'));
        $possession_type = in_array(strtolower($getVal('possession_type')), ['physical', 'symbolic'])
                            ? ucfirst(strtolower($getVal('possession_type'))) : 'Physical';
        $statusRaw = $getVal('status');
        $status = in_array(strtolower($statusRaw), ['available', 'sold', 'pending'])
                    ? strtolower($statusRaw) : 'available';

        // 🔥 Retry mechanism for Supabase PDO issues
        $success_flag = false;
        $lastError = '';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
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
                $success_flag = true;
                break;
            } catch (PDOException $e) {
                $lastError = $e->getMessage();
                usleep(100000); // 0.1 sec wait before retry
            }
        }
        if (!$success_flag) {
            $fail++;
            $failRows[] = "Row $rowNum: " . cleanUTF8($lastError);
        }
    }
    fclose($handle);

    return ['success'=>$success, 'skip'=>$skip, 'fail'=>$fail, 'skip_rows'=>$skipRows, 'fail_rows'=>$failRows];
}

// ============================================================
// HANDLE: FIX EXISTING NULL START/END DATES
// ============================================================
$fixReport = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_existing'])) {
    try {
        $c1 = $pdo->exec("UPDATE properties SET auction_start_time = auction_date WHERE auction_start_time IS NULL AND auction_date IS NOT NULL");
        $c2 = $pdo->exec("UPDATE properties SET auction_end_time = auction_date WHERE auction_end_time IS NULL AND auction_date IS NOT NULL");
        $fixReport = ['start' => $c1, 'end' => $c2];
    } catch (PDOException $e) {
        $fixReport = ['error' => $e->getMessage()];
    }
}

// ============================================================
// HANDLE: TAB 1 (Convert + Download)
// ============================================================
$convertReport = null; $convertError = ''; $csvData = ''; $csvFileName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert') {
    try {
        $file = $_FILES['source_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception("File upload error");
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext === 'xlsx') $rows = readXlsxFile($file['tmp_name'], 'MASTER');
        elseif ($ext === 'xls') throw new Exception("पुराना .xls Support नहीं – Excel → Save As → .xlsx करें");
        elseif (in_array($ext, ['csv', 'txt', 'tsv'])) $rows = readCsvRows($file['tmp_name']);
        else throw new Exception("Unsupported: .$ext");

        if (empty($rows)) throw new Exception("File में Data नहीं मिला");

        list($csvData, $validCount, $skipCount, $skipRows) = convertMasterToBulkCsv($rows, 'PNB Housing');
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
// HANDLE: TAB 2 (Upload to DB)
// ============================================================
$uploadReport = null; $uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    try {
        $file = $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception("CSV upload error");
        $uploadReport = processBulkCsv($file['tmp_name'], $pdo);
    } catch (Exception $e) {
        $uploadError = $e->getMessage();
    }
}

$activeTab = $_GET['tab'] ?? 'convert';
if (isset($_POST['action']) && $_POST['action'] === 'upload') $activeTab = 'upload';

include 'header.php';
?>

<style>
    .nav-pills-custom .nav-link { color: #475569; font-weight: 700; border-radius: 50px; padding: 12px 30px; margin-right: 8px; }
    .nav-pills-custom .nav-link.active { background: linear-gradient(135deg, #1e40af, #2563eb); color: #fff; }
    .card-conv { background: #fff; border-radius: 24px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); border: 1px solid #e8edf4; margin-bottom: 24px; }
    .upload-area { border: 3px dashed #b8cbe8; border-radius: 20px; padding: 40px 20px; text-align: center; background: #f8faff; cursor: pointer; transition: all 0.3s; }
    .upload-area:hover { border-color: #2563eb; background: #eff6ff; }
    .upload-area i { font-size: 3.5rem; color: #2563eb; margin-bottom: 12px; }
    .btn-primary-custom { background: linear-gradient(135deg, #1e40af, #2563eb); color: #fff; border: none; padding: 14px 40px; border-radius: 50px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 6px 20px rgba(37,99,235,0.25); transition: all 0.3s; }
    .btn-primary-custom:hover { transform: translateY(-2px); color: #fff; }
    .btn-success-custom { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; padding: 14px 40px; border-radius: 50px; font-weight: 700; font-size: 1.1rem; box-shadow: 0 6px 20px rgba(16,185,129,0.25); transition: all 0.3s; }
    .btn-success-custom:hover { transform: translateY(-2px); color: #fff; }
    .btn-warning-custom { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; padding: 12px 30px; border-radius: 50px; font-weight: 700; box-shadow: 0 6px 20px rgba(245,158,11,0.25); }
    .btn-warning-custom:hover { transform: translateY(-2px); color: #fff; }
    .info-box { background: #eff6ff; border-left: 5px solid #2563eb; border-radius: 12px; padding: 18px 22px; margin-bottom: 24px; }
    .report-card { border-radius: 16px; padding: 20px; margin-bottom: 16px; }
    .report-success { background: #ecfdf5; border-left: 5px solid #10b981; }
    .report-warning { background: #fffbeb; border-left: 5px solid #f59e0b; }
    .report-error { background: #fef2f2; border-left: 5px solid #dc2626; }
</style>

<div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-file-excel text-success me-2"></i> Bulk Upload Properties</h1>

    <!-- Fix Existing Properties Panel -->
    <?php if ($fixReport): ?>
        <?php if (isset($fixReport['error'])): ?>
            <div class="alert alert-danger">❌ Fix Error: <?= htmlspecialchars($fixReport['error']) ?></div>
        <?php else: ?>
            <div class="alert alert-success">
                ✅ <strong>Fix Applied!</strong>
                Start Times Updated: <strong><?= $fixReport['start'] ?></strong> |
                End Times Updated: <strong><?= $fixReport['end'] ?></strong>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <ul class="nav nav-pills nav-pills-custom mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'convert' ? 'active' : '' ?>" href="?tab=convert">
                <i class="fas fa-magic me-2"></i> Step 1: Convert + Download
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'upload' ? 'active' : '' ?>" href="?tab=upload">
                <i class="fas fa-upload me-2"></i> Step 2: Upload to Database
            </a>
        </li>
    </ul>

    <?php if ($activeTab === 'convert'): ?>
        <!-- ==================== TAB 1: CONVERT ==================== -->
        <div class="info-box">
            <h6 class="fw-bold mb-2"><i class="fas fa-info-circle me-2"></i> Step 1: Excel को CSV में Convert करें</h6>
            <ol class="mb-0" style="font-size: 0.9rem;">
                <li>Excel File (.xlsx) या CSV Upload करें</li>
                <li>"Convert" बटन दबाएँ</li>
                <li>CSV Download करें – Excel में खोलकर <strong>Verify करें</strong></li>
                <li>फिर <strong>Step 2 Tab</strong> में जाकर Upload करें</li>
            </ol>
        </div>

        <?php if ($convertError): ?>
            <div class="alert alert-danger"><strong>❌ Error:</strong> <?= htmlspecialchars($convertError) ?></div>
        <?php endif; ?>

        <?php if ($convertReport): ?>
            <div class="report-card report-success">
                <h5>✅ Conversion Successful!</h5>
                <p class="mb-1"><strong>Valid Properties:</strong> <span class="text-success fw-bold"><?= $convertReport['valid'] ?></span></p>
                <p class="mb-0"><strong>Skipped (Club/Invalid):</strong> <span class="text-warning fw-bold"><?= $convertReport['skip'] ?></span></p>
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
                        <input type="file" name="source_file" id="srcFile" style="display:none;" required>
                        <div id="fileName" class="mt-3 fw-bold text-success"></div>
                    </label>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn-primary-custom">
                            <i class="fas fa-magic me-2"></i> Convert to Bulk CSV
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ==================== TAB 2: UPLOAD ==================== -->
        <div class="info-box">
            <h6 class="fw-bold mb-2"><i class="fas fa-info-circle me-2"></i> Step 2: Verified CSV Upload करें</h6>
            <ol class="mb-0" style="font-size: 0.9rem;">
                <li>Step 1 से Download की हुई (Verified) CSV File चुनें</li>
                <li>अगर Excel में Edit किया है तो <strong>Save as CSV UTF-8</strong> करें</li>
                <li>"Upload to Database" बटन दबाएँ</li>
            </ol>
        </div>

        <!-- 🆕 Fix Existing Properties Button -->
        <div class="card-conv">
            <h5 class="fw-bold mb-2"><i class="fas fa-tools text-warning me-2"></i> पहले से Upload हुई Properties Fix करें</h5>
            <p class="text-muted mb-3" style="font-size: 0.9rem;">
                अगर किसी Property में <strong>Auction Start Time</strong> या <strong>Auction End Time</strong> N/A दिख रही है,
                तो यह बटन दबाएँ – उन सबमें <strong>Auction Date</strong> वाली तारीख Auto-Set हो जाएगी।
            </p>
            <form method="POST">
                <button type="submit" name="fix_existing" value="1" class="btn-warning-custom">
                    <i class="fas fa-wrench me-2"></i> Fix NULL Start/End Dates
                </button>
            </form>
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
                        <input type="file" name="csv_file" id="csvFile" style="display:none;" required>
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
    function setupUpload(formId, inputId, fileNameId) {
        const form = document.getElementById(formId);
        if (!form) return;
        const area = form.querySelector('.upload-area');
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
    setupUpload('convForm', 'srcFile', 'fileName');
    setupUpload('uploadForm', 'csvFile', 'fileName2');
});
</script>

<?php include 'footer.php'; ?>
