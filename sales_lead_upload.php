<?php
// ============================================================
// 📤 Sales CRM – Bulk Upload Leads (CSV)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['sales', 'admin'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] == 'admin');
$message = '';
$message_type = '';
$imported = 0;
$failed = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "❌ File upload error!";
        $message_type = "danger";
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) {
            $message = "❌ Only CSV files allowed!";
            $message_type = "danger";
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                $message = "❌ Cannot read file!";
                $message_type = "danger";
            } else {
                // Detect header
                $header = fgetcsv($handle);
                $header = array_map(function($h) { return strtolower(trim($h)); }, $header);
                
                // Map columns
                $col_map = [
                    'name' => array_search('name', $header),
                    'phone' => array_search('phone', $header),
                    'email' => array_search('email', $header),
                    'city' => array_search('city', $header),
                    'state' => array_search('state', $header),
                    'property_type' => array_search('property_type', $header),
                    'budget_min' => array_search('budget_min', $header),
                    'budget_max' => array_search('budget_max', $header),
                    'source' => array_search('source', $header),
                    'priority' => array_search('priority', $header),
                    'notes' => array_search('notes', $header),
                ];
                
                if ($col_map['name'] === false || $col_map['phone'] === false) {
                    $message = "❌ CSV must have 'name' and 'phone' columns!";
                    $message_type = "danger";
                } else {
                    $pdo->beginTransaction();
                    try {
                        $row_num = 1;
                        while (($row = fgetcsv($handle)) !== false) {
                            $row_num++;
                            
                            $name = trim($row[$col_map['name']] ?? '');
                            $phone = trim($row[$col_map['phone']] ?? '');
                            
                            if (empty($name) || empty($phone)) {
                                $failed++;
                                $errors[] = "Row $row_num: Missing name or phone";
                                continue;
                            }
                            
                            $email = $col_map['email'] !== false ? trim($row[$col_map['email']] ?? '') : '';
                            $city = $col_map['city'] !== false ? trim($row[$col_map['city']] ?? '') : '';
                            $state = $col_map['state'] !== false ? trim($row[$col_map['state']] ?? '') : '';
                            $property_type = $col_map['property_type'] !== false ? trim($row[$col_map['property_type']] ?? '') : '';
                            $budget_min = ($col_map['budget_min'] !== false && !empty($row[$col_map['budget_min']])) ? (float)$row[$col_map['budget_min']] : null;
                            $budget_max = ($col_map['budget_max'] !== false && !empty($row[$col_map['budget_max']])) ? (float)$row[$col_map['budget_max']] : null;
                            $source = ($col_map['source'] !== false && !empty($row[$col_map['source']])) ? trim($row[$col_map['source']]) : 'Bulk Upload';
                            $priority = ($col_map['priority'] !== false && !empty($row[$col_map['priority']])) ? strtolower(trim($row[$col_map['priority']])) : 'medium';
                            $notes = $col_map['notes'] !== false ? trim($row[$col_map['notes']] ?? '') : '';
                            
                            // Validate priority
                            if (!in_array($priority, ['low', 'medium', 'high'])) $priority = 'medium';
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO sales_leads 
                                (name, email, phone, city, state, property_type, budget_min, budget_max, 
                                 source, status, priority, notes, assigned_to, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, ?)
                            ");
                            $stmt->execute([$name, $email, $phone, $city, $state, $property_type, 
                                           $budget_min, $budget_max, $source, $priority, $notes, $user_id, $user_id]);
                            $imported++;
                        }
                        $pdo->commit();
                        $message = "✅ Imported <strong>$imported</strong> leads successfully!";
                        if ($failed > 0) $message .= " <span class='text-danger'>($failed failed)</span>";
                        $message_type = "success";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = "❌ Import failed: " . $e->getMessage();
                        $message_type = "danger";
                    }
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
        background: #fff;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        border: 2px dashed #cbd5e1;
        text-align: center;
        transition: all 0.3s;
    }
    .upload-card:hover { border-color: #2563eb; background: #f8faff; }
    .upload-icon {
        font-size: 4rem;
        color: #2563eb;
        margin-bottom: 20px;
    }
    .sample-table {
        width: 100%;
        font-size: 0.78rem;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .sample-table th {
        background: #1e293b; color: #fff;
        padding: 8px 10px; font-size: 0.68rem;
        text-align: left;
    }
    .sample-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0"><i class="fas fa-file-upload me-2 text-success"></i> Bulk Upload Leads</h3>
        <a href="sales_leads.php" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show"><?= $message ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-warning">
            <strong>Errors:</strong>
            <ul class="mb-0" style="font-size:0.85rem;">
                <?php foreach (array_slice($errors, 0, 10) as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
                <?php if (count($errors) > 10): ?>
                    <li>...and <?= count($errors) - 10 ?> more</li>
                <?php endif; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-7">
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-card">
                    <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <h5 class="fw-bold">Choose CSV File</h5>
                    <p class="text-muted small mb-3">Upload a CSV file with lead information</p>
                    <input type="file" name="csv_file" class="form-control mb-3" accept=".csv,.txt" required style="max-width:400px; margin:0 auto;">
                    <button type="submit" class="btn btn-success rounded-pill px-5">
                        <i class="fas fa-upload me-2"></i> Upload & Import
                    </button>
                </div>
            </form>
        </div>

        <div class="col-md-5">
            <div class="card border-0 shadow-sm rounded-4 p-3">
                <h6 class="fw-bold mb-2"><i class="fas fa-info-circle text-primary me-2"></i> CSV Format</h6>
                <p class="text-muted small mb-2">Your CSV must have these columns:</p>
                <table class="sample-table">
                    <thead>
                        <tr>
                            <th>name</th>
                            <th>phone</th>
                            <th>email</th>
                            <th>city</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Rahul Sharma</td><td>9876543210</td><td>rahul@x.com</td><td>Indore</td></tr>
                        <tr><td>Priya Verma</td><td>9876543211</td><td>priya@x.com</td><td>Bhopal</td></tr>
                    </tbody>
                </table>
                <div class="mt-3 small text-muted">
                    <strong>Optional columns:</strong> state, property_type, budget_min, budget_max, source, priority, notes
                </div>
                <a href="data:text/csv;charset=utf-8,name,phone,email,city,state,property_type,budget_min,budget_max,source,priority,notes%0ARahul Sharma,9876543210,rahul@example.com,Indore,MP,Flat,2000000,3000000,Website,high,Looking for 2BHK" 
                   download="sales_leads_sample.csv" class="btn btn-outline-primary btn-sm mt-3 w-100 rounded-pill">
                    <i class="fas fa-download me-1"></i> Download Sample CSV
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
