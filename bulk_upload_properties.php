<?php
// ============================================================
// 📤 Bulk Upload Properties – UTF-8 Safe + EMD Auto (Always)
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
// 🔥 HELPER: Clean UTF-8
// ============================================================
function cleanUTF8($str) {
    if ($str === null) return '';
    $str = (string)$str;
    if (!mb_check_encoding($str, 'UTF-8')) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $str);
        if ($converted !== false) { $str = $converted; }
        else { $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8'); }
    }
    $replacements = [
        "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
        "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
        "\xE2\x80\x93" => '-', "\xE2\x80\x94" => '-',
        "\xE2\x80\xA6" => '...', "\xC2\xA0" => ' ',
    ];
    $str = str_replace(array_keys($replacements), array_values($replacements), $str);
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
    return trim($str);
}

// ============================================================
// HELPER: Extract first number
// ============================================================
function extractNumber($str) {
    if (empty($str)) return 0;
    $str = str_replace([',', '₹', ' '], '', $str);
    if (preg_match('/-?\d+(\.\d+)?/', $str, $m)) return (float)$m[0];
    return 0;
}

// ============================================================
// HELPER: Check invalid value
// ============================================================
function isInvalidValue($str) {
    if (empty($str)) return true;
    $lower = strtolower(trim($str));
    $invalid = ['#value!', 'club', 'club case', 'na', 'n/a', '-', 'null'];
    return in_array($lower, $invalid);
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
    ];
    return $map[strtolower($type)] ?? 'Other';
}

// ============================================================
// HELPER: Parse Date
// ============================================================
function parseDate($dateStr) {
    if (empty($dateStr) || trim($dateStr) === '') return null;
    $dateStr = trim($dateStr);
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

// ============================================================
// HELPER: Detect Delimiter
// ============================================================
function detectDelimiter($filepath) {
    $handle = fopen($filepath, 'r');
    $firstLine = fgets($handle);
    fclose($handle);
    if ($firstLine === false) return ',';
    $tabCount = substr_count($firstLine, "\t");
    $commaCount = substr_count($firstLine, ',');
    return ($tabCount > $commaCount) ? "\t" : ',';
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
        if (!in_array($ext, ['csv', 'txt', 'tsv'])) {
            $errors[] = "❌ Only .csv / .txt files are allowed.";
        } else {
            $delimiter = detectDelimiter($file['tmp_name']);
            $handle = fopen($file['tmp_name'], 'r');

            if (!$handle) {
                $errors[] = "❌ Cannot read file.";
            } else {
                $header = fgetcsv($handle, 0, $delimiter);
                if ($header && isset($header[0])) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                }
                $header = array_map('trim', $header);
                $headerLower = array_map('strtolower', $header);

                $required = ['title', 'city', 'price', 'auction_date'];
                foreach ($required as $col) {
                    if (!in_array($col, $headerLower)) {
                        $errors[] = "❌ Missing required column: <strong>$col</strong>";
                    }
                }

                if (empty($errors)) {
                    $colMap = [];
                    foreach ($headerLower as $i => $col) {
                        $colMap[$col] = $i;
                    }

                    $successCount = 0;
                    $skipCount = 0;
                    $failCount = 0;
                    $skipRows = [];
                    $failRows = [];
                    $rowNum = 1;

                    $insertSQL = "
                        INSERT INTO properties (
                            title, description, price, location, city, state, type, bank_name,
                            sqft, possession_type, borrower_name, emd_amount, bid_increment,
                            emd_deadline, auction_start_time, auction_end_time, locality,
                            reserve_price_per_sqft, contact_number, status, auction_date,
                            inspection_date, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ";

                    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $rowNum++;
                        if (count(array_filter($row)) === 0) continue;

                        $row = array_map('cleanUTF8', $row);

                        $getVal = function($col) use ($row, $colMap) {
                            return isset($colMap[$col]) && isset($row[$colMap[$col]]) ? $row[$colMap[$col]] : '';
                        };

                        $title              = $getVal('title');
                        $location           = $getVal('location');
                        $city               = $getVal('city');
                        $state              = $getVal('state');
                        $locality           = $getVal('locality');
                        $type               = $getVal('type');
                        $bank_name          = $getVal('bank_name');
                        $borrower_name      = $getVal('borrower_name');
                        $priceRaw           = $getVal('price');
                        $pricePerSqftRaw    = $getVal('reserve_price_per_sqft');
                        $sqftRaw            = $getVal('sqft');
                        $possession_type    = $getVal('possession_type');
                        $emdRaw             = $getVal('emd_amount');
                        $bidRaw             = $getVal('bid_increment');
                        $auction_start_time = $getVal('auction_start_time');
                        $auction_end_time   = $getVal('auction_end_time');
                        $auction_date_raw   = $getVal('auction_date');
                        $inspection_date_raw = $getVal('inspection_date');
                        $contact_number     = $getVal('contact_number');
                        $statusRaw          = $getVal('status');
                        $description        = $getVal('description');

                        // Skip invalid price (Club cases)
                        if (isInvalidValue($priceRaw)) {
                            $skipCount++;
                            $skipRows[] = "Row $rowNum: Skipped (invalid price: '$priceRaw')";
                            continue;
                        }

                        $price = extractNumber($priceRaw);
                        $pricePerSqft = extractNumber($pricePerSqftRaw);
                        $sqft = extractNumber($sqftRaw);
                        $emd_amount = extractNumber($emdRaw);
                        $bid_increment = extractNumber($bidRaw);

                        if (empty($title) || empty($city) || $price <= 0 || empty($auction_date_raw)) {
                            $failCount++;
                            $failRows[] = "Row $rowNum: Missing required fields";
                            continue;
                        }

                        $auction_date = parseDate($auction_date_raw);
                        if (!$auction_date) {
                            $failCount++;
                            $failRows[] = "Row $rowNum: Invalid auction_date '$auction_date_raw'";
                            continue;
                        }

                        $type = normalizeType($type);
                        $possession_type = in_array(strtolower($possession_type), ['physical', 'symbolic'])
                                            ? ucfirst(strtolower($possession_type))
                                            : 'Physical';
                        $status = in_array(strtolower($statusRaw), ['available', 'sold', 'pending'])
                                    ? strtolower($statusRaw)
                                    : 'available';

                        $inspection_date = parseDate($inspection_date_raw);

                        // ============================================================
                        // 🔥 EMD DEADLINE = AUCTION DATE − 1 DAY (हमेशा)
                        // चाहे CSV में कुछ भी हो, हम इसे Override करेंगे
                        // ============================================================
                        $emd_deadline_parsed = null;
                        if (!empty($auction_date)) {
                            // Auction Date से 1 दिन पहले, शाम 5:00 बजे
                            $emd_deadline_parsed = date('Y-m-d 17:00:00', strtotime($auction_date . ' -1 day'));
                        }

                        // Auction Start / End Time (अगर CSV में हो)
                        $auction_start_parsed = null;
                        if (!empty($auction_start_time) && !isInvalidValue($auction_start_time)) {
                            $ts = strtotime($auction_start_time);
                            if ($ts !== false) $auction_start_parsed = date('Y-m-d H:i:s', $ts);
                        }
                        $auction_end_parsed = null;
                        if (!empty($auction_end_time) && !isInvalidValue($auction_end_time)) {
                            $ts = strtotime($auction_end_time);
                            if ($ts !== false) $auction_end_parsed = date('Y-m-d H:i:s', $ts);
                        }

                        try {
                            $stmt = $pdo->prepare($insertSQL);
                            $stmt->execute([
                                $title, $description, $price, $location, $city, $state, $type, $bank_name,
                                $sqft, $possession_type, $borrower_name, $emd_amount, $bid_increment,
                                $emd_deadline_parsed, $auction_start_parsed, $auction_end_parsed, $locality,
                                $pricePerSqft, $contact_number, $status, $auction_date,
                                $inspection_date
                            ]);
                            $successCount++;
                        } catch (PDOException $e) {
                            $failCount++;
                            $failRows[] = "Row $rowNum: " . cleanUTF8($e->getMessage());
                        }
                    }

                    $report = [
                        'success' => $successCount,
                        'skip' => $skipCount,
                        'fail' => $failCount,
                        'skip_rows' => $skipRows,
                        'fail_rows' => $failRows,
                        'delimiter' => ($delimiter === "\t") ? 'Tab' : 'Comma'
                    ];
                }
                fclose($handle);
            }
        }
    }
}

