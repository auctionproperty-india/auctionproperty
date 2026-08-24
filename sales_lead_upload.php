<?php
// ============================================================
// sales_lead_upload.php – Auto‑Map Any CSV/XLSX Format
// ============================================================

require_once __DIR__ . '/db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sales')) {
    header("Location: login.php");
    exit;
}
include 'header.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$team_id = null;
if ($role == 'sales') {
    $stmt = $pdo->prepare("SELECT id FROM sales_team WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $team_id = $stmt->fetchColumn();
    if (!$team_id) {
        echo "<div class='alert alert-danger'>You are not assigned to any sales team.</div>";
        include 'footer.php';
        exit;
    }
}

if ($role == 'admin') {
    $sales_users = $pdo->query("SELECT st.id, u.name FROM sales_team st JOIN users u ON st.user_id = u.id")->fetchAll();
}

$message = '';
$count = 0;

// ---------- Auto‑detect delimiter for CSV ----------
function detectDelimiter($filePath) {
    $fh = fopen($filePath, 'r');
    $firstLine = fgets($fh);
    fclose($fh);
    $delimiters = [',', "\t", ';', '|'];
    $results = [];
    foreach ($delimiters as $d) {
        $count = substr_count($firstLine, $d);
        if ($count > 0) {
            $results[$d] = $count;
        }
    }
    if (empty($results)) return ',';
    arsort($results);
    return key($results);
}

// ---------- Parse XLSX without external libraries ----------
function parseXLSX($filePath) {
    $data = [];
    if (!class_exists('ZipArchive')) {
        return ['error' => 'ZipArchive extension is required.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['error' => 'Cannot open XLSX file.'];
    }
    $sharedStrings = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml !== false) {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $strings = $dom->getElementsByTagName('si');
        foreach ($strings as $si) {
            $text = '';
            $children = $si->childNodes;
            foreach ($children as $child) {
                if ($child instanceof DOMElement && $child->tagName === 't') {
                    $text .= $child->nodeValue;
                }
            }
            $sharedStrings[] = $text;
        }
    }
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($xml === false) {
        return ['error' => 'Sheet1 not found.'];
    }
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $rows = $dom->getElementsByTagName('row');
    foreach ($rows as $row) {
        $rowData = [];
        $cells = $row->getElementsByTagName('c');
        foreach ($cells as $cell) {
            $type = $cell->getAttribute('t');
            $value = '';
            $vNode = $cell->getElementsByTagName('v')->item(0);
            if ($vNode) {
                if ($type === 's') {
                    $idx = (int)$vNode->nodeValue;
                    $value = $sharedStrings[$idx] ?? '';
                } else {
                    $value = $vNode->nodeValue;
                }
            }
            $rowData[] = $value;
        }
        if (!empty($rowData)) {
            $data[] = $rowData;
        }
    }
    $zip->close();
    return ['data' => $data];
}

// ---------- Normalize header column name ----------
function normalizeHeader($col) {
    $col = trim($col);
    $col = str_replace([' ', '_', '-', '.'], '', $col);
    return strtolower($col);
}

// ---------- Handle file upload ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lead_file'])) {
    $file = $_FILES['lead_file'];
    $tmp_name = $file['tmp_name'];
    $name = $file['name'];
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $assigned_to = $_POST['assigned_to'] ?? $team_id;

    $rows = [];
    $delimiter = ',';

    // ---------- CSV ----------
    if ($extension === 'csv') {
        $delimiter = detectDelimiter($tmp_name);
        if (($handle = fopen($tmp_name, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        } else {
            $message = "❌ Failed to open CSV file.";
        }
    }
    // ---------- XLSX ----------
    elseif ($extension === 'xlsx') {
        $result = parseXLSX($tmp_name);
        if (isset($result['error'])) {
            $message = "❌ " . $result['error'];
        } else {
            $rows = $result['data'];
        }
    }
    else {
        $message = "❌ Unsupported file type. Please upload CSV or XLSX.";
    }

    // ---------- Process rows with header mapping ----------
    if (!empty($rows) && empty($message)) {
        // Extract header (first row)
        $header = array_shift($rows);
        // Normalize header columns
        $headerMap = [];
        foreach ($header as $idx => $col) {
            $normal = normalizeHeader($col);
            $headerMap[$idx] = $normal;
        }

        // Define target fields and their aliases
        $fieldMap = [
            'name' => ['full_name', 'name', 'customer', 'client', 'fullname'],
            'phone' => ['phone_number', 'phone', 'mobile', 'contact', 'phoneno'],
            'email' => ['email', 'mail', 'e-mail'],
            'address' => ['address', 'addr', 'location'],
            'city' => ['city', 'town', 'district'],
            'state' => ['state', 'province', 'region'],
            'source' => ['source', 'lead_source', 'origin'],
        ];

        // Determine column indexes
        $colIndex = [];
        foreach ($fieldMap as $field => $aliases) {
            $found = false;
            foreach ($headerMap as $idx => $normal) {
                if (in_array($normal, $aliases)) {
                    $colIndex[$field] = $idx;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $colIndex[$field] = null; // no column found
            }
        }

        // Also capture extra columns (all others) to store as notes
        $extraColumns = [];
        foreach ($headerMap as $idx => $normal) {
            $isMapped = false;
            foreach ($fieldMap as $field => $aliases) {
                if (in_array($normal, $aliases)) {
                    $isMapped = true;
                    break;
                }
            }
            if (!$isMapped) {
                $extraColumns[$idx] = $normal;
            }
        }

        // Insert rows
        $inserted = 0;
        foreach ($rows as $row) {
            // If row has fewer columns than header, pad
            $row = array_pad($row, count($header), '');

            // Extract mapped fields
            $name    = isset($colIndex['name']) && isset($row[$colIndex['name']]) ? trim($row[$colIndex['name']]) : '';
            $phone   = isset($colIndex['phone']) && isset($row[$colIndex['phone']]) ? trim($row[$colIndex['phone']]) : '';
            $email   = isset($colIndex['email']) && isset($row[$colIndex['email']]) ? trim($row[$colIndex['email']]) : '';
            $address = isset($colIndex['address']) && isset($row[$colIndex['address']]) ? trim($row[$colIndex['address']]) : '';
            $city    = isset($colIndex['city']) && isset($row[$colIndex['city']]) ? trim($row[$colIndex['city']]) : '';
            $state   = isset($colIndex['state']) && isset($row[$colIndex['state']]) ? trim($row[$colIndex['state']]) : '';
            $source  = isset($colIndex['source']) && isset($row[$colIndex['source']]) ? trim($row[$colIndex['source']]) : 'File Upload';

            // Build notes from extra columns
            $notes = '';
            foreach ($extraColumns as $idx => $colName) {
                if (isset($row[$idx]) && trim($row[$idx]) !== '') {
                    $notes .= $colName . ': ' . trim($row[$idx]) . ' | ';
                }
            }
            $notes = rtrim($notes, ' | ');

            // Skip if name or phone is empty
            if (empty($name) || empty($phone)) continue;

            // Insert
            $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, address, city, state, lead_source, assigned_to, created_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $address, $city, $state, $source ?: 'File Upload', $assigned_to, $_SESSION['user_id'], $notes]);
            $inserted++;
        }
        $message = "✅ $inserted leads uploaded successfully!";
    } elseif (empty($message)) {
        $message = "⚠️ No data rows found in the file.";
    }
}
?>

<div class="container-fluid mt-4">
    <h1>📥 Upload Leads</h1>
    <p>Upload a <strong>CSV</strong> or <strong>Excel (.xlsx)</strong> file with any column format.<br>
    The script will automatically detect and map columns like <code>Name</code>, <code>Phone</code>, <code>City</code>, etc.</p>

    <?php if ($message): ?>
        <div class="alert <?= strpos($message, '✅') !== false ? 'alert-success' : 'alert-danger' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Choose File</label>
                        <input type="file" name="lead_file" class="form-control" accept=".csv,.xlsx" required>
                        <small class="text-muted">Supported: .csv, .xlsx</small>
                    </div>
                    <?php if ($role == 'admin'): ?>
                    <div class="col-md-6">
                        <label class="form-label">Assign to (Sales Person)</label>
                        <select name="assigned_to" class="form-control">
                            <?php foreach ($sales_users as $su): ?>
                                <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="assigned_to" value="<?= $team_id ?>">
                    <?php endif; ?>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Upload & Import</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ====== MANUAL SINGLE LEAD ENTRY ====== -->
    <hr>
    <h3>➕ Or Add a Single Lead Manually</h3>
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_add'])) {
        $name = trim($_POST['manual_name']);
        $phone = trim($_POST['manual_phone']);
        $email = trim($_POST['manual_email']);
        $address = trim($_POST['manual_address']);
        $city = trim($_POST['manual_city']);
        $state = trim($_POST['manual_state']);
        $source = trim($_POST['manual_source']) ?: 'Manual Entry';
        $assigned_to = ($role == 'admin') ? $_POST['manual_assigned_to'] : $team_id;
        $notes = trim($_POST['manual_notes']);

        if (!empty($name) && !empty($phone)) {
            $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, address, city, state, lead_source, assigned_to, created_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $address, $city, $state, $source, $assigned_to, $_SESSION['user_id'], $notes]);
            echo "<div class='alert alert-success'>✅ Lead added manually!</div>";
        } else {
            echo "<div class='alert alert-danger'>Name and Phone are required.</div>";
        }
    }
    ?>
    <form method="POST">
        <input type="hidden" name="manual_add" value="1">
        <div class="row g-3">
            <div class="col-md-4"><label>Name *</label><input type="text" name="manual_name" class="form-control" required></div>
            <div class="col-md-4"><label>Phone *</label><input type="text" name="manual_phone" class="form-control" required></div>
            <div class="col-md-4"><label>Email</label><input type="email" name="manual_email" class="form-control"></div>
            <div class="col-md-4"><label>City</label><input type="text" name="manual_city" class="form-control"></div>
            <div class="col-md-4"><label>State</label><input type="text" name="manual_state" class="form-control"></div>
            <div class="col-md-4"><label>Address</label><input type="text" name="manual_address" class="form-control"></div>
            <div class="col-md-4"><label>Source</label><input type="text" name="manual_source" class="form-control" placeholder="e.g., Call, Website"></div>
            <div class="col-md-4"><label>Notes</label><input type="text" name="manual_notes" class="form-control" placeholder="Additional info"></div>
            <?php if ($role == 'admin'): ?>
            <div class="col-md-4">
                <label>Assign to</label>
                <select name="manual_assigned_to" class="form-control">
                    <?php foreach ($sales_users as $su): ?>
                        <option value="<?= $su['id'] ?>"><?= htmlspecialchars($su['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12"><button type="submit" class="btn btn-success">Add Lead</button></div>
        </div>
    </form>
</div>
<?php include 'footer.php'; ?>
