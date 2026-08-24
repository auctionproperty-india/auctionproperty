<?php
// ============================================================
// sales_lead_upload.php – CSV & XLSX Upload (No Libraries Required)
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

// ============================================================
// 🔥 CORE FUNCTION: Parse XLSX without external libraries
// ============================================================
function parseXLSX($filePath) {
    $data = [];
    if (!class_exists('ZipArchive')) {
        return ['error' => 'ZipArchive extension is required to read XLSX files.'];
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['error' => 'Cannot open XLSX file.'];
    }
    // Read shared strings (for text values)
    $sharedStrings = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml !== false) {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $strings = $dom->getElementsByTagName('si');
        foreach ($strings as $si) {
            // Get all <t> elements (there might be multiple for formatted text)
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
    // Read sheet data
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
                    // Shared string
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

// ============================================================
// Handle Upload
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lead_file'])) {
    $file = $_FILES['lead_file'];
    $tmp_name = $file['tmp_name'];
    $name = $file['name'];
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $assigned_to = $_POST['assigned_to'] ?? $team_id;

    $rows = [];

    // --- CSV ---
    if ($extension === 'csv') {
        if (($handle = fopen($tmp_name, "r")) !== FALSE) {
            $header = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== FALSE) {
                $rows[] = array_pad($data, 7, '');
            }
            fclose($handle);
        } else {
            $message = "❌ Failed to open CSV file.";
        }
    }
    // --- XLSX ---
    elseif ($extension === 'xlsx') {
        $result = parseXLSX($tmp_name);
        if (isset($result['error'])) {
            $message = "❌ " . $result['error'];
        } else {
            $rows = $result['data'];
            // Remove header row if present
            if (!empty($rows)) {
                array_shift($rows);
            }
        }
    }
    // --- XLS (older Excel) ---
    elseif ($extension === 'xls') {
        $message = "❌ .xls files are not supported. Please save the file as .xlsx or .csv and upload again.";
    }
    else {
        $message = "❌ Unsupported file type. Please upload CSV or XLSX.";
    }

    // Insert rows
    if (!empty($rows) && empty($message)) {
        $inserted = 0;
        foreach ($rows as $row) {
            list($name_col, $phone, $email, $address, $city, $state, $source) = array_pad($row, 7, '');
            if (empty($name_col) || empty($phone)) continue;
            $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, address, city, state, lead_source, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name_col, $phone, $email, $address, $city, $state, $source ?: 'Excel Upload', $assigned_to, $_SESSION['user_id']]);
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
    <p>Upload a <strong>CSV</strong> or <strong>Excel (.xlsx)</strong> file with columns:<br>
    <code>Name, Phone, Email, Address, City, State, Source</code></p>

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

        if (!empty($name) && !empty($phone)) {
            $stmt = $pdo->prepare("INSERT INTO leads (name, phone, email, address, city, state, lead_source, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $address, $city, $state, $source, $assigned_to, $_SESSION['user_id']]);
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
