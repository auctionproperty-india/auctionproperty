<?php
// ============================================================
// 📤 Bulk Upload Properties – Excel/CSV
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'sub_admin');

// ---- Helper: parse date from multiple formats ----
function parseDateFlexible($dateStr) {
    if (empty($dateStr)) return null;
    $dateStr = trim($dateStr);
    // Remove time portion if any (for date-only columns)
    $parts = explode(' ', $dateStr);
    $datePart = $parts[0];

    // Try DD/MM/YYYY
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $datePart, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // Try YYYY-MM-DD
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $datePart, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    // Fallback: strtotime
    $ts = strtotime($dateStr);
    if ($ts !== false) return date('Y-m-d', $ts);
    return null;
}

// ---- Handle Upload ----
$report = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error (Code: {$file['error']})";
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) {
            $errors[] = "❌ Only .csv files are allowed. (Excel में Save As → CSV करके Upload करें)";
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                $errors[] = "❌ Cannot read file.";
            } else {
                // Read header row
                $header = fgetcsv($handle);
                // Remove BOM if present
                if ($header && isset($header[0])) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
                }

                // Required columns check
                $required = ['title', 'city', 'price', 'auction_date'];
                $header = array_map('trim', $header);
                $headerLower = array_map('strtolower', $header);

                foreach ($required as $col) {
                    if (!in_array($col, $headerLower)) {
                        $errors[] = "❌ Missing required column: <strong>$col</strong>";
                    }
                }

                if (empty($errors)) {
                    // Map column names to indexes
                    $colMap = [];
                    foreach ($headerLower as $i => $col) {
                        $colMap[$col] = $i;
                    }

                    $successCount = 0;
                    $failCount = 0;
                    $failRows = [];
                    $rowNum = 1; // header is row 1

                    // Prepare INSERT
                    $stmt = $pdo->prepare("
                        INSERT INTO properties (
                            title, description, price, location, city, state, type, bank_name,
                            sqft, possession_type, borrower_name, emd_amount, bid_increment,
                            emd_deadline, auction_start_time, auction_end_time, locality,
                            reserve_price_per_sqft, contact_number, status, auction_date,
                            inspection_date, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNum++;

                        // Skip empty rows
                        if (count(array_filter($row)) === 0) continue;

                        // Helper to get value by column name
                        $getVal = function($col) use ($row, $colMap) {
                            return isset($colMap[$col]) && isset($row[$colMap[$col]]) 
                                ? trim($row[$colMap[$col]]) 
                                : '';
                        };

                        $title              = $getVal('title');
                        $location           = $getVal('location');
                        $city               = $getVal('city');
                        $state              = $getVal('state');
                        $locality           = $getVal('locality');
                        $type               = $getVal('type');
                        $bank_name          = $getVal('bank_name');
                        $borrower_name      = $getVal('borrower_name');
                        $price              = (float)$getVal('price');
                        $reserve_price_per_sqft = (float)$getVal('reserve_price_per_sqft');
                        $sqft               = (float)$getVal('sqft');
                        $possession_type    = $getVal('possession_type');
                        $emd_amount         = (float)$getVal('emd_amount');
                        $bid_increment      = (float)$getVal('bid_increment');
                        $emd_deadline       = $getVal('emd_deadline');
                        $auction_start_time = $getVal('auction_start_time');
                        $auction_end_time   = $getVal('auction_end_time');
                        $auction_date_raw   = $getVal('auction_date');
                        $inspection_date_raw = $getVal('inspection_date');
                        $contact_number     = $getVal('contact_number');
                        $status             = $getVal('status') ?: 'available';
                        $description        = $getVal('description');

                        // Validation
                        if (empty($title) || empty($city) || $price <= 0 || empty($auction_date_raw)) {
                            $failCount++;
                            $failRows[] = "Row $rowNum: Missing required fields (title, city, price, auction_date)";
                            continue;
                        }

                        // Parse auction_date
                        $auction_date = parseDateFlexible($auction_date_raw);
                        if (!$auction_date) {
                            $failCount++;
                            $failRows[] = "Row $rowNum: Invalid auction_date format ('$auction_date_raw')";
                            continue;
                        }

                        $inspection_date = parseDateFlexible($inspection_date_raw);

                        try {
                            $stmt->execute([
                                $title, $description, $price, $location, $city, $state, $type, $bank_name,
                                $sqft, $possession_type, $borrower_name, $emd_amount, $bid_increment,
                                $emd_deadline, $auction_start_time, $auction_end_time, $locality,
                                $reserve_price_per_sqft, $contact_number, $status, $auction_date,
                                $inspection_date
                            ]);
                            $successCount++;
                        } catch (PDOException $e) {
                            $failCount++;
                            $failRows[] = "Row $rowNum: DB Error – " . $e->getMessage();
                        }
                    }

                    $report = [
                        'success' => $successCount,
                        'fail' => $failCount,
                        'fail_rows' => $failRows,
                        'total' => $rowNum - 1
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
    .upload-card {
        background: #ffffff;
        border-radius: 24px;
        padding: 32px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.06);
        border: 1px solid #e8edf4;
    }
    .upload-area {
        border: 3px dashed #b8cbe8;
        border-radius: 20px;
        padding: 40px 20px;
        text-align: center;
        background: #f8faff;
        transition: all 0.3s;
        cursor: pointer;
    }
    .upload-area:hover {
        border-color: #2563eb;
        background: #eff6ff;
    }
    .upload-area.dragover {
        border-color: #10b981;
        background: #ecfdf5;
    }
    .upload-area i {
        font-size: 3.5rem;
        color: #2563eb;
        margin-bottom: 12px;
    }
    .upload-area h4 {
        color: #0f172a;
        font-weight: 700;
    }
    .upload-area p {
        color: #64748b;
        margin: 0;
    }
    .btn-upload {
        background: linear-gradient(135deg, #1e40af, #2563eb);
        color: #fff;
        border: none;
        padding: 14px 40px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 1.1rem;
        box-shadow: 0 6px 20px rgba(37,99,235,0.25);
        transition: all 0.3s;
    }
    .btn-upload:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(37,99,235,0.35);
        color: #fff;
    }
    .btn-download {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #0f172a;
        border: none;
        padding: 12px 32px;
        border-radius: 50px;
        font-weight: 700;
        text-decoration: none;
        display: inline-block;
        box-shadow: 0 6px 20px rgba(251,191,36,0.25);
        transition: all 0.3s;
    }
    .btn-download:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(251,191,36,0.35);
        color: #0f172a;
    }
    .info-box {
        background: #eff6ff;
        border-left: 5px solid #2563eb;
        border-radius: 12px;
        padding: 18px 22px;
        margin-bottom: 24px;
    }
    .info-box h6 {
        color: #1e40af;
        font-weight: 700;
        margin-bottom: 10px;
    }
    .info-box ul {
        margin: 0;
        padding-left: 20px;
        color: #334155;
        font-size: 0.9rem;
    }
    .info-box code {
        background: #dbeafe;
        color: #1e40af;
        padding: 2px 8px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
    }
    .report-card {
        border-radius: 16px;
        padding: 22px;
        margin-bottom: 20px;
    }
    .report-success {
        background: #ecfdf5;
        border-left: 5px solid #10b981;
    }
    .report-error {
        background: #fef2f2;
        border-left: 5px solid #dc2626;
    }
    .report-card h5 {
        font-weight: 700;
        margin-bottom: 8px;
    }
</style>

<div class="container-fluid mt-4">
    <h1 class="mb-4"><i class="fas fa-file-excel text-success me-2"></i> Bulk Upload Properties</h1>

    <?php if ($report): ?>
        <div class="report-card report-success">
            <h5>✅ Upload Completed!</h5>
            <p class="mb-1"><strong>Total Rows:</strong> <?= $report['total'] ?></p>
            <p class="mb-1"><strong>Successfully Added:</strong> <span class="text-success fw-bold"><?= $report['success'] ?></span></p>
            <p class="mb-0"><strong>Failed:</strong> <span class="text-danger fw-bold"><?= $report['fail'] ?></span></p>
        </div>

        <?php if (!empty($report['fail_rows'])): ?>
            <div class="report-card report-error">
                <h5>❌ Failed Rows (Error Details)</h5>
                <ul class="mb-0">
                    <?php foreach ($report['fail_rows'] as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <a href="properties.php" class="btn btn-primary mb-4"><i class="fas fa-list me-2"></i> View All Properties</a>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $e): ?>
                <div><?= $e ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Info Box -->
    <div class="info-box">
        <h6><i class="fas fa-info-circle me-2"></i> How to Use</h6>
        <ul>
            <li><strong>Step 1:</strong> नीचे दिए गए बटन से <code>Sample CSV Template</code> डाउनलोड करें</li>
            <li><strong>Step 2:</strong> उसे <strong>Excel / Google Sheets</strong> में खोलें और अपनी Properties का Data भरें</li>
            <li><strong>Step 3:</strong> Excel में <strong>File → Save As → CSV (Comma delimited)</strong> चुनकर सेव करें</li>
            <li><strong>Step 4:</strong> नीचे Upload Box में CSV फ़ाइल को Drag करें या Click करके चुनें</li>
            <li><strong>Step 5:</strong> <strong>Upload</strong> बटन दबाएँ – आपकी सारी Properties एक साथ Add हो जाएँगी!</li>
        </ul>
    </div>

    <!-- Download Template -->
    <div class="text-center mb-4">
        <a href="download_template.php" class="btn-download">
            <i class="fas fa-download me-2"></i> Download Sample CSV Template
        </a>
    </div>

    <!-- Upload Card -->
    <div class="upload-card">
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="upload-area" id="uploadArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <h4>Drag & Drop CSV File Here</h4>
                <p>या क्लिक करके फ़ाइल चुनें</p>
                <input type="file" name="csv_file" id="csvFile" accept=".csv" style="display:none;" required>
                <div id="fileName" class="mt-3 fw-bold text-success"></div>
            </div>
            <div class="text-center mt-4">
                <button type="submit" class="btn-upload">
                    <i class="fas fa-upload me-2"></i> Upload Properties
                </button>
            </div>
        </form>
    </div>

    <!-- Column Format Reference -->
    <div class="upload-card mt-4">
        <h5 class="fw-bold mb-3"><i class="fas fa-table me-2"></i> Column Format Reference</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead class="table-primary">
                    <tr>
                        <th>Column</th>
                        <th>Required?</th>
                        <th>Format / Example</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><code>title</code></td><td><span class="badge bg-danger">Yes</span></td><td>Shop in Govind Nagar, Mathura</td></tr>
                    <tr><td><code>location</code></td><td>No</td><td>Full Address</td></tr>
                    <tr><td><code>city</code></td><td><span class="badge bg-danger">Yes</span></td><td>Mathura</td></tr>
                    <tr><td><code>state</code></td><td>No</td><td>Uttar Pradesh</td></tr>
                    <tr><td><code>locality</code></td><td>No</td><td>Govind Nagar</td></tr>
                    <tr><td><code>type</code></td><td>No</td><td>Flat / Plot / Shop / Land / House / Car/Vehicle / Commercial / Office / Row House / Bungalow / Other</td></tr>
                    <tr><td><code>bank_name</code></td><td>No</td><td>SMFG India Home Finance</td></tr>
                    <tr><td><code>borrower_name</code></td><td>No</td><td>Sameer Khan</td></tr>
                    <tr><td><code>price</code></td><td><span class="badge bg-danger">Yes</span></td><td>880000 (सिर्फ Number)</td></tr>
                    <tr><td><code>reserve_price_per_sqft</code></td><td>No</td><td>0</td></tr>
                    <tr><td><code>sqft</code></td><td>No</td><td>167.17</td></tr>
                    <tr><td><code>possession_type</code></td><td>No</td><td>Physical / Symbolic</td></tr>
                    <tr><td><code>emd_amount</code></td><td>No</td><td>88000</td></tr>
                    <tr><td><code>bid_increment</code></td><td>No</td><td>25000</td></tr>
                    <tr><td><code>emd_deadline</code></td><td>No</td><td>19/08/2026 12:00 PM</td></tr>
                    <tr><td><code>auction_start_time</code></td><td>No</td><td>20/08/2026 11:00 AM</td></tr>
                    <tr><td><code>auction_end_time</code></td><td>No</td><td>20/08/2026 01:00 PM</td></tr>
                    <tr><td><code>auction_date</code></td><td><span class="badge bg-danger">Yes</span></td><td>20/08/2026</td></tr>
                    <tr><td><code>inspection_date</code></td><td>No</td><td>18/08/2026</td></tr>
                    <tr><td><code>contact_number</code></td><td>No</td><td>8878190275</td></tr>
                    <tr><td><code>status</code></td><td>No</td><td>available / sold / pending (Default: available)</td></tr>
                    <tr><td><code>description</code></td><td>No</td><td>Property description</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const csvFile = document.getElementById('csvFile');
    const fileName = document.getElementById('fileName');
    const form = document.getElementById('uploadForm');

    // Click to open file dialog
    uploadArea.addEventListener('click', function() {
        csvFile.click();
    });

    // Drag & Drop
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
    });
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            csvFile.files = e.dataTransfer.files;
            showFileName(e.dataTransfer.files[0].name);
        }
    });

    // File select
    csvFile.addEventListener('change', function() {
        if (this.files.length > 0) {
            showFileName(this.files[0].name);
        }
    });

    function showFileName(name) {
        fileName.innerHTML = '<i class="fas fa-check-circle"></i> Selected: ' + name;
    }
});
</script>

<?php include 'footer.php'; ?>