include 'header.php';
?>

<style>
    .upload-card { background: #ffffff; border-radius: 24px; padding: 32px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); border: 1px solid #e8edf4; }
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
            <h5>✅ Upload Completed! <small class="text-muted">(Delimiter: <?= $report['delimiter'] ?>)</small></h5>
            <p class="mb-1"><strong>Successfully Added:</strong> <span class="text-success fw-bold"><?= $report['success'] ?></span></p>
            <p class="mb-1"><strong>Skipped (Club/Invalid):</strong> <span class="text-warning fw-bold"><?= $report['skip'] ?></span></p>
            <p class="mb-0"><strong>Failed:</strong> <span class="text-danger fw-bold"><?= $report['fail'] ?></span></p>
        </div>

        <?php if (!empty($report['skip_rows'])): ?>
            <div class="report-card report-warning">
                <h5>⚠️ Skipped Rows (Club / Invalid)</h5>
                <ul class="mb-0" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach (array_slice($report['skip_rows'], 0, 30) as $s): ?>
                        <li><?= htmlspecialchars($s) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($report['skip_rows']) > 30): ?>
                        <li><em>... और <?= count($report['skip_rows']) - 30 ?> और Rows</em></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($report['fail_rows'])): ?>
            <div class="report-card report-error">
                <h5>❌ Failed Rows (पहली 30 दिखाई जा रही हैं)</h5>
                <ul class="mb-0" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach (array_slice($report['fail_rows'], 0, 30) as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($report['fail_rows']) > 30): ?>
                        <li><em>... और <?= count($report['fail_rows']) - 30 ?> और Rows</em></li>
                    <?php endif; ?>
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
            <li><strong style="color: #dc2626;">EMD Deadline हमेशा Auction Date से 1 दिन पहले (शाम 5:00 बजे) Set होगी</strong></li>
            <li>Smart Quotes (<code>" " ' '</code>) और En-dash (<code>–</code>) Auto-Fix होंगे</li>
            <li>Windows-1252 Characters UTF-8 में Convert होंगे</li>
            <li>Club Case वाली Rows Auto-Skip होंगी</li>
            <li>Excel में Save करते समय <strong>CSV UTF-8</strong> ही चुनें</li>
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
                <h4>Drag & Drop CSV File Here</h4>
                <p>या क्लिक करके फ़ाइल चुनें</p>
                <input type="file" name="csv_file" id="csvFile" accept=".csv,.txt,.tsv" style="display:none;" required>
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
